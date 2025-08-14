<?php
/**
 * Module d'évaluations - Gestion des notes
 * Application de gestion scolaire - République Démocratique du Congo
 */

require_once '../../../config/config.php';
require_once '../../../config/database.php';
require_once '../../../includes/functions.php';

// Vérifier l'authentification et les permissions
requireLogin();
if (!checkPermission('evaluations') && !checkPermission('evaluations_view')) {
    showMessage('error', 'Accès refusé à cette fonctionnalité.');
    redirectTo('index.php');
}

$page_title = 'Gestion des notes';

// Récupérer l'année scolaire active
$current_year = $database->query("SELECT * FROM annees_scolaires WHERE status = 'active' LIMIT 1")->fetch();
if (!$current_year) {
    showMessage('error', 'Aucune année scolaire active trouvée.');
    redirectTo('../../../index.php');
}

// Paramètres de filtrage
$classe_filter = (int)($_GET['classe_id'] ?? 0);
$matiere_filter = (int)($_GET['matiere_id'] ?? 0);
$periode_filter = sanitizeInput($_GET['periode'] ?? '');
$type_filter = sanitizeInput($_GET['type'] ?? '');
$status_filter = sanitizeInput($_GET['status'] ?? '');

// Récupérer les listes pour les filtres
$classes = $database->query(
    "SELECT * FROM classes WHERE annee_scolaire_id = ? ORDER BY niveau, nom",
    [$current_year['id']]
)->fetchAll();

$matieres = $database->query(
    "SELECT * FROM matieres ORDER BY niveau, nom"
)->fetchAll();

// Construire la requête avec filtres
$where_conditions = ["e.annee_scolaire_id = ?"];
$params = [$current_year['id']];

if ($classe_filter) {
    $where_conditions[] = "e.classe_id = ?";
    $params[] = $classe_filter;
}

if ($matiere_filter) {
    $where_conditions[] = "e.matiere_id = ?";
    $params[] = $matiere_filter;
}

if ($periode_filter) {
    $where_conditions[] = "e.periode = ?";
    $params[] = $periode_filter;
}

if ($type_filter) {
    $where_conditions[] = "e.type_evaluation = ?";
    $params[] = $type_filter;
}

if ($status_filter) {
    $where_conditions[] = "e.status = ?";
    $params[] = $status_filter;
}

$where_clause = implode(' AND ', $where_conditions);

// Récupérer les évaluations avec statistiques des notes
$evaluations = $database->query(
    "SELECT e.*,
            m.nom as matiere_nom, m.coefficient as matiere_coefficient,
            c.nom as classe_nom, c.niveau,
            CONCAT(p.nom, ' ', p.prenom) as enseignant_nom,
            COUNT(n.id) as notes_saisies,
            COUNT(DISTINCT i.eleve_id) as total_eleves,
            AVG(n.note) as moyenne_classe,
            MIN(n.note) as note_min,
            MAX(n.note) as note_max
     FROM evaluations e
     JOIN matieres m ON e.matiere_id = m.id
     JOIN classes c ON e.classe_id = c.id
     LEFT JOIN personnel p ON e.enseignant_id = p.id
     LEFT JOIN notes n ON e.id = n.evaluation_id
     LEFT JOIN inscriptions i ON (c.id = i.classe_id AND i.annee_scolaire_id = e.annee_scolaire_id AND i.status = 'inscrit')
     WHERE $where_clause
     GROUP BY e.id, m.nom, m.coefficient, c.nom, c.niveau, p.nom, p.prenom
     ORDER BY e.date_evaluation DESC, e.created_at DESC",
    $params
)->fetchAll();

// Statistiques générales
$stats = [
    'total_evaluations' => count($evaluations),
    'evaluations_avec_notes' => 0,
    'evaluations_sans_notes' => 0,
    'total_notes' => 0,
    'moyenne_generale' => 0
];

$total_notes_sum = 0;
$total_notes_count = 0;

