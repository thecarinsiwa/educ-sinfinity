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

$page_title = 'Gestion des Élèves';

// Obtenir l'année scolaire actuelle
$current_year = getCurrentAcademicYear();

// Statistiques des élèves
$stats = [];

// Total des élèves inscrits
try {
    $stmt = $database->query(
        "SELECT COUNT(*) as total FROM inscriptions WHERE status = 'inscrit' AND annee_scolaire_id = ?",
        [$current_year['id'] ?? 0]
    );
    $stats['total_inscrits'] = $stmt->fetch()['total'];
} catch (Exception $e) {
    $stats['total_inscrits'] = 0;
}

// Élèves par sexe
try {
    $stmt = $database->query(
        "SELECT e.sexe, COUNT(*) as total 
         FROM eleves e 
         JOIN inscriptions i ON e.id = i.eleve_id 
         WHERE i.status = 'inscrit' AND i.annee_scolaire_id = ? 
         GROUP BY e.sexe",
        [$current_year['id'] ?? 0]
    );
    $sexe_stats = $stmt->fetchAll();
    $stats['garcons'] = 0;
    $stats['filles'] = 0;
    foreach ($sexe_stats as $stat) {
        if ($stat['sexe'] === 'M') {
            $stats['garcons'] = $stat['total'];
        } else {
            $stats['filles'] = $stat['total'];
        }
    }
} catch (Exception $e) {
    $stats['garcons'] = 0;
    $stats['filles'] = 0;
}

// Élèves par niveau
try {
    $stmt = $database->query(
        "SELECT c.niveau, COUNT(*) as total 
         FROM inscriptions i 
         JOIN classes c ON i.classe_id = c.id 
         WHERE i.status = 'inscrit' AND i.annee_scolaire_id = ? 
         GROUP BY c.niveau",
        [$current_year['id'] ?? 0]
    );
    $niveau_stats = $stmt->fetchAll();
    $stats['maternelle'] = 0;
    $stats['primaire'] = 0;
    $stats['secondaire'] = 0;
    foreach ($niveau_stats as $stat) {
        $stats[$stat['niveau']] = $stat['total'];
    }
} catch (Exception $e) {
    $stats['maternelle'] = 0;
    $stats['primaire'] = 0;
    $stats['secondaire'] = 0;
}

// Nouveaux élèves ce mois
try {
    $stmt = $database->query(
        "SELECT COUNT(*) as total 
         FROM inscriptions 
         WHERE status = 'inscrit' 
         AND annee_scolaire_id = ? 
         AND MONTH(date_inscription) = MONTH(CURDATE()) 
         AND YEAR(date_inscription) = YEAR(CURDATE())",
        [$current_year['id'] ?? 0]
    );
    $stats['nouveaux_mois'] = $stmt->fetch()['total'];
} catch (Exception $e) {
    $stats['nouveaux_mois'] = 0;
}

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
$classes = getClasses($current_year['id'] ?? null);

include '../../includes/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">
        <i class="fas fa-users me-2"></i>
        Gestion des Élèves
    </h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <div class="btn-group me-2">
            <a href="./index.php" class="btn btn-outline-secondary">
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
                    <li><a class="dropdown-item" href="add.php">
                        <i class="fas fa-user-plus me-2"></i>Ajouter un élève
                    </a></li>
                    <li><a class="dropdown-item" href="admissions/bulk-import.php">
                        <i class="fas fa-file-import me-2"></i>Import en masse
                    </a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item" href="admissions/direct-enrollment.php">
                        <i class="fas fa-user-check me-2"></i>Inscription directe
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
                <li><a class="dropdown-item" href="reports.php">
                    <i class="fas fa-chart-bar me-2"></i>Rapports
                </a></li>
                <li><a class="dropdown-item" href="search.php">
                    <i class="fas fa-search me-2"></i>Recherche avancée
                </a></li>
                <li><a class="dropdown-item" href="transfers/">
                    <i class="fas fa-exchange-alt me-2"></i>Transferts
                </a></li>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item" href="enrollment.php">
                    <i class="fas fa-user-plus me-2"></i>Inscriptions Nouvelle Année
                </a></li>
                <li><a class="dropdown-item" href="enrollment-history.php">
                    <i class="fas fa-history me-2"></i>Historique des Inscriptions
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
                        <h4><?php echo $stats['total_inscrits']; ?></h4>
                        <p class="mb-0">Total inscrits</p>
                    </div>
                    <div class="align-self-center">
                        <i class="fas fa-users fa-2x"></i>
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
                        <h4><?php echo $stats['garcons']; ?></h4>
                        <p class="mb-0">Garçons</p>
                    </div>
                    <div class="align-self-center">
                        <i class="fas fa-male fa-2x"></i>
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
                        <h4><?php echo $stats['filles']; ?></h4>
                        <p class="mb-0">Filles</p>
                    </div>
                    <div class="align-self-center">
                        <i class="fas fa-female fa-2x"></i>
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
                        <h4><?php echo $stats['nouveaux_mois']; ?></h4>
                        <p class="mb-0">Nouveaux ce mois</p>
                    </div>
                    <div class="align-self-center">
                        <i class="fas fa-user-plus fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>



