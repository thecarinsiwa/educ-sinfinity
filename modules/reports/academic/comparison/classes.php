<?php
/**
 * Module Rapports Académiques - Comparaison des classes
 * Application de gestion scolaire - République Démocratique du Congo
 */

require_once '../../../../config/config.php';
require_once '../../../../config/database.php';
require_once '../../../../includes/functions.php';

// Vérifier l'authentification et les permissions
requireLogin();
if (!checkPermission('reports') && !checkPermission('academic')) {
    showMessage('error', 'Accès refusé à cette fonctionnalité.');
    redirectTo('../index.php');
}

$page_title = 'Comparaison des classes';
$current_year = getCurrentAcademicYear();

if (!$current_year) {
    showMessage('error', 'Aucune année scolaire active.');
    redirectTo('../../../index.php');
}

// Paramètres de filtrage
$periode_filter = sanitizeInput($_GET['periode'] ?? '');
$niveau_filter = sanitizeInput($_GET['niveau'] ?? '');
$classes_selectionnees = $_GET['classes'] ?? [];

// Récupérer les classes disponibles
$classes = $database->query(
    "SELECT c.id, c.nom, c.niveau, c.section,
            COUNT(DISTINCT i.eleve_id) as nb_eleves
     FROM classes c
     LEFT JOIN inscriptions i ON c.id = i.classe_id AND i.status = 'inscrit'
     WHERE c.annee_scolaire_id = ?
     GROUP BY c.id, c.nom, c.niveau, c.section
     ORDER BY c.niveau, c.nom",
    [$current_year['id']]
)->fetchAll();

// Récupérer les périodes disponibles
$periodes = $database->query(
    "SELECT DISTINCT periode FROM evaluations 
     WHERE annee_scolaire_id = ? 
     ORDER BY 
        CASE periode 
            WHEN '1er trimestre' THEN 1 
            WHEN '2ème trimestre' THEN 2 
            WHEN '3ème trimestre' THEN 3 
        END",
    [$current_year['id']]
)->fetchAll();

// Construire les conditions WHERE
$where_conditions = ["c.annee_scolaire_id = ?"];
$params = [$current_year['id']];

if ($periode_filter) {
    $where_conditions[] = "e.periode = ?";
    $params[] = $periode_filter;
}

if ($niveau_filter) {
    $where_conditions[] = "c.niveau = ?";
    $params[] = $niveau_filter;
}

if (!empty($classes_selectionnees)) {
    $placeholders = str_repeat('?,', count($classes_selectionnees) - 1) . '?';
    $where_conditions[] = "c.id IN ($placeholders)";
    $params = array_merge($params, $classes_selectionnees);
}

$where_clause = implode(' AND ', $where_conditions);

// Récupérer les données de comparaison
$comparaison_classes = $database->query(
    "SELECT c.id, c.nom as classe_nom, c.niveau, c.section,
            COUNT(DISTINCT e.id) as nb_eleves_evalues,
            COUNT(n.id) as nb_notes,
            AVG(n.note) as moyenne_classe,
            MIN(n.note) as note_min,
            MAX(n.note) as note_max,
            STDDEV(n.note) as ecart_type,
            COUNT(CASE WHEN n.note >= 16 THEN 1 END) as excellents,
            COUNT(CASE WHEN n.note >= 14 AND n.note < 16 THEN 1 END) as tres_bien,
            COUNT(CASE WHEN n.note >= 12 AND n.note < 14 THEN 1 END) as bien,
            COUNT(CASE WHEN n.note >= 10 AND n.note < 12 THEN 1 END) as satisfaisant,
            COUNT(CASE WHEN n.note >= 8 AND n.note < 10 THEN 1 END) as passable,
            COUNT(CASE WHEN n.note < 8 THEN 1 END) as insuffisant,
            COUNT(CASE WHEN n.note >= 10 THEN 1 END) * 100.0 / COUNT(n.id) as taux_reussite
     FROM classes c
     JOIN inscriptions i ON c.id = i.classe_id
     JOIN eleves e ON i.eleve_id = e.id
     JOIN notes n ON e.id = n.eleve_id
     JOIN evaluations ev ON n.evaluation_id = ev.id
     WHERE $where_clause AND i.status = 'inscrit'
     GROUP BY c.id, c.nom, c.niveau, c.section
     ORDER BY moyenne_classe DESC",
    $params
)->fetchAll();

