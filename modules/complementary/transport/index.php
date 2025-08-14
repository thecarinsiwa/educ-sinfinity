<?php
/**
 * Module Transport - Page principale
 * Application de gestion scolaire - République Démocratique du Congo
 */

require_once '../../../config/config.php';
require_once '../../../config/database.php';
require_once '../../../includes/functions.php';

// Vérifier l'authentification et les permissions
requireLogin();
if (!checkPermission('transport') && !checkPermission('transport_view')) {
    showMessage('error', 'Accès refusé à ce module.');
    redirectTo('../index.php');
}

$page_title = 'Gestion du Transport Scolaire';

// Obtenir l'année scolaire actuelle
$current_year = getCurrentAcademicYear();

// Statistiques du transport
$stats = [];

// Nombre total de véhicules
$stmt = $database->query("SELECT COUNT(*) as total FROM vehicules");
$stats['total_vehicules'] = $stmt->fetch()['total'];

// Véhicules actifs
$stmt = $database->query("SELECT COUNT(*) as total FROM vehicules WHERE status = 'actif'");
$stats['vehicules_actifs'] = $stmt->fetch()['total'];

// Élèves transportés
$stmt = $database->query(
    "SELECT COUNT(*) as total FROM transport_eleves WHERE annee_scolaire_id = ? AND status = 'actif'",
    [$current_year['id'] ?? 0]
);
$stats['eleves_transportes'] = $stmt->fetch()['total'];

// Itinéraires actifs
$stmt = $database->query("SELECT COUNT(*) as total FROM itineraires WHERE status = 'actif'");
$stats['itineraires_actifs'] = $stmt->fetch()['total'];

// Véhicules par statut
$vehicules_par_statut = $database->query(
    "SELECT status, COUNT(*) as nombre FROM vehicules GROUP BY status ORDER BY nombre DESC"
)->fetchAll();

// Élèves par itinéraire
$eleves_par_itineraire = $database->query(
    "SELECT i.nom as itineraire_nom, i.code, COUNT(te.id) as nb_eleves
     FROM itineraires i
     LEFT JOIN transport_eleves te ON i.id = te.itineraire_id AND te.status = 'actif'
     WHERE i.status = 'actif'
     GROUP BY i.id
     ORDER BY nb_eleves DESC
     LIMIT 10"
)->fetchAll();

// Véhicules récents
$vehicules_info = $database->query(
    "SELECT v.*, COUNT(te.id) as nb_eleves_assignes
     FROM vehicules v
     LEFT JOIN transport_eleves te ON v.id = te.vehicule_id AND te.status = 'actif'
     GROUP BY v.id
     ORDER BY v.created_at DESC
     LIMIT 8"
)->fetchAll();

// Alertes de maintenance
$alertes_maintenance = $database->query(
    "SELECT * FROM vehicules 
     WHERE (prochaine_revision <= DATE_ADD(CURDATE(), INTERVAL 30 DAY) AND prochaine_revision >= CURDATE())
     OR status = 'maintenance'
     ORDER BY prochaine_revision ASC
     LIMIT 5"
)->fetchAll();

