<?php
/**
 * Modification d'un dossier scolaire
 * Application de gestion scolaire - République Démocratique du Congo
 */

require_once '../../../config/config.php';
require_once '../../../config/database.php';
require_once '../../../includes/functions.php';

// Vérifier l'authentification et les permissions
requireLogin();
if (!checkPermission('students')) {
    showMessage('error', 'Accès refusé à ce module.');
    redirectTo('../../dashboard.php');
}

$eleve_id = intval($_GET['id'] ?? 0);

if (!$eleve_id) {
    showMessage('error', 'ID d\'élève invalide.');
    redirectTo('index.php');
}

// Obtenir l'année scolaire actuelle
$current_year = getCurrentAcademicYear();

// Récupérer les informations de l'élève
try {
    $eleve = $database->query(
        "SELECT e.*, i.classe_id, c.nom as classe_nom
         FROM eleves e
         JOIN inscriptions i ON e.id = i.eleve_id
         JOIN classes c ON i.classe_id = c.id
         WHERE e.id = ? AND i.annee_scolaire_id = ?",
        [$eleve_id, $current_year['id'] ?? 0]
    )->fetch();

    if (!$eleve) {
        showMessage('error', 'Élève non trouvé ou non inscrit pour l\'année scolaire actuelle.');
        redirectTo('index.php');
    }
} catch (Exception $e) {
    showMessage('error', 'Erreur lors du chargement de l\'élève : ' . $e->getMessage());
    redirectTo('index.php');
}

// Récupérer les classes disponibles
try {
    $classes = $database->query(
        "SELECT id, nom, niveau, section FROM classes WHERE annee_scolaire_id = ? ORDER BY niveau, nom",
        [$current_year['id'] ?? 0]
    )->fetchAll();
} catch (Exception $e) {
    $classes = [];
}

