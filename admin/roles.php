<?php
/**
 * Administration - Gestion des Rôles (Version Actualisée - Sans Animations)
 * Application de gestion scolaire - République Démocratique du Congo
 * 
 * Fonctionnalités ajoutées :
 * - Recherche et filtrage avancés
 * - Actions en lot
 * - Modèles de permissions prédéfinis
 * - Export/Import des rôles
 * - Interface utilisateur améliorée
 * - Performance optimisée (animations supprimées)
 * 
 * Dernière mise à jour : <?php echo date('d/m/Y H:i'); ?>
 */

require_once '../config/config.php';

// Vérification de session robuste
require_once '../session_check.php';
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/permissions.php';
require_once '../includes/ui-permissions.php';
require_once '../config/detailed-permissions.php';

// Vérifier les permissions
if (!checkUserPermission('users', 'read') && !checkPermission('admin')) {
    showMessage('error', 'Accès refusé à ce module.');
    redirectTo('../dashboard.php');
}

$page_title = 'Administration - Gestion des Rôles';

// Variables pour la gestion des rôles

$errors = [];

// Paramètres de recherche et filtrage
$search = sanitizeInput($_GET['search'] ?? '');
$status_filter = sanitizeInput($_GET['status'] ?? '');
$sort_by = sanitizeInput($_GET['sort'] ?? 'nom');
$sort_order = sanitizeInput($_GET['order'] ?? 'ASC');


// Traitement des actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = sanitizeInput($_POST['action'] ?? '');
    
    try {
        switch ($action) {
                
                
            case 'toggle_status':
                $role_id = (int)($_POST['role_id'] ?? 0);
                
                if (!$role_id) {
                    throw new Exception('ID du rôle requis');
                }
                
                // Récupérer le rôle
                $role = $database->query(
                    "SELECT * FROM roles WHERE id = ?",
                    [$role_id]
                )->fetch();
                
                if (!$role) {
                    throw new Exception('Rôle non trouvé');
                }
                
                // Changer le statut
                $new_status = $role['actif'] ? 0 : 1;
                
                $database->execute(
                    "UPDATE roles SET actif = ? WHERE id = ?",
                    [$new_status, $role_id]
                );
                
                // Enregistrer l'action
                if (function_exists('logUserAction')) {
                    logUserAction(
                        'toggle_role_status',
                        'admin',
                        'Statut changé pour le rôle: ' . $role['nom'] . ' - Nouveau statut: ' . ($new_status ? 'actif' : 'inactif'),
                        $role_id
                    );
                }
                
                showMessage('success', 'Statut du rôle mis à jour');
                break;
                
            case 'bulk_toggle_status':
                $role_ids = $_POST['role_ids'] ?? [];
                $new_status = (int)($_POST['new_status'] ?? 0);
                
                if (empty($role_ids)) {
                    throw new Exception('Aucun rôle sélectionné');
                }
                
                $placeholders = str_repeat('?,', count($role_ids) - 1) . '?';
                $database->execute(
                    "UPDATE roles SET actif = ? WHERE id IN ({$placeholders})",
                    array_merge([$new_status], $role_ids)
                );
                
                // Enregistrer l'action
                if (function_exists('logUserAction')) {
                    logUserAction(
                        'bulk_toggle_roles',
                        'admin',
                        'Statut modifié pour ' . count($role_ids) . ' rôle(s) - Nouveau statut: ' . ($new_status ? 'actif' : 'inactif')
                    );
                }
                
                showMessage('success', count($role_ids) . ' rôle(s) mis à jour avec succès');
                break;
                
                
                
        }
        
    } catch (Exception $e) {
        $errors[] = $e->getMessage();
    }
}

// Construire la requête avec filtres
$where_conditions = [];
$params = [];

if ($search) {
    $where_conditions[] = "(r.nom LIKE ? OR r.description LIKE ?)";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
}

if ($status_filter !== '') {
    $where_conditions[] = "r.actif = ?";
    $params[] = (int)$status_filter;
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Valider les paramètres de tri
$allowed_sort_fields = ['nom', 'date_creation', 'nb_users'];
$sort_by = in_array($sort_by, $allowed_sort_fields) ? $sort_by : 'nom';
$sort_order = in_array(strtoupper($sort_order), ['ASC', 'DESC']) ? strtoupper($sort_order) : 'ASC';

// Récupérer la liste des rôles
$roles = $database->query(
    "SELECT r.*, 
            (SELECT COUNT(*) FROM users WHERE role_id = r.id) as nb_users
     FROM roles r
     {$where_clause}
     ORDER BY r.{$sort_by} {$sort_order}",
    $params
)->fetchAll();

// Statistiques
$stats = [];
$stats['total_roles'] = count($roles);
$stats['active_roles'] = count(array_filter($roles, function($r) { return $r['actif']; }));
$stats['inactive_roles'] = $stats['total_roles'] - $stats['active_roles'];

// Utiliser les permissions détaillées
$detailed_permissions = getDetailedPermissions();
$available_actions = getDetailedActions();

// Fonction helper pour vérifier si une permission est cochée
function isPermissionChecked($current_permissions, $module_key, $page_key, $action, $subpage_key = null) {
    // Format nouveau : module:page:subpage:action
    if ($subpage_key !== null) {
        if (isset($current_permissions[$module_key][$page_key][$subpage_key]) && 
            in_array($action, $current_permissions[$module_key][$page_key][$subpage_key])) {
            return true;
        }
    }
    
    // Format ancien : module:page:action (pour compatibilité)
    if (isset($current_permissions[$module_key][$page_key]) && 
        in_array($action, $current_permissions[$module_key][$page_key])) {
        return true;
    }
    
    // Format ancien avec sous-pages : module:subpage:action
    if ($subpage_key !== null && isset($current_permissions[$module_key][$subpage_key]) && 
        in_array($action, $current_permissions[$module_key][$subpage_key])) {
        return true;
    }
    
    // Format avec slash : module:page/subpage:action (comme academic:classes/view:read)
    if ($subpage_key !== null) {
        $combined_key = $page_key . '/' . $subpage_key;
        if (isset($current_permissions[$module_key][$combined_key]) && 
            in_array($action, $current_permissions[$module_key][$combined_key])) {
            return true;
        }
    }
    
    return false;
}

include '../includes/header.php';
?>

<!-- DataTables CSS -->
<link href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css" rel="stylesheet">
<link href="https://cdn.datatables.net/buttons/2.4.1/css/buttons.bootstrap5.min.css" rel="stylesheet">
<link href="https://cdn.datatables.net/responsive/2.5.0/css/responsive.bootstrap5.min.css" rel="stylesheet">

<style>
/* Styles personnalisés pour DataTables */
.dataTables_wrapper .dataTables_length,
.dataTables_wrapper .dataTables_filter,
.dataTables_wrapper .dataTables_info,
.dataTables_wrapper .dataTables_processing,
.dataTables_wrapper .dataTables_paginate {
    margin-bottom: 1rem;
}

.dataTables_wrapper .dataTables_paginate .paginate_button {
    padding: 0.375rem 0.75rem;
    margin-left: 2px;
    border: 1px solid #dee2e6;
    border-radius: 0.375rem;
    background-color: #fff;
}

.dataTables_wrapper .dataTables_paginate .paginate_button:hover {
    background-color: #e9ecef;
    border-color: #adb5bd;
}

.dataTables_wrapper .dataTables_paginate .paginate_button.current {
    background-color: #0d6efd;
    border-color: #0d6efd;
    color: #fff !important;
}

/* Améliorer l'apparence du tableau */
#rolesTable th {
    background-color: #f8f9fa;
    border-bottom: 2px solid #dee2e6;
    font-weight: 600;
}

