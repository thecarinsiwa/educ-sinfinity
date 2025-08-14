<?php
/**
 * Rapport mensuel des absences et retards
 * Application de gestion scolaire - République Démocratique du Congo
 */

require_once '../../../../config/config.php';
require_once '../../../../config/database.php';
require_once '../../../../includes/functions.php';

// Vérifier l'authentification et les permissions
requireLogin();
if (!checkPermission('students')) {
    redirectTo('../../../../login.php');
}

$page_title = "Rapport mensuel des absences";

// Récupérer l'année scolaire active
$current_year = $database->query("SELECT * FROM annees_scolaires WHERE status = 'active' LIMIT 1")->fetch();

// Paramètres de filtrage
$selected_month = $_GET['month'] ?? date('Y-m');
$selected_class = $_GET['class_id'] ?? '';
$report_type = $_GET['type'] ?? 'summary';

// Traitement de l'export
if (isset($_GET['export']) && $_GET['export'] === 'excel') {
    // Headers pour l'export Excel
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment; filename="rapport_mensuel_' . $selected_month . '.xls"');
    header('Cache-Control: max-age=0');
}

// Récupérer les classes
$classes = $database->query(
    "SELECT * FROM classes WHERE annee_scolaire_id = ? ORDER BY niveau, nom",
    [$current_year['id'] ?? 0]
)->fetchAll();

// Construire la requête de base
$where_conditions = ["YEAR(a.date_absence) = YEAR(?) AND MONTH(a.date_absence) = MONTH(?)"];
$params = [$selected_month . '-01', $selected_month . '-01'];

if ($selected_class) {
    $where_conditions[] = "c.id = ?";
    $params[] = $selected_class;
}

$where_clause = implode(' AND ', $where_conditions);

// Statistiques générales du mois
$stats_query = "
    SELECT 
        COUNT(*) as total_incidents,
        COUNT(CASE WHEN a.type_absence IN ('absence', 'absence_justifiee') THEN 1 END) as total_absences,
        COUNT(CASE WHEN a.type_absence IN ('retard', 'retard_justifie') THEN 1 END) as total_retards,
        COUNT(CASE WHEN a.type_absence IN ('absence_justifiee', 'retard_justifie') THEN 1 END) as total_justifies,
        COUNT(DISTINCT a.eleve_id) as eleves_concernes,
        COUNT(DISTINCT c.id) as classes_concernees
    FROM absences a
    JOIN eleves e ON a.eleve_id = e.id
    JOIN inscriptions i ON e.id = i.eleve_id
    JOIN classes c ON i.classe_id = c.id
    WHERE $where_clause AND i.annee_scolaire_id = ?
";
$params[] = $current_year['id'] ?? 0;

$monthly_stats = $database->query($stats_query, $params)->fetch();

// Statistiques par classe
$class_stats_query = "
    SELECT 
        c.id, c.nom as classe_nom, c.niveau,
        COUNT(*) as total_incidents,
        COUNT(CASE WHEN a.type_absence IN ('absence', 'absence_justifiee') THEN 1 END) as absences,
        COUNT(CASE WHEN a.type_absence IN ('retard', 'retard_justifie') THEN 1 END) as retards,
        COUNT(CASE WHEN a.type_absence IN ('absence_justifiee', 'retard_justifie') THEN 1 END) as justifies,
        COUNT(DISTINCT a.eleve_id) as eleves_concernes,
        COUNT(DISTINCT i.eleve_id) as total_eleves
    FROM classes c
    JOIN inscriptions i ON c.id = i.classe_id
    LEFT JOIN absences a ON i.eleve_id = a.eleve_id AND YEAR(a.date_absence) = YEAR(?) AND MONTH(a.date_absence) = MONTH(?)
    WHERE i.annee_scolaire_id = ? AND i.status = 'inscrit'
    " . ($selected_class ? "AND c.id = ?" : "") . "
    GROUP BY c.id, c.nom, c.niveau
    ORDER BY c.niveau, c.nom
