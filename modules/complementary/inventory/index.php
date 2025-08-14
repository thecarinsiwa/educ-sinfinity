<?php
/**
 * Module Inventaire - Page principale
 * Application de gestion scolaire - République Démocratique du Congo
 */

require_once '../../../config/config.php';
require_once '../../../config/database.php';
require_once '../../../includes/functions.php';

// Vérifier l'authentification et les permissions
requireLogin();
if (!checkPermission('inventory') && !checkPermission('inventory_view')) {
    showMessage('error', 'Accès refusé à ce module.');
    redirectTo('../index.php');
}

$page_title = 'Gestion de l\'Inventaire';

// Statistiques de l'inventaire
$stats = [];

// Nombre total d'articles
$stmt = $database->query("SELECT COUNT(*) as total FROM inventaire WHERE status != 'supprime'");
$stats['total_articles'] = $stmt->fetch()['total'];

// Articles actifs
$stmt = $database->query("SELECT COUNT(*) as total FROM inventaire WHERE status = 'actif'");
$stats['articles_actifs'] = $stmt->fetch()['total'];

// Valeur totale
$stmt = $database->query("SELECT SUM(valeur_totale) as total FROM inventaire WHERE status = 'actif'");
$stats['valeur_totale'] = $stmt->fetch()['total'] ?? 0;

// Articles en maintenance
$stmt = $database->query("SELECT COUNT(*) as total FROM inventaire WHERE status = 'maintenance'");
$stats['articles_maintenance'] = $stmt->fetch()['total'];

// Articles par catégorie
$categories = $database->query(
    "SELECT categorie, COUNT(*) as nombre, SUM(valeur_totale) as valeur_totale
     FROM inventaire
     WHERE status = 'actif'
     GROUP BY categorie
     ORDER BY valeur_totale DESC"
)->fetchAll();

// Articles récents (derniers 30 jours)
$articles_recents = $database->query(
    "SELECT * FROM inventaire
     WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
     AND status != 'supprime'
     ORDER BY created_at DESC
     LIMIT 10"
)->fetchAll();

// Articles nécessitant attention
$articles_attention = $database->query(
    "SELECT * FROM inventaire
     WHERE (status = 'maintenance' OR 
            (date_garantie IS NOT NULL AND date_garantie <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)) OR
            (quantite_stock <= quantite_min AND quantite_min > 0))
     AND status != 'supprime'
     ORDER BY 
        CASE 
            WHEN status = 'maintenance' THEN 1
            WHEN date_garantie <= CURDATE() THEN 2
            WHEN quantite_stock <= quantite_min THEN 3
            ELSE 4
        END,
        created_at DESC
     LIMIT 8"
)->fetchAll();

// Mouvements récents
$mouvements_recents = $database->query(
    "SELECT m.*, i.nom as article_nom, u.username as utilisateur_nom
     FROM mouvements_inventaire m
     JOIN inventaire i ON m.article_id = i.id
     LEFT JOIN users u ON m.user_id = u.id
     ORDER BY m.created_at DESC
     LIMIT 8"
)->fetchAll();

// Articles les plus utilisés
$articles_populaires = $database->query(
    "SELECT i.*, COUNT(m.id) as nb_mouvements
     FROM inventaire i
     LEFT JOIN mouvements_inventaire m ON i.id = m.article_id
     WHERE i.status = 'actif'
     GROUP BY i.id
     ORDER BY nb_mouvements DESC, i.nom
     LIMIT 6"
)->fetchAll();

