<?php
/**
 * Fonctions de permissions pour le sidebar
 * Système de gestion scolaire - République Démocratique du Congo
 * 
 * Ce fichier contient les fonctions spécifiques pour gérer les permissions
 * du sidebar avec le nouveau système basé sur les pages.
 */

require_once __DIR__ . '/../config/page-permissions.php';

/**
 * Vérifier si un utilisateur a accès à un module
 * DÉSACTIVÉ - Utilise l'ancien système de rôles
 * 
 * @param int $user_id ID de l'utilisateur
 * @param string $module Module à vérifier
 * @return bool True si l'utilisateur a accès au module
 */
function hasModuleAccess($user_id, $module) {
    // Désactivé - Utiliser l'ancien système via checkPermission()
    return checkPermission($module);
}

/**
 * Vérifier si l'utilisateur actuel a accès à un module
 * Cette fonction est définie dans functions.php avec un système de fallback
 * 
 * @param string $module Module à vérifier
 * @return bool True si l'utilisateur a accès au module
 */
// Fonction définie dans functions.php - pas de redéclaration ici

/**
 * Obtenir les pages accessibles d'un module pour un utilisateur
 * 
 * @param int $user_id ID de l'utilisateur
 * @param string $module Module
 * @return array Pages accessibles
 */
function getModuleAccessiblePages($user_id, $module) {
    global $database;
    
    try {
        // Récupérer le rôle de l'utilisateur avec ses permissions
        $stmt = $database->query(
            "SELECT r.permissions 
             FROM users u 
             JOIN roles r ON u.role_id = r.id 
             WHERE u.id = ? AND r.actif = 1",
            [$user_id]
        );
        $result = $stmt->fetch();
        
        if (!$result || !$result['permissions']) {
            return [];
        }
        
        $permissions = json_decode($result['permissions'], true);
        
        if (!$permissions) {
            return [];
        }
        
        $accessible_pages = [];
        
        // Filtrer les pages du module
        foreach ($permissions as $page => $actions) {
            if (strpos($page, $module . '/') === 0) {
                $accessible_pages[$page] = $actions;
            }
        }
        
        return $accessible_pages;
        
    } catch (Exception $e) {
        error_log("Erreur de récupération des pages du module : " . $e->getMessage());
        return [];
    }
}

/**
 * Obtenir les pages accessibles d'un module pour l'utilisateur actuel
 * 
 * @param string $module Module
 * @return array Pages accessibles
 */
function getCurrentUserModulePages($module) {
    if (!isset($_SESSION['user_id'])) {
        return [];
    }
    
    return getModuleAccessiblePages($_SESSION['user_id'], $module);
}

/**
 * Vérifier si un utilisateur a accès à une page spécifique
 * Cette fonction est définie dans permissions-pages.php
 * 
 * @param int $user_id ID de l'utilisateur
 * @param string $page_path Chemin de la page
 * @param string $action Action (défaut: 'read')
 * @return bool True si l'utilisateur a accès
 */
// Fonction définie dans permissions-pages.php - pas de redéclaration ici

/**
 * Vérifier si l'utilisateur actuel a accès à une page
 * 
 * @param string $page_path Chemin de la page
 * @param string $action Action (défaut: 'read')
 * @return bool True si l'utilisateur a accès
 */
function checkUserPageAccess($page_path, $action = 'read') {
    if (!isset($_SESSION['user_id'])) {
        return false;
    }
    
    return hasUserPageAccess($_SESSION['user_id'], $page_path, $action);
}

/**
 * Obtenir les modules accessibles pour un utilisateur
 * DÉSACTIVÉ - Utilise l'ancien système de rôles
 * 
 * @param int $user_id ID de l'utilisateur
 * @return array Modules accessibles
 */
function getUserAccessibleModules($user_id) {
    // Désactivé - Utiliser l'ancien système basé sur les rôles
    if (!isset($_SESSION['user_role'])) {
        return [];
    }
    
    $role = $_SESSION['user_role'];
    
    if (!defined('ROLES') || !isset(ROLES[$role]['permissions'])) {
        return [];
    }
    
    $user_permissions = ROLES[$role]['permissions'];
    
    // Si l'utilisateur a la permission 'all', il a accès à tous les modules
    if (in_array('all', $user_permissions)) {
        return array_keys(MODULES);
    }
    
    return $user_permissions;
}

/**
 * Obtenir les modules accessibles pour l'utilisateur actuel
 * 
 * @return array Modules accessibles
 */
function getCurrentUserModules() {
    if (!isset($_SESSION['user_id'])) {
        return [];
    }
    
    return getUserAccessibleModules($_SESSION['user_id']);
}

/**
 * Fonction de compatibilité pour l'ancien système
 * Cette fonction est définie dans functions.php avec une signature différente
 * 
 * @param string $permission Permission à vérifier
 * @return bool True si l'utilisateur a la permission
 */
// Fonction définie dans functions.php - pas de redéclaration ici

/**
 * Obtenir les statistiques d'accès d'un utilisateur
 * 
 * @param int $user_id ID de l'utilisateur
 * @return array Statistiques
 */
function getUserAccessStats($user_id) {
    global $database;
    
    try {
        // Récupérer le rôle de l'utilisateur avec ses permissions
        $stmt = $database->query(
            "SELECT r.permissions 
             FROM users u 
             JOIN roles r ON u.role_id = r.id 
             WHERE u.id = ? AND r.actif = 1",
            [$user_id]
        );
        $result = $stmt->fetch();
        
        if (!$result || !$result['permissions']) {
            return [
                'total_pages' => 0,
                'modules' => [],
                'actions' => []
            ];
        }
        
        $permissions = json_decode($result['permissions'], true);
        
        if (!$permissions) {
            return [
                'total_pages' => 0,
                'modules' => [],
                'actions' => []
            ];
        }
        
        $stats = [
            'total_pages' => count($permissions),
            'modules' => [],
            'actions' => []
        ];
        
        foreach ($permissions as $page => $actions) {
            $module = explode('/', $page)[0];
            if (!isset($stats['modules'][$module])) {
                $stats['modules'][$module] = 0;
            }
            $stats['modules'][$module]++;
            
            foreach ($actions as $action) {
                if (!isset($stats['actions'][$action])) {
                    $stats['actions'][$action] = 0;
                }
                $stats['actions'][$action]++;
            }
        }
        
        return $stats;
        
    } catch (Exception $e) {
        error_log("Erreur de récupération des statistiques d'accès : " . $e->getMessage());
        return [
            'total_pages' => 0,
            'modules' => [],
            'actions' => []
        ];
    }
}

/**
 * Obtenir les statistiques d'accès de l'utilisateur actuel
 * 
 * @return array Statistiques
 */
function getCurrentUserAccessStats() {
    if (!isset($_SESSION['user_id'])) {
        return [
            'total_pages' => 0,
            'modules' => [],
            'actions' => []
        ];
    }
    
    return getUserAccessStats($_SESSION['user_id']);
}
?>
