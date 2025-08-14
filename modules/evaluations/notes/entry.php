<?php
/**
 * Module d'évaluations et notes - Saisie de notes
 * Application de gestion scolaire - République Démocratique du Congo
 */

require_once '../../../config/config.php';
require_once '../../../config/database.php';
require_once '../../../includes/functions.php';

// Vérifier l'authentification et les permissions
requireLogin();
if (!checkPermission('evaluations')) {
    showMessage('error', 'Accès refusé à cette fonctionnalité.');
    redirectTo('../index.php');
}

$page_title = 'Saisie de notes';

// Récupérer l'ID de l'évaluation
$evaluation_id = (int)($_GET['evaluation_id'] ?? 0);
if (!$evaluation_id) {
    showMessage('error', 'Évaluation non spécifiée.');
    redirectTo('../evaluations/index.php');
}

// Récupérer les informations de l'évaluation
$sql = "SELECT e.*,
               m.nom as matiere_nom, m.coefficient as matiere_coefficient,
               c.nom as classe_nom, c.niveau,
               p.nom as enseignant_nom, p.prenom as enseignant_prenom
        FROM evaluations e
        JOIN matieres m ON e.matiere_id = m.id
        JOIN classes c ON e.classe_id = c.id
        LEFT JOIN personnel p ON e.enseignant_id = p.id
        WHERE e.id = ?";

$evaluation = $database->query($sql, [$evaluation_id])->fetch();

if (!$evaluation) {
    showMessage('error', 'Évaluation non trouvée.');
    redirectTo('../evaluations/index.php');
}

// Récupérer la liste des élèves de la classe avec leurs notes existantes
$sql = "SELECT e.id, e.nom, e.prenom, e.numero_matricule,
               n.note, n.observation, n.id as note_id
        FROM eleves e
        JOIN inscriptions i ON e.id = i.eleve_id
        LEFT JOIN notes n ON e.id = n.eleve_id AND n.evaluation_id = ?
        WHERE i.classe_id = ? AND i.status = 'inscrit' AND i.annee_scolaire_id = ?
        ORDER BY e.nom, e.prenom";

$eleves = $database->query($sql, [
    $evaluation_id, 
    $evaluation['classe_id'], 
    $evaluation['annee_scolaire_id']
])->fetchAll();

$errors = [];
$success = false;

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $notes_data = $_POST['notes'] ?? [];
    $observations_data = $_POST['observations'] ?? [];
    
    if (empty($notes_data)) {
        $errors[] = 'Aucune note à enregistrer.';
    } else {
        try {
            $database->beginTransaction();
            
            $notes_saved = 0;
            $notes_updated = 0;
            
            foreach ($notes_data as $eleve_id => $note_value) {
                $eleve_id = (int)$eleve_id;
                $note_value = trim($note_value);
                $observation = trim($observations_data[$eleve_id] ?? '');
                
                // Valider la note
                if ($note_value !== '') {
                    $note_value = (float)$note_value;
                    
                    if ($note_value < 0 || $note_value > $evaluation['note_max']) {
                        $errors[] = "Note invalide pour l'élève ID $eleve_id (doit être entre 0 et {$evaluation['note_max']}).";
                        continue;
                    }
                } else {
                    $note_value = null;
                }
                
                // Vérifier si une note existe déjà
                $existing_note = $database->query(
                    "SELECT id FROM notes WHERE eleve_id = ? AND evaluation_id = ?",
                    [$eleve_id, $evaluation_id]
                )->fetch();
                
                if ($existing_note) {
                    // Mettre à jour la note existante
                    $database->execute(
                        "UPDATE notes SET note = ?, observation = ? WHERE id = ?",
                        [$note_value, $observation, $existing_note['id']]
                    );
                    $notes_updated++;
                } else {
                    // Créer une nouvelle note
                    $database->execute(
                        "INSERT INTO notes (eleve_id, evaluation_id, note, observation) VALUES (?, ?, ?, ?)",
                        [$eleve_id, $evaluation_id, $note_value, $observation]
                    );
                    $notes_saved++;
                }
            }
            
            // Mettre à jour le statut de l'évaluation si nécessaire
            if ($evaluation['status'] === 'programmee') {
                $database->execute(
                    "UPDATE evaluations SET status = 'en_cours' WHERE id = ?",
                    [$evaluation_id]
                );
            }
            
            $database->commit();
            
            $message = "Notes enregistrées avec succès ! ";
            $message .= "($notes_saved nouvelles notes, $notes_updated notes mises à jour)";
            showMessage('success', $message);
            
            // Recharger les données
            $eleves = $database->query($sql, [
                $evaluation_id, 
                $evaluation['classe_id'], 
                $evaluation['annee_scolaire_id']
            ])->fetchAll();
            
        } catch (Exception $e) {
            $database->rollback();
            $errors[] = 'Erreur lors de l\'enregistrement : ' . $e->getMessage();
        }
    }
}

