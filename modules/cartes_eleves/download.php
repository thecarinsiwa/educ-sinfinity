<?php
/**
 * Page de téléchargement des cartes d'élèves
 * Application de gestion scolaire - République Démocratique du Congo
 */

require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/config/database.php';
require_once dirname(__DIR__, 2) . '/includes/functions.php';
require_once dirname(__DIR__, 2) . '/includes/permissions-pages.php';

// Vérifier l'authentification
if (!isLoggedIn()) {
    redirectTo('auth/login.php');
}

requirePagePermissionFromDB('cartes_eleves', 'download', 'read', '../dashboard.php');

// Récupérer l'ID de la carte
$carte_id = intval($_GET['id'] ?? 0);

if (!$carte_id) {
    redirectTo('index.php?error=invalid_id');
}

try {
    // Récupérer les informations de la carte
    $carte = $database->query("
        SELECT ce.*, e.nom, e.prenom, e.numero_matricule, e.photo, 
               c.nom as classe_nom, c.niveau,
               a.annee, a.date_debut, a.date_fin
        FROM carte_eleve ce
        LEFT JOIN eleves e ON ce.eleve_id = e.id
        LEFT JOIN classes c ON e.classe_id = c.id
        LEFT JOIN annees_scolaires a ON ce.annee_scolaire_id = a.id
        WHERE ce.id = ?
    ", [$carte_id])->fetch();
    
    if (!$carte) {
        redirectTo('index.php?error=card_not_found');
    }
    
    // Paramètres par défaut de la carte
    $parametres = [
        'nom_ecole' => 'École de l\'Excellence',
        'adresse_ecole' => 'Kinshasa, RDC',
        'telephone_ecole' => '+243 123 456 789',
        'email_ecole' => 'contact@ecole-excellence.cd',
        'logo_ecole' => '',
        'couleur_principale' => '#2c3e50',
        'couleur_secondaire' => '#3498db',
        'couleur_texte' => '#2c3e50',
        'dimensions' => '85.6x54',
        'include_qr_code' => 1,
        'include_photo' => 1,
        'include_signature' => 1
    ];
    
    // Déterminer les dimensions
    $dimensions = explode('x', $parametres['dimensions']);
    $width_mm = floatval($dimensions[0]);
    $height_mm = floatval($dimensions[1]);
    
    // Convertir en points (1 mm = 2.834645669 points)
    $width_pt = $width_mm * 2.834645669;
    $height_pt = $height_mm * 2.834645669;
    
    // Inclure TCPDF
    require_once dirname(__DIR__, 2) . '/vendor/tcpdf/tcpdf.php';
    
    // Créer le PDF
    $pdf = new TCPDF('L', 'pt', array($width_pt, $height_pt), true, 'UTF-8', false);
    
    // Supprimer les en-têtes et pieds de page
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);
    
    // Définir les marges
    $pdf->SetMargins(0, 0, 0);
    $pdf->SetAutoPageBreak(false, 0);
    
    // Ajouter une page
    $pdf->AddPage();
    
    // Couleurs
    $primary_color = hex2rgb($parametres['couleur_principale']);
    $secondary_color = hex2rgb($parametres['couleur_secondaire']);
    $text_color = hex2rgb($parametres['couleur_texte']);
    
    // Fond de la carte
    $pdf->SetFillColor(255, 255, 255);
    $pdf->Rect(0, 0, $width_pt, $height_pt, 'F');
    
    // Bande colorée en haut
    $pdf->SetFillColor($primary_color[0], $primary_color[1], $primary_color[2]);
    $pdf->Rect(0, 0, $width_pt, 20, 'F');
    
    // Nom de l'école
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->SetTextColor(255, 255, 255);
    $pdf->SetXY(10, 5);
    $pdf->Cell(0, 0, $parametres['nom_ecole'], 0, 0, 'L');
    
    // Année scolaire
    $pdf->SetFont('helvetica', '', 8);
    $pdf->SetXY($width_pt - 60, 5);
    $pdf->Cell(0, 0, $carte['annee'], 0, 0, 'R');
    
    // Photo de l'élève
    if ($parametres['include_photo'] && !empty($carte['photo'])) {
        $photo_path = dirname(__DIR__, 2) . '/uploads/photos/' . $carte['photo'];
        if (file_exists($photo_path)) {
            $pdf->Image($photo_path, 10, 30, 40, 50, 'JPG', '', '', true, 300, '', false, false, 0, false, false, false);
        }
    }
    
    // Informations de l'élève
    $pdf->SetTextColor($text_color[0], $text_color[1], $text_color[2]);
    $pdf->SetFont('helvetica', 'B', 14);
    $pdf->SetXY(60, 35);
    $pdf->Cell(0, 0, strtoupper($carte['nom'] . ' ' . $carte['prenom']), 0, 0, 'L');
    
    $pdf->SetFont('helvetica', '', 10);
    $pdf->SetXY(60, 50);
    $pdf->Cell(0, 0, 'Classe: ' . $carte['classe_nom'], 0, 0, 'L');
    
    $pdf->SetXY(60, 60);
    $pdf->Cell(0, 0, 'Matricule: ' . $carte['numero_matricule'], 0, 0, 'L');
    
    // Numéro de carte
    $pdf->SetFont('helvetica', 'B', 8);
    $pdf->SetXY($width_pt - 80, $height_pt - 25);
    $pdf->Cell(0, 0, 'N°: ' . $carte['numero_carte'], 0, 0, 'R');
    
    // QR Code
    if ($parametres['include_qr_code']) {
        // Position et taille du QR code
        $qr_size = 35; // Taille en points
        $qr_x = $width_pt - $qr_size - 10; // Position X avec marge
        $qr_y = $height_pt - $qr_size - 10; // Position Y avec marge
        
        // S'assurer que le QR code reste dans les limites de la carte
        if ($qr_x < 0) $qr_x = 10;
        if ($qr_y < 0) $qr_y = 10;
        
        // Dessiner un rectangle de fond pour le QR code
        $pdf->SetFillColor(255, 255, 255); // Blanc
        $pdf->Rect($qr_x - 3, $qr_y - 3, $qr_size + 6, $qr_size + 6, 'F');
        
        // Dessiner un rectangle de bordure épaisse
        $pdf->SetDrawColor(0, 0, 0); // Noir
        $pdf->SetLineWidth(1);
        $pdf->Rect($qr_x - 3, $qr_y - 3, $qr_size + 6, $qr_size + 6);
        
        // Vérifier si un fichier QR code PNG existe
        if (!empty($carte['qr_code_path'])) {
            // Construire le chemin absolu
            $qr_absolute_path = dirname(__DIR__, 2) . '/' . $carte['qr_code_path'];
            
            if (file_exists($qr_absolute_path)) {
                // Utiliser le fichier PNG généré
                try {
                    $pdf->Image($qr_absolute_path, $qr_x, $qr_y, $qr_size, $qr_size, 'PNG', '', '', true, 300, '', false, false, 0, false, false, false);
                } catch (Exception $e) {
                    // Fallback vers le QR code simulé si l'image échoue
                    drawSimulatedQRCode($pdf, $qr_x, $qr_y, $qr_size, $carte['numero_carte']);
                }
            } else {
                // Fichier PNG non trouvé, utiliser le QR code simulé
                drawSimulatedQRCode($pdf, $qr_x, $qr_y, $qr_size, $carte['numero_carte']);
            }
        } else {
            // Pas de chemin QR code, dessiner un QR code simulé
            drawSimulatedQRCode($pdf, $qr_x, $qr_y, $qr_size, $carte['numero_carte']);
        }
        
        // Ajouter un label sous le QR code
        $pdf->SetFont('helvetica', 'B', 7);
        $pdf->SetTextColor($text_color[0], $text_color[1], $text_color[2]);
        $pdf->SetXY($qr_x, $qr_y + $qr_size + 5);
        $pdf->Cell($qr_size, 0, 'QR CODE', 0, 0, 'C');
        
        // Ajouter les données du QR code en texte
        $pdf->SetFont('helvetica', '', 5);
        $pdf->SetXY($qr_x, $qr_y + $qr_size + 10);
        $pdf->Cell($qr_size, 0, substr($carte['numero_matricule'], 0, 10), 0, 0, 'C');
        
        // Ajouter un texte explicatif
        $pdf->SetFont('helvetica', '', 4);
        $pdf->SetXY($qr_x, $qr_y + $qr_size + 14);
        $pdf->Cell($qr_size, 0, 'Scan pour pointage', 0, 0, 'C');
    }
    
    // Ajouter un cadre
    $pdf->SetDrawColor(0, 0, 0);
    $pdf->Rect(2, 2, $width_pt - 4, $height_pt - 4);
    
    // Nom du fichier de téléchargement
    $filename = 'Carte_' . $carte['numero_matricule'] . '_' . date('Y') . '.pdf';
    
    // Envoyer le PDF au navigateur
    $pdf->Output($filename, 'D'); // 'D' pour téléchargement forcé
    
} catch (Exception $e) {
    // En cas d'erreur, rediriger vers la page d'index avec un message d'erreur
    redirectTo('index.php?error=download_failed&message=' . urlencode($e->getMessage()));
}

