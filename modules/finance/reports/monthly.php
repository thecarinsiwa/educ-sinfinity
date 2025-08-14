<?php
/**
 * Module de gestion financière - Rapport mensuel
 * Application de gestion scolaire - République Démocratique du Congo
 */

require_once '../../../config/config.php';
require_once '../../../config/database.php';
require_once '../../../includes/functions.php';

// Vérifier l'authentification et les permissions
requireLogin();
if (!checkPermission('finance') && !checkPermission('finance_view')) {
    showMessage('error', 'Accès refusé à ce module.');
    redirectTo('../index.php');
}

$page_title = 'Rapport Mensuel';

// Obtenir l'année scolaire actuelle
$current_year = getCurrentAcademicYear();

// Paramètres de période
$annee = (int)($_GET['annee'] ?? date('Y'));
$mois = (int)($_GET['mois'] ?? date('n'));
$periode_comparaison = sanitizeInput($_GET['comparaison'] ?? 'mois_precedent');

// Calculer les dates
$date_debut = date('Y-m-01', strtotime("$annee-$mois-01"));
$date_fin = date('Y-m-t', strtotime("$annee-$mois-01"));
$mois_nom = date('F Y', strtotime("$annee-$mois-01"));

// Calculer les dates de comparaison
$date_comparaison_debut = '';
$date_comparaison_fin = '';
$periode_comparaison_nom = '';

switch ($periode_comparaison) {
    case 'mois_precedent':
        $date_comparaison_debut = date('Y-m-01', strtotime("$annee-$mois-01 -1 month"));
        $date_comparaison_fin = date('Y-m-t', strtotime("$annee-$mois-01 -1 month"));
        $periode_comparaison_nom = date('F Y', strtotime("$annee-$mois-01 -1 month"));
        break;
    case 'meme_mois_annee_precedente':
        $date_comparaison_debut = date('Y-m-01', strtotime("$annee-$mois-01 -1 year"));
        $date_comparaison_fin = date('Y-m-t', strtotime("$annee-$mois-01 -1 year"));
        $periode_comparaison_nom = date('F Y', strtotime("$annee-$mois-01 -1 year"));
        break;
    case 'trimestre_precedent':
        $trimestre = ceil($mois / 3);
        $mois_debut_trimestre = ($trimestre - 1) * 3 + 1;
        $date_comparaison_debut = date('Y-m-01', strtotime("$annee-" . str_pad($mois_debut_trimestre, 2, '0', STR_PAD_LEFT) . "-01 -3 months"));
        $date_comparaison_fin = date('Y-m-t', strtotime("$annee-" . str_pad($mois_debut_trimestre, 2, '0', STR_PAD_LEFT) . "-01 -1 month"));
        $periode_comparaison_nom = 'Trimestre précédent';
        break;
}

// Statistiques du mois en cours
$stats_mois = [];