$errors = [];

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Récupérer et valider les données
    $nom = sanitizeInput($_POST['nom'] ?? '');
    $prenom = sanitizeInput($_POST['prenom'] ?? '');
    $sexe = $_POST['sexe'] ?? '';
    $date_naissance = $_POST['date_naissance'] ?? '';
    $lieu_naissance = sanitizeInput($_POST['lieu_naissance'] ?? '');

    $adresse = sanitizeInput($_POST['adresse'] ?? '');
    $telephone = sanitizeInput($_POST['telephone'] ?? '');
    $email = sanitizeInput($_POST['email'] ?? '');
    $nom_pere = sanitizeInput($_POST['nom_pere'] ?? '');
    $nom_mere = sanitizeInput($_POST['nom_mere'] ?? '');
    $profession_pere = sanitizeInput($_POST['profession_pere'] ?? '');
    $profession_mere = sanitizeInput($_POST['profession_mere'] ?? '');
    $telephone_parent = sanitizeInput($_POST['telephone_parent'] ?? '');
    $classe_id = intval($_POST['classe_id'] ?? 0);
    $status = $_POST['status'] ?? 'actif';
    
    // Gestion de la photo
    $photo_path = $eleve['photo']; // Garder la photo existante par défaut
    
    if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = '../../../uploads/photos/';
        
        // Créer le répertoire s'il n'existe pas
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        
        $file_info = pathinfo($_FILES['photo']['name']);
        $extension = strtolower($file_info['extension']);
        
        // Vérifier l'extension
        $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];
        if (!in_array($extension, $allowed_extensions)) {
            $errors[] = 'Format de fichier non autorisé. Utilisez JPG, PNG ou GIF.';
        } else {
            // Vérifier la taille (max 5MB)
            if ($_FILES['photo']['size'] > 5 * 1024 * 1024) {
                $errors[] = 'La taille du fichier ne doit pas dépasser 5MB.';
            } else {
                // Générer un nom unique
                $new_filename = 'eleve_' . $eleve_id . '_' . time() . '.' . $extension;
                $upload_path = $upload_dir . $new_filename;
                
                if (move_uploaded_file($_FILES['photo']['tmp_name'], $upload_path)) {
                    // Supprimer l'ancienne photo si elle existe
                    if ($eleve['photo'] && file_exists('../../../' . $eleve['photo'])) {
                        unlink('../../../' . $eleve['photo']);
                    }
                    $photo_path = 'uploads/photos/' . $new_filename;
                } else {
                    $errors[] = 'Erreur lors du téléchargement de la photo.';
                }
            }
        }
    }
    
    // Validation
    if (empty($nom)) $errors[] = 'Le nom est obligatoire.';
    if (empty($prenom)) $errors[] = 'Le prénom est obligatoire.';
    if (empty($sexe)) $errors[] = 'Le sexe est obligatoire.';
    if (!$classe_id) $errors[] = 'La classe est obligatoire.';
    
    // Validation de la date de naissance
    if ($date_naissance) {
        $date_obj = DateTime::createFromFormat('Y-m-d', $date_naissance);
        if (!$date_obj || $date_obj->format('Y-m-d') !== $date_naissance) {
            $errors[] = 'Format de date de naissance invalide.';
        }
    }
    
    // Validation de l'email
    if ($email && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Format d\'email invalide.';
    }
    
    if (empty($errors)) {
        try {
            $database->beginTransaction();
            
            // Mettre à jour les informations de l'élève
            $database->execute(
                "UPDATE eleves SET
                 nom = ?, prenom = ?, sexe = ?, date_naissance = ?, lieu_naissance = ?,
                 adresse = ?, telephone = ?, email = ?,
                 nom_pere = ?, nom_mere = ?, profession_pere = ?, profession_mere = ?,
                 telephone_parent = ?, photo = ?, status = ?, updated_at = NOW()
                 WHERE id = ?",
                [
                    $nom, $prenom, $sexe, $date_naissance ?: null, $lieu_naissance,
                    $adresse, $telephone, $email,
                    $nom_pere, $nom_mere, $profession_pere, $profession_mere,
                    $telephone_parent, $photo_path, $status, $eleve_id
                ]
            );
            
            // Mettre à jour la classe si elle a changé
            if ($classe_id != $eleve['classe_id']) {
                $database->execute(
                    "UPDATE inscriptions SET classe_id = ?, updated_at = NOW() 
                     WHERE eleve_id = ? AND annee_scolaire_id = ?",
                    [$classe_id, $eleve_id, $current_year['id'] ?? 0]
                );
            }
            
            $database->commit();
            showMessage('success', 'Dossier modifié avec succès.');
            redirectTo("view.php?id=$eleve_id");
            
        } catch (Exception $e) {
            $database->rollback();
            $errors[] = 'Erreur lors de la modification : ' . $e->getMessage();
        }
    }
} else {
    // Pré-remplir le formulaire avec les données existantes
    $_POST = [
        'nom' => $eleve['nom'],
        'prenom' => $eleve['prenom'],
        'sexe' => $eleve['sexe'],
        'date_naissance' => $eleve['date_naissance'],
        'lieu_naissance' => $eleve['lieu_naissance'],

        'adresse' => $eleve['adresse'],
        'telephone' => $eleve['telephone'],
        'email' => $eleve['email'],
        'nom_pere' => $eleve['nom_pere'],
        'nom_mere' => $eleve['nom_mere'],
        'profession_pere' => $eleve['profession_pere'],
        'profession_mere' => $eleve['profession_mere'],
        'telephone_parent' => $eleve['telephone_parent'],
        'classe_id' => $eleve['classe_id'],
        'status' => $eleve['status']
    ];
}

$page_title = 'Modifier le dossier de ' . $eleve['nom'] . ' ' . $eleve['prenom'];

include '../../../includes/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">
        <i class="fas fa-edit me-2"></i>
        Modifier le Dossier
    </h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <div class="btn-group me-2">
            <a href="view.php?id=<?php echo $eleve_id; ?>" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-1"></i>
                Retour aux détails
            </a>
        </div>
        <div class="btn-group">
            <a href="index.php" class="btn btn-outline-secondary">
                <i class="fas fa-list me-1"></i>
                Liste des dossiers
            </a>
        </div>
    </div>
</div>