#rolesTable td {
    vertical-align: middle;
}

/* Styles pour les badges de permissions */
.permission-badge {
    font-size: 0.75em;
    padding: 0.25em 0.5em;
    margin: 0.1em;
    border-radius: 0.25rem;
}

.permission-badge.module {
    background-color: #e3f2fd;
    color: #1976d2;
    border: 1px solid #bbdefb;
}

.permission-badge.page {
    background-color: #f3e5f5;
    color: #7b1fa2;
    border: 1px solid #e1bee7;
}

.permission-badge.action {
    background-color: #e8f5e8;
    color: #2e7d32;
    border: 1px solid #c8e6c9;
}

/* Aperçu des permissions */
.permission-preview {
    display: flex;
    flex-wrap: wrap;
    gap: 2px;
}

.permission-preview .permission-badge {
    font-size: 0.7em;
    padding: 0.2em 0.4em;
    margin: 0;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    max-width: 80px;
}

/* Styles pour les boutons de contrôle des permissions */
.permission-control-buttons .btn {
    font-size: 0.75rem;
    padding: 0.25rem 0.5rem;
    border-radius: 0.25rem;
}

.permission-control-buttons .btn:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

.permission-control-buttons .btn-sm {
    font-size: 0.7rem;
    padding: 0.2rem 0.4rem;
}

/* Styles pour les cartes de permissions */
.card-header .btn-group {
    opacity: 1;
}

/* Indicateur visuel pour les modules/pages cochés */
.module-checked .card-header {
    background-color: #e8f5e8;
    border-left: 4px solid #28a745;
}

.page-checked .card-header {
    background-color: #fff3cd;
    border-left: 4px solid #ffc107;
}

/* Responsive pour mobile */
@media (max-width: 768px) {
    #rolesTable th:nth-child(n+4),
    #rolesTable td:nth-child(n+4) {
        display: none;
    }
    
    .dataTables_wrapper .dataTables_length,
    .dataTables_wrapper .dataTables_filter {
        text-align: center;
        margin-bottom: 0.5rem;
    }
    
    .permission-control-buttons .btn {
        font-size: 0.7rem;
        padding: 0.2rem 0.3rem;
    }
    
    .card-header .btn-group {
        opacity: 1;
    }
}
</style>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">
        <i class="fas fa-user-tag me-2"></i>
        Gestion des Rôles
    </h1>
    <div class="btn-toolbar mb-2 mb-md-0">
            <div class="btn-group me-2">
                <a href="roles_add.php" class="btn btn-primary">
                    <i class="fas fa-plus me-1"></i>
                    Nouveau rôle
                </a>
                <button type="button" class="btn btn-outline-primary dropdown-toggle dropdown-toggle-split" data-bs-toggle="dropdown">
                    <span class="visually-hidden">Menu déroulant</span>
                </button>
                <ul class="dropdown-menu">
                    <li><a class="dropdown-item" href="roles_add.php">
                        <i class="fas fa-plus me-2"></i>Créer un nouveau rôle
                    </a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item" href="roles_bulk.php">
                        <i class="fas fa-tasks me-2"></i>Actions en lot
                    </a></li>
                </ul>
            </div>
            <div class="btn-group me-2">
                <button type="button" class="btn btn-outline-success" onclick="exportSelected()">
                    <i class="fas fa-download me-1"></i>
                    Exporter
                </button>
                <button type="button" class="btn btn-outline-warning" onclick="goToBulkActions()">
                    <i class="fas fa-exchange-alt me-1"></i>
                    Actions en lot
                </button>
            </div>
    </div>
</div>

