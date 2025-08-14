<?php
/**
 * Module de gestion financière - Page principale
 * Application de gestion scolaire - République Démocratique du Congo
 */

require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../includes/functions.php';

// Vérifier l'authentification et les permissions
requireLogin();
if (!checkPermission('finance') && !checkPermission('finance_view')) {
    showMessage('error', 'Accès refusé à ce module.');
    redirectTo('../../dashboard.php');
}

$page_title = 'Gestion Financière';

// Obtenir l'année scolaire actuelle
$current_year = getCurrentAcademicYear();

// Statistiques financières
$stats = [];

// Total des recettes (paiements reçus)
$stmt = $database->query(
    "SELECT SUM(montant) as total FROM paiements WHERE annee_scolaire_id = ?",
    [$current_year['id'] ?? 0]
);
$stats['total_recettes'] = $stmt->fetch()['total'] ?? 0;

// Total des frais attendus
$stmt = $database->query(
    "SELECT SUM(f.montant) as total 
     FROM frais_scolaires f 
     JOIN inscriptions i ON f.classe_id = i.classe_id 
     WHERE i.status = 'inscrit' AND i.annee_scolaire_id = ?",
    [$current_year['id'] ?? 0]
);
$stats['total_attendu'] = $stmt->fetch()['total'] ?? 0;

// Paiements récents (7 derniers jours)
$stmt = $database->query(
    "SELECT SUM(montant) as total FROM paiements WHERE date_paiement >= DATE_SUB(NOW(), INTERVAL 7 DAY) AND annee_scolaire_id = ?",
    [$current_year['id'] ?? 0]
);
$stats['paiements_attente'] = $stmt->fetch()['total'] ?? 0;

// Nombre d'élèves avec impayés
$stmt = $database->query(
    "SELECT COUNT(DISTINCT i.eleve_id) as total
     FROM inscriptions i
     LEFT JOIN paiements p ON i.eleve_id = p.eleve_id
     JOIN frais_scolaires f ON i.classe_id = f.classe_id
     WHERE i.status = 'inscrit' AND i.annee_scolaire_id = ?
     GROUP BY i.eleve_id
     HAVING SUM(COALESCE(p.montant, 0)) < SUM(f.montant)",
    [$current_year['id'] ?? 0]
);
$stats['eleves_impayes'] = $stmt->rowCount();

// Calculs dérivés
$stats['taux_recouvrement'] = $stats['total_attendu'] > 0 ? 
    round(($stats['total_recettes'] / $stats['total_attendu']) * 100, 1) : 0;
$stats['reste_a_percevoir'] = $stats['total_attendu'] - $stats['total_recettes'];

// Paiements récents (derniers 7 jours)
$paiements_recents = $database->query(
    "SELECT p.*, e.nom, e.prenom, e.numero_matricule, c.nom as classe_nom
     FROM paiements p
     JOIN eleves e ON p.eleve_id = e.id
     JOIN inscriptions i ON e.id = i.eleve_id
     JOIN classes c ON i.classe_id = c.id
     WHERE p.date_paiement >= DATE_SUB(NOW(), INTERVAL 7 DAY)

     AND p.annee_scolaire_id = ?
     ORDER BY p.date_paiement DESC
     LIMIT 10",
    [$current_year['id'] ?? 0]
)->fetchAll();

// Évolution mensuelle des recettes
$recettes_mensuelles = $database->query(
    "SELECT 
        MONTH(date_paiement) as mois,
        YEAR(date_paiement) as annee,
        SUM(montant) as total
     FROM paiements 
     WHERE status = 'valide' 
     AND annee_scolaire_id = ?
     AND date_paiement >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
     GROUP BY YEAR(date_paiement), MONTH(date_paiement)
     ORDER BY annee, mois",
    [$current_year['id'] ?? 0]
)->fetchAll();

include '../../includes/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">
        <i class="fas fa-coins me-2"></i>
        Gestion Financière
    </h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <div class="btn-group me-2">
            <button type="button" class="btn btn-outline-secondary">
                <i class="fas fa-calendar-alt me-1"></i>
                <?php echo $current_year['annee'] ?? 'Aucune année active'; ?>
            </button>
        </div>
        <?php if (checkPermission('finance')): ?>
            <div class="btn-group">
                <button type="button" class="btn btn-primary dropdown-toggle" data-bs-toggle="dropdown">
                    <i class="fas fa-plus me-1"></i>
                    Nouveau
                </button>
                <ul class="dropdown-menu">
                    <li><a class="dropdown-item" href="payments/add.php">
                        <i class="fas fa-money-bill me-2"></i>Enregistrer paiement
                    </a></li>
                    <li><a class="dropdown-item" href="fees/add.php">
                        <i class="fas fa-tags me-2"></i>Configurer frais
                    </a></li>
                    <li><a class="dropdown-item" href="expenses/add.php">
                        <i class="fas fa-receipt me-2"></i>Nouvelle dépense
                    </a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item" href="reports/generate.php">
                        <i class="fas fa-chart-bar me-2"></i>Générer rapport
                    </a></li>
                </ul>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Statistiques financières -->
