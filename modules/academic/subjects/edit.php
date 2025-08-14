<?php
/**
 * Module de gestion académique - Modifier une matière
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

$page_title = 'Modifier une matière';

// Récupérer l'ID de la matière
$matiere_id = (int)($_GET['id'] ?? 0);
if (!$matiere_id) {
    showMessage('error', 'ID de matière manquant.');
    redirectTo('index.php');
}

// Récupérer les données de la matière
$matiere = $database->query(
    "SELECT * FROM matieres WHERE id = ?",
    [$matiere_id]
)->fetch();

if (!$matiere) {
    showMessage('error', 'Matière non trouvée.');
    redirectTo('index.php');
}

$errors = [];
$success = false;

// Récupérer les statistiques d'utilisation de la matière
$usage_stats = $database->query(
    "SELECT 
        COUNT(DISTINCT et.classe_id) as nb_classes,
        COUNT(DISTINCT et.enseignant_id) as nb_enseignants,
        COUNT(et.id) as nb_cours,
        COUNT(DISTINCT e.id) as nb_evaluations
     FROM emplois_temps et
     LEFT JOIN evaluations e ON et.matiere_id = e.matiere_id
     WHERE et.matiere_id = ?",
    [$matiere_id]
)->fetch();

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validation des données
    $nom = sanitizeInput($_POST['nom'] ?? '');
    $niveau = sanitizeInput($_POST['niveau'] ?? '');
    $type = sanitizeInput($_POST['type'] ?? '');
    $description = sanitizeInput($_POST['description'] ?? '');
    $coefficient = (int)($_POST['coefficient'] ?? 0) ?: null;
    $volume_horaire = (int)($_POST['volume_horaire'] ?? 0) ?: null;
    $objectifs = sanitizeInput($_POST['objectifs'] ?? '');
    
    // Validation des champs obligatoires
    if (empty($nom)) $errors[] = 'Le nom de la matière est obligatoire.';
    if (empty($niveau)) $errors[] = 'Le niveau est obligatoire.';
    if (empty($type)) $errors[] = 'Le type est obligatoire.';
    
    // Vérifier l'unicité du nom de matière pour ce niveau (sauf pour la matière actuelle)
    if (!empty($nom) && !empty($niveau)) {
        $stmt = $database->query(
            "SELECT id FROM matieres WHERE nom = ? AND niveau = ? AND id != ?", 
            [$nom, $niveau, $matiere_id]
        );
        if ($stmt->fetch()) {
            $errors[] = 'Une matière avec ce nom existe déjà pour ce niveau.';
        }
    }
    
    // Validation du coefficient
    if ($coefficient && ($coefficient < 1 || $coefficient > 10)) {
        $errors[] = 'Le coefficient doit être compris entre 1 et 10.';
    }
    
    // Validation du volume horaire
    if ($volume_horaire && ($volume_horaire < 1 || $volume_horaire > 20)) {
        $errors[] = 'Le volume horaire doit être compris entre 1 et 20 heures par semaine.';
    }
    
    // Si pas d'erreurs, mettre à jour la matière
    if (empty($errors)) {
        try {
            $database->execute(
                "UPDATE matieres SET 
                    nom = ?, 
                    niveau = ?, 
                    type = ?, 
                    description = ?, 
                    coefficient = ?, 
                    volume_horaire = ?, 
                    objectifs = ?
                 WHERE id = ?",
                [$nom, $niveau, $type, $description, $coefficient, $volume_horaire, $objectifs, $matiere_id]
            );
            
            // Enregistrer l'action
            logUserAction(
                'update_subject',
                'academic',
                "Matière modifiée: $nom (ID: $matiere_id)",
                $matiere_id
            );
            
            showMessage('success', 'Matière modifiée avec succès.');
            
            // Recharger les données mises à jour
            $matiere = $database->query(
                "SELECT * FROM matieres WHERE id = ?",
                [$matiere_id]
            )->fetch();
            
            $success = true;
            
        } catch (Exception $e) {
            $errors[] = 'Erreur lors de la modification: ' . $e->getMessage();
        }
    }
}

include '../../../includes/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">
        <i class="fas fa-edit me-2"></i>
        Modifier la matière
    </h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <div class="btn-group me-2">
            <a href="index.php" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-1"></i>
                Retour à la liste
            </a>
            <a href="view.php?id=<?php echo $matiere_id; ?>" class="btn btn-outline-info">
                <i class="fas fa-eye me-1"></i>
                Voir détails
            </a>
        </div>
    </div>
</div>

<?php if (!empty($errors)): ?>
    <div class="alert alert-danger">
        <h5><i class="fas fa-exclamation-triangle me-2"></i>Erreurs de validation</h5>
        <ul class="mb-0">
            <?php foreach ($errors as $error): ?>
                <li><?php echo htmlspecialchars($error); ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<?php if ($success): ?>
    <div class="alert alert-success">
        <i class="fas fa-check-circle me-2"></i>
        La matière a été modifiée avec succès.
    </div>
<?php endif; ?>

<div class="row">
    <div class="col-lg-8">
        <!-- Formulaire de modification -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-edit me-2"></i>
                    Informations de la matière
                </h5>
            </div>
            <div class="card-body">
                <form method="POST" class="needs-validation" novalidate>
                    <div class="row">
                        <div class="col-md-8">
                            <div class="mb-3">
                                <label for="nom" class="form-label">
                                    Nom de la matière <span class="text-danger">*</span>
                                </label>
                                <input type="text" 
                                       class="form-control" 
                                       id="nom" 
                                       name="nom" 
                                       value="<?php echo htmlspecialchars($matiere['nom']); ?>"
                                       required>
                                <div class="invalid-feedback">
                                    Veuillez saisir le nom de la matière.
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="code" class="form-label">Code</label>
                                <input type="text" 
                                       class="form-control" 
                                       id="code" 
                                       name="code" 
                                       value="<?php echo htmlspecialchars($matiere['code'] ?? ''); ?>"
                                       placeholder="Ex: MATH, FR, SCI">
                                <small class="form-text text-muted">Code abrégé (optionnel)</small>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="niveau" class="form-label">
                                    Niveau <span class="text-danger">*</span>
                                </label>
                                <select class="form-select" id="niveau" name="niveau" required>
                                    <option value="">Sélectionner un niveau</option>
                                    <option value="maternelle" <?php echo $matiere['niveau'] === 'maternelle' ? 'selected' : ''; ?>>Maternelle</option>
                                    <option value="primaire" <?php echo $matiere['niveau'] === 'primaire' ? 'selected' : ''; ?>>Primaire</option>
                                    <option value="secondaire" <?php echo $matiere['niveau'] === 'secondaire' ? 'selected' : ''; ?>>Secondaire</option>
                                </select>
                                <div class="invalid-feedback">
                                    Veuillez sélectionner un niveau.
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="type" class="form-label">
                                    Type <span class="text-danger">*</span>
                                </label>
                                <select class="form-select" id="type" name="type" required>
                                    <option value="">Sélectionner un type</option>
                                    <option value="obligatoire" <?php echo $matiere['type'] === 'obligatoire' ? 'selected' : ''; ?>>Obligatoire</option>
                                    <option value="optionnelle" <?php echo $matiere['type'] === 'optionnelle' ? 'selected' : ''; ?>>Optionnelle</option>
                                </select>
                                <div class="invalid-feedback">
                                    Veuillez sélectionner un type.
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="coefficient" class="form-label">Coefficient</label>
                                <input type="number" 
                                       class="form-control" 
                                       id="coefficient" 
                                       name="coefficient" 
                                       value="<?php echo $matiere['coefficient']; ?>"
                                       min="1" 
                                       max="10">
                                <small class="form-text text-muted">Entre 1 et 10 (optionnel)</small>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="volume_horaire" class="form-label">Volume horaire</label>
                                <div class="input-group">
                                    <input type="number" 
                                           class="form-control" 
                                           id="volume_horaire" 
                                           name="volume_horaire" 
                                           value="<?php echo $matiere['volume_horaire']; ?>"
                                           min="1" 
                                           max="20">
                                    <span class="input-group-text">h/semaine</span>
                                </div>
                                <small class="form-text text-muted">Entre 1 et 20 heures par semaine</small>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="description" class="form-label">Description</label>
                        <textarea class="form-control" 
                                  id="description" 
                                  name="description" 
                                  rows="3"
                                  placeholder="Description de la matière..."><?php echo htmlspecialchars($matiere['description'] ?? ''); ?></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label for="objectifs" class="form-label">Objectifs pédagogiques</label>
                        <textarea class="form-control" 
                                  id="objectifs" 
                                  name="objectifs" 
                                  rows="4"
                                  placeholder="Objectifs et compétences visés par cette matière..."><?php echo htmlspecialchars($matiere['objectifs'] ?? ''); ?></textarea>
                    </div>
                    
                    <div class="d-flex justify-content-between">
                        <a href="index.php" class="btn btn-secondary">
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
    
    <div class="col-lg-4">
        <!-- Informations sur l'utilisation -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-chart-bar me-2"></i>
                    Utilisation de la matière
                </h5>
            </div>
            <div class="card-body">
                <div class="row text-center">
                    <div class="col-6">
                        <h3 class="text-primary"><?php echo $usage_stats['nb_classes'] ?? 0; ?></h3>
                        <p class="mb-0 small">Classes</p>
                    </div>
                    <div class="col-6">
                        <h3 class="text-success"><?php echo $usage_stats['nb_enseignants'] ?? 0; ?></h3>
                        <p class="mb-0 small">Enseignants</p>
                    </div>
                </div>
                
                <hr>
                
                <div class="row text-center">
                    <div class="col-6">
                        <h3 class="text-info"><?php echo $usage_stats['nb_cours'] ?? 0; ?></h3>
                        <p class="mb-0 small">Cours programmés</p>
                    </div>
                    <div class="col-6">
                        <h3 class="text-warning"><?php echo $usage_stats['nb_evaluations'] ?? 0; ?></h3>
                        <p class="mb-0 small">Évaluations</p>
                    </div>
                </div>
                
                <?php if ($usage_stats['nb_classes'] > 0): ?>
                    <div class="alert alert-info mt-3">
                        <small>
                            <i class="fas fa-info-circle me-1"></i>
                            Cette matière est utilisée dans <?php echo $usage_stats['nb_classes']; ?> classe(s). 
                            Les modifications affecteront tous les emplois du temps associés.
                        </small>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Actions rapides -->
        <div class="card mt-3">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-tools me-2"></i>
                    Actions rapides
                </h5>
            </div>
            <div class="card-body">
                <div class="d-grid gap-2">
                    <a href="view.php?id=<?php echo $matiere_id; ?>" class="btn btn-outline-info btn-sm">
                        <i class="fas fa-eye me-1"></i>
                        Voir les détails complets
                    </a>
                    
                    <?php if ($usage_stats['nb_classes'] > 0): ?>
                        <a href="../schedule/index.php?matiere_id=<?php echo $matiere_id; ?>" class="btn btn-outline-primary btn-sm">
                            <i class="fas fa-calendar me-1"></i>
                            Voir dans l'emploi du temps
                        </a>
                    <?php endif; ?>
                    
                    <?php if ($usage_stats['nb_evaluations'] > 0): ?>
                        <a href="../evaluations/index.php?matiere_id=<?php echo $matiere_id; ?>" class="btn btn-outline-success btn-sm">
                            <i class="fas fa-clipboard-check me-1"></i>
                            Voir les évaluations
                        </a>
                    <?php endif; ?>
                    
                    <hr>
                    
                    <a href="delete.php?id=<?php echo $matiere_id; ?>" 
                       class="btn btn-outline-danger btn-sm btn-delete"
                       data-name="<?php echo htmlspecialchars($matiere['nom']); ?>">
                        <i class="fas fa-trash me-1"></i>
                        Supprimer cette matière
                    </a>
                </div>
            </div>
        </div>
        
        <!-- Informations système -->
        <div class="card mt-3">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-info-circle me-2"></i>
                    Informations
                </h5>
            </div>
            <div class="card-body">
                <small class="text-muted">
                    <strong>Créée le :</strong> 
                    <?php echo date('d/m/Y à H:i', strtotime($matiere['created_at'])); ?>
                </small>
            </div>
        </div>
    </div>
</div>

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

// Confirmation de suppression
document.addEventListener('DOMContentLoaded', function() {
    const deleteButtons = document.querySelectorAll('.btn-delete');
    deleteButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            const name = this.getAttribute('data-name');
            if (confirm(`Êtes-vous sûr de vouloir supprimer la matière "${name}" ?\n\nCette action est irréversible et supprimera également tous les cours et évaluations associés.`)) {
                window.location.href = this.href;
            }
        });
    });
});
</script>

<?php include '../../../includes/footer.php'; ?>
