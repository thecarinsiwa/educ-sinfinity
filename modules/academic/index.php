<?php
/**
 * Module de gestion académique - Page principale
 * Application de gestion scolaire - République Démocratique du Congo
 */

require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../includes/functions.php';

// Vérifier l'authentification et les permissions
requireLogin();
if (!checkPermission('academic') && !checkPermission('academic_view')) {
    showMessage('error', 'Accès refusé à ce module.');
    redirectTo('../../dashboard.php');
}

$page_title = 'Gestion Académique';

// Obtenir l'année scolaire actuelle
$current_year = getCurrentAcademicYear();

// Statistiques générales
$stats = [];

// Nombre de classes
$stmt = $database->query("SELECT COUNT(*) as total FROM classes WHERE annee_scolaire_id = ?", [$current_year['id'] ?? 0]);
$stats['total_classes'] = $stmt->fetch()['total'];

// Nombre de matières
$stmt = $database->query("SELECT COUNT(*) as total FROM matieres");
$stats['total_matieres'] = $stmt->fetch()['total'];

// Nombre d'enseignants
$stmt = $database->query("SELECT COUNT(*) as total FROM personnel WHERE fonction = 'enseignant' AND status = 'actif'");
$stats['total_enseignants'] = $stmt->fetch()['total'];

// Nombre d'emplois du temps configurés
$stmt = $database->query("SELECT COUNT(DISTINCT classe_id) as total FROM emplois_temps WHERE annee_scolaire_id = ?", [$current_year['id'] ?? 0]);
$stats['classes_avec_emploi'] = $stmt->fetch()['total'];

// Répartition par niveau
$niveaux = $database->query(
    "SELECT niveau, COUNT(*) as nb_classes 
     FROM classes 
     WHERE annee_scolaire_id = ? 
     GROUP BY niveau 
     ORDER BY 
        CASE niveau 
            WHEN 'maternelle' THEN 1 
            WHEN 'primaire' THEN 2 
            WHEN 'secondaire' THEN 3 
            ELSE 4 
        END", 
    [$current_year['id'] ?? 0]
)->fetchAll();

// Classes récemment créées
$recent_classes = $database->query(
    "SELECT c.*, COUNT(i.id) as nb_eleves 
     FROM classes c 
     LEFT JOIN inscriptions i ON c.id = i.classe_id AND i.status = 'inscrit'
     WHERE c.annee_scolaire_id = ? 
     GROUP BY c.id 
     ORDER BY c.created_at DESC 
     LIMIT 5",
    [$current_year['id'] ?? 0]
)->fetchAll();

include '../../includes/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">
        <i class="fas fa-book me-2"></i>
        Gestion Académique
    </h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <div class="btn-group me-2">
            <button type="button" class="btn btn-outline-secondary">
                <i class="fas fa-calendar-alt me-1"></i>
                <?php echo $current_year['annee'] ?? 'Aucune année active'; ?>
            </button>
        </div>
        <?php if (checkPermission('academic')): ?>
            <div class="btn-group">
                <button type="button" class="btn btn-primary dropdown-toggle" data-bs-toggle="dropdown">
                    <i class="fas fa-plus me-1"></i>
                    Nouveau
                </button>
                <ul class="dropdown-menu">
                    <li><a class="dropdown-item" href="classes/add.php">
                        <i class="fas fa-school me-2"></i>Nouvelle classe
                    </a></li>
                    <li><a class="dropdown-item" href="subjects/add.php">
                        <i class="fas fa-book-open me-2"></i>Nouvelle matière
                    </a></li>
                    <li><a class="dropdown-item" href="schedule/add.php">
                        <i class="fas fa-calendar-plus me-2"></i>Emploi du temps
                    </a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item" href="years/add.php">
                        <i class="fas fa-calendar-check me-2"></i>Année scolaire
                    </a></li>
                </ul>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Statistiques rapides -->
