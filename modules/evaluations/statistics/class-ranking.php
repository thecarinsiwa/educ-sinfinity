<?php
/**
 * Module d'évaluations - Classement des classes
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

$page_title = 'Classement des classes';

// Récupérer l'année scolaire active
$current_year = $database->query("SELECT * FROM annees_scolaires WHERE status = 'active' LIMIT 1")->fetch();
if (!$current_year) {
    showMessage('error', 'Aucune année scolaire active trouvée.');
    redirectTo('../../../index.php');
}

// Récupérer les paramètres de filtrage
$niveau_filter = sanitizeInput($_GET['niveau'] ?? '');
$periode_filter = sanitizeInput($_GET['periode'] ?? '');
$matiere_filter = (int)($_GET['matiere_id'] ?? 0);

// Construire les conditions de filtrage
$where_conditions = ['e.annee_scolaire_id = ?'];
$params = [$current_year['id']];

if ($niveau_filter) {
    $where_conditions[] = 'c.niveau = ?';
    $params[] = $niveau_filter;
}

if ($periode_filter) {
    $where_conditions[] = 'e.periode = ?';
    $params[] = $periode_filter;
}

if ($matiere_filter) {
    $where_conditions[] = 'e.matiere_id = ?';
    $params[] = $matiere_filter;
}

$where_clause = implode(' AND ', $where_conditions);

// Récupérer le classement des classes
$classement_classes = $database->query(
    "SELECT c.id, c.nom as classe_nom, c.niveau, c.section,
            COUNT(DISTINCT e.id) as nb_evaluations,
            COUNT(n.id) as nb_notes,
            COUNT(DISTINCT i.eleve_id) as nb_eleves,
            AVG(n.note / e.note_max * 20) as moyenne_classe,
            MIN(n.note / e.note_max * 20) as note_min,
            MAX(n.note / e.note_max * 20) as note_max,
            STDDEV(n.note / e.note_max * 20) as ecart_type
     FROM classes c
     LEFT JOIN evaluations e ON c.id = e.classe_id AND $where_clause
     LEFT JOIN notes n ON e.id = n.evaluation_id
     LEFT JOIN inscriptions i ON (c.id = i.classe_id AND i.annee_scolaire_id = ? AND i.status = 'inscrit')
     WHERE c.annee_scolaire_id = ?
     GROUP BY c.id, c.nom, c.niveau, c.section
     HAVING nb_evaluations > 0
     ORDER BY moyenne_classe DESC, nb_notes DESC",
    array_merge($params, [$current_year['id'], $current_year['id']])
)->fetchAll();

// Récupérer les statistiques par niveau
$stats_niveaux = $database->query(
    "SELECT c.niveau,
            COUNT(DISTINCT c.id) as nb_classes,
            COUNT(DISTINCT i.eleve_id) as nb_eleves,
            AVG(moyenne_classe) as moyenne_niveau
     FROM (
         SELECT c.id, c.niveau,
                AVG(n.note / e.note_max * 20) as moyenne_classe
         FROM classes c
         LEFT JOIN evaluations e ON c.id = e.classe_id AND $where_clause
         LEFT JOIN notes n ON e.id = n.evaluation_id
         WHERE c.annee_scolaire_id = ?
         GROUP BY c.id, c.niveau
         HAVING COUNT(n.id) > 0
     ) as moyennes_classes
     JOIN classes c ON moyennes_classes.id = c.id
     LEFT JOIN inscriptions i ON (c.id = i.classe_id AND i.annee_scolaire_id = ? AND i.status = 'inscrit')
     GROUP BY c.niveau
     ORDER BY moyenne_niveau DESC",
    array_merge($params, [$current_year['id'], $current_year['id']])
)->fetchAll();

// Récupérer les listes pour les filtres
$niveaux = $database->query(
    "SELECT DISTINCT niveau FROM classes WHERE annee_scolaire_id = ? ORDER BY niveau",
    [$current_year['id']]
)->fetchAll();

$matieres = $database->query(
    "SELECT * FROM matieres ORDER BY nom"
)->fetchAll();

// Calculer les statistiques générales
$stats_generales = [
    'nb_classes_total' => count($classement_classes),
    'moyenne_generale' => 0,
    'meilleure_classe' => null,
    'classe_en_difficulte' => null
];

if (!empty($classement_classes)) {
    $moyennes = array_column($classement_classes, 'moyenne_classe');
    $stats_generales['moyenne_generale'] = array_sum($moyennes) / count($moyennes);
    $stats_generales['meilleure_classe'] = $classement_classes[0];
    $stats_generales['classe_en_difficulte'] = end($classement_classes);
}

include '../../../includes/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">
        <i class="fas fa-trophy me-2"></i>
        Classement des classes
    </h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <div class="btn-group me-2">
            <a href="../notes/" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-1"></i>
                Retour aux notes
            </a>
            <a href="../notes/statistics.php" class="btn btn-outline-info">
                <i class="fas fa-chart-bar me-1"></i>
                Statistiques générales
            </a>
        </div>
        <div class="btn-group">
            <button onclick="window.print()" class="btn btn-primary">
                <i class="fas fa-print me-1"></i>
                Imprimer
            </button>
        </div>
    </div>
</div>

<!-- Filtres -->
<div class="card mb-4">
    <div class="card-header">
        <h5 class="mb-0">
            <i class="fas fa-filter me-2"></i>
            Filtres de classement
        </h5>
    </div>
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-md-3">
                <label for="niveau" class="form-label">Niveau</label>
                <select class="form-select" id="niveau" name="niveau">
                    <option value="">Tous les niveaux</option>
                    <?php foreach ($niveaux as $niveau): ?>
                        <option value="<?php echo $niveau['niveau']; ?>" 
                                <?php echo $niveau_filter === $niveau['niveau'] ? 'selected' : ''; ?>>
                            <?php echo ucfirst($niveau['niveau']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="col-md-3">
                <label for="periode" class="form-label">Période</label>
                <select class="form-select" id="periode" name="periode">
                    <option value="">Toutes les périodes</option>
                    <option value="1er_trimestre" <?php echo $periode_filter === '1er_trimestre' ? 'selected' : ''; ?>>
                        1er Trimestre
                    </option>
                    <option value="2eme_trimestre" <?php echo $periode_filter === '2eme_trimestre' ? 'selected' : ''; ?>>
                        2ème Trimestre
                    </option>
                    <option value="3eme_trimestre" <?php echo $periode_filter === '3eme_trimestre' ? 'selected' : ''; ?>>
                        3ème Trimestre
                    </option>
                    <option value="annuelle" <?php echo $periode_filter === 'annuelle' ? 'selected' : ''; ?>>
                        Année complète
                    </option>
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
            
            <div class="col-md-3">
                <label class="form-label">&nbsp;</label>
                <div class="d-grid">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search me-1"></i>
                        Filtrer
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Statistiques générales -->
<div class="row mb-4">
    <div class="col-lg-3 col-md-6 mb-3">
        <div class="card bg-primary text-white">
            <div class="card-body text-center">
                <h3><?php echo $stats_generales['nb_classes_total']; ?></h3>
                <p class="mb-0">Classes classées</p>
            </div>
        </div>
    </div>
    <div class="col-lg-3 col-md-6 mb-3">
        <div class="card bg-success text-white">
            <div class="card-body text-center">
                <h3><?php echo $stats_generales['moyenne_generale'] ? round($stats_generales['moyenne_generale'], 2) : '0'; ?>/20</h3>
                <p class="mb-0">Moyenne générale</p>
            </div>
        </div>
    </div>
    <div class="col-lg-3 col-md-6 mb-3">
        <div class="card bg-warning text-white">
            <div class="card-body text-center">
                <h3>
                    <?php if ($stats_generales['meilleure_classe']): ?>
                        <?php echo round($stats_generales['meilleure_classe']['moyenne_classe'], 2); ?>/20
                    <?php else: ?>
                        -
                    <?php endif; ?>
                </h3>
                <p class="mb-0">Meilleure moyenne</p>
            </div>
        </div>
    </div>
    <div class="col-lg-3 col-md-6 mb-3">
        <div class="card bg-info text-white">
            <div class="card-body text-center">
                <h3><?php echo count($stats_niveaux); ?></h3>
                <p class="mb-0">Niveaux représentés</p>
            </div>
        </div>
    </div>
</div>

<!-- Statistiques par niveau -->
<?php if (!empty($stats_niveaux)): ?>
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="mb-0">
                <i class="fas fa-layer-group me-2"></i>
                Performance par niveau
            </h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Niveau</th>
                            <th>Classes</th>
                            <th>Élèves</th>
                            <th>Moyenne niveau</th>
                            <th>Performance</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($stats_niveaux as $niveau): ?>
                            <tr>
                                <td>
                                    <strong><?php echo ucfirst($niveau['niveau']); ?></strong>
                                </td>
                                <td class="text-center"><?php echo $niveau['nb_classes']; ?></td>
                                <td class="text-center"><?php echo $niveau['nb_eleves']; ?></td>
                                <td class="text-center">
                                    <?php
                                    $moyenne_niveau = $niveau['moyenne_niveau'] ? round($niveau['moyenne_niveau'], 2) : 0;
                                    $color = $moyenne_niveau >= 14 ? 'success' : ($moyenne_niveau >= 10 ? 'warning' : 'danger');
                                    ?>
                                    <span class="badge bg-<?php echo $color; ?> fs-6">
                                        <?php echo $moyenne_niveau; ?>/20
                                    </span>
                                </td>
                                <td class="text-center">
                                    <?php
                                    if ($moyenne_niveau >= 16) {
                                        echo '<i class="fas fa-star text-warning" title="Excellent"></i>';
                                    } elseif ($moyenne_niveau >= 14) {
                                        echo '<i class="fas fa-thumbs-up text-success" title="Très bien"></i>';
                                    } elseif ($moyenne_niveau >= 12) {
                                        echo '<i class="fas fa-check text-info" title="Bien"></i>';
                                    } elseif ($moyenne_niveau >= 10) {
                                        echo '<i class="fas fa-minus text-warning" title="Passable"></i>';
                                    } else {
                                        echo '<i class="fas fa-times text-danger" title="Insuffisant"></i>';
                                    }
                                    ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
<?php endif; ?>

<!-- Classement détaillé des classes -->
<div class="card">
    <div class="card-header">
        <h5 class="mb-0">
            <i class="fas fa-list-ol me-2"></i>
            Classement détaillé (<?php echo count($classement_classes); ?> classes)
        </h5>
    </div>
    <div class="card-body">
        <?php if (!empty($classement_classes)): ?>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Rang</th>
                            <th>Classe</th>
                            <th>Niveau</th>
                            <th>Élèves</th>
                            <th>Évaluations</th>
                            <th>Notes</th>
                            <th>Moyenne</th>
                            <th>Min/Max</th>
                            <th>Écart-type</th>
                            <th>Performance</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($classement_classes as $index => $classe): ?>
                            <tr <?php echo $index < 3 ? 'class="table-warning"' : ''; ?>>
                                <td>
                                    <?php if ($index < 3): ?>
                                        <span class="badge bg-warning fs-6"><?php echo $index + 1; ?></span>
                                    <?php else: ?>
                                        <span class="fw-bold"><?php echo $index + 1; ?></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <strong><?php echo htmlspecialchars($classe['classe_nom']); ?></strong>
                                    <?php if ($classe['section']): ?>
                                        <br><small class="text-muted"><?php echo htmlspecialchars($classe['section']); ?></small>
                                    <?php endif; ?>
                                </td>
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
                                        $color = $moyenne >= 16 ? 'success' : ($moyenne >= 14 ? 'info' : ($moyenne >= 12 ? 'primary' : ($moyenne >= 10 ? 'warning' : 'danger')));
                                        ?>
                                        <span class="badge bg-<?php echo $color; ?> fs-6">
                                            <?php echo $moyenne; ?>/20
                                        </span>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <small class="text-muted">
                                        <?php echo $classe['note_min'] ? round($classe['note_min'], 1) : '0'; ?> - <?php echo $classe['note_max'] ? round($classe['note_max'], 1) : '0'; ?>
                                    </small>
                                </td>
                                <td class="text-center">
                                    <small class="text-muted">
                                        <?php echo $classe['ecart_type'] ? round($classe['ecart_type'], 2) : '0'; ?>
                                    </small>
                                </td>
                                <td class="text-center">
                                    <?php
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
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Légende -->
            <div class="mt-3">
                <h6>Légende :</h6>
                <div class="row">
                    <div class="col-md-6">
                        <small>
                            <i class="fas fa-star text-warning"></i> Excellent (≥16/20) |
                            <i class="fas fa-thumbs-up text-success"></i> Très bien (14-16/20) |
                            <i class="fas fa-check text-info"></i> Bien (12-14/20)
                        </small>
                    </div>
                    <div class="col-md-6">
                        <small>
                            <i class="fas fa-minus text-warning"></i> Passable (10-12/20) |
                            <i class="fas fa-times text-danger"></i> Insuffisant (<10/20)
                        </small>
                    </div>
                </div>
                <small class="text-muted mt-2 d-block">
                    <strong>Écart-type :</strong> Mesure de la dispersion des notes dans la classe. 
                    Plus la valeur est faible, plus les notes sont homogènes.
                </small>
            </div>
            
        <?php else: ?>
            <div class="text-center py-5">
                <i class="fas fa-chart-bar fa-3x text-muted mb-3"></i>
                <h5 class="text-muted">Aucune donnée de classement</h5>
                <p class="text-muted">
                    Aucune classe n'a d'évaluations avec les critères sélectionnés.<br>
                    Modifiez les filtres ou vérifiez que des notes ont été saisies.
                </p>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Analyse comparative -->
<?php if (!empty($classement_classes) && count($classement_classes) > 1): ?>
    <div class="card mt-4">
        <div class="card-header">
            <h5 class="mb-0">
                <i class="fas fa-chart-line me-2"></i>
                Analyse comparative
            </h5>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-lg-6">
                    <h6>Classes les plus performantes :</h6>
                    <ul class="list-unstyled">
                        <?php for ($i = 0; $i < min(3, count($classement_classes)); $i++): ?>
                            <li class="mb-2">
                                <span class="badge bg-success me-2"><?php echo $i + 1; ?></span>
                                <strong><?php echo htmlspecialchars($classement_classes[$i]['classe_nom']); ?></strong>
                                - <?php echo $classement_classes[$i]['moyenne_classe'] ? round($classement_classes[$i]['moyenne_classe'], 2) : '0'; ?>/20
                                <small class="text-muted">
                                    (<?php echo $classement_classes[$i]['nb_eleves']; ?> élèves)
                                </small>
                            </li>
                        <?php endfor; ?>
                    </ul>
                </div>
                
                <div class="col-lg-6">
                    <h6>Classes nécessitant un accompagnement :</h6>
                    <ul class="list-unstyled">
                        <?php 
                        $classes_difficulte = array_slice(array_reverse($classement_classes), 0, 3);
                        foreach ($classes_difficulte as $index => $classe): 
                        ?>
                            <li class="mb-2">
                                <span class="badge bg-warning me-2"><?php echo count($classement_classes) - $index; ?></span>
                                <strong><?php echo htmlspecialchars($classe['classe_nom']); ?></strong>
                                - <?php echo $classe['moyenne_classe'] ? round($classe['moyenne_classe'], 2) : '0'; ?>/20
                                <small class="text-muted">
                                    (<?php echo $classe['nb_eleves']; ?> élèves)
                                </small>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
            
            <hr>
            
            <div class="row">
                <div class="col-lg-4">
                    <h6>Écart de performance :</h6>
                    <p>
                        <?php
                        $premiere_moyenne = $classement_classes[0]['moyenne_classe'] ?? 0;
                        $derniere_moyenne = end($classement_classes)['moyenne_classe'] ?? 0;
                        $ecart = $premiere_moyenne - $derniere_moyenne;
                        echo round($ecart, 2);
                        ?> points entre la meilleure et la moins performante
                    </p>
                </div>
                <div class="col-lg-4">
                    <h6>Homogénéité :</h6>
                    <p>
                        <?php
                        $moyennes = array_filter(array_column($classement_classes, 'moyenne_classe'), function($x) { return $x !== null; });
                        if (!empty($moyennes)) {
                            $moyenne_generale = $stats_generales['moyenne_generale'] ?? 0;
                            $ecart_type_general = sqrt(array_sum(array_map(function($x) use ($moyenne_generale) {
                                return pow($x - $moyenne_generale, 2);
                            }, $moyennes)) / count($moyennes));
                            echo round($ecart_type_general, 2);
                        } else {
                            echo '0';
                        }
                        ?> d'écart-type général
                    </p>
                </div>
                <div class="col-lg-4">
                    <h6>Recommandation :</h6>
                    <p>
                        <?php if ($ecart < 3): ?>
                            <span class="text-success">Performances homogènes</span>
                        <?php elseif ($ecart < 5): ?>
                            <span class="text-warning">Écarts modérés</span>
                        <?php else: ?>
                            <span class="text-danger">Écarts importants - Accompagnement nécessaire</span>
                        <?php endif; ?>
                    </p>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php include '../../../includes/footer.php'; ?>
