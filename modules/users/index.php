<?php
/**
 * Module Gestion des Utilisateurs - Page principale
 * Application de gestion scolaire - République Démocratique du Congo
 */

require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../includes/functions.php';

// Vérifier l'authentification et les permissions
requireLogin();
if (!checkPermission('admin') && !checkPermission('users_manage')) {
    showMessage('error', 'Accès refusé à ce module.');
    redirectTo('../../dashboard.php');
}

$page_title = 'Gestion des Utilisateurs';

// Statistiques des utilisateurs
$stats = [];

// Total des utilisateurs
$stmt = $database->query("SELECT COUNT(*) as total FROM users");
$stats['total_users'] = $stmt->fetch()['total'];

// Utilisateurs actifs
$stmt = $database->query("SELECT COUNT(*) as total FROM users WHERE status = 'actif'");
$stats['active_users'] = $stmt->fetch()['total'];

// Utilisateurs inactifs
$stats['inactive_users'] = $stats['total_users'] - $stats['active_users'];

// Connexions récentes (24h)
$stmt = $database->query(
    "SELECT COUNT(*) as total FROM users WHERE derniere_connexion >= DATE_SUB(NOW(), INTERVAL 24 HOUR)"
);
$stats['recent_logins'] = $stmt->fetch()['total'];

// Utilisateurs par rôle
$users_by_role = $database->query(
    "SELECT role, COUNT(*) as nombre FROM users GROUP BY role ORDER BY nombre DESC"
)->fetchAll();

// Utilisateurs récemment créés
$recent_users = $database->query(
    "SELECT id, username, nom, prenom, role, status, created_at
     FROM users 
     ORDER BY created_at DESC 
     LIMIT 8"
)->fetchAll();

// Sessions actives
$active_sessions = $database->query(
    "SELECT COUNT(DISTINCT user_id) as total FROM user_sessions 
     WHERE last_activity >= DATE_SUB(NOW(), INTERVAL 30 MINUTE)"
)->fetch()['total'];

// Actions récentes
$recent_actions = $database->query(
    "SELECT ual.*, u.username, u.nom, u.prenom
     FROM user_actions_log ual
     JOIN users u ON ual.user_id = u.id
     ORDER BY ual.created_at DESC
     LIMIT 10"
)->fetchAll();

include '../../includes/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">
        <i class="fas fa-users-cog me-2"></i>
        Gestion des Utilisateurs
    </h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <?php if (checkPermission('admin')): ?>
            <div class="btn-group me-2">
                <button type="button" class="btn btn-primary dropdown-toggle" data-bs-toggle="dropdown">
                    <i class="fas fa-plus me-1"></i>
                    Nouveau
                </button>
                <ul class="dropdown-menu">
                    <li><a class="dropdown-item" href="add.php">
                        <i class="fas fa-user-plus me-2"></i>Nouvel utilisateur
                    </a></li>
                    <li><a class="dropdown-item" href="bulk-import.php">
                        <i class="fas fa-file-import me-2"></i>Import en masse
                    </a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item" href="../admin/pending-users.php">
                        <i class="fas fa-user-clock me-2"></i>Comptes en attente
                    </a></li>
                    <li><a class="dropdown-item" href="roles/manage.php">
                        <i class="fas fa-shield-alt me-2"></i>Gérer les rôles
                    </a></li>
                </ul>
            </div>
        <?php endif; ?>
        <div class="btn-group me-2">
            <button type="button" class="btn btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">
                <i class="fas fa-file-export me-1"></i>
                Exporter
            </button>
            <ul class="dropdown-menu">
                <li><a class="dropdown-item" href="exports/users-list.php">
                    <i class="fas fa-file-excel me-2"></i>Liste Excel
                </a></li>
                <li><a class="dropdown-item" href="exports/users-report.php">
                    <i class="fas fa-file-pdf me-2"></i>Rapport PDF
                </a></li>
            </ul>
        </div>
        <div class="btn-group">
            <button type="button" class="btn btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">
                <i class="fas fa-tools me-1"></i>
                Outils
            </button>
            <ul class="dropdown-menu">
                <li><a class="dropdown-item" href="logs/">
                    <i class="fas fa-history me-2"></i>Historique des actions
                </a></li>
                <li><a class="dropdown-item" href="sessions/">
                    <i class="fas fa-desktop me-2"></i>Sessions actives
                </a></li>
                <li><a class="dropdown-item" href="permissions/">
                    <i class="fas fa-shield-alt me-2"></i>Permissions
                </a></li>
                <li><a class="dropdown-item" href="security/">
                    <i class="fas fa-lock me-2"></i>Sécurité
                </a></li>
            </ul>
        </div>
    </div>
