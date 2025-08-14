<?php
/**
 * Module Recouvrement - Gestion des Frais Scolaires
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

// Traitement des actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    try {
        if ($action === 'toggle_status') {
            $frais_id = intval($_POST['frais_id'] ?? 0);
            $new_status = intval($_POST['new_status'] ?? 0);
            
            $database->execute(
                "UPDATE frais_scolaires SET actif = ?, updated_at = NOW() WHERE id = ?",
                [$new_status, $frais_id]
            );
            
            showMessage('success', 'Statut du frais mis à jour.');
        }
    } catch (Exception $e) {
        $errors[] = 'Erreur lors de l\'action : ' . $e->getMessage();
    }
}

// Paramètres de filtrage et pagination
$search = trim($_GET['search'] ?? '');
$type_filter = $_GET['type'] ?? '';
$classe_filter = $_GET['classe'] ?? '';
$annee_filter = $_GET['annee'] ?? '';
$status_filter = $_GET['status'] ?? '';
$page = max(1, intval($_GET['page'] ?? 1));
$per_page = 25;
$offset = ($page - 1) * $per_page;

// Construction de la requête avec filtres
$where_conditions = ["1=1"];
$params = [];

if ($search) {
    $where_conditions[] = "(f.nom LIKE ? OR f.description LIKE ?)";
    $search_param = "%$search%";
    $params = array_merge($params, [$search_param, $search_param]);
}

if ($type_filter) {
    $where_conditions[] = "f.type_frais = ?";
    $params[] = $type_filter;
}

if ($classe_filter) {
    $where_conditions[] = "f.classe_id = ?";
    $params[] = $classe_filter;
}

if ($annee_filter) {
    $where_conditions[] = "f.annee_scolaire_id = ?";
    $params[] = $annee_filter;
}

if ($status_filter !== '') {
    $where_conditions[] = "f.actif = ?";
    $params[] = intval($status_filter);
}

$where_clause = implode(' AND ', $where_conditions);

// Récupérer les frais avec pagination
try {
    $sql = "SELECT f.*, c.nom as classe_nom, a.nom as annee_nom,
                   COUNT(p.id) as nb_paiements,
                   COALESCE(SUM(p.montant), 0) as total_paye
            FROM frais_scolaires f
            LEFT JOIN classes c ON f.classe_id = c.id
            LEFT JOIN annees_scolaires a ON f.annee_scolaire_id = a.id
            LEFT JOIN paiements p ON f.id = p.frais_id AND p.status != 'annule'
            WHERE $where_clause
            GROUP BY f.id
            ORDER BY f.created_at DESC, f.id DESC
            LIMIT $per_page OFFSET $offset";
    
    $frais = $database->query($sql, $params)->fetchAll();
    
    // Compter le total pour la pagination
    $total_sql = "SELECT COUNT(*) as total 
                  FROM frais_scolaires f
                  LEFT JOIN classes c ON f.classe_id = c.id
                  LEFT JOIN annees_scolaires a ON f.annee_scolaire_id = a.id
                  WHERE $where_clause";
    
    $total = $database->query($total_sql, $params)->fetch()['total'];
    $total_pages = ceil($total / $per_page);
    
} catch (Exception $e) {
    $frais = [];
    $total = 0;
    $total_pages = 0;
    $errors[] = 'Erreur lors du chargement des frais : ' . $e->getMessage();
}

// Statistiques des frais
try {
    $stats = $database->query(
        "SELECT 
            COUNT(*) as total_frais,
            SUM(CASE WHEN actif = 1 THEN 1 ELSE 0 END) as frais_actifs,
            SUM(CASE WHEN obligatoire = 1 THEN 1 ELSE 0 END) as frais_obligatoires,
            COALESCE(SUM(montant), 0) as montant_total_frais,
            COUNT(CASE WHEN date_echeance < CURDATE() AND actif = 1 THEN 1 END) as frais_echus
         FROM frais_scolaires"
    )->fetch();
} catch (Exception $e) {
    $stats = ['total_frais' => 0, 'frais_actifs' => 0, 'frais_obligatoires' => 0, 'montant_total_frais' => 0, 'frais_echus' => 0];
}

// Récupérer les classes et années pour les filtres
try {
    $classes = $database->query("SELECT id, nom FROM classes WHERE active = 1 ORDER BY nom")->fetchAll();
    $annees = $database->query("SELECT id, nom FROM annees_scolaires ORDER BY nom DESC")->fetchAll();
} catch (Exception $e) {
    $classes = [];
    $annees = [];
}

$page_title = "Gestion des Frais Scolaires";
include '../../../includes/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">
        <i class="fas fa-file-invoice me-2 text-primary"></i>
        Gestion des Frais Scolaires
        <span class="badge bg-secondary ms-2"><?php echo number_format($total ?? 0); ?></span>
    </h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <div class="btn-group me-2">
            <a href="add.php" class="btn btn-success">
                <i class="fas fa-plus me-1"></i>
                Nouveau Frais
            </a>
        </div>
        <div class="btn-group me-2">
            <a href="import.php" class="btn btn-info">
                <i class="fas fa-file-import me-1"></i>
                Importer
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
        <div class="card bg-primary text-white shadow-sm">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h6 class="card-title">Total Frais</h6>
                        <h3 class="mb-0"><?php echo number_format($stats['total_frais'] ?? 0); ?></h3>
                        <small><?php echo number_format($stats['frais_actifs'] ?? 0); ?> actifs</small>
                    </div>
                    <div class="align-self-center">
                        <i class="fas fa-file-invoice fa-2x opacity-75"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-lg-3 col-md-6 mb-3">
        <div class="card bg-success text-white shadow-sm">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h6 class="card-title">Obligatoires</h6>
                        <h3 class="mb-0"><?php echo number_format($stats['frais_obligatoires'] ?? 0); ?></h3>
                        <small>Frais obligatoires</small>
                    </div>
                    <div class="align-self-center">
                        <i class="fas fa-exclamation-circle fa-2x opacity-75"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-lg-3 col-md-6 mb-3">
        <div class="card bg-info text-white shadow-sm">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h6 class="card-title">Montant Total</h6>
                        <h3 class="mb-0"><?php echo number_format($stats['montant_total_frais'] ?? 0, 0, ',', ' '); ?> FC</h3>
                        <small>Tous les frais</small>
                    </div>
                    <div class="align-self-center">
                        <i class="fas fa-coins fa-2x opacity-75"></i>
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
                        <h6 class="card-title">Échus</h6>
                        <h3 class="mb-0"><?php echo number_format($stats['frais_echus'] ?? 0); ?></h3>
                        <small>Date dépassée</small>
                    </div>
                    <div class="align-self-center">
                        <i class="fas fa-clock fa-2x opacity-75"></i>
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
            <div class="col-md-3">
                <label for="search" class="form-label">Recherche</label>
                <input type="text" class="form-control" id="search" name="search" 
                       value="<?php echo htmlspecialchars($search); ?>" 
                       placeholder="Nom du frais...">
            </div>
            
            <div class="col-md-2">
                <label for="type" class="form-label">Type</label>
                <select class="form-select" id="type" name="type">
                    <option value="">Tous</option>
                    <option value="inscription" <?php echo ($type_filter === 'inscription') ? 'selected' : ''; ?>>
                        📝 Inscription
                    </option>
                    <option value="scolarite" <?php echo ($type_filter === 'scolarite') ? 'selected' : ''; ?>>
                        🎓 Scolarité
                    </option>
                    <option value="examen" <?php echo ($type_filter === 'examen') ? 'selected' : ''; ?>>
                        📋 Examen
                    </option>
                    <option value="transport" <?php echo ($type_filter === 'transport') ? 'selected' : ''; ?>>
                        🚌 Transport
                    </option>
                    <option value="cantine" <?php echo ($type_filter === 'cantine') ? 'selected' : ''; ?>>
                        🍽️ Cantine
                    </option>
                    <option value="autre" <?php echo ($type_filter === 'autre') ? 'selected' : ''; ?>>
                        📦 Autre
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
                <label for="annee" class="form-label">Année</label>
                <select class="form-select" id="annee" name="annee">
                    <option value="">Toutes</option>
                    <?php foreach ($annees as $annee): ?>
                        <option value="<?php echo $annee['id']; ?>" 
                                <?php echo ($annee_filter == $annee['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($annee['nom']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="col-md-2">
                <label for="status" class="form-label">Statut</label>
                <select class="form-select" id="status" name="status">
                    <option value="">Tous</option>
                    <option value="1" <?php echo ($status_filter === '1') ? 'selected' : ''; ?>>
                        ✅ Actif
                    </option>
                    <option value="0" <?php echo ($status_filter === '0') ? 'selected' : ''; ?>>
                        ❌ Inactif
                    </option>
                </select>
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

<!-- Liste des frais -->
<div class="card shadow-sm">
    <div class="card-body">
        <?php if (empty($frais)): ?>
            <div class="text-center py-5">
                <i class="fas fa-file-invoice fa-3x text-muted mb-3"></i>
                <h5 class="text-muted">Aucun frais trouvé</h5>
                <p class="text-muted">
                    <?php if ($search || $type_filter || $classe_filter || $annee_filter || $status_filter !== ''): ?>
                        Essayez de modifier vos critères de recherche.
                    <?php else: ?>
                        Aucun frais scolaire configuré pour le moment.
                    <?php endif; ?>
                </p>
                <a href="add.php" class="btn btn-success">
                    <i class="fas fa-plus me-1"></i>
                    Nouveau frais
                </a>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover" id="fraisTable">
                    <thead class="table-light">
                        <tr>
                            <th>Nom du Frais</th>
                            <th>Type</th>
                            <th>Montant</th>
                            <th>Classe/Niveau</th>
                            <th>Année</th>
                            <th>Échéance</th>
                            <th>Paiements</th>
                            <th>Statut</th>
                            <th width="120">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($frais as $frais_item): ?>
                            <tr class="<?php echo (!$frais_item['actif']) ? 'table-secondary' : ''; ?>">
                                <td>
                                    <div>
                                        <strong><?php echo htmlspecialchars($frais_item['nom']); ?></strong>
                                        <?php if ($frais_item['obligatoire']): ?>
                                            <span class="badge bg-danger ms-1">Obligatoire</span>
                                        <?php endif; ?>
                                        <?php if ($frais_item['description']): ?>
                                            <br><small class="text-muted">
                                                <?php echo htmlspecialchars(substr($frais_item['description'], 0, 50)); ?>
                                                <?php if (strlen($frais_item['description']) > 50): ?>...<?php endif; ?>
                                            </small>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge bg-<?php
                                        echo match($frais_item['type_frais']) {
                                            'inscription' => 'primary',
                                            'scolarite' => 'success',
                                            'examen' => 'warning',
                                            'transport' => 'info',
                                            'cantine' => 'secondary',
                                            'autre' => 'dark',
                                            default => 'secondary'
                                        };
                                    ?>">
                                        <?php
                                        echo match($frais_item['type_frais']) {
                                            'inscription' => '📝 Inscription',
                                            'scolarite' => '🎓 Scolarité',
                                            'examen' => '📋 Examen',
                                            'transport' => '🚌 Transport',
                                            'cantine' => '🍽️ Cantine',
                                            'autre' => '📦 Autre',
                                            default => ucfirst($frais_item['type_frais'])
                                        };
                                        ?>
                                    </span>
                                </td>
                                <td>
                                    <strong class="text-primary">
                                        <?php echo number_format($frais_item['montant'], 0, ',', ' '); ?> FC
                                    </strong>
                                </td>
                                <td>
                                    <?php if ($frais_item['classe_nom']): ?>
                                        <span class="badge bg-info">
                                            <?php echo htmlspecialchars($frais_item['classe_nom']); ?>
                                        </span>
                                    <?php elseif ($frais_item['niveau']): ?>
                                        <span class="badge bg-secondary">
                                            <?php echo htmlspecialchars($frais_item['niveau']); ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-muted">Tous</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($frais_item['annee_nom']): ?>
                                        <small class="text-muted">
                                            <?php echo htmlspecialchars($frais_item['annee_nom']); ?>
                                        </small>
                                    <?php else: ?>
                                        <span class="text-muted">Toutes</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($frais_item['date_echeance']): ?>
                                        <?php
                                        $echeance = new DateTime($frais_item['date_echeance']);
                                        $now = new DateTime();
                                        $expired = $echeance < $now;
                                        ?>
                                        <span class="<?php echo $expired ? 'text-danger' : 'text-success'; ?>">
                                            <?php echo formatDateTime($frais_item['date_echeance'], 'd/m/Y'); ?>
                                        </span>
                                        <?php if ($expired): ?>
                                            <br><small class="text-danger">Échu</small>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="text-muted">Aucune</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div>
                                        <strong class="text-success">
                                            <?php echo number_format($frais_item['nb_paiements']); ?>
                                        </strong>
                                        <small class="text-muted">paiement(s)</small>
                                        <?php if ($frais_item['total_paye'] > 0): ?>
                                            <br><small class="text-success">
                                                <?php echo number_format($frais_item['total_paye'], 0, ',', ' '); ?> FC
                                            </small>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <form method="POST" class="d-inline">
                                        <input type="hidden" name="action" value="toggle_status">
                                        <input type="hidden" name="frais_id" value="<?php echo $frais_item['id']; ?>">
                                        <input type="hidden" name="new_status" value="<?php echo $frais_item['actif'] ? 0 : 1; ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-<?php echo $frais_item['actif'] ? 'success' : 'secondary'; ?>"
                                                title="<?php echo $frais_item['actif'] ? 'Désactiver' : 'Activer'; ?>">
                                            <?php if ($frais_item['actif']): ?>
                                                <i class="fas fa-check-circle"></i> Actif
                                            <?php else: ?>
                                                <i class="fas fa-pause-circle"></i> Inactif
                                            <?php endif; ?>
                                        </button>
                                    </form>
                                </td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <a href="view.php?id=<?php echo $frais_item['id']; ?>"
                                           class="btn btn-outline-primary" title="Voir détails">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="edit.php?id=<?php echo $frais_item['id']; ?>"
                                           class="btn btn-outline-warning" title="Modifier">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <div class="btn-group btn-group-sm">
                                            <button type="button" class="btn btn-outline-secondary dropdown-toggle"
                                                    data-bs-toggle="dropdown" title="Plus d'actions">
                                                <i class="fas fa-ellipsis-v"></i>
                                            </button>
                                            <ul class="dropdown-menu">
                                                <li><a class="dropdown-item" href="duplicate.php?id=<?php echo $frais_item['id']; ?>">
                                                    <i class="fas fa-copy me-2"></i>Dupliquer
                                                </a></li>
                                                <li><a class="dropdown-item" href="../paiements/?frais_id=<?php echo $frais_item['id']; ?>">
                                                    <i class="fas fa-money-bill me-2"></i>Voir paiements
                                                </a></li>
                                                <li><hr class="dropdown-divider"></li>
                                                <li><a class="dropdown-item text-danger" href="delete.php?id=<?php echo $frais_item['id']; ?>"
                                                       onclick="return confirm('Supprimer ce frais ? Cette action est irréversible.')">
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
                <nav aria-label="Navigation des frais" class="mt-4">
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
                        (<?php echo number_format($total ?? 0); ?> frais au total)
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

.table-secondary {
    background-color: rgba(108, 117, 125, 0.1) !important;
}
</style>

<?php include '../../../includes/footer.php'; ?>
