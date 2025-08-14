<?php
/**
 * Module Gestion des Certificats de Transfert - Page principale
 * Application de gestion scolaire - République Démocratique du Congo
 */

require_once '../../../../config/config.php';
require_once '../../../../config/database.php';
require_once '../../../../includes/functions.php';

// Vérifier l'authentification et les permissions
requireLogin();
if (!checkPermission('students') && !checkPermission('students_view')) {
    showMessage('error', 'Accès refusé à ce module.');
    redirectTo('../index.php');
}

$page_title = 'Gestion des Certificats de Transfert';

// Obtenir l'année scolaire actuelle
$current_year = getCurrentAcademicYear();

// Paramètres de filtrage et pagination
$search = sanitizeInput($_GET['search'] ?? '');
$status_filter = sanitizeInput($_GET['status'] ?? '');
$type_filter = sanitizeInput($_GET['type'] ?? '');
$page = max(1, intval($_GET['page'] ?? 1));
$per_page = 20;
$offset = ($page - 1) * $per_page;

// Statistiques des certificats
$stats = [];

// Certificats générés
try {
    $stmt = $database->query(
        "SELECT COUNT(*) as total FROM transfers 
         WHERE certificat_genere = 1 AND statut = 'complete'",
        []
    );
    $stats['certificats_generes'] = $stmt->fetch()['total'];
} catch (Exception $e) {
    $stats['certificats_generes'] = 0;
}

// Transferts complétés sans certificat
try {
    $stmt = $database->query(
        "SELECT COUNT(*) as total FROM transfers 
         WHERE statut = 'complete' AND (certificat_genere = 0 OR certificat_genere IS NULL)",
        []
    );
    $stats['sans_certificat'] = $stmt->fetch()['total'];
} catch (Exception $e) {
    $stats['sans_certificat'] = 0;
}

// Certificats par type
try {
    $stats_par_type = $database->query(
        "SELECT type_mouvement, COUNT(*) as nombre
         FROM transfers 
         WHERE certificat_genere = 1 AND statut = 'complete'
         GROUP BY type_mouvement",
        []
    )->fetchAll();
} catch (Exception $e) {
    $stats_par_type = [];
}

// Construction de la requête avec filtres
$where_conditions = ["t.statut = 'complete'"];
$params = [];

if ($search) {
    $where_conditions[] = "(e.nom LIKE ? OR e.prenom LIKE ? OR e.numero_matricule LIKE ? OR t.numero_certificat LIKE ?)";
    $search_param = "%$search%";
    $params = array_merge($params, [$search_param, $search_param, $search_param, $search_param]);
}

if ($status_filter === 'avec_certificat') {
    $where_conditions[] = "t.certificat_genere = 1";
} elseif ($status_filter === 'sans_certificat') {
    $where_conditions[] = "(t.certificat_genere = 0 OR t.certificat_genere IS NULL)";
}

if ($type_filter) {
    $where_conditions[] = "t.type_mouvement = ?";
    $params[] = $type_filter;
}

$where_clause = implode(' AND ', $where_conditions);

// Compter le total pour la pagination
try {
    $total_stmt = $database->query(
        "SELECT COUNT(*) as total
         FROM transfers t
         JOIN eleves e ON t.eleve_id = e.id
         WHERE $where_clause",
        $params
    );
    $total_records = $total_stmt->fetch()['total'];
} catch (Exception $e) {
    $total_records = 0;
}

$total_pages = ceil($total_records / $per_page);

