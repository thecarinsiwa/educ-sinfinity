<?php
/**
 * Module d'évaluations - Saisie en lot des notes
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

$page_title = 'Saisie en lot des notes';

// Récupérer l'année scolaire active
$current_year = $database->query("SELECT * FROM annees_scolaires WHERE status = 'active' LIMIT 1")->fetch();
if (!$current_year) {
    showMessage('error', 'Aucune année scolaire active trouvée.');
    redirectTo('../../../index.php');
}

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $evaluation_id = (int)($_POST['evaluation_id'] ?? 0);
    $notes_data = $_POST['notes'] ?? [];
    
    if ($evaluation_id && !empty($notes_data)) {
        try {
            $database->beginTransaction();
            
            $success_count = 0;
            $error_count = 0;
            $errors = [];
            
            foreach ($notes_data as $eleve_id => $note_info) {
                $eleve_id = (int)$eleve_id;
                $note = floatval($note_info['note'] ?? 0);
                $observation = sanitizeInput($note_info['observation'] ?? '');
                
                if ($note > 0) {
                    try {
                        // Vérifier si la note existe déjà
                        $existing_note = $database->query(
                            "SELECT id FROM notes WHERE evaluation_id = ? AND eleve_id = ?",
                            [$evaluation_id, $eleve_id]
                        )->fetch();
                        
                        if ($existing_note) {
                            // Mettre à jour la note existante
                            $database->query(
                                "UPDATE notes SET note = ?, observation = ?
                                 WHERE evaluation_id = ? AND eleve_id = ?",
                                [$note, $observation, $evaluation_id, $eleve_id]
                            );
                        } else {
                            // Insérer une nouvelle note
                            $database->query(
                                "INSERT INTO notes (evaluation_id, eleve_id, note, observation)
                                 VALUES (?, ?, ?, ?)",
                                [$evaluation_id, $eleve_id, $note, $observation]
                            );
                        }
                        $success_count++;
                    } catch (Exception $e) {
                        $error_count++;
                        $errors[] = "Erreur pour l'élève ID $eleve_id: " . $e->getMessage();
                    }
                }
            }
            
            $database->commit();
            
            if ($success_count > 0) {
                showMessage('success', "$success_count note(s) saisie(s) avec succès.");
            }
            if ($error_count > 0) {
                showMessage('warning', "$error_count erreur(s) rencontrée(s).");
                foreach ($errors as $error) {
                    showMessage('error', $error);
                }
            }
            
        } catch (Exception $e) {
            $database->rollBack();
            showMessage('error', 'Erreur lors de la sauvegarde: ' . $e->getMessage());
        }
    } else {
        showMessage('error', 'Données manquantes pour la saisie en lot.');
    }
}

// Récupérer les paramètres de filtrage
$classe_filter = (int)($_GET['classe_id'] ?? 0);
$matiere_filter = (int)($_GET['matiere_id'] ?? 0);
$evaluation_id = (int)($_GET['evaluation_id'] ?? 0);

// Récupérer les listes pour les filtres
$classes = $database->query(
    "SELECT * FROM classes WHERE annee_scolaire_id = ? ORDER BY niveau, nom",
    [$current_year['id']]
)->fetchAll();

$matieres = $database->query(
    "SELECT * FROM matieres ORDER BY niveau, nom"
)->fetchAll();

// Récupérer les évaluations selon les filtres
$evaluations = [];
if ($classe_filter && $matiere_filter) {
    $evaluations = $database->query(
        "SELECT e.*, m.nom as matiere_nom, c.nom as classe_nom
         FROM evaluations e
         JOIN matieres m ON e.matiere_id = m.id
         JOIN classes c ON e.classe_id = c.id
         WHERE e.annee_scolaire_id = ? AND e.classe_id = ? AND e.matiere_id = ?
         ORDER BY e.date_evaluation DESC",
        [$current_year['id'], $classe_filter, $matiere_filter]
    )->fetchAll();
}

// Récupérer les élèves et leurs notes si une évaluation est sélectionnée
$eleves_notes = [];
$evaluation_details = null;
if ($evaluation_id) {
    // Détails de l'évaluation
    $evaluation_details = $database->query(
        "SELECT e.*, m.nom as matiere_nom, c.nom as classe_nom, m.coefficient
         FROM evaluations e
         JOIN matieres m ON e.matiere_id = m.id
         JOIN classes c ON e.classe_id = c.id
         WHERE e.id = ?",
        [$evaluation_id]
    )->fetch();
    
    if ($evaluation_details) {
        // Récupérer les élèves avec leurs notes existantes
        $eleves_notes = $database->query(
            "SELECT el.id, el.nom, el.prenom, el.numero_matricule,
                    n.note, n.observation
             FROM eleves el
             JOIN inscriptions i ON el.id = i.eleve_id
             LEFT JOIN notes n ON (el.id = n.eleve_id AND n.evaluation_id = ?)
             WHERE i.classe_id = ? AND i.annee_scolaire_id = ? AND i.status = 'inscrit'
             ORDER BY el.nom, el.prenom",
            [$evaluation_id, $evaluation_details['classe_id'], $current_year['id']]
        )->fetchAll();
    }
}

include '../../../includes/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">
        <i class="fas fa-edit me-2"></i>
        Saisie en lot des notes
    </h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <div class="btn-group me-2">
            <a href="index.php" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-1"></i>
                Retour aux notes
            </a>
            <a href="entry.php" class="btn btn-outline-info">
                <i class="fas fa-plus me-1"></i>
                Saisie individuelle
            </a>
        </div>
    </div>
</div>

<!-- Filtres de sélection -->
<div class="card mb-4">
    <div class="card-header">
        <h5 class="mb-0">
            <i class="fas fa-filter me-2"></i>
            Sélection de l'évaluation
        </h5>
    </div>
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-md-4">
                <label for="classe_id" class="form-label">Classe</label>
                <select class="form-select" id="classe_id" name="classe_id" required>
                    <option value="">Sélectionner une classe...</option>
                    <?php foreach ($classes as $classe): ?>
                        <option value="<?php echo $classe['id']; ?>" 
                                <?php echo $classe_filter == $classe['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($classe['nom']); ?> 
                            (<?php echo ucfirst($classe['niveau']); ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="col-md-4">
                <label for="matiere_id" class="form-label">Matière</label>
                <select class="form-select" id="matiere_id" name="matiere_id" required>
                    <option value="">Sélectionner une matière...</option>
                    <?php foreach ($matieres as $matiere): ?>
                        <option value="<?php echo $matiere['id']; ?>" 
                                <?php echo $matiere_filter == $matiere['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($matiere['nom']); ?>
                            (Coef. <?php echo $matiere['coefficient']; ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="col-md-4">
                <label for="evaluation_id" class="form-label">Évaluation</label>
                <select class="form-select" id="evaluation_id" name="evaluation_id">
                    <option value="">Sélectionner une évaluation...</option>
                    <?php foreach ($evaluations as $eval): ?>
                        <option value="<?php echo $eval['id']; ?>" 
                                <?php echo $evaluation_id == $eval['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($eval['nom']); ?> 
                            (<?php echo date('d/m/Y', strtotime($eval['date_evaluation'])); ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="col-12">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-search me-1"></i>
                    Charger l'évaluation
                </button>
                <?php if ($evaluation_id): ?>
                    <a href="batch-entry.php" class="btn btn-outline-secondary">
                        <i class="fas fa-times me-1"></i>
                        Réinitialiser
                    </a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<?php if ($evaluation_details && !empty($eleves_notes)): ?>
    <!-- Informations sur l'évaluation -->
    <div class="card mb-4">
        <div class="card-header bg-info text-white">
            <h5 class="mb-0">
                <i class="fas fa-clipboard-check me-2"></i>
                <?php echo htmlspecialchars($evaluation_details['nom']); ?>
            </h5>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-3">
                    <strong>Classe :</strong><br>
                    <?php echo htmlspecialchars($evaluation_details['classe_nom']); ?>
                </div>
                <div class="col-md-3">
                    <strong>Matière :</strong><br>
                    <?php echo htmlspecialchars($evaluation_details['matiere_nom']); ?>
                </div>
                <div class="col-md-3">
                    <strong>Date :</strong><br>
                    <?php echo date('d/m/Y', strtotime($evaluation_details['date_evaluation'])); ?>
                </div>
                <div class="col-md-3">
                    <strong>Note maximale :</strong><br>
                    <?php echo $evaluation_details['note_max']; ?> points
                </div>
            </div>
        </div>
    </div>

    <!-- Formulaire de saisie en lot -->
    <form method="POST" id="batchEntryForm">
        <input type="hidden" name="evaluation_id" value="<?php echo $evaluation_id; ?>">
        
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">
                    <i class="fas fa-users me-2"></i>
                    Saisie des notes (<?php echo count($eleves_notes); ?> élèves)
                </h5>
                <div class="btn-group btn-group-sm">
                    <button type="button" class="btn btn-outline-success" onclick="fillAllNotes()">
                        <i class="fas fa-fill me-1"></i>
                        Remplir tout
                    </button>
                    <button type="button" class="btn btn-outline-warning" onclick="clearAllNotes()">
                        <i class="fas fa-eraser me-1"></i>
                        Effacer tout
                    </button>
                </div>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th width="5%">#</th>
                                <th width="15%">Matricule</th>
                                <th width="30%">Élève</th>
                                <th width="15%">Note / <?php echo $evaluation_details['note_max']; ?></th>
                                <th width="15%">Note / 20</th>
                                <th width="20%">Observation</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($eleves_notes as $index => $eleve): ?>
                                <tr>
                                    <td><?php echo $index + 1; ?></td>
                                    <td>
                                        <code><?php echo htmlspecialchars($eleve['numero_matricule']); ?></code>
                                    </td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($eleve['nom'] . ' ' . $eleve['prenom']); ?></strong>
                                    </td>
                                    <td>
                                        <input type="number" 
                                               class="form-control note-input" 
                                               name="notes[<?php echo $eleve['id']; ?>][note]"
                                               value="<?php echo $eleve['note'] ?? ''; ?>"
                                               min="0" 
                                               max="<?php echo $evaluation_details['note_max']; ?>" 
                                               step="0.25"
                                               data-max="<?php echo $evaluation_details['note_max']; ?>"
                                               data-eleve-id="<?php echo $eleve['id']; ?>"
                                               onchange="updateNote20(this)">
                                    </td>
                                    <td>
                                        <span class="badge bg-secondary note-20" id="note20_<?php echo $eleve['id']; ?>">
                                            <?php 
                                            if ($eleve['note']) {
                                                echo round(($eleve['note'] / $evaluation_details['note_max']) * 20, 2);
                                            } else {
                                                echo '-';
                                            }
                                            ?>
                                        </span>
                                    </td>
                                    <td>
                                        <input type="text" 
                                               class="form-control form-control-sm" 
                                               name="notes[<?php echo $eleve['id']; ?>][observation]"
                                               value="<?php echo htmlspecialchars($eleve['observation'] ?? ''); ?>"
                                               placeholder="Observation...">
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <div class="d-flex justify-content-between align-items-center mt-3">
                    <div>
                        <small class="text-muted">
                            <i class="fas fa-info-circle me-1"></i>
                            Les notes vides ne seront pas sauvegardées. 
                            Utilisez des décimales avec un point (ex: 15.5).
                        </small>
                    </div>
                    <div>
                        <button type="submit" class="btn btn-success btn-lg">
                            <i class="fas fa-save me-2"></i>
                            Sauvegarder toutes les notes
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </form>

<?php elseif ($classe_filter && $matiere_filter && empty($evaluations)): ?>
    <!-- Aucune évaluation trouvée -->
    <div class="card">
        <div class="card-body text-center py-5">
            <i class="fas fa-clipboard fa-3x text-muted mb-3"></i>
            <h5 class="text-muted">Aucune évaluation trouvée</h5>
            <p class="text-muted">
                Aucune évaluation n'a été trouvée pour cette combinaison classe/matière.<br>
                <a href="../evaluations/create.php" class="btn btn-primary mt-2">
                    <i class="fas fa-plus me-1"></i>
                    Créer une évaluation
                </a>
            </p>
        </div>
    </div>

<?php else: ?>
    <!-- Instructions -->
    <div class="card">
        <div class="card-body text-center py-5">
            <i class="fas fa-hand-point-up fa-3x text-primary mb-3"></i>
            <h5>Sélectionnez une évaluation</h5>
            <p class="text-muted">
                Choisissez d'abord une classe et une matière pour voir les évaluations disponibles,<br>
                puis sélectionnez l'évaluation pour laquelle vous souhaitez saisir les notes en lot.
            </p>
        </div>
    </div>
<?php endif; ?>

<script>
// Fonction pour mettre à jour la note sur 20
function updateNote20(input) {
    const note = parseFloat(input.value) || 0;
    const noteMax = parseFloat(input.dataset.max);
    const eleveId = input.dataset.eleveId;
    const note20Element = document.getElementById('note20_' + eleveId);
    
    if (note > 0) {
        const note20 = Math.round((note / noteMax) * 20 * 100) / 100;
        note20Element.textContent = note20.toFixed(2);
        
        // Changer la couleur selon la note
        note20Element.className = 'badge ';
        if (note20 >= 16) note20Element.className += 'bg-success';
        else if (note20 >= 14) note20Element.className += 'bg-info';
        else if (note20 >= 12) note20Element.className += 'bg-primary';
        else if (note20 >= 10) note20Element.className += 'bg-warning';
        else note20Element.className += 'bg-danger';
    } else {
        note20Element.textContent = '-';
        note20Element.className = 'badge bg-secondary';
    }
}

// Fonction pour remplir toutes les notes avec une valeur
function fillAllNotes() {
    const noteValue = prompt('Entrez la note à attribuer à tous les élèves:', '');
    if (noteValue !== null && noteValue !== '') {
        const note = parseFloat(noteValue);
        const noteInputs = document.querySelectorAll('.note-input');
        const noteMax = parseFloat(noteInputs[0]?.dataset.max || 20);
        
        if (note >= 0 && note <= noteMax) {
            noteInputs.forEach(input => {
                input.value = noteValue;
                updateNote20(input);
            });
        } else {
            alert('La note doit être comprise entre 0 et ' + noteMax);
        }
    }
}

// Fonction pour effacer toutes les notes
function clearAllNotes() {
    if (confirm('Êtes-vous sûr de vouloir effacer toutes les notes saisies ?')) {
        const noteInputs = document.querySelectorAll('.note-input');
        noteInputs.forEach(input => {
            input.value = '';
            updateNote20(input);
        });
        
        const observationInputs = document.querySelectorAll('input[name*="[observation]"]');
        observationInputs.forEach(input => {
            input.value = '';
        });
    }
}

// Validation du formulaire
document.getElementById('batchEntryForm')?.addEventListener('submit', function(e) {
    const noteInputs = document.querySelectorAll('.note-input');
    let hasNotes = false;
    
    noteInputs.forEach(input => {
        if (input.value && parseFloat(input.value) > 0) {
            hasNotes = true;
        }
    });
    
    if (!hasNotes) {
        e.preventDefault();
        alert('Veuillez saisir au moins une note avant de sauvegarder.');
        return false;
    }
    
    return confirm('Êtes-vous sûr de vouloir sauvegarder ces notes ? Cette action remplacera les notes existantes.');
});

// Auto-complétion des filtres
document.addEventListener('DOMContentLoaded', function() {
    const classeSelect = document.getElementById('classe_id');
    const matiereSelect = document.getElementById('matiere_id');
    
    function updateEvaluations() {
        if (classeSelect.value && matiereSelect.value) {
            // Auto-submit du formulaire quand classe et matière sont sélectionnées
            // (optionnel - peut être activé si souhaité)
        }
    }
    
    classeSelect.addEventListener('change', updateEvaluations);
    matiereSelect.addEventListener('change', updateEvaluations);
});
</script>

<?php include '../../../includes/footer.php'; ?>
