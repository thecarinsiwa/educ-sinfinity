<?php
/**
 * Module Internat - Page principale
 * Application de gestion scolaire - République Démocratique du Congo
 */

require_once '../../../config/config.php';
require_once '../../../config/database.php';
require_once '../../../includes/functions.php';

// Vérifier l'authentification et les permissions
requireLogin();
if (!checkPermission('internat') && !checkPermission('internat_view')) {
    showMessage('error', 'Accès refusé à ce module.');
    redirectTo('../index.php');
}

$page_title = 'Gestion de l\'Internat';

// Obtenir l'année scolaire actuelle
$current_year = getCurrentAcademicYear();

// Statistiques de l'internat
$stats = [];

// Nombre total de chambres
$stmt = $database->query("SELECT COUNT(*) as total FROM chambres");
$stats['total_chambres'] = $stmt->fetch()['total'];

// Chambres occupées
$stmt = $database->query("SELECT COUNT(*) as total FROM chambres WHERE status = 'occupee'");
$stats['chambres_occupees'] = $stmt->fetch()['total'];

// Chambres disponibles
$stats['chambres_disponibles'] = $stats['total_chambres'] - $stats['chambres_occupees'];

// Résidents actuels
$stmt = $database->query(
    "SELECT COUNT(*) as total FROM hebergements WHERE annee_scolaire_id = ? AND status = 'actif'",
    [$current_year['id'] ?? 0]
);
$stats['residents_actuels'] = $stmt->fetch()['total'];

// Capacité totale
$stmt = $database->query("SELECT SUM(capacite) as total FROM chambres WHERE status != 'hors_service'");
$stats['capacite_totale'] = $stmt->fetch()['total'] ?? 0;

// Taux d'occupation
$stats['taux_occupation'] = $stats['capacite_totale'] > 0 ? 
    round(($stats['residents_actuels'] / $stats['capacite_totale']) * 100, 1) : 0;

// Résidents récents (derniers 30 jours)
$residents_recents = $database->query(
    "SELECT h.*, e.nom, e.prenom, e.numero_matricule, c.nom as classe_nom,
            ch.numero as chambre_numero, ch.type as chambre_type
     FROM hebergements h
     JOIN eleves e ON h.eleve_id = e.id
     JOIN inscriptions i ON e.id = i.eleve_id
     JOIN classes c ON i.classe_id = c.id
     JOIN chambres ch ON h.chambre_id = ch.id
     WHERE h.date_debut >= DATE_SUB(NOW(), INTERVAL 30 DAY)
     AND h.annee_scolaire_id = ?
     ORDER BY h.date_debut DESC
     LIMIT 10",
    [$current_year['id'] ?? 0]
)->fetchAll();

// Répartition par type de chambre
$types_chambres = $database->query(
    "SELECT type, COUNT(*) as nombre, SUM(capacite) as capacite_totale
     FROM chambres
     WHERE status != 'hors_service'
     GROUP BY type
     ORDER BY nombre DESC"
)->fetchAll();

// Résidents par classe
$residents_par_classe = $database->query(
    "SELECT c.nom as classe_nom, c.niveau, COUNT(h.id) as nb_residents
     FROM classes c
     LEFT JOIN inscriptions i ON c.id = i.classe_id
     LEFT JOIN hebergements h ON i.eleve_id = h.eleve_id AND h.annee_scolaire_id = ?
     WHERE c.annee_scolaire_id = ? AND h.status = 'actif'
     GROUP BY c.id
     HAVING nb_residents > 0
     ORDER BY nb_residents DESC",
    [$current_year['id'] ?? 0, $current_year['id'] ?? 0]
)->fetchAll();

// Chambres nécessitant attention
$chambres_attention = $database->query(
    "SELECT ch.*,
            COALESCE(COUNT(h.id), 0) as nb_residents
     FROM chambres ch
     LEFT JOIN hebergements h ON ch.id = h.chambre_id AND h.status = 'actif'
     WHERE ch.status IN ('maintenance', 'nettoyage')
     GROUP BY ch.id
     ORDER BY ch.status, ch.numero
     LIMIT 8"
)->fetchAll();

// Événements récents de l'internat
$evenements_recents = $database->query(
    "SELECT * FROM evenements_internat 
     WHERE date_evenement >= DATE_SUB(NOW(), INTERVAL 7 DAY)
     ORDER BY date_evenement DESC, created_at DESC
     LIMIT 8"
)->fetchAll();

