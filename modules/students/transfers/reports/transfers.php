<?php
/**
 * Rapports des transferts d'élèves
 * Application de gestion scolaire - République Démocratique du Congo
 */

require_once '../../../../config/config.php';
require_once '../../../../config/database.php';
require_once '../../../../includes/functions.php';

// Vérifier l'authentification et les permissions
requireLogin();
if (!checkPermission('students')) {
    redirectTo('../../../../login.php');
}

$page_title = "Rapports des transferts";

// Récupérer les paramètres de filtre
$period = $_GET['period'] ?? 'month';
$type_filter = $_GET['type'] ?? '';
$status_filter = $_GET['status'] ?? '';
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
$where_conditions = ["t.date_demande BETWEEN ? AND ?"];
$params = [$date_from, $date_to];

if ($type_filter) {
    $where_conditions[] = "t.type_mouvement = ?";
    $params[] = $type_filter;
}

if ($status_filter) {
    $where_conditions[] = "t.statut = ?";
    $params[] = $status_filter;
}

$where_clause = implode(' AND ', $where_conditions);

// Statistiques générales
$general_stats = $database->query(
    "SELECT 
        COUNT(*) as total_transfers,
        COUNT(CASE WHEN t.type_mouvement = 'transfert_entrant' THEN 1 END) as entrants,
        COUNT(CASE WHEN t.type_mouvement = 'transfert_sortant' THEN 1 END) as sortants,
        COUNT(CASE WHEN t.type_mouvement = 'sortie_definitive' THEN 1 END) as sorties,
        COUNT(CASE WHEN t.statut = 'en_attente' THEN 1 END) as en_attente,
        COUNT(CASE WHEN t.statut = 'approuve' THEN 1 END) as approuve,
        COUNT(CASE WHEN t.statut = 'rejete' THEN 1 END) as rejete,
        COUNT(CASE WHEN t.statut = 'complete' THEN 1 END) as complete,
        SUM(t.frais_transfert) as total_frais,
        SUM(t.frais_payes) as total_payes
     FROM transfers t
     WHERE $where_clause",
    $params
)->fetch();

// Évolution mensuelle
$monthly_evolution = []; // Temporairement désactivé pour debug

// Top des écoles d'origine/destination
$top_schools = []; // Temporairement désactivé pour debug

// Statistiques par statut
$status_stats = []; // Temporairement désactivé pour debug

// Statistiques par classe
$class_stats = $database->query(
    "SELECT 
        c.niveau,
        c.nom as classe_nom,
        COUNT(*) as total_transfers,
        COUNT(CASE WHEN t.type_mouvement = 'transfert_entrant' THEN 1 END) as entrants,
        COUNT(CASE WHEN t.type_mouvement = 'transfert_sortant' THEN 1 END) as sortants,
        COUNT(CASE WHEN t.type_mouvement = 'sortie_definitive' THEN 1 END) as sorties
     FROM transfers t
     INNER JOIN classes c ON (t.classe_origine_id = c.id OR t.classe_destination_id = c.id)
     WHERE $where_clause
     GROUP BY c.id, c.niveau, c.nom
     ORDER BY total_transfers DESC",
    $params
)->fetchAll();

// Temps de traitement moyen
$processing_time = $database->query(
    "SELECT 
        AVG(CASE WHEN t.date_approbation IS NOT NULL THEN DATEDIFF(t.date_approbation, t.date_demande) END) as avg_approval_days,
        AVG(CASE WHEN t.date_effective IS NOT NULL THEN DATEDIFF(t.date_effective, t.date_demande) END) as avg_completion_days,
        MIN(CASE WHEN t.date_approbation IS NOT NULL THEN DATEDIFF(t.date_approbation, t.date_demande) END) as min_approval_days,
        MAX(CASE WHEN t.date_approbation IS NOT NULL THEN DATEDIFF(t.date_approbation, t.date_demande) END) as max_approval_days
     FROM transfers t
     WHERE $where_clause",
    $params
)->fetch();