<?php if (!empty($errors)): ?>
    <div class="alert alert-danger">
        <h6><i class="fas fa-exclamation-triangle me-2"></i>Erreurs détectées :</h6>
        <ul class="mb-0">
            <?php foreach ($errors as $error): ?>
                <li><?php echo htmlspecialchars($error); ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<?php if (false): // Section d'édition supprimée - utilisez les pages dédiées ?>
    <!-- Formulaire d'édition de rôle -->
    <div class="row">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-edit me-2"></i>
                        Modifier le rôle
                    </h5>
                </div>
                <div class="card-body">
                    <form method="POST" class="needs-validation" novalidate>
                        <input type="hidden" name="action" value="update_role">
                        <input type="hidden" name="role_id" value="<?php echo $edit_role['id']; ?>">

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="nom" class="form-label">Nom du rôle <span class="text-danger">*</span></label>
                                <input type="text"
                                       class="form-control"
                                       id="nom"
                                       name="nom"
                                       value="<?php echo htmlspecialchars($edit_role['nom']); ?>"
                                       required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="actif" class="form-label">Statut</label>
                                <select class="form-select" id="actif" name="actif">
                                    <option value="1" <?php echo $edit_role['actif'] ? 'selected' : ''; ?>>Actif</option>
                                    <option value="0" <?php echo !$edit_role['actif'] ? 'selected' : ''; ?>>Inactif</option>
                                </select>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="description" class="form-label">Description</label>
                            <textarea class="form-control"
                                      id="description"
                                      name="description"
                                      rows="3"><?php echo htmlspecialchars($edit_role['description']); ?></textarea>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Permissions détaillées</label>
                            <div class="accordion" id="permissionsAccordion">
                                <?php
                                $current_permissions = (!empty($edit_role['permissions'])) ? json_decode($edit_role['permissions'], true) : [];
                                $module_index = 0;
                                foreach ($detailed_permissions as $module_key => $module): ?>
                                    <div class="accordion-item">
                                        <h2 class="accordion-header" id="heading<?php echo $module_index; ?>">
                                            <button class="accordion-button <?php echo $module_index === 0 ? '' : 'collapsed'; ?>" 
                                                    type="button" 
                                                    data-bs-toggle="collapse" 
                                                    data-bs-target="#collapse<?php echo $module_index; ?>" 
                                                    aria-expanded="<?php echo $module_index === 0 ? 'true' : 'false'; ?>" 
                                                    aria-controls="collapse<?php echo $module_index; ?>">
                                                <strong><?php echo $module['name']; ?></strong>
                                            </button>
                                        </h2>
                                        <div id="collapse<?php echo $module_index; ?>" 
                                             class="accordion-collapse collapse <?php echo $module_index === 0 ? 'show' : ''; ?>" 
                                             aria-labelledby="heading<?php echo $module_index; ?>" 
                                             data-bs-parent="#permissionsAccordion">
                                            <div class="accordion-body">
                                                <!-- Boutons de contrôle pour le module -->
                                                <div class="row mb-3">
                                                    <div class="col-12">
                                                        <div class="btn-group btn-group-sm permission-control-buttons" role="group">
                                                            <button type="button" class="btn btn-outline-primary" onclick="toggleModulePermissions('<?php echo $module_key; ?>', true)">
                                                                <i class="fas fa-check-square me-1"></i>
                                                                Cocher tout le module
                                                            </button>
                                                            <button type="button" class="btn btn-outline-secondary" onclick="toggleModulePermissions('<?php echo $module_key; ?>', false)">
                                                                <i class="fas fa-square me-1"></i>
                                                                Décocher tout le module
                                                            </button>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="row">
                                                    <?php foreach ($module['pages'] as $page_key => $page): ?>
                                                        <div class="col-md-6 mb-3">
                                                            <div class="card">
                                                                <div class="card-header d-flex justify-content-between align-items-center">
                                                                    <h6 class="mb-0"><?php echo $page['name']; ?></h6>
                                                                    <div class="btn-group btn-group-sm permission-control-buttons" role="group">
                                                                        <button type="button" class="btn btn-outline-success btn-sm" onclick="togglePagePermissions('<?php echo $module_key; ?>', '<?php echo $page_key; ?>', true)" title="Cocher tout">
                                                                            <i class="fas fa-check"></i>
                                                                        </button>
                                                                        <button type="button" class="btn btn-outline-warning btn-sm" onclick="togglePagePermissions('<?php echo $module_key; ?>', '<?php echo $page_key; ?>', false)" title="Décocher tout">
                                                                            <i class="fas fa-times"></i>
                                                                        </button>
                                                                    </div>
                                                                </div>
                                                                <div class="card-body">
                                                                    <?php if (isset($page['permissions'])): ?>
                                                                        <?php foreach ($page['permissions'] as $action): ?>
                                                                            <div class="form-check">
                                                                                <input class="form-check-input"
                                                                                       type="checkbox"
                                                                                       name="permissions[]"
                                                                                       value="<?php echo $module_key . ':' . $page_key . ':' . $action; ?>"
                                                                                       id="perm_<?php echo $module_key . '_' . $page_key . '_' . $action; ?>"
                                                                                       <?php 
                                                                                       $is_checked = isPermissionChecked($current_permissions, $module_key, $page_key, $action, null);
                                                                                       if ($is_checked) echo 'checked';
                                                                                       ?>>
                                                                                <label class="form-check-label" for="perm_<?php echo $module_key . '_' . $page_key . '_' . $action; ?>">
                                                                                    <?php echo $available_actions[$action] ?? ucfirst($action); ?>
                                                                                </label>
                                                                            </div>
                                                                        <?php endforeach; ?>
                                                                    <?php elseif (isset($page['pages'])): ?>
                                                                        <?php foreach ($page['pages'] as $subpage_key => $subpage): ?>
                                                                            <div class="mb-2">
                                                                                <small class="text-muted fw-bold"><?php echo $subpage['name']; ?>:</small>
                                                                                <?php if (isset($subpage['permissions'])): ?>
                                                                                    <?php foreach ($subpage['permissions'] as $action): ?>
                                                                                        <div class="form-check ms-3">
                                                                                            <input class="form-check-input"
                                                                                                   type="checkbox"
                                                                                                   name="permissions[]"
                                                                                                   value="<?php echo $module_key . ':' . $page_key . ':' . $subpage_key . ':' . $action; ?>"
                                                                                                   id="perm_<?php echo $module_key . '_' . $page_key . '_' . $subpage_key . '_' . $action; ?>"
                                                                                                   <?php 
                                                                                                   $is_checked = isPermissionChecked($current_permissions, $module_key, $page_key, $action, $subpage_key);
                                                                                                   if ($is_checked) echo 'checked';
                                                                                                   ?>>
                                                                                            <label class="form-check-label" for="perm_<?php echo $module_key . '_' . $page_key . '_' . $subpage_key . '_' . $action; ?>">
                                                                                                <?php echo $available_actions[$action] ?? ucfirst($action); ?>
                                                                                            </label>
                                                                                        </div>
                                                                                    <?php endforeach; ?>
                                                                                <?php elseif (isset($subpage['pages'])): ?>
                                                                                    <?php foreach ($subpage['pages'] as $subsubpage_key => $subsubpage): ?>
                                                                                        <div class="mb-1">
                                                                                            <small class="text-muted"><?php echo $subsubpage['name']; ?>:</small>
                                                                                            <?php if (isset($subsubpage['permissions'])): ?>
                                                                                                <?php foreach ($subsubpage['permissions'] as $action): ?>
                                                                                                    <div class="form-check ms-4">
                                                                                                        <input class="form-check-input"
                                                                                                               type="checkbox"
                                                                                                               name="permissions[]"
                                                                                                               value="<?php echo $module_key . ':' . $subsubpage_key . ':' . $action; ?>"
                                                                                                               id="perm_<?php echo $module_key . '_' . $page_key . '_' . $subpage_key . '_' . $subsubpage_key . '_' . $action; ?>"
                                                                                                               <?php 
                                                                                                               $is_checked = isPermissionChecked($current_permissions, $module_key, $subsubpage_key, $action, null);
                                                                                                               if ($is_checked) echo 'checked';
                                                                                                               ?>>
                                                                                                        <label class="form-check-label" for="perm_<?php echo $module_key . '_' . $page_key . '_' . $subpage_key . '_' . $subsubpage_key . '_' . $action; ?>">
                                                                                                            <?php echo $available_actions[$action] ?? ucfirst($action); ?>
                                                                                                        </label>
                                                                                                    </div>
                                                                                                <?php endforeach; ?>
                                                                                            <?php endif; ?>
                                                                                        </div>
                                                                                    <?php endforeach; ?>
                                                                                <?php endif; ?>
                                                                            </div>
                                                                        <?php endforeach; ?>
                                                                    <?php endif; ?>
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

                        <div class="d-flex justify-content-between">
                            <a href="roles.php" class="btn btn-secondary">
                                <i class="fas fa-times me-1"></i>
                                Annuler
                            </a>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-1"></i>
                                Enregistrer
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <!-- Informations sur le rôle -->
            <div class="card mb-4">
                <div class="card-header">
                    <h6 class="mb-0">
                        <i class="fas fa-info-circle me-2"></i>
                        Informations
                    </h6>
                </div>
                <div class="card-body">
                    <table class="table table-borderless table-sm">
                        <tr>
                            <td class="fw-bold">Créé le :</td>
                            <td><?php echo formatDate($edit_role['date_creation']); ?></td>
                        </tr>
                        <?php if ($edit_role['date_modification']): ?>
                        <tr>
                            <td class="fw-bold">Modifié le :</td>
                            <td><?php echo formatDate($edit_role['date_modification']); ?></td>
                        </tr>
                        <?php endif; ?>
                        <tr>
                            <td class="fw-bold">Utilisateurs :</td>
                            <td>
                                <?php
                                $users_count = $database->query(
                                    "SELECT COUNT(*) as count FROM users WHERE role_id = ?",
                                    [$edit_role['id']]
                                )->fetch()['count'];
                                echo $users_count;
                                ?>
                            </td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>
    </div>

