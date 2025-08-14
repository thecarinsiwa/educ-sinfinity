<?php
/**
 * Module Communication - Tableau de bord
 * Application de gestion scolaire - République Démocratique du Congo
 */

require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../includes/functions.php';

// Vérifier l'authentification et les permissions
requireLogin();
if (!checkPermission('communication')) {
    showMessage('error', 'Accès refusé à cette page.');
    redirectTo('../../index.php');
}

// Statistiques générales
try {
    $stats = $database->query(
        "SELECT 
            (SELECT COUNT(*) FROM messages WHERE status = 'envoye' AND DATE(date_envoi) = CURDATE()) as messages_aujourd_hui,
            (SELECT COUNT(*) FROM messages WHERE status = 'brouillon') as brouillons,
            (SELECT COUNT(*) FROM messages WHERE status = 'programme') as programmes,
            (SELECT COUNT(*) FROM annonces WHERE active = 1) as annonces_actives,
            (SELECT COUNT(*) FROM notifications WHERE lu = 0 AND user_id = ?) as notifications_non_lues,
            (SELECT COUNT(*) FROM sms_logs WHERE DATE(date_envoi) = CURDATE()) as sms_aujourd_hui",
        [$_SESSION['user_id']]
    )->fetch();
} catch (Exception $e) {
    $stats = [
        'messages_aujourd_hui' => 0,
        'brouillons' => 0,
        'programmes' => 0,
        'annonces_actives' => 0,
        'notifications_non_lues' => 0,
        'sms_aujourd_hui' => 0
    ];
}

// Messages récents
try {
    $messages_recents = $database->query(
        "SELECT m.*, u.nom as expediteur_nom, u.prenom as expediteur_prenom
         FROM messages m
         JOIN users u ON m.expediteur_id = u.id
         WHERE m.status = 'envoye'
         ORDER BY m.date_envoi DESC
         LIMIT 8"
    )->fetchAll();
} catch (Exception $e) {
    $messages_recents = [];
}

// Annonces actives
try {
    $annonces_actives = $database->query(
        "SELECT a.*, u.nom as auteur_nom, u.prenom as auteur_prenom
         FROM annonces a
         JOIN users u ON a.auteur_id = u.id
         WHERE a.active = 1 AND (a.date_expiration IS NULL OR a.date_expiration > NOW())
         ORDER BY a.epinglee DESC, a.date_publication DESC
         LIMIT 5"
    )->fetchAll();
} catch (Exception $e) {
    $annonces_actives = [];
}

// Notifications récentes
try {
    $notifications_recentes = $database->query(
        "SELECT * FROM notifications 
         WHERE user_id = ? AND (expire_le IS NULL OR expire_le > NOW())
         ORDER BY lu ASC, created_at DESC
         LIMIT 6",
        [$_SESSION['user_id']]
    )->fetchAll();
} catch (Exception $e) {
    $notifications_recentes = [];
}

// Statistiques SMS
try {
    $stats_sms = $database->query(
        "SELECT 
            COUNT(*) as total_sms,
            COUNT(CASE WHEN status = 'envoye' THEN 1 END) as envoyes,
            COUNT(CASE WHEN status = 'echec' THEN 1 END) as echecs,
            SUM(CASE WHEN cout IS NOT NULL THEN cout ELSE 0 END) as cout_total
         FROM sms_logs 
         WHERE MONTH(created_at) = MONTH(CURDATE()) AND YEAR(created_at) = YEAR(CURDATE())"
    )->fetch();
} catch (Exception $e) {
    $stats_sms = ['total_sms' => 0, 'envoyes' => 0, 'echecs' => 0, 'cout_total' => 0];
}

$page_title = "Module Communication";
include '../../includes/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">
        <i class="fas fa-comments me-2"></i>
        Module Communication
    </h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <div class="btn-group me-2">
            <a href="messages/compose.php" class="btn btn-primary">
                <i class="fas fa-pen me-1"></i>
                Nouveau message
            </a>
        </div>
        <div class="btn-group me-2">
            <a href="annonces/add.php" class="btn btn-success">
                <i class="fas fa-bullhorn me-1"></i>
                Nouvelle annonce
            </a>
        </div>
    </div>
</div>

