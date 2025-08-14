<?php
/**
 * Module de gestion académique - Modifier une classe
 * Application de gestion scolaire - République Démocratique du Congo
 */

require_once '../../../config/config.php';
require_once '../../../config/database.php';
require_once '../../../includes/functions.php';

// Vérifier l'authentification et les permissions
requireLogin();
if (!checkPermission('academic')) {
    showMessage('error', 'Accès refusé à cette fonctionnalité.');
    redirectTo('index.php');
}

// Récupérer l'ID de la classe
$classe_id = (int)($_GET['id'] ?? 0);
if (!$classe_id) {
    showMessage('error', 'ID de classe manquant.');
    redirectTo('index.php');
}

// Récupérer l'année scolaire active
$current_year = getCurrentAcademicYear();

if (!$current_year) {
    showMessage('error', 'Aucune année scolaire active.');
    redirectTo('../years/add.php');
}

// Récupérer les informations de la classe
$classe = $database->query(
    "SELECT c.*, 
            p.nom as titulaire_nom, p.prenom as titulaire_prenom
     FROM classes c 
     LEFT JOIN personnel p ON c.titulaire_id = p.id
     WHERE c.id = ?",
    [$classe_id]
)->fetch();

if (!$classe) {
    showMessage('error', 'Classe non trouvée.');
    redirectTo('index.php');
}

// Vérifier que la classe appartient à l'année scolaire active
if ($classe['annee_scolaire_id'] != $current_year['id']) {
    showMessage('error', 'Cette classe n\'appartient pas à l\'année scolaire active.');
    redirectTo('index.php');
}

$page_title = 'Modifier la classe : ' . $classe['nom'];

// Obtenir la liste des enseignants pour les titulaires
$enseignants = $database->query(
    "SELECT id, nom, prenom, specialite FROM personnel WHERE fonction = 'enseignant' AND status = 'actif' ORDER BY nom, prenom"
)->fetchAll();

$errors = [];
$success = false;

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validation des données
    $nom = sanitizeInput($_POST['nom'] ?? '');
    $niveau = sanitizeInput($_POST['niveau'] ?? '');
    $section = sanitizeInput($_POST['section'] ?? '');
    $description = sanitizeInput($_POST['description'] ?? '');
    $salle = sanitizeInput($_POST['salle'] ?? '');
    $capacite_max = (int)($_POST['capacite_max'] ?? 0);
    $titulaire_id = (int)($_POST['titulaire_id'] ?? 0) ?: null;
    
    // Validation des champs obligatoires
    if (empty($nom)) $errors[] = 'Le nom de la classe est obligatoire.';
    if (empty($niveau)) $errors[] = 'Le niveau est obligatoire.';
    
    // Vérifier l'unicité du nom de classe pour cette année (sauf la classe actuelle)
    if (!empty($nom)) {
        $stmt = $database->query(
            "SELECT id FROM classes WHERE nom = ? AND annee_scolaire_id = ? AND id != ?", 
            [$nom, $current_year['id'], $classe_id]
        );
        if ($stmt->fetch()) {
            $errors[] = 'Une classe avec ce nom existe déjà pour cette année scolaire.';
        }
    }
    
    // Validation de la capacité
    if ($capacite_max < 0) {
        $errors[] = 'La capacité maximale ne peut pas être négative.';
    }
    
    // Vérifier que le titulaire existe
    if ($titulaire_id) {
        $stmt = $database->query("SELECT id FROM personnel WHERE id = ? AND fonction = 'enseignant' AND status = 'actif'", [$titulaire_id]);
        if (!$stmt->fetch()) {
            $errors[] = 'L\'enseignant sélectionné n\'existe pas ou n\'est pas actif.';
        }
    }
    
    // Si pas d'erreurs, mettre à jour la classe
    if (empty($errors)) {
        try {
            $sql = "UPDATE classes SET 
                    nom = ?, niveau = ?, section = ?, description = ?, 
                    salle = ?, capacite_max = ?, titulaire_id = ?, 
                    updated_at = NOW()
                    WHERE id = ?";
            
            $database->execute($sql, [
                $nom, $niveau, $section, $description, 
                $salle, $capacite_max ?: null, $titulaire_id, $classe_id
            ]);
            
            // Enregistrer l'action
            logAction('academic', 'Modification de la classe ' . $nom, $classe_id);
            
            showMessage('success', 'Classe modifiée avec succès !');
            redirectTo('view.php?id=' . $classe_id);
            
        } catch (Exception $e) {
            $errors[] = 'Erreur lors de la modification : ' . $e->getMessage();
        }
    }
}

