<?php
/**
 * Module d'évaluations et notes - Gestion des évaluations
 * Application de gestion scolaire - République Démocratique du Congo
 */

require_once '../../../config/config.php';
require_once '../../../config/database.php';
require_once '../../../includes/functions.php';

// Vérifier l'authentification et les permissions
requireLogin();
if (!checkPermission('evaluations') && !checkPermission('evaluations_view')) {
    showMessage('error', 'Accès refusé à ce module.');
    redirectTo('../index.php');
}

$page_title = 'Gestion des Évaluations';

// Obtenir l'année scolaire actuelle
$current_year = getCurrentAcademicYear();

// Paramètres de recherche et filtrage
$search = sanitizeInput($_GET['search'] ?? '');
$classe_filter = (int)($_GET['classe'] ?? 0);
$matiere_filter = (int)($_GET['matiere'] ?? 0);
$type_filter = sanitizeInput($_GET['type'] ?? '');
$periode_filter = sanitizeInput($_GET['periode'] ?? '');
$status_filter = sanitizeInput($_GET['status'] ?? '');

// Construction de la requête
$sql = "SELECT e.*, 
               m.nom as matiere_nom, m.coefficient,
               c.nom as classe_nom, c.niveau,
               p.nom as enseignant_nom, p.prenom as enseignant_prenom,
               COUNT(n.id) as nb_notes,
               AVG(n.note) as moyenne_evaluation
        FROM evaluations e
        JOIN matieres m ON e.matiere_id = m.id
        JOIN classes c ON e.classe_id = c.id
        LEFT JOIN personnel p ON e.enseignant_id = p.id
        LEFT JOIN notes n ON e.id = n.evaluation_id
        WHERE e.annee_scolaire_id = ?";

$params = [$current_year['id'] ?? 0];

