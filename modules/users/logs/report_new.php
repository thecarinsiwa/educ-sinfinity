<?php
/**
 * Module Gestion des Utilisateurs - Rapport des logs (Version corrigée)
 * Application de gestion scolaire - République Démocratique du Congo
 */

require_once '../../../config/config.php';
require_once '../../../config/database.php';
require_once '../../../includes/functions.php';

// Vérifier l'authentification
requireLogin();

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
    // Construire la requête principale
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
    $logs = [];
    if (isset($_GET['debug'])) {
        echo "<div class='alert alert-danger'>";
        echo "<strong>Erreur SQL:</strong> " . htmlspecialchars($e->getMessage());
        echo "<br><strong>Requête:</strong> <code>" . htmlspecialchars($main_sql) . "</code>";
        echo "<br><strong>Paramètres:</strong> <code>" . print_r($params, true) . "</code>";
        echo "</div>";
    }
}

// Compter le total pour la pagination
try {
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
    "SELECT COUNT(*) as count FROM user_actions_log WHERE DATE(created_at) = CURDATE()"
)->fetch()['count'];
$stats['week_logs'] = $database->query(
    "SELECT COUNT(*) as count FROM user_actions_log WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)"
)->fetch()['count'];

// Top actions
$top_actions = $database->query(
    "SELECT action, COUNT(*) as count 
     FROM user_actions_log 
     GROUP BY action 
     ORDER BY count DESC 
     LIMIT 5"
)->fetchAll();

