<?php
/**
 * Module Académique - Détection des conflits d'emploi du temps
 * Application de gestion scolaire - République Démocratique du Congo
 */

require_once '../../../config/config.php';
require_once '../../../config/database.php';
require_once '../../../includes/functions.php';

// Vérifier l'authentification et les permissions
requireLogin();
if (!checkPermission('admin') && !checkPermission('academic')) {
    showMessage('error', 'Accès refusé à cette fonctionnalité.');
    redirectTo('../../../index.php');
}

$page_title = 'Détection des conflits';

// Récupérer l'année scolaire active
$current_year = $database->query("SELECT * FROM annees_scolaires WHERE status = 'active' LIMIT 1")->fetch();
if (!$current_year) {
    showMessage('error', 'Aucune année scolaire active trouvée.');
    redirectTo('../../../index.php');
}

// Paramètres de recherche
$search_classe = $_GET['classe_id'] ?? '';
$search_enseignant = $_GET['enseignant_id'] ?? '';
$search_jour = $_GET['jour_semaine'] ?? '';
$search_heure_debut = $_GET['heure_debut'] ?? '';
$search_heure_fin = $_GET['heure_fin'] ?? '';
$search_salle = $_GET['salle'] ?? '';

// Fonction pour détecter les conflits
function detectConflicts($database, $current_year_id, $filters = []) {
    $conflicts = [];
    
    // 1. Conflits de classe (même classe, même créneau)
    $query_classe = "
        SELECT et1.id as cours1_id, et2.id as cours2_id,
               c.nom as classe_nom, c.niveau,
               et1.jour_semaine, et1.heure_debut, et1.heure_fin,
               m1.nom as matiere1, m2.nom as matiere2,
               CONCAT(p1.nom, ' ', p1.prenom) as enseignant1,
               CONCAT(p2.nom, ' ', p2.prenom) as enseignant2,
               'classe' as type_conflit
        FROM emploi_temps et1
        JOIN emploi_temps et2 ON et1.classe_id = et2.classe_id 
            AND et1.jour_semaine = et2.jour_semaine
            AND et1.id < et2.id
            AND ((et1.heure_debut < et2.heure_fin AND et1.heure_fin > et2.heure_debut))
        JOIN classes c ON et1.classe_id = c.id
        JOIN matieres m1 ON et1.matiere_id = m1.id
        JOIN matieres m2 ON et2.matiere_id = m2.id
        JOIN personnel p1 ON et1.enseignant_id = p1.id
        JOIN personnel p2 ON et2.enseignant_id = p2.id
        WHERE et1.annee_scolaire_id = ?
    ";
    
    $params = [$current_year_id];
    
    if (!empty($filters['classe_id'])) {
        $query_classe .= " AND et1.classe_id = ?";
        $params[] = $filters['classe_id'];
    }
    
    $conflicts_classe = $database->query($query_classe, $params)->fetchAll();
    $conflicts = array_merge($conflicts, $conflicts_classe);
    
    // 2. Conflits d'enseignant (même enseignant, même créneau)
    $query_enseignant = "
        SELECT et1.id as cours1_id, et2.id as cours2_id,
               CONCAT(p.nom, ' ', p.prenom) as enseignant_nom,
               et1.jour_semaine, et1.heure_debut, et1.heure_fin,
               c1.nom as classe1, c2.nom as classe2,
               m1.nom as matiere1, m2.nom as matiere2,
               'enseignant' as type_conflit
        FROM emploi_temps et1
        JOIN emploi_temps et2 ON et1.enseignant_id = et2.enseignant_id 
            AND et1.jour_semaine = et2.jour_semaine
            AND et1.id < et2.id
            AND ((et1.heure_debut < et2.heure_fin AND et1.heure_fin > et2.heure_debut))
        JOIN personnel p ON et1.enseignant_id = p.id
        JOIN classes c1 ON et1.classe_id = c1.id
        JOIN classes c2 ON et2.classe_id = c2.id
        JOIN matieres m1 ON et1.matiere_id = m1.id
        JOIN matieres m2 ON et2.matiere_id = m2.id
        WHERE et1.annee_scolaire_id = ?
    ";
    
    $params = [$current_year_id];
    
    if (!empty($filters['enseignant_id'])) {
        $query_enseignant .= " AND et1.enseignant_id = ?";
        $params[] = $filters['enseignant_id'];
    }
    
    $conflicts_enseignant = $database->query($query_enseignant, $params)->fetchAll();
    $conflicts = array_merge($conflicts, $conflicts_enseignant);
    
    // 3. Conflits de salle (même salle, même créneau)
    $query_salle = "
        SELECT et1.id as cours1_id, et2.id as cours2_id,
               et1.salle,
               et1.jour_semaine, et1.heure_debut, et1.heure_fin,
               c1.nom as classe1, c2.nom as classe2,
               m1.nom as matiere1, m2.nom as matiere2,
               CONCAT(p1.nom, ' ', p1.prenom) as enseignant1,
               CONCAT(p2.nom, ' ', p2.prenom) as enseignant2,
               'salle' as type_conflit
        FROM emploi_temps et1
        JOIN emploi_temps et2 ON et1.salle = et2.salle 
            AND et1.jour_semaine = et2.jour_semaine
            AND et1.id < et2.id
            AND et1.salle IS NOT NULL AND et1.salle != ''
            AND ((et1.heure_debut < et2.heure_fin AND et1.heure_fin > et2.heure_debut))
        JOIN classes c1 ON et1.classe_id = c1.id
        JOIN classes c2 ON et2.classe_id = c2.id
        JOIN matieres m1 ON et1.matiere_id = m1.id
        JOIN matieres m2 ON et2.matiere_id = m2.id
        JOIN personnel p1 ON et1.enseignant_id = p1.id
        JOIN personnel p2 ON et2.enseignant_id = p2.id
        WHERE et1.annee_scolaire_id = ?
    ";
    
    $params = [$current_year_id];
    
    if (!empty($filters['salle'])) {
        $query_salle .= " AND et1.salle = ?";
        $params[] = $filters['salle'];
    }
    
    $conflicts_salle = $database->query($query_salle, $params)->fetchAll();
    $conflicts = array_merge($conflicts, $conflicts_salle);
    
    return $conflicts;
}

