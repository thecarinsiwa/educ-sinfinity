<?php
/**
 * Administration - Gestion des Utilisateurs
 * Application de gestion scolaire - République Démocratique du Congo
 */

require_once '../config/config.php';
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/permissions.php';

// Vérifier l'authentification et les permissions
requireLogin();
if (!checkUserPermission('users', 'read') && !checkPermission('admin')) {
    showMessage('error', 'Accès refusé à ce module.');
    redirectTo('../dashboard.php');
}

// Récupérer l'ID utilisateur pour l'édition si spécifié
$edit_user_id = (int)($_GET['edit'] ?? 0);
$add_user = isset($_GET['add']);
$edit_user = null;

$page_title = $add_user ? 'Administration - Ajouter un Utilisateur' : 
              ($edit_user_id ? 'Administration - Modifier l\'Utilisateur' : 'Administration - Gestion des Utilisateurs');

if ($edit_user_id) {
    $edit_user = $database->query(
        "SELECT u.*, p.matricule, p.fonction, r.nom as role_nom
         FROM users u 
         LEFT JOIN personnel p ON u.id = p.user_id 
         LEFT JOIN roles r ON u.role_id = r.id
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
                $nature = sanitizeInput($_POST['nature'] ?? 'staff');
                $telephone = sanitizeInput($_POST['telephone'] ?? '');
                $adresse = sanitizeInput($_POST['adresse'] ?? '');
                $genre = sanitizeInput($_POST['genre'] ?? '');
                $date_naissance = sanitizeInput($_POST['date_naissance'] ?? '');
                
                if (!$username || !$password || !$nom || !$prenom || !$role || !$nature) {
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
                
                // Récupérer l'ID du rôle
                $role_stmt = $database->query("SELECT id FROM roles WHERE nom = ?", [$role]);
                $role_data = $role_stmt->fetch();
                
                if (!$role_data) {
                    throw new Exception('Rôle invalide');
                }
                
                // Créer l'utilisateur
                $password_hash = hashPassword($password);
                
                // Convertir la date de naissance si fournie
                $date_naissance_formatted = null;
                if ($date_naissance) {
                    $date_naissance_formatted = date('Y-m-d', strtotime($date_naissance));
                }
                
                $database->execute(
                    "INSERT INTO users (username, password, nom, prenom, email, role_id, nature, telephone, adresse, genre, date_naissance, status) 
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'actif')",
                    [$username, $password_hash, $nom, $prenom, $email, $role_data['id'], $nature, $telephone, $adresse, $genre, $date_naissance_formatted]
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
                redirectTo('users.php');
                break;
                
            case 'update_user':
                $user_id = (int)($_POST['user_id'] ?? 0);
                $username = sanitizeInput($_POST['username'] ?? '');
                $nom = sanitizeInput($_POST['nom'] ?? '');
                $prenom = sanitizeInput($_POST['prenom'] ?? '');
                $email = sanitizeInput($_POST['email'] ?? '');
                $role = sanitizeInput($_POST['role'] ?? '');
                $nature = sanitizeInput($_POST['nature'] ?? 'staff');
                $status = sanitizeInput($_POST['status'] ?? '');
                $telephone = sanitizeInput($_POST['telephone'] ?? '');
                $adresse = sanitizeInput($_POST['adresse'] ?? '');
                $genre = sanitizeInput($_POST['genre'] ?? '');
                $date_naissance = sanitizeInput($_POST['date_naissance'] ?? '');
                
                if (!$user_id || !$username || !$nom || !$prenom || !$role || !$status || !$nature) {
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
                
                // Récupérer l'ID du rôle
                $role_stmt = $database->query("SELECT id FROM roles WHERE nom = ?", [$role]);
                $role_data = $role_stmt->fetch();
                
                if (!$role_data) {
                    throw new Exception('Rôle invalide');
                }
                
                // Convertir la date de naissance si fournie
                $date_naissance_formatted = null;
                if ($date_naissance) {
                    $date_naissance_formatted = date('Y-m-d', strtotime($date_naissance));
                }
                
                // Mettre à jour l'utilisateur
                $database->execute(
                    "UPDATE users SET username = ?, nom = ?, prenom = ?, email = ?, role_id = ?, nature = ?, telephone = ?, adresse = ?, genre = ?, date_naissance = ?, status = ? WHERE id = ?",
                    [$username, $nom, $prenom, $email, $role_data['id'], $nature, $telephone, $adresse, $genre, $date_naissance_formatted, $status, $user_id]
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
    "SELECT u.*, p.matricule, p.fonction, r.nom as role_nom,
            (SELECT COUNT(*) FROM user_actions_log WHERE user_id = u.id) as nb_actions,
            (SELECT MAX(created_at) FROM user_actions_log WHERE user_id = u.id) as derniere_action
     FROM users u
     LEFT JOIN personnel p ON u.id = p.user_id
     LEFT JOIN roles r ON u.role_id = r.id
     ORDER BY u.created_at DESC"
)->fetchAll();

// Statistiques
$stats = [];
$stats['total_users'] = count($users);
$stats['active_users'] = count(array_filter($users, function($u) { return $u['status'] === 'actif'; }));
$stats['inactive_users'] = $stats['total_users'] - $stats['active_users'];

// Statistiques par nature
$stats['admin_users'] = count(array_filter($users, function($u) { return $u['nature'] === 'admin'; }));
$stats['teacher_users'] = count(array_filter($users, function($u) { return $u['nature'] === 'teacher'; }));
$stats['student_users'] = count(array_filter($users, function($u) { return $u['nature'] === 'student'; }));
$stats['parent_users'] = count(array_filter($users, function($u) { return $u['nature'] === 'parent'; }));
$stats['staff_users'] = count(array_filter($users, function($u) { return $u['nature'] === 'staff'; }));

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
        <?php 
        if ($add_user) {
            echo 'Ajouter un Utilisateur';
        } elseif ($edit_user) {
            echo 'Modifier l\'utilisateur';
        } else {
            echo 'Gestion des Utilisateurs';
        }
        ?>
    </h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <?php if ($edit_user || $add_user): ?>
            <div class="btn-group me-2">
                <a href="users.php" class="btn btn-outline-secondary">
                    <i class="fas fa-arrow-left me-1"></i>
                    Retour à la liste
                </a>
            </div>
        <?php else: ?>
            <div class="btn-group me-2">
                <a href="users.php?add" class="btn btn-primary">
                    <i class="fas fa-plus me-1"></i>
                    Nouvel utilisateur
                </a>
                <a href="roles.php" class="btn btn-outline-primary">
                    <i class="fas fa-user-tag me-1"></i>
                    Gérer les rôles
                </a>
            </div>
            <div class="btn-group">
                <button type="button" class="btn btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">
                    <i class="fas fa-tools me-1"></i>
                    Outils
                </button>
                <ul class="dropdown-menu">
                    <li><a class="dropdown-item" href="roles.php">
                        <i class="fas fa-user-tag me-2"></i>Gérer les rôles
                    </a></li>
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
                                    <?php
                                    $roles = $database->query("SELECT * FROM roles WHERE actif = 1 ORDER BY nom")->fetchAll();
                                    foreach ($roles as $role): ?>
                                        <option value="<?php echo $role['nom']; ?>" <?php echo $edit_user['role_nom'] === $role['nom'] ? 'selected' : ''; ?>>
                                            <?php echo ucfirst($role['nom']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="nature" class="form-label">Nature d'utilisateur <span class="text-danger">*</span></label>
                                <select class="form-select" id="nature" name="nature" required>
                                    <option value="">Sélectionner...</option>
                                    <option value="admin" <?php echo ($edit_user['nature'] ?? '') === 'admin' ? 'selected' : ''; ?>>Administrateur</option>
                                    <option value="teacher" <?php echo ($edit_user['nature'] ?? '') === 'teacher' ? 'selected' : ''; ?>>Enseignant</option>
                                    <option value="student" <?php echo ($edit_user['nature'] ?? '') === 'student' ? 'selected' : ''; ?>>Élève</option>
                                    <option value="parent" <?php echo ($edit_user['nature'] ?? '') === 'parent' ? 'selected' : ''; ?>>Parent</option>
                                    <option value="staff" <?php echo ($edit_user['nature'] ?? '') === 'staff' ? 'selected' : ''; ?>>Personnel</option>
                                </select>
                                <small class="form-text text-muted">Détermine le dashboard de l'utilisateur</small>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="telephone" class="form-label">Téléphone</label>
                                <input type="tel"
                                       class="form-control"
                                       id="telephone"
                                       name="telephone"
                                       value="<?php echo htmlspecialchars($edit_user['telephone'] ?? ''); ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="genre" class="form-label">Genre</label>
                                <select class="form-select" id="genre" name="genre">
                                    <option value="">Sélectionner...</option>
                                    <option value="M" <?php echo ($edit_user['genre'] ?? '') === 'M' ? 'selected' : ''; ?>>Masculin</option>
                                    <option value="F" <?php echo ($edit_user['genre'] ?? '') === 'F' ? 'selected' : ''; ?>>Féminin</option>
                                </select>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="date_naissance" class="form-label">Date de naissance</label>
                                <input type="date"
                                       class="form-control"
                                       id="date_naissance"
                                       name="date_naissance"
                                       value="<?php echo $edit_user['date_naissance'] ?? ''; ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="status" class="form-label">Statut <span class="text-danger">*</span></label>
                                <select class="form-select" id="status" name="status" required>
                                    <option value="actif" <?php echo $edit_user['status'] === 'actif' ? 'selected' : ''; ?>>Actif</option>
                                    <option value="inactif" <?php echo $edit_user['status'] === 'inactif' ? 'selected' : ''; ?>>Inactif</option>
                                </select>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="adresse" class="form-label">Adresse</label>
                            <textarea class="form-control"
                                      id="adresse"
                                      name="adresse"
                                      rows="3"><?php echo htmlspecialchars($edit_user['adresse'] ?? ''); ?></textarea>
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
                        <?php if ($edit_user['nature']): ?>
                        <tr>
                            <td class="fw-bold">Nature d'utilisateur :</td>
                            <td>
                                <?php
                                $nature_colors = [
                                    'admin' => 'danger',
                                    'teacher' => 'primary',
                                    'student' => 'success',
                                    'parent' => 'info',
                                    'staff' => 'warning'
                                ];
                                $nature_color = $nature_colors[$edit_user['nature']] ?? 'secondary';
                                $nature_labels = [
                                    'admin' => 'Administrateur',
                                    'teacher' => 'Enseignant',
                                    'student' => 'Élève',
                                    'parent' => 'Parent',
                                    'staff' => 'Personnel'
                                ];
                                $nature_label = $nature_labels[$edit_user['nature']] ?? ucfirst($edit_user['nature']);
                                ?>
                                <span class="badge bg-<?php echo $nature_color; ?>">
                                    <?php echo $nature_label; ?>
                                </span>
                                <br><small class="text-muted">Dashboard: <?php echo ucfirst($edit_user['nature']); ?></small>
                            </td>
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
                        <?php if ($edit_user['telephone']): ?>
                        <tr>
                            <td class="fw-bold">Téléphone :</td>
                            <td>
                                <a href="tel:<?php echo $edit_user['telephone']; ?>" class="text-decoration-none">
                                    <?php echo htmlspecialchars($edit_user['telephone']); ?>
                                </a>
                            </td>
                        </tr>
                        <?php endif; ?>
                        <?php if ($edit_user['email']): ?>
                        <tr>
                            <td class="fw-bold">Email :</td>
                            <td>
                                <a href="mailto:<?php echo $edit_user['email']; ?>" class="text-decoration-none">
                                    <?php echo htmlspecialchars($edit_user['email']); ?>
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
                        <?php if ($edit_user['nature']): ?>
                        <a href="../dashboards/<?php echo $edit_user['nature']; ?>.php" class="btn btn-outline-info btn-sm" target="_blank">
                            <i class="fas fa-tachometer-alt me-1"></i>
                            Voir le dashboard
                        </a>
                        <?php endif; ?>
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

<?php elseif ($add_user): ?>
    <!-- Page d'ajout d'utilisateur -->
    <div class="row">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-user-plus me-2"></i>
                        Créer un nouvel utilisateur
                    </h5>
                </div>
                <div class="card-body">
                    <form method="POST" class="needs-validation" novalidate>
                        <input type="hidden" name="action" value="create_user">

                        <!-- Informations de base -->
                        <h6 class="text-primary mb-3">
                            <i class="fas fa-user me-2"></i>
                            Informations de base
                        </h6>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="username" class="form-label">Nom d'utilisateur <span class="text-danger">*</span></label>
                                <input type="text"
                                       class="form-control"
                                       id="username"
                                       name="username"
                                       required
                                       placeholder="Ex: john.doe">
                                <div class="form-text">Utilisé pour la connexion au système</div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="password" class="form-label">Mot de passe <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <input type="password"
                                           class="form-control"
                                           id="password"
                                           name="password"
                                           required
                                           minlength="6">
                                    <button class="btn btn-outline-secondary" type="button" onclick="togglePassword('password')">
                                        <i class="fas fa-eye" id="password-icon"></i>
                                    </button>
                                </div>
                                <div class="form-text">Minimum 6 caractères</div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="nom" class="form-label">Nom <span class="text-danger">*</span></label>
                                <input type="text"
                                       class="form-control"
                                       id="nom"
                                       name="nom"
                                       required
                                       placeholder="Ex: Doe">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="prenom" class="form-label">Prénom <span class="text-danger">*</span></label>
                                <input type="text"
                                       class="form-control"
                                       id="prenom"
                                       name="prenom"
                                       required
                                       placeholder="Ex: John">
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="email" class="form-label">Email</label>
                                <input type="email"
                                       class="form-control"
                                       id="email"
                                       name="email"
                                       placeholder="Ex: john.doe@example.com">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="telephone" class="form-label">Téléphone</label>
                                <input type="tel"
                                       class="form-control"
                                       id="telephone"
                                       name="telephone"
                                       placeholder="Ex: +243 XXX XXX XXX">
                            </div>
                        </div>

                        <!-- Rôle et Nature -->
                        <h6 class="text-primary mb-3 mt-4">
                            <i class="fas fa-user-tag me-2"></i>
                            Rôle et Nature
                        </h6>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="role" class="form-label">Rôle <span class="text-danger">*</span></label>
                                <select class="form-select" id="role" name="role" required>
                                    <option value="">Sélectionner un rôle...</option>
                                    <?php
                                    $roles = $database->query("SELECT * FROM roles WHERE actif = 1 ORDER BY nom")->fetchAll();
                                    foreach ($roles as $role): ?>
                                        <option value="<?php echo $role['nom']; ?>">
                                            <?php echo ucfirst($role['nom']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="nature" class="form-label">Nature d'utilisateur <span class="text-danger">*</span></label>
                                <select class="form-select" id="nature" name="nature" required>
                                    <option value="">Sélectionner...</option>
                                    <option value="admin">Administrateur</option>
                                    <option value="teacher">Enseignant</option>
                                    <option value="student">Élève</option>
                                    <option value="parent">Parent</option>
                                    <option value="staff">Personnel</option>
                                </select>
                                <div class="form-text">Détermine le dashboard de l'utilisateur</div>
                            </div>
                        </div>

                        <!-- Informations personnelles -->
                        <h6 class="text-primary mb-3 mt-4">
                            <i class="fas fa-id-card me-2"></i>
                            Informations personnelles
                        </h6>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="genre" class="form-label">Genre</label>
                                <select class="form-select" id="genre" name="genre">
                                    <option value="">Sélectionner...</option>
                                    <option value="M">Masculin</option>
                                    <option value="F">Féminin</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="date_naissance" class="form-label">Date de naissance</label>
                                <input type="date"
                                       class="form-control"
                                       id="date_naissance"
                                       name="date_naissance">
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="adresse" class="form-label">Adresse</label>
                            <textarea class="form-control"
                                      id="adresse"
                                      name="adresse"
                                      rows="3"
                                      placeholder="Adresse complète de l'utilisateur"></textarea>
                        </div>

                        <!-- Actions -->
                        <div class="d-flex justify-content-between mt-4">
                            <a href="users.php" class="btn btn-secondary">
                                <i class="fas fa-times me-1"></i>
                                Annuler
                            </a>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-1"></i>
                                Créer l'utilisateur
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <!-- Guide d'aide -->
            <div class="card mb-4">
                <div class="card-header">
                    <h6 class="mb-0">
                        <i class="fas fa-info-circle me-2"></i>
                        Guide d'aide
                    </h6>
                </div>
                <div class="card-body">
                    <h6 class="text-primary">Nature vs Rôle</h6>
                    <ul class="list-unstyled small">
                        <li><strong>Nature :</strong> Détermine le dashboard</li>
                        <li><strong>Rôle :</strong> Détermine les permissions</li>
                    </ul>
                    
                    <h6 class="text-primary mt-3">Suggestions de cohérence :</h6>
                    <ul class="list-unstyled small">
                        <li><span class="badge bg-danger">Admin</span> → Rôles: admin, directeur</li>
                        <li><span class="badge bg-primary">Enseignant</span> → Rôle: enseignant</li>
                        <li><span class="badge bg-success">Élève</span> → Rôle: élève</li>
                        <li><span class="badge bg-info">Parent</span> → Rôle: parent</li>
                        <li><span class="badge bg-warning">Personnel</span> → Rôles: secrétaire, comptable</li>
                    </ul>
                </div>
            </div>

            <!-- Statistiques rapides -->
            <div class="card">
                <div class="card-header">
                    <h6 class="mb-0">
                        <i class="fas fa-chart-bar me-2"></i>
                        Statistiques
                    </h6>
                </div>
                <div class="card-body">
                    <div class="row text-center">
                        <div class="col-6">
                            <h5 class="text-primary mb-0"><?php echo $stats['total_users']; ?></h5>
                            <small class="text-muted">Total</small>
                        </div>
                        <div class="col-6">
                            <h5 class="text-success mb-0"><?php echo $stats['active_users']; ?></h5>
                            <small class="text-muted">Actifs</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

<?php else: ?>
    <!-- Vue principale - Liste des utilisateurs -->

    <!-- Statistiques générales -->
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

    <!-- Statistiques par nature -->
    <div class="row mb-4">
        <div class="col-md-2">
            <div class="card text-center">
                <div class="card-body">
                    <i class="fas fa-user-shield fa-2x text-danger mb-2"></i>
                    <h5 class="mb-0"><?php echo $stats['admin_users']; ?></h5>
                    <small class="text-muted">Administrateurs</small>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card text-center">
                <div class="card-body">
                    <i class="fas fa-chalkboard-teacher fa-2x text-primary mb-2"></i>
                    <h5 class="mb-0"><?php echo $stats['teacher_users']; ?></h5>
                    <small class="text-muted">Enseignants</small>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card text-center">
                <div class="card-body">
                    <i class="fas fa-user-graduate fa-2x text-success mb-2"></i>
                    <h5 class="mb-0"><?php echo $stats['student_users']; ?></h5>
                    <small class="text-muted">Élèves</small>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card text-center">
                <div class="card-body">
                    <i class="fas fa-users fa-2x text-info mb-2"></i>
                    <h5 class="mb-0"><?php echo $stats['parent_users']; ?></h5>
                    <small class="text-muted">Parents</small>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card text-center">
                <div class="card-body">
                    <i class="fas fa-briefcase fa-2x text-warning mb-2"></i>
                    <h5 class="mb-0"><?php echo $stats['staff_users']; ?></h5>
                    <small class="text-muted">Personnel</small>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card text-center">
                <div class="card-body">
                    <i class="fas fa-tachometer-alt fa-2x text-secondary mb-2"></i>
                    <h5 class="mb-0"><?php echo $stats['total_users']; ?></h5>
                    <small class="text-muted">Total</small>
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
                                <th>Nature</th>
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
                                        $color = $role_colors[$user['role_nom']] ?? 'secondary';
                                        ?>
                                        <span class="badge bg-<?php echo $color; ?>">
                                            <?php echo ucfirst($user['role_nom']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php
                                        $nature_colors = [
                                            'admin' => 'danger',
                                            'teacher' => 'primary',
                                            'student' => 'success',
                                            'parent' => 'info',
                                            'staff' => 'warning'
                                        ];
                                        $nature_color = $nature_colors[$user['nature']] ?? 'secondary';
                                        $nature_labels = [
                                            'admin' => 'Admin',
                                            'teacher' => 'Enseignant',
                                            'student' => 'Élève',
                                            'parent' => 'Parent',
                                            'staff' => 'Personnel'
                                        ];
                                        $nature_label = $nature_labels[$user['nature']] ?? ucfirst($user['nature']);
                                        ?>
                                        <span class="badge bg-<?php echo $nature_color; ?>">
                                            <?php echo $nature_label; ?>
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
                                            <?php if ($user['nature']): ?>
                                            <a href="../dashboards/<?php echo $user['nature']; ?>.php"
                                               class="btn btn-outline-info"
                                               title="Voir le dashboard"
                                               target="_blank">
                                                <i class="fas fa-tachometer-alt"></i>
                                            </a>
                                            <?php endif; ?>
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
                    <a href="users.php?add" class="btn btn-primary">
                        <i class="fas fa-plus me-1"></i>
                        Créer un utilisateur
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>


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

function togglePassword(fieldId) {
    const field = document.getElementById(fieldId);
    const icon = document.getElementById(fieldId + '-icon');
    
    if (field.type === 'password') {
        field.type = 'text';
        icon.classList.remove('fa-eye');
        icon.classList.add('fa-eye-slash');
    } else {
        field.type = 'password';
        icon.classList.remove('fa-eye-slash');
        icon.classList.add('fa-eye');
    }
}

// Validation du formulaire de création (si l'élément existe)
const newPasswordField = document.getElementById('new_password');
if (newPasswordField) {
    newPasswordField.addEventListener('input', function() {
        if (this.value.length > 0 && this.value.length < 6) {
            this.setCustomValidity('Le mot de passe doit contenir au moins 6 caractères.');
        } else {
            this.setCustomValidity('');
        }
    });
}

// Validation du formulaire de création sur la page dédiée
const passwordField = document.getElementById('password');
if (passwordField) {
    passwordField.addEventListener('input', function() {
        if (this.value.length > 0 && this.value.length < 6) {
            this.setCustomValidity('Le mot de passe doit contenir au moins 6 caractères.');
        } else {
            this.setCustomValidity('');
        }
    });
}


// Validation cohérence nature/rôle
function validateNatureRole() {
    const nature = document.getElementById('nature') || document.getElementById('new_nature');
    const role = document.getElementById('role') || document.getElementById('new_role');
    
    if (nature && role) {
        const natureValue = nature.value;
        const roleValue = role.value;
        
        // Suggestions de cohérence
        const suggestions = {
            'admin': ['admin', 'directeur'],
            'teacher': ['enseignant'],
            'student': ['eleve'],
            'parent': ['parent'],
            'staff': ['secretaire', 'comptable', 'surveillant']
        };
        
        if (natureValue && roleValue && suggestions[natureValue]) {
            const compatibleRoles = suggestions[natureValue];
            if (!compatibleRoles.includes(roleValue.toLowerCase())) {
                // Afficher un avertissement mais ne pas bloquer
                console.log('Attention: La nature "' + natureValue + '" est généralement associée aux rôles: ' + compatibleRoles.join(', '));
            }
        }
    }
}

// Ajouter les event listeners si les éléments existent
document.addEventListener('DOMContentLoaded', function() {
    const natureSelect = document.getElementById('nature') || document.getElementById('new_nature');
    const roleSelect = document.getElementById('role') || document.getElementById('new_role');
    
    if (natureSelect) {
        natureSelect.addEventListener('change', validateNatureRole);
    }
    if (roleSelect) {
        roleSelect.addEventListener('change', validateNatureRole);
    }
    
    // Validation du changement de mot de passe (si l'élément existe)
    const newPasswordField = document.getElementById('new_password_field');
    if (newPasswordField) {
        newPasswordField.addEventListener('input', function() {
            if (this.value.length > 0 && this.value.length < 6) {
                this.setCustomValidity('Le mot de passe doit contenir au moins 6 caractères.');
            } else {
                this.setCustomValidity('');
            }
        });
    }
});
</script>

<?php include '../includes/footer.php'; ?>