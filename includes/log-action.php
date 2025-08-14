<?php
/**
 * Enregistrer une action utilisateur
 * Application de gestion scolaire - République Démocratique du Congo
 */

require_once '../config/config.php';
require_once '../config/database.php';
require_once 'functions.php';

// Vérifier l'authentification
requireLogin();

header('Content-Type: application/json');

try {
    // Récupérer les données JSON
    $input = json_decode(file_get_contents('php://input'), true);
    
    $action = sanitizeInput($input['action'] ?? '');
    $module = sanitizeInput($input['module'] ?? '');
    $details = sanitizeInput($input['details'] ?? '');
    $target_id = (int)($input['target_id'] ?? 0) ?: null;
    
    if (!$action || !$module) {
        throw new Exception('Action et module requis');
    }
    
    // Enregistrer l'action
    $success = logUserAction($action, $module, $details, $target_id);
    
    if ($success) {
        echo json_encode([
            'success' => true,
            'message' => 'Action enregistrée'
        ]);
    } else {
        throw new Exception('Erreur lors de l\'enregistrement');
    }
    
} catch (Exception $e) {
    error_log("Erreur lors de l'enregistrement d'action: " . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
