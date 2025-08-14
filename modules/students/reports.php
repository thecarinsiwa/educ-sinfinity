<?php
/**
 * Module de gestion des élèves - Rapports et Statistiques
 * Application de gestion scolaire - République Démocratique du Congo
 */

require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../includes/functions.php';

// Vérifier l'authentification et les permissions
requireLogin();
if (!checkPermission('students')) {
    showMessage('error', 'Accès refusé à cette fonctionnalité.');
    redirectTo('index.php');
}

$page_title = 'Rapports et Statistiques - Élèves';

// Récupérer l'année scolaire active
$current_year = $database->query("SELECT * FROM annees_scolaires WHERE status = 'active' LIMIT 1")->fetch();

// Paramètres de filtrage
$selected_year = $_GET['year_id'] ?? ($current_year['id'] ?? 0);
$selected_class = $_GET['class_id'] ?? '';
$report_type = $_GET['type'] ?? 'overview';

// Statistiques générales avec cache simple
$cache_key = "stats_reports_" . $selected_year . "_" . $selected_class . "_" . $report_type;
$cache_duration = 300; // 5 minutes

// Vérifier le cache en session
if (isset($_SESSION[$cache_key]) &&
    isset($_SESSION[$cache_key . '_time']) &&
    (time() - $_SESSION[$cache_key . '_time']) < $cache_duration) {

    // Utiliser les données en cache
    $stats = $_SESSION[$cache_key]['stats'];
    $stats_genre = $_SESSION[$cache_key]['stats_genre'];
    $stats_classes = $_SESSION[$cache_key]['stats_classes'];
    $inscriptions_mensuelles = $_SESSION[$cache_key]['inscriptions_mensuelles'];
    $stats_age = $_SESSION[$cache_key]['stats_age'];
    $dossiers_incomplets = $_SESSION[$cache_key]['dossiers_incomplets'];
    $nouvelles_inscriptions = $_SESSION[$cache_key]['nouvelles_inscriptions'];
    $classes_critiques = $_SESSION[$cache_key]['classes_critiques'];

} else {
    // Recalculer les statistiques
    $stats = [];

    try {
        // Total des élèves inscrits
        $stmt = $database->query(
            "SELECT COUNT(*) as total FROM inscriptions
             WHERE status = 'inscrit' AND annee_scolaire_id = ?",
            [$selected_year]
        );
        $stats['total_eleves'] = $stmt->fetch()['total'];

    // Répartition par genre
    $stats_genre = $database->query(
        "SELECT e.sexe, COUNT(*) as nombre
         FROM eleves e
         JOIN inscriptions i ON e.id = i.eleve_id
         WHERE i.status = 'inscrit' AND i.annee_scolaire_id = ?
         GROUP BY e.sexe",
        [$selected_year]
    )->fetchAll();

    // Répartition par classe
    $stats_classes = $database->query(
        "SELECT c.nom, c.niveau, COUNT(i.id) as effectif,
                ROUND(COUNT(i.id) * 100.0 / (SELECT COUNT(*) FROM inscriptions WHERE status = 'inscrit' AND annee_scolaire_id = ?), 1) as pourcentage
         FROM classes c
         LEFT JOIN inscriptions i ON c.id = i.classe_id AND i.status = 'inscrit'
         WHERE c.annee_scolaire_id = ?
         GROUP BY c.id, c.nom, c.niveau
         ORDER BY c.niveau, c.nom",
        [$selected_year, $selected_year]
    )->fetchAll();

    // Évolution des inscriptions par mois
    $inscriptions_mensuelles = $database->query(
        "SELECT DATE_FORMAT(i.date_inscription, '%Y-%m') as mois,
                DATE_FORMAT(i.date_inscription, '%M %Y') as mois_nom,
                COUNT(*) as nombre
         FROM inscriptions i
         WHERE i.annee_scolaire_id = ?
         GROUP BY DATE_FORMAT(i.date_inscription, '%Y-%m'), DATE_FORMAT(i.date_inscription, '%M %Y')
         ORDER BY mois",
        [$selected_year]
    )->fetchAll();

    // Statistiques d'âge
    $stats_age = $database->query(
        "SELECT 
            CASE 
                WHEN TIMESTAMPDIFF(YEAR, e.date_naissance, CURDATE()) < 6 THEN 'Moins de 6 ans'
                WHEN TIMESTAMPDIFF(YEAR, e.date_naissance, CURDATE()) BETWEEN 6 AND 10 THEN '6-10 ans'
                WHEN TIMESTAMPDIFF(YEAR, e.date_naissance, CURDATE()) BETWEEN 11 AND 15 THEN '11-15 ans'
                WHEN TIMESTAMPDIFF(YEAR, e.date_naissance, CURDATE()) BETWEEN 16 AND 18 THEN '16-18 ans'
                ELSE 'Plus de 18 ans'
            END as tranche_age,
            COUNT(*) as nombre
         FROM eleves e
         JOIN inscriptions i ON e.id = i.eleve_id
         WHERE i.status = 'inscrit' AND i.annee_scolaire_id = ? AND e.date_naissance IS NOT NULL
         GROUP BY tranche_age
         ORDER BY MIN(TIMESTAMPDIFF(YEAR, e.date_naissance, CURDATE()))",
        [$selected_year]
    )->fetchAll();

    // Élèves avec dossiers incomplets
    $dossiers_incomplets = $database->query(
        "SELECT e.*, c.nom as classe_nom, c.niveau,
                CASE 
                    WHEN e.photo IS NULL THEN 'Photo manquante'
                    WHEN e.date_naissance IS NULL THEN 'Date de naissance manquante'
                    WHEN e.lieu_naissance IS NULL THEN 'Lieu de naissance manquant'
                    WHEN e.adresse IS NULL THEN 'Adresse manquante'
                    WHEN e.telephone_parent IS NULL THEN 'Contact parent manquant'
                    ELSE 'Informations incomplètes'
                END as probleme
         FROM eleves e
         JOIN inscriptions i ON e.id = i.eleve_id
         JOIN classes c ON i.classe_id = c.id
         WHERE i.annee_scolaire_id = ? AND i.status = 'inscrit'
         AND (e.photo IS NULL OR e.date_naissance IS NULL 
              OR e.lieu_naissance IS NULL OR e.adresse IS NULL 
              OR e.telephone_parent IS NULL)
         ORDER BY i.date_inscription DESC
         LIMIT 20",
        [$selected_year]
    )->fetchAll();

    // Nouvelles inscriptions (7 derniers jours)
    $nouvelles_inscriptions = $database->query(
        "SELECT e.nom, e.prenom, e.numero_matricule, c.nom as classe_nom, c.niveau, i.date_inscription
         FROM eleves e
         JOIN inscriptions i ON e.id = i.eleve_id
         JOIN classes c ON i.classe_id = c.id
         WHERE i.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
         AND i.annee_scolaire_id = ?
         ORDER BY i.created_at DESC
         LIMIT 10",
        [$selected_year]
    )->fetchAll();

    // Classes avec effectifs critiques (trop pleines ou trop vides)
    $classes_critiques = $database->query(
        "SELECT c.nom, c.niveau, c.capacite_max, COUNT(i.id) as effectif_actuel,
                CASE 
                    WHEN COUNT(i.id) > c.capacite_max THEN 'Surcharge'
                    WHEN COUNT(i.id) < 10 THEN 'Sous-effectif'
                    ELSE 'Normal'
                END as statut
         FROM classes c
         LEFT JOIN inscriptions i ON c.id = i.classe_id AND i.status = 'inscrit'
         WHERE c.annee_scolaire_id = ?
         GROUP BY c.id, c.nom, c.niveau, c.capacite_max
         HAVING statut != 'Normal'
         ORDER BY c.niveau, c.nom",
        [$selected_year]
    )->fetchAll();

    } catch (Exception $e) {
        $stats['total_eleves'] = 0;
        $stats_genre = [];
        $stats_classes = [];
        $inscriptions_mensuelles = [];
        $stats_age = [];
        $dossiers_incomplets = [];
        $nouvelles_inscriptions = [];
        $classes_critiques = [];
    }

    // Sauvegarder en cache
    $_SESSION[$cache_key] = [
        'stats' => $stats,
        'stats_genre' => $stats_genre,
        'stats_classes' => $stats_classes,
        'inscriptions_mensuelles' => $inscriptions_mensuelles,
        'stats_age' => $stats_age,
        'dossiers_incomplets' => $dossiers_incomplets,
        'nouvelles_inscriptions' => $nouvelles_inscriptions,
        'classes_critiques' => $classes_critiques
    ];
    $_SESSION[$cache_key . '_time'] = time();
}

