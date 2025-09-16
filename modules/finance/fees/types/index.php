<?php
/**
 * Module de gestion financière - Types de frais scolaires
 * Application de gestion scolaire - République Démocratique du Congo
 */

require_once '../../../../config/config.php';
require_once '../../../../config/database.php';
require_once '../../../../includes/functions.php';
require_once '../../../../includes/permissions-pages.php';
require_once '../../../../includes/ui-permissions.php';
require_once 'functions.php';

// Vérifier l'authentification et les permissions
requireLogin();
requirePagePermissionFromDB('finance', 'fees/types/index', 'read', '../../../../dashboard.php');

$page_title = 'Types de Frais Scolaires';

// Obtenir l'année scolaire actuelle
$current_year = getCurrentAcademicYear();

if (!$current_year || !isset($current_year['id'])) {
    showMessage('error', 'Aucune année scolaire active ou ID manquant.');
    redirectTo('../../index.php');
}

// Paramètres de recherche et filtrage
$search = sanitizeInput($_GET['search'] ?? '');
$status_filter = sanitizeInput($_GET['status'] ?? '');

// Construction de la requête
$sql = "SELECT tf.*, 
               COUNT(f.id) as nombre_frais_utilises,
               as_annee.annee, as_annee.date_debut, as_annee.date_fin
        FROM type_frais tf
        JOIN annees_scolaires as_annee ON tf.annee_scolaire_id = as_annee.id
        LEFT JOIN frais_scolaires f ON tf.id = f.type_frais_id AND f.annee_scolaire_id = tf.annee_scolaire_id
        WHERE tf.annee_scolaire_id = ?";

$params = [$current_year['id']];

if (!empty($search)) {
    $sql .= " AND (tf.nom LIKE ? OR tf.description LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
}

if (!empty($status_filter)) {
    if ($status_filter === 'actif') {
        $sql .= " AND tf.actif = 1";
    } elseif ($status_filter === 'inactif') {
        $sql .= " AND tf.actif = 0";
    }
}
    
$sql .= " GROUP BY tf.id ORDER BY tf.nom";

$types_frais = $database->query($sql, $params)->fetchAll();

// Statistiques
$stats = [
    'total' => count($types_frais),
    'actifs' => count(array_filter($types_frais, fn($t) => $t['actif'] == 1)),
    'inactifs' => count(array_filter($types_frais, fn($t) => $t['actif'] == 0)),
    'utilises' => count(array_filter($types_frais, fn($t) => $t['nombre_frais_utilises'] > 0))
];

include '../../../../includes/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">
        <i class="fas fa-tags me-2"></i>
        Types de Frais Scolaires
    </h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <div class="btn-group me-2">
            <a href="../index.php" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-1"></i>
                Retour aux frais
            </a>
        </div>
        <div class="btn-group me-2">
            <span class="btn btn-outline-info">
                <i class="fas fa-calendar me-1"></i>
                Année: <?php echo htmlspecialchars($current_year['annee'] ?? 'Non définie'); ?>
            </span>
        </div>
        <?php if (hasPagePermissionFromDB('finance', 'fees/types/add', 'create')): ?>
            <div class="btn-group me-2">
                <a href="add.php" class="btn btn-primary">
                    <i class="fas fa-plus me-1"></i>
                    Nouveau type
                </a>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Statistiques rapides -->
<div class="row mb-4">
    <div class="col-md-3">
        <div class="card text-white bg-primary">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h4><?php echo $stats['total']; ?></h4>
                        <p class="mb-0">Total types</p>
                    </div>
                    <div class="align-self-center">
                        <i class="fas fa-tags fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-white bg-success">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h4><?php echo $stats['actifs']; ?></h4>
                        <p class="mb-0">Actifs</p>
                    </div>
                    <div class="align-self-center">
                        <i class="fas fa-check-circle fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-white bg-warning">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h4><?php echo $stats['inactifs']; ?></h4>
                        <p class="mb-0">Inactifs</p>
                    </div>
                    <div class="align-self-center">
                        <i class="fas fa-pause-circle fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-white bg-info">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h4><?php echo $stats['utilises']; ?></h4>
                        <p class="mb-0">Utilisés</p>
                    </div>
                    <div class="align-self-center">
                        <i class="fas fa-link fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Filtres de recherche -->
<div class="card mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-md-4">
                <label for="search" class="form-label">Rechercher</label>
                <input type="text" 
                       class="form-control" 
                       id="search" 
                       name="search" 
                       placeholder="Nom ou description..."
                       value="<?php echo htmlspecialchars($search); ?>">
            </div>
            <div class="col-md-3">
                <label for="status" class="form-label">Statut</label>
                <select class="form-select" id="status" name="status">
                    <option value="">Tous</option>
                    <option value="actif" <?php echo $status_filter === 'actif' ? 'selected' : ''; ?>>Actifs</option>
                    <option value="inactif" <?php echo $status_filter === 'inactif' ? 'selected' : ''; ?>>Inactifs</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">&nbsp;</label>
                <div class="d-grid">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search"></i>
                    </button>
                </div>
            </div>
            <div class="col-md-3">
                <label class="form-label">&nbsp;</label>
                <div class="d-grid">
                    <a href="index.php" class="btn btn-outline-secondary">
                        <i class="fas fa-times me-1"></i>
                        Effacer
                    </a>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Liste des types de frais -->