// Derniers transferts
$recent_transfers = $database->query(
    "SELECT 
        t.*,
        e.nom as eleve_nom,
        e.prenom as eleve_prenom,
        c1.nom as classe_origine_nom,
        c2.nom as classe_destination_nom
     FROM transfers t
     LEFT JOIN eleves e ON t.eleve_id = e.id
     LEFT JOIN classes c1 ON t.classe_origine_id = c1.id
     LEFT JOIN classes c2 ON t.classe_destination_id = c2.id
     WHERE $where_clause
     ORDER BY t.date_demande DESC
     LIMIT 10",
    $params
)->fetchAll();

include '../../../../includes/header.php';
?>

<!-- Styles CSS modernes -->
<style>
.reports-header {
    background: linear-gradient(135deg, #17a2b8 0%, #20c997 100%);
    color: white;
    padding: 2rem 0;
    margin: -20px -15px 30px -15px;
    border-radius: 0 0 20px 20px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.1);
}

.reports-header h1 {
    font-weight: 300;
    font-size: 2.5rem;
    margin-bottom: 0.5rem;
}

.reports-header .subtitle {
    opacity: 0.9;
    font-size: 1.1rem;
}

.stats-card {
    background: white;
    border-radius: 15px;
    padding: 1.5rem;
    box-shadow: 0 5px 15px rgba(0,0,0,0.08);
    border: none;
    transition: all 0.3s ease;
    margin-bottom: 2rem;
    position: relative;
    overflow: hidden;
}

