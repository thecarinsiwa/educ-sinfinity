<?php
/**
 * Test de la page des documents d'admission
 */

require_once 'config/database.php';

echo "=== Test de la page des documents d'admission ===\n";

try {
    // Obtenir l'année scolaire actuelle
    $current_year = $database->query("SELECT * FROM annees_scolaires WHERE status = 'active' LIMIT 1")->fetch();
    
    if (!$current_year) {
        echo "Aucune année scolaire active trouvée\n";
        exit;
    }
    
    echo "Année scolaire active: " . $current_year['annee'] . "\n";
    
    // Test de la requête des statistiques
    $stats = $database->query(
        "SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN certificat_naissance = 'verifie' THEN 1 ELSE 0 END) as cert_naissance_ok,
            SUM(CASE WHEN bulletin_precedent = 'verifie' THEN 1 ELSE 0 END) as bulletin_ok,
            SUM(CASE WHEN certificat_medical = 'verifie' THEN 1 ELSE 0 END) as cert_medical_ok,
            SUM(CASE WHEN photo_identite = 'verifie' THEN 1 ELSE 0 END) as photo_ok,
            SUM(CASE WHEN certificat_naissance = 'verifie' AND bulletin_precedent = 'verifie' 
                     AND certificat_medical = 'verifie' AND photo_identite = 'verifie' THEN 1 ELSE 0 END) as dossiers_complets
         FROM demandes_admission 
         WHERE annee_scolaire_id = ?",
        [$current_year['id'] ?? 0]
    )->fetch();
    
    echo "✓ Requête des statistiques exécutée avec succès\n";
    echo "Total dossiers: " . ($stats['total'] ?? 0) . "\n";
    echo "Certificats de naissance: " . ($stats['cert_naissance_ok'] ?? 0) . "\n";
    echo "Bulletins: " . ($stats['bulletin_ok'] ?? 0) . "\n";
    echo "Certificats médicaux: " . ($stats['cert_medical_ok'] ?? 0) . "\n";
    echo "Photos: " . ($stats['photo_ok'] ?? 0) . "\n";
    echo "Dossiers complets: " . ($stats['dossiers_complets'] ?? 0) . "\n";
    
    // Test de number_format avec les valeurs
    echo "\nTest de number_format:\n";
    echo "Total formaté: " . number_format($stats['total'] ?? 0) . "\n";
    echo "Cert. naissance formaté: " . number_format($stats['cert_naissance_ok'] ?? 0) . "\n";
    
} catch (Exception $e) {
    echo "❌ Erreur: " . $e->getMessage() . "\n";
}
?>
