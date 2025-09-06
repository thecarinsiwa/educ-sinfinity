<?php
/**
 * Impression de toutes les cartes d'élèves
 * Application de gestion scolaire - République Démocratique du Congo
 */

require_once '../../config/config.php';
require_once '../../includes/functions.php';
requireLogin();

// Vérifier les permissions
if (!hasPermission('cartes_eleves', 'print')) {
    die('Permissions insuffisantes');
}

// Récupérer l'année scolaire courante
$current_year = $database->query("SELECT * FROM annees_scolaires WHERE status = 'active'")->fetch();

// Récupérer toutes les cartes actives
$sql = "SELECT ce.*, e.nom, e.prenom, e.numero_matricule, e.photo,
               c.nom as classe_nom, c.niveau
        FROM cartes_eleves ce
        LEFT JOIN eleves e ON ce.eleve_id = e.id
        LEFT JOIN classes c ON e.classe_id = c.id
        WHERE ce.annee_scolaire_id = ? AND ce.statut = 'active'
        ORDER BY c.niveau, c.nom, e.nom, e.prenom";

$cartes = $database->query($sql, [$current_year['id']])->fetchAll();

if (empty($cartes)) {
    die('Aucune carte à imprimer');
}

// Récupérer les paramètres de design
$parametres = $database->query("SELECT * FROM parametres_cartes LIMIT 1")->fetch();
if (!$parametres) {
    $parametres = [
        'nom_ecole' => 'École Sinfinity',
        'couleur_principale' => '#1e40af',
        'couleur_secondaire' => '#3b82f6',
        'couleur_texte' => '#1f2937',
        'format_carte' => 'pdf',
        'dimensions' => '85.6x54mm',
        'qr_code_size' => 100,
        'include_photo' => 1,
        'include_qr_code' => 1,
        'include_barcode' => 0
    ];
}

// Déterminer les dimensions
$dimensions = explode('x', $parametres['dimensions']);
$width_mm = floatval($dimensions[0]);
$height_mm = floatval($dimensions[1]);

// Convertir en points (1 mm = 2.834645669 points)
$width_pt = $width_mm * 2.834645669;
$height_pt = $height_mm * 2.834645669;

// Inclure TCPDF
require_once '../../vendor/tcpdf/tcpdf.php';

// Créer le PDF
$pdf = new TCPDF('L', 'pt', array($width_pt, $height_pt), true, 'UTF-8', false);

// Supprimer les en-têtes et pieds de page
$pdf->setPrintHeader(false);
$pdf->setPrintFooter(false);

// Définir les marges
$pdf->SetMargins(0, 0, 0);
$pdf->SetAutoPageBreak(false, 0);

// Couleurs
$primary_color = hex2rgb($parametres['couleur_principale']);
$secondary_color = hex2rgb($parametres['couleur_secondaire']);
$text_color = hex2rgb($parametres['couleur_texte']);

// Générer les cartes
foreach ($cartes as $carte) {
    // Ajouter une page
    $pdf->AddPage();
    
    // Créer un dégradé de fond
    $pdf->Rect(0, 0, $width_pt, $height_pt, 'F', array(), $primary_color);
    
    // Ajouter un rectangle avec dégradé (simulation)
    $pdf->SetFillColor($secondary_color[0], $secondary_color[1], $secondary_color[2]);
    $pdf->Rect(0, 0, $width_pt, $height_pt * 0.3, 'F');
    
    // Nom de l'école
    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->SetTextColor(255, 255, 255);
    $pdf->SetXY(10, 10);
    $pdf->Cell(0, 0, $parametres['nom_ecole'], 0, 0, 'L');
    
    // Type de carte
    $pdf->SetFont('helvetica', '', 8);
    $pdf->SetXY($width_pt - 60, 10);
    $pdf->Cell(0, 0, 'Carte d\'Élève', 0, 0, 'R');
    
    // Photo de l'élève
    if ($carte['photo'] && $parametres['include_photo']) {
        $photo_path = '../../uploads/photos/' . $carte['photo'];
        if (file_exists($photo_path)) {
            $pdf->Image($photo_path, 10, 25, 30, 30, '', '', '', true, 300, '', false, false, 0, false, false, false);
        }
    }
    
    // Informations de l'élève
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->SetTextColor($text_color[0], $text_color[1], $text_color[2]);
    $pdf->SetXY(50, 25);
    $pdf->Cell(0, 0, $carte['nom'] . ' ' . $carte['prenom'], 0, 0, 'L');
    
    $pdf->SetFont('helvetica', '', 9);
    $pdf->SetXY(50, 35);
    $pdf->Cell(0, 0, 'Classe: ' . $carte['classe_nom'], 0, 0, 'L');
    
    $pdf->SetXY(50, 42);
    $pdf->Cell(0, 0, 'Année: ' . $current_year['annee'], 0, 0, 'L');
    
    // Matricule
    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->SetXY(10, $height_pt - 25);
    $pdf->Cell(0, 0, 'Matricule: ' . $carte['numero_matricule'], 0, 0, 'L');
    
    // Numéro de carte
    $pdf->SetFont('helvetica', 'B', 8);
    $pdf->SetXY($width_pt - 80, $height_pt - 25);
    $pdf->Cell(0, 0, 'N°: ' . $carte['numero_carte'], 0, 0, 'R');
    
    // QR Code
    if ($parametres['include_qr_code']) {
        // Créer un QR code simple (en production, utiliser une vraie bibliothèque QR)
        $pdf->SetFont('helvetica', '', 6);
        $pdf->SetXY($width_pt - 40, $height_pt - 40);
        $pdf->Cell(35, 35, 'QR CODE', 1, 0, 'C');
        
        // Ajouter les données du QR code en petit
        $pdf->SetXY($width_pt - 40, $height_pt - 35);
        $pdf->Cell(35, 0, substr($carte['numero_matricule'], 0, 8), 0, 0, 'C');
    }
    
    // Ajouter un cadre
    $pdf->SetDrawColor(0, 0, 0);
    $pdf->Rect(2, 2, $width_pt - 4, $height_pt - 4);
}

// Sortie du PDF
$pdf->Output('cartes_eleves_' . $current_year['annee'] . '.pdf', 'I');

/**
 * Convertir une couleur hexadécimale en RGB
 */
function hex2rgb($hex) {
    $hex = str_replace('#', '', $hex);
    $r = hexdec(substr($hex, 0, 2));
    $g = hexdec(substr($hex, 2, 2));
    $b = hexdec(substr($hex, 4, 2));
    return array($r, $g, $b);
}
?>