// Récupérer les années scolaires pour le filtre
$annees_scolaires = $database->query(
    "SELECT * FROM annees_scolaires ORDER BY date_debut DESC"
)->fetchAll();

// Récupérer les classes pour le filtre
$classes = $database->query(
    "SELECT * FROM classes WHERE annee_scolaire_id = ? ORDER BY niveau, nom",
    [$selected_year]
)->fetchAll();

include '../../includes/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">
        <i class="fas fa-chart-bar me-2"></i>
        Rapports et Statistiques - Élèves
    </h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <div class="btn-group me-2">
            <a href="index.php" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-1"></i>
                Retour
            </a>
        </div>
        <div class="btn-group me-2">
            <button type="button" class="btn btn-primary dropdown-toggle" data-bs-toggle="dropdown">
                <i class="fas fa-file-export me-1"></i>
                Exporter
            </button>
            <ul class="dropdown-menu">
                <li><a class="dropdown-item" href="#" onclick="exportReport('pdf')">
                    <i class="fas fa-file-pdf me-2"></i>Rapport PDF
                </a></li>
                <li><a class="dropdown-item" href="#" onclick="exportReport('excel')">
                    <i class="fas fa-file-excel me-2"></i>Données Excel
                </a></li>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item" href="attendance/reports/monthly.php">
                    <i class="fas fa-calendar-alt me-2"></i>Rapport d'assiduité
                </a></li>
            </ul>
        </div>
        <div class="btn-group">
            <button type="button" class="btn btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">
                <i class="fas fa-tools me-1"></i>
                Outils
            </button>
            <ul class="dropdown-menu">
                <li><a class="dropdown-item" href="#" onclick="printReport()">
                    <i class="fas fa-print me-2"></i>Imprimer
                </a></li>
                <li><a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#customReportModal">
                    <i class="fas fa-cog me-2"></i>Rapport personnalisé
                </a></li>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item" href="transfers/reports/transfers.php">
                    <i class="fas fa-exchange-alt me-2"></i>Rapports de transferts
                </a></li>
            </ul>
        </div>
    </div>