// Calculer les statistiques
$stats = [
    'total_eleves' => count($eleves),
    'notes_saisies' => count(array_filter($eleves, fn($e) => $e['note'] !== null)),
    'moyenne_classe' => 0,
    'note_min' => null,
    'note_max' => null
];

$notes_valides = array_filter(array_column($eleves, 'note'), fn($n) => $n !== null);
if (!empty($notes_valides)) {
    $stats['moyenne_classe'] = round(array_sum($notes_valides) / count($notes_valides), 2);
    $stats['note_min'] = min($notes_valides);
    $stats['note_max'] = max($notes_valides);
}

include '../../../includes/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">
        <i class="fas fa-edit me-2"></i>
        Saisie de notes
    </h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <div class="btn-group me-2">
            <a href="../evaluations/view.php?id=<?php echo $evaluation_id; ?>" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-1"></i>
                Retour à l'évaluation
            </a>
        </div>
        <div class="btn-group">
            <button type="button" class="btn btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">
                <i class="fas fa-tools me-1"></i>
                Outils
            </button>
            <ul class="dropdown-menu">
                <li><a class="dropdown-item" href="#" onclick="fillAllNotes()">
                    <i class="fas fa-fill me-2"></i>Remplir toutes les notes
                </a></li>
                <li><a class="dropdown-item" href="#" onclick="clearAllNotes()">
                    <i class="fas fa-eraser me-2"></i>Effacer toutes les notes
                </a></li>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item" href="#" onclick="calculateStats()">
                    <i class="fas fa-calculator me-2"></i>Recalculer statistiques
                </a></li>
            </ul>
        </div>
    </div>
</div>

<!-- Informations de l'évaluation -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-body">
                <div class="row">
                    <div class="col-md-8">
                        <h5 class="card-title">
                            <i class="fas fa-clipboard-list me-2"></i>
                            <?php echo htmlspecialchars($evaluation['nom']); ?>
                        </h5>
                        <div class="row">
                            <div class="col-md-6">
                                <p class="mb-1"><strong>Classe :</strong> 
                                    <span class="badge bg-<?php 
                                        echo $evaluation['niveau'] === 'maternelle' ? 'warning' : 
                                            ($evaluation['niveau'] === 'primaire' ? 'success' : 'primary'); 
                                    ?>">
                                        <?php echo htmlspecialchars($evaluation['classe_nom']); ?>
                                    </span>
                                </p>
                                <p class="mb-1"><strong>Matière :</strong> <?php echo htmlspecialchars($evaluation['matiere_nom']); ?></p>
                                <p class="mb-1"><strong>Type :</strong>
                                    <span class="badge bg-info"><?php echo ucfirst($evaluation['type_evaluation']); ?></span>
                                </p>
                            </div>
                            <div class="col-md-6">
                                <p class="mb-1"><strong>Date :</strong> <?php echo formatDate($evaluation['date_evaluation']); ?></p>
                                <p class="mb-1"><strong>Note max :</strong> <?php echo $evaluation['note_max']; ?> points</p>
                                <p class="mb-1"><strong>Coefficient :</strong> <?php echo $evaluation['coefficient']; ?></p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="text-end">
                            <h6>Statistiques</h6>
                            <p class="mb-1"><strong>Élèves :</strong> <?php echo $stats['total_eleves']; ?></p>
                            <p class="mb-1"><strong>Notes saisies :</strong> 
                                <span class="badge bg-<?php echo $stats['notes_saisies'] == $stats['total_eleves'] ? 'success' : 'warning'; ?>">
                                    <?php echo $stats['notes_saisies']; ?>/<?php echo $stats['total_eleves']; ?>
                                </span>
                            </p>
                            <?php if ($stats['moyenne_classe'] > 0): ?>
                                <p class="mb-1"><strong>Moyenne :</strong> 
                                    <span class="badge bg-<?php 
                                        echo $stats['moyenne_classe'] >= 14 ? 'success' : 
                                            ($stats['moyenne_classe'] >= 10 ? 'warning' : 'danger'); 
                                    ?>">
                                        <?php echo $stats['moyenne_classe']; ?>/<?php echo $evaluation['note_max']; ?>
                                    </span>
                                </p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
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

