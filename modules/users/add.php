<?php
/**
 * Module Gestion des Utilisateurs - Ajouter un utilisateur
 * Application de gestion scolaire - République Démocratique du Congo
 */

require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../includes/functions.php';

// Vérifier l'authentification et les permissions
requireLogin();
if (!checkPermission('admin')) {
    showMessage('error', 'Accès refusé à ce module.');
    redirectTo('list.php');
}

$page_title = 'Ajouter un Utilisateur';

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $username = sanitizeInput($_POST['username'] ?? '');
        $password = sanitizeInput($_POST['password'] ?? '');
        $confirm_password = sanitizeInput($_POST['confirm_password'] ?? '');
        $nom = sanitizeInput($_POST['nom'] ?? '');
        $prenom = sanitizeInput($_POST['prenom'] ?? '');
        $email = sanitizeInput($_POST['email'] ?? '');
        $telephone = sanitizeInput($_POST['telephone'] ?? '');
        $role = sanitizeInput($_POST['role'] ?? '');
        $status = sanitizeInput($_POST['status'] ?? 'actif');
        $adresse = sanitizeInput($_POST['adresse'] ?? '');
        $date_naissance = sanitizeInput($_POST['date_naissance'] ?? '');
        $genre = sanitizeInput($_POST['genre'] ?? '');
        
        // Validation
        if (!$username) {
            throw new Exception('Le nom d\'utilisateur est requis');
        }
        
        if (!$password) {
            throw new Exception('Le mot de passe est requis');
        }
        
        if ($password !== $confirm_password) {
            throw new Exception('Les mots de passe ne correspondent pas');
        }
        
        if (strlen($password) < 6) {
            throw new Exception('Le mot de passe doit contenir au moins 6 caractères');
        }
        
        if (!$nom || !$prenom) {
            throw new Exception('Le nom et le prénom sont requis');
        }
        
        if (!$role) {
            throw new Exception('Le rôle est requis');
        }
        
        if ($email && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception('Format d\'email invalide');
        }
        
        // Vérifier que le nom d'utilisateur n'existe pas
        $existing = $database->query(
            "SELECT id FROM users WHERE username = ?",
            [$username]
        )->fetch();
        
        if ($existing) {
            throw new Exception('Ce nom d\'utilisateur existe déjà');
        }
        
        // Vérifier que l'email n'existe pas (si fourni)
        if ($email) {
            $existing_email = $database->query(
                "SELECT id FROM users WHERE email = ?",
                [$email]
            )->fetch();
            
            if ($existing_email) {
                throw new Exception('Cette adresse email est déjà utilisée');
            }
        }
        
        // Commencer une transaction
        $database->beginTransaction();
        
        try {
            // Créer l'utilisateur avec mot de passe SHA1
            $password_hash = hashPassword($password);
            
            $database->query(
                "INSERT INTO users (username, password, nom, prenom, email, telephone, role, status, adresse, date_naissance, genre, created_at) 
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())",
                [$username, $password_hash, $nom, $prenom, $email, $telephone, $role, $status, $adresse, $date_naissance, $genre]
            );
            
            $user_id = $database->lastInsertId();
            
            // Enregistrer l'action dans l'historique
            logUserAction(
                'create_user',
                'users',
                'Utilisateur créé: ' . $username . ' (' . $nom . ' ' . $prenom . ') - Rôle: ' . $role,
                $user_id
            );
            
            // Valider la transaction
            $database->commit();
            
            showMessage('success', 'Utilisateur créé avec succès');
            redirectTo('view.php?id=' . $user_id);
            
        } catch (Exception $e) {
            $database->rollback();
            throw $e;
        }
        
    } catch (Exception $e) {
        showMessage('error', $e->getMessage());
    }
}

