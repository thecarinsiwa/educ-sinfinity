<?php
/**
 * Module Discipline - Tableau de bord
 * Application de gestion scolaire - République Démocratique du Congo
 */

require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../includes/functions.php';

// Vérifier l'authentification et les permissions
requireLogin();
if (!checkPermission('discipline')) {
    showMessage('error', 'Accès refusé à cette page.');
    redirectTo('../../index.php');
}

// Statistiques générales
try {
    $stats = $database->query(
        "SELECT 
            (SELECT COUNT(*) FROM incidents WHERE status != 'archive') as incidents_actifs,
            (SELECT COUNT(*) FROM incidents WHERE status = 'nouveau') as incidents_nouveaux,
            (SELECT COUNT(*) FROM sanctions WHERE status = 'active') as sanctions_actives,
            (SELECT COUNT(*) FROM recompenses WHERE MONTH(date_recompense) = MONTH(CURDATE()) AND YEAR(date_recompense) = YEAR(CURDATE())) as recompenses_mois,
            (SELECT COUNT(*) FROM incidents WHERE DATE(date_incident) = CURDATE()) as incidents_aujourd_hui,
            (SELECT COUNT(*) FROM sanctions WHERE DATE(date_sanction) = CURDATE()) as sanctions_aujourd_hui"
    )->fetch();
} catch (Exception $e) {
    $stats = [
        'incidents_actifs' => 0,
        'incidents_nouveaux' => 0,
        'sanctions_actives' => 0,
        'recompenses_mois' => 0,
        'incidents_aujourd_hui' => 0,
        'sanctions_aujourd_hui' => 0
    ];
}

// Incidents récents
try {
    $incidents_recents = $database->query(
        "SELECT i.*, 
                CONCAT('Élève ', i.eleve_id) as eleve_nom,
                CONCAT('Personnel ', i.rapporte_par) as rapporte_par_nom,
                DATEDIFF(NOW(), i.date_incident) as jours_depuis
         FROM incidents i
         WHERE i.status != 'archive'
         ORDER BY i.date_incident DESC
         LIMIT 10"
    )->fetchAll();
} catch (Exception $e) {
    $incidents_recents = [];
}

// Sanctions en cours
try {
    // Vérifier d'abord si les nouvelles colonnes existent
    $columns = $database->query("DESCRIBE sanctions")->fetchAll();
    $column_names = array_column($columns, 'Field');

    $has_date_fin = in_array('date_fin', $column_names);
    $has_date_debut = in_array('date_debut', $column_names);
    $has_type_sanction_id = in_array('type_sanction_id', $column_names);

    // Vérifier si la table types_sanctions existe
    $types_sanctions_exists = $database->query("SHOW TABLES LIKE 'types_sanctions'")->fetch();

    if ($has_type_sanction_id && $types_sanctions_exists) {
        // Nouvelle structure avec types_sanctions
        if ($has_date_fin && $has_date_debut) {
            $sanctions_en_cours = $database->query(
                "SELECT s.*, ts.nom as type_nom, ts.couleur,
                        CONCAT('Élève ', s.eleve_id) as eleve_nom,
                        DATEDIFF(s.date_fin, CURDATE()) as jours_restants
                 FROM sanctions s
                 JOIN types_sanctions ts ON s.type_sanction_id = ts.id
                 WHERE s.status = 'active' AND (s.date_fin IS NULL OR s.date_fin >= CURDATE())
                 ORDER BY s.date_debut DESC
                 LIMIT 10"
            )->fetchAll();
        } else {
            $sanctions_en_cours = $database->query(
                "SELECT s.*, ts.nom as type_nom, ts.couleur,
                        CONCAT('Élève ', s.eleve_id) as eleve_nom,
                        s.duree_jours as jours_restants
                 FROM sanctions s
                 JOIN types_sanctions ts ON s.type_sanction_id = ts.id
                 WHERE s.status = 'active'
                 ORDER BY s.date_sanction DESC
                 LIMIT 10"
            )->fetchAll();
        }
    } else {
        // Ancienne structure sans types_sanctions
        $sanctions_en_cours = $database->query(
            "SELECT s.*,
                    s.type_sanction as type_nom,
                    '#ffc107' as couleur,
                    CONCAT('Élève ', s.eleve_id) as eleve_nom,
                    s.duree_jours as jours_restants
             FROM sanctions s
             WHERE s.status = 'active'
             ORDER BY s.date_sanction DESC
             LIMIT 10"
        )->fetchAll();
    }
} catch (Exception $e) {
    $sanctions_en_cours = [];
}

// Statistiques par gravité
try {
    $stats_gravite = $database->query(
        "SELECT gravite, COUNT(*) as nombre
         FROM incidents 
         WHERE MONTH(date_incident) = MONTH(CURDATE()) AND YEAR(date_incident) = YEAR(CURDATE())
         GROUP BY gravite
         ORDER BY 
            CASE gravite 
                WHEN 'legere' THEN 1 
                WHEN 'moyenne' THEN 2 
                WHEN 'grave' THEN 3 
                WHEN 'tres_grave' THEN 4 
            END"
    )->fetchAll();
} catch (Exception $e) {
    $stats_gravite = [];
}

