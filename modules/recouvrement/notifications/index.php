<?php
/**
 * Module Recouvrement - Notifications
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

$page_title = 'Notifications de recouvrement';

// Obtenir l'année scolaire actuelle
$current_year = getCurrentAcademicYear();

// Traitement des actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = sanitizeInput($_POST['action'] ?? '');
    
    if ($action === 'send_notification') {
        try {
            $type_notification = sanitizeInput($_POST['type_notification']);
            $destinataires = $_POST['destinataires'] ?? [];
            $sujet = sanitizeInput($_POST['sujet']);
            $message = sanitizeInput($_POST['message']);
            $campagne_id = (int)($_POST['campagne_id'] ?? 0);
            
            $database->beginTransaction();
            
            // Enregistrer la notification
            $notification_id = $database->query(
                "INSERT INTO notifications_recouvrement (
                    type_notification, sujet, message, campagne_id, annee_scolaire_id,
                    created_by, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, NOW())",
                [$type_notification, $sujet, $message, $campagne_id, $current_year['id'], $_SESSION['user_id']]
            )->lastInsertId();
            
            // Enregistrer les destinataires
            foreach ($destinataires as $eleve_id) {
                $database->query(
                    "INSERT INTO notifications_destinataires (
                        notification_id, eleve_id, status, created_at
                    ) VALUES (?, ?, 'pending', NOW())",
                    [$notification_id, $eleve_id]
                );
            }
            
            $database->commit();
            showMessage('success', 'Notification programmée avec succès.');
            redirectTo('index.php');
            
        } catch (Exception $e) {
            $database->rollback();
            showMessage('error', 'Erreur lors de l\'envoi : ' . $e->getMessage());
        }
    }
    
    if ($action === 'send_immediate') {
        $notification_id = (int)($_POST['notification_id'] ?? 0);
        
        try {
            // Simuler l'envoi immédiat
            $database->query(
                "UPDATE notifications_destinataires SET status = 'sent', sent_at = NOW() WHERE notification_id = ?",
                [$notification_id]
            );
            
            showMessage('success', 'Notification envoyée immédiatement.');
            redirectTo('index.php');
            
        } catch (Exception $e) {
            showMessage('error', 'Erreur lors de l\'envoi : ' . $e->getMessage());
        }
    }
}

// Récupérer les notifications
$notifications = $database->query(
    "SELECT 
        nr.*,
        COUNT(nd.eleve_id) as total_destinataires,
        COUNT(CASE WHEN nd.status = 'sent' THEN 1 END) as envoyees,
        COUNT(CASE WHEN nd.status = 'pending' THEN 1 END) as en_attente,
        COUNT(CASE WHEN nd.status = 'failed' THEN 1 END) as echouees,
        cr.nom as campagne_nom
     FROM notifications_recouvrement nr
     LEFT JOIN notifications_destinataires nd ON nr.id = nd.notification_id
     LEFT JOIN campagnes_recouvrement cr ON nr.campagne_id = cr.id
     WHERE nr.annee_scolaire_id = ?
     GROUP BY nr.id
     ORDER BY nr.created_at DESC",
    [$current_year['id']]
)->fetchAll();

// Statistiques des notifications
$notification_stats = $database->query(
    "SELECT 
        COUNT(*) as total_notifications,
        COUNT(CASE WHEN type_notification = 'sms' THEN 1 END) as sms_count,
        COUNT(CASE WHEN type_notification = 'email' THEN 1 END) as email_count,
        COUNT(CASE WHEN type_notification = 'lettre' THEN 1 END) as lettre_count,
        SUM(CASE WHEN nd.status = 'sent' THEN 1 ELSE 0 END) as total_envoyees,
        SUM(CASE WHEN nd.status = 'failed' THEN 1 ELSE 0 END) as total_echouees
     FROM notifications_recouvrement nr
     LEFT JOIN notifications_destinataires nd ON nr.id = nd.notification_id
     WHERE nr.annee_scolaire_id = ?",
    [$current_year['id']]
)->fetch();

// Récupérer les campagnes pour le formulaire
$campaigns = $database->query(
    "SELECT id, nom FROM campagnes_recouvrement WHERE annee_scolaire_id = ? AND status = 'active'",
    [$current_year['id']]
)->fetchAll();

// Récupérer les débiteurs pour le formulaire
$debitors = $database->query(
    "SELECT 
        e.id,
        e.nom,
        e.prenom,
        e.telephone,
        e.email,
        c.nom as classe_nom,
        SUM(fs.montant) - COALESCE(SUM(p.montant), 0) as dette_totale
     FROM eleves e
     JOIN inscriptions i ON e.id = i.eleve_id
     JOIN classes c ON i.classe_id = c.id
     JOIN frais_scolaires fs ON i.classe_id = fs.classe_id
     LEFT JOIN paiements p ON e.id = p.eleve_id 
         AND fs.type_frais COLLATE utf8mb4_unicode_ci = p.type_paiement COLLATE utf8mb4_unicode_ci
         AND p.annee_scolaire_id = fs.annee_scolaire_id
     WHERE i.annee_scolaire_id = ? AND fs.annee_scolaire_id = ?
     GROUP BY e.id
     HAVING dette_totale > 0
     ORDER BY dette_totale DESC",
    [$current_year['id'], $current_year['id']]
)->fetchAll();

include '../../../includes/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">
        <i class="fas fa-bell me-2"></i>
        Notifications de recouvrement
    </h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <div class="btn-group me-2">
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#sendNotificationModal">
                <i class="fas fa-paper-plane me-1"></i>
                Nouvelle notification
            </button>
        </div>
        <div class="btn-group">
            <a href="../reports/notifications.php" class="btn btn-outline-info">
                <i class="fas fa-chart-bar me-1"></i>
                Rapports
            </a>
        </div>
    </div>
</div>

<!-- Statistiques des notifications -->
<div class="row mb-4">
    <div class="col-md-2">
        <div class="card text-center">
            <div class="card-body">
                <h4 class="text-primary"><?php echo $notification_stats['total_notifications']; ?></h4>
                <p class="card-text">Total notifications</p>
            </div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card text-center">
            <div class="card-body">
                <h4 class="text-success"><?php echo $notification_stats['sms_count']; ?></h4>
                <p class="card-text">SMS</p>
            </div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card text-center">
            <div class="card-body">
                <h4 class="text-info"><?php echo $notification_stats['email_count']; ?></h4>
                <p class="card-text">Emails</p>
            </div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card text-center">
            <div class="card-body">
                <h4 class="text-warning"><?php echo $notification_stats['lettre_count']; ?></h4>
                <p class="card-text">Lettres</p>
            </div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card text-center">
            <div class="card-body">
                <h4 class="text-success"><?php echo $notification_stats['total_envoyees']; ?></h4>
                <p class="card-text">Envoyées</p>
            </div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card text-center">
            <div class="card-body">
                <h4 class="text-danger"><?php echo $notification_stats['total_echouees']; ?></h4>
                <p class="card-text">Échouées</p>
            </div>
        </div>
    </div>
</div>

<!-- Liste des notifications -->
<div class="card">
    <div class="card-header">
        <h5 class="mb-0">
            <i class="fas fa-list me-2"></i>
            Historique des notifications
        </h5>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>Type</th>
                        <th>Sujet</th>
                        <th>Campagne</th>
                        <th>Destinataires</th>
                        <th>Statut</th>
                        <th>Date création</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($notifications as $notification): ?>
                        <tr>
                            <td>
                                <?php 
                                $type_labels = [
                                    'sms' => '<span class="badge bg-success">SMS</span>',
                                    'email' => '<span class="badge bg-info">Email</span>',
                                    'lettre' => '<span class="badge bg-warning">Lettre</span>'
                                ];
                                echo $type_labels[$notification['type_notification']] ?? $notification['type_notification'];
                                ?>
                            </td>
                            <td>
                                <strong><?php echo htmlspecialchars($notification['sujet']); ?></strong><br>
                                <small class="text-muted"><?php echo substr(htmlspecialchars($notification['message']), 0, 50) . '...'; ?></small>
                            </td>
                            <td>
                                <?php echo htmlspecialchars($notification['campagne_nom'] ?? 'Général'); ?>
                            </td>
                            <td>
                                <span class="badge bg-primary"><?php echo $notification['total_destinataires']; ?></span><br>
                                <small class="text-muted">
                                    <?php echo $notification['envoyees']; ?> envoyées, 
                                    <?php echo $notification['en_attente']; ?> en attente
                                </small>
                            </td>
                            <td>
                                <?php 
                                $progress = $notification['total_destinataires'] > 0 ? 
                                    ($notification['envoyees'] / $notification['total_destinataires']) * 100 : 0;
                                ?>
                                <div class="progress" style="height: 20px;">
                                    <div class="progress-bar bg-success" role="progressbar" 
                                         style="width: <?php echo $progress; ?>%"
                                         aria-valuenow="<?php echo $progress; ?>" 
                                         aria-valuemin="0" aria-valuemax="100">
                                        <?php echo round($progress); ?>%
                                    </div>
                                </div>
                            </td>
                            <td>
                                <?php echo date('d/m/Y H:i', strtotime($notification['created_at'])); ?>
                            </td>
                            <td>
                                <div class="btn-group btn-group-sm">
                                    <a href="details.php?id=<?php echo $notification['id']; ?>" class="btn btn-outline-primary" title="Voir détails">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <?php if ($notification['en_attente'] > 0): ?>
                                        <button type="button" class="btn btn-outline-success" 
                                                onclick="sendImmediate(<?php echo $notification['id']; ?>)" title="Envoyer maintenant">
                                            <i class="fas fa-paper-plane"></i>
                                        </button>
                                    <?php endif; ?>
                                    <button type="button" class="btn btn-outline-info" 
                                            onclick="showNotificationModal(<?php echo $notification['id']; ?>)" title="Répéter">
                                        <i class="fas fa-redo"></i>
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

<!-- Templates de messages -->
<div class="row mt-4">
    <div class="col-md-4">
        <div class="card">
            <div class="card-header">
                <h6 class="mb-0">
                    <i class="fas fa-file-alt me-2"></i>
                    Templates SMS
                </h6>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <strong>Rappel paiement :</strong>
                    <p class="text-muted small">Bonjour {nom_parent}, votre enfant {nom_eleve} a une dette de {montant} FC. Merci de régulariser.</p>
                </div>
                <div class="mb-3">
                    <strong>Rappel urgent :</strong>
                    <p class="text-muted small">URGENT: Dette de {montant} FC pour {nom_eleve}. Contactez-nous au {telephone}.</p>
                </div>
                <div class="mb-3">
                    <strong>Confirmation paiement :</strong>
                    <p class="text-muted small">Merci pour votre paiement de {montant} FC. Reçu confirmé pour {nom_eleve}.</p>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-4">
        <div class="card">
            <div class="card-header">
                <h6 class="mb-0">
                    <i class="fas fa-envelope me-2"></i>
                    Templates Email
                </h6>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <strong>Rappel courtois :</strong>
                    <p class="text-muted small">Madame, Monsieur, nous vous rappelons que votre enfant {nom_eleve} a une dette de {montant} FC.</p>
                </div>
                <div class="mb-3">
                    <strong>Lettre de mise en demeure :</strong>
                    <p class="text-muted small">Suite à nos relances, nous vous mettons en demeure de régulariser la dette de {montant} FC.</p>
                </div>
                <div class="mb-3">
                    <strong>Accord de paiement :</strong>
                    <p class="text-muted small">Nous acceptons votre proposition de paiement échelonné pour {nom_eleve}.</p>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-4">
        <div class="card">
            <div class="card-header">
                <h6 class="mb-0">
                    <i class="fas fa-print me-2"></i>
                    Templates Lettres
                </h6>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <strong>Lettre de rappel :</strong>
                    <p class="text-muted small">Lettre officielle de rappel pour dette de {montant} FC.</p>
                </div>
                <div class="mb-3">
                    <strong>Mise en demeure :</strong>
                    <p class="text-muted small">Lettre de mise en demeure avec délai de 15 jours.</p>
                </div>
                <div class="mb-3">
                    <strong>Accord de paiement :</strong>
                    <p class="text-muted small">Convention de paiement échelonné signée.</p>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Envoi de notification -->
<div class="modal fade" id="sendNotificationModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Nouvelle notification</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="send_notification">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="type_notification" class="form-label">Type de notification *</label>
                                <select class="form-select" id="type_notification" name="type_notification" required>
                                    <option value="">Sélectionner</option>
                                    <option value="sms">SMS</option>
                                    <option value="email">Email</option>
                                    <option value="lettre">Lettre</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="campagne_id" class="form-label">Campagne (optionnel)</label>
                                <select class="form-select" id="campagne_id" name="campagne_id">
                                    <option value="">Aucune campagne</option>
                                    <?php foreach ($campaigns as $campaign): ?>
                                        <option value="<?php echo $campaign['id']; ?>">
                                            <?php echo htmlspecialchars($campaign['nom']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="sujet" class="form-label">Sujet *</label>
                        <input type="text" class="form-control" id="sujet" name="sujet" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="message" class="form-label">Message *</label>
                        <textarea class="form-control" id="message" name="message" rows="5" required 
                                  placeholder="Utilisez {nom_eleve}, {nom_parent}, {montant}, {classe} comme variables"></textarea>
                        <small class="form-text text-muted">
                            Variables disponibles: {nom_eleve}, {nom_parent}, {montant}, {classe}, {telephone}
                        </small>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Destinataires *</label>
                        <div class="row">
                            <div class="col-md-6">
                                <button type="button" class="btn btn-outline-primary btn-sm mb-2" onclick="selectAllDebitors()">
                                    <i class="fas fa-check-double me-1"></i>
                                    Sélectionner tous les débiteurs
                                </button>
                            </div>
                            <div class="col-md-6">
                                <button type="button" class="btn btn-outline-secondary btn-sm mb-2" onclick="clearSelection()">
                                    <i class="fas fa-times me-1"></i>
                                    Effacer la sélection
                                </button>
                            </div>
                        </div>
                        <div style="max-height: 200px; overflow-y: auto; border: 1px solid #ddd; padding: 10px;">
                            <?php foreach ($debitors as $debitor): ?>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="destinataires[]" 
                                           value="<?php echo $debitor['id']; ?>" id="debitor_<?php echo $debitor['id']; ?>">
                                    <label class="form-check-label" for="debitor_<?php echo $debitor['id']; ?>">
                                        <strong><?php echo htmlspecialchars($debitor['nom'] . ' ' . $debitor['prenom']); ?></strong>
                                        <br>
                                        <small class="text-muted">
                                            <?php echo htmlspecialchars($debitor['classe_nom']); ?> - 
                                            <?php echo number_format($debitor['dette_totale']); ?> FC
                                            <?php if ($debitor['telephone']): ?>
                                                - <?php echo htmlspecialchars($debitor['telephone']); ?>
                                            <?php endif; ?>
                                        </small>
                                    </label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-primary">Programmer l'envoi</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function selectAllDebitors() {
    const checkboxes = document.querySelectorAll('input[name="destinataires[]"]');
    checkboxes.forEach(checkbox => checkbox.checked = true);
}

function clearSelection() {
    const checkboxes = document.querySelectorAll('input[name="destinataires[]"]');
    checkboxes.forEach(checkbox => checkbox.checked = false);
}

function sendImmediate(notificationId) {
    if (confirm('Envoyer cette notification immédiatement ?')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="action" value="send_immediate">
            <input type="hidden" name="notification_id" value="${notificationId}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}

function showNotificationModal(notificationId) {
    // Récupérer les détails de la notification et pré-remplir le formulaire
    // Cette fonction peut être étendue pour récupérer les données via AJAX
    document.getElementById('sendNotificationModal').querySelector('.modal-title').textContent = 'Répéter notification';
    new bootstrap.Modal(document.getElementById('sendNotificationModal')).show();
}

// Validation du formulaire
document.querySelector('form').addEventListener('submit', function(e) {
    const destinataires = document.querySelectorAll('input[name="destinataires[]"]:checked');
    if (destinataires.length === 0) {
        e.preventDefault();
        alert('Veuillez sélectionner au moins un destinataire.');
    }
});
</script>

<?php include '../../../includes/footer.php'; ?>
