<?php
/**
 * Justifier une absence
 * Application de gestion scolaire - République Démocratique du Congo
 */

require_once '../../../config/config.php';
require_once '../../../config/database.php';
require_once '../../../includes/functions.php';
require_once '../../../includes/permissions-pages.php';

// VÃ©rifier l'authentification et les permissions
requireLogin();

requirePagePermissionFromDB('students', 'attendance/justify-absence', 'edit', '../../../dashboard.php');


header('Content-Type: application/json');

try {
    // RÃ©cupÃ©rer les donnÃ©es JSON
    $input = json_decode(file_get_contents('php://input'), true);
    $absence_id = (int)($input['id'] ?? 0);
    
    if (!$absence_id) {
        throw new Exception('ID d\'absence manquant');
    }
    
    // VÃ©rifier que l'absence existe et n'est pas dÃ©jÃ  justifiÃ©e
    $absence = $database->query(
        "SELECT a.*, e.nom as eleve_nom, e.prenom as eleve_prenom, 
                c.nom as classe_nom
         FROM absences a
         JOIN eleves e ON a.eleve_id = e.id
         JOIN inscriptions i ON e.id = i.eleve_id
         JOIN classes c ON i.classe_id = c.id
         WHERE a.id = ?",
        [$absence_id]
    )->fetch();
    
    if (!$absence) {
        throw new Exception('Absence non trouvÃ©e');
    }
    
    if ($absence['justifiee']) {
        throw new Exception('Cette absence est dÃ©jÃ  justifiÃ©e');
    }
    
    // Commencer une transaction
    $database->beginTransaction();
    
    try {
        // Mettre Ã  jour l'absence
        $database->query(
            "UPDATE absences 
             SET justifiee = 1, 
                 updated_by = ?, 
                 updated_at = NOW() 
             WHERE id = ?",
            [$_SESSION['user_id'], $absence_id]
        );
        
        // Enregistrer l'action dans l'historique
        logUserAction(
            'justify_absence',
            'attendance',
            'Absence justifiÃ©e pour ' . $absence['eleve_nom'] . ' ' . $absence['eleve_prenom'] . 
            ' (' . $absence['classe_nom'] . ') - Date: ' . formatDate($absence['date_absence']),
            $absence_id
        );
        
        // Valider la transaction
        $database->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'Absence justifiÃ©e avec succÃ¨s'
        ]);
        
    } catch (Exception $e) {
        $database->rollback();
        throw $e;
    }
    
} catch (Exception $e) {
    error_log("Erreur lors de la justification d'absence: " . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>




