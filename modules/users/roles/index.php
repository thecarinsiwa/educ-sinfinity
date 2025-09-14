<?php
/**
 * Module Gestion des Rôles avec Permissions - Page principale
 * Application de gestion scolaire - République Démocratique du Congo
 */

require_once '../../../config/config.php';
require_once '../../../config/database.php';
require_once '../../../includes/functions.php';
require_once '../../../includes/permissions-pages.php';

// Vérifier l'authentification et les permissions
requireLogin();
requirePagePermission('admin', 'roles/index', 'read', '../../dashboard.php');

$page_title = 'Gestion des Rôles et Permissions';

// Initialiser la connexion à la base de données
$database = new Database();
$database = $database->connect();

// Traitement des actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = sanitizeInput($_POST['action'] ?? '');
    
    try {
        switch ($action) {
            case 'create_role':
                $nom = sanitizeInput($_POST['nom'] ?? '');
                $description = sanitizeInput($_POST['description'] ?? '');
                $actif = isset($_POST['actif']) ? 1 : 0;
                $permissions = $_POST['permissions'] ?? [];
                
                if (!$nom) {
                    throw new Exception('Le nom du rôle est obligatoire');
                }
                
                // Vérifier que le nom du rôle n'existe pas
                $existing = $database->query(
                    "SELECT id FROM roles WHERE nom = ?",
                    [$nom]
                )->fetch();
                
                if ($existing) {
                    throw new Exception('Ce nom de rôle existe déjà');
                }
                
                // Préparer les permissions
                $permissions_json = json_encode($permissions, JSON_UNESCAPED_UNICODE);
                
                $database->query(
                    "INSERT INTO roles (nom, description, actif, permissions) VALUES (?, ?, ?, ?)",
                    [$nom, $description, $actif, $permissions_json]
                );
                
                logAction('create_role', "Rôle créé: $nom", ['role_nom' => $nom]);
                showMessage('success', 'Rôle créé avec succès');
                break;
                
            case 'update_role':
                $id = (int)($_POST['id'] ?? 0);
                $nom = sanitizeInput($_POST['nom'] ?? '');
                $description = sanitizeInput($_POST['description'] ?? '');
                $actif = isset($_POST['actif']) ? 1 : 0;
                $permissions = $_POST['permissions'] ?? [];
                
                if (!$id || !$nom) {
                    throw new Exception('Données invalides');
                }
                
                // Vérifier que le nom du rôle n'existe pas (sauf pour le rôle actuel)
                $existing = $database->query(
                    "SELECT id FROM roles WHERE nom = ? AND id != ?",
                    [$nom, $id]
                )->fetch();
                
                if ($existing) {
                    throw new Exception('Ce nom de rôle existe déjà');
                }
                
                // Préparer les permissions
                $permissions_json = json_encode($permissions, JSON_UNESCAPED_UNICODE);
                
                $database->query(
                    "UPDATE roles SET nom = ?, description = ?, actif = ?, permissions = ? WHERE id = ?",
                    [$nom, $description, $actif, $permissions_json, $id]
                );
                
                logAction('update_role', "Rôle modifié: $nom", ['role_id' => $id, 'role_nom' => $nom]);
                showMessage('success', 'Rôle modifié avec succès');
                break;
                
            case 'toggle_status':
                $id = (int)($_POST['id'] ?? 0);
                
                if (!$id) {
                    throw new Exception('ID invalide');
                }
                
                // Récupérer le rôle actuel
                $role = $database->query(
                    "SELECT nom, actif FROM roles WHERE id = ?",
                    [$id]
                )->fetch();
                
                if (!$role) {
                    throw new Exception('Rôle introuvable');
                }
                
                $new_status = $role['actif'] ? 0 : 1;
                $database->query(
                    "UPDATE roles SET actif = ? WHERE id = ?",
                    [$new_status, $id]
                );
                
                $status_text = $new_status ? 'activé' : 'désactivé';
                logAction('toggle_role_status', "Rôle {$status_text}: {$role['nom']}", ['role_id' => $id]);
                showMessage('success', "Rôle {$status_text} avec succès");
                break;
        }
    } catch (Exception $e) {
        showMessage('error', $e->getMessage());
    }
}

