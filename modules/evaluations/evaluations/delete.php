<?php
/**
 * Module d'évaluations - Supprimer une évaluation
 * Application de gestion scolaire - République Démocratique du Congo
 */

require_once '../../../config/config.php';
require_once '../../../config/database.php';
require_once '../../../includes/functions.php';

// Vérifier l'authentification et les permissions
requireLogin();
if (!checkPermission('evaluations')) {
    showMessage('error', 'Accès refusé à cette fonctionnalité.');
    redirectTo('index.php');
}

// Récupérer l'ID de l'évaluation
$evaluation_id = (int)($_GET['id'] ?? 0);
if (!$evaluation_id) {
    showMessage('error', 'ID d\'évaluation manquant.');
    redirectTo('index.php');
}

// Récupérer les données de l'évaluation
$evaluation = $database->query(
    "SELECT e.*, 
            m.nom as matiere_nom,
            c.nom as classe_nom,
            CONCAT(p.nom, ' ', p.prenom) as enseignant_nom
     FROM evaluations e
     JOIN matieres m ON e.matiere_id = m.id
     JOIN classes c ON e.classe_id = c.id
     LEFT JOIN personnel p ON e.enseignant_id = p.id
     WHERE e.id = ?",
    [$evaluation_id]
)->fetch();

if (!$evaluation) {
    showMessage('error', 'Évaluation non trouvée.');
    redirectTo('index.php');
}

$page_title = 'Supprimer l\'évaluation : ' . $evaluation['nom'];

// Vérifier les dépendances
$dependencies = [];

