<?php
/**
 * Module de Réinscription avec Vérification des Frais et Statut
 * Application de gestion scolaire - République Démocratique du Congo
 */

require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../includes/functions.php';
require_once '../../includes/permissions-pages.php';

// Vérifier l'authentification et les permissions
requireLogin();
requirePagePermissionFromDB('students', 're-enrollment', 'create', '../../dashboard.php');

$page_title = 'Réinscription - Vérification et Carte Élève';

// Obtenir l'année scolaire actuelle
$current_year = getCurrentAcademicYear();

// Traitement des actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'verifier_frais':
                $eleve_id = (int)$_POST['eleve_id'];
                $annee_scolaire_id = (int)$_POST['annee_scolaire_id'];
                
                try {
                    // Récupérer les informations de l'élève et ses frais
                    $stmt = $database->query(
                        "SELECT e.*, i.classe_id, c.nom as classe_nom, c.niveau,
                                i.frais_inscription_paye, i.date_inscription, i.status
                         FROM eleves e
                         JOIN inscriptions i ON e.id = i.eleve_id
                         JOIN classes c ON i.classe_id = c.id
                         WHERE e.id = ? AND i.annee_scolaire_id = ?",
                        [$eleve_id, $annee_scolaire_id]
                    );
                    
                    $eleve_info = $stmt->fetch();
                    
                    if ($eleve_info) {
                        // Calculer les frais restants
                        $frais_inscription_restants = $eleve_info['frais_inscription_paye'] ?? 0;
                        
                        $result = [
                            'success' => true,
                            'eleve' => $eleve_info,
                            'frais_inscription_restants' => $frais_inscription_restants,
                            'en_ordre' => ($frais_inscription_restants > 0)
                        ];
                        
                        echo json_encode($result);
                        exit;
                    } else {
                        echo json_encode(['success' => false, 'message' => 'Élève non trouvé']);
                        exit;
                    }
                } catch (Exception $e) {
                    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
                    exit;
                }
                break;
                
            case 'reinscrire_avec_verification':
                $eleve_id = (int)$_POST['eleve_id'];
                $confirmation_frais = $_POST['confirmation_frais'] === 'true';
                $commentaire = sanitizeInput($_POST['commentaire'] ?? '');
                
                try {
                    // Vérifier si l'élève n'est pas déjà inscrit pour cette année
                    $stmt = $database->query(
                        "SELECT id FROM inscriptions WHERE eleve_id = ? AND annee_scolaire_id = ?",
                        [$eleve_id, $current_year['id']]
                    );
                    
                    if (!$stmt->fetch()) {
                        // Récupérer la classe de l'année précédente
                        $stmt = $database->query(
                            "SELECT i.classe_id, c.niveau 
                             FROM inscriptions i 
                             JOIN classes c ON i.classe_id = c.id 
                             WHERE i.eleve_id = ? AND i.annee_scolaire_id = ? AND i.status = 'inscrit'
                             ORDER BY i.created_at DESC LIMIT 1",
                            [$eleve_id, $current_year['id'] - 1]
                        );
                        
                        $ancienne_inscription = $stmt->fetch();
                        
                        if ($ancienne_inscription) {
                            // Déterminer la nouvelle classe (promotion automatique)
                            $nouvelle_classe = getNouvelleClasse($ancienne_inscription['classe_id'], $ancienne_inscription['niveau']);
                            
                            if ($nouvelle_classe) {
                                // Créer la nouvelle inscription
                                $database->query(
                                    "INSERT INTO inscriptions (eleve_id, classe_id, annee_scolaire_id, date_inscription, status, created_at) 
                                     VALUES (?, ?, ?, NOW(), 'inscrit', NOW())",
                                    [$eleve_id, $nouvelle_classe['id'], $current_year['id']]
                                );
                                
                                // Mettre à jour le statut de l'élève
                                $database->query(
                                    "UPDATE eleves SET status = 'actif', updated_at = NOW() WHERE id = ?",
                                    [$eleve_id]
                                );
                                
                                // Logger l'action avec commentaire sur les frais
                                $message_log = "Réinscription de l'élève ID: $eleve_id pour l'année " . $current_year['annee'];
                                if ($commentaire) {
                                    $message_log .= " - Commentaire: $commentaire";
                                }
                                if (!$confirmation_frais) {
                                    $message_log .= " - Frais non vérifiés";
                                }
                                
                                logUserAction('reinscription_avec_verification', 'students', $message_log);
                                
                                showMessage('success', "Élève réinscrit avec succès pour l'année " . $current_year['annee']);
                            } else {
                                showMessage('error', "Impossible de déterminer la nouvelle classe pour cet élève");
                            }
                        } else {
                            showMessage('error', "Aucune inscription précédente trouvée pour cet élève");
                        }
                    } else {
                        showMessage('warning', "Cet élève est déjà inscrit pour l'année " . $current_year['annee']);
                    }
                } catch (Exception $e) {
                    showMessage('error', 'Erreur lors de la réinscription: ' . $e->getMessage());
                }
                break;
        }
    }
}

