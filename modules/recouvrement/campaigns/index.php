<?php
/**
 * Module Recouvrement - Campagnes de recouvrement
 * Application de gestion scolaire - République Démocratique du Congo
 */

require_once '../../../config/config.php';
require_once '../../../config/database.php';
require_once '../../../includes/functions.php';

// Vérifier l'authentification et les permissions
requireLogin();
if (!checkPermission('recouvrement') && !checkPermission('recouvrement_view')) {
    showMessage('error', 'Accès refusé à ce module.');
    redirectTo('../../dashboard.php');
}

$page_title = 'Campagnes de recouvrement';

// Obtenir l'année scolaire actuelle
$current_year = getCurrentAcademicYear();

// Traitement des actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = sanitizeInput($_POST['action'] ?? '');
    
    if ($action === 'create_campaign') {
        try {
            $nom = sanitizeInput($_POST['nom']);
            $description = sanitizeInput($_POST['description']);
            $type_cible = sanitizeInput($_POST['type_cible']);
            $montant_min = (float)($_POST['montant_min'] ?? 0);
            $montant_max = (float)($_POST['montant_max'] ?? 0);
            $date_debut = sanitizeInput($_POST['date_debut']);
            $date_fin = sanitizeInput($_POST['date_fin']);
            $strategie = sanitizeInput($_POST['strategie']);
            $budget = (float)($_POST['budget'] ?? 0);
            
            $database->query(
                "INSERT INTO campagnes_recouvrement (
                    nom, description, type_cible, montant_min, montant_max,
                    date_debut, date_fin, strategie, budget, annee_scolaire_id,
                    status, created_by, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active', ?, NOW())",
                [
                    $nom, $description, $type_cible, $montant_min, $montant_max,
                    $date_debut, $date_fin, $strategie, $budget, $current_year['id'],
                    $_SESSION['user_id']
                ]
            );
            
            showMessage('success', 'Campagne créée avec succès.');
            redirectTo('index.php');
            
        } catch (Exception $e) {
            showMessage('error', 'Erreur lors de la création de la campagne : ' . $e->getMessage());
        }
    }
    
    if ($action === 'update_status') {
        $campaign_id = (int)($_POST['campaign_id'] ?? 0);
        $new_status = sanitizeInput($_POST['new_status']);
        
        try {
            $database->query(
                "UPDATE campagnes_recouvrement SET status = ?, updated_at = NOW() WHERE id = ?",
                [$new_status, $campaign_id]
            );
            
            showMessage('success', 'Statut de la campagne mis à jour.');
            redirectTo('index.php');
            
        } catch (Exception $e) {
            showMessage('error', 'Erreur lors de la mise à jour : ' . $e->getMessage());
        }
    }
}

// Récupérer les campagnes
$campaigns = $database->query(
    "SELECT 
        cr.*,
        COUNT(DISTINCT ccd.eleve_id) as nombre_cibles,
        SUM(ccd.montant_dette) as total_dettes_cible,
        COUNT(DISTINCT CASE WHEN ccd.status = 'contacte' THEN ccd.eleve_id END) as contactes,
        COUNT(DISTINCT CASE WHEN ccd.status = 'paye' THEN ccd.eleve_id END) as payes,
        SUM(CASE WHEN ccd.status = 'paye' THEN ccd.montant_recouvre ELSE 0 END) as total_recouvre
     FROM campagnes_recouvrement cr
     LEFT JOIN campagnes_cibles_dettes ccd ON cr.id = ccd.campagne_id
     WHERE cr.annee_scolaire_id = ?
     GROUP BY cr.id
     ORDER BY cr.created_at DESC",
    [$current_year['id']]
)->fetchAll();

// Statistiques des campagnes
$campaign_stats = $database->query(
    "SELECT 
        COUNT(*) as total_campaigns,
        COUNT(CASE WHEN status = 'active' THEN 1 END) as active_campaigns,
        COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed_campaigns,
        COUNT(CASE WHEN status = 'paused' THEN 1 END) as paused_campaigns,
        SUM(budget) as total_budget,
        AVG(DATEDIFF(date_fin, date_debut)) as avg_duration_days
     FROM campagnes_recouvrement 
     WHERE annee_scolaire_id = ?",
    [$current_year['id']]
)->fetch();

// Performance des campagnes
$campaign_performance = $database->query(
    "SELECT 
        cr.nom,
        COUNT(DISTINCT ccd.eleve_id) as total_cibles,
        COUNT(DISTINCT CASE WHEN ccd.status = 'paye' THEN ccd.eleve_id END) as payes,
        SUM(CASE WHEN ccd.status = 'paye' THEN ccd.montant_recouvre ELSE 0 END) as montant_recouvre,
        ROUND(
            (COUNT(DISTINCT CASE WHEN ccd.status = 'paye' THEN ccd.eleve_id END) * 100.0 / 
             COUNT(DISTINCT ccd.eleve_id)), 1
        ) as taux_reussite
     FROM campagnes_recouvrement cr
     LEFT JOIN campagnes_cibles_dettes ccd ON cr.id = ccd.campagne_id
     WHERE cr.annee_scolaire_id = ? AND ccd.eleve_id IS NOT NULL
     GROUP BY cr.id, cr.nom
     ORDER BY taux_reussite DESC",
    [$current_year['id']]
)->fetchAll();

