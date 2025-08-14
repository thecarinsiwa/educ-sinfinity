<?php
/**
 * Module Académique - Ajout de note pour un élève
 * Application de gestion scolaire - République Démocratique du Congo
 */

require_once '../../../config/config.php';
require_once '../../../config/database.php';
require_once '../../../includes/functions.php';

// Vérifier l'authentification et les permissions
requireLogin();
if (!checkPermission('evaluations')) {
    showMessage('error', 'Accès refusé à cette fonctionnalité.');
    redirectTo('../../dashboard.php');
}

$page_title = 'Ajouter une note';

// Récupérer les paramètres
$evaluation_id = (int)($_GET['evaluation_id'] ?? 0);
$eleve_id = (int)($_GET['eleve_id'] ?? 0);

if (!$evaluation_id || !$eleve_id) {
    showMessage('error', 'Paramètres manquants (évaluation_id et eleve_id requis).');
    redirectTo('../../dashboard.php');
}

// Récupérer les informations de l'évaluation
$evaluation = $database->query(
    "SELECT e.*,
            m.nom as matiere_nom, m.coefficient as matiere_coefficient,
            c.nom as classe_nom, c.niveau,
            p.nom as enseignant_nom, p.prenom as enseignant_prenom
     FROM evaluations e
     JOIN matieres m ON e.matiere_id = m.id
     JOIN classes c ON e.classe_id = c.id
     LEFT JOIN personnel p ON e.enseignant_id = p.id
     WHERE e.id = ?",
    [$evaluation_id]
)->fetch();

if (!$evaluation) {
    showMessage('error', 'Évaluation non trouvée.');
    redirectTo('../../dashboard.php');
}

// Récupérer les informations de l'élève
$eleve = $database->query(
    "SELECT e.*, i.classe_id, c.nom as classe_nom, c.niveau
     FROM eleves e
     JOIN inscriptions i ON e.id = i.eleve_id
     JOIN classes c ON i.classe_id = c.id
     WHERE e.id = ? AND i.status = 'inscrit' AND i.annee_scolaire_id = ?",
    [$eleve_id, $evaluation['annee_scolaire_id']]
)->fetch();

if (!$eleve) {
    showMessage('error', 'Élève non trouvé ou non inscrit dans cette année scolaire.');
    redirectTo('../../dashboard.php');
}

// Vérifier que l'élève appartient à la classe de l'évaluation
if ($eleve['classe_id'] != $evaluation['classe_id']) {
    showMessage('error', 'Cet élève n\'appartient pas à la classe de cette évaluation.');
    redirectTo('../../dashboard.php');
}

// Récupérer la note existante si elle existe
$note_existante = $database->query(
    "SELECT * FROM notes WHERE eleve_id = ? AND evaluation_id = ?",
    [$eleve_id, $evaluation_id]
)->fetch();

$errors = [];
$success = false;

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $note_value = trim($_POST['note'] ?? '');
    $observation = trim($_POST['observation'] ?? '');
    
    // Validation
    if ($note_value === '') {
        $errors[] = 'La note est obligatoire.';
    } else {
        $note_value = (float)$note_value;
        
        if ($note_value < 0 || $note_value > $evaluation['note_max']) {
            $errors[] = "La note doit être comprise entre 0 et {$evaluation['note_max']}.";
        }
    }
    
    if (empty($errors)) {
        try {
            $database->beginTransaction();
            
            if ($note_existante) {
                // Mettre à jour la note existante
                $database->execute(
                    "UPDATE notes SET note = ?, observation = ?, updated_at = NOW() WHERE id = ?",
                    [$note_value, $observation, $note_existante['id']]
                );
                $message = 'Note mise à jour avec succès !';
            } else {
                // Créer une nouvelle note
                $database->execute(
                    "INSERT INTO notes (eleve_id, evaluation_id, note, observation, created_at, updated_at) VALUES (?, ?, ?, ?, NOW(), NOW())",
                    [$eleve_id, $evaluation_id, $note_value, $observation]
                );
                $message = 'Note ajoutée avec succès !';
            }
            
            // Mettre à jour le statut de l'évaluation si nécessaire
            if ($evaluation['status'] === 'programmee') {
                $database->execute(
                    "UPDATE evaluations SET status = 'en_cours' WHERE id = ?",
                    [$evaluation_id]
                );
            }
            
            $database->commit();
            showMessage('success', $message);
            
            // Recharger la note existante
            $note_existante = $database->query(
                "SELECT * FROM notes WHERE eleve_id = ? AND evaluation_id = ?",
                [$eleve_id, $evaluation_id]
            )->fetch();
            
            $success = true;
            
        } catch (Exception $e) {
            $database->rollback();
            $errors[] = 'Erreur lors de l\'enregistrement : ' . $e->getMessage();
        }
    }
}

