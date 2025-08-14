<?php
/**
 * Module Discipline - Liste des sanctions
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

// Vérifier la structure de la table sanctions
$columns = $database->query("DESCRIBE sanctions")->fetchAll();
$column_names = array_column($columns, 'Field');
$has_type_sanction_id = in_array('type_sanction_id', $column_names);
$has_incident_id = in_array('incident_id', $column_names);
$has_date_debut = in_array('date_debut', $column_names);
$has_date_fin = in_array('date_fin', $column_names);

// Traitement des actions en lot
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_action'])) {
    $bulk_action = $_POST['bulk_action'] ?? '';
    $sanction_ids = $_POST['sanction_ids'] ?? [];
    
    if (!empty($sanction_ids) && is_array($sanction_ids)) {
        $sanction_ids = array_map('intval', $sanction_ids);
        
        try {
            $database->beginTransaction();
            
            switch ($bulk_action) {
                case 'change_status':
                    $new_status = $_POST['bulk_status'] ?? '';
                    $valid_statuses = $has_type_sanction_id ? ['active', 'terminee', 'suspendue', 'annulee'] : ['active', 'levee'];
                    
                    if (in_array($new_status, $valid_statuses)) {
                        $placeholders = str_repeat('?,', count($sanction_ids) - 1) . '?';
                        $database->execute(
                            "UPDATE sanctions SET status = ? WHERE id IN ($placeholders)",
                            array_merge([$new_status], $sanction_ids)
                        );
                        showMessage('success', count($sanction_ids) . ' sanction(s) mise(s) à jour.');
                    }
                    break;
                    
                case 'terminate':
                    $placeholders = str_repeat('?,', count($sanction_ids) - 1) . '?';
                    $new_status = $has_type_sanction_id ? 'terminee' : 'levee';
                    $database->execute(
                        "UPDATE sanctions SET status = ? WHERE id IN ($placeholders)",
                        array_merge([$new_status], $sanction_ids)
                    );
                    showMessage('success', count($sanction_ids) . ' sanction(s) terminée(s).');
                    break;
            }
            
            $database->commit();
            
        } catch (Exception $e) {
            $database->rollback();
            $errors[] = 'Erreur lors de l\'action en lot : ' . $e->getMessage();
        }
    }
}

// Traitement de l'export Excel
if (isset($_GET['export']) && $_GET['export'] === 'excel') {
    try {
        if ($has_type_sanction_id) {
            $export_sql = "SELECT s.*,
                                  e.numero_matricule, e.nom as eleve_nom, e.prenom as eleve_prenom,
                                  c.nom as classe_nom, c.niveau,
                                  ts.nom as type_nom,
                                  p.nom as prononcee_par_nom, p.prenom as prononcee_par_prenom
                           FROM sanctions s
                           JOIN eleves e ON s.eleve_id = e.id
                           LEFT JOIN inscriptions i ON e.id = i.eleve_id AND i.status = 'inscrit'
                           LEFT JOIN classes c ON i.classe_id = c.id
                           LEFT JOIN types_sanctions ts ON s.type_sanction_id = ts.id
                           LEFT JOIN personnel p ON s.prononcee_par = p.id
                           ORDER BY s.date_sanction DESC";
        } else {
            $export_sql = "SELECT s.*,
                                  e.numero_matricule, e.nom as eleve_nom, e.prenom as eleve_prenom,
                                  c.nom as classe_nom, c.niveau,
                                  s.type_sanction as type_nom,
                                  p.nom as prononcee_par_nom, p.prenom as prononcee_par_prenom
                           FROM sanctions s
                           JOIN eleves e ON s.eleve_id = e.id
                           LEFT JOIN inscriptions i ON e.id = i.eleve_id AND i.status = 'inscrit'
                           LEFT JOIN classes c ON i.classe_id = c.id
                           LEFT JOIN personnel p ON s.enseignant_id = p.id
                           ORDER BY s.date_sanction DESC";
        }
        
        $export_sanctions = $database->query($export_sql)->fetchAll();
        
        header('Content-Type: application/vnd.ms-excel');
        header('Content-Disposition: attachment; filename="sanctions_' . date('Y-m-d') . '.xls"');
        header('Pragma: no-cache');
        header('Expires: 0');
        
        echo "<table border='1'>";
        echo "<tr>";
        echo "<th>ID</th><th>Date</th><th>Élève</th><th>Matricule</th><th>Classe</th>";
        echo "<th>Type</th><th>Description</th><th>Statut</th><th>Prononcée par</th>";
        echo "</tr>";
        
        foreach ($export_sanctions as $sanction) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($sanction['id']) . "</td>";
            echo "<td>" . htmlspecialchars(formatDate($sanction['date_sanction'])) . "</td>";
            echo "<td>" . htmlspecialchars($sanction['eleve_nom'] . ' ' . $sanction['eleve_prenom']) . "</td>";
            echo "<td>" . htmlspecialchars($sanction['numero_matricule']) . "</td>";
            echo "<td>" . htmlspecialchars($sanction['classe_nom'] ?? 'N/A') . "</td>";
            echo "<td>" . htmlspecialchars($sanction['type_nom']) . "</td>";
            echo "<td>" . htmlspecialchars($sanction['description'] ?? $sanction['motif'] ?? '') . "</td>";
            echo "<td>" . htmlspecialchars(ucfirst($sanction['status'])) . "</td>";
            echo "<td>" . htmlspecialchars($sanction['prononcee_par_nom'] . ' ' . $sanction['prononcee_par_prenom']) . "</td>";
            echo "</tr>";
        }
        echo "</table>";
        exit;
        
    } catch (Exception $e) {
        showMessage('error', 'Erreur lors de l\'export : ' . $e->getMessage());
    }
}

// Paramètres de filtrage et pagination
$search = trim($_GET['search'] ?? '');
$status_filter = $_GET['status'] ?? '';
$type_filter = $_GET['type'] ?? '';
$classe_filter = intval($_GET['classe'] ?? 0);
$date_debut = $_GET['date_debut'] ?? '';
$date_fin = $_GET['date_fin'] ?? '';
$page = max(1, intval($_GET['page'] ?? 1));
$per_page = 25;
$offset = ($page - 1) * $per_page;

// Construction de la requête avec filtres
$where_conditions = ["1=1"];
$params = [];

if ($search) {
    if ($has_type_sanction_id) {
        $where_conditions[] = "(s.description LIKE ? OR CONCAT(e.nom, ' ', e.prenom) LIKE ? OR e.numero_matricule LIKE ? OR ts.nom LIKE ?)";
        $search_param = "%$search%";
        $params = array_merge($params, [$search_param, $search_param, $search_param, $search_param]);
    } else {
        $where_conditions[] = "(s.motif LIKE ? OR CONCAT(e.nom, ' ', e.prenom) LIKE ? OR e.numero_matricule LIKE ? OR s.type_sanction LIKE ?)";
        $search_param = "%$search%";
        $params = array_merge($params, [$search_param, $search_param, $search_param, $search_param]);
    }
}

if ($status_filter) {
    $where_conditions[] = "s.status = ?";
    $params[] = $status_filter;
}

if ($type_filter) {
    if ($has_type_sanction_id) {
        $where_conditions[] = "s.type_sanction_id = ?";
        $params[] = $type_filter;
    } else {
        $where_conditions[] = "s.type_sanction = ?";
        $params[] = $type_filter;
    }
}

if ($classe_filter > 0) {
    $where_conditions[] = "c.id = ?";
    $params[] = $classe_filter;
}

if ($date_debut) {
    $where_conditions[] = "DATE(s.date_sanction) >= ?";
    $params[] = $date_debut;
}

if ($date_fin) {
    $where_conditions[] = "DATE(s.date_sanction) <= ?";
    $params[] = $date_fin;
}

$where_clause = implode(' AND ', $where_conditions);

// Récupérer les sanctions avec pagination
try {
    if ($has_type_sanction_id) {
        // Nouvelle structure avec types_sanctions
        $sql = "SELECT s.*,
                       e.numero_matricule, e.nom as eleve_nom, e.prenom as eleve_prenom,
                       c.nom as classe_nom, c.niveau,
                       ts.nom as type_nom, ts.couleur,
                       p.nom as prononcee_par_nom, p.prenom as prononcee_par_prenom,
                       DATEDIFF(NOW(), s.date_sanction) as jours_depuis
                FROM sanctions s
                JOIN eleves e ON s.eleve_id = e.id
                LEFT JOIN inscriptions i ON e.id = i.eleve_id AND i.status = 'inscrit'
                LEFT JOIN classes c ON i.classe_id = c.id
                LEFT JOIN types_sanctions ts ON s.type_sanction_id = ts.id
                LEFT JOIN personnel p ON s.prononcee_par = p.id
                WHERE $where_clause
                ORDER BY s.date_sanction DESC, s.id DESC
                LIMIT $per_page OFFSET $offset";
    } else {
        // Ancienne structure
        $sql = "SELECT s.*,
                       e.numero_matricule, e.nom as eleve_nom, e.prenom as eleve_prenom,
                       c.nom as classe_nom, c.niveau,
                       s.type_sanction as type_nom, '#ffc107' as couleur,
                       p.nom as prononcee_par_nom, p.prenom as prononcee_par_prenom,
                       DATEDIFF(NOW(), s.date_sanction) as jours_depuis
                FROM sanctions s
                JOIN eleves e ON s.eleve_id = e.id
                LEFT JOIN inscriptions i ON e.id = i.eleve_id AND i.status = 'inscrit'
                LEFT JOIN classes c ON i.classe_id = c.id
                LEFT JOIN personnel p ON s.enseignant_id = p.id
                WHERE $where_clause
                ORDER BY s.date_sanction DESC, s.id DESC
                LIMIT $per_page OFFSET $offset";
    }
    
    $sanctions = $database->query($sql, $params)->fetchAll();
    
    // Compter le total pour la pagination
    $total_sql = "SELECT COUNT(*) as total
                  FROM sanctions s
                  JOIN eleves e ON s.eleve_id = e.id
                  LEFT JOIN inscriptions i ON e.id = i.eleve_id AND i.status = 'inscrit'
                  LEFT JOIN classes c ON i.classe_id = c.id";

    if ($has_type_sanction_id) {
        $total_sql .= " LEFT JOIN types_sanctions ts ON s.type_sanction_id = ts.id
                        LEFT JOIN personnel p ON s.prononcee_par = p.id";
    } else {
        $total_sql .= " LEFT JOIN personnel p ON s.enseignant_id = p.id";
    }

    $total_sql .= " WHERE $where_clause";
    
    $total = $database->query($total_sql, $params)->fetch()['total'];
    $total_pages = ceil($total / $per_page);
    
} catch (Exception $e) {
    $sanctions = [];
    $total = 0;
    $total_pages = 0;
    $errors[] = 'Erreur lors du chargement des sanctions : ' . $e->getMessage();
}

// Récupérer les classes pour le filtre
try {
    $classes = $database->query(
        "SELECT id, nom, niveau FROM classes ORDER BY niveau, nom"
    )->fetchAll();
} catch (Exception $e) {
    $classes = [];
}

// Récupérer les types de sanctions pour le filtre
try {
    if ($has_type_sanction_id) {
        $types_sanctions = $database->query(
            "SELECT id, nom FROM types_sanctions WHERE active = 1 ORDER BY nom"
        )->fetchAll();
    } else {
        $types_sanctions = [
            ['id' => 'avertissement', 'nom' => 'Avertissement'],
            ['id' => 'blame', 'nom' => 'Blâme'],
            ['id' => 'exclusion_temporaire', 'nom' => 'Exclusion temporaire'],
            ['id' => 'exclusion_definitive', 'nom' => 'Exclusion définitive'],
            ['id' => 'travaux_supplementaires', 'nom' => 'Travaux supplémentaires']
        ];
    }
} catch (Exception $e) {
    $types_sanctions = [];
}

// Statistiques rapides
try {
    $stats = $database->query(
        "SELECT
            COUNT(*) as total,
            SUM(CASE WHEN s.status = 'active' THEN 1 ELSE 0 END) as actives,
            SUM(CASE WHEN s.status IN ('terminee', 'levee') THEN 1 ELSE 0 END) as terminees,
            COUNT(DISTINCT s.eleve_id) as eleves_sanctionnes
         FROM sanctions s
         JOIN eleves e ON s.eleve_id = e.id
         LEFT JOIN inscriptions i ON e.id = i.eleve_id AND i.status = 'inscrit'
         LEFT JOIN classes c ON i.classe_id = c.id
         WHERE $where_clause",
        $params
    )->fetch();
} catch (Exception $e) {
    $stats = ['total' => 0, 'actives' => 0, 'terminees' => 0, 'eleves_sanctionnes' => 0];
}

$page_title = "Liste des sanctions";
include '../../../includes/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">
        <i class="fas fa-gavel me-2 text-danger"></i>
        Liste des sanctions
        <span class="badge bg-secondary ms-2"><?php echo number_format($total ?? 0); ?></span>
    </h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <div class="btn-group me-2">
            <a href="add.php" class="btn btn-danger">
                <i class="fas fa-plus me-1"></i>
                Prononcer une sanction
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
    <div class="col-md-3">
        <div class="card text-center shadow-sm stats-card">
            <div class="card-body">
                <i class="fas fa-gavel fa-2x text-danger mb-2"></i>
                <h4><?php echo number_format($stats['total'] ?? 0); ?></h4>
                <p class="text-muted mb-0">Total sanctions</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-center shadow-sm stats-card">
            <div class="card-body">
                <i class="fas fa-exclamation-circle fa-2x text-warning mb-2"></i>
                <h4><?php echo number_format($stats['actives'] ?? 0); ?></h4>
                <p class="text-muted mb-0">Actives</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-center shadow-sm stats-card">
            <div class="card-body">
                <i class="fas fa-check-circle fa-2x text-success mb-2"></i>
                <h4><?php echo number_format($stats['terminees'] ?? 0); ?></h4>
                <p class="text-muted mb-0">Terminées</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-center shadow-sm stats-card">
            <div class="card-body">
                <i class="fas fa-users fa-2x text-info mb-2"></i>
                <h4><?php echo number_format($stats['eleves_sanctionnes'] ?? 0); ?></h4>
                <p class="text-muted mb-0">Élèves sanctionnés</p>
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
                    <option value="active" <?php echo ($status_filter === 'active') ? 'selected' : ''; ?>>
                        🟡 Active
                    </option>
                    <?php if ($has_type_sanction_id): ?>
                        <option value="terminee" <?php echo ($status_filter === 'terminee') ? 'selected' : ''; ?>>
                            🟢 Terminée
                        </option>
                        <option value="suspendue" <?php echo ($status_filter === 'suspendue') ? 'selected' : ''; ?>>
                            🟠 Suspendue
                        </option>
                        <option value="annulee" <?php echo ($status_filter === 'annulee') ? 'selected' : ''; ?>>
                            🔴 Annulée
                        </option>
                    <?php else: ?>
                        <option value="levee" <?php echo ($status_filter === 'levee') ? 'selected' : ''; ?>>
                            🟢 Levée
                        </option>
                    <?php endif; ?>
                </select>
            </div>
            
            <div class="col-md-2">
                <label for="type" class="form-label">Type</label>
                <select class="form-select" id="type" name="type">
                    <option value="">Tous les types</option>
                    <?php foreach ($types_sanctions as $type): ?>
                        <option value="<?php echo htmlspecialchars($type['id']); ?>" 
                                <?php echo ($type_filter == $type['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($type['nom']); ?>
                        </option>
                    <?php endforeach; ?>
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
<?php if (!empty($sanctions)): ?>
<div class="card shadow-sm mb-4">
    <div class="card-body">
        <form method="POST" id="bulkForm">
            <div class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label for="bulk_action" class="form-label">Actions en lot</label>
                    <select class="form-select" id="bulk_action" name="bulk_action">
                        <option value="">Sélectionner une action...</option>
                        <option value="change_status">Changer le statut</option>
                        <option value="terminate">Terminer les sanctions</option>
                    </select>
                </div>
                
                <div class="col-md-3" id="bulk_status_section" style="display: none;">
                    <label for="bulk_status" class="form-label">Nouveau statut</label>
                    <select class="form-select" id="bulk_status" name="bulk_status">
                        <option value="active">Active</option>
                        <?php if ($has_type_sanction_id): ?>
                            <option value="terminee">Terminée</option>
                            <option value="suspendue">Suspendue</option>
                            <option value="annulee">Annulée</option>
                        <?php else: ?>
                            <option value="levee">Levée</option>
                        <?php endif; ?>
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
                        <span id="selected-count">0</span> sanction(s) sélectionnée(s)
                    </small>
                </div>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<!-- Liste des sanctions -->
<div class="card shadow-sm">
    <div class="card-body">
        <?php if (empty($sanctions)): ?>
            <div class="text-center py-5">
                <i class="fas fa-gavel fa-3x text-muted mb-3"></i>
                <h5 class="text-muted">Aucune sanction trouvée</h5>
                <p class="text-muted">
                    <?php if ($search || $status_filter || $type_filter || $classe_filter || $date_debut || $date_fin): ?>
                        Essayez de modifier vos critères de recherche.
                    <?php else: ?>
                        Aucune sanction n'a été prononcée pour le moment.
                    <?php endif; ?>
                </p>
                <?php if (!$search && !$status_filter && !$type_filter && !$classe_filter && !$date_debut && !$date_fin): ?>
                    <a href="add.php" class="btn btn-danger">
                        <i class="fas fa-plus me-1"></i>
                        Prononcer la première sanction
                    </a>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover" id="sanctionsTable">
                    <thead class="table-light">
                        <tr>
                            <th width="30">
                                <input type="checkbox" id="select-all" class="form-check-input">
                            </th>
                            <th>Date</th>
                            <th>Élève</th>
                            <th>Type</th>
                            <th>Description</th>
                            <th>Statut</th>
                            <th>Prononcée par</th>
                            <th width="100">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($sanctions as $sanction): ?>
                            <tr>
                                <td>
                                    <input type="checkbox" name="sanction_ids[]" value="<?php echo $sanction['id']; ?>" 
                                           class="form-check-input sanction-checkbox">
                                </td>
                                <td>
                                    <div>
                                        <strong><?php echo formatDate($sanction['date_sanction']); ?></strong>
                                        <br><small class="text-muted">
                                            <?php if ($sanction['jours_depuis'] == 0): ?>
                                                Aujourd'hui
                                            <?php elseif ($sanction['jours_depuis'] == 1): ?>
                                                Hier
                                            <?php else: ?>
                                                Il y a <?php echo $sanction['jours_depuis']; ?> jour(s)
                                            <?php endif; ?>
                                        </small>
                                    </div>
                                </td>
                                <td>
                                    <div>
                                        <strong><?php echo htmlspecialchars($sanction['eleve_nom'] . ' ' . $sanction['eleve_prenom']); ?></strong>
                                        <br><small class="text-muted">
                                            <?php echo htmlspecialchars($sanction['numero_matricule']); ?>
                                            <?php if ($sanction['classe_nom']): ?>
                                                - <?php echo htmlspecialchars($sanction['classe_nom']); ?>
                                            <?php endif; ?>
                                        </small>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge" style="background-color: <?php echo $sanction['couleur'] ?? '#6c757d'; ?>">
                                        <?php echo htmlspecialchars($sanction['type_nom']); ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="sanction-description">
                                        <?php 
                                        $description = $sanction['description'] ?? $sanction['motif'] ?? '';
                                        echo htmlspecialchars(substr($description, 0, 60)); 
                                        ?>
                                        <?php if (strlen($description) > 60): ?>...<?php endif; ?>
                                        
                                        <?php if ($has_date_debut && $sanction['date_debut']): ?>
                                            <br><small class="text-muted">
                                                <i class="fas fa-calendar me-1"></i>
                                                Du <?php echo formatDate($sanction['date_debut']); ?>
                                                <?php if ($has_date_fin && $sanction['date_fin']): ?>
                                                    au <?php echo formatDate($sanction['date_fin']); ?>
                                                <?php endif; ?>
                                            </small>
                                        <?php elseif ($sanction['duree_jours']): ?>
                                            <br><small class="text-muted">
                                                <i class="fas fa-clock me-1"></i>
                                                Durée : <?php echo $sanction['duree_jours']; ?> jour(s)
                                            </small>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge bg-<?php 
                                        echo match($sanction['status']) {
                                            'active' => 'warning',
                                            'terminee', 'levee' => 'success',
                                            'suspendue' => 'info',
                                            'annulee' => 'danger',
                                            default => 'secondary'
                                        };
                                    ?>">
                                        <?php echo ucfirst($sanction['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <small>
                                        <?php echo htmlspecialchars($sanction['prononcee_par_nom'] . ' ' . $sanction['prononcee_par_prenom']); ?>
                                    </small>
                                </td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <a href="view.php?id=<?php echo $sanction['id']; ?>" 
                                           class="btn btn-outline-primary" title="Voir détails">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="../../students/view.php?id=<?php echo $sanction['eleve_id']; ?>" 
                                           class="btn btn-outline-info" title="Voir dossier élève">
                                            <i class="fas fa-user"></i>
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
                <nav aria-label="Navigation des sanctions" class="mt-4">
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
                        (<?php echo number_format($total ?? 0); ?> sanction<?php echo ($total ?? 0) > 1 ? 's' : ''; ?> au total)
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

.sanction-description {
    max-width: 250px;
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
    const checkboxes = document.querySelectorAll('.sanction-checkbox');
    checkboxes.forEach(checkbox => {
        checkbox.checked = this.checked;
    });
    updateSelectedCount();
});

document.querySelectorAll('.sanction-checkbox').forEach(checkbox => {
    checkbox.addEventListener('change', updateSelectedCount);
});

function updateSelectedCount() {
    const selected = document.querySelectorAll('.sanction-checkbox:checked').length;
    document.getElementById('selected-count').textContent = selected;
    
    // Mettre à jour l'état du checkbox "Tout sélectionner"
    const selectAll = document.getElementById('select-all');
    const total = document.querySelectorAll('.sanction-checkbox').length;
    selectAll.indeterminate = selected > 0 && selected < total;
    selectAll.checked = selected === total && total > 0;
}

// Gestion des actions en lot
document.getElementById('bulk_action').addEventListener('change', function() {
    const statusSection = document.getElementById('bulk_status_section');
    statusSection.style.display = this.value === 'change_status' ? 'block' : 'none';
});

function confirmBulkAction() {
    const selected = document.querySelectorAll('.sanction-checkbox:checked').length;
    if (selected === 0) {
        alert('Veuillez sélectionner au moins une sanction.');
        return false;
    }
    
    const action = document.getElementById('bulk_action').value;
    if (!action) {
        alert('Veuillez sélectionner une action.');
        return false;
    }
    
    let message = `Êtes-vous sûr de vouloir appliquer cette action à ${selected} sanction(s) ?`;
    if (action === 'terminate') {
        message = `Êtes-vous sûr de vouloir terminer ${selected} sanction(s) ?`;
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
