<?php
/**
 * Page d'inscription
 * Application de gestion scolaire - République Démocratique du Congo
 */

require_once '../config/config.php';
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/permissions.php';

// Rediriger si déjà connecté
if (isLoggedIn()) {
    redirectTo('../dashboard.php');
}

$error_message = '';
$success_message = '';

// Initialiser la connexion à la base de données
$database = new Database();
$database = $database->connect();

// Récupérer les rôles actifs pour le formulaire
$stmt = $database->prepare("SELECT id, nom, description FROM roles WHERE actif = 1 ORDER BY nom");
$stmt->execute();
$roles = $stmt->fetchAll();

// Traitement du formulaire d'inscription
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $username = sanitizeInput($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        $nom = sanitizeInput($_POST['nom'] ?? '');
        $prenom = sanitizeInput($_POST['prenom'] ?? '');
        $email = sanitizeInput($_POST['email'] ?? '');
        $telephone = sanitizeInput($_POST['telephone'] ?? '');
        $role_id = (int)($_POST['role_id'] ?? 0); // ID du rôle sélectionné
        
        // Validation des champs
        if (empty($username) || empty($password) || empty($nom) || empty($prenom)) {
            throw new Exception('Veuillez remplir tous les champs obligatoires.');
        }
        
        if (!$role_id) {
            throw new Exception('Veuillez sélectionner un rôle.');
        }
        
        if (strlen($username) < 3) {
            throw new Exception('Le nom d\'utilisateur doit contenir au moins 3 caractères.');
        }
        
        if (strlen($password) < 6) {
            throw new Exception('Le mot de passe doit contenir au moins 6 caractères.');
        }
        
        if ($password !== $confirm_password) {
            throw new Exception('Les mots de passe ne correspondent pas.');
        }
        
        if ($email && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception('Format d\'email invalide.');
        }
        
        // Vérifier que le rôle sélectionné existe et est actif
        $stmt = $database->prepare("SELECT id, nom FROM roles WHERE id = ? AND actif = 1");
        $stmt->execute([$role_id]);
        $role_exists = $stmt->fetch();
        
        if (!$role_exists) {
            throw new Exception('Le rôle sélectionné n\'est pas valide.');
        }
        
        // Vérifier que le nom d'utilisateur n'existe pas
        $stmt = $database->prepare("SELECT id FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $existing_user = $stmt->fetch();
        
        if ($existing_user) {
            throw new Exception('Ce nom d\'utilisateur est déjà utilisé.');
        }
        
        // Vérifier que l'email n'existe pas (si fourni)
        if ($email) {
            $stmt = $database->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $existing_email = $stmt->fetch();
            
            if ($existing_email) {
                throw new Exception('Cette adresse email est déjà utilisée.');
            }
        }
        
        // Commencer une transaction
        $database->beginTransaction();
        
        try {
            // Créer l'utilisateur avec statut "inactif" par défaut
            $password_hash = hashPassword($password);
            
            $stmt = $database->prepare(
                "INSERT INTO users (username, password, nom, prenom, email, telephone, role_id, status, created_at) 
                 VALUES (?, ?, ?, ?, ?, ?, ?, 'inactif', NOW())"
            );
            $stmt->execute([$username, $password_hash, $nom, $prenom, $email, $telephone, $role_id]);
            
            $user_id = $database->lastInsertId();
            
            // Enregistrer l'action dans l'historique (si possible)
            if (function_exists('logUserAction')) {
                logUserAction(
                    'user_signup',
                    'auth',
                    'Inscription d\'un nouvel utilisateur: ' . $username . ' (' . $nom . ' ' . $prenom . ') - Rôle: ' . $role_exists['nom'] . ' - Statut: inactif',
                    $user_id
                );
            }
            
            // Valider la transaction
            $database->commit();
            
            $success_message = 'Inscription réussie ! Votre compte a été créé avec le statut "inactif". Veuillez contacter un administrateur pour l\'activation.';
            
        } catch (Exception $e) {
            $database->rollback();
            throw $e;
        }
        
    } catch (Exception $e) {
        $error_message = $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inscription - <?php echo APP_NAME; ?></title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .signup-container {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            padding: 40px;
            width: 100%;
            max-width: 600px;
            margin: 20px;
        }
        
        .logo {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .logo i {
            font-size: 4rem;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .form-control {
            border: 2px solid #e1e5e9;
            border-radius: 10px;
            padding: 12px 15px;
            font-size: 16px;
            transition: all 0.3s ease;
        }
        
        .form-control:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            border-radius: 10px;
            padding: 12px 30px;
            font-size: 16px;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(102, 126, 234, 0.3);
        }
        
        .alert {
            border-radius: 10px;
            border: none;
        }
        
        .text-muted a {
            color: #667eea !important;
            text-decoration: none;
        }
        
        .text-muted a:hover {
            text-decoration: underline;
        }
        
        .form-label {
            font-weight: 600;
            color: #495057;
            margin-bottom: 8px;
        }
        
        .required {
            color: #dc3545;
        }
        
        .status-info {
            background: #e3f2fd;
            border: 1px solid #2196f3;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 20px;
        }
        
        .status-info i {
            color: #2196f3;
        }
    </style>
</head>
<body>
    <div class="signup-container">
        <div class="logo">
            <i class="fas fa-graduation-cap"></i>
            <h2 class="mt-3 mb-0"><?php echo APP_NAME; ?></h2>
            <p class="text-muted">Créer un nouveau compte</p>
        </div>
        
        <div class="status-info">
            <i class="fas fa-info-circle me-2"></i>
            <strong>Information importante :</strong> Votre compte sera créé avec le statut "inactif". 
            Un administrateur devra l'activer avant que vous puissiez vous connecter.
        </div>
        
        <?php if ($error_message): ?>
            <div class="alert alert-danger" role="alert">
                <i class="fas fa-exclamation-triangle me-2"></i>
                <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>
        
        <?php if ($success_message): ?>
            <div class="alert alert-success" role="alert">
                <i class="fas fa-check-circle me-2"></i>
                <?php echo htmlspecialchars($success_message); ?>
            </div>
            <div class="text-center mt-3">
                <a href="login.php" class="btn btn-primary">
                    <i class="fas fa-sign-in-alt me-2"></i>
                    Aller à la connexion
                </a>
            </div>
        <?php else: ?>
            <form method="POST" id="signupForm">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="nom" class="form-label">Nom <span class="required">*</span></label>
                        <input type="text" class="form-control" id="nom" name="nom" 
                               value="<?php echo htmlspecialchars($_POST['nom'] ?? ''); ?>" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="prenom" class="form-label">Prénom <span class="required">*</span></label>
                        <input type="text" class="form-control" id="prenom" name="prenom" 
                               value="<?php echo htmlspecialchars($_POST['prenom'] ?? ''); ?>" required>
                    </div>
                </div>
                
                <div class="mb-3">
                    <label for="username" class="form-label">Nom d'utilisateur <span class="required">*</span></label>
                    <input type="text" class="form-control" id="username" name="username" 
                           value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>" required>
                    <div class="form-text">Au moins 3 caractères, utilisé pour se connecter</div>
                </div>
                
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="password" class="form-label">Mot de passe <span class="required">*</span></label>
                        <div class="input-group">
                            <input type="password" class="form-control" id="password" name="password" required>
                            <button type="button" class="btn btn-outline-secondary" onclick="togglePassword('password')">
                                <i class="fas fa-eye" id="password-icon"></i>
                            </button>
                        </div>
                        <div class="form-text">Au moins 6 caractères</div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="confirm_password" class="form-label">Confirmer le mot de passe <span class="required">*</span></label>
                        <div class="input-group">
                            <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                            <button type="button" class="btn btn-outline-secondary" onclick="togglePassword('confirm_password')">
                                <i class="fas fa-eye" id="confirm_password-icon"></i>
                            </button>
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="email" class="form-label">Email</label>
                        <input type="email" class="form-control" id="email" name="email" 
                               value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="telephone" class="form-label">Téléphone</label>
                        <input type="tel" class="form-control" id="telephone" name="telephone" 
                               value="<?php echo htmlspecialchars($_POST['telephone'] ?? ''); ?>">
                    </div>
                </div>
                
                <div class="mb-4">
                    <label for="role_id" class="form-label">Rôle souhaité <span class="required">*</span></label>
                    <select class="form-select" id="role_id" name="role_id" required>
                        <option value="">Sélectionner un rôle</option>
                        <?php 
                        $selected_role_id = (int)($_POST['role_id'] ?? 0);
                        foreach ($roles as $role): 
                        ?>
                            <option value="<?php echo $role['id']; ?>"
                                    <?php echo $selected_role_id === $role['id'] ? 'selected' : ''; ?>
                                    title="<?php echo htmlspecialchars($role['description']); ?>">
                                <?php echo htmlspecialchars(ucfirst($role['nom'])); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <div class="form-text">
                        Votre rôle pourra être modifié par un administrateur. 
                        <a href="../modules/users/roles/" class="text-decoration-none" target="_blank">
                            <i class="fas fa-info-circle me-1"></i>Voir les permissions des rôles
                        </a>
                    </div>
                </div>
                
                <!-- Affichage des permissions du rôle sélectionné -->
                <div class="mb-4" id="role-permissions" style="display: none;">
                    <label class="form-label">Permissions du rôle sélectionné</label>
                    <div class="card">
                        <div class="card-body">
                            <div id="permissions-content">
                                <!-- Contenu des permissions généré par JavaScript -->
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="d-grid">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-user-plus me-2"></i>
                        Créer mon compte
                    </button>
                </div>
            </form>
        <?php endif; ?>
        
        <div class="text-center mt-4">
            <p class="text-muted">
                Vous avez déjà un compte ? 
                <a href="login.php">Se connecter</a>
            </p>
        </div>
    </div>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
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
        
        // Validation en temps réel
        document.getElementById('signupForm').addEventListener('submit', function(e) {
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            const username = document.getElementById('username').value;
            
            if (username.length < 3) {
                e.preventDefault();
                alert('Le nom d\'utilisateur doit contenir au moins 3 caractères');
                return;
            }
            
            if (password.length < 6) {
                e.preventDefault();
                alert('Le mot de passe doit contenir au moins 6 caractères');
                return;
            }
            
            if (password !== confirmPassword) {
                e.preventDefault();
                alert('Les mots de passe ne correspondent pas');
                return;
            }
        });
        
        // Vérification en temps réel des mots de passe
        document.getElementById('confirm_password').addEventListener('input', function() {
            const password = document.getElementById('password').value;
            const confirmPassword = this.value;
            
            if (confirmPassword && password !== confirmPassword) {
                this.classList.add('is-invalid');
            } else {
                this.classList.remove('is-invalid');
            }
        });
        
        // Génération automatique du nom d'utilisateur
        document.getElementById('nom').addEventListener('input', generateUsername);
        document.getElementById('prenom').addEventListener('input', generateUsername);
        
        function generateUsername() {
            const nom = document.getElementById('nom').value.toLowerCase();
            const prenom = document.getElementById('prenom').value.toLowerCase();
            const usernameField = document.getElementById('username');
            
            if (nom && prenom && !usernameField.value) {
                // Générer un nom d'utilisateur basé sur prénom.nom
                const suggestion = prenom.charAt(0) + nom;
                usernameField.placeholder = 'Suggestion: ' + suggestion;
            }
        }
        
        // Affichage des permissions du rôle sélectionné
        document.getElementById('role_id').addEventListener('change', function() {
            const roleId = this.value;
            const permissionsDiv = document.getElementById('role-permissions');
            const permissionsContent = document.getElementById('permissions-content');
            
            if (roleId) {
                // Récupérer les permissions du rôle via AJAX
                fetch(`../modules/users/roles/get-role-permissions.php?id=${roleId}`)
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            displayPermissions(data.permissions, data.role_name);
                            permissionsDiv.style.display = 'block';
                        } else {
                            permissionsDiv.style.display = 'none';
                        }
                    })
                    .catch(error => {
                        console.error('Erreur lors du chargement des permissions:', error);
                        permissionsDiv.style.display = 'none';
                    });
            } else {
                permissionsDiv.style.display = 'none';
            }
        });
        
        function displayPermissions(permissions, roleName) {
            const modules = {
                'students': 'Gestion des élèves',
                'users': 'Gestion des utilisateurs',
                'finance': 'Gestion financière',
                'academic': 'Gestion académique',
                'reports': 'Rapports',
                'settings': 'Paramètres'
            };
            
            const actions = {
                'read': 'Lire',
                'create': 'Créer',
                'edit': 'Modifier',
                'delete': 'Supprimer'
            };
            
            let html = `<h6>Rôle: <strong>${roleName}</strong></h6>`;
            html += '<div class="row">';
            
            for (const moduleKey in modules) {
                if (permissions[moduleKey] && permissions[moduleKey].length > 0) {
                    html += '<div class="col-md-6 mb-3">';
                    html += `<div class="card border-primary">`;
                    html += `<div class="card-header bg-primary text-white">`;
                    html += `<h6 class="mb-0">${modules[moduleKey]}</h6>`;
                    html += `</div>`;
                    html += `<div class="card-body">`;
                    
                    permissions[moduleKey].forEach(action => {
                        html += `<span class="badge bg-success me-1 mb-1">${actions[action]}</span>`;
                    });
                    
                    html += `</div></div></div>`;
                }
            }
            
            html += '</div>';
            permissionsContent.innerHTML = html;
        }
    </script>
</body>
</html>
