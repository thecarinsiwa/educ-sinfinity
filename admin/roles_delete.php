<?php
/**
 * Administration - Supprimer un rôle
 * Application de gestion scolaire - République Démocratique du Congo
 */

require_once '../config/config.php';

// Vérification de session robuste
require_once '../session_check.php';
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/permissions.php';

// Vérifier les permissions
if (!checkUserPermission('users', 'delete') && !checkPermission('admin')) {
    showMessage('error', 'Accès refusé à ce module.');
    redirectTo('../dashboard.php');
}

$page_title = 'Administration - Supprimer un rôle';

$errors = [];
$success = false;
$role = null;

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

// Vérifier si le rôle est utilisé par des utilisateurs
try {
    $user_count = $database->query(
        "SELECT COUNT(*) as count FROM users WHERE role_id = ?",
        [$role_id]
    )->fetch()['count'];
} catch (Exception $e) {
    $user_count = 0;
}

// Traitement du formulaire de suppression
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $confirm_delete = $_POST['confirm_delete'] ?? '';
    $reassign_role_id = (int)($_POST['reassign_role_id'] ?? 0);
    
    if ($confirm_delete !== 'DELETE') {
        $errors[] = 'Vous devez confirmer la suppression en tapant "DELETE"';
    }
    
    if ($user_count > 0 && $reassign_role_id <= 0) {
        $errors[] = 'Vous devez choisir un rôle de remplacement pour les utilisateurs';
    }
    
    if ($reassign_role_id > 0) {
        // Vérifier que le rôle de remplacement existe
        $reassign_role = $database->query(
            "SELECT id FROM roles WHERE id = ? AND id != ?",
            [$reassign_role_id, $role_id]
        )->fetch();
        
        if (!$reassign_role) {
            $errors[] = 'Le rôle de remplacement sélectionné n\'existe pas';
        }
    }
    
    // Si pas d'erreurs, supprimer le rôle
    if (empty($errors)) {
        try {
            $database->beginTransaction();
            
            // Réassigner les utilisateurs si nécessaire
            if ($user_count > 0 && $reassign_role_id > 0) {
                $database->execute(
                    "UPDATE users SET role_id = ? WHERE role_id = ?",
                    [$reassign_role_id, $role_id]
                );
            }
            
            // Supprimer le rôle
            $database->execute(
                "DELETE FROM roles WHERE id = ?",
                [$role_id]
            );
            
            // Enregistrer l'action
            if (function_exists('logUserAction')) {
                logUserAction(
                    'delete_role',
                    'admin',
                    'Rôle supprimé: ' . $role['nom'],
                    $role_id
                );
            }
            
            $database->commit();
            $success = true;
            showMessage('success', 'Rôle supprimé avec succès');
            
            // Rediriger vers la liste des rôles
            redirectTo('roles.php');
            
        } catch (Exception $e) {
            $database->rollback();
            $errors[] = 'Erreur lors de la suppression du rôle: ' . $e->getMessage();
        }
    }
}

// Récupérer les autres rôles pour la réassignation
$other_roles = [];
try {
    $other_roles = $database->query(
        "SELECT id, nom FROM roles WHERE id != ? AND actif = 1 ORDER BY nom",
        [$role_id]
    )->fetchAll();
} catch (Exception $e) {
    // Ignorer l'erreur, on continuera sans les autres rôles
}

include '../includes/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">
        <i class="fas fa-trash me-2"></i>
        Supprimer le rôle : <?php echo htmlspecialchars($role['nom']); ?>
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
        <p class="mb-0">Le rôle a été supprimé avec succès.</p>
    </div>
<?php endif; ?>