// Top users
$top_users = $database->query(
    "SELECT u.username, u.nom, u.prenom, COUNT(*) as count
     FROM user_actions_log ual
     JOIN users u ON ual.user_id = u.id
     GROUP BY ual.user_id, u.username, u.nom, u.prenom
     ORDER BY count DESC
     LIMIT 5"
)->fetchAll();

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - Educ-Sinfinity</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        .stats-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
        }
        .stats-card h3 {
            margin: 0;
            font-size: 2rem;
        }
        .stats-card p {
            margin: 5px 0 0 0;
            opacity: 0.9;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h1><i class="bi bi-graph-up"></i> <?php echo $page_title; ?></h1>
                    <div>
                        <a href="index.php" class="btn btn-outline-primary">
                            <i class="bi bi-arrow-left"></i> Retour aux logs
                        </a>
                        <a href="export.php?<?php echo http_build_query($_GET); ?>" class="btn btn-success">
                            <i class="bi bi-download"></i> Exporter
                        </a>
                    </div>
                </div>

                <!-- Filtres -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5><i class="bi bi-funnel"></i> Filtres</h5>
                    </div>
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-3">
                                <label for="user_id" class="form-label">Utilisateur</label>
                                <select class="form-select" id="user_id" name="user_id">
                                    <option value="">Tous les utilisateurs</option>
                                    <?php
                                    $users = $database->query("SELECT id, username, nom, prenom FROM users ORDER BY nom, prenom")->fetchAll();
                                    foreach ($users as $user) {
                                        $selected = ($user_filter == $user['id']) ? 'selected' : '';
                                        echo "<option value='{$user['id']}' $selected>{$user['nom']} {$user['prenom']} ({$user['username']})</option>";
                                    }
                                    ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label for="action" class="form-label">Action</label>
                                <input type="text" class="form-control" id="action" name="action" value="<?php echo htmlspecialchars($action_filter); ?>" placeholder="Rechercher une action">
                            </div>
                            <div class="col-md-2">
                                <label for="date_from" class="form-label">Date début</label>
                                <input type="date" class="form-control" id="date_from" name="date_from" value="<?php echo htmlspecialchars($date_from); ?>">
                            </div>
                            <div class="col-md-2">
                                <label for="date_to" class="form-label">Date fin</label>
                                <input type="date" class="form-control" id="date_to" name="date_to" value="<?php echo htmlspecialchars($date_to); ?>">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">&nbsp;</label>
                                <div class="d-grid">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="bi bi-search"></i> Filtrer
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Statistiques -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="stats-card">
                            <h3><?php echo number_format($stats['total_logs']); ?></h3>
                            <p>Total des logs</p>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stats-card">
                            <h3><?php echo number_format($stats['today_logs']); ?></h3>
                            <p>Logs aujourd'hui</p>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stats-card">
                            <h3><?php echo number_format($stats['week_logs']); ?></h3>
                            <p>Logs cette semaine</p>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stats-card">
                            <h3><?php echo $total_pages; ?></h3>
                            <p>Pages totales</p>
                        </div>
                    </div>
                </div>

                <!-- Top actions et utilisateurs -->
                <div class="row mb-4">
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h5><i class="bi bi-trophy"></i> Top Actions</h5>
                            </div>
                            <div class="card-body">
                                <?php if (!empty($top_actions)): ?>
                                    <div class="list-group">
                                        <?php foreach ($top_actions as $action): ?>
                                            <div class="list-group-item d-flex justify-content-between align-items-center">
                                                <span><?php echo htmlspecialchars($action['action']); ?></span>
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
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h5><i class="bi bi-people"></i> Top Utilisateurs</h5>
                            </div>
                            <div class="card-body">
                                <?php if (!empty($top_users)): ?>
                                    <div class="list-group">
                                        <?php foreach ($top_users as $user): ?>
                                            <div class="list-group-item d-flex justify-content-between align-items-center">
                                                <span><?php echo htmlspecialchars($user['nom'] . ' ' . $user['prenom']); ?></span>
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

                <!-- Tableau des logs -->
                <div class="card">
                    <div class="card-header">
                        <h5><i class="bi bi-list-ul"></i> Logs détaillés</h5>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($logs)): ?>
                            <div class="table-responsive">
                                <table class="table table-striped table-hover">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Utilisateur</th>
                                            <th>Action</th>
                                            <th>Module</th>
                                            <th>Détails</th>
                                            <th>Date</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($logs as $log): ?>
                                            <tr>
                                                <td><?php echo $log['id']; ?></td>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($log['username']); ?></strong><br>
                                                    <small class="text-muted"><?php echo htmlspecialchars($log['nom'] . ' ' . $log['prenom']); ?></small>
                                                </td>
                                                <td>
                                                    <span class="badge bg-info"><?php echo htmlspecialchars($log['action']); ?></span>
                                                </td>
                                                <td>
                                                    <span class="badge bg-secondary"><?php echo htmlspecialchars($log['module']); ?></span>
                                                </td>
                                                <td><?php echo htmlspecialchars($log['details']); ?></td>
                                                <td>
                                                    <?php echo date('d/m/Y H:i', strtotime($log['created_at'])); ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>

                            <!-- Pagination -->
                            <?php if ($total_pages > 1): ?>
                                <nav aria-label="Pagination">
                                    <ul class="pagination justify-content-center">
                                        <?php if ($page > 1): ?>
                                            <li class="page-item">
                                                <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>">
                                                    <i class="bi bi-chevron-left"></i> Précédent
                                                </a>
                                            </li>
                                        <?php endif; ?>

                                        <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                                            <li class="page-item <?php echo ($i == $page) ? 'active' : ''; ?>">
                                                <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>">
                                                    <?php echo $i; ?>
                                                </a>
                                            </li>
                                        <?php endfor; ?>

                                        <?php if ($page < $total_pages): ?>
                                            <li class="page-item">
                                                <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>">
                                                    Suivant <i class="bi bi-chevron-right"></i>
                                                </a>
                                            </li>
                                        <?php endif; ?>
                                    </ul>
                                </nav>
                            <?php endif; ?>

                        <?php else: ?>
                            <div class="text-center py-5">
                                <i class="bi bi-inbox display-1 text-muted"></i>
                                <h4 class="text-muted mt-3">Aucun log trouvé</h4>
                                <p class="text-muted">Aucun log ne correspond aux critères de recherche.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
