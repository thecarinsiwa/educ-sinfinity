<?php
/**
 * Module Discipline - Liste des récompenses
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

// Vérifier si la table recompenses existe
$tables = $database->query("SHOW TABLES LIKE 'recompenses'")->fetch();
if (!$tables) {
    // Créer la table si elle n'existe pas
    try {
        $database->execute("
            CREATE TABLE IF NOT EXISTS recompenses (
                id INT PRIMARY KEY AUTO_INCREMENT,
                eleve_id INT NOT NULL,
                classe_id INT,
                type_recompense VARCHAR(100) NOT NULL,
                motif TEXT NOT NULL,
                date_recompense DATE NOT NULL,
                attribuee_par INT NOT NULL,
                valeur_points INT DEFAULT 0,
                description TEXT,
                parent_informe TINYINT(1) DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ");
    } catch (Exception $e) {
        $errors[] = 'Erreur lors de la création de la table : ' . $e->getMessage();
    }
}

// Traitement de l'export Excel
if (isset($_GET['export']) && $_GET['export'] === 'excel') {
    try {
        $export_sql = "SELECT r.*, 
                              e.numero_matricule, e.nom as eleve_nom, e.prenom as eleve_prenom,
                              c.nom as classe_nom, c.niveau,
                              p.nom as attribuee_par_nom, p.prenom as attribuee_par_prenom
                       FROM recompenses r
                       JOIN eleves e ON r.eleve_id = e.id
                       LEFT JOIN classes c ON r.classe_id = c.id
                       LEFT JOIN personnel p ON r.attribuee_par = p.id
                       ORDER BY r.date_recompense DESC";
        
        $export_recompenses = $database->query($export_sql)->fetchAll();
        
        header('Content-Type: application/vnd.ms-excel');
        header('Content-Disposition: attachment; filename="recompenses_' . date('Y-m-d') . '.xls"');
        header('Pragma: no-cache');
        header('Expires: 0');
        
        echo "<table border='1'>";
        echo "<tr>";
        echo "<th>ID</th><th>Date</th><th>Élève</th><th>Matricule</th><th>Classe</th>";
        echo "<th>Type</th><th>Motif</th><th>Points</th><th>Attribuée par</th>";
        echo "</tr>";
        
        foreach ($export_recompenses as $recompense) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($recompense['id']) . "</td>";
            echo "<td>" . htmlspecialchars(formatDate($recompense['date_recompense'])) . "</td>";
            echo "<td>" . htmlspecialchars($recompense['eleve_nom'] . ' ' . $recompense['eleve_prenom']) . "</td>";
            echo "<td>" . htmlspecialchars($recompense['numero_matricule']) . "</td>";
            echo "<td>" . htmlspecialchars($recompense['classe_nom'] ?? 'N/A') . "</td>";
            echo "<td>" . htmlspecialchars($recompense['type_recompense']) . "</td>";
            echo "<td>" . htmlspecialchars($recompense['motif']) . "</td>";
            echo "<td>" . htmlspecialchars($recompense['valeur_points']) . "</td>";
            echo "<td>" . htmlspecialchars($recompense['attribuee_par_nom'] . ' ' . $recompense['attribuee_par_prenom']) . "</td>";
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
    $where_conditions[] = "(r.motif LIKE ? OR r.type_recompense LIKE ? OR CONCAT(e.nom, ' ', e.prenom) LIKE ? OR e.numero_matricule LIKE ?)";
    $search_param = "%$search%";
    $params = array_merge($params, [$search_param, $search_param, $search_param, $search_param]);
}

if ($type_filter) {
    $where_conditions[] = "r.type_recompense = ?";
    $params[] = $type_filter;
}

if ($classe_filter > 0) {
    $where_conditions[] = "r.classe_id = ?";
    $params[] = $classe_filter;
}

if ($date_debut) {
    $where_conditions[] = "DATE(r.date_recompense) >= ?";
    $params[] = $date_debut;
}

if ($date_fin) {
    $where_conditions[] = "DATE(r.date_recompense) <= ?";
    $params[] = $date_fin;
}

$where_clause = implode(' AND ', $where_conditions);

// Récupérer les récompenses avec pagination
try {
    $sql = "SELECT r.*, 
                   e.numero_matricule, e.nom as eleve_nom, e.prenom as eleve_prenom,
                   c.nom as classe_nom, c.niveau,
                   p.nom as attribuee_par_nom, p.prenom as attribuee_par_prenom,
                   DATEDIFF(NOW(), r.date_recompense) as jours_depuis
            FROM recompenses r
            JOIN eleves e ON r.eleve_id = e.id
            LEFT JOIN classes c ON r.classe_id = c.id
            LEFT JOIN personnel p ON r.attribuee_par = p.id
            WHERE $where_clause
            ORDER BY r.date_recompense DESC, r.id DESC
            LIMIT $per_page OFFSET $offset";
    
    $recompenses = $database->query($sql, $params)->fetchAll();
    
    // Compter le total pour la pagination
    $total_sql = "SELECT COUNT(*) as total 
                  FROM recompenses r
                  JOIN eleves e ON r.eleve_id = e.id
                  LEFT JOIN classes c ON r.classe_id = c.id
                  LEFT JOIN personnel p ON r.attribuee_par = p.id
                  WHERE $where_clause";
    
    $total = $database->query($total_sql, $params)->fetch()['total'];
    $total_pages = ceil($total / $per_page);
    
} catch (Exception $e) {
    $recompenses = [];
    $total = 0;
    $total_pages = 0;
    $errors[] = 'Erreur lors du chargement des récompenses : ' . $e->getMessage();
}

// Récupérer les classes pour le filtre
try {
    $classes = $database->query(
        "SELECT id, nom, niveau FROM classes ORDER BY niveau, nom"
    )->fetchAll();
} catch (Exception $e) {
    $classes = [];
}

// Récupérer les types de récompenses uniques
try {
    $types_recompenses = $database->query(
        "SELECT DISTINCT type_recompense FROM recompenses ORDER BY type_recompense"
    )->fetchAll();
} catch (Exception $e) {
    $types_recompenses = [];
}

// Statistiques rapides
try {
    $stats = $database->query(
        "SELECT 
            COUNT(*) as total,
            SUM(valeur_points) as total_points,
            COUNT(DISTINCT eleve_id) as eleves_recompenses,
            AVG(valeur_points) as moyenne_points
         FROM recompenses r
         JOIN eleves e ON r.eleve_id = e.id
         WHERE $where_clause",
        $params
    )->fetch();
} catch (Exception $e) {
    $stats = ['total' => 0, 'total_points' => 0, 'eleves_recompenses' => 0, 'moyenne_points' => 0];
}

$page_title = "Liste des récompenses";
include '../../../includes/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">
        <i class="fas fa-award me-2 text-success"></i>
        Liste des récompenses
        <span class="badge bg-secondary ms-2"><?php echo number_format($total ?? 0); ?></span>
    </h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <div class="btn-group me-2">
            <a href="add.php" class="btn btn-success">
                <i class="fas fa-plus me-1"></i>
                Attribuer une récompense
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
                <i class="fas fa-award fa-2x text-success mb-2"></i>
                <h4><?php echo number_format($stats['total'] ?? 0); ?></h4>
                <p class="text-muted mb-0">Total récompenses</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-center shadow-sm stats-card">
            <div class="card-body">
                <i class="fas fa-star fa-2x text-warning mb-2"></i>
                <h4><?php echo number_format($stats['total_points'] ?? 0); ?></h4>
                <p class="text-muted mb-0">Points attribués</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-center shadow-sm stats-card">
            <div class="card-body">
                <i class="fas fa-users fa-2x text-info mb-2"></i>
                <h4><?php echo number_format($stats['eleves_recompenses'] ?? 0); ?></h4>
                <p class="text-muted mb-0">Élèves récompensés</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-center shadow-sm stats-card">
            <div class="card-body">
                <i class="fas fa-chart-line fa-2x text-primary mb-2"></i>
                <h4><?php echo number_format($stats['moyenne_points'] ?? 0, 1); ?></h4>
                <p class="text-muted mb-0">Points moyens</p>
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
                       placeholder="Motif, type, élève, matricule...">
            </div>
            
            <div class="col-md-2">
                <label for="type" class="form-label">Type</label>
                <select class="form-select" id="type" name="type">
                    <option value="">Tous les types</option>
                    <?php foreach ($types_recompenses as $type): ?>
                        <option value="<?php echo htmlspecialchars($type['type_recompense']); ?>" 
                                <?php echo ($type_filter === $type['type_recompense']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $type['type_recompense']))); ?>
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
            
            <div class="col-md-2">
                <label class="form-label">&nbsp;</label>
                <div class="d-grid">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search me-1"></i>
                        Filtrer
                    </button>
                </div>
            </div>
            
            <div class="col-12">
                <a href="?" class="btn btn-outline-secondary">
                    <i class="fas fa-undo me-1"></i>
                    Reset
                </a>
            </div>
        </form>
    </div>
</div>

<!-- Liste des récompenses -->
<div class="card shadow-sm">
    <div class="card-body">
        <?php if (empty($recompenses)): ?>
            <div class="text-center py-5">
                <i class="fas fa-award fa-3x text-muted mb-3"></i>
                <h5 class="text-muted">Aucune récompense trouvée</h5>
                <p class="text-muted">
                    <?php if ($search || $type_filter || $classe_filter || $date_debut || $date_fin): ?>
                        Essayez de modifier vos critères de recherche.
                    <?php else: ?>
                        Aucune récompense n'a été attribuée pour le moment.
                    <?php endif; ?>
                </p>
                <?php if (!$search && !$type_filter && !$classe_filter && !$date_debut && !$date_fin): ?>
                    <a href="add.php" class="btn btn-success">
                        <i class="fas fa-plus me-1"></i>
                        Attribuer la première récompense
                    </a>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover" id="recompensesTable">
                    <thead class="table-light">
                        <tr>
                            <th>Date</th>
                            <th>Élève</th>
                            <th>Type</th>
                            <th>Motif</th>
                            <th>Points</th>
                            <th>Attribuée par</th>
                            <th width="100">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recompenses as $recompense): ?>
                            <tr>
                                <td>
                                    <div>
                                        <strong><?php echo formatDate($recompense['date_recompense']); ?></strong>
                                        <br><small class="text-muted">
                                            <?php if ($recompense['jours_depuis'] == 0): ?>
                                                Aujourd'hui
                                            <?php elseif ($recompense['jours_depuis'] == 1): ?>
                                                Hier
                                            <?php else: ?>
                                                Il y a <?php echo $recompense['jours_depuis']; ?> jour(s)
                                            <?php endif; ?>
                                        </small>
                                    </div>
                                </td>
                                <td>
                                    <div>
                                        <strong><?php echo htmlspecialchars($recompense['eleve_nom'] . ' ' . $recompense['eleve_prenom']); ?></strong>
                                        <br><small class="text-muted">
                                            <?php echo htmlspecialchars($recompense['numero_matricule']); ?>
                                            <?php if ($recompense['classe_nom']): ?>
                                                - <?php echo htmlspecialchars($recompense['classe_nom']); ?>
                                            <?php endif; ?>
                                        </small>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge bg-success">
                                        <?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $recompense['type_recompense']))); ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="recompense-motif">
                                        <?php echo htmlspecialchars(substr($recompense['motif'], 0, 60)); ?>
                                        <?php if (strlen($recompense['motif']) > 60): ?>...<?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge bg-warning text-dark">
                                        <i class="fas fa-star me-1"></i>
                                        <?php echo $recompense['valeur_points']; ?>
                                    </span>
                                </td>
                                <td>
                                    <small>
                                        <?php echo htmlspecialchars($recompense['attribuee_par_nom'] . ' ' . $recompense['attribuee_par_prenom']); ?>
                                    </small>
                                </td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <a href="view.php?id=<?php echo $recompense['id']; ?>" 
                                           class="btn btn-outline-primary" title="Voir détails">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="../../students/view.php?id=<?php echo $recompense['eleve_id']; ?>" 
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
                <nav aria-label="Navigation des récompenses" class="mt-4">
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
                        (<?php echo number_format($total ?? 0); ?> récompense<?php echo ($total ?? 0) > 1 ? 's' : ''; ?> au total)
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

.recompense-motif {
    max-width: 250px;
}

@media print {
    .btn-toolbar, .card:first-child, .card:nth-child(2), .no-print {
        display: none !important;
    }
}
</style>

<script>
function exportData() {
    const params = new URLSearchParams(window.location.search);
    params.set('export', 'excel');
    window.location.href = 'index.php?' + params.toString();
}
</script>

<?php include '../../../includes/footer.php'; ?>
