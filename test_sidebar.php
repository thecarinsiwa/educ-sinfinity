<?php
/**
 * Test rapide pour vérifier le sidebar
 */

require_once 'config/config.php';
require_once 'config/database.php';
require_once 'includes/functions.php';
require_once 'includes/permissions.php';

// Démarrer la session
session_start();

// Simuler une connexion pour test
if (!isset($_SESSION['user_id'])) {
    echo "<h2>Test du système de permissions</h2>";
    echo "<p>Veuillez vous connecter d'abord.</p>";
    echo "<a href='auth/login.php'>Se connecter</a>";
    exit;
}

echo "<h2>🧪 Test du Sidebar - Utilisateur: " . ($_SESSION['username'] ?? 'Inconnu') . "</h2>";

// Tester les modules principaux
$modules = ['students', 'finance', 'academic', 'evaluations', 'personnel'];

echo "<h3>📋 Tests des permissions par module</h3>";
echo "<table border='1' cellpadding='5' cellspacing='0'>";
echo "<tr><th>Module</th><th>checkPermission()</th><th>checkModuleAccess()</th><th>Sidebar visible?</th></tr>";

foreach ($modules as $module) {
    $permission_result = checkPermission($module);
    $module_access_result = checkModuleAccess($module);
    $sidebar_visible = $module_access_result ? '✅ Oui' : '❌ Non';
    
    echo "<tr>";
    echo "<td><strong>" . htmlspecialchars($module) . "</strong></td>";
    echo "<td>" . ($permission_result ? '✅ Oui' : '❌ Non') . "</td>";
    echo "<td>" . ($module_access_result ? '✅ Oui' : '❌ Non') . "</td>";
    echo "<td>" . $sidebar_visible . "</td>";
    echo "</tr>";
}
echo "</table>";

// Afficher les permissions détaillées
echo "<h3>🔍 Permissions détaillées</h3>";

try {
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
        
        if ($permissions) {
            echo "<table border='1' cellpadding='5' cellspacing='0'>";
            echo "<tr><th>Module</th><th>Pages avec permissions</th></tr>";
            
            foreach ($permissions as $module => $module_permissions) {
                if (is_array($module_permissions)) {
                    $pages = [];
                    foreach ($module_permissions as $page => $actions) {
                        if (is_array($actions) && !empty($actions)) {
                            $pages[] = $page . ' (' . implode(', ', $actions) . ')';
                        }
                    }
                    echo "<tr>";
                    echo "<td><strong>" . htmlspecialchars($module) . "</strong></td>";
                    echo "<td>" . (empty($pages) ? 'Aucune' : implode('<br>', $pages)) . "</td>";
                    echo "</tr>";
                }
            }
            echo "</table>";
        } else {
            echo "<p>❌ Erreur de décodage JSON</p>";
        }
    } else {
        echo "<p>❌ Aucune permission trouvée</p>";
    }
} catch (Exception $e) {
    echo "<p>❌ Erreur: " . htmlspecialchars($e->getMessage()) . "</p>";
}

echo "<hr>";
echo "<h3>🔧 Actions de correction</h3>";
echo "<p><a href='debug_permissions.php'>Voir le debug complet</a></p>";
echo "<p><a href='admin/roles.php'>Gérer les rôles</a></p>";
echo "<p><a href='dashboard.php'>Retour au tableau de bord</a></p>";
?>
