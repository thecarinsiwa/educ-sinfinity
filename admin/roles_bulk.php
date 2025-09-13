<?php
/**
 * Administration - Actions en lot sur les rôles
 * Application de gestion scolaire - République Démocratique du Congo
 */

require_once '../config/config.php';

// Vérification de session robuste
require_once '../session_check.php';
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/permissions.php';

// Vérifier les permissions
if (!checkUserPermission('users', 'read') && !checkPermission('admin')) {
    showMessage('error', 'Accès refusé à ce module.');
    redirectTo('../dashboard.php');
}

$page_title = 'Administration - Actions en lot sur les rôles';

$errors = [];
$success = false;
$selected_roles = [];

// Récupérer les IDs des rôles sélectionnés
$role_ids = $_GET['ids'] ?? '';
if ($role_ids) {
    $ids = explode(',', $role_ids);
    $ids = array_map('intval', $ids);
    $ids = array_filter($ids);
    
    if (!empty($ids)) {
        $placeholders = str_repeat('?,', count($ids) - 1) . '?';
        $selected_roles = $database->query(
            "SELECT * FROM roles WHERE id IN ($placeholders) ORDER BY nom",
            $ids
        )->fetchAll();
    }
}

// Traitement des actions en lot
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = sanitizeInput($_POST['action'] ?? '');
    $role_ids = $_POST['role_ids'] ?? [];
    
    if (empty($role_ids)) {
        $errors[] = 'Aucun rôle sélectionné';
    } else {
        try {
            switch ($action) {
                case 'activate':
                    $placeholders = str_repeat('?,', count($role_ids) - 1) . '?';
                    $database->execute(
                        "UPDATE roles SET actif = 1, date_modification = NOW() WHERE id IN ($placeholders)",
                        $role_ids
                    );
                    
                    if (function_exists('logUserAction')) {
                        logUserAction(
                            'bulk_activate_roles',
                            'admin',
                            count($role_ids) . ' rôle(s) activé(s) en lot'
                        );
                    }
                    
                    $success = true;
                    showMessage('success', count($role_ids) . ' rôle(s) activé(s) avec succès');
                    break;
                    
                case 'deactivate':
                    $placeholders = str_repeat('?,', count($role_ids) - 1) . '?';
                    $database->execute(
                        "UPDATE roles SET actif = 0, date_modification = NOW() WHERE id IN ($placeholders)",
                        $role_ids
                    );
                    
                    if (function_exists('logUserAction')) {
                        logUserAction(
                            'bulk_deactivate_roles',
                            'admin',
                            count($role_ids) . ' rôle(s) désactivé(s) en lot'
                        );
                    }
                    
                    $success = true;
                    showMessage('success', count($role_ids) . ' rôle(s) désactivé(s) avec succès');
                    break;
                    
                case 'export':
                    $placeholders = str_repeat('?,', count($role_ids) - 1) . '?';
                    $roles_to_export = $database->query(
                        "SELECT * FROM roles WHERE id IN ($placeholders)",
                        $role_ids
                    )->fetchAll();
                    
                    $export_data = [
                        'export_date' => date('Y-m-d H:i:s'),
                        'exported_by' => $_SESSION['user_id'] ?? 'unknown',
                        'export_type' => 'bulk_roles',
                        'roles' => $roles_to_export
                    ];
                    
                    header('Content-Type: application/json');
                    header('Content-Disposition: attachment; filename="roles_bulk_export_' . date('Y-m-d_H-i-s') . '.json"');
                    echo json_encode($export_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
                    exit;
                    
                case 'duplicate':
                    $duplicated_count = 0;
                    foreach ($role_ids as $role_id) {
                        $role = $database->query("SELECT * FROM roles WHERE id = ?", [$role_id])->fetch();
                        if ($role) {
                            $new_name = $role['nom'] . ' (Copie)';
                            $counter = 1;
                            while ($database->query("SELECT id FROM roles WHERE nom = ?", [$new_name])->fetch()) {
                                $new_name = $role['nom'] . " (Copie {$counter})";
                                $counter++;
                            }
                            
                            $database->execute(
                                "INSERT INTO roles (nom, description, permissions, actif, date_creation) 
                                 VALUES (?, ?, ?, ?, NOW())",
                                [$new_name, $role['description'], $role['permissions'], 0]
                            );
                            $duplicated_count++;
                        }
                    }
                    
                    if (function_exists('logUserAction')) {
                        logUserAction(
                            'bulk_duplicate_roles',
                            'admin',
                            $duplicated_count . ' rôle(s) dupliqué(s) en lot'
                        );
                    }
                    
                    $success = true;
                    showMessage('success', $duplicated_count . ' rôle(s) dupliqué(s) avec succès');
                    break;
                    
                case 'delete':
                    // Vérifier si des utilisateurs utilisent ces rôles
                    $placeholders = str_repeat('?,', count($role_ids) - 1) . '?';
                    $users_count = $database->query(
                        "SELECT COUNT(*) as count FROM utilisateurs WHERE role_id IN ($placeholders)",
                        $role_ids
                    )->fetch()['count'];
                    
                    if ($users_count > 0) {
                        $errors[] = "Impossible de supprimer ces rôles car {$users_count} utilisateur(s) les utilisent encore";
                    } else {
                        $database->execute(
                            "DELETE FROM roles WHERE id IN ($placeholders)",
                            $role_ids
                        );
                        
                        if (function_exists('logUserAction')) {
                            logUserAction(
                                'bulk_delete_roles',
                                'admin',
                                count($role_ids) . ' rôle(s) supprimé(s) en lot'
                            );
                        }
                        
                        $success = true;
                        showMessage('success', count($role_ids) . ' rôle(s) supprimé(s) avec succès');
                    }
                    break;
                    
                default:
                    $errors[] = 'Action non reconnue';
            }
        } catch (Exception $e) {
            $errors[] = 'Erreur lors de l\'exécution de l\'action: ' . $e->getMessage();
        }
    }
}

