<?php
/**
 * Module d'évaluations et notes - Page principale
 * Application de gestion scolaire - République Démocratique du Congo
 */

require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../includes/functions.php';

// Vérifier l'authentification et les permissions
requireLogin();
if (!checkPermission('evaluations') && !checkPermission('evaluations_view')) {
    showMessage('error', 'Accès refusé à ce module.');
    redirectTo('../../dashboard.php');
}

$page_title = 'Évaluations et Notes';

// Obtenir l'année scolaire actuelle
$current_year = getCurrentAcademicYear();

// Statistiques générales
$stats = [];

// Nombre total d'évaluations
$stmt = $database->query(
    "SELECT COUNT(*) as total FROM evaluations WHERE annee_scolaire_id = ?",
    [$current_year['id'] ?? 0]
);
$stats['total_evaluations'] = $stmt->fetch()['total'];

// Nombre de notes saisies
$stmt = $database->query(
    "SELECT COUNT(*) as total FROM notes n 
     JOIN evaluations e ON n.evaluation_id = e.id 
     WHERE e.annee_scolaire_id = ?",
    [$current_year['id'] ?? 0]
);
$stats['total_notes'] = $stmt->fetch()['total'];

// Nombre d'élèves évalués
$stmt = $database->query(
    "SELECT COUNT(DISTINCT n.eleve_id) as total FROM notes n 
     JOIN evaluations e ON n.evaluation_id = e.id 
     WHERE e.annee_scolaire_id = ?",
    [$current_year['id'] ?? 0]
);
$stats['eleves_evalues'] = $stmt->fetch()['total'];

// Moyenne générale de l'école
$stmt = $database->query(
    "SELECT AVG(n.note) as moyenne FROM notes n 
     JOIN evaluations e ON n.evaluation_id = e.id 
     WHERE e.annee_scolaire_id = ? AND n.note IS NOT NULL",
    [$current_year['id'] ?? 0]
);
$stats['moyenne_generale'] = round($stmt->fetch()['moyenne'] ?? 0, 2);

// Répartition par type d'évaluation
$types_evaluations = $database->query(
    "SELECT type_evaluation, COUNT(*) as nombre 
     FROM evaluations 
     WHERE annee_scolaire_id = ? 
     GROUP BY type_evaluation 
     ORDER BY nombre DESC",
    [$current_year['id'] ?? 0]
)->fetchAll();

// Évaluations récentes
$evaluations_recentes = $database->query(
    "SELECT e.*, m.nom as matiere_nom, c.nom as classe_nom, c.niveau,
            p.nom as enseignant_nom, p.prenom as enseignant_prenom,
            COUNT(n.id) as nb_notes
     FROM evaluations e
     JOIN matieres m ON e.matiere_id = m.id
     JOIN classes c ON e.classe_id = c.id
     LEFT JOIN personnel p ON e.enseignant_id = p.id
     LEFT JOIN notes n ON e.id = n.evaluation_id
     WHERE e.annee_scolaire_id = ?
     GROUP BY e.id
     ORDER BY e.date_evaluation DESC, e.id DESC
     LIMIT 10",
    [$current_year['id'] ?? 0]
)->fetchAll();

// Répartition des notes par tranche
$tranches_notes = $database->query(
    "SELECT 
        CASE 
            WHEN n.note >= 16 THEN 'Excellent (16-20)'
            WHEN n.note >= 14 THEN 'Très bien (14-15)'
            WHEN n.note >= 12 THEN 'Bien (12-13)'
            WHEN n.note >= 10 THEN 'Satisfaisant (10-11)'
            WHEN n.note >= 8 THEN 'Passable (8-9)'
            ELSE 'Insuffisant (0-7)'
        END as tranche,
        COUNT(*) as nombre
     FROM notes n
     JOIN evaluations e ON n.evaluation_id = e.id
     WHERE e.annee_scolaire_id = ? AND n.note IS NOT NULL
     GROUP BY tranche
     ORDER BY MIN(n.note) DESC",
    [$current_year['id'] ?? 0]
)->fetchAll();

