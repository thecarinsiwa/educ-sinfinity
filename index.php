<?php
/**
 * Page d'accueil - Redirection vers le tableau de bord ou la connexion
 * Application de gestion scolaire - République Démocratique du Congo
 */

// Gestion des erreurs pour le développement
error_reporting(E_ALL);
ini_set('display_errors', 1);

try {
    // Inclure les fichiers de configuration
    require_once 'config/config.php';
    require_once 'config/database.php';
    require_once 'includes/functions.php';

    // Rediriger selon l'état de connexion
    if (isLoggedIn()) {
        redirectTo('dashboard.php');
    } else {
        redirectTo('auth/login.php');
    }

} catch (Exception $e) {
    // Afficher l'erreur pour le débogage
    echo "<!DOCTYPE html>
    <html lang='fr'>
    <head>
        <meta charset='UTF-8'>
        <meta name='viewport' content='width=device-width, initial-scale=1.0'>
        <title>Erreur - Educ-Sinfinity</title>
        <style>
            body { font-family: Arial, sans-serif; margin: 40px; background: #f5f5f5; }
            .error-container { background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
            .error-title { color: #d32f2f; margin-bottom: 20px; }
            .error-message { background: #ffebee; padding: 15px; border-left: 4px solid #d32f2f; margin: 20px 0; }
            .error-details { background: #f5f5f5; padding: 15px; border-radius: 4px; font-family: monospace; font-size: 14px; }
            .btn { display: inline-block; padding: 10px 20px; background: #1976d2; color: white; text-decoration: none; border-radius: 4px; margin-top: 20px; }
        </style>
    </head>
    <body>
        <div class='error-container'>
            <h1 class='error-title'>🚨 Erreur de Configuration</h1>
            <div class='error-message'>
                <strong>Message d'erreur :</strong><br>
                " . htmlspecialchars($e->getMessage()) . "
            </div>
            <div class='error-details'>
                <strong>Fichier :</strong> " . htmlspecialchars($e->getFile()) . "<br>
                <strong>Ligne :</strong> " . $e->getLine() . "<br>
                <strong>Trace :</strong><br>
                " . nl2br(htmlspecialchars($e->getTraceAsString())) . "
            </div>
            <h3>Solutions possibles :</h3>
            <ul>
                <li>Vérifiez que la base de données MySQL est démarrée</li>
                <li>Vérifiez les paramètres de connexion dans <code>config/database.php</code></li>
                <li>Assurez-vous que la base de données 'educ_sinfinity' existe</li>
                <li>Vérifiez les permissions des fichiers</li>
            </ul>
            <a href='setup.php' class='btn'>🔧 Configuration initiale</a>
        </div>
    </body>
    </html>";
}
?>
