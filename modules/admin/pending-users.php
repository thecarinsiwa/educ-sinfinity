<?php
/**
 * Gestion des comptes en attente d'activation
 * Application de gestion scolaire - République Démocratique du Congo
 */

require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../includes/functions.php';

// Vérifier l'authentification et les permissions
requireLogin();
if (!checkPermission('admin')) {
    showMessage('error', 'Accès refusé à ce module.');
    redirectTo('../../dashboard.php');
}

$page_title = 'Comptes en Attente d\'Activation';

// Traitement des actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = sanitizeInput($_POST['action'] ?? '');
    $user_id = (int)($_POST['user_id'] ?? 0);
    
    try {
        switch ($action) {
            case 'activate':
                if (!$user_id) {
                    throw new Exception('ID utilisateur manquant');
                }
                
                if (activateUser($user_id, $_SESSION['user_id'])) {
                    showMessage('success', 'Compte activé avec succès');
                } else {
                    throw new Exception('Erreur lors de l\'activation du compte');
                }
                break;
                
            case 'reject':
                if (!$user_id) {
                    throw new Exception('ID utilisateur manquant');
                }
                
                // Récupérer les informations avant suppression
                $user = $database->query(
                    "SELECT username, nom, prenom FROM users WHERE id = ?",
                    [$user_id]
                )->fetch();
                
                if (!$user) {
                    throw new Exception('Utilisateur non trouvé');
                }
                
                // Supprimer le compte
                $database->query("DELETE FROM users WHERE id = ?", [$user_id]);
                
                // Enregistrer l'action
                logUserAction(
                    'reject_user_signup',
                    'users',
                    'Inscription rejetée pour: ' . $user['username'] . ' (' . $user['nom'] . ' ' . $user['prenom'] . ')',
                    $user_id
                );
                
                showMessage('success', 'Inscription rejetée et compte supprimé');
                break;
                
            case 'bulk_activate':
                $user_ids = $_POST['user_ids'] ?? [];
                if (empty($user_ids)) {
                    throw new Exception('Aucun utilisateur sélectionné');
                }
                
                $activated = 0;
                foreach ($user_ids as $id) {
                    if (activateUser((int)$id, $_SESSION['user_id'])) {
                        $activated++;
                    }
                }
                
                showMessage('success', "$activated compte(s) activé(s) avec succès");
                break;
                
            case 'bulk_reject':
                $user_ids = $_POST['user_ids'] ?? [];
                if (empty($user_ids)) {
                    throw new Exception('Aucun utilisateur sélectionné');
                }
                
                $rejected = 0;
                foreach ($user_ids as $id) {
                    $user = $database->query(
                        "SELECT username, nom, prenom FROM users WHERE id = ?",
                        [(int)$id]
                    )->fetch();
                    
                    if ($user) {
                        $database->query("DELETE FROM users WHERE id = ?", [(int)$id]);
                        
                        logUserAction(
                            'reject_user_signup',
                            'users',
                            'Inscription rejetée pour: ' . $user['username'] . ' (' . $user['nom'] . ' ' . $user['prenom'] . ')',
                            (int)$id
                        );
                        
                        $rejected++;
                    }
                }
                
                showMessage('success', "$rejected inscription(s) rejetée(s)");
                break;
        }
        
    } catch (Exception $e) {
        showMessage('error', $e->getMessage());
    }
}

// Récupérer les comptes en attente
$pending_users = $database->query(
    "SELECT id, username, nom, prenom, email, telephone, role, created_at
     FROM users 
     WHERE status = 'inactif' 
     ORDER BY created_at DESC"
)->fetchAll();

// Statistiques
$stats = [];
$stats['total_pending'] = count($pending_users);
$stats['today_signups'] = $database->query(
    "SELECT COUNT(*) as total FROM users WHERE status = 'inactif' AND DATE(created_at) = CURDATE()"
)->fetch()['total'];

include '../../includes/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">
        <i class="fas fa-user-clock me-2"></i>
        Comptes en Attente d'Activation
    </h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <div class="btn-group me-2">
            <a href="../users/" class="btn btn-outline-secondary">
                <i class="fas fa-users me-1"></i>
                Tous les utilisateurs
            </a>
        </div>
        <div class="btn-group">
            <button type="button" class="btn btn-outline-primary" onclick="refreshPage()">
                <i class="fas fa-sync-alt me-1"></i>
                Actualiser
            </button>
        </div>
    </div>
</div>

<!-- Statistiques -->
<div class="row mb-4">
    <div class="col-lg-6 col-md-6 mb-3">
        <div class="card text-white bg-warning">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h4><?php echo $stats['total_pending']; ?></h4>
                        <p class="mb-0">Comptes en attente</p>
                    </div>
                    <div class="align-self-center">
                        <i class="fas fa-user-clock fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-6 col-md-6 mb-3">
        <div class="card text-white bg-info">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h4><?php echo $stats['today_signups']; ?></h4>
                        <p class="mb-0">Inscriptions aujourd'hui</p>
                    </div>
                    <div class="align-self-center">
                        <i class="fas fa-user-plus fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Liste des comptes en attente -->