// Récupération des rôles
$roles = $database->query(
    "SELECT r.*, 
            COUNT(u.id) as nb_utilisateurs,
            COUNT(CASE WHEN u.status = 'actif' THEN 1 END) as nb_utilisateurs_actifs
     FROM roles r
     LEFT JOIN users u ON r.id = u.role_id
     GROUP BY r.id
     ORDER BY r.nom"
)->fetchAll();

// Statistiques
$stats = [
    'total_roles' => count($roles),
    'active_roles' => count(array_filter($roles, fn($r) => $r['actif'])),
    'inactive_roles' => count(array_filter($roles, fn($r) => !$r['actif'])),
    'total_users_with_roles' => array_sum(array_column($roles, 'nb_utilisateurs'))
];

include '../../../includes/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">
        <i class="fas fa-shield-alt me-2"></i>
        Gestion des Rôles et Permissions
    </h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addRoleModal">
            <i class="fas fa-plus me-1"></i>
            Nouveau Rôle
        </button>
    </div>
</div>

<!-- Statistiques -->
<div class="row mb-4">
    <div class="col-lg-3 col-md-6 mb-3">
        <div class="card text-white bg-primary">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h4><?php echo $stats['total_roles']; ?></h4>
                        <p class="mb-0">Total rôles</p>
                    </div>
                    <div class="align-self-center">
                        <i class="fas fa-shield-alt fa-2x"></i>
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
                        <h4><?php echo $stats['active_roles']; ?></h4>
                        <p class="mb-0">Rôles actifs</p>
                    </div>
                    <div class="align-self-center">
                        <i class="fas fa-check-circle fa-2x"></i>
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
                        <h4><?php echo $stats['total_users_with_roles']; ?></h4>
                        <p class="mb-0">Utilisateurs assignés</p>
                    </div>
                    <div class="align-self-center">
                        <i class="fas fa-users fa-2x"></i>
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
                        <h4><?php echo count(getAvailableModules()); ?></h4>
                        <p class="mb-0">Modules disponibles</p>
                    </div>
                    <div class="align-self-center">
                        <i class="fas fa-cogs fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Liste des rôles -->
