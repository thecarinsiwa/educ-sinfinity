<?php
/**
 * Module de gestion financiÃ¨re - Activer/DÃ©sactiver un type de frais
 * Application de gestion scolaire - RÃ©publique DÃ©mocratique du Congo
 */

require_once '../../../../config/config.php';
require_once '../../../../config/database.php';
require_once '../../../../includes/functions.php';
require_once '../../../../includes/permissions-pages.php';

// Vérifier l'authentification et les permissions
requireLogin();
requirePagePermissionFromDB('finance', 'fees', 'edit', '../../../../dashboard.php');

// RÃ©cupÃ©rer les paramÃ¨tres
$id = (int)($_GET['id'] ?? 0);
$action = sanitizeInput($_GET['action'] ?? '');

if (!$id || !in_array($action, ['activer', 'desactiver'])) {
    showMessage('error', 'ParamÃ¨tres invalides.');
    redirectTo('index.php');
}

try {
    // VÃ©rifier que le type de frais existe
    $type_frais = $database->query(
        "SELECT id, nom, actif FROM type_frais WHERE id = ?",
        [$id]
    )->fetch();
    
    if (!$type_frais) {
        showMessage('error', 'Type de frais non trouvÃ©.');
        redirectTo('index.php');
    }
    
    // DÃ©terminer le nouveau statut
    $nouveau_statut = ($action === 'activer') ? 1 : 0;
    
    // VÃ©rifier si le statut change rÃ©ellement
    if ($type_frais['actif'] == $nouveau_statut) {
        $message = ($action === 'activer') ? 'Ce type de frais est dÃ©jÃ  actif.' : 'Ce type de frais est dÃ©jÃ  inactif.';
        showMessage('warning', $message);
        redirectTo('index.php');
    }
    
    $database->beginTransaction();
    
    // Mettre Ã  jour le statut
    $sql = "UPDATE type_frais SET actif = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?";
    $database->execute($sql, [$nouveau_statut, $id]);
    
    // Enregistrer l'action dans les logs
    $action_log = ($action === 'activer') ? 'type_frais_activated' : 'type_frais_deactivated';
    $message_log = "Type de frais {$action}: {$type_frais['nom']}";
    logAction($action_log, $message_log, $id);
    
    $database->commit();
    
    $message = ($action === 'activer') 
        ? 'Type de frais activÃ© avec succÃ¨s !' 
        : 'Type de frais dÃ©sactivÃ© avec succÃ¨s !';
    
    showMessage('success', $message);
    
} catch (Exception $e) {
    if (isset($database)) {
        $database->rollback();
    }
    showMessage('error', 'Erreur lors de la modification : ' . $e->getMessage());
}

redirectTo('index.php');
?>



