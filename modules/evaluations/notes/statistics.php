<?php
/**
 * Module d'évaluations - Statistiques des notes
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

$page_title = 'Statistiques des notes';

// Récupérer l'année scolaire active
$current_year = $database->query("SELECT * FROM annees_scolaires WHERE status = 'active' LIMIT 1")->fetch();
if (!$current_year) {
    showMessage('error', 'Aucune année scolaire active trouvée.');
    redirectTo('../../../index.php');
}

// Paramètres de filtrage
$evaluation_id = (int)($_GET['evaluation_id'] ?? 0);
$classe_filter = (int)($_GET['classe_id'] ?? 0);
$matiere_filter = (int)($_GET['matiere_id'] ?? 0);
$periode_filter = sanitizeInput($_GET['periode'] ?? '');

// Récupérer les listes pour les filtres
$classes = $database->query(
    "SELECT * FROM classes WHERE annee_scolaire_id = ? ORDER BY niveau, nom",
    [$current_year['id']]
)->fetchAll();

$matieres = $database->query(
    "SELECT * FROM matieres ORDER BY niveau, nom"
)->fetchAll();

$evaluations = $database->query(
    "SELECT e.id, e.nom, m.nom as matiere_nom, c.nom as classe_nom
     FROM evaluations e
     JOIN matieres m ON e.matiere_id = m.id
     JOIN classes c ON e.classe_id = c.id
     WHERE e.annee_scolaire_id = ?
     ORDER BY e.date_evaluation DESC",
    [$current_year['id']]
)->fetchAll();

// Statistiques générales
$stats_generales = $database->query(
    "SELECT 
        COUNT(DISTINCT e.id) as total_evaluations,
        COUNT(n.id) as total_notes,
        AVG(n.note) as moyenne_generale,
        MIN(n.note) as note_min_globale,
        MAX(n.note) as note_max_globale,
        COUNT(DISTINCT n.eleve_id) as eleves_evalues
     FROM evaluations e
     LEFT JOIN notes n ON e.id = n.evaluation_id
     WHERE e.annee_scolaire_id = ?",
    [$current_year['id']]
)->fetch();

// Statistiques par période
$stats_periodes = $database->query(
    "SELECT 
        e.periode,
        COUNT(DISTINCT e.id) as nb_evaluations,
        COUNT(n.id) as nb_notes,
        AVG(n.note) as moyenne_periode,
        MIN(n.note) as note_min,
        MAX(n.note) as note_max
     FROM evaluations e
     LEFT JOIN notes n ON e.id = n.evaluation_id
     WHERE e.annee_scolaire_id = ?
     GROUP BY e.periode
     ORDER BY e.periode",
    [$current_year['id']]
)->fetchAll();

// Statistiques par matière
$stats_matieres = $database->query(
    "SELECT
        m.nom as matiere_nom,
        m.coefficient,
        COUNT(DISTINCT e.id) as nb_evaluations,
        COUNT(n.id) as nb_notes,
        AVG(n.note) as moyenne_matiere,
        MIN(n.note) as note_min,
        MAX(n.note) as note_max
     FROM matieres m
     LEFT JOIN evaluations e ON m.id = e.matiere_id AND e.annee_scolaire_id = ?
     LEFT JOIN notes n ON e.id = n.evaluation_id
     GROUP BY m.id, m.nom, m.coefficient
     HAVING nb_evaluations > 0
     ORDER BY moyenne_matiere DESC",
    [$current_year['id']]
)->fetchAll();

// Statistiques par classe
$stats_classes = $database->query(
    "SELECT
        c.nom as classe_nom,
        c.niveau,
        COUNT(DISTINCT e.id) as nb_evaluations,
        COUNT(n.id) as nb_notes,
        AVG(n.note) as moyenne_classe,
        MIN(n.note) as note_min,
        MAX(n.note) as note_max,
        COUNT(DISTINCT i.eleve_id) as nb_eleves_inscrits
     FROM classes c
     LEFT JOIN evaluations e ON c.id = e.classe_id AND e.annee_scolaire_id = ?
     LEFT JOIN notes n ON e.id = n.evaluation_id
     LEFT JOIN inscriptions i ON (c.id = i.classe_id AND i.annee_scolaire_id = ? AND i.status = 'inscrit')
     WHERE c.annee_scolaire_id = ?
     GROUP BY c.id, c.nom, c.niveau
     HAVING nb_evaluations > 0
     ORDER BY moyenne_classe DESC",
    [$current_year['id'], $current_year['id'], $current_year['id']]
)->fetchAll();

// Distribution des notes (par tranches)
$distribution_notes = $database->query(
    "SELECT 
        CASE 
            WHEN n.note >= 16 THEN 'Très bien (16-20)'
            WHEN n.note >= 14 THEN 'Bien (14-16)'
            WHEN n.note >= 12 THEN 'Assez bien (12-14)'
            WHEN n.note >= 10 THEN 'Passable (10-12)'
            ELSE 'Insuffisant (0-10)'
        END as tranche,
        COUNT(*) as nombre,
        ROUND((COUNT(*) * 100.0 / (SELECT COUNT(*) FROM notes n2 JOIN evaluations e2 ON n2.evaluation_id = e2.id WHERE e2.annee_scolaire_id = ?)), 2) as pourcentage
     FROM notes n
     JOIN evaluations e ON n.evaluation_id = e.id
     WHERE e.annee_scolaire_id = ?
     GROUP BY tranche
     ORDER BY MIN(n.note) DESC",
    [$current_year['id'], $current_year['id']]
)->fetchAll();

// Top 10 des meilleures moyennes par élève
$top_eleves = $database->query(
    "SELECT
        el.nom,
        el.prenom,
        el.numero_matricule,
        c.nom as classe_nom,
        COUNT(n.id) as nb_notes,
        AVG(n.note) as moyenne_eleve,
        MIN(n.note) as note_min,
        MAX(n.note) as note_max
     FROM eleves el
     JOIN inscriptions i ON el.id = i.eleve_id
     JOIN classes c ON i.classe_id = c.id
     JOIN notes n ON el.id = n.eleve_id
     JOIN evaluations e ON n.evaluation_id = e.id
     WHERE i.annee_scolaire_id = ? AND i.status = 'inscrit' AND e.annee_scolaire_id = ?
     GROUP BY el.id, el.nom, el.prenom, el.numero_matricule, c.nom
     HAVING nb_notes >= 3
     ORDER BY moyenne_eleve DESC
     LIMIT 10",
    [$current_year['id'], $current_year['id']]
)->fetchAll();

include '../../../includes/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">
        <i class="fas fa-chart-bar me-2"></i>
        Statistiques des notes
    </h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <div class="btn-group me-2">
            <a href="index.php" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-1"></i>
                Retour aux notes
            </a>
            <a href="../evaluations/" class="btn btn-outline-info">
                <i class="fas fa-clipboard-check me-1"></i>
                Évaluations
            </a>
            <a href="../statistics/class-ranking.php" class="btn btn-outline-success">
                <i class="fas fa-trophy me-1"></i>
                Classement
            </a>
        </div>
    </div>
</div>

<!-- Statistiques générales -->
<div class="row mb-4">
    <div class="col-lg-3 col-md-6 mb-3">
        <div class="card bg-primary text-white">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h4 class="mb-0"><?php echo $stats_generales['total_evaluations']; ?></h4>
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
                        <h4 class="mb-0"><?php echo $stats_generales['total_notes']; ?></h4>
                        <p class="mb-0">Notes saisies</p>
                    </div>
                    <div class="align-self-center">
                        <i class="fas fa-edit fa-2x"></i>
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
                        <h4 class="mb-0"><?php echo round($stats_generales['moyenne_generale'], 2); ?>/20</h4>
                        <p class="mb-0">Moyenne générale</p>
                    </div>
                    <div class="align-self-center">
                        <i class="fas fa-chart-line fa-2x"></i>
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
                        <h4 class="mb-0"><?php echo $stats_generales['eleves_evalues']; ?></h4>
                        <p class="mb-0">Élèves évalués</p>
                    </div>
                    <div class="align-self-center">
                        <i class="fas fa-users fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <!-- Distribution des notes -->
    <div class="col-lg-6 mb-4">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-chart-pie me-2"></i>
                    Distribution des notes
                </h5>
            </div>
            <div class="card-body">
                <?php if (!empty($distribution_notes)): ?>
                    <?php foreach ($distribution_notes as $tranche): ?>
                        <div class="mb-3">
                            <div class="d-flex justify-content-between mb-1">
                                <span><?php echo $tranche['tranche']; ?></span>
                                <span><?php echo $tranche['nombre']; ?> notes (<?php echo $tranche['pourcentage']; ?>%)</span>
                            </div>
                            <div class="progress">
                                <?php
                                $color = 'secondary';
                                if (strpos($tranche['tranche'], 'Très bien') !== false) $color = 'success';
                                elseif (strpos($tranche['tranche'], 'Bien') !== false) $color = 'info';
                                elseif (strpos($tranche['tranche'], 'Assez bien') !== false) $color = 'primary';
                                elseif (strpos($tranche['tranche'], 'Passable') !== false) $color = 'warning';
                                elseif (strpos($tranche['tranche'], 'Insuffisant') !== false) $color = 'danger';
                                ?>
                                <div class="progress-bar bg-<?php echo $color; ?>" 
                                     style="width: <?php echo $tranche['pourcentage']; ?>%"></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p class="text-muted">Aucune donnée disponible</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Statistiques par période -->
    <div class="col-lg-6 mb-4">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-calendar me-2"></i>
                    Statistiques par période
                </h5>
            </div>
            <div class="card-body">
                <?php if (!empty($stats_periodes)): ?>
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Période</th>
                                    <th>Évaluations</th>
                                    <th>Notes</th>
                                    <th>Moyenne</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($stats_periodes as $periode): ?>
                                    <tr>
                                        <td>
                                            <span class="badge bg-primary">
                                                <?php echo str_replace('_', ' ', ucfirst($periode['periode'])); ?>
                                            </span>
                                        </td>
                                        <td><?php echo $periode['nb_evaluations']; ?></td>
                                        <td><?php echo $periode['nb_notes']; ?></td>
                                        <td>
                                            <?php if ($periode['moyenne_periode']): ?>
                                                <span class="badge bg-success">
                                                    <?php echo round($periode['moyenne_periode'], 2); ?>/20
                                                </span>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p class="text-muted">Aucune donnée disponible</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <!-- Statistiques par matière -->
    <div class="col-lg-8 mb-4">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-book me-2"></i>
                    Statistiques par matière
                </h5>
            </div>
            <div class="card-body">
                <?php if (!empty($stats_matieres)): ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Matière</th>
                                    <th>Coef.</th>
                                    <th>Évaluations</th>
                                    <th>Notes</th>
                                    <th>Moyenne</th>
                                    <th>Min/Max</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($stats_matieres as $matiere): ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($matiere['matiere_nom']); ?></strong></td>
                                        <td>
                                            <span class="badge bg-info"><?php echo $matiere['coefficient']; ?></span>
                                        </td>
                                        <td><?php echo $matiere['nb_evaluations']; ?></td>
                                        <td><?php echo $matiere['nb_notes']; ?></td>
                                        <td>
                                            <?php if ($matiere['moyenne_matiere']): ?>
                                                <?php 
                                                $moyenne = round($matiere['moyenne_matiere'], 2);
                                                $color = $moyenne >= 14 ? 'success' : ($moyenne >= 10 ? 'warning' : 'danger');
                                                ?>
                                                <span class="badge bg-<?php echo $color; ?>">
                                                    <?php echo $moyenne; ?>/20
                                                </span>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($matiere['note_min'] && $matiere['note_max']): ?>
                                                <small class="text-muted">
                                                    <?php echo $matiere['note_min']; ?> - <?php echo $matiere['note_max']; ?>
                                                </small>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p class="text-muted">Aucune donnée disponible</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Top 10 des élèves -->
    <div class="col-lg-4 mb-4">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-trophy me-2"></i>
                    Top 10 des élèves
                </h5>
            </div>
            <div class="card-body">
                <?php if (!empty($top_eleves)): ?>
                    <?php foreach ($top_eleves as $index => $eleve): ?>
                        <div class="d-flex justify-content-between align-items-center mb-2 p-2 <?php echo $index < 3 ? 'bg-light rounded' : ''; ?>">
                            <div>
                                <div class="d-flex align-items-center">
                                    <?php if ($index < 3): ?>
                                        <span class="badge bg-warning me-2"><?php echo $index + 1; ?></span>
                                    <?php else: ?>
                                        <span class="text-muted me-2"><?php echo $index + 1; ?>.</span>
                                    <?php endif; ?>
                                    <div>
                                        <strong><?php echo htmlspecialchars($eleve['nom'] . ' ' . $eleve['prenom']); ?></strong>
                                        <br>
                                        <small class="text-muted">
                                            <?php echo htmlspecialchars($eleve['classe_nom']); ?> • 
                                            <?php echo $eleve['nb_notes']; ?> notes
                                        </small>
                                    </div>
                                </div>
                            </div>
                            <div class="text-end">
                                <?php 
                                $moyenne = round($eleve['moyenne_eleve'], 2);
                                $color = $moyenne >= 16 ? 'success' : ($moyenne >= 14 ? 'info' : ($moyenne >= 12 ? 'primary' : ($moyenne >= 10 ? 'warning' : 'danger')));
                                ?>
                                <span class="badge bg-<?php echo $color; ?>">
                                    <?php echo $moyenne; ?>/20
                                </span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p class="text-muted">Aucune donnée disponible</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Statistiques par classe -->
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-school me-2"></i>
                    Statistiques par classe
                </h5>
            </div>
            <div class="card-body">
                <?php if (!empty($stats_classes)): ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Classe</th>
                                    <th>Niveau</th>
                                    <th>Élèves inscrits</th>
                                    <th>Évaluations</th>
                                    <th>Notes saisies</th>
                                    <th>Moyenne classe</th>
                                    <th>Min/Max</th>
                                    <th>Performance</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($stats_classes as $classe): ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($classe['classe_nom']); ?></strong></td>
                                        <td>
                                            <span class="badge bg-secondary">
                                                <?php echo ucfirst($classe['niveau']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo $classe['nb_eleves_inscrits']; ?></td>
                                        <td><?php echo $classe['nb_evaluations']; ?></td>
                                        <td><?php echo $classe['nb_notes']; ?></td>
                                        <td>
                                            <?php if ($classe['moyenne_classe']): ?>
                                                <?php 
                                                $moyenne = round($classe['moyenne_classe'], 2);
                                                $color = $moyenne >= 14 ? 'success' : ($moyenne >= 10 ? 'warning' : 'danger');
                                                ?>
                                                <span class="badge bg-<?php echo $color; ?>">
                                                    <?php echo $moyenne; ?>/20
                                                </span>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($classe['note_min'] && $classe['note_max']): ?>
                                                <small class="text-muted">
                                                    <?php echo $classe['note_min']; ?> - <?php echo $classe['note_max']; ?>
                                                </small>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($classe['moyenne_classe']): ?>
                                                <?php 
                                                $moyenne = round($classe['moyenne_classe'], 2);
                                                if ($moyenne >= 16) {
                                                    echo '<i class="fas fa-star text-warning" title="Excellent"></i>';
                                                } elseif ($moyenne >= 14) {
                                                    echo '<i class="fas fa-thumbs-up text-success" title="Très bien"></i>';
                                                } elseif ($moyenne >= 12) {
                                                    echo '<i class="fas fa-check text-info" title="Bien"></i>';
                                                } elseif ($moyenne >= 10) {
                                                    echo '<i class="fas fa-minus text-warning" title="Passable"></i>';
                                                } else {
                                                    echo '<i class="fas fa-times text-danger" title="Insuffisant"></i>';
                                                }
                                                ?>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p class="text-muted">Aucune donnée disponible</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php include '../../../includes/footer.php'; ?>