<!-- Informations de l'élève -->
<div class="alert alert-info mb-4">
    <div class="row">
        <div class="col-md-3">
            <strong>Élève :</strong> <?php echo htmlspecialchars($eleve['nom'] . ' ' . $eleve['prenom']); ?>
        </div>
        <div class="col-md-3">
            <strong>Classe actuelle :</strong> <?php echo htmlspecialchars($eleve['classe_nom']); ?>
        </div>
        <div class="col-md-3">
            <strong>Créé le :</strong> <?php echo formatDate($eleve['created_at']); ?>
        </div>
        <div class="col-md-3">
            <strong>Modifié le :</strong> <?php echo $eleve['updated_at'] ? formatDate($eleve['updated_at']) : 'Jamais'; ?>
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

<form method="POST" class="needs-validation" novalidate enctype="multipart/form-data">
    <!-- Informations personnelles -->
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="mb-0">
                <i class="fas fa-user me-2"></i>
                Informations Personnelles
            </h5>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-3 mb-3">
                    <label for="nom" class="form-label">Nom <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="nom" name="nom" 
                           value="<?php echo htmlspecialchars($_POST['nom'] ?? ''); ?>" required>
                </div>
                <div class="col-md-3 mb-3">
                    <label for="prenom" class="form-label">Prénom <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="prenom" name="prenom" 
                           value="<?php echo htmlspecialchars($_POST['prenom'] ?? ''); ?>" required>
                </div>
                <div class="col-md-3 mb-3">
                    <label for="sexe" class="form-label">Sexe <span class="text-danger">*</span></label>
                    <select class="form-select" id="sexe" name="sexe" required>
                        <option value="">Sélectionner...</option>
                        <option value="M" <?php echo ($_POST['sexe'] ?? '') === 'M' ? 'selected' : ''; ?>>Masculin</option>
                        <option value="F" <?php echo ($_POST['sexe'] ?? '') === 'F' ? 'selected' : ''; ?>>Féminin</option>
                    </select>
                </div>
                <div class="col-md-3 mb-3">
                    <label for="status" class="form-label">Statut de l'élève</label>
                    <select class="form-select" id="status" name="status">
                        <option value="actif" <?php echo ($_POST['status'] ?? '') === 'actif' ? 'selected' : ''; ?>>Actif</option>
                        <option value="transfere" <?php echo ($_POST['status'] ?? '') === 'transfere' ? 'selected' : ''; ?>>Transféré</option>
                        <option value="abandonne" <?php echo ($_POST['status'] ?? '') === 'abandonne' ? 'selected' : ''; ?>>Abandonné</option>
                        <option value="diplome" <?php echo ($_POST['status'] ?? '') === 'diplome' ? 'selected' : ''; ?>>Diplômé</option>
                    </select>
                </div>
            </div>
            <div class="row">
                <div class="col-md-4 mb-3">
                    <label for="date_naissance" class="form-label">Date de naissance</label>
                    <input type="date" class="form-control" id="date_naissance" name="date_naissance" 
                           value="<?php echo htmlspecialchars($_POST['date_naissance'] ?? ''); ?>">
                </div>
                <div class="col-md-4 mb-3">
                    <label for="lieu_naissance" class="form-label">Lieu de naissance</label>
                    <input type="text" class="form-control" id="lieu_naissance" name="lieu_naissance" 
                           value="<?php echo htmlspecialchars($_POST['lieu_naissance'] ?? ''); ?>">
                </div>
                <div class="col-md-4 mb-3">
                    <label for="photo" class="form-label">Photo de l'élève</label>
                    <div class="d-flex align-items-center">
                        <?php if ($eleve['photo']): ?>
                            <div class="me-3">
                                <img src="<?php echo '../../../' . htmlspecialchars($eleve['photo']); ?>" 
                                     alt="Photo actuelle" class="img-thumbnail" style="width: 60px; height: 60px; object-fit: cover;">
                            </div>
                        <?php endif; ?>
                        <input type="file" class="form-control" id="photo" name="photo" accept="image/*">
                    </div>
                    <small class="form-text text-muted">Formats acceptés : JPG, PNG, GIF. Taille max : 5MB</small>
                </div>
            </div>

        </div>
    </div>

    <!-- Informations scolaires -->
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="mb-0">
                <i class="fas fa-graduation-cap me-2"></i>
                Informations Scolaires
            </h5>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="classe_id" class="form-label">Classe <span class="text-danger">*</span></label>
                    <select class="form-select" id="classe_id" name="classe_id" required>
                        <option value="">Sélectionner une classe...</option>
                        <?php foreach ($classes as $classe): ?>
                            <option value="<?php echo $classe['id']; ?>" 
                                    <?php echo ($_POST['classe_id'] ?? '') == $classe['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($classe['nom'] . ' (' . $classe['niveau'] . ')'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </div>
    </div>

    <!-- Informations de contact -->
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="mb-0">
                <i class="fas fa-address-book me-2"></i>
                Informations de Contact
            </h5>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-12 mb-3">
                    <label for="adresse" class="form-label">Adresse</label>
                    <textarea class="form-control" id="adresse" name="adresse" rows="3"><?php echo htmlspecialchars($_POST['adresse'] ?? ''); ?></textarea>
                </div>
            </div>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="telephone" class="form-label">Téléphone de l'élève</label>
                    <input type="tel" class="form-control" id="telephone" name="telephone" 
                           value="<?php echo htmlspecialchars($_POST['telephone'] ?? ''); ?>">
                </div>
                <div class="col-md-6 mb-3">
                    <label for="email" class="form-label">Email</label>
                    <input type="email" class="form-control" id="email" name="email" 
                           value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
                </div>
            </div>
        </div>
    </div>

    <!-- Informations familiales -->
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="mb-0">
                <i class="fas fa-users me-2"></i>
                Informations Familiales
            </h5>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="nom_pere" class="form-label">Nom du père</label>
                    <input type="text" class="form-control" id="nom_pere" name="nom_pere" 
                           value="<?php echo htmlspecialchars($_POST['nom_pere'] ?? ''); ?>">
                </div>
                <div class="col-md-6 mb-3">
                    <label for="profession_pere" class="form-label">Profession du père</label>
                    <input type="text" class="form-control" id="profession_pere" name="profession_pere" 
                           value="<?php echo htmlspecialchars($_POST['profession_pere'] ?? ''); ?>">
                </div>
            </div>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="nom_mere" class="form-label">Nom de la mère</label>
                    <input type="text" class="form-control" id="nom_mere" name="nom_mere" 
                           value="<?php echo htmlspecialchars($_POST['nom_mere'] ?? ''); ?>">
                </div>
                <div class="col-md-6 mb-3">
                    <label for="profession_mere" class="form-label">Profession de la mère</label>
                    <input type="text" class="form-control" id="profession_mere" name="profession_mere" 
                           value="<?php echo htmlspecialchars($_POST['profession_mere'] ?? ''); ?>">
                </div>
            </div>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="telephone_parent" class="form-label">Téléphone des parents</label>
                    <input type="tel" class="form-control" id="telephone_parent" name="telephone_parent" 
                           value="<?php echo htmlspecialchars($_POST['telephone_parent'] ?? ''); ?>">
                </div>
            </div>
        </div>
    </div>

    <!-- Boutons d'action -->
    <div class="card">
        <div class="card-body">
            <div class="d-flex justify-content-between">
                <div>
                    <a href="view.php?id=<?php echo $eleve_id; ?>" class="btn btn-secondary">
                        <i class="fas fa-times me-1"></i>
                        Annuler
                    </a>
                </div>
                <div>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-1"></i>
                        Enregistrer les modifications
                    </button>
                </div>
            </div>
        </div>
    </div>
</form>

<script>
// Validation Bootstrap
(function() {
    'use strict';
    window.addEventListener('load', function() {
        var forms = document.getElementsByClassName('needs-validation');
        var validation = Array.prototype.filter.call(forms, function(form) {
            form.addEventListener('submit', function(event) {
                if (form.checkValidity() === false) {
                    event.preventDefault();
                    event.stopPropagation();
                }
                form.classList.add('was-validated');
            }, false);
        });
    }, false);
})();
</script>

<?php include '../../../includes/footer.php'; ?>