// Récupérer les transferts avec informations des certificats
try {
    $transfers = $database->query(
        "SELECT t.*, e.nom, e.prenom, e.numero_matricule, e.date_naissance,
                c_orig.nom as classe_origine_nom, c_orig.niveau as classe_origine_niveau,
                c_dest.nom as classe_destination_nom, c_dest.niveau as classe_destination_niveau,
                u_traite.nom as traite_par_nom, u_traite.prenom as traite_par_prenom
         FROM transfers t
         JOIN eleves e ON t.eleve_id = e.id
         LEFT JOIN classes c_orig ON t.classe_origine_id = c_orig.id
         LEFT JOIN classes c_dest ON t.classe_destination_id = c_dest.id
         LEFT JOIN users u_traite ON t.traite_par = u_traite.id
         WHERE $where_clause
         ORDER BY t.date_effective DESC, t.created_at DESC
         LIMIT $per_page OFFSET $offset",
        $params
    )->fetchAll();
} catch (Exception $e) {
    $transfers = [];
}

// Certificats récemment générés (7 derniers jours)
try {
    $recent_certificates = $database->query(
        "SELECT t.*, e.nom, e.prenom, e.numero_matricule
         FROM transfers t
         JOIN eleves e ON t.eleve_id = e.id
         WHERE t.certificat_genere = 1 
         AND t.updated_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
         ORDER BY t.updated_at DESC
         LIMIT 10",
        []
    )->fetchAll();
} catch (Exception $e) {
    $recent_certificates = [];
}

// Vérifier si la table transfers existe
$table_exists = false;
try {
    $database->query("SELECT 1 FROM transfers LIMIT 1");
    $table_exists = true;
} catch (Exception $e) {
    $table_exists = false;
}

include '../../../../includes/header.php';
?>

<!-- Styles CSS modernes -->
<style>
.certificate-header {
    background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
    color: white;
    padding: 2rem 0;
    margin: -20px -15px 30px -15px;
    border-radius: 0 0 20px 20px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.1);
}

.certificate-header h1 {
    font-weight: 300;
    font-size: 2.5rem;
    margin-bottom: 0.5rem;
}

.certificate-header .subtitle {
    opacity: 0.9;
    font-size: 1.1rem;
}

.stats-card {
    background: white;
    border-radius: 15px;
    padding: 1.5rem;
    box-shadow: 0 5px 15px rgba(0,0,0,0.08);
    border: none;
    margin-bottom: 1.5rem;
    transition: transform 0.3s ease;
}

.stats-card:hover {
    transform: translateY(-5px);
}

.stats-icon {
    width: 60px;
    height: 60px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    margin-bottom: 1rem;
}

.certificate-card {
    background: white;
    border-radius: 15px;
    padding: 1.5rem;
    box-shadow: 0 5px 15px rgba(0,0,0,0.08);
    border: none;
    margin-bottom: 1.5rem;
    transition: all 0.3s ease;
}

.certificate-card:hover {
    box-shadow: 0 8px 25px rgba(0,0,0,0.15);
}

.student-info {
    background: #f8f9fa;
    border-radius: 10px;
    padding: 1rem;
    margin-bottom: 1rem;
}

.certificate-status {
    display: inline-flex;
    align-items: center;
    padding: 0.5rem 1rem;
    border-radius: 20px;
    font-weight: 500;
    font-size: 0.9rem;
}

.status-generated {
    background: #d4edda;
    color: #155724;
}

.status-pending {
    background: #fff3cd;
    color: #856404;
}

.btn-modern {
    border-radius: 25px;
    padding: 0.5rem 1.5rem;
    font-weight: 600;
    transition: all 0.3s ease;
    border: none;
    box-shadow: 0 3px 15px rgba(0,0,0,0.1);
}

.btn-modern:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 25px rgba(0,0,0,0.2);
}

.search-box {
    background: white;
    border-radius: 15px;
    padding: 1.5rem;
    box-shadow: 0 5px 15px rgba(0,0,0,0.08);
    margin-bottom: 2rem;
}

