<?php
/**
 * Dashboard Élève
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

// Vérifier que l'utilisateur est bien un élève
$user_nature = $_SESSION['user_nature'] ?? 'staff';
if ($user_nature !== 'student') {
    redirectToDashboard($user_nature);
}

$page_title = getDashboardTitle('student');
$dashboard_config = getDashboardConfig('student');
$dashboard_modules = getDashboardModules('student');
$dashboard_stats = getStudentDashboardStats($database, $_SESSION['user_id']);

// Obtenir les informations de l'utilisateur connecté
$current_user = getCurrentUser($database);

// Obtenir l'emploi du temps d'aujourd'hui
$today_schedule = getStudentTodaySchedule($database, $_SESSION['user_id']);

include '../includes/header.php';
?>

<div class="container-fluid">
    <!-- En-tête du dashboard -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-body bg-<?php echo getDashboardColor('student'); ?> text-white">
                    <div class="row align-items-center">
                        <div class="col-md-8">
                            <h1 class="card-title mb-2">
                                <i class="<?php echo getDashboardIcon('student'); ?> me-3"></i>
                                <?php echo getDashboardTitle('student'); ?>
                            </h1>
                            <p class="card-text mb-0"><?php echo getDashboardDescription('student'); ?></p>
                        </div>
                        <div class="col-md-4 text-end">
                            <div class="h4 mb-0">
                                Salut, <?php echo htmlspecialchars($current_user['nom'] ?? $current_user['username']); ?>!
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
                            <h5 class="card-title mb-0">Mes Notes</h5>
                            <h3 class="text-primary mb-0"><?php echo $dashboard_stats['my_notes'] ?? 0; ?></h3>
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
                            <h3 class="text-success mb-0"><?php echo $dashboard_stats['attendance_percentage'] ?? 100; ?>%</h3>
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
                            <i class="fas fa-book fa-2x text-info"></i>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <h5 class="card-title mb-0">Livres Empruntés</h5>
                            <h3 class="text-info mb-0"><?php echo $dashboard_stats['borrowed_books'] ?? 0; ?></h3>
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
                            <i class="fas fa-trophy fa-2x text-warning"></i>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <h5 class="card-title mb-0">Moyenne</h5>
                            <h3 class="text-warning mb-0"><?php echo $dashboard_stats['average_grade'] ?? 'N/A'; ?></h3>
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
                            <i class="<?php echo $module['icon']; ?> fa-2x text-<?php echo getDashboardColor('student'); ?>"></i>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <h5 class="card-title mb-1"><?php echo $module['name']; ?></h5>
                            <p class="card-text text-muted small mb-0"><?php echo $module['description']; ?></p>
                        </div>
                    </div>
                    
                    <div class="d-grid">
                        <a href="<?php echo getModuleDefaultUrl($module_key); ?>" 
                           class="btn btn-outline-<?php echo getDashboardColor('student'); ?>">
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
                <div class="card-header bg-<?php echo getDashboardColor('student'); ?> text-white">
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
                                Mes Notes
                            </a>
                        </div>
                        <div class="col-md-3 mb-2">
                            <a href="modules/academic/schedule/index.php" class="btn btn-outline-success w-100">
                                <i class="fas fa-calendar me-2"></i>
                                Mon Emploi du Temps
                            </a>
                        </div>
                        <div class="col-md-3 mb-2">
                            <a href="modules/cartes_eleves/index.php" class="btn btn-outline-warning w-100">
                                <i class="fas fa-id-card me-2"></i>
                                Ma Carte
                            </a>
                        </div>
                        <div class="col-md-3 mb-2">
                            <a href="modules/library/loans/index.php" class="btn btn-outline-info w-100">
                                <i class="fas fa-book me-2"></i>
                                Mes Emprunts
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Emploi du temps d'aujourd'hui -->
    <div class="row mt-4">
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-light">
                    <h5 class="mb-0">
                        <i class="fas fa-calendar-day me-2"></i>
                        Mon Emploi du Temps - Aujourd'hui
                    </h5>
                </div>
                <div class="card-body">
                    <?php if (!empty($today_schedule)): ?>
                    <div class="row">
                        <?php 
                        $morning_courses = [];
                        $afternoon_courses = [];
                        
                        foreach ($today_schedule as $course) {
                            $hour = (int)substr($course['heure_debut'], 0, 2);
                            if ($hour < 12) {
                                $morning_courses[] = $course;
                            } else {
                                $afternoon_courses[] = $course;
                            }
                        }
                        ?>
                        
                        <div class="col-md-6">
                            <h6 class="text-primary">Cours du Matin</h6>
                            <?php if (!empty($morning_courses)): ?>
                            <ul class="list-group list-group-flush">
                                <?php foreach ($morning_courses as $course): ?>
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    <div>
                                        <strong><?php echo $course['heure_debut']; ?> - <?php echo $course['heure_fin']; ?></strong><br>
                                        <small class="text-muted"><?php echo $course['matiere_nom'] ?? 'Matière'; ?> - <?php echo $course['enseignant_nom'] ?? 'Enseignant'; ?></small>
                                    </div>
                                    <span class="badge bg-secondary">Programmé</span>
                                </li>
                                <?php endforeach; ?>
                            </ul>
                            <?php else: ?>
                            <p class="text-muted">Aucun cours prévu ce matin</p>
                            <?php endif; ?>
                        </div>
                        
                        <div class="col-md-6">
                            <h6 class="text-primary">Cours de l'Après-midi</h6>
                            <?php if (!empty($afternoon_courses)): ?>
                            <ul class="list-group list-group-flush">
                                <?php foreach ($afternoon_courses as $course): ?>
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    <div>
                                        <strong><?php echo $course['heure_debut']; ?> - <?php echo $course['heure_fin']; ?></strong><br>
                                        <small class="text-muted"><?php echo $course['matiere_nom'] ?? 'Matière'; ?> - <?php echo $course['enseignant_nom'] ?? 'Enseignant'; ?></small>
                                    </div>
                                    <span class="badge bg-secondary">Programmé</span>
                                </li>
                                <?php endforeach; ?>
                            </ul>
                            <?php else: ?>
                            <p class="text-muted">Aucun cours prévu cet après-midi</p>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php else: ?>
                    <div class="text-center text-muted">
                        <i class="fas fa-calendar-times fa-3x mb-3"></i>
                        <p>Aucun cours prévu aujourd'hui</p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
