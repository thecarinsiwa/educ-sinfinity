<?php
/**
 * Module d'évaluations - Évaluations d'une classe
 * Application de gestion scolaire - République Démocratique du Congo
 */

require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../includes/functions.php';

// Vérifier l'authentification et les permissions
requireLogin();
if (!checkPermission('evaluations') && !checkPermission('evaluations_view')) {
    showMessage('error', 'Accès refusé à cette fonctionnalité.');
    redirectTo('index.php');
}

// Récupérer l'ID de la classe
$classe_id = (int)($_GET['id'] ?? 0);
if (!$classe_id) {
    showMessage('error', 'ID de classe manquant.');
    redirectTo('index.php');
}

// Récupérer l'année scolaire active
$current_year = getCurrentAcademicYear();

// Récupérer les informations de la classe
$classe = $database->query(
    "SELECT c.*, 
            COUNT(DISTINCT i.eleve_id) as nb_eleves,
            COUNT(DISTINCT e.id) as nb_evaluations
     FROM classes c
     LEFT JOIN inscriptions i ON c.id = i.classe_id AND i.status = 'inscrit' AND i.annee_scolaire_id = ?
     LEFT JOIN evaluations e ON c.id = e.classe_id AND e.annee_scolaire_id = ?
     WHERE c.id = ?
     GROUP BY c.id",
    [$current_year['id'] ?? 0, $current_year['id'] ?? 0, $classe_id]
)->fetch();

if (!$classe) {
    showMessage('error', 'Classe non trouvée.');
    redirectTo('index.php');
}

$page_title = 'Évaluations de la classe : ' . $classe['nom'];

// Récupérer toutes les évaluations de cette classe
$evaluations = $database->query(
    "SELECT e.*, 
            m.nom as matiere_nom, m.code as matiere_code, m.coefficient as matiere_coefficient,
            CONCAT(p.nom, ' ', p.prenom) as enseignant_nom,
            COUNT(n.id) as nb_notes,
            AVG(n.note) as moyenne_classe,
            MIN(n.note) as note_min,
            MAX(n.note) as note_max
     FROM evaluations e
     JOIN matieres m ON e.matiere_id = m.id
     LEFT JOIN personnel p ON e.enseignant_id = p.id
     LEFT JOIN notes n ON e.id = n.evaluation_id
     WHERE e.classe_id = ? AND e.annee_scolaire_id = ?
     GROUP BY e.id
     ORDER BY e.date_evaluation DESC, e.id DESC",
    [$classe_id, $current_year['id'] ?? 0]
)->fetchAll();

// Récupérer les élèves de la classe
$eleves = $database->query(
    "SELECT e.id, e.nom, e.prenom, e.numero_matricule, e.sexe,
            COUNT(n.id) as nb_evaluations_passees,
            AVG(n.note) as moyenne_generale
     FROM eleves e
     JOIN inscriptions i ON e.id = i.eleve_id
     LEFT JOIN notes n ON e.id = n.eleve_id
     LEFT JOIN evaluations ev ON n.evaluation_id = ev.id AND ev.classe_id = ? AND ev.annee_scolaire_id = ?
     WHERE i.classe_id = ? AND i.status = 'inscrit' AND i.annee_scolaire_id = ?
     GROUP BY e.id
     ORDER BY e.nom, e.prenom",
    [$classe_id, $current_year['id'] ?? 0, $classe_id, $current_year['id'] ?? 0]
)->fetchAll();

// Statistiques générales de la classe
$stats = [
    'total_eleves' => count($eleves),
    'total_evaluations' => count($evaluations),
    'moyenne_generale_classe' => 0,
    'nb_filles' => count(array_filter($eleves, fn($e) => $e['sexe'] === 'F')),
    'nb_garcons' => count(array_filter($eleves, fn($e) => $e['sexe'] === 'M')),
    'eleves_avec_notes' => count(array_filter($eleves, fn($e) => $e['nb_evaluations_passees'] > 0))
];

