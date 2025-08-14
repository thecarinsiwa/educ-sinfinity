<?php
/**
 * Module Rapports Administratifs - Page principale
 * Application de gestion scolaire - République Démocratique du Congo
 */

require_once '../../../config/config.php';
require_once '../../../config/database.php';
require_once '../../../includes/functions.php';

// Vérifier l'authentification
requireLogin();

$page_title = 'Rapports Administratifs';

// Obtenir l'année scolaire actuelle
$current_year = getCurrentAcademicYear();

// Statistiques administratives
$stats = [];

// Effectifs élèves
$stmt = $database->query(
    "SELECT COUNT(*) as total FROM inscriptions WHERE status = 'inscrit' AND annee_scolaire_id = ?",
    [$current_year['id'] ?? 0]
);
$stats['total_eleves'] = $stmt->fetch()['total'];

// Personnel actif
$stmt = $database->query("SELECT COUNT(*) as total FROM personnel WHERE status = 'actif'");
$stats['total_personnel'] = $stmt->fetch()['total'];

// Classes actives
$stmt = $database->query(
    "SELECT COUNT(*) as total FROM classes WHERE annee_scolaire_id = ?",
    [$current_year['id'] ?? 0]
);
$stats['total_classes'] = $stmt->fetch()['total'];

// Taux d'occupation moyen
$stmt = $database->query(
    "SELECT AVG(effectif_actuel * 100.0 / capacite_max) as taux
     FROM classes 
     WHERE annee_scolaire_id = ? AND capacite_max > 0",
    [$current_year['id'] ?? 0]
);
$stats['taux_occupation'] = round($stmt->fetch()['taux'] ?? 0, 1);

// Évolution des inscriptions par mois
$inscriptions_mensuelles = $database->query(
    "SELECT DATE_FORMAT(created_at, '%Y-%m') as mois, COUNT(*) as nombre
     FROM inscriptions 
     WHERE YEAR(created_at) = YEAR(CURDATE()) AND annee_scolaire_id = ?
     GROUP BY DATE_FORMAT(created_at, '%Y-%m')
     ORDER BY mois",
    [$current_year['id'] ?? 0]
)->fetchAll();

// Répartition par niveau et genre
$repartition_niveau_genre = $database->query(
    "SELECT c.niveau, e.sexe, COUNT(*) as nombre
     FROM eleves e
     JOIN inscriptions i ON e.id = i.eleve_id
     JOIN classes c ON i.classe_id = c.id
     WHERE i.status = 'inscrit' AND i.annee_scolaire_id = ?
     GROUP BY c.niveau, e.sexe
     ORDER BY c.niveau, e.sexe",
    [$current_year['id'] ?? 0]
)->fetchAll();

// Effectifs par classe
$effectifs_classes = $database->query(
    "SELECT c.nom as classe_nom, c.niveau, c.capacite_max,
            COUNT(i.id) as effectif_actuel,
            ROUND(COUNT(i.id) * 100.0 / c.capacite_max, 1) as taux_occupation
     FROM classes c
     LEFT JOIN inscriptions i ON c.id = i.classe_id AND i.status = 'inscrit'
     WHERE c.annee_scolaire_id = ?
     GROUP BY c.id
     ORDER BY c.niveau, c.nom",
    [$current_year['id'] ?? 0]
)->fetchAll();

// Personnel par fonction
$personnel_par_fonction = $database->query(
    "SELECT fonction, COUNT(*) as nombre
     FROM personnel 
     WHERE status = 'actif'
     GROUP BY fonction
     ORDER BY nombre DESC"
)->fetchAll();

// Âge moyen des élèves par niveau
$age_moyen_niveaux = $database->query(
    "SELECT c.niveau, 
            AVG(YEAR(CURDATE()) - YEAR(e.date_naissance)) as age_moyen,
            MIN(YEAR(CURDATE()) - YEAR(e.date_naissance)) as age_min,
            MAX(YEAR(CURDATE()) - YEAR(e.date_naissance)) as age_max
     FROM eleves e
     JOIN inscriptions i ON e.id = i.eleve_id
     JOIN classes c ON i.classe_id = c.id
     WHERE i.status = 'inscrit' AND i.annee_scolaire_id = ? AND e.date_naissance IS NOT NULL
     GROUP BY c.niveau
     ORDER BY 
        CASE c.niveau 
            WHEN 'maternelle' THEN 1 
            WHEN 'primaire' THEN 2 
            WHEN 'secondaire' THEN 3 
        END",
    [$current_year['id'] ?? 0]
)->fetchAll();

