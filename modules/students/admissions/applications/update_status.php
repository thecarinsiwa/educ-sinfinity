<?php
/**
 * Mise Ã  jour du statut d'une candidature
 * Application de gestion scolaire - République Démocratique du Congo
 */

require_once '../../../../config/config.php';
require_once '../../../../config/database.php';
require_once '../../../../includes/functions.php';
require_once '../../../../includes/permissions-pages.php';

// VÃ©rifier l'authentification et les permissions
requireLogin();

requirePagePermissionFromDB('students', 'admissions/applications/update_status', 'edit', '../../../../dashboard.php');

$candidature_id = intval($_GET['id'] ?? 0);
$new_status = $_GET['status'] ?? '';

// VÃ©rifier les paramÃ¨tres
if (!$candidature_id || !$new_status) {
    showMessage('error', 'Paramètres invalides.');
    redirectTo('index.php');
}

// VÃ©rifier que le statut est valide
$valid_statuses = ['en_attente', 'acceptee', 'refusee', 'en_cours_traitement', 'inscrit'];
if (!in_array($new_status, $valid_statuses)) {
    showMessage('error', 'Statut invalide.');
    redirectTo('index.php');
}

try {
    // VÃ©rifier que la candidature existe
    $candidature = $database->query(
        "SELECT * FROM demandes_admission WHERE id = ?",
        [$candidature_id]
    )->fetch();

    if (!$candidature) {
        showMessage('error', 'Candidature non trouvÃ©e.');
        redirectTo('index.php');
    }

    // Mettre Ã  jour le statut
    $database->execute(
        "UPDATE demandes_admission 
         SET status = ?, traite_par = ?, date_traitement = NOW(), updated_at = NOW()
         WHERE id = ?",
        [$new_status, $_SESSION['user_id'], $candidature_id]
    );

    // Messages de confirmation selon le statut
    $status_messages = [
        'acceptee' => 'Candidature acceptÃ©e avec succÃ¨s.',
        'refusee' => 'Candidature refusÃ©e.',
        'en_cours_traitement' => 'Candidature mise en cours de traitement.',
        'inscrit' => 'Candidat marquÃ© comme inscrit.',
        'en_attente' => 'Candidature remise en attente.'
    ];

    $message = $status_messages[$new_status] ?? 'Statut mis Ã  jour avec succÃ¨s.';
    showMessage('success', $message);

    // Si la candidature est acceptÃ©e, proposer de crÃ©er l'Ã©lÃ¨ve
    if ($new_status === 'acceptee') {
        $_SESSION['create_student_from_application'] = $candidature_id;
        showMessage('info', 'Vous pouvez maintenant crÃ©er le dossier Ã©lÃ¨ve Ã  partir de cette candidature.');
    }

} catch (Exception $e) {
    showMessage('error', 'Erreur lors de la mise Ã  jour : ' . $e->getMessage());
}

// Rediriger vers la page de dÃ©tails
redirectTo("view.php?id=$candidature_id");
?>