// Calculer la moyenne générale de la classe
$moyennes_eleves = array_filter(array_column($eleves, 'moyenne_generale'), fn($m) => $m !== null);
if (!empty($moyennes_eleves)) {
    $stats['moyenne_generale_classe'] = round(array_sum($moyennes_eleves) / count($moyennes_eleves), 2);
}

// Répartition par type d'évaluation
$types_evaluations = $database->query(
    "SELECT e.type, COUNT(*) as nombre, AVG(n.note) as moyenne
     FROM evaluations e
     LEFT JOIN notes n ON e.id = n.evaluation_id
     WHERE e.classe_id = ? AND e.annee_scolaire_id = ?
     GROUP BY e.type
     ORDER BY nombre DESC",
    [$classe_id, $current_year['id'] ?? 0]
)->fetchAll();

// Répartition par matière
$matieres_evaluations = $database->query(
    "SELECT m.nom as matiere_nom, m.coefficient,
            COUNT(e.id) as nb_evaluations,
            AVG(n.note) as moyenne_matiere,
            COUNT(DISTINCT n.eleve_id) as nb_eleves_evalues
     FROM matieres m
     LEFT JOIN evaluations e ON m.id = e.matiere_id AND e.classe_id = ? AND e.annee_scolaire_id = ?
     LEFT JOIN notes n ON e.id = n.evaluation_id
     WHERE m.id IN (SELECT DISTINCT matiere_id FROM evaluations WHERE classe_id = ? AND annee_scolaire_id = ?)
     GROUP BY m.id
     ORDER BY m.nom",
    [$classe_id, $current_year['id'] ?? 0, $classe_id, $current_year['id'] ?? 0]
)->fetchAll();

// Répartition des notes par tranche
$tranches_notes = $database->query(
    "SELECT 
        CASE 
            WHEN n.note >= 16 THEN 'Excellent (16-20)'
            WHEN n.note >= 14 THEN 'Très bien (14-15)'
            WHEN n.note >= 12 THEN 'Bien (12-13)'
            WHEN n.note >= 10 THEN 'Satisfaisant (10-11)'
            WHEN n.note >= 8 THEN 'Passable (8-9)'
            ELSE 'Insuffisant (0-7)'
        END as tranche,
        COUNT(*) as nombre
     FROM notes n
     JOIN evaluations e ON n.evaluation_id = e.id
     WHERE e.classe_id = ? AND e.annee_scolaire_id = ?
     GROUP BY tranche
     ORDER BY MIN(n.note)",
    [$classe_id, $current_year['id'] ?? 0]
)->fetchAll();

include '../../includes/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">
        <i class="fas fa-chalkboard me-2"></i>
        Évaluations de la classe : <?php echo htmlspecialchars($classe['nom']); ?>
    </h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <div class="btn-group me-2">
            <a href="index.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left me-1"></i>
                Retour aux évaluations
            </a>
            <?php if (checkPermission('evaluations')): ?>
                <a href="evaluations/add.php?classe_id=<?php echo $classe_id; ?>" class="btn btn-primary">
                    <i class="fas fa-plus me-1"></i>
                    Nouvelle évaluation
                </a>
            <?php endif; ?>
        </div>
        <div class="btn-group">
            <button type="button" class="btn btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">
                <i class="fas fa-download me-1"></i>
                Exporter
            </button>
            <ul class="dropdown-menu">
                <li><a class="dropdown-item" href="notes/export.php?classe_id=<?php echo $classe_id; ?>&format=excel"><i class="fas fa-file-excel me-2"></i>Excel</a></li>
                <li><a class="dropdown-item" href="notes/export.php?classe_id=<?php echo $classe_id; ?>&format=pdf"><i class="fas fa-file-pdf me-2"></i>PDF</a></li>
            </ul>
        </div>
    </div>
</div>