include '../../../includes/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">
        <i class="fas fa-bullhorn me-2"></i>
        Campagnes de recouvrement
    </h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <div class="btn-group me-2">
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createCampaignModal">
                <i class="fas fa-plus me-1"></i>
                Nouvelle campagne
            </button>
        </div>
        <div class="btn-group">
            <a href="../reports/campaigns.php" class="btn btn-outline-info">
                <i class="fas fa-chart-bar me-1"></i>
                Rapports
            </a>
        </div>
    </div>
</div>

<!-- Statistiques des campagnes -->
<div class="row mb-4">
    <div class="col-md-2">
        <div class="card text-center">
            <div class="card-body">
                <h4 class="text-primary"><?php echo $campaign_stats['total_campaigns']; ?></h4>
                <p class="card-text">Total campagnes</p>
            </div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card text-center">
            <div class="card-body">
                <h4 class="text-success"><?php echo $campaign_stats['active_campaigns']; ?></h4>
                <p class="card-text">Actives</p>
            </div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card text-center">
            <div class="card-body">
                <h4 class="text-info"><?php echo $campaign_stats['completed_campaigns']; ?></h4>
                <p class="card-text">Terminées</p>
            </div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card text-center">
            <div class="card-body">
                <h4 class="text-warning"><?php echo $campaign_stats['paused_campaigns']; ?></h4>
                <p class="card-text">En pause</p>
            </div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card text-center">
            <div class="card-body">
                <h4 class="text-danger"><?php echo number_format($campaign_stats['total_budget']); ?></h4>
                <p class="card-text">Budget total (FC)</p>
            </div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card text-center">
            <div class="card-body">
                <h4 class="text-secondary"><?php echo round($campaign_stats['avg_duration_days']); ?></h4>
                <p class="card-text">Durée moyenne (jours)</p>
            </div>
        </div>
    </div>
</div>

<!-- Performance des campagnes -->
<?php if (!empty($campaign_performance)): ?>
<div class="card mb-4">
    <div class="card-header">
        <h5 class="mb-0">
            <i class="fas fa-chart-line me-2"></i>
            Performance des campagnes
        </h5>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>Campagne</th>
                        <th>Total cibles</th>
                        <th>Payés</th>
                        <th>Montant recouvré</th>
                        <th>Taux de réussite</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($campaign_performance as $perf): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($perf['nom']); ?></strong></td>
                            <td><span class="badge bg-primary"><?php echo $perf['total_cibles']; ?></span></td>
                            <td><span class="badge bg-success"><?php echo $perf['payes']; ?></span></td>
                            <td><strong><?php echo number_format($perf['montant_recouvre']); ?> FC</strong></td>
                            <td>
                                <div class="progress" style="height: 20px;">
                                    <div class="progress-bar bg-success" role="progressbar" 
                                         style="width: <?php echo $perf['taux_reussite']; ?>%"
                                         aria-valuenow="<?php echo $perf['taux_reussite']; ?>" 
                                         aria-valuemin="0" aria-valuemax="100">
                                        <?php echo $perf['taux_reussite']; ?>%
                                    </div>
                                </div>
                            </td>
                            <td>
                                <a href="details.php?id=<?php echo $perf['id']; ?>" class="btn btn-sm btn-outline-primary">
                                    <i class="fas fa-eye"></i>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Liste des campagnes -->
