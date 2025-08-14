<?php
/**
 * Module Gestion des Utilisateurs - Voir un utilisateur
 * Application de gestion scolaire - République Démocratique du Congo
 */

require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../includes/functions.php';

// Vérifier l'authentification et les permissions
requireLogin();
if (!checkPermission('admin') && !checkPermission('users_view')) {
    showMessage('error', 'Accès refusé à ce module.');
    redirectTo('list.php');
}

$user_id = (int)($_GET['id'] ?? 0);

if (!$user_id) {
    showMessage('error', 'ID utilisateur manquant.');
    redirectTo('list.php');
}

// Récupérer les informations de l'utilisateur
$user = $database->query(
    "SELECT * FROM users WHERE id = ?",
    [$user_id]
)->fetch();

if (!$user) {
    showMessage('error', 'Utilisateur non trouvé.');
    redirectTo('list.php');
}

// Vérifier les permissions (un utilisateur peut voir son propre profil)
if (!checkPermission('admin') && $user_id != $_SESSION['user_id']) {
    showMessage('error', 'Vous ne pouvez voir que votre propre profil.');
    redirectTo('profile/');
}

$page_title = 'Profil de ' . $user['nom'] . ' ' . $user['prenom'];

// Statistiques de l'utilisateur
$stats = [];

// Nombre d'actions
$stmt = $database->query(
    "SELECT COUNT(*) as total FROM user_actions_log WHERE user_id = ?",
    [$user_id]
);
$stats['total_actions'] = $stmt->fetch()['total'];

// Sessions actives
$stmt = $database->query(
    "SELECT COUNT(*) as total FROM user_sessions WHERE user_id = ? AND last_activity >= DATE_SUB(NOW(), INTERVAL 30 MINUTE)",
    [$user_id]
);
$stats['active_sessions'] = $stmt->fetch()['total'];

// Dernières actions
$recent_actions = $database->query(
    "SELECT * FROM user_actions_log 
     WHERE user_id = ? 
     ORDER BY created_at DESC 
     LIMIT 10",
    [$user_id]
)->fetchAll();

// Sessions récentes
$recent_sessions = $database->query(
    "SELECT * FROM user_sessions 
     WHERE user_id = ? 
     ORDER BY last_activity DESC 
     LIMIT 5",
    [$user_id]
)->fetchAll();

// Enregistrer la consultation du profil
logUserAction(
    'view_user_profile',
    'users',
    'Consultation du profil de ' . $user['username'] . ' (' . $user['nom'] . ' ' . $user['prenom'] . ')',
    $user_id
);