foreach ($evaluations as $eval) {
    if ($eval['notes_saisies'] > 0) {
        $stats['evaluations_avec_notes']++;
        $stats['total_notes'] += $eval['notes_saisies'];
        if ($eval['moyenne_classe']) {
            $total_notes_sum += $eval['moyenne_classe'] * $eval['notes_saisies'];
            $total_notes_count += $eval['notes_saisies'];
        }
    } else {
        $stats['evaluations_sans_notes']++;
    }
}

if ($total_notes_count > 0) {
    $stats['moyenne_generale'] = round($total_notes_sum / $total_notes_count, 2);
}

include '../../../includes/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">
        <i class="fas fa-clipboard-list me-2"></i>
        Gestion des notes
    </h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <div class="btn-group me-2">
            <a href="../evaluations/" class="btn btn-outline-secondary">
                <i class="fas fa-clipboard-check me-1"></i>
                Évaluations
            </a>
            <a href="statistics.php" class="btn btn-outline-info">
                <i class="fas fa-chart-bar me-1"></i>
                Statistiques
            </a>
            <a href="reports.php" class="btn btn-outline-warning">
                <i class="fas fa-file-alt me-1"></i>
                Rapports
            </a>
        </div>
        <?php if (checkPermission('evaluations')): ?>
            <div class="btn-group">
                <a href="../evaluations/add.php" class="btn btn-success">
                    <i class="fas fa-plus me-1"></i>
                    Nouvelle évaluation
                </a>
                <a href="batch-entry.php" class="btn btn-primary">
                    <i class="fas fa-edit me-1"></i>
                    Saisie en lot
                </a>
                <a href="../bulletins/generate.php" class="btn btn-warning">
                    <i class="fas fa-file-alt me-1"></i>
                    Bulletins
                </a>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Statistiques générales -->
<div class="row mb-4">
    <div class="col-lg-3 col-md-6 mb-3">
        <div class="card bg-primary text-white">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h4 class="mb-0"><?php echo $stats['total_evaluations']; ?></h4>
                        <p class="mb-0">Évaluations</p>
                    </div>
                    <div class="align-self-center">
                        <i class="fas fa-clipboard-check fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-3 col-md-6 mb-3">
        <div class="card bg-success text-white">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h4 class="mb-0"><?php echo $stats['evaluations_avec_notes']; ?></h4>
                        <p class="mb-0">Avec notes</p>
                    </div>
                    <div class="align-self-center">
                        <i class="fas fa-check-circle fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-3 col-md-6 mb-3">
        <div class="card bg-warning text-white">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h4 class="mb-0"><?php echo $stats['evaluations_sans_notes']; ?></h4>
                        <p class="mb-0">Sans notes</p>
                    </div>
                    <div class="align-self-center">
                        <i class="fas fa-exclamation-triangle fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-3 col-md-6 mb-3">
        <div class="card bg-info text-white">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h4 class="mb-0"><?php echo $stats['moyenne_generale']; ?>/20</h4>
                        <p class="mb-0">Moyenne générale</p>
                    </div>
                    <div class="align-self-center">
                        <i class="fas fa-chart-line fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Filtres -->
