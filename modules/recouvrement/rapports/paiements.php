<?php
/**
 * Module Recouvrement - Rapports des Paiements
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
$type_frais = $_GET['type_frais'] ?? '';
$mode_paiement = $_GET['mode_paiement'] ?? '';

try {
    // Récupérer les classes pour le filtre
    $classes = $database->query("
        SELECT id, nom 
        FROM classes 
        WHERE status = 'active' 
        ORDER BY nom
    ")->fetchAll();

    // Récupérer les types de frais pour le filtre
    $types_frais = $database->query("
        SELECT DISTINCT tf.id, tf.nom
        FROM types_frais tf
        JOIN frais_scolaires fs ON tf.id = fs.type_frais_id
        WHERE tf.status = 'active'
        ORDER BY tf.nom
    ")->fetchAll();

    // Construire la requête avec filtres
    $where_conditions = ["p.status = 'valide'"];
    $params = [];

    if (!empty($date_debut)) {
        $where_conditions[] = "DATE(p.date_paiement) >= ?";
        $params[] = $date_debut;
    }

    if (!empty($date_fin)) {
        $where_conditions[] = "DATE(p.date_paiement) <= ?";
        $params[] = $date_fin;
    }

    if (!empty($classe_id)) {
        $where_conditions[] = "i.classe_id = ?";
        $params[] = $classe_id;
    }

    if (!empty($type_frais)) {
        $where_conditions[] = "fs.type_frais_id = ?";
        $params[] = $type_frais;
    }

    if (!empty($mode_paiement)) {
        $where_conditions[] = "p.mode_paiement = ?";
        $params[] = $mode_paiement;
    }

    $where_clause = implode(' AND ', $where_conditions);

    // Statistiques des paiements
    $stats_paiements = $database->query("
        SELECT 
            COUNT(*) as total_paiements,
            SUM(p.montant) as montant_total,
            AVG(p.montant) as montant_moyen,
            COUNT(DISTINCT p.eleve_id) as eleves_payeurs,
            MIN(p.montant) as montant_min,
            MAX(p.montant) as montant_max
        FROM paiements p
        JOIN eleves e ON p.eleve_id = e.id
        LEFT JOIN inscriptions i ON e.id = i.eleve_id AND i.status = 'inscrit'
        LEFT JOIN frais_scolaires fs ON p.frais_id = fs.id
        WHERE $where_clause
    ", $params)->fetch();

    // Paiements par mode de paiement
    $paiements_par_mode = $database->query("
        SELECT 
            p.mode_paiement,
            COUNT(*) as nombre,
            SUM(p.montant) as montant_total
        FROM paiements p
        JOIN eleves e ON p.eleve_id = e.id
        LEFT JOIN inscriptions i ON e.id = i.eleve_id AND i.status = 'inscrit'
        LEFT JOIN frais_scolaires fs ON p.frais_id = fs.id
        WHERE $where_clause
        GROUP BY p.mode_paiement
        ORDER BY montant_total DESC
    ", $params)->fetchAll();

    // Paiements par type de frais
    $paiements_par_type = $database->query("
        SELECT 
            tf.nom as type_frais,
            COUNT(*) as nombre,
            SUM(p.montant) as montant_total
        FROM paiements p
        JOIN frais_scolaires fs ON p.frais_id = fs.id
        JOIN types_frais tf ON fs.type_frais_id = tf.id
        JOIN eleves e ON p.eleve_id = e.id
        LEFT JOIN inscriptions i ON e.id = i.eleve_id AND i.status = 'inscrit'
        WHERE $where_clause
        GROUP BY tf.id, tf.nom
        ORDER BY montant_total DESC
    ", $params)->fetchAll();

    // Évolution des paiements par jour
    $evolution_paiements = $database->query("
        SELECT 
            DATE(p.date_paiement) as date_paiement,
            COUNT(*) as nombre_paiements,
            SUM(p.montant) as montant_jour
        FROM paiements p
        JOIN eleves e ON p.eleve_id = e.id
        LEFT JOIN inscriptions i ON e.id = i.eleve_id AND i.status = 'inscrit'
        LEFT JOIN frais_scolaires fs ON p.frais_id = fs.id
        WHERE $where_clause
        GROUP BY DATE(p.date_paiement)
        ORDER BY date_paiement DESC
        LIMIT 30
    ", $params)->fetchAll();

    // Liste détaillée des paiements
    $paiements_details = $database->query("
        SELECT 
            p.*,
            e.nom, e.prenom, e.numero_matricule,
            cl.nom as classe_nom,
            tf.nom as type_frais,
            fs.nom as frais_nom,
            u.nom as utilisateur_nom
        FROM paiements p
        JOIN eleves e ON p.eleve_id = e.id
        LEFT JOIN inscriptions i ON e.id = i.eleve_id AND i.status = 'inscrit'
        LEFT JOIN classes cl ON i.classe_id = cl.id
        LEFT JOIN frais_scolaires fs ON p.frais_id = fs.id
        LEFT JOIN types_frais tf ON fs.type_frais_id = tf.id
        LEFT JOIN utilisateurs u ON p.created_by = u.id
        WHERE $where_clause
        ORDER BY p.date_paiement DESC, p.created_at DESC
        LIMIT 100
    ", $params)->fetchAll();

} catch (Exception $e) {
    $errors[] = "Erreur lors du chargement des données : " . $e->getMessage();
}

$page_title = "Rapports des Paiements";
include '../../../includes/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">
        <i class="fas fa-money-bill-wave me-2 text-success"></i>
        Rapports des Paiements
    </h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <div class="btn-group me-2">
            <a href="index.php" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-1"></i>
                Retour
            </a>
        </div>
        <div class="btn-group">
            <a href="export.php?type=paiements&format=excel&<?php echo http_build_query($_GET); ?>" class="btn btn-success">
                <i class="fas fa-file-excel me-1"></i>
                Exporter Excel
            </a>
            <a href="export.php?type=paiements&format=pdf&<?php echo http_build_query($_GET); ?>" class="btn btn-danger">
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
                <label for="type_frais" class="form-label">Type de frais</label>
                <select class="form-select" id="type_frais" name="type_frais">
                    <option value="">Tous les types</option>
                    <?php foreach ($types_frais as $type): ?>
                        <option value="<?php echo $type['id']; ?>" 
                                <?php echo ($type_frais == $type['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($type['nom']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label for="mode_paiement" class="form-label">Mode de paiement</label>
                <select class="form-select" id="mode_paiement" name="mode_paiement">
                    <option value="">Tous les modes</option>
                    <option value="especes" <?php echo ($mode_paiement == 'especes') ? 'selected' : ''; ?>>Espèces</option>
                    <option value="virement" <?php echo ($mode_paiement == 'virement') ? 'selected' : ''; ?>>Virement</option>
                    <option value="mobile_money" <?php echo ($mode_paiement == 'mobile_money') ? 'selected' : ''; ?>>Mobile Money</option>
                    <option value="cheque" <?php echo ($mode_paiement == 'cheque') ? 'selected' : ''; ?>>Chèque</option>
                </select>
            </div>
            <div class="col-12">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-search me-1"></i>
                    Filtrer
                </button>
                <a href="paiements.php" class="btn btn-outline-secondary">
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
        <div class="card bg-success text-white shadow-sm">
            <div class="card-body text-center">
                <h4 class="mb-0"><?php echo number_format($stats_paiements['total_paiements'] ?? 0); ?></h4>
                <small>Paiements</small>
            </div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card bg-info text-white shadow-sm">
            <div class="card-body text-center">
                <h4 class="mb-0"><?php echo number_format($stats_paiements['montant_total'] ?? 0, 0, ',', ' '); ?></h4>
                <small>Montant Total (FC)</small>
            </div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card bg-warning text-white shadow-sm">
            <div class="card-body text-center">
                <h4 class="mb-0"><?php echo number_format($stats_paiements['eleves_payeurs'] ?? 0); ?></h4>
                <small>Élèves Payeurs</small>
            </div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card bg-primary text-white shadow-sm">
            <div class="card-body text-center">
                <h4 class="mb-0"><?php echo number_format($stats_paiements['montant_moyen'] ?? 0, 0, ',', ' '); ?></h4>
                <small>Paiement Moyen (FC)</small>
            </div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card bg-secondary text-white shadow-sm">
            <div class="card-body text-center">
                <h4 class="mb-0"><?php echo number_format($stats_paiements['montant_min'] ?? 0, 0, ',', ' '); ?></h4>
                <small>Montant Min (FC)</small>
            </div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card bg-dark text-white shadow-sm">
            <div class="card-body text-center">
                <h4 class="mb-0"><?php echo number_format($stats_paiements['montant_max'] ?? 0, 0, ',', ' '); ?></h4>
                <small>Montant Max (FC)</small>
            </div>
        </div>
    </div>
</div>

<div class="row mb-4">
    <!-- Paiements par mode -->
    <div class="col-lg-6">
        <div class="card shadow-sm">
            <div class="card-header bg-light">
                <h5 class="mb-0">
                    <i class="fas fa-credit-card me-2"></i>
                    Répartition par Mode de Paiement
                </h5>
            </div>
            <div class="card-body">
                <?php if (!empty($paiements_par_mode)): ?>
                    <canvas id="modesPaiementChart" width="400" height="200"></canvas>
                    <div class="mt-3">
                        <?php foreach ($paiements_par_mode as $mode): ?>
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span class="badge bg-primary">
                                    <?php echo ucfirst(str_replace('_', ' ', $mode['mode_paiement'])); ?>
                                </span>
                                <span>
                                    <?php echo number_format($mode['nombre']); ?> paiements
                                    (<?php echo number_format($mode['montant_total'], 0, ',', ' '); ?> FC)
                                </span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="text-center text-muted">
                        <i class="fas fa-credit-card fa-3x mb-3"></i>
                        <p>Aucune donnée disponible</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Paiements par type de frais -->
    <div class="col-lg-6">
        <div class="card shadow-sm">
            <div class="card-header bg-light">
                <h5 class="mb-0">
                    <i class="fas fa-tags me-2"></i>
                    Répartition par Type de Frais
                </h5>
            </div>
            <div class="card-body">
                <?php if (!empty($paiements_par_type)): ?>
                    <canvas id="typesFraisChart" width="400" height="200"></canvas>
                    <div class="mt-3">
                        <?php foreach ($paiements_par_type as $type): ?>
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span class="badge bg-info">
                                    <?php echo htmlspecialchars($type['type_frais']); ?>
                                </span>
                                <span>
                                    <?php echo number_format($type['nombre']); ?> paiements
                                    (<?php echo number_format($type['montant_total'], 0, ',', ' '); ?> FC)
                                </span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="text-center text-muted">
                        <i class="fas fa-tags fa-3x mb-3"></i>
                        <p>Aucune donnée disponible</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Évolution des paiements -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card shadow-sm">
            <div class="card-header bg-light">
                <h5 class="mb-0">
                    <i class="fas fa-chart-line me-2"></i>
                    Évolution des Paiements (30 derniers jours)
                </h5>
            </div>
            <div class="card-body">
                <?php if (!empty($evolution_paiements)): ?>
                    <canvas id="evolutionChart" width="400" height="150"></canvas>
                <?php else: ?>
                    <div class="text-center text-muted">
                        <i class="fas fa-chart-line fa-3x mb-3"></i>
                        <p>Aucune donnée d'évolution disponible</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Liste détaillée des paiements -->
<div class="card shadow-sm">
    <div class="card-header bg-light">
        <h5 class="mb-0">
            <i class="fas fa-list me-2"></i>
            Détail des Paiements (100 derniers)
        </h5>
    </div>
    <div class="card-body">
        <?php if (!empty($paiements_details)): ?>
            <div class="table-responsive">
                <table class="table table-hover table-sm">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Matricule</th>
                            <th>Élève</th>
                            <th>Classe</th>
                            <th>Type de Frais</th>
                            <th>Montant</th>
                            <th>Mode</th>
                            <th>Référence</th>
                            <th>Statut</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($paiements_details as $paiement): ?>
                            <tr>
                                <td><?php echo date('d/m/Y H:i', strtotime($paiement['date_paiement'])); ?></td>
                                <td><?php echo htmlspecialchars($paiement['numero_matricule']); ?></td>
                                <td><?php echo htmlspecialchars($paiement['nom'] . ' ' . $paiement['prenom']); ?></td>
                                <td><?php echo htmlspecialchars($paiement['classe_nom'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($paiement['type_frais'] ?? 'N/A'); ?></td>
                                <td><?php echo number_format($paiement['montant'], 0, ',', ' '); ?> FC</td>
                                <td>
                                    <span class="badge bg-secondary">
                                        <?php echo ucfirst(str_replace('_', ' ', $paiement['mode_paiement'])); ?>
                                    </span>
                                </td>
                                <td><?php echo htmlspecialchars($paiement['reference_paiement'] ?? 'N/A'); ?></td>
                                <td>
                                    <span class="badge bg-<?php 
                                        echo match($paiement['status']) {
                                            'valide' => 'success',
                                            'en_attente' => 'warning',
                                            'annule' => 'danger',
                                            default => 'secondary'
                                        };
                                    ?>">
                                        <?php echo ucfirst($paiement['status']); ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="text-center text-muted">
                <i class="fas fa-money-bill-wave fa-3x mb-3"></i>
                <p>Aucun paiement trouvé avec les critères sélectionnés</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Inclure Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
// Graphique des modes de paiement
<?php if (!empty($paiements_par_mode)): ?>
const modesCtx = document.getElementById('modesPaiementChart').getContext('2d');
const modesChart = new Chart(modesCtx, {
    type: 'pie',
    data: {
        labels: [
            <?php foreach ($paiements_par_mode as $mode): ?>
                '<?php echo ucfirst(str_replace('_', ' ', $mode['mode_paiement'])); ?>',
            <?php endforeach; ?>
        ],
        datasets: [{
            data: [
                <?php foreach ($paiements_par_mode as $mode): ?>
                    <?php echo $mode['montant_total']; ?>,
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

// Graphique des types de frais
<?php if (!empty($paiements_par_type)): ?>
const typesCtx = document.getElementById('typesFraisChart').getContext('2d');
const typesChart = new Chart(typesCtx, {
    type: 'doughnut',
    data: {
        labels: [
            <?php foreach ($paiements_par_type as $type): ?>
                '<?php echo htmlspecialchars($type['type_frais']); ?>',
            <?php endforeach; ?>
        ],
        datasets: [{
            data: [
                <?php foreach ($paiements_par_type as $type): ?>
                    <?php echo $type['montant_total']; ?>,
                <?php endforeach; ?>
            ],
            backgroundColor: [
                '#17a2b8', '#28a745', '#ffc107', '#dc3545', '#6f42c1', '#fd7e14'
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

// Graphique d'évolution
<?php if (!empty($evolution_paiements)): ?>
const evolutionCtx = document.getElementById('evolutionChart').getContext('2d');
const evolutionChart = new Chart(evolutionCtx, {
    type: 'line',
    data: {
        labels: [
            <?php foreach (array_reverse($evolution_paiements) as $evolution): ?>
                '<?php echo date('d/m', strtotime($evolution['date_paiement'])); ?>',
            <?php endforeach; ?>
        ],
        datasets: [{
            label: 'Montant (FC)',
            data: [
                <?php foreach (array_reverse($evolution_paiements) as $evolution): ?>
                    <?php echo $evolution['montant_jour']; ?>,
                <?php endforeach; ?>
            ],
            borderColor: '#28a745',
            backgroundColor: 'rgba(40, 167, 69, 0.1)',
            tension: 0.4,
            yAxisID: 'y'
        }, {
            label: 'Nombre de paiements',
            data: [
                <?php foreach (array_reverse($evolution_paiements) as $evolution): ?>
                    <?php echo $evolution['nombre_paiements']; ?>,
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
            },
            y1: {
                type: 'linear',
                display: true,
                position: 'right',
                grid: {
                    drawOnChartArea: false,
                },
            }
        }
    }
});
<?php endif; ?>
</script>

<?php include '../../../includes/footer.php'; ?>