try {
    // Recettes totales du mois
    $recettes_mois = $database->query(
        "SELECT 
            SUM(montant) as total_recettes,
            COUNT(*) as nombre_paiements,
            AVG(montant) as montant_moyen
         FROM paiements 
         WHERE date_paiement BETWEEN ? AND ?
         AND annee_scolaire_id = ?",
        [$date_debut, $date_fin, $current_year['id'] ?? 0]
    )->fetch();
    
    $stats_mois['total_recettes'] = $recettes_mois['total_recettes'] ?? 0;
    $stats_mois['nombre_paiements'] = $recettes_mois['nombre_paiements'] ?? 0;
    $stats_mois['montant_moyen'] = $recettes_mois['montant_moyen'] ?? 0;
    
    // Recettes par type de frais
    $recettes_par_type = $database->query(
        "SELECT 
            type_frais,
            SUM(montant) as total,
            COUNT(*) as nombre
         FROM paiements 
         WHERE date_paiement BETWEEN ? AND ?
         AND annee_scolaire_id = ?
         GROUP BY type_frais
         ORDER BY total DESC",
        [$date_debut, $date_fin, $current_year['id'] ?? 0]
    )->fetchAll();
    
    $stats_mois['recettes_par_type'] = $recettes_par_type;
    
    // Recettes par mode de paiement
    $recettes_par_mode = $database->query(
        "SELECT 
            mode_paiement,
            SUM(montant) as total,
            COUNT(*) as nombre
         FROM paiements 
         WHERE date_paiement BETWEEN ? AND ?
         AND annee_scolaire_id = ?
         GROUP BY mode_paiement
         ORDER BY total DESC",
        [$date_debut, $date_fin, $current_year['id'] ?? 0]
    )->fetchAll();
    
    $stats_mois['recettes_par_mode'] = $recettes_par_mode;
    
    // Recettes par classe/niveau
    $recettes_par_niveau = $database->query(
        "SELECT 
            c.niveau,
            c.nom as classe_nom,
            SUM(p.montant) as total,
            COUNT(p.id) as nombre
         FROM paiements p
         JOIN eleves e ON p.eleve_id = e.id
         JOIN inscriptions i ON e.id = i.eleve_id AND i.annee_scolaire_id = p.annee_scolaire_id
         JOIN classes c ON i.classe_id = c.id
         WHERE p.date_paiement BETWEEN ? AND ?
         AND p.annee_scolaire_id = ?
         GROUP BY c.niveau, c.nom
         ORDER BY total DESC",
        [$date_debut, $date_fin, $current_year['id'] ?? 0]
    )->fetchAll();
    
    $stats_mois['recettes_par_niveau'] = $recettes_par_niveau;
    
    // Évolution quotidienne
    $evolution_quotidienne = $database->query(
        "SELECT 
            DATE(date_paiement) as date,
            SUM(montant) as total,
            COUNT(*) as nombre
         FROM paiements 
         WHERE date_paiement BETWEEN ? AND ?
         AND annee_scolaire_id = ?
         GROUP BY DATE(date_paiement)
         ORDER BY date",
        [$date_debut, $date_fin, $current_year['id'] ?? 0]
    )->fetchAll();
    
    $stats_mois['evolution_quotidienne'] = $evolution_quotidienne;
    
} catch (Exception $e) {
    showMessage('error', 'Erreur lors de la récupération des données : ' . $e->getMessage());
}

// Statistiques de comparaison
$stats_comparaison = [];

if (!empty($date_comparaison_debut) && !empty($date_comparaison_fin)) {
    try {
        $recettes_comparaison = $database->query(
            "SELECT 
                SUM(montant) as total_recettes,
                COUNT(*) as nombre_paiements,
                AVG(montant) as montant_moyen
             FROM paiements 
             WHERE date_paiement BETWEEN ? AND ?
             AND annee_scolaire_id = ?",
            [$date_comparaison_debut, $date_comparaison_fin, $current_year['id'] ?? 0]
        )->fetch();
        
        $stats_comparaison['total_recettes'] = $recettes_comparaison['total_recettes'] ?? 0;
        $stats_comparaison['nombre_paiements'] = $recettes_comparaison['nombre_paiements'] ?? 0;
        $stats_comparaison['montant_moyen'] = $recettes_comparaison['montant_moyen'] ?? 0;
        
    } catch (Exception $e) {
        // Ignorer l'erreur pour la comparaison
    }
}

// Calculer les variations
$variations = [];
if (!empty($stats_comparaison)) {
    $variations['recettes'] = $stats_mois['total_recettes'] > 0 && $stats_comparaison['total_recettes'] > 0 
        ? (($stats_mois['total_recettes'] - $stats_comparaison['total_recettes']) / $stats_comparaison['total_recettes']) * 100 
        : 0;
    
    $variations['paiements'] = $stats_mois['nombre_paiements'] > 0 && $stats_comparaison['nombre_paiements'] > 0 
        ? (($stats_mois['nombre_paiements'] - $stats_comparaison['nombre_paiements']) / $stats_comparaison['nombre_paiements']) * 100 
        : 0;
    
    $variations['moyen'] = $stats_mois['montant_moyen'] > 0 && $stats_comparaison['montant_moyen'] > 0 
        ? (($stats_mois['montant_moyen'] - $stats_comparaison['montant_moyen']) / $stats_comparaison['montant_moyen']) * 100 
        : 0;
}

