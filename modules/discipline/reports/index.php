<?php
/**
 * Module Discipline - Rapports disciplinaires
 * Application de gestion scolaire - République Démocratique du Congo
 */

require_once '../../../config/config.php';
require_once '../../../config/database.php';
require_once '../../../includes/functions.php';

// Vérifier l'authentification et les permissions
requireLogin();
if (!checkPermission('discipline')) {
    showMessage('error', 'Accès refusé à cette page.');
    redirectTo('../../../index.php');
}

// Paramètres de filtrage
$periode = $_GET['periode'] ?? 'mois_courant';
$classe_filter = intval($_GET['classe'] ?? 0);
$gravite_filter = $_GET['gravite'] ?? '';

// Calculer les dates selon la période
$date_debut = '';
$date_fin = '';
switch ($periode) {
    case 'semaine_courante':
        $date_debut = date('Y-m-d', strtotime('monday this week'));
        $date_fin = date('Y-m-d', strtotime('sunday this week'));
        break;
    case 'mois_courant':
        $date_debut = date('Y-m-01');
        $date_fin = date('Y-m-t');
        break;
    case 'trimestre_courant':
        $mois = date('n');
        if ($mois <= 3) {
            $date_debut = date('Y-01-01');
            $date_fin = date('Y-03-31');
        } elseif ($mois <= 6) {
            $date_debut = date('Y-04-01');
            $date_fin = date('Y-06-30');
        } elseif ($mois <= 9) {
            $date_debut = date('Y-07-01');
            $date_fin = date('Y-09-30');
        } else {
            $date_debut = date('Y-10-01');
            $date_fin = date('Y-12-31');
        }
        break;
    case 'annee_scolaire':
        $date_debut = date('Y-09-01');
        $date_fin = date('Y-07-31', strtotime('+1 year'));
        break;
}

// Statistiques générales
try {
    $stats_generales = [];
    
    // Incidents par statut
    $sql = "SELECT status, COUNT(*) as nombre FROM incidents WHERE 1=1";
    $params = [];
    
    if ($date_debut && $date_fin) {
        $sql .= " AND DATE(date_incident) BETWEEN ? AND ?";
        $params[] = $date_debut;
        $params[] = $date_fin;
    }
    
    if ($classe_filter > 0) {
        $sql .= " AND classe_id = ?";
        $params[] = $classe_filter;
    }
    
    $sql .= " GROUP BY status";
    
    $incidents_par_status = $database->query($sql, $params)->fetchAll();
    foreach ($incidents_par_status as $stat) {
        $stats_generales['incidents_' . $stat['status']] = $stat['nombre'];
    }
    
    // Sanctions actives
    $sql_sanctions = "SELECT COUNT(*) as total FROM sanctions WHERE status = 'active'";
    $params_sanctions = [];
    
    if ($date_debut && $date_fin) {
        $sql_sanctions .= " AND DATE(date_sanction) BETWEEN ? AND ?";
        $params_sanctions[] = $date_debut;
        $params_sanctions[] = $date_fin;
    }
    
    $stats_generales['sanctions_actives'] = $database->query($sql_sanctions, $params_sanctions)->fetch()['total'];
    
    // Récompenses
    $tables = $database->query("SHOW TABLES LIKE 'recompenses'")->fetch();
    if ($tables) {
        $sql_recompenses = "SELECT COUNT(*) as total FROM recompenses WHERE 1=1";
        $params_recompenses = [];
        
        if ($date_debut && $date_fin) {
            $sql_recompenses .= " AND DATE(date_recompense) BETWEEN ? AND ?";
            $params_recompenses[] = $date_debut;
            $params_recompenses[] = $date_fin;
        }
        
        $stats_generales['recompenses'] = $database->query($sql_recompenses, $params_recompenses)->fetch()['total'];
    } else {
        $stats_generales['recompenses'] = 0;
    }
    
} catch (Exception $e) {
    $stats_generales = [
        'incidents_nouveau' => 0,
        'incidents_en_cours' => 0,
        'incidents_resolu' => 0,
        'incidents_archive' => 0,
        'sanctions_actives' => 0,
        'recompenses' => 0
    ];
}

// Incidents par gravité
try {
    $sql = "SELECT gravite, COUNT(*) as nombre FROM incidents WHERE 1=1";
    $params = [];
    
    if ($date_debut && $date_fin) {
        $sql .= " AND DATE(date_incident) BETWEEN ? AND ?";
        $params[] = $date_debut;
        $params[] = $date_fin;
    }
    
    if ($classe_filter > 0) {
        $sql .= " AND classe_id = ?";
        $params[] = $classe_filter;
    }
    
    if ($gravite_filter) {
        $sql .= " AND gravite = ?";
        $params[] = $gravite_filter;
    }
    
    $sql .= " GROUP BY gravite ORDER BY 
                CASE gravite 
                    WHEN 'legere' THEN 1 
                    WHEN 'moyenne' THEN 2 
                    WHEN 'grave' THEN 3 
                    WHEN 'tres_grave' THEN 4 
                END";
    
    $incidents_par_gravite = $database->query($sql, $params)->fetchAll();
} catch (Exception $e) {
    $incidents_par_gravite = [];
}

