<?php
/**
 * Module Discipline - Liste des incidents
 * Application de gestion scolaire - République Démocratique du Congo
 */

require_once '../../../config/config.php';
require_once '../../../config/database.php';
require_once '../../../includes/functions.php';

// Vérifier l'authentification et les permissions
requireLogin();
if (!checkPermission('discipline')) {
    showMessage('error', 'Accès refusé à cette page.');
    redirectTo('../../../index.php');
}

$errors = [];

// Paramètres de filtrage et pagination
$search = trim($_GET['search'] ?? '');
$status_filter = $_GET['status'] ?? '';
$gravite_filter = $_GET['gravite'] ?? '';
$classe_filter = intval($_GET['classe'] ?? 0);
$date_debut = $_GET['date_debut'] ?? '';
$date_fin = $_GET['date_fin'] ?? '';
$page = max(1, intval($_GET['page'] ?? 1));
$per_page = 25;
$offset = ($page - 1) * $per_page;

// Construction de la requête avec filtres
$where_conditions = ["1=1"];
$params = [];

// Vérifier la structure de la table incidents
$columns = $database->query("DESCRIBE incidents")->fetchAll();
$column_names = array_column($columns, 'Field');
$has_rapporte_par = in_array('rapporte_par', $column_names);

if ($search) {
    $where_conditions[] = "(i.description LIKE ? OR CONCAT(e.nom, ' ', e.prenom) LIKE ? OR e.numero_matricule LIKE ?)";
    $search_param = "%$search%";
    $params = array_merge($params, [$search_param, $search_param, $search_param]);
}

if ($status_filter) {
    $where_conditions[] = "i.status = ?";
    $params[] = $status_filter;
}

if ($gravite_filter) {
    $where_conditions[] = "i.gravite = ?";
    $params[] = $gravite_filter;
}

if ($classe_filter > 0) {
    $where_conditions[] = "i.classe_id = ?";
    $params[] = $classe_filter;
}

if ($date_debut) {
    $where_conditions[] = "DATE(i.date_incident) >= ?";
    $params[] = $date_debut;
}

if ($date_fin) {
    $where_conditions[] = "DATE(i.date_incident) <= ?";
    $params[] = $date_fin;
}

$where_clause = implode(' AND ', $where_conditions);

// Traitement de l'export Excel
if (isset($_GET['export']) && $_GET['export'] === 'excel') {
    // Récupérer tous les incidents selon les filtres (sans pagination)
    try {
        if ($has_rapporte_par) {
            $export_sql = "SELECT i.*,
                                  e.numero_matricule, e.nom as eleve_nom, e.prenom as eleve_prenom,
                                  c.nom as classe_nom, c.niveau,
                                  p.nom as rapporte_par_nom, p.prenom as rapporte_par_prenom
                           FROM incidents i
                           JOIN eleves e ON i.eleve_id = e.id
                           LEFT JOIN inscriptions ins_exp ON e.id = ins_exp.eleve_id AND ins_exp.status = 'inscrit'
                           LEFT JOIN classes c ON ins_exp.classe_id = c.id
                           LEFT JOIN personnel p ON i.rapporte_par = p.id
                           WHERE $where_clause
                           ORDER BY i.date_incident DESC";
        } else {
            $export_sql = "SELECT i.*,
                                  e.numero_matricule, e.nom as eleve_nom, e.prenom as eleve_prenom,
                                  c.nom as classe_nom, c.niveau,
                                  'Personnel' as rapporte_par_nom, '' as rapporte_par_prenom
                           FROM incidents i
                           JOIN eleves e ON i.eleve_id = e.id
                           LEFT JOIN inscriptions ins_exp2 ON e.id = ins_exp2.eleve_id AND ins_exp2.status = 'inscrit'
                           LEFT JOIN classes c ON ins_exp2.classe_id = c.id
                           WHERE $where_clause
                           ORDER BY i.date_incident DESC";
        }

        $export_incidents = $database->query($export_sql, $params)->fetchAll();

        // Générer le fichier Excel
        header('Content-Type: application/vnd.ms-excel');
        header('Content-Disposition: attachment; filename="incidents_' . date('Y-m-d') . '.xls"');
        header('Pragma: no-cache');
        header('Expires: 0');

        echo "<table border='1'>";
        echo "<tr>";
        echo "<th>ID</th>";
        echo "<th>Date</th>";
        echo "<th>Élève</th>";
        echo "<th>Matricule</th>";
        echo "<th>Classe</th>";
        echo "<th>Description</th>";
        echo "<th>Lieu</th>";
        echo "<th>Gravité</th>";
        echo "<th>Statut</th>";
        echo "<th>Signalé par</th>";
        echo "<th>Témoins</th>";
        echo "</tr>";

        foreach ($export_incidents as $incident) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($incident['id']) . "</td>";
            echo "<td>" . htmlspecialchars(formatDateTime($incident['date_incident'], 'd/m/Y H:i')) . "</td>";
            echo "<td>" . htmlspecialchars($incident['eleve_nom'] . ' ' . $incident['eleve_prenom']) . "</td>";
            echo "<td>" . htmlspecialchars($incident['numero_matricule']) . "</td>";
            echo "<td>" . htmlspecialchars($incident['classe_nom'] ?? 'N/A') . "</td>";
            echo "<td>" . htmlspecialchars($incident['description']) . "</td>";
            echo "<td>" . htmlspecialchars($incident['lieu'] ?? '') . "</td>";
            echo "<td>" . htmlspecialchars(ucfirst(str_replace('_', ' ', $incident['gravite']))) . "</td>";
            echo "<td>" . htmlspecialchars(ucfirst(str_replace('_', ' ', $incident['status']))) . "</td>";
            echo "<td>" . htmlspecialchars($incident['rapporte_par_nom'] . ' ' . $incident['rapporte_par_prenom']) . "</td>";
            echo "<td>" . htmlspecialchars($incident['temoins'] ?? '') . "</td>";
            echo "</tr>";
        }
        echo "</table>";
        exit;

    } catch (Exception $e) {
        showMessage('error', 'Erreur lors de l\'export : ' . $e->getMessage());
    }
}

