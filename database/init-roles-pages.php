<?php
/**
 * Initialisation des rôles basée sur les pages individuelles
 * Système de gestion scolaire - République Démocratique du Congo
 * 
 * Ce script crée un système de rôles basé sur les 247 pages individuelles
 * au lieu des modules génériques pour un contrôle d'accès granulaire.
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/page-permissions.php';

// Fonction pour générer les permissions JSON pour un rôle basé sur les pages
function generateRolePagePermissions($role_name) {
    $permissions = [];
    
    // Définir les permissions par rôle et par page
    $role_permissions = getRolePagePermissions($role_name);
    
    foreach ($role_permissions as $page => $actions) {
        $permissions[$page] = $actions;
    }
    
    return json_encode($permissions, JSON_UNESCAPED_UNICODE);
}

// Fonction pour obtenir les permissions d'une page pour un rôle spécifique
function getRolePagePermissions($role_name) {
    $permissions = [];
    
    switch ($role_name) {
        case 'admin':
            // Admin a accès à toutes les pages avec toutes les actions
            foreach (PAGE_PERMISSIONS as $page => $actions) {
                $permissions[$page] = $actions;
            }
            break;
            
        case 'directeur':
            // Directeur a accès à la plupart des pages sauf certaines fonctions admin
            foreach (PAGE_PERMISSIONS as $page => $actions) {
                // Exclure certaines pages d'administration système
                if (strpos($page, 'admin/') === 0 || 
                    strpos($page, 'users/roles/') === 0 ||
                    strpos($page, 'system/') === 0) {
                    continue;
                }
                $permissions[$page] = $actions;
            }
            break;
            
        case 'enseignant':
            // Enseignant a accès aux pages pédagogiques
            $enseignant_pages = [
                // Academic
                'academic/index', 'academic/classes/index', 'academic/classes/view',
                'academic/subjects/index', 'academic/subjects/view',
                'academic/schedule/index', 'academic/schedule/class',
                'academic/evaluations/index', 'academic/evaluations/view',
                'academic/notes/add', 'academic/notes/student',
                
                // Students
                'students/index', 'students/view', 'students/list', 'students/search',
                'students/attendance/index', 'students/attendance/add-absence',
                'students/attendance/add-delay', 'students/attendance/bulk-attendance',
                'students/attendance/edit', 'students/attendance/justify-absence',
                'students/attendance/get-students', 'students/attendance/get-absence-history',
                'students/records/index', 'students/records/view',
                'students/student-tracking/index', 'students/student-tracking/follow-up/index',
                'students/student-tracking/evaluations/index', 'students/student-tracking/evaluations/add',
                
                // Evaluations
                'evaluations/index', 'evaluations/class', 'evaluations/teacher',
                'evaluations/evaluations/index', 'evaluations/evaluations/add',
                'evaluations/evaluations/edit', 'evaluations/evaluations/view',
                'evaluations/notes/index', 'evaluations/notes/entry',
                'evaluations/notes/batch-entry', 'evaluations/notes/student',
                'evaluations/notes/reports', 'evaluations/notes/statistics',
                'evaluations/notes/classe_report', 'evaluations/notes/matiere_report',
                'evaluations/notes/periode_report', 'evaluations/notes/evaluation_report',
                'evaluations/bulletins/index', 'evaluations/bulletins/generate',
                'evaluations/bulletins/individual', 'evaluations/bulletins/batch_bulletins',
                'evaluations/bulletins/preview', 'evaluations/bulletins/download',
                'evaluations/statistics/index', 'evaluations/statistics/class-ranking',
                'evaluations/statistics/student-performance', 'evaluations/statistics/subject-analysis',
                
                // Discipline
                'discipline/index', 'discipline/incidents/index', 'discipline/incidents/add',
                'discipline/incidents/view', 'discipline/incidents/search_eleves',
                'discipline/sanctions/index', 'discipline/sanctions/add',
                'discipline/sanctions/search_eleves', 'discipline/recompenses/index',
                'discipline/recompenses/add', 'discipline/reports/index',
                
                // Communication
                'communication/index', 'communication/annonces/add',
                'communication/messages/index', 'communication/messages/compose',
                'communication/messages/view', 'communication/sms/index',
                'communication/sms/send',
                
                // Reports
                'reports/index', 'reports/academic/index', 'reports/academic/export',
                'reports/academic/analysis/detailed', 'reports/academic/comparison/classes',
                'reports/academic/trends/evolution',
                
                // Cartes élèves (lecture)
                'cartes_eleves/index', 'cartes_eleves/view', 'cartes_eleves/qr-scanner'
            ];
            
            foreach ($enseignant_pages as $page) {
                if (isset(PAGE_PERMISSIONS[$page])) {
                    $permissions[$page] = PAGE_PERMISSIONS[$page];
                }
            }
            break;
            
        case 'secretaire':
            // Secrétaire a accès aux fonctions administratives
            $secretaire_pages = [
                // Students
                'students/index', 'students/add', 'students/edit', 'students/view',
                'students/list', 'students/search', 'students/reports',
                'students/enrollment', 'students/re-enrollment', 'students/change-status',
                'students/confirm-inscriptions', 'students/enrollment-history',
                'students/admissions/index', 'students/admissions/new-application',
                'students/admissions/direct-enrollment', 'students/admissions/bulk-import',
                'students/admissions/applications/index', 'students/admissions/applications/add',
                'students/admissions/applications/edit', 'students/admissions/applications/view',
                'students/admissions/applications/process', 'students/admissions/applications/update_status',
                'students/admissions/enrollment/index', 'students/admissions/enrollment/get-candidature',
                'students/admissions/evaluation/index', 'students/admissions/evaluation/get-evaluation',
                'students/admissions/documents/index', 'students/admissions/exports/applications',
                'students/admissions/reports/admission-stats', 'students/admissions/settings/criteria',
                'students/attendance/index', 'students/attendance/add-absence',
                'students/attendance/add-delay', 'students/attendance/bulk-attendance',
                'students/attendance/edit', 'students/attendance/justify-absence',
                'students/attendance/get-students', 'students/attendance/get-absence-history',
                'students/attendance/log-action', 'students/attendance/exports/attendance',
                'students/attendance/exports/preview-data', 'students/attendance/notifications/parents',
                'students/attendance/notifications/send-single-notification',
                'students/attendance/reports/monthly', 'students/records/index',
                'students/records/view', 'students/records/edit', 'students/records/documents',
                'students/transfers/index', 'students/transfers/view', 'students/transfers/view-transfer',
                'students/transfers/new-transfer', 'students/transfers/new-exit',
                'students/transfers/process', 'students/transfers/bulk-process',
                'students/transfers/certificate', 'students/transfers/certificates/index',
                'students/transfers/certificates/generate', 'students/transfers/exports/movements',
                'students/transfers/reports/transfers',
                
                // Academic
                'academic/index', 'academic/classes/index', 'academic/classes/add',
                'academic/classes/edit', 'academic/classes/view', 'academic/classes/export',
                'academic/subjects/index', 'academic/subjects/add', 'academic/subjects/edit',
                'academic/subjects/view', 'academic/subjects/export',
                'academic/schedule/index', 'academic/schedule/add', 'academic/schedule/add-schedule',
                'academic/schedule/edit-schedule', 'academic/schedule/class',
                'academic/schedule/conflicts', 'academic/schedule/detect-conflicts',
                'academic/schedule/resolve-conflict', 'academic/schedule/generate',
                'academic/schedule/export', 'academic/years/index', 'academic/years/add',
                'academic/years/edit', 'academic/evaluations/index', 'academic/evaluations/view',
                'academic/notes/add', 'academic/notes/student', 'academic/schedule',
                
                // Personnel
                'personnel/index', 'personnel/add', 'personnel/edit', 'personnel/view',
                'personnel/export', 'personnel/import', 'personnel/create-account',
                
                // Communication
                'communication/index', 'communication/annonces/add',
                'communication/messages/index', 'communication/messages/compose',
                'communication/messages/view', 'communication/sms/index',
                'communication/sms/send', 'communication/templates/index',
                
                // Reports
                'reports/index', 'reports/academic/index', 'reports/academic/export',
                'reports/academic/analysis/detailed', 'reports/academic/bulletins/generate-all',
                'reports/academic/comparison/classes', 'reports/academic/trends/evolution',
                'reports/administrative/index', 'reports/custom/index',
                
                // Cartes élèves
                'cartes_eleves/index', 'cartes_eleves/view', 'cartes_eleves/generate_card',
                'cartes_eleves/auto-generate', 'cartes_eleves/regenerate-all',
                'cartes_eleves/regenerate-qr-codes', 'cartes_eleves/print',
                'cartes_eleves/print-all', 'cartes_eleves/download', 'cartes_eleves/download-qr',
                'cartes_eleves/qr-generator', 'cartes_eleves/simple-qr-generator',
                'cartes_eleves/qr-scanner', 'cartes_eleves/qr-actions', 'cartes_eleves/actions',
                'cartes_eleves/get-students', 'cartes_eleves/integration-paiements',
                'cartes_eleves/integration-presences', 'cartes_eleves/settings',
                
                // Users (lecture)
                'users/index', 'users/view', 'users/list', 'users/logs/index',
                'users/sessions/index'
            ];
            
            foreach ($secretaire_pages as $page) {
                if (isset(PAGE_PERMISSIONS[$page])) {
                    $permissions[$page] = PAGE_PERMISSIONS[$page];
                }
            }
            break;
            
        case 'comptable':
            // Comptable a accès aux fonctions financières
            $comptable_pages = [
                // Finance
                'finance/index', 'finance/devises/index', 'finance/fees/index',
                'finance/fees/add', 'finance/fees/edit', 'finance/fees/view',
                'finance/fees/bulk-add', 'finance/fees/duplicate', 'finance/fees/manage',
                'finance/fees/templates', 'finance/fees/types/index', 'finance/fees/types/add',
                'finance/fees/types/edit', 'finance/fees/types/view', 'finance/fees/types/toggle-status',
                'finance/fees/types/init-default-types', 'finance/payments/index',
                'finance/payments/add', 'finance/payments/edit', 'finance/payments/cancel',
                'finance/payments/view', 'finance/payments/receipt', 'finance/payments/export',
                'finance/payments/search_eleves', 'finance/payments/get_eleves_for_payment',
                'finance/payments/get_frais_by_classe', 'finance/payments/get-priority-fee-type',
                'finance/expenses/index', 'finance/expenses/add', 'finance/expenses/edit',
                'finance/expenses/view', 'finance/expenses/pay', 'finance/expenses/caisses',
                'finance/expenses/journal_caisse', 'finance/expenses/historique_caisses',
                'finance/expenses/maintenance_caisses', 'finance/expenses/integration_paiements',
                'finance/expenses/ajax_caisse_stats', 'finance/expenses/caisse_functions',
                'finance/reports/index', 'finance/reports/debtors', 'finance/reports/monthly',
                
                // Students (lecture pour les paiements)
                'students/index', 'students/view', 'students/list', 'students/search',
                
                // Communication
                'communication/index', 'communication/messages/index', 'communication/messages/view',
                'communication/sms/index', 'communication/sms/send',
                
                // Reports
                'reports/index', 'reports/financial/index',
                
                // Cartes élèves (lecture)
                'cartes_eleves/index', 'cartes_eleves/view', 'cartes_eleves/qr-scanner',
                
                // Recouvrement
                'recouvrement/index', 'recouvrement/campaigns/index', 'recouvrement/campaigns/edit',
                'recouvrement/campaigns/details', 'recouvrement/cartes/index',
                'recouvrement/cartes/generate', 'recouvrement/cartes/view', 'recouvrement/cartes/print',
                'recouvrement/frais/index', 'recouvrement/notifications/index',
                'recouvrement/paiements/index', 'recouvrement/parametres/index',
                'recouvrement/rapports/index', 'recouvrement/rapports/export',
                'recouvrement/rapports/paiements', 'recouvrement/rapports/presences',
                'recouvrement/rapports/comparatif', 'recouvrement/rapports/solvabilite',
                'recouvrement/reports/index', 'recouvrement/reports/export',
                'recouvrement/solvabilite/index', 'recouvrement/scan-qr'
            ];
            
            foreach ($comptable_pages as $page) {
                if (isset(PAGE_PERMISSIONS[$page])) {
                    $permissions[$page] = PAGE_PERMISSIONS[$page];
                }
            }
            break;
            
        case 'surveillant':
            // Surveillant a accès aux fonctions de discipline et surveillance
            $surveillant_pages = [
                // Students
                'students/index', 'students/view', 'students/list', 'students/search',
                'students/attendance/index', 'students/attendance/add-absence',
                'students/attendance/add-delay', 'students/attendance/bulk-attendance',
                'students/attendance/edit', 'students/attendance/justify-absence',
                'students/attendance/get-students', 'students/attendance/get-absence-history',
                'students/attendance/log-action', 'students/attendance/exports/attendance',
                'students/attendance/exports/preview-data', 'students/attendance/notifications/parents',
                'students/attendance/notifications/send-single-notification',
                'students/attendance/reports/monthly',
                
                // Academic (lecture)
                'academic/index', 'academic/classes/index', 'academic/classes/view',
                'academic/subjects/index', 'academic/subjects/view',
                'academic/schedule/index', 'academic/schedule/class',
                'academic/evaluations/index', 'academic/evaluations/view',
                'academic/notes/student', 'academic/schedule',
                
                // Discipline
                'discipline/index', 'discipline/incidents/index', 'discipline/incidents/add',
                'discipline/incidents/view', 'discipline/incidents/search_eleves',
                'discipline/sanctions/index', 'discipline/sanctions/add',
                'discipline/sanctions/search_eleves', 'discipline/recompenses/index',
                'discipline/recompenses/add', 'discipline/reports/index',
                
                // Communication
                'communication/index', 'communication/annonces/add',
                'communication/messages/index', 'communication/messages/compose',
                'communication/messages/view', 'communication/sms/index',
                'communication/sms/send',
                
                // Reports
                'reports/index', 'reports/academic/index', 'reports/academic/export',
                'reports/academic/analysis/detailed', 'reports/academic/comparison/classes',
                'reports/academic/trends/evolution',
                
                // Cartes élèves (lecture)
                'cartes_eleves/index', 'cartes_eleves/view', 'cartes_eleves/qr-scanner',
                
                // Recouvrement (lecture)
                'recouvrement/index', 'recouvrement/scan-qr'
            ];
            
            foreach ($surveillant_pages as $page) {
                if (isset(PAGE_PERMISSIONS[$page])) {
                    $permissions[$page] = PAGE_PERMISSIONS[$page];
                }
            }
            break;
            
        case 'bibliothecaire':
            // Bibliothécaire a accès aux fonctions de bibliothèque
            $bibliothecaire_pages = [
                // Library
                'library/index', 'library/books/index', 'library/books/add',
                'library/books/edit', 'library/books/view', 'library/books/export',
                'library/books/import', 'library/books/categories', 'library/books/update_database',
                'library/loans/index', 'library/loans/add', 'library/loans/returns',
                'library/loans/check_table', 'library/loans/create_table', 'library/loans/fix_database',
                'library/reservations/add', 'library/reports/inventory', 'library/settings/index',
                
                // Students (lecture pour les emprunts)
                'students/index', 'students/view', 'students/list', 'students/search',
                
                // Communication
                'communication/index', 'communication/messages/index', 'communication/messages/view',
                'communication/sms/index', 'communication/sms/send',
                
                // Reports
                'reports/index', 'reports/academic/index', 'reports/academic/export',
                'reports/academic/analysis/detailed', 'reports/academic/comparison/classes',
                'reports/academic/trends/evolution',
                
                // Cartes élèves (lecture)
                'cartes_eleves/index', 'cartes_eleves/view', 'cartes_eleves/qr-scanner'
            ];
            
            foreach ($bibliothecaire_pages as $page) {
                if (isset(PAGE_PERMISSIONS[$page])) {
                    $permissions[$page] = PAGE_PERMISSIONS[$page];
                }
            }
            break;
            
        case 'parent':
            // Parent a accès limité aux informations de ses enfants
            $parent_pages = [
                // Students (lecture limitée)
                'students/view', 'students/records/view', 'students/records/documents',
                
                // Finance (lecture des paiements)
                'finance/payments/view', 'finance/payments/receipt',
                
                // Evaluations (lecture des notes et bulletins)
                'evaluations/notes/student', 'evaluations/bulletins/individual',
                'evaluations/bulletins/preview', 'evaluations/bulletins/download',
                'evaluations/statistics/student-performance',
                
                // Communication
                'communication/index', 'communication/messages/index', 'communication/messages/view',
                'communication/sms/index',
                
                // Reports (lecture limitée)
                'reports/index', 'reports/academic/index',
                
                // Cartes élèves (lecture)
                'cartes_eleves/view'
            ];
            
            foreach ($parent_pages as $page) {
                if (isset(PAGE_PERMISSIONS[$page])) {
                    $permissions[$page] = PAGE_PERMISSIONS[$page];
                }
            }
            break;
            
        case 'eleve':
            // Élève a accès très limité
            $eleve_pages = [
                // Students (lecture de son propre dossier)
                'students/view', 'students/records/view',
                
                // Finance (lecture de ses paiements)
                'finance/payments/view',
                
                // Evaluations (lecture de ses notes)
                'evaluations/notes/student', 'evaluations/bulletins/individual',
                'evaluations/bulletins/preview', 'evaluations/bulletins/download',
                
                // Communication
                'communication/index', 'communication/messages/index', 'communication/messages/view',
                
                // Library (lecture)
                'library/index', 'library/books/index', 'library/books/view',
                
                // Cartes élèves (lecture de sa carte)
                'cartes_eleves/view'
            ];
            
            foreach ($eleve_pages as $page) {
                if (isset(PAGE_PERMISSIONS[$page])) {
                    $permissions[$page] = PAGE_PERMISSIONS[$page];
                }
            }
            break;
    }
    
    return $permissions;
}

// Fonction pour obtenir la description d'un rôle
function getRoleDescription($role_name) {
    $descriptions = [
        'admin' => 'Administrateur système avec accès complet à toutes les fonctionnalités',
        'directeur' => 'Directeur d\'établissement avec accès étendu à la gestion administrative',
        'enseignant' => 'Enseignant avec accès aux fonctions pédagogiques et de suivi des élèves',
        'secretaire' => 'Secrétaire avec accès aux fonctions administratives et de gestion des élèves',
        'comptable' => 'Comptable avec accès aux fonctions financières et de recouvrement',
        'surveillant' => 'Surveillant avec accès aux fonctions de discipline et de surveillance',
        'bibliothecaire' => 'Bibliothécaire avec accès aux fonctions de gestion de la bibliothèque',
        'parent' => 'Parent avec accès limité aux informations de ses enfants',
        'eleve' => 'Élève avec accès très limité à ses propres informations'
    ];
    
    return $descriptions[$role_name] ?? 'Rôle non défini';
}

// Fonction pour obtenir les pages accessibles par un rôle
function getAccessiblePages($role_name) {
    $permissions = getRolePagePermissions($role_name);
    return array_keys($permissions);
}

// Fonction pour générer la documentation des rôles
function generateRolesDocumentation($roles) {
    $doc = "# 📋 Documentation des Rôles - Système par Pages\n";
    $doc .= "## Système de gestion scolaire Educ-Sinfinity\n\n";
    $doc .= "**Date de génération :** " . date('d/m/Y à H:i:s') . "\n\n";
    $doc .= "**Total des pages :** " . getTotalPages() . "\n\n";
    
    $doc .= "## 🎯 Vue d'ensemble\n\n";
    $doc .= "Ce système utilise un contrôle d'accès granulaire basé sur les **247 pages individuelles** ";
    $doc .= "au lieu des modules génériques, permettant un contrôle précis des permissions.\n\n";
    
    $doc .= "## 📊 Statistiques par module\n\n";
    $stats = getModuleStats();
    foreach ($stats as $module => $count) {
        $doc .= "- **{$module}** : {$count} pages\n";
    }
    
    $doc .= "\n## 🔐 Rôles disponibles\n\n";
    
    foreach ($roles as $role) {
        $permissions_data = json_decode($role['permissions'], true);
        $accessible_pages = getAccessiblePages($role['nom']);
        
        $doc .= "### {$role['nom']}\n\n";
        $doc .= "**Description :** {$role['description']}\n\n";
        $doc .= "**Pages accessibles :** " . count($accessible_pages) . " pages\n\n";
        
        // Grouper par module
        $module_pages = [];
        foreach ($accessible_pages as $page) {
            $module = explode('/', $page)[0];
            if (!isset($module_pages[$module])) {
                $module_pages[$module] = [];
            }
            $module_pages[$module][] = $page;
        }
        
        $doc .= "**Répartition par module :**\n\n";
        foreach ($module_pages as $module => $pages) {
            $doc .= "- **{$module}** : " . count($pages) . " pages\n";
        }
        
        $doc .= "\n---\n\n";
    }
    
    return $doc;
}

// Script principal
try {
    echo "🚀 Initialisation des rôles basée sur les pages - Educ-Sinfinity\n";
    echo "================================================================\n\n";
    
    // Connexion à la base de données
    $database = new Database();
    $database->connect();
    echo "✅ Connexion à la base de données établie\n";
    
    // Vérifier que la table roles existe
    $result = $database->query("SHOW TABLES LIKE 'roles'");
    if ($result->rowCount() == 0) {
        throw new Exception("La table 'roles' n'existe pas. Veuillez d'abord créer la table.");
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
            'permissions' => generateRolePagePermissions('admin'),
            'actif' => 1
        ],
        [
            'nom' => 'directeur',
            'description' => getRoleDescription('directeur'),
            'permissions' => generateRolePagePermissions('directeur'),
            'actif' => 1
        ],
        [
            'nom' => 'enseignant',
            'description' => getRoleDescription('enseignant'),
            'permissions' => generateRolePagePermissions('enseignant'),
            'actif' => 1
        ],
        [
            'nom' => 'secretaire',
            'description' => getRoleDescription('secretaire'),
            'permissions' => generateRolePagePermissions('secretaire'),
            'actif' => 1
        ],
        [
            'nom' => 'comptable',
            'description' => getRoleDescription('comptable'),
            'permissions' => generateRolePagePermissions('comptable'),
            'actif' => 1
        ],
        [
            'nom' => 'surveillant',
            'description' => getRoleDescription('surveillant'),
            'permissions' => generateRolePagePermissions('surveillant'),
            'actif' => 1
        ],
        [
            'nom' => 'bibliothecaire',
            'description' => getRoleDescription('bibliothecaire'),
            'permissions' => generateRolePagePermissions('bibliothecaire'),
            'actif' => 1
        ],
        [
            'nom' => 'parent',
            'description' => getRoleDescription('parent'),
            'permissions' => generateRolePagePermissions('parent'),
            'actif' => 1
        ],
        [
            'nom' => 'eleve',
            'description' => getRoleDescription('eleve'),
            'permissions' => generateRolePagePermissions('eleve'),
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
            $accessible_pages = getAccessiblePages($role['nom']);
            echo "✅ Rôle '{$role['nom']}' créé\n";
            echo "   📄 Pages accessibles : " . count($accessible_pages) . " pages\n";
            
            // Afficher les modules accessibles
            $module_stats = [];
            foreach ($accessible_pages as $page) {
                $module = explode('/', $page)[0];
                if (!isset($module_stats[$module])) {
                    $module_stats[$module] = 0;
                }
                $module_stats[$module]++;
            }
            
            $modules_list = [];
            foreach ($module_stats as $module => $count) {
                $modules_list[] = "{$module} ({$count})";
            }
            echo "   📁 Modules : " . implode(', ', $modules_list) . "\n\n";
            
        } catch (Exception $e) {
            echo "❌ Erreur lors de la création du rôle '{$role['nom']}' : " . $e->getMessage() . "\n";
        }
    }
    
    echo "📊 Résumé de l'initialisation :\n";
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
            JSON_LENGTH(permissions) as nb_pages
        FROM roles 
        ORDER BY nom
    ")->fetchAll();
    
    foreach ($permissions_stats as $stat) {
        echo "• {$stat['nom']} : {$stat['nb_pages']} pages accessibles\n";
    }
    
    // Créer un fichier de documentation des rôles
    $doc_content = generateRolesDocumentation($roles);
    file_put_contents(__DIR__ . '/../docs/ROLES-PAGES-DOCUMENTATION.md', $doc_content);
    echo "\n📄 Documentation des rôles créée : docs/ROLES-PAGES-DOCUMENTATION.md\n";
    
    echo "\n🎉 Initialisation des rôles basée sur les pages terminée avec succès !\n";
    echo "====================================================================\n";
    
} catch (Exception $e) {
    echo "❌ Erreur : " . $e->getMessage() . "\n";
    exit(1);
}
?>
