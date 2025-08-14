<?php
/**
 * Administration - Gestion des Utilisateurs
 * Application de gestion scolaire - République Démocratique du Congo
 */

require_once '../config/config.php';
require_once '../config/database.php';
require_once '../includes/functions.php';

// Vérifier l'authentification et les permissions
requireLogin();
if (!checkPermission('admin')) {
    showMessage('error', 'Accès refusé à ce module.');
    redirectTo('../dashboard.php');
}

$page_title = 'Administration - Gestion des Utilisateurs';

// Récupérer l'ID utilisateur pour l'édition si spécifié
$edit_user_id = (int)($_GET['edit'] ?? 0);
$edit_user = null;

if ($edit_user_id) {
    $edit_user = $database->query(
        "SELECT u.*, p.matricule, p.fonction 
         FROM users u 
         LEFT JOIN personnel p ON u.id = p.user_id 
         WHERE u.id = ?", 
        [$edit_user_id]
    )->fetch();
    
    if (!$edit_user) {
        showMessage('error', 'Utilisateur non trouvé.');
        redirectTo('users.php');
    }
}

$errors = [];

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
                
                // Créer l'utilisateur
                $password_hash = hashPassword($password);
                
                $database->execute(
                    "INSERT INTO users (username, password, nom, prenom, email, role, status) 
                     VALUES (?, ?, ?, ?, ?, ?, 'actif')",
                    [$username, $password_hash, $nom, $prenom, $email, $role]
                );
                
                $user_id = $database->lastInsertId();
                
                // Enregistrer l'action
                if (function_exists('logUserAction')) {
                    logUserAction(
                        'create_user',
                        'admin',
                        'Utilisateur créé: ' . $username . ' (' . $nom . ' ' . $prenom . ')',
                        $user_id
                    );
                }
                
                showMessage('success', 'Utilisateur créé avec succès');
                break;
                
            case 'update_user':
                $user_id = (int)($_POST['user_id'] ?? 0);
                $username = sanitizeInput($_POST['username'] ?? '');
                $nom = sanitizeInput($_POST['nom'] ?? '');
                $prenom = sanitizeInput($_POST['prenom'] ?? '');
                $email = sanitizeInput($_POST['email'] ?? '');
                $role = sanitizeInput($_POST['role'] ?? '');
                $status = sanitizeInput($_POST['status'] ?? '');
                
                if (!$user_id || !$username || !$nom || !$prenom || !$role || !$status) {
                    throw new Exception('Tous les champs obligatoires doivent être remplis');
                }
                
                // Vérifier que l'utilisateur existe
                $user = $database->query("SELECT * FROM users WHERE id = ?", [$user_id])->fetch();
                if (!$user) {
                    throw new Exception('Utilisateur non trouvé');
                }
                
                // Vérifier l'unicité du nom d'utilisateur (sauf pour l'utilisateur actuel)
                $existing = $database->query(
                    "SELECT id FROM users WHERE username = ? AND id != ?",
                    [$username, $user_id]
                )->fetch();
                
                if ($existing) {
                    throw new Exception('Ce nom d\'utilisateur existe déjà');
                }
                
                // Mettre à jour l'utilisateur
                $database->execute(
                    "UPDATE users SET username = ?, nom = ?, prenom = ?, email = ?, role = ?, status = ? WHERE id = ?",
                    [$username, $nom, $prenom, $email, $role, $status, $user_id]
                );
                
                // Enregistrer l'action
                if (function_exists('logUserAction')) {
                    logUserAction(
                        'update_user',
                        'admin',
                        'Utilisateur modifié: ' . $username . ' (' . $nom . ' ' . $prenom . ')',
                        $user_id
                    );
                }
                
                showMessage('success', 'Utilisateur modifié avec succès');
                redirectTo('users.php');
                break;
                
            case 'update_password':
                $user_id = (int)($_POST['user_id'] ?? 0);
                $new_password = sanitizeInput($_POST['new_password'] ?? '');
                
                if (!$user_id || !$new_password) {
                    throw new Exception('ID utilisateur et nouveau mot de passe requis');
                }
                
                if (strlen($new_password) < 6) {
                    throw new Exception('Le mot de passe doit contenir au moins 6 caractères');
                }
                
                // Vérifier que l'utilisateur existe
                $user = $database->query(
                    "SELECT username, nom, prenom FROM users WHERE id = ?",
                    [$user_id]
                )->fetch();
                
                if (!$user) {
                    throw new Exception('Utilisateur non trouvé');
                }
                
                // Mettre à jour le mot de passe
                $password_hash = hashPassword($new_password);
                
                $database->execute(
                    "UPDATE users SET password = ? WHERE id = ?",
                    [$password_hash, $user_id]
                );
                
                // Enregistrer l'action
                if (function_exists('logUserAction')) {
                    logUserAction(
                        'update_password',
                        'admin',
                        'Mot de passe modifié pour: ' . $user['username'] . ' (' . $user['nom'] . ' ' . $user['prenom'] . ')',
                        $user_id
                    );
                }
                
                showMessage('success', 'Mot de passe mis à jour avec succès');
                break;
                
            case 'toggle_status':
                $user_id = (int)($_POST['user_id'] ?? 0);
                
                if (!$user_id) {
                    throw new Exception('ID utilisateur requis');
                }
                
                // Récupérer l'utilisateur
                $user = $database->query(
                    "SELECT * FROM users WHERE id = ?",
                    [$user_id]
                )->fetch();
                
                if (!$user) {
                    throw new Exception('Utilisateur non trouvé');
                }
                
                // Changer le statut
                $new_status = $user['status'] === 'actif' ? 'inactif' : 'actif';
                
                $database->execute(
                    "UPDATE users SET status = ? WHERE id = ?",
                    [$new_status, $user_id]
                );
                
                // Enregistrer l'action
                if (function_exists('logUserAction')) {
                    logUserAction(
                        'toggle_user_status',
                        'admin',
                        'Statut changé pour: ' . $user['username'] . ' - Nouveau statut: ' . $new_status,
                        $user_id
                    );
                }
                
                showMessage('success', 'Statut utilisateur mis à jour');
                break;
                
            case 'delete_user':
                $user_id = (int)($_POST['user_id'] ?? 0);
                
                if (!$user_id) {
                    throw new Exception('ID utilisateur requis');
                }
                
                // Vérifier que ce n'est pas l'utilisateur connecté
                if ($user_id == $_SESSION['user_id']) {
                    throw new Exception('Vous ne pouvez pas supprimer votre propre compte');
                }
                
                // Récupérer l'utilisateur
                $user = $database->query("SELECT * FROM users WHERE id = ?", [$user_id])->fetch();
                if (!$user) {
                    throw new Exception('Utilisateur non trouvé');
                }
                
                $database->beginTransaction();
                
                // Dissocier de personnel si lié
                $database->execute("UPDATE personnel SET user_id = NULL WHERE user_id = ?", [$user_id]);
                
                // Supprimer l'utilisateur
                $database->execute("DELETE FROM users WHERE id = ?", [$user_id]);
                
                $database->commit();
                
                // Enregistrer l'action
                if (function_exists('logUserAction')) {
                    logUserAction(
                        'delete_user',
                        'admin',
                        'Utilisateur supprimé: ' . $user['username'] . ' (' . $user['nom'] . ' ' . $user['prenom'] . ')',
                        $user_id
                    );
                }
                
                showMessage('success', 'Utilisateur supprimé avec succès');
                break;
        }
        
    } catch (Exception $e) {
        $errors[] = $e->getMessage();
    }
}

