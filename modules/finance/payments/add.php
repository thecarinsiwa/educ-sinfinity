<?php
/**
 * Module de gestion financière - Ajouter un paiement
 * Application de gestion scolaire - République Démocratique du Congo
 */

require_once '../../../config/config.php';
require_once '../../../config/database.php';
require_once '../../../includes/functions.php';
require_once '../../../includes/permissions-pages.php';

// Fonction pour récupérer l'ID du type de frais à partir du nom
function getTypeFraisId($type_nom, $annee_scolaire_id) {
    global $database;
    
    $type_frais = $database->query(
        "SELECT id FROM type_frais WHERE nom = ? AND annee_scolaire_id = ? AND actif = 1",
        [$type_nom, $annee_scolaire_id]
    )->fetch();
    
    return $type_frais ? $type_frais['id'] : null;
}
require_once '../fees/types/priority-functions-simple.php';

// Vérifier l'authentification et les permissions
requireLogin();
requirePagePermissionFromDB('finance', 'payments', 'create', '../../dashboard.php');

$page_title = 'Enregistrer un paiement';

// Obtenir l'année scolaire actuelle
$current_year = getCurrentAcademicYear();

// Obtenir la devise par défaut
$devise_par_defaut = getDefaultCurrency();

if (!$current_year) {
    showMessage('error', 'Aucune année scolaire active.');
    redirectTo('../index.php');
}

// Récupérer les élèves inscrits
$eleves = $database->query(
    "SELECT e.id, e.nom, e.prenom, e.numero_matricule, c.nom as classe_nom, c.niveau
     FROM eleves e
     JOIN inscriptions i ON e.id = i.eleve_id
     JOIN classes c ON i.classe_id = c.id
     WHERE i.status = 'inscrit' AND i.annee_scolaire_id = ?
     ORDER BY e.nom, e.prenom",
    [$current_year['id']]
)->fetchAll();

// Récupérer les types de frais actifs pour l'année scolaire (triés par priorité)
$types_frais = $database->query(
    "SELECT id, nom, description, priorite FROM type_frais 
     WHERE annee_scolaire_id = ? AND actif = 1 
     ORDER BY priorite ASC, nom ASC",
    [$current_year['id']]
)->fetchAll();

$errors = [];
$success = false;

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validation des données
    $eleve_id = (int)($_POST['eleve_id'] ?? 0);
    $type_paiement = sanitizeInput($_POST['type_paiement'] ?? '');
    $montant = (float)($_POST['montant'] ?? 0);
    $devise_id = (int)($_POST['devise_id'] ?? 0);
    $mode_paiement = sanitizeInput($_POST['mode_paiement'] ?? '');
    $date_paiement = sanitizeInput($_POST['date_paiement'] ?? '');
    $description = sanitizeInput($_POST['description'] ?? '');
    $reference = sanitizeInput($_POST['reference'] ?? '');
    
    // Validation des champs obligatoires
    if (!$eleve_id) $errors[] = 'L\'élève est obligatoire.';
    if (empty($type_paiement)) $errors[] = 'Le type de paiement est obligatoire.';
    if ($montant <= 0) $errors[] = 'Le montant doit être supérieur à zéro.';
    if (empty($mode_paiement)) $errors[] = 'Le mode de paiement est obligatoire.';
    if (empty($date_paiement)) $errors[] = 'La date de paiement est obligatoire.';
    
    // Vérifier que le type de paiement existe et est actif
    if (!empty($type_paiement)) {
        $type_frais_existe = $database->query(
            "SELECT id, priorite FROM type_frais WHERE nom = ? AND annee_scolaire_id = ? AND actif = 1",
            [$type_paiement, $current_year['id']]
        )->fetch();
        
        if (!$type_frais_existe) {
            $errors[] = 'Le type de paiement sélectionné n\'est pas valide ou n\'est pas actif pour cette année scolaire.';
        } else {
            // Vérifier la règle de priorité
            require_once '../fees/types/priority-functions-simple.php';
            $can_pay = canPayFeeType($type_frais_existe['id'], $eleve_id, $current_year['id']);
            
            if (!$can_pay['allowed']) {
                $errors[] = $can_pay['message'];
            }
        }
    }
    
    // Vérifier que l'élève existe et a une classe assignée
    if ($eleve_id) {
        $eleve_check = $database->query(
            "SELECT e.id, e.classe_id, e.status 
             FROM eleves e 
             WHERE e.id = ?",
            [$eleve_id]
        )->fetch();
        
        if (!$eleve_check) {
            $errors[] = 'L\'élève sélectionné n\'existe pas.';
        } elseif (!$eleve_check['classe_id']) {
            $errors[] = 'L\'élève sélectionné n\'a pas de classe assignée. Impossible de procéder à l\'inscription.';
        } elseif ($type_paiement === 'inscription' && $eleve_check['status'] === 'inscrit') {
            // Vérifier si l'élève est déjà inscrit pour cette année
            $inscription_existante = $database->query(
                "SELECT id FROM inscriptions WHERE eleve_id = ? AND annee_scolaire_id = ?",
            [$eleve_id, $current_year['id']]
            )->fetch();
            
            if ($inscription_existante) {
                $errors[] = 'Cet élève est déjà inscrit pour cette année scolaire.';
            }
        }
    }
    
    // Validation de la date
    if (!empty($date_paiement) && !isValidDate($date_paiement)) {
        $errors[] = 'La date de paiement n\'est pas valide.';
    }
    
    // Validation du montant
    if ($montant > 10000000) { // 10 millions max
        $errors[] = 'Le montant ne peut pas dépasser 10 000 000 ' . ($devise_par_defaut['symbole'] ?? 'FC') . '.';
    }
    
    // Si pas d'erreurs, enregistrer le paiement
    if (empty($errors)) {
        try {
            $database->beginTransaction();
            
            // Générer le numéro de reçu
            $numero_recu = generateReceiptNumber();
            
            // Récupérer l'ID du type de frais
            $type_frais_id = getTypeFraisId($type_paiement, $current_year['id']);
            if (!$type_frais_id) {
                $errors[] = 'Type de frais non trouvé ou inactif.';
                $has_errors = true;
            }

            if (!$has_errors) {
                // Insérer le paiement
                $sql = "INSERT INTO paiements (
                            eleve_id, annee_scolaire_id, recu_numero, type_frais_id,
                            montant, devise_id, montant_devise_par_defaut, mode_paiement, date_paiement, observation,
                            user_id
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

                // Calculer le montant dans la devise par défaut
                $montant_devise_par_defaut = convertToDefaultCurrency($montant, $devise_id);
                
                $database->execute($sql, [
                    $eleve_id, $current_year['id'], $numero_recu, $type_frais_id,
                    $montant, $devise_id, $montant_devise_par_defaut, $mode_paiement, $date_paiement, $description,
                    $_SESSION['user_id']
                ]);
                
                $paiement_id = $database->lastInsertId();
                
                // Enregistrer automatiquement le paiement dans la caisse si une session est ouverte
                require_once '../expenses/caisse_functions.php';
                enregistrerPaiementDansCaisse($paiement_id, $eleve_id, $montant, $devise_id, $type_paiement, $date_paiement, $numero_recu);
                
                // Si c'est un paiement de frais d'inscription, inscrire automatiquement l'élève
                if (strtolower($type_paiement) === 'inscription') {
                try {
                    // Récupérer les informations de l'élève (classe_id)
                    $eleve_info = $database->query(
                        "SELECT e.classe_id, e.status as eleve_status 
                         FROM eleves e 
                         WHERE e.id = ?",
                        [$eleve_id]
                    )->fetch();
                    
                    if ($eleve_info && $eleve_info['classe_id']) {
                        // Vérifier si l'élève a déjà une inscription pour cette année
                        $inscription = $database->query(
                            "SELECT id, frais_inscription_paye FROM inscriptions 
                             WHERE eleve_id = ? AND annee_scolaire_id = ?",
                            [$eleve_id, $current_year['id']]
                        )->fetch();
                        
                        if ($inscription) {
                            // Mettre à jour l'inscription existante
                            $nouveau_total = $inscription['frais_inscription_paye'] + $montant_devise_par_defaut;
                            
                            // Vérifier si le montant total couvre les frais d'inscription complets
                            $frais_inscription_classe = $database->query(
                                "SELECT frais_inscription FROM classes WHERE id = ?",
                                [$eleve_info['classe_id']]
                            )->fetchColumn();
                            
                            // Déterminer le statut selon le montant payé
                            $nouveau_status = 'en_attente'; // Par défaut en attente
                            if ($nouveau_total >= $frais_inscription_classe) {
                                $nouveau_status = 'inscrit'; // Inscription complète
                            }
                            
                            $database->execute(
                                "UPDATE inscriptions 
                                 SET status = ?, 
                                     frais_inscription_paye = ?, 
                                     updated_at = NOW() 
                                 WHERE id = ?",
                                [$nouveau_status, $nouveau_total, $inscription['id']]
                            );
                            
                            $message_inscription = "Inscription mise à jour. Total des frais d'inscription payés : " . 
                                                  number_format($nouveau_total, 2) . " " . 
                                                  ($devise_par_defaut['symbole'] ?? 'FC') . 
                                                  " - Statut : " . ($nouveau_status === 'inscrit' ? 'Inscrit' : 'En attente');
                        } else {
                            // Créer une nouvelle inscription avec statut "en attente"
                            $database->execute(
                                "INSERT INTO inscriptions (
                                    eleve_id, annee_scolaire_id, classe_id, 
                                    date_inscription, frais_inscription_paye, 
                                    status, created_at, updated_at
                                ) VALUES (?, ?, ?, NOW(), ?, 'en_attente', NOW(), NOW())",
                                [$eleve_id, $current_year['id'], $eleve_info['classe_id'], $montant_devise_par_defaut]
                            );
                            
                            $message_inscription = "Nouvelle inscription créée avec le statut 'En attente'. Frais d'inscription payés : " . 
                                                  number_format($montant_devise_par_defaut, 2) . " " . 
                                                  ($devise_par_defaut['symbole'] ?? 'FC');
                        }
                        
                        // Mettre à jour le statut de l'élève selon l'inscription
                        if ($eleve_info['eleve_status'] !== 'inscrit') {
                            $database->execute(
                                "UPDATE eleves SET status = 'inscrit', updated_at = NOW() WHERE id = ?",
                                [$eleve_id]
                            );
                        }
                        
                        // Log de l'inscription automatique
                        logAction('inscription_automatique', [
                            'eleve_id' => $eleve_id,
                            'classe_id' => $eleve_info['classe_id'],
                            'annee_scolaire_id' => $current_year['id'],
                            'montant_paye' => $montant_devise_par_defaut,
                            'paiement_id' => $paiement_id,
                            'status_inscription' => $nouveau_status ?? 'en_attente'
                        ]);
                        
                    } else {
                        throw new Exception("Impossible de récupérer les informations de l'élève ou classe non assignée");
                    }
                    
                } catch (Exception $e) {
                    // En cas d'erreur lors de l'inscription, on continue
                    // car le paiement a déjà été enregistré avec succès
                    error_log("Erreur lors de l'inscription automatique de l'élève: " . $e->getMessage());
                    $message_inscription = "Paiement enregistré mais erreur lors de l'inscription automatique. Contactez l'administrateur.";
                }
                }
                
                $database->commit();
                
                // Message de succès avec informations d'inscription si applicable
                $message_succes = 'Paiement enregistré avec succès !';
                if (strtolower($type_paiement) === 'inscription' && isset($message_inscription)) {
                    $message_succes .= ' ' . $message_inscription;
                }
                
                showMessage('success', $message_succes);
                redirectTo('receipt.php?id=' . $paiement_id);
            }
            
        } catch (Exception $e) {
            $database->rollback();
            $errors[] = 'Erreur lors de l\'enregistrement : ' . $e->getMessage();
        }
    }
}



