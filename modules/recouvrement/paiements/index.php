<?php
/**
 * Module Recouvrement - Gestion des Paiements
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
$mode_filter = $_GET['mode'] ?? '';
$status_filter = $_GET['status'] ?? '';
$classe_filter = $_GET['classe'] ?? '';
$date_debut = $_GET['date_debut'] ?? date('Y-m-01'); // Premier jour du mois
$date_fin = $_GET['date_fin'] ?? date('Y-m-d'); // Aujourd'hui
$page = max(1, intval($_GET['page'] ?? 1));
$per_page = 25;
$offset = ($page - 1) * $per_page;

// Construction de la requête avec filtres
$where_conditions = ["1=1"];
$params = [];

if ($search) {
    $where_conditions[] = "(e.nom LIKE ? OR e.prenom LIKE ? OR e.numero_matricule LIKE ? OR p.reference_paiement LIKE ?)";
    $search_param = "%$search%";
    $params = array_merge($params, [$search_param, $search_param, $search_param, $search_param]);
}

if ($mode_filter) {
    $where_conditions[] = "p.mode_paiement = ?";
    $params[] = $mode_filter;
}

if ($status_filter) {
    $where_conditions[] = "p.status = ?";
    $params[] = $status_filter;
}

if ($classe_filter) {
    $where_conditions[] = "cl.id = ?";
    $params[] = $classe_filter;
}

if ($date_debut) {
    $where_conditions[] = "DATE(p.date_paiement) >= ?";
    $params[] = $date_debut;
}

if ($date_fin) {
    $where_conditions[] = "DATE(p.date_paiement) <= ?";
    $params[] = $date_fin;
}

$where_clause = implode(' AND ', $where_conditions);

// Récupérer les paiements avec pagination
try {
    $sql = "SELECT p.*, e.nom, e.prenom, e.numero_matricule, cl.nom as classe_nom,
                   f.nom as frais_nom, f.type_frais,
                   u.nom as recu_par_nom, u.prenom as recu_par_prenom,
                   DATEDIFF(NOW(), p.date_paiement) as jours_depuis
            FROM paiements p
            JOIN eleves e ON p.eleve_id = e.id
            JOIN frais_scolaires f ON p.frais_id = f.id
            LEFT JOIN inscriptions i ON e.id = i.eleve_id AND i.status = 'inscrit'
            LEFT JOIN classes cl ON i.classe_id = cl.id
            LEFT JOIN users u ON p.recu_par = u.id
            WHERE $where_clause
            ORDER BY p.date_paiement DESC, p.id DESC
            LIMIT $per_page OFFSET $offset";
    
    $paiements = $database->query($sql, $params)->fetchAll();
    
    // Compter le total pour la pagination
    $total_sql = "SELECT COUNT(*) as total 
                  FROM paiements p
                  JOIN eleves e ON p.eleve_id = e.id
                  JOIN frais_scolaires f ON p.frais_id = f.id
                  LEFT JOIN inscriptions i ON e.id = i.eleve_id AND i.status = 'inscrit'
                  LEFT JOIN classes cl ON i.classe_id = cl.id
                  WHERE $where_clause";
    
    $total = $database->query($total_sql, $params)->fetch()['total'];
    $total_pages = ceil($total / $per_page);
    
} catch (Exception $e) {
    $paiements = [];
    $total = 0;
    $total_pages = 0;
    $errors[] = 'Erreur lors du chargement des paiements : ' . $e->getMessage();
}

// Statistiques des paiements
try {
    $stats = $database->query(
        "SELECT 
            COUNT(*) as total_paiements,
            COALESCE(SUM(montant), 0) as montant_total,
            COALESCE(SUM(CASE WHEN status = 'complet' THEN montant ELSE 0 END), 0) as montant_complet,
            COALESCE(SUM(CASE WHEN status = 'partiel' THEN montant ELSE 0 END), 0) as montant_partiel,
            COUNT(CASE WHEN DATE(date_paiement) = CURDATE() THEN 1 END) as paiements_aujourd_hui,
            COALESCE(SUM(CASE WHEN DATE(date_paiement) = CURDATE() THEN montant ELSE 0 END), 0) as montant_aujourd_hui
         FROM paiements p
         WHERE DATE(date_paiement) BETWEEN ? AND ?",
        [$date_debut, $date_fin]
    )->fetch();
} catch (Exception $e) {
    $stats = [
        'total_paiements' => 0, 'montant_total' => 0, 'montant_complet' => 0, 'montant_partiel' => 0,
        'paiements_aujourd_hui' => 0, 'montant_aujourd_hui' => 0
    ];
}

// Récupérer les classes pour le filtre
try {
    $classes = $database->query(
        "SELECT id, nom FROM classes WHERE active = 1 ORDER BY nom"
    )->fetchAll();
} catch (Exception $e) {
    $classes = [];
}

$page_title = "Gestion des Paiements";
include '../../../includes/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">
        <i class="fas fa-money-bill me-2 text-success"></i>
        Gestion des Paiements
        <span class="badge bg-secondary ms-2"><?php echo number_format($total ?? 0); ?></span>
    </h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <div class="btn-group me-2">
            <a href="add.php" class="btn btn-success">
                <i class="fas fa-plus me-1"></i>
                Nouveau Paiement
            </a>
        </div>
        <div class="btn-group me-2">
            <button type="button" class="btn btn-info" onclick="exportData()">
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
    <div class="col-lg-3 col-md-6 mb-3">
        <div class="card bg-success text-white shadow-sm">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h6 class="card-title">Aujourd'hui</h6>
                        <h3 class="mb-0"><?php echo number_format($stats['montant_aujourd_hui'] ?? 0, 0, ',', ' '); ?> FC</h3>
                        <small><?php echo number_format($stats['paiements_aujourd_hui'] ?? 0); ?> paiement(s)</small>
                    </div>
                    <div class="align-self-center">
                        <i class="fas fa-calendar-day fa-2x opacity-75"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-lg-3 col-md-6 mb-3">
        <div class="card bg-primary text-white shadow-sm">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h6 class="card-title">Total Période</h6>
                        <h3 class="mb-0"><?php echo number_format($stats['montant_total'] ?? 0, 0, ',', ' '); ?> FC</h3>
                        <small><?php echo number_format($stats['total_paiements'] ?? 0); ?> paiement(s)</small>
                    </div>
                    <div class="align-self-center">
                        <i class="fas fa-coins fa-2x opacity-75"></i>
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
                        <h6 class="card-title">Complets</h6>
                        <h3 class="mb-0"><?php echo number_format($stats['montant_complet'] ?? 0, 0, ',', ' '); ?> FC</h3>
                        <small>Paiements complets</small>
                    </div>
                    <div class="align-self-center">
                        <i class="fas fa-check-circle fa-2x opacity-75"></i>
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
                        <h6 class="card-title">Partiels</h6>
                        <h3 class="mb-0"><?php echo number_format($stats['montant_partiel'] ?? 0, 0, ',', ' '); ?> FC</h3>
                        <small>Paiements partiels</small>
                    </div>
                    <div class="align-self-center">
                        <i class="fas fa-exclamation-circle fa-2x opacity-75"></i>
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
                       placeholder="Nom, matricule, référence...">
            </div>
            
            <div class="col-md-2">
                <label for="mode" class="form-label">Mode</label>
                <select class="form-select" id="mode" name="mode">
                    <option value="">Tous</option>
                    <option value="especes" <?php echo ($mode_filter === 'especes') ? 'selected' : ''; ?>>
                        💵 Espèces
                    </option>
                    <option value="cheque" <?php echo ($mode_filter === 'cheque') ? 'selected' : ''; ?>>
                        📝 Chèque
                    </option>
                    <option value="virement" <?php echo ($mode_filter === 'virement') ? 'selected' : ''; ?>>
                        🏦 Virement
                    </option>
                    <option value="mobile_money" <?php echo ($mode_filter === 'mobile_money') ? 'selected' : ''; ?>>
                        📱 Mobile Money
                    </option>
                    <option value="carte" <?php echo ($mode_filter === 'carte') ? 'selected' : ''; ?>>
                        💳 Carte
                    </option>
                </select>
            </div>
            
            <div class="col-md-2">
                <label for="status" class="form-label">Statut</label>
                <select class="form-select" id="status" name="status">
                    <option value="">Tous</option>
                    <option value="complet" <?php echo ($status_filter === 'complet') ? 'selected' : ''; ?>>
                        ✅ Complet
                    </option>
                    <option value="partiel" <?php echo ($status_filter === 'partiel') ? 'selected' : ''; ?>>
                        ⚠️ Partiel
                    </option>
                    <option value="annule" <?php echo ($status_filter === 'annule') ? 'selected' : ''; ?>>
                        ❌ Annulé
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
            
            <div class="col-md-3">
                <label class="form-label">Période</label>
                <div class="row g-1">
                    <div class="col-6">
                        <input type="date" class="form-control form-control-sm" name="date_debut" 
                               value="<?php echo htmlspecialchars($date_debut); ?>">
                    </div>
                    <div class="col-6">
                        <input type="date" class="form-control form-control-sm" name="date_fin" 
                               value="<?php echo htmlspecialchars($date_fin); ?>">
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

<!-- Liste des paiements -->
<div class="card shadow-sm">
    <div class="card-body">
        <?php if (empty($paiements)): ?>
            <div class="text-center py-5">
                <i class="fas fa-money-bill fa-3x text-muted mb-3"></i>
                <h5 class="text-muted">Aucun paiement trouvé</h5>
                <p class="text-muted">
                    <?php if ($search || $mode_filter || $status_filter || $classe_filter): ?>
                        Essayez de modifier vos critères de recherche.
                    <?php else: ?>
                        Aucun paiement enregistré pour cette période.
                    <?php endif; ?>
                </p>
                <a href="add.php" class="btn btn-success">
                    <i class="fas fa-plus me-1"></i>
                    Nouveau paiement
                </a>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover" id="paiementsTable">
                    <thead class="table-light">
                        <tr>
                            <th>Date</th>
                            <th>Élève</th>
                            <th>Classe</th>
                            <th>Frais</th>
                            <th>Montant</th>
                            <th>Mode</th>
                            <th>Statut</th>
                            <th>Reçu par</th>
                            <th width="100">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($paiements as $paiement): ?>
                            <tr>
                                <td>
                                    <div>
                                        <strong><?php echo formatDateTime($paiement['date_paiement'], 'd/m/Y'); ?></strong>
                                        <br><small class="text-muted">
                                            Il y a <?php echo $paiement['jours_depuis']; ?> jour(s)
                                        </small>
                                    </div>
                                </td>
                                <td>
                                    <div>
                                        <strong><?php echo htmlspecialchars($paiement['nom'] . ' ' . $paiement['prenom']); ?></strong>
                                        <br><small class="text-muted">
                                            <i class="fas fa-id-badge me-1"></i>
                                            <?php echo htmlspecialchars($paiement['numero_matricule']); ?>
                                        </small>
                                    </div>
                                </td>
                                <td>
                                    <?php if ($paiement['classe_nom']): ?>
                                        <span class="badge bg-info">
                                            <?php echo htmlspecialchars($paiement['classe_nom']); ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-muted">Non assigné</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div>
                                        <strong><?php echo htmlspecialchars($paiement['frais_nom']); ?></strong>
                                        <br><span class="badge bg-secondary">
                                            <?php echo ucfirst($paiement['type_frais']); ?>
                                        </span>
                                    </div>
                                </td>
                                <td>
                                    <div>
                                        <strong class="text-success">
                                            <?php echo number_format($paiement['montant'], 0, ',', ' '); ?> FC
                                        </strong>
                                        <?php if ($paiement['montant_restant'] > 0): ?>
                                            <br><small class="text-danger">
                                                Reste: <?php echo number_format($paiement['montant_restant'], 0, ',', ' '); ?> FC
                                            </small>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge bg-<?php
                                        echo match($paiement['mode_paiement']) {
                                            'especes' => 'success',
                                            'cheque' => 'warning',
                                            'virement' => 'info',
                                            'mobile_money' => 'primary',
                                            'carte' => 'secondary',
                                            default => 'secondary'
                                        };
                                    ?>">
                                        <?php
                                        echo match($paiement['mode_paiement']) {
                                            'especes' => '💵 Espèces',
                                            'cheque' => '📝 Chèque',
                                            'virement' => '🏦 Virement',
                                            'mobile_money' => '📱 Mobile Money',
                                            'carte' => '💳 Carte',
                                            default => ucfirst($paiement['mode_paiement'])
                                        };
                                        ?>
                                    </span>
                                    <?php if ($paiement['reference_paiement']): ?>
                                        <br><small class="text-muted">
                                            Réf: <?php echo htmlspecialchars($paiement['reference_paiement']); ?>
                                        </small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge bg-<?php
                                        echo match($paiement['status']) {
                                            'complet' => 'success',
                                            'partiel' => 'warning',
                                            'annule' => 'danger',
                                            default => 'secondary'
                                        };
                                    ?> fs-6">
                                        <?php
                                        echo match($paiement['status']) {
                                            'complet' => '✅ Complet',
                                            'partiel' => '⚠️ Partiel',
                                            'annule' => '❌ Annulé',
                                            default => ucfirst($paiement['status'])
                                        };
                                        ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($paiement['recu_par_nom']): ?>
                                        <small class="text-muted">
                                            <i class="fas fa-user me-1"></i>
                                            <?php echo htmlspecialchars($paiement['recu_par_nom'] . ' ' . $paiement['recu_par_prenom']); ?>
                                        </small>
                                    <?php else: ?>
                                        <span class="text-muted">Système</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <a href="view.php?id=<?php echo $paiement['id']; ?>"
                                           class="btn btn-outline-primary" title="Voir détails">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="receipt.php?id=<?php echo $paiement['id']; ?>"
                                           class="btn btn-outline-info" title="Reçu" target="_blank">
                                            <i class="fas fa-receipt"></i>
                                        </a>
                                        <?php if ($paiement['status'] !== 'annule'): ?>
                                            <div class="btn-group btn-group-sm">
                                                <button type="button" class="btn btn-outline-secondary dropdown-toggle"
                                                        data-bs-toggle="dropdown" title="Plus d'actions">
                                                    <i class="fas fa-ellipsis-v"></i>
                                                </button>
                                                <ul class="dropdown-menu">
                                                    <li><a class="dropdown-item" href="edit.php?id=<?php echo $paiement['id']; ?>">
                                                        <i class="fas fa-edit me-2"></i>Modifier
                                                    </a></li>
                                                    <?php if ($paiement['status'] === 'partiel'): ?>
                                                        <li><a class="dropdown-item" href="complete.php?id=<?php echo $paiement['id']; ?>">
                                                            <i class="fas fa-plus me-2"></i>Compléter
                                                        </a></li>
                                                    <?php endif; ?>
                                                    <li><hr class="dropdown-divider"></li>
                                                    <li><a class="dropdown-item text-danger" href="cancel.php?id=<?php echo $paiement['id']; ?>"
                                                           onclick="return confirm('Annuler ce paiement ?')">
                                                        <i class="fas fa-times me-2"></i>Annuler
                                                    </a></li>
                                                </ul>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
                <nav aria-label="Navigation des paiements" class="mt-4">
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
                        (<?php echo number_format($total ?? 0); ?> paiement<?php echo ($total ?? 0) > 1 ? 's' : ''; ?> au total)
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
</style>

<script>
function exportData() {
    const params = new URLSearchParams(window.location.search);
    params.set('export', 'excel');
    window.location.href = 'index.php?' + params.toString();
}
</script>

<?php include '../../../includes/footer.php'; ?>