/**
 * Dessiner un QR code simulé en fallback
 */
function drawSimulatedQRCode($pdf, $qr_x, $qr_y, $qr_size, $numero_carte) {
    // Créer un QR code visible avec des rectangles
    $pdf->SetFillColor(0, 0, 0); // Noir
    $cell_size = 2.5; // Taille de chaque cellule
    
    // Dessiner un pattern QR code plus réaliste basé sur le numero_carte
    $numero = $numero_carte;
    $hash = crc32($numero); // Utiliser un hash pour créer un pattern reproductible
    
    for ($i = 0; $i < 12; $i++) {
        for ($j = 0; $j < 12; $j++) {
            // Pattern basé sur le hash du numero_carte pour être reproductible
            $pattern = ($hash + $i * 7 + $j * 11) % 3;
            
            if ($pattern == 0 || 
                ($i == 0 || $i == 11 || $j == 0 || $j == 11) || // Bordures
                (($i >= 2 && $i <= 4) && ($j >= 2 && $j <= 4)) || // Coin supérieur gauche
                (($i >= 8 && $i <= 10) && ($j >= 2 && $j <= 4)) || // Coin supérieur droit
                (($i >= 2 && $i <= 4) && ($j >= 8 && $j <= 10)) || // Coin inférieur gauche
                (($i >= 6 && $i <= 8) && ($j >= 6 && $j <= 8))) { // Centre
                $pdf->Rect($qr_x + ($i * $cell_size), $qr_y + ($j * $cell_size), $cell_size, $cell_size, 'F');
            }
        }
    }
    
    // Ajouter les coins caractéristiques des QR codes (plus grands et visibles)
    $pdf->Rect($qr_x, $qr_y, $cell_size * 5, $cell_size * 5, 'F');
    $pdf->Rect($qr_x + $cell_size * 7, $qr_y, $cell_size * 5, $cell_size * 5, 'F');
    $pdf->Rect($qr_x, $qr_y + $cell_size * 7, $cell_size * 5, $cell_size * 5, 'F');
    
    // Ajouter un petit carré au centre pour simuler le timing pattern
    $pdf->Rect($qr_x + $cell_size * 5, $qr_y + $cell_size * 5, $cell_size * 2, $cell_size * 2, 'F');
}

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
