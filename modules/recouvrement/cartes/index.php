<?php
/**
 * Module Recouvrement - Gestion des Cartes
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

// Traitement des actions en lot
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_action'])) {
    $bulk_action = $_POST['bulk_action'] ?? '';
    $carte_ids = $_POST['carte_ids'] ?? [];
    
    if (!empty($carte_ids) && is_array($carte_ids)) {
        $carte_ids = array_map('intval', $carte_ids);
        
        try {
            $database->beginTransaction();
            
            switch ($bulk_action) {
                case 'activate':
                    $placeholders = str_repeat('?,', count($carte_ids) - 1) . '?';
                    $database->execute(
                        "UPDATE cartes_eleves SET status = 'active', updated_at = NOW() WHERE id IN ($placeholders)",
                        $carte_ids
                    );
                    showMessage('success', count($carte_ids) . ' carte(s) activée(s).');
                    break;
                    
                case 'deactivate':
                    $placeholders = str_repeat('?,', count($carte_ids) - 1) . '?';
                    $database->execute(
                        "UPDATE cartes_eleves SET status = 'inactive', updated_at = NOW() WHERE id IN ($placeholders)",
                        $carte_ids
                    );
                    showMessage('success', count($carte_ids) . ' carte(s) désactivée(s).');
                    break;
                    
                case 'mark_lost':
                    $placeholders = str_repeat('?,', count($carte_ids) - 1) . '?';
                    $database->execute(
                        "UPDATE cartes_eleves SET status = 'perdue', updated_at = NOW() WHERE id IN ($placeholders)",
                        $carte_ids
                    );
                    showMessage('success', count($carte_ids) . ' carte(s) marquée(s) comme perdue(s).');
                    break;
                    
                case 'delete':
                    $placeholders = str_repeat('?,', count($carte_ids) - 1) . '?';
                    $database->execute(
                        "DELETE FROM cartes_eleves WHERE id IN ($placeholders)",
                        $carte_ids
                    );
                    showMessage('success', count($carte_ids) . ' carte(s) supprimée(s).');
                    break;
            }
            
            $database->commit();
            
        } catch (Exception $e) {
            $database->rollback();
            $errors[] = 'Erreur lors de l\'action en lot : ' . $e->getMessage();
        }
    }
}

// Paramètres de filtrage et pagination
$search = trim($_GET['search'] ?? '');
$status_filter = $_GET['status'] ?? '';
$classe_filter = $_GET['classe'] ?? '';
$page = max(1, intval($_GET['page'] ?? 1));
$per_page = 25;
$offset = ($page - 1) * $per_page;

// Construction de la requête avec filtres
$where_conditions = ["1=1"];
$params = [];

if ($search) {
    $where_conditions[] = "(e.nom LIKE ? OR e.prenom LIKE ? OR e.numero_matricule LIKE ? OR c.numero_carte LIKE ?)";
    $search_param = "%$search%";
    $params = array_merge($params, [$search_param, $search_param, $search_param, $search_param]);
}

if ($status_filter) {
    $where_conditions[] = "c.status = ?";
    $params[] = $status_filter;
}

if ($classe_filter) {
    $where_conditions[] = "cl.id = ?";
    $params[] = $classe_filter;
}

$where_clause = implode(' AND ', $where_conditions);

// Récupérer les cartes avec pagination
try {
    $sql = "SELECT c.*, e.nom, e.prenom, e.numero_matricule, cl.nom as classe_nom,
                   DATEDIFF(NOW(), c.created_at) as jours_depuis_creation
            FROM cartes_eleves c
            JOIN eleves e ON c.eleve_id = e.id
            LEFT JOIN inscriptions i ON e.id = i.eleve_id AND i.status = 'inscrit'
            LEFT JOIN classes cl ON i.classe_id = cl.id
            WHERE $where_clause
            ORDER BY c.created_at DESC, c.id DESC
            LIMIT $per_page OFFSET $offset";
    
    $cartes = $database->query($sql, $params)->fetchAll();
    
    // Compter le total pour la pagination
    $total_sql = "SELECT COUNT(*) as total 
                  FROM cartes_eleves c
                  JOIN eleves e ON c.eleve_id = e.id
                  LEFT JOIN inscriptions i ON e.id = i.eleve_id AND i.status = 'inscrit'
                  LEFT JOIN classes cl ON i.classe_id = cl.id
                  WHERE $where_clause";
    
    $total = $database->query($total_sql, $params)->fetch()['total'];
    $total_pages = ceil($total / $per_page);
    
} catch (Exception $e) {
    $cartes = [];
    $total = 0;
    $total_pages = 0;
    $errors[] = 'Erreur lors du chargement des cartes : ' . $e->getMessage();
}

// Statistiques des cartes
try {
    $stats = $database->query(
        "SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as actives,
            SUM(CASE WHEN status = 'inactive' THEN 1 ELSE 0 END) as inactives,
            SUM(CASE WHEN status = 'perdue' THEN 1 ELSE 0 END) as perdues,
            SUM(CASE WHEN status = 'endommagee' THEN 1 ELSE 0 END) as endommagees
         FROM cartes_eleves"
    )->fetch();
} catch (Exception $e) {
    $stats = ['total' => 0, 'actives' => 0, 'inactives' => 0, 'perdues' => 0, 'endommagees' => 0];
}

// Récupérer les classes pour le filtre
try {
    $classes = $database->query(
        "SELECT id, nom FROM classes ORDER BY nom"
    )->fetchAll();
} catch (Exception $e) {
    $classes = [];
}

$page_title = "Gestion des Cartes";
include '../../../includes/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">
        <i class="fas fa-id-card me-2 text-primary"></i>
        Gestion des Cartes
        <span class="badge bg-secondary ms-2"><?php echo number_format($total ?? 0); ?></span>
    </h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <div class="btn-group me-2">
            <a href="generate.php" class="btn btn-success">
                <i class="fas fa-plus me-1"></i>
                Générer Cartes
            </a>
        </div>
        <div class="btn-group me-2">
            <a href="print.php" class="btn btn-info">
                <i class="fas fa-print me-1"></i>
                Imprimer
            </a>
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
    <div class="col-lg-3 col-md-6 mb-3">
        <div class="card bg-success text-white shadow-sm">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h6 class="card-title">Cartes Actives</h6>
                        <h3 class="mb-0"><?php echo number_format($stats['actives'] ?? 0); ?></h3>
                    </div>
                    <div class="align-self-center">
                        <i class="fas fa-check-circle fa-2x opacity-75"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-lg-3 col-md-6 mb-3">
        <div class="card bg-secondary text-white shadow-sm">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h6 class="card-title">Inactives</h6>
                        <h3 class="mb-0"><?php echo number_format($stats['inactives'] ?? 0); ?></h3>
                    </div>
                    <div class="align-self-center">
                        <i class="fas fa-pause-circle fa-2x opacity-75"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-lg-3 col-md-6 mb-3">
        <div class="card bg-warning text-white shadow-sm">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h6 class="card-title">Perdues</h6>
                        <h3 class="mb-0"><?php echo number_format($stats['perdues'] ?? 0); ?></h3>
                    </div>
                    <div class="align-self-center">
                        <i class="fas fa-exclamation-triangle fa-2x opacity-75"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-lg-3 col-md-6 mb-3">
        <div class="card bg-danger text-white shadow-sm">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h6 class="card-title">Endommagées</h6>
                        <h3 class="mb-0"><?php echo number_format($stats['endommagees'] ?? 0); ?></h3>
                    </div>
                    <div class="align-self-center">
                        <i class="fas fa-times-circle fa-2x opacity-75"></i>
                    </div>
                </div>
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
            <div class="col-md-4">
                <label for="search" class="form-label">Recherche</label>
                <input type="text" class="form-control" id="search" name="search" 
                       value="<?php echo htmlspecialchars($search); ?>" 
                       placeholder="Nom, matricule, numéro carte...">
            </div>
            
            <div class="col-md-3">
                <label for="status" class="form-label">Statut</label>
                <select class="form-select" id="status" name="status">
                    <option value="">Tous les statuts</option>
                    <option value="active" <?php echo ($status_filter === 'active') ? 'selected' : ''; ?>>
                        ✅ Active
                    </option>
                    <option value="inactive" <?php echo ($status_filter === 'inactive') ? 'selected' : ''; ?>>
                        ⏸️ Inactive
                    </option>
                    <option value="perdue" <?php echo ($status_filter === 'perdue') ? 'selected' : ''; ?>>
                        ⚠️ Perdue
                    </option>
                    <option value="endommagee" <?php echo ($status_filter === 'endommagee') ? 'selected' : ''; ?>>
                        ❌ Endommagée
                    </option>
                </select>
            </div>
            
            <div class="col-md-3">
                <label for="classe" class="form-label">Classe</label>
                <select class="form-select" id="classe" name="classe">
                    <option value="">Toutes les classes</option>
                    <?php foreach ($classes as $classe): ?>
                        <option value="<?php echo $classe['id']; ?>" 
                                <?php echo ($classe_filter == $classe['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($classe['nom']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="col-md-2">
                <label class="form-label">&nbsp;</label>
                <div class="d-grid gap-2">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search me-1"></i>
                        Filtrer
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Actions en lot -->
<?php if (!empty($cartes)): ?>
<div class="card shadow-sm mb-4">
    <div class="card-body">
        <form method="POST" id="bulkForm">
            <div class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label for="bulk_action" class="form-label">Actions en lot</label>
                    <select class="form-select" id="bulk_action" name="bulk_action">
                        <option value="">Sélectionner une action...</option>
                        <option value="activate">Activer</option>
                        <option value="deactivate">Désactiver</option>
                        <option value="mark_lost">Marquer comme perdue</option>
                        <option value="delete">Supprimer</option>
                    </select>
                </div>

                <div class="col-md-3">
                    <button type="submit" class="btn btn-warning" onclick="return confirmBulkAction()">
                        <i class="fas fa-cogs me-1"></i>
                        Appliquer
                    </button>
                </div>

                <div class="col-md-6 text-end">
                    <small class="text-muted">
                        <span id="selected-count">0</span> carte(s) sélectionnée(s)
                    </small>
                </div>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<!-- Liste des cartes -->
<div class="card shadow-sm">
    <div class="card-body">
        <?php if (empty($cartes)): ?>
            <div class="text-center py-5">
                <i class="fas fa-id-card fa-3x text-muted mb-3"></i>
                <h5 class="text-muted">Aucune carte trouvée</h5>
                <p class="text-muted">
                    <?php if ($search || $status_filter || $classe_filter): ?>
                        Essayez de modifier vos critères de recherche.
                    <?php else: ?>
                        Aucune carte n'a été générée pour le moment.
                    <?php endif; ?>
                </p>
                <a href="generate.php" class="btn btn-success">
                    <i class="fas fa-plus me-1"></i>
                    Générer des cartes
                </a>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover" id="cartesTable">
                    <thead class="table-light">
                        <tr>
                            <th width="30">
                                <input type="checkbox" id="select-all" class="form-check-input">
                            </th>
                            <th>Élève</th>
                            <th>Numéro Carte</th>
                            <th>Classe</th>
                            <th>Statut</th>
                            <th>Date Émission</th>
                            <th>Expiration</th>
                            <th width="120">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($cartes as $carte): ?>
                            <tr>
                                <td>
                                    <input type="checkbox" name="carte_ids[]" value="<?php echo $carte['id']; ?>"
                                           class="form-check-input carte-checkbox">
                                </td>
                                <td>
                                    <div>
                                        <strong><?php echo htmlspecialchars($carte['nom'] . ' ' . $carte['prenom']); ?></strong>
                                        <br><small class="text-muted">
                                            <i class="fas fa-id-badge me-1"></i>
                                            <?php echo htmlspecialchars($carte['numero_matricule']); ?>
                                        </small>
                                    </div>
                                </td>
                                <td>
                                    <code><?php echo htmlspecialchars($carte['numero_carte']); ?></code>
                                </td>
                                <td>
                                    <?php if ($carte['classe_nom']): ?>
                                        <span class="badge bg-info">
                                            <?php echo htmlspecialchars($carte['classe_nom']); ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-muted">Non assigné</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge bg-<?php
                                        echo match($carte['status']) {
                                            'active' => 'success',
                                            'inactive' => 'secondary',
                                            'perdue' => 'warning',
                                            'endommagee' => 'danger',
                                            default => 'secondary'
                                        };
                                    ?>">
                                        <?php
                                        echo match($carte['status']) {
                                            'active' => '✅ Active',
                                            'inactive' => '⏸️ Inactive',
                                            'perdue' => '⚠️ Perdue',
                                            'endommagee' => '❌ Endommagée',
                                            default => ucfirst($carte['status'])
                                        };
                                        ?>
                                    </span>
                                </td>
                                <td>
                                    <?php echo formatDateTime($carte['date_emission'], 'd/m/Y'); ?>
                                    <br><small class="text-muted">
                                        Il y a <?php echo $carte['jours_depuis_creation']; ?> jour(s)
                                    </small>
                                </td>
                                <td>
                                    <?php if ($carte['date_expiration']): ?>
                                        <?php
                                        $expiration = new DateTime($carte['date_expiration']);
                                        $now = new DateTime();
                                        $expired = $expiration < $now;
                                        ?>
                                        <span class="<?php echo $expired ? 'text-danger' : 'text-success'; ?>">
                                            <?php echo formatDateTime($carte['date_expiration'], 'd/m/Y'); ?>
                                        </span>
                                        <?php if ($expired): ?>
                                            <br><small class="text-danger">Expirée</small>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="text-muted">Aucune</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <a href="view.php?id=<?php echo $carte['id']; ?>"
                                           class="btn btn-outline-primary" title="Voir la carte">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="print.php?id=<?php echo $carte['id']; ?>"
                                           class="btn btn-outline-info" title="Imprimer">
                                            <i class="fas fa-print"></i>
                                        </a>
                                        <div class="btn-group btn-group-sm">
                                            <button type="button" class="btn btn-outline-secondary dropdown-toggle"
                                                    data-bs-toggle="dropdown" title="Plus d'actions">
                                                <i class="fas fa-ellipsis-v"></i>
                                            </button>
                                            <ul class="dropdown-menu">
                                                <?php if ($carte['status'] !== 'active'): ?>
                                                    <li><a class="dropdown-item" href="?action=activate&id=<?php echo $carte['id']; ?>">
                                                        <i class="fas fa-check me-2"></i>Activer
                                                    </a></li>
                                                <?php endif; ?>
                                                <?php if ($carte['status'] === 'active'): ?>
                                                    <li><a class="dropdown-item" href="?action=deactivate&id=<?php echo $carte['id']; ?>">
                                                        <i class="fas fa-pause me-2"></i>Désactiver
                                                    </a></li>
                                                <?php endif; ?>
                                                <li><a class="dropdown-item" href="?action=mark_lost&id=<?php echo $carte['id']; ?>">
                                                    <i class="fas fa-exclamation-triangle me-2"></i>Marquer perdue
                                                </a></li>
                                                <li><hr class="dropdown-divider"></li>
                                                <li><a class="dropdown-item text-danger" href="?action=delete&id=<?php echo $carte['id']; ?>"
                                                       onclick="return confirm('Supprimer cette carte ?')">
                                                    <i class="fas fa-trash me-2"></i>Supprimer
                                                </a></li>
                                            </ul>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
                <nav aria-label="Navigation des cartes" class="mt-4">
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
                        (<?php echo number_format($total ?? 0); ?> carte<?php echo ($total ?? 0) > 1 ? 's' : ''; ?> au total)
                    </div>
                </nav>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<style>
.opacity-75 {
    opacity: 0.75;
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
    const checkboxes = document.querySelectorAll('.carte-checkbox');
    checkboxes.forEach(checkbox => {
        checkbox.checked = this.checked;
    });
    updateSelectedCount();
});

document.querySelectorAll('.carte-checkbox').forEach(checkbox => {
    checkbox.addEventListener('change', updateSelectedCount);
});

function updateSelectedCount() {
    const selected = document.querySelectorAll('.carte-checkbox:checked').length;
    document.getElementById('selected-count').textContent = selected;

    // Mettre à jour l'état du checkbox "Tout sélectionner"
    const selectAll = document.getElementById('select-all');
    const total = document.querySelectorAll('.carte-checkbox').length;
    selectAll.indeterminate = selected > 0 && selected < total;
    selectAll.checked = selected === total && total > 0;
}

function confirmBulkAction() {
    const selected = document.querySelectorAll('.carte-checkbox:checked').length;
    if (selected === 0) {
        alert('Veuillez sélectionner au moins une carte.');
        return false;
    }

    const action = document.getElementById('bulk_action').value;
    if (!action) {
        alert('Veuillez sélectionner une action.');
        return false;
    }

    let message = `Êtes-vous sûr de vouloir appliquer cette action à ${selected} carte(s) ?`;
    if (action === 'delete') {
        message = `Êtes-vous sûr de vouloir supprimer définitivement ${selected} carte(s) ?`;
    }

    return confirm(message);
}

// Initialiser le compteur
updateSelectedCount();
</script>

<?php include '../../../includes/footer.php'; ?>