include '../../../includes/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">
        <i class="fas fa-edit me-2"></i>
        Modifier la classe : <?php echo htmlspecialchars($classe['nom']); ?>
    </h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <div class="btn-group me-2">
            <a href="index.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left me-1"></i>
                Retour à la liste
            </a>
        </div>
        <div class="btn-group">
            <a href="view.php?id=<?php echo $classe_id; ?>" class="btn btn-outline-primary">
                <i class="fas fa-eye me-1"></i>
                Voir la classe
            </a>
        </div>
    </div>
</div>

<?php if (!empty($errors)): ?>
    <div class="alert alert-danger">
        <h5><i class="fas fa-exclamation-triangle me-2"></i>Erreurs détectées :</h5>
        <ul class="mb-0">
            <?php foreach ($errors as $error): ?>
                <li><?php echo htmlspecialchars($error); ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<div class="row">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-edit me-2"></i>
                    Informations de la classe
                </h5>
            </div>
            <div class="card-body">
                <form method="POST" action="">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="nom" class="form-label">
                                    Nom de la classe <span class="text-danger">*</span>
                                </label>
                                <input type="text" class="form-control" id="nom" name="nom" 
                                       value="<?php echo htmlspecialchars($classe['nom']); ?>" required>
                                <div class="form-text">Ex: 6ème A, 5ème B, etc.</div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="niveau" class="form-label">
                                    Niveau <span class="text-danger">*</span>
                                </label>
                                <select class="form-select" id="niveau" name="niveau" required>
                                    <option value="">Sélectionner un niveau</option>
                                    <option value="maternelle" <?php echo $classe['niveau'] === 'maternelle' ? 'selected' : ''; ?>>Maternelle</option>
                                    <option value="primaire" <?php echo $classe['niveau'] === 'primaire' ? 'selected' : ''; ?>>Primaire</option>
                                    <option value="secondaire" <?php echo $classe['niveau'] === 'secondaire' ? 'selected' : ''; ?>>Secondaire</option>
                                    <option value="superieur" <?php echo $classe['niveau'] === 'superieur' ? 'selected' : ''; ?>>Supérieur</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="section" class="form-label">Section</label>
                                <input type="text" class="form-control" id="section" name="section" 
                                       value="<?php echo htmlspecialchars($classe['section'] ?? ''); ?>">
                                <div class="form-text">Ex: Scientifique, Littéraire, etc.</div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="salle" class="form-label">Salle de classe</label>
                                <input type="text" class="form-control" id="salle" name="salle" 
                                       value="<?php echo htmlspecialchars($classe['salle'] ?? ''); ?>">
                                <div class="form-text">Ex: Salle 101, Bâtiment A, etc.</div>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="description" class="form-label">Description</label>
                        <textarea class="form-control" id="description" name="description" rows="3"><?php echo htmlspecialchars($classe['description'] ?? ''); ?></textarea>
                        <div class="form-text">Description optionnelle de la classe</div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="capacite_max" class="form-label">Capacité maximale</label>
                                <input type="number" class="form-control" id="capacite_max" name="capacite_max" 
                                       value="<?php echo $classe['capacite_max'] ?? ''; ?>" min="0">
                                <div class="form-text">Nombre maximum d'élèves autorisés</div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="titulaire_id" class="form-label">Titulaire de classe</label>
                                <select class="form-select" id="titulaire_id" name="titulaire_id">
                                    <option value="">Aucun titulaire</option>
                                    <?php foreach ($enseignants as $enseignant): ?>
                                        <option value="<?php echo $enseignant['id']; ?>" 
                                                <?php echo $classe['titulaire_id'] == $enseignant['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($enseignant['nom'] . ' ' . $enseignant['prenom']); ?>
                                            <?php if ($enseignant['specialite']): ?>
                                                (<?php echo htmlspecialchars($enseignant['specialite']); ?>)
                                            <?php endif; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="form-text">Enseignant responsable de la classe</div>
                            </div>
                        </div>
                    </div>

                    <div class="d-flex justify-content-between">
                        <a href="view.php?id=<?php echo $classe_id; ?>" class="btn btn-secondary">
                            <i class="fas fa-times me-1"></i>
                            Annuler
                        </a>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-1"></i>
                            Enregistrer les modifications
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-md-4">
        <!-- Informations actuelles -->
        <div class="card mb-3">
            <div class="card-header">
                <h6 class="mb-0">
                    <i class="fas fa-info-circle me-2"></i>
                    Informations actuelles
                </h6>
            </div>
            <div class="card-body">
                <table class="table table-sm table-borderless">
                    <tr>
                        <td class="fw-bold">Nom :</td>
                        <td><?php echo htmlspecialchars($classe['nom']); ?></td>
                    </tr>
                    <tr>
                        <td class="fw-bold">Niveau :</td>
                        <td><span class="badge bg-primary"><?php echo ucfirst($classe['niveau']); ?></span></td>
                    </tr>
                    <?php if ($classe['section']): ?>
                        <tr>
                            <td class="fw-bold">Section :</td>
                            <td><?php echo htmlspecialchars($classe['section']); ?></td>
                        </tr>
                    <?php endif; ?>
                    <?php if ($classe['salle']): ?>
                        <tr>
                            <td class="fw-bold">Salle :</td>
                            <td><?php echo htmlspecialchars($classe['salle']); ?></td>
                        </tr>
                    <?php endif; ?>
                    <tr>
                        <td class="fw-bold">Capacité :</td>
                        <td><?php echo $classe['capacite_max'] ? $classe['capacite_max'] . ' élèves' : 'Non définie'; ?></td>
                    </tr>
                    <tr>
                        <td class="fw-bold">Titulaire :</td>
                        <td>
                            <?php if ($classe['titulaire_nom']): ?>
                                <?php echo htmlspecialchars($classe['titulaire_nom'] . ' ' . $classe['titulaire_prenom']); ?>
                            <?php else: ?>
                                <span class="text-muted">Aucun titulaire</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <td class="fw-bold">Année scolaire :</td>
                        <td><?php echo htmlspecialchars($current_year['annee']); ?></td>
                    </tr>
                    <tr>
                        <td class="fw-bold">Créée le :</td>
                        <td><?php echo formatDate($classe['created_at']); ?></td>
                    </tr>
                    <?php if ($classe['updated_at']): ?>
                        <tr>
                            <td class="fw-bold">Modifiée le :</td>
                            <td><?php echo formatDate($classe['updated_at']); ?></td>
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
                    <a href="view.php?id=<?php echo $classe_id; ?>" class="btn btn-outline-primary btn-sm">
                        <i class="fas fa-eye me-1"></i>
                        Voir les détails
                    </a>
                    <a href="../schedule/index.php?classe_id=<?php echo $classe_id; ?>" class="btn btn-outline-info btn-sm">
                        <i class="fas fa-calendar-alt me-1"></i>
                        Emploi du temps
                    </a>
                    <a href="../../students/index.php?classe_id=<?php echo $classe_id; ?>" class="btn btn-outline-success btn-sm">
                        <i class="fas fa-users me-1"></i>
                        Élèves de la classe
                    </a>
                    <a href="../../evaluations/class.php?id=<?php echo $classe_id; ?>" class="btn btn-outline-warning btn-sm">
                        <i class="fas fa-chart-line me-1"></i>
                        Évaluations
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Validation côté client
document.addEventListener('DOMContentLoaded', function() {
    const form = document.querySelector('form');
    const nomInput = document.getElementById('nom');
    const niveauSelect = document.getElementById('niveau');
    
    form.addEventListener('submit', function(e) {
        let hasErrors = false;
        
        // Réinitialiser les styles d'erreur
        nomInput.classList.remove('is-invalid');
        niveauSelect.classList.remove('is-invalid');
        
        // Validation du nom
        if (!nomInput.value.trim()) {
            nomInput.classList.add('is-invalid');
            hasErrors = true;
        }
        
        // Validation du niveau
        if (!niveauSelect.value) {
            niveauSelect.classList.add('is-invalid');
            hasErrors = true;
        }
        
        if (hasErrors) {
            e.preventDefault();
            alert('Veuillez corriger les erreurs avant de soumettre le formulaire.');
        }
    });
});
</script>

<?php include '../../../includes/footer.php'; ?>
