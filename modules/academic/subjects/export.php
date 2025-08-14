<?php
/**
 * Module de gestion académique - Export des matières
 * Application de gestion scolaire - République Démocratique du Congo
 */

require_once '../../../config/config.php';
require_once '../../../config/database.php';
require_once '../../../includes/functions.php';

// Vérifier l'authentification et les permissions
requireLogin();
if (!checkPermission('academic') && !checkPermission('academic_view')) {
    showMessage('error', 'Accès refusé à cette fonctionnalité.');
    redirectTo('index.php');
}

// Paramètres d'export
$format = $_GET['format'] ?? 'excel';
$matiere_id = $_GET['id'] ?? null;

// Récupérer les données
if ($matiere_id) {
    // Export d'une matière spécifique
    $matiere = $database->query(
        "SELECT * FROM matieres WHERE id = ?",
        [$matiere_id]
    )->fetch();
    
    if (!$matiere) {
        showMessage('error', 'Matière non trouvée.');
        redirectTo('index.php');
    }
    
    $matieres = [$matiere];
    $filename_base = 'matiere_' . sanitizeFilename($matiere['nom']);
} else {
    // Export de toutes les matières
    $matieres = $database->query(
        "SELECT m.*, 
                COUNT(DISTINCT et.classe_id) as nb_classes,
                COUNT(DISTINCT et.enseignant_id) as nb_enseignants,
                COUNT(et.id) as nb_cours
         FROM matieres m 
         LEFT JOIN emplois_temps et ON m.id = et.matiere_id
         GROUP BY m.id 
         ORDER BY m.niveau, m.nom"
    )->fetchAll();
    
    $filename_base = 'matieres_' . date('Y-m-d');
}

// Fonction pour nettoyer les noms de fichiers
function sanitizeFilename($filename) {
    return preg_replace('/[^a-zA-Z0-9_-]/', '_', $filename);
}

