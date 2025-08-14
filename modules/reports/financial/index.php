<?php
/**
 * Module Rapports Financiers - Page principale
 * Application de gestion scolaire - République Démocratique du Congo
 */

require_once '../../../config/config.php';
require_once '../../../config/database.php';
require_once '../../../includes/functions.php';

// Vérifier l'authentification
requireLogin();
if (!checkPermission('finances') && !checkPermission('finances_view')) {
    showMessage('error', 'Accès refusé à ce module.');
    redirectTo('../index.php');
}

$page_title = 'Rapports Financiers';

// Obtenir l'année scolaire actuelle
$current_year = getCurrentAcademicYear();

// Paramètres de filtrage
$mois_filter = sanitizeInput($_GET['mois'] ?? date('Y-m'));
$type_filter = sanitizeInput($_GET['type'] ?? '');

// Statistiques financières
$stats = [];

// Recettes totales de l'année
$stmt = $database->query(
    "SELECT SUM(montant) as total FROM paiements WHERE status = 'valide' AND annee_scolaire_id = ?",
    [$current_year['id'] ?? 0]
);
$stats['recettes_annee'] = $stmt->fetch()['total'] ?? 0;

// Recettes du mois en cours
$stmt = $database->query(
    "SELECT SUM(montant) as total FROM paiements 
     WHERE status = 'valide' 
     AND DATE_FORMAT(date_paiement, '%Y-%m') = ?
     AND annee_scolaire_id = ?",
    [date('Y-m'), $current_year['id'] ?? 0]
);
$stats['recettes_mois'] = $stmt->fetch()['total'] ?? 0;

// Dépenses totales de l'année
$stmt = $database->query(
    "SELECT SUM(montant) as total FROM depenses WHERE status = 'approuve' AND annee_scolaire_id = ?",
    [$current_year['id'] ?? 0]
);
$stats['depenses_annee'] = $stmt->fetch()['total'] ?? 0;

// Créances (impayés)
$stmt = $database->query(
    "SELECT SUM(f.montant_total - COALESCE(p.montant_paye, 0)) as total
     FROM frais_scolaires f
     LEFT JOIN (
         SELECT eleve_id, type_frais, SUM(montant) as montant_paye
         FROM paiements 
         WHERE status = 'valide' AND annee_scolaire_id = ?
         GROUP BY eleve_id, type_frais
     ) p ON f.eleve_id = p.eleve_id AND f.type_frais = p.type_frais
     WHERE f.annee_scolaire_id = ?
     AND (f.montant_total - COALESCE(p.montant_paye, 0)) > 0",
    [$current_year['id'] ?? 0, $current_year['id'] ?? 0]
);
$stats['creances'] = $stmt->fetch()['total'] ?? 0;

// Bénéfice/Perte
$stats['benefice'] = $stats['recettes_annee'] - $stats['depenses_annee'];

// Évolution mensuelle des recettes
$recettes_mensuelles = $database->query(
    "SELECT DATE_FORMAT(date_paiement, '%Y-%m') as mois, 
            SUM(montant) as montant,
            COUNT(*) as nb_paiements
     FROM paiements 
     WHERE status = 'valide' 
     AND date_paiement >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
     AND annee_scolaire_id = ?
     GROUP BY DATE_FORMAT(date_paiement, '%Y-%m')
     ORDER BY mois",
    [$current_year['id'] ?? 0]
)->fetchAll();

// Répartition des recettes par type
$recettes_par_type = $database->query(
    "SELECT type_frais, SUM(montant) as montant, COUNT(*) as nb_paiements
     FROM paiements 
     WHERE status = 'valide' AND annee_scolaire_id = ?
     GROUP BY type_frais
     ORDER BY montant DESC",
    [$current_year['id'] ?? 0]
)->fetchAll();

// Répartition des dépenses par catégorie
$depenses_par_categorie = $database->query(
    "SELECT categorie, SUM(montant) as montant, COUNT(*) as nb_depenses
     FROM depenses 
     WHERE status = 'approuve' AND annee_scolaire_id = ?
     GROUP BY categorie
     ORDER BY montant DESC",
    [$current_year['id'] ?? 0]
)->fetchAll();

