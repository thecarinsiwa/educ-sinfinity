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
if (!checkPermission('recouvrement') && !checkPermission('admin')) {
    showMessage('error', 'Accès refusé à cette page.');
    redirectTo('../../../index.php');
}

$errors = [];
$success_message = '';

// Récupérer les statistiques générales
try {
    // Statistiques des paiements
    $stats_paiements = $database->query("
        SELECT 
            COUNT(*) as total_paiements,
            SUM(montant) as montant_total,
            AVG(montant) as montant_moyen,
            COUNT(DISTINCT eleve_id) as eleves_payeurs
        FROM paiements 
        WHERE status = 'valide'
    ")->fetch();

    // Statistiques de solvabilité
    $stats_solvabilite = $database->query("
        SELECT 
            status_solvabilite,
            COUNT(*) as nombre,
            SUM(total_paye) as total_paye,
            SUM(solde_restant) as solde_restant
        FROM solvabilite_eleves s
        JOIN annees_scolaires a ON s.annee_scolaire_id = a.id
        WHERE a.status = 'active'
        GROUP BY status_solvabilite
    ")->fetchAll();

    // Statistiques des présences QR
    $stats_presences = $database->query("
        SELECT 
            DATE(created_at) as date_presence,
            COUNT(DISTINCT eleve_id) as presents,
            COUNT(CASE WHEN type_scan = 'entree' THEN 1 END) as entrees,
            COUNT(CASE WHEN type_scan = 'sortie' THEN 1 END) as sorties
        FROM presences_qr 
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        GROUP BY DATE(created_at)
        ORDER BY date_presence DESC
        LIMIT 7
    ")->fetchAll();

    // Top 10 des élèves les plus solvables
    $top_solvables = $database->query("
        SELECT 
            e.nom, e.prenom, e.numero_matricule,
            s.total_paye, s.pourcentage_paye,
            cl.nom as classe_nom
        FROM solvabilite_eleves s
        JOIN eleves e ON s.eleve_id = e.id
        JOIN annees_scolaires a ON s.annee_scolaire_id = a.id
        LEFT JOIN inscriptions i ON e.id = i.eleve_id AND i.status = 'inscrit'
        LEFT JOIN classes cl ON i.classe_id = cl.id
        WHERE a.status = 'active'
        ORDER BY s.pourcentage_paye DESC, s.total_paye DESC
        LIMIT 10
    ")->fetchAll();

} catch (Exception $e) {
    $errors[] = "Erreur lors du chargement des statistiques : " . $e->getMessage();
}

$page_title = "Rapports - Recouvrement";
include '../../../includes/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">
        <i class="fas fa-chart-line me-2 text-warning"></i>
        Rapports de Recouvrement
    </h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <div class="btn-group me-2">
            <a href="../index.php" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-1"></i>
                Retour
            </a>
        </div>
        <div class="btn-group">
            <button type="button" class="btn btn-primary dropdown-toggle" data-bs-toggle="dropdown">
                <i class="fas fa-download me-1"></i>
                Exporter
            </button>
            <ul class="dropdown-menu">
                <li><a class="dropdown-item" href="export.php?type=paiements&format=excel">
                    <i class="fas fa-file-excel me-2"></i>Paiements (Excel)
                </a></li>
                <li><a class="dropdown-item" href="export.php?type=solvabilite&format=excel">
                    <i class="fas fa-file-excel me-2"></i>Solvabilité (Excel)
                </a></li>
                <li><a class="dropdown-item" href="export.php?type=presences&format=excel">
                    <i class="fas fa-file-excel me-2"></i>Présences (Excel)
                </a></li>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item" href="export.php?type=global&format=pdf">
                    <i class="fas fa-file-pdf me-2"></i>Rapport Global (PDF)
                </a></li>
            </ul>
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

<?php if (!empty($success_message)): ?>
    <div class="alert alert-success">
        <i class="fas fa-check-circle me-2"></i>
        <?php echo htmlspecialchars($success_message); ?>
    </div>
<?php endif; ?>

<!-- Navigation des rapports -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card shadow-sm">
            <div class="card-body">
                <div class="row text-center">
                    <div class="col-md-3">
                        <a href="paiements.php" class="btn btn-outline-success btn-lg w-100 mb-2">
                            <i class="fas fa-money-bill-wave fa-2x d-block mb-2"></i>
                            <strong>Paiements</strong>
                            <small class="d-block text-muted">Analyse des paiements</small>
                        </a>
                    </div>
                    <div class="col-md-3">
                        <a href="solvabilite.php" class="btn btn-outline-info btn-lg w-100 mb-2">
                            <i class="fas fa-chart-pie fa-2x d-block mb-2"></i>
                            <strong>Solvabilité</strong>
                            <small class="d-block text-muted">État de solvabilité</small>
                        </a>
                    </div>
                    <div class="col-md-3">
                        <a href="presences.php" class="btn btn-outline-warning btn-lg w-100 mb-2">
                            <i class="fas fa-user-check fa-2x d-block mb-2"></i>
                            <strong>Présences</strong>
                            <small class="d-block text-muted">Suivi des présences</small>
                        </a>
                    </div>
                    <div class="col-md-3">
                        <a href="comparatif.php" class="btn btn-outline-primary btn-lg w-100 mb-2">
                            <i class="fas fa-chart-bar fa-2x d-block mb-2"></i>
                            <strong>Comparatif</strong>
                            <small class="d-block text-muted">Analyses comparatives</small>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Statistiques générales -->
<div class="row mb-4">
    <div class="col-md-3">
        <div class="card bg-success text-white shadow-sm">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h4 class="mb-0"><?php echo number_format($stats_paiements['total_paiements'] ?? 0); ?></h4>
                        <p class="mb-0">Paiements</p>
                    </div>
                    <div class="align-self-center">
                        <i class="fas fa-money-bill-wave fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-info text-white shadow-sm">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h4 class="mb-0"><?php echo number_format($stats_paiements['montant_total'] ?? 0, 0, ',', ' '); ?> FC</h4>
                        <p class="mb-0">Montant Total</p>
                    </div>
                    <div class="align-self-center">
                        <i class="fas fa-coins fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-warning text-white shadow-sm">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h4 class="mb-0"><?php echo number_format($stats_paiements['eleves_payeurs'] ?? 0); ?></h4>
                        <p class="mb-0">Élèves Payeurs</p>
                    </div>
                    <div class="align-self-center">
                        <i class="fas fa-users fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-primary text-white shadow-sm">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h4 class="mb-0"><?php echo number_format($stats_paiements['montant_moyen'] ?? 0, 0, ',', ' '); ?> FC</h4>
                        <p class="mb-0">Paiement Moyen</p>
                    </div>
                    <div class="align-self-center">
                        <i class="fas fa-calculator fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <!-- Répartition de la solvabilité -->
    <div class="col-lg-6 mb-4">
        <div class="card shadow-sm">
            <div class="card-header bg-light">
                <h5 class="mb-0">
                    <i class="fas fa-chart-pie me-2"></i>
                    Répartition de la Solvabilité
                </h5>
            </div>
            <div class="card-body">
                <?php if (!empty($stats_solvabilite)): ?>
                    <canvas id="solvabiliteChart" width="400" height="200"></canvas>
                    <div class="mt-3">
                        <?php foreach ($stats_solvabilite as $stat): ?>
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span class="badge bg-<?php 
                                    echo match($stat['status_solvabilite']) {
                                        'solvable' => 'success',
                                        'partiellement_solvable' => 'warning',
                                        'non_solvable' => 'danger',
                                        default => 'secondary'
                                    };
                                ?>">
                                    <?php 
                                    echo match($stat['status_solvabilite']) {
                                        'solvable' => 'Solvable',
                                        'partiellement_solvable' => 'Partiellement solvable',
                                        'non_solvable' => 'Non solvable',
                                        default => 'Inconnu'
                                    };
                                    ?>
                                </span>
                                <span><?php echo number_format($stat['nombre']); ?> élèves</span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="text-center text-muted">
                        <i class="fas fa-chart-pie fa-3x mb-3"></i>
                        <p>Aucune donnée de solvabilité disponible</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Évolution des présences -->
    <div class="col-lg-6 mb-4">
        <div class="card shadow-sm">
            <div class="card-header bg-light">
                <h5 class="mb-0">
                    <i class="fas fa-chart-line me-2"></i>
                    Présences des 7 derniers jours
                </h5>
            </div>
            <div class="card-body">
                <?php if (!empty($stats_presences)): ?>
                    <canvas id="presencesChart" width="400" height="200"></canvas>
                <?php else: ?>
                    <div class="text-center text-muted">
                        <i class="fas fa-chart-line fa-3x mb-3"></i>
                        <p>Aucune donnée de présence disponible</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Top élèves solvables -->
<div class="row">
    <div class="col-12">
        <div class="card shadow-sm">
            <div class="card-header bg-light">
                <h5 class="mb-0">
                    <i class="fas fa-trophy me-2"></i>
                    Top 10 des Élèves les Plus Solvables
                </h5>
            </div>
            <div class="card-body">
                <?php if (!empty($top_solvables)): ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Rang</th>
                                    <th>Matricule</th>
                                    <th>Nom Complet</th>
                                    <th>Classe</th>
                                    <th>Montant Payé</th>
                                    <th>Pourcentage</th>
                                    <th>Statut</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($top_solvables as $index => $eleve): ?>
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
                                        <td><?php echo number_format($eleve['total_paye'], 0, ',', ' '); ?> FC</td>
                                        <td>
                                            <div class="progress" style="height: 20px;">
                                                <div class="progress-bar bg-success" role="progressbar" 
                                                     style="width: <?php echo $eleve['pourcentage_paye']; ?>%">
                                                    <?php echo number_format($eleve['pourcentage_paye'], 1); ?>%
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?php echo ($eleve['pourcentage_paye'] >= 100) ? 'success' : (($eleve['pourcentage_paye'] >= 50) ? 'warning' : 'danger'); ?>">
                                                <?php echo ($eleve['pourcentage_paye'] >= 100) ? 'Solvable' : (($eleve['pourcentage_paye'] >= 50) ? 'Partiel' : 'Non solvable'); ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="text-center text-muted">
                        <i class="fas fa-trophy fa-3x mb-3"></i>
                        <p>Aucune donnée disponible</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Inclure Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
// Graphique de solvabilité
<?php if (!empty($stats_solvabilite)): ?>
const solvabiliteCtx = document.getElementById('solvabiliteChart').getContext('2d');
const solvabiliteChart = new Chart(solvabiliteCtx, {
    type: 'doughnut',
    data: {
        labels: [
            <?php foreach ($stats_solvabilite as $stat): ?>
                '<?php echo match($stat['status_solvabilite']) {
                    'solvable' => 'Solvable',
                    'partiellement_solvable' => 'Partiellement solvable',
                    'non_solvable' => 'Non solvable',
                    default => 'Inconnu'
                }; ?>',
            <?php endforeach; ?>
        ],
        datasets: [{
            data: [
                <?php foreach ($stats_solvabilite as $stat): ?>
                    <?php echo $stat['nombre']; ?>,
                <?php endforeach; ?>
            ],
            backgroundColor: [
                <?php foreach ($stats_solvabilite as $stat): ?>
                    '<?php echo match($stat['status_solvabilite']) {
                        'solvable' => '#28a745',
                        'partiellement_solvable' => '#ffc107',
                        'non_solvable' => '#dc3545',
                        default => '#6c757d'
                    }; ?>',
                <?php endforeach; ?>
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

// Graphique des présences
<?php if (!empty($stats_presences)): ?>
const presencesCtx = document.getElementById('presencesChart').getContext('2d');
const presencesChart = new Chart(presencesCtx, {
    type: 'line',
    data: {
        labels: [
            <?php foreach (array_reverse($stats_presences) as $stat): ?>
                '<?php echo date('d/m', strtotime($stat['date_presence'])); ?>',
            <?php endforeach; ?>
        ],
        datasets: [{
            label: 'Présents',
            data: [
                <?php foreach (array_reverse($stats_presences) as $stat): ?>
                    <?php echo $stat['presents']; ?>,
                <?php endforeach; ?>
            ],
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
