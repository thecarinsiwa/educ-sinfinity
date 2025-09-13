<?php
/**
 * Initialisation Avancée des Rôles - Educ-Sinfinity
 * Système de gestion scolaire - République Démocratique du Congo
 * 
 * Ce script crée un système de rôles avancé basé sur l'analyse complète
 * des modules et sous-modules du système.
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/detailed-permissions.php';

// Fonction pour générer les permissions JSON pour un rôle
function generateRolePermissions($role_name) {
    $permissions = [];
    
    switch ($role_name) {
        case 'admin':
            // Administrateur : Accès complet à tous les modules
            $permissions = [
                'students' => ['read', 'create', 'edit', 'delete'],
                'academic' => ['read', 'create', 'edit', 'delete'],
                'finance' => ['read', 'create', 'edit', 'delete'],
                'evaluations' => ['read', 'create', 'edit', 'delete'],
                'personnel' => ['read', 'create', 'edit', 'delete'],
                'communication' => ['read', 'create', 'edit', 'delete'],
                'reports' => ['read', 'create', 'edit', 'delete'],
                'users' => ['read', 'create', 'edit', 'delete'],
                'library' => ['read', 'create', 'edit', 'delete'],
                'discipline' => ['read', 'create', 'edit', 'delete'],
                'cartes_eleves' => ['read', 'create', 'edit', 'delete'],
                'recouvrement' => ['read', 'create', 'edit', 'delete'],
                'admissions' => ['read', 'create', 'edit', 'delete'],
                'settings' => ['read', 'create', 'edit', 'delete']
            ];
            break;
            
        case 'directeur':
            // Directeur : Accès étendu sauf gestion des utilisateurs
            $permissions = [
                'students' => ['read', 'create', 'edit'],
                'academic' => ['read', 'create', 'edit', 'delete'],
                'finance' => ['read', 'create', 'edit'],
                'evaluations' => ['read', 'create', 'edit', 'delete'],
                'personnel' => ['read', 'create', 'edit'],
                'communication' => ['read', 'create', 'edit'],
                'reports' => ['read', 'create', 'edit'],
                'users' => ['read'],
                'library' => ['read', 'create', 'edit'],
                'discipline' => ['read', 'create', 'edit', 'delete'],
                'cartes_eleves' => ['read', 'create', 'edit'],
                'recouvrement' => ['read', 'create', 'edit'],
                'admissions' => ['read', 'create', 'edit', 'delete'],
                'settings' => ['read']
            ];
            break;
            
        case 'enseignant':
            // Enseignant : Accès aux modules pédagogiques et élèves
            $permissions = [
                'students' => ['read', 'edit'],
                'academic' => ['read', 'create', 'edit'],
                'finance' => [],
                'evaluations' => ['read', 'create', 'edit', 'delete'],
                'personnel' => [],
                'communication' => ['read', 'create'],
                'reports' => ['read'],
                'users' => [],
                'library' => ['read', 'create'],
                'discipline' => ['read', 'create'],
                'cartes_eleves' => ['read'],
                'recouvrement' => [],
                'admissions' => ['read'],
                'settings' => []
            ];
            break;
            
        case 'secretaire':
            // Secrétaire : Accès aux modules administratifs
            $permissions = [
                'students' => ['read', 'create', 'edit'],
                'academic' => ['read', 'create'],
                'finance' => ['read'],
                'evaluations' => ['read'],
                'personnel' => ['read'],
                'communication' => ['read', 'create', 'edit'],
                'reports' => ['read', 'create'],
                'users' => ['read'],
                'library' => ['read', 'create'],
                'discipline' => ['read', 'create'],
                'cartes_eleves' => ['read', 'create'],
                'recouvrement' => ['read'],
                'admissions' => ['read', 'create', 'edit'],
                'settings' => []
            ];
            break;
            
        case 'comptable':
            // Comptable : Accès complet au module financier
            $permissions = [
                'students' => ['read'],
                'academic' => [],
                'finance' => ['read', 'create', 'edit', 'delete'],
                'evaluations' => [],
                'personnel' => [],
                'communication' => ['read'],
                'reports' => ['read', 'create', 'edit'],
                'users' => [],
                'library' => [],
                'discipline' => [],
                'cartes_eleves' => ['read'],
                'recouvrement' => ['read', 'create', 'edit'],
                'admissions' => ['read'],
                'settings' => []
            ];
            break;
            
        case 'surveillant':
            // Surveillant : Accès aux modules de discipline et présence
            $permissions = [
                'students' => ['read', 'edit'],
                'academic' => ['read'],
                'finance' => [],
                'evaluations' => [],
                'personnel' => [],
                'communication' => ['read'],
                'reports' => ['read'],
                'users' => [],
                'library' => [],
                'discipline' => ['read', 'create', 'edit'],
                'cartes_eleves' => ['read'],
                'recouvrement' => ['read'],
                'admissions' => [],
                'settings' => []
            ];
            break;
            
        case 'bibliothecaire':
            // Bibliothécaire : Accès complet au module bibliothèque
            $permissions = [
                'students' => ['read'],
                'academic' => [],
                'finance' => [],
                'evaluations' => [],
                'personnel' => [],
                'communication' => ['read'],
                'reports' => ['read'],
                'users' => [],
                'library' => ['read', 'create', 'edit', 'delete'],
                'discipline' => [],
                'cartes_eleves' => ['read'],
                'recouvrement' => [],
                'admissions' => [],
                'settings' => []
            ];
            break;
            
        case 'parent':
            // Parent : Accès limité aux informations de ses enfants
            $permissions = [
                'students' => ['read'],
                'academic' => [],
                'finance' => ['read'],
                'evaluations' => ['read'],
                'personnel' => [],
                'communication' => ['read'],
                'reports' => ['read'],
                'users' => [],
                'library' => [],
                'discipline' => [],
                'cartes_eleves' => ['read'],
                'recouvrement' => [],
                'admissions' => [],
                'settings' => []
            ];
            break;
            
        case 'eleve':
            // Élève : Accès très limité à ses propres informations
            $permissions = [
                'students' => ['read'],
                'academic' => [],
                'finance' => ['read'],
                'evaluations' => ['read'],
                'personnel' => [],
                'communication' => ['read'],
                'reports' => [],
                'users' => [],
                'library' => ['read'],
                'discipline' => [],
                'cartes_eleves' => ['read'],
                'recouvrement' => [],
                'admissions' => [],
                'settings' => []
            ];
            break;
            
        default:
            $permissions = [];
    }
    
    return json_encode($permissions, JSON_UNESCAPED_UNICODE);
}

// Fonction pour obtenir la description détaillée d'un rôle
function getRoleDescription($role_name) {
    $descriptions = [
        'admin' => 'Administrateur système avec accès complet à tous les modules et fonctionnalités. Peut gérer les utilisateurs, les rôles et la configuration système.',
        'directeur' => 'Directeur de l\'établissement avec accès étendu aux modules pédagogiques et administratifs. Peut prendre des décisions importantes et gérer le personnel.',
        'enseignant' => 'Enseignant avec accès aux modules pédagogiques, évaluations et gestion des élèves. Peut saisir les notes et gérer les présences.',
        'secretaire' => 'Secrétaire avec accès aux modules administratifs et de communication. Gère les inscriptions, les dossiers et la communication avec les parents.',
        'comptable' => 'Comptable avec accès complet au module financier. Gère les paiements, les frais scolaires et les rapports financiers.',
        'surveillant' => 'Surveillant avec accès aux modules de discipline et de présence. Gère les incidents, sanctions et pointage des présences.',
        'bibliothecaire' => 'Bibliothécaire avec accès complet au module bibliothèque. Gère les livres, emprunts et réservations.',
        'parent' => 'Parent avec accès limité aux informations de ses enfants. Peut consulter les notes, paiements et communications.',
        'eleve' => 'Élève avec accès très limité à ses propres informations. Peut consulter ses notes et paiements.'
    ];
    
    return $descriptions[$role_name] ?? 'Rôle personnalisé';
}

// Fonction pour obtenir les modules accessibles par rôle
function getAccessibleModules($role_name) {
    $modules = [
        'admin' => ['students', 'academic', 'finance', 'evaluations', 'personnel', 'communication', 'reports', 'users', 'library', 'discipline', 'cartes_eleves', 'recouvrement', 'admissions', 'settings'],
        'directeur' => ['students', 'academic', 'finance', 'evaluations', 'personnel', 'communication', 'reports', 'users', 'library', 'discipline', 'cartes_eleves', 'recouvrement', 'admissions', 'settings'],
        'enseignant' => ['students', 'academic', 'evaluations', 'communication', 'reports', 'library', 'discipline', 'cartes_eleves', 'admissions'],
        'secretaire' => ['students', 'academic', 'finance', 'evaluations', 'personnel', 'communication', 'reports', 'users', 'library', 'discipline', 'cartes_eleves', 'recouvrement', 'admissions'],
        'comptable' => ['students', 'finance', 'communication', 'reports', 'cartes_eleves', 'recouvrement', 'admissions'],
        'surveillant' => ['students', 'academic', 'communication', 'reports', 'discipline', 'cartes_eleves', 'recouvrement'],
        'bibliothecaire' => ['students', 'communication', 'reports', 'library', 'cartes_eleves'],
        'parent' => ['students', 'finance', 'evaluations', 'communication', 'reports', 'cartes_eleves'],
        'eleve' => ['students', 'finance', 'evaluations', 'communication', 'library', 'cartes_eleves']
    ];
    
    return $modules[$role_name] ?? [];
}

try {
    echo "🚀 Initialisation avancée des rôles - Educ-Sinfinity\n";
    echo "====================================================\n\n";
    
    // Vérifier la connexion à la base de données
    if (!$database) {
        throw new Exception("❌ Erreur de connexion à la base de données");
    }
    
    echo "✅ Connexion à la base de données établie\n";
    
    // Vérifier si la table roles existe
    $check_table = $database->query("SHOW TABLES LIKE 'roles'");
    if ($check_table->rowCount() == 0) {
        echo "❌ La table 'roles' n'existe pas. Veuillez d'abord exécuter la migration.\n";
        exit(1);
    }
    
    echo "✅ Table 'roles' trouvée\n";
    
    // Vider la table roles
    $database->execute("DELETE FROM roles");
    echo "🗑️ Table 'roles' vidée\n";
    
    // Définir les rôles à créer
    $roles = [
        [
            'nom' => 'admin',
            'description' => getRoleDescription('admin'),
            'permissions' => generateRolePermissions('admin'),
            'actif' => 1
        ],
        [
            'nom' => 'directeur',
            'description' => getRoleDescription('directeur'),
            'permissions' => generateRolePermissions('directeur'),
            'actif' => 1
        ],
        [
            'nom' => 'enseignant',
            'description' => getRoleDescription('enseignant'),
            'permissions' => generateRolePermissions('enseignant'),
            'actif' => 1
        ],
        [
            'nom' => 'secretaire',
            'description' => getRoleDescription('secretaire'),
            'permissions' => generateRolePermissions('secretaire'),
            'actif' => 1
        ],
        [
            'nom' => 'comptable',
            'description' => getRoleDescription('comptable'),
            'permissions' => generateRolePermissions('comptable'),
            'actif' => 1
        ],
        [
            'nom' => 'surveillant',
            'description' => getRoleDescription('surveillant'),
            'permissions' => generateRolePermissions('surveillant'),
            'actif' => 1
        ],
        [
            'nom' => 'bibliothecaire',
            'description' => getRoleDescription('bibliothecaire'),
            'permissions' => generateRolePermissions('bibliothecaire'),
            'actif' => 1
        ],
        [
            'nom' => 'parent',
            'description' => getRoleDescription('parent'),
            'permissions' => generateRolePermissions('parent'),
            'actif' => 1
        ],
        [
            'nom' => 'eleve',
            'description' => getRoleDescription('eleve'),
            'permissions' => generateRolePermissions('eleve'),
            'actif' => 1
        ]
    ];
    
    echo "\n📋 Création des rôles :\n";
    echo "=======================\n";
    
    $inserted_count = 0;
    
    foreach ($roles as $role) {
        try {
            $result = $database->execute("
                INSERT INTO roles (nom, description, permissions, actif, date_creation, date_modification) 
                VALUES (?, ?, ?, ?, NOW(), NOW())
            ", [
                $role['nom'],
                $role['description'],
                $role['permissions'],
                $role['actif']
            ]);
            
            $inserted_count++;
            echo "✅ Rôle '{$role['nom']}' créé\n";
            
            // Afficher les modules accessibles
            $accessible_modules = getAccessibleModules($role['nom']);
            echo "   📁 Modules accessibles : " . implode(', ', $accessible_modules) . "\n";
            
            // Afficher les permissions
            $permissions_data = json_decode($role['permissions'], true);
            $total_permissions = 0;
            foreach ($permissions_data as $module => $actions) {
                $total_permissions += count($actions);
            }
            echo "   🔐 Permissions : {$total_permissions} actions sur " . count($permissions_data) . " modules\n";
            
        } catch (Exception $e) {
            echo "❌ Erreur lors de la création du rôle '{$role['nom']}' : " . $e->getMessage() . "\n";
        }
    }
    
    echo "\n📊 Résumé de l'initialisation :\n";
    echo "===============================\n";
    echo "✅ Rôles créés : {$inserted_count}/" . count($roles) . "\n";
    
    // Vérifier les rôles créés
    $created_roles = $database->query("SELECT nom, actif FROM roles ORDER BY nom")->fetchAll();
    
    echo "\n📋 Rôles disponibles :\n";
    echo "=====================\n";
    foreach ($created_roles as $role) {
        $status = $role['actif'] ? '✅ Actif' : '❌ Inactif';
        echo "• {$role['nom']} - {$status}\n";
    }
    
    // Statistiques des permissions
    echo "\n🔐 Statistiques des permissions :\n";
    echo "=================================\n";
    
    $permissions_stats = $database->query("
        SELECT 
            nom,
            JSON_LENGTH(permissions) as nb_modules,
            (
                SELECT SUM(JSON_LENGTH(value)) 
                FROM JSON_TABLE(permissions, '$.*' COLUMNS (value JSON PATH '$')) as jt
            ) as nb_actions
        FROM roles 
        ORDER BY nom
    ")->fetchAll();
    
    foreach ($permissions_stats as $stat) {
        echo "• {$stat['nom']} : {$stat['nb_actions']} actions sur {$stat['nb_modules']} modules\n";
    }
    
    // Créer un fichier de documentation des rôles
    $doc_content = generateRolesDocumentation($roles);
    file_put_contents(__DIR__ . '/../docs/ROLES-DOCUMENTATION.md', $doc_content);
    echo "\n📄 Documentation des rôles créée : docs/ROLES-DOCUMENTATION.md\n";
    
    echo "\n🎉 Initialisation avancée des rôles terminée avec succès !\n";
    echo "========================================================\n";
    
} catch (Exception $e) {
    echo "\n❌ Erreur lors de l'initialisation : " . $e->getMessage() . "\n";
    exit(1);
}

/**
 * Générer la documentation des rôles
 */
