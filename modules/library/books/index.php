<?php
/**
 * Module Bibliothèque - Gestion des livres
 * Application de gestion scolaire - République Démocratique du Congo
 */

require_once '../../../config/config.php';
require_once '../../../config/database.php';
require_once '../../../includes/functions.php';

// Vérifier l'authentification et les permissions
requireLogin();
if (!checkPermission('library') && !checkPermission('library_view')) {
    showMessage('error', 'Accès refusé à ce module.');
    redirectTo('../index.php');
}

// Traitement des actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && checkPermission('library')) {
    try {
        $action = $_POST['action'] ?? '';
        
        switch ($action) {
            case 'update_status':
                $livre_id = intval($_POST['livre_id']);
                $new_status = $_POST['status'] ?? '';
                
                $valid_statuses = ['disponible', 'emprunte', 'reserve', 'perdu', 'retire'];
                if (!in_array($new_status, $valid_statuses)) {
                    throw new Exception('Statut invalide.');
                }
                
                $database->execute(
                    "UPDATE livres SET status = ?, updated_at = NOW() WHERE id = ?",
                    [$new_status, $livre_id]
                );
                
                showMessage('success', 'Statut du livre mis à jour avec succès.');
                break;
                
            case 'bulk_update':
                $livre_ids = $_POST['livre_ids'] ?? [];
                $bulk_action = $_POST['bulk_action'] ?? '';
                
                if (empty($livre_ids)) {
                    throw new Exception('Aucun livre sélectionné.');
                }
                
                switch ($bulk_action) {
                    case 'change_status':
                        $bulk_status = $_POST['bulk_status'] ?? '';
                        if (!in_array($bulk_status, $valid_statuses)) {
                            throw new Exception('Statut invalide.');
                        }
                        
                        $placeholders = str_repeat('?,', count($livre_ids) - 1) . '?';
                        $database->execute(
                            "UPDATE livres SET status = ?, updated_at = NOW() WHERE id IN ($placeholders)",
                            array_merge([$bulk_status], $livre_ids)
                        );
                        
                        showMessage('success', count($livre_ids) . ' livre(s) mis à jour.');
                        break;
                        
                    case 'change_category':
                        $bulk_category = intval($_POST['bulk_category']);
                        $placeholders = str_repeat('?,', count($livre_ids) - 1) . '?';
                        $database->execute(
                            "UPDATE livres SET categorie_id = ?, updated_at = NOW() WHERE id IN ($placeholders)",
                            array_merge([$bulk_category], $livre_ids)
                        );
                        
                        showMessage('success', count($livre_ids) . ' livre(s) mis à jour.');
                        break;
                }
                break;
        }
    } catch (Exception $e) {
        showMessage('error', 'Erreur : ' . $e->getMessage());
    }
}

// Paramètres de pagination et filtres
$page = intval($_GET['page'] ?? 1);
$per_page = 20;
$offset = ($page - 1) * $per_page;

$search = trim($_GET['search'] ?? '');
$category_filter = intval($_GET['category'] ?? 0);
$status_filter = $_GET['status'] ?? '';
$etat_filter = $_GET['etat'] ?? '';

// Construction de la requête
$where_conditions = ["1=1"];
$params = [];

if ($search) {
    $where_conditions[] = "(l.titre LIKE ? OR l.auteur LIKE ? OR l.isbn LIKE ? OR l.editeur LIKE ?)";
    $search_param = "%$search%";
    $params = array_merge($params, [$search_param, $search_param, $search_param, $search_param]);
}

if ($category_filter) {
    $where_conditions[] = "l.categorie_id = ?";
    $params[] = $category_filter;
}

if ($status_filter) {
    $where_conditions[] = "l.status = ?";
    $params[] = $status_filter;
}

if ($etat_filter) {
    $where_conditions[] = "l.etat = ?";
    $params[] = $etat_filter;
}

$where_clause = implode(' AND ', $where_conditions);

