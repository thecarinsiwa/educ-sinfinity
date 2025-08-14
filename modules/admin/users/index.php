<?php
/**
 * Module Gestion des Utilisateurs
 * Application de gestion scolaire - République Démocratique du Congo
 */

require_once '../../../config/config.php';
require_once '../../../config/database.php';
require_once '../../../includes/functions.php';

// Vérifier l'authentification et les permissions
requireLogin();
if (!checkPermission('admin')) {
    showMessage('error', 'Accès refusé à ce module.');
    redirectTo('../../../dashboard.php');
}

$page_title = 'Gestion des Utilisateurs';

// Traitement des actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = sanitizeInput($_POST['action'] ?? '');
    
    try {
        switch ($action) {
            case 'create_user':
                $username = sanitizeInput($_POST['username'] ?? '');
                $password = sanitizeInput($_POST['password'] ?? '');
                $nom = sanitizeInput($_POST['nom'] ?? '');
                $prenom = sanitizeInput($_POST['prenom'] ?? '');
                $email = sanitizeInput($_POST['email'] ?? '');
                $role = sanitizeInput($_POST['role'] ?? '');
                
                if (!$username || !$password || !$nom || !$prenom || !$role) {
                    throw new Exception('Tous les champs obligatoires doivent être remplis');
                }
                
                // Vérifier que le nom d'utilisateur n'existe pas
                $existing = $database->query(
                    "SELECT id FROM users WHERE username = ?",
                    [$username]
                )->fetch();
                
                if ($existing) {
                    throw new Exception('Ce nom d\'utilisateur existe déjà');
                }
                
                // Créer l'utilisateur avec mot de passe SHA1
                $password_hash = hashPassword($password);
                
                $database->query(
                    "INSERT INTO users (username, password, nom, prenom, email, role, status, created_at) 
                     VALUES (?, ?, ?, ?, ?, ?, 'actif', NOW())",
                    [$username, $password_hash, $nom, $prenom, $email, $role]
                );
                
                $user_id = $database->lastInsertId();
                
                // Enregistrer l'action
                logUserAction(
                    'create_user',
                    'admin',
                    'Utilisateur créé: ' . $username . ' (' . $nom . ' ' . $prenom . ')',
                    $user_id
                );
                
                showMessage('success', 'Utilisateur créé avec succès');
                break;
                
            case 'update_password':
                $user_id = (int)($_POST['user_id'] ?? 0);
                $new_password = sanitizeInput($_POST['new_password'] ?? '');
                
                if (!$user_id || !$new_password) {
                    throw new Exception('ID utilisateur et nouveau mot de passe requis');
                }
                
                // Vérifier que l'utilisateur existe
                $user = $database->query(
                    "SELECT username, nom, prenom FROM users WHERE id = ?",
                    [$user_id]
                )->fetch();
                
                if (!$user) {
                    throw new Exception('Utilisateur non trouvé');
                }
                
                // Mettre à jour le mot de passe avec SHA1
                $password_hash = hashPassword($new_password);
                
                $database->query(
                    "UPDATE users SET password = ?, updated_at = NOW() WHERE id = ?",
                    [$password_hash, $user_id]
                );
                
                // Enregistrer l'action
                logUserAction(
                    'update_password',
                    'admin',
                    'Mot de passe mis à jour pour: ' . $user['username'] . ' (' . $user['nom'] . ' ' . $user['prenom'] . ')',
                    $user_id
                );
                
                showMessage('success', 'Mot de passe mis à jour avec succès');
                break;
                
            case 'toggle_status':
                $user_id = (int)($_POST['user_id'] ?? 0);
                
                if (!$user_id) {
                    throw new Exception('ID utilisateur requis');
                }
                
                // Vérifier que l'utilisateur existe et récupérer son statut actuel
                $user = $database->query(
                    "SELECT username, nom, prenom, status FROM users WHERE id = ?",
                    [$user_id]
                )->fetch();
                
                if (!$user) {
                    throw new Exception('Utilisateur non trouvé');
                }
                
                // Ne pas permettre de désactiver son propre compte
                if ($user_id == $_SESSION['user_id']) {
                    throw new Exception('Vous ne pouvez pas désactiver votre propre compte');
                }
                
                // Basculer le statut
                $new_status = $user['status'] === 'actif' ? 'inactif' : 'actif';
                
                $database->query(
                    "UPDATE users SET status = ?, updated_at = NOW() WHERE id = ?",
                    [$new_status, $user_id]
                );
                
                // Enregistrer l'action
                logUserAction(
                    'toggle_user_status',
                    'admin',
                    'Statut changé pour: ' . $user['username'] . ' (' . $user['nom'] . ' ' . $user['prenom'] . ') - Nouveau statut: ' . $new_status,
                    $user_id
                );
                
                showMessage('success', 'Statut utilisateur mis à jour');
                break;
        }
        
    } catch (Exception $e) {
        showMessage('error', $e->getMessage());
    }
}