</div>

<!-- Filtres -->
<div class="card mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-md-4">
                <label for="year_id" class="form-label">Année scolaire</label>
                <select class="form-select" id="year_id" name="year_id" onchange="this.form.submit()">
                    <?php foreach ($annees_scolaires as $annee): ?>
                        <option value="<?php echo $annee['id']; ?>" <?php echo $selected_year == $annee['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($annee['annee']); ?>
                            <?php echo $annee['status'] === 'active' ? ' (Active)' : ''; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label for="class_id" class="form-label">Classe (optionnel)</label>
                <select class="form-select" id="class_id" name="class_id" onchange="this.form.submit()">
                    <option value="">Toutes les classes</option>
                    <?php foreach ($classes as $classe): ?>
                        <option value="<?php echo $classe['id']; ?>" <?php echo $selected_class == $classe['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($classe['niveau'] . ' - ' . $classe['nom']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label for="type" class="form-label">Type de rapport</label>
                <select class="form-select" id="type" name="type" onchange="this.form.submit()">
                    <option value="overview" <?php echo $report_type === 'overview' ? 'selected' : ''; ?>>Vue d'ensemble</option>
                    <option value="demographics" <?php echo $report_type === 'demographics' ? 'selected' : ''; ?>>Démographie</option>
                    <option value="enrollment" <?php echo $report_type === 'enrollment' ? 'selected' : ''; ?>>Inscriptions</option>
                    <option value="classes" <?php echo $report_type === 'classes' ? 'selected' : ''; ?>>Répartition par classe</option>
                    <option value="issues" <?php echo $report_type === 'issues' ? 'selected' : ''; ?>>Problèmes à résoudre</option>
                </select>
            </div>
        </form>
    </div>
</div>

<!-- Statistiques principales -->
<div class="row mb-4">
    <div class="col-md-3">
        <div class="card text-center">
            <div class="card-body">
                <i class="fas fa-users fa-2x text-primary mb-2"></i>
                <h3 class="mb-0"><?php echo number_format($stats['total_eleves']); ?></h3>
                <small class="text-muted">Total élèves inscrits</small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-center">
            <div class="card-body">
                <i class="fas fa-school fa-2x text-success mb-2"></i>
                <h3 class="mb-0"><?php echo count($stats_classes); ?></h3>
                <small class="text-muted">Classes actives</small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-center">
            <div class="card-body">
                <i class="fas fa-user-plus fa-2x text-info mb-2"></i>
                <h3 class="mb-0"><?php echo count($nouvelles_inscriptions); ?></h3>
                <small class="text-muted">Nouvelles inscriptions (7j)</small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-center">
            <div class="card-body">
                <i class="fas fa-exclamation-triangle fa-2x text-warning mb-2"></i>
                <h3 class="mb-0"><?php echo count($dossiers_incomplets); ?></h3>
                <small class="text-muted">Dossiers incomplets</small>
            </div>
        </div>
    </div>
</div>

<?php if ($report_type === 'overview' || $report_type === 'demographics'): ?>
    <!-- Répartition par genre -->
    <div class="row mb-4">
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-venus-mars me-2"></i>
                        Répartition par genre
                    </h5>
                </div>
                <div class="card-body">
                    <?php if (!empty($stats_genre)): ?>
                        <canvas id="genderChart" width="400" height="200"></canvas>
                        <div class="mt-3">
                            <div class="row text-center">
                                <?php foreach ($stats_genre as $genre): ?>
                                    <div class="col">
                                        <h4 class="mb-0"><?php echo $genre['nombre']; ?></h4>
                                        <small class="text-muted">
                                            <?php echo $genre['sexe'] === 'M' ? 'Garçons' : 'Filles'; ?>
                                            (<?php echo round($genre['nombre'] * 100 / $stats['total_eleves'], 1); ?>%)
                                        </small>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="text-center text-muted py-4">
                            <i class="fas fa-chart-pie fa-3x mb-3"></i>
                            <p>Aucune donnée de genre disponible</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-birthday-cake me-2"></i>
                        Répartition par âge
                    </h5>
                </div>
                <div class="card-body">
                    <?php if (!empty($stats_age)): ?>
                        <canvas id="ageChart" width="400" height="200"></canvas>
                        <div class="mt-3">
                            <?php foreach ($stats_age as $age): ?>
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <span><?php echo htmlspecialchars($age['tranche_age']); ?></span>
                                    <span class="badge bg-primary"><?php echo $age['nombre']; ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="text-center text-muted py-4">
                            <i class="fas fa-chart-bar fa-3x mb-3"></i>
                            <p>Aucune donnée d'âge disponible</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php if ($report_type === 'overview' || $report_type === 'classes'): ?>
    <!-- Répartition par classe -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-school me-2"></i>
                        Effectifs par classe
                    </h5>
                </div>
                <div class="card-body">
                    <?php if (!empty($stats_classes)): ?>
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Niveau</th>
                                        <th>Classe</th>
                                        <th>Effectif</th>
                                        <th>Pourcentage</th>
                                        <th>Statut</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($stats_classes as $classe): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($classe['niveau']); ?></td>
                                            <td><?php echo htmlspecialchars($classe['nom']); ?></td>
                                            <td>
                                                <span class="badge bg-primary fs-6"><?php echo $classe['effectif']; ?></span>
                                            </td>
                                            <td>
                                                <div class="progress" style="height: 20px;">
                                                    <div class="progress-bar" role="progressbar"
                                                         style="width: <?php echo $classe['pourcentage']; ?>%">
                                                        <?php echo $classe['pourcentage']; ?>%
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <?php
                                                $status_class = 'success';
                                                $status_text = 'Normal';
                                                if ($classe['effectif'] == 0) {
                                                    $status_class = 'secondary';
                                                    $status_text = 'Vide';
                                                } elseif ($classe['effectif'] < 10) {
                                                    $status_class = 'warning';
                                                    $status_text = 'Sous-effectif';
                                                } elseif ($classe['effectif'] > 40) {
                                                    $status_class = 'danger';
                                                    $status_text = 'Surcharge';
                                                }
                                                ?>
                                                <span class="badge bg-<?php echo $status_class; ?>">
                                                    <?php echo $status_text; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <a href="list.php?class_id=<?php echo $classe['id'] ?? ''; ?>"
                                                   class="btn btn-sm btn-outline-primary">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="text-center text-muted py-4">
                            <i class="fas fa-school fa-3x mb-3"></i>
                            <p>Aucune classe trouvée pour cette année scolaire</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php if ($report_type === 'overview' || $report_type === 'enrollment'): ?>
    <!-- Évolution des inscriptions -->
    <div class="row mb-4">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-chart-line me-2"></i>
                        Évolution des inscriptions
                    </h5>
                </div>
                <div class="card-body">
                    <?php if (!empty($inscriptions_mensuelles)): ?>
                        <canvas id="enrollmentChart" width="400" height="200"></canvas>
                    <?php else: ?>
                        <div class="text-center text-muted py-4">
                            <i class="fas fa-chart-line fa-3x mb-3"></i>
                            <p>Aucune donnée d'inscription disponible</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-user-plus me-2"></i>
                        Nouvelles inscriptions
                    </h5>
                </div>
                <div class="card-body">
                    <?php if (!empty($nouvelles_inscriptions)): ?>
                        <div class="list-group list-group-flush">
                            <?php foreach ($nouvelles_inscriptions as $inscription): ?>
                                <div class="list-group-item px-0">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div>
                                            <h6 class="mb-1">
                                                <?php echo htmlspecialchars($inscription['nom'] . ' ' . $inscription['prenom']); ?>
                                            </h6>
                                            <p class="mb-1 text-muted small">
                                                <?php echo htmlspecialchars($inscription['classe_nom']); ?>
                                            </p>
                                            <small class="text-muted">
                                                <?php echo formatDate($inscription['date_inscription']); ?>
                                            </small>
                                        </div>
                                        <span class="badge bg-success">Nouveau</span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="text-center text-muted py-4">
                            <i class="fas fa-user-plus fa-2x mb-3"></i>
                            <p>Aucune nouvelle inscription</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php if ($report_type === 'overview' || $report_type === 'issues'): ?>
    <!-- Problèmes à résoudre -->
    <div class="row mb-4">
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        Dossiers incomplets
                    </h5>
                </div>
                <div class="card-body">
                    <?php if (!empty($dossiers_incomplets)): ?>
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Élève</th>
                                        <th>Classe</th>
                                        <th>Problème</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach (array_slice($dossiers_incomplets, 0, 10) as $dossier): ?>
                                        <tr>
                                            <td>
                                                <small>
                                                    <?php echo htmlspecialchars($dossier['nom'] . ' ' . $dossier['prenom']); ?>
                                                    <br><span class="text-muted"><?php echo htmlspecialchars($dossier['numero_matricule']); ?></span>
                                                </small>
                                            </td>
                                            <td>
                                                <small><?php echo htmlspecialchars($dossier['classe_nom']); ?></small>
                                            </td>
                                            <td>
                                                <span class="badge bg-warning text-dark">
                                                    <?php echo htmlspecialchars($dossier['probleme']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <a href="records/edit.php?id=<?php echo $dossier['id']; ?>"
                                                   class="btn btn-sm btn-outline-primary">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php if (count($dossiers_incomplets) > 10): ?>
                            <div class="text-center mt-3">
                                <small class="text-muted">
                                    ... et <?php echo count($dossiers_incomplets) - 10; ?> autres dossiers
                                </small>
                            </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <div class="text-center text-muted py-4">
                            <i class="fas fa-check-circle fa-2x text-success mb-3"></i>
                            <p>Tous les dossiers sont complets !</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-users-slash me-2"></i>
                        Classes avec effectifs critiques
                    </h5>
                </div>
                <div class="card-body">
                    <?php if (!empty($classes_critiques)): ?>
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Classe</th>
                                        <th>Effectif</th>
                                        <th>Capacité</th>
                                        <th>Statut</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($classes_critiques as $classe): ?>
                                        <tr>
                                            <td>
                                                <small>
                                                    <?php echo htmlspecialchars($classe['niveau'] . ' - ' . $classe['nom']); ?>
                                                </small>
                                            </td>
                                            <td>
                                                <span class="badge bg-primary"><?php echo $classe['effectif_actuel']; ?></span>
                                            </td>
                                            <td>
                                                <small><?php echo $classe['capacite_max'] ?: 'Non définie'; ?></small>
                                            </td>
                                            <td>
                                                <?php
                                                $status_class = $classe['statut'] === 'Surcharge' ? 'danger' : 'warning';
                                                ?>
                                                <span class="badge bg-<?php echo $status_class; ?>">
                                                    <?php echo htmlspecialchars($classe['statut']); ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="text-center text-muted py-4">
                            <i class="fas fa-check-circle fa-2x text-success mb-3"></i>
                            <p>Tous les effectifs sont normaux</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<!-- Actions rapides -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-bolt me-2"></i>
                    Actions rapides
                </h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-lg-3 col-md-6 mb-3">
                        <a href="list.php" class="btn btn-outline-primary w-100">
                            <i class="fas fa-list me-2"></i>
                            Liste complète des élèves
                        </a>
                    </div>
                    <div class="col-lg-3 col-md-6 mb-3">
                        <a href="attendance/reports/monthly.php" class="btn btn-outline-info w-100">
                            <i class="fas fa-calendar-check me-2"></i>
                            Rapport d'assiduité
                        </a>
                    </div>
                    <div class="col-lg-3 col-md-6 mb-3">
                        <a href="transfers/reports/transfers.php" class="btn btn-outline-warning w-100">
                            <i class="fas fa-exchange-alt me-2"></i>
                            Rapports de transferts
                        </a>
                    </div>
                    <div class="col-lg-3 col-md-6 mb-3">
                        <a href="admissions/index.php" class="btn btn-outline-success w-100">
                            <i class="fas fa-user-plus me-2"></i>
                            Gestion des admissions
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal pour rapport personnalisé -->
<div class="modal fade" id="customReportModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-cog me-2"></i>
                    Créer un rapport personnalisé
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="customReportForm">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="custom_year" class="form-label">Année scolaire</label>
                            <select class="form-select" id="custom_year" name="year_id">
                                <?php foreach ($annees_scolaires as $annee): ?>
                                    <option value="<?php echo $annee['id']; ?>" <?php echo $selected_year == $annee['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($annee['annee']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="custom_class" class="form-label">Classe</label>
                            <select class="form-select" id="custom_class" name="class_id">
                                <option value="">Toutes les classes</option>
                                <?php foreach ($classes as $classe): ?>
                                    <option value="<?php echo $classe['id']; ?>">
                                        <?php echo htmlspecialchars($classe['niveau'] . ' - ' . $classe['nom']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Sections à inclure</label>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="include_demographics" checked>
                            <label class="form-check-label" for="include_demographics">
                                Données démographiques
                            </label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="include_classes" checked>
                            <label class="form-check-label" for="include_classes">
                                Répartition par classe
                            </label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="include_enrollment" checked>
                            <label class="form-check-label" for="include_enrollment">
                                Évolution des inscriptions
                            </label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="include_issues">
                            <label class="form-check-label" for="include_issues">
                                Problèmes à résoudre
                            </label>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="fas fa-times me-1"></i>
                    Annuler
                </button>
                <button type="button" class="btn btn-primary" onclick="generateCustomReport()">
                    <i class="fas fa-chart-bar me-1"></i>
                    Générer le rapport
                </button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Données pour les graphiques
const genderData = <?php echo json_encode($stats_genre); ?>;
const ageData = <?php echo json_encode($stats_age); ?>;
const enrollmentData = <?php echo json_encode($inscriptions_mensuelles); ?>;

// Graphique de répartition par genre
if (document.getElementById('genderChart') && genderData.length > 0) {
    const genderCtx = document.getElementById('genderChart').getContext('2d');
    new Chart(genderCtx, {
        type: 'doughnut',
        data: {
            labels: genderData.map(item => item.sexe === 'M' ? 'Garçons' : 'Filles'),
            datasets: [{
                data: genderData.map(item => item.nombre),
                backgroundColor: ['#007bff', '#e83e8c'],
                borderWidth: 2
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom'
                }
            }
        }
    });
}

// Graphique de répartition par âge
if (document.getElementById('ageChart') && ageData.length > 0) {
    const ageCtx = document.getElementById('ageChart').getContext('2d');
    new Chart(ageCtx, {
        type: 'bar',
        data: {
            labels: ageData.map(item => item.tranche_age),
            datasets: [{
                label: 'Nombre d\'élèves',
                data: ageData.map(item => item.nombre),
                backgroundColor: '#28a745',
                borderColor: '#1e7e34',
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        stepSize: 1
                    }
                }
            },
            plugins: {
                legend: {
                    display: false
                }
            }
        }
    });
}

// Graphique d'évolution des inscriptions
if (document.getElementById('enrollmentChart') && enrollmentData.length > 0) {
    const enrollmentCtx = document.getElementById('enrollmentChart').getContext('2d');
    new Chart(enrollmentCtx, {
        type: 'line',
        data: {
            labels: enrollmentData.map(item => item.mois_nom || item.mois),
            datasets: [{
                label: 'Inscriptions',
                data: enrollmentData.map(item => item.nombre),
                borderColor: '#007bff',
                backgroundColor: 'rgba(0, 123, 255, 0.1)',
                borderWidth: 2,
                fill: true,
                tension: 0.4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        stepSize: 1
                    }
                }
            },
            plugins: {
                legend: {
                    display: false
                }
            }
        }
    });
}

// Fonctions d'export et d'impression
function exportReport(format) {
    const params = new URLSearchParams(window.location.search);
    params.set('export', format);

    if (format === 'pdf') {
        window.open('exports/students-report.php?' + params.toString(), '_blank');
    } else if (format === 'excel') {
        window.location.href = 'exports/students-report.php?' + params.toString();
    }
}

function printReport() {
    window.print();
}

function generateCustomReport() {
    const form = document.getElementById('customReportForm');
    const formData = new FormData(form);

    // Construire l'URL avec les paramètres
    const params = new URLSearchParams();
    params.set('year_id', formData.get('year_id'));
    if (formData.get('class_id')) {
        params.set('class_id', formData.get('class_id'));
    }

    // Déterminer le type de rapport basé sur les sections sélectionnées
    const sections = [];
    if (document.getElementById('include_demographics').checked) sections.push('demographics');
    if (document.getElementById('include_classes').checked) sections.push('classes');
    if (document.getElementById('include_enrollment').checked) sections.push('enrollment');
    if (document.getElementById('include_issues').checked) sections.push('issues');

    if (sections.length === 0) {
        alert('Veuillez sélectionner au moins une section à inclure.');
        return;
    }

    params.set('type', sections.length === 1 ? sections[0] : 'overview');

    // Fermer le modal et rediriger
    const modal = bootstrap.Modal.getInstance(document.getElementById('customReportModal'));
    modal.hide();

    window.location.href = 'reports.php?' + params.toString();
}

// Mise à jour des classes lors du changement d'année
document.getElementById('year_id').addEventListener('change', function() {
    // Cette fonctionnalité pourrait être améliorée avec AJAX
    // Pour l'instant, on soumet le formulaire
});

// Styles pour l'impression
const printStyles = `
    @media print {
        .btn-toolbar, .card-header .dropdown, .no-print {
            display: none !important;
        }
        .card {
            border: 1px solid #dee2e6 !important;
            box-shadow: none !important;
        }
        .page-break {
            page-break-before: always;
        }
    }
`;

// Ajouter les styles d'impression
const styleSheet = document.createElement('style');
styleSheet.textContent = printStyles;
document.head.appendChild(styleSheet);
</script>

<?php include '../../includes/footer.php'; ?>
