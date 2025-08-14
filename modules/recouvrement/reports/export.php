<?php
/**
 * Module Recouvrement - Export des rapports
 * Application de gestion scolaire - République Démocratique du Congo
 */

require_once '../../../config/config.php';
require_once '../../../config/database.php';
require_once '../../../includes/functions.php';

// Vérifier l'authentification et les permissions
requireLogin();
if (!checkPermission('recouvrement') && !checkPermission('recouvrement_view')) {
    showMessage('error', 'Accès refusé à ce module.');
    redirectTo('../../dashboard.php');
}

// Obtenir l'année scolaire actuelle
$current_year = getCurrentAcademicYear();

// Paramètres d'export
$format = $_GET['format'] ?? 'excel';
$type = $_GET['type'] ?? 'general';
$period = $_GET['period'] ?? 'month';
$niveau_filter = $_GET['niveau'] ?? '';
$classe_filter = $_GET['classe'] ?? '';
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
$where_conditions = ["i.annee_scolaire_id = ?"];
$params = [$current_year['id']];

if ($niveau_filter) {
    $where_conditions[] = "c.niveau = ?";
    $params[] = $niveau_filter;
}

if ($classe_filter) {
    $where_conditions[] = "c.id = ?";
    $params[] = $classe_filter;
}

$where_clause = implode(' AND ', $where_conditions);

// Fonction pour formater les montants
function formatMontant($montant) {
    return number_format($montant, 0, ',', ' ') . ' FC';
}

// Fonction pour formater les dates
function formatDate($date) {
    return date('d/m/Y', strtotime($date));
}

// Fonction pour formater les pourcentages
function formatPourcentage($valeur, $total) {
    return $total > 0 ? round(($valeur / $total) * 100, 1) . '%' : '0%';
}

