<?php
/**
 * Module Rapports Académiques - Analyse détaillée
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

$page_title = 'Analyse détaillée des performances';
$current_year = getCurrentAcademicYear();

if (!$current_year) {
    showMessage('error', 'Aucune année scolaire active.');
    redirectTo('../../../index.php');
}

// Paramètres de filtrage
$periode_filter = sanitizeInput($_GET['periode'] ?? '');
$classe_filter = (int)($_GET['classe'] ?? 0);
$matiere_filter = (int)($_GET['matiere'] ?? 0);
$niveau_filter = sanitizeInput($_GET['niveau'] ?? '');

// Récupérer les classes pour le filtre
$classes = $database->query(
    "SELECT c.id, c.nom, c.niveau, c.section
     FROM classes c
     WHERE c.annee_scolaire_id = ?
     ORDER BY c.niveau, c.nom",
    [$current_year['id']]
)->fetchAll();

// Récupérer les matières pour le filtre
$matieres = $database->query(
    "SELECT m.id, m.nom, m.coefficient
     FROM matieres m
     JOIN evaluations e ON m.id = e.matiere_id
     WHERE e.annee_scolaire_id = ?
     GROUP BY m.id, m.nom, m.coefficient
     ORDER BY m.nom",
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
$where_conditions = ["e.annee_scolaire_id = ?"];
$params = [$current_year['id']];

if ($periode_filter) {
    $where_conditions[] = "e.periode = ?";
    $params[] = $periode_filter;
}

if ($classe_filter) {
    $where_conditions[] = "i.classe_id = ?";
    $params[] = $classe_filter;
}

if ($matiere_filter) {
    $where_conditions[] = "e.matiere_id = ?";
    $params[] = $matiere_filter;
}

if ($niveau_filter) {
    $where_conditions[] = "c.niveau = ?";
    $params[] = $niveau_filter;
}

$where_clause = implode(' AND ', $where_conditions);

// 1. Analyse par niveau
$analyse_niveaux = $database->query(
    "SELECT c.niveau,
            COUNT(DISTINCT e.id) as nb_eleves,
            COUNT(n.id) as nb_notes,
            AVG(n.note) as moyenne_generale,
            MIN(n.note) as note_min,
            MAX(n.note) as note_max,
            STDDEV(n.note) as ecart_type,
            COUNT(CASE WHEN n.note >= 16 THEN 1 END) as excellents,
            COUNT(CASE WHEN n.note >= 14 AND n.note < 16 THEN 1 END) as tres_bien,
            COUNT(CASE WHEN n.note >= 12 AND n.note < 14 THEN 1 END) as bien,
            COUNT(CASE WHEN n.note >= 10 AND n.note < 12 THEN 1 END) as satisfaisant,
            COUNT(CASE WHEN n.note >= 8 AND n.note < 10 THEN 1 END) as passable,
            COUNT(CASE WHEN n.note < 8 THEN 1 END) as insuffisant
     FROM classes c
     JOIN inscriptions i ON c.id = i.classe_id
     JOIN eleves e ON i.eleve_id = e.id
     JOIN notes n ON e.id = n.eleve_id
     JOIN evaluations ev ON n.evaluation_id = ev.id
     WHERE $where_clause AND i.status = 'inscrit'
     GROUP BY c.niveau
     ORDER BY 
        CASE c.niveau 
            WHEN 'maternelle' THEN 1 
            WHEN 'primaire' THEN 2 
            WHEN 'secondaire' THEN 3 
            WHEN 'superieur' THEN 4 
        END",
    $params
)->fetchAll();

// 2. Analyse par classe
$analyse_classes = $database->query(
    "SELECT c.id, c.nom as classe_nom, c.niveau, c.section,
            COUNT(DISTINCT e.id) as nb_eleves,
            COUNT(n.id) as nb_notes,
            AVG(n.note) as moyenne_classe,
            MIN(n.note) as note_min,
            MAX(n.note) as note_max,
            STDDEV(n.note) as ecart_type,
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

// 3. Analyse par matière
$analyse_matieres = $database->query(
    "SELECT m.id, m.nom as matiere_nom, m.coefficient,
            COUNT(n.id) as nb_notes,
            AVG(n.note) as moyenne_matiere,
            MIN(n.note) as note_min,
            MAX(n.note) as note_max,
            STDDEV(n.note) as ecart_type,
            COUNT(CASE WHEN n.note >= 10 THEN 1 END) * 100.0 / COUNT(n.id) as taux_reussite
     FROM matieres m
     JOIN evaluations e ON m.id = e.matiere_id
     JOIN notes n ON e.id = n.evaluation_id
     WHERE $where_clause
     GROUP BY m.id, m.nom, m.coefficient
     ORDER BY moyenne_matiere DESC",
    $params
)->fetchAll();

// 4. Distribution des notes
$distribution_notes = $database->query(
    "SELECT 
        CASE 
            WHEN n.note >= 18 THEN '18-20'
            WHEN n.note >= 16 THEN '16-17'
            WHEN n.note >= 14 THEN '14-15'
            WHEN n.note >= 12 THEN '12-13'
            WHEN n.note >= 10 THEN '10-11'
            WHEN n.note >= 8 THEN '8-9'
            WHEN n.note >= 6 THEN '6-7'
            WHEN n.note >= 4 THEN '4-5'
            WHEN n.note >= 2 THEN '2-3'
            ELSE '0-1'
        END as tranche_notes,
        COUNT(*) as nombre_notes,
        COUNT(*) * 100.0 / (SELECT COUNT(*) FROM notes n2 
                           JOIN evaluations e2 ON n2.evaluation_id = e2.id 
                           WHERE $where_clause) as pourcentage
     FROM notes n
     JOIN evaluations e ON n.evaluation_id = e.id
     WHERE $where_clause
     GROUP BY 
        CASE 
            WHEN n.note >= 18 THEN '18-20'
            WHEN n.note >= 16 THEN '16-17'
            WHEN n.note >= 14 THEN '14-15'
            WHEN n.note >= 12 THEN '12-13'
            WHEN n.note >= 10 THEN '10-11'
            WHEN n.note >= 8 THEN '8-9'
            WHEN n.note >= 6 THEN '6-7'
            WHEN n.note >= 4 THEN '4-5'
            WHEN n.note >= 2 THEN '2-3'
            ELSE '0-1'
        END
     ORDER BY 
        CASE tranche_notes
            WHEN '18-20' THEN 1
            WHEN '16-17' THEN 2
            WHEN '14-15' THEN 3
            WHEN '12-13' THEN 4
            WHEN '10-11' THEN 5
            WHEN '8-9' THEN 6
            WHEN '6-7' THEN 7
            WHEN '4-5' THEN 8
            WHEN '2-3' THEN 9
            WHEN '0-1' THEN 10
        END",
    $params
)->fetchAll();

// 5. Évolution par période
$evolution_periodes = $database->query(
    "SELECT e.periode,
            COUNT(DISTINCT n.eleve_id) as nb_eleves_evalues,
            COUNT(n.id) as nb_notes,
            AVG(n.note) as moyenne_periode,
            MIN(n.note) as note_min,
            MAX(n.note) as note_max,
            COUNT(CASE WHEN n.note >= 10 THEN 1 END) * 100.0 / COUNT(n.id) as taux_reussite
     FROM evaluations e
     JOIN notes n ON e.id = n.evaluation_id
     WHERE e.annee_scolaire_id = ?
     " . ($classe_filter ? "AND EXISTS (SELECT 1 FROM inscriptions i JOIN classes c ON i.classe_id = c.id WHERE i.eleve_id = n.eleve_id AND i.classe_id = ?)" : "") . "
     " . ($matiere_filter ? "AND e.matiere_id = ?" : "") . "
     GROUP BY e.periode
     ORDER BY 
        CASE e.periode 
            WHEN '1er trimestre' THEN 1 
            WHEN '2ème trimestre' THEN 2 
            WHEN '3ème trimestre' THEN 3 
        END",
    array_filter([$current_year['id'], $classe_filter, $matiere_filter])
)->fetchAll();

include '../../../../includes/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">
        <i class="fas fa-chart-line me-2"></i>
        Analyse détaillée des performances
    </h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <div class="btn-group me-2">
            <a href="../index.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left me-1"></i>
                Retour aux rapports
            </a>
        </div>
        <div class="btn-group">
            <a href="../export.php?type=performance&format=excel<?php echo $periode_filter ? '&periode=' . urlencode($periode_filter) : ''; ?><?php echo $classe_filter ? '&classe=' . $classe_filter : ''; ?>" class="btn btn-outline-success">
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
            Filtres d'analyse
        </h5>
    </div>
    <div class="card-body">
        <form method="GET" action="" class="row g-3">
            <div class="col-md-2">
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
            
            <div class="col-md-2">
                <label for="classe" class="form-label">Classe</label>
                <select class="form-select" id="classe" name="classe">
                    <option value="">Toutes les classes</option>
                    <?php foreach ($classes as $c): ?>
                        <option value="<?php echo $c['id']; ?>" <?php echo $classe_filter == $c['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($c['nom']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="col-md-2">
                <label for="matiere" class="form-label">Matière</label>
                <select class="form-select" id="matiere" name="matiere">
                    <option value="">Toutes les matières</option>
                    <?php foreach ($matieres as $m): ?>
                        <option value="<?php echo $m['id']; ?>" <?php echo $matiere_filter == $m['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($m['nom']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="col-md-2">
                <label for="niveau" class="form-label">Niveau</label>
                <select class="form-select" id="niveau" name="niveau">
                    <option value="">Tous les niveaux</option>
                    <option value="maternelle" <?php echo $niveau_filter === 'maternelle' ? 'selected' : ''; ?>>Maternelle</option>
                    <option value="primaire" <?php echo $niveau_filter === 'primaire' ? 'selected' : ''; ?>>Primaire</option>
                    <option value="secondaire" <?php echo $niveau_filter === 'secondaire' ? 'selected' : ''; ?>>Secondaire</option>
                    <option value="superieur" <?php echo $niveau_filter === 'superieur' ? 'selected' : ''; ?>>Supérieur</option>
                </select>
            </div>
            
            <div class="col-md-4 d-flex align-items-end">
                <button type="submit" class="btn btn-primary me-2">
                    <i class="fas fa-search me-1"></i>
                    Analyser
                </button>
                <a href="?" class="btn btn-outline-secondary">
                    <i class="fas fa-times me-1"></i>
                    Réinitialiser
                </a>
            </div>
        </form>
    </div>
</div>

<!-- Résumé des filtres actifs -->
<?php if ($periode_filter || $classe_filter || $matiere_filter || $niveau_filter): ?>
    <div class="alert alert-info">
        <h6><i class="fas fa-info-circle me-2"></i>Filtres actifs :</h6>
        <div class="row">
            <?php if ($periode_filter): ?>
                <div class="col-md-3">
                    <strong>Période :</strong> <?php echo htmlspecialchars($periode_filter); ?>
                </div>
            <?php endif; ?>
            <?php if ($classe_filter): ?>
                <div class="col-md-3">
                    <strong>Classe :</strong> <?php echo htmlspecialchars($classes[array_search($classe_filter, array_column($classes, 'id'))]['nom'] ?? ''); ?>
                </div>
            <?php endif; ?>
            <?php if ($matiere_filter): ?>
                <div class="col-md-3">
                    <strong>Matière :</strong> <?php echo htmlspecialchars($matieres[array_search($matiere_filter, array_column($matieres, 'id'))]['nom'] ?? ''); ?>
                </div>
            <?php endif; ?>
            <?php if ($niveau_filter): ?>
                <div class="col-md-3">
                    <strong>Niveau :</strong> <?php echo ucfirst($niveau_filter); ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>

<!-- 1. Analyse par niveau -->
<div class="card mb-4">
    <div class="card-header">
        <h5 class="mb-0">
            <i class="fas fa-layer-group me-2"></i>
            Analyse par niveau
        </h5>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-striped">
                <thead>
                    <tr>
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
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($analyse_niveaux as $niveau): ?>
                        <tr>
                            <td><strong><?php echo ucfirst($niveau['niveau']); ?></strong></td>
                            <td><?php echo $niveau['nb_eleves']; ?></td>
                            <td><?php echo $niveau['nb_notes']; ?></td>
                            <td><span class="badge bg-primary"><?php echo round($niveau['moyenne_generale'], 2); ?></span></td>
                            <td><?php echo $niveau['note_min']; ?></td>
                            <td><?php echo $niveau['note_max']; ?></td>
                            <td><?php echo round($niveau['ecart_type'], 2); ?></td>
                            <td><span class="badge bg-success"><?php echo $niveau['excellents']; ?></span></td>
                            <td><span class="badge bg-info"><?php echo $niveau['tres_bien']; ?></span></td>
                            <td><span class="badge bg-primary"><?php echo $niveau['bien']; ?></span></td>
                            <td><span class="badge bg-warning"><?php echo $niveau['satisfaisant']; ?></span></td>
                            <td><span class="badge bg-secondary"><?php echo $niveau['passable']; ?></span></td>
                            <td><span class="badge bg-danger"><?php echo $niveau['insuffisant']; ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- 2. Analyse par classe -->
<div class="card mb-4">
    <div class="card-header">
        <h5 class="mb-0">
            <i class="fas fa-users me-2"></i>
            Analyse par classe
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
                        <th>Taux de réussite</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($analyse_classes as $classe): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($classe['classe_nom']); ?></strong></td>
                            <td><?php echo ucfirst($classe['niveau']); ?></td>
                            <td><?php echo $classe['nb_eleves']; ?></td>
                            <td><?php echo $classe['nb_notes']; ?></td>
                            <td><span class="badge bg-primary"><?php echo round($classe['moyenne_classe'], 2); ?></span></td>
                            <td><?php echo $classe['note_min']; ?></td>
                            <td><?php echo $classe['note_max']; ?></td>
                            <td><?php echo round($classe['ecart_type'], 2); ?></td>
                            <td><span class="badge bg-<?php echo $classe['taux_reussite'] >= 80 ? 'success' : ($classe['taux_reussite'] >= 60 ? 'warning' : 'danger'); ?>"><?php echo round($classe['taux_reussite'], 1); ?>%</span></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- 3. Analyse par matière -->
<div class="card mb-4">
    <div class="card-header">
        <h5 class="mb-0">
            <i class="fas fa-book me-2"></i>
            Analyse par matière
        </h5>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>Matière</th>
                        <th>Coefficient</th>
                        <th>Notes</th>
                        <th>Moyenne</th>
                        <th>Min</th>
                        <th>Max</th>
                        <th>Écart-type</th>
                        <th>Taux de réussite</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($analyse_matieres as $matiere): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($matiere['matiere_nom']); ?></strong></td>
                            <td><?php echo $matiere['coefficient']; ?></td>
                            <td><?php echo $matiere['nb_notes']; ?></td>
                            <td><span class="badge bg-primary"><?php echo round($matiere['moyenne_matiere'], 2); ?></span></td>
                            <td><?php echo $matiere['note_min']; ?></td>
                            <td><?php echo $matiere['note_max']; ?></td>
                            <td><?php echo round($matiere['ecart_type'], 2); ?></td>
                            <td><span class="badge bg-<?php echo $matiere['taux_reussite'] >= 80 ? 'success' : ($matiere['taux_reussite'] >= 60 ? 'warning' : 'danger'); ?>"><?php echo round($matiere['taux_reussite'], 1); ?>%</span></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- 4. Distribution des notes -->
<div class="row">
    <div class="col-md-6">
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-chart-pie me-2"></i>
                    Distribution des notes
                </h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Tranche</th>
                                <th>Nombre</th>
                                <th>Pourcentage</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($distribution_notes as $tranche): ?>
                                <tr>
                                    <td><strong><?php echo $tranche['tranche_notes']; ?></strong></td>
                                    <td><?php echo $tranche['nombre_notes']; ?></td>
                                    <td><?php echo round($tranche['pourcentage'], 1); ?>%</td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-6">
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-chart-line me-2"></i>
                    Évolution par période
                </h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Période</th>
                                <th>Élèves</th>
                                <th>Notes</th>
                                <th>Moyenne</th>
                                <th>Taux de réussite</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($evolution_periodes as $periode): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($periode['periode']); ?></strong></td>
                                    <td><?php echo $periode['nb_eleves_evalues']; ?></td>
                                    <td><?php echo $periode['nb_notes']; ?></td>
                                    <td><span class="badge bg-primary"><?php echo round($periode['moyenne_periode'], 2); ?></span></td>
                                    <td><span class="badge bg-<?php echo $periode['taux_reussite'] >= 80 ? 'success' : ($periode['taux_reussite'] >= 60 ? 'warning' : 'danger'); ?>"><?php echo round($periode['taux_reussite'], 1); ?>%</span></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Graphique de distribution -->
<?php if (!empty($distribution_notes)): ?>
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">
                <i class="fas fa-chart-bar me-2"></i>
                Graphique de distribution des notes
            </h5>
        </div>
        <div class="card-body">
            <canvas id="distributionChart" width="400" height="200"></canvas>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const ctx = document.getElementById('distributionChart').getContext('2d');
            
            const data = {
                labels: [<?php echo implode(',', array_map(function($tranche) { return '"' . $tranche['tranche_notes'] . '"'; }, $distribution_notes)); ?>],
                datasets: [{
                    label: 'Nombre de notes',
                    data: [<?php echo implode(',', array_map(function($tranche) { return $tranche['nombre_notes']; }, $distribution_notes)); ?>],
                    backgroundColor: [
                        'rgba(255, 99, 132, 0.2)',
                        'rgba(54, 162, 235, 0.2)',
                        'rgba(255, 206, 86, 0.2)',
                        'rgba(75, 192, 192, 0.2)',
                        'rgba(153, 102, 255, 0.2)',
                        'rgba(255, 159, 64, 0.2)',
                        'rgba(199, 199, 199, 0.2)',
                        'rgba(83, 102, 255, 0.2)',
                        'rgba(255, 205, 86, 0.2)',
                        'rgba(255, 99, 132, 0.2)'
                    ],
                    borderColor: [
                        'rgba(255, 99, 132, 1)',
                        'rgba(54, 162, 235, 1)',
                        'rgba(255, 206, 86, 1)',
                        'rgba(75, 192, 192, 1)',
                        'rgba(153, 102, 255, 1)',
                        'rgba(255, 159, 64, 1)',
                        'rgba(199, 199, 199, 1)',
                        'rgba(83, 102, 255, 1)',
                        'rgba(255, 205, 86, 1)',
                        'rgba(255, 99, 132, 1)'
                    ],
                    borderWidth: 2
                }]
            };
            
            const config = {
                type: 'bar',
                data: data,
                options: {
                    responsive: true,
                    scales: {
                        y: {
                            beginAtZero: true
                        }
                    },
                    plugins: {
                        legend: {
                            display: false
                        }
                    }
                }
            };
            
            new Chart(ctx, config);
        });
    </script>
<?php endif; ?>

<?php include '../../../../includes/footer.php'; ?>
