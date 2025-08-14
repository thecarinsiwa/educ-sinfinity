<?php
/**
 * Module Rapports et Statistiques - Page principale
 * Application de gestion scolaire - République Démocratique du Congo
 */

require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../includes/functions.php';

// Vérifier l'authentification
requireLogin();

$page_title = 'Rapports et Statistiques';

// Obtenir l'année scolaire actuelle
$current_year = getCurrentAcademicYear();

// Statistiques générales de l'école
$stats = [];

// Effectifs
$stmt = $database->query(
    "SELECT COUNT(*) as total FROM inscriptions WHERE status = 'inscrit' AND annee_scolaire_id = ?",
    [$current_year['id'] ?? 0]
);
$stats['total_eleves'] = $stmt->fetch()['total'];

$stmt = $database->query(
    "SELECT COUNT(*) as total FROM personnel WHERE status = 'actif'"
);
$stats['total_personnel'] = $stmt->fetch()['total'];

$stmt = $database->query(
    "SELECT COUNT(*) as total FROM classes WHERE annee_scolaire_id = ?",
    [$current_year['id'] ?? 0]
);
$stats['total_classes'] = $stmt->fetch()['total'];

// Finances
$stmt = $database->query(
    "SELECT SUM(montant) as total FROM paiements WHERE status = 'valide' AND annee_scolaire_id = ?",
    [$current_year['id'] ?? 0]
);
$stats['recettes_totales'] = $stmt->fetch()['total'] ?? 0;

// Évaluations
$stmt = $database->query(
    "SELECT COUNT(*) as total FROM evaluations WHERE annee_scolaire_id = ?",
    [$current_year['id'] ?? 0]
);
$stats['total_evaluations'] = $stmt->fetch()['total'];

$stmt = $database->query(
    "SELECT AVG(note) as moyenne FROM notes n 
     JOIN evaluations e ON n.evaluation_id = e.id 
     WHERE e.annee_scolaire_id = ?",
    [$current_year['id'] ?? 0]
);
$stats['moyenne_generale'] = round($stmt->fetch()['moyenne'] ?? 0, 2);

// Évolution mensuelle des inscriptions
$inscriptions_mensuelles = $database->query(
    "SELECT MONTH(created_at) as mois, COUNT(*) as nombre
     FROM inscriptions 
     WHERE YEAR(created_at) = YEAR(CURDATE()) AND annee_scolaire_id = ?
     GROUP BY MONTH(created_at)
     ORDER BY mois",
    [$current_year['id'] ?? 0]
)->fetchAll();

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

// Top 5 des classes les plus nombreuses
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

// Évolution des recettes (6 derniers mois)
$recettes_mensuelles = $database->query(
    "SELECT DATE_FORMAT(date_paiement, '%Y-%m') as mois, SUM(montant) as montant
     FROM paiements 
     WHERE status = 'valide' 
     AND date_paiement >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
     AND annee_scolaire_id = ?
     GROUP BY DATE_FORMAT(date_paiement, '%Y-%m')
     ORDER BY mois",
    [$current_year['id'] ?? 0]
)->fetchAll();

// Statistiques par genre
$stats_genre = $database->query(
    "SELECT e.sexe, COUNT(*) as nombre
     FROM eleves e
     JOIN inscriptions i ON e.id = i.eleve_id
     WHERE i.status = 'inscrit' AND i.annee_scolaire_id = ?
     GROUP BY e.sexe",
    [$current_year['id'] ?? 0]
)->fetchAll();

