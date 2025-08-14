<?php
/**
 * Module Académique - Ajouter une année scolaire
 * Application de gestion scolaire - République Démocratique du Congo
 */

require_once '../../../config/config.php';
require_once '../../../config/database.php';
require_once '../../../includes/functions.php';

// Vérifier l'authentification et les permissions
requireLogin();
if (!checkPermission('admin') && !checkPermission('academic')) {
    showMessage('error', 'Accès refusé à cette fonctionnalité.');
    redirectTo('../../../index.php');
}

$page_title = 'Ajouter une année scolaire';

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $annee = sanitizeInput($_POST['annee']);
        $date_debut = sanitizeInput($_POST['date_debut']);
        $date_fin = sanitizeInput($_POST['date_fin']);
        $status = sanitizeInput($_POST['status']);
        $auto_activate = isset($_POST['auto_activate']);
        
        // Validations
        if (empty($annee) || empty($date_debut) || empty($date_fin)) {
            throw new Exception('Tous les champs obligatoires doivent être remplis.');
        }
        
        // Vérifier le format de l'année
        if (!preg_match('/^\d{4}-\d{4}$/', $annee)) {
            throw new Exception('Le format de l\'année doit être YYYY-YYYY (ex: 2024-2025).');
        }
        
        // Vérifier que la date de fin est postérieure à la date de début
        if ($date_fin <= $date_debut) {
            throw new Exception('La date de fin doit être postérieure à la date de début.');
        }
        
        // Vérifier que l'année n'existe pas déjà
        $existing = $database->query(
            "SELECT id FROM annees_scolaires WHERE annee = ?",
            [$annee]
        )->fetch();
        
        if ($existing) {
            throw new Exception('Cette année scolaire existe déjà.');
        }
        
        // Vérifier les chevauchements de dates
        $overlap = $database->query(
            "SELECT id, annee FROM annees_scolaires 
             WHERE (date_debut <= ? AND date_fin >= ?) 
             OR (date_debut <= ? AND date_fin >= ?)
             OR (date_debut >= ? AND date_fin <= ?)",
            [$date_debut, $date_debut, $date_fin, $date_fin, $date_debut, $date_fin]
        )->fetch();
        
        if ($overlap) {
            throw new Exception('Les dates de cette année scolaire chevauchent avec l\'année existante: ' . $overlap['annee']);
        }
        
        // Si auto-activation, désactiver les autres années
        if ($auto_activate || $status === 'active') {
            $database->execute("UPDATE annees_scolaires SET status = 'fermee'");
            $status = 'active';
        }
        
        // Insérer la nouvelle année scolaire
        $database->execute(
            "INSERT INTO annees_scolaires (annee, date_debut, date_fin, status, created_at) 
             VALUES (?, ?, ?, ?, NOW())",
            [$annee, $date_debut, $date_fin, $status]
        );
        
        $year_id = $database->lastInsertId();
        
        // Enregistrer l'action
        logUserAction(
            'create_academic_year',
            'academic',
            'Nouvelle année scolaire créée: ' . $annee . ' (' . $status . ')',
            $year_id
        );
        
        showMessage('success', 'Année scolaire créée avec succès.');
        
        // Rediriger vers la liste
        redirectTo('index.php');
        
    } catch (Exception $e) {
        showMessage('error', 'Erreur lors de la création: ' . $e->getMessage());
    }
}

// Suggérer la prochaine année scolaire
$current_year = date('Y');
$next_year = $current_year + 1;
$suggested_year = $current_year . '-' . $next_year;
$suggested_date_debut = $current_year . '-09-01';
$suggested_date_fin = $next_year . '-07-31';

// Vérifier si l'année suggérée existe déjà
$existing_suggested = $database->query(
    "SELECT id FROM annees_scolaires WHERE annee = ?",
    [$suggested_year]
)->fetch();

if ($existing_suggested) {
    // Suggérer l'année suivante
    $current_year++;
    $next_year++;
    $suggested_year = $current_year . '-' . $next_year;
    $suggested_date_debut = $current_year . '-09-01';
    $suggested_date_fin = $next_year . '-07-31';
}

// Vérifier s'il y a une année active
$active_year = $database->query("SELECT * FROM annees_scolaires WHERE status = 'active' LIMIT 1")->fetch();

