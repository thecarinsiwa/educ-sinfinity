<?php
/**
 * Administration - Ajouter un rôle
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
if (!checkUserPermission('users', 'create') && !checkPermission('admin')) {
    showMessage('error', 'Accès refusé à ce module.');
    redirectTo('../dashboard.php');
}

$page_title = 'Administration - Ajouter un rôle';

$errors = [];
$success = false;

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nom = sanitizeInput($_POST['nom'] ?? '');
    $description = sanitizeInput($_POST['description'] ?? '');
    $permissions = $_POST['permissions'] ?? [];
    $actif = (int)($_POST['actif'] ?? 1);
    
    // Validation
    if (empty($nom)) {
        $errors[] = 'Le nom du rôle est obligatoire';
    }
    
    if (strlen($nom) > 100) {
        $errors[] = 'Le nom du rôle ne peut pas dépasser 100 caractères';
    }
    
    // Vérifier que le nom du rôle n'existe pas
    if (empty($errors)) {
        $existing = $database->query(
            "SELECT id FROM roles WHERE nom = ?",
            [$nom]
        )->fetch();
        
        if ($existing) {
            $errors[] = 'Ce nom de rôle existe déjà';
        }
    }
    
    // Si pas d'erreurs, créer le rôle
    if (empty($errors)) {
        try {
            // Organiser les permissions selon le format JSON attendu
            $permissions_organized = [];
            foreach ($permissions as $permission) {
                $parts = explode(':', $permission);
                if (count($parts) >= 3) {
                    $module = $parts[0];
                    $page_path = $parts[1];
                    $action = $parts[2];
                    
                    // Initialiser le module s'il n'existe pas
                    if (!isset($permissions_organized[$module])) {
                        $module_data = $module_permissions[$module] ?? null;
                        $permissions_organized[$module] = [
                            'name' => $module_data['name'] ?? ucfirst($module),
                            'pages' => []
                        ];
                    }
                    
                    // Gérer les pages avec sous-pages (ex: roles/manage)
                    $page_parts = explode('/', $page_path);
                    
                    if (count($page_parts) == 1) {
                        // Page simple
                        $page_key = $page_parts[0];
                        if (!isset($permissions_organized[$module]['pages'][$page_key])) {
                            $page_name = ucwords(str_replace(['_', '-'], [' ', ' '], $page_key));
                            $permissions_organized[$module]['pages'][$page_key] = [
                                'name' => $page_name,
                                'permissions' => []
                            ];
                        }
                        
                        if (!in_array($action, $permissions_organized[$module]['pages'][$page_key]['permissions'])) {
                            $permissions_organized[$module]['pages'][$page_key]['permissions'][] = $action;
                        }
                    } else {
                        // Page avec sous-pages (ex: roles/manage)
                        $parent_page = $page_parts[0];
                        $sub_page = $page_parts[1];
                        
                        if (!isset($permissions_organized[$module]['pages'][$parent_page])) {
                            $parent_name = ucwords(str_replace(['_', '-'], [' ', ' '], $parent_page));
                            $permissions_organized[$module]['pages'][$parent_page] = [
                                'name' => $parent_name,
                                'pages' => []
                            ];
                        }
                        
                        if (!isset($permissions_organized[$module]['pages'][$parent_page]['pages'][$sub_page])) {
                            $sub_name = ucwords(str_replace(['_', '-'], [' ', ' '], $sub_page));
                            $permissions_organized[$module]['pages'][$parent_page]['pages'][$sub_page] = [
                                'name' => $sub_name,
                                'permissions' => []
                            ];
                        }
                        
                        if (!in_array($action, $permissions_organized[$module]['pages'][$parent_page]['pages'][$sub_page]['permissions'])) {
                            $permissions_organized[$module]['pages'][$parent_page]['pages'][$sub_page]['permissions'][] = $action;
                        }
                    }
                }
            }
            
            $permissions_json = json_encode($permissions_organized, JSON_UNESCAPED_UNICODE);
            
            // Créer le rôle
            $role_id = $database->execute(
                "INSERT INTO roles (nom, description, permissions, actif, date_creation) VALUES (?, ?, ?, ?, NOW())",
                [$nom, $description, $permissions_json, $actif]
            );
            
            // Enregistrer l'action
            if (function_exists('logUserAction')) {
                logUserAction(
                    'create_role',
                    'admin',
                    'Rôle créé: ' . $nom,
                    $role_id
                );
            }
            
            $success = true;
            showMessage('success', 'Rôle créé avec succès');
            
            // Rediriger vers la page de modification du nouveau rôle
            redirectTo('roles_edit.php?id=' . $role_id);
            
        } catch (Exception $e) {
            $errors[] = 'Erreur lors de la création du rôle: ' . $e->getMessage();
        }
    }
}

// Utiliser la nouvelle structure des permissions
$module_permissions = getModulePermissionsStructure();
$available_actions = getModuleAvailableActions();

include '../includes/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">
        <i class="fas fa-user-tag me-2"></i>
        Ajouter un nouveau rôle
    </h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <div class="btn-group me-2">
            <a href="roles.php" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-1"></i>
                Retour à la liste
            </a>
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

<div class="row">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-plus me-2"></i>
                    Informations du rôle
                </h5>
            </div>
            <div class="card-body">
                <form method="POST" class="needs-validation" novalidate>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="nom" class="form-label">Nom du rôle <span class="text-danger">*</span></label>
                            <input type="text"
                                   class="form-control"
                                   id="nom"
                                   name="nom"
                                   value="<?php echo htmlspecialchars($_POST['nom'] ?? ''); ?>"
                                   required
                                   maxlength="100">
                            <div class="invalid-feedback">
                                Veuillez saisir un nom de rôle valide.
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="actif" class="form-label">Statut</label>
                            <select class="form-select" id="actif" name="actif">
                                <option value="1" <?php echo ($_POST['actif'] ?? 1) == 1 ? 'selected' : ''; ?>>Actif</option>
                                <option value="0" <?php echo ($_POST['actif'] ?? 1) == 0 ? 'selected' : ''; ?>>Inactif</option>
                            </select>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="description" class="form-label">Description</label>
                        <textarea class="form-control"
                                  id="description"
                                  name="description"
                                  rows="3"
                                  placeholder="Description du rôle et de ses responsabilités..."><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Permissions détaillées</label>
                        
                        <!-- Contrôles globaux -->
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <button type="button" class="btn btn-sm btn-outline-primary" id="selectAllModules">
                                    <i class="fas fa-check-square"></i> Sélectionner tous les modules
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-secondary ms-2" id="deselectAllModules">
                                    <i class="fas fa-square"></i> Désélectionner tous
                                </button>
                            </div>
                            <div class="col-md-6 text-end">
                                <small class="text-muted">
                                    <i class="fas fa-info-circle"></i>
                                    Total: <strong><?php echo count($module_permissions); ?></strong> modules, 
                                    <strong><?php echo array_sum(array_map(function($m) { return count($m['pages']); }, $module_permissions)); ?></strong> pages
                                </small>
                            </div>
                        </div>

                        <div class="accordion" id="permissionsAccordion">
                            <?php
                            $module_index = 0;
                            foreach ($module_permissions as $module_key => $module): ?>
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
                                            
                                            <!-- Contrôles du module -->
                                            <div class="row mb-3">
                                                <div class="col-md-6">
                                                    <button type="button" class="btn btn-sm btn-outline-primary select-module-all" data-module="<?php echo $module_index; ?>">
                                                        <i class="fas fa-check-square"></i> Tout sélectionner
                                                    </button>
                                                    <button type="button" class="btn btn-sm btn-outline-secondary ms-1 deselect-module-all" data-module="<?php echo $module_index; ?>">
                                                        <i class="fas fa-square"></i> Tout désélectionner
                                                    </button>
                                                </div>
                                            </div>

                                            <div class="row">
                                                <?php 
                                                $page_index = 0;
                                                foreach ($module['pages'] as $page_key => $page_actions): ?>
                                                    <div class="col-lg-4 col-md-6 mb-3">
                                                        <div class="card border-light">
                                                            <div class="card-header bg-light">
                                                                <h6 class="mb-0 text-truncate" title="<?php echo ucwords(str_replace(['/', '_', '-'], [' ', ' ', ' '], $page_key)); ?>">
                                                                    <i class="fas fa-file-alt me-1"></i>
                                                                    <?php echo ucwords(str_replace(['/', '_', '-'], [' ', ' ', ' '], $page_key)); ?>
                                                                </h6>
                                                            </div>
                                                            <div class="card-body p-2">
                                                                <?php foreach ($page_actions as $action): ?>
                                                                    <div class="form-check form-check-sm">
                                                                        <input class="form-check-input"
                                                                               type="checkbox"
                                                                               name="permissions[]"
                                                                               value="<?php echo $module_key . ':' . $page_key . ':' . $action; ?>"
                                                                               id="perm_<?php echo $module_key . '_' . $page_key . '_' . $action; ?>">
                                                                        <label class="form-check-label small" for="perm_<?php echo $module_key . '_' . $page_key . '_' . $action; ?>">
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
                                                    <?php $page_index++; ?>
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
                            Créer le rôle
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <!-- Informations sur les rôles -->
        <div class="card mb-4">
            <div class="card-header">
                <h6 class="mb-0">
                    <i class="fas fa-info-circle me-2"></i>
                    À propos des rôles
                </h6>
            </div>
            <div class="card-body">
                <p class="text-muted small">
                    Les rôles définissent les permissions d'accès aux différentes fonctionnalités de l'application. 
                    Chaque rôle peut avoir des permissions spécifiques pour chaque module et page.
                </p>
                <div class="row text-center">
                    <div class="col-6">
                        <h4 class="text-primary mb-0"><?php echo count($module_permissions); ?></h4>
                        <small class="text-muted">Modules</small>
                    </div>
                    <div class="col-6">
                        <h4 class="text-success mb-0"><?php echo count($available_actions); ?></h4>
                        <small class="text-muted">Actions</small>
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
        <div class="card">
            <div class="card-header">
                <h6 class="mb-0">
                    <i class="fas fa-bolt me-2"></i>
                    Actions rapides
                </h6>
            </div>
            <div class="card-body">
                <div class="d-grid gap-2">
                    <a href="roles.php" class="btn btn-outline-primary btn-sm">
                        <i class="fas fa-list me-1"></i>
                        Voir tous les rôles
                    </a>
                    <a href="roles_bulk.php" class="btn btn-outline-warning btn-sm">
                        <i class="fas fa-tasks me-1"></i>
                        Actions en lot
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Validation Bootstrap
(function() {
    'use strict';
    window.addEventListener('load', function() {
        var forms = document.getElementsByClassName('needs-validation');
        var validation = Array.prototype.filter.call(forms, function(form) {
            form.addEventListener('submit', function(event) {
                if (form.checkValidity() === false) {
                    event.preventDefault();
                    event.stopPropagation();
                }
                form.classList.add('was-validated');
            }, false);
        });
    }, false);
})();

// Sélection/désélection en masse
document.addEventListener('DOMContentLoaded', function() {
    
    // Sélection/désélection globale
    document.getElementById('selectAllModules').addEventListener('click', function() {
        const allCheckboxes = document.querySelectorAll('input[name="permissions[]"]');
        allCheckboxes.forEach(cb => cb.checked = true);
    });
    
    document.getElementById('deselectAllModules').addEventListener('click', function() {
        const allCheckboxes = document.querySelectorAll('input[name="permissions[]"]');
        allCheckboxes.forEach(cb => cb.checked = false);
    });
    
    // Sélection/désélection par module
    document.querySelectorAll('.select-module-all').forEach(btn => {
        btn.addEventListener('click', function() {
            const moduleIndex = this.getAttribute('data-module');
            const collapse = document.querySelector(`#collapse${moduleIndex}`);
            const checkboxes = collapse.querySelectorAll('input[type="checkbox"]');
            checkboxes.forEach(cb => cb.checked = true);
        });
    });
    
    document.querySelectorAll('.deselect-module-all').forEach(btn => {
        btn.addEventListener('click', function() {
            const moduleIndex = this.getAttribute('data-module');
            const collapse = document.querySelector(`#collapse${moduleIndex}`);
            const checkboxes = collapse.querySelectorAll('input[type="checkbox"]');
            checkboxes.forEach(cb => cb.checked = false);
        });
    });
    
    // Compteur de sélections en temps réel
    function updateSelectionCount() {
        const selectedCount = document.querySelectorAll('input[name="permissions[]"]:checked').length;
        const totalCount = document.querySelectorAll('input[name="permissions[]"]').length;
        
        // Mettre à jour l'affichage si un élément existe
        const countElement = document.getElementById('selectionCount');
        if (countElement) {
            countElement.textContent = `${selectedCount}/${totalCount} permissions sélectionnées`;
        }
    }
    
    // Écouter les changements sur toutes les checkboxes
    document.querySelectorAll('input[name="permissions[]"]').forEach(checkbox => {
        checkbox.addEventListener('change', updateSelectionCount);
    });
    
    // Initialiser le compteur
    updateSelectionCount();
});
</script>

<?php include '../includes/footer.php'; ?>