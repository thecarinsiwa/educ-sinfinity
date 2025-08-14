<?php
/**
 * Module de recouvrement - Tableau de bord
 * Application de gestion scolaire - République Démocratique du Congo
 */

require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../includes/functions.php';

// Vérifier l'authentification et les permissions
requireLogin();
if (!checkPermission('recouvrement') && !checkPermission('recouvrement_view')) {
    showMessage('error', 'Accès refusé à ce module.');
    redirectTo('../dashboard.php');
}

$page_title = 'Recouvrement';

// Obtenir l'année scolaire actuelle
$current_year = getCurrentAcademicYear();

// Statistiques de recouvrement
$stats = [];

try {
    // Total des dettes
    $total_dettes = $database->query(
        "SELECT 
            COUNT(DISTINCT e.id) as nombre_debiteurs,
            SUM(dette.montant_du) as total_dettes
         FROM eleves e
         JOIN inscriptions i ON e.id = i.eleve_id
         JOIN (
             SELECT 
                 e.id as eleve_id,
                 SUM(fs.montant) - COALESCE(SUM(p.montant), 0) as montant_du
             FROM eleves e
             JOIN inscriptions i ON e.id = i.eleve_id
             JOIN frais_scolaires fs ON i.classe_id = fs.classe_id
             LEFT JOIN paiements p ON e.id = p.eleve_id 
                 AND fs.type_frais COLLATE utf8mb4_unicode_ci = p.type_paiement COLLATE utf8mb4_unicode_ci
                 AND p.annee_scolaire_id = fs.annee_scolaire_id
             WHERE i.annee_scolaire_id = ? 
                 AND fs.annee_scolaire_id = ?
             GROUP BY e.id
             HAVING montant_du > 0
         ) dette ON e.id = dette.eleve_id
         WHERE i.annee_scolaire_id = ?",
        [$current_year['id'], $current_year['id'], $current_year['id']]
    )->fetch();
    
    $stats['nombre_debiteurs'] = $total_dettes['nombre_debiteurs'] ?? 0;
    $stats['total_dettes'] = $total_dettes['total_dettes'] ?? 0;
    
    // Dettes par niveau
    $dettes_par_niveau = $database->query(
        "SELECT 
            c.niveau,
            COUNT(DISTINCT e.id) as nombre_debiteurs,
            SUM(dette.montant_du) as total_dettes
         FROM eleves e
         JOIN inscriptions i ON e.id = i.eleve_id
         JOIN classes c ON i.classe_id = c.id
         JOIN (
             SELECT 
                 e.id as eleve_id,
                 SUM(fs.montant) - COALESCE(SUM(p.montant), 0) as montant_du
             FROM eleves e
             JOIN inscriptions i ON e.id = i.eleve_id
             JOIN frais_scolaires fs ON i.classe_id = fs.classe_id
             LEFT JOIN paiements p ON e.id = p.eleve_id 
                 AND fs.type_frais COLLATE utf8mb4_unicode_ci = p.type_paiement COLLATE utf8mb4_unicode_ci
                 AND p.annee_scolaire_id = fs.annee_scolaire_id
             WHERE i.annee_scolaire_id = ? 
                 AND fs.annee_scolaire_id = ?
             GROUP BY e.id
             HAVING montant_du > 0
         ) dette ON e.id = dette.eleve_id
         WHERE i.annee_scolaire_id = ?
         GROUP BY c.niveau
         ORDER BY total_dettes DESC",
        [$current_year['id'], $current_year['id'], $current_year['id']]
    )->fetchAll();
    
    $stats['dettes_par_niveau'] = $dettes_par_niveau;
    
    // Dettes par classe
    $dettes_par_classe = $database->query(
        "SELECT 
            c.nom as classe_nom,
            c.niveau,
            COUNT(DISTINCT e.id) as nombre_debiteurs,
            SUM(dette.montant_du) as total_dettes
         FROM eleves e
         JOIN inscriptions i ON e.id = i.eleve_id
         JOIN classes c ON i.classe_id = c.id
         JOIN (
        SELECT 
                 e.id as eleve_id,
                 SUM(fs.montant) - COALESCE(SUM(p.montant), 0) as montant_du
             FROM eleves e
             JOIN inscriptions i ON e.id = i.eleve_id
             JOIN frais_scolaires fs ON i.classe_id = fs.classe_id
             LEFT JOIN paiements p ON e.id = p.eleve_id 
                 AND fs.type_frais COLLATE utf8mb4_unicode_ci = p.type_paiement COLLATE utf8mb4_unicode_ci
                 AND p.annee_scolaire_id = fs.annee_scolaire_id
             WHERE i.annee_scolaire_id = ? 
                 AND fs.annee_scolaire_id = ?
             GROUP BY e.id
             HAVING montant_du > 0
         ) dette ON e.id = dette.eleve_id
         WHERE i.annee_scolaire_id = ?
         GROUP BY c.id, c.nom, c.niveau
         ORDER BY total_dettes DESC
         LIMIT 10",
        [$current_year['id'], $current_year['id'], $current_year['id']]
    )->fetchAll();
    
    $stats['dettes_par_classe'] = $dettes_par_classe;
    
    // Dettes par type de frais
    $dettes_par_type = $database->query(
        "SELECT 
            fs.type_frais,
            COUNT(DISTINCT e.id) as nombre_debiteurs,
            SUM(fs.montant - COALESCE(p.montant_paye, 0)) as total_dettes
         FROM eleves e
         JOIN inscriptions i ON e.id = i.eleve_id
         JOIN frais_scolaires fs ON i.classe_id = fs.classe_id
         LEFT JOIN (
        SELECT 
                 eleve_id,
                 type_paiement,
                 SUM(montant) as montant_paye
             FROM paiements 
             WHERE annee_scolaire_id = ?
             GROUP BY eleve_id, type_paiement
         ) p ON e.id = p.eleve_id AND fs.type_frais COLLATE utf8mb4_unicode_ci = p.type_paiement COLLATE utf8mb4_unicode_ci
         WHERE i.annee_scolaire_id = ? AND fs.annee_scolaire_id = ?
         GROUP BY fs.type_frais
         HAVING total_dettes > 0
         ORDER BY total_dettes DESC",
        [$current_year['id'], $current_year['id'], $current_year['id']]
    )->fetchAll();
    
    $stats['dettes_par_type'] = $dettes_par_type;
    
} catch (Exception $e) {
    showMessage('error', 'Erreur lors de la récupération des statistiques : ' . $e->getMessage());
}