<!-- Formulaire de saisie des notes -->
<form method="POST" id="notes-form">
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">
                <i class="fas fa-list me-2"></i>
                Liste des élèves (<?php echo count($eleves); ?>)
            </h5>
        </div>
        <div class="card-body">
            <?php if (!empty($eleves)): ?>
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead class="table-dark">
                            <tr>
                                <th style="width: 50px;">#</th>
                                <th>Élève</th>
                                <th>Matricule</th>
                                <th style="width: 120px;">Note / <?php echo $evaluation['note_max']; ?></th>
                                <th style="width: 200px;">Observation</th>
                                <th style="width: 100px;">Statut</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($eleves as $index => $eleve): ?>
                                <tr>
                                    <td><?php echo $index + 1; ?></td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($eleve['nom'] . ' ' . $eleve['prenom']); ?></strong>
                                    </td>
                                    <td>
                                        <small class="text-muted"><?php echo htmlspecialchars($eleve['numero_matricule']); ?></small>
                                    </td>
                                    <td>
                                        <input type="number" 
                                               class="form-control form-control-sm note-input" 
                                               name="notes[<?php echo $eleve['id']; ?>]" 
                                               value="<?php echo $eleve['note'] !== null ? $eleve['note'] : ''; ?>"
                                               min="0" 
                                               max="<?php echo $evaluation['note_max']; ?>" 
                                               step="0.25"
                                               placeholder="0-<?php echo $evaluation['note_max']; ?>"
                                               data-eleve-id="<?php echo $eleve['id']; ?>">
                                    </td>
                                    <td>
                                        <input type="text" 
                                               class="form-control form-control-sm" 
                                               name="observations[<?php echo $eleve['id']; ?>]" 
                                               value="<?php echo htmlspecialchars($eleve['observation'] ?? ''); ?>"
                                               placeholder="Observation..."
                                               maxlength="255">
                                    </td>
                                    <td class="text-center">
                                        <?php if ($eleve['note'] !== null): ?>
                                            <span class="badge bg-<?php 
                                                echo $eleve['note'] >= 14 ? 'success' : 
                                                    ($eleve['note'] >= 10 ? 'warning' : 'danger'); 
                                            ?>">
                                                <?php 
                                                if ($eleve['note'] >= 16) echo 'Excellent';
                                                elseif ($eleve['note'] >= 14) echo 'Très bien';
                                                elseif ($eleve['note'] >= 12) echo 'Bien';
                                                elseif ($eleve['note'] >= 10) echo 'Satisfaisant';
                                                elseif ($eleve['note'] >= 8) echo 'Passable';
                                                else echo 'Insuffisant';
                                                ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">Non noté</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Boutons d'action -->
                <div class="row mt-4">
                    <div class="col-12">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <small class="text-muted">
                                    <i class="fas fa-info-circle me-1"></i>
                                    Les notes sont automatiquement sauvegardées. Utilisez des décimales avec un point (ex: 15.5).
                                </small>
                            </div>
                            <div>
                                <button type="button" class="btn btn-outline-secondary me-2" onclick="validateAllNotes()">
                                    <i class="fas fa-check-double me-1"></i>
                                    Valider toutes les notes
                                </button>
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save me-1"></i>
                                    Enregistrer les notes
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <div class="text-center py-5">
                    <i class="fas fa-users fa-3x text-muted mb-3"></i>
                    <h5 class="text-muted">Aucun élève trouvé</h5>
                    <p class="text-muted">Aucun élève n'est inscrit dans cette classe.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</form>

<!-- Statistiques en temps réel -->
<div class="row mt-4">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-chart-bar me-2"></i>
                    Statistiques en temps réel
                </h5>
            </div>
            <div class="card-body">
                <div id="live-stats">
                    <div class="row text-center">
                        <div class="col-4">
                            <h4 id="stat-notes-saisies" class="text-primary"><?php echo $stats['notes_saisies']; ?></h4>
                            <small class="text-muted">Notes saisies</small>
                        </div>
                        <div class="col-4">
                            <h4 id="stat-moyenne" class="text-success"><?php echo $stats['moyenne_classe'] ?: '-'; ?></h4>
                            <small class="text-muted">Moyenne</small>
                        </div>
                        <div class="col-4">
                            <h4 id="stat-completion" class="text-info">
                                <?php echo $stats['total_eleves'] > 0 ? round(($stats['notes_saisies'] / $stats['total_eleves']) * 100) : 0; ?>%
                            </h4>
                            <small class="text-muted">Complété</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-tools me-2"></i>
                    Outils rapides
                </h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-6 mb-2">
                        <div class="d-grid">
                            <button type="button" class="btn btn-outline-primary btn-sm" onclick="fillRandomNotes()">
                                <i class="fas fa-random me-1"></i>
                                Notes aléatoires
                            </button>
                        </div>
                    </div>
                    <div class="col-6 mb-2">
                        <div class="d-grid">
                            <button type="button" class="btn btn-outline-warning btn-sm" onclick="fillAverageNotes()">
                                <i class="fas fa-equals me-1"></i>
                                Notes moyennes
                            </button>
                        </div>
                    </div>
                    <div class="col-6 mb-2">
                        <div class="d-grid">
                            <button type="button" class="btn btn-outline-success btn-sm" onclick="copyFromClipboard()">
                                <i class="fas fa-paste me-1"></i>
                                Coller notes
                            </button>
                        </div>
                    </div>
                    <div class="col-6 mb-2">
                        <div class="d-grid">
                            <button type="button" class="btn btn-outline-danger btn-sm" onclick="clearAllNotes()">
                                <i class="fas fa-eraser me-1"></i>
                                Effacer tout
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
const noteMax = <?php echo $evaluation['note_max']; ?>;
const totalEleves = <?php echo count($eleves); ?>;

