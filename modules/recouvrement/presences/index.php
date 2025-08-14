<?php
/**
 * Module Recouvrement - Suivi des Présences
 * Application de gestion scolaire - République Démocratique du Congo
 */

require_once '../../../config/config.php';
require_once '../../../config/database.php';
require_once '../../../includes/functions.php';

// Vérifier l'authentification et les permissions
requireLogin();
if (!checkPermission('recouvrement') && !checkPermission('admin')) {
    showMessage('error', 'Accès refusé à cette page.');
    redirectTo('../../../index.php');
}

$errors = [];

// Paramètres de filtrage et pagination
$search = trim($_GET['search'] ?? '');
$type_filter = $_GET['type'] ?? '';
$classe_filter = $_GET['classe'] ?? '';
$date_debut = $_GET['date_debut'] ?? date('Y-m-d');
$date_fin = $_GET['date_fin'] ?? date('Y-m-d');
$page = max(1, intval($_GET['page'] ?? 1));
$per_page = 50;
$offset = ($page - 1) * $per_page;

// Construction de la requête avec filtres
$where_conditions = ["1=1"];
$params = [];

if ($search) {
    $where_conditions[] = "(e.nom LIKE ? OR e.prenom LIKE ? OR e.numero_matricule LIKE ?)";
    $search_param = "%$search%";
    $params = array_merge($params, [$search_param, $search_param, $search_param]);
}

if ($type_filter) {
    $where_conditions[] = "pq.type_scan = ?";
    $params[] = $type_filter;
}

if ($classe_filter) {
    $where_conditions[] = "cl.id = ?";
    $params[] = $classe_filter;
}

if ($date_debut) {
    $where_conditions[] = "DATE(pq.created_at) >= ?";
    $params[] = $date_debut;
}

if ($date_fin) {
    $where_conditions[] = "DATE(pq.created_at) <= ?";
    $params[] = $date_fin;
}

$where_clause = implode(' AND ', $where_conditions);

// Récupérer les présences avec pagination
try {
    $sql = "SELECT pq.*, e.nom, e.prenom, e.numero_matricule, cl.nom as classe_nom,
                   u.nom as scanne_par_nom, u.prenom as scanne_par_prenom,
                   c.numero_carte
            FROM presences_qr pq
            JOIN eleves e ON pq.eleve_id = e.id
            LEFT JOIN inscriptions i ON e.id = i.eleve_id AND i.status = 'inscrit'
            LEFT JOIN classes cl ON i.classe_id = cl.id
            LEFT JOIN users u ON pq.scanne_par = u.id
            LEFT JOIN cartes_eleves c ON pq.carte_id = c.id
            WHERE $where_clause
            ORDER BY pq.created_at DESC, pq.id DESC
            LIMIT $per_page OFFSET $offset";
    
    $presences = $database->query($sql, $params)->fetchAll();
    
    // Compter le total pour la pagination
    $total_sql = "SELECT COUNT(*) as total 
                  FROM presences_qr pq
                  JOIN eleves e ON pq.eleve_id = e.id
                  LEFT JOIN inscriptions i ON e.id = i.eleve_id AND i.status = 'inscrit'
                  LEFT JOIN classes cl ON i.classe_id = cl.id
                  WHERE $where_clause";
    
    $total = $database->query($total_sql, $params)->fetch()['total'];
    $total_pages = ceil($total / $per_page);
    
} catch (Exception $e) {
    $presences = [];
    $total = 0;
    $total_pages = 0;
    $errors[] = 'Erreur lors du chargement des présences : ' . $e->getMessage();
}

// Statistiques des présences
try {
    $stats = $database->query(
        "SELECT 
            COUNT(*) as total_scans,
            COUNT(DISTINCT eleve_id) as eleves_uniques,
            COUNT(CASE WHEN type_scan = 'entree' THEN 1 END) as entrees,
            COUNT(CASE WHEN type_scan = 'sortie' THEN 1 END) as sorties,
            COUNT(CASE WHEN DATE(created_at) = CURDATE() THEN 1 END) as scans_aujourd_hui,
            COUNT(CASE WHEN DATE(created_at) = CURDATE() AND type_scan = 'entree' THEN 1 END) as entrees_aujourd_hui
         FROM presences_qr pq
         WHERE DATE(created_at) BETWEEN ? AND ?",
        [$date_debut, $date_fin]
    )->fetch();
} catch (Exception $e) {
    $stats = ['total_scans' => 0, 'eleves_uniques' => 0, 'entrees' => 0, 'sorties' => 0, 'scans_aujourd_hui' => 0, 'entrees_aujourd_hui' => 0];
}

