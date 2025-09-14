<?php
/**
 * AJAX - Statistiques des caisses en temps réel
 * Application de gestion scolaire - République Démocratique du Congo
 */

require_once '../../../config/config.php';
require_once '../../../config/database.php';
require_once '../../../includes/functions.php';
require_once '../../../includes/permissions-pages.php';

// Vérifier l'authentification
requireLogin();

// Vérifier l'accès à cette page
requirePagePermissionFromDB('finance', 'expenses/ajax_caisse_stats', 'read', '../../dashboard.php');

// Obtenir la devise par défaut
$devise_par_defaut = getDefaultCurrency();

// Définir le type de contenu JSON
header('Content-Type: application/json');

try {
    // Sessions ouvertes
    $sessions_ouvertes = $database->query(
        "SELECT COUNT(*) as total FROM sessions_caisse WHERE statut = 'ouverte'"
    )->fetch()['total'];
    
    // Mouvements aujourd'hui
    $mouvements_aujourdhui = $database->query(
        "SELECT COUNT(*) as total FROM mouvements_caisse WHERE DATE(date_mouvement) = CURDATE()"
    )->fetch()['total'];
    
    // Total entrées aujourd'hui (converties en devise par défaut)
    $entrees_aujourdhui = $database->query(
        "SELECT COALESCE(SUM(mc.montant / d.taux_conversion), 0) as total 
         FROM mouvements_caisse mc
         JOIN devises d ON mc.devise_id = d.id
         WHERE DATE(mc.date_mouvement) = CURDATE() AND mc.type_mouvement = 'entree'"
    )->fetch()['total'];
    
    // Total sorties aujourd'hui (converties en devise par défaut)
    $sorties_aujourdhui = $database->query(
        "SELECT COALESCE(SUM(mc.montant / d.taux_conversion), 0) as total 
         FROM mouvements_caisse mc
         JOIN devises d ON mc.devise_id = d.id
         WHERE DATE(mc.date_mouvement) = CURDATE() AND mc.type_mouvement = 'sortie'"
    )->fetch()['total'];
    
    // Obtenir l'année scolaire actuelle
    $current_year = getCurrentAcademicYear();
    
    // Dépenses synchronisées avec les caisses
    $depenses_synchronisees = $database->query(
        "SELECT COUNT(*) as total FROM depenses d
         WHERE d.annee_scolaire_id = ? 
         AND d.id IN (
             SELECT DISTINCT CAST(SUBSTRING_INDEX(mc.reference, '-', -1) AS UNSIGNED)
             FROM mouvements_caisse mc
             WHERE mc.reference LIKE 'DEPENSE-%'
         )",
        [$current_year['id']]
    )->fetch()['total'];
    
    // Retourner les données en JSON
    echo json_encode([
        'success' => true,
        'sessions_ouvertes' => (int)$sessions_ouvertes,
        'mouvements_aujourdhui' => (int)$mouvements_aujourdhui,
        'entrees_aujourdhui' => (float)$entrees_aujourdhui,
        'sorties_aujourdhui' => (float)$sorties_aujourdhui,
        'depenses_synchronisees' => (int)$depenses_synchronisees,
        'devise_symbole' => $devise_par_defaut['symbole'] ?? 'FC',
        'timestamp' => time()
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>

