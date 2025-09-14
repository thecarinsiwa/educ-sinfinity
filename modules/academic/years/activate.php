<?php
/**
 * Module de gestion académique - Activer une année scolaire
 * Application de gestion scolaire - République Démocratique du Congo
 */

require_once '../../../config/config.php';
require_once '../../../config/database.php';
require_once '../../../includes/functions.php';
require_once '../../../includes/permissions-pages.php';

// Vérifier l'authentification et les permissions
requireLogin();
requirePagePermissionFromDB('academic', 'years/activate', 'edit', '../../../dashboard.php');

$page_title = 'Activer une Année Scolaire';

// Récupérer l'ID de l'année
$year_id = (int)($_GET['id'] ?? 0);

if ($year_id <= 0) {
    showMessage('error', 'ID d\'année invalide.');
    redirectTo('index.php');
}

// Récupérer les informations de l'année
try {
    $year = $database->query(
        "SELECT * FROM annees_scolaires WHERE id = ?",
        [$year_id]
    )->fetch();
    
    if (!$year) {
        showMessage('error', 'Année scolaire non trouvée.');
        redirectTo('index.php');
    }
    
    if ($year['status'] === 'active') {
        showMessage('warning', 'Cette année scolaire est déjà active.');
        redirectTo('index.php');
    }
    
    // Désactiver toutes les autres années actives
    $database->execute(
        "UPDATE annees_scolaires SET status = 'fermee' WHERE status = 'active'"
    );
    
    // Activer l'année sélectionnée
    $database->execute(
        "UPDATE annees_scolaires SET status = 'active' WHERE id = ?",
        [$year_id]
    );
    
    // Enregistrer l'action
    if (function_exists('logUserAction')) {
        logUserAction(
            'activate_year',
            'academic',
            'Année scolaire activée: ' . $year['annee'],
            $year_id
        );
    }
    
    showMessage('success', 'L\'année scolaire ' . $year['annee'] . ' a été activée avec succès.');
    redirectTo('index.php');
    
} catch (Exception $e) {
    showMessage('error', 'Erreur lors de l\'activation de l\'année scolaire: ' . $e->getMessage());
    redirectTo('index.php');
}
?>
