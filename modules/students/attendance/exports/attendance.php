<?php
/**
 * Export des données d'attendance
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

$page_title = "Export des données d'attendance";

// Récupérer l'année scolaire active
$current_year = $database->query("SELECT * FROM annees_scolaires WHERE status = 'active' LIMIT 1")->fetch();

// Traitement de l'export
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['export_action'])) {
    try {
        $export_format = $_POST['export_format'] ?? 'excel';
        $date_start = $_POST['date_start'] ?? '';
        $date_end = $_POST['date_end'] ?? '';
        $selected_classes = $_POST['selected_classes'] ?? [];
        $export_type = $_POST['export_type'] ?? 'summary';
        $include_justified = isset($_POST['include_justified']);
        
        // Validation
        if (!$date_start || !$date_end) {
            throw new Exception('Période de dates requise');
        }
        
        if (strtotime($date_start) > strtotime($date_end)) {
            throw new Exception('La date de début doit être antérieure à la date de fin');
        }
        
        // Construire la requête
        $where_conditions = [
            "a.date_absence >= ?",
            "a.date_absence <= ?",
            "i.annee_scolaire_id = ?"
        ];
        $params = [$date_start, $date_end . ' 23:59:59', $current_year['id'] ?? 0];
        
        if (!empty($selected_classes)) {
            $placeholders = str_repeat('?,', count($selected_classes) - 1) . '?';
            $where_conditions[] = "c.id IN ($placeholders)";
            $params = array_merge($params, $selected_classes);
        }
        
        if (!$include_justified) {
            $where_conditions[] = "a.type_absence NOT IN ('absence_justifiee', 'retard_justifie')";
        }
        
        $where_clause = implode(' AND ', $where_conditions);
        
        // Requête selon le type d'export
        if ($export_type === 'detailed') {
            $query = "
                SELECT
                    a.id,
                    e.numero_matricule,
                    e.nom as eleve_nom,
                    e.prenom as eleve_prenom,
                    e.date_naissance,
                    c.nom as classe_nom,
                    c.niveau,
                    a.type_absence,
                    a.date_absence,
                    a.motif,
                    a.duree_retard,
                    a.justification,
                    a.created_at,
                    u.nom as valide_par_nom,
                    u.prenom as valide_par_prenom
                FROM absences a
                JOIN eleves e ON a.eleve_id = e.id
                JOIN inscriptions i ON e.id = i.eleve_id
                JOIN classes c ON i.classe_id = c.id
                LEFT JOIN users u ON a.valide_par = u.id
                WHERE $where_clause
                ORDER BY a.date_absence DESC, c.niveau, c.nom, e.nom, e.prenom
            ";
        } else {
            $query = "
                SELECT 
                    c.niveau,
                    c.nom as classe_nom,
                    COUNT(*) as total_incidents,
                    COUNT(CASE WHEN a.type_absence IN ('absence', 'absence_justifiee') THEN 1 END) as total_absences,
                    COUNT(CASE WHEN a.type_absence IN ('retard', 'retard_justifie') THEN 1 END) as total_retards,
                    COUNT(CASE WHEN a.type_absence IN ('absence_justifiee', 'retard_justifie') THEN 1 END) as total_justifies,
                    COUNT(DISTINCT a.eleve_id) as eleves_concernes,
                    AVG(CASE WHEN a.duree_retard > 0 THEN a.duree_retard END) as duree_moyenne_retard
                FROM absences a
                JOIN eleves e ON a.eleve_id = e.id
                JOIN inscriptions i ON e.id = i.eleve_id
                JOIN classes c ON i.classe_id = c.id
                WHERE $where_clause
                GROUP BY c.id, c.niveau, c.nom
                ORDER BY c.niveau, c.nom
            ";
        }
        
        $data = $database->query($query, $params)->fetchAll();
        
        if (empty($data)) {
            throw new Exception('Aucune donnée trouvée pour les critères sélectionnés');
        }
        
        // Générer l'export selon le format
        switch ($export_format) {
            case 'excel':
                generateExcelExport($data, $export_type, $date_start, $date_end);
                break;
            case 'csv':
                generateCSVExport($data, $export_type, $date_start, $date_end);
                break;
            case 'pdf':
                generatePDFExport($data, $export_type, $date_start, $date_end);
                break;
            default:
                throw new Exception('Format d\'export non supporté');
        }
        
        // Enregistrer l'action
        logUserAction(
            'export_attendance',
            'attendance',
            "Export $export_format - Type: $export_type, Période: $date_start à $date_end, Enregistrements: " . count($data),
            null
        );
        
        exit; // L'export a été généré, arrêter l'exécution
        
    } catch (Exception $e) {
        showMessage('error', $e->getMessage());
    }
}

// Récupérer les classes pour les filtres
$classes = $database->query(
    "SELECT * FROM classes WHERE annee_scolaire_id = ? ORDER BY niveau, nom",
    [$current_year['id'] ?? 0]
)->fetchAll();

// Statistiques rapides pour l'aperçu
$quick_stats = $database->query(
    "SELECT
        COUNT(*) as total_records,
        COUNT(CASE WHEN DATE(a.date_absence) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) THEN 1 END) as last_30_days,
        COUNT(CASE WHEN DATE(a.date_absence) >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) THEN 1 END) as last_7_days,
        COUNT(DISTINCT a.eleve_id) as unique_students
     FROM absences a
     JOIN eleves e ON a.eleve_id = e.id
     JOIN inscriptions i ON e.id = i.eleve_id
     WHERE i.annee_scolaire_id = ?",
    [$current_year['id'] ?? 0]
)->fetch();

// Fonctions d'export
function generateExcelExport($data, $type, $date_start, $date_end) {
    $filename = "attendance_export_" . date('Y-m-d_H-i-s') . ".xls";
    
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: max-age=0');
    
    echo "<html><head><meta charset='UTF-8'></head><body>";
    echo "<h2>Export des données d'attendance</h2>";
    echo "<p>Période: " . formatDate($date_start) . " au " . formatDate($date_end) . "</p>";
    echo "<p>Type: " . ($type === 'detailed' ? 'Détaillé' : 'Résumé') . "</p>";
    echo "<p>Généré le: " . date('d/m/Y à H:i:s') . "</p>";
    echo "<br>";
    
    echo "<table border='1' cellpadding='5' cellspacing='0'>";
    
    if ($type === 'detailed') {
        echo "<tr style='background-color: #f0f0f0; font-weight: bold;'>";
        echo "<th>Matricule</th>";
        echo "<th>Nom</th>";
        echo "<th>Prénom</th>";
        echo "<th>Classe</th>";
        echo "<th>Type</th>";
        echo "<th>Date</th>";
        echo "<th>Heure</th>";
        echo "<th>Motif</th>";
        echo "<th>Durée retard (min)</th>";
        echo "<th>Justification</th>";
        echo "<th>Validé par</th>";
        echo "</tr>";

        foreach ($data as $row) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($row['numero_matricule']) . "</td>";
            echo "<td>" . htmlspecialchars($row['eleve_nom']) . "</td>";
            echo "<td>" . htmlspecialchars($row['eleve_prenom']) . "</td>";
            echo "<td>" . htmlspecialchars($row['niveau'] . ' - ' . $row['classe_nom']) . "</td>";
            echo "<td>" . htmlspecialchars(ucfirst(str_replace('_', ' ', $row['type_absence']))) . "</td>";
            echo "<td>" . date('d/m/Y', strtotime($row['date_absence'])) . "</td>";
            echo "<td>" . date('H:i', strtotime($row['date_absence'])) . "</td>";
            echo "<td>" . htmlspecialchars($row['motif'] ?: '-') . "</td>";
            echo "<td>" . ($row['duree_retard'] ?: '-') . "</td>";
            echo "<td>" . htmlspecialchars($row['justification'] ?: '-') . "</td>";
            echo "<td>" . htmlspecialchars(($row['valide_par_nom'] ?? '') . ' ' . ($row['valide_par_prenom'] ?? '')) . "</td>";
            echo "</tr>";
        }
    } else {
        echo "<tr style='background-color: #f0f0f0; font-weight: bold;'>";
        echo "<th>Classe</th>";
        echo "<th>Total incidents</th>";
        echo "<th>Absences</th>";
        echo "<th>Retards</th>";
        echo "<th>Justifiés</th>";
        echo "<th>Élèves concernés</th>";
        echo "<th>Durée moyenne retard (min)</th>";
        echo "</tr>";
        
        foreach ($data as $row) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($row['niveau'] . ' - ' . $row['classe_nom']) . "</td>";
            echo "<td>" . $row['total_incidents'] . "</td>";
            echo "<td>" . $row['total_absences'] . "</td>";
            echo "<td>" . $row['total_retards'] . "</td>";
            echo "<td>" . $row['total_justifies'] . "</td>";
            echo "<td>" . $row['eleves_concernes'] . "</td>";
            echo "<td>" . round($row['duree_moyenne_retard'] ?? 0, 1) . "</td>";
            echo "</tr>";
        }
    }
    
    echo "</table>";
    echo "</body></html>";
}

function generateCSVExport($data, $type, $date_start, $date_end) {
    $filename = "attendance_export_" . date('Y-m-d_H-i-s') . ".csv";
    
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: max-age=0');
    
    $output = fopen('php://output', 'w');
    
    // BOM pour UTF-8
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    if ($type === 'detailed') {
        fputcsv($output, [
            'Matricule', 'Nom', 'Prénom', 'Classe', 'Type', 'Date', 'Heure',
            'Motif', 'Durée retard (min)', 'Justification', 'Validé par'
        ], ';');

        foreach ($data as $row) {
            fputcsv($output, [
                $row['numero_matricule'],
                $row['eleve_nom'],
                $row['eleve_prenom'],
                $row['niveau'] . ' - ' . $row['classe_nom'],
                ucfirst(str_replace('_', ' ', $row['type_absence'])),
                date('d/m/Y', strtotime($row['date_absence'])),
                date('H:i', strtotime($row['date_absence'])),
                $row['motif'] ?: '-',
                $row['duree_retard'] ?: '-',
                $row['justification'] ?: '-',
                ($row['valide_par_nom'] ?? '') . ' ' . ($row['valide_par_prenom'] ?? '')
            ], ';');
        }
    } else {
        fputcsv($output, [
            'Classe', 'Total incidents', 'Absences', 'Retards', 'Justifiés', 
            'Élèves concernés', 'Durée moyenne retard (min)'
        ], ';');
        
        foreach ($data as $row) {
            fputcsv($output, [
                $row['niveau'] . ' - ' . $row['classe_nom'],
                $row['total_incidents'],
                $row['total_absences'],
                $row['total_retards'],
                $row['total_justifies'],
                $row['eleves_concernes'],
                round($row['duree_moyenne_retard'] ?? 0, 1)
            ], ';');
        }
    }
    
    fclose($output);
}

function generatePDFExport($data, $type, $date_start, $date_end) {
    // Simulation d'export PDF (nécessiterait une librairie comme TCPDF ou FPDF)
    $filename = "attendance_export_" . date('Y-m-d_H-i-s') . ".html";
    
    header('Content-Type: text/html; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    
    echo "<!DOCTYPE html><html><head><meta charset='UTF-8'>";
    echo "<title>Export Attendance PDF</title>";
    echo "<style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; font-weight: bold; }
        .header { text-align: center; margin-bottom: 30px; }
        .info { margin-bottom: 20px; }
    </style></head><body>";
    
    echo "<div class='header'>";
    echo "<h1>Export des données d'attendance</h1>";
    echo "<div class='info'>";
    echo "<p><strong>Période:</strong> " . formatDate($date_start) . " au " . formatDate($date_end) . "</p>";
    echo "<p><strong>Type:</strong> " . ($type === 'detailed' ? 'Détaillé' : 'Résumé') . "</p>";
    echo "<p><strong>Généré le:</strong> " . date('d/m/Y à H:i:s') . "</p>";
    echo "</div>";
    echo "</div>";
    
    echo "<table>";
    
    if ($type === 'detailed') {
        echo "<tr>";
        echo "<th>Matricule</th><th>Nom</th><th>Prénom</th><th>Classe</th>";
        echo "<th>Type</th><th>Date</th><th>Heure</th><th>Motif</th><th>Justification</th>";
        echo "</tr>";

        foreach ($data as $row) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($row['numero_matricule']) . "</td>";
            echo "<td>" . htmlspecialchars($row['eleve_nom']) . "</td>";
            echo "<td>" . htmlspecialchars($row['eleve_prenom']) . "</td>";
            echo "<td>" . htmlspecialchars($row['niveau'] . ' - ' . $row['classe_nom']) . "</td>";
            echo "<td>" . htmlspecialchars(ucfirst(str_replace('_', ' ', $row['type_absence']))) . "</td>";
            echo "<td>" . date('d/m/Y', strtotime($row['date_absence'])) . "</td>";
            echo "<td>" . date('H:i', strtotime($row['date_absence'])) . "</td>";
            echo "<td>" . htmlspecialchars($row['motif'] ?: '-') . "</td>";
            echo "<td>" . htmlspecialchars($row['justification'] ?: '-') . "</td>";
            echo "</tr>";
        }
    } else {
        echo "<tr>";
        echo "<th>Classe</th><th>Total incidents</th><th>Absences</th>";
        echo "<th>Retards</th><th>Justifiés</th><th>Élèves concernés</th>";
        echo "</tr>";
        
        foreach ($data as $row) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($row['niveau'] . ' - ' . $row['classe_nom']) . "</td>";
            echo "<td>" . $row['total_incidents'] . "</td>";
            echo "<td>" . $row['total_absences'] . "</td>";
            echo "<td>" . $row['total_retards'] . "</td>";
            echo "<td>" . $row['total_justifies'] . "</td>";
            echo "<td>" . $row['eleves_concernes'] . "</td>";
            echo "</tr>";
        }
    }
    
    echo "</table>";
    echo "</body></html>";
}

include '../../../../includes/header.php';
?>

<!-- Styles CSS modernes -->
<style>
.export-header {
    background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
    color: white;
    padding: 2rem 0;
    margin: -20px -15px 30px -15px;
    border-radius: 0 0 20px 20px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.1);
}

.export-header h1 {
    font-weight: 300;
    font-size: 2.5rem;
    margin-bottom: 0.5rem;
}

.export-header .subtitle {
    opacity: 0.9;
    font-size: 1.1rem;
}

.export-card {
    background: white;
    border-radius: 15px;
    padding: 2rem;
    box-shadow: 0 5px 15px rgba(0,0,0,0.08);
    border: none;
    transition: all 0.3s ease;
    margin-bottom: 2rem;
}

.export-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 10px 30px rgba(0,0,0,0.15);
}

.stats-overview {
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
    border-radius: 15px;
    padding: 1.5rem;
    margin-bottom: 2rem;
}

.stat-item {
    text-align: center;
    padding: 1rem;
}

.stat-number {
    font-size: 2rem;
    font-weight: 700;
    color: #28a745;
    display: block;
    margin-bottom: 0.5rem;
}

.stat-label {
    color: #6c757d;
    font-size: 0.9rem;
    font-weight: 500;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.form-section {
    background: #f8f9fa;
    border-radius: 10px;
    padding: 1.5rem;
    margin-bottom: 1.5rem;
    border-left: 4px solid #28a745;
}

.form-section h6 {
    color: #28a745;
    font-weight: 600;
    margin-bottom: 1rem;
    display: flex;
    align-items: center;
}

.form-section h6 i {
    margin-right: 0.5rem;
}

.btn-export {
    border-radius: 25px;
    padding: 0.75rem 2rem;
    font-weight: 600;
    transition: all 0.3s ease;
    border: none;
    box-shadow: 0 3px 15px rgba(0,0,0,0.1);
}

.btn-export:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 25px rgba(0,0,0,0.2);
}

.btn-export.excel {
    background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
    color: white;
}

.btn-export.csv {
    background: linear-gradient(135deg, #17a2b8 0%, #6f42c1 100%);
    color: white;
}

.btn-export.pdf {
    background: linear-gradient(135deg, #dc3545 0%, #fd7e14 100%);
    color: white;
}

.format-option {
    border: 2px solid #e9ecef;
    border-radius: 10px;
    padding: 1rem;
    text-align: center;
    cursor: pointer;
    transition: all 0.3s ease;
    margin-bottom: 1rem;
}

.format-option:hover {
    border-color: #28a745;
    background-color: #f8fff9;
}

.format-option.selected {
    border-color: #28a745;
    background-color: #d4edda;
}

.format-option i {
    font-size: 2rem;
    margin-bottom: 0.5rem;
    display: block;
}

.preview-section {
    background: white;
    border-radius: 15px;
    padding: 1.5rem;
    box-shadow: 0 2px 10px rgba(0,0,0,0.05);
    margin-top: 2rem;
}

@keyframes fadeInUp {
    from {
        opacity: 0;
        transform: translateY(30px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.animate-fade-in {
    animation: fadeInUp 0.6s ease-out;
}

.animate-delay-1 { animation-delay: 0.1s; }
.animate-delay-2 { animation-delay: 0.2s; }
.animate-delay-3 { animation-delay: 0.3s; }

@media (max-width: 768px) {
    .export-header {
        margin: -20px -15px 20px -15px;
        padding: 1.5rem 0;
    }

    .export-header h1 {
        font-size: 2rem;
    }

    .export-card {
        padding: 1rem;
    }
}
</style>

<!-- En-tête moderne -->
<div class="export-header">
    <div class="container-fluid">
        <div class="row align-items-center">
            <div class="col-md-8">
                <h1 class="animate-fade-in">
                    <i class="fas fa-download me-3"></i>
                    Export des données d'attendance
                </h1>
                <p class="subtitle animate-fade-in animate-delay-1">
                    Exportez vos données d'absences et retards dans différents formats
                </p>
            </div>
            <div class="col-md-4 text-end">
                <div class="animate-fade-in animate-delay-2">
                    <a href="../index.php" class="btn btn-light btn-lg" style="border-radius: 25px;">
                        <i class="fas fa-arrow-left me-2"></i>
                        Retour
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Aperçu des statistiques -->
<div class="stats-overview animate-fade-in animate-delay-1">
    <div class="row">
        <div class="col-lg-3 col-md-6">
            <div class="stat-item">
                <span class="stat-number"><?php echo number_format($quick_stats['total_records'] ?? 0); ?></span>
                <span class="stat-label">Total enregistrements</span>
            </div>
        </div>
        <div class="col-lg-3 col-md-6">
            <div class="stat-item">
                <span class="stat-number"><?php echo number_format($quick_stats['last_30_days'] ?? 0); ?></span>
                <span class="stat-label">30 derniers jours</span>
            </div>
        </div>
        <div class="col-lg-3 col-md-6">
            <div class="stat-item">
                <span class="stat-number"><?php echo number_format($quick_stats['last_7_days'] ?? 0); ?></span>
                <span class="stat-label">7 derniers jours</span>
            </div>
        </div>
        <div class="col-lg-3 col-md-6">
            <div class="stat-item">
                <span class="stat-number"><?php echo number_format($quick_stats['unique_students'] ?? 0); ?></span>
                <span class="stat-label">Élèves concernés</span>
            </div>
        </div>
    </div>
</div>

<!-- Formulaire d'export -->
<div class="export-card animate-fade-in animate-delay-2">
    <form method="POST" id="exportForm">
        <input type="hidden" name="export_action" value="1">

        <div class="row">
            <!-- Configuration de l'export -->
            <div class="col-lg-8">
                <!-- Période -->
                <div class="form-section">
                    <h6><i class="fas fa-calendar-alt"></i>Période d'export</h6>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="date_start" class="form-label">Date de début</label>
                            <input type="date" class="form-control form-control-lg" id="date_start" name="date_start"
                                   value="<?php echo date('Y-m-01'); ?>" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="date_end" class="form-label">Date de fin</label>
                            <input type="date" class="form-control form-control-lg" id="date_end" name="date_end"
                                   value="<?php echo date('Y-m-t'); ?>" required>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-12">
                            <div class="btn-group" role="group">
                                <button type="button" class="btn btn-outline-secondary btn-sm" onclick="setDateRange('today')">
                                    Aujourd'hui
                                </button>
                                <button type="button" class="btn btn-outline-secondary btn-sm" onclick="setDateRange('week')">
                                    Cette semaine
                                </button>
                                <button type="button" class="btn btn-outline-secondary btn-sm" onclick="setDateRange('month')">
                                    Ce mois
                                </button>
                                <button type="button" class="btn btn-outline-secondary btn-sm" onclick="setDateRange('year')">
                                    Cette année
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Classes -->
                <div class="form-section">
                    <h6><i class="fas fa-school"></i>Classes à inclure</h6>
                    <div class="row">
                        <div class="col-12 mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="select_all_classes" onchange="toggleAllClasses()">
                                <label class="form-check-label fw-bold" for="select_all_classes">
                                    Sélectionner toutes les classes
                                </label>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <?php foreach ($classes as $class): ?>
                            <div class="col-lg-4 col-md-6 mb-2">
                                <div class="form-check">
                                    <input class="form-check-input class-checkbox" type="checkbox"
                                           name="selected_classes[]" value="<?php echo $class['id']; ?>"
                                           id="class_<?php echo $class['id']; ?>">
                                    <label class="form-check-label" for="class_<?php echo $class['id']; ?>">
                                        <?php echo htmlspecialchars($class['niveau'] . ' - ' . $class['nom']); ?>
                                    </label>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Options avancées -->
                <div class="form-section">
                    <h6><i class="fas fa-cog"></i>Options d'export</h6>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="export_type" class="form-label">Type de données</label>
                            <select class="form-select form-select-lg" id="export_type" name="export_type">
                                <option value="summary">Résumé par classe</option>
                                <option value="detailed">Données détaillées</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <div class="form-check mt-4">
                                <input class="form-check-input" type="checkbox" id="include_justified" name="include_justified" checked>
                                <label class="form-check-label" for="include_justified">
                                    Inclure les absences justifiées
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Format d'export -->
            <div class="col-lg-4">
                <div class="form-section">
                    <h6><i class="fas fa-file-export"></i>Format d'export</h6>

                    <div class="format-option" onclick="selectFormat('excel')">
                        <input type="radio" name="export_format" value="excel" id="format_excel" checked style="display: none;">
                        <i class="fas fa-file-excel text-success"></i>
                        <div class="fw-bold">Excel (.xls)</div>
                        <small class="text-muted">Idéal pour l'analyse</small>
                    </div>

                    <div class="format-option" onclick="selectFormat('csv')">
                        <input type="radio" name="export_format" value="csv" id="format_csv" style="display: none;">
                        <i class="fas fa-file-csv text-info"></i>
                        <div class="fw-bold">CSV (.csv)</div>
                        <small class="text-muted">Compatible universellement</small>
                    </div>

                    <div class="format-option" onclick="selectFormat('pdf')">
                        <input type="radio" name="export_format" value="pdf" id="format_pdf" style="display: none;">
                        <i class="fas fa-file-pdf text-danger"></i>
                        <div class="fw-bold">PDF (.html)</div>
                        <small class="text-muted">Pour impression</small>
                    </div>
                </div>

                <!-- Bouton d'export -->
                <div class="d-grid">
                    <button type="submit" class="btn btn-export excel btn-lg" id="exportButton">
                        <i class="fas fa-download me-2"></i>
                        Générer l'export
                    </button>
                </div>
            </div>
        </div>
    </form>
</div>

<!-- Section de prévisualisation -->
<div class="preview-section animate-fade-in animate-delay-3">
    <h5 class="mb-3">
        <i class="fas fa-eye me-2"></i>
        Aperçu des données
    </h5>

    <div id="previewContent">
        <div class="text-center py-4">
            <i class="fas fa-search fa-3x text-muted mb-3"></i>
            <p class="text-muted">Configurez vos filtres et cliquez sur "Aperçu" pour voir les données</p>
            <button type="button" class="btn btn-outline-primary" onclick="loadPreview()">
                <i class="fas fa-eye me-1"></i>
                Charger l'aperçu
            </button>
        </div>
    </div>
</div>

<!-- Guide d'utilisation -->
<div class="export-card animate-fade-in animate-delay-3">
    <h5 class="mb-3">
        <i class="fas fa-question-circle me-2"></i>
        Guide d'utilisation
    </h5>

    <div class="row">
        <div class="col-md-6">
            <h6 class="text-primary">📊 Types d'export</h6>
            <ul class="list-unstyled">
                <li class="mb-2">
                    <strong>Résumé par classe :</strong> Statistiques agrégées par classe (nombre d'absences, retards, etc.)
                </li>
                <li class="mb-2">
                    <strong>Données détaillées :</strong> Liste complète de tous les incidents avec détails
                </li>
            </ul>
        </div>

        <div class="col-md-6">
            <h6 class="text-success">📁 Formats disponibles</h6>
            <ul class="list-unstyled">
                <li class="mb-2">
                    <strong>Excel (.xls) :</strong> Idéal pour l'analyse et les calculs
                </li>
                <li class="mb-2">
                    <strong>CSV (.csv) :</strong> Compatible avec tous les logiciels
                </li>
                <li class="mb-2">
                    <strong>PDF (.html) :</strong> Parfait pour l'impression et l'archivage
                </li>
            </ul>
        </div>
    </div>

    <div class="alert alert-info mt-3">
        <i class="fas fa-lightbulb me-2"></i>
        <strong>Conseil :</strong> Pour de gros volumes de données, privilégiez le format CSV qui est plus rapide à générer.
    </div>
</div>

<script>
// Variables globales
let selectedFormat = 'excel';

// Initialisation
document.addEventListener('DOMContentLoaded', function() {
    // Sélectionner le format Excel par défaut
    selectFormat('excel');

    // Enregistrer l'action de consultation
    fetch('../log-action.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            action: 'view_export_page',
            module: 'attendance',
            details: 'Consultation de la page d\'export des données'
        })
    }).catch(error => console.log('Log error:', error));
});

// Sélection du format d'export
function selectFormat(format) {
    selectedFormat = format;

    // Réinitialiser toutes les options
    document.querySelectorAll('.format-option').forEach(option => {
        option.classList.remove('selected');
    });

    // Sélectionner l'option choisie
    document.querySelector(`input[value="${format}"]`).checked = true;
    document.querySelector(`input[value="${format}"]`).closest('.format-option').classList.add('selected');

    // Mettre à jour le bouton d'export
    const button = document.getElementById('exportButton');
    const icons = {
        excel: 'fas fa-file-excel',
        csv: 'fas fa-file-csv',
        pdf: 'fas fa-file-pdf'
    };

    const classes = {
        excel: 'btn-export excel',
        csv: 'btn-export csv',
        pdf: 'btn-export pdf'
    };

    const labels = {
        excel: 'Générer Excel',
        csv: 'Générer CSV',
        pdf: 'Générer PDF'
    };

    button.className = `btn btn-lg ${classes[format]}`;
    button.innerHTML = `<i class="${icons[format]} me-2"></i>${labels[format]}`;
}

// Définir des plages de dates prédéfinies
function setDateRange(range) {
    const startInput = document.getElementById('date_start');
    const endInput = document.getElementById('date_end');
    const today = new Date();

    let startDate, endDate;

    switch (range) {
        case 'today':
            startDate = endDate = today;
            break;
        case 'week':
            startDate = new Date(today);
            startDate.setDate(today.getDate() - today.getDay());
            endDate = new Date(startDate);
            endDate.setDate(startDate.getDate() + 6);
            break;
        case 'month':
            startDate = new Date(today.getFullYear(), today.getMonth(), 1);
            endDate = new Date(today.getFullYear(), today.getMonth() + 1, 0);
            break;
        case 'year':
            startDate = new Date(today.getFullYear(), 0, 1);
            endDate = new Date(today.getFullYear(), 11, 31);
            break;
    }

    startInput.value = startDate.toISOString().split('T')[0];
    endInput.value = endDate.toISOString().split('T')[0];
}

// Sélectionner/désélectionner toutes les classes
function toggleAllClasses() {
    const selectAll = document.getElementById('select_all_classes');
    const checkboxes = document.querySelectorAll('.class-checkbox');

    checkboxes.forEach(checkbox => {
        checkbox.checked = selectAll.checked;
    });
}

// Charger l'aperçu des données
function loadPreview() {
    const formData = new FormData(document.getElementById('exportForm'));
    formData.delete('export_action'); // Retirer l'action d'export
    formData.append('preview_action', '1');

    const previewContent = document.getElementById('previewContent');
    previewContent.innerHTML = `
        <div class="text-center py-4">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Chargement...</span>
            </div>
            <p class="mt-2 text-muted">Chargement de l'aperçu...</p>
        </div>
    `;

    fetch('preview-data.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            displayPreview(data.data, data.type);
        } else {
            previewContent.innerHTML = `
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    ${data.message || 'Aucune donnée trouvée pour les critères sélectionnés'}
                </div>
            `;
        }
    })
    .catch(error => {
        console.error('Erreur:', error);
        previewContent.innerHTML = `
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle me-2"></i>
                Erreur lors du chargement de l'aperçu
            </div>
        `;
    });
}

// Afficher l'aperçu des données
function displayPreview(data, type) {
    const previewContent = document.getElementById('previewContent');

    if (!data || data.length === 0) {
        previewContent.innerHTML = `
            <div class="alert alert-info">
                <i class="fas fa-info-circle me-2"></i>
                Aucune donnée trouvée pour les critères sélectionnés
            </div>
        `;
        return;
    }

    let html = `
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h6 class="mb-0">Aperçu des données (${data.length} enregistrement${data.length > 1 ? 's' : ''})</h6>
            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="loadPreview()">
                <i class="fas fa-sync-alt me-1"></i>
                Actualiser
            </button>
        </div>
        <div class="table-responsive">
            <table class="table table-sm table-striped">
                <thead class="table-dark">
                    <tr>
    `;

    if (type === 'detailed') {
        html += `
            <th>Matricule</th>
            <th>Élève</th>
            <th>Classe</th>
            <th>Type</th>
            <th>Date</th>
            <th>Motif</th>
        `;
    } else {
        html += `
            <th>Classe</th>
            <th>Incidents</th>
            <th>Absences</th>
            <th>Retards</th>
            <th>Justifiés</th>
            <th>Élèves</th>
        `;
    }

    html += `
                    </tr>
                </thead>
                <tbody>
    `;

    // Afficher seulement les 10 premiers enregistrements pour l'aperçu
    const previewData = data.slice(0, 10);

    previewData.forEach(row => {
        html += '<tr>';

        if (type === 'detailed') {
            html += `
                <td>${row.numero_matricule || '-'}</td>
                <td>${row.eleve_nom || ''} ${row.eleve_prenom || ''}</td>
                <td>${row.niveau || ''} - ${row.classe_nom || ''}</td>
                <td><span class="badge bg-secondary">${row.type_absence || ''}</span></td>
                <td>${row.date_absence ? new Date(row.date_absence).toLocaleDateString('fr-FR') : '-'}</td>
                <td>${row.motif || '-'}</td>
            `;
        } else {
            html += `
                <td>${row.niveau || ''} - ${row.classe_nom || ''}</td>
                <td><span class="badge bg-primary">${row.total_incidents || 0}</span></td>
                <td><span class="badge bg-danger">${row.total_absences || 0}</span></td>
                <td><span class="badge bg-warning">${row.total_retards || 0}</span></td>
                <td><span class="badge bg-success">${row.total_justifies || 0}</span></td>
                <td><span class="badge bg-info">${row.eleves_concernes || 0}</span></td>
            `;
        }

        html += '</tr>';
    });

    html += `
                </tbody>
            </table>
        </div>
    `;

    if (data.length > 10) {
        html += `
            <div class="alert alert-info mt-3">
                <i class="fas fa-info-circle me-2"></i>
                Aperçu limité aux 10 premiers enregistrements. L'export complet contiendra ${data.length} enregistrement${data.length > 1 ? 's' : ''}.
            </div>
        `;
    }

    previewContent.innerHTML = html;
}

// Validation du formulaire
document.getElementById('exportForm').addEventListener('submit', function(e) {
    const startDate = document.getElementById('date_start').value;
    const endDate = document.getElementById('date_end').value;
    const selectedClasses = document.querySelectorAll('.class-checkbox:checked');

    if (!startDate || !endDate) {
        e.preventDefault();
        alert('Veuillez sélectionner une période de dates');
        return false;
    }

    if (new Date(startDate) > new Date(endDate)) {
        e.preventDefault();
        alert('La date de début doit être antérieure à la date de fin');
        return false;
    }

    // Vérifier qu'au moins une classe est sélectionnée (optionnel)
    if (selectedClasses.length === 0) {
        if (!confirm('Aucune classe sélectionnée. Exporter toutes les classes ?')) {
            e.preventDefault();
            return false;
        }
    }

    // Afficher un message de chargement
    const button = document.getElementById('exportButton');
    const originalText = button.innerHTML;
    button.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Génération en cours...';
    button.disabled = true;

    // Réactiver le bouton après 5 secondes (au cas où)
    setTimeout(() => {
        button.innerHTML = originalText;
        button.disabled = false;
    }, 5000);
});
</script>

<?php include '../../../../includes/footer.php'; ?>