include '../../../includes/header.php';
?>

<!-- DataTables CSS -->
<link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css">
<link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/buttons/2.4.2/css/buttons.bootstrap5.min.css">
<link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/responsive/2.5.0/css/responsive.bootstrap5.min.css">

<style>
.eleve-selected {
    background-color: #d4edda !important;
    border-color: #c3e6cb !important;
}

.eleve-selected:hover {
    background-color: #c3e6cb !important;
}

#eleveTable tbody tr {
    cursor: pointer;
}

#eleveTable tbody tr:hover {
    background-color: #f8f9fa;
}

.modal-xl {
    max-width: 95%;
}

.student-info-card {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border: none;
}

.student-info-card .card-body {
    padding: 1.5rem;
}

.student-info-card .badge {
    background-color: rgba(255, 255, 255, 0.2);
    color: white;
    font-size: 0.9rem;
}

.avatar-placeholder {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border: 3px solid rgba(255, 255, 255, 0.3);
}

#eleve_display {
    background-color: #f8f9fa;
    cursor: pointer;
}

#eleve_display:hover {
    background-color: #e9ecef;
}
</style>


<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">
        <i class="fas fa-plus me-2"></i>
        Enregistrer un paiement
    </h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <a href="index.php" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-left me-1"></i>
            Retour à la liste
        </a>
        <?php if ($devise_par_defaut): ?>
            <div class="btn-group me-2">
                <button type="button" class="btn btn-outline-info">
                    <i class="fas fa-exchange-alt me-1"></i>
                    Devise par défaut : <?php echo htmlspecialchars($devise_par_defaut['code']); ?> 
                    (<?php echo htmlspecialchars($devise_par_defaut['symbole']); ?>)
                </button>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php if (!empty($errors)): ?>
    <div class="alert alert-danger">
        <h6><i class="fas fa-exclamation-triangle me-2"></i>Erreurs détectées :</h6>
        <ul class="mb-0">
            <?php foreach ($errors as $error): ?>
                <li><?php echo htmlspecialchars($error); ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<form method="POST" class="needs-validation" novalidate>
    <div class="row">
        <!-- Informations du paiement -->
        <div class="col-lg-8">
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-money-bill me-2"></i>
                        Informations du paiement
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="eleve_id" class="form-label">Sélectionner un élève <span class="text-danger">*</span></label>
                            <div class="d-flex gap-2">
                                <input type="hidden" id="eleve_id" name="eleve_id" value="<?php echo htmlspecialchars($_POST['eleve_id'] ?? ''); ?>">
                                <input type="hidden" id="eleve_classe_id" name="eleve_classe_id" value="">
                                <input type="text" 
                                       class="form-control" 
                                       id="eleve_display" 
                                       placeholder="Cliquez pour sélectionner un élève..."
                                       readonly
                                       required>
                                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#eleveModal">
                                    <i class="fas fa-search me-1"></i>
                                    Rechercher
                                </button>
                            </div>
                            <div class="form-text">Cliquez sur "Rechercher" pour ouvrir la liste des élèves</div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="type_paiement" class="form-label">Type de paiement <span class="text-danger">*</span></label>
                            <select class="form-select" id="type_paiement" name="type_paiement" required onchange="updateFraisOptions()">
                                <option value="">Sélectionner un élève d'abord...</option>
                                <?php if (!empty($types_frais)): ?>
                                    <?php foreach ($types_frais as $type): ?>
                                        <option value="<?php echo htmlspecialchars($type['nom']); ?>" 
                                                <?php echo ($_POST['type_paiement'] ?? '') === $type['nom'] ? 'selected' : ''; ?>
                                                title="<?php echo htmlspecialchars($type['description']); ?>"
                                                data-priorite="<?php echo $type['priorite']; ?>"
                                                data-type-id="<?php echo $type['id']; ?>">
                                            <?php echo htmlspecialchars($type['nom']); ?> 
                                            (Priorité <?php echo $type['priorite']; ?>)
                                        </option>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <option value="" disabled>Aucun type de frais configuré pour cette année</option>
                                <?php endif; ?>
                            </select>
                            <div class="form-text">
                                <i class="fas fa-info-circle me-1"></i>
                                Le type de frais sera sélectionné automatiquement selon la priorité une fois l'élève choisi.
                            </div>
                            <?php if (empty($types_frais)): ?>
                                <div class="form-text text-warning">
                                    <i class="fas fa-exclamation-triangle me-1"></i>
                                    Aucun type de frais n'est configuré pour cette année scolaire. 
                                    <a href="../fees/types/add.php" class="alert-link">Cliquez ici pour en créer</a>.
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Sélection des frais spécifiques à la classe -->
                    <div class="row" id="frais-selection" style="display: none;">
                        <div class="col-12 mb-3">
                            <label for="frais_id" class="form-label">Frais spécifique à la classe</label>
                            <select class="form-select" id="frais_id" name="frais_id" onchange="updateMontantFromFrais()">
                                <option value="">Sélectionner un frais...</option>
                            </select>
                            <div class="form-text">Sélectionnez un frais spécifique pour pré-remplir le montant et la description</div>
                        </div>
                    </div>
                    
                    <!-- Montant et devise -->
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="montant" class="form-label">Montant <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <input type="number" 
                                       class="form-control" 
                                       id="montant" 
                                       name="montant" 
                                       min="1" 
                                       max="10000000"
                                       step="1"
                                       placeholder="Ex: 50000"
                                       value="<?php echo htmlspecialchars($_POST['montant'] ?? ''); ?>"
                                       required>
                                <span class="input-group-text" id="montant-symbole"><?php echo htmlspecialchars($devise_par_defaut['symbole'] ?? 'FC'); ?></span>
                            </div>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="devise_id" class="form-label">Devise <span class="text-danger">*</span></label>
                            <select class="form-select" id="devise_id" name="devise_id" required onchange="updateMontantSymbole()">
                                <option value="">Sélectionner...</option>
                                <?php 
                                $devises = getActiveCurrencies();
                                foreach ($devises as $devise): 
                                ?>
                                    <option value="<?= $devise['id'] ?>" 
                                            <?= ($_POST['devise_id'] ?? ($devise['devise_par_defaut'] ? $devise['id'] : '')) == $devise['id'] ? 'selected' : '' ?>
                                            data-symbole="<?= htmlspecialchars($devise['symbole']) ?>"
                                            data-taux="<?= $devise['taux_conversion'] ?>"
                                            <?= $devise['devise_par_defaut'] ? 'data-default="true"' : '' ?>>
                                        <?= htmlspecialchars($devise['code']) ?> - <?= htmlspecialchars($devise['nom']) ?>
                                        <?= $devise['devise_par_defaut'] ? ' (Par défaut)' : '' ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <!-- Affichage de la conversion -->
                    <div class="row" id="conversion-display" style="display: none;">
                        <div class="col-12 mb-3">
                            <div class="alert alert-info">
                                <h6><i class="fas fa-exchange-alt me-2"></i>Conversion automatique</h6>
                                <div id="conversion-details">
                                    <!-- Rempli par JavaScript -->
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Mode de paiement et date -->
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="mode_paiement" class="form-label">Mode de paiement <span class="text-danger">*</span></label>
                            <select class="form-select" id="mode_paiement" name="mode_paiement" required>
                                <option value="">Sélectionner...</option>
                                <option value="especes" <?php echo ($_POST['mode_paiement'] ?? '') === 'especes' ? 'selected' : ''; ?>>Espèces</option>
                                <option value="cheque" <?php echo ($_POST['mode_paiement'] ?? '') === 'cheque' ? 'selected' : ''; ?>>Chèque</option>
                                <option value="virement" <?php echo ($_POST['mode_paiement'] ?? '') === 'virement' ? 'selected' : ''; ?>>Virement bancaire</option>
                                <option value="mobile_money" <?php echo ($_POST['mode_paiement'] ?? '') === 'mobile_money' ? 'selected' : ''; ?>>Mobile Money</option>
                            </select>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="date_paiement" class="form-label">Date de paiement <span class="text-danger">*</span></label>
                            <input type="date" 
                                   class="form-control" 
                                   id="date_paiement" 
                                   name="date_paiement" 
                                   value="<?php echo htmlspecialchars($_POST['date_paiement'] ?? date('Y-m-d')); ?>"
                                   max="<?php echo date('Y-m-d'); ?>"
                                   required>
                        </div>
                    </div>
                    
                    <!-- Référence et description -->
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="reference" class="form-label">Référence</label>
                            <input type="text" 
                                   class="form-control" 
                                   id="reference" 
                                   name="reference" 
                                   placeholder="N° chèque, référence virement..."
                                   value="<?php echo htmlspecialchars($_POST['reference'] ?? ''); ?>">
                            <div class="form-text">Optionnel - pour chèques, virements, etc.</div>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="description" class="form-label">Description</label>
                            <input type="text" 
                                   class="form-control" 
                                   id="description" 
                                   name="description" 
                                   placeholder="Description optionnelle..."
                                   value="<?php echo htmlspecialchars($_POST['description'] ?? ''); ?>">
                        </div>
                    </div>
                    

                    

                </div>
            </div>
        </div>
        
        <!-- Informations complémentaires -->
        <div class="col-lg-4">
            <!-- Informations de l'élève sélectionné -->
            <div class="card mb-4" id="eleve-info" style="display: none;">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">
                        <i class="fas fa-user me-2"></i>
                        Informations élève
                    </h5>
                </div>
                <div class="card-body">
                    <div id="eleve-details">
                        <!-- Rempli par JavaScript -->
                    </div>
                </div>
            </div>
            
            <!-- Résumé du paiement -->
            <div class="card mb-4" id="paiement-resume" style="display: none;">
                <div class="card-header bg-info text-white">
                    <h5 class="mb-0">
                        <i class="fas fa-calculator me-2"></i>
                        Résumé du paiement
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row text-center">
                        <div class="col-6">
                            <div class="border-end">
                                <h6 class="text-muted">Montant</h6>
                                <h4 class="text-success" id="resume-montant">0 <?php echo htmlspecialchars($devise_par_defaut['symbole'] ?? 'FC'); ?></h4>
                    </div>
                        </div>
                        <div class="col-6">
                            <h6 class="text-muted">Devise</h6>
                            <h4 class="text-info" id="resume-devise">-</h4>
                        </div>
                    </div>
                    <hr>
                    <div class="text-center">
                        <small class="text-muted" id="resume-conversion"></small>
                    </div>
                </div>
            </div>
            
            <!-- Aide -->
            <div class="card">
                <div class="card-header bg-secondary text-white">
                    <h5 class="mb-0">
                        <i class="fas fa-info-circle me-2"></i>
                        Aide
                    </h5>
                </div>
                <div class="card-body">
                    <div class="accordion" id="accordionAide">
                        <!-- Types de paiement -->
                        <div class="accordion-item">
                            <h2 class="accordion-header" id="headingTypes">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseTypes">
                                    Types de paiement
                                </button>
                            </h2>
                            <div id="collapseTypes" class="accordion-collapse collapse" data-bs-parent="#accordionAide">
                                <div class="accordion-body">
                                    <ul class="list-unstyled small mb-0">
                        <li><strong>Inscription :</strong> Frais d'inscription annuelle</li>
                        <li><strong>Mensualité :</strong> Frais mensuels de scolarité</li>
                        <li><strong>Examen :</strong> Frais d'examens et compositions</li>
                        <li><strong>Autre :</strong> Autres frais (uniforme, transport, etc.)</li>
                    </ul>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Modes de paiement -->
                        <div class="accordion-item">
                            <h2 class="accordion-header" id="headingModes">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseModes">
                                    Modes de paiement
                                </button>
                            </h2>
                            <div id="collapseModes" class="accordion-collapse collapse" data-bs-parent="#accordionAide">
                                <div class="accordion-body">
                                    <ul class="list-unstyled small mb-0">
                        <li><strong>Espèces :</strong> Paiement en liquide</li>
                        <li><strong>Chèque :</strong> Paiement par chèque bancaire</li>
                        <li><strong>Virement :</strong> Virement bancaire</li>
                        <li><strong>Mobile Money :</strong> Airtel Money, Orange Money, etc.</li>
                    </ul>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Sélection d'élève -->
                        <div class="accordion-item">
                            <h2 class="accordion-header" id="headingSelection">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseSelection">
                                    Sélection d'élève
                                </button>
                            </h2>
                            <div id="collapseSelection" class="accordion-collapse collapse" data-bs-parent="#accordionAide">
                                <div class="accordion-body">
                                    <ul class="list-unstyled small mb-0">
                                        <li><strong>Recherche :</strong> Cliquez sur "Rechercher" pour ouvrir la liste complète</li>
                                        <li><strong>Filtres :</strong> Utilisez la barre de recherche pour filtrer par nom, matricule, etc.</li>
                                        <li><strong>Statuts :</strong> Affiche tous les élèves (actif, transféré, abandonné, diplômé, etc.)</li>
                                        <li><strong>Sélection :</strong> Cliquez sur "Sélectionner" puis "Confirmer la sélection"</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Fonctionnalités automatiques -->
                        <div class="accordion-item">
                            <h2 class="accordion-header" id="headingAutomatique">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseAutomatique">
                                    Fonctionnalités automatiques
                                </button>
                            </h2>
                            <div id="collapseAutomatique" class="accordion-collapse collapse" data-bs-parent="#accordionAide">
                                <div class="accordion-body">
                                    <ul class="list-unstyled small mb-0">
                                        <li><strong>Sélection automatique :</strong> Le type de frais prioritaire est sélectionné automatiquement</li>
                                        <li><strong>Règle de priorité :</strong> Impossible de payer des frais de priorité basse si les frais de priorité haute ne sont pas soldés</li>
                                        <li><strong>Frais d'inscription :</strong> Inscription automatique dans la table inscriptions</li>
                                        <li><strong>Statut élève :</strong> Mise à jour automatique à "inscrit"</li>
                                        <li><strong>Suivi des paiements :</strong> Cumul des frais d'inscription payés</li>
                                        <li><strong>Validation :</strong> Vérification que l'élève a une classe assignée</li>
                                        <li><strong>Frais spécifiques :</strong> Après sélection d'un élève et d'un type, choisissez le frais exact</li>
                                        <li><strong>Pré-remplissage :</strong> Le montant et la description sont automatiquement remplis</li>
                                        <li><strong>Par classe :</strong> Seuls les frais de la classe de l'élève sont affichés</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Système de priorité -->
                        <div class="accordion-item">
                            <h2 class="accordion-header" id="headingPriorite">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapsePriorite">
                                    Système de priorité
                                </button>
                            </h2>
                            <div id="collapsePriorite" class="accordion-collapse collapse" data-bs-parent="#accordionAide">
                                <div class="accordion-body">
                                    <ul class="list-unstyled small mb-0">
                                        <li><strong>Priorité 1 :</strong> Frais d'inscription (doivent être soldés en premier)</li>
                                        <li><strong>Priorité 2 :</strong> Frais mensuels (après inscription complète)</li>
                                        <li><strong>Priorité 3 :</strong> Autres frais (uniforme, transport, etc.)</li>
                                        <li><strong>Sélection automatique :</strong> Le système sélectionne automatiquement le type de frais prioritaire restant à payer</li>
                                        <li><strong>Blocage :</strong> Impossible de sélectionner manuellement un type de frais de priorité plus basse</li>
                                        <li><strong>Notification :</strong> Affichage du statut de tous les types de frais dans la notification</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Boutons d'action -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <a href="index.php" class="btn btn-secondary">
                            <i class="fas fa-times me-1"></i>
                            Annuler
                        </a>
                        <div>
                            <button type="reset" class="btn btn-outline-secondary me-2">
                                <i class="fas fa-undo me-1"></i>
                                Réinitialiser
                            </button>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-1"></i>
                                Enregistrer le paiement
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</form>