// Préparer les données selon le type d'export
switch ($type) {
    case 'debitors':
        // Liste des débiteurs
        $data = $database->query(
            "SELECT 
                e.nom,
                e.prenom,
                e.telephone,
                e.email,
                c.nom as classe_nom,
                c.niveau,
                dette.montant_du,
                DATEDIFF(CURDATE(), i.date_inscription) as jours_inscription,
                CASE 
                    WHEN dette.montant_du > 200000 THEN 'Très élevée'
                    WHEN dette.montant_du > 100000 THEN 'Élevée'
                    WHEN dette.montant_du > 50000 THEN 'Moyenne'
                    ELSE 'Faible'
                END as niveau_dette
             FROM eleves e
             JOIN inscriptions i ON e.id = i.eleve_id
             JOIN classes c ON i.classe_id = c.id
             JOIN (
                 SELECT 
                     e.id as eleve_id,
                     SUM(fs.montant) - COALESCE(SUM(p.montant), 0) as montant_du
                 FROM eleves e
                 JOIN inscriptions i ON e.id = i.eleve_id
                 JOIN classes c ON i.classe_id = c.id
                 JOIN frais_scolaires fs ON i.classe_id = fs.classe_id
                 LEFT JOIN paiements p ON e.id = p.eleve_id 
                     AND fs.type_frais COLLATE utf8mb4_unicode_ci = p.type_paiement COLLATE utf8mb4_unicode_ci
                     AND p.annee_scolaire_id = fs.annee_scolaire_id
                 WHERE $where_clause AND fs.annee_scolaire_id = ?
                 GROUP BY e.id
                 HAVING montant_du > 0
             ) dette ON e.id = dette.eleve_id
             WHERE $where_clause
             ORDER BY dette.montant_du DESC",
            array_merge($params, [$current_year['id']])
        )->fetchAll();
        
        $filename = "debitors_" . date('Y-m-d') . ".$format";
        $title = "Liste des débiteurs - " . date('d/m/Y');
        $headers = ['Nom', 'Prénom', 'Téléphone', 'Email', 'Classe', 'Niveau', 'Dette (FC)', 'Jours inscription', 'Niveau dette'];
        break;
        
    case 'niveau':
        // Dettes par niveau
        $data = $database->query(
            "SELECT 
                c.niveau,
                COUNT(DISTINCT e.id) as nombre_eleves,
                COUNT(DISTINCT CASE WHEN dette.montant_du > 0 THEN e.id END) as nombre_debiteurs,
                SUM(dette.montant_du) as total_dettes,
                AVG(CASE WHEN dette.montant_du > 0 THEN dette.montant_du END) as dette_moyenne,
                ROUND((COUNT(DISTINCT CASE WHEN dette.montant_du > 0 THEN e.id END) * 100.0 / COUNT(DISTINCT e.id)), 1) as pourcentage_debiteurs
             FROM eleves e
             JOIN inscriptions i ON e.id = i.eleve_id
             JOIN classes c ON i.classe_id = c.id
             JOIN (
                 SELECT 
                     e.id as eleve_id,
                     SUM(fs.montant) - COALESCE(SUM(p.montant), 0) as montant_du
                 FROM eleves e
                 JOIN inscriptions i ON e.id = i.eleve_id
                 JOIN classes c ON i.classe_id = c.id
                 JOIN frais_scolaires fs ON i.classe_id = fs.classe_id
                 LEFT JOIN paiements p ON e.id = p.eleve_id 
                     AND fs.type_frais COLLATE utf8mb4_unicode_ci = p.type_paiement COLLATE utf8mb4_unicode_ci
                     AND p.annee_scolaire_id = fs.annee_scolaire_id
                 WHERE $where_clause AND fs.annee_scolaire_id = ?
                 GROUP BY e.id
             ) dette ON e.id = dette.eleve_id
             WHERE $where_clause
             GROUP BY c.niveau
             ORDER BY total_dettes DESC",
            array_merge($params, [$current_year['id']])
        )->fetchAll();
        
        $filename = "dettes_par_niveau_" . date('Y-m-d') . ".$format";
        $title = "Dettes par niveau - " . date('d/m/Y');
        $headers = ['Niveau', 'Total élèves', 'Débiteurs', 'Total dettes (FC)', 'Dette moyenne (FC)', '% Débiteurs'];
        break;
        
    case 'classe':
        // Dettes par classe
        $data = $database->query(
            "SELECT 
                c.nom as classe_nom,
                c.niveau,
                COUNT(DISTINCT e.id) as nombre_eleves,
                COUNT(DISTINCT CASE WHEN dette.montant_du > 0 THEN e.id END) as nombre_debiteurs,
                SUM(dette.montant_du) as total_dettes,
                AVG(CASE WHEN dette.montant_du > 0 THEN dette.montant_du END) as dette_moyenne
             FROM eleves e
             JOIN inscriptions i ON e.id = i.eleve_id
             JOIN classes c ON i.classe_id = c.id
             JOIN (
                 SELECT 
                     e.id as eleve_id,
                     SUM(fs.montant) - COALESCE(SUM(p.montant), 0) as montant_du
                 FROM eleves e
                 JOIN inscriptions i ON e.id = i.eleve_id
                 JOIN classes c ON i.classe_id = c.id
                 JOIN frais_scolaires fs ON i.classe_id = fs.classe_id
                 LEFT JOIN paiements p ON e.id = p.eleve_id 
                     AND fs.type_frais COLLATE utf8mb4_unicode_ci = p.type_paiement COLLATE utf8mb4_unicode_ci
                     AND p.annee_scolaire_id = fs.annee_scolaire_id
                 WHERE $where_clause AND fs.annee_scolaire_id = ?
                 GROUP BY e.id
             ) dette ON e.id = dette.eleve_id
             WHERE $where_clause
             GROUP BY c.id, c.nom, c.niveau
             ORDER BY total_dettes DESC",
            array_merge($params, [$current_year['id']])
        )->fetchAll();
        
        $filename = "dettes_par_classe_" . date('Y-m-d') . ".$format";
        $title = "Dettes par classe - " . date('d/m/Y');
        $headers = ['Classe', 'Niveau', 'Total élèves', 'Débiteurs', 'Total dettes (FC)', 'Dette moyenne (FC)'];
        break;
        
    case 'paiements':
        // Évolution des paiements
        $data = $database->query(
            "SELECT 
                DATE_FORMAT(p.date_paiement, '%Y-%m') as mois,
                COUNT(DISTINCT p.eleve_id) as nombre_payeurs,
                SUM(p.montant) as total_paiements,
                COUNT(*) as nombre_transactions,
                AVG(p.montant) as montant_moyen
             FROM paiements p
             JOIN inscriptions i ON p.eleve_id = i.eleve_id
             WHERE i.annee_scolaire_id = ? AND p.date_paiement BETWEEN ? AND ?
             GROUP BY DATE_FORMAT(p.date_paiement, '%Y-%m')
             ORDER BY mois DESC",
            [$current_year['id'], $date_from, $date_to]
        )->fetchAll();
        
        $filename = "evolution_paiements_" . date('Y-m-d') . ".$format";
        $title = "Évolution des paiements - " . date('d/m/Y');
        $headers = ['Mois', 'Nombre payeurs', 'Total paiements (FC)', 'Nombre transactions', 'Montant moyen (FC)'];
        break;
        
    default:
        // Rapport général
        $data = $database->query(
            "SELECT 
                c.niveau,
                c.nom as classe_nom,
                COUNT(DISTINCT e.id) as nombre_eleves,
                COUNT(DISTINCT CASE WHEN dette.montant_du > 0 THEN e.id END) as nombre_debiteurs,
                SUM(dette.montant_du) as total_dettes,
                AVG(CASE WHEN dette.montant_du > 0 THEN dette.montant_du END) as dette_moyenne,
                SUM(p.montant) as total_paiements,
                ROUND((SUM(p.montant) * 100.0 / SUM(fs.montant)), 1) as taux_recouvrement
             FROM eleves e
             JOIN inscriptions i ON e.id = i.eleve_id
             JOIN classes c ON i.classe_id = c.id
             JOIN frais_scolaires fs ON i.classe_id = fs.classe_id
             LEFT JOIN paiements p ON e.id = p.eleve_id 
                 AND fs.type_frais COLLATE utf8mb4_unicode_ci = p.type_paiement COLLATE utf8mb4_unicode_ci
                 AND p.annee_scolaire_id = fs.annee_scolaire_id
                 AND p.date_paiement BETWEEN ? AND ?
             JOIN (
                 SELECT 
                     e.id as eleve_id,
                     SUM(fs.montant) - COALESCE(SUM(p.montant), 0) as montant_du
                 FROM eleves e
                 JOIN inscriptions i ON e.id = i.eleve_id
                 JOIN classes c ON i.classe_id = c.id
                 JOIN frais_scolaires fs ON i.classe_id = fs.classe_id
                 LEFT JOIN paiements p ON e.id = p.eleve_id 
                     AND fs.type_frais COLLATE utf8mb4_unicode_ci = p.type_paiement COLLATE utf8mb4_unicode_ci
                     AND p.annee_scolaire_id = fs.annee_scolaire_id
                 WHERE $where_clause AND fs.annee_scolaire_id = ?
                 GROUP BY e.id
             ) dette ON e.id = dette.eleve_id
             WHERE $where_clause AND fs.annee_scolaire_id = ?
             GROUP BY c.id, c.nom, c.niveau
             ORDER BY c.niveau, total_dettes DESC",
            array_merge($params, [$date_from, $date_to, $current_year['id'], $current_year['id']])
        )->fetchAll();
        
        $filename = "rapport_recouvrement_" . date('Y-m-d') . ".$format";
        $title = "Rapport de recouvrement - " . date('d/m/Y');
        $headers = ['Niveau', 'Classe', 'Total élèves', 'Débiteurs', 'Total dettes (FC)', 'Dette moyenne (FC)', 'Paiements (FC)', 'Taux recouvrement (%)'];
        break;
}

