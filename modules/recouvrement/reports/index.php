<?php
/**
 * Module Recouvrement - Rapports
 * Application de gestion scolaire - République Démocratique du Congo
 */

require_once '../../../config/config.php';
require_once '../../../config/database.php';
require_once '../../../includes/functions.php';

// Vérifier l'authentification et les permissions
requireLogin();
if (!checkPermission('recouvrement') && !checkPermission('recouvrement_view')) {
    showMessage('error', 'Accès refusé à ce module.');
    redirectTo('../../dashboard.php');
}

$page_title = 'Rapports de recouvrement';

// Obtenir l'année scolaire actuelle
$current_year = getCurrentAcademicYear();

// Paramètres de filtre
$period = $_GET['period'] ?? 'month';
$niveau_filter = $_GET['niveau'] ?? '';
$classe_filter = $_GET['classe'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';

// Définir les dates selon la période
switch ($period) {
    case 'week':
        $date_from = $date_from ?: date('Y-m-d', strtotime('-7 days'));
        $date_to = $date_to ?: date('Y-m-d');
        break;
    case 'month':
        $date_from = $date_from ?: date('Y-m-01');
        $date_to = $date_to ?: date('Y-m-t');
        break;
    case 'year':
        $date_from = $date_from ?: date('Y-01-01');
        $date_to = $date_to ?: date('Y-12-31');
        break;
    case 'custom':
        $date_from = $date_from ?: date('Y-m-01');
        $date_to = $date_to ?: date('Y-m-d');
        break;
}

// Construire les conditions WHERE
$where_conditions = ["i.annee_scolaire_id = ?"];
$params = [$current_year['id']];

if ($niveau_filter) {
    $where_conditions[] = "c.niveau = ?";
    $params[] = $niveau_filter;
}

if ($classe_filter) {
    $where_conditions[] = "c.id = ?";
    $params[] = $classe_filter;
}

$where_clause = implode(' AND ', $where_conditions);

// Statistiques générales
$general_stats = $database->query(
    "SELECT 
        COUNT(DISTINCT e.id) as total_eleves,
        COUNT(DISTINCT CASE WHEN dette.montant_du > 0 THEN e.id END) as nombre_debiteurs,
        SUM(dette.montant_du) as total_dettes,
        SUM(CASE WHEN dette.montant_du > 0 THEN dette.montant_du ELSE 0 END) as dettes_actives,
        AVG(CASE WHEN dette.montant_du > 0 THEN dette.montant_du END) as dette_moyenne,
        SUM(p.montant) as total_paiements,
        ROUND((SUM(p.montant) * 100.0 / SUM(fs.montant)), 1) as taux_recouvrement
     FROM eleves e
     JOIN inscriptions i ON e.id = i.eleve_id
     JOIN classes c ON i.classe_id = c.id
     JOIN frais_scolaires fs ON i.classe_id = fs.classe_id
     LEFT JOIN paiements p ON e.id = p.eleve_id 
         AND fs.type_frais COLLATE utf8mb4_unicode_ci = p.type_paiement COLLATE utf8mb4_unicode_ci
         AND p.annee_scolaire_id = fs.annee_scolaire_id
         AND p.date_paiement BETWEEN ? AND ?
     JOIN (
         SELECT 
             e.id as eleve_id,
             SUM(fs.montant) - COALESCE(SUM(p2.montant), 0) as montant_du
         FROM eleves e
         JOIN inscriptions i ON e.id = i.eleve_id
         JOIN frais_scolaires fs ON i.classe_id = fs.classe_id
         LEFT JOIN paiements p2 ON e.id = p2.eleve_id 
             AND fs.type_frais COLLATE utf8mb4_unicode_ci = p2.type_paiement COLLATE utf8mb4_unicode_ci
             AND p2.annee_scolaire_id = fs.annee_scolaire_id
         WHERE $where_clause AND fs.annee_scolaire_id = ?
         GROUP BY e.id
     ) dette ON e.id = dette.eleve_id
     WHERE $where_clause AND fs.annee_scolaire_id = ?",
    array_merge($params, [$date_from, $date_to, $current_year['id'], $current_year['id']])
)->fetch();

// Dettes par niveau
$dettes_par_niveau = $database->query(
    "SELECT 
        c.niveau,
        COUNT(DISTINCT e.id) as nombre_eleves,
        COUNT(DISTINCT CASE WHEN dette.montant_du > 0 THEN e.id END) as nombre_debiteurs,
        SUM(dette.montant_du) as total_dettes,
        AVG(CASE WHEN dette.montant_du > 0 THEN dette.montant_du END) as dette_moyenne,
        ROUND((COUNT(DISTINCT CASE WHEN dette.montant_du > 0 THEN e.id END) * 100.0 / COUNT(DISTINCT e.id)), 1) as pourcentage_debiteurs
     FROM eleves e
     JOIN inscriptions i ON e.id = i.eleve_id
     JOIN classes c ON i.classe_id = c.id
     JOIN (
         SELECT 
             e.id as eleve_id,
             SUM(fs.montant) - COALESCE(SUM(p.montant), 0) as montant_du
         FROM eleves e
         JOIN inscriptions i ON e.id = i.eleve_id
         JOIN classes c ON i.classe_id = c.id
         JOIN frais_scolaires fs ON i.classe_id = fs.classe_id
         LEFT JOIN paiements p ON e.id = p.eleve_id 
             AND fs.type_frais COLLATE utf8mb4_unicode_ci = p.type_paiement COLLATE utf8mb4_unicode_ci
             AND p.annee_scolaire_id = fs.annee_scolaire_id
         WHERE $where_clause AND fs.annee_scolaire_id = ?
         GROUP BY e.id
     ) dette ON e.id = dette.eleve_id
     WHERE $where_clause
     GROUP BY c.niveau
     ORDER BY total_dettes DESC",
    array_merge($params, [$current_year['id']])
)->fetchAll();

// Dettes par classe
$dettes_par_classe = $database->query(
    "SELECT 
        c.nom as classe_nom,
        c.niveau,
        COUNT(DISTINCT e.id) as nombre_eleves,
        COUNT(DISTINCT CASE WHEN dette.montant_du > 0 THEN e.id END) as nombre_debiteurs,
        SUM(dette.montant_du) as total_dettes,
        AVG(CASE WHEN dette.montant_du > 0 THEN dette.montant_du END) as dette_moyenne
     FROM eleves e
     JOIN inscriptions i ON e.id = i.eleve_id
     JOIN classes c ON i.classe_id = c.id
     JOIN (
         SELECT 
             e.id as eleve_id,
             SUM(fs.montant) - COALESCE(SUM(p.montant), 0) as montant_du
         FROM eleves e
         JOIN inscriptions i ON e.id = i.eleve_id
         JOIN classes c ON i.classe_id = c.id
         JOIN frais_scolaires fs ON i.classe_id = fs.classe_id
         LEFT JOIN paiements p ON e.id = p.eleve_id 
             AND fs.type_frais COLLATE utf8mb4_unicode_ci = p.type_paiement COLLATE utf8mb4_unicode_ci
             AND p.annee_scolaire_id = fs.annee_scolaire_id
         WHERE $where_clause AND fs.annee_scolaire_id = ?
         GROUP BY e.id
     ) dette ON e.id = dette.eleve_id
     WHERE $where_clause
     GROUP BY c.id, c.nom, c.niveau
     ORDER BY total_dettes DESC
     LIMIT 20",
    array_merge($params, [$current_year['id']])
)->fetchAll();

// Évolution des paiements
$evolution_paiements = $database->query(
    "SELECT 
        DATE_FORMAT(p.date_paiement, '%Y-%m') as mois,
        COUNT(DISTINCT p.eleve_id) as nombre_payeurs,
        SUM(p.montant) as total_paiements,
        COUNT(*) as nombre_transactions
     FROM paiements p
     JOIN inscriptions i ON p.eleve_id = i.eleve_id
     WHERE i.annee_scolaire_id = ? AND p.date_paiement BETWEEN ? AND ?
     GROUP BY DATE_FORMAT(p.date_paiement, '%Y-%m')
     ORDER BY mois DESC
     LIMIT 12",
    [$current_year['id'], $date_from, $date_to]
)->fetchAll();

// Top des débiteurs
$top_debiteurs = $database->query(
    "SELECT 
        e.nom,
        e.prenom,
        c.nom as classe_nom,
        c.niveau,
        dette.montant_du,
        DATEDIFF(CURDATE(), i.date_inscription) as jours_inscription
     FROM eleves e
     JOIN inscriptions i ON e.id = i.eleve_id
     JOIN classes c ON i.classe_id = c.id
     JOIN (
         SELECT 
             e.id as eleve_id,
             SUM(fs.montant) - COALESCE(SUM(p.montant), 0) as montant_du
         FROM eleves e
         JOIN inscriptions i ON e.id = i.eleve_id
         JOIN classes c ON i.classe_id = c.id
         JOIN frais_scolaires fs ON i.classe_id = fs.classe_id
         LEFT JOIN paiements p ON e.id = p.eleve_id 
             AND fs.type_frais COLLATE utf8mb4_unicode_ci = p.type_paiement COLLATE utf8mb4_unicode_ci
             AND p.annee_scolaire_id = fs.annee_scolaire_id
         WHERE $where_clause AND fs.annee_scolaire_id = ?
         GROUP BY e.id
         HAVING montant_du > 0
     ) dette ON e.id = dette.eleve_id
     WHERE $where_clause
     ORDER BY dette.montant_du DESC
     LIMIT 10",
    array_merge($params, [$current_year['id']])
)->fetchAll();

// Récupérer les niveaux pour le filtre
$niveaux = $database->query(
    "SELECT DISTINCT niveau FROM classes WHERE annee_scolaire_id = ? ORDER BY niveau",
    [$current_year['id']]
)->fetchAll();

// Récupérer les classes pour le filtre
$classes = $database->query(
    "SELECT id, nom, niveau FROM classes WHERE annee_scolaire_id = ? ORDER BY niveau, nom",
    [$current_year['id']]
)->fetchAll();

include '../../../includes/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">
        <i class="fas fa-chart-bar me-2"></i>
        Rapports de recouvrement
    </h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <div class="btn-group me-2">
            <a href="export.php" class="btn btn-success">
                <i class="fas fa-file-excel me-1"></i>
                Exporter
            </a>
        </div>
        <div class="btn-group">
            <a href="../index.php" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-1"></i>
                Retour
            </a>
        </div>
    </div>
</div>

<!-- Filtres -->
<div class="card mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-md-2">
                <label for="period" class="form-label">Période</label>
                <select class="form-select" id="period" name="period" onchange="toggleCustomDates()">
                    <option value="week" <?php echo $period === 'week' ? 'selected' : ''; ?>>Cette semaine</option>
                    <option value="month" <?php echo $period === 'month' ? 'selected' : ''; ?>>Ce mois</option>
                    <option value="year" <?php echo $period === 'year' ? 'selected' : ''; ?>>Cette année</option>
                    <option value="custom" <?php echo $period === 'custom' ? 'selected' : ''; ?>>Personnalisée</option>
                </select>
            </div>
            
            <div class="col-md-2" id="date-from-col" style="<?php echo $period !== 'custom' ? 'display: none;' : ''; ?>">
                <label for="date_from" class="form-label">Du</label>
                <input type="date" class="form-control" id="date_from" name="date_from" value="<?php echo htmlspecialchars($date_from); ?>">
            </div>
            
            <div class="col-md-2" id="date-to-col" style="<?php echo $period !== 'custom' ? 'display: none;' : ''; ?>">
                <label for="date_to" class="form-label">Au</label>
                <input type="date" class="form-control" id="date_to" name="date_to" value="<?php echo htmlspecialchars($date_to); ?>">
            </div>
            
            <div class="col-md-2">
                <label for="niveau" class="form-label">Niveau</label>
                <select class="form-select" id="niveau" name="niveau">
                    <option value="">Tous les niveaux</option>
                    <?php foreach ($niveaux as $niveau): ?>
                        <option value="<?php echo $niveau['niveau']; ?>" <?php echo $niveau_filter === $niveau['niveau'] ? 'selected' : ''; ?>>
                            <?php echo ucfirst($niveau['niveau']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="col-md-2">
                <label for="classe" class="form-label">Classe</label>
                <select class="form-select" id="classe" name="classe">
                    <option value="">Toutes les classes</option>
                    <?php foreach ($classes as $classe): ?>
                        <option value="<?php echo $classe['id']; ?>" <?php echo $classe_filter == $classe['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($classe['nom']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="col-md-2 d-flex align-items-end">
                <button type="submit" class="btn btn-primary w-100">
                    <i class="fas fa-search me-1"></i>
                    Analyser
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Statistiques générales -->
<div class="row mb-4">
    <div class="col-md-2">
        <div class="card text-center">
            <div class="card-body">
                <h4 class="text-primary"><?php echo number_format($general_stats['total_eleves']); ?></h4>
                <p class="card-text">Total élèves</p>
            </div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card text-center">
            <div class="card-body">
                <h4 class="text-danger"><?php echo number_format($general_stats['nombre_debiteurs']); ?></h4>
                <p class="card-text">Débiteurs</p>
            </div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card text-center">
            <div class="card-body">
                <h4 class="text-warning"><?php echo number_format($general_stats['dettes_actives']); ?></h4>
                <p class="card-text">Dettes actives (FC)</p>
            </div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card text-center">
            <div class="card-body">
                <h4 class="text-info"><?php echo number_format($general_stats['dette_moyenne']); ?></h4>
                <p class="card-text">Dette moyenne (FC)</p>
            </div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card text-center">
            <div class="card-body">
                <h4 class="text-success"><?php echo number_format($general_stats['total_paiements']); ?></h4>
                <p class="card-text">Paiements (FC)</p>
            </div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card text-center">
            <div class="card-body">
                <h4 class="text-success"><?php echo $general_stats['taux_recouvrement']; ?>%</h4>
                <p class="card-text">Taux recouvrement</p>
            </div>
        </div>
    </div>
</div>

<!-- Graphiques -->
<div class="row mb-4">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-chart-pie me-2"></i>
                    Répartition des dettes par niveau
                </h5>
            </div>
            <div class="card-body">
                <canvas id="dettesNiveauChart" height="300"></canvas>
            </div>
        </div>
    </div>
    
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-chart-line me-2"></i>
                    Évolution des paiements
                </h5>
            </div>
            <div class="card-body">
                <canvas id="evolutionPaiementsChart" height="300"></canvas>
            </div>
        </div>
    </div>
</div>

<!-- Dettes par niveau -->
<div class="card mb-4">
    <div class="card-header">
        <h5 class="mb-0">
            <i class="fas fa-layer-group me-2"></i>
            Dettes par niveau
        </h5>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>Niveau</th>
                        <th>Total élèves</th>
                        <th>Débiteurs</th>
                        <th>% Débiteurs</th>
                        <th>Total dettes</th>
                        <th>Dette moyenne</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($dettes_par_niveau as $niveau): ?>
                        <tr>
                            <td>
                                <strong><?php echo ucfirst($niveau['niveau']); ?></strong>
                            </td>
                            <td>
                                <span class="badge bg-primary"><?php echo $niveau['nombre_eleves']; ?></span>
                            </td>
                            <td>
                                <span class="badge bg-danger"><?php echo $niveau['nombre_debiteurs']; ?></span>
                            </td>
                            <td>
                                <div class="progress" style="height: 20px;">
                                    <div class="progress-bar bg-warning" role="progressbar" 
                                         style="width: <?php echo $niveau['pourcentage_debiteurs']; ?>%"
                                         aria-valuenow="<?php echo $niveau['pourcentage_debiteurs']; ?>" 
                                         aria-valuemin="0" aria-valuemax="100">
                                        <?php echo $niveau['pourcentage_debiteurs']; ?>%
                                    </div>
                                </div>
                            </td>
                            <td>
                                <strong><?php echo number_format($niveau['total_dettes']); ?> FC</strong>
                            </td>
                            <td>
                                <?php echo number_format($niveau['dette_moyenne']); ?> FC
                            </td>
                            <td>
                                <a href="niveau.php?niveau=<?php echo $niveau['niveau']; ?>" class="btn btn-sm btn-outline-primary">
                                    <i class="fas fa-eye"></i>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Top des débiteurs -->
<div class="card mb-4">
    <div class="card-header">
        <h5 class="mb-0">
            <i class="fas fa-exclamation-triangle me-2"></i>
            Top 10 des plus gros débiteurs
        </h5>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>Rang</th>
                        <th>Élève</th>
                        <th>Classe</th>
                        <th>Dette</th>
                        <th>Jours d'inscription</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($top_debiteurs as $index => $debiteur): ?>
                        <tr>
                            <td>
                                <span class="badge bg-danger"><?php echo $index + 1; ?></span>
                            </td>
                            <td>
                                <strong><?php echo htmlspecialchars($debiteur['nom'] . ' ' . $debiteur['prenom']); ?></strong>
                            </td>
                            <td>
                                <?php echo htmlspecialchars($debiteur['classe_nom']); ?>
                                <span class="badge bg-info"><?php echo ucfirst($debiteur['niveau']); ?></span>
                            </td>
                            <td>
                                <strong class="text-danger"><?php echo number_format($debiteur['montant_du']); ?> FC</strong>
                            </td>
                            <td>
                                <?php echo $debiteur['jours_inscription']; ?> jours
                            </td>
                            <td>
                                <div class="btn-group btn-group-sm">
                                    <a href="../notifications/index.php?eleve_id=<?php echo $debiteur['id']; ?>" class="btn btn-outline-warning" title="Envoyer notification">
                                        <i class="fas fa-bell"></i>
                                    </a>
                                    <a href="../paiements/historique.php?eleve_id=<?php echo $debiteur['id']; ?>" class="btn btn-outline-info" title="Historique paiements">
                                        <i class="fas fa-history"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Afficher/masquer les champs de dates personnalisées
function toggleCustomDates() {
    const period = document.getElementById('period').value;
    const dateFromCol = document.getElementById('date-from-col');
    const dateToCol = document.getElementById('date-to-col');
    
    if (period === 'custom') {
        dateFromCol.style.display = 'block';
        dateToCol.style.display = 'block';
    } else {
        dateFromCol.style.display = 'none';
        dateToCol.style.display = 'none';
    }
}

// Graphique des dettes par niveau
const dettesNiveauData = <?php echo json_encode($dettes_par_niveau); ?>;
const ctx1 = document.getElementById('dettesNiveauChart').getContext('2d');

new Chart(ctx1, {
    type: 'doughnut',
    data: {
        labels: dettesNiveauData.map(item => ucfirst(item.niveau)),
        datasets: [{
            data: dettesNiveauData.map(item => item.total_dettes),
            backgroundColor: [
                '#FF6384',
                '#36A2EB',
                '#FFCE56',
                '#4BC0C0',
                '#9966FF',
                '#FF9F40'
            ]
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: 'bottom'
            },
            tooltip: {
                callbacks: {
                    label: function(context) {
                        return context.label + ': ' + new Intl.NumberFormat('fr-FR').format(context.parsed) + ' FC';
                    }
                }
            }
        }
    }
});

// Graphique d'évolution des paiements
const evolutionData = <?php echo json_encode(array_reverse($evolution_paiements)); ?>;
const ctx2 = document.getElementById('evolutionPaiementsChart').getContext('2d');

new Chart(ctx2, {
    type: 'line',
    data: {
        labels: evolutionData.map(item => {
            const date = new Date(item.mois + '-01');
            return date.toLocaleDateString('fr-FR', { year: 'numeric', month: 'short' });
        }),
        datasets: [
            {
                label: 'Montant des paiements (FC)',
                data: evolutionData.map(item => item.total_paiements),
                borderColor: '#36A2EB',
                backgroundColor: 'rgba(54, 162, 235, 0.1)',
                yAxisID: 'y'
            },
            {
                label: 'Nombre de payeurs',
                data: evolutionData.map(item => item.nombre_payeurs),
                borderColor: '#FF6384',
                backgroundColor: 'rgba(255, 99, 132, 0.1)',
                yAxisID: 'y1'
            }
        ]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        interaction: {
            mode: 'index',
            intersect: false,
        },
        scales: {
            x: {
                display: true,
                title: {
                    display: true,
                    text: 'Mois'
                }
            },
            y: {
                type: 'linear',
                display: true,
                position: 'left',
                title: {
                    display: true,
                    text: 'Montant (FC)'
                }
            },
            y1: {
                type: 'linear',
                display: true,
                position: 'right',
                title: {
                    display: true,
                    text: 'Nombre de payeurs'
                },
                grid: {
                    drawOnChartArea: false,
                },
            }
        }
    }
});

function ucfirst(string) {
    return string.charAt(0).toUpperCase() + string.slice(1);
}
</script>

<?php include '../../../includes/footer.php'; ?>