<div class="row mb-4">
    <div class="col-lg-3 col-md-6 mb-3">
        <div class="card text-white bg-success">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h4><?php echo formatMoney($stats['total_recettes']); ?></h4>
                        <p class="mb-0">Recettes totales</p>
                    </div>
                    <div class="align-self-center">
                        <i class="fas fa-arrow-up fa-2x"></i>
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
                        <h4><?php echo formatMoney($stats['total_attendu']); ?></h4>
                        <p class="mb-0">Montant attendu</p>
                    </div>
                    <div class="align-self-center">
                        <i class="fas fa-bullseye fa-2x"></i>
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
                        <h4><?php echo formatMoney($stats['reste_a_percevoir']); ?></h4>
                        <p class="mb-0">Reste à percevoir</p>
                    </div>
                    <div class="align-self-center">
                        <i class="fas fa-hourglass-half fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-3 col-md-6 mb-3">
        <div class="card text-white bg-primary">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h4><?php echo $stats['taux_recouvrement']; ?>%</h4>
                        <p class="mb-0">Taux de recouvrement</p>
                    </div>
                    <div class="align-self-center">
                        <i class="fas fa-percentage fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Indicateurs supplémentaires -->
<div class="row mb-4">
    <div class="col-md-6">
        <div class="card">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h5 class="card-title text-danger"><?php echo $stats['eleves_impayes']; ?></h5>
                        <p class="card-text">Élèves avec impayés</p>
                    </div>
                    <i class="fas fa-exclamation-triangle fa-2x text-danger"></i>
                </div>
                <div class="progress mt-2" style="height: 6px;">
                    <?php 
                    $total_eleves = $database->query("SELECT COUNT(*) as total FROM inscriptions WHERE status = 'inscrit' AND annee_scolaire_id = ?", [$current_year['id'] ?? 0])->fetch()['total'];
                    $pourcentage_impayes = $total_eleves > 0 ? ($stats['eleves_impayes'] / $total_eleves) * 100 : 0;
                    ?>
                    <div class="progress-bar bg-danger" style="width: <?php echo $pourcentage_impayes; ?>%"></div>
                </div>
                <small class="text-muted"><?php echo round($pourcentage_impayes, 1); ?>% du total des élèves</small>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h5 class="card-title text-warning"><?php echo formatMoney($stats['paiements_attente']); ?></h5>
                        <p class="card-text">Paiements en attente</p>
                    </div>
                    <i class="fas fa-clock fa-2x text-warning"></i>
                </div>
                <div class="progress mt-2" style="height: 6px;">
                    <?php 
                    $pourcentage_attente = $stats['total_attendu'] > 0 ? ($stats['paiements_attente'] / $stats['total_attendu']) * 100 : 0;
                    ?>
                    <div class="progress-bar bg-warning" style="width: <?php echo $pourcentage_attente; ?>%"></div>
                </div>
                <small class="text-muted"><?php echo round($pourcentage_attente, 1); ?>% du montant attendu</small>
            </div>
        </div>
    </div>
</div>

<!-- Modules financiers -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-th-large me-2"></i>
                    Modules financiers
                </h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-lg-3 col-md-6 mb-3">
                        <a href="payments/" class="text-decoration-none">
                            <div class="card h-100 border-0 shadow-sm hover-card">
                                <div class="card-body text-center">
                                    <i class="fas fa-money-bill-wave fa-3x text-success mb-3"></i>
                                    <h5 class="card-title">Paiements</h5>
                                    <p class="card-text text-muted">
                                        Enregistrement et suivi des paiements élèves
                                    </p>
                                    <div class="mt-3">
                                        <span class="badge bg-success"><?php echo formatMoney($stats['total_recettes']); ?></span>
                                    </div>
                                </div>
                            </div>
                        </a>
                    </div>
                    
                    <div class="col-lg-3 col-md-6 mb-3">
                        <a href="fees/" class="text-decoration-none">
                            <div class="card h-100 border-0 shadow-sm hover-card">
                                <div class="card-body text-center">
                                    <i class="fas fa-tags fa-3x text-primary mb-3"></i>
                                    <h5 class="card-title">Frais scolaires</h5>
                                    <p class="card-text text-muted">
                                        Configuration des frais par classe et niveau
                                    </p>
                                    <div class="mt-3">
                                        <span class="badge bg-primary">Configuration</span>
                                    </div>
                                </div>
                            </div>
                        </a>
                    </div>
                    
                    <div class="col-lg-3 col-md-6 mb-3">
                        <a href="expenses/" class="text-decoration-none">
                            <div class="card h-100 border-0 shadow-sm hover-card">
                                <div class="card-body text-center">
                                    <i class="fas fa-receipt fa-3x text-danger mb-3"></i>
                                    <h5 class="card-title">Dépenses</h5>
                                    <p class="card-text text-muted">
                                        Gestion des dépenses et charges de l'école
                                    </p>
                                    <div class="mt-3">
                                        <span class="badge bg-danger">Comptabilité</span>
                                    </div>
                                </div>
                            </div>
                        </a>
                    </div>
                    
                    <div class="col-lg-3 col-md-6 mb-3">
                        <a href="reports/" class="text-decoration-none">
                            <div class="card h-100 border-0 shadow-sm hover-card">
                                <div class="card-body text-center">
                                    <i class="fas fa-chart-bar fa-3x text-info mb-3"></i>
                                    <h5 class="card-title">Rapports</h5>
                                    <p class="card-text text-muted">
                                        Analyses financières et tableaux de bord
                                    </p>
                                    <div class="mt-3">
                                        <span class="badge bg-info">Analyses</span>
                                    </div>
                                </div>
                            </div>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Graphique et paiements récents -->