<?php else: ?>
    <!-- Vue principale - Liste des rôles -->

    

    <!-- Statistiques -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card text-center">
                <div class="card-body">
                    <i class="fas fa-user-tag fa-2x text-primary mb-2"></i>
                    <h4 class="mb-0"><?php echo $stats['total_roles']; ?></h4>
                    <small class="text-muted">Total rôles</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-center">
                <div class="card-body">
                    <i class="fas fa-user-check fa-2x text-success mb-2"></i>
                    <h4 class="mb-0"><?php echo $stats['active_roles']; ?></h4>
                    <small class="text-muted">Rôles actifs</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-center">
                <div class="card-body">
                    <i class="fas fa-user-times fa-2x text-danger mb-2"></i>
                    <h4 class="mb-0"><?php echo $stats['inactive_roles']; ?></h4>
                    <small class="text-muted">Rôles inactifs</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-center">
                <div class="card-body">
                    <i class="fas fa-users fa-2x text-info mb-2"></i>
                    <h4 class="mb-0"><?php echo array_sum(array_column($roles, 'nb_users')); ?></h4>
                    <small class="text-muted">Utilisateurs assignés</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Statistiques avancées des permissions -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-chart-pie me-2"></i>
                        Répartition des Permissions par Module
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <?php
                        $module_stats = [];
                        foreach ($roles as $role) {
                            $permissions = null;
                            if (!empty($role['permissions'])) {
                                $permissions = json_decode($role['permissions'], true);
                            }
                            if ($permissions) {
                                foreach ($permissions as $module => $module_permissions) {
                                    if (!isset($module_stats[$module])) {
                                        $module_stats[$module] = 0;
                                    }
                                    $module_stats[$module]++;
                                }
                            }
                        }
                        
                        // Trier par nombre d'utilisations
                        arsort($module_stats);
                        
                        foreach ($module_stats as $module_key => $count):
                            if (isset($detailed_permissions[$module_key])):
                                $percentage = round(($count / count($roles)) * 100, 1);
                        ?>
                            <div class="col-md-3 mb-3">
                                <div class="card border-0 bg-light">
                                    <div class="card-body text-center">
                                        <h6 class="card-title"><?php echo $detailed_permissions[$module_key]['name']; ?></h6>
                                        <div class="progress mb-2" style="height: 8px;">
                                            <div class="progress-bar bg-primary" style="width: <?php echo $percentage; ?>%"></div>
                                        </div>
                                        <small class="text-muted">
                                            <?php echo $count; ?> rôle(s) - <?php echo $percentage; ?>%
                                        </small>
                                    </div>
                                </div>
                            </div>
                        <?php 
                            endif;
                        endforeach; 
                        ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Liste des rôles -->
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0">
                <i class="fas fa-list me-2"></i>
                Liste des rôles (<?php echo count($roles); ?>)
            </h5>
            <div class="btn-group btn-group-sm">
                <button type="button" class="btn btn-outline-primary" onclick="selectAll()">
                    <i class="fas fa-check-square me-1"></i>
                    Tout sélectionner
                </button>
                <button type="button" class="btn btn-outline-secondary" onclick="deselectAll()">
                    <i class="fas fa-square me-1"></i>
                    Désélectionner
                </button>
            </div>
        </div>
        <div class="card-body">
            <?php if (!empty($roles)): ?>
                <!-- Barre de recherche personnalisée -->
                <div class="row mb-3">
                    <div class="col-md-6">
                        <div class="input-group">
                            <span class="input-group-text">
                                <i class="fas fa-search"></i>
                            </span>
                            <input type="text" id="customSearch" class="form-control" placeholder="Rechercher dans les rôles...">
                        </div>
                    </div>
                    <div class="col-md-6 text-end">
                        <div class="btn-group btn-group-sm" role="group">
                            <button type="button" class="btn btn-outline-secondary" onclick="filterByStatus('all')">
                                <i class="fas fa-list me-1"></i>Tous
                            </button>
                            <button type="button" class="btn btn-outline-success" onclick="filterByStatus('active')">
                                <i class="fas fa-check-circle me-1"></i>Actifs
                            </button>
                            <button type="button" class="btn btn-outline-danger" onclick="filterByStatus('inactive')">
                                <i class="fas fa-times-circle me-1"></i>Inactifs
                            </button>
                        </div>
                    </div>
                </div>
                
                <div class="table-responsive">
                    <table id="rolesTable" class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th width="50">
                                    <input type="checkbox" class="form-check-input" id="selectAllCheckbox" onchange="toggleAllCheckboxes(this)">
                                </th>
                                <th>
                                    <a href="?<?php echo http_build_query(array_merge($_GET, ['sort' => 'nom', 'order' => $sort_by === 'nom' && $sort_order === 'ASC' ? 'DESC' : 'ASC'])); ?>" class="text-decoration-none">
                                        Rôle
                                        <?php if ($sort_by === 'nom'): ?>
                                            <i class="fas fa-sort-<?php echo $sort_order === 'ASC' ? 'up' : 'down'; ?> ms-1"></i>
                                        <?php endif; ?>
                                    </a>
                                </th>
                                <th>Description</th>
                                <th>Permissions</th>
                                <th>Aperçu des Permissions</th>
                                <th>
                                    <a href="?<?php echo http_build_query(array_merge($_GET, ['sort' => 'nb_users', 'order' => $sort_by === 'nb_users' && $sort_order === 'ASC' ? 'DESC' : 'ASC'])); ?>" class="text-decoration-none">
                                        Utilisateurs
                                        <?php if ($sort_by === 'nb_users'): ?>
                                            <i class="fas fa-sort-<?php echo $sort_order === 'ASC' ? 'up' : 'down'; ?> ms-1"></i>
                                        <?php endif; ?>
                                    </a>
                                </th>
                                <th>Statut</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($roles as $role): ?>
                                <tr>
                                    <td>
                                        <input type="checkbox" class="form-check-input role-checkbox" value="<?php echo $role['id']; ?>">
                                    </td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($role['nom']); ?></strong>
                                        <br><small class="text-muted">#<?php echo $role['id']; ?></small>
                                    </td>
                                    <td>
                                        <?php echo htmlspecialchars($role['description']); ?>
                                    </td>
                                    <td>
                                        <?php
                                        $permissions = null;
                                        if (!empty($role['permissions'])) {
                                            $permissions = json_decode($role['permissions'], true);
                                        }
                                        if ($permissions) {
                                            $permission_count = 0;
                                            $module_count = 0;
                                            
                                            // Compter les permissions avec la structure compatible
                                            foreach ($permissions as $module => $module_data) {
                                                if (is_array($module_data) && isset($module_data['pages'])) {
                                                    $module_count++;
                                                    foreach ($module_data['pages'] as $page => $page_data) {
                                                        if (isset($page_data['permissions']) && is_array($page_data['permissions'])) {
                                                            // Page directe avec permissions
                                                            $permission_count += count($page_data['permissions']);
                                                        } elseif (isset($page_data['pages']) && is_array($page_data['pages'])) {
                                                            // Sous-pages (structure hiérarchique)
                                                            foreach ($page_data['pages'] as $subpage => $subpage_data) {
                                                                if (isset($subpage_data['permissions']) && is_array($subpage_data['permissions'])) {
                                                                    $permission_count += count($subpage_data['permissions']);
                                                                }
                                                            }
                                                        }
                                                    }
                                                }
                                            }
                                            
                                            echo "<span class='badge bg-info'>{$permission_count} permissions</span>";
                                            echo "<br><small class='text-muted'>{$module_count} module(s)</small>";
                                        } else {
                                            echo "<span class='text-muted'>Aucune</span>";
                                        }
                                        ?>
                                    </td>
                                    <td>
                                        <?php
                                        $permissions = null;
                                        if (!empty($role['permissions'])) {
                                            $permissions = json_decode($role['permissions'], true);
                                        }
                                        if ($permissions) {
                                            $preview_count = 0;
                                            $max_preview = 4;
                                            
                                            echo "<div class='permission-preview'>";
                                            foreach ($permissions as $module => $module_permissions) {
                                                if ($preview_count >= $max_preview) {
                                                    $remaining = count($permissions) - $max_preview;
                                                    echo "<span class='permission-badge module'>+{$remaining}</span>";
                                                    break;
                                                }
                                                
                                                if (is_array($module_permissions)) {
                                                    $module_name = $detailed_permissions[$module]['name'] ?? ucfirst($module);
                                                    $page_count = count($module_permissions);
                                                    echo "<span class='permission-badge module' title='{$module_name} ({$page_count} pages)'>";
                                                    echo "<i class='fas fa-folder me-1'></i>" . substr($module_name, 0, 12);
                                                    echo "</span>";
                                                    $preview_count++;
                                                }
                                            }
                                            echo "</div>";
                                        } else {
                                            echo "<span class='text-muted'><i class='fas fa-ban me-1'></i>Aucune</span>";
                                        }
                                        ?>
                                    </td>
                                    <td>
                                        <span class="badge bg-secondary"><?php echo $role['nb_users']; ?> utilisateur(s)</span>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?php echo $role['actif'] ? 'success' : 'danger'; ?>">
                                            <?php echo $role['actif'] ? 'Actif' : 'Inactif'; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <a href="roles_edit.php?id=<?php echo $role['id']; ?>" 
                                               class="btn btn-outline-primary" 
                                               title="Modifier le rôle">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <a href="roles_view.php?id=<?php echo $role['id']; ?>" 
                                               class="btn btn-outline-info" 
                                               title="Voir le rôle">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <div class="btn-group btn-group-sm">
                                                <button type="button" class="btn btn-outline-<?php echo $role['actif'] ? 'danger' : 'success'; ?> dropdown-toggle" data-bs-toggle="dropdown">
                                                    <i class="fas fa-<?php echo $role['actif'] ? 'ban' : 'check'; ?>"></i>
                                                </button>
                                                <ul class="dropdown-menu">
                                                    <li><a class="dropdown-item" href="#" onclick="toggleRoleStatus(<?php echo $role['id']; ?>, <?php echo $role['actif'] ? 0 : 1; ?>)">
                                                        <i class="fas fa-<?php echo $role['actif'] ? 'check' : 'ban'; ?> me-2"></i>
                                                        <?php echo $role['actif'] ? 'Désactiver' : 'Activer'; ?>
                                                    </a></li>
                                                    <li><hr class="dropdown-divider"></li>
                                                    <li><a class="dropdown-item text-danger" href="roles_delete.php?id=<?php echo $role['id']; ?>" onclick="return confirm('Êtes-vous sûr de vouloir supprimer ce rôle ?')">
                                                        <i class="fas fa-trash me-2"></i>
                                                        Supprimer
                                                    </a></li>
                                                </ul>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="text-center py-5">
                    <i class="fas fa-user-tag fa-3x text-muted mb-3"></i>
                    <h5 class="text-muted">Aucun rôle trouvé</h5>
                    <p class="text-muted">Commencez par créer le premier rôle.</p>
                    <div class="btn-group">
                        <a href="roles_add.php" class="btn btn-primary">
                            <i class="fas fa-plus me-1"></i>
                            Créer un rôle
                        </a>
                        <a href="roles_bulk.php" class="btn btn-outline-secondary">
                            <i class="fas fa-tasks me-1"></i>
                            Actions en lot
                        </a>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>