include '../../includes/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">
        <i class="fas fa-hand-holding-usd me-2"></i>
        Recouvrement
    </h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <div class="btn-group me-2">
            <a href="../finance/reports/debtors.php" class="btn btn-outline-danger">
                <i class="fas fa-exclamation-triangle me-1"></i>
                Voir les débiteurs
            </a>
        </div>
        <div class="btn-group">
            <button type="button" class="btn btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">
                <i class="fas fa-download me-1"></i>
                Exporter
            </button>
            <ul class="dropdown-menu">
                <li><a class="dropdown-item" href="export-debts.php?format=excel">
                    <i class="fas fa-file-excel me-2"></i>Rapport des dettes (Excel)
                </a></li>
                <li><a class="dropdown-item" href="export-debts.php?format=pdf">
                    <i class="fas fa-file-pdf me-2"></i>Rapport des dettes (PDF)
                </a></li>
            </ul>
        </div>
    </div>
</div>

<!-- Statistiques principales -->
<div class="row mb-4">
    <div class="col-md-6">
        <div class="card text-white bg-danger">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h4><?php echo $stats['nombre_debiteurs']; ?></h4>
                        <p class="mb-0">Élèves débiteurs</p>
                    </div>
                    <div class="align-self-center">
                        <i class="fas fa-users fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card text-white bg-warning">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h4><?php echo formatMoney($stats['total_dettes']); ?></h4>
                        <p class="mb-0">Total des dettes</p>
                    </div>
                    <div class="align-self-center">
                        <i class="fas fa-money-bill-wave fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Actions rapides -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-bolt me-2"></i>
                    Actions rapides
                </h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-3 mb-3">
                        <div class="d-grid">
                            <a href="../finance/reports/debtors.php" class="btn btn-outline-danger">
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                Liste des débiteurs
                        </a>
                    </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="d-grid">
                            <a href="campaigns/" class="btn btn-outline-primary">
                                <i class="fas fa-bullhorn me-2"></i>
                                Campagnes de recouvrement
                        </a>
                    </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="d-grid">
                            <a href="notifications/" class="btn btn-outline-warning">
                                <i class="fas fa-bell me-2"></i>
                                Notifications parents
                        </a>
                    </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="d-grid">
                            <a href="reports/" class="btn btn-outline-info">
                                <i class="fas fa-chart-bar me-2"></i>
                                Rapports
                        </a>
                    </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Graphiques et analyses -->
