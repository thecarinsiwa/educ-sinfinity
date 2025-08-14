<?php
/**
 * Module de gestion du personnel - Ajouter un membre
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

$page_title = 'Ajouter un membre du personnel';

$errors = [];
$success = false;

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validation des données personnelles
    $matricule = sanitizeInput($_POST['matricule'] ?? '');
    $nom = sanitizeInput($_POST['nom'] ?? '');
    $prenom = sanitizeInput($_POST['prenom'] ?? '');
    $sexe = sanitizeInput($_POST['sexe'] ?? '');
    $date_naissance = sanitizeInput($_POST['date_naissance'] ?? '');
    $lieu_naissance = sanitizeInput($_POST['lieu_naissance'] ?? '');
    $adresse = sanitizeInput($_POST['adresse'] ?? '');
    $telephone = sanitizeInput($_POST['telephone'] ?? '');
    $email = sanitizeInput($_POST['email'] ?? '');
    
    // Informations professionnelles
    $fonction = sanitizeInput($_POST['fonction'] ?? '');
    $specialite = sanitizeInput($_POST['specialite'] ?? '');
    $diplome = sanitizeInput($_POST['diplome'] ?? '');
    $date_embauche = sanitizeInput($_POST['date_embauche'] ?? '');
    $salaire_base = sanitizeInput($_POST['salaire_base'] ?? '');
    
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
                $sql_user = "INSERT INTO users (username, email, password, role, status) VALUES (?, ?, ?, ?, 'actif')";
                $database->execute($sql_user, [$username, $email, $hashed_password, $user_role]);
                $user_id = $database->lastInsertId();
            }
            
            // Insérer le membre du personnel
            $sql = "INSERT INTO personnel (matricule, nom, prenom, sexe, date_naissance, lieu_naissance, 
                                         adresse, telephone, email, fonction, specialite, diplome, 
                                         date_embauche, salaire_base, status, user_id) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'actif', ?)";
            
            $database->execute($sql, [
                $matricule, $nom, $prenom, $sexe, $date_naissance, $lieu_naissance,
                $adresse, $telephone, $email, $fonction, $specialite, $diplome,
                $date_embauche, $salaire_base ?: null, $user_id
            ]);
            
            $personnel_id = $database->lastInsertId();
            
            $database->commit();
            
            showMessage('success', 'Membre du personnel ajouté avec succès !');
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
                            <label for="email" class="form-label">Email</label>
                            <input type="email" 
                                   class="form-control" 
                                   id="email" 
                                   name="email" 
                                   value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
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
                            <input type="password"
                                   class="form-control"
                                   id="user_password"
                                   name="user_password"
                                   placeholder="Mot de passe sécurisé">
                            <small class="text-muted">Minimum 8 caractères</small>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="user_role" class="form-label">Rôle dans le système</label>
                            <select class="form-select" id="user_role" name="user_role">
                                <option value="">Sélectionner un rôle...</option>
                                <option value="directeur" <?php echo ($_POST['user_role'] ?? '') === 'directeur' ? 'selected' : ''; ?>>Directeur</option>
                                <option value="enseignant" <?php echo ($_POST['user_role'] ?? '') === 'enseignant' ? 'selected' : ''; ?>>Enseignant</option>
                                <option value="secretaire" <?php echo ($_POST['user_role'] ?? '') === 'secretaire' ? 'selected' : ''; ?>>Secrétaire</option>
                                <option value="comptable" <?php echo ($_POST['user_role'] ?? '') === 'comptable' ? 'selected' : ''; ?>>Comptable</option>
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
                                            <th>Permissions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <td><span class="badge bg-danger">Directeur</span></td>
                                            <td>Accès complet sauf administration système</td>
                                        </tr>
                                        <tr>
                                            <td><span class="badge bg-primary">Enseignant</span></td>
                                            <td>Gestion des notes, consultation des élèves</td>
                                        </tr>
                                        <tr>
                                            <td><span class="badge bg-info">Secrétaire</span></td>
                                            <td>Gestion des élèves, communication</td>
                                        </tr>
                                        <tr>
                                            <td><span class="badge bg-success">Comptable</span></td>
                                            <td>Gestion financière, rapports</td>
                                        </tr>
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

    if (this.checked) {
        accountFields.style.display = 'block';
        usernameField.required = true;
        passwordField.required = true;
        roleField.required = true;
    } else {
        accountFields.style.display = 'none';
        usernameField.required = false;
        passwordField.required = false;
        roleField.required = false;
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
        switch(this.value) {
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
            default:
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
</script>

<?php include '../../includes/footer.php'; ?>