// Traitement des actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'reinscrire_eleves':
                if (isset($_POST['eleves_selectionnes']) && is_array($_POST['eleves_selectionnes'])) {
                    $eleves_reinscrits = 0;
                    $errors = [];
                    
                    foreach ($_POST['eleves_selectionnes'] as $eleve_id) {
                        try {
                            // Vérifier si l'élève n'est pas déjà inscrit pour cette année
                            $stmt = $database->query(
                                "SELECT id FROM inscriptions WHERE eleve_id = ? AND annee_scolaire_id = ?",
                                [$eleve_id, $current_year['id']]
                            );
                            
                            if (!$stmt->fetch()) {
                                // Récupérer la classe de l'année précédente
                                $stmt = $database->query(
                                    "SELECT i.classe_id, c.niveau 
                                     FROM inscriptions i 
                                     JOIN classes c ON i.classe_id = c.id 
                                     WHERE i.eleve_id = ? AND i.annee_scolaire_id = ? AND i.status = 'inscrit'
                                     ORDER BY i.created_at DESC LIMIT 1",
                                    [$eleve_id, $current_year['id'] - 1]
                                );
                                
                                $ancienne_inscription = $stmt->fetch();
                                
                                if ($ancienne_inscription) {
                                    // Déterminer la nouvelle classe (promotion automatique)
                                    $nouvelle_classe = getNouvelleClasse($ancienne_inscription['classe_id'], $ancienne_inscription['niveau']);
                                    
                                    if ($nouvelle_classe) {
                                        // Créer la nouvelle inscription
                                        $database->query(
                                            "INSERT INTO inscriptions (eleve_id, classe_id, annee_scolaire_id, date_inscription, status, created_at) 
                                             VALUES (?, ?, ?, NOW(), 'inscrit', NOW())",
                                            [$eleve_id, $nouvelle_classe['id'], $current_year['id']]
                                        );
                                        
                                        // Mettre à jour le statut de l'élève
                                        $database->query(
                                            "UPDATE eleves SET status = 'actif', updated_at = NOW() WHERE id = ?",
                                            [$eleve_id]
                                        );
                                        
                                        $eleves_reinscrits++;
                                        
                                        // Logger l'action
                                        logUserAction('reinscription', 'students', "Réinscription de l'élève ID: $eleve_id pour l'année " . $current_year['annee']);
                                    } else {
                                        $errors[] = "Impossible de déterminer la nouvelle classe pour l'élève ID: $eleve_id";
                                    }
                                } else {
                                    $errors[] = "Aucune inscription précédente trouvée pour l'élève ID: $eleve_id";
                                }
                            }
                        } catch (Exception $e) {
                            $errors[] = "Erreur lors de la réinscription de l'élève ID: $eleve_id: " . $e->getMessage();
                        }
                    }
                    
                    if ($eleves_reinscrits > 0) {
                        showMessage('success', "$eleves_reinscrits élève(s) réinscrit(s) avec succès pour l'année " . $current_year['annee']);
                    }
                    
                    if (!empty($errors)) {
                        showMessage('error', 'Erreurs lors de la réinscription: ' . implode(', ', $errors));
                    }
                }
                break;
                
            case 'inscrire_nouveau':
                // Traitement de l'inscription d'un nouvel élève
                $nom = sanitizeInput($_POST['nom']);
                $prenom = sanitizeInput($_POST['prenom']);
                $sexe = sanitizeInput($_POST['sexe']);
                $date_naissance = sanitizeInput($_POST['date_naissance']);
                $classe_id = (int)$_POST['classe_id'];
                $frais_inscription = (float)$_POST['frais_inscription'];
                
                try {
                    // Générer un matricule unique
                    $numero_matricule = generateMatricule();
                    
                    // Insérer le nouvel élève
                    $database->query(
                        "INSERT INTO eleves (numero_matricule, nom, prenom, sexe, date_naissance, status, created_at, updated_at) 
                         VALUES (?, ?, ?, ?, ?, 'actif', NOW(), NOW())",
                        [$numero_matricule, $nom, $prenom, $sexe, $date_naissance]
                    );
                    
                    $eleve_id = $database->lastInsertId();
                    
                    // Générer automatiquement la carte d'élève
                    require_once '../cartes_eleves/auto-generate.php';
                    $carte_id = autoGenerateStudentCard($eleve_id, $current_year['id']);
                    
                    // Créer l'inscription
                    $database->query(
                        "INSERT INTO inscriptions (eleve_id, classe_id, annee_scolaire_id, date_inscription, frais_inscription_paye, status, created_at) 
                         VALUES (?, ?, ?, NOW(), ?, 'inscrit', NOW())",
                        [$eleve_id, $classe_id, $current_year['id'], $frais_inscription]
                    );
                    
                    showMessage('success', "Nouvel élève inscrit avec succès. Matricule: $numero_matricule");
                    
                    // Logger l'action
                    logUserAction('inscription_nouveau', 'students', "Inscription du nouvel élève: $nom $prenom (ID: $eleve_id)");
                    
                } catch (Exception $e) {
                    showMessage('error', 'Erreur lors de l\'inscription: ' . $e->getMessage());
                }
                break;
        }
    }
}

