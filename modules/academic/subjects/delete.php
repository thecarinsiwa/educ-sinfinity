<?php
/**
 * Module de gestion académique - Supprimer une matière
 * Application de gestion scolaire - République Démocratique du Congo
 */

require_once '../../../config/config.php';
require_once '../../../config/database.php';
require_once '../../../includes/functions.php';

// Vérifier l'authentification et les permissions
requireLogin();
if (!checkPermission('academic')) {
    showMessage('error', 'Accès refusé à cette fonctionnalité.');
    redirectTo('index.php');
}

// Récupérer l'ID de la matière
$matiere_id = (int)($_GET['id'] ?? 0);
if (!$matiere_id) {
    showMessage('error', 'ID de matière manquant.');
    redirectTo('index.php');
}

// Récupérer les données de la matière
$matiere = $database->query(
    "SELECT * FROM matieres WHERE id = ?",
    [$matiere_id]
)->fetch();

if (!$matiere) {
    showMessage('error', 'Matière non trouvée.');
    redirectTo('index.php');
}

$page_title = 'Supprimer la matière : ' . $matiere['nom'];

// Vérifier les dépendances
$dependencies = [];

// Vérifier les emplois du temps
$emplois_temps = $database->query(
    "SELECT COUNT(*) as count FROM emplois_temps WHERE matiere_id = ?",
    [$matiere_id]
)->fetch();
if ($emplois_temps['count'] > 0) {
    $dependencies[] = [
        'type' => 'Emplois du temps',
        'count' => $emplois_temps['count'],
        'description' => 'cours programmés'
    ];
}

// Vérifier les évaluations
$evaluations = $database->query(
    "SELECT COUNT(*) as count FROM evaluations WHERE matiere_id = ?",
    [$matiere_id]
)->fetch();
if ($evaluations['count'] > 0) {
    $dependencies[] = [
        'type' => 'Évaluations',
        'count' => $evaluations['count'],
        'description' => 'évaluations créées'
    ];
}

// Vérifier les notes
$notes = $database->query(
    "SELECT COUNT(*) as count FROM notes n 
     JOIN evaluations e ON n.evaluation_id = e.id 
     WHERE e.matiere_id = ?",
    [$matiere_id]
)->fetch();
if ($notes['count'] > 0) {
    $dependencies[] = [
        'type' => 'Notes',
        'count' => $notes['count'],
        'description' => 'notes d\'élèves'
    ];
}

// Traitement de la suppression
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_delete'])) {
    try {
        // Commencer une transaction
        $database->beginTransaction();
        
        // Supprimer les notes liées aux évaluations de cette matière
        $database->execute(
            "DELETE n FROM notes n 
             JOIN evaluations e ON n.evaluation_id = e.id 
             WHERE e.matiere_id = ?",
            [$matiere_id]
        );
        
        // Supprimer les évaluations
        $database->execute(
            "DELETE FROM evaluations WHERE matiere_id = ?",
            [$matiere_id]
        );
        
        // Supprimer les emplois du temps
        $database->execute(
            "DELETE FROM emplois_temps WHERE matiere_id = ?",
            [$matiere_id]
        );
        
        // Supprimer la matière
        $database->execute(
            "DELETE FROM matieres WHERE id = ?",
            [$matiere_id]
        );
        
        // Valider la transaction
        $database->commit();
        
        // Enregistrer l'action
        logUserAction(
            'delete_subject',
            'academic',
            "Matière supprimée: {$matiere['nom']} (ID: $matiere_id)",
            $matiere_id
        );
        
        showMessage('success', 'La matière "' . $matiere['nom'] . '" a été supprimée avec succès.');
        redirectTo('index.php');
        
    } catch (Exception $e) {
        // Annuler la transaction en cas d'erreur
        $database->rollback();
        showMessage('error', 'Erreur lors de la suppression: ' . $e->getMessage());
    }
}

include '../../../includes/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2 text-danger">
        <i class="fas fa-exclamation-triangle me-2"></i>
        Supprimer la matière
    </h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <div class="btn-group me-2">
            <a href="index.php" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-1"></i>
                Retour à la liste
            </a>
            <a href="view.php?id=<?php echo $matiere_id; ?>" class="btn btn-outline-info">
                <i class="fas fa-eye me-1"></i>
                Voir détails
            </a>
        </div>
    </div>
</div>

