<?php
/**
 * Module Applications - Gestion détaillée des candidatures
 * Application de gestion scolaire - République Démocratique du Congo
 */

require_once '../../../../config/config.php';
require_once '../../../../config/database.php';
require_once '../../../../includes/functions.php';

// Vérifier l'authentification et les permissions
requireLogin();
if (!checkPermission('students') && !checkPermission('students_view')) {
    showMessage('error', 'Accès refusé à ce module.');
    redirectTo('../../index.php');
}

$page_title = 'Gestion des Candidatures';

// Obtenir l'année scolaire actuelle
$current_year = getCurrentAcademicYear();

// Paramètres de filtrage et pagination
$status_filter = $_GET['status'] ?? '';
$priorite_filter = $_GET['priorite'] ?? '';
$classe_filter = $_GET['classe'] ?? '';
$search = $_GET['search'] ?? '';
$page = max(1, intval($_GET['page'] ?? 1));
$per_page = 20;
$offset = ($page - 1) * $per_page;

// Construction de la requête avec filtres
$where_conditions = ["da.annee_scolaire_id = ?"];
$params = [$current_year['id'] ?? 0];

if ($status_filter) {
    $where_conditions[] = "da.status = ?";
    $params[] = $status_filter;
}

if ($priorite_filter) {
    $where_conditions[] = "da.priorite = ?";
    $params[] = $priorite_filter;
}

if ($classe_filter) {
    $where_conditions[] = "da.classe_demandee_id = ?";
    $params[] = $classe_filter;
}

