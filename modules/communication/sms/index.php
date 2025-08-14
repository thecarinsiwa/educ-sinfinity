<?php
/**
 * Module Communication - Gestion des SMS
 * Application de gestion scolaire - République Démocratique du Congo
 */

require_once '../../../config/config.php';
require_once '../../../config/database.php';
require_once '../../../includes/functions.php';

// Vérifier l'authentification et les permissions
requireLogin();
if (!checkPermission('communication')) {
    showMessage('error', 'Accès refusé à cette page.');
    redirectTo('../index.php');
}

// Paramètres de filtrage
$status_filter = $_GET['status'] ?? '';
$type_filter = $_GET['type'] ?? '';
$date_filter = $_GET['date'] ?? '';

// Construction de la requête WHERE
$where_conditions = ['1=1'];
$params = [];

if ($status_filter) {
    $where_conditions[] = "status = ?";
    $params[] = $status_filter;
}

if ($type_filter) {
    $where_conditions[] = "type_sms = ?";
    $params[] = $type_filter;
}

if ($date_filter) {
    $where_conditions[] = "DATE(created_at) = ?";
    $params[] = $date_filter;
}

$where_clause = implode(' AND ', $where_conditions);

// Statistiques générales
try {
    $stats = $database->query(
        "SELECT 
            COUNT(*) as total_sms,
            COUNT(CASE WHEN status = 'envoye' THEN 1 END) as envoyes,
            COUNT(CASE WHEN status = 'echec' THEN 1 END) as echecs,
            COUNT(CASE WHEN status = 'en_attente' THEN 1 END) as en_attente,
            COUNT(CASE WHEN status = 'livre' THEN 1 END) as livres,
            SUM(CASE WHEN cout IS NOT NULL THEN cout ELSE 0 END) as cout_total,
            COUNT(CASE WHEN DATE(created_at) = CURDATE() THEN 1 END) as aujourd_hui
         FROM sms_logs"
    )->fetch();
} catch (Exception $e) {
    $stats = [
        'total_sms' => 0, 'envoyes' => 0, 'echecs' => 0, 
        'en_attente' => 0, 'livres' => 0, 'cout_total' => 0, 'aujourd_hui' => 0
    ];
}

// Liste des SMS avec filtres
try {
    $sms_list = $database->query(
        "SELECT sl.*, u.nom as expediteur_nom, u.prenom as expediteur_prenom
         FROM sms_logs sl
         LEFT JOIN users u ON sl.expediteur_id = u.id
         WHERE $where_clause
         ORDER BY sl.created_at DESC
         LIMIT 50",
        $params
    )->fetchAll();
} catch (Exception $e) {
    $sms_list = [];
    showMessage('error', 'Erreur lors du chargement : ' . $e->getMessage());
}

// Statistiques par type
try {
    $stats_type = $database->query(
        "SELECT type_sms, COUNT(*) as nombre, 
                COUNT(CASE WHEN status = 'envoye' THEN 1 END) as reussis
         FROM sms_logs 
         WHERE MONTH(created_at) = MONTH(CURDATE()) AND YEAR(created_at) = YEAR(CURDATE())
         GROUP BY type_sms
         ORDER BY nombre DESC"
    )->fetchAll();
} catch (Exception $e) {
    $stats_type = [];
}

$page_title = "Gestion des SMS";
include '../../../includes/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">
        <i class="fas fa-mobile-alt me-2"></i>
        Gestion des SMS
    </h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <div class="btn-group me-2">
            <a href="../" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-1"></i>
                Retour à la communication
            </a>
        </div>
        <div class="btn-group me-2">
            <a href="send.php" class="btn btn-primary">
                <i class="fas fa-paper-plane me-1"></i>
                Envoyer un SMS
            </a>
        </div>
    </div>
</div>