// Détecter les conflits
$filters = array_filter([
    'classe_id' => $search_classe,
    'enseignant_id' => $search_enseignant,
    'salle' => $search_salle
]);

$conflicts = detectConflicts($database, $current_year['id'], $filters);

// Vérification spécifique si des paramètres sont passés (depuis add.php)
$specific_check = false;
if ($search_classe && $search_enseignant && $search_jour && $search_heure_debut && $search_heure_fin) {
    $specific_check = true;
    
    // Vérifier le conflit spécifique
    $specific_conflicts = $database->query(
        "SELECT et.*, c.nom as classe_nom, m.nom as matiere_nom, CONCAT(p.nom, ' ', p.prenom) as enseignant_nom
         FROM emploi_temps et
         JOIN classes c ON et.classe_id = c.id
         JOIN matieres m ON et.matiere_id = m.id
         JOIN personnel p ON et.enseignant_id = p.id
         WHERE ((et.classe_id = ? OR et.enseignant_id = ?) OR (et.salle = ? AND et.salle IS NOT NULL AND et.salle != ''))
         AND et.jour_semaine = ? 
         AND ((et.heure_debut < ? AND et.heure_fin > ?) OR (et.heure_debut < ? AND et.heure_fin > ?))
         AND et.annee_scolaire_id = ?",
        [$search_classe, $search_enseignant, $search_salle, $search_jour, $search_heure_fin, $search_heure_debut, $search_heure_debut, $search_heure_fin, $current_year['id']]
    )->fetchAll();
}

// Récupérer les données pour les filtres
$classes = $database->query(
    "SELECT * FROM classes WHERE annee_scolaire_id = ? ORDER BY niveau, nom",
    [$current_year['id']]
)->fetchAll();

$enseignants = $database->query(
    "SELECT * FROM personnel WHERE fonction = 'enseignant' AND status = 'actif' ORDER BY nom, prenom"
)->fetchAll();

// Statistiques
$stats = [
    'total_conflicts' => count($conflicts),
    'classe_conflicts' => count(array_filter($conflicts, function($c) { return $c['type_conflit'] === 'classe'; })),
    'enseignant_conflicts' => count(array_filter($conflicts, function($c) { return $c['type_conflit'] === 'enseignant'; })),
    'salle_conflicts' => count(array_filter($conflicts, function($c) { return $c['type_conflit'] === 'salle'; }))
];

include '../../../includes/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">
        <i class="fas fa-exclamation-triangle me-2"></i>
        Détection des conflits
    </h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <div class="btn-group me-2">
            <a href="generate.php" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-1"></i>
                Retour
            </a>
        </div>
        <div class="btn-group">
            <button type="button" class="btn btn-outline-primary" onclick="window.location.reload()">
                <i class="fas fa-sync me-1"></i>
                Actualiser
            </button>
        </div>
    </div>
</div>

