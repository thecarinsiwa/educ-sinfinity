<?php
/**
 * Module Bibliothèque - Page des Rapports
 * Application de gestion scolaire - République Démocratique du Congo
 */

require_once '../../../config/config.php';
require_once '../../../config/database.php';
require_once '../../../includes/functions.php';
require_once '../../../includes/permissions-pages.php';
require_once '../../../includes/ui-permissions.php';

// Vérifier l'authentification et les permissions
requireLogin();
requirePagePermissionFromDB('reports', 'library/reports/index', 'read', '../../../dashboard.php');

// Paramètres de filtrage
$date_debut = $_GET['date_debut'] ?? date('Y-m-01'); // Premier jour du mois
$date_fin = $_GET['date_fin'] ?? date('Y-m-d'); // Aujourd'hui
$type_rapport = $_GET['type'] ?? 'general';

// Statistiques générales
try {
    $stats = [];
    
    // Statistiques de base
    $stats['total_livres'] = $database->query("SELECT COUNT(*) as total FROM livres WHERE status != 'retire'")->fetch()['total'];
    $stats['livres_disponibles'] = $database->query("SELECT COUNT(*) as total FROM livres WHERE status = 'disponible'")->fetch()['total'];
    $stats['livres_empruntes'] = $database->query("SELECT COUNT(*) as total FROM livres WHERE status = 'emprunte'")->fetch()['total'];
    $stats['livres_reserves'] = $database->query("SELECT COUNT(*) as total FROM livres WHERE status = 'reserve'")->fetch()['total'];
    
    // Emprunts
    $stats['emprunts_total'] = $database->query("SELECT COUNT(*) as total FROM emprunts_livres")->fetch()['total'];
    $stats['emprunts_actifs'] = $database->query("SELECT COUNT(*) as total FROM emprunts_livres WHERE status = 'en_cours'")->fetch()['total'];
    $stats['emprunts_retard'] = $database->query(
        "SELECT COUNT(*) as total FROM emprunts_livres WHERE status = 'en_cours' AND date_retour_prevue < CURDATE()"
    )->fetch()['total'];
    $stats['emprunts_period'] = $database->query(
        "SELECT COUNT(*) as total FROM emprunts_livres WHERE date_emprunt BETWEEN ? AND ?",
        [$date_debut, $date_fin]
    )->fetch()['total'];
    
    // Retours
    $stats['retours_period'] = $database->query(
        "SELECT COUNT(*) as total FROM emprunts_livres WHERE date_retour BETWEEN ? AND ? AND status = 'rendu'",
        [$date_debut, $date_fin]
    )->fetch()['total'];
    
    // Réservations
    $stats['reservations_actives'] = $database->query("SELECT COUNT(*) as total FROM reservations_livres WHERE status = 'active'")->fetch()['total'];
    $stats['reservations_period'] = $database->query(
        "SELECT COUNT(*) as total FROM reservations_livres WHERE date_reservation BETWEEN ? AND ?",
        [$date_debut, $date_fin]
    )->fetch()['total'];
    
    // Pénalités
    $stats['penalites_total'] = $database->query("SELECT COUNT(*) as total FROM penalites_bibliotheque")->fetch()['total'];
    $stats['penalites_impayees'] = $database->query("SELECT COUNT(*) as total FROM penalites_bibliotheque WHERE status = 'impayee'")->fetch()['total'];
    $stats['penalites_period'] = $database->query(
        "SELECT COUNT(*) as total FROM penalites_bibliotheque WHERE date_creation BETWEEN ? AND ?",
        [$date_debut, $date_fin]
    )->fetch()['total'];
    
} catch (Exception $e) {
    $stats = [
        'total_livres' => 0, 'livres_disponibles' => 0, 'livres_empruntes' => 0, 'livres_reserves' => 0,
        'emprunts_total' => 0, 'emprunts_actifs' => 0, 'emprunts_retard' => 0, 'emprunts_period' => 0,
        'retours_period' => 0, 'reservations_actives' => 0, 'reservations_period' => 0,
        'penalites_total' => 0, 'penalites_impayees' => 0, 'penalites_period' => 0
    ];
}

