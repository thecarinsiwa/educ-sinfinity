<?php
/**
 * Module Suivi des Absences et Retards - Page principale
 * Application de gestion scolaire - République Démocratique du Congo
 */

require_once '../../../config/config.php';
require_once '../../../config/database.php';
require_once '../../../includes/functions.php';

// Vérifier l'authentification et les permissions
requireLogin();
if (!checkPermission('students') && !checkPermission('students_view')) {
    showMessage('error', 'Accès refusé à ce module.');
    redirectTo('../index.php');
}

$page_title = 'Suivi des Absences et Retards';

// Obtenir l'année scolaire actuelle
$current_year = getCurrentAcademicYear();

// Paramètres de filtrage
$date_filter = sanitizeInput($_GET['date'] ?? date('Y-m-d'));
$classe_filter = (int)($_GET['classe'] ?? 0);
$type_filter = sanitizeInput($_GET['type'] ?? '');

// Statistiques d'assiduité
$stats = [];

// Absences aujourd'hui
$stmt = $database->query(
    "SELECT COUNT(*) as total FROM absences 
     WHERE DATE(date_absence) = CURDATE() AND type_absence = 'absence'",
    []
);
$stats['absences_aujourd_hui'] = $stmt->fetch()['total'];

// Retards aujourd'hui
$stmt = $database->query(
    "SELECT COUNT(*) as total FROM absences 
     WHERE DATE(date_absence) = CURDATE() AND type_absence = 'retard'",
    []
);
$stats['retards_aujourd_hui'] = $stmt->fetch()['total'];

// Absences cette semaine
$stmt = $database->query(
    "SELECT COUNT(*) as total FROM absences 
     WHERE WEEK(date_absence) = WEEK(CURDATE()) 
     AND YEAR(date_absence) = YEAR(CURDATE()) 
     AND type_absence = 'absence'",
    []
);
$stats['absences_semaine'] = $stmt->fetch()['total'];

// Élèves avec absences répétées (plus de 3 absences ce mois)
$stmt = $database->query(
    "SELECT COUNT(DISTINCT eleve_id) as total FROM absences 
     WHERE MONTH(date_absence) = MONTH(CURDATE()) 
     AND YEAR(date_absence) = YEAR(CURDATE()) 
     AND type_absence = 'absence'
     GROUP BY eleve_id
     HAVING COUNT(*) > 3",
    []
);
$stats['eleves_absenteisme'] = count($stmt->fetchAll());

// Absences du jour sélectionné avec informations utilisateur
$params = [$date_filter, $current_year['id'] ?? 0];
$where_conditions = ["DATE(a.date_absence) = ?", "i.annee_scolaire_id = ?"];

if ($classe_filter) {
    $where_conditions[] = "c.id = ?";
    $params[] = $classe_filter;
}

if ($type_filter) {
    $where_conditions[] = "a.type_absence = ?";
    $params[] = $type_filter;
}

$absences_jour = $database->query(
    "SELECT a.*, e.nom, e.prenom, e.numero_matricule,
            c.nom as classe_nom, c.niveau,
            u_valide.username as valide_par_username,
            u_valide.nom as valide_par_nom,
            u_valide.prenom as valide_par_prenom,
            a.updated_at
     FROM absences a
     JOIN eleves e ON a.eleve_id = e.id
     JOIN inscriptions i ON e.id = i.eleve_id
     JOIN classes c ON i.classe_id = c.id
     LEFT JOIN users u_valide ON a.valide_par = u_valide.id
     WHERE " . implode(" AND ", $where_conditions) . "
     ORDER BY a.created_at DESC",
    $params
)->fetchAll();

// Élèves avec le plus d'absences ce mois
$eleves_absents = $database->query(
    "SELECT e.nom, e.prenom, e.numero_matricule, c.nom as classe_nom,
            COUNT(CASE WHEN a.type_absence = 'absence' THEN 1 END) as nb_absences,
            COUNT(CASE WHEN a.type_absence = 'retard' THEN 1 END) as nb_retards,
            COUNT(*) as total_incidents
     FROM eleves e
     JOIN inscriptions i ON e.id = i.eleve_id
     JOIN classes c ON i.classe_id = c.id
     JOIN absences a ON e.id = a.eleve_id
     WHERE MONTH(a.date_absence) = MONTH(CURDATE()) 
     AND YEAR(a.date_absence) = YEAR(CURDATE())
     AND i.annee_scolaire_id = ?
     GROUP BY e.id, e.nom, e.prenom, e.numero_matricule, c.nom
     ORDER BY total_incidents DESC
     LIMIT 10",
    [$current_year['id'] ?? 0]
)->fetchAll();