<div class="card">
    <div class="card-header">
        <h5 class="mb-0">
            <i class="fas fa-list me-2"></i>
            Liste des Rôles et Permissions
        </h5>
    </div>
    <div class="card-body">
        <?php if (!empty($roles)): ?>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Rôle</th>
                            <th>Description</th>
                            <th>Statut</th>
                            <th>Utilisateurs</th>
                            <th>Permissions</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($roles as $role): ?>
                            <?php 
                            $permissions = json_decode($role['permissions'], true) ?: [];
                            ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($role['nom']); ?></strong>
                                </td>
                                <td>
                                    <?php echo htmlspecialchars($role['description'] ?: 'Aucune description'); ?>
                                </td>
                                <td>
                                    <span class="badge bg-<?php echo $role['actif'] ? 'success' : 'secondary'; ?>">
                                        <?php echo $role['actif'] ? 'Actif' : 'Inactif'; ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge bg-primary"><?php echo $role['nb_utilisateurs']; ?> total</span>
                                    <?php if ($role['nb_utilisateurs_actifs'] > 0): ?>
                                        <span class="badge bg-success"><?php echo $role['nb_utilisateurs_actifs']; ?> actifs</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!empty($permissions)): ?>
                                        <div class="permissions-preview">
                                            <?php 
                                            $module_count = 0;
                                            foreach ($permissions as $module => $actions) {
                                                if (!empty($actions)) {
                                                    $module_count++;
                                                    if ($module_count <= 3) {
                                                        echo "<span class='badge bg-info me-1' title='" . implode(', ', $actions) . "'>" . ucfirst($module) . "</span>";
                                                    }
                                                }
                                            }
                                            if ($module_count > 3) {
                                                echo "<span class='badge bg-secondary'>+{$module_count} modules</span>";
                                            }
                                            ?>
                                        </div>
                                    <?php else: ?>
                                        <span class="text-muted">Aucune permission</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <button type="button" class="btn btn-outline-primary" 
                                                onclick="editRole(<?php echo htmlspecialchars(json_encode($role)); ?>)"
                                                title="Modifier">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button type="button" class="btn btn-outline-info" 
                                                onclick="viewPermissions(<?php echo htmlspecialchars(json_encode($permissions)); ?>, '<?php echo htmlspecialchars($role['nom']); ?>')"
                                                title="Voir les permissions">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <form method="POST" class="d-inline" 
                                              onsubmit="return confirm('Êtes-vous sûr de vouloir <?php echo $role['actif'] ? 'désactiver' : 'activer'; ?> ce rôle ?')">
                                            <input type="hidden" name="action" value="toggle_status">
                                            <input type="hidden" name="id" value="<?php echo $role['id']; ?>">
                                            <button type="submit" class="btn btn-outline-<?php echo $role['actif'] ? 'warning' : 'success'; ?>" 
                                                    title="<?php echo $role['actif'] ? 'Désactiver' : 'Activer'; ?>">
                                                <i class="fas fa-<?php echo $role['actif'] ? 'pause' : 'play'; ?>"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="text-center py-4">
                <i class="fas fa-shield-alt fa-3x text-muted mb-3"></i>
                <p class="text-muted">Aucun rôle trouvé</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Modal Ajout de rôle -->
<div class="modal fade" id="addRoleModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-plus me-2"></i>
                        Nouveau Rôle
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" value="create_role">
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="nom" class="form-label">Nom du rôle <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="nom" name="nom" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-check mt-4">
                                <input class="form-check-input" type="checkbox" id="actif" name="actif" checked>
                                <label class="form-check-label" for="actif">
                                    Rôle actif
                                </label>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="description" class="form-label">Description</label>
                        <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Permissions</label>
                        <div class="permissions-grid">
                            <?php 
                            $modules = getAvailableModules();
                            $actions = getAvailableActions();
                            
                            foreach ($modules as $module_key => $module_label): 
                            ?>
                                <div class="card mb-3">
                                    <div class="card-header">
                                        <h6 class="mb-0"><?php echo $module_label; ?></h6>
                                    </div>
                                    <div class="card-body">
                                        <div class="row">
                                            <?php foreach ($actions as $action_key => $action_label): ?>
                                                <div class="col-md-3">
                                                    <div class="form-check">
                                                        <input class="form-check-input" type="checkbox" 
                                                               id="permissions_<?php echo $module_key; ?>_<?php echo $action_key; ?>" 
                                                               name="permissions[<?php echo $module_key; ?>][]" 
                                                               value="<?php echo $action_key; ?>">
                                                        <label class="form-check-label" for="permissions_<?php echo $module_key; ?>_<?php echo $action_key; ?>">
                                                            <?php echo $action_label; ?>
                                                        </label>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-1"></i>
                        Créer le rôle
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Modification de rôle -->
<div class="modal fade" id="editRoleModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-edit me-2"></i>
                        Modifier le Rôle
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" value="update_role">
                    <input type="hidden" name="id" id="edit_id">
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="edit_nom" class="form-label">Nom du rôle <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="edit_nom" name="nom" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-check mt-4">
                                <input class="form-check-input" type="checkbox" id="edit_actif" name="actif">
                                <label class="form-check-label" for="edit_actif">
                                    Rôle actif
                                </label>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_description" class="form-label">Description</label>
                        <textarea class="form-control" id="edit_description" name="description" rows="3"></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Permissions</label>
                        <div class="permissions-grid">
                            <?php 
                            foreach ($modules as $module_key => $module_label): 
                            ?>
                                <div class="card mb-3">
                                    <div class="card-header">
                                        <h6 class="mb-0"><?php echo $module_label; ?></h6>
                                    </div>
                                    <div class="card-body">
                                        <div class="row">
                                            <?php foreach ($actions as $action_key => $action_label): ?>
                                                <div class="col-md-3">
                                                    <div class="form-check">
                                                        <input class="form-check-input" type="checkbox" 
                                                               id="edit_permissions_<?php echo $module_key; ?>_<?php echo $action_key; ?>" 
                                                               name="permissions[<?php echo $module_key; ?>][]" 
                                                               value="<?php echo $action_key; ?>">
                                                        <label class="form-check-label" for="edit_permissions_<?php echo $module_key; ?>_<?php echo $action_key; ?>">
                                                            <?php echo $action_label; ?>
                                                        </label>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-1"></i>
                        Modifier le rôle
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Affichage des permissions -->
<div class="modal fade" id="viewPermissionsModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-eye me-2"></i>
                    Permissions du Rôle
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="permissions-content">
                <!-- Contenu des permissions -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fermer</button>
            </div>
        </div>
    </div>