// Traitement des actions en lot
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_action'])) {
    $bulk_action = $_POST['bulk_action'] ?? '';
    $incident_ids = $_POST['incident_ids'] ?? [];
    
    if (!empty($incident_ids) && is_array($incident_ids)) {
        $incident_ids = array_map('intval', $incident_ids);
        
        try {
            $database->beginTransaction();
            
            switch ($bulk_action) {
                case 'change_status':
                    $new_status = $_POST['bulk_status'] ?? '';
                    if (in_array($new_status, ['nouveau', 'en_cours', 'resolu', 'archive'])) {
                        $placeholders = str_repeat('?,', count($incident_ids) - 1) . '?';
                        $database->execute(
                            "UPDATE incidents SET status = ?, updated_at = NOW() WHERE id IN ($placeholders)",
                            array_merge([$new_status], $incident_ids)
                        );
                        showMessage('success', count($incident_ids) . ' incident(s) mis à jour.');
                    }
                    break;
                    
                case 'archive':
                    $placeholders = str_repeat('?,', count($incident_ids) - 1) . '?';
                    $database->execute(
                        "UPDATE incidents SET status = 'archive', updated_at = NOW() WHERE id IN ($placeholders)",
                        $incident_ids
                    );
                    showMessage('success', count($incident_ids) . ' incident(s) archivé(s).');
                    break;
            }
            
            $database->commit();
            
        } catch (Exception $e) {
            $database->rollback();
            $errors[] = 'Erreur lors de l\'action en lot : ' . $e->getMessage();
        }
    }
}

