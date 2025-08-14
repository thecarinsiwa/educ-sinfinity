<?php
/**
 * Module Gestion des Utilisateurs - Historique des actions
 * Application de gestion scolaire - République Démocratique du Congo
 */

require_once '../../../config/config.php';
require_once '../../../config/database.php';
require_once '../../../includes/functions.php';

// Vérifier l'authentification et les permissions
requireLogin();
if (!checkPermission('admin') && !checkPermission('users_view')) {
    showMessage('error', 'Accès refusé à ce module.');
    redirectTo('../index.php');
}

$page_title = 'Historique des Actions Utilisateurs';

// Paramètres de filtrage
$user_filter = (int)($_GET['user_id'] ?? 0);
$action_filter = sanitizeInput($_GET['action'] ?? '');
$module_filter = sanitizeInput($_GET['module'] ?? '');
$date_from = sanitizeInput($_GET['date_from'] ?? '');
$date_to = sanitizeInput($_GET['date_to'] ?? '');
$search = sanitizeInput($_GET['search'] ?? '');

// Construction de la requête avec filtres
$where_conditions = ["1=1"];
$params = [];

if ($user_filter) {
    $where_conditions[] = "ual.user_id = ?";
    $params[] = $user_filter;
}

if ($action_filter) {
    $where_conditions[] = "ual.action = ?";
    $params[] = $action_filter;
}

if ($module_filter) {
    $where_conditions[] = "ual.module = ?";
    $params[] = $module_filter;
}

if ($date_from) {
    $where_conditions[] = "DATE(ual.created_at) >= ?";
    $params[] = $date_from;
}

if ($date_to) {
    $where_conditions[] = "DATE(ual.created_at) <= ?";
    $params[] = $date_to;
}

if ($search) {
    $where_conditions[] = "(ual.details LIKE ? OR u.username LIKE ? OR u.nom LIKE ? OR u.prenom LIKE ?)";
    $search_param = "%$search%";
    $params = array_merge($params, [$search_param, $search_param, $search_param, $search_param]);
}

$where_clause = implode(' AND ', $where_conditions);

// Récupérer les actions avec pagination
$page = (int)($_GET['page'] ?? 1);
$per_page = 50;
$offset = ($page - 1) * $per_page;

$actions = $database->query(
    "SELECT ual.*, u.username, u.nom, u.prenom, u.role
     FROM user_actions_log ual
     JOIN users u ON ual.user_id = u.id
     WHERE $where_clause
     ORDER BY ual.created_at DESC
     LIMIT $per_page OFFSET $offset",
    $params
)->fetchAll();

// Compter le total pour la pagination
$total_stmt = $database->query(
    "SELECT COUNT(*) as total 
     FROM user_actions_log ual
     JOIN users u ON ual.user_id = u.id
     WHERE $where_clause",
    $params
);
$total_records = $total_stmt->fetch()['total'];
$total_pages = ceil($total_records / $per_page);

// Récupérer les données pour les filtres
$users = $database->query(
    "SELECT id, username, nom, prenom FROM users ORDER BY nom, prenom"
)->fetchAll();

$actions_list = $database->query(
    "SELECT DISTINCT action FROM user_actions_log ORDER BY action"
)->fetchAll();

$modules_list = $database->query(
    "SELECT DISTINCT module FROM user_actions_log ORDER BY module"
)->fetchAll();

// Statistiques
$stats = [];
$stats['total_actions'] = $database->query("SELECT COUNT(*) as total FROM user_actions_log")->fetch()['total'];
$stats['today_actions'] = $database->query(
    "SELECT COUNT(*) as total FROM user_actions_log WHERE DATE(created_at) = CURDATE()"
)->fetch()['total'];
$stats['week_actions'] = $database->query(
    "SELECT COUNT(*) as total FROM user_actions_log WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)"
)->fetch()['total'];

// Utilisateur spécifique si filtré
$filtered_user = null;
if ($user_filter) {
    $filtered_user = $database->query(
        "SELECT username, nom, prenom FROM users WHERE id = ?",
        [$user_filter]
    )->fetch();
}

include '../../../includes/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">
        <i class="fas fa-history me-2"></i>
        Historique des Actions
        <?php if ($filtered_user): ?>
            <small class="text-muted">- <?php echo htmlspecialchars($filtered_user['nom'] . ' ' . $filtered_user['prenom']); ?></small>
        <?php endif; ?>
    </h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <div class="btn-group me-2">
            <a href="../index.php" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-1"></i>
                Retour
            </a>
        </div>
        <div class="btn-group">
            <button type="button" class="btn btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">
                <i class="fas fa-tools me-1"></i>
                Outils
            </button>
            <ul class="dropdown-menu">
                <li><a class="dropdown-item" href="export.php?<?php echo http_build_query($_GET); ?>">
                    <i class="fas fa-file-excel me-2"></i>Exporter Excel
                </a></li>
                <li><a class="dropdown-item" href="report.php?<?php echo http_build_query($_GET); ?>">
                    <i class="fas fa-file-pdf me-2"></i>Rapport PDF
                </a></li>
                <?php if (checkPermission('admin')): ?>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item text-danger" href="cleanup.php" 
                           onclick="return confirm('Nettoyer les anciens logs (plus de 6 mois) ?')">
                        <i class="fas fa-trash me-2"></i>Nettoyer les anciens logs
                    </a></li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</div>

