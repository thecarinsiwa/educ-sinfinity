<?php
/**
 * Module Gestion des Utilisateurs - Liste complète
 * Application de gestion scolaire - République Démocratique du Congo
 */

require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../includes/functions.php';

// Vérifier l'authentification et les permissions
requireLogin();
if (!checkPermission('admin') && !checkPermission('users_view')) {
    showMessage('error', 'Accès refusé à ce module.');
    redirectTo('index.php');
}

$page_title = 'Liste des Utilisateurs';

// Paramètres de recherche et filtrage
$search = sanitizeInput($_GET['search'] ?? '');
$role_filter = sanitizeInput($_GET['role'] ?? '');
$status_filter = sanitizeInput($_GET['status'] ?? '');

// Construction de la requête avec filtres
$where_conditions = ["1=1"];
$params = [];

if ($search) {
    $where_conditions[] = "(u.username LIKE ? OR u.nom LIKE ? OR u.prenom LIKE ? OR u.email LIKE ?)";
    $search_param = "%$search%";
    $params = array_merge($params, [$search_param, $search_param, $search_param, $search_param]);
}

if ($role_filter) {
    $where_conditions[] = "u.role = ?";
    $params[] = $role_filter;
}

if ($status_filter) {
    $where_conditions[] = "u.status = ?";
    $params[] = $status_filter;
}

$where_clause = implode(' AND ', $where_conditions);

// Récupérer les utilisateurs avec pagination
$page = (int)($_GET['page'] ?? 1);
$per_page = 20;
$offset = ($page - 1) * $per_page;

$users = $database->query(
    "SELECT u.*, 
            (SELECT COUNT(*) FROM user_actions_log WHERE user_id = u.id) as nb_actions,
            (SELECT COUNT(*) FROM user_sessions WHERE user_id = u.id AND last_activity >= DATE_SUB(NOW(), INTERVAL 30 MINUTE)) as sessions_actives
     FROM users u
     WHERE $where_clause
     ORDER BY u.created_at DESC
     LIMIT $per_page OFFSET $offset",
    $params
)->fetchAll();

// Compter le total pour la pagination
$total_stmt = $database->query(
    "SELECT COUNT(*) as total FROM users u WHERE $where_clause",
    $params
);
$total_records = $total_stmt->fetch()['total'];
$total_pages = ceil($total_records / $per_page);

// Récupérer les rôles pour le filtre
$roles = $database->query("SELECT DISTINCT role FROM users ORDER BY role")->fetchAll();

include '../../includes/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">
        <i class="fas fa-users me-2"></i>
        Liste des Utilisateurs
    </h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <div class="btn-group me-2">
            <a href="index.php" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-1"></i>
                Retour
            </a>
        </div>
        <?php if (checkPermission('admin')): ?>
            <div class="btn-group me-2">
                <a href="add.php" class="btn btn-primary">
                    <i class="fas fa-plus me-1"></i>
                    Nouvel utilisateur
                </a>
            </div>
        <?php endif; ?>
        <div class="btn-group">
            <button type="button" class="btn btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">
                <i class="fas fa-tools me-1"></i>
                Actions
            </button>
            <ul class="dropdown-menu">
                <li><a class="dropdown-item" href="exports/users-list.php?<?php echo http_build_query($_GET); ?>">
                    <i class="fas fa-file-excel me-2"></i>Exporter Excel
                </a></li>
                <li><a class="dropdown-item" href="exports/users-report.php?<?php echo http_build_query($_GET); ?>">
                    <i class="fas fa-file-pdf me-2"></i>Rapport PDF
                </a></li>
                <?php if (checkPermission('admin')): ?>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item" href="bulk-actions.php">
                        <i class="fas fa-tasks me-2"></i>Actions en masse
                    </a></li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</div>

