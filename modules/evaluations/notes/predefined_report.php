<?php
/**
 * Module d'évaluations - Rapports prédéfinis
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

// Récupérer le type de rapport
$type = sanitizeInput($_GET['type'] ?? '');

if (!$type) {
    showMessage('error', 'Type de rapport manquant.');
    redirectTo('reports.php');
}

// Récupérer l'année scolaire active
$current_year = $database->query("SELECT * FROM annees_scolaires WHERE status = 'active' LIMIT 1")->fetch();
if (!$current_year) {
    showMessage('error', 'Aucune année scolaire active trouvée.');
    redirectTo('../../../index.php');
}

$page_title = 'Rapport prédéfini';
$report_data = [];

switch ($type) {
    case 'ranking':
        $page_title = 'Classement général des élèves';
        $report_data = $database->query(
            "SELECT el.nom, el.prenom, el.numero_matricule,
                    c.nom as classe_nom, c.niveau,
                    COUNT(n.id) as nb_notes,
                    AVG(n.note) as moyenne_eleve,
                    MIN(n.note) as note_min,
                    MAX(n.note) as note_max
             FROM eleves el
             JOIN inscriptions i ON el.id = i.eleve_id
             JOIN classes c ON i.classe_id = c.id
             JOIN notes n ON el.id = n.eleve_id
             JOIN evaluations e ON n.evaluation_id = e.id
             WHERE i.annee_scolaire_id = ? AND i.status = 'inscrit' AND e.annee_scolaire_id = ?
             GROUP BY el.id, el.nom, el.prenom, el.numero_matricule, c.nom, c.niveau
             HAVING nb_notes >= 3
             ORDER BY moyenne_eleve DESC
             LIMIT 50",
            [$current_year['id'], $current_year['id']]
        )->fetchAll();
        break;
        
    case 'global_analysis':
        $page_title = 'Analyse globale des performances';
        $report_data = [
            'stats_generales' => $database->query(
                "SELECT COUNT(DISTINCT e.id) as nb_evaluations,
                        COUNT(DISTINCT e.classe_id) as nb_classes,
                        COUNT(DISTINCT e.matiere_id) as nb_matieres,
                        COUNT(n.id) as nb_notes,
                        AVG(n.note) as moyenne_generale,
                        MIN(n.note) as note_min,
                        MAX(n.note) as note_max
                 FROM evaluations e
                 LEFT JOIN notes n ON e.id = n.evaluation_id
                 WHERE e.annee_scolaire_id = ?",
                [$current_year['id']]
            )->fetch(),
            'distribution' => $database->query(
                "SELECT 
                    CASE 
                        WHEN n.note >= 16 THEN 'Excellent'
                        WHEN n.note >= 14 THEN 'Très bien'
                        WHEN n.note >= 12 THEN 'Bien'
                        WHEN n.note >= 10 THEN 'Assez bien'
                        WHEN n.note >= 8 THEN 'Passable'
                        ELSE 'Insuffisant'
                    END as tranche,
                    COUNT(*) as nombre,
                    ROUND((COUNT(*) * 100.0 / (SELECT COUNT(*) FROM notes n2 JOIN evaluations e2 ON n2.evaluation_id = e2.id WHERE e2.annee_scolaire_id = ?)), 2) as pourcentage
                 FROM notes n
                 JOIN evaluations e ON n.evaluation_id = e.id
                 WHERE e.annee_scolaire_id = ?
                 GROUP BY tranche
                 ORDER BY MIN(n.note) DESC",
                [$current_year['id'], $current_year['id']]
            )->fetchAll()
        ];
        break;
        
    case 'struggling_students':
        $page_title = 'Élèves en difficulté (moyenne < 10/20)';
        $report_data = $database->query(
            "SELECT el.nom, el.prenom, el.numero_matricule,
                    c.nom as classe_nom, c.niveau,
                    COUNT(n.id) as nb_notes,
                    AVG(n.note) as moyenne_eleve,
                    MIN(n.note) as note_min,
                    MAX(n.note) as note_max
             FROM eleves el
             JOIN inscriptions i ON el.id = i.eleve_id
             JOIN classes c ON i.classe_id = c.id
             JOIN notes n ON el.id = n.eleve_id
             JOIN evaluations e ON n.evaluation_id = e.id
             WHERE i.annee_scolaire_id = ? AND i.status = 'inscrit' AND e.annee_scolaire_id = ?
             GROUP BY el.id, el.nom, el.prenom, el.numero_matricule, c.nom, c.niveau
             HAVING moyenne_eleve < 10 AND nb_notes >= 2
             ORDER BY moyenne_eleve ASC",
            [$current_year['id'], $current_year['id']]
        )->fetchAll();
        break;
        
    case 'monthly_tracking':
        $page_title = 'Suivi mensuel des évaluations';
        $report_data = $database->query(
            "SELECT DATE_FORMAT(e.date_evaluation, '%Y-%m') as mois,
                    COUNT(DISTINCT e.id) as nb_evaluations,
                    COUNT(n.id) as nb_notes,
                    AVG(n.note) as moyenne_mois,
                    MIN(n.note) as note_min,
                    MAX(n.note) as note_max
             FROM evaluations e
             LEFT JOIN notes n ON e.id = n.evaluation_id
             WHERE e.annee_scolaire_id = ?
             GROUP BY mois
             ORDER BY mois",
            [$current_year['id']]
        )->fetchAll();
        break;
        
    default:
        showMessage('error', 'Type de rapport non reconnu.');
        redirectTo('reports.php');
}

include '../../../includes/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">
        <i class="fas fa-magic me-2"></i>
        <?php echo $page_title; ?>
    </h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <div class="btn-group me-2">
            <a href="reports.php" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-1"></i>
                Retour
            </a>
        </div>
        <div class="btn-group">
            <button onclick="window.print()" class="btn btn-primary">
                <i class="fas fa-print me-1"></i>
                Imprimer
            </button>
        </div>
    </div>
</div>

<?php if ($type === 'ranking'): ?>
    <!-- Classement général -->
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">
                <i class="fas fa-trophy me-2"></i>
                Top 50 des meilleurs élèves
            </h5>
        </div>
        <div class="card-body">
            <?php if (!empty($report_data)): ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Rang</th>
                                <th>Matricule</th>
                                <th>Élève</th>
                                <th>Classe</th>
                                <th>Niveau</th>
                                <th>Nb notes</th>
                                <th>Moyenne</th>
                                <th>Min/Max</th>
                                <th>Mention</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($report_data as $index => $eleve): ?>
                                <tr>
                                    <td>
                                        <?php if ($index < 3): ?>
                                            <span class="badge bg-warning fs-6"><?php echo $index + 1; ?></span>
                                        <?php else: ?>
                                            <span class="fw-bold"><?php echo $index + 1; ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td><code><?php echo htmlspecialchars($eleve['numero_matricule']); ?></code></td>
                                    <td><strong><?php echo htmlspecialchars($eleve['nom'] . ' ' . $eleve['prenom']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($eleve['classe_nom']); ?></td>
                                    <td>
                                        <span class="badge bg-secondary">
                                            <?php echo ucfirst($eleve['niveau']); ?>
                                        </span>
                                    </td>
                                    <td class="text-center"><?php echo $eleve['nb_notes']; ?></td>
                                    <td class="text-center">
                                        <?php 
                                        $moyenne = round($eleve['moyenne_eleve'], 2);
                                        $color = $moyenne >= 16 ? 'success' : ($moyenne >= 14 ? 'info' : ($moyenne >= 12 ? 'primary' : 'warning'));
                                        ?>
                                        <span class="badge bg-<?php echo $color; ?> fs-6">
                                            <?php echo $moyenne; ?>/20
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        <small class="text-muted">
                                            <?php echo $eleve['note_min']; ?> - <?php echo $eleve['note_max']; ?>
                                        </small>
                                    </td>
                                    <td class="text-center">
                                        <?php
                                        if ($moyenne >= 16) echo '<span class="text-success fw-bold">Excellent</span>';
                                        elseif ($moyenne >= 14) echo '<span class="text-info fw-bold">Très bien</span>';
                                        elseif ($moyenne >= 12) echo '<span class="text-primary fw-bold">Bien</span>';
                                        elseif ($moyenne >= 10) echo '<span class="text-warning fw-bold">Assez bien</span>';
                                        else echo '<span class="text-danger fw-bold">Insuffisant</span>';
                                        ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="text-center py-4">
                    <i class="fas fa-trophy fa-3x text-muted mb-3"></i>
                    <h5 class="text-muted">Aucun élève trouvé</h5>
                    <p class="text-muted">Aucun élève n'a suffisamment de notes pour établir un classement.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

<?php elseif ($type === 'global_analysis'): ?>
    <!-- Analyse globale -->
    <div class="row mb-4">
        <div class="col-lg-2 col-md-4 mb-3">
            <div class="card bg-primary text-white">
                <div class="card-body text-center">
                    <h3><?php echo $report_data['stats_generales']['nb_evaluations']; ?></h3>
                    <p class="mb-0">Évaluations</p>
                </div>
            </div>
        </div>
        <div class="col-lg-2 col-md-4 mb-3">
            <div class="card bg-success text-white">
                <div class="card-body text-center">
                    <h3><?php echo $report_data['stats_generales']['nb_classes']; ?></h3>
                    <p class="mb-0">Classes</p>
                </div>
            </div>
        </div>
        <div class="col-lg-2 col-md-4 mb-3">
            <div class="card bg-info text-white">
                <div class="card-body text-center">
                    <h3><?php echo $report_data['stats_generales']['nb_matieres']; ?></h3>
                    <p class="mb-0">Matières</p>
                </div>
            </div>
        </div>
        <div class="col-lg-2 col-md-4 mb-3">
            <div class="card bg-warning text-white">
                <div class="card-body text-center">
                    <h3><?php echo $report_data['stats_generales']['nb_notes']; ?></h3>
                    <p class="mb-0">Notes</p>
                </div>
            </div>
        </div>
        <div class="col-lg-2 col-md-4 mb-3">
            <div class="card bg-secondary text-white">
                <div class="card-body text-center">
                    <h3><?php echo round($report_data['stats_generales']['moyenne_generale'], 2); ?></h3>
                    <p class="mb-0">Moyenne</p>
                </div>
            </div>
        </div>
        <div class="col-lg-2 col-md-4 mb-3">
            <div class="card bg-dark text-white">
                <div class="card-body text-center">
                    <h3><?php echo $report_data['stats_generales']['note_min']; ?>-<?php echo $report_data['stats_generales']['note_max']; ?></h3>
                    <p class="mb-0">Min-Max</p>
                </div>
            </div>
        </div>
    </div>
    
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">
                <i class="fas fa-chart-pie me-2"></i>
                Distribution des notes
            </h5>
        </div>
        <div class="card-body">
            <?php if (!empty($report_data['distribution'])): ?>
                <?php foreach ($report_data['distribution'] as $tranche): ?>
                    <div class="mb-3">
                        <div class="d-flex justify-content-between mb-1">
                            <span><?php echo $tranche['tranche']; ?></span>
                            <span><?php echo $tranche['nombre']; ?> notes (<?php echo $tranche['pourcentage']; ?>%)</span>
                        </div>
                        <div class="progress">
                            <?php
                            $color = 'secondary';
                            if (strpos($tranche['tranche'], 'Excellent') !== false) $color = 'success';
                            elseif (strpos($tranche['tranche'], 'Très bien') !== false) $color = 'info';
                            elseif (strpos($tranche['tranche'], 'Bien') !== false) $color = 'primary';
                            elseif (strpos($tranche['tranche'], 'Assez bien') !== false) $color = 'warning';
                            elseif (strpos($tranche['tranche'], 'Passable') !== false) $color = 'warning';
                            elseif (strpos($tranche['tranche'], 'Insuffisant') !== false) $color = 'danger';
                            ?>
                            <div class="progress-bar bg-<?php echo $color; ?>" 
                                 style="width: <?php echo $tranche['pourcentage']; ?>%"></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p class="text-muted">Aucune donnée disponible pour la distribution.</p>
            <?php endif; ?>
        </div>
    </div>

<?php elseif ($type === 'struggling_students'): ?>
    <!-- Élèves en difficulté -->
    <div class="card">
        <div class="card-header bg-warning">
            <h5 class="mb-0">
                <i class="fas fa-exclamation-triangle me-2"></i>
                Élèves nécessitant un accompagnement
            </h5>
        </div>
        <div class="card-body">
            <?php if (!empty($report_data)): ?>
                <div class="alert alert-warning">
                    <strong>Attention :</strong> <?php echo count($report_data); ?> élève(s) ont une moyenne inférieure à 10/20 et nécessitent un accompagnement particulier.
                </div>
                
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Matricule</th>
                                <th>Élève</th>
                                <th>Classe</th>
                                <th>Nb notes</th>
                                <th>Moyenne</th>
                                <th>Min/Max</th>
                                <th>Priorité</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($report_data as $eleve): ?>
                                <tr>
                                    <td><code><?php echo htmlspecialchars($eleve['numero_matricule']); ?></code></td>
                                    <td><strong><?php echo htmlspecialchars($eleve['nom'] . ' ' . $eleve['prenom']); ?></strong></td>
                                    <td>
                                        <?php echo htmlspecialchars($eleve['classe_nom']); ?>
                                        <small class="text-muted">(<?php echo ucfirst($eleve['niveau']); ?>)</small>
                                    </td>
                                    <td class="text-center"><?php echo $eleve['nb_notes']; ?></td>
                                    <td class="text-center">
                                        <span class="badge bg-danger fs-6">
                                            <?php echo round($eleve['moyenne_eleve'], 2); ?>/20
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        <small class="text-muted">
                                            <?php echo $eleve['note_min']; ?> - <?php echo $eleve['note_max']; ?>
                                        </small>
                                    </td>
                                    <td class="text-center">
                                        <?php
                                        $moyenne = round($eleve['moyenne_eleve'], 2);
                                        if ($moyenne < 5) {
                                            echo '<span class="badge bg-danger">Urgente</span>';
                                        } elseif ($moyenne < 7) {
                                            echo '<span class="badge bg-warning">Élevée</span>';
                                        } else {
                                            echo '<span class="badge bg-info">Modérée</span>';
                                        }
                                        ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="text-center py-4">
                    <i class="fas fa-smile fa-3x text-success mb-3"></i>
                    <h5 class="text-success">Excellente nouvelle !</h5>
                    <p class="text-muted">Aucun élève n'a une moyenne inférieure à 10/20.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

<?php elseif ($type === 'monthly_tracking'): ?>
    <!-- Suivi mensuel -->
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">
                <i class="fas fa-calendar-check me-2"></i>
                Évolution mensuelle des évaluations
            </h5>
        </div>
        <div class="card-body">
            <?php if (!empty($report_data)): ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Mois</th>
                                <th>Évaluations</th>
                                <th>Notes saisies</th>
                                <th>Moyenne</th>
                                <th>Min/Max</th>
                                <th>Tendance</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $previous_moyenne = null;
                            foreach ($report_data as $mois): 
                                $moyenne_actuelle = round($mois['moyenne_mois'], 2);
                            ?>
                                <tr>
                                    <td>
                                        <strong>
                                            <?php 
                                            $date = DateTime::createFromFormat('Y-m', $mois['mois']);
                                            echo $date ? $date->format('F Y') : $mois['mois']; 
                                            ?>
                                        </strong>
                                    </td>
                                    <td class="text-center"><?php echo $mois['nb_evaluations']; ?></td>
                                    <td class="text-center"><?php echo $mois['nb_notes']; ?></td>
                                    <td class="text-center">
                                        <?php if ($mois['moyenne_mois']): ?>
                                            <?php 
                                            $color = $moyenne_actuelle >= 14 ? 'success' : ($moyenne_actuelle >= 10 ? 'warning' : 'danger');
                                            ?>
                                            <span class="badge bg-<?php echo $color; ?>">
                                                <?php echo $moyenne_actuelle; ?>/20
                                            </span>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <?php if ($mois['note_min'] && $mois['note_max']): ?>
                                            <small class="text-muted">
                                                <?php echo $mois['note_min']; ?> - <?php echo $mois['note_max']; ?>
                                            </small>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <?php if ($previous_moyenne !== null && $mois['moyenne_mois']): ?>
                                            <?php if ($moyenne_actuelle > $previous_moyenne): ?>
                                                <i class="fas fa-arrow-up text-success" title="En hausse"></i>
                                            <?php elseif ($moyenne_actuelle < $previous_moyenne): ?>
                                                <i class="fas fa-arrow-down text-danger" title="En baisse"></i>
                                            <?php else: ?>
                                                <i class="fas fa-minus text-muted" title="Stable"></i>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php 
                                $previous_moyenne = $moyenne_actuelle;
                            endforeach; 
                            ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="text-center py-4">
                    <i class="fas fa-calendar fa-3x text-muted mb-3"></i>
                    <h5 class="text-muted">Aucune donnée mensuelle</h5>
                    <p class="text-muted">Aucune évaluation trouvée pour générer le suivi mensuel.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>

<?php include '../../../includes/footer.php'; ?>