<div class="row mb-4">
    <div class="col-md-3">
        <div class="card text-white bg-primary">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h4><?php echo $stats['total_classes']; ?></h4>
                        <p class="mb-0">Classes</p>
                    </div>
                    <div class="align-self-center">
                        <i class="fas fa-school fa-2x"></i>
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
                        <h4><?php echo $stats['total_matieres']; ?></h4>
                        <p class="mb-0">Matières</p>
                    </div>
                    <div class="align-self-center">
                        <i class="fas fa-book-open fa-2x"></i>
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
                        <h4><?php echo $stats['total_enseignants']; ?></h4>
                        <p class="mb-0">Enseignants</p>
                    </div>
                    <div class="align-self-center">
                        <i class="fas fa-chalkboard-teacher fa-2x"></i>
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
                        <h4><?php echo $stats['classes_avec_emploi']; ?></h4>
                        <p class="mb-0">Emplois configurés</p>
                    </div>
                    <div class="align-self-center">
                        <i class="fas fa-calendar-check fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modules académiques -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-th-large me-2"></i>
                    Modules académiques
                </h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-lg-3 col-md-6 mb-3">
                        <a href="classes/" class="text-decoration-none">
                            <div class="card h-100 border-0 shadow-sm hover-card">
                                <div class="card-body text-center">
                                    <i class="fas fa-school fa-3x text-primary mb-3"></i>
                                    <h5 class="card-title">Classes</h5>
                                    <p class="card-text text-muted">
                                        Gestion des classes par niveau et année scolaire
                                    </p>
                                    <div class="mt-3">
                                        <span class="badge bg-primary"><?php echo $stats['total_classes']; ?> classes</span>
                                    </div>
                                </div>
                            </div>
                        </a>
                    </div>
                    
                    <div class="col-lg-3 col-md-6 mb-3">
                        <a href="subjects/" class="text-decoration-none">
                            <div class="card h-100 border-0 shadow-sm hover-card">
                                <div class="card-body text-center">
                                    <i class="fas fa-book-open fa-3x text-success mb-3"></i>
                                    <h5 class="card-title">Matières</h5>
                                    <p class="card-text text-muted">
                                        Configuration des matières et coefficients
                                    </p>
                                    <div class="mt-3">
                                        <span class="badge bg-success"><?php echo $stats['total_matieres']; ?> matières</span>
                                    </div>
                                </div>
                            </div>
                        </a>
                    </div>
                    
                    <div class="col-lg-3 col-md-6 mb-3">
                        <a href="schedule/" class="text-decoration-none">
                            <div class="card h-100 border-0 shadow-sm hover-card">
                                <div class="card-body text-center">
                                    <i class="fas fa-calendar-alt fa-3x text-warning mb-3"></i>
                                    <h5 class="card-title">Emplois du temps</h5>
                                    <p class="card-text text-muted">
                                        Planification des cours et horaires
                                    </p>
                                    <div class="mt-3">
                                        <span class="badge bg-warning"><?php echo $stats['classes_avec_emploi']; ?> configurés</span>
                                    </div>
                                </div>
                            </div>
                        </a>
                    </div>
                    
                    <div class="col-lg-3 col-md-6 mb-3">
                        <a href="years/" class="text-decoration-none">
                            <div class="card h-100 border-0 shadow-sm hover-card">
                                <div class="card-body text-center">
                                    <i class="fas fa-calendar-check fa-3x text-info mb-3"></i>
                                    <h5 class="card-title">Années scolaires</h5>
                                    <p class="card-text text-muted">
                                        Gestion des périodes scolaires
                                    </p>
                                    <div class="mt-3">
                                        <span class="badge bg-info">Année <?php echo date('Y'); ?></span>
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