@keyframes fadeInUp {
    from {
        opacity: 0;
        transform: translateY(30px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.animate-fade-in {
    animation: fadeInUp 0.6s ease-out;
}

.animate-delay-1 { animation-delay: 0.1s; }
.animate-delay-2 { animation-delay: 0.2s; }
.animate-delay-3 { animation-delay: 0.3s; }

@media (max-width: 768px) {
    .certificate-header {
        margin: -20px -15px 20px -15px;
        padding: 1.5rem 0;
    }

    .certificate-header h1 {
        font-size: 2rem;
    }

    .stats-card, .certificate-card {
        padding: 1rem;
    }
}
</style>

<?php if (!$table_exists): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <h4 class="alert-heading">
            <i class="fas fa-exclamation-triangle me-2"></i>
            Table manquante détectée
        </h4>
        <p>La table <code>transfers</code> n'existe pas dans la base de données.</p>
        <p>Cette table est nécessaire pour la gestion des certificats de transfert.</p>
        <hr>
        <p class="mb-0">
            <a href="../../../../fix-students-tables.php" class="btn btn-warning me-2">
                <i class="fas fa-tools me-1"></i>
                Créer la table automatiquement
            </a>
            <a href="../../../../debug-tables.php" class="btn btn-info">
                <i class="fas fa-search me-1"></i>
                Diagnostic complet
            </a>
        </p>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<!-- En-tête moderne -->
<div class="certificate-header">
    <div class="container-fluid">
        <div class="row align-items-center">
            <div class="col-md-8">
                <h1 class="animate-fade-in">
                    <i class="fas fa-certificate me-3"></i>
                    Gestion des Certificats
                </h1>
                <p class="subtitle animate-fade-in animate-delay-1">
                    Génération et gestion des certificats de transfert et de scolarité
                </p>
            </div>
            <div class="col-md-4 text-end">
                <div class="animate-fade-in animate-delay-2">
                    <a href="../index.php" class="btn btn-light btn-modern">
                        <i class="fas fa-arrow-left me-2"></i>
                        Retour aux transferts
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Statistiques -->
<div class="row mb-4">
    <div class="col-lg-3 col-md-6 mb-3">
        <div class="stats-card animate-fade-in">
            <div class="stats-icon bg-success text-white mx-auto">
                <i class="fas fa-certificate"></i>
            </div>
            <div class="text-center">
                <h3 class="mb-1"><?php echo $stats['certificats_generes']; ?></h3>
                <p class="text-muted mb-0">Certificats générés</p>
            </div>
        </div>
    </div>
    <div class="col-lg-3 col-md-6 mb-3">
        <div class="stats-card animate-fade-in animate-delay-1">
            <div class="stats-icon bg-warning text-white mx-auto">
                <i class="fas fa-clock"></i>
            </div>
            <div class="text-center">
                <h3 class="mb-1"><?php echo $stats['sans_certificat']; ?></h3>
                <p class="text-muted mb-0">En attente de certificat</p>
            </div>
        </div>
    </div>
    <div class="col-lg-3 col-md-6 mb-3">
        <div class="stats-card animate-fade-in animate-delay-2">
            <div class="stats-icon bg-info text-white mx-auto">
                <i class="fas fa-exchange-alt"></i>
            </div>
            <div class="text-center">
                <h3 class="mb-1">
                    <?php
                    $transferts = array_filter($stats_par_type, function($s) { return $s['type_mouvement'] === 'transfert_sortant' || $s['type_mouvement'] === 'transfert_entrant'; });
                    echo array_sum(array_column($transferts, 'nombre'));
                    ?>
                </h3>
                <p class="text-muted mb-0">Certificats de transfert</p>
            </div>
        </div>
    </div>
    <div class="col-lg-3 col-md-6 mb-3">
        <div class="stats-card animate-fade-in animate-delay-3">
            <div class="stats-icon bg-secondary text-white mx-auto">
                <i class="fas fa-graduation-cap"></i>
            </div>
            <div class="text-center">
                <h3 class="mb-1">
                    <?php
                    $sorties = array_filter($stats_par_type, function($s) { return $s['type_mouvement'] === 'sortie_definitive'; });
                    echo array_sum(array_column($sorties, 'nombre'));
                    ?>
                </h3>
                <p class="text-muted mb-0">Certificats de fin de scolarité</p>
            </div>
        </div>
    </div>
</div>

<!-- Recherche et filtres -->
<div class="search-box animate-fade-in animate-delay-1">
    <form method="GET" class="row g-3">
        <div class="col-md-4">
            <label for="search" class="form-label">
                <i class="fas fa-search me-1"></i>
                Rechercher
            </label>
            <input type="text" class="form-control" id="search" name="search"
                   value="<?php echo htmlspecialchars($search); ?>"
                   placeholder="Nom, prénom, matricule ou N° certificat...">
        </div>
        <div class="col-md-3">
            <label for="status" class="form-label">
                <i class="fas fa-filter me-1"></i>
                Statut du certificat
            </label>
            <select class="form-select" id="status" name="status">
                <option value="">Tous les statuts</option>
                <option value="avec_certificat" <?php echo $status_filter === 'avec_certificat' ? 'selected' : ''; ?>>
                    Avec certificat
                </option>
                <option value="sans_certificat" <?php echo $status_filter === 'sans_certificat' ? 'selected' : ''; ?>>
                    Sans certificat
                </option>
            </select>
        </div>
        <div class="col-md-3">
            <label for="type" class="form-label">
                <i class="fas fa-tags me-1"></i>
                Type de mouvement
            </label>
            <select class="form-select" id="type" name="type">
                <option value="">Tous les types</option>
                <option value="transfert_entrant" <?php echo $type_filter === 'transfert_entrant' ? 'selected' : ''; ?>>
                    Transfert entrant
                </option>
                <option value="transfert_sortant" <?php echo $type_filter === 'transfert_sortant' ? 'selected' : ''; ?>>
                    Transfert sortant
                </option>
                <option value="sortie_definitive" <?php echo $type_filter === 'sortie_definitive' ? 'selected' : ''; ?>>
                    Sortie définitive
                </option>
            </select>
        </div>
        <div class="col-md-2">
            <label class="form-label">&nbsp;</label>
            <div class="d-grid">
                <button type="submit" class="btn btn-primary btn-modern">
                    <i class="fas fa-search me-1"></i>
                    Rechercher
                </button>
            </div>
        </div>
    </form>
</div>

<!-- Actions rapides -->
<?php if (checkPermission('students')): ?>
<div class="row mb-4">
    <div class="col-12">
        <div class="certificate-card animate-fade-in animate-delay-2">
            <h5 class="mb-3">
                <i class="fas fa-bolt me-2"></i>
                Actions rapides
            </h5>
            <div class="row">
                <div class="col-md-3 mb-2">
                    <div class="d-grid">
                        <a href="?status=sans_certificat" class="btn btn-warning btn-modern">
                            <i class="fas fa-clock me-2"></i>
                            Transferts sans certificat
                        </a>
                    </div>
                </div>
                <div class="col-md-3 mb-2">
                    <div class="d-grid">
                        <a href="../bulk-process.php" class="btn btn-info btn-modern">
                            <i class="fas fa-tasks me-2"></i>
                            Traitement en masse
                        </a>
                    </div>
                </div>
                <div class="col-md-3 mb-2">
                    <div class="d-grid">
                        <a href="../reports/transfers.php" class="btn btn-secondary btn-modern">
                            <i class="fas fa-chart-bar me-2"></i>
                            Rapport des certificats
                        </a>
                    </div>
                </div>
                <div class="col-md-3 mb-2">
                    <div class="d-grid">
                        <a href="../exports/movements.php" class="btn btn-success btn-modern">
                            <i class="fas fa-file-export me-2"></i>
                            Exporter les données
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Contenu principal -->
<div class="row">
    <div class="col-lg-8">
        <!-- Liste des transferts et certificats -->
        <div class="certificate-card animate-fade-in animate-delay-3">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="mb-0">
                    <i class="fas fa-list me-2"></i>
                    Transferts et Certificats
                    <?php if (!empty($transfers)): ?>
                        <span class="badge bg-secondary"><?php echo count($transfers); ?></span>
                    <?php endif; ?>
                </h5>
                <?php if ($search || $status_filter || $type_filter): ?>
                    <a href="?" class="btn btn-outline-secondary btn-sm">
                        <i class="fas fa-times me-1"></i>
                        Effacer filtres
                    </a>
                <?php endif; ?>
            </div>

            <?php if (!empty($transfers)): ?>
                <div class="row">
                    <?php foreach ($transfers as $transfer): ?>
                        <div class="col-md-6 mb-3">
                            <div class="card h-100 border-0 shadow-sm">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                        <h6 class="card-title mb-0">
                                            <?php echo htmlspecialchars($transfer['nom'] . ' ' . $transfer['prenom']); ?>
                                        </h6>
                                        <span class="certificate-status <?php echo $transfer['certificat_genere'] ? 'status-generated' : 'status-pending'; ?>">
                                            <i class="fas fa-<?php echo $transfer['certificat_genere'] ? 'check-circle' : 'clock'; ?> me-1"></i>
                                            <?php echo $transfer['certificat_genere'] ? 'Généré' : 'En attente'; ?>
                                        </span>
                                    </div>

                                    <div class="student-info">
                                        <div class="row">
                                            <div class="col-6">
                                                <small class="text-muted">Matricule:</small><br>
                                                <strong><?php echo htmlspecialchars($transfer['numero_matricule']); ?></strong>
                                            </div>
                                            <div class="col-6">
                                                <small class="text-muted">Date effective:</small><br>
                                                <strong><?php echo date('d/m/Y', strtotime($transfer['date_effective'])); ?></strong>
                                            </div>
                                        </div>
                                        <div class="row mt-2">
                                            <div class="col-12">
                                                <small class="text-muted">Type:</small><br>
                                                <span class="badge bg-<?php
                                                    echo $transfer['type_mouvement'] === 'transfert_entrant' ? 'success' :
                                                        ($transfer['type_mouvement'] === 'transfert_sortant' ? 'info' : 'secondary');
                                                ?>">
                                                    <?php
                                                    $type_labels = [
                                                        'transfert_entrant' => 'Transfert entrant',
                                                        'transfert_sortant' => 'Transfert sortant',
                                                        'sortie_definitive' => 'Sortie définitive'
                                                    ];
                                                    echo $type_labels[$transfer['type_mouvement']] ?? $transfer['type_mouvement'];
                                                    ?>
                                                </span>
                                            </div>
                                        </div>
                                        <?php if ($transfer['numero_certificat']): ?>
                                            <div class="row mt-2">
                                                <div class="col-12">
                                                    <small class="text-muted">N° Certificat:</small><br>
                                                    <strong class="text-success"><?php echo htmlspecialchars($transfer['numero_certificat']); ?></strong>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                    </div>

                                    <div class="d-flex justify-content-between align-items-center mt-3">
                                        <div class="btn-group btn-group-sm">
                                            <a href="../view-transfer.php?id=<?php echo $transfer['id']; ?>"
                                               class="btn btn-outline-info" title="Voir le transfert">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <?php if ($transfer['certificat_genere']): ?>
                                                <a href="generate.php?id=<?php echo $transfer['id']; ?>&action=print"
                                                   target="_blank" class="btn btn-outline-success" title="Imprimer le certificat">
                                                    <i class="fas fa-print"></i>
                                                </a>
                                            <?php endif; ?>
                                        </div>

                                        <?php if (checkPermission('students')): ?>
                                            <?php if (!$transfer['certificat_genere']): ?>
                                                <a href="generate.php?id=<?php echo $transfer['id']; ?>"
                                                   class="btn btn-primary btn-sm btn-modern">
                                                    <i class="fas fa-certificate me-1"></i>
                                                    Générer certificat
                                                </a>
                                            <?php else: ?>
                                                <a href="generate.php?id=<?php echo $transfer['id']; ?>"
                                                   class="btn btn-success btn-sm btn-modern">
                                                    <i class="fas fa-cog me-1"></i>
                                                    Gérer certificat
                                                </a>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                    <nav aria-label="Navigation des certificats" class="mt-4">
                        <ul class="pagination justify-content-center">
                            <?php if ($page > 1): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status_filter); ?>&type=<?php echo urlencode($type_filter); ?>">
                                        <i class="fas fa-chevron-left"></i>
                                    </a>
                                </li>
                            <?php endif; ?>

                            <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                                <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status_filter); ?>&type=<?php echo urlencode($type_filter); ?>">
                                        <?php echo $i; ?>
                                    </a>
                                </li>
                            <?php endfor; ?>

                            <?php if ($page < $total_pages): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status_filter); ?>&type=<?php echo urlencode($type_filter); ?>">
                                        <i class="fas fa-chevron-right"></i>
                                    </a>
                                </li>
                            <?php endif; ?>
                        </ul>
                    </nav>
                <?php endif; ?>

            <?php else: ?>
                <div class="text-center py-5">
                    <i class="fas fa-certificate fa-4x text-muted mb-3"></i>
                    <h4 class="text-muted">Aucun transfert trouvé</h4>
                    <p class="text-muted">
                        <?php if ($search || $status_filter || $type_filter): ?>
                            Aucun transfert ne correspond aux critères de recherche.
                        <?php else: ?>
                            Aucun transfert complété n'a été trouvé.
                        <?php endif; ?>
                    </p>
                    <?php if ($search || $status_filter || $type_filter): ?>
                        <a href="?" class="btn btn-outline-secondary btn-modern">
                            <i class="fas fa-times me-1"></i>
                            Effacer les filtres
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="col-lg-4">
        <!-- Certificats récents -->
        <?php if (!empty($recent_certificates)): ?>
        <div class="certificate-card animate-fade-in animate-delay-3">
            <h6 class="mb-3">
                <i class="fas fa-clock me-2"></i>
                Certificats récents (7 derniers jours)
            </h6>
            <?php foreach ($recent_certificates as $cert): ?>
                <div class="d-flex justify-content-between align-items-center mb-3 p-2 bg-light rounded">
                    <div>
                        <strong><?php echo htmlspecialchars($cert['nom'] . ' ' . substr($cert['prenom'], 0, 1) . '.'); ?></strong>
                        <br><small class="text-muted">
                            <?php echo htmlspecialchars($cert['numero_matricule']); ?>
                        </small>
                        <br><small class="text-success">
                            <i class="fas fa-certificate me-1"></i>
                            <?php echo htmlspecialchars($cert['numero_certificat']); ?>
                        </small>
                    </div>
                    <div class="text-end">
                        <a href="generate.php?id=<?php echo $cert['id']; ?>&action=print"
                           target="_blank" class="btn btn-sm btn-outline-success" title="Imprimer">
                            <i class="fas fa-print"></i>
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- Statistiques détaillées -->
        <div class="certificate-card animate-fade-in animate-delay-3">
            <h6 class="mb-3">
                <i class="fas fa-chart-pie me-2"></i>
                Répartition par type
            </h6>
            <?php if (!empty($stats_par_type)): ?>
                <?php foreach ($stats_par_type as $stat): ?>
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span>
                            <?php
                            $type_labels = [
                                'transfert_entrant' => 'Transferts entrants',
                                'transfert_sortant' => 'Transferts sortants',
                                'sortie_definitive' => 'Sorties définitives'
                            ];
                            echo $type_labels[$stat['type_mouvement']] ?? $stat['type_mouvement'];
                            ?>
                        </span>
                        <span class="badge bg-<?php
                            echo $stat['type_mouvement'] === 'transfert_entrant' ? 'success' :
                                ($stat['type_mouvement'] === 'transfert_sortant' ? 'info' : 'secondary');
                        ?>">
                            <?php echo $stat['nombre']; ?>
                        </span>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p class="text-muted text-center">Aucune donnée disponible</p>
            <?php endif; ?>
        </div>

        <!-- Guide d'utilisation -->
        <div class="certificate-card animate-fade-in animate-delay-3">
            <h6 class="mb-3">
                <i class="fas fa-info-circle me-2"></i>
                Guide d'utilisation
            </h6>
            <div class="small">
                <div class="mb-3">
                    <strong>Génération de certificats :</strong>
                    <ul class="mt-1 mb-0">
                        <li>Les certificats sont générés automatiquement pour les transferts complétés</li>
                        <li>Chaque certificat reçoit un numéro unique</li>
                        <li>Les certificats peuvent être imprimés ou téléchargés en PDF</li>
                    </ul>
                </div>
                <div class="mb-3">
                    <strong>Types de certificats :</strong>
                    <ul class="mt-1 mb-0">
                        <li><span class="badge bg-success me-1">Entrant</span> Admission par transfert</li>
                        <li><span class="badge bg-info me-1">Sortant</span> Transfert vers autre école</li>
                        <li><span class="badge bg-secondary me-1">Sortie</span> Fin de scolarité</li>
                    </ul>
                </div>
                <div>
                    <strong>Actions disponibles :</strong>
                    <ul class="mt-1 mb-0">
                        <li>Recherche par nom, matricule ou N° certificat</li>
                        <li>Filtrage par statut et type</li>
                        <li>Impression et téléchargement PDF</li>
                        <li>Régénération si nécessaire</li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- Liens utiles -->
        <div class="certificate-card animate-fade-in animate-delay-3">
            <h6 class="mb-3">
                <i class="fas fa-external-link-alt me-2"></i>
                Liens utiles
            </h6>
            <div class="d-grid gap-2">
                <a href="../index.php" class="btn btn-outline-primary btn-sm">
                    <i class="fas fa-exchange-alt me-2"></i>
                    Gestion des transferts
                </a>
                <a href="../bulk-process.php" class="btn btn-outline-info btn-sm">
                    <i class="fas fa-tasks me-2"></i>
                    Traitement en masse
                </a>
                <a href="../reports/transfers.php" class="btn btn-outline-secondary btn-sm">
                    <i class="fas fa-chart-bar me-2"></i>
                    Rapports et statistiques
                </a>
                <a href="../exports/movements.php" class="btn btn-outline-success btn-sm">
                    <i class="fas fa-file-export me-2"></i>
                    Exporter les données
                </a>
            </div>
        </div>
    </div>
</div>

<script>
// Confirmation pour les actions sensibles
document.addEventListener('DOMContentLoaded', function() {
    // Animation des cartes au scroll
    const observerOptions = {
        threshold: 0.1,
        rootMargin: '0px 0px -50px 0px'
    };

    const observer = new IntersectionObserver(function(entries) {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.style.opacity = '1';
                entry.target.style.transform = 'translateY(0)';
            }
        });
    }, observerOptions);

    // Observer toutes les cartes
    document.querySelectorAll('.certificate-card, .stats-card').forEach(card => {
        card.style.opacity = '0';
        card.style.transform = 'translateY(20px)';
        card.style.transition = 'opacity 0.6s ease, transform 0.6s ease';
        observer.observe(card);
    });

    // Recherche en temps réel (optionnel)
    const searchInput = document.getElementById('search');
    if (searchInput) {
        let searchTimeout;
        searchInput.addEventListener('input', function() {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                // Ici on pourrait implémenter une recherche AJAX
                // Pour l'instant, on laisse le comportement par défaut
            }, 500);
        });
    }
});
</script>

<?php include '../../../../includes/footer.php'; ?>
