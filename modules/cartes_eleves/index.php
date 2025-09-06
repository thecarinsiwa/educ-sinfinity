<?php
/**
 * Module Cartes d'Élèves - Page principale
 * Application de gestion scolaire - République Démocratique du Congo
 */

require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/config/database.php';
require_once dirname(__DIR__, 2) . '/includes/functions.php';

// Vérifier l'authentification et les permissions
requireLogin();
if (!hasPermission('cartes_eleves', 'view')) {
    showMessage('error', 'Accès refusé à ce module.');
    redirectTo('../dashboard.php');
}

$page_title = 'Cartes d\'Élèves';

// Obtenir l'année scolaire actuelle
$current_year = getCurrentAcademicYear();

// Paramètres de filtrage
$classe_filter = (int)($_GET['classe'] ?? 0);
$statut_filter = sanitizeInput($_GET['statut'] ?? '');
$search = sanitizeInput($_GET['search'] ?? '');

// Statistiques des cartes
$stats = [];

// Total des cartes
$stmt = $database->query("SELECT COUNT(*) as total FROM carte_eleve WHERE annee_scolaire_id = ?", [$current_year['id']]);
$stats['total_cartes'] = $stmt->fetch()['total'];

// Cartes actives
$stmt = $database->query("SELECT COUNT(*) as total FROM carte_eleve WHERE annee_scolaire_id = ? AND statut = 'active'", [$current_year['id']]);
$stats['cartes_actives'] = $stmt->fetch()['total'];

// Cartes expirées
$stmt = $database->query("SELECT COUNT(*) as total FROM carte_eleve WHERE annee_scolaire_id = ? AND statut = 'expiree'", [$current_year['id']]);
$stats['cartes_expirees'] = $stmt->fetch()['total'];

// Cartes suspendues
$stmt = $database->query("SELECT COUNT(*) as total FROM carte_eleve WHERE annee_scolaire_id = ? AND statut = 'suspendue'", [$current_year['id']]);
$stats['cartes_suspendues'] = $stmt->fetch()['total'];