<!-- Statistiques rapides -->
<div class="row mb-4">
    <div class="col-md-2">
        <div class="card text-center shadow-sm stats-card">
            <div class="card-body">
                <i class="fas fa-mobile-alt fa-2x text-primary mb-2"></i>
                <h4><?php echo number_format($stats['total_sms'] ?? 0); ?></h4>
                <p class="text-muted mb-0">Total SMS</p>
            </div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card text-center shadow-sm stats-card">
            <div class="card-body">
                <i class="fas fa-check-circle fa-2x text-success mb-2"></i>
                <h4><?php echo number_format($stats['envoyes'] ?? 0); ?></h4>
                <p class="text-muted mb-0">Envoyés</p>
            </div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card text-center shadow-sm stats-card">
            <div class="card-body">
                <i class="fas fa-times-circle fa-2x text-danger mb-2"></i>
                <h4><?php echo number_format($stats['echecs'] ?? 0); ?></h4>
                <p class="text-muted mb-0">Échecs</p>
            </div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card text-center shadow-sm stats-card">
            <div class="card-body">
                <i class="fas fa-clock fa-2x text-warning mb-2"></i>
                <h4><?php echo number_format($stats['en_attente'] ?? 0); ?></h4>
                <p class="text-muted mb-0">En attente</p>
            </div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card text-center shadow-sm stats-card">
            <div class="card-body">
                <i class="fas fa-check-double fa-2x text-info mb-2"></i>
                <h4><?php echo number_format($stats['livres'] ?? 0); ?></h4>
                <p class="text-muted mb-0">Livrés</p>
            </div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card text-center shadow-sm stats-card">
            <div class="card-body">
                <i class="fas fa-coins fa-2x text-secondary mb-2"></i>
                <h4><?php echo number_format($stats['cout_total'] ?? 0); ?></h4>
                <p class="text-muted mb-0">Coût total (FC)</p>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <!-- Liste des SMS -->
    <div class="col-lg-8">
        <!-- Filtres -->
        <div class="card mb-4 shadow-sm">
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-3">
                        <label for="status" class="form-label">Statut</label>
                        <select class="form-select" id="status" name="status">
                            <option value="">Tous les statuts</option>
                            <option value="en_attente" <?php echo $status_filter === 'en_attente' ? 'selected' : ''; ?>>En attente</option>
                            <option value="envoye" <?php echo $status_filter === 'envoye' ? 'selected' : ''; ?>>Envoyé</option>
                            <option value="echec" <?php echo $status_filter === 'echec' ? 'selected' : ''; ?>>Échec</option>
                            <option value="livre" <?php echo $status_filter === 'livre' ? 'selected' : ''; ?>>Livré</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label for="type" class="form-label">Type</label>
                        <select class="form-select" id="type" name="type">
                            <option value="">Tous les types</option>
                            <option value="info" <?php echo $type_filter === 'info' ? 'selected' : ''; ?>>Information</option>
                            <option value="urgent" <?php echo $type_filter === 'urgent' ? 'selected' : ''; ?>>Urgent</option>
                            <option value="rappel" <?php echo $type_filter === 'rappel' ? 'selected' : ''; ?>>Rappel</option>
                            <option value="absence" <?php echo $type_filter === 'absence' ? 'selected' : ''; ?>>Absence</option>
                            <option value="retard" <?php echo $type_filter === 'retard' ? 'selected' : ''; ?>>Retard</option>
                            <option value="discipline" <?php echo $type_filter === 'discipline' ? 'selected' : ''; ?>>Discipline</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label for="date" class="form-label">Date</label>
                        <input type="date" class="form-control" id="date" name="date" value="<?php echo htmlspecialchars($date_filter); ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">&nbsp;</label>
                        <button type="submit" class="btn btn-primary d-block w-100">
                            <i class="fas fa-filter me-1"></i>
                            Filtrer
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Liste des SMS -->
        <div class="card shadow-sm">
            <div class="card-header bg-light">
                <h5 class="mb-0">
                    <i class="fas fa-list me-2"></i>
                    Historique des SMS (<?php echo count($sms_list); ?>)
                </h5>
            </div>
            <div class="card-body">
                <?php if (empty($sms_list)): ?>
                    <div class="text-center py-5">
                        <i class="fas fa-mobile-alt fa-3x text-muted mb-3"></i>
                        <h5 class="text-muted">Aucun SMS trouvé</h5>
                        <p class="text-muted">Aucun SMS ne correspond aux critères sélectionnés.</p>
                        <a href="send.php" class="btn btn-primary">
                            <i class="fas fa-paper-plane me-1"></i>
                            Envoyer votre premier SMS
                        </a>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead class="table-light">
                                <tr>
                                    <th>Date/Heure</th>
                                    <th>Destinataire</th>
                                    <th>Message</th>
                                    <th>Type</th>
                                    <th>Statut</th>
                                    <th>Coût</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($sms_list as $sms): ?>
                                    <tr>
                                        <td>
                                            <?php echo formatDate($sms['created_at']); ?>
                                            <?php if ($sms['date_envoi']): ?>
                                                <br><small class="text-muted">
                                                    Envoyé: <?php echo formatDate($sms['date_envoi']); ?>
                                                </small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($sms['destinataire_telephone']); ?></strong>
                                            <?php if ($sms['destinataire_nom']): ?>
                                                <br><small class="text-muted">
                                                    <?php echo htmlspecialchars($sms['destinataire_nom']); ?>
                                                </small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="text-truncate d-inline-block" style="max-width: 200px;" title="<?php echo htmlspecialchars($sms['message']); ?>">
                                                <?php echo htmlspecialchars(substr($sms['message'], 0, 50)); ?>
                                                <?php if (strlen($sms['message']) > 50): ?>...<?php endif; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php
                                            $type_colors = [
                                                'info' => 'primary',
                                                'urgent' => 'danger',
                                                'rappel' => 'warning',
                                                'absence' => 'info',
                                                'retard' => 'warning',
                                                'discipline' => 'danger'
                                            ];
                                            $color = $type_colors[$sms['type_sms']] ?? 'secondary';
                                            ?>
                                            <span class="badge bg-<?php echo $color; ?>">
                                                <?php echo ucfirst($sms['type_sms']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php
                                            $status_colors = [
                                                'en_attente' => 'warning',
                                                'envoye' => 'success',
                                                'echec' => 'danger',
                                                'livre' => 'info'
                                            ];
                                            $color = $status_colors[$sms['status']] ?? 'secondary';
                                            ?>
                                            <span class="badge bg-<?php echo $color; ?>">
                                                <?php echo ucfirst(str_replace('_', ' ', $sms['status'])); ?>
                                            </span>
                                            <?php if ($sms['tentatives'] > 1): ?>
                                                <br><small class="text-muted">
                                                    <?php echo $sms['tentatives']; ?> tentative(s)
                                                </small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($sms['cout']): ?>
                                                <?php echo number_format($sms['cout'], 0); ?> FC
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <button type="button" class="btn btn-sm btn-outline-primary" 
                                                    onclick="viewSmsDetails(<?php echo htmlspecialchars(json_encode($sms)); ?>)" 
                                                    title="Voir détails">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Statistiques par type -->
    <div class="col-lg-4">
        <div class="card shadow-sm">
            <div class="card-header bg-light">
                <h5 class="mb-0">
                    <i class="fas fa-chart-pie me-2"></i>
                    SMS par type (ce mois)
                </h5>
            </div>
            <div class="card-body">
                <?php if (empty($stats_type)): ?>
                    <div class="text-center py-4">
                        <i class="fas fa-chart-pie fa-2x text-muted mb-2"></i>
                        <p class="text-muted mb-0">Aucun SMS ce mois</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($stats_type as $stat): ?>
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <div>
                                <strong><?php echo ucfirst($stat['type_sms']); ?></strong>
                                <br><small class="text-muted">
                                    <?php echo $stat['reussis']; ?>/<?php echo $stat['nombre']; ?> réussis
                                </small>
                            </div>
                            <div class="text-end">
                                <span class="badge bg-primary"><?php echo $stat['nombre']; ?></span>
                                <br><small class="text-success">
                                    <?php echo $stat['nombre'] > 0 ? round(($stat['reussis'] / $stat['nombre']) * 100, 1) : 0; ?>%
                                </small>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Actions rapides -->
        <div class="card shadow-sm mt-4">
            <div class="card-header bg-light">
                <h5 class="mb-0">
                    <i class="fas fa-bolt me-2"></i>
                    Actions rapides
                </h5>
            </div>
            <div class="card-body">
                <div class="d-grid gap-2">
                    <a href="send.php" class="btn btn-primary">
                        <i class="fas fa-paper-plane me-2"></i>
                        Envoyer un SMS
                    </a>
                    <a href="send.php?type=urgent" class="btn btn-danger">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        SMS urgent
                    </a>
                    <a href="send.php?type=rappel" class="btn btn-warning">
                        <i class="fas fa-bell me-2"></i>
                        SMS de rappel
                    </a>
                    <a href="../templates/?type=sms" class="btn btn-outline-secondary">
                        <i class="fas fa-file-alt me-2"></i>
                        Templates SMS
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal de détails SMS -->
<div class="modal fade" id="smsDetailsModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-mobile-alt me-2"></i>
                    Détails du SMS
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="smsDetailsContent">
                <!-- Contenu dynamique -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="fas fa-times me-1"></i>
                    Fermer
                </button>
            </div>
        </div>
    </div>
</div>

<script>
function viewSmsDetails(sms) {
    const content = `
        <div class="row">
            <div class="col-md-6">
                <h6>Informations générales</h6>
                <table class="table table-sm">
                    <tr><td><strong>ID:</strong></td><td>${sms.id}</td></tr>
                    <tr><td><strong>Date création:</strong></td><td>${sms.created_at}</td></tr>
                    <tr><td><strong>Date envoi:</strong></td><td>${sms.date_envoi || 'Non envoyé'}</td></tr>
                    <tr><td><strong>Date livraison:</strong></td><td>${sms.date_livraison || 'Non livré'}</td></tr>
                    <tr><td><strong>Tentatives:</strong></td><td>${sms.tentatives}</td></tr>
                </table>
            </div>
            <div class="col-md-6">
                <h6>Destinataire</h6>
                <table class="table table-sm">
                    <tr><td><strong>Téléphone:</strong></td><td>${sms.destinataire_telephone}</td></tr>
                    <tr><td><strong>Nom:</strong></td><td>${sms.destinataire_nom || 'Non spécifié'}</td></tr>
                    <tr><td><strong>Type:</strong></td><td><span class="badge bg-primary">${sms.type_sms}</span></td></tr>
                    <tr><td><strong>Statut:</strong></td><td><span class="badge bg-success">${sms.status}</span></td></tr>
                    <tr><td><strong>Coût:</strong></td><td>${sms.cout ? sms.cout + ' FC' : 'Gratuit'}</td></tr>
                </table>
            </div>
        </div>
        <div class="row mt-3">
            <div class="col-12">
                <h6>Message</h6>
                <div class="border p-3 bg-light rounded">
                    ${sms.message}
                </div>
                <small class="text-muted">${sms.message.length} caractère(s)</small>
            </div>
        </div>
        ${sms.provider_response ? `
        <div class="row mt-3">
            <div class="col-12">
                <h6>Réponse du fournisseur</h6>
                <div class="border p-3 bg-light rounded">
                    <code>${sms.provider_response}</code>
                </div>
            </div>
        </div>
        ` : ''}
    `;
    
    document.getElementById('smsDetailsContent').innerHTML = content;
    const modal = new bootstrap.Modal(document.getElementById('smsDetailsModal'));
    modal.show();
}

// Styles pour les cartes statistiques
const style = document.createElement('style');
style.textContent = `
    .stats-card {
        transition: all 0.2s ease-in-out;
    }
    .stats-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15) !important;
    }
`;
document.head.appendChild(style);
</script>

<?php include '../../../includes/footer.php'; ?>