<div class="row">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    Confirmation de suppression
                </h5>
            </div>
            <div class="card-body">
                <div class="alert alert-warning">
                    <h6><i class="fas fa-warning me-2"></i>Attention !</h6>
                    <p class="mb-0">
                        Cette action est irréversible. Une fois supprimé, le rôle ne pourra plus être récupéré.
                    </p>
                </div>

                <form method="POST" class="needs-validation" novalidate>
                    <div class="mb-4">
                        <h6>Informations sur le rôle à supprimer :</h6>
                        <table class="table table-borderless">
                            <tr>
                                <td><strong>Nom :</strong></td>
                                <td><?php echo htmlspecialchars($role['nom']); ?></td>
                            </tr>
                            <tr>
                                <td><strong>Description :</strong></td>
                                <td><?php echo $role['description'] ? htmlspecialchars($role['description']) : 'Aucune description'; ?></td>
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
                                    <span class="badge bg-<?php echo $user_count > 0 ? 'warning' : 'success'; ?>">
                                        <?php echo $user_count; ?> utilisateur(s)
                                    </span>
                                </td>
                            </tr>
                        </table>
                    </div>

                    <?php if ($user_count > 0): ?>
                    <div class="mb-4">
                        <h6>Réassignation des utilisateurs :</h6>
                        <p class="text-muted">
                            Ce rôle est utilisé par <?php echo $user_count; ?> utilisateur(s). 
                            Vous devez choisir un rôle de remplacement.
                        </p>
                        
                        <?php if (!empty($other_roles)): ?>
                        <div class="mb-3">
                            <label for="reassign_role_id" class="form-label">Rôle de remplacement <span class="text-danger">*</span></label>
                            <select class="form-select" id="reassign_role_id" name="reassign_role_id" required>
                                <option value="">Sélectionner un rôle...</option>
                                <?php foreach ($other_roles as $other_role): ?>
                                    <option value="<?php echo $other_role['id']; ?>" 
                                            <?php echo ($_POST['reassign_role_id'] ?? '') == $other_role['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($other_role['nom']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="invalid-feedback">
                                Veuillez sélectionner un rôle de remplacement.
                            </div>
                        </div>
                        <?php else: ?>
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            Aucun autre rôle actif disponible pour la réassignation. 
                            Vous ne pouvez pas supprimer ce rôle.
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>

                    <div class="mb-4">
                        <h6>Confirmation :</h6>
                        <p class="text-muted">
                            Pour confirmer la suppression, tapez <strong>DELETE</strong> dans le champ ci-dessous :
                        </p>
                        <div class="mb-3">
                            <label for="confirm_delete" class="form-label">Confirmer la suppression <span class="text-danger">*</span></label>
                            <input type="text"
                                   class="form-control"
                                   id="confirm_delete"
                                   name="confirm_delete"
                                   value="<?php echo htmlspecialchars($_POST['confirm_delete'] ?? ''); ?>"
                                   placeholder="Tapez DELETE pour confirmer"
                                   required>
                            <div class="invalid-feedback">
                                Vous devez taper "DELETE" pour confirmer la suppression.
                            </div>
                        </div>
                    </div>

                    <div class="d-flex justify-content-between">
                        <a href="roles.php" class="btn btn-secondary">
                            <i class="fas fa-times me-1"></i>
                            Annuler
                        </a>
                        <?php if ($user_count > 0 && empty($other_roles)): ?>
                        <button type="button" class="btn btn-danger" disabled>
                            <i class="fas fa-trash me-1"></i>
                            Suppression impossible
                        </button>
                        <?php else: ?>
                        <button type="submit" class="btn btn-danger">
                            <i class="fas fa-trash me-1"></i>
                            Supprimer le rôle
                        </button>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <!-- Informations sur la suppression -->
        <div class="card mb-4">
            <div class="card-header">
                <h6 class="mb-0">
                    <i class="fas fa-info-circle me-2"></i>
                    À propos de la suppression
                </h6>
            </div>
            <div class="card-body">
                <p class="text-muted small">
                    La suppression d'un rôle est une action irréversible qui peut affecter les utilisateurs 
                    qui utilisent ce rôle.
                </p>
                <ul class="list-unstyled small">
                    <li><i class="fas fa-check text-success me-1"></i> Les utilisateurs seront réassignés au rôle choisi</li>
                    <li><i class="fas fa-check text-success me-1"></i> Les permissions du rôle seront supprimées</li>
                    <li><i class="fas fa-times text-danger me-1"></i> Cette action ne peut pas être annulée</li>
                </ul>
            </div>
        </div>

        <!-- Actions alternatives -->
        <div class="card">
            <div class="card-header">
                <h6 class="mb-0">
                    <i class="fas fa-lightbulb me-2"></i>
                    Actions alternatives
                </h6>
            </div>
            <div class="card-body">
                <div class="d-grid gap-2">
                    <a href="roles_edit.php?id=<?php echo $role_id; ?>" class="btn btn-outline-primary btn-sm">
                        <i class="fas fa-edit me-1"></i>
                        Modifier le rôle
                    </a>
                    <a href="roles_view.php?id=<?php echo $role_id; ?>" class="btn btn-outline-info btn-sm">
                        <i class="fas fa-eye me-1"></i>
                        Voir les détails
                    </a>
                    <?php if ($role['actif']): ?>
                    <button type="button" class="btn btn-outline-warning btn-sm" onclick="toggleRoleStatus(<?php echo $role_id; ?>, 0)">
                        <i class="fas fa-pause me-1"></i>
                        Désactiver le rôle
                    </button>
                    <?php else: ?>
                    <button type="button" class="btn btn-outline-success btn-sm" onclick="toggleRoleStatus(<?php echo $role_id; ?>, 1)">
                        <i class="fas fa-play me-1"></i>
                        Activer le rôle
                    </button>
                    <?php endif; ?>
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

// Fonction pour basculer le statut du rôle
function toggleRoleStatus(roleId, newStatus) {
    if (confirm(`Êtes-vous sûr de vouloir ${newStatus ? 'activer' : 'désactiver'} ce rôle ?`)) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = 'roles.php';
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
</script>

<?php include '../includes/footer.php'; ?>