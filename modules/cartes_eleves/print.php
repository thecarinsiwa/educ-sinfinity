<?php
/**
 * Impression d'une carte d'élève - Modèle RDC Officiel
 * Application de gestion scolaire - République Démocratique du Congo
 */

require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/config/database.php';
require_once dirname(__DIR__, 2) . '/includes/functions.php';
requireLogin();

$carte_id = $_GET['id'] ?? 0;

if (!$carte_id) {
    die('Carte non trouvée');
}

// Récupérer les informations de la carte
$sql = "SELECT ce.*, e.nom, e.prenom, e.numero_matricule, e.photo, e.date_naissance, e.sexe, e.lieu_naissance,
               c.nom as classe_nom, c.niveau,
               a.annee, a.date_debut, a.date_fin
        FROM carte_eleve ce
        LEFT JOIN eleves e ON ce.eleve_id = e.id
        LEFT JOIN classes c ON e.classe_id = c.id
        LEFT JOIN annees_scolaires a ON ce.annee_scolaire_id = a.id
        WHERE ce.id = ?";

$carte = $database->query($sql, [$carte_id])->fetch();

if (!$carte) {
    die('Carte non trouvée');
}

// Récupérer les paramètres de l'école
$parametres_ecole = $database->query("SELECT * FROM parametres_cartes LIMIT 1")->fetch();
if (!$parametres_ecole) {
    $parametres_ecole = [
        'nom_ecole' => 'École Sinfinity',
        'adresse_ecole' => 'Kinshasa, République Démocratique du Congo'
    ];
}

// Déterminer les dimensions (format carte d'identité)
$width_mm = 85.6;
$height_mm = 54;
$width_pt = $width_mm * 2.834645669; // Conversion mm vers points
$height_pt = $height_mm * 2.834645669;

// Créer le PDF
require_once dirname(__DIR__, 2) . '/vendor/tcpdf/tcpdf.php';

$pdf = new TCPDF('L', 'pt', array($width_pt, $height_pt), true, 'UTF-8', false);
$pdf->SetCreator('Système de Gestion Scolaire');
$pdf->SetAuthor('École Sinfinity');
$pdf->SetTitle('Carte d\'Identification de l\'Élève');
$pdf->SetSubject('Carte d\'élève RDC');

// Supprimer les en-têtes et pieds de page
$pdf->setPrintHeader(false);
$pdf->setPrintFooter(false);

// Définir les marges
$pdf->SetMargins(10, 10, 10);
$pdf->SetAutoPageBreak(false);

// Ajouter une page
$pdf->AddPage();

// Couleurs RDC
$bleu_rdc = array(30, 58, 138); // Bleu foncé RDC
$rouge_rdc = array(220, 38, 38); // Rouge RDC
$jaune_rdc = array(251, 191, 36); // Jaune RDC
$noir = array(0, 0, 0);
$blanc = array(255, 255, 255);

// Fond blanc
$pdf->SetFillColor($blanc[0], $blanc[1], $blanc[2]);
$pdf->Rect(0, 0, $width_pt, $height_pt, 'F');

// === EN-TÊTE OFFICIEL RDC ===

// Titre principal
$pdf->SetFont('helvetica', 'B', 7);
$pdf->SetTextColor($bleu_rdc[0], $bleu_rdc[1], $bleu_rdc[2]);
$pdf->SetXY(10, 8);
$pdf->Cell(0, 0, 'REPUBLIQUE DEMOCRATIQUE DU CONGO', 0, 0, 'C');

// Sous-titre - Nom de l'école
$pdf->SetFont('helvetica', 'B', 7);
$pdf->SetXY(10, 15);
$pdf->Cell(0, 0, strtoupper($parametres_ecole['nom_ecole']), 0, 0, 'C');

// Année scolaire
$pdf->SetFont('helvetica', 'B', 6);
$pdf->SetXY(10, 22);
$pdf->Cell(0, 0, 'ANNEE SCOLAIRE ' . $carte['annee'], 0, 0, 'C');