// Récupérer les livres
try {
    $livres = $database->query(
        "SELECT l.*, cl.nom as categorie_nom, cl.couleur as categorie_couleur,
                COUNT(el.id) as nb_emprunts_total,
                COUNT(CASE WHEN el.status = 'en_cours' THEN 1 END) as nb_emprunts_actifs
         FROM livres l
         LEFT JOIN categories_livres cl ON l.categorie_id = cl.id
         LEFT JOIN emprunts_livres el ON l.id = el.livre_id
         WHERE $where_clause
         GROUP BY l.id
         ORDER BY l.titre
         LIMIT $per_page OFFSET $offset",
        $params
    )->fetchAll();

    // Compter le total pour la pagination
    $total_livres = $database->query(
        "SELECT COUNT(DISTINCT l.id) as total FROM livres l WHERE $where_clause",
        $params
    )->fetch()['total'];

} catch (Exception $e) {
    $livres = [];
    $total_livres = 0;
    showMessage('error', 'Erreur lors du chargement : ' . $e->getMessage());
}

// Récupérer les catégories pour les filtres
try {
    $categories = $database->query("SELECT * FROM categories_livres ORDER BY nom")->fetchAll();
} catch (Exception $e) {
    $categories = [];
}

// Statistiques
try {
    $stats = $database->query(
        "SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN status = 'disponible' THEN 1 ELSE 0 END) as disponibles,
            SUM(CASE WHEN status = 'emprunte' THEN 1 ELSE 0 END) as empruntes,
            SUM(CASE WHEN status = 'reserve' THEN 1 ELSE 0 END) as reserves,
            SUM(CASE WHEN status = 'perdu' THEN 1 ELSE 0 END) as perdus,
            SUM(CASE WHEN status = 'retire' THEN 1 ELSE 0 END) as retires
         FROM livres"
    )->fetch();
} catch (Exception $e) {
    $stats = [
        'total' => 0, 'disponibles' => 0, 'empruntes' => 0,
        'reserves' => 0, 'perdus' => 0, 'retires' => 0
    ];
}

$total_pages = ceil($total_livres / $per_page);

$page_title = "Catalogue des Livres";
include '../../../includes/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">
        <i class="fas fa-book me-2"></i>
        Catalogue des Livres
    </h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <div class="btn-group me-2">
            <a href="../" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-1"></i>
                Retour à la bibliothèque
            </a>
        </div>
        <?php if (checkPermission('library')): ?>
            <div class="btn-group me-2">
                <a href="add.php" class="btn btn-primary">
                    <i class="fas fa-plus me-1"></i>
                    Ajouter un livre
                </a>
            </div>
            <div class="btn-group me-2">
                <a href="categories.php" class="btn btn-outline-secondary">
                    <i class="fas fa-tags me-1"></i>
                    Gérer les catégories
                </a>
            </div>
            <div class="btn-group me-2">
                <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#bulkActionModal">
                    <i class="fas fa-tasks me-1"></i>
                    Actions en lot
                </button>
            </div>
        <?php endif; ?>
        <div class="btn-group">
            <button type="button" class="btn btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">
                <i class="fas fa-download me-1"></i>
                Export
            </button>
            <ul class="dropdown-menu">
                <?php
                // Construire l'URL de base pour les exports avec les filtres actuels
                $export_base_url = 'export.php?';
                if ($search) $export_base_url .= 'search=' . urlencode($search) . '&';
                if ($category_filter) $export_base_url .= 'category=' . $category_filter . '&';
                if ($status_filter) $export_base_url .= 'status=' . urlencode($status_filter) . '&';
                if ($etat_filter) $export_base_url .= 'etat=' . urlencode($etat_filter) . '&';
                ?>
                <li><a class="dropdown-item" href="<?php echo $export_base_url; ?>format=csv">
                    <i class="fas fa-file-csv me-2"></i>Export CSV
                </a></li>
                <li><a class="dropdown-item" href="<?php echo $export_base_url; ?>format=excel">
                    <i class="fas fa-file-excel me-2"></i>Export Excel
                </a></li>
                <li><a class="dropdown-item" href="<?php echo $export_base_url; ?>format=pdf">
                    <i class="fas fa-file-pdf me-2"></i>Export PDF
                </a></li>
            </ul>
        </div>
    </div>
</div>