// Récupérer la liste des utilisateurs
$users = $database->query(
    "SELECT u.*, 
            (SELECT COUNT(*) FROM user_actions_log WHERE user_id = u.id) as nb_actions,
            (SELECT MAX(created_at) FROM user_actions_log WHERE user_id = u.id) as derniere_action
     FROM users u
     ORDER BY u.created_at DESC"
)->fetchAll();

// Statistiques
$stats = [];
$stats['total_users'] = count($users);
$stats['active_users'] = count(array_filter($users, function($u) { return $u['status'] === 'actif'; }));
$stats['inactive_users'] = $stats['total_users'] - $stats['active_users'];

// Utilisateurs connectés récemment (dernières 24h)
$recent_logins = $database->query(
    "SELECT COUNT(*) as total FROM users WHERE derniere_connexion >= DATE_SUB(NOW(), INTERVAL 24 HOUR)"
)->fetch()['total'];

$stats['recent_logins'] = $recent_logins;

include '../../../includes/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">
        <i class="fas fa-users-cog me-2"></i>
        Gestion des Utilisateurs
    </h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <div class="btn-group me-2">
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createUserModal">
                <i class="fas fa-plus me-1"></i>
                Nouvel utilisateur
            </button>
        </div>
        <div class="btn-group">
            <button type="button" class="btn btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">
                <i class="fas fa-tools me-1"></i>
                Outils
            </button>
            <ul class="dropdown-menu">
                <li><a class="dropdown-item" href="logs.php">
                    <i class="fas fa-history me-2"></i>Historique des actions
                </a></li>
                <li><a class="dropdown-item" href="sessions.php">
                    <i class="fas fa-desktop me-2"></i>Sessions actives
                </a></li>
                <li><a class="dropdown-item" href="permissions.php">
                    <i class="fas fa-shield-alt me-2"></i>Gestion des permissions
                </a></li>
            </ul>
        </div>
    </div>
</div>

<!-- Statistiques -->
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
        <div class="card text-white bg-warning">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h4><?php echo $stats['inactive_users']; ?></h4>
                        <p class="mb-0">Utilisateurs inactifs</p>
                    </div>
                    <div class="align-self-center">
                        <i class="fas fa-user-times fa-2x"></i>
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