function generateRolesDocumentation($roles) {
    $doc = "# 📋 Documentation des Rôles - Educ-Sinfinity\n\n";
    $doc .= "## Vue d'ensemble\n\n";
    $doc .= "Ce document décrit tous les rôles disponibles dans le système Educ-Sinfinity et leurs permissions associées.\n\n";
    
    $doc .= "## Rôles Disponibles\n\n";
    
    foreach ($roles as $role) {
        $permissions_data = json_decode($role['permissions'], true);
        $accessible_modules = getAccessibleModules($role['nom']);
        
        $doc .= "### {$role['nom']}\n\n";
        $doc .= "**Description :** {$role['description']}\n\n";
        
        $doc .= "**Modules accessibles :** " . implode(', ', $accessible_modules) . "\n\n";
        
        $doc .= "**Permissions détaillées :**\n\n";
        $doc .= "| Module | Actions |\n";
        $doc .= "|--------|----------|\n";
        
        foreach ($permissions_data as $module => $actions) {
            $actions_str = implode(', ', $actions);
            $doc .= "| {$module} | {$actions_str} |\n";
        }
        
        $doc .= "\n---\n\n";
    }
    
    $doc .= "## Hiérarchie des Rôles\n\n";
    $doc .= "Les rôles sont organisés par niveau de privilège :\n\n";
    $doc .= "1. **Niveau 1** : Admin (accès complet)\n";
    $doc .= "2. **Niveau 2** : Directeur (accès étendu)\n";
    $doc .= "3. **Niveau 3** : Enseignant, Secrétaire, Comptable (accès spécialisé)\n";
    $doc .= "4. **Niveau 4** : Surveillant, Bibliothécaire (accès limité)\n";
    $doc .= "5. **Niveau 5** : Parent (accès restreint)\n";
    $doc .= "6. **Niveau 6** : Élève (accès minimal)\n\n";
    
    $doc .= "## Modules du Système\n\n";
    $doc .= "Le système comprend les modules suivants :\n\n";
    $doc .= "- **students** : Gestion des élèves\n";
    $doc .= "- **academic** : Gestion académique\n";
    $doc .= "- **finance** : Gestion financière\n";
    $doc .= "- **evaluations** : Évaluations et notes\n";
    $doc .= "- **personnel** : Gestion du personnel\n";
    $doc .= "- **communication** : Communication\n";
    $doc .= "- **reports** : Rapports\n";
    $doc .= "- **users** : Gestion des utilisateurs\n";
    $doc .= "- **library** : Bibliothèque\n";
    $doc .= "- **discipline** : Discipline\n";
    $doc .= "- **cartes_eleves** : Cartes d'élèves\n";
    $doc .= "- **recouvrement** : Recouvrement\n";
    $doc .= "- **admissions** : Admissions\n";
    $doc .= "- **settings** : Paramètres\n\n";
    
    $doc .= "## Actions Disponibles\n\n";
    $doc .= "- **read** : Lire/consulter\n";
    $doc .= "- **create** : Créer/ajouter\n";
    $doc .= "- **edit** : Modifier\n";
    $doc .= "- **delete** : Supprimer\n\n";
    
    $doc .= "---\n\n";
    $doc .= "*Documentation générée automatiquement le " . date('d/m/Y à H:i:s') . "*\n";
    
    return $doc;
}
?>
