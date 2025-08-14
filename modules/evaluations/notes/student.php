<?php
/**
 * Module d'évaluations - Notes détaillées d'un étudiant
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

// Récupérer l'ID de l'étudiant
$eleve_id = (int)($_GET['eleve_id'] ?? 0);
if (!$eleve_id) {
    showMessage('error', 'ID d\'étudiant manquant.');
    redirectTo('index.php');
}

// Récupérer l'année scolaire active
$current_year = getCurrentAcademicYear();
if (!$current_year) {
    showMessage('error', 'Aucune année scolaire active.');
    redirectTo('../../../index.php');
}

// Récupérer les informations de l'étudiant
$eleve = $database->query(
    "SELECT e.*, 
            i.date_inscription, i.status as statut_inscription,
            c.nom as classe_nom, c.niveau, c.section
     FROM eleves e
     LEFT JOIN inscriptions i ON e.id = i.eleve_id AND i.annee_scolaire_id = ?
     LEFT JOIN classes c ON i.classe_id = c.id
     WHERE e.id = ?",
    [$current_year['id'], $eleve_id]
)->fetch();

if (!$eleve) {
    showMessage('error', 'Étudiant non trouvé.');
    redirectTo('index.php');
}

$page_title = 'Notes de ' . $eleve['nom'] . ' ' . $eleve['prenom'];

// Paramètres de filtrage
$matiere_filter = (int)($_GET['matiere_id'] ?? 0);
$periode_filter = sanitizeInput($_GET['periode'] ?? '');
$type_filter = sanitizeInput($_GET['type'] ?? '');

// Récupérer les matières pour le filtre
$matieres = $database->query(
    "SELECT DISTINCT m.* 
     FROM matieres m
     JOIN evaluations e ON m.id = e.matiere_id
     JOIN notes n ON e.id = n.evaluation_id
     WHERE n.eleve_id = ? AND e.annee_scolaire_id = ?
     ORDER BY m.nom",
    [$eleve_id, $current_year['id']]
)->fetchAll();

// Construire la requête avec filtres
$where_conditions = ["n.eleve_id = ?", "e.annee_scolaire_id = ?"];
$params = [$eleve_id, $current_year['id']];

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

$where_clause = implode(' AND ', $where_conditions);

// Récupérer toutes les notes de l'étudiant
$notes = $database->query(
    "SELECT n.*,
            e.nom as evaluation_nom, e.type_evaluation, e.date_evaluation, e.periode,
            m.id as matiere_id, m.nom as matiere_nom, m.coefficient as matiere_coefficient,
            CONCAT(p.nom, ' ', p.prenom) as enseignant_nom
     FROM notes n
     JOIN evaluations e ON n.evaluation_id = e.id
     JOIN matieres m ON e.matiere_id = m.id
     LEFT JOIN personnel p ON e.enseignant_id = p.id
     WHERE $where_clause
     ORDER BY e.date_evaluation DESC, m.nom",
    $params
)->fetchAll();

// Calculer les statistiques par matière
$stats_par_matiere = [];
$total_notes = 0;
$total_points = 0;
$total_coefficient = 0;

foreach ($notes as $note) {
    $matiere_id = $note['matiere_id'];
    if (!isset($stats_par_matiere[$matiere_id])) {
        $stats_par_matiere[$matiere_id] = [
            'matiere_nom' => $note['matiere_nom'],
            'coefficient' => $note['matiere_coefficient'],
            'notes' => [],
            'moyenne' => 0,
            'total_points' => 0,
            'nb_notes' => 0
        ];
    }
    
    $stats_par_matiere[$matiere_id]['notes'][] = $note;
    $stats_par_matiere[$matiere_id]['total_points'] += $note['note'];
    $stats_par_matiere[$matiere_id]['nb_notes']++;
    
    $total_notes++;
    $total_points += $note['note'] * $note['matiere_coefficient'];
    $total_coefficient += $note['matiere_coefficient'];
}

// Calculer les moyennes par matière
foreach ($stats_par_matiere as &$stats) {
    if ($stats['nb_notes'] > 0) {
        $stats['moyenne'] = round($stats['total_points'] / $stats['nb_notes'], 2);
    }
}

// Calculer la moyenne générale
$moyenne_generale = $total_coefficient > 0 ? round($total_points / $total_coefficient, 2) : 0;

// Calculer le statut académique
$statut_academique = 'Non évalué';
$statut_color = 'secondary';
if ($moyenne_generale > 0) {
    if ($moyenne_generale >= 16) {
        $statut_academique = 'Excellent';
        $statut_color = 'success';
    } elseif ($moyenne_generale >= 14) {
        $statut_academique = 'Très bien';
        $statut_color = 'info';
    } elseif ($moyenne_generale >= 12) {
        $statut_academique = 'Bien';
        $statut_color = 'primary';
    } elseif ($moyenne_generale >= 10) {
        $statut_academique = 'Satisfaisant';
        $statut_color = 'warning';
    } else {
        $statut_academique = 'Insuffisant';
        $statut_color = 'danger';
    }
}

include '../../../includes/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">
        <i class="fas fa-chart-line me-2"></i>
        Notes de <?php echo htmlspecialchars($eleve['nom'] . ' ' . $eleve['prenom']); ?>
    </h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <div class="btn-group me-2">
            <a href="index.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left me-1"></i>
                Retour aux notes
            </a>
        </div>
        <div class="btn-group">
            <a href="../../students/view.php?id=<?php echo $eleve_id; ?>" class="btn btn-outline-primary">
                <i class="fas fa-user me-1"></i>
                Profil étudiant
            </a>
        </div>
    </div>
</div>

<!-- Informations de l'étudiant -->
<div class="row mb-4">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-user-graduate me-2"></i>
                    Informations de l'étudiant
                </h5>
            </div>
            <div class="card-body">
                <table class="table table-borderless">
                    <tr>
                        <td class="fw-bold">Nom complet :</td>
                        <td><?php echo htmlspecialchars($eleve['nom'] . ' ' . $eleve['prenom']); ?></td>
                    </tr>
                    <tr>
                        <td class="fw-bold">Matricule :</td>
                        <td><?php echo htmlspecialchars($eleve['numero_matricule']); ?></td>
                    </tr>
                    <tr>
                        <td class="fw-bold">Classe :</td>
                        <td>
                            <?php if ($eleve['classe_nom']): ?>
                                <span class="badge bg-primary"><?php echo htmlspecialchars($eleve['classe_nom']); ?></span>
                                <br><small class="text-muted"><?php echo ucfirst($eleve['niveau']); ?> - <?php echo htmlspecialchars($eleve['section'] ?? ''); ?></small>
                            <?php else: ?>
                                <span class="text-muted">Non inscrit</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <td class="fw-bold">Année scolaire :</td>
                        <td><?php echo htmlspecialchars($current_year['annee']); ?></td>
                    </tr>
                </table>
            </div>
        </div>
    </div>
    
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-chart-bar me-2"></i>
                    Statistiques générales
                </h5>
            </div>
            <div class="card-body">
                <div class="row text-center">
                    <div class="col-4">
                        <div class="h3 text-primary"><?php echo $total_notes; ?></div>
                        <small class="text-muted">Notes totales</small>
                    </div>
                    <div class="col-4">
                        <div class="h3 text-success"><?php echo count($stats_par_matiere); ?></div>
                        <small class="text-muted">Matières</small>
                    </div>
                    <div class="col-4">
                        <div class="h3 text-<?php echo $statut_color; ?>"><?php echo $moyenne_generale; ?></div>
                        <small class="text-muted">Moyenne générale</small>
                    </div>
                </div>
                <div class="text-center mt-3">
                    <span class="badge bg-<?php echo $statut_color; ?> fs-6">
                        <?php echo $statut_academique; ?>
                    </span>
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
        <form method="GET" action="" class="row g-3">
            <input type="hidden" name="eleve_id" value="<?php echo $eleve_id; ?>">
            
            <div class="col-md-3">
                <label for="matiere_id" class="form-label">Matière</label>
                <select class="form-select" id="matiere_id" name="matiere_id">
                    <option value="">Toutes les matières</option>
                    <?php foreach ($matieres as $matiere): ?>
                        <option value="<?php echo $matiere['id']; ?>" <?php echo $matiere_filter == $matiere['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($matiere['nom']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="col-md-3">
                <label for="periode" class="form-label">Période</label>
                <select class="form-select" id="periode" name="periode">
                    <option value="">Toutes les périodes</option>
                    <option value="1er trimestre" <?php echo $periode_filter === '1er trimestre' ? 'selected' : ''; ?>>1er trimestre</option>
                    <option value="2ème trimestre" <?php echo $periode_filter === '2ème trimestre' ? 'selected' : ''; ?>>2ème trimestre</option>
                    <option value="3ème trimestre" <?php echo $periode_filter === '3ème trimestre' ? 'selected' : ''; ?>>3ème trimestre</option>
                </select>
            </div>
            
            <div class="col-md-3">
                <label for="type" class="form-label">Type d'évaluation</label>
                <select class="form-select" id="type" name="type">
                    <option value="">Tous les types</option>
                    <option value="devoir" <?php echo $type_filter === 'devoir' ? 'selected' : ''; ?>>Devoir</option>
                    <option value="composition" <?php echo $type_filter === 'composition' ? 'selected' : ''; ?>>Composition</option>
                    <option value="examen" <?php echo $type_filter === 'examen' ? 'selected' : ''; ?>>Examen</option>
                    <option value="interrogation" <?php echo $type_filter === 'interrogation' ? 'selected' : ''; ?>>Interrogation</option>
                </select>
            </div>
            
            <div class="col-md-3 d-flex align-items-end">
                <button type="submit" class="btn btn-primary me-2">
                    <i class="fas fa-search me-1"></i>
                    Filtrer
                </button>
                <a href="?eleve_id=<?php echo $eleve_id; ?>" class="btn btn-outline-secondary">
                    <i class="fas fa-times me-1"></i>
                    Réinitialiser
                </a>
            </div>
        </form>
    </div>
</div>

<!-- Notes par matière -->
<?php if (!empty($stats_par_matiere)): ?>
    <?php foreach ($stats_par_matiere as $matiere_id => $stats): ?>
        <div class="card mb-4">
            <div class="card-header">
                <div class="d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        <i class="fas fa-book me-2"></i>
                        <?php echo htmlspecialchars($stats['matiere_nom']); ?>
                        <span class="badge bg-info ms-2">Coefficient: <?php echo $stats['coefficient']; ?></span>
                    </h5>
                    <div>
                        <span class="badge bg-<?php echo $stats['moyenne'] >= 10 ? 'success' : 'danger'; ?> fs-6">
                            Moyenne: <?php echo $stats['moyenne']; ?>/20
                        </span>
                    </div>
                </div>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Évaluation</th>
                                <th>Type</th>
                                <th>Période</th>
                                <th>Note</th>
                                <th>Observation</th>
                                <th>Enseignant</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($stats['notes'] as $note): ?>
                                <tr>
                                    <td><?php echo formatDate($note['date_evaluation']); ?></td>
                                    <td><?php echo htmlspecialchars($note['evaluation_nom']); ?></td>
                                    <td>
                                        <span class="badge bg-secondary">
                                            <?php echo ucfirst($note['type_evaluation']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo htmlspecialchars($note['periode']); ?></td>
                                    <td>
                                        <span class="badge bg-<?php echo $note['note'] >= 10 ? 'success' : 'danger'; ?> fs-6">
                                            <?php echo $note['note']; ?>/20
                                        </span>
                                    </td>
                                    <td>
                                        <small><?php echo htmlspecialchars($note['observation'] ?: '-'); ?></small>
                                    </td>
                                    <td>
                                        <small><?php echo htmlspecialchars($note['enseignant_nom'] ?: '-'); ?></small>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
<?php else: ?>
    <div class="card">
        <div class="card-body text-center">
            <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
            <h5>Aucune note trouvée</h5>
            <p class="text-muted">Cet étudiant n'a pas encore de notes enregistrées pour cette période.</p>
        </div>
    </div>
<?php endif; ?>

<!-- Graphique des performances -->
<?php if (!empty($stats_par_matiere)): ?>
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">
                <i class="fas fa-chart-bar me-2"></i>
                Graphique des performances par matière
            </h5>
        </div>
        <div class="card-body">
            <canvas id="performanceChart" width="400" height="200"></canvas>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const ctx = document.getElementById('performanceChart').getContext('2d');
            
            const data = {
                labels: [<?php echo implode(',', array_map(function($stats) { return '"' . addslashes($stats['matiere_nom']) . '"'; }, $stats_par_matiere)); ?>],
                datasets: [{
                    label: 'Moyenne par matière',
                    data: [<?php echo implode(',', array_map(function($stats) { return $stats['moyenne']; }, $stats_par_matiere)); ?>],
                    backgroundColor: [
                        'rgba(255, 99, 132, 0.2)',
                        'rgba(54, 162, 235, 0.2)',
                        'rgba(255, 206, 86, 0.2)',
                        'rgba(75, 192, 192, 0.2)',
                        'rgba(153, 102, 255, 0.2)',
                        'rgba(255, 159, 64, 0.2)'
                    ],
                    borderColor: [
                        'rgba(255, 99, 132, 1)',
                        'rgba(54, 162, 235, 1)',
                        'rgba(255, 206, 86, 1)',
                        'rgba(75, 192, 192, 1)',
                        'rgba(153, 102, 255, 1)',
                        'rgba(255, 159, 64, 1)'
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
                            beginAtZero: true,
                            max: 20,
                            ticks: {
                                stepSize: 2
                            }
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

<?php include '../../../includes/footer.php'; ?>