include '../../../includes/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">
        <i class="fas fa-bed me-2"></i>
        Gestion de l'Internat
    </h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <div class="btn-group me-2">
            <a href="../index.php" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-1"></i>
                Retour
            </a>
        </div>
        <?php if (checkPermission('internat')): ?>
            <div class="btn-group me-2">
                <button type="button" class="btn btn-success dropdown-toggle" data-bs-toggle="dropdown">
                    <i class="fas fa-plus me-1"></i>
                    Nouveau
                </button>
                <ul class="dropdown-menu">
                    <li><a class="dropdown-item" href="residents/add.php">
                        <i class="fas fa-user-plus me-2"></i>Nouveau résident
                    </a></li>
                    <li><a class="dropdown-item" href="rooms/add.php">
                        <i class="fas fa-door-open me-2"></i>Nouvelle chambre
                    </a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item" href="events/add.php">
                        <i class="fas fa-calendar-plus me-2"></i>Nouvel événement
                    </a></li>
                </ul>
            </div>
        <?php endif; ?>
        <div class="btn-group">
            <button type="button" class="btn btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">
                <i class="fas fa-chart-bar me-1"></i>
                Rapports
            </button>
            <ul class="dropdown-menu">
                <li><a class="dropdown-item" href="reports/occupancy.php">
                    <i class="fas fa-chart-pie me-2"></i>Taux d'occupation
                </a></li>
                <li><a class="dropdown-item" href="reports/residents.php">
                    <i class="fas fa-users me-2"></i>Liste des résidents
                </a></li>
                <li><a class="dropdown-item" href="reports/rooms.php">
                    <i class="fas fa-door-open me-2"></i>État des chambres
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
                        <h4><?php echo $stats['residents_actuels']; ?></h4>
                        <p class="mb-0">Résidents actuels</p>
                    </div>
                    <div class="align-self-center">
                        <i class="fas fa-users fa-2x"></i>
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
                        <h4><?php echo $stats['chambres_disponibles']; ?></h4>
                        <p class="mb-0">Chambres libres</p>
                    </div>
                    <div class="align-self-center">
                        <i class="fas fa-door-open fa-2x"></i>
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
                        <h4><?php echo $stats['taux_occupation']; ?>%</h4>
                        <p class="mb-0">Taux d'occupation</p>
                    </div>
                    <div class="align-self-center">
                        <i class="fas fa-chart-pie fa-2x"></i>
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
                        <h4><?php echo $stats['capacite_totale']; ?></h4>
                        <p class="mb-0">Capacité totale</p>
                    </div>
                    <div class="align-self-center">
                        <i class="fas fa-bed fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modules de l'internat -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-th-large me-2"></i>
                    Modules de l'internat
                </h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-lg-3 col-md-6 mb-3">
                        <a href="residents/" class="text-decoration-none">
                            <div class="card h-100 border-0 shadow-sm hover-card">
                                <div class="card-body text-center">
                                    <i class="fas fa-users fa-3x text-primary mb-3"></i>
                                    <h5 class="card-title">Résidents</h5>
                                    <p class="card-text text-muted">
                                        Gestion des élèves internes
                                    </p>
                                    <div class="mt-3">
                                        <span class="badge bg-primary"><?php echo $stats['residents_actuels']; ?> résidents</span>
                                    </div>
                                </div>
                            </div>
                        </a>
                    </div>
                    
                    <div class="col-lg-3 col-md-6 mb-3">
                        <a href="rooms/" class="text-decoration-none">
                            <div class="card h-100 border-0 shadow-sm hover-card">
                                <div class="card-body text-center">
                                    <i class="fas fa-door-open fa-3x text-success mb-3"></i>
                                    <h5 class="card-title">Chambres</h5>
                                    <p class="card-text text-muted">
                                        Gestion des chambres et lits
                                    </p>
                                    <div class="mt-3">
                                        <span class="badge bg-success"><?php echo $stats['total_chambres']; ?> chambres</span>
                                    </div>
                                </div>
                            </div>
                        </a>
                    </div>
                    
                    <div class="col-lg-3 col-md-6 mb-3">
                        <a href="services/" class="text-decoration-none">
                            <div class="card h-100 border-0 shadow-sm hover-card">
                                <div class="card-body text-center">
                                    <i class="fas fa-concierge-bell fa-3x text-info mb-3"></i>
                                    <h5 class="card-title">Services</h5>
                                    <p class="card-text text-muted">
                                        Services et équipements
                                    </p>
                                    <div class="mt-3">
                                        <span class="badge bg-info">Restauration & Loisirs</span>
                                    </div>
                                </div>
                            </div>
                        </a>
                    </div>
                    
                    <div class="col-lg-3 col-md-6 mb-3">
                        <a href="surveillance/" class="text-decoration-none">
                            <div class="card h-100 border-0 shadow-sm hover-card">
                                <div class="card-body text-center">
                                    <i class="fas fa-shield-alt fa-3x text-warning mb-3"></i>
                                    <h5 class="card-title">Surveillance</h5>
                                    <p class="card-text text-muted">
                                        Sécurité et surveillance
                                    </p>
                                    <div class="mt-3">
                                        <span class="badge bg-warning">24h/24</span>
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

