<?php
/**
 * Module Gestion des Élèves - Page principale
 * Application de gestion scolaire - République Démocratique du Congo
 */

require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../includes/functions.php';

// Vérifier l'authentification et les permissions
requireLogin();
if (!checkPermission('students') && !checkPermission('students_view')) {
    showMessage('error', 'Accès refusé à ce module.');
    redirectTo('../../dashboard.php');
}

$page_title = 'Gestion des Élèves';

// Obtenir l'année scolaire actuelle
$current_year = getCurrentAcademicYear();

// Statistiques générales
$stats = [];

// Total des élèves inscrits
$stmt = $database->query(
    "SELECT COUNT(*) as total FROM inscriptions WHERE status = 'inscrit' AND annee_scolaire_id = ?",
    [$current_year['id'] ?? 0]
);
$stats['total_eleves'] = $stmt->fetch()['total'];

// Élèves par sexe
try {
    $stmt = $database->query(
        "SELECT e.sexe, COUNT(*) as total 
         FROM eleves e 
         JOIN inscriptions i ON e.id = i.eleve_id 
         WHERE i.status = 'inscrit' AND i.annee_scolaire_id = ? 
         GROUP BY e.sexe",
        [$current_year['id'] ?? 0]
    );
    $sexe_stats = $stmt->fetchAll();
    $stats['garcons'] = 0;
    $stats['filles'] = 0;
    foreach ($sexe_stats as $stat) {
        if ($stat['sexe'] === 'M') {
            $stats['garcons'] = $stat['total'];
        } else {
            $stats['filles'] = $stat['total'];
        }
    }
} catch (Exception $e) {
    $stats['garcons'] = 0;
    $stats['filles'] = 0;
}

// Nouvelles inscriptions ce mois
try {
    $stmt = $database->query(
        "SELECT COUNT(*) as total FROM inscriptions
         WHERE MONTH(created_at) = MONTH(CURDATE())
         AND YEAR(created_at) = YEAR(CURDATE())
         AND annee_scolaire_id = ?",
        [$current_year['id'] ?? 0]
    );
    $stats['nouvelles_inscriptions'] = $stmt->fetch()['total'];
} catch (Exception $e) {
    $stats['nouvelles_inscriptions'] = 0;
}

// Absences aujourd'hui
try {
    $stmt = $database->query(
        "SELECT COUNT(*) as total FROM absences
         WHERE DATE(date_absence) = CURDATE() AND type_absence = 'absence'"
    );
    $stats['absences_aujourd_hui'] = $stmt->fetch()['total'];
} catch (Exception $e) {
    $stats['absences_aujourd_hui'] = 0;
}

// Répartition par niveau
$repartition_niveaux = $database->query(
    "SELECT c.niveau, COUNT(i.id) as nombre
     FROM classes c
     LEFT JOIN inscriptions i ON c.id = i.classe_id AND i.status = 'inscrit'
     WHERE c.annee_scolaire_id = ?
     GROUP BY c.niveau
     ORDER BY 
        CASE c.niveau 
            WHEN 'maternelle' THEN 1 
            WHEN 'primaire' THEN 2 
            WHEN 'secondaire' THEN 3 
        END",
    [$current_year['id'] ?? 0]
)->fetchAll();

// Élèves récemment inscrits
try {
    $eleves_recents = $database->query(
        "SELECT e.nom, e.prenom, e.numero_matricule, c.nom as classe_nom, c.niveau, i.created_at
         FROM eleves e
         JOIN inscriptions i ON e.id = i.eleve_id
         JOIN classes c ON i.classe_id = c.id
         WHERE i.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
         AND i.annee_scolaire_id = ?
         ORDER BY i.created_at DESC
         LIMIT 8",
        [$current_year['id'] ?? 0]
    )->fetchAll();
} catch (Exception $e) {
    $eleves_recents = [];
}

// Classes avec le plus d'élèves
$classes_nombreuses = $database->query(
    "SELECT c.nom, c.niveau, COUNT(i.id) as effectif
     FROM classes c
     LEFT JOIN inscriptions i ON c.id = i.classe_id AND i.status = 'inscrit'
     WHERE c.annee_scolaire_id = ?
     GROUP BY c.id
     ORDER BY effectif DESC
     LIMIT 5",
    [$current_year['id'] ?? 0]
)->fetchAll();

