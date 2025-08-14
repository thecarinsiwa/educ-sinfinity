<?php
/**
 * Module Discipline - Page principale
 * Application de gestion scolaire - République Démocratique du Congo
 */

require_once '../../../config/config.php';
require_once '../../../config/database.php';
require_once '../../../includes/functions.php';

// Vérifier l'authentification et les permissions
requireLogin();
if (!checkPermission('discipline') && !checkPermission('discipline_view')) {
    showMessage('error', 'Accès refusé à ce module.');
    redirectTo('../index.php');
}

$page_title = 'Gestion de la Discipline';

// Obtenir l'année scolaire actuelle
$current_year = getCurrentAcademicYear();

// Statistiques de discipline
$stats = [];

// Incidents par statut
$stmt = $database->query(
    "SELECT status, COUNT(*) as nombre FROM incidents WHERE annee_scolaire_id = ? GROUP BY status",
    [$current_year['id'] ?? 0]
);
$incidents_par_status = $stmt->fetchAll();
foreach ($incidents_par_status as $stat) {
    $stats['incidents_' . $stat['status']] = $stat['nombre'];
}

// Sanctions actives
$stmt = $database->query(
    "SELECT COUNT(*) as total FROM sanctions WHERE annee_scolaire_id = ? AND status = 'active'",
    [$current_year['id'] ?? 0]
);
$stats['sanctions_actives'] = $stmt->fetch()['total'];

// Incidents récents (derniers 7 jours)
$incidents_recents = $database->query(
    "SELECT i.*, e.nom, e.prenom, e.numero_matricule, c.nom as classe_nom,
            p.nom as rapporteur_nom, p.prenom as rapporteur_prenom
     FROM incidents i
     JOIN eleves e ON i.eleve_id = e.id
     JOIN inscriptions ins ON e.id = ins.eleve_id
     JOIN classes c ON ins.classe_id = c.id
     LEFT JOIN personnel p ON i.rapporteur_id = p.id
     WHERE i.date_incident >= DATE_SUB(NOW(), INTERVAL 7 DAY)
     AND i.annee_scolaire_id = ?
     ORDER BY i.date_incident DESC, i.created_at DESC
     LIMIT 10",
    [$current_year['id'] ?? 0]
)->fetchAll();

// Types d'incidents les plus fréquents
$types_incidents = $database->query(
    "SELECT type_incident, COUNT(*) as nombre
     FROM incidents
     WHERE annee_scolaire_id = ?
     GROUP BY type_incident
     ORDER BY nombre DESC
     LIMIT 8",
    [$current_year['id'] ?? 0]
)->fetchAll();

// Élèves avec le plus d'incidents
$eleves_incidents = $database->query(
    "SELECT e.nom, e.prenom, e.numero_matricule, c.nom as classe_nom,
            COUNT(i.id) as nb_incidents,
            COUNT(CASE WHEN i.gravite = 'grave' THEN 1 END) as incidents_graves
     FROM eleves e
     JOIN inscriptions ins ON e.id = ins.eleve_id
     JOIN classes c ON ins.classe_id = c.id
     LEFT JOIN incidents i ON e.id = i.eleve_id AND i.annee_scolaire_id = ?
     WHERE ins.status = 'inscrit' AND ins.annee_scolaire_id = ?
     GROUP BY e.id
     HAVING nb_incidents > 0
     ORDER BY nb_incidents DESC, incidents_graves DESC
     LIMIT 10",
    [$current_year['id'] ?? 0, $current_year['id'] ?? 0]
)->fetchAll();

// Sanctions en cours
$sanctions_actives = $database->query(
    "SELECT s.*, e.nom, e.prenom, e.numero_matricule, c.nom as classe_nom
     FROM sanctions s
     JOIN eleves e ON s.eleve_id = e.id
     JOIN inscriptions ins ON e.id = ins.eleve_id
     JOIN classes c ON ins.classe_id = c.id
     WHERE s.status = 'active' AND s.annee_scolaire_id = ?
     ORDER BY s.date_debut DESC
     LIMIT 8",
    [$current_year['id'] ?? 0]
)->fetchAll();

