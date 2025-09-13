<?php
/**
 * Module de gestion académique - Supprimer une classe
 * Application de gestion scolaire - République Démocratique du Congo
 */

require_once '../../../config/config.php';
require_once '../../../config/database.php';
require_once '../../../includes/functions.php';
require_once '../../../includes/permissions-pages.php';

// Vérifier l'authentification et les permissions
requireLogin();
requirePagePermissionFromDB('academic', 'classes', 'delete', '../../../dashboard.php', 'delete');

// Récupérer l'ID de la classe
$id = (int)($_GET['id'] ?? 0);
if (!$id) {
    showMessage('error', 'Classe non spécifiée.');
    redirectTo('index.php');
}

// Récupérer les informations de la classe
$classe = $database->query(
    "SELECT c.*, a.annee as annee_scolaire 
     FROM classes c 
     LEFT JOIN annees_scolaires a ON c.annee_scolaire_id = a.id 
     WHERE c.id = ?",
    [$id]
)->fetch();

if (!$classe) {
    showMessage('error', 'Classe non trouvée.');
    redirectTo('index.php');
}

// Vérifier s'il y a des élèves inscrits dans cette classe
$nb_eleves = $database->query(
    "SELECT COUNT(*) as total FROM inscriptions WHERE classe_id = ? AND status = 'inscrit'",
    [$id]
)->fetch()['total'];

if ($nb_eleves > 0) {
    showMessage('error', "Impossible de supprimer cette classe car elle contient $nb_eleves élève(s) inscrit(s).");
    redirectTo('index.php');
}

// Vérifier s'il y a des emplois du temps associés
$nb_emplois = $database->query(
    "SELECT COUNT(*) as total FROM emploi_temps WHERE classe_id = ?",
    [$id]
)->fetch()['total'];

if ($nb_emplois > 0) {
    showMessage('error', "Impossible de supprimer cette classe car elle a des emplois du temps associés.");
    redirectTo('index.php');
}

// Confirmation de suppression
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_delete'])) {
    try {
        // Supprimer la classe
        $database->execute("DELETE FROM classes WHERE id = ?", [$id]);
        
        // Enregistrer l'action
        if (function_exists('logUserAction')) {
            logUserAction(
                'delete_class',
                'academic',
                'Classe supprimée: ' . $classe['nom'],
                $id
            );
        }
        
        showMessage('success', 'Classe supprimée avec succès.');
        redirectTo('index.php');
        
    } catch (Exception $e) {
        showMessage('error', 'Erreur lors de la suppression: ' . $e->getMessage());
    }
}

$page_title = 'Supprimer une classe';

include '../../../includes/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">
        <i class="fas fa-trash me-2"></i>
        Supprimer une classe
    </h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <a href="index.php" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-left me-1"></i>
            Retour à la liste
        </a>
    </div>
</div>

<div class="row">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header bg-danger text-white">
                <h5 class="mb-0">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    Confirmation de suppression
                </h5>
            </div>
            <div class="card-body">
                <div class="alert alert-warning">
                    <h6><i class="fas fa-warning me-2"></i>Attention !</h6>
                    <p class="mb-0">Vous êtes sur le point de supprimer définitivement cette classe. Cette action est irréversible.</p>
                </div>
                
                <h6>Informations de la classe :</h6>
                <table class="table table-borderless">
                    <tr>
                        <td><strong>Nom :</strong></td>
                        <td><?php echo htmlspecialchars($classe['nom']); ?></td>
                    </tr>
                    <tr>
                        <td><strong>Niveau :</strong></td>
                        <td><?php echo ucfirst($classe['niveau']); ?></td>
                    </tr>
                    <tr>
                        <td><strong>Année scolaire :</strong></td>
                        <td><?php echo htmlspecialchars($classe['annee_scolaire']); ?></td>
                    </tr>
                    <?php if ($classe['description']): ?>
                    <tr>
                        <td><strong>Description :</strong></td>
                        <td><?php echo htmlspecialchars($classe['description']); ?></td>
                    </tr>
                    <?php endif; ?>
                </table>
                
                <form method="POST" class="mt-4">
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" id="confirm_delete" name="confirm_delete" required>
                        <label class="form-check-label" for="confirm_delete">
                            Je confirme vouloir supprimer définitivement cette classe
                        </label>
                    </div>
                    
                    <div class="d-flex justify-content-between">
                        <a href="index.php" class="btn btn-secondary">
                            <i class="fas fa-times me-1"></i>
                            Annuler
                        </a>
                        <button type="submit" class="btn btn-danger">
                            <i class="fas fa-trash me-1"></i>
                            Supprimer définitivement
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header">
                <h6 class="mb-0">
                    <i class="fas fa-info-circle me-2"></i>
                    Informations
                </h6>
            </div>
            <div class="card-body">
                <p class="text-muted small">
                    <i class="fas fa-check-circle text-success me-1"></i>
                    Aucun élève inscrit dans cette classe
                </p>
                <p class="text-muted small">
                    <i class="fas fa-check-circle text-success me-1"></i>
                    Aucun emploi du temps associé
                </p>
                <hr>
                <p class="text-muted small">
                    <strong>Note :</strong> La suppression d'une classe est définitive et ne peut pas être annulée.
                </p>
            </div>
        </div>
    </div>
</div>

<?php include '../../../includes/footer.php'; ?>
