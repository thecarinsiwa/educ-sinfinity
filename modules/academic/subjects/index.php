<?php
/**
 * Module de gestion académique - Gestion des matières
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

$page_title = 'Gestion des Matières';

// Paramètres de recherche et filtrage
$search = sanitizeInput($_GET['search'] ?? '');
$niveau_filter = sanitizeInput($_GET['niveau'] ?? '');
$type_filter = sanitizeInput($_GET['type'] ?? '');

// Construction de la requête
$sql = "SELECT m.*, 
               COUNT(DISTINCT et.classe_id) as nb_classes,
               COUNT(DISTINCT et.enseignant_id) as nb_enseignants
        FROM matieres m 
        LEFT JOIN emplois_temps et ON m.id = et.matiere_id
        WHERE 1=1";

$params = [];

if (!empty($search)) {
    $sql .= " AND (m.nom LIKE ? OR m.description LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
}

if (!empty($niveau_filter)) {
    $sql .= " AND m.niveau = ?";
    $params[] = $niveau_filter;
}

if (!empty($type_filter)) {
    $sql .= " AND m.type = ?";
    $params[] = $type_filter;
}

$sql .= " GROUP BY m.id ORDER BY m.niveau, m.nom";

$matieres = $database->query($sql, $params)->fetchAll();

// Statistiques
$stats = [
    'total' => count($matieres),
    'maternelle' => count(array_filter($matieres, fn($m) => $m['niveau'] === 'maternelle')),
    'primaire' => count(array_filter($matieres, fn($m) => $m['niveau'] === 'primaire')),
    'secondaire' => count(array_filter($matieres, fn($m) => $m['niveau'] === 'secondaire')),
    'obligatoires' => count(array_filter($matieres, fn($m) => $m['type'] === 'obligatoire')),
    'optionnelles' => count(array_filter($matieres, fn($m) => $m['type'] === 'optionnelle'))
];

include '../../../includes/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">
        <i class="fas fa-book-open me-2"></i>
        Gestion des Matières
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
                    Nouvelle matière
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
                        <p class="mb-0">Total matières</p>
                    </div>
                    <div class="align-self-center">
                        <i class="fas fa-book-open fa-2x"></i>
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
                        <h4><?php echo $stats['obligatoires']; ?></h4>
                        <p class="mb-0">Obligatoires</p>
                    </div>
                    <div class="align-self-center">
                        <i class="fas fa-star fa-2x"></i>
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
                        <h4><?php echo $stats['optionnelles']; ?></h4>
                        <p class="mb-0">Optionnelles</p>
                    </div>
                    <div class="align-self-center">
                        <i class="fas fa-star-half-alt fa-2x"></i>
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
                        <i class="fas fa-graduation-cap fa-2x"></i>
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
            <div class="col-md-4">
                <label for="search" class="form-label">Rechercher</label>
                <input type="text" 
                       class="form-control" 
                       id="search" 
                       name="search" 
                       placeholder="Nom de matière ou description..."
                       value="<?php echo htmlspecialchars($search); ?>">
            </div>
            <div class="col-md-3">
                <label for="niveau" class="form-label">Niveau</label>
                <select class="form-select" id="niveau" name="niveau">
                    <option value="">Tous les niveaux</option>
                    <option value="maternelle" <?php echo $niveau_filter === 'maternelle' ? 'selected' : ''; ?>>Maternelle</option>
                    <option value="primaire" <?php echo $niveau_filter === 'primaire' ? 'selected' : ''; ?>>Primaire</option>
                    <option value="secondaire" <?php echo $niveau_filter === 'secondaire' ? 'selected' : ''; ?>>Secondaire</option>
                    <option value="general" <?php echo $niveau_filter === 'general' ? 'selected' : ''; ?>>Général</option>
                </select>
            </div>
            <div class="col-md-3">
                <label for="type" class="form-label">Type</label>
                <select class="form-select" id="type" name="type">
                    <option value="">Tous les types</option>
                    <option value="obligatoire" <?php echo $type_filter === 'obligatoire' ? 'selected' : ''; ?>>Obligatoire</option>
                    <option value="optionnelle" <?php echo $type_filter === 'optionnelle' ? 'selected' : ''; ?>>Optionnelle</option>
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

<!-- Liste des matières -->
<div class="card">
    <div class="card-header">
        <h5 class="mb-0">
            <i class="fas fa-list me-2"></i>
            Liste des matières (<?php echo count($matieres); ?>)
        </h5>
    </div>
    <div class="card-body">
        <?php if (!empty($matieres)): ?>
            <div class="table-responsive">
                <table class="table table-striped table-hover datatable">
                    <thead>
                        <tr>
                            <th>Matière</th>
                            <th>Niveau</th>
                            <th>Type</th>
                            <th>Coefficient</th>
                            <th>Volume horaire</th>
                            <th>Classes</th>
                            <th>Enseignants</th>
                            <th class="no-sort">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($matieres as $matiere): ?>
                            <tr>
                                <td>
                                    <div>
                                        <strong><?php echo htmlspecialchars($matiere['nom']); ?></strong>
                                        <?php if ($matiere['description']): ?>
                                            <br><small class="text-muted">
                                                <?php echo htmlspecialchars(substr($matiere['description'], 0, 50)); ?>
                                                <?php echo strlen($matiere['description']) > 50 ? '...' : ''; ?>
                                            </small>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <?php
                                    $niveau_colors = [
                                        'maternelle' => 'warning',
                                        'primaire' => 'success',
                                        'secondaire' => 'primary',
                                        'general' => 'info'
                                    ];
                                    $color = $niveau_colors[$matiere['niveau']] ?? 'secondary';
                                    ?>
                                    <span class="badge bg-<?php echo $color; ?>">
                                        <?php echo ucfirst($matiere['niveau']); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge bg-<?php echo $matiere['type'] === 'obligatoire' ? 'success' : 'warning'; ?>">
                                        <i class="fas fa-<?php echo $matiere['type'] === 'obligatoire' ? 'star' : 'star-half-alt'; ?> me-1"></i>
                                        <?php echo ucfirst($matiere['type']); ?>
                                    </span>
                                </td>
                                <td class="text-center">
                                    <?php if ($matiere['coefficient']): ?>
                                        <span class="badge bg-info"><?php echo $matiere['coefficient']; ?></span>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <?php if ($matiere['volume_horaire']): ?>
                                        <?php echo $matiere['volume_horaire']; ?>h/semaine
                                    <?php else: ?>
                                        <span class="text-muted">Non défini</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <?php if ($matiere['nb_classes'] > 0): ?>
                                        <span class="badge bg-primary"><?php echo $matiere['nb_classes']; ?></span>
                                    <?php else: ?>
                                        <span class="text-muted">0</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <?php if ($matiere['nb_enseignants'] > 0): ?>
                                        <span class="badge bg-success"><?php echo $matiere['nb_enseignants']; ?></span>
                                    <?php else: ?>
                                        <span class="text-muted">0</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <a href="view.php?id=<?php echo $matiere['id']; ?>" 
                                           class="btn btn-outline-info" 
                                           title="Voir détails">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <?php if (checkPermission('academic')): ?>
                                            <a href="edit.php?id=<?php echo $matiere['id']; ?>" 
                                               class="btn btn-outline-primary" 
                                               title="Modifier">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <a href="delete.php?id=<?php echo $matiere['id']; ?>" 
                                               class="btn btn-outline-danger btn-delete" 
                                               title="Supprimer"
                                               data-name="<?php echo htmlspecialchars($matiere['nom']); ?>">
                                                <i class="fas fa-trash"></i>
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
                <i class="fas fa-book-open fa-3x text-muted mb-3"></i>
                <h5 class="text-muted">Aucune matière trouvée</h5>
                <p class="text-muted">
                    <?php if (checkPermission('academic')): ?>
                        <a href="add.php" class="btn btn-primary">
                            <i class="fas fa-plus me-1"></i>
                            Créer la première matière
                        </a>
                    <?php else: ?>
                        Aucune matière n'est encore configurée dans le système.
                    <?php endif; ?>
                </p>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Résumé par niveau -->
<?php if (!empty($matieres)): ?>
<div class="row mt-4">
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-chart-bar me-2"></i>
                    Répartition par niveau
                </h5>
            </div>
            <div class="card-body">
                <canvas id="niveauxChart" width="100%" height="200"></canvas>
            </div>
        </div>
    </div>
    
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-chart-pie me-2"></i>
                    Types de matières
                </h5>
            </div>
            <div class="card-body">
                <div class="row text-center">
                    <div class="col-6">
                        <h3 class="text-success"><?php echo $stats['obligatoires']; ?></h3>
                        <p class="mb-0">
                            <i class="fas fa-star me-1"></i>
                            Obligatoires
                        </p>
                    </div>
                    <div class="col-6">
                        <h3 class="text-warning"><?php echo $stats['optionnelles']; ?></h3>
                        <p class="mb-0">
                            <i class="fas fa-star-half-alt me-1"></i>
                            Optionnelles
                        </p>
                    </div>
                </div>
                
                <hr>
                
                <div class="table-responsive">
                    <table class="table table-sm table-borderless">
                        <tr>
                            <td><span class="badge bg-warning">Maternelle</span></td>
                            <td class="text-end"><?php echo $stats['maternelle']; ?> matières</td>
                        </tr>
                        <tr>
                            <td><span class="badge bg-success">Primaire</span></td>
                            <td class="text-end"><?php echo $stats['primaire']; ?> matières</td>
                        </tr>
                        <tr>
                            <td><span class="badge bg-primary">Secondaire</span></td>
                            <td class="text-end"><?php echo $stats['secondaire']; ?> matières</td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
// Graphique de répartition par niveau
<?php if (!empty($matieres)): ?>
const niveauxData = {
    'Maternelle': <?php echo $stats['maternelle']; ?>,
    'Primaire': <?php echo $stats['primaire']; ?>,
    'Secondaire': <?php echo $stats['secondaire']; ?>
};

const niveauxCtx = document.getElementById('niveauxChart').getContext('2d');
const niveauxChart = new Chart(niveauxCtx, {
    type: 'bar',
    data: {
        labels: Object.keys(niveauxData),
        datasets: [{
            label: 'Nombre de matières',
            data: Object.values(niveauxData),
            backgroundColor: ['#f39c12', '#27ae60', '#3498db'],
            borderWidth: 0
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                display: false
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    stepSize: 1
                }
            }
        }
    }
});
<?php endif; ?>
</script>

<?php include '../../../includes/footer.php'; ?>