$page_title = "Module Discipline";
include '../../includes/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">
        <i class="fas fa-gavel me-2"></i>
        Module Discipline
    </h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <div class="btn-group me-2">
            <a href="incidents/add.php" class="btn btn-primary">
                <i class="fas fa-plus me-1"></i>
                Signaler un incident
            </a>
        </div>
        <div class="btn-group me-2">
            <a href="recompenses/add.php" class="btn btn-success">
                <i class="fas fa-award me-1"></i>
                Ajouter une récompense
            </a>
        </div>
    </div>
</div>

<!-- Statistiques rapides -->
<div class="row mb-4">
    <div class="col-md-2">
        <div class="card text-center shadow-sm stats-card">
            <div class="card-body">
                <i class="fas fa-exclamation-triangle fa-2x text-warning mb-2"></i>
                <h4><?php echo number_format($stats['incidents_actifs'] ?? 0); ?></h4>
                <p class="text-muted mb-0">Incidents actifs</p>
            </div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card text-center shadow-sm stats-card">
            <div class="card-body">
                <i class="fas fa-bell fa-2x text-danger mb-2"></i>
                <h4><?php echo number_format($stats['incidents_nouveaux'] ?? 0); ?></h4>
                <p class="text-muted mb-0">Nouveaux incidents</p>
            </div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card text-center shadow-sm stats-card">
            <div class="card-body">
                <i class="fas fa-gavel fa-2x text-info mb-2"></i>
                <h4><?php echo number_format($stats['sanctions_actives'] ?? 0); ?></h4>
                <p class="text-muted mb-0">Sanctions actives</p>
            </div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card text-center shadow-sm stats-card">
            <div class="card-body">
                <i class="fas fa-award fa-2x text-success mb-2"></i>
                <h4><?php echo number_format($stats['recompenses_mois'] ?? 0); ?></h4>
                <p class="text-muted mb-0">Récompenses ce mois</p>
            </div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card text-center shadow-sm stats-card">
            <div class="card-body">
                <i class="fas fa-calendar-day fa-2x text-primary mb-2"></i>
                <h4><?php echo number_format($stats['incidents_aujourd_hui'] ?? 0); ?></h4>
                <p class="text-muted mb-0">Incidents aujourd'hui</p>
            </div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card text-center shadow-sm stats-card">
            <div class="card-body">
                <i class="fas fa-balance-scale fa-2x text-secondary mb-2"></i>
                <h4><?php echo number_format($stats['sanctions_aujourd_hui'] ?? 0); ?></h4>
                <p class="text-muted mb-0">Sanctions aujourd'hui</p>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <!-- Incidents récents -->
    <div class="col-lg-8 mb-4">
        <div class="card shadow-sm">
            <div class="card-header bg-light">
                <h5 class="mb-0">
                    <i class="fas fa-list me-2 text-warning"></i>
                    Incidents récents
                </h5>
            </div>
            <div class="card-body">
                <?php if (empty($incidents_recents)): ?>
                    <div class="text-center py-4">
                        <i class="fas fa-check-circle fa-3x text-success mb-3"></i>
                        <h6 class="text-muted">Aucun incident récent</h6>
                        <p class="text-muted">Excellente discipline dans l'établissement !</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead class="table-light">
                                <tr>
                                    <th>Date</th>
                                    <th>Élève</th>
                                    <th>Description</th>
                                    <th>Gravité</th>
                                    <th>Statut</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($incidents_recents as $incident): ?>
                                    <tr>
                                        <td>
                                            <?php echo formatDate($incident['date_incident']); ?>
                                            <?php if ($incident['jours_depuis'] == 0): ?>
                                                <br><small class="text-primary">Aujourd'hui</small>
                                            <?php elseif ($incident['jours_depuis'] == 1): ?>
                                                <br><small class="text-muted">Hier</small>
                                            <?php else: ?>
                                                <br><small class="text-muted">Il y a <?php echo $incident['jours_depuis']; ?> jour(s)</small>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($incident['eleve_nom']); ?></td>
                                        <td>
                                            <?php echo htmlspecialchars(substr($incident['description'], 0, 50)); ?>
                                            <?php if (strlen($incident['description']) > 50): ?>...<?php endif; ?>
                                        </td>
                                        <td>
                                            <?php
                                            $gravite_colors = [
                                                'legere' => 'success',
                                                'moyenne' => 'warning',
                                                'grave' => 'danger',
                                                'tres_grave' => 'dark'
                                            ];
                                            $color = $gravite_colors[$incident['gravite']] ?? 'secondary';
                                            ?>
                                            <span class="badge bg-<?php echo $color; ?>">
                                                <?php echo ucfirst(str_replace('_', ' ', $incident['gravite'])); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php
                                            $status_colors = [
                                                'nouveau' => 'danger',
                                                'en_cours' => 'warning',
                                                'resolu' => 'success',
                                                'archive' => 'secondary'
                                            ];
                                            $color = $status_colors[$incident['status']] ?? 'secondary';
                                            ?>
                                            <span class="badge bg-<?php echo $color; ?>">
                                                <?php echo ucfirst(str_replace('_', ' ', $incident['status'])); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <a href="incidents/view.php?id=<?php echo $incident['id']; ?>" 
                                               class="btn btn-sm btn-outline-primary" title="Voir détails">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="text-center">
                        <a href="incidents/" class="btn btn-outline-primary">
                            <i class="fas fa-list me-1"></i>
                            Voir tous les incidents
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Colonne droite -->
    <div class="col-lg-4">
        <!-- Sanctions en cours -->
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-light">
                <h5 class="mb-0">
                    <i class="fas fa-gavel me-2 text-info"></i>
                    Sanctions en cours
                </h5>
            </div>
            <div class="card-body">
                <?php if (empty($sanctions_en_cours)): ?>
                    <div class="text-center py-3">
                        <i class="fas fa-peace fa-2x text-success mb-2"></i>
                        <p class="text-muted mb-0">Aucune sanction en cours</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($sanctions_en_cours as $sanction): ?>
                        <div class="d-flex align-items-center mb-3 p-2 border rounded">
                            <div class="flex-shrink-0">
                                <span class="badge" style="background-color: <?php echo $sanction['couleur'] ?? '#6c757d'; ?>">
                                    <?php echo htmlspecialchars($sanction['type_nom']); ?>
                                </span>
                            </div>
                            <div class="flex-grow-1 ms-3">
                                <div class="fw-bold"><?php echo htmlspecialchars($sanction['eleve_nom']); ?></div>
                                <small class="text-muted">
                                    <?php if (isset($sanction['jours_restants']) && is_numeric($sanction['jours_restants'])): ?>
                                        <?php if ($sanction['jours_restants'] > 0): ?>
                                            <?php echo $sanction['jours_restants']; ?> jour(s) restant(s)
                                        <?php elseif ($sanction['jours_restants'] == 0): ?>
                                            Se termine aujourd'hui
                                        <?php else: ?>
                                            Durée : <?php echo abs($sanction['jours_restants']); ?> jour(s)
                                        <?php endif; ?>
                                    <?php elseif (isset($sanction['duree_jours']) && $sanction['duree_jours']): ?>
                                        Durée : <?php echo $sanction['duree_jours']; ?> jour(s)
                                    <?php else: ?>
                                        Sanction permanente
                                    <?php endif; ?>
                                </small>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    <div class="text-center">
                        <a href="sanctions/" class="btn btn-sm btn-outline-info">
                            Voir toutes les sanctions
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Statistiques par gravité -->
        <div class="card shadow-sm">
            <div class="card-header bg-light">
                <h5 class="mb-0">
                    <i class="fas fa-chart-pie me-2 text-primary"></i>
                    Incidents ce mois par gravité
                </h5>
            </div>
            <div class="card-body">
                <?php if (empty($stats_gravite)): ?>
                    <div class="text-center py-3">
                        <i class="fas fa-smile fa-2x text-success mb-2"></i>
                        <p class="text-muted mb-0">Aucun incident ce mois</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($stats_gravite as $stat): ?>
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span><?php echo ucfirst(str_replace('_', ' ', $stat['gravite'])); ?></span>
                            <span class="badge bg-primary"><?php echo $stat['nombre']; ?></span>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Actions rapides -->
