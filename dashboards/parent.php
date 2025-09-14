<?php
/**
 * Dashboard Parent
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

// Vérifier que l'utilisateur est bien un parent
$user_nature = $_SESSION['user_nature'] ?? 'staff';
if ($user_nature !== 'parent') {
    redirectToDashboard($user_nature);
}

$page_title = getDashboardTitle('parent');
$dashboard_config = getDashboardConfig('parent');
$dashboard_modules = getDashboardModules('parent');
$dashboard_stats = getParentDashboardStats($database);

// Obtenir les informations de l'utilisateur connecté
$current_user = getCurrentUser($database);

// Obtenir les messages récents
$recent_messages = getParentRecentMessages($database);

include '../includes/header.php';
?>

<div class="container-fluid">
    <!-- En-tête du dashboard -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-body bg-<?php echo getDashboardColor('parent'); ?> text-white">
                    <div class="row align-items-center">
                        <div class="col-md-8">
                            <h1 class="card-title mb-2">
                                <i class="<?php echo getDashboardIcon('parent'); ?> me-3"></i>
                                <?php echo getDashboardTitle('parent'); ?>
                            </h1>
                            <p class="card-text mb-0"><?php echo getDashboardDescription('parent'); ?></p>
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
                            <i class="fas fa-star fa-2x text-primary"></i>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <h5 class="card-title mb-0">Notes Récentes</h5>
                            <h3 class="text-primary mb-0"><?php echo $dashboard_stats['child_notes'] ?? 0; ?></h3>
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
                            <i class="fas fa-calendar-check fa-2x text-success"></i>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <h5 class="card-title mb-0">Présences</h5>
                            <h3 class="text-success mb-0"><?php echo $dashboard_stats['child_attendance'] ?? 100; ?>%</h3>
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
                            <i class="fas fa-credit-card fa-2x text-info"></i>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <h5 class="card-title mb-0">Paiements</h5>
                            <h3 class="text-info mb-0">À jour</h3>
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
                            <i class="fas fa-envelope fa-2x text-warning"></i>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <h5 class="card-title mb-0">Messages</h5>
                            <h3 class="text-warning mb-0"><?php echo $dashboard_stats['unread_messages'] ?? 0; ?></h3>
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
                            <i class="<?php echo $module['icon']; ?> fa-2x text-<?php echo getDashboardColor('parent'); ?>"></i>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <h5 class="card-title mb-1"><?php echo $module['name']; ?></h5>
                            <p class="card-text text-muted small mb-0"><?php echo $module['description']; ?></p>
                        </div>
                    </div>
                    
                    <div class="d-grid">
                        <a href="<?php echo getModuleDefaultUrl($module_key); ?>" 
                           class="btn btn-outline-<?php echo getDashboardColor('parent'); ?>">
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
                <div class="card-header bg-<?php echo getDashboardColor('parent'); ?> text-white">
                    <h5 class="mb-0">
                        <i class="fas fa-bolt me-2"></i>
                        Actions Rapides
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-3 mb-2">
                            <a href="modules/evaluations/notes/student.php" class="btn btn-outline-primary w-100">
                                <i class="fas fa-star me-2"></i>
                                Notes de mon enfant
                            </a>
                        </div>
                        <div class="col-md-3 mb-2">
                            <a href="modules/recouvrement/paiements/index.php" class="btn btn-outline-success w-100">
                                <i class="fas fa-credit-card me-2"></i>
                                Paiements
                            </a>
                        </div>
                        <div class="col-md-3 mb-2">
                            <a href="modules/communication/messages/index.php" class="btn btn-outline-warning w-100">
                                <i class="fas fa-envelope me-2"></i>
                                Messages
                            </a>
                        </div>
                        <div class="col-md-3 mb-2">
                            <a href="modules/evaluations/bulletins/index.php" class="btn btn-outline-info w-100">
                                <i class="fas fa-file-alt me-2"></i>
                                Bulletins
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Informations sur l'enfant -->
    <div class="row mt-4">
        <div class="col-md-6">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-light">
                    <h5 class="mb-0">
                        <i class="fas fa-child me-2"></i>
                        Informations sur mon enfant
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-4">
                            <img src="../assets/images/default-avatar.svg" class="img-fluid rounded-circle" alt="Photo">
                        </div>
                        <div class="col-8">
                            <h6>Marie Kabila</h6>
                            <p class="text-muted mb-2">6ème A - Sciences</p>
                            <div class="row">
                                <div class="col-6">
                                    <small class="text-muted">Moyenne générale</small>
                                    <div class="h5 text-success">16.5/20</div>
                                </div>
                                <div class="col-6">
                                    <small class="text-muted">Rang</small>
                                    <div class="h5 text-primary">3ème/45</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-6">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-light">
                    <h5 class="mb-0">
                        <i class="fas fa-chart-line me-2"></i>
                        Évolution des Notes
                    </h5>
                </div>
                <div class="card-body">
                    <canvas id="notesChart" height="150"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Messages récents -->
    <div class="row mt-4">
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-light">
                    <h5 class="mb-0">
                        <i class="fas fa-envelope me-2"></i>
                        Messages Récents de l'École
                    </h5>
                </div>
                <div class="card-body">
                    <?php if (!empty($recent_messages)): ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($recent_messages as $message): ?>
                        <div class="list-group-item d-flex justify-content-between align-items-start">
                            <div class="ms-2 me-auto">
                                <div class="fw-bold"><?php echo htmlspecialchars($message['titre']); ?></div>
                                <p class="mb-1"><?php echo htmlspecialchars(substr($message['contenu'], 0, 100)) . (strlen($message['contenu']) > 100 ? '...' : ''); ?></p>
                                <small class="text-muted">Par <?php echo htmlspecialchars($message['auteur_nom'] ?? 'École'); ?> - <?php echo date('d/m/Y H:i', strtotime($message['created_at'])); ?></small>
                            </div>
                            <span class="badge bg-primary rounded-pill"><?php echo ucfirst($message['type_message'] ?? 'Message'); ?></span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php else: ?>
                    <div class="text-center text-muted">
                        <i class="fas fa-envelope-open fa-3x mb-3"></i>
                        <p>Aucun message récent</p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Chart.js pour le graphique des notes -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Graphique des notes
const ctx = document.getElementById('notesChart').getContext('2d');
new Chart(ctx, {
    type: 'line',
    data: {
        labels: ['Sept', 'Oct', 'Nov', 'Déc', 'Jan'],
        datasets: [{
            label: 'Moyenne générale',
            data: [15.2, 15.8, 16.1, 16.3, 16.5],
            borderColor: '#0d6efd',
            backgroundColor: 'rgba(13, 110, 253, 0.1)',
            tension: 0.4
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        scales: {
            y: {
                beginAtZero: false,
                min: 10,
                max: 20
            }
        }
    }
});
</script>

<?php include '../includes/footer.php'; ?>
