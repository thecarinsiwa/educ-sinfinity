<?php
/**
 * Module de gestion académique - Gestion des classes
 * Application de gestion scolaire - République Démocratique du Congo
 */

require_once '../../../config/config.php';
require_once '../../../config/database.php';
require_once '../../../includes/functions.php';

// Vérifier l'authentification et les permissions
requireLogin();
if (!checkPermission('academic') && !checkPermission('academic_view')) {
    showMessage('error', 'Accès refusé à ce module.');
    redirectTo('../index.php');
}

$page_title = 'Gestion des Classes';

// Obtenir l'année scolaire actuelle
$current_year = getCurrentAcademicYear();

// Paramètres de recherche et filtrage
$search = sanitizeInput($_GET['search'] ?? '');
$niveau_filter = sanitizeInput($_GET['niveau'] ?? '');

// Construction de la requête
$sql = "SELECT c.*, 
               COUNT(DISTINCT i.eleve_id) as nb_eleves,
               COUNT(DISTINCT et.enseignant_id) as nb_enseignants,
               p.nom as titulaire_nom, p.prenom as titulaire_prenom
        FROM classes c 
        LEFT JOIN inscriptions i ON c.id = i.classe_id AND i.status = 'inscrit'
        LEFT JOIN emplois_temps et ON c.id = et.classe_id
        LEFT JOIN personnel p ON c.titulaire_id = p.id
        WHERE c.annee_scolaire_id = ?";

$params = [$current_year['id'] ?? 0];

if (!empty($search)) {
    $sql .= " AND (c.nom LIKE ? OR c.description LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
}

if (!empty($niveau_filter)) {
    $sql .= " AND c.niveau = ?";
    $params[] = $niveau_filter;
}

$sql .= " GROUP BY c.id ORDER BY 
          CASE c.niveau 
              WHEN 'maternelle' THEN 1 
              WHEN 'primaire' THEN 2 
              WHEN 'secondaire' THEN 3 
              ELSE 4 
          END, c.nom";

$classes = $database->query($sql, $params)->fetchAll();

// Statistiques
$stats = [
    'total' => count($classes),
    'maternelle' => count(array_filter($classes, fn($c) => $c['niveau'] === 'maternelle')),
    'primaire' => count(array_filter($classes, fn($c) => $c['niveau'] === 'primaire')),
    'secondaire' => count(array_filter($classes, fn($c) => $c['niveau'] === 'secondaire')),
    'total_eleves' => array_sum(array_column($classes, 'nb_eleves'))
];

include '../../../includes/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">
        <i class="fas fa-school me-2"></i>
        Gestion des Classes
    </h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <div class="btn-group me-2">
            <a href="../index.php" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-1"></i>
                Retour
            </a>
        </div>
        <?php if (checkPermission('academic')): ?>
            <div class="btn-group me-2">
                <a href="add.php" class="btn btn-primary">
                    <i class="fas fa-plus me-1"></i>
                    Nouvelle classe
                </a>
            </div>
        <?php endif; ?>
        <div class="btn-group">
            <button type="button" class="btn btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">
                <i class="fas fa-download me-1"></i>
                Exporter
            </button>
            <ul class="dropdown-menu">
                <li><a class="dropdown-item" href="export.php?format=excel">
                    <i class="fas fa-file-excel me-2"></i>Excel
                </a></li>
                <li><a class="dropdown-item" href="export.php?format=pdf">
                    <i class="fas fa-file-pdf me-2"></i>PDF
                </a></li>
            </ul>
        </div>
    </div>
</div>

<!-- Statistiques rapides -->
<div class="row mb-4">
    <div class="col-md-3">
        <div class="card text-white bg-primary">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h4><?php echo $stats['total']; ?></h4>
                        <p class="mb-0">Total classes</p>
                    </div>
                    <div class="align-self-center">
                        <i class="fas fa-school fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-white bg-warning">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h4><?php echo $stats['maternelle']; ?></h4>
                        <p class="mb-0">Maternelle</p>
                    </div>
                    <div class="align-self-center">
                        <i class="fas fa-baby fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-white bg-success">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h4><?php echo $stats['primaire']; ?></h4>
                        <p class="mb-0">Primaire</p>
                    </div>
                    <div class="align-self-center">
                        <i class="fas fa-child fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-white bg-info">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h4><?php echo $stats['secondaire']; ?></h4>
                        <p class="mb-0">Secondaire</p>
                    </div>
                    <div class="align-self-center">
                        <i class="fas fa-user-graduate fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Filtres de recherche -->
