<?php
/**
 * Diagnostic complet pour résoudre l'erreur 500
 */

// Activer l'affichage des erreurs
error_reporting(E_ALL);
ini_set('display_errors', 1);

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Diagnostic - Educ-Sinfinity</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .container { background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .success { color: #28a745; }
        .error { color: #dc3545; }
        .warning { color: #ffc107; }
        .section { margin: 20px 0; padding: 15px; border-left: 4px solid #007bff; background: #f8f9fa; }
        .code { background: #e9ecef; padding: 10px; border-radius: 4px; font-family: monospace; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔍 Diagnostic Educ-Sinfinity</h1>
        
        <div class="section">
            <h2>1. Informations PHP</h2>
            <p><strong>Version PHP:</strong> <?php echo PHP_VERSION; ?></p>
            <p><strong>Serveur:</strong> <?php echo $_SERVER['SERVER_SOFTWARE'] ?? 'Non disponible'; ?></p>
            <p><strong>Document Root:</strong> <?php echo $_SERVER['DOCUMENT_ROOT'] ?? 'Non disponible'; ?></p>
            <p><strong>Script actuel:</strong> <?php echo __FILE__; ?></p>
        </div>

        <div class="section">
            <h2>2. Extensions PHP requises</h2>
            <?php
            $required_extensions = ['pdo', 'pdo_mysql', 'mbstring', 'json', 'session'];
            foreach ($required_extensions as $ext) {
                if (extension_loaded($ext)) {
                    echo "<p class='success'>✅ $ext</p>";
                } else {
                    echo "<p class='error'>❌ $ext (MANQUANT)</p>";
                }
            }
            ?>
        </div>

        <div class="section">
            <h2>3. Fichiers de configuration</h2>
            <?php
            $files = [
                'config/config.php' => 'Configuration générale',
                'config/database.php' => 'Configuration base de données',
                'includes/functions.php' => 'Fonctions utilitaires',
                'index.php' => 'Page d\'accueil',
                '.htaccess' => 'Configuration Apache'
            ];
            
            foreach ($files as $file => $description) {
                if (file_exists($file)) {
                    echo "<p class='success'>✅ $file ($description)</p>";
                } else {
                    echo "<p class='error'>❌ $file ($description) - MANQUANT</p>";
                }
            }
            ?>
        </div>

        <div class="section">
            <h2>4. Test de chargement des fichiers</h2>
            <?php
            try {
                echo "<p>Tentative de chargement de config.php...</p>";
                require_once 'config/config.php';
                echo "<p class='success'>✅ config.php chargé avec succès</p>";
                
                echo "<p>Tentative de chargement de database.php...</p>";
                require_once 'config/database.php';
                echo "<p class='success'>✅ database.php chargé avec succès</p>";
                
                echo "<p>Tentative de chargement de functions.php...</p>";
                require_once 'includes/functions.php';
                echo "<p class='success'>✅ functions.php chargé avec succès</p>";
                
            } catch (Exception $e) {
                echo "<p class='error'>❌ Erreur lors du chargement: " . htmlspecialchars($e->getMessage()) . "</p>";
                echo "<p class='error'>Fichier: " . htmlspecialchars($e->getFile()) . "</p>";
                echo "<p class='error'>Ligne: " . $e->getLine() . "</p>";
                echo "<div class='code'>" . nl2br(htmlspecialchars($e->getTraceAsString())) . "</div>";
            }
            ?>
        </div>

        <div class="section">
            <h2>5. Test de connexion à la base de données</h2>
            <?php
            try {
                if (isset($database) && $database) {
                    echo "<p class='success'>✅ Objet database créé</p>";
                    
                    $stmt = $database->query("SELECT 1 as test");
                    if ($stmt) {
                        $result = $stmt->fetch();
                        echo "<p class='success'>✅ Connexion à la base de données réussie</p>";
                        echo "<p>Résultat du test: " . $result['test'] . "</p>";
                        
                        // Test des tables principales
                        $tables = ['users', 'annees_scolaires', 'classes'];
                        foreach ($tables as $table) {
                            try {
                                $stmt = $database->query("SHOW TABLES LIKE '$table'");
                                if ($stmt->rowCount() > 0) {
                                    echo "<p class='success'>✅ Table $table existe</p>";
                                } else {
                                    echo "<p class='warning'>⚠️ Table $table manquante</p>";
                                }
                            } catch (Exception $e) {
                                echo "<p class='error'>❌ Erreur table $table: " . htmlspecialchars($e->getMessage()) . "</p>";
                            }
                        }
                    }
                } else {
                    echo "<p class='error'>❌ Objet database non créé</p>";
                }
            } catch (Exception $e) {
                echo "<p class='error'>❌ Erreur de base de données: " . htmlspecialchars($e->getMessage()) . "</p>";
            }
            ?>
        </div>

        <div class="section">
            <h2>6. Permissions des dossiers</h2>
            <?php
            $dirs = ['uploads', 'assets', 'logs', 'config', 'database'];
            foreach ($dirs as $dir) {
                if (is_dir($dir)) {
                    if (is_readable($dir)) {
                        echo "<p class='success'>✅ $dir lisible</p>";
                    } else {
                        echo "<p class='error'>❌ $dir non lisible</p>";
                    }
                    if (is_writable($dir)) {
                        echo "<p class='success'>✅ $dir accessible en écriture</p>";
                    } else {
                        echo "<p class='warning'>⚠️ $dir non accessible en écriture</p>";
                    }
                } else {
                    echo "<p class='warning'>⚠️ Dossier $dir n'existe pas</p>";
                }
            }
            ?>
        </div>

        <div class="section">
            <h2>7. Test du fichier .htaccess</h2>
            <?php
            if (file_exists('.htaccess')) {
                echo "<p class='success'>✅ Fichier .htaccess existe</p>";
                $htaccess_content = file_get_contents('.htaccess');
                $lines = explode("\n", $htaccess_content);
                echo "<p>Nombre de lignes: " . count($lines) . "</p>";
                
                // Vérifier les directives problématiques
                $problematic = ['<Directory', 'php_flag', 'php_value'];
                $issues = [];
                foreach ($lines as $line_num => $line) {
                    foreach ($problematic as $directive) {
                        if (strpos($line, $directive) !== false) {
                            $issues[] = "Ligne " . ($line_num + 1) . ": " . trim($line);
                        }
                    }
                }
                
                if (empty($issues)) {
                    echo "<p class='success'>✅ Aucune directive problématique détectée</p>";
                } else {
                    echo "<p class='warning'>⚠️ Directives potentiellement problématiques:</p>";
                    foreach ($issues as $issue) {
                        echo "<p class='warning'>- " . htmlspecialchars($issue) . "</p>";
                    }
                }
            } else {
                echo "<p class='warning'>⚠️ Fichier .htaccess n'existe pas</p>";
            }
            ?>
        </div>

        <div class="section">
            <h2>8. Actions recommandées</h2>
            <div style="background: #fff3cd; padding: 15px; border-radius: 4px; border-left: 4px solid #ffc107;">
                <h3>Si vous voyez encore l'erreur 500:</h3>
                <ol>
                    <li><strong>Renommez temporairement .htaccess</strong> en .htaccess_backup</li>
                    <li><strong>Testez l'accès</strong> à index.php</li>
                    <li><strong>Vérifiez les logs d'erreur</strong> de votre serveur (Laragon/logs/apache_error.log)</li>
                    <li><strong>Créez la base de données</strong> si elle n'existe pas</li>
                    <li><strong>Exécutez le script</strong> database/schema.sql</li>
                </ol>
            </div>
        </div>

        <div class="section">
            <h2>9. Liens utiles</h2>
            <p>
                <a href="test.php" style="margin-right: 10px;">Test simple</a>
                <a href="setup.php" style="margin-right: 10px;">Configuration</a>
                <a href="index.php" style="margin-right: 10px;">Page d'accueil</a>
            </p>
        </div>
    </div>
</body>
</html>
