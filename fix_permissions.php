<?php
/**
 * Script de correction des permissions
 */

require_once 'config/config.php';
require_once 'config/database.php';
require_once 'includes/functions.php';

// Démarrer la session
session_start();

// Vérifier si l'utilisateur est admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'Administrateur') {
    echo "<h2>❌ Accès refusé</h2>";
    echo "<p>Seuls les administrateurs peuvent utiliser ce script.</p>";
    exit;
}

echo "<h2>🔧 Correction des Permissions</h2>";

try {
    // Récupérer tous les rôles
    $stmt = $database->query("SELECT * FROM roles WHERE actif = 1");
    $roles = $stmt->fetchAll();
    
    echo "<h3>📋 Rôles trouvés : " . count($roles) . "</h3>";
    
    foreach ($roles as $role) {
        echo "<h4>🔍 Rôle: " . htmlspecialchars($role['nom']) . "</h4>";
        
        if ($role['permissions']) {
            $permissions = json_decode($role['permissions'], true);
            
            if ($permissions) {
                echo "<p>✅ Permissions valides trouvées</p>";
                
                // Compter les modules avec permissions
                $modules_count = 0;
                foreach ($permissions as $module => $module_permissions) {
                    if (is_array($module_permissions) && !empty($module_permissions)) {
                        $modules_count++;
                    }
                }
                
                echo "<p>📊 Modules avec permissions: " . $modules_count . "</p>";
                
                // Lister les modules
                echo "<ul>";
                foreach ($permissions as $module => $module_permissions) {
                    if (is_array($module_permissions) && !empty($module_permissions)) {
                        $pages_count = 0;
                        foreach ($module_permissions as $page => $actions) {
                            if (is_array($actions) && !empty($actions)) {
                                $pages_count++;
                            }
                        }
                        echo "<li><strong>" . htmlspecialchars($module) . "</strong> - " . $pages_count . " page(s)</li>";
                    }
                }
                echo "</ul>";
                
            } else {
                echo "<p>❌ Erreur de décodage JSON</p>";
                echo "<pre>" . htmlspecialchars($role['permissions']) . "</pre>";
            }
        } else {
            echo "<p>❌ Aucune permission configurée</p>";
        }
        
        echo "<hr>";
    }
    
    // Vérifier les utilisateurs avec ce rôle
    echo "<h3>👥 Utilisateurs avec le rôle 'Huitier'</h3>";
    
    $stmt = $database->query(
        "SELECT u.username, u.status, r.nom as role_nom, r.actif as role_actif
         FROM users u 
         JOIN roles r ON u.role_id = r.id 
         WHERE r.nom = 'Huitier'"
    );
    $users = $stmt->fetchAll();
    
    if ($users) {
        echo "<table border='1' cellpadding='5' cellspacing='0'>";
        echo "<tr><th>Utilisateur</th><th>Status</th><th>Rôle</th><th>Rôle actif</th></tr>";
        
        foreach ($users as $user) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($user['username']) . "</td>";
            echo "<td>" . htmlspecialchars($user['status']) . "</td>";
            echo "<td>" . htmlspecialchars($user['role_nom']) . "</td>";
            echo "<td>" . ($user['role_actif'] ? '✅ Oui' : '❌ Non') . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p>❌ Aucun utilisateur trouvé avec le rôle 'Huitier'</p>";
    }
    
} catch (Exception $e) {
    echo "<p>❌ Erreur: " . htmlspecialchars($e->getMessage()) . "</p>";
}

echo "<hr>";
echo "<h3>🔗 Liens utiles</h3>";
echo "<p><a href='test_sidebar.php'>Tester le sidebar</a></p>";
echo "<p><a href='debug_permissions.php'>Debug complet</a></p>";
echo "<p><a href='admin/roles.php'>Gérer les rôles</a></p>";
echo "<p><a href='dashboard.php'>Tableau de bord</a></p>";
?>