<!-- Contenu principal -->
<div class="row">
    <div class="col-lg-8">
        <!-- Résidents récents -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-clock me-2"></i>
                    Nouveaux résidents (30 derniers jours)
                </h5>
            </div>
            <div class="card-body">
                <?php if (!empty($residents_recents)): ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Date d'arrivée</th>
                                    <th>Élève</th>
                                    <th>Classe</th>
                                    <th>Chambre</th>
                                    <th>Type</th>
                                    <th>Statut</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($residents_recents as $resident): ?>
                                    <tr>
                                        <td><?php echo formatDate($resident['date_debut']); ?></td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($resident['nom'] . ' ' . $resident['prenom']); ?></strong>
                                            <br><small class="text-muted">
                                                <?php echo htmlspecialchars($resident['numero_matricule']); ?>
                                            </small>
                                        </td>
                                        <td>
                                            <span class="badge bg-secondary">
                                                <?php echo htmlspecialchars($resident['classe_nom']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($resident['chambre_numero']); ?></strong>
                                        </td>
                                        <td>
                                            <span class="badge bg-info">
                                                <?php echo ucfirst($resident['chambre_type']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php
                                            $status_colors = [
                                                'actif' => 'success',
                                                'suspendu' => 'warning',
                                                'termine' => 'secondary'
                                            ];
                                            $color = $status_colors[$resident['status']] ?? 'secondary';
                                            ?>
                                            <span class="badge bg-<?php echo $color; ?>">
                                                <?php echo ucfirst($resident['status']); ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="text-center">
                        <a href="residents/" class="btn btn-outline-secondary">
                            <i class="fas fa-list me-1"></i>
                            Voir tous les résidents
                        </a>
                    </div>
                <?php else: ?>
                    <div class="text-center py-4">
                        <i class="fas fa-user-plus fa-3x text-muted mb-3"></i>
                        <p class="text-muted">Aucun nouveau résident ce mois</p>
                        <?php if (checkPermission('internat')): ?>
                            <a href="residents/add.php" class="btn btn-primary">
                                <i class="fas fa-plus me-1"></i>
                                Ajouter un résident
                            </a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Chambres nécessitant attention -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    Chambres nécessitant attention
                </h5>
            </div>
            <div class="card-body">
                <?php if (!empty($chambres_attention)): ?>
                    <div class="row">
                        <?php foreach ($chambres_attention as $chambre): ?>
                            <div class="col-md-6 mb-3">
                                <div class="card border-<?php 
                                    echo $chambre['status'] === 'maintenance' ? 'danger' : 
                                        ($chambre['status'] === 'nettoyage' ? 'warning' : 'info'); 
                                ?>">
                                    <div class="card-body">
                                        <h6 class="card-title">
                                            Chambre <?php echo htmlspecialchars($chambre['numero']); ?>
                                        </h6>
                                        <p class="card-text">
                                            <strong>Statut :</strong> <?php echo ucfirst($chambre['status']); ?>
                                            <br><strong>Type :</strong> <?php echo ucfirst($chambre['type']); ?>
                                            <br><strong>Capacité :</strong> <?php echo $chambre['capacite']; ?> places
                                            <?php if ($chambre['residents']): ?>
                                                <br><strong>Résidents :</strong> 
                                                <small><?php echo htmlspecialchars($chambre['residents']); ?></small>
                                            <?php endif; ?>
                                        </p>
                                        <div class="d-flex justify-content-between align-items-center">
                                            <span class="badge bg-<?php 
                                                echo $chambre['status'] === 'maintenance' ? 'danger' : 
                                                    ($chambre['status'] === 'nettoyage' ? 'warning' : 'info'); 
                                            ?>">
                                                <?php echo ucfirst($chambre['status']); ?>
                                            </span>
                                            <a href="rooms/view.php?id=<?php echo $chambre['id']; ?>" 
                                               class="btn btn-sm btn-outline-primary">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="text-center py-4">
                        <i class="fas fa-check-circle fa-3x text-success mb-3"></i>
                        <h5 class="text-success">Toutes les chambres sont en bon état</h5>
                        <p class="text-muted">Aucune chambre ne nécessite d'attention particulière.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <div class="col-lg-4">
        <!-- Types de chambres -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-chart-pie me-2"></i>
                    Répartition des chambres
                </h5>
            </div>
            <div class="card-body">
                <?php if (!empty($types_chambres)): ?>
                    <?php foreach ($types_chambres as $type): ?>
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <div>
                                <strong><?php echo ucfirst($type['type']); ?></strong>
                                <br><small class="text-muted">
                                    <?php echo $type['capacite_totale']; ?> places
                                </small>
                            </div>
                            <span class="badge bg-primary"><?php echo $type['nombre']; ?></span>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p class="text-muted text-center">Aucune chambre configurée</p>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Résidents par classe -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-school me-2"></i>
                    Résidents par classe
                </h5>
            </div>
            <div class="card-body">
                <?php if (!empty($residents_par_classe)): ?>
                    <?php foreach ($residents_par_classe as $classe): ?>
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <div>
                                <span class="badge bg-<?php 
                                    echo $classe['niveau'] === 'maternelle' ? 'warning' : 
                                        ($classe['niveau'] === 'primaire' ? 'success' : 'primary'); 
                                ?>">
                                    <?php echo htmlspecialchars($classe['classe_nom']); ?>
                                </span>
                            </div>
                            <span class="badge bg-secondary"><?php echo $classe['nb_residents']; ?></span>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p class="text-muted text-center">Aucun résident</p>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Événements récents -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-calendar me-2"></i>
                    Événements récents
                </h5>
            </div>
            <div class="card-body">
                <?php if (!empty($evenements_recents)): ?>
                    <?php foreach (array_slice($evenements_recents, 0, 5) as $evenement): ?>
                        <div class="mb-3">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <strong><?php echo htmlspecialchars($evenement['titre']); ?></strong>
                                    <br><small class="text-muted">
                                        <?php echo formatDate($evenement['date_evenement']); ?>
                                    </small>
                                </div>
                                <span class="badge bg-<?php 
                                    echo $evenement['type'] === 'maintenance' ? 'warning' : 
                                        ($evenement['type'] === 'incident' ? 'danger' : 'info'); 
                                ?>">
                                    <?php echo ucfirst($evenement['type']); ?>
                                </span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    <div class="text-center">
                        <a href="events/" class="btn btn-sm btn-outline-primary">
                            <i class="fas fa-list me-1"></i>
                            Voir tous
                        </a>
                    </div>
                <?php else: ?>
                    <p class="text-muted text-center">Aucun événement récent</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Actions rapides -->
<?php if (checkPermission('internat')): ?>
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
                            <a href="residents/add.php" class="btn btn-outline-primary">
                                <i class="fas fa-user-plus me-2"></i>
                                Nouveau résident
                            </a>
                        </div>
                    </div>
                    <div class="col-md-3 mb-2">
                        <div class="d-grid">
                            <a href="rooms/maintenance.php" class="btn btn-outline-warning">
                                <i class="fas fa-tools me-2"></i>
                                Maintenance
                            </a>
                        </div>
                    </div>
                    <div class="col-md-3 mb-2">
                        <div class="d-grid">
                            <a href="reports/occupancy.php" class="btn btn-outline-info">
                                <i class="fas fa-chart-pie me-2"></i>
                                Taux d'occupation
                            </a>
                        </div>
                    </div>
                    <div class="col-md-3 mb-2">
                        <div class="d-grid">
                            <a href="events/add.php" class="btn btn-outline-success">
                                <i class="fas fa-calendar-plus me-2"></i>
                                Nouvel événement
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<style>
.hover-card {
    transition: transform 0.2s ease-in-out;
}

.hover-card:hover {
    transform: translateY(-5px);
}
</style>

<?php include '../../../includes/footer.php'; ?>
