<?php
/**
 * Module Bibliothèque - Page principale
 * Application de gestion scolaire - République Démocratique du Congo
 */

require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../includes/functions.php';

// Vérifier l'authentification et les permissions
requireLogin();
if (!checkPermission('library') && !checkPermission('library_view')) {
    showMessage('error', 'Accès refusé à ce module.');
    redirectTo('../index.php');
}

// Statistiques de la bibliothèque
try {
    $stats = [];
    
    // Nombre total de livres
    $stats['total_livres'] = $database->query("SELECT COUNT(*) as total FROM livres")->fetch()['total'];
    
    // Livres disponibles
    $stats['livres_disponibles'] = $database->query("SELECT COUNT(*) as total FROM livres WHERE status = 'disponible'")->fetch()['total'];
    
    // Emprunts actifs
    $stats['emprunts_actifs'] = $database->query("SELECT COUNT(*) as total FROM emprunts_livres WHERE status = 'en_cours'")->fetch()['total'];
    
    // Emprunts en retard
    $stats['emprunts_retard'] = $database->query(
        "SELECT COUNT(*) as total FROM emprunts_livres WHERE status = 'en_cours' AND date_retour_prevue < CURDATE()"
    )->fetch()['total'];
    
    // Réservations actives
    $stats['reservations_actives'] = $database->query("SELECT COUNT(*) as total FROM reservations_livres WHERE status = 'active'")->fetch()['total'];
    
    // Pénalités impayées
    $stats['penalites_impayees'] = $database->query("SELECT COUNT(*) as total FROM penalites_bibliotheque WHERE status = 'impayee'")->fetch()['total'];
    
} catch (Exception $e) {
    $stats = [
        'total_livres' => 0,
        'livres_disponibles' => 0,
        'emprunts_actifs' => 0,
        'emprunts_retard' => 0,
        'reservations_actives' => 0,
        'penalites_impayees' => 0
    ];
}

// Emprunts récents (derniers 7 jours)
try {
    $emprunts_recents = $database->query(
        "SELECT el.*, l.titre, l.auteur, l.isbn,
                CONCAT('Emprunteur ', el.emprunteur_id) as emprunteur_nom,
                CASE
                    WHEN el.emprunteur_type = 'eleve' THEN 'Élève'
                    WHEN el.emprunteur_type = 'personnel' THEN 'Personnel'
                    ELSE 'Inconnu'
                END as info_supplementaire
         FROM emprunts_livres el
         JOIN livres l ON el.livre_id = l.id
         WHERE el.date_emprunt >= DATE_SUB(NOW(), INTERVAL 7 DAY)
         ORDER BY el.date_emprunt DESC
         LIMIT 10"
    )->fetchAll();
} catch (Exception $e) {
    $emprunts_recents = [];
}

// Livres les plus empruntés
try {
    $livres_populaires = $database->query(
        "SELECT l.*, COUNT(el.id) as nb_emprunts
         FROM livres l
         LEFT JOIN emprunts_livres el ON l.id = el.livre_id
         GROUP BY l.id
         ORDER BY nb_emprunts DESC, l.titre
         LIMIT 8"
    )->fetchAll();
} catch (Exception $e) {
    $livres_populaires = [];
}

// Répartition par catégorie
try {
    $categories = $database->query(
        "SELECT cl.nom as categorie, cl.couleur, COUNT(l.id) as nombre
         FROM categories_livres cl
         LEFT JOIN livres l ON cl.id = l.categorie_id AND l.status != 'retire'
         GROUP BY cl.id, cl.nom, cl.couleur
         ORDER BY nombre DESC"
    )->fetchAll();
} catch (Exception $e) {
    // Si erreur avec la jointure, essayer une requête simplifiée
    try {
        $categories = $database->query(
            "SELECT nom as categorie, couleur, 0 as nombre
             FROM categories_livres
             ORDER BY nom"
        )->fetchAll();
    } catch (Exception $e2) {
        $categories = [];
    }
}

// Top lecteurs
try {
    $top_lecteurs = $database->query(
        "SELECT
            CONCAT('Emprunteur ', el.emprunteur_id) as nom_complet,
            CASE
                WHEN el.emprunteur_type = 'eleve' THEN 'Élève'
                WHEN el.emprunteur_type = 'personnel' THEN 'Personnel'
                ELSE 'Inconnu'
            END as info,
            el.emprunteur_type,
            COUNT(el.id) as nb_emprunts
         FROM emprunts_livres el
         GROUP BY el.emprunteur_type, el.emprunteur_id
         HAVING nb_emprunts > 0
         ORDER BY nb_emprunts DESC
         LIMIT 5"
    )->fetchAll();
} catch (Exception $e) {
    $top_lecteurs = [];
}