<!-- Modal de sélection d'élève -->
<div class="modal fade" id="eleveModal" tabindex="-1" aria-labelledby="eleveModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="eleveModalLabel">
                    <i class="fas fa-users me-2"></i>
                    Sélectionner un élève
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <!-- Informations de l'élève sélectionné -->
                <div id="eleve-selection-info" class="mb-4" style="display: none;">
                    <div class="card student-info-card">
                        <div class="card-body">
                            <div class="row align-items-center">
                                <div class="col-md-8">
                                    <h5 class="mb-2" id="selected-student-name">Nom de l'élève</h5>
                                    <div class="d-flex flex-wrap gap-2">
                                        <span class="badge" id="selected-student-matricule">Matricule</span>
                                        <span class="badge" id="selected-student-class">Classe</span>
                                        <span class="badge" id="selected-student-status">Statut</span>
                                    </div>
                                </div>
                                <div class="col-md-4 text-end">
                                    <button type="button" class="btn btn-success" onclick="confirmStudentSelection()">
                                        <i class="fas fa-check me-1"></i>
                                        Confirmer la sélection
                                    </button>
                                </div>
                            </div>
                            
                            <!-- Informations financières -->
                            <div class="row mt-3" id="student-financial-info" style="display: none;">
                                <div class="col-12">
                                    <h6 class="text-muted mb-3">
                                        <i class="fas fa-calculator me-2"></i>
                                        Situation Financière
                                    </h6>
                                    <div class="row">
                                        <div class="col-md-4">
                                            <div class="card border-primary">
                                                <div class="card-body text-center">
                                                    <h6 class="card-title text-primary">Total à Payer</h6>
                                                    <h4 class="text-primary" id="selected-student-total-frais">0 <?php echo htmlspecialchars($devise_par_defaut['symbole'] ?? 'FC'); ?></h4>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="card border-success">
                                                <div class="card-body text-center">
                                                    <h6 class="card-title text-success">Déjà Payé</h6>
                                                    <h4 class="text-success" id="selected-student-total-paye">0 <?php echo htmlspecialchars($devise_par_defaut['symbole'] ?? 'FC'); ?></h4>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="card border-danger">
                                                <div class="card-body text-center">
                                                    <h6 class="card-title text-danger">Solde Restant</h6>
                                                    <h4 class="text-danger" id="selected-student-solde-restant">0 <?php echo htmlspecialchars($devise_par_defaut['symbole'] ?? 'FC'); ?></h4>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tableau des élèves -->
                <div class="table-responsive">
                    <table id="eleveTable" class="table table-striped table-hover" style="width:100%">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Matricule</th>
                                <th>Nom</th>
                                <th>Prénom</th>
                                <th>Classe</th>
                                <th>Niveau</th>
                                <th>Statut</th>
                                <th>Total à Payer</th>
                                <th>Déjà Payé</th>
                                <th>Solde Restant</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- Rempli par DataTables -->
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fermer</button>
            </div>
        </div>
    </div>