// Type de carte
$pdf->SetFont('helvetica', 'B', 6);
$pdf->SetXY(10, 30);
$pdf->Cell(0, 0, 'CARTE D\'IDENTIFICATION DE L\'ELEVE', 0, 0, 'C');

// === EMBLÈMES NATIONAUX ===

// Drapeau RDC (coin supérieur gauche) - Image PNG
$drapeau_path = '../../assets/images/rdc_drapeau.png';
if (file_exists($drapeau_path)) {
    $pdf->Image($drapeau_path, 10, 8, 18, 10, '', '', '', true, 300, '', false, false, 0, false, false, false);
} else {
    // Fallback si l'image n'existe pas - Drapeau dessiné manuellement
    $pdf->SetFillColor($bleu_rdc[0], $bleu_rdc[1], $bleu_rdc[2]);
    $pdf->Rect(10, 8, 15, 10, 'F');
    
    // Bande diagonale rouge
    $pdf->SetFillColor($rouge_rdc[0], $rouge_rdc[1], $rouge_rdc[2]);
    $pdf->Rect(10, 8, 15, 2, 'F');
    
    // Bande diagonale jaune
    $pdf->SetFillColor($jaune_rdc[0], $jaune_rdc[1], $jaune_rdc[2]);
    $pdf->Rect(10, 10, 15, 1, 'F');
    
    // Étoile jaune sur le drapeau
    $pdf->SetFillColor($jaune_rdc[0], $jaune_rdc[1], $jaune_rdc[2]);
    $pdf->Circle(17, 13, 3, 0, 360, 'F');
}

// Emblème circulaire (coin supérieur droit) - Version simplifiée
$pdf->SetFillColor($bleu_rdc[0], $bleu_rdc[1], $bleu_rdc[2]);
$pdf->Circle($width_pt - 20, 13, 8, 0, 360, 'F');

// Texte dans l'emblème
$pdf->SetFont('helvetica', 'B', 4);
$pdf->SetTextColor($blanc[0], $blanc[1], $blanc[2]);
$pdf->SetXY($width_pt - 25, 10);
$pdf->Cell(10, 0, 'RDC', 0, 0, 'C');

// === INFORMATIONS DE L'ÉLÈVE ===

$y_start = 40;
$line_height = 6;

// NOM
$pdf->SetFont('helvetica', 'B', 8);
$pdf->SetTextColor($noir[0], $noir[1], $noir[2]);
$pdf->SetXY(10, $y_start);
$pdf->Cell(0, 0, 'NOM:', 0, 0, 'L');
$pdf->SetXY(35, $y_start);
$pdf->Cell(0, 0, strtoupper($carte['nom']), 0, 0, 'L');
$y_start += 2; 

// POST-NOM
$y_start += $line_height;
$pdf->SetXY(10, $y_start);
$pdf->Cell(0, 0, 'POST-NOM:', 0, 0, 'L');
$pdf->SetXY(60, $y_start);
$pdf->Cell(0, 0, strtoupper($carte['prenom']), 0, 0, 'L');
$y_start += 2; 


// SEXE
$y_start += $line_height;
$pdf->SetXY(10, $y_start);
$pdf->Cell(0, 0, 'SEX:', 0, 5, 'L');
$pdf->SetXY(35, $y_start);
$pdf->Cell(0, 0, strtoupper($carte['sexe']), 0, 0, 'L');
$y_start += 2; 


// LIEU & DATE DE N.
$y_start += $line_height;
$pdf->SetXY(10, $y_start);
$pdf->Cell(5, 0, 'LIEU & DATE DE NAIS. :', 0, 0, 'L');
$pdf->SetXY(105, $y_start);
$pdf->SetFont('helvetica', 'I', 7);
$lieu_naissance = $carte['lieu_naissance'] ?? 'KINSHASA';
$date_naissance = $carte['date_naissance'] ? date('d/m/Y', strtotime($carte['date_naissance'])) : '01/01/2000';
$pdf->Cell(0, 0, strtoupper($lieu_naissance . ', ' . $date_naissance), 0, 0, 'L');
$y_start += 2; 

$pdf->SetFont('helvetica', 'B', 8);

