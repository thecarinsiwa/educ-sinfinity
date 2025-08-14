<?php
/**
 * Module Admissions - Statistiques des admissions
 * Application de gestion scolaire - République Démocratique du Congo
 */

require_once '../../../../config/config.php';
require_once '../../../../config/database.php';
require_once '../../../../includes/functions.php';

// Vérifier l'authentification et les permissions
requireLogin();
if (!checkPermission('students') && !checkPermission('students_view')) {
    showMessage('error', 'Accès refusé à cette fonctionnalité.');
    redirectTo('../../../index.php');
}

$page_title = 'Statistiques des admissions';

// Récupérer l'année scolaire active
$current_year = getCurrentAcademicYear();

if (!$current_year) {
    showMessage('error', 'Aucune année scolaire active.');
    redirectTo('../../../index.php');
}

// Paramètres de filtrage
$periode_filter = sanitizeInput($_GET['periode'] ?? '');
$niveau_filter = sanitizeInput($_GET['niveau'] ?? '');
$classe_filter = (int)($_GET['classe'] ?? 0);
$date_debut = sanitizeInput($_GET['date_debut'] ?? '');
$date_fin = sanitizeInput($_GET['date_fin'] ?? '');

// Construire les conditions WHERE
$where_conditions = ["da.annee_scolaire_id = ?"];
$params = [$current_year['id']];

if ($periode_filter) {
    $where_conditions[] = "DATE_FORMAT(da.created_at, '%Y-%m') = ?";
    $params[] = $periode_filter;
}

if ($niveau_filter) {
    $where_conditions[] = "c.niveau = ?";
    $params[] = $niveau_filter;
}

if ($classe_filter) {
    $where_conditions[] = "da.classe_demandee_id = ?";
    $params[] = $classe_filter;
}

if ($date_debut) {
    $where_conditions[] = "DATE(da.created_at) >= ?";
    $params[] = $date_debut;
}

if ($date_fin) {
    $where_conditions[] = "DATE(da.created_at) <= ?";
    $params[] = $date_fin;
}

$where_clause = implode(' AND ', $where_conditions);

// Statistiques générales
$stats_generales = $database->query(
    "SELECT 
        COUNT(*) as total_demandes,
        COUNT(CASE WHEN status = 'en_attente' THEN 1 END) as en_attente,
        COUNT(CASE WHEN status = 'acceptee' THEN 1 END) as acceptees,
        COUNT(CASE WHEN status = 'refusee' THEN 1 END) as refusees,
        COUNT(CASE WHEN status = 'annulee' THEN 1 END) as annulees,
        ROUND(COUNT(CASE WHEN status = 'acceptee' THEN 1 END) * 100.0 / COUNT(*), 2) as taux_acceptation
     FROM demandes_admission da
     WHERE $where_clause",
    $params
)->fetch();

// Évolution mensuelle des demandes
$evolution_mensuelle = $database->query(
    "SELECT 
        DATE_FORMAT(created_at, '%Y-%m') as mois,
        COUNT(*) as total_demandes,
        COUNT(CASE WHEN status = 'acceptee' THEN 1 END) as acceptees,
        COUNT(CASE WHEN status = 'refusee' THEN 1 END) as refusees
     FROM demandes_admission da
     WHERE $where_clause
     GROUP BY DATE_FORMAT(created_at, '%Y-%m')
     ORDER BY mois",
    $params
)->fetchAll();

// Répartition par niveau
$repartition_niveau = $database->query(
    "SELECT 
        c.niveau,
        COUNT(*) as total_demandes,
        COUNT(CASE WHEN da.status = 'acceptee' THEN 1 END) as acceptees,
        COUNT(CASE WHEN da.status = 'refusee' THEN 1 END) as refusees,
        ROUND(COUNT(CASE WHEN da.status = 'acceptee' THEN 1 END) * 100.0 / COUNT(*), 2) as taux_acceptation
     FROM demandes_admission da
     LEFT JOIN classes c ON da.classe_demandee_id = c.id
     WHERE $where_clause
     GROUP BY c.niveau
     ORDER BY total_demandes DESC",
    $params
)->fetchAll();

