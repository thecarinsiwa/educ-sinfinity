<?php
/**
 * Module d'évaluations - Voir les détails d'une évaluation
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

// Récupérer l'ID de l'évaluation
$evaluation_id = (int)($_GET['id'] ?? 0);
if (!$evaluation_id) {
    showMessage('error', 'ID d\'évaluation manquant.');
    redirectTo('index.php');
}

// Récupérer l'année scolaire active
$current_year = $database->query("SELECT * FROM annees_scolaires WHERE status = 'active' LIMIT 1")->fetch();

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

$page_title = 'Détails de l\'évaluation : ' . $evaluation['nom'];

// Récupérer les notes de cette évaluation
$notes = $database->query(
    "SELECT n.*, e.nom, e.prenom, e.numero_matricule
     FROM notes n
     JOIN eleves e ON n.eleve_id = e.id
     WHERE n.evaluation_id = ?
     ORDER BY e.nom, e.prenom",
    [$evaluation_id]
)->fetchAll();

// Récupérer tous les élèves de la classe (pour voir qui n'a pas encore de note)
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
    'nb_echecs' => 0
];

if (!empty($notes)) {
    $notes_values = array_column($notes, 'note');
    $stats['moyenne_classe'] = round(array_sum($notes_values) / count($notes_values), 2);
    $stats['note_min'] = min($notes_values);
    $stats['note_max'] = max($notes_values);
    
    // Calculer admis/échecs (seuil à 10/20)
    $seuil_reussite = $evaluation['note_max'] / 2;
    foreach ($notes_values as $note) {
        if ($note >= $seuil_reussite) {
            $stats['nb_admis']++;
        } else {
            $stats['nb_echecs']++;
        }
    }
}

include '../../../includes/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">
        <i class="fas fa-clipboard-check me-2"></i>
        <?php echo htmlspecialchars($evaluation['nom']); ?>
    </h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <div class="btn-group me-2">
            <a href="index.php" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-1"></i>
                Retour à la liste
            </a>
        </div>
        <?php if (checkPermission('evaluations')): ?>
            <div class="btn-group">
                <a href="../notes/entry.php?evaluation_id=<?php echo $evaluation_id; ?>" class="btn btn-success">
                    <i class="fas fa-edit me-1"></i>
                    Saisir les notes
                </a>
                <a href="edit.php?id=<?php echo $evaluation_id; ?>" class="btn btn-primary">
                    <i class="fas fa-pen me-1"></i>
                    Modifier
                </a>
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="row">
    <div class="col-lg-8">
        <!-- Informations générales -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-info-circle me-2"></i>
                    Informations générales
                </h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <table class="table table-borderless">
                            <tr>
                                <td><strong>Nom :</strong></td>
                                <td><?php echo htmlspecialchars($evaluation['nom']); ?></td>
                            </tr>
                            <tr>
                                <td><strong>Type :</strong></td>
                                <td>
                                    <?php
                                    $type_colors = [
                                        'interrogation' => 'info',
                                        'devoir' => 'primary',
                                        'examen' => 'warning',
                                        'composition' => 'danger'
                                    ];
                                    $color = $type_colors[$evaluation['type_evaluation']] ?? 'secondary';
                                    ?>
                                    <span class="badge bg-<?php echo $color; ?>">
                                        <?php echo ucfirst($evaluation['type_evaluation']); ?>
                                    </span>
                                </td>
                            </tr>
                            <tr>
                                <td><strong>Matière :</strong></td>
                                <td>
                                    <strong><?php echo htmlspecialchars($evaluation['matiere_nom']); ?></strong>
                                    <?php if ($evaluation['matiere_code']): ?>
                                        <span class="badge bg-secondary ms-1"><?php echo htmlspecialchars($evaluation['matiere_code']); ?></span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <tr>
                                <td><strong>Classe :</strong></td>
                                <td>
                                    <strong><?php echo htmlspecialchars($evaluation['classe_nom']); ?></strong>
                                    <span class="badge bg-info ms-1"><?php echo ucfirst($evaluation['niveau']); ?></span>
                                    <?php if ($evaluation['section']): ?>
                                        <span class="badge bg-secondary ms-1"><?php echo htmlspecialchars($evaluation['section']); ?></span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <tr>
                                <td><strong>Enseignant :</strong></td>
                                <td><?php echo htmlspecialchars($evaluation['enseignant_nom']); ?></td>
                            </tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <table class="table table-borderless">
                            <tr>
                                <td><strong>Date :</strong></td>
                                <td><?php echo date('d/m/Y', strtotime($evaluation['date_evaluation'])); ?></td>
                            </tr>
                            <?php if ($evaluation['heure_debut'] && $evaluation['heure_fin']): ?>
                            <tr>
                                <td><strong>Horaire :</strong></td>
                                <td>
                                    <?php echo substr($evaluation['heure_debut'], 0, 5); ?> - 
                                    <?php echo substr($evaluation['heure_fin'], 0, 5); ?>
                                    <?php if ($evaluation['duree_minutes']): ?>
                                        <small class="text-muted">(<?php echo $evaluation['duree_minutes']; ?> min)</small>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endif; ?>
                            <tr>
                                <td><strong>Période :</strong></td>
                                <td>
                                    <span class="badge bg-success">
                                        <?php echo str_replace('_', ' ', ucfirst($evaluation['periode'])); ?>
                                    </span>
                                </td>
                            </tr>
                            <tr>
                                <td><strong>Note max :</strong></td>
                                <td><span class="badge bg-primary"><?php echo $evaluation['note_max']; ?> pts</span></td>
                            </tr>
                            <tr>
                                <td><strong>Coefficient :</strong></td>
                                <td><span class="badge bg-info"><?php echo $evaluation['coefficient']; ?></span></td>
                            </tr>
                            <?php if ($evaluation['status']): ?>
                            <tr>
                                <td><strong>Statut :</strong></td>
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
                                        <?php echo ucfirst(str_replace('_', ' ', $evaluation['status'])); ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endif; ?>
                        </table>
                    </div>
                </div>
                
                <?php if ($evaluation['description']): ?>
                    <hr>
                    <h6><i class="fas fa-align-left me-2"></i>Description</h6>
                    <p class="text-muted"><?php echo nl2br(htmlspecialchars($evaluation['description'])); ?></p>
                <?php endif; ?>
                
                <?php if ($evaluation['consignes']): ?>
                    <hr>
                    <h6><i class="fas fa-list-ul me-2"></i>Consignes</h6>
                    <p class="text-muted"><?php echo nl2br(htmlspecialchars($evaluation['consignes'])); ?></p>
                <?php endif; ?>
                
                <?php if ($evaluation['bareme']): ?>
                    <hr>
                    <h6><i class="fas fa-calculator me-2"></i>Barème</h6>
                    <p class="text-muted"><?php echo nl2br(htmlspecialchars($evaluation['bareme'])); ?></p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Liste des notes -->
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">
                    <i class="fas fa-list-ol me-2"></i>
                    Notes des élèves (<?php echo count($notes); ?>/<?php echo $stats['total_eleves']; ?>)
                </h5>
                <?php if (checkPermission('evaluations')): ?>
                    <a href="../notes/entry.php?evaluation_id=<?php echo $evaluation_id; ?>" class="btn btn-sm btn-success">
                        <i class="fas fa-plus me-1"></i>
                        Saisir/Modifier notes
                    </a>
                <?php endif; ?>
            </div>
            <div class="card-body">
                <?php if (!empty($notes)): ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Matricule</th>
                                    <th>Élève</th>
                                    <th>Note</th>
                                    <th>Note/20</th>
                                    <th>Appréciation</th>
                                    <th>Observation</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($notes as $note): ?>
                                    <tr>
                                        <td><code><?php echo htmlspecialchars($note['numero_matricule']); ?></code></td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($note['nom'] . ' ' . $note['prenom']); ?></strong>
                                        </td>
                                        <td>
                                            <span class="badge bg-primary fs-6">
                                                <?php echo $note['note']; ?>/<?php echo $evaluation['note_max']; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php 
                                            $note_sur_20 = round(($note['note'] / $evaluation['note_max']) * 20, 2);
                                            $color_note = $note_sur_20 >= 10 ? 'success' : 'danger';
                                            ?>
                                            <span class="badge bg-<?php echo $color_note; ?>">
                                                <?php echo $note_sur_20; ?>/20
                                            </span>
                                        </td>
                                        <td>
                                            <?php
                                            if ($note_sur_20 >= 16) echo '<span class="text-success">Très bien</span>';
                                            elseif ($note_sur_20 >= 14) echo '<span class="text-info">Bien</span>';
                                            elseif ($note_sur_20 >= 12) echo '<span class="text-primary">Assez bien</span>';
                                            elseif ($note_sur_20 >= 10) echo '<span class="text-warning">Passable</span>';
                                            else echo '<span class="text-danger">Insuffisant</span>';
                                            ?>
                                        </td>
                                        <td>
                                            <?php if ($note['observation']): ?>
                                                <small class="text-muted"><?php echo htmlspecialchars($note['observation']); ?></small>
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
                    <div class="text-center py-4">
                        <i class="fas fa-clipboard fa-3x text-muted mb-3"></i>
                        <h5 class="text-muted">Aucune note saisie</h5>
                        <p class="text-muted">Les notes pour cette évaluation n'ont pas encore été saisies.</p>
                        <?php if (checkPermission('evaluations')): ?>
                            <a href="../notes/entry.php?evaluation_id=<?php echo $evaluation_id; ?>" class="btn btn-primary">
                                <i class="fas fa-edit me-1"></i>
                                Commencer la saisie
                            </a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <div class="col-lg-4">
        <!-- Statistiques -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-chart-bar me-2"></i>
                    Statistiques
                </h5>
            </div>
            <div class="card-body">
                <div class="row text-center">
                    <div class="col-6">
                        <h3 class="text-primary"><?php echo $stats['total_eleves']; ?></h3>
                        <p class="mb-0 small">Élèves inscrits</p>
                    </div>
                    <div class="col-6">
                        <h3 class="text-success"><?php echo $stats['notes_saisies']; ?></h3>
                        <p class="mb-0 small">Notes saisies</p>
                    </div>
                </div>
                
                <?php if ($stats['notes_manquantes'] > 0): ?>
                    <div class="alert alert-warning mt-3">
                        <small>
                            <i class="fas fa-exclamation-triangle me-1"></i>
                            <?php echo $stats['notes_manquantes']; ?> note(s) manquante(s)
                        </small>
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($notes)): ?>
                    <hr>
                    <div class="row text-center">
                        <div class="col-4">
                            <h4 class="text-info"><?php echo $stats['moyenne_classe']; ?></h4>
                            <p class="mb-0 small">Moyenne</p>
                        </div>
                        <div class="col-4">
                            <h4 class="text-danger"><?php echo $stats['note_min']; ?></h4>
                            <p class="mb-0 small">Min</p>
                        </div>
                        <div class="col-4">
                            <h4 class="text-success"><?php echo $stats['note_max']; ?></h4>
                            <p class="mb-0 small">Max</p>
                        </div>
                    </div>
                    
                    <hr>
                    
                    <div class="row text-center">
                        <div class="col-6">
                            <h4 class="text-success"><?php echo $stats['nb_admis']; ?></h4>
                            <p class="mb-0 small">Admis</p>
                        </div>
                        <div class="col-6">
                            <h4 class="text-danger"><?php echo $stats['nb_echecs']; ?></h4>
                            <p class="mb-0 small">Échecs</p>
                        </div>
                    </div>
                    
                    <?php if ($stats['notes_saisies'] > 0): ?>
                        <div class="mt-3">
                            <?php 
                            $taux_reussite = round(($stats['nb_admis'] / $stats['notes_saisies']) * 100, 1);
                            $color_taux = $taux_reussite >= 70 ? 'success' : ($taux_reussite >= 50 ? 'warning' : 'danger');
                            ?>
                            <div class="progress">
                                <div class="progress-bar bg-<?php echo $color_taux; ?>" 
                                     style="width: <?php echo $taux_reussite; ?>%">
                                    <?php echo $taux_reussite; ?>% de réussite
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Actions rapides -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-tools me-2"></i>
                    Actions rapides
                </h5>
            </div>
            <div class="card-body">
                <div class="d-grid gap-2">
                    <?php if (checkPermission('evaluations')): ?>
                        <a href="../notes/entry.php?evaluation_id=<?php echo $evaluation_id; ?>" class="btn btn-success btn-sm">
                            <i class="fas fa-edit me-1"></i>
                            Saisir/Modifier les notes
                        </a>
                        
                        <a href="edit.php?id=<?php echo $evaluation_id; ?>" class="btn btn-primary btn-sm">
                            <i class="fas fa-pen me-1"></i>
                            Modifier l'évaluation
                        </a>
                    <?php endif; ?>
                    
                    <?php if (!empty($notes)): ?>
                        <a href="../reports/evaluation.php?id=<?php echo $evaluation_id; ?>" class="btn btn-outline-info btn-sm">
                            <i class="fas fa-file-pdf me-1"></i>
                            Rapport PDF
                        </a>
                        
                        <a href="../exports/notes.php?evaluation_id=<?php echo $evaluation_id; ?>" class="btn btn-outline-success btn-sm">
                            <i class="fas fa-file-excel me-1"></i>
                            Exporter Excel
                        </a>
                    <?php endif; ?>
                    
                    <hr>
                    
                    <a href="../notes/statistics.php?evaluation_id=<?php echo $evaluation_id; ?>" class="btn btn-outline-secondary btn-sm">
                        <i class="fas fa-chart-line me-1"></i>
                        Analyses détaillées
                    </a>
                    
                    <?php if (checkPermission('evaluations')): ?>
                        <a href="delete.php?id=<?php echo $evaluation_id; ?>" 
                           class="btn btn-outline-danger btn-sm btn-delete"
                           data-name="<?php echo htmlspecialchars($evaluation['nom']); ?>">
                            <i class="fas fa-trash me-1"></i>
                            Supprimer
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Confirmation de suppression
document.addEventListener('DOMContentLoaded', function() {
    const deleteButtons = document.querySelectorAll('.btn-delete');
    deleteButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            const name = this.getAttribute('data-name');
            if (confirm(`Êtes-vous sûr de vouloir supprimer l'évaluation "${name}" ?\n\nCette action supprimera également toutes les notes associées et ne peut pas être annulée.`)) {
                window.location.href = this.href;
            }
        });
    });
});
</script>

<?php include '../../../includes/footer.php'; ?>