</div>

<style>
.eleve-selected {
    background-color: #e3f2fd !important;
}

.student-info-card {
    border-left: 4px solid #007bff;
}

#student-financial-info .card {
    transition: transform 0.2s;
}

#student-financial-info .card:hover {
    transform: translateY(-2px);
}

.table th {
    background-color: #f8f9fa;
    font-weight: 600;
}

.text-danger.fw-bold {
    font-weight: 700 !important;
}
</style>

<script>
// Variables globales pour la devise par défaut
const devise_par_defaut_symbole = '<?php echo $devise_par_defaut ? htmlspecialchars($devise_par_defaut['symbole']) : 'FC'; ?>';
const devise_par_defaut_code = '<?php echo $devise_par_defaut ? htmlspecialchars($devise_par_defaut['code']) : 'CDF'; ?>';

// Variables pour la sélection d'élève
let selectedStudent = null;
let eleveTable = null;

// Initialisation du DataTable des élèves
function initializeEleveTable() {
    eleveTable = $('#eleveTable').DataTable({
        ajax: {
            url: 'get_eleves_for_payment.php',
            type: 'POST',
            data: function(d) {
                d.statuses = ['actif', 'transfere', 'abandonne', 'diplome', 'non-evalue', 'admis', 'evalue'];
            },
            error: function(xhr, error, thrown) {
                console.error('Erreur AJAX DataTables:', error);
                console.error('Détails:', xhr.responseText);
                
                // Afficher un message d'erreur à l'utilisateur
                const errorDiv = document.createElement('div');
                errorDiv.className = 'alert alert-danger';
                errorDiv.innerHTML = `
                    <h6><i class="fas fa-exclamation-triangle me-2"></i>Erreur de chargement des données</h6>
                    <p>Impossible de charger la liste des élèves. Veuillez réessayer.</p>
                    <button type="button" class="btn btn-sm btn-outline-danger" onclick="retryLoadTable()">
                        <i class="fas fa-redo me-1"></i>Réessayer
                    </button>
                `;
                
                // Remplacer le contenu du modal
                const modalBody = document.querySelector('#eleveModal .modal-body');
                modalBody.innerHTML = '';
                modalBody.appendChild(errorDiv);
            }
        },
        columns: [
            { data: 'id', visible: false },
            { data: 'numero_matricule' },
            { data: 'nom' },
            { data: 'prenom' },
            { data: 'classe_nom' },
            { data: 'niveau' },
            { 
                data: 'status',
                render: function(data, type, row) {
                    const statusColors = {
                        'actif': 'success',
                        'transfere': 'info',
                        'abandonne': 'danger',
                        'diplome': 'primary',
                        'non-evalue': 'warning',
                        'admis': 'secondary',
                        'evalue': 'success',
                        'inscrit': 'success'
                    };
                    const statusLabels = {
                        'actif': 'Actif',
                        'transfere': 'Transféré',
                        'abandonne': 'Abandonné',
                        'diplome': 'Diplômé',
                        'non-evalue': 'Non évalué',
                        'admis': 'Admis',
                        'evalue': 'Évalué',
                        'inscrit': 'Inscrit'
                    };
                    return `<span class="badge bg-${statusColors[data] || 'secondary'}">${statusLabels[data] || data}</span>`;
                }
            },
            {
                data: 'total_frais_formatted',
                render: function(data, type, row) {
                    const totalFrais = parseFloat(row.total_frais) || 0;
                    if (totalFrais === 0) {
                        return '<span class="text-muted">Aucun frais</span>';
                    }
                    return `<strong class="text-primary">${data}</strong>`;
                }
            },
            {
                data: 'total_paye_formatted',
                render: function(data, type, row) {
                    const totalPaye = parseFloat(row.total_paye) || 0;
                    if (totalPaye === 0) {
                        return '<span class="text-muted">0 <?php echo htmlspecialchars($devise_par_defaut['symbole'] ?? 'FC'); ?></span>';
                    }
                    return `<span class="text-success">${data}</span>`;
                }
            },
            {
                data: 'solde_restant_formatted',
                render: function(data, type, row) {
                    const soldeRestant = parseFloat(row.solde_restant) || 0;
                    if (soldeRestant === 0) {
                        return '<span class="badge bg-success">Solde acquitté</span>';
                    } else if (soldeRestant > 0) {
                        return `<span class="text-danger fw-bold">${data}</span>`;
                    } else {
                        return `<span class="text-warning">${data}</span>`;
                    }
                }
            },
            {
                data: null,
                orderable: false,
                render: function(data, type, row) {
                    return `<button type="button" class="btn btn-sm btn-primary" onclick="selectStudent(${row.id}, '${row.nom}', '${row.prenom}', '${row.numero_matricule}', '${row.classe_nom}', '${row.status}', ${row.classe_id}, ${row.total_frais}, ${row.total_paye}, ${row.solde_restant})">
                        <i class="fas fa-check me-1"></i>Sélectionner
                    </button>`;
                }
            }
        ],
        language: {
            url: '//cdn.datatables.net/plug-ins/1.13.7/i18n/fr-FR.json'
        },
        responsive: true,
        pageLength: 25,
        order: [[2, 'asc'], [3, 'asc']], // Trier par nom, puis prénom
        dom: '<"row"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6"f>>' +
             '<"row"<"col-sm-12"tr>>' +
             '<"row"<"col-sm-12 col-md-5"i><"col-sm-12 col-md-7"p>>',
        lengthMenu: [[10, 25, 50, 100], [10, 25, 50, 100]],
        processing: true,
        serverSide: false
    });
}