// Calculer les statistiques globales pour comparaison
$stats_globales = [
    'moyenne_globale' => 0,
    'note_min_globale' => 20,
    'note_max_globale' => 0,
    'taux_reussite_global' => 0,
    'total_notes' => 0,
    'total_eleves' => 0
];

if (!empty($comparaison_classes)) {
    $total_notes = array_sum(array_column($comparaison_classes, 'nb_notes'));
    $total_eleves = array_sum(array_column($comparaison_classes, 'nb_eleves_evalues'));
    $moyenne_ponderee = 0;
    $taux_reussite_pondere = 0;
    
    foreach ($comparaison_classes as $classe) {
        $moyenne_ponderee += $classe['moyenne_classe'] * $classe['nb_notes'];
        $taux_reussite_pondere += ($classe['taux_reussite'] / 100) * $classe['nb_notes'];
        
        if ($classe['note_min'] < $stats_globales['note_min_globale']) {
            $stats_globales['note_min_globale'] = $classe['note_min'];
        }
        if ($classe['note_max'] > $stats_globales['note_max_globale']) {
            $stats_globales['note_max_globale'] = $classe['note_max'];
        }
    }
    
    $stats_globales['moyenne_globale'] = $total_notes > 0 ? round($moyenne_ponderee / $total_notes, 2) : 0;
    $stats_globales['taux_reussite_global'] = $total_notes > 0 ? round(($taux_reussite_pondere / $total_notes) * 100, 1) : 0;
    $stats_globales['total_notes'] = $total_notes;
    $stats_globales['total_eleves'] = $total_eleves;
}

// Récupérer les données par matière pour chaque classe
$comparaison_matieres = [];
if (!empty($classes_selectionnees)) {
    $placeholders = str_repeat('?,', count($classes_selectionnees) - 1) . '?';
    $comparaison_matieres = $database->query(
        "SELECT c.id, c.nom as classe_nom, m.nom as matiere_nom,
                COUNT(n.id) as nb_notes,
                AVG(n.note) as moyenne_matiere,
                COUNT(CASE WHEN n.note >= 10 THEN 1 END) * 100.0 / COUNT(n.id) as taux_reussite
         FROM classes c
         JOIN inscriptions i ON c.id = i.classe_id
         JOIN eleves e ON i.eleve_id = e.id
         JOIN notes n ON e.id = n.eleve_id
         JOIN evaluations ev ON n.evaluation_id = ev.id
         JOIN matieres m ON ev.matiere_id = m.id
         WHERE c.id IN ($placeholders) AND i.status = 'inscrit'
         " . ($periode_filter ? "AND ev.periode = ?" : "") . "
         GROUP BY c.id, c.nom, m.nom
         ORDER BY c.nom, m.nom",
        array_merge($classes_selectionnees, $periode_filter ? [$periode_filter] : [])
    )->fetchAll();
}

include '../../../../includes/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">
        <i class="fas fa-balance-scale me-2"></i>
        Comparaison des classes
    </h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <div class="btn-group me-2">
            <a href="../index.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left me-1"></i>
                Retour aux rapports
            </a>
        </div>
        <div class="btn-group">
            <a href="../export.php?type=comparaison&format=excel<?php echo $periode_filter ? '&periode=' . urlencode($periode_filter) : ''; ?>" class="btn btn-outline-success">
                <i class="fas fa-file-excel me-1"></i>
                Exporter Excel
            </a>
        </div>
    </div>
</div>