<div class="card mb-4">
    <div class="card-header">
        <h5 class="mb-0">
            <i class="fas fa-filter me-2"></i>
            Filtres
        </h5>
    </div>
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-md-3">
                <label for="classe_id" class="form-label">Classe</label>
                <select class="form-select" id="classe_id" name="classe_id">
                    <option value="">Toutes les classes</option>
                    <?php foreach ($classes as $classe): ?>
                        <option value="<?php echo $classe['id']; ?>" 
                                <?php echo $classe_filter == $classe['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($classe['nom']); ?> 
                            (<?php echo ucfirst($classe['niveau']); ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label for="matiere_id" class="form-label">Matière</label>
                <select class="form-select" id="matiere_id" name="matiere_id">
                    <option value="">Toutes les matières</option>
                    <?php foreach ($matieres as $matiere): ?>
                        <option value="<?php echo $matiere['id']; ?>" 
                                <?php echo $matiere_filter == $matiere['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($matiere['nom']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label for="periode" class="form-label">Période</label>
                <select class="form-select" id="periode" name="periode">
                    <option value="">Toutes les périodes</option>
                    <option value="1er_trimestre" <?php echo $periode_filter === '1er_trimestre' ? 'selected' : ''; ?>>1er Trimestre</option>
                    <option value="2eme_trimestre" <?php echo $periode_filter === '2eme_trimestre' ? 'selected' : ''; ?>>2ème Trimestre</option>
                    <option value="3eme_trimestre" <?php echo $periode_filter === '3eme_trimestre' ? 'selected' : ''; ?>>3ème Trimestre</option>
                    <option value="annuelle" <?php echo $periode_filter === 'annuelle' ? 'selected' : ''; ?>>Annuelle</option>
                </select>
            </div>
            <div class="col-md-2">
                <label for="type" class="form-label">Type</label>
                <select class="form-select" id="type" name="type">
                    <option value="">Tous les types</option>
                    <option value="interrogation" <?php echo $type_filter === 'interrogation' ? 'selected' : ''; ?>>Interrogation</option>
                    <option value="devoir" <?php echo $type_filter === 'devoir' ? 'selected' : ''; ?>>Devoir</option>
                    <option value="examen" <?php echo $type_filter === 'examen' ? 'selected' : ''; ?>>Examen</option>
                    <option value="composition" <?php echo $type_filter === 'composition' ? 'selected' : ''; ?>>Composition</option>
                </select>
            </div>
            <div class="col-md-2">
                <label for="status" class="form-label">Statut</label>
                <select class="form-select" id="status" name="status">
                    <option value="">Tous les statuts</option>
                    <option value="programmee" <?php echo $status_filter === 'programmee' ? 'selected' : ''; ?>>Programmée</option>
                    <option value="en_cours" <?php echo $status_filter === 'en_cours' ? 'selected' : ''; ?>>En cours</option>
                    <option value="terminee" <?php echo $status_filter === 'terminee' ? 'selected' : ''; ?>>Terminée</option>
                    <option value="annulee" <?php echo $status_filter === 'annulee' ? 'selected' : ''; ?>>Annulée</option>
                </select>
            </div>
            <div class="col-12">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-search me-1"></i>
                    Filtrer
                </button>
                <a href="index.php" class="btn btn-outline-secondary">
                    <i class="fas fa-times me-1"></i>
                    Réinitialiser
                </a>
            </div>
        </form>
    </div>
</div>

<!-- Liste des évaluations -->
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">
            <i class="fas fa-list me-2"></i>
            Évaluations et notes (<?php echo count($evaluations); ?>)
        </h5>
        <div class="btn-group btn-group-sm">
            <button type="button" class="btn btn-outline-secondary" id="toggleView" data-view="card">
                <i class="fas fa-table me-1"></i>
                Vue tableau
            </button>
        </div>
    </div>
    <div class="card-body">
        <?php if (!empty($evaluations)): ?>
            <!-- Vue en cartes (par défaut) -->
            <div id="cardView" class="row">
                <?php foreach ($evaluations as $evaluation): ?>
                    <div class="col-lg-6 col-xl-4 mb-4">
                        <div class="card h-100 border-left-primary">
                            <div class="card-header pb-2">
                                <div class="d-flex justify-content-between align-items-start">
                                    <h6 class="mb-1">
                                        <a href="../evaluations/view.php?id=<?php echo $evaluation['id']; ?>" 
                                           class="text-decoration-none">
                                            <?php echo htmlspecialchars($evaluation['nom']); ?>
                                        </a>
                                    </h6>
                                    <?php
                                    $type_colors = [
                                        'interrogation' => 'info',
                                        'devoir' => 'primary',
                                        'examen' => 'warning',
                                        'composition' => 'danger'
                                    ];
                                    $color = $type_colors[$evaluation['type_evaluation']] ?? 'secondary';
                                    ?>
                                    <span class="badge bg-<?php echo $color; ?>">
                                        <?php echo ucfirst($evaluation['type_evaluation']); ?>
                                    </span>
                                </div>
                                <small class="text-muted">
                                    <?php echo htmlspecialchars($evaluation['classe_nom']); ?> • 
                                    <?php echo htmlspecialchars($evaluation['matiere_nom']); ?>
                                </small>
                            </div>
                            <div class="card-body pt-2">
                                <div class="row text-center mb-3">
                                    <div class="col-4">
                                        <div class="border-end">
                                            <h5 class="mb-0 text-primary"><?php echo $evaluation['notes_saisies']; ?></h5>
                                            <small class="text-muted">Notes</small>
                                        </div>
                                    </div>
                                    <div class="col-4">
                                        <div class="border-end">
                                            <h5 class="mb-0 text-info"><?php echo $evaluation['total_eleves']; ?></h5>
                                            <small class="text-muted">Élèves</small>
                                        </div>
                                    </div>
                                    <div class="col-4">
                                        <h5 class="mb-0 text-success">
                                            <?php echo $evaluation['moyenne_classe'] ? round($evaluation['moyenne_classe'], 1) : '-'; ?>
                                        </h5>
                                        <small class="text-muted">Moyenne</small>
                                    </div>
                                </div>
                                
                                <?php if ($evaluation['notes_saisies'] > 0): ?>
                                    <div class="progress mb-2" style="height: 6px;">
                                        <?php 
                                        $progress = $evaluation['total_eleves'] > 0 ? 
                                                   ($evaluation['notes_saisies'] / $evaluation['total_eleves']) * 100 : 0;
                                        $progress_color = $progress >= 100 ? 'success' : ($progress >= 50 ? 'warning' : 'danger');
                                        ?>
                                        <div class="progress-bar bg-<?php echo $progress_color; ?>" 
                                             style="width: <?php echo min(100, $progress); ?>%"></div>
                                    </div>
                                    <small class="text-muted">
                                        <?php echo round($progress, 1); ?>% des notes saisies
                                        <?php if ($evaluation['moyenne_classe']): ?>
                                            • Min: <?php echo $evaluation['note_min']; ?> • Max: <?php echo $evaluation['note_max']; ?>
                                        <?php endif; ?>
                                    </small>
                                <?php else: ?>
                                    <div class="alert alert-warning py-2 mb-2">
                                        <small><i class="fas fa-exclamation-triangle me-1"></i>Aucune note saisie</small>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="mt-3">
                                    <small class="text-muted">
                                        <i class="fas fa-calendar me-1"></i>
                                        <?php echo date('d/m/Y', strtotime($evaluation['date_evaluation'])); ?>
                                        <span class="ms-2">
                                            <i class="fas fa-user me-1"></i>
                                            <?php echo htmlspecialchars($evaluation['enseignant_nom']); ?>
                                        </span>
                                    </small>
                                </div>
                            </div>
                            <div class="card-footer bg-transparent">
                                <div class="btn-group w-100" role="group">
                                    <a href="../evaluations/view.php?id=<?php echo $evaluation['id']; ?>" 
                                       class="btn btn-outline-info btn-sm">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <?php if (checkPermission('evaluations')): ?>
                                        <a href="entry.php?evaluation_id=<?php echo $evaluation['id']; ?>" 
                                           class="btn btn-outline-success btn-sm">
                                            <i class="fas fa-edit"></i>
                                            Notes
                                        </a>
                                        <a href="../evaluations/edit.php?id=<?php echo $evaluation['id']; ?>" 
                                           class="btn btn-outline-primary btn-sm">
                                            <i class="fas fa-pen"></i>
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <!-- Vue en tableau (cachée par défaut) -->
            <div id="tableView" class="d-none">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Évaluation</th>
                                <th>Type</th>
                                <th>Classe</th>
                                <th>Matière</th>
                                <th>Date</th>
                                <th>Notes</th>
                                <th>Moyenne</th>
                                <th>Progression</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($evaluations as $evaluation): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo htmlspecialchars($evaluation['nom']); ?></strong>
                                        <br>
                                        <small class="text-muted"><?php echo htmlspecialchars($evaluation['enseignant_nom']); ?></small>
                                    </td>
                                    <td>
                                        <?php
                                        $type_colors = [
                                            'interrogation' => 'info',
                                            'devoir' => 'primary',
                                            'examen' => 'warning',
                                            'composition' => 'danger'
                                        ];
                                        $color = $type_colors[$evaluation['type_evaluation']] ?? 'secondary';
                                        ?>
                                        <span class="badge bg-<?php echo $color; ?>">
                                            <?php echo ucfirst($evaluation['type_evaluation']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo htmlspecialchars($evaluation['classe_nom']); ?></td>
                                    <td><?php echo htmlspecialchars($evaluation['matiere_nom']); ?></td>
                                    <td><?php echo date('d/m/Y', strtotime($evaluation['date_evaluation'])); ?></td>
                                    <td>
                                        <span class="badge bg-primary">
                                            <?php echo $evaluation['notes_saisies']; ?>/<?php echo $evaluation['total_eleves']; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($evaluation['moyenne_classe']): ?>
                                            <span class="badge bg-success">
                                                <?php echo round($evaluation['moyenne_classe'], 1); ?>/20
                                            </span>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php 
                                        $progress = $evaluation['total_eleves'] > 0 ? 
                                                   ($evaluation['notes_saisies'] / $evaluation['total_eleves']) * 100 : 0;
                                        $progress_color = $progress >= 100 ? 'success' : ($progress >= 50 ? 'warning' : 'danger');
                                        ?>
                                        <div class="progress" style="height: 20px;">
                                            <div class="progress-bar bg-<?php echo $progress_color; ?>" 
                                                 style="width: <?php echo min(100, $progress); ?>%">
                                                <?php echo round($progress, 0); ?>%
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <a href="../evaluations/view.php?id=<?php echo $evaluation['id']; ?>" 
                                               class="btn btn-outline-info" title="Voir">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <?php if (checkPermission('evaluations')): ?>
                                                <a href="entry.php?evaluation_id=<?php echo $evaluation['id']; ?>" 
                                                   class="btn btn-outline-success" title="Saisir notes">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php else: ?>
            <div class="text-center py-5">
                <i class="fas fa-clipboard fa-3x text-muted mb-3"></i>
                <h5 class="text-muted">Aucune évaluation trouvée</h5>
                <p class="text-muted">
                    <?php if ($classe_filter || $matiere_filter || $periode_filter || $type_filter): ?>
                        Aucune évaluation ne correspond aux critères de filtrage sélectionnés.
                        <br>
                        <a href="index.php" class="btn btn-outline-primary mt-2">
                            <i class="fas fa-times me-1"></i>
                            Réinitialiser les filtres
                        </a>
                    <?php else: ?>
                        Commencez par créer des évaluations pour pouvoir saisir des notes.
                        <br>
                        <?php if (checkPermission('evaluations')): ?>
                            <a href="../evaluations/add.php" class="btn btn-primary mt-2">
                                <i class="fas fa-plus me-1"></i>
                                Créer une évaluation
                            </a>
                        <?php endif; ?>
                    <?php endif; ?>
                </p>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Basculer entre vue carte et vue tableau
    const toggleBtn = document.getElementById('toggleView');
    const cardView = document.getElementById('cardView');
    const tableView = document.getElementById('tableView');
    
    if (toggleBtn) {
        toggleBtn.addEventListener('click', function() {
            const currentView = this.getAttribute('data-view');
            
            if (currentView === 'card') {
                // Passer à la vue tableau
                cardView.classList.add('d-none');
                tableView.classList.remove('d-none');
                this.setAttribute('data-view', 'table');
                this.innerHTML = '<i class="fas fa-th me-1"></i>Vue cartes';
            } else {
                // Passer à la vue cartes
                tableView.classList.add('d-none');
                cardView.classList.remove('d-none');
                this.setAttribute('data-view', 'card');
                this.innerHTML = '<i class="fas fa-table me-1"></i>Vue tableau';
            }
        });
    }
    
    // Auto-submit du formulaire de filtrage
    const filterSelects = document.querySelectorAll('#classe_id, #matiere_id, #periode, #type, #status');
    filterSelects.forEach(select => {
        select.addEventListener('change', function() {
            // Optionnel: soumettre automatiquement le formulaire
            // this.form.submit();
        });
    });
});
</script>

<?php include '../../../includes/footer.php'; ?>
