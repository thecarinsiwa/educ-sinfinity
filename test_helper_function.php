<?php
require_once 'config/database.php';

// Fonction helper pour vérifier si une permission est cochée
function isPermissionChecked($current_permissions, $module_key, $page_key, $subpage_key = null, $action) {
    // Format nouveau : module:page:subpage:action
    if ($subpage_key !== null) {
        if (isset($current_permissions[$module_key][$page_key][$subpage_key]) && 
            in_array($action, $current_permissions[$module_key][$page_key][$subpage_key])) {
            return true;
        }
    }
    
    // Format ancien : module:page:action (pour compatibilité)
    if (isset($current_permissions[$module_key][$page_key]) && 
        in_array($action, $current_permissions[$module_key][$page_key])) {
        return true;
    }
    
    // Format ancien avec sous-pages : module:subpage:action
    if ($subpage_key !== null && isset($current_permissions[$module_key][$subpage_key]) && 
        in_array($action, $current_permissions[$module_key][$subpage_key])) {
        return true;
    }
    
    return false;
}

// Récupérer les permissions du rôle 20
$role = $database->query('SELECT permissions FROM roles WHERE id = 20')->fetch();
$current_permissions = json_decode($role['permissions'], true) ?: [];

echo "Permissions du rôle 20:\n";
print_r($current_permissions);

echo "\nTest de la fonction helper:\n";

// Test avec academic:classes/view:read
$result1 = isPermissionChecked($current_permissions, 'academic', 'classes/view', null, 'read');
echo "academic:classes/view:read -> " . ($result1 ? 'COCHÉ' : 'NON COCHÉ') . "\n";

// Test avec academic:classes/index:read
$result2 = isPermissionChecked($current_permissions, 'academic', 'classes/index', null, 'read');
echo "academic:classes/index:read -> " . ($result2 ? 'COCHÉ' : 'NON COCHÉ') . "\n";

// Test avec students:list:read
$result3 = isPermissionChecked($current_permissions, 'students', 'list', null, 'read');
echo "students:list:read -> " . ($result3 ? 'COCHÉ' : 'NON COCHÉ') . "\n";

// Test avec students:attendance/index:read
$result4 = isPermissionChecked($current_permissions, 'students', 'attendance/index', null, 'read');
echo "students:attendance/index:read -> " . ($result4 ? 'COCHÉ' : 'NON COCHÉ') . "\n";
?>
