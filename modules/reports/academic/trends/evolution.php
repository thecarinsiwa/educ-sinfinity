<?php
/**
 * Module Rapports Académiques - Évolution des tendances
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

$page_title = 'Évolution des tendances';
$current_year = getCurrentAcademicYear();

if (!$current_year) {
    showMessage('error', 'Aucune année scolaire active.');
    redirectTo('../../../index.php');
}

// Paramètres de filtrage
$classe_filter = (int)($_GET['classe'] ?? 0);
$matiere_filter = (int)($_GET['matiere'] ?? 0);
$niveau_filter = sanitizeInput($_GET['niveau'] ?? '');
$periode_debut = sanitizeInput($_GET['periode_debut'] ?? '');
$periode_fin = sanitizeInput($_GET['periode_fin'] ?? '');

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

// Construire les conditions WHERE pour les requêtes avec alias 'e' pour evaluations
$where_conditions_e = ["e.annee_scolaire_id = ?"];
$params_e = [$current_year['id']];

if ($matiere_filter) {
    $where_conditions_e[] = "e.matiere_id = ?";
    $params_e[] = $matiere_filter;
}

if ($periode_debut && $periode_fin) {
    $where_conditions_e[] = "e.periode BETWEEN ? AND ?";
    $params_e[] = $periode_debut;
    $params_e[] = $periode_fin;
}

$where_clause_e = implode(' AND ', $where_conditions_e);

// Construire les conditions WHERE pour les requêtes avec alias 'ev' pour evaluations
$where_conditions_ev = ["ev.annee_scolaire_id = ?"];
$params_ev = [$current_year['id']];

if ($classe_filter) {
    $where_conditions_ev[] = "i.classe_id = ?";
    $params_ev[] = $classe_filter;
}

if ($matiere_filter) {
    $where_conditions_ev[] = "ev.matiere_id = ?";
    $params_ev[] = $matiere_filter;
}

if ($niveau_filter) {
    $where_conditions_ev[] = "c.niveau = ?";
    $params_ev[] = $niveau_filter;
}

if ($periode_debut && $periode_fin) {
    $where_conditions_ev[] = "ev.periode BETWEEN ? AND ?";
    $params_ev[] = $periode_debut;
    $params_ev[] = $periode_fin;
}

$where_clause_ev = implode(' AND ', $where_conditions_ev);

// 1. Évolution des moyennes par période
$evolution_periodes = $database->query(
    "SELECT e.periode,
            COUNT(DISTINCT n.eleve_id) as nb_eleves_evalues,
            COUNT(n.id) as nb_notes,
            AVG(n.note) as moyenne_periode,
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
     FROM evaluations e
     JOIN notes n ON e.id = n.evaluation_id
     " . ($classe_filter ? "JOIN inscriptions i ON n.eleve_id = i.eleve_id JOIN classes c ON i.classe_id = c.id" : "") . "
     WHERE $where_clause_e
     GROUP BY e.periode
     ORDER BY 
        CASE e.periode 
            WHEN '1er trimestre' THEN 1 
            WHEN '2ème trimestre' THEN 2 
            WHEN '3ème trimestre' THEN 3 
        END",
    $params_e
)->fetchAll();

// 2. Évolution par classe
$evolution_classes = $database->query(
    "SELECT c.id, c.nom as classe_nom, c.niveau,
            ev.periode,
            COUNT(DISTINCT n.eleve_id) as nb_eleves_evalues,
            COUNT(n.id) as nb_notes,
            AVG(n.note) as moyenne_classe,
            COUNT(CASE WHEN n.note >= 10 THEN 1 END) * 100.0 / COUNT(n.id) as taux_reussite
     FROM classes c
     JOIN inscriptions i ON c.id = i.classe_id
     JOIN eleves e ON i.eleve_id = e.id
     JOIN notes n ON e.id = n.eleve_id
     JOIN evaluations ev ON n.evaluation_id = ev.id
     WHERE $where_clause_ev AND i.status = 'inscrit'
     GROUP BY c.id, c.nom, c.niveau, ev.periode
     ORDER BY c.nom, 
        CASE ev.periode 
            WHEN '1er trimestre' THEN 1 
            WHEN '2ème trimestre' THEN 2 
            WHEN '3ème trimestre' THEN 3 
        END",
    $params_ev
)->fetchAll();

// 3. Évolution par matière
$evolution_matieres = $database->query(
    "SELECT m.id, m.nom as matiere_nom, m.coefficient,
            e.periode,
            COUNT(n.id) as nb_notes,
            AVG(n.note) as moyenne_matiere,
            COUNT(CASE WHEN n.note >= 10 THEN 1 END) * 100.0 / COUNT(n.id) as taux_reussite
     FROM matieres m
     JOIN evaluations e ON m.id = e.matiere_id
     JOIN notes n ON e.id = n.evaluation_id
     WHERE $where_clause_e
     GROUP BY m.id, m.nom, m.coefficient, e.periode
     ORDER BY m.nom, 
        CASE e.periode 
            WHEN '1er trimestre' THEN 1 
            WHEN '2ème trimestre' THEN 2 
            WHEN '3ème trimestre' THEN 3 
        END",
    $params_e
)->fetchAll();

// 4. Tendances mensuelles (si données disponibles)
$tendances_mensuelles = $database->query(
    "SELECT DATE_FORMAT(e.date_evaluation, '%Y-%m') as mois,
            COUNT(DISTINCT n.eleve_id) as nb_eleves_evalues,
            COUNT(n.id) as nb_notes,
            AVG(n.note) as moyenne_mensuelle,
            COUNT(CASE WHEN n.note >= 10 THEN 1 END) * 100.0 / COUNT(n.id) as taux_reussite
     FROM evaluations e
     JOIN notes n ON e.id = n.evaluation_id
     " . ($classe_filter ? "JOIN inscriptions i ON n.eleve_id = i.eleve_id JOIN classes c ON i.classe_id = c.id" : "") . "
     WHERE $where_clause_e AND e.date_evaluation IS NOT NULL
     GROUP BY DATE_FORMAT(e.date_evaluation, '%Y-%m')
     ORDER BY mois",
    $params_e
)->fetchAll();

// 5. Analyse des progrès des élèves
$progres_eleves = $database->query(
    "SELECT e.id, e.nom, e.prenom, c.nom as classe_nom,
            COUNT(DISTINCT ev.periode) as nb_periodes,
            AVG(n.note) as moyenne_generale,
            MIN(n.note) as note_min,
            MAX(n.note) as note_max,
            (MAX(n.note) - MIN(n.note)) as progression_notes,
            COUNT(CASE WHEN n.note >= 10 THEN 1 END) * 100.0 / COUNT(n.id) as taux_reussite
     FROM eleves e
     JOIN inscriptions i ON e.id = i.eleve_id
     JOIN classes c ON i.classe_id = c.id
     JOIN notes n ON e.id = n.eleve_id
     JOIN evaluations ev ON n.evaluation_id = ev.id
     WHERE $where_clause_ev AND i.status = 'inscrit'
     GROUP BY e.id, e.nom, e.prenom, c.nom
     HAVING nb_periodes >= 2
     ORDER BY progression_notes DESC
     LIMIT 20",
    $params_ev
)->fetchAll();

include '../../../../includes/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">
        <i class="fas fa-chart-line me-2"></i>
        Évolution des tendances
    </h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <div class="btn-group me-2">
            <a href="../index.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left me-1"></i>
                Retour aux rapports
            </a>
        </div>
        <div class="btn-group">
            <a href="../export.php?type=evolution&format=excel<?php echo $classe_filter ? '&classe=' . $classe_filter : ''; ?>" class="btn btn-outline-success">
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
            Paramètres d'analyse
        </h5>
    </div>
    <div class="card-body">
        <form method="GET" action="" class="row g-3">
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
            
            <div class="col-md-2">
                <label for="periode_debut" class="form-label">Période début</label>
                <select class="form-select" id="periode_debut" name="periode_debut">
                    <option value="">Toutes</option>
                    <?php foreach ($periodes as $p): ?>
                        <option value="<?php echo htmlspecialchars($p['periode']); ?>" <?php echo $periode_debut === $p['periode'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($p['periode']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="col-md-2">
                <label for="periode_fin" class="form-label">Période fin</label>
                <select class="form-select" id="periode_fin" name="periode_fin">
                    <option value="">Toutes</option>
                    <?php foreach ($periodes as $p): ?>
                        <option value="<?php echo htmlspecialchars($p['periode']); ?>" <?php echo $periode_fin === $p['periode'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($p['periode']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="col-md-2 d-flex align-items-end">
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

<?php if (!empty($evolution_periodes)): ?>
    <!-- Évolution générale par période -->
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="mb-0">
                <i class="fas fa-chart-line me-2"></i>
                Évolution générale par période
            </h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Période</th>
                            <th>Élèves évalués</th>
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
                        <?php foreach ($evolution_periodes as $periode): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($periode['periode']); ?></strong></td>
                                <td><?php echo $periode['nb_eleves_evalues']; ?></td>
                                <td><?php echo $periode['nb_notes']; ?></td>
                                <td><span class="badge bg-primary"><?php echo round($periode['moyenne_periode'], 2); ?></span></td>
                                <td><?php echo $periode['note_min']; ?></td>
                                <td><?php echo $periode['note_max']; ?></td>
                                <td><?php echo round($periode['ecart_type'], 2); ?></td>
                                <td><span class="badge bg-success"><?php echo $periode['excellents']; ?></span></td>
                                <td><span class="badge bg-info"><?php echo $periode['tres_bien']; ?></span></td>
                                <td><span class="badge bg-primary"><?php echo $periode['bien']; ?></span></td>
                                <td><span class="badge bg-warning"><?php echo $periode['satisfaisant']; ?></span></td>
                                <td><span class="badge bg-secondary"><?php echo $periode['passable']; ?></span></td>
                                <td><span class="badge bg-danger"><?php echo $periode['insuffisant']; ?></span></td>
                                <td><span class="badge bg-<?php echo $periode['taux_reussite'] >= 80 ? 'success' : ($periode['taux_reussite'] >= 60 ? 'warning' : 'danger'); ?>"><?php echo round($periode['taux_reussite'], 1); ?>%</span></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Graphiques d'évolution -->
    <div class="row">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-chart-line me-2"></i>
                        Évolution des moyennes
                    </h5>
                </div>
                <div class="card-body">
                    <canvas id="evolutionMoyennesChart" width="400" height="200"></canvas>
                </div>
            </div>
        </div>
        
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-chart-area me-2"></i>
                        Évolution du taux de réussite
                    </h5>
                </div>
                <div class="card-body">
                    <canvas id="evolutionReussiteChart" width="400" height="200"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Évolution par classe -->
    <?php if (!empty($evolution_classes)): ?>
        <div class="card mt-4">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-users me-2"></i>
                    Évolution par classe
                </h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Classe</th>
                                <th>Niveau</th>
                                <th>Période</th>
                                <th>Élèves</th>
                                <th>Notes</th>
                                <th>Moyenne</th>
                                <th>Taux de réussite</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($evolution_classes as $classe): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($classe['classe_nom']); ?></strong></td>
                                    <td><?php echo ucfirst($classe['niveau']); ?></td>
                                    <td><?php echo htmlspecialchars($classe['periode']); ?></td>
                                    <td><?php echo $classe['nb_eleves_evalues']; ?></td>
                                    <td><?php echo $classe['nb_notes']; ?></td>
                                    <td><span class="badge bg-primary"><?php echo round($classe['moyenne_classe'], 2); ?></span></td>
                                    <td><span class="badge bg-<?php echo $classe['taux_reussite'] >= 80 ? 'success' : ($classe['taux_reussite'] >= 60 ? 'warning' : 'danger'); ?>"><?php echo round($classe['taux_reussite'], 1); ?>%</span></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Évolution par matière -->
    <?php if (!empty($evolution_matieres)): ?>
        <div class="card mt-4">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-book me-2"></i>
                    Évolution par matière
                </h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Matière</th>
                                <th>Coefficient</th>
                                <th>Période</th>
                                <th>Notes</th>
                                <th>Moyenne</th>
                                <th>Taux de réussite</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($evolution_matieres as $matiere): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($matiere['matiere_nom']); ?></strong></td>
                                    <td><?php echo $matiere['coefficient']; ?></td>
                                    <td><?php echo htmlspecialchars($matiere['periode']); ?></td>
                                    <td><?php echo $matiere['nb_notes']; ?></td>
                                    <td><span class="badge bg-primary"><?php echo round($matiere['moyenne_matiere'], 2); ?></span></td>
                                    <td><span class="badge bg-<?php echo $matiere['taux_reussite'] >= 80 ? 'success' : ($matiere['taux_reussite'] >= 60 ? 'warning' : 'danger'); ?>"><?php echo round($matiere['taux_reussite'], 1); ?>%</span></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Progrès des élèves -->
    <?php if (!empty($progres_eleves)): ?>
        <div class="card mt-4">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-trophy me-2"></i>
                    Top 20 des élèves avec la plus grande progression
                </h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Élève</th>
                                <th>Classe</th>
                                <th>Périodes</th>
                                <th>Moyenne générale</th>
                                <th>Note min</th>
                                <th>Note max</th>
                                <th>Progression</th>
                                <th>Taux de réussite</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($progres_eleves as $eleve): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($eleve['nom'] . ' ' . $eleve['prenom']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($eleve['classe_nom']); ?></td>
                                    <td><?php echo $eleve['nb_periodes']; ?></td>
                                    <td><span class="badge bg-primary"><?php echo round($eleve['moyenne_generale'], 2); ?></span></td>
                                    <td><?php echo $eleve['note_min']; ?></td>
                                    <td><?php echo $eleve['note_max']; ?></td>
                                    <td><span class="badge bg-success">+<?php echo $eleve['progression_notes']; ?></span></td>
                                    <td><span class="badge bg-<?php echo $eleve['taux_reussite'] >= 80 ? 'success' : ($eleve['taux_reussite'] >= 60 ? 'warning' : 'danger'); ?>"><?php echo round($eleve['taux_reussite'], 1); ?>%</span></td>
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
            // Graphique d'évolution des moyennes
            const ctx1 = document.getElementById('evolutionMoyennesChart').getContext('2d');
            new Chart(ctx1, {
                type: 'line',
                data: {
                    labels: [<?php echo implode(',', array_map(function($periode) { return '"' . addslashes($periode['periode']) . '"'; }, $evolution_periodes)); ?>],
                    datasets: [{
                        label: 'Moyenne',
                        data: [<?php echo implode(',', array_map(function($periode) { return $periode['moyenne_periode']; }, $evolution_periodes)); ?>],
                        borderColor: 'rgba(54, 162, 235, 1)',
                        backgroundColor: 'rgba(54, 162, 235, 0.2)',
                        borderWidth: 3,
                        fill: true,
                        tension: 0.1
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

            // Graphique d'évolution du taux de réussite
            const ctx2 = document.getElementById('evolutionReussiteChart').getContext('2d');
            new Chart(ctx2, {
                type: 'line',
                data: {
                    labels: [<?php echo implode(',', array_map(function($periode) { return '"' . addslashes($periode['periode']) . '"'; }, $evolution_periodes)); ?>],
                    datasets: [{
                        label: 'Taux de réussite (%)',
                        data: [<?php echo implode(',', array_map(function($periode) { return $periode['taux_reussite']; }, $evolution_periodes)); ?>],
                        borderColor: 'rgba(75, 192, 192, 1)',
                        backgroundColor: 'rgba(75, 192, 192, 0.2)',
                        borderWidth: 3,
                        fill: true,
                        tension: 0.1
                    }]
                },
                options: {
                    responsive: true,
                    scales: {
                        y: {
                            beginAtZero: true,
                            max: 100
                        }
                    }
                }
            });
        });
    </script>

<?php else: ?>
    <div class="alert alert-info">
        <h5><i class="fas fa-info-circle me-2"></i>Aucune donnée d'évolution disponible</h5>
        <p class="mb-0">
            Aucune donnée d'évolution trouvée pour les critères sélectionnés. 
            Veuillez ajuster les filtres ou vérifier qu'il y a suffisamment de données d'évaluation.
        </p>
    </div>
<?php endif; ?>

<?php include '../../../../includes/footer.php'; ?>