<!-- Statistiques rapides -->
<div class="row mb-4">
    <div class="col-md-2">
        <div class="card text-center shadow-sm stats-card">
            <div class="card-body">
                <i class="fas fa-paper-plane fa-2x text-primary mb-2"></i>
                <h4><?php echo number_format($stats['messages_aujourd_hui'] ?? 0); ?></h4>
                <p class="text-muted mb-0">Messages aujourd'hui</p>
            </div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card text-center shadow-sm stats-card">
            <div class="card-body">
                <i class="fas fa-edit fa-2x text-warning mb-2"></i>
                <h4><?php echo number_format($stats['brouillons'] ?? 0); ?></h4>
                <p class="text-muted mb-0">Brouillons</p>
            </div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card text-center shadow-sm stats-card">
            <div class="card-body">
                <i class="fas fa-clock fa-2x text-info mb-2"></i>
                <h4><?php echo number_format($stats['programmes'] ?? 0); ?></h4>
                <p class="text-muted mb-0">Programmés</p>
            </div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card text-center shadow-sm stats-card">
            <div class="card-body">
                <i class="fas fa-bullhorn fa-2x text-success mb-2"></i>
                <h4><?php echo number_format($stats['annonces_actives'] ?? 0); ?></h4>
                <p class="text-muted mb-0">Annonces actives</p>
            </div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card text-center shadow-sm stats-card">
            <div class="card-body">
                <i class="fas fa-bell fa-2x text-danger mb-2"></i>
                <h4><?php echo number_format($stats['notifications_non_lues'] ?? 0); ?></h4>
                <p class="text-muted mb-0">Notifications</p>
            </div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card text-center shadow-sm stats-card">
            <div class="card-body">
                <i class="fas fa-sms fa-2x text-secondary mb-2"></i>
                <h4><?php echo number_format($stats['sms_aujourd_hui'] ?? 0); ?></h4>
                <p class="text-muted mb-0">SMS aujourd'hui</p>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <!-- Messages récents -->
    <div class="col-lg-6 mb-4">
        <div class="card shadow-sm">
            <div class="card-header bg-light">
                <h5 class="mb-0">
                    <i class="fas fa-envelope me-2 text-primary"></i>
                    Messages récents
                </h5>
            </div>
            <div class="card-body">
                <?php if (empty($messages_recents)): ?>
                    <div class="text-center py-4">
                        <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                        <h6 class="text-muted">Aucun message récent</h6>
                        <a href="messages/compose.php" class="btn btn-primary btn-sm">
                            <i class="fas fa-pen me-1"></i>
                            Composer un message
                        </a>
                    </div>
                <?php else: ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($messages_recents as $message): ?>
                            <div class="list-group-item border-0 px-0">
                                <div class="d-flex w-100 justify-content-between">
                                    <h6 class="mb-1"><?php echo htmlspecialchars($message['sujet']); ?></h6>
                                    <small class="text-muted"><?php echo formatDate($message['date_envoi']); ?></small>
                                </div>
                                <p class="mb-1 text-muted small">
                                    De: <?php echo htmlspecialchars($message['expediteur_nom'] . ' ' . $message['expediteur_prenom']); ?>
                                </p>
                                <small class="text-muted">
                                    <?php
                                    $type_colors = [
                                        'info' => 'primary',
                                        'urgent' => 'danger',
                                        'rappel' => 'warning',
                                        'felicitation' => 'success',
                                        'convocation' => 'info'
                                    ];
                                    $color = $type_colors[$message['type_message']] ?? 'secondary';
                                    ?>
                                    <span class="badge bg-<?php echo $color; ?>">
                                        <?php echo ucfirst($message['type_message']); ?>
                                    </span>
                                </small>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="text-center mt-3">
                        <a href="messages/" class="btn btn-outline-primary btn-sm">
                            <i class="fas fa-list me-1"></i>
                            Voir tous les messages
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Annonces actives -->
    <div class="col-lg-6 mb-4">
        <div class="card shadow-sm">
            <div class="card-header bg-light">
                <h5 class="mb-0">
                    <i class="fas fa-bullhorn me-2 text-success"></i>
                    Annonces actives
                </h5>
            </div>
            <div class="card-body">
                <?php if (empty($annonces_actives)): ?>
                    <div class="text-center py-4">
                        <i class="fas fa-bullhorn fa-3x text-muted mb-3"></i>
                        <h6 class="text-muted">Aucune annonce active</h6>
                        <a href="annonces/add.php" class="btn btn-success btn-sm">
                            <i class="fas fa-plus me-1"></i>
                            Créer une annonce
                        </a>
                    </div>
                <?php else: ?>
                    <?php foreach ($annonces_actives as $annonce): ?>
                        <div class="card mb-3 border-start border-4" style="border-left-color: <?php echo $annonce['couleur']; ?> !important;">
                            <div class="card-body p-3">
                                <div class="d-flex justify-content-between align-items-start">
                                    <h6 class="card-title mb-1">
                                        <?php if ($annonce['epinglee']): ?>
                                            <i class="fas fa-thumbtack text-warning me-1"></i>
                                        <?php endif; ?>
                                        <?php echo htmlspecialchars($annonce['titre']); ?>
                                    </h6>
                                    <span class="badge" style="background-color: <?php echo $annonce['couleur']; ?>">
                                        <?php echo ucfirst($annonce['type_annonce']); ?>
                                    </span>
                                </div>
                                <p class="card-text small text-muted mb-2">
                                    <?php echo htmlspecialchars(substr($annonce['contenu'], 0, 100)); ?>
                                    <?php if (strlen($annonce['contenu']) > 100): ?>...<?php endif; ?>
                                </p>
                                <small class="text-muted">
                                    Par <?php echo htmlspecialchars($annonce['auteur_nom'] . ' ' . $annonce['auteur_prenom']); ?>
                                    • <?php echo formatDate($annonce['date_publication']); ?>
                                    • <?php echo $annonce['vues']; ?> vue(s)
                                </small>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    <div class="text-center">
                        <a href="annonces/" class="btn btn-outline-success btn-sm">
                            <i class="fas fa-list me-1"></i>
                            Voir toutes les annonces
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <!-- Notifications -->
    <div class="col-lg-6 mb-4">
        <div class="card shadow-sm">
            <div class="card-header bg-light">
                <h5 class="mb-0">
                    <i class="fas fa-bell me-2 text-warning"></i>
                    Mes notifications
                </h5>
            </div>
            <div class="card-body">
                <?php if (empty($notifications_recentes)): ?>
                    <div class="text-center py-4">
                        <i class="fas fa-bell-slash fa-3x text-muted mb-3"></i>
                        <h6 class="text-muted">Aucune notification</h6>
                        <p class="text-muted small">Vous êtes à jour !</p>
                    </div>
                <?php else: ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($notifications_recentes as $notif): ?>
                            <div class="list-group-item border-0 px-0 <?php echo !$notif['lu'] ? 'bg-light' : ''; ?>">
                                <div class="d-flex align-items-start">
                                    <div class="flex-shrink-0 me-3">
                                        <i class="<?php echo $notif['icone']; ?> text-<?php echo $notif['type']; ?>"></i>
                                    </div>
                                    <div class="flex-grow-1">
                                        <h6 class="mb-1 <?php echo !$notif['lu'] ? 'fw-bold' : ''; ?>">
                                            <?php echo htmlspecialchars($notif['titre']); ?>
                                        </h6>
                                        <p class="mb-1 small text-muted">
                                            <?php echo htmlspecialchars(substr($notif['contenu'], 0, 80)); ?>
                                            <?php if (strlen($notif['contenu']) > 80): ?>...<?php endif; ?>
                                        </p>
                                        <small class="text-muted">
                                            <?php echo formatDate($notif['created_at']); ?>
                                        </small>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="text-center mt-3">
                        <a href="notifications/" class="btn btn-outline-warning btn-sm">
                            <i class="fas fa-bell me-1"></i>
                            Voir toutes les notifications
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Statistiques SMS -->
    <div class="col-lg-6 mb-4">
        <div class="card shadow-sm">
            <div class="card-header bg-light">
                <h5 class="mb-0">
                    <i class="fas fa-mobile-alt me-2 text-info"></i>
                    Statistiques SMS (ce mois)
                </h5>
            </div>
            <div class="card-body">
                <div class="row text-center">
                    <div class="col-6 mb-3">
                        <div class="border-end">
                            <h4 class="text-primary"><?php echo number_format($stats_sms['total_sms'] ?? 0); ?></h4>
                            <small class="text-muted">Total envoyés</small>
                        </div>
                    </div>
                    <div class="col-6 mb-3">
                        <h4 class="text-success"><?php echo number_format($stats_sms['envoyes'] ?? 0); ?></h4>
                        <small class="text-muted">Réussis</small>
                    </div>
                    <div class="col-6">
                        <div class="border-end">
                            <h4 class="text-danger"><?php echo number_format($stats_sms['echecs'] ?? 0); ?></h4>
                            <small class="text-muted">Échecs</small>
                        </div>
                    </div>
                    <div class="col-6">
                        <h4 class="text-warning"><?php echo number_format($stats_sms['cout_total'] ?? 0); ?> FC</h4>
                        <small class="text-muted">Coût total</small>
                    </div>
                </div>
                <div class="text-center mt-3">
                    <a href="sms/" class="btn btn-outline-info btn-sm">
                        <i class="fas fa-chart-bar me-1"></i>
                        Voir les détails SMS
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Actions rapides -->
<div class="row">
    <div class="col-12">
        <div class="card shadow-sm">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0">
                    <i class="fas fa-bolt me-2"></i>
                    Actions rapides
                </h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-3 mb-3">
                        <a href="messages/compose.php" class="btn btn-outline-primary w-100">
                            <i class="fas fa-pen fa-2x mb-2"></i>
                            <br>Composer un message
                        </a>
                    </div>
                    <div class="col-md-3 mb-3">
                        <a href="annonces/add.php" class="btn btn-outline-success w-100">
                            <i class="fas fa-bullhorn fa-2x mb-2"></i>
                            <br>Publier une annonce
                        </a>
                    </div>
                    <div class="col-md-3 mb-3">
                        <a href="sms/send.php" class="btn btn-outline-info w-100">
                            <i class="fas fa-mobile-alt fa-2x mb-2"></i>
                            <br>Envoyer un SMS
                        </a>
                    </div>
                    <div class="col-md-3 mb-3">
                        <a href="templates/" class="btn btn-outline-secondary w-100">
                            <i class="fas fa-file-alt fa-2x mb-2"></i>
                            <br>Gérer les templates
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.stats-card {
    transition: all 0.2s ease-in-out;
}

.stats-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15) !important;
}

.card {
    border: none;
    border-radius: 10px;
}

.card-header {
    border-radius: 10px 10px 0 0 !important;
    border: none;
}

.list-group-item:hover {
    background-color: #f8f9fa;
}
</style>

<?php include '../../includes/footer.php'; ?>