// Classes avec le plus d'évaluations
$classes_actives = $database->query(
    "SELECT c.nom as classe_nom, c.niveau, COUNT(e.id) as nb_evaluations,
            AVG(n.note) as moyenne_classe
     FROM classes c
     LEFT JOIN evaluations e ON c.id = e.classe_id AND e.annee_scolaire_id = ?
     LEFT JOIN notes n ON e.id = n.evaluation_id
     WHERE c.annee_scolaire_id = ?
     GROUP BY c.id
     HAVING nb_evaluations > 0
     ORDER BY nb_evaluations DESC, moyenne_classe DESC
     LIMIT 8",
    [$current_year['id'] ?? 0, $current_year['id'] ?? 0]
)->fetchAll();

include '../../includes/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">
        <i class="fas fa-chart-line me-2"></i>
        Évaluations et Notes
    </h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <div class="btn-group me-2">
            <button type="button" class="btn btn-outline-secondary">
                <i class="fas fa-calendar-alt me-1"></i>
                <?php echo $current_year['annee'] ?? 'Aucune année active'; ?>
            </button>
        </div>
        <?php if (checkPermission('evaluations')): ?>
            <div class="btn-group">
                <button type="button" class="btn btn-primary dropdown-toggle" data-bs-toggle="dropdown">
                    <i class="fas fa-plus me-1"></i>
                    Nouveau
                </button>
                <ul class="dropdown-menu">
                    <li><a class="dropdown-item" href="evaluations/add.php">
                        <i class="fas fa-clipboard-list me-2"></i>Nouvelle évaluation
                    </a></li>
                    <li><a class="dropdown-item" href="notes/batch-entry.php">
                        <i class="fas fa-edit me-2"></i>Saisie de notes
                    </a></li>
                    <li><a class="dropdown-item" href="bulletins/generate.php">
                        <i class="fas fa-file-alt me-2"></i>Générer bulletins
                    </a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item" href="periods/manage.php">
                        <i class="fas fa-calendar-check me-2"></i>Gérer périodes
                    </a></li>
                </ul>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Statistiques rapides -->
<div class="row mb-4">
    <div class="col-lg-3 col-md-6 mb-3">
        <div class="card text-white bg-primary">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h4><?php echo $stats['total_evaluations']; ?></h4>
                        <p class="mb-0">Évaluations</p>
                    </div>
                    <div class="align-self-center">
                        <i class="fas fa-clipboard-list fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-3 col-md-6 mb-3">
        <div class="card text-white bg-success">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h4><?php echo $stats['total_notes']; ?></h4>
                        <p class="mb-0">Notes saisies</p>
                    </div>
                    <div class="align-self-center">
                        <i class="fas fa-edit fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-3 col-md-6 mb-3">
        <div class="card text-white bg-info">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h4><?php echo $stats['eleves_evalues']; ?></h4>
                        <p class="mb-0">Élèves évalués</p>
                    </div>
                    <div class="align-self-center">
                        <i class="fas fa-users fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-3 col-md-6 mb-3">
        <div class="card text-white bg-warning">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h4><?php echo $stats['moyenne_generale']; ?>/20</h4>
                        <p class="mb-0">Moyenne générale</p>
                    </div>
                    <div class="align-self-center">
                        <i class="fas fa-star fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modules d'évaluations -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-th-large me-2"></i>
                    Modules d'évaluations
                </h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-lg-3 col-md-6 mb-3">
                        <a href="evaluations/" class="text-decoration-none">
                            <div class="card h-100 border-0 shadow-sm hover-card">
                                <div class="card-body text-center">
                                    <i class="fas fa-clipboard-list fa-3x text-primary mb-3"></i>
                                    <h5 class="card-title">Évaluations</h5>
                                    <p class="card-text text-muted">
                                        Gestion des examens, devoirs et compositions
                                    </p>
                                    <div class="mt-3">
                                        <span class="badge bg-primary"><?php echo $stats['total_evaluations']; ?> évaluations</span>
                                    </div>
                                </div>
                            </div>
                        </a>
                    </div>
                    
                    <div class="col-lg-3 col-md-6 mb-3">
                        <a href="notes/" class="text-decoration-none">
                            <div class="card h-100 border-0 shadow-sm hover-card">
                                <div class="card-body text-center">
                                    <i class="fas fa-edit fa-3x text-success mb-3"></i>
                                    <h5 class="card-title">Saisie de notes</h5>
                                    <p class="card-text text-muted">
                                        Enregistrement et modification des notes
                                    </p>
                                    <div class="mt-3">
                                        <span class="badge bg-success"><?php echo $stats['total_notes']; ?> notes</span>
                                    </div>
                                </div>
                            </div>
                        </a>
                    </div>
                    
                    <div class="col-lg-3 col-md-6 mb-3">
                        <a href="bulletins/" class="text-decoration-none">
                            <div class="card h-100 border-0 shadow-sm hover-card">
                                <div class="card-body text-center">
                                    <i class="fas fa-file-alt fa-3x text-warning mb-3"></i>
                                    <h5 class="card-title">Bulletins</h5>
                                    <p class="card-text text-muted">
                                        Génération et impression des bulletins
                                    </p>
                                    <div class="mt-3">
                                        <span class="badge bg-warning">Rapports</span>
                                    </div>
                                </div>
                            </div>
                        </a>
                    </div>
                    
                    <div class="col-lg-3 col-md-6 mb-3">
                        <a href="statistics/" class="text-decoration-none">
                            <div class="card h-100 border-0 shadow-sm hover-card">
                                <div class="card-body text-center">
                                    <i class="fas fa-chart-bar fa-3x text-info mb-3"></i>
                                    <h5 class="card-title">Statistiques</h5>
                                    <p class="card-text text-muted">
                                        Analyses et rapports de performance
                                    </p>
                                    <div class="mt-3">
                                        <span class="badge bg-info">Analyses</span>
                                    </div>
                                </div>
                            </div>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Graphiques et données -->
