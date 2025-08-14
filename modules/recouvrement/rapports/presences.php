<?php
/**
 * Module Recouvrement - Rapports des Présences
 * Application de gestion scolaire - République Démocratique du Congo
 */

require_once '../../../config/config.php';
require_once '../../../config/database.php';
require_once '../../../includes/functions.php';

// Vérifier l'authentification et les permissions
requireLogin();
if (!checkPermission('recouvrement') && !checkPermission('admin')) {
    showMessage('error', 'Accès refusé à cette page.');
    redirectTo('../../../index.php');
}

$errors = [];
$success_message = '';

// Paramètres de filtrage
$date_debut = $_GET['date_debut'] ?? date('Y-m-01');
$date_fin = $_GET['date_fin'] ?? date('Y-m-t');
$classe_id = $_GET['classe_id'] ?? '';
$type_scan = $_GET['type_scan'] ?? '';
$lieu_scan = $_GET['lieu_scan'] ?? '';

try {
    // Récupérer les classes pour le filtre
    $classes = $database->query("
        SELECT id, nom
        FROM classes
        ORDER BY nom
    ")->fetchAll();

    // Récupérer les lieux de scan pour le filtre
    $lieux_scan = $database->query("
        SELECT DISTINCT lieu_scan 
        FROM presences_qr 
        WHERE lieu_scan IS NOT NULL AND lieu_scan != ''
        ORDER BY lieu_scan
    ")->fetchAll();

    // Construire la requête avec filtres
    $where_conditions = ["1=1"];
    $params = [];

    if (!empty($date_debut)) {
        $where_conditions[] = "DATE(p.created_at) >= ?";
        $params[] = $date_debut;
    }

    if (!empty($date_fin)) {
        $where_conditions[] = "DATE(p.created_at) <= ?";
        $params[] = $date_fin;
    }

    if (!empty($classe_id)) {
        $where_conditions[] = "i.classe_id = ?";
        $params[] = $classe_id;
    }

    if (!empty($type_scan)) {
        $where_conditions[] = "p.type_scan = ?";
        $params[] = $type_scan;
    }

    if (!empty($lieu_scan)) {
        $where_conditions[] = "p.lieu_scan = ?";
        $params[] = $lieu_scan;
    }

    $where_clause = implode(' AND ', $where_conditions);

    // Statistiques générales des présences
    $stats_presences = $database->query("
        SELECT 
            COUNT(*) as total_scans,
            COUNT(DISTINCT p.eleve_id) as eleves_presents,
            COUNT(DISTINCT DATE(p.created_at)) as jours_activite,
            COUNT(CASE WHEN p.type_scan = 'entree' THEN 1 END) as total_entrees,
            COUNT(CASE WHEN p.type_scan = 'sortie' THEN 1 END) as total_sorties,
            AVG(CASE WHEN p.type_scan = 'entree' THEN TIME_TO_SEC(p.heure_entree) END) as heure_entree_moyenne,
            AVG(CASE WHEN p.type_scan = 'sortie' THEN TIME_TO_SEC(p.heure_sortie) END) as heure_sortie_moyenne
        FROM presences_qr p
        JOIN eleves e ON p.eleve_id = e.id
        LEFT JOIN inscriptions i ON e.id = i.eleve_id AND i.status = 'inscrit'
        WHERE $where_clause
    ", $params)->fetch();

    // Présences par jour
    $presences_par_jour = $database->query("
        SELECT 
            DATE(p.created_at) as date_presence,
            COUNT(DISTINCT p.eleve_id) as eleves_presents,
            COUNT(CASE WHEN p.type_scan = 'entree' THEN 1 END) as entrees,
            COUNT(CASE WHEN p.type_scan = 'sortie' THEN 1 END) as sorties,
            COUNT(*) as total_scans
        FROM presences_qr p
        JOIN eleves e ON p.eleve_id = e.id
        LEFT JOIN inscriptions i ON e.id = i.eleve_id AND i.status = 'inscrit'
        WHERE $where_clause
        GROUP BY DATE(p.created_at)
        ORDER BY date_presence DESC
        LIMIT 30
    ", $params)->fetchAll();

    // Présences par classe
    $presences_par_classe = $database->query("
        SELECT 
            cl.nom as classe_nom,
            COUNT(DISTINCT p.eleve_id) as eleves_presents,
            COUNT(*) as total_scans,
            COUNT(CASE WHEN p.type_scan = 'entree' THEN 1 END) as entrees,
            COUNT(CASE WHEN p.type_scan = 'sortie' THEN 1 END) as sorties,
            AVG(CASE WHEN p.type_scan = 'entree' THEN TIME_TO_SEC(p.heure_entree) END) as heure_entree_moyenne
        FROM presences_qr p
        JOIN eleves e ON p.eleve_id = e.id
        LEFT JOIN inscriptions i ON e.id = i.eleve_id AND i.status = 'inscrit'
        LEFT JOIN classes cl ON i.classe_id = cl.id
        WHERE $where_clause AND cl.id IS NOT NULL
        GROUP BY cl.id, cl.nom
        ORDER BY eleves_presents DESC
    ", $params)->fetchAll();

    // Présences par lieu de scan
    $presences_par_lieu = $database->query("
        SELECT 
            p.lieu_scan,
            COUNT(*) as total_scans,
            COUNT(DISTINCT p.eleve_id) as eleves_uniques,
            COUNT(CASE WHEN p.type_scan = 'entree' THEN 1 END) as entrees,
            COUNT(CASE WHEN p.type_scan = 'sortie' THEN 1 END) as sorties
        FROM presences_qr p
        JOIN eleves e ON p.eleve_id = e.id
        LEFT JOIN inscriptions i ON e.id = i.eleve_id AND i.status = 'inscrit'
        WHERE $where_clause
        GROUP BY p.lieu_scan
        ORDER BY total_scans DESC
    ", $params)->fetchAll();

    // Top élèves les plus présents
    $top_eleves_presents = $database->query("
        SELECT 
            e.nom, e.prenom, e.numero_matricule,
            cl.nom as classe_nom,
            COUNT(*) as total_scans,
            COUNT(CASE WHEN p.type_scan = 'entree' THEN 1 END) as entrees,
            COUNT(CASE WHEN p.type_scan = 'sortie' THEN 1 END) as sorties,
            COUNT(DISTINCT DATE(p.created_at)) as jours_presence
        FROM presences_qr p
        JOIN eleves e ON p.eleve_id = e.id
        LEFT JOIN inscriptions i ON e.id = i.eleve_id AND i.status = 'inscrit'
        LEFT JOIN classes cl ON i.classe_id = cl.id
        WHERE $where_clause
        GROUP BY p.eleve_id, e.nom, e.prenom, e.numero_matricule, cl.nom
        ORDER BY jours_presence DESC, total_scans DESC
        LIMIT 20
    ", $params)->fetchAll();

    // Analyse des heures de pointe
    $heures_pointe = $database->query("
        SELECT 
            HOUR(COALESCE(p.heure_entree, p.heure_sortie, p.created_at)) as heure,
            COUNT(*) as nombre_scans,
            COUNT(CASE WHEN p.type_scan = 'entree' THEN 1 END) as entrees,
            COUNT(CASE WHEN p.type_scan = 'sortie' THEN 1 END) as sorties
        FROM presences_qr p
        JOIN eleves e ON p.eleve_id = e.id
        LEFT JOIN inscriptions i ON e.id = i.eleve_id AND i.status = 'inscrit'
        WHERE $where_clause
        GROUP BY HOUR(COALESCE(p.heure_entree, p.heure_sortie, p.created_at))
        ORDER BY heure
    ", $params)->fetchAll();

} catch (Exception $e) {
    $errors[] = "Erreur lors du chargement des données : " . $e->getMessage();
}

$page_title = "Rapports des Présences";
include '../../../includes/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">
        <i class="fas fa-user-check me-2 text-warning"></i>
        Rapports des Présences
    </h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <div class="btn-group me-2">
            <a href="index.php" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-1"></i>
                Retour
            </a>
        </div>
        <div class="btn-group">
            <a href="export.php?type=presences&format=excel&<?php echo http_build_query($_GET); ?>" class="btn btn-success">
                <i class="fas fa-file-excel me-1"></i>
                Exporter Excel
            </a>
            <a href="export.php?type=presences&format=pdf&<?php echo http_build_query($_GET); ?>" class="btn btn-danger">
                <i class="fas fa-file-pdf me-1"></i>
                Exporter PDF
            </a>
        </div>
    </div>
</div>

<?php if (!empty($errors)): ?>
    <div class="alert alert-danger">
        <h6><i class="fas fa-exclamation-circle me-1"></i> Erreurs :</h6>
        <ul class="mb-0">
            <?php foreach ($errors as $error): ?>
                <li><?php echo htmlspecialchars($error); ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<!-- Filtres -->
<div class="card shadow-sm mb-4">
    <div class="card-header bg-light">
        <h5 class="mb-0">
            <i class="fas fa-filter me-2"></i>
            Filtres de Recherche
        </h5>
    </div>
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-md-3">
                <label for="date_debut" class="form-label">Date de début</label>
                <input type="date" class="form-control" id="date_debut" name="date_debut" 
                       value="<?php echo htmlspecialchars($date_debut); ?>">
            </div>
            <div class="col-md-3">
                <label for="date_fin" class="form-label">Date de fin</label>
                <input type="date" class="form-control" id="date_fin" name="date_fin" 
                       value="<?php echo htmlspecialchars($date_fin); ?>">
            </div>
            <div class="col-md-2">
                <label for="classe_id" class="form-label">Classe</label>
                <select class="form-select" id="classe_id" name="classe_id">
                    <option value="">Toutes les classes</option>
                    <?php foreach ($classes as $classe): ?>
                        <option value="<?php echo $classe['id']; ?>" 
                                <?php echo ($classe_id == $classe['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($classe['nom']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label for="type_scan" class="form-label">Type de scan</label>
                <select class="form-select" id="type_scan" name="type_scan">
                    <option value="">Tous les types</option>
                    <option value="entree" <?php echo ($type_scan == 'entree') ? 'selected' : ''; ?>>Entrée</option>
                    <option value="sortie" <?php echo ($type_scan == 'sortie') ? 'selected' : ''; ?>>Sortie</option>
                </select>
            </div>
            <div class="col-md-2">
                <label for="lieu_scan" class="form-label">Lieu de scan</label>
                <select class="form-select" id="lieu_scan" name="lieu_scan">
                    <option value="">Tous les lieux</option>
                    <?php foreach ($lieux_scan as $lieu): ?>
                        <option value="<?php echo htmlspecialchars($lieu['lieu_scan']); ?>" 
                                <?php echo ($lieu_scan == $lieu['lieu_scan']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($lieu['lieu_scan']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-12">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-search me-1"></i>
                    Filtrer
                </button>
                <a href="presences.php" class="btn btn-outline-secondary">
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
        <div class="card bg-primary text-white shadow-sm">
            <div class="card-body text-center">
                <h4 class="mb-0"><?php echo number_format($stats_presences['total_scans'] ?? 0); ?></h4>
                <small>Total Scans</small>
            </div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card bg-success text-white shadow-sm">
            <div class="card-body text-center">
                <h4 class="mb-0"><?php echo number_format($stats_presences['eleves_presents'] ?? 0); ?></h4>
                <small>Élèves Présents</small>
            </div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card bg-info text-white shadow-sm">
            <div class="card-body text-center">
                <h4 class="mb-0"><?php echo number_format($stats_presences['total_entrees'] ?? 0); ?></h4>
                <small>Entrées</small>
            </div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card bg-warning text-white shadow-sm">
            <div class="card-body text-center">
                <h4 class="mb-0"><?php echo number_format($stats_presences['total_sorties'] ?? 0); ?></h4>
                <small>Sorties</small>
            </div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card bg-secondary text-white shadow-sm">
            <div class="card-body text-center">
                <h4 class="mb-0"><?php echo number_format($stats_presences['jours_activite'] ?? 0); ?></h4>
                <small>Jours d'Activité</small>
            </div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card bg-dark text-white shadow-sm">
            <div class="card-body text-center">
                <h4 class="mb-0">
                    <?php 
                    $heure_moyenne = ($stats_presences['heure_entree_moyenne'] ?? 0) / 3600;
                    echo sprintf('%02d:%02d', floor($heure_moyenne), ($heure_moyenne - floor($heure_moyenne)) * 60);
                    ?>
                </h4>
                <small>Heure Entrée Moy.</small>
            </div>
        </div>
    </div>
</div>

<div class="row mb-4">
    <!-- Évolution des présences -->
    <div class="col-lg-8">
        <div class="card shadow-sm">
            <div class="card-header bg-light">
                <h5 class="mb-0">
                    <i class="fas fa-chart-line me-2"></i>
                    Évolution des Présences (30 derniers jours)
                </h5>
            </div>
            <div class="card-body">
                <?php if (!empty($presences_par_jour)): ?>
                    <canvas id="evolutionPresencesChart" width="400" height="200"></canvas>
                <?php else: ?>
                    <div class="text-center text-muted">
                        <i class="fas fa-chart-line fa-3x mb-3"></i>
                        <p>Aucune donnée d'évolution disponible</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Présences par lieu -->
    <div class="col-lg-4">
        <div class="card shadow-sm">
            <div class="card-header bg-light">
                <h5 class="mb-0">
                    <i class="fas fa-map-marker-alt me-2"></i>
                    Présences par Lieu
                </h5>
            </div>
            <div class="card-body">
                <?php if (!empty($presences_par_lieu)): ?>
                    <canvas id="lieuxChart" width="400" height="200"></canvas>
                    <div class="mt-3">
                        <?php foreach ($presences_par_lieu as $lieu): ?>
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span class="badge bg-primary">
                                    <?php echo htmlspecialchars($lieu['lieu_scan']); ?>
                                </span>
                                <span>
                                    <?php echo number_format($lieu['total_scans']); ?> scans
                                </span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="text-center text-muted">
                        <i class="fas fa-map-marker-alt fa-3x mb-3"></i>
                        <p>Aucune donnée par lieu disponible</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="row mb-4">
    <!-- Heures de pointe -->
    <div class="col-lg-6">
        <div class="card shadow-sm">
            <div class="card-header bg-light">
                <h5 class="mb-0">
                    <i class="fas fa-clock me-2"></i>
                    Analyse des Heures de Pointe
                </h5>
            </div>
            <div class="card-body">
                <?php if (!empty($heures_pointe)): ?>
                    <canvas id="heuresChart" width="400" height="200"></canvas>
                <?php else: ?>
                    <div class="text-center text-muted">
                        <i class="fas fa-clock fa-3x mb-3"></i>
                        <p>Aucune donnée horaire disponible</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Présences par classe -->
    <div class="col-lg-6">
        <div class="card shadow-sm">
            <div class="card-header bg-light">
                <h5 class="mb-0">
                    <i class="fas fa-graduation-cap me-2"></i>
                    Présences par Classe
                </h5>
            </div>
            <div class="card-body">
                <?php if (!empty($presences_par_classe)): ?>
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Classe</th>
                                    <th>Élèves</th>
                                    <th>Scans</th>
                                    <th>Entrées</th>
                                    <th>Sorties</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($presences_par_classe as $classe): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($classe['classe_nom']); ?></td>
                                        <td><span class="badge bg-primary"><?php echo number_format($classe['eleves_presents']); ?></span></td>
                                        <td><?php echo number_format($classe['total_scans']); ?></td>
                                        <td><span class="badge bg-success"><?php echo number_format($classe['entrees']); ?></span></td>
                                        <td><span class="badge bg-warning"><?php echo number_format($classe['sorties']); ?></span></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="text-center text-muted">
                        <i class="fas fa-graduation-cap fa-3x mb-3"></i>
                        <p>Aucune donnée par classe disponible</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Top élèves les plus présents -->
<div class="card shadow-sm">
    <div class="card-header bg-light">
        <h5 class="mb-0">
            <i class="fas fa-trophy me-2"></i>
            Top 20 des Élèves les Plus Présents
        </h5>
    </div>
    <div class="card-body">
        <?php if (!empty($top_eleves_presents)): ?>
            <div class="table-responsive">
                <table class="table table-hover table-sm">
                    <thead>
                        <tr>
                            <th>Rang</th>
                            <th>Matricule</th>
                            <th>Nom Complet</th>
                            <th>Classe</th>
                            <th>Jours de Présence</th>
                            <th>Total Scans</th>
                            <th>Entrées</th>
                            <th>Sorties</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($top_eleves_presents as $index => $eleve): ?>
                            <tr>
                                <td>
                                    <?php if ($index < 3): ?>
                                        <i class="fas fa-medal text-<?php echo ['warning', 'secondary', 'warning'][$index]; ?>"></i>
                                    <?php endif; ?>
                                    <?php echo $index + 1; ?>
                                </td>
                                <td><?php echo htmlspecialchars($eleve['numero_matricule']); ?></td>
                                <td><?php echo htmlspecialchars($eleve['nom'] . ' ' . $eleve['prenom']); ?></td>
                                <td><?php echo htmlspecialchars($eleve['classe_nom'] ?? 'N/A'); ?></td>
                                <td>
                                    <span class="badge bg-primary fs-6">
                                        <?php echo number_format($eleve['jours_presence']); ?> jours
                                    </span>
                                </td>
                                <td><?php echo number_format($eleve['total_scans']); ?></td>
                                <td><span class="badge bg-success"><?php echo number_format($eleve['entrees']); ?></span></td>
                                <td><span class="badge bg-warning"><?php echo number_format($eleve['sorties']); ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="text-center text-muted">
                <i class="fas fa-trophy fa-3x mb-3"></i>
                <p>Aucune donnée de présence disponible</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Inclure Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
// Graphique d'évolution des présences
<?php if (!empty($presences_par_jour)): ?>
const evolutionCtx = document.getElementById('evolutionPresencesChart').getContext('2d');
const evolutionChart = new Chart(evolutionCtx, {
    type: 'line',
    data: {
        labels: [
            <?php foreach (array_reverse($presences_par_jour) as $jour): ?>
                '<?php echo date('d/m', strtotime($jour['date_presence'])); ?>',
            <?php endforeach; ?>
        ],
        datasets: [{
            label: 'Élèves présents',
            data: [
                <?php foreach (array_reverse($presences_par_jour) as $jour): ?>
                    <?php echo $jour['eleves_presents']; ?>,
                <?php endforeach; ?>
            ],
            borderColor: '#007bff',
            backgroundColor: 'rgba(0, 123, 255, 0.1)',
            tension: 0.4
        }, {
            label: 'Entrées',
            data: [
                <?php foreach (array_reverse($presences_par_jour) as $jour): ?>
                    <?php echo $jour['entrees']; ?>,
                <?php endforeach; ?>
            ],
            borderColor: '#28a745',
            backgroundColor: 'rgba(40, 167, 69, 0.1)',
            tension: 0.4
        }, {
            label: 'Sorties',
            data: [
                <?php foreach (array_reverse($presences_par_jour) as $jour): ?>
                    <?php echo $jour['sorties']; ?>,
                <?php endforeach; ?>
            ],
            borderColor: '#ffc107',
            backgroundColor: 'rgba(255, 193, 7, 0.1)',
            tension: 0.4
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
<?php endif; ?>

// Graphique des lieux
<?php if (!empty($presences_par_lieu)): ?>
const lieuxCtx = document.getElementById('lieuxChart').getContext('2d');
const lieuxChart = new Chart(lieuxCtx, {
    type: 'doughnut',
    data: {
        labels: [
            <?php foreach ($presences_par_lieu as $lieu): ?>
                '<?php echo htmlspecialchars($lieu['lieu_scan']); ?>',
            <?php endforeach; ?>
        ],
        datasets: [{
            data: [
                <?php foreach ($presences_par_lieu as $lieu): ?>
                    <?php echo $lieu['total_scans']; ?>,
                <?php endforeach; ?>
            ],
            backgroundColor: [
                '#007bff', '#28a745', '#ffc107', '#dc3545', '#6c757d', '#17a2b8'
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
<?php endif; ?>

// Graphique des heures de pointe
<?php if (!empty($heures_pointe)): ?>
const heuresCtx = document.getElementById('heuresChart').getContext('2d');
const heuresChart = new Chart(heuresCtx, {
    type: 'bar',
    data: {
        labels: [
            <?php foreach ($heures_pointe as $heure): ?>
                '<?php echo sprintf('%02d:00', $heure['heure']); ?>',
            <?php endforeach; ?>
        ],
        datasets: [{
            label: 'Nombre de scans',
            data: [
                <?php foreach ($heures_pointe as $heure): ?>
                    <?php echo $heure['nombre_scans']; ?>,
                <?php endforeach; ?>
            ],
            backgroundColor: 'rgba(0, 123, 255, 0.8)',
            borderColor: '#007bff',
            borderWidth: 1
        }]
    },
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
});
<?php endif; ?>
</script>

<?php include '../../../includes/footer.php'; ?>