<div class="card">
    <div class="card-header">
        <div class="d-flex justify-content-between align-items-center">
            <h5 class="mb-0">
                <i class="fas fa-list me-2"></i>
                Comptes en attente
                <?php if ($stats['total_pending'] > 0): ?>
                    <span class="badge bg-warning"><?php echo $stats['total_pending']; ?></span>
                <?php endif; ?>
            </h5>
            
            <?php if (!empty($pending_users)): ?>
                <div class="btn-group">
                    <button type="button" class="btn btn-success btn-sm" onclick="bulkActivate()">
                        <i class="fas fa-check me-1"></i>
                        Activer sélectionnés
                    </button>
                    <button type="button" class="btn btn-danger btn-sm" onclick="bulkReject()">
                        <i class="fas fa-times me-1"></i>
                        Rejeter sélectionnés
                    </button>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <div class="card-body">
        <?php if (!empty($pending_users)): ?>
            <form id="bulkForm" method="POST">
                <input type="hidden" name="action" id="bulkAction">
                
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>
                                    <input type="checkbox" id="selectAll" onchange="toggleSelectAll()">
                                </th>
                                <th>Utilisateur</th>
                                <th>Nom complet</th>
                                <th>Email</th>
                                <th>Téléphone</th>
                                <th>Rôle souhaité</th>
                                <th>Date d'inscription</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($pending_users as $user): ?>
                                <tr>
                                    <td>
                                        <input type="checkbox" name="user_ids[]" value="<?php echo $user['id']; ?>" class="user-checkbox">
                                    </td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="avatar-sm me-2">
                                                <div class="bg-warning rounded-circle d-flex align-items-center justify-content-center" 
                                                     style="width: 32px; height: 32px;">
                                                    <i class="fas fa-user text-white"></i>
                                                </div>
                                            </div>
                                            <div>
                                                <strong><?php echo htmlspecialchars($user['username']); ?></strong>
                                                <br><small class="text-muted">
                                                    ID: <?php echo $user['id']; ?>
                                                </small>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <?php echo htmlspecialchars($user['nom'] . ' ' . $user['prenom']); ?>
                                    </td>
                                    <td>
                                        <?php if ($user['email']): ?>
                                            <a href="mailto:<?php echo htmlspecialchars($user['email']); ?>">
                                                <?php echo htmlspecialchars($user['email']); ?>
                                            </a>
                                        <?php else: ?>
                                            <span class="text-muted">Non renseigné</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($user['telephone']): ?>
                                            <a href="tel:<?php echo htmlspecialchars($user['telephone']); ?>">
                                                <?php echo htmlspecialchars($user['telephone']); ?>
                                            </a>
                                        <?php else: ?>
                                            <span class="text-muted">Non renseigné</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?php 
                                            echo $user['role'] === 'enseignant' ? 'primary' : 
                                                ($user['role'] === 'secretaire' ? 'info' : 
                                                ($user['role'] === 'comptable' ? 'success' : 'secondary')); 
                                        ?>">
                                            <?php echo ucfirst($user['role']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php echo formatDateTime($user['created_at']); ?>
                                        <br><small class="text-muted">
                                            Il y a <?php echo timeAgo($user['created_at']); ?>
                                        </small>
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="action" value="activate">
                                                <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                <button type="submit" class="btn btn-success" 
                                                        onclick="return confirm('Activer ce compte ?')"
                                                        title="Activer le compte">
                                                    <i class="fas fa-check"></i>
                                                </button>
                                            </form>
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="action" value="reject">
                                                <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                <button type="submit" class="btn btn-danger" 
                                                        onclick="return confirm('Rejeter cette inscription ? Le compte sera supprimé définitivement.')"
                                                        title="Rejeter l'inscription">
                                                    <i class="fas fa-times"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </form>
        <?php else: ?>
            <div class="text-center py-5">
                <i class="fas fa-user-check fa-4x text-muted mb-3"></i>
                <h4 class="text-muted">Aucun compte en attente</h4>
                <p class="text-muted">
                    Tous les comptes ont été traités ou aucune nouvelle inscription n'a été effectuée.
                </p>
                <a href="../../auth/signup.php" class="btn btn-outline-primary" target="_blank">
                    <i class="fas fa-external-link-alt me-1"></i>
                    Voir la page d'inscription
                </a>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
function toggleSelectAll() {
    const selectAll = document.getElementById('selectAll');
    const checkboxes = document.querySelectorAll('.user-checkbox');
    
    checkboxes.forEach(checkbox => {
        checkbox.checked = selectAll.checked;
    });
}

function bulkActivate() {
    const checkedBoxes = document.querySelectorAll('.user-checkbox:checked');
    if (checkedBoxes.length === 0) {
        alert('Veuillez sélectionner au moins un utilisateur');
        return;
    }
    
    if (confirm(`Activer ${checkedBoxes.length} compte(s) sélectionné(s) ?`)) {
        document.getElementById('bulkAction').value = 'bulk_activate';
        document.getElementById('bulkForm').submit();
    }
}

function bulkReject() {
    const checkedBoxes = document.querySelectorAll('.user-checkbox:checked');
    if (checkedBoxes.length === 0) {
        alert('Veuillez sélectionner au moins un utilisateur');
        return;
    }
    
    if (confirm(`ATTENTION: Rejeter ${checkedBoxes.length} inscription(s) ?\n\nCes comptes seront supprimés définitivement !`)) {
        document.getElementById('bulkAction').value = 'bulk_reject';
        document.getElementById('bulkForm').submit();
    }
}

function refreshPage() {
    location.reload();
}

// Auto-refresh toutes les 2 minutes
setInterval(function() {
    location.reload();
}, 120000);
</script>

<?php include '../../includes/footer.php'; ?>
