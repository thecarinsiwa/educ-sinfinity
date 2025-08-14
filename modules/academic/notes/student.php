<?php
/**
 * Module Académique - Notes détaillées d'un étudiant
 * Application de gestion scolaire - République Démocratique du Congo
 */

require_once '../../../config/config.php';
require_once '../../../config/database.php';
require_once '../../../includes/functions.php';

// Vérifier l'authentification et les permissions
requireLogin();
if (!checkPermission('evaluations') && !checkPermission('evaluations_view')) {
    showMessage('error', 'Accès refusé à cette fonctionnalité.');
    redirectTo('../../dashboard.php');
}

// Récupérer l'ID de l'étudiant
$eleve_id = (int)($_GET['eleve_id'] ?? 0);
if (!$eleve_id) {
    showMessage('error', 'ID d\'étudiant manquant.');
    redirectTo('../../dashboard.php');
}

// Récupérer l'année scolaire active
$current_year = getCurrentAcademicYear();
if (!$current_year) {
    showMessage('error', 'Aucune année scolaire active.');
    redirectTo('../../../index.php');
}

// Récupérer les informations de l'étudiant
$eleve = $database->query(
    "SELECT e.*, 
            i.date_inscription, i.status as statut_inscription,
            c.nom as classe_nom, c.niveau, c.section
     FROM eleves e
     LEFT JOIN inscriptions i ON e.id = i.eleve_id AND i.annee_scolaire_id = ?
     LEFT JOIN classes c ON i.classe_id = c.id
     WHERE e.id = ?",
    [$current_year['id'], $eleve_id]
)->fetch();

if (!$eleve) {
    showMessage('error', 'Étudiant non trouvé.');
    redirectTo('../../dashboard.php');
}

$page_title = 'Notes de ' . $eleve['nom'] . ' ' . $eleve['prenom'];

// Paramètres de filtrage
$matiere_filter = (int)($_GET['matiere_id'] ?? 0);
$periode_filter = sanitizeInput($_GET['periode'] ?? '');
$type_filter = sanitizeInput($_GET['type'] ?? '');

// Récupérer les matières pour le filtre
$matieres = $database->query(
    "SELECT DISTINCT m.* 
     FROM matieres m
     JOIN evaluations e ON m.id = e.matiere_id
     JOIN notes n ON e.id = n.evaluation_id
     WHERE n.eleve_id = ? AND e.annee_scolaire_id = ?
     ORDER BY m.nom",
    [$eleve_id, $current_year['id']]
)->fetchAll();

// Construire la requête avec filtres
$where_conditions = ["n.eleve_id = ?", "e.annee_scolaire_id = ?"];
$params = [$eleve_id, $current_year['id']];

if ($matiere_filter) {
    $where_conditions[] = "e.matiere_id = ?";
    $params[] = $matiere_filter;
}

if ($periode_filter) {
    $where_conditions[] = "e.periode = ?";
    $params[] = $periode_filter;
}

if ($type_filter) {
    $where_conditions[] = "e.type_evaluation = ?";
    $params[] = $type_filter;
}

$where_clause = implode(' AND ', $where_conditions);

// Récupérer toutes les notes de l'étudiant
$notes = $database->query(
    "SELECT n.*,
            e.nom as evaluation_nom, e.type_evaluation, e.date_evaluation, e.periode, e.note_max,
            m.id as matiere_id, m.nom as matiere_nom, m.coefficient as matiere_coefficient,
            CONCAT(p.nom, ' ', p.prenom) as enseignant_nom
     FROM notes n
     JOIN evaluations e ON n.evaluation_id = e.id
     JOIN matieres m ON e.matiere_id = m.id
     LEFT JOIN personnel p ON e.enseignant_id = p.id
     WHERE $where_clause
     ORDER BY e.date_evaluation DESC, m.nom",
    $params
)->fetchAll();

// Calculer les statistiques par matière
$stats_par_matiere = [];
$total_notes = 0;
$total_points = 0;
$total_coefficient = 0;

