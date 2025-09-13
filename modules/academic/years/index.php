<?php
/**
 * Module de gestion académique - Gestion des années scolaires
 * Application de gestion scolaire - République Démocratique du Congo
 */

require_once '../../../config/config.php';
require_once '../../../config/database.php';
require_once '../../../includes/functions.php';
require_once '../../../includes/permissions-pages.php';

// Vérifier l'authentification et les permissions
requireLogin();
requirePagePermissionFromDB('academic', 'years', 'read', '../../../dashboard.php');

$page_title = 'Gestion des Années Scolaires';

// Paramètres de recherche
$search = sanitizeInput($_GET['search'] ?? '');

// Construction de la requête
$sql = "SELECT * FROM annees_scolaires WHERE 1=1";
$params = [];

if (!empty($search)) {
    $sql .= " AND annee LIKE ?";
    $params[] = "%$search%";
}

$sql .= " ORDER BY date_debut DESC";

$annees = $database->query($sql, $params)->fetchAll();

// Statistiques
$stats = [
    'total' => count($annees),
    'actives' => count(array_filter($annees, fn($a) => $a['status'] === 'active')),
    'fermees' => count(array_filter($annees, fn($a) => $a['status'] === 'fermee'))
];

include '../../../includes/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">
        <i class="fas fa-calendar-check me-2"></i>
        Gestion des Années Scolaires
    </h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <div class="btn-group me-2">
            <a href="../index.php" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-1"></i>
                Retour
            </a>
        </div>
        <?php if (hasPagePermissionFromDB('academic', 'years', 'create')): ?>
            <div class="btn-group me-2">
                <a href="add.php" class="btn btn-primary">
                    <i class="fas fa-plus me-1"></i>
                    Nouvelle année
                </a>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Statistiques rapides -->
<div class="row mb-4">
    <div class="col-md-4">
        <div class="card text-white bg-primary">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h4><?php echo $stats['total']; ?></h4>
                        <p class="mb-0">Total années</p>
                    </div>
                    <div class="align-self-center">
                        <i class="fas fa-calendar fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card text-white bg-success">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h4><?php echo $stats['actives']; ?></h4>
                        <p class="mb-0">Années actives</p>
                    </div>
                    <div class="align-self-center">
                        <i class="fas fa-check-circle fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card text-white bg-secondary">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h4><?php echo $stats['fermees']; ?></h4>
                        <p class="mb-0">Années fermées</p>
                    </div>
                    <div class="align-self-center">
                        <i class="fas fa-lock fa-2x"></i>
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
            <div class="col-md-8">
                <label for="search" class="form-label">Rechercher</label>
                <input type="text" 
                       class="form-control" 
                       id="search" 
                       name="search" 
                       placeholder="Rechercher par année..."
                       value="<?php echo htmlspecialchars($search); ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label">&nbsp;</label>
                <div class="d-grid">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search me-1"></i>
                        Rechercher
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Liste des années scolaires -->
<div class="card">
    <div class="card-header">
        <h5 class="mb-0">
            <i class="fas fa-list me-2"></i>
            Liste des années scolaires (<?php echo count($annees); ?>)
        </h5>
    </div>
    <div class="card-body">
        <?php if (!empty($annees)): ?>
            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead>
                        <tr>
                            <th>Année Scolaire</th>
                            <th>Date de début</th>
                            <th>Date de fin</th>
                            <th>Statut</th>
                            <th>Créée le</th>
                            <th class="no-sort">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($annees as $annee): ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($annee['annee']); ?></strong>
                                </td>
                                <td><?php echo date('d/m/Y', strtotime($annee['date_debut'])); ?></td>
                                <td><?php echo date('d/m/Y', strtotime($annee['date_fin'])); ?></td>
                                <td>
                                    <span class="badge bg-<?php echo $annee['status'] === 'active' ? 'success' : 'secondary'; ?>">
                                        <?php echo $annee['status'] === 'active' ? 'Active' : 'Fermée'; ?>
                                    </span>
                                </td>
                                <td><?php echo date('d/m/Y H:i', strtotime($annee['created_at'])); ?></td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <?php if (hasPagePermissionFromDB('academic', 'years', 'update')): ?>
                                        <a href="edit.php?id=<?php echo $annee['id']; ?>" 
                                           class="btn btn-outline-primary" 
                                           title="Modifier">
                                            <i class="fas fa-edit"></i>
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
                <i class="fas fa-calendar fa-3x text-muted mb-3"></i>
                <h5 class="text-muted">Aucune année scolaire trouvée</h5>
                <p class="text-muted">
                    <?php if (hasPagePermissionFromDB('academic', 'years', 'create')): ?>
                        <a href="add.php" class="btn btn-primary">
                            <i class="fas fa-plus me-1"></i>
                            Créer la première année scolaire
                        </a>
                    <?php else: ?>
                        Aucune année scolaire n'est encore configurée dans le système.
                    <?php endif; ?>
                </p>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include '../../../includes/footer.php'; ?>