<!-- Informations de la classe -->
<div class="row mb-4">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-info-circle me-2"></i>
                    Informations de la classe
                </h5>
            </div>
            <div class="card-body">
                <table class="table table-borderless">
                    <tr>
                        <td class="fw-bold">Classe :</td>
                        <td><?php echo htmlspecialchars($classe['nom']); ?></td>
                    </tr>
                    <tr>
                        <td class="fw-bold">Niveau :</td>
                        <td><?php echo ucfirst($classe['niveau']); ?></td>
                    </tr>
                    <tr>
                        <td class="fw-bold">Section :</td>
                        <td><?php echo htmlspecialchars($classe['section'] ?? 'Non spécifiée'); ?></td>
                    </tr>
                    <tr>
                        <td class="fw-bold">Année scolaire :</td>
                        <td><?php echo $current_year['annee'] ?? 'Non définie'; ?></td>
                    </tr>
                    <tr>
                        <td class="fw-bold">Capacité :</td>
                        <td><?php echo $classe['capacite_max']; ?> élèves</td>
                    </tr>
                </table>
            </div>
        </div>
    </div>
    
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-chart-bar me-2"></i>
                    Statistiques générales
                </h5>
            </div>
            <div class="card-body">
                <div class="row text-center">
                    <div class="col-6 mb-3">
                        <div class="h4 text-primary"><?php echo $stats['total_eleves']; ?></div>
                        <small class="text-muted">Élèves inscrits</small>
                    </div>
                    <div class="col-6 mb-3">
                        <div class="h4 text-success"><?php echo $stats['total_evaluations']; ?></div>
                        <small class="text-muted">Évaluations</small>
                    </div>
                    <div class="col-6 mb-3">
                        <div class="h4 text-info"><?php echo $stats['moyenne_generale_classe']; ?>/20</div>
                        <small class="text-muted">Moyenne générale</small>
                    </div>
                    <div class="col-6 mb-3">
                        <div class="h4 text-warning"><?php echo $stats['eleves_avec_notes']; ?></div>
                        <small class="text-muted">Élèves évalués</small>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Statistiques détaillées -->