// Nouvelles inscriptions (30 derniers jours)
$nouvelles_inscriptions = $database->query(
    "SELECT e.nom, e.prenom, e.numero_matricule, c.nom as classe_nom, c.niveau,
            i.created_at as date_inscription
     FROM inscriptions i
     JOIN eleves e ON i.eleve_id = e.id
     JOIN classes c ON i.classe_id = c.id
     WHERE i.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
     AND i.annee_scolaire_id = ?
     ORDER BY i.created_at DESC
     LIMIT 15",
    [$current_year['id'] ?? 0]
)->fetchAll();

// Classes avec le plus fort taux d'occupation
$classes_saturees = $database->query(
    "SELECT c.nom as classe_nom, c.niveau, c.capacite_max,
            COUNT(i.id) as effectif_actuel,
            ROUND(COUNT(i.id) * 100.0 / c.capacite_max, 1) as taux_occupation
     FROM classes c
     LEFT JOIN inscriptions i ON c.id = i.classe_id AND i.status = 'inscrit'
     WHERE c.annee_scolaire_id = ? AND c.capacite_max > 0
     GROUP BY c.id
     HAVING taux_occupation >= 80
     ORDER BY taux_occupation DESC
     LIMIT 10",
    [$current_year['id'] ?? 0]
)->fetchAll();

// Statistiques par tranche d'âge
$tranches_age = $database->query(
    "SELECT 
        CASE 
            WHEN YEAR(CURDATE()) - YEAR(e.date_naissance) < 6 THEN 'Moins de 6 ans'
            WHEN YEAR(CURDATE()) - YEAR(e.date_naissance) BETWEEN 6 AND 10 THEN '6-10 ans'
            WHEN YEAR(CURDATE()) - YEAR(e.date_naissance) BETWEEN 11 AND 15 THEN '11-15 ans'
            WHEN YEAR(CURDATE()) - YEAR(e.date_naissance) BETWEEN 16 AND 18 THEN '16-18 ans'
            ELSE 'Plus de 18 ans'
        END as tranche_age,
        COUNT(*) as nombre
     FROM eleves e
     JOIN inscriptions i ON e.id = i.eleve_id
     WHERE i.status = 'inscrit' AND i.annee_scolaire_id = ? AND e.date_naissance IS NOT NULL
     GROUP BY tranche_age
     ORDER BY 
        CASE tranche_age
            WHEN 'Moins de 6 ans' THEN 1
            WHEN '6-10 ans' THEN 2
            WHEN '11-15 ans' THEN 3
            WHEN '16-18 ans' THEN 4
            ELSE 5
        END",
    [$current_year['id'] ?? 0]
)->fetchAll();

include '../../../includes/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">
        <i class="fas fa-users me-2"></i>
        Rapports Administratifs
    </h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <div class="btn-group me-2">
            <a href="../index.php" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-1"></i>
                Retour
            </a>
        </div>
        <div class="btn-group me-2">
            <button type="button" class="btn btn-info dropdown-toggle" data-bs-toggle="dropdown">
                <i class="fas fa-file-export me-1"></i>
                Exporter
            </button>
            <ul class="dropdown-menu">
                <li><a class="dropdown-item" href="export.php?type=administrative&format=pdf">
                    <i class="fas fa-file-pdf me-2"></i>Rapport PDF
                </a></li>
                <li><a class="dropdown-item" href="export.php?type=administrative&format=excel">
                    <i class="fas fa-file-excel me-2"></i>Données Excel
                </a></li>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item" href="lists/students.php">
                    <i class="fas fa-list me-2"></i>Liste des élèves
                </a></li>
                <li><a class="dropdown-item" href="lists/staff.php">
                    <i class="fas fa-list me-2"></i>Liste du personnel
                </a></li>
            </ul>
        </div>
        <div class="btn-group">
            <button type="button" class="btn btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">
                <i class="fas fa-tools me-1"></i>
                Analyses
            </button>
            <ul class="dropdown-menu">
                <li><a class="dropdown-item" href="analysis/demographics.php">
                    <i class="fas fa-chart-pie me-2"></i>Analyse démographique
                </a></li>
                <li><a class="dropdown-item" href="analysis/capacity.php">
                    <i class="fas fa-chart-bar me-2"></i>Analyse des capacités
                </a></li>
                <li><a class="dropdown-item" href="analysis/trends.php">
                    <i class="fas fa-chart-line me-2"></i>Tendances d'inscription
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
                        <h4><?php echo $stats['total_eleves']; ?></h4>
                        <p class="mb-0">Élèves inscrits</p>
                    </div>
                    <div class="align-self-center">
                        <i class="fas fa-users fa-2x"></i>
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
                        <h4><?php echo $stats['total_personnel']; ?></h4>
                        <p class="mb-0">Personnel actif</p>
                    </div>
                    <div class="align-self-center">
                        <i class="fas fa-chalkboard-teacher fa-2x"></i>
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
                        <h4><?php echo $stats['total_classes']; ?></h4>
                        <p class="mb-0">Classes actives</p>
                    </div>
                    <div class="align-self-center">
                        <i class="fas fa-school fa-2x"></i>
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
                        <h4><?php echo $stats['taux_occupation']; ?>%</h4>
                        <p class="mb-0">Taux d'occupation</p>
                    </div>
                    <div class="align-self-center">
                        <i class="fas fa-chart-pie fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Graphiques principaux -->