include '../../../includes/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">
        <i class="fas fa-chart-line me-2"></i>
        Rapport Mensuel - <?php echo $mois_nom; ?>
    </h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <div class="btn-group me-2">
            <a href="index.php" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-1"></i>
                Retour
            </a>
        </div>
        <div class="btn-group">
            <button type="button" class="btn btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">
                <i class="fas fa-download me-1"></i>
                Exporter
            </button>
            <ul class="dropdown-menu">
                <li><a class="dropdown-item" href="export-monthly.php?format=excel&annee=<?php echo $annee; ?>&mois=<?php echo $mois; ?>">
                    <i class="fas fa-file-excel me-2"></i>Excel
                </a></li>
                <li><a class="dropdown-item" href="export-monthly.php?format=pdf&annee=<?php echo $annee; ?>&mois=<?php echo $mois; ?>">
                    <i class="fas fa-file-pdf me-2"></i>PDF
                </a></li>
            </ul>
        </div>
    </div>
</div>

<!-- Sélecteur de période -->
<div class="card mb-4">
    <div class="card-header">
        <h5 class="mb-0">
            <i class="fas fa-calendar me-2"></i>
            Sélection de la période
        </h5>
    </div>
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-md-3">
                <label for="annee" class="form-label">Année</label>
                <select name="annee" id="annee" class="form-select">
                    <?php for ($a = date('Y') - 2; $a <= date('Y') + 1; $a++): ?>
                        <option value="<?php echo $a; ?>" <?php echo $annee == $a ? 'selected' : ''; ?>>
                            <?php echo $a; ?>
                        </option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label for="mois" class="form-label">Mois</label>
                <select name="mois" id="mois" class="form-select">
                    <?php for ($m = 1; $m <= 12; $m++): ?>
                        <option value="<?php echo $m; ?>" <?php echo $mois == $m ? 'selected' : ''; ?>>
                            <?php echo date('F', mktime(0, 0, 0, $m, 1)); ?>
                        </option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label for="comparaison" class="form-label">Comparaison avec</label>
                <select name="comparaison" id="comparaison" class="form-select">
                    <option value="mois_precedent" <?php echo $periode_comparaison === 'mois_precedent' ? 'selected' : ''; ?>>Mois précédent</option>
                    <option value="meme_mois_annee_precedente" <?php echo $periode_comparaison === 'meme_mois_annee_precedente' ? 'selected' : ''; ?>>Même mois année précédente</option>
                    <option value="trimestre_precedent" <?php echo $periode_comparaison === 'trimestre_precedent' ? 'selected' : ''; ?>>Trimestre précédent</option>
                </select>
            </div>
            <div class="col-md-3 d-flex align-items-end">
                <div class="d-grid gap-2 w-100">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search me-1"></i>
                        Analyser
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Statistiques principales -->
<div class="row mb-4">
    <div class="col-md-4">
        <div class="card text-white bg-primary">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h4><?php echo formatMoney($stats_mois['total_recettes']); ?></h4>
                        <p class="mb-0">Recettes totales</p>
                        <?php if (!empty($variations) && isset($variations['recettes'])): ?>
                            <small class="<?php echo $variations['recettes'] >= 0 ? 'text-success' : 'text-danger'; ?>">
                                <i class="fas fa-<?php echo $variations['recettes'] >= 0 ? 'arrow-up' : 'arrow-down'; ?>"></i>
                                <?php echo abs(round($variations['recettes'], 1)); ?>% vs <?php echo $periode_comparaison_nom; ?>
                            </small>
                        <?php endif; ?>
                    </div>
                    <div class="align-self-center">
                        <i class="fas fa-money-bill-wave fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card text-white bg-success">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h4><?php echo $stats_mois['nombre_paiements']; ?></h4>
                        <p class="mb-0">Nombre de paiements</p>
                        <?php if (!empty($variations) && isset($variations['paiements'])): ?>
                            <small class="<?php echo $variations['paiements'] >= 0 ? 'text-success' : 'text-danger'; ?>">
                                <i class="fas fa-<?php echo $variations['paiements'] >= 0 ? 'arrow-up' : 'arrow-down'; ?>"></i>
                                <?php echo abs(round($variations['paiements'], 1)); ?>% vs <?php echo $periode_comparaison_nom; ?>
                            </small>
                        <?php endif; ?>
                    </div>
                    <div class="align-self-center">
                        <i class="fas fa-credit-card fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card text-white bg-info">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h4><?php echo formatMoney($stats_mois['montant_moyen']); ?></h4>
                        <p class="mb-0">Montant moyen</p>
                        <?php if (!empty($variations) && isset($variations['moyen'])): ?>
                            <small class="<?php echo $variations['moyen'] >= 0 ? 'text-success' : 'text-danger'; ?>">
                                <i class="fas fa-<?php echo $variations['moyen'] >= 0 ? 'arrow-up' : 'arrow-down'; ?>"></i>
                                <?php echo abs(round($variations['moyen'], 1)); ?>% vs <?php echo $periode_comparaison_nom; ?>
                            </small>
                        <?php endif; ?>
                    </div>
                    <div class="align-self-center">
                        <i class="fas fa-calculator fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Graphiques et analyses -->
