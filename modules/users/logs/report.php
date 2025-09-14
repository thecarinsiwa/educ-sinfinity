<?php
/**
 * Module Gestion des Utilisateurs - Rapport des logs
 * Application de gestion scolaire - République Démocratique du Congo
 */

require_once '../../../config/config.php';
require_once '../../../config/database.php';
require_once '../../../includes/functions.php';
// require_once '../../../includes/permissions-pages.php'; // Temporairement désactivé pour debug

// Vérifier l'authentification et les permissions
requireLogin();
// requirePagePermission('admin', 'logs/report', 'read', '../../../dashboard.php'); // Temporairement désactivé pour debug

$page_title = 'Rapport des Logs';

// Paramètres de filtrage
$user_filter = (int)($_GET['user_id'] ?? 0);
$action_filter = sanitizeInput($_GET['action'] ?? '');
$date_from = sanitizeInput($_GET['date_from'] ?? '');
$date_to = sanitizeInput($_GET['date_to'] ?? '');
$page = (int)($_GET['page'] ?? 1);

// Construction des conditions WHERE
$where_conditions = [];
$params = [];

if ($user_filter) {
    $where_conditions[] = "ual.user_id = ?";
    $params[] = $user_filter;
}

if ($action_filter) {
    $where_conditions[] = "ual.action LIKE ?";
    $params[] = "%$action_filter%";
}

if ($date_from) {
    $where_conditions[] = "DATE(ual.created_at) >= ?";
    $params[] = $date_from;
}

if ($date_to) {
    $where_conditions[] = "DATE(ual.created_at) <= ?";
    $params[] = $date_to;
}

$where_clause = !empty($where_conditions) ? implode(' AND ', $where_conditions) : '1=1';

// Debug: afficher la requête pour diagnostiquer
if (isset($_GET['debug'])) {
    echo "<pre>WHERE clause: $where_clause</pre>";
    echo "<pre>Params: " . print_r($params, true) . "</pre>";
}

// Récupérer les logs avec pagination
$per_page = 100;
$offset = ($page - 1) * $per_page;

try {
    // Construire la requête principale de manière plus robuste
    $main_sql = "SELECT ual.*, u.username, u.nom, u.prenom, r.nom as role
                 FROM user_actions_log as ual
                 JOIN users u ON ual.user_id = u.id
                 LEFT JOIN roles r ON u.role_id = r.id";
    
    if (!empty($where_conditions)) {
        $main_sql .= " WHERE " . implode(' AND ', $where_conditions);
    }
    
    $main_sql .= " ORDER BY ual.created_at DESC LIMIT $per_page OFFSET $offset";
    
    if (isset($_GET['debug'])) {
        echo "<div class='alert alert-info'>";
        echo "<strong>Requête principale:</strong> <code>" . htmlspecialchars($main_sql) . "</code>";
        echo "<br><strong>Paramètres:</strong> <code>" . print_r($params, true) . "</code>";
        echo "</div>";
    }
    
    $logs = $database->query($main_sql, $params)->fetchAll();
} catch (Exception $e) {
    // En cas d'erreur, essayer une requête simplifiée
    if (isset($_GET['debug'])) {
        echo "<div class='alert alert-danger'>";
        echo "<strong>Erreur SQL:</strong> " . htmlspecialchars($e->getMessage());
        echo "<br><strong>Requête:</strong> <code>" . htmlspecialchars($main_sql) . "</code>";
        echo "<br><strong>Paramètres:</strong> <code>" . print_r($params, true) . "</code>";
        echo "</div>";
    }
    
    // Fallback: requête simplifiée sans filtres complexes
    try {
        $logs = $database->query(
            "SELECT ual.*, u.username, u.nom, u.prenom, r.nom as role
             FROM user_actions_log ual
             JOIN users u ON ual.user_id = u.id
             LEFT JOIN roles r ON u.role_id = r.id
             ORDER BY ual.created_at DESC
             LIMIT $per_page OFFSET $offset"
        )->fetchAll();
        
        if (isset($_GET['debug'])) {
            echo "<div class='alert alert-info'>";
            echo "<strong>Fallback activé:</strong> Affichage des logs sans filtres";
            echo "</div>";
        }
    } catch (Exception $e2) {
        $logs = [];
        if (isset($_GET['debug'])) {
            echo "<div class='alert alert-danger'>";
            echo "<strong>Erreur même avec fallback:</strong> " . htmlspecialchars($e2->getMessage());
            echo "</div>";
        }
    }
}

