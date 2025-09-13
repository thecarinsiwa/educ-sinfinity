<?php
/**
 * Démonstration des fonctions UI avec permissions
 */

require_once 'config/config.php';
require_once 'config/database.php';
require_once 'includes/functions.php';
require_once 'includes/permissions-pages.php';
require_once 'includes/ui-permissions.php';

// Démarrer la session
session_start();

if (!isset($_SESSION['user_id'])) {
    echo "<h2>❌ Aucun utilisateur connecté</h2>";
    echo "<p>Veuillez vous connecter d'abord.</p>";
    echo "<a href='auth/login.php'>Se connecter</a>";
    exit;
}

$page_title = 'Démonstration UI Permissions';

include 'includes/header.php';
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
        <h1 class="h2">
            <i class="fas fa-shield-alt me-2"></i>
            Démonstration UI Permissions
        </h1>
    </div>

    <!-- Statistiques des permissions -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-chart-pie me-2"></i>
                        Vos Permissions
                    </h5>
                </div>
                <div class="card-body">
                    <?php 
                    $stats = getUIPermissionStats();
                    if ($stats): ?>
                        <div class="row">
                            <div class="col-md-3">
                                <div class="text-center">
                                    <h4 class="text-primary"><?php echo $stats['total_modules']; ?></h4>
                                    <small class="text-muted">Modules</small>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="text-center">
                                    <h4 class="text-success"><?php echo $stats['total_pages']; ?></h4>
                                    <small class="text-muted">Pages</small>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="text-center">
                                    <h4 class="text-info"><?php echo $stats['total_actions']; ?></h4>
                                    <small class="text-muted">Actions</small>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="text-center">
                                    <h4 class="text-warning"><?php echo $_SESSION['user_role'] ?? 'N/A'; ?></h4>
                                    <small class="text-muted">Rôle</small>
                                </div>
                            </div>
                        </div>
                    <?php else: ?>
                        <p class="text-muted">Aucune statistique disponible</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Test des boutons avec permissions -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-mouse-pointer me-2"></i>
                        Boutons avec Permissions
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <h6>Module Students</h6>
                            <div class="d-grid gap-2">
                                <?php echo generatePermissionLink(
                                    'modules/students/index.php',
                                    'Voir les élèves',
                                    'students',
                                    'index',
                                    'read',
                                    ['class' => 'btn btn-outline-primary', 'icon' => 'fas fa-eye']
                                ); ?>
                                
                                <?php echo generatePermissionLink(
                                    'modules/students/add.php',
                                    'Ajouter un élève',
                                    'students',
                                    'add',
                                    'create',
                                    ['class' => 'btn btn-outline-success', 'icon' => 'fas fa-plus']
                                ); ?>
                                
                                <?php echo generatePermissionLink(
                                    'modules/students/list.php',
                                    'Liste des élèves',
                                    'students',
                                    'list',
                                    'read',
                                    ['class' => 'btn btn-outline-info', 'icon' => 'fas fa-list']
                                ); ?>
                            </div>
                        </div>
                        
                        <div class="col-md-4 mb-3">
                            <h6>Module Finance</h6>
                            <div class="d-grid gap-2">
                                <?php echo generatePermissionLink(
                                    'modules/finance/index.php',
                                    'Tableau de bord finance',
                                    'finance',
                                    'index',
                                    'read',
                                    ['class' => 'btn btn-outline-primary', 'icon' => 'fas fa-chart-line']
                                ); ?>
                                
                                <?php echo generatePermissionLink(
                                    'modules/finance/payments/add.php',
                                    'Nouveau paiement',
                                    'finance',
                                    'payments/add',
                                    'create',
                                    ['class' => 'btn btn-outline-success', 'icon' => 'fas fa-plus']
                                ); ?>
                                
                                <?php echo generatePermissionLink(
                                    'modules/finance/fees/add.php',
                                    'Ajouter des frais',
                                    'finance',
                                    'fees/add',
                                    'create',
                                    ['class' => 'btn btn-outline-warning', 'icon' => 'fas fa-plus']
                                ); ?>
                            </div>
                        </div>
                        
                        <div class="col-md-4 mb-3">
                            <h6>Module Administration</h6>
                            <div class="d-grid gap-2">
                                <?php echo generatePermissionLink(
                                    'admin/users.php',
                                    'Gérer les utilisateurs',
                                    'users',
                                    'index',
                                    'read',
                                    ['class' => 'btn btn-outline-primary', 'icon' => 'fas fa-users']
                                ); ?>
                                
                                <?php echo generatePermissionLink(
                                    'admin/roles.php',
                                    'Gérer les rôles',
                                    'users',
                                    'roles/index',
                                    'read',
                                    ['class' => 'btn btn-outline-success', 'icon' => 'fas fa-user-tag']
                                ); ?>
                                
                                <?php echo generatePermissionLink(
                                    'admin/settings.php',
                                    'Paramètres système',
                                    'admin',
                                    'settings',
                                    'edit',
                                    ['class' => 'btn btn-outline-danger', 'icon' => 'fas fa-cog']
                                ); ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Test des boutons d'actions groupées -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-tasks me-2"></i>
                        Boutons d'Actions Groupées
                    </h5>
                </div>
                <div class="card-body">
                    <?php 
                    $actions = [
                        [
                            'module' => 'students',
                            'page' => 'add',
                            'action' => 'create',
                            'url' => 'modules/students/add.php',
                            'text' => 'Ajouter',
                            'icon' => 'fas fa-plus',
                            'class' => 'btn btn-success'
                        ],
                        [
                            'module' => 'students',
                            'page' => 'edit',
                            'action' => 'edit',
                            'url' => '#',
                            'text' => 'Modifier',
                            'icon' => 'fas fa-edit',
                            'class' => 'btn btn-primary'
                        ],
                        [
                            'module' => 'students',
                            'page' => 'delete',
                            'action' => 'delete',
                            'url' => '#',
                            'text' => 'Supprimer',
                            'icon' => 'fas fa-trash',
                            'class' => 'btn btn-danger'
                        ]
                    ];
                    
                    echo generateActionButtons($actions);
                    ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Test du menu déroulant -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-bars me-2"></i>
                        Menu Déroulant avec Permissions
                    </h5>
                </div>
                <div class="card-body">
                    <?php 
                    $dropdown_items = [
                        [
                            'module' => 'students',
                            'page' => 'add',
                            'action' => 'create',
                            'url' => 'modules/students/add.php',
                            'text' => 'Ajouter un élève',
                            'icon' => 'fas fa-user-plus'
                        ],
                        [
                            'module' => 'students',
                            'page' => 'list',
                            'action' => 'read',
                            'url' => 'modules/students/list.php',
                            'text' => 'Liste des élèves',
                            'icon' => 'fas fa-list'
                        ],
                        [
                            'module' => 'students',
                            'page' => 'attendance',
                            'action' => 'read',
                            'url' => 'modules/students/attendance/',
                            'text' => 'Gérer les présences',
                            'icon' => 'fas fa-calendar-check'
                        ],
                        [
                            'module' => 'finance',
                            'page' => 'payments/add',
                            'action' => 'create',
                            'url' => 'modules/finance/payments/add.php',
                            'text' => 'Nouveau paiement',
                            'icon' => 'fas fa-plus'
                        ]
                    ];
                    
                    echo generatePermissionDropdown('Actions disponibles', $dropdown_items);
                    ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Messages d'information -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-info-circle me-2"></i>
                        Messages d'Information
                    </h5>
                </div>
                <div class="card-body">
                    <?php echo generatePermissionMessage(
                        'students',
                        'add',
                        'create',
                        'Vous pouvez ajouter de nouveaux élèves à partir de cette interface.'
                    ); ?>
                    
                    <?php echo generatePermissionMessage(
                        'admin',
                        'settings',
                        'edit',
                        'Vous avez accès aux paramètres système.'
                    ); ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Classes CSS conditionnelles -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-palette me-2"></i>
                        Classes CSS Conditionnelles
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-4">
                            <div class="<?php echo getPermissionClasses('students', 'add', 'create', 'card border-primary'); ?>">
                                <div class="card-body text-center">
                                    <i class="fas fa-user-plus fa-2x mb-2"></i>
                                    <h6>Ajouter un élève</h6>
                                    <p class="small">Accès selon permissions</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="<?php echo getPermissionClasses('finance', 'payments/add', 'create', 'card border-success'); ?>">
                                <div class="card-body text-center">
                                    <i class="fas fa-plus fa-2x mb-2"></i>
                                    <h6>Nouveau paiement</h6>
                                    <p class="small">Accès selon permissions</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="<?php echo getPermissionClasses('admin', 'settings', 'edit', 'card border-danger'); ?>">
                                <div class="card-body text-center">
                                    <i class="fas fa-cog fa-2x mb-2"></i>
                                    <h6>Paramètres système</h6>
                                    <p class="small">Accès selon permissions</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Légende -->
    <div class="row">
        <div class="col-12">
            <div class="alert alert-info">
                <h6><i class="fas fa-info-circle me-2"></i>Légende</h6>
                <ul class="mb-0">
                    <li><strong>Boutons colorés :</strong> Vous avez les permissions nécessaires</li>
                    <li><strong>Boutons gris/désactivés :</strong> Vous n'avez pas les permissions</li>
                    <li><strong>Éléments avec bordure :</strong> Accès selon vos permissions</li>
                    <li><strong>Éléments grisés :</strong> Accès non autorisé</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