// Cartes générées cette semaine
$stmt = $database->query("SELECT COUNT(*) as total FROM carte_eleve 
                         WHERE annee_scolaire_id = ? AND WEEK(date_generation) = WEEK(CURDATE()) 
                         AND YEAR(date_generation) = YEAR(CURDATE())", [$current_year['id']]);
$stats['cartes_semaine'] = $stmt->fetch()['total'];

// Cartes expirant dans les 30 prochains jours
$stmt = $database->query("SELECT COUNT(*) as total FROM carte_eleve 
                         WHERE annee_scolaire_id = ? AND statut = 'active' 
                         AND date_expiration <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)", [$current_year['id']]);
$stats['cartes_expirant'] = $stmt->fetch()['total'];

// Récupérer les cartes avec filtres
$where_conditions = ["ce.annee_scolaire_id = ?"];
$params = [$current_year['id']];

if ($classe_filter) {
    $where_conditions[] = "c.id = ?";
    $params[] = $classe_filter;
}

if ($statut_filter) {
    $where_conditions[] = "ce.statut = ?";
    $params[] = $statut_filter;
}

if ($search) {
    $where_conditions[] = "(e.nom LIKE ? OR e.prenom LIKE ? OR e.numero_matricule LIKE ? OR ce.numero_carte LIKE ?)";
    $search_param = "%$search%";
    $params = array_merge($params, [$search_param, $search_param, $search_param, $search_param]);
}

$cartes = $database->query(
    "SELECT ce.*, e.nom, e.prenom, e.numero_matricule, e.photo,
            c.nom as classe_nom, c.niveau,
            a.annee as annee_scolaire
     FROM carte_eleve ce
     JOIN eleves e ON ce.eleve_id = e.id
     JOIN inscriptions i ON e.id = i.eleve_id
     JOIN classes c ON i.classe_id = c.id
     JOIN annees_scolaires a ON ce.annee_scolaire_id = a.id
     WHERE " . implode(" AND ", $where_conditions) . "
     ORDER BY ce.created_at DESC",
    $params
)->fetchAll();

// Statistiques par classe
$stats_par_classe = $database->query(
    "SELECT c.nom as classe_nom, c.niveau,
            COUNT(DISTINCT i.eleve_id) as nb_eleves,
            COUNT(ce.id) as nb_cartes,
            COUNT(CASE WHEN ce.statut = 'active' THEN 1 END) as cartes_actives,
            COUNT(CASE WHEN ce.statut = 'expiree' THEN 1 END) as cartes_expirees
     FROM classes c
     JOIN inscriptions i ON c.id = i.classe_id
     LEFT JOIN carte_eleve ce ON i.eleve_id = ce.eleve_id AND ce.annee_scolaire_id = ?
     WHERE c.annee_scolaire_id = ? AND i.status = 'inscrit'
     GROUP BY c.id, c.nom, c.niveau
     ORDER BY c.niveau, c.nom",
    [$current_year['id'], $current_year['id']]
)->fetchAll();

// Évolution des cartes (7 derniers jours)
$evolution_cartes = $database->query(
    "SELECT DATE(date_generation) as date_gen,
            COUNT(*) as cartes_generees
     FROM carte_eleve 
     WHERE date_generation >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
     AND annee_scolaire_id = ?
     GROUP BY DATE(date_generation)
     ORDER BY date_gen",
    [$current_year['id']]
)->fetchAll();

// Récupérer les classes pour le filtre
$classes = $database->query("SELECT DISTINCT c.id, c.nom, c.niveau FROM classes c
                            JOIN eleves e ON c.id = e.classe_id
                            JOIN inscriptions i ON e.id = i.eleve_id
                            WHERE i.status = 'inscrit' AND i.annee_scolaire_id = ? ORDER BY c.niveau, c.nom",
                            [$current_year['id']])->fetchAll();

include dirname(__DIR__, 2) . '/includes/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">
        <i class="fas fa-id-card me-2"></i>
        Cartes d'Élèves
    </h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <div class="btn-group me-2">
            <a href="../index.php" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-1"></i>
                Retour
            </a>
        </div>
        <?php if (hasPermission('cartes_eleves', 'create')): ?>
            <div class="btn-group me-2">
                <a href="generate_card.php" class="btn btn-primary">
                    <i class="fas fa-id-card me-1"></i>
                    Générer Carte
                </a>
            </div>
            <div class="btn-group me-2">
                <button type="button" class="btn btn-outline-primary dropdown-toggle" data-bs-toggle="dropdown">
                    <i class="fas fa-plus me-1"></i>
                    Nouveau
                </button>
                <ul class="dropdown-menu">
                    <li><a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#generateCardsModal">
                        <i class="fas fa-id-card me-2"></i>Générer des cartes
                    </a></li>
                    <li><a class="dropdown-item" href="regenerate-all.php">
                        <i class="fas fa-sync me-2"></i>Régénérer toutes les cartes
                    </a></li>
                </ul>
            </div>
        <?php endif; ?>
        <div class="btn-group">
            <button type="button" class="btn btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">
                <i class="fas fa-tools me-1"></i>
                Outils
            </button>
            <ul class="dropdown-menu">
                <li><a class="dropdown-item" href="print-all.php">
                    <i class="fas fa-print me-2"></i>Imprimer toutes les cartes
                </a></li>
                <li><a class="dropdown-item" href="qr-scanner.php">
                    <i class="fas fa-qrcode me-2"></i>Scanner QR Code
                </a></li>
                <li><a class="dropdown-item" href="settings.php">
                    <i class="fas fa-cog me-2"></i>Paramètres
                </a></li>
            </ul>
        </div>
    </div>
</div>

<!-- Statistiques rapides -->
<div class="row mb-4">
    <div class="col-lg-3 col-md-6 mb-3">
        <div class="card text-white bg-primary">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h4><?php echo $stats['total_cartes']; ?></h4>
                        <p class="mb-0">Total des cartes</p>
                    </div>
                    <div class="align-self-center">
                        <i class="fas fa-id-card fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-3 col-md-6 mb-3">
        <div class="card text-white bg-success">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h4><?php echo $stats['cartes_actives']; ?></h4>
                        <p class="mb-0">Cartes actives</p>
                    </div>
                    <div class="align-self-center">
                        <i class="fas fa-check-circle fa-2x"></i>
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
                        <h4><?php echo $stats['cartes_expirees']; ?></h4>
                        <p class="mb-0">Cartes expirées</p>
                    </div>
                    <div class="align-self-center">
                        <i class="fas fa-exclamation-triangle fa-2x"></i>
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
                        <h4><?php echo $stats['cartes_suspendues']; ?></h4>
                        <p class="mb-0">Cartes suspendues</p>
                    </div>
                    <div class="align-self-center">
                        <i class="fas fa-ban fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Filtres -->
<div class="card mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-md-3">
                <label for="classe" class="form-label">Classe</label>
                <select class="form-select" id="classe" name="classe">
                    <option value="">Toutes les classes</option>
                    <?php foreach ($classes as $classe): ?>
                        <option value="<?php echo $classe['id']; ?>" 
                                <?php echo $classe_filter == $classe['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($classe['nom']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label for="statut" class="form-label">Statut</label>
                <select class="form-select" id="statut" name="statut">
                    <option value="">Tous les statuts</option>
                    <option value="active" <?php echo $statut_filter === 'active' ? 'selected' : ''; ?>>Actives</option>
                    <option value="expiree" <?php echo $statut_filter === 'expiree' ? 'selected' : ''; ?>>Expirées</option>
                    <option value="suspendue" <?php echo $statut_filter === 'suspendue' ? 'selected' : ''; ?>>Suspendues</option>
                    <option value="archivée" <?php echo $statut_filter === 'archivée' ? 'selected' : ''; ?>>Archivées</option>
                </select>
            </div>
            <div class="col-md-4">
                <label for="search" class="form-label">Recherche</label>
                <input type="text" class="form-control" id="search" name="search" 
                       placeholder="Nom, prénom, matricule ou numéro de carte..."
                       value="<?php echo htmlspecialchars($search); ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label">&nbsp;</label>
                <div class="d-grid">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search me-1"></i>
                        Filtrer
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Contenu principal -->
<div class="row">
    <div class="col-lg-8">
        <!-- Liste des cartes -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-list me-2"></i>
                    Liste des cartes d'élèves
                    <?php if (!empty($cartes)): ?>
                        <span class="badge bg-secondary"><?php echo count($cartes); ?></span>
                    <?php endif; ?>
                </h5>
            </div>
            <div class="card-body">
                <?php if (!empty($cartes)): ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Photo</th>
                                    <th>Élève</th>
                                    <th>Classe</th>
                                    <th>N° Carte</th>
                                    <th>Statut</th>
                                    <th>Date Génération</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($cartes as $carte): ?>
                                    <tr>
                                        <td>
                                            <?php if ($carte['photo']): ?>
                                                <img src="../../uploads/photos/<?= $carte['photo'] ?>" 
                                                     class="rounded-circle avatar-sm" alt="Photo">
                                            <?php else: ?>
                                                <div class="avatar-sm bg-light rounded-circle d-flex align-items-center justify-content-center">
                                                    <i class="mdi mdi-account text-muted"></i>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($carte['nom'] . ' ' . $carte['prenom']); ?></strong>
                                            <br><small class="text-muted">
                                                <?php echo htmlspecialchars($carte['numero_matricule']); ?>
                                            </small>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?php 
                                                echo $carte['niveau'] === 'maternelle' ? 'warning' : 
                                                    ($carte['niveau'] === 'primaire' ? 'success' : 'primary'); 
                                            ?>">
                                                <?php echo htmlspecialchars($carte['classe_nom']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <code><?php echo htmlspecialchars($carte['numero_carte']); ?></code>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?php 
                                                echo $carte['statut'] === 'active' ? 'success' : 
                                                    ($carte['statut'] === 'expiree' ? 'warning' : 
                                                        ($carte['statut'] === 'suspendue' ? 'danger' : 'secondary')); 
                                            ?>">
                                                <?php echo ucfirst($carte['statut']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <small><?php echo formatDateTime($carte['date_generation']); ?></small>
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <button type="button" class="btn btn-outline-info"
                                                        onclick="viewCard(<?php echo $carte['id']; ?>)"
                                                        title="Voir">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                                <div class="btn-group" role="group">
                                                    <button type="button" class="btn btn-outline-primary"
                                                            onclick="printCard(<?php echo $carte['id']; ?>)"
                                                            title="Imprimer Standard">
                                                        <i class="fas fa-print"></i>
                                                    </button>
                                                    <button type="button" class="btn btn-outline-info"
                                                            onclick="printCardRDC(<?php echo $carte['id']; ?>)"
                                                            title="Imprimer Modèle RDC">
                                                        <i class="fas fa-flag"></i>
                                                    </button>
                                                </div>
                                                <a href="download.php?id=<?php echo $carte['id']; ?>" 
                                                   class="btn btn-outline-success" 
                                                   title="Télécharger PDF">
                                                    <i class="fas fa-download"></i>
                                                </a>
                                                <?php if (hasPermission('cartes_eleves', 'edit')): ?>
                                                    <div class="btn-group" role="group">
                                                        <button type="button" class="btn btn-outline-secondary dropdown-toggle" 
                                                                data-bs-toggle="dropdown" title="Actions">
                                                            <i class="fas fa-ellipsis-v"></i>
                                                        </button>
                                                        <ul class="dropdown-menu">
                                                            <li><a class="dropdown-item" href="#" onclick="regenerateCard(<?php echo $carte['id']; ?>)">
                                                                <i class="fas fa-sync me-2"></i>Régénérer
                                                            </a></li>
                                                            <li><a class="dropdown-item" href="#" onclick="suspendCard(<?php echo $carte['id']; ?>)">
                                                                <i class="fas fa-pause me-2"></i>Suspendre
                                                            </a></li>
                                                            <li><a class="dropdown-item" href="#" onclick="archiveCard(<?php echo $carte['id']; ?>)">
                                                                <i class="fas fa-archive me-2"></i>Archiver
                                                            </a></li>
                                                        </ul>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="text-center py-4">
                        <i class="fas fa-id-card fa-3x text-muted mb-3"></i>
                        <h5 class="text-muted">Aucune carte trouvée</h5>
                        <p class="text-muted">
                            Aucune carte d'élève ne correspond aux critères de recherche.
                        </p>
                        <?php if (hasPermission('cartes_eleves', 'create')): ?>
                            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#generateCardsModal">
                                <i class="fas fa-plus me-1"></i>
                                Générer des cartes
                            </button>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Évolution des cartes -->
        <?php if (!empty($evolution_cartes)): ?>
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-chart-line me-2"></i>
                    Évolution des cartes (7 derniers jours)
                </h5>
            </div>
            <div class="card-body">
                <canvas id="evolutionChart" width="100%" height="300"></canvas>
            </div>
        </div>
        <?php endif; ?>
    </div>
    
    <div class="col-lg-4">
        <!-- Statistiques par classe -->
        <div class="card mb-4">
            <div class="card-header">
                <h6 class="mb-0">
                    <i class="fas fa-school me-2"></i>
                    Cartes par classe
                </h6>
            </div>
            <div class="card-body">
                <?php if (!empty($stats_par_classe)): ?>
                    <?php foreach ($stats_par_classe as $stat): ?>
                        <div class="mb-3">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <span class="badge bg-<?php 
                                    echo $stat['niveau'] === 'maternelle' ? 'warning' : 
                                        ($stat['niveau'] === 'primaire' ? 'success' : 'primary'); 
                                ?>">
                                    <?php echo htmlspecialchars($stat['classe_nom']); ?>
                                </span>
                                <small class="text-muted">
                                    <?php echo $stat['nb_eleves']; ?> élèves
                                </small>
                            </div>
                            <div class="d-flex justify-content-between">
                                <small class="text-success">
                                    <?php echo $stat['cartes_actives']; ?> actives
                                </small>
                                <small class="text-warning">
                                    <?php echo $stat['cartes_expirees']; ?> expirées
                                </small>
                            </div>
                            <div class="progress mt-1" style="height: 4px;">
                                <div class="progress-bar bg-success" style="width: <?php echo $stat['nb_eleves'] > 0 ? ($stat['cartes_actives'] / $stat['nb_eleves'] * 100) : 0; ?>%"></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p class="text-muted text-center">Aucune donnée disponible</p>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Cartes expirant bientôt -->
        <?php if ($stats['cartes_expirant'] > 0): ?>
        <div class="card mb-4">
            <div class="card-header">
                <h6 class="mb-0">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    Cartes expirant bientôt
                </h6>
            </div>
            <div class="card-body">
                <div class="alert alert-warning">
                    <i class="fas fa-clock me-2"></i>
                    <strong><?php echo $stats['cartes_expirant']; ?></strong> cartes expireront dans les 30 prochains jours.
                </div>
                <div class="d-grid">
                    <a href="?statut=active" class="btn btn-outline-warning btn-sm">
                        <i class="fas fa-eye me-1"></i>
                        Voir les cartes actives
                    </a>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Actions rapides -->
        <div class="card">
            <div class="card-header">
                <h6 class="mb-0">
                    <i class="fas fa-bolt me-2"></i>
                    Actions rapides
                </h6>
            </div>
            <div class="card-body">
                <div class="d-grid gap-2">
                    <button type="button" class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#generateCardsModal">
                        <i class="fas fa-id-card me-2"></i>
                        Générer des cartes
                    </button>
                    <a href="print-all.php" class="btn btn-outline-success">
                        <i class="fas fa-print me-2"></i>
                        Imprimer toutes les cartes
                    </a>
                    <a href="qr-scanner.php" class="btn btn-outline-info">
                        <i class="fas fa-qrcode me-2"></i>
                        Scanner QR Code
                    </a>
                    <a href="settings.php" class="btn btn-outline-secondary">
                        <i class="fas fa-cog me-2"></i>
                        Paramètres
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Actions rapides -->
<?php if (hasPermission('cartes_eleves', 'create')): ?>
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
                            <button type="button" class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#generateCardsModal">
                                <i class="fas fa-id-card me-2"></i>
                                Générer des cartes
                            </button>
                        </div>
                    </div>
                    <div class="col-md-3 mb-2">
                        <div class="d-grid">
                            <a href="print-all.php" class="btn btn-outline-success">
                                <i class="fas fa-print me-2"></i>
                                Imprimer toutes
                            </a>
                        </div>
                    </div>
                    <div class="col-md-3 mb-2">
                        <div class="d-grid">
                            <a href="qr-scanner.php" class="btn btn-outline-info">
                                <i class="fas fa-qrcode me-2"></i>
                                Scanner QR
                            </a>
                        </div>
                    </div>
                    <div class="col-md-3 mb-2">
                        <div class="d-grid">
                            <a href="regenerate-all.php" class="btn btn-outline-warning">
                                <i class="fas fa-sync me-2"></i>
                                Régénérer toutes
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<?php if (!empty($evolution_cartes)): ?>
<script>
// Graphique d'évolution des cartes
const evolutionCtx = document.getElementById('evolutionChart').getContext('2d');
const evolutionChart = new Chart(evolutionCtx, {
    type: 'line',
    data: {
        labels: [<?php echo implode(',', array_map(function($e) { return "'" . date('d/m', strtotime($e['date_gen'])) . "'"; }, $evolution_cartes)); ?>],
        datasets: [{
            label: 'Cartes générées',
            data: [<?php echo implode(',', array_column($evolution_cartes, 'cartes_generees')); ?>],
            borderColor: '#0d6efd',
            backgroundColor: 'rgba(13, 110, 253, 0.1)',
            tension: 0.4,
            fill: true
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: 'top'
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
</script>
<?php endif; ?>

<!-- Modal Génération de cartes -->
<div class="modal fade" id="generateCardsModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-id-card me-2"></i>
                    Générer des Cartes d'Élèves
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="generateCardsForm">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="classe_generation" class="form-label">Classe</label>
                                <select class="form-select" id="classe_generation" name="classe_id" required>
                                    <option value="">Sélectionner une classe</option>
                                    <?php foreach ($classes as $classe): ?>
                                        <option value="<?php echo $classe['id']; ?>">
                                            <?php echo htmlspecialchars($classe['nom']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="type_generation" class="form-label">Type de génération</label>
                                <select class="form-select" id="type_generation" name="type_generation" required>
                                    <option value="all">Tous les élèves de la classe</option>
                                    <option value="selected">Élèves sélectionnés</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Élèves</label>
                        <div id="studentsList" class="border rounded p-3" style="max-height: 300px; overflow-y: auto;">
                            <p class="text-muted text-center">Sélectionnez une classe pour voir les élèves</p>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-primary">Générer les cartes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Fonctions JavaScript pour la gestion des cartes
function viewCard(carteId) {
    window.open(`view.php?id=${carteId}`, '_blank');
}

function printCard(carteId) {
    window.open(`print.php?id=${carteId}`, '_blank');
}

function printCardRDC(carteId) {
    window.open(`print-rdc.php?id=${carteId}`, '_blank');
}

function regenerateCard(carteId) {
    if (confirm('Régénérer cette carte ?')) {
        fetch('actions.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                action: 'regenerate',
                carte_id: carteId
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Carte régénérée avec succès');
                location.reload();
            } else {
                alert('Erreur: ' + data.message);
            }
        });
    }
}

function suspendCard(carteId) {
    if (confirm('Suspendre cette carte ?')) {
        fetch('actions.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                action: 'suspend',
                carte_id: carteId
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Carte suspendue avec succès');
                location.reload();
            } else {
                alert('Erreur: ' + data.message);
            }
        });
    }
}

function archiveCard(carteId) {
    if (confirm('Archiver cette carte ?')) {
        fetch('actions.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                action: 'archive',
                carte_id: carteId
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Carte archivée avec succès');
                location.reload();
            } else {
                alert('Erreur: ' + data.message);
            }
        });
    }
}

// Gestion de la génération de cartes
document.getElementById('classe_generation').addEventListener('change', function() {
    const classeId = this.value;
    const studentsList = document.getElementById('studentsList');
    
    if (classeId) {
        fetch('get-students.php?classe_id=' + classeId)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    let html = '';
                    data.students.forEach(student => {
                        html += `
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="student_ids[]" 
                                       value="${student.id}" id="student_${student.id}">
                                <label class="form-check-label" for="student_${student.id}">
                                    ${student.nom} ${student.prenom} (${student.numero_matricule})
                                </label>
                            </div>
                        `;
                    });
                    studentsList.innerHTML = html;
                } else {
                    studentsList.innerHTML = '<p class="text-danger">Erreur lors du chargement des élèves</p>';
                }
            });
    } else {
        studentsList.innerHTML = '<p class="text-muted text-center">Sélectionnez une classe pour voir les élèves</p>';
    }
});

document.getElementById('generateCardsForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    formData.append('action', 'generate');
    
    fetch('actions.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert(`Cartes générées avec succès: ${data.count} carte(s)`);
            location.reload();
        } else {
            alert('Erreur: ' + data.message);
        }
    });
});
</script>

<?php include dirname(__DIR__, 2) . '/includes/footer.php'; ?>