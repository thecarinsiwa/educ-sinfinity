<?php
/**
 * Mise à jour du statut d'une candidature
 * Application de gestion scolaire - République Démocratique du Congo
 */

require_once '../../../../config/config.php';
require_once '../../../../config/database.php';
require_once '../../../../includes/functions.php';

// Vérifier l'authentification et les permissions
requireLogin();
if (!checkPermission('students')) {
    showMessage('error', 'Accès refusé à cette action.');
    redirectTo('index.php');
}

$candidature_id = intval($_GET['id'] ?? 0);
$new_status = $_GET['status'] ?? '';

// Vérifier les paramètres
if (!$candidature_id || !$new_status) {
    showMessage('error', 'Paramètres invalides.');
    redirectTo('index.php');
}

// Vérifier que le statut est valide
$valid_statuses = ['en_attente', 'acceptee', 'refusee', 'en_cours_traitement', 'inscrit'];
if (!in_array($new_status, $valid_statuses)) {
    showMessage('error', 'Statut invalide.');
    redirectTo('index.php');
}

try {
    // Vérifier que la candidature existe
    $candidature = $database->query(
        "SELECT * FROM demandes_admission WHERE id = ?",
        [$candidature_id]
    )->fetch();

    if (!$candidature) {
        showMessage('error', 'Candidature non trouvée.');
        redirectTo('index.php');
    }

    // Mettre à jour le statut
    $database->execute(
        "UPDATE demandes_admission 
         SET status = ?, traite_par = ?, date_traitement = NOW(), updated_at = NOW()
         WHERE id = ?",
        [$new_status, $_SESSION['user_id'], $candidature_id]
    );

    // Messages de confirmation selon le statut
    $status_messages = [
        'acceptee' => 'Candidature acceptée avec succès.',
        'refusee' => 'Candidature refusée.',
        'en_cours_traitement' => 'Candidature mise en cours de traitement.',
        'inscrit' => 'Candidat marqué comme inscrit.',
        'en_attente' => 'Candidature remise en attente.'
    ];

    $message = $status_messages[$new_status] ?? 'Statut mis à jour avec succès.';
    showMessage('success', $message);

    // Si la candidature est acceptée, proposer de créer l'élève
    if ($new_status === 'acceptee') {
        $_SESSION['create_student_from_application'] = $candidature_id;
        showMessage('info', 'Vous pouvez maintenant créer le dossier élève à partir de cette candidature.');
    }

} catch (Exception $e) {
    showMessage('error', 'Erreur lors de la mise à jour : ' . $e->getMessage());
}

// Rediriger vers la page de détails
redirectTo("view.php?id=$candidature_id");
?>