<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="card border-danger">
            <div class="card-header bg-danger text-white">
                <h5 class="mb-0">
                    <i class="fas fa-trash me-2"></i>
                    Confirmation de suppression
                </h5>
            </div>
            <div class="card-body">
                <div class="alert alert-danger">
                    <h5><i class="fas fa-exclamation-triangle me-2"></i>Attention !</h5>
                    <p class="mb-0">
                        Vous êtes sur le point de supprimer définitivement la matière 
                        <strong>"<?php echo htmlspecialchars($matiere['nom']); ?>"</strong>.
                        Cette action est <strong>irréversible</strong>.
                    </p>
                </div>

                <!-- Informations sur la matière -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h6 class="mb-0">Informations sur la matière</h6>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <p><strong>Nom :</strong> <?php echo htmlspecialchars($matiere['nom']); ?></p>
                                <p><strong>Niveau :</strong> 
                                    <span class="badge bg-primary"><?php echo ucfirst($matiere['niveau']); ?></span>
                                </p>
                                <p><strong>Type :</strong> 
                                    <span class="badge bg-<?php echo $matiere['type'] === 'obligatoire' ? 'success' : 'warning'; ?>">
                                        <?php echo ucfirst($matiere['type']); ?>
                                    </span>
                                </p>
                            </div>
                            <div class="col-md-6">
                                <?php if ($matiere['coefficient']): ?>
                                    <p><strong>Coefficient :</strong> <?php echo $matiere['coefficient']; ?></p>
                                <?php endif; ?>
                                <?php if ($matiere['volume_horaire']): ?>
                                    <p><strong>Volume horaire :</strong> <?php echo $matiere['volume_horaire']; ?>h/semaine</p>
                                <?php endif; ?>
                                <p><strong>Créée le :</strong> <?php echo date('d/m/Y', strtotime($matiere['created_at'])); ?></p>
                            </div>
                        </div>
                        
                        <?php if ($matiere['description']): ?>
                            <hr>
                            <p><strong>Description :</strong></p>
                            <p class="text-muted"><?php echo nl2br(htmlspecialchars($matiere['description'])); ?></p>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Dépendances -->
                <?php if (!empty($dependencies)): ?>
                    <div class="alert alert-warning">
                        <h6><i class="fas fa-link me-2"></i>Éléments liés qui seront supprimés</h6>
                        <p>La suppression de cette matière entraînera également la suppression de :</p>
                        <ul class="mb-0">
                            <?php foreach ($dependencies as $dep): ?>
                                <li>
                                    <strong><?php echo $dep['count']; ?> <?php echo $dep['type']; ?></strong>
                                    (<?php echo $dep['description']; ?>)
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php else: ?>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        Cette matière n'est utilisée dans aucun emploi du temps ou évaluation. 
                        Sa suppression n'affectera aucune autre donnée.
                    </div>
                <?php endif; ?>

                <!-- Formulaire de confirmation -->
                <form method="POST" class="mt-4">
                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="understand" required>
                            <label class="form-check-label" for="understand">
                                Je comprends que cette action est irréversible et que toutes les données liées seront supprimées.
                            </label>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="confirm_name" class="form-label">
                            Pour confirmer, tapez le nom de la matière : 
                            <strong><?php echo htmlspecialchars($matiere['nom']); ?></strong>
                        </label>
                        <input type="text" 
                               class="form-control" 
                               id="confirm_name" 
                               name="confirm_name"
                               placeholder="Tapez le nom exact de la matière"
                               required>
                    </div>
                    
                    <div class="d-flex justify-content-between">
                        <a href="index.php" class="btn btn-secondary">
                            <i class="fas fa-times me-1"></i>
                            Annuler
                        </a>
                        <button type="submit" 
                                name="confirm_delete" 
                                class="btn btn-danger" 
                                id="deleteBtn" 
                                disabled>
                            <i class="fas fa-trash me-1"></i>
                            Supprimer définitivement
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const understandCheckbox = document.getElementById('understand');
    const confirmNameInput = document.getElementById('confirm_name');
    const deleteBtn = document.getElementById('deleteBtn');
    const expectedName = <?php echo json_encode($matiere['nom']); ?>;
    
    function checkFormValidity() {
        const isUnderstood = understandCheckbox.checked;
        const isNameCorrect = confirmNameInput.value.trim() === expectedName;
        
        deleteBtn.disabled = !(isUnderstood && isNameCorrect);
        
        if (isNameCorrect) {
            confirmNameInput.classList.remove('is-invalid');
            confirmNameInput.classList.add('is-valid');
        } else if (confirmNameInput.value.trim() !== '') {
            confirmNameInput.classList.remove('is-valid');
            confirmNameInput.classList.add('is-invalid');
        } else {
            confirmNameInput.classList.remove('is-valid', 'is-invalid');
        }
    }
    
    understandCheckbox.addEventListener('change', checkFormValidity);
    confirmNameInput.addEventListener('input', checkFormValidity);
    
    // Confirmation finale
    document.querySelector('form').addEventListener('submit', function(e) {
        if (!confirm('Êtes-vous absolument certain de vouloir supprimer cette matière ?\n\nCette action ne peut pas être annulée.')) {
            e.preventDefault();
        }
    });
});
</script>

<?php include '../../../includes/footer.php'; ?>
