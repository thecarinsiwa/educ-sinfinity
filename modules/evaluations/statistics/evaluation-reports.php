<?php
/**
 * Module d'évaluations - Rapports d'évaluations
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

$page_title = 'Rapports d\'évaluations';

// Récupérer l'année scolaire active
$current_year = $database->query("SELECT * FROM annees_scolaires WHERE status = 'active' LIMIT 1")->fetch();
if (!$current_year) {
    showMessage('error', 'Aucune année scolaire active trouvée.');
    redirectTo('../../../index.php');
}

// Récupérer les paramètres de filtrage
$periode_filter = sanitizeInput($_GET['periode'] ?? '');
$classe_filter = (int)($_GET['classe'] ?? 0);
$matiere_filter = (int)($_GET['matiere'] ?? 0);
$type_filter = sanitizeInput($_GET['type'] ?? '');
$status_filter = sanitizeInput($_GET['status'] ?? '');

// Récupérer les listes pour les filtres
$classes = $database->query(
    "SELECT * FROM classes WHERE annee_scolaire_id = ? ORDER BY niveau, nom",
    [$current_year['id']]
)->fetchAll();

$matieres = $database->query("SELECT * FROM matieres ORDER BY nom")->fetchAll();

// Construire la requête avec filtres
$where_conditions = ["e.annee_scolaire_id = ?"];
$params = [$current_year['id']];

if ($periode_filter) {
    $where_conditions[] = "e.periode = ?";
    $params[] = $periode_filter;
}

if ($classe_filter) {
    $where_conditions[] = "e.classe_id = ?";
    $params[] = $classe_filter;
}

if ($matiere_filter) {
    $where_conditions[] = "e.matiere_id = ?";
    $params[] = $matiere_filter;
}

if ($type_filter) {
    $where_conditions[] = "e.type_evaluation = ?";
    $params[] = $type_filter;
}

if ($status_filter) {
    $where_conditions[] = "e.status = ?";
    $params[] = $status_filter;
}

$where_clause = implode(' AND ', $where_conditions);

// Récupérer les évaluations avec statistiques
$evaluations = $database->query(
    "SELECT e.id, e.nom, e.description, e.date_evaluation, e.type_evaluation, 
            e.periode, e.note_max, e.coefficient, e.status,
            c.nom as classe_nom, c.niveau,
            m.nom as matiere_nom, m.code as matiere_code,
            p.nom as enseignant_nom, p.prenom as enseignant_prenom,
            COUNT(n.id) as nb_notes_saisies,
            COUNT(DISTINCT i.eleve_id) as nb_eleves_classe,
            AVG(n.note / e.note_max * 20) as moyenne_evaluation,
            MIN(n.note / e.note_max * 20) as note_min,
            MAX(n.note / e.note_max * 20) as note_max_obtenue,
            STDDEV(n.note / e.note_max * 20) as ecart_type
     FROM evaluations e
     JOIN classes c ON e.classe_id = c.id
     JOIN matieres m ON e.matiere_id = m.id
     LEFT JOIN personnel p ON e.enseignant_id = p.id
     LEFT JOIN notes n ON e.id = n.evaluation_id
     LEFT JOIN inscriptions i ON c.id = i.classe_id AND i.annee_scolaire_id = e.annee_scolaire_id AND i.status = 'inscrit'
     WHERE $where_clause
     GROUP BY e.id
     ORDER BY e.date_evaluation DESC, e.created_at DESC",
    $params
)->fetchAll();

// Statistiques générales des évaluations filtrées
$stats_generales = [
    'total_evaluations' => count($evaluations),
    'evaluations_terminees' => 0,
    'evaluations_en_cours' => 0,
    'taux_saisie_moyen' => 0,
    'moyenne_generale' => 0
];

$total_notes = 0;
$total_possible = 0;
$somme_moyennes = 0;
$nb_avec_notes = 0;

foreach ($evaluations as $eval) {
    if ($eval['status'] === 'terminee') {
        $stats_generales['evaluations_terminees']++;
    } else {
        $stats_generales['evaluations_en_cours']++;
    }
    
    $total_notes += $eval['nb_notes_saisies'];
    $total_possible += $eval['nb_eleves_classe'];
    
    if ($eval['moyenne_evaluation']) {
        $somme_moyennes += $eval['moyenne_evaluation'];
        $nb_avec_notes++;
    }
}

$stats_generales['taux_saisie_moyen'] = $total_possible > 0 ? ($total_notes / $total_possible) * 100 : 0;
$stats_generales['moyenne_generale'] = $nb_avec_notes > 0 ? $somme_moyennes / $nb_avec_notes : 0;

include '../../../includes/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">
        <i class="fas fa-file-chart-line me-2"></i>
        Rapports d'évaluations
    </h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <div class="btn-group me-2">
            <a href="index.php" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-1"></i>
                Retour aux statistiques
            </a>
        </div>
        <div class="btn-group">
            <a href="../evaluations/add.php" class="btn btn-primary">
                <i class="fas fa-plus me-1"></i>
                Nouvelle évaluation
            </a>
        </div>
    </div>
</div>

<!-- Statistiques générales -->
<div class="row mb-4">
    <div class="col-lg-3 col-md-6 mb-3">
        <div class="card bg-primary text-white">
            <div class="card-body text-center">
                <i class="fas fa-clipboard-check fa-2x mb-2"></i>
                <h3><?php echo $stats_generales['total_evaluations']; ?></h3>
                <p class="mb-0">Évaluations trouvées</p>
            </div>
        </div>
    </div>
    <div class="col-lg-3 col-md-6 mb-3">
        <div class="card bg-success text-white">
            <div class="card-body text-center">
                <i class="fas fa-check-circle fa-2x mb-2"></i>
                <h3><?php echo $stats_generales['evaluations_terminees']; ?></h3>
                <p class="mb-0">Terminées</p>
            </div>
        </div>
    </div>
    <div class="col-lg-3 col-md-6 mb-3">
        <div class="card bg-warning text-white">
            <div class="card-body text-center">
                <i class="fas fa-clock fa-2x mb-2"></i>
                <h3><?php echo $stats_generales['evaluations_en_cours']; ?></h3>
                <p class="mb-0">En cours</p>
            </div>
        </div>
    </div>
    <div class="col-lg-3 col-md-6 mb-3">
        <div class="card bg-info text-white">
            <div class="card-body text-center">
                <i class="fas fa-percentage fa-2x mb-2"></i>
                <h3><?php echo round($stats_generales['taux_saisie_moyen'], 1); ?>%</h3>
                <p class="mb-0">Taux de saisie</p>
            </div>
        </div>
    </div>
</div>

<!-- Filtres -->
<div class="card mb-4">
    <div class="card-header">
        <h5 class="mb-0">
            <i class="fas fa-filter me-2"></i>
            Filtres de recherche
        </h5>
    </div>
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-md-2">
                <label for="periode" class="form-label">Période</label>
                <select class="form-select" id="periode" name="periode">
                    <option value="">Toutes</option>
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
            
            <div class="col-md-2">
                <label for="classe" class="form-label">Classe</label>
                <select class="form-select" id="classe" name="classe">
                    <option value="">Toutes</option>
                    <?php foreach ($classes as $classe): ?>
                        <option value="<?php echo $classe['id']; ?>" 
                                <?php echo $classe_filter == $classe['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($classe['nom'] ?? ''); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="col-md-2">
                <label for="matiere" class="form-label">Matière</label>
                <select class="form-select" id="matiere" name="matiere">
                    <option value="">Toutes</option>
                    <?php foreach ($matieres as $matiere): ?>
                        <option value="<?php echo $matiere['id']; ?>" 
                                <?php echo $matiere_filter == $matiere['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($matiere['nom'] ?? ''); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="col-md-2">
                <label for="type" class="form-label">Type</label>
                <select class="form-select" id="type" name="type">
                    <option value="">Tous</option>
                    <option value="controle" <?php echo $type_filter === 'controle' ? 'selected' : ''; ?>>Contrôle</option>
                    <option value="devoir" <?php echo $type_filter === 'devoir' ? 'selected' : ''; ?>>Devoir</option>
                    <option value="examen" <?php echo $type_filter === 'examen' ? 'selected' : ''; ?>>Examen</option>
                    <option value="interrogation" <?php echo $type_filter === 'interrogation' ? 'selected' : ''; ?>>Interrogation</option>
                </select>
            </div>
            
            <div class="col-md-2">
                <label for="status" class="form-label">Statut</label>
                <select class="form-select" id="status" name="status">
                    <option value="">Tous</option>
                    <option value="programmee" <?php echo $status_filter === 'programmee' ? 'selected' : ''; ?>>Programmée</option>
                    <option value="en_cours" <?php echo $status_filter === 'en_cours' ? 'selected' : ''; ?>>En cours</option>
                    <option value="terminee" <?php echo $status_filter === 'terminee' ? 'selected' : ''; ?>>Terminée</option>
                    <option value="annulee" <?php echo $status_filter === 'annulee' ? 'selected' : ''; ?>>Annulée</option>
                </select>
            </div>
            
            <div class="col-md-2">
                <label class="form-label">&nbsp;</label>
                <button type="submit" class="btn btn-primary w-100">
                    <i class="fas fa-search me-1"></i>
                    Filtrer
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Liste des évaluations -->
<div class="card">
    <div class="card-header">
        <h5 class="mb-0">
            <i class="fas fa-list me-2"></i>
            Liste des évaluations (<?php echo count($evaluations); ?>)
        </h5>
    </div>
    <div class="card-body">
        <?php if (!empty($evaluations)): ?>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Évaluation</th>
                            <th>Type</th>
                            <th>Classe</th>
                            <th>Matière</th>
                            <th>Enseignant</th>
                            <th>Notes</th>
                            <th>Moyenne</th>
                            <th>Statut</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($evaluations as $evaluation): ?>
                            <tr>
                                <td><?php echo date('d/m/Y', strtotime($evaluation['date_evaluation'])); ?></td>
                                <td>
                                    <strong><?php echo htmlspecialchars($evaluation['nom'] ?? ''); ?></strong>
                                    <?php if ($evaluation['description']): ?>
                                        <br><small class="text-muted"><?php echo htmlspecialchars(substr($evaluation['description'], 0, 50)) . '...'; ?></small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge bg-secondary">
                                        <?php echo ucfirst($evaluation['type_evaluation'] ?? ''); ?>
                                    </span>
                                </td>
                                <td>
                                    <strong><?php echo htmlspecialchars($evaluation['classe_nom'] ?? ''); ?></strong>
                                    <br><small class="text-muted"><?php echo ucfirst($evaluation['niveau'] ?? ''); ?></small>
                                </td>
                                <td>
                                    <strong><?php echo htmlspecialchars($evaluation['matiere_nom'] ?? ''); ?></strong>
                                    <br><small class="text-muted"><?php echo htmlspecialchars($evaluation['matiere_code'] ?? ''); ?></small>
                                </td>
                                <td>
                                    <?php if ($evaluation['enseignant_nom']): ?>
                                        <?php echo htmlspecialchars(($evaluation['enseignant_nom'] ?? '') . ' ' . ($evaluation['enseignant_prenom'] ?? '')); ?>
                                    <?php else: ?>
                                        <span class="text-muted">Non assigné</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <?php
                                    $taux_saisie = $evaluation['nb_eleves_classe'] > 0 ? 
                                        ($evaluation['nb_notes_saisies'] / $evaluation['nb_eleves_classe']) * 100 : 0;
                                    $badge_color = $taux_saisie >= 100 ? 'success' : ($taux_saisie >= 50 ? 'warning' : 'danger');
                                    ?>
                                    <span class="badge bg-<?php echo $badge_color; ?>">
                                        <?php echo $evaluation['nb_notes_saisies']; ?>/<?php echo $evaluation['nb_eleves_classe']; ?>
                                    </span>
                                    <br><small class="text-muted"><?php echo round($taux_saisie, 1); ?>%</small>
                                </td>
                                <td class="text-center">
                                    <?php if ($evaluation['moyenne_evaluation']): ?>
                                        <?php
                                        $moyenne = round($evaluation['moyenne_evaluation'], 2);
                                        $color = $moyenne >= 16 ? 'success' : ($moyenne >= 14 ? 'info' : ($moyenne >= 12 ? 'primary' : ($moyenne >= 10 ? 'warning' : 'danger')));
                                        ?>
                                        <span class="badge bg-<?php echo $color; ?>">
                                            <?php echo $moyenne; ?>/20
                                        </span>
                                        <?php if ($evaluation['ecart_type']): ?>
                                            <br><small class="text-muted">σ: <?php echo round($evaluation['ecart_type'], 2); ?></small>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php
                                    $status_colors = [
                                        'programmee' => 'secondary',
                                        'en_cours' => 'warning',
                                        'terminee' => 'success',
                                        'annulee' => 'danger'
                                    ];
                                    $status_color = $status_colors[$evaluation['status']] ?? 'secondary';
                                    ?>
                                    <span class="badge bg-<?php echo $status_color; ?>">
                                        <?php echo ucfirst(str_replace('_', ' ', $evaluation['status'] ?? '')); ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <a href="../evaluations/view.php?id=<?php echo $evaluation['id']; ?>" 
                                           class="btn btn-outline-info" title="Voir détails">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="../notes/entry.php?evaluation_id=<?php echo $evaluation['id']; ?>" 
                                           class="btn btn-outline-primary" title="Saisir notes">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <a href="../evaluations/edit.php?id=<?php echo $evaluation['id']; ?>" 
                                           class="btn btn-outline-warning" title="Modifier">
                                            <i class="fas fa-pencil-alt"></i>
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
                <i class="fas fa-search fa-3x mb-3"></i>
                <h5>Aucune évaluation trouvée</h5>
                <p>Aucune évaluation ne correspond aux critères de recherche sélectionnés.</p>
                <a href="?<?php echo http_build_query(array_filter(['periode' => $periode_filter, 'classe' => $classe_filter])); ?>" 
                   class="btn btn-outline-primary">
                    <i class="fas fa-times me-1"></i>
                    Réinitialiser les filtres
                </a>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include '../../../includes/footer.php'; ?>