<!-- Statistiques -->
<div class="row mb-4">
    <div class="col-md-3">
        <div class="card text-center">
            <div class="card-body">
                <i class="fas fa-exclamation-triangle fa-2x text-danger mb-2"></i>
                <h3 class="mb-0"><?php echo $stats['total_conflicts']; ?></h3>
                <small class="text-muted">Conflits totaux</small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-center">
            <div class="card-body">
                <i class="fas fa-users fa-2x text-warning mb-2"></i>
                <h3 class="mb-0"><?php echo $stats['classe_conflicts']; ?></h3>
                <small class="text-muted">Conflits de classe</small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-center">
            <div class="card-body">
                <i class="fas fa-chalkboard-teacher fa-2x text-info mb-2"></i>
                <h3 class="mb-0"><?php echo $stats['enseignant_conflicts']; ?></h3>
                <small class="text-muted">Conflits d'enseignant</small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-center">
            <div class="card-body">
                <i class="fas fa-door-open fa-2x text-secondary mb-2"></i>
                <h3 class="mb-0"><?php echo $stats['salle_conflicts']; ?></h3>
                <small class="text-muted">Conflits de salle</small>
            </div>
        </div>
    </div>
</div>

<!-- Filtres -->
<div class="card mb-4">
    <div class="card-header">
        <h5 class="mb-0">
            <i class="fas fa-filter me-2"></i>
            Filtres de recherche
        </h5>
    </div>
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-md-3">
                <label for="classe_id" class="form-label">Classe</label>
                <select class="form-select" id="classe_id" name="classe_id">
                    <option value="">Toutes les classes</option>
                    <?php foreach ($classes as $classe): ?>
                        <option value="<?php echo $classe['id']; ?>" <?php echo $search_classe == $classe['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($classe['niveau'] . ' - ' . $classe['nom']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label for="enseignant_id" class="form-label">Enseignant</label>
                <select class="form-select" id="enseignant_id" name="enseignant_id">
                    <option value="">Tous les enseignants</option>
                    <?php foreach ($enseignants as $enseignant): ?>
                        <option value="<?php echo $enseignant['id']; ?>" <?php echo $search_enseignant == $enseignant['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($enseignant['nom'] . ' ' . $enseignant['prenom']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label for="jour_semaine" class="form-label">Jour</label>
                <select class="form-select" id="jour_semaine" name="jour_semaine">
                    <option value="">Tous les jours</option>
                    <?php 
                    $jours = ['Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi'];
                    foreach ($jours as $jour): 
                    ?>
                        <option value="<?php echo $jour; ?>" <?php echo $search_jour == $jour ? 'selected' : ''; ?>>
                            <?php echo $jour; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label for="salle" class="form-label">Salle</label>
                <input type="text" class="form-control" id="salle" name="salle" 
                       value="<?php echo htmlspecialchars($search_salle); ?>" placeholder="Ex: Salle 101">
            </div>
            <div class="col-md-2">
                <label class="form-label">&nbsp;</label>
                <div class="d-grid">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search me-1"></i>
                        Rechercher
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<?php if ($specific_check): ?>
<!-- Vérification spécifique -->
<div class="card mb-4">
    <div class="card-header">
        <h5 class="mb-0">
            <i class="fas fa-search me-2"></i>
            Vérification spécifique
        </h5>
    </div>
    <div class="card-body">
        <div class="alert alert-info">
            <h6><i class="fas fa-info-circle me-2"></i>Créneau vérifié</h6>
            <p><strong>Jour :</strong> <?php echo htmlspecialchars($search_jour); ?></p>
            <p><strong>Heure :</strong> <?php echo htmlspecialchars($search_heure_debut . ' - ' . $search_heure_fin); ?></p>
            <?php if ($search_salle): ?>
                <p><strong>Salle :</strong> <?php echo htmlspecialchars($search_salle); ?></p>
            <?php endif; ?>
        </div>
        
        <?php if (!empty($specific_conflicts)): ?>
            <div class="alert alert-danger">
                <h6><i class="fas fa-exclamation-triangle me-2"></i>Conflits détectés</h6>
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead>
                            <tr>
                                <th>Classe</th>
                                <th>Matière</th>
                                <th>Enseignant</th>
                                <th>Horaire</th>
                                <th>Salle</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($specific_conflicts as $conflict): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($conflict['classe_nom']); ?></td>
                                    <td><?php echo htmlspecialchars($conflict['matiere_nom']); ?></td>
                                    <td><?php echo htmlspecialchars($conflict['enseignant_nom']); ?></td>
                                    <td><?php echo htmlspecialchars($conflict['heure_debut'] . ' - ' . $conflict['heure_fin']); ?></td>
                                    <td><?php echo htmlspecialchars($conflict['salle'] ?: 'Non définie'); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php else: ?>
            <div class="alert alert-success">
                <h6><i class="fas fa-check-circle me-2"></i>Aucun conflit détecté</h6>
                <p class="mb-0">Le créneau est libre et peut être utilisé.</p>
            </div>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<!-- Liste des conflits -->
<div class="card">
    <div class="card-header">
        <h5 class="mb-0">
            <i class="fas fa-list me-2"></i>
            Liste des conflits détectés
        </h5>
    </div>
    <div class="card-body">
        <?php if (!empty($conflicts)): ?>
            <div class="table-responsive">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Type</th>
                            <th>Jour</th>
                            <th>Horaire</th>
                            <th>Détails du conflit</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($conflicts as $conflict): ?>
                            <tr>
                                <td>
                                    <span class="badge bg-<?php 
                                        echo $conflict['type_conflit'] === 'classe' ? 'danger' : 
                                            ($conflict['type_conflit'] === 'enseignant' ? 'warning' : 'info'); 
                                    ?>">
                                        <?php echo ucfirst($conflict['type_conflit']); ?>
                                    </span>
                                </td>
                                <td><?php echo htmlspecialchars($conflict['jour_semaine']); ?></td>
                                <td><?php echo htmlspecialchars($conflict['heure_debut'] . ' - ' . $conflict['heure_fin']); ?></td>
                                <td>
                                    <?php if ($conflict['type_conflit'] === 'classe'): ?>
                                        <strong>Classe :</strong> <?php echo htmlspecialchars($conflict['classe_nom']); ?><br>
                                        <strong>Matières :</strong> <?php echo htmlspecialchars($conflict['matiere1'] . ' / ' . $conflict['matiere2']); ?><br>
                                        <strong>Enseignants :</strong> <?php echo htmlspecialchars($conflict['enseignant1'] . ' / ' . $conflict['enseignant2']); ?>
                                    <?php elseif ($conflict['type_conflit'] === 'enseignant'): ?>
                                        <strong>Enseignant :</strong> <?php echo htmlspecialchars($conflict['enseignant_nom']); ?><br>
                                        <strong>Classes :</strong> <?php echo htmlspecialchars($conflict['classe1'] . ' / ' . $conflict['classe2']); ?><br>
                                        <strong>Matières :</strong> <?php echo htmlspecialchars($conflict['matiere1'] . ' / ' . $conflict['matiere2']); ?>
                                    <?php else: ?>
                                        <strong>Salle :</strong> <?php echo htmlspecialchars($conflict['salle']); ?><br>
                                        <strong>Classes :</strong> <?php echo htmlspecialchars($conflict['classe1'] . ' / ' . $conflict['classe2']); ?><br>
                                        <strong>Matières :</strong> <?php echo htmlspecialchars($conflict['matiere1'] . ' / ' . $conflict['matiere2']); ?>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <button type="button" class="btn btn-outline-primary" 
                                                onclick="resolveConflict(<?php echo $conflict['cours1_id']; ?>, <?php echo $conflict['cours2_id']; ?>)">
                                            <i class="fas fa-tools"></i>
                                        </button>
                                        <button type="button" class="btn btn-outline-danger" 
                                                onclick="deleteConflictCourse(<?php echo $conflict['cours2_id']; ?>)">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="text-center py-4">
                <i class="fas fa-check-circle fa-3x text-success mb-3"></i>
                <h5>Aucun conflit détecté</h5>
                <p class="text-muted">L'emploi du temps ne présente aucun conflit pour les critères sélectionnés.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
function resolveConflict(cours1Id, cours2Id) {
    if (confirm('Voulez-vous résoudre ce conflit en modifiant l\'un des cours ?')) {
        // Rediriger vers une page de résolution de conflit
        window.location.href = 'resolve-conflict.php?cours1=' + cours1Id + '&cours2=' + cours2Id;
    }
}

function deleteConflictCourse(coursId) {
    if (confirm('Êtes-vous sûr de vouloir supprimer ce cours pour résoudre le conflit ?')) {
        // Faire un appel AJAX pour supprimer le cours
        fetch('delete-course.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                cours_id: coursId
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Cours supprimé avec succès.');
                window.location.reload();
            } else {
                alert('Erreur lors de la suppression : ' + data.message);
            }
        })
        .catch(error => {
            console.error('Erreur:', error);
            alert('Erreur lors de la suppression du cours.');
        });
    }
}
</script>

<?php include '../../../includes/footer.php'; ?>
