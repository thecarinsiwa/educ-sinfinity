<?php
/**
 * Module d'évaluations - Index des statistiques
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

$page_title = 'Statistiques des évaluations';

// Récupérer l'année scolaire active
$current_year = $database->query("SELECT * FROM annees_scolaires WHERE status = 'active' LIMIT 1")->fetch();
if (!$current_year) {
    showMessage('error', 'Aucune année scolaire active trouvée.');
    redirectTo('../../../index.php');
}

// Statistiques générales
$stats_generales = [
    'total_evaluations' => 0,
    'total_notes' => 0,
    'moyenne_generale' => 0,
    'total_classes' => 0,
    'total_eleves' => 0,
    'evaluations_par_periode' => []
];

try {
    // Total des évaluations
    $stats_generales['total_evaluations'] = $database->query(
        "SELECT COUNT(*) as total FROM evaluations WHERE annee_scolaire_id = ?",
        [$current_year['id']]
    )->fetch()['total'];

    // Total des notes
    $stats_generales['total_notes'] = $database->query(
        "SELECT COUNT(*) as total FROM notes n 
         JOIN evaluations e ON n.evaluation_id = e.id 
         WHERE e.annee_scolaire_id = ?",
        [$current_year['id']]
    )->fetch()['total'];

    // Moyenne générale
    $moyenne_result = $database->query(
        "SELECT AVG(n.note / e.note_max * 20) as moyenne 
         FROM notes n 
         JOIN evaluations e ON n.evaluation_id = e.id 
         WHERE e.annee_scolaire_id = ?",
        [$current_year['id']]
    )->fetch();
    $stats_generales['moyenne_generale'] = $moyenne_result['moyenne'] ?? 0;

    // Total des classes
    $stats_generales['total_classes'] = $database->query(
        "SELECT COUNT(*) as total FROM classes WHERE annee_scolaire_id = ?",
        [$current_year['id']]
    )->fetch()['total'];

    // Total des élèves
    $stats_generales['total_eleves'] = $database->query(
        "SELECT COUNT(DISTINCT i.eleve_id) as total 
         FROM inscriptions i 
         WHERE i.annee_scolaire_id = ? AND i.status = 'inscrit'",
        [$current_year['id']]
    )->fetch()['total'];

    // Évaluations par période
    $stats_generales['evaluations_par_periode'] = $database->query(
        "SELECT periode, COUNT(*) as total 
         FROM evaluations 
         WHERE annee_scolaire_id = ? 
         GROUP BY periode 
         ORDER BY 
            CASE periode 
                WHEN '1er_trimestre' THEN 1 
                WHEN '2eme_trimestre' THEN 2 
                WHEN '3eme_trimestre' THEN 3 
                ELSE 4 
            END",
        [$current_year['id']]
    )->fetchAll();

} catch (Exception $e) {
    // En cas d'erreur, garder les valeurs par défaut
}

// Statistiques par matière (top 5)
$stats_matieres = $database->query(
    "SELECT m.nom, m.code, COUNT(e.id) as nb_evaluations, COUNT(n.id) as nb_notes,
            AVG(n.note / e.note_max * 20) as moyenne_matiere
     FROM matieres m
     LEFT JOIN evaluations e ON m.id = e.matiere_id AND e.annee_scolaire_id = ?
     LEFT JOIN notes n ON e.id = n.evaluation_id
     GROUP BY m.id, m.nom, m.code
     HAVING nb_evaluations > 0
     ORDER BY nb_evaluations DESC, moyenne_matiere DESC
     LIMIT 5",
    [$current_year['id']]
)->fetchAll();

// Statistiques par classe (top 5)
$stats_classes = $database->query(
    "SELECT c.nom, c.niveau, COUNT(DISTINCT e.id) as nb_evaluations, 
            COUNT(n.id) as nb_notes, AVG(n.note / e.note_max * 20) as moyenne_classe
     FROM classes c
     LEFT JOIN evaluations e ON c.id = e.classe_id AND e.annee_scolaire_id = ?
     LEFT JOIN notes n ON e.id = n.evaluation_id
     WHERE c.annee_scolaire_id = ?
     GROUP BY c.id, c.nom, c.niveau
     HAVING nb_evaluations > 0
     ORDER BY moyenne_classe DESC
     LIMIT 5",
    [$current_year['id'], $current_year['id']]
)->fetchAll();

// Activité récente (dernières évaluations)
$activite_recente = $database->query(
    "SELECT e.nom, e.date_evaluation, e.type_evaluation, c.nom as classe_nom, 
            m.nom as matiere_nom, COUNT(n.id) as nb_notes_saisies
     FROM evaluations e
     JOIN classes c ON e.classe_id = c.id
     JOIN matieres m ON e.matiere_id = m.id
     LEFT JOIN notes n ON e.id = n.evaluation_id
     WHERE e.annee_scolaire_id = ?
     GROUP BY e.id
     ORDER BY e.date_evaluation DESC, e.created_at DESC
     LIMIT 8",
    [$current_year['id']]
)->fetchAll();

include '../../../includes/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">
        <i class="fas fa-chart-bar me-2"></i>
        Statistiques des évaluations
    </h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <div class="btn-group me-2">
            <a href="../notes/" class="btn btn-outline-secondary">
                <i class="fas fa-edit me-1"></i>
                Saisie des notes
            </a>
            <a href="../evaluations/" class="btn btn-outline-primary">
                <i class="fas fa-clipboard-check me-1"></i>
                Évaluations
            </a>
        </div>
        <div class="btn-group">
            <button type="button" class="btn btn-primary dropdown-toggle" data-bs-toggle="dropdown">
                <i class="fas fa-chart-line me-1"></i>
                Rapports détaillés
            </button>
            <ul class="dropdown-menu">
                <li><a class="dropdown-item" href="class-ranking.php">
                    <i class="fas fa-trophy me-2"></i>Classement des classes
                </a></li>
                <li><a class="dropdown-item" href="student-performance.php">
                    <i class="fas fa-user-graduate me-2"></i>Performance des élèves
                </a></li>
                <li><a class="dropdown-item" href="subject-analysis.php">
                    <i class="fas fa-book me-2"></i>Analyse par matière
                </a></li>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item" href="evaluation-reports.php">
                    <i class="fas fa-file-chart-line me-2"></i>Rapports d'évaluations
                </a></li>
            </ul>
        </div>
    </div>
</div>

<!-- Vue d'ensemble -->
<div class="row mb-4">
    <div class="col-lg-3 col-md-6 mb-3">
        <div class="card bg-primary text-white">
            <div class="card-body text-center">
                <i class="fas fa-clipboard-check fa-2x mb-2"></i>
                <h3><?php echo number_format($stats_generales['total_evaluations']); ?></h3>
                <p class="mb-0">Évaluations créées</p>
            </div>
        </div>
    </div>
    <div class="col-lg-3 col-md-6 mb-3">
        <div class="card bg-success text-white">
            <div class="card-body text-center">
                <i class="fas fa-edit fa-2x mb-2"></i>
                <h3><?php echo number_format($stats_generales['total_notes']); ?></h3>
                <p class="mb-0">Notes saisies</p>
            </div>
        </div>
    </div>
    <div class="col-lg-3 col-md-6 mb-3">
        <div class="card bg-info text-white">
            <div class="card-body text-center">
                <i class="fas fa-chart-line fa-2x mb-2"></i>
                <h3><?php echo $stats_generales['moyenne_generale'] ? round($stats_generales['moyenne_generale'], 2) : '0'; ?>/20</h3>
                <p class="mb-0">Moyenne générale</p>
            </div>
        </div>
    </div>
    <div class="col-lg-3 col-md-6 mb-3">
        <div class="card bg-warning text-white">
            <div class="card-body text-center">
                <i class="fas fa-users fa-2x mb-2"></i>
                <h3><?php echo number_format($stats_generales['total_eleves']); ?></h3>
                <p class="mb-0">Élèves évalués</p>
            </div>
        </div>
    </div>
</div>

<!-- Répartition par période -->
<div class="row mb-4">
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-calendar-alt me-2"></i>
                    Évaluations par période
                </h5>
            </div>
            <div class="card-body">
                <?php if (!empty($stats_generales['evaluations_par_periode'])): ?>
                    <?php foreach ($stats_generales['evaluations_par_periode'] as $periode): ?>
                        <?php
                        $periode_nom = str_replace('_', ' ', ucfirst($periode['periode']));
                        $pourcentage = $stats_generales['total_evaluations'] > 0 ? 
                            ($periode['total'] / $stats_generales['total_evaluations']) * 100 : 0;
                        ?>
                        <div class="mb-3">
                            <div class="d-flex justify-content-between">
                                <span><?php echo $periode_nom; ?></span>
                                <span><strong><?php echo $periode['total']; ?> évaluations</strong></span>
                            </div>
                            <div class="progress">
                                <div class="progress-bar" style="width: <?php echo $pourcentage; ?>%"></div>
                            </div>
                            <small class="text-muted"><?php echo round($pourcentage, 1); ?>% du total</small>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="text-center text-muted py-3">
                        <i class="fas fa-calendar-times fa-2x mb-2"></i>
                        <p>Aucune évaluation créée pour cette année</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-tachometer-alt me-2"></i>
                    Indicateurs de performance
                </h5>
            </div>
            <div class="card-body">
                <div class="row text-center">
                    <div class="col-6 mb-3">
                        <h4 class="text-primary"><?php echo $stats_generales['total_classes']; ?></h4>
                        <small class="text-muted">Classes actives</small>
                    </div>
                    <div class="col-6 mb-3">
                        <h4 class="text-success">
                            <?php 
                            $taux_saisie = $stats_generales['total_evaluations'] > 0 ? 
                                ($stats_generales['total_notes'] / ($stats_generales['total_evaluations'] * $stats_generales['total_eleves'])) * 100 : 0;
                            echo round($taux_saisie, 1);
                            ?>%
                        </h4>
                        <small class="text-muted">Taux de saisie</small>
                    </div>
                    <div class="col-12">
                        <div class="progress mb-2">
                            <div class="progress-bar bg-success" style="width: <?php echo min($taux_saisie, 100); ?>%"></div>
                        </div>
                        <small class="text-muted">
                            <?php echo number_format($stats_generales['total_notes']); ?> notes sur 
                            <?php echo number_format($stats_generales['total_evaluations'] * $stats_generales['total_eleves']); ?> possibles
                        </small>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Top matières et classes -->
<div class="row mb-4">
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-book me-2"></i>
                    Top 5 - Matières les plus évaluées
                </h5>
            </div>
            <div class="card-body">
                <?php if (!empty($stats_matieres)): ?>
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Matière</th>
                                    <th>Éval.</th>
                                    <th>Notes</th>
                                    <th>Moyenne</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($stats_matieres as $matiere): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo htmlspecialchars($matiere['nom'] ?? ''); ?></strong><br>
                                            <small class="text-muted"><?php echo htmlspecialchars($matiere['code'] ?? ''); ?></small>
                                        </td>
                                        <td><?php echo $matiere['nb_evaluations']; ?></td>
                                        <td><?php echo $matiere['nb_notes']; ?></td>
                                        <td>
                                            <?php if ($matiere['moyenne_matiere']): ?>
                                                <span class="badge bg-primary">
                                                    <?php echo round($matiere['moyenne_matiere'], 2); ?>/20
                                                </span>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="text-center text-muted py-3">
                        <i class="fas fa-book-open fa-2x mb-2"></i>
                        <p>Aucune matière évaluée</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-trophy me-2"></i>
                    Top 5 - Classes les plus performantes
                </h5>
            </div>
            <div class="card-body">
                <?php if (!empty($stats_classes)): ?>
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Classe</th>
                                    <th>Éval.</th>
                                    <th>Notes</th>
                                    <th>Moyenne</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($stats_classes as $index => $classe): ?>
                                    <tr>
                                        <td>
                                            <?php if ($index < 3): ?>
                                                <span class="badge bg-warning me-1"><?php echo $index + 1; ?></span>
                                            <?php endif; ?>
                                            <strong><?php echo htmlspecialchars($classe['nom'] ?? ''); ?></strong><br>
                                            <small class="text-muted"><?php echo ucfirst($classe['niveau'] ?? ''); ?></small>
                                        </td>
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
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="text-center text-muted py-3">
                        <i class="fas fa-school fa-2x mb-2"></i>
                        <p>Aucune classe évaluée</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Activité récente -->
<div class="card">
    <div class="card-header">
        <h5 class="mb-0">
            <i class="fas fa-clock me-2"></i>
            Activité récente
        </h5>
    </div>
    <div class="card-body">
        <?php if (!empty($activite_recente)): ?>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Évaluation</th>
                            <th>Type</th>
                            <th>Classe</th>
                            <th>Matière</th>
                            <th>Notes saisies</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($activite_recente as $evaluation): ?>
                            <tr>
                                <td><?php echo date('d/m/Y', strtotime($evaluation['date_evaluation'])); ?></td>
                                <td><strong><?php echo htmlspecialchars($evaluation['nom'] ?? ''); ?></strong></td>
                                <td>
                                    <span class="badge bg-secondary">
                                        <?php echo ucfirst($evaluation['type_evaluation'] ?? ''); ?>
                                    </span>
                                </td>
                                <td><?php echo htmlspecialchars($evaluation['classe_nom'] ?? ''); ?></td>
                                <td><?php echo htmlspecialchars($evaluation['matiere_nom'] ?? ''); ?></td>
                                <td class="text-center">
                                    <?php if ($evaluation['nb_notes_saisies'] > 0): ?>
                                        <span class="badge bg-success"><?php echo $evaluation['nb_notes_saisies']; ?></span>
                                    <?php else: ?>
                                        <span class="badge bg-warning">0</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <a href="../notes/entry.php" class="btn btn-outline-primary" title="Saisir notes">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <a href="evaluation-details.php" class="btn btn-outline-info" title="Détails">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="text-center text-muted py-5">
                <i class="fas fa-calendar-times fa-3x mb-3"></i>
                <h5>Aucune activité récente</h5>
                <p>Aucune évaluation n'a été créée récemment.</p>
                <a href="../evaluations/add.php" class="btn btn-primary">
                    <i class="fas fa-plus me-1"></i>
                    Créer une évaluation
                </a>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Actions rapides -->
<div class="row mt-4">
    <div class="col-12">
        <div class="card bg-light">
            <div class="card-body">
                <h6 class="card-title">
                    <i class="fas fa-bolt me-2"></i>
                    Actions rapides
                </h6>
                <div class="row">
                    <div class="col-md-3 mb-2">
                        <a href="../evaluations/add.php" class="btn btn-primary w-100">
                            <i class="fas fa-plus me-1"></i>
                            Nouvelle évaluation
                        </a>
                    </div>
                    <div class="col-md-3 mb-2">
                        <a href="../notes/batch-entry.php" class="btn btn-success w-100">
                            <i class="fas fa-edit me-1"></i>
                            Saisie en lot
                        </a>
                    </div>
                    <div class="col-md-3 mb-2">
                        <a href="class-ranking.php" class="btn btn-info w-100">
                            <i class="fas fa-trophy me-1"></i>
                            Classements
                        </a>
                    </div>
                    <div class="col-md-3 mb-2">
                        <a href="../bulletins/" class="btn btn-warning w-100">
                            <i class="fas fa-file-alt me-1"></i>
                            Bulletins
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../../../includes/footer.php'; ?>