";

$class_params = [$selected_month . '-01', $selected_month . '-01', $current_year['id'] ?? 0];
if ($selected_class) {
    $class_params[] = $selected_class;
}

$class_stats = $database->query($class_stats_query, $class_params)->fetchAll();

// Top élèves avec le plus d'incidents
$top_students_query = "
    SELECT 
        e.id, e.nom, e.prenom, e.numero_matricule,
        c.nom as classe_nom,
        COUNT(*) as total_incidents,
        COUNT(CASE WHEN a.type_absence IN ('absence', 'absence_justifiee') THEN 1 END) as absences,
        COUNT(CASE WHEN a.type_absence IN ('retard', 'retard_justifie') THEN 1 END) as retards,
        COUNT(CASE WHEN a.type_absence IN ('absence_justifiee', 'retard_justifie') THEN 1 END) as justifies
    FROM absences a
    JOIN eleves e ON a.eleve_id = e.id
    JOIN inscriptions i ON e.id = i.eleve_id
    JOIN classes c ON i.classe_id = c.id
    WHERE $where_clause AND i.annee_scolaire_id = ?
    GROUP BY e.id, e.nom, e.prenom, e.numero_matricule, c.nom
    ORDER BY total_incidents DESC
    LIMIT 20
";

$top_students = $database->query($top_students_query, $params)->fetchAll();

// Évolution quotidienne du mois
$daily_evolution_query = "
    SELECT 
        DATE(a.date_absence) as date_incident,
        COUNT(*) as total_incidents,
        COUNT(CASE WHEN a.type_absence IN ('absence', 'absence_justifiee') THEN 1 END) as absences,
        COUNT(CASE WHEN a.type_absence IN ('retard', 'retard_justifie') THEN 1 END) as retards
    FROM absences a
    JOIN eleves e ON a.eleve_id = e.id
    JOIN inscriptions i ON e.id = i.eleve_id
    JOIN classes c ON i.classe_id = c.id
    WHERE $where_clause AND i.annee_scolaire_id = ?
    GROUP BY DATE(a.date_absence)
    ORDER BY date_incident
";

$daily_evolution = $database->query($daily_evolution_query, $params)->fetchAll();

// Enregistrer l'action
logUserAction(
    'view_monthly_report',
    'attendance',
    'Consultation du rapport mensuel - Mois: ' . $selected_month . 
    ($selected_class ? ', Classe: ' . $selected_class : ''),
    null
);

// Si c'est un export, afficher seulement les données
if (isset($_GET['export']) && $_GET['export'] === 'excel') {
    include 'monthly_export.php';
    exit;
}

include '../../../../includes/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">
        <i class="fas fa-chart-line me-2"></i>
        Rapport mensuel des absences
    </h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <div class="btn-group me-2">
            <a href="../index.php" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-1"></i>
                Retour
            </a>
        </div>
        <div class="btn-group">
            <button type="button" class="btn btn-success" onclick="exportToExcel()">
                <i class="fas fa-file-excel me-1"></i>
                Exporter Excel
            </button>
            <button type="button" class="btn btn-info" onclick="printReport()">
                <i class="fas fa-print me-1"></i>
                Imprimer
            </button>
        </div>
    </div>
</div>

