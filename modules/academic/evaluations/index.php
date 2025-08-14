<?php
/**
 * Module Académique - Liste des évaluations
 * Application de gestion scolaire - République Démocratique du Congo
 */

require_once '../../../config/config.php';
require_once '../../../config/database.php';
require_once '../../../includes/functions.php';

// Vérifier l'authentification et les permissions
requireLogin();
if (!checkPermission('academic') && !checkPermission('evaluations_view')) {
    showMessage('error', 'Accès refusé à cette fonctionnalité.');
    redirectTo('../../index.php');
}

$page_title = 'Gestion des évaluations';

// Récupérer l'année scolaire active
$current_year = getCurrentAcademicYear();

if (!$current_year) {
    showMessage('error', 'Aucune année scolaire active.');
    redirectTo('../../index.php');
}

// Paramètres de filtrage
$matiere_filter = (int)($_GET['matiere_id'] ?? 0);
$classe_filter = (int)($_GET['classe_id'] ?? 0);
$type_filter = sanitizeInput($_GET['type'] ?? '');
$periode_filter = sanitizeInput($_GET['periode'] ?? '');
$status_filter = sanitizeInput($_GET['status'] ?? '');

// Récupérer les matières pour le filtre
$matieres = $database->query(
    "SELECT m.id, m.nom, m.code, m.coefficient
     FROM matieres m
     JOIN evaluations e ON m.id = e.matiere_id
     WHERE e.annee_scolaire_id = ?
     GROUP BY m.id, m.nom, m.code, m.coefficient
     ORDER BY m.nom",
    [$current_year['id']]
)->fetchAll();

// Récupérer les classes pour le filtre
$classes = $database->query(
    "SELECT c.id, c.nom, c.niveau, c.section
     FROM classes c
     JOIN evaluations e ON c.id = e.classe_id
     WHERE e.annee_scolaire_id = ?
     GROUP BY c.id, c.nom, c.niveau, c.section
     ORDER BY c.niveau, c.nom",
    [$current_year['id']]
)->fetchAll();

// Récupérer les types d'évaluation
$types_evaluation = $database->query(
    "SELECT DISTINCT type_evaluation FROM evaluations 
     WHERE annee_scolaire_id = ? AND type_evaluation IS NOT NULL
     ORDER BY type_evaluation",
    [$current_year['id']]
)->fetchAll();

// Récupérer les périodes
$periodes = $database->query(
    "SELECT DISTINCT periode FROM evaluations 
     WHERE annee_scolaire_id = ? AND periode IS NOT NULL
     ORDER BY 
        CASE periode 
            WHEN '1er trimestre' THEN 1 
            WHEN '2ème trimestre' THEN 2 
            WHEN '3ème trimestre' THEN 3 
        END",
    [$current_year['id']]
)->fetchAll();

// Construire la requête avec filtres
$where_conditions = ["e.annee_scolaire_id = ?"];
$params = [$current_year['id']];

if ($matiere_filter) {
    $where_conditions[] = "e.matiere_id = ?";
    $params[] = $matiere_filter;
}

if ($classe_filter) {
    $where_conditions[] = "e.classe_id = ?";
    $params[] = $classe_filter;
}

if ($type_filter) {
    $where_conditions[] = "e.type_evaluation = ?";
    $params[] = $type_filter;
}

if ($periode_filter) {
    $where_conditions[] = "e.periode = ?";
    $params[] = $periode_filter;
}

$where_clause = implode(' AND ', $where_conditions);