// Préparer les données pour l'export
$export_data = [];
foreach ($data as $row) {
    $export_row = [];
    foreach ($row as $key => $value) {
        if (strpos($key, 'montant') !== false || strpos($key, 'total') !== false) {
            $export_row[] = formatMontant($value);
        } elseif (strpos($key, 'pourcentage') !== false || strpos($key, 'taux') !== false) {
            $export_row[] = $value . '%';
        } elseif (strpos($key, 'date') !== false || strpos($key, 'mois') !== false) {
            $export_row[] = $value;
        } else {
            $export_row[] = $value;
        }
    }
    $export_data[] = $export_row;
}

// Exporter selon le format
if ($format === 'excel') {
    // Headers pour Excel
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: max-age=0');
    
    // BOM pour Excel
    echo "\xEF\xBB\xBF";
    
    // En-tête du fichier
    echo $title . "\n\n";
    
    // En-têtes des colonnes
    echo implode("\t", $headers) . "\n";
    
    // Données
    foreach ($export_data as $row) {
        echo implode("\t", $row) . "\n";
    }
    
} elseif ($format === 'csv') {
    // Headers pour CSV
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    
    // BOM pour Excel
    echo "\xEF\xBB\xBF";
    
    // En-tête du fichier
    echo $title . "\n\n";
    
    // En-têtes des colonnes
    echo implode(";", $headers) . "\n";
    
    // Données
    foreach ($export_data as $row) {
        echo implode(";", $row) . "\n";
    }
    
} else {
    // Format PDF (basique en HTML)
    header('Content-Type: text/html; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '.html"');
    
    echo '<!DOCTYPE html>
    <html>
    <head>
        <meta charset="utf-8">
        <title>' . $title . '</title>
        <style>
            body { font-family: Arial, sans-serif; margin: 20px; }
            table { border-collapse: collapse; width: 100%; margin-top: 20px; }
            th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
            th { background-color: #f2f2f2; font-weight: bold; }
            h1 { color: #333; }
            .header { text-align: center; margin-bottom: 30px; }
        </style>
    </head>
    <body>
        <div class="header">
            <h1>' . $title . '</h1>
            <p>Généré le ' . date('d/m/Y à H:i') . '</p>
        </div>
        
        <table>
            <thead>
                <tr>';
    
    foreach ($headers as $header) {
        echo '<th>' . htmlspecialchars($header) . '</th>';
    }
    
    echo '</tr>
            </thead>
            <tbody>';
    
    foreach ($export_data as $row) {
        echo '<tr>';
        foreach ($row as $cell) {
            echo '<td>' . htmlspecialchars($cell) . '</td>';
        }
        echo '</tr>';
    }
    
    echo '</tbody>
        </table>
    </body>
    </html>';
}

exit;
?>
