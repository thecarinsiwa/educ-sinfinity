<?php
/**
 * Module d'évaluations - Analyse par matière
 * Application de gestion scolaire - République Démocratique du Congo
 */

require_once '../../../config/config.php';
require_once '../../../config/database.php';
require_once '../../../includes/functions.php';

// Vérifier l'authentification et les permissions
requireLogin();
if (!checkPermission('evaluations') && !checkPermission('evaluations_view')) {
    showMessage('error', 'Accès refusé à cette fonctionnalité.');
    redirectTo('index.php');
}

$page_title = 'Analyse par matière';

// Récupérer l'année scolaire active
$current_year = $database->query("SELECT * FROM annees_scolaires WHERE status = 'active' LIMIT 1")->fetch();
if (!$current_year) {
    showMessage('error', 'Aucune année scolaire active trouvée.');
    redirectTo('../../../index.php');
}

// Récupérer les paramètres de filtrage
$matiere_filter = (int)($_GET['matiere'] ?? 0);
$periode_filter = sanitizeInput($_GET['periode'] ?? '');
$classe_filter = (int)($_GET['classe'] ?? 0);

// Récupérer les listes pour les filtres
$matieres = $database->query("SELECT * FROM matieres ORDER BY nom")->fetchAll();
$classes = $database->query(
    "SELECT * FROM classes WHERE annee_scolaire_id = ? ORDER BY niveau, nom",
    [$current_year['id']]
)->fetchAll();

// Statistiques générales par matière
$stats_matieres = $database->query(
    "SELECT m.id, m.nom, m.code, m.coefficient,
            COUNT(DISTINCT e.id) as nb_evaluations,
            COUNT(DISTINCT e.classe_id) as nb_classes,
            COUNT(n.id) as nb_notes,
            AVG(n.note / e.note_max * 20) as moyenne_matiere,
            MIN(n.note / e.note_max * 20) as note_min,
            MAX(n.note / e.note_max * 20) as note_max,
            STDDEV(n.note / e.note_max * 20) as ecart_type
     FROM matieres m
     LEFT JOIN evaluations e ON m.id = e.matiere_id AND e.annee_scolaire_id = ?
     LEFT JOIN notes n ON e.id = n.evaluation_id
     WHERE (? = 0 OR m.id = ?)
     GROUP BY m.id, m.nom, m.code, m.coefficient
     HAVING nb_evaluations > 0
     ORDER BY moyenne_matiere DESC, nb_evaluations DESC",
    [$current_year['id'], $matiere_filter, $matiere_filter]
)->fetchAll();

// Analyse détaillée si une matière est sélectionnée
$analyse_detaillee = null;
if ($matiere_filter) {
    $matiere_info = $database->query(
        "SELECT * FROM matieres WHERE id = ?",
        [$matiere_filter]
    )->fetch();
    
    if ($matiere_info) {
        // Performance par classe pour cette matière
        $performance_classes = $database->query(
            "SELECT c.nom as classe_nom, c.niveau,
                    COUNT(DISTINCT e.id) as nb_evaluations,
                    COUNT(n.id) as nb_notes,
                    AVG(n.note / e.note_max * 20) as moyenne_classe,
                    MIN(n.note / e.note_max * 20) as note_min,
                    MAX(n.note / e.note_max * 20) as note_max
             FROM classes c
             JOIN evaluations e ON c.id = e.classe_id
             JOIN notes n ON e.id = n.evaluation_id
             WHERE e.matiere_id = ? AND e.annee_scolaire_id = ?
             " . ($periode_filter ? "AND e.periode = ?" : "") . "
             " . ($classe_filter ? "AND c.id = ?" : "") . "
             GROUP BY c.id, c.nom, c.niveau
             ORDER BY moyenne_classe DESC",
            array_filter([
                $matiere_filter, 
                $current_year['id'], 
                $periode_filter ?: null,
                $classe_filter ?: null
            ])
        )->fetchAll();
        
        // Évolution par période
        $evolution_periodes = $database->query(
            "SELECT e.periode,
                    COUNT(DISTINCT e.id) as nb_evaluations,
                    COUNT(n.id) as nb_notes,
                    AVG(n.note / e.note_max * 20) as moyenne_periode
             FROM evaluations e
             LEFT JOIN notes n ON e.id = n.evaluation_id
             WHERE e.matiere_id = ? AND e.annee_scolaire_id = ?
             GROUP BY e.periode
             ORDER BY 
                CASE e.periode 
                    WHEN '1er_trimestre' THEN 1 
                    WHEN '2eme_trimestre' THEN 2 
                    WHEN '3eme_trimestre' THEN 3 
                    ELSE 4 
                END",
            [$matiere_filter, $current_year['id']]
        )->fetchAll();
        
        // Types d'évaluations
        $types_evaluations = $database->query(
            "SELECT e.type_evaluation,
                    COUNT(e.id) as nb_evaluations,
                    COUNT(n.id) as nb_notes,
                    AVG(n.note / e.note_max * 20) as moyenne_type
             FROM evaluations e
             LEFT JOIN notes n ON e.id = n.evaluation_id
             WHERE e.matiere_id = ? AND e.annee_scolaire_id = ?
             GROUP BY e.type_evaluation
             ORDER BY moyenne_type DESC",
            [$matiere_filter, $current_year['id']]
        )->fetchAll();
        
        $analyse_detaillee = [
            'matiere' => $matiere_info,
            'performance_classes' => $performance_classes,
            'evolution_periodes' => $evolution_periodes,
            'types_evaluations' => $types_evaluations
        ];
    }
}