<div class="card">
    <div class="card-header">
        <h5 class="mb-0">
            <i class="fas fa-list me-2"></i>
            Liste des types de frais (<?php echo count($types_frais); ?>)
        </h5>
    </div>
    <div class="card-body">
        <?php if (!empty($types_frais)): ?>
            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead>
                        <tr>
                            <th>Nom</th>
                            <th>Description</th>
                                <th>Priorité</th>
                            <th>Statut</th>
                            <th>Utilisé</th>
                            <th>Date création</th>
                            <th class="no-sort">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($types_frais as $type): ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($type['nom']); ?></strong>
                                </td>
                                <td>
                                    <?php echo displayDescription($type['description']); ?>
                                </td>
                                <td>
                                    <?php
                                    $priority_colors = [
                                        1 => 'danger',    // Priorité maximale
                                        2 => 'warning',   // Haute priorité
                                        3 => 'info',      // Priorité moyenne
                                        4 => 'primary',   // Priorité normale
                                        5 => 'secondary', // Priorité faible
                                    ];
                                    $color = $priority_colors[$type['priorite']] ?? 'light';
                                    ?>
                                    <span class="badge bg-<?php echo $color; ?>">
                                        <?php echo $type['priorite']; ?>
                                    </span>
                                    <br><small class="text-muted">
                                        <?php
                                        switch($type['priorite']) {
                                            case 1: echo 'Maximale'; break;
                                            case 2: echo 'Haute'; break;
                                            case 3: echo 'Moyenne'; break;
                                            case 4: echo 'Normale'; break;
                                            case 5: echo 'Faible'; break;
                                            default: echo 'Très faible'; break;
                                        }
                                        ?>
                                    </small>
                                </td>
                                <td>
                                    <?php if ($type['actif']): ?>
                                        <span class="badge bg-success">Actif</span>
                                    <?php else: ?>
                                        <span class="badge bg-warning">Inactif</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($type['nombre_frais_utilises'] > 0): ?>
                                        <span class="badge bg-info">
                                            <?php echo $type['nombre_frais_utilises']; ?> frais
                                        </span>
                                    <?php else: ?>
                                        <span class="text-muted">Non utilisé</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php echo formatDate($type['date_creation']); ?>
                                    <br><small class="text-muted">
                                        <?php echo date('H:i', strtotime($type['date_creation'])); ?>
                                    </small>
                                </td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <?php if (hasPagePermissionFromDB('finance', 'fees/types/view', 'read')): ?>
                                        <a href="view.php?id=<?php echo $type['id']; ?>" 
                                           class="btn btn-outline-info" 
                                           title="Voir détails">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <?php endif; ?>
                                        <?php if (hasPagePermissionFromDB('finance', 'fees/types/edit', 'update')): ?>
                                            <a href="edit.php?id=<?php echo $type['id']; ?>" 
                                               class="btn btn-outline-primary" 
                                               title="Modifier">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                        <?php endif; ?>
                                        <?php if (hasPagePermissionFromDB('finance', 'fees/types/toggle-status', 'update')): ?>
                                            <?php if ($type['actif']): ?>
                                                <a href="toggle-status.php?id=<?php echo $type['id']; ?>&action=desactiver" 
                                                   class="btn btn-outline-warning" 
                                                   title="Désactiver"
                                                   onclick="return confirm('Êtes-vous sûr de vouloir désactiver ce type de frais ?')">
                                                    <i class="fas fa-pause"></i>
                                                </a>
                                            <?php else: ?>
                                                <a href="toggle-status.php?id=<?php echo $type['id']; ?>&action=activer" 
                                                   class="btn btn-outline-success" 
                                                   title="Activer"
                                                   onclick="return confirm('Êtes-vous sûr de vouloir activer ce type de frais ?')">
                                                    <i class="fas fa-play"></i>
                                                </a>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                        <?php if ($type['nombre_frais_utilises'] == 0 && hasPagePermissionFromDB('finance', 'fees/types/delete', 'delete')): ?>
                                            <a href="delete.php?id=<?php echo $type['id']; ?>" 
                                               class="btn btn-outline-danger" 
                                               title="Supprimer"
                                               onclick="return confirm('Êtes-vous sûr de vouloir supprimer ce type de frais ? Cette action est irréversible.')">
                                                <i class="fas fa-trash"></i>
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="text-center py-5">
                <i class="fas fa-tags fa-3x text-muted mb-3"></i>
                <h5 class="text-muted">Aucun type de frais trouvé</h5>
                <p class="text-muted">
                    <?php if (!empty($search) || !empty($status_filter)): ?>
                        Aucun type de frais ne correspond aux critères de recherche.
                    <?php else: ?>
                        Aucun type de frais n'a encore été configuré pour cette année scolaire.
                    <?php endif; ?>
                </p>
                <?php if (hasPagePermissionFromDB('finance', 'index', 'read')): ?>
                    <a href="add.php" class="btn btn-primary">
                        <i class="fas fa-plus me-1"></i>
                        Créer le premier type de frais
                    </a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include '../../../../includes/footer.php'; ?>



