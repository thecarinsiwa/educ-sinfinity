<?php
/**
 * Dashboard Administrateur
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

// Vérifier que l'utilisateur est bien un admin
$user_nature = $_SESSION['user_nature'] ?? 'staff';
if ($user_nature !== 'admin') {
    redirectToDashboard($user_nature);
}

$page_title = getDashboardTitle('admin');
$dashboard_config = getDashboardConfig('admin');
$dashboard_modules = getDashboardModules('admin');
$dashboard_stats = getAdminDashboardStats($database);

// Obtenir les informations de l'utilisateur connecté
$current_user = getCurrentUser($database);

include '../includes/header.php';
?>

<div class="container-fluid">
    <!-- En-tête du dashboard -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-body bg-<?php echo getDashboardColor('admin'); ?> text-white">
                    <div class="row align-items-center">
                        <div class="col-md-8">
                            <h1 class="card-title mb-2">
                                <i class="<?php echo getDashboardIcon('admin'); ?> me-3"></i>
                                <?php echo getDashboardTitle('admin'); ?>
                            </h1>
                            <p class="card-text mb-0"><?php echo getDashboardDescription('admin'); ?></p>
                        </div>
                        <div class="col-md-4 text-end">
                            <div class="h4 mb-0">
                                Bienvenue, <?php echo htmlspecialchars($current_user['nom'] ?? $current_user['username']); ?>!
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
                            <i class="fas fa-users fa-2x text-primary"></i>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <h5 class="card-title mb-0">Utilisateurs</h5>
                            <h3 class="text-primary mb-0"><?php echo $dashboard_stats['total_users'] ?? 0; ?></h3>
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
                            <i class="fas fa-user-tie fa-2x text-success"></i>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <h5 class="card-title mb-0">Personnel</h5>
                            <h3 class="text-success mb-0"><?php echo $dashboard_stats['total_personnel'] ?? 0; ?></h3>
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
                            <i class="fas fa-user-graduate fa-2x text-info"></i>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <h5 class="card-title mb-0">Élèves</h5>
                            <h3 class="text-info mb-0"><?php echo $dashboard_stats['total_students'] ?? 0; ?></h3>
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
                            <h5 class="card-title mb-0">Classes</h5>
                            <h3 class="text-warning mb-0"><?php echo $dashboard_stats['total_classes'] ?? 0; ?></h3>
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
                            <i class="<?php echo $module['icon']; ?> fa-2x text-<?php echo getDashboardColor('admin'); ?>"></i>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <h5 class="card-title mb-1"><?php echo $module['name']; ?></h5>
                            <p class="card-text text-muted small mb-0"><?php echo $module['description']; ?></p>
                        </div>
                    </div>
                    
                    <div class="d-grid">
                        <a href="<?php echo getModuleDefaultUrl($module_key); ?>" 
                           class="btn btn-outline-<?php echo getDashboardColor('admin'); ?>">
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
                <div class="card-header bg-<?php echo getDashboardColor('admin'); ?> text-white">
                    <h5 class="mb-0">
                        <i class="fas fa-bolt me-2"></i>
                        Actions Rapides
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-3 mb-2">
                            <a href="modules/users/add.php" class="btn btn-outline-primary w-100">
                                <i class="fas fa-user-plus me-2"></i>
                                Nouvel Utilisateur
                            </a>
                        </div>
                        <div class="col-md-3 mb-2">
                            <a href="modules/personnel/add.php" class="btn btn-outline-success w-100">
                                <i class="fas fa-user-tie me-2"></i>
                                Nouveau Personnel
                            </a>
                        </div>
                        <div class="col-md-3 mb-2">
                            <a href="modules/finance/fees/add.php" class="btn btn-outline-warning w-100">
                                <i class="fas fa-receipt me-2"></i>
                                Nouveaux Frais
                            </a>
                        </div>
                        <div class="col-md-3 mb-2">
                            <a href="modules/reports/index.php" class="btn btn-outline-info w-100">
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

<?php include '../includes/footer.php'; ?>
