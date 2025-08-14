<?php
/**
 * Module de gestion du personnel - Créer un compte utilisateur
 * Application de gestion scolaire - République Démocratique du Congo
 */

require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../includes/functions.php';

// Vérifier l'authentification et les permissions
requireLogin();
if (!checkPermission('personnel')) {
    showMessage('error', 'Accès refusé à cette fonctionnalité.');
    redirectTo('index.php');
}

// Récupérer l'ID du membre
$id = (int)($_GET['id'] ?? 0);
if (!$id) {
    showMessage('error', 'Membre du personnel non spécifié.');
    redirectTo('index.php');
}

// Récupérer les informations du membre
$membre = $database->query(
    "SELECT * FROM personnel WHERE id = ?", 
    [$id]
)->fetch();

if (!$membre) {
    showMessage('error', 'Membre du personnel non trouvé.');
    redirectTo('index.php');
}

// Vérifier si le membre a déjà un compte
if ($membre['user_id']) {
    showMessage('warning', 'Ce membre a déjà un compte utilisateur associé.');
    redirectTo('view.php?id=' . $id);
}

$errors = [];

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Récupération des données
    $username = sanitizeInput($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $email = sanitizeInput($_POST['email'] ?? '');
    $role = sanitizeInput($_POST['role'] ?? '');
    
    // Validation des champs obligatoires
    if (empty($username)) $errors[] = 'Le nom d\'utilisateur est obligatoire.';
    if (empty($password)) $errors[] = 'Le mot de passe est obligatoire.';
    if (empty($confirm_password)) $errors[] = 'La confirmation du mot de passe est obligatoire.';
    if (empty($role)) $errors[] = 'Le rôle utilisateur est obligatoire.';
    
    // Validation du nom d'utilisateur
    if (!empty($username)) {
        if (strlen($username) < 3) {
            $errors[] = 'Le nom d\'utilisateur doit contenir au moins 3 caractères.';
        }
        
        // Vérifier l'unicité du nom d'utilisateur
        $stmt = $database->query("SELECT id FROM users WHERE username = ?", [$username]);
        if ($stmt->fetch()) {
            $errors[] = 'Ce nom d\'utilisateur existe déjà.';
        }
    }
    
    // Validation du mot de passe
    if (!empty($password)) {
        if (strlen($password) < 6) {
            $errors[] = 'Le mot de passe doit contenir au moins 6 caractères.';
        }
        
        if ($password !== $confirm_password) {
            $errors[] = 'Les mots de passe ne correspondent pas.';
        }
    }
    
    // Validation de l'email
    if (!empty($email)) {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'L\'adresse email n\'est pas valide.';
        }
        
        // Vérifier l'unicité de l'email
        $stmt = $database->query("SELECT id FROM users WHERE email = ?", [$email]);
        if ($stmt->fetch()) {
            $errors[] = 'Cette adresse email est déjà utilisée.';
        }
    }
    
    // Si pas d'erreurs, créer le compte utilisateur
    if (empty($errors)) {
        try {
            $database->beginTransaction();
            
            // Créer le compte utilisateur
            $hashed_password = hashPassword($password);
            $sql_user = "INSERT INTO users (username, password, nom, prenom, email, telephone, role, status, adresse, date_naissance, genre) 
                         VALUES (?, ?, ?, ?, ?, ?, ?, 'actif', ?, ?, ?)";
            
            $database->execute($sql_user, [
                $username, 
                $hashed_password, 
                $membre['nom'], 
                $membre['prenom'], 
                $email ?: $membre['email'], 
                $membre['telephone'], 
                $role, 
                $membre['adresse'], 
                $membre['date_naissance'], 
                $membre['sexe']
            ]);
            
            $user_id = $database->lastInsertId();
            
            // Associer le compte au membre du personnel
            $database->execute("UPDATE personnel SET user_id = ? WHERE id = ?", [$user_id, $id]);
            
            // Enregistrer l'action dans l'historique
            if (function_exists('logUserAction')) {
                logUserAction(
                    'create_account',
                    'personnel',
                    'Compte utilisateur créé pour: ' . $membre['nom'] . ' ' . $membre['prenom'] . ' (ID: ' . $id . ')',
                    $user_id
                );
            }
            
            $database->commit();
            
            showMessage('success', 'Compte utilisateur créé avec succès !');
            redirectTo('view.php?id=' . $id);
            
        } catch (Exception $e) {
            $database->rollback();
            $errors[] = 'Erreur lors de la création du compte : ' . $e->getMessage();
        }
    }
} else {
    // Pré-remplir certains champs avec les données du membre
    $_POST = [
        'username' => strtolower(substr($membre['prenom'], 0, 1) . $membre['nom']),
        'email' => $membre['email'],
        'role' => $membre['fonction'] === 'enseignant' ? 'enseignant' : 
                 ($membre['fonction'] === 'directeur' ? 'directeur' : 
                 ($membre['fonction'] === 'secretaire' ? 'secretaire' : 
                 ($membre['fonction'] === 'comptable' ? 'comptable' : '')))
    ];
}