// Top 10 des plus gros payeurs
$gros_payeurs = $database->query(
    "SELECT e.nom, e.prenom, e.numero_matricule, c.nom as classe_nom,
            SUM(p.montant) as total_paye,
            COUNT(p.id) as nb_paiements
     FROM eleves e
     JOIN inscriptions i ON e.id = i.eleve_id
     JOIN classes c ON i.classe_id = c.id
     JOIN paiements p ON e.id = p.eleve_id
     WHERE p.status = 'valide' AND p.annee_scolaire_id = ?
     GROUP BY e.id
     ORDER BY total_paye DESC
     LIMIT 10",
    [$current_year['id'] ?? 0]
)->fetchAll();

// Élèves avec le plus de créances
$plus_grandes_creances = $database->query(
    "SELECT e.nom, e.prenom, e.numero_matricule, c.nom as classe_nom,
            SUM(f.montant_total - COALESCE(p.montant_paye, 0)) as creance_totale
     FROM eleves e
     JOIN inscriptions i ON e.id = i.eleve_id
     JOIN classes c ON i.classe_id = c.id
     JOIN frais_scolaires f ON e.id = f.eleve_id
     LEFT JOIN (
         SELECT eleve_id, type_frais, SUM(montant) as montant_paye
         FROM paiements 
         WHERE status = 'valide' AND annee_scolaire_id = ?
         GROUP BY eleve_id, type_frais
     ) p ON f.eleve_id = p.eleve_id AND f.type_frais = p.type_frais
     WHERE f.annee_scolaire_id = ? AND i.status = 'inscrit'
     GROUP BY e.id
     HAVING creance_totale > 0
     ORDER BY creance_totale DESC
     LIMIT 10",
    [$current_year['id'] ?? 0, $current_year['id'] ?? 0]
)->fetchAll();

// Paiements récents
$paiements_recents = $database->query(
    "SELECT p.*, e.nom, e.prenom, e.numero_matricule, c.nom as classe_nom
     FROM paiements p
     JOIN eleves e ON p.eleve_id = e.id
     JOIN inscriptions i ON e.id = i.eleve_id
     JOIN classes c ON i.classe_id = c.id
     WHERE p.status = 'valide' AND p.annee_scolaire_id = ?
     ORDER BY p.date_paiement DESC, p.created_at DESC
     LIMIT 10",
    [$current_year['id'] ?? 0]
)->fetchAll();

include '../../../includes/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">
        <i class="fas fa-chart-line me-2"></i>
        Rapports Financiers
    </h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <div class="btn-group me-2">
            <a href="../index.php" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-1"></i>
                Retour
            </a>
        </div>
        <div class="btn-group me-2">
            <button type="button" class="btn btn-success dropdown-toggle" data-bs-toggle="dropdown">
                <i class="fas fa-file-export me-1"></i>
                Exporter
            </button>
            <ul class="dropdown-menu">
                <li><a class="dropdown-item" href="export.php?type=financial&format=pdf&<?php echo http_build_query($_GET); ?>">
                    <i class="fas fa-file-pdf me-2"></i>Rapport PDF
                </a></li>
                <li><a class="dropdown-item" href="export.php?type=financial&format=excel&<?php echo http_build_query($_GET); ?>">
                    <i class="fas fa-file-excel me-2"></i>Données Excel
                </a></li>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item" href="bilan/annual.php">
                    <i class="fas fa-balance-scale me-2"></i>Bilan annuel
                </a></li>
            </ul>
        </div>
        <div class="btn-group">
            <button type="button" class="btn btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">
                <i class="fas fa-tools me-1"></i>
                Analyses
            </button>
            <ul class="dropdown-menu">
                <li><a class="dropdown-item" href="analysis/cashflow.php">
                    <i class="fas fa-water me-2"></i>Flux de trésorerie
                </a></li>
                <li><a class="dropdown-item" href="analysis/profitability.php">
                    <i class="fas fa-chart-pie me-2"></i>Rentabilité
                </a></li>
                <li><a class="dropdown-item" href="analysis/budget.php">
                    <i class="fas fa-calculator me-2"></i>Analyse budgétaire
                </a></li>
            </ul>
        </div>
    </div>
</div>

