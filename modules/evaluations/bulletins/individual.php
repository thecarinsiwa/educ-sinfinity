<?php
/**
 * Module d'évaluations - Bulletin individuel
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

$page_title = 'Bulletin individuel';

// Récupérer l'année scolaire active
$current_year = $database->query("SELECT * FROM annees_scolaires WHERE status = 'active' LIMIT 1")->fetch();
if (!$current_year) {
    showMessage('error', 'Aucune année scolaire active trouvée.');
    redirectTo('../../../index.php');
}

// Récupérer les paramètres
$eleve_id = (int)($_GET['eleve'] ?? 0);
$periode = sanitizeInput($_GET['periode'] ?? '');

if (!$eleve_id || !$periode) {
    showMessage('error', 'Paramètres manquants pour afficher le bulletin.');
    redirectTo('index.php');
}

// Récupérer les informations de l'élève
$eleve = $database->query(
    "SELECT e.*, c.nom as classe_nom, c.niveau, c.section
     FROM eleves e
     JOIN inscriptions i ON e.id = i.eleve_id
     JOIN classes c ON i.classe_id = c.id
     WHERE e.id = ? AND i.annee_scolaire_id = ? AND i.status = 'inscrit'",
    [$eleve_id, $current_year['id']]
)->fetch();

if (!$eleve) {
    showMessage('error', 'Élève non trouvé ou non inscrit pour cette année.');
    redirectTo('index.php');
}

// Récupérer les notes de l'élève pour la période
$notes_detaillees = $database->query(
    "SELECT n.note, n.observation,
            ev.nom as evaluation_nom, ev.type_evaluation, ev.coefficient as eval_coefficient, 
            ev.note_max, ev.date_evaluation,
            m.nom as matiere_nom, m.coefficient as matiere_coefficient, m.code as matiere_code
     FROM notes n
     JOIN evaluations ev ON n.evaluation_id = ev.id
     JOIN matieres m ON ev.matiere_id = m.id
     WHERE n.eleve_id = ? AND ev.annee_scolaire_id = ? AND ev.periode = ?
     ORDER BY m.nom, ev.date_evaluation",
    [$eleve_id, $current_year['id'], $periode]
)->fetchAll();

// Calculer les moyennes par matière
$moyennes_matieres = [];
$notes_par_matiere = [];

foreach ($notes_detaillees as $note) {
    $matiere = $note['matiere_nom'];
    if (!isset($notes_par_matiere[$matiere])) {
        $notes_par_matiere[$matiere] = [
            'notes' => [],
            'coefficient' => $note['matiere_coefficient'],
            'code' => $note['matiere_code']
        ];
    }
    
    // Convertir la note sur 20
    $note_sur_20 = ($note['note'] / $note['note_max']) * 20;
    $notes_par_matiere[$matiere]['notes'][] = [
        'note' => $note_sur_20,
        'coefficient' => $note['eval_coefficient'],
        'evaluation' => $note['evaluation_nom'],
        'type' => $note['type_evaluation'],
        'date' => $note['date_evaluation'],
        'observation' => $note['observation']
    ];
}

// Calculer les moyennes
$moyenne_generale = 0;
$total_coefficients = 0;

foreach ($notes_par_matiere as $matiere => $data) {
    $somme_notes = 0;
    $somme_coef = 0;
    
    foreach ($data['notes'] as $note_info) {
        $somme_notes += $note_info['note'] * $note_info['coefficient'];
        $somme_coef += $note_info['coefficient'];
    }
    
    $moyenne_matiere = $somme_coef > 0 ? $somme_notes / $somme_coef : 0;
    $moyennes_matieres[$matiere] = [
        'moyenne' => $moyenne_matiere,
        'coefficient' => $data['coefficient'],
        'code' => $data['code'],
        'notes_detail' => $data['notes']
    ];
    
    $moyenne_generale += $moyenne_matiere * $data['coefficient'];
    $total_coefficients += $data['coefficient'];
}

$moyenne_generale = $total_coefficients > 0 ? $moyenne_generale / $total_coefficients : 0;

// Déterminer l'appréciation générale
$appreciation_generale = '';
$mention_couleur = '';
if ($moyenne_generale >= 16) {
    $appreciation_generale = 'Excellent';
    $mention_couleur = 'success';
} elseif ($moyenne_generale >= 14) {
    $appreciation_generale = 'Très bien';
    $mention_couleur = 'info';
} elseif ($moyenne_generale >= 12) {
    $appreciation_generale = 'Bien';
    $mention_couleur = 'primary';
} elseif ($moyenne_generale >= 10) {
    $appreciation_generale = 'Assez bien';
    $mention_couleur = 'warning';
} elseif ($moyenne_generale >= 8) {
    $appreciation_generale = 'Passable';
    $mention_couleur = 'warning';
} else {
    $appreciation_generale = 'Insuffisant';
    $mention_couleur = 'danger';
}

// Statistiques de la classe pour comparaison
$stats_classe = $database->query(
    "SELECT AVG(moyenne_eleve) as moyenne_classe, COUNT(*) as effectif
     FROM (
         SELECT AVG(n.note / ev.note_max * 20) as moyenne_eleve
         FROM notes n
         JOIN evaluations ev ON n.evaluation_id = ev.id
         JOIN inscriptions i ON n.eleve_id = i.eleve_id
         WHERE i.classe_id = (
             SELECT classe_id FROM inscriptions 
             WHERE eleve_id = ? AND annee_scolaire_id = ?
         ) AND ev.annee_scolaire_id = ? AND ev.periode = ?
         GROUP BY n.eleve_id
     ) as moyennes",
    [$eleve_id, $current_year['id'], $current_year['id'], $periode]
)->fetch();

include '../../../includes/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">
        <i class="fas fa-user-graduate me-2"></i>
        Bulletin individuel
    </h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <div class="btn-group me-2">
            <a href="index.php" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-1"></i>
                Retour à la liste
            </a>
        </div>
        <div class="btn-group">
            <a href="preview.php?eleve=<?php echo $eleve_id; ?>&periode=<?php echo $periode; ?>" 
               class="btn btn-info" target="_blank">
                <i class="fas fa-eye me-1"></i>
                Aperçu
            </a>
            <a href="download.php?eleve=<?php echo $eleve_id; ?>&periode=<?php echo $periode; ?>&format=print" 
               class="btn btn-success" target="_blank">
                <i class="fas fa-print me-1"></i>
                Imprimer
            </a>
            <a href="download.php?eleve=<?php echo $eleve_id; ?>&periode=<?php echo $periode; ?>&format=pdf" 
               class="btn btn-danger">
                <i class="fas fa-file-pdf me-1"></i>
                PDF
            </a>
        </div>
    </div>
</div>

<!-- Informations de l'élève -->
<div class="card mb-4">
    <div class="card-header bg-primary text-white">
        <h5 class="mb-0">
            <i class="fas fa-id-card me-2"></i>
            Informations de l'élève
        </h5>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-6">
                <table class="table table-borderless">
                    <tr>
                        <td><strong>Nom complet :</strong></td>
                        <td><?php echo htmlspecialchars(($eleve['nom'] ?? '') . ' ' . ($eleve['prenom'] ?? '')); ?></td>
                    </tr>
                    <tr>
                        <td><strong>Matricule :</strong></td>
                        <td><code><?php echo htmlspecialchars($eleve['numero_matricule'] ?? ''); ?></code></td>
                    </tr>
                    <tr>
                        <td><strong>Date de naissance :</strong></td>
                        <td><?php echo $eleve['date_naissance'] ? date('d/m/Y', strtotime($eleve['date_naissance'])) : 'Non renseignée'; ?></td>
                    </tr>
                    <tr>
                        <td><strong>Sexe :</strong></td>
                        <td><?php echo ucfirst($eleve['sexe']); ?></td>
                    </tr>
                </table>
            </div>
            <div class="col-md-6">
                <table class="table table-borderless">
                    <tr>
                        <td><strong>Classe :</strong></td>
                        <td><?php echo htmlspecialchars($eleve['classe_nom'] ?? ''); ?></td>
                    </tr>
                    <tr>
                        <td><strong>Niveau :</strong></td>
                        <td><?php echo ucfirst($eleve['niveau']); ?></td>
                    </tr>
                    <tr>
                        <td><strong>Section :</strong></td>
                        <td><?php echo $eleve['section'] ? htmlspecialchars($eleve['section']) : 'Non spécifiée'; ?></td>
                    </tr>
                    <tr>
                        <td><strong>Période :</strong></td>
                        <td><span class="badge bg-info"><?php echo str_replace('_', ' ', ucfirst($periode)); ?></span></td>
                    </tr>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Résultats scolaires -->
<div class="row mb-4">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-chart-bar me-2"></i>
                    Résultats par matière
                </h5>
            </div>
            <div class="card-body">
                <?php if (!empty($moyennes_matieres)): ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Matière</th>
                                    <th>Code</th>
                                    <th>Coef.</th>
                                    <th>Évaluations</th>
                                    <th>Moyenne</th>
                                    <th>Appréciation</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($moyennes_matieres as $matiere => $data): ?>
                                    <?php
                                    $moyenne = $data['moyenne'];
                                    $color = $moyenne >= 16 ? 'success' : ($moyenne >= 14 ? 'info' : ($moyenne >= 12 ? 'primary' : ($moyenne >= 10 ? 'warning' : 'danger')));
                                    
                                    $appreciation = '';
                                    if ($moyenne >= 16) $appreciation = 'Excellent';
                                    elseif ($moyenne >= 14) $appreciation = 'Très bien';
                                    elseif ($moyenne >= 12) $appreciation = 'Bien';
                                    elseif ($moyenne >= 10) $appreciation = 'Assez bien';
                                    elseif ($moyenne >= 8) $appreciation = 'Passable';
                                    else $appreciation = 'Insuffisant';
                                    ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($matiere ?? ''); ?></strong></td>
                                        <td><code><?php echo htmlspecialchars($data['code'] ?? ''); ?></code></td>
                                        <td class="text-center">
                                            <span class="badge bg-secondary"><?php echo $data['coefficient']; ?></span>
                                        </td>
                                        <td class="text-center"><?php echo count($data['notes_detail']); ?></td>
                                        <td class="text-center">
                                            <span class="badge bg-<?php echo $color; ?> fs-6">
                                                <?php echo round($moyenne, 2); ?>/20
                                            </span>
                                        </td>
                                        <td class="text-center">
                                            <span class="text-<?php echo $color; ?>"><?php echo $appreciation; ?></span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot>
                                <tr class="table-primary">
                                    <td colspan="4"><strong>MOYENNE GÉNÉRALE</strong></td>
                                    <td class="text-center">
                                        <span class="badge bg-<?php echo $mention_couleur; ?> fs-5">
                                            <?php echo round($moyenne_generale, 2); ?>/20
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        <strong class="text-<?php echo $mention_couleur; ?>">
                                            <?php echo $appreciation_generale; ?>
                                        </strong>
                                    </td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="text-center py-4">
                        <i class="fas fa-exclamation-triangle fa-3x text-warning mb-3"></i>
                        <h5 class="text-muted">Aucune note disponible</h5>
                        <p class="text-muted">
                            Aucune note n'a été saisie pour cet élève durant cette période.
                        </p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <div class="col-lg-4">
        <!-- Statistiques -->
        <div class="card mb-3">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-chart-pie me-2"></i>
                    Statistiques
                </h5>
            </div>
            <div class="card-body">
                <div class="text-center mb-3">
                    <h2 class="text-<?php echo $mention_couleur; ?>">
                        <?php echo round($moyenne_generale, 2); ?>/20
                    </h2>
                    <p class="text-<?php echo $mention_couleur; ?> mb-0">
                        <strong><?php echo $appreciation_generale; ?></strong>
                    </p>
                </div>
                
                <hr>
                
                <table class="table table-sm table-borderless">
                    <tr>
                        <td>Matières évaluées :</td>
                        <td><strong><?php echo count($moyennes_matieres); ?></strong></td>
                    </tr>
                    <tr>
                        <td>Total évaluations :</td>
                        <td><strong><?php echo count($notes_detaillees); ?></strong></td>
                    </tr>
                    <tr>
                        <td>Moyenne de classe :</td>
                        <td><strong><?php echo $stats_classe['moyenne_classe'] ? round($stats_classe['moyenne_classe'], 2) : 'N/A'; ?>/20</strong></td>
                    </tr>
                    <tr>
                        <td>Effectif classe :</td>
                        <td><strong><?php echo $stats_classe['effectif'] ?? 'N/A'; ?> élèves</strong></td>
                    </tr>
                    <?php if ($stats_classe['moyenne_classe']): ?>
                        <tr>
                            <td>Écart à la moyenne :</td>
                            <td>
                                <?php 
                                $ecart = $moyenne_generale - $stats_classe['moyenne_classe'];
                                $ecart_color = $ecart >= 0 ? 'success' : 'danger';
                                ?>
                                <strong class="text-<?php echo $ecart_color; ?>">
                                    <?php echo ($ecart >= 0 ? '+' : '') . round($ecart, 2); ?>
                                </strong>
                            </td>
                        </tr>
                    <?php endif; ?>
                </table>
            </div>
        </div>
        
        <!-- Actions -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-cogs me-2"></i>
                    Actions
                </h5>
            </div>
            <div class="card-body">
                <div class="d-grid gap-2">
                    <a href="preview.php?eleve=<?php echo $eleve_id; ?>&periode=<?php echo $periode; ?>" 
                       class="btn btn-info" target="_blank">
                        <i class="fas fa-eye me-1"></i>
                        Aperçu du bulletin
                    </a>
                    <a href="download.php?eleve=<?php echo $eleve_id; ?>&periode=<?php echo $periode; ?>&format=print" 
                       class="btn btn-success" target="_blank">
                        <i class="fas fa-print me-1"></i>
                        Imprimer le bulletin
                    </a>
                    <a href="download.php?eleve=<?php echo $eleve_id; ?>&periode=<?php echo $periode; ?>&format=pdf" 
                       class="btn btn-danger">
                        <i class="fas fa-file-pdf me-1"></i>
                        Télécharger PDF
                    </a>
                    <hr>
                    <a href="../notes/entry.php?eleve_id=<?php echo $eleve_id; ?>" 
                       class="btn btn-outline-primary">
                        <i class="fas fa-edit me-1"></i>
                        Modifier les notes
                    </a>
                    <a href="individual.php?eleve=<?php echo $eleve_id; ?>&periode=<?php echo $periode === '1er_trimestre' ? '2eme_trimestre' : ($periode === '2eme_trimestre' ? '3eme_trimestre' : '1er_trimestre'); ?>" 
                       class="btn btn-outline-info">
                        <i class="fas fa-calendar me-1"></i>
                        Autre période
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Détail des évaluations -->
<?php if (!empty($notes_detaillees)): ?>
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">
                <i class="fas fa-list-alt me-2"></i>
                Détail des évaluations (<?php echo count($notes_detaillees); ?>)
            </h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-sm table-hover">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Matière</th>
                            <th>Évaluation</th>
                            <th>Type</th>
                            <th>Note</th>
                            <th>Note/20</th>
                            <th>Coef.</th>
                            <th>Observation</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($notes_detaillees as $note): ?>
                            <?php
                            $note_sur_20 = ($note['note'] / $note['note_max']) * 20;
                            $color = $note_sur_20 >= 16 ? 'success' : ($note_sur_20 >= 14 ? 'info' : ($note_sur_20 >= 12 ? 'primary' : ($note_sur_20 >= 10 ? 'warning' : 'danger')));
                            ?>
                            <tr>
                                <td><?php echo date('d/m/Y', strtotime($note['date_evaluation'])); ?></td>
                                <td><?php echo htmlspecialchars($note['matiere_nom'] ?? ''); ?></td>
                                <td><?php echo htmlspecialchars($note['evaluation_nom'] ?? ''); ?></td>
                                <td>
                                    <span class="badge bg-secondary">
                                        <?php echo ucfirst($note['type_evaluation']); ?>
                                    </span>
                                </td>
                                <td class="text-center">
                                    <?php echo $note['note']; ?>/<?php echo $note['note_max']; ?>
                                </td>
                                <td class="text-center">
                                    <span class="badge bg-<?php echo $color; ?>">
                                        <?php echo round($note_sur_20, 2); ?>/20
                                    </span>
                                </td>
                                <td class="text-center"><?php echo $note['eval_coefficient']; ?></td>
                                <td>
                                    <?php if ($note['observation']): ?>
                                        <small class="text-muted"><?php echo htmlspecialchars($note['observation'] ?? ''); ?></small>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php include '../../../includes/footer.php'; ?>
