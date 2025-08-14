<?php
/**
 * API pour enregistrer les actions utilisateur
 * Application de gestion scolaire - République Démocratique du Congo
 */

require_once '../../../config/config.php';
require_once '../../../config/database.php';
require_once '../../../includes/functions.php';

// Vérifier l'authentification
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Non authentifié']);
    exit;
}

// Vérifier que c'est une requête POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
    exit;
}

// Lire les données JSON
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Données JSON invalides']);
    exit;
}

try {
    $action = $input['action'] ?? '';
    $module = $input['module'] ?? '';
    $details = $input['details'] ?? '';
    $target_id = $input['target_id'] ?? null;
    
    if (!$action || !$module) {
        throw new Exception('Action et module requis');
    }
    
    // Enregistrer l'action
    $result = logUserAction($action, $module, $details, $target_id);
    
    if ($result) {
        echo json_encode(['success' => true, 'message' => 'Action enregistrée']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Erreur lors de l\'enregistrement']);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
