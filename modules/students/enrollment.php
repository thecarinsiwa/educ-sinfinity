<?php
/**
 * Module Gestion des Inscriptions - Nouvelle Année Scolaire
 * Application de gestion scolaire - République Démocratique du Congo
 */

require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../includes/functions.php';
require_once '../../includes/permissions-pages.php';

// Vérifier l'authentification et les permissions
requireLogin();
requirePagePermissionFromDB('students', 'enrollment', 'create', '../../dashboard.php');

$page_title = 'Gestion des Inscriptions - Nouvelle Année';

// Obtenir l'année scolaire actuelle
$current_year = getCurrentAcademicYear();

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
                i.date_inscription, i.frais_inscription_paye
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
    'nouveaux_inscrits' => 0,
    'classes_disponibles' => count($classes_annee_actuelle)
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

include '../../includes/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">
        <i class="fas fa-user-plus me-2"></i>
        Gestion des Inscriptions - Nouvelle Année
    </h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <div class="btn-group me-2">
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#nouvelEleveModal">
                <i class="fas fa-plus me-1"></i>
                Nouvel élève
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
                <li><a class="dropdown-item" href="re-enrollment.php"><i class="fas fa-user-check me-2"></i>Vérification & Carte</a></li>
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
                        <h4><?php echo $stats['deja_inscrits']; ?></h4>
                        <p class="mb-0">Déjà inscrits</p>
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
                        <h4><?php echo $stats['classes_disponibles']; ?></h4>
                        <p class="mb-0">Classes disponibles</p>
                    </div>
                    <div class="align-self-center">
                        <i class="fas fa-chalkboard fa-2x"></i>
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
                <label for="classe" class="form-label">Classe</label>
                <select class="form-select" id="classe" name="classe">
                    <option value="">Toutes les classes</option>
                    <?php foreach ($classes_annee_actuelle as $classe): ?>
                        <option value="<?php echo $classe['id']; ?>" <?php echo ($_GET['classe'] ?? '') == $classe['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($classe['nom']); ?>
                        </option>
                    <?php endforeach; ?>
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
        Élèves de l'année précédente à réinscrire (<?php echo count($eleves_annee_precedente); ?>)
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
                                <th>Nouvelle classe</th>
                                <th>Contact</th>
                                <th>Date inscription</th>
                                <th>Frais payés</th>
                                <th>Statut</th>
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
                                
                                $nouvelle_classe = getNouvelleClasse($eleve['classe_id'], $eleve['niveau']);
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
                                        <?php if ($nouvelle_classe): ?>
                                            <span class="badge bg-primary">
                                                <?php echo htmlspecialchars($nouvelle_classe['nom']); ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <small class="text-muted">
                                            <i class="fas fa-calendar fa-xs"></i>
                                            <?php echo $eleve['date_naissance'] ? formatDate($eleve['date_naissance']) : '-'; ?>
                                        </small>
                                    </td>
                                    <td>
                                        <?php echo $eleve['date_inscription'] ? formatDate($eleve['date_inscription']) : '-'; ?>
                                    </td>
                                    <td>
                                        <?php if ($eleve['frais_inscription_paye']): ?>
                                            <strong><?php echo formatMoney($eleve['frais_inscription_paye']); ?></strong>
                                        <?php else: ?>
                                            <span class="text-muted">Non payé</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($deja_inscrit): ?>
                                            <span class="badge bg-success">Déjà inscrit</span>
                                        <?php else: ?>
                                            <span class="badge bg-warning">À réinscrire</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <a href="../students/view.php?id=<?php echo $eleve['id']; ?>" 
                                               class="btn btn-outline-info" 
                                               title="Voir détails">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <a href="re-enrollment.php?eleve_id=<?php echo $eleve['id']; ?>&action=verifier" 
                                               class="btn btn-outline-warning" 
                                               title="Vérifier frais et carte">
                                                <i class="fas fa-search-dollar"></i>
                                            </a>
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

<!-- Modal pour nouvel élève -->
<div class="modal fade" id="nouvelEleveModal" tabindex="-1" aria-labelledby="nouvelEleveModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="nouvelEleveModalLabel">
                    <i class="fas fa-user-plus me-2"></i>
                    Inscrire un nouvel élève
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" id="formNouvelEleve">
                <div class="modal-body">
                    <input type="hidden" name="action" value="inscrire_nouveau">
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="nom" class="form-label">Nom *</label>
                                <input type="text" class="form-control" id="nom" name="nom" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="prenom" class="form-label">Prénom *</label>
                                <input type="text" class="form-control" id="prenom" name="prenom" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="sexe" class="form-label">Sexe *</label>
                                <select class="form-select" id="sexe" name="sexe" required>
                                    <option value="">Sélectionner...</option>
                                    <option value="M">Masculin</option>
                                    <option value="F">Féminin</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="date_naissance" class="form-label">Date de naissance *</label>
                                <input type="date" class="form-control" id="date_naissance" name="date_naissance" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="classe_id" class="form-label">Classe *</label>
                                <select class="form-select" id="classe_id" name="classe_id" required>
                                    <option value="">Sélectionner une classe...</option>
                                    <?php foreach ($classes_annee_actuelle as $classe): ?>
                                        <option value="<?php echo $classe['id']; ?>" data-frais="<?php echo $classe['frais_inscription'] ?? 0; ?>">
                                            <?php echo htmlspecialchars($classe['nom']); ?> (<?php echo ucfirst($classe['niveau']); ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="frais_inscription" class="form-label">Frais d'inscription (FC)</label>
                                <input type="number" class="form-control" id="frais_inscription" name="frais_inscription" min="0" step="100">
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-1"></i>
                        Inscrire l'élève
                    </button>
                </div>
            </form>
        </div>
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
    
    // Gestion des frais d'inscription automatiques
    const classeSelect = document.getElementById('classe_id');
    const fraisInput = document.getElementById('frais_inscription');
    
    if (classeSelect && fraisInput) {
        classeSelect.addEventListener('change', function() {
            const selectedOption = this.options[this.selectedIndex];
            const frais = selectedOption.dataset.frais;
            if (frais && frais > 0) {
                fraisInput.value = frais;
            }
        });
    }
    
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
