<?php
/**
 * API pour récupérer les données d'une candidature
 * Application de gestion scolaire - République Démocratique du Congo
 */

require_once '../../../../config/config.php';
require_once '../../../../config/database.php';
require_once '../../../../includes/functions.php';

// Vérifier l'authentification et les permissions
requireLogin();
if (!checkPermission('students')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Accès refusé']);
    exit;
}

$candidature_id = intval($_GET['id'] ?? 0);

if (!$candidature_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'ID invalide']);
    exit;
}

try {
    $candidature = $database->query(
        "SELECT classe_demandee_id, frais_inscription, frais_scolarite, reduction_accordee
         FROM demandes_admission 
         WHERE id = ? AND status = 'acceptee'",
        [$candidature_id]
    )->fetch();

    if (!$candidature) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Candidature non trouvée ou non acceptée']);
        exit;
    }

    echo json_encode([
        'success' => true,
        'classe_demandee_id' => $candidature['classe_demandee_id'],
        'frais_inscription' => $candidature['frais_inscription'],
        'frais_scolarite' => $candidature['frais_scolarite'],
        'reduction_accordee' => $candidature['reduction_accordee']
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erreur serveur']);
}
?>