<div class="row mb-4">
    <!-- Évolution quotidienne -->
    <div class="col-md-8">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-chart-area me-2"></i>
                    Évolution quotidienne des recettes
                </h5>
            </div>
            <div class="card-body">
                <?php if (!empty($stats_mois['evolution_quotidienne'])): ?>
                    <canvas id="evolutionChart" width="400" height="200"></canvas>
                <?php else: ?>
                    <div class="text-center py-4">
                        <i class="fas fa-chart-line fa-3x text-muted mb-3"></i>
                        <p class="text-muted">Aucune donnée disponible pour ce mois.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Répartition par type -->
    <div class="col-md-4">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-chart-pie me-2"></i>
                    Répartition par type de frais
                </h5>
            </div>
            <div class="card-body">
                <?php if (!empty($stats_mois['recettes_par_type'])): ?>
                    <canvas id="typeChart" width="400" height="200"></canvas>
                <?php else: ?>
                    <div class="text-center py-4">
                        <i class="fas fa-chart-pie fa-3x text-muted mb-3"></i>
                        <p class="text-muted">Aucune donnée disponible.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Tableaux détaillés -->
<div class="row mb-4">
    <!-- Recettes par type -->
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-list me-2"></i>
                    Recettes par type de frais
                </h5>
            </div>
            <div class="card-body">
                <?php if (!empty($stats_mois['recettes_par_type'])): ?>
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Type</th>
                                    <th>Montant</th>
                                    <th>Nombre</th>
                                    <th>%</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($stats_mois['recettes_par_type'] as $type): ?>
                                    <tr>
                                        <td>
                                            <span class="badge bg-secondary">
                                                <?php echo ucfirst($type['type_frais']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <strong class="text-success">
                                                <?php echo formatMoney($type['total']); ?>
                                            </strong>
                                        </td>
                                        <td><?php echo $type['nombre']; ?></td>
                                        <td>
                                            <span class="badge bg-light text-dark">
                                                <?php echo $stats_mois['total_recettes'] > 0 ? round(($type['total'] / $stats_mois['total_recettes']) * 100, 1) : 0; ?>%
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="text-center py-3">
                        <p class="text-muted">Aucune donnée disponible.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Recettes par mode de paiement -->
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-credit-card me-2"></i>
                    Recettes par mode de paiement
                </h5>
            </div>
            <div class="card-body">
                <?php if (!empty($stats_mois['recettes_par_mode'])): ?>
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Mode</th>
                                    <th>Montant</th>
                                    <th>Nombre</th>
                                    <th>%</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($stats_mois['recettes_par_mode'] as $mode): ?>
                                    <tr>
                                        <td>
                                            <span class="badge bg-info">
                                                <?php echo ucfirst($mode['mode_paiement']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <strong class="text-success">
                                                <?php echo formatMoney($mode['total']); ?>
                                            </strong>
                                        </td>
                                        <td><?php echo $mode['nombre']; ?></td>
                                        <td>
                                            <span class="badge bg-light text-dark">
                                                <?php echo $stats_mois['total_recettes'] > 0 ? round(($mode['total'] / $stats_mois['total_recettes']) * 100, 1) : 0; ?>%
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="text-center py-3">
                        <p class="text-muted">Aucune donnée disponible.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Recettes par niveau -->
<?php if (!empty($stats_mois['recettes_par_niveau'])): ?>
<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-school me-2"></i>
                    Recettes par niveau et classe
                </h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Niveau</th>
                                <th>Classe</th>
                                <th>Montant total</th>
                                <th>Nombre de paiements</th>
                                <th>Montant moyen</th>
                                <th>% du total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($stats_mois['recettes_par_niveau'] as $niveau): ?>
                                <tr>
                                    <td>
                                        <span class="badge bg-<?php 
                                            echo $niveau['niveau'] === 'maternelle' ? 'warning' : 
                                                ($niveau['niveau'] === 'primaire' ? 'success' : 'primary'); 
                                        ?>">
                                            <?php echo ucfirst($niveau['niveau']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo htmlspecialchars($niveau['classe_nom']); ?></td>
                                    <td>
                                        <strong class="text-success">
                                            <?php echo formatMoney($niveau['total']); ?>
                                        </strong>
                                    </td>
                                    <td><?php echo $niveau['nombre']; ?></td>
                                    <td><?php echo formatMoney($niveau['total'] / $niveau['nombre']); ?></td>
                                    <td>
                                        <span class="badge bg-light text-dark">
                                            <?php echo $stats_mois['total_recettes'] > 0 ? round(($niveau['total'] / $stats_mois['total_recettes']) * 100, 1) : 0; ?>%
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Scripts pour les graphiques -->
<?php if (!empty($stats_mois['evolution_quotidienne']) || !empty($stats_mois['recettes_par_type'])): ?>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
<?php if (!empty($stats_mois['evolution_quotidienne'])): ?>
// Graphique d'évolution quotidienne
const evolutionCtx = document.getElementById('evolutionChart').getContext('2d');
const evolutionChart = new Chart(evolutionCtx, {
    type: 'line',
    data: {
        labels: [<?php echo implode(',', array_map(function($item) { return '"' . date('d/m', strtotime($item['date'])) . '"'; }, $stats_mois['evolution_quotidienne'])); ?>],
        datasets: [{
            label: 'Recettes quotidiennes (FC)',
            data: [<?php echo implode(',', array_column($stats_mois['evolution_quotidienne'], 'total')); ?>],
            borderColor: 'rgb(75, 192, 192)',
            backgroundColor: 'rgba(75, 192, 192, 0.2)',
            tension: 0.1
        }]
    },
    options: {
        responsive: true,
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    callback: function(value) {
                        return value.toLocaleString() + ' FC';
                    }
                }
            }
        },
        plugins: {
            tooltip: {
                callbacks: {
                    label: function(context) {
                        return context.parsed.y.toLocaleString() + ' FC';
                    }
                }
            }
        }
    }
});
<?php endif; ?>