<!-- Statistiques rapides -->
<div class="row mb-4">
    <div class="col-lg-3 col-md-6 mb-3">
        <div class="card text-white bg-success">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h4><?php echo formatMoney($stats['recettes_annee']); ?></h4>
                        <p class="mb-0">Recettes annuelles</p>
                    </div>
                    <div class="align-self-center">
                        <i class="fas fa-arrow-up fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-3 col-md-6 mb-3">
        <div class="card text-white bg-danger">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h4><?php echo formatMoney($stats['depenses_annee']); ?></h4>
                        <p class="mb-0">Dépenses annuelles</p>
                    </div>
                    <div class="align-self-center">
                        <i class="fas fa-arrow-down fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-3 col-md-6 mb-3">
        <div class="card text-white bg-<?php echo $stats['benefice'] >= 0 ? 'primary' : 'warning'; ?>">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h4><?php echo formatMoney($stats['benefice']); ?></h4>
                        <p class="mb-0"><?php echo $stats['benefice'] >= 0 ? 'Bénéfice' : 'Perte'; ?></p>
                    </div>
                    <div class="align-self-center">
                        <i class="fas fa-<?php echo $stats['benefice'] >= 0 ? 'plus' : 'minus'; ?> fa-2x"></i>
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
                        <h4><?php echo formatMoney($stats['creances']); ?></h4>
                        <p class="mb-0">Créances</p>
                    </div>
                    <div class="align-self-center">
                        <i class="fas fa-exclamation-triangle fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Graphiques principaux -->