<div class="card">
    <div class="card-header">
        <h5 class="mb-0">
            <i class="fas fa-list me-2"></i>
            Toutes les campagnes
        </h5>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>Nom</th>
                        <th>Type cible</th>
                        <th>Période</th>
                        <th>Budget</th>
                        <th>Cibles</th>
                        <th>Contactés</th>
                        <th>Payés</th>
                        <th>Montant recouvré</th>
                        <th>Statut</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($campaigns as $campaign): ?>
                        <tr>
                            <td>
                                <strong><?php echo htmlspecialchars($campaign['nom']); ?></strong><br>
                                <small class="text-muted"><?php echo htmlspecialchars($campaign['description']); ?></small>
                            </td>
                            <td>
                                <?php 
                                $type_labels = [
                                    'tous' => '<span class="badge bg-primary">Tous</span>',
                                    'retard' => '<span class="badge bg-warning">Retard</span>',
                                    'montant' => '<span class="badge bg-info">Montant</span>',
                                    'niveau' => '<span class="badge bg-secondary">Niveau</span>'
                                ];
                                echo $type_labels[$campaign['type_cible']] ?? $campaign['type_cible'];
                                ?>
                            </td>
                            <td>
                                <?php echo date('d/m/Y', strtotime($campaign['date_debut'])); ?> - 
                                <?php echo date('d/m/Y', strtotime($campaign['date_fin'])); ?>
                            </td>
                            <td><strong><?php echo number_format($campaign['budget']); ?> FC</strong></td>
                            <td><span class="badge bg-primary"><?php echo $campaign['nombre_cibles']; ?></span></td>
                            <td><span class="badge bg-info"><?php echo $campaign['contactes']; ?></span></td>
                            <td><span class="badge bg-success"><?php echo $campaign['payes']; ?></span></td>
                            <td><strong><?php echo number_format($campaign['total_recouvre']); ?> FC</strong></td>
                            <td>
                                <?php 
                                $status_labels = [
                                    'active' => '<span class="badge bg-success">Active</span>',
                                    'paused' => '<span class="badge bg-warning">En pause</span>',
                                    'completed' => '<span class="badge bg-info">Terminée</span>',
                                    'cancelled' => '<span class="badge bg-danger">Annulée</span>'
                                ];
                                echo $status_labels[$campaign['status']] ?? $campaign['status'];
                                ?>
                            </td>
                            <td>
                                <div class="btn-group btn-group-sm">
                                    <a href="details.php?id=<?php echo $campaign['id']; ?>" class="btn btn-outline-primary" title="Voir détails">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <a href="edit.php?id=<?php echo $campaign['id']; ?>" class="btn btn-outline-warning" title="Modifier">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <button type="button" class="btn btn-outline-info" onclick="showStatusModal(<?php echo $campaign['id']; ?>, '<?php echo $campaign['status']; ?>')" title="Changer statut">
                                        <i class="fas fa-cog"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal Création de campagne -->
<div class="modal fade" id="createCampaignModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Nouvelle campagne de recouvrement</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="create_campaign">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="nom" class="form-label">Nom de la campagne *</label>
                                <input type="text" class="form-control" id="nom" name="nom" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="type_cible" class="form-label">Type de cible *</label>
                                <select class="form-select" id="type_cible" name="type_cible" required>
                                    <option value="">Sélectionner</option>
                                    <option value="tous">Tous les débiteurs</option>
                                    <option value="retard">Retard de paiement</option>
                                    <option value="montant">Montant spécifique</option>
                                    <option value="niveau">Par niveau</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="description" class="form-label">Description</label>
                        <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="date_debut" class="form-label">Date de début *</label>
                                <input type="date" class="form-control" id="date_debut" name="date_debut" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="date_fin" class="form-label">Date de fin *</label>
                                <input type="date" class="form-control" id="date_fin" name="date_fin" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="montant_min" class="form-label">Montant minimum (FC)</label>
                                <input type="number" class="form-control" id="montant_min" name="montant_min" min="0">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="montant_max" class="form-label">Montant maximum (FC)</label>
                                <input type="number" class="form-control" id="montant_max" name="montant_max" min="0">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="budget" class="form-label">Budget campagne (FC)</label>
                                <input type="number" class="form-control" id="budget" name="budget" min="0">
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="strategie" class="form-label">Stratégie de recouvrement</label>
                        <select class="form-select" id="strategie" name="strategie">
                            <option value="appel_telephonique">Appels téléphoniques</option>
                            <option value="sms">SMS</option>
                            <option value="email">Email</option>
                            <option value="visite_domicile">Visite à domicile</option>
                            <option value="lettre">Lettre de rappel</option>
                            <option value="mixte">Stratégie mixte</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-primary">Créer la campagne</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Changement de statut -->
<div class="modal fade" id="statusModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Changer le statut de la campagne</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="update_status">
                <input type="hidden" name="campaign_id" id="campaign_id">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="new_status" class="form-label">Nouveau statut</label>
                        <select class="form-select" id="new_status" name="new_status" required>
                            <option value="active">Active</option>
                            <option value="paused">En pause</option>
                            <option value="completed">Terminée</option>
                            <option value="cancelled">Annulée</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-primary">Mettre à jour</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function showStatusModal(campaignId, currentStatus) {
    document.getElementById('campaign_id').value = campaignId;
    document.getElementById('new_status').value = currentStatus;
    new bootstrap.Modal(document.getElementById('statusModal')).show();
}

// Validation des dates
document.getElementById('date_fin').addEventListener('change', function() {
    const dateDebut = document.getElementById('date_debut').value;
    const dateFin = this.value;
    
    if (dateDebut && dateFin && dateFin <= dateDebut) {
        alert('La date de fin doit être postérieure à la date de début.');
        this.value = '';
    }
});
</script>

<?php include '../../../includes/footer.php'; ?>
