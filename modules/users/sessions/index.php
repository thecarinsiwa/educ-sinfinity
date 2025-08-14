<?php
/**
 * Module Gestion des Utilisateurs - Sessions actives
 * Application de gestion scolaire - République Démocratique du Congo
 */

require_once '../../../config/config.php';
require_once '../../../config/database.php';
require_once '../../../includes/functions.php';

// Vérifier l'authentification et les permissions
requireLogin();
if (!checkPermission('admin')) {
    showMessage('error', 'Accès refusé à ce module.');
    redirectTo('../index.php');
}

$page_title = 'Sessions Actives';

// Traitement des actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = sanitizeInput($_POST['action'] ?? '');
    
    try {
        switch ($action) {
            case 'kill_session':
                $session_id = sanitizeInput($_POST['session_id'] ?? '');
                
                if (!$session_id) {
                    throw new Exception('ID de session manquant');
                }
                
                // Supprimer la session
                $database->query(
                    "DELETE FROM user_sessions WHERE id = ?",
                    [$session_id]
                );
                
                // Enregistrer l'action
                logUserAction(
                    'kill_session',
                    'users',
                    'Session terminée: ' . $session_id,
                    null
                );
                
                showMessage('success', 'Session terminée avec succès');
                break;
                
            case 'kill_user_sessions':
                $user_id = (int)($_POST['user_id'] ?? 0);
                
                if (!$user_id) {
                    throw new Exception('ID utilisateur manquant');
                }
                
                // Récupérer les informations de l'utilisateur
                $user = $database->query(
                    "SELECT username, nom, prenom FROM users WHERE id = ?",
                    [$user_id]
                )->fetch();
                
                if (!$user) {
                    throw new Exception('Utilisateur non trouvé');
                }
                
                // Supprimer toutes les sessions de l'utilisateur
                $stmt = $database->query(
                    "DELETE FROM user_sessions WHERE user_id = ?",
                    [$user_id]
                );
                
                $sessions_killed = $stmt->rowCount();
                
                // Enregistrer l'action
                logUserAction(
                    'kill_user_sessions',
                    'users',
                    'Toutes les sessions terminées pour: ' . $user['username'] . ' (' . $user['nom'] . ' ' . $user['prenom'] . ') - ' . $sessions_killed . ' sessions',
                    $user_id
                );
                
                showMessage('success', $sessions_killed . ' session(s) terminée(s) pour ' . $user['username']);
                break;
                
            case 'cleanup_expired':
                // Supprimer les sessions expirées (plus de 24h d'inactivité)
                $stmt = $database->query(
                    "DELETE FROM user_sessions WHERE last_activity < DATE_SUB(NOW(), INTERVAL 24 HOUR)"
                );
                
                $sessions_cleaned = $stmt->rowCount();
                
                // Enregistrer l'action
                logUserAction(
                    'cleanup_expired_sessions',
                    'users',
                    'Nettoyage des sessions expirées: ' . $sessions_cleaned . ' sessions supprimées',
                    null
                );
                
                showMessage('success', $sessions_cleaned . ' session(s) expirée(s) supprimée(s)');
                break;
        }
        
    } catch (Exception $e) {
        showMessage('error', $e->getMessage());
    }
}

// Récupérer les sessions actives
$sessions = $database->query(
    "SELECT s.*, u.username, u.nom, u.prenom, u.role,
            TIMESTAMPDIFF(MINUTE, s.last_activity, NOW()) as minutes_inactive
     FROM user_sessions s
     JOIN users u ON s.user_id = u.id
     ORDER BY s.last_activity DESC"
)->fetchAll();

// Statistiques
$stats = [];
$stats['total_sessions'] = count($sessions);
$stats['active_sessions'] = count(array_filter($sessions, function($s) { return $s['minutes_inactive'] <= 30; }));
$stats['idle_sessions'] = $stats['total_sessions'] - $stats['active_sessions'];

