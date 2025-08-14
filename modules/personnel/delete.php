<?php
/**
 * Module de gestion du personnel - Supprimer un membre
 * Application de gestion scolaire - République Démocratique du Congo
 */

require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../includes/functions.php';

// Vérifier l'authentification et les permissions
requireLogin();
if (!checkPermission('personnel')) {
    showMessage('error', 'Accès refusé à cette fonctionnalité.');
    redirectTo('index.php');
}

// Récupérer l'ID du membre
$id = (int)($_GET['id'] ?? 0);
if (!$id) {
    showMessage('error', 'Membre du personnel non spécifié.');
    redirectTo('index.php');
}

// Récupérer les informations du membre
$membre = $database->query(
    "SELECT p.*, u.username FROM personnel p LEFT JOIN users u ON p.user_id = u.id WHERE p.id = ?", 
    [$id]
)->fetch();

if (!$membre) {
    showMessage('error', 'Membre du personnel non trouvé.');
    redirectTo('index.php');
}

// Vérifier les dépendances avant suppression
$dependencies = [];

// Vérifier les emplois du temps
$stmt = $database->query("SELECT COUNT(*) as count FROM emplois_temps WHERE enseignant_id = ?", [$id]);
if ($stmt->fetch()['count'] > 0) {
    $dependencies[] = "Emplois du temps assignés";
}

// Vérifier les évaluations
$stmt = $database->query("SELECT COUNT(*) as count FROM evaluations WHERE enseignant_id = ?", [$id]);
if ($stmt->fetch()['count'] > 0) {
    $dependencies[] = "Évaluations créées";
}

// Traitement de la suppression
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_delete'])) {
    $force_delete = isset($_POST['force_delete']);
    
    if (!empty($dependencies) && !$force_delete) {
        showMessage('error', 'Impossible de supprimer ce membre car il a des dépendances. Utilisez la suppression forcée si nécessaire.');
    } else {
        try {
            $database->beginTransaction();
            
            if ($force_delete) {
                // Supprimer les dépendances
                $database->execute("DELETE FROM emplois_temps WHERE enseignant_id = ?", [$id]);
                $database->execute("UPDATE evaluations SET enseignant_id = NULL WHERE enseignant_id = ?", [$id]);
            }
            
            // Supprimer le compte utilisateur associé si existe
            if ($membre['user_id']) {
                $database->execute("DELETE FROM users WHERE id = ?", [$membre['user_id']]);
            }
            
            // Supprimer le membre du personnel
            $database->execute("DELETE FROM personnel WHERE id = ?", [$id]);
            
            $database->commit();
            
            showMessage('success', 'Membre du personnel supprimé avec succès.');
            redirectTo('index.php');
            
        } catch (Exception $e) {
            $database->rollback();
            showMessage('error', 'Erreur lors de la suppression : ' . $e->getMessage());
        }
    }
}

$page_title = 'Supprimer - ' . $membre['nom'] . ' ' . $membre['prenom'];

include '../../includes/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">
        <i class="fas fa-user-times me-2 text-danger"></i>
        Supprimer un membre du personnel
    </h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <a href="view.php?id=<?php echo $membre['id']; ?>" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-left me-1"></i>
            Retour
        </a>
    </div>
</div>