include '../../../includes/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">
        <i class="fas fa-gavel me-2"></i>
        Gestion de la Discipline
    </h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <div class="btn-group me-2">
            <a href="../index.php" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-1"></i>
                Retour
            </a>
        </div>
        <?php if (checkPermission('discipline')): ?>
            <div class="btn-group me-2">
                <button type="button" class="btn btn-warning dropdown-toggle" data-bs-toggle="dropdown">
                    <i class="fas fa-plus me-1"></i>
                    Nouveau
                </button>
                <ul class="dropdown-menu">
                    <li><a class="dropdown-item" href="incidents/add.php">
                        <i class="fas fa-exclamation-triangle me-2"></i>Signaler incident
                    </a></li>
                    <li><a class="dropdown-item" href="sanctions/add.php">
                        <i class="fas fa-gavel me-2"></i>Nouvelle sanction
                    </a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item" href="rules/manage.php">
                        <i class="fas fa-list-ul me-2"></i>Gérer règlement
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
                <li><a class="dropdown-item" href="reports/incidents.php">
                    <i class="fas fa-exclamation-triangle me-2"></i>Rapport incidents
                </a></li>
                <li><a class="dropdown-item" href="reports/sanctions.php">
                    <i class="fas fa-gavel me-2"></i>Rapport sanctions
                </a></li>
                <li><a class="dropdown-item" href="reports/behavior.php">
                    <i class="fas fa-user-check me-2"></i>Suivi comportemental
                </a></li>
            </ul>
        </div>
    </div>
</div>

