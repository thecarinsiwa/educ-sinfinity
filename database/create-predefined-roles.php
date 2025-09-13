<?php
/**
 * Script de création de rôles prédéfinis
 * Application de gestion scolaire - République Démocratique du Congo
 * 
 * Ce script crée des rôles avec différents niveaux d'accès
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

// Rôles prédéfinis avec leurs permissions
$predefined_roles = [
    [
        'nom' => 'Super Administrateur',
        'description' => 'Accès complet à toutes les fonctionnalités du système',
        'permissions' => [
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
                "schedule/generate" => ["create"],
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
        ]
    ],
    [
        'nom' => 'Directeur',
        'description' => 'Accès de direction avec permissions étendues',
        'permissions' => [
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
        ]
    ],
    [
        'nom' => 'Secrétaire',
        'description' => 'Accès aux fonctions administratives et d\'inscription',
        'permissions' => [
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
        ]
    ],
    [
        'nom' => 'Enseignant',
        'description' => 'Accès aux fonctions pédagogiques',
        'permissions' => [
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
            ],
            "evaluations" => [
                "add" => ["create"],
                "edit" => ["edit"],
                "view" => ["read"],
                "index" => ["read"]
            ]
        ]
    ],
    [
        'nom' => 'Comptable',
        'description' => 'Accès aux fonctions financières',
        'permissions' => [
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
        ]
    ],
    [
        'nom' => 'Surveillant',
        'description' => 'Accès limité aux fonctions de surveillance',
        'permissions' => [
            "students" => [
                "view" => ["read"],
                "index" => ["read"],
                "attendance/index" => ["read"],
                "attendance/add-delay" => ["create"],
                "attendance/add-absence" => ["create"]
            ]
        ]
    ],
    [
        'nom' => 'Parent',
        'description' => 'Accès en lecture seule pour les parents',
        'permissions' => [
            "students" => [
                "view" => ["read"],
                "attendance/index" => ["read"],
                "reports" => ["read"]
            ]
        ]
    ]
];

try {
    echo "🚀 Création des rôles prédéfinis...\n\n";
    
    // Vérifier si la table roles existe
    $check_table = $database->query("SHOW TABLES LIKE 'roles'");
    if ($check_table->rowCount() == 0) {
        echo "❌ La table 'roles' n'existe pas. Veuillez d'abord créer la table.\n";
        exit(1);
    }
    
    $created_count = 0;
    $skipped_count = 0;
    
    foreach ($predefined_roles as $role_data) {
        // Vérifier si le rôle existe déjà
        $existing_role = $database->query(
            "SELECT id FROM roles WHERE nom = ?",
            [$role_data['nom']]
        )->fetch();
        
        if ($existing_role) {
            echo "⏭️  Rôle '{$role_data['nom']}' existe déjà, ignoré.\n";
            $skipped_count++;
            continue;
        }
        
        // Convertir les permissions en JSON
        $permissions_json = json_encode($role_data['permissions'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        
        // Insérer le nouveau rôle
        $sql = "INSERT INTO roles (nom, description, permissions, actif, date_creation) VALUES (?, ?, ?, 1, NOW())";
        $result = $database->execute($sql, [
            $role_data['nom'],
            $role_data['description'],
            $permissions_json
        ]);
        
        if ($result) {
            $role_id = $database->lastInsertId();
            echo "✅ Rôle créé: '{$role_data['nom']}' (ID: $role_id)\n";
            $created_count++;
        } else {
            echo "❌ Erreur lors de la création du rôle: '{$role_data['nom']}'\n";
        }
    }
    
    echo "\n📊 Résumé :\n";
    echo "   - Rôles créés: $created_count\n";
    echo "   - Rôles ignorés: $skipped_count\n";
    echo "   - Total traités: " . count($predefined_roles) . "\n\n";
    
    // Afficher tous les rôles existants
    echo "📋 Rôles existants dans la base de données :\n";
    $all_roles = $database->query("SELECT id, nom, description, actif FROM roles ORDER BY nom")->fetchAll();
    
    foreach ($all_roles as $role) {
        $status = $role['actif'] ? '✅ Actif' : '❌ Inactif';
        echo "   - ID {$role['id']}: {$role['nom']} - {$role['description']} ($status)\n";
    }
    
    echo "\n💡 Pour assigner un rôle à un utilisateur :\n";
    echo "   UPDATE users SET role_id = [ID_DU_ROLE] WHERE id = [ID_UTILISATEUR];\n\n";
    
} catch (Exception $e) {
    echo "❌ Erreur: " . $e->getMessage() . "\n";
    echo "   Fichier: " . $e->getFile() . "\n";
    echo "   Ligne: " . $e->getLine() . "\n";
}

echo "\n🎯 Script terminé.\n";
?>
