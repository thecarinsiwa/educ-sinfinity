<?php
/**
 * Test de la structure complète des permissions
 */

require_once 'config/module-permissions-structure.php';

echo "<h2>📊 Test de la Structure Complète des Permissions</h2>\n";

// Test 1: Vérification des statistiques
echo "<h3>📈 Statistiques Générales</h3>\n";
$stats = getPermissionsStats();
echo "<pre>\n";
echo "Total des modules: {$stats['total_modules']}\n";
echo "Total des pages: {$stats['total_pages']}\n";
echo "Total des actions: {$stats['total_actions']}\n";
echo "</pre>\n";

// Test 2: Liste des modules avec leurs pages
echo "<h3>📋 Modules et Pages</h3>\n";
echo "<pre>\n";
foreach ($stats['modules'] as $module_key => $module_info) {
    echo "📁 {$module_info['name']} ({$module_key}): {$module_info['pages']} pages\n";
}
echo "</pre>\n";

// Test 3: Vérification des actions disponibles
echo "<h3>⚙️ Actions Disponibles</h3>\n";
echo "<pre>\n";
foreach (AVAILABLE_ACTIONS as $key => $label) {
    echo "• {$key}: {$label}\n";
}
echo "</pre>\n";

// Test 4: Test des fonctions utilitaires
echo "<h3>🔧 Test des Fonctions Utilitaires</h3>\n";

// Test moduleExists
echo "<h4>Vérification d'existence des modules:</h4>\n";
$test_modules = ['academic', 'students', 'finance', 'inexistant'];
foreach ($test_modules as $module) {
    $exists = moduleExists($module);
    echo "• {$module}: " . ($exists ? "✅ Existe" : "❌ N'existe pas") . "\n";
}

// Test pageExists
echo "<h4>Vérification d'existence des pages:</h4>\n";
$test_pages = [
    ['academic', 'index'],
    ['students', 'attendance/index'],
    ['finance', 'payments/add'],
    ['inexistant', 'test']
];
foreach ($test_pages as [$module, $page]) {
    $exists = pageExists($module, $page);
    echo "• {$module}/{$page}: " . ($exists ? "✅ Existe" : "❌ N'existe pas") . "\n";
}

// Test getPageActions
echo "<h4>Actions disponibles pour les pages:</h4>\n";
foreach ($test_pages as [$module, $page]) {
    $actions = getPageActions($module, $page);
    if (!empty($actions)) {
        echo "• {$module}/{$page}: " . implode(', ', $actions) . "\n";
    }
}

// Test 5: Simulation d'un rôle avec permissions
echo "<h3>👤 Test de Rôle avec Permissions</h3>\n";

$test_permissions_json = '{
    "users": {
        "name": "Utilisateurs",
        "pages": {
            "index": {"name": "Index", "permissions": ["read"]},
            "add": {"name": "Ajouter", "permissions": ["create"]},
            "roles": {
                "name": "Rôles",
                "pages": {
                    "index": {"name": "Index", "permissions": ["read"]},
                    "manage": {"name": "Gérer", "permissions": ["edit"]}
                }
            }
        }
    },
    "students": {
        "name": "Gestion des Élèves",
        "pages": {
            "index": {"name": "Index", "permissions": ["read"]},
            "attendance/index": {"name": "Présences", "permissions": ["read", "create"]}
        }
    }
}';

$summary = getRolePermissionsSummary($test_permissions_json);
echo "<pre>\n";
echo "Résumé du rôle de test:\n";
echo "- Total permissions: {$summary['total_permissions']}\n";
echo "- Total modules: {$summary['total_modules']}\n";
echo "- Détail par module:\n";
foreach ($summary['modules'] as $module_key => $module_info) {
    echo "  • {$module_info['name']}: {$module_info['permissions']} permissions\n";
}
echo "</pre>\n";

// Test 6: Aperçu des permissions
echo "<h3>👁️ Aperçu des Permissions</h3>\n";
$preview = generatePermissionsPreview($test_permissions_json, 3);
echo "<p>{$preview}</p>\n";

// Test 7: Validation de la structure
echo "<h3>✅ Validation de la Structure</h3>\n";
echo "<ul>\n";

$errors = [];
$warnings = [];

// Vérifier que tous les modules ont un nom et une icône
foreach (MODULE_PERMISSIONS_STRUCTURE as $module_key => $module) {
    if (empty($module['name'])) {
        $errors[] = "Module {$module_key} n'a pas de nom";
    }
    if (empty($module['icon'])) {
        $errors[] = "Module {$module_key} n'a pas d'icône";
    }
    if (empty($module['pages'])) {
        $warnings[] = "Module {$module_key} n'a pas de pages";
    }
}

if (empty($errors)) {
    echo "<li>✅ Tous les modules ont un nom et une icône</li>\n";
} else {
    foreach ($errors as $error) {
        echo "<li>❌ {$error}</li>\n";
    }
}

if (empty($warnings)) {
    echo "<li>✅ Tous les modules ont des pages</li>\n";
} else {
    foreach ($warnings as $warning) {
        echo "<li>⚠️ {$warning}</li>\n";
    }
}

// Vérifier les actions
$valid_actions = array_keys(AVAILABLE_ACTIONS);
$invalid_actions = [];

foreach (MODULE_PERMISSIONS_STRUCTURE as $module_key => $module) {
    foreach ($module['pages'] as $page_key => $actions) {
        foreach ($actions as $action) {
            if (!in_array($action, $valid_actions)) {
                $invalid_actions[] = "{$module_key}/{$page_key}: {$action}";
            }
        }
    }
}

if (empty($invalid_actions)) {
    echo "<li>✅ Toutes les actions sont valides</li>\n";
} else {
    foreach ($invalid_actions as $invalid) {
        echo "<li>❌ Action invalide: {$invalid}</li>\n";
    }
}

echo "</ul>\n";

echo "<h3>🎉 Résultat Final</h3>\n";
if (empty($errors) && empty($invalid_actions)) {
    echo "<div class='alert alert-success'>\n";
    echo "<h4>✅ Structure Complètement Validée !</h4>\n";
    echo "<p>La structure des permissions est complète et prête pour la production.</p>\n";
    echo "<ul>\n";
    echo "<li><strong>{$stats['total_modules']}</strong> modules configurés</li>\n";
    echo "<li><strong>{$stats['total_pages']}</strong> pages avec permissions</li>\n";
    echo "<li><strong>{$stats['total_actions']}</strong> actions disponibles</li>\n";
    echo "</ul>\n";
    echo "</div>\n";
} else {
    echo "<div class='alert alert-danger'>\n";
    echo "<h4>❌ Des erreurs ont été détectées</h4>\n";
    echo "<p>Veuillez corriger les erreurs avant d'utiliser la structure en production.</p>\n";
    echo "</div>\n";
}
?>