// Récupérer les incidents avec pagination
try {
    if ($has_rapporte_par) {
        // Nouvelle structure
        $sql = "SELECT i.*, 
                       e.numero_matricule, e.nom as eleve_nom, e.prenom as eleve_prenom,
                       c.nom as classe_nom, c.niveau,
                       p.nom as rapporte_par_nom, p.prenom as rapporte_par_prenom,
                       DATEDIFF(NOW(), i.date_incident) as jours_depuis
                FROM incidents i
                JOIN eleves e ON i.eleve_id = e.id
                LEFT JOIN inscriptions ins ON e.id = ins.eleve_id AND ins.status = 'inscrit'
                LEFT JOIN classes c ON ins.classe_id = c.id
                LEFT JOIN personnel p ON i.rapporte_par = p.id
                WHERE $where_clause
                ORDER BY i.date_incident DESC, i.id DESC
                LIMIT $per_page OFFSET $offset";
    } else {
        // Ancienne structure
        $sql = "SELECT i.*, 
                       e.numero_matricule, e.nom as eleve_nom, e.prenom as eleve_prenom,
                       c.nom as classe_nom, c.niveau,
                       'Personnel' as rapporte_par_nom, '' as rapporte_par_prenom,
                       DATEDIFF(NOW(), i.date_incident) as jours_depuis
                FROM incidents i
                JOIN eleves e ON i.eleve_id = e.id
                LEFT JOIN inscriptions ins2 ON e.id = ins2.eleve_id AND ins2.status = 'inscrit'
                LEFT JOIN classes c ON ins2.classe_id = c.id
                WHERE $where_clause
                ORDER BY i.date_incident DESC, i.id DESC
                LIMIT $per_page OFFSET $offset";
    }
    
    $incidents = $database->query($sql, $params)->fetchAll();
    
    // Compter le total pour la pagination
    $total_sql = "SELECT COUNT(*) as total
                  FROM incidents i
                  JOIN eleves e ON i.eleve_id = e.id
                  LEFT JOIN inscriptions ins3 ON e.id = ins3.eleve_id AND ins3.status = 'inscrit'
                  LEFT JOIN classes c ON ins3.classe_id = c.id";
    
    if ($has_rapporte_par) {
        $total_sql .= " LEFT JOIN personnel p ON i.rapporte_par = p.id";
    }
    
    $total_sql .= " WHERE $where_clause";
    
    $total = $database->query($total_sql, $params)->fetch()['total'];
    $total_pages = ceil($total / $per_page);
    
} catch (Exception $e) {
    $incidents = [];
    $total = 0;
    $total_pages = 0;
    $errors[] = 'Erreur lors du chargement des incidents : ' . $e->getMessage();
}

// Récupérer les classes pour le filtre
try {
    $classes = $database->query(
        "SELECT id, nom, niveau FROM classes ORDER BY niveau, nom"
    )->fetchAll();
} catch (Exception $e) {
    $classes = [];
}

// Statistiques rapides
try {
    $stats = $database->query(
        "SELECT
            COUNT(*) as total,
            SUM(CASE WHEN i.status = 'nouveau' THEN 1 ELSE 0 END) as nouveaux,
            SUM(CASE WHEN i.status = 'en_cours' THEN 1 ELSE 0 END) as en_cours,
            SUM(CASE WHEN i.status = 'resolu' THEN 1 ELSE 0 END) as resolus,
            SUM(CASE WHEN i.gravite = 'grave' OR i.gravite = 'tres_grave' THEN 1 ELSE 0 END) as graves
         FROM incidents i
         JOIN eleves e ON i.eleve_id = e.id
         LEFT JOIN inscriptions ins4 ON e.id = ins4.eleve_id AND ins4.status = 'inscrit'
         LEFT JOIN classes c ON ins4.classe_id = c.id
         WHERE $where_clause",
        $params
    )->fetch();
} catch (Exception $e) {
    $stats = ['total' => 0, 'nouveaux' => 0, 'en_cours' => 0, 'resolus' => 0, 'graves' => 0];
}

$page_title = "Liste des incidents";
include '../../../includes/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">
        <i class="fas fa-exclamation-triangle me-2 text-warning"></i>
        Liste des incidents
        <span class="badge bg-secondary ms-2"><?php echo number_format($total); ?></span>
    </h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <div class="btn-group me-2">
            <a href="add.php" class="btn btn-primary">
                <i class="fas fa-plus me-1"></i>
                Signaler un incident
            </a>
        </div>
        <div class="btn-group me-2">
            <button type="button" class="btn btn-outline-success" onclick="exportData()">
                <i class="fas fa-file-excel me-1"></i>
                Exporter
            </button>
        </div>
        <div class="btn-group">
            <a href="../index.php" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-1"></i>
                Retour
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

<!-- Statistiques rapides -->
<div class="row mb-4">
    <div class="col-md-2">
        <div class="card text-center shadow-sm stats-card">
            <div class="card-body">
                <i class="fas fa-list fa-2x text-primary mb-2"></i>
                <h4><?php echo number_format($stats['total']); ?></h4>
                <p class="text-muted mb-0">Total</p>
            </div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card text-center shadow-sm stats-card">
            <div class="card-body">
                <i class="fas fa-bell fa-2x text-danger mb-2"></i>
                <h4><?php echo number_format($stats['nouveaux']); ?></h4>
                <p class="text-muted mb-0">Nouveaux</p>
            </div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card text-center shadow-sm stats-card">
            <div class="card-body">
                <i class="fas fa-clock fa-2x text-warning mb-2"></i>
                <h4><?php echo number_format($stats['en_cours']); ?></h4>
                <p class="text-muted mb-0">En cours</p>
            </div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card text-center shadow-sm stats-card">
            <div class="card-body">
                <i class="fas fa-check-circle fa-2x text-success mb-2"></i>
                <h4><?php echo number_format($stats['resolus']); ?></h4>
                <p class="text-muted mb-0">Résolus</p>
            </div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card text-center shadow-sm stats-card">
            <div class="card-body">
                <i class="fas fa-exclamation-circle fa-2x text-danger mb-2"></i>
                <h4><?php echo number_format($stats['graves']); ?></h4>
                <p class="text-muted mb-0">Graves</p>
            </div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card text-center shadow-sm stats-card">
            <div class="card-body">
                <i class="fas fa-percentage fa-2x text-info mb-2"></i>
                <h4><?php echo $stats['total'] > 0 ? round(($stats['resolus'] / $stats['total']) * 100) : 0; ?>%</h4>
                <p class="text-muted mb-0">Résolus</p>
            </div>
        </div>
    </div>
