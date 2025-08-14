<?php
/**
 * Module d'évaluations - Rapport par période
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

// Récupérer les paramètres
$periode = sanitizeInput($_GET['periode'] ?? '');
$type_rapport = sanitizeInput($_GET['type'] ?? 'global');
$format = sanitizeInput($_GET['format'] ?? 'view');

if (!$periode) {
    showMessage('error', 'Période manquante.');
    redirectTo('reports.php');
}

// Récupérer l'année scolaire active
$current_year = $database->query("SELECT * FROM annees_scolaires WHERE status = 'active' LIMIT 1")->fetch();
if (!$current_year) {
    showMessage('error', 'Aucune année scolaire active trouvée.');
    redirectTo('../../../index.php');
}

// Récupérer les statistiques générales pour la période
$stats_generales = $database->query(
    "SELECT 
        COUNT(DISTINCT e.id) as nb_evaluations,
        COUNT(DISTINCT e.classe_id) as nb_classes,
        COUNT(DISTINCT e.matiere_id) as nb_matieres,
        COUNT(n.id) as nb_notes,
        AVG(n.note) as moyenne_generale,
        MIN(n.note) as note_min,
        MAX(n.note) as note_max
     FROM evaluations e
     LEFT JOIN notes n ON e.id = n.evaluation_id
     WHERE e.annee_scolaire_id = ? AND e.periode = ?",
    [$current_year['id'], $periode]
)->fetch();

// Récupérer les statistiques par classe
$stats_classes = $database->query(
    "SELECT c.nom as classe_nom, c.niveau,
            COUNT(DISTINCT e.id) as nb_evaluations,
            COUNT(n.id) as nb_notes,
            AVG(n.note) as moyenne_classe,
            MIN(n.note) as note_min,
            MAX(n.note) as note_max
     FROM classes c
     LEFT JOIN evaluations e ON (c.id = e.classe_id AND e.annee_scolaire_id = ? AND e.periode = ?)
     LEFT JOIN notes n ON e.id = n.evaluation_id
     WHERE c.annee_scolaire_id = ?
     GROUP BY c.id, c.nom, c.niveau
     HAVING nb_evaluations > 0
     ORDER BY moyenne_classe DESC",
    [$current_year['id'], $periode, $current_year['id']]
)->fetchAll();

// Récupérer les statistiques par matière
$stats_matieres = $database->query(
    "SELECT m.nom as matiere_nom, m.coefficient,
            COUNT(DISTINCT e.id) as nb_evaluations,
            COUNT(n.id) as nb_notes,
            AVG(n.note) as moyenne_matiere,
            MIN(n.note) as note_min,
            MAX(n.note) as note_max
     FROM matieres m
     LEFT JOIN evaluations e ON (m.id = e.matiere_id AND e.annee_scolaire_id = ? AND e.periode = ?)
     LEFT JOIN notes n ON e.id = n.evaluation_id
     GROUP BY m.id, m.nom, m.coefficient
     HAVING nb_evaluations > 0
     ORDER BY moyenne_matiere DESC",
    [$current_year['id'], $periode]
)->fetchAll();

// Gestion des formats
if ($format === 'excel') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="rapport_periode_' . $periode . '.csv"');
    
    $output = fopen('php://output', 'w');
    
    // En-têtes
    fputcsv($output, ['Rapport de période - ' . str_replace('_', ' ', ucfirst($periode))]);
    fputcsv($output, ['Année scolaire', $current_year['annee']]);
    fputcsv($output, ['']);
    
    // Statistiques générales
    fputcsv($output, ['STATISTIQUES GÉNÉRALES']);
    fputcsv($output, ['Évaluations', $stats_generales['nb_evaluations']]);
    fputcsv($output, ['Classes concernées', $stats_generales['nb_classes']]);
    fputcsv($output, ['Matières concernées', $stats_generales['nb_matieres']]);
    fputcsv($output, ['Notes saisies', $stats_generales['nb_notes']]);
    fputcsv($output, ['Moyenne générale', round($stats_generales['moyenne_generale'], 2)]);
    fputcsv($output, ['']);
    
    // Statistiques par classe
    fputcsv($output, ['STATISTIQUES PAR CLASSE']);
    fputcsv($output, ['Classe', 'Niveau', 'Évaluations', 'Notes', 'Moyenne', 'Min', 'Max']);
    foreach ($stats_classes as $classe) {
        fputcsv($output, [
            $classe['classe_nom'],
            ucfirst($classe['niveau']),
            $classe['nb_evaluations'],
            $classe['nb_notes'],
            round($classe['moyenne_classe'], 2),
            $classe['note_min'],
            $classe['note_max']
        ]);
    }
    fputcsv($output, ['']);
    
    // Statistiques par matière
    fputcsv($output, ['STATISTIQUES PAR MATIÈRE']);
    fputcsv($output, ['Matière', 'Coefficient', 'Évaluations', 'Notes', 'Moyenne', 'Min', 'Max']);
    foreach ($stats_matieres as $matiere) {
        fputcsv($output, [
            $matiere['matiere_nom'],
            $matiere['coefficient'],
            $matiere['nb_evaluations'],
            $matiere['nb_notes'],
            round($matiere['moyenne_matiere'], 2),
            $matiere['note_min'],
            $matiere['note_max']
        ]);
    }
    
    fclose($output);
    exit;
}

$page_title = 'Rapport de période : ' . str_replace('_', ' ', ucfirst($periode));

include '../../../includes/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">
        <i class="fas fa-calendar me-2"></i>
        Rapport de période
    </h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <div class="btn-group me-2">
            <a href="reports.php" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-1"></i>
                Retour
            </a>
        </div>
        <div class="btn-group">
            <a href="?periode=<?php echo $periode; ?>&type=<?php echo $type_rapport; ?>&format=excel" 
               class="btn btn-success">
                <i class="fas fa-file-excel me-1"></i>
                Excel
            </a>
        </div>
    </div>
</div>

<!-- En-tête -->
<div class="card mb-4">
    <div class="card-body">
        <div class="text-center">
            <h2><?php echo str_replace('_', ' ', ucfirst($periode)); ?></h2>
            <h4 class="text-muted">Rapport de performance</h4>
            <p class="text-muted">Année scolaire <?php echo $current_year['annee']; ?></p>
        </div>
    </div>
</div>

<!-- Statistiques générales -->
<div class="row mb-4">
    <div class="col-lg-2 col-md-4 mb-3">
        <div class="card bg-primary text-white">
            <div class="card-body text-center">
                <h3><?php echo $stats_generales['nb_evaluations']; ?></h3>
                <p class="mb-0">Évaluations</p>
            </div>
        </div>
    </div>
    <div class="col-lg-2 col-md-4 mb-3">
        <div class="card bg-success text-white">
            <div class="card-body text-center">
                <h3><?php echo $stats_generales['nb_classes']; ?></h3>
                <p class="mb-0">Classes</p>
            </div>
        </div>
    </div>
    <div class="col-lg-2 col-md-4 mb-3">
        <div class="card bg-info text-white">
            <div class="card-body text-center">
                <h3><?php echo $stats_generales['nb_matieres']; ?></h3>
                <p class="mb-0">Matières</p>
            </div>
        </div>
    </div>
    <div class="col-lg-2 col-md-4 mb-3">
        <div class="card bg-warning text-white">
            <div class="card-body text-center">
                <h3><?php echo $stats_generales['nb_notes']; ?></h3>
                <p class="mb-0">Notes</p>
            </div>
        </div>
    </div>
    <div class="col-lg-2 col-md-4 mb-3">
        <div class="card bg-secondary text-white">
            <div class="card-body text-center">
                <h3><?php echo round($stats_generales['moyenne_generale'], 2); ?></h3>
                <p class="mb-0">Moyenne</p>
            </div>
        </div>
    </div>
    <div class="col-lg-2 col-md-4 mb-3">
        <div class="card bg-dark text-white">
            <div class="card-body text-center">
                <h3><?php echo $stats_generales['note_min']; ?>-<?php echo $stats_generales['note_max']; ?></h3>
                <p class="mb-0">Min-Max</p>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <!-- Statistiques par classe -->
    <div class="col-lg-6 mb-4">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-school me-2"></i>
                    Performance par classe
                </h5>
            </div>
            <div class="card-body">
                <?php if (!empty($stats_classes)): ?>
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Classe</th>
                                    <th>Éval.</th>
                                    <th>Notes</th>
                                    <th>Moyenne</th>
                                    <th>Performance</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($stats_classes as $classe): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo htmlspecialchars($classe['classe_nom']); ?></strong>
                                            <br>
                                            <small class="text-muted"><?php echo ucfirst($classe['niveau']); ?></small>
                                        </td>
                                        <td class="text-center"><?php echo $classe['nb_evaluations']; ?></td>
                                        <td class="text-center"><?php echo $classe['nb_notes']; ?></td>
                                        <td class="text-center">
                                            <?php if ($classe['moyenne_classe']): ?>
                                                <?php 
                                                $moyenne = round($classe['moyenne_classe'], 2);
                                                $color = $moyenne >= 14 ? 'success' : ($moyenne >= 10 ? 'warning' : 'danger');
                                                ?>
                                                <span class="badge bg-<?php echo $color; ?>">
                                                    <?php echo $moyenne; ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-center">
                                            <?php if ($classe['moyenne_classe']): ?>
                                                <?php 
                                                $moyenne = round($classe['moyenne_classe'], 2);
                                                if ($moyenne >= 16) {
                                                    echo '<i class="fas fa-star text-warning"></i>';
                                                } elseif ($moyenne >= 14) {
                                                    echo '<i class="fas fa-thumbs-up text-success"></i>';
                                                } elseif ($moyenne >= 12) {
                                                    echo '<i class="fas fa-check text-info"></i>';
                                                } elseif ($moyenne >= 10) {
                                                    echo '<i class="fas fa-minus text-warning"></i>';
                                                } else {
                                                    echo '<i class="fas fa-times text-danger"></i>';
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
                    <p class="text-muted">Aucune donnée disponible pour les classes.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Statistiques par matière -->
    <div class="col-lg-6 mb-4">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-book me-2"></i>
                    Performance par matière
                </h5>
            </div>
            <div class="card-body">
                <?php if (!empty($stats_matieres)): ?>
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Matière</th>
                                    <th>Coef.</th>
                                    <th>Éval.</th>
                                    <th>Notes</th>
                                    <th>Moyenne</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($stats_matieres as $matiere): ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($matiere['matiere_nom']); ?></strong></td>
                                        <td class="text-center">
                                            <span class="badge bg-info"><?php echo $matiere['coefficient']; ?></span>
                                        </td>
                                        <td class="text-center"><?php echo $matiere['nb_evaluations']; ?></td>
                                        <td class="text-center"><?php echo $matiere['nb_notes']; ?></td>
                                        <td class="text-center">
                                            <?php if ($matiere['moyenne_matiere']): ?>
                                                <?php 
                                                $moyenne = round($matiere['moyenne_matiere'], 2);
                                                $color = $moyenne >= 14 ? 'success' : ($moyenne >= 10 ? 'warning' : 'danger');
                                                ?>
                                                <span class="badge bg-<?php echo $color; ?>">
                                                    <?php echo $moyenne; ?>
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
                    <p class="text-muted">Aucune donnée disponible pour les matières.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Analyse détaillée -->
<div class="card">
    <div class="card-header">
        <h5 class="mb-0">
            <i class="fas fa-chart-line me-2"></i>
            Analyse détaillée de la période
        </h5>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-lg-8">
                <h6>Répartition des performances par classe :</h6>
                <?php
                $performance_distribution = [
                    'excellent' => 0,
                    'tres_bien' => 0,
                    'bien' => 0,
                    'passable' => 0,
                    'insuffisant' => 0
                ];
                
                foreach ($stats_classes as $classe) {
                    if ($classe['moyenne_classe']) {
                        $moyenne = round($classe['moyenne_classe'], 2);
                        if ($moyenne >= 16) $performance_distribution['excellent']++;
                        elseif ($moyenne >= 14) $performance_distribution['tres_bien']++;
                        elseif ($moyenne >= 12) $performance_distribution['bien']++;
                        elseif ($moyenne >= 10) $performance_distribution['passable']++;
                        else $performance_distribution['insuffisant']++;
                    }
                }
                
                $total_classes = count($stats_classes);
                ?>
                
                <div class="mb-3">
                    <div class="d-flex justify-content-between mb-1">
                        <span>Excellent (≥16)</span>
                        <span><?php echo $performance_distribution['excellent']; ?> classe(s)</span>
                    </div>
                    <div class="progress mb-2">
                        <div class="progress-bar bg-success" 
                             style="width: <?php echo $total_classes > 0 ? ($performance_distribution['excellent'] / $total_classes) * 100 : 0; ?>%"></div>
                    </div>
                </div>
                
                <div class="mb-3">
                    <div class="d-flex justify-content-between mb-1">
                        <span>Très bien (14-16)</span>
                        <span><?php echo $performance_distribution['tres_bien']; ?> classe(s)</span>
                    </div>
                    <div class="progress mb-2">
                        <div class="progress-bar bg-info" 
                             style="width: <?php echo $total_classes > 0 ? ($performance_distribution['tres_bien'] / $total_classes) * 100 : 0; ?>%"></div>
                    </div>
                </div>
                
                <div class="mb-3">
                    <div class="d-flex justify-content-between mb-1">
                        <span>Bien (12-14)</span>
                        <span><?php echo $performance_distribution['bien']; ?> classe(s)</span>
                    </div>
                    <div class="progress mb-2">
                        <div class="progress-bar bg-primary" 
                             style="width: <?php echo $total_classes > 0 ? ($performance_distribution['bien'] / $total_classes) * 100 : 0; ?>%"></div>
                    </div>
                </div>
                
                <div class="mb-3">
                    <div class="d-flex justify-content-between mb-1">
                        <span>Passable (10-12)</span>
                        <span><?php echo $performance_distribution['passable']; ?> classe(s)</span>
                    </div>
                    <div class="progress mb-2">
                        <div class="progress-bar bg-warning" 
                             style="width: <?php echo $total_classes > 0 ? ($performance_distribution['passable'] / $total_classes) * 100 : 0; ?>%"></div>
                    </div>
                </div>
                
                <div class="mb-3">
                    <div class="d-flex justify-content-between mb-1">
                        <span>Insuffisant (<10)</span>
                        <span><?php echo $performance_distribution['insuffisant']; ?> classe(s)</span>
                    </div>
                    <div class="progress mb-2">
                        <div class="progress-bar bg-danger" 
                             style="width: <?php echo $total_classes > 0 ? ($performance_distribution['insuffisant'] / $total_classes) * 100 : 0; ?>%"></div>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-4">
                <h6>Résumé :</h6>
                <ul class="list-unstyled">
                    <li><strong>Période :</strong> <?php echo str_replace('_', ' ', ucfirst($periode)); ?></li>
                    <li><strong>Classes évaluées :</strong> <?php echo count($stats_classes); ?></li>
                    <li><strong>Matières concernées :</strong> <?php echo count($stats_matieres); ?></li>
                    <li><strong>Total évaluations :</strong> <?php echo $stats_generales['nb_evaluations']; ?></li>
                    <li><strong>Total notes :</strong> <?php echo $stats_generales['nb_notes']; ?></li>
                </ul>
                
                <hr>
                
                <h6>Actions :</h6>
                <div class="d-grid gap-2">
                    <a href="?periode=<?php echo $periode; ?>&type=<?php echo $type_rapport; ?>&format=excel" 
                       class="btn btn-success btn-sm">
                        <i class="fas fa-file-excel me-1"></i>
                        Export Excel
                    </a>
                    <a href="statistics.php" class="btn btn-info btn-sm">
                        <i class="fas fa-chart-bar me-1"></i>
                        Statistiques détaillées
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../../../includes/footer.php'; ?>