// Top livres empruntés pour la période
try {
    $top_livres = $database->query(
        "SELECT l.*, COUNT(el.id) as nb_emprunts
         FROM livres l
         LEFT JOIN emprunts_livres el ON l.id = el.livre_id 
         AND el.date_emprunt BETWEEN ? AND ?
         WHERE l.status != 'retire'
         GROUP BY l.id
         ORDER BY nb_emprunts DESC, l.titre
         LIMIT 10",
        [$date_debut, $date_fin]
    )->fetchAll();
} catch (Exception $e) {
    $top_livres = [];
}

// Top emprunteurs pour la période
try {
    $top_emprunteurs = $database->query(
        "SELECT 
            el.emprunteur_type,
            el.emprunteur_id,
            CASE
                WHEN el.emprunteur_type = 'eleve' THEN 'Élève'
                WHEN el.emprunteur_type = 'personnel' THEN 'Personnel'
                ELSE 'Inconnu'
            END as type_label,
            COUNT(el.id) as nb_emprunts
         FROM emprunts_livres el
         WHERE el.date_emprunt BETWEEN ? AND ?
         GROUP BY el.emprunteur_type, el.emprunteur_id
         ORDER BY nb_emprunts DESC
         LIMIT 10",
        [$date_debut, $date_fin]
    )->fetchAll();
} catch (Exception $e) {
    $top_emprunteurs = [];
}

// Évolution des emprunts par jour (derniers 30 jours)
try {
    $evolution_emprunts = $database->query(
        "SELECT DATE(date_emprunt) as date, COUNT(*) as nb_emprunts
         FROM emprunts_livres 
         WHERE date_emprunt >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
         GROUP BY DATE(date_emprunt)
         ORDER BY date"
    )->fetchAll();
} catch (Exception $e) {
    $evolution_emprunts = [];
}

// Répartition par catégorie
try {
    $categories_stats = $database->query(
        "SELECT cl.nom as categorie, cl.couleur, 
                COUNT(l.id) as total_livres,
                COUNT(CASE WHEN l.status = 'disponible' THEN 1 END) as disponibles,
                COUNT(CASE WHEN l.status = 'emprunte' THEN 1 END) as empruntes
         FROM categories_livres cl
         LEFT JOIN livres l ON cl.id = l.categorie_id AND l.status != 'retire'
         GROUP BY cl.id, cl.nom, cl.couleur
         ORDER BY total_livres DESC"
    )->fetchAll();
} catch (Exception $e) {
    $categories_stats = [];
}

// Emprunts en retard détaillés
try {
    $emprunts_retard_detail = $database->query(
        "SELECT el.*, l.titre, l.auteur, l.isbn,
                DATEDIFF(CURDATE(), el.date_retour_prevue) as jours_retard
         FROM emprunts_livres el
         JOIN livres l ON el.livre_id = l.id
         WHERE el.status = 'en_cours' AND el.date_retour_prevue < CURDATE()
         ORDER BY el.date_retour_prevue ASC
         LIMIT 20"
    )->fetchAll();
} catch (Exception $e) {
    $emprunts_retard_detail = [];
}

$page_title = 'Rapports - Bibliothèque';
include '../../../includes/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">
        <i class="fas fa-chart-bar me-2"></i>
        Rapports - Bibliothèque
    </h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <div class="btn-group me-2">
            <a href="../" class="btn btn-outline-secondary">
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
                <?php if (hasPagePermissionFromDB('library', 'reports/export', 'read')): ?>
                <li><a class="dropdown-item" href="#" onclick="exportReport('pdf')">
                    <i class="fas fa-file-pdf me-2"></i>PDF
                </a></li>
                <li><a class="dropdown-item" href="#" onclick="exportReport('excel')">
                    <i class="fas fa-file-excel me-2"></i>Excel
                </a></li>
                <li><a class="dropdown-item" href="#" onclick="exportReport('csv')">
                    <i class="fas fa-file-csv me-2"></i>CSV
                </a></li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</div>