// Récupérer les évaluations
$evaluations = $database->query(
    "SELECT e.*, 
            m.nom as matiere_nom, m.code as matiere_code, m.coefficient as matiere_coefficient,
            c.nom as classe_nom, c.niveau, c.section,
            CONCAT(p.nom, ' ', p.prenom) as enseignant_nom,
            COUNT(n.id) as nb_notes,
            COUNT(DISTINCT n.eleve_id) as nb_eleves_notes,
            AVG(n.note) as moyenne_classe
     FROM evaluations e
     JOIN matieres m ON e.matiere_id = m.id
     JOIN classes c ON e.classe_id = c.id
     LEFT JOIN personnel p ON e.enseignant_id = p.id
     LEFT JOIN notes n ON e.id = n.evaluation_id
     WHERE $where_clause
     GROUP BY e.id, e.nom, e.type_evaluation, e.date_evaluation, e.periode, e.note_max, e.description, e.matiere_id, e.classe_id, e.enseignant_id, e.annee_scolaire_id, e.created_at, e.updated_at, m.nom, m.code, m.coefficient, c.nom, c.niveau, c.section, p.nom, p.prenom
     ORDER BY e.date_evaluation DESC, e.id DESC",
    $params
)->fetchAll();

// Récupérer les statistiques générales
$stats = [];

// Total des évaluations
$stmt = $database->query(
    "SELECT COUNT(*) as total FROM evaluations WHERE annee_scolaire_id = ?",
    [$current_year['id']]
);
$stats['total_evaluations'] = $stmt->fetch()['total'];

// Total des notes
$stmt = $database->query(
    "SELECT COUNT(*) as total FROM notes n 
     JOIN evaluations e ON n.evaluation_id = e.id 
     WHERE e.annee_scolaire_id = ?",
    [$current_year['id']]
);
$stats['total_notes'] = $stmt->fetch()['total'];

// Moyenne générale
$stmt = $database->query(
    "SELECT AVG(n.note) as moyenne FROM notes n 
     JOIN evaluations e ON n.evaluation_id = e.id 
     WHERE e.annee_scolaire_id = ? AND n.note IS NOT NULL",
    [$current_year['id']]
);
$stats['moyenne_generale'] = round($stmt->fetch()['moyenne'] ?? 0, 2);

// Répartition par type
$repartition_types = $database->query(
    "SELECT type_evaluation, COUNT(*) as nombre 
     FROM evaluations 
     WHERE annee_scolaire_id = ? 
     GROUP BY type_evaluation 
     ORDER BY nombre DESC",
    [$current_year['id']]
)->fetchAll();

include '../../../includes/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">
        <i class="fas fa-clipboard-list me-2"></i>
        Gestion des évaluations
    </h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <div class="btn-group me-2">
            <a href="add.php" class="btn btn-success">
                <i class="fas fa-plus me-1"></i>
                Nouvelle évaluation
            </a>
        </div>
        <div class="btn-group">
            <a href="../index.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left me-1"></i>
                Retour au module académique
            </a>
        </div>
    </div>
</div>