// Compter le total pour la pagination
try {
    // Construire la requête de comptage de manière plus robuste
    $count_sql = "SELECT COUNT(*) as total FROM user_actions_log as ual JOIN users u ON ual.user_id = u.id";
    
    if (!empty($where_conditions)) {
        $count_sql .= " WHERE " . implode(' AND ', $where_conditions);
    }
    
    if (isset($_GET['debug'])) {
        echo "<div class='alert alert-info'>";
        echo "<strong>Requête de comptage:</strong> <code>$count_sql</code>";
        echo "<br><strong>Paramètres:</strong> <code>" . print_r($params, true) . "</code>";
        echo "</div>";
    }
    
    $total_stmt = $database->query($count_sql, $params);
    $total_logs = $total_stmt->fetch()['total'];
    $total_pages = ceil($total_logs / $per_page);
} catch (Exception $e) {
    // En cas d'erreur, utiliser des valeurs par défaut
    $total_logs = 0;
    $total_pages = 0;
    
    if (isset($_GET['debug'])) {
        echo "<div class='alert alert-warning'>";
        echo "<strong>Erreur dans le comptage:</strong> " . htmlspecialchars($e->getMessage());
        echo "<br><strong>Requête:</strong> <code>$count_sql</code>";
        echo "<br><strong>Paramètres:</strong> <code>" . print_r($params, true) . "</code>";
        echo "</div>";
    }
}

// Statistiques
$stats = [];
$stats['total_logs'] = $total_logs;
$stats['today_logs'] = $database->query(
    "SELECT COUNT(*) as total FROM user_actions_log WHERE DATE(created_at) = CURDATE()"
)->fetch()['total'];
$stats['week_logs'] = $database->query(
    "SELECT COUNT(*) as total FROM user_actions_log WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)"
)->fetch()['total'];

// Actions les plus fréquentes
$top_actions = $database->query(
    "SELECT action, COUNT(*) as count 
     FROM user_actions_log 
     WHERE $where_clause
     GROUP BY action 
     ORDER BY count DESC 
     LIMIT 10",
    $params
)->fetchAll();

// Utilisateurs les plus actifs
$top_users = $database->query(
    "SELECT u.username, u.nom, u.prenom, COUNT(ual.id) as count
     FROM user_actions_log ual
     JOIN users u ON ual.user_id = u.id
     WHERE $where_clause
     GROUP BY ual.user_id, u.username, u.nom, u.prenom
     ORDER BY count DESC
     LIMIT 10",
    $params
)->fetchAll();

include '../../../includes/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">
        <i class="fas fa-chart-line me-2"></i>
        Rapport des Logs
    </h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <div class="btn-group me-2">
            <a href="index.php" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-1"></i>
                Retour aux logs
            </a>
        </div>
        <div class="btn-group">
            <a href="export.php?<?php echo http_build_query($_GET); ?>" class="btn btn-outline-primary">
                <i class="fas fa-download me-1"></i>
                Exporter
            </a>
        </div>
    </div>
</div>