// Top 10 des élèves avec le plus d'incidents
try {
    $sql = "SELECT e.nom, e.prenom, e.numero_matricule, c.nom as classe_nom,
                   COUNT(i.id) as nb_incidents,
                   SUM(CASE WHEN i.gravite = 'legere' THEN 1 ELSE 0 END) as incidents_legers,
                   SUM(CASE WHEN i.gravite = 'moyenne' THEN 1 ELSE 0 END) as incidents_moyens,
                   SUM(CASE WHEN i.gravite = 'grave' THEN 1 ELSE 0 END) as incidents_graves,
                   SUM(CASE WHEN i.gravite = 'tres_grave' THEN 1 ELSE 0 END) as incidents_tres_graves
            FROM incidents i
            JOIN eleves e ON i.eleve_id = e.id
            LEFT JOIN inscriptions ins ON e.id = ins.eleve_id AND ins.status = 'inscrit'
            LEFT JOIN classes c ON ins.classe_id = c.id
            WHERE 1=1";
    
    $params = [];
    
    if ($date_debut && $date_fin) {
        $sql .= " AND DATE(i.date_incident) BETWEEN ? AND ?";
        $params[] = $date_debut;
        $params[] = $date_fin;
    }
    
    if ($classe_filter > 0) {
        $sql .= " AND c.id = ?";
        $params[] = $classe_filter;
    }
    
    $sql .= " GROUP BY e.id, e.nom, e.prenom, e.numero_matricule, c.nom
              ORDER BY nb_incidents DESC
              LIMIT 10";
    
    $top_eleves_incidents = $database->query($sql, $params)->fetchAll();
} catch (Exception $e) {
    $top_eleves_incidents = [];
}

// Évolution mensuelle des incidents
try {
    $sql = "SELECT DATE_FORMAT(date_incident, '%Y-%m') as mois,
                   COUNT(*) as nb_incidents,
                   SUM(CASE WHEN gravite = 'legere' THEN 1 ELSE 0 END) as legers,
                   SUM(CASE WHEN gravite = 'moyenne' THEN 1 ELSE 0 END) as moyens,
                   SUM(CASE WHEN gravite = 'grave' THEN 1 ELSE 0 END) as graves,
                   SUM(CASE WHEN gravite = 'tres_grave' THEN 1 ELSE 0 END) as tres_graves
            FROM incidents 
            WHERE date_incident >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)";
    
    $params = [];
    
    if ($classe_filter > 0) {
        $sql .= " AND classe_id = ?";
        $params[] = $classe_filter;
    }
    
    $sql .= " GROUP BY DATE_FORMAT(date_incident, '%Y-%m')
              ORDER BY mois DESC
              LIMIT 6";
    
    $evolution_mensuelle = $database->query($sql, $params)->fetchAll();
} catch (Exception $e) {
    $evolution_mensuelle = [];
}

// Récupérer les classes pour le filtre
try {
    $classes = $database->query(
        "SELECT id, nom, niveau FROM classes ORDER BY niveau, nom"
    )->fetchAll();
} catch (Exception $e) {
    $classes = [];
}

$page_title = "Rapports disciplinaires";
include '../../../includes/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">
        <i class="fas fa-chart-bar me-2 text-info"></i>
        Rapports disciplinaires
    </h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <div class="btn-group me-2">
            <button type="button" class="btn btn-outline-primary" onclick="window.print()">
                <i class="fas fa-print me-1"></i>
                Imprimer
            </button>
        </div>
        <a href="../index.php" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-left me-1"></i>
            Retour
        </a>
    </div>
</div>