<!-- Section d'actions rapides -->
<div class="row mt-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h6 class="mb-0">
                    <i class="fas fa-bolt me-2"></i>
                    Actions rapides
                </h6>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-3 mb-3">
                        <a href="roles_add.php" class="btn btn-primary w-100">
                            <i class="fas fa-plus me-2"></i>
                            Nouveau rôle
                        </a>
                    </div>
                    <div class="col-md-3 mb-3">
                        <a href="roles_bulk.php" class="btn btn-outline-warning w-100">
                            <i class="fas fa-tasks me-2"></i>
                            Actions en lot
                        </a>
                    </div>
                    <div class="col-md-3 mb-3">
                        <button type="button" class="btn btn-outline-success w-100" onclick="exportSelected()">
                            <i class="fas fa-download me-2"></i>
                            Exporter
                        </button>
                    </div>
                    <div class="col-md-3 mb-3">
                        <a href="../dashboard.php" class="btn btn-outline-secondary w-100">
                            <i class="fas fa-home me-2"></i>
                            Dashboard
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modales de détails des permissions -->
<?php foreach ($roles as $role): ?>
    <div class="modal fade" id="permissionsModal<?php echo $role['id']; ?>" tabindex="-1">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-shield-alt me-2"></i>
                        Permissions du rôle : <?php echo htmlspecialchars($role['nom']); ?>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <?php
                    $permissions = null;
                    if (!empty($role['permissions'])) {
                        $permissions = json_decode($role['permissions'], true);
                    }
                    if ($permissions): ?>
                        <div class="row">
                            <?php foreach ($permissions as $module_key => $module_permissions): ?>
                                <?php if (isset($detailed_permissions[$module_key])): ?>
                                    <div class="col-md-6 mb-4">
                                        <div class="card">
                                            <div class="card-header">
                                                <h6 class="mb-0">
                                                    <i class="fas fa-folder me-2"></i>
                                                    <?php echo $detailed_permissions[$module_key]['name']; ?>
                                                </h6>
                                            </div>
                                            <div class="card-body">
                                                <?php if (is_array($module_permissions)): ?>
                                                    <?php foreach ($module_permissions as $page_key => $actions): ?>
                                                        <?php if (isset($detailed_permissions[$module_key]['pages'][$page_key])): ?>
                                                            <div class="mb-3">
                                                                <h6 class="text-primary">
                                                                    <i class="fas fa-file me-1"></i>
                                                                    <?php echo $detailed_permissions[$module_key]['pages'][$page_key]['name']; ?>
                                                                </h6>
                                                                <div class="ms-3">
                                                                    <?php if (is_array($actions)): ?>
                                                                        <?php foreach ($actions as $action): ?>
                                                                            <span class="badge bg-success me-1 mb-1">
                                                                                <i class="fas fa-check me-1"></i>
                                                                                <?php echo $available_actions[$action] ?? ucfirst($action); ?>
                                                                            </span>
                                                                        <?php endforeach; ?>
                                                                    <?php endif; ?>
                                                                </div>
                                                            </div>
                                                        <?php endif; ?>
                                                    <?php endforeach; ?>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-4">
                            <i class="fas fa-ban fa-3x text-muted mb-3"></i>
                            <h5 class="text-muted">Aucune permission configurée</h5>
                            <p class="text-muted">Ce rôle n'a pas de permissions spécifiques.</p>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fermer</button>
                    <a href="roles_edit.php?id=<?php echo $role['id']; ?>" class="btn btn-primary">
                        <i class="fas fa-edit me-1"></i>
                        Modifier les permissions
                    </a>
                </div>
            </div>
        </div>
    </div>
<?php endforeach; ?>

