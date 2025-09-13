<?php
/**
 * Vérification de session robuste pour les pages du dossier racine
 * Ce fichier doit être inclus après config.php dans chaque page protégée
 */

// La session est déjà démarrée par config.php, pas besoin de la redémarrer

// Fonction pour vérifier si l'utilisateur est connecté
function isUserLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

// Fonction de redirection robuste
function safeRedirect($url) {
    // Nettoyer le buffer de sortie
    if (ob_get_level()) {
        ob_end_clean();
    }
    
    // Si les headers n'ont pas encore été envoyés
    if (!headers_sent()) {
        header("Location: " . $url);
        exit;
    } else {
        // Utiliser JavaScript pour la redirection
        echo "<!DOCTYPE html><html><head><title>Redirection...</title></head><body>";
        echo "<script>window.location.href = '" . htmlspecialchars($url, ENT_QUOTES) . "';</script>";
        echo "<noscript><meta http-equiv='refresh' content='0;url=" . htmlspecialchars($url, ENT_QUOTES) . "'></noscript>";
        echo "<p>Redirection en cours... <a href='" . htmlspecialchars($url, ENT_QUOTES) . "'>Cliquez ici si la redirection ne fonctionne pas</a></p>";
        echo "</body></html>";
        exit;
    }
}

// Vérifier la session et rediriger si nécessaire
if (!isUserLoggedIn()) {
    safeRedirect('auth/login.php');
}
?>
