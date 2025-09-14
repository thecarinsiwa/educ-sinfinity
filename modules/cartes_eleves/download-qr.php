<?php
/**
 * Téléchargement du QR Code d'une carte d'élève
 * Application de gestion scolaire - République Démocratique du Congo
 */

require_once '../../config/config.php';
require_once '../../includes/functions.php';
require_once '../includes/permissions-pages.php';
requireLogin();

requirePagePermissionFromDB('cartes_eleves', 'cartes_eleves/download-qr', 'read', '../dashboard.php');

$carte_id = $_GET['id'] ?? 0;

if (!$carte_id) {
    die('Carte non trouvée');
}

// Récupérer les informations de la carte
$carte = $database->query(
    "SELECT ce.*, e.nom, e.prenom, e.numero_matricule
     FROM cartes_eleves ce
     LEFT JOIN eleves e ON ce.eleve_id = e.id
     WHERE ce.id = ?",
    [$carte_id]
)->fetch();

if (!$carte) {
    die('Carte non trouvée');
}

// Récupérer les paramètres de design
$parametres = $database->query("SELECT * FROM parametres_cartes LIMIT 1")->fetch();
$qr_size = $parametres['qr_code_size'] ?? 100;

// Créer une image QR code simple (en production, utiliser une vraie bibliothèque QR)
$image = imagecreate($qr_size, $qr_size);
$white = imagecolorallocate($image, 255, 255, 255);
$black = imagecolorallocate($image, 0, 0, 0);

// Remplir avec du blanc
imagefill($image, 0, 0, $white);

// Dessiner un QR code simple (simulation)
$qr_data = json_decode($carte['qr_data'], true);
$text = $carte['numero_matricule'];

// Dessiner un carré noir au centre
$center = $qr_size / 2;
$square_size = $qr_size * 0.6;
$x1 = $center - $square_size / 2;
$y1 = $center - $square_size / 2;
$x2 = $center + $square_size / 2;
$y2 = $center + $square_size / 2;

imagefilledrectangle($image, $x1, $y1, $x2, $y2, $black);

// Ajouter le texte
$font_size = 3;
$text_width = imagefontwidth($font_size) * strlen($text);
$text_height = imagefontheight($font_size);
$text_x = ($qr_size - $text_width) / 2;
$text_y = ($qr_size - $text_height) / 2;

imagestring($image, $font_size, $text_x, $text_y, $text, $white);

// En-têtes pour le téléchargement
header('Content-Type: image/png');
header('Content-Disposition: attachment; filename="qr_code_' . $carte['numero_matricule'] . '.png"');
header('Cache-Control: no-cache, must-revalidate');
header('Expires: Sat, 26 Jul 1997 05:00:00 GMT');

// Afficher l'image
imagepng($image);
imagedestroy($image);
?>

