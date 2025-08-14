<?php
/**
 * Module d'évaluations - Rapport d'évaluation
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
$evaluation_id = (int)($_GET['id'] ?? 0);
$format = sanitizeInput($_GET['format'] ?? 'view');

if (!$evaluation_id) {
    showMessage('error', 'ID d\'évaluation manquant.');
    redirectTo('index.php');
}

// Récupérer l'année scolaire active
$current_year = $database->query("SELECT * FROM annees_scolaires WHERE status = 'active' LIMIT 1")->fetch();
if (!$current_year) {
    showMessage('error', 'Aucune année scolaire active trouvée.');
    redirectTo('../../../index.php');
}

// Récupérer les détails de l'évaluation
$evaluation = $database->query(
    "SELECT e.*, 
            m.nom as matiere_nom, m.code as matiere_code, m.coefficient as matiere_coefficient,
            c.nom as classe_nom, c.niveau, c.section,
            CONCAT(p.nom, ' ', p.prenom) as enseignant_nom,
            ans.annee as annee_scolaire
     FROM evaluations e
     JOIN matieres m ON e.matiere_id = m.id
     JOIN classes c ON e.classe_id = c.id
     LEFT JOIN personnel p ON e.enseignant_id = p.id
     JOIN annees_scolaires ans ON e.annee_scolaire_id = ans.id
     WHERE e.id = ?",
    [$evaluation_id]
)->fetch();

if (!$evaluation) {
    showMessage('error', 'Évaluation non trouvée.');
    redirectTo('index.php');
}

// Récupérer les notes avec détails des élèves
$notes = $database->query(
    "SELECT n.*, e.nom, e.prenom, e.numero_matricule, e.date_naissance, e.sexe
     FROM notes n
     JOIN eleves e ON n.eleve_id = e.id
     WHERE n.evaluation_id = ?
     ORDER BY e.nom, e.prenom",
    [$evaluation_id]
)->fetchAll();

// Récupérer tous les élèves de la classe
$eleves_classe = $database->query(
    "SELECT e.id, e.nom, e.prenom, e.numero_matricule
     FROM eleves e
     JOIN inscriptions i ON e.id = i.eleve_id
     WHERE i.classe_id = ? AND i.status = 'inscrit' AND i.annee_scolaire_id = ?
     ORDER BY e.nom, e.prenom",
    [$evaluation['classe_id'], $evaluation['annee_scolaire_id']]
)->fetchAll();

// Calculer les statistiques
$stats = [
    'total_eleves' => count($eleves_classe),
    'notes_saisies' => count($notes),
    'notes_manquantes' => count($eleves_classe) - count($notes),
    'moyenne_classe' => 0,
    'note_min' => 0,
    'note_max' => 0,
    'nb_admis' => 0,
    'nb_echecs' => 0,
    'taux_reussite' => 0
];

if (!empty($notes)) {
    $notes_values = array_column($notes, 'note');
    $stats['moyenne_classe'] = round(array_sum($notes_values) / count($notes_values), 2);
    $stats['note_min'] = min($notes_values);
    $stats['note_max'] = max($notes_values);
    
    // Calculer admis/échecs (seuil à 50% de la note max)
    $seuil_reussite = $evaluation['note_max'] / 2;
    foreach ($notes_values as $note) {
        if ($note >= $seuil_reussite) {
            $stats['nb_admis']++;
        } else {
            $stats['nb_echecs']++;
        }
    }
    
    $stats['taux_reussite'] = round(($stats['nb_admis'] / count($notes)) * 100, 1);
}

// Distribution des notes par tranches
$distribution = [
    'excellent' => 0,    // >= 16/20
    'tres_bien' => 0,    // 14-16/20
    'bien' => 0,         // 12-14/20
    'assez_bien' => 0,   // 10-12/20
    'passable' => 0,     // 8-10/20
    'insuffisant' => 0   // < 8/20
];

foreach ($notes as $note) {
    $note_sur_20 = ($note['note'] / $evaluation['note_max']) * 20;
    
    if ($note_sur_20 >= 16) $distribution['excellent']++;
    elseif ($note_sur_20 >= 14) $distribution['tres_bien']++;
    elseif ($note_sur_20 >= 12) $distribution['bien']++;
    elseif ($note_sur_20 >= 10) $distribution['assez_bien']++;
    elseif ($note_sur_20 >= 8) $distribution['passable']++;
    else $distribution['insuffisant']++;
}

// Gestion des différents formats
if ($format === 'pdf') {
    // Pour le PDF, on redirige vers la vue avec un paramètre pour impression
    header("Location: evaluation_report.php?id=$evaluation_id&format=view&print=1");
    exit;
} elseif ($format === 'excel') {
    // Pour Excel, on génère un fichier CSV simple
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="rapport_evaluation_' . $evaluation_id . '.csv"');
    
    $output = fopen('php://output', 'w');
    
    // En-têtes du fichier
    fputcsv($output, ['Rapport d\'évaluation - ' . $evaluation['nom']]);
    fputcsv($output, ['']);
    fputcsv($output, ['Classe', $evaluation['classe_nom']]);
    fputcsv($output, ['Matière', $evaluation['matiere_nom']]);
    fputcsv($output, ['Date', date('d/m/Y', strtotime($evaluation['date_evaluation']))]);
    fputcsv($output, ['Note maximale', $evaluation['note_max']]);
    fputcsv($output, ['']);
    
    // Statistiques
    fputcsv($output, ['STATISTIQUES']);
    fputcsv($output, ['Total élèves', $stats['total_eleves']]);
    fputcsv($output, ['Notes saisies', $stats['notes_saisies']]);
    fputcsv($output, ['Moyenne classe', $stats['moyenne_classe']]);
    fputcsv($output, ['Note minimale', $stats['note_min']]);
    fputcsv($output, ['Note maximale', $stats['note_max']]);
    fputcsv($output, ['Taux de réussite', $stats['taux_reussite'] . '%']);
    fputcsv($output, ['']);
    
    // En-têtes des notes
    fputcsv($output, ['NOTES DES ÉLÈVES']);
    fputcsv($output, ['Matricule', 'Nom', 'Prénom', 'Note', 'Note/20', 'Appréciation', 'Observation']);
    
    // Données des notes
    foreach ($notes as $note) {
        $note_sur_20 = round(($note['note'] / $evaluation['note_max']) * 20, 2);
        
        if ($note_sur_20 >= 16) $appreciation = 'Excellent';
        elseif ($note_sur_20 >= 14) $appreciation = 'Très bien';
        elseif ($note_sur_20 >= 12) $appreciation = 'Bien';
        elseif ($note_sur_20 >= 10) $appreciation = 'Assez bien';
        elseif ($note_sur_20 >= 8) $appreciation = 'Passable';
        else $appreciation = 'Insuffisant';
        
        fputcsv($output, [
            $note['numero_matricule'],
            $note['nom'],
            $note['prenom'],
            $note['note'] . '/' . $evaluation['note_max'],
            $note_sur_20 . '/20',
            $appreciation,
            $note['observation'] ?? ''
        ]);
    }
    
    fclose($output);
    exit;
}

// Format par défaut : vue HTML
$page_title = 'Rapport d\'évaluation : ' . $evaluation['nom'];
$print_mode = isset($_GET['print']);

if ($print_mode) {
    // Mode impression - CSS spécial
    echo "<!DOCTYPE html>
    <html>
    <head>
        <title>$page_title</title>
        <meta charset='utf-8'>
        <style>
            body { font-family: Arial, sans-serif; font-size: 12px; margin: 20px; }
            .header { text-align: center; margin-bottom: 30px; border-bottom: 2px solid #333; padding-bottom: 10px; }
            .info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px; }
            .stats-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; margin: 20px 0; }
            .stat-box { border: 1px solid #ddd; padding: 10px; text-align: center; }
            table { width: 100%; border-collapse: collapse; margin: 20px 0; }
            th, td { border: 1px solid #ddd; padding: 8px; text-align: left; font-size: 11px; }
            th { background-color: #f5f5f5; font-weight: bold; }
            .text-center { text-align: center; }
            .badge { padding: 2px 6px; border-radius: 3px; font-size: 10px; }
            .badge-success { background-color: #d4edda; color: #155724; }
            .badge-warning { background-color: #fff3cd; color: #856404; }
            .badge-danger { background-color: #f8d7da; color: #721c24; }
            @media print {
                body { margin: 0; }
                .no-print { display: none; }
            }
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
        <i class="fas fa-file-alt me-2"></i>
        Rapport d'évaluation
    </h1>
    <div class="btn-toolbar mb-2 mb-md-0 no-print">
        <div class="btn-group me-2">
            <a href="index.php" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-1"></i>
                Retour
            </a>
            <a href="../evaluations/view.php?id=<?php echo $evaluation_id; ?>" class="btn btn-outline-info">
                <i class="fas fa-eye me-1"></i>
                Voir évaluation
            </a>
        </div>
        <div class="btn-group">
            <a href="?id=<?php echo $evaluation_id; ?>&format=view&print=1" class="btn btn-primary" target="_blank">
                <i class="fas fa-print me-1"></i>
                Imprimer
            </a>
            <a href="?id=<?php echo $evaluation_id; ?>&format=excel" class="btn btn-success">
                <i class="fas fa-file-excel me-1"></i>
                Excel
            </a>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- En-tête du rapport -->
<div class="<?php echo $print_mode ? 'header' : 'card mb-4'; ?>">
    <?php if (!$print_mode): ?><div class="card-body"><?php endif; ?>
        <div class="text-center">
            <h2><?php echo htmlspecialchars($evaluation['nom']); ?></h2>
            <h4 class="text-muted">Rapport d'évaluation détaillé</h4>
        </div>
        
        <div class="<?php echo $print_mode ? 'info-grid' : 'row mt-4'; ?>">
            <div class="<?php echo $print_mode ? '' : 'col-md-6'; ?>">
                <table class="table table-borderless">
                    <tr>
                        <td><strong>Classe :</strong></td>
                        <td><?php echo htmlspecialchars($evaluation['classe_nom']); ?></td>
                    </tr>
                    <tr>
                        <td><strong>Matière :</strong></td>
                        <td><?php echo htmlspecialchars($evaluation['matiere_nom']); ?></td>
                    </tr>
                    <tr>
                        <td><strong>Enseignant :</strong></td>
                        <td><?php echo htmlspecialchars($evaluation['enseignant_nom']); ?></td>
                    </tr>
                    <tr>
                        <td><strong>Type :</strong></td>
                        <td><?php echo ucfirst($evaluation['type_evaluation']); ?></td>
                    </tr>
                </table>
            </div>
            <div class="<?php echo $print_mode ? '' : 'col-md-6'; ?>">
                <table class="table table-borderless">
                    <tr>
                        <td><strong>Date :</strong></td>
                        <td><?php echo date('d/m/Y', strtotime($evaluation['date_evaluation'])); ?></td>
                    </tr>
                    <tr>
                        <td><strong>Période :</strong></td>
                        <td><?php echo str_replace('_', ' ', ucfirst($evaluation['periode'])); ?></td>
                    </tr>
                    <tr>
                        <td><strong>Note max :</strong></td>
                        <td><?php echo $evaluation['note_max']; ?> points</td>
                    </tr>
                    <tr>
                        <td><strong>Coefficient :</strong></td>
                        <td><?php echo $evaluation['coefficient']; ?></td>
                    </tr>
                </table>
            </div>
        </div>
    <?php if (!$print_mode): ?></div><?php endif; ?>
</div>

<!-- Statistiques -->
<div class="<?php echo $print_mode ? 'stats-grid' : 'row mb-4'; ?>">
    <?php
    $stat_items = [
        ['label' => 'Total élèves', 'value' => $stats['total_eleves'], 'icon' => 'users'],
        ['label' => 'Notes saisies', 'value' => $stats['notes_saisies'], 'icon' => 'edit'],
        ['label' => 'Moyenne classe', 'value' => $stats['moyenne_classe'] . '/20', 'icon' => 'chart-line'],
        ['label' => 'Note minimale', 'value' => $stats['note_min'], 'icon' => 'arrow-down'],
        ['label' => 'Note maximale', 'value' => $stats['note_max'], 'icon' => 'arrow-up'],
        ['label' => 'Taux de réussite', 'value' => $stats['taux_reussite'] . '%', 'icon' => 'percentage']
    ];
    
    foreach ($stat_items as $stat): ?>
        <div class="<?php echo $print_mode ? 'stat-box' : 'col-lg-2 col-md-4 mb-3'; ?>">
            <?php if (!$print_mode): ?>
                <div class="card text-center">
                    <div class="card-body">
                        <i class="fas fa-<?php echo $stat['icon']; ?> fa-2x text-primary mb-2"></i>
                        <h4 class="mb-0"><?php echo $stat['value']; ?></h4>
                        <p class="mb-0 small"><?php echo $stat['label']; ?></p>
                    </div>
                </div>
            <?php else: ?>
                <strong><?php echo $stat['label']; ?></strong><br>
                <?php echo $stat['value']; ?>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>
</div>

<!-- Liste des notes -->
<div class="<?php echo $print_mode ? '' : 'card'; ?>">
    <?php if (!$print_mode): ?>
        <div class="card-header">
            <h5 class="mb-0">
                <i class="fas fa-list-ol me-2"></i>
                Notes des élèves (<?php echo count($notes); ?>/<?php echo $stats['total_eleves']; ?>)
            </h5>
        </div>
        <div class="card-body">
    <?php else: ?>
        <h3>Notes des élèves</h3>
    <?php endif; ?>
    
    <?php if (!empty($notes)): ?>
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>N°</th>
                        <th>Matricule</th>
                        <th>Élève</th>
                        <th>Note</th>
                        <th>Note/20</th>
                        <th>Appréciation</th>
                        <?php if (!$print_mode): ?><th>Observation</th><?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($notes as $index => $note): ?>
                        <tr>
                            <td><?php echo $index + 1; ?></td>
                            <td><code><?php echo htmlspecialchars($note['numero_matricule']); ?></code></td>
                            <td><strong><?php echo htmlspecialchars($note['nom'] . ' ' . $note['prenom']); ?></strong></td>
                            <td class="text-center">
                                <span class="badge bg-primary">
                                    <?php echo $note['note']; ?>/<?php echo $evaluation['note_max']; ?>
                                </span>
                            </td>
                            <td class="text-center">
                                <?php 
                                $note_sur_20 = round(($note['note'] / $evaluation['note_max']) * 20, 2);
                                $color_note = $note_sur_20 >= 10 ? 'success' : 'danger';
                                ?>
                                <span class="badge bg-<?php echo $color_note; ?>">
                                    <?php echo $note_sur_20; ?>/20
                                </span>
                            </td>
                            <td class="text-center">
                                <?php
                                if ($note_sur_20 >= 16) echo '<span class="badge badge-success">Excellent</span>';
                                elseif ($note_sur_20 >= 14) echo '<span class="badge badge-success">Très bien</span>';
                                elseif ($note_sur_20 >= 12) echo '<span class="badge badge-warning">Bien</span>';
                                elseif ($note_sur_20 >= 10) echo '<span class="badge badge-warning">Assez bien</span>';
                                elseif ($note_sur_20 >= 8) echo '<span class="badge badge-warning">Passable</span>';
                                else echo '<span class="badge badge-danger">Insuffisant</span>';
                                ?>
                            </td>
                            <?php if (!$print_mode): ?>
                                <td>
                                    <?php if ($note['observation']): ?>
                                        <small><?php echo htmlspecialchars($note['observation']); ?></small>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <div class="text-center py-4">
            <i class="fas fa-clipboard fa-3x text-muted mb-3"></i>
            <h5 class="text-muted">Aucune note saisie</h5>
            <p class="text-muted">Les notes pour cette évaluation n'ont pas encore été saisies.</p>
        </div>
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
