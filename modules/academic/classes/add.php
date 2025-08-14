<?php
/**
 * Module de gestion académique - Ajouter une classe
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

$page_title = 'Ajouter une classe';

// Obtenir l'année scolaire actuelle
$current_year = getCurrentAcademicYear();

if (!$current_year) {
    showMessage('error', 'Aucune année scolaire active. Veuillez d\'abord créer une année scolaire.');
    redirectTo('../years/add.php');
}

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
    $description = sanitizeInput($_POST['description'] ?? '');
    $salle = sanitizeInput($_POST['salle'] ?? '');
    $capacite_max = (int)($_POST['capacite_max'] ?? 0);
    $titulaire_id = (int)($_POST['titulaire_id'] ?? 0) ?: null;
    
    // Validation des champs obligatoires
    if (empty($nom)) $errors[] = 'Le nom de la classe est obligatoire.';
    if (empty($niveau)) $errors[] = 'Le niveau est obligatoire.';
    
    // Vérifier l'unicité du nom de classe pour cette année
    if (!empty($nom)) {
        $stmt = $database->query(
            "SELECT id FROM classes WHERE nom = ? AND annee_scolaire_id = ?", 
            [$nom, $current_year['id']]
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
    
    // Si pas d'erreurs, enregistrer la classe
    if (empty($errors)) {
        try {
            $sql = "INSERT INTO classes (nom, niveau, description, salle, capacite_max, titulaire_id, annee_scolaire_id) 
                    VALUES (?, ?, ?, ?, ?, ?, ?)";
            
            $database->execute($sql, [
                $nom, $niveau, $description, $salle, 
                $capacite_max ?: null, $titulaire_id, $current_year['id']
            ]);
            
            $classe_id = $database->lastInsertId();
            
            showMessage('success', 'Classe créée avec succès !');
            redirectTo('view.php?id=' . $classe_id);
            
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
        Ajouter une classe
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
                            <label for="nom" class="form-label">Nom de la classe <span class="text-danger">*</span></label>
                            <input type="text" 
                                   class="form-control" 
                                   id="nom" 
                                   name="nom" 
                                   placeholder="Ex: 1ère Primaire A"
                                   value="<?php echo htmlspecialchars($_POST['nom'] ?? ''); ?>"
                                   required>
                            <div class="form-text">Le nom doit être unique pour cette année scolaire</div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="niveau" class="form-label">Niveau <span class="text-danger">*</span></label>
                            <select class="form-select" id="niveau" name="niveau" required>
                                <option value="">Sélectionner un niveau...</option>
                                <option value="maternelle" <?php echo ($_POST['niveau'] ?? '') === 'maternelle' ? 'selected' : ''; ?>>Maternelle</option>
                                <option value="primaire" <?php echo ($_POST['niveau'] ?? '') === 'primaire' ? 'selected' : ''; ?>>Primaire</option>
                                <option value="secondaire" <?php echo ($_POST['niveau'] ?? '') === 'secondaire' ? 'selected' : ''; ?>>Secondaire</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="description" class="form-label">Description</label>
                        <textarea class="form-control" 
                                  id="description" 
                                  name="description" 
                                  rows="3"
                                  placeholder="Description optionnelle de la classe..."><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="salle" class="form-label">Salle de classe</label>
                            <input type="text" 
                                   class="form-control" 
                                   id="salle" 
                                   name="salle" 
                                   placeholder="Ex: Salle A1, Bâtiment Principal"
                                   value="<?php echo htmlspecialchars($_POST['salle'] ?? ''); ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="capacite_max" class="form-label">Capacité maximale</label>
                            <input type="number" 
                                   class="form-control" 
                                   id="capacite_max" 
                                   name="capacite_max" 
                                   min="1" 
                                   max="100"
                                   placeholder="Ex: 30"
                                   value="<?php echo htmlspecialchars($_POST['capacite_max'] ?? ''); ?>">
                            <div class="form-text">Nombre maximum d'élèves dans cette classe</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Configuration avancée -->
        <div class="col-lg-4">
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-cog me-2"></i>
                        Configuration
                    </h5>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label for="titulaire_id" class="form-label">Enseignant titulaire</label>
                        <select class="form-select" id="titulaire_id" name="titulaire_id">
                            <option value="">Aucun titulaire pour le moment</option>
                            <?php foreach ($enseignants as $enseignant): ?>
                                <option value="<?php echo $enseignant['id']; ?>" 
                                        <?php echo ($_POST['titulaire_id'] ?? '') == $enseignant['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($enseignant['nom'] . ' ' . $enseignant['prenom']); ?>
                                    <?php if ($enseignant['specialite']): ?>
                                        - <?php echo htmlspecialchars($enseignant['specialite']); ?>
                                    <?php endif; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text">L'enseignant responsable principal de cette classe</div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Année scolaire</label>
                        <div class="form-control-plaintext">
                            <i class="fas fa-calendar-alt me-2"></i>
                            <?php echo htmlspecialchars($current_year['annee']); ?>
                        </div>
                        <div class="form-text">La classe sera créée pour cette année scolaire</div>
                    </div>
                </div>
            </div>
            
            <!-- Suggestions selon le niveau -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-lightbulb me-2"></i>
                        Suggestions
                    </h5>
                </div>
                <div class="card-body">
                    <div id="suggestions-maternelle" class="niveau-suggestions" style="display: none;">
                        <h6>Maternelle</h6>
                        <ul class="list-unstyled">
                            <li><strong>Exemples de noms :</strong></li>
                            <li>• Petite Section A</li>
                            <li>• Moyenne Section B</li>
                            <li>• Grande Section C</li>
                        </ul>
                        <p><small class="text-muted">Capacité recommandée : 15-20 élèves</small></p>
                    </div>
                    
                    <div id="suggestions-primaire" class="niveau-suggestions" style="display: none;">
                        <h6>Primaire</h6>
                        <ul class="list-unstyled">
                            <li><strong>Exemples de noms :</strong></li>
                            <li>• 1ère Primaire A</li>
                            <li>• 2ème Primaire B</li>
                            <li>• 6ème Primaire C</li>
                        </ul>
                        <p><small class="text-muted">Capacité recommandée : 25-35 élèves</small></p>
                    </div>
                    
                    <div id="suggestions-secondaire" class="niveau-suggestions" style="display: none;">
                        <h6>Secondaire</h6>
                        <ul class="list-unstyled">
                            <li><strong>Exemples de noms :</strong></li>
                            <li>• 1ère Secondaire A</li>
                            <li>• 4ème Secondaire Sciences</li>
                            <li>• 6ème Secondaire Littéraire</li>
                        </ul>
                        <p><small class="text-muted">Capacité recommandée : 30-40 élèves</small></p>
                    </div>
                    
                    <div id="suggestions-default">
                        <p class="text-muted">Sélectionnez un niveau pour voir les suggestions.</p>
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
                                Créer la classe
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
    
    if (niveau) {
        const suggestions = document.getElementById('suggestions-' + niveau);
        if (suggestions) {
            suggestions.style.display = 'block';
            defaultSuggestions.style.display = 'none';
        }
        
        // Suggestion de capacité selon le niveau
        const capaciteField = document.getElementById('capacite_max');
        if (!capaciteField.value) {
            switch(niveau) {
                case 'maternelle':
                    capaciteField.value = '20';
                    break;
                case 'primaire':
                    capaciteField.value = '30';
                    break;
                case 'secondaire':
                    capaciteField.value = '35';
                    break;
            }
        }
    } else {
        defaultSuggestions.style.display = 'block';
    }
});

// Génération automatique de nom de classe
document.getElementById('niveau').addEventListener('change', function() {
    const nomField = document.getElementById('nom');
    if (!nomField.value) {
        const niveau = this.value;
        switch(niveau) {
            case 'maternelle':
                nomField.placeholder = 'Ex: Petite Section A, Moyenne Section B';
                break;
            case 'primaire':
                nomField.placeholder = 'Ex: 1ère Primaire A, 2ème Primaire B';
                break;
            case 'secondaire':
                nomField.placeholder = 'Ex: 1ère Secondaire A, 4ème Sciences';
                break;
        }
    }
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