<!-- Statistiques rapides -->
<div class="row mb-4">
    <div class="col-lg-3 col-md-6 mb-3">
        <div class="card text-white bg-warning">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h4><?php echo ($stats['incidents_ouvert'] ?? 0) + ($stats['incidents_en_cours'] ?? 0); ?></h4>
                        <p class="mb-0">Incidents ouverts</p>
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
                        <h4><?php echo $stats['sanctions_actives']; ?></h4>
                        <p class="mb-0">Sanctions actives</p>
                    </div>
                    <div class="align-self-center">
                        <i class="fas fa-gavel fa-2x"></i>
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
                        <h4><?php echo $stats['incidents_resolu'] ?? 0; ?></h4>
                        <p class="mb-0">Incidents résolus</p>
                    </div>
                    <div class="align-self-center">
                        <i class="fas fa-check-circle fa-2x"></i>
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
                        <h4><?php echo count($incidents_recents); ?></h4>
                        <p class="mb-0">Cette semaine</p>
                    </div>
                    <div class="align-self-center">
                        <i class="fas fa-calendar-week fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modules de discipline -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-th-large me-2"></i>
                    Modules de discipline
                </h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-lg-3 col-md-6 mb-3">
                        <a href="incidents/" class="text-decoration-none">
                            <div class="card h-100 border-0 shadow-sm hover-card">
                                <div class="card-body text-center">
                                    <i class="fas fa-exclamation-triangle fa-3x text-warning mb-3"></i>
                                    <h5 class="card-title">Incidents</h5>
                                    <p class="card-text text-muted">
                                        Signalement et suivi des incidents
                                    </p>
                                    <div class="mt-3">
                                        <span class="badge bg-warning"><?php echo array_sum(array_column($incidents_par_status, 'nombre')); ?> incidents</span>
                                    </div>
                                </div>
                            </div>
                        </a>
                    </div>
                    
                    <div class="col-lg-3 col-md-6 mb-3">
                        <a href="sanctions/" class="text-decoration-none">
                            <div class="card h-100 border-0 shadow-sm hover-card">
                                <div class="card-body text-center">
                                    <i class="fas fa-gavel fa-3x text-danger mb-3"></i>
                                    <h5 class="card-title">Sanctions</h5>
                                    <p class="card-text text-muted">
                                        Gestion des sanctions disciplinaires
                                    </p>
                                    <div class="mt-3">
                                        <span class="badge bg-danger"><?php echo $stats['sanctions_actives']; ?> actives</span>
                                    </div>
                                </div>
                            </div>
                        </a>
                    </div>
                    
                    <div class="col-lg-3 col-md-6 mb-3">
                        <a href="behavior/" class="text-decoration-none">
                            <div class="card h-100 border-0 shadow-sm hover-card">
                                <div class="card-body text-center">
                                    <i class="fas fa-user-check fa-3x text-success mb-3"></i>
                                    <h5 class="card-title">Comportement</h5>
                                    <p class="card-text text-muted">
                                        Suivi comportemental des élèves
                                    </p>
                                    <div class="mt-3">
                                        <span class="badge bg-success">Suivi individuel</span>
                                    </div>
                                </div>
                            </div>
                        </a>
                    </div>
                    
                    <div class="col-lg-3 col-md-6 mb-3">
                        <a href="rules/" class="text-decoration-none">
                            <div class="card h-100 border-0 shadow-sm hover-card">
                                <div class="card-body text-center">
                                    <i class="fas fa-list-ul fa-3x text-info mb-3"></i>
                                    <h5 class="card-title">Règlement</h5>
                                    <p class="card-text text-muted">
                                        Règlement intérieur et codes de conduite
                                    </p>
                                    <div class="mt-3">
                                        <span class="badge bg-info">Règles & Codes</span>
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
        <!-- Incidents récents -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-clock me-2"></i>
                    Incidents récents (7 derniers jours)
                </h5>
            </div>
            <div class="card-body">
                <?php if (!empty($incidents_recents)): ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Élève</th>
                                    <th>Type</th>
                                    <th>Gravité</th>
                                    <th>Rapporteur</th>
                                    <th>Statut</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($incidents_recents as $incident): ?>
                                    <tr>
                                        <td><?php echo formatDate($incident['date_incident']); ?></td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($incident['nom'] . ' ' . $incident['prenom']); ?></strong>
                                            <br><small class="text-muted">
                                                <?php echo htmlspecialchars($incident['classe_nom']); ?>
                                            </small>
                                        </td>
                                        <td>
                                            <span class="badge bg-secondary">
                                                <?php echo htmlspecialchars($incident['type_incident']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php
                                            $gravite_colors = [
                                                'legere' => 'success',
                                                'moyenne' => 'warning',
                                                'grave' => 'danger'
                                            ];
                                            $color = $gravite_colors[$incident['gravite']] ?? 'secondary';
                                            ?>
                                            <span class="badge bg-<?php echo $color; ?>">
                                                <?php echo ucfirst($incident['gravite']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($incident['rapporteur_nom']): ?>
                                                <small>
                                                    <?php echo htmlspecialchars($incident['rapporteur_nom'] . ' ' . substr($incident['rapporteur_prenom'], 0, 1) . '.'); ?>
                                                </small>
                                            <?php else: ?>
                                                <span class="text-muted">Non spécifié</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php
                                            $status_colors = [
                                                'ouvert' => 'warning',
                                                'en_cours' => 'info',
                                                'resolu' => 'success'
                                            ];
                                            $color = $status_colors[$incident['status']] ?? 'secondary';
                                            ?>
                                            <span class="badge bg-<?php echo $color; ?>">
                                                <?php echo ucfirst($incident['status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <a href="incidents/view.php?id=<?php echo $incident['id']; ?>" 
                                                   class="btn btn-outline-info" title="Voir">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                <?php if (checkPermission('discipline') && $incident['status'] !== 'resolu'): ?>
                                                    <a href="incidents/edit.php?id=<?php echo $incident['id']; ?>" 
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
                    <div class="text-center">
                        <a href="incidents/" class="btn btn-outline-secondary">
                            <i class="fas fa-list me-1"></i>
                            Voir tous les incidents
                        </a>
                    </div>
                <?php else: ?>
                    <div class="text-center py-4">
                        <i class="fas fa-check-circle fa-3x text-success mb-3"></i>
                        <h5 class="text-success">Aucun incident récent</h5>
                        <p class="text-muted">Aucun incident signalé cette semaine.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Sanctions actives -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-gavel me-2"></i>
                    Sanctions en cours
                </h5>
            </div>
            <div class="card-body">
                <?php if (!empty($sanctions_actives)): ?>
                    <div class="row">
                        <?php foreach ($sanctions_actives as $sanction): ?>
                            <div class="col-md-6 mb-3">
                                <div class="card border-danger">
                                    <div class="card-body">
                                        <h6 class="card-title">
                                            <?php echo htmlspecialchars($sanction['nom'] . ' ' . $sanction['prenom']); ?>
                                        </h6>
                                        <p class="card-text">
                                            <strong><?php echo htmlspecialchars($sanction['type_sanction']); ?></strong>
                                            <br><small class="text-muted">
                                                <?php echo htmlspecialchars($sanction['classe_nom']); ?> - 
                                                Du <?php echo formatDate($sanction['date_debut']); ?>
                                                <?php if ($sanction['date_fin']): ?>
                                                    au <?php echo formatDate($sanction['date_fin']); ?>
                                                <?php endif; ?>
                                            </small>
                                        </p>
                                        <div class="d-flex justify-content-between align-items-center">
                                            <span class="badge bg-danger">Active</span>
                                            <a href="sanctions/view.php?id=<?php echo $sanction['id']; ?>" 
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
                        <h5 class="text-success">Aucune sanction active</h5>
                        <p class="text-muted">Aucune sanction disciplinaire en cours.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <div class="col-lg-4">
        <!-- Types d'incidents -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-chart-pie me-2"></i>
                    Types d'incidents
                </h5>
            </div>
            <div class="card-body">
                <?php if (!empty($types_incidents)): ?>
                    <?php foreach ($types_incidents as $type): ?>
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span><?php echo htmlspecialchars($type['type_incident']); ?></span>
                            <span class="badge bg-warning"><?php echo $type['nombre']; ?></span>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p class="text-muted text-center">Aucun incident enregistré</p>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Élèves à surveiller -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-eye me-2"></i>
                    Élèves à surveiller
                </h5>
            </div>
            <div class="card-body">
                <?php if (!empty($eleves_incidents)): ?>
                    <?php foreach (array_slice($eleves_incidents, 0, 8) as $eleve): ?>
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <div>
                                <strong><?php echo htmlspecialchars($eleve['nom'] . ' ' . $eleve['prenom']); ?></strong>
                                <br><small class="text-muted">
                                    <?php echo htmlspecialchars($eleve['classe_nom']); ?>
                                </small>
                            </div>
                            <div class="text-end">
                                <span class="badge bg-warning"><?php echo $eleve['nb_incidents']; ?></span>
                                <?php if ($eleve['incidents_graves'] > 0): ?>
                                    <br><span class="badge bg-danger"><?php echo $eleve['incidents_graves']; ?> grave<?php echo $eleve['incidents_graves'] > 1 ? 's' : ''; ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    <div class="text-center mt-3">
                        <a href="behavior/monitoring.php" class="btn btn-sm btn-outline-primary">
                            <i class="fas fa-list me-1"></i>
                            Voir tous
                        </a>
                    </div>
                <?php else: ?>
                    <div class="text-center py-3">
                        <i class="fas fa-smile fa-2x text-success mb-2"></i>
                        <p class="text-muted mb-0">Aucun élève à surveiller</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Actions rapides -->
<?php if (checkPermission('discipline')): ?>
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
                            <a href="incidents/add.php" class="btn btn-outline-warning">
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                Signaler incident
                            </a>
                        </div>
                    </div>
                    <div class="col-md-3 mb-2">
                        <div class="d-grid">
                            <a href="sanctions/add.php" class="btn btn-outline-danger">
                                <i class="fas fa-gavel me-2"></i>
                                Nouvelle sanction
                            </a>
                        </div>
                    </div>
                    <div class="col-md-3 mb-2">
                        <div class="d-grid">
                            <a href="behavior/monitoring.php" class="btn btn-outline-info">
                                <i class="fas fa-user-check me-2"></i>
                                Suivi comportemental
                            </a>
                        </div>
                    </div>
                    <div class="col-md-3 mb-2">
                        <div class="d-grid">
                            <a href="reports/monthly.php" class="btn btn-outline-secondary">
                                <i class="fas fa-chart-bar me-2"></i>
                                Rapport mensuel
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
