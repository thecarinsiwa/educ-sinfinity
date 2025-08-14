<?php
/**
 * Module Gestion des Élèves - Liste complète des élèves
 * Application de gestion scolaire - République Démocratique du Congo
 */

require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../includes/functions.php';

// Vérifier l'authentification et les permissions
requireLogin();
if (!checkPermission('students') && !checkPermission('students_view')) {
    showMessage('error', 'Accès refusé à ce module.');
    redirectTo('../../dashboard.php');
}

$page_title = 'Liste des Élèves';

// Obtenir l'année scolaire actuelle
$current_year = getCurrentAcademicYear();

// Paramètres de filtrage et pagination
$search = sanitizeInput($_GET['search'] ?? '');
$classe_filter = sanitizeInput($_GET['classe'] ?? '');
$niveau_filter = sanitizeInput($_GET['niveau'] ?? '');
$status_filter = sanitizeInput($_GET['status'] ?? 'inscrit');
$sexe_filter = sanitizeInput($_GET['sexe'] ?? '');
$page = max(1, intval($_GET['page'] ?? 1));
$per_page = 25;
$offset = ($page - 1) * $per_page;

// Construction de la requête avec filtres
$where_conditions = ["i.annee_scolaire_id = ?"];
$params = [$current_year['id'] ?? 0];

if ($search) {
    $where_conditions[] = "(e.nom LIKE ? OR e.prenom LIKE ? OR e.numero_matricule LIKE ?)";
    $search_param = "%$search%";
    $params = array_merge($params, [$search_param, $search_param, $search_param]);
}

if ($classe_filter) {
    $where_conditions[] = "c.id = ?";
    $params[] = $classe_filter;
}

if ($niveau_filter) {
    $where_conditions[] = "c.niveau = ?";
    $params[] = $niveau_filter;
}

if ($status_filter) {
    $where_conditions[] = "i.status = ?";
    $params[] = $status_filter;
}

if ($sexe_filter) {
    $where_conditions[] = "e.sexe = ?";
    $params[] = $sexe_filter;
}

$where_clause = implode(' AND ', $where_conditions);

// Compter le total pour la pagination
try {
    $total_stmt = $database->query(
        "SELECT COUNT(*) as total
         FROM eleves e
         JOIN inscriptions i ON e.id = i.eleve_id
         JOIN classes c ON i.classe_id = c.id
         WHERE $where_clause",
        $params
    );
    $total_records = $total_stmt->fetch()['total'];
} catch (Exception $e) {
    $total_records = 0;
}

$total_pages = ceil($total_records / $per_page);

// Récupérer les élèves
try {
    $eleves = $database->query(
        "SELECT e.*, i.status as inscription_status, i.date_inscription,
                c.nom as classe_nom, c.niveau, c.section,
                TIMESTAMPDIFF(YEAR, e.date_naissance, CURDATE()) as age
         FROM eleves e
         JOIN inscriptions i ON e.id = i.eleve_id
         JOIN classes c ON i.classe_id = c.id
         WHERE $where_clause
         ORDER BY c.niveau, c.nom, e.nom, e.prenom
         LIMIT $per_page OFFSET $offset",
        $params
    )->fetchAll();
} catch (Exception $e) {
    $eleves = [];
}

// Récupérer les classes pour le filtre
try {
    $classes = $database->query(
        "SELECT DISTINCT c.id, c.nom, c.niveau, c.section
         FROM classes c
         JOIN inscriptions i ON c.id = i.classe_id
         WHERE i.annee_scolaire_id = ?
         ORDER BY c.niveau, c.nom",
        [$current_year['id'] ?? 0]
    )->fetchAll();
} catch (Exception $e) {
    $classes = [];
}

// Statistiques rapides
$stats = [];
try {
    $stats_query = $database->query(
        "SELECT 
            COUNT(*) as total,
            COUNT(CASE WHEN e.sexe = 'M' THEN 1 END) as garcons,
            COUNT(CASE WHEN e.sexe = 'F' THEN 1 END) as filles,
            COUNT(CASE WHEN c.niveau = 'maternelle' THEN 1 END) as maternelle,
            COUNT(CASE WHEN c.niveau = 'primaire' THEN 1 END) as primaire,
            COUNT(CASE WHEN c.niveau = 'secondaire' THEN 1 END) as secondaire,
            COUNT(CASE WHEN e.photo IS NOT NULL AND e.photo != '' THEN 1 END) as avec_photos
         FROM eleves e
         JOIN inscriptions i ON e.id = i.eleve_id
         JOIN classes c ON i.classe_id = c.id
         WHERE $where_clause",
        $params
    );
    $stats = $stats_query->fetch();
} catch (Exception $e) {
    $stats = ['total' => 0, 'garcons' => 0, 'filles' => 0, 'maternelle' => 0, 'primaire' => 0, 'secondaire' => 0, 'avec_photos' => 0];
}

