<?php
/**
 * Module d'évaluations et notes - Ajouter une évaluation
 * Application de gestion scolaire - République Démocratique du Congo
 */

require_once '../../../config/config.php';
require_once '../../../config/database.php';
require_once '../../../includes/functions.php';

// Vérifier l'authentification et les permissions
requireLogin();
if (!checkPermission('evaluations')) {
    showMessage('error', 'Accès refusé à cette fonctionnalité.');
    redirectTo('index.php');
}

$page_title = 'Créer une évaluation';

// Obtenir l'année scolaire actuelle
$current_year = getCurrentAcademicYear();

if (!$current_year) {
    showMessage('error', 'Aucune année scolaire active.');
    redirectTo('../index.php');
}

// Récupérer les classes
$classes = $database->query(
    "SELECT id, nom, niveau FROM classes WHERE annee_scolaire_id = ? ORDER BY niveau, nom",
    [$current_year['id']]
)->fetchAll();

// Récupérer les matières
$matieres = $database->query(
    "SELECT id, nom, coefficient FROM matieres ORDER BY nom"
)->fetchAll();

// Récupérer les enseignants
$enseignants = $database->query(
    "SELECT id, nom, prenom FROM personnel WHERE fonction = 'enseignant' AND status = 'actif' ORDER BY nom, prenom"
)->fetchAll();

