<?php
/**
 * Module de gestion du personnel - Ajouter un membre
 * Application de gestion scolaire - République Démocratique du Congo
 */

require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../includes/functions.php';
require_once '../../includes/permissions-pages.php';

// Vérifier l'authentification et les permissions
requireLogin();
requirePagePermissionFromDB('personnel', 'add', 'create', '../../dashboard.php');

$page_title = 'Ajouter un membre du personnel';

$errors = [];
$success = false;

// Récupérer les rôles disponibles depuis la base de données
$roles = $database->query("SELECT id, nom, description FROM roles WHERE actif = 1 ORDER BY nom")->fetchAll();

// Créer un mapping des fonctions vers les IDs des rôles
$function_to_role_mapping = [];
foreach ($roles as $role) {
    $role_name_lower = strtolower($role['nom']);
    if (strpos($role_name_lower, 'directeur') !== false) {
        $function_to_role_mapping['directeur'] = $role['id'];
        $function_to_role_mapping['sous_directeur'] = $role['id'];
    } elseif (strpos($role_name_lower, 'enseignant') !== false || strpos($role_name_lower, 'professeur') !== false) {
        $function_to_role_mapping['enseignant'] = $role['id'];
    } elseif (strpos($role_name_lower, 'secrétaire') !== false || strpos($role_name_lower, 'secretaire') !== false) {
        $function_to_role_mapping['secretaire'] = $role['id'];
    } elseif (strpos($role_name_lower, 'comptable') !== false) {
        $function_to_role_mapping['comptable'] = $role['id'];
    }
}

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validation des données personnelles
    $matricule = trim(sanitizeInput($_POST['matricule'] ?? ''));
    $nom = trim(sanitizeInput($_POST['nom'] ?? ''));
    $prenom = trim(sanitizeInput($_POST['prenom'] ?? ''));
    $sexe = sanitizeInput($_POST['sexe'] ?? '');
    $date_naissance = trim(sanitizeInput($_POST['date_naissance'] ?? ''));
    $lieu_naissance = trim(sanitizeInput($_POST['lieu_naissance'] ?? ''));
    $adresse = trim(sanitizeInput($_POST['adresse'] ?? ''));
    $telephone = trim(sanitizeInput($_POST['telephone'] ?? ''));
    $email = trim(sanitizeInput($_POST['email'] ?? ''));
    
    // Informations professionnelles
    $fonction = sanitizeInput($_POST['fonction'] ?? '');
    $specialite = trim(sanitizeInput($_POST['specialite'] ?? ''));
    $diplome = trim(sanitizeInput($_POST['diplome'] ?? ''));
    $date_embauche = sanitizeInput($_POST['date_embauche'] ?? '');
    $salaire_base = trim(sanitizeInput($_POST['salaire_base'] ?? ''));
    
    // Informations de compte utilisateur (optionnel)
    $create_account = isset($_POST['create_account']);
    $username = sanitizeInput($_POST['username'] ?? '');
    $user_password = $_POST['user_password'] ?? '';
    $user_role = sanitizeInput($_POST['user_role'] ?? '');
    
    // Validation des champs obligatoires
    if (empty($matricule)) $errors[] = 'Le matricule est obligatoire.';
    if (empty($nom)) $errors[] = 'Le nom est obligatoire.';
    if (empty($prenom)) $errors[] = 'Le prénom est obligatoire.';
    if (empty($sexe)) $errors[] = 'Le sexe est obligatoire.';
    if (empty($fonction)) $errors[] = 'La fonction est obligatoire.';
    if (empty($date_embauche)) $errors[] = 'La date d\'embauche est obligatoire.';
    
    // Vérifier l'unicité du matricule
    if (!empty($matricule)) {
        $stmt = $database->query("SELECT id FROM personnel WHERE matricule = ?", [$matricule]);
        if ($stmt->fetch()) {
            $errors[] = 'Ce matricule existe déjà.';
        }
    }
    
    // Validation de l'email
    if (!empty($email) && !isValidEmail($email)) {
        $errors[] = 'L\'adresse email n\'est pas valide.';
    }
    
    // Validation du téléphone
    if (!empty($telephone) && !isValidPhone($telephone)) {
        $errors[] = 'Le numéro de téléphone n\'est pas valide.';
    }
    
    // Validation du salaire
    if (!empty($salaire_base) && !is_numeric($salaire_base)) {
        $errors[] = 'Le salaire doit être un nombre valide.';
    }
    
    // Validation du compte utilisateur si demandé
    if ($create_account) {
        if (empty($username)) $errors[] = 'Le nom d\'utilisateur est obligatoire pour créer un compte.';
        if (empty($user_password)) $errors[] = 'Le mot de passe est obligatoire pour créer un compte.';
        if (empty($user_role)) $errors[] = 'Le rôle utilisateur est obligatoire pour créer un compte.';
        if (empty($email)) $errors[] = 'L\'adresse email est obligatoire pour créer un compte utilisateur.';
        
        // Vérifier que le rôle existe
        if (!empty($user_role)) {
            $stmt = $database->query("SELECT id FROM roles WHERE id = ? AND actif = 1", [$user_role]);
            if (!$stmt->fetch()) {
                $errors[] = 'Le rôle sélectionné n\'existe pas ou n\'est pas actif.';
            }
        }
        
        // Vérifier la longueur du mot de passe
        if (!empty($user_password) && strlen($user_password) < 8) {
            $errors[] = 'Le mot de passe doit contenir au moins 8 caractères.';
        }
        
        // Vérifier l'unicité du nom d'utilisateur
        if (!empty($username)) {
            $stmt = $database->query("SELECT id FROM users WHERE username = ?", [$username]);
            if ($stmt->fetch()) {
                $errors[] = 'Ce nom d\'utilisateur existe déjà.';
            }
        }
        
        // Vérifier l'unicité de l'email pour les utilisateurs
        if (!empty($email)) {
            $stmt = $database->query("SELECT id FROM users WHERE email = ?", [$email]);
            if ($stmt->fetch()) {
                $errors[] = 'Cette adresse email est déjà utilisée par un autre utilisateur.';
            }
        }
    }
    
    // Si pas d'erreurs, enregistrer le membre du personnel
    if (empty($errors)) {
        try {
            $database->beginTransaction();
            
            $user_id = null;
            
            // Créer le compte utilisateur si demandé
            if ($create_account) {
                $hashed_password = password_hash($user_password, PASSWORD_DEFAULT);
                $sql_user = "INSERT INTO users (username, email, password, nom, prenom, role_id, status) VALUES (?, ?, ?, ?, ?, ?, 'actif')";
                $database->execute($sql_user, [$username, $email, $hashed_password, $nom, $prenom, $user_role]);
                $user_id = $database->lastInsertId();
            }
            
            // Insérer le membre du personnel
            $sql = "INSERT INTO personnel (matricule, nom, prenom, sexe, date_naissance, lieu_naissance, 
                                         adresse, telephone, email, fonction, specialite, diplome, 
                                         date_embauche, salaire_base, user_id) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
            $database->execute($sql, [
                $matricule, $nom, $prenom, $sexe, $date_naissance ?: null, $lieu_naissance ?: null,
                $adresse ?: null, $telephone ?: null, $email ?: null, $fonction, $specialite ?: null, $diplome ?: null,
                $date_embauche, $salaire_base ?: null, $user_id
            ]);
            
            $personnel_id = $database->lastInsertId();
            
            $database->commit();
            
            // Message de succès avec détails
            $success_message = 'Membre du personnel ajouté avec succès !';
            if ($create_account) {
                $success_message .= ' Un compte utilisateur a également été créé avec le nom d\'utilisateur : ' . $username;
            }
            
            showMessage('success', $success_message);
            redirectTo('view.php?id=' . $personnel_id);
            
        } catch (Exception $e) {
            $database->rollback();
            $errors[] = 'Erreur lors de l\'enregistrement : ' . $e->getMessage();
        }
    }
}

