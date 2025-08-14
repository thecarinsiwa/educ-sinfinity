<?php
/**
 * Module de gestion des élèves - Ajouter un élève
 * Application de gestion scolaire - République Démocratique du Congo
 */

require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../includes/functions.php';

// Vérifier l'authentification et les permissions
requireLogin();
if (!checkPermission('students')) {
    showMessage('error', 'Accès refusé à cette fonctionnalité.');
    redirectTo('index.php');
}

$page_title = 'Ajouter un élève';

// Obtenir l'année scolaire actuelle
$current_year = getCurrentAcademicYear();

// Obtenir la liste des classes
$classes = getClasses($current_year['id'] ?? null);

$errors = [];
$success = false;

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validation des données
    $nom = sanitizeInput($_POST['nom'] ?? '');
    $prenom = sanitizeInput($_POST['prenom'] ?? '');
    $sexe = sanitizeInput($_POST['sexe'] ?? '');
    $date_naissance = sanitizeInput($_POST['date_naissance'] ?? '');
    $lieu_naissance = sanitizeInput($_POST['lieu_naissance'] ?? '');
    $adresse = sanitizeInput($_POST['adresse'] ?? '');
    $telephone = sanitizeInput($_POST['telephone'] ?? '');
    $email = sanitizeInput($_POST['email'] ?? '');
    
    // Informations des parents
    $nom_pere = sanitizeInput($_POST['nom_pere'] ?? '');
    $nom_mere = sanitizeInput($_POST['nom_mere'] ?? '');
    $profession_pere = sanitizeInput($_POST['profession_pere'] ?? '');
    $profession_mere = sanitizeInput($_POST['profession_mere'] ?? '');
    $telephone_parent = sanitizeInput($_POST['telephone_parent'] ?? '');
    $personne_contact = sanitizeInput($_POST['personne_contact'] ?? '');
    $telephone_contact = sanitizeInput($_POST['telephone_contact'] ?? '');
    
    // Informations scolaires
    $classe_id = sanitizeInput($_POST['classe_id'] ?? '');
    $numero_matricule = sanitizeInput($_POST['numero_matricule'] ?? '');
    
    // Validation
    if (empty($nom)) $errors[] = 'Le nom est obligatoire.';
    if (empty($prenom)) $errors[] = 'Le prénom est obligatoire.';
    if (empty($sexe)) $errors[] = 'Le sexe est obligatoire.';
    if (empty($date_naissance)) $errors[] = 'La date de naissance est obligatoire.';
    if (empty($classe_id)) $errors[] = 'La classe est obligatoire.';
    if (empty($numero_matricule)) $errors[] = 'Le numéro de matricule est obligatoire.';
    
    // Vérifier l'unicité du matricule
    if (!empty($numero_matricule)) {
        $stmt = $database->query("SELECT id FROM eleves WHERE numero_matricule = ?", [$numero_matricule]);
        if ($stmt->fetch()) {
            $errors[] = 'Ce numéro de matricule existe déjà.';
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
    
    if (!empty($telephone_parent) && !isValidPhone($telephone_parent)) {
        $errors[] = 'Le numéro de téléphone du parent n\'est pas valide.';
    }
    
    if (!empty($telephone_contact) && !isValidPhone($telephone_contact)) {
        $errors[] = 'Le numéro de téléphone de contact n\'est pas valide.';
    }
    
    // Gestion de l'upload de photo
    $photo_filename = null;
    if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
        $upload_result = uploadFile($_FILES['photo'], '../../uploads/photos', ALLOWED_IMAGE_TYPES);
        if ($upload_result['success']) {
            $photo_filename = $upload_result['filename'];
        } else {
            $errors[] = 'Erreur lors du téléchargement de la photo : ' . $upload_result['message'];
        }
    }
    
    // Si pas d'erreurs, enregistrer l'élève
    if (empty($errors)) {
        try {
            $database->beginTransaction();
            
            // Insérer l'élève
            $sql = "INSERT INTO eleves (numero_matricule, nom, prenom, sexe, date_naissance, lieu_naissance,
                                      adresse, telephone, email, nom_pere, nom_mere, profession_pere,
                                      profession_mere, telephone_parent, personne_contact, telephone_contact,
                                      photo, status, date_inscription)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'actif', NOW())";

            $database->execute($sql, [
                $numero_matricule, $nom, $prenom, $sexe, $date_naissance, $lieu_naissance,
                $adresse, $telephone, $email, $nom_pere, $nom_mere, $profession_pere,
                $profession_mere, $telephone_parent, $personne_contact, $telephone_contact,
                $photo_filename
            ]);
            
            $eleve_id = $database->lastInsertId();
            
            // Inscrire l'élève dans la classe
            if (!empty($classe_id) && !empty($current_year['id'])) {
                $sql_inscription = "INSERT INTO inscriptions (eleve_id, classe_id, annee_scolaire_id, date_inscription, status) 
                                   VALUES (?, ?, ?, CURRENT_DATE, 'inscrit')";
                $database->execute($sql_inscription, [$eleve_id, $classe_id, $current_year['id']]);
            }
            
            $database->commit();
            
            showMessage('success', 'Élève ajouté avec succès !');
            redirectTo('view.php?id=' . $eleve_id);
            
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
        Ajouter un élève
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

<form method="POST" enctype="multipart/form-data" class="needs-validation" novalidate>
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
                        <div class="col-md-6 mb-3">
                            <label for="nom" class="form-label">Nom <span class="text-danger">*</span></label>
                            <input type="text" 
                                   class="form-control" 
                                   id="nom" 
                                   name="nom" 
                                   value="<?php echo htmlspecialchars($_POST['nom'] ?? ''); ?>"
                                   required>
                        </div>
                        <div class="col-md-6 mb-3">
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
                        <div class="col-md-4 mb-3">
                            <label for="sexe" class="form-label">Sexe <span class="text-danger">*</span></label>
                            <select class="form-select" id="sexe" name="sexe" required>
                                <option value="">Sélectionner...</option>
                                <option value="M" <?php echo ($_POST['sexe'] ?? '') === 'M' ? 'selected' : ''; ?>>Masculin</option>
                                <option value="F" <?php echo ($_POST['sexe'] ?? '') === 'F' ? 'selected' : ''; ?>>Féminin</option>
                            </select>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="date_naissance" class="form-label">Date de naissance <span class="text-danger">*</span></label>
                            <input type="date" 
                                   class="form-control" 
                                   id="date_naissance" 
                                   name="date_naissance" 
                                   value="<?php echo htmlspecialchars($_POST['date_naissance'] ?? ''); ?>"
                                   required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Âge</label>
                            <div class="form-control-plaintext age-display">-</div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="lieu_naissance" class="form-label">Lieu de naissance</label>
                            <input type="text" 
                                   class="form-control" 
                                   id="lieu_naissance" 
                                   name="lieu_naissance" 
                                   value="<?php echo htmlspecialchars($_POST['lieu_naissance'] ?? ''); ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="telephone" class="form-label">Téléphone</label>
                            <input type="tel" 
                                   class="form-control" 
                                   id="telephone" 
                                   name="telephone" 
                                   placeholder="+243 XXX XXX XXX"
                                   value="<?php echo htmlspecialchars($_POST['telephone'] ?? ''); ?>">
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="email" class="form-label">Email</label>
                            <input type="email" 
                                   class="form-control" 
                                   id="email" 
                                   name="email" 
                                   value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="adresse" class="form-label">Adresse</label>
                            <textarea class="form-control" 
                                      id="adresse" 
                                      name="adresse" 
                                      rows="2"><?php echo htmlspecialchars($_POST['adresse'] ?? ''); ?></textarea>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Photo et informations scolaires -->
        <div class="col-lg-4">
            <!-- Photo -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-camera me-2"></i>
                        Photo
                    </h5>
                </div>
                <div class="card-body text-center">
                    <div class="mb-3">
                        <img id="photo-preview"
                             src="../../assets/images/default-avatar.svg"
                             alt="Photo de l'élève"
                             class="img-thumbnail"
                             style="width: 150px; height: 150px; object-fit: cover;">
                    </div>
                    <input type="file" 
                           class="form-control" 
                           id="photo" 
                           name="photo" 
                           accept="image/*">
                    <small class="text-muted">Formats acceptés : JPG, PNG, GIF (max 5MB)</small>
                </div>
            </div>
            
            <!-- Informations scolaires -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-graduation-cap me-2"></i>
                        Informations scolaires
                    </h5>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label for="numero_matricule" class="form-label">Numéro de matricule <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <input type="text" 
                                   class="form-control" 
                                   id="numero_matricule" 
                                   name="numero_matricule" 
                                   value="<?php echo htmlspecialchars($_POST['numero_matricule'] ?? ''); ?>"
                                   required>
                            <button class="btn btn-outline-secondary generate-matricule" 
                                    type="button" 
                                    data-prefix="STU"
                                    title="Générer automatiquement">
                                <i class="fas fa-magic"></i>
                            </button>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="classe_id" class="form-label">Classe <span class="text-danger">*</span></label>
                        <select class="form-select" id="classe_id" name="classe_id" required>
                            <option value="">Sélectionner une classe...</option>
                            <?php foreach ($classes as $classe): ?>
                                <option value="<?php echo $classe['id']; ?>" 
                                        <?php echo ($_POST['classe_id'] ?? '') == $classe['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($classe['nom'] . ' - ' . ucfirst($classe['niveau'])); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Informations des parents -->
    <div class="row">
        <div class="col-12">
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-users me-2"></i>
                        Informations des parents/tuteurs
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="nom_pere" class="form-label">Nom du père</label>
                            <input type="text"
                                   class="form-control"
                                   id="nom_pere"
                                   name="nom_pere"
                                   value="<?php echo htmlspecialchars($_POST['nom_pere'] ?? ''); ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="profession_pere" class="form-label">Profession du père</label>
                            <input type="text"
                                   class="form-control"
                                   id="profession_pere"
                                   name="profession_pere"
                                   value="<?php echo htmlspecialchars($_POST['profession_pere'] ?? ''); ?>">
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="nom_mere" class="form-label">Nom de la mère</label>
                            <input type="text"
                                   class="form-control"
                                   id="nom_mere"
                                   name="nom_mere"
                                   value="<?php echo htmlspecialchars($_POST['nom_mere'] ?? ''); ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="profession_mere" class="form-label">Profession de la mère</label>
                            <input type="text"
                                   class="form-control"
                                   id="profession_mere"
                                   name="profession_mere"
                                   value="<?php echo htmlspecialchars($_POST['profession_mere'] ?? ''); ?>">
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label for="telephone_parent" class="form-label">Téléphone des parents</label>
                            <input type="tel"
                                   class="form-control"
                                   id="telephone_parent"
                                   name="telephone_parent"
                                   placeholder="+243 XXX XXX XXX"
                                   value="<?php echo htmlspecialchars($_POST['telephone_parent'] ?? ''); ?>">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="personne_contact" class="form-label">Personne de contact</label>
                            <input type="text"
                                   class="form-control"
                                   id="personne_contact"
                                   name="personne_contact"
                                   value="<?php echo htmlspecialchars($_POST['personne_contact'] ?? ''); ?>">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="telephone_contact" class="form-label">Téléphone de contact</label>
                            <input type="tel"
                                   class="form-control"
                                   id="telephone_contact"
                                   name="telephone_contact"
                                   placeholder="+243 XXX XXX XXX"
                                   value="<?php echo htmlspecialchars($_POST['telephone_contact'] ?? ''); ?>">
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
                                Enregistrer l'élève
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</form>

<script>
// Preview de la photo
document.getElementById('photo').addEventListener('change', function(e) {
    const file = e.target.files[0];
    if (file) {
        const reader = new FileReader();
        reader.onload = function(e) {
            document.getElementById('photo-preview').src = e.target.result;
        };
        reader.readAsDataURL(file);
    }
});

// Calcul automatique de l'âge
document.getElementById('date_naissance').addEventListener('change', function() {
    const birthDate = new Date(this.value);
    const today = new Date();
    let age = today.getFullYear() - birthDate.getFullYear();
    const monthDiff = today.getMonth() - birthDate.getMonth();

    if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < birthDate.getDate())) {
        age--;
    }

    document.querySelector('.age-display').textContent = age >= 0 ? age + ' ans' : '-';
});

// Génération automatique du matricule
document.querySelector('.generate-matricule').addEventListener('click', function() {
    const prefix = this.dataset.prefix || 'STU';
    const year = new Date().getFullYear();
    const random = Math.floor(Math.random() * 9999).toString().padStart(4, '0');
    const matricule = prefix + year + random;

    document.getElementById('numero_matricule').value = matricule;
});

// Validation du formulaire
document.querySelector('form').addEventListener('submit', function(e) {
    const requiredFields = this.querySelectorAll('[required]');
    let isValid = true;

    requiredFields.forEach(function(field) {
        if (!field.value.trim()) {
            field.classList.add('is-invalid');
            isValid = false;
        } else {
            field.classList.remove('is-invalid');
        }
    });

    if (!isValid) {
        e.preventDefault();
        showError('Veuillez remplir tous les champs obligatoires.');
    }
});
</script>

<?php include '../../includes/footer.php'; ?>