// Mise à jour des statistiques en temps réel
function updateLiveStats() {
    const noteInputs = document.querySelectorAll('.note-input');
    let notesSaisies = 0;
    let totalNotes = 0;
    let notesValides = [];
    
    noteInputs.forEach(input => {
        const value = parseFloat(input.value);
        if (!isNaN(value) && value >= 0) {
            notesSaisies++;
            totalNotes += value;
            notesValides.push(value);
        }
    });
    
    const moyenne = notesValides.length > 0 ? (totalNotes / notesValides.length).toFixed(2) : '-';
    const completion = totalEleves > 0 ? Math.round((notesSaisies / totalEleves) * 100) : 0;
    
    document.getElementById('stat-notes-saisies').textContent = notesSaisies;
    document.getElementById('stat-moyenne').textContent = moyenne;
    document.getElementById('stat-completion').textContent = completion + '%';
}

// Écouter les changements sur les champs de notes
document.querySelectorAll('.note-input').forEach(input => {
    input.addEventListener('input', function() {
        updateLiveStats();
        
        // Validation en temps réel
        const value = parseFloat(this.value);
        if (isNaN(value) || value < 0 || value > noteMax) {
            this.classList.add('is-invalid');
        } else {
            this.classList.remove('is-invalid');
        }
    });
});

// Fonctions utilitaires
function fillAllNotes() {
    const note = prompt(`Entrez la note à attribuer à tous les élèves (0-${noteMax}):`);
    if (note !== null) {
        const noteValue = parseFloat(note);
        if (!isNaN(noteValue) && noteValue >= 0 && noteValue <= noteMax) {
            document.querySelectorAll('.note-input').forEach(input => {
                input.value = noteValue;
            });
            updateLiveStats();
        } else {
            alert('Note invalide !');
        }
    }
}

function fillRandomNotes() {
    if (confirm('Générer des notes aléatoires pour tous les élèves ?')) {
        document.querySelectorAll('.note-input').forEach(input => {
            const randomNote = (Math.random() * noteMax).toFixed(2);
            input.value = randomNote;
        });
        updateLiveStats();
    }
}

function fillAverageNotes() {
    const moyenne = noteMax * 0.6; // 60% de la note max
    if (confirm(`Attribuer la note moyenne (${moyenne.toFixed(2)}) à tous les élèves ?`)) {
        document.querySelectorAll('.note-input').forEach(input => {
            input.value = moyenne.toFixed(2);
        });
        updateLiveStats();
    }
}

function clearAllNotes() {
    if (confirm('Effacer toutes les notes saisies ?')) {
        document.querySelectorAll('.note-input').forEach(input => {
            input.value = '';
        });
        updateLiveStats();
    }
}

function validateAllNotes() {
    let hasErrors = false;
    document.querySelectorAll('.note-input').forEach(input => {
        const value = parseFloat(input.value);
        if (input.value !== '' && (isNaN(value) || value < 0 || value > noteMax)) {
            input.classList.add('is-invalid');
            hasErrors = true;
        } else {
            input.classList.remove('is-invalid');
        }
    });
    
    if (hasErrors) {
        alert('Certaines notes sont invalides. Veuillez les corriger.');
    } else {
        alert('Toutes les notes sont valides !');
    }
}

// Initialiser les statistiques
updateLiveStats();

// Validation du formulaire
document.getElementById('notes-form').addEventListener('submit', function(e) {
    let hasInvalidNotes = false;
    
    document.querySelectorAll('.note-input').forEach(input => {
        const value = parseFloat(input.value);
        if (input.value !== '' && (isNaN(value) || value < 0 || value > noteMax)) {
            input.classList.add('is-invalid');
            hasInvalidNotes = true;
        }
    });
    
    if (hasInvalidNotes) {
        e.preventDefault();
        alert('Certaines notes sont invalides. Veuillez les corriger avant d\'enregistrer.');
    }
});
</script>

<?php include '../../../includes/footer.php'; ?>