<!-- Filtres -->
<div class="card mb-4">
    <div class="card-header">
        <h5 class="mb-0">
            <i class="fas fa-filter me-2"></i>
            Paramètres de comparaison
        </h5>
    </div>
    <div class="card-body">
        <form method="GET" action="">
            <div class="row">
                <div class="col-md-3">
                    <label for="periode" class="form-label">Période</label>
                    <select class="form-select" id="periode" name="periode">
                        <option value="">Toutes les périodes</option>
                        <?php foreach ($periodes as $p): ?>
                            <option value="<?php echo htmlspecialchars($p['periode']); ?>" <?php echo $periode_filter === $p['periode'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($p['periode']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="col-md-3">
                    <label for="niveau" class="form-label">Niveau</label>
                    <select class="form-select" id="niveau" name="niveau">
                        <option value="">Tous les niveaux</option>
                        <option value="maternelle" <?php echo $niveau_filter === 'maternelle' ? 'selected' : ''; ?>>Maternelle</option>
                        <option value="primaire" <?php echo $niveau_filter === 'primaire' ? 'selected' : ''; ?>>Primaire</option>
                        <option value="secondaire" <?php echo $niveau_filter === 'secondaire' ? 'selected' : ''; ?>>Secondaire</option>
                        <option value="superieur" <?php echo $niveau_filter === 'superieur' ? 'selected' : ''; ?>>Supérieur</option>
                    </select>
                </div>
                
                <div class="col-md-6">
                    <label class="form-label">Classes à comparer</label>
                    <div class="row">
                        <?php 
                        $classes_par_colonne = array_chunk($classes, ceil(count($classes) / 2));
                        foreach ($classes_par_colonne as $colonne): 
                        ?>
                            <div class="col-md-6">
                                <?php foreach ($colonne as $classe): ?>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" 
                                               id="classe_<?php echo $classe['id']; ?>" 
                                               name="classes[]" 
                                               value="<?php echo $classe['id']; ?>"
                                               <?php echo in_array($classe['id'], $classes_selectionnees) ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="classe_<?php echo $classe['id']; ?>">
                                            <?php echo htmlspecialchars($classe['nom']); ?>
                                            <small class="text-muted">(<?php echo ucfirst($classe['niveau']); ?>)</small>
                                        </label>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            
            <div class="mt-3">
                <button type="submit" class="btn btn-primary me-2">
                    <i class="fas fa-search me-1"></i>
                    Comparer
                </button>
                <a href="?" class="btn btn-outline-secondary">
                    <i class="fas fa-times me-1"></i>
                    Réinitialiser
                </a>
            </div>
        </form>
    </div>
</div>

<?php if (!empty($comparaison_classes)): ?>
    <!-- Statistiques globales -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card text-center">
                <div class="card-body">
                    <h3 class="text-primary"><?php echo $stats_globales['moyenne_globale']; ?></h3>
                    <p class="mb-0">Moyenne globale</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-center">
                <div class="card-body">
                    <h3 class="text-success"><?php echo $stats_globales['taux_reussite_global']; ?>%</h3>
                    <p class="mb-0">Taux de réussite</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-center">
                <div class="card-body">
                    <h3 class="text-info"><?php echo $stats_globales['total_eleves']; ?></h3>
                    <p class="mb-0">Élèves évalués</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-center">
                <div class="card-body">
                    <h3 class="text-warning"><?php echo $stats_globales['total_notes']; ?></h3>
                    <p class="mb-0">Total notes</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Tableau de comparaison -->
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="mb-0">
                <i class="fas fa-table me-2"></i>
                Comparaison détaillée des classes
            </h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Classe</th>
                            <th>Niveau</th>
                            <th>Élèves</th>
                            <th>Notes</th>
                            <th>Moyenne</th>
                            <th>Min</th>
                            <th>Max</th>
                            <th>Écart-type</th>
                            <th>Excellent</th>
                            <th>Très bien</th>
                            <th>Bien</th>
                            <th>Satisfaisant</th>
                            <th>Passable</th>
                            <th>Insuffisant</th>
                            <th>Taux de réussite</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($comparaison_classes as $classe): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($classe['classe_nom']); ?></strong></td>
                                <td><?php echo ucfirst($classe['niveau']); ?></td>
                                <td><?php echo $classe['nb_eleves_evalues']; ?></td>
                                <td><?php echo $classe['nb_notes']; ?></td>
                                <td>
                                    <span class="badge bg-<?php echo $classe['moyenne_classe'] >= $stats_globales['moyenne_globale'] ? 'success' : 'danger'; ?>">
                                        <?php echo round($classe['moyenne_classe'], 2); ?>
                                    </span>
                                </td>
                                <td><?php echo $classe['note_min']; ?></td>
                                <td><?php echo $classe['note_max']; ?></td>
                                <td><?php echo round($classe['ecart_type'], 2); ?></td>
                                <td><span class="badge bg-success"><?php echo $classe['excellents']; ?></span></td>
                                <td><span class="badge bg-info"><?php echo $classe['tres_bien']; ?></span></td>
                                <td><span class="badge bg-primary"><?php echo $classe['bien']; ?></span></td>
                                <td><span class="badge bg-warning"><?php echo $classe['satisfaisant']; ?></span></td>
                                <td><span class="badge bg-secondary"><?php echo $classe['passable']; ?></span></td>
                                <td><span class="badge bg-danger"><?php echo $classe['insuffisant']; ?></span></td>
                                <td>
                                    <span class="badge bg-<?php echo $classe['taux_reussite'] >= $stats_globales['taux_reussite_global'] ? 'success' : 'danger'; ?>">
                                        <?php echo round($classe['taux_reussite'], 1); ?>%
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Graphique de comparaison -->
    <div class="row">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-chart-bar me-2"></i>
                        Comparaison des moyennes
                    </h5>
                </div>
                <div class="card-body">
                    <canvas id="moyennesChart" width="400" height="200"></canvas>
                </div>
            </div>
        </div>
        
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-chart-pie me-2"></i>
                        Comparaison des taux de réussite
                    </h5>
                </div>
                <div class="card-body">
                    <canvas id="reussiteChart" width="400" height="200"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Comparaison par matière -->
    <?php if (!empty($comparaison_matieres)): ?>
        <div class="card mt-4">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-book me-2"></i>
                    Comparaison par matière
                </h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Classe</th>
                                <th>Matière</th>
                                <th>Notes</th>
                                <th>Moyenne</th>
                                <th>Taux de réussite</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($comparaison_matieres as $matiere): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($matiere['classe_nom']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($matiere['matiere_nom']); ?></td>
                                    <td><?php echo $matiere['nb_notes']; ?></td>
                                    <td>
                                        <span class="badge bg-primary">
                                            <?php echo round($matiere['moyenne_matiere'], 2); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?php echo $matiere['taux_reussite'] >= 80 ? 'success' : ($matiere['taux_reussite'] >= 60 ? 'warning' : 'danger'); ?>">
                                            <?php echo round($matiere['taux_reussite'], 1); ?>%
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Graphique des moyennes
            const ctx1 = document.getElementById('moyennesChart').getContext('2d');
            new Chart(ctx1, {
                type: 'bar',
                data: {
                    labels: [<?php echo implode(',', array_map(function($classe) { return '"' . addslashes($classe['classe_nom']) . '"'; }, $comparaison_classes)); ?>],
                    datasets: [{
                        label: 'Moyenne',
                        data: [<?php echo implode(',', array_map(function($classe) { return $classe['moyenne_classe']; }, $comparaison_classes)); ?>],
                        backgroundColor: 'rgba(54, 162, 235, 0.2)',
                        borderColor: 'rgba(54, 162, 235, 1)',
                        borderWidth: 2
                    }]
                },
                options: {
                    responsive: true,
                    scales: {
                        y: {
                            beginAtZero: true,
                            max: 20
                        }
                    }
                }
            });

            // Graphique des taux de réussite
            const ctx2 = document.getElementById('reussiteChart').getContext('2d');
            new Chart(ctx2, {
                type: 'doughnut',
                data: {
                    labels: [<?php echo implode(',', array_map(function($classe) { return '"' . addslashes($classe['classe_nom']) . '"'; }, $comparaison_classes)); ?>],
                    datasets: [{
                        data: [<?php echo implode(',', array_map(function($classe) { return $classe['taux_reussite']; }, $comparaison_classes)); ?>],
                        backgroundColor: [
                            'rgba(255, 99, 132, 0.8)',
                            'rgba(54, 162, 235, 0.8)',
                            'rgba(255, 206, 86, 0.8)',
                            'rgba(75, 192, 192, 0.8)',
                            'rgba(153, 102, 255, 0.8)',
                            'rgba(255, 159, 64, 0.8)'
                        ]
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: {
                            position: 'bottom'
                        }
                    }
                }
            });
        });
    </script>

<?php else: ?>
    <div class="alert alert-info">
        <h5><i class="fas fa-info-circle me-2"></i>Aucune donnée à comparer</h5>
        <p class="mb-0">
            Veuillez sélectionner au moins une classe et appliquer les filtres pour voir les résultats de comparaison.
        </p>
    </div>
<?php endif; ?>

<?php include '../../../../includes/footer.php'; ?>
