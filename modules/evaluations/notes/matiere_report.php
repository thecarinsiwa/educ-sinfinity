<?php
/**
 * Module d'évaluations - Rapport par matière
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
$matiere_id = (int)($_GET['matiere_id'] ?? 0);
$niveau = sanitizeInput($_GET['niveau'] ?? '');
$type = sanitizeInput($_GET['type'] ?? 'analysis');

if (!$matiere_id) {
    showMessage('error', 'ID de matière manquant.');
    redirectTo('reports.php');
}

// Récupérer l'année scolaire active
$current_year = $database->query("SELECT * FROM annees_scolaires WHERE status = 'active' LIMIT 1")->fetch();
if (!$current_year) {
    showMessage('error', 'Aucune année scolaire active trouvée.');
    redirectTo('../../../index.php');
}

// Récupérer les détails de la matière
$matiere = $database->query(
    "SELECT * FROM matieres WHERE id = ?",
    [$matiere_id]
)->fetch();

if (!$matiere) {
    showMessage('error', 'Matière non trouvée.');
    redirectTo('reports.php');
}

// Construire la condition de niveau
$niveau_condition = '';
$params = [$current_year['id'], $matiere_id];
if ($niveau) {
    $niveau_condition = ' AND c.niveau = ?';
    $params[] = $niveau;
}

// Récupérer les statistiques par classe pour cette matière
$stats_classes = $database->query(
    "SELECT c.nom as classe_nom, c.niveau,
            COUNT(DISTINCT e.id) as nb_evaluations,
            COUNT(n.id) as nb_notes,
            AVG(n.note) as moyenne_classe,
            MIN(n.note) as note_min,
            MAX(n.note) as note_max,
            COUNT(DISTINCT i.eleve_id) as nb_eleves
     FROM classes c
     LEFT JOIN evaluations e ON (c.id = e.classe_id AND e.annee_scolaire_id = ? AND e.matiere_id = ?)
     LEFT JOIN notes n ON e.id = n.evaluation_id
     LEFT JOIN inscriptions i ON (c.id = i.classe_id AND i.annee_scolaire_id = ? AND i.status = 'inscrit')
     WHERE c.annee_scolaire_id = ? $niveau_condition
     GROUP BY c.id, c.nom, c.niveau
     HAVING nb_evaluations > 0
     ORDER BY c.niveau, moyenne_classe DESC",
    array_merge($params, [$current_year['id'], $current_year['id']])
)->fetchAll();

// Récupérer les évaluations de cette matière
$evaluations = $database->query(
    "SELECT e.*, c.nom as classe_nom, c.niveau,
            CONCAT(p.nom, ' ', p.prenom) as enseignant_nom,
            COUNT(n.id) as notes_saisies,
            AVG(n.note) as moyenne_evaluation
     FROM evaluations e
     JOIN classes c ON e.classe_id = c.id
     LEFT JOIN personnel p ON e.enseignant_id = p.id
     LEFT JOIN notes n ON e.id = n.evaluation_id
     WHERE e.annee_scolaire_id = ? AND e.matiere_id = ? $niveau_condition
     GROUP BY e.id, c.nom, c.niveau, p.nom, p.prenom
     ORDER BY e.date_evaluation DESC",
    $params
)->fetchAll();

// Calculer les statistiques générales
$stats_generales = [
    'nb_classes' => count($stats_classes),
    'nb_evaluations' => count($evaluations),
    'nb_notes_total' => array_sum(array_column($stats_classes, 'nb_notes')),
    'moyenne_generale' => 0
];

if (!empty($stats_classes)) {
    $moyennes_classes = array_filter(array_column($stats_classes, 'moyenne_classe'));
    if (!empty($moyennes_classes)) {
        $stats_generales['moyenne_generale'] = round(array_sum($moyennes_classes) / count($moyennes_classes), 2);
    }
}

$page_title = 'Rapport matière : ' . $matiere['nom'];

include '../../../includes/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">
        <i class="fas fa-book me-2"></i>
        Rapport par matière
    </h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <div class="btn-group me-2">
            <a href="reports.php" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-1"></i>
                Retour
            </a>
        </div>
    </div>
</div>

<!-- En-tête -->
<div class="card mb-4">
    <div class="card-body">
        <div class="text-center">
            <h2><?php echo htmlspecialchars($matiere['nom']); ?></h2>
            <h4 class="text-muted">
                Analyse de performance
                <?php if ($niveau): ?>
                    - Niveau <?php echo ucfirst($niveau); ?>
                <?php endif; ?>
            </h4>
            <p class="text-muted">
                Coefficient : <?php echo $matiere['coefficient']; ?> | 
                Année scolaire <?php echo $current_year['annee']; ?>
            </p>
        </div>
    </div>
</div>

<!-- Statistiques générales -->
<div class="row mb-4">
    <div class="col-lg-3 col-md-6 mb-3">
        <div class="card bg-primary text-white">
            <div class="card-body text-center">
                <h3><?php echo $stats_generales['nb_classes']; ?></h3>
                <p class="mb-0">Classes</p>
            </div>
        </div>
    </div>
    <div class="col-lg-3 col-md-6 mb-3">
        <div class="card bg-success text-white">
            <div class="card-body text-center">
                <h3><?php echo $stats_generales['nb_evaluations']; ?></h3>
                <p class="mb-0">Évaluations</p>
            </div>
        </div>
    </div>
    <div class="col-lg-3 col-md-6 mb-3">
        <div class="card bg-info text-white">
            <div class="card-body text-center">
                <h3><?php echo $stats_generales['moyenne_generale']; ?>/20</h3>
                <p class="mb-0">Moyenne générale</p>
            </div>
        </div>
    </div>
    <div class="col-lg-3 col-md-6 mb-3">
        <div class="card bg-warning text-white">
            <div class="card-body text-center">
                <h3><?php echo $stats_generales['nb_notes_total']; ?></h3>
                <p class="mb-0">Notes saisies</p>
            </div>
        </div>
    </div>
</div>

<?php if ($type === 'comparison'): ?>
    <!-- Comparaison des classes -->
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="mb-0">
                <i class="fas fa-balance-scale me-2"></i>
                Comparaison des performances par classe
            </h5>
        </div>
        <div class="card-body">
            <?php if (!empty($stats_classes)): ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Rang</th>
                                <th>Classe</th>
                                <th>Niveau</th>
                                <th>Élèves</th>
                                <th>Évaluations</th>
                                <th>Notes saisies</th>
                                <th>Moyenne</th>
                                <th>Min/Max</th>
                                <th>Performance</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($stats_classes as $index => $classe): ?>
                                <tr>
                                    <td>
                                        <?php if ($index < 3): ?>
                                            <span class="badge bg-warning"><?php echo $index + 1; ?></span>
                                        <?php else: ?>
                                            <?php echo $index + 1; ?>
                                        <?php endif; ?>
                                    </td>
                                    <td><strong><?php echo htmlspecialchars($classe['classe_nom']); ?></strong></td>
                                    <td>
                                        <span class="badge bg-secondary">
                                            <?php echo ucfirst($classe['niveau']); ?>
                                        </span>
                                    </td>
                                    <td class="text-center"><?php echo $classe['nb_eleves']; ?></td>
                                    <td class="text-center"><?php echo $classe['nb_evaluations']; ?></td>
                                    <td class="text-center"><?php echo $classe['nb_notes']; ?></td>
                                    <td class="text-center">
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
                                    <td class="text-center">
                                        <?php if ($classe['note_min'] && $classe['note_max']): ?>
                                            <small class="text-muted">
                                                <?php echo $classe['note_min']; ?> - <?php echo $classe['note_max']; ?>
                                            </small>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
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
                <p class="text-muted">Aucune donnée disponible pour la comparaison.</p>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>

<!-- Liste des évaluations -->
<div class="card mb-4">
    <div class="card-header">
        <h5 class="mb-0">
            <i class="fas fa-clipboard-list me-2"></i>
            Évaluations de la matière (<?php echo count($evaluations); ?>)
        </h5>
    </div>
    <div class="card-body">
        <?php if (!empty($evaluations)): ?>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Nom</th>
                            <th>Classe</th>
                            <th>Type</th>
                            <th>Enseignant</th>
                            <th>Notes saisies</th>
                            <th>Moyenne</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($evaluations as $eval): ?>
                            <tr>
                                <td><?php echo date('d/m/Y', strtotime($eval['date_evaluation'])); ?></td>
                                <td><strong><?php echo htmlspecialchars($eval['nom']); ?></strong></td>
                                <td>
                                    <?php echo htmlspecialchars($eval['classe_nom']); ?>
                                    <small class="text-muted">(<?php echo ucfirst($eval['niveau']); ?>)</small>
                                </td>
                                <td>
                                    <span class="badge bg-primary">
                                        <?php echo ucfirst($eval['type_evaluation']); ?>
                                    </span>
                                </td>
                                <td><?php echo htmlspecialchars($eval['enseignant_nom']); ?></td>
                                <td class="text-center"><?php echo $eval['notes_saisies']; ?></td>
                                <td class="text-center">
                                    <?php if ($eval['moyenne_evaluation']): ?>
                                        <span class="badge bg-success">
                                            <?php echo round($eval['moyenne_evaluation'], 2); ?>/20
                                        </span>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <a href="evaluation_report.php?id=<?php echo $eval['id']; ?>&format=view" 
                                           class="btn btn-outline-info" title="Voir rapport">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="../evaluations/view.php?id=<?php echo $eval['id']; ?>" 
                                           class="btn btn-outline-primary" title="Détails">
                                            <i class="fas fa-info"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="text-center py-4">
                <i class="fas fa-clipboard fa-3x text-muted mb-3"></i>
                <h5 class="text-muted">Aucune évaluation trouvée</h5>
                <p class="text-muted">
                    Aucune évaluation n'a été trouvée pour cette matière
                    <?php if ($niveau): ?>
                        au niveau <?php echo ucfirst($niveau); ?>
                    <?php endif; ?>.
                </p>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php if ($type === 'analysis'): ?>
    <!-- Analyse détaillée -->
    <div class="row">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-chart-line me-2"></i>
                        Analyse de performance
                    </h5>
                </div>
                <div class="card-body">
                    <?php if (!empty($stats_classes)): ?>
                        <h6>Répartition par niveau de performance :</h6>
                        <?php
                        $performance_levels = [
                            'excellent' => ['label' => 'Excellent (≥16)', 'count' => 0, 'color' => 'success'],
                            'tres_bien' => ['label' => 'Très bien (14-16)', 'count' => 0, 'color' => 'info'],
                            'bien' => ['label' => 'Bien (12-14)', 'count' => 0, 'color' => 'primary'],
                            'passable' => ['label' => 'Passable (10-12)', 'count' => 0, 'color' => 'warning'],
                            'insuffisant' => ['label' => 'Insuffisant (<10)', 'count' => 0, 'color' => 'danger']
                        ];
                        
                        foreach ($stats_classes as $classe) {
                            if ($classe['moyenne_classe']) {
                                $moyenne = round($classe['moyenne_classe'], 2);
                                if ($moyenne >= 16) $performance_levels['excellent']['count']++;
                                elseif ($moyenne >= 14) $performance_levels['tres_bien']['count']++;
                                elseif ($moyenne >= 12) $performance_levels['bien']['count']++;
                                elseif ($moyenne >= 10) $performance_levels['passable']['count']++;
                                else $performance_levels['insuffisant']['count']++;
                            }
                        }
                        
                        $total_classes = count($stats_classes);
                        ?>
                        
                        <?php foreach ($performance_levels as $level): ?>
                            <div class="mb-3">
                                <div class="d-flex justify-content-between mb-1">
                                    <span><?php echo $level['label']; ?></span>
                                    <span><?php echo $level['count']; ?> classe(s) (<?php echo $total_classes > 0 ? round(($level['count'] / $total_classes) * 100, 1) : 0; ?>%)</span>
                                </div>
                                <div class="progress">
                                    <div class="progress-bar bg-<?php echo $level['color']; ?>" 
                                         style="width: <?php echo $total_classes > 0 ? ($level['count'] / $total_classes) * 100 : 0; ?>%"></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p class="text-muted">Aucune donnée disponible pour l'analyse.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <div class="col-lg-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-info-circle me-2"></i>
                        Informations sur la matière
                    </h5>
                </div>
                <div class="card-body">
                    <table class="table table-borderless table-sm">
                        <tr>
                            <td><strong>Nom :</strong></td>
                            <td><?php echo htmlspecialchars($matiere['nom']); ?></td>
                        </tr>
                        <tr>
                            <td><strong>Code :</strong></td>
                            <td><?php echo htmlspecialchars($matiere['code'] ?? 'N/A'); ?></td>
                        </tr>
                        <tr>
                            <td><strong>Coefficient :</strong></td>
                            <td><span class="badge bg-info"><?php echo $matiere['coefficient']; ?></span></td>
                        </tr>
                        <tr>
                            <td><strong>Niveau :</strong></td>
                            <td><?php echo ucfirst($matiere['niveau'] ?? 'Tous'); ?></td>
                        </tr>
                        <tr>
                            <td><strong>Description :</strong></td>
                            <td><?php echo htmlspecialchars($matiere['description'] ?? 'Aucune'); ?></td>
                        </tr>
                    </table>
                </div>
            </div>
            
            <div class="card mt-3">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-download me-2"></i>
                        Exports
                    </h5>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <a href="?matiere_id=<?php echo $matiere_id; ?>&niveau=<?php echo $niveau; ?>&type=excel" 
                           class="btn btn-success btn-sm">
                            <i class="fas fa-file-excel me-1"></i>
                            Export Excel
                        </a>
                        <a href="?matiere_id=<?php echo $matiere_id; ?>&niveau=<?php echo $niveau; ?>&type=comparison" 
                           class="btn btn-info btn-sm">
                            <i class="fas fa-balance-scale me-1"></i>
                            Comparaison classes
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php include '../../../includes/footer.php'; ?>
