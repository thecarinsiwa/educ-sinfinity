<?php
/**
 * Module Rapports AcadÃ©miques - GÃ©nÃ©ration de tous les bulletins
 * Application de gestion scolaire - RÃ©publique DÃ©mocratique du Congo
 */

require_once '../../../../config/config.php';
require_once '../../../../config/database.php';
require_once '../../../../includes/functions.php';
require_once '../../../../includes/permissions-pages.php';

// Vérifier l'authentification et les permissions
requireLogin();
requirePagePermissionFromDB('reports', 'academic/bulletins/generate-all', 'read', '../../../../dashboard.php');

$page_title = 'GÃ©nÃ©ration des bulletins';
$current_year = getCurrentAcademicYear();

if (!$current_year) {
    showMessage('error', 'Aucune annÃ©e scolaire active.');
    redirectTo('../../../index.php');
}

$errors = [];
$success = false;
$generated_count = 0;

// RÃ©cupÃ©rer les classes pour le filtre
$classes = $database->query(
    "SELECT c.id, c.nom, c.niveau, c.section,
            COUNT(DISTINCT i.eleve_id) as nb_eleves
     FROM classes c
     LEFT JOIN inscriptions i ON c.id = i.classe_id AND i.status = 'inscrit'
     WHERE c.annee_scolaire_id = ?
     GROUP BY c.id, c.nom, c.niveau, c.section
     ORDER BY c.niveau, c.nom",
    [$current_year['id']]
)->fetchAll();

// RÃ©cupÃ©rer les pÃ©riodes disponibles
$periodes = $database->query(
    "SELECT DISTINCT periode FROM evaluations 
     WHERE annee_scolaire_id = ? 
     ORDER BY 
        CASE periode 
            WHEN '1er trimestre' THEN 1 
            WHEN '2Ã¨me trimestre' THEN 2 
            WHEN '3Ã¨me trimestre' THEN 3 
        END",
    [$current_year['id']]
)->fetchAll();

// Traitement de la gÃ©nÃ©ration
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $classe_id = (int)($_POST['classe_id'] ?? 0);
    $periode = sanitizeInput($_POST['periode'] ?? '');
    $format = sanitizeInput($_POST['format'] ?? 'pdf');
    $include_observations = isset($_POST['include_observations']);
    $include_graphiques = isset($_POST['include_graphiques']);
    
    // Validation
    if (!$classe_id) {
        $errors[] = 'Veuillez sÃ©lectionner une classe.';
    }
    
    if (empty($periode)) {
        $errors[] = 'Veuillez sÃ©lectionner une pÃ©riode.';
    }
    
    if (empty($errors)) {
        // RÃ©cupÃ©rer tous les Ã©lÃ¨ves de la classe
        $eleves = $database->query(
            "SELECT e.id, e.nom, e.prenom, e.numero_matricule,
                    c.nom as classe_nom, c.niveau, c.section
             FROM eleves e
             JOIN inscriptions i ON e.id = i.eleve_id
             JOIN classes c ON i.classe_id = c.id
             WHERE i.classe_id = ? AND i.status = 'inscrit' AND i.annee_scolaire_id = ?
             ORDER BY e.nom, e.prenom",
            [$classe_id, $current_year['id']]
        )->fetchAll();
        
        if (empty($eleves)) {
            $errors[] = 'Aucun Ã©lÃ¨ve trouvÃ© dans cette classe.';
        } else {
            $generated_count = count($eleves);
            $success = true;
            
            // Enregistrer l'action
            logAction('reports', "GÃ©nÃ©ration de $generated_count bulletins pour la classe " . $classes[array_search($classe_id, array_column($classes, 'id'))]['nom'] . " - PÃ©riode: $periode");
        }
    }
}

include '../../../../includes/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">
        <i class="fas fa-file-pdf me-2"></i>
        GÃ©nÃ©ration des bulletins
    </h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <div class="btn-group me-2">
            <a href="../index.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left me-1"></i>
                Retour aux rapports
            </a>
        </div>
    </div>
</div>

<?php if (!empty($errors)): ?>
    <div class="alert alert-danger">
        <h5><i class="fas fa-exclamation-triangle me-2"></i>Erreurs dÃ©tectÃ©es :</h5>
        <ul class="mb-0">
            <?php foreach ($errors as $error): ?>
                <li><?php echo htmlspecialchars($error); ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<?php if ($success): ?>
    <div class="alert alert-success">
        <h5><i class="fas fa-check-circle me-2"></i>GÃ©nÃ©ration rÃ©ussie !</h5>
        <p class="mb-0">
            <?php echo $generated_count; ?> bulletin(s) gÃ©nÃ©rÃ©(s) avec succÃ¨s pour la pÃ©riode <?php echo htmlspecialchars($periode); ?>.
        </p>
    </div>