<div class="row mb-4">
    <div class="col-lg-8">
        <!-- Évolution des inscriptions -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-chart-line me-2"></i>
                    Évolution des inscriptions (année en cours)
                </h5>
            </div>
            <div class="card-body">
                <?php if (!empty($inscriptions_mensuelles)): ?>
                    <canvas id="inscriptionsChart" width="100%" height="300"></canvas>
                <?php else: ?>
                    <div class="text-center py-4">
                        <i class="fas fa-chart-line fa-3x text-muted mb-3"></i>
                        <p class="text-muted">Aucune donnée d'inscription disponible</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <div class="col-lg-4">
        <!-- Répartition par niveau -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-chart-pie me-2"></i>
                    Répartition par niveau
                </h5>
            </div>
            <div class="card-body">
                <?php if (!empty($repartition_niveau_genre)): ?>
                    <canvas id="niveauxChart" width="100%" height="300"></canvas>
                <?php else: ?>
                    <div class="text-center py-4">
                        <i class="fas fa-chart-pie fa-3x text-muted mb-3"></i>
                        <p class="text-muted">Aucune donnée disponible</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Tableaux détaillés -->
<div class="row">
    <div class="col-lg-8">
        <!-- Effectifs par classe -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-list me-2"></i>
                    Effectifs par classe
                </h5>
            </div>
            <div class="card-body">
                <?php if (!empty($effectifs_classes)): ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Classe</th>
                                    <th>Niveau</th>
                                    <th>Effectif</th>
                                    <th>Capacité</th>
                                    <th>Taux d'occupation</th>
                                    <th>Statut</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($effectifs_classes as $classe): ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($classe['classe_nom']); ?></strong></td>
                                        <td>
                                            <span class="badge bg-<?php 
                                                echo $classe['niveau'] === 'maternelle' ? 'warning' : 
                                                    ($classe['niveau'] === 'primaire' ? 'success' : 'primary'); 
                                            ?>">
                                                <?php echo ucfirst($classe['niveau']); ?>
                                            </span>
                                        </td>
                                        <td class="text-center"><?php echo $classe['effectif_actuel']; ?></td>
                                        <td class="text-center"><?php echo $classe['capacite_max']; ?></td>
                                        <td class="text-center">
                                            <?php if ($classe['capacite_max'] > 0): ?>
                                                <span class="badge bg-<?php 
                                                    echo $classe['taux_occupation'] >= 90 ? 'danger' : 
                                                        ($classe['taux_occupation'] >= 80 ? 'warning' : 'success'); 
                                                ?>">
                                                    <?php echo $classe['taux_occupation']; ?>%
                                                </span>
                                            <?php else: ?>
                                                <span class="text-muted">N/A</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($classe['capacite_max'] > 0): ?>
                                                <?php if ($classe['taux_occupation'] >= 100): ?>
                                                    <span class="badge bg-danger">Saturée</span>
                                                <?php elseif ($classe['taux_occupation'] >= 90): ?>
                                                    <span class="badge bg-warning">Presque pleine</span>
                                                <?php elseif ($classe['taux_occupation'] >= 50): ?>
                                                    <span class="badge bg-success">Normale</span>
                                                <?php else: ?>
                                                    <span class="badge bg-info">Disponible</span>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <span class="badge bg-secondary">Non définie</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p class="text-muted text-center">Aucune classe configurée</p>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Nouvelles inscriptions -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-user-plus me-2"></i>
                    Nouvelles inscriptions (30 derniers jours)
                </h5>
            </div>
            <div class="card-body">
                <?php if (!empty($nouvelles_inscriptions)): ?>
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Élève</th>
                                    <th>Matricule</th>
                                    <th>Classe</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($nouvelles_inscriptions as $inscription): ?>
                                    <tr>
                                        <td><?php echo formatDate($inscription['date_inscription']); ?></td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($inscription['nom'] . ' ' . $inscription['prenom']); ?></strong>
                                        </td>
                                        <td>
                                            <small class="text-muted"><?php echo htmlspecialchars($inscription['numero_matricule']); ?></small>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?php 
                                                echo $inscription['niveau'] === 'maternelle' ? 'warning' : 
                                                    ($inscription['niveau'] === 'primaire' ? 'success' : 'primary'); 
                                            ?>">
                                                <?php echo htmlspecialchars($inscription['classe_nom']); ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="text-center py-4">
                        <i class="fas fa-user-plus fa-3x text-muted mb-3"></i>
                        <p class="text-muted">Aucune nouvelle inscription ce mois</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <div class="col-lg-4">
        <!-- Personnel par fonction -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-users-cog me-2"></i>
                    Personnel par fonction
                </h5>
            </div>
            <div class="card-body">
                <?php if (!empty($personnel_par_fonction)): ?>
                    <?php foreach ($personnel_par_fonction as $fonction): ?>
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <span><?php echo ucfirst($fonction['fonction']); ?></span>
                            <span class="badge bg-primary"><?php echo $fonction['nombre']; ?></span>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p class="text-muted text-center">Aucun personnel enregistré</p>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Âge moyen par niveau -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-birthday-cake me-2"></i>
                    Âge moyen par niveau
                </h5>
            </div>
            <div class="card-body">
                <?php if (!empty($age_moyen_niveaux)): ?>
                    <?php foreach ($age_moyen_niveaux as $niveau): ?>
                        <div class="mb-3">
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="badge bg-<?php 
                                    echo $niveau['niveau'] === 'maternelle' ? 'warning' : 
                                        ($niveau['niveau'] === 'primaire' ? 'success' : 'primary'); 
                                ?>">
                                    <?php echo ucfirst($niveau['niveau']); ?>
                                </span>
                                <strong><?php echo round($niveau['age_moyen'], 1); ?> ans</strong>
                            </div>
                            <small class="text-muted">
                                Âge : <?php echo $niveau['age_min']; ?> - <?php echo $niveau['age_max']; ?> ans
                            </small>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p class="text-muted text-center">Aucune donnée d'âge disponible</p>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Tranches d'âge -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-chart-bar me-2"></i>
                    Répartition par âge
                </h5>
            </div>
            <div class="card-body">
                <?php if (!empty($tranches_age)): ?>
                    <?php foreach ($tranches_age as $tranche): ?>
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span><?php echo $tranche['tranche_age']; ?></span>
                            <div class="text-end">
                                <strong><?php echo $tranche['nombre']; ?></strong>
                                <br><small class="text-muted">
                                    <?php echo $stats['total_eleves'] > 0 ? round(($tranche['nombre'] / $stats['total_eleves']) * 100, 1) : 0; ?>%
                                </small>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p class="text-muted text-center">Aucune donnée disponible</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
