<?php
/**
 * Module Académique - Gestion des années scolaires
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

$page_title = 'Gestion des années scolaires';

// Traitement des actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_POST['activate_year'])) {
            $year_id = (int)$_POST['year_id'];
            
            // Désactiver toutes les années
            $database->execute("UPDATE annees_scolaires SET status = 'fermee'");
            
            // Activer l'année sélectionnée
            $database->execute(
                "UPDATE annees_scolaires SET status = 'active' WHERE id = ?",
                [$year_id]
            );
            
            // Récupérer le nom de l'année pour le log
            $year = $database->query("SELECT annee FROM annees_scolaires WHERE id = ?", [$year_id])->fetch();
            
            logUserAction(
                'activate_academic_year',
                'academic',
                'Année scolaire activée: ' . $year['annee'],
                $year_id
            );
            
            showMessage('success', 'Année scolaire activée avec succès.');
            
        } elseif (isset($_POST['close_year'])) {
            $year_id = (int)$_POST['year_id'];
            
            $database->execute(
                "UPDATE annees_scolaires SET status = 'fermee' WHERE id = ?",
                [$year_id]
            );
            
            $year = $database->query("SELECT annee FROM annees_scolaires WHERE id = ?", [$year_id])->fetch();
            
            logUserAction(
                'close_academic_year',
                'academic',
                'Année scolaire fermée: ' . $year['annee'],
                $year_id
            );
            
            showMessage('success', 'Année scolaire fermée avec succès.');
            
        } elseif (isset($_POST['delete_year'])) {
            $year_id = (int)$_POST['year_id'];
            
            // Vérifier s'il y a des données liées
            $linked_data = $database->query(
                "SELECT 
                    (SELECT COUNT(*) FROM classes WHERE annee_scolaire_id = ?) as classes,
                    (SELECT COUNT(*) FROM inscriptions WHERE annee_scolaire_id = ?) as inscriptions,
                    (SELECT COUNT(*) FROM emploi_temps WHERE annee_scolaire_id = ?) as emploi_temps",
                [$year_id, $year_id, $year_id]
            )->fetch();
            
            if ($linked_data['classes'] > 0 || $linked_data['inscriptions'] > 0 || $linked_data['emploi_temps'] > 0) {
                throw new Exception('Impossible de supprimer cette année scolaire car elle contient des données (classes, inscriptions, emplois du temps).');
            }
            
            $year = $database->query("SELECT annee FROM annees_scolaires WHERE id = ?", [$year_id])->fetch();
            
            $database->execute("DELETE FROM annees_scolaires WHERE id = ?", [$year_id]);
            
            logUserAction(
                'delete_academic_year',
                'academic',
                'Année scolaire supprimée: ' . $year['annee'],
                $year_id
            );
            
            showMessage('success', 'Année scolaire supprimée avec succès.');
        }
        
    } catch (Exception $e) {
        showMessage('error', 'Erreur: ' . $e->getMessage());
    }
}

// Récupérer toutes les années scolaires
$annees_scolaires = $database->query(
    "SELECT * FROM annees_scolaires ORDER BY date_debut DESC"
)->fetchAll();

// Statistiques
$stats = [];
$stats['total_annees'] = count($annees_scolaires);
$stats['annee_active'] = count(array_filter($annees_scolaires, function($a) { return $a['status'] === 'active'; }));
$stats['annees_fermees'] = count(array_filter($annees_scolaires, function($a) { return $a['status'] === 'fermee'; }));

// Année active
$current_year = array_filter($annees_scolaires, function($a) { return $a['status'] === 'active'; });
$current_year = !empty($current_year) ? reset($current_year) : null;

// Statistiques détaillées pour l'année active
if ($current_year) {
    $stats['classes_actives'] = $database->query(
        "SELECT COUNT(*) as total FROM classes WHERE annee_scolaire_id = ?",
        [$current_year['id']]
    )->fetch()['total'];
    
    $stats['eleves_inscrits'] = $database->query(
        "SELECT COUNT(*) as total FROM inscriptions WHERE annee_scolaire_id = ? AND status = 'inscrit'",
        [$current_year['id']]
    )->fetch()['total'];
    
    try {
        $stats['emplois_generes'] = $database->query(
            "SELECT COUNT(DISTINCT classe_id) as total FROM emploi_temps WHERE annee_scolaire_id = ?",
            [$current_year['id']]
        )->fetch()['total'];
    } catch (Exception $e) {
        $stats['emplois_generes'] = 0;
    }
} else {
    $stats['classes_actives'] = 0;
    $stats['eleves_inscrits'] = 0;
    $stats['emplois_generes'] = 0;
}

include '../../../includes/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">
        <i class="fas fa-calendar-alt me-2"></i>
        Gestion des années scolaires
    </h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <div class="btn-group me-2">
            <a href="../index.php" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-1"></i>
                Retour
            </a>
        </div>
        <div class="btn-group">
            <a href="add.php" class="btn btn-primary">
                <i class="fas fa-plus me-1"></i>
                Nouvelle année scolaire
            </a>
        </div>
    </div>
</div>

<!-- Statistiques principales -->
<div class="row mb-4">
    <div class="col-lg-3 col-md-6 mb-3">
        <div class="card text-center">
            <div class="card-body">
                <i class="fas fa-calendar fa-2x text-primary mb-2"></i>
                <h3 class="mb-0"><?php echo number_format($stats['total_annees']); ?></h3>
                <small class="text-muted">Années totales</small>
            </div>
        </div>
    </div>
    <div class="col-lg-3 col-md-6 mb-3">
        <div class="card text-center">
            <div class="card-body">
                <i class="fas fa-calendar-check fa-2x text-success mb-2"></i>
                <h3 class="mb-0"><?php echo number_format($stats['annee_active']); ?></h3>
                <small class="text-muted">Année active</small>
            </div>
        </div>
    </div>
    <div class="col-lg-3 col-md-6 mb-3">
        <div class="card text-center">
            <div class="card-body">
                <i class="fas fa-users fa-2x text-info mb-2"></i>
                <h3 class="mb-0"><?php echo number_format($stats['eleves_inscrits']); ?></h3>
                <small class="text-muted">Élèves inscrits</small>
            </div>
        </div>
    </div>
    <div class="col-lg-3 col-md-6 mb-3">
        <div class="card text-center">
            <div class="card-body">
                <i class="fas fa-school fa-2x text-warning mb-2"></i>
                <h3 class="mb-0"><?php echo number_format($stats['classes_actives']); ?></h3>
                <small class="text-muted">Classes actives</small>
            </div>
        </div>
    </div>
</div>

<?php if (!$current_year): ?>
<div class="alert alert-warning">
    <h5><i class="fas fa-exclamation-triangle me-2"></i>Aucune année scolaire active</h5>
    <p>Il n'y a actuellement aucune année scolaire active. Vous devez activer une année pour que le système fonctionne correctement.</p>
    <?php if (!empty($annees_scolaires)): ?>
        <p>Vous pouvez activer une année existante ou créer une nouvelle année scolaire.</p>
    <?php endif; ?>
</div>
<?php endif; ?>

<!-- Année scolaire active -->
<?php if ($current_year): ?>
<div class="row mb-4">
    <div class="col-12">
        <div class="card border-success">
            <div class="card-header bg-success text-white">
                <h5 class="mb-0">
                    <i class="fas fa-star me-2"></i>
                    Année scolaire active
                </h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-8">
                        <h4><?php echo htmlspecialchars($current_year['annee']); ?></h4>
                        <p class="mb-2">
                            <strong>Période :</strong> 
                            <?php echo formatDate($current_year['date_debut']); ?> - 
                            <?php echo formatDate($current_year['date_fin']); ?>
                        </p>
                        <p class="mb-0">
                            <strong>Durée :</strong> 
                            <?php 
                            $debut = new DateTime($current_year['date_debut']);
                            $fin = new DateTime($current_year['date_fin']);
                            $duree = $debut->diff($fin);
                            echo $duree->days . ' jours (' . round($duree->days / 30) . ' mois environ)';
                            ?>
                        </p>
                    </div>
                    <div class="col-md-4 text-end">
                        <div class="mb-2">
                            <span class="badge bg-success fs-6">ACTIVE</span>
                        </div>
                        <div class="btn-group">
                            <a href="edit.php?id=<?php echo $current_year['id']; ?>" class="btn btn-outline-primary btn-sm">
                                <i class="fas fa-edit me-1"></i>Modifier
                            </a>
                            <button type="button" class="btn btn-outline-warning btn-sm" 
                                    onclick="closeYear(<?php echo $current_year['id']; ?>, '<?php echo htmlspecialchars($current_year['annee']); ?>')">
                                <i class="fas fa-lock me-1"></i>Fermer
                            </button>
                        </div>
                    </div>
                </div>
                
                <hr>
                
                <div class="row text-center">
                    <div class="col-md-3">
                        <div class="border-end">
                            <h5 class="text-primary"><?php echo number_format($stats['classes_actives']); ?></h5>
                            <small class="text-muted">Classes</small>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="border-end">
                            <h5 class="text-success"><?php echo number_format($stats['eleves_inscrits']); ?></h5>
                            <small class="text-muted">Élèves</small>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="border-end">
                            <h5 class="text-info"><?php echo number_format($stats['emplois_generes']); ?></h5>
                            <small class="text-muted">Emplois du temps</small>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <h5 class="text-warning">
                            <?php 
                            $progress = $stats['classes_actives'] > 0 ? round(($stats['emplois_generes'] / $stats['classes_actives']) * 100) : 0;
                            echo $progress . '%';
                            ?>
                        </h5>
                        <small class="text-muted">Couverture emplois</small>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Liste des années scolaires -->
<div class="card">
    <div class="card-header">
        <h5 class="mb-0">
            <i class="fas fa-list me-2"></i>
            Toutes les années scolaires
        </h5>
    </div>
    <div class="card-body">
        <?php if (!empty($annees_scolaires)): ?>
            <div class="table-responsive">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Année</th>
                            <th>Période</th>
                            <th>Statut</th>
                            <th>Classes</th>
                            <th>Élèves</th>
                            <th>Créée le</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($annees_scolaires as $annee): ?>
                            <?php
                            // Statistiques pour cette année
                            $classes_count = $database->query(
                                "SELECT COUNT(*) as total FROM classes WHERE annee_scolaire_id = ?",
                                [$annee['id']]
                            )->fetch()['total'];
                            
                            $eleves_count = $database->query(
                                "SELECT COUNT(*) as total FROM inscriptions WHERE annee_scolaire_id = ? AND status = 'inscrit'",
                                [$annee['id']]
                            )->fetch()['total'];
                            ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($annee['annee']); ?></strong>
                                </td>
                                <td>
                                    <?php echo formatDate($annee['date_debut']); ?> - 
                                    <?php echo formatDate($annee['date_fin']); ?>
                                </td>
                                <td>
                                    <?php if ($annee['status'] === 'active'): ?>
                                        <span class="badge bg-success">Active</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Fermée</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge bg-primary"><?php echo $classes_count; ?></span>
                                </td>
                                <td>
                                    <span class="badge bg-info"><?php echo $eleves_count; ?></span>
                                </td>
                                <td>
                                    <?php echo formatDateTime($annee['created_at']); ?>
                                </td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <a href="edit.php?id=<?php echo $annee['id']; ?>" 
                                           class="btn btn-outline-primary" title="Modifier">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        
                                        <?php if ($annee['status'] !== 'active'): ?>
                                            <button type="button" class="btn btn-outline-success" 
                                                    onclick="activateYear(<?php echo $annee['id']; ?>, '<?php echo htmlspecialchars($annee['annee']); ?>')"
                                                    title="Activer">
                                                <i class="fas fa-play"></i>
                                            </button>
                                        <?php else: ?>
                                            <button type="button" class="btn btn-outline-warning" 
                                                    onclick="closeYear(<?php echo $annee['id']; ?>, '<?php echo htmlspecialchars($annee['annee']); ?>')"
                                                    title="Fermer">
                                                <i class="fas fa-pause"></i>
                                            </button>
                                        <?php endif; ?>
                                        
                                        <?php if ($classes_count == 0 && $eleves_count == 0): ?>
                                            <button type="button" class="btn btn-outline-danger" 
                                                    onclick="deleteYear(<?php echo $annee['id']; ?>, '<?php echo htmlspecialchars($annee['annee']); ?>')"
                                                    title="Supprimer">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        <?php else: ?>
                                            <button type="button" class="btn btn-outline-secondary" disabled
                                                    title="Impossible de supprimer (contient des données)">
                                                <i class="fas fa-lock"></i>
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="text-center py-4">
                <i class="fas fa-calendar fa-3x text-muted mb-3"></i>
                <h5>Aucune année scolaire</h5>
                <p class="text-muted">Commencez par créer votre première année scolaire.</p>
                <a href="add.php" class="btn btn-primary">
                    <i class="fas fa-plus me-1"></i>
                    Créer une année scolaire
                </a>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Formulaires cachés pour les actions -->
<form id="activateForm" method="POST" style="display: none;">
    <input type="hidden" name="activate_year" value="1">
    <input type="hidden" name="year_id" id="activateYearId">
</form>

<form id="closeForm" method="POST" style="display: none;">
    <input type="hidden" name="close_year" value="1">
    <input type="hidden" name="year_id" id="closeYearId">
</form>

<form id="deleteForm" method="POST" style="display: none;">
    <input type="hidden" name="delete_year" value="1">
    <input type="hidden" name="year_id" id="deleteYearId">
</form>

<script>
function activateYear(yearId, yearName) {
    if (confirm('Êtes-vous sûr de vouloir activer l\'année scolaire "' + yearName + '" ?\n\nCela désactivera automatiquement l\'année actuellement active.')) {
        document.getElementById('activateYearId').value = yearId;
        document.getElementById('activateForm').submit();
    }
}

function closeYear(yearId, yearName) {
    if (confirm('Êtes-vous sûr de vouloir fermer l\'année scolaire "' + yearName + '" ?\n\nUne fois fermée, vous ne pourrez plus y ajouter de nouvelles données.')) {
        document.getElementById('closeYearId').value = yearId;
        document.getElementById('closeForm').submit();
    }
}

function deleteYear(yearId, yearName) {
    if (confirm('Êtes-vous sûr de vouloir supprimer définitivement l\'année scolaire "' + yearName + '" ?\n\nCette action est irréversible !')) {
        document.getElementById('deleteYearId').value = yearId;
        document.getElementById('deleteForm').submit();
    }
}
</script>

<?php include '../../../includes/footer.php'; ?>
