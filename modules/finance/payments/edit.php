<?php
/**
 * Module de gestion financière - Modifier un paiement
 * Application de gestion scolaire - République Démocratique du Congo
 */

require_once '../../../config/config.php';
require_once '../../../config/database.php';
require_once '../../../includes/functions.php';

// Vérifier l'authentification et les permissions
requireLogin();
if (!checkPermission('finance')) {
    showMessage('error', 'Accès refusé à cette fonctionnalité.');
    redirectTo('index.php');
}

// Récupérer l'ID du paiement
$id = (int)($_GET['id'] ?? 0);
if (!$id) {
    showMessage('error', 'Paiement non spécifié.');
    redirectTo('index.php');
}

// Récupérer les informations du paiement
$sql = "SELECT p.*, 
               e.nom, e.prenom, e.numero_matricule,
               c.nom as classe_nom, c.niveau
        FROM paiements p
        JOIN eleves e ON p.eleve_id = e.id
        JOIN inscriptions i ON e.id = i.eleve_id AND i.annee_scolaire_id = p.annee_scolaire_id
        JOIN classes c ON i.classe_id = c.id
        WHERE p.id = ?";

$paiement = $database->query($sql, [$id])->fetch();

if (!$paiement) {
    showMessage('error', 'Paiement non trouvé.');
    redirectTo('index.php');
}

$page_title = 'Modifier le paiement - ' . $paiement['recu_numero'];

// Obtenir l'année scolaire actuelle
$current_year = getCurrentAcademicYear();

$errors = [];
$success = false;

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validation des données
    $type_paiement = sanitizeInput($_POST['type_paiement'] ?? '');
    $montant = (float)($_POST['montant'] ?? 0);
    $mode_paiement = sanitizeInput($_POST['mode_paiement'] ?? '');
    $date_paiement = sanitizeInput($_POST['date_paiement'] ?? '');
    $observation = sanitizeInput($_POST['observation'] ?? '');
    $mois_concerne = sanitizeInput($_POST['mois_concerne'] ?? '');
    
    // Validation des champs obligatoires
    if (empty($type_paiement)) $errors[] = 'Le type de paiement est obligatoire.';
    if ($montant <= 0) $errors[] = 'Le montant doit être supérieur à 0.';
    if (empty($mode_paiement)) $errors[] = 'Le mode de paiement est obligatoire.';
    if (empty($date_paiement)) $errors[] = 'La date de paiement est obligatoire.';
    
    // Validation de la date
    if (!empty($date_paiement) && !isValidDate($date_paiement)) {
        $errors[] = 'La date de paiement n\'est pas valide.';
    }
    
    // Validation du montant
    if ($montant > 10000000) { // 10 millions FC max
        $errors[] = 'Le montant ne peut pas dépasser 10 000 000 FC.';
    }
    
    // Si pas d'erreurs, mettre à jour le paiement
    if (empty($errors)) {
        try {
            $database->beginTransaction();
            
            // Mettre à jour le paiement
            $sql = "UPDATE paiements SET 
                        type_paiement = ?, 
                        montant = ?, 
                        mode_paiement = ?, 
                        date_paiement = ?, 
                        observation = ?,
                        mois_concerne = ?,
                        updated_at = CURRENT_TIMESTAMP
                    WHERE id = ?";
            
            $database->execute($sql, [
                $type_paiement, $montant, $mode_paiement, $date_paiement,
                $observation, $mois_concerne, $id
            ]);
            
            $database->commit();
            
            showMessage('success', 'Paiement modifié avec succès !');
            redirectTo('view.php?id=' . $id);
            
        } catch (Exception $e) {
            $database->rollback();
            $errors[] = 'Erreur lors de la modification : ' . $e->getMessage();
        }
    }
}

include '../../../includes/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">
        <i class="fas fa-edit me-2"></i>
        Modifier le paiement
    </h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <div class="btn-group me-2">
            <a href="view.php?id=<?php echo $id; ?>" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-1"></i>
                Retour
            </a>
        </div>
        <div class="btn-group">
            <a href="receipt.php?id=<?php echo $id; ?>" class="btn btn-outline-primary">
                <i class="fas fa-receipt me-1"></i>
                Voir le reçu
            </a>
        </div>
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