include '../../../includes/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">
        <i class="fas fa-boxes me-2"></i>
        Gestion de l'Inventaire
    </h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <div class="btn-group me-2">
            <a href="../index.php" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-1"></i>
                Retour
            </a>
        </div>
        <?php if (checkPermission('inventory')): ?>
            <div class="btn-group me-2">
                <button type="button" class="btn btn-dark dropdown-toggle" data-bs-toggle="dropdown">
                    <i class="fas fa-plus me-1"></i>
                    Nouveau
                </button>
                <ul class="dropdown-menu">
                    <li><a class="dropdown-item" href="items/add.php">
                        <i class="fas fa-box me-2"></i>Nouvel article
                    </a></li>
                    <li><a class="dropdown-item" href="movements/add.php">
                        <i class="fas fa-exchange-alt me-2"></i>Nouveau mouvement
                    </a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item" href="categories/manage.php">
                        <i class="fas fa-tags me-2"></i>Gérer catégories
                    </a></li>
                </ul>
            </div>
        <?php endif; ?>
        <div class="btn-group">
            <button type="button" class="btn btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">
                <i class="fas fa-chart-bar me-1"></i>
                Rapports
            </button>
            <ul class="dropdown-menu">
                <li><a class="dropdown-item" href="reports/inventory.php">
                    <i class="fas fa-list me-2"></i>Inventaire complet
                </a></li>
                <li><a class="dropdown-item" href="reports/valuation.php">
                    <i class="fas fa-dollar-sign me-2"></i>Valorisation
                </a></li>
                <li><a class="dropdown-item" href="reports/movements.php">
                    <i class="fas fa-exchange-alt me-2"></i>Mouvements
                </a></li>
            </ul>
        </div>
    </div>
</div>

<!-- Statistiques rapides -->
<div class="row mb-4">
    <div class="col-lg-3 col-md-6 mb-3">
        <div class="card text-white bg-primary">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h4><?php echo $stats['total_articles']; ?></h4>
                        <p class="mb-0">Total articles</p>
                    </div>
                    <div class="align-self-center">
                        <i class="fas fa-boxes fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-3 col-md-6 mb-3">
        <div class="card text-white bg-success">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h4><?php echo $stats['articles_actifs']; ?></h4>
                        <p class="mb-0">Articles actifs</p>
                    </div>
                    <div class="align-self-center">
                        <i class="fas fa-check-circle fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-3 col-md-6 mb-3">
        <div class="card text-white bg-info">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h4><?php echo formatMoney($stats['valeur_totale']); ?></h4>
                        <p class="mb-0">Valeur totale</p>
                    </div>
                    <div class="align-self-center">
                        <i class="fas fa-dollar-sign fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-3 col-md-6 mb-3">
        <div class="card text-white bg-warning">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h4><?php echo count($articles_attention); ?></h4>
                        <p class="mb-0">Alertes</p>
                    </div>
                    <div class="align-self-center">
                        <i class="fas fa-exclamation-triangle fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modules de l'inventaire -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-th-large me-2"></i>
                    Modules de l'inventaire
                </h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-lg-3 col-md-6 mb-3">
                        <a href="items/" class="text-decoration-none">
                            <div class="card h-100 border-0 shadow-sm hover-card">
                                <div class="card-body text-center">
                                    <i class="fas fa-box fa-3x text-primary mb-3"></i>
                                    <h5 class="card-title">Articles</h5>
                                    <p class="card-text text-muted">
                                        Gestion des articles et équipements
                                    </p>
                                    <div class="mt-3">
                                        <span class="badge bg-primary"><?php echo $stats['total_articles']; ?> articles</span>
                                    </div>
                                </div>
                            </div>
                        </a>
                    </div>
                    
                    <div class="col-lg-3 col-md-6 mb-3">
                        <a href="movements/" class="text-decoration-none">
                            <div class="card h-100 border-0 shadow-sm hover-card">
                                <div class="card-body text-center">
                                    <i class="fas fa-exchange-alt fa-3x text-success mb-3"></i>
                                    <h5 class="card-title">Mouvements</h5>
                                    <p class="card-text text-muted">
                                        Entrées, sorties et transferts
                                    </p>
                                    <div class="mt-3">
                                        <span class="badge bg-success">Suivi des flux</span>
                                    </div>
                                </div>
                            </div>
                        </a>
                    </div>
                    
                    <div class="col-lg-3 col-md-6 mb-3">
                        <a href="maintenance/" class="text-decoration-none">
                            <div class="card h-100 border-0 shadow-sm hover-card">
                                <div class="card-body text-center">
                                    <i class="fas fa-tools fa-3x text-warning mb-3"></i>
                                    <h5 class="card-title">Maintenance</h5>
                                    <p class="card-text text-muted">
                                        Suivi de la maintenance
                                    </p>
                                    <div class="mt-3">
                                        <span class="badge bg-warning"><?php echo $stats['articles_maintenance']; ?> en maintenance</span>
                                    </div>
                                </div>
                            </div>
                        </a>
                    </div>
                    
                    <div class="col-lg-3 col-md-6 mb-3">
                        <a href="reports/" class="text-decoration-none">
                            <div class="card h-100 border-0 shadow-sm hover-card">
                                <div class="card-body text-center">
                                    <i class="fas fa-chart-bar fa-3x text-info mb-3"></i>
                                    <h5 class="card-title">Rapports</h5>
                                    <p class="card-text text-muted">
                                        Analyses et statistiques
                                    </p>
                                    <div class="mt-3">
                                        <span class="badge bg-info">Valorisation & Analyses</span>
                                    </div>
                                </div>
                            </div>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Contenu principal -->
