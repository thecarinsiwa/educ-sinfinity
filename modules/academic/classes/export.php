<?php
/**
 * Export des classes - PDF et Excel
 * Application de gestion scolaire - République Démocratique du Congo
 */

require_once '../../../config/config.php';
require_once '../../../config/database.php';
require_once '../../../includes/functions.php';

// Vérifier l'authentification et les permissions
requireLogin();
if (!checkPermission('academic') && !checkPermission('academic_view')) {
    showMessage('error', 'Accès refusé à ce module.');
    redirectTo('../index.php');
}

// Obtenir le format d'export
$format = sanitizeInput($_GET['format'] ?? 'excel');
if (!in_array($format, ['excel', 'pdf'])) {
    $format = 'excel';
}

// Obtenir l'année scolaire actuelle
$current_year = getCurrentAcademicYear();

// Paramètres de recherche et filtrage
$search = sanitizeInput($_GET['search'] ?? '');
$niveau_filter = sanitizeInput($_GET['niveau'] ?? '');

// Construction de la requête
$sql = "SELECT c.*, 
               COUNT(DISTINCT i.eleve_id) as nb_eleves,
               COUNT(DISTINCT et.enseignant_id) as nb_enseignants,
               p.nom as titulaire_nom, p.prenom as titulaire_prenom
        FROM classes c 
        LEFT JOIN inscriptions i ON c.id = i.classe_id AND i.status = 'inscrit'
        LEFT JOIN emplois_temps et ON c.id = et.classe_id
        LEFT JOIN personnel p ON c.titulaire_id = p.id
        WHERE c.annee_scolaire_id = ?";

$params = [$current_year['id'] ?? 0];

if (!empty($search)) {
    $sql .= " AND (c.nom LIKE ? OR c.description LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
}

if (!empty($niveau_filter)) {
    $sql .= " AND c.niveau = ?";
    $params[] = $niveau_filter;
}

$sql .= " GROUP BY c.id ORDER BY 
          CASE c.niveau 
              WHEN 'maternelle' THEN 1 
              WHEN 'primaire' THEN 2 
              WHEN 'secondaire' THEN 3 
              ELSE 4 
          END, c.nom";

$classes = $database->query($sql, $params)->fetchAll();

// Statistiques
$stats = [
    'total' => count($classes),
    'maternelle' => count(array_filter($classes, fn($c) => $c['niveau'] === 'maternelle')),
    'primaire' => count(array_filter($classes, fn($c) => $c['niveau'] === 'primaire')),
    'secondaire' => count(array_filter($classes, fn($c) => $c['niveau'] === 'secondaire')),
    'total_eleves' => array_sum(array_column($classes, 'nb_eleves'))
];

// Fonction pour formater le niveau
function formatNiveau($niveau) {
    $niveaux = [
        'maternelle' => 'Maternelle',
        'primaire' => 'Primaire',
        'secondaire' => 'Secondaire'
    ];
    return $niveaux[$niveau] ?? $niveau;
}

// Fonction pour formater le statut
function formatStatut($statut) {
    $statuts = [
        'active' => 'Active',
        'inactive' => 'Inactive',
        'archive' => 'Archivée'
    ];
    return $statuts[$statut] ?? $statut;
}