<!-- Filtres -->
<div class="card mb-4">
    <div class="card-header">
        <h5 class="mb-0">
            <i class="fas fa-filter me-2"></i>
            Filtres du rapport
        </h5>
    </div>
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-md-4">
                <label for="month" class="form-label">Mois</label>
                <input type="month" class="form-control" id="month" name="month" 
                       value="<?php echo htmlspecialchars($selected_month); ?>" required>
            </div>
            
            <div class="col-md-4">
                <label for="class_id" class="form-label">Classe (optionnel)</label>
                <select class="form-select" id="class_id" name="class_id">
                    <option value="">Toutes les classes</option>
                    <?php foreach ($classes as $class): ?>
                        <option value="<?php echo $class['id']; ?>" 
                                <?php echo $selected_class == $class['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($class['niveau'] . ' - ' . $class['nom']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="col-md-4">
                <label for="type" class="form-label">Type de rapport</label>
                <select class="form-select" id="type" name="type">
                    <option value="summary" <?php echo $report_type === 'summary' ? 'selected' : ''; ?>>Résumé</option>
                    <option value="detailed" <?php echo $report_type === 'detailed' ? 'selected' : ''; ?>>Détaillé</option>
                </select>
            </div>
            
            <div class="col-12">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-search me-1"></i>
                    Générer le rapport
                </button>
                <button type="button" class="btn btn-outline-secondary" onclick="resetFilters()">
                    <i class="fas fa-undo me-1"></i>
                    Réinitialiser
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Statistiques générales améliorées -->
<div class="row mb-5">
    <div class="col-lg-3 col-md-6 mb-4">
        <div class="card border-0 shadow-sm animate-slide-up"
             style="background: linear-gradient(135deg, #007bff 0%, #0056b3 100%);
                    border-radius: 20px;
                    color: white;
                    transition: all 0.3s ease;">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h2 class="mb-2 fw-bold" style="font-size: 2.5rem;">
                            <?php echo number_format($monthly_stats['total_incidents'] ?? 0); ?>
                        </h2>
                        <p class="mb-0 opacity-90" style="font-size: 1rem; font-weight: 500;">
                            Total incidents
                        </p>
                    </div>
                    <div class="text-end">
                        <i class="fas fa-exclamation-triangle" style="font-size: 3rem; opacity: 0.3;"></i>
                    </div>
                </div>
                <div class="mt-3">
                    <div class="progress" style="height: 4px; background: rgba(255,255,255,0.3);">
                        <div class="progress-bar bg-light" style="width: 100%; border-radius: 2px;"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-lg-3 col-md-6 mb-4">
        <div class="card border-0 shadow-sm animate-slide-up"
             style="background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
                    border-radius: 20px;
                    color: white;
                    transition: all 0.3s ease;
                    animation-delay: 0.1s;">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h2 class="mb-2 fw-bold" style="font-size: 2.5rem;">
                            <?php echo number_format($monthly_stats['total_absences'] ?? 0); ?>
                        </h2>
                        <p class="mb-0 opacity-90" style="font-size: 1rem; font-weight: 500;">
                            Absences
                        </p>
                    </div>
                    <div class="text-end">
                        <i class="fas fa-user-times" style="font-size: 3rem; opacity: 0.3;"></i>
                    </div>
                </div>
                <div class="mt-3">
                    <div class="progress" style="height: 4px; background: rgba(255,255,255,0.3);">
                        <div class="progress-bar bg-light"
                             style="width: <?php echo $monthly_stats['total_incidents'] > 0 ? round(($monthly_stats['total_absences'] / $monthly_stats['total_incidents']) * 100) : 0; ?>%; border-radius: 2px;"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-lg-3 col-md-6 mb-4">
        <div class="card border-0 shadow-sm animate-slide-up"
             style="background: linear-gradient(135deg, #ffc107 0%, #e0a800 100%);
                    border-radius: 20px;
                    color: white;
                    transition: all 0.3s ease;
                    animation-delay: 0.2s;">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h2 class="mb-2 fw-bold" style="font-size: 2.5rem;">
                            <?php echo number_format($monthly_stats['total_retards'] ?? 0); ?>
                        </h2>
                        <p class="mb-0 opacity-90" style="font-size: 1rem; font-weight: 500;">
                            Retards
                        </p>
                    </div>
                    <div class="text-end">
                        <i class="fas fa-clock" style="font-size: 3rem; opacity: 0.3;"></i>
                    </div>
                </div>
                <div class="mt-3">
                    <div class="progress" style="height: 4px; background: rgba(255,255,255,0.3);">
                        <div class="progress-bar bg-light"
                             style="width: <?php echo $monthly_stats['total_incidents'] > 0 ? round(($monthly_stats['total_retards'] / $monthly_stats['total_incidents']) * 100) : 0; ?>%; border-radius: 2px;"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-lg-3 col-md-6 mb-4">
        <div class="card border-0 shadow-sm animate-slide-up"
             style="background: linear-gradient(135deg, #28a745 0%, #1e7e34 100%);
                    border-radius: 20px;
                    color: white;
                    transition: all 0.3s ease;
                    animation-delay: 0.3s;">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h2 class="mb-2 fw-bold" style="font-size: 2.5rem;">
                            <?php echo number_format($monthly_stats['total_justifies'] ?? 0); ?>
                        </h2>
                        <p class="mb-0 opacity-90" style="font-size: 1rem; font-weight: 500;">
                            Justifiés
                        </p>
                    </div>
                    <div class="text-end">
                        <i class="fas fa-check-circle" style="font-size: 3rem; opacity: 0.3;"></i>
                    </div>
                </div>
                <div class="mt-3">
                    <div class="progress" style="height: 4px; background: rgba(255,255,255,0.3);">
                        <div class="progress-bar bg-light"
                             style="width: <?php echo $monthly_stats['total_incidents'] > 0 ? round(($monthly_stats['total_justifies'] / $monthly_stats['total_incidents']) * 100) : 0; ?>%; border-radius: 2px;"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Top élèves avec incidents -->
<?php if (!empty($top_students) && $report_type === 'detailed'): ?>
<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-users me-2"></i>
                    Top élèves avec le plus d'incidents
                </h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead class="table-dark">
                            <tr>
                                <th>#</th>
                                <th>Élève</th>
                                <th>Matricule</th>
                                <th>Classe</th>
                                <th>Total</th>
                                <th>Absences</th>
                                <th>Retards</th>
                                <th>Justifiés</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($top_students as $index => $student): ?>
                                <tr>
                                    <td><strong><?php echo $index + 1; ?></strong></td>
                                    <td><?php echo htmlspecialchars($student['nom'] . ' ' . $student['prenom']); ?></td>
                                    <td><?php echo htmlspecialchars($student['numero_matricule']); ?></td>
                                    <td><?php echo htmlspecialchars($student['classe_nom']); ?></td>
                                    <td>
                                        <span class="badge bg-primary fs-6"><?php echo $student['total_incidents']; ?></span>
                                    </td>
                                    <td>
                                        <span class="badge bg-danger"><?php echo $student['absences']; ?></span>
                                    </td>
                                    <td>
                                        <span class="badge bg-warning"><?php echo $student['retards']; ?></span>
                                    </td>
                                    <td>
                                        <span class="badge bg-success"><?php echo $student['justifies']; ?></span>
                                    </td>
                                    <td>
                                        <a href="../index.php?student_id=<?php echo $student['id']; ?>"
                                           class="btn btn-sm btn-outline-primary">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Évolution quotidienne -->
<?php if (!empty($daily_evolution) && $report_type === 'detailed'): ?>
<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-chart-line me-2"></i>
                    Évolution quotidienne du mois
                </h5>
            </div>
            <div class="card-body">
                <canvas id="dailyChart" height="100"></canvas>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<style>
@media print {
    .btn-toolbar, .card-header .btn, .no-print {
        display: none !important;
    }

    .card {
        border: 1px solid #000 !important;
        box-shadow: none !important;
    }

    .table {
        font-size: 12px;
    }

    .badge {
        border: 1px solid #000;
        color: #000 !important;
        background-color: transparent !important;
    }
}

/* Styles supplémentaires pour améliorer l'apparence */
.table tbody tr:hover {
    background-color: rgba(0,123,255,0.05) !important;
    transform: translateX(5px);
    transition: all 0.3s ease;
}

.card {
    transition: all 0.3s ease;
}

.card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(0,0,0,0.15) !important;
}