// Fonction pour réessayer le chargement du tableau
function retryLoadTable() {
    // Restaurer le contenu original du modal
    const modalBody = document.querySelector('#eleveModal .modal-body');
    modalBody.innerHTML = `
        <!-- Informations de l'élève sélectionné -->
        <div id="eleve-selection-info" class="mb-4" style="display: none;">
            <div class="card student-info-card">
                <div class="card-body">
                    <div class="row align-items-center">
                        <div class="col-md-8">
                            <h5 class="mb-2" id="selected-student-name">Nom de l'élève</h5>
                            <div class="d-flex flex-wrap gap-2">
                                <span class="badge" id="selected-student-matricule">Matricule</span>
                                <span class="badge" id="selected-student-class">Classe</span>
                                <span class="badge" id="selected-student-status">Statut</span>
                            </div>
                        </div>
                        <div class="col-md-4 text-end">
                            <button type="button" class="btn btn-success" onclick="confirmStudentSelection()">
                                <i class="fas fa-check me-1"></i>
                                Confirmer la sélection
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tableau des élèves -->
        <div class="table-responsive">
            <table id="eleveTable" class="table table-striped table-hover" style="width:100%">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Matricule</th>
                        <th>Nom</th>
                        <th>Prénom</th>
                        <th>Classe</th>
                        <th>Niveau</th>
                        <th>Statut</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <!-- Rempli par DataTables -->
                </tbody>
            </table>
            </div>
        `;
        
    // Réinitialiser le DataTable
    if (eleveTable) {
        eleveTable.destroy();
        eleveTable = null;
    }
    
    // Réinitialiser le tableau
    setTimeout(() => {
        initializeEleveTable();
    }, 100);
}

// Sélectionner un élève
function selectStudent(id, nom, prenom, matricule, classe, status, classe_id, totalFrais = 0, totalPaye = 0, soldeRestant = 0) {
    selectedStudent = { id, nom, prenom, matricule, classe, status, classe_id, totalFrais, totalPaye, soldeRestant };
    
    // Mettre à jour l'affichage des informations
    document.getElementById('selected-student-name').textContent = `${nom} ${prenom}`;
    document.getElementById('selected-student-matricule').textContent = `Matricule: ${matricule}`;
    document.getElementById('selected-student-class').textContent = `Classe: ${classe}`;
    document.getElementById('selected-student-status').textContent = `Statut: ${status}`;
    
    // Mettre à jour les informations financières
    document.getElementById('selected-student-total-frais').textContent = `${parseFloat(totalFrais).toLocaleString()} <?php echo htmlspecialchars($devise_par_defaut['symbole'] ?? 'FC'); ?>`;
    document.getElementById('selected-student-total-paye').textContent = `${parseFloat(totalPaye).toLocaleString()} <?php echo htmlspecialchars($devise_par_defaut['symbole'] ?? 'FC'); ?>`;
    document.getElementById('selected-student-solde-restant').textContent = `${parseFloat(soldeRestant).toLocaleString()} <?php echo htmlspecialchars($devise_par_defaut['symbole'] ?? 'FC'); ?>`;
    
    // Afficher la section de confirmation et les informations financières
    document.getElementById('eleve-selection-info').style.display = 'block';
    document.getElementById('student-financial-info').style.display = 'block';
    
    // Mettre en surbrillance la ligne sélectionnée
    $('#eleveTable tbody tr').removeClass('eleve-selected');
    $(`#eleveTable tbody tr:contains("${nom}")`).addClass('eleve-selected');
}