<!-- Filtres -->
<div class="card shadow-sm mb-4">
    <div class="card-header bg-light">
        <h6 class="mb-0">
            <i class="fas fa-filter me-2"></i>
            Filtres de rapport
        </h6>
    </div>
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-md-3">
                <label for="periode" class="form-label">Période</label>
                <select class="form-select" id="periode" name="periode">
                    <option value="semaine_courante" <?php echo ($periode === 'semaine_courante') ? 'selected' : ''; ?>>
                        Semaine courante
                    </option>
                    <option value="mois_courant" <?php echo ($periode === 'mois_courant') ? 'selected' : ''; ?>>
                        Mois courant
                    </option>
                    <option value="trimestre_courant" <?php echo ($periode === 'trimestre_courant') ? 'selected' : ''; ?>>
                        Trimestre courant
                    </option>
                    <option value="annee_scolaire" <?php echo ($periode === 'annee_scolaire') ? 'selected' : ''; ?>>
                        Année scolaire
                    </option>
                </select>
            </div>
            
            <div class="col-md-3">
                <label for="classe" class="form-label">Classe</label>
                <select class="form-select" id="classe" name="classe">
                    <option value="0">Toutes les classes</option>
                    <?php foreach ($classes as $classe): ?>
                        <option value="<?php echo $classe['id']; ?>" 
                                <?php echo ($classe_filter == $classe['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($classe['nom'] . ' (' . ucfirst($classe['niveau']) . ')'); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="col-md-3">
                <label for="gravite" class="form-label">Gravité</label>
                <select class="form-select" id="gravite" name="gravite">
                    <option value="">Toutes les gravités</option>
                    <option value="legere" <?php echo ($gravite_filter === 'legere') ? 'selected' : ''; ?>>
                        Légère
                    </option>
                    <option value="moyenne" <?php echo ($gravite_filter === 'moyenne') ? 'selected' : ''; ?>>
                        Moyenne
                    </option>
                    <option value="grave" <?php echo ($gravite_filter === 'grave') ? 'selected' : ''; ?>>
                        Grave
                    </option>
                    <option value="tres_grave" <?php echo ($gravite_filter === 'tres_grave') ? 'selected' : ''; ?>>
                        Très grave
                    </option>
                </select>
            </div>
            
            <div class="col-md-3">
                <label class="form-label">&nbsp;</label>
                <div>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search me-1"></i>
                        Filtrer
                    </button>
                    <a href="?" class="btn btn-outline-secondary">
                        <i class="fas fa-undo me-1"></i>
                        Reset
                    </a>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Statistiques générales -->
<div class="row mb-4">
    <div class="col-md-2">
        <div class="card text-center shadow-sm stats-card">
            <div class="card-body">
                <i class="fas fa-exclamation-triangle fa-2x text-warning mb-2"></i>
                <h4><?php echo number_format($stats_generales['incidents_nouveau'] ?? 0); ?></h4>
                <p class="text-muted mb-0">Nouveaux incidents</p>
            </div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card text-center shadow-sm stats-card">
            <div class="card-body">
                <i class="fas fa-clock fa-2x text-info mb-2"></i>
                <h4><?php echo number_format($stats_generales['incidents_en_cours'] ?? 0); ?></h4>
                <p class="text-muted mb-0">En cours</p>
            </div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card text-center shadow-sm stats-card">
            <div class="card-body">
                <i class="fas fa-check-circle fa-2x text-success mb-2"></i>
                <h4><?php echo number_format($stats_generales['incidents_resolu'] ?? 0); ?></h4>
                <p class="text-muted mb-0">Résolus</p>
            </div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card text-center shadow-sm stats-card">
            <div class="card-body">
                <i class="fas fa-gavel fa-2x text-danger mb-2"></i>
                <h4><?php echo number_format($stats_generales['sanctions_actives'] ?? 0); ?></h4>
                <p class="text-muted mb-0">Sanctions actives</p>
            </div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card text-center shadow-sm stats-card">
            <div class="card-body">
                <i class="fas fa-award fa-2x text-success mb-2"></i>
                <h4><?php echo number_format($stats_generales['recompenses'] ?? 0); ?></h4>
                <p class="text-muted mb-0">Récompenses</p>
            </div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card text-center shadow-sm stats-card">
            <div class="card-body">
                <i class="fas fa-archive fa-2x text-secondary mb-2"></i>
                <h4><?php echo number_format($stats_generales['incidents_archive'] ?? 0); ?></h4>
                <p class="text-muted mb-0">Archivés</p>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <!-- Incidents par gravité -->
    <div class="col-lg-6 mb-4">
        <div class="card shadow-sm">
            <div class="card-header bg-light">
                <h5 class="mb-0">
                    <i class="fas fa-chart-pie me-2"></i>
                    Incidents par gravité
                </h5>
            </div>
            <div class="card-body">
                <?php if (!empty($incidents_par_gravite)): ?>
                    <canvas id="graviteChart" width="400" height="200"></canvas>
                    <div class="mt-3">
                        <?php foreach ($incidents_par_gravite as $incident): ?>
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span class="badge bg-<?php 
                                    echo match($incident['gravite']) {
                                        'legere' => 'success',
                                        'moyenne' => 'warning',
                                        'grave' => 'danger',
                                        'tres_grave' => 'dark',
                                        default => 'secondary'
                                    };
                                ?>">
                                    <?php echo ucfirst(str_replace('_', ' ', $incident['gravite'])); ?>
                                </span>
                                <strong><?php echo $incident['nombre']; ?></strong>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="text-center py-4">
                        <i class="fas fa-chart-pie fa-3x text-muted mb-3"></i>
                        <p class="text-muted">Aucun incident pour cette période</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Top élèves avec incidents -->
    <div class="col-lg-6 mb-4">
        <div class="card shadow-sm">
            <div class="card-header bg-light">
                <h5 class="mb-0">
                    <i class="fas fa-list-ol me-2"></i>
                    Élèves avec le plus d'incidents
                </h5>
            </div>
            <div class="card-body">
                <?php if (!empty($top_eleves_incidents)): ?>
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Élève</th>
                                    <th>Classe</th>
                                    <th class="text-center">Total</th>
                                    <th class="text-center">Détail</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($top_eleves_incidents as $eleve): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo htmlspecialchars($eleve['nom'] . ' ' . $eleve['prenom']); ?></strong>
                                            <br><small class="text-muted"><?php echo htmlspecialchars($eleve['numero_matricule']); ?></small>
                                        </td>
                                        <td><?php echo htmlspecialchars($eleve['classe_nom'] ?? 'N/A'); ?></td>
                                        <td class="text-center">
                                            <span class="badge bg-danger"><?php echo $eleve['nb_incidents']; ?></span>
                                        </td>
                                        <td class="text-center">
                                            <small>
                                                <?php if ($eleve['incidents_legers'] > 0): ?>
                                                    <span class="badge bg-success"><?php echo $eleve['incidents_legers']; ?></span>
                                                <?php endif; ?>
                                                <?php if ($eleve['incidents_moyens'] > 0): ?>
                                                    <span class="badge bg-warning"><?php echo $eleve['incidents_moyens']; ?></span>
                                                <?php endif; ?>
                                                <?php if ($eleve['incidents_graves'] > 0): ?>
                                                    <span class="badge bg-danger"><?php echo $eleve['incidents_graves']; ?></span>
                                                <?php endif; ?>
                                                <?php if ($eleve['incidents_tres_graves'] > 0): ?>
                                                    <span class="badge bg-dark"><?php echo $eleve['incidents_tres_graves']; ?></span>
                                                <?php endif; ?>
                                            </small>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="text-center py-4">
                        <i class="fas fa-smile fa-3x text-success mb-3"></i>
                        <p class="text-muted">Aucun incident à signaler !</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Évolution mensuelle -->
<?php if (!empty($evolution_mensuelle)): ?>
<div class="card shadow-sm mb-4">
    <div class="card-header bg-light">
        <h5 class="mb-0">
            <i class="fas fa-chart-line me-2"></i>
            Évolution mensuelle des incidents (6 derniers mois)
        </h5>
    </div>
    <div class="card-body">
        <canvas id="evolutionChart" width="400" height="100"></canvas>
    </div>
</div>
<?php endif; ?>

<style>
.stats-card {
    transition: all 0.2s ease-in-out;
}

.stats-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15) !important;
}