$page_title = 'Créer un compte - ' . $membre['nom'] . ' ' . $membre['prenom'];

include '../../includes/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">
        <i class="fas fa-user-plus me-2"></i>
        Créer un compte utilisateur
    </h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <div class="btn-group me-2">
            <a href="view.php?id=<?php echo $id; ?>" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-1"></i>
                Retour aux détails
            </a>
        </div>
        <div class="btn-group">
            <a href="index.php" class="btn btn-outline-secondary">
                <i class="fas fa-list me-1"></i>
                Liste du personnel
            </a>
        </div>
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

<div class="row">
    <!-- Informations du membre -->
    <div class="col-lg-4">
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-user me-2"></i>
                    Membre du personnel
                </h5>
            </div>
            <div class="card-body">
                <div class="text-center mb-3">
                    <i class="fas fa-user-circle fa-4x text-muted"></i>
                </div>
                <table class="table table-borderless table-sm">
                    <tr>
                        <td class="fw-bold">Matricule :</td>
                        <td><?php echo htmlspecialchars($membre['matricule']); ?></td>
                    </tr>
                    <tr>
                        <td class="fw-bold">Nom complet :</td>
                        <td><?php echo htmlspecialchars($membre['nom'] . ' ' . $membre['prenom']); ?></td>
                    </tr>
                    <tr>
                        <td class="fw-bold">Fonction :</td>
                        <td>
                            <span class="badge bg-primary">
                                <?php echo ucfirst(str_replace('_', ' ', $membre['fonction'])); ?>
                            </span>
                        </td>
                    </tr>
                    <tr>
                        <td class="fw-bold">Email :</td>
                        <td><?php echo htmlspecialchars($membre['email'] ?: 'Non renseigné'); ?></td>
                    </tr>
                    <tr>
                        <td class="fw-bold">Téléphone :</td>
                        <td><?php echo htmlspecialchars($membre['telephone'] ?: 'Non renseigné'); ?></td>
                    </tr>
                </table>
            </div>
        </div>
        
        <!-- Informations sur les rôles -->
        <div class="card">
            <div class="card-header">
                <h6 class="mb-0">
                    <i class="fas fa-info-circle me-2"></i>
                    Rôles et permissions
                </h6>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Rôle</th>
                                <th>Permissions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><span class="badge bg-danger">Directeur</span></td>
                                <td><small>Accès complet sauf administration système</small></td>
                            </tr>
                            <tr>
                                <td><span class="badge bg-primary">Enseignant</span></td>
                                <td><small>Gestion des notes, consultation des élèves</small></td>
                            </tr>
                            <tr>
                                <td><span class="badge bg-info">Secrétaire</span></td>
                                <td><small>Gestion des élèves, communication</small></td>
                            </tr>
                            <tr>
                                <td><span class="badge bg-success">Comptable</span></td>
                                <td><small>Gestion financière, rapports</small></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Formulaire de création de compte -->
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-user-cog me-2"></i>
                    Informations du compte utilisateur
                </h5>
            </div>
            <div class="card-body">
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i>
                    <strong>Information :</strong> Ce compte permettra au membre du personnel de se connecter au système 
                    avec les permissions correspondant à son rôle.
                </div>

                <form method="POST" class="needs-validation" novalidate>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="username" class="form-label">Nom d'utilisateur <span class="text-danger">*</span></label>
                            <input type="text" 
                                   class="form-control" 
                                   id="username" 
                                   name="username" 
                                   value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>"
                                   placeholder="Ex: jdupont"
                                   required>
                            <small class="text-muted">Minimum 3 caractères, utilisé pour la connexion</small>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="email" class="form-label">Email de connexion</label>
                            <input type="email" 
                                   class="form-control" 
                                   id="email" 
                                   name="email" 
                                   value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"
                                   placeholder="utilisateur@exemple.com">
                            <small class="text-muted">Optionnel, peut être utilisé pour la récupération de mot de passe</small>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="password" class="form-label">Mot de passe <span class="text-danger">*</span></label>
                            <input type="password" 
                                   class="form-control" 
                                   id="password" 
                                   name="password" 
                                   placeholder="Mot de passe sécurisé"
                                   required>
                            <small class="text-muted">Minimum 6 caractères</small>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="confirm_password" class="form-label">Confirmer le mot de passe <span class="text-danger">*</span></label>
                            <input type="password" 
                                   class="form-control" 
                                   id="confirm_password" 
                                   name="confirm_password" 
                                   placeholder="Répéter le mot de passe"
                                   required>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="role" class="form-label">Rôle dans le système <span class="text-danger">*</span></label>
                        <select class="form-select" id="role" name="role" required>
                            <option value="">Sélectionner un rôle...</option>
                            <option value="directeur" <?php echo ($_POST['role'] ?? '') === 'directeur' ? 'selected' : ''; ?>>Directeur</option>
                            <option value="enseignant" <?php echo ($_POST['role'] ?? '') === 'enseignant' ? 'selected' : ''; ?>>Enseignant</option>
                            <option value="secretaire" <?php echo ($_POST['role'] ?? '') === 'secretaire' ? 'selected' : ''; ?>>Secrétaire</option>
                            <option value="comptable" <?php echo ($_POST['role'] ?? '') === 'comptable' ? 'selected' : ''; ?>>Comptable</option>
                            <option value="surveillant" <?php echo ($_POST['role'] ?? '') === 'surveillant' ? 'selected' : ''; ?>>Surveillant</option>
                        </select>
                        <small class="text-muted">Détermine les permissions d'accès au système</small>
                    </div>
                    
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <strong>Important :</strong> 
                        <ul class="mb-0 mt-2">
                            <li>Le compte sera créé avec le statut "actif" et sera immédiatement utilisable</li>
                            <li>Les informations personnelles (nom, prénom, etc.) seront copiées depuis le dossier personnel</li>
                            <li>L'utilisateur pourra modifier son mot de passe après la première connexion</li>
                        </ul>
                    </div>
                    
                    <!-- Boutons d'action -->
                    <div class="d-flex justify-content-between">
                        <a href="view.php?id=<?php echo $id; ?>" class="btn btn-secondary">
                            <i class="fas fa-times me-1"></i>
                            Annuler
                        </a>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-user-plus me-1"></i>
                            Créer le compte
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
// Génération automatique du nom d'utilisateur
document.getElementById('username').addEventListener('focus', function() {
    if (!this.value) {
        const nom = '<?php echo strtolower($membre['nom']); ?>';
        const prenom = '<?php echo strtolower($membre['prenom']); ?>';
        
        if (nom && prenom) {
            // Prendre la première lettre du prénom + nom (max 8 caractères)
            const username = (prenom.charAt(0) + nom).substring(0, 8);
            this.value = username.replace(/[^a-z0-9]/g, '');
        }
    }
});

