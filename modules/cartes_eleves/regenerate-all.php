<?php
/**
 * Régénération de toutes les cartes d'élèves
 * Application de gestion scolaire - République Démocratique du Congo
 */

require_once '../../config/config.php';
require_once '../../includes/functions.php';
requireLogin();

// Vérifier les permissions
if (!hasPermission('cartes_eleves', 'manage')) {
    echo json_encode(['success' => false, 'message' => 'Permissions insuffisantes']);
    exit;
}

try {
    $database->beginTransaction();
    
    // Récupérer l'année scolaire courante
    $current_year = $database->query("SELECT * FROM annees_scolaires WHERE status = 'active'")->fetch();
    
    // Récupérer tous les élèves actifs de l'année courante
    $students = $database->query(
        "SELECT e.id FROM eleves e 
         JOIN inscriptions i ON e.id = i.eleve_id 
         WHERE i.annee_scolaire_id = ? AND i.status = 'inscrit'",
        [$current_year['id']]
    )->fetchAll();
    
    $regenerated_count = 0;
    
    foreach ($students as $student) {
        // Archiver l'ancienne carte si elle existe
        $old_card = $database->query(
            "SELECT * FROM cartes_eleves WHERE eleve_id = ? AND annee_scolaire_id = ?",
            [$student['id'], $current_year['id']]
        )->fetch();
        
        if ($old_card) {
            // Archiver l'ancienne carte
            $database->execute(
                "INSERT INTO cartes_eleves_historique 
                 (carte_id, eleve_id, annee_scolaire_id, numero_carte, qr_code, statut, date_generation, date_expiration, date_archivage)
                 VALUES (?, ?, ?, ?, ?, 'archivée', ?, ?, NOW())",
                [
                    $old_card['id'], $old_card['eleve_id'], $old_card['annee_scolaire_id'],
                    $old_card['numero_carte'], $old_card['qr_code'], $old_card['date_generation'],
                    $old_card['date_expiration']
                ]
            );
            
            // Supprimer l'ancienne carte
            $database->execute("DELETE FROM cartes_eleves WHERE id = ?", [$old_card['id']]);
        }
        
        // Générer une nouvelle carte
        require_once 'auto-generate.php';
        $carte_id = autoGenerateStudentCard($student['id'], $current_year['id']);
        
        if ($carte_id) {
            $regenerated_count++;
        }
    }
    
    $database->commit();
    
    // Log de l'action
    logAction('cartes_eleves', "Régénération de $regenerated_count carte(s) d'élève");
    
    echo json_encode([
        'success' => true,
        'message' => "Cartes régénérées avec succès",
        'count' => $regenerated_count
    ]);
    
} catch (Exception $e) {
    $database->rollback();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