<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="card border-danger">
            <div class="card-header bg-danger text-white">
                <h5 class="mb-0">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    Confirmation de suppression
                </h5>
            </div>
            <div class="card-body">
                <div class="alert alert-warning">
                    <h6><i class="fas fa-warning me-2"></i>Attention !</h6>
                    <p class="mb-0">
                        Vous êtes sur le point de supprimer définitivement ce membre du personnel. 
                        Cette action est <strong>irréversible</strong>.
                    </p>
                </div>
                
                <!-- Informations du membre à supprimer -->
                <div class="row mb-4">
                    <div class="col-md-6">
                        <h6>Informations du membre :</h6>
                        <table class="table table-borderless table-sm">
                            <tr>
                                <td><strong>Matricule :</strong></td>
                                <td><?php echo htmlspecialchars($membre['matricule']); ?></td>
                            </tr>
                            <tr>
                                <td><strong>Nom complet :</strong></td>
                                <td><?php echo htmlspecialchars($membre['nom'] . ' ' . $membre['prenom']); ?></td>
                            </tr>
                            <tr>
                                <td><strong>Fonction :</strong></td>
                                <td><?php echo ucfirst(str_replace('_', ' ', $membre['fonction'])); ?></td>
                            </tr>
                            <tr>
                                <td><strong>Statut :</strong></td>
                                <td>
                                    <span class="badge bg-<?php echo $membre['status'] === 'actif' ? 'success' : 'warning'; ?>">
                                        <?php echo ucfirst($membre['status']); ?>
                                    </span>
                                </td>
                            </tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <h6>Compte utilisateur :</h6>
                        <?php if ($membre['user_id']): ?>
                            <div class="alert alert-info">
                                <i class="fas fa-user me-2"></i>
                                <strong>Compte associé :</strong> <?php echo htmlspecialchars($membre['username']); ?>
                                <br><small>Ce compte sera également supprimé.</small>
                            </div>
                        <?php else: ?>
                            <p class="text-muted">Aucun compte utilisateur associé.</p>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Vérification des dépendances -->
                <?php if (!empty($dependencies)): ?>
                    <div class="alert alert-danger">
                        <h6><i class="fas fa-link me-2"></i>Dépendances détectées :</h6>
                        <ul class="mb-0">
                            <?php foreach ($dependencies as $dependency): ?>
                                <li><?php echo $dependency; ?></li>
                            <?php endforeach; ?>
                        </ul>
                        <hr>
                        <p class="mb-0">
                            <strong>Options :</strong>
                        </p>
                        <ul class="mb-0">
                            <li><strong>Suppression normale :</strong> Impossible tant que des dépendances existent</li>
                            <li><strong>Suppression forcée :</strong> Supprime le membre et toutes ses dépendances</li>
                        </ul>
                    </div>
                <?php endif; ?>
                
                <!-- Formulaire de confirmation -->
                <form method="POST">
                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="confirm_delete" name="confirm_delete" required>
                            <label class="form-check-label" for="confirm_delete">
                                <strong>Je confirme vouloir supprimer définitivement ce membre du personnel</strong>
                            </label>
                        </div>
                    </div>
                    
                    <?php if (!empty($dependencies)): ?>
                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="force_delete" name="force_delete">
                                <label class="form-check-label text-danger" for="force_delete">
                                    <strong>Suppression forcée</strong> - Supprimer également toutes les dépendances
                                </label>
                            </div>
                            <small class="text-muted">
                                Attention : Cette option supprimera également tous les emplois du temps et évaluations associés.
                            </small>
                        </div>
                    <?php endif; ?>
                    
                    <div class="d-flex justify-content-between">
                        <a href="view.php?id=<?php echo $membre['id']; ?>" class="btn btn-secondary">
                            <i class="fas fa-times me-1"></i>
                            Annuler
                        </a>
                        <button type="submit" class="btn btn-danger" id="delete-btn" disabled>
                            <i class="fas fa-trash me-1"></i>
                            Supprimer définitivement
                        </button>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Alternatives à la suppression -->
        <div class="card mt-4">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-lightbulb me-2"></i>
                    Alternatives à la suppression
                </h5>
            </div>
            <div class="card-body">
                <p>Plutôt que de supprimer définitivement ce membre, vous pouvez :</p>
                <div class="row">
                    <div class="col-md-6">
                        <div class="d-grid">
                            <a href="edit.php?id=<?php echo $membre['id']; ?>&action=suspend" class="btn btn-outline-warning">
                                <i class="fas fa-pause me-2"></i>
                                Suspendre temporairement
                            </a>
                        </div>
                        <small class="text-muted">Le membre reste dans le système mais devient inactif</small>
                    </div>
                    <div class="col-md-6">
                        <div class="d-grid">
                            <a href="edit.php?id=<?php echo $membre['id']; ?>&action=archive" class="btn btn-outline-secondary">
                                <i class="fas fa-archive me-2"></i>
                                Marquer comme démissionné
                            </a>
                        </div>
                        <small class="text-muted">Conserve l'historique pour les rapports</small>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Activer le bouton de suppression seulement si la confirmation est cochée
document.getElementById('confirm_delete').addEventListener('change', function() {
    const deleteBtn = document.getElementById('delete-btn');
    deleteBtn.disabled = !this.checked;
});

// Changer le texte du bouton selon le type de suppression
document.getElementById('force_delete').addEventListener('change', function() {
    const deleteBtn = document.getElementById('delete-btn');
    const confirmCheckbox = document.getElementById('confirm_delete');
    
    if (this.checked) {
        deleteBtn.innerHTML = '<i class="fas fa-exclamation-triangle me-1"></i>Suppression forcée';
        deleteBtn.className = 'btn btn-danger';
        confirmCheckbox.parentNode.querySelector('label').innerHTML = 
            '<strong>Je confirme vouloir supprimer définitivement ce membre ET toutes ses dépendances</strong>';
    } else {
        deleteBtn.innerHTML = '<i class="fas fa-trash me-1"></i>Supprimer définitivement';
        deleteBtn.className = 'btn btn-danger';
        confirmCheckbox.parentNode.querySelector('label').innerHTML = 
            '<strong>Je confirme vouloir supprimer définitivement ce membre du personnel</strong>';
    }
});
</script>

<?php include '../../includes/footer.php'; ?>