include '../../../includes/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">
        <i class="fas fa-plus-circle me-2"></i>
        Ajouter une note
    </h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <div class="btn-group me-2">
            <a href="../evaluations/view.php?id=<?php echo $evaluation_id; ?>" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-1"></i>
                Retour à l'évaluation
            </a>
        </div>
        <div class="btn-group">
            <a href="entry.php?evaluation_id=<?php echo $evaluation_id; ?>" class="btn btn-outline-primary">
                <i class="fas fa-list me-1"></i>
                Saisie complète
            </a>
        </div>
    </div>
</div>

<!-- Informations de l'évaluation -->
<div class="row mb-4">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-clipboard-list me-2"></i>
                    Informations de l'évaluation
                </h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <p class="mb-1"><strong>Évaluation :</strong> <?php echo htmlspecialchars($evaluation['nom']); ?></p>
                        <p class="mb-1"><strong>Classe :</strong> 
                            <span class="badge bg-<?php 
                                echo $evaluation['niveau'] === 'maternelle' ? 'warning' : 
                                    ($evaluation['niveau'] === 'primaire' ? 'success' : 'primary'); 
                            ?>">
                                <?php echo htmlspecialchars($evaluation['classe_nom']); ?>
                            </span>
                        </p>
                        <p class="mb-1"><strong>Matière :</strong> <?php echo htmlspecialchars($evaluation['matiere_nom']); ?></p>
                    </div>
                    <div class="col-md-6">
                        <p class="mb-1"><strong>Date :</strong> <?php echo formatDate($evaluation['date_evaluation']); ?></p>
                        <p class="mb-1"><strong>Note max :</strong> <?php echo $evaluation['note_max']; ?> points</p>
                        <p class="mb-1"><strong>Coefficient :</strong> <?php echo $evaluation['coefficient']; ?></p>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-4">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-user me-2"></i>
                    Informations de l'élève
                </h5>
            </div>
            <div class="card-body">
                <h6><?php echo htmlspecialchars($eleve['nom'] . ' ' . $eleve['prenom']); ?></h6>
                <p class="mb-1"><strong>Matricule :</strong> <?php echo htmlspecialchars($eleve['numero_matricule']); ?></p>
                <p class="mb-1"><strong>Classe :</strong> <?php echo htmlspecialchars($eleve['classe_nom']); ?></p>
                <p class="mb-0"><strong>Niveau :</strong> <?php echo ucfirst($eleve['niveau']); ?></p>
            </div>
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