include '../../includes/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">
        <i class="fas fa-chart-bar me-2"></i>
        Rapports et Statistiques
    </h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <div class="btn-group me-2">
            <button type="button" class="btn btn-outline-secondary">
                <i class="fas fa-calendar-alt me-1"></i>
                <?php echo $current_year['annee'] ?? 'Aucune année active'; ?>
            </button>
        </div>
        <div class="btn-group me-2">
            <button type="button" class="btn btn-primary dropdown-toggle" data-bs-toggle="dropdown">
                <i class="fas fa-file-export me-1"></i>
                Exporter
            </button>
            <ul class="dropdown-menu">
                <li><a class="dropdown-item" href="exports/dashboard.php?format=pdf">
                    <i class="fas fa-file-pdf me-2"></i>Tableau de bord PDF
                </a></li>
                <li><a class="dropdown-item" href="exports/dashboard.php?format=excel">
                    <i class="fas fa-file-excel me-2"></i>Données Excel
                </a></li>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item" href="exports/custom.php">
                    <i class="fas fa-cog me-2"></i>Export personnalisé
                </a></li>
            </ul>
        </div>
        <div class="btn-group">
            <button type="button" class="btn btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">
                <i class="fas fa-cog me-1"></i>
                Paramètres
            </button>
            <ul class="dropdown-menu">
                <li><a class="dropdown-item" href="settings/dashboard.php">
                    <i class="fas fa-tachometer-alt me-2"></i>Personnaliser tableau de bord
                </a></li>
                <li><a class="dropdown-item" href="settings/reports.php">
                    <i class="fas fa-file-alt me-2"></i>Configuration rapports
                </a></li>
                <li><a class="dropdown-item" href="settings/alerts.php">
                    <i class="fas fa-bell me-2"></i>Alertes automatiques
                </a></li>
            </ul>
        </div>
    </div>
</div>

<!-- Statistiques rapides -->
<div class="row mb-4">
    <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
        <div class="card text-white bg-primary">
            <div class="card-body text-center">
                <i class="fas fa-users fa-2x mb-2"></i>
                <h4><?php echo $stats['total_eleves']; ?></h4>
                <p class="mb-0 small">Élèves inscrits</p>
            </div>
        </div>
    </div>
    <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
        <div class="card text-white bg-success">
            <div class="card-body text-center">
                <i class="fas fa-chalkboard-teacher fa-2x mb-2"></i>
                <h4><?php echo $stats['total_personnel']; ?></h4>
                <p class="mb-0 small">Personnel</p>
            </div>
        </div>
    </div>
    <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
        <div class="card text-white bg-info">
            <div class="card-body text-center">
                <i class="fas fa-school fa-2x mb-2"></i>
                <h4><?php echo $stats['total_classes']; ?></h4>
                <p class="mb-0 small">Classes</p>
            </div>
        </div>
    </div>
    <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
        <div class="card text-white bg-warning">
            <div class="card-body text-center">
                <i class="fas fa-dollar-sign fa-2x mb-2"></i>
                <h4><?php echo formatMoney($stats['recettes_totales']); ?></h4>
                <p class="mb-0 small">Recettes</p>
            </div>
        </div>
    </div>
    <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
        <div class="card text-white bg-secondary">
            <div class="card-body text-center">
                <i class="fas fa-clipboard-list fa-2x mb-2"></i>
                <h4><?php echo $stats['total_evaluations']; ?></h4>
                <p class="mb-0 small">Évaluations</p>
            </div>
        </div>
    </div>
    <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
        <div class="card text-white bg-dark">
            <div class="card-body text-center">
                <i class="fas fa-star fa-2x mb-2"></i>
                <h4><?php echo $stats['moyenne_generale']; ?>/20</h4>
                <p class="mb-0 small">Moyenne</p>
            </div>
        </div>
    </div>
</div>