include '../../includes/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">
        <i class="fas fa-users me-2"></i>
                    Gestion des Élèves
                </h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <div class="btn-group me-2">
            <button type="button" class="btn btn-outline-secondary">
                <i class="fas fa-calendar-alt me-1"></i>
                <?php echo $current_year['annee'] ?? 'Aucune année active'; ?>
            </button>
        </div>
                <?php if (checkPermission('students')): ?>
            <div class="btn-group">
                        <button type="button" class="btn btn-primary dropdown-toggle" data-bs-toggle="dropdown">
                            <i class="fas fa-plus me-1"></i>
                            Nouveau
                        </button>
                        <ul class="dropdown-menu">
                    <li><a class="dropdown-item" href="add.php">
                        <i class="fas fa-user-plus me-2"></i>Ajouter un élève
                    </a></li>
                            <li><a class="dropdown-item" href="admissions/new-application.php">
                                <i class="fas fa-file-alt me-2"></i>Demande d'admission
                            </a></li>
                            <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item" href="admissions/bulk-import.php">
                        <i class="fas fa-file-import me-2"></i>Import en masse
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
                        <h4><?php echo $stats['total_eleves']; ?></h4>
                        <p class="mb-0">Élèves inscrits</p>
                </div>
                    <div class="align-self-center">
                        <i class="fas fa-users fa-2x"></i>
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
                        <h4><?php echo $stats['garcons']; ?></h4>
                        <p class="mb-0">Garçons</p>
                </div>
                    <div class="align-self-center">
                        <i class="fas fa-male fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-white bg-danger">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h4><?php echo $stats['filles']; ?></h4>
                        <p class="mb-0">Filles</p>
                </div>
                    <div class="align-self-center">
                        <i class="fas fa-female fa-2x"></i>
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
                        <h4><?php echo $stats['nouvelles_inscriptions']; ?></h4>
                        <p class="mb-0">Nouvelles inscriptions</p>
                </div>
                    <div class="align-self-center">
                        <i class="fas fa-user-plus fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modules de gestion des élèves -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-th-large me-2"></i>
                    Modules de gestion des élèves
                </h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-lg-3 col-md-6 mb-3">
                        <a href="list.php" class="text-decoration-none">
                            <div class="card h-100 border-0 shadow-sm hover-card">
                                <div class="card-body text-center">
                                    <i class="fas fa-list fa-3x text-primary mb-3"></i>
                                    <h5 class="card-title">Liste des élèves</h5>
                                    <p class="card-text text-muted">
                                        Consulter et gérer la liste complète des élèves
                                    </p>
                                    <div class="mt-3">
                                        <span class="badge bg-primary"><?php echo $stats['total_eleves']; ?> élèves</span>
                        </div>
                        </div>
                        </div>
                    </a>
                    </div>
                    
                    <div class="col-lg-3 col-md-6 mb-3">
                        <a href="add.php" class="text-decoration-none">
                            <div class="card h-100 border-0 shadow-sm hover-card">
                                <div class="card-body text-center">
                                    <i class="fas fa-user-plus fa-3x text-success mb-3"></i>
                                    <h5 class="card-title">Ajouter un élève</h5>
                                    <p class="card-text text-muted">
                                        Inscription directe d'un nouvel élève
                                    </p>
                                    <div class="mt-3">
                                        <span class="badge bg-success">Inscription rapide</span>
                        </div>
                        </div>
                        </div>
                    </a>
                    </div>
                    
                    <div class="col-lg-3 col-md-6 mb-3">
                        <a href="admissions/" class="text-decoration-none">
                            <div class="card h-100 border-0 shadow-sm hover-card">
                                <div class="card-body text-center">
                                    <i class="fas fa-file-alt fa-3x text-warning mb-3"></i>
                                    <h5 class="card-title">Admissions</h5>
                                    <p class="card-text text-muted">
                                        Gestion des demandes d'admission
                                    </p>
                                    <div class="mt-3">
                                        <span class="badge bg-warning">Processus complet</span>
                        </div>
                        </div>
                        </div>
                    </a>
                    </div>
                    
                    <div class="col-lg-3 col-md-6 mb-3">
                        <a href="attendance/" class="text-decoration-none">
                            <div class="card h-100 border-0 shadow-sm hover-card">
                                <div class="card-body text-center">
                                    <i class="fas fa-calendar-check fa-3x text-info mb-3"></i>
                                    <h5 class="card-title">Présences</h5>
                                    <p class="card-text text-muted">
                                        Gestion des absences et retards
                                    </p>
                                    <div class="mt-3">
                                        <span class="badge bg-info">Suivi quotidien</span>
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