<!-- Filtres -->
<div class="card mb-4">
    <div class="card-header">
        <h5 class="mb-0">
            <i class="fas fa-filter me-2"></i>
            Filtres
        </h5>
    </div>
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-md-3">
                <label for="user_id" class="form-label">Utilisateur</label>
                <select class="form-select" id="user_id" name="user_id">
                    <option value="">Tous les utilisateurs</option>
                    <?php
                    $users = $database->query(
                        "SELECT id, username, nom, prenom FROM users ORDER BY nom, prenom"
                    )->fetchAll();
                    
                    foreach ($users as $user):
                    ?>
                        <option value="<?php echo $user['id']; ?>" 
                                <?php echo $user_filter === $user['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($user['nom'] . ' ' . $user['prenom'] . ' (@' . $user['username'] . ')'); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label for="action" class="form-label">Action</label>
                <input type="text" class="form-control" id="action" name="action" 
                       value="<?php echo htmlspecialchars($action_filter); ?>" 
                       placeholder="Rechercher une action...">
            </div>
            <div class="col-md-2">
                <label for="date_from" class="form-label">Date début</label>
                <input type="date" class="form-control" id="date_from" name="date_from" 
                       value="<?php echo htmlspecialchars($date_from); ?>">
            </div>
            <div class="col-md-2">
                <label for="date_to" class="form-label">Date fin</label>
                <input type="date" class="form-control" id="date_to" name="date_to" 
                       value="<?php echo htmlspecialchars($date_to); ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label">&nbsp;</label>
                <div class="d-grid">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search me-1"></i>
                        Filtrer
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Statistiques -->
<div class="row mb-4">
    <div class="col-md-4">
        <div class="card text-white bg-primary">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h4><?php echo number_format($stats['total_logs']); ?></h4>
                        <p class="mb-0">Total logs</p>
                    </div>
                    <div class="align-self-center">
                        <i class="fas fa-list fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card text-white bg-success">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h4><?php echo number_format($stats['today_logs']); ?></h4>
                        <p class="mb-0">Aujourd'hui</p>
                    </div>
                    <div class="align-self-center">
                        <i class="fas fa-calendar-day fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card text-white bg-info">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h4><?php echo number_format($stats['week_logs']); ?></h4>
                        <p class="mb-0">Cette semaine</p>
                    </div>
                    <div class="align-self-center">
                        <i class="fas fa-calendar-week fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <!-- Actions les plus fréquentes -->
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-chart-bar me-2"></i>
                    Actions les plus fréquentes
                </h5>
            </div>
            <div class="card-body">
                <?php if (!empty($top_actions)): ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($top_actions as $action): ?>
                            <div class="list-group-item d-flex justify-content-between align-items-center">
                                <span><?php echo htmlspecialchars(getActionLabel($action['action'])); ?></span>
                                <span class="badge bg-primary rounded-pill"><?php echo $action['count']; ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p class="text-muted">Aucune donnée disponible</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Utilisateurs les plus actifs -->
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-users me-2"></i>
                    Utilisateurs les plus actifs
                </h5>
            </div>
            <div class="card-body">
                <?php if (!empty($top_users)): ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($top_users as $user): ?>
                            <div class="list-group-item d-flex justify-content-between align-items-center">
                                <div>
                                    <strong><?php echo htmlspecialchars($user['nom'] . ' ' . $user['prenom']); ?></strong>
                                    <br><small class="text-muted">@<?php echo htmlspecialchars($user['username']); ?></small>
                                </div>
                                <span class="badge bg-success rounded-pill"><?php echo $user['count']; ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p class="text-muted">Aucune donnée disponible</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Liste des logs -->
<div class="card mt-4">
    <div class="card-header">
        <h5 class="mb-0">
            <i class="fas fa-list me-2"></i>
            Logs récents (<?php echo count($logs); ?> sur <?php echo number_format($total_logs); ?>)
        </h5>
    </div>
    <div class="card-body">
        <?php if (!empty($logs)): ?>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Date/Heure</th>
                            <th>Utilisateur</th>
                            <th>Action</th>
                            <th>Détails</th>
                            <th>IP</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($logs as $log): ?>
                            <tr>
                                <td>
                                    <small><?php echo formatDateTime($log['created_at']); ?></small>
                                </td>
                                <td>
                                    <div>
                                        <strong><?php echo htmlspecialchars($log['nom'] . ' ' . $log['prenom']); ?></strong>
                                        <br><small class="text-muted">@<?php echo htmlspecialchars($log['username']); ?></small>
                                        <?php if ($log['role']): ?>
                                            <br><span class="badge bg-secondary"><?php echo htmlspecialchars($log['role']); ?></span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge bg-<?php echo getActionColor($log['action']); ?>">
                                        <i class="fas fa-<?php echo getActionIcon($log['action']); ?> me-1"></i>
                                        <?php echo htmlspecialchars(getActionLabel($log['action'])); ?>
                                    </span>
                                </td>
                                <td>
                                    <small><?php echo htmlspecialchars($log['details']); ?></small>
                                </td>
                                <td>
                                    <small class="text-muted"><?php echo htmlspecialchars($log['ip_address']); ?></small>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
                <nav aria-label="Navigation des logs">
                    <ul class="pagination justify-content-center">
                        <?php if ($page > 1): ?>
                            <li class="page-item">
                                <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>">
                                    <i class="fas fa-chevron-left"></i>
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
                                    <i class="fas fa-chevron-right"></i>
                                </a>
                            </li>
                        <?php endif; ?>
                    </ul>
                </nav>
            <?php endif; ?>
            
        <?php else: ?>
            <div class="text-center py-5">
                <i class="fas fa-history fa-3x text-muted mb-3"></i>
                <h5 class="text-muted">Aucun log trouvé</h5>
                <p class="text-muted">Aucun log ne correspond aux critères de recherche.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php
// Fonction helper pour les couleurs d'actions
function getActionColor($action) {
    $colors = [
        'create_user' => 'success',
        'update_user' => 'warning',
        'delete_user' => 'danger',
        'login' => 'info',
        'logout' => 'secondary',
        'change_password' => 'warning',
        'update_profile' => 'primary'
    ];
    return $colors[$action] ?? 'secondary';
}

include '../../../includes/footer.php';
?>