<div class="row mb-4">
    <!-- Répartition par type d'évaluation -->
    <div class="col-lg-4 col-md-6 mb-4">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-chart-pie me-2"></i>
                    Par type d'évaluation
                </h5>
            </div>
            <div class="card-body">
                <?php if (!empty($types_evaluations)): ?>
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Type</th>
                                    <th>Nombre</th>
                                    <th>Moyenne</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($types_evaluations as $type): ?>
                                    <tr>
                                        <td><?php echo ucfirst($type['type']); ?></td>
                                        <td><?php echo $type['nombre']; ?></td>
                                        <td>
                                            <?php if ($type['moyenne']): ?>
                                                <span class="badge bg-<?php echo $type['moyenne'] >= 10 ? 'success' : 'danger'; ?>">
                                                    <?php echo round($type['moyenne'], 2); ?>/20
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
                    <p class="text-muted text-center">Aucune évaluation pour le moment</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Répartition par matière -->
    <div class="col-lg-4 col-md-6 mb-4">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-book me-2"></i>
                    Par matière
                </h5>
            </div>
            <div class="card-body">
                <?php if (!empty($matieres_evaluations)): ?>
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Matière</th>
                                    <th>Éval.</th>
                                    <th>Moyenne</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($matieres_evaluations as $matiere): ?>
                                    <tr>
                                        <td>
                                            <?php echo htmlspecialchars($matiere['matiere_nom']); ?>
                                            <small class="text-muted">(coef. <?php echo $matiere['coefficient']; ?>)</small>
                                        </td>
                                        <td><?php echo $matiere['nb_evaluations']; ?></td>
                                        <td>
                                            <?php if ($matiere['moyenne_matiere']): ?>
                                                <span class="badge bg-<?php echo $matiere['moyenne_matiere'] >= 10 ? 'success' : 'danger'; ?>">
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
                    <p class="text-muted text-center">Aucune matière évaluée</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Répartition des notes -->
    <div class="col-lg-4 col-md-6 mb-4">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-chart-bar me-2"></i>
                    Répartition des notes
                </h5>
            </div>
            <div class="card-body">
                <?php if (!empty($tranches_notes)): ?>
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Tranche</th>
                                    <th>Nombre</th>
                                    <th>%</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $total_notes = array_sum(array_column($tranches_notes, 'nombre'));
                                foreach ($tranches_notes as $tranche): 
                                    $pourcentage = $total_notes > 0 ? round(($tranche['nombre'] / $total_notes) * 100, 1) : 0;
                                ?>
                                    <tr>
                                        <td><?php echo $tranche['tranche']; ?></td>
                                        <td><?php echo $tranche['nombre']; ?></td>
                                        <td>
                                            <div class="progress" style="height: 20px;">
                                                <div class="progress-bar bg-<?php echo strpos($tranche['tranche'], 'Excellent') !== false ? 'success' : 
                                                    (strpos($tranche['tranche'], 'Très bien') !== false ? 'info' : 
                                                    (strpos($tranche['tranche'], 'Bien') !== false ? 'primary' : 
                                                    (strpos($tranche['tranche'], 'Satisfaisant') !== false ? 'warning' : 'danger'))); ?>" 
                                                     style="width: <?php echo $pourcentage; ?>%">
                                                    <?php echo $pourcentage; ?>%
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p class="text-muted text-center">Aucune note enregistrée</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Liste des évaluations -->
<div class="card mb-4">
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
                            <th>Matière</th>
                            <th>Type</th>
                            <th>Enseignant</th>
                            <th>Notes saisies</th>
                            <th>Moyenne</th>
                            <th>Période</th>
                            <th class="no-sort">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($evaluations as $evaluation): ?>
                            <tr>
                                <td><?php echo formatDate($evaluation['date_evaluation']); ?></td>
                                <td>
                                    <strong><?php echo htmlspecialchars($evaluation['nom']); ?></strong>
                                    <br><small class="text-muted">Note max: <?php echo $evaluation['note_max']; ?>/20</small>
                                </td>
                                <td>
                                    <?php echo htmlspecialchars($evaluation['matiere_nom']); ?>
                                    <br><small class="text-muted">Coef. <?php echo $evaluation['matiere_coefficient']; ?></small>
                                </td>
                                <td>
                                    <?php
                                    $type_colors = [
                                        'interrogation' => 'primary',
                                        'devoir' => 'success',
                                        'examen' => 'warning',
                                        'composition' => 'danger'
                                    ];
                                    $color = $type_colors[$evaluation['type']] ?? 'secondary';
                                    ?>
                                    <span class="badge bg-<?php echo $color; ?>">
                                        <?php echo ucfirst($evaluation['type']); ?>
                                    </span>
                                </td>
                                <td><?php echo htmlspecialchars($evaluation['enseignant_nom'] ?? 'Non assigné'); ?></td>
                                <td>
                                    <?php echo $evaluation['nb_notes']; ?> / <?php echo $stats['total_eleves']; ?>
                                    <?php if ($evaluation['nb_notes'] < $stats['total_eleves']): ?>
                                        <br><small class="text-warning">Incomplet</small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($evaluation['moyenne_classe']): ?>
                                        <span class="badge bg-<?php echo $evaluation['moyenne_classe'] >= 10 ? 'success' : 'danger'; ?>">
                                            <?php echo round($evaluation['moyenne_classe'], 2); ?>/20
                                        </span>
                                        <br><small class="text-muted">
                                            Min: <?php echo $evaluation['note_min']; ?> | Max: <?php echo $evaluation['note_max']; ?>
                                        </small>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php echo ucfirst(str_replace('_', ' ', $evaluation['periode'])); ?>
                                </td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <a href="evaluations/view.php?id=<?php echo $evaluation['id']; ?>" 
                                           class="btn btn-outline-info" 
                                           title="Voir détails">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <?php if (checkPermission('evaluations')): ?>
                                            <a href="notes/entry.php?evaluation_id=<?php echo $evaluation['id']; ?>" 
                                               class="btn btn-outline-primary" 
                                               title="Saisir les notes">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <a href="evaluations/edit.php?id=<?php echo $evaluation['id']; ?>" 
                                               class="btn btn-outline-warning" 
                                               title="Modifier">
                                                <i class="fas fa-pencil"></i>
                                            </a>
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
                <h5 class="text-muted">Aucune évaluation pour cette classe</h5>
                <p class="text-muted">
                    <?php if (checkPermission('evaluations')): ?>
                        <a href="evaluations/add.php?classe_id=<?php echo $classe_id; ?>" class="btn btn-primary">
                            <i class="fas fa-plus me-1"></i>
                            Créer la première évaluation
                        </a>
                    <?php else: ?>
                        Aucune évaluation n'a encore été créée pour cette classe.
                    <?php endif; ?>
                </p>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Liste des élèves avec leurs moyennes -->