<div class="row mb-4">
    <div class="col-lg-8">
        <!-- Évolution des recettes -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-chart-line me-2"></i>
                    Évolution des recettes (12 derniers mois)
                </h5>
            </div>
            <div class="card-body">
                <?php if (!empty($recettes_mensuelles)): ?>
                    <canvas id="recettesChart" width="100%" height="300"></canvas>
                <?php else: ?>
                    <div class="text-center py-4">
                        <i class="fas fa-chart-line fa-3x text-muted mb-3"></i>
                        <p class="text-muted">Aucune donnée de recettes disponible</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <div class="col-lg-4">
        <!-- Répartition des recettes -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-chart-pie me-2"></i>
                    Recettes par type
                </h5>
            </div>
            <div class="card-body">
                <?php if (!empty($recettes_par_type)): ?>
                    <canvas id="typesChart" width="100%" height="300"></canvas>
                    <div class="mt-3">
                        <?php foreach ($recettes_par_type as $type): ?>
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span><?php echo ucfirst(str_replace('_', ' ', $type['type_frais'])); ?></span>
                                <div class="text-end">
                                    <strong><?php echo formatMoney($type['montant']); ?></strong>
                                    <br><small class="text-muted"><?php echo $type['nb_paiements']; ?> paiements</small>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
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
    <div class="col-lg-6">
        <!-- Top payeurs -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-trophy me-2"></i>
                    Top 10 des payeurs
                </h5>
            </div>
            <div class="card-body">
                <?php if (!empty($gros_payeurs)): ?>
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Rang</th>
                                    <th>Élève</th>
                                    <th>Classe</th>
                                    <th>Total payé</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($gros_payeurs as $index => $payeur): ?>
                                    <tr>
                                        <td>
                                            <span class="badge bg-<?php echo $index < 3 ? 'warning' : 'secondary'; ?>">
                                                <?php echo $index + 1; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($payeur['nom'] . ' ' . $payeur['prenom']); ?></strong>
                                            <br><small class="text-muted"><?php echo htmlspecialchars($payeur['numero_matricule']); ?></small>
                                        </td>
                                        <td>
                                            <span class="badge bg-secondary"><?php echo htmlspecialchars($payeur['classe_nom']); ?></span>
                                        </td>
                                        <td class="text-end">
                                            <strong><?php echo formatMoney($payeur['total_paye']); ?></strong>
                                            <br><small class="text-muted"><?php echo $payeur['nb_paiements']; ?> paiements</small>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p class="text-muted text-center">Aucun paiement enregistré</p>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Paiements récents -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-clock me-2"></i>
                    Paiements récents
                </h5>
            </div>
            <div class="card-body">
                <?php if (!empty($paiements_recents)): ?>
                    <div class="list-group list-group-flush">
                        <?php foreach (array_slice($paiements_recents, 0, 8) as $paiement): ?>
                            <div class="list-group-item d-flex justify-content-between align-items-start">
                                <div class="ms-2 me-auto">
                                    <div class="fw-bold">
                                        <?php echo htmlspecialchars($paiement['nom'] . ' ' . $paiement['prenom']); ?>
                                    </div>
                                    <small class="text-muted">
                                        <?php echo htmlspecialchars($paiement['classe_nom']); ?> - 
                                        <?php echo ucfirst(str_replace('_', ' ', $paiement['type_frais'])); ?>
                                    </small>
                                    <br><small class="text-muted">
                                        <?php echo formatDate($paiement['date_paiement']); ?>
                                    </small>
                                </div>
                                <span class="badge bg-success fs-6">
                                    <?php echo formatMoney($paiement['montant']); ?>
                                </span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p class="text-muted text-center">Aucun paiement récent</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <div class="col-lg-6">
        <!-- Plus grandes créances -->
        <div class="card mb-4">
            <div class="card-header bg-warning text-dark">
                <h5 class="mb-0">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    Plus grandes créances
                </h5>
            </div>
            <div class="card-body">
                <?php if (!empty($plus_grandes_creances)): ?>
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Élève</th>
                                    <th>Classe</th>
                                    <th>Créance</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($plus_grandes_creances as $creance): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo htmlspecialchars($creance['nom'] . ' ' . $creance['prenom']); ?></strong>
                                            <br><small class="text-muted"><?php echo htmlspecialchars($creance['numero_matricule']); ?></small>
                                        </td>
                                        <td>
                                            <span class="badge bg-secondary"><?php echo htmlspecialchars($creance['classe_nom']); ?></span>
                                        </td>
                                        <td class="text-end">
                                            <span class="badge bg-danger fs-6">
                                                <?php echo formatMoney($creance['creance_totale']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <a href="../../finances/payments/student.php?eleve_id=<?php echo $creance['eleve_id'] ?? ''; ?>" 
                                               class="btn btn-sm btn-outline-primary">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="text-center py-4">
                        <i class="fas fa-check-circle fa-3x text-success mb-3"></i>
                        <h5 class="text-success">Aucune créance</h5>
                        <p class="text-muted">Tous les frais sont à jour !</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Dépenses par catégorie -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-chart-bar me-2"></i>
                    Dépenses par catégorie
                </h5>
            </div>
            <div class="card-body">
                <?php if (!empty($depenses_par_categorie)): ?>
                    <?php foreach ($depenses_par_categorie as $categorie): ?>
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <div>
                                <strong><?php echo ucfirst($categorie['categorie']); ?></strong>
                                <br><small class="text-muted"><?php echo $categorie['nb_depenses']; ?> dépenses</small>
                            </div>
                            <div class="text-end">
                                <strong><?php echo formatMoney($categorie['montant']); ?></strong>
                                <br><div class="progress" style="width: 100px; height: 6px;">
                                    <div class="progress-bar bg-danger" 
                                         style="width: <?php echo ($categorie['montant'] / $stats['depenses_annee']) * 100; ?>%"></div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p class="text-muted text-center">Aucune dépense enregistrée</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
// Graphique des recettes mensuelles
<?php if (!empty($recettes_mensuelles)): ?>
const recettesCtx = document.getElementById('recettesChart').getContext('2d');
const recettesChart = new Chart(recettesCtx, {
    type: 'line',
    data: {
        labels: [<?php echo implode(',', array_map(function($r) { return "'" . date('M Y', strtotime($r['mois'] . '-01')) . "'"; }, $recettes_mensuelles)); ?>],
        datasets: [{
            label: 'Recettes (FC)',
            data: [<?php echo implode(',', array_column($recettes_mensuelles, 'montant')); ?>],
            borderColor: '#28a745',
            backgroundColor: 'rgba(40, 167, 69, 0.1)',
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
                ticks: {
                    callback: function(value) {
                        return new Intl.NumberFormat('fr-CD', {
                            style: 'currency',
                            currency: 'CDF'
                        }).format(value);
                    }
                }
            }
        }
    }
});
<?php endif; ?>

// Graphique des types de recettes
<?php if (!empty($recettes_par_type)): ?>
const typesCtx = document.getElementById('typesChart').getContext('2d');
const typesChart = new Chart(typesCtx, {
    type: 'doughnut',
    data: {
        labels: [<?php echo implode(',', array_map(function($t) { return "'" . ucfirst(str_replace('_', ' ', $t['type_frais'])) . "'"; }, $recettes_par_type)); ?>],
        datasets: [{
            data: [<?php echo implode(',', array_column($recettes_par_type, 'montant')); ?>],
            backgroundColor: ['#007bff', '#28a745', '#ffc107', '#dc3545', '#6c757d', '#17a2b8'],
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
