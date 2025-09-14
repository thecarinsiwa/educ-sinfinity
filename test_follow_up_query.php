<?php
/**
 * Test de la requête de suivi des étudiants
 */

require_once 'config/database.php';

echo "=== Test de la requête de suivi des étudiants ===\n";

try {
    // Obtenir l'année scolaire actuelle
    $current_year = $database->query("SELECT * FROM annees_scolaires WHERE status = 'active' LIMIT 1")->fetch();
    
    if (!$current_year) {
        echo "Aucune année scolaire active trouvée\n";
        exit;
    }
    
    echo "Année scolaire active: " . $current_year['annee'] . "\n";
    
    // Test de la requête corrigée
    $eleves = $database->query(
        "SELECT e.*, c.nom as classe_nom, c.niveau,
                ss.trimestre, ss.moyenne_generale, ss.rang_classe, ss.effectif_classe,
                ss.appreciation, ss.decision_conseil, ss.date_conseil,
                (SELECT COUNT(*) FROM paiements p 
                 JOIN type_frais tf ON p.type_frais_id = tf.id 
                 WHERE p.eleve_id = e.id AND tf.nom = 'mensualite' AND p.status = 'en_attente') as paiements_en_retard,
                (SELECT COUNT(*) FROM sanctions s WHERE s.eleve_id = e.id AND s.status = 'active') as sanctions_actives
         FROM eleves e
         LEFT JOIN inscriptions i ON e.id = i.eleve_id
         LEFT JOIN classes c ON i.classe_id = c.id
         LEFT JOIN suivi_scolaire ss ON e.id = ss.eleve_id AND ss.annee_scolaire_id = ?
         WHERE e.status = 'actif'
         ORDER BY e.nom, e.prenom
         LIMIT 5",
        [$current_year['id'] ?? 0]
    )->fetchAll();
    
    echo "✓ Requête exécutée avec succès\n";
    echo "Nombre d'élèves trouvés: " . count($eleves) . "\n";
    
    foreach ($eleves as $eleve) {
        echo "- " . $eleve['nom'] . " " . $eleve['prenom'] . " (Classe: " . ($eleve['classe_nom'] ?? 'N/A') . ")\n";
        echo "  Paiements en retard: " . $eleve['paiements_en_retard'] . "\n";
        echo "  Sanctions actives: " . $eleve['sanctions_actives'] . "\n";
    }
    
} catch (Exception $e) {
    echo "❌ Erreur: " . $e->getMessage() . "\n";
}
?>