<div class="row">
    <div class="col-lg-8">
        <!-- Articles récents -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-clock me-2"></i>
                    Articles récents (30 derniers jours)
                </h5>
            </div>
            <div class="card-body">
                <?php if (!empty($articles_recents)): ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Article</th>
                                    <th>Catégorie</th>
                                    <th>Quantité</th>
                                    <th>Valeur unitaire</th>
                                    <th>Statut</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($articles_recents as $article): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo htmlspecialchars($article['nom']); ?></strong>
                                            <?php if ($article['code_barre']): ?>
                                                <br><small class="text-muted">
                                                    Code: <?php echo htmlspecialchars($article['code_barre']); ?>
                                                </small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="badge bg-secondary">
                                                <?php echo htmlspecialchars($article['categorie']); ?>
                                            </span>
                                        </td>
                                        <td class="text-center">
                                            <?php echo $article['quantite_stock']; ?>
                                            <?php if ($article['unite']): ?>
                                                <small class="text-muted"><?php echo htmlspecialchars($article['unite']); ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-end">
                                            <?php echo formatMoney($article['valeur_unitaire']); ?>
                                        </td>
                                        <td>
                                            <?php
                                            $status_colors = [
                                                'actif' => 'success',
                                                'maintenance' => 'warning',
                                                'hors_service' => 'danger',
                                                'reserve' => 'info'
                                            ];
                                            $color = $status_colors[$article['status']] ?? 'secondary';
                                            ?>
                                            <span class="badge bg-<?php echo $color; ?>">
                                                <?php echo ucfirst(str_replace('_', ' ', $article['status'])); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <a href="items/view.php?id=<?php echo $article['id']; ?>" 
                                                   class="btn btn-outline-info" title="Voir">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                <?php if (checkPermission('inventory')): ?>
                                                    <a href="items/edit.php?id=<?php echo $article['id']; ?>" 
                                                       class="btn btn-outline-primary" title="Modifier">
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
                    <div class="text-center py-4">
                        <i class="fas fa-box fa-3x text-muted mb-3"></i>
                        <p class="text-muted">Aucun article récent</p>
                        <?php if (checkPermission('inventory')): ?>
                            <a href="items/add.php" class="btn btn-primary">
                                <i class="fas fa-plus me-1"></i>
                                Ajouter un article
                            </a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Articles nécessitant attention -->
        <?php if (!empty($articles_attention)): ?>
        <div class="card">
            <div class="card-header bg-warning text-dark">
                <h5 class="mb-0">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    Articles nécessitant attention
                </h5>
            </div>
            <div class="card-body">
                <div class="list-group list-group-flush">
                    <?php foreach ($articles_attention as $article): ?>
                        <div class="list-group-item d-flex justify-content-between align-items-start">
                            <div class="ms-2 me-auto">
                                <div class="fw-bold"><?php echo htmlspecialchars($article['nom']); ?></div>
                                <small class="text-muted">
                                    <?php if ($article['status'] === 'maintenance'): ?>
                                        <i class="fas fa-tools text-warning me-1"></i>En maintenance
                                    <?php elseif ($article['date_garantie'] && $article['date_garantie'] <= date('Y-m-d')): ?>
                                        <i class="fas fa-calendar-times text-danger me-1"></i>Garantie expirée
                                    <?php elseif ($article['quantite_stock'] <= $article['quantite_min']): ?>
                                        <i class="fas fa-exclamation-circle text-warning me-1"></i>Stock faible
                                    <?php endif; ?>
                                </small>
                            </div>
                            <div>
                                <a href="items/view.php?id=<?php echo $article['id']; ?>" 
                                   class="btn btn-sm btn-outline-primary">
                                    <i class="fas fa-eye"></i>
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
    
    <div class="col-lg-4">
        <!-- Répartition par catégorie -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-chart-pie me-2"></i>
                    Répartition par catégorie
                </h5>
            </div>
            <div class="card-body">
                <?php if (!empty($categories)): ?>
                    <?php foreach ($categories as $categorie): ?>
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <div>
                                <strong><?php echo htmlspecialchars($categorie['categorie']); ?></strong>
                                <br><small class="text-muted">
                                    <?php echo formatMoney($categorie['valeur_totale']); ?>
                                </small>
                            </div>
                            <span class="badge bg-primary"><?php echo $categorie['nombre']; ?></span>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p class="text-muted text-center">Aucune catégorie définie</p>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Mouvements récents -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-exchange-alt me-2"></i>
                    Mouvements récents
                </h5>
            </div>
            <div class="card-body">
                <?php if (!empty($mouvements_recents)): ?>
                    <div class="list-group list-group-flush">
                        <?php foreach (array_slice($mouvements_recents, 0, 6) as $mouvement): ?>
                            <div class="list-group-item">
                                <div class="d-flex w-100 justify-content-between">
                                    <h6 class="mb-1">
                                        <?php
                                        $type_icons = [
                                            'entree' => 'fas fa-arrow-down text-success',
                                            'sortie' => 'fas fa-arrow-up text-danger',
                                            'transfert' => 'fas fa-exchange-alt text-info'
                                        ];
                                        $icon = $type_icons[$mouvement['type_mouvement']] ?? 'fas fa-circle';
                                        ?>
                                        <i class="<?php echo $icon; ?> me-1"></i>
                                        <?php echo ucfirst($mouvement['type_mouvement']); ?>
                                    </h6>
                                    <small><?php echo formatDateTime($mouvement['created_at']); ?></small>
                                </div>
                                <p class="mb-1"><?php echo htmlspecialchars($mouvement['article_nom']); ?></p>
                                <small>
                                    Quantité: <?php echo $mouvement['quantite']; ?>
                                    <?php if ($mouvement['utilisateur_nom']): ?>
                                        - Par: <?php echo htmlspecialchars($mouvement['utilisateur_nom']); ?>
                                    <?php endif; ?>
                                </small>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="text-center mt-3">
                        <a href="movements/" class="btn btn-sm btn-outline-primary">
                            <i class="fas fa-list me-1"></i>
                            Voir tous
                        </a>
                    </div>
                <?php else: ?>
                    <p class="text-muted text-center">Aucun mouvement récent</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Actions rapides -->
