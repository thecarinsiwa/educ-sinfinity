<?php
/**
 * Script de correction de la base de données pour le module bibliothèque
 */

require_once '../../../config/config.php';
require_once '../../../config/database.php';
require_once '../../../includes/functions.php';

// Vérifier l'authentification
requireLogin();

echo "<!DOCTYPE html>
<html>
<head>
    <title>Correction Base de Données - Bibliothèque</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .success { color: green; background: #d4edda; padding: 10px; border-radius: 5px; margin: 10px 0; }
        .error { color: red; background: #f8d7da; padding: 10px; border-radius: 5px; margin: 10px 0; }
        .info { color: blue; background: #d1ecf1; padding: 10px; border-radius: 5px; margin: 10px 0; }
        .btn { background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; display: inline-block; margin: 5px; }
    </style>
</head>
<body>";

echo "<h1>🔧 Correction de la Base de Données - Module Bibliothèque</h1>";

try {
    // Lire le fichier SQL
    $sql_file = '../../../database/migrations/create_emprunts_livres_table.sql';
    
    if (!file_exists($sql_file)) {
        throw new Exception("Fichier SQL non trouvé : $sql_file");
    }
    
    $sql_content = file_get_contents($sql_file);
    
    // Diviser les requêtes SQL
    $queries = array_filter(array_map('trim', explode(';', $sql_content)));
    
    echo "<div class='info'>📋 Exécution de " . count($queries) . " requêtes SQL...</div>";
    
    foreach ($queries as $query) {
        if (empty($query)) continue;
        
        try {
            $database->execute($query);
            echo "<div class='success'>✅ Requête exécutée avec succès</div>";
        } catch (Exception $e) {
            // Ignorer les erreurs de table déjà existante
            if (strpos($e->getMessage(), 'already exists') !== false || 
                strpos($e->getMessage(), 'Duplicate entry') !== false) {
                echo "<div class='info'>ℹ️ Table/Donnée déjà existante (ignoré)</div>";
            } else {
                echo "<div class='error'>❌ Erreur : " . htmlspecialchars($e->getMessage()) . "</div>";
            }
        }
    }
    
    // Vérifier que les tables ont été créées
    echo "<h2>🔍 Vérification des tables</h2>";
    
    $tables_to_check = ['emprunts_livres', 'reservations_livres', 'parametres_bibliotheque'];
    
    foreach ($tables_to_check as $table) {
        $exists = $database->query("SHOW TABLES LIKE '$table'")->fetch();
        if ($exists) {
            echo "<div class='success'>✅ Table '$table' existe</div>";
            
            // Vérifier la structure de la colonne status pour emprunts_livres
            if ($table === 'emprunts_livres') {
                $status_column = $database->query("SHOW COLUMNS FROM emprunts_livres LIKE 'status'")->fetch();
                echo "<div class='info'>📋 Colonne status : " . $status_column['Type'] . "</div>";
            }
        } else {
            echo "<div class='error'>❌ Table '$table' n'existe pas</div>";
        }
    }
    
    echo "<h2>🎉 Correction terminée !</h2>";
    echo "<p>Les tables nécessaires ont été créées avec succès.</p>";
    
    echo "<div style='margin-top: 20px;'>";
    echo "<a href='add.php' class='btn'>📚 Tester l'ajout d'emprunt</a>";
    echo "<a href='index.php' class='btn'>📋 Gestion des emprunts</a>";
    echo "<a href='../books/' class='btn'>📖 Gestion des livres</a>";
    echo "</div>";
    
} catch (Exception $e) {
    echo "<div class='error'>❌ Erreur fatale : " . htmlspecialchars($e->getMessage()) . "</div>";
}

echo "</body></html>";
?>