foreach ($notes as $note) {
    $matiere_id = $note['matiere_id'];
    if (!isset($stats_par_matiere[$matiere_id])) {
        $stats_par_matiere[$matiere_id] = [
            'matiere_nom' => $note['matiere_nom'],
            'coefficient' => $note['matiere_coefficient'],
            'notes' => [],
            'moyenne' => 0,
            'total_points' => 0,
            'nb_notes' => 0,
            'note_min' => null,
            'note_max' => null
        ];
    }
    
    $stats_par_matiere[$matiere_id]['notes'][] = $note;
    $stats_par_matiere[$matiere_id]['total_points'] += $note['note'];
    $stats_par_matiere[$matiere_id]['nb_notes']++;
    
    // Calculer min/max
    if ($stats_par_matiere[$matiere_id]['note_min'] === null || $note['note'] < $stats_par_matiere[$matiere_id]['note_min']) {
        $stats_par_matiere[$matiere_id]['note_min'] = $note['note'];
    }
    if ($stats_par_matiere[$matiere_id]['note_max'] === null || $note['note'] > $stats_par_matiere[$matiere_id]['note_max']) {
        $stats_par_matiere[$matiere_id]['note_max'] = $note['note'];
    }
    
    $total_notes++;
    $total_points += $note['note'] * $note['matiere_coefficient'];
    $total_coefficient += $note['matiere_coefficient'];
}

// Calculer les moyennes par matière
foreach ($stats_par_matiere as &$stats) {
    if ($stats['nb_notes'] > 0) {
        $stats['moyenne'] = round($stats['total_points'] / $stats['nb_notes'], 2);
    }
}

// Calculer la moyenne générale
$moyenne_generale = $total_coefficient > 0 ? round($total_points / $total_coefficient, 2) : 0;

// Calculer le statut académique
$statut_academique = 'Non évalué';
$statut_color = 'secondary';
if ($moyenne_generale > 0) {
    if ($moyenne_generale >= 16) {
        $statut_academique = 'Excellent';
        $statut_color = 'success';
    } elseif ($moyenne_generale >= 14) {
        $statut_academique = 'Très bien';
        $statut_color = 'info';
    } elseif ($moyenne_generale >= 12) {
        $statut_academique = 'Bien';
        $statut_color = 'primary';
    } elseif ($moyenne_generale >= 10) {
        $statut_academique = 'Satisfaisant';
        $statut_color = 'warning';
    } else {
        $statut_academique = 'Insuffisant';
        $statut_color = 'danger';
    }
}

include '../../../includes/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">
        <i class="fas fa-chart-line me-2"></i>
        Notes de <?php echo htmlspecialchars($eleve['nom'] . ' ' . $eleve['prenom']); ?>
    </h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <div class="btn-group me-2">
            <a href="../../dashboard.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left me-1"></i>
                Retour au tableau de bord
            </a>
        </div>
        <div class="btn-group">
            <a href="../../students/view.php?id=<?php echo $eleve_id; ?>" class="btn btn-outline-primary">
                <i class="fas fa-user me-1"></i>
                Profil étudiant
            </a>
        </div>
    </div>
</div>