<!-- Statistiques générales -->
<div class="row mb-4">
    <div class="col-md-3">
        <div class="card text-center">
            <div class="card-body">
                <h3 class="text-primary"><?php echo $stats['total_evaluations']; ?></h3>
                <p class="card-text">Évaluations</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-center">
            <div class="card-body">
                <h3 class="text-success"><?php echo $stats['total_notes']; ?></h3>
                <p class="card-text">Notes saisies</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-center">
            <div class="card-body">
                <h3 class="text-info"><?php echo $stats['moyenne_generale']; ?></h3>
                <p class="card-text">Moyenne générale</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-center">
            <div class="card-body">
                <h3 class="text-warning"><?php echo count($evaluations); ?></h3>
                <p class="card-text">Résultats filtrés</p>
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
        <form method="GET" action="" class="row g-3">
            <div class="col-md-2">
                <label for="matiere_id" class="form-label">Matière</label>
                <select class="form-select" id="matiere_id" name="matiere_id">
                    <option value="">Toutes les matières</option>
                    <?php foreach ($matieres as $m): ?>
                        <option value="<?php echo $m['id']; ?>" <?php echo $matiere_filter == $m['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($m['nom']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="col-md-2">
                <label for="classe_id" class="form-label">Classe</label>
                <select class="form-select" id="classe_id" name="classe_id">
                    <option value="">Toutes les classes</option>
                    <?php foreach ($classes as $c): ?>
                        <option value="<?php echo $c['id']; ?>" <?php echo $classe_filter == $c['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($c['nom']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="col-md-2">
                <label for="type" class="form-label">Type</label>
                <select class="form-select" id="type" name="type">
                    <option value="">Tous les types</option>
                    <?php foreach ($types_evaluation as $t): ?>
                        <option value="<?php echo htmlspecialchars($t['type_evaluation']); ?>" <?php echo $type_filter === $t['type_evaluation'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($t['type_evaluation']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="col-md-2">
                <label for="periode" class="form-label">Période</label>
                <select class="form-select" id="periode" name="periode">
                    <option value="">Toutes les périodes</option>
                    <?php foreach ($periodes as $p): ?>
                        <option value="<?php echo htmlspecialchars($p['periode']); ?>" <?php echo $periode_filter === $p['periode'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($p['periode']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="col-md-2">
                <label for="status" class="form-label">Statut</label>
                <select class="form-select" id="status" name="status">
                    <option value="">Tous</option>
                    <option value="complete" <?php echo $status_filter === 'complete' ? 'selected' : ''; ?>>Complète</option>
                    <option value="incomplete" <?php echo $status_filter === 'incomplete' ? 'selected' : ''; ?>>Incomplète</option>
                </select>
            </div>
            
            <div class="col-md-2 d-flex align-items-end">
                <button type="submit" class="btn btn-primary me-2">
                    <i class="fas fa-search me-1"></i>
                    Filtrer
                </button>
                <a href="?" class="btn btn-outline-secondary">
                    <i class="fas fa-times me-1"></i>
                    Réinitialiser
                </a>
            </div>
        </form>
    </div>
</div>

<!-- Liste des évaluations -->
<div class="card">
    <div class="card-header">
        <h5 class="mb-0">
            <i class="fas fa-list me-2"></i>
            Liste des évaluations
            <?php if ($matiere_filter || $classe_filter || $type_filter || $periode_filter): ?>
                <span class="badge bg-info ms-2">Filtré</span>
            <?php endif; ?>
        </h5>
    </div>
    <div class="card-body">
        <?php if (empty($evaluations)): ?>
            <div class="alert alert-info">
                <h5><i class="fas fa-info-circle me-2"></i>Aucune évaluation trouvée</h5>
                <p class="mb-0">
                    <?php if ($matiere_filter || $classe_filter || $type_filter || $periode_filter): ?>
                        Aucune évaluation ne correspond aux critères de recherche sélectionnés.
                        <a href="?" class="alert-link">Réinitialiser les filtres</a>
                    <?php else: ?>
                        Aucune évaluation n'a été créée pour cette année scolaire.
                        <a href="add.php" class="alert-link">Créer la première évaluation</a>
                    <?php endif; ?>
                </p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Nom</th>
                            <th>Matière</th>
                            <th>Classe</th>
                            <th>Type</th>
                            <th>Date</th>
                            <th>Période</th>
                            <th>Notes</th>
                            <th>Moyenne</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($evaluations as $index => $evaluation): ?>
                            <?php 
                            // Déterminer le statut de l'évaluation
                            $total_eleves = $database->query(
                                "SELECT COUNT(*) as total FROM inscriptions 
                                 WHERE classe_id = ? AND status = 'inscrit' AND annee_scolaire_id = ?",
                                [$evaluation['classe_id'], $current_year['id']]
                            )->fetch()['total'];
                            
                            $pourcentage_notes = $total_eleves > 0 ? round(($evaluation['nb_eleves_notes'] / $total_eleves) * 100, 1) : 0;
                            $status_class = $pourcentage_notes >= 90 ? 'success' : ($pourcentage_notes >= 50 ? 'warning' : 'danger');
                            ?>
                            <tr>
                                <td><?php echo $index + 1; ?></td>
                                <td>
                                    <strong><?php echo htmlspecialchars($evaluation['nom']); ?></strong>
                                    <?php if ($evaluation['description']): ?>
                                        <br><small class="text-muted"><?php echo htmlspecialchars(substr($evaluation['description'], 0, 50)) . (strlen($evaluation['description']) > 50 ? '...' : ''); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php echo htmlspecialchars($evaluation['matiere_nom']); ?>
                                    <br><small class="text-muted">Coeff. <?php echo $evaluation['matiere_coefficient']; ?></small>
                                </td>
                                <td>
                                    <?php echo htmlspecialchars($evaluation['classe_nom']); ?>
                                    <br><small class="text-muted"><?php echo ucfirst($evaluation['niveau']); ?></small>
                                </td>
                                <td>
                                    <span class="badge bg-primary"><?php echo htmlspecialchars($evaluation['type_evaluation']); ?></span>
                                </td>
                                <td>
                                    <?php echo formatDate($evaluation['date_evaluation']); ?>
                                    <br><small class="text-muted"><?php echo $evaluation['note_max']; ?>/20</small>
                                </td>
                                <td>
                                    <span class="badge bg-warning"><?php echo htmlspecialchars($evaluation['periode']); ?></span>
                                </td>
                                <td>
                                    <span class="badge bg-<?php echo $status_class; ?>">
                                        <?php echo $evaluation['nb_eleves_notes']; ?>/<?php echo $total_eleves; ?>
                                    </span>
                                    <br><small class="text-muted"><?php echo $pourcentage_notes; ?>%</small>
                                </td>
                                <td>
                                    <?php if ($evaluation['moyenne_classe']): ?>
                                        <span class="badge bg-info"><?php echo round($evaluation['moyenne_classe'], 2); ?></span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="btn-group" role="group">
                                        <a href="view.php?id=<?php echo $evaluation['id']; ?>" class="btn btn-sm btn-info" title="Voir">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="edit.php?id=<?php echo $evaluation['id']; ?>" class="btn btn-sm btn-warning" title="Modifier">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <a href="../notes/add.php?evaluation_id=<?php echo $evaluation['id']; ?>" class="btn btn-sm btn-success" title="Ajouter des notes">
                                            <i class="fas fa-plus"></i>
                                        </a>
                                        <button type="button" class="btn btn-sm btn-danger" title="Supprimer" 
                                                onclick="confirmDelete(<?php echo $evaluation['id']; ?>, '<?php echo addslashes($evaluation['nom']); ?>')">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Graphique de répartition par type -->
<?php if (!empty($repartition_types)): ?>
    <div class="row mt-4">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-chart-pie me-2"></i>
                        Répartition par type d'évaluation
                    </h5>
                </div>
                <div class="card-body">
                    <canvas id="typesChart" width="400" height="200"></canvas>
                </div>
            </div>
        </div>
        
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-list me-2"></i>
                        Détail par type
                    </h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Type</th>
                                    <th>Nombre</th>
                                    <th>Pourcentage</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($repartition_types as $type): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($type['type_evaluation']); ?></td>
                                        <td><span class="badge bg-primary"><?php echo $type['nombre']; ?></span></td>
                                        <td><?php echo round(($type['nombre'] / $stats['total_evaluations']) * 100, 1); ?>%</td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<script>
function confirmDelete(id, nom) {
    if (confirm('Êtes-vous sûr de vouloir supprimer l\'évaluation "' + nom + '" ?\n\nCette action est irréversible et supprimera également toutes les notes associées.')) {
        window.location.href = 'delete.php?id=' + id;
    }
}
</script>

<?php if (!empty($repartition_types)): ?>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const ctx = document.getElementById('typesChart').getContext('2d');
            new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: [<?php echo implode(',', array_map(function($type) { return '"' . addslashes($type['type_evaluation']) . '"'; }, $repartition_types)); ?>],
                    datasets: [{
                        data: [<?php echo implode(',', array_map(function($type) { return $type['nombre']; }, $repartition_types)); ?>],
                        backgroundColor: [
                            'rgba(54, 162, 235, 0.8)',
                            'rgba(255, 99, 132, 0.8)',
                            'rgba(255, 205, 86, 0.8)',
                            'rgba(75, 192, 192, 0.8)',
                            'rgba(153, 102, 255, 0.8)',
                            'rgba(255, 159, 64, 0.8)'
                        ],
                        borderWidth: 2,
                        borderColor: '#fff'
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: {
                            position: 'bottom'
                        }
                    }
                }
            });
        });
    </script>
<?php endif; ?>

<?php include '../../../includes/footer.php'; ?>