include '../../includes/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">
        <i class="fas fa-user-plus me-2"></i>
        Ajouter un Utilisateur
    </h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <div class="btn-group me-2">
            <a href="list.php" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-1"></i>
                Retour à la liste
            </a>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-user-plus me-2"></i>
                    Informations de l'utilisateur
                </h5>
            </div>
            <div class="card-body">
                <form method="POST" id="userForm">
                    <!-- Informations de connexion -->
                    <div class="row mb-4">
                        <div class="col-12">
                            <h6 class="text-primary border-bottom pb-2">
                                <i class="fas fa-key me-2"></i>
                                Informations de connexion
                            </h6>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="username" class="form-label">Nom d'utilisateur <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="username" name="username" 
                                   value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>" required>
                            <div class="form-text">Utilisé pour se connecter au système</div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="role" class="form-label">Rôle <span class="text-danger">*</span></label>
                            <select class="form-select" id="role" name="role" required>
                                <option value="">Sélectionner un rôle</option>
                                <option value="admin" <?php echo ($_POST['role'] ?? '') === 'admin' ? 'selected' : ''; ?>>
                                    Administrateur
                                </option>
                                <option value="directeur" <?php echo ($_POST['role'] ?? '') === 'directeur' ? 'selected' : ''; ?>>
                                    Directeur
                                </option>
                                <option value="enseignant" <?php echo ($_POST['role'] ?? '') === 'enseignant' ? 'selected' : ''; ?>>
                                    Enseignant
                                </option>
                                <option value="secretaire" <?php echo ($_POST['role'] ?? '') === 'secretaire' ? 'selected' : ''; ?>>
                                    Secrétaire
                                </option>
                                <option value="comptable" <?php echo ($_POST['role'] ?? '') === 'comptable' ? 'selected' : ''; ?>>
                                    Comptable
                                </option>
                                <option value="surveillant" <?php echo ($_POST['role'] ?? '') === 'surveillant' ? 'selected' : ''; ?>>
                                    Surveillant
                                </option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="password" class="form-label">Mot de passe <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <input type="password" class="form-control" id="password" name="password" required>
                                <button type="button" class="btn btn-outline-secondary" onclick="togglePassword('password')">
                                    <i class="fas fa-eye" id="password-icon"></i>
                                </button>
                            </div>
                            <div class="form-text">Minimum 6 caractères (sera chiffré en SHA1)</div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="confirm_password" class="form-label">Confirmer le mot de passe <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                                <button type="button" class="btn btn-outline-secondary" onclick="togglePassword('confirm_password')">
                                    <i class="fas fa-eye" id="confirm_password-icon"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Informations personnelles -->
                    <div class="row mb-4">
                        <div class="col-12">
                            <h6 class="text-primary border-bottom pb-2">
                                <i class="fas fa-user me-2"></i>
                                Informations personnelles
                            </h6>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="nom" class="form-label">Nom <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="nom" name="nom" 
                                   value="<?php echo htmlspecialchars($_POST['nom'] ?? ''); ?>" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="prenom" class="form-label">Prénom <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="prenom" name="prenom" 
                                   value="<?php echo htmlspecialchars($_POST['prenom'] ?? ''); ?>" required>
                        </div>
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
                        <div class="col-md-6 mb-3">
                            <label for="date_naissance" class="form-label">Date de naissance</label>
                            <input type="date" class="form-control" id="date_naissance" name="date_naissance" 
                                   value="<?php echo htmlspecialchars($_POST['date_naissance'] ?? ''); ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="genre" class="form-label">Genre</label>
                            <select class="form-select" id="genre" name="genre">
                                <option value="">Non spécifié</option>
                                <option value="M" <?php echo ($_POST['genre'] ?? '') === 'M' ? 'selected' : ''; ?>>Masculin</option>
                                <option value="F" <?php echo ($_POST['genre'] ?? '') === 'F' ? 'selected' : ''; ?>>Féminin</option>
                            </select>
                        </div>
                        <div class="col-12 mb-3">
                            <label for="adresse" class="form-label">Adresse</label>
                            <textarea class="form-control" id="adresse" name="adresse" rows="2"><?php echo htmlspecialchars($_POST['adresse'] ?? ''); ?></textarea>
                        </div>
                    </div>
                    
                    <!-- Paramètres du compte -->
                    <div class="row mb-4">
                        <div class="col-12">
                            <h6 class="text-primary border-bottom pb-2">
                                <i class="fas fa-cog me-2"></i>
                                Paramètres du compte
                            </h6>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="status" class="form-label">Statut du compte</label>
                            <select class="form-select" id="status" name="status">
                                <option value="actif" <?php echo ($_POST['status'] ?? 'actif') === 'actif' ? 'selected' : ''; ?>>
                                    Actif
                                </option>
                                <option value="inactif" <?php echo ($_POST['status'] ?? '') === 'inactif' ? 'selected' : ''; ?>>
                                    Inactif
                                </option>
                            </select>
                            <div class="form-text">Un compte inactif ne peut pas se connecter</div>
                        </div>
                    </div>
                    
                    <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                        <a href="list.php" class="btn btn-secondary me-md-2">
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
        <div class="card">
            <div class="card-header">
                <h6 class="mb-0">
                    <i class="fas fa-info-circle me-2"></i>
                    Informations importantes
                </h6>
            </div>
            <div class="card-body">
                <div class="alert alert-info">
                    <h6><i class="fas fa-shield-alt me-2"></i>Sécurité</h6>
                    <ul class="mb-0">
                        <li>Le mot de passe sera chiffré en SHA1</li>
                        <li>L'utilisateur pourra le modifier après connexion</li>
                        <li>Toutes les actions seront tracées</li>
                    </ul>
                </div>
                
                <div class="alert alert-warning">
                    <h6><i class="fas fa-users-cog me-2"></i>Rôles et permissions</h6>
                    <ul class="mb-0">
                        <li><strong>Admin:</strong> Accès complet</li>
                        <li><strong>Directeur:</strong> Gestion pédagogique</li>
                        <li><strong>Enseignant:</strong> Classes et notes</li>
                        <li><strong>Secrétaire:</strong> Élèves et inscriptions</li>
                        <li><strong>Comptable:</strong> Finances</li>
                        <li><strong>Surveillant:</strong> Discipline</li>
                    </ul>
                </div>
                
                <div class="alert alert-success">
                    <h6><i class="fas fa-history me-2"></i>Traçabilité</h6>
                    <p class="mb-0">
                        Cette création sera enregistrée dans l'historique avec votre nom 
                        (<strong><?php echo htmlspecialchars($_SESSION['username']); ?></strong>).
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>

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
document.getElementById('userForm').addEventListener('submit', function(e) {
    const password = document.getElementById('password').value;
    const confirmPassword = document.getElementById('confirm_password').value;
    
    if (password !== confirmPassword) {
        e.preventDefault();
        alert('Les mots de passe ne correspondent pas');
        return;
    }
    
    if (password.length < 6) {
        e.preventDefault();
        alert('Le mot de passe doit contenir au moins 6 caractères');
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
</script>

<?php include '../../includes/footer.php'; ?>
