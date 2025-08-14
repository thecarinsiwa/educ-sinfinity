<?php
/**
 * Module Rapports Académiques - Page principale
 * Application de gestion scolaire - République Démocratique du Congo
 */

require_once '../../../config/config.php';
require_once '../../../config/database.php';
require_once '../../../includes/functions.php';

// Vérifier l'authentification
requireLogin();

$page_title = 'Rapports Académiques';

// Obtenir l'année scolaire actuelle
$current_year = getCurrentAcademicYear();

// Paramètres de filtrage
$periode_filter = sanitizeInput($_GET['periode'] ?? '');
$classe_filter = (int)($_GET['classe'] ?? 0);
$matiere_filter = (int)($_GET['matiere'] ?? 0);

// Statistiques académiques générales
$stats = [];

// Nombre total d'évaluations
$stmt = $database->query(
    "SELECT COUNT(*) as total FROM evaluations WHERE annee_scolaire_id = ?",
    [$current_year['id'] ?? 0]
);
$stats['total_evaluations'] = $stmt->fetch()['total'];

// Moyenne générale de l'école
$stmt = $database->query(
    "SELECT AVG(n.note) as moyenne FROM notes n 
     JOIN evaluations e ON n.evaluation_id = e.id 
     WHERE e.annee_scolaire_id = ?",
    [$current_year['id'] ?? 0]
);
$stats['moyenne_ecole'] = round($stmt->fetch()['moyenne'] ?? 0, 2);

// Taux de réussite global (moyenne >= 10)
$stmt = $database->query(
    "SELECT 
        COUNT(CASE WHEN moyenne >= 10 THEN 1 END) * 100.0 / COUNT(*) as taux_reussite
     FROM (
        SELECT AVG(n.note) as moyenne
        FROM notes n 
        JOIN evaluations e ON n.evaluation_id = e.id 
        WHERE e.annee_scolaire_id = ?
        GROUP BY n.eleve_id
     ) as moyennes_eleves",
    [$current_year['id'] ?? 0]
);
$stats['taux_reussite'] = round($stmt->fetch()['taux_reussite'] ?? 0, 1);

// Performance par classe
$performance_classes = $database->query(
    "SELECT c.id, c.nom as classe_nom, c.niveau,
            COUNT(DISTINCT n.eleve_id) as nb_eleves_evalues,
            AVG(n.note) as moyenne_classe,
            MIN(n.note) as note_min,
            MAX(n.note) as note_max,
            COUNT(CASE WHEN n.note >= 16 THEN 1 END) as excellents,
            COUNT(CASE WHEN n.note >= 10 AND n.note < 16 THEN 1 END) as admis,
            COUNT(CASE WHEN n.note < 10 THEN 1 END) as echecs
     FROM classes c
     JOIN inscriptions i ON c.id = i.classe_id
     JOIN notes n ON i.eleve_id = n.eleve_id
     JOIN evaluations e ON n.evaluation_id = e.id
     WHERE c.annee_scolaire_id = ? AND i.status = 'inscrit'
     " . ($periode_filter ? "AND e.periode = ?" : "") . "
     GROUP BY c.id, c.nom, c.niveau
     ORDER BY moyenne_classe DESC",
    array_filter([$current_year['id'] ?? 0, $periode_filter])
)->fetchAll();

// Performance par matière
$performance_matieres = $database->query(
    "SELECT m.id, m.nom as matiere_nom, m.coefficient,
            COUNT(n.id) as nb_notes,
            AVG(n.note) as moyenne_matiere,
            MIN(n.note) as note_min,
            MAX(n.note) as note_max
     FROM matieres m
     JOIN evaluations e ON m.id = e.matiere_id
     JOIN notes n ON e.id = n.evaluation_id
     WHERE e.annee_scolaire_id = ?
     " . ($periode_filter ? "AND e.periode = ?" : "") . "
     GROUP BY m.id, m.nom, m.coefficient
     ORDER BY moyenne_matiere DESC",
    array_filter([$current_year['id'] ?? 0, $periode_filter])
)->fetchAll();

// Évolution des moyennes par trimestre
$evolution_trimestres = $database->query(
    "SELECT e.periode, AVG(n.note) as moyenne
     FROM evaluations e
     JOIN notes n ON e.id = n.evaluation_id
     WHERE e.annee_scolaire_id = ?
     GROUP BY e.periode
     ORDER BY 
        CASE e.periode 
            WHEN '1er_trimestre' THEN 1 
            WHEN '2eme_trimestre' THEN 2 
            WHEN '3eme_trimestre' THEN 3 
        END",
    [$current_year['id'] ?? 0]
)->fetchAll();