<?php endif; ?>

<div class="row">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-cog me-2"></i>
                    ParamÃ¨tres de gÃ©nÃ©ration
                </h5>
            </div>
            <div class="card-body">
                <form method="POST" action="">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="classe_id" class="form-label">
                                    Classe <span class="text-danger">*</span>
                                </label>
                                <select class="form-select" id="classe_id" name="classe_id" required>
                                    <option value="">SÃ©lectionner une classe</option>
                                    <?php foreach ($classes as $classe): ?>
                                        <option value="<?php echo $classe['id']; ?>" 
                                                <?php echo isset($_POST['classe_id']) && $_POST['classe_id'] == $classe['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($classe['nom']); ?>
                                            (<?php echo ucfirst($classe['niveau']); ?>)
                                            - <?php echo $classe['nb_eleves']; ?> Ã©lÃ¨ve(s)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="periode" class="form-label">
                                    PÃ©riode <span class="text-danger">*</span>
                                </label>
                                <select class="form-select" id="periode" name="periode" required>
                                    <option value="">SÃ©lectionner une pÃ©riode</option>
                                    <?php foreach ($periodes as $p): ?>
                                        <option value="<?php echo htmlspecialchars($p['periode']); ?>" 
                                                <?php echo isset($_POST['periode']) && $_POST['periode'] === $p['periode'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($p['periode']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="format" class="form-label">Format d'export</label>
                                <select class="form-select" id="format" name="format">
                                    <option value="pdf" <?php echo (!isset($_POST['format']) || $_POST['format'] === 'pdf') ? 'selected' : ''; ?>>PDF</option>
                                    <option value="excel" <?php echo (isset($_POST['format']) && $_POST['format'] === 'excel') ? 'selected' : ''; ?>>Excel</option>
                                    <option value="word" <?php echo (isset($_POST['format']) && $_POST['format'] === 'word') ? 'selected' : ''; ?>>Word</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Options d'inclusion</label>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="include_observations" name="include_observations" 
                                           <?php echo isset($_POST['include_observations']) ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="include_observations">
                                        Inclure les observations des enseignants
                                    </label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="include_graphiques" name="include_graphiques" 
                                           <?php echo isset($_POST['include_graphiques']) ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="include_graphiques">
                                        Inclure les graphiques de performance
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="d-flex justify-content-between">
                        <a href="../index.php" class="btn btn-secondary">
                            <i class="fas fa-times me-1"></i>
                            Annuler
                        </a>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-download me-1"></i>
                            GÃ©nÃ©rer les bulletins
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-md-4">
        <!-- Informations -->
        <div class="card mb-3">
            <div class="card-header">
                <h6 class="mb-0">
                    <i class="fas fa-info-circle me-2"></i>
                    Informations
                </h6>
            </div>
            <div class="card-body">
                <p class="mb-2">
                    <strong>AnnÃ©e scolaire :</strong><br>
                    <?php echo htmlspecialchars($current_year['annee']); ?>
                </p>
                <p class="mb-2">
                    <strong>Classes disponibles :</strong><br>
                    <?php echo count($classes); ?> classe(s)
                </p>
                <p class="mb-0">
                    <strong>PÃ©riodes disponibles :</strong><br>
                    <?php echo count($periodes); ?> pÃ©riode(s)
                </p>
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
                    <a href="../index.php" class="btn btn-outline-primary btn-sm">
                        <i class="fas fa-chart-bar me-1"></i>
                        Rapports acadÃ©miques
                    </a>
                    <a href="../../evaluations/notes/index.php" class="btn btn-outline-info btn-sm">
                        <i class="fas fa-sticky-note me-1"></i>
                        Gestion des notes
                    </a>
                    <a href="../../students/index.php" class="btn btn-outline-success btn-sm">
                        <i class="fas fa-users me-1"></i>
                        Liste des Ã©lÃ¨ves
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const classeSelect = document.getElementById('classe_id');
    const periodeSelect = document.getElementById('periode');
    const submitBtn = document.querySelector('button[type="submit"]');
    
    // Validation en temps rÃ©el
    function validateForm() {
        const classeValid = classeSelect.value !== '';
        const periodeValid = periodeSelect.value !== '';
        
        submitBtn.disabled = !(classeValid && periodeValid);
    }
    
    classeSelect.addEventListener('change', validateForm);
    periodeSelect.addEventListener('change', validateForm);
    
    // Validation initiale
    validateForm();
});
</script>

<?php include '../../../../includes/footer.php'; ?>