<div class="card mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-md-6">
                <label for="search" class="form-label">Rechercher</label>
                <input type="text" 
                       class="form-control" 
                       id="search" 
                       name="search" 
                       placeholder="Nom de classe ou description..."
                       value="<?php echo htmlspecialchars($search); ?>">
            </div>
            <div class="col-md-4">
                <label for="niveau" class="form-label">Niveau</label>
                <select class="form-select" id="niveau" name="niveau">
                    <option value="">Tous les niveaux</option>
                    <option value="maternelle" <?php echo $niveau_filter === 'maternelle' ? 'selected' : ''; ?>>Maternelle</option>
                    <option value="primaire" <?php echo $niveau_filter === 'primaire' ? 'selected' : ''; ?>>Primaire</option>
                    <option value="secondaire" <?php echo $niveau_filter === 'secondaire' ? 'selected' : ''; ?>>Secondaire</option>
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

<!-- Liste des classes -->
<div class="card">
    <div class="card-header">
        <h5 class="mb-0">
            <i class="fas fa-list me-2"></i>
            Liste des classes (<?php echo count($classes); ?>)
        </h5>
    </div>
    <div class="card-body">
        <?php if (!empty($classes)): ?>
            <div class="row">
                <?php foreach ($classes as $classe): ?>
                    <div class="col-lg-4 col-md-6 mb-4">
                        <div class="card h-100 border-0 shadow-sm hover-card">
                            <div class="card-header bg-<?php 
                                echo $classe['niveau'] === 'maternelle' ? 'warning' : 
                                    ($classe['niveau'] === 'primaire' ? 'success' : 'primary'); 
                            ?> text-white">
                                <div class="d-flex justify-content-between align-items-center">
                                    <h6 class="mb-0"><?php echo htmlspecialchars($classe['nom']); ?></h6>
                                    <span class="badge bg-light text-dark">
                                        <?php echo ucfirst($classe['niveau']); ?>
                                    </span>
                                </div>
                            </div>
                            <div class="card-body">
                                <?php if ($classe['description']): ?>
                                    <p class="card-text text-muted small">
                                        <?php echo htmlspecialchars($classe['description']); ?>
                                    </p>
                                <?php endif; ?>
                                
                                <div class="row text-center mb-3">
                                    <div class="col-4">
                                        <div class="border-end">
                                            <h5 class="text-primary mb-0"><?php echo $classe['nb_eleves']; ?></h5>
                                            <small class="text-muted">Élèves</small>
                                        </div>
                                    </div>
                                    <div class="col-4">
                                        <div class="border-end">
                                            <h5 class="text-success mb-0"><?php echo $classe['nb_enseignants']; ?></h5>
                                            <small class="text-muted">Enseignants</small>
                                        </div>
                                    </div>
                                    <div class="col-4">
                                        <h5 class="text-info mb-0"><?php echo $classe['capacite_max'] ?? 'N/A'; ?></h5>
                                        <small class="text-muted">Capacité</small>
                                    </div>
                                </div>
                                
                                <?php if ($classe['titulaire_nom']): ?>
                                    <div class="mb-3">
                                        <small class="text-muted">Titulaire :</small><br>
                                        <strong><?php echo htmlspecialchars($classe['titulaire_nom'] . ' ' . $classe['titulaire_prenom']); ?></strong>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="d-flex justify-content-between align-items-center">
                                    <small class="text-muted">
                                        <i class="fas fa-map-marker-alt me-1"></i>
                                        <?php echo htmlspecialchars($classe['salle'] ?? 'Salle non définie'); ?>
                                    </small>
                                    <div class="btn-group btn-group-sm">
                                        <a href="view.php?id=<?php echo $classe['id']; ?>" 
                                           class="btn btn-outline-info" 
                                           title="Voir détails">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <?php if (checkPermission('academic')): ?>
                                            <a href="edit.php?id=<?php echo $classe['id']; ?>" 
                                               class="btn btn-outline-primary" 
                                               title="Modifier">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <a href="../schedule/class.php?id=<?php echo $classe['id']; ?>" 
                                               class="btn btn-outline-warning" 
                                               title="Emploi du temps">
                                                <i class="fas fa-calendar"></i>
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Barre de progression de la capacité -->
                            <?php if ($classe['capacite_max']): ?>
                                <?php 
                                $pourcentage = min(100, ($classe['nb_eleves'] / $classe['capacite_max']) * 100);
                                $color = $pourcentage > 90 ? 'danger' : ($pourcentage > 75 ? 'warning' : 'success');
                                ?>
                                <div class="card-footer bg-light">
                                    <div class="d-flex justify-content-between align-items-center mb-1">
                                        <small class="text-muted">Occupation</small>
                                        <small class="text-muted"><?php echo round($pourcentage); ?>%</small>
                                    </div>
                                    <div class="progress" style="height: 4px;">
                                        <div class="progress-bar bg-<?php echo $color; ?>" 
                                             style="width: <?php echo $pourcentage; ?>%"></div>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <!-- Pagination si nécessaire -->
            <?php if (count($classes) > 12): ?>
                <nav aria-label="Navigation des classes">
                    <ul class="pagination justify-content-center">
                        <!-- Pagination à implémenter si nécessaire -->
                    </ul>
                </nav>
            <?php endif; ?>
            
        <?php else: ?>
            <div class="text-center py-5">
                <i class="fas fa-school fa-3x text-muted mb-3"></i>
                <h5 class="text-muted">Aucune classe trouvée</h5>
                <p class="text-muted">
                    <?php if (checkPermission('academic')): ?>
                        <a href="add.php" class="btn btn-primary">
                            <i class="fas fa-plus me-1"></i>
                            Créer la première classe
                        </a>
                    <?php else: ?>
                        Aucune classe n'est encore configurée pour cette année scolaire.
                    <?php endif; ?>
                </p>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Résumé par niveau -->