include '../../includes/header.php';
?>

<!-- Styles CSS modernes -->
<style>
.students-header {
    background: linear-gradient(135deg, #007bff 0%, #6610f2 100%);
    color: white;
    padding: 2rem 0;
    margin: -20px -15px 30px -15px;
    border-radius: 0 0 20px 20px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.1);
}

.students-header h1 {
    font-weight: 300;
    font-size: 2.5rem;
    margin-bottom: 0.5rem;
}

.students-header .subtitle {
    opacity: 0.9;
    font-size: 1.1rem;
}

.filter-card {
    background: white;
    border-radius: 15px;
    padding: 1.5rem;
    box-shadow: 0 5px 15px rgba(0,0,0,0.08);
    margin-bottom: 2rem;
}

.stats-card {
    background: white;
    border-radius: 15px;
    padding: 1.5rem;
    box-shadow: 0 5px 15px rgba(0,0,0,0.08);
    margin-bottom: 1.5rem;
    transition: transform 0.3s ease;
}

.stats-card:hover {
    transform: translateY(-5px);
}

.student-card {
    background: white;
    border-radius: 10px;
    padding: 1rem;
    box-shadow: 0 3px 10px rgba(0,0,0,0.1);
    margin-bottom: 1rem;
    transition: all 0.3s ease;
    border-left: 4px solid #007bff;
}

.student-card:hover {
    box-shadow: 0 5px 20px rgba(0,0,0,0.15);
    transform: translateY(-2px);
}