// Top 10 des meilleurs élèves
$meilleurs_eleves = $database->query(
    "SELECT e.id, e.nom, e.prenom, e.numero_matricule, c.nom as classe_nom,
            AVG(n.note) as moyenne_generale,
            COUNT(n.id) as nb_notes
     FROM eleves e
     JOIN inscriptions i ON e.id = i.eleve_id
     JOIN classes c ON i.classe_id = c.id
     JOIN notes n ON e.id = n.eleve_id
     JOIN evaluations ev ON n.evaluation_id = ev.id
     WHERE i.status = 'inscrit' AND i.annee_scolaire_id = ?
     " . ($periode_filter ? "AND ev.periode = ?" : "") . "
     GROUP BY e.id, e.nom, e.prenom, e.numero_matricule, c.nom
     HAVING nb_notes >= 3
     ORDER BY moyenne_generale DESC
     LIMIT 10",
    array_filter([$current_year['id'] ?? 0, $periode_filter])
)->fetchAll();

// Répartition des mentions
$repartition_mentions = $database->query(
    "SELECT 
        CASE 
            WHEN moyenne >= 16 THEN 'Excellent'
            WHEN moyenne >= 14 THEN 'Très bien'
            WHEN moyenne >= 12 THEN 'Bien'
            WHEN moyenne >= 10 THEN 'Satisfaisant'
            WHEN moyenne >= 8 THEN 'Passable'
            ELSE 'Insuffisant'
        END as mention,
        COUNT(*) as nombre
     FROM (
        SELECT AVG(n.note) as moyenne
        FROM notes n 
        JOIN evaluations e ON n.evaluation_id = e.id 
        WHERE e.annee_scolaire_id = ?
        " . ($periode_filter ? "AND e.periode = ?" : "") . "
        GROUP BY n.eleve_id
     ) as moyennes_eleves
     GROUP BY mention
     ORDER BY 
        CASE mention
            WHEN 'Excellent' THEN 1
            WHEN 'Très bien' THEN 2
            WHEN 'Bien' THEN 3
            WHEN 'Satisfaisant' THEN 4
            WHEN 'Passable' THEN 5
            ELSE 6
        END",
    array_filter([$current_year['id'] ?? 0, $periode_filter])
)->fetchAll();

// Récupérer les classes et matières pour les filtres
$classes = $database->query(
    "SELECT id, nom, niveau FROM classes WHERE annee_scolaire_id = ? ORDER BY niveau, nom",
    [$current_year['id'] ?? 0]
)->fetchAll();

$matieres = $database->query("SELECT id, nom FROM matieres ORDER BY nom")->fetchAll();

include '../../../includes/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">
        <i class="fas fa-graduation-cap me-2"></i>
        Rapports Académiques
    </h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <div class="btn-group me-2">
            <a href="../index.php" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-1"></i>
                Retour
            </a>
        </div>
        <div class="btn-group me-2">
            <button type="button" class="btn btn-primary dropdown-toggle" data-bs-toggle="dropdown">
                <i class="fas fa-file-export me-1"></i>
                Exporter
            </button>
            <ul class="dropdown-menu">
                <li><a class="dropdown-item" href="export.php?type=performance&format=pdf&<?php echo http_build_query($_GET); ?>">
                    <i class="fas fa-file-pdf me-2"></i>Rapport PDF
                </a></li>
                <li><a class="dropdown-item" href="export.php?type=performance&format=excel&<?php echo http_build_query($_GET); ?>">
                    <i class="fas fa-file-excel me-2"></i>Données Excel
                </a></li>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item" href="bulletins/generate-all.php">
                    <i class="fas fa-file-alt me-2"></i>Tous les bulletins
                </a></li>
            </ul>
        </div>
        <div class="btn-group">
            <button type="button" class="btn btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">
                <i class="fas fa-tools me-1"></i>
                Outils
            </button>
            <ul class="dropdown-menu">
                <li><a class="dropdown-item" href="analysis/detailed.php">
                    <i class="fas fa-microscope me-2"></i>Analyse détaillée
                </a></li>
                <li><a class="dropdown-item" href="comparison/classes.php">
                    <i class="fas fa-balance-scale me-2"></i>Comparaison classes
                </a></li>
                <li><a class="dropdown-item" href="trends/evolution.php">
                    <i class="fas fa-chart-line me-2"></i>Tendances
                </a></li>
            </ul>
        </div>
    </div>
</div>