// Récupérer les classes pour le filtre
try {
    $classes = $database->query(
        "SELECT id, nom FROM classes ORDER BY nom"
    )->fetchAll();
} catch (Exception $e) {
    $classes = [];
}

$page_title = "Suivi des Présences";
include '../../../includes/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">
        <i class="fas fa-user-check me-2 text-info"></i>
        Suivi des Présences
        <span class="badge bg-secondary ms-2"><?php echo number_format($total ?? 0); ?></span>
    </h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <div class="btn-group me-2">
            <a href="../scan-qr.php" class="btn btn-primary">
                <i class="fas fa-qrcode me-1"></i>
                Scanner QR
            </a>
        </div>
        <div class="btn-group me-2">
            <button type="button" class="btn btn-success" onclick="exportData()">
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

<!-- Statistiques -->
<div class="row mb-4">
    <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
        <div class="card bg-primary text-white shadow-sm">
            <div class="card-body text-center">
                <h4 class="mb-1"><?php echo number_format($stats['scans_aujourd_hui'] ?? 0); ?></h4>
                <small>Scans Aujourd'hui</small>
            </div>
        </div>
    </div>
    
    <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
        <div class="card bg-success text-white shadow-sm">
            <div class="card-body text-center">
                <h4 class="mb-1"><?php echo number_format($stats['entrees_aujourd_hui'] ?? 0); ?></h4>
                <small>Entrées Aujourd'hui</small>
            </div>
        </div>
    </div>
    
    <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
        <div class="card bg-info text-white shadow-sm">
            <div class="card-body text-center">
                <h4 class="mb-1"><?php echo number_format($stats['eleves_uniques'] ?? 0); ?></h4>
                <small>Élèves Uniques</small>
            </div>
        </div>
    </div>
    
    <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
        <div class="card bg-warning text-white shadow-sm">
            <div class="card-body text-center">
                <h4 class="mb-1"><?php echo number_format($stats['entrees'] ?? 0); ?></h4>
                <small>Total Entrées</small>
            </div>
        </div>
    </div>
    
    <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
        <div class="card bg-secondary text-white shadow-sm">
            <div class="card-body text-center">
                <h4 class="mb-1"><?php echo number_format($stats['sorties'] ?? 0); ?></h4>
                <small>Total Sorties</small>
            </div>
        </div>
    </div>
    
    <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
        <div class="card bg-dark text-white shadow-sm">
            <div class="card-body text-center">
                <h4 class="mb-1"><?php echo number_format($stats['total_scans'] ?? 0); ?></h4>
                <small>Total Scans</small>
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
                       placeholder="Nom, matricule...">
            </div>
            
            <div class="col-md-2">
                <label for="type" class="form-label">Type</label>
                <select class="form-select" id="type" name="type">
                    <option value="">Tous</option>
                    <option value="entree" <?php echo ($type_filter === 'entree') ? 'selected' : ''; ?>>
                        🚪 Entrée
                    </option>
                    <option value="sortie" <?php echo ($type_filter === 'sortie') ? 'selected' : ''; ?>>
                        🚶 Sortie
                    </option>
                </select>
            </div>
            
            <div class="col-md-2">
                <label for="classe" class="form-label">Classe</label>
                <select class="form-select" id="classe" name="classe">
                    <option value="">Toutes</option>
                    <?php foreach ($classes as $classe): ?>
                        <option value="<?php echo $classe['id']; ?>" 
                                <?php echo ($classe_filter == $classe['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($classe['nom']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="col-md-2">
                <label for="date_debut" class="form-label">Du</label>
                <input type="date" class="form-control" id="date_debut" name="date_debut" 
                       value="<?php echo htmlspecialchars($date_debut); ?>">
            </div>
            
            <div class="col-md-2">
                <label for="date_fin" class="form-label">Au</label>
                <input type="date" class="form-control" id="date_fin" name="date_fin" 
                       value="<?php echo htmlspecialchars($date_fin); ?>">
            </div>
            
            <div class="col-md-1">
                <label class="form-label">&nbsp;</label>
                <div class="d-grid">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search"></i>
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Liste des présences -->
<div class="card shadow-sm">
    <div class="card-body">
        <?php if (empty($presences)): ?>
            <div class="text-center py-5">
                <i class="fas fa-user-check fa-3x text-muted mb-3"></i>
                <h5 class="text-muted">Aucune présence trouvée</h5>
                <p class="text-muted">
                    <?php if ($search || $type_filter || $classe_filter): ?>
                        Essayez de modifier vos critères de recherche.
                    <?php else: ?>
                        Aucune présence enregistrée pour cette période.
                    <?php endif; ?>
                </p>
                <a href="../scan-qr.php" class="btn btn-primary">
                    <i class="fas fa-qrcode me-1"></i>
                    Commencer à scanner
                </a>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover" id="presencesTable">
                    <thead class="table-light">
                        <tr>
                            <th>Date/Heure</th>
                            <th>Élève</th>
                            <th>Classe</th>
                            <th>Type</th>
                            <th>Lieu</th>
                            <th>Carte</th>
                            <th>Scanné par</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($presences as $presence): ?>
                            <tr>
                                <td>
                                    <div>
                                        <strong><?php echo formatDateTime($presence['created_at'], 'd/m/Y'); ?></strong>
                                        <br><small class="text-muted">
                                            <?php echo formatDateTime($presence['created_at'], 'H:i:s'); ?>
                                        </small>
                                    </div>
                                </td>
                                <td>
                                    <div>
                                        <strong><?php echo htmlspecialchars($presence['nom'] . ' ' . $presence['prenom']); ?></strong>
                                        <br><small class="text-muted">
                                            <i class="fas fa-id-badge me-1"></i>
                                            <?php echo htmlspecialchars($presence['numero_matricule']); ?>
                                        </small>
                                    </div>
                                </td>
                                <td>
                                    <?php if ($presence['classe_nom']): ?>
                                        <span class="badge bg-info">
                                            <?php echo htmlspecialchars($presence['classe_nom']); ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-muted">Non assigné</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge bg-<?php echo ($presence['type_scan'] === 'entree') ? 'success' : 'warning'; ?> fs-6">
                                        <?php if ($presence['type_scan'] === 'entree'): ?>
                                            <i class="fas fa-sign-in-alt me-1"></i>Entrée
                                        <?php else: ?>
                                            <i class="fas fa-sign-out-alt me-1"></i>Sortie
                                        <?php endif; ?>
                                    </span>
                                </td>
                                <td>
                                    <small class="text-muted">
                                        <i class="fas fa-map-marker-alt me-1"></i>
                                        <?php echo htmlspecialchars($presence['lieu_scan'] ?: 'Non spécifié'); ?>
                                    </small>
                                </td>
                                <td>
                                    <?php if ($presence['numero_carte']): ?>
                                        <code class="small"><?php echo htmlspecialchars($presence['numero_carte']); ?></code>
                                    <?php else: ?>
                                        <span class="text-muted">N/A</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($presence['scanne_par_nom']): ?>
                                        <small class="text-muted">
                                            <i class="fas fa-user me-1"></i>
                                            <?php echo htmlspecialchars($presence['scanne_par_nom'] . ' ' . $presence['scanne_par_prenom']); ?>
                                        </small>
                                    <?php else: ?>
                                        <span class="text-muted">Système</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
                <nav aria-label="Navigation des présences" class="mt-4">
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
                        (<?php echo number_format($total ?? 0); ?> présence<?php echo ($total ?? 0) > 1 ? 's' : ''; ?> au total)
                    </div>
                </nav>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<script>
function exportData() {
    const params = new URLSearchParams(window.location.search);
    params.set('export', 'excel');
    window.location.href = 'index.php?' + params.toString();
}
</script>

<?php include '../../../includes/footer.php'; ?>