$errors = [];
$success = false;

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validation des données
    $nom = sanitizeInput($_POST['nom'] ?? '');
    $description = sanitizeInput($_POST['description'] ?? '');
    $classe_id = (int)($_POST['classe_id'] ?? 0);
    $matiere_id = (int)($_POST['matiere_id'] ?? 0);
    $enseignant_id = (int)($_POST['enseignant_id'] ?? 0) ?: null;
    $type_evaluation = sanitizeInput($_POST['type_evaluation'] ?? '');
    $periode = sanitizeInput($_POST['periode'] ?? '');
    $date_evaluation = sanitizeInput($_POST['date_evaluation'] ?? '');
    $heure_debut = sanitizeInput($_POST['heure_debut'] ?? '');
    $heure_fin = sanitizeInput($_POST['heure_fin'] ?? '');
    $duree_minutes = (int)($_POST['duree_minutes'] ?? 0) ?: null;
    $note_max = (float)($_POST['note_max'] ?? 20);
    $bareme = sanitizeInput($_POST['bareme'] ?? '');
    $consignes = sanitizeInput($_POST['consignes'] ?? '');
    $coefficient_evaluation = (float)($_POST['coefficient_evaluation'] ?? 1);
    
    // Validation des champs obligatoires
    if (empty($nom)) $errors[] = 'Le nom de l\'évaluation est obligatoire.';
    if (!$classe_id) $errors[] = 'La classe est obligatoire.';
    if (!$matiere_id) $errors[] = 'La matière est obligatoire.';
    if (empty($type_evaluation)) $errors[] = 'Le type d\'évaluation est obligatoire.';
    if (empty($periode)) $errors[] = 'La période est obligatoire.';
    if (empty($date_evaluation)) $errors[] = 'La date d\'évaluation est obligatoire.';
    
    // Vérifier que la classe existe
    if ($classe_id) {
        $stmt = $database->query("SELECT id FROM classes WHERE id = ? AND annee_scolaire_id = ?", [$classe_id, $current_year['id']]);
        if (!$stmt->fetch()) {
            $errors[] = 'La classe sélectionnée n\'existe pas.';
        }
    }
    
    // Vérifier que la matière existe
    if ($matiere_id) {
        $stmt = $database->query("SELECT id FROM matieres WHERE id = ?", [$matiere_id]);
        if (!$stmt->fetch()) {
            $errors[] = 'La matière sélectionnée n\'existe pas.';
        }
    }
    
    // Validation de la date
    if (!empty($date_evaluation) && !isValidDate($date_evaluation)) {
        $errors[] = 'La date d\'évaluation n\'est pas valide.';
    }
    
    // Validation des heures
    if (!empty($heure_debut) && !empty($heure_fin)) {
        if (strtotime($heure_fin) <= strtotime($heure_debut)) {
            $errors[] = 'L\'heure de fin doit être postérieure à l\'heure de début.';
        }
    }
    
    // Validation de la note maximale
    if ($note_max <= 0 || $note_max > 100) {
        $errors[] = 'La note maximale doit être comprise entre 1 et 100.';
    }
    
    // Validation du coefficient
    if ($coefficient_evaluation <= 0 || $coefficient_evaluation > 10) {
        $errors[] = 'Le coefficient doit être compris entre 0.1 et 10.';
    }
    
    // Vérifier les conflits d'horaire si enseignant spécifié
    if ($enseignant_id && !empty($date_evaluation) && !empty($heure_debut) && !empty($heure_fin)) {
        $stmt = $database->query(
            "SELECT COUNT(*) as conflits FROM evaluations 
             WHERE enseignant_id = ? 
             AND date_evaluation = ? 
             AND status != 'annulee'
             AND ((heure_debut <= ? AND heure_fin > ?) OR (heure_debut < ? AND heure_fin >= ?))",
            [$enseignant_id, $date_evaluation, $heure_debut, $heure_debut, $heure_fin, $heure_fin]
        );
        if ($stmt->fetch()['conflits'] > 0) {
            $errors[] = 'L\'enseignant a déjà une évaluation programmée à cette heure.';
        }
    }
    
    // Si pas d'erreurs, enregistrer l'évaluation
    if (empty($errors)) {
        try {
            $sql = "INSERT INTO evaluations (
                        nom, description, classe_id, matiere_id, enseignant_id,
                        type_evaluation, periode, date_evaluation, heure_debut, heure_fin,
                        duree_minutes, note_max, bareme, consignes, coefficient,
                        annee_scolaire_id, status, user_id
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'programmee', ?)";

            $database->execute($sql, [
                $nom, $description, $classe_id, $matiere_id, $enseignant_id,
                $type_evaluation, $periode, $date_evaluation, $heure_debut, $heure_fin,
                $duree_minutes, $note_max, $bareme, $consignes, $coefficient_evaluation,
                $current_year['id'], $_SESSION['user_id']
            ]);
            
            $evaluation_id = $database->lastInsertId();
            
            showMessage('success', 'Évaluation créée avec succès !');
            redirectTo('view.php?id=' . $evaluation_id);
            
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
        Créer une évaluation
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
                        <div class="col-md-8 mb-3">
                            <label for="nom" class="form-label">Nom de l'évaluation <span class="text-danger">*</span></label>
                            <input type="text" 
                                   class="form-control" 
                                   id="nom" 
                                   name="nom" 
                                   placeholder="Ex: Composition du 1er trimestre"
                                   value="<?php echo htmlspecialchars($_POST['nom'] ?? ''); ?>"
                                   required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="type_evaluation" class="form-label">Type <span class="text-danger">*</span></label>
                            <select class="form-select" id="type_evaluation" name="type_evaluation" required>
                                <option value="">Sélectionner...</option>
                                <option value="devoir" <?php echo ($_POST['type_evaluation'] ?? '') === 'devoir' ? 'selected' : ''; ?>>Devoir</option>
                                <option value="composition" <?php echo ($_POST['type_evaluation'] ?? '') === 'composition' ? 'selected' : ''; ?>>Composition</option>
                                <option value="examen" <?php echo ($_POST['type_evaluation'] ?? '') === 'examen' ? 'selected' : ''; ?>>Examen</option>
                                <option value="interrogation" <?php echo ($_POST['type_evaluation'] ?? '') === 'interrogation' ? 'selected' : ''; ?>>Interrogation</option>
                                <option value="controle" <?php echo ($_POST['type_evaluation'] ?? '') === 'controle' ? 'selected' : ''; ?>>Contrôle</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="classe_id" class="form-label">Classe <span class="text-danger">*</span></label>
                            <select class="form-select" id="classe_id" name="classe_id" required>
                                <option value="">Sélectionner une classe...</option>
                                <?php foreach ($classes as $classe): ?>
                                    <option value="<?php echo $classe['id']; ?>" 
                                            <?php echo ($_POST['classe_id'] ?? '') == $classe['id'] ? 'selected' : ''; ?>
                                            data-niveau="<?php echo $classe['niveau']; ?>">
                                        <?php echo htmlspecialchars($classe['nom']); ?> 
                                        (<?php echo ucfirst($classe['niveau']); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="matiere_id" class="form-label">Matière <span class="text-danger">*</span></label>
                            <select class="form-select" id="matiere_id" name="matiere_id" required>
                                <option value="">Sélectionner une matière...</option>
                                <?php foreach ($matieres as $matiere): ?>
                                    <option value="<?php echo $matiere['id']; ?>" 
                                            <?php echo ($_POST['matiere_id'] ?? '') == $matiere['id'] ? 'selected' : ''; ?>
                                            data-coefficient="<?php echo $matiere['coefficient']; ?>">
                                        <?php echo htmlspecialchars($matiere['nom']); ?>
                                        <?php if ($matiere['coefficient']): ?>
                                            (Coef. <?php echo $matiere['coefficient']; ?>)
                                        <?php endif; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="enseignant_id" class="form-label">Enseignant</label>
                            <select class="form-select" id="enseignant_id" name="enseignant_id">
                                <option value="">Sélectionner un enseignant...</option>
                                <?php foreach ($enseignants as $enseignant): ?>
                                    <option value="<?php echo $enseignant['id']; ?>" 
                                            <?php echo ($_POST['enseignant_id'] ?? '') == $enseignant['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($enseignant['nom'] . ' ' . $enseignant['prenom']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="periode" class="form-label">Période <span class="text-danger">*</span></label>
                            <select class="form-select" id="periode" name="periode" required>
                                <option value="">Sélectionner...</option>
                                <option value="1er_trimestre" <?php echo ($_POST['periode'] ?? '') === '1er_trimestre' ? 'selected' : ''; ?>>1er Trimestre</option>
                                <option value="2eme_trimestre" <?php echo ($_POST['periode'] ?? '') === '2eme_trimestre' ? 'selected' : ''; ?>>2ème Trimestre</option>
                                <option value="3eme_trimestre" <?php echo ($_POST['periode'] ?? '') === '3eme_trimestre' ? 'selected' : ''; ?>>3ème Trimestre</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="description" class="form-label">Description</label>
                        <textarea class="form-control" 
                                  id="description" 
                                  name="description" 
                                  rows="3"
                                  placeholder="Description de l'évaluation, chapitres concernés..."><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
                    </div>
                </div>
            </div>
            
            <!-- Paramètres d'évaluation -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-cog me-2"></i>
                        Paramètres d'évaluation
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label for="date_evaluation" class="form-label">Date d'évaluation <span class="text-danger">*</span></label>
                            <input type="date" 
                                   class="form-control" 
                                   id="date_evaluation" 
                                   name="date_evaluation" 
                                   value="<?php echo htmlspecialchars($_POST['date_evaluation'] ?? ''); ?>"
                                   min="<?php echo date('Y-m-d'); ?>"
                                   required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="heure_debut" class="form-label">Heure de début</label>
                            <input type="time" 
                                   class="form-control" 
                                   id="heure_debut" 
                                   name="heure_debut" 
                                   value="<?php echo htmlspecialchars($_POST['heure_debut'] ?? ''); ?>">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="heure_fin" class="form-label">Heure de fin</label>
                            <input type="time" 
                                   class="form-control" 
                                   id="heure_fin" 
                                   name="heure_fin" 
                                   value="<?php echo htmlspecialchars($_POST['heure_fin'] ?? ''); ?>">
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label for="duree_minutes" class="form-label">Durée (minutes)</label>
                            <input type="number" 
                                   class="form-control" 
                                   id="duree_minutes" 
                                   name="duree_minutes" 
                                   min="1" 
                                   max="480"
                                   placeholder="Ex: 120"
                                   value="<?php echo htmlspecialchars($_POST['duree_minutes'] ?? ''); ?>">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="note_max" class="form-label">Note maximale</label>
                            <input type="number" 
                                   class="form-control" 
                                   id="note_max" 
                                   name="note_max" 
                                   min="1" 
                                   max="100"
                                   step="0.5"
                                   value="<?php echo htmlspecialchars($_POST['note_max'] ?? '20'); ?>">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="coefficient_evaluation" class="form-label">Coefficient</label>
                            <input type="number" 
                                   class="form-control" 
                                   id="coefficient_evaluation" 
                                   name="coefficient_evaluation" 
                                   min="0.1" 
                                   max="10"
                                   step="0.1"
                                   value="<?php echo htmlspecialchars($_POST['coefficient_evaluation'] ?? '1'); ?>">
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="bareme" class="form-label">Barème</label>
                            <textarea class="form-control" 
                                      id="bareme" 
                                      name="bareme" 
                                      rows="3"
                                      placeholder="Ex: Question 1: 5 pts, Question 2: 10 pts..."><?php echo htmlspecialchars($_POST['bareme'] ?? ''); ?></textarea>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="consignes" class="form-label">Consignes</label>
                            <textarea class="form-control" 
                                      id="consignes" 
                                      name="consignes" 
                                      rows="3"
                                      placeholder="Consignes particulières pour l'évaluation..."><?php echo htmlspecialchars($_POST['consignes'] ?? ''); ?></textarea>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Aide et suggestions -->
        <div class="col-lg-4">
            <!-- Types d'évaluations -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-info-circle me-2"></i>
                        Types d'évaluations
                    </h5>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <h6><i class="fas fa-book text-info me-2"></i>Devoir</h6>
                        <p class="small text-muted">
                            Évaluation régulière sur un chapitre ou une leçon spécifique.
                        </p>
                    </div>
                    <div class="mb-3">
                        <h6><i class="fas fa-clipboard-check text-warning me-2"></i>Composition</h6>
                        <p class="small text-muted">
                            Évaluation trimestrielle couvrant plusieurs chapitres.
                        </p>
                    </div>
                    <div class="mb-3">
                        <h6><i class="fas fa-graduation-cap text-danger me-2"></i>Examen</h6>
                        <p class="small text-muted">
                            Évaluation finale ou officielle (TENAFEP, Baccalauréat).
                        </p>
                    </div>
                    <div>
                        <h6><i class="fas fa-question text-secondary me-2"></i>Interrogation</h6>
                        <p class="small text-muted">
                            Évaluation courte et rapide sur une notion précise.
                        </p>
                    </div>
                </div>
            </div>
            
            <!-- Suggestions de durée -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-clock me-2"></i>
                        Durées suggérées
                    </h5>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2" id="duree-suggestions">
                        <button type="button" class="btn btn-outline-primary btn-duree" data-duree="30">
                            Interrogation : 30 min
                        </button>
                        <button type="button" class="btn btn-outline-success btn-duree" data-duree="60">
                            Devoir : 1h
                        </button>
                        <button type="button" class="btn btn-outline-warning btn-duree" data-duree="120">
                            Composition : 2h
                        </button>
                        <button type="button" class="btn btn-outline-danger btn-duree" data-duree="180">
                            Examen : 3h
                        </button>
                    </div>
                </div>
            </div>
            
            <!-- Système de notation RDC -->
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-star me-2"></i>
                        Système de notation RDC
                    </h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Note</th>
                                    <th>Mention</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td><span class="badge bg-success">16-20</span></td>
                                    <td>Excellent</td>
                                </tr>
                                <tr>
                                    <td><span class="badge bg-info">14-15</span></td>
                                    <td>Très bien</td>
                                </tr>
                                <tr>
                                    <td><span class="badge bg-primary">12-13</span></td>
                                    <td>Bien</td>
                                </tr>
                                <tr>
                                    <td><span class="badge bg-warning">10-11</span></td>
                                    <td>Satisfaisant</td>
                                </tr>
                                <tr>
                                    <td><span class="badge bg-secondary">8-9</span></td>
                                    <td>Passable</td>
                                </tr>
                                <tr>
                                    <td><span class="badge bg-danger">0-7</span></td>
                                    <td>Insuffisant</td>
                                </tr>
                            </tbody>
                        </table>
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
                                Créer l'évaluation
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</form>

<script>
// Calcul automatique de la durée
document.getElementById('heure_debut').addEventListener('change', calculateDuration);
document.getElementById('heure_fin').addEventListener('change', calculateDuration);

function calculateDuration() {
    const debut = document.getElementById('heure_debut').value;
    const fin = document.getElementById('heure_fin').value;
    
    if (debut && fin) {
        const debutTime = new Date('2000-01-01 ' + debut);
        const finTime = new Date('2000-01-01 ' + fin);
        
        if (finTime > debutTime) {
            const diffMs = finTime - debutTime;
            const diffMinutes = Math.floor(diffMs / (1000 * 60));
            document.getElementById('duree_minutes').value = diffMinutes;
        }
    }
}

// Sélection des durées suggérées
document.querySelectorAll('.btn-duree').forEach(function(btn) {
    btn.addEventListener('click', function() {
        const duree = this.dataset.duree;
        document.getElementById('duree_minutes').value = duree;
        
        // Highlight temporaire
        this.classList.add('active');
        setTimeout(() => {
            this.classList.remove('active');
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