<div class="row mt-4">
    <div class="col-12">
        <div class="card shadow-sm">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0">
                    <i class="fas fa-bolt me-2"></i>
                    Actions rapides
                </h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-3 mb-3">
                        <a href="incidents/" class="btn btn-outline-warning w-100">
                            <i class="fas fa-exclamation-triangle fa-2x mb-2"></i>
                            <br>Incident
                        </a>
                    </div>
                    <div class="col-md-3 mb-3">
                        <a href="sanctions/" class="btn btn-outline-danger w-100">
                            <i class="fas fa-gavel fa-2x mb-2"></i>
                            <br>Sanction
                        </a>
                    </div>
                    <div class="col-md-3 mb-3">
                        <a href="recompenses/" class="btn btn-outline-success w-100">
                            <i class="fas fa-award fa-2x mb-2"></i>
                            <br>Récompense
                        </a>
                    </div>
                    <div class="col-md-3 mb-3">
                        <a href="reports/" class="btn btn-outline-info w-100">
                            <i class="fas fa-chart-bar fa-2x mb-2"></i>
                            <br>Rapports disciplinaires
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.stats-card {
    transition: all 0.2s ease-in-out;
}

.stats-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15) !important;
}

.card {
    border: none;
    border-radius: 10px;
}

.card-header {
    border-radius: 10px 10px 0 0 !important;
    border: none;
}
</style>

<?php include '../../includes/footer.php'; ?>
