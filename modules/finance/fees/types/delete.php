<?php
/**
 * Module de gestion financiÃ¨re - Supprimer un type de frais
 * Application de gestion scolaire - RÃ©publique DÃ©mocratique du Congo
 */

require_once '../../../../config/config.php';
require_once '../../../../config/database.php';
require_once '../../../../includes/functions.php';
require_once '../../../../includes/permissions-pages.php';

// Vérifier l'authentification et les permissions
requireLogin();
requirePagePermissionFromDB('finance', 'fees', 'delete', '../../../../dashboard.php');

// RÃ©cupÃ©rer l'ID du type de frais
$id = (int)($_GET['id'] ?? 0);

if (!$id) {
    showMessage('error', 'Type de frais non spÃ©cifiÃ©.');
    redirectTo('index.php');
}

try {
    // VÃ©rifier que le type de frais existe
    $type_frais = $database->query(
        "SELECT id, nom FROM type_frais WHERE id = ?",
        [$id]
    )->fetch();
    
    if (!$type_frais) {
        showMessage('error', 'Type de frais non trouvÃ©.');
        redirectTo('index.php');
    }
    
    // VÃ©rifier si le type de frais est utilisÃ©
    $usage_count = $database->query(
        "SELECT COUNT(*) as count FROM frais_scolaires WHERE type_frais = ?",
        [$type_frais['nom']]
    )->fetch()['count'];
    
    if ($usage_count > 0) {
        showMessage('error', 'Impossible de supprimer ce type de frais car il est utilisÃ© dans ' . $usage_count . ' configuration(s).');
        redirectTo('index.php');
    }
    
    $database->beginTransaction();
    
    // Supprimer le type de frais
    $sql = "DELETE FROM type_frais WHERE id = ?";
    $database->execute($sql, [$id]);
    
    // Enregistrer l'action dans les logs
    logAction('type_frais_deleted', "Type de frais supprimÃ©: {$type_frais['nom']}", $id);
    
    $database->commit();
    
    showMessage('success', 'Type de frais supprimÃ© avec succÃ¨s !');
    
} catch (Exception $e) {
    if (isset($database)) {
        $database->rollback();
    }
    showMessage('error', 'Erreur lors de la suppression : ' . $e->getMessage());
}

redirectTo('index.php');
?>