</div>

<style>
.permissions-grid .card {
    border: 1px solid #dee2e6;
}

.permissions-grid .card-header {
    background-color: #f8f9fa;
    border-bottom: 1px solid #dee2e6;
}

.permissions-preview .badge {
    font-size: 0.75em;
}
</style>

<script>
function editRole(role) {
    document.getElementById('edit_id').value = role.id;
    document.getElementById('edit_nom').value = role.nom;
    document.getElementById('edit_description').value = role.description || '';
    document.getElementById('edit_actif').checked = role.actif == 1;
    
    // Réinitialiser les permissions
    document.querySelectorAll('#editRoleModal input[type="checkbox"]').forEach(checkbox => {
        checkbox.checked = false;
    });
    
    // Définir les permissions existantes
    if (role.permissions) {
        const permissions = JSON.parse(role.permissions);
        for (const module in permissions) {
            permissions[module].forEach(action => {
                const checkbox = document.getElementById(`edit_permissions_${module}_${action}`);
                if (checkbox) {
                    checkbox.checked = true;
                }
            });
        }
    }
    
    var editModal = new bootstrap.Modal(document.getElementById('editRoleModal'));
    editModal.show();
}

function viewPermissions(permissions, roleName) {
    const content = document.getElementById('permissions-content');
    const modules = <?php echo json_encode(getAvailableModules()); ?>;
    const actions = <?php echo json_encode(getAvailableActions()); ?>;
    
    let html = `<h6>Rôle: <strong>${roleName}</strong></h6>`;
    html += '<table class="table table-bordered table-sm">';
    html += '<thead><tr><th>Module</th>';
    
    for (const actionKey in actions) {
        html += `<th>${actions[actionKey]}</th>`;
    }
    
    html += '</tr></thead><tbody>';
    
    for (const moduleKey in modules) {
        html += `<tr><td><strong>${modules[moduleKey]}</strong></td>`;
        
        for (const actionKey in actions) {
            const hasPermission = permissions[moduleKey] && permissions[moduleKey].includes(actionKey);
            const className = hasPermission ? 'table-success' : 'table-light';
            const icon = hasPermission ? '✅' : '❌';
            
            html += `<td class="${className} text-center">${icon}</td>`;
        }
        
        html += '</tr>';
    }
    
    html += '</tbody></table>';
    
    content.innerHTML = html;
    
    var viewModal = new bootstrap.Modal(document.getElementById('viewPermissionsModal'));
    viewModal.show();
}
</script>

<?php include '../../../includes/footer.php'; ?>