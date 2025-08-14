<?php
/**
 * Module Recouvrement - Gestion de la Solvabilité
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
$status_filter = $_GET['status'] ?? '';
$classe_filter = $_GET['classe'] ?? '';
$seuil_filter = $_GET['seuil'] ?? '';
$page = max(1, intval($_GET['page'] ?? 1));
$per_page = 25;
$offset = ($page - 1) * $per_page;

// Construction de la requête avec filtres
$where_conditions = ["a.status = 'active'"];
$params = [];

if ($search) {
    $where_conditions[] = "(e.nom LIKE ? OR e.prenom LIKE ? OR e.numero_matricule LIKE ?)";
    $search_param = "%$search%";
    $params = array_merge($params, [$search_param, $search_param, $search_param]);
}

if ($status_filter) {
    $where_conditions[] = "s.status_solvabilite = ?";
    $params[] = $status_filter;
}

if ($classe_filter) {
    $where_conditions[] = "cl.id = ?";
    $params[] = $classe_filter;
}

if ($seuil_filter) {
    switch ($seuil_filter) {
        case 'critique':
            $where_conditions[] = "s.solde_restant > 100000"; // Plus de 100k FC
            break;
        case 'eleve':
            $where_conditions[] = "s.solde_restant BETWEEN 50000 AND 100000"; // 50k-100k FC
            break;
        case 'faible':
            $where_conditions[] = "s.solde_restant BETWEEN 1 AND 50000"; // 1-50k FC
            break;
    }
}

$where_clause = implode(' AND ', $where_conditions);

// Récupérer la solvabilité avec pagination
try {
    $sql = "SELECT s.*, e.nom, e.prenom, e.numero_matricule, cl.nom as classe_nom,
                   a.nom as annee_nom
            FROM solvabilite_eleves s
            JOIN eleves e ON s.eleve_id = e.id
            JOIN annees_scolaires a ON s.annee_scolaire_id = a.id
            LEFT JOIN inscriptions i ON e.id = i.eleve_id AND i.status = 'inscrit'
            LEFT JOIN classes cl ON i.classe_id = cl.id
            WHERE $where_clause
            ORDER BY s.solde_restant DESC, e.nom ASC
            LIMIT $per_page OFFSET $offset";
    
    $solvabilites = $database->query($sql, $params)->fetchAll();
    
    // Compter le total pour la pagination
    $total_sql = "SELECT COUNT(*) as total 
                  FROM solvabilite_eleves s
                  JOIN eleves e ON s.eleve_id = e.id
                  JOIN annees_scolaires a ON s.annee_scolaire_id = a.id
                  LEFT JOIN inscriptions i ON e.id = i.eleve_id AND i.status = 'inscrit'
                  LEFT JOIN classes cl ON i.classe_id = cl.id
                  WHERE $where_clause";
    
    $total = $database->query($total_sql, $params)->fetch()['total'];
    $total_pages = ceil($total / $per_page);
    
} catch (Exception $e) {
    $solvabilites = [];
    $total = 0;
    $total_pages = 0;
    $errors[] = 'Erreur lors du chargement de la solvabilité : ' . $e->getMessage();
}

// Statistiques de solvabilité
try {
    $stats = $database->query(
        "SELECT 
            COUNT(*) as total_eleves,
            SUM(CASE WHEN status_solvabilite = 'solvable' THEN 1 ELSE 0 END) as solvables,
            SUM(CASE WHEN status_solvabilite = 'partiellement_solvable' THEN 1 ELSE 0 END) as partiellement_solvables,
            SUM(CASE WHEN status_solvabilite = 'non_solvable' THEN 1 ELSE 0 END) as non_solvables,
            COALESCE(SUM(total_frais), 0) as total_frais_global,
            COALESCE(SUM(total_paye), 0) as total_paye_global,
            COALESCE(SUM(solde_restant), 0) as total_impaye_global,
            COALESCE(AVG(pourcentage_paye), 0) as pourcentage_moyen
         FROM solvabilite_eleves s
         JOIN annees_scolaires a ON s.annee_scolaire_id = a.id
         WHERE a.status = 'active'"
    )->fetch();
} catch (Exception $e) {
    $stats = [
        'total_eleves' => 0, 'solvables' => 0, 'partiellement_solvables' => 0, 'non_solvables' => 0,
        'total_frais_global' => 0, 'total_paye_global' => 0, 'total_impaye_global' => 0, 'pourcentage_moyen' => 0
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

$page_title = "Gestion de la Solvabilité";
include '../../../includes/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">
        <i class="fas fa-chart-pie me-2 text-warning"></i>
        Gestion de la Solvabilité
        <span class="badge bg-secondary ms-2"><?php echo number_format($total ?? 0); ?></span>
    </h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <div class="btn-group me-2">
            <a href="recalculate.php" class="btn btn-info">
                <i class="fas fa-calculator me-1"></i>
                Recalculer
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

<!-- Statistiques globales -->
<div class="row mb-4">
    <div class="col-lg-3 col-md-6 mb-3">
        <div class="card bg-success text-white shadow-sm">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h6 class="card-title">Solvables</h6>
                        <h3 class="mb-0"><?php echo number_format($stats['solvables'] ?? 0); ?></h3>
                        <small><?php echo $stats['total_eleves'] > 0 ? number_format(($stats['solvables'] / $stats['total_eleves']) * 100, 1) : 0; ?>%</small>
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
                        <h6 class="card-title">Partiellement</h6>
                        <h3 class="mb-0"><?php echo number_format($stats['partiellement_solvables'] ?? 0); ?></h3>
                        <small><?php echo $stats['total_eleves'] > 0 ? number_format(($stats['partiellement_solvables'] / $stats['total_eleves']) * 100, 1) : 0; ?>%</small>
                    </div>
                    <div class="align-self-center">
                        <i class="fas fa-exclamation-circle fa-2x opacity-75"></i>
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
                        <h6 class="card-title">Non Solvables</h6>
                        <h3 class="mb-0"><?php echo number_format($stats['non_solvables'] ?? 0); ?></h3>
                        <small><?php echo $stats['total_eleves'] > 0 ? number_format(($stats['non_solvables'] / $stats['total_eleves']) * 100, 1) : 0; ?>%</small>
                    </div>
                    <div class="align-self-center">
                        <i class="fas fa-times-circle fa-2x opacity-75"></i>
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
                        <h6 class="card-title">Taux Moyen</h6>
                        <h3 class="mb-0"><?php echo number_format($stats['pourcentage_moyen'] ?? 0, 1); ?>%</h3>
                        <small>de paiement</small>
                    </div>
                    <div class="align-self-center">
                        <i class="fas fa-percentage fa-2x opacity-75"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Résumé financier -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card shadow-sm">
            <div class="card-header bg-light">
                <h6 class="mb-0">
                    <i class="fas fa-coins me-2"></i>
                    Résumé Financier Global
                </h6>
            </div>
            <div class="card-body">
                <div class="row text-center">
                    <div class="col-md-4">
                        <h4 class="text-primary"><?php echo number_format($stats['total_frais_global'] ?? 0, 0, ',', ' '); ?> FC</h4>
                        <small class="text-muted">Total des Frais</small>
                    </div>
                    <div class="col-md-4">
                        <h4 class="text-success"><?php echo number_format($stats['total_paye_global'] ?? 0, 0, ',', ' '); ?> FC</h4>
                        <small class="text-muted">Total Payé</small>
                    </div>
                    <div class="col-md-4">
                        <h4 class="text-danger"><?php echo number_format($stats['total_impaye_global'] ?? 0, 0, ',', ' '); ?> FC</h4>
                        <small class="text-muted">Total Impayé</small>
                    </div>
                </div>
                
                <!-- Barre de progression globale -->
                <div class="mt-3">
                    <?php 
                    $pourcentage_global = $stats['total_frais_global'] > 0 ? 
                        ($stats['total_paye_global'] / $stats['total_frais_global']) * 100 : 0;
                    ?>
                    <div class="progress" style="height: 20px;">
                        <div class="progress-bar bg-success" role="progressbar" 
                             style="width: <?php echo $pourcentage_global; ?>%">
                            <?php echo number_format($pourcentage_global, 1); ?>%
                        </div>
                    </div>
                    <small class="text-muted">Taux de recouvrement global</small>
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
                       placeholder="Nom, matricule...">
            </div>

            <div class="col-md-2">
                <label for="status" class="form-label">Statut</label>
                <select class="form-select" id="status" name="status">
                    <option value="">Tous</option>
                    <option value="solvable" <?php echo ($status_filter === 'solvable') ? 'selected' : ''; ?>>
                        ✅ Solvable
                    </option>
                    <option value="partiellement_solvable" <?php echo ($status_filter === 'partiellement_solvable') ? 'selected' : ''; ?>>
                        ⚠️ Partiellement
                    </option>
                    <option value="non_solvable" <?php echo ($status_filter === 'non_solvable') ? 'selected' : ''; ?>>
                        ❌ Non solvable
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
                <label for="seuil" class="form-label">Seuil Dette</label>
                <select class="form-select" id="seuil" name="seuil">
                    <option value="">Tous</option>
                    <option value="critique" <?php echo ($seuil_filter === 'critique') ? 'selected' : ''; ?>>
                        🔴 > 100k FC
                    </option>
                    <option value="eleve" <?php echo ($seuil_filter === 'eleve') ? 'selected' : ''; ?>>
                        🟡 50k-100k FC
                    </option>
                    <option value="faible" <?php echo ($seuil_filter === 'faible') ? 'selected' : ''; ?>>
                        🟢 < 50k FC
                    </option>
                </select>
            </div>

            <div class="col-md-3">
                <label class="form-label">&nbsp;</label>
                <div class="d-grid gap-2 d-md-flex">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search me-1"></i>
                        Filtrer
                    </button>
                    <a href="?" class="btn btn-outline-secondary">
                        <i class="fas fa-undo me-1"></i>
                        Reset
                    </a>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Liste de solvabilité -->
<div class="card shadow-sm">
    <div class="card-body">
        <?php if (empty($solvabilites)): ?>
            <div class="text-center py-5">
                <i class="fas fa-chart-pie fa-3x text-muted mb-3"></i>
                <h5 class="text-muted">Aucune donnée de solvabilité trouvée</h5>
                <p class="text-muted">
                    <?php if ($search || $status_filter || $classe_filter || $seuil_filter): ?>
                        Essayez de modifier vos critères de recherche.
                    <?php else: ?>
                        Les données de solvabilité n'ont pas encore été calculées.
                    <?php endif; ?>
                </p>
                <a href="recalculate.php" class="btn btn-info">
                    <i class="fas fa-calculator me-1"></i>
                    Calculer la solvabilité
                </a>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover" id="solvabiliteTable">
                    <thead class="table-light">
                        <tr>
                            <th>Élève</th>
                            <th>Classe</th>
                            <th>Total Frais</th>
                            <th>Total Payé</th>
                            <th>Solde Restant</th>
                            <th>% Payé</th>
                            <th>Statut</th>
                            <th width="100">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($solvabilites as $solvabilite): ?>
                            <tr class="<?php echo ($solvabilite['status_solvabilite'] === 'non_solvable') ? 'table-warning' : ''; ?>">
                                <td>
                                    <div>
                                        <strong><?php echo htmlspecialchars($solvabilite['nom'] . ' ' . $solvabilite['prenom']); ?></strong>
                                        <br><small class="text-muted">
                                            <i class="fas fa-id-badge me-1"></i>
                                            <?php echo htmlspecialchars($solvabilite['numero_matricule']); ?>
                                        </small>
                                    </div>
                                </td>
                                <td>
                                    <?php if ($solvabilite['classe_nom']): ?>
                                        <span class="badge bg-info">
                                            <?php echo htmlspecialchars($solvabilite['classe_nom']); ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-muted">Non assigné</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <strong class="text-primary">
                                        <?php echo number_format($solvabilite['total_frais'], 0, ',', ' '); ?> FC
                                    </strong>
                                </td>
                                <td>
                                    <strong class="text-success">
                                        <?php echo number_format($solvabilite['total_paye'], 0, ',', ' '); ?> FC
                                    </strong>
                                </td>
                                <td>
                                    <strong class="text-danger">
                                        <?php echo number_format($solvabilite['solde_restant'], 0, ',', ' '); ?> FC
                                    </strong>
                                </td>
                                <td>
                                    <div>
                                        <div class="progress mb-1" style="height: 8px;">
                                            <div class="progress-bar bg-<?php
                                                echo ($solvabilite['pourcentage_paye'] >= 100) ? 'success' :
                                                     (($solvabilite['pourcentage_paye'] >= 50) ? 'warning' : 'danger');
                                            ?>" role="progressbar"
                                                 style="width: <?php echo min(100, $solvabilite['pourcentage_paye']); ?>%">
                                            </div>
                                        </div>
                                        <small class="text-muted">
                                            <?php echo number_format($solvabilite['pourcentage_paye'], 1); ?>%
                                        </small>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge bg-<?php
                                        echo match($solvabilite['status_solvabilite']) {
                                            'solvable' => 'success',
                                            'partiellement_solvable' => 'warning',
                                            'non_solvable' => 'danger',
                                            default => 'secondary'
                                        };
                                    ?> fs-6">
                                        <?php
                                        echo match($solvabilite['status_solvabilite']) {
                                            'solvable' => '✅ Solvable',
                                            'partiellement_solvable' => '⚠️ Partiel',
                                            'non_solvable' => '❌ Non solvable',
                                            default => 'Inconnu'
                                        };
                                        ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <a href="view.php?id=<?php echo $solvabilite['eleve_id']; ?>"
                                           class="btn btn-outline-primary" title="Voir détails">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="../paiements/add.php?eleve_id=<?php echo $solvabilite['eleve_id']; ?>"
                                           class="btn btn-outline-success" title="Nouveau paiement">
                                            <i class="fas fa-plus"></i>
                                        </a>
                                        <a href="history.php?id=<?php echo $solvabilite['eleve_id']; ?>"
                                           class="btn btn-outline-info" title="Historique">
                                            <i class="fas fa-history"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<style>
.opacity-75 {
    opacity: 0.75;
}

.table-warning {
    background-color: rgba(255, 193, 7, 0.1) !important;
}

.progress {
    border-radius: 10px;
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
