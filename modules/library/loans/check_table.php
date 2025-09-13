<?php
/**
 * Vérification et création de la table emprunts_livres
 */

require_once '../../../config/config.php';
require_once '../../../config/database.php';
require_once '../../../includes/functions.php';
require_once '../../../includes/permissions-pages.php';

// Vérifier l'authentification et les permissions
requireLogin();
requirePagePermissionFromDB('library', 'loans', 'read', '../../../dashboard.php');

echo "<h2>Vérification de la table emprunts_livres</h2>";

try {
    // Vérifier si la table existe
    $table_exists = $database->query("SHOW TABLES LIKE 'emprunts_livres'")->fetch();
    
    if (!$table_exists) {
        echo "<p style='color: red;'>❌ La table emprunts_livres n'existe pas.</p>";
        
        // Créer la table
        echo "<p>🔄 Création de la table...</p>";
        
        $database->execute("
            CREATE TABLE `emprunts_livres` (
                `id` int NOT NULL AUTO_INCREMENT,
                `livre_id` int NOT NULL,
                `emprunteur_type` enum('eleve','personnel') NOT NULL,
                `emprunteur_id` int NOT NULL,
                `date_emprunt` date NOT NULL,
                `date_retour_prevue` date NOT NULL,
                `date_retour_effective` date NULL,
                `duree_jours` int NOT NULL DEFAULT 14,
                `status` enum('en_cours','rendu','perdu','en_retard') NOT NULL DEFAULT 'en_cours',
                `notes_emprunt` text,
                `notes_retour` text,
                `traite_par` int NULL,
                `rendu_par` int NULL,
                `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                KEY `idx_livre_id` (`livre_id`),
                KEY `idx_emprunteur` (`emprunteur_type`, `emprunteur_id`),
                KEY `idx_status` (`status`),
                KEY `idx_date_retour` (`date_retour_prevue`),
                KEY `idx_traite_par` (`traite_par`),
                KEY `idx_rendu_par` (`rendu_par`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        
        echo "<p style='color: green;'>✅ Table emprunts_livres créée avec succès !</p>";
        
    } else {
        echo "<p style='color: green;'>✅ La table emprunts_livres existe.</p>";
        
        // Vérifier la structure de la colonne status
        $columns = $database->query("SHOW COLUMNS FROM emprunts_livres LIKE 'status'")->fetch();
        echo "<p>📋 Structure de la colonne status : " . $columns['Type'] . "</p>";
    }
    
    // Vérifier si la table parametres_bibliotheque existe
    $param_table_exists = $database->query("SHOW TABLES LIKE 'parametres_bibliotheque'")->fetch();
    
    if (!$param_table_exists) {
        echo "<p>🔄 Création de la table parametres_bibliotheque...</p>";
        
        $database->execute("
            CREATE TABLE `parametres_bibliotheque` (
                `id` int NOT NULL AUTO_INCREMENT,
                `cle` varchar(100) NOT NULL,
                `valeur` text NOT NULL,
                `description` text,
                `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uk_cle` (`cle`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        
        // Insérer les paramètres par défaut
        $parametres = [
            ['duree_emprunt_eleve', '14', 'Durée d\'emprunt par défaut pour les élèves'],
            ['duree_emprunt_personnel', '21', 'Durée d\'emprunt par défaut pour le personnel']
        ];
        
        foreach ($parametres as $param) {
            $database->execute(
                "INSERT INTO parametres_bibliotheque (cle, valeur, description) VALUES (?, ?, ?)",
                $param
            );
        }
        
        echo "<p style='color: green;'>✅ Table parametres_bibliotheque créée avec succès !</p>";
    } else {
        echo "<p style='color: green;'>✅ La table parametres_bibliotheque existe.</p>";
    }
    
    echo "<hr>";
    echo "<p><a href='add.php' style='background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>🎯 Tester la page d'ajout d'emprunt</a></p>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Erreur : " . $e->getMessage() . "</p>";
}
?>