</div>

<!-- Statistiques rapides -->
<div class="row mb-4">
    <div class="col-lg-3 col-md-6 mb-3">
        <div class="card text-white bg-primary">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h4><?php echo $stats['total_users']; ?></h4>
                        <p class="mb-0">Total utilisateurs</p>
                    </div>
                    <div class="align-self-center">
                        <i class="fas fa-users fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-3 col-md-6 mb-3">
        <div class="card text-white bg-success">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h4><?php echo $stats['active_users']; ?></h4>
                        <p class="mb-0">Utilisateurs actifs</p>
                    </div>
                    <div class="align-self-center">
                        <i class="fas fa-user-check fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-3 col-md-6 mb-3">
        <div class="card text-white bg-info">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h4><?php echo $active_sessions; ?></h4>
                        <p class="mb-0">Sessions actives</p>
                    </div>
                    <div class="align-self-center">
                        <i class="fas fa-desktop fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-3 col-md-6 mb-3">
        <div class="card text-white bg-warning">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h4><?php echo $stats['recent_logins']; ?></h4>
                        <p class="mb-0">Connexions récentes</p>
                    </div>
                    <div class="align-self-center">
                        <i class="fas fa-sign-in-alt fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modules de gestion des utilisateurs -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-th-large me-2"></i>
                    Modules de gestion
                </h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-lg-3 col-md-6 mb-3">
                        <a href="list.php" class="text-decoration-none">
                            <div class="card h-100 border-0 shadow-sm hover-card">
                                <div class="card-body text-center">
                                    <i class="fas fa-users fa-3x text-primary mb-3"></i>
                                    <h5 class="card-title">Liste des Utilisateurs</h5>
                                    <p class="card-text text-muted">
                                        Consulter et gérer tous les utilisateurs
                                    </p>
                                    <div class="mt-3">
                                        <span class="badge bg-primary"><?php echo $stats['total_users']; ?> utilisateurs</span>
                                    </div>
                                </div>
                            </div>
                        </a>
                    </div>
                    
                    <div class="col-lg-3 col-md-6 mb-3">
                        <a href="roles/" class="text-decoration-none">
                            <div class="card h-100 border-0 shadow-sm hover-card">
                                <div class="card-body text-center">
                                    <i class="fas fa-shield-alt fa-3x text-success mb-3"></i>
                                    <h5 class="card-title">Rôles & Permissions</h5>
                                    <p class="card-text text-muted">
                                        Gérer les rôles et permissions
                                    </p>
                                    <div class="mt-3">
                                        <span class="badge bg-success">Contrôle d'accès</span>
                                    </div>
                                </div>
                            </div>
                        </a>
                    </div>
                    
                    <div class="col-lg-3 col-md-6 mb-3">
                        <a href="sessions/" class="text-decoration-none">
                            <div class="card h-100 border-0 shadow-sm hover-card">
                                <div class="card-body text-center">
                                    <i class="fas fa-desktop fa-3x text-info mb-3"></i>
                                    <h5 class="card-title">Sessions Actives</h5>
                                    <p class="card-text text-muted">
                                        Surveiller les connexions en cours
                                    </p>
                                    <div class="mt-3">
                                        <span class="badge bg-info"><?php echo $active_sessions; ?> actives</span>
                                    </div>
                                </div>
                            </div>
                        </a>
                    </div>
                    
                    <div class="col-lg-3 col-md-6 mb-3">
                        <a href="logs/" class="text-decoration-none">
                            <div class="card h-100 border-0 shadow-sm hover-card">
                                <div class="card-body text-center">
                                    <i class="fas fa-history fa-3x text-warning mb-3"></i>
                                    <h5 class="card-title">Historique</h5>
                                    <p class="card-text text-muted">
                                        Consulter l'historique des actions
                                    </p>
                                    <div class="mt-3">
                                        <span class="badge bg-warning">Audit complet</span>
                                    </div>
                                </div>
                            </div>
                        </a>
                    </div>
                </div>
                
                <!-- Modules de sécurité -->
                <div class="row">
                    <div class="col-lg-3 col-md-6 mb-3">
                        <a href="security/" class="text-decoration-none">
                            <div class="card h-100 border-0 shadow-sm hover-card">
                                <div class="card-body text-center">
                                    <i class="fas fa-lock fa-3x text-danger mb-3"></i>
                                    <h5 class="card-title">Sécurité</h5>
                                    <p class="card-text text-muted">
                                        Paramètres de sécurité et audit
                                    </p>
                                    <div class="mt-3">
                                        <span class="badge bg-danger">SHA1 + Audit</span>
                                    </div>
                                </div>
                            </div>
                        </a>
                    </div>
                    
                    <div class="col-lg-3 col-md-6 mb-3">
                        <a href="profile/" class="text-decoration-none">
                            <div class="card h-100 border-0 shadow-sm hover-card">
                                <div class="card-body text-center">
                                    <i class="fas fa-user-cog fa-3x text-secondary mb-3"></i>
                                    <h5 class="card-title">Mon Profil</h5>
                                    <p class="card-text text-muted">
                                        Gérer mon profil utilisateur
                                    </p>
                                    <div class="mt-3">
                                        <span class="badge bg-secondary">Personnel</span>
                                    </div>
                                </div>
                            </div>
                        </a>
                    </div>
                    
                    <div class="col-lg-3 col-md-6 mb-3">
                        <a href="backup/" class="text-decoration-none">
                            <div class="card h-100 border-0 shadow-sm hover-card">
                                <div class="card-body text-center">
                                    <i class="fas fa-database fa-3x text-dark mb-3"></i>
                                    <h5 class="card-title">Sauvegarde</h5>
                                    <p class="card-text text-muted">
                                        Sauvegarder les données utilisateurs
                                    </p>
                                    <div class="mt-3">
                                        <span class="badge bg-dark">Backup & Restore</span>
                                    </div>
                                </div>
                            </div>
                        </a>
                    </div>
                    
                    <div class="col-lg-3 col-md-6 mb-3">
                        <a href="reports/" class="text-decoration-none">
                            <div class="card h-100 border-0 shadow-sm hover-card">
                                <div class="card-body text-center">
                                    <i class="fas fa-chart-bar fa-3x text-muted mb-3"></i>
                                    <h5 class="card-title">Rapports</h5>
                                    <p class="card-text text-muted">
                                        Rapports d'utilisation et statistiques
                                    </p>
                                    <div class="mt-3">
                                        <span class="badge bg-light text-dark">Analytics</span>
                                    </div>
                                </div>
                            </div>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Contenu principal -->