<!-- Modules de rapports -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-th-large me-2"></i>
                    Modules de rapports
                </h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-lg-3 col-md-6 mb-3">
                        <a href="academic/" class="text-decoration-none">
                            <div class="card h-100 border-0 shadow-sm hover-card">
                                <div class="card-body text-center">
                                    <i class="fas fa-graduation-cap fa-3x text-primary mb-3"></i>
                                    <h5 class="card-title">Rapports Académiques</h5>
                                    <p class="card-text text-muted">
                                        Résultats, bulletins, statistiques scolaires
                                    </p>
                                </div>
                            </div>
                        </a>
                    </div>
                    
                    <div class="col-lg-3 col-md-6 mb-3">
                        <a href="financial/" class="text-decoration-none">
                            <div class="card h-100 border-0 shadow-sm hover-card">
                                <div class="card-body text-center">
                                    <i class="fas fa-chart-line fa-3x text-success mb-3"></i>
                                    <h5 class="card-title">Rapports Financiers</h5>
                                    <p class="card-text text-muted">
                                        Recettes, dépenses, bilans financiers
                                    </p>
                                </div>
                            </div>
                        </a>
                    </div>
                    
                    <div class="col-lg-3 col-md-6 mb-3">
                        <a href="administrative/" class="text-decoration-none">
                            <div class="card h-100 border-0 shadow-sm hover-card">
                                <div class="card-body text-center">
                                    <i class="fas fa-users fa-3x text-info mb-3"></i>
                                    <h5 class="card-title">Rapports Administratifs</h5>
                                    <p class="card-text text-muted">
                                        Effectifs, personnel, inscriptions
                                    </p>
                                </div>
                            </div>
                        </a>
                    </div>
                    
                    <div class="col-lg-3 col-md-6 mb-3">
                        <a href="custom/" class="text-decoration-none">
                            <div class="card h-100 border-0 shadow-sm hover-card">
                                <div class="card-body text-center">
                                    <i class="fas fa-cogs fa-3x text-warning mb-3"></i>
                                    <h5 class="card-title">Rapports Personnalisés</h5>
                                    <p class="card-text text-muted">
                                        Créer des rapports sur mesure
                                    </p>
                                </div>
                            </div>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Graphiques et analyses -->