@media print {
    .btn-toolbar, .card-header .btn, .no-print {
        display: none !important;
    }
}
</style>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Graphique des incidents par gravité
<?php if (!empty($incidents_par_gravite)): ?>
const graviteCtx = document.getElementById('graviteChart').getContext('2d');
const graviteChart = new Chart(graviteCtx, {
    type: 'doughnut',
    data: {
        labels: [<?php echo implode(',', array_map(function($item) { return '"' . ucfirst(str_replace('_', ' ', $item['gravite'])) . '"'; }, $incidents_par_gravite)); ?>],
        datasets: [{
            data: [<?php echo implode(',', array_column($incidents_par_gravite, 'nombre')); ?>],
            backgroundColor: [
                '#28a745', // légère
                '#ffc107', // moyenne  
                '#dc3545', // grave
                '#343a40'  // très grave
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

// Graphique d'évolution mensuelle
<?php if (!empty($evolution_mensuelle)): ?>
const evolutionCtx = document.getElementById('evolutionChart').getContext('2d');
const evolutionChart = new Chart(evolutionCtx, {
    type: 'line',
    data: {
        labels: [<?php echo implode(',', array_map(function($item) { return '"' . date('M Y', strtotime($item['mois'] . '-01')) . '"'; }, array_reverse($evolution_mensuelle))); ?>],
        datasets: [{
            label: 'Total incidents',
            data: [<?php echo implode(',', array_column(array_reverse($evolution_mensuelle), 'nb_incidents')); ?>],
            borderColor: '#007bff',
            backgroundColor: 'rgba(0, 123, 255, 0.1)',
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
</script>

<?php include '../../../includes/footer.php'; ?>
