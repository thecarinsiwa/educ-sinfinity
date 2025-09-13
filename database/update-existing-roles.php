<?php
/**
 * Script de mise à jour des rôles existants
 * Application de gestion scolaire - République Démocratique du Congo
 * 
 * Ce script met à jour les rôles existants avec la nouvelle structure de permissions
 */

require_once __DIR__ . '/../config/database.php';

// Structure complète des permissions par rôle
$role_permissions = [
    'admin' => [
        "users" => [
            "add" => ["create"],
            "edit" => ["edit"],
            "list" => ["read"],
            "view" => ["read"],
            "index" => ["read"],
            "logs/index" => ["read"],
            "roles/index" => ["admin"],
            "sessions/index" => ["read"],
            "roles/get-role-permissions" => ["read"]
        ],
        "students" => [
            "add" => ["create"],
            "edit" => ["edit"],
            "list" => ["read"],
            "view" => ["read"],
            "index" => ["read"],
            "search" => ["read"],
            "reports" => ["read", "export"],
            "enrollment" => ["create", "edit"],
            "transfers/view" => ["read"],
            "transfers/index" => ["read"],
            "transfers/process" => ["edit"],
            "transfers/new-exit" => ["create"],
            "transfers/new-transfer" => ["create"],
            "admissions/index" => ["read"],
            "admissions/new-application" => ["create"],
            "admissions/applications/add" => ["create"],
            "admissions/applications/edit" => ["edit"],
            "admissions/applications/view" => ["read"],
            "admissions/applications/index" => ["read"],
            "admissions/direct-enrollment" => ["create"],
            "admissions/settings/criteria" => ["admin"],
            "attendance/index" => ["read"],
            "attendance/edit" => ["edit"],
            "attendance/add-delay" => ["create"],
            "attendance/add-absence" => ["create"],
            "attendance/bulk-attendance" => ["create", "edit"],
            "attendance/justify-absence" => ["edit"]
        ],
        "academic" => [
            "classes/add" => ["create"],
            "classes/edit" => ["edit"],
            "classes/view" => ["read"],
            "classes/index" => ["read"],
            "classes/delete" => ["delete"],
            "subjects/add" => ["create"],
            "subjects/edit" => ["edit"],
            "subjects/view" => ["read"],
            "subjects/index" => ["read"],
            "subjects/delete" => ["delete"],
            "schedule/add" => ["create"],
            "schedule/edit" => ["edit"],
            "schedule/view" => ["read"],
            "schedule/index" => ["read"],
            "schedule/delete" => ["delete"],
            "years/add" => ["create"],
            "years/edit" => ["edit"],
            "years/view" => ["read"],
            "years/index" => ["read"],
            "years/delete" => ["delete"]
        ],
        "personnel" => [
            "add" => ["create"],
            "edit" => ["edit"],
            "view" => ["read"],
            "index" => ["read"],
            "delete" => ["delete"],
            "import" => ["import"],
            "export" => ["export"],
            "payroll" => ["read"],
            "payslip" => ["read"]
        ],
        "finance" => [
            "fees/add" => ["create"],
            "fees/edit" => ["edit"],
            "fees/view" => ["read"],
            "fees/index" => ["read"],
            "fees/delete" => ["delete"],
            "payments/add" => ["create"],
            "payments/edit" => ["edit"],
            "payments/view" => ["read"],
            "payments/index" => ["read"],
            "expenses/add" => ["create"],
            "expenses/edit" => ["edit"],
            "expenses/view" => ["read"],
            "expenses/index" => ["read"],
            "reports/financial" => ["read", "export"]
        ],
        "admin" => [
            "users/index" => ["read"],
            "users/add" => ["create"],
            "users/edit" => ["edit"],
            "roles/index" => ["read"],
            "roles/add" => ["create"],
            "roles/edit" => ["edit"],
            "settings/index" => ["admin"],
            "logs/index" => ["read"]
        ]
    ],
    'directeur' => [
        "students" => [
            "add" => ["create"],
            "edit" => ["edit"],
            "list" => ["read"],
            "view" => ["read"],
            "index" => ["read"],
            "search" => ["read"],
            "reports" => ["read", "export"],
            "enrollment" => ["create", "edit"],
            "transfers/view" => ["read"],
            "transfers/index" => ["read"],
            "transfers/process" => ["edit"],
            "admissions/index" => ["read"],
            "admissions/applications/view" => ["read"],
            "admissions/applications/index" => ["read"],
            "attendance/index" => ["read"],
            "attendance/justify-absence" => ["edit"]
        ],
        "academic" => [
            "classes/view" => ["read"],
            "classes/index" => ["read"],
            "subjects/view" => ["read"],
            "subjects/index" => ["read"],
            "schedule/view" => ["read"],
            "schedule/index" => ["read"],
            "years/view" => ["read"],
            "years/index" => ["read"]
        ],
        "personnel" => [
            "view" => ["read"],
            "index" => ["read"],
            "payroll" => ["read"],
            "payslip" => ["read"]
        ],
        "finance" => [
            "fees/view" => ["read"],
            "fees/index" => ["read"],
            "payments/view" => ["read"],
            "payments/index" => ["read"],
            "expenses/view" => ["read"],
            "expenses/index" => ["read"],
            "reports/financial" => ["read", "export"]
        ],
        "reports" => [
            "academic/index" => ["read"],
            "financial/index" => ["read"],
            "administrative/index" => ["read"]
        ]
    ],
    'secretaire' => [
        "students" => [
            "add" => ["create"],
            "edit" => ["edit"],
            "list" => ["read"],
            "view" => ["read"],
            "index" => ["read"],
            "search" => ["read"],
            "enrollment" => ["create", "edit"],
            "transfers/view" => ["read"],
            "transfers/index" => ["read"],
            "admissions/index" => ["read"],
            "admissions/new-application" => ["create"],
            "admissions/applications/add" => ["create"],
            "admissions/applications/view" => ["read"],
            "admissions/applications/index" => ["read"],
            "admissions/direct-enrollment" => ["create"],
            "attendance/index" => ["read"],
            "attendance/add-delay" => ["create"],
            "attendance/add-absence" => ["create"]
        ],
        "academic" => [
            "classes/view" => ["read"],
            "classes/index" => ["read"],
            "subjects/view" => ["read"],
            "subjects/index" => ["read"]
        ],
        "finance" => [
            "fees/view" => ["read"],
            "fees/index" => ["read"],
            "payments/view" => ["read"],
            "payments/index" => ["read"]
        ]
    ],
    'enseignant' => [
        "students" => [
            "list" => ["read"],
            "view" => ["read"],
            "index" => ["read"],
            "search" => ["read"],
            "attendance/index" => ["read"],
            "attendance/add-delay" => ["create"],
            "attendance/add-absence" => ["create"],
            "attendance/bulk-attendance" => ["create", "edit"]
        ],
        "academic" => [
            "classes/view" => ["read"],
            "classes/index" => ["read"],
            "subjects/view" => ["read"],
            "subjects/index" => ["read"],
            "schedule/view" => ["read"],
            "schedule/index" => ["read"]
        ]
    ],
    'comptable' => [
        "students" => [
            "view" => ["read"],
            "index" => ["read"],
            "search" => ["read"]
        ],
        "finance" => [
            "fees/add" => ["create"],
            "fees/edit" => ["edit"],
            "fees/view" => ["read"],
            "fees/index" => ["read"],
            "payments/add" => ["create"],
            "payments/edit" => ["edit"],
            "payments/view" => ["read"],
            "payments/index" => ["read"],
            "expenses/add" => ["create"],
            "expenses/edit" => ["edit"],
            "expenses/view" => ["read"],
            "expenses/index" => ["read"],
            "reports/financial" => ["read", "export"]
        ]
    ],
    'surveillant' => [
        "students" => [
            "view" => ["read"],
            "index" => ["read"],
            "attendance/index" => ["read"],
            "attendance/add-delay" => ["create"],
            "attendance/add-absence" => ["create"]
        ]
    ],
    'bibliothecaire' => [
        "library" => [
            "books/add" => ["create"],
            "books/edit" => ["edit"],
            "books/view" => ["read"],
            "books/index" => ["read"],
            "books/delete" => ["delete"],
            "loans/add" => ["create"],
            "loans/edit" => ["edit"],
            "loans/view" => ["read"],
            "loans/index" => ["read"],
            "loans/delete" => ["delete"],
            "reservations/index" => ["read"]
        ]
    ],
    'parent' => [
        "students" => [
            "view" => ["read"],
            "attendance/index" => ["read"],
            "reports" => ["read"]
        ]
    ],
    'eleve' => [
        "students" => [
            "view" => ["read"]
        ]
    ],
    'huitier' => [
        "students" => [
            "add" => ["create"],
            "view" => ["read"],
            "index" => ["read"],
            "search" => ["read"],
            "attendance/index" => ["read"],
            "attendance/add-delay" => ["create"],
            "attendance/add-absence" => ["create"]
        ]
    ]
];