.student-avatar {
    width: 50px;
    height: 50px;
    border-radius: 50%;
    background: linear-gradient(135deg, #007bff, #6610f2);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: bold;
    font-size: 1.2rem;
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

.badge-niveau {
    padding: 0.5rem 1rem;
    border-radius: 20px;
    font-weight: 500;
    font-size: 0.8rem;
}

.niveau-maternelle { background: #fff3cd; color: #856404; }
.niveau-primaire { background: #d4edda; color: #155724; }
.niveau-secondaire { background: #d1ecf1; color: #0c5460; }

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
    .students-header {
        margin: -20px -15px 20px -15px;
        padding: 1.5rem 0;
    }

    .students-header h1 {
        font-size: 2rem;
    }

    .filter-card, .stats-card {
        padding: 1rem;
    }
}
</style>

<!-- En-tête moderne -->
<div class="students-header">
    <div class="container-fluid">
        <div class="row align-items-center">
            <div class="col-md-8">
                <h1 class="animate-fade-in">
                    <i class="fas fa-users me-3"></i>
                    Liste des Élèves
                </h1>
                <p class="subtitle animate-fade-in animate-delay-1">
                    <?php echo $total_records; ?> élève(s) trouvé(s) - Année scolaire <?php echo htmlspecialchars($current_year['annee'] ?? 'Non définie'); ?>
                </p>
            </div>
            <div class="col-md-4 text-end">
                <div class="animate-fade-in animate-delay-2">
                    <a href="index.php" class="btn btn-light btn-modern me-2">
                        <i class="fas fa-arrow-left me-2"></i>
                        Retour
                    </a>
                    <?php if (checkPermission('students')): ?>
                        <a href="add.php" class="btn btn-success btn-modern">
                            <i class="fas fa-plus me-2"></i>
                            Nouvel élève
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Statistiques rapides -->
<div class="row mb-4">
    <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
        <div class="stats-card animate-fade-in text-center">
            <div class="h4 text-primary mb-1"><?php echo $stats['total']; ?></div>
            <div class="text-muted small">Total</div>
        </div>
    </div>
    <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
        <div class="stats-card animate-fade-in animate-delay-1 text-center">
            <div class="h4 text-info mb-1"><?php echo $stats['garcons']; ?></div>
            <div class="text-muted small">Garçons</div>
        </div>
    </div>
    <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
        <div class="stats-card animate-fade-in animate-delay-2 text-center">
            <div class="h4 text-danger mb-1"><?php echo $stats['filles']; ?></div>
            <div class="text-muted small">Filles</div>
        </div>
    </div>
    <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
        <div class="stats-card animate-fade-in animate-delay-3 text-center">
            <div class="h4 text-warning mb-1"><?php echo $stats['maternelle']; ?></div>
            <div class="text-muted small">Maternelle</div>
        </div>
    </div>
    <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
        <div class="stats-card animate-fade-in text-center">
            <div class="h4 text-success mb-1"><?php echo $stats['primaire']; ?></div>
            <div class="text-muted small">Primaire</div>
        </div>
    </div>
    <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
        <div class="stats-card animate-fade-in animate-delay-1 text-center">
            <div class="h4 text-secondary mb-1"><?php echo $stats['secondaire']; ?></div>
            <div class="text-muted small">Secondaire</div>
        </div>
    </div>
</div>

<!-- Statistiques des photos -->
<div class="row mb-4">
    <div class="col-md-6">
        <div class="stats-card animate-fade-in animate-delay-2">
            <div class="d-flex align-items-center">
                <div class="me-3">
                    <i class="fas fa-camera fa-2x text-primary"></i>
                </div>
                <div>
                    <div class="h5 mb-1"><?php echo $stats['avec_photos']; ?> / <?php echo $stats['total']; ?></div>
                    <div class="text-muted small">Élèves avec photo</div>
                    <?php if ($stats['total'] > 0): ?>
                        <div class="progress mt-2" style="height: 6px;">
                            <div class="progress-bar bg-primary" role="progressbar" 
                                 style="width: <?php echo round(($stats['avec_photos'] / $stats['total']) * 100); ?>%"></div>
                        </div>
                        <small class="text-muted"><?php echo round(($stats['avec_photos'] / $stats['total']) * 100); ?>% de complétude</small>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="stats-card animate-fade-in animate-delay-3">
            <div class="d-flex align-items-center">
                <div class="me-3">
                    <i class="fas fa-user-plus fa-2x text-success"></i>
                </div>
                <div>
                    <div class="h5 mb-1"><?php echo $stats['total'] - $stats['avec_photos']; ?></div>
                    <div class="text-muted small">Élèves sans photo</div>
                    <small class="text-warning">
                        <i class="fas fa-exclamation-triangle me-1"></i>
                        Photos à ajouter
                    </small>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Filtres de recherche -->
<div class="filter-card animate-fade-in animate-delay-2">
    <form method="GET" class="row g-3">
        <div class="col-md-3">
            <label for="search" class="form-label">
                <i class="fas fa-search me-1"></i>
                Rechercher
            </label>
            <input type="text" class="form-control" id="search" name="search"
                   value="<?php echo htmlspecialchars($search); ?>"
                   placeholder="Nom, prénom ou matricule...">
        </div>
        <div class="col-md-2">
            <label for="classe" class="form-label">
                <i class="fas fa-chalkboard me-1"></i>
                Classe
            </label>
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
            <label for="niveau" class="form-label">
                <i class="fas fa-layer-group me-1"></i>
                Niveau
            </label>
            <select class="form-select" id="niveau" name="niveau">
                <option value="">Tous les niveaux</option>
                <option value="maternelle" <?php echo $niveau_filter === 'maternelle' ? 'selected' : ''; ?>>Maternelle</option>
                <option value="primaire" <?php echo $niveau_filter === 'primaire' ? 'selected' : ''; ?>>Primaire</option>
                <option value="secondaire" <?php echo $niveau_filter === 'secondaire' ? 'selected' : ''; ?>>Secondaire</option>
            </select>
        </div>
        <div class="col-md-2">
            <label for="sexe" class="form-label">
                <i class="fas fa-venus-mars me-1"></i>
                Sexe
            </label>
            <select class="form-select" id="sexe" name="sexe">
                <option value="">Tous</option>
                <option value="M" <?php echo $sexe_filter === 'M' ? 'selected' : ''; ?>>Masculin</option>
                <option value="F" <?php echo $sexe_filter === 'F' ? 'selected' : ''; ?>>Féminin</option>
            </select>
        </div>
        <div class="col-md-2">
            <label for="status" class="form-label">
                <i class="fas fa-user-check me-1"></i>
                Statut
            </label>
            <select class="form-select" id="status" name="status">
                <option value="inscrit" <?php echo $status_filter === 'inscrit' ? 'selected' : ''; ?>>Inscrit</option>
                <option value="suspendu" <?php echo $status_filter === 'suspendu' ? 'selected' : ''; ?>>Suspendu</option>
                <option value="transfere" <?php echo $status_filter === 'transfere' ? 'selected' : ''; ?>>Transféré</option>
                <option value="abandonne" <?php echo $status_filter === 'abandonne' ? 'selected' : ''; ?>>Abandonné</option>
            </select>
        </div>
        <div class="col-md-1">
            <label class="form-label">&nbsp;</label>
            <div class="d-grid">
                <button type="submit" class="btn btn-primary btn-modern">
                    <i class="fas fa-search"></i>
                </button>
            </div>
        </div>
    </form>

    <?php if ($search || $classe_filter || $niveau_filter || $sexe_filter || $status_filter !== 'inscrit'): ?>
        <div class="mt-3">
            <a href="list.php" class="btn btn-outline-secondary btn-sm">
                <i class="fas fa-times me-1"></i>
                Effacer les filtres
            </a>
        </div>
    <?php endif; ?>
</div>

<!-- Actions rapides -->
<?php if (checkPermission('students')): ?>
<div class="row mb-4">
    <div class="col-12">
        <div class="filter-card animate-fade-in animate-delay-3">
            <h6 class="mb-3">
                <i class="fas fa-bolt me-2"></i>
                Actions rapides
            </h6>
            <div class="row">
                <div class="col-md-2 mb-2">
                    <div class="d-grid">
                        <a href="add.php" class="btn btn-success btn-modern btn-sm">
                            <i class="fas fa-plus me-2"></i>
                            Nouvel élève
                        </a>
                    </div>
                </div>
                <div class="col-md-2 mb-2">
                    <div class="d-grid">
                        <a href="search.php" class="btn btn-info btn-modern btn-sm">
                            <i class="fas fa-search-plus me-2"></i>
                            Recherche avancée
                        </a>
                    </div>
                </div>
                <div class="col-md-2 mb-2">
                    <div class="d-grid">
                        <a href="reports.php" class="btn btn-warning btn-modern btn-sm">
                            <i class="fas fa-chart-bar me-2"></i>
                            Rapports
                        </a>
                    </div>
                </div>
                <div class="col-md-2 mb-2">
                    <div class="d-grid">
                        <button class="btn btn-secondary btn-modern btn-sm" onclick="exportData()">
                            <i class="fas fa-file-export me-2"></i>
                            Exporter
                        </button>
                    </div>
                </div>
                <div class="col-md-2 mb-2">
                    <div class="d-grid">
                        <button class="btn btn-primary btn-modern btn-sm" onclick="printList()">
                            <i class="fas fa-print me-2"></i>
                            Imprimer
                        </button>
                    </div>
                </div>
                <div class="col-md-2 mb-2">
                    <div class="d-grid">
                        <a href="admissions/" class="btn btn-outline-primary btn-modern btn-sm">
                            <i class="fas fa-user-plus me-2"></i>
                            Admissions
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Liste des élèves -->
<div class="row">
    <div class="col-12">
        <div class="filter-card animate-fade-in">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="mb-0">
                    <i class="fas fa-list me-2"></i>
                    Liste des élèves
                    <span class="badge bg-secondary"><?php echo $total_records; ?></span>
                </h5>
                <div class="btn-group btn-group-sm">
                    <button class="btn btn-outline-secondary" onclick="toggleView('cards')" id="btn-cards">
                        <i class="fas fa-th-large"></i>
                    </button>
                    <button class="btn btn-outline-secondary active" onclick="toggleView('table')" id="btn-table">
                        <i class="fas fa-table"></i>
                    </button>
                </div>
            </div>

            <?php if (!empty($eleves)): ?>
                <!-- Vue en cartes -->
                <div id="cards-view" style="display: none;">
                    <div class="row">
                        <?php foreach ($eleves as $eleve): ?>
                            <div class="col-lg-4 col-md-6 mb-3">
                                <div class="student-card">
                                    <div class="d-flex align-items-center">
                                        <?php if ($eleve['photo']): ?>
                                            <img src="<?php echo '../../' . htmlspecialchars($eleve['photo']); ?>" 
                                                 alt="Photo de <?php echo htmlspecialchars($eleve['nom']); ?>" 
                                                 class="rounded-circle me-3" width="50" height="50" style="object-fit: cover;">
                                        <?php else: ?>
                                            <div class="student-avatar me-3">
                                                <?php echo strtoupper(substr($eleve['nom'], 0, 1) . substr($eleve['prenom'], 0, 1)); ?>
                                            </div>
                                        <?php endif; ?>
                                        <div class="flex-grow-1">
                                            <h6 class="mb-1">
                                                <?php echo htmlspecialchars($eleve['nom'] . ' ' . $eleve['prenom']); ?>
                                            </h6>
                                            <div class="small text-muted mb-1">
                                                <i class="fas fa-id-card me-1"></i>
                                                <?php echo htmlspecialchars($eleve['numero_matricule']); ?>
                                            </div>
                                            <div class="small">
                                                <span class="badge badge-niveau niveau-<?php echo $eleve['niveau']; ?>">
                                                    <?php echo htmlspecialchars($eleve['classe_nom']); ?>
                                                </span>
                                                <span class="badge bg-<?php echo $eleve['sexe'] === 'M' ? 'info' : 'danger'; ?> ms-1">
                                                    <?php echo $eleve['sexe'] === 'M' ? 'M' : 'F'; ?>
                                                </span>
                                                <span class="badge bg-secondary ms-1">
                                                    <?php echo $eleve['age']; ?> ans
                                                </span>
                                            </div>
                                        </div>
                                        <div class="dropdown">
                                            <button class="btn btn-sm btn-outline-secondary dropdown-toggle"
                                                    data-bs-toggle="dropdown">
                                                <i class="fas fa-ellipsis-v"></i>
                                            </button>
                                            <ul class="dropdown-menu">
                                                <li><a class="dropdown-item" href="records/view.php?id=<?php echo $eleve['id']; ?>">
                                                    <i class="fas fa-eye me-2"></i>Voir le profil
                                                </a></li>
                                                <?php if (checkPermission('students')): ?>
                                                    <li><a class="dropdown-item" href="records/edit.php?id=<?php echo $eleve['id']; ?>">
                                                        <i class="fas fa-edit me-2"></i>Modifier
                                                    </a></li>
                                                    <li><hr class="dropdown-divider"></li>
                                                    <li><a class="dropdown-item" href="attendance/index.php?eleve_id=<?php echo $eleve['id']; ?>">
                                                        <i class="fas fa-calendar-check me-2"></i>Présences
                                                    </a></li>
                                                <?php endif; ?>
                                            </ul>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Vue en tableau -->
                <div id="table-view">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead class="table-light">
                                <tr>
                                    <th>Photo</th>
                                    <th>Élève</th>
                                    <th>Matricule</th>
                                    <th>Classe</th>
                                    <th>Âge</th>
                                    <th>Sexe</th>
                                    <th>Statut</th>
                                    <th>Date inscription</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($eleves as $eleve): ?>
                                    <tr>
                                        <td>
                                            <?php if ($eleve['photo']): ?>
                                                <img src="<?php echo '../../' . htmlspecialchars($eleve['photo']); ?>" 
                                                     alt="Photo de <?php echo htmlspecialchars($eleve['nom']); ?>" 
                                                     class="rounded-circle" width="40" height="40" style="object-fit: cover;">
                                            <?php else: ?>
                                                <div class="student-avatar" style="width: 40px; height: 40px; font-size: 1rem;">
                                                    <?php echo strtoupper(substr($eleve['nom'], 0, 1) . substr($eleve['prenom'], 0, 1)); ?>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($eleve['nom'] . ' ' . $eleve['prenom']); ?></strong>
                                        </td>
                                        <td>
                                            <code><?php echo htmlspecialchars($eleve['numero_matricule']); ?></code>
                                        </td>
                                        <td>
                                            <span class="badge badge-niveau niveau-<?php echo $eleve['niveau']; ?>">
                                                <?php echo htmlspecialchars($eleve['classe_nom']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo $eleve['age']; ?> ans</td>
                                        <td>
                                            <span class="badge bg-<?php echo $eleve['sexe'] === 'M' ? 'info' : 'danger'; ?>">
                                                <?php echo $eleve['sexe'] === 'M' ? 'Masculin' : 'Féminin'; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?php
                                                echo $eleve['inscription_status'] === 'inscrit' ? 'success' :
                                                    ($eleve['inscription_status'] === 'suspendu' ? 'warning' : 'secondary');
                                            ?>">
                                                <?php echo ucfirst($eleve['inscription_status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php echo $eleve['date_inscription'] ? date('d/m/Y', strtotime($eleve['date_inscription'])) : 'N/A'; ?>
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <a href="records/view.php?id=<?php echo $eleve['id']; ?>"
                                                   class="btn btn-outline-info" title="Voir le profil">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                <?php if (checkPermission('students')): ?>
                                                    <a href="records/edit.php?id=<?php echo $eleve['id']; ?>"
                                                       class="btn btn-outline-primary" title="Modifier">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                    <a href="attendance/index.php?eleve_id=<?php echo $eleve['id']; ?>"
                                                       class="btn btn-outline-secondary" title="Présences">
                                                        <i class="fas fa-calendar-check"></i>
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

                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                    <nav aria-label="Navigation des élèves" class="mt-4">
                        <ul class="pagination justify-content-center">
                            <?php if ($page > 1): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&classe=<?php echo urlencode($classe_filter); ?>&niveau=<?php echo urlencode($niveau_filter); ?>&status=<?php echo urlencode($status_filter); ?>&sexe=<?php echo urlencode($sexe_filter); ?>">
                                        <i class="fas fa-chevron-left"></i>
                                    </a>
                                </li>
                            <?php endif; ?>

                            <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                                <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&classe=<?php echo urlencode($classe_filter); ?>&niveau=<?php echo urlencode($niveau_filter); ?>&status=<?php echo urlencode($status_filter); ?>&sexe=<?php echo urlencode($sexe_filter); ?>">
                                        <?php echo $i; ?>
                                    </a>
                                </li>
                            <?php endfor; ?>

                            <?php if ($page < $total_pages): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&classe=<?php echo urlencode($classe_filter); ?>&niveau=<?php echo urlencode($niveau_filter); ?>&status=<?php echo urlencode($status_filter); ?>&sexe=<?php echo urlencode($sexe_filter); ?>">
                                        <i class="fas fa-chevron-right"></i>
                                    </a>
                                </li>
                            <?php endif; ?>
                        </ul>
                    </nav>
                <?php endif; ?>

            <?php else: ?>
                <div class="text-center py-5">
                    <i class="fas fa-users fa-4x text-muted mb-3"></i>
                    <h4 class="text-muted">Aucun élève trouvé</h4>
                    <p class="text-muted">
                        <?php if ($search || $classe_filter || $niveau_filter || $sexe_filter || $status_filter !== 'inscrit'): ?>
                            Aucun élève ne correspond aux critères de recherche.
                        <?php else: ?>
                            Aucun élève n'est inscrit pour cette année scolaire.
                        <?php endif; ?>
                    </p>
                    <?php if ($search || $classe_filter || $niveau_filter || $sexe_filter || $status_filter !== 'inscrit'): ?>
                        <a href="list.php" class="btn btn-outline-secondary btn-modern">
                            <i class="fas fa-times me-1"></i>
                            Effacer les filtres
                        </a>
                    <?php endif; ?>
                    <?php if (checkPermission('students')): ?>
                        <a href="add.php" class="btn btn-primary btn-modern ms-2">
                            <i class="fas fa-plus me-1"></i>
                            Ajouter un élève
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
// Fonctions JavaScript
function toggleView(view) {
    const cardsView = document.getElementById('cards-view');
    const tableView = document.getElementById('table-view');
    const btnCards = document.getElementById('btn-cards');
    const btnTable = document.getElementById('btn-table');

    if (view === 'cards') {
        cardsView.style.display = 'block';
        tableView.style.display = 'none';
        btnCards.classList.add('active');
        btnTable.classList.remove('active');
        localStorage.setItem('studentsView', 'cards');
    } else {
        cardsView.style.display = 'none';
        tableView.style.display = 'block';
        btnCards.classList.remove('active');
        btnTable.classList.add('active');
        localStorage.setItem('studentsView', 'table');
    }
}

function exportData() {
    const params = new URLSearchParams(window.location.search);
    params.set('export', 'excel');
    window.location.href = 'list.php?' + params.toString();
}

function printList() {
    window.print();
}

// Restaurer la vue préférée
document.addEventListener('DOMContentLoaded', function() {
    const savedView = localStorage.getItem('studentsView') || 'table';
    toggleView(savedView);

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

    // Observer toutes les cartes d'élèves
    document.querySelectorAll('.student-card').forEach(card => {
        card.style.opacity = '0';
        card.style.transform = 'translateY(20px)';
        card.style.transition = 'opacity 0.6s ease, transform 0.6s ease';
        observer.observe(card);
    });
});
</script>

<?php include '../../includes/footer.php'; ?>
