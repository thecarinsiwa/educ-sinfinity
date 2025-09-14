<?php
/**
 * Module Gestion des Utilisateurs - Export des logs
 * Application de gestion scolaire - République Démocratique du Congo
 */

require_once '../../../config/config.php';
require_once '../../../config/database.php';
require_once '../../../includes/functions.php';
require_once '../../../includes/permissions-pages.php';

// Vérifier l'authentification et les permissions
requireLogin();
requirePagePermission('admin', 'logs/export', 'read', '../../../dashboard.php');

// Paramètres de filtrage
$user_filter = (int)($_GET['user_id'] ?? 0);
$action_filter = sanitizeInput($_GET['action'] ?? '');
$date_from = sanitizeInput($_GET['date_from'] ?? '');
$date_to = sanitizeInput($_GET['date_to'] ?? '');
$format = sanitizeInput($_GET['format'] ?? 'excel');

// Construction des conditions WHERE
$where_conditions = [];
$params = [];

if ($user_filter) {
    $where_conditions[] = "ual.user_id = ?";
    $params[] = $user_filter;
}

if ($action_filter) {
    $where_conditions[] = "ual.action LIKE ?";
    $params[] = "%$action_filter%";
}

if ($date_from) {
    $where_conditions[] = "DATE(ual.created_at) >= ?";
    $params[] = $date_from;
}

if ($date_to) {
    $where_conditions[] = "DATE(ual.created_at) <= ?";
    $params[] = $date_to;
}

$where_clause = !empty($where_conditions) ? implode(' AND ', $where_conditions) : '1=1';

// Récupérer tous les logs (sans pagination pour l'export)
$logs = $database->query(
    "SELECT ual.*, u.username, u.nom, u.prenom, r.nom as role
     FROM user_actions_log ual
     JOIN users u ON ual.user_id = u.id
     LEFT JOIN roles r ON u.role_id = r.id
     WHERE $where_clause
     ORDER BY ual.created_at DESC",
    $params
)->fetchAll();

// Informations sur l'export
$export_info = [
    'date_export' => date('Y-m-d H:i:s'),
    'total_logs' => count($logs),
    'filters' => [
        'user_id' => $user_filter,
        'action' => $action_filter,
        'date_from' => $date_from,
        'date_to' => $date_to
    ]
];