if ($format === 'excel') {
    // Export Excel (CSV)
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename_base . '.csv"');
    
    // BOM pour UTF-8
    echo "\xEF\xBB\xBF";
    
    $output = fopen('php://output', 'w');
    
    // En-têtes
    if ($matiere_id) {
        // Export détaillé d'une matière
        fputcsv($output, [
            'Nom',
            'Code',
            'Niveau',
            'Type',
            'Coefficient',
            'Volume horaire (h/semaine)',
            'Description',
            'Objectifs',
            'Date de création'
        ]);
        
        foreach ($matieres as $matiere) {
            fputcsv($output, [
                $matiere['nom'],
                $matiere['code'] ?? '',
                ucfirst($matiere['niveau']),
                ucfirst($matiere['type']),
                $matiere['coefficient'] ?? '',
                $matiere['volume_horaire'] ?? '',
                $matiere['description'] ?? '',
                $matiere['objectifs'] ?? '',
                date('d/m/Y H:i', strtotime($matiere['created_at']))
            ]);
        }
    } else {
        // Export de toutes les matières avec statistiques
        fputcsv($output, [
            'Nom',
            'Code',
            'Niveau',
            'Type',
            'Coefficient',
            'Volume horaire',
            'Classes utilisant',
            'Enseignants',
            'Cours programmés',
            'Date de création'
        ]);
        
        foreach ($matieres as $matiere) {
            fputcsv($output, [
                $matiere['nom'],
                $matiere['code'] ?? '',
                ucfirst($matiere['niveau']),
                ucfirst($matiere['type']),
                $matiere['coefficient'] ?? '',
                $matiere['volume_horaire'] ? $matiere['volume_horaire'] . 'h' : '',
                $matiere['nb_classes'] ?? 0,
                $matiere['nb_enseignants'] ?? 0,
                $matiere['nb_cours'] ?? 0,
                date('d/m/Y', strtotime($matiere['created_at']))
            ]);
        }
    }
    
    fclose($output);
    exit;
    
} elseif ($format === 'pdf') {
    // Export PDF avec TCPDF
    require_once '../../../vendor/tcpdf/tcpdf.php';
    
    // Créer un nouveau document PDF
    $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
    
    // Informations du document
    $pdf->SetCreator('Educ-Sinfinity');
    $pdf->SetAuthor('École Sinfinity');
    $pdf->SetTitle($matiere_id ? 'Détails de la matière' : 'Liste des matières');
    $pdf->SetSubject('Export des matières');
    
    // Supprimer header/footer par défaut
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);
    
    // Marges
    $pdf->SetMargins(15, 15, 15);
    $pdf->SetAutoPageBreak(TRUE, 15);
    
    // Ajouter une page
    $pdf->AddPage();
    
    // En-tête du document
    $pdf->SetFont('helvetica', 'B', 20);
    $pdf->Cell(0, 10, 'EDUC-SINFINITY', 0, 1, 'C');
    $pdf->SetFont('helvetica', '', 12);
    $pdf->Cell(0, 5, 'Système de Gestion Scolaire - RDC', 0, 1, 'C');
    $pdf->Ln(5);
    
    $pdf->SetFont('helvetica', 'B', 16);
    $title = $matiere_id ? 'Détails de la matière : ' . $matieres[0]['nom'] : 'Liste des matières';
    $pdf->Cell(0, 10, $title, 0, 1, 'C');
    $pdf->Ln(5);
    
    $pdf->SetFont('helvetica', '', 10);
    $pdf->Cell(0, 5, 'Généré le ' . date('d/m/Y à H:i'), 0, 1, 'R');
    $pdf->Ln(10);
    
    if ($matiere_id) {
        // Export détaillé d'une matière
        $matiere = $matieres[0];
        
        $pdf->SetFont('helvetica', 'B', 12);
        $pdf->Cell(0, 8, 'Informations générales', 0, 1, 'L');
        $pdf->Ln(2);
        
        $pdf->SetFont('helvetica', '', 10);
        
        // Tableau des informations
        $pdf->Cell(40, 6, 'Nom :', 1, 0, 'L');
        $pdf->Cell(0, 6, $matiere['nom'], 1, 1, 'L');
        
        if ($matiere['code']) {
            $pdf->Cell(40, 6, 'Code :', 1, 0, 'L');
            $pdf->Cell(0, 6, $matiere['code'], 1, 1, 'L');
        }
        
        $pdf->Cell(40, 6, 'Niveau :', 1, 0, 'L');
        $pdf->Cell(0, 6, ucfirst($matiere['niveau']), 1, 1, 'L');
        
        $pdf->Cell(40, 6, 'Type :', 1, 0, 'L');
        $pdf->Cell(0, 6, ucfirst($matiere['type']), 1, 1, 'L');
        
        if ($matiere['coefficient']) {
            $pdf->Cell(40, 6, 'Coefficient :', 1, 0, 'L');
            $pdf->Cell(0, 6, $matiere['coefficient'], 1, 1, 'L');
        }
        
        if ($matiere['volume_horaire']) {
            $pdf->Cell(40, 6, 'Volume horaire :', 1, 0, 'L');
            $pdf->Cell(0, 6, $matiere['volume_horaire'] . ' h/semaine', 1, 1, 'L');
        }
        
        $pdf->Cell(40, 6, 'Créée le :', 1, 0, 'L');
        $pdf->Cell(0, 6, date('d/m/Y à H:i', strtotime($matiere['created_at'])), 1, 1, 'L');
        
        if ($matiere['description']) {
            $pdf->Ln(5);
            $pdf->SetFont('helvetica', 'B', 12);
            $pdf->Cell(0, 8, 'Description', 0, 1, 'L');
            $pdf->SetFont('helvetica', '', 10);
            $pdf->MultiCell(0, 5, $matiere['description'], 1, 'L');
        }
        
        if ($matiere['objectifs']) {
            $pdf->Ln(5);
            $pdf->SetFont('helvetica', 'B', 12);
            $pdf->Cell(0, 8, 'Objectifs pédagogiques', 0, 1, 'L');
            $pdf->SetFont('helvetica', '', 10);
            $pdf->MultiCell(0, 5, $matiere['objectifs'], 1, 'L');
        }
        
    } else {
        // Liste de toutes les matières
        $pdf->SetFont('helvetica', 'B', 9);
        
        // En-têtes du tableau
        $pdf->Cell(50, 8, 'Matière', 1, 0, 'C');
        $pdf->Cell(25, 8, 'Niveau', 1, 0, 'C');
        $pdf->Cell(25, 8, 'Type', 1, 0, 'C');
        $pdf->Cell(15, 8, 'Coeff.', 1, 0, 'C');
        $pdf->Cell(20, 8, 'Vol. h.', 1, 0, 'C');
        $pdf->Cell(20, 8, 'Classes', 1, 0, 'C');
        $pdf->Cell(25, 8, 'Créée le', 1, 1, 'C');
        
        // Données
        $pdf->SetFont('helvetica', '', 8);
        foreach ($matieres as $matiere) {
            $pdf->Cell(50, 6, substr($matiere['nom'], 0, 25), 1, 0, 'L');
            $pdf->Cell(25, 6, ucfirst($matiere['niveau']), 1, 0, 'C');
            $pdf->Cell(25, 6, ucfirst($matiere['type']), 1, 0, 'C');
            $pdf->Cell(15, 6, $matiere['coefficient'] ?: '-', 1, 0, 'C');
            $pdf->Cell(20, 6, $matiere['volume_horaire'] ? $matiere['volume_horaire'] . 'h' : '-', 1, 0, 'C');
            $pdf->Cell(20, 6, $matiere['nb_classes'] ?? '0', 1, 0, 'C');
            $pdf->Cell(25, 6, date('d/m/Y', strtotime($matiere['created_at'])), 1, 1, 'C');
        }
    }
    
    // Pied de page
    $pdf->Ln(10);
    $pdf->SetFont('helvetica', 'I', 8);
    $pdf->Cell(0, 5, 'Document généré par Educ-Sinfinity - ' . date('d/m/Y à H:i'), 0, 1, 'C');
    
    // Sortie du PDF
    $filename = $filename_base . '.pdf';
    $pdf->Output($filename, 'D');
    exit;
    
} else {
    showMessage('error', 'Format d\'export non supporté.');
    redirectTo('index.php');
}
?>
