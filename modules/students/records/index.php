<?php
/**
 * Module Dossiers Scolaires - Page principale
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

$page_title = 'Dossiers Scolaires';

// Obtenir l'année scolaire actuelle
$current_year = getCurrentAcademicYear();

// Paramètres de recherche et filtrage
$search = sanitizeInput($_GET['search'] ?? '');
$classe_filter = (int)($_GET['classe'] ?? 0);
$niveau_filter = sanitizeInput($_GET['niveau'] ?? '');

// Statistiques des dossiers
$stats = [];

// Total des dossiers
$stmt = $database->query(
    "SELECT COUNT(DISTINCT e.id) as total FROM eleves e 
     JOIN inscriptions i ON e.id = i.eleve_id 
     WHERE i.annee_scolaire_id = ?",
    [$current_year['id'] ?? 0]
);
$stats['total_dossiers'] = $stmt->fetch()['total'];

// Dossiers complets
$stmt = $database->query(
    "SELECT COUNT(DISTINCT e.id) as total FROM eleves e 
     JOIN inscriptions i ON e.id = i.eleve_id 
     WHERE i.annee_scolaire_id = ? 
     AND e.photo IS NOT NULL 
     AND e.date_naissance IS NOT NULL 
     AND e.lieu_naissance IS NOT NULL 
     AND e.adresse IS NOT NULL",
    [$current_year['id'] ?? 0]
);
$stats['dossiers_complets'] = $stmt->fetch()['total'];

// Dossiers incomplets
$stats['dossiers_incomplets'] = $stats['total_dossiers'] - $stats['dossiers_complets'];

// Dossiers mis à jour récemment
$stmt = $database->query(
    "SELECT COUNT(DISTINCT e.id) as total FROM eleves e 
     JOIN inscriptions i ON e.id = i.eleve_id 
     WHERE i.annee_scolaire_id = ? 
     AND e.updated_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)",
    [$current_year['id'] ?? 0]
);
$stats['dossiers_recents'] = $stmt->fetch()['total'];

// Construction de la requête pour les dossiers
$where_conditions = ["i.annee_scolaire_id = ?"];
$params = [$current_year['id'] ?? 0];

if ($search) {
    $where_conditions[] = "(e.nom LIKE ? OR e.prenom LIKE ? OR e.numero_matricule LIKE ?)";
    $search_param = "%$search%";
    $params = array_merge($params, [$search_param, $search_param, $search_param]);
}

if ($classe_filter) {
    $where_conditions[] = "i.classe_id = ?";
    $params[] = $classe_filter;
}

if ($niveau_filter) {
    $where_conditions[] = "c.niveau = ?";
    $params[] = $niveau_filter;
}

$where_clause = implode(' AND ', $where_conditions);

// Récupérer les dossiers avec pagination
$page = (int)($_GET['page'] ?? 1);
$per_page = 20;
$offset = ($page - 1) * $per_page;

$dossiers = $database->query(
    "SELECT e.*, CONCAT('INS', YEAR(i.date_inscription), LPAD(i.id, 4, '0')) as numero_inscription,
            i.date_inscription, i.status as statut_inscription,
            c.nom as classe_nom, c.niveau,
            CASE 
                WHEN e.photo IS NOT NULL AND e.date_naissance IS NOT NULL 
                     AND e.lieu_naissance IS NOT NULL AND e.adresse IS NOT NULL 
                THEN 'complet' 
                ELSE 'incomplet' 
            END as statut_dossier,
            (SELECT COUNT(*) FROM documents_eleves de WHERE de.eleve_id = e.id) as nb_documents
     FROM eleves e
     JOIN inscriptions i ON e.id = i.eleve_id
     JOIN classes c ON i.classe_id = c.id
     WHERE $where_clause
     ORDER BY e.nom, e.prenom
     LIMIT $per_page OFFSET $offset",
    $params
)->fetchAll();

// Compter le total pour la pagination
$total_stmt = $database->query(
    "SELECT COUNT(DISTINCT e.id) as total FROM eleves e 
     JOIN inscriptions i ON e.id = i.eleve_id 
     JOIN classes c ON i.classe_id = c.id 
     WHERE $where_clause",
    $params
);
$total_records = $total_stmt->fetch()['total'];
$total_pages = ceil($total_records / $per_page);

// Récupérer les classes pour le filtre
$classes = $database->query(
    "SELECT id, nom, niveau FROM classes WHERE annee_scolaire_id = ? ORDER BY niveau, nom",
    [$current_year['id'] ?? 0]
)->fetchAll();

// Dossiers nécessitant mise à jour
$dossiers_attention = $database->query(
    "SELECT e.*, c.nom as classe_nom, c.niveau,
            CASE 
                WHEN e.photo IS NULL THEN 'Photo manquante'
                WHEN e.date_naissance IS NULL THEN 'Date de naissance manquante'
                WHEN e.lieu_naissance IS NULL THEN 'Lieu de naissance manquant'
                WHEN e.adresse IS NULL THEN 'Adresse manquante'
                ELSE 'Informations incomplètes'
            END as probleme
     FROM eleves e
     JOIN inscriptions i ON e.id = i.eleve_id
     JOIN classes c ON i.classe_id = c.id
     WHERE i.annee_scolaire_id = ?
     AND (e.photo IS NULL OR e.date_naissance IS NULL 
          OR e.lieu_naissance IS NULL OR e.adresse IS NULL)
     ORDER BY i.date_inscription DESC
     LIMIT 8",
    [$current_year['id'] ?? 0]
)->fetchAll();

// Statistiques par niveau
$stats_par_niveau = $database->query(
    "SELECT c.niveau, 
            COUNT(DISTINCT e.id) as total,
            COUNT(CASE WHEN e.photo IS NOT NULL AND e.date_naissance IS NOT NULL 
                            AND e.lieu_naissance IS NOT NULL AND e.adresse IS NOT NULL 
                       THEN 1 END) as complets
     FROM eleves e
     JOIN inscriptions i ON e.id = i.eleve_id
     JOIN classes c ON i.classe_id = c.id
     WHERE i.annee_scolaire_id = ?
     GROUP BY c.niveau
     ORDER BY 
        CASE c.niveau 
            WHEN 'maternelle' THEN 1 
            WHEN 'primaire' THEN 2 
            WHEN 'secondaire' THEN 3 
        END",
    [$current_year['id'] ?? 0]
)->fetchAll();

include '../../../includes/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">
        <i class="fas fa-folder-open me-2"></i>
        Dossiers Scolaires
    </h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <div class="btn-group me-2">
            <a href="../index.php" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-1"></i>
                Retour
            </a>
        </div>
        <div class="btn-group me-2">
            <button type="button" class="btn btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">
                <i class="fas fa-file-export me-1"></i>
                Exporter
            </button>
            <ul class="dropdown-menu">
                <li><a class="dropdown-item" href="export.php?type=complete&<?php echo http_build_query($_GET); ?>">
                    <i class="fas fa-file-pdf me-2"></i>Dossiers complets PDF
                </a></li>
                <li><a class="dropdown-item" href="export.php?type=list&<?php echo http_build_query($_GET); ?>">
                    <i class="fas fa-file-excel me-2"></i>Liste Excel
                </a></li>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item" href="export.php?type=incomplete">
                    <i class="fas fa-exclamation-triangle me-2"></i>Dossiers incomplets
                </a></li>
            </ul>
        </div>
        <div class="btn-group">
            <button type="button" class="btn btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">
                <i class="fas fa-tools me-1"></i>
                Outils
            </button>
            <ul class="dropdown-menu">
                <li><a class="dropdown-item" href="bulk-update.php">
                    <i class="fas fa-edit me-2"></i>Mise à jour en masse
                </a></li>
                <li><a class="dropdown-item" href="archive.php">
                    <i class="fas fa-archive me-2"></i>Archiver dossiers
                </a></li>
                <li><a class="dropdown-item" href="statistics.php">
                    <i class="fas fa-chart-bar me-2"></i>Statistiques détaillées
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
                        <h4><?php echo $stats['total_dossiers']; ?></h4>
                        <p class="mb-0">Total dossiers</p>
                    </div>
                    <div class="align-self-center">
                        <i class="fas fa-folder fa-2x"></i>
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
                        <h4><?php echo $stats['dossiers_complets']; ?></h4>
                        <p class="mb-0">Complets</p>
                    </div>
                    <div class="align-self-center">
                        <i class="fas fa-check-circle fa-2x"></i>
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
                        <h4><?php echo $stats['dossiers_incomplets']; ?></h4>
                        <p class="mb-0">Incomplets</p>
                    </div>
                    <div class="align-self-center">
                        <i class="fas fa-exclamation-triangle fa-2x"></i>
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
                        <h4><?php echo $stats['dossiers_recents']; ?></h4>
                        <p class="mb-0">Mis à jour (30j)</p>
                    </div>
                    <div class="align-self-center">
                        <i class="fas fa-sync fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Filtres et recherche -->
<div class="card mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-md-4">
                <label for="search" class="form-label">Rechercher</label>
                <input type="text" class="form-control" id="search" name="search" 
                       value="<?php echo htmlspecialchars($search); ?>" 
                       placeholder="Nom, prénom ou matricule...">
            </div>
            <div class="col-md-3">
                <label for="niveau" class="form-label">Niveau</label>
                <select class="form-select" id="niveau" name="niveau">
                    <option value="">Tous les niveaux</option>
                    <option value="maternelle" <?php echo $niveau_filter === 'maternelle' ? 'selected' : ''; ?>>Maternelle</option>
                    <option value="primaire" <?php echo $niveau_filter === 'primaire' ? 'selected' : ''; ?>>Primaire</option>
                    <option value="secondaire" <?php echo $niveau_filter === 'secondaire' ? 'selected' : ''; ?>>Secondaire</option>
                </select>
            </div>
            <div class="col-md-3">
                <label for="classe" class="form-label">Classe</label>
                <select class="form-select" id="classe" name="classe">
                    <option value="">Toutes les classes</option>
                    <?php foreach ($classes as $classe): ?>
                        <option value="<?php echo $classe['id']; ?>" 
                                <?php echo $classe_filter == $classe['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($classe['nom']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">&nbsp;</label>
                <div class="d-grid">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search me-1"></i>
                        Filtrer
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Liste des dossiers -->
<div class="row">
    <div class="col-lg-9">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">
                    <i class="fas fa-list me-2"></i>
                    Dossiers scolaires
                    <?php if ($total_records > 0): ?>
                        <span class="badge bg-secondary"><?php echo $total_records; ?></span>
                    <?php endif; ?>
                </h5>
                <div class="btn-group btn-group-sm">
                    <button type="button" class="btn btn-outline-secondary" onclick="toggleView('cards')">
                        <i class="fas fa-th"></i>
                    </button>
                    <button type="button" class="btn btn-outline-secondary active" onclick="toggleView('table')">
                        <i class="fas fa-list"></i>
                    </button>
                </div>
            </div>
            <div class="card-body">
                <?php if (!empty($dossiers)): ?>
                    <!-- Vue tableau -->
                    <div id="table-view">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Photo</th>
                                        <th>Élève</th>
                                        <th>Matricule</th>
                                        <th>Classe</th>
                                        <th>Statut dossier</th>
                                        <th>Documents</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($dossiers as $dossier): ?>
                                        <tr>
                                            <td>
                                                <?php if ($dossier['photo']): ?>
                                                    <img src="<?php echo '../../../' . htmlspecialchars($dossier['photo']); ?>" 
                                                         alt="Photo" class="rounded-circle" width="40" height="40" style="object-fit: cover;">
                                                <?php else: ?>
                                                    <div class="bg-secondary rounded-circle d-flex align-items-center justify-content-center" 
                                                         style="width: 40px; height: 40px;">
                                                        <i class="fas fa-user text-white"></i>
                                                    </div>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <strong><?php echo htmlspecialchars($dossier['nom'] . ' ' . $dossier['prenom']); ?></strong>
                                                <br><small class="text-muted">
                                                    <?php echo $dossier['sexe'] === 'M' ? 'Masculin' : 'Féminin'; ?>
                                                    <?php if ($dossier['date_naissance']): ?>
                                                        - <?php echo calculateAge($dossier['date_naissance']); ?> ans
                                                    <?php endif; ?>
                                                </small>
                                            </td>
                                            <td>
                                                <code><?php echo htmlspecialchars($dossier['numero_matricule']); ?></code>
                                            </td>
                                            <td>
                                                <span class="badge bg-<?php 
                                                    echo $dossier['niveau'] === 'maternelle' ? 'warning' : 
                                                        ($dossier['niveau'] === 'primaire' ? 'success' : 'primary'); 
                                                ?>">
                                                    <?php echo htmlspecialchars($dossier['classe_nom']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="badge bg-<?php echo $dossier['statut_dossier'] === 'complet' ? 'success' : 'warning'; ?>">
                                                    <?php echo ucfirst($dossier['statut_dossier']); ?>
                                                </span>
                                            </td>
                                            <td class="text-center">
                                                <span class="badge bg-info"><?php echo $dossier['nb_documents']; ?></span>
                                            </td>
                                            <td>
                                                <div class="btn-group btn-group-sm">
                                                    <a href="view.php?id=<?php echo $dossier['id']; ?>" 
                                                       class="btn btn-outline-info" title="Voir dossier">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                    <?php if (checkPermission('students')): ?>
                                                        <a href="edit.php?id=<?php echo $dossier['id']; ?>" 
                                                           class="btn btn-outline-primary" title="Modifier">
                                                            <i class="fas fa-edit"></i>
                                                        </a>
                                                        <a href="documents.php?id=<?php echo $dossier['id']; ?>" 
                                                           class="btn btn-outline-secondary" title="Documents">
                                                            <i class="fas fa-folder"></i>
                                                        </a>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    
                    <!-- Vue cartes -->
                    <div id="cards-view" style="display: none;">
                        <div class="row">
                            <?php foreach ($dossiers as $dossier): ?>
                                <div class="col-md-6 col-lg-4 mb-3">
                                    <div class="card h-100">
                                        <div class="card-body">
                                            <div class="d-flex align-items-center mb-3">
                                                <?php if ($dossier['photo']): ?>
                                                    <img src="<?php echo '../../../' . htmlspecialchars($dossier['photo']); ?>" 
                                                         alt="Photo" class="rounded-circle me-3" width="50" height="50" style="object-fit: cover;">
                                                <?php else: ?>
                                                    <div class="bg-secondary rounded-circle d-flex align-items-center justify-content-center me-3" 
                                                         style="width: 50px; height: 50px;">
                                                        <i class="fas fa-user text-white"></i>
                                                    </div>
                                                <?php endif; ?>
                                                <div>
                                                    <h6 class="mb-0"><?php echo htmlspecialchars($dossier['nom'] . ' ' . $dossier['prenom']); ?></h6>
                                                    <small class="text-muted"><?php echo htmlspecialchars($dossier['numero_matricule']); ?></small>
                                                </div>
                                            </div>
                                            <div class="mb-2">
                                                <span class="badge bg-<?php 
                                                    echo $dossier['niveau'] === 'maternelle' ? 'warning' : 
                                                        ($dossier['niveau'] === 'primaire' ? 'success' : 'primary'); 
                                                ?>">
                                                    <?php echo htmlspecialchars($dossier['classe_nom']); ?>
                                                </span>
                                                <span class="badge bg-<?php echo $dossier['statut_dossier'] === 'complet' ? 'success' : 'warning'; ?>">
                                                    <?php echo ucfirst($dossier['statut_dossier']); ?>
                                                </span>
                                            </div>
                                            <div class="d-flex justify-content-between align-items-center">
                                                <small class="text-muted">
                                                    <?php echo $dossier['nb_documents']; ?> document(s)
                                                </small>
                                                <div class="btn-group btn-group-sm">
                                                    <a href="view.php?id=<?php echo $dossier['id']; ?>" 
                                                       class="btn btn-outline-info">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                    <?php if (checkPermission('students')): ?>
                                                        <a href="edit.php?id=<?php echo $dossier['id']; ?>" 
                                                           class="btn btn-outline-primary">
                                                            <i class="fas fa-edit"></i>
                                                        </a>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    
                    <!-- Pagination -->
                    <?php if ($total_pages > 1): ?>
                        <nav aria-label="Navigation des dossiers">
                            <ul class="pagination justify-content-center">
                                <?php if ($page > 1): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>">
                                            Précédent
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
                                            Suivant
                                        </a>
                                    </li>
                                <?php endif; ?>
                            </ul>
                        </nav>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="text-center py-5">
                        <i class="fas fa-folder-open fa-4x text-muted mb-3"></i>
                        <h4 class="text-muted">Aucun dossier trouvé</h4>
                        <p class="text-muted">
                            <?php if ($search || $classe_filter || $niveau_filter): ?>
                                Aucun dossier ne correspond aux critères de recherche.
                            <?php else: ?>
                                Aucun dossier scolaire n'est disponible.
                            <?php endif; ?>
                        </p>
                        <?php if ($search || $classe_filter || $niveau_filter): ?>
                            <a href="?" class="btn btn-outline-primary">
                                <i class="fas fa-times me-1"></i>
                                Effacer les filtres
                            </a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <div class="col-lg-3">
        <!-- Dossiers nécessitant attention -->
        <?php if (!empty($dossiers_attention)): ?>
        <div class="card mb-4">
            <div class="card-header bg-warning text-dark">
                <h6 class="mb-0">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    Dossiers incomplets
                </h6>
            </div>
            <div class="card-body">
                <?php foreach (array_slice($dossiers_attention, 0, 5) as $dossier): ?>
                    <div class="d-flex justify-content-between align-items-start mb-3">
                        <div>
                            <strong><?php echo htmlspecialchars($dossier['nom'] . ' ' . substr($dossier['prenom'], 0, 1) . '.'); ?></strong>
                            <br><small class="text-muted">
                                <?php echo htmlspecialchars($dossier['classe_nom']); ?>
                            </small>
                            <br><small class="text-danger">
                                <?php echo $dossier['probleme']; ?>
                            </small>
                        </div>
                        <a href="edit.php?id=<?php echo $dossier['id']; ?>" 
                           class="btn btn-sm btn-outline-warning">
                            <i class="fas fa-edit"></i>
                        </a>
                    </div>
                <?php endforeach; ?>
                <?php if (count($dossiers_attention) > 5): ?>
                    <div class="text-center">
                        <a href="?status=incomplete" class="btn btn-sm btn-outline-warning">
                            Voir tous (<?php echo count($dossiers_attention); ?>)
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Statistiques par niveau -->
        <div class="card">
            <div class="card-header">
                <h6 class="mb-0">
                    <i class="fas fa-chart-pie me-2"></i>
                    Statistiques par niveau
                </h6>
            </div>
            <div class="card-body">
                <?php if (!empty($stats_par_niveau)): ?>
                    <?php foreach ($stats_par_niveau as $stat): ?>
                        <div class="mb-3">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <span class="badge bg-<?php 
                                    echo $stat['niveau'] === 'maternelle' ? 'warning' : 
                                        ($stat['niveau'] === 'primaire' ? 'success' : 'primary'); 
                                ?>">
                                    <?php echo ucfirst($stat['niveau']); ?>
                                </span>
                                <small class="text-muted">
                                    <?php echo $stat['complets']; ?>/<?php echo $stat['total']; ?> complets
                                </small>
                            </div>
                            <div class="progress" style="height: 6px;">
                                <div class="progress-bar bg-success" 
                                     style="width: <?php echo $stat['total'] > 0 ? ($stat['complets'] / $stat['total']) * 100 : 0; ?>%">
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p class="text-muted text-center">Aucune donnée disponible</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
function toggleView(viewType) {
    const tableView = document.getElementById('table-view');
    const cardsView = document.getElementById('cards-view');
    const buttons = document.querySelectorAll('.btn-group .btn');
    
    buttons.forEach(btn => btn.classList.remove('active'));
    
    if (viewType === 'cards') {
        tableView.style.display = 'none';
        cardsView.style.display = 'block';
        document.querySelector('button[onclick="toggleView(\'cards\')"]').classList.add('active');
    } else {
        tableView.style.display = 'block';
        cardsView.style.display = 'none';
        document.querySelector('button[onclick="toggleView(\'table\')"]').classList.add('active');
    }
}
</script>

<?php include '../../../includes/footer.php'; ?>