// Statistiques par classe
$stats_par_classe = $database->query(
    "SELECT c.nom as classe_nom, c.niveau,
            COUNT(DISTINCT i.eleve_id) as nb_eleves,
            COUNT(CASE WHEN a.type_absence = 'absence' AND DATE(a.date_absence) = CURDATE() THEN 1 END) as absences_jour,
            COUNT(CASE WHEN a.type_absence = 'retard' AND DATE(a.date_absence) = CURDATE() THEN 1 END) as retards_jour
     FROM classes c
     JOIN inscriptions i ON c.id = i.classe_id
     LEFT JOIN absences a ON i.eleve_id = a.eleve_id
     WHERE c.annee_scolaire_id = ? AND i.status = 'inscrit'
     GROUP BY c.id, c.nom, c.niveau
     ORDER BY c.niveau, c.nom",
    [$current_year['id'] ?? 0]
)->fetchAll();

// Évolution des absences (7 derniers jours)
$evolution_absences = $database->query(
    "SELECT DATE(date_absence) as date_abs,
            COUNT(CASE WHEN type_absence = 'absence' THEN 1 END) as absences,
            COUNT(CASE WHEN type_absence = 'retard' THEN 1 END) as retards
     FROM absences 
     WHERE date_absence >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
     GROUP BY DATE(date_absence)
     ORDER BY date_abs",
    []
)->fetchAll();

// Motifs d'absence les plus fréquents
$motifs_frequents = $database->query(
    "SELECT motif, COUNT(*) as nombre
     FROM absences 
     WHERE MONTH(date_absence) = MONTH(CURDATE()) 
     AND YEAR(date_absence) = YEAR(CURDATE())
     AND motif IS NOT NULL AND motif != ''
     GROUP BY motif
     ORDER BY nombre DESC
     LIMIT 8",
    []
)->fetchAll();

// Récupérer les classes pour le filtre
$classes = $database->query(
    "SELECT id, nom, niveau FROM classes WHERE annee_scolaire_id = ? ORDER BY niveau, nom",
    [$current_year['id'] ?? 0]
)->fetchAll();

include '../../../includes/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">
        <i class="fas fa-calendar-check me-2"></i>
        Suivi des Absences et Retards
    </h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <div class="btn-group me-2">
            <a href="../index.php" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-1"></i>
                Retour
            </a>
        </div>
        <?php if (checkPermission('students')): ?>
            <div class="btn-group me-2">
                <button type="button" class="btn btn-primary dropdown-toggle" data-bs-toggle="dropdown">
                    <i class="fas fa-plus me-1"></i>
                    Nouveau
                </button>
                <ul class="dropdown-menu">
                    <li><a class="dropdown-item" href="add-absence.php">
                        <i class="fas fa-user-times me-2"></i>Signaler absence
                    </a></li>
                    <li><a class="dropdown-item" href="add-delay.php">
                        <i class="fas fa-clock me-2"></i>Signaler retard
                    </a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item" href="bulk-attendance.php">
                        <i class="fas fa-list-check me-2"></i>Appel de classe
                    </a></li>
                </ul>
            </div>
        <?php endif; ?>
        <div class="btn-group">
            <button type="button" class="btn btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">
                <i class="fas fa-tools me-1"></i>
                Outils
            </button>
            <ul class="dropdown-menu">
                <li><a class="dropdown-item" href="reports/monthly.php">
                    <i class="fas fa-chart-bar me-2"></i>Rapport mensuel
                </a></li>
                <li><a class="dropdown-item" href="exports/attendance.php">
                    <i class="fas fa-file-export me-2"></i>Exporter données
                </a></li>
                <li><a class="dropdown-item" href="notifications/parents.php">
                    <i class="fas fa-bell me-2"></i>Notifier parents
                </a></li>
            </ul>
        </div>
    </div>
</div>

