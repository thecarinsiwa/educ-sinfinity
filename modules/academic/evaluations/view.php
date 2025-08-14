<?php
/**
 * Module Académique - Détails d'une évaluation
 * Application de gestion scolaire - République Démocratique du Congo
 */

require_once '../../../config/config.php';
require_once '../../../config/database.php';
require_once '../../../includes/functions.php';

// Vérifier l'authentification et les permissions
requireLogin();
if (!checkPermission('academic') && !checkPermission('evaluations_view')) {
    showMessage('error', 'Accès refusé à cette fonctionnalité.');
    redirectTo('../../index.php');
}

// Récupérer l'ID de l'évaluation
$evaluation_id = (int)($_GET['id'] ?? 0);
if (!$evaluation_id) {
    showMessage('error', 'ID d\'évaluation manquant.');
    redirectTo('index.php');
}

// Récupérer l'année scolaire active
$current_year = getCurrentAcademicYear();

if (!$current_year) {
    showMessage('error', 'Aucune année scolaire active.');
    redirectTo('../../index.php');
}

// Récupérer les détails de l'évaluation
$evaluation = $database->query(
    "SELECT e.*, 
            m.nom as matiere_nom, m.code as matiere_code, m.coefficient as matiere_coefficient,
            c.nom as classe_nom, c.niveau, c.section, c.capacite_max,
            CONCAT(p.nom, ' ', p.prenom) as enseignant_nom,
            ans.annee as annee_scolaire
     FROM evaluations e
     JOIN matieres m ON e.matiere_id = m.id
     JOIN classes c ON e.classe_id = c.id
     LEFT JOIN personnel p ON e.enseignant_id = p.id
     JOIN annees_scolaires ans ON e.annee_scolaire_id = ans.id
     WHERE e.id = ? AND e.annee_scolaire_id = ?",
    [$evaluation_id, $current_year['id']]
)->fetch();

if (!$evaluation) {
    showMessage('error', 'Évaluation non trouvée ou n\'appartient pas à l\'année scolaire active.');
    redirectTo('index.php');
}

$page_title = 'Détails de l\'évaluation : ' . $evaluation['nom'];

// Récupérer les notes de cette évaluation
$notes = $database->query(
    "SELECT n.*, e.nom, e.prenom, e.numero_matricule, e.sexe, e.date_naissance
     FROM notes n
     JOIN eleves e ON n.eleve_id = e.id
     WHERE n.evaluation_id = ?
     ORDER BY e.nom, e.prenom",
    [$evaluation_id]
)->fetchAll();

// Récupérer tous les élèves de la classe (pour voir qui n'a pas encore de note)
$eleves_classe = $database->query(
    "SELECT e.id, e.nom, e.prenom, e.numero_matricule, e.sexe, e.date_naissance
     FROM eleves e
     JOIN inscriptions i ON e.id = i.eleve_id
     WHERE i.classe_id = ? AND i.status = 'inscrit' AND i.annee_scolaire_id = ?
     ORDER BY e.nom, e.prenom",
    [$evaluation['classe_id'], $current_year['id']]
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
    'ecart_type' => 0
];