<!-- Filtres -->
<div class="card mb-4">
    <div class="card-header">
        <h5 class="mb-0">
            <i class="fas fa-filter me-2"></i>
            Filtres de rapport
        </h5>
    </div>
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-md-3">
                <label for="date_debut" class="form-label">Date de début</label>
                <input type="date" class="form-control" id="date_debut" name="date_debut" value="<?php echo $date_debut; ?>">
            </div>
            <div class="col-md-3">
                <label for="date_fin" class="form-label">Date de fin</label>
                <input type="date" class="form-control" id="date_fin" name="date_fin" value="<?php echo $date_fin; ?>">
            </div>
            <div class="col-md-3">
                <label for="type" class="form-label">Type de rapport</label>
                <select class="form-select" id="type" name="type">
                    <option value="general" <?php echo $type_rapport === 'general' ? 'selected' : ''; ?>>Général</option>
                    <option value="emprunts" <?php echo $type_rapport === 'emprunts' ? 'selected' : ''; ?>>Emprunts</option>
                    <option value="retours" <?php echo $type_rapport === 'retours' ? 'selected' : ''; ?>>Retours</option>
                    <option value="penalites" <?php echo $type_rapport === 'penalites' ? 'selected' : ''; ?>>Pénalités</option>
                </select>
            </div>
            <div class="col-md-3 d-flex align-items-end">
                <button type="submit" class="btn btn-primary me-2">
                    <i class="fas fa-search me-1"></i>
                    Filtrer
                </button>
                <a href="?" class="btn btn-outline-secondary">
                    <i class="fas fa-undo me-1"></i>
                    Reset
                </a>
            </div>
        </form>
    </div>
</div>

<!-- Statistiques générales -->
<div class="row mb-4">
    <div class="col-lg-3 col-md-6 mb-3">
        <div class="card text-white bg-primary">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h4><?php echo number_format($stats['total_livres']); ?></h4>
                        <p class="mb-0 small">Total livres</p>
                    </div>
                    <div class="align-self-center">
                        <i class="fas fa-book fa-2x"></i>
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
                        <h4><?php echo number_format($stats['emprunts_period']); ?></h4>
                        <p class="mb-0 small">Emprunts (période)</p>
                    </div>
                    <div class="align-self-center">
                        <i class="fas fa-hand-holding fa-2x"></i>
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
                        <h4><?php echo number_format($stats['retours_period']); ?></h4>
                        <p class="mb-0 small">Retours (période)</p>
                    </div>
                    <div class="align-self-center">
                        <i class="fas fa-undo fa-2x"></i>
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
                        <h4><?php echo number_format($stats['emprunts_retard']); ?></h4>
                        <p class="mb-0 small">En retard</p>
                    </div>
                    <div class="align-self-center">
                        <i class="fas fa-exclamation-triangle fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Contenu principal -->
