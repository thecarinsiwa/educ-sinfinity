<?php
/**
 * Administration - Modifier un rôle
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

$page_title = 'Administration - Modifier un rôle';

$errors = [];
$success = false;
$role = null;

// Utiliser la nouvelle structure des permissions
$module_permissions = getModulePermissionsStructure();
$available_actions = getModuleAvailableActions();

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
    
    // Vérifier que le nom du rôle n'existe pas (sauf pour le rôle actuel)
    if (empty($errors)) {
        $existing = $database->query(
            "SELECT id FROM roles WHERE nom = ? AND id != ?",
            [$nom, $role_id]
        )->fetch();
        
        if ($existing) {
            $errors[] = 'Ce nom de rôle existe déjà';
        }
    }
    
    // Si pas d'erreurs, modifier le rôle
    if (empty($errors)) {
        try {
            // Organiser les permissions selon le format JSON attendu (logique simplifiée)
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
                    
                    // Gérer les pages - toutes les pages sont traitées comme des pages simples
                    // car la structure des permissions utilise des noms de pages avec slashes
                    $page_key = $page_path;
                    if (!isset($permissions_organized[$module]['pages'][$page_key])) {
                        $page_name = translatePageName($page_key);
                        $permissions_organized[$module]['pages'][$page_key] = [
                            'name' => $page_name,
                            'permissions' => []
                        ];
                    }
                    
                    if (!in_array($action, $permissions_organized[$module]['pages'][$page_key]['permissions'])) {
                        $permissions_organized[$module]['pages'][$page_key]['permissions'][] = $action;
                    }
                }
            }
            
            $permissions_json = json_encode($permissions_organized, JSON_UNESCAPED_UNICODE);
            
            // Modifier le rôle
            $database->execute(
                "UPDATE roles SET nom = ?, description = ?, permissions = ?, actif = ?, date_modification = NOW() WHERE id = ?",
                [$nom, $description, $permissions_json, $actif, $role_id]
            );
            
            // Enregistrer l'action
            if (function_exists('logUserAction')) {
                logUserAction(
                    'update_role',
                    'admin',
                    'Rôle modifié: ' . $nom,
                    $role_id
                );
            }
            
            $success = true;
            showMessage('success', 'Rôle modifié avec succès');
            
            // Mettre à jour les données du rôle pour l'affichage
            $role['nom'] = $nom;
            $role['description'] = $description;
            $role['permissions'] = $permissions_json;
            $role['actif'] = $actif;
            
        } catch (Exception $e) {
            $errors[] = 'Erreur lors de la modification du rôle: ' . $e->getMessage();
        }
    }
}

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
} else {
    // Si pas de permissions existantes, initialiser avec les permissions de la structure
    foreach ($module_permissions as $module_key => $module) {
        foreach ($module['pages'] as $page_key => $page_actions) {
            foreach ($page_actions as $action) {
                $permission_key = $module_key . ':' . $page_key . ':' . $action;
                $existing_permissions[] = $permission_key;
            }
        }
    }
}

include '../includes/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">
        <i class="fas fa-user-tag me-2"></i>
        Modifier le rôle : <?php echo htmlspecialchars($role['nom']); ?>
    </h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <div class="btn-group me-2">
            <a href="roles.php" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-1"></i>
                Retour à la liste
            </a>
            <a href="roles_view.php?id=<?php echo $role_id; ?>" class="btn btn-outline-info">
                <i class="fas fa-eye me-1"></i>
                Voir le rôle
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

<?php if ($success): ?>
    <div class="alert alert-success">
        <h6><i class="fas fa-check-circle me-2"></i>Succès !</h6>
        <p class="mb-0">Le rôle a été modifié avec succès.</p>
    </div>
<?php endif; ?>

<div class="row">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-edit me-2"></i>
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
                                   value="<?php echo htmlspecialchars($_POST['nom'] ?? $role['nom']); ?>"
                                   required
                                   maxlength="100">
                            <div class="invalid-feedback">
                                Veuillez saisir un nom de rôle valide.
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="actif" class="form-label">Statut</label>
                            <select class="form-select" id="actif" name="actif">
                                <option value="1" <?php echo ($_POST['actif'] ?? $role['actif']) == 1 ? 'selected' : ''; ?>>Actif</option>
                                <option value="0" <?php echo ($_POST['actif'] ?? $role['actif']) == 0 ? 'selected' : ''; ?>>Inactif</option>
                            </select>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="description" class="form-label">Description</label>
                        <textarea class="form-control"
                                  id="description"
                                  name="description"
                                  rows="3"
                                  placeholder="Description du rôle et de ses responsabilités..."><?php echo htmlspecialchars($_POST['description'] ?? $role['description']); ?></textarea>
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
                                                                <h6 class="mb-0 text-truncate" title="<?php echo translatePageName($page_key); ?>">
                                                                    <i class="fas fa-file-alt me-1"></i>
                                                                    <?php echo translatePageName($page_key); ?>
                                                                </h6>
                                                            </div>
                                                            <div class="card-body p-2">
                                                                <?php foreach ($page_actions as $action): ?>
                                                                    <?php 
                                                                    $permission_key = $module_key . ':' . $page_key . ':' . $action;
                                                                    $is_checked = in_array($permission_key, $existing_permissions);
                                                                    ?>
                                                                    <div class="form-check form-check-sm">
                                                                        <input class="form-check-input"
                                                                               type="checkbox"
                                                                               name="permissions[]"
                                                                               value="<?php echo $permission_key; ?>"
                                                                               id="perm_<?php echo $module_key . '_' . $page_key . '_' . $action; ?>"
                                                                               <?php echo $is_checked ? 'checked' : ''; ?>>
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
                            Modifier le rôle
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
                    Informations du rôle
                </h6>
            </div>
            <div class="card-body">
                <table class="table table-sm">
                    <tr>
                        <td><strong>ID :</strong></td>
                        <td><?php echo $role['id']; ?></td>
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
                    <a href="roles_view.php?id=<?php echo $role_id; ?>" class="btn btn-outline-info btn-sm">
                        <i class="fas fa-eye me-1"></i>
                        Voir le rôle
                    </a>
                    <a href="roles_delete.php?id=<?php echo $role_id; ?>" class="btn btn-outline-danger btn-sm" 
                       onclick="return confirm('Êtes-vous sûr de vouloir supprimer ce rôle ?')">
                        <i class="fas fa-trash me-1"></i>
                        Supprimer le rôle
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