include '../../../includes/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">
        <i class="fas fa-plus me-2"></i>
        Ajouter une année scolaire
    </h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <div class="btn-group me-2">
            <a href="index.php" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-1"></i>
                Retour
            </a>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-calendar-plus me-2"></i>
                    Informations de l'année scolaire
                </h5>
            </div>
            <div class="card-body">
                <form method="POST" id="addYearForm">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="annee" class="form-label">Année scolaire <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="annee" name="annee" 
                                   value="<?php echo htmlspecialchars($_POST['annee'] ?? $suggested_year); ?>"
                                   placeholder="Ex: 2024-2025" pattern="\d{4}-\d{4}" required>
                            <div class="form-text">Format: YYYY-YYYY (ex: 2024-2025)</div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="status" class="form-label">Statut initial</label>
                            <select class="form-select" id="status" name="status">
                                <option value="fermee" <?php echo (isset($_POST['status']) && $_POST['status'] === 'fermee') ? 'selected' : ''; ?>>
                                    Fermée (inactive)
                                </option>
                                <option value="active" <?php echo (isset($_POST['status']) && $_POST['status'] === 'active') ? 'selected' : ''; ?>>
                                    Active
                                </option>
                            </select>
                            <div class="form-text">
                                <?php if ($active_year): ?>
                                    <span class="text-warning">
                                        <i class="fas fa-exclamation-triangle me-1"></i>
                                        Il y a déjà une année active (<?php echo $active_year['annee']; ?>)
                                    </span>
                                <?php else: ?>
                                    <span class="text-info">
                                        <i class="fas fa-info-circle me-1"></i>
                                        Aucune année active actuellement
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="date_debut" class="form-label">Date de début <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" id="date_debut" name="date_debut" 
                                   value="<?php echo htmlspecialchars($_POST['date_debut'] ?? $suggested_date_debut); ?>" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="date_fin" class="form-label">Date de fin <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" id="date_fin" name="date_fin" 
                                   value="<?php echo htmlspecialchars($_POST['date_fin'] ?? $suggested_date_fin); ?>" required>
                        </div>
                    </div>
                    
                    <?php if ($active_year): ?>
                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="auto_activate" name="auto_activate" 
                                   <?php echo isset($_POST['auto_activate']) ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="auto_activate">
                                <strong>Activer automatiquement cette année</strong>
                            </label>
                            <div class="form-text text-warning">
                                <i class="fas fa-exclamation-triangle me-1"></i>
                                Cela désactivera automatiquement l'année actuellement active (<?php echo $active_year['annee']; ?>)
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                        <a href="index.php" class="btn btn-outline-secondary">
                            <i class="fas fa-times me-1"></i>
                            Annuler
                        </a>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-1"></i>
                            Créer l'année scolaire
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-info-circle me-2"></i>
                    Aide
                </h5>
            </div>
            <div class="card-body">
                <div class="alert alert-info">
                    <h6><i class="fas fa-lightbulb me-2"></i>Conseils</h6>
                    <ul class="mb-0">
                        <li>L'année scolaire suit généralement le format YYYY-YYYY</li>
                        <li>La période standard va de septembre à juillet</li>
                        <li>Une seule année peut être active à la fois</li>
                        <li>Les dates ne peuvent pas se chevaucher</li>
                    </ul>
                </div>
                
                <div class="alert alert-warning">
                    <h6><i class="fas fa-exclamation-triangle me-2"></i>Important</h6>
                    <ul class="mb-0">
                        <li>Vérifiez bien les dates avant de créer</li>
                        <li>L'activation désactive les autres années</li>
                        <li>Une année fermée peut être réactivée</li>
                    </ul>
                </div>
            </div>
        </div>
        
        <div class="card mt-3">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-calendar me-2"></i>
                    Années existantes
                </h5>
            </div>
            <div class="card-body">
                <?php
                $existing_years = $database->query(
                    "SELECT annee, status FROM annees_scolaires ORDER BY date_debut DESC LIMIT 5"
                )->fetchAll();
                ?>
                
                <?php if (!empty($existing_years)): ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($existing_years as $year): ?>
                            <div class="list-group-item border-0 px-0 py-2">
                                <div class="d-flex justify-content-between align-items-center">
                                    <span><?php echo htmlspecialchars($year['annee']); ?></span>
                                    <span class="badge bg-<?php echo $year['status'] === 'active' ? 'success' : 'secondary'; ?>">
                                        <?php echo $year['status'] === 'active' ? 'Active' : 'Fermée'; ?>
                                    </span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p class="text-muted mb-0">Aucune année scolaire existante</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
// Validation du formulaire
document.getElementById('addYearForm').addEventListener('submit', function(e) {
    const annee = document.getElementById('annee').value;
    const dateDebut = document.getElementById('date_debut').value;
    const dateFin = document.getElementById('date_fin').value;
    
    // Vérifier le format de l'année
    const yearPattern = /^\d{4}-\d{4}$/;
    if (!yearPattern.test(annee)) {
        e.preventDefault();
        alert('Le format de l\'année doit être YYYY-YYYY (ex: 2024-2025)');
        return;
    }
    
    // Vérifier que la date de fin est postérieure à la date de début
    if (dateFin <= dateDebut) {
        e.preventDefault();
        alert('La date de fin doit être postérieure à la date de début');
        return;
    }
    
    // Vérifier la cohérence avec l'année
    const years = annee.split('-');
    const startYear = parseInt(years[0]);
    const endYear = parseInt(years[1]);
    
    const startDate = new Date(dateDebut);
    const endDate = new Date(dateFin);
    
    if (startDate.getFullYear() < startYear || endDate.getFullYear() > endYear) {
        if (!confirm('Les dates ne correspondent pas exactement à l\'année scolaire spécifiée. Continuer ?')) {
            e.preventDefault();
            return;
        }
    }
});

// Auto-génération de l'année basée sur les dates
document.getElementById('date_debut').addEventListener('change', function() {
    const dateDebut = new Date(this.value);
    if (dateDebut) {
        const startYear = dateDebut.getFullYear();
        const endYear = startYear + 1;
        document.getElementById('annee').value = startYear + '-' + endYear;
        
        // Suggérer la date de fin
        const dateFin = new Date(endYear, 6, 31); // 31 juillet
        document.getElementById('date_fin').value = dateFin.toISOString().split('T')[0];
    }
});

// Gestion de l'activation automatique
document.getElementById('status').addEventListener('change', function() {
    const autoActivateCheckbox = document.getElementById('auto_activate');
    if (this.value === 'active' && autoActivateCheckbox) {
        autoActivateCheckbox.checked = true;
    }
});

<?php if ($active_year): ?>
document.getElementById('auto_activate').addEventListener('change', function() {
    const statusSelect = document.getElementById('status');
    if (this.checked) {
        statusSelect.value = 'active';
    }
});
<?php endif; ?>
</script>

<?php include '../../../includes/footer.php'; ?>