// Synchronisation automatique du rôle avec la fonction
document.addEventListener('DOMContentLoaded', function() {
    const fonction = '<?php echo $membre['fonction']; ?>';
    const roleField = document.getElementById('role');
    
    // Suggérer un rôle basé sur la fonction
    switch(fonction) {
        case 'directeur':
        case 'sous_directeur':
            roleField.value = 'directeur';
            break;
        case 'enseignant':
            roleField.value = 'enseignant';
            break;
        case 'secretaire':
            roleField.value = 'secretaire';
            break;
        case 'comptable':
            roleField.value = 'comptable';
            break;
        case 'surveillant':
            roleField.value = 'surveillant';
            break;
    }
});

// Validation du mot de passe en temps réel
document.getElementById('confirm_password').addEventListener('input', function() {
    const password = document.getElementById('password').value;
    const confirmPassword = this.value;
    
    if (confirmPassword && password !== confirmPassword) {
        this.setCustomValidity('Les mots de passe ne correspondent pas.');
    } else {
        this.setCustomValidity('');
    }
});

document.getElementById('password').addEventListener('input', function() {
    const password = this.value;
    const confirmPassword = document.getElementById('confirm_password').value;
    
    if (password.length > 0 && password.length < 6) {
        this.setCustomValidity('Le mot de passe doit contenir au moins 6 caractères.');
    } else {
        this.setCustomValidity('');
    }
    
    // Revalider la confirmation si elle existe
    if (confirmPassword) {
        document.getElementById('confirm_password').dispatchEvent(new Event('input'));
    }
});

// Validation du nom d'utilisateur
document.getElementById('username').addEventListener('input', function() {
    const username = this.value;
    
    if (username.length > 0 && username.length < 3) {
        this.setCustomValidity('Le nom d\'utilisateur doit contenir au moins 3 caractères.');
    } else {
        this.setCustomValidity('');
    }
});
</script>

<?php include '../../includes/footer.php'; ?>