<?php if (checkPermission('inventory')): ?>
<div class="row mt-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-bolt me-2"></i>
                    Actions rapides
                </h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-3 mb-2">
                        <div class="d-grid">
                            <a href="items/add.php" class="btn btn-outline-primary">
                                <i class="fas fa-box me-2"></i>
                                Nouvel article
                            </a>
                        </div>
                    </div>
                    <div class="col-md-3 mb-2">
                        <div class="d-grid">
                            <a href="movements/add.php" class="btn btn-outline-success">
                                <i class="fas fa-exchange-alt me-2"></i>
                                Nouveau mouvement
                            </a>
                        </div>
                    </div>
                    <div class="col-md-3 mb-2">
                        <div class="d-grid">
                            <a href="maintenance/schedule.php" class="btn btn-outline-warning">
                                <i class="fas fa-tools me-2"></i>
                                Planifier maintenance
                            </a>
                        </div>
                    </div>
                    <div class="col-md-3 mb-2">
                        <div class="d-grid">
                            <a href="reports/inventory.php" class="btn btn-outline-info">
                                <i class="fas fa-list me-2"></i>
                                Inventaire complet
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<style>
.hover-card {
    transition: transform 0.2s ease-in-out;
}

.hover-card:hover {
    transform: translateY(-5px);
}
</style>

<?php include '../../../includes/footer.php'; ?>
