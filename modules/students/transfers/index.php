<?php
/**
 * Module Gestion des Transferts et Sorties - Page principale
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

$page_title = 'Gestion des Transferts et Sorties';

// Obtenir l'année scolaire actuelle
$current_year = getCurrentAcademicYear();

// Paramètres de filtrage
$status_filter = sanitizeInput($_GET['status'] ?? '');
$type_filter = sanitizeInput($_GET['type'] ?? '');

// Statistiques des transferts
$stats = [];

// Demandes en attente
try {
    $stmt = $database->query(
        "SELECT COUNT(*) as total FROM transferts_sorties
         WHERE status = 'en_attente' AND annee_scolaire_id = ?",
        [$current_year['id'] ?? 0]
    );
    $stats['demandes_attente'] = $stmt->fetch()['total'];
} catch (Exception $e) {
    $stats['demandes_attente'] = 0;
}

// Transferts approuvés
try {
    $stmt = $database->query(
        "SELECT COUNT(*) as total FROM transferts_sorties
         WHERE status = 'approuve' AND type_mouvement = 'transfert' AND annee_scolaire_id = ?",
        [$current_year['id'] ?? 0]
    );
    $stats['transferts_approuves'] = $stmt->fetch()['total'];
} catch (Exception $e) {
    $stats['transferts_approuves'] = 0;
}

// Sorties définitives
try {
    $stmt = $database->query(
        "SELECT COUNT(*) as total FROM transferts_sorties
         WHERE status = 'approuve' AND type_mouvement = 'sortie_definitive' AND annee_scolaire_id = ?",
        [$current_year['id'] ?? 0]
    );
    $stats['sorties_definitives'] = $stmt->fetch()['total'];
} catch (Exception $e) {
    $stats['sorties_definitives'] = 0;
}

// Demandes rejetées
try {
    $stmt = $database->query(
        "SELECT COUNT(*) as total FROM transferts_sorties
         WHERE status = 'rejete' AND annee_scolaire_id = ?",
        [$current_year['id'] ?? 0]
    );
    $stats['demandes_rejetees'] = $stmt->fetch()['total'];
} catch (Exception $e) {
    $stats['demandes_rejetees'] = 0;
}

// Construction de la requête avec filtres
$where_conditions = ["ts.annee_scolaire_id = ?"];
$params = [$current_year['id'] ?? 0];

if ($status_filter) {
    $where_conditions[] = "ts.status = ?";
    $params[] = $status_filter;
}

if ($type_filter) {
    $where_conditions[] = "ts.type_mouvement = ?";
    $params[] = $type_filter;
}

$where_clause = implode(' AND ', $where_conditions);

// Récupérer les demandes de transfert/sortie
try {
    $demandes = $database->query(
        "SELECT ts.*, e.nom, e.prenom, e.numero_matricule,
                c.nom as classe_actuelle, c.niveau,
                u.username as traite_par
         FROM transferts_sorties ts
         JOIN eleves e ON ts.eleve_id = e.id
         JOIN inscriptions i ON e.id = i.eleve_id
         JOIN classes c ON i.classe_id = c.id
         LEFT JOIN users u ON ts.traite_par = u.id
         WHERE $where_clause
         ORDER BY ts.created_at DESC
         LIMIT 20",
        $params
    )->fetchAll();
} catch (Exception $e) {
    $demandes = [];
}

// Demandes récentes (7 derniers jours)
try {
    $demandes_recentes = $database->query(
        "SELECT ts.*, e.nom, e.prenom, e.numero_matricule,
                c.nom as classe_actuelle, c.niveau
         FROM transferts_sorties ts
         JOIN eleves e ON ts.eleve_id = e.id
         JOIN inscriptions i ON e.id = i.eleve_id
         JOIN classes c ON i.classe_id = c.id
         WHERE ts.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
         AND ts.annee_scolaire_id = ?
         ORDER BY ts.created_at DESC
         LIMIT 8",
        [$current_year['id'] ?? 0]
    )->fetchAll();
} catch (Exception $e) {
    $demandes_recentes = [];
}

// Statistiques par type de mouvement
try {
    $stats_par_type = $database->query(
        "SELECT type_mouvement, status, COUNT(*) as nombre
         FROM transferts_sorties
         WHERE annee_scolaire_id = ?
         GROUP BY type_mouvement, status
         ORDER BY type_mouvement, status",
        [$current_year['id'] ?? 0]
    )->fetchAll();
} catch (Exception $e) {
    $stats_par_type = [];
}

// Motifs les plus fréquents
try {
    $motifs_frequents = $database->query(
        "SELECT motif, COUNT(*) as nombre
         FROM transferts_sorties
         WHERE annee_scolaire_id = ? AND motif IS NOT NULL AND motif != ''
         GROUP BY motif
         ORDER BY nombre DESC
         LIMIT 8",
        [$current_year['id'] ?? 0]
    )->fetchAll();
} catch (Exception $e) {
    $motifs_frequents = [];
}

// Évolution mensuelle
try {
    $evolution_mensuelle = $database->query(
        "SELECT DATE_FORMAT(created_at, '%Y-%m') as mois,
                COUNT(CASE WHEN type_mouvement = 'transfert' THEN 1 END) as transferts,
                COUNT(CASE WHEN type_mouvement = 'sortie_definitive' THEN 1 END) as sorties
         FROM transferts_sorties
         WHERE YEAR(created_at) = YEAR(CURDATE()) AND annee_scolaire_id = ?
         GROUP BY DATE_FORMAT(created_at, '%Y-%m')
         ORDER BY mois",
        [$current_year['id'] ?? 0]
    )->fetchAll();
} catch (Exception $e) {
    $evolution_mensuelle = [];
}

// Demandes nécessitant attention (en attente depuis plus de 7 jours)
try {
    $demandes_attention = $database->query(
        "SELECT ts.*, e.nom, e.prenom, e.numero_matricule,
                c.nom as classe_actuelle,
                DATEDIFF(NOW(), ts.created_at) as jours_attente
         FROM transferts_sorties ts
         JOIN eleves e ON ts.eleve_id = e.id
         JOIN inscriptions i ON e.id = i.eleve_id
         JOIN classes c ON i.classe_id = c.id
         WHERE ts.status = 'en_attente'
         AND ts.annee_scolaire_id = ?
         AND DATEDIFF(NOW(), ts.created_at) > 7
         ORDER BY ts.created_at ASC
         LIMIT 8",
        [$current_year['id'] ?? 0]
    )->fetchAll();
} catch (Exception $e) {
    $demandes_attention = [];
}

// Vérifier si la table transferts_sorties existe
$table_exists = false;
try {
    $database->query("SELECT 1 FROM transferts_sorties LIMIT 1");
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
        <p>La table <code>transferts_sorties</code> n'existe pas dans la base de données.</p>
        <p>Cette table est nécessaire pour la gestion des transferts et sorties d'élèves.</p>
        <hr>
        <p class="mb-0">
            <a href="../../../fix-students-tables.php" class="btn btn-warning me-2">
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
        <i class="fas fa-exchange-alt me-2"></i>
        Gestion des Transferts et Sorties
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
                    <li><a class="dropdown-item" href="new-transfer.php">
                        <i class="fas fa-exchange-alt me-2"></i>Demande de transfert
                    </a></li>
                    <li><a class="dropdown-item" href="new-exit.php">
                        <i class="fas fa-sign-out-alt me-2"></i>Sortie définitive
                    </a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item" href="bulk-process.php">
                        <i class="fas fa-tasks me-2"></i>Traitement en masse
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
                <li><a class="dropdown-item" href="reports/transfers.php">
                    <i class="fas fa-chart-bar me-2"></i>Rapport des transferts
                </a></li>
                <li><a class="dropdown-item" href="exports/movements.php">
                    <i class="fas fa-file-export me-2"></i>Exporter mouvements
                </a></li>
                <li><a class="dropdown-item" href="certificates/generate.php">
                    <i class="fas fa-certificate me-2"></i>Certificats de scolarité
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
        <div class="card text-white bg-info">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h4><?php echo $stats['transferts_approuves']; ?></h4>
                        <p class="mb-0">Transferts</p>
                    </div>
                    <div class="align-self-center">
                        <i class="fas fa-exchange-alt fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-3 col-md-6 mb-3">
        <div class="card text-white bg-secondary">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h4><?php echo $stats['sorties_definitives']; ?></h4>
                        <p class="mb-0">Sorties définitives</p>
                    </div>
                    <div class="align-self-center">
                        <i class="fas fa-sign-out-alt fa-2x"></i>
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

<!-- Modules de transfert -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-th-large me-2"></i>
                    Types de mouvements
                </h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-lg-3 col-md-6 mb-3">
                        <a href="?type=transfert" class="text-decoration-none">
                            <div class="card h-100 border-0 shadow-sm hover-card">
                                <div class="card-body text-center">
                                    <i class="fas fa-exchange-alt fa-3x text-info mb-3"></i>
                                    <h5 class="card-title">Transferts</h5>
                                    <p class="card-text text-muted">
                                        Changement d'établissement
                                    </p>
                                    <div class="mt-3">
                                        <span class="badge bg-info"><?php echo $stats['transferts_approuves']; ?> approuvés</span>
                                    </div>
                                </div>
                            </div>
                        </a>
                    </div>
                    
                    <div class="col-lg-3 col-md-6 mb-3">
                        <a href="?type=sortie_definitive" class="text-decoration-none">
                            <div class="card h-100 border-0 shadow-sm hover-card">
                                <div class="card-body text-center">
                                    <i class="fas fa-sign-out-alt fa-3x text-secondary mb-3"></i>
                                    <h5 class="card-title">Sorties définitives</h5>
                                    <p class="card-text text-muted">
                                        Fin de scolarité
                                    </p>
                                    <div class="mt-3">
                                        <span class="badge bg-secondary"><?php echo $stats['sorties_definitives']; ?> sorties</span>
                                    </div>
                                </div>
                            </div>
                        </a>
                    </div>
                    
                    <div class="col-lg-3 col-md-6 mb-3">
                        <a href="?status=en_attente" class="text-decoration-none">
                            <div class="card h-100 border-0 shadow-sm hover-card">
                                <div class="card-body text-center">
                                    <i class="fas fa-clock fa-3x text-warning mb-3"></i>
                                    <h5 class="card-title">En attente</h5>
                                    <p class="card-text text-muted">
                                        Demandes à traiter
                                    </p>
                                    <div class="mt-3">
                                        <span class="badge bg-warning"><?php echo $stats['demandes_attente']; ?> demandes</span>
                                    </div>
                                </div>
                            </div>
                        </a>
                    </div>
                    
                    <div class="col-lg-3 col-md-6 mb-3">
                        <a href="certificates/" class="text-decoration-none">
                            <div class="card h-100 border-0 shadow-sm hover-card">
                                <div class="card-body text-center">
                                    <i class="fas fa-certificate fa-3x text-success mb-3"></i>
                                    <h5 class="card-title">Certificats</h5>
                                    <p class="card-text text-muted">
                                        Documents de scolarité
                                    </p>
                                    <div class="mt-3">
                                        <span class="badge bg-success">Génération automatique</span>
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

<!-- Filtres -->
<div class="card mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-md-4">
                <label for="status" class="form-label">Statut</label>
                <select class="form-select" id="status" name="status">
                    <option value="">Tous les statuts</option>
                    <option value="en_attente" <?php echo $status_filter === 'en_attente' ? 'selected' : ''; ?>>En attente</option>
                    <option value="approuve" <?php echo $status_filter === 'approuve' ? 'selected' : ''; ?>>Approuvé</option>
                    <option value="rejete" <?php echo $status_filter === 'rejete' ? 'selected' : ''; ?>>Rejeté</option>
                </select>
            </div>
            <div class="col-md-4">
                <label for="type" class="form-label">Type de mouvement</label>
                <select class="form-select" id="type" name="type">
                    <option value="">Tous les types</option>
                    <option value="transfert" <?php echo $type_filter === 'transfert' ? 'selected' : ''; ?>>Transfert</option>
                    <option value="sortie_definitive" <?php echo $type_filter === 'sortie_definitive' ? 'selected' : ''; ?>>Sortie définitive</option>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label">&nbsp;</label>
                <div class="d-grid">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-filter me-1"></i>
                        Filtrer
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Contenu principal -->
<div class="row">
    <div class="col-lg-8">
        <!-- Liste des demandes -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-list me-2"></i>
                    Demandes de transfert et sortie
                    <?php if (!empty($demandes)): ?>
                        <span class="badge bg-secondary"><?php echo count($demandes); ?></span>
                    <?php endif; ?>
                </h5>
            </div>
            <div class="card-body">
                <?php if (!empty($demandes)): ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Élève</th>
                                    <th>Classe</th>
                                    <th>Type</th>
                                    <th>Motif</th>
                                    <th>Statut</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($demandes as $demande): ?>
                                    <tr>
                                        <td><?php echo formatDate($demande['created_at']); ?></td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($demande['nom'] . ' ' . $demande['prenom']); ?></strong>
                                            <br><small class="text-muted">
                                                <?php echo htmlspecialchars($demande['numero_matricule']); ?>
                                            </small>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?php 
                                                echo $demande['niveau'] === 'maternelle' ? 'warning' : 
                                                    ($demande['niveau'] === 'primaire' ? 'success' : 'primary'); 
                                            ?>">
                                                <?php echo htmlspecialchars($demande['classe_actuelle']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?php echo $demande['type_mouvement'] === 'transfert' ? 'info' : 'secondary'; ?>">
                                                <?php echo $demande['type_mouvement'] === 'transfert' ? 'Transfert' : 'Sortie définitive'; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($demande['motif']): ?>
                                                <small><?php echo htmlspecialchars(substr($demande['motif'], 0, 50)); ?>
                                                <?php echo strlen($demande['motif']) > 50 ? '...' : ''; ?></small>
                                            <?php else: ?>
                                                <span class="text-muted">Non spécifié</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php
                                            $status_colors = [
                                                'en_attente' => 'warning',
                                                'approuve' => 'success',
                                                'rejete' => 'danger'
                                            ];
                                            $color = $status_colors[$demande['status']] ?? 'secondary';
                                            ?>
                                            <span class="badge bg-<?php echo $color; ?>">
                                                <?php echo ucfirst(str_replace('_', ' ', $demande['status'])); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <a href="view.php?id=<?php echo $demande['id']; ?>" 
                                                   class="btn btn-outline-info" title="Voir">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                <?php if (checkPermission('students') && $demande['status'] === 'en_attente'): ?>
                                                    <a href="process.php?id=<?php echo $demande['id']; ?>" 
                                                       class="btn btn-outline-primary" title="Traiter">
                                                        <i class="fas fa-cog"></i>
                                                    </a>
                                                <?php endif; ?>
                                                <?php if ($demande['status'] === 'approuve'): ?>
                                                    <a href="certificate.php?id=<?php echo $demande['id']; ?>" 
                                                       class="btn btn-outline-success" title="Certificat">
                                                        <i class="fas fa-certificate"></i>
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
                        <i class="fas fa-exchange-alt fa-4x text-muted mb-3"></i>
                        <h4 class="text-muted">Aucune demande trouvée</h4>
                        <p class="text-muted">
                            <?php if ($status_filter || $type_filter): ?>
                                Aucune demande ne correspond aux critères sélectionnés.
                            <?php else: ?>
                                Aucune demande de transfert ou sortie n'a été enregistrée.
                            <?php endif; ?>
                        </p>
                        <?php if (checkPermission('students')): ?>
                            <a href="new-transfer.php" class="btn btn-primary me-2">
                                <i class="fas fa-plus me-1"></i>
                                Nouvelle demande
                            </a>
                        <?php endif; ?>
                        <?php if ($status_filter || $type_filter): ?>
                            <a href="?" class="btn btn-outline-secondary">
                                <i class="fas fa-times me-1"></i>
                                Effacer filtres
                            </a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <div class="col-lg-4">
        <!-- Demandes nécessitant attention -->
        <?php if (!empty($demandes_attention)): ?>
        <div class="card mb-4">
            <div class="card-header bg-warning text-dark">
                <h6 class="mb-0">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    Demandes en attente (>7 jours)
                </h6>
            </div>
            <div class="card-body">
                <?php foreach ($demandes_attention as $demande): ?>
                    <div class="d-flex justify-content-between align-items-start mb-3">
                        <div>
                            <strong><?php echo htmlspecialchars($demande['nom'] . ' ' . substr($demande['prenom'], 0, 1) . '.'); ?></strong>
                            <br><small class="text-muted">
                                <?php echo htmlspecialchars($demande['classe_actuelle']); ?>
                            </small>
                            <br><small class="text-warning">
                                En attente depuis <?php echo $demande['jours_attente']; ?> jour(s)
                            </small>
                        </div>
                        <a href="process.php?id=<?php echo $demande['id']; ?>" 
                           class="btn btn-sm btn-outline-warning">
                            <i class="fas fa-cog"></i>
                        </a>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Statistiques par type -->
        <div class="card mb-4">
            <div class="card-header">
                <h6 class="mb-0">
                    <i class="fas fa-chart-pie me-2"></i>
                    Répartition par type et statut
                </h6>
            </div>
            <div class="card-body">
                <?php if (!empty($stats_par_type)): ?>
                    <?php 
                    $grouped_stats = [];
                    foreach ($stats_par_type as $stat) {
                        $grouped_stats[$stat['type_mouvement']][$stat['status']] = $stat['nombre'];
                    }
                    ?>
                    <?php foreach ($grouped_stats as $type => $statuts): ?>
                        <div class="mb-3">
                            <strong><?php echo $type === 'transfert' ? 'Transferts' : 'Sorties définitives'; ?></strong>
                            <div class="mt-1">
                                <?php foreach ($statuts as $statut => $nombre): ?>
                                    <span class="badge bg-<?php 
                                        echo $statut === 'approuve' ? 'success' : 
                                            ($statut === 'en_attente' ? 'warning' : 'danger'); 
                                    ?> me-1">
                                        <?php echo ucfirst(str_replace('_', ' ', $statut)); ?>: <?php echo $nombre; ?>
                                    </span>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p class="text-muted text-center">Aucune donnée disponible</p>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Motifs fréquents -->
        <?php if (!empty($motifs_frequents)): ?>
        <div class="card">
            <div class="card-header">
                <h6 class="mb-0">
                    <i class="fas fa-list me-2"></i>
                    Motifs les plus fréquents
                </h6>
            </div>
            <div class="card-body">
                <?php foreach ($motifs_frequents as $motif): ?>
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span><?php echo htmlspecialchars(substr($motif['motif'], 0, 30)); ?>
                        <?php echo strlen($motif['motif']) > 30 ? '...' : ''; ?></span>
                        <span class="badge bg-secondary"><?php echo $motif['nombre']; ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
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
                            <a href="new-transfer.php" class="btn btn-outline-info">
                                <i class="fas fa-exchange-alt me-2"></i>
                                Nouveau transfert
                            </a>
                        </div>
                    </div>
                    <div class="col-md-3 mb-2">
                        <div class="d-grid">
                            <a href="new-exit.php" class="btn btn-outline-secondary">
                                <i class="fas fa-sign-out-alt me-2"></i>
                                Sortie définitive
                            </a>
                        </div>
                    </div>
                    <div class="col-md-3 mb-2">
                        <div class="d-grid">
                            <a href="?status=en_attente" class="btn btn-outline-warning">
                                <i class="fas fa-clock me-2"></i>
                                Traiter demandes
                            </a>
                        </div>
                    </div>
                    <div class="col-md-3 mb-2">
                        <div class="d-grid">
                            <a href="certificates/generate.php" class="btn btn-outline-success">
                                <i class="fas fa-certificate me-2"></i>
                                Générer certificats
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
