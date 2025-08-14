<?php
/**
 * Module d'évaluations - Performance des élèves
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

$page_title = 'Performance des élèves';

// Récupérer l'année scolaire active
$current_year = $database->query("SELECT * FROM annees_scolaires WHERE status = 'active' LIMIT 1")->fetch();
if (!$current_year) {
    showMessage('error', 'Aucune année scolaire active trouvée.');
    redirectTo('../../../index.php');
}

// Récupérer les paramètres de filtrage
$classe_filter = (int)($_GET['classe'] ?? 0);
$periode_filter = sanitizeInput($_GET['periode'] ?? '');
$matiere_filter = (int)($_GET['matiere'] ?? 0);
$niveau_filter = sanitizeInput($_GET['niveau'] ?? '');
$limit = (int)($_GET['limit'] ?? 20);

// Récupérer les listes pour les filtres
$classes = $database->query(
    "SELECT * FROM classes WHERE annee_scolaire_id = ? ORDER BY niveau, nom",
    [$current_year['id']]
)->fetchAll();

$matieres = $database->query("SELECT * FROM matieres ORDER BY nom")->fetchAll();

$niveaux = $database->query(
    "SELECT DISTINCT niveau FROM classes WHERE annee_scolaire_id = ? ORDER BY niveau",
    [$current_year['id']]
)->fetchAll();

// Construire la requête avec filtres
$where_conditions = ["i.annee_scolaire_id = ?", "i.status = 'inscrit'"];
$params = [$current_year['id']];

if ($classe_filter) {
    $where_conditions[] = "c.id = ?";
    $params[] = $classe_filter;
}

if ($niveau_filter) {
    $where_conditions[] = "c.niveau = ?";
    $params[] = $niveau_filter;
}

if ($periode_filter) {
    $where_conditions[] = "e.periode = ?";
    $params[] = $periode_filter;
}

if ($matiere_filter) {
    $where_conditions[] = "e.matiere_id = ?";
    $params[] = $matiere_filter;
}

$where_clause = implode(' AND ', $where_conditions);

// Récupérer les performances des élèves
$performances_eleves = $database->query(
    "SELECT el.id, el.nom, el.prenom, el.numero_matricule, el.date_naissance,
            c.nom as classe_nom, c.niveau,
            COUNT(DISTINCT e.id) as nb_evaluations,
            COUNT(n.id) as nb_notes,
            AVG(n.note / e.note_max * 20) as moyenne_generale,
            MIN(n.note / e.note_max * 20) as note_min,
            MAX(n.note / e.note_max * 20) as note_max,
            STDDEV(n.note / e.note_max * 20) as ecart_type,
            COUNT(CASE WHEN (n.note / e.note_max * 20) >= 16 THEN 1 END) as nb_excellentes,
            COUNT(CASE WHEN (n.note / e.note_max * 20) >= 14 THEN 1 END) as nb_tres_bien,
            COUNT(CASE WHEN (n.note / e.note_max * 20) >= 12 THEN 1 END) as nb_bien,
            COUNT(CASE WHEN (n.note / e.note_max * 20) >= 10 THEN 1 END) as nb_assez_bien,
            COUNT(CASE WHEN (n.note / e.note_max * 20) < 10 THEN 1 END) as nb_insuffisantes
     FROM eleves el
     JOIN inscriptions i ON el.id = i.eleve_id
     JOIN classes c ON i.classe_id = c.id
     LEFT JOIN notes n ON el.id = n.eleve_id
     LEFT JOIN evaluations e ON n.evaluation_id = e.id AND e.annee_scolaire_id = i.annee_scolaire_id
     WHERE $where_clause
     GROUP BY el.id, el.nom, el.prenom, el.numero_matricule, c.nom, c.niveau
     HAVING nb_notes > 0
     ORDER BY moyenne_generale DESC, nb_notes DESC
     LIMIT ?",
    array_merge($params, [$limit])
)->fetchAll();

// Statistiques générales
$stats_generales = [
    'total_eleves' => count($performances_eleves),
    'moyenne_classe' => 0,
    'meilleure_moyenne' => 0,
    'moins_bonne_moyenne' => 0,
    'ecart_type_general' => 0
];

if (!empty($performances_eleves)) {
    $moyennes = array_column($performances_eleves, 'moyenne_generale');
    $moyennes = array_filter($moyennes, function($m) { return $m !== null; });
    
    if (!empty($moyennes)) {
        $stats_generales['moyenne_classe'] = array_sum($moyennes) / count($moyennes);
        $stats_generales['meilleure_moyenne'] = max($moyennes);
        $stats_generales['moins_bonne_moyenne'] = min($moyennes);
        
        // Calcul de l'écart-type
        $moyenne_generale = $stats_generales['moyenne_classe'];
        $variance = array_sum(array_map(function($m) use ($moyenne_generale) {
            return pow($m - $moyenne_generale, 2);
        }, $moyennes)) / count($moyennes);
        $stats_generales['ecart_type_general'] = sqrt($variance);
    }
}

// Répartition par tranches de notes
$repartition_notes = [
    'excellent' => 0,    // >= 16
    'tres_bien' => 0,    // >= 14
    'bien' => 0,         // >= 12
    'assez_bien' => 0,   // >= 10
    'insuffisant' => 0   // < 10
];

foreach ($performances_eleves as $eleve) {
    if ($eleve['moyenne_generale']) {
        $moyenne = $eleve['moyenne_generale'];
        if ($moyenne >= 16) $repartition_notes['excellent']++;
        elseif ($moyenne >= 14) $repartition_notes['tres_bien']++;
        elseif ($moyenne >= 12) $repartition_notes['bien']++;
        elseif ($moyenne >= 10) $repartition_notes['assez_bien']++;
        else $repartition_notes['insuffisant']++;
    }
}

include '../../../includes/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">
        <i class="fas fa-user-graduate me-2"></i>
        Performance des élèves
    </h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <div class="btn-group me-2">
            <a href="index.php" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-1"></i>
                Retour aux statistiques
            </a>
        </div>
        <div class="btn-group">
            <a href="class-ranking.php" class="btn btn-outline-primary">
                <i class="fas fa-trophy me-1"></i>
                Classement classes
            </a>
            <a href="../bulletins/" class="btn btn-outline-success">
                <i class="fas fa-file-alt me-1"></i>
                Bulletins
            </a>
        </div>
    </div>
</div>

<!-- Statistiques générales -->
<div class="row mb-4">
    <div class="col-lg-3 col-md-6 mb-3">
        <div class="card bg-primary text-white">
            <div class="card-body text-center">
                <i class="fas fa-users fa-2x mb-2"></i>
                <h3><?php echo $stats_generales['total_eleves']; ?></h3>
                <p class="mb-0">Élèves évalués</p>
            </div>
        </div>
    </div>
    <div class="col-lg-3 col-md-6 mb-3">
        <div class="card bg-info text-white">
            <div class="card-body text-center">
                <i class="fas fa-chart-line fa-2x mb-2"></i>
                <h3><?php echo $stats_generales['moyenne_classe'] ? round($stats_generales['moyenne_classe'], 2) : '0'; ?>/20</h3>
                <p class="mb-0">Moyenne générale</p>
            </div>
        </div>
    </div>
    <div class="col-lg-3 col-md-6 mb-3">
        <div class="card bg-success text-white">
            <div class="card-body text-center">
                <i class="fas fa-trophy fa-2x mb-2"></i>
                <h3><?php echo $stats_generales['meilleure_moyenne'] ? round($stats_generales['meilleure_moyenne'], 2) : '0'; ?>/20</h3>
                <p class="mb-0">Meilleure moyenne</p>
            </div>
        </div>
    </div>
    <div class="col-lg-3 col-md-6 mb-3">
        <div class="card bg-warning text-white">
            <div class="card-body text-center">
                <i class="fas fa-chart-bar fa-2x mb-2"></i>
                <h3><?php echo $stats_generales['ecart_type_general'] ? round($stats_generales['ecart_type_general'], 2) : '0'; ?></h3>
                <p class="mb-0">Écart-type</p>
            </div>
        </div>
    </div>
</div>

<!-- Filtres et répartition -->
<div class="row mb-4">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-filter me-2"></i>
                    Filtres de recherche
                </h5>
            </div>
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-3">
                        <label for="classe" class="form-label">Classe</label>
                        <select class="form-select" id="classe" name="classe">
                            <option value="">Toutes les classes</option>
                            <?php foreach ($classes as $classe): ?>
                                <option value="<?php echo $classe['id']; ?>" 
                                        <?php echo $classe_filter == $classe['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($classe['nom'] ?? ''); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="col-md-2">
                        <label for="niveau" class="form-label">Niveau</label>
                        <select class="form-select" id="niveau" name="niveau">
                            <option value="">Tous</option>
                            <?php foreach ($niveaux as $niveau): ?>
                                <option value="<?php echo $niveau['niveau']; ?>" 
                                        <?php echo $niveau_filter === $niveau['niveau'] ? 'selected' : ''; ?>>
                                    <?php echo ucfirst($niveau['niveau']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
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
                    
                    <div class="col-md-3">
                        <label for="matiere" class="form-label">Matière</label>
                        <select class="form-select" id="matiere" name="matiere">
                            <option value="">Toutes les matières</option>
                            <?php foreach ($matieres as $matiere): ?>
                                <option value="<?php echo $matiere['id']; ?>" 
                                        <?php echo $matiere_filter == $matiere['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($matiere['nom'] ?? ''); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="col-md-2">
                        <label for="limit" class="form-label">Limite</label>
                        <select class="form-select" id="limit" name="limit">
                            <option value="20" <?php echo $limit == 20 ? 'selected' : ''; ?>>20</option>
                            <option value="50" <?php echo $limit == 50 ? 'selected' : ''; ?>>50</option>
                            <option value="100" <?php echo $limit == 100 ? 'selected' : ''; ?>>100</option>
                            <option value="200" <?php echo $limit == 200 ? 'selected' : ''; ?>>200</option>
                        </select>
                    </div>
                    
                    <div class="col-12">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-search me-1"></i>
                            Rechercher
                        </button>
                        <a href="?" class="btn btn-outline-secondary">
                            <i class="fas fa-times me-1"></i>
                            Réinitialiser
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-chart-pie me-2"></i>
                    Répartition des performances
                </h5>
            </div>
            <div class="card-body">
                <?php if ($stats_generales['total_eleves'] > 0): ?>
                    <?php
                    $repartition_data = [
                        ['label' => 'Excellent (≥16)', 'count' => $repartition_notes['excellent'], 'color' => 'success'],
                        ['label' => 'Très bien (≥14)', 'count' => $repartition_notes['tres_bien'], 'color' => 'info'],
                        ['label' => 'Bien (≥12)', 'count' => $repartition_notes['bien'], 'color' => 'primary'],
                        ['label' => 'Assez bien (≥10)', 'count' => $repartition_notes['assez_bien'], 'color' => 'warning'],
                        ['label' => 'Insuffisant (<10)', 'count' => $repartition_notes['insuffisant'], 'color' => 'danger']
                    ];
                    ?>
                    <?php foreach ($repartition_data as $data): ?>
                        <?php $pourcentage = ($data['count'] / $stats_generales['total_eleves']) * 100; ?>
                        <div class="mb-2">
                            <div class="d-flex justify-content-between">
                                <span><?php echo $data['label']; ?></span>
                                <span><strong><?php echo $data['count']; ?> (<?php echo round($pourcentage, 1); ?>%)</strong></span>
                            </div>
                            <div class="progress">
                                <div class="progress-bar bg-<?php echo $data['color']; ?>" 
                                     style="width: <?php echo $pourcentage; ?>%"></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p class="text-muted">Aucune donnée disponible</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Liste des élèves -->
<div class="card">
    <div class="card-header">
        <h5 class="mb-0">
            <i class="fas fa-list me-2"></i>
            Classement des élèves (<?php echo count($performances_eleves); ?>)
        </h5>
    </div>
    <div class="card-body">
        <?php if (!empty($performances_eleves)): ?>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Rang</th>
                            <th>Élève</th>
                            <th>Classe</th>
                            <th>Évaluations</th>
                            <th>Notes</th>
                            <th>Moyenne</th>
                            <th>Min/Max</th>
                            <th>Répartition</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($performances_eleves as $index => $eleve): ?>
                            <tr>
                                <td>
                                    <?php if ($index < 3): ?>
                                        <span class="badge bg-warning"><?php echo $index + 1; ?></span>
                                    <?php else: ?>
                                        <?php echo $index + 1; ?>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <strong><?php echo htmlspecialchars(($eleve['nom'] ?? '') . ' ' . ($eleve['prenom'] ?? '')); ?></strong>
                                    <br><small class="text-muted"><?php echo htmlspecialchars($eleve['numero_matricule'] ?? ''); ?></small>
                                </td>
                                <td>
                                    <strong><?php echo htmlspecialchars($eleve['classe_nom'] ?? ''); ?></strong>
                                    <br><small class="text-muted"><?php echo ucfirst($eleve['niveau'] ?? ''); ?></small>
                                </td>
                                <td class="text-center"><?php echo $eleve['nb_evaluations']; ?></td>
                                <td class="text-center"><?php echo $eleve['nb_notes']; ?></td>
                                <td class="text-center">
                                    <?php if ($eleve['moyenne_generale']): ?>
                                        <?php
                                        $moyenne = round($eleve['moyenne_generale'], 2);
                                        $color = $moyenne >= 16 ? 'success' : ($moyenne >= 14 ? 'info' : ($moyenne >= 12 ? 'primary' : ($moyenne >= 10 ? 'warning' : 'danger')));
                                        ?>
                                        <span class="badge bg-<?php echo $color; ?> fs-6">
                                            <?php echo $moyenne; ?>/20
                                        </span>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <small class="text-muted">
                                        <?php echo $eleve['note_min'] ? round($eleve['note_min'], 1) : '0'; ?> - 
                                        <?php echo $eleve['note_max'] ? round($eleve['note_max'], 1) : '0'; ?>
                                    </small>
                                </td>
                                <td>
                                    <small>
                                        <span class="badge bg-success"><?php echo $eleve['nb_excellentes']; ?></span>
                                        <span class="badge bg-info"><?php echo $eleve['nb_tres_bien'] - $eleve['nb_excellentes']; ?></span>
                                        <span class="badge bg-primary"><?php echo $eleve['nb_bien'] - $eleve['nb_tres_bien']; ?></span>
                                        <span class="badge bg-warning"><?php echo $eleve['nb_assez_bien'] - $eleve['nb_bien']; ?></span>
                                        <span class="badge bg-danger"><?php echo $eleve['nb_insuffisantes']; ?></span>
                                    </small>
                                </td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <a href="../bulletins/individual.php?eleve=<?php echo $eleve['id']; ?>&periode=<?php echo $periode_filter ?: '1er_trimestre'; ?>" 
                                           class="btn btn-outline-primary" title="Bulletin">
                                            <i class="fas fa-file-alt"></i>
                                        </a>
                                        <a href="../notes/entry.php?eleve_id=<?php echo $eleve['id']; ?>" 
                                           class="btn btn-outline-success" title="Notes">
                                            <i class="fas fa-edit"></i>
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
                <i class="fas fa-user-times fa-3x mb-3"></i>
                <h5>Aucun élève trouvé</h5>
                <p>Aucun élève ne correspond aux critères de recherche sélectionnés ou aucune note n'a été saisie.</p>
                <a href="?" class="btn btn-outline-primary">
                    <i class="fas fa-times me-1"></i>
                    Réinitialiser les filtres
                </a>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include '../../../includes/footer.php'; ?>