<!-- Contenu principal -->
<div class="row">
    <div class="col-lg-8">
        <!-- Liste des élèves -->
        <div class="card">
            <div class="card-header">
                <div class="d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        <i class="fas fa-list me-2"></i>
                        Liste des élèves
                    </h5>
                    <div class="btn-group btn-group-sm">
                        <button type="button" class="btn btn-outline-secondary" id="card-view-btn" title="Vue en cartes">
                            <i class="fas fa-th-large"></i>
                        </button>
                        <button type="button" class="btn btn-outline-secondary active" id="table-view-btn" title="Vue en tableau">
                            <i class="fas fa-table"></i>
                        </button>
                    </div>
                </div>
            </div>
            <div class="card-body">
                <!-- Filtres -->
                <form method="GET" class="row g-3 mb-4">
                    <div class="col-md-3">
                        <input type="text" class="form-control" name="search" 
                               placeholder="Rechercher..." 
                               value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    <div class="col-md-2">
                        <select class="form-select" name="niveau">
                            <option value="">Tous niveaux</option>
                            <option value="maternelle" <?php echo $niveau_filter === 'maternelle' ? 'selected' : ''; ?>>Maternelle</option>
                            <option value="primaire" <?php echo $niveau_filter === 'primaire' ? 'selected' : ''; ?>>Primaire</option>
                            <option value="secondaire" <?php echo $niveau_filter === 'secondaire' ? 'selected' : ''; ?>>Secondaire</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <select class="form-select" name="classe">
                            <option value="">Toutes classes</option>
                            <?php foreach ($classes as $classe): ?>
                                <option value="<?php echo $classe['id']; ?>" 
                                        <?php echo $classe_filter == $classe['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($classe['nom']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <select class="form-select" name="sexe">
                            <option value="">Tous sexes</option>
                            <option value="M" <?php echo $sexe_filter === 'M' ? 'selected' : ''; ?>>Masculin</option>
                            <option value="F" <?php echo $sexe_filter === 'F' ? 'selected' : ''; ?>>Féminin</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <button type="submit" class="btn btn-primary me-2">
                            <i class="fas fa-search me-1"></i>
                            Filtrer
                        </button>
                        <a href="list.php" class="btn btn-outline-secondary">
                            <i class="fas fa-times me-1"></i>
                            Réinitialiser
                        </a>
                    </div>
                </form>

                <!-- Vue en cartes -->
                <div id="card-view" style="display: none;">
                    <div class="row">
                        <?php foreach ($eleves as $eleve): ?>
                            <div class="col-lg-4 col-md-6 mb-3">
                                <div class="card h-100 border-0 shadow-sm hover-card">
                                    <div class="card-body">
                                        <div class="d-flex align-items-center">
                                            <?php if ($eleve['photo']): ?>
                                                <img src="../../uploads/photos/<?php echo htmlspecialchars($eleve['photo']); ?>" 
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
                                                    <li><a class="dropdown-item" href="view.php?id=<?php echo $eleve['id']; ?>">
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
                                                <img src="../../uploads/photos/<?php echo htmlspecialchars($eleve['photo']); ?>" 
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
                                        <td>
                                            <span class="badge bg-secondary"><?php echo $eleve['age']; ?> ans</span>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?php echo $eleve['sexe'] === 'M' ? 'info' : 'danger'; ?>">
                                                <?php echo $eleve['sexe'] === 'M' ? 'M' : 'F'; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php
                                            $status_colors = [
                                                'inscrit' => 'success',
                                                'transfere' => 'info',
                                                'abandonne' => 'warning',
                                                'diplome' => 'primary'
                                            ];
                                            $color = $status_colors[$eleve['inscription_status']] ?? 'secondary';
                                            ?>
                                            <span class="badge bg-<?php echo $color; ?>">
                                                <?php echo ucfirst($eleve['inscription_status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <small><?php echo formatDate($eleve['date_inscription']); ?></small>
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <a href="view.php?id=<?php echo $eleve['id']; ?>" 
                                                   class="btn btn-outline-info" title="Voir">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                <?php if (checkPermission('students')): ?>
                                                    <a href="records/edit.php?id=<?php echo $eleve['id']; ?>" 
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
                </div>

                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                    <nav aria-label="Pagination des élèves">
                        <ul class="pagination justify-content-center">
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
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <div class="col-lg-4">
        <!-- Répartition par niveau -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-chart-pie me-2"></i>
                    Répartition par niveau
                </h5>
            </div>
            <div class="card-body">
                <div class="list-group list-group-flush">
                    <div class="list-group-item d-flex justify-content-between align-items-center">
                        <div>
                            <i class="fas fa-baby text-warning me-2"></i>
                            Maternelle
                        </div>
                        <span class="badge bg-warning rounded-pill"><?php echo $stats['maternelle']; ?></span>
                    </div>
                    <div class="list-group-item d-flex justify-content-between align-items-center">
                        <div>
                            <i class="fas fa-child text-success me-2"></i>
                            Primaire
                        </div>
                        <span class="badge bg-success rounded-pill"><?php echo $stats['primaire']; ?></span>
                    </div>
                    <div class="list-group-item d-flex justify-content-between align-items-center">
                        <div>
                            <i class="fas fa-graduation-cap text-primary me-2"></i>
                            Secondaire
                        </div>
                        <span class="badge bg-primary rounded-pill"><?php echo $stats['secondaire']; ?></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Actions rapides -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-bolt me-2"></i>
                    Actions rapides
                </h5>
            </div>
            <div class="card-body">
                <div class="d-grid gap-2">
                    <?php if (checkPermission('students')): ?>
                        <a href="add.php" class="btn btn-primary">
                            <i class="fas fa-user-plus me-2"></i>
                            Ajouter un élève
                        </a>
                        <a href="attendance/" class="btn btn-success">
                            <i class="fas fa-calendar-check me-2"></i>
                            Gérer les présences
                        </a>
                        <a href="../evaluations/notes/" class="btn btn-warning">
                            <i class="fas fa-chart-line me-2"></i>
                            Saisir les notes
                        </a>
                        <a href="transfers/" class="btn btn-info">
                            <i class="fas fa-exchange-alt me-2"></i>
                            Gérer les transferts
                        </a>
                    <?php endif; ?>
                    <a href="reports.php" class="btn btn-outline-secondary">
                        <i class="fas fa-chart-bar me-2"></i>
                        Voir les rapports
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.hover-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.1) !important;
    transition: all 0.3s ease;
}

.student-avatar {
    width: 50px;
    height: 50px;
    background: linear-gradient(45deg, #007bff, #0056b3);
    color: white;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
    font-size: 1.2rem;
}

.badge-niveau {
    font-size: 0.75rem;
}

.niveau-maternelle {
    background-color: #ffc107;
    color: #212529;
}

.niveau-primaire {
    background-color: #28a745;
    color: white;
}

.niveau-secondaire {
    background-color: #007bff;
    color: white;
}

#card-view .card {
    transition: all 0.3s ease;
}

#card-view .card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.1) !important;
}
</style>

<script>
// Gestion des vues (cartes/tableau)
document.getElementById('card-view-btn').addEventListener('click', function() {
    document.getElementById('card-view').style.display = 'block';
    document.getElementById('table-view').style.display = 'none';
    this.classList.add('active');
    document.getElementById('table-view-btn').classList.remove('active');
});

document.getElementById('table-view-btn').addEventListener('click', function() {
    document.getElementById('card-view').style.display = 'none';
    document.getElementById('table-view').style.display = 'block';
    this.classList.add('active');
    document.getElementById('card-view-btn').classList.remove('active');
});

// Auto-submit du formulaire de filtres
document.querySelectorAll('select[name="niveau"], select[name="classe"], select[name="sexe"]').forEach(function(select) {
    select.addEventListener('change', function() {
        this.closest('form').submit();
    });
});
</script>

<?php include '../../includes/footer.php'; ?>
