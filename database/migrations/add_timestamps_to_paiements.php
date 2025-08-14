<?php
/**
 * Migration: Ajouter les colonnes created_at et updated_at à la table paiements
 * Application de gestion scolaire - République Démocratique du Congo
 */

require_once '../../config/config.php';
require_once '../../config/database.php';

try {
    echo "Début de la migration: Ajout des colonnes timestamp à la table paiements...\n";
    
    // Vérifier si les colonnes existent déjà
    $check_columns = "SHOW COLUMNS FROM paiements LIKE 'created_at'";
    $result = $database->query($check_columns);
    
    if ($result->rowCount() == 0) {
        // Ajouter les colonnes created_at et updated_at
        $add_timestamps = "
            ALTER TABLE paiements 
            ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            ADD COLUMN updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP
        ";
        
        $database->execute($add_timestamps);
        echo "✓ Colonnes created_at et updated_at ajoutées à la table paiements\n";
        
        // Mettre à jour les enregistrements existants avec la date de paiement comme created_at
        $update_existing = "
            UPDATE paiements 
            SET created_at = date_paiement 
            WHERE created_at IS NULL
        ";
        
        $database->execute($update_existing);
        echo "✓ Mise à jour des enregistrements existants terminée\n";
        
    } else {
        echo "ℹ Les colonnes timestamp existent déjà dans la table paiements\n";
    }
    
    echo "Migration terminée avec succès!\n";
    
} catch (Exception $e) {
    echo "❌ Erreur lors de la migration: " . $e->getMessage() . "\n";
    exit(1);
}
?>