</div>

<!-- Filtres -->
<div class="card shadow-sm mb-4">
    <div class="card-header bg-light">
        <h6 class="mb-0">
            <i class="fas fa-filter me-2"></i>
            Filtres de recherche
        </h6>
    </div>
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-md-3">
                <label for="search" class="form-label">Recherche</label>
                <input type="text" class="form-control" id="search" name="search" 
                       value="<?php echo htmlspecialchars($search); ?>" 
                       placeholder="Description, élève, matricule...">
            </div>
            
            <div class="col-md-2">
                <label for="status" class="form-label">Statut</label>
                <select class="form-select" id="status" name="status">
                    <option value="">Tous les statuts</option>
                    <option value="nouveau" <?php echo ($status_filter === 'nouveau') ? 'selected' : ''; ?>>
                        🔴 Nouveau
                    </option>
                    <option value="en_cours" <?php echo ($status_filter === 'en_cours') ? 'selected' : ''; ?>>
                        🟡 En cours
                    </option>
                    <option value="resolu" <?php echo ($status_filter === 'resolu') ? 'selected' : ''; ?>>
                        🟢 Résolu
                    </option>
                    <option value="archive" <?php echo ($status_filter === 'archive') ? 'selected' : ''; ?>>
                        📁 Archivé
                    </option>
                </select>
            </div>
            
            <div class="col-md-2">
                <label for="gravite" class="form-label">Gravité</label>
                <select class="form-select" id="gravite" name="gravite">
                    <option value="">Toutes les gravités</option>
                    <option value="legere" <?php echo ($gravite_filter === 'legere') ? 'selected' : ''; ?>>
                        🟢 Légère
                    </option>
                    <option value="moyenne" <?php echo ($gravite_filter === 'moyenne') ? 'selected' : ''; ?>>
                        🟡 Moyenne
                    </option>
                    <option value="grave" <?php echo ($gravite_filter === 'grave') ? 'selected' : ''; ?>>
                        🟠 Grave
                    </option>
                    <option value="tres_grave" <?php echo ($gravite_filter === 'tres_grave') ? 'selected' : ''; ?>>
                        🔴 Très grave
                    </option>
                </select>
            </div>
            
            <div class="col-md-2">
                <label for="classe" class="form-label">Classe</label>
                <select class="form-select" id="classe" name="classe">
                    <option value="">Toutes les classes</option>
                    <?php foreach ($classes as $classe): ?>
                        <option value="<?php echo $classe['id']; ?>" 
                                <?php echo ($classe_filter == $classe['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($classe['nom'] . ' (' . ucfirst($classe['niveau']) . ')'); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="col-md-3">
                <label class="form-label">Période</label>
                <div class="row g-1">
                    <div class="col-6">
                        <input type="date" class="form-control form-control-sm" name="date_debut" 
                               value="<?php echo htmlspecialchars($date_debut); ?>" placeholder="Du">
                    </div>
                    <div class="col-6">
                        <input type="date" class="form-control form-control-sm" name="date_fin" 
                               value="<?php echo htmlspecialchars($date_fin); ?>" placeholder="Au">
                    </div>
                </div>
            </div>
            
            <div class="col-12">
                <button type="submit" class="btn btn-primary">
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

<!-- Actions en lot -->
<?php if (!empty($incidents)): ?>
<div class="card shadow-sm mb-4">
    <div class="card-body">
        <form method="POST" id="bulkForm">
            <div class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label for="bulk_action" class="form-label">Actions en lot</label>
                    <select class="form-select" id="bulk_action" name="bulk_action">
                        <option value="">Sélectionner une action...</option>
                        <option value="change_status">Changer le statut</option>
                        <option value="archive">Archiver</option>
                    </select>
                </div>
                
                <div class="col-md-3" id="bulk_status_section" style="display: none;">
                    <label for="bulk_status" class="form-label">Nouveau statut</label>
                    <select class="form-select" id="bulk_status" name="bulk_status">
                        <option value="nouveau">Nouveau</option>
                        <option value="en_cours">En cours</option>
                        <option value="resolu">Résolu</option>
                        <option value="archive">Archivé</option>
                    </select>
                </div>
                
                <div class="col-md-3">
                    <button type="submit" class="btn btn-warning" onclick="return confirmBulkAction()">
                        <i class="fas fa-cogs me-1"></i>
                        Appliquer
                    </button>
                </div>
                
                <div class="col-md-3 text-end">
                    <small class="text-muted">
                        <span id="selected-count">0</span> incident(s) sélectionné(s)
                    </small>
                </div>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<!-- Liste des incidents -->
<div class="card shadow-sm">
    <div class="card-body">
        <?php if (empty($incidents)): ?>
            <div class="text-center py-5">
                <i class="fas fa-search fa-3x text-muted mb-3"></i>
                <h5 class="text-muted">Aucun incident trouvé</h5>
                <p class="text-muted">
                    <?php if ($search || $status_filter || $gravite_filter || $classe_filter || $date_debut || $date_fin): ?>
                        Essayez de modifier vos critères de recherche.
                    <?php else: ?>
                        Aucun incident n'a été signalé pour le moment.
                    <?php endif; ?>
                </p>
                <?php if (!$search && !$status_filter && !$gravite_filter && !$classe_filter && !$date_debut && !$date_fin): ?>
                    <a href="add.php" class="btn btn-primary">
                        <i class="fas fa-plus me-1"></i>
                        Signaler le premier incident
                    </a>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover" id="incidentsTable">
                    <thead class="table-light">
                        <tr>
                            <th width="30">
                                <input type="checkbox" id="select-all" class="form-check-input">
                            </th>
                            <th>Date</th>
                            <th>Élève</th>
                            <th>Description</th>
                            <th>Gravité</th>
                            <th>Statut</th>
                            <th>Signalé par</th>
                            <th width="100">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($incidents as $incident): ?>
                            <tr>
                                <td>
                                    <input type="checkbox" name="incident_ids[]" value="<?php echo $incident['id']; ?>" 
                                           class="form-check-input incident-checkbox">
                                </td>
                                <td>
                                    <div>
                                        <strong><?php echo formatDate($incident['date_incident']); ?></strong>
                                        <br><small class="text-muted">
                                            <?php if ($incident['jours_depuis'] == 0): ?>
                                                Aujourd'hui
                                            <?php elseif ($incident['jours_depuis'] == 1): ?>
                                                Hier
                                            <?php else: ?>
                                                Il y a <?php echo $incident['jours_depuis']; ?> jour(s)
                                            <?php endif; ?>
                                        </small>
                                    </div>
                                </td>
                                <td>
                                    <div>
                                        <strong><?php echo htmlspecialchars($incident['eleve_nom'] . ' ' . $incident['eleve_prenom']); ?></strong>
                                        <br><small class="text-muted">
                                            <?php echo htmlspecialchars($incident['numero_matricule']); ?>
                                            <?php if ($incident['classe_nom']): ?>
                                                - <?php echo htmlspecialchars($incident['classe_nom']); ?>
                                            <?php endif; ?>
                                        </small>
                                    </div>
                                </td>
                                <td>
                                    <div class="incident-description">
                                        <?php echo htmlspecialchars(substr($incident['description'], 0, 80)); ?>
                                        <?php if (strlen($incident['description']) > 80): ?>...<?php endif; ?>
                                        <?php if (!empty($incident['lieu'])): ?>
                                            <br><small class="text-muted">
                                                <i class="fas fa-map-marker-alt me-1"></i>
                                                <?php echo htmlspecialchars($incident['lieu']); ?>
                                            </small>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge bg-<?php 
                                        echo match($incident['gravite']) {
                                            'legere' => 'success',
                                            'moyenne' => 'warning',
                                            'grave' => 'danger',
                                            'tres_grave' => 'dark',
                                            default => 'secondary'
                                        };
                                    ?>">
                                        <?php echo ucfirst(str_replace('_', ' ', $incident['gravite'])); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge bg-<?php 
                                        echo match($incident['status']) {
                                            'nouveau' => 'danger',
                                            'en_cours' => 'warning',
                                            'resolu' => 'success',
                                            'archive' => 'secondary',
                                            default => 'secondary'
                                        };
                                    ?>">
                                        <?php echo ucfirst(str_replace('_', ' ', $incident['status'])); ?>
                                    </span>
                                </td>
                                <td>
                                    <small>
                                        <?php if ($incident['rapporte_par_nom']): ?>
                                            <?php echo htmlspecialchars($incident['rapporte_par_nom'] . ' ' . $incident['rapporte_par_prenom']); ?>
                                        <?php else: ?>
                                            Personnel
                                        <?php endif; ?>
                                    </small>
                                </td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <a href="view.php?id=<?php echo $incident['id']; ?>" 
                                           class="btn btn-outline-primary" title="Voir détails">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="../sanctions/add.php?incident_id=<?php echo $incident['id']; ?>&eleve_id=<?php echo $incident['eleve_id']; ?>" 
                                           class="btn btn-outline-danger" title="Prononcer une sanction">
                                            <i class="fas fa-gavel"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
                <nav aria-label="Navigation des incidents" class="mt-4">
                    <ul class="pagination justify-content-center">
                        <?php if ($page > 1): ?>
                            <li class="page-item">
                                <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>">
                                    <i class="fas fa-chevron-left"></i>
                                </a>
                            </li>
                        <?php endif; ?>
                        
                        <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                            <li class="page-item <?php echo ($i === $page) ? 'active' : ''; ?>">
                                <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>">
                                    <?php echo $i; ?>
                                </a>
                            </li>
                        <?php endfor; ?>
                        
                        <?php if ($page < $total_pages): ?>
                            <li class="page-item">
                                <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>">
                                    <i class="fas fa-chevron-right"></i>
                                </a>
                            </li>
                        <?php endif; ?>
                    </ul>
                    
                    <div class="text-center text-muted">
                        Page <?php echo $page; ?> sur <?php echo $total_pages; ?> 
                        (<?php echo number_format($total); ?> incident<?php echo $total > 1 ? 's' : ''; ?> au total)
                    </div>
                </nav>
            <?php endif; ?>
        <?php endif; ?>
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

.incident-description {
    max-width: 300px;
}

@media print {
    .btn-toolbar, .card:first-child, .card:nth-child(2), .no-print {
        display: none !important;
    }
}
</style>

<script>
// Gestion de la sélection multiple
document.getElementById('select-all').addEventListener('change', function() {
    const checkboxes = document.querySelectorAll('.incident-checkbox');
    checkboxes.forEach(checkbox => {
        checkbox.checked = this.checked;
    });
    updateSelectedCount();
});

document.querySelectorAll('.incident-checkbox').forEach(checkbox => {
    checkbox.addEventListener('change', updateSelectedCount);
});

function updateSelectedCount() {
    const selected = document.querySelectorAll('.incident-checkbox:checked').length;
    document.getElementById('selected-count').textContent = selected;
    
    // Mettre à jour l'état du checkbox "Tout sélectionner"
    const selectAll = document.getElementById('select-all');
    const total = document.querySelectorAll('.incident-checkbox').length;
    selectAll.indeterminate = selected > 0 && selected < total;
    selectAll.checked = selected === total && total > 0;
}

// Gestion des actions en lot
document.getElementById('bulk_action').addEventListener('change', function() {
    const statusSection = document.getElementById('bulk_status_section');
    statusSection.style.display = this.value === 'change_status' ? 'block' : 'none';
});

function confirmBulkAction() {
    const selected = document.querySelectorAll('.incident-checkbox:checked').length;
    if (selected === 0) {
        alert('Veuillez sélectionner au moins un incident.');
        return false;
    }
    
    const action = document.getElementById('bulk_action').value;
    if (!action) {
        alert('Veuillez sélectionner une action.');
        return false;
    }
    
    let message = `Êtes-vous sûr de vouloir appliquer cette action à ${selected} incident(s) ?`;
    if (action === 'archive') {
        message = `Êtes-vous sûr de vouloir archiver ${selected} incident(s) ?`;
    }
    
    return confirm(message);
}

function exportData() {
    const params = new URLSearchParams(window.location.search);
    params.set('export', 'excel');
    window.location.href = 'index.php?' + params.toString();
}

// Initialiser le compteur
updateSelectedCount();
</script>

<?php include '../../../includes/footer.php'; ?>