<!-- Statistiques rapides -->
<div class="row mb-4">
    <div class="col-lg-3 col-md-6 mb-3">
        <div class="card text-white bg-danger">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h4><?php echo $stats['absences_aujourd_hui']; ?></h4>
                        <p class="mb-0">Absences aujourd'hui</p>
                    </div>
                    <div class="align-self-center">
                        <i class="fas fa-user-times fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-3 col-md-6 mb-3">
        <div class="card text-white bg-warning">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h4><?php echo $stats['retards_aujourd_hui']; ?></h4>
                        <p class="mb-0">Retards aujourd'hui</p>
                    </div>
                    <div class="align-self-center">
                        <i class="fas fa-clock fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-3 col-md-6 mb-3">
        <div class="card text-white bg-info">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h4><?php echo $stats['absences_semaine']; ?></h4>
                        <p class="mb-0">Absences cette semaine</p>
                    </div>
                    <div class="align-self-center">
                        <i class="fas fa-calendar-week fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-3 col-md-6 mb-3">
        <div class="card text-white bg-secondary">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h4><?php echo $stats['eleves_absenteisme']; ?></h4>
                        <p class="mb-0">Absentéisme répété</p>
                    </div>
                    <div class="align-self-center">
                        <i class="fas fa-exclamation-triangle fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Filtres -->
