<?php
/**
 * Module Inscriptions et Admissions - Page principale
 * Application de gestion scolaire - République Démocratique du Congo
 */

require_once '../../../config/config.php';
require_once '../../../config/database.php';
require_once '../../../includes/functions.php';

// Vérifier l'authentification et les permissions
requireLogin();
if (!checkPermission('students') && !checkPermission('students_view')) {
    showMessage('error', 'Accès refusé à ce module.');
    redirectTo('../index.php');
}

$page_title = 'Inscriptions et Admissions';

// Obtenir l'année scolaire actuelle
$current_year = getCurrentAcademicYear();

// Statistiques des admissions
$stats = [];

// Demandes d'admission en attente
try {
    $stmt = $database->query(
        "SELECT COUNT(*) as total FROM demandes_admission WHERE status = 'en_attente' AND annee_scolaire_id = ?",
        [$current_year['id'] ?? 0]
    );
    $stats['demandes_attente'] = $stmt->fetch()['total'];
} catch (Exception $e) {
    $stats['demandes_attente'] = 0;
}

// Admissions approuvées
try {
    $stmt = $database->query(
        "SELECT COUNT(*) as total FROM demandes_admission WHERE status = 'acceptee' AND annee_scolaire_id = ?",
        [$current_year['id'] ?? 0]
    );
    $stats['admissions_approuvees'] = $stmt->fetch()['total'];
} catch (Exception $e) {
    $stats['admissions_approuvees'] = 0;
}

// Inscriptions finalisées
$stmt = $database->query(
    "SELECT COUNT(*) as total FROM inscriptions WHERE status = 'inscrit' AND annee_scolaire_id = ?",
    [$current_year['id'] ?? 0]
);
$stats['inscriptions_finalisees'] = $stmt->fetch()['total'];

// Demandes rejetées
try {
    $stmt = $database->query(
        "SELECT COUNT(*) as total FROM demandes_admission WHERE status = 'refusee' AND annee_scolaire_id = ?",
        [$current_year['id'] ?? 0]
    );
    $stats['demandes_rejetees'] = $stmt->fetch()['total'];
} catch (Exception $e) {
    $stats['demandes_rejetees'] = 0;
}

// Demandes récentes (7 derniers jours)
try {
    $demandes_recentes = $database->query(
        "SELECT da.*, c.nom as classe_demandee, c.niveau
         FROM demandes_admission da
         LEFT JOIN classes c ON da.classe_demandee_id = c.id
         WHERE da.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
         AND da.annee_scolaire_id = ?
         ORDER BY da.created_at DESC
         LIMIT 10",
        [$current_year['id'] ?? 0]
    )->fetchAll();
} catch (Exception $e) {
    $demandes_recentes = [];
}

// Demandes par statut
try {
    $demandes_par_statut = $database->query(
        "SELECT status, COUNT(*) as nombre
         FROM demandes_admission
         WHERE annee_scolaire_id = ?
         GROUP BY status
         ORDER BY nombre DESC",
        [$current_year['id'] ?? 0]
    )->fetchAll();
} catch (Exception $e) {
    $demandes_par_statut = [];
}

// Inscriptions par niveau
$inscriptions_par_niveau = $database->query(
    "SELECT c.niveau, COUNT(i.id) as nombre
     FROM inscriptions i
     JOIN classes c ON i.classe_id = c.id
     WHERE i.status = 'inscrit' AND i.annee_scolaire_id = ?
     GROUP BY c.niveau
     ORDER BY 
        CASE c.niveau 
            WHEN 'maternelle' THEN 1 
            WHEN 'primaire' THEN 2 
            WHEN 'secondaire' THEN 3 
        END",
    [$current_year['id'] ?? 0]
)->fetchAll();

// Évolution mensuelle des inscriptions
$evolution_inscriptions = $database->query(
    "SELECT DATE_FORMAT(created_at, '%Y-%m') as mois, COUNT(*) as nombre
     FROM inscriptions 
     WHERE YEAR(created_at) = YEAR(CURDATE()) AND annee_scolaire_id = ?
     GROUP BY DATE_FORMAT(created_at, '%Y-%m')
     ORDER BY mois",
    [$current_year['id'] ?? 0]
)->fetchAll();