include '../../../includes/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">
        <i class="fas fa-bus me-2"></i>
        Gestion du Transport Scolaire
    </h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <div class="btn-group me-2">
            <a href="../index.php" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-1"></i>
                Retour
            </a>
        </div>
        <?php if (checkPermission('transport')): ?>
            <div class="btn-group me-2">
                <button type="button" class="btn btn-secondary dropdown-toggle" data-bs-toggle="dropdown">
                    <i class="fas fa-plus me-1"></i>
                    Nouveau
                </button>
                <ul class="dropdown-menu">
                    <li><a class="dropdown-item" href="vehicles/add.php">
                        <i class="fas fa-bus me-2"></i>Nouveau véhicule
                    </a></li>
                    <li><a class="dropdown-item" href="routes/add.php">
                        <i class="fas fa-route me-2"></i>Nouvel itinéraire
                    </a></li>
                    <li><a class="dropdown-item" href="students/assign.php">
                        <i class="fas fa-user-plus me-2"></i>Assigner élève
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
                <li><a class="dropdown-item" href="reports/routes.php">
                    <i class="fas fa-route me-2"></i>Rapport itinéraires
                </a></li>
                <li><a class="dropdown-item" href="reports/vehicles.php">
                    <i class="fas fa-bus me-2"></i>État des véhicules
                </a></li>
                <li><a class="dropdown-item" href="reports/students.php">
                    <i class="fas fa-users me-2"></i>Élèves transportés
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
                        <h4><?php echo $stats['eleves_transportes']; ?></h4>
                        <p class="mb-0">Élèves transportés</p>
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
                        <h4><?php echo $stats['vehicules_actifs']; ?></h4>
                        <p class="mb-0">Véhicules actifs</p>
                    </div>
                    <div class="align-self-center">
                        <i class="fas fa-bus fa-2x"></i>
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
                        <h4><?php echo $stats['itineraires_actifs']; ?></h4>
                        <p class="mb-0">Itinéraires actifs</p>
                    </div>
                    <div class="align-self-center">
                        <i class="fas fa-route fa-2x"></i>
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
                        <h4><?php echo count($alertes_maintenance); ?></h4>
                        <p class="mb-0">Alertes maintenance</p>
                    </div>
                    <div class="align-self-center">
                        <i class="fas fa-exclamation-triangle fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modules du transport -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-th-large me-2"></i>
                    Modules du transport
                </h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-lg-3 col-md-6 mb-3">
                        <a href="vehicles/" class="text-decoration-none">
                            <div class="card h-100 border-0 shadow-sm hover-card">
                                <div class="card-body text-center">
                                    <i class="fas fa-bus fa-3x text-primary mb-3"></i>
                                    <h5 class="card-title">Véhicules</h5>
                                    <p class="card-text text-muted">
                                        Gestion de la flotte de véhicules
                                    </p>
                                    <div class="mt-3">
                                        <span class="badge bg-primary"><?php echo $stats['total_vehicules']; ?> véhicules</span>
                                    </div>
                                </div>
                            </div>
                        </a>
                    </div>
                    
                    <div class="col-lg-3 col-md-6 mb-3">
                        <a href="routes/" class="text-decoration-none">
                            <div class="card h-100 border-0 shadow-sm hover-card">
                                <div class="card-body text-center">
                                    <i class="fas fa-route fa-3x text-success mb-3"></i>
                                    <h5 class="card-title">Itinéraires</h5>
                                    <p class="card-text text-muted">
                                        Gestion des parcours et arrêts
                                    </p>
                                    <div class="mt-3">
                                        <span class="badge bg-success"><?php echo $stats['itineraires_actifs']; ?> itinéraires</span>
                                    </div>
                                </div>
                            </div>
                        </a>
                    </div>
                    
                    <div class="col-lg-3 col-md-6 mb-3">
                        <a href="students/" class="text-decoration-none">
                            <div class="card h-100 border-0 shadow-sm hover-card">
                                <div class="card-body text-center">
                                    <i class="fas fa-users fa-3x text-info mb-3"></i>
                                    <h5 class="card-title">Élèves</h5>
                                    <p class="card-text text-muted">
                                        Gestion des élèves transportés
                                    </p>
                                    <div class="mt-3">
                                        <span class="badge bg-info"><?php echo $stats['eleves_transportes']; ?> élèves</span>
                                    </div>
                                </div>
                            </div>
                        </a>
                    </div>
                    
                    <div class="col-lg-3 col-md-6 mb-3">
                        <a href="maintenance/" class="text-decoration-none">
                            <div class="card h-100 border-0 shadow-sm hover-card">
                                <div class="card-body text-center">
                                    <i class="fas fa-tools fa-3x text-warning mb-3"></i>
                                    <h5 class="card-title">Maintenance</h5>
                                    <p class="card-text text-muted">
                                        Suivi et maintenance des véhicules
                                    </p>
                                    <div class="mt-3">
                                        <span class="badge bg-warning">Entretien & Réparations</span>
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
        <!-- Véhicules -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-bus me-2"></i>
                    État des véhicules
                </h5>
            </div>
            <div class="card-body">
                <?php if (!empty($vehicules_info)): ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Véhicule</th>
                                    <th>Immatriculation</th>
                                    <th>Capacité</th>
                                    <th>Élèves assignés</th>
                                    <th>Statut</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($vehicules_info as $vehicule): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo htmlspecialchars($vehicule['marque'] . ' ' . $vehicule['modele']); ?></strong>
                                            <br><small class="text-muted">
                                                <?php echo htmlspecialchars($vehicule['type_vehicule']); ?>
                                            </small>
                                        </td>
                                        <td><?php echo htmlspecialchars($vehicule['immatriculation']); ?></td>
                                        <td><?php echo $vehicule['capacite']; ?> places</td>
                                        <td class="text-center">
                                            <span class="badge bg-<?php echo $vehicule['nb_eleves_assignes'] > 0 ? 'success' : 'secondary'; ?>">
                                                <?php echo $vehicule['nb_eleves_assignes']; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php
                                            $status_colors = [
                                                'actif' => 'success',
                                                'maintenance' => 'warning',
                                                'hors_service' => 'danger',
                                                'reserve' => 'info'
                                            ];
                                            $color = $status_colors[$vehicule['status']] ?? 'secondary';
                                            ?>
                                            <span class="badge bg-<?php echo $color; ?>">
                                                <?php echo ucfirst(str_replace('_', ' ', $vehicule['status'])); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <a href="vehicles/view.php?id=<?php echo $vehicule['id']; ?>" 
                                                   class="btn btn-outline-info" title="Voir">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                <?php if (checkPermission('transport')): ?>
                                                    <a href="vehicles/edit.php?id=<?php echo $vehicule['id']; ?>" 
                                                       class="btn btn-outline-primary" title="Modifier">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
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
                        <i class="fas fa-bus fa-3x text-muted mb-3"></i>
                        <p class="text-muted">Aucun véhicule enregistré</p>
                        <?php if (checkPermission('transport')): ?>
                            <a href="vehicles/add.php" class="btn btn-primary">
                                <i class="fas fa-plus me-1"></i>
                                Ajouter un véhicule
                            </a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Alertes de maintenance -->
        <?php if (!empty($alertes_maintenance)): ?>
        <div class="card">
            <div class="card-header bg-warning text-dark">
                <h5 class="mb-0">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    Alertes de maintenance
                </h5>
            </div>
            <div class="card-body">
                <div class="list-group list-group-flush">
                    <?php foreach ($alertes_maintenance as $alerte): ?>
                        <div class="list-group-item d-flex justify-content-between align-items-center">
                            <div>
                                <strong><?php echo htmlspecialchars($alerte['marque'] . ' ' . $alerte['modele']); ?></strong>
                                <br><small class="text-muted">
                                    <?php echo htmlspecialchars($alerte['immatriculation']); ?>
                                </small>
                            </div>
                            <div class="text-end">
                                <?php if ($alerte['status'] === 'maintenance'): ?>
                                    <span class="badge bg-danger">En maintenance</span>
                                <?php else: ?>
                                    <span class="badge bg-warning">
                                        Révision: <?php echo formatDate($alerte['prochaine_revision']); ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
    
    <div class="col-lg-4">
        <!-- Statut des véhicules -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-chart-pie me-2"></i>
                    Statut des véhicules
                </h5>
            </div>
            <div class="card-body">
                <?php if (!empty($vehicules_par_statut)): ?>
                    <?php foreach ($vehicules_par_statut as $statut): ?>
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span><?php echo ucfirst(str_replace('_', ' ', $statut['status'])); ?></span>
                            <span class="badge bg-<?php 
                                echo $statut['status'] === 'actif' ? 'success' : 
                                    ($statut['status'] === 'maintenance' ? 'warning' : 'secondary'); 
                            ?>">
                                <?php echo $statut['nombre']; ?>
                            </span>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p class="text-muted text-center">Aucun véhicule</p>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Élèves par itinéraire -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-route me-2"></i>
                    Élèves par itinéraire
                </h5>
            </div>
            <div class="card-body">
                <?php if (!empty($eleves_par_itineraire)): ?>
                    <?php foreach ($eleves_par_itineraire as $itineraire): ?>
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <div>
                                <strong><?php echo htmlspecialchars($itineraire['itineraire_nom']); ?></strong>
                                <br><small class="text-muted">
                                    Code: <?php echo htmlspecialchars($itineraire['code']); ?>
                                </small>
                            </div>
                            <span class="badge bg-primary"><?php echo $itineraire['nb_eleves']; ?></span>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p class="text-muted text-center">Aucun itinéraire configuré</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Actions rapides -->
<?php if (checkPermission('transport')): ?>
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
                            <a href="vehicles/add.php" class="btn btn-outline-primary">
                                <i class="fas fa-bus me-2"></i>
                                Nouveau véhicule
                            </a>
                        </div>
                    </div>
                    <div class="col-md-3 mb-2">
                        <div class="d-grid">
                            <a href="routes/add.php" class="btn btn-outline-success">
                                <i class="fas fa-route me-2"></i>
                                Nouvel itinéraire
                            </a>
                        </div>
                    </div>
                    <div class="col-md-3 mb-2">
                        <div class="d-grid">
                            <a href="students/assign.php" class="btn btn-outline-info">
                                <i class="fas fa-user-plus me-2"></i>
                                Assigner élève
                            </a>
                        </div>
                    </div>
                    <div class="col-md-3 mb-2">
                        <div class="d-grid">
                            <a href="maintenance/schedule.php" class="btn btn-outline-warning">
                                <i class="fas fa-tools me-2"></i>
                                Planifier maintenance
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