.badge {
    transition: all 0.3s ease;
}

.badge:hover {
    transform: scale(1.05);
}

.progress-bar {
    transition: width 1s ease-in-out;
}

/* Animation pour les cartes */
@keyframes slideInUp {
    from {
        opacity: 0;
        transform: translateY(30px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.animate-slide-up {
    animation: slideInUp 0.6s ease-out;
}

/* Amélioration des couleurs des badges */
.badge.bg-primary { background: linear-gradient(135deg, #007bff, #0056b3) !important; }
.badge.bg-danger { background: linear-gradient(135deg, #dc3545, #c82333) !important; }
.badge.bg-warning { background: linear-gradient(135deg, #ffc107, #e0a800) !important; }
.badge.bg-success { background: linear-gradient(135deg, #28a745, #1e7e34) !important; }
.badge.bg-info { background: linear-gradient(135deg, #17a2b8, #138496) !important; }

/* Responsive amélioré */
@media (max-width: 768px) {
    .table-responsive {
        border-radius: 10px;
    }

    .card-body {
        padding: 1rem !important;
    }

    .badge {
        font-size: 0.7rem !important;
        padding: 0.3rem 0.6rem !important;
    }
}
</style>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Données pour les graphiques
const monthlyData = {
    absences: <?php echo $monthly_stats['total_absences'] ?? 0; ?>,
    retards: <?php echo $monthly_stats['total_retards'] ?? 0; ?>,
    justifies: <?php echo $monthly_stats['total_justifies'] ?? 0; ?>
};

const dailyData = <?php echo json_encode($daily_evolution); ?>;

// Graphique circulaire des incidents
const ctx = document.getElementById('incidentChart').getContext('2d');
const incidentChart = new Chart(ctx, {
    type: 'doughnut',
    data: {
        labels: ['Absences', 'Retards', 'Justifiés'],
        datasets: [{
            data: [
                monthlyData.absences - (monthlyData.justifies * 0.6), // Approximation absences non justifiées
                monthlyData.retards - (monthlyData.justifies * 0.4), // Approximation retards non justifiés
                monthlyData.justifies
            ],
            backgroundColor: [
                '#dc3545',
                '#ffc107',
                '#28a745'
            ],
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

// Graphique d'évolution quotidienne
<?php if (!empty($daily_evolution) && $report_type === 'detailed'): ?>
const dailyCtx = document.getElementById('dailyChart').getContext('2d');
const dailyChart = new Chart(dailyCtx, {
    type: 'line',
    data: {
        labels: dailyData.map(item => {
            const date = new Date(item.date_incident);
            return date.getDate() + '/' + (date.getMonth() + 1);
        }),
        datasets: [{
            label: 'Absences',
            data: dailyData.map(item => item.absences),
            borderColor: '#dc3545',
            backgroundColor: 'rgba(220, 53, 69, 0.1)',
            tension: 0.4
        }, {
            label: 'Retards',
            data: dailyData.map(item => item.retards),
            borderColor: '#ffc107',
            backgroundColor: 'rgba(255, 193, 7, 0.1)',
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
                position: 'top'
            }
        }
    }
});
<?php endif; ?>

// Fonctions utilitaires
function exportToExcel() {
    const currentUrl = new URL(window.location);
    currentUrl.searchParams.set('export', 'excel');
    window.location.href = currentUrl.toString();
}

function printReport() {
    window.print();
}

function resetFilters() {
    window.location.href = 'monthly.php';
}

// Enregistrer l'action de consultation
document.addEventListener('DOMContentLoaded', function() {
    fetch('../log-action.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            action: 'view_monthly_report',
            module: 'attendance',
            details: 'Consultation du rapport mensuel - Mois: <?php echo $selected_month; ?>'
        })
    }).catch(error => console.log('Log error:', error));
});
</script>

<!-- Section principale des statistiques -->
<div class="row g-4 mb-5">
    <!-- Statistiques par classe -->
    <div class="col-lg-8">
        <div class="card shadow-sm border-0 h-100" style="border-radius: 15px;">
            <div class="card-header bg-gradient text-white" style="background: linear-gradient(135deg, #495057 0%, #6c757d 100%); border-radius: 15px 15px 0 0;">
                <div class="d-flex align-items-center justify-content-between">
                    <h5 class="mb-0 fw-bold">
                        <i class="fas fa-school me-2"></i>
                        Statistiques par classe
                    </h5>
                    <span class="badge bg-light text-dark">
                        <?php echo count($class_stats); ?> classe(s)
                    </span>
                </div>
            </div>
            <div class="card-body p-0">
                <?php if (!empty($class_stats)): ?>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead style="background: linear-gradient(135deg, #343a40 0%, #495057 100%); color: white;">
                                <tr>
                                    <th class="border-0 py-3 px-4" style="font-weight: 600; font-size: 0.85rem; text-transform: uppercase; letter-spacing: 0.5px;">
                                        <i class="fas fa-graduation-cap me-2"></i>Classe
                                    </th>
                                    <th class="border-0 py-3 px-4" style="font-weight: 600; font-size: 0.85rem; text-transform: uppercase; letter-spacing: 0.5px;">
                                        <i class="fas fa-users me-2"></i>Élèves
                                    </th>
                                    <th class="border-0 py-3 px-4" style="font-weight: 600; font-size: 0.85rem; text-transform: uppercase; letter-spacing: 0.5px;">
                                        <i class="fas fa-exclamation-triangle me-2"></i>Incidents
                                    </th>
                                    <th class="border-0 py-3 px-4" style="font-weight: 600; font-size: 0.85rem; text-transform: uppercase; letter-spacing: 0.5px;">
                                        <i class="fas fa-user-times me-2"></i>Absences
                                    </th>
                                    <th class="border-0 py-3 px-4" style="font-weight: 600; font-size: 0.85rem; text-transform: uppercase; letter-spacing: 0.5px;">
                                        <i class="fas fa-clock me-2"></i>Retards
                                    </th>
                                    <th class="border-0 py-3 px-4" style="font-weight: 600; font-size: 0.85rem; text-transform: uppercase; letter-spacing: 0.5px;">
                                        <i class="fas fa-check-circle me-2"></i>Justifiés
                                    </th>
                                    <th class="border-0 py-3 px-4" style="font-weight: 600; font-size: 0.85rem; text-transform: uppercase; letter-spacing: 0.5px;">
                                        <i class="fas fa-chart-bar me-2"></i>Taux
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($class_stats as $stat): ?>
                                    <?php 
                                    $incident_rate = $stat['total_eleves'] > 0 ? 
                                        round(($stat['eleves_concernes'] / $stat['total_eleves']) * 100, 1) : 0;
                                    ?>
                                    <tr class="border-0" style="transition: all 0.3s ease;">
                                        <td class="py-3 px-4 border-0">
                                            <div class="d-flex align-items-center">
                                                <div class="bg-primary rounded-circle d-flex align-items-center justify-content-center me-3"
                                                     style="width: 40px; height: 40px; font-size: 0.8rem; font-weight: bold;">
                                                    <?php echo substr($stat['niveau'], 0, 2); ?>
                                                </div>
                                                <div>
                                                    <div class="fw-bold text-dark"><?php echo htmlspecialchars($stat['classe_nom']); ?></div>
                                                    <small class="text-muted"><?php echo htmlspecialchars($stat['niveau']); ?></small>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="py-3 px-4 border-0">
                                            <div class="d-flex align-items-center">
                                                <span class="badge bg-info rounded-pill me-2" style="font-size: 0.9rem;">
                                                    <?php echo $stat['eleves_concernes']; ?>
                                                </span>
                                                <span class="text-muted">/ <?php echo $stat['total_eleves']; ?></span>
                                            </div>
                                        </td>
                                        <td class="py-3 px-4 border-0">
                                            <span class="badge bg-primary rounded-pill" style="font-size: 0.9rem; padding: 0.5rem 1rem;">
                                                <?php echo $stat['total_incidents'] ?? 0; ?>
                                            </span>
                                        </td>
                                        <td class="py-3 px-4 border-0">
                                            <span class="badge bg-danger rounded-pill" style="font-size: 0.9rem; padding: 0.5rem 1rem;">
                                                <?php echo $stat['absences'] ?? 0; ?>
                                            </span>
                                        </td>
                                        <td class="py-3 px-4 border-0">
                                            <span class="badge bg-warning rounded-pill" style="font-size: 0.9rem; padding: 0.5rem 1rem;">
                                                <?php echo $stat['retards'] ?? 0; ?>
                                            </span>
                                        </td>
                                        <td class="py-3 px-4 border-0">
                                            <span class="badge bg-success rounded-pill" style="font-size: 0.9rem; padding: 0.5rem 1rem;">
                                                <?php echo $stat['justifies'] ?? 0; ?>
                                            </span>
                                        </td>
                                        <td class="py-3 px-4 border-0">
                                            <div class="d-flex align-items-center">
                                                <div class="progress me-3" style="height: 8px; width: 100px; border-radius: 10px;">
                                                    <div class="progress-bar bg-gradient"
                                                         style="width: <?php echo $incident_rate; ?>%;
                                                                background: linear-gradient(90deg,
                                                                <?php echo $incident_rate > 50 ? '#dc3545' : ($incident_rate > 25 ? '#ffc107' : '#28a745'); ?>,
                                                                <?php echo $incident_rate > 50 ? '#ff6b7a' : ($incident_rate > 25 ? '#ffd54f' : '#66bb6a'); ?>);
                                                                border-radius: 10px;"
                                                         role="progressbar"
                                                         aria-valuenow="<?php echo $incident_rate; ?>"
                                                         aria-valuemin="0" aria-valuemax="100">
                                                    </div>
                                                </div>
                                                <span class="fw-bold" style="color: <?php echo $incident_rate > 50 ? '#dc3545' : ($incident_rate > 25 ? '#ffc107' : '#28a745'); ?>;">
                                                    <?php echo $incident_rate; ?>%
                                                </span>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="text-center py-4">
                        <i class="fas fa-chart-bar fa-3x text-muted mb-3"></i>
                        <p class="text-muted">Aucune donnée disponible pour la période sélectionnée.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Informations complémentaires -->
    <div class="col-lg-4">
        <div class="card shadow-sm border-0 mb-4" style="border-radius: 15px;">
            <div class="card-header text-white" style="background: linear-gradient(135deg, #17a2b8 0%, #20c997 100%); border-radius: 15px 15px 0 0;">
                <h5 class="mb-0 fw-bold">
                    <i class="fas fa-chart-pie me-2"></i>
                    Résumé du mois
                </h5>
            </div>
            <div class="card-body p-4">
                <!-- Période -->
                <div class="d-flex align-items-center p-3 mb-3 rounded-3" style="background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);">
                    <div class="bg-primary rounded-circle d-flex align-items-center justify-content-center me-3"
                         style="width: 50px; height: 50px;">
                        <i class="fas fa-calendar-alt text-white"></i>
                    </div>
                    <div>
                        <h6 class="mb-1 text-muted fw-bold" style="font-size: 0.8rem; text-transform: uppercase;">Période</h6>
                        <h5 class="mb-0 fw-bold text-dark">
                            <?php echo date('F Y', strtotime($selected_month . '-01')); ?>
                        </h5>
                    </div>
                </div>

                <!-- Élèves concernés -->
                <div class="d-flex align-items-center p-3 mb-3 rounded-3" style="background: linear-gradient(135deg, #fff3cd 0%, #ffeaa7 100%);">
                    <div class="bg-warning rounded-circle d-flex align-items-center justify-content-center me-3"
                         style="width: 50px; height: 50px;">
                        <i class="fas fa-users text-white"></i>
                    </div>
                    <div>
                        <h6 class="mb-1 text-muted fw-bold" style="font-size: 0.8rem; text-transform: uppercase;">Élèves concernés</h6>
                        <h4 class="mb-0 fw-bold text-warning">
                            <?php echo number_format($monthly_stats['eleves_concernes'] ?? 0); ?>
                        </h4>
                    </div>
                </div>

                <!-- Classes concernées -->
                <div class="d-flex align-items-center p-3 mb-3 rounded-3" style="background: linear-gradient(135deg, #d1ecf1 0%, #bee5eb 100%);">
                    <div class="bg-info rounded-circle d-flex align-items-center justify-content-center me-3"
                         style="width: 50px; height: 50px;">
                        <i class="fas fa-school text-white"></i>
                    </div>
                    <div>
                        <h6 class="mb-1 text-muted fw-bold" style="font-size: 0.8rem; text-transform: uppercase;">Classes concernées</h6>
                        <h4 class="mb-0 fw-bold text-info">
                            <?php echo number_format($monthly_stats['classes_concernees'] ?? 0); ?>
                        </h4>
                    </div>
                </div>
                
                <?php if ($monthly_stats['total_incidents'] > 0): ?>
                <!-- Taux de justification -->
                <div class="p-3 rounded-3" style="background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%);">
                    <div class="d-flex align-items-center mb-3">
                        <div class="bg-success rounded-circle d-flex align-items-center justify-content-center me-3"
                             style="width: 50px; height: 50px;">
                            <i class="fas fa-check-circle text-white"></i>
                        </div>
                        <div>
                            <h6 class="mb-1 text-muted fw-bold" style="font-size: 0.8rem; text-transform: uppercase;">Taux de justification</h6>
                            <?php
                            $justification_rate = round(($monthly_stats['total_justifies'] / $monthly_stats['total_incidents']) * 100, 1);
                            ?>
                            <h4 class="mb-0 fw-bold text-success">
                                <?php echo $justification_rate; ?>%
                            </h4>
                        </div>
                    </div>
                    <div class="progress" style="height: 10px; border-radius: 10px;">
                        <div class="progress-bar bg-gradient"
                             style="width: <?php echo $justification_rate; ?>%;
                                    background: linear-gradient(90deg, #28a745, #66bb6a);
                                    border-radius: 10px;"
                             role="progressbar"
                             aria-valuenow="<?php echo $justification_rate; ?>"
                             aria-valuemin="0" aria-valuemax="100">
                        </div>
                    </div>
                    <div class="d-flex justify-content-between mt-2">
                        <small class="text-muted">0%</small>
                        <small class="text-muted">100%</small>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Graphique circulaire -->
        <div class="card shadow-sm border-0" style="border-radius: 15px;">
            <div class="card-header text-white" style="background: linear-gradient(135deg, #6f42c1 0%, #e83e8c 100%); border-radius: 15px 15px 0 0;">
                <h5 class="mb-0 fw-bold">
                    <i class="fas fa-chart-pie me-2"></i>
                    Répartition des incidents
                </h5>
            </div>
            <div class="card-body p-4 text-center">
                <div style="position: relative; height: 300px; width: 300px; margin: 0 auto;">
                    <canvas id="incidentChart"></canvas>
                </div>
                <div class="mt-3">
                    <small class="text-muted">
                        <i class="fas fa-info-circle me-1"></i>
                        Cliquez sur les segments pour plus de détails
                    </small>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../../../../includes/footer.php'; ?>