// Classes avec places disponibles
$classes_disponibles = $database->query(
    "SELECT c.nom, c.niveau, c.capacite_max,
            COUNT(i.id) as effectif_actuel,
            (c.capacite_max - COUNT(i.id)) as places_disponibles
     FROM classes c
     LEFT JOIN inscriptions i ON c.id = i.classe_id AND i.status = 'inscrit'
     WHERE c.annee_scolaire_id = ? AND c.capacite_max > 0
     GROUP BY c.id
     HAVING places_disponibles > 0
     ORDER BY places_disponibles DESC
     LIMIT 8",
    [$current_year['id'] ?? 0]
)->fetchAll();

// Demandes nécessitant attention
try {
    $demandes_attention = $database->query(
        "SELECT da.*, c.nom as classe_demandee, c.niveau,
                DATEDIFF(NOW(), da.created_at) as jours_attente
         FROM demandes_admission da
         LEFT JOIN classes c ON da.classe_demandee_id = c.id
         WHERE da.status = 'en_attente'
         AND da.annee_scolaire_id = ?
         AND (
             DATEDIFF(NOW(), da.created_at) > 7 OR
             da.priorite = 'urgente'
         )
         ORDER BY da.priorite DESC, da.created_at ASC
         LIMIT 8",
        [$current_year['id'] ?? 0]
    )->fetchAll();
} catch (Exception $e) {
    $demandes_attention = [];
}

// Vérifier si la table demandes_admission existe
$table_exists = false;
try {
    $database->query("SELECT 1 FROM demandes_admission LIMIT 1");
    $table_exists = true;
} catch (Exception $e) {
    $table_exists = false;
}

include '../../../includes/header.php';
?>

<?php if (!$table_exists): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <h4 class="alert-heading">
            <i class="fas fa-exclamation-triangle me-2"></i>
            Table manquante détectée
        </h4>
        <p>La table <code>demandes_admission</code> n'existe pas dans la base de données.</p>
        <p>Cette table est nécessaire pour la gestion des demandes d'admission.</p>
        <hr>
        <p class="mb-0">
            <a href="../../../fix-admissions-table.php" class="btn btn-warning me-2">
                <i class="fas fa-tools me-1"></i>
                Créer la table automatiquement
            </a>
            <a href="../../../debug-tables.php" class="btn btn-info">
                <i class="fas fa-search me-1"></i>
                Diagnostic complet
            </a>
        </p>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">
        <i class="fas fa-user-plus me-2"></i>
        Inscriptions et Admissions
    </h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <div class="btn-group me-2">
            <a href="../index.php" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-1"></i>
                Retour
            </a>
        </div>
        <?php if (checkPermission('students')): ?>
            <div class="btn-group me-2">
                <button type="button" class="btn btn-primary dropdown-toggle" data-bs-toggle="dropdown">
                    <i class="fas fa-plus me-1"></i>
                    Nouveau
                </button>
                <ul class="dropdown-menu">
                    <li><a class="dropdown-item" href="new-application.php">
                        <i class="fas fa-file-alt me-2"></i>Nouvelle demande
                    </a></li>
                    <li><a class="dropdown-item" href="direct-enrollment.php">
                        <i class="fas fa-user-check me-2"></i>Inscription directe
                    </a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item" href="bulk-import.php">
                        <i class="fas fa-file-import me-2"></i>Import en masse
                    </a></li>
                </ul>
            </div>
        <?php endif; ?>
        <div class="btn-group">
            <button type="button" class="btn btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">
                <i class="fas fa-tools me-1"></i>
                Outils
            </button>
            <ul class="dropdown-menu">
                <li><a class="dropdown-item" href="reports/admission-stats.php">
                    <i class="fas fa-chart-bar me-2"></i>Statistiques
                </a></li>
                <li><a class="dropdown-item" href="exports/applications.php">
                    <i class="fas fa-file-export me-2"></i>Exporter demandes
                </a></li>
                <li><a class="dropdown-item" href="settings/criteria.php">
                    <i class="fas fa-cog me-2"></i>Critères d'admission
                </a></li>
            </ul>
        </div>
    </div>