if ($format === 'excel') {
    // Export Excel
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment; filename="logs_export_' . date('Y-m-d_H-i-s') . '.xls"');
    
    echo "<!DOCTYPE html>";
    echo "<html>";
    echo "<head>";
    echo "<meta charset='UTF-8'>";
    echo "<title>Export des Logs</title>";
    echo "</head>";
    echo "<body>";
    
    echo "<h2>Export des Logs - " . date('d/m/Y H:i:s') . "</h2>";
    echo "<p><strong>Total des logs :</strong> " . count($logs) . "</p>";
    
    if ($user_filter) {
        $user = $database->query("SELECT nom, prenom FROM users WHERE id = ?", [$user_filter])->fetch();
        if ($user) {
            echo "<p><strong>Utilisateur :</strong> " . htmlspecialchars($user['nom'] . ' ' . $user['prenom']) . "</p>";
        }
    }
    
    if ($date_from || $date_to) {
        echo "<p><strong>Période :</strong> ";
        if ($date_from) echo "Du " . $date_from;
        if ($date_from && $date_to) echo " au ";
        if ($date_to) echo $date_to;
        echo "</p>";
    }
    
    echo "<table border='1' cellpadding='5' cellspacing='0'>";
    echo "<thead>";
    echo "<tr style='background-color: #f0f0f0;'>";
    echo "<th>Date/Heure</th>";
    echo "<th>Utilisateur</th>";
    echo "<th>Rôle</th>";
    echo "<th>Action</th>";
    echo "<th>Détails</th>";
    echo "<th>Adresse IP</th>";
    echo "<th>User Agent</th>";
    echo "</tr>";
    echo "</thead>";
    echo "<tbody>";
    
    foreach ($logs as $log) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($log['created_at']) . "</td>";
        echo "<td>" . htmlspecialchars($log['nom'] . ' ' . $log['prenom'] . ' (@' . $log['username'] . ')') . "</td>";
        echo "<td>" . htmlspecialchars($log['role'] ?? 'N/A') . "</td>";
        echo "<td>" . htmlspecialchars(getActionLabel($log['action'])) . "</td>";
        echo "<td>" . htmlspecialchars($log['details']) . "</td>";
        echo "<td>" . htmlspecialchars($log['ip_address']) . "</td>";
        echo "<td>" . htmlspecialchars($log['user_agent'] ?? 'N/A') . "</td>";
        echo "</tr>";
    }
    
    echo "</tbody>";
    echo "</table>";
    echo "</body>";
    echo "</html>";
    
} elseif ($format === 'pdf') {
    // Export PDF
    require_once '../../../vendor/tcpdf/tcpdf.php';
    
    $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
    
    // Informations du document
    $pdf->SetCreator('Educ-Sinfinity');
    $pdf->SetAuthor('Système de Gestion Scolaire');
    $pdf->SetTitle('Export des Logs');
    $pdf->SetSubject('Rapport des actions utilisateurs');
    
    // Marges
    $pdf->SetMargins(15, 15, 15);
    $pdf->SetHeaderMargin(10);
    $pdf->SetFooterMargin(10);
    
    // Police
    $pdf->SetFont('helvetica', '', 10);
    
    // Ajouter une page
    $pdf->AddPage();
    
    // Titre
    $pdf->SetFont('helvetica', 'B', 16);
    $pdf->Cell(0, 10, 'Export des Logs - ' . date('d/m/Y H:i:s'), 0, 1, 'C');
    $pdf->Ln(5);
    
    // Informations
    $pdf->SetFont('helvetica', '', 10);
    $pdf->Cell(0, 5, 'Total des logs : ' . count($logs), 0, 1);
    
    if ($user_filter) {
        $user = $database->query("SELECT nom, prenom FROM users WHERE id = ?", [$user_filter])->fetch();
        if ($user) {
            $pdf->Cell(0, 5, 'Utilisateur : ' . $user['nom'] . ' ' . $user['prenom'], 0, 1);
        }
    }
    
    if ($date_from || $date_to) {
        $period = '';
        if ($date_from) $period .= 'Du ' . $date_from;
        if ($date_from && $date_to) $period .= ' au ';
        if ($date_to) $period .= $date_to;
        $pdf->Cell(0, 5, 'Période : ' . $period, 0, 1);
    }
    
    $pdf->Ln(10);
    
    // Tableau
    $pdf->SetFont('helvetica', 'B', 8);
    $pdf->Cell(30, 8, 'Date/Heure', 1, 0, 'C');
    $pdf->Cell(40, 8, 'Utilisateur', 1, 0, 'C');
    $pdf->Cell(20, 8, 'Rôle', 1, 0, 'C');
    $pdf->Cell(30, 8, 'Action', 1, 0, 'C');
    $pdf->Cell(50, 8, 'Détails', 1, 0, 'C');
    $pdf->Cell(20, 8, 'IP', 1, 1, 'C');
    
    $pdf->SetFont('helvetica', '', 7);
    
    foreach ($logs as $log) {
        $pdf->Cell(30, 6, $log['created_at'], 1, 0, 'C');
        $pdf->Cell(40, 6, $log['nom'] . ' ' . $log['prenom'], 1, 0, 'L');
        $pdf->Cell(20, 6, $log['role'] ?? 'N/A', 1, 0, 'C');
        $pdf->Cell(30, 6, getActionLabel($log['action']), 1, 0, 'L');
        $pdf->Cell(50, 6, substr($log['details'], 0, 30) . '...', 1, 0, 'L');
        $pdf->Cell(20, 6, $log['ip_address'], 1, 1, 'C');
    }
    
    // Sortie
    $pdf->Output('logs_export_' . date('Y-m-d_H-i-s') . '.pdf', 'D');
    
} elseif ($format === 'csv') {
    // Export CSV
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="logs_export_' . date('Y-m-d_H-i-s') . '.csv"');
    
    // BOM pour UTF-8
    echo "\xEF\xBB\xBF";
    
    // En-têtes CSV
    echo "Date/Heure,Utilisateur,Rôle,Action,Détails,Adresse IP,User Agent\n";
    
    // Données
    foreach ($logs as $log) {
        $row = [
            $log['created_at'],
            $log['nom'] . ' ' . $log['prenom'] . ' (@' . $log['username'] . ')',
            $log['role'] ?? 'N/A',
            getActionLabel($log['action']),
            str_replace('"', '""', $log['details']),
            $log['ip_address'],
            $log['user_agent'] ?? 'N/A'
        ];
        
        echo '"' . implode('","', $row) . '"' . "\n";
    }
    
} else {
    // Format non supporté
    showMessage('error', 'Format d\'export non supporté');
    redirectTo('report.php?' . http_build_query($_GET));
}
?>