if ($format === 'excel') {
    // Export Excel
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment; filename="classes_' . date('Y-m-d_H-i-s') . '.xls"');
    header('Cache-Control: max-age=0');
    
    // Début du fichier Excel
    echo '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">';
    echo '<head>';
    echo '<meta charset="UTF-8">';
    echo '<style>';
    echo 'table { border-collapse: collapse; width: 100%; }';
    echo 'th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }';
    echo 'th { background-color: #f2f2f2; font-weight: bold; }';
    echo '.header { background-color: #007bff; color: white; font-weight: bold; }';
    echo '.stats { background-color: #e9ecef; font-weight: bold; }';
    echo '</style>';
    echo '</head>';
    echo '<body>';
    
    // Titre et informations
    echo '<table>';
    echo '<tr><td colspan="8" class="header" style="text-align: center; font-size: 18px;">LISTE DES CLASSES</td></tr>';
    echo '<tr><td colspan="8" style="text-align: center;">Année scolaire: ' . htmlspecialchars($current_year['nom'] ?? 'N/A') . '</td></tr>';
    echo '<tr><td colspan="8" style="text-align: center;">Date d\'export: ' . date('d/m/Y à H:i') . '</td></tr>';
    echo '<tr><td colspan="8">&nbsp;</td></tr>';
    
    // Statistiques
    echo '<tr class="stats">';
    echo '<td colspan="2">Total classes: ' . $stats['total'] . '</td>';
    echo '<td colspan="2">Maternelle: ' . $stats['maternelle'] . '</td>';
    echo '<td colspan="2">Primaire: ' . $stats['primaire'] . '</td>';
    echo '<td colspan="2">Secondaire: ' . $stats['secondaire'] . '</td>';
    echo '</tr>';
    echo '<tr class="stats">';
    echo '<td colspan="8">Total élèves: ' . $stats['total_eleves'] . '</td>';
    echo '</tr>';
    echo '<tr><td colspan="8">&nbsp;</td></tr>';
    
    // En-têtes des colonnes
    echo '<tr style="background-color: #f8f9fa; font-weight: bold;">';
    echo '<th>N°</th>';
    echo '<th>Nom de la classe</th>';
    echo '<th>Niveau</th>';
    echo '<th>Description</th>';
    echo '<th>Capacité</th>';
    echo '<th>Élèves inscrits</th>';
    echo '<th>Enseignants</th>';
    echo '<th>Titulaire</th>';
    echo '</tr>';
    
    // Données des classes
    $numero = 1;
    foreach ($classes as $classe) {
        echo '<tr>';
        echo '<td>' . $numero++ . '</td>';
        echo '<td>' . htmlspecialchars($classe['nom']) . '</td>';
        echo '<td>' . formatNiveau($classe['niveau']) . '</td>';
        echo '<td>' . htmlspecialchars($classe['description'] ?? '') . '</td>';
        echo '<td>' . ($classe['capacite'] ?? 'N/A') . '</td>';
        echo '<td>' . $classe['nb_eleves'] . '</td>';
        echo '<td>' . $classe['nb_enseignants'] . '</td>';
        echo '<td>' . htmlspecialchars(($classe['titulaire_nom'] ?? '') . ' ' . ($classe['titulaire_prenom'] ?? '')) . '</td>';
        echo '</tr>';
    }
    
    echo '</table>';
    echo '</body></html>';
    
} else {
    // Export PDF
    require_once '../../../vendor/tcpdf/tcpdf.php';
    
    // Créer une nouvelle instance de TCPDF
    $pdf = new TCPDF('L', 'mm', 'A4', true, 'UTF-8', false);
    
    // Définir les informations du document
    $pdf->SetCreator('Educ-Sinfinity');
    $pdf->SetAuthor('Système de Gestion Scolaire');
    $pdf->SetTitle('Liste des Classes - ' . ($current_year['nom'] ?? 'N/A'));
    $pdf->SetSubject('Export des classes');
    
    // Supprimer les en-têtes et pieds de page par défaut
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);
    
    // Définir les marges
    $pdf->SetMargins(15, 15, 15);
    $pdf->SetAutoPageBreak(TRUE, 15);
    
    // Ajouter une page
    $pdf->AddPage();
    
    // Définir la police
    $pdf->SetFont('helvetica', '', 10);
    
    // Titre principal
    $pdf->SetFont('helvetica', 'B', 16);
    $pdf->Cell(0, 10, 'LISTE DES CLASSES', 0, 1, 'C');
    $pdf->SetFont('helvetica', '', 10);
    $pdf->Cell(0, 8, 'Année scolaire: ' . ($current_year['nom'] ?? 'N/A'), 0, 1, 'C');
    $pdf->Cell(0, 8, 'Date d\'export: ' . date('d/m/Y à H:i'), 0, 1, 'C');
    $pdf->Ln(5);
    
    // Statistiques
    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->SetFillColor(240, 240, 240);
    $pdf->Cell(60, 8, 'Total classes: ' . $stats['total'], 1, 0, 'C', true);
    $pdf->Cell(60, 8, 'Maternelle: ' . $stats['maternelle'], 1, 0, 'C', true);
    $pdf->Cell(60, 8, 'Primaire: ' . $stats['primaire'], 1, 0, 'C', true);
    $pdf->Cell(60, 8, 'Secondaire: ' . $stats['secondaire'], 1, 1, 'C', true);
    $pdf->Cell(0, 8, 'Total élèves: ' . $stats['total_eleves'], 1, 1, 'C', true);
    $pdf->Ln(5);
    
    // En-têtes du tableau
    $pdf->SetFont('helvetica', 'B', 9);
    $pdf->SetFillColor(200, 200, 200);
    $pdf->Cell(15, 8, 'N°', 1, 0, 'C', true);
    $pdf->Cell(40, 8, 'Nom de la classe', 1, 0, 'C', true);
    $pdf->Cell(25, 8, 'Niveau', 1, 0, 'C', true);
    $pdf->Cell(50, 8, 'Description', 1, 0, 'C', true);
    $pdf->Cell(20, 8, 'Capacité', 1, 0, 'C', true);
    $pdf->Cell(25, 8, 'Élèves', 1, 0, 'C', true);
    $pdf->Cell(25, 8, 'Enseignants', 1, 0, 'C', true);
    $pdf->Cell(50, 8, 'Titulaire', 1, 0, 'C', true);
    $pdf->Cell(30, 8, 'Statut', 1, 1, 'C', true);
    
    // Données des classes
    $pdf->SetFont('helvetica', '', 8);
    $numero = 1;
    foreach ($classes as $classe) {
        // Vérifier si on doit passer à une nouvelle page
        if ($pdf->GetY() > 180) {
            $pdf->AddPage();
            // Répéter les en-têtes
            $pdf->SetFont('helvetica', 'B', 9);
            $pdf->SetFillColor(200, 200, 200);
            $pdf->Cell(15, 8, 'N°', 1, 0, 'C', true);
            $pdf->Cell(40, 8, 'Nom de la classe', 1, 0, 'C', true);
            $pdf->Cell(25, 8, 'Niveau', 1, 0, 'C', true);
            $pdf->Cell(50, 8, 'Description', 1, 0, 'C', true);
            $pdf->Cell(20, 8, 'Capacité', 1, 0, 'C', true);
            $pdf->Cell(25, 8, 'Élèves', 1, 0, 'C', true);
            $pdf->Cell(25, 8, 'Enseignants', 1, 0, 'C', true);
            $pdf->Cell(50, 8, 'Titulaire', 1, 0, 'C', true);
            $pdf->Cell(30, 8, 'Statut', 1, 1, 'C', true);
            $pdf->SetFont('helvetica', '', 8);
        }
        
        $pdf->Cell(15, 8, $numero++, 1, 0, 'C');
        $pdf->Cell(40, 8, $classe['nom'], 1, 0, 'L');
        $pdf->Cell(25, 8, formatNiveau($classe['niveau']), 1, 0, 'C');
        $pdf->Cell(50, 8, substr($classe['description'] ?? '', 0, 30) . (strlen($classe['description'] ?? '') > 30 ? '...' : ''), 1, 0, 'L');
        $pdf->Cell(20, 8, $classe['capacite'] ?? 'N/A', 1, 0, 'C');
        $pdf->Cell(25, 8, $classe['nb_eleves'], 1, 0, 'C');
        $pdf->Cell(25, 8, $classe['nb_enseignants'], 1, 0, 'C');
        $pdf->Cell(50, 8, ($classe['titulaire_nom'] ?? '') . ' ' . ($classe['titulaire_prenom'] ?? ''), 1, 0, 'L');
        $pdf->Cell(30, 8, formatStatut($classe['statut'] ?? 'active'), 1, 1, 'C');
    }
    
    // Informations de pied de page
    $pdf->Ln(10);
    $pdf->SetFont('helvetica', 'I', 8);
    $pdf->Cell(0, 6, 'Document généré automatiquement par le système de gestion scolaire Educ-Sinfinity', 0, 1, 'C');
    $pdf->Cell(0, 6, 'Page ' . $pdf->getAliasNumPage() . '/' . $pdf->getAliasNbPages(), 0, 1, 'C');
    
    // Sortie du PDF
    $pdf->Output('classes_' . date('Y-m-d_H-i-s') . '.pdf', 'D');
}

exit;
?>
