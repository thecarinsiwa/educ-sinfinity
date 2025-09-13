<?php
/**
 * Système de gestion des permissions granulaires
 * Application de gestion scolaire - République Démocratique du Congo
 */

/**
 * Vérifier si un utilisateur a une permission spécifique
 * DÉSACTIVÉ - Utilise l'ancien système de rôles
 * 
 * @param int $user_id ID de l'utilisateur
 * @param string $module Module (ex: 'students', 'users', 'finance')
 * @param string $action Action (ex: 'read', 'create', 'edit', 'delete')
 * @return bool True si l'utilisateur a la permission
 */
function hasUserPermission($user_id, $module, $action) {
    // Désactivé - Utiliser l'ancien système via checkPermission()
    return checkPermission($module);
}

/**
 * Vérifier si l'utilisateur actuel a une permission
 * DÉSACTIVÉ - Utilise l'ancien système de rôles
 * 
 * @param string $module Module
 * @param string $action Action
 * @return bool True si l'utilisateur a la permission
 */
function checkUserPermission($module, $action) {
    // Désactivé - Utiliser l'ancien système via checkPermission()
    return checkPermission($module);
}

/**
 * Récupérer toutes les permissions d'un utilisateur
 * 
 * @param int $user_id ID de l'utilisateur
 * @return array Tableau des permissions par module
 */
function getUserPermissions($user_id) {
    global $database;
    
    try {
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
        
        return json_decode($result['permissions'], true) ?: [];
        
    } catch (Exception $e) {
        error_log("Erreur de récupération des permissions : " . $e->getMessage());
        return [];
    }
}

/**
 * Récupérer toutes les permissions d'un rôle par ID
 * 
 * @param int $role_id ID du rôle
 * @return array Tableau des permissions par module
 */
function getRolePermissionsById($role_id) {
    global $database;
    
    try {
        $stmt = $database->query("SELECT permissions FROM roles WHERE id = ? AND actif = 1", [$role_id]);
        $result = $stmt->fetch();
        
        if (!$result || !$result['permissions']) {
            return [];
        }
        
        return json_decode($result['permissions'], true) ?: [];
        
    } catch (Exception $e) {
        error_log("Erreur de récupération des permissions du rôle : " . $e->getMessage());
        return [];
    }
}

/**
 * Mettre à jour les permissions d'un rôle
 * 
 * @param int $role_id ID du rôle
 * @param array $permissions Nouvelles permissions
 * @return bool True si la mise à jour a réussi
 */
function updateRolePermissions($role_id, $permissions) {
    global $database;
    
    try {
        $permissions_json = json_encode($permissions, JSON_UNESCAPED_UNICODE);
        
        $result = $database->execute("UPDATE roles SET permissions = ? WHERE id = ?", [$permissions_json, $role_id]);
        
        return $result;
        
    } catch (Exception $e) {
        error_log("Erreur de mise à jour des permissions : " . $e->getMessage());
        return false;
    }
}

/**
 * Obtenir la liste de tous les modules disponibles
 * 
 * @return array Liste des modules
 */
function getAvailableModules() {
    return [
        'students' => 'Gestion des élèves',
        'users' => 'Gestion des utilisateurs',
        'finance' => 'Gestion financière',
        'academic' => 'Gestion académique',
        'reports' => 'Rapports',
        'settings' => 'Paramètres'
    ];
}

/**
 * Obtenir la liste de toutes les actions disponibles
 * 
 * @return array Liste des actions
 */
function getAvailableActions() {
    return [
        'read' => 'Lire',
        'create' => 'Créer',
        'edit' => 'Modifier',
        'delete' => 'Supprimer'
    ];
}

/**
 * Vérifier si un utilisateur peut accéder à un module
 * 
 * @param int $user_id ID de l'utilisateur
 * @param string $module Module
 * @return bool True si l'utilisateur peut accéder au module
 */
function canAccessModule($user_id, $module) {
    $permissions = getUserPermissions($user_id);
    
    if (!isset($permissions[$module])) {
        return false;
    }
    
    // L'utilisateur peut accéder au module s'il a au moins une permission
    return !empty($permissions[$module]);
}

/**
 * Obtenir les modules accessibles par un utilisateur
 * 
 * @param int $user_id ID de l'utilisateur
 * @return array Liste des modules accessibles
 */
function getAccessibleModules($user_id) {
    $permissions = getUserPermissions($user_id);
    $modules = [];
    
    foreach ($permissions as $module => $actions) {
        if (!empty($actions)) {
            $modules[] = $module;
        }
    }
    
    return $modules;
}

/**
 * Afficher un message d'erreur si l'utilisateur n'a pas la permission
 * 
 * @param string $module Module
 * @param string $action Action
 * @param string $redirect_url URL de redirection
 */
function requireUserPermission($module, $action, $redirect_url = null) {
    if (!checkUserPermission($module, $action)) {
        if ($redirect_url) {
            showMessage('error', 'Vous n\'avez pas la permission d\'effectuer cette action.');
            redirectTo($redirect_url);
        } else {
            showMessage('error', 'Accès refusé. Permissions insuffisantes.');
            redirectTo('../../dashboard.php');
        }
        exit;
    }
}