<!-- Statistiques -->
<div class="row mb-4">
    <div class="col-md-2">
        <div class="card text-center">
            <div class="card-body">
                <i class="fas fa-book fa-2x text-primary mb-2"></i>
                <h5 class="card-title"><?php echo number_format($stats['total'] ?? 0); ?></h5>
                <p class="card-text text-muted">Total</p>
            </div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card text-center">
            <div class="card-body">
                <i class="fas fa-check-circle fa-2x text-success mb-2"></i>
                <h5 class="card-title"><?php echo number_format($stats['disponibles'] ?? 0); ?></h5>
                <p class="card-text text-muted">Disponibles</p>
            </div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card text-center">
            <div class="card-body">
                <i class="fas fa-hand-holding fa-2x text-info mb-2"></i>
                <h5 class="card-title"><?php echo number_format($stats['empruntes'] ?? 0); ?></h5>
                <p class="card-text text-muted">Empruntés</p>
            </div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card text-center">
            <div class="card-body">
                <i class="fas fa-bookmark fa-2x text-warning mb-2"></i>
                <h5 class="card-title"><?php echo number_format($stats['reserves'] ?? 0); ?></h5>
                <p class="card-text text-muted">Réservés</p>
            </div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card text-center">
            <div class="card-body">
                <i class="fas fa-exclamation-triangle fa-2x text-danger mb-2"></i>
                <h5 class="card-title"><?php echo number_format($stats['perdus'] ?? 0); ?></h5>
                <p class="card-text text-muted">Perdus</p>
            </div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card text-center">
            <div class="card-body">
                <i class="fas fa-archive fa-2x text-secondary mb-2"></i>
                <h5 class="card-title"><?php echo number_format($stats['retires'] ?? 0); ?></h5>
                <p class="card-text text-muted">Retirés</p>
            </div>
        </div>
    </div>
</div>

