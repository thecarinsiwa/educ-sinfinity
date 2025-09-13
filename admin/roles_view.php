<?php
/**
 * Administration - Voir un rôle
 * Application de gestion scolaire - République Démocratique du Congo
 */

require_once '../config/config.php';

// Vérification de session robuste
require_once '../session_check.php';
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/permissions.php';
require_once '../config/module-permissions-structure.php';

// Vérifier les permissions
if (!checkUserPermission('users', 'read') && !checkPermission('admin')) {
    showMessage('error', 'Accès refusé à ce module.');
    redirectTo('../dashboard.php');
}

$page_title = 'Administration - Voir un rôle';

// Récupérer l'ID du rôle
$role_id = (int)($_GET['id'] ?? 0);

if ($role_id <= 0) {
    showMessage('error', 'ID de rôle invalide.');
    redirectTo('roles.php');
}

// Récupérer les informations du rôle
try {
    $role = $database->query(
        "SELECT * FROM roles WHERE id = ?",
        [$role_id]
    )->fetch();
    
    if (!$role) {
        showMessage('error', 'Rôle non trouvé.');
        redirectTo('roles.php');
    }
} catch (Exception $e) {
    showMessage('error', 'Erreur lors de la récupération du rôle: ' . $e->getMessage());
    redirectTo('roles.php');
}

// Compter le nombre d'utilisateurs avec ce rôle
try {
    $user_count = $database->query(
        "SELECT COUNT(*) as count FROM users WHERE role_id = ?",
        [$role_id]
    )->fetch()['count'];
} catch (Exception $e) {
    $user_count = 0;
}

// Utiliser la nouvelle structure des permissions
$module_permissions = getModulePermissionsStructure();
$available_actions = getModuleAvailableActions();

// Décoder les permissions existantes (compatible avec ancien et nouveau format)
$existing_permissions = [];
if ($role && $role['permissions']) {
    $decoded_permissions = json_decode($role['permissions'], true);
    if (is_array($decoded_permissions)) {
        foreach ($decoded_permissions as $module => $module_data) {
            if (isset($module_data['pages'])) {
                foreach ($module_data['pages'] as $page => $page_data) {
                    if (isset($page_data['permissions'])) {
                        // Page directe avec permissions (nouveau format)
                        foreach ($page_data['permissions'] as $action) {
                            $existing_permissions[] = $module . ':' . $page . ':' . $action;
                        }
                    } elseif (isset($page_data['pages'])) {
                        // Sous-pages (ancien format hiérarchique)
                        foreach ($page_data['pages'] as $subpage => $subpage_data) {
                            if (isset($subpage_data['permissions'])) {
                                foreach ($subpage_data['permissions'] as $action) {
                                    // Pour les sous-pages, on utilise le format page/subpage
                                    $existing_permissions[] = $module . ':' . $page . '/' . $subpage . ':' . $action;
                                }
                            }
                        }
                    }
                }
            }
        }
    }
}