include '../../includes/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">
        <i class="fas fa-user me-2"></i>
        Profil Utilisateur
    </h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <div class="btn-group me-2">
            <a href="list.php" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-1"></i>
                Retour à la liste
            </a>
        </div>
        <?php if (checkPermission('admin') || $user_id == $_SESSION['user_id']): ?>
            <div class="btn-group me-2">
                <a href="edit.php?id=<?php echo $user_id; ?>" class="btn btn-primary">
                    <i class="fas fa-edit me-1"></i>
                    Modifier
                </a>
            </div>
        <?php endif; ?>
        <?php if (checkPermission('admin') && $user_id != $_SESSION['user_id']): ?>
            <div class="btn-group">
                <button type="button" class="btn btn-outline-warning dropdown-toggle" data-bs-toggle="dropdown">
                    <i class="fas fa-cog me-1"></i>
                    Actions
                </button>
                <ul class="dropdown-menu">
                    <li><a class="dropdown-item" href="reset-password.php?id=<?php echo $user_id; ?>">
                        <i class="fas fa-key me-2"></i>Réinitialiser mot de passe
                    </a></li>
                    <li><a class="dropdown-item" href="toggle-status.php?id=<?php echo $user_id; ?>">
                        <i class="fas fa-<?php echo $user['status'] === 'actif' ? 'ban' : 'check'; ?> me-2"></i>
                        <?php echo $user['status'] === 'actif' ? 'Désactiver' : 'Activer'; ?>
                    </a></li>
                    <?php if ($user['compte_verrouille']): ?>
                        <li><a class="dropdown-item" href="unlock.php?id=<?php echo $user_id; ?>">
                            <i class="fas fa-unlock me-2"></i>Déverrouiller
                        </a></li>
                    <?php endif; ?>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item text-danger" href="delete.php?id=<?php echo $user_id; ?>" 
                           onclick="return confirm('Êtes-vous sûr de vouloir supprimer cet utilisateur ?')">
                        <i class="fas fa-trash me-2"></i>Supprimer
                    </a></li>
                </ul>
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="row">
    <div class="col-lg-4">
        <!-- Carte profil -->
        <div class="card mb-4">
            <div class="card-body text-center">
                <div class="avatar-xl mb-3">
                    <?php if ($user['photo']): ?>
                        <img src="<?php echo htmlspecialchars($user['photo']); ?>" 
                             alt="Avatar" class="rounded-circle" width="120" height="120">
                    <?php else: ?>
                        <div class="bg-secondary rounded-circle d-flex align-items-center justify-content-center mx-auto" 
                             style="width: 120px; height: 120px;">
                            <i class="fas fa-user text-white fa-3x"></i>
                        </div>
                    <?php endif; ?>
                </div>
                
                <h4><?php echo htmlspecialchars($user['nom'] . ' ' . $user['prenom']); ?></h4>
                <p class="text-muted">@<?php echo htmlspecialchars($user['username']); ?></p>
                
                <div class="mb-3">
                    <span class="badge bg-<?php 
                        echo $user['role'] === 'admin' ? 'danger' : 
                            ($user['role'] === 'directeur' ? 'warning' : 
                            ($user['role'] === 'enseignant' ? 'primary' : 'info')); 
                    ?> fs-6">
                        <?php echo ucfirst($user['role']); ?>
                    </span>
                    <span class="badge bg-<?php echo $user['status'] === 'actif' ? 'success' : 'secondary'; ?> fs-6">
                        <?php echo ucfirst($user['status']); ?>
                    </span>
                </div>
                
                <?php if ($user['compte_verrouille']): ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-lock me-2"></i>
                        Compte verrouillé
                        <?php if ($user['date_verrouillage']): ?>
                            <br><small>Depuis le <?php echo formatDateTime($user['date_verrouillage']); ?></small>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
                
                <div class="row text-center">
                    <div class="col-6">
                        <div class="border-end">
                            <h5 class="mb-0"><?php echo $stats['total_actions']; ?></h5>
                            <small class="text-muted">Actions</small>
                        </div>
                    </div>
                    <div class="col-6">
                        <h5 class="mb-0"><?php echo $stats['active_sessions']; ?></h5>
                        <small class="text-muted">Sessions actives</small>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Informations de connexion -->
        <div class="card">
            <div class="card-header">
                <h6 class="mb-0">
                    <i class="fas fa-info-circle me-2"></i>
                    Informations de connexion
                </h6>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <label class="form-label text-muted">Première connexion</label>
                    <div>
                        <?php if ($user['created_at']): ?>
                            <?php echo formatDateTime($user['created_at']); ?>
                        <?php else: ?>
                            <span class="text-muted">Non disponible</span>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="mb-3">
                    <label class="form-label text-muted">Dernière connexion</label>
                    <div>
                        <?php if ($user['derniere_connexion']): ?>
                            <?php echo formatDateTime($user['derniere_connexion']); ?>
                        <?php else: ?>
                            <span class="text-muted">Jamais connecté</span>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="mb-0">
                    <label class="form-label text-muted">Tentatives de connexion</label>
                    <div>
                        <?php echo $user['tentatives_connexion'] ?? 0; ?> tentatives
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-lg-8">
        <!-- Informations personnelles -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-user me-2"></i>
                    Informations personnelles
                </h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label text-muted">Nom complet</label>
                        <div><?php echo htmlspecialchars($user['nom'] . ' ' . $user['prenom']); ?></div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label text-muted">Email</label>
                        <div>
                            <?php if ($user['email']): ?>
                                <a href="mailto:<?php echo htmlspecialchars($user['email']); ?>">
                                    <?php echo htmlspecialchars($user['email']); ?>
                                </a>
                            <?php else: ?>
                                <span class="text-muted">Non renseigné</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label text-muted">Téléphone</label>
                        <div>
                            <?php if ($user['telephone']): ?>
                                <a href="tel:<?php echo htmlspecialchars($user['telephone']); ?>">
                                    <?php echo htmlspecialchars($user['telephone']); ?>
                                </a>
                            <?php else: ?>
                                <span class="text-muted">Non renseigné</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label text-muted">Date de naissance</label>
                        <div>
                            <?php if ($user['date_naissance']): ?>
                                <?php echo formatDate($user['date_naissance']); ?>
                                <small class="text-muted">
                                    (<?php echo calculateAge($user['date_naissance']); ?> ans)
                                </small>
                            <?php else: ?>
                                <span class="text-muted">Non renseignée</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label text-muted">Genre</label>
                        <div>
                            <?php if ($user['genre']): ?>
                                <?php echo $user['genre'] === 'M' ? 'Masculin' : 'Féminin'; ?>
                            <?php else: ?>
                                <span class="text-muted">Non spécifié</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label text-muted">Compte créé le</label>
                        <div><?php echo formatDateTime($user['created_at']); ?></div>
                    </div>
                    <?php if ($user['adresse']): ?>
                        <div class="col-12 mb-3">
                            <label class="form-label text-muted">Adresse</label>
                            <div><?php echo nl2br(htmlspecialchars($user['adresse'])); ?></div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Onglets pour historique et sessions -->
        <div class="card">
            <div class="card-header">
                <ul class="nav nav-tabs card-header-tabs" id="userTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="actions-tab" data-bs-toggle="tab" 
                                data-bs-target="#actions" type="button" role="tab">
                            <i class="fas fa-history me-2"></i>
                            Actions récentes
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="sessions-tab" data-bs-toggle="tab" 
                                data-bs-target="#sessions" type="button" role="tab">
                            <i class="fas fa-desktop me-2"></i>
                            Sessions
                        </button>
                    </li>
                </ul>
            </div>
            <div class="card-body">
                <div class="tab-content" id="userTabsContent">
                    <!-- Actions récentes -->
                    <div class="tab-pane fade show active" id="actions" role="tabpanel">
                        <?php if (!empty($recent_actions)): ?>
                            <div class="list-group list-group-flush">
                                <?php foreach ($recent_actions as $action): ?>
                                    <div class="list-group-item border-0 px-0">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <div>
                                                <h6 class="mb-1">
                                                    <i class="fas fa-<?php echo getActionIcon($action['action']); ?> me-2"></i>
                                                    <?php echo getActionLabel($action['action']); ?>
                                                </h6>
                                                <p class="mb-1"><?php echo htmlspecialchars($action['details']); ?></p>
                                                <small class="text-muted">
                                                    Module: <?php echo ucfirst($action['module']); ?>
                                                    <?php if ($action['ip_address']): ?>
                                                        | IP: <?php echo htmlspecialchars($action['ip_address']); ?>
                                                    <?php endif; ?>
                                                </small>
                                            </div>
                                            <small class="text-muted">
                                                <?php echo formatDateTime($action['created_at']); ?>
                                            </small>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <div class="text-center mt-3">
                                <a href="logs/?user_id=<?php echo $user_id; ?>" class="btn btn-outline-primary">
                                    <i class="fas fa-history me-1"></i>
                                    Voir tout l'historique
                                </a>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-4">
                                <i class="fas fa-history fa-3x text-muted mb-3"></i>
                                <p class="text-muted">Aucune action enregistrée</p>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Sessions -->
                    <div class="tab-pane fade" id="sessions" role="tabpanel">
                        <?php if (!empty($recent_sessions)): ?>
                            <div class="table-responsive">
                                <table class="table table-sm">
                                    <thead>
                                        <tr>
                                            <th>Session ID</th>
                                            <th>Adresse IP</th>
                                            <th>Navigateur</th>
                                            <th>Dernière activité</th>
                                            <th>Statut</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($recent_sessions as $session): ?>
                                            <tr>
                                                <td>
                                                    <code><?php echo substr($session['id'], 0, 8); ?>...</code>
                                                </td>
                                                <td><?php echo htmlspecialchars($session['ip_address']); ?></td>
                                                <td>
                                                    <small><?php echo htmlspecialchars(substr($session['user_agent'], 0, 50)); ?>...</small>
                                                </td>
                                                <td><?php echo formatDateTime($session['last_activity']); ?></td>
                                                <td>
                                                    <?php 
                                                    $is_active = strtotime($session['last_activity']) > (time() - 1800); // 30 minutes
                                                    ?>
                                                    <span class="badge bg-<?php echo $is_active ? 'success' : 'secondary'; ?>">
                                                        <?php echo $is_active ? 'Active' : 'Expirée'; ?>
                                                    </span>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-4">
                                <i class="fas fa-desktop fa-3x text-muted mb-3"></i>
                                <p class="text-muted">Aucune session enregistrée</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
// Fonctions helper
function getActionIcon($action) {
    $icons = [
        'create_user' => 'user-plus',
        'update_user' => 'user-edit',
        'delete_user' => 'user-times',
        'login' => 'sign-in-alt',
        'logout' => 'sign-out-alt',
        'change_password' => 'key',
        'update_profile' => 'user-cog',
        'view_user_profile' => 'eye'
    ];
    return $icons[$action] ?? 'info-circle';
}

function getActionLabel($action) {
    $labels = [
        'create_user' => 'Utilisateur créé',
        'update_user' => 'Utilisateur modifié',
        'delete_user' => 'Utilisateur supprimé',
        'login' => 'Connexion',
        'logout' => 'Déconnexion',
        'change_password' => 'Mot de passe changé',
        'update_profile' => 'Profil mis à jour',
        'view_user_profile' => 'Profil consulté'
    ];
    return $labels[$action] ?? 'Action inconnue';
}

function calculateAge($birthdate) {
    $birth = new DateTime($birthdate);
    $today = new DateTime();
    return $birth->diff($today)->y;
}

include '../../includes/footer.php';
?>