<div class="row">
    <div class="col-lg-8">
        <!-- Évolution des recettes -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-chart-line me-2"></i>
                    Évolution des recettes (6 derniers mois)
                </h5>
            </div>
            <div class="card-body">
                <?php if (!empty($recettes_mensuelles)): ?>
                    <canvas id="recettesChart" width="100%" height="300"></canvas>
                <?php else: ?>
                    <div class="text-center py-4">
                        <i class="fas fa-chart-line fa-3x text-muted mb-3"></i>
                        <p class="text-muted">Aucune donnée de recettes disponible</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Répartition par niveau -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-chart-pie me-2"></i>
                    Répartition des élèves par niveau
                </h5>
            </div>
            <div class="card-body">
                <?php if (!empty($repartition_niveaux)): ?>
                    <div class="row">
                        <div class="col-md-6">
                            <canvas id="niveauxChart" width="100%" height="300"></canvas>
                        </div>
                        <div class="col-md-6">
                            <div class="mt-3">
                                <?php foreach ($repartition_niveaux as $niveau): ?>
                                    <div class="d-flex justify-content-between align-items-center mb-3">
                                        <div>
                                            <span class="badge bg-<?php 
                                                echo $niveau['niveau'] === 'maternelle' ? 'warning' : 
                                                    ($niveau['niveau'] === 'primaire' ? 'success' : 'primary'); 
                                            ?>">
                                                <?php echo ucfirst($niveau['niveau']); ?>
                                            </span>
                                        </div>
                                        <div>
                                            <strong><?php echo $niveau['nombre']; ?> élèves</strong>
                                            <br><small class="text-muted">
                                                <?php echo $stats['total_eleves'] > 0 ? round(($niveau['nombre'] / $stats['total_eleves']) * 100, 1) : 0; ?>%
                                            </small>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="text-center py-4">
                        <i class="fas fa-chart-pie fa-3x text-muted mb-3"></i>
                        <p class="text-muted">Aucune donnée d'effectifs disponible</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <div class="col-lg-4">
        <!-- Classes les plus nombreuses -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-trophy me-2"></i>
                    Classes les plus nombreuses
                </h5>
            </div>
            <div class="card-body">
                <?php if (!empty($classes_nombreuses)): ?>
                    <?php foreach ($classes_nombreuses as $index => $classe): ?>
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <div>
                                <span class="badge bg-<?php echo $index < 3 ? 'warning' : 'secondary'; ?> me-2">
                                    <?php echo $index + 1; ?>
                                </span>
                                <strong><?php echo htmlspecialchars($classe['nom']); ?></strong>
                                <br><small class="text-muted">
                                    <?php echo ucfirst($classe['niveau']); ?>
                                </small>
                            </div>
                            <span class="badge bg-primary fs-6">
                                <?php echo $classe['effectif']; ?>
                            </span>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p class="text-muted text-center">Aucune classe configurée</p>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Répartition par genre -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-venus-mars me-2"></i>
                    Répartition par genre
                </h5>
            </div>
            <div class="card-body">
                <?php if (!empty($stats_genre)): ?>
                    <?php foreach ($stats_genre as $genre): ?>
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <div>
                                <i class="fas fa-<?php echo $genre['sexe'] === 'M' ? 'mars' : 'venus'; ?> me-2 text-<?php echo $genre['sexe'] === 'M' ? 'primary' : 'danger'; ?>"></i>
                                <?php echo $genre['sexe'] === 'M' ? 'Garçons' : 'Filles'; ?>
                            </div>
                            <div class="text-end">
                                <strong><?php echo $genre['nombre']; ?></strong>
                                <br><small class="text-muted">
                                    <?php echo $stats['total_eleves'] > 0 ? round(($genre['nombre'] / $stats['total_eleves']) * 100, 1) : 0; ?>%
                                </small>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p class="text-muted text-center">Aucune donnée disponible</p>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Actions rapides -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-bolt me-2"></i>
                    Actions rapides
                </h5>
            </div>
            <div class="card-body">
                <div class="d-grid gap-2">
                    <a href="academic/class-performance.php" class="btn btn-outline-primary">
                        <i class="fas fa-chart-bar me-2"></i>
                        Performance des classes
                    </a>
                    <a href="financial/monthly-report.php" class="btn btn-outline-success">
                        <i class="fas fa-calendar-alt me-2"></i>
                        Rapport mensuel
                    </a>
                    <a href="administrative/enrollment-stats.php" class="btn btn-outline-info">
                        <i class="fas fa-users me-2"></i>
                        Statistiques d'inscription
                    </a>
                    <a href="exports/annual-report.php" class="btn btn-outline-warning">
                        <i class="fas fa-file-alt me-2"></i>
                        Rapport annuel
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Graphique des recettes
<?php if (!empty($recettes_mensuelles)): ?>
const recettesCtx = document.getElementById('recettesChart').getContext('2d');
const recettesChart = new Chart(recettesCtx, {
    type: 'line',
    data: {
        labels: [<?php echo implode(',', array_map(function($r) { return "'" . date('M Y', strtotime($r['mois'] . '-01')) . "'"; }, $recettes_mensuelles)); ?>],
        datasets: [{
            label: 'Recettes (FC)',
            data: [<?php echo implode(',', array_column($recettes_mensuelles, 'montant')); ?>],
            borderColor: '#28a745',
            backgroundColor: 'rgba(40, 167, 69, 0.1)',
            tension: 0.4,
            fill: true
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                display: false
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    callback: function(value) {
                        return new Intl.NumberFormat('fr-CD', {
                            style: 'currency',
                            currency: 'CDF'
                        }).format(value);
                    }
                }
            }
        }
    }
});
<?php endif; ?>

// Graphique des niveaux
<?php if (!empty($repartition_niveaux)): ?>
const niveauxCtx = document.getElementById('niveauxChart').getContext('2d');
const niveauxChart = new Chart(niveauxCtx, {
    type: 'doughnut',
    data: {
        labels: [<?php echo implode(',', array_map(function($n) { return "'" . ucfirst($n['niveau']) . "'"; }, $repartition_niveaux)); ?>],
        datasets: [{
            data: [<?php echo implode(',', array_column($repartition_niveaux, 'nombre')); ?>],
            backgroundColor: ['#ffc107', '#28a745', '#007bff'],
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

<style>
.hover-card {
    transition: transform 0.2s ease-in-out;
}

.hover-card:hover {
    transform: translateY(-5px);
}
</style>

<?php include '../../includes/footer.php'; ?>