// Répartition par classe
$repartition_classe = $database->query(
    "SELECT 
        c.nom as classe_nom,
        c.niveau,
        COUNT(*) as total_demandes,
        COUNT(CASE WHEN da.status = 'acceptee' THEN 1 END) as acceptees,
        COUNT(CASE WHEN da.status = 'refusee' THEN 1 END) as refusees,
        ROUND(COUNT(CASE WHEN da.status = 'acceptee' THEN 1 END) * 100.0 / COUNT(*), 2) as taux_acceptation
     FROM demandes_admission da
     LEFT JOIN classes c ON da.classe_demandee_id = c.id
     WHERE $where_clause
     GROUP BY c.id, c.nom, c.niveau
     ORDER BY total_demandes DESC
     LIMIT 15",
    $params
)->fetchAll();

// Répartition par sexe
$repartition_sexe = $database->query(
    "SELECT 
        da.sexe,
        COUNT(*) as total_demandes,
        COUNT(CASE WHEN da.status = 'acceptee' THEN 1 END) as acceptees,
        COUNT(CASE WHEN da.status = 'refusee' THEN 1 END) as refusees,
        ROUND(COUNT(CASE WHEN da.status = 'acceptee' THEN 1 END) * 100.0 / COUNT(*), 2) as taux_acceptation
     FROM demandes_admission da
     WHERE $where_clause
     GROUP BY da.sexe
     ORDER BY total_demandes DESC",
    $params
)->fetchAll();

// Motifs de refus
$motifs_refus = $database->query(
    "SELECT 
        da.decision_motif,
        COUNT(*) as nombre
     FROM demandes_admission da
     WHERE $where_clause AND da.status = 'refusee' AND da.decision_motif IS NOT NULL
     GROUP BY da.decision_motif
     ORDER BY nombre DESC
     LIMIT 10",
    $params
)->fetchAll();

// Temps de traitement moyen
$temps_traitement = $database->query(
    "SELECT 
        AVG(DATEDIFF(da.date_traitement, da.created_at)) as temps_moyen_jours,
        MIN(DATEDIFF(da.date_traitement, da.created_at)) as temps_min_jours,
        MAX(DATEDIFF(da.date_traitement, da.created_at)) as temps_max_jours
     FROM demandes_admission da
     WHERE $where_clause AND da.status IN ('acceptee', 'refusee') AND da.date_traitement IS NOT NULL",
    $params
)->fetch();

// Récupérer les classes pour le filtre
$classes = $database->query(
    "SELECT c.id, c.nom, c.niveau
     FROM classes c
     WHERE c.annee_scolaire_id = ?
     ORDER BY c.niveau, c.nom",
    [$current_year['id']]
)->fetchAll();

// Récupérer les périodes d'admission (utiliser la date de création comme période)
$periodes = $database->query(
    "SELECT DISTINCT DATE_FORMAT(created_at, '%Y-%m') as periode_admission 
     FROM demandes_admission 
     WHERE annee_scolaire_id = ?
     ORDER BY periode_admission",
    [$current_year['id']]
)->fetchAll();