<!-- Formulaire d'ajout de note -->
<div class="row">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-edit me-2"></i>
                    <?php echo $note_existante ? 'Modifier la note' : 'Ajouter une note'; ?>
                </h5>
            </div>
            <div class="card-body">
                <form method="POST" id="note-form">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="note" class="form-label">
                                    Note <span class="text-danger">*</span>
                                </label>
                                <div class="input-group">
                                    <input type="number" 
                                           class="form-control" 
                                           id="note" 
                                           name="note" 
                                           value="<?php echo $note_existante ? $note_existante['note'] : ''; ?>"
                                           min="0" 
                                           max="<?php echo $evaluation['note_max']; ?>" 
                                           step="0.25"
                                           placeholder="0-<?php echo $evaluation['note_max']; ?>"
                                           required>
                                    <span class="input-group-text">/ <?php echo $evaluation['note_max']; ?></span>
                                </div>
                                <div class="form-text">
                                    Note sur <?php echo $evaluation['note_max']; ?> points (pas de 0.25)
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="observation" class="form-label">Observation</label>
                                <textarea class="form-control" 
                                          id="observation" 
                                          name="observation" 
                                          rows="3" 
                                          placeholder="Commentaire sur la performance de l'élève..."
                                          maxlength="255"><?php echo htmlspecialchars($note_existante['observation'] ?? ''); ?></textarea>
                                <div class="form-text">
                                    Commentaire optionnel (max 255 caractères)
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <?php if ($note_existante): ?>
                                <small class="text-muted">
                                    <i class="fas fa-info-circle me-1"></i>
                                    Note créée le <?php echo formatDate($note_existante['created_at']); ?>
                                    <?php if ($note_existante['updated_at'] !== $note_existante['created_at']): ?>
                                        - Modifiée le <?php echo formatDate($note_existante['updated_at']); ?>
                                    <?php endif; ?>
                                </small>
                            <?php endif; ?>
                        </div>
                        <div>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-1"></i>
                                <?php echo $note_existante ? 'Mettre à jour' : 'Enregistrer'; ?>
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <div class="col-md-4">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-chart-bar me-2"></i>
                    Aperçu de la note
                </h5>
            </div>
            <div class="card-body">
                <div id="note-preview">
                    <?php if ($note_existante): ?>
                        <div class="text-center mb-3">
                            <h3 class="text-<?php 
                                echo $note_existante['note'] >= 14 ? 'success' : 
                                    ($note_existante['note'] >= 10 ? 'warning' : 'danger'); 
                            ?>">
                                <?php echo $note_existante['note']; ?>/<?php echo $evaluation['note_max']; ?>
                            </h3>
                            <span class="badge bg-<?php 
                                echo $note_existante['note'] >= 14 ? 'success' : 
                                    ($note_existante['note'] >= 10 ? 'warning' : 'danger'); 
                            ?> fs-6">
                                <?php 
                                if ($note_existante['note'] >= 16) echo 'Excellent';
                                elseif ($note_existante['note'] >= 14) echo 'Très bien';
                                elseif ($note_existante['note'] >= 12) echo 'Bien';
                                elseif ($note_existante['note'] >= 10) echo 'Satisfaisant';
                                elseif ($note_existante['note'] >= 8) echo 'Passable';
                                else echo 'Insuffisant';
                                ?>
                            </span>
                        </div>
                        <div class="progress mb-3">
                            <div class="progress-bar bg-<?php 
                                echo $note_existante['note'] >= 14 ? 'success' : 
                                    ($note_existante['note'] >= 10 ? 'warning' : 'danger'); 
                            ?>" 
                                 style="width: <?php echo ($note_existante['note'] / $evaluation['note_max']) * 100; ?>%">
                            </div>
                        </div>
                        <p class="mb-1"><strong>Pourcentage :</strong> 
                            <?php echo round(($note_existante['note'] / $evaluation['note_max']) * 100, 1); ?>%
                        </p>
                        <p class="mb-1"><strong>Note pondérée :</strong> 
                            <?php echo round($note_existante['note'] * $evaluation['coefficient'], 2); ?>
                        </p>
                    <?php else: ?>
                        <div class="text-center text-muted">
                            <i class="fas fa-edit fa-3x mb-3"></i>
                            <p>Aucune note enregistrée</p>
                            <small>Saisissez une note pour voir l'aperçu</small>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Actions rapides -->
        <div class="card mt-3">
            <div class="card-header">
                <h6 class="mb-0">
                    <i class="fas fa-tools me-2"></i>
                    Actions rapides
                </h6>
            </div>
            <div class="card-body">
                <div class="d-grid gap-2">
                    <a href="student.php?eleve_id=<?php echo $eleve_id; ?>" class="btn btn-outline-info btn-sm">
                        <i class="fas fa-user-graduate me-1"></i>
                        Notes de l'élève
                    </a>
                    <a href="../evaluations/view.php?id=<?php echo $evaluation_id; ?>" class="btn btn-outline-secondary btn-sm">
                        <i class="fas fa-clipboard-list me-1"></i>
                        Détails évaluation
                    </a>
                    <a href="entry.php?evaluation_id=<?php echo $evaluation_id; ?>" class="btn btn-outline-primary btn-sm">
                        <i class="fas fa-list me-1"></i>
                        Saisie complète
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Mise à jour en temps réel de l'aperçu de la note
document.getElementById('note').addEventListener('input', function() {
    const note = parseFloat(this.value) || 0;
    const noteMax = <?php echo $evaluation['note_max']; ?>;
    const preview = document.getElementById('note-preview');
    
    if (note > 0) {
        const percentage = (note / noteMax) * 100;
        let color = 'danger';
        let appreciation = 'Insuffisant';
        
        if (note >= 16) {
            color = 'success';
            appreciation = 'Excellent';
        } else if (note >= 14) {
            color = 'success';
            appreciation = 'Très bien';
        } else if (note >= 12) {
            color = 'warning';
            appreciation = 'Bien';
        } else if (note >= 10) {
            color = 'warning';
            appreciation = 'Satisfaisant';
        } else if (note >= 8) {
            color = 'danger';
            appreciation = 'Passable';
        }
        
        preview.innerHTML = `
            <div class="text-center mb-3">
                <h3 class="text-${color}">${note}/${noteMax}</h3>
                <span class="badge bg-${color} fs-6">${appreciation}</span>
            </div>
            <div class="progress mb-3">
                <div class="progress-bar bg-${color}" style="width: ${percentage}%"></div>
            </div>
            <p class="mb-1"><strong>Pourcentage :</strong> ${percentage.toFixed(1)}%</p>
            <p class="mb-1"><strong>Note pondérée :</strong> ${(note * <?php echo $evaluation['coefficient']; ?>).toFixed(2)}</p>
        `;
    } else {
        preview.innerHTML = `
            <div class="text-center text-muted">
                <i class="fas fa-edit fa-3x mb-3"></i>
                <p>Aucune note enregistrée</p>
                <small>Saisissez une note pour voir l'aperçu</small>
            </div>
        `;
    }
});

// Validation du formulaire
document.getElementById('note-form').addEventListener('submit', function(e) {
    const note = parseFloat(document.getElementById('note').value);
    const noteMax = <?php echo $evaluation['note_max']; ?>;
    
    if (note < 0 || note > noteMax) {
        e.preventDefault();
        alert(`La note doit être comprise entre 0 et ${noteMax}.`);
        return false;
    }
});
</script>

<?php include '../../../includes/footer.php'; ?>