<!-- Répartition par niveau et élèves récents -->
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
                <?php if (!empty($repartition_niveaux)): ?>
                    <canvas id="niveauxChart" width="100%" height="200"></canvas>
                    <div class="mt-3">
                        <?php foreach ($repartition_niveaux as $niveau): ?>
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span class="badge bg-<?php 
                                    echo $niveau['niveau'] === 'maternelle' ? 'warning' : 
                                        ($niveau['niveau'] === 'primaire' ? 'success' : 'primary'); 
                                ?>">
                                    <?php echo ucfirst($niveau['niveau']); ?>
                                </span>
                                <span><?php echo $niveau['nombre']; ?> élève<?php echo $niveau['nombre'] > 1 ? 's' : ''; ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="text-center py-4">
                        <i class="fas fa-chart-pie fa-3x text-muted mb-3"></i>
                        <p class="text-muted">Aucun élève inscrit</p>
                        <?php if (checkPermission('students')): ?>
                            <a href="add.php" class="btn btn-primary">
                                <i class="fas fa-plus me-1"></i>
                                Ajouter un élève
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
                    Élèves récemment inscrits
                </h5>
                        </div>
            <div class="card-body">
                <?php if (!empty($eleves_recents)): ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($eleves_recents as $eleve): ?>
                            <div class="list-group-item d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="mb-1"><?php echo htmlspecialchars($eleve['nom'] . ' ' . $eleve['prenom']); ?></h6>
                                    <small class="text-muted">
                                        <?php echo htmlspecialchars($eleve['classe_nom']); ?> - 
                                        <?php echo ucfirst($eleve['niveau']); ?>
                                    </small>
                        </div>
                                <div>
                                    <a href="view.php?id=<?php echo $eleve['id'] ?? ''; ?>" 
                                       class="btn btn-sm btn-outline-primary">
                                        <i class="fas fa-eye"></i>
                    </a>
                        </div>
                        </div>
                        <?php endforeach; ?>
                        </div>
                    <div class="text-center mt-3">
                        <a href="list.php" class="btn btn-outline-secondary">
                            <i class="fas fa-list me-1"></i>
                            Voir tous les élèves
                        </a>
                        </div>
                <?php else: ?>
                    <div class="text-center py-4">
                        <i class="fas fa-users fa-3x text-muted mb-3"></i>
                        <p class="text-muted">Aucun élève récemment inscrit</p>
                        <?php if (checkPermission('students')): ?>
                            <a href="add.php" class="btn btn-primary">
                                <i class="fas fa-plus me-1"></i>
                                Ajouter un élève
                            </a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Actions rapides -->
<?php if (checkPermission('students')): ?>
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
                            <a href="add.php" class="btn btn-outline-primary">
                                <i class="fas fa-user-plus me-2"></i>
                                Ajouter un élève
                            </a>
                        </div>
                        </div>
                    <div class="col-md-3 mb-2">
                        <div class="d-grid">
                            <a href="list.php" class="btn btn-outline-success">
                                <i class="fas fa-list me-2"></i>
                                Liste des élèves
                            </a>
                        </div>
                        </div>
                    <div class="col-md-3 mb-2">
                        <div class="d-grid">
                            <a href="attendance/" class="btn btn-outline-warning">
                                <i class="fas fa-calendar-check me-2"></i>
                                Gérer les présences
                    </a>
                        </div>
                        </div>
                    <div class="col-md-3 mb-2">
                        <div class="d-grid">
                            <a href="reports.php" class="btn btn-outline-info">
                                <i class="fas fa-chart-bar me-2"></i>
                                Rapports
                            </a>
                        </div>
                        </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<style>
.hover-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.1) !important;
    transition: all 0.3s ease;
}
</style>

<script>
// Graphique de répartition par niveau
<?php if (!empty($repartition_niveaux)): ?>
const niveauxCtx = document.getElementById('niveauxChart').getContext('2d');
const niveauxChart = new Chart(niveauxCtx, {
    type: 'doughnut',
    data: {
        labels: [<?php echo implode(',', array_map(function($n) { return "'" . ucfirst($n['niveau']) . "'"; }, $repartition_niveaux)); ?>],
        datasets: [{
            data: [<?php echo implode(',', array_column($repartition_niveaux, 'nombre')); ?>],
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
