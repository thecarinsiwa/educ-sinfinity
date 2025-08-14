<?php
/**
 * Module Recouvrement - Export des Rapports
 * Application de gestion scolaire - République Démocratique du Congo
 */

require_once '../../../config/config.php';
require_once '../../../config/database.php';
require_once '../../../includes/functions.php';

// Vérifier l'authentification et les permissions
requireLogin();
if (!checkPermission('recouvrement') && !checkPermission('admin')) {
    showMessage('error', 'Accès refusé à cette page.');
    redirectTo('../../../index.php');
}

$type = $_GET['type'] ?? '';
$format = $_GET['format'] ?? 'excel';

// Fonction pour générer un export Excel simple (CSV)
function exportToCSV($data, $headers, $filename) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
    header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
    header('Expires: 0');
    
    $output = fopen('php://output', 'w');
    
    // Ajouter BOM pour UTF-8
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    // Écrire les en-têtes
    fputcsv($output, $headers, ';');
    
    // Écrire les données
    foreach ($data as $row) {
        fputcsv($output, $row, ';');
    }
    
    fclose($output);
    exit;
}

// Fonction pour générer un export PDF simple (HTML)
function exportToPDF($content, $title, $filename) {
    header('Content-Type: text/html; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '.html"');
    
    echo '<!DOCTYPE html>
    <html>
    <head>
        <meta charset="utf-8">
        <title>' . htmlspecialchars($title) . '</title>
        <style>
            body { font-family: Arial, sans-serif; margin: 20px; }
            table { width: 100%; border-collapse: collapse; margin: 20px 0; }
            th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
            th { background-color: #f2f2f2; font-weight: bold; }
            .header { text-align: center; margin-bottom: 30px; }
            .date { color: #666; font-size: 12px; }
        </style>
    </head>
    <body>
        <div class="header">
            <h1>' . htmlspecialchars($title) . '</h1>
            <p class="date">Généré le ' . date('d/m/Y à H:i') . '</p>
        </div>
        ' . $content . '
    </body>
    </html>';
    exit;
}

try {
    switch ($type) {
        case 'paiements':
            // Export des paiements
            $date_debut = $_GET['date_debut'] ?? date('Y-m-01');
            $date_fin = $_GET['date_fin'] ?? date('Y-m-t');
            $classe_id = $_GET['classe_id'] ?? '';
            $type_frais = $_GET['type_frais'] ?? '';
            $mode_paiement = $_GET['mode_paiement'] ?? '';
            
            // Construire la requête avec filtres
            $where_conditions = ["p.status = 'valide'"];
            $params = [];
            
            if (!empty($date_debut)) {
                $where_conditions[] = "DATE(p.date_paiement) >= ?";
                $params[] = $date_debut;
            }
            
            if (!empty($date_fin)) {
                $where_conditions[] = "DATE(p.date_paiement) <= ?";
                $params[] = $date_fin;
            }
            
            if (!empty($classe_id)) {
                $where_conditions[] = "i.classe_id = ?";
                $params[] = $classe_id;
            }
            
            if (!empty($type_frais)) {
                $where_conditions[] = "fs.type_frais_id = ?";
                $params[] = $type_frais;
            }
            
            if (!empty($mode_paiement)) {
                $where_conditions[] = "p.mode_paiement = ?";
                $params[] = $mode_paiement;
            }
            
            $where_clause = implode(' AND ', $where_conditions);
            
            $paiements = $database->query("
                SELECT 
                    p.date_paiement,
                    e.numero_matricule,
                    CONCAT(e.nom, ' ', e.prenom) as nom_complet,
                    cl.nom as classe_nom,
                    tf.nom as type_frais,
                    fs.nom as frais_nom,
                    p.montant,
                    p.mode_paiement,
                    p.reference_paiement,
                    p.status
                FROM paiements p
                JOIN eleves e ON p.eleve_id = e.id
                LEFT JOIN inscriptions i ON e.id = i.eleve_id AND i.status = 'inscrit'
                LEFT JOIN classes cl ON i.classe_id = cl.id
                LEFT JOIN frais_scolaires fs ON p.frais_id = fs.id
                LEFT JOIN types_frais tf ON fs.type_frais_id = tf.id
                WHERE $where_clause
                ORDER BY p.date_paiement DESC
            ", $params)->fetchAll();
            
            if ($format === 'excel') {
                $headers = [
                    'Date', 'Matricule', 'Nom Complet', 'Classe', 'Type de Frais', 
                    'Frais', 'Montant (FC)', 'Mode de Paiement', 'Référence', 'Statut'
                ];
                
                $data = [];
                foreach ($paiements as $paiement) {
                    $data[] = [
                        date('d/m/Y', strtotime($paiement['date_paiement'])),
                        $paiement['numero_matricule'],
                        $paiement['nom_complet'],
                        $paiement['classe_nom'] ?? 'N/A',
                        $paiement['type_frais'] ?? 'N/A',
                        $paiement['frais_nom'] ?? 'N/A',
                        number_format($paiement['montant'], 0, ',', ' '),
                        ucfirst(str_replace('_', ' ', $paiement['mode_paiement'])),
                        $paiement['reference_paiement'] ?? 'N/A',
                        ucfirst($paiement['status'])
                    ];
                }
                
                exportToCSV($data, $headers, 'rapport_paiements_' . date('Y-m-d'));
            } else {
                $content = '<table>';
                $content .= '<thead><tr>';
                $content .= '<th>Date</th><th>Matricule</th><th>Nom Complet</th><th>Classe</th>';
                $content .= '<th>Type de Frais</th><th>Montant (FC)</th><th>Mode de Paiement</th>';
                $content .= '</tr></thead><tbody>';
                
                foreach ($paiements as $paiement) {
                    $content .= '<tr>';
                    $content .= '<td>' . date('d/m/Y', strtotime($paiement['date_paiement'])) . '</td>';
                    $content .= '<td>' . htmlspecialchars($paiement['numero_matricule']) . '</td>';
                    $content .= '<td>' . htmlspecialchars($paiement['nom_complet']) . '</td>';
                    $content .= '<td>' . htmlspecialchars($paiement['classe_nom'] ?? 'N/A') . '</td>';
                    $content .= '<td>' . htmlspecialchars($paiement['type_frais'] ?? 'N/A') . '</td>';
                    $content .= '<td>' . number_format($paiement['montant'], 0, ',', ' ') . '</td>';
                    $content .= '<td>' . ucfirst(str_replace('_', ' ', $paiement['mode_paiement'])) . '</td>';
                    $content .= '</tr>';
                }
                
                $content .= '</tbody></table>';
                
                exportToPDF($content, 'Rapport des Paiements', 'rapport_paiements_' . date('Y-m-d'));
            }
            break;
            
        case 'solvabilite':
            // Export de la solvabilité
            $classe_id = $_GET['classe_id'] ?? '';
            $status_solvabilite = $_GET['status_solvabilite'] ?? '';
            
            $where_conditions = ["a.status = 'active'"];
            $params = [];
            
            if (!empty($classe_id)) {
                $where_conditions[] = "i.classe_id = ?";
                $params[] = $classe_id;
            }
            
            if (!empty($status_solvabilite)) {
                $where_conditions[] = "s.status_solvabilite = ?";
                $params[] = $status_solvabilite;
            }
            
            $where_clause = implode(' AND ', $where_conditions);
            
            $solvabilite = $database->query("
                SELECT 
                    e.numero_matricule,
                    CONCAT(e.nom, ' ', e.prenom) as nom_complet,
                    cl.nom as classe_nom,
                    s.total_paye,
                    s.solde_restant,
                    s.pourcentage_paye,
                    s.status_solvabilite,
                    s.derniere_maj
                FROM solvabilite_eleves s
                JOIN annees_scolaires a ON s.annee_scolaire_id = a.id
                JOIN eleves e ON s.eleve_id = e.id
                LEFT JOIN inscriptions i ON e.id = i.eleve_id AND i.status = 'inscrit'
                LEFT JOIN classes cl ON i.classe_id = cl.id
                WHERE $where_clause
                ORDER BY s.pourcentage_paye DESC
            ", $params)->fetchAll();
            
            if ($format === 'excel') {
                $headers = [
                    'Matricule', 'Nom Complet', 'Classe', 'Montant Payé (FC)', 
                    'Solde Restant (FC)', 'Pourcentage (%)', 'Statut', 'Dernière MAJ'
                ];
                
                $data = [];
                foreach ($solvabilite as $eleve) {
                    $data[] = [
                        $eleve['numero_matricule'],
                        $eleve['nom_complet'],
                        $eleve['classe_nom'] ?? 'N/A',
                        number_format($eleve['total_paye'], 0, ',', ' '),
                        number_format($eleve['solde_restant'], 0, ',', ' '),
                        number_format($eleve['pourcentage_paye'], 1),
                        match($eleve['status_solvabilite']) {
                            'solvable' => 'Solvable',
                            'partiellement_solvable' => 'Partiellement solvable',
                            'non_solvable' => 'Non solvable',
                            default => 'Inconnu'
                        },
                        date('d/m/Y', strtotime($eleve['derniere_maj']))
                    ];
                }
                
                exportToCSV($data, $headers, 'rapport_solvabilite_' . date('Y-m-d'));
            } else {
                $content = '<table>';
                $content .= '<thead><tr>';
                $content .= '<th>Matricule</th><th>Nom Complet</th><th>Classe</th>';
                $content .= '<th>Montant Payé (FC)</th><th>Pourcentage (%)</th><th>Statut</th>';
                $content .= '</tr></thead><tbody>';
                
                foreach ($solvabilite as $eleve) {
                    $content .= '<tr>';
                    $content .= '<td>' . htmlspecialchars($eleve['numero_matricule']) . '</td>';
                    $content .= '<td>' . htmlspecialchars($eleve['nom_complet']) . '</td>';
                    $content .= '<td>' . htmlspecialchars($eleve['classe_nom'] ?? 'N/A') . '</td>';
                    $content .= '<td>' . number_format($eleve['total_paye'], 0, ',', ' ') . '</td>';
                    $content .= '<td>' . number_format($eleve['pourcentage_paye'], 1) . '%</td>';
                    $content .= '<td>' . match($eleve['status_solvabilite']) {
                        'solvable' => 'Solvable',
                        'partiellement_solvable' => 'Partiellement solvable',
                        'non_solvable' => 'Non solvable',
                        default => 'Inconnu'
                    } . '</td>';
                    $content .= '</tr>';
                }
                
                $content .= '</tbody></table>';
                
                exportToPDF($content, 'Rapport de Solvabilité', 'rapport_solvabilite_' . date('Y-m-d'));
            }
            break;
            
        case 'presences':
            // Export des présences
            $date_debut = $_GET['date_debut'] ?? date('Y-m-01');
            $date_fin = $_GET['date_fin'] ?? date('Y-m-t');
            $classe_id = $_GET['classe_id'] ?? '';
            
            $where_conditions = ["1=1"];
            $params = [];
            
            if (!empty($date_debut)) {
                $where_conditions[] = "DATE(p.created_at) >= ?";
                $params[] = $date_debut;
            }
            
            if (!empty($date_fin)) {
                $where_conditions[] = "DATE(p.created_at) <= ?";
                $params[] = $date_fin;
            }
            
            if (!empty($classe_id)) {
                $where_conditions[] = "i.classe_id = ?";
                $params[] = $classe_id;
            }
            
            $where_clause = implode(' AND ', $where_conditions);
            
            $presences = $database->query("
                SELECT 
                    DATE(p.created_at) as date_presence,
                    e.numero_matricule,
                    CONCAT(e.nom, ' ', e.prenom) as nom_complet,
                    cl.nom as classe_nom,
                    p.type_scan,
                    p.lieu_scan,
                    COALESCE(p.heure_entree, p.heure_sortie, TIME(p.created_at)) as heure_scan
                FROM presences_qr p
                JOIN eleves e ON p.eleve_id = e.id
                LEFT JOIN inscriptions i ON e.id = i.eleve_id AND i.status = 'inscrit'
                LEFT JOIN classes cl ON i.classe_id = cl.id
                WHERE $where_clause
                ORDER BY p.created_at DESC
                LIMIT 1000
            ", $params)->fetchAll();
            
            if ($format === 'excel') {
                $headers = [
                    'Date', 'Matricule', 'Nom Complet', 'Classe', 
                    'Type de Scan', 'Lieu', 'Heure'
                ];
                
                $data = [];
                foreach ($presences as $presence) {
                    $data[] = [
                        date('d/m/Y', strtotime($presence['date_presence'])),
                        $presence['numero_matricule'],
                        $presence['nom_complet'],
                        $presence['classe_nom'] ?? 'N/A',
                        ucfirst($presence['type_scan']),
                        $presence['lieu_scan'] ?? 'N/A',
                        $presence['heure_scan']
                    ];
                }
                
                exportToCSV($data, $headers, 'rapport_presences_' . date('Y-m-d'));
            } else {
                $content = '<table>';
                $content .= '<thead><tr>';
                $content .= '<th>Date</th><th>Matricule</th><th>Nom Complet</th><th>Classe</th>';
                $content .= '<th>Type de Scan</th><th>Lieu</th><th>Heure</th>';
                $content .= '</tr></thead><tbody>';
                
                foreach ($presences as $presence) {
                    $content .= '<tr>';
                    $content .= '<td>' . date('d/m/Y', strtotime($presence['date_presence'])) . '</td>';
                    $content .= '<td>' . htmlspecialchars($presence['numero_matricule']) . '</td>';
                    $content .= '<td>' . htmlspecialchars($presence['nom_complet']) . '</td>';
                    $content .= '<td>' . htmlspecialchars($presence['classe_nom'] ?? 'N/A') . '</td>';
                    $content .= '<td>' . ucfirst($presence['type_scan']) . '</td>';
                    $content .= '<td>' . htmlspecialchars($presence['lieu_scan'] ?? 'N/A') . '</td>';
                    $content .= '<td>' . $presence['heure_scan'] . '</td>';
                    $content .= '</tr>';
                }
                
                $content .= '</tbody></table>';
                
                exportToPDF($content, 'Rapport des Présences', 'rapport_presences_' . date('Y-m-d'));
            }
            break;
            
        default:
            throw new Exception('Type d\'export non supporté.');
    }
    
} catch (Exception $e) {
    // En cas d'erreur, rediriger vers la page des rapports
    showMessage('error', 'Erreur lors de l\'export : ' . $e->getMessage());
    redirectTo('index.php');
}