$page_title = 'Gestion de la Bibliothèque';
include '../../includes/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">
        <i class="fas fa-book me-2"></i>
        Gestion de la Bibliothèque
    </h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <div class="btn-group me-2">
            <a href="../" class="btn btn-outline-secondary">
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
                    <li><a class="dropdown-item" href="reservations/add.php">
                        <i class="fas fa-bookmark me-2"></i>Nouvelle réservation
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
                <li><a class="dropdown-item" href="reports/penalties.php">
                    <i class="fas fa-exclamation-triangle me-2"></i>Pénalités
                </a></li>
            </ul>
        </div>
    </div>
</div>

<!-- Statistiques rapides -->
<div class="row mb-4">
    <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
        <div class="card text-white bg-primary">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h4><?php echo number_format($stats['total_livres'] ?? 0); ?></h4>
                        <p class="mb-0 small">Total livres</p>
                    </div>
                    <div class="align-self-center">
                        <i class="fas fa-book fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
        <div class="card text-white bg-success">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h4><?php echo number_format($stats['livres_disponibles'] ?? 0); ?></h4>
                        <p class="mb-0 small">Disponibles</p>
                    </div>
                    <div class="align-self-center">
                        <i class="fas fa-check-circle fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
        <div class="card text-white bg-info">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h4><?php echo number_format($stats['emprunts_actifs'] ?? 0); ?></h4>
                        <p class="mb-0 small">Emprunts actifs</p>
                    </div>
                    <div class="align-self-center">
                        <i class="fas fa-hand-holding fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
        <div class="card text-white bg-warning">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h4><?php echo number_format($stats['emprunts_retard'] ?? 0); ?></h4>
                        <p class="mb-0 small">En retard</p>
                    </div>
                    <div class="align-self-center">
                        <i class="fas fa-exclamation-triangle fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
        <div class="card text-white bg-secondary">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h4><?php echo number_format($stats['reservations_actives'] ?? 0); ?></h4>
                        <p class="mb-0 small">Réservations</p>
                    </div>
                    <div class="align-self-center">
                        <i class="fas fa-bookmark fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
        <div class="card text-white bg-danger">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h4><?php echo number_format($stats['penalites_impayees'] ?? 0); ?></h4>
                        <p class="mb-0 small">Pénalités</p>
                    </div>
                    <div class="align-self-center">
                        <i class="fas fa-money-bill fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Contenu principal -->
