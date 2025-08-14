<?php
/**
 * Module Recouvrement - Détails d'une campagne
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

$page_title = 'Détails de la campagne';

// Obtenir l'ID de la campagne
$campaign_id = (int)($_GET['id'] ?? 0);
if (!$campaign_id) {
    showMessage('error', 'ID de campagne manquant.');
    redirectTo('index.php');
}

// Obtenir l'année scolaire actuelle
$current_year = getCurrentAcademicYear();

// Récupérer les détails de la campagne
$campaign = $database->query(
    "SELECT 
        cr.*,
        u.nom as created_by_name,
        u.prenom as created_by_firstname
     FROM campagnes_recouvrement cr
     LEFT JOIN users u ON cr.created_by = u.id
     WHERE cr.id = ? AND cr.annee_scolaire_id = ?",
    [$campaign_id, $current_year['id']]
)->fetch();

if (!$campaign) {
    showMessage('error', 'Campagne non trouvée.');
    redirectTo('index.php');
}

// Traitement des actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = sanitizeInput($_POST['action'] ?? '');
    
    if ($action === 'update_target_status') {
        $target_id = (int)($_POST['target_id'] ?? 0);
        $new_status = sanitizeInput($_POST['new_status']);
        $commentaire = sanitizeInput($_POST['commentaire'] ?? '');
        $montant_recouvre = (float)($_POST['montant_recouvre'] ?? 0);
        
        try {
            $database->query(
                "UPDATE campagnes_cibles_dettes SET 
                    status = ?, 
                    commentaire = ?, 
                    montant_recouvre = ?,
                    date_contact = CASE WHEN ? = 'contacte' THEN CURDATE() ELSE date_contact END,
                    updated_at = NOW() 
                 WHERE id = ? AND campagne_id = ?",
                [$new_status, $commentaire, $montant_recouvre, $new_status, $target_id, $campaign_id]
            );
            
            showMessage('success', 'Statut mis à jour avec succès.');
            redirectTo("details.php?id=$campaign_id");
            
        } catch (Exception $e) {
            showMessage('error', 'Erreur lors de la mise à jour : ' . $e->getMessage());
        }
    }
}

// Statistiques de la campagne
$campaign_stats = $database->query(
    "SELECT 
        COUNT(DISTINCT ccd.eleve_id) as total_cibles,
        COUNT(DISTINCT CASE WHEN ccd.status = 'contacte' THEN ccd.eleve_id END) as contactes,
        COUNT(DISTINCT CASE WHEN ccd.status = 'paye' THEN ccd.eleve_id END) as payes,
        COUNT(DISTINCT CASE WHEN ccd.status = 'refuse' THEN ccd.eleve_id END) as refuses,
        COUNT(DISTINCT CASE WHEN ccd.status = 'injoignable' THEN ccd.eleve_id END) as injoignables,
        SUM(ccd.montant_dette) as total_dettes,
        SUM(ccd.montant_recouvre) as total_recouvre,
        ROUND((SUM(ccd.montant_recouvre) * 100.0 / SUM(ccd.montant_dette)), 1) as taux_recouvrement
     FROM campagnes_cibles_dettes ccd
     WHERE ccd.campagne_id = ?",
    [$campaign_id]
)->fetch();

// Cibles de la campagne
$targets = $database->query(
    "SELECT 
        ccd.*,
        e.nom,
        e.prenom,
        e.telephone,
        e.email,
        c.nom as classe_nom,
        c.niveau
     FROM campagnes_cibles_dettes ccd
     JOIN eleves e ON ccd.eleve_id = e.id
     JOIN inscriptions i ON e.id = i.eleve_id
     JOIN classes c ON i.classe_id = c.id
     WHERE ccd.campagne_id = ?
     ORDER BY ccd.montant_dette DESC",
    [$campaign_id]
)->fetchAll();

// Historique des actions
$actions = $database->query(
    "SELECT 
        'contact' as type,
        ccd.date_contact as date_action,
        ccd.methode_contact as action_detail,
        ccd.commentaire,
        CONCAT(e.nom, ' ', e.prenom) as eleve_nom
     FROM campagnes_cibles_dettes ccd
     JOIN eleves e ON ccd.eleve_id = e.id
     WHERE ccd.campagne_id = ? AND ccd.date_contact IS NOT NULL
     UNION ALL
     SELECT 
        'payment' as type,
        ccd.updated_at as date_action,
        CONCAT('Paiement: ', ccd.montant_recouvre, ' FC') as action_detail,
        ccd.commentaire,
        CONCAT(e.nom, ' ', e.prenom) as eleve_nom
     FROM campagnes_cibles_dettes ccd
     JOIN eleves e ON ccd.eleve_id = e.id
     WHERE ccd.campagne_id = ? AND ccd.status = 'paye'
     ORDER BY date_action DESC
     LIMIT 50",
    [$campaign_id, $campaign_id]
)->fetchAll();

include '../../../includes/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">
        <i class="fas fa-bullhorn me-2"></i>
        Détails de la campagne
    </h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <div class="btn-group me-2">
            <a href="index.php" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-1"></i>
                Retour
            </a>
        </div>
        <div class="btn-group">
            <a href="edit.php?id=<?php echo $campaign_id; ?>" class="btn btn-warning">
                <i class="fas fa-edit me-1"></i>
                Modifier
            </a>
        </div>
    </div>
</div>

<!-- Informations de la campagne -->
<div class="row mb-4">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-info-circle me-2"></i>
                    Informations générales
                </h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <p><strong>Nom :</strong> <?php echo htmlspecialchars($campaign['nom']); ?></p>
                        <p><strong>Description :</strong> <?php echo htmlspecialchars($campaign['description']); ?></p>
                        <p><strong>Type de cible :</strong> 
                            <?php 
                            $type_labels = [
                                'tous' => '<span class="badge bg-primary">Tous</span>',
                                'retard' => '<span class="badge bg-warning">Retard</span>',
                                'montant' => '<span class="badge bg-info">Montant</span>',
                                'niveau' => '<span class="badge bg-secondary">Niveau</span>'
                            ];
                            echo $type_labels[$campaign['type_cible']] ?? $campaign['type_cible'];
                            ?>
                        </p>
                        <p><strong>Stratégie :</strong> <?php echo ucfirst(str_replace('_', ' ', $campaign['strategie'])); ?></p>
                    </div>
                    <div class="col-md-6">
                        <p><strong>Période :</strong> <?php echo date('d/m/Y', strtotime($campaign['date_debut'])); ?> - <?php echo date('d/m/Y', strtotime($campaign['date_fin'])); ?></p>
                        <p><strong>Budget :</strong> <?php echo number_format($campaign['budget']); ?> FC</p>
                        <p><strong>Statut :</strong> 
                            <?php 
                            $status_labels = [
                                'active' => '<span class="badge bg-success">Active</span>',
                                'paused' => '<span class="badge bg-warning">En pause</span>',
                                'completed' => '<span class="badge bg-info">Terminée</span>',
                                'cancelled' => '<span class="badge bg-danger">Annulée</span>'
                            ];
                            echo $status_labels[$campaign['status']] ?? $campaign['status'];
                            ?>
                        </p>
                        <p><strong>Créée par :</strong> <?php echo htmlspecialchars($campaign['created_by_name'] . ' ' . $campaign['created_by_firstname']); ?></p>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-4">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-chart-pie me-2"></i>
                    Statistiques
                </h5>
            </div>
            <div class="card-body">
                <div class="row text-center">
                    <div class="col-6 mb-3">
                        <h4 class="text-primary"><?php echo $campaign_stats['total_cibles']; ?></h4>
                        <small>Total cibles</small>
                    </div>
                    <div class="col-6 mb-3">
                        <h4 class="text-success"><?php echo $campaign_stats['payes']; ?></h4>
                        <small>Payés</small>
                    </div>
                    <div class="col-6 mb-3">
                        <h4 class="text-info"><?php echo $campaign_stats['contactes']; ?></h4>
                        <small>Contactés</small>
                    </div>
                    <div class="col-6 mb-3">
                        <h4 class="text-warning"><?php echo $campaign_stats['taux_recouvrement']; ?>%</h4>
                        <small>Taux recouvrement</small>
                    </div>
                </div>
                <hr>
                <div class="row text-center">
                    <div class="col-6">
                        <strong><?php echo number_format($campaign_stats['total_dettes']); ?> FC</strong>
                        <br><small>Total dettes</small>
                    </div>
                    <div class="col-6">
                        <strong><?php echo number_format($campaign_stats['total_recouvre']); ?> FC</strong>
                        <br><small>Total recouvré</small>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Cibles de la campagne -->
<div class="card mb-4">
    <div class="card-header">
        <h5 class="mb-0">
            <i class="fas fa-users me-2"></i>
            Cibles de la campagne (<?php echo count($targets); ?>)
        </h5>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>Élève</th>
                        <th>Classe</th>
                        <th>Contact</th>
                        <th>Dette</th>
                        <th>Recouvré</th>
                        <th>Statut</th>
                        <th>Dernier contact</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($targets as $target): ?>
                        <tr>
                            <td>
                                <strong><?php echo htmlspecialchars($target['nom'] . ' ' . $target['prenom']); ?></strong>
                            </td>
                            <td>
                                <?php echo htmlspecialchars($target['classe_nom']); ?>
                                <span class="badge bg-info"><?php echo ucfirst($target['niveau']); ?></span>
                            </td>
                            <td>
                                <?php if ($target['telephone']): ?>
                                    <i class="fas fa-phone"></i> <?php echo htmlspecialchars($target['telephone']); ?><br>
                                <?php endif; ?>
                                <?php if ($target['email']): ?>
                                    <i class="fas fa-envelope"></i> <?php echo htmlspecialchars($target['email']); ?>
                                <?php endif; ?>
                            </td>
                            <td>
                                <strong><?php echo number_format($target['montant_dette']); ?> FC</strong>
                            </td>
                            <td>
                                <strong class="text-success"><?php echo number_format($target['montant_recouvre']); ?> FC</strong>
                            </td>
                            <td>
                                <?php 
                                $status_labels = [
                                    'pending' => '<span class="badge bg-secondary">En attente</span>',
                                    'contacte' => '<span class="badge bg-info">Contacté</span>',
                                    'paye' => '<span class="badge bg-success">Payé</span>',
                                    'refuse' => '<span class="badge bg-danger">Refusé</span>',
                                    'injoignable' => '<span class="badge bg-warning">Injoignable</span>'
                                ];
                                echo $status_labels[$target['status']] ?? $target['status'];
                                ?>
                            </td>
                            <td>
                                <?php echo $target['date_contact'] ? date('d/m/Y', strtotime($target['date_contact'])) : '-'; ?>
                                <?php if ($target['methode_contact']): ?>
                                    <br><small class="text-muted"><?php echo ucfirst($target['methode_contact']); ?></small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <button type="button" class="btn btn-sm btn-outline-primary" 
                                        onclick="showUpdateModal(<?php echo $target['id']; ?>, '<?php echo $target['status']; ?>', <?php echo $target['montant_recouvre']; ?>, '<?php echo htmlspecialchars($target['commentaire']); ?>')">
                                    <i class="fas fa-edit"></i>
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Historique des actions -->
<?php if (!empty($actions)): ?>
<div class="card">
    <div class="card-header">
        <h5 class="mb-0">
            <i class="fas fa-history me-2"></i>
            Historique des actions
        </h5>
    </div>
    <div class="card-body">
        <div class="timeline">
            <?php foreach ($actions as $action): ?>
                <div class="timeline-item">
                    <div class="timeline-marker <?php echo $action['type'] === 'contact' ? 'bg-info' : 'bg-success'; ?>"></div>
                    <div class="timeline-content">
                        <div class="d-flex justify-content-between">
                            <h6 class="mb-1"><?php echo htmlspecialchars($action['eleve_nom']); ?></h6>
                            <small class="text-muted"><?php echo date('d/m/Y H:i', strtotime($action['date_action'])); ?></small>
                        </div>
                        <p class="mb-1"><?php echo htmlspecialchars($action['action_detail']); ?></p>
                        <?php if ($action['commentaire']): ?>
                            <small class="text-muted"><?php echo htmlspecialchars($action['commentaire']); ?></small>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Modal Mise à jour statut -->
<div class="modal fade" id="updateStatusModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Mettre à jour le statut</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="update_target_status">
                <input type="hidden" name="target_id" id="target_id">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="new_status" class="form-label">Nouveau statut</label>
                        <select class="form-select" id="new_status" name="new_status" required>
                            <option value="pending">En attente</option>
                            <option value="contacte">Contacté</option>
                            <option value="paye">Payé</option>
                            <option value="refuse">Refusé</option>
                            <option value="injoignable">Injoignable</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="montant_recouvre" class="form-label">Montant recouvré (FC)</label>
                        <input type="number" class="form-control" id="montant_recouvre" name="montant_recouvre" min="0" step="1000">
                    </div>
                    <div class="mb-3">
                        <label for="commentaire" class="form-label">Commentaire</label>
                        <textarea class="form-control" id="commentaire" name="commentaire" rows="3"></textarea>
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

<style>
.timeline {
    position: relative;
    padding-left: 30px;
}

.timeline-item {
    position: relative;
    margin-bottom: 20px;
}

.timeline-marker {
    position: absolute;
    left: -35px;
    top: 5px;
    width: 12px;
    height: 12px;
    border-radius: 50%;
}

.timeline-content {
    background: #f8f9fa;
    padding: 15px;
    border-radius: 5px;
    border-left: 3px solid #007bff;
}
</style>

<script>
function showUpdateModal(targetId, currentStatus, montantRecouvre, commentaire) {
    document.getElementById('target_id').value = targetId;
    document.getElementById('new_status').value = currentStatus;
    document.getElementById('montant_recouvre').value = montantRecouvre;
    document.getElementById('commentaire').value = commentaire;
    new bootstrap.Modal(document.getElementById('updateStatusModal')).show();
}

// Mettre à jour le montant recouvré quand le statut change
document.getElementById('new_status').addEventListener('change', function() {
    const montantField = document.getElementById('montant_recouvre');
    if (this.value === 'paye') {
        montantField.required = true;
    } else {
        montantField.required = false;
    }
});
</script>

<?php include '../../../includes/footer.php'; ?>
