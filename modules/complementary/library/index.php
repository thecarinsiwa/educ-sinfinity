<?php
/**
 * Module Bibliothèque - Page principale
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

$page_title = 'Gestion de la Bibliothèque';

// Statistiques de la bibliothèque
$stats = [];

// Nombre total de livres
$stmt = $database->query("SELECT COUNT(*) as total FROM livres");
$stats['total_livres'] = $stmt->fetch()['total'];

// Livres disponibles
$stmt = $database->query("SELECT COUNT(*) as total FROM livres WHERE status = 'disponible'");
$stats['livres_disponibles'] = $stmt->fetch()['total'];

// Emprunts actifs
$stmt = $database->query("SELECT COUNT(*) as total FROM emprunts WHERE status = 'en_cours'");
$stats['emprunts_actifs'] = $stmt->fetch()['total'];

// Emprunts en retard
$stmt = $database->query(
    "SELECT COUNT(*) as total FROM emprunts WHERE status = 'en_cours' AND date_retour_prevue < CURDATE()"
);
$stats['emprunts_retard'] = $stmt->fetch()['total'];

// Emprunts récents (derniers 7 jours)
$emprunts_recents = $database->query(
    "SELECT e.*, l.titre, l.auteur, l.isbn,
            el.nom as eleve_nom, el.prenom as eleve_prenom, el.numero_matricule,
            c.nom as classe_nom
     FROM emprunts e
     JOIN livres l ON e.livre_id = l.id
     JOIN eleves el ON e.eleve_id = el.id
     JOIN inscriptions i ON el.id = i.eleve_id
     JOIN classes c ON i.classe_id = c.id
     WHERE e.date_emprunt >= DATE_SUB(NOW(), INTERVAL 7 DAY)
     ORDER BY e.date_emprunt DESC
     LIMIT 10"
)->fetchAll();

// Livres les plus empruntés
$livres_populaires = $database->query(
    "SELECT l.*, COUNT(e.id) as nb_emprunts
     FROM livres l
     LEFT JOIN emprunts e ON l.id = e.livre_id
     GROUP BY l.id
     ORDER BY nb_emprunts DESC, l.titre
     LIMIT 8"
)->fetchAll();

// Répartition par catégorie
$categories = $database->query(
    "SELECT categorie, COUNT(*) as nombre
     FROM livres
     WHERE status != 'retire'
     GROUP BY categorie
     ORDER BY nombre DESC"
)->fetchAll();

// Élèves avec le plus d'emprunts
$top_lecteurs = $database->query(
    "SELECT el.nom, el.prenom, el.numero_matricule, c.nom as classe_nom,
            COUNT(e.id) as nb_emprunts
     FROM eleves el
     JOIN inscriptions i ON el.id = i.eleve_id
     JOIN classes c ON i.classe_id = c.id
     LEFT JOIN emprunts e ON el.id = e.eleve_id
     WHERE i.status = 'inscrit'
     GROUP BY el.id
     HAVING nb_emprunts > 0
     ORDER BY nb_emprunts DESC
     LIMIT 5"
)->fetchAll();

include '../../../includes/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">
        <i class="fas fa-book me-2"></i>
        Gestion de la Bibliothèque
    </h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <div class="btn-group me-2">
            <a href="../index.php" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-1"></i>
                Retour
            </a>
        </div>
        <?php if (checkPermission('library')): ?>
            <div class="btn-group me-2">
                <button type="button" class="btn btn-primary dropdown-toggle" data-bs-toggle="dropdown">
                    <i class="fas fa-plus me-1"></i>
                    Nouveau
                </button>
                <ul class="dropdown-menu">
                    <li><a class="dropdown-item" href="books/add.php">
                        <i class="fas fa-book me-2"></i>Ajouter un livre
                    </a></li>
                    <li><a class="dropdown-item" href="loans/add.php">
                        <i class="fas fa-hand-holding me-2"></i>Nouvel emprunt
                    </a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item" href="books/import.php">
                        <i class="fas fa-upload me-2"></i>Import en lot
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
                    <i class="fas fa-list me-2"></i>Inventaire
                </a></li>
                <li><a class="dropdown-item" href="reports/loans.php">
                    <i class="fas fa-exchange-alt me-2"></i>Emprunts
                </a></li>
                <li><a class="dropdown-item" href="reports/statistics.php">
                    <i class="fas fa-chart-pie me-2"></i>Statistiques
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
                        <h4><?php echo $stats['total_livres']; ?></h4>
                        <p class="mb-0">Total livres</p>
                    </div>
                    <div class="align-self-center">
                        <i class="fas fa-book fa-2x"></i>
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
                        <h4><?php echo $stats['livres_disponibles']; ?></h4>
                        <p class="mb-0">Disponibles</p>
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
                        <h4><?php echo $stats['emprunts_actifs']; ?></h4>
                        <p class="mb-0">Emprunts actifs</p>
                    </div>
                    <div class="align-self-center">
                        <i class="fas fa-hand-holding fa-2x"></i>
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
                        <h4><?php echo $stats['emprunts_retard']; ?></h4>
                        <p class="mb-0">En retard</p>
                    </div>
                    <div class="align-self-center">
                        <i class="fas fa-exclamation-triangle fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modules de la bibliothèque -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-th-large me-2"></i>
                    Modules de la bibliothèque
                </h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-lg-3 col-md-6 mb-3">
                        <a href="books/" class="text-decoration-none">
                            <div class="card h-100 border-0 shadow-sm hover-card">
                                <div class="card-body text-center">
                                    <i class="fas fa-book fa-3x text-primary mb-3"></i>
                                    <h5 class="card-title">Catalogue</h5>
                                    <p class="card-text text-muted">
                                        Gestion des livres et du catalogue
                                    </p>
                                    <div class="mt-3">
                                        <span class="badge bg-primary"><?php echo $stats['total_livres']; ?> livres</span>
                                    </div>
                                </div>
                            </div>
                        </a>
                    </div>
                    
                    <div class="col-lg-3 col-md-6 mb-3">
                        <a href="loans/" class="text-decoration-none">
                            <div class="card h-100 border-0 shadow-sm hover-card">
                                <div class="card-body text-center">
                                    <i class="fas fa-exchange-alt fa-3x text-success mb-3"></i>
                                    <h5 class="card-title">Emprunts</h5>
                                    <p class="card-text text-muted">
                                        Gestion des emprunts et retours
                                    </p>
                                    <div class="mt-3">
                                        <span class="badge bg-success"><?php echo $stats['emprunts_actifs']; ?> actifs</span>
                                    </div>
                                </div>
                            </div>
                        </a>
                    </div>
                    
                    <div class="col-lg-3 col-md-6 mb-3">
                        <a href="readers/" class="text-decoration-none">
                            <div class="card h-100 border-0 shadow-sm hover-card">
                                <div class="card-body text-center">
                                    <i class="fas fa-users fa-3x text-info mb-3"></i>
                                    <h5 class="card-title">Lecteurs</h5>
                                    <p class="card-text text-muted">
                                        Gestion des abonnés et lecteurs
                                    </p>
                                    <div class="mt-3">
                                        <span class="badge bg-info">Élèves & Personnel</span>
                                    </div>
                                </div>
                            </div>
                        </a>
                    </div>
                    
                    <div class="col-lg-3 col-md-6 mb-3">
                        <a href="reports/" class="text-decoration-none">
                            <div class="card h-100 border-0 shadow-sm hover-card">
                                <div class="card-body text-center">
                                    <i class="fas fa-chart-bar fa-3x text-warning mb-3"></i>
                                    <h5 class="card-title">Rapports</h5>
                                    <p class="card-text text-muted">
                                        Statistiques et analyses
                                    </p>
                                    <div class="mt-3">
                                        <span class="badge bg-warning">Analyses</span>
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
        <!-- Emprunts récents -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-clock me-2"></i>
                    Emprunts récents (7 derniers jours)
                </h5>
            </div>
            <div class="card-body">
                <?php if (!empty($emprunts_recents)): ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Livre</th>
                                    <th>Élève</th>
                                    <th>Classe</th>
                                    <th>Retour prévu</th>
                                    <th>Statut</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($emprunts_recents as $emprunt): ?>
                                    <tr>
                                        <td><?php echo formatDate($emprunt['date_emprunt']); ?></td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($emprunt['titre']); ?></strong>
                                            <br><small class="text-muted">
                                                <?php echo htmlspecialchars($emprunt['auteur']); ?>
                                            </small>
                                        </td>
                                        <td>
                                            <?php echo htmlspecialchars($emprunt['eleve_nom'] . ' ' . $emprunt['eleve_prenom']); ?>
                                            <br><small class="text-muted">
                                                <?php echo htmlspecialchars($emprunt['numero_matricule']); ?>
                                            </small>
                                        </td>
                                        <td>
                                            <span class="badge bg-secondary">
                                                <?php echo htmlspecialchars($emprunt['classe_nom']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php echo formatDate($emprunt['date_retour_prevue']); ?>
                                            <?php if ($emprunt['date_retour_prevue'] < date('Y-m-d') && $emprunt['status'] === 'en_cours'): ?>
                                                <br><small class="text-danger">En retard</small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php
                                            $status_colors = [
                                                'en_cours' => 'info',
                                                'rendu' => 'success',
                                                'perdu' => 'danger'
                                            ];
                                            $color = $status_colors[$emprunt['status']] ?? 'secondary';
                                            ?>
                                            <span class="badge bg-<?php echo $color; ?>">
                                                <?php echo ucfirst(str_replace('_', ' ', $emprunt['status'])); ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="text-center">
                        <a href="loans/" class="btn btn-outline-secondary">
                            <i class="fas fa-list me-1"></i>
                            Voir tous les emprunts
                        </a>
                    </div>
                <?php else: ?>
                    <div class="text-center py-4">
                        <i class="fas fa-exchange-alt fa-3x text-muted mb-3"></i>
                        <p class="text-muted">Aucun emprunt récent</p>
                        <?php if (checkPermission('library')): ?>
                            <a href="loans/add.php" class="btn btn-primary">
                                <i class="fas fa-plus me-1"></i>
                                Nouvel emprunt
                            </a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Livres populaires -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-star me-2"></i>
                    Livres les plus empruntés
                </h5>
            </div>
            <div class="card-body">
                <?php if (!empty($livres_populaires)): ?>
                    <div class="row">
                        <?php foreach ($livres_populaires as $livre): ?>
                            <div class="col-md-6 mb-3">
                                <div class="card border-0 shadow-sm">
                                    <div class="card-body">
                                        <h6 class="card-title"><?php echo htmlspecialchars($livre['titre']); ?></h6>
                                        <p class="card-text">
                                            <small class="text-muted">
                                                <?php echo htmlspecialchars($livre['auteur']); ?>
                                            </small>
                                        </p>
                                        <div class="d-flex justify-content-between align-items-center">
                                            <span class="badge bg-<?php echo $livre['status'] === 'disponible' ? 'success' : 'warning'; ?>">
                                                <?php echo ucfirst($livre['status']); ?>
                                            </span>
                                            <small class="text-muted">
                                                <?php echo $livre['nb_emprunts']; ?> emprunt<?php echo $livre['nb_emprunts'] > 1 ? 's' : ''; ?>
                                            </small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p class="text-muted text-center">Aucun livre dans le catalogue</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <div class="col-lg-4">
        <!-- Catégories -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-tags me-2"></i>
                    Répartition par catégorie
                </h5>
            </div>
            <div class="card-body">
                <?php if (!empty($categories)): ?>
                    <?php foreach ($categories as $categorie): ?>
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span><?php echo htmlspecialchars($categorie['categorie']); ?></span>
                            <span class="badge bg-primary"><?php echo $categorie['nombre']; ?></span>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p class="text-muted text-center">Aucune catégorie définie</p>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Top lecteurs -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-trophy me-2"></i>
                    Top lecteurs
                </h5>
            </div>
            <div class="card-body">
                <?php if (!empty($top_lecteurs)): ?>
                    <?php foreach ($top_lecteurs as $index => $lecteur): ?>
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <div>
                                <div class="d-flex align-items-center">
                                    <span class="badge bg-<?php echo $index < 3 ? 'warning' : 'secondary'; ?> me-2">
                                        <?php echo $index + 1; ?>
                                    </span>
                                    <div>
                                        <strong><?php echo htmlspecialchars($lecteur['nom'] . ' ' . $lecteur['prenom']); ?></strong>
                                        <br><small class="text-muted">
                                            <?php echo htmlspecialchars($lecteur['classe_nom']); ?>
                                        </small>
                                    </div>
                                </div>
                            </div>
                            <span class="badge bg-success">
                                <?php echo $lecteur['nb_emprunts']; ?>
                            </span>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p class="text-muted text-center">Aucun emprunt enregistré</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Actions rapides -->
<?php if (checkPermission('library')): ?>
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
                            <a href="books/add.php" class="btn btn-outline-primary">
                                <i class="fas fa-book me-2"></i>
                                Ajouter livre
                            </a>
                        </div>
                    </div>
                    <div class="col-md-3 mb-2">
                        <div class="d-grid">
                            <a href="loans/add.php" class="btn btn-outline-success">
                                <i class="fas fa-hand-holding me-2"></i>
                                Nouvel emprunt
                            </a>
                        </div>
                    </div>
                    <div class="col-md-3 mb-2">
                        <div class="d-grid">
                            <a href="loans/returns.php" class="btn btn-outline-warning">
                                <i class="fas fa-undo me-2"></i>
                                Retours
                            </a>
                        </div>
                    </div>
                    <div class="col-md-3 mb-2">
                        <div class="d-grid">
                            <a href="reports/inventory.php" class="btn btn-outline-info">
                                <i class="fas fa-list me-2"></i>
                                Inventaire
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
