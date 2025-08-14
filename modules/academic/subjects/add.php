<?php
/**
 * Module de gestion académique - Ajouter une matière
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

$page_title = 'Ajouter une matière';

$errors = [];
$success = false;

// Matières prédéfinies par niveau
$matieres_predefinies = [
    'maternelle' => [
        'Éveil' => ['coefficient' => 1, 'volume' => 5],
        'Langage' => ['coefficient' => 2, 'volume' => 6],
        'Mathématiques de base' => ['coefficient' => 2, 'volume' => 4],
        'Dessin' => ['coefficient' => 1, 'volume' => 2],
        'Chant' => ['coefficient' => 1, 'volume' => 2],
        'Éducation physique' => ['coefficient' => 1, 'volume' => 2]
    ],
    'primaire' => [
        'Français' => ['coefficient' => 4, 'volume' => 8],
        'Mathématiques' => ['coefficient' => 4, 'volume' => 6],
        'Sciences' => ['coefficient' => 2, 'volume' => 3],
        'Histoire' => ['coefficient' => 2, 'volume' => 2],
        'Géographie' => ['coefficient' => 2, 'volume' => 2],
        'Éducation civique et morale' => ['coefficient' => 2, 'volume' => 2],
        'Éducation physique' => ['coefficient' => 1, 'volume' => 2],
        'Dessin' => ['coefficient' => 1, 'volume' => 1],
        'Chant' => ['coefficient' => 1, 'volume' => 1],
        'Travaux manuels' => ['coefficient' => 1, 'volume' => 2]
    ],
    'secondaire' => [
        'Français' => ['coefficient' => 5, 'volume' => 6],
        'Mathématiques' => ['coefficient' => 5, 'volume' => 6],
        'Physique' => ['coefficient' => 3, 'volume' => 4],
        'Chimie' => ['coefficient' => 3, 'volume' => 4],
        'Biologie' => ['coefficient' => 3, 'volume' => 3],
        'Histoire' => ['coefficient' => 3, 'volume' => 3],
        'Géographie' => ['coefficient' => 3, 'volume' => 3],
        'Anglais' => ['coefficient' => 3, 'volume' => 4],
        'Éducation civique et morale' => ['coefficient' => 2, 'volume' => 2],
        'Éducation physique' => ['coefficient' => 2, 'volume' => 2],
        'Religion' => ['coefficient' => 2, 'volume' => 2],
        'Informatique' => ['coefficient' => 2, 'volume' => 2]
    ]
];

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
    
    // Vérifier l'unicité du nom de matière pour ce niveau
    if (!empty($nom) && !empty($niveau)) {
        $stmt = $database->query(
            "SELECT id FROM matieres WHERE nom = ? AND niveau = ?", 
            [$nom, $niveau]
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
    
    // Si pas d'erreurs, enregistrer la matière
    if (empty($errors)) {
        try {
            $sql = "INSERT INTO matieres (nom, niveau, type, description, coefficient, volume_horaire, objectifs) 
                    VALUES (?, ?, ?, ?, ?, ?, ?)";
            
            $database->execute($sql, [
                $nom, $niveau, $type, $description, 
                $coefficient, $volume_horaire, $objectifs
            ]);
            
            $matiere_id = $database->lastInsertId();
            
            showMessage('success', 'Matière créée avec succès !');
            redirectTo('view.php?id=' . $matiere_id);
            
        } catch (Exception $e) {
            $errors[] = 'Erreur lors de l\'enregistrement : ' . $e->getMessage();
        }
    }
}

include '../../../includes/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">
        <i class="fas fa-plus me-2"></i>
        Ajouter une matière
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
        <!-- Informations de base -->
        <div class="col-lg-8">
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-info-circle me-2"></i>
                        Informations de base
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="nom" class="form-label">Nom de la matière <span class="text-danger">*</span></label>
                            <input type="text" 
                                   class="form-control" 
                                   id="nom" 
                                   name="nom" 
                                   placeholder="Ex: Mathématiques, Français..."
                                   value="<?php echo htmlspecialchars($_POST['nom'] ?? ''); ?>"
                                   required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="niveau" class="form-label">Niveau <span class="text-danger">*</span></label>
                            <select class="form-select" id="niveau" name="niveau" required>
                                <option value="">Sélectionner un niveau...</option>
                                <option value="maternelle" <?php echo ($_POST['niveau'] ?? '') === 'maternelle' ? 'selected' : ''; ?>>Maternelle</option>
                                <option value="primaire" <?php echo ($_POST['niveau'] ?? '') === 'primaire' ? 'selected' : ''; ?>>Primaire</option>
                                <option value="secondaire" <?php echo ($_POST['niveau'] ?? '') === 'secondaire' ? 'selected' : ''; ?>>Secondaire</option>
                                <option value="general" <?php echo ($_POST['niveau'] ?? '') === 'general' ? 'selected' : ''; ?>>Général (tous niveaux)</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label for="type" class="form-label">Type <span class="text-danger">*</span></label>
                            <select class="form-select" id="type" name="type" required>
                                <option value="">Sélectionner un type...</option>
                                <option value="obligatoire" <?php echo ($_POST['type'] ?? '') === 'obligatoire' ? 'selected' : ''; ?>>Obligatoire</option>
                                <option value="optionnelle" <?php echo ($_POST['type'] ?? '') === 'optionnelle' ? 'selected' : ''; ?>>Optionnelle</option>
                            </select>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="coefficient" class="form-label">Coefficient</label>
                            <input type="number" 
                                   class="form-control" 
                                   id="coefficient" 
                                   name="coefficient" 
                                   min="1" 
                                   max="10"
                                   placeholder="Ex: 3"
                                   value="<?php echo htmlspecialchars($_POST['coefficient'] ?? ''); ?>">
                            <div class="form-text">Importance de la matière (1-10)</div>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="volume_horaire" class="form-label">Volume horaire</label>
                            <div class="input-group">
                                <input type="number" 
                                       class="form-control" 
                                       id="volume_horaire" 
                                       name="volume_horaire" 
                                       min="1" 
                                       max="20"
                                       placeholder="Ex: 4"
                                       value="<?php echo htmlspecialchars($_POST['volume_horaire'] ?? ''); ?>">
                                <span class="input-group-text">h/semaine</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="description" class="form-label">Description</label>
                        <textarea class="form-control" 
                                  id="description" 
                                  name="description" 
                                  rows="3"
                                  placeholder="Description de la matière, contenu général..."><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label for="objectifs" class="form-label">Objectifs pédagogiques</label>
                        <textarea class="form-control" 
                                  id="objectifs" 
                                  name="objectifs" 
                                  rows="4"
                                  placeholder="Objectifs d'apprentissage, compétences à acquérir..."><?php echo htmlspecialchars($_POST['objectifs'] ?? ''); ?></textarea>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Suggestions et aide -->
        <div class="col-lg-4">
            <!-- Matières prédéfinies -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-lightbulb me-2"></i>
                        Matières suggérées
                    </h5>
                </div>
                <div class="card-body">
                    <div id="suggestions-default">
                        <p class="text-muted">Sélectionnez un niveau pour voir les matières suggérées.</p>
                    </div>
                    
                    <?php foreach ($matieres_predefinies as $niveau_key => $matieres): ?>
                        <div id="suggestions-<?php echo $niveau_key; ?>" class="niveau-suggestions" style="display: none;">
                            <h6><?php echo ucfirst($niveau_key); ?></h6>
                            <div class="list-group list-group-flush">
                                <?php foreach ($matieres as $nom_matiere => $details): ?>
                                    <div class="list-group-item p-2 border-0 suggestion-item" 
                                         data-nom="<?php echo htmlspecialchars($nom_matiere); ?>"
                                         data-coefficient="<?php echo $details['coefficient']; ?>"
                                         data-volume="<?php echo $details['volume']; ?>"
                                         style="cursor: pointer;">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <span class="fw-bold"><?php echo htmlspecialchars($nom_matiere); ?></span>
                                            <div>
                                                <small class="badge bg-info">Coef. <?php echo $details['coefficient']; ?></small>
                                                <small class="badge bg-secondary"><?php echo $details['volume']; ?>h</small>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <small class="text-muted">Cliquez sur une matière pour la sélectionner</small>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <!-- Guide des coefficients -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-info-circle me-2"></i>
                        Guide des coefficients
                    </h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Coefficient</th>
                                    <th>Importance</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td><span class="badge bg-success">1-2</span></td>
                                    <td>Matière complémentaire</td>
                                </tr>
                                <tr>
                                    <td><span class="badge bg-warning">3-4</span></td>
                                    <td>Matière importante</td>
                                </tr>
                                <tr>
                                    <td><span class="badge bg-danger">5+</span></td>
                                    <td>Matière fondamentale</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            
            <!-- Types de matières -->
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-question-circle me-2"></i>
                        Types de matières
                    </h5>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <h6><i class="fas fa-star text-success me-2"></i>Obligatoire</h6>
                        <p class="small text-muted">
                            Matière que tous les élèves du niveau doivent suivre. 
                            Compte dans le calcul de la moyenne générale.
                        </p>
                    </div>
                    <div>
                        <h6><i class="fas fa-star-half-alt text-warning me-2"></i>Optionnelle</h6>
                        <p class="small text-muted">
                            Matière facultative que les élèves peuvent choisir. 
                            Peut compter comme bonus dans la moyenne.
                        </p>
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
                                Créer la matière
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</form>

<script>
// Affichage des suggestions selon le niveau sélectionné
document.getElementById('niveau').addEventListener('change', function() {
    // Masquer toutes les suggestions
    document.querySelectorAll('.niveau-suggestions').forEach(function(el) {
        el.style.display = 'none';
    });
    
    const niveau = this.value;
    const defaultSuggestions = document.getElementById('suggestions-default');
    
    if (niveau && niveau !== 'general') {
        const suggestions = document.getElementById('suggestions-' + niveau);
        if (suggestions) {
            suggestions.style.display = 'block';
            defaultSuggestions.style.display = 'none';
        }
    } else {
        defaultSuggestions.style.display = 'block';
    }
});

// Sélection d'une matière suggérée
document.querySelectorAll('.suggestion-item').forEach(function(item) {
    item.addEventListener('click', function() {
        const nom = this.dataset.nom;
        const coefficient = this.dataset.coefficient;
        const volume = this.dataset.volume;
        
        document.getElementById('nom').value = nom;
        document.getElementById('coefficient').value = coefficient;
        document.getElementById('volume_horaire').value = volume;
        document.getElementById('type').value = 'obligatoire';
        
        // Highlight temporaire
        this.classList.add('bg-primary', 'text-white');
        setTimeout(() => {
            this.classList.remove('bg-primary', 'text-white');
        }, 1000);
    });
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

<?php include '../../../includes/footer.php'; ?>