include '../includes/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">
        <i class="fas fa-tasks me-2"></i>
        Actions en lot sur les rôles
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

<?php if ($success): ?>
    <div class="alert alert-success">
        <h6><i class="fas fa-check-circle me-2"></i>Succès !</h6>
        <p class="mb-0">L'action en lot a été exécutée avec succès.</p>
    </div>
<?php endif; ?>

<?php if (empty($selected_roles)): ?>
    <div class="alert alert-warning">
        <h6><i class="fas fa-exclamation-triangle me-2"></i>Aucun rôle sélectionné</h6>
        <p class="mb-0">Veuillez retourner à la liste des rôles et sélectionner au moins un rôle pour effectuer des actions en lot.</p>
        <a href="roles.php" class="btn btn-primary mt-2">
            <i class="fas fa-list me-1"></i>
            Voir la liste des rôles
        </a>
    </div>
<?php else: ?>

<div class="row">
    <div class="col-lg-8">
        <!-- Rôles sélectionnés -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-list me-2"></i>
                    Rôles sélectionnés (<?php echo count($selected_roles); ?>)
                </h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Nom</th>
                                <th>Description</th>
                                <th>Statut</th>
                                <th>Utilisateurs</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($selected_roles as $role): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo htmlspecialchars($role['nom']); ?></strong>
                                        <br><small class="text-muted">#<?php echo $role['id']; ?></small>
                                    </td>
                                    <td><?php echo htmlspecialchars($role['description']); ?></td>
                                    <td>
                                        <span class="badge bg-<?php echo $role['actif'] ? 'success' : 'secondary'; ?>">
                                            <?php echo $role['actif'] ? 'Actif' : 'Inactif'; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php
                                        $users_count = $database->query(
                                            "SELECT COUNT(*) as count FROM utilisateurs WHERE role_id = ?",
                                            [$role['id']]
                                        )->fetch()['count'];
                                        echo $users_count . ' utilisateur(s)';
                                        ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Actions disponibles -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-cogs me-2"></i>
                    Actions disponibles
                </h5>
            </div>
            <div class="card-body">
                <form method="POST" id="bulkActionsForm">
                    <input type="hidden" name="role_ids" value="<?php echo implode(',', array_column($selected_roles, 'id')); ?>">
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <div class="card h-100">
                                <div class="card-body text-center">
                                    <i class="fas fa-check-circle fa-3x text-success mb-3"></i>
                                    <h6>Activer les rôles</h6>
                                    <p class="text-muted small">Rendre tous les rôles sélectionnés actifs</p>
                                    <button type="submit" name="action" value="activate" class="btn btn-success btn-sm">
                                        <i class="fas fa-check me-1"></i>
                                        Activer
                                    </button>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <div class="card h-100">
                                <div class="card-body text-center">
                                    <i class="fas fa-ban fa-3x text-warning mb-3"></i>
                                    <h6>Désactiver les rôles</h6>
                                    <p class="text-muted small">Rendre tous les rôles sélectionnés inactifs</p>
                                    <button type="submit" name="action" value="deactivate" class="btn btn-warning btn-sm">
                                        <i class="fas fa-ban me-1"></i>
                                        Désactiver
                                    </button>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <div class="card h-100">
                                <div class="card-body text-center">
                                    <i class="fas fa-copy fa-3x text-info mb-3"></i>
                                    <h6>Dupliquer les rôles</h6>
                                    <p class="text-muted small">Créer des copies de tous les rôles sélectionnés</p>
                                    <button type="submit" name="action" value="duplicate" class="btn btn-info btn-sm" 
                                            onclick="return confirm('Êtes-vous sûr de vouloir dupliquer tous ces rôles ?')">
                                        <i class="fas fa-copy me-1"></i>
                                        Dupliquer
                                    </button>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <div class="card h-100">
                                <div class="card-body text-center">
                                    <i class="fas fa-download fa-3x text-primary mb-3"></i>
                                    <h6>Exporter les rôles</h6>
                                    <p class="text-muted small">Télécharger les rôles sélectionnés en JSON</p>
                                    <button type="submit" name="action" value="export" class="btn btn-primary btn-sm">
                                        <i class="fas fa-download me-1"></i>
                                        Exporter
                                    </button>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <div class="card h-100">
                                <div class="card-body text-center">
                                    <i class="fas fa-trash fa-3x text-danger mb-3"></i>
                                    <h6>Supprimer les rôles</h6>
                                    <p class="text-muted small">Supprimer définitivement tous les rôles sélectionnés</p>
                                    <button type="submit" name="action" value="delete" class="btn btn-danger btn-sm" 
                                            onclick="return confirm('Êtes-vous sûr de vouloir supprimer définitivement tous ces rôles ? Cette action est irréversible !')">
                                        <i class="fas fa-trash me-1"></i>
                                        Supprimer
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
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
                        <h4 class="text-primary mb-0"><?php echo count($selected_roles); ?></h4>
                        <small class="text-muted">Rôles sélectionnés</small>
                    </div>
                    <div class="col-6">
                        <h4 class="text-success mb-0">
                            <?php echo count(array_filter($selected_roles, function($r) { return $r['actif']; })); ?>
                        </h4>
                        <small class="text-muted">Actifs</small>
                    </div>
                </div>
                
                <hr>
                
                <h6>Répartition par statut :</h6>
                <?php
                $active_count = count(array_filter($selected_roles, function($r) { return $r['actif']; }));
                $inactive_count = count($selected_roles) - $active_count;
                ?>
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="small">Actifs</span>
                    <span class="badge bg-success"><?php echo $active_count; ?></span>
                </div>
                <div class="d-flex justify-content-between align-items-center">
                    <span class="small">Inactifs</span>
                    <span class="badge bg-secondary"><?php echo $inactive_count; ?></span>
                </div>
            </div>
        </div>

        <!-- Informations importantes -->
        <div class="card">
            <div class="card-header">
                <h6 class="mb-0">
                    <i class="fas fa-info-circle me-2"></i>
                    Informations importantes
                </h6>
            </div>
            <div class="card-body">
                <div class="alert alert-warning">
                    <h6><i class="fas fa-exclamation-triangle me-2"></i>Attention !</h6>
                    <ul class="mb-0 small">
                        <li>Les actions en lot sont irréversibles</li>
                        <li>Vérifiez que les rôles ne sont pas utilisés avant suppression</li>
                        <li>Les rôles dupliqués seront créés en statut inactif</li>
                        <li>L'export génère un fichier JSON téléchargeable</li>
                    </ul>
                </div>
                
                <div class="alert alert-info">
                    <h6><i class="fas fa-lightbulb me-2"></i>Conseils :</h6>
                    <ul class="mb-0 small">
                        <li>Utilisez l'export pour sauvegarder avant suppression</li>
                        <li>Les rôles dupliqués peuvent être modifiés individuellement</li>
                        <li>Vérifiez les utilisateurs avant de désactiver des rôles</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<?php endif; ?>

<script>
// Confirmation pour les actions destructives
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('bulkActionsForm');
    if (form) {
        form.addEventListener('submit', function(e) {
            const action = e.submitter.value;
            
            if (action === 'delete') {
                if (!confirm('Êtes-vous absolument sûr de vouloir supprimer définitivement tous ces rôles ? Cette action est irréversible et ne peut pas être annulée !')) {
                    e.preventDefault();
                    return false;
                }
            } else if (action === 'duplicate') {
                if (!confirm('Êtes-vous sûr de vouloir dupliquer tous ces rôles ? Cela créera des copies avec des noms modifiés.')) {
                    e.preventDefault();
                    return false;
                }
            }
        });
    }
});

// Animation des cartes
document.addEventListener('DOMContentLoaded', function() {
    const cards = document.querySelectorAll('.card');
    cards.forEach((card, index) => {
        card.style.opacity = '0';
        card.style.transform = 'translateY(20px)';
        
        setTimeout(() => {
            card.style.transition = 'all 0.3s ease';
            card.style.opacity = '1';
            card.style.transform = 'translateY(0)';
        }, index * 100);
    });
});
</script>

<?php include '../includes/footer.php'; ?>
