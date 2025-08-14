<?php
/**
 * Module Communication - Page principale
 * Application de gestion scolaire - République Démocratique du Congo
 */

require_once '../../../config/config.php';
require_once '../../../config/database.php';
require_once '../../../includes/functions.php';

// Vérifier l'authentification et les permissions
requireLogin();
if (!checkPermission('communication') && !checkPermission('communication_view')) {
    showMessage('error', 'Accès refusé à ce module.');
    redirectTo('../index.php');
}

$page_title = 'Communication';

// Statistiques de communication
$stats = [];

// Messages non lus pour l'utilisateur actuel
$stmt = $database->query(
    "SELECT COUNT(*) as total FROM messages WHERE destinataire_id = ? AND status = 'non_lu'",
    [$_SESSION['user_id']]
);
$stats['messages_non_lus'] = $stmt->fetch()['total'];

// Messages envoyés cette semaine
$stmt = $database->query(
    "SELECT COUNT(*) as total FROM messages WHERE expediteur_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)",
    [$_SESSION['user_id']]
);
$stats['messages_envoyes_semaine'] = $stmt->fetch()['total'];

// Notifications non lues
$stmt = $database->query(
    "SELECT COUNT(*) as total FROM notifications WHERE user_id = ? AND status = 'non_lu'",
    [$_SESSION['user_id']]
);
$stats['notifications_non_lues'] = $stmt->fetch()['total'];

// Annonces actives
$stmt = $database->query(
    "SELECT COUNT(*) as total FROM annonces WHERE status = 'active' AND (date_fin IS NULL OR date_fin >= CURDATE())"
);
$stats['annonces_actives'] = $stmt->fetch()['total'];

// Messages récents
$messages_recents = $database->query(
    "SELECT m.*, 
            u_exp.username as expediteur_nom, u_exp.nom as expediteur_nom_complet,
            u_dest.username as destinataire_nom, u_dest.nom as destinataire_nom_complet
     FROM messages m
     JOIN users u_exp ON m.expediteur_id = u_exp.id
     JOIN users u_dest ON m.destinataire_id = u_dest.id
     WHERE m.expediteur_id = ? OR m.destinataire_id = ?
     ORDER BY m.created_at DESC
     LIMIT 8",
    [$_SESSION['user_id'], $_SESSION['user_id']]
)->fetchAll();

// Annonces récentes
$annonces_recentes = $database->query(
    "SELECT a.*, u.username as auteur_nom
     FROM annonces a
     JOIN users u ON a.auteur_id = u.id
     WHERE a.status = 'active' 
     AND (a.date_fin IS NULL OR a.date_fin >= CURDATE())
     ORDER BY a.created_at DESC
     LIMIT 5"
)->fetchAll();

// Notifications récentes
$notifications_recentes = $database->query(
    "SELECT * FROM notifications 
     WHERE user_id = ? 
     ORDER BY created_at DESC 
     LIMIT 8",
    [$_SESSION['user_id']]
)->fetchAll();