if (!empty($search)) {
    $sql .= " AND (e.nom LIKE ? OR e.description LIKE ? OR m.nom LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

if ($classe_filter) {
    $sql .= " AND e.classe_id = ?";
    $params[] = $classe_filter;
}

if ($matiere_filter) {
    $sql .= " AND e.matiere_id = ?";
    $params[] = $matiere_filter;
}

if (!empty($type_filter)) {
    $sql .= " AND e.type = ?";
    $params[] = $type_filter;
}

if (!empty($periode_filter)) {
    $sql .= " AND e.periode = ?";
    $params[] = $periode_filter;
}

if (!empty($status_filter)) {
    $sql .= " AND e.status = ?";
    $params[] = $status_filter;
}

$sql .= " GROUP BY e.id ORDER BY e.date_evaluation DESC, e.id DESC";

$evaluations = $database->query($sql, $params)->fetchAll();

// Récupérer les classes pour le filtre
$classes = $database->query(
    "SELECT id, nom, niveau FROM classes WHERE annee_scolaire_id = ? ORDER BY niveau, nom",
    [$current_year['id'] ?? 0]
)->fetchAll();

// Récupérer les matières pour le filtre
$matieres = $database->query(
    "SELECT id, nom FROM matieres ORDER BY nom"
)->fetchAll();

// Statistiques
$stats = [
    'total' => count($evaluations),
    'programmees' => count(array_filter($evaluations, fn($e) => $e['status'] === 'programmee')),
    'en_cours' => count(array_filter($evaluations, fn($e) => $e['status'] === 'en_cours')),
    'terminees' => count(array_filter($evaluations, fn($e) => $e['status'] === 'terminee')),
    'notes_saisies' => array_sum(array_column($evaluations, 'nb_notes'))
];

include '../../../includes/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">
        <i class="fas fa-clipboard-list me-2"></i>
        Gestion des Évaluations
    </h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <div class="btn-group me-2">
            <a href="../index.php" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-1"></i>
                Retour
            </a>
        </div>
        <?php if (checkPermission('evaluations')): ?>
            <div class="btn-group me-2">
                <a href="add.php" class="btn btn-primary">
                    <i class="fas fa-plus me-1"></i>
                    Nouvelle évaluation
                </a>
            </div>
        <?php endif; ?>
        <div class="btn-group">
            <button type="button" class="btn btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">
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
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item" href="calendar.php">
                    <i class="fas fa-calendar me-2"></i>Calendrier
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
                        <p class="mb-0">Total évaluations</p>
                    </div>
                    <div class="align-self-center">
                        <i class="fas fa-clipboard-list fa-2x"></i>
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
                        <h4><?php echo $stats['programmees']; ?></h4>
                        <p class="mb-0">Programmées</p>
                    </div>
                    <div class="align-self-center">
                        <i class="fas fa-clock fa-2x"></i>
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
                        <h4><?php echo $stats['en_cours']; ?></h4>
                        <p class="mb-0">En cours</p>
                    </div>
                    <div class="align-self-center">
                        <i class="fas fa-play fa-2x"></i>
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
                        <h4><?php echo $stats['terminees']; ?></h4>
                        <p class="mb-0">Terminées</p>
                    </div>
                    <div class="align-self-center">
                        <i class="fas fa-check fa-2x"></i>
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
            <div class="col-md-3">
                <label for="search" class="form-label">Rechercher</label>
                <input type="text" 
                       class="form-control" 
                       id="search" 
                       name="search" 
                       placeholder="Nom d'évaluation, matière..."
                       value="<?php echo htmlspecialchars($search); ?>">
            </div>
            <div class="col-md-2">
                <label for="classe" class="form-label">Classe</label>
                <select class="form-select" id="classe" name="classe">
                    <option value="">Toutes</option>
                    <?php foreach ($classes as $classe): ?>
                        <option value="<?php echo $classe['id']; ?>" 
                                <?php echo $classe_filter == $classe['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($classe['nom']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label for="matiere" class="form-label">Matière</label>
                <select class="form-select" id="matiere" name="matiere">
                    <option value="">Toutes</option>
                    <?php foreach ($matieres as $matiere): ?>
                        <option value="<?php echo $matiere['id']; ?>" 
                                <?php echo $matiere_filter == $matiere['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($matiere['nom']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label for="type" class="form-label">Type</label>
                <select class="form-select" id="type" name="type">
                    <option value="">Tous</option>
                    <option value="devoir" <?php echo $type_filter === 'devoir' ? 'selected' : ''; ?>>Devoir</option>
                    <option value="composition" <?php echo $type_filter === 'composition' ? 'selected' : ''; ?>>Composition</option>
                    <option value="examen" <?php echo $type_filter === 'examen' ? 'selected' : ''; ?>>Examen</option>
                    <option value="interrogation" <?php echo $type_filter === 'interrogation' ? 'selected' : ''; ?>>Interrogation</option>
                </select>
            </div>
            <div class="col-md-2">
                <label for="periode" class="form-label">Période</label>
                <select class="form-select" id="periode" name="periode">
                    <option value="">Toutes</option>
                    <option value="1er_trimestre" <?php echo $periode_filter === '1er_trimestre' ? 'selected' : ''; ?>>1er Trimestre</option>
                    <option value="2eme_trimestre" <?php echo $periode_filter === '2eme_trimestre' ? 'selected' : ''; ?>>2ème Trimestre</option>
                    <option value="3eme_trimestre" <?php echo $periode_filter === '3eme_trimestre' ? 'selected' : ''; ?>>3ème Trimestre</option>
                </select>
            </div>
            <div class="col-md-1">
                <label class="form-label">&nbsp;</label>
                <div class="d-grid">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search"></i>
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Liste des évaluations -->
<div class="card">
    <div class="card-header">
        <h5 class="mb-0">
            <i class="fas fa-list me-2"></i>
            Liste des évaluations (<?php echo count($evaluations); ?>)
        </h5>
    </div>
    <div class="card-body">
        <?php if (!empty($evaluations)): ?>
            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Évaluation</th>
                            <th>Classe</th>
                            <th>Matière</th>
                            <th>Type</th>
                            <th>Période</th>
                            <th>Enseignant</th>
                            <th>Notes</th>
                            <th>Moyenne</th>
                            <th>Statut</th>
                            <th class="no-sort">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($evaluations as $evaluation): ?>
                            <tr>
                                <td>
                                    <?php echo formatDate($evaluation['date_evaluation']); ?>
                                    <?php if ($evaluation['heure_debut']): ?>
                                        <br><small class="text-muted">
                                            <?php echo substr($evaluation['heure_debut'], 0, 5); ?>
                                            <?php if ($evaluation['heure_fin']): ?>
                                                - <?php echo substr($evaluation['heure_fin'], 0, 5); ?>
                                            <?php endif; ?>
                                        </small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div>
                                        <strong><?php echo htmlspecialchars($evaluation['nom']); ?></strong>
                                        <?php if ($evaluation['description']): ?>
                                            <br><small class="text-muted">
                                                <?php echo htmlspecialchars(substr($evaluation['description'], 0, 50)); ?>
                                                <?php echo strlen($evaluation['description']) > 50 ? '...' : ''; ?>
                                            </small>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge bg-<?php 
                                        echo $evaluation['niveau'] === 'maternelle' ? 'warning' : 
                                            ($evaluation['niveau'] === 'primaire' ? 'success' : 'primary'); 
                                    ?>">
                                        <?php echo htmlspecialchars($evaluation['classe_nom']); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php echo htmlspecialchars($evaluation['matiere_nom']); ?>
                                    <?php if ($evaluation['coefficient']): ?>
                                        <br><small class="text-muted">Coef. <?php echo $evaluation['coefficient']; ?></small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php
                                    $type_colors = [
                                        'devoir' => 'info',
                                        'composition' => 'warning',
                                        'examen' => 'danger',
                                        'interrogation' => 'secondary'
                                    ];
                                    $color = $type_colors[$evaluation['type_evaluation']] ?? 'secondary';
                                    ?>
                                    <span class="badge bg-<?php echo $color; ?>">
                                        <?php echo ucfirst($evaluation['type_evaluation']); ?>
                                    </span>
                                </td>
                                <td>
                                    <small><?php echo str_replace('_', ' ', ucfirst($evaluation['periode'])); ?></small>
                                </td>
                                <td>
                                    <?php if ($evaluation['enseignant_nom']): ?>
                                        <small>
                                            <?php echo htmlspecialchars($evaluation['enseignant_nom'] . ' ' . substr($evaluation['enseignant_prenom'], 0, 1) . '.'); ?>
                                        </small>
                                    <?php else: ?>
                                        <span class="text-muted">Non assigné</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <?php if ($evaluation['nb_notes'] > 0): ?>
                                        <span class="badge bg-success"><?php echo $evaluation['nb_notes']; ?></span>
                                    <?php else: ?>
                                        <span class="badge bg-warning">0</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <?php if ($evaluation['moyenne_evaluation']): ?>
                                        <span class="badge bg-<?php 
                                            echo $evaluation['moyenne_evaluation'] >= 14 ? 'success' : 
                                                ($evaluation['moyenne_evaluation'] >= 10 ? 'warning' : 'danger'); 
                                        ?>">
                                            <?php echo round($evaluation['moyenne_evaluation'], 1); ?>/20
                                        </span>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php
                                    $status_colors = [
                                        'programmee' => 'warning',
                                        'en_cours' => 'info',
                                        'terminee' => 'success',
                                        'annulee' => 'danger'
                                    ];
                                    $color = $status_colors[$evaluation['status']] ?? 'secondary';
                                    ?>
                                    <span class="badge bg-<?php echo $color; ?>">
                                        <?php echo ucfirst($evaluation['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <a href="view.php?id=<?php echo $evaluation['id']; ?>" 
                                           class="btn btn-outline-info" 
                                           title="Voir détails">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <?php if (checkPermission('evaluations')): ?>
                                            <a href="../notes/entry.php?evaluation_id=<?php echo $evaluation['id']; ?>" 
                                               class="btn btn-outline-success" 
                                               title="Saisir notes">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <a href="edit.php?id=<?php echo $evaluation['id']; ?>" 
                                               class="btn btn-outline-primary" 
                                               title="Modifier">
                                                <i class="fas fa-pen"></i>
                                            </a>
                                            <?php if ($evaluation['status'] !== 'terminee'): ?>
                                                <a href="delete.php?id=<?php echo $evaluation['id']; ?>" 
                                                   class="btn btn-outline-danger" 
                                                   title="Supprimer"
                                                   onclick="return confirm('Êtes-vous sûr de vouloir supprimer cette évaluation ?')">
                                                    <i class="fas fa-trash"></i>
                                                </a>
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
                <i class="fas fa-clipboard-list fa-3x text-muted mb-3"></i>
                <h5 class="text-muted">Aucune évaluation trouvée</h5>
                <p class="text-muted">
                    <?php if (!empty($search) || $classe_filter || $matiere_filter): ?>
                        Aucune évaluation ne correspond aux critères de recherche.
                    <?php else: ?>
                        Aucune évaluation n'a encore été créée.
                    <?php endif; ?>
                </p>
                <?php if (checkPermission('evaluations')): ?>
                    <a href="add.php" class="btn btn-primary">
                        <i class="fas fa-plus me-1"></i>
                        Créer la première évaluation
                    </a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Actions rapides -->
<?php if (checkPermission('evaluations')): ?>
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
                            <a href="add.php" class="btn btn-outline-primary">
                                <i class="fas fa-plus me-2"></i>
                                Nouvelle évaluation
                            </a>
                        </div>
                    </div>
                    <div class="col-md-3 mb-2">
                        <div class="d-grid">
                            <a href="bulk-create.php" class="btn btn-outline-success">
                                <i class="fas fa-layer-group me-2"></i>
                                Création en lot
                            </a>
                        </div>
                    </div>
                    <div class="col-md-3 mb-2">
                        <div class="d-grid">
                            <a href="calendar.php" class="btn btn-outline-info">
                                <i class="fas fa-calendar me-2"></i>
                                Calendrier
                            </a>
                        </div>
                    </div>
                    <div class="col-md-3 mb-2">
                        <div class="d-grid">
                            <a href="../statistics/evaluations.php" class="btn btn-outline-secondary">
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

<?php include '../../../includes/footer.php'; ?>