<div class="row">
    <div class="col-lg-8">
        <!-- Top livres empruntés -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-star me-2"></i>
                    Top livres empruntés (<?php echo formatDate($date_debut); ?> - <?php echo formatDate($date_fin); ?>)
                </h5>
            </div>
            <div class="card-body">
                <?php if (!empty($top_livres)): ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Titre</th>
                                    <th>Auteur</th>
                                    <th>ISBN</th>
                                    <th>Statut</th>
                                    <th>Emprunts</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($top_livres as $index => $livre): ?>
                                    <tr>
                                        <td>
                                            <span class="badge bg-<?php echo $index < 3 ? 'warning' : 'secondary'; ?>">
                                                <?php echo $index + 1; ?>
                                            </span>
                                        </td>
                                        <td><strong><?php echo htmlspecialchars($livre['titre']); ?></strong></td>
                                        <td><?php echo htmlspecialchars($livre['auteur']); ?></td>
                                        <td><code><?php echo htmlspecialchars($livre['isbn']); ?></code></td>
                                        <td>
                                            <span class="badge bg-<?php echo $livre['status'] === 'disponible' ? 'success' : 'warning'; ?>">
                                                <?php echo ucfirst($livre['status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge bg-primary"><?php echo $livre['nb_emprunts']; ?></span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p class="text-muted text-center">Aucun emprunt pour cette période</p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Top emprunteurs -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-users me-2"></i>
                    Top emprunteurs (<?php echo formatDate($date_debut); ?> - <?php echo formatDate($date_fin); ?>)
                </h5>
            </div>
            <div class="card-body">
                <?php if (!empty($top_emprunteurs)): ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Emprunteur</th>
                                    <th>Type</th>
                                    <th>Emprunts</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($top_emprunteurs as $index => $emprunteur): ?>
                                    <tr>
                                        <td>
                                            <span class="badge bg-<?php echo $index < 3 ? 'warning' : 'secondary'; ?>">
                                                <?php echo $index + 1; ?>
                                            </span>
                                        </td>
                                        <td><strong>Emprunteur #<?php echo $emprunteur['emprunteur_id']; ?></strong></td>
                                        <td>
                                            <span class="badge bg-<?php echo $emprunteur['emprunteur_type'] === 'eleve' ? 'primary' : 'secondary'; ?>">
                                                <?php echo htmlspecialchars($emprunteur['type_label']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge bg-success"><?php echo $emprunteur['nb_emprunts']; ?></span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p class="text-muted text-center">Aucun emprunteur pour cette période</p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Emprunts en retard -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-exclamation-triangle me-2 text-warning"></i>
                    Emprunts en retard
                </h5>
            </div>
            <div class="card-body">
                <?php if (!empty($emprunts_retard_detail)): ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Livre</th>
                                    <th>Auteur</th>
                                    <th>ISBN</th>
                                    <th>Date emprunt</th>
                                    <th>Retour prévu</th>
                                    <th>Jours de retard</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($emprunts_retard_detail as $emprunt): ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($emprunt['titre']); ?></strong></td>
                                        <td><?php echo htmlspecialchars($emprunt['auteur']); ?></td>
                                        <td><code><?php echo htmlspecialchars($emprunt['isbn']); ?></code></td>
                                        <td><?php echo formatDate($emprunt['date_emprunt']); ?></td>
                                        <td><?php echo formatDate($emprunt['date_retour_prevue']); ?></td>
                                        <td>
                                            <span class="badge bg-<?php echo $emprunt['jours_retard'] > 7 ? 'danger' : 'warning'; ?>">
                                                <?php echo $emprunt['jours_retard']; ?> jour<?php echo $emprunt['jours_retard'] > 1 ? 's' : ''; ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p class="text-success text-center">
                        <i class="fas fa-check-circle me-2"></i>
                        Aucun emprunt en retard
                    </p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <!-- Statistiques par catégorie -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-tags me-2"></i>
                    Répartition par catégorie
                </h5>
            </div>
            <div class="card-body">
                <?php if (!empty($categories_stats)): ?>
                    <?php foreach ($categories_stats as $categorie): ?>
                        <div class="mb-3">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <div class="d-flex align-items-center">
                                    <span class="badge me-2" style="background-color: <?php echo $categorie['couleur']; ?>">
                                        &nbsp;
                                    </span>
                                    <span><?php echo htmlspecialchars($categorie['categorie']); ?></span>
                                </div>
                                <span class="badge bg-primary"><?php echo $categorie['total_livres']; ?></span>
                            </div>
                            <div class="progress mb-1" style="height: 6px;">
                                <div class="progress-bar" role="progressbar" 
                                     style="width: <?php echo $stats['total_livres'] > 0 ? ($categorie['total_livres'] / $stats['total_livres']) * 100 : 0; ?>%">
                                </div>
                            </div>
                            <small class="text-muted">
                                <?php echo $categorie['disponibles']; ?> disponibles, 
                                <?php echo $categorie['empruntes']; ?> empruntés
                            </small>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p class="text-muted text-center">Aucune catégorie définie</p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Statistiques détaillées -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-chart-pie me-2"></i>
                    Statistiques détaillées
                </h5>
            </div>
            <div class="card-body">
                <div class="row text-center">
                    <div class="col-6 mb-3">
                        <h6 class="text-success"><?php echo number_format($stats['livres_disponibles']); ?></h6>
                        <small class="text-muted">Disponibles</small>
                    </div>
                    <div class="col-6 mb-3">
                        <h6 class="text-warning"><?php echo number_format($stats['livres_empruntes']); ?></h6>
                        <small class="text-muted">Empruntés</small>
                    </div>
                    <div class="col-6 mb-3">
                        <h6 class="text-info"><?php echo number_format($stats['livres_reserves']); ?></h6>
                        <small class="text-muted">Réservés</small>
                    </div>
                    <div class="col-6 mb-3">
                        <h6 class="text-primary"><?php echo number_format($stats['reservations_actives']); ?></h6>
                        <small class="text-muted">Réservations</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Actions rapides -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-bolt me-2"></i>
                    Actions rapides
                </h5>
            </div>
            <div class="card-body">
                <div class="d-grid gap-2">
                    <a href="../loans/" class="btn btn-outline-primary">
                        <i class="fas fa-exchange-alt me-2"></i>
                        Gestion des emprunts
                    </a>
                    <a href="../books/" class="btn btn-outline-success">
                        <i class="fas fa-book me-2"></i>
                        Catalogue des livres
                    </a>
                    <a href="inventory.php" class="btn btn-outline-info">
                        <i class="fas fa-list me-2"></i>
                        Inventaire détaillé
                    </a>
                    <a href="../" class="btn btn-outline-secondary">
                        <i class="fas fa-home me-2"></i>
                        Accueil bibliothèque
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Graphique d'évolution (si des données existent) -->
<?php if (!empty($evolution_emprunts)): ?>
<div class="row mt-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-chart-line me-2"></i>
                    Évolution des emprunts (30 derniers jours)
                </h5>
            </div>
            <div class="card-body">
                <canvas id="evolutionChart" height="100"></canvas>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Graphique d'évolution des emprunts
<?php if (!empty($evolution_emprunts)): ?>
const ctx = document.getElementById('evolutionChart').getContext('2d');
const evolutionData = <?php echo json_encode($evolution_emprunts); ?>;

new Chart(ctx, {
    type: 'line',
    data: {
        labels: evolutionData.map(item => item.date),
        datasets: [{
            label: 'Emprunts',
            data: evolutionData.map(item => item.nb_emprunts),
            borderColor: 'rgb(75, 192, 192)',
            backgroundColor: 'rgba(75, 192, 192, 0.1)',
            tension: 0.1
        }]
    },
    options: {
        responsive: true,
        plugins: {
            title: {
                display: true,
                text: 'Évolution des emprunts'
            }
        },
        scales: {
            y: {
                beginAtZero: true
            }
        }
    }
});
<?php endif; ?>

// Fonction d'export
function exportReport(format) {
    const params = new URLSearchParams(window.location.search);
    params.set('export', format);
    window.open('export.php?' + params.toString(), '_blank');
}

// Validation des dates
document.getElementById('date_debut').addEventListener('change', function() {
    const dateDebut = new Date(this.value);
    const dateFin = new Date(document.getElementById('date_fin').value);
    
    if (dateDebut > dateFin) {
        document.getElementById('date_fin').value = this.value;
    }
});

document.getElementById('date_fin').addEventListener('change', function() {
    const dateDebut = new Date(document.getElementById('date_debut').value);
    const dateFin = new Date(this.value);
    
    if (dateFin < dateDebut) {
        document.getElementById('date_debut').value = this.value;
    }
});
</script>

<style>
.card {
    border: none;
    border-radius: 10px;
    box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
}

.card-header {
    border-radius: 10px 10px 0 0 !important;
    border: none;
    background-color: #f8f9fa;
}

.badge {
    font-size: 0.75em;
    padding: 0.5em 0.75em;
}

.progress {
    border-radius: 10px;
}

.table th {
    border-top: none;
    font-weight: 600;
    color: #495057;
}

.hover-card {
    transition: all 0.3s ease-in-out;
}

.hover-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15) !important;
}
</style>

<?php include '../../../includes/footer.php'; ?>