// Confirmer la sélection d'un élève
function confirmStudentSelection() {
    if (selectedStudent) {
        // Mettre à jour les champs du formulaire
        document.getElementById('eleve_id').value = selectedStudent.id;
        document.getElementById('eleve_classe_id').value = selectedStudent.classe_id;
        document.getElementById('eleve_display').value = `${selectedStudent.nom} ${selectedStudent.prenom} (${selectedStudent.matricule}) - ${selectedStudent.classe}`;
    
        // Afficher les informations de l'élève
        showStudentInfo(selectedStudent);
        
        // Fermer le modal
        const modal = bootstrap.Modal.getInstance(document.getElementById('eleveModal'));
        modal.hide();
        
        // Réinitialiser la sélection
        selectedStudent = null;
        document.getElementById('eleve-selection-info').style.display = 'none';
        
        // Retirer la surbrillance
        $('#eleveTable tbody tr').removeClass('eleve-selected');
        
        // Récupérer et afficher le type de frais prioritaire
        const eleveId = document.getElementById('eleve_id').value;
        loadPriorityFeeType(eleveId);
    }
}

// Fonction pour charger le type de frais prioritaire
function loadPriorityFeeType(eleveId) {
    const anneeScolaireId = <?php echo $current_year['id']; ?>;
    
    // Afficher un indicateur de chargement
    const typeSelect = document.getElementById('type_paiement');
    const originalValue = typeSelect.value;
    const originalOptions = typeSelect.innerHTML; // Sauvegarder les options originales
    typeSelect.innerHTML = '<option value="">Chargement du type prioritaire...</option>';
    typeSelect.disabled = true;
    
    // Faire la requête AJAX
    fetch(`get-priority-fee-type.php?eleve_id=${eleveId}&annee_scolaire_id=${anneeScolaireId}`, {
        method: 'GET',
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Restaurer les options originales
            typeSelect.innerHTML = originalOptions;
            
            if (data.priority_fee) {
                // Attendre un court délai pour s'assurer que les options sont restaurées
                setTimeout(() => {
                    console.log('=== DEBUG SÉLECTION AUTOMATIQUE ===');
                    console.log('Type prioritaire à sélectionner:', data.priority_fee.type_nom);
                    console.log('Options disponibles dans le select:');
                    Array.from(typeSelect.options).forEach((opt, index) => {
                        console.log(`  [${index}] Valeur: "${opt.value}", Texte: "${opt.textContent}"`);
                    });
                    
                    // Vérifier si l'option existe
                    const targetOption = Array.from(typeSelect.options).find(opt => opt.value === data.priority_fee.type_nom);
                    if (targetOption) {
                        console.log('✓ Option trouvée, sélection en cours...');
                        typeSelect.value = data.priority_fee.type_nom;
                        console.log('Valeur sélectionnée:', typeSelect.value);
                        console.log('Option sélectionnée:', typeSelect.selectedOptions[0]?.textContent);
                    } else {
                        console.error('✗ Option non trouvée pour:', data.priority_fee.type_nom);
                        console.log('Tentative de sélection forcée...');
                        typeSelect.value = data.priority_fee.type_nom;
                    }
                    
                    // Désactiver les options de priorité plus basse
                    updateTypeSelectOptions(data.payment_status, data.priority_fee.type_priorite);
                    
                    // Afficher une notification
                    showPriorityNotification(data.priority_fee, data.payment_status);
                    
                    // Mettre à jour les options de frais
                    updateFraisOptions();
                    console.log('=== FIN DEBUG ===');
                }, 100);
            } else {
                // Tous les frais sont soldés - permettre tous les types
                enableAllTypeOptions();
                showAllFeesPaidNotification(data.payment_status);
            }
        } else {
            console.error('Erreur:', data.error);
            typeSelect.innerHTML = originalOptions;
        }
    })
    .catch(error => {
        console.error('Erreur lors du chargement du type prioritaire:', error);
        typeSelect.innerHTML = originalOptions;
    })
    .finally(() => {
        typeSelect.disabled = false;
    });
}

// Fonction pour restaurer le sélecteur de type
function restoreTypeSelect() {
    const typeSelect = document.getElementById('type_paiement');
    typeSelect.innerHTML = `
        <option value="">Sélectionner un élève d'abord...</option>
        <?php if (!empty($types_frais)): ?>
            <?php foreach ($types_frais as $type): ?>
                <option value="<?php echo htmlspecialchars($type['nom']); ?>" 
                        data-priorite="<?php echo $type['priorite']; ?>"
                        data-type-id="<?php echo $type['id']; ?>">
                    <?php echo htmlspecialchars($type['nom']); ?> 
                    (Priorité <?php echo $type['priorite']; ?>)
                </option>
            <?php endforeach; ?>
        <?php endif; ?>
    `;
}

// Fonction pour mettre à jour les options du sélecteur selon la priorité
function updateTypeSelectOptions(paymentStatus, currentPriority) {
    const typeSelect = document.getElementById('type_paiement');
    const options = typeSelect.querySelectorAll('option[data-priorite]');
    
    options.forEach(option => {
        const priorite = parseInt(option.dataset.priorite);
        const typeNom = option.value;
        
        // Trouver le statut de ce type dans paymentStatus
        const typeStatus = paymentStatus.find(status => status.nom === typeNom);
        
        if (typeStatus) {
            if (priorite < currentPriority) {
                // Désactiver les types de priorité plus haute non soldés
                if (!typeStatus.soldé) {
                    option.disabled = true;
                    option.textContent = `${typeNom} (Priorité ${priorite}) - Non soldé`;
                    option.style.color = '#dc3545';
                } else {
                    option.disabled = false;
                    option.textContent = `${typeNom} (Priorité ${priorite}) - Soldé`;
                    option.style.color = '#198754';
                }
            } else if (priorite === currentPriority) {
                // Type prioritaire actuel - sélectionné et activé
                option.disabled = false;
                option.selected = true;
                option.textContent = `${typeNom} (Priorité ${priorite}) - À payer`;
                option.style.color = '#0d6efd';
                option.style.fontWeight = 'bold';
            } else {
                // Types de priorité plus basse - désactivés
                option.disabled = true;
                option.textContent = `${typeNom} (Priorité ${priorite}) - Priorité plus basse`;
                option.style.color = '#6c757d';
            }
        }
    });
}

// Fonction pour activer toutes les options (quand tous les frais sont soldés)
function enableAllTypeOptions() {
    const typeSelect = document.getElementById('type_paiement');
    const options = typeSelect.querySelectorAll('option[data-priorite]');
    
    options.forEach(option => {
        option.disabled = false;
        option.style.color = '';
        option.style.fontWeight = '';
        const priorite = option.dataset.priorite;
        const typeNom = option.value;
        option.textContent = `${typeNom} (Priorité ${priorite})`;
    });
}

// Fonction pour afficher la notification de priorité
function showPriorityNotification(priorityFee, paymentStatus) {
    // Créer ou mettre à jour la notification
    let notification = document.getElementById('priority-notification');
    if (!notification) {
        notification = document.createElement('div');
        notification.id = 'priority-notification';
        notification.className = 'alert alert-info alert-dismissible fade show';
        notification.style.marginTop = '10px';
        
        // Insérer après le sélecteur de type
        const typeSelect = document.getElementById('type_paiement');
        typeSelect.parentNode.insertBefore(notification, typeSelect.nextSibling);
    }
    
    const montantRestant = parseFloat(priorityFee.montant_restant).toLocaleString();
    const montantPaye = parseFloat(priorityFee.montant_paye).toLocaleString();
    
    // Créer un résumé des autres types de frais
    let otherFeesSummary = '';
    const otherFees = paymentStatus.filter(status => status.nom !== priorityFee.type_nom);
    if (otherFees.length > 0) {
        otherFeesSummary = '<hr class="my-2"><small class="text-muted"><strong>Autres frais :</strong><br>';
        otherFees.forEach(fee => {
            const status = fee.soldé ? '✅ Soldé' : '❌ Non soldé';
            const color = fee.soldé ? 'text-success' : 'text-danger';
            otherFeesSummary += `<span class="${color}">${fee.nom} (P${fee.priorite}) - ${status}</span><br>`;
        });
        otherFeesSummary += '</small>';
    }
    
    notification.innerHTML = `
        <div class="d-flex align-items-start">
            <i class="fas fa-exclamation-circle me-2 mt-1"></i>
            <div class="flex-grow-1">
                <strong>Type de frais prioritaire sélectionné :</strong> ${priorityFee.type_nom} (Priorité ${priorityFee.type_priorite})
                <br>
                <small class="text-muted">
                    <strong>Montant restant :</strong> ${montantRestant} <?php echo htmlspecialchars($devise_par_defaut['symbole'] ?? 'FC'); ?> 
                    | <strong>Déjà payé :</strong> ${montantPaye} <?php echo htmlspecialchars($devise_par_defaut['symbole'] ?? 'FC'); ?>
                </small>
                ${otherFeesSummary}
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    `;
}