// Graphique des inscriptions mensuelles
<?php if (!empty($inscriptions_mensuelles)): ?>
const inscriptionsCtx = document.getElementById('inscriptionsChart').getContext('2d');
const inscriptionsChart = new Chart(inscriptionsCtx, {
    type: 'bar',
    data: {
        labels: [<?php echo implode(',', array_map(function($i) { return "'" . date('M Y', strtotime($i['mois'] . '-01')) . "'"; }, $inscriptions_mensuelles)); ?>],
        datasets: [{
            label: 'Nouvelles inscriptions',
            data: [<?php echo implode(',', array_column($inscriptions_mensuelles, 'nombre')); ?>],
            backgroundColor: '#007bff',
            borderColor: '#0056b3',
            borderWidth: 1
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
                ticks: {
                    stepSize: 1
                }
            }
        }
    }
});
<?php endif; ?>

// Graphique des niveaux
<?php if (!empty($repartition_niveau_genre)): ?>
<?php
$niveaux_data = [];
foreach ($repartition_niveau_genre as $item) {
    if (!isset($niveaux_data[$item['niveau']])) {
        $niveaux_data[$item['niveau']] = 0;
    }
    $niveaux_data[$item['niveau']] += $item['nombre'];
}
?>
const niveauxCtx = document.getElementById('niveauxChart').getContext('2d');
const niveauxChart = new Chart(niveauxCtx, {
    type: 'doughnut',
    data: {
        labels: [<?php echo implode(',', array_map(function($n) { return "'" . ucfirst($n) . "'"; }, array_keys($niveaux_data))); ?>],
        datasets: [{
            data: [<?php echo implode(',', array_values($niveaux_data)); ?>],
            backgroundColor: ['#ffc107', '#28a745', '#007bff'],
            borderWidth: 0
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: 'bottom'
            }
        }
    }
});
<?php endif; ?>
</script>

<?php include '../../../includes/footer.php'; ?>