<div class="card">
    <div class="card-header">
        <h5 class="mb-0">
            <i class="fas fa-users me-2"></i>
            Élèves de la classe (<?php echo count($eleves); ?>)
        </h5>
    </div>
    <div class="card-body">
        <?php if (!empty($eleves)): ?>
            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead>
                        <tr>
                            <th>Matricule</th>
                            <th>Nom complet</th>
                            <th>Sexe</th>
                            <th>Évaluations passées</th>
                            <th>Moyenne générale</th>
                            <th>Statut</th>
                            <th class="no-sort">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($eleves as $eleve): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($eleve['numero_matricule']); ?></td>
                                <td>
                                    <strong><?php echo htmlspecialchars($eleve['nom'] . ' ' . $eleve['prenom']); ?></strong>
                                </td>
                                <td>
                                    <i class="fas fa-<?php echo $eleve['sexe'] === 'M' ? 'mars text-primary' : 'venus text-pink'; ?>"></i>
                                    <?php echo $eleve['sexe'] === 'M' ? 'Masculin' : 'Féminin'; ?>
                                </td>
                                <td>
                                    <?php echo $eleve['nb_evaluations_passees']; ?> / <?php echo $stats['total_evaluations']; ?>
                                    <?php if ($eleve['nb_evaluations_passees'] < $stats['total_evaluations']): ?>
                                        <br><small class="text-warning">Incomplet</small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($eleve['moyenne_generale']): ?>
                                        <span class="badge bg-<?php echo $eleve['moyenne_generale'] >= 10 ? 'success' : 'danger'; ?>">
                                            <?php echo round($eleve['moyenne_generale'], 2); ?>/20
                                        </span>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($eleve['moyenne_generale']): ?>
                                        <?php if ($eleve['moyenne_generale'] >= 16): ?>
                                            <span class="badge bg-success">Excellent</span>
                                        <?php elseif ($eleve['moyenne_generale'] >= 14): ?>
                                            <span class="badge bg-info">Très bien</span>
                                        <?php elseif ($eleve['moyenne_generale'] >= 12): ?>
                                            <span class="badge bg-primary">Bien</span>
                                        <?php elseif ($eleve['moyenne_generale'] >= 10): ?>
                                            <span class="badge bg-warning">Satisfaisant</span>
                                        <?php else: ?>
                                            <span class="badge bg-danger">Insuffisant</span>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Non évalué</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <a href="../students/records/view.php?id=<?php echo $eleve['id']; ?>" 
                                           class="btn btn-outline-info" 
                                           title="Voir dossier">
                                            <i class="fas fa-folder"></i>
                                        </a>
                                        <a href="notes/student.php?eleve_id=<?php echo $eleve['id']; ?>&classe_id=<?php echo $classe_id; ?>" 
                                           class="btn btn-outline-primary" 
                                           title="Voir notes">
                                            <i class="fas fa-chart-line"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="text-center py-5">
                <i class="fas fa-users fa-3x text-muted mb-3"></i>
                <h5 class="text-muted">Aucun élève dans cette classe</h5>
                <p class="text-muted">Aucun élève n'est inscrit dans cette classe pour l'année scolaire en cours.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>
