<?php
/**
 * Modules complémentaires - Page principale
 * Application de gestion scolaire - République Démocratique du Congo
 */

require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../includes/functions.php';

// Vérifier l'authentification
requireLogin();

$page_title = 'Modules Complémentaires';

// Obtenir l'année scolaire actuelle
$current_year = getCurrentAcademicYear();

// Statistiques générales pour chaque module
$stats = [];

// Bibliothèque
if (checkPermission('library') || checkPermission('library_view')) {
    $stmt = $database->query("SELECT COUNT(*) as total FROM livres WHERE status = 'disponible'");
    $stats['library']['livres_disponibles'] = $stmt->fetch()['total'];
    
    $stmt = $database->query("SELECT COUNT(*) as total FROM emprunts WHERE status = 'en_cours'");
    $stats['library']['emprunts_actifs'] = $stmt->fetch()['total'];
}

// Discipline
if (checkPermission('discipline') || checkPermission('discipline_view')) {
    $stmt = $database->query(
        "SELECT COUNT(*) as total FROM incidents WHERE annee_scolaire_id = ? AND status != 'resolu'",
        [$current_year['id'] ?? 0]
    );
    $stats['discipline']['incidents_ouverts'] = $stmt->fetch()['total'];
    
    $stmt = $database->query(
        "SELECT COUNT(*) as total FROM sanctions WHERE annee_scolaire_id = ? AND status = 'active'",
        [$current_year['id'] ?? 0]
    );
    $stats['discipline']['sanctions_actives'] = $stmt->fetch()['total'];
}

// Communication
if (checkPermission('communication') || checkPermission('communication_view')) {
    $stmt = $database->query(
        "SELECT COUNT(*) as total FROM messages WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)"
    );
    $stats['communication']['messages_semaine'] = $stmt->fetch()['total'];
    
    $stmt = $database->query(
        "SELECT COUNT(*) as total FROM notifications WHERE status = 'non_lu'"
    );
    $stats['communication']['notifications_non_lues'] = $stmt->fetch()['total'];
}

// Internat
if (checkPermission('internat') || checkPermission('internat_view')) {
    $stmt = $database->query(
        "SELECT COUNT(*) as total FROM hebergements WHERE annee_scolaire_id = ? AND status = 'actif'",
        [$current_year['id'] ?? 0]
    );
    $stats['internat']['residents_actifs'] = $stmt->fetch()['total'];
    
    $stmt = $database->query("SELECT COUNT(*) as total FROM chambres WHERE status = 'disponible'");
    $stats['internat']['chambres_disponibles'] = $stmt->fetch()['total'];
}

// Transport
if (checkPermission('transport') || checkPermission('transport_view')) {
    $stmt = $database->query(
        "SELECT COUNT(*) as total FROM transport_eleves WHERE annee_scolaire_id = ? AND status = 'actif'",
        [$current_year['id'] ?? 0]
    );
    $stats['transport']['eleves_transportes'] = $stmt->fetch()['total'];
    
    $stmt = $database->query("SELECT COUNT(*) as total FROM vehicules WHERE status = 'actif'");
    $stats['transport']['vehicules_actifs'] = $stmt->fetch()['total'];
}

// Inventaire
if (checkPermission('inventory') || checkPermission('inventory_view')) {
    $stmt = $database->query("SELECT COUNT(*) as total FROM inventaire WHERE status = 'actif'");
    $stats['inventory']['articles_actifs'] = $stmt->fetch()['total'];
    
    $stmt = $database->query("SELECT SUM(valeur_totale) as total FROM inventaire WHERE status = 'actif'");
    $stats['inventory']['valeur_totale'] = $stmt->fetch()['total'] ?? 0;
}

// Santé
if (checkPermission('health') || checkPermission('health_view')) {
    $stmt = $database->query(
        "SELECT COUNT(*) as total FROM consultations WHERE date_consultation >= DATE_SUB(NOW(), INTERVAL 30 DAY)"
    );
    $stats['health']['consultations_mois'] = $stmt->fetch()['total'];
    
    $stmt = $database->query(
        "SELECT COUNT(*) as total FROM vaccinations WHERE annee_scolaire_id = ?",
        [$current_year['id'] ?? 0]
    );
    $stats['health']['vaccinations_annee'] = $stmt->fetch()['total'];
}