include '../includes/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">
        <i class="fas fa-user-tag me-2"></i>
        Détails du rôle : <?php echo htmlspecialchars($role['nom']); ?>
    </h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <div class="btn-group me-2">
            <a href="roles.php" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-1"></i>
                Retour à la liste
            </a>
            <a href="roles_edit.php?id=<?php echo $role_id; ?>" class="btn btn-primary">
                <i class="fas fa-edit me-1"></i>
                Modifier le rôle
            </a>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-lg-8">
        <!-- Informations générales -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-info-circle me-2"></i>
                    Informations générales
                </h5>
            </div>
            <div class="card-body">
                    <!-- Section de débogage (à supprimer en production) -->
                    <?php if (isset($_GET['debug'])): ?>
                    <div class="alert alert-info">
                        <h6><i class="fas fa-bug me-2"></i>Mode débogage</h6>
                        <p><strong>Permissions JSON brutes :</strong></p>
                        <pre class="small"><?php echo htmlspecialchars($role['permissions']); ?></pre>
                        <p><strong>Permissions décodées :</strong></p>
                        <pre class="small"><?php print_r($existing_permissions); ?></pre>
                    </div>
                    <?php endif; ?>
                    
                <div class="row">
                    <div class="col-md-6">
                        <table class="table table-borderless">
                            <tr>
                                    <td><strong>Nom :</strong></td>
                                <td><?php echo htmlspecialchars($role['nom']); ?></td>
                            </tr>
                            <tr>
                                <td><strong>Statut :</strong></td>
                                <td>
                                        <span class="badge bg-<?php echo $role['actif'] ? 'success' : 'secondary'; ?>">
                                        <?php echo $role['actif'] ? 'Actif' : 'Inactif'; ?>
                                    </span>
                                </td>
                            </tr>
                            <tr>
                                    <td><strong>Utilisateurs :</strong></td>
                                    <td>
                                        <span class="badge bg-info"><?php echo $user_count; ?> utilisateur(s)</span>
                                    </td>
                            </tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <table class="table table-borderless">
                            <tr>
                                <td><strong>Créé le :</strong></td>
                                <td><?php echo date('d/m/Y H:i', strtotime($role['date_creation'])); ?></td>
                            </tr>
                            <?php if ($role['date_modification']): ?>
                            <tr>
                                <td><strong>Modifié le :</strong></td>
                                <td><?php echo date('d/m/Y H:i', strtotime($role['date_modification'])); ?></td>
                            </tr>
                            <?php endif; ?>
                        </table>
                    </div>
                </div>
                
                <?php if ($role['description']): ?>
                <div class="mt-3">
                    <h6>Description :</h6>
                    <p class="text-muted"><?php echo nl2br(htmlspecialchars($role['description'])); ?></p>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Permissions détaillées -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-shield-alt me-2"></i>
                    Permissions détaillées
                </h5>
            </div>
            <div class="card-body">
                    <div class="accordion" id="permissionsAccordion">
                        <?php
                        $module_index = 0;
                        foreach ($module_permissions as $module_key => $module): 
                            // Vérifier si ce module a des permissions
                            $has_permissions = false;
                            
                            // Vérifier les pages
                            foreach ($module['pages'] as $page_key => $page_actions) {
                                foreach ($page_actions as $action) {
                                    if (in_array($module_key . ':' . $page_key . ':' . $action, $existing_permissions)) {
                                        $has_permissions = true;
                                        break 2;
                                    }
                                }
                            }
                            
                            if (!$has_permissions) continue;
                        ?>
                        <div class="accordion-item">
                            <h2 class="accordion-header" id="heading<?php echo $module_index; ?>">
                                <button class="accordion-button <?php echo $module_index === 0 ? '' : 'collapsed'; ?>" 
                                        type="button" 
                                        data-bs-toggle="collapse" 
                                        data-bs-target="#collapse<?php echo $module_index; ?>" 
                                        aria-expanded="<?php echo $module_index === 0 ? 'true' : 'false'; ?>" 
                                        aria-controls="collapse<?php echo $module_index; ?>">
                                    <i class="<?php echo $module['icon']; ?> me-2"></i>
                                    <strong><?php echo $module['name']; ?></strong>
                                    <span class="badge bg-secondary ms-2"><?php echo count($module['pages']); ?> pages</span>
                                </button>
                            </h2>
                            <div id="collapse<?php echo $module_index; ?>" 
                                 class="accordion-collapse collapse <?php echo $module_index === 0 ? 'show' : ''; ?>" 
                                 aria-labelledby="heading<?php echo $module_index; ?>" 
                                 data-bs-parent="#permissionsAccordion">
                                <div class="accordion-body">
                                    <div class="alert alert-info mb-3">
                                        <small>
                                            <i class="<?php echo $module['icon']; ?> me-1"></i>
                                            <strong><?php echo $module['description']; ?></strong>
                                        </small>
                                    </div>
                                    
                                    <div class="row">
                                        <?php foreach ($module['pages'] as $page_key => $page_actions): ?>
                                            <?php
                                            // Vérifier si cette page a des permissions
                                            $page_has_permissions = false;
                                            $page_permissions = [];
                                            
                                            foreach ($page_actions as $action) {
                                                if (in_array($module_key . ':' . $page_key . ':' . $action, $existing_permissions)) {
                                                    $page_has_permissions = true;
                                                    $page_permissions[] = $action;
                                                }
                                            }
                                            
                                            if (!$page_has_permissions) continue;
                                            ?>
                                            <div class="col-lg-4 col-md-6 mb-3">
                                                <div class="card border-light">
                                                    <div class="card-header bg-light">
                                                        <h6 class="mb-0 text-truncate" title="<?php echo ucwords(str_replace(['/', '_', '-'], [' ', ' ', ' '], $page_key)); ?>">
                                                            <i class="fas fa-file-alt me-1"></i>
                                                            <?php echo ucwords(str_replace(['/', '_', '-'], [' ', ' ', ' '], $page_key)); ?>
                                                        </h6>
                                                    </div>
                                                    <div class="card-body p-2">
                                                        <?php foreach ($page_permissions as $action): ?>
                                                            <div class="form-check form-check-sm">
                                                                <input class="form-check-input" type="checkbox" checked disabled>
                                                                <label class="form-check-label small text-success">
                                                                    <i class="fas fa-check me-1"></i>
                                                                    <span class="badge bg-<?php 
                                                                        echo $action === 'read' ? 'primary' : 
                                                                            ($action === 'create' ? 'success' : 
                                                                            ($action === 'edit' ? 'warning' : 
                                                                            ($action === 'delete' ? 'danger' : 
                                                                            ($action === 'export' ? 'info' : 
                                                                            ($action === 'import' ? 'secondary' : 
                                                                            ($action === 'print' ? 'dark' : 'light')))))); 
                                                                    ?> me-1">
                                                                        <?php echo $available_actions[$action] ?? ucfirst($action); ?>
                                                                    </span>
                                                                </label>
                                                            </div>
                                                        <?php endforeach; ?>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php $module_index++; ?>
                        <?php endforeach; ?>
                    </div>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <!-- Statistiques -->
        <div class="card mb-4">
            <div class="card-header">
                <h6 class="mb-0">
                    <i class="fas fa-chart-bar me-2"></i>
                    Statistiques
                </h6>
            </div>
            <div class="card-body">
                <div class="row text-center">
                    <div class="col-6">
                        <div class="border-end">
                            <h4 class="text-primary"><?php echo count($existing_permissions); ?></h4>
                            <small class="text-muted">Permissions</small>
                        </div>
                    </div>
                    <div class="col-6">
                        <h4 class="text-info"><?php echo $user_count; ?></h4>
                        <small class="text-muted">Utilisateurs</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Actions disponibles -->
        <div class="card mb-4">
            <div class="card-header">
                <h6 class="mb-0">
                    <i class="fas fa-tags me-2"></i>
                    Actions disponibles
                </h6>
            </div>
            <div class="card-body">
                <ul class="list-unstyled small">
                    <li class="mb-1"><span class="badge bg-primary me-1">Lire</span> Consulter les informations</li>
                    <li class="mb-1"><span class="badge bg-success me-1">Créer</span> Ajouter de nouveaux éléments</li>
                    <li class="mb-1"><span class="badge bg-warning me-1">Modifier</span> Modifier les éléments existants</li>
                    <li class="mb-1"><span class="badge bg-danger me-1">Supprimer</span> Supprimer les éléments</li>
                    <li class="mb-1"><span class="badge bg-info me-1">Exporter</span> Exporter les données</li>
                    <li class="mb-1"><span class="badge bg-secondary me-1">Importer</span> Importer des données</li>
                    <li class="mb-1"><span class="badge bg-dark me-1">Imprimer</span> Imprimer des documents</li>
                    <li class="mb-1"><span class="badge bg-light text-dark me-1">Admin</span> Administration système</li>
                </ul>
            </div>
        </div>

        <!-- Actions rapides -->
        <div class="card mb-4">
            <div class="card-header">
                <h6 class="mb-0">
                    <i class="fas fa-bolt me-2"></i>
                    Actions rapides
                </h6>
            </div>
            <div class="card-body">
                <div class="d-grid gap-2">
                    <a href="roles_edit.php?id=<?php echo $role_id; ?>" class="btn btn-primary btn-sm">
                        <i class="fas fa-edit me-1"></i>
                        Modifier le rôle
                    </a>
                    <a href="roles_delete.php?id=<?php echo $role_id; ?>" class="btn btn-outline-danger btn-sm" 
                       onclick="return confirm('Êtes-vous sûr de vouloir supprimer ce rôle ?')">
                        <i class="fas fa-trash me-1"></i>
                        Supprimer le rôle
                    </a>
                </div>
            </div>
        </div>

        <!-- Utilisateurs avec ce rôle -->
        <?php if ($user_count > 0): ?>
        <div class="card">
            <div class="card-header">
                <h6 class="mb-0">
                    <i class="fas fa-users me-2"></i>
                    Utilisateurs avec ce rôle
                </h6>
            </div>
            <div class="card-body">
                <p class="text-muted small">
                    <i class="fas fa-info-circle me-1"></i>
                    Ce rôle est utilisé par <?php echo $user_count; ?> utilisateur(s).
                </p>
                <a href="users.php?role=<?php echo $role_id; ?>" class="btn btn-outline-primary btn-sm">
                    <i class="fas fa-eye me-1"></i>
                    Voir les utilisateurs
                </a>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
