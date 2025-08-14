<?php
/**
 * Module d'évaluations - Rapport de classe
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

// Récupérer les paramètres
$classe_id = (int)($_GET['classe_id'] ?? 0);
$periode = sanitizeInput($_GET['periode'] ?? '');
$format = sanitizeInput($_GET['format'] ?? 'view');

if (!$classe_id) {
    showMessage('error', 'ID de classe manquant.');
    redirectTo('reports.php');
}

// Récupérer l'année scolaire active
$current_year = $database->query("SELECT * FROM annees_scolaires WHERE status = 'active' LIMIT 1")->fetch();
if (!$current_year) {
    showMessage('error', 'Aucune année scolaire active trouvée.');
    redirectTo('../../../index.php');
}

// Récupérer les détails de la classe
$classe = $database->query(
    "SELECT c.*, COUNT(DISTINCT i.eleve_id) as nb_eleves_inscrits
     FROM classes c
     LEFT JOIN inscriptions i ON (c.id = i.classe_id AND i.annee_scolaire_id = ? AND i.status = 'inscrit')
     WHERE c.id = ?
     GROUP BY c.id",
    [$current_year['id'], $classe_id]
)->fetch();

if (!$classe) {
    showMessage('error', 'Classe non trouvée.');
    redirectTo('reports.php');
}

// Construire la condition de période
$periode_condition = '';
$params = [$current_year['id'], $classe_id];
if ($periode) {
    $periode_condition = ' AND e.periode = ?';
    $params[] = $periode;
}

// Récupérer les évaluations de la classe
$evaluations = $database->query(
    "SELECT e.*, 
            m.nom as matiere_nom, m.coefficient as matiere_coefficient,
            CONCAT(p.nom, ' ', p.prenom) as enseignant_nom,
            COUNT(n.id) as notes_saisies,
            AVG(n.note) as moyenne_evaluation
     FROM evaluations e
     JOIN matieres m ON e.matiere_id = m.id
     LEFT JOIN personnel p ON e.enseignant_id = p.id
     LEFT JOIN notes n ON e.id = n.evaluation_id
     WHERE e.annee_scolaire_id = ? AND e.classe_id = ? $periode_condition
     GROUP BY e.id, m.nom, m.coefficient, p.nom, p.prenom
     ORDER BY e.date_evaluation DESC",
    $params
)->fetchAll();

// Récupérer les élèves avec leurs moyennes
$eleves = $database->query(
    "SELECT el.*, 
            COUNT(n.id) as nb_notes,
            AVG(n.note) as moyenne_eleve,
            MIN(n.note) as note_min,
            MAX(n.note) as note_max
     FROM eleves el
     JOIN inscriptions i ON el.id = i.eleve_id
     LEFT JOIN notes n ON el.id = n.eleve_id
     LEFT JOIN evaluations e ON (n.evaluation_id = e.id AND e.classe_id = ? $periode_condition)
     WHERE i.classe_id = ? AND i.annee_scolaire_id = ? AND i.status = 'inscrit'
     GROUP BY el.id, el.nom, el.prenom, el.numero_matricule
     ORDER BY moyenne_eleve DESC, el.nom, el.prenom",
    array_merge([$classe_id], $periode ? [$periode] : [], [$classe_id, $current_year['id']])
)->fetchAll();

// Calculer les statistiques générales
$stats = [
    'nb_evaluations' => count($evaluations),
    'nb_eleves' => count($eleves),
    'moyenne_generale' => 0,
    'nb_notes_total' => 0
];

if (!empty($evaluations)) {
    $moyennes_evaluations = array_filter(array_column($evaluations, 'moyenne_evaluation'));
    if (!empty($moyennes_evaluations)) {
        $stats['moyenne_generale'] = round(array_sum($moyennes_evaluations) / count($moyennes_evaluations), 2);
    }
    $stats['nb_notes_total'] = array_sum(array_column($evaluations, 'notes_saisies'));
}

// Gestion des formats
if ($format === 'excel') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="rapport_classe_' . $classe_id . '.csv"');
    
    $output = fopen('php://output', 'w');
    
    // En-têtes
    fputcsv($output, ['Rapport de classe - ' . $classe['nom']]);
    fputcsv($output, ['Année scolaire', $current_year['annee']]);
    if ($periode) fputcsv($output, ['Période', str_replace('_', ' ', ucfirst($periode))]);
    fputcsv($output, ['']);
    
    // Statistiques
    fputcsv($output, ['STATISTIQUES GÉNÉRALES']);
    fputcsv($output, ['Nombre d\'élèves', $stats['nb_eleves']]);
    fputcsv($output, ['Nombre d\'évaluations', $stats['nb_evaluations']]);
    fputcsv($output, ['Moyenne générale', $stats['moyenne_generale']]);
    fputcsv($output, ['']);
    
    // Évaluations
    fputcsv($output, ['ÉVALUATIONS']);
    fputcsv($output, ['Date', 'Nom', 'Matière', 'Type', 'Notes saisies', 'Moyenne']);
    foreach ($evaluations as $eval) {
        fputcsv($output, [
            date('d/m/Y', strtotime($eval['date_evaluation'])),
            $eval['nom'],
            $eval['matiere_nom'],
            ucfirst($eval['type_evaluation']),
            $eval['notes_saisies'],
            round($eval['moyenne_evaluation'], 2)
        ]);
    }
    fputcsv($output, ['']);
    
    // Élèves
    fputcsv($output, ['ÉLÈVES']);
    fputcsv($output, ['Rang', 'Matricule', 'Nom', 'Prénom', 'Nb notes', 'Moyenne', 'Note min', 'Note max']);
    foreach ($eleves as $index => $eleve) {
        fputcsv($output, [
            $index + 1,
            $eleve['numero_matricule'],
            $eleve['nom'],
            $eleve['prenom'],
            $eleve['nb_notes'],
            round($eleve['moyenne_eleve'], 2),
            $eleve['note_min'],
            $eleve['note_max']
        ]);
    }
    
    fclose($output);
    exit;
}

$page_title = 'Rapport de classe : ' . $classe['nom'];
$print_mode = isset($_GET['print']);

if ($print_mode) {
    echo "<!DOCTYPE html>
    <html>
    <head>
        <title>$page_title</title>
        <meta charset='utf-8'>
        <style>
            body { font-family: Arial, sans-serif; font-size: 12px; margin: 20px; }
            .header { text-align: center; margin-bottom: 30px; border-bottom: 2px solid #333; padding-bottom: 10px; }
            table { width: 100%; border-collapse: collapse; margin: 20px 0; }
            th, td { border: 1px solid #ddd; padding: 6px; text-align: left; font-size: 11px; }
            th { background-color: #f5f5f5; font-weight: bold; }
            .text-center { text-align: center; }
            @media print { body { margin: 0; } .no-print { display: none; } }
        </style>
    </head>
    <body>";
} else {
    include '../../../includes/header.php';
}
?>

<?php if (!$print_mode): ?>
<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">
        <i class="fas fa-school me-2"></i>
        Rapport de classe
    </h1>
    <div class="btn-toolbar mb-2 mb-md-0 no-print">
        <div class="btn-group me-2">
            <a href="reports.php" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-1"></i>
                Retour
            </a>
        </div>
        <div class="btn-group">
            <a href="?classe_id=<?php echo $classe_id; ?>&periode=<?php echo $periode; ?>&format=view&print=1" 
               class="btn btn-primary" target="_blank">
                <i class="fas fa-print me-1"></i>
                Imprimer
            </a>
            <a href="?classe_id=<?php echo $classe_id; ?>&periode=<?php echo $periode; ?>&format=excel" 
               class="btn btn-success">
                <i class="fas fa-file-excel me-1"></i>
                Excel
            </a>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- En-tête -->
<div class="<?php echo $print_mode ? 'header' : 'card mb-4'; ?>">
    <?php if (!$print_mode): ?><div class="card-body"><?php endif; ?>
        <div class="text-center">
            <h2>Classe <?php echo htmlspecialchars($classe['nom']); ?></h2>
            <h4 class="text-muted">
                Rapport de performance
                <?php if ($periode): ?>
                    - <?php echo str_replace('_', ' ', ucfirst($periode)); ?>
                <?php endif; ?>
            </h4>
            <p class="text-muted">Année scolaire <?php echo $current_year['annee']; ?></p>
        </div>
    <?php if (!$print_mode): ?></div><?php endif; ?>
</div>

<!-- Statistiques générales -->
<div class="<?php echo $print_mode ? '' : 'row mb-4'; ?>">
    <?php if (!$print_mode): ?>
        <div class="col-lg-3 col-md-6 mb-3">
            <div class="card bg-primary text-white">
                <div class="card-body text-center">
                    <h3><?php echo $stats['nb_eleves']; ?></h3>
                    <p class="mb-0">Élèves</p>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6 mb-3">
            <div class="card bg-success text-white">
                <div class="card-body text-center">
                    <h3><?php echo $stats['nb_evaluations']; ?></h3>
                    <p class="mb-0">Évaluations</p>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6 mb-3">
            <div class="card bg-info text-white">
                <div class="card-body text-center">
                    <h3><?php echo $stats['moyenne_generale']; ?>/20</h3>
                    <p class="mb-0">Moyenne générale</p>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6 mb-3">
            <div class="card bg-warning text-white">
                <div class="card-body text-center">
                    <h3><?php echo $stats['nb_notes_total']; ?></h3>
                    <p class="mb-0">Notes saisies</p>
                </div>
            </div>
        </div>
    <?php else: ?>
        <h3>Statistiques générales</h3>
        <p><strong>Élèves :</strong> <?php echo $stats['nb_eleves']; ?> | 
           <strong>Évaluations :</strong> <?php echo $stats['nb_evaluations']; ?> | 
           <strong>Moyenne générale :</strong> <?php echo $stats['moyenne_generale']; ?>/20 | 
           <strong>Notes saisies :</strong> <?php echo $stats['nb_notes_total']; ?></p>
    <?php endif; ?>
</div>

<!-- Liste des évaluations -->
<div class="<?php echo $print_mode ? '' : 'card mb-4'; ?>">
    <?php if (!$print_mode): ?>
        <div class="card-header">
            <h5 class="mb-0">
                <i class="fas fa-clipboard-check me-2"></i>
                Évaluations (<?php echo count($evaluations); ?>)
            </h5>
        </div>
        <div class="card-body">
    <?php else: ?>
        <h3>Évaluations</h3>
    <?php endif; ?>
    
    <?php if (!empty($evaluations)): ?>
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Nom</th>
                        <th>Matière</th>
                        <th>Type</th>
                        <th>Enseignant</th>
                        <th>Notes saisies</th>
                        <th>Moyenne</th>
                        <?php if (!$print_mode): ?><th>Actions</th><?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($evaluations as $eval): ?>
                        <tr>
                            <td><?php echo date('d/m/Y', strtotime($eval['date_evaluation'])); ?></td>
                            <td><strong><?php echo htmlspecialchars($eval['nom']); ?></strong></td>
                            <td><?php echo htmlspecialchars($eval['matiere_nom']); ?></td>
                            <td>
                                <span class="badge bg-primary">
                                    <?php echo ucfirst($eval['type_evaluation']); ?>
                                </span>
                            </td>
                            <td><?php echo htmlspecialchars($eval['enseignant_nom']); ?></td>
                            <td class="text-center"><?php echo $eval['notes_saisies']; ?></td>
                            <td class="text-center">
                                <?php if ($eval['moyenne_evaluation']): ?>
                                    <span class="badge bg-success">
                                        <?php echo round($eval['moyenne_evaluation'], 2); ?>/20
                                    </span>
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                            <?php if (!$print_mode): ?>
                                <td>
                                    <a href="evaluation_report.php?id=<?php echo $eval['id']; ?>&format=view" 
                                       class="btn btn-sm btn-outline-info" title="Voir rapport">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                </td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <p class="text-muted">Aucune évaluation trouvée pour cette classe.</p>
    <?php endif; ?>
    
    <?php if (!$print_mode): ?></div><?php endif; ?>
</div>

<!-- Classement des élèves -->
<div class="<?php echo $print_mode ? '' : 'card'; ?>">
    <?php if (!$print_mode): ?>
        <div class="card-header">
            <h5 class="mb-0">
                <i class="fas fa-trophy me-2"></i>
                Classement des élèves (<?php echo count($eleves); ?>)
            </h5>
        </div>
        <div class="card-body">
    <?php else: ?>
        <h3>Classement des élèves</h3>
    <?php endif; ?>
    
    <?php if (!empty($eleves)): ?>
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>Rang</th>
                        <th>Matricule</th>
                        <th>Élève</th>
                        <th>Nb notes</th>
                        <th>Moyenne</th>
                        <th>Note min</th>
                        <th>Note max</th>
                        <th>Appréciation</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($eleves as $index => $eleve): ?>
                        <tr>
                            <td>
                                <?php if ($index < 3 && $eleve['moyenne_eleve']): ?>
                                    <span class="badge bg-warning"><?php echo $index + 1; ?></span>
                                <?php else: ?>
                                    <?php echo $index + 1; ?>
                                <?php endif; ?>
                            </td>
                            <td><code><?php echo htmlspecialchars($eleve['numero_matricule']); ?></code></td>
                            <td><strong><?php echo htmlspecialchars($eleve['nom'] . ' ' . $eleve['prenom']); ?></strong></td>
                            <td class="text-center"><?php echo $eleve['nb_notes']; ?></td>
                            <td class="text-center">
                                <?php if ($eleve['moyenne_eleve']): ?>
                                    <?php 
                                    $moyenne = round($eleve['moyenne_eleve'], 2);
                                    $color = $moyenne >= 14 ? 'success' : ($moyenne >= 10 ? 'warning' : 'danger');
                                    ?>
                                    <span class="badge bg-<?php echo $color; ?>">
                                        <?php echo $moyenne; ?>/20
                                    </span>
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center"><?php echo $eleve['note_min'] ?? '-'; ?></td>
                            <td class="text-center"><?php echo $eleve['note_max'] ?? '-'; ?></td>
                            <td class="text-center">
                                <?php if ($eleve['moyenne_eleve']): ?>
                                    <?php
                                    $moyenne = round($eleve['moyenne_eleve'], 2);
                                    if ($moyenne >= 16) echo '<span class="text-success">Excellent</span>';
                                    elseif ($moyenne >= 14) echo '<span class="text-info">Très bien</span>';
                                    elseif ($moyenne >= 12) echo '<span class="text-primary">Bien</span>';
                                    elseif ($moyenne >= 10) echo '<span class="text-warning">Assez bien</span>';
                                    else echo '<span class="text-danger">Insuffisant</span>';
                                    ?>
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
        <p class="text-muted">Aucun élève trouvé pour cette classe.</p>
    <?php endif; ?>
    
    <?php if (!$print_mode): ?></div><?php endif; ?>
</div>

<?php if ($print_mode): ?>
    <script>
        window.onload = function() {
            window.print();
        };
    </script>
    </body>
    </html>
<?php else: ?>
    <?php include '../../../includes/footer.php'; ?>
<?php endif; ?>