// Récupérer la liste des utilisateurs
$users = $database->query(
    "SELECT u.*, p.matricule, p.fonction,
            (SELECT COUNT(*) FROM user_actions_log WHERE user_id = u.id) as nb_actions,
            (SELECT MAX(created_at) FROM user_actions_log WHERE user_id = u.id) as derniere_action
     FROM users u
     LEFT JOIN personnel p ON u.id = p.user_id
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

include '../includes/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">
        <i class="fas fa-users-cog me-2"></i>
        <?php echo $edit_user ? 'Modifier l\'utilisateur' : 'Gestion des Utilisateurs'; ?>
    </h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <?php if ($edit_user): ?>
            <div class="btn-group me-2">
                <a href="users.php" class="btn btn-outline-secondary">
                    <i class="fas fa-arrow-left me-1"></i>
                    Retour à la liste
                </a>
            </div>
        <?php else: ?>
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
                    <li><a class="dropdown-item" href="../modules/admin/pending-users.php">
                        <i class="fas fa-user-clock me-2"></i>Comptes en attente
                    </a></li>
                    <li><a class="dropdown-item" href="#" onclick="exportUsers()">
                        <i class="fas fa-download me-2"></i>Exporter la liste
                    </a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item" href="../modules/admin/logs.php">
                        <i class="fas fa-history me-2"></i>Historique des actions
                    </a></li>
                </ul>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php if (!empty($errors)): ?>
    <div class="alert alert-danger">
        <h6><i class="fas fa-exclamation-triangle me-2"></i>Erreurs détectées :</h6>
        <ul class="mb-0">
            <?php foreach ($errors as $error): ?>
                <li><?php echo htmlspecialchars($error); ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<?php if ($edit_user): ?>
    <!-- Formulaire d'édition d'utilisateur -->
    <div class="row">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-edit me-2"></i>
                        Modifier l'utilisateur
                    </h5>
                </div>
                <div class="card-body">
                    <form method="POST" class="needs-validation" novalidate>
                        <input type="hidden" name="action" value="update_user">
                        <input type="hidden" name="user_id" value="<?php echo $edit_user['id']; ?>">

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="username" class="form-label">Nom d'utilisateur <span class="text-danger">*</span></label>
                                <input type="text"
                                       class="form-control"
                                       id="username"
                                       name="username"
                                       value="<?php echo htmlspecialchars($edit_user['username']); ?>"
                                       required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="email" class="form-label">Email</label>
                                <input type="email"
                                       class="form-control"
                                       id="email"
                                       name="email"
                                       value="<?php echo htmlspecialchars($edit_user['email']); ?>">
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="nom" class="form-label">Nom <span class="text-danger">*</span></label>
                                <input type="text"
                                       class="form-control"
                                       id="nom"
                                       name="nom"
                                       value="<?php echo htmlspecialchars($edit_user['nom']); ?>"
                                       required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="prenom" class="form-label">Prénom <span class="text-danger">*</span></label>
                                <input type="text"
                                       class="form-control"
                                       id="prenom"
                                       name="prenom"
                                       value="<?php echo htmlspecialchars($edit_user['prenom']); ?>"
                                       required>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="role" class="form-label">Rôle <span class="text-danger">*</span></label>
                                <select class="form-select" id="role" name="role" required>
                                    <option value="">Sélectionner un rôle...</option>
                                    <option value="admin" <?php echo $edit_user['role'] === 'admin' ? 'selected' : ''; ?>>Administrateur</option>
                                    <option value="directeur" <?php echo $edit_user['role'] === 'directeur' ? 'selected' : ''; ?>>Directeur</option>
                                    <option value="enseignant" <?php echo $edit_user['role'] === 'enseignant' ? 'selected' : ''; ?>>Enseignant</option>
                                    <option value="secretaire" <?php echo $edit_user['role'] === 'secretaire' ? 'selected' : ''; ?>>Secrétaire</option>
                                    <option value="comptable" <?php echo $edit_user['role'] === 'comptable' ? 'selected' : ''; ?>>Comptable</option>
                                    <option value="surveillant" <?php echo $edit_user['role'] === 'surveillant' ? 'selected' : ''; ?>>Surveillant</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="status" class="form-label">Statut <span class="text-danger">*</span></label>
                                <select class="form-select" id="status" name="status" required>
                                    <option value="actif" <?php echo $edit_user['status'] === 'actif' ? 'selected' : ''; ?>>Actif</option>
                                    <option value="inactif" <?php echo $edit_user['status'] === 'inactif' ? 'selected' : ''; ?>>Inactif</option>
                                </select>
                            </div>
                        </div>

                        <div class="d-flex justify-content-between">
                            <a href="users.php" class="btn btn-secondary">
                                <i class="fas fa-times me-1"></i>
                                Annuler
                            </a>
                            <div>
                                <button type="button" class="btn btn-outline-warning me-2" onclick="changePassword(<?php echo $edit_user['id']; ?>, '<?php echo htmlspecialchars($edit_user['username']); ?>')">
                                    <i class="fas fa-key me-1"></i>
                                    Changer le mot de passe
                                </button>
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save me-1"></i>
                                    Enregistrer
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <!-- Informations sur l'utilisateur -->
            <div class="card mb-4">
                <div class="card-header">
                    <h6 class="mb-0">
                        <i class="fas fa-info-circle me-2"></i>
                        Informations
                    </h6>
                </div>
                <div class="card-body">
                    <table class="table table-borderless table-sm">
                        <tr>
                            <td class="fw-bold">Créé le :</td>
                            <td><?php echo formatDate($edit_user['created_at']); ?></td>
                        </tr>
                        <?php if ($edit_user['derniere_connexion']): ?>
                        <tr>
                            <td class="fw-bold">Dernière connexion :</td>
                            <td><?php echo formatDateTime($edit_user['derniere_connexion']); ?></td>
                        </tr>
                        <?php endif; ?>
                        <?php if ($edit_user['matricule']): ?>
                        <tr>
                            <td class="fw-bold">Personnel associé :</td>
                            <td>
                                <a href="../modules/personnel/view.php?id=<?php echo $edit_user['id']; ?>" class="text-decoration-none">
                                    <?php echo htmlspecialchars($edit_user['matricule']); ?>
                                    <br><small class="text-muted"><?php echo ucfirst($edit_user['fonction']); ?></small>
                                </a>
                            </td>
                        </tr>
                        <?php endif; ?>
                    </table>
                </div>
            </div>

            <!-- Actions rapides -->
            <div class="card">
                <div class="card-header">
                    <h6 class="mb-0">
                        <i class="fas fa-bolt me-2"></i>
                        Actions rapides
                    </h6>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <button type="button" class="btn btn-outline-warning btn-sm" onclick="changePassword(<?php echo $edit_user['id']; ?>, '<?php echo htmlspecialchars($edit_user['username']); ?>')">
                            <i class="fas fa-key me-1"></i>
                            Changer le mot de passe
                        </button>
                        <form method="POST" style="display: inline;">
                            <input type="hidden" name="action" value="toggle_status">
                            <input type="hidden" name="user_id" value="<?php echo $edit_user['id']; ?>">
                            <button type="submit" class="btn btn-outline-<?php echo $edit_user['status'] === 'actif' ? 'danger' : 'success'; ?> btn-sm w-100">
                                <i class="fas fa-<?php echo $edit_user['status'] === 'actif' ? 'ban' : 'check'; ?> me-1"></i>
                                <?php echo $edit_user['status'] === 'actif' ? 'Désactiver' : 'Activer'; ?>
                            </button>
                        </form>
                        <?php if ($edit_user['id'] != $_SESSION['user_id']): ?>
                        <button type="button" class="btn btn-outline-danger btn-sm" onclick="deleteUser(<?php echo $edit_user['id']; ?>, '<?php echo htmlspecialchars($edit_user['username']); ?>')">
                            <i class="fas fa-trash me-1"></i>
                            Supprimer
                        </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

<?php else: ?>
    <!-- Vue principale - Liste des utilisateurs -->

    <!-- Statistiques -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card text-center">
                <div class="card-body">
                    <i class="fas fa-users fa-2x text-primary mb-2"></i>
                    <h4 class="mb-0"><?php echo $stats['total_users']; ?></h4>
                    <small class="text-muted">Total utilisateurs</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-center">
                <div class="card-body">
                    <i class="fas fa-user-check fa-2x text-success mb-2"></i>
                    <h4 class="mb-0"><?php echo $stats['active_users']; ?></h4>
                    <small class="text-muted">Comptes actifs</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-center">
                <div class="card-body">
                    <i class="fas fa-user-times fa-2x text-danger mb-2"></i>
                    <h4 class="mb-0"><?php echo $stats['inactive_users']; ?></h4>
                    <small class="text-muted">Comptes inactifs</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-center">
                <div class="card-body">
                    <i class="fas fa-clock fa-2x text-info mb-2"></i>
                    <h4 class="mb-0"><?php echo $stats['recent_logins']; ?></h4>
                    <small class="text-muted">Connexions 24h</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Liste des utilisateurs -->
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">
                <i class="fas fa-list me-2"></i>
                Liste des utilisateurs (<?php echo count($users); ?>)
            </h5>
        </div>
        <div class="card-body">
            <?php if (!empty($users)): ?>
                <div class="table-responsive">
                    <table class="table table-striped table-hover datatable">
                        <thead>
                            <tr>
                                <th>Utilisateur</th>
                                <th>Nom complet</th>
                                <th>Email</th>
                                <th>Rôle</th>
                                <th>Personnel</th>
                                <th>Statut</th>
                                <th>Dernière connexion</th>
                                <th class="no-sort">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $user): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo htmlspecialchars($user['username']); ?></strong>
                                        <br><small class="text-muted">#<?php echo $user['id']; ?></small>
                                    </td>
                                    <td>
                                        <?php echo htmlspecialchars($user['nom'] . ' ' . $user['prenom']); ?>
                                    </td>
                                    <td>
                                        <?php if ($user['email']): ?>
                                            <a href="mailto:<?php echo $user['email']; ?>" class="text-decoration-none">
                                                <?php echo htmlspecialchars($user['email']); ?>
                                            </a>
                                        <?php else: ?>
                                            <span class="text-muted">Non renseigné</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php
                                        $role_colors = [
                                            'admin' => 'danger',
                                            'directeur' => 'warning',
                                            'enseignant' => 'primary',
                                            'secretaire' => 'info',
                                            'comptable' => 'success',
                                            'surveillant' => 'secondary'
                                        ];
                                        $color = $role_colors[$user['role']] ?? 'secondary';
                                        ?>
                                        <span class="badge bg-<?php echo $color; ?>">
                                            <?php echo ucfirst($user['role']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($user['matricule']): ?>
                                            <a href="../modules/personnel/view.php?id=<?php echo $user['id']; ?>" class="text-decoration-none">
                                                <?php echo htmlspecialchars($user['matricule']); ?>
                                                <br><small class="text-muted"><?php echo ucfirst($user['fonction']); ?></small>
                                            </a>
                                        <?php else: ?>
                                            <span class="text-muted">Non lié</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?php echo $user['status'] === 'actif' ? 'success' : 'danger'; ?>">
                                            <?php echo ucfirst($user['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($user['derniere_connexion']): ?>
                                            <?php echo formatDateTime($user['derniere_connexion']); ?>
                                        <?php else: ?>
                                            <span class="text-muted">Jamais</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <a href="users.php?edit=<?php echo $user['id']; ?>"
                                               class="btn btn-outline-primary"
                                               title="Modifier">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <button type="button"
                                                    class="btn btn-outline-warning"
                                                    onclick="changePassword(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['username']); ?>')"
                                                    title="Changer le mot de passe">
                                                <i class="fas fa-key"></i>
                                            </button>
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="action" value="toggle_status">
                                                <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                <button type="submit"
                                                        class="btn btn-outline-<?php echo $user['status'] === 'actif' ? 'danger' : 'success'; ?>"
                                                        title="<?php echo $user['status'] === 'actif' ? 'Désactiver' : 'Activer'; ?>"
                                                        onclick="return confirm('Êtes-vous sûr de vouloir <?php echo $user['status'] === 'actif' ? 'désactiver' : 'activer'; ?> cet utilisateur ?')">
                                                    <i class="fas fa-<?php echo $user['status'] === 'actif' ? 'ban' : 'check'; ?>"></i>
                                                </button>
                                            </form>
                                            <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                            <button type="button"
                                                    class="btn btn-outline-danger"
                                                    onclick="deleteUser(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['username']); ?>')"
                                                    title="Supprimer">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="text-center py-5">
                    <i class="fas fa-users fa-3x text-muted mb-3"></i>
                    <h5 class="text-muted">Aucun utilisateur trouvé</h5>
                    <p class="text-muted">Commencez par créer le premier utilisateur.</p>
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createUserModal">
                        <i class="fas fa-plus me-1"></i>
                        Créer un utilisateur
                    </button>
                </div>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>

<!-- Modal de création d'utilisateur -->
<div class="modal fade" id="createUserModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-user-plus me-2"></i>
                    Créer un nouvel utilisateur
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="create_user">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="new_username" class="form-label">Nom d'utilisateur <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="new_username" name="username" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="new_password" class="form-label">Mot de passe <span class="text-danger">*</span></label>
                            <input type="password" class="form-control" id="new_password" name="password" required>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="new_nom" class="form-label">Nom <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="new_nom" name="nom" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="new_prenom" class="form-label">Prénom <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="new_prenom" name="prenom" required>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="new_email" class="form-label">Email</label>
                            <input type="email" class="form-control" id="new_email" name="email">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="new_role" class="form-label">Rôle <span class="text-danger">*</span></label>
                            <select class="form-select" id="new_role" name="role" required>
                                <option value="">Sélectionner...</option>
                                <option value="admin">Administrateur</option>
                                <option value="directeur">Directeur</option>
                                <option value="enseignant">Enseignant</option>
                                <option value="secretaire">Secrétaire</option>
                                <option value="comptable">Comptable</option>
                                <option value="surveillant">Surveillant</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times me-1"></i>
                        Annuler
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-1"></i>
                        Créer l'utilisateur
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal de changement de mot de passe -->
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
                <input type="hidden" name="action" value="update_password">
                <input type="hidden" name="user_id" id="password_user_id">
                <div class="modal-body">
                    <p>Changer le mot de passe pour : <strong id="password_username"></strong></p>
                    <div class="mb-3">
                        <label for="new_password_field" class="form-label">Nouveau mot de passe <span class="text-danger">*</span></label>
                        <input type="password" class="form-control" id="new_password_field" name="new_password" required>
                        <small class="text-muted">Minimum 6 caractères</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times me-1"></i>
                        Annuler
                    </button>
                    <button type="submit" class="btn btn-warning">
                        <i class="fas fa-key me-1"></i>
                        Changer le mot de passe
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function changePassword(userId, username) {
    document.getElementById('password_user_id').value = userId;
    document.getElementById('password_username').textContent = username;
    document.getElementById('new_password_field').value = '';

    const modal = new bootstrap.Modal(document.getElementById('passwordModal'));
    modal.show();
}

function deleteUser(userId, username) {
    if (confirm('Êtes-vous sûr de vouloir supprimer l\'utilisateur "' + username + '" ?\n\nCette action est irréversible.')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="action" value="delete_user">
            <input type="hidden" name="user_id" value="${userId}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}

function exportUsers() {
    // Rediriger vers une page d'export (à implémenter)
    alert('Fonctionnalité d\'export à implémenter');
}

// Validation du formulaire de création
document.getElementById('new_password').addEventListener('input', function() {
    if (this.value.length > 0 && this.value.length < 6) {
        this.setCustomValidity('Le mot de passe doit contenir au moins 6 caractères.');
    } else {
        this.setCustomValidity('');
    }
});

// Validation du changement de mot de passe
document.getElementById('new_password_field').addEventListener('input', function() {
    if (this.value.length > 0 && this.value.length < 6) {
        this.setCustomValidity('Le mot de passe doit contenir au moins 6 caractères.');
    } else {
        this.setCustomValidity('');
    }
});
</script>

<?php include '../includes/footer.php'; ?>