// CLASSE
$y_start += $line_height;
$pdf->SetXY(10, $y_start);
$pdf->Cell(0, 0, 'CLASSE:', 0, 0, 'L');
$pdf->SetXY(50, $y_start);
$pdf->Cell(0, 0, $carte['classe_nom'] ?? 'N/A', 0, 0, 'L');
$y_start += 2; 

// OPTION
$y_start += $line_height;
$pdf->SetXY(10, $y_start);
$pdf->Cell(0, 0, 'OPTION:', 0, 0, 'L');
$pdf->SetXY(50, $y_start);
$option = 'GENERALE'; // Valeur par défaut car la colonne option n'existe pas dans la table classes
$pdf->Cell(0, 0, strtoupper($option), 0, 0, 'L');
$y_start += 2; 



// NUMERO PERMANENT
$y_start += $line_height;
$pdf->SetXY(10, $y_start);
$pdf->Cell(0, 0, 'NUMERO MATRICULE:', 0, 0, 'L');
$pdf->SetXY(100, $y_start);
$pdf->Cell(0, 0, $carte['numero_matricule'], 0, 0, 'L');
$y_start += 2; 

// === PHOTO ===

// Zone photo (coin droit)
$photo_x = $width_pt - 45;
$photo_y = 40;
$photo_width = 35;
$photo_height = 45;

// Cadre photo
$pdf->SetDrawColor($noir[0], $noir[1], $noir[2]);
$pdf->SetLineWidth(0.5);
$pdf->Rect($photo_x, $photo_y, $photo_width, $photo_height);

// Texte "PHOTO"
$pdf->SetFont('helvetica', 'B', 8);
$pdf->SetTextColor($noir[0], $noir[1], $noir[2]);
$pdf->SetXY($photo_x, $photo_y + $photo_height/2 - 2);
$pdf->Cell($photo_width, 0, 'PHOTO', 0, 0, 'C');

// Insérer la photo si elle existe
if ($carte['photo'] && file_exists('../../uploads/photos/' . $carte['photo'])) {
    $photo_path = '../../uploads/photos/' . $carte['photo'];
    $pdf->Image($photo_path, $photo_x + 2, $photo_y + 2, $photo_width - 4, $photo_height - 4, '', '', '', true, 300, '', false, false, 0, false, false, false);
}

// === SIGNATURE ET QR CODE ===

// Signature (coin inférieur gauche)
$pdf->SetFont('helvetica', 'B', 7);
$pdf->SetTextColor($noir[0], $noir[1], $noir[2]);
$pdf->SetXY(10, $height_pt - 20);
$pdf->Cell(0, 0, 'LE SECRETAIRE GENERAL', 0, 0, 'L');

$pdf->SetXY(10, $height_pt - 15);
$pdf->Cell(0, 0, 'LUFUNISABO BUNDOKI', 0, 0, 'L');

// Numéro de signature
$pdf->SetXY(60, $height_pt - 15);
$pdf->Cell(0, 0, '236731', 0, 0, 'L');

// QR Code (coin inférieur droit)
if ($carte['qr_code_path'] && file_exists('../../' . $carte['qr_code_path'])) {
    $qr_size = 25;
    $qr_x = $width_pt - $qr_size - 10;
    $qr_y = $height_pt - $qr_size - 10;
    
    $pdf->Image('../../' . $carte['qr_code_path'], $qr_x, $qr_y, $qr_size, $qr_size, '', '', '', true, 300, '', false, false, 0, false, false, false);
    
    // Numéro sous le QR code
    $pdf->SetFont('helvetica', 'B', 8);
    $pdf->SetXY($qr_x, $qr_y + $qr_size + 2);
    $pdf->Cell($qr_size, 0, '236731', 0, 0, 'C');
}

// === BORDURE DE LA CARTE ===

// Bordure noire fine
$pdf->SetDrawColor($noir[0], $noir[1], $noir[2]);
$pdf->SetLineWidth(0.5);
$pdf->Rect(5, 5, $width_pt - 10, $height_pt - 10);

// Sortie du PDF
$pdf->Output('carte_eleve_rdc_' . $carte['numero_matricule'] . '.pdf', 'I');
?>