// Sessions par utilisateur
$sessions_by_user = [];
foreach ($sessions as $session) {
    $user_key = $session['user_id'];
    if (!isset($sessions_by_user[$user_key])) {
        $sessions_by_user[$user_key] = [
            'user' => $session,
            'count' => 0,
            'active' => 0
        ];
    }
    $sessions_by_user[$user_key]['count']++;
    if ($session['minutes_inactive'] <= 30) {
        $sessions_by_user[$user_key]['active']++;
    }
}

include '../../../includes/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">
        <i class="fas fa-desktop me-2"></i>
        Sessions Actives
    </h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <div class="btn-group me-2">
            <a href="../index.php" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-1"></i>
                Retour
            </a>
        </div>
        <div class="btn-group me-2">
            <button type="button" class="btn btn-outline-warning" onclick="refreshSessions()">
                <i class="fas fa-sync-alt me-1"></i>
                Actualiser
            </button>
        </div>
        <div class="btn-group">
            <button type="button" class="btn btn-outline-danger dropdown-toggle" data-bs-toggle="dropdown">
                <i class="fas fa-broom me-1"></i>
                Nettoyage
            </button>
            <ul class="dropdown-menu">
                <li>
                    <form method="POST" style="display: inline;">
                        <input type="hidden" name="action" value="cleanup_expired">
                        <button type="submit" class="dropdown-item" 
                                onclick="return confirm('Supprimer toutes les sessions expirées ?')">
                            <i class="fas fa-clock me-2"></i>Sessions expirées
                        </button>
                    </form>
                </li>
            </ul>
        </div>
    </div>
</div>

