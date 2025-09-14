<?php
/**
 * Suppression d'une année scolaire
 * Application de gestion scolaire - République Démocratique du Congo
 */

require_once '../../../config/config.php';
require_once '../../../config/database.php';
require_once '../../../includes/functions.php';
require_once '../../../includes/permissions-pages.php';

// Vérifier l'authentification et les permissions
requireLogin();
requirePagePermissionFromDB('academic', 'years/delete', 'delete', '../../../dashboard.php');

$page_title = 'Supprimer une année scolaire';

// Vérifier que l'ID est fourni
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    showMessage('error', 'ID d\'année scolaire invalide.');
    redirectTo('index.php');
    exit;
}

$annee_id = (int)$_GET['id'];

// Récupérer les informations de l'année scolaire
$annee = $database->query(
    "SELECT * FROM annees_scolaires WHERE id = ?",
    [$annee_id]
)->fetch();

if (!$annee) {
    showMessage('error', 'Année scolaire non trouvée.');
    redirectTo('index.php');
    exit;
}

// Vérifier si l'année scolaire est active
if ($annee['status'] === 'active') {
    showMessage('error', 'Impossible de supprimer une année scolaire active. Veuillez d\'abord la désactiver.');
    redirectTo('index.php');
    exit;
}

// Vérifier s'il y a des données liées à cette année scolaire
$has_classes = $database->query(
    "SELECT COUNT(*) as count FROM classes WHERE annee_scolaire_id = ?",
    [$annee_id]
)->fetch()['count'] > 0;

$has_inscriptions = $database->query(
    "SELECT COUNT(*) as count FROM inscriptions WHERE annee_scolaire_id = ?",
    [$annee_id]
)->fetch()['count'] > 0;

if ($has_classes || $has_inscriptions) {
    showMessage('error', 'Impossible de supprimer cette année scolaire car elle contient des données liées (classes, inscriptions, etc.).');
    redirectTo('index.php');
    exit;
}

// Traitement de la suppression
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Démarrer une transaction
        $database->beginTransaction();
        
        // Supprimer l'année scolaire
        $result = $database->execute(
            "DELETE FROM annees_scolaires WHERE id = ?",
            [$annee_id]
        );
        
        if ($result) {
            // Log de l'action
            logAction('delete', 'annees_scolaires', $annee_id, "Suppression de l'année scolaire {$annee['annee']}");
            
            // Valider la transaction
            $database->commit();
            
            showMessage('success', "L'année scolaire '{$annee['annee']}' a été supprimée avec succès.");
            redirectTo('index.php');
        } else {
            throw new Exception('Erreur lors de la suppression');
        }
        
    } catch (Exception $e) {
        // Annuler la transaction en cas d'erreur
        $database->rollback();
        
        error_log("Erreur suppression année scolaire: " . $e->getMessage());
        showMessage('error', 'Une erreur est survenue lors de la suppression. Veuillez réessayer.');
    }
}

include '../../../includes/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">
        <i class="fas fa-trash me-2"></i>
        Supprimer une année scolaire
    </h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <div class="btn-group me-2">
            <a href="index.php" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-1"></i>
                Retour
            </a>
        </div>
    </div>
</div>

<!-- Confirmation de suppression -->
<div class="row justify-content-center">
    <div class="col-md-8">
        <div class="card border-danger">
            <div class="card-header bg-danger text-white">
                <h5 class="mb-0">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    Confirmation de suppression
                </h5>
            </div>
            <div class="card-body">
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <strong>Attention !</strong> Cette action est irréversible. L'année scolaire sera définitivement supprimée.
                </div>
                
                <div class="row mb-3">
                    <div class="col-md-6">
                        <strong>Année scolaire :</strong><br>
                        <span class="text-primary"><?php echo htmlspecialchars($annee['annee']); ?></span>
                    </div>
                    <div class="col-md-6">
                        <strong>Période :</strong><br>
                        <?php echo date('d/m/Y', strtotime($annee['date_debut'])); ?> - 
                        <?php echo date('d/m/Y', strtotime($annee['date_fin'])); ?>
                    </div>
                </div>
                
                <div class="row mb-3">
                    <div class="col-md-6">
                        <strong>Statut :</strong><br>
                        <span class="badge bg-<?php echo $annee['status'] === 'active' ? 'success' : 'secondary'; ?>">
                            <?php echo $annee['status'] === 'active' ? 'Active' : 'Fermée'; ?>
                        </span>
                    </div>
                    <div class="col-md-6">
                        <strong>Créée le :</strong><br>
                        <?php echo date('d/m/Y H:i', strtotime($annee['created_at'])); ?>
                    </div>
                </div>
                
                <?php if ($annee['description']): ?>
                <div class="mb-3">
                    <strong>Description :</strong><br>
                    <p class="text-muted"><?php echo htmlspecialchars($annee['description']); ?></p>
                </div>
                <?php endif; ?>
                
                <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                    <a href="index.php" class="btn btn-secondary me-md-2">
                        <i class="fas fa-times me-1"></i>
                        Annuler
                    </a>
                    <form method="POST" class="d-inline">
                        <button type="submit" class="btn btn-danger" onclick="return confirm('Êtes-vous absolument sûr de vouloir supprimer cette année scolaire ? Cette action est irréversible !')">
                            <i class="fas fa-trash me-1"></i>
                            Confirmer la suppression
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../../../includes/footer.php'; ?>