if (!empty($notes)) {
    $notes_values = array_column($notes, 'note');
    $stats['moyenne_classe'] = round(array_sum($notes_values) / count($notes_values), 2);
    $stats['note_min'] = min($notes_values);
    $stats['note_max'] = max($notes_values);
    
    // Calculer l'écart-type
    $moyenne = $stats['moyenne_classe'];
    $variance = 0;
    foreach ($notes_values as $note) {
        $variance += pow($note - $moyenne, 2);
    }
    $stats['ecart_type'] = round(sqrt($variance / count($notes_values)), 2);
    
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

// Répartition des notes par tranche
$tranches_notes = [];
if (!empty($notes)) {
    $tranches = [
        'Excellent (16-20)' => 0,
        'Très bien (14-15)' => 0,
        'Bien (12-13)' => 0,
        'Satisfaisant (10-11)' => 0,
        'Passable (8-9)' => 0,
        'Insuffisant (0-7)' => 0
    ];
    
    foreach ($notes as $note) {
        $note_value = $note['note'];
        if ($note_value >= 16) {
            $tranches['Excellent (16-20)']++;
        } elseif ($note_value >= 14) {
            $tranches['Très bien (14-15)']++;
        } elseif ($note_value >= 12) {
            $tranches['Bien (12-13)']++;
        } elseif ($note_value >= 10) {
            $tranches['Satisfaisant (10-11)']++;
        } elseif ($note_value >= 8) {
            $tranches['Passable (8-9)']++;
        } else {
            $tranches['Insuffisant (0-7)']++;
        }
    }
    
    $tranches_notes = $tranches;
}

include '../../../includes/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">
        <i class="fas fa-clipboard-check me-2"></i>
        Détails de l'évaluation
    </h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <div class="btn-group me-2">
            <a href="index.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left me-1"></i>
                Retour aux évaluations
            </a>
        </div>
        <div class="btn-group">
            <a href="edit.php?id=<?php echo $evaluation_id; ?>" class="btn btn-warning">
                <i class="fas fa-edit me-1"></i>
                Modifier
            </a>
            <a href="../notes/add.php?evaluation_id=<?php echo $evaluation_id; ?>" class="btn btn-success">
                <i class="fas fa-plus me-1"></i>
                Ajouter des notes
            </a>
        </div>
    </div>
</div>

<!-- Informations de l'évaluation -->
<div class="row">
    <div class="col-md-8">
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-info-circle me-2"></i>
                    Informations de l'évaluation
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
                                <td><strong>Matière :</strong></td>
                                <td>
                                    <?php echo htmlspecialchars($evaluation['matiere_nom']); ?>
                                    <span class="badge bg-info ms-2">Coeff. <?php echo $evaluation['matiere_coefficient']; ?></span>
                                </td>
                            </tr>
                            <tr>
                                <td><strong>Classe :</strong></td>
                                <td>
                                    <?php echo htmlspecialchars($evaluation['classe_nom']); ?>
                                    <span class="badge bg-secondary ms-2"><?php echo ucfirst($evaluation['niveau']); ?></span>
                                </td>
                            </tr>
                            <tr>
                                <td><strong>Type :</strong></td>
                                <td><span class="badge bg-primary"><?php echo htmlspecialchars($evaluation['type_evaluation']); ?></span></td>
                            </tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <table class="table table-borderless">
                            <tr>
                                <td><strong>Date :</strong></td>
                                <td><?php echo formatDate($evaluation['date_evaluation']); ?></td>
                            </tr>
                            <tr>
                                <td><strong>Période :</strong></td>
                                <td><span class="badge bg-warning"><?php echo htmlspecialchars($evaluation['periode']); ?></span></td>
                            </tr>
                            <tr>
                                <td><strong>Note max :</strong></td>
                                <td><span class="badge bg-success"><?php echo $evaluation['note_max']; ?>/20</span></td>
                            </tr>
                            <tr>
                                <td><strong>Enseignant :</strong></td>
                                <td><?php echo htmlspecialchars($evaluation['enseignant_nom'] ?? 'Non assigné'); ?></td>
                            </tr>
                        </table>
                    </div>
                </div>
                
                <?php if ($evaluation['description']): ?>
                    <div class="mt-3">
                        <strong>Description :</strong>
                        <p class="mb-0"><?php echo nl2br(htmlspecialchars($evaluation['description'])); ?></p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <div class="col-md-4">
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
                    <div class="col-6 mb-3">
                        <div class="border rounded p-2">
                            <h4 class="text-primary mb-0"><?php echo $stats['total_eleves']; ?></h4>
                            <small class="text-muted">Élèves</small>
                        </div>
                    </div>
                    <div class="col-6 mb-3">
                        <div class="border rounded p-2">
                            <h4 class="text-success mb-0"><?php echo $stats['notes_saisies']; ?></h4>
                            <small class="text-muted">Notes saisies</small>
                        </div>
                    </div>
                    <div class="col-6 mb-3">
                        <div class="border rounded p-2">
                            <h4 class="text-warning mb-0"><?php echo $stats['notes_manquantes']; ?></h4>
                            <small class="text-muted">Notes manquantes</small>
                        </div>
                    </div>
                    <div class="col-6 mb-3">
                        <div class="border rounded p-2">
                            <h4 class="text-info mb-0"><?php echo $stats['moyenne_classe']; ?></h4>
                            <small class="text-muted">Moyenne</small>
                        </div>
                    </div>
                </div>
                
                <?php if (!empty($notes)): ?>
                    <hr>
                    <div class="row text-center">
                        <div class="col-6">
                            <div class="border rounded p-2">
                                <h5 class="text-success mb-0"><?php echo $stats['nb_admis']; ?></h5>
                                <small class="text-muted">Admis</small>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="border rounded p-2">
                                <h5 class="text-danger mb-0"><?php echo $stats['nb_echecs']; ?></h5>
                                <small class="text-muted">Échecs</small>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php if (!empty($notes)): ?>
    <!-- Graphique de répartition -->
    <div class="row">
        <div class="col-md-6">
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-chart-pie me-2"></i>
                        Répartition des notes
                    </h5>
                </div>
                <div class="card-body">
                    <canvas id="repartitionChart" width="400" height="200"></canvas>
                </div>
            </div>
        </div>
        
        <div class="col-md-6">
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-list me-2"></i>
                        Détail par tranche
                    </h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Tranche</th>
                                    <th>Nombre</th>
                                    <th>Pourcentage</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($tranches_notes as $tranche => $nombre): ?>
                                    <?php if ($nombre > 0): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($tranche); ?></td>
                                            <td><span class="badge bg-primary"><?php echo $nombre; ?></span></td>
                                            <td><?php echo round(($nombre / $stats['notes_saisies']) * 100, 1); ?>%</td>
                                        </tr>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<!-- Liste des notes -->
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">
            <i class="fas fa-list-alt me-2"></i>
            Notes des élèves
        </h5>
        <div>
            <span class="badge bg-success"><?php echo $stats['notes_saisies']; ?> notes saisies</span>
            <?php if ($stats['notes_manquantes'] > 0): ?>
                <span class="badge bg-warning ms-1"><?php echo $stats['notes_manquantes']; ?> manquantes</span>
            <?php endif; ?>
        </div>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-striped table-hover">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Matricule</th>
                        <th>Nom et Prénom</th>
                        <th>Sexe</th>
                        <th>Âge</th>
                        <th>Note</th>
                        <th>Appréciation</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $eleves_avec_notes = [];
                    foreach ($notes as $note) {
                        $eleves_avec_notes[] = $note['eleve_id'];
                    }
                    
                    $counter = 1;
                    foreach ($eleves_classe as $eleve): 
                        $note_eleve = null;
                        foreach ($notes as $note) {
                            if ($note['eleve_id'] == $eleve['id']) {
                                $note_eleve = $note;
                                break;
                            }
                        }
                    ?>
                        <tr class="<?php echo $note_eleve ? '' : 'table-warning'; ?>">
                            <td><?php echo $counter++; ?></td>
                            <td><strong><?php echo htmlspecialchars($eleve['numero_matricule']); ?></strong></td>
                            <td>
                                <strong><?php echo htmlspecialchars($eleve['nom'] . ' ' . $eleve['prenom']); ?></strong>
                            </td>
                            <td>
                                <span class="badge bg-<?php echo $eleve['sexe'] === 'M' ? 'primary' : 'pink'; ?>">
                                    <?php echo $eleve['sexe'] === 'M' ? 'Garçon' : 'Fille'; ?>
                                </span>
                            </td>
                            <td><?php echo calculateAge($eleve['date_naissance']); ?> ans</td>
                            <td>
                                <?php if ($note_eleve): ?>
                                    <span class="badge bg-<?php 
                                        $note_value = $note_eleve['note'];
                                        if ($note_value >= 16) echo 'success';
                                        elseif ($note_value >= 14) echo 'info';
                                        elseif ($note_value >= 12) echo 'primary';
                                        elseif ($note_value >= 10) echo 'warning';
                                        elseif ($note_value >= 8) echo 'secondary';
                                        else echo 'danger';
                                    ?>">
                                        <?php echo $note_eleve['note']; ?>/<?php echo $evaluation['note_max']; ?>
                                    </span>
                                <?php else: ?>
                                    <span class="badge bg-warning">Non noté</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($note_eleve && $note_eleve['appreciation']): ?>
                                    <?php echo htmlspecialchars($note_eleve['appreciation']); ?>
                                <?php else: ?>
                                    <em class="text-muted">-</em>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($note_eleve): ?>
                                    <a href="../notes/edit.php?id=<?php echo $note_eleve['id']; ?>" class="btn btn-sm btn-warning">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                <?php else: ?>
                                    <a href="../notes/add.php?evaluation_id=<?php echo $evaluation_id; ?>&eleve_id=<?php echo $eleve['id']; ?>" class="btn btn-sm btn-success">
                                        <i class="fas fa-plus"></i> Noter
                                    </a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php if (!empty($tranches_notes)): ?>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const ctx = document.getElementById('repartitionChart').getContext('2d');
            new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: [<?php echo implode(',', array_map(function($tranche) { return '"' . addslashes($tranche) . '"'; }, array_keys(array_filter($tranches_notes)))); ?>],
                    datasets: [{
                        data: [<?php echo implode(',', array_filter($tranches_notes)); ?>],
                        backgroundColor: [
                            'rgba(40, 167, 69, 0.8)',
                            'rgba(23, 162, 184, 0.8)',
                            'rgba(0, 123, 255, 0.8)',
                            'rgba(255, 193, 7, 0.8)',
                            'rgba(108, 117, 125, 0.8)',
                            'rgba(220, 53, 69, 0.8)'
                        ],
                        borderWidth: 2,
                        borderColor: '#fff'
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: {
                            position: 'bottom'
                        }
                    }
                }
            });
        });
    </script>
<?php endif; ?>

<?php include '../../../includes/footer.php'; ?>