include '../../includes/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">
        <i class="fas fa-user-plus me-2"></i>
        Ajouter un membre du personnel
    </h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <a href="index.php" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-left me-1"></i>
            Retour à la liste
        </a>
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

<form method="POST" class="needs-validation" novalidate>
    <div class="row">
        <!-- Informations personnelles -->
        <div class="col-lg-8">
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-user me-2"></i>
                        Informations personnelles
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label for="matricule" class="form-label">Matricule <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <input type="text" 
                                       class="form-control" 
                                       id="matricule" 
                                       name="matricule" 
                                       value="<?php echo htmlspecialchars($_POST['matricule'] ?? ''); ?>"
                                       required>
                                <button class="btn btn-outline-secondary generate-matricule" 
                                        type="button" 
                                        data-prefix="EMP"
                                        title="Générer automatiquement">
                                    <i class="fas fa-magic"></i>
                                </button>
                            </div>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="nom" class="form-label">Nom <span class="text-danger">*</span></label>
                            <input type="text" 
                                   class="form-control" 
                                   id="nom" 
                                   name="nom" 
                                   value="<?php echo htmlspecialchars($_POST['nom'] ?? ''); ?>"
                                   required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="prenom" class="form-label">Prénom <span class="text-danger">*</span></label>
                            <input type="text" 
                                   class="form-control" 
                                   id="prenom" 
                                   name="prenom" 
                                   value="<?php echo htmlspecialchars($_POST['prenom'] ?? ''); ?>"
                                   required>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-3 mb-3">
                            <label for="sexe" class="form-label">Sexe <span class="text-danger">*</span></label>
                            <select class="form-select" id="sexe" name="sexe" required>
                                <option value="">Sélectionner...</option>
                                <option value="M" <?php echo ($_POST['sexe'] ?? '') === 'M' ? 'selected' : ''; ?>>Masculin</option>
                                <option value="F" <?php echo ($_POST['sexe'] ?? '') === 'F' ? 'selected' : ''; ?>>Féminin</option>
                            </select>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label for="date_naissance" class="form-label">Date de naissance</label>
                            <input type="date" 
                                   class="form-control" 
                                   id="date_naissance" 
                                   name="date_naissance" 
                                   value="<?php echo htmlspecialchars($_POST['date_naissance'] ?? ''); ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="lieu_naissance" class="form-label">Lieu de naissance</label>
                            <input type="text" 
                                   class="form-control" 
                                   id="lieu_naissance" 
                                   name="lieu_naissance" 
                                   value="<?php echo htmlspecialchars($_POST['lieu_naissance'] ?? ''); ?>">
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="telephone" class="form-label">Téléphone</label>
                            <input type="tel" 
                                   class="form-control" 
                                   id="telephone" 
                                   name="telephone" 
                                   placeholder="+243 XXX XXX XXX"
                                   value="<?php echo htmlspecialchars($_POST['telephone'] ?? ''); ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="email" class="form-label">Email <span id="email-required" class="text-danger" style="display: none;">*</span></label>
                            <input type="email" 
                                   class="form-control" 
                                   id="email" 
                                   name="email" 
                                   value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
                            <small id="email-help" class="text-muted" style="display: none;">Obligatoire pour créer un compte utilisateur</small>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="adresse" class="form-label">Adresse</label>
                        <textarea class="form-control" 
                                  id="adresse" 
                                  name="adresse" 
                                  rows="2"><?php echo htmlspecialchars($_POST['adresse'] ?? ''); ?></textarea>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Informations professionnelles -->
        <div class="col-lg-4">
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-briefcase me-2"></i>
                        Informations professionnelles
                    </h5>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label for="fonction" class="form-label">Fonction <span class="text-danger">*</span></label>
                        <select class="form-select" id="fonction" name="fonction" required>
                            <option value="">Sélectionner une fonction...</option>
                            <option value="enseignant" <?php echo ($_POST['fonction'] ?? '') === 'enseignant' ? 'selected' : ''; ?>>Enseignant</option>
                            <option value="directeur" <?php echo ($_POST['fonction'] ?? '') === 'directeur' ? 'selected' : ''; ?>>Directeur</option>
                            <option value="sous_directeur" <?php echo ($_POST['fonction'] ?? '') === 'sous_directeur' ? 'selected' : ''; ?>>Sous-directeur</option>
                            <option value="secretaire" <?php echo ($_POST['fonction'] ?? '') === 'secretaire' ? 'selected' : ''; ?>>Secrétaire</option>
                            <option value="comptable" <?php echo ($_POST['fonction'] ?? '') === 'comptable' ? 'selected' : ''; ?>>Comptable</option>
                            <option value="surveillant" <?php echo ($_POST['fonction'] ?? '') === 'surveillant' ? 'selected' : ''; ?>>Surveillant</option>
                            <option value="gardien" <?php echo ($_POST['fonction'] ?? '') === 'gardien' ? 'selected' : ''; ?>>Gardien</option>
                            <option value="autre" <?php echo ($_POST['fonction'] ?? '') === 'autre' ? 'selected' : ''; ?>>Autre</option>
                        </select>
                    </div>
                    
                    <div class="mb-3" id="specialite-group">
                        <label for="specialite" class="form-label">Spécialité</label>
                        <input type="text" 
                               class="form-control" 
                               id="specialite" 
                               name="specialite" 
                               placeholder="Ex: Mathématiques, Français..."
                               value="<?php echo htmlspecialchars($_POST['specialite'] ?? ''); ?>">
                        <small class="text-muted">Pour les enseignants, précisez la matière enseignée</small>
                    </div>
                    
                    <div class="mb-3">
                        <label for="diplome" class="form-label">Diplôme</label>
                        <input type="text" 
                               class="form-control" 
                               id="diplome" 
                               name="diplome" 
                               placeholder="Ex: Licence en Mathématiques"
                               value="<?php echo htmlspecialchars($_POST['diplome'] ?? ''); ?>">
                    </div>
                    
                    <div class="mb-3">
                        <label for="date_embauche" class="form-label">Date d'embauche <span class="text-danger">*</span></label>
                        <input type="date" 
                               class="form-control" 
                               id="date_embauche" 
                               name="date_embauche" 
                               value="<?php echo htmlspecialchars($_POST['date_embauche'] ?? date('Y-m-d')); ?>"
                               required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="salaire_base" class="form-label">Salaire de base (FC)</label>
                        <input type="number" 
                               class="form-control" 
                               id="salaire_base" 
                               name="salaire_base" 
                               min="0" 
                               step="1000"
                               placeholder="Ex: 150000"
                               value="<?php echo htmlspecialchars($_POST['salaire_base'] ?? ''); ?>">
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Compte utilisateur (optionnel) -->
    <div class="row">
        <div class="col-12">
            <div class="card mb-4">
                <div class="card-header">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">
                            <i class="fas fa-user-cog me-2"></i>
                            Compte utilisateur (optionnel)
                        </h5>
                        <div class="form-check form-switch">
                            <input class="form-check-input"
                                   type="checkbox"
                                   id="create_account"
                                   name="create_account"
                                   <?php echo isset($_POST['create_account']) ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="create_account">
                                Créer un compte utilisateur
                            </label>
                        </div>
                    </div>
                </div>
                <div class="card-body" id="account-fields" style="display: <?php echo isset($_POST['create_account']) ? 'block' : 'none'; ?>;">
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        <strong>Information :</strong> Un compte utilisateur permettra à cette personne de se connecter au système avec des permissions spécifiques.
                    </div>

                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label for="username" class="form-label">Nom d'utilisateur</label>
                            <input type="text"
                                   class="form-control"
                                   id="username"
                                   name="username"
                                   placeholder="Ex: jdupont"
                                   value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>">
                            <small class="text-muted">Utilisé pour la connexion</small>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="user_password" class="form-label">Mot de passe</label>
                            <div class="input-group">
                                <input type="password"
                                       class="form-control"
                                       id="user_password"
                                       name="user_password"
                                       placeholder="Mot de passe sécurisé">
                                <button class="btn btn-outline-secondary" 
                                        type="button" 
                                        id="generate-password"
                                        title="Générer un mot de passe sécurisé">
                                    <i class="fas fa-key"></i>
                                </button>
                            </div>
                            <small class="text-muted">Minimum 8 caractères</small>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="user_role" class="form-label">Rôle dans le système</label>
                            <select class="form-select" id="user_role" name="user_role">
                                <option value="">Sélectionner un rôle...</option>
                                <?php foreach ($roles as $role): ?>
                                    <option value="<?php echo $role['id']; ?>" 
                                            <?php echo ($_POST['user_role'] ?? '') == $role['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($role['nom']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <small class="text-muted">Détermine les permissions d'accès</small>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-12">
                            <div class="table-responsive">
                                <table class="table table-sm">
                                    <thead>
                                        <tr>
                                            <th>Rôle</th>
                                            <th>Description</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($roles as $index => $role): ?>
                                            <tr>
                                                <td>
                                                    <span class="badge bg-<?php 
                                                        $colors = ['danger', 'primary', 'info', 'success', 'warning', 'secondary'];
                                                        echo $colors[$index % count($colors)]; 
                                                    ?>">
                                                        <?php echo htmlspecialchars($role['nom']); ?>
                                                    </span>
                                                </td>
                                                <td><?php echo htmlspecialchars($role['description']); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Boutons d'action -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <a href="index.php" class="btn btn-secondary">
                            <i class="fas fa-times me-1"></i>
                            Annuler
                        </a>
                        <div>
                            <button type="reset" class="btn btn-outline-secondary me-2">
                                <i class="fas fa-undo me-1"></i>
                                Réinitialiser
                            </button>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-1"></i>
                                Enregistrer le membre
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</form>

<script>
// Gestion de l'affichage des champs de compte utilisateur
document.getElementById('create_account').addEventListener('change', function() {
    const accountFields = document.getElementById('account-fields');
    const usernameField = document.getElementById('username');
    const passwordField = document.getElementById('user_password');
    const roleField = document.getElementById('user_role');
    const emailField = document.getElementById('email');
    const emailRequired = document.getElementById('email-required');
    const emailHelp = document.getElementById('email-help');

    if (this.checked) {
        accountFields.style.display = 'block';
        usernameField.required = true;
        passwordField.required = true;
        roleField.required = true;
        emailField.required = true;
        emailRequired.style.display = 'inline';
        emailHelp.style.display = 'block';
    } else {
        accountFields.style.display = 'none';
        usernameField.required = false;
        passwordField.required = false;
        roleField.required = false;
        emailField.required = false;
        emailRequired.style.display = 'none';
        emailHelp.style.display = 'none';
    }
});

// Génération automatique du nom d'utilisateur basé sur nom/prénom
document.getElementById('nom').addEventListener('blur', generateUsername);
document.getElementById('prenom').addEventListener('blur', generateUsername);

function generateUsername() {
    const nom = document.getElementById('nom').value.toLowerCase();
    const prenom = document.getElementById('prenom').value.toLowerCase();
    const usernameField = document.getElementById('username');

    if (nom && prenom && !usernameField.value) {
        // Prendre la première lettre du prénom + nom (max 8 caractères)
        const username = (prenom.charAt(0) + nom).substring(0, 8);
        usernameField.value = username.replace(/[^a-z0-9]/g, '');
    }
}

// Affichage conditionnel du champ spécialité
document.getElementById('fonction').addEventListener('change', function() {
    const specialiteGroup = document.getElementById('specialite-group');
    const specialiteField = document.getElementById('specialite');

    if (this.value === 'enseignant') {
        specialiteGroup.style.display = 'block';
        specialiteField.placeholder = 'Ex: Mathématiques, Français, Sciences...';
    } else {
        specialiteGroup.style.display = 'block';
        specialiteField.placeholder = 'Spécialité ou domaine d\'expertise...';
    }
});

// Synchronisation automatique du rôle utilisateur avec la fonction
document.getElementById('fonction').addEventListener('change', function() {
    const roleField = document.getElementById('user_role');
    const createAccountCheckbox = document.getElementById('create_account');

    if (createAccountCheckbox.checked) {
        // Mapping des fonctions vers les IDs des rôles
        const functionToRoleMapping = <?php echo json_encode($function_to_role_mapping); ?>;
        
        const selectedFunction = this.value;
        if (functionToRoleMapping[selectedFunction]) {
            roleField.value = functionToRoleMapping[selectedFunction];
        } else {
            roleField.value = '';
        }
    }
});

// Validation du mot de passe
document.getElementById('user_password').addEventListener('input', function() {
    const password = this.value;
    const minLength = 8;

    if (password.length > 0 && password.length < minLength) {
        this.setCustomValidity(`Le mot de passe doit contenir au moins ${minLength} caractères.`);
    } else {
        this.setCustomValidity('');
    }
});

// Formatage du salaire
document.getElementById('salaire_base').addEventListener('input', function() {
    let value = this.value.replace(/\D/g, '');
    if (value) {
        this.value = parseInt(value);
    }
});

// Génération automatique de mot de passe
document.getElementById('generate-password').addEventListener('click', function() {
    const passwordField = document.getElementById('user_password');
    
    // Caractères pour le mot de passe
    const lowercase = 'abcdefghijklmnopqrstuvwxyz';
    const uppercase = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    const numbers = '0123456789';
    const symbols = '!@#$%^&*';
    
    let password = '';
    
    // Assurer au moins un caractère de chaque type
    password += lowercase[Math.floor(Math.random() * lowercase.length)];
    password += uppercase[Math.floor(Math.random() * uppercase.length)];
    password += numbers[Math.floor(Math.random() * numbers.length)];
    password += symbols[Math.floor(Math.random() * symbols.length)];
    
    // Compléter avec des caractères aléatoires
    const allChars = lowercase + uppercase + numbers + symbols;
    for (let i = 4; i < 12; i++) {
        password += allChars[Math.floor(Math.random() * allChars.length)];
    }
    
    // Mélanger le mot de passe
    password = password.split('').sort(() => Math.random() - 0.5).join('');
    
    passwordField.value = password;
    passwordField.type = 'text'; // Afficher temporairement
    
    // Remettre en mode password après 3 secondes
    setTimeout(() => {
        passwordField.type = 'password';
    }, 3000);
    
    // Message informatif
    const toast = document.createElement('div');
    toast.className = 'toast align-items-center text-white bg-success border-0 position-fixed top-0 end-0 m-3';
    toast.style.zIndex = '9999';
    toast.innerHTML = `
        <div class="d-flex">
            <div class="toast-body">
                <i class="fas fa-key me-2"></i>
                Mot de passe généré ! Il sera masqué dans 3 secondes.
            </div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
        </div>
    `;
    
    document.body.appendChild(toast);
    const bsToast = new bootstrap.Toast(toast);
    bsToast.show();
    
    // Supprimer l'élément après fermeture
    toast.addEventListener('hidden.bs.toast', () => {
        document.body.removeChild(toast);
    });
});
</script>

<?php include '../../includes/footer.php'; ?>