<div class="card mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-md-3">
                <label for="date" class="form-label">Date</label>
                <input type="date" class="form-control" id="date" name="date" 
                       value="<?php echo htmlspecialchars($date_filter); ?>">
            </div>
            <div class="col-md-3">
                <label for="classe" class="form-label">Classe</label>
                <select class="form-select" id="classe" name="classe">
                    <option value="">Toutes les classes</option>
                    <?php foreach ($classes as $classe): ?>
                        <option value="<?php echo $classe['id']; ?>" 
                                <?php echo $classe_filter == $classe['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($classe['nom']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label for="type" class="form-label">Type</label>
                <select class="form-select" id="type" name="type">
                    <option value="">Tous les types</option>
                    <option value="absence" <?php echo $type_filter === 'absence' ? 'selected' : ''; ?>>Absences</option>
                    <option value="retard" <?php echo $type_filter === 'retard' ? 'selected' : ''; ?>>Retards</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">&nbsp;</label>
                <div class="d-grid">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-filter me-1"></i>
                        Filtrer
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Contenu principal -->
<div class="row">
    <div class="col-lg-8">
        <!-- Absences du jour -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-calendar-day me-2"></i>
                    Absences et retards du <?php echo formatDate($date_filter); ?>
                    <?php if (!empty($absences_jour)): ?>
                        <span class="badge bg-secondary"><?php echo count($absences_jour); ?></span>
                    <?php endif; ?>
                </h5>
            </div>
            <div class="card-body">
                <?php if (!empty($absences_jour)): ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Heure</th>
                                    <th>Élève</th>
                                    <th>Classe</th>
                                    <th>Type</th>
                                    <th>Motif</th>
                                    <th>Justifiée</th>
                                    <th>Saisi par</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($absences_jour as $absence): ?>
                                    <tr>
                                        <td><?php echo date('H:i', strtotime($absence['date_absence'])); ?></td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($absence['nom'] . ' ' . $absence['prenom']); ?></strong>
                                            <br><small class="text-muted">
                                                <?php echo htmlspecialchars($absence['numero_matricule']); ?>
                                            </small>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?php 
                                                echo $absence['niveau'] === 'maternelle' ? 'warning' : 
                                                    ($absence['niveau'] === 'primaire' ? 'success' : 'primary'); 
                                            ?>">
                                                <?php echo htmlspecialchars($absence['classe_nom']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?php echo $absence['type_absence'] === 'absence' ? 'danger' : 'warning'; ?>">
                                                <?php echo ucfirst($absence['type_absence']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($absence['motif']): ?>
                                                <small><?php echo htmlspecialchars($absence['motif']); ?></small>
                                            <?php else: ?>
                                                <span class="text-muted">Non spécifié</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php $is_justified = in_array($absence['type_absence'], ['absence_justifiee', 'retard_justifie']); ?>
                                            <span class="badge bg-<?php echo $is_justified ? 'success' : 'secondary'; ?>">
                                                <?php echo $is_justified ? 'Oui' : 'Non'; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($absence['valide_par_username']): ?>
                                                <div>
                                                    <strong><?php echo htmlspecialchars($absence['valide_par_nom'] . ' ' . $absence['valide_par_prenom']); ?></strong>
                                                    <br><small class="text-muted">
                                                        @<?php echo htmlspecialchars($absence['valide_par_username']); ?>
                                                        <br><?php echo formatDateTime($absence['created_at']); ?>
                                                        <?php if ($absence['date_validation']): ?>
                                                            <br><em class="text-success">
                                                                <i class="fas fa-check"></i>
                                                                Validé le: <?php echo formatDateTime($absence['date_validation']); ?>
                                                            </em>
                                                        <?php endif; ?>
                                                    </small>
                                                </div>
                                            <?php else: ?>
                                                <span class="text-muted">
                                                    <i class="fas fa-question-circle"></i> Non renseigné
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <button type="button" class="btn btn-outline-info"
                                                        onclick="showAbsenceHistory(<?php echo $absence['id']; ?>)"
                                                        title="Historique">
                                                    <i class="fas fa-history"></i>
                                                </button>
                                                <?php if (checkPermission('students')): ?>
                                                    <a href="edit.php?id=<?php echo $absence['id']; ?>"
                                                       class="btn btn-outline-primary" title="Modifier">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                    <?php if (!in_array($absence['type_absence'], ['absence_justifiee', 'retard_justifie'])): ?>
                                                        <button type="button" class="btn btn-outline-success"
                                                                onclick="justifyAbsence(<?php echo $absence['id']; ?>)"
                                                                title="Justifier">
                                                            <i class="fas fa-check"></i>
                                                        </button>
                                                    <?php endif; ?>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="text-center py-4">
                        <i class="fas fa-calendar-check fa-3x text-success mb-3"></i>
                        <h5 class="text-success">Aucune absence signalée</h5>
                        <p class="text-muted">
                            Tous les élèves étaient présents le <?php echo formatDate($date_filter); ?>.
                        </p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Évolution des absences -->
        <?php if (!empty($evolution_absences)): ?>
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-chart-line me-2"></i>
                    Évolution des absences (7 derniers jours)
                </h5>
            </div>
            <div class="card-body">
                <canvas id="evolutionChart" width="100%" height="300"></canvas>
            </div>
        </div>
        <?php endif; ?>
    </div>
    
    <div class="col-lg-4">
        <!-- Élèves les plus absents -->
        <div class="card mb-4">
            <div class="card-header">
                <h6 class="mb-0">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    Élèves les plus absents (ce mois)
                </h6>
            </div>
            <div class="card-body">
                <?php if (!empty($eleves_absents)): ?>
                    <?php foreach (array_slice($eleves_absents, 0, 8) as $eleve): ?>
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <div>
                                <strong><?php echo htmlspecialchars($eleve['nom'] . ' ' . substr($eleve['prenom'], 0, 1) . '.'); ?></strong>
                                <br><small class="text-muted">
                                    <?php echo htmlspecialchars($eleve['classe_nom']); ?>
                                </small>
                            </div>
                            <div class="text-end">
                                <span class="badge bg-danger"><?php echo $eleve['nb_absences']; ?> abs.</span>
                                <span class="badge bg-warning"><?php echo $eleve['nb_retards']; ?> ret.</span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="text-center py-3">
                        <i class="fas fa-smile fa-2x text-success mb-2"></i>
                        <p class="text-muted mb-0">Excellente assiduité ce mois !</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Statistiques par classe -->
        <div class="card mb-4">
            <div class="card-header">
                <h6 class="mb-0">
                    <i class="fas fa-school me-2"></i>
                    Assiduité par classe (aujourd'hui)
                </h6>
            </div>
            <div class="card-body">
                <?php if (!empty($stats_par_classe)): ?>
                    <?php foreach ($stats_par_classe as $stat): ?>
                        <div class="mb-3">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <span class="badge bg-<?php 
                                    echo $stat['niveau'] === 'maternelle' ? 'warning' : 
                                        ($stat['niveau'] === 'primaire' ? 'success' : 'primary'); 
                                ?>">
                                    <?php echo htmlspecialchars($stat['classe_nom']); ?>
                                </span>
                                <small class="text-muted">
                                    <?php echo $stat['nb_eleves']; ?> élèves
                                </small>
                            </div>
                            <div class="d-flex justify-content-between">
                                <small class="text-danger">
                                    <?php echo $stat['absences_jour']; ?> absences
                                </small>
                                <small class="text-warning">
                                    <?php echo $stat['retards_jour']; ?> retards
                                </small>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p class="text-muted text-center">Aucune donnée disponible</p>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Motifs fréquents -->
        <?php if (!empty($motifs_frequents)): ?>
        <div class="card">
            <div class="card-header">
                <h6 class="mb-0">
                    <i class="fas fa-list me-2"></i>
                    Motifs les plus fréquents
                </h6>
            </div>
            <div class="card-body">
                <?php foreach ($motifs_frequents as $motif): ?>
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span><?php echo htmlspecialchars($motif['motif']); ?></span>
                        <span class="badge bg-secondary"><?php echo $motif['nombre']; ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Actions rapides -->
<?php if (checkPermission('students')): ?>
<div class="row mt-4">
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
                    <div class="col-md-3 mb-2">
                        <div class="d-grid">
                            <a href="add-absence.php" class="btn btn-outline-danger">
                                <i class="fas fa-user-times me-2"></i>
                                Signaler absence
                            </a>
                        </div>
                    </div>
                    <div class="col-md-3 mb-2">
                        <div class="d-grid">
                            <a href="add-delay.php" class="btn btn-outline-warning">
                                <i class="fas fa-clock me-2"></i>
                                Signaler retard
                            </a>
                        </div>
                    </div>
                    <div class="col-md-3 mb-2">
                        <div class="d-grid">
                            <a href="bulk-attendance.php" class="btn btn-outline-primary">
                                <i class="fas fa-list-check me-2"></i>
                                Appel de classe
                            </a>
                        </div>
                    </div>
                    <div class="col-md-3 mb-2">
                        <div class="d-grid">
                            <a href="reports/monthly.php" class="btn btn-outline-info">
                                <i class="fas fa-chart-bar me-2"></i>
                                Rapport mensuel
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<?php if (!empty($evolution_absences)): ?>
<script>
// Graphique d'évolution des absences
const evolutionCtx = document.getElementById('evolutionChart').getContext('2d');
const evolutionChart = new Chart(evolutionCtx, {
    type: 'line',
    data: {
        labels: [<?php echo implode(',', array_map(function($e) { return "'" . date('d/m', strtotime($e['date_abs'])) . "'"; }, $evolution_absences)); ?>],
        datasets: [{
            label: 'Absences',
            data: [<?php echo implode(',', array_column($evolution_absences, 'absences')); ?>],
            borderColor: '#dc3545',
            backgroundColor: 'rgba(220, 53, 69, 0.1)',
            tension: 0.4,
            fill: true
        }, {
            label: 'Retards',
            data: [<?php echo implode(',', array_column($evolution_absences, 'retards')); ?>],
            borderColor: '#ffc107',
            backgroundColor: 'rgba(255, 193, 7, 0.1)',
            tension: 0.4,
            fill: true
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: 'top'
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    stepSize: 1
                }
            }
        }
    }
});
</script>
<?php endif; ?>

<!-- Modal pour l'historique des absences -->
<div class="modal fade" id="historyModal" tabindex="-1" aria-labelledby="historyModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="historyModalLabel">
                    <i class="fas fa-history me-2"></i>
                    Historique de l'absence
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="historyContent">
                <div class="text-center">
                    <div class="spinner-border" role="status">
                        <span class="visually-hidden">Chargement...</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function justifyAbsence(absenceId) {
    if (confirm('Marquer cette absence comme justifiée ?')) {
        fetch('justify-absence.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({id: absenceId})
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Enregistrer l'action dans l'historique
                logUserAction('justify_absence', 'attendance', 'Absence justifiée', absenceId);
                location.reload();
            } else {
                alert('Erreur lors de la justification: ' + (data.message || 'Erreur inconnue'));
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Erreur lors de la justification');
        });
    }
}

function showAbsenceHistory(absenceId) {
    const modal = new bootstrap.Modal(document.getElementById('historyModal'));
    const content = document.getElementById('historyContent');

    // Afficher le spinner
    content.innerHTML = `
        <div class="text-center">
            <div class="spinner-border" role="status">
                <span class="visually-hidden">Chargement...</span>
            </div>
        </div>
    `;

    modal.show();

    // Charger l'historique
    fetch('get-absence-history.php?id=' + absenceId)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                displayHistory(data.history, data.absence_info);
            } else {
                content.innerHTML = `
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        Erreur lors du chargement de l'historique: ${data.message || 'Erreur inconnue'}
                    </div>
                `;
            }
        })
        .catch(error => {
            console.error('Error:', error);
            content.innerHTML = `
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    Erreur lors du chargement de l'historique
                </div>
            `;
        });
}