<div class="row">
    <div class="col-lg-8">
        <!-- Utilisateurs récemment créés -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-user-plus me-2"></i>
                    Utilisateurs récemment créés
                </h5>
            </div>
            <div class="card-body">
                <?php if (!empty($recent_users)): ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Utilisateur</th>
                                    <th>Nom complet</th>
                                    <th>Rôle</th>
                                    <th>Statut</th>
                                    <th>Créé le</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent_users as $user): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo htmlspecialchars($user['username']); ?></strong>
                                        </td>
                                        <td>
                                            <?php echo htmlspecialchars($user['nom'] . ' ' . $user['prenom']); ?>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?php 
                                                echo $user['role'] === 'admin' ? 'danger' : 
                                                    ($user['role'] === 'directeur' ? 'warning' : 'info'); 
                                            ?>">
                                                <?php echo ucfirst($user['role']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?php echo $user['status'] === 'actif' ? 'success' : 'secondary'; ?>">
                                                <?php echo ucfirst($user['status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php echo formatDate($user['created_at']); ?>
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <a href="view.php?id=<?php echo $user['id']; ?>" 
                                                   class="btn btn-outline-info" title="Voir">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                <?php if (checkPermission('admin')): ?>
                                                    <a href="edit.php?id=<?php echo $user['id']; ?>" 
                                                       class="btn btn-outline-primary" title="Modifier">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="text-center py-4">
                        <i class="fas fa-users fa-3x text-muted mb-3"></i>
                        <p class="text-muted">Aucun utilisateur récent</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Actions récentes -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-history me-2"></i>
                    Actions récentes
                </h5>
            </div>
            <div class="card-body">
                <?php if (!empty($recent_actions)): ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($recent_actions as $action): ?>
                            <div class="list-group-item border-0 px-0">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <h6 class="mb-1">
                                            <i class="fas fa-<?php echo getActionIcon($action['action']); ?> me-2"></i>
                                            <?php echo getActionLabel($action['action']); ?>
                                        </h6>
                                        <p class="mb-1"><?php echo htmlspecialchars($action['details']); ?></p>
                                        <small class="text-muted">
                                            Par <?php echo htmlspecialchars($action['nom'] . ' ' . $action['prenom']); ?>
                                            (@<?php echo htmlspecialchars($action['username']); ?>)
                                        </small>
                                    </div>
                                    <small class="text-muted">
                                        <?php echo formatDateTime($action['created_at']); ?>
                                    </small>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="text-center py-4">
                        <i class="fas fa-history fa-3x text-muted mb-3"></i>
                        <p class="text-muted">Aucune action récente</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <div class="col-lg-4">
        <!-- Répartition par rôle -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-chart-pie me-2"></i>
                    Répartition par rôle
                </h5>
            </div>
            <div class="card-body">
                <?php if (!empty($users_by_role)): ?>
                    <?php foreach ($users_by_role as $role): ?>
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <span class="badge bg-<?php 
                                echo $role['role'] === 'admin' ? 'danger' : 
                                    ($role['role'] === 'directeur' ? 'warning' : 
                                    ($role['role'] === 'enseignant' ? 'primary' : 'info')); 
                            ?>">
                                <?php echo ucfirst($role['role']); ?>
                            </span>
                            <div class="text-end">
                                <strong><?php echo $role['nombre']; ?></strong>
                                <br><small class="text-muted">
                                    <?php echo $stats['total_users'] > 0 ? round(($role['nombre'] / $stats['total_users']) * 100, 1) : 0; ?>%
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
                    <?php if (checkPermission('admin')): ?>
                        <a href="add.php" class="btn btn-outline-primary">
                            <i class="fas fa-user-plus me-2"></i>
                            Nouvel utilisateur
                        </a>
                        <a href="roles/manage.php" class="btn btn-outline-success">
                            <i class="fas fa-shield-alt me-2"></i>
                            Gérer les rôles
                        </a>
                    <?php endif; ?>
                    <a href="list.php" class="btn btn-outline-info">
                        <i class="fas fa-users me-2"></i>
                        Liste complète
                    </a>
                    <a href="sessions/" class="btn btn-outline-warning">
                        <i class="fas fa-desktop me-2"></i>
                        Sessions actives
                    </a>
                    <a href="logs/" class="btn btn-outline-secondary">
                        <i class="fas fa-history me-2"></i>
                        Historique complet
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.hover-card {
    transition: transform 0.2s ease-in-out;
}

.hover-card:hover {
    transform: translateY(-5px);
}
</style>

<?php
// Fonctions helper pour l'affichage
function getActionIcon($action) {
    $icons = [
        'create_user' => 'user-plus',
        'update_user' => 'user-edit',
        'delete_user' => 'user-times',
        'login' => 'sign-in-alt',
        'logout' => 'sign-out-alt',
        'change_password' => 'key',
        'update_profile' => 'user-cog'
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
        'update_profile' => 'Profil mis à jour'
    ];
    return $labels[$action] ?? 'Action inconnue';
}

include '../../includes/footer.php';
?>
