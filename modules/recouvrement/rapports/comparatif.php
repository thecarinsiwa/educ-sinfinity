<?php
/**
 * Module Recouvrement - Rapports Comparatifs
 * Application de gestion scolaire - RÃ©publique DÃ©mocratique du Congo
 */

require_once '../../../config/config.php';
require_once '../../../config/database.php';
require_once '../../../includes/functions.php';
require_once '../../../includes/permissions-pages.php';
require_once '../../../includes/ui-permissions.php';

// VÃ©rifier l'authentification et les permissions
requireLogin();
requirePagePermissionFromDB('recouvrement', 'rapports/comparatif', 'read', '../../dashboard.php');

$errors = [];
$success_message = '';

// ParamÃ¨tres de comparaison
$periode1_debut = $_GET['periode1_debut'] ?? date('Y-m-01', strtotime('-1 month'));
$periode1_fin = $_GET['periode1_fin'] ?? date('Y-m-t', strtotime('-1 month'));
$periode2_debut = $_GET['periode2_debut'] ?? date('Y-m-01');
$periode2_fin = $_GET['periode2_fin'] ?? date('Y-m-t');
$type_comparaison = $_GET['type_comparaison'] ?? 'mensuel';

try {
    // Fonction pour rÃ©cupÃ©rer les statistiques d'une pÃ©riode
    function getStatsForPeriod($database, $debut, $fin) {
        // Statistiques des paiements
        $paiements = $database->query("
            SELECT 
                COUNT(*) as total_paiements,
                SUM(montant) as montant_total,
                AVG(montant) as montant_moyen,
                COUNT(DISTINCT eleve_id) as eleves_payeurs
            FROM paiements 
            WHERE status = 'valide' 
            AND DATE(date_paiement) BETWEEN ? AND ?
        ", [$debut, $fin])->fetch();

        // Statistiques de solvabilitÃ© (snapshot actuel)
        $solvabilite = $database->query("
            SELECT 
                COUNT(*) as total_eleves,
                AVG(pourcentage_paye) as pourcentage_moyen,
                SUM(total_paye) as montant_total_paye,
                SUM(solde_restant) as solde_total_restant,
                COUNT(CASE WHEN status_solvabilite = 'solvable' THEN 1 END) as solvables,
                COUNT(CASE WHEN status_solvabilite = 'partiellement_solvable' THEN 1 END) as partiellement_solvables,
                COUNT(CASE WHEN status_solvabilite = 'non_solvable' THEN 1 END) as non_solvables
            FROM solvabilite_eleves s
            JOIN annees_scolaires a ON s.annee_scolaire_id = a.id
            WHERE a.status = 'active'
        ")->fetch();

        // Statistiques des prÃ©sences
        $presences = $database->query("
            SELECT 
                COUNT(*) as total_scans,
                COUNT(DISTINCT eleve_id) as eleves_presents,
                COUNT(DISTINCT DATE(created_at)) as jours_activite,
                COUNT(CASE WHEN type_scan = 'entree' THEN 1 END) as total_entrees,
                COUNT(CASE WHEN type_scan = 'sortie' THEN 1 END) as total_sorties
            FROM presences_qr 
            WHERE DATE(created_at) BETWEEN ? AND ?
        ", [$debut, $fin])->fetch();

        return [
            'paiements' => $paiements,
            'solvabilite' => $solvabilite,
            'presences' => $presences,
            'periode' => ['debut' => $debut, 'fin' => $fin]
        ];
    }

    // RÃ©cupÃ©rer les statistiques pour les deux pÃ©riodes
    $stats_periode1 = getStatsForPeriod($database, $periode1_debut, $periode1_fin);
    $stats_periode2 = getStatsForPeriod($database, $periode2_debut, $periode2_fin);

    // Comparaison par classe pour la pÃ©riode actuelle
    $comparaison_classes = $database->query("
        SELECT 
            cl.nom as classe_nom,
            COUNT(DISTINCT p.eleve_id) as eleves_payeurs,
            SUM(p.montant) as montant_total_paiements,
            AVG(s.pourcentage_paye) as pourcentage_solvabilite_moyen,
            COUNT(DISTINCT pr.eleve_id) as eleves_presents
        FROM classes cl
        LEFT JOIN inscriptions i ON cl.id = i.classe_id AND i.status = 'inscrit'
        LEFT JOIN paiements p ON i.eleve_id = p.eleve_id 
            AND p.status = 'valide' 
            AND DATE(p.date_paiement) BETWEEN ? AND ?
        LEFT JOIN solvabilite_eleves s ON i.eleve_id = s.eleve_id
        LEFT JOIN annees_scolaires a ON s.annee_scolaire_id = a.id AND a.status = 'active'
        LEFT JOIN presences_qr pr ON i.eleve_id = pr.eleve_id 
            AND DATE(pr.created_at) BETWEEN ? AND ?
        WHERE cl.status = 'active'
        GROUP BY cl.id, cl.nom
        ORDER BY montant_total_paiements DESC
    ", [$periode2_debut, $periode2_fin, $periode2_debut, $periode2_fin])->fetchAll();

    // Ã‰volution mensuelle des paiements (12 derniers mois)
    $evolution_mensuelle = $database->query("
        SELECT 
            DATE_FORMAT(date_paiement, '%Y-%m') as mois,
            COUNT(*) as nombre_paiements,
            SUM(montant) as montant_total,
            COUNT(DISTINCT eleve_id) as eleves_payeurs
        FROM paiements 
        WHERE status = 'valide' 
        AND date_paiement >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
        GROUP BY DATE_FORMAT(date_paiement, '%Y-%m')
        ORDER BY mois
    ")->fetchAll();

    // Top 10 des types de frais les plus payÃ©s
    $top_types_frais = $database->query("
        SELECT 
            tf.nom as type_frais,
            COUNT(*) as nombre_paiements,
            SUM(p.montant) as montant_total
        FROM paiements p
        JOIN frais_scolaires fs ON p.frais_id = fs.id
        JOIN type_frais tf ON fs.type_frais_id = tf.id
        JOIN types_frais tf ON fs.type_frais_id = tf.id
        WHERE p.status = 'valide'
        AND DATE(p.date_paiement) BETWEEN ? AND ?
        GROUP BY tf.id, tf.nom
        ORDER BY montant_total DESC
        LIMIT 10
    ", [$periode2_debut, $periode2_fin])->fetchAll();

} catch (Exception $e) {
    $errors[] = "Erreur lors du chargement des donnÃ©es : " . $e->getMessage();
}

// Fonction pour calculer le pourcentage de variation
function calculerVariation($valeur1, $valeur2) {
    if ($valeur1 == 0) return $valeur2 > 0 ? 100 : 0;
    return (($valeur2 - $valeur1) / $valeur1) * 100;
}

$page_title = "Rapports Comparatifs";
include '../../../includes/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">
        <i class="fas fa-chart-bar me-2 text-primary"></i>
        Rapports Comparatifs
    </h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <div class="btn-group me-2">
            <a href="index.php" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-1"></i>
                Retour
            </a>
        </div>
        <div class="btn-group">
            <a href="export.php?type=comparatif&format=excel&<?php echo http_build_query($_GET); ?>" class="btn btn-success">
                <i class="fas fa-file-excel me-1"></i>
                Exporter Excel
            </a>
            <a href="export.php?type=comparatif&format=pdf&<?php echo http_build_query($_GET); ?>" class="btn btn-danger">
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

<!-- ParamÃ¨tres de comparaison -->
<div class="card shadow-sm mb-4">
    <div class="card-header bg-light">
        <h5 class="mb-0">
            <i class="fas fa-calendar-alt me-2"></i>
            ParamÃ¨tres de Comparaison
        </h5>
    </div>
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-md-2">
                <label for="periode1_debut" class="form-label">PÃ©riode 1 - DÃ©but</label>
                <input type="date" class="form-control" id="periode1_debut" name="periode1_debut" 
                       value="<?php echo htmlspecialchars($periode1_debut); ?>">
            </div>
            <div class="col-md-2">
                <label for="periode1_fin" class="form-label">PÃ©riode 1 - Fin</label>
                <input type="date" class="form-control" id="periode1_fin" name="periode1_fin" 
                       value="<?php echo htmlspecialchars($periode1_fin); ?>">
            </div>
            <div class="col-md-2">
                <label for="periode2_debut" class="form-label">PÃ©riode 2 - DÃ©but</label>
                <input type="date" class="form-control" id="periode2_debut" name="periode2_debut" 
                       value="<?php echo htmlspecialchars($periode2_debut); ?>">
            </div>
            <div class="col-md-2">
                <label for="periode2_fin" class="form-label">PÃ©riode 2 - Fin</label>
                <input type="date" class="form-control" id="periode2_fin" name="periode2_fin" 
                       value="<?php echo htmlspecialchars($periode2_fin); ?>">
            </div>
            <div class="col-md-2">
                <label for="type_comparaison" class="form-label">Type</label>
                <select class="form-select" id="type_comparaison" name="type_comparaison">
                    <option value="mensuel" <?php echo ($type_comparaison == 'mensuel') ? 'selected' : ''; ?>>Mensuel</option>
                    <option value="trimestriel" <?php echo ($type_comparaison == 'trimestriel') ? 'selected' : ''; ?>>Trimestriel</option>
                    <option value="annuel" <?php echo ($type_comparaison == 'annuel') ? 'selected' : ''; ?>>Annuel</option>
                    <option value="personnalise" <?php echo ($type_comparaison == 'personnalise') ? 'selected' : ''; ?>>PersonnalisÃ©</option>
                </select>
            </div>
            <div class="col-md-2 d-flex align-items-end">
                <button type="submit" class="btn btn-primary w-100">
                    <i class="fas fa-sync me-1"></i>
                    Comparer
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Comparaison des pÃ©riodes -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card shadow-sm">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0">
                    <i class="fas fa-balance-scale me-2"></i>
                    Comparaison des PÃ©riodes
                </h5>
            </div>
            <div class="card-body">
                <div class="row text-center">
                    <div class="col-md-6">
                        <h6 class="text-muted">
                            PÃ©riode 1: <?php echo date('d/m/Y', strtotime($periode1_debut)); ?> - <?php echo date('d/m/Y', strtotime($periode1_fin)); ?>
                        </h6>
                    </div>
                    <div class="col-md-6">
                        <h6 class="text-muted">
                            PÃ©riode 2: <?php echo date('d/m/Y', strtotime($periode2_debut)); ?> - <?php echo date('d/m/Y', strtotime($periode2_fin)); ?>
                        </h6>
                    </div>
                </div>
                
                <div class="table-responsive mt-3">
                    <table class="table table-bordered">
                        <thead class="table-light">
                            <tr>
                                <th>Indicateur</th>
                                <th class="text-center">PÃ©riode 1</th>
                                <th class="text-center">PÃ©riode 2</th>
                                <th class="text-center">Variation</th>
                                <th class="text-center">Tendance</th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- Paiements -->
                            <tr>
                                <td><strong>Nombre de Paiements</strong></td>
                                <td class="text-center"><?php echo number_format($stats_periode1['paiements']['total_paiements'] ?? 0); ?></td>
                                <td class="text-center"><?php echo number_format($stats_periode2['paiements']['total_paiements'] ?? 0); ?></td>
                                <td class="text-center">
                                    <?php 
                                    $variation = calculerVariation($stats_periode1['paiements']['total_paiements'] ?? 0, $stats_periode2['paiements']['total_paiements'] ?? 0);
                                    $class = $variation > 0 ? 'text-success' : ($variation < 0 ? 'text-danger' : 'text-muted');
                                    echo "<span class='$class'>" . number_format($variation, 1) . "%</span>";
                                    ?>
                                </td>
                                <td class="text-center">
                                    <?php if ($variation > 0): ?>
                                        <i class="fas fa-arrow-up text-success"></i>
                                    <?php elseif ($variation < 0): ?>
                                        <i class="fas fa-arrow-down text-danger"></i>
                                    <?php else: ?>
                                        <i class="fas fa-minus text-muted"></i>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <tr>
                                <td><strong>Montant Total (FC)</strong></td>
                                <td class="text-center"><?php echo number_format($stats_periode1['paiements']['montant_total'] ?? 0, 0, ',', ' '); ?></td>
                                <td class="text-center"><?php echo number_format($stats_periode2['paiements']['montant_total'] ?? 0, 0, ',', ' '); ?></td>
                                <td class="text-center">
                                    <?php 
                                    $variation = calculerVariation($stats_periode1['paiements']['montant_total'] ?? 0, $stats_periode2['paiements']['montant_total'] ?? 0);
                                    $class = $variation > 0 ? 'text-success' : ($variation < 0 ? 'text-danger' : 'text-muted');
                                    echo "<span class='$class'>" . number_format($variation, 1) . "%</span>";
                                    ?>
                                </td>
                                <td class="text-center">
                                    <?php if ($variation > 0): ?>
                                        <i class="fas fa-arrow-up text-success"></i>
                                    <?php elseif ($variation < 0): ?>
                                        <i class="fas fa-arrow-down text-danger"></i>
                                    <?php else: ?>
                                        <i class="fas fa-minus text-muted"></i>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <tr>
                                <td><strong>Ã‰lÃ¨ves Payeurs</strong></td>
                                <td class="text-center"><?php echo number_format($stats_periode1['paiements']['eleves_payeurs'] ?? 0); ?></td>
                                <td class="text-center"><?php echo number_format($stats_periode2['paiements']['eleves_payeurs'] ?? 0); ?></td>
                                <td class="text-center">
                                    <?php 
                                    $variation = calculerVariation($stats_periode1['paiements']['eleves_payeurs'] ?? 0, $stats_periode2['paiements']['eleves_payeurs'] ?? 0);
                                    $class = $variation > 0 ? 'text-success' : ($variation < 0 ? 'text-danger' : 'text-muted');
                                    echo "<span class='$class'>" . number_format($variation, 1) . "%</span>";
                                    ?>
                                </td>
                                <td class="text-center">
                                    <?php if ($variation > 0): ?>
                                        <i class="fas fa-arrow-up text-success"></i>
                                    <?php elseif ($variation < 0): ?>
                                        <i class="fas fa-arrow-down text-danger"></i>
                                    <?php else: ?>
                                        <i class="fas fa-minus text-muted"></i>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <!-- PrÃ©sences -->
                            <tr>
                                <td><strong>Total Scans PrÃ©sence</strong></td>
                                <td class="text-center"><?php echo number_format($stats_periode1['presences']['total_scans'] ?? 0); ?></td>
                                <td class="text-center"><?php echo number_format($stats_periode2['presences']['total_scans'] ?? 0); ?></td>
                                <td class="text-center">
                                    <?php 
                                    $variation = calculerVariation($stats_periode1['presences']['total_scans'] ?? 0, $stats_periode2['presences']['total_scans'] ?? 0);
                                    $class = $variation > 0 ? 'text-success' : ($variation < 0 ? 'text-danger' : 'text-muted');
                                    echo "<span class='$class'>" . number_format($variation, 1) . "%</span>";
                                    ?>
                                </td>
                                <td class="text-center">
                                    <?php if ($variation > 0): ?>
                                        <i class="fas fa-arrow-up text-success"></i>
                                    <?php elseif ($variation < 0): ?>
                                        <i class="fas fa-arrow-down text-danger"></i>
                                    <?php else: ?>
                                        <i class="fas fa-minus text-muted"></i>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <tr>
                                <td><strong>Ã‰lÃ¨ves PrÃ©sents</strong></td>
                                <td class="text-center"><?php echo number_format($stats_periode1['presences']['eleves_presents'] ?? 0); ?></td>
                                <td class="text-center"><?php echo number_format($stats_periode2['presences']['eleves_presents'] ?? 0); ?></td>
                                <td class="text-center">
                                    <?php 
                                    $variation = calculerVariation($stats_periode1['presences']['eleves_presents'] ?? 0, $stats_periode2['presences']['eleves_presents'] ?? 0);
                                    $class = $variation > 0 ? 'text-success' : ($variation < 0 ? 'text-danger' : 'text-muted');
                                    echo "<span class='$class'>" . number_format($variation, 1) . "%</span>";
                                    ?>
                                </td>
                                <td class="text-center">
                                    <?php if ($variation > 0): ?>
                                        <i class="fas fa-arrow-up text-success"></i>
                                    <?php elseif ($variation < 0): ?>
                                        <i class="fas fa-arrow-down text-danger"></i>
                                    <?php else: ?>
                                        <i class="fas fa-minus text-muted"></i>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row mb-4">
    <!-- Ã‰volution mensuelle -->
    <div class="col-lg-8">
        <div class="card shadow-sm">
            <div class="card-header bg-light">
                <h5 class="mb-0">
                    <i class="fas fa-chart-line me-2"></i>
                    Ã‰volution Mensuelle des Paiements (12 derniers mois)
                </h5>
            </div>
            <div class="card-body">
                <?php if (!empty($evolution_mensuelle)): ?>
                    <canvas id="evolutionMensuelleChart" width="400" height="200"></canvas>
                <?php else: ?>
                    <div class="text-center text-muted">
                        <i class="fas fa-chart-line fa-3x mb-3"></i>
                        <p>Aucune donnÃ©e d'Ã©volution disponible</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Top types de frais -->
    <div class="col-lg-4">
        <div class="card shadow-sm">
            <div class="card-header bg-light">
                <h5 class="mb-0">
                    <i class="fas fa-tags me-2"></i>
                    Top Types de Frais (PÃ©riode 2)
                </h5>
            </div>
            <div class="card-body">
                <?php if (!empty($top_types_frais)): ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($top_types_frais as $index => $type): ?>
                            <div class="list-group-item d-flex justify-content-between align-items-center px-0">
                                <div>
                                    <span class="badge bg-primary me-2"><?php echo $index + 1; ?></span>
                                    <small><?php echo htmlspecialchars($type['type_frais']); ?></small>
                                </div>
                                <div class="text-end">
                                    <div class="fw-bold"><?php echo number_format($type['montant_total'], 0, ',', ' '); ?> FC</div>
                                    <small class="text-muted"><?php echo number_format($type['nombre_paiements']); ?> paiements</small>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="text-center text-muted">
                        <i class="fas fa-tags fa-3x mb-3"></i>
                        <p>Aucune donnÃ©e disponible</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Comparaison par classe -->
<div class="card shadow-sm">
    <div class="card-header bg-light">
        <h5 class="mb-0">
            <i class="fas fa-graduation-cap me-2"></i>
            Performance par Classe (PÃ©riode 2)
        </h5>
    </div>
    <div class="card-body">
        <?php if (!empty($comparaison_classes)): ?>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Classe</th>
                            <th>Ã‰lÃ¨ves Payeurs</th>
                            <th>Montant Total Paiements</th>
                            <th>% SolvabilitÃ© Moyen</th>
                            <th>Ã‰lÃ¨ves PrÃ©sents</th>
                            <th>Performance Globale</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($comparaison_classes as $classe): ?>
                            <?php 
                            $score_performance = 0;
                            if ($classe['montant_total_paiements'] > 0) $score_performance += 30;
                            if ($classe['pourcentage_solvabilite_moyen'] >= 75) $score_performance += 40;
                            elseif ($classe['pourcentage_solvabilite_moyen'] >= 50) $score_performance += 20;
                            if ($classe['eleves_presents'] > 0) $score_performance += 30;
                            ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($classe['classe_nom']); ?></strong></td>
                                <td>
                                    <span class="badge bg-info"><?php echo number_format($classe['eleves_payeurs'] ?? 0); ?></span>
                                </td>
                                <td><?php echo number_format($classe['montant_total_paiements'] ?? 0, 0, ',', ' '); ?> FC</td>
                                <td>
                                    <div class="progress" style="height: 20px;">
                                        <div class="progress-bar bg-<?php echo ($classe['pourcentage_solvabilite_moyen'] >= 75) ? 'success' : (($classe['pourcentage_solvabilite_moyen'] >= 50) ? 'warning' : 'danger'); ?>" 
                                             role="progressbar" style="width: <?php echo min($classe['pourcentage_solvabilite_moyen'] ?? 0, 100); ?>%">
                                            <?php echo number_format($classe['pourcentage_solvabilite_moyen'] ?? 0, 1); ?>%
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge bg-success"><?php echo number_format($classe['eleves_presents'] ?? 0); ?></span>
                                </td>
                                <td>
                                    <div class="progress" style="height: 20px;">
                                        <div class="progress-bar bg-<?php echo ($score_performance >= 80) ? 'success' : (($score_performance >= 60) ? 'warning' : 'danger'); ?>" 
                                             role="progressbar" style="width: <?php echo $score_performance; ?>%">
                                            <?php echo $score_performance; ?>%
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="text-center text-muted">
                <i class="fas fa-graduation-cap fa-3x mb-3"></i>
                <p>Aucune donnÃ©e par classe disponible</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Inclure Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
// Graphique d'Ã©volution mensuelle
<?php if (!empty($evolution_mensuelle)): ?>
const evolutionMensuelleCtx = document.getElementById('evolutionMensuelleChart').getContext('2d');
const evolutionMensuelleChart = new Chart(evolutionMensuelleCtx, {
    type: 'line',
    data: {
        labels: [
            <?php foreach ($evolution_mensuelle as $mois): ?>
                '<?php echo date('m/Y', strtotime($mois['mois'] . '-01')); ?>',
            <?php endforeach; ?>
        ],
        datasets: [{
            label: 'Montant (FC)',
            data: [
                <?php foreach ($evolution_mensuelle as $mois): ?>
                    <?php echo $mois['montant_total']; ?>,
                <?php endforeach; ?>
            ],
            borderColor: '#28a745',
            backgroundColor: 'rgba(40, 167, 69, 0.1)',
            tension: 0.4,
            yAxisID: 'y'
        }, {
            label: 'Nombre de paiements',
            data: [
                <?php foreach ($evolution_mensuelle as $mois): ?>
                    <?php echo $mois['nombre_paiements']; ?>,
                <?php endforeach; ?>
            ],
            borderColor: '#007bff',
            backgroundColor: 'rgba(0, 123, 255, 0.1)',
            tension: 0.4,
            yAxisID: 'y1'
        }]
    },
    options: {
        responsive: true,
        scales: {
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
                    text: 'Nombre de paiements'
                },
                grid: {
                    drawOnChartArea: false,
                },
            }
        }
    }
});
<?php endif; ?>

// Mise Ã  jour automatique des dates selon le type de comparaison
document.getElementById('type_comparaison').addEventListener('change', function() {
    const type = this.value;
    const now = new Date();
    
    if (type === 'mensuel') {
        // Mois prÃ©cÃ©dent vs mois actuel
        const moisPrecedent = new Date(now.getFullYear(), now.getMonth() - 1, 1);
        const finMoisPrecedent = new Date(now.getFullYear(), now.getMonth(), 0);
        const debutMoisActuel = new Date(now.getFullYear(), now.getMonth(), 1);
        const finMoisActuel = new Date(now.getFullYear(), now.getMonth() + 1, 0);
        
        document.getElementById('periode1_debut').value = moisPrecedent.toISOString().split('T')[0];
        document.getElementById('periode1_fin').value = finMoisPrecedent.toISOString().split('T')[0];
        document.getElementById('periode2_debut').value = debutMoisActuel.toISOString().split('T')[0];
        document.getElementById('periode2_fin').value = finMoisActuel.toISOString().split('T')[0];
    }
    // Ajouter d'autres types si nÃ©cessaire
});
</script>

<?php include '../../../includes/footer.php'; ?>