<div class="row">
    <div class="col-lg-8">
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
                        <?php foreach ($paiements_recents as $paiement): ?>
                            <div class="list-group-item d-flex justify-content-between align-items-start">
                                <div class="ms-2 me-auto">
                                    <div class="fw-bold">
                                        <?php echo htmlspecialchars($paiement['nom'] . ' ' . $paiement['prenom']); ?>
                                    </div>
                                    <small class="text-muted">
                                        <?php echo htmlspecialchars($paiement['classe_nom']); ?> - 
                                        <?php echo formatDate($paiement['date_paiement']); ?>
                                    </small>
                                </div>
                                <span class="badge bg-success rounded-pill">
                                    <?php echo formatMoney($paiement['montant']); ?>
                                </span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="text-center mt-3">
                        <a href="payments/" class="btn btn-outline-secondary">
                            <i class="fas fa-list me-1"></i>
                            Voir tous les paiements
                        </a>
                    </div>
                <?php else: ?>
                    <div class="text-center py-4">
                        <i class="fas fa-money-bill fa-3x text-muted mb-3"></i>
                        <p class="text-muted">Aucun paiement récent</p>
                        <?php if (checkPermission('finance')): ?>
                            <a href="payments/add.php" class="btn btn-primary">
                                <i class="fas fa-plus me-1"></i>
                                Enregistrer un paiement
                            </a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Actions rapides -->
<?php if (checkPermission('finance')): ?>
<div class="row mt-4">
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
                    <div class="col-md-3 mb-2">
                        <div class="d-grid">
                            <a href="payments/add.php" class="btn btn-outline-success">
                                <i class="fas fa-money-bill me-2"></i>
                                Nouveau paiement
                            </a>
                        </div>
                    </div>
                    <div class="col-md-3 mb-2">
                        <div class="d-grid">
                            <a href="fees/manage.php" class="btn btn-outline-primary">
                                <i class="fas fa-cog me-2"></i>
                                Configurer frais
                            </a>
                        </div>
                    </div>
                    <div class="col-md-3 mb-2">
                        <div class="d-grid">
                            <a href="reports/debtors.php" class="btn btn-outline-warning">
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                Élèves débiteurs
                            </a>
                        </div>
                    </div>
                    <div class="col-md-3 mb-2">
                        <div class="d-grid">
                            <a href="reports/monthly.php" class="btn btn-outline-info">
                                <i class="fas fa-chart-bar me-2"></i>
                                Rapport mensuel
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
// Graphique d'évolution des recettes
<?php if (!empty($recettes_mensuelles)): ?>
const recettesCtx = document.getElementById('recettesChart').getContext('2d');
const recettesChart = new Chart(recettesCtx, {
    type: 'line',
    data: {
        labels: [<?php 
            echo implode(',', array_map(function($r) { 
                return "'" . getMonthName($r['mois']) . " " . $r['annee'] . "'"; 
            }, $recettes_mensuelles)); 
        ?>],
        datasets: [{
            label: 'Recettes (FC)',
            data: [<?php echo implode(',', array_column($recettes_mensuelles, 'total')); ?>],
            borderColor: '#28a745',
            backgroundColor: 'rgba(40, 167, 69, 0.1)',
            borderWidth: 2,
            fill: true,
            tension: 0.4
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
                            currency: 'CDF',
                            minimumFractionDigits: 0
                        }).format(value);
                    }
                }
            }
        },
        interaction: {
            intersect: false,
            mode: 'index'
        }
    }
});
<?php endif; ?>
</script>

<?php include '../../includes/footer.php'; ?>
