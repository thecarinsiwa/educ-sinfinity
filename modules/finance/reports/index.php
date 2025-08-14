<?php
/**
 * Module de gestion financière - Rapports financiers
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

$page_title = 'Rapports Financiers';

// Obtenir l'année scolaire actuelle
$current_year = getCurrentAcademicYear();

// Paramètres de période
$periode = sanitizeInput($_GET['periode'] ?? 'mois_courant');
$date_debut = '';
$date_fin = '';

switch ($periode) {
    case 'mois_courant':
        $date_debut = date('Y-m-01');
        $date_fin = date('Y-m-t');
        break;
    case 'mois_precedent':
        $date_debut = date('Y-m-01', strtotime('first day of last month'));
        $date_fin = date('Y-m-t', strtotime('last day of last month'));
        break;
    case 'trimestre_courant':
        $mois = date('n');
        $trimestre = ceil($mois / 3);
        $date_debut = date('Y-' . str_pad(($trimestre - 1) * 3 + 1, 2, '0', STR_PAD_LEFT) . '-01');
        $date_fin = date('Y-m-t', strtotime($date_debut . ' +2 months'));
        break;
    case 'annee_courante':
        $date_debut = date('Y-01-01');
        $date_fin = date('Y-12-31');
        break;
    case 'personnalisee':
        $date_debut = sanitizeInput($_GET['date_debut'] ?? date('Y-m-01'));
        $date_fin = sanitizeInput($_GET['date_fin'] ?? date('Y-m-t'));
        break;
}

// Statistiques financières pour la période
$stats = [];

// Recettes par type
$recettes_par_type = $database->query(
    "SELECT type_paiement, SUM(montant) as total, COUNT(*) as nombre
     FROM paiements 
     WHERE status = 'valide' 
     AND date_paiement BETWEEN ? AND ?
     AND annee_scolaire_id = ?
     GROUP BY type_paiement
     ORDER BY total DESC",
    [$date_debut, $date_fin, $current_year['id'] ?? 0]
)->fetchAll();

$stats['total_recettes'] = array_sum(array_column($recettes_par_type, 'total'));
$stats['nombre_paiements'] = array_sum(array_column($recettes_par_type, 'nombre'));

// Recettes par mode de paiement
$recettes_par_mode = $database->query(
    "SELECT mode_paiement, SUM(montant) as total, COUNT(*) as nombre
     FROM paiements 
     WHERE status = 'valide' 
     AND date_paiement BETWEEN ? AND ?
     AND annee_scolaire_id = ?
     GROUP BY mode_paiement
     ORDER BY total DESC",
    [$date_debut, $date_fin, $current_year['id'] ?? 0]
)->fetchAll();

// Recettes par classe/niveau
$recettes_par_niveau = $database->query(
    "SELECT c.niveau, c.nom, SUM(p.montant) as total, COUNT(p.id) as nombre
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

// Évolution quotidienne des recettes (derniers 30 jours)
$evolution_quotidienne = $database->query(
    "SELECT DATE(date_paiement) as date, SUM(montant) as total
     FROM paiements 
     WHERE date_paiement >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
     AND annee_scolaire_id = ?
     GROUP BY DATE(date_paiement)
     ORDER BY date",
    [$current_year['id'] ?? 0]
)->fetchAll();

// Top 10 des plus gros paiements de la période
$gros_paiements = $database->query(
    "SELECT p.*, e.nom, e.prenom, e.numero_matricule, c.nom as classe_nom
     FROM paiements p
     JOIN eleves e ON p.eleve_id = e.id
     JOIN inscriptions i ON e.id = i.eleve_id AND i.annee_scolaire_id = p.annee_scolaire_id
     JOIN classes c ON i.classe_id = c.id
     WHERE p.date_paiement BETWEEN ? AND ?
     AND p.annee_scolaire_id = ?
     ORDER BY p.montant DESC
     LIMIT 10",
    [$date_debut, $date_fin, $current_year['id'] ?? 0]
)->fetchAll();

// Élèves débiteurs
$eleves_debiteurs = $database->query(
    "SELECT e.nom, e.prenom, e.numero_matricule, c.nom as classe_nom,
            SUM(f.montant) as total_du,
            COALESCE(SUM(p.montant), 0) as total_paye,
            (SUM(f.montant) - COALESCE(SUM(p.montant), 0)) as solde_du
     FROM eleves e
     JOIN inscriptions i ON e.id = i.eleve_id
     JOIN classes c ON i.classe_id = c.id
     JOIN frais_scolaires f ON c.id = f.classe_id
     LEFT JOIN paiements p ON e.id = p.eleve_id
     WHERE i.status = 'inscrit' AND i.annee_scolaire_id = ?
     GROUP BY e.id, e.nom, e.prenom, e.numero_matricule, c.nom
     HAVING solde_du > 0
     ORDER BY solde_du DESC
     LIMIT 20",
    [$current_year['id'] ?? 0]
)->fetchAll();

include '../../../includes/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">
        <i class="fas fa-chart-bar me-2"></i>
        Rapports Financiers
    </h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <div class="btn-group me-2">
            <a href="../index.php" class="btn btn-outline-secondary">
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
                <li><a class="dropdown-item" href="export.php?format=pdf&periode=<?php echo $periode; ?>&date_debut=<?php echo $date_debut; ?>&date_fin=<?php echo $date_fin; ?>">
                    <i class="fas fa-file-pdf me-2"></i>Rapport PDF
                </a></li>
                <li><a class="dropdown-item" href="export.php?format=excel&periode=<?php echo $periode; ?>&date_debut=<?php echo $date_debut; ?>&date_fin=<?php echo $date_fin; ?>">
                    <i class="fas fa-file-excel me-2"></i>Données Excel
                </a></li>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item" href="#" onclick="window.print()">
                    <i class="fas fa-print me-2"></i>Imprimer
                </a></li>
            </ul>
        </div>
    </div>
</div>

<!-- Sélection de période -->
<div class="card mb-4 no-print">
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-md-4">
                <label for="periode" class="form-label">Période</label>
                <select class="form-select" id="periode" name="periode" onchange="toggleCustomDates()">
                    <option value="mois_courant" <?php echo $periode === 'mois_courant' ? 'selected' : ''; ?>>Mois courant</option>
                    <option value="mois_precedent" <?php echo $periode === 'mois_precedent' ? 'selected' : ''; ?>>Mois précédent</option>
                    <option value="trimestre_courant" <?php echo $periode === 'trimestre_courant' ? 'selected' : ''; ?>>Trimestre courant</option>
                    <option value="annee_courante" <?php echo $periode === 'annee_courante' ? 'selected' : ''; ?>>Année courante</option>
                    <option value="personnalisee" <?php echo $periode === 'personnalisee' ? 'selected' : ''; ?>>Période personnalisée</option>
                </select>
            </div>
            <div class="col-md-3" id="date-debut-group" style="display: <?php echo $periode === 'personnalisee' ? 'block' : 'none'; ?>;">
                <label for="date_debut" class="form-label">Du</label>
                <input type="date" class="form-control" id="date_debut" name="date_debut" value="<?php echo $date_debut; ?>">
            </div>
            <div class="col-md-3" id="date-fin-group" style="display: <?php echo $periode === 'personnalisee' ? 'block' : 'none'; ?>;">
                <label for="date_fin" class="form-label">Au</label>
                <input type="date" class="form-control" id="date_fin" name="date_fin" value="<?php echo $date_fin; ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label">&nbsp;</label>
                <div class="d-grid">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-chart-bar me-1"></i>
                        Générer
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Résumé de la période -->
<div class="row mb-4">
    <div class="col-12">
        <div class="alert alert-info">
            <h5 class="mb-2">
                <i class="fas fa-calendar me-2"></i>
                Rapport financier du <?php echo formatDate($date_debut); ?> au <?php echo formatDate($date_fin); ?>
            </h5>
            <div class="row">
                <div class="col-md-4">
                    <strong>Total des recettes :</strong> <?php echo formatMoney($stats['total_recettes']); ?>
                </div>
                <div class="col-md-4">
                    <strong>Nombre de paiements :</strong> <?php echo $stats['nombre_paiements']; ?>
                </div>
                <div class="col-md-4">
                    <strong>Paiement moyen :</strong> 
                    <?php echo $stats['nombre_paiements'] > 0 ? formatMoney($stats['total_recettes'] / $stats['nombre_paiements']) : '0 FC'; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Graphiques et statistiques -->
<div class="row mb-4">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-chart-line me-2"></i>
                    Évolution des recettes (30 derniers jours)
                </h5>
            </div>
            <div class="card-body">
                <?php if (!empty($evolution_quotidienne)): ?>
                    <canvas id="evolutionChart" width="100%" height="300"></canvas>
                <?php else: ?>
                    <div class="text-center py-4">
                        <i class="fas fa-chart-line fa-3x text-muted mb-3"></i>
                        <p class="text-muted">Aucune donnée disponible pour cette période</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <div class="col-lg-4">
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
                                <span><?php echo ucfirst($type['type_paiement']); ?></span>
                                <div>
                                    <span class="badge bg-primary me-1"><?php echo $type['nombre']; ?></span>
                                    <strong><?php echo formatMoney($type['total']); ?></strong>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="text-center py-4">
                        <i class="fas fa-chart-pie fa-3x text-muted mb-3"></i>
                        <p class="text-muted">Aucun paiement pour cette période</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Tableaux détaillés -->
<div class="row">
    <div class="col-lg-6">
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-money-bill me-2"></i>
                    Recettes par mode de paiement
                </h5>
            </div>
            <div class="card-body">
                <?php if (!empty($recettes_par_mode)): ?>
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Mode</th>
                                    <th class="text-center">Nombre</th>
                                    <th class="text-end">Montant</th>
                                    <th class="text-center">%</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recettes_par_mode as $mode): ?>
                                    <tr>
                                        <td>
                                            <?php
                                            $modes = [
                                                'especes' => 'Espèces',
                                                'cheque' => 'Chèque',
                                                'virement' => 'Virement',
                                                'mobile_money' => 'Mobile Money'
                                            ];
                                            echo $modes[$mode['mode_paiement']] ?? ucfirst($mode['mode_paiement']);
                                            ?>
                                        </td>
                                        <td class="text-center"><?php echo $mode['nombre']; ?></td>
                                        <td class="text-end"><?php echo formatMoney($mode['total']); ?></td>
                                        <td class="text-center">
                                            <?php echo $stats['total_recettes'] > 0 ? round(($mode['total'] / $stats['total_recettes']) * 100, 1) : 0; ?>%
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p class="text-muted text-center">Aucune donnée disponible</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <div class="col-lg-6">
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-school me-2"></i>
                    Recettes par niveau
                </h5>
            </div>
            <div class="card-body">
                <?php if (!empty($recettes_par_niveau)): ?>
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Niveau</th>
                                    <th class="text-center">Paiements</th>
                                    <th class="text-end">Montant</th>
                                    <th class="text-center">%</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recettes_par_niveau as $niveau): ?>
                                    <tr>
                                        <td>
                                            <span class="badge bg-<?php 
                                                echo $niveau['niveau'] === 'maternelle' ? 'warning' : 
                                                    ($niveau['niveau'] === 'primaire' ? 'success' : 'primary'); 
                                            ?>">
                                                <?php echo ucfirst($niveau['niveau']); ?>
                                            </span>
                                        </td>
                                        <td class="text-center"><?php echo $niveau['nombre']; ?></td>
                                        <td class="text-end"><?php echo formatMoney($niveau['total']); ?></td>
                                        <td class="text-center">
                                            <?php echo $stats['total_recettes'] > 0 ? round(($niveau['total'] / $stats['total_recettes']) * 100, 1) : 0; ?>%
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p class="text-muted text-center">Aucune donnée disponible</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Plus gros paiements et débiteurs -->
<div class="row">
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-trophy me-2"></i>
                    Plus gros paiements de la période
                </h5>
            </div>
            <div class="card-body">
                <?php if (!empty($gros_paiements)): ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($gros_paiements as $index => $paiement): ?>
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
                                <div class="text-end">
                                    <span class="badge bg-success fs-6">
                                        <?php echo formatMoney($paiement['montant']); ?>
                                    </span>
                                    <br><small class="text-muted">#<?php echo $index + 1; ?></small>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p class="text-muted text-center">Aucun paiement pour cette période</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    Élèves débiteurs (Top 20)
                </h5>
                <a href="debtors.php" class="btn btn-sm btn-outline-primary">
                    <i class="fas fa-list me-1"></i>
                    Voir tout
                </a>
            </div>
            <div class="card-body">
                <?php if (!empty($eleves_debiteurs)): ?>
                    <div class="list-group list-group-flush">
                        <?php foreach (array_slice($eleves_debiteurs, 0, 10) as $debiteur): ?>
                            <div class="list-group-item d-flex justify-content-between align-items-start">
                                <div class="ms-2 me-auto">
                                    <div class="fw-bold">
                                        <?php echo htmlspecialchars($debiteur['nom'] . ' ' . $debiteur['prenom']); ?>
                                    </div>
                                    <small class="text-muted">
                                        <?php echo htmlspecialchars($debiteur['classe_nom']); ?> - 
                                        <?php echo htmlspecialchars($debiteur['numero_matricule']); ?>
                                    </small>
                                </div>
                                <div class="text-end">
                                    <span class="badge bg-danger">
                                        <?php echo formatMoney($debiteur['solde_du']); ?>
                                    </span>
                                    <br><small class="text-muted">
                                        Payé: <?php echo formatMoney($debiteur['total_paye']); ?>
                                    </small>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="text-center py-3">
                        <i class="fas fa-check-circle fa-2x text-success mb-2"></i>
                        <p class="text-muted mb-0">Aucun élève débiteur !</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Rapports spécialisés -->
<div class="row mt-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-chart-bar me-2"></i>
                    Rapports spécialisés
                </h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-body text-center">
                                <i class="fas fa-exclamation-triangle fa-3x text-danger mb-3"></i>
                                <h5 class="card-title">Rapport des Débiteurs</h5>
                                <p class="card-text">Analyse détaillée des élèves ayant des dettes de frais scolaires.</p>
                                <a href="debtors.php" class="btn btn-outline-danger">
                                    <i class="fas fa-arrow-right me-1"></i>
                                    Voir le rapport
                                </a>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-body text-center">
                                <i class="fas fa-chart-line fa-3x text-primary mb-3"></i>
                                <h5 class="card-title">Rapport Mensuel</h5>
                                <p class="card-text">Analyse mensuelle des recettes avec graphiques et comparaisons.</p>
                                <a href="monthly.php" class="btn btn-outline-primary">
                                    <i class="fas fa-arrow-right me-1"></i>
                                    Voir le rapport
                                </a>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-body text-center">
                                <i class="fas fa-cogs fa-3x text-warning mb-3"></i>
                                <h5 class="card-title">Gestion Avancée</h5>
                                <p class="card-text">Gestion en masse des frais scolaires avec actions groupées.</p>
                                <a href="../fees/manage.php" class="btn btn-outline-warning">
                                    <i class="fas fa-arrow-right me-1"></i>
                                    Accéder
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Fonction pour afficher/masquer les champs de date personnalisée
function toggleCustomDates() {
    const periode = document.getElementById('periode').value;
    const dateDebutGroup = document.getElementById('date-debut-group');
    const dateFinGroup = document.getElementById('date-fin-group');
    
    if (periode === 'personnalisee') {
        dateDebutGroup.style.display = 'block';
        dateFinGroup.style.display = 'block';
    } else {
        dateDebutGroup.style.display = 'none';
        dateFinGroup.style.display = 'none';
    }
}

// Graphique d'évolution des recettes
<?php if (!empty($evolution_quotidienne)): ?>
const evolutionCtx = document.getElementById('evolutionChart').getContext('2d');
const evolutionChart = new Chart(evolutionCtx, {
    type: 'line',
    data: {
        labels: [<?php echo implode(',', array_map(function($e) { return "'" . formatDate($e['date']) . "'"; }, $evolution_quotidienne)); ?>],
        datasets: [{
            label: 'Recettes quotidiennes (FC)',
            data: [<?php echo implode(',', array_column($evolution_quotidienne, 'total')); ?>],
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
        }
    }
});
<?php endif; ?>

// Graphique des types de paiement
<?php if (!empty($recettes_par_type)): ?>
const typesCtx = document.getElementById('typesChart').getContext('2d');
const typesChart = new Chart(typesCtx, {
    type: 'doughnut',
    data: {
        labels: [<?php echo implode(',', array_map(function($t) { return "'" . ucfirst($t['type_paiement']) . "'"; }, $recettes_par_type)); ?>],
        datasets: [{
            data: [<?php echo implode(',', array_column($recettes_par_type, 'total')); ?>],
            backgroundColor: ['#007bff', '#28a745', '#ffc107', '#dc3545', '#6f42c1', '#fd7e14'],
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
