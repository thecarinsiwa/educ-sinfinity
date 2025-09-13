<?php
/**
 * Script pour corriger les permissions du module students
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

echo "<h2>🔧 Correction des Permissions Students</h2>";

try {
    // Récupérer le rôle "Huitier"
    $stmt = $database->query("SELECT * FROM roles WHERE nom = 'Huitier'");
    $role = $stmt->fetch();
    
    if (!$role) {
        echo "<p>❌ Rôle 'Huitier' non trouvé</p>";
        exit;
    }
    
    echo "<h3>🔍 Rôle trouvé: " . htmlspecialchars($role['nom']) . "</h3>";
    
    // Permissions par défaut pour le rôle Huitier basées sur l'image
    $default_permissions = [
        'students' => [
            'index' => ['read'],
            'add' => ['create'],
            'list' => ['read'],
            'view' => ['read']
        ],
        'finance' => [
            'index' => ['read']
        ]
    ];
    
    if ($role['permissions']) {
        $current_permissions = json_decode($role['permissions'], true);
        echo "<h4>📋 Permissions actuelles:</h4>";
        echo "<pre>" . htmlspecialchars(json_encode($current_permissions, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) . "</pre>";
        
        // Vérifier si les permissions students sont correctes
        if (isset($current_permissions['students'])) {
            echo "<p>✅ Permissions 'students' trouvées</p>";
            
            $students_perms = $current_permissions['students'];
            $has_index = isset($students_perms['index']) && in_array('read', $students_perms['index']);
            $has_add = isset($students_perms['add']) && in_array('create', $students_perms['add']);
            
            echo "<p>Index (read): " . ($has_index ? '✅' : '❌') . "</p>";
            echo "<p>Add (create): " . ($has_add ? '✅' : '❌') . "</p>";
            
            if (!$has_index || !$has_add) {
                echo "<h4>🔧 Correction nécessaire</h4>";
                
                // Fusionner les permissions
                $current_permissions['students'] = array_merge($current_permissions['students'], $default_permissions['students']);
                
                // Mettre à jour dans la base de données
                $new_permissions_json = json_encode($current_permissions, JSON_UNESCAPED_UNICODE);
                
                $update_stmt = $database->execute(
                    "UPDATE roles SET permissions = ?, date_modification = NOW() WHERE id = ?",
                    [$new_permissions_json, $role['id']]
                );
                
                if ($update_stmt) {
                    echo "<p>✅ Permissions mises à jour avec succès</p>";
                    echo "<h4>📋 Nouvelles permissions:</h4>";
                    echo "<pre>" . htmlspecialchars(json_encode($current_permissions, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) . "</pre>";
                } else {
                    echo "<p>❌ Erreur lors de la mise à jour</p>";
                }
            } else {
                echo "<p>✅ Permissions déjà correctes</p>";
            }
        } else {
            echo "<p>❌ Permissions 'students' manquantes</p>";
            echo "<h4>🔧 Ajout des permissions students</h4>";
            
            // Ajouter les permissions students
            $current_permissions['students'] = $default_permissions['students'];
            
            // Mettre à jour dans la base de données
            $new_permissions_json = json_encode($current_permissions, JSON_UNESCAPED_UNICODE);
            
            $update_stmt = $database->execute(
                "UPDATE roles SET permissions = ?, date_modification = NOW() WHERE id = ?",
                [$new_permissions_json, $role['id']]
            );
            
            if ($update_stmt) {
                echo "<p>✅ Permissions students ajoutées avec succès</p>";
            } else {
                echo "<p>❌ Erreur lors de l'ajout</p>";
            }
        }
    } else {
        echo "<p>❌ Aucune permission configurée</p>";
        echo "<h4>🔧 Configuration des permissions par défaut</h4>";
        
        // Configurer les permissions par défaut
        $new_permissions_json = json_encode($default_permissions, JSON_UNESCAPED_UNICODE);
        
        $update_stmt = $database->execute(
            "UPDATE roles SET permissions = ?, date_modification = NOW() WHERE id = ?",
            [$new_permissions_json, $role['id']]
        );
        
        if ($update_stmt) {
            echo "<p>✅ Permissions configurées avec succès</p>";
            echo "<h4>📋 Permissions configurées:</h4>";
            echo "<pre>" . htmlspecialchars(json_encode($default_permissions, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) . "</pre>";
        } else {
            echo "<p>❌ Erreur lors de la configuration</p>";
        }
    }
    
} catch (Exception $e) {
    echo "<p>❌ Erreur: " . htmlspecialchars($e->getMessage()) . "</p>";
}

echo "<hr>";
echo "<h3>🔗 Tests</h3>";
echo "<p><a href='test_students_access.php'>→ Tester l'accès au module students</a></p>";
echo "<p><a href='modules/students/index.php'>→ Accéder au module students</a></p>";
echo "<p><a href='admin/roles.php'>→ Gérer les rôles</a></p>";
echo "<p><a href='dashboard.php'>→ Tableau de bord</a></p>";
?>
