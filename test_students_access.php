<?php
/**
 * Test spécifique pour l'accès au module students
 */

require_once 'config/config.php';
require_once 'config/database.php';
require_once 'includes/functions.php';
require_once 'includes/permissions-pages.php';

// Démarrer la session
session_start();

if (!isset($_SESSION['user_id'])) {
    echo "<h2>❌ Aucun utilisateur connecté</h2>";
    echo "<p>Veuillez vous connecter d'abord.</p>";
    echo "<a href='auth/login.php'>Se connecter</a>";
    exit;
}

echo "<h2>🧪 Test d'accès au module Students</h2>";
echo "<p><strong>Utilisateur:</strong> " . ($_SESSION['username'] ?? 'Inconnu') . "</p>";

// Test des permissions pour différentes pages du module students
$pages_to_test = [
    ['page' => 'index', 'action' => 'read', 'description' => 'Accueil du module'],
    ['page' => 'add', 'action' => 'create', 'description' => 'Ajouter un élève'],
    ['page' => 'list', 'action' => 'read', 'description' => 'Liste des élèves'],
    ['page' => 'view', 'action' => 'read', 'description' => 'Voir un élève'],
    ['page' => 'edit', 'action' => 'edit', 'description' => 'Modifier un élève']
];

echo "<h3>📋 Test des permissions par page</h3>";
echo "<table border='1' cellpadding='5' cellspacing='0'>";
echo "<tr><th>Page</th><th>Action</th><th>Description</th><th>hasPagePermission()</th><th>checkPermission()</th></tr>";

foreach ($pages_to_test as $test) {
    $has_permission = hasPagePermission('students', $test['page'], $test['action']);
    $has_old_permission = checkPermission('students');
    
    echo "<tr>";
    echo "<td>" . htmlspecialchars($test['page']) . "</td>";
    echo "<td>" . htmlspecialchars($test['action']) . "</td>";
    echo "<td>" . htmlspecialchars($test['description']) . "</td>";
    echo "<td>" . ($has_permission ? '✅ Oui' : '❌ Non') . "</td>";
    echo "<td>" . ($has_old_permission ? '✅ Oui' : '❌ Non') . "</td>";
    echo "</tr>";
}

echo "</table>";

// Afficher les permissions détaillées pour le module students
echo "<h3>🔍 Permissions détaillées pour le module 'students'</h3>";

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
        
        if ($permissions && isset($permissions['students'])) {
            echo "<h4>✅ Permissions trouvées pour le module 'students':</h4>";
            echo "<ul>";
            
            $students_permissions = $permissions['students'];
            if (is_array($students_permissions)) {
                foreach ($students_permissions as $page => $actions) {
                    if (is_array($actions)) {
                        echo "<li><strong>" . htmlspecialchars($page) . ":</strong> " . htmlspecialchars(implode(', ', $actions)) . "</li>";
                    }
                }
            }
            echo "</ul>";
        } else {
            echo "<p>❌ Aucune permission trouvée pour le module 'students'</p>";
            echo "<p><strong>Modules disponibles:</strong></p>";
            echo "<ul>";
            foreach ($permissions as $module => $module_permissions) {
                echo "<li>" . htmlspecialchars($module) . "</li>";
            }
            echo "</ul>";
        }
    } else {
        echo "<p>❌ Aucune permission configurée</p>";
    }
} catch (Exception $e) {
    echo "<p>❌ Erreur: " . htmlspecialchars($e->getMessage()) . "</p>";
}

// Test de simulation de requirePagePermission
echo "<h3>🎯 Simulation de requirePagePermission</h3>";

$test_pages = ['index', 'add', 'list', 'view'];
foreach ($test_pages as $page) {
    $can_access = hasPagePermission('students', $page, 'read');
    echo "<p><strong>students/{$page}:</strong> " . ($can_access ? '✅ Accès autorisé' : '❌ Accès refusé') . "</p>";
}

echo "<hr>";
echo "<h3>🔗 Actions</h3>";
echo "<p><a href='modules/students/index.php'>→ Essayer d'accéder au module students</a></p>";
echo "<p><a href='test_sidebar.php'>→ Tester le sidebar</a></p>";
echo "<p><a href='debug_permissions.php'>→ Debug complet</a></p>";
echo "<p><a href='dashboard.php'>→ Tableau de bord</a></p>";
?>