// Récupérer les élèves de l'année précédente
$eleves_annee_precedente = [];
try {
    $stmt = $database->query(
        "SELECT e.id, e.numero_matricule, e.nom, e.prenom, e.sexe, e.date_naissance, 
                i.classe_id, c.nom as classe_nom, c.niveau, i.status as statut_inscription,
                i.date_inscription, i.frais_inscription_paye,
                e.status as statut_eleve
         FROM eleves e
         JOIN inscriptions i ON e.id = i.eleve_id
         JOIN classes c ON i.classe_id = c.id
         WHERE i.annee_scolaire_id = ? AND i.status = 'inscrit'
         ORDER BY e.nom, e.prenom",
        [$current_year['id'] - 1]
    );
    $eleves_annee_precedente = $stmt->fetchAll();
} catch (Exception $e) {
    showMessage('error', 'Erreur lors de la récupération des élèves: ' . $e->getMessage());
}

// Récupérer les classes de l'année actuelle
$classes_annee_actuelle = [];
try {
    $stmt = $database->query(
        "SELECT id, nom, niveau, frais_inscription FROM classes WHERE annee_scolaire_id = ? ORDER BY niveau, nom",
        [$current_year['id']]
    );
    $classes_annee_actuelle = $stmt->fetchAll();
} catch (Exception $e) {
    showMessage('error', 'Erreur lors de la récupération des classes: ' . $e->getMessage());
}

// Statistiques
$stats = [
    'total_eleves_precedente' => count($eleves_annee_precedente),
    'deja_inscrits' => 0,
    'eleves_en_ordre_frais' => 0,
    'eleves_reussis' => 0
];

// Compter les élèves déjà inscrits pour l'année actuelle
try {
    $stmt = $database->query(
        "SELECT COUNT(DISTINCT e.id) as total FROM eleves e 
         JOIN inscriptions i ON e.id = i.eleve_id 
         WHERE i.annee_scolaire_id = ? AND i.status = 'inscrit'",
        [$current_year['id']]
    );
    $stats['deja_inscrits'] = $stmt->fetch()['total'];
} catch (Exception $e) {
    // Ignorer l'erreur pour les statistiques
}

// Compter les élèves en ordre de frais et actifs
foreach ($eleves_annee_precedente as $eleve) {
    $frais_inscription_paye = $eleve['frais_inscription_paye'] ?? 0;
    
    if ($frais_inscription_paye > 0) {
        $stats['eleves_en_ordre_frais']++;
    }
    
    if (($eleve['statut_eleve'] ?? '') === 'actif') {
        $stats['eleves_reussis']++;
    }
}

include '../../includes/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">
        <i class="fas fa-user-check me-2"></i>
        Réinscription - Vérification et Carte Élève
    </h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <div class="btn-group me-2">
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#verificationModal">
                <i class="fas fa-search me-1"></i>
                Vérifier frais
            </button>
            <button type="button" class="btn btn-success" id="btnReinscrireSelection">
                <i class="fas fa-sync-alt me-1"></i>
                Réinscrire sélection
            </button>
        </div>
        <div class="btn-group">
            <button type="button" class="btn btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">
                <i class="fas fa-download me-1"></i>
                Exporter
            </button>
            <ul class="dropdown-menu">
                <li><a class="dropdown-item" href="enrollment-history.php"><i class="fas fa-history me-2"></i>Historique</a></li>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item" href="export.php?format=excel"><i class="fas fa-file-excel me-2"></i>Excel</a></li>
                <li><a class="dropdown-item" href="export.php?format=pdf"><i class="fas fa-file-pdf me-2"></i>PDF</a></li>
            </ul>
        </div>
    </div>
</div>