// Vérifier les notes
$notes = $database->query(
    "SELECT COUNT(*) as count FROM notes WHERE evaluation_id = ?",
    [$evaluation_id]
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
        
        // Supprimer les notes liées
        $database->execute(
            "DELETE FROM notes WHERE evaluation_id = ?",
            [$evaluation_id]
        );
        
        // Supprimer l'évaluation
        $database->execute(
            "DELETE FROM evaluations WHERE id = ?",
            [$evaluation_id]
        );
        
        // Valider la transaction
        $database->commit();
        
        // Enregistrer l'action
        logUserAction(
            'delete_evaluation',
            'evaluations',
            "Évaluation supprimée: {$evaluation['nom']} (ID: $evaluation_id)",
            $evaluation_id
        );
        
        showMessage('success', 'L\'évaluation "' . $evaluation['nom'] . '" a été supprimée avec succès.');
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
        Supprimer l'évaluation
    </h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <div class="btn-group me-2">
            <a href="index.php" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-1"></i>
                Retour à la liste
            </a>
            <a href="view.php?id=<?php echo $evaluation_id; ?>" class="btn btn-outline-info">
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
                        Vous êtes sur le point de supprimer définitivement l'évaluation 
                        <strong>"<?php echo htmlspecialchars($evaluation['nom']); ?>"</strong>.
                        Cette action est <strong>irréversible</strong>.
                    </p>
                </div>

                <!-- Informations sur l'évaluation -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h6 class="mb-0">Informations sur l'évaluation</h6>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <p><strong>Nom :</strong> <?php echo htmlspecialchars($evaluation['nom']); ?></p>
                                <p><strong>Type :</strong> 
                                    <span class="badge bg-primary"><?php echo ucfirst($evaluation['type']); ?></span>
                                </p>
                                <p><strong>Classe :</strong> <?php echo htmlspecialchars($evaluation['classe_nom']); ?></p>
                                <p><strong>Matière :</strong> <?php echo htmlspecialchars($evaluation['matiere_nom']); ?></p>
                            </div>
                            <div class="col-md-6">
                                <p><strong>Enseignant :</strong> <?php echo htmlspecialchars($evaluation['enseignant_nom']); ?></p>
                                <p><strong>Date :</strong> <?php echo date('d/m/Y', strtotime($evaluation['date_evaluation'])); ?></p>
                                <p><strong>Période :</strong> 
                                    <span class="badge bg-success">
                                        <?php echo str_replace('_', ' ', ucfirst($evaluation['periode'])); ?>
                                    </span>
                                </p>
                                <p><strong>Note max :</strong> <span class="badge bg-info"><?php echo $evaluation['note_max']; ?> pts</span></p>
                            </div>
                        </div>
                        
                        <?php if ($evaluation['description']): ?>
                            <hr>
                            <p><strong>Description :</strong></p>
                            <p class="text-muted"><?php echo nl2br(htmlspecialchars($evaluation['description'])); ?></p>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Dépendances -->
                <?php if (!empty($dependencies)): ?>
                    <div class="alert alert-warning">
                        <h6><i class="fas fa-link me-2"></i>Éléments liés qui seront supprimés</h6>
                        <p>La suppression de cette évaluation entraînera également la suppression de :</p>
                        <ul class="mb-0">
                            <?php foreach ($dependencies as $dep): ?>
                                <li>
                                    <strong><?php echo $dep['count']; ?> <?php echo $dep['type']; ?></strong>
                                    (<?php echo $dep['description']; ?>)
                                </li>
                            <?php endforeach; ?>
                        </ul>
                        <div class="mt-3">
                            <div class="alert alert-danger mb-0">
                                <small>
                                    <i class="fas fa-exclamation-triangle me-1"></i>
                                    <strong>ATTENTION :</strong> Toutes les notes des élèves pour cette évaluation seront définitivement perdues !
                                </small>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        Cette évaluation n'a pas encore de notes saisies. 
                        Sa suppression n'affectera aucune donnée d'élève.
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
                    
                    <?php if (!empty($dependencies)): ?>
                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="understand_notes" required>
                                <label class="form-check-label" for="understand_notes">
                                    Je comprends que <strong><?php echo $dependencies[0]['count']; ?> note(s) d'élèves</strong> seront définitivement supprimées.
                                </label>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <div class="mb-3">
                        <label for="confirm_name" class="form-label">
                            Pour confirmer, tapez le nom de l'évaluation : 
                            <strong><?php echo htmlspecialchars($evaluation['nom']); ?></strong>
                        </label>
                        <input type="text" 
                               class="form-control" 
                               id="confirm_name" 
                               name="confirm_name"
                               placeholder="Tapez le nom exact de l'évaluation"
                               required>
                    </div>
                    
                    <div class="d-flex justify-content-between">
                        <a href="view.php?id=<?php echo $evaluation_id; ?>" class="btn btn-secondary">
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
    const understandNotesCheckbox = document.getElementById('understand_notes');
    const confirmNameInput = document.getElementById('confirm_name');
    const deleteBtn = document.getElementById('deleteBtn');
    const expectedName = <?php echo json_encode($evaluation['nom']); ?>;
    const hasNotes = <?php echo !empty($dependencies) ? 'true' : 'false'; ?>;
    
    function checkFormValidity() {
        const isUnderstood = understandCheckbox.checked;
        const isNotesUnderstood = hasNotes ? (understandNotesCheckbox && understandNotesCheckbox.checked) : true;
        const isNameCorrect = confirmNameInput.value.trim() === expectedName;
        
        deleteBtn.disabled = !(isUnderstood && isNotesUnderstood && isNameCorrect);
        
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
    if (understandNotesCheckbox) {
        understandNotesCheckbox.addEventListener('change', checkFormValidity);
    }
    confirmNameInput.addEventListener('input', checkFormValidity);
    
    // Confirmation finale
    document.querySelector('form').addEventListener('submit', function(e) {
        let message = 'Êtes-vous absolument certain de vouloir supprimer cette évaluation ?';
        if (hasNotes) {
            message += '\n\nToutes les notes des élèves seront définitivement perdues !';
        }
        message += '\n\nCette action ne peut pas être annulée.';
        
        if (!confirm(message)) {
            e.preventDefault();
        }
    });
});
</script>

<?php include '../../../includes/footer.php'; ?>
