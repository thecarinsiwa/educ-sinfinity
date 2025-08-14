<?php
/**
 * Module Recouvrement - Rapports de Solvabilité
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
$classe_id = $_GET['classe_id'] ?? '';
$status_solvabilite = $_GET['status_solvabilite'] ?? '';
$seuil_min = $_GET['seuil_min'] ?? '';
$seuil_max = $_GET['seuil_max'] ?? '';

try {
    // Récupérer les classes pour le filtre
    $classes = $database->query("
        SELECT id, nom
        FROM classes
        ORDER BY nom
    ")->fetchAll();

    // Construire la requête avec filtres
    $where_conditions = ["a.status = 'active'"];
    $params = [];

    if (!empty($classe_id)) {
        $where_conditions[] = "i.classe_id = ?";
        $params[] = $classe_id;
    }

    if (!empty($status_solvabilite)) {
        $where_conditions[] = "s.status_solvabilite = ?";
        $params[] = $status_solvabilite;
    }

    if (!empty($seuil_min)) {
        $where_conditions[] = "s.pourcentage_paye >= ?";
        $params[] = $seuil_min;
    }

    if (!empty($seuil_max)) {
        $where_conditions[] = "s.pourcentage_paye <= ?";
        $params[] = $seuil_max;
    }

    $where_clause = implode(' AND ', $where_conditions);

    // Statistiques générales de solvabilité
    $stats_solvabilite = $database->query("
        SELECT 
            COUNT(*) as total_eleves,
            SUM(s.total_paye) as montant_total_paye,
            SUM(s.solde_restant) as solde_total_restant,
            AVG(s.pourcentage_paye) as pourcentage_moyen,
            COUNT(CASE WHEN s.status_solvabilite = 'solvable' THEN 1 END) as solvables,
            COUNT(CASE WHEN s.status_solvabilite = 'partiellement_solvable' THEN 1 END) as partiellement_solvables,
            COUNT(CASE WHEN s.status_solvabilite = 'non_solvable' THEN 1 END) as non_solvables
        FROM solvabilite_eleves s
        JOIN annees_scolaires a ON s.annee_scolaire_id = a.id
        JOIN eleves e ON s.eleve_id = e.id
        LEFT JOIN inscriptions i ON e.id = i.eleve_id AND i.status = 'inscrit'
        WHERE $where_clause
    ", $params)->fetch();

    // Répartition par classe
    $solvabilite_par_classe = $database->query("
        SELECT 
            cl.nom as classe_nom,
            COUNT(*) as total_eleves,
            AVG(s.pourcentage_paye) as pourcentage_moyen,
            SUM(s.total_paye) as montant_total_paye,
            SUM(s.solde_restant) as solde_total_restant,
            COUNT(CASE WHEN s.status_solvabilite = 'solvable' THEN 1 END) as solvables,
            COUNT(CASE WHEN s.status_solvabilite = 'partiellement_solvable' THEN 1 END) as partiellement_solvables,
            COUNT(CASE WHEN s.status_solvabilite = 'non_solvable' THEN 1 END) as non_solvables
        FROM solvabilite_eleves s
        JOIN annees_scolaires a ON s.annee_scolaire_id = a.id
        JOIN eleves e ON s.eleve_id = e.id
        LEFT JOIN inscriptions i ON e.id = i.eleve_id AND i.status = 'inscrit'
        LEFT JOIN classes cl ON i.classe_id = cl.id
        WHERE $where_clause AND cl.id IS NOT NULL
        GROUP BY cl.id, cl.nom
        ORDER BY pourcentage_moyen DESC
    ", $params)->fetchAll();

    // Distribution par tranches de pourcentage
    $distribution_pourcentage = $database->query("
        SELECT 
            CASE 
                WHEN s.pourcentage_paye = 0 THEN '0%'
                WHEN s.pourcentage_paye > 0 AND s.pourcentage_paye < 25 THEN '1-24%'
                WHEN s.pourcentage_paye >= 25 AND s.pourcentage_paye < 50 THEN '25-49%'
                WHEN s.pourcentage_paye >= 50 AND s.pourcentage_paye < 75 THEN '50-74%'
                WHEN s.pourcentage_paye >= 75 AND s.pourcentage_paye < 100 THEN '75-99%'
                WHEN s.pourcentage_paye >= 100 THEN '100%'
            END as tranche,
            COUNT(*) as nombre_eleves,
            SUM(s.total_paye) as montant_paye,
            SUM(s.solde_restant) as solde_restant
        FROM solvabilite_eleves s
        JOIN annees_scolaires a ON s.annee_scolaire_id = a.id
        JOIN eleves e ON s.eleve_id = e.id
        LEFT JOIN inscriptions i ON e.id = i.eleve_id AND i.status = 'inscrit'
        WHERE $where_clause
        GROUP BY 
            CASE 
                WHEN s.pourcentage_paye = 0 THEN '0%'
                WHEN s.pourcentage_paye > 0 AND s.pourcentage_paye < 25 THEN '1-24%'
                WHEN s.pourcentage_paye >= 25 AND s.pourcentage_paye < 50 THEN '25-49%'
                WHEN s.pourcentage_paye >= 50 AND s.pourcentage_paye < 75 THEN '50-74%'
                WHEN s.pourcentage_paye >= 75 AND s.pourcentage_paye < 100 THEN '75-99%'
                WHEN s.pourcentage_paye >= 100 THEN '100%'
            END
        ORDER BY 
            CASE 
                WHEN s.pourcentage_paye = 0 THEN 1
                WHEN s.pourcentage_paye > 0 AND s.pourcentage_paye < 25 THEN 2
                WHEN s.pourcentage_paye >= 25 AND s.pourcentage_paye < 50 THEN 3
                WHEN s.pourcentage_paye >= 50 AND s.pourcentage_paye < 75 THEN 4
                WHEN s.pourcentage_paye >= 75 AND s.pourcentage_paye < 100 THEN 5
                WHEN s.pourcentage_paye >= 100 THEN 6
            END
    ", $params)->fetchAll();

    // Liste détaillée des élèves
    $eleves_solvabilite = $database->query("
        SELECT 
            e.nom, e.prenom, e.numero_matricule,
            cl.nom as classe_nom,
            s.total_paye, s.solde_restant, s.pourcentage_paye,
            s.status_solvabilite, s.derniere_maj
        FROM solvabilite_eleves s
        JOIN annees_scolaires a ON s.annee_scolaire_id = a.id
        JOIN eleves e ON s.eleve_id = e.id
        LEFT JOIN inscriptions i ON e.id = i.eleve_id AND i.status = 'inscrit'
        LEFT JOIN classes cl ON i.classe_id = cl.id
        WHERE $where_clause
        ORDER BY s.pourcentage_paye DESC, s.total_paye DESC
        LIMIT 100
    ", $params)->fetchAll();

} catch (Exception $e) {
    $errors[] = "Erreur lors du chargement des données : " . $e->getMessage();
}

$page_title = "Rapports de Solvabilité";
include '../../../includes/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">
        <i class="fas fa-chart-pie me-2 text-info"></i>
        Rapports de Solvabilité
    </h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <div class="btn-group me-2">
            <a href="index.php" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-1"></i>
                Retour
            </a>
        </div>
        <div class="btn-group">
            <a href="export.php?type=solvabilite&format=excel&<?php echo http_build_query($_GET); ?>" class="btn btn-success">
                <i class="fas fa-file-excel me-1"></i>
                Exporter Excel
            </a>
            <a href="export.php?type=solvabilite&format=pdf&<?php echo http_build_query($_GET); ?>" class="btn btn-danger">
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
            <div class="col-md-3">
                <label for="status_solvabilite" class="form-label">Statut de solvabilité</label>
                <select class="form-select" id="status_solvabilite" name="status_solvabilite">
                    <option value="">Tous les statuts</option>
                    <option value="solvable" <?php echo ($status_solvabilite == 'solvable') ? 'selected' : ''; ?>>Solvable</option>
                    <option value="partiellement_solvable" <?php echo ($status_solvabilite == 'partiellement_solvable') ? 'selected' : ''; ?>>Partiellement solvable</option>
                    <option value="non_solvable" <?php echo ($status_solvabilite == 'non_solvable') ? 'selected' : ''; ?>>Non solvable</option>
                </select>
            </div>
            <div class="col-md-3">
                <label for="seuil_min" class="form-label">Pourcentage minimum (%)</label>
                <input type="number" class="form-control" id="seuil_min" name="seuil_min" 
                       min="0" max="100" value="<?php echo htmlspecialchars($seuil_min); ?>"
                       placeholder="0">
            </div>
            <div class="col-md-3">
                <label for="seuil_max" class="form-label">Pourcentage maximum (%)</label>
                <input type="number" class="form-control" id="seuil_max" name="seuil_max" 
                       min="0" max="100" value="<?php echo htmlspecialchars($seuil_max); ?>"
                       placeholder="100">
            </div>
            <div class="col-12">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-search me-1"></i>
                    Filtrer
                </button>
                <a href="solvabilite.php" class="btn btn-outline-secondary">
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
                <h4 class="mb-0"><?php echo number_format($stats_solvabilite['total_eleves'] ?? 0); ?></h4>
                <small>Total Élèves</small>
            </div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card bg-success text-white shadow-sm">
            <div class="card-body text-center">
                <h4 class="mb-0"><?php echo number_format($stats_solvabilite['solvables'] ?? 0); ?></h4>
                <small>Solvables</small>
            </div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card bg-warning text-white shadow-sm">
            <div class="card-body text-center">
                <h4 class="mb-0"><?php echo number_format($stats_solvabilite['partiellement_solvables'] ?? 0); ?></h4>
                <small>Partiellement</small>
            </div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card bg-danger text-white shadow-sm">
            <div class="card-body text-center">
                <h4 class="mb-0"><?php echo number_format($stats_solvabilite['non_solvables'] ?? 0); ?></h4>
                <small>Non Solvables</small>
            </div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card bg-info text-white shadow-sm">
            <div class="card-body text-center">
                <h4 class="mb-0"><?php echo number_format($stats_solvabilite['pourcentage_moyen'] ?? 0, 1); ?>%</h4>
                <small>% Moyen</small>
            </div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card bg-secondary text-white shadow-sm">
            <div class="card-body text-center">
                <h4 class="mb-0"><?php echo number_format($stats_solvabilite['solde_total_restant'] ?? 0, 0, ',', ' '); ?></h4>
                <small>Solde Restant (FC)</small>
            </div>
        </div>
    </div>
</div>

<div class="row mb-4">
    <!-- Distribution par pourcentage -->
    <div class="col-lg-6">
        <div class="card shadow-sm">
            <div class="card-header bg-light">
                <h5 class="mb-0">
                    <i class="fas fa-chart-bar me-2"></i>
                    Distribution par Tranche de Pourcentage
                </h5>
            </div>
            <div class="card-body">
                <?php if (!empty($distribution_pourcentage)): ?>
                    <canvas id="distributionChart" width="400" height="200"></canvas>
                    <div class="mt-3">
                        <?php foreach ($distribution_pourcentage as $tranche): ?>
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span class="badge bg-<?php 
                                    echo match($tranche['tranche']) {
                                        '0%' => 'danger',
                                        '1-24%' => 'warning',
                                        '25-49%' => 'info',
                                        '50-74%' => 'primary',
                                        '75-99%' => 'success',
                                        '100%' => 'dark',
                                        default => 'secondary'
                                    };
                                ?>">
                                    <?php echo $tranche['tranche']; ?>
                                </span>
                                <span><?php echo number_format($tranche['nombre_eleves']); ?> élèves</span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="text-center text-muted">
                        <i class="fas fa-chart-bar fa-3x mb-3"></i>
                        <p>Aucune donnée disponible</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Solvabilité par classe -->
    <div class="col-lg-6">
        <div class="card shadow-sm">
            <div class="card-header bg-light">
                <h5 class="mb-0">
                    <i class="fas fa-graduation-cap me-2"></i>
                    Solvabilité par Classe
                </h5>
            </div>
            <div class="card-body">
                <?php if (!empty($solvabilite_par_classe)): ?>
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Classe</th>
                                    <th>Élèves</th>
                                    <th>% Moyen</th>
                                    <th>Solvables</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($solvabilite_par_classe as $classe): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($classe['classe_nom']); ?></td>
                                        <td><?php echo number_format($classe['total_eleves']); ?></td>
                                        <td>
                                            <div class="progress" style="height: 15px;">
                                                <div class="progress-bar bg-<?php echo ($classe['pourcentage_moyen'] >= 75) ? 'success' : (($classe['pourcentage_moyen'] >= 50) ? 'warning' : 'danger'); ?>" 
                                                     role="progressbar" style="width: <?php echo $classe['pourcentage_moyen']; ?>%">
                                                    <?php echo number_format($classe['pourcentage_moyen'], 1); ?>%
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="badge bg-success"><?php echo $classe['solvables']; ?></span>
                                            <span class="badge bg-warning"><?php echo $classe['partiellement_solvables']; ?></span>
                                            <span class="badge bg-danger"><?php echo $classe['non_solvables']; ?></span>
                                        </td>
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

<!-- Liste détaillée des élèves -->
<div class="card shadow-sm">
    <div class="card-header bg-light">
        <h5 class="mb-0">
            <i class="fas fa-users me-2"></i>
            Détail de la Solvabilité des Élèves (100 premiers)
        </h5>
    </div>
    <div class="card-body">
        <?php if (!empty($eleves_solvabilite)): ?>
            <div class="table-responsive">
                <table class="table table-hover table-sm">
                    <thead>
                        <tr>
                            <th>Matricule</th>
                            <th>Nom Complet</th>
                            <th>Classe</th>
                            <th>Montant Payé</th>
                            <th>Solde Restant</th>
                            <th>Pourcentage</th>
                            <th>Statut</th>
                            <th>Dernière MAJ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($eleves_solvabilite as $eleve): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($eleve['numero_matricule']); ?></td>
                                <td><?php echo htmlspecialchars($eleve['nom'] . ' ' . $eleve['prenom']); ?></td>
                                <td><?php echo htmlspecialchars($eleve['classe_nom'] ?? 'N/A'); ?></td>
                                <td><?php echo number_format($eleve['total_paye'], 0, ',', ' '); ?> FC</td>
                                <td><?php echo number_format($eleve['solde_restant'], 0, ',', ' '); ?> FC</td>
                                <td>
                                    <div class="progress" style="height: 20px;">
                                        <div class="progress-bar bg-<?php echo ($eleve['pourcentage_paye'] >= 100) ? 'success' : (($eleve['pourcentage_paye'] >= 50) ? 'warning' : 'danger'); ?>" 
                                             role="progressbar" style="width: <?php echo min($eleve['pourcentage_paye'], 100); ?>%">
                                            <?php echo number_format($eleve['pourcentage_paye'], 1); ?>%
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge bg-<?php 
                                        echo match($eleve['status_solvabilite']) {
                                            'solvable' => 'success',
                                            'partiellement_solvable' => 'warning',
                                            'non_solvable' => 'danger',
                                            default => 'secondary'
                                        };
                                    ?>">
                                        <?php 
                                        echo match($eleve['status_solvabilite']) {
                                            'solvable' => 'Solvable',
                                            'partiellement_solvable' => 'Partiel',
                                            'non_solvable' => 'Non solvable',
                                            default => 'Inconnu'
                                        };
                                        ?>
                                    </span>
                                </td>
                                <td><?php echo date('d/m/Y', strtotime($eleve['derniere_maj'])); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="text-center text-muted">
                <i class="fas fa-users fa-3x mb-3"></i>
                <p>Aucun élève trouvé avec les critères sélectionnés</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Inclure Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
// Graphique de distribution par pourcentage
<?php if (!empty($distribution_pourcentage)): ?>
const distributionCtx = document.getElementById('distributionChart').getContext('2d');
const distributionChart = new Chart(distributionCtx, {
    type: 'bar',
    data: {
        labels: [
            <?php foreach ($distribution_pourcentage as $tranche): ?>
                '<?php echo $tranche['tranche']; ?>',
            <?php endforeach; ?>
        ],
        datasets: [{
            label: 'Nombre d\'élèves',
            data: [
                <?php foreach ($distribution_pourcentage as $tranche): ?>
                    <?php echo $tranche['nombre_eleves']; ?>,
                <?php endforeach; ?>
            ],
            backgroundColor: [
                <?php foreach ($distribution_pourcentage as $tranche): ?>
                    '<?php echo match($tranche['tranche']) {
                        '0%' => '#dc3545',
                        '1-24%' => '#ffc107',
                        '25-49%' => '#17a2b8',
                        '50-74%' => '#007bff',
                        '75-99%' => '#28a745',
                        '100%' => '#343a40',
                        default => '#6c757d'
                    }; ?>',
                <?php endforeach; ?>
            ]
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