<!-- Modal de création de rôle supprimée - utilisez roles_add.php -->
<div class="modal fade" id="createRoleModal" tabindex="-1" style="display: none;">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-user-tag me-2"></i>
                    Créer un nouveau rôle
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="create_role">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="new_nom" class="form-label">Nom du rôle <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="new_nom" name="nom" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="new_description" class="form-label">Description</label>
                            <input type="text" class="form-control" id="new_description" name="description">
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Permissions détaillées</label>
                        <div class="accordion" id="newPermissionsAccordion">
                            <?php
                            $module_index = 0;
                            foreach ($detailed_permissions as $module_key => $module): ?>
                                <div class="accordion-item">
                                    <h2 class="accordion-header" id="newHeading<?php echo $module_index; ?>">
                                        <button class="accordion-button collapsed" 
                                                type="button" 
                                                data-bs-toggle="collapse" 
                                                data-bs-target="#newCollapse<?php echo $module_index; ?>" 
                                                aria-expanded="false" 
                                                aria-controls="newCollapse<?php echo $module_index; ?>">
                                            <strong><?php echo $module['name']; ?></strong>
                                        </button>
                                    </h2>
                                    <div id="newCollapse<?php echo $module_index; ?>" 
                                         class="accordion-collapse collapse" 
                                         aria-labelledby="newHeading<?php echo $module_index; ?>" 
                                         data-bs-parent="#newPermissionsAccordion">
                                        <div class="accordion-body">
                                            <!-- Boutons de contrôle pour le module -->
                                            <div class="row mb-3">
                                                <div class="col-12">
                                                    <div class="btn-group btn-group-sm permission-control-buttons" role="group">
                                                        <button type="button" class="btn btn-outline-primary" onclick="toggleModulePermissions('<?php echo $module_key; ?>', true)">
                                                            <i class="fas fa-check-square me-1"></i>
                                                            Cocher tout le module
                                                        </button>
                                                        <button type="button" class="btn btn-outline-secondary" onclick="toggleModulePermissions('<?php echo $module_key; ?>', false)">
                                                            <i class="fas fa-square me-1"></i>
                                                            Décocher tout le module
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="row">
                                                <?php foreach ($module['pages'] as $page_key => $page): ?>
                                                    <div class="col-md-6 mb-3">
                                                        <div class="card">
                                                            <div class="card-header d-flex justify-content-between align-items-center">
                                                                <h6 class="mb-0"><?php echo $page['name']; ?></h6>
                                                                <div class="btn-group btn-group-sm permission-control-buttons" role="group">
                                                                    <button type="button" class="btn btn-outline-success btn-sm" onclick="togglePagePermissions('<?php echo $module_key; ?>', '<?php echo $page_key; ?>', true)" title="Cocher tout">
                                                                        <i class="fas fa-check"></i>
                                                                    </button>
                                                                    <button type="button" class="btn btn-outline-warning btn-sm" onclick="togglePagePermissions('<?php echo $module_key; ?>', '<?php echo $page_key; ?>', false)" title="Décocher tout">
                                                                        <i class="fas fa-times"></i>
                                                                    </button>
                                                                </div>
                                                            </div>
                                                            <div class="card-body">
                                                                    <?php if (isset($page['permissions'])): ?>
                                                                        <?php foreach ($page['permissions'] as $action): ?>
                                                                            <div class="form-check">
                                                                                <input class="form-check-input"
                                                                                       type="checkbox"
                                                                                       name="permissions[]"
                                                                                       value="<?php echo $module_key . ':' . $page_key . ':' . $action; ?>"
                                                                                       id="new_perm_<?php echo $module_key . '_' . $page_key . '_' . $action; ?>">
                                                                                <label class="form-check-label" for="new_perm_<?php echo $module_key . '_' . $page_key . '_' . $action; ?>">
                                                                                    <?php echo $available_actions[$action] ?? ucfirst($action); ?>
                                                                                </label>
                                                                            </div>
                                                                        <?php endforeach; ?>
                                                                    <?php elseif (isset($page['pages'])): ?>
                                                                        <?php foreach ($page['pages'] as $subpage_key => $subpage): ?>
                                                                            <div class="mb-2">
                                                                                <small class="text-muted fw-bold"><?php echo $subpage['name']; ?>:</small>
                                                                                <?php if (isset($subpage['permissions'])): ?>
                                                                                    <?php foreach ($subpage['permissions'] as $action): ?>
                                                                                        <div class="form-check ms-3">
                                                                                            <input class="form-check-input"
                                                                                                   type="checkbox"
                                                                                                   name="permissions[]"
                                                                                                   value="<?php echo $module_key . ':' . $page_key . ':' . $subpage_key . ':' . $action; ?>"
                                                                                                   id="new_perm_<?php echo $module_key . '_' . $page_key . '_' . $subpage_key . '_' . $action; ?>">
                                                                                            <label class="form-check-label" for="new_perm_<?php echo $module_key . '_' . $page_key . '_' . $subpage_key . '_' . $action; ?>">
                                                                                                <?php echo $available_actions[$action] ?? ucfirst($action); ?>
                                                                                            </label>
                                                                                        </div>
                                                                                    <?php endforeach; ?>
                                                                                <?php elseif (isset($subpage['pages'])): ?>
                                                                                    <?php foreach ($subpage['pages'] as $subsubpage_key => $subsubpage): ?>
                                                                                        <div class="mb-1">
                                                                                            <small class="text-muted"><?php echo $subsubpage['name']; ?>:</small>
                                                                                            <?php if (isset($subsubpage['permissions'])): ?>
                                                                                                <?php foreach ($subsubpage['permissions'] as $action): ?>
                                                                                                    <div class="form-check ms-4">
                                                                                                        <input class="form-check-input"
                                                                                                               type="checkbox"
                                                                                                               name="permissions[]"
                                                                                                               value="<?php echo $module_key . ':' . $subsubpage_key . ':' . $action; ?>"
                                                                                                               id="new_perm_<?php echo $module_key . '_' . $page_key . '_' . $subpage_key . '_' . $subsubpage_key . '_' . $action; ?>">
                                                                                                        <label class="form-check-label" for="new_perm_<?php echo $module_key . '_' . $page_key . '_' . $subpage_key . '_' . $subsubpage_key . '_' . $action; ?>">
                                                                                                            <?php echo $available_actions[$action] ?? ucfirst($action); ?>
                                                                                                        </label>
                                                                                                    </div>
                                                                                                <?php endforeach; ?>
                                                                                            <?php endif; ?>
                                                                                        </div>
                                                                                    <?php endforeach; ?>
                                                                                <?php endif; ?>
                                                                            </div>
                                                                        <?php endforeach; ?>
                                                                    <?php endif; ?>
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
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times me-1"></i>
                        Annuler
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-1"></i>
                        Créer le rôle
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal d'import de rôles -->
<div class="modal fade" id="importModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-upload me-2"></i>
                    Importer des rôles
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="import_roles">
                <div class="modal-body">
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        Sélectionnez un fichier JSON exporté précédemment. Les rôles existants seront ignorés.
                    </div>
                    <div class="mb-3">
                        <label for="import_file" class="form-label">Fichier JSON</label>
                        <input type="file" class="form-control" id="import_file" name="import_file" accept=".json" required>
                        <div class="form-text">Format accepté : .json</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times me-1"></i>
                        Annuler
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-upload me-1"></i>
                        Importer
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal d'actions en lot -->
<div class="modal fade" id="bulkActionsModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-tasks me-2"></i>
                    Actions en lot
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" id="bulkActionsForm">
                <input type="hidden" name="action" value="bulk_toggle_status">
                <input type="hidden" name="role_ids" id="bulkRoleIds">
                <div class="modal-body">
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <span id="bulkSelectedCount">0</span> rôle(s) sélectionné(s)
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Action à effectuer</label>
                        <select class="form-select" name="new_status" required>
                            <option value="">Choisir une action...</option>
                            <option value="1">Activer les rôles</option>
                            <option value="0">Désactiver les rôles</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times me-1"></i>
                        Annuler
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-check me-1"></i>
                        Appliquer
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- JavaScript pour les nouvelles fonctionnalités -->
<script>
// Variables globales
let selectedRoles = new Set();