<!-- Liste des utilisateurs -->
<div class="card">
    <div class="card-header">
        <h5 class="mb-0">
            <i class="fas fa-list me-2"></i>
            Liste des utilisateurs
        </h5>
    </div>
    <div class="card-body">
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
                        <th>Actions</th>
                        <th>Historique</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $user): ?>
                        <tr>
                            <td>
                                <strong><?php echo htmlspecialchars($user['username']); ?></strong>
                                <br><small class="text-muted">
                                    Créé le <?php echo formatDate($user['created_at']); ?>
                                </small>
                            </td>
                            <td>
                                <?php echo htmlspecialchars($user['nom'] . ' ' . $user['prenom']); ?>
                            </td>
                            <td>
                                <?php echo htmlspecialchars($user['email'] ?? 'Non renseigné'); ?>
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
                                <?php if ($user['derniere_connexion']): ?>
                                    <?php echo formatDateTime($user['derniere_connexion']); ?>
                                <?php else: ?>
                                    <span class="text-muted">Jamais connecté</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="btn-group btn-group-sm">
                                    <button type="button" class="btn btn-outline-warning" 
                                            onclick="changePassword(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['username']); ?>')"
                                            title="Changer mot de passe">
                                        <i class="fas fa-key"></i>
                                    </button>
                                    <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                        <form method="POST" style="display: inline;" 
                                              onsubmit="return confirm('Êtes-vous sûr de vouloir changer le statut de cet utilisateur ?')">
                                            <input type="hidden" name="action" value="toggle_status">
                                            <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                            <button type="submit" class="btn btn-outline-<?php echo $user['status'] === 'actif' ? 'danger' : 'success'; ?>"
                                                    title="<?php echo $user['status'] === 'actif' ? 'Désactiver' : 'Activer'; ?>">
                                                <i class="fas fa-<?php echo $user['status'] === 'actif' ? 'ban' : 'check'; ?>"></i>
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td>
                                <button type="button" class="btn btn-outline-info btn-sm" 
                                        onclick="showUserHistory(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['username']); ?>')"
                                        title="Voir l'historique">
                                    <i class="fas fa-history"></i>
                                    <span class="badge bg-secondary"><?php echo $user['nb_actions']; ?></span>
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal Créer utilisateur -->
<div class="modal fade" id="createUserModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-user-plus me-2"></i>
                    Nouvel utilisateur
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="create_user">
                    
                    <div class="mb-3">
                        <label for="username" class="form-label">Nom d'utilisateur <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="username" name="username" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="password" class="form-label">Mot de passe <span class="text-danger">*</span></label>
                        <input type="password" class="form-control" id="password" name="password" required>
                        <div class="form-text">Le mot de passe sera chiffré en SHA1</div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="nom" class="form-label">Nom <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="nom" name="nom" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="prenom" class="form-label">Prénom <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="prenom" name="prenom" required>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="email" class="form-label">Email</label>
                        <input type="email" class="form-control" id="email" name="email">
                    </div>
                    
                    <div class="mb-3">
                        <label for="role" class="form-label">Rôle <span class="text-danger">*</span></label>
                        <select class="form-select" id="role" name="role" required>
                            <option value="">Sélectionner un rôle</option>
                            <option value="admin">Administrateur</option>
                            <option value="directeur">Directeur</option>
                            <option value="enseignant">Enseignant</option>
                            <option value="secretaire">Secrétaire</option>
                            <option value="comptable">Comptable</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-primary">Créer l'utilisateur</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Changer mot de passe -->
<div class="modal fade" id="passwordModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-key me-2"></i>
                    Changer le mot de passe
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="update_password">
                    <input type="hidden" name="user_id" id="password_user_id">
                    
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        Changement de mot de passe pour: <strong id="password_username"></strong>
                    </div>
                    
                    <div class="mb-3">
                        <label for="new_password" class="form-label">Nouveau mot de passe <span class="text-danger">*</span></label>
                        <input type="password" class="form-control" id="new_password" name="new_password" required>
                        <div class="form-text">Le mot de passe sera automatiquement chiffré en SHA1</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-warning">Changer le mot de passe</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function changePassword(userId, username) {
    document.getElementById('password_user_id').value = userId;
    document.getElementById('password_username').textContent = username;
    document.getElementById('new_password').value = '';
    
    const modal = new bootstrap.Modal(document.getElementById('passwordModal'));
    modal.show();
}

function showUserHistory(userId, username) {
    // Rediriger vers la page d'historique avec l'ID utilisateur
    window.location.href = 'logs.php?user_id=' + userId;
}
</script>

<?php include '../../../includes/footer.php'; ?>
