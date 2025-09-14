
<?php
/**
 * Système de gestion des permissions basé sur les pages individuelles
 * Application de gestion scolaire - République Démocratique du Congo
 * 
 * Ce fichier implémente le nouveau système de permissions basé sur les 247 pages
 * au lieu des modules génériques pour un contrôle d'accès granulaire.
 */

require_once __DIR__ . '/../config/page-permissions.php';
require_once  __DIR__ . '/ui-permissions.php';

/**
 * Vérifier si un utilisateur a accès à une page spécifique
 * DÉSACTIVÉ - Utilise l'ancien système de rôles
 * 
 * @param int $user_id ID de l'utilisateur
 * @param string $page_path Chemin de la page (ex: 'students/index', 'finance/payments/add')
 * @param string $action Action (ex: 'read', 'create', 'edit', 'delete')
 * @return bool True si l'utilisateur a la permission
 */
function hasUserPageAccess($user_id, $page_path, $action = 'read') {
    // Désactivé - Utiliser l'ancien système via checkPermission()
    $module = explode('/', $page_path)[0];
    return checkPermission($module);
}

/**
 * Vérifier si l'utilisateur actuel a accès à une page
 * DÉSACTIVÉ - Utilise l'ancien système de rôles
 * 
 * @param string $page_path Chemin de la page
 * @param string $action Action
 * @return bool True si l'utilisateur a la permission
 */
function checkPagePermission($page_path, $action = 'read') {
    // Désactivé - Utiliser l'ancien système via checkPermission()
    $module = explode('/', $page_path)[0];
    return checkPermission($module);
}

/**
 * Récupérer toutes les permissions d'un utilisateur par page
 * 
 * @param int $user_id ID de l'utilisateur
 * @return array Tableau des permissions par page
 */
function getUserPagePermissions($user_id) {
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
        error_log("Erreur de récupération des permissions de page : " . $e->getMessage());
        return [];
    }
}

/**
 * Récupérer toutes les permissions d'un rôle par page
 * 
 * @param int $role_id ID du rôle
 * @return array Tableau des permissions par page
 */