// Fonctions de sélection
function toggleAllCheckboxes(masterCheckbox) {
    const checkboxes = document.querySelectorAll('.role-checkbox');
    checkboxes.forEach(checkbox => {
        checkbox.checked = masterCheckbox.checked;
        if (masterCheckbox.checked) {
            selectedRoles.add(checkbox.value);
        } else {
            selectedRoles.delete(checkbox.value);
        }
    });
    updateSelectedCount();
}

function selectAll() {
    const checkboxes = document.querySelectorAll('.role-checkbox');
    const masterCheckbox = document.getElementById('selectAllCheckbox');
    checkboxes.forEach(checkbox => {
        checkbox.checked = true;
        selectedRoles.add(checkbox.value);
    });
    masterCheckbox.checked = true;
    updateSelectedCount();
}

function deselectAll() {
    const checkboxes = document.querySelectorAll('.role-checkbox');
    const masterCheckbox = document.getElementById('selectAllCheckbox');
    checkboxes.forEach(checkbox => {
        checkbox.checked = false;
    });
    masterCheckbox.checked = false;
    selectedRoles.clear();
    updateSelectedCount();
}

// Gestion des événements de checkbox
document.addEventListener('DOMContentLoaded', function() {
    const checkboxes = document.querySelectorAll('.role-checkbox');
    checkboxes.forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            if (this.checked) {
                selectedRoles.add(this.value);
            } else {
                selectedRoles.delete(this.value);
            }
            updateSelectedCount();
            updateMasterCheckbox();
        });
    });
});

function updateSelectedCount() {
    const count = selectedRoles.size;
    const countElement = document.getElementById('bulkSelectedCount');
    if (countElement) {
        countElement.textContent = count;
    }
}

function updateMasterCheckbox() {
    const checkboxes = document.querySelectorAll('.role-checkbox');
    const masterCheckbox = document.getElementById('selectAllCheckbox');
    const checkedCount = Array.from(checkboxes).filter(cb => cb.checked).length;
    
    if (checkedCount === 0) {
        masterCheckbox.checked = false;
        masterCheckbox.indeterminate = false;
    } else if (checkedCount === checkboxes.length) {
        masterCheckbox.checked = true;
        masterCheckbox.indeterminate = false;
    } else {
        masterCheckbox.checked = false;
        masterCheckbox.indeterminate = true;
    }
}

// Actions en lot
function goToBulkActions() {
    if (selectedRoles.size === 0) {
        alert('Veuillez sélectionner au moins un rôle.');
        return;
    }
    
    const roleIds = Array.from(selectedRoles).join(',');
    window.location.href = `roles_bulk.php?ids=${roleIds}`;
}

function bulkToggleStatus() {
    if (selectedRoles.size === 0) {
        alert('Veuillez sélectionner au moins un rôle.');
        return;
    }
    
    document.getElementById('bulkRoleIds').value = Array.from(selectedRoles).join(',');
    const modal = new bootstrap.Modal(document.getElementById('bulkActionsModal'));
    modal.show();
}

// Export des rôles sélectionnés
function exportSelected() {
    if (selectedRoles.size === 0) {
        alert('Veuillez sélectionner au moins un rôle à exporter.');
        return;
    }
    
    const form = document.createElement('form');
    form.method = 'POST';
    form.style.display = 'none';
    
    const actionInput = document.createElement('input');
    actionInput.type = 'hidden';
    actionInput.name = 'action';
    actionInput.value = 'export_roles';
    form.appendChild(actionInput);
    
    Array.from(selectedRoles).forEach(roleId => {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'role_ids[]';
        input.value = roleId;
        form.appendChild(input);
    });
    
    document.body.appendChild(form);
    form.submit();
    document.body.removeChild(form);
}

// Actions individuelles
function toggleRoleStatus(roleId, newStatus) {
    if (confirm(`Êtes-vous sûr de vouloir ${newStatus ? 'activer' : 'désactiver'} ce rôle ?`)) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.style.display = 'none';
        
        const actionInput = document.createElement('input');
        actionInput.type = 'hidden';
        actionInput.name = 'action';
        actionInput.value = 'toggle_status';
        form.appendChild(actionInput);
        
        const roleIdInput = document.createElement('input');
        roleIdInput.type = 'hidden';
        roleIdInput.name = 'role_id';
        roleIdInput.value = roleId;
        form.appendChild(roleIdInput);
        
        document.body.appendChild(form);
        form.submit();
    }
}




// Animation des cartes et initialisation DataTables
document.addEventListener('DOMContentLoaded', function() {
    // Initialiser DataTables
    if (document.getElementById('rolesTable')) {
        const table = $('#rolesTable').DataTable({
            "pageLength": 25,
            "lengthMenu": [[10, 25, 50, 100, -1], [10, 25, 50, 100, "Tous"]],
            "order": [[1, "asc"]], // Trier par nom par défaut
            "columnDefs": [
                { "orderable": false, "targets": [0, 3, 4, 6, 7] }, // Colonnes non triables
                { "searchable": false, "targets": [0, 4, 6, 7] }, // Colonnes non recherchables
                { "className": "text-center", "targets": [0, 5, 6] }, // Centrer certaines colonnes
                { "width": "50px", "targets": [0] }, // Largeur fixe pour les checkboxes
                { "width": "120px", "targets": [6, 7] } // Largeur fixe pour statut et actions
            ],
            "responsive": true,
            "dom": '<"row"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6"f>>' +
                   '<"row"<"col-sm-12"tr>>' +
                   '<"row"<"col-sm-12 col-md-5"i><"col-sm-12 col-md-7"p>>',
            "initComplete": function() {
                // Masquer le header de DataTables et utiliser notre header personnalisé
                $('.dataTables_length').hide();
                $('.dataTables_filter').hide();
                
        // Indicateur de chargement simplifié
        $('.dataTables_processing').html('Chargement des rôles...');
            },
            "processing": true,
            "language": {
                "processing": 'Chargement...',
                "emptyTable": "Aucun rôle trouvé",
                "info": "Affichage de _START_ à _END_ sur _TOTAL_ rôles",
                "infoEmpty": "Affichage de 0 à 0 sur 0 rôles",
                "infoFiltered": "(filtré sur _MAX_ rôles au total)",
                "lengthMenu": "Afficher _MENU_ rôles",
                "loadingRecords": "Chargement...",
                "search": "Rechercher:",
                "zeroRecords": "Aucun rôle correspondant trouvé",
                "paginate": {
                    "first": "Premier",
                    "last": "Dernier",
                    "next": "Suivant",
                    "previous": "Précédent"
                }
            }
        });
        
        // Synchroniser les checkboxes avec DataTables
        $('#selectAllCheckbox').on('change', function() {
            const isChecked = this.checked;
            $('.role-checkbox').prop('checked', isChecked);
            updateSelectedCount();
        });
        
        $('.role-checkbox').on('change', function() {
            updateSelectedCount();
            updateMasterCheckbox();
        });
        
        // Recherche personnalisée
        $('#customSearch').on('keyup', function() {
            table.search(this.value).draw();
        });
    }
    
    // Interface optimisée - affichage direct sans animations pour de meilleures performances
});

// Fonctions de filtrage par statut
function filterByStatus(status) {
    const table = $('#rolesTable').DataTable();
    
    // Retirer les classes actives des boutons
    $('.btn-group .btn').removeClass('active');
    
    if (status === 'all') {
        table.column(6).search('').draw(); // Colonne statut
        $('.btn-group .btn:first').addClass('active');
    } else if (status === 'active') {
        table.column(6).search('Actif').draw();
        $('.btn-group .btn:nth-child(2)').addClass('active');
    } else if (status === 'inactive') {
        table.column(6).search('Inactif').draw();
        $('.btn-group .btn:nth-child(3)').addClass('active');
    }
}