include '../../includes/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">
        <i class="fas fa-puzzle-piece me-2"></i>
        Modules Complémentaires
    </h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <div class="btn-group me-2">
            <button type="button" class="btn btn-outline-secondary">
                <i class="fas fa-calendar-alt me-1"></i>
                <?php echo $current_year['annee'] ?? 'Aucune année active'; ?>
            </button>
        </div>
        <div class="btn-group">
            <button type="button" class="btn btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">
                <i class="fas fa-cog me-1"></i>
                Configuration
            </button>
            <ul class="dropdown-menu">
                <li><a class="dropdown-item" href="settings/modules.php">
                    <i class="fas fa-toggle-on me-2"></i>Activer/Désactiver modules
                </a></li>
                <li><a class="dropdown-item" href="settings/permissions.php">
                    <i class="fas fa-shield-alt me-2"></i>Gérer permissions
                </a></li>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item" href="reports/overview.php">
                    <i class="fas fa-chart-bar me-2"></i>Rapport global
                </a></li>
            </ul>
        </div>
    </div>
</div>

<!-- Vue d'ensemble -->
<div class="row mb-4">
    <div class="col-12">
        <div class="alert alert-info">
            <h5 class="mb-2">
                <i class="fas fa-info-circle me-2"></i>
                Modules Complémentaires
            </h5>
            <p class="mb-0">
                Ces modules étendent les fonctionnalités de base de l'application pour couvrir tous les aspects 
                de la gestion scolaire : bibliothèque, discipline, communication, internat, transport, inventaire et santé.
            </p>
        </div>
    </div>
</div>