<!-- Informations de l'étudiant -->
<div class="row mb-4">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-user-graduate me-2"></i>
                    Informations de l'étudiant
                </h5>
            </div>
            <div class="card-body">
                <table class="table table-borderless">
                    <tr>
                        <td class="fw-bold">Nom complet :</td>
                        <td><?php echo htmlspecialchars($eleve['nom'] . ' ' . $eleve['prenom']); ?></td>
                    </tr>
                    <tr>
                        <td class="fw-bold">Matricule :</td>
                        <td><?php echo htmlspecialchars($eleve['numero_matricule']); ?></td>
                    </tr>
                    <tr>
                        <td class="fw-bold">Classe :</td>
                        <td>
                            <?php if ($eleve['classe_nom']): ?>
                                <span class="badge bg-<?php 
                                    echo $eleve['niveau'] === 'maternelle' ? 'warning' : 
                                        ($eleve['niveau'] === 'primaire' ? 'success' : 'primary'); 
                                ?>">
                                    <?php echo htmlspecialchars($eleve['classe_nom']); ?>
                                </span>
                            <?php else: ?>
                                <span class="text-muted">Non inscrit</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <td class="fw-bold">Niveau :</td>
                        <td><?php echo ucfirst($eleve['niveau'] ?? 'Non défini'); ?></td>
                    </tr>
                    <tr>
                        <td class="fw-bold">Statut :</td>
                        <td>
                            <span class="badge bg-<?php echo $eleve['statut_inscription'] === 'inscrit' ? 'success' : 'secondary'; ?>">
                                <?php echo ucfirst($eleve['statut_inscription'] ?? 'Non inscrit'); ?>
                            </span>
                        </td>
                    </tr>
                </table>
            </div>
        </div>
    </div>
    
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-chart-pie me-2"></i>
                    Résumé académique
                </h5>
            </div>
            <div class="card-body">
                <div class="row text-center">
                    <div class="col-6 mb-3">
                        <h4 class="text-primary"><?php echo $total_notes; ?></h4>
                        <small>Total notes</small>
                    </div>
                    <div class="col-6 mb-3">
                        <h4 class="text-<?php echo $statut_color; ?>"><?php echo $moyenne_generale; ?></h4>
                        <small>Moyenne générale</small>
                    </div>
                    <div class="col-12">
                        <span class="badge bg-<?php echo $statut_color; ?> fs-6">
                            <?php echo $statut_academique; ?>
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Filtres -->
<div class="card mb-4">
    <div class="card-header">
        <h5 class="mb-0">
            <i class="fas fa-filter me-2"></i>
            Filtres
        </h5>
    </div>
    <div class="card-body">
        <form method="GET" class="row g-3">
            <input type="hidden" name="eleve_id" value="<?php echo $eleve_id; ?>">
            
            <div class="col-md-3">
                <label for="matiere_id" class="form-label">Matière</label>
                <select class="form-select" id="matiere_id" name="matiere_id">
                    <option value="">Toutes les matières</option>
                    <?php foreach ($matieres as $matiere): ?>
                        <option value="<?php echo $matiere['id']; ?>" 
                                <?php echo $matiere_filter == $matiere['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($matiere['nom']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="col-md-3">
                <label for="periode" class="form-label">Période</label>
                <select class="form-select" id="periode" name="periode">
                    <option value="">Toutes les périodes</option>
                    <option value="trimestre1" <?php echo $periode_filter === 'trimestre1' ? 'selected' : ''; ?>>1er Trimestre</option>
                    <option value="trimestre2" <?php echo $periode_filter === 'trimestre2' ? 'selected' : ''; ?>>2ème Trimestre</option>
                    <option value="trimestre3" <?php echo $periode_filter === 'trimestre3' ? 'selected' : ''; ?>>3ème Trimestre</option>
                </select>
            </div>
            
            <div class="col-md-3">
                <label for="type" class="form-label">Type d'évaluation</label>
                <select class="form-select" id="type" name="type">
                    <option value="">Tous les types</option>
                    <option value="devoir" <?php echo $type_filter === 'devoir' ? 'selected' : ''; ?>>Devoir</option>
                    <option value="composition" <?php echo $type_filter === 'composition' ? 'selected' : ''; ?>>Composition</option>
                    <option value="examen" <?php echo $type_filter === 'examen' ? 'selected' : ''; ?>>Examen</option>
                    <option value="interrogation" <?php echo $type_filter === 'interrogation' ? 'selected' : ''; ?>>Interrogation</option>
                </select>
            </div>
            
            <div class="col-md-3">
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

<!-- Statistiques par matière -->
<?php if (!empty($stats_par_matiere)): ?>
<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-chart-bar me-2"></i>
                    Statistiques par matière
                </h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <?php foreach ($stats_par_matiere as $matiere_id => $stats): ?>
                        <div class="col-md-4 mb-3">
                            <div class="card border-<?php 
                                echo $stats['moyenne'] >= 14 ? 'success' : 
                                    ($stats['moyenne'] >= 10 ? 'warning' : 'danger'); 
                            ?>">
                                <div class="card-body">
                                    <h6 class="card-title"><?php echo htmlspecialchars($stats['matiere_nom']); ?></h6>
                                    <div class="row text-center">
                                        <div class="col-4">
                                            <small class="text-muted">Moyenne</small>
                                            <h5 class="text-<?php 
                                                echo $stats['moyenne'] >= 14 ? 'success' : 
                                                    ($stats['moyenne'] >= 10 ? 'warning' : 'danger'); 
                                            ?>"><?php echo $stats['moyenne']; ?></h5>
                                        </div>
                                        <div class="col-4">
                                            <small class="text-muted">Notes</small>
                                            <h5><?php echo $stats['nb_notes']; ?></h5>
                                        </div>
                                        <div class="col-4">
                                            <small class="text-muted">Coef</small>
                                            <h5><?php echo $stats['coefficient']; ?></h5>
                                        </div>
                                    </div>
                                    <div class="mt-2">
                                        <small class="text-muted">
                                            Min: <?php echo $stats['note_min']; ?> | 
                                            Max: <?php echo $stats['note_max']; ?>
                                        </small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Liste des notes -->
<div class="card">
    <div class="card-header">
        <h5 class="mb-0">
            <i class="fas fa-list me-2"></i>
            Détail des notes (<?php echo count($notes); ?>)
        </h5>
    </div>
    <div class="card-body">
        <?php if (!empty($notes)): ?>
            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead class="table-dark">
                        <tr>
                            <th>Date</th>
                            <th>Matière</th>
                            <th>Évaluation</th>
                            <th>Type</th>
                            <th>Note</th>
                            <th>Pourcentage</th>
                            <th>Appréciation</th>
                            <th>Observation</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($notes as $note): ?>
                            <tr>
                                <td>
                                    <small><?php echo formatDate($note['date_evaluation']); ?></small>
                                </td>
                                <td>
                                    <strong><?php echo htmlspecialchars($note['matiere_nom']); ?></strong>
                                    <br><small class="text-muted">Coef: <?php echo $note['matiere_coefficient']; ?></small>
                                </td>
                                <td><?php echo htmlspecialchars($note['evaluation_nom']); ?></td>
                                <td>
                                    <span class="badge bg-info"><?php echo ucfirst($note['type_evaluation']); ?></span>
                                </td>
                                <td>
                                    <strong class="text-<?php 
                                        echo $note['note'] >= 14 ? 'success' : 
                                            ($note['note'] >= 10 ? 'warning' : 'danger'); 
                                    ?>">
                                        <?php echo $note['note']; ?>/<?php echo $note['note_max']; ?>
                                    </strong>
                                </td>
                                <td>
                                    <?php 
                                    $pourcentage = round(($note['note'] / $note['note_max']) * 100, 1);
                                    echo $pourcentage . '%';
                                    ?>
                                </td>
                                <td>
                                    <span class="badge bg-<?php 
                                        echo $note['note'] >= 16 ? 'success' : 
                                            ($note['note'] >= 14 ? 'success' : 
                                            ($note['note'] >= 12 ? 'warning' : 
                                            ($note['note'] >= 10 ? 'warning' : 'danger'))); 
                                    ?>">
                                        <?php 
                                        if ($note['note'] >= 16) echo 'Excellent';
                                        elseif ($note['note'] >= 14) echo 'Très bien';
                                        elseif ($note['note'] >= 12) echo 'Bien';
                                        elseif ($note['note'] >= 10) echo 'Satisfaisant';
                                        elseif ($note['note'] >= 8) echo 'Passable';
                                        else echo 'Insuffisant';
                                        ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($note['observation']): ?>
                                        <small class="text-muted"><?php echo htmlspecialchars($note['observation']); ?></small>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a href="add.php?evaluation_id=<?php echo $note['evaluation_id']; ?>&eleve_id=<?php echo $eleve_id; ?>" 
                                       class="btn btn-sm btn-outline-primary" 
                                       title="Modifier la note">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="text-center py-4">
                <i class="fas fa-clipboard-list fa-3x text-muted mb-3"></i>
                <h5 class="text-muted">Aucune note trouvée</h5>
                <p class="text-muted">
                    <?php if ($matiere_filter || $periode_filter || $type_filter): ?>
                        Aucune note ne correspond aux critères de filtrage sélectionnés.
                    <?php else: ?>
                        Cet étudiant n'a pas encore de notes enregistrées.
                    <?php endif; ?>
                </p>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Graphique d'évolution (optionnel) -->
<?php if (count($notes) > 1): ?>
<div class="card mt-4">
    <div class="card-header">
        <h5 class="mb-0">
            <i class="fas fa-chart-line me-2"></i>
            Évolution des notes
        </h5>
    </div>
    <div class="card-body">
        <canvas id="evolutionChart" width="400" height="200"></canvas>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Données pour le graphique
const chartData = {
    labels: <?php echo json_encode(array_map(function($note) { 
        return formatDate($note['date_evaluation']); 
    }, array_slice($notes, 0, 10))); ?>,
    datasets: [{
        label: 'Notes',
        data: <?php echo json_encode(array_map(function($note) { 
            return $note['note']; 
        }, array_slice($notes, 0, 10))); ?>,
        borderColor: 'rgb(75, 192, 192)',
        backgroundColor: 'rgba(75, 192, 192, 0.2)',
        tension: 0.1
    }]
};

// Configuration du graphique
const config = {
    type: 'line',
    data: chartData,
    options: {
        responsive: true,
        plugins: {
            title: {
                display: true,
                text: 'Évolution des notes (10 dernières)'
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                max: <?php echo max(array_column($notes, 'note_max')); ?>
            }
        }
    }
};

// Créer le graphique
new Chart(document.getElementById('evolutionChart'), config);
</script>
<?php endif; ?>

<?php include '../../../includes/footer.php'; ?>