<!-- Filtres -->
<div class="card mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-md-3">
                <label for="search" class="form-label">Recherche</label>
                <input type="text" class="form-control" id="search" name="search" 
                       value="<?php echo htmlspecialchars($search); ?>" 
                       placeholder="Titre, auteur, ISBN...">
            </div>
            <div class="col-md-2">
                <label for="category" class="form-label">Catégorie</label>
                <select class="form-select" id="category" name="category">
                    <option value="">Toutes</option>
                    <?php foreach ($categories as $categorie): ?>
                        <option value="<?php echo $categorie['id']; ?>" 
                                <?php echo $category_filter === $categorie['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($categorie['nom']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label for="status" class="form-label">Statut</label>
                <select class="form-select" id="status" name="status">
                    <option value="">Tous</option>
                    <option value="disponible" <?php echo $status_filter === 'disponible' ? 'selected' : ''; ?>>Disponible</option>
                    <option value="emprunte" <?php echo $status_filter === 'emprunte' ? 'selected' : ''; ?>>Emprunté</option>
                    <option value="reserve" <?php echo $status_filter === 'reserve' ? 'selected' : ''; ?>>Réservé</option>
                    <option value="perdu" <?php echo $status_filter === 'perdu' ? 'selected' : ''; ?>>Perdu</option>
                    <option value="retire" <?php echo $status_filter === 'retire' ? 'selected' : ''; ?>>Retiré</option>
                </select>
            </div>
            <div class="col-md-2">
                <label for="etat" class="form-label">État</label>
                <select class="form-select" id="etat" name="etat">
                    <option value="">Tous</option>
                    <option value="excellent" <?php echo $etat_filter === 'excellent' ? 'selected' : ''; ?>>Excellent</option>
                    <option value="bon" <?php echo $etat_filter === 'bon' ? 'selected' : ''; ?>>Bon</option>
                    <option value="moyen" <?php echo $etat_filter === 'moyen' ? 'selected' : ''; ?>>Moyen</option>
                    <option value="mauvais" <?php echo $etat_filter === 'mauvais' ? 'selected' : ''; ?>>Mauvais</option>
                    <option value="hors_service" <?php echo $etat_filter === 'hors_service' ? 'selected' : ''; ?>>Hors service</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">&nbsp;</label>
                <button type="submit" class="btn btn-primary d-block w-100">
                    <i class="fas fa-search me-1"></i>
                    Filtrer
                </button>
            </div>
            <div class="col-md-1">
                <label class="form-label">&nbsp;</label>
                <a href="?" class="btn btn-outline-secondary d-block w-100">
                    <i class="fas fa-times"></i>
                </a>
            </div>
        </form>
    </div>
</div>

<!-- Liste des livres -->
<div class="card">
    <div class="card-header">
        <h5 class="mb-0">
            <i class="fas fa-list me-2"></i>
            Liste des livres (<?php echo number_format($total_livres ?? 0); ?>)
        </h5>
    </div>
    <div class="card-body">
        <?php if (empty($livres)): ?>
            <div class="text-center py-4">
                <i class="fas fa-book fa-3x text-muted mb-3"></i>
                <h5 class="text-muted">Aucun livre trouvé</h5>
                <p class="text-muted">Aucun livre ne correspond aux critères sélectionnés.</p>
                <?php if (checkPermission('library')): ?>
                    <a href="add.php" class="btn btn-primary">
                        <i class="fas fa-plus me-1"></i>
                        Ajouter le premier livre
                    </a>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <form method="POST" id="bulkForm">
                <input type="hidden" name="action" value="bulk_update">

                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead class="table-light">
                            <tr>
                                <?php if (checkPermission('library')): ?>
                                    <th width="40">
                                        <input type="checkbox" id="selectAll" class="form-check-input">
                                    </th>
                                <?php endif; ?>
                                <th>Livre</th>
                                <th>Catégorie</th>
                                <th>Statut</th>
                                <th>État</th>
                                <th>Emprunts</th>
                                <th>Emplacement</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($livres as $livre): ?>
                                <tr>
                                    <?php if (checkPermission('library')): ?>
                                        <td>
                                            <input type="checkbox" name="livre_ids[]"
                                                   value="<?php echo $livre['id']; ?>"
                                                   class="form-check-input livre-checkbox">
                                        </td>
                                    <?php endif; ?>
                                    <td>
                                        <div>
                                            <strong><?php echo htmlspecialchars($livre['titre']); ?></strong>
                                            <br>
                                            <small class="text-muted">
                                                <?php echo htmlspecialchars($livre['auteur']); ?>
                                                <?php if ($livre['isbn']): ?>
                                                    • ISBN: <?php echo htmlspecialchars($livre['isbn']); ?>
                                                <?php endif; ?>
                                            </small>
                                            <?php if ($livre['editeur']): ?>
                                                <br>
                                                <small class="text-muted">
                                                    <?php echo htmlspecialchars($livre['editeur']); ?>
                                                    <?php if ($livre['annee_publication']): ?>
                                                        (<?php echo $livre['annee_publication']; ?>)
                                                    <?php endif; ?>
                                                </small>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <?php if ($livre['categorie_nom']): ?>
                                            <span class="badge" style="background-color: <?php echo $livre['categorie_couleur']; ?>">
                                                <?php echo htmlspecialchars($livre['categorie_nom']); ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-muted">Non classé</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php
                                        $status_classes = [
                                            'disponible' => 'success',
                                            'emprunte' => 'info',
                                            'reserve' => 'warning',
                                            'perdu' => 'danger',
                                            'retire' => 'secondary'
                                        ];
                                        $status_names = [
                                            'disponible' => 'Disponible',
                                            'emprunte' => 'Emprunté',
                                            'reserve' => 'Réservé',
                                            'perdu' => 'Perdu',
                                            'retire' => 'Retiré'
                                        ];
                                        $status_class = $status_classes[$livre['status']] ?? 'secondary';
                                        $status_name = $status_names[$livre['status']] ?? $livre['status'];
                                        ?>
                                        <span class="badge bg-<?php echo $status_class; ?>">
                                            <?php echo $status_name; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php
                                        $etat_classes = [
                                            'excellent' => 'success',
                                            'bon' => 'primary',
                                            'moyen' => 'warning',
                                            'mauvais' => 'danger',
                                            'hors_service' => 'dark'
                                        ];
                                        $etat_class = $etat_classes[$livre['etat']] ?? 'secondary';
                                        ?>
                                        <span class="badge bg-<?php echo $etat_class; ?>">
                                            <?php echo ucfirst($livre['etat']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="text-center">
                                            <span class="badge bg-primary"><?php echo $livre['nb_emprunts_total']; ?></span>
                                            <?php if ($livre['nb_emprunts_actifs'] > 0): ?>
                                                <br><small class="text-info"><?php echo $livre['nb_emprunts_actifs']; ?> actif(s)</small>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <?php if ($livre['emplacement']): ?>
                                            <small><?php echo htmlspecialchars($livre['emplacement']); ?></small>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <a href="view.php?id=<?php echo $livre['id']; ?>"
                                               class="btn btn-outline-info" title="Voir les détails">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <?php if (checkPermission('library')): ?>
                                                <a href="edit.php?id=<?php echo $livre['id']; ?>"
                                                   class="btn btn-outline-primary" title="Modifier">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <button type="button" class="btn btn-outline-success"
                                                        onclick="changeStatus(<?php echo $livre['id']; ?>)"
                                                        title="Changer le statut">
                                                    <i class="fas fa-exchange-alt"></i>
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </form>
        <?php endif; ?>
    </div>
</div>

<!-- Pagination -->
<?php if ($total_pages > 1): ?>
<nav aria-label="Navigation des pages" class="mt-4">
    <ul class="pagination justify-content-center">
        <?php if ($page > 1): ?>
            <li class="page-item">
                <a class="page-link" href="?page=<?php echo $page - 1; ?>&<?php echo http_build_query(array_filter($_GET, function($key) { return $key !== 'page'; }, ARRAY_FILTER_USE_KEY)); ?>">
                    <i class="fas fa-chevron-left"></i>
                </a>
            </li>
        <?php endif; ?>

        <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
            <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                <a class="page-link" href="?page=<?php echo $i; ?>&<?php echo http_build_query(array_filter($_GET, function($key) { return $key !== 'page'; }, ARRAY_FILTER_USE_KEY)); ?>">
                    <?php echo $i; ?>
                </a>
            </li>
        <?php endfor; ?>

        <?php if ($page < $total_pages): ?>
            <li class="page-item">
                <a class="page-link" href="?page=<?php echo $page + 1; ?>&<?php echo http_build_query(array_filter($_GET, function($key) { return $key !== 'page'; }, ARRAY_FILTER_USE_KEY)); ?>">
                    <i class="fas fa-chevron-right"></i>
                </a>
            </li>
        <?php endif; ?>
    </ul>
</nav>
<?php endif; ?>

<!-- Modal de changement de statut -->
<div class="modal fade" id="statusModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-exchange-alt me-2"></i>
                    Changer le statut
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" id="statusForm">
                <input type="hidden" name="action" value="update_status">
                <input type="hidden" name="livre_id" id="modal_livre_id">

                <div class="modal-body">
                    <div class="mb-3">
                        <label for="status" class="form-label">Nouveau statut</label>
                        <select class="form-select" id="status" name="status" required>
                            <option value="">-- Sélectionner --</option>
                            <option value="disponible">Disponible</option>
                            <option value="emprunte">Emprunté</option>
                            <option value="reserve">Réservé</option>
                            <option value="perdu">Perdu</option>
                            <option value="retire">Retiré</option>
                        </select>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times me-1"></i>
                        Annuler
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-1"></i>
                        Mettre à jour
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal d'actions en lot -->
<div class="modal fade" id="bulkActionModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-tasks me-2"></i>
                    Actions en lot
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i>
                    Sélectionnez d'abord les livres dans la liste, puis choisissez l'action à effectuer.
                </div>

                <div class="mb-3">
                    <label for="bulk_action" class="form-label">Action à effectuer</label>
                    <select class="form-select" id="bulk_action" name="bulk_action" required>
                        <option value="">-- Sélectionner une action --</option>
                        <option value="change_status">Changer le statut</option>
                        <option value="change_category">Changer la catégorie</option>
                    </select>
                </div>

                <div id="bulk_status_section" style="display: none;">
                    <div class="mb-3">
                        <label for="bulk_status" class="form-label">Nouveau statut</label>
                        <select class="form-select" id="bulk_status" name="bulk_status">
                            <option value="">-- Sélectionner --</option>
                            <option value="disponible">Disponible</option>
                            <option value="emprunte">Emprunté</option>
                            <option value="reserve">Réservé</option>
                            <option value="perdu">Perdu</option>
                            <option value="retire">Retiré</option>
                        </select>
                    </div>
                </div>

                <div id="bulk_category_section" style="display: none;">
                    <div class="mb-3">
                        <label for="bulk_category" class="form-label">Nouvelle catégorie</label>
                        <select class="form-select" id="bulk_category" name="bulk_category">
                            <option value="">-- Sélectionner --</option>
                            <?php foreach ($categories as $categorie): ?>
                                <option value="<?php echo $categorie['id']; ?>">
                                    <?php echo htmlspecialchars($categorie['nom']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div id="selectedCount" class="text-muted">
                    Aucun livre sélectionné
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="fas fa-times me-1"></i>
                    Annuler
                </button>
                <button type="button" class="btn btn-primary" onclick="submitBulkAction()">
                    <i class="fas fa-check me-1"></i>
                    Appliquer l'action
                </button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Gestion de la sélection multiple
    const selectAllCheckbox = document.getElementById('selectAll');
    const livreCheckboxes = document.querySelectorAll('.livre-checkbox');

    if (selectAllCheckbox) {
        selectAllCheckbox.addEventListener('change', function() {
            livreCheckboxes.forEach(checkbox => {
                checkbox.checked = this.checked;
            });
            updateSelectedCount();
        });
    }

    livreCheckboxes.forEach(checkbox => {
        checkbox.addEventListener('change', updateSelectedCount);
    });

    function updateSelectedCount() {
        const selectedCount = document.querySelectorAll('.livre-checkbox:checked').length;
        const countElement = document.getElementById('selectedCount');
        if (countElement) {
            countElement.textContent = selectedCount > 0
                ? `${selectedCount} livre(s) sélectionné(s)`
                : 'Aucun livre sélectionné';
        }
    }

    // Gestion des actions en lot
    const bulkActionSelect = document.getElementById('bulk_action');
    const bulkStatusSection = document.getElementById('bulk_status_section');
    const bulkCategorySection = document.getElementById('bulk_category_section');

    if (bulkActionSelect) {
        bulkActionSelect.addEventListener('change', function() {
            bulkStatusSection.style.display = 'none';
            bulkCategorySection.style.display = 'none';

            if (this.value === 'change_status') {
                bulkStatusSection.style.display = 'block';
            } else if (this.value === 'change_category') {
                bulkCategorySection.style.display = 'block';
            }
        });
    }

    // Auto-submit du formulaire de recherche
    let searchTimeout;
    const searchInput = document.getElementById('search');
    if (searchInput) {
        searchInput.addEventListener('input', function() {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                this.form.submit();
            }, 500);
        });
    }
});