include '../../../includes/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">
        <i class="fas fa-book me-2"></i>
        Analyse par matière
    </h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <div class="btn-group me-2">
            <a href="index.php" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-1"></i>
                Retour aux statistiques
            </a>
        </div>
        <div class="btn-group">
            <a href="class-ranking.php" class="btn btn-outline-primary">
                <i class="fas fa-trophy me-1"></i>
                Classement classes
            </a>
            <a href="student-performance.php" class="btn btn-outline-info">
                <i class="fas fa-user-graduate me-1"></i>
                Performance élèves
            </a>
        </div>
    </div>
</div>

<!-- Filtres -->
<div class="card mb-4">
    <div class="card-header">
        <h5 class="mb-0">
            <i class="fas fa-filter me-2"></i>
            Filtres d'analyse
        </h5>
    </div>
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-md-4">
                <label for="matiere" class="form-label">Matière</label>
                <select class="form-select" id="matiere" name="matiere">
                    <option value="">Toutes les matières</option>
                    <?php foreach ($matieres as $matiere): ?>
                        <option value="<?php echo $matiere['id']; ?>" 
                                <?php echo $matiere_filter == $matiere['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($matiere['nom'] ?? ''); ?> 
                            (<?php echo htmlspecialchars($matiere['code'] ?? ''); ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="col-md-3">
                <label for="periode" class="form-label">Période</label>
                <select class="form-select" id="periode" name="periode">
                    <option value="">Toutes les périodes</option>
                    <option value="1er_trimestre" <?php echo $periode_filter === '1er_trimestre' ? 'selected' : ''; ?>>
                        1er Trimestre
                    </option>
                    <option value="2eme_trimestre" <?php echo $periode_filter === '2eme_trimestre' ? 'selected' : ''; ?>>
                        2ème Trimestre
                    </option>
                    <option value="3eme_trimestre" <?php echo $periode_filter === '3eme_trimestre' ? 'selected' : ''; ?>>
                        3ème Trimestre
                    </option>
                </select>
            </div>
            
            <div class="col-md-3">
                <label for="classe" class="form-label">Classe</label>
                <select class="form-select" id="classe" name="classe">
                    <option value="">Toutes les classes</option>
                    <?php foreach ($classes as $classe): ?>
                        <option value="<?php echo $classe['id']; ?>" 
                                <?php echo $classe_filter == $classe['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($classe['nom'] ?? ''); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="col-md-2">
                <label class="form-label">&nbsp;</label>
                <button type="submit" class="btn btn-primary w-100">
                    <i class="fas fa-search me-1"></i>
                    Analyser
                </button>
            </div>
        </form>
    </div>
</div>

<?php if ($analyse_detaillee): ?>
    <!-- Analyse détaillée d'une matière -->
    <div class="card mb-4">
        <div class="card-header bg-primary text-white">
            <h5 class="mb-0">
                <i class="fas fa-microscope me-2"></i>
                Analyse détaillée - <?php echo htmlspecialchars($analyse_detaillee['matiere']['nom'] ?? ''); ?>
            </h5>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <h6>Informations générales</h6>
                    <table class="table table-borderless table-sm">
                        <tr>
                            <td><strong>Code matière :</strong></td>
                            <td><?php echo htmlspecialchars($analyse_detaillee['matiere']['code'] ?? ''); ?></td>
                        </tr>
                        <tr>
                            <td><strong>Coefficient :</strong></td>
                            <td><?php echo $analyse_detaillee['matiere']['coefficient']; ?></td>
                        </tr>
                        <tr>
                            <td><strong>Description :</strong></td>
                            <td><?php echo $analyse_detaillee['matiere']['description'] ? htmlspecialchars($analyse_detaillee['matiere']['description']) : 'Non renseignée'; ?></td>
                        </tr>
                    </table>
                </div>
                <div class="col-md-6">
                    <h6>Statistiques globales</h6>
                    <?php 
                    $stats_globales = array_filter($stats_matieres, function($m) use ($matiere_filter) {
                        return $m['id'] == $matiere_filter;
                    });
                    $stats_globales = reset($stats_globales);
                    ?>
                    <?php if ($stats_globales): ?>
                        <table class="table table-borderless table-sm">
                            <tr>
                                <td><strong>Évaluations :</strong></td>
                                <td><?php echo $stats_globales['nb_evaluations']; ?></td>
                            </tr>
                            <tr>
                                <td><strong>Notes saisies :</strong></td>
                                <td><?php echo $stats_globales['nb_notes']; ?></td>
                            </tr>
                            <tr>
                                <td><strong>Moyenne générale :</strong></td>
                                <td>
                                    <span class="badge bg-primary">
                                        <?php echo $stats_globales['moyenne_matiere'] ? round($stats_globales['moyenne_matiere'], 2) : '0'; ?>/20
                                    </span>
                                </td>
                            </tr>
                        </table>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Performance par classe -->
    <div class="row mb-4">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-chart-bar me-2"></i>
                        Performance par classe
                    </h5>
                </div>
                <div class="card-body">
                    <?php if (!empty($analyse_detaillee['performance_classes'])): ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Rang</th>
                                        <th>Classe</th>
                                        <th>Niveau</th>
                                        <th>Évaluations</th>
                                        <th>Notes</th>
                                        <th>Moyenne</th>
                                        <th>Min/Max</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($analyse_detaillee['performance_classes'] as $index => $classe): ?>
                                        <tr>
                                            <td>
                                                <?php if ($index < 3): ?>
                                                    <span class="badge bg-warning"><?php echo $index + 1; ?></span>
                                                <?php else: ?>
                                                    <?php echo $index + 1; ?>
                                                <?php endif; ?>
                                            </td>
                                            <td><strong><?php echo htmlspecialchars($classe['classe_nom'] ?? ''); ?></strong></td>
                                            <td><?php echo ucfirst($classe['niveau'] ?? ''); ?></td>
                                            <td><?php echo $classe['nb_evaluations']; ?></td>
                                            <td><?php echo $classe['nb_notes']; ?></td>
                                            <td>
                                                <?php if ($classe['moyenne_classe']): ?>
                                                    <?php
                                                    $moyenne = round($classe['moyenne_classe'], 2);
                                                    $color = $moyenne >= 16 ? 'success' : ($moyenne >= 14 ? 'info' : ($moyenne >= 12 ? 'primary' : ($moyenne >= 10 ? 'warning' : 'danger')));
                                                    ?>
                                                    <span class="badge bg-<?php echo $color; ?>">
                                                        <?php echo $moyenne; ?>/20
                                                    </span>
                                                <?php else: ?>
                                                    <span class="text-muted">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <small class="text-muted">
                                                    <?php echo $classe['note_min'] ? round($classe['note_min'], 1) : '0'; ?> - 
                                                    <?php echo $classe['note_max'] ? round($classe['note_max'], 1) : '0'; ?>
                                                </small>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="text-center text-muted py-4">
                            <i class="fas fa-chart-bar fa-3x mb-3"></i>
                            <p>Aucune donnée de performance par classe disponible</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <div class="col-lg-4">
            <!-- Évolution par période -->
            <div class="card mb-3">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-calendar-alt me-2"></i>
                        Évolution par période
                    </h5>
                </div>
                <div class="card-body">
                    <?php if (!empty($analyse_detaillee['evolution_periodes'])): ?>
                        <?php foreach ($analyse_detaillee['evolution_periodes'] as $periode): ?>
                            <div class="mb-3">
                                <div class="d-flex justify-content-between">
                                    <span><?php echo str_replace('_', ' ', ucfirst($periode['periode'])); ?></span>
                                    <span class="badge bg-primary">
                                        <?php echo $periode['moyenne_periode'] ? round($periode['moyenne_periode'], 2) : '0'; ?>/20
                                    </span>
                                </div>
                                <small class="text-muted">
                                    <?php echo $periode['nb_evaluations']; ?> évaluations, 
                                    <?php echo $periode['nb_notes']; ?> notes
                                </small>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p class="text-muted">Aucune donnée par période</p>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Types d'évaluations -->
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-clipboard-check me-2"></i>
                        Types d'évaluations
                    </h5>
                </div>
                <div class="card-body">
                    <?php if (!empty($analyse_detaillee['types_evaluations'])): ?>
                        <?php foreach ($analyse_detaillee['types_evaluations'] as $type): ?>
                            <div class="mb-3">
                                <div class="d-flex justify-content-between">
                                    <span class="badge bg-secondary">
                                        <?php echo ucfirst($type['type_evaluation']); ?>
                                    </span>
                                    <span class="badge bg-info">
                                        <?php echo $type['moyenne_type'] ? round($type['moyenne_type'], 2) : '0'; ?>/20
                                    </span>
                                </div>
                                <small class="text-muted">
                                    <?php echo $type['nb_evaluations']; ?> évaluations, 
                                    <?php echo $type['nb_notes']; ?> notes
                                </small>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p class="text-muted">Aucune donnée par type</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<!-- Vue d'ensemble des matières -->
<div class="card">
    <div class="card-header">
        <h5 class="mb-0">
            <i class="fas fa-books me-2"></i>
            <?php echo $matiere_filter ? 'Comparaison avec les autres matières' : 'Vue d\'ensemble des matières'; ?>
        </h5>
    </div>
    <div class="card-body">
        <?php if (!empty($stats_matieres)): ?>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Rang</th>
                            <th>Matière</th>
                            <th>Code</th>
                            <th>Coef.</th>
                            <th>Classes</th>
                            <th>Évaluations</th>
                            <th>Notes</th>
                            <th>Moyenne</th>
                            <th>Écart-type</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($stats_matieres as $index => $matiere): ?>
                            <tr <?php echo $matiere['id'] == $matiere_filter ? 'class="table-primary"' : ''; ?>>
                                <td>
                                    <?php if ($index < 3): ?>
                                        <span class="badge bg-warning"><?php echo $index + 1; ?></span>
                                    <?php else: ?>
                                        <?php echo $index + 1; ?>
                                    <?php endif; ?>
                                </td>
                                <td><strong><?php echo htmlspecialchars($matiere['nom'] ?? ''); ?></strong></td>
                                <td><code><?php echo htmlspecialchars($matiere['code'] ?? ''); ?></code></td>
                                <td><?php echo $matiere['coefficient']; ?></td>
                                <td><?php echo $matiere['nb_classes']; ?></td>
                                <td><?php echo $matiere['nb_evaluations']; ?></td>
                                <td><?php echo $matiere['nb_notes']; ?></td>
                                <td>
                                    <?php if ($matiere['moyenne_matiere']): ?>
                                        <?php
                                        $moyenne = round($matiere['moyenne_matiere'], 2);
                                        $color = $moyenne >= 16 ? 'success' : ($moyenne >= 14 ? 'info' : ($moyenne >= 12 ? 'primary' : ($moyenne >= 10 ? 'warning' : 'danger')));
                                        ?>
                                        <span class="badge bg-<?php echo $color; ?>">
                                            <?php echo $moyenne; ?>/20
                                        </span>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php echo $matiere['ecart_type'] ? round($matiere['ecart_type'], 2) : '0'; ?>
                                </td>
                                <td>
                                    <a href="?matiere=<?php echo $matiere['id']; ?>" 
                                       class="btn btn-sm btn-outline-primary" 
                                       title="Analyser cette matière">
                                        <i class="fas fa-search"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="text-center text-muted py-5">
                <i class="fas fa-book-open fa-3x mb-3"></i>
                <h5>Aucune donnée disponible</h5>
                <p>Aucune matière n'a d'évaluations pour les critères sélectionnés.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include '../../../includes/footer.php'; ?>