// Fonction pour afficher la notification quand tous les frais sont soldés
function showAllFeesPaidNotification(paymentStatus) {
    let notification = document.getElementById('priority-notification');
    if (!notification) {
        notification = document.createElement('div');
        notification.id = 'priority-notification';
        notification.className = 'alert alert-success alert-dismissible fade show';
        notification.style.marginTop = '10px';
        
        const typeSelect = document.getElementById('type_paiement');
        typeSelect.parentNode.insertBefore(notification, typeSelect.nextSibling);
    }
    
    notification.innerHTML = `
        <div class="d-flex align-items-center">
            <i class="fas fa-check-circle me-2"></i>
            <div class="flex-grow-1">
                <strong>Excellent !</strong> Tous les frais sont soldés pour cet élève.
                <br>
                <small class="text-muted">Vous pouvez sélectionner n'importe quel type de frais pour un paiement supplémentaire.</small>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    `;
}

// Afficher les informations de l'élève sélectionné
function showStudentInfo(student) {
    const eleveInfo = document.getElementById('eleve-info');
    const eleveDetails = document.getElementById('eleve-details');
    
    eleveDetails.innerHTML = `
        <div class="text-center mb-3">
            <div class="avatar-placeholder bg-primary text-white rounded-circle d-inline-flex align-items-center justify-content-center" style="width: 60px; height: 60px;">
                <i class="fas fa-user fa-2x"></i>
            </div>
        </div>
        <h6 class="text-center mb-3">${student.nom} ${student.prenom}</h6>
        <table class="table table-borderless table-sm">
            <tr>
                <td><strong>Matricule :</strong></td>
                <td><span class="badge bg-secondary">${student.matricule}</span></td>
            </tr>
            <tr>
                <td><strong>Classe :</strong></td>
                <td><span class="badge bg-primary">${student.classe}</span></td>
                </tr>
            <tr>
                <td><strong>Statut :</strong></td>
                <td><span class="badge bg-info">${student.status}</span></td>
            </tr>
        </table>
        <div class="text-center mt-3">
            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="changeStudent()">
                <i class="fas fa-edit me-1"></i>Changer d'élève
            </button>
        </div>
    `;
    
    eleveInfo.style.display = 'block';
}

// Changer d'élève
function changeStudent() {
    // Réinitialiser les champs
    document.getElementById('eleve_id').value = '';
    document.getElementById('eleve_classe_id').value = '';
    document.getElementById('eleve_display').value = '';
    document.getElementById('eleve-info').style.display = 'none';
    
    // Masquer la sélection des frais
    document.getElementById('frais-selection').style.display = 'none';
    
    // Nettoyer les notifications de priorité
    const notification = document.getElementById('priority-notification');
    if (notification) {
        notification.remove();
    }
    
    // Réinitialiser le sélecteur de type
    document.getElementById('type_paiement').value = '';
    
    // Rouvrir le modal
    const modal = new bootstrap.Modal(document.getElementById('eleveModal'));
    modal.show();
}

// Initialiser le DataTable quand le modal s'ouvre
document.getElementById('eleveModal').addEventListener('shown.bs.modal', function() {
    if (!eleveTable) {
        initializeEleveTable();
    } else {
        eleveTable.ajax.reload();
    }
});

// Permettre de cliquer sur le champ d'affichage pour rouvrir le modal
document.getElementById('eleve_display').addEventListener('click', function() {
    const modal = new bootstrap.Modal(document.getElementById('eleveModal'));
    modal.show();
});



// Navigation au clavier dans les résultats
eleveSearch.addEventListener('keydown', function(e) {
    const results = eleveResults.querySelectorAll('.eleve-result');
    const currentIndex = Array.from(results).findIndex(el => el.classList.contains('active'));
    
    if (e.key === 'ArrowDown') {
        e.preventDefault();
        if (currentIndex < results.length - 1) {
            if (currentIndex >= 0) results[currentIndex].classList.remove('active');
            results[currentIndex + 1].classList.add('active');
        }
    } else if (e.key === 'ArrowUp') {
        e.preventDefault();
        if (currentIndex > 0) {
            results[currentIndex].classList.remove('active');
            results[currentIndex - 1].classList.add('active');
        }
    } else if (e.key === 'Enter') {
        e.preventDefault();
        const activeResult = eleveResults.querySelector('.eleve-result.active');
        if (activeResult) {
            activeResult.click();
        }
    }
});



// Mise à jour du symbole de la devise selon la sélection
function updateMontantSymbole() {
    const deviseSelect = document.getElementById('devise_id');
    const symboleSpan = document.getElementById('montant-symbole');
    const selectedOption = deviseSelect.options[deviseSelect.selectedIndex];
    
    if (selectedOption && selectedOption.dataset.symbole) {
        symboleSpan.textContent = selectedOption.dataset.symbole;
    } else {
        symboleSpan.textContent = '<?php echo htmlspecialchars($devise_par_defaut['symbole'] ?? 'FC'); ?>';
    }
    
    // Mettre à jour la conversion
    updateConversion();
}

// Mise à jour de l'affichage de la conversion
function updateConversion() {
    const montant = parseFloat(document.getElementById('montant').value) || 0;
    const deviseSelect = document.getElementById('devise_id');
    const selectedOption = deviseSelect.options[deviseSelect.selectedIndex];
    const conversionDisplay = document.getElementById('conversion-display');
    const conversionDetails = document.getElementById('conversion-details');
    
    if (montant > 0 && selectedOption && selectedOption.dataset.taux) {
        const taux = parseFloat(selectedOption.dataset.taux);
        const symbole = selectedOption.dataset.symbole;
        const code = selectedOption.text.split(' - ')[0];
        
        // Calculer le montant en devise par défaut
        const montantDeviseDefaut = montant / taux;
        
        conversionDetails.innerHTML = `
            <div class="row">
                <div class="col-md-6">
                    <strong>Montant saisi :</strong> ${montant.toLocaleString()} ${symbole} (${code})
                </div>
                <div class="col-md-6">
                    <strong>Équivalent en devise par défaut :</strong> ${montantDeviseDefaut.toLocaleString()} ${devise_par_defaut_symbole}
                </div>
            </div>
            <small class="text-muted mt-2 d-block">
                Taux de conversion : 1 ${code} = ${(1/taux).toLocaleString()} ${devise_par_defaut_symbole}
            </small>
        `;
        
        conversionDisplay.style.display = 'block';
    } else {
        conversionDisplay.style.display = 'none';
    }
    
    // Mettre à jour le résumé
    updatePaiementResume();
}

// Initialiser le symbole au chargement de la page
document.addEventListener('DOMContentLoaded', function() {
    // Sélectionner automatiquement la devise par défaut si aucune n'est sélectionnée
    const deviseSelect = document.getElementById('devise_id');
    if (deviseSelect && !deviseSelect.value) {
        const defaultOption = deviseSelect.querySelector('option[data-default="true"]');
        if (defaultOption) {
            deviseSelect.value = defaultOption.value;
            updateMontantSymbole();
        }
    } else {
        updateMontantSymbole();
    }
});