<div class="row mb-4">
    <div class="col-lg-8">
        <!-- Emprunts récents -->
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-light">
                <h5 class="mb-0">
                    <i class="fas fa-clock me-2 text-primary"></i>
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
                                    <th>Emprunteur</th>
                                    <th>Type</th>
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
                                            <?php echo htmlspecialchars($emprunt['emprunteur_nom']); ?>
                                            <br><small class="text-muted">
                                                <?php echo htmlspecialchars($emprunt['info_supplementaire']); ?>
                                            </small>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?php echo $emprunt['emprunteur_type'] === 'eleve' ? 'primary' : 'secondary'; ?>">
                                                <?php echo $emprunt['emprunteur_type'] === 'eleve' ? 'Élève' : 'Personnel'; ?>
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
                                                'en_retard' => 'warning',
                                                'perdu' => 'danger',
                                                'annule' => 'secondary'
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
        <div class="card shadow-sm">
            <div class="card-header bg-light">
                <h5 class="mb-0">
                    <i class="fas fa-star me-2 text-warning"></i>
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
                    <div class="text-center">
                        <a href="books/" class="btn btn-outline-secondary">
                            <i class="fas fa-book me-1"></i>
                            Voir le catalogue complet
                        </a>
                    </div>
                <?php else: ?>
                    <p class="text-muted text-center">Aucun livre dans le catalogue</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <!-- Catégories -->
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-light">
                <h5 class="mb-0">
                    <i class="fas fa-tags me-2 text-info"></i>
                    Répartition par catégorie
                </h5>
            </div>
            <div class="card-body">
                <?php if (!empty($categories)): ?>
                    <?php foreach ($categories as $categorie): ?>
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <div class="d-flex align-items-center">
                                <span class="badge me-2" style="background-color: <?php echo $categorie['couleur']; ?>">
                                    &nbsp;
                                </span>
                                <span><?php echo htmlspecialchars($categorie['categorie']); ?></span>
                            </div>
                            <span class="badge bg-primary"><?php echo $categorie['nombre']; ?></span>
                        </div>
                    <?php endforeach; ?>
                    <div class="text-center mt-3">
                        <a href="books/categories.php" class="btn btn-outline-secondary btn-sm">
                            <i class="fas fa-cog me-1"></i>
                            Gérer les catégories
                        </a>
                    </div>
                <?php else: ?>
                    <p class="text-muted text-center">Aucune catégorie définie</p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Top lecteurs -->
        <div class="card shadow-sm">
            <div class="card-header bg-light">
                <h5 class="mb-0">
                    <i class="fas fa-trophy me-2 text-success"></i>
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
                                        <strong><?php echo htmlspecialchars($lecteur['nom_complet']); ?></strong>
                                        <br><small class="text-muted">
                                            <?php echo htmlspecialchars($lecteur['info']); ?>
                                            <span class="badge bg-<?php echo $lecteur['emprunteur_type'] === 'eleve' ? 'primary' : 'secondary'; ?> ms-1">
                                                <?php echo $lecteur['emprunteur_type'] === 'eleve' ? 'Élève' : 'Personnel'; ?>
                                            </span>
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
                    <div class="col-md-2 mb-2">
                        <div class="d-grid">
                            <a href="books/add.php" class="btn btn-outline-primary">
                                <i class="fas fa-book me-2"></i>
                                Ajouter livre
                            </a>
                        </div>
                    </div>
                    <div class="col-md-2 mb-2">
                        <div class="d-grid">
                            <a href="loans/add.php" class="btn btn-outline-success">
                                <i class="fas fa-hand-holding me-2"></i>
                                Nouvel emprunt
                            </a>
                        </div>
                    </div>
                    <div class="col-md-2 mb-2">
                        <div class="d-grid">
                            <a href="loans/returns.php" class="btn btn-outline-warning">
                                <i class="fas fa-undo me-2"></i>
                                Retours
                            </a>
                        </div>
                    </div>
                    <div class="col-md-2 mb-2">
                        <div class="d-grid">
                            <a href="reservations/add.php" class="btn btn-outline-info">
                                <i class="fas fa-bookmark me-2"></i>
                                Réservation
                            </a>
                        </div>
                    </div>
                    <div class="col-md-2 mb-2">
                        <div class="d-grid">
                            <a href="reports/inventory.php" class="btn btn-outline-secondary">
                                <i class="fas fa-list me-2"></i>
                                Inventaire
                            </a>
                        </div>
                    </div>
                    <div class="col-md-2 mb-2">
                        <div class="d-grid">
                            <a href="settings/" class="btn btn-outline-dark">
                                <i class="fas fa-cog me-2"></i>
                                Paramètres
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
    transition: all 0.3s ease-in-out;
    border: 1px solid #e9ecef;
}

.hover-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15) !important;
    border-color: #007bff;
}

.card {
    border: none;
    border-radius: 10px;
}

.card-header {
    border-radius: 10px 10px 0 0 !important;
    border: none;
}

.badge {
    font-size: 0.75em;
    padding: 0.5em 0.75em;
}

.stats-card {
    transition: all 0.2s ease-in-out;
}

.stats-card:hover {
    transform: translateY(-2px);
}
</style>

<!-- Modules de la bibliothèque -->
<div class="row mb-5">
    <div class="col-12">
        <div class="card shadow-sm">
            <div class="card-header bg-primary text-white">
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
                                        <span class="badge bg-primary"><?php echo number_format($stats['total_livres']); ?> livres</span>
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
                                        <span class="badge bg-success"><?php echo number_format($stats['emprunts_actifs']); ?> actifs</span>
                                    </div>
                                </div>
                            </div>
                        </a>
                    </div>
                    
                    <div class="col-lg-3 col-md-6 mb-3">
                        <a href="reservations/" class="text-decoration-none">
                            <div class="card h-100 border-0 shadow-sm hover-card">
                                <div class="card-body text-center">
                                    <i class="fas fa-bookmark fa-3x text-info mb-3"></i>
                                    <h5 class="card-title">Réservations</h5>
                                    <p class="card-text text-muted">
                                        Gestion des réservations
                                    </p>
                                    <div class="mt-3">
                                        <span class="badge bg-info"><?php echo number_format($stats['reservations_actives']); ?> actives</span>
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

<?php include '../../includes/footer.php'; ?>
