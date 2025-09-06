<?php
/**
 * Script de régénération des QR codes PNG
 * Gère les doublons et la régénération propre
 */

require_once dirname(__DIR__, 2) . '/config/database.php';
require_once __DIR__ . '/qr-generator.php';

echo "=== Régénération des QR codes PNG ===\n\n";

try {
    $qrGenerator = new QRCodeGenerator($database);
    
    // Récupérer toutes les cartes sans QR code PNG
    $cartes = $database->query("
        SELECT ce.*, e.numero_matricule 
        FROM carte_eleve ce
        LEFT JOIN eleves e ON ce.eleve_id = e.id
        WHERE ce.qr_code_path IS NULL OR ce.qr_code_path = ''
        ORDER BY ce.id
    ")->fetchAll();
    
    echo "Cartes à traiter : " . count($cartes) . "\n\n";
    
    $success = 0;
    $errors = 0;
    
    foreach ($cartes as $carte) {
        echo "Traitement carte ID " . $carte['id'] . " (Élève: " . $carte['numero_matricule'] . ")... ";
        
        try {
            // Générer le QR code
            $result = $qrGenerator->generateQRCode(
                $carte['eleve_id'],
                $carte['numero_matricule'],
                $carte['annee_scolaire']
            );
            
            if ($result['success']) {
                // Mettre à jour la base de données
                $updateSql = "UPDATE carte_eleve SET qr_code_path = ? WHERE id = ?";
                $database->execute($updateSql, [
                    $qrGenerator->getRelativePath($result['filepath']),
                    $carte['id']
                ]);
                
                echo "✅ Succès\n";
                $success++;
            } else {
                echo "❌ Erreur: " . $result['error'] . "\n";
                $errors++;
            }
            
        } catch (Exception $e) {
            echo "❌ Exception: " . $e->getMessage() . "\n";
            $errors++;
        }
    }
    
    echo "\n=== Résumé ===\n";
    echo "Succès : " . $success . "\n";
    echo "Erreurs : " . $errors . "\n";
    
} catch (Exception $e) {
    echo "❌ Erreur générale : " . $e->getMessage() . "\n";
}

echo "\n=== Régénération terminée ===\n";
?>