.stats-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
    background: linear-gradient(90deg, var(--card-color, #17a2b8), var(--card-color-light, #20c997));
}

.stats-card.primary { --card-color: #007bff; --card-color-light: #66b3ff; }
.stats-card.success { --card-color: #28a745; --card-color-light: #66bb6a; }
.stats-card.warning { --card-color: #ffc107; --card-color-light: #ffd54f; }
.stats-card.danger { --card-color: #dc3545; --card-color-light: #ff6b7a; }
.stats-card.info { --card-color: #17a2b8; --card-color-light: #58c4d4; }

.stats-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 30px rgba(0,0,0,0.15);
}

.stat-number {
    font-size: 2.5rem;
    font-weight: 700;
    color: var(--card-color);
    margin-bottom: 0.5rem;
    display: block;
}

.stat-label {
    color: #6c757d;
    font-size: 0.9rem;
    font-weight: 500;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.stat-icon {
    position: absolute;
    right: 1.5rem;
    top: 50%;
    transform: translateY(-50%);
    font-size: 3rem;
    opacity: 0.1;
    color: var(--card-color);
}

.chart-card {
    background: white;
    border-radius: 15px;
    padding: 2rem;
    box-shadow: 0 5px 15px rgba(0,0,0,0.08);
    margin-bottom: 2rem;
}

.filters-card {
    background: white;
    border-radius: 15px;
    padding: 1.5rem;
    box-shadow: 0 2px 10px rgba(0,0,0,0.05);
    margin-bottom: 2rem;
}

.table-modern {
    background: white;
    border-radius: 15px;
    overflow: hidden;
    box-shadow: 0 5px 15px rgba(0,0,0,0.08);
}

.table-modern thead th {
    background: linear-gradient(135deg, #495057 0%, #6c757d 100%);
    color: white;
    border: none;
    padding: 1rem;
    font-weight: 600;
    text-transform: uppercase;
    font-size: 0.85rem;
    letter-spacing: 0.5px;
}

.btn-modern {
    border-radius: 25px;
    padding: 0.5rem 1.5rem;
    font-weight: 500;
    transition: all 0.3s ease;
    border: none;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
}

.btn-modern:hover {
    transform: translateY(-2px);
    box-shadow: 0 5px 20px rgba(0,0,0,0.2);
}

@keyframes fadeInUp {
    from {
        opacity: 0;
        transform: translateY(30px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.animate-fade-in {
    animation: fadeInUp 0.6s ease-out;
}

.animate-delay-1 { animation-delay: 0.1s; }
.animate-delay-2 { animation-delay: 0.2s; }
.animate-delay-3 { animation-delay: 0.3s; }
.animate-delay-4 { animation-delay: 0.4s; }

@media (max-width: 768px) {
    .reports-header {
        margin: -20px -15px 20px -15px;
        padding: 1.5rem 0;
    }
    
    .reports-header h1 {
        font-size: 2rem;
    }
    
    .stats-card {
        padding: 1rem;
        margin-bottom: 1rem;
    }
}
</style>

<!-- En-tête moderne -->
<div class="reports-header">
    <div class="container-fluid">
        <div class="row align-items-center">
            <div class="col-md-8">
                <h1 class="animate-fade-in">
                    <i class="fas fa-chart-bar me-3"></i>
                    Rapports des transferts
                </h1>
                <p class="subtitle animate-fade-in animate-delay-1">
                    Analyse détaillée des mouvements d'élèves - Période: <?php echo date('d/m/Y', strtotime($date_from)) . ' au ' . date('d/m/Y', strtotime($date_to)); ?>
                </p>
            </div>
            <div class="col-md-4 text-end">
                <div class="animate-fade-in animate-delay-2">
                    <a href="../index.php" class="btn btn-light btn-modern me-2">
                        <i class="fas fa-arrow-left me-1"></i>
                        Retour
                    </a>
                    <button type="button" class="btn btn-info btn-modern me-2" onclick="refreshData()" data-bs-toggle="tooltip" title="Rafraîchir les données">
                        <i class="fas fa-sync-alt me-1"></i>
                        Actualiser
                    </button>
                    <button type="button" class="btn btn-success btn-modern" onclick="exportReport()">
                        <i class="fas fa-file-excel me-1"></i>
                        Exporter
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Filtres -->
<div class="filters-card animate-fade-in animate-delay-1">
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
            <label for="type" class="form-label">Type</label>
            <select class="form-select" id="type" name="type">
                <option value="">Tous les types</option>
                <option value="transfert_entrant" <?php echo $type_filter === 'transfert_entrant' ? 'selected' : ''; ?>>Entrants</option>
                <option value="transfert_sortant" <?php echo $type_filter === 'transfert_sortant' ? 'selected' : ''; ?>>Sortants</option>
                <option value="sortie_definitive" <?php echo $type_filter === 'sortie_definitive' ? 'selected' : ''; ?>>Sorties</option>
            </select>
        </div>
        
        <div class="col-md-2">
            <label for="status" class="form-label">Statut</label>
            <select class="form-select" id="status" name="status">
                <option value="">Tous les statuts</option>
                <option value="en_attente" <?php echo $status_filter === 'en_attente' ? 'selected' : ''; ?>>En attente</option>
                <option value="approuve" <?php echo $status_filter === 'approuve' ? 'selected' : ''; ?>>Approuvé</option>
                <option value="rejete" <?php echo $status_filter === 'rejete' ? 'selected' : ''; ?>>Rejeté</option>
                <option value="complete" <?php echo $status_filter === 'complete' ? 'selected' : ''; ?>>Complété</option>
            </select>
        </div>
        
        <div class="col-md-2 d-flex align-items-end">
            <button type="submit" class="btn btn-primary btn-modern w-100">
                <i class="fas fa-search me-1"></i>
                Analyser
            </button>
        </div>
    </form>
</div>

<!-- Statistiques générales -->
<div class="row animate-fade-in animate-delay-2">
    <div class="col-lg-2 col-md-4 col-sm-6 mb-4">
        <div class="stats-card info">
            <span class="stat-number"><?php echo number_format($general_stats['total_transfers']); ?></span>
            <span class="stat-label">Total transferts</span>
            <i class="fas fa-exchange-alt stat-icon"></i>
        </div>
    </div>
    
    <div class="col-lg-2 col-md-4 col-sm-6 mb-4">
        <div class="stats-card success">
            <span class="stat-number"><?php echo number_format($general_stats['entrants']); ?></span>
            <span class="stat-label">Entrants</span>
            <i class="fas fa-arrow-right stat-icon"></i>
        </div>
    </div>
    
    <div class="col-lg-2 col-md-4 col-sm-6 mb-4">
        <div class="stats-card warning">
            <span class="stat-number"><?php echo number_format($general_stats['sortants']); ?></span>
            <span class="stat-label">Sortants</span>
            <i class="fas fa-arrow-left stat-icon"></i>
        </div>
    </div>
    
    <div class="col-lg-2 col-md-4 col-sm-6 mb-4">
        <div class="stats-card danger">
            <span class="stat-number"><?php echo number_format($general_stats['sorties']); ?></span>
            <span class="stat-label">Sorties définitives</span>
            <i class="fas fa-graduation-cap stat-icon"></i>
        </div>
    </div>
    
    <div class="col-lg-2 col-md-4 col-sm-6 mb-4">
        <div class="stats-card primary">
            <span class="stat-number"><?php echo number_format($general_stats['complete']); ?></span>
            <span class="stat-label">Complétés</span>
            <i class="fas fa-check-circle stat-icon"></i>
        </div>
    </div>
    
    <div class="col-lg-2 col-md-4 col-sm-6 mb-4">
        <div class="stats-card info">
            <span class="stat-number"><?php echo number_format($general_stats['total_frais']); ?></span>
            <span class="stat-label">Frais totaux (FC)</span>
            <i class="fas fa-money-bill stat-icon"></i>
        </div>
    </div>
    
    <div class="col-lg-2 col-md-4 col-sm-6 mb-4">
        <div class="stats-card success">
            <span class="stat-number"><?php echo number_format($general_stats['total_payes']); ?></span>
            <span class="stat-label">Frais payés (FC)</span>
            <i class="fas fa-credit-card stat-icon"></i>
        </div>
    </div>
</div>

<!-- Statistiques par statut -->
<?php if (!empty($status_stats)): ?>
<div class="chart-card animate-fade-in animate-delay-3">
    <h5 class="mb-3">
        <i class="fas fa-chart-pie me-2"></i>
        Répartition par statut
    </h5>
    <div class="row">
        <?php foreach ($status_stats as $stat): ?>
            <div class="col-md-3 mb-3">
                <div class="card text-center">
                    <div class="card-body">
                        <h4 class="text-<?php 
                            echo $stat['statut'] === 'complete' ? 'success' : 
                                ($stat['statut'] === 'approuve' ? 'primary' : 
                                ($stat['statut'] === 'en_attente' ? 'warning' : 'danger')); 
                        ?>">
                            <?php echo $stat['nombre']; ?>
                        </h4>
                        <p class="card-text">
                            <strong><?php echo ucfirst(str_replace('_', ' ', $stat['statut'])); ?></strong><br>
                            <small class="text-muted"><?php echo $stat['pourcentage']; ?>%</small>
                        </p>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<!-- Temps de traitement -->
<?php if ($processing_time['avg_approval_days'] !== null): ?>
<div class="chart-card animate-fade-in animate-delay-3">
    <h5 class="mb-3">
        <i class="fas fa-clock me-2"></i>
        Temps de traitement
    </h5>
    <div class="row">
        <div class="col-md-3">
            <div class="text-center">
                <h4 class="text-primary"><?php echo round($processing_time['avg_approval_days'], 1); ?></h4>
                <p class="text-muted">Jours moyens pour approbation</p>
            </div>
        </div>
        <div class="col-md-3">
            <div class="text-center">
                <h4 class="text-success"><?php echo round($processing_time['avg_completion_days'], 1); ?></h4>
                <p class="text-muted">Jours moyens pour complétion</p>
            </div>
        </div>
        <div class="col-md-3">
            <div class="text-center">
                <h4 class="text-info"><?php echo $processing_time['min_approval_days']; ?></h4>
                <p class="text-muted">Temps minimum (jours)</p>
            </div>
        </div>
        <div class="col-md-3">
            <div class="text-center">
                <h4 class="text-warning"><?php echo $processing_time['max_approval_days']; ?></h4>
                <p class="text-muted">Temps maximum (jours)</p>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Évolution mensuelle -->
<?php if (!empty($monthly_evolution)): ?>
<div class="chart-card animate-fade-in animate-delay-3">
    <h5 class="mb-3">
        <i class="fas fa-chart-line me-2"></i>
        Évolution mensuelle (12 derniers mois)
    </h5>
    <canvas id="monthlyChart" height="100"></canvas>
</div>
<?php endif; ?>

<!-- Statistiques par classe -->
<?php if (!empty($class_stats)): ?>
<div class="table-modern animate-fade-in animate-delay-4">
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead>
                <tr>
                    <th>Classe</th>
                    <th>Total</th>
                    <th>Entrants</th>
                    <th>Sortants</th>
                    <th>Sorties</th>
                    <th>Taux de rotation</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($class_stats as $stat): ?>
                    <tr>
                        <td>
                            <strong><?php echo htmlspecialchars($stat['niveau'] . ' - ' . $stat['classe_nom']); ?></strong>
                        </td>
                        <td>
                            <span class="badge bg-primary rounded-pill"><?php echo $stat['total_transfers']; ?></span>
                        </td>
                        <td>
                            <span class="badge bg-success rounded-pill"><?php echo $stat['entrants']; ?></span>
                        </td>
                        <td>
                            <span class="badge bg-warning rounded-pill"><?php echo $stat['sortants']; ?></span>
                        </td>
                        <td>
                            <span class="badge bg-danger rounded-pill"><?php echo $stat['sorties']; ?></span>
                        </td>
                        <td>
                            <?php 
                            $rotation_rate = $stat['total_transfers'] > 0 ? 
                                round((($stat['sortants'] + $stat['sorties']) / $stat['total_transfers']) * 100, 1) : 0;
                            ?>
                            <div class="progress" style="height: 20px;">
                                <div class="progress-bar" role="progressbar" 
                                     style="width: <?php echo min($rotation_rate, 100); ?>%"
                                     aria-valuenow="<?php echo $rotation_rate; ?>" 
                                     aria-valuemin="0" aria-valuemax="100">
                                    <?php echo $rotation_rate; ?>%
                                </div>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<!-- Derniers transferts -->
<?php if (!empty($recent_transfers)): ?>
<div class="table-modern animate-fade-in animate-delay-4">
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead>
                <tr>
                    <th>Élève</th>
                    <th>Type</th>
                    <th>École origine</th>
                    <th>École destination</th>
                    <th>Date demande</th>
                    <th>Statut</th>
                    <th>Frais</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($recent_transfers as $transfer): ?>
                    <tr>
                        <td>
                            <strong><?php echo htmlspecialchars($transfer['eleve_nom'] . ' ' . $transfer['eleve_prenom']); ?></strong>
                        </td>
                        <td>
                            <?php 
                            $type_labels = [
                                'transfert_entrant' => '<span class="badge bg-success">Entrant</span>',
                                'transfert_sortant' => '<span class="badge bg-warning">Sortant</span>',
                                'sortie_definitive' => '<span class="badge bg-danger">Sortie</span>'
                            ];
                            echo $type_labels[$transfer['type_mouvement']] ?? $transfer['type_mouvement'];
                            ?>
                        </td>
                        <td><?php echo htmlspecialchars($transfer['ecole_origine'] ?? '-'); ?></td>
                        <td><?php echo htmlspecialchars($transfer['ecole_destination'] ?? '-'); ?></td>
                        <td><?php echo date('d/m/Y', strtotime($transfer['date_demande'])); ?></td>
                        <td>
                            <?php 
                            $status_labels = [
                                'en_attente' => '<span class="badge bg-warning">En attente</span>',
                                'approuve' => '<span class="badge bg-primary">Approuvé</span>',
                                'rejete' => '<span class="badge bg-danger">Rejeté</span>',
                                'complete' => '<span class="badge bg-success">Complété</span>'
                            ];
                            echo $status_labels[$transfer['statut']] ?? $transfer['statut'];
                            ?>
                        </td>
                        <td>
                            <strong><?php echo number_format($transfer['frais_transfert']); ?> FC</strong><br>
                            <small class="text-muted">Payé: <?php echo number_format($transfer['frais_payes']); ?> FC</small>
                        </td>
                        <td>
                            <button type="button" class="btn btn-sm btn-outline-primary" 
                                    onclick="viewTransferDetails(<?php echo $transfer['id']; ?>)" 
                                    data-bs-toggle="tooltip" title="Voir les détails">
                                <i class="fas fa-eye"></i>
                            </button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<!-- Top des écoles -->
<?php if (!empty($top_schools)): ?>
<div class="chart-card animate-fade-in animate-delay-4">
    <h5 class="mb-3">
        <i class="fas fa-school me-2"></i>
        Écoles les plus fréquentes
    </h5>
    <div class="table-responsive">
        <table class="table table-hover">
            <thead>
                <tr>
                    <th>École</th>
                    <th>Type de mouvement</th>
                    <th>Nombre de transferts</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($top_schools as $school): ?>
                    <tr>
                        <td>
                            <strong><?php echo htmlspecialchars($school['ecole']); ?></strong>
                        </td>
                        <td>
                            <?php 
                            $type_labels = [
                                'transfert_entrant' => '<span class="badge bg-success">Entrant</span>',
                                'transfert_sortant' => '<span class="badge bg-warning">Sortant</span>',
                                'sortie_definitive' => '<span class="badge bg-danger">Sortie</span>'
                            ];
                            echo $type_labels[$school['type_mouvement']] ?? $school['type_mouvement'];
                            ?>
                        </td>
                        <td>
                            <span class="badge bg-primary rounded-pill"><?php echo $school['nombre_transfers']; ?></span>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

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

// Graphique d'évolution mensuelle
<?php if (!empty($monthly_evolution)): ?>
const monthlyData = <?php echo json_encode(array_reverse($monthly_evolution)); ?>;
const ctx = document.getElementById('monthlyChart').getContext('2d');

new Chart(ctx, {
    type: 'line',
    data: {
        labels: monthlyData.map(item => {
            const date = new Date(item.mois + '-01');
            return date.toLocaleDateString('fr-FR', { year: 'numeric', month: 'short' });
        }),
        datasets: [
            {
                label: 'Entrants',
                data: monthlyData.map(item => item.entrants),
                borderColor: '#28a745',
                backgroundColor: 'rgba(40, 167, 69, 0.1)',
                tension: 0.4
            },
            {
                label: 'Sortants',
                data: monthlyData.map(item => item.sortants),
                borderColor: '#ffc107',
                backgroundColor: 'rgba(255, 193, 7, 0.1)',
                tension: 0.4
            },
            {
                label: 'Sorties',
                data: monthlyData.map(item => item.sorties),
                borderColor: '#dc3545',
                backgroundColor: 'rgba(220, 53, 69, 0.1)',
                tension: 0.4
            }
        ]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: 'top',
            },
            title: {
                display: false
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    stepSize: 1
                }
            }
        }
    }
});
<?php endif; ?>

// Fonction d'export
function exportReport() {
    const params = new URLSearchParams(window.location.search);
    params.set('export', 'excel');
    window.location.href = '../exports/movements.php?' + params.toString();
}

// Fonction pour afficher les détails d'un transfert
function viewTransferDetails(transferId) {
    window.open('../view-transfer.php?id=' + transferId, '_blank');
}

// Fonction pour rafraîchir les données
function refreshData() {
    window.location.reload();
}

// Initialisation des tooltips Bootstrap
document.addEventListener('DOMContentLoaded', function() {
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
});
</script>

<?php include '../../../../includes/footer.php'; ?>