<div class="row">
    <div class="col-lg-8">
        <!-- Informations de l'élève -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-user-graduate me-2"></i>
                    Informations de l'élève
                </h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <p><strong>Nom complet :</strong> <?php echo htmlspecialchars($paiement['nom'] . ' ' . $paiement['prenom']); ?></p>
                        <p><strong>Matricule :</strong> <?php echo htmlspecialchars($paiement['numero_matricule']); ?></p>
                    </div>
                    <div class="col-md-6">
                        <p><strong>Classe :</strong> <?php echo htmlspecialchars($paiement['classe_nom']); ?></p>
                        <p><strong>Niveau :</strong> <?php echo ucfirst($paiement['niveau']); ?></p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Formulaire de modification -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-edit me-2"></i>
                    Modifier les informations du paiement
                </h5>
            </div>
            <div class="card-body">
                <form method="POST" class="needs-validation" novalidate>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="type_paiement" class="form-label">
                                Type de paiement <span class="text-danger">*</span>
                            </label>
                            <select class="form-select" id="type_paiement" name="type_paiement" required>
                                <option value="">Sélectionner le type</option>
                                <option value="inscription" <?php echo ($paiement['type_paiement'] ?? '') === 'inscription' ? 'selected' : ''; ?>>
                                    Frais d'inscription
                                </option>
                                <option value="mensualite" <?php echo ($paiement['type_paiement'] ?? '') === 'mensualite' ? 'selected' : ''; ?>>
                                    Mensualité
                                </option>
                                <option value="examen" <?php echo ($paiement['type_paiement'] ?? '') === 'examen' ? 'selected' : ''; ?>>
                                    Frais d'examen
                                </option>
                                <option value="autre" <?php echo ($paiement['type_paiement'] ?? '') === 'autre' ? 'selected' : ''; ?>>
                                    Autre
                                </option>
                            </select>
                            <div class="invalid-feedback">
                                Veuillez sélectionner un type de paiement.
                            </div>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="montant" class="form-label">
                                Montant (FC) <span class="text-danger">*</span>
                            </label>
                            <input type="number" 
                                   class="form-control" 
                                   id="montant" 
                                   name="montant" 
                                   value="<?php echo htmlspecialchars($paiement['montant'] ?? ''); ?>"
                                   min="1" 
                                   max="10000000" 
                                   step="0.01" 
                                   required>
                            <div class="invalid-feedback">
                                Veuillez saisir un montant valide.
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="mode_paiement" class="form-label">
                                Mode de paiement <span class="text-danger">*</span>
                            </label>
                            <select class="form-select" id="mode_paiement" name="mode_paiement" required>
                                <option value="">Sélectionner le mode</option>
                                <option value="especes" <?php echo ($paiement['mode_paiement'] ?? '') === 'especes' ? 'selected' : ''; ?>>
                                    Espèces
                                </option>
                                <option value="cheque" <?php echo ($paiement['mode_paiement'] ?? '') === 'cheque' ? 'selected' : ''; ?>>
                                    Chèque
                                </option>
                                <option value="virement" <?php echo ($paiement['mode_paiement'] ?? '') === 'virement' ? 'selected' : ''; ?>>
                                    Virement bancaire
                                </option>
                                <option value="mobile_money" <?php echo ($paiement['mode_paiement'] ?? '') === 'mobile_money' ? 'selected' : ''; ?>>
                                    Mobile Money
                                </option>
                            </select>
                            <div class="invalid-feedback">
                                Veuillez sélectionner un mode de paiement.
                            </div>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="date_paiement" class="form-label">
                                Date de paiement <span class="text-danger">*</span>
                            </label>
                            <input type="date" 
                                   class="form-control" 
                                   id="date_paiement" 
                                   name="date_paiement" 
                                   value="<?php echo htmlspecialchars($paiement['date_paiement'] ?? ''); ?>"
                                   required>
                            <div class="invalid-feedback">
                                Veuillez sélectionner une date de paiement.
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="mois_concerne" class="form-label">Mois concerné</label>
                            <input type="text" 
                                   class="form-control" 
                                   id="mois_concerne" 
                                   name="mois_concerne" 
                                   value="<?php echo htmlspecialchars($paiement['mois_concerne'] ?? ''); ?>"
                                   placeholder="Ex: Janvier 2024">
                            <div class="form-text">Pour les mensualités uniquement</div>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="observation" class="form-label">Observation</label>
                            <textarea class="form-control" 
                                      id="observation" 
                                      name="observation" 
                                      rows="3"
                                      placeholder="Observation optionnelle..."><?php echo htmlspecialchars($paiement['observation'] ?? ''); ?></textarea>
                        </div>
                    </div>
                    
                    <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                        <a href="view.php?id=<?php echo $id; ?>" class="btn btn-outline-secondary me-md-2">
                            <i class="fas fa-times me-1"></i>
                            Annuler
                        </a>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-1"></i>
                            Enregistrer les modifications
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <!-- Informations du reçu -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-receipt me-2"></i>
                    Informations du reçu
                </h5>
            </div>
            <div class="card-body">
                <table class="table table-borderless table-sm">
                    <tr>
                        <td class="fw-bold">N° Reçu :</td>
                        <td><?php echo htmlspecialchars($paiement['recu_numero']); ?></td>
                    </tr>
                    <tr>
                        <td class="fw-bold">Date création :</td>
                        <td><?php echo formatDate($paiement['created_at'] ?? $paiement['date_paiement']); ?></td>
                    </tr>
                    <tr>
                        <td class="fw-bold">Montant actuel :</td>
                        <td class="text-success fw-bold"><?php echo formatMoney($paiement['montant']); ?></td>
                    </tr>
                </table>
                
                <div class="alert alert-info">
                    <small>
                        <i class="fas fa-info-circle me-1"></i>
                        La modification d'un paiement peut affecter les rapports financiers.
                    </small>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Validation du formulaire
(function() {
    'use strict';
    window.addEventListener('load', function() {
        var forms = document.getElementsByClassName('needs-validation');
        var validation = Array.prototype.filter.call(forms, function(form) {
            form.addEventListener('submit', function(event) {
                if (form.checkValidity() === false) {
                    event.preventDefault();
                    event.stopPropagation();
                }
                form.classList.add('was-validated');
            }, false);
        });
    }, false);
})();
</script>

<?php include '../../../includes/footer.php'; ?>