include '../../../../includes/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">
        <i class="fas fa-chart-bar me-2"></i>
        Statistiques des admissions
    </h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <div class="btn-group me-2">
            <a href="../index.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left me-1"></i>
                Retour aux admissions
            </a>
        </div>
        <div class="btn-group">
            <a href="../exports/applications.php?format=excel" class="btn btn-outline-success">
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
                <label for="periode" class="form-label">Période</label>
                <select class="form-select" id="periode" name="periode">
                    <option value="">Toutes les périodes</option>
                    <?php foreach ($periodes as $p): ?>
                        <option value="<?php echo htmlspecialchars($p['periode_admission']); ?>" <?php echo $periode_filter === $p['periode_admission'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($p['periode_admission']); ?>
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
                <label for="date_debut" class="form-label">Date début</label>
                <input type="date" class="form-control" id="date_debut" name="date_debut" value="<?php echo $date_debut; ?>">
            </div>
            
            <div class="col-md-2">
                <label for="date_fin" class="form-label">Date fin</label>
                <input type="date" class="form-control" id="date_fin" name="date_fin" value="<?php echo $date_fin; ?>">
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

<!-- Statistiques générales -->
<div class="row mb-4">
    <div class="col-md-2">
        <div class="card text-center">
            <div class="card-body">
                <h3 class="text-primary"><?php echo $stats_generales['total_demandes']; ?></h3>
                <p class="card-text">Total demandes</p>
            </div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card text-center">
            <div class="card-body">
                <h3 class="text-warning"><?php echo $stats_generales['en_attente']; ?></h3>
                <p class="card-text">En attente</p>
            </div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card text-center">
            <div class="card-body">
                <h3 class="text-success"><?php echo $stats_generales['acceptees']; ?></h3>
                <p class="card-text">Acceptées</p>
            </div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card text-center">
            <div class="card-body">
                <h3 class="text-danger"><?php echo $stats_generales['refusees']; ?></h3>
                <p class="card-text">Refusées</p>
            </div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card text-center">
            <div class="card-body">
                <h3 class="text-info"><?php echo $stats_generales['taux_acceptation']; ?>%</h3>
                <p class="card-text">Taux d'acceptation</p>
            </div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card text-center">
            <div class="card-body">
                <h3 class="text-secondary"><?php echo round($temps_traitement['temps_moyen_jours'] ?? 0, 1); ?></h3>
                <p class="card-text">Jours de traitement</p>
            </div>
        </div>
    </div>
</div>

<!-- Graphiques -->
<div class="row">
    <div class="col-md-6">
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-chart-pie me-2"></i>
                    Répartition par statut
                </h5>
            </div>
            <div class="card-body">
                <canvas id="statutChart" width="400" height="200"></canvas>
            </div>
        </div>
    </div>
    
    <div class="col-md-6">
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-chart-line me-2"></i>
                    Évolution mensuelle
                </h5>
            </div>
            <div class="card-body">
                <canvas id="evolutionChart" width="400" height="200"></canvas>
            </div>
        </div>
    </div>
</div>

<!-- Répartition par niveau -->
<div class="row">
    <div class="col-md-8">
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-list me-2"></i>
                    Répartition par niveau
                </h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Niveau</th>
                                <th>Total demandes</th>
                                <th>Acceptées</th>
                                <th>Refusées</th>
                                <th>Taux d'acceptation</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($repartition_niveau as $niveau): ?>
                                <tr>
                                    <td><strong><?php echo ucfirst($niveau['niveau'] ?? 'Non spécifié'); ?></strong></td>
                                    <td><span class="badge bg-primary"><?php echo $niveau['total_demandes']; ?></span></td>
                                    <td><span class="badge bg-success"><?php echo $niveau['acceptees']; ?></span></td>
                                    <td><span class="badge bg-danger"><?php echo $niveau['refusees']; ?></span></td>
                                    <td><span class="badge bg-info"><?php echo $niveau['taux_acceptation']; ?>%</span></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-4">
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-chart-pie me-2"></i>
                    Répartition par sexe
                </h5>
            </div>
            <div class="card-body">
                <canvas id="sexeChart" width="400" height="200"></canvas>
            </div>
        </div>
    </div>
</div>

<!-- Top 15 des classes -->
<div class="card mb-4">
    <div class="card-header">
        <h5 class="mb-0">
            <i class="fas fa-trophy me-2"></i>
            Top 15 des classes les plus demandées
        </h5>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Classe</th>
                        <th>Niveau</th>
                        <th>Total demandes</th>
                        <th>Acceptées</th>
                        <th>Refusées</th>
                        <th>Taux d'acceptation</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($repartition_classe as $index => $classe): ?>
                        <tr>
                            <td><?php echo $index + 1; ?></td>
                            <td><strong><?php echo htmlspecialchars($classe['classe_nom'] ?? 'Non spécifiée'); ?></strong></td>
                            <td><?php echo ucfirst($classe['niveau'] ?? 'Non spécifié'); ?></td>
                            <td><span class="badge bg-primary"><?php echo $classe['total_demandes']; ?></span></td>
                            <td><span class="badge bg-success"><?php echo $classe['acceptees']; ?></span></td>
                            <td><span class="badge bg-danger"><?php echo $classe['refusees']; ?></span></td>
                            <td><span class="badge bg-info"><?php echo $classe['taux_acceptation']; ?>%</span></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Motifs de refus -->
<?php if (!empty($motifs_refus)): ?>
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="mb-0">
                <i class="fas fa-exclamation-triangle me-2"></i>
                Top 10 des motifs de refus
            </h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Motif de refus</th>
                            <th>Nombre</th>
                            <th>Pourcentage</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $total_refus = array_sum(array_column($motifs_refus, 'nombre'));
                        foreach ($motifs_refus as $index => $motif): 
                        ?>
                            <tr>
                                <td><?php echo $index + 1; ?></td>
                                                                    <td><strong><?php echo htmlspecialchars($motif['decision_motif']); ?></strong></td>
                                <td><span class="badge bg-danger"><?php echo $motif['nombre']; ?></span></td>
                                <td><?php echo round(($motif['nombre'] / $total_refus) * 100, 1); ?>%</td>
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
    // Graphique de répartition par statut
    const ctx1 = document.getElementById('statutChart').getContext('2d');
    new Chart(ctx1, {
        type: 'doughnut',
        data: {
            labels: ['En attente', 'Acceptées', 'Refusées', 'Annulées'],
            datasets: [{
                data: [
                    <?php echo $stats_generales['en_attente']; ?>,
                    <?php echo $stats_generales['acceptees']; ?>,
                    <?php echo $stats_generales['refusees']; ?>,
                    <?php echo $stats_generales['annulees']; ?>
                ],
                backgroundColor: [
                    'rgba(255, 193, 7, 0.8)',
                    'rgba(40, 167, 69, 0.8)',
                    'rgba(220, 53, 69, 0.8)',
                    'rgba(108, 117, 125, 0.8)'
                ],
                borderWidth: 2,
                borderColor: '#fff'
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

    // Graphique d'évolution mensuelle
    const ctx2 = document.getElementById('evolutionChart').getContext('2d');
    new Chart(ctx2, {
        type: 'line',
        data: {
            labels: [<?php echo implode(',', array_map(function($item) { return '"' . addslashes($item['mois']) . '"'; }, $evolution_mensuelle)); ?>],
            datasets: [{
                label: 'Total demandes',
                data: [<?php echo implode(',', array_map(function($item) { return $item['total_demandes']; }, $evolution_mensuelle)); ?>],
                borderColor: 'rgba(54, 162, 235, 1)',
                backgroundColor: 'rgba(54, 162, 235, 0.2)',
                borderWidth: 3,
                fill: true,
                tension: 0.1
            }, {
                label: 'Acceptées',
                data: [<?php echo implode(',', array_map(function($item) { return $item['acceptees']; }, $evolution_mensuelle)); ?>],
                borderColor: 'rgba(40, 167, 69, 1)',
                backgroundColor: 'rgba(40, 167, 69, 0.2)',
                borderWidth: 3,
                fill: false,
                tension: 0.1
            }]
        },
        options: {
            responsive: true,
            scales: {
                y: {
                    beginAtZero: true
                }
            }
        }
    });

    // Graphique de répartition par sexe
    const ctx3 = document.getElementById('sexeChart').getContext('2d');
    new Chart(ctx3, {
        type: 'pie',
        data: {
                         labels: [<?php echo implode(',', array_map(function($item) { return '"' . addslashes($item['sexe'] ?? 'Non spécifié') . '"'; }, $repartition_sexe)); ?>],
            datasets: [{
                data: [<?php echo implode(',', array_map(function($item) { return $item['total_demandes']; }, $repartition_sexe)); ?>],
                backgroundColor: [
                    'rgba(54, 162, 235, 0.8)',
                    'rgba(255, 99, 132, 0.8)',
                    'rgba(255, 205, 86, 0.8)'
                ],
                borderWidth: 2,
                borderColor: '#fff'
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

<?php include '../../../../includes/footer.php'; ?>
