<?php
/**
 * Module de gestion du personnel - Modifier un membre
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
$sql = "SELECT p.*, u.username, u.email as user_email, u.role, u.status as user_status
        FROM personnel p 
        LEFT JOIN users u ON p.user_id = u.id
        WHERE p.id = ?";

$membre = $database->query($sql, [$id])->fetch();

if (!$membre) {
    showMessage('error', 'Membre du personnel non trouvé.');
    redirectTo('index.php');
}

$errors = [];

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Récupération et validation des données
    $matricule = sanitizeInput($_POST['matricule'] ?? '');
    $nom = sanitizeInput($_POST['nom'] ?? '');
    $prenom = sanitizeInput($_POST['prenom'] ?? '');
    $sexe = sanitizeInput($_POST['sexe'] ?? '');
    $date_naissance = sanitizeInput($_POST['date_naissance'] ?? '');
    $lieu_naissance = sanitizeInput($_POST['lieu_naissance'] ?? '');
    $adresse = sanitizeInput($_POST['adresse'] ?? '');
    $telephone = sanitizeInput($_POST['telephone'] ?? '');
    $email = sanitizeInput($_POST['email'] ?? '');
    $fonction = sanitizeInput($_POST['fonction'] ?? '');
    $specialite = sanitizeInput($_POST['specialite'] ?? '');
    $diplome = sanitizeInput($_POST['diplome'] ?? '');
    $date_embauche = sanitizeInput($_POST['date_embauche'] ?? '');
    $salaire_base = sanitizeInput($_POST['salaire_base'] ?? '');
    $status = sanitizeInput($_POST['status'] ?? '');
    
    // Validation des champs obligatoires
    if (empty($matricule)) $errors[] = 'Le matricule est obligatoire.';
    if (empty($nom)) $errors[] = 'Le nom est obligatoire.';
    if (empty($prenom)) $errors[] = 'Le prénom est obligatoire.';
    if (empty($sexe)) $errors[] = 'Le sexe est obligatoire.';
    if (empty($fonction)) $errors[] = 'La fonction est obligatoire.';
    if (empty($date_embauche)) $errors[] = 'La date d\'embauche est obligatoire.';
    if (empty($status)) $errors[] = 'Le statut est obligatoire.';
    
    // Vérifier l'unicité du matricule (sauf pour le membre actuel)
    if (!empty($matricule)) {
        $stmt = $database->query("SELECT id FROM personnel WHERE matricule = ? AND id != ?", [$matricule, $id]);
        if ($stmt->fetch()) {
            $errors[] = 'Ce matricule existe déjà.';
        }
    }
    
    // Validation de l'email
    if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'L\'adresse email n\'est pas valide.';
    }
    
    // Validation du salaire
    if (!empty($salaire_base) && (!is_numeric($salaire_base) || $salaire_base < 0)) {
        $errors[] = 'Le salaire doit être un nombre positif.';
    }
    
    // Si pas d'erreurs, mettre à jour le membre du personnel
    if (empty($errors)) {
        try {
            $database->beginTransaction();
            
            // Mettre à jour le membre du personnel
            $sql = "UPDATE personnel SET 
                    matricule = ?, nom = ?, prenom = ?, sexe = ?, date_naissance = ?, lieu_naissance = ?, 
                    adresse = ?, telephone = ?, email = ?, fonction = ?, specialite = ?, diplome = ?, 
                    date_embauche = ?, salaire_base = ?, status = ?
                    WHERE id = ?";
            
            $database->execute($sql, [
                $matricule, $nom, $prenom, $sexe, $date_naissance ?: null, $lieu_naissance,
                $adresse, $telephone, $email, $fonction, $specialite, $diplome,
                $date_embauche, $salaire_base ?: null, $status, $id
            ]);
            
            $database->commit();
            
            showMessage('success', 'Membre du personnel modifié avec succès !');
            redirectTo('view.php?id=' . $id);
            
        } catch (Exception $e) {
            $database->rollback();
            $errors[] = 'Erreur lors de la modification : ' . $e->getMessage();
        }
    }
} else {
    // Pré-remplir le formulaire avec les données existantes
    $_POST = [
        'matricule' => $membre['matricule'],
        'nom' => $membre['nom'],
        'prenom' => $membre['prenom'],
        'sexe' => $membre['sexe'],
        'date_naissance' => $membre['date_naissance'],
        'lieu_naissance' => $membre['lieu_naissance'],
        'adresse' => $membre['adresse'],
        'telephone' => $membre['telephone'],
        'email' => $membre['email'],
        'fonction' => $membre['fonction'],
        'specialite' => $membre['specialite'],
        'diplome' => $membre['diplome'],
        'date_embauche' => $membre['date_embauche'],
        'salaire_base' => $membre['salaire_base'],
        'status' => $membre['status']
    ];
}

$page_title = 'Modifier - ' . $membre['nom'] . ' ' . $membre['prenom'];

include '../../includes/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">
        <i class="fas fa-edit me-2"></i>
        Modifier un membre du personnel
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
                            <input type="text" 
                                   class="form-control" 
                                   id="matricule" 
                                   name="matricule" 
                                   value="<?php echo htmlspecialchars($_POST['matricule'] ?? ''); ?>"
                                   required>
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
                               value="<?php echo htmlspecialchars($_POST['date_embauche'] ?? ''); ?>"
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
                    
                    <div class="mb-3">
                        <label for="status" class="form-label">Statut <span class="text-danger">*</span></label>
                        <select class="form-select" id="status" name="status" required>
                            <option value="">Sélectionner un statut...</option>
                            <option value="actif" <?php echo ($_POST['status'] ?? '') === 'actif' ? 'selected' : ''; ?>>Actif</option>
                            <option value="suspendu" <?php echo ($_POST['status'] ?? '') === 'suspendu' ? 'selected' : ''; ?>>Suspendu</option>
                            <option value="demissionne" <?php echo ($_POST['status'] ?? '') === 'demissionne' ? 'selected' : ''; ?>>Démissionné</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Compte utilisateur associé (information) -->
            <?php if ($membre['user_id']): ?>
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-user-cog me-2"></i>
                            Compte utilisateur associé
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            <strong>Information :</strong> Ce membre a un compte utilisateur associé.
                            Pour modifier les informations de connexion, utilisez la gestion des utilisateurs.
                        </div>
                        <table class="table table-borderless table-sm">
                            <tr>
                                <td class="fw-bold">Nom d'utilisateur :</td>
                                <td><?php echo htmlspecialchars($membre['username']); ?></td>
                            </tr>
                            <tr>
                                <td class="fw-bold">Email de connexion :</td>
                                <td><?php echo htmlspecialchars($membre['user_email']); ?></td>
                            </tr>
                            <tr>
                                <td class="fw-bold">Rôle :</td>
                                <td>
                                    <span class="badge bg-primary">
                                        <?php echo ucfirst($membre['role']); ?>
                                    </span>
                                </td>
                            </tr>
                            <tr>
                                <td class="fw-bold">Statut du compte :</td>
                                <td>
                                    <span class="badge bg-<?php echo $membre['user_status'] === 'actif' ? 'success' : 'danger'; ?>">
                                        <?php echo ucfirst($membre['user_status']); ?>
                                    </span>
                                </td>
                            </tr>
                        </table>

                        <?php if (checkPermission('admin')): ?>
                            <div class="mt-3">
                                <a href="../../admin/users.php?edit=<?php echo $membre['user_id']; ?>"
                                   class="btn btn-sm btn-outline-primary">
                                    <i class="fas fa-cog me-1"></i>
                                    Gérer le compte utilisateur
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php else: ?>
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-user-slash me-2"></i>
                            Compte utilisateur
                        </h5>
                    </div>
                    <div class="card-body text-center">
                        <i class="fas fa-user-slash fa-2x text-muted mb-3"></i>
                        <p class="text-muted">Aucun compte utilisateur associé</p>
                        <?php if (checkPermission('personnel')): ?>
                            <a href="create-account.php?id=<?php echo $membre['id']; ?>"
                               class="btn btn-sm btn-primary">
                                <i class="fas fa-plus me-1"></i>
                                Créer un compte
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Boutons d'action -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <a href="view.php?id=<?php echo $id; ?>" class="btn btn-secondary">
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
                                Enregistrer les modifications
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</form>

<script>
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

// Formatage du salaire
document.getElementById('salaire_base').addEventListener('input', function() {
    let value = this.value.replace(/\D/g, '');
    if (value) {
        this.value = parseInt(value);
    }
});

// Validation du formulaire
document.addEventListener('DOMContentLoaded', function() {
    // Déclencher l'événement change pour la fonction au chargement
    document.getElementById('fonction').dispatchEvent(new Event('change'));
});
</script>

<?php include '../../includes/footer.php'; ?>