<?php if (!empty($classes)): ?>
<div class="row mt-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-chart-bar me-2"></i>
                    Résumé par niveau
                </h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-borderless">
                        <thead>
                            <tr>
                                <th>Niveau</th>
                                <th class="text-center">Nombre de classes</th>
                                <th class="text-center">Total élèves</th>
                                <th class="text-center">Moyenne par classe</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $niveaux_stats = [];
                            foreach ($classes as $classe) {
                                if (!isset($niveaux_stats[$classe['niveau']])) {
                                    $niveaux_stats[$classe['niveau']] = [
                                        'nb_classes' => 0,
                                        'total_eleves' => 0
                                    ];
                                }
                                $niveaux_stats[$classe['niveau']]['nb_classes']++;
                                $niveaux_stats[$classe['niveau']]['total_eleves'] += $classe['nb_eleves'];
                            }
                            
                            foreach ($niveaux_stats as $niveau => $stats):
                                $moyenne = $stats['nb_classes'] > 0 ? round($stats['total_eleves'] / $stats['nb_classes'], 1) : 0;
                            ?>
                                <tr>
                                    <td>
                                        <span class="badge bg-<?php 
                                            echo $niveau === 'maternelle' ? 'warning' : 
                                                ($niveau === 'primaire' ? 'success' : 'primary'); 
                                        ?>">
                                            <?php echo ucfirst($niveau); ?>
                                        </span>
                                    </td>
                                    <td class="text-center"><?php echo $stats['nb_classes']; ?></td>
                                    <td class="text-center"><?php echo $stats['total_eleves']; ?></td>
                                    <td class="text-center"><?php echo $moyenne; ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<?php include '../../../includes/footer.php'; ?>