function displayHistory(history, absenceInfo) {
    const content = document.getElementById('historyContent');

    let html = `
        <div class="mb-4">
            <h6><i class="fas fa-info-circle me-2"></i>Informations de l'absence</h6>
            <div class="card bg-light">
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <strong>Élève:</strong> ${absenceInfo.eleve_nom}<br>
                            <strong>Date:</strong> ${absenceInfo.date_absence}<br>
                            <strong>Type:</strong> <span class="badge bg-${absenceInfo.type_absence === 'absence' ? 'danger' : 'warning'}">${absenceInfo.type_absence}</span>
                        </div>
                        <div class="col-md-6">
                            <strong>Motif:</strong> ${absenceInfo.motif || 'Non spécifié'}<br>
                            <strong>Justifiée:</strong> <span class="badge bg-${['absence_justifiee', 'retard_justifie'].includes(absenceInfo.type_absence) ? 'success' : 'secondary'}">${['absence_justifiee', 'retard_justifie'].includes(absenceInfo.type_absence) ? 'Oui' : 'Non'}</span><br>
                            <strong>Créée le:</strong> ${absenceInfo.created_at}
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <h6><i class="fas fa-history me-2"></i>Historique des modifications</h6>
    `;

    if (history.length > 0) {
        html += '<div class="timeline">';
        history.forEach((entry, index) => {
            const isLast = index === history.length - 1;
            html += `
                <div class="timeline-item ${isLast ? 'timeline-item-last' : ''}">
                    <div class="timeline-marker bg-${getActionColor(entry.action)}">
                        <i class="fas ${getActionIcon(entry.action)}"></i>
                    </div>
                    <div class="timeline-content">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <h6 class="mb-1">${getActionLabel(entry.action)}</h6>
                                <p class="mb-1">${entry.details || 'Aucun détail'}</p>
                                <small class="text-muted">
                                    <i class="fas fa-user me-1"></i>${entry.user_name}
                                    <i class="fas fa-clock ms-2 me-1"></i>${entry.created_at}
                                    ${entry.ip_address ? `<i class="fas fa-globe ms-2 me-1"></i>${entry.ip_address}` : ''}
                                </small>
                            </div>
                        </div>
                    </div>
                </div>
            `;
        });
        html += '</div>';
    } else {
        html += `
            <div class="alert alert-info">
                <i class="fas fa-info-circle me-2"></i>
                Aucun historique de modification disponible
            </div>
        `;
    }

    content.innerHTML = html;
}