<?php if (!empty($stats_mois['recettes_par_type'])): ?>
// Graphique en camembert pour les types de frais
const typeCtx = document.getElementById('typeChart').getContext('2d');
const typeChart = new Chart(typeCtx, {
    type: 'doughnut',
    data: {
        labels: [<?php echo implode(',', array_map(function($item) { return '"' . ucfirst($item['type_frais']) . '"'; }, $stats_mois['recettes_par_type'])); ?>],
        datasets: [{
            data: [<?php echo implode(',', array_column($stats_mois['recettes_par_type'], 'total')); ?>],
            backgroundColor: [
                '#FF6384',
                '#36A2EB',
                '#FFCE56',
                '#4BC0C0',
                '#9966FF',
                '#FF9F40',
                '#FF6384'
            ]
        }]
    },
    options: {
        responsive: true,
        plugins: {
            legend: {
                position: 'bottom'
            },
            tooltip: {
                callbacks: {
                    label: function(context) {
                        const total = context.dataset.data.reduce((a, b) => a + b, 0);
                        const percentage = ((context.parsed / total) * 100).toFixed(1);
                        return context.label + ': ' + context.parsed.toLocaleString() + ' FC (' + percentage + '%)';
                    }
                }
            }
        }
    }
});
<?php endif; ?>
</script>
<?php endif; ?>

<?php include '../../../includes/footer.php'; ?>