<!-- Filtres et recherche -->
<div class="card mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-md-4">
                <label for="search" class="form-label">Rechercher</label>
                <input type="text" class="form-control" id="search" name="search" 
                       value="<?php echo htmlspecialchars($search); ?>" 
                       placeholder="Nom, prénom, username ou email...">
            </div>
            <div class="col-md-3">
                <label for="role" class="form-label">Rôle</label>
                <select class="form-select" id="role" name="role">
                    <option value="">Tous les rôles</option>
                    <?php foreach ($roles as $role): ?>
                        <option value="<?php echo $role['role']; ?>" 
                                <?php echo $role_filter === $role['role'] ? 'selected' : ''; ?>>
                            <?php echo ucfirst($role['role']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label for="status" class="form-label">Statut</label>
                <select class="form-select" id="status" name="status">
                    <option value="">Tous les statuts</option>
                    <option value="actif" <?php echo $status_filter === 'actif' ? 'selected' : ''; ?>>Actif</option>
                    <option value="inactif" <?php echo $status_filter === 'inactif' ? 'selected' : ''; ?>>Inactif</option>
                </select>
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

<!-- Liste des utilisateurs -->
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">
            <i class="fas fa-list me-2"></i>
            Utilisateurs
            <?php if ($total_records > 0): ?>
                <span class="badge bg-secondary"><?php echo $total_records; ?></span>
            <?php endif; ?>
        </h5>
        <div class="btn-group btn-group-sm">
            <button type="button" class="btn btn-outline-secondary" onclick="toggleView('cards')">
                <i class="fas fa-th"></i>
            </button>
            <button type="button" class="btn btn-outline-secondary active" onclick="toggleView('table')">
                <i class="fas fa-list"></i>
            </button>
        </div>
    </div>
    <div class="card-body">
        <?php if (!empty($users)): ?>
            <!-- Vue tableau -->
            <div id="table-view">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Utilisateur</th>
                                <th>Nom complet</th>
                                <th>Email</th>
                                <th>Rôle</th>
                                <th>Statut</th>
                                <th>Dernière connexion</th>
                                <th>Sessions</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $user): ?>
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="avatar-sm me-2">
                                                <?php if ($user['photo']): ?>
                                                    <img src="<?php echo htmlspecialchars($user['photo']); ?>" 
                                                         alt="Avatar" class="rounded-circle" width="32" height="32">
                                                <?php else: ?>
                                                    <div class="bg-secondary rounded-circle d-flex align-items-center justify-content-center" 
                                                         style="width: 32px; height: 32px;">
                                                        <i class="fas fa-user text-white"></i>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                            <div>
                                                <strong><?php echo htmlspecialchars($user['username']); ?></strong>
                                                <br><small class="text-muted">
                                                    ID: <?php echo $user['id']; ?>
                                                </small>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <?php echo htmlspecialchars($user['nom'] . ' ' . $user['prenom']); ?>
                                    </td>
                                    <td>
                                        <?php if ($user['email']): ?>
                                            <a href="mailto:<?php echo htmlspecialchars($user['email']); ?>">
                                                <?php echo htmlspecialchars($user['email']); ?>
                                            </a>
                                        <?php else: ?>
                                            <span class="text-muted">Non renseigné</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?php 
                                            echo $user['role'] === 'admin' ? 'danger' : 
                                                ($user['role'] === 'directeur' ? 'warning' : 
                                                ($user['role'] === 'enseignant' ? 'primary' : 'info')); 
                                        ?>">
                                            <?php echo ucfirst($user['role']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?php echo $user['status'] === 'actif' ? 'success' : 'secondary'; ?>">
                                            <?php echo ucfirst($user['status']); ?>
                                        </span>
                                        <?php if ($user['compte_verrouille']): ?>
                                            <br><small class="text-danger">
                                                <i class="fas fa-lock"></i> Verrouillé
                                            </small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($user['derniere_connexion']): ?>
                                            <?php echo formatDateTime($user['derniere_connexion']); ?>
                                        <?php else: ?>
                                            <span class="text-muted">Jamais connecté</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <?php if ($user['sessions_actives'] > 0): ?>
                                            <span class="badge bg-success"><?php echo $user['sessions_actives']; ?></span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">0</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <a href="view.php?id=<?php echo $user['id']; ?>" 
                                               class="btn btn-outline-info" title="Voir profil">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <?php if (checkPermission('admin') || $user['id'] == $_SESSION['user_id']): ?>
                                                <a href="edit.php?id=<?php echo $user['id']; ?>" 
                                                   class="btn btn-outline-primary" title="Modifier">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                            <?php endif; ?>
                                            <button type="button" class="btn btn-outline-secondary" 
                                                    onclick="showUserHistory(<?php echo $user['id']; ?>)" 
                                                    title="Historique">
                                                <i class="fas fa-history"></i>
                                                <span class="badge bg-secondary"><?php echo $user['nb_actions']; ?></span>
                                            </button>
                                            <?php if (checkPermission('admin') && $user['id'] != $_SESSION['user_id']): ?>
                                                <div class="btn-group btn-group-sm">
                                                    <button type="button" class="btn btn-outline-warning dropdown-toggle" 
                                                            data-bs-toggle="dropdown" title="Actions">
                                                        <i class="fas fa-cog"></i>
                                                    </button>
                                                    <ul class="dropdown-menu">
                                                        <li><a class="dropdown-item" href="#" 
                                                               onclick="resetPassword(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['username']); ?>')">
                                                            <i class="fas fa-key me-2"></i>Réinitialiser mot de passe
                                                        </a></li>
                                                        <li><a class="dropdown-item" href="#" 
                                                               onclick="toggleUserStatus(<?php echo $user['id']; ?>, '<?php echo $user['status']; ?>')">
                                                            <i class="fas fa-<?php echo $user['status'] === 'actif' ? 'ban' : 'check'; ?> me-2"></i>
                                                            <?php echo $user['status'] === 'actif' ? 'Désactiver' : 'Activer'; ?>
                                                        </a></li>
                                                        <?php if ($user['compte_verrouille']): ?>
                                                            <li><a class="dropdown-item" href="#" 
                                                                   onclick="unlockUser(<?php echo $user['id']; ?>)">
                                                                <i class="fas fa-unlock me-2"></i>Déverrouiller
                                                            </a></li>
                                                        <?php endif; ?>
                                                        <li><hr class="dropdown-divider"></li>
                                                        <li><a class="dropdown-item text-danger" href="#"
                                                               onclick="deleteUser(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['username']); ?>')">
                                                            <i class="fas fa-trash me-2"></i>Supprimer
                                                        </a></li>
                                                    </ul>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <!-- Vue cartes -->
            <div id="cards-view" style="display: none;">
                <div class="row">
                    <?php foreach ($users as $user): ?>
                        <div class="col-md-6 col-lg-4 mb-3">
                            <div class="card h-100">
                                <div class="card-body">
                                    <div class="d-flex align-items-center mb-3">
                                        <div class="avatar-md me-3">
                                            <?php if ($user['photo']): ?>
                                                <img src="<?php echo htmlspecialchars($user['photo']); ?>" 
                                                     alt="Avatar" class="rounded-circle" width="50" height="50">
                                            <?php else: ?>
                                                <div class="bg-secondary rounded-circle d-flex align-items-center justify-content-center" 
                                                     style="width: 50px; height: 50px;">
                                                    <i class="fas fa-user text-white fa-lg"></i>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        <div>
                                            <h6 class="mb-0"><?php echo htmlspecialchars($user['username']); ?></h6>
                                            <small class="text-muted">
                                                <?php echo htmlspecialchars($user['nom'] . ' ' . $user['prenom']); ?>
                                            </small>
                                        </div>
                                    </div>
                                    
                                    <div class="mb-2">
                                        <span class="badge bg-<?php 
                                            echo $user['role'] === 'admin' ? 'danger' : 
                                                ($user['role'] === 'directeur' ? 'warning' : 
                                                ($user['role'] === 'enseignant' ? 'primary' : 'info')); 
                                        ?>">
                                            <?php echo ucfirst($user['role']); ?>
                                        </span>
                                        <span class="badge bg-<?php echo $user['status'] === 'actif' ? 'success' : 'secondary'; ?>">
                                            <?php echo ucfirst($user['status']); ?>
                                        </span>
                                    </div>
                                    
                                    <div class="d-flex justify-content-between align-items-center">
                                        <small class="text-muted">
                                            <?php echo $user['nb_actions']; ?> actions
                                        </small>
                                        <div class="btn-group btn-group-sm">
                                            <a href="view.php?id=<?php echo $user['id']; ?>" 
                                               class="btn btn-outline-info">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <?php if (checkPermission('admin') || $user['id'] == $_SESSION['user_id']): ?>
                                                <a href="edit.php?id=<?php echo $user['id']; ?>" 
                                                   class="btn btn-outline-primary">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
                <nav aria-label="Navigation des utilisateurs">
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
                <i class="fas fa-users fa-4x text-muted mb-3"></i>
                <h4 class="text-muted">Aucun utilisateur trouvé</h4>
                <p class="text-muted">
                    <?php if ($search || $role_filter || $status_filter): ?>
                        Aucun utilisateur ne correspond aux critères de recherche.
                    <?php else: ?>
                        Aucun utilisateur n'est enregistré dans le système.
                    <?php endif; ?>
                </p>
                <?php if ($search || $role_filter || $status_filter): ?>
                    <a href="?" class="btn btn-outline-primary">
                        <i class="fas fa-times me-1"></i>
                        Effacer les filtres
                    </a>
                <?php endif; ?>
                <?php if (checkPermission('admin')): ?>
                    <a href="add.php" class="btn btn-primary ms-2">
                        <i class="fas fa-plus me-1"></i>
                        Créer le premier utilisateur
                    </a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
function toggleView(viewType) {
    const tableView = document.getElementById('table-view');
    const cardsView = document.getElementById('cards-view');
    const buttons = document.querySelectorAll('.btn-group .btn');
    
    buttons.forEach(btn => btn.classList.remove('active'));
    
    if (viewType === 'cards') {
        tableView.style.display = 'none';
        cardsView.style.display = 'block';
        document.querySelector('button[onclick="toggleView(\'cards\')"]').classList.add('active');
    } else {
        tableView.style.display = 'block';
        cardsView.style.display = 'none';
        document.querySelector('button[onclick="toggleView(\'table\')"]').classList.add('active');
    }
}

function showUserHistory(userId) {
    window.location.href = 'logs/?user_id=' + userId;
}

function resetPassword(userId, username) {
    if (confirm('Réinitialiser le mot de passe pour ' + username + ' ?')) {
        // Implémenter la réinitialisation
        window.location.href = 'reset-password.php?id=' + userId;
    }
}

function toggleUserStatus(userId, currentStatus) {
    const action = currentStatus === 'actif' ? 'désactiver' : 'activer';
    if (confirm('Êtes-vous sûr de vouloir ' + action + ' cet utilisateur ?')) {
        // Implémenter le changement de statut
        window.location.href = 'toggle-status.php?id=' + userId;
    }
}

function unlockUser(userId) {
    if (confirm('Déverrouiller ce compte utilisateur ?')) {
        // Implémenter le déverrouillage
        window.location.href = 'unlock.php?id=' + userId;
    }
}

function deleteUser(userId, username) {
    if (confirm('ATTENTION: Supprimer définitivement l\'utilisateur ' + username + ' ?\n\nCette action est irréversible !')) {
        // Implémenter la suppression
        window.location.href = 'delete.php?id=' + userId;
    }
}
</script>

<?php include '../../includes/footer.php'; ?>