</div>

<!-- Statistiques rapides -->
<div class="row mb-4">
    <div class="col-lg-3 col-md-6 mb-3">
        <div class="card text-white bg-warning">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h4><?php echo $stats['demandes_attente']; ?></h4>
                        <p class="mb-0">En attente</p>
                    </div>
                    <div class="align-self-center">
                        <i class="fas fa-clock fa-2x"></i>
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
                        <h4><?php echo $stats['admissions_approuvees']; ?></h4>
                        <p class="mb-0">Approuvées</p>
                    </div>
                    <div class="align-self-center">
                        <i class="fas fa-check-circle fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-3 col-md-6 mb-3">
        <div class="card text-white bg-primary">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h4><?php echo $stats['inscriptions_finalisees']; ?></h4>
                        <p class="mb-0">Inscriptions</p>
                    </div>
                    <div class="align-self-center">
                        <i class="fas fa-user-graduate fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-3 col-md-6 mb-3">
        <div class="card text-white bg-danger">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h4><?php echo $stats['demandes_rejetees']; ?></h4>
                        <p class="mb-0">Rejetées</p>
                    </div>
                    <div class="align-self-center">
                        <i class="fas fa-times-circle fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modules d'admission -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-th-large me-2"></i>
                    Processus d'admission
                </h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-lg-3 col-md-6 mb-3">
                        <a href="applications/" class="text-decoration-none">
                            <div class="card h-100 border-0 shadow-sm hover-card">
                                <div class="card-body text-center">
                                    <i class="fas fa-file-alt fa-3x text-warning mb-3"></i>
                                    <h5 class="card-title">Demandes</h5>
                                    <p class="card-text text-muted">
                                        Gestion des demandes d'admission
                                    </p>
                                    <div class="mt-3">
                                        <span class="badge bg-warning"><?php echo $stats['demandes_attente']; ?> en attente</span>
                                    </div>
                                </div>
                            </div>
                        </a>
                    </div>
                    
                    <div class="col-lg-3 col-md-6 mb-3">
                        <a href="evaluation/" class="text-decoration-none">
                            <div class="card h-100 border-0 shadow-sm hover-card">
                                <div class="card-body text-center">
                                    <i class="fas fa-clipboard-check fa-3x text-info mb-3"></i>
                                    <h5 class="card-title">Évaluation</h5>
                                    <p class="card-text text-muted">
                                        Tests et entretiens d'admission
                                    </p>
                                    <div class="mt-3">
                                        <span class="badge bg-info">Critères & Tests</span>
                                    </div>
                                </div>
                            </div>
                        </a>
                    </div>
                    
                    <div class="col-lg-3 col-md-6 mb-3">
                        <a href="enrollment/" class="text-decoration-none">
                            <div class="card h-100 border-0 shadow-sm hover-card">
                                <div class="card-body text-center">
                                    <i class="fas fa-user-check fa-3x text-success mb-3"></i>
                                    <h5 class="card-title">Inscription</h5>
                                    <p class="card-text text-muted">
                                        Finalisation des inscriptions
                                    </p>
                                    <div class="mt-3">
                                        <span class="badge bg-success"><?php echo $stats['inscriptions_finalisees']; ?> inscrits</span>
                                    </div>
                                </div>
                            </div>
                        </a>
                    </div>
                    
                    <div class="col-lg-3 col-md-6 mb-3">
                        <a href="documents/" class="text-decoration-none">
                            <div class="card h-100 border-0 shadow-sm hover-card">
                                <div class="card-body text-center">
                                    <i class="fas fa-folder-open fa-3x text-primary mb-3"></i>
                                    <h5 class="card-title">Documents</h5>
                                    <p class="card-text text-muted">
                                        Gestion des pièces justificatives
                                    </p>
                                    <div class="mt-3">
                                        <span class="badge bg-primary">Dossiers complets</span>
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
        <!-- Demandes récentes -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-clock me-2"></i>
                    Demandes récentes (7 derniers jours)
                </h5>
            </div>
            <div class="card-body">
                <?php if (!empty($demandes_recentes)): ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Candidat</th>
                                    <th>Classe demandée</th>
                                    <th>Statut</th>
                                    <th>Priorité</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($demandes_recentes as $demande): ?>
                                    <tr>
                                        <td><?php echo formatDate($demande['created_at']); ?></td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($demande['nom_eleve'] . ' ' . $demande['prenom_eleve']); ?></strong>
                                            <br><small class="text-muted">
                                                <?php echo htmlspecialchars($demande['telephone_parent']); ?>
                                            </small>
                                        </td>
                                        <td>
                                            <?php if ($demande['classe_demandee']): ?>
                                                <span class="badge bg-<?php 
                                                    echo $demande['niveau'] === 'maternelle' ? 'warning' : 
                                                        ($demande['niveau'] === 'primaire' ? 'success' : 'primary'); 
                                                ?>">
                                                    <?php echo htmlspecialchars($demande['classe_demandee']); ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="text-muted">Non spécifiée</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php
                                            $status_colors = [
                                                'en_attente' => 'warning',
                                                'acceptee' => 'success',
                                                'refusee' => 'danger',
                                                'en_cours_traitement' => 'info',
                                                'inscrit' => 'primary'
                                            ];
                                            $color = $status_colors[$demande['status']] ?? 'secondary';
                                            ?>
                                            <span class="badge bg-<?php echo $color; ?>">
                                                <?php echo ucfirst(str_replace('_', ' ', $demande['status'])); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php
                                            $priorite_colors = [
                                                'normale' => 'secondary',
                                                'importante' => 'warning',
                                                'urgente' => 'danger'
                                            ];
                                            $color = $priorite_colors[$demande['priorite']] ?? 'secondary';
                                            ?>
                                            <span class="badge bg-<?php echo $color; ?>">
                                                <?php echo ucfirst($demande['priorite']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <a href="applications/view.php?id=<?php echo $demande['id']; ?>" 
                                                   class="btn btn-outline-info" title="Voir">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                <?php if (checkPermission('students') && $demande['status'] === 'en_attente'): ?>
                                                    <a href="applications/process.php?id=<?php echo $demande['id']; ?>" 
                                                       class="btn btn-outline-primary" title="Traiter">
                                                        <i class="fas fa-cog"></i>
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
                        <i class="fas fa-file-alt fa-3x text-muted mb-3"></i>
                        <p class="text-muted">Aucune demande récente</p>
                        <?php if (checkPermission('students')): ?>
                            <a href="new-application.php" class="btn btn-primary">
                                <i class="fas fa-plus me-1"></i>
                                Nouvelle demande
                            </a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Demandes nécessitant attention -->
        <?php if (!empty($demandes_attention)): ?>
        <div class="card">
            <div class="card-header bg-warning text-dark">
                <h5 class="mb-0">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    Demandes nécessitant attention
                </h5>
            </div>
            <div class="card-body">
                <div class="list-group list-group-flush">
                    <?php foreach ($demandes_attention as $demande): ?>
                        <div class="list-group-item d-flex justify-content-between align-items-start">
                            <div class="ms-2 me-auto">
                                <div class="fw-bold">
                                    <?php echo htmlspecialchars($demande['nom_eleve'] . ' ' . $demande['prenom_eleve']); ?>
                                </div>
                                <small class="text-muted">
                                    Classe: <?php echo htmlspecialchars($demande['classe_demandee'] ?? 'Non spécifiée'); ?>
                                    - En attente depuis <?php echo $demande['jours_attente']; ?> jour(s)
                                </small>
                            </div>
                            <div>
                                <?php if ($demande['priorite'] === 'urgente'): ?>
                                    <span class="badge bg-danger me-2">Urgent</span>
                                <?php endif; ?>
                                <a href="applications/process.php?id=<?php echo $demande['id']; ?>" 
                                   class="btn btn-sm btn-outline-primary">
                                    <i class="fas fa-cog"></i>
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
        <!-- Statut des demandes -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-chart-pie me-2"></i>
                    Statut des demandes
                </h5>
            </div>
            <div class="card-body">
                <?php if (!empty($demandes_par_statut)): ?>
                    <?php foreach ($demandes_par_statut as $statut): ?>
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <span><?php echo ucfirst(str_replace('_', ' ', $statut['status'])); ?></span>
                            <span class="badge bg-<?php
                                echo $statut['status'] === 'acceptee' ? 'success' :
                                    ($statut['status'] === 'en_attente' ? 'warning' :
                                    ($statut['status'] === 'refusee' ? 'danger' :
                                    ($statut['status'] === 'inscrit' ? 'primary' : 'info')));
                            ?>">
                                <?php echo $statut['nombre']; ?>
                            </span>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p class="text-muted text-center">Aucune demande</p>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Inscriptions par niveau -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-layer-group me-2"></i>
                    Inscriptions par niveau
                </h5>
            </div>
            <div class="card-body">
                <?php if (!empty($inscriptions_par_niveau)): ?>
                    <?php foreach ($inscriptions_par_niveau as $niveau): ?>
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <span class="badge bg-<?php 
                                echo $niveau['niveau'] === 'maternelle' ? 'warning' : 
                                    ($niveau['niveau'] === 'primaire' ? 'success' : 'primary'); 
                            ?>">
                                <?php echo ucfirst($niveau['niveau']); ?>
                            </span>
                            <strong><?php echo $niveau['nombre']; ?> élèves</strong>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p class="text-muted text-center">Aucune inscription</p>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Classes disponibles -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-door-open me-2"></i>
                    Places disponibles
                </h5>
            </div>
            <div class="card-body">
                <?php if (!empty($classes_disponibles)): ?>
                    <?php foreach ($classes_disponibles as $classe): ?>
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <div>
                                <strong><?php echo htmlspecialchars($classe['nom']); ?></strong>
                                <br><small class="text-muted">
                                    <?php echo ucfirst($classe['niveau']); ?> - 
                                    <?php echo $classe['effectif_actuel']; ?>/<?php echo $classe['capacite_max']; ?>
                                </small>
                            </div>
                            <span class="badge bg-<?php 
                                echo $classe['places_disponibles'] > 10 ? 'success' : 
                                    ($classe['places_disponibles'] > 5 ? 'warning' : 'danger'); 
                            ?> fs-6">
                                <?php echo $classe['places_disponibles']; ?> places
                            </span>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="text-center py-3">
                        <i class="fas fa-exclamation-triangle fa-2x text-warning mb-2"></i>
                        <p class="text-muted mb-0">Toutes les classes sont complètes</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Actions rapides -->
<?php if (checkPermission('students')): ?>
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
                            <a href="new-application.php" class="btn btn-outline-primary">
                                <i class="fas fa-file-alt me-2"></i>
                                Nouvelle demande
                            </a>
                        </div>
                    </div>
                    <div class="col-md-3 mb-2">
                        <div class="d-grid">
                            <a href="applications/?status=en_attente" class="btn btn-outline-warning">
                                <i class="fas fa-clock me-2"></i>
                                Traiter demandes
                            </a>
                        </div>
                    </div>
                    <div class="col-md-3 mb-2">
                        <div class="d-grid">
                            <a href="direct-enrollment.php" class="btn btn-outline-success">
                                <i class="fas fa-user-check me-2"></i>
                                Inscription directe
                            </a>
                        </div>
                    </div>
                    <div class="col-md-3 mb-2">
                        <div class="d-grid">
                            <a href="reports/admission-stats.php" class="btn btn-outline-info">
                                <i class="fas fa-chart-bar me-2"></i>
                                Statistiques
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