<div class="row mb-4">
    <!-- Dettes par niveau -->
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-chart-pie me-2"></i>
                    Dettes par niveau
                </h5>
            </div>
            <div class="card-body">
                <?php if (!empty($stats['dettes_par_niveau'])): ?>
                    <canvas id="niveauChart" width="400" height="200"></canvas>
                                            <?php else: ?>
                    <div class="text-center py-4">
                        <i class="fas fa-chart-pie fa-3x text-muted mb-3"></i>
                        <p class="text-muted">Aucune dette enregistrée.</p>
                                    </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
    
    <!-- Dettes par type -->
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-chart-bar me-2"></i>
                    Dettes par type de frais
                </h5>
                    </div>
            <div class="card-body">
                <?php if (!empty($stats['dettes_par_type'])): ?>
                    <canvas id="typeChart" width="400" height="200"></canvas>
                <?php else: ?>
                    <div class="text-center py-4">
                        <i class="fas fa-chart-bar fa-3x text-muted mb-3"></i>
                        <p class="text-muted">Aucune dette enregistrée.</p>
                    </div>
                <?php endif; ?>
            </div>
            </div>
        </div>
    </div>

<!-- Tableaux détaillés -->
<div class="row mb-4">
    <!-- Dettes par niveau -->
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-list me-2"></i>
                    Dettes par niveau
                </h5>
            </div>
            <div class="card-body">
                <?php if (!empty($stats['dettes_par_niveau'])): ?>
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Niveau</th>
                                    <th>Débiteurs</th>
                                    <th>Total dettes</th>
                                    <th>%</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($stats['dettes_par_niveau'] as $niveau): ?>
                                    <tr>
                                        <td>
                                            <span class="badge bg-<?php 
                                                echo $niveau['niveau'] === 'maternelle' ? 'warning' : 
                                                    ($niveau['niveau'] === 'primaire' ? 'success' : 'primary'); 
                                            ?>">
                                                <?php echo ucfirst($niveau['niveau']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo $niveau['nombre_debiteurs']; ?></td>
                                        <td>
                                            <strong class="text-danger">
                                                <?php echo formatMoney($niveau['total_dettes']); ?>
                                            </strong>
                                        </td>
                                        <td>
                                            <span class="badge bg-light text-dark">
                                                <?php echo $stats['total_dettes'] > 0 ? round(($niveau['total_dettes'] / $stats['total_dettes']) * 100, 1) : 0; ?>%
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

    <!-- Dettes par classe -->
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-school me-2"></i>
                    Top 10 des classes avec dettes
                </h5>
            </div>
            <div class="card-body">
                <?php if (!empty($stats['dettes_par_classe'])): ?>
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Classe</th>
                                    <th>Débiteurs</th>
                                    <th>Total dettes</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($stats['dettes_par_classe'] as $classe): ?>
                                    <tr>
                                        <td>
                                            <span class="badge bg-<?php 
                                                echo $classe['niveau'] === 'maternelle' ? 'warning' : 
                                                    ($classe['niveau'] === 'primaire' ? 'success' : 'primary'); 
                                            ?>">
                                                <?php echo htmlspecialchars($classe['classe_nom']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo $classe['nombre_debiteurs']; ?></td>
                                        <td>
                                            <strong class="text-danger">
                                                <?php echo formatMoney($classe['total_dettes']); ?>
                                            </strong>
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

<!-- Scripts pour les graphiques -->
<?php if (!empty($stats['dettes_par_niveau']) || !empty($stats['dettes_par_type'])): ?>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
<?php if (!empty($stats['dettes_par_niveau'])): ?>
// Graphique en camembert pour les niveaux
const niveauCtx = document.getElementById('niveauChart').getContext('2d');
const niveauChart = new Chart(niveauCtx, {
    type: 'doughnut',
    data: {
        labels: [<?php echo implode(',', array_map(function($item) { return '"' . ucfirst($item['niveau']) . '"'; }, $stats['dettes_par_niveau'])); ?>],
        datasets: [{
            data: [<?php echo implode(',', array_column($stats['dettes_par_niveau'], 'total_dettes')); ?>],
            backgroundColor: [
                '#FF6384',
                '#36A2EB',
                '#FFCE56',
                '#4BC0C0',
                '#9966FF'
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

<?php if (!empty($stats['dettes_par_type'])): ?>
// Graphique en barres pour les types de frais
const typeCtx = document.getElementById('typeChart').getContext('2d');
const typeChart = new Chart(typeCtx, {
    type: 'bar',
    data: {
        labels: [<?php echo implode(',', array_map(function($item) { return '"' . ucfirst($item['type_frais']) . '"'; }, $stats['dettes_par_type'])); ?>],
        datasets: [{
            label: 'Dettes par type (FC)',
            data: [<?php echo implode(',', array_column($stats['dettes_par_type'], 'total_dettes')); ?>],
            backgroundColor: 'rgba(255, 99, 132, 0.8)',
            borderColor: 'rgba(255, 99, 132, 1)',
            borderWidth: 1
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
</script>
<?php endif; ?>

<?php include '../../includes/footer.php'; ?>