// Événements pour la conversion en temps réel
document.getElementById('montant').addEventListener('input', updateConversion);
document.getElementById('devise_id').addEventListener('change', updateConversion);

// Empêcher la sélection d'options désactivées
document.getElementById('type_paiement').addEventListener('change', function(e) {
    const selectedOption = e.target.options[e.target.selectedIndex];
    if (selectedOption && selectedOption.disabled) {
        // Revenir à l'option précédente valide
        const validOptions = Array.from(e.target.options).filter(option => !option.disabled && option.value);
        if (validOptions.length > 0) {
            e.target.value = validOptions[0].value;
        }
        
        // Afficher un message d'erreur
        showError('Vous ne pouvez pas sélectionner ce type de frais. Veuillez d\'abord solder les frais de priorité plus élevée.');
    }
});

// Formatage du montant en temps réel
document.getElementById('montant').addEventListener('input', function() {
    let value = this.value.replace(/\D/g, '');
    if (value) {
        this.value = parseInt(value);
    }
});

// Gestion des champs conditionnels selon le mode de paiement
document.getElementById('mode_paiement').addEventListener('change', function() {
    const referenceField = document.getElementById('reference');
    const referenceLabel = referenceField.previousElementSibling;
    
    switch(this.value) {
        case 'cheque':
            referenceLabel.innerHTML = 'Numéro de chèque <span class="text-danger">*</span>';
            referenceField.required = true;
            referenceField.placeholder = 'Numéro du chèque';
            break;
        case 'virement':
            referenceLabel.innerHTML = 'Référence virement <span class="text-danger">*</span>';
            referenceField.required = true;
            referenceField.placeholder = 'Référence du virement';
            break;
        case 'mobile_money':
            referenceLabel.innerHTML = 'ID transaction';
            referenceField.required = false;
            referenceField.placeholder = 'ID de la transaction';
            break;
        default:
            referenceLabel.innerHTML = 'Référence';
            referenceField.required = false;
            referenceField.placeholder = 'Référence optionnelle';
    }
});

// Validation du formulaire
document.querySelector('form').addEventListener('submit', function(e) {
    const requiredFields = this.querySelectorAll('[required]');
    let isValid = true;
    
    requiredFields.forEach(function(field) {
        if (!field.value.trim()) {
            field.classList.add('is-invalid');
            isValid = false;
        } else {
            field.classList.remove('is-invalid');
        }
    });
    
    // Validation spécifique pour le type de paiement
    const typeSelect = document.getElementById('type_paiement');
    const selectedOption = typeSelect.options[typeSelect.selectedIndex];
    
    if (selectedOption && selectedOption.disabled) {
        e.preventDefault();
        showError('Vous ne pouvez pas sélectionner ce type de frais. Veuillez d\'abord solder les frais de priorité plus élevée.');
        typeSelect.classList.add('is-invalid');
        isValid = false;
    } else {
        typeSelect.classList.remove('is-invalid');
    }
    
    if (!isValid) {
        e.preventDefault();
        if (!typeSelect.classList.contains('is-invalid')) {
            showError('Veuillez remplir tous les champs obligatoires.');
        }
    }
});

// Fonction pour afficher les erreurs
function showError(message) {
    // Supprimer les anciennes alertes d'erreur
    const existingAlert = document.querySelector('.alert-danger');
    if (existingAlert) {
        existingAlert.remove();
    }
    
    // Créer une nouvelle alerte
    const alert = document.createElement('div');
    alert.className = 'alert alert-danger alert-dismissible fade show';
    alert.innerHTML = `
        <i class="fas fa-exclamation-triangle me-2"></i>
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    
    // Insérer l'alerte en haut du formulaire
    const form = document.querySelector('form');
    form.insertBefore(alert, form.firstChild);
    
    // Faire défiler vers le haut
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

// Fonction pour mettre à jour les options de frais selon la classe de l'élève
function updateFraisOptions() {
    const eleveClasseId = document.getElementById('eleve_classe_id').value;
    const typePaiement = document.getElementById('type_paiement').value;
    const fraisSelection = document.getElementById('frais-selection');
    const fraisSelect = document.getElementById('frais_id');
    
    if (!eleveClasseId || !typePaiement) {
        fraisSelection.style.display = 'none';
        return;
    }
    
    // Charger les frais pour cette classe et ce type
    fetch(`get_frais_by_classe.php?classe_id=${eleveClasseId}&type_frais=${typePaiement}`)
        .then(response => response.json())
        .then(data => {
            fraisSelect.innerHTML = '<option value="">Sélectionner un frais...</option>';
            
            if (data.success && data.frais.length > 0) {
                data.frais.forEach(frais => {
                    const option = document.createElement('option');
                    option.value = frais.id;
                    option.textContent = `${frais.libelle} - ${frais.montant} ${frais.devise_symbole || '<?php echo htmlspecialchars($devise_par_defaut['symbole'] ?? 'FC'); ?>'}`;
                    option.dataset.montant = frais.montant;
                    option.dataset.description = frais.description || '';
                    fraisSelect.appendChild(option);
                });
                
                fraisSelection.style.display = 'block';
            } else {
                fraisSelection.style.display = 'none';
            }
        })
        .catch(error => {
            console.error('Erreur lors du chargement des frais:', error);
            fraisSelection.style.display = 'none';
        });
}

// Fonction pour mettre à jour le montant et la description depuis le frais sélectionné
function updateMontantFromFrais() {
    const fraisSelect = document.getElementById('frais_id');
    const selectedOption = fraisSelect.options[fraisSelect.selectedIndex];
    
    if (selectedOption && selectedOption.dataset.montant) {
        document.getElementById('montant').value = selectedOption.dataset.montant;
        if (selectedOption.dataset.description) {
            document.getElementById('description').value = selectedOption.dataset.description;
        }
        // Déclencher la mise à jour de la conversion
        updateConversion();
        // Mettre à jour le résumé
        updatePaiementResume();
    }
}

// Fonction pour mettre à jour le résumé du paiement
function updatePaiementResume() {
    const montant = document.getElementById('montant').value;
    const deviseSelect = document.getElementById('devise_id');
    const selectedOption = deviseSelect.options[deviseSelect.selectedIndex];
    
    if (montant && selectedOption) {
        const resumeCard = document.getElementById('paiement-resume');
        const resumeMontant = document.getElementById('resume-montant');
        const resumeDevise = document.getElementById('resume-devise');
        const resumeConversion = document.getElementById('resume-conversion');
        
        // Afficher le résumé
        resumeCard.style.display = 'block';
        
        // Mettre à jour le montant
        resumeMontant.textContent = `${parseFloat(montant).toLocaleString()} ${selectedOption.dataset.symbole || '<?php echo htmlspecialchars($devise_par_defaut['symbole'] ?? 'FC'); ?>'}`;
        
        // Mettre à jour la devise
        resumeDevise.textContent = selectedOption.text.split(' - ')[0];
        
        // Mettre à jour la conversion si applicable
        if (selectedOption.dataset.taux) {
            const taux = parseFloat(selectedOption.dataset.taux);
            const montantDeviseDefaut = montant / taux;
            resumeConversion.textContent = `Équivalent : ${montantDeviseDefaut.toLocaleString()} ${devise_par_defaut_symbole}`;
            resumeConversion.style.display = 'block';
        } else {
            resumeConversion.style.display = 'none';
        }
    }
}
</script>

<!-- DataTables JS -->
<script type="text/javascript" src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
<script type="text/javascript" src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>
<script type="text/javascript" src="https://cdn.datatables.net/buttons/2.4.2/js/dataTables.buttons.min.js"></script>
<script type="text/javascript" src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.bootstrap5.min.js"></script>
<script type="text/javascript" src="https://cdn.datatables.net/responsive/2.5.0/js/dataTables.responsive.min.js"></script>
<script type="text/javascript" src="https://cdn.datatables.net/responsive/2.5.0/js/responsive.bootstrap5.min.js"></script>

<?php include '../../../includes/footer.php'; ?>
