<?php
/**
 * Dashboard Personnel Administratif
 * Système de gestion scolaire - République Démocratique du Congo
 */

require_once '../config/config.php';
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/dashboard-router.php';
require_once '../includes/module-urls.php';
require_once '../includes/dashboard-data-simple.php';

// Vérifier l'authentification
requireLogin();

// Vérifier que l'utilisateur est bien du personnel administratif
$user_nature = $_SESSION['user_nature'] ?? 'staff';
if ($user_nature !== 'staff') {
    redirectToDashboard($user_nature);
}

$page_title = getDashboardTitle('staff');
$dashboard_config = getDashboardConfig('staff');
$dashboard_modules = getDashboardModules('staff');
$dashboard_stats = getStaffDashboardStats($database);

// Obtenir les informations de l'utilisateur connecté
$current_user = getCurrentUser($database);

// Obtenir les activités récentes
$recent_activities = getStaffRecentActivities($database);

// Obtenir les données pour les graphiques
$payment_chart_data = getDashboardChartData($database, 'payments');

include '../includes/header.php';
?>

<div class="container-fluid">
    <!-- En-tête du dashboard -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-body bg-<?php echo getDashboardColor('staff'); ?> text-white">
                    <div class="row align-items-center">
                        <div class="col-md-8">
                            <h1 class="card-title mb-2">
                                <i class="<?php echo getDashboardIcon('staff'); ?> me-3"></i>
                                <?php echo getDashboardTitle('staff'); ?>
                            </h1>
                            <p class="card-text mb-0"><?php echo getDashboardDescription('staff'); ?></p>
                        </div>
                        <div class="col-md-4 text-end">
                            <div class="h4 mb-0">
                                Bonjour, <?php echo htmlspecialchars($current_user['nom'] ?? $current_user['username']); ?>!
                            </div>
                            <small class="opacity-75"><?php echo date('d/m/Y H:i'); ?></small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Statistiques rapides -->
    <div class="row mb-4">
        <div class="col-lg-3 col-md-6 mb-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-shrink-0">
                            <i class="fas fa-credit-card fa-2x text-primary"></i>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <h5 class="card-title mb-0">Paiements en Attente</h5>
                            <h3 class="text-primary mb-0"><?php echo $dashboard_stats['pending_payments'] ?? 0; ?></h3>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-lg-3 col-md-6 mb-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-shrink-0">
                            <i class="fas fa-user-plus fa-2x text-success"></i>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <h5 class="card-title mb-0">Nouvelles Candidatures</h5>
                            <h3 class="text-success mb-0"><?php echo $dashboard_stats['new_applications'] ?? 0; ?></h3>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-lg-3 col-md-6 mb-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-shrink-0">
                            <i class="fas fa-bullhorn fa-2x text-info"></i>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <h5 class="card-title mb-0">Campagnes Actives</h5>
                            <h3 class="text-info mb-0"><?php echo $dashboard_stats['active_campaigns'] ?? 0; ?></h3>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-lg-3 col-md-6 mb-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-shrink-0">
                            <i class="fas fa-chart-line fa-2x text-warning"></i>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <h5 class="card-title mb-0">Recouvrement</h5>
                            <h3 class="text-warning mb-0"><?php echo $dashboard_stats['recovery_rate'] ?? 0; ?>%</h3>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modules du dashboard -->
    <div class="row">
        <?php foreach ($dashboard_modules as $module_key => $module): ?>
        <div class="col-lg-4 col-md-6 mb-4">
            <div class="card border-0 shadow-sm h-100 hover-card">
                <div class="card-body">
                    <div class="d-flex align-items-center mb-3">
                        <div class="flex-shrink-0">
                            <i class="<?php echo $module['icon']; ?> fa-2x text-<?php echo getDashboardColor('staff'); ?>"></i>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <h5 class="card-title mb-1"><?php echo $module['name']; ?></h5>
                            <p class="card-text text-muted small mb-0"><?php echo $module['description']; ?></p>
                        </div>
                    </div>
                    
                    <div class="d-grid">
                        <a href="<?php echo getModuleDefaultUrl($module_key); ?>" 
                           class="btn btn-outline-<?php echo getDashboardColor('staff'); ?>">
                            <i class="fas fa-arrow-right me-2"></i>
                            Accéder au module
                        </a>
                    </div>
                    
                    <!-- Pages rapides -->
                    <?php if (isset($module['pages']) && !empty($module['pages'])): ?>
                    <div class="mt-3">
                        <small class="text-muted">Accès rapide :</small>
                        <div class="mt-2">
                            <?php foreach (array_slice($module['pages'], 0, 2) as $page_key => $page): ?>
                            <a href="<?php echo getModuleUrl($module_key, $page_key); ?>" 
                               class="btn btn-sm btn-light me-1 mb-1">
                                <i class="<?php echo $page['icon']; ?> me-1"></i>
                                <?php echo $page['name']; ?>
                            </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Actions rapides -->
    <div class="row mt-4">
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-<?php echo getDashboardColor('staff'); ?> text-white">
                    <h5 class="mb-0">
                        <i class="fas fa-bolt me-2"></i>
                        Actions Rapides
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-3 mb-2">
                            <a href="modules/finance/payments/add.php" class="btn btn-outline-primary w-100">
                                <i class="fas fa-credit-card me-2"></i>
                                Enregistrer Paiement
                            </a>
                        </div>
                        <div class="col-md-3 mb-2">
                            <a href="modules/admissions/applications/list.php" class="btn btn-outline-success w-100">
                                <i class="fas fa-file-alt me-2"></i>
                                Candidatures
                            </a>
                        </div>
                        <div class="col-md-3 mb-2">
                            <a href="modules/recouvrement/campaigns/add.php" class="btn btn-outline-warning w-100">
                                <i class="fas fa-bullhorn me-2"></i>
                                Nouvelle Campagne
                            </a>
                        </div>
                        <div class="col-md-3 mb-2">
                            <a href="modules/communication/messages/compose.php" class="btn btn-outline-info w-100">
                                <i class="fas fa-envelope me-2"></i>
                                Envoyer Message
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Tableaux de bord spécialisés -->
    <div class="row mt-4">
        <div class="col-md-6">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-light">
                    <h5 class="mb-0">
                        <i class="fas fa-chart-pie me-2"></i>
                        Répartition des Paiements
                    </h5>
                </div>
                <div class="card-body">
                    <canvas id="paymentsChart" height="200"></canvas>
                </div>
            </div>
        </div>
        
        <div class="col-md-6">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-light">
                    <h5 class="mb-0">
                        <i class="fas fa-calendar-alt me-2"></i>
                        Activités Récentes
                    </h5>
                </div>
                <div class="card-body">
                    <?php if (!empty($recent_activities)): ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($recent_activities as $activity): ?>
                        <div class="list-group-item d-flex justify-content-between align-items-start">
                            <div class="ms-2 me-auto">
                                <div class="fw-bold">
                                    <?php 
                                    switch($activity['type']) {
                                        case 'paiement':
                                            echo 'Nouveau paiement';
                                            break;
                                        case 'candidature':
                                            echo 'Nouvelle candidature';
                                            break;
                                        default:
                                            echo ucfirst($activity['type']);
                                    }
                                    ?>
                                </div>
                                <p class="mb-1"><?php echo htmlspecialchars($activity['description']); ?></p>
                                <small class="text-muted"><?php echo date('d/m/Y H:i', strtotime($activity['created_at'])); ?></small>
                            </div>
                            <span class="badge bg-success rounded-pill">
                                <?php if ($activity['montant']): ?>
                                    <?php echo number_format($activity['montant'], 0, ',', ' '); ?> USD
                                <?php else: ?>
                                    Nouveau
                                <?php endif; ?>
                            </span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php else: ?>
                    <div class="text-center text-muted">
                        <i class="fas fa-clock fa-3x mb-3"></i>
                        <p>Aucune activité récente</p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Tâches urgentes -->
    <div class="row mt-4">
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-light">
                    <h5 class="mb-0">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        Tâches Urgentes
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <div class="card border-warning">
                                <div class="card-body">
                                    <h6 class="card-title text-warning">
                                        <i class="fas fa-clock me-2"></i>
                                        Paiements en Retard
                                    </h6>
                                    <p class="card-text">15 familles n'ont pas encore payé les frais du 2ème trimestre.</p>
                                    <a href="modules/finance/payments/index.php" class="btn btn-warning btn-sm">
                                        Voir la liste
                                    </a>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4 mb-3">
                            <div class="card border-danger">
                                <div class="card-body">
                                    <h6 class="card-title text-danger">
                                        <i class="fas fa-user-plus me-2"></i>
                                        Candidatures en Attente
                                    </h6>
                                    <p class="card-text">8 nouvelles candidatures nécessitent une évaluation.</p>
                                    <a href="modules/admissions/applications/list.php" class="btn btn-danger btn-sm">
                                        Évaluer
                                    </a>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4 mb-3">
                            <div class="card border-info">
                                <div class="card-body">
                                    <h6 class="card-title text-info">
                                        <i class="fas fa-bullhorn me-2"></i>
                                        Campagne de Recouvrement
                                    </h6>
                                    <p class="card-text">Lancer une nouvelle campagne pour les paiements en retard.</p>
                                    <a href="modules/recouvrement/campaigns/add.php" class="btn btn-info btn-sm">
                                        Créer
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Chart.js pour le graphique des paiements -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Graphique des paiements
const ctx = document.getElementById('paymentsChart').getContext('2d');
const paymentData = <?php echo json_encode($payment_chart_data); ?>;
const labels = paymentData.map(item => item.status);
const values = paymentData.map(item => parseInt(item.count));

new Chart(ctx, {
    type: 'doughnut',
    data: {
        labels: labels,
        datasets: [{
            data: values,
            backgroundColor: [
                '#28a745',
                '#ffc107',
                '#dc3545',
                '#6c757d'
            ]
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
</script>

<?php include '../includes/footer.php'; ?>