// Auto-refresh des statistiques désactivé pour améliorer les performances

// Fonctions pour gérer les permissions détaillées
function toggleModulePermissions(moduleKey, checked) {
    // Cocher/décocher toutes les permissions d'un module
    const checkboxes = document.querySelectorAll(`input[name="permissions[]"][value^="${moduleKey}:"]`);
    checkboxes.forEach(checkbox => {
        checkbox.checked = checked;
    });
    
    // Mettre à jour l'état des boutons de page
    updatePageButtonsState(moduleKey);
    
    // Mettre à jour les indicateurs visuels
    updateVisualIndicators(moduleKey);
}

function togglePagePermissions(moduleKey, pageKey, checked) {
    // Cocher/décocher toutes les permissions d'une page spécifique
    const checkboxes = document.querySelectorAll(`input[name="permissions[]"][value^="${moduleKey}:${pageKey}:"]`);
    checkboxes.forEach(checkbox => {
        checkbox.checked = checked;
    });
    
    // Mettre à jour l'état des boutons de page
    updatePageButtonsState(moduleKey, pageKey);
    
    // Mettre à jour les indicateurs visuels
    updateVisualIndicators(moduleKey);
}

function updatePageButtonsState(moduleKey, pageKey = null) {
    if (pageKey) {
        // Mettre à jour l'état d'une page spécifique
        const pageCheckboxes = document.querySelectorAll(`input[name="permissions[]"][value^="${moduleKey}:${pageKey}:"]`);
        const checkedCount = Array.from(pageCheckboxes).filter(cb => cb.checked).length;
        const totalCount = pageCheckboxes.length;
        
        // Trouver les boutons de cette page
        const pageButtons = document.querySelectorAll(`button[onclick*="togglePagePermissions('${moduleKey}', '${pageKey}'"]`);
        pageButtons.forEach(button => {
            if (button.onclick.toString().includes('true')) {
                // Bouton "Cocher tout"
                button.disabled = checkedCount === totalCount;
                button.classList.toggle('btn-success', checkedCount === totalCount);
                button.classList.toggle('btn-outline-success', checkedCount !== totalCount);
            } else {
                // Bouton "Décocher tout"
                button.disabled = checkedCount === 0;
                button.classList.toggle('btn-warning', checkedCount === 0);
                button.classList.toggle('btn-outline-warning', checkedCount !== 0);
            }
        });
    } else {
        // Mettre à jour l'état de toutes les pages du module
        const moduleCheckboxes = document.querySelectorAll(`input[name="permissions[]"][value^="${moduleKey}:"]`);
        const checkedCount = Array.from(moduleCheckboxes).filter(cb => cb.checked).length;
        const totalCount = moduleCheckboxes.length;
        
        // Trouver les boutons du module
        const moduleButtons = document.querySelectorAll(`button[onclick*="toggleModulePermissions('${moduleKey}'"]`);
        moduleButtons.forEach(button => {
            if (button.onclick.toString().includes('true')) {
                // Bouton "Cocher tout le module"
                button.disabled = checkedCount === totalCount;
                button.classList.toggle('btn-primary', checkedCount === totalCount);
                button.classList.toggle('btn-outline-primary', checkedCount !== totalCount);
            } else {
                // Bouton "Décocher tout le module"
                button.disabled = checkedCount === 0;
                button.classList.toggle('btn-secondary', checkedCount === 0);
                button.classList.toggle('btn-outline-secondary', checkedCount !== 0);
            }
        });
        
        // Mettre à jour aussi tous les boutons de pages du module
        const allPageButtons = document.querySelectorAll(`button[onclick*="togglePagePermissions('${moduleKey}'"]`);
        allPageButtons.forEach(button => {
            const onclick = button.onclick.toString();
            const pageKeyMatch = onclick.match(/togglePagePermissions\('([^']+)',\s*'([^']+)'/);
            if (pageKeyMatch) {
                updatePageButtonsState(moduleKey, pageKeyMatch[2]);
            }
        });
    }
}

// Fonction pour mettre à jour les indicateurs visuels
function updateVisualIndicators(moduleKey) {
    // Mettre à jour l'indicateur du module
    const moduleCheckboxes = document.querySelectorAll(`input[name="permissions[]"][value^="${moduleKey}:"]`);
    const checkedCount = Array.from(moduleCheckboxes).filter(cb => cb.checked).length;
    const totalCount = moduleCheckboxes.length;
    
    // Trouver l'accordion du module
    const moduleAccordion = document.querySelector(`button[onclick*="toggleModulePermissions('${moduleKey}'"]`)?.closest('.accordion-item');
    if (moduleAccordion) {
        if (checkedCount === totalCount && totalCount > 0) {
            moduleAccordion.classList.add('module-checked');
        } else {
            moduleAccordion.classList.remove('module-checked');
        }
    }
    
    // Mettre à jour les indicateurs des pages
    const pageCards = document.querySelectorAll(`button[onclick*="togglePagePermissions('${moduleKey}'"]`);
    pageCards.forEach(button => {
        const onclick = button.onclick.toString();
        const pageKeyMatch = onclick.match(/togglePagePermissions\('([^']+)',\s*'([^']+)'/);
        if (pageKeyMatch) {
            const pageKey = pageKeyMatch[2];
            const pageCheckboxes = document.querySelectorAll(`input[name="permissions[]"][value^="${moduleKey}:${pageKey}:"]`);
            const pageCheckedCount = Array.from(pageCheckboxes).filter(cb => cb.checked).length;
            const pageTotalCount = pageCheckboxes.length;
            
            const pageCard = button.closest('.card');
            if (pageCard) {
                if (pageCheckedCount === pageTotalCount && pageTotalCount > 0) {
                    pageCard.classList.add('page-checked');
                } else {
                    pageCard.classList.remove('page-checked');
                }
            }
        }
    });
}

// Initialiser l'état des boutons au chargement de la page
document.addEventListener('DOMContentLoaded', function() {
        // Initialiser immédiatement sans délai
        const modules = <?php echo json_encode(array_keys($detailed_permissions)); ?>;
        modules.forEach(moduleKey => {
            updatePageButtonsState(moduleKey);
            updateVisualIndicators(moduleKey);
        });
        
        // Ajouter des gestionnaires d'événements pour les checkboxes de permissions
        const permissionCheckboxes = document.querySelectorAll('input[name="permissions[]"]');
        permissionCheckboxes.forEach(checkbox => {
            checkbox.addEventListener('change', function() {
                const value = this.value;
                const parts = value.split(':');
                if (parts.length >= 2) {
                    const moduleKey = parts[0];
                    updatePageButtonsState(moduleKey);
                    updateVisualIndicators(moduleKey);
                }
            });
        });
});
</script>

<!-- DataTables Scripts -->
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.1/js/dataTables.buttons.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.bootstrap5.min.js"></script>
<script src="https://cdn.datatables.net/responsive/2.5.0/js/dataTables.responsive.min.js"></script>
<script src="https://cdn.datatables.net/responsive/2.5.0/js/responsive.bootstrap5.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/pdfmake.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/vfs_fonts.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.html5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.print.min.js"></script>

<?php include '../includes/footer.php'; ?>