<!-- Statistiques -->
<div class="row mb-4">
    <div class="col-lg-4 col-md-6 mb-3">
        <div class="card text-white bg-primary">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h4><?php echo $stats['total_sessions']; ?></h4>
                        <p class="mb-0">Total sessions</p>
                    </div>
                    <div class="align-self-center">
                        <i class="fas fa-desktop fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-4 col-md-6 mb-3">
        <div class="card text-white bg-success">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h4><?php echo $stats['active_sessions']; ?></h4>
                        <p class="mb-0">Sessions actives</p>
                    </div>
                    <div class="align-self-center">
                        <i class="fas fa-circle fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-4 col-md-6 mb-3">
        <div class="card text-white bg-warning">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h4><?php echo $stats['idle_sessions']; ?></h4>
                        <p class="mb-0">Sessions inactives</p>
                    </div>
                    <div class="align-self-center">
                        <i class="fas fa-pause-circle fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-lg-8">
        <!-- Liste des sessions -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-list me-2"></i>
                    Sessions en cours
                </h5>
            </div>
            <div class="card-body">
                <?php if (!empty($sessions)): ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Utilisateur</th>
                                    <th>Session ID</th>
                                    <th>Adresse IP</th>
                                    <th>Navigateur</th>
                                    <th>Dernière activité</th>
                                    <th>Statut</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($sessions as $session): ?>
                                    <tr class="<?php echo $session['minutes_inactive'] > 30 ? 'table-warning' : ''; ?>">
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div>
                                                    <strong><?php echo htmlspecialchars($session['nom'] . ' ' . $session['prenom']); ?></strong>
                                                    <br><small class="text-muted">
                                                        @<?php echo htmlspecialchars($session['username']); ?>
                                                        <span class="badge bg-<?php 
                                                            echo $session['role'] === 'admin' ? 'danger' : 
                                                                ($session['role'] === 'directeur' ? 'warning' : 'info'); 
                                                        ?> ms-1">
                                                            <?php echo ucfirst($session['role']); ?>
                                                        </span>
                                                    </small>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <code><?php echo substr($session['id'], 0, 12); ?>...</code>
                                            <?php if ($session['id'] === session_id()): ?>
                                                <br><small class="text-success">
                                                    <i class="fas fa-user"></i> Votre session
                                                </small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php echo htmlspecialchars($session['ip_address']); ?>
                                        </td>
                                        <td>
                                            <small title="<?php echo htmlspecialchars($session['user_agent']); ?>">
                                                <?php echo htmlspecialchars(substr($session['user_agent'], 0, 30)); ?>...
                                            </small>
                                        </td>
                                        <td>
                                            <?php echo formatDateTime($session['last_activity']); ?>
                                            <br><small class="text-muted">
                                                Il y a <?php echo $session['minutes_inactive']; ?> min
                                            </small>
                                        </td>
                                        <td>
                                            <?php if ($session['minutes_inactive'] <= 5): ?>
                                                <span class="badge bg-success">
                                                    <i class="fas fa-circle"></i> Très actif
                                                </span>
                                            <?php elseif ($session['minutes_inactive'] <= 30): ?>
                                                <span class="badge bg-info">
                                                    <i class="fas fa-circle"></i> Actif
                                                </span>
                                            <?php else: ?>
                                                <span class="badge bg-warning">
                                                    <i class="fas fa-pause-circle"></i> Inactif
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($session['id'] !== session_id()): ?>
                                                <form method="POST" style="display: inline;">
                                                    <input type="hidden" name="action" value="kill_session">
                                                    <input type="hidden" name="session_id" value="<?php echo $session['id']; ?>">
                                                    <button type="submit" class="btn btn-outline-danger btn-sm" 
                                                            onclick="return confirm('Terminer cette session ?')"
                                                            title="Terminer la session">
                                                        <i class="fas fa-times"></i>
                                                    </button>
                                                </form>
                                            <?php else: ?>
                                                <span class="text-muted">Session actuelle</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="text-center py-4">
                        <i class="fas fa-desktop fa-3x text-muted mb-3"></i>
                        <p class="text-muted">Aucune session active</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <div class="col-lg-4">
        <!-- Sessions par utilisateur -->
        <div class="card">
            <div class="card-header">
                <h6 class="mb-0">
                    <i class="fas fa-users me-2"></i>
                    Sessions par utilisateur
                </h6>
            </div>
            <div class="card-body">
                <?php if (!empty($sessions_by_user)): ?>
                    <?php foreach ($sessions_by_user as $user_data): ?>
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <div>
                                <strong><?php echo htmlspecialchars($user_data['user']['nom'] . ' ' . $user_data['user']['prenom']); ?></strong>
                                <br><small class="text-muted">
                                    @<?php echo htmlspecialchars($user_data['user']['username']); ?>
                                </small>
                            </div>
                            <div class="text-end">
                                <span class="badge bg-primary"><?php echo $user_data['count']; ?> total</span>
                                <?php if ($user_data['active'] > 0): ?>
                                    <br><span class="badge bg-success"><?php echo $user_data['active']; ?> actives</span>
                                <?php endif; ?>
                                <?php if ($user_data['count'] > 1): ?>
                                    <br>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="action" value="kill_user_sessions">
                                        <input type="hidden" name="user_id" value="<?php echo $user_data['user']['user_id']; ?>">
                                        <button type="submit" class="btn btn-outline-danger btn-sm mt-1" 
                                                onclick="return confirm('Terminer toutes les sessions de cet utilisateur ?')"
                                                title="Terminer toutes les sessions">
                                            <i class="fas fa-times"></i> Tout terminer
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p class="text-muted text-center">Aucune session</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
function refreshSessions() {
    location.reload();
}

// Auto-refresh toutes les 30 secondes
setInterval(function() {
    // Ajouter un indicateur visuel de rafraîchissement
    const refreshBtn = document.querySelector('button[onclick="refreshSessions()"]');
    if (refreshBtn) {
        const icon = refreshBtn.querySelector('i');
        icon.classList.add('fa-spin');
        
        setTimeout(() => {
            location.reload();
        }, 500);
    }
}, 30000);

// Indicateur de dernière mise à jour
document.addEventListener('DOMContentLoaded', function() {
    const now = new Date();
    const timeString = now.toLocaleTimeString();
    
    // Ajouter l'heure de dernière mise à jour
    const header = document.querySelector('.border-bottom h1');
    if (header) {
        const updateTime = document.createElement('small');
        updateTime.className = 'text-muted ms-2';
        updateTime.textContent = `(Mis à jour à ${timeString})`;
        header.appendChild(updateTime);
    }
});
</script>

<?php include '../../../includes/footer.php'; ?>
