<?php
/**
 * API pour rÃ©cupÃ©rer les donnÃ©es d'Ã©valuation d'une candidature
 * Application de gestion scolaire - République Démocratique du Congo
 */

require_once '../../../../config/config.php';
require_once '../../../../config/database.php';
require_once '../../../../includes/functions.php';
require_once '../../../../includes/permissions-pages.php';

// VÃ©rifier l'authentification et les permissions
requireLogin();

requirePagePermissionFromDB('students', 'admissions', 'read', '../../../../dashboard.php');

$candidature_id = intval($_GET['id'] ?? 0);

if (!$candidature_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'ID invalide']);
    exit;
}

try {
    $evaluation = $database->query(
        "SELECT note_evaluation, recommandation, commentaire_evaluation, 
                date_evaluation, evalue_par
         FROM demandes_admission 
         WHERE id = ?",
        [$candidature_id]
    )->fetch();

    if (!$evaluation) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Candidature non trouvÃ©e']);
        exit;
    }

    echo json_encode([
        'success' => true,
        'note_evaluation' => $evaluation['note_evaluation'],
        'recommandation' => $evaluation['recommandation'],
        'commentaire_evaluation' => $evaluation['commentaire_evaluation'],
        'date_evaluation' => $evaluation['date_evaluation'],
        'evalue_par' => $evaluation['evalue_par']
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erreur serveur']);
}
?>