<!-- Répartition par niveau et classes récentes -->
<div class="row">
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-chart-pie me-2"></i>
                    Répartition par niveau
                </h5>
            </div>
            <div class="card-body">
                <?php if (!empty($niveaux)): ?>
                    <canvas id="niveauxChart" width="100%" height="200"></canvas>
                    <div class="mt-3">
                        <?php foreach ($niveaux as $niveau): ?>
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span class="badge bg-<?php 
                                    echo $niveau['niveau'] === 'maternelle' ? 'warning' : 
                                        ($niveau['niveau'] === 'primaire' ? 'success' : 'primary'); 
                                ?>">
                                    <?php echo ucfirst($niveau['niveau']); ?>
                                </span>
                                <span><?php echo $niveau['nb_classes']; ?> classe<?php echo $niveau['nb_classes'] > 1 ? 's' : ''; ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="text-center py-4">
                        <i class="fas fa-chart-pie fa-3x text-muted mb-3"></i>
                        <p class="text-muted">Aucune classe configurée</p>
                        <?php if (checkPermission('academic')): ?>
                            <a href="classes/add.php" class="btn btn-primary">
                                <i class="fas fa-plus me-1"></i>
                                Créer une classe
                            </a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-clock me-2"></i>
                    Classes récentes
                </h5>
            </div>
            <div class="card-body">
                <?php if (!empty($recent_classes)): ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($recent_classes as $classe): ?>
                            <div class="list-group-item d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="mb-1"><?php echo htmlspecialchars($classe['nom']); ?></h6>
                                    <small class="text-muted">
                                        <?php echo ucfirst($classe['niveau']); ?> - 
                                        <?php echo $classe['nb_eleves']; ?> élève<?php echo $classe['nb_eleves'] > 1 ? 's' : ''; ?>
                                    </small>
                                </div>
                                <div>
                                    <a href="classes/view.php?id=<?php echo $classe['id']; ?>" 
                                       class="btn btn-sm btn-outline-primary">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="text-center mt-3">
                        <a href="classes/" class="btn btn-outline-secondary">
                            <i class="fas fa-list me-1"></i>
                            Voir toutes les classes
                        </a>
                    </div>
                <?php else: ?>
                    <div class="text-center py-4">
                        <i class="fas fa-school fa-3x text-muted mb-3"></i>
                        <p class="text-muted">Aucune classe créée</p>
                        <?php if (checkPermission('academic')): ?>
                            <a href="classes/add.php" class="btn btn-primary">
                                <i class="fas fa-plus me-1"></i>
                                Créer la première classe
                            </a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Actions rapides -->
<?php if (checkPermission('academic')): ?>
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
                            <a href="classes/add.php" class="btn btn-outline-primary">
                                <i class="fas fa-school me-2"></i>
                                Nouvelle classe
                            </a>
                        </div>
                    </div>
                    <div class="col-md-3 mb-2">
                        <div class="d-grid">
                            <a href="subjects/add.php" class="btn btn-outline-success">
                                <i class="fas fa-book-open me-2"></i>
                                Nouvelle matière
                            </a>
                        </div>
                    </div>
                    <div class="col-md-3 mb-2">
                        <div class="d-grid">
                            <a href="schedule/generate.php" class="btn btn-outline-warning">
                                <i class="fas fa-magic me-2"></i>
                                Générer emploi du temps
                            </a>
                        </div>
                    </div>
                    <div class="col-md-3 mb-2">
                        <div class="d-grid">
                            <a href="reports/" class="btn btn-outline-info">
                                <i class="fas fa-chart-bar me-2"></i>
                                Rapports académiques
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
// Graphique de répartition par niveau
<?php if (!empty($niveaux)): ?>
const niveauxCtx = document.getElementById('niveauxChart').getContext('2d');
const niveauxChart = new Chart(niveauxCtx, {
    type: 'doughnut',
    data: {
        labels: [<?php echo implode(',', array_map(function($n) { return "'" . ucfirst($n['niveau']) . "'"; }, $niveaux)); ?>],
        datasets: [{
            data: [<?php echo implode(',', array_column($niveaux, 'nb_classes')); ?>],
            backgroundColor: ['#f39c12', '#27ae60', '#3498db', '#9b59b6'],
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