<!-- Statistiques rapides -->
<div class="row mb-4">
    <div class="col-md-3">
        <div class="card text-white bg-primary">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h4><?php echo $stats['total_eleves_precedente']; ?></h4>
                        <p class="mb-0">Élèves année précédente</p>
                    </div>
                    <div class="align-self-center">
                        <i class="fas fa-users fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-white bg-success">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h4><?php echo $stats['eleves_en_ordre_frais']; ?></h4>
                        <p class="mb-0">En ordre de frais</p>
                    </div>
                    <div class="align-self-center">
                        <i class="fas fa-check-circle fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-white bg-warning">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h4><?php echo $stats['eleves_reussis']; ?></h4>
                        <p class="mb-0">Actifs</p>
                    </div>
                    <div class="align-self-center">
                        <i class="fas fa-graduation-cap fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-white bg-info">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h4><?php echo $current_year['annee']; ?></h4>
                        <p class="mb-0">Année scolaire</p>
                    </div>
                    <div class="align-self-center">
                        <i class="fas fa-calendar-alt fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Filtres de recherche -->
<div class="card mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-md-4">
                <label for="search" class="form-label">Rechercher</label>
                <input type="text" 
                       class="form-control" 
                       id="search" 
                       name="search" 
                       placeholder="Nom, prénom ou matricule..."
                       value="<?php echo htmlspecialchars($_GET['search'] ?? ''); ?>">
            </div>
            <div class="col-md-3">
                <label for="niveau" class="form-label">Niveau</label>
                <select class="form-select" id="niveau" name="niveau">
                    <option value="">Tous les niveaux</option>
                    <option value="maternelle" <?php echo ($_GET['niveau'] ?? '') === 'maternelle' ? 'selected' : ''; ?>>Maternelle</option>
                    <option value="primaire" <?php echo ($_GET['niveau'] ?? '') === 'primaire' ? 'selected' : ''; ?>>Primaire</option>
                    <option value="secondaire" <?php echo ($_GET['niveau'] ?? '') === 'secondaire' ? 'selected' : ''; ?>>Secondaire</option>
                </select>
            </div>
            <div class="col-md-3">
                <label for="statut_eleve" class="form-label">Statut élève</label>
                <select class="form-select" id="statut_eleve" name="statut_eleve">
                    <option value="">Tous les statuts</option>
                    <option value="actif" <?php echo ($_GET['statut_eleve'] ?? '') === 'actif' ? 'selected' : ''; ?>>Actif</option>
                    <option value="transfere" <?php echo ($_GET['statut_eleve'] ?? '') === 'transfere' ? 'selected' : ''; ?>>Transféré</option>
                    <option value="abandonne" <?php echo ($_GET['statut_eleve'] ?? '') === 'abandonne' ? 'selected' : ''; ?>>Abandonné</option>
                    <option value="diplome" <?php echo ($_GET['statut_eleve'] ?? '') === 'diplome' ? 'selected' : ''; ?>>Diplômé</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">&nbsp;</label>
                <div class="d-grid">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search me-1"></i>
                        Filtrer
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Liste du personnel -->
<div class="card">
    <div class="card-header">
                    <h5 class="mb-0">
            <i class="fas fa-list me-2"></i>
            Élèves de l'année précédente - Vérification et Réinscription (<?php echo count($eleves_annee_precedente); ?>)
        </h5>
    </div>
    <div class="card-body">
        <?php if (!empty($eleves_annee_precedente)): ?>
            <form id="formReinscription" method="POST">
                <input type="hidden" name="action" value="reinscrire_eleves">
                
                <div class="table-responsive">
                    <table class="table table-striped table-hover datatable">
                        <thead>
                            <tr>
                                <th width="50">
                                    <input type="checkbox" id="selectAll" class="form-check-input">
                                </th>
                                <th>Matricule</th>
                                <th>Nom complet</th>
                                <th>Classe précédente</th>
                                <th>Statut élève</th>
                                <th>Date inscription</th>
                                <th>Frais inscription</th>
                                <th>Statut frais</th>
                                <th class="no-sort">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($eleves_annee_precedente as $eleve): ?>
                                <?php 
                                // Vérification de sécurité pour éviter les erreurs
                                if (!isset($eleve['classe_id']) || !isset($eleve['niveau']) || 
                                    !isset($eleve['nom']) || !isset($eleve['prenom']) || 
                                    !isset($eleve['numero_matricule']) || !isset($eleve['date_naissance'])) {
                                    continue; // Passer à l'élève suivant si les données sont incomplètes
                                }
                                
                                $deja_inscrit = false;
                                
                                // Vérifier si l'élève est déjà inscrit pour l'année actuelle
                                try {
                                    $stmt = $database->query(
                                        "SELECT id FROM inscriptions WHERE eleve_id = ? AND annee_scolaire_id = ?",
                                        [$eleve['id'], $current_year['id']]
                                    );
                                    $deja_inscrit = $stmt->fetch() ? true : false;
                                } catch (Exception $e) {
                                    // Ignorer l'erreur
                                }
                                
                                // Déterminer le statut des frais
                                $frais_inscription_paye = $eleve['frais_inscription_paye'] ?? 0;
                                $en_ordre_frais = ($frais_inscription_paye > 0);
                                ?>
                                <tr>
                                    <td>
                                        <?php if (!$deja_inscrit): ?>
                                            <input type="checkbox" name="eleves_selectionnes[]" value="<?php echo $eleve['id']; ?>" class="form-check-input eleve-checkbox">
                                        <?php else: ?>
                                            <i class="fas fa-check text-success" title="Déjà inscrit"></i>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($eleve['numero_matricule']); ?></strong>
                                    </td>
                                    <td>
                                        <div>
                                            <strong><?php echo htmlspecialchars($eleve['nom'] . ' ' . $eleve['prenom']); ?></strong>
                                            <br><small class="text-muted">
                                                <i class="fas fa-<?php echo $eleve['sexe'] === 'M' ? 'mars' : 'venus'; ?>"></i>
                                                <?php echo $eleve['sexe'] === 'M' ? 'Masculin' : 'Féminin'; ?>
                                                <?php if ($eleve['date_naissance']): ?>
                                                    - <?php echo calculateAge($eleve['date_naissance']); ?> ans
                                                <?php endif; ?>
                                            </small>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge bg-secondary">
                                            <?php echo htmlspecialchars($eleve['classe_nom']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php
                                        $statut = $eleve['statut_eleve'] ?? 'actif';
                                        $statut_colors = [
                                            'actif' => 'success',
                                            'transfere' => 'warning',
                                            'abandonne' => 'danger',
                                            'diplome' => 'info'
                                        ];
                                        $color = $statut_colors[$statut] ?? 'secondary';
                                        $statut_text = [
                                            'actif' => 'Actif',
                                            'transfere' => 'Transféré',
                                            'abandonne' => 'Abandonné',
                                            'diplome' => 'Diplômé'
                                        ];
                                        ?>
                                        <span class="badge bg-<?php echo $color; ?>">
                                            <?php echo $statut_text[$statut] ?? 'Non défini'; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <small class="text-muted">
                                            <i class="fas fa-calendar fa-xs"></i>
                                            <?php echo $eleve['date_inscription'] ? formatDate($eleve['date_inscription']) : '-'; ?>
                                        </small>
                                    </td>
                                    <td>
                                        <?php if ($frais_inscription_paye > 0): ?>
                                            <span class="text-success">
                                                <i class="fas fa-check"></i> <?php echo formatMoney($frais_inscription_paye); ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-danger">
                                                <i class="fas fa-times"></i> Non payé
                                            </span>
                                        <?php endif; ?>
                                    </td>

                                    <td>
                                        <?php if ($en_ordre_frais): ?>
                                            <span class="badge bg-success">En ordre</span>
                                        <?php else: ?>
                                            <span class="badge bg-danger">Non en ordre</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <button type="button" 
                                                    class="btn btn-outline-info btn-verifier-frais" 
                                                    title="Vérifier frais"
                                                    data-eleve-id="<?php echo $eleve['id']; ?>"
                                                    data-annee="<?php echo $current_year['id'] - 1; ?>">
                                                <i class="fas fa-search-dollar"></i>
                                            </button>
                                            <button type="button" 
                                                    class="btn btn-outline-primary btn-imprimer-carte" 
                                                    title="Imprimer carte"
                                                    data-eleve-id="<?php echo $eleve['id']; ?>">
                                                <i class="fas fa-print"></i>
                                            </button>
                                            <?php if (!$deja_inscrit): ?>
                                                <button type="button" 
                                                        class="btn btn-outline-success btn-reinscrire-individuel" 
                                                        title="Réinscrire individuellement"
                                                        data-eleve-id="<?php echo $eleve['id']; ?>"
                                                        data-eleve-nom="<?php echo htmlspecialchars($eleve['nom'] . ' ' . $eleve['prenom']); ?>">
                                                    <i class="fas fa-sync-alt"></i>
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </form>
        <?php else: ?>
            <div class="text-center py-5">
                <i class="fas fa-users fa-3x text-muted mb-3"></i>
                <h5 class="text-muted">Aucun élève trouvé pour l'année précédente</h5>
                <p class="text-muted">
                    Tous les élèves ont été réinscrits ou il n'y a pas d'élèves pour l'année précédente.
                </p>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Modal de vérification des frais -->
<div class="modal fade" id="verificationModal" tabindex="-1" aria-labelledby="verificationModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="verificationModalLabel">
                    <i class="fas fa-search-dollar me-2"></i>
                    Vérification des Frais de l'Élève
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label for="eleve_verification" class="form-label">Sélectionner un élève</label>
                        <select class="form-select" id="eleve_verification">
                            <option value="">Choisir un élève...</option>
                            <?php foreach ($eleves_annee_precedente as $eleve): ?>
                                <option value="<?php echo $eleve['id']; ?>">
                                    <?php echo htmlspecialchars($eleve['numero_matricule'] . ' - ' . $eleve['nom'] . ' ' . $eleve['prenom']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label for="annee_verification" class="form-label">Année scolaire</label>
                        <select class="form-select" id="annee_verification">
                            <?php 
                            // Extraire la première année de la chaîne (ex: "2024-2025" -> 2024)
                            $annee_courante = (int)explode('-', $current_year['annee'])[0];
                            ?>
                            <option value="<?php echo $current_year['id'] - 1; ?>"><?php echo ($annee_courante - 1) . '-' . $annee_courante; ?></option>
                            <?php if ($current_year['id'] > 2): ?>
                                <option value="<?php echo $current_year['id'] - 2; ?>"><?php echo ($annee_courante - 2) . '-' . ($annee_courante - 1); ?></option>
                            <?php endif; ?>
                        </select>
                    </div>
                </div>
                
                <div id="resultats_verification" class="d-none">
                    <hr>
                    <h6>Résultats de la vérification</h6>
                    <div class="row">
                        <div class="col-md-12">
                            <div class="card">
                                <div class="card-body">
                                    <h6 class="card-title">Frais d'inscription</h6>
                                    <p class="card-text" id="frais_inscription_resultat">-</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="alert mt-3" id="statut_frais_alert">
                        <!-- Le statut sera affiché ici -->
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fermer</button>
                <button type="button" class="btn btn-primary" id="btnVerifierFrais">
                    <i class="fas fa-search me-1"></i>
                    Vérifier
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modal de réinscription avec vérification -->
<div class="modal fade" id="reinscriptionModal" tabindex="-1" aria-labelledby="reinscriptionModalLabel" aria-hidden="true">
    <div class="modal-content">
        <div class="modal-header">
            <h5 class="modal-title" id="reinscriptionModalLabel">
                <i class="fas fa-sync-alt me-2"></i>
                Réinscription avec Vérification
            </h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <form method="POST" id="formReinscriptionModal">
            <div class="modal-body">
                <input type="hidden" name="action" value="reinscrire_avec_verification">
                <input type="hidden" name="eleve_id" id="eleve_id_reinscription">
                
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i>
                    <strong id="eleve_nom_reinscription"></strong> sera réinscrit pour l'année <?php echo $current_year['annee']; ?>
                </div>
                
                <div class="mb-3">
                    <label for="confirmation_frais" class="form-label">Confirmation des frais</label>
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="confirmation_frais" id="frais_verifies" value="true" required>
                        <label class="form-check-label" for="frais_verifies">
                            <i class="fas fa-check text-success"></i> Les frais sont vérifiés et en ordre
                        </label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="confirmation_frais" id="frais_non_verifies" value="false" required>
                        <label class="form-check-label" for="frais_non_verifies">
                            <i class="fas fa-exclamation-triangle text-warning"></i> Les frais ne sont pas vérifiés
                        </label>
                    </div>
                </div>
                
                <div class="mb-3">
                    <label for="commentaire" class="form-label">Commentaire (optionnel)</label>
                    <textarea class="form-control" id="commentaire" name="commentaire" rows="3" placeholder="Ajouter un commentaire sur la situation des frais..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                <button type="submit" class="btn btn-success">
                    <i class="fas fa-sync-alt me-1"></i>
                    Réinscrire l'élève
                </button>
            </div>
        </form>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Gestion de la sélection multiple
    const selectAllCheckbox = document.getElementById('selectAll');
    const eleveCheckboxes = document.querySelectorAll('.eleve-checkbox');
    
    // Mettre à jour l'état de "Tout sélectionner"
    function updateSelectAllState() {
        const selectedCount = document.querySelectorAll('.eleve-checkbox:checked').length;
        const totalCount = eleveCheckboxes.length;
        
        if (selectedCount === 0) {
            selectAllCheckbox.checked = false;
            selectAllCheckbox.indeterminate = false;
        } else if (selectedCount === totalCount) {
            selectAllCheckbox.checked = true;
            selectAllCheckbox.indeterminate = false;
        } else {
            selectAllCheckbox.checked = false;
            selectAllCheckbox.indeterminate = true;
        }
    }
    
    // Écouter les changements sur "Tout sélectionner"
    selectAllCheckbox.addEventListener('change', function() {
        eleveCheckboxes.forEach(checkbox => {
            checkbox.checked = this.checked;
        });
        updateSelectAllState();
    });
    
    // Écouter les changements sur les checkboxes individuelles
    eleveCheckboxes.forEach(checkbox => {
        checkbox.addEventListener('change', updateSelectAllState);
    });
    
    // Initialiser l'état
    updateSelectAllState();
    
    // Vérification des frais
    document.getElementById('btnVerifierFrais').addEventListener('click', function() {
        const eleveId = document.getElementById('eleve_verification').value;
        const anneeId = document.getElementById('annee_verification').value;
        
        if (!eleveId) {
            alert('Veuillez sélectionner un élève');
            return;
        }
        
        // Afficher un indicateur de chargement
        this.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Vérification...';
        this.disabled = true;
        
        fetch('re-enrollment.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `action=verifier_frais&eleve_id=${eleveId}&annee_scolaire_id=${anneeId}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Afficher les résultats
                document.getElementById('frais_inscription_resultat').innerHTML = 
                    data.frais_inscription_restants > 0 ? 
                    `<i class="fas fa-check text-success"></i> ${data.frais_inscription_restants} FC payés` :
                    `<i class="fas fa-times text-danger"></i> Non payés`;
                
                const alertDiv = document.getElementById('statut_frais_alert');
                if (data.en_ordre) {
                    alertDiv.className = 'alert alert-success mt-3';
                    alertDiv.innerHTML = '<i class="fas fa-check-circle me-2"></i><strong>En ordre :</strong> Tous les frais sont payés';
                } else {
                    alertDiv.className = 'alert alert-warning mt-3';
                    alertDiv.innerHTML = '<i class="fas fa-exclamation-triangle me-2"></i><strong>Attention :</strong> Certains frais ne sont pas payés';
                }
                
                document.getElementById('resultats_verification').classList.remove('d-none');
            } else {
                alert('Erreur : ' + data.message);
            }
        })
        .catch(error => {
            console.error('Erreur:', error);
            alert('Erreur lors de la vérification');
        })
        .finally(() => {
            // Restaurer le bouton
            this.innerHTML = '<i class="fas fa-search me-1"></i>Vérifier';
            this.disabled = false;
        });
    });
    
    // Boutons de vérification des frais individuels
    document.querySelectorAll('.btn-verifier-frais').forEach(btn => {
        btn.addEventListener('click', function() {
            const eleveId = this.dataset.eleveId;
            const anneeId = this.dataset.annee;
            
            document.getElementById('eleve_verification').value = eleveId;
            document.getElementById('annee_verification').value = anneeId;
            
            // Déclencher la vérification automatiquement
            document.getElementById('btnVerifierFrais').click();
            
            // Ouvrir le modal
            new bootstrap.Modal(document.getElementById('verificationModal')).show();
        });
    });
    
    // Boutons d'impression de carte
    document.querySelectorAll('.btn-imprimer-carte').forEach(btn => {
        btn.addEventListener('click', function() {
            const eleveId = this.dataset.eleveId;
            
            // Ouvrir la carte dans une nouvelle fenêtre pour impression
            const carteWindow = window.open(`carte-eleve.php?id=${eleveId}`, '_blank');
            if (carteWindow) {
                carteWindow.focus();
            }
        });
    });
    
    // Boutons de réinscription individuelle
    document.querySelectorAll('.btn-reinscrire-individuel').forEach(btn => {
        btn.addEventListener('click', function() {
            const eleveId = this.dataset.eleveId;
            const eleveNom = this.dataset.eleveNom;
            
            document.getElementById('eleve_id_reinscription').value = eleveId;
            document.getElementById('eleve_nom_reinscription').textContent = eleveNom;
            
            new bootstrap.Modal(document.getElementById('reinscriptionModal')).show();
        });
    });
    
    // Bouton de réinscription de la sélection
    const btnReinscrireSelection = document.getElementById('btnReinscrireSelection');
    if (btnReinscrireSelection) {
        btnReinscrireSelection.addEventListener('click', function() {
            const selectedCheckboxes = document.querySelectorAll('.eleve-checkbox:checked');
            if (selectedCheckboxes.length === 0) {
                alert('Veuillez sélectionner au moins un élève à réinscrire.');
                return;
            }
            
            if (confirm(`Êtes-vous sûr de vouloir réinscrire ${selectedCheckboxes.length} élève(s) pour l'année scolaire ?`)) {
                document.getElementById('formReinscription').submit();
            }
        });
    }
    
    // Boutons de réinscription individuelle
    document.querySelectorAll('.btn-reinscrire-individuel').forEach(btn => {
        btn.addEventListener('click', function() {
            const eleveId = this.dataset.eleveId;
            const eleveNom = this.dataset.eleveNom;
            
            if (confirm(`Êtes-vous sûr de vouloir réinscrire l'élève "${eleveNom}" ?`)) {
                // Créer un formulaire temporaire pour la réinscription individuelle
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="reinscrire_eleves">
                    <input type="hidden" name="eleves_selectionnes[]" value="${eleveId}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        });
    });
    
    // Mettre à jour les statistiques
    function updateStats() {
        const elevesEnOrdre = document.querySelectorAll('.badge.bg-success').length;
        const elevesActifs = document.querySelectorAll('.badge.bg-success').length;
        
        document.getElementById('elevesEnOrdre').textContent = elevesEnOrdre;
        document.getElementById('elevesActifs').textContent = elevesActifs;
    }
    
    // Initialiser les statistiques
    updateStats();
    
    // Vérifier si on doit ouvrir automatiquement le modal de vérification
    const urlParams = new URLSearchParams(window.location.search);
    const eleveId = urlParams.get('eleve_id');
    const action = urlParams.get('action');
    
    if (eleveId && action === 'verifier') {
        // Pré-remplir le formulaire et ouvrir le modal
        document.getElementById('eleve_verification').value = eleveId;
        document.getElementById('annee_verification').value = '<?php echo $current_year['id'] - 1; ?>';
        
        // Déclencher la vérification automatiquement
        setTimeout(() => {
            document.getElementById('btnVerifierFrais').click();
            new bootstrap.Modal(document.getElementById('verificationModal')).show();
        }, 500);
    }
});
</script>

<?php
/**
 * Fonction pour déterminer la nouvelle classe d'un élève
 */
function getNouvelleClasse($ancienne_classe_id, $niveau) {
    global $database, $current_year;
    
    try {
        // Récupérer les informations de l'ancienne classe
        $stmt = $database->query(
            "SELECT nom, niveau FROM classes WHERE id = ?",
            [$ancienne_classe_id]
        );
        $ancienne_classe = $stmt->fetch();
        
        if (!$ancienne_classe) return null;
        
        // Déterminer la nouvelle classe selon le niveau
        $nouveau_nom = '';
        switch ($ancienne_classe['niveau']) {
            case 'maternelle':
                if (strpos($ancienne_classe['nom'], '1ère') !== false) {
                    $nouveau_nom = '2ème Maternelle';
                } elseif (strpos($ancienne_classe['nom'], '2ème') !== false) {
                    $nouveau_nom = '3ème Maternelle';
                } elseif (strpos($ancienne_classe['nom'], '3ème') !== false) {
                    $nouveau_nom = '1ère Primaire A'; // Passage au primaire
                }
                break;
                
            case 'primaire':
                if (strpos($ancienne_classe['nom'], '1ère') !== false) {
                    $nouveau_nom = '2ème Primaire A';
                } elseif (strpos($ancienne_classe['nom'], '2ème') !== false) {
                    $nouveau_nom = '3ème Primaire A';
                } elseif (strpos($ancienne_classe['nom'], '3ème') !== false) {
                    $nouveau_nom = '4ème Primaire A';
                } elseif (strpos($ancienne_classe['nom'], '4ème') !== false) {
                    $nouveau_nom = '5ème Primaire A';
                } elseif (strpos($ancienne_classe['nom'], '5ème') !== false) {
                    $nouveau_nom = '6ème Primaire A';
                } elseif (strpos($ancienne_classe['nom'], '6ème') !== false) {
                    $nouveau_nom = '1ère Secondaire A'; // Passage au secondaire
                }
                break;
                
            case 'secondaire':
                if (strpos($ancienne_classe['nom'], '1ère') !== false) {
                    $nouveau_nom = '2ème Secondaire A';
                } elseif (strpos($ancienne_classe['nom'], '2ème') !== false) {
                    $nouveau_nom = '3ème Secondaire A';
                } elseif (strpos($ancienne_classe['nom'], '3ème') !== false) {
                    $nouveau_nom = '4ème Secondaire A';
                } elseif (strpos($ancienne_classe['nom'], '4ème') !== false) {
                    $nouveau_nom = '5ème Secondaire A';
                } elseif (strpos($ancienne_classe['nom'], '5ème') !== false) {
                    $nouveau_nom = '6ème Secondaire A';
                } elseif (strpos($ancienne_classe['nom'], '6ème') !== false) {
                    return null; // Fin du secondaire
                }
                break;
        }
        
        if (empty($nouveau_nom)) return null;
        
        // Rechercher la classe correspondante dans la nouvelle année
        $stmt = $database->query(
            "SELECT id, nom, niveau, frais_inscription 
             FROM classes 
             WHERE annee_scolaire_id = ? AND nom LIKE ? 
             ORDER BY id LIMIT 1",
            [$current_year['id'], $nouveau_nom . '%']
        );
        
        return $stmt->fetch();
        
    } catch (Exception $e) {
        return null;
    }
}
?>