function getRolePagePermissions($role_id) {
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
 * Mettre à jour les permissions d'un rôle par page
 * 
 * @param int $role_id ID du rôle
 * @param array $permissions Nouvelles permissions par page
 * @return bool True si la mise à jour a réussi
 */
function updateRolePagePermissions($role_id, $permissions) {
    global $database;
    
    try {
        $permissions_json = json_encode($permissions, JSON_UNESCAPED_UNICODE);
        
        $result = $database->execute(
            "UPDATE roles SET permissions = ?, date_modification = NOW() WHERE id = ?", 
            [$permissions_json, $role_id]
        );
        
        return $result;
        
    } catch (Exception $e) {
        error_log("Erreur de mise à jour des permissions de page : " . $e->getMessage());
        return false;
    }
}

/**
 * Obtenir la liste de toutes les pages disponibles
 * 
 * @return array Liste des pages avec leurs permissions
 */
function getAvailablePages() {
    return PAGE_PERMISSIONS;
}




/**
 * Obtenir les pages accessibles par un utilisateur dans un module
 * 
 * @param int $user_id ID de l'utilisateur
 * @param string $module Module
 * @return array Liste des pages accessibles
 */
function getAccessiblePagesInModule($user_id, $module) {
    $permissions = getUserPagePermissions($user_id);
    $pages = [];
    
    foreach ($permissions as $page => $actions) {
        if (strpos($page, $module . '/') === 0) {
            $pages[$page] = $actions;
        }
    }
    
    return $pages;
}

/**
 * Vérifier si l'utilisateur a accès à une page spécifique avec le nouveau système
 * 
 * @param string $module Module (ex: 'academic', 'students')
 * @param string $page Page (ex: 'index', 'add', 'edit')
 * @param string $action Action (ex: 'read', 'create', 'edit')
 * @return bool True si l'utilisateur a la permission
 */
function hasPagePermission($module, $page, $action = 'read') {
    global $database;
    
    if (!isset($_SESSION['user_id'])) {
        return false;
    }
    
    try {
        // Récupérer les permissions du rôle de l'utilisateur
        $stmt = $database->query(
            "SELECT r.permissions 
             FROM users u 
             JOIN roles r ON u.role_id = r.id 
             WHERE u.id = ? AND r.actif = 1",
            [$_SESSION['user_id']]
        );
        $result = $stmt->fetch();
        
        if ($result && $result['permissions']) {
            $permissions = json_decode($result['permissions'], true);
            
            // Vérifier si l'utilisateur a la permission pour cette page spécifique
            if (is_array($permissions) && isset($permissions[$module])) {
                $module_permissions = $permissions[$module];
                
                if (is_array($module_permissions)) {
                    // Vérifier la page spécifique
                    if (isset($module_permissions[$page]) && is_array($module_permissions[$page])) {
                        return in_array($action, $module_permissions[$page]);
                    }
                    
                    // Si la page spécifique n'existe pas, refuser l'accès
                    // (être strict sur les permissions de pages)
                    return false;
                }
            }
        }
    } catch (Exception $e) {
        error_log("Erreur dans hasPagePermission: " . $e->getMessage());
    }
    
    // Fallback vers l'ancien système
    return checkPermission($module);
}

/**
 * Afficher un message d'erreur si l'utilisateur n'a pas la permission
 * 
 * @param string $module Module (ex: 'academic', 'students')
 * @param string $page Page (ex: 'index', 'add', 'edit')
 * @param string $action Action (ex: 'read', 'create', 'edit')
 * @param string $redirect_url URL de redirection
 */
function requirePagePermission($module, $page, $action = 'read', $redirect_url = null) {
    // Utiliser le nouveau système de vérification des permissions
    if (!hasPagePermission($module, $page, $action)) {
        if ($redirect_url) {
            showMessage('error', 'Vous n\'avez pas la permission d\'accéder à cette page.');
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
 * @param string $permission Permission à vérifier
 * @return bool True si l'utilisateur a la permission
 */
function checkPagePermissionCompat($permission) {
    // Utiliser l'ancien système de rôles
    if (strpos($permission, '/') !== false) {
        $module = explode('/', $permission)[0];
        return checkPermission($module);
    }
    
    if (strpos($permission, ':') !== false) {
        $module = explode(':', $permission)[0];
        return checkPermission($module);
    }
    
    return checkPermission($permission);
}

/**
 * Générer un tableau HTML des permissions par page
 * 
 * @param array $permissions Permissions à afficher
 * @return string HTML du tableau
 */
function renderPagePermissionsTable($permissions) {
    $actions = getAvailableActions();
    
    $html = '<div class="table-responsive">';
    $html .= '<table class="table table-bordered table-sm">';
    $html .= '<thead class="table-dark"><tr><th>Page</th>';
    
    foreach ($actions as $action_key => $action_label) {
        $html .= "<th class='text-center'>{$action_label}</th>";
    }
    
    $html .= '</tr></thead><tbody>';
    
    // Grouper par module
    $modules = [];
    foreach ($permissions as $page => $page_actions) {
        $module = explode('/', $page)[0];
        if (!isset($modules[$module])) {
            $modules[$module] = [];
        }
        $modules[$module][$page] = $page_actions;
    }
    
    foreach ($modules as $module => $module_pages) {
        $html .= "<tr class='table-secondary'><td colspan='" . (count($actions) + 1) . "'><strong>📁 {$module}</strong></td></tr>";
        
        foreach ($module_pages as $page => $page_actions) {
            $page_name = str_replace($module . '/', '', $page);
            $html .= "<tr><td class='ps-4'>{$page_name}</td>";
            
            foreach ($actions as $action_key => $action_label) {
                $has_permission = in_array($action_key, $page_actions);
                $class = $has_permission ? 'table-success' : 'table-light';
                $icon = $has_permission ? '✅' : '❌';
                
                $html .= "<td class='{$class} text-center'>{$icon}</td>";
            }
            
            $html .= '</tr>';
        }
    }
    
    $html .= '</tbody></table>';
    $html .= '</div>';
    
    return $html;
}

/**
 * Obtenir les statistiques des permissions d'un utilisateur
 * 
 * @param int $user_id ID de l'utilisateur
 * @return array Statistiques
 */
function getUserPermissionStats($user_id) {
    $permissions = getUserPagePermissions($user_id);
    $stats = [
        'total_pages' => 0,
        'total_actions' => 0,
        'modules' => []
    ];
    
    foreach ($permissions as $module => $module_permissions) {
        if (is_array($module_permissions)) {
            $module_stats = [
                'name' => $module,
                'pages' => 0,
                'actions' => 0
            ];
            
            foreach ($module_permissions as $page => $actions) {
                if (is_array($actions)) {
                    $stats['total_pages']++;
                    $module_stats['pages']++;
                    
                    // Compter les actions uniques
                    $unique_actions = array_unique($actions);
                    $stats['total_actions'] += count($unique_actions);
                    $module_stats['actions'] += count($unique_actions);
                }
            }
            
            $stats['modules'][] = $module_stats;
        }
    }
    
    return $stats;
}

/**
 * Vérifier si une page existe dans le système
 * 
 * @param string $page_path Chemin de la page
 * @return bool True si la page existe
 */
function pageExists($page_path) {
    return isset(PAGE_PERMISSIONS[$page_path]);
}

/**
 * Obtenir les actions disponibles pour une page
 * 
 * @param string $page_path Chemin de la page
 * @return array Actions disponibles
 */
function getPageActions($page_path) {
    return PAGE_PERMISSIONS[$page_path] ?? [];
}



/**
 * Obtenir la liste des pages d'un module
 * 
 * @param string $module Module
 * @return array Liste des pages du module
 */
function getModulePages($module) {
    $pages = [];
    
    foreach (PAGE_PERMISSIONS as $page => $actions) {
        if (strpos($page, $module . '/') === 0) {
            $pages[$page] = $actions;
        }
    }
    
    return $pages;
}

/**
 * Obtenir le nombre total de pages dans le système
 * 
 * @return int Nombre total de pages
 */
function getTotalPagesCount() {
    return count(PAGE_PERMISSIONS);
}

/**
 * Obtenir les statistiques globales du système
 * 
 * @return array Statistiques globales
 */
function getSystemStats() {
    $stats = [
        'total_pages' => getTotalPagesCount(),
        'modules' => [],
        'actions' => []
    ];
    
    foreach (PAGE_PERMISSIONS as $page => $actions) {
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
}

/**
 * Vérifier l'accès à la page actuelle et rediriger si nécessaire
 * DÉSACTIVÉ - Utilise l'ancien système de rôles
 * 
 * @param string $action Action requise (défaut: 'read')
 * @param string $redirect_url URL de redirection (défaut: dashboard)
 */
function requireCurrentPageAccess($action = 'read', $redirect_url = null) {
    // Extraire le module du script actuel
    $script_name = $_SERVER['SCRIPT_NAME'];
    if (preg_match('/modules\/([^\/]+)/', $script_name, $matches)) {
        $module = $matches[1];
        if (!checkPermission($module)) {
            $redirect_url = $redirect_url ?: '../dashboard.php';
            header("Location: " . $redirect_url);
            exit;
        }
    } else {
        // Si on ne peut pas déterminer le module, rediriger
        $redirect_url = $redirect_url ?: '../dashboard.php';
        header("Location: " . $redirect_url);
        exit;
    }
}

/**
 * Obtenir le chemin de la page actuelle
 * 
 * @return string|null Chemin de la page actuelle
 */
function getCurrentPagePath() {
    // Obtenir le script actuel
    $script_name = $_SERVER['SCRIPT_NAME'];
    
    // Extraire le chemin relatif
    $relative_path = str_replace('/educ-sinfinity/', '', $script_name);
    $relative_path = str_replace('\\', '/', $relative_path);
    
    // Convertir en format de page (sans extension et sans modules/)
    if (strpos($relative_path, 'modules/') === 0) {
        $page_path = substr($relative_path, 8); // Enlever 'modules/'
        $page_path = str_replace('.php', '', $page_path);
        
        // Ne plus supprimer le /index pour correspondre aux permissions
        return $page_path;
    }
    
    // Pour les autres pages (admin, etc.)
    $page_path = str_replace('.php', '', $relative_path);
    return $page_path;
}


/**
 * Vérifier les permissions d'une page en utilisant directement la base de données
 * Version optimisée qui évite les sessions et utilise les données de la table roles
 * 
 * @param string $module Nom du module
 * @param string $page Nom de la page
 * @param string $action Action à vérifier
 * @param string $subpage Nom de la sous-page (optionnel)
 * @return bool True si l'utilisateur a la permission, False sinon
 */
function hasPagePermissionFromDB($module, $page, $action, $subpage = null) {
    // Si pas d'utilisateur connecté, refuser l'accès
    if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
        return false;
    }
    
    global $database;
    
    try {
        // Récupérer le rôle de l'utilisateur
        $user = $database->query(
            "SELECT role_id FROM users WHERE id = ? AND status = 'actif'",
            [$_SESSION['user_id']]
        )->fetch();
        
        if (!$user) {
            return false;
        }
        
        // Récupérer les permissions du rôle
        $role = $database->query(
            "SELECT nom, permissions FROM roles WHERE id = ? AND actif = 1",
            [$user['role_id']]
        )->fetch();
        
        if (!$role || empty($role['permissions'])) {
            return false;
        }
        
        $permissions = json_decode($role['permissions'], true);
        if (!$permissions || !isset($permissions[$module])) {
            return false;
        }
        
        $module_permissions = $permissions[$module];
        
        // Construire la clé de page selon le format utilisé dans la base de données
        $page_key = $subpage ? "$page/$subpage" : $page;
        
        // Vérifier d'abord si le module a une structure 'pages'
        if (isset($module_permissions['pages'])) {
            $pages = $module_permissions['pages'];
            
            // Chercher la page dans le format "page/subpage"
            if (isset($pages[$page_key])) {
                $page_data = $pages[$page_key];
                
                // Vérifier les permissions de la page
                if (is_array($page_data)) {
                    // Format avec objet : {"name": "...", "permissions": ["read"]}
                    if (isset($page_data['permissions']) && is_array($page_data['permissions'])) {
                        return in_array($action, $page_data['permissions']);
                    }
                    // Format direct : ["read", "create"] (fallback)
                    elseif (!isset($page_data['name']) && !isset($page_data['pages'])) {
                        return in_array($action, $page_data);
                    }
                }
            }
            
            // Si la page exacte n'est pas trouvée, essayer de chercher avec la page seule
            if (!$subpage && isset($pages[$page])) {
                $page_data = $pages[$page];
                if (is_array($page_data)) {
                    if (isset($page_data['permissions']) && is_array($page_data['permissions'])) {
                        return in_array($action, $page_data['permissions']);
                    }
                }
            }
        }
        
        // Fallback: chercher dans la structure directe du module (ancien format)
        if (!isset($module_permissions['pages'])) {
            if (isset($module_permissions[$page])) {
                $page_data = $module_permissions[$page];
                
                if ($subpage) {
                    // Chercher dans les sous-pages
                    if (isset($page_data[$subpage])) {
                        $subpage_data = $page_data[$subpage];
                        if (is_array($subpage_data) && !isset($subpage_data['permissions'])) {
                            return in_array($action, $subpage_data);
                        } elseif (isset($subpage_data['permissions']) && is_array($subpage_data['permissions'])) {
                            return in_array($action, $subpage_data['permissions']);
                        }
                    }
                    // Structure 2: page_data a une propriété 'pages' qui contient les sous-pages
                    elseif (isset($page_data['pages'][$subpage])) {
                        $subpage_data = $page_data['pages'][$subpage];
                        if (is_array($subpage_data) && !isset($subpage_data['permissions'])) {
                            return in_array($action, $subpage_data);
                        } elseif (isset($subpage_data['permissions']) && is_array($subpage_data['permissions'])) {
                            return in_array($action, $subpage_data['permissions']);
                        }
                    }
                } else {
                    // Pour les pages sans sous-page, vérifier d'abord si la page a des permissions directes
                    if (is_array($page_data)) {
                        // Format direct : ["read", "create"]
                        if (!isset($page_data['permissions']) && !isset($page_data['pages'])) {
                            return in_array($action, $page_data);
                        }
                        // Format avec objet : {"name": "...", "permissions": ["read"]}
                        elseif (isset($page_data['permissions']) && is_array($page_data['permissions'])) {
                            return in_array($action, $page_data['permissions']);
                        }
                        // Si la page a des sous-pages mais pas de permissions directes,
                        // vérifier si l'utilisateur a accès à au moins une sous-page avec cette action
                        elseif (isset($page_data['pages']) && is_array($page_data['pages'])) {
                            foreach ($page_data['pages'] as $subpage_name => $subpage_data) {
                                if (is_array($subpage_data)) {
                                    // Vérifier les permissions de la sous-page
                                    if (isset($subpage_data['permissions']) && is_array($subpage_data['permissions'])) {
                                        if (in_array($action, $subpage_data['permissions'])) {
                                            return true; // L'utilisateur a la permission sur au moins une sous-page
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }
        
        return false;
        
    } catch (Exception $e) {
        error_log("Erreur lors de la vérification des permissions: " . $e->getMessage());
        return false;
    }
}

/**
 * Exiger les permissions d'une page en utilisant directement la base de données
 * Version optimisée qui évite les sessions et utilise les données de la table roles
 * 
 * @param string $module Nom du module
 * @param string $page Nom de la page
 * @param string $action Action requise
 * @param string $redirect_url URL de redirection en cas d'échec
 * @param string $subpage Nom de la sous-page (optionnel)
 * @return void
 */
function requirePagePermissionFromDB($module, $page, $action, $redirect_url = '../../../dashboard.php', $subpage = null) {
    // Vérifier d'abord la connexion
    requireLogin();
    
    // Vérifier les permissions
    if (!hasPagePermissionFromDB($module, $page, $action, $subpage)) {
        showMessage('error', 'Accès refusé. Vous n\'avez pas les permissions nécessaires.');
        redirectTo($redirect_url);
    }
}

?>