<!-- Modules disponibles -->
<div class="row">
    <!-- Bibliothèque -->
    <div class="col-lg-6 col-xl-4 mb-4">
        <div class="card h-100 <?php echo (checkPermission('library') || checkPermission('library_view')) ? '' : 'opacity-50'; ?>">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0">
                    <i class="fas fa-book me-2"></i>
                    Bibliothèque
                </h5>
            </div>
            <div class="card-body">
                <p class="card-text">
                    Gestion des livres, emprunts, retours et catalogue de la bibliothèque scolaire.
                </p>
                <?php if (checkPermission('library') || checkPermission('library_view')): ?>
                    <div class="row text-center mb-3">
                        <div class="col-6">
                            <h4 class="text-primary"><?php echo $stats['library']['livres_disponibles'] ?? 0; ?></h4>
                            <small class="text-muted">Livres disponibles</small>
                        </div>
                        <div class="col-6">
                            <h4 class="text-success"><?php echo $stats['library']['emprunts_actifs'] ?? 0; ?></h4>
                            <small class="text-muted">Emprunts actifs</small>
                        </div>
                    </div>
                    <div class="d-grid">
                        <a href="library/" class="btn btn-primary">
                            <i class="fas fa-arrow-right me-1"></i>
                            Accéder au module
                        </a>
                    </div>
                <?php else: ?>
                    <div class="text-center">
                        <i class="fas fa-lock fa-2x text-muted mb-2"></i>
                        <p class="text-muted">Accès non autorisé</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Discipline -->
    <div class="col-lg-6 col-xl-4 mb-4">
        <div class="card h-100 <?php echo (checkPermission('discipline') || checkPermission('discipline_view')) ? '' : 'opacity-50'; ?>">
            <div class="card-header bg-warning text-dark">
                <h5 class="mb-0">
                    <i class="fas fa-gavel me-2"></i>
                    Discipline
                </h5>
            </div>
            <div class="card-body">
                <p class="card-text">
                    Gestion des incidents, sanctions disciplinaires et suivi comportemental des élèves.
                </p>
                <?php if (checkPermission('discipline') || checkPermission('discipline_view')): ?>
                    <div class="row text-center mb-3">
                        <div class="col-6">
                            <h4 class="text-warning"><?php echo $stats['discipline']['incidents_ouverts'] ?? 0; ?></h4>
                            <small class="text-muted">Incidents ouverts</small>
                        </div>
                        <div class="col-6">
                            <h4 class="text-danger"><?php echo $stats['discipline']['sanctions_actives'] ?? 0; ?></h4>
                            <small class="text-muted">Sanctions actives</small>
                        </div>
                    </div>
                    <div class="d-grid">
                        <a href="discipline/" class="btn btn-warning">
                            <i class="fas fa-arrow-right me-1"></i>
                            Accéder au module
                        </a>
                    </div>
                <?php else: ?>
                    <div class="text-center">
                        <i class="fas fa-lock fa-2x text-muted mb-2"></i>
                        <p class="text-muted">Accès non autorisé</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Communication -->
    <div class="col-lg-6 col-xl-4 mb-4">
        <div class="card h-100 <?php echo (checkPermission('communication') || checkPermission('communication_view')) ? '' : 'opacity-50'; ?>">
            <div class="card-header bg-info text-white">
                <h5 class="mb-0">
                    <i class="fas fa-comments me-2"></i>
                    Communication
                </h5>
            </div>
            <div class="card-body">
                <p class="card-text">
                    Messagerie interne, notifications, annonces et communication avec les parents.
                </p>
                <?php if (checkPermission('communication') || checkPermission('communication_view')): ?>
                    <div class="row text-center mb-3">
                        <div class="col-6">
                            <h4 class="text-info"><?php echo $stats['communication']['messages_semaine'] ?? 0; ?></h4>
                            <small class="text-muted">Messages (7j)</small>
                        </div>
                        <div class="col-6">
                            <h4 class="text-warning"><?php echo $stats['communication']['notifications_non_lues'] ?? 0; ?></h4>
                            <small class="text-muted">Non lues</small>
                        </div>
                    </div>
                    <div class="d-grid">
                        <a href="communication/" class="btn btn-info">
                            <i class="fas fa-arrow-right me-1"></i>
                            Accéder au module
                        </a>
                    </div>
                <?php else: ?>
                    <div class="text-center">
                        <i class="fas fa-lock fa-2x text-muted mb-2"></i>
                        <p class="text-muted">Accès non autorisé</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Internat -->
    <div class="col-lg-6 col-xl-4 mb-4">
        <div class="card h-100 <?php echo (checkPermission('internat') || checkPermission('internat_view')) ? '' : 'opacity-50'; ?>">
            <div class="card-header bg-success text-white">
                <h5 class="mb-0">
                    <i class="fas fa-bed me-2"></i>
                    Internat
                </h5>
            </div>
            <div class="card-body">
                <p class="card-text">
                    Gestion des chambres, hébergement des élèves internes et suivi de l'internat.
                </p>
                <?php if (checkPermission('internat') || checkPermission('internat_view')): ?>
                    <div class="row text-center mb-3">
                        <div class="col-6">
                            <h4 class="text-success"><?php echo $stats['internat']['residents_actifs'] ?? 0; ?></h4>
                            <small class="text-muted">Résidents</small>
                        </div>
                        <div class="col-6">
                            <h4 class="text-primary"><?php echo $stats['internat']['chambres_disponibles'] ?? 0; ?></h4>
                            <small class="text-muted">Chambres libres</small>
                        </div>
                    </div>
                    <div class="d-grid">
                        <a href="internat/" class="btn btn-success">
                            <i class="fas fa-arrow-right me-1"></i>
                            Accéder au module
                        </a>
                    </div>
                <?php else: ?>
                    <div class="text-center">
                        <i class="fas fa-lock fa-2x text-muted mb-2"></i>
                        <p class="text-muted">Accès non autorisé</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Transport -->
    <div class="col-lg-6 col-xl-4 mb-4">
        <div class="card h-100 <?php echo (checkPermission('transport') || checkPermission('transport_view')) ? '' : 'opacity-50'; ?>">
            <div class="card-header bg-secondary text-white">
                <h5 class="mb-0">
                    <i class="fas fa-bus me-2"></i>
                    Transport
                </h5>
            </div>
            <div class="card-body">
                <p class="card-text">
                    Gestion du transport scolaire, itinéraires, véhicules et suivi des élèves transportés.
                </p>
                <?php if (checkPermission('transport') || checkPermission('transport_view')): ?>
                    <div class="row text-center mb-3">
                        <div class="col-6">
                            <h4 class="text-secondary"><?php echo $stats['transport']['eleves_transportes'] ?? 0; ?></h4>
                            <small class="text-muted">Élèves</small>
                        </div>
                        <div class="col-6">
                            <h4 class="text-success"><?php echo $stats['transport']['vehicules_actifs'] ?? 0; ?></h4>
                            <small class="text-muted">Véhicules</small>
                        </div>
                    </div>
                    <div class="d-grid">
                        <a href="transport/" class="btn btn-secondary">
                            <i class="fas fa-arrow-right me-1"></i>
                            Accéder au module
                        </a>
                    </div>
                <?php else: ?>
                    <div class="text-center">
                        <i class="fas fa-lock fa-2x text-muted mb-2"></i>
                        <p class="text-muted">Accès non autorisé</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Inventaire -->
    <div class="col-lg-6 col-xl-4 mb-4">
        <div class="card h-100 <?php echo (checkPermission('inventory') || checkPermission('inventory_view')) ? '' : 'opacity-50'; ?>">
            <div class="card-header bg-dark text-white">
                <h5 class="mb-0">
                    <i class="fas fa-boxes me-2"></i>
                    Inventaire
                </h5>
            </div>
            <div class="card-body">
                <p class="card-text">
                    Gestion du matériel, équipements, fournitures et suivi de l'inventaire scolaire.
                </p>
                <?php if (checkPermission('inventory') || checkPermission('inventory_view')): ?>
                    <div class="row text-center mb-3">
                        <div class="col-6">
                            <h4 class="text-dark"><?php echo $stats['inventory']['articles_actifs'] ?? 0; ?></h4>
                            <small class="text-muted">Articles</small>
                        </div>
                        <div class="col-6">
                            <h4 class="text-success"><?php echo formatMoney($stats['inventory']['valeur_totale'] ?? 0); ?></h4>
                            <small class="text-muted">Valeur totale</small>
                        </div>
                    </div>
                    <div class="d-grid">
                        <a href="inventory/" class="btn btn-dark">
                            <i class="fas fa-arrow-right me-1"></i>
                            Accéder au module
                        </a>
                    </div>
                <?php else: ?>
                    <div class="text-center">
                        <i class="fas fa-lock fa-2x text-muted mb-2"></i>
                        <p class="text-muted">Accès non autorisé</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Santé -->
    <div class="col-lg-6 col-xl-4 mb-4">
        <div class="card h-100 <?php echo (checkPermission('health') || checkPermission('health_view')) ? '' : 'opacity-50'; ?>">
            <div class="card-header bg-danger text-white">
                <h5 class="mb-0">
                    <i class="fas fa-heartbeat me-2"></i>
                    Santé
                </h5>
            </div>
            <div class="card-body">
                <p class="card-text">
                    Suivi médical, vaccinations, consultations et gestion de l'infirmerie scolaire.
                </p>
                <?php if (checkPermission('health') || checkPermission('health_view')): ?>
                    <div class="row text-center mb-3">
                        <div class="col-6">
                            <h4 class="text-danger"><?php echo $stats['health']['consultations_mois'] ?? 0; ?></h4>
                            <small class="text-muted">Consultations (30j)</small>
                        </div>
                        <div class="col-6">
                            <h4 class="text-success"><?php echo $stats['health']['vaccinations_annee'] ?? 0; ?></h4>
                            <small class="text-muted">Vaccinations</small>
                        </div>
                    </div>
                    <div class="d-grid">
                        <a href="health/" class="btn btn-danger">
                            <i class="fas fa-arrow-right me-1"></i>
                            Accéder au module
                        </a>
                    </div>
                <?php else: ?>
                    <div class="text-center">
                        <i class="fas fa-lock fa-2x text-muted mb-2"></i>
                        <p class="text-muted">Accès non autorisé</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Actions rapides -->
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
                            <a href="reports/overview.php" class="btn btn-outline-primary">
                                <i class="fas fa-chart-bar me-2"></i>
                                Rapport global
                            </a>
                        </div>
                    </div>
                    <div class="col-md-3 mb-2">
                        <div class="d-grid">
                            <a href="settings/modules.php" class="btn btn-outline-secondary">
                                <i class="fas fa-cog me-2"></i>
                                Configuration
                            </a>
                        </div>
                    </div>
                    <div class="col-md-3 mb-2">
                        <div class="d-grid">
                            <a href="backup/export.php" class="btn btn-outline-info">
                                <i class="fas fa-download me-2"></i>
                                Export données
                            </a>
                        </div>
                    </div>
                    <div class="col-md-3 mb-2">
                        <div class="d-grid">
                            <a href="help/documentation.php" class="btn btn-outline-success">
                                <i class="fas fa-question-circle me-2"></i>
                                Documentation
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.hover-card {
    transition: transform 0.2s ease-in-out;
}

.hover-card:hover {
    transform: translateY(-5px);
}

.opacity-50 {
    opacity: 0.5;
}
</style>

<?php include '../../includes/footer.php'; ?>
