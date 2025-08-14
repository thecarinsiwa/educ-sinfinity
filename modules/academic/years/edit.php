<?php
/**
 * Module Académique - Modifier une année scolaire
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

$page_title = 'Modifier une année scolaire';

// Récupérer l'ID de l'année à modifier
$year_id = (int)($_GET['id'] ?? 0);

if (!$year_id) {
    showMessage('error', 'ID de l\'année scolaire manquant.');
    redirectTo('index.php');
}

// Récupérer les informations de l'année scolaire
$year = $database->query(
    "SELECT * FROM annees_scolaires WHERE id = ?",
    [$year_id]
)->fetch();

if (!$year) {
    showMessage('error', 'Année scolaire non trouvée.');
    redirectTo('index.php');
}

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $annee = sanitizeInput($_POST['annee']);
        $date_debut = sanitizeInput($_POST['date_debut']);
        $date_fin = sanitizeInput($_POST['date_fin']);
        $status = sanitizeInput($_POST['status']);
        
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
        
        // Vérifier que l'année n'existe pas déjà (sauf pour l'année actuelle)
        $existing = $database->query(
            "SELECT id FROM annees_scolaires WHERE annee = ? AND id != ?",
            [$annee, $year_id]
        )->fetch();
        
        if ($existing) {
            throw new Exception('Cette année scolaire existe déjà.');
        }
        
        // Vérifier les chevauchements de dates (sauf pour l'année actuelle)
        $overlap = $database->query(
            "SELECT id, annee FROM annees_scolaires 
             WHERE ((date_debut <= ? AND date_fin >= ?) 
             OR (date_debut <= ? AND date_fin >= ?)
             OR (date_debut >= ? AND date_fin <= ?))
             AND id != ?",
            [$date_debut, $date_debut, $date_fin, $date_fin, $date_debut, $date_fin, $year_id]
        )->fetch();
        
        if ($overlap) {
            throw new Exception('Les dates de cette année scolaire chevauchent avec l\'année existante: ' . $overlap['annee']);
        }
        
        // Si on active cette année, désactiver les autres
        if ($status === 'active' && $year['status'] !== 'active') {
            $database->execute("UPDATE annees_scolaires SET status = 'fermee' WHERE id != ?", [$year_id]);
        }
        
        // Mettre à jour l'année scolaire
        $database->execute(
            "UPDATE annees_scolaires 
             SET annee = ?, date_debut = ?, date_fin = ?, status = ?, updated_at = NOW()
             WHERE id = ?",
            [$annee, $date_debut, $date_fin, $status, $year_id]
        );
        
        // Enregistrer l'action
        logUserAction(
            'update_academic_year',
            'academic',
            'Année scolaire modifiée: ' . $annee . ' (' . $status . ')',
            $year_id
        );
        
        showMessage('success', 'Année scolaire modifiée avec succès.');
        
        // Recharger les données
        $year = $database->query(
            "SELECT * FROM annees_scolaires WHERE id = ?",
            [$year_id]
        )->fetch();
        
    } catch (Exception $e) {
        showMessage('error', 'Erreur lors de la modification: ' . $e->getMessage());
    }
}

// Statistiques pour cette année
$stats = [];
$stats['classes'] = $database->query(
    "SELECT COUNT(*) as total FROM classes WHERE annee_scolaire_id = ?",
    [$year_id]
)->fetch()['total'];

$stats['eleves'] = $database->query(
    "SELECT COUNT(*) as total FROM inscriptions WHERE annee_scolaire_id = ? AND status = 'inscrit'",
    [$year_id]
)->fetch()['total'];

try {
    $stats['emplois'] = $database->query(
        "SELECT COUNT(DISTINCT classe_id) as total FROM emploi_temps WHERE annee_scolaire_id = ?",
        [$year_id]
    )->fetch()['total'];
} catch (Exception $e) {
    $stats['emplois'] = 0;
}

$stats['paiements'] = $database->query(
    "SELECT COUNT(*) as total FROM paiements WHERE annee_scolaire_id = ?",
    [$year_id]
)->fetch()['total'];

// Vérifier s'il y a une autre année active
$other_active = $database->query(
    "SELECT * FROM annees_scolaires WHERE status = 'active' AND id != ? LIMIT 1",
    [$year_id]
)->fetch();

include '../../../includes/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">
        <i class="fas fa-edit me-2"></i>
        Modifier l'année scolaire
    </h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <div class="btn-group me-2">
            <a href="index.php" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-1"></i>
                Retour
            </a>
        </div>
        <div class="btn-group">
            <span class="badge bg-<?php echo $year['status'] === 'active' ? 'success' : 'secondary'; ?> fs-6">
                <?php echo $year['status'] === 'active' ? 'ACTIVE' : 'FERMÉE'; ?>
            </span>
        </div>
    </div>
</div>

<!-- Statistiques de l'année -->
<div class="row mb-4">
    <div class="col-lg-3 col-md-6 mb-3">
        <div class="card text-center">
            <div class="card-body">
                <i class="fas fa-school fa-2x text-primary mb-2"></i>
                <h3 class="mb-0"><?php echo number_format($stats['classes']); ?></h3>
                <small class="text-muted">Classes</small>
            </div>
        </div>
    </div>
    <div class="col-lg-3 col-md-6 mb-3">
        <div class="card text-center">
            <div class="card-body">
                <i class="fas fa-users fa-2x text-success mb-2"></i>
                <h3 class="mb-0"><?php echo number_format($stats['eleves']); ?></h3>
                <small class="text-muted">Élèves inscrits</small>
            </div>
        </div>
    </div>
    <div class="col-lg-3 col-md-6 mb-3">
        <div class="card text-center">
            <div class="card-body">
                <i class="fas fa-calendar-alt fa-2x text-info mb-2"></i>
                <h3 class="mb-0"><?php echo number_format($stats['emplois']); ?></h3>
                <small class="text-muted">Emplois du temps</small>
            </div>
        </div>
    </div>
    <div class="col-lg-3 col-md-6 mb-3">
        <div class="card text-center">
            <div class="card-body">
                <i class="fas fa-money-bill fa-2x text-warning mb-2"></i>
                <h3 class="mb-0"><?php echo number_format($stats['paiements']); ?></h3>
                <small class="text-muted">Paiements</small>
            </div>
        </div>
    </div>
</div>

<?php if ($stats['classes'] > 0 || $stats['eleves'] > 0 || $stats['paiements'] > 0): ?>
<div class="alert alert-warning">
    <h6><i class="fas fa-exclamation-triangle me-2"></i>Attention</h6>
    <p class="mb-0">Cette année scolaire contient des données importantes. Soyez prudent lors des modifications, 
    surtout des dates qui pourraient affecter les inscriptions et les paiements existants.</p>
</div>
<?php endif; ?>

<div class="row">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-calendar-edit me-2"></i>
                    Informations de l'année scolaire
                </h5>
            </div>
            <div class="card-body">
                <form method="POST" id="editYearForm">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="annee" class="form-label">Année scolaire <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="annee" name="annee" 
                                   value="<?php echo htmlspecialchars($_POST['annee'] ?? $year['annee']); ?>"
                                   placeholder="Ex: 2024-2025" pattern="\d{4}-\d{4}" required>
                            <div class="form-text">Format: YYYY-YYYY (ex: 2024-2025)</div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="status" class="form-label">Statut</label>
                            <select class="form-select" id="status" name="status">
                                <option value="fermee" <?php echo (($_POST['status'] ?? $year['status']) === 'fermee') ? 'selected' : ''; ?>>
                                    Fermée (inactive)
                                </option>
                                <option value="active" <?php echo (($_POST['status'] ?? $year['status']) === 'active') ? 'selected' : ''; ?>>
                                    Active
                                </option>
                            </select>
                            <div class="form-text">
                                <?php if ($other_active && $year['status'] !== 'active'): ?>
                                    <span class="text-warning">
                                        <i class="fas fa-exclamation-triangle me-1"></i>
                                        Il y a déjà une année active (<?php echo $other_active['annee']; ?>)
                                    </span>
                                <?php elseif ($year['status'] === 'active'): ?>
                                    <span class="text-success">
                                        <i class="fas fa-check-circle me-1"></i>
                                        Cette année est actuellement active
                                    </span>
                                <?php else: ?>
                                    <span class="text-info">
                                        <i class="fas fa-info-circle me-1"></i>
                                        Aucune autre année active
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="date_debut" class="form-label">Date de début <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" id="date_debut" name="date_debut" 
                                   value="<?php echo htmlspecialchars($_POST['date_debut'] ?? $year['date_debut']); ?>" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="date_fin" class="form-label">Date de fin <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" id="date_fin" name="date_fin" 
                                   value="<?php echo htmlspecialchars($_POST['date_fin'] ?? $year['date_fin']); ?>" required>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Informations système</label>
                        <div class="row">
                            <div class="col-md-6">
                                <small class="text-muted">
                                    <strong>Créée le :</strong> <?php echo formatDateTime($year['created_at']); ?>
                                </small>
                            </div>
                            <div class="col-md-6">
                                <small class="text-muted">
                                    <strong>Modifiée le :</strong> 
                                    <?php echo $year['updated_at'] ? formatDateTime($year['updated_at']) : 'Jamais'; ?>
                                </small>
                            </div>
                        </div>
                    </div>
                    
                    <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                        <a href="index.php" class="btn btn-outline-secondary">
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
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-info-circle me-2"></i>
                    Informations
                </h5>
            </div>
            <div class="card-body">
                <div class="alert alert-info">
                    <h6><i class="fas fa-lightbulb me-2"></i>Conseils</h6>
                    <ul class="mb-0">
                        <li>Vérifiez les dates avant de modifier</li>
                        <li>L'activation désactive les autres années</li>
                        <li>Les modifications affectent les données liées</li>
                    </ul>
                </div>
                
                <?php if ($year['status'] === 'active'): ?>
                <div class="alert alert-success">
                    <h6><i class="fas fa-star me-2"></i>Année active</h6>
                    <p class="mb-0">Cette année est actuellement active. Toutes les nouvelles inscriptions et données seront associées à cette année.</p>
                </div>
                <?php endif; ?>
                
                <?php if ($stats['classes'] > 0 || $stats['eleves'] > 0): ?>
                <div class="alert alert-warning">
                    <h6><i class="fas fa-database me-2"></i>Données liées</h6>
                    <p class="mb-2">Cette année contient :</p>
                    <ul class="mb-0">
                        <?php if ($stats['classes'] > 0): ?>
                            <li><?php echo $stats['classes']; ?> classe(s)</li>
                        <?php endif; ?>
                        <?php if ($stats['eleves'] > 0): ?>
                            <li><?php echo $stats['eleves']; ?> élève(s) inscrit(s)</li>
                        <?php endif; ?>
                        <?php if ($stats['emplois'] > 0): ?>
                            <li><?php echo $stats['emplois']; ?> emploi(s) du temps</li>
                        <?php endif; ?>
                        <?php if ($stats['paiements'] > 0): ?>
                            <li><?php echo $stats['paiements']; ?> paiement(s)</li>
                        <?php endif; ?>
                    </ul>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="card mt-3">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-bolt me-2"></i>
                    Actions rapides
                </h5>
            </div>
            <div class="card-body">
                <div class="d-grid gap-2">
                    <?php if ($year['status'] !== 'active'): ?>
                        <button type="button" class="btn btn-success" onclick="activateYear()">
                            <i class="fas fa-play me-2"></i>
                            Activer cette année
                        </button>
                    <?php else: ?>
                        <button type="button" class="btn btn-warning" onclick="closeYear()">
                            <i class="fas fa-pause me-2"></i>
                            Fermer cette année
                        </button>
                    <?php endif; ?>
                    
                    <a href="../schedule/?classe_id=&annee_id=<?php echo $year_id; ?>" class="btn btn-outline-info">
                        <i class="fas fa-calendar me-2"></i>
                        Voir les emplois du temps
                    </a>
                    
                    <a href="../../students/?annee_id=<?php echo $year_id; ?>" class="btn btn-outline-primary">
                        <i class="fas fa-users me-2"></i>
                        Voir les élèves
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Validation du formulaire
document.getElementById('editYearForm').addEventListener('submit', function(e) {
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
    
    // Confirmation si changement de statut vers actif
    const status = document.getElementById('status').value;
    const originalStatus = '<?php echo $year['status']; ?>';
    
    if (status === 'active' && originalStatus !== 'active') {
        <?php if ($other_active): ?>
        if (!confirm('Activer cette année désactivera automatiquement l\'année actuellement active (<?php echo $other_active['annee']; ?>). Continuer ?')) {
            e.preventDefault();
            return;
        }
        <?php endif; ?>
    }
});

function activateYear() {
    document.getElementById('status').value = 'active';
    document.getElementById('editYearForm').submit();
}

function closeYear() {
    if (confirm('Êtes-vous sûr de vouloir fermer cette année scolaire ?')) {
        document.getElementById('status').value = 'fermee';
        document.getElementById('editYearForm').submit();
    }
}
</script>

<?php include '../../../includes/footer.php'; ?>