try {
    echo "🚀 Mise à jour des rôles existants avec la nouvelle structure de permissions...\n\n";
    
    $database = new Database();
    $updated_count = 0;
    $skipped_count = 0;
    
    // Récupérer tous les rôles existants
    $existing_roles = $database->query("SELECT id, nom FROM roles WHERE actif = 1");
    
    while ($role = $existing_roles->fetch()) {
        $role_name = strtolower($role['nom']);
        
        if (isset($role_permissions[$role_name])) {
            // Convertir les permissions en JSON
            $permissions_json = json_encode($role_permissions[$role_name], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            
            // Mettre à jour le rôle
            $sql = "UPDATE roles SET permissions = ?, date_modification = NOW() WHERE id = ?";
            $result = $database->execute($sql, [$permissions_json, $role['id']]);
            
            if ($result) {
                echo "✅ Rôle '{$role['nom']}' mis à jour avec succès\n";
                $updated_count++;
                
                // Afficher quelques statistiques
                $module_count = count($role_permissions[$role_name]);
                $page_count = 0;
                foreach ($role_permissions[$role_name] as $pages) {
                    $page_count += count($pages);
                }
                echo "   📊 Modules: $module_count, Pages: $page_count\n";
            } else {
                echo "❌ Erreur lors de la mise à jour du rôle '{$role['nom']}'\n";
            }
        } else {
            echo "⏭️  Aucune configuration trouvée pour le rôle '{$role['nom']}', ignoré\n";
            $skipped_count++;
        }
    }
    
    echo "\n📊 Résumé de la mise à jour :\n";
    echo "   - Rôles mis à jour: $updated_count\n";
    echo "   - Rôles ignorés: $skipped_count\n";
    
    // Afficher un exemple de permissions pour un rôle
    if ($updated_count > 0) {
        echo "\n🔍 Exemple de permissions pour le rôle 'admin' :\n";
        $admin_role = $database->query("SELECT permissions FROM roles WHERE nom = 'admin'")->fetch();
        if ($admin_role) {
            $permissions = json_decode($admin_role['permissions'], true);
            echo json_encode(array_slice($permissions, 0, 2, true), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            echo "\n... (structure complète dans la base de données)\n";
        }
    }
    
    echo "\n💡 Prochaines étapes :\n";
    echo "   1. Testez les permissions avec différents utilisateurs\n";
    echo "   2. Vérifiez que les boutons se désactivent correctement\n";
    echo "   3. Ajustez les permissions selon vos besoins\n\n";
    
} catch (Exception $e) {
    echo "❌ Erreur: " . $e->getMessage() . "\n";
    echo "   Fichier: " . $e->getFile() . "\n";
    echo "   Ligne: " . $e->getLine() . "\n";
}

echo "\n🎯 Script terminé.\n";
?>