function changeStatus(livreId) {
    document.getElementById('modal_livre_id').value = livreId;
    new bootstrap.Modal(document.getElementById('statusModal')).show();
}

function submitBulkAction() {
    const selectedCheckboxes = document.querySelectorAll('.livre-checkbox:checked');
    const bulkAction = document.getElementById('bulk_action').value;

    if (selectedCheckboxes.length === 0) {
        alert('Veuillez sélectionner au moins un livre.');
        return;
    }

    if (!bulkAction) {
        alert('Veuillez choisir une action.');
        return;
    }

    let additionalField = '';
    let additionalValue = '';

    if (bulkAction === 'change_status') {
        additionalField = 'bulk_status';
        additionalValue = document.getElementById('bulk_status').value;
        if (!additionalValue) {
            alert('Veuillez choisir un statut.');
            return;
        }
    } else if (bulkAction === 'change_category') {
        additionalField = 'bulk_category';
        additionalValue = document.getElementById('bulk_category').value;
        if (!additionalValue) {
            alert('Veuillez choisir une catégorie.');
            return;
        }
    }

    if (confirm(`Êtes-vous sûr de vouloir appliquer cette action à ${selectedCheckboxes.length} livre(s) ?`)) {
        // Créer un formulaire dynamique
        const form = document.createElement('form');
        form.method = 'POST';
        form.style.display = 'none';

        // Action
        const actionInput = document.createElement('input');
        actionInput.type = 'hidden';
        actionInput.name = 'action';
        actionInput.value = 'bulk_update';
        form.appendChild(actionInput);

        // Action en lot
        const bulkActionInput = document.createElement('input');
        bulkActionInput.type = 'hidden';
        bulkActionInput.name = 'bulk_action';
        bulkActionInput.value = bulkAction;
        form.appendChild(bulkActionInput);

        // Champ supplémentaire
        if (additionalField && additionalValue) {
            const additionalInput = document.createElement('input');
            additionalInput.type = 'hidden';
            additionalInput.name = additionalField;
            additionalInput.value = additionalValue;
            form.appendChild(additionalInput);
        }

        // Livres sélectionnés
        selectedCheckboxes.forEach(checkbox => {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'livre_ids[]';
            input.value = checkbox.value;
            form.appendChild(input);
        });

        document.body.appendChild(form);
        form.submit();
    }
}
</script>

<?php include '../../../includes/footer.php'; ?>