<!-- Statistiques rapides -->
<div class="row mb-4">
    <div class="col-lg-3 col-md-6 mb-3">
        <div class="card text-white bg-primary">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h4><?php echo $stats['total_evaluations']; ?></h4>
                        <p class="mb-0">Évaluations</p>
                    </div>
                    <div class="align-self-center">
                        <i class="fas fa-clipboard-list fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-3 col-md-6 mb-3">
        <div class="card text-white bg-success">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h4><?php echo $stats['moyenne_ecole']; ?>/20</h4>
                        <p class="mb-0">Moyenne école</p>
                    </div>
                    <div class="align-self-center">
                        <i class="fas fa-star fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-3 col-md-6 mb-3">
        <div class="card text-white bg-info">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h4><?php echo $stats['taux_reussite']; ?>%</h4>
                        <p class="mb-0">Taux de réussite</p>
                    </div>
                    <div class="align-self-center">
                        <i class="fas fa-chart-pie fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-3 col-md-6 mb-3">
        <div class="card text-white bg-warning">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h4><?php echo count($performance_classes); ?></h4>
                        <p class="mb-0">Classes évaluées</p>
                    </div>
                    <div class="align-self-center">
                        <i class="fas fa-school fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Filtres -->
<div class="card mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-md-4">
                <label for="periode" class="form-label">Période</label>
                <select class="form-select" id="periode" name="periode">
                    <option value="">Toutes les périodes</option>
                    <option value="1er_trimestre" <?php echo $periode_filter === '1er_trimestre' ? 'selected' : ''; ?>>1er Trimestre</option>
                    <option value="2eme_trimestre" <?php echo $periode_filter === '2eme_trimestre' ? 'selected' : ''; ?>>2ème Trimestre</option>
                    <option value="3eme_trimestre" <?php echo $periode_filter === '3eme_trimestre' ? 'selected' : ''; ?>>3ème Trimestre</option>
                </select>
            </div>
            <div class="col-md-4">
                <label for="classe" class="form-label">Classe</label>
                <select class="form-select" id="classe" name="classe">
                    <option value="">Toutes les classes</option>
                    <?php foreach ($classes as $classe): ?>
                        <option value="<?php echo $classe['id']; ?>" 
                                <?php echo $classe_filter == $classe['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($classe['nom']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label">&nbsp;</label>
                <div class="d-grid">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-filter me-1"></i>
                        Filtrer
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Contenu principal -->
<div class="row">
    <div class="col-lg-8">
        <!-- Performance par classe -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-chart-bar me-2"></i>
                    Performance par classe
                </h5>
            </div>
            <div class="card-body">
                <?php if (!empty($performance_classes)): ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Classe</th>
                                    <th>Niveau</th>
                                    <th>Élèves</th>
                                    <th>Moyenne</th>
                                    <th>Min/Max</th>
                                    <th>Excellents</th>
                                    <th>Admis</th>
                                    <th>Échecs</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($performance_classes as $classe): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo htmlspecialchars($classe['classe_nom']); ?></strong>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?php 
                                                echo $classe['niveau'] === 'maternelle' ? 'warning' : 
                                                    ($classe['niveau'] === 'primaire' ? 'success' : 'primary'); 
                                            ?>">
                                                <?php echo ucfirst($classe['niveau']); ?>
                                            </span>
                                        </td>
                                        <td class="text-center"><?php echo $classe['nb_eleves_evalues']; ?></td>
                                        <td class="text-center">
                                            <span class="badge bg-<?php 
                                                echo $classe['moyenne_classe'] >= 14 ? 'success' : 
                                                    ($classe['moyenne_classe'] >= 10 ? 'warning' : 'danger'); 
                                            ?> fs-6">
                                                <?php echo round($classe['moyenne_classe'], 2); ?>/20
                                            </span>
                                        </td>
                                        <td class="text-center">
                                            <small>
                                                <?php echo round($classe['note_min'], 1); ?> - <?php echo round($classe['note_max'], 1); ?>
                                            </small>
                                        </td>
                                        <td class="text-center">
                                            <span class="badge bg-success"><?php echo $classe['excellents']; ?></span>
                                        </td>
                                        <td class="text-center">
                                            <span class="badge bg-info"><?php echo $classe['admis']; ?></span>
                                        </td>
                                        <td class="text-center">
                                            <span class="badge bg-danger"><?php echo $classe['echecs']; ?></span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="text-center py-4">
                        <i class="fas fa-chart-bar fa-3x text-muted mb-3"></i>
                        <p class="text-muted">Aucune donnée de performance disponible</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Performance par matière -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-book me-2"></i>
                    Performance par matière
                </h5>
            </div>
            <div class="card-body">
                <?php if (!empty($performance_matieres)): ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Matière</th>
                                    <th>Coefficient</th>
                                    <th>Notes</th>
                                    <th>Moyenne</th>
                                    <th>Min/Max</th>
                                    <th>Performance</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($performance_matieres as $matiere): ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($matiere['matiere_nom']); ?></strong></td>
                                        <td class="text-center"><?php echo $matiere['coefficient']; ?></td>
                                        <td class="text-center"><?php echo $matiere['nb_notes']; ?></td>
                                        <td class="text-center">
                                            <span class="badge bg-<?php 
                                                echo $matiere['moyenne_matiere'] >= 14 ? 'success' : 
                                                    ($matiere['moyenne_matiere'] >= 10 ? 'warning' : 'danger'); 
                                            ?> fs-6">
                                                <?php echo round($matiere['moyenne_matiere'], 2); ?>/20
                                            </span>
                                        </td>
                                        <td class="text-center">
                                            <small>
                                                <?php echo round($matiere['note_min'], 1); ?> - <?php echo round($matiere['note_max'], 1); ?>
                                            </small>
                                        </td>
                                        <td>
                                            <div class="progress" style="height: 20px;">
                                                <div class="progress-bar bg-<?php 
                                                    echo $matiere['moyenne_matiere'] >= 14 ? 'success' : 
                                                        ($matiere['moyenne_matiere'] >= 10 ? 'warning' : 'danger'); 
                                                ?>" 
                                                     style="width: <?php echo ($matiere['moyenne_matiere'] / 20) * 100; ?>%">
                                                    <?php echo round($matiere['moyenne_matiere'], 1); ?>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="text-center py-4">
                        <i class="fas fa-book fa-3x text-muted mb-3"></i>
                        <p class="text-muted">Aucune donnée de matière disponible</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <div class="col-lg-4">
        <!-- Meilleurs élèves -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-trophy me-2"></i>
                    Top 10 des élèves
                </h5>
            </div>
            <div class="card-body">
                <?php if (!empty($meilleurs_eleves)): ?>
                    <?php foreach ($meilleurs_eleves as $index => $eleve): ?>
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <div>
                                <span class="badge bg-<?php echo $index < 3 ? 'warning' : 'secondary'; ?> me-2">
                                    <?php echo $index + 1; ?>
                                    <?php if ($index === 0): ?>
                                        <i class="fas fa-crown ms-1"></i>
                                    <?php endif; ?>
                                </span>
                                <div class="d-inline-block">
                                    <strong><?php echo htmlspecialchars($eleve['nom'] . ' ' . $eleve['prenom']); ?></strong>
                                    <br><small class="text-muted">
                                        <?php echo htmlspecialchars($eleve['classe_nom']); ?>
                                    </small>
                                </div>
                            </div>
                            <span class="badge bg-<?php 
                                echo $eleve['moyenne_generale'] >= 16 ? 'success' : 
                                    ($eleve['moyenne_generale'] >= 14 ? 'info' : 'primary'); 
                            ?> fs-6">
                                <?php echo round($eleve['moyenne_generale'], 2); ?>/20
                            </span>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p class="text-muted text-center">Aucune donnée disponible</p>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Répartition des mentions -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-medal me-2"></i>
                    Répartition des mentions
                </h5>
            </div>
            <div class="card-body">
                <?php if (!empty($repartition_mentions)): ?>
                    <?php foreach ($repartition_mentions as $mention): ?>
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="badge bg-<?php 
                                echo $mention['mention'] === 'Excellent' ? 'success' : 
                                    ($mention['mention'] === 'Très bien' ? 'info' : 
                                    ($mention['mention'] === 'Bien' ? 'primary' : 
                                    ($mention['mention'] === 'Satisfaisant' ? 'warning' : 
                                    ($mention['mention'] === 'Passable' ? 'secondary' : 'danger')))); 
                            ?>">
                                <?php echo $mention['mention']; ?>
                            </span>
                            <strong><?php echo $mention['nombre']; ?></strong>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p class="text-muted text-center">Aucune donnée disponible</p>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Évolution trimestrielle -->
        <?php if (!empty($evolution_trimestres)): ?>
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-chart-line me-2"></i>
                    Évolution trimestrielle
                </h5>
            </div>
            <div class="card-body">
                <canvas id="evolutionChart" width="100%" height="200"></canvas>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php if (!empty($evolution_trimestres)): ?>
<script>
// Graphique d'évolution trimestrielle
const evolutionCtx = document.getElementById('evolutionChart').getContext('2d');
const evolutionChart = new Chart(evolutionCtx, {
    type: 'line',
    data: {
        labels: [<?php echo implode(',', array_map(function($t) { return "'" . str_replace('_', ' ', ucfirst($t['periode'])) . "'"; }, $evolution_trimestres)); ?>],
        datasets: [{
            label: 'Moyenne générale',
            data: [<?php echo implode(',', array_map(function($t) { return round($t['moyenne'], 2); }, $evolution_trimestres)); ?>],
            borderColor: '#007bff',
            backgroundColor: 'rgba(0, 123, 255, 0.1)',
            tension: 0.4,
            fill: true
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                display: false
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                max: 20,
                ticks: {
                    callback: function(value) {
                        return value + '/20';
                    }
                }
            }
        }
    }
});
</script>
<?php endif; ?>

<?php include '../../../includes/footer.php'; ?>