/**
 * Fonction de compatibilité pour l'ancienne API
 * DÉSACTIVÉ - Utilise l'ancien système de rôles
 * 
 * @param string $permission Permission à vérifier (format: "module:action" ou ancien format)
 * @return bool True si l'utilisateur a la permission
 */
function checkPermissionCompat($permission) {
    // Utiliser l'ancien système de rôles
    if (strpos($permission, ':') !== false) {
        $module = explode(':', $permission)[0];
        return checkPermission($module);
    }
    
    return checkPermission($permission);
}

/**
 * Générer un tableau HTML des permissions
 * 
 * @param array $permissions Permissions à afficher
 * @return string HTML du tableau
 */
function renderPermissionsTable($permissions) {
    $modules = getAvailableModules();
    $actions = getAvailableActions();
    
    $html = '<table class="table table-bordered table-sm">';
    $html .= '<thead><tr><th>Module</th>';
    
    foreach ($actions as $action_key => $action_label) {
        $html .= "<th>{$action_label}</th>";
    }
    
    $html .= '</tr></thead><tbody>';
    
    foreach ($modules as $module_key => $module_label) {
        $html .= "<tr><td><strong>{$module_label}</strong></td>";
        
        foreach ($actions as $action_key => $action_label) {
            $has_permission = isset($permissions[$module_key]) && in_array($action_key, $permissions[$module_key]);
            $class = $has_permission ? 'table-success' : 'table-light';
            $icon = $has_permission ? '✅' : '❌';
            
            $html .= "<td class='{$class} text-center'>{$icon}</td>";
        }
        
        $html .= '</tr>';
    }
    
    $html .= '</tbody></table>';
    
    return $html;
}

/**
 * Vérifier si un utilisateur a accès à une page spécifique
 */
function hasPageAccess($user_id, $module, $page, $action = 'read') {
    global $database;
    
    try {
        // Récupérer le rôle de l'utilisateur
        $user = $database->query(
            "SELECT role_id FROM users WHERE id = ?",
            [$user_id]
        )->fetch();
        
        if (!$user || !$user['role_id']) {
            return false;
        }
        
        // Récupérer les permissions du rôle
        $role = $database->query(
            "SELECT permissions FROM roles WHERE id = ?",
            [$user['role_id']]
        )->fetch();
        
        if (!$role || !$role['permissions']) {
            return false;
        }
        
        $permissions = json_decode($role['permissions'], true);
        
        // Vérifier si l'utilisateur a la permission pour ce module et cette action
        if (isset($permissions[$module]) && in_array($action, $permissions[$module])) {
            return true;
        }
        
        return false;
        
    } catch (Exception $e) {
        error_log("Erreur dans hasPageAccess: " . $e->getMessage());
        return false;
    }
}

/**
 * Vérifier l'accès à une page pour l'utilisateur connecté
 */
function checkPageAccess($module, $page, $action = 'read') {
    if (!isset($_SESSION['user_id'])) {
        return false;
    }
    
    return hasPageAccess($_SESSION['user_id'], $module, $page, $action);
}

/**
 * Rediriger si l'utilisateur n'a pas accès à une page
 */
function requirePageAccess($module, $page, $action = 'read', $redirect_url = null) {
    if (!checkPageAccess($module, $page, $action)) {
        if ($redirect_url === null) {
            $redirect_url = APP_URL . '/dashboard.php';
        }
        
        showMessage('error', 'Accès refusé à cette page.');
        redirectTo($redirect_url);
        exit;
    }
}

/**
 * Obtenir les pages accessibles pour un utilisateur
 */
function getAccessiblePages($user_id) {
    global $database;
    
    try {
        // Récupérer le rôle de l'utilisateur
        $user = $database->query(
            "SELECT role_id FROM users WHERE id = ?",
            [$user_id]
        )->fetch();
        
        if (!$user || !$user['role_id']) {
            return [];
        }
        
        // Récupérer les permissions du rôle
        $role = $database->query(
            "SELECT permissions FROM roles WHERE id = ?",
            [$user['role_id']]
        )->fetch();
        
        if (!$role || !$role['permissions']) {
            return [];
        }
        
        $permissions = json_decode($role['permissions'], true);
        $accessible_pages = [];
        
        // Inclure le fichier de permissions détaillées
        if (file_exists(__DIR__ . '/../config/detailed-permissions.php')) {
            require_once __DIR__ . '/../config/detailed-permissions.php';
            $detailed_permissions = getDetailedPermissions();
            
            foreach ($permissions as $module => $actions) {
                if (isset($detailed_permissions[$module])) {
                    $accessible_pages[$module] = [
                        'name' => $detailed_permissions[$module]['name'],
                        'pages' => []
                    ];
                    
                    foreach ($detailed_permissions[$module]['pages'] as $page_key => $page) {
                        if (isset($page['permissions'])) {
                            foreach ($page['permissions'] as $action) {
                                if (in_array($action, $actions)) {
                                    $accessible_pages[$module]['pages'][$page_key] = $page;
                                    break;
                                }
                            }
                        }
                    }
                }
            }
        }
        
        return $accessible_pages;
        
    } catch (Exception $e) {
        error_log("Erreur dans getAccessiblePages: " . $e->getMessage());
        return [];
    }
}
?>