// Statistiques globales (pour les administrateurs)
$stats_globales = [];
if (checkPermission('communication')) {
    $stmt = $database->query("SELECT COUNT(*) as total FROM messages WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)");
    $stats_globales['messages_mois'] = $stmt->fetch()['total'];
    
    $stmt = $database->query("SELECT COUNT(DISTINCT expediteur_id) as total FROM messages WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
    $stats_globales['utilisateurs_actifs'] = $stmt->fetch()['total'];
}

include '../../../includes/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">
        <i class="fas fa-comments me-2"></i>
        Communication
    </h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <div class="btn-group me-2">
            <a href="../index.php" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-1"></i>
                Retour
            </a>
        </div>
        <div class="btn-group me-2">
            <button type="button" class="btn btn-info dropdown-toggle" data-bs-toggle="dropdown">
                <i class="fas fa-plus me-1"></i>
                Nouveau
            </button>
            <ul class="dropdown-menu">
                <li><a class="dropdown-item" href="messages/compose.php">
                    <i class="fas fa-envelope me-2"></i>Nouveau message
                </a></li>
                <?php if (checkPermission('communication')): ?>
                    <li><a class="dropdown-item" href="announcements/add.php">
                        <i class="fas fa-bullhorn me-2"></i>Nouvelle annonce
                    </a></li>
                    <li><a class="dropdown-item" href="notifications/send.php">
                        <i class="fas fa-bell me-2"></i>Envoyer notification
                    </a></li>
                <?php endif; ?>
            </ul>
        </div>
        <div class="btn-group">
            <button type="button" class="btn btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">
                <i class="fas fa-cog me-1"></i>
                Paramètres
            </button>
            <ul class="dropdown-menu">
                <li><a class="dropdown-item" href="settings/preferences.php">
                    <i class="fas fa-user-cog me-2"></i>Préférences
                </a></li>
                <li><a class="dropdown-item" href="settings/signatures.php">
                    <i class="fas fa-signature me-2"></i>Signatures
                </a></li>
                <?php if (checkPermission('communication')): ?>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item" href="settings/templates.php">
                        <i class="fas fa-file-alt me-2"></i>Modèles
                    </a></li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</div>

<!-- Statistiques rapides -->
<div class="row mb-4">
    <div class="col-lg-3 col-md-6 mb-3">
        <div class="card text-white bg-primary">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h4><?php echo $stats['messages_non_lus']; ?></h4>
                        <p class="mb-0">Messages non lus</p>
                    </div>
                    <div class="align-self-center">
                        <i class="fas fa-envelope fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-3 col-md-6 mb-3">
        <div class="card text-white bg-warning">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h4><?php echo $stats['notifications_non_lues']; ?></h4>
                        <p class="mb-0">Notifications</p>
                    </div>
                    <div class="align-self-center">
                        <i class="fas fa-bell fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-3 col-md-6 mb-3">
        <div class="card text-white bg-success">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h4><?php echo $stats['annonces_actives']; ?></h4>
                        <p class="mb-0">Annonces actives</p>
                    </div>
                    <div class="align-self-center">
                        <i class="fas fa-bullhorn fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-3 col-md-6 mb-3">
        <div class="card text-white bg-info">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h4><?php echo $stats['messages_envoyes_semaine']; ?></h4>
                        <p class="mb-0">Envoyés (7j)</p>
                    </div>
                    <div class="align-self-center">
                        <i class="fas fa-paper-plane fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modules de communication -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-th-large me-2"></i>
                    Modules de communication
                </h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-lg-3 col-md-6 mb-3">
                        <a href="messages/" class="text-decoration-none">
                            <div class="card h-100 border-0 shadow-sm hover-card">
                                <div class="card-body text-center">
                                    <i class="fas fa-envelope fa-3x text-primary mb-3"></i>
                                    <h5 class="card-title">Messagerie</h5>
                                    <p class="card-text text-muted">
                                        Messages internes entre utilisateurs
                                    </p>
                                    <div class="mt-3">
                                        <?php if ($stats['messages_non_lus'] > 0): ?>
                                            <span class="badge bg-danger"><?php echo $stats['messages_non_lus']; ?> non lus</span>
                                        <?php else: ?>
                                            <span class="badge bg-success">À jour</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </a>
                    </div>
                    
                    <div class="col-lg-3 col-md-6 mb-3">
                        <a href="announcements/" class="text-decoration-none">
                            <div class="card h-100 border-0 shadow-sm hover-card">
                                <div class="card-body text-center">
                                    <i class="fas fa-bullhorn fa-3x text-success mb-3"></i>
                                    <h5 class="card-title">Annonces</h5>
                                    <p class="card-text text-muted">
                                        Annonces officielles et informations
                                    </p>
                                    <div class="mt-3">
                                        <span class="badge bg-success"><?php echo $stats['annonces_actives']; ?> actives</span>
                                    </div>
                                </div>
                            </div>
                        </a>
                    </div>
                    
                    <div class="col-lg-3 col-md-6 mb-3">
                        <a href="notifications/" class="text-decoration-none">
                            <div class="card h-100 border-0 shadow-sm hover-card">
                                <div class="card-body text-center">
                                    <i class="fas fa-bell fa-3x text-warning mb-3"></i>
                                    <h5 class="card-title">Notifications</h5>
                                    <p class="card-text text-muted">
                                        Alertes et notifications système
                                    </p>
                                    <div class="mt-3">
                                        <?php if ($stats['notifications_non_lues'] > 0): ?>
                                            <span class="badge bg-warning"><?php echo $stats['notifications_non_lues']; ?> nouvelles</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">Aucune</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </a>
                    </div>
                    
                    <div class="col-lg-3 col-md-6 mb-3">
                        <a href="parents/" class="text-decoration-none">
                            <div class="card h-100 border-0 shadow-sm hover-card">
                                <div class="card-body text-center">
                                    <i class="fas fa-users fa-3x text-info mb-3"></i>
                                    <h5 class="card-title">Parents</h5>
                                    <p class="card-text text-muted">
                                        Communication avec les parents
                                    </p>
                                    <div class="mt-3">
                                        <span class="badge bg-info">Liaison école-famille</span>
                                    </div>
                                </div>
                            </div>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Contenu principal -->
<div class="row">
    <div class="col-lg-8">
        <!-- Messages récents -->
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">
                    <i class="fas fa-envelope me-2"></i>
                    Messages récents
                </h5>
                <a href="messages/" class="btn btn-sm btn-outline-primary">
                    <i class="fas fa-inbox me-1"></i>
                    Boîte de réception
                </a>
            </div>
            <div class="card-body">
                <?php if (!empty($messages_recents)): ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($messages_recents as $message): ?>
                            <div class="list-group-item d-flex justify-content-between align-items-start">
                                <div class="ms-2 me-auto">
                                    <div class="fw-bold">
                                        <?php if ($message['expediteur_id'] == $_SESSION['user_id']): ?>
                                            <i class="fas fa-arrow-right text-success me-1"></i>
                                            À: <?php echo htmlspecialchars($message['destinataire_nom_complet'] ?: $message['destinataire_nom']); ?>
                                        <?php else: ?>
                                            <i class="fas fa-arrow-left text-primary me-1"></i>
                                            De: <?php echo htmlspecialchars($message['expediteur_nom_complet'] ?: $message['expediteur_nom']); ?>
                                        <?php endif; ?>
                                    </div>
                                    <div><?php echo htmlspecialchars($message['sujet']); ?></div>
                                    <small class="text-muted">
                                        <?php echo formatDateTime($message['created_at']); ?>
                                    </small>
                                </div>
                                <div>
                                    <?php if ($message['destinataire_id'] == $_SESSION['user_id'] && $message['status'] === 'non_lu'): ?>
                                        <span class="badge bg-primary">Nouveau</span>
                                    <?php endif; ?>
                                    <a href="messages/view.php?id=<?php echo $message['id']; ?>" 
                                       class="btn btn-sm btn-outline-secondary ms-2">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="text-center py-4">
                        <i class="fas fa-envelope fa-3x text-muted mb-3"></i>
                        <p class="text-muted">Aucun message</p>
                        <a href="messages/compose.php" class="btn btn-primary">
                            <i class="fas fa-plus me-1"></i>
                            Écrire un message
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Annonces -->
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">
                    <i class="fas fa-bullhorn me-2"></i>
                    Annonces récentes
                </h5>
                <a href="announcements/" class="btn btn-sm btn-outline-success">
                    <i class="fas fa-list me-1"></i>
                    Toutes les annonces
                </a>
            </div>
            <div class="card-body">
                <?php if (!empty($annonces_recentes)): ?>
                    <?php foreach ($annonces_recentes as $annonce): ?>
                        <div class="card mb-3 border-start border-success border-4">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <h6 class="card-title"><?php echo htmlspecialchars($annonce['titre']); ?></h6>
                                        <p class="card-text"><?php echo htmlspecialchars(substr($annonce['contenu'], 0, 150)); ?>
                                        <?php echo strlen($annonce['contenu']) > 150 ? '...' : ''; ?></p>
                                        <small class="text-muted">
                                            Par <?php echo htmlspecialchars($annonce['auteur_nom']); ?> - 
                                            <?php echo formatDateTime($annonce['created_at']); ?>
                                        </small>
                                    </div>
                                    <div>
                                        <?php
                                        $priorite_colors = [
                                            'normale' => 'secondary',
                                            'importante' => 'warning',
                                            'urgente' => 'danger'
                                        ];
                                        $color = $priorite_colors[$annonce['priorite']] ?? 'secondary';
                                        ?>
                                        <span class="badge bg-<?php echo $color; ?>">
                                            <?php echo ucfirst($annonce['priorite']); ?>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="text-center py-4">
                        <i class="fas fa-bullhorn fa-3x text-muted mb-3"></i>
                        <p class="text-muted">Aucune annonce active</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <div class="col-lg-4">
        <!-- Notifications -->
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">
                    <i class="fas fa-bell me-2"></i>
                    Notifications
                </h5>
                <?php if ($stats['notifications_non_lues'] > 0): ?>
                    <a href="notifications/mark-all-read.php" class="btn btn-sm btn-outline-secondary">
                        <i class="fas fa-check me-1"></i>
                        Tout marquer lu
                    </a>
                <?php endif; ?>
            </div>
            <div class="card-body">
                <?php if (!empty($notifications_recentes)): ?>
                    <div class="list-group list-group-flush">
                        <?php foreach (array_slice($notifications_recentes, 0, 5) as $notification): ?>
                            <div class="list-group-item <?php echo $notification['status'] === 'non_lu' ? 'bg-light' : ''; ?>">
                                <div class="d-flex w-100 justify-content-between">
                                    <h6 class="mb-1"><?php echo htmlspecialchars($notification['titre']); ?></h6>
                                    <small><?php echo formatDateTime($notification['created_at']); ?></small>
                                </div>
                                <p class="mb-1"><?php echo htmlspecialchars(substr($notification['contenu'], 0, 80)); ?>
                                <?php echo strlen($notification['contenu']) > 80 ? '...' : ''; ?></p>
                                <?php if ($notification['status'] === 'non_lu'): ?>
                                    <small class="text-primary">Nouveau</small>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="text-center mt-3">
                        <a href="notifications/" class="btn btn-sm btn-outline-primary">
                            <i class="fas fa-list me-1"></i>
                            Voir toutes
                        </a>
                    </div>
                <?php else: ?>
                    <div class="text-center py-3">
                        <i class="fas fa-bell-slash fa-2x text-muted mb-2"></i>
                        <p class="text-muted mb-0">Aucune notification</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Statistiques globales (pour admin) -->
        <?php if (checkPermission('communication') && !empty($stats_globales)): ?>
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-chart-bar me-2"></i>
                    Statistiques globales
                </h5>
            </div>
            <div class="card-body">
                <div class="row text-center">
                    <div class="col-6">
                        <h4 class="text-primary"><?php echo $stats_globales['messages_mois']; ?></h4>
                        <small class="text-muted">Messages ce mois</small>
                    </div>
                    <div class="col-6">
                        <h4 class="text-success"><?php echo $stats_globales['utilisateurs_actifs']; ?></h4>
                        <small class="text-muted">Utilisateurs actifs</small>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Actions rapides -->
<div class="row mt-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-bolt me-2"></i>
                    Actions rapides
                </h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-3 mb-2">
                        <div class="d-grid">
                            <a href="messages/compose.php" class="btn btn-outline-primary">
                                <i class="fas fa-envelope me-2"></i>
                                Nouveau message
                            </a>
                        </div>
                    </div>
                    <?php if (checkPermission('communication')): ?>
                        <div class="col-md-3 mb-2">
                            <div class="d-grid">
                                <a href="announcements/add.php" class="btn btn-outline-success">
                                    <i class="fas fa-bullhorn me-2"></i>
                                    Nouvelle annonce
                                </a>
                            </div>
                        </div>
                        <div class="col-md-3 mb-2">
                            <div class="d-grid">
                                <a href="notifications/send.php" class="btn btn-outline-warning">
                                    <i class="fas fa-bell me-2"></i>
                                    Envoyer notification
                                </a>
                            </div>
                        </div>
                    <?php endif; ?>
                    <div class="col-md-3 mb-2">
                        <div class="d-grid">
                            <a href="parents/contact.php" class="btn btn-outline-info">
                                <i class="fas fa-users me-2"></i>
                                Contacter parents
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.hover-card {
    transition: transform 0.2s ease-in-out;
}

.hover-card:hover {
    transform: translateY(-5px);
}
</style>

<?php include '../../../includes/footer.php'; ?>
