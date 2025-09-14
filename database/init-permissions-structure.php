<?php
/**
 * Script d'initialisation de la structure des permissions
 * Application de gestion scolaire - République Démocratique du Congo
 * 
 * Ce script crée la structure complète des permissions dans la base de données
 * selon la hiérarchie module > page > actions
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

// Structure complète des permissions
$permissions_structure = [
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
        "records/edit" => ["edit"],
        "records/view" => ["read"],
        "records/index" => ["read"],
        "records/documents" => ["read", "create"],
        "change-status" => ["edit"],
        "re-enrollment" => ["create", "edit"],
        "transfers/view" => ["read"],
        "transfers/index" => ["read"],
        "transfers/process" => ["edit"],
        "transfers/new-exit" => ["create"],
        "transfers/view-transfer" => ["read"],
        "transfers/certificate" => ["read", "print"],
        "transfers/bulk-process" => ["edit"],
        "transfers/new-transfer" => ["create"],
        "transfers/exports/movements" => ["export"],
        "transfers/reports/transfers" => ["read", "export"],
        "transfers/certificates/index" => ["read"],
        "transfers/certificates/generate" => ["create"],
        "enrollment-history" => ["read"],
        "admissions/index" => ["read"],
        "admissions/bulk-import" => ["import"],
        "admissions/documents/index" => ["read"],
        "admissions/new-application" => ["create"],
        "admissions/applications/add" => ["create"],
        "admissions/applications/edit" => ["edit"],
        "admissions/applications/view" => ["read"],
        "admissions/applications/index" => ["read"],
        "admissions/applications/list" => ["read"],
        "admissions/applications/evaluate" => ["edit"],
        "admissions/applications/process" => ["edit"],
        "admissions/applications/update_status" => ["edit"],
        "admissions/enrollment/index" => ["read"],
        "admissions/enrollment/get-candidature" => ["read"],
        "admissions/evaluation/index" => ["read"],
        "admissions/evaluation/get-evaluation" => ["read"],
        "admissions/direct-enrollment" => ["create"],
        "admissions/students/view" => ["read"],
        "admissions/settings/criteria" => ["admin"],
        "admissions/reports/admission-stats" => ["read", "export"],
        "admissions/exports/applications" => ["export"],
        "attendance/index" => ["read"],
        "attendance/edit" => ["edit"],
        "attendance/add-delay" => ["create"],
        "attendance/add-absence" => ["create"],
        "attendance/log-action" => ["create"],
        "attendance/get-students" => ["read"],
        "attendance/get-absence-history" => ["read"],
        "attendance/exports/attendance" => ["export"],
        "attendance/exports/preview-data" => ["read"],
        "attendance/bulk-attendance" => ["create", "edit"],
        "attendance/justify-absence" => ["edit"],
        "attendance/reports/monthly" => ["read", "export"],
        "attendance/notifications/parents" => ["create"],
        "attendance/notifications/send-single-notification" => ["create"],
        "confirm-inscriptions" => ["edit"],
        "student-tracking/index" => ["read"],
        "student-tracking/decisions/index" => ["read"],
        "student-tracking/decisions/take-decision" => ["create"],
        "student-tracking/evaluations/add" => ["create"],
        "student-tracking/evaluations/index" => ["read"],
        "student-tracking/follow-up/index" => ["read"]
    ],
    "academic" => [
        "classes/add" => ["create"],
        "classes/edit" => ["edit"],
        "classes/view" => ["read"],
        "classes/index" => ["read"],
        "classes/delete" => ["delete"],
        "classes/export" => ["export"],
        "classes/import" => ["import"],
        "subjects/add" => ["create"],
        "subjects/edit" => ["edit"],
        "subjects/view" => ["read"],
        "subjects/index" => ["read"],
        "subjects/delete" => ["delete"],
        "subjects/export" => ["export"],
        "schedule/add" => ["create"],
        "schedule/edit" => ["edit"],
        "schedule/view" => ["read"],
        "schedule/index" => ["read"],
        "schedule/delete" => ["delete"],
        "schedule/generate" => ["create"],
        "schedule/conflicts" => ["read"],
        "schedule/add-schedule" => ["create"],
        "schedule/class" => ["read"],
        "schedule/edit-schedule" => ["edit"],
        "schedule/resolve-conflict" => ["edit"],
        "schedule/detect-conflicts" => ["read"],
        "schedule/export" => ["export"],
        "years/add" => ["create"],
        "years/edit" => ["edit"],
        "years/view" => ["read"],
        "years/index" => ["read"],
        "years/delete" => ["delete"],
        "years/activate" => ["edit"]
        
        ],
        "evaluations" => [
            "class/index" => ["read"],
            "evaluations/index" => ["read"],
            "teacher/index" => ["read"],
            "bulletins/batch_bulletins" => ["create"],
            "bulletins/download" => ["read"],
            "bulletins/generate" => ["create"],
            "bulletins/individual" => ["read"],
            "bulletins/preview" => ["read"],
            "evaluations/add" => ["create"],
            "evaluations/edit" => ["edit"],
            "evaluations/view" => ["read"],
            "evaluations/delete" => ["delete"],  
            "notes/add" => ["create"],
            "notes/edit" => ["edit"],
            "notes/view" => ["read"],
            "notes/index" => ["read"],
            "notes/delete" => ["delete"],
            "notes/student" => ["read", "export"],
            "notes/batch-entry" => ["create"],
            "notes/classe_report" => ["read"],
            "notes/entry" => ["create"],
            "notes/evaluation_report" => ["read"],
            "notes/matiere_report" => ["read"],
            "notes/periode_report" => ["read"],
            "notes/predefined_report" => ["read"],
            "notes/reports" => ["read"],
            "notes/statistics" => ["read"],
            "statistics/class-ranking" => ["read"],
            "statistics/evaluation-reports" => ["read"],
            "statistics/index" => ["read"],
            "statistics/student-performance" => ["read"],
            "statistics/subject-analysis" => ["read"]

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
        "payslip" => ["read"],
        "create-account" => ["create"]

    ],
    "finance" => [
        "index" => ["read"],
        "fees/add" => ["create"],
        "fees/edit" => ["edit"],
        "fees/view" => ["read"],
        "fees/templates" => ["read"],
        "fees/index" => ["read"],
        "fees/manage" => ["read"],
        "fees/bulk-add" => ["create"],
        "fees/duplicate" => ["create"],
        "fees/delete" => ["delete"],
        "payments/add" => ["create"],
        "payments/edit" => ["edit"],
        "payments/view" => ["read"],
        "payments/index" => ["read"],
        "payments/delete" => ["delete"],
        "payments/cancel" => ["delete"],
        "payments/export" => ["export"],
        "payments/search_eleves" => ["read"],
        "payments/get_eleves_for_payment" => ["read"],
        "payments/get_frais_by_classe" => ["read"],
        "payments/receipt" => ["read"],
        "payments/verification_correction" => ["read"],
        "expenses/add" => ["create"],
        "expenses/edit" => ["edit"],
        "expenses/view" => ["read"],
        "expenses/index" => ["read"],
        "expenses/delete" => ["delete"],
        "devises/index" => ["read", "admin"],
        "devises/add" => ["create"],
        "devises/edit" => ["edit"],
        "devises/delete" => ["delete"],
        "expenses/ajax_caisse_stats" => ["read"],
        "expenses/caisses" => ["read"],
        "expenses/historique_caisses" => ["read"],
        "expenses/index" => ["read"],
        "expenses/integration_paiements" => ["read"],
        "expenses/journal_caisse" => ["read"],
        "expenses/maintenance_caisses" => ["read"],
        "expenses/pay" => ["edit"],
        "reports/debtors" => ["read"],
        "reports/index" => ["read"],
        "reports/monthly" => ["read"],
        "reports/financial" => ["read", "export"],
        "fees/types/add" => ["create"],
        "fees/types/delete" => ["delete"],
        "fees/types/edit" => ["edit"],
        "fees/types/index" => ["read"],
        "fees/types/toggle-status" => ["edit"]
    ],
    "library" => [
        "index" => ["read"],
        "books/add" => ["create"],
        "books/edit" => ["edit"],
        "books/view" => ["read"],
        "books/index" => ["read"],
        "books/delete" => ["delete"],
        "books/categories" => ["read"],
        "books/import" => ["create"],
        "books/export" => ["export"],
        "books/update_database" => ["edit"],
        "loans/add" => ["create"],
        "loans/edit" => ["edit"],
        "loans/view" => ["read"],
        "loans/index" => ["read"],
        "loans/delete" => ["delete"],
        "loans/returns" => ["edit"],
        "reservations/add" => ["create"],
        "reservations/index" => ["read"],
        "settings/index" => ["edit"],
        "reports/inventory" => ["read"]
    ],
    "communication" => [
        "messages/add" => ["create"],
        "messages/edit" => ["edit"],
        "messages/view" => ["read"],
        "messages/index" => ["read"],
        "messages/delete" => ["delete"],
        "annonces/add" => ["create"],
        "annonces/edit" => ["edit"],
        "annonces/view" => ["read"],
        "annonces/index" => ["read"],
        "annonces/delete" => ["delete"],
        "sms/send" => ["create"],
        "sms/index" => ["read"],
        "templates/add" => ["create"],
        "templates/edit" => ["edit"],
        "templates/view" => ["read"],
        "templates/index" => ["read"],
        "templates/delete" => ["delete"],
        "communication/annonces/add" => ["create"],
        "communication/messages/compose" => ["create"],
        "communication/messages/index" => ["read"],
        "communication/messages/view" => ["read"],
        "communication/sms/index" => ["read"],
        "communication/sms/send" => ["create"],
        "communication/templates/index" => ["read"],
        "communication/index" => ["read"]

    ],
    "discipline" => [
        "incidents/add" => ["create"],
        "incidents/edit" => ["edit"],
        "incidents/view" => ["read"],
        "incidents/index" => ["read"],
        "incidents/delete" => ["delete"],
        "sanctions/add" => ["create"],
        "sanctions/edit" => ["edit"],
        "sanctions/view" => ["read"],
        "sanctions/index" => ["read"],
        "sanctions/delete" => ["delete"],
        "recompenses/add" => ["create"],
        "recompenses/edit" => ["edit"],
        "recompenses/view" => ["read"],
        "recompenses/index" => ["read"],
        "recompenses/delete" => ["delete"],
        "reports/index" => ["read", "export"],
        "incidents/search_eleves" => ["read"],
        "sanctions/search_eleves" => ["read"],
        "discipline/index" => ["read"]
    ],
    "cartes_eleves" => [
        "cartes_eleves/actions" => ["edit"],
        "cartes_eleves/list" => ["read"],
        "cartes_eleves/view" => ["read"],
        "cartes_eleves/index" => ["read"],
        "cartes_eleves/delete" => ["delete"],
        "cartes_eleves/generate" => ["create"],
        "cartes_eleves/download-qr" => ["read"],
        "cartes_eleves/auto-generate" => ["create"],
        "cartes_eleves/parametres" => ["admin"],
        "cartes_eleves/export" => ["export"],
        "cartes_eleves/import" => ["import"],
        "cartes_eleves/suspend" => ["edit"],
        "cartes_eleves/archive" => ["edit"],
        "cartes_eleves/activate" => ["edit"],
        "cartes_eleves/export" => ["export"],
        "cartes_eleves/download" => ["read"],
        "cartes_eleves/get-students" => ["read"],
        "cartes_eleves/print-all" => ["read"],
        "cartes_eleves/print" => ["read"],
        "cartes_eleves/qr-actions" => ["read"],
        "cartes_eleves/qr-scanner" => ["read"],
        "cartes_eleves/regenerate-all" => ["edit"],
        "cartes_eleves/settings" => ["edit"]
    ],
    "recouvrement" => [
        "cartes/add" => ["create"],
        "cartes/edit" => ["edit"],
        "cartes/view" => ["read"],
        "cartes/index" => ["read"],
        "cartes/delete" => ["delete"],
        "cartes/generate" => ["create"],
        "cartes/print" => ["read"],
        "campaigns/add" => ["create"],
        "campaigns/edit" => ["edit"],
        "campaigns/view" => ["read"],
        "campaigns/index" => ["read"],
        "campaigns/delete" => ["delete"],
        "campaigns/details" => ["read"],
        "parametres/index" => ["edit"],
        "presences/index" => ["read"],
        "solvabilite/index" => ["read"],
        "rapports/index" => ["read"],
        "paiements/index" => ["read"],
        "notifications/index" => ["read"],
        "index" => ["read"],
        "scan-qr" => ["read"],
        "reports/index" => ["read"],
        "frais/index" => ["read"],
        "rapports/solvabilite" => ["read"],
        "rapports/comparatif" => ["read"],
        "rapports/export" => ["read"],
        "rapports/presences" => ["read"],
        "rapports/paiements" => ["read"]
        
       
    ],
    "reports" => [
        "index" => ["read"],
        "academic/index" => ["read"],
        "academic/analysis/detailed" => ["read"],
        "academic/bulletins/generate-all" => ["read"],
        "academic/comparison/classes" => ["read"],
        "academic/trends/evolution" => ["read"],
        "academic/export" => ["read"],
        "financial/index" => ["read"],
        "financial/export" => ["export"],
        "administrative/index" => ["read"],
        "administrative/export" => ["export"],
        "custom/index" => ["read"],
        "custom/export" => ["export"],
        "library/reports/index" => ["read"]
    ],

   

    "complementary" => [
        "communication/index" => ["read"],
        "discipline/index" => ["read"],
        "health/index" => ["read"],
        "internat/index" => ["read"],
        "inventory/index" => ["read"],
        "library/index" => ["read"],
        "transport/index" => ["read"],
        "complementary/index" => ["read"]
    ],

    "admin" => [
        "users/index" => ["read"],
        "users/add" => ["create"],
        "users/edit" => ["edit"],
        "users/delete" => ["delete"],
        "users/list" => ["read"],
        "users/view" => ["read"],
        "roles/index" => ["read"],
        "roles/add" => ["create"],
        "roles/edit" => ["edit"],
        "roles/delete" => ["delete"],
        "settings/index" => ["admin"],
        "logs/index" => ["read"],
        "sessions/index" => ["read"],
        "roles/get-role-permissions" => ["read"]

    ]
];

try {
    echo "🚀 Initialisation de la structure des permissions...\n\n";
    
    // Vérifier si la table roles existe
    $check_table = $database->query("SHOW TABLES LIKE 'roles'");
    if ($check_table->rowCount() == 0) {
        echo "❌ La table 'roles' n'existe pas. Veuillez d'abord créer la table.\n";
        exit(1);
    }
    
    // Créer un rôle de test avec la nouvelle structure
    $role_name = "Test Role - " . date('Y-m-d H:i:s');
    $role_description = "Rôle de test avec la nouvelle structure de permissions";
    
    // Convertir la structure en JSON
    $permissions_json = json_encode($permissions_structure, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    
    // Insérer le nouveau rôle
    $sql = "INSERT INTO roles (nom, description, permissions, actif, date_creation) VALUES (?, ?, ?, 1, NOW())";
    $result = $database->execute($sql, [$role_name, $role_description, $permissions_json]);
    
    if ($result) {
        $role_id = $database->lastInsertId();
        echo "✅ Rôle créé avec succès !\n";
        echo "   - ID: $role_id\n";
        echo "   - Nom: $role_name\n";
        echo "   - Description: $role_description\n\n";
        
        // Afficher les statistiques
        $modules_count = count($permissions_structure);
        $total_pages = 0;
        $total_actions = 0;
        
        foreach ($permissions_structure as $module => $pages) {
            $total_pages += count($pages);
            foreach ($pages as $page => $actions) {
                $total_actions += count($actions);
            }
        }
        
        echo "📊 Statistiques de la structure :\n";
        echo "   - Modules: $modules_count\n";
        echo "   - Pages totales: $total_pages\n";
        echo "   - Actions totales: $total_actions\n\n";
        
        // Afficher un aperçu de la structure
        echo "🔍 Aperçu de la structure JSON :\n";
        echo json_encode(array_slice($permissions_structure, 0, 2, true), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        echo "\n... (structure complète dans la base de données)\n\n";
        
        echo "💡 Pour utiliser ce rôle :\n";
        echo "   1. Assignez ce rôle à un utilisateur dans la table 'users'\n";
        echo "   2. Le système de permissions utilisera automatiquement cette structure\n";
        echo "   3. Vous pouvez créer d'autres rôles avec des permissions différentes\n\n";
        
    } else {
        echo "❌ Erreur lors de la création du rôle.\n";
    }
    
} catch (Exception $e) {
    echo "❌ Erreur: " . $e->getMessage() . "\n";
    echo "   Fichier: " . $e->getFile() . "\n";
    echo "   Ligne: " . $e->getLine() . "\n";
}

echo "\n🎯 Script terminé.\n";
?>
