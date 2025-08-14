<?php
/**
 * Export des mouvements d'élèves (transferts)
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

$page_title = "Export des mouvements d'élèves";

// Traitement de l'export direct (depuis les rapports)
if (isset($_GET['export']) && $_GET['export'] === 'excel') {
    // Récupérer les paramètres de filtre depuis l'URL
    $period = $_GET['period'] ?? 'month';
    $type_filter = $_GET['type'] ?? '';
    $status_filter = $_GET['status'] ?? '';
    $date_from = $_GET['date_from'] ?? '';
    $date_to = $_GET['date_to'] ?? '';
    
    // Définir les dates selon la période
    switch ($period) {
        case 'week':
            $date_from = $date_from ?: date('Y-m-d', strtotime('-7 days'));
            $date_to = $date_to ?: date('Y-m-d');
            break;
        case 'month':
            $date_from = $date_from ?: date('Y-m-01');
            $date_to = $date_to ?: date('Y-m-t');
            break;
        case 'year':
            $date_from = $date_from ?: date('Y-01-01');
            $date_to = $date_to ?: date('Y-12-31');
            break;
        case 'custom':
            $date_from = $date_from ?: date('Y-m-01');
            $date_to = $date_to ?: date('Y-m-d');
            break;
    }
    
    // Construire les conditions WHERE
    $where_conditions = ["t.date_demande BETWEEN ? AND ?"];
    $params = [$date_from, $date_to];
    
    if ($type_filter) {
        $where_conditions[] = "t.type_mouvement = ?";
        $params[] = $type_filter;
    }
    
    if ($status_filter) {
        $where_conditions[] = "t.statut = ?";
        $params[] = $status_filter;
    }
    
    $where_clause = implode(' AND ', $where_conditions);
    
    // Récupérer les données
    $data = $database->query(
        "SELECT t.*, e.numero_matricule, e.nom, e.prenom, e.date_naissance, e.sexe,
                c_orig.nom as classe_origine_nom, c_orig.niveau as classe_origine_niveau,
                c_dest.nom as classe_destination_nom, c_dest.niveau as classe_destination_niveau,
                u_traite.nom as traite_par_nom, u_traite.prenom as traite_par_prenom,
                u_approuve.nom as approuve_par_nom, u_approuve.prenom as approuve_par_prenom
         FROM transfers t
         JOIN eleves e ON t.eleve_id = e.id
         LEFT JOIN classes c_orig ON t.classe_origine_id = c_orig.id
         LEFT JOIN classes c_dest ON t.classe_destination_id = c_dest.id
         LEFT JOIN users u_traite ON t.traite_par = u_traite.id
         LEFT JOIN users u_approuve ON t.approuve_par = u_approuve.id
         WHERE $where_clause
         ORDER BY t.date_demande DESC, t.id DESC",
        $params
    )->fetchAll();
    
    generateExcelExport($data, $date_from, $date_to, $type_filter, $status_filter);
    exit;
}

// Traitement du formulaire d'export
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['export_action'])) {
    try {
        $export_format = $_POST['export_format'] ?? 'excel';
        $date_start = $_POST['date_start'] ?? '';
        $date_end = $_POST['date_end'] ?? '';
        $selected_types = $_POST['selected_types'] ?? [];
        $selected_statuses = $_POST['selected_statuses'] ?? [];
        $export_type = $_POST['export_type'] ?? 'detailed';
        
        // Validation
        if (!$date_start || !$date_end) {
            throw new Exception('Période de dates requise');
        }
        
        if (strtotime($date_start) > strtotime($date_end)) {
            throw new Exception('La date de début doit être antérieure à la date de fin');
        }
        
        // Construire la requête
        $where_conditions = [
            "t.date_demande >= ?",
            "t.date_demande <= ?"
        ];
        $params = [$date_start, $date_end . ' 23:59:59'];
        
        if (!empty($selected_types)) {
            $placeholders = str_repeat('?,', count($selected_types) - 1) . '?';
            $where_conditions[] = "t.type_mouvement IN ($placeholders)";
            $params = array_merge($params, $selected_types);
        }
        
        if (!empty($selected_statuses)) {
            $placeholders = str_repeat('?,', count($selected_statuses) - 1) . '?';
            $where_conditions[] = "t.statut IN ($placeholders)";
            $params = array_merge($params, $selected_statuses);
        }
        
        $where_clause = implode(' AND ', $where_conditions);
        
        // Requête selon le type d'export
        if ($export_type === 'detailed') {
            $query = "
                SELECT t.*, e.numero_matricule, e.nom, e.prenom, e.date_naissance, e.sexe, e.adresse,
                       c_orig.nom as classe_origine_nom, c_orig.niveau as classe_origine_niveau,
                       c_dest.nom as classe_destination_nom, c_dest.niveau as classe_destination_niveau,
                       u_traite.nom as traite_par_nom, u_traite.prenom as traite_par_prenom,
                       u_approuve.nom as approuve_par_nom, u_approuve.prenom as approuve_par_prenom
                FROM transfers t
                JOIN eleves e ON t.eleve_id = e.id
                LEFT JOIN classes c_orig ON t.classe_origine_id = c_orig.id
                LEFT JOIN classes c_dest ON t.classe_destination_id = c_dest.id
                LEFT JOIN users u_traite ON t.traite_par = u_traite.id
                LEFT JOIN users u_approuve ON t.approuve_par = u_approuve.id
                WHERE $where_clause
                ORDER BY t.date_demande DESC, t.id DESC
            ";
        } else {
            $query = "
                SELECT 
                    t.type_mouvement,
                    t.statut,
                    COUNT(*) as nombre_transfers,
                    COUNT(DISTINCT t.eleve_id) as eleves_concernes,
                    SUM(t.frais_transfert) as total_frais,
                    SUM(t.frais_payes) as total_payes,
                    AVG(DATEDIFF(t.date_approbation, t.date_demande)) as delai_moyen_approbation
                FROM transfers t
                WHERE $where_clause
                GROUP BY t.type_mouvement, t.statut
                ORDER BY t.type_mouvement, t.statut
            ";
        }
        
        $data = $database->query($query, $params)->fetchAll();
        
        if (empty($data)) {
            throw new Exception('Aucune donnée trouvée pour les critères sélectionnés');
        }
        
        // Générer l'export selon le format
        switch ($export_format) {
            case 'excel':
                generateExcelExport($data, $date_start, $date_end, implode(',', $selected_types), implode(',', $selected_statuses), $export_type);
                break;
            case 'csv':
                generateCSVExport($data, $date_start, $date_end, $export_type);
                break;
            case 'pdf':
                generatePDFExport($data, $date_start, $date_end, $export_type);
                break;
            default:
                throw new Exception('Format d\'export non supporté');
        }
        
        // Enregistrer l'action
        logUserAction(
            'export_movements',
            'transfers',
            "Export $export_format - Type: $export_type, Période: $date_start à $date_end, Enregistrements: " . count($data),
            null
        );
        
        exit; // L'export a été généré, arrêter l'exécution
        
    } catch (Exception $e) {
        showMessage('error', $e->getMessage());
    }
}

// Statistiques rapides pour l'aperçu
$quick_stats = $database->query(
    "SELECT 
        COUNT(*) as total_records,
        COUNT(CASE WHEN DATE(date_demande) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) THEN 1 END) as last_30_days,
        COUNT(CASE WHEN DATE(date_demande) >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) THEN 1 END) as last_7_days,
        COUNT(DISTINCT eleve_id) as unique_students,
        COUNT(CASE WHEN type_mouvement = 'transfert_entrant' THEN 1 END) as entrants,
        COUNT(CASE WHEN type_mouvement = 'transfert_sortant' THEN 1 END) as sortants,
        COUNT(CASE WHEN type_mouvement = 'sortie_definitive' THEN 1 END) as sorties
     FROM transfers"
)->fetch();

// Fonctions d'export
function generateExcelExport($data, $date_start, $date_end, $types = '', $statuses = '', $export_type = 'detailed') {
    $filename = "movements_export_" . date('Y-m-d_H-i-s') . ".xls";
    
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: max-age=0');
    
    echo "<html><head><meta charset='UTF-8'></head><body>";
    echo "<h2>Export des mouvements d'élèves</h2>";
    echo "<p>Période: " . formatDate($date_start) . " au " . formatDate($date_end) . "</p>";
    echo "<p>Type: " . ($export_type === 'detailed' ? 'Détaillé' : 'Résumé') . "</p>";
    if ($types) echo "<p>Types: " . $types . "</p>";
    if ($statuses) echo "<p>Statuts: " . $statuses . "</p>";
    echo "<p>Généré le: " . date('d/m/Y à H:i:s') . "</p>";
    echo "<br>";
    
    echo "<table border='1' cellpadding='5' cellspacing='0'>";
    
    if ($export_type === 'detailed') {
        echo "<tr style='background-color: #f0f0f0; font-weight: bold;'>";
        echo "<th>Matricule</th>";
        echo "<th>Nom</th>";
        echo "<th>Prénom</th>";
        echo "<th>Type mouvement</th>";
        echo "<th>École origine</th>";
        echo "<th>École destination</th>";
        echo "<th>Classe origine</th>";
        echo "<th>Classe destination</th>";
        echo "<th>Date demande</th>";
        echo "<th>Date effective</th>";
        echo "<th>Statut</th>";
        echo "<th>Motif</th>";
        echo "<th>Frais</th>";
        echo "<th>Traité par</th>";
        echo "</tr>";
        
        foreach ($data as $row) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($row['numero_matricule']) . "</td>";
            echo "<td>" . htmlspecialchars($row['nom']) . "</td>";
            echo "<td>" . htmlspecialchars($row['prenom']) . "</td>";
            echo "<td>" . htmlspecialchars(ucfirst(str_replace('_', ' ', $row['type_mouvement']))) . "</td>";
            echo "<td>" . htmlspecialchars($row['ecole_origine'] ?: '-') . "</td>";
            echo "<td>" . htmlspecialchars($row['ecole_destination'] ?: '-') . "</td>";
            echo "<td>" . htmlspecialchars(($row['classe_origine_niveau'] ?? '') . ' - ' . ($row['classe_origine_nom'] ?? '')) . "</td>";
            echo "<td>" . htmlspecialchars(($row['classe_destination_niveau'] ?? '') . ' - ' . ($row['classe_destination_nom'] ?? '')) . "</td>";
            echo "<td>" . date('d/m/Y', strtotime($row['date_demande'])) . "</td>";
            echo "<td>" . ($row['date_effective'] ? date('d/m/Y', strtotime($row['date_effective'])) : '-') . "</td>";
            echo "<td>" . htmlspecialchars(ucfirst(str_replace('_', ' ', $row['statut']))) . "</td>";
            echo "<td>" . htmlspecialchars($row['motif'] ?: '-') . "</td>";
            echo "<td>" . number_format($row['frais_transfert'], 0, ',', ' ') . " FC</td>";
            echo "<td>" . htmlspecialchars(($row['traite_par_nom'] ?? '') . ' ' . ($row['traite_par_prenom'] ?? '')) . "</td>";
            echo "</tr>";
        }
    } else {
        echo "<tr style='background-color: #f0f0f0; font-weight: bold;'>";
        echo "<th>Type mouvement</th>";
        echo "<th>Statut</th>";
        echo "<th>Nombre</th>";
        echo "<th>Élèves concernés</th>";
        echo "<th>Total frais</th>";
        echo "<th>Total payé</th>";
        echo "<th>Délai moyen (jours)</th>";
        echo "</tr>";
        
        foreach ($data as $row) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars(ucfirst(str_replace('_', ' ', $row['type_mouvement']))) . "</td>";
            echo "<td>" . htmlspecialchars(ucfirst(str_replace('_', ' ', $row['statut']))) . "</td>";
            echo "<td>" . $row['nombre_transfers'] . "</td>";
            echo "<td>" . $row['eleves_concernes'] . "</td>";
            echo "<td>" . number_format($row['total_frais'], 0, ',', ' ') . " FC</td>";
            echo "<td>" . number_format($row['total_payes'], 0, ',', ' ') . " FC</td>";
            echo "<td>" . round($row['delai_moyen_approbation'] ?? 0, 1) . "</td>";
            echo "</tr>";
        }
    }
    
    echo "</table>";
    echo "</body></html>";
}

function generateCSVExport($data, $date_start, $date_end, $export_type) {
    $filename = "movements_export_" . date('Y-m-d_H-i-s') . ".csv";
    
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: max-age=0');
    
    $output = fopen('php://output', 'w');
    
    // BOM pour UTF-8
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    if ($export_type === 'detailed') {
        fputcsv($output, [
            'Matricule', 'Nom', 'Prénom', 'Type mouvement', 'École origine', 'École destination',
            'Classe origine', 'Classe destination', 'Date demande', 'Date effective', 'Statut',
            'Motif', 'Frais', 'Traité par'
        ], ';');
        
        foreach ($data as $row) {
            fputcsv($output, [
                $row['numero_matricule'],
                $row['nom'],
                $row['prenom'],
                ucfirst(str_replace('_', ' ', $row['type_mouvement'])),
                $row['ecole_origine'] ?: '-',
                $row['ecole_destination'] ?: '-',
                ($row['classe_origine_niveau'] ?? '') . ' - ' . ($row['classe_origine_nom'] ?? ''),
                ($row['classe_destination_niveau'] ?? '') . ' - ' . ($row['classe_destination_nom'] ?? ''),
                date('d/m/Y', strtotime($row['date_demande'])),
                $row['date_effective'] ? date('d/m/Y', strtotime($row['date_effective'])) : '-',
                ucfirst(str_replace('_', ' ', $row['statut'])),
                $row['motif'] ?: '-',
                number_format($row['frais_transfert'], 0, ',', ' ') . ' FC',
                ($row['traite_par_nom'] ?? '') . ' ' . ($row['traite_par_prenom'] ?? '')
            ], ';');
        }
    } else {
        fputcsv($output, [
            'Type mouvement', 'Statut', 'Nombre', 'Élèves concernés', 'Total frais', 'Total payé', 'Délai moyen (jours)'
        ], ';');
        
        foreach ($data as $row) {
            fputcsv($output, [
                ucfirst(str_replace('_', ' ', $row['type_mouvement'])),
                ucfirst(str_replace('_', ' ', $row['statut'])),
                $row['nombre_transfers'],
                $row['eleves_concernes'],
                number_format($row['total_frais'], 0, ',', ' ') . ' FC',
                number_format($row['total_payes'], 0, ',', ' ') . ' FC',
                round($row['delai_moyen_approbation'] ?? 0, 1)
            ], ';');
        }
    }
    
    fclose($output);
}

function generatePDFExport($data, $date_start, $date_end, $export_type) {
    $filename = "movements_export_" . date('Y-m-d_H-i-s') . ".html";
    
    header('Content-Type: text/html; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    
    echo "<!DOCTYPE html><html><head><meta charset='UTF-8'>";
    echo "<title>Export Mouvements PDF</title>";
    echo "<style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; font-weight: bold; }
        .header { text-align: center; margin-bottom: 30px; }
        .info { margin-bottom: 20px; }
    </style></head><body>";
    
    echo "<div class='header'>";
    echo "<h1>Export des mouvements d'élèves</h1>";
    echo "<div class='info'>";
    echo "<p><strong>Période:</strong> " . formatDate($date_start) . " au " . formatDate($date_end) . "</p>";
    echo "<p><strong>Type:</strong> " . ($export_type === 'detailed' ? 'Détaillé' : 'Résumé') . "</p>";
    echo "<p><strong>Généré le:</strong> " . date('d/m/Y à H:i:s') . "</p>";
    echo "</div>";
    echo "</div>";
    
    echo "<table>";
    
    if ($export_type === 'detailed') {
        echo "<tr>";
        echo "<th>Matricule</th><th>Nom</th><th>Prénom</th><th>Type</th>";
        echo "<th>École origine</th><th>École destination</th><th>Date demande</th><th>Statut</th>";
        echo "</tr>";
        
        foreach ($data as $row) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($row['numero_matricule']) . "</td>";
            echo "<td>" . htmlspecialchars($row['nom']) . "</td>";
            echo "<td>" . htmlspecialchars($row['prenom']) . "</td>";
            echo "<td>" . htmlspecialchars(ucfirst(str_replace('_', ' ', $row['type_mouvement']))) . "</td>";
            echo "<td>" . htmlspecialchars($row['ecole_origine'] ?: '-') . "</td>";
            echo "<td>" . htmlspecialchars($row['ecole_destination'] ?: '-') . "</td>";
            echo "<td>" . date('d/m/Y', strtotime($row['date_demande'])) . "</td>";
            echo "<td>" . htmlspecialchars(ucfirst(str_replace('_', ' ', $row['statut']))) . "</td>";
            echo "</tr>";
        }
    } else {
        echo "<tr>";
        echo "<th>Type mouvement</th><th>Statut</th><th>Nombre</th>";
        echo "<th>Élèves concernés</th><th>Total frais</th>";
        echo "</tr>";
        
        foreach ($data as $row) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars(ucfirst(str_replace('_', ' ', $row['type_mouvement']))) . "</td>";
            echo "<td>" . htmlspecialchars(ucfirst(str_replace('_', ' ', $row['statut']))) . "</td>";
            echo "<td>" . $row['nombre_transfers'] . "</td>";
            echo "<td>" . $row['eleves_concernes'] . "</td>";
            echo "<td>" . number_format($row['total_frais'], 0, ',', ' ') . " FC</td>";
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
    background: linear-gradient(135deg, #fd7e14 0%, #ffc107 100%);
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
    color: #fd7e14;
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
    border-left: 4px solid #fd7e14;
}

.form-section h6 {
    color: #fd7e14;
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
    border-color: #fd7e14;
    background-color: #fff8f0;
}

.format-option.selected {
    border-color: #fd7e14;
    background-color: #fff3e0;
}

.format-option i {
    font-size: 2rem;
    margin-bottom: 0.5rem;
    display: block;
}

.checkbox-group {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 0.5rem;
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
                    <i class="fas fa-file-export me-3"></i>
                    Export des mouvements d'élèves
                </h1>
                <p class="subtitle animate-fade-in animate-delay-1">
                    Exportez vos données de transferts dans différents formats
                </p>
            </div>
            <div class="col-md-4 text-end">
                <div class="animate-fade-in animate-delay-2">
                    <a href="../index.php" class="btn btn-light btn-export">
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
        <div class="col-lg-2 col-md-4 col-sm-6">
            <div class="stat-item">
                <span class="stat-number"><?php echo number_format($quick_stats['total_records'] ?? 0); ?></span>
                <span class="stat-label">Total mouvements</span>
            </div>
        </div>
        <div class="col-lg-2 col-md-4 col-sm-6">
            <div class="stat-item">
                <span class="stat-number"><?php echo number_format($quick_stats['entrants'] ?? 0); ?></span>
                <span class="stat-label">Entrants</span>
            </div>
        </div>
        <div class="col-lg-2 col-md-4 col-sm-6">
            <div class="stat-item">
                <span class="stat-number"><?php echo number_format($quick_stats['sortants'] ?? 0); ?></span>
                <span class="stat-label">Sortants</span>
            </div>
        </div>
        <div class="col-lg-2 col-md-4 col-sm-6">
            <div class="stat-item">
                <span class="stat-number"><?php echo number_format($quick_stats['sorties'] ?? 0); ?></span>
                <span class="stat-label">Sorties</span>
            </div>
        </div>
        <div class="col-lg-2 col-md-4 col-sm-6">
            <div class="stat-item">
                <span class="stat-number"><?php echo number_format($quick_stats['last_30_days'] ?? 0); ?></span>
                <span class="stat-label">30 derniers jours</span>
            </div>
        </div>
        <div class="col-lg-2 col-md-4 col-sm-6">
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

                <!-- Types de mouvements -->
                <div class="form-section">
                    <h6><i class="fas fa-exchange-alt"></i>Types de mouvements à inclure</h6>
                    <div class="checkbox-group">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="selected_types[]" value="transfert_entrant"
                                   id="type_entrant" checked>
                            <label class="form-check-label" for="type_entrant">
                                <i class="fas fa-arrow-right text-success me-1"></i>
                                Transferts entrants
                            </label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="selected_types[]" value="transfert_sortant"
                                   id="type_sortant" checked>
                            <label class="form-check-label" for="type_sortant">
                                <i class="fas fa-arrow-left text-warning me-1"></i>
                                Transferts sortants
                            </label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="selected_types[]" value="sortie_definitive"
                                   id="type_sortie" checked>
                            <label class="form-check-label" for="type_sortie">
                                <i class="fas fa-graduation-cap text-info me-1"></i>
                                Sorties définitives
                            </label>
                        </div>
                    </div>
                </div>

                <!-- Statuts -->
                <div class="form-section">
                    <h6><i class="fas fa-tasks"></i>Statuts à inclure</h6>
                    <div class="checkbox-group">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="selected_statuses[]" value="en_attente"
                                   id="status_attente" checked>
                            <label class="form-check-label" for="status_attente">
                                <i class="fas fa-clock text-warning me-1"></i>
                                En attente
                            </label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="selected_statuses[]" value="approuve"
                                   id="status_approuve" checked>
                            <label class="form-check-label" for="status_approuve">
                                <i class="fas fa-check text-info me-1"></i>
                                Approuvés
                            </label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="selected_statuses[]" value="rejete"
                                   id="status_rejete">
                            <label class="form-check-label" for="status_rejete">
                                <i class="fas fa-times text-danger me-1"></i>
                                Rejetés
                            </label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="selected_statuses[]" value="complete"
                                   id="status_complete" checked>
                            <label class="form-check-label" for="status_complete">
                                <i class="fas fa-check-circle text-success me-1"></i>
                                Complétés
                            </label>
                        </div>
                    </div>
                </div>

                <!-- Options avancées -->
                <div class="form-section">
                    <h6><i class="fas fa-cog"></i>Options d'export</h6>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="export_type" class="form-label">Type de données</label>
                            <select class="form-select form-select-lg" id="export_type" name="export_type">
                                <option value="detailed">Données détaillées</option>
                                <option value="summary">Résumé statistique</option>
                            </select>
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
                    <strong>Données détaillées :</strong> Liste complète de tous les mouvements avec informations complètes
                </li>
                <li class="mb-2">
                    <strong>Résumé statistique :</strong> Statistiques agrégées par type et statut
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

// Validation du formulaire
document.getElementById('exportForm').addEventListener('submit', function(e) {
    const startDate = document.getElementById('date_start').value;
    const endDate = document.getElementById('date_end').value;
    const selectedTypes = document.querySelectorAll('input[name="selected_types[]"]:checked');
    const selectedStatuses = document.querySelectorAll('input[name="selected_statuses[]"]:checked');

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

    if (selectedTypes.length === 0) {
        e.preventDefault();
        alert('Veuillez sélectionner au moins un type de mouvement');
        return false;
    }

    if (selectedStatuses.length === 0) {
        e.preventDefault();
        alert('Veuillez sélectionner au moins un statut');
        return false;
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