if ($search) {
    $where_conditions[] = "(da.nom_eleve LIKE ? OR da.prenom_eleve LIKE ? OR da.numero_demande LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

$where_clause = implode(' AND ', $where_conditions);

// Compter le total pour la pagination
try {
    $total_stmt = $database->query(
        "SELECT COUNT(*) as total FROM demandes_admission da WHERE $where_clause",
        $params
    );
    $total_records = $total_stmt->fetch()['total'];
    $total_pages = ceil($total_records / $per_page);
} catch (Exception $e) {
    $total_records = 0;
    $total_pages = 1;
}

// Récupérer les candidatures avec pagination
try {
    $candidatures = $database->query(
        "SELECT da.*, c.nom as classe_demandee, c.niveau, c.section,
                u.username as traite_par_nom,
                DATEDIFF(NOW(), da.created_at) as jours_depuis_demande
         FROM demandes_admission da
         LEFT JOIN classes c ON da.classe_demandee_id = c.id
         LEFT JOIN users u ON da.traite_par = u.id
         WHERE $where_clause
         ORDER BY 
            CASE da.priorite 
                WHEN 'tres_urgente' THEN 1 
                WHEN 'urgente' THEN 2 
                WHEN 'normale' THEN 3 
            END,
            da.created_at DESC
         LIMIT $per_page OFFSET $offset",
        $params
    )->fetchAll();
} catch (Exception $e) {
    $candidatures = [];
    showMessage('error', 'Erreur lors du chargement des candidatures : ' . $e->getMessage());
}

// Récupérer les options pour les filtres
try {
    $classes_options = $database->query(
        "SELECT id, nom, niveau FROM classes WHERE annee_scolaire_id = ? ORDER BY niveau, nom",
        [$current_year['id'] ?? 0]
    )->fetchAll();
} catch (Exception $e) {
    $classes_options = [];
}

// Statistiques rapides
$stats = [];
try {
    $stats_queries = [
        'total' => "SELECT COUNT(*) as count FROM demandes_admission WHERE annee_scolaire_id = " . ($current_year['id'] ?? 0),
        'en_attente' => "SELECT COUNT(*) as count FROM demandes_admission WHERE status = 'en_attente' AND annee_scolaire_id = " . ($current_year['id'] ?? 0),
        'acceptee' => "SELECT COUNT(*) as count FROM demandes_admission WHERE status = 'acceptee' AND annee_scolaire_id = " . ($current_year['id'] ?? 0),
        'refusee' => "SELECT COUNT(*) as count FROM demandes_admission WHERE status = 'refusee' AND annee_scolaire_id = " . ($current_year['id'] ?? 0),
        'urgentes' => "SELECT COUNT(*) as count FROM demandes_admission WHERE priorite IN ('urgente', 'tres_urgente') AND status = 'en_attente' AND annee_scolaire_id = " . ($current_year['id'] ?? 0)
    ];
    
    foreach ($stats_queries as $key => $query) {
        $result = $database->query($query)->fetch();
        $stats[$key] = $result['count'] ?? 0;
    }
} catch (Exception $e) {
    $stats = ['total' => 0, 'en_attente' => 0, 'acceptee' => 0, 'refusee' => 0, 'urgentes' => 0];
}

include '../../../../includes/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">
        <i class="fas fa-file-alt me-2"></i>
        Gestion des Candidatures
    </h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <div class="btn-group me-2">
            <a href="../index.php" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-1"></i>
                Retour aux Admissions
            </a>
        </div>
        <?php if (checkPermission('students')): ?>
            <div class="btn-group me-2">
                <a href="add.php" class="btn btn-primary">
                    <i class="fas fa-plus me-1"></i>
                    Nouvelle Candidature
                </a>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Statistiques rapides -->
<div class="row mb-4">
    <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
        <div class="card bg-primary text-white">
            <div class="card-body text-center">
                <h4><?php echo $stats['total']; ?></h4>
                <small>Total</small>
            </div>
        </div>
    </div>
    <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
        <div class="card bg-warning text-white">
            <div class="card-body text-center">
                <h4><?php echo $stats['en_attente']; ?></h4>
                <small>En attente</small>
            </div>
        </div>
    </div>
    <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
        <div class="card bg-success text-white">
            <div class="card-body text-center">
                <h4><?php echo $stats['acceptee']; ?></h4>
                <small>Acceptées</small>
            </div>
        </div>
    </div>
    <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
        <div class="card bg-danger text-white">
            <div class="card-body text-center">
                <h4><?php echo $stats['refusee']; ?></h4>
                <small>Refusées</small>
            </div>
        </div>
    </div>
    <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
        <div class="card bg-info text-white">
            <div class="card-body text-center">
                <h4><?php echo $stats['urgentes']; ?></h4>
                <small>Urgentes</small>
            </div>
        </div>
    </div>
</div>

<!-- Filtres et recherche -->
<div class="card mb-4">
    <div class="card-header">
        <h5 class="mb-0">
            <i class="fas fa-filter me-2"></i>
            Filtres et Recherche
        </h5>
    </div>
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-md-3">
                <label for="search" class="form-label">Recherche</label>
                <input type="text" class="form-control" id="search" name="search" 
                       value="<?php echo htmlspecialchars($search); ?>" 
                       placeholder="Nom, prénom ou numéro...">
            </div>
            <div class="col-md-2">
                <label for="status" class="form-label">Statut</label>
                <select class="form-select" id="status" name="status">
                    <option value="">Tous les statuts</option>
                    <option value="en_attente" <?php echo $status_filter === 'en_attente' ? 'selected' : ''; ?>>En attente</option>
                    <option value="acceptee" <?php echo $status_filter === 'acceptee' ? 'selected' : ''; ?>>Acceptée</option>
                    <option value="refusee" <?php echo $status_filter === 'refusee' ? 'selected' : ''; ?>>Refusée</option>
                    <option value="en_cours_traitement" <?php echo $status_filter === 'en_cours_traitement' ? 'selected' : ''; ?>>En cours</option>
                    <option value="inscrit" <?php echo $status_filter === 'inscrit' ? 'selected' : ''; ?>>Inscrit</option>
                </select>
            </div>
            <div class="col-md-2">
                <label for="priorite" class="form-label">Priorité</label>
                <select class="form-select" id="priorite" name="priorite">
                    <option value="">Toutes priorités</option>
                    <option value="normale" <?php echo $priorite_filter === 'normale' ? 'selected' : ''; ?>>Normale</option>
                    <option value="urgente" <?php echo $priorite_filter === 'urgente' ? 'selected' : ''; ?>>Urgente</option>
                    <option value="tres_urgente" <?php echo $priorite_filter === 'tres_urgente' ? 'selected' : ''; ?>>Très urgente</option>
                </select>
            </div>
            <div class="col-md-3">
                <label for="classe" class="form-label">Classe demandée</label>
                <select class="form-select" id="classe" name="classe">
                    <option value="">Toutes les classes</option>
                    <?php foreach ($classes_options as $classe): ?>
                        <option value="<?php echo $classe['id']; ?>" <?php echo $classe_filter == $classe['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($classe['nom'] . ' (' . $classe['niveau'] . ')'); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2 d-flex align-items-end">
                <button type="submit" class="btn btn-primary me-2">
                    <i class="fas fa-search me-1"></i>
                    Filtrer
                </button>
                <a href="?" class="btn btn-outline-secondary">
                    <i class="fas fa-times me-1"></i>
                    Reset
                </a>
            </div>
        </form>
    </div>
</div>

<!-- Liste des candidatures -->
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">
            <i class="fas fa-list me-2"></i>
            Candidatures (<?php echo $total_records; ?> résultat<?php echo $total_records > 1 ? 's' : ''; ?>)
        </h5>
        <?php if (checkPermission('students')): ?>
            <div class="btn-group">
                <button type="button" class="btn btn-sm btn-outline-primary dropdown-toggle" data-bs-toggle="dropdown">
                    <i class="fas fa-download me-1"></i>
                    Exporter
                </button>
                <ul class="dropdown-menu">
                    <li><a class="dropdown-item" href="export.php?format=excel&<?php echo http_build_query($_GET); ?>">
                        <i class="fas fa-file-excel me-2"></i>Excel
                    </a></li>
                    <li><a class="dropdown-item" href="export.php?format=pdf&<?php echo http_build_query($_GET); ?>">
                        <i class="fas fa-file-pdf me-2"></i>PDF
                    </a></li>
                </ul>
            </div>
        <?php endif; ?>
    </div>
    <div class="card-body p-0">
        <?php if (!empty($candidatures)): ?>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Numéro</th>
                            <th>Candidat</th>
                            <th>Classe demandée</th>
                            <th>Statut</th>
                            <th>Priorité</th>
                            <th>Date demande</th>
                            <th>Jours</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($candidatures as $candidature): ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($candidature['numero_demande']); ?></strong>
                                </td>
                                <td>
                                    <div>
                                        <strong><?php echo htmlspecialchars($candidature['nom_eleve'] . ' ' . $candidature['prenom_eleve']); ?></strong>
                                        <br><small class="text-muted">
                                            <?php echo htmlspecialchars($candidature['telephone_parent'] ?? 'Pas de téléphone'); ?>
                                        </small>
                                    </div>
                                </td>
                                <td>
                                    <?php if ($candidature['classe_demandee']): ?>
                                        <span class="badge bg-light text-dark">
                                            <?php echo htmlspecialchars($candidature['classe_demandee']); ?>
                                        </span>
                                        <br><small class="text-muted"><?php echo htmlspecialchars($candidature['niveau']); ?></small>
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
                                    $color = $status_colors[$candidature['status']] ?? 'secondary';
                                    ?>
                                    <span class="badge bg-<?php echo $color; ?>">
                                        <?php echo ucfirst(str_replace('_', ' ', $candidature['status'])); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php
                                    $priorite_colors = [
                                        'normale' => 'secondary',
                                        'urgente' => 'warning',
                                        'tres_urgente' => 'danger'
                                    ];
                                    $priorite_color = $priorite_colors[$candidature['priorite']] ?? 'secondary';
                                    ?>
                                    <span class="badge bg-<?php echo $priorite_color; ?>">
                                        <?php echo ucfirst(str_replace('_', ' ', $candidature['priorite'])); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php echo formatDate($candidature['created_at']); ?>
                                </td>
                                <td>
                                    <span class="badge bg-<?php echo $candidature['jours_depuis_demande'] > 7 ? 'danger' : 'info'; ?>">
                                        <?php echo $candidature['jours_depuis_demande']; ?> j
                                    </span>
                                </td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <a href="view.php?id=<?php echo $candidature['id']; ?>" 
                                           class="btn btn-outline-primary" title="Voir détails">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <?php if (checkPermission('students')): ?>
                                            <a href="edit.php?id=<?php echo $candidature['id']; ?>" 
                                               class="btn btn-outline-warning" title="Modifier">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <?php if ($candidature['status'] === 'en_attente'): ?>
                                                <button type="button" class="btn btn-outline-success" 
                                                        onclick="updateStatus(<?php echo $candidature['id']; ?>, 'acceptee')" 
                                                        title="Accepter">
                                                    <i class="fas fa-check"></i>
                                                </button>
                                                <button type="button" class="btn btn-outline-danger" 
                                                        onclick="updateStatus(<?php echo $candidature['id']; ?>, 'refusee')" 
                                                        title="Refuser">
                                                    <i class="fas fa-times"></i>
                                                </button>
                                            <?php endif; ?>
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
                <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                <h5 class="text-muted">Aucune candidature trouvée</h5>
                <p class="text-muted">
                    <?php if ($search || $status_filter || $priorite_filter || $classe_filter): ?>
                        Essayez de modifier vos critères de recherche.
                    <?php else: ?>
                        Aucune candidature n'a été soumise pour cette année scolaire.
                    <?php endif; ?>
                </p>
                <?php if (checkPermission('students')): ?>
                    <a href="add.php" class="btn btn-primary">
                        <i class="fas fa-plus me-1"></i>
                        Ajouter une candidature
                    </a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Pagination -->
    <?php if ($total_pages > 1): ?>
        <div class="card-footer">
            <nav aria-label="Navigation des candidatures">
                <ul class="pagination justify-content-center mb-0">
                    <?php if ($page > 1): ?>
                        <li class="page-item">
                            <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>">
                                <i class="fas fa-chevron-left"></i>
                            </a>
                        </li>
                    <?php endif; ?>
                    
                    <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                        <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                            <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>">
                                <?php echo $i; ?>
                            </a>
                        </li>
                    <?php endfor; ?>
                    
                    <?php if ($page < $total_pages): ?>
                        <li class="page-item">
                            <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>">
                                <i class="fas fa-chevron-right"></i>
                            </a>
                        </li>
                    <?php endif; ?>
                </ul>
            </nav>
            <div class="text-center mt-2">
                <small class="text-muted">
                    Page <?php echo $page; ?> sur <?php echo $total_pages; ?> 
                    (<?php echo $total_records; ?> candidature<?php echo $total_records > 1 ? 's' : ''; ?> au total)
                </small>
            </div>
        </div>
    <?php endif; ?>
</div>

<script>
function updateStatus(candidatureId, newStatus) {
    const statusNames = {
        'acceptee': 'accepter',
        'refusee': 'refuser'
    };
    
    if (confirm(`Êtes-vous sûr de vouloir ${statusNames[newStatus]} cette candidature ?`)) {
        // Ici vous pouvez ajouter un appel AJAX pour mettre à jour le statut
        // Pour l'instant, on redirige vers une page de traitement
        window.location.href = `update_status.php?id=${candidatureId}&status=${newStatus}`;
    }
}

// Auto-submit du formulaire de recherche après 500ms de pause
let searchTimeout;
document.getElementById('search').addEventListener('input', function() {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        this.form.submit();
    }, 500);
});
</script>

<?php include '../../../../includes/footer.php'; ?>