function getActionColor(action) {
    const colors = {
        'create_absence': 'primary',
        'update_absence': 'warning',
        'justify_absence': 'success',
        'delete_absence': 'danger'
    };
    return colors[action] || 'secondary';
}

function getActionIcon(action) {
    const icons = {
        'create_absence': 'fa-plus',
        'update_absence': 'fa-edit',
        'justify_absence': 'fa-check',
        'delete_absence': 'fa-trash'
    };
    return icons[action] || 'fa-info';
}

function getActionLabel(action) {
    const labels = {
        'create_absence': 'Absence créée',
        'update_absence': 'Absence modifiée',
        'justify_absence': 'Absence justifiée',
        'delete_absence': 'Absence supprimée'
    };
    return labels[action] || 'Action inconnue';
}

function logUserAction(action, module, details, targetId) {
    fetch('../../includes/log-action.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            action: action,
            module: module,
            details: details,
            target_id: targetId
        })
    }).catch(error => {
        console.error('Erreur lors de l\'enregistrement de l\'action:', error);
    });
}
</script>

<style>
.timeline {
    position: relative;
    padding-left: 30px;
}

.timeline::before {
    content: '';
    position: absolute;
    left: 15px;
    top: 0;
    bottom: 0;
    width: 2px;
    background: #dee2e6;
}

.timeline-item {
    position: relative;
    margin-bottom: 20px;
}

.timeline-item-last {
    margin-bottom: 0;
}

.timeline-marker {
    position: absolute;
    left: -22px;
    top: 0;
    width: 30px;
    height: 30px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 12px;
}

.timeline-content {
    background: #f8f9fa;
    border: 1px solid #dee2e6;
    border-radius: 8px;
    padding: 15px;
    margin-left: 15px;
}
</style>

<?php include '../../../includes/footer.php'; ?>