<div class="row">
    <div class="col-lg-8">
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-chart-pie me-2"></i>
                    Répartition des notes par niveau
                </h5>
            </div>
            <div class="card-body">
                <?php if (!empty($tranches_notes)): ?>
                    <canvas id="notesChart" width="100%" height="300"></canvas>
                    <div class="row mt-3">
                        <?php foreach ($tranches_notes as $tranche): ?>
                            <div class="col-md-4 mb-2">
                                <div class="d-flex justify-content-between align-items-center">
                                    <span class="small"><?php echo $tranche['tranche']; ?></span>
                                    <span class="badge bg-primary"><?php echo $tranche['nombre']; ?></span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="text-center py-4">
                        <i class="fas fa-chart-pie fa-3x text-muted mb-3"></i>
                        <p class="text-muted">Aucune note disponible pour générer les statistiques</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Évaluations récentes -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-clock me-2"></i>
                    Évaluations récentes
                </h5>
            </div>
            <div class="card-body">
                <?php if (!empty($evaluations_recentes)): ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Évaluation</th>
                                    <th>Classe</th>
                                    <th>Matière</th>
                                    <th>Enseignant</th>
                                    <th>Notes</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($evaluations_recentes as $eval): ?>
                                    <tr>
                                        <td><?php echo formatDate($eval['date_evaluation']); ?></td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($eval['nom']); ?></strong>
                                            <br><small class="text-muted">
                                                <?php echo ucfirst($eval['type_evaluation']); ?>
                                            </small>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?php 
                                                echo $eval['niveau'] === 'maternelle' ? 'warning' : 
                                                    ($eval['niveau'] === 'primaire' ? 'success' : 'primary'); 
                                            ?>">
                                                <?php echo htmlspecialchars($eval['classe_nom']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo htmlspecialchars($eval['matiere_nom']); ?></td>
                                        <td>
                                            <?php if ($eval['enseignant_nom']): ?>
                                                <?php echo htmlspecialchars($eval['enseignant_nom'] . ' ' . substr($eval['enseignant_prenom'], 0, 1) . '.'); ?>
                                            <?php else: ?>
                                                <span class="text-muted">Non assigné</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($eval['nb_notes'] > 0): ?>
                                                <span class="badge bg-success"><?php echo $eval['nb_notes']; ?></span>
                                            <?php else: ?>
                                                <span class="badge bg-warning">0</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <a href="evaluations/view.php?id=<?php echo $eval['id']; ?>" 
                                                   class="btn btn-outline-info" title="Voir">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                <?php if (checkPermission('evaluations')): ?>
                                                    <a href="notes/entry.php?evaluation_id=<?php echo $eval['id']; ?>" 
                                                       class="btn btn-outline-primary" title="Saisir notes">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="text-center mt-3">
                        <a href="evaluations/" class="btn btn-outline-secondary">
                            <i class="fas fa-list me-1"></i>
                            Voir toutes les évaluations
                        </a>
                    </div>
                <?php else: ?>
                    <div class="text-center py-4">
                        <i class="fas fa-clipboard-list fa-3x text-muted mb-3"></i>
                        <p class="text-muted">Aucune évaluation créée</p>
                        <?php if (checkPermission('evaluations')): ?>
                            <a href="evaluations/add.php" class="btn btn-primary">
                                <i class="fas fa-plus me-1"></i>
                                Créer la première évaluation
                            </a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <div class="col-lg-4">
        <!-- Types d'évaluations -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-tags me-2"></i>
                    Types d'évaluations
                </h5>
            </div>
            <div class="card-body">
                <?php if (!empty($types_evaluations)): ?>
                    <?php foreach ($types_evaluations as $type): ?>
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span><?php echo ucfirst($type['type_evaluation']); ?></span>
                            <span class="badge bg-primary"><?php echo $type['nombre']; ?></span>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p class="text-muted text-center">Aucune évaluation configurée</p>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Classes les plus actives -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-trophy me-2"></i>
                    Classes les plus actives
                </h5>
            </div>
            <div class="card-body">
                <?php if (!empty($classes_actives)): ?>
                    <?php foreach ($classes_actives as $classe): ?>
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <div>
                                <strong><?php echo htmlspecialchars($classe['classe_nom']); ?></strong>
                                <br><small class="text-muted">
                                    <?php echo $classe['nb_evaluations']; ?> évaluation<?php echo $classe['nb_evaluations'] > 1 ? 's' : ''; ?>
                                </small>
                            </div>
                            <div class="text-end">
                                <?php if ($classe['moyenne_classe']): ?>
                                    <span class="badge bg-<?php 
                                        echo $classe['moyenne_classe'] >= 14 ? 'success' : 
                                            ($classe['moyenne_classe'] >= 10 ? 'warning' : 'danger'); 
                                    ?>">
                                        <?php echo round($classe['moyenne_classe'], 1); ?>/20
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p class="text-muted text-center">Aucune évaluation disponible</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Actions rapides -->
<?php if (checkPermission('evaluations')): ?>
<div class="row mt-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-bolt me-2"></i>
                    Actions rapides
                </h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-3 mb-2">
                        <div class="d-grid">
                            <a href="evaluations/add.php" class="btn btn-outline-primary">
                                <i class="fas fa-plus me-2"></i>
                                Nouvelle évaluation
                            </a>
                        </div>
                    </div>
                    <div class="col-md-3 mb-2">
                        <div class="d-grid">
                            <a href="notes/batch-entry.php" class="btn btn-outline-success">
                                <i class="fas fa-edit me-2"></i>
                                Saisie rapide
                            </a>
                        </div>
                    </div>
                    <div class="col-md-3 mb-2">
                        <div class="d-grid">
                            <a href="bulletins/generate.php" class="btn btn-outline-warning">
                                <i class="fas fa-file-alt me-2"></i>
                                Générer bulletins
                            </a>
                        </div>
                    </div>
                    <div class="col-md-3 mb-2">
                        <div class="d-grid">
                            <a href="statistics/class-ranking.php" class="btn btn-outline-info">
                                <i class="fas fa-chart-bar me-2"></i>
                                Classements
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
// Graphique de répartition des notes
<?php if (!empty($tranches_notes)): ?>
const notesCtx = document.getElementById('notesChart').getContext('2d');
const notesChart = new Chart(notesCtx, {
    type: 'doughnut',
    data: {
        labels: [<?php echo implode(',', array_map(function($t) { return "'" . $t['tranche'] . "'"; }, $tranches_notes)); ?>],
        datasets: [{
            data: [<?php echo implode(',', array_column($tranches_notes, 'nombre')); ?>],
            backgroundColor: ['#28a745', '#17a2b8', '#ffc107', '#fd7e14', '#dc3545', '#6c757d'],
            borderWidth: 0
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: 'bottom'
            }
        }
    }
});
<?php endif; ?>
</script>

<?php include '../../includes/footer.php'; ?>