<!-- Statistiques -->
<div class="row mb-4">
    <div class="col-lg-4 col-md-6 mb-3">
        <div class="card text-white bg-primary">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h4><?php echo number_format($stats['total_actions']); ?></h4>
                        <p class="mb-0">Total actions</p>
                    </div>
                    <div class="align-self-center">
                        <i class="fas fa-history fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-4 col-md-6 mb-3">
        <div class="card text-white bg-success">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h4><?php echo $stats['today_actions']; ?></h4>
                        <p class="mb-0">Actions aujourd'hui</p>
                    </div>
                    <div class="align-self-center">
                        <i class="fas fa-calendar-day fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-4 col-md-6 mb-3">
        <div class="card text-white bg-info">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h4><?php echo $stats['week_actions']; ?></h4>
                        <p class="mb-0">Actions cette semaine</p>
                    </div>
                    <div class="align-self-center">
                        <i class="fas fa-calendar-week fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Filtres -->
<div class="card mb-4">
    <div class="card-header">
        <h6 class="mb-0">
            <i class="fas fa-filter me-2"></i>
            Filtres de recherche
        </h6>
    </div>
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-md-3">
                <label for="user_id" class="form-label">Utilisateur</label>
                <select class="form-select" id="user_id" name="user_id">
                    <option value="">Tous les utilisateurs</option>
                    <?php foreach ($users as $user): ?>
                        <option value="<?php echo $user['id']; ?>" 
                                <?php echo $user_filter == $user['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($user['nom'] . ' ' . $user['prenom'] . ' (@' . $user['username'] . ')'); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label for="action" class="form-label">Action</label>
                <select class="form-select" id="action" name="action">
                    <option value="">Toutes les actions</option>
                    <?php foreach ($actions_list as $action): ?>
                        <option value="<?php echo $action['action']; ?>" 
                                <?php echo $action_filter === $action['action'] ? 'selected' : ''; ?>>
                            <?php echo getActionLabel($action['action']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label for="module" class="form-label">Module</label>
                <select class="form-select" id="module" name="module">
                    <option value="">Tous les modules</option>
                    <?php foreach ($modules_list as $module): ?>
                        <option value="<?php echo $module['module']; ?>" 
                                <?php echo $module_filter === $module['module'] ? 'selected' : ''; ?>>
                            <?php echo ucfirst($module['module']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label for="date_from" class="form-label">Du</label>
                <input type="date" class="form-control" id="date_from" name="date_from" 
                       value="<?php echo htmlspecialchars($date_from); ?>">
            </div>
            <div class="col-md-2">
                <label for="date_to" class="form-label">Au</label>
                <input type="date" class="form-control" id="date_to" name="date_to" 
                       value="<?php echo htmlspecialchars($date_to); ?>">
            </div>
            <div class="col-md-1">
                <label class="form-label">&nbsp;</label>
                <div class="d-grid">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search"></i>
                    </button>
                </div>
            </div>
        </form>
        
        <div class="row mt-3">
            <div class="col-md-11">
                <input type="text" class="form-control" name="search"
                       value="<?php echo htmlspecialchars($search); ?>"
                       placeholder="Rechercher dans les détails des actions..." form="searchForm">
            </div>
            <div class="col-md-1">
                <button type="submit" class="btn btn-outline-secondary w-100" form="searchForm">
                    <i class="fas fa-search"></i>
                </button>
            </div>
        </div>
        </form>

        <form method="GET" id="searchForm" style="display: none;">
            <?php foreach ($_GET as $key => $value): ?>
                <?php if ($key !== 'search'): ?>
                    <input type="hidden" name="<?php echo htmlspecialchars($key); ?>" value="<?php echo htmlspecialchars($value); ?>">
                <?php endif; ?>
            <?php endforeach; ?>
    </div>
</div>

<!-- Liste des actions -->
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">
            <i class="fas fa-list me-2"></i>
            Actions
            <?php if ($total_records > 0): ?>
                <span class="badge bg-secondary"><?php echo number_format($total_records); ?></span>
            <?php endif; ?>
        </h5>
        <?php if ($user_filter || $action_filter || $module_filter || $date_from || $date_to || $search): ?>
            <a href="?" class="btn btn-outline-secondary btn-sm">
                <i class="fas fa-times me-1"></i>
                Effacer les filtres
            </a>
        <?php endif; ?>
    </div>
    <div class="card-body">
        <?php if (!empty($actions)): ?>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Date/Heure</th>
                            <th>Utilisateur</th>
                            <th>Action</th>
                            <th>Module</th>
                            <th>Détails</th>
                            <th>IP</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($actions as $action): ?>
                            <tr>
                                <td>
                                    <small><?php echo formatDateTime($action['created_at']); ?></small>
                                </td>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div>
                                            <strong><?php echo htmlspecialchars($action['nom'] . ' ' . $action['prenom']); ?></strong>
                                            <br><small class="text-muted">
                                                @<?php echo htmlspecialchars($action['username']); ?>
                                                <span class="badge bg-<?php 
                                                    echo $action['role'] === 'admin' ? 'danger' : 
                                                        ($action['role'] === 'directeur' ? 'warning' : 'info'); 
                                                ?> ms-1">
                                                    <?php echo ucfirst($action['role']); ?>
                                                </span>
                                            </small>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge bg-<?php echo getActionColor($action['action']); ?>">
                                        <i class="fas fa-<?php echo getActionIcon($action['action']); ?> me-1"></i>
                                        <?php echo getActionLabel($action['action']); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge bg-secondary">
                                        <?php echo ucfirst($action['module']); ?>
                                    </span>
                                </td>
                                <td>
                                    <div style="max-width: 300px;">
                                        <?php echo htmlspecialchars($action['details']); ?>
                                        <?php if ($action['target_id']): ?>
                                            <br><small class="text-muted">
                                                ID cible: <?php echo $action['target_id']; ?>
                                            </small>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <small>
                                        <?php echo htmlspecialchars($action['ip_address'] ?? 'N/A'); ?>
                                    </small>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
                <nav aria-label="Navigation de l'historique">
                    <ul class="pagination justify-content-center">
                        <?php if ($page > 1): ?>
                            <li class="page-item">
                                <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>">
                                    Précédent
                                </a>
                            </li>
                        <?php endif; ?>
                        
                        <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                            <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>">
                                    <?php echo $i; ?>
                                </a>
                            </li>
                        <?php endfor; ?>
                        
                        <?php if ($page < $total_pages): ?>
                            <li class="page-item">
                                <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>">
                                    Suivant
                                </a>
                            </li>
                        <?php endif; ?>
                    </ul>
                </nav>
            <?php endif; ?>
        <?php else: ?>
            <div class="text-center py-5">
                <i class="fas fa-history fa-4x text-muted mb-3"></i>
                <h4 class="text-muted">Aucune action trouvée</h4>
                <p class="text-muted">
                    <?php if ($user_filter || $action_filter || $module_filter || $date_from || $date_to || $search): ?>
                        Aucune action ne correspond aux critères de recherche.
                    <?php else: ?>
                        Aucune action n'est enregistrée dans l'historique.
                    <?php endif; ?>
                </p>
                <?php if ($user_filter || $action_filter || $module_filter || $date_from || $date_to || $search): ?>
                    <a href="?" class="btn btn-outline-primary">
                        <i class="fas fa-times me-1"></i>
                        Effacer les filtres
                    </a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php
// Fonctions helper
function getActionIcon($action) {
    $icons = [
        'create_user' => 'user-plus',
        'update_user' => 'user-edit',
        'delete_user' => 'user-times',
        'login' => 'sign-in-alt',
        'logout' => 'sign-out-alt',
        'change_password' => 'key',
        'update_profile' => 'user-cog',
        'view_user_profile' => 'eye',
        'create_absence' => 'user-times',
        'justify_absence' => 'check',
        'update_absence' => 'edit'
    ];
    return $icons[$action] ?? 'info-circle';
}

function getActionLabel($action) {
    $labels = [
        'create_user' => 'Utilisateur créé',
        'update_user' => 'Utilisateur modifié',
        'delete_user' => 'Utilisateur supprimé',
        'login' => 'Connexion',
        'logout' => 'Déconnexion',
        'change_password' => 'Mot de passe changé',
        'update_profile' => 'Profil mis à jour',
        'view_user_profile' => 'Profil consulté',
        'create_absence' => 'Absence créée',
        'justify_absence' => 'Absence justifiée',
        'update_absence' => 'Absence modifiée'
    ];
    return $labels[$action] ?? ucfirst(str_replace('_', ' ', $action));
}

function getActionColor($action) {
    $colors = [
        'create_user' => 'success',
        'update_user' => 'warning',
        'delete_user' => 'danger',
        'login' => 'info',
        'logout' => 'secondary',
        'change_password' => 'warning',
        'update_profile' => 'primary',
        'view_user_profile' => 'info',
        'create_absence' => 'danger',
        'justify_absence' => 'success',
        'update_absence' => 'warning'
    ];
    return $colors[$action] ?? 'secondary';
}

include '../../../includes/footer.php';
?>
