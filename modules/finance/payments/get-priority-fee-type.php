<?php
/**
 * Endpoint AJAX pour récupérer le type de frais prioritaire pour un élève
 */

require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/../fees/types/priority-functions-simple.php';

// Vérifier l'authentification (version compatible AJAX)
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Non authentifié']);
    exit;
}

if (!checkPagePermission('finance')) {
    http_response_code(403);
    echo json_encode(['error' => 'Accès refusé']);
    exit;
}

// Vérifier que c'est une requête AJAX
if (!isset($_SERVER['HTTP_X_REQUESTED_WITH']) || strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) !== 'xmlhttprequest') {
    http_response_code(400);
    echo json_encode(['error' => 'Requête invalide']);
    exit;
}

// Récupérer les paramètres
$eleve_id = (int)($_GET['eleve_id'] ?? 0);
$annee_scolaire_id = (int)($_GET['annee_scolaire_id'] ?? 0);

if (!$eleve_id || !$annee_scolaire_id) {
    http_response_code(400);
    echo json_encode(['error' => 'Paramètres manquants']);
    exit;
}

try {
    // Récupérer le type de frais prioritaire
    $next_fee = getNextPriorityFeeType($eleve_id, $annee_scolaire_id);
    
    if ($next_fee) {
        // Récupérer le statut de paiement complet
        $payment_status = getStudentPaymentStatus($eleve_id, $annee_scolaire_id);
        
        echo json_encode([
            'success' => true,
            'priority_fee' => [
                'type_id' => $next_fee['type_frais']['id'],
                'type_nom' => $next_fee['type_frais']['nom'],
                'type_priorite' => $next_fee['type_frais']['priorite'],
                'frais_id' => $next_fee['frais']['id'],
                'frais_libelle' => $next_fee['frais']['libelle'],
                'montant_restant' => $next_fee['montant_restant'],
                'montant_paye' => $next_fee['montant_paye']
            ],
            'payment_status' => $payment_status
        ]);
    } else {
        echo json_encode([
            'success' => true,
            'priority_fee' => null,
            'message' => 'Tous les frais sont soldés pour cet élève',
            'payment_status' => getStudentPaymentStatus($eleve_id, $annee_scolaire_id)
        ]);
    }
    
} catch (Exception $e) {
    error_log("Erreur get-priority-fee-type: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Erreur serveur']);
}
?>
