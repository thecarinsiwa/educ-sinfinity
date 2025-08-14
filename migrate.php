<?php
/**
 * Script de migration simple pour corriger l'erreur Database::exec()
 * Application de gestion scolaire - République Démocratique du Congo
 */

// Activer l'affichage des erreurs
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<!DOCTYPE html>
<html lang='fr'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>Migration - Educ-Sinfinity</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .container { background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); max-width: 800px; margin: 0 auto; }
        .success { color: #28a745; background: #d4edda; padding: 10px; border-radius: 4px; margin: 10px 0; }
        .error { color: #dc3545; background: #f8d7da; padding: 10px; border-radius: 4px; margin: 10px 0; }
        .warning { color: #856404; background: #fff3cd; padding: 10px; border-radius: 4px; margin: 10px 0; }
        .btn { background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 4px; display: inline-block; margin: 10px 5px; }
        .btn:hover { background: #0056b3; color: white; }
    </style>
</head>
<body>
    <div class='container'>
        <h1>🔧 Migration Educ-Sinfinity</h1>";

try {
    echo "<h2>1. Test de connexion à la base de données</h2>";
    
    // Paramètres de connexion
    $host = 'localhost';
    $username = 'root';
    $password = '';
    $dbname = 'educ_sinfinity';
    
    // Créer la base de données si elle n'existe pas
    echo "<p>Connexion au serveur MySQL...</p>";
    $pdo = new PDO("mysql:host=$host", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "<div class='success'>✅ Connexion au serveur MySQL réussie</div>";
    
    echo "<p>Création de la base de données si nécessaire...</p>";
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `$dbname` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    echo "<div class='success'>✅ Base de données '$dbname' prête</div>";
    
    // Se connecter à la base de données
    echo "<p>Connexion à la base de données...</p>";
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "<div class='success'>✅ Connexion à la base de données réussie</div>";
    
    echo "<h2>2. Création des tables essentielles</h2>";
    
    // Table users
    echo "<p>Création de la table users...</p>";
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `users` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `username` varchar(50) NOT NULL,
          `password` varchar(255) NOT NULL,
          `nom` varchar(100) NOT NULL,
          `prenom` varchar(100) NOT NULL,
          `email` varchar(255) DEFAULT NULL,
          `telephone` varchar(20) DEFAULT NULL,
          `role` enum('admin','directeur','enseignant','secretaire','comptable','surveillant') NOT NULL,
          `status` enum('actif','inactif') DEFAULT 'actif',
          `photo` varchar(255) DEFAULT NULL,
          `adresse` text DEFAULT NULL,
          `date_naissance` date DEFAULT NULL,
          `genre` enum('M','F') DEFAULT NULL,
          `derniere_connexion` timestamp NULL DEFAULT NULL,
          `tentatives_connexion` int(11) DEFAULT 0,
          `compte_verrouille` boolean DEFAULT FALSE,
          `date_verrouillage` timestamp NULL DEFAULT NULL,
          `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
          `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
          PRIMARY KEY (`id`),
          UNIQUE KEY `username` (`username`),
          UNIQUE KEY `email` (`email`),
          KEY `idx_role` (`role`),
          KEY `idx_status` (`status`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "<div class='success'>✅ Table users créée</div>";
    
    // Table années scolaires
    echo "<p>Création de la table annees_scolaires...</p>";
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `annees_scolaires` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `nom` varchar(100) NOT NULL,
          `date_debut` date NOT NULL,
          `date_fin` date NOT NULL,
          `status` enum('active','inactive','terminee') DEFAULT 'inactive',
          `description` text DEFAULT NULL,
          `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
          `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
          PRIMARY KEY (`id`),
          KEY `idx_status` (`status`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "<div class='success'>✅ Table annees_scolaires créée</div>";
    
    // Table user_sessions
    echo "<p>Création de la table user_sessions...</p>";
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `user_sessions` (
          `id` varchar(128) NOT NULL,
          `user_id` int(11) NOT NULL,
          `ip_address` varchar(45) DEFAULT NULL,
          `user_agent` text DEFAULT NULL,
          `last_activity` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
          PRIMARY KEY (`id`),
          KEY `idx_user_id` (`user_id`),
          KEY `idx_last_activity` (`last_activity`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "<div class='success'>✅ Table user_sessions créée</div>";
    
    // Table user_actions_log
    echo "<p>Création de la table user_actions_log...</p>";
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `user_actions_log` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `user_id` int(11) NOT NULL,
          `action` varchar(100) NOT NULL,
          `module` varchar(50) NOT NULL,
          `details` text DEFAULT NULL,
          `target_id` int(11) DEFAULT NULL,
          `ip_address` varchar(45) DEFAULT NULL,
          `user_agent` text DEFAULT NULL,
          `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
          PRIMARY KEY (`id`),
          KEY `idx_user_id` (`user_id`),
          KEY `idx_action` (`action`),
          KEY `idx_module` (`module`),
          KEY `idx_created_at` (`created_at`),
          KEY `idx_target_id` (`target_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "<div class='success'>✅ Table user_actions_log créée</div>";
    
    echo "<h2>3. Insertion des données initiales</h2>";
    
    // Vérifier et créer l'année scolaire
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM annees_scolaires WHERE nom = ?");
    $stmt->execute(['2024-2025']);
    if ($stmt->fetchColumn() == 0) {
        $pdo->exec("
            INSERT INTO `annees_scolaires` (`nom`, `date_debut`, `date_fin`, `status`, `description`) VALUES
            ('2024-2025', '2024-09-01', '2025-07-31', 'active', 'Année scolaire 2024-2025')
        ");
        echo "<div class='success'>✅ Année scolaire 2024-2025 créée</div>";
    } else {
        echo "<div class='warning'>⚠️ Année scolaire 2024-2025 existe déjà</div>";
    }
    
    // Vérifier et créer l'utilisateur admin
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
    $stmt->execute(['admin']);
    if ($stmt->fetchColumn() == 0) {
        $pdo->exec("
            INSERT INTO `users` (`username`, `password`, `nom`, `prenom`, `email`, `role`, `status`) VALUES
            ('admin', 'd033e22ae348aeb5660fc2140aec35850c4da997', 'Administrateur', 'Système', 'admin@educ-sinfinity.cd', 'admin', 'actif')
        ");
        echo "<div class='success'>✅ Utilisateur admin créé</div>";
    } else {
        echo "<div class='warning'>⚠️ Utilisateur admin existe déjà</div>";
    }
    
    echo "<h2>4. Test de l'application</h2>";
    
    // Tester la classe Database
    echo "<p>Test de la classe Database...</p>";
    require_once 'config/database.php';
    
    if (class_exists('Database')) {
        $database = new Database();
        $test_query = $database->query("SELECT COUNT(*) as total FROM users");
        $result = $test_query->fetch();
        echo "<div class='success'>✅ Classe Database fonctionne - " . $result['total'] . " utilisateur(s) trouvé(s)</div>";
    } else {
        echo "<div class='error'>❌ Classe Database non trouvée</div>";
    }
    
    echo "<div style='background: #d4edda; padding: 20px; border-radius: 8px; margin: 20px 0;'>";
    echo "<h2>🎉 Migration terminée avec succès!</h2>";
    echo "<h3>Informations de connexion:</h3>";
    echo "<p><strong>Nom d'utilisateur:</strong> admin</p>";
    echo "<p><strong>Mot de passe:</strong> admin123</p>";
    echo "<p><strong>Base de données:</strong> $dbname</p>";
    echo "</div>";
    
    echo "<div style='text-align: center; margin: 30px 0;'>";
    echo "<a href='index.php' class='btn'>🏠 Accéder à l'application</a>";
    echo "<a href='diagnostic.php' class='btn'>🔍 Diagnostic complet</a>";
    echo "<a href='setup.php' class='btn'>⚙️ Configuration avancée</a>";
    echo "</div>";
    
} catch (Exception $e) {
    echo "<div class='error'>";
    echo "<h3>❌ Erreur de migration:</h3>";
    echo "<p><strong>Message:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p><strong>Fichier:</strong> " . htmlspecialchars($e->getFile()) . "</p>";
    echo "<p><strong>Ligne:</strong> " . $e->getLine() . "</p>";
    echo "</div>";
    
    echo "<div class='warning'>";
    echo "<h3>💡 Solutions possibles:</h3>";
    echo "<ul>";
    echo "<li>Vérifiez que MySQL est démarré dans Laragon</li>";
    echo "<li>Vérifiez les paramètres de connexion dans config/database.php</li>";
    echo "<li>Assurez-vous que l'utilisateur MySQL a les permissions CREATE DATABASE</li>";
    echo "<li>Redémarrez Laragon et réessayez</li>";
    echo "</ul>";
    echo "</div>";
}

echo "    </div>
</body>
</html>";
?>
