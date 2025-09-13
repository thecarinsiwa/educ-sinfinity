<?php
/**
 * Configuration des permissions par page
 * Système de gestion scolaire - République Démocratique du Congo
 * 
 * Ce fichier définit les permissions pour chaque page individuelle
 * au lieu des modules génériques pour un contrôle plus granulaire.
 */

// Actions disponibles
define('AVAILABLE_ACTIONS', [
    'read' => 'Lire',
    'create' => 'Créer', 
    'edit' => 'Modifier',
    'delete' => 'Supprimer',
    'export' => 'Exporter',
    'import' => 'Importer',
    'print' => 'Imprimer',
    'admin' => 'Administrer'
]);

// Permissions par page - Structure: module/sous-module/page => [actions]
define('PAGE_PERMISSIONS', [
    // ========================================
    // MODULE ACADEMIC (30 pages)
    // ========================================
    'academic/index' => ['read'],
    'academic/classes/index' => ['read'],
    'academic/classes/add' => ['create'],
    'academic/classes/edit' => ['edit'],
    'academic/classes/view' => ['read'],
    'academic/classes/export' => ['export'],
    'academic/subjects/index' => ['read'],
    'academic/subjects/add' => ['create'],
    'academic/subjects/edit' => ['edit'],
    'academic/subjects/delete' => ['delete'],
    'academic/subjects/view' => ['read'],
    'academic/subjects/export' => ['export'],
    'academic/schedule/index' => ['read'],
    'academic/schedule/add' => ['create'],
    'academic/schedule/add-schedule' => ['create'],
    'academic/schedule/edit-schedule' => ['edit'],
    'academic/schedule/class' => ['read'],
    'academic/schedule/conflicts' => ['read'],
    'academic/schedule/detect-conflicts' => ['read'],
    'academic/schedule/resolve-conflict' => ['edit'],
    'academic/schedule/generate' => ['create'],
    'academic/schedule/export' => ['export'],
    'academic/years/index' => ['read'],
    'academic/years/add' => ['create'],
    'academic/years/edit' => ['edit'],
    'academic/evaluations/index' => ['read'],
    'academic/evaluations/view' => ['read'],
    'academic/notes/add' => ['create'],
    'academic/notes/student' => ['read'],
    'academic/schedule' => ['read'],

    // ========================================
    // MODULE STUDENTS (65 pages)
    // ========================================
    'students/index' => ['read'],
    'students/add' => ['create'],
    'students/edit' => ['edit'],
    'students/view' => ['read'],
    'students/list' => ['read'],
    'students/search' => ['read'],
    'students/reports' => ['read', 'export'],
    'students/enrollment' => ['create', 'edit'],
    'students/re-enrollment' => ['create', 'edit'],
    'students/change-status' => ['edit'],
    'students/confirm-inscriptions' => ['edit'],
    'students/enrollment-history' => ['read'],
    
    // Admissions
    'students/admissions/index' => ['read'],
    'students/admissions/new-application' => ['create'],
    'students/admissions/direct-enrollment' => ['create'],
    'students/admissions/bulk-import' => ['import'],
    'students/admissions/applications/index' => ['read'],
    'students/admissions/applications/add' => ['create'],
    'students/admissions/applications/edit' => ['edit'],
    'students/admissions/applications/view' => ['read'],
    'students/admissions/applications/process' => ['edit'],
    'students/admissions/applications/update_status' => ['edit'],
    'students/admissions/enrollment/index' => ['read'],
    'students/admissions/enrollment/get-candidature' => ['read'],
    'students/admissions/evaluation/index' => ['read'],
    'students/admissions/evaluation/get-evaluation' => ['read'],
    'students/admissions/documents/index' => ['read'],
    'students/admissions/exports/applications' => ['export'],
    'students/admissions/reports/admission-stats' => ['read', 'export'],
    'students/admissions/settings/criteria' => ['admin'],
    
    // Attendance
    'students/attendance/index' => ['read'],
    'students/attendance/add-absence' => ['create'],
    'students/attendance/add-delay' => ['create'],
    'students/attendance/bulk-attendance' => ['create', 'edit'],
    'students/attendance/edit' => ['edit'],
    'students/attendance/justify-absence' => ['edit'],
    'students/attendance/get-students' => ['read'],
    'students/attendance/get-absence-history' => ['read'],
    'students/attendance/log-action' => ['create'],
    'students/attendance/exports/attendance' => ['export'],
    'students/attendance/exports/preview-data' => ['read'],
    'students/attendance/notifications/parents' => ['create'],
    'students/attendance/notifications/send-single-notification' => ['create'],
    'students/attendance/reports/monthly' => ['read', 'export'],
    
    // Records
    'students/records/index' => ['read'],
    'students/records/view' => ['read'],
    'students/records/edit' => ['edit'],
    'students/records/documents' => ['read', 'create'],
    
    // Student-tracking
    'students/student-tracking/index' => ['read'],
    'students/student-tracking/follow-up/index' => ['read'],
    'students/student-tracking/evaluations/index' => ['read'],
    'students/student-tracking/evaluations/add' => ['create'],
    'students/student-tracking/decisions/index' => ['read'],
    'students/student-tracking/decisions/take-decision' => ['create'],
    
    // Transfers
    'students/transfers/index' => ['read'],
    'students/transfers/view' => ['read'],
    'students/transfers/view-transfer' => ['read'],
    'students/transfers/new-transfer' => ['create'],
    'students/transfers/new-exit' => ['create'],
    'students/transfers/process' => ['edit'],
    'students/transfers/bulk-process' => ['edit'],
    'students/transfers/certificate' => ['read', 'print'],
    'students/transfers/certificates/index' => ['read'],
    'students/transfers/certificates/generate' => ['create'],
    'students/transfers/exports/movements' => ['export'],
    'students/transfers/reports/transfers' => ['read', 'export'],

    // ========================================
    // MODULE FINANCE (47 pages)
    // ========================================
    'finance/index' => ['read'],
    'finance/devises/index' => ['read', 'admin'],
    'finance/fees/index' => ['read'],
    'finance/fees/add' => ['create'],
    'finance/fees/edit' => ['edit'],
    'finance/fees/delete' => ['delete'],
    'finance/fees/view' => ['read'],
    'finance/fees/bulk-add' => ['create'],
    'finance/fees/duplicate' => ['create'],
    'finance/fees/manage' => ['edit'],
    'finance/fees/templates' => ['read', 'create'],
    'finance/fees/types/index' => ['read'],
    'finance/fees/types/add' => ['create'],
    'finance/fees/types/edit' => ['edit'],
    'finance/fees/types/delete' => ['delete'],
    'finance/fees/types/view' => ['read'],
    'finance/fees/types/toggle-status' => ['edit'],
    'finance/fees/types/init-default-types' => ['admin'],
    'finance/payments/index' => ['read'],
    'finance/payments/add' => ['create'],
    'finance/payments/edit' => ['edit'],
    'finance/payments/cancel' => ['edit'],
    'finance/payments/view' => ['read'],
    'finance/payments/receipt' => ['read', 'print'],
    'finance/payments/export' => ['export'],
    'finance/payments/search_eleves' => ['read'],
    'finance/payments/get_eleves_for_payment' => ['read'],
    'finance/payments/get_frais_by_classe' => ['read'],
    'finance/payments/get-priority-fee-type' => ['read'],
    'finance/expenses/index' => ['read'],
    'finance/expenses/add' => ['create'],
    'finance/expenses/edit' => ['edit'],
    'finance/expenses/view' => ['read'],
    'finance/expenses/pay' => ['create'],
    'finance/expenses/caisses' => ['read', 'admin'],
    'finance/expenses/journal_caisse' => ['read'],
    'finance/expenses/historique_caisses' => ['read'],
    'finance/expenses/maintenance_caisses' => ['admin'],
    'finance/expenses/integration_paiements' => ['edit'],
    'finance/expenses/ajax_caisse_stats' => ['read'],
    'finance/expenses/caisse_functions' => ['admin'],
    'finance/reports/index' => ['read'],
    'finance/reports/debtors' => ['read', 'export'],
    'finance/reports/monthly' => ['read', 'export'],

    // ========================================
    // MODULE EVALUATIONS (25 pages)
    // ========================================
    'evaluations/index' => ['read'],
    'evaluations/class' => ['read'],
    'evaluations/teacher' => ['read'],
    'evaluations/evaluations/index' => ['read'],
    'evaluations/evaluations/add' => ['create'],
    'evaluations/evaluations/edit' => ['edit'],
    'evaluations/evaluations/delete' => ['delete'],
    'evaluations/evaluations/view' => ['read'],
    'evaluations/notes/index' => ['read'],
    'evaluations/notes/entry' => ['create'],
    'evaluations/notes/batch-entry' => ['create'],
    'evaluations/notes/student' => ['read'],
    'evaluations/notes/reports' => ['read'],
    'evaluations/notes/statistics' => ['read'],
    'evaluations/notes/classe_report' => ['read', 'export'],
    'evaluations/notes/matiere_report' => ['read', 'export'],
    'evaluations/notes/periode_report' => ['read', 'export'],
    'evaluations/notes/evaluation_report' => ['read', 'export'],
    'evaluations/notes/predefined_report' => ['read', 'export'],
    'evaluations/bulletins/index' => ['read'],
    'evaluations/bulletins/generate' => ['create'],
    'evaluations/bulletins/individual' => ['read'],
    'evaluations/bulletins/batch_bulletins' => ['create'],
    'evaluations/bulletins/preview' => ['read'],
    'evaluations/bulletins/download' => ['read', 'export'],
    'evaluations/bulletins/bulletin_template' => ['read', 'edit'],
    'evaluations/statistics/index' => ['read'],
    'evaluations/statistics/class-ranking' => ['read'],
    'evaluations/statistics/student-performance' => ['read'],
    'evaluations/statistics/subject-analysis' => ['read'],
    'evaluations/statistics/evaluation-reports' => ['read', 'export'],

    // ========================================
    // MODULE RECOUVREMENT (23 pages)
    // ========================================
    'recouvrement/index' => ['read'],
    'recouvrement/campaigns/index' => ['read'],
    'recouvrement/campaigns/edit' => ['edit'],
    'recouvrement/campaigns/details' => ['read'],
    'recouvrement/cartes/index' => ['read'],
    'recouvrement/cartes/generate' => ['create'],
    'recouvrement/cartes/view' => ['read'],
    'recouvrement/cartes/print' => ['print'],
    'recouvrement/frais/index' => ['read'],
    'recouvrement/notifications/index' => ['read'],
    'recouvrement/paiements/index' => ['read'],
    'recouvrement/parametres/index' => ['admin'],
    'recouvrement/rapports/index' => ['read'],
    'recouvrement/rapports/export' => ['export'],
    'recouvrement/rapports/paiements' => ['read', 'export'],
    'recouvrement/rapports/presences' => ['read', 'export'],
    'recouvrement/rapports/comparatif' => ['read', 'export'],
    'recouvrement/rapports/solvabilite' => ['read', 'export'],
    'recouvrement/reports/index' => ['read'],
    'recouvrement/reports/export' => ['export'],
    'recouvrement/solvabilite/index' => ['read'],
    'recouvrement/scan-qr' => ['read'],

    // ========================================
    // MODULE CARTES ELEVES (20 pages)
    // ========================================
    'cartes_eleves/index' => ['read'],
    'cartes_eleves/view' => ['read'],
    'cartes_eleves/generate_card' => ['create'],
    'cartes_eleves/auto-generate' => ['create'],
    'cartes_eleves/regenerate-all' => ['edit'],
    'cartes_eleves/regenerate-qr-codes' => ['edit'],
    'cartes_eleves/print' => ['print'],
    'cartes_eleves/print-all' => ['print'],
    'cartes_eleves/download' => ['read'],
    'cartes_eleves/download-qr' => ['read'],
    'cartes_eleves/qr-generator' => ['create'],
    'cartes_eleves/simple-qr-generator' => ['create'],
    'cartes_eleves/qr-scanner' => ['read'],
    'cartes_eleves/qr-actions' => ['edit'],
    'cartes_eleves/actions' => ['edit'],
    'cartes_eleves/get-students' => ['read'],
    'cartes_eleves/integration-paiements' => ['edit'],
    'cartes_eleves/integration-presences' => ['edit'],
    'cartes_eleves/settings' => ['admin'],
    'cartes_eleves/install' => ['admin'],

    // ========================================
    // MODULE LIBRARY (18 pages)
    // ========================================
    'library/index' => ['read'],
    'library/books/index' => ['read'],
    'library/books/add' => ['create'],
    'library/books/edit' => ['edit'],
    'library/books/view' => ['read'],
    'library/books/delete' => ['delete'],
    'library/books/export' => ['export'],
    'library/books/import' => ['import'],
    'library/books/categories' => ['read', 'admin'],
    'library/books/update_database' => ['admin'],
    'library/loans/index' => ['read'],
    'library/loans/add' => ['create'],
    'library/loans/returns' => ['edit'],
    'library/loans/check_table' => ['admin'],
    'library/loans/create_table' => ['admin'],
    'library/loans/fix_database' => ['admin'],
    'library/reservations/add' => ['create'],
    'library/reports/inventory' => ['read', 'export'],
    'library/settings/index' => ['admin'],

    // ========================================
    // MODULE REPORTS (11 pages)
    // ========================================
    'reports/index' => ['read'],
    'reports/academic/index' => ['read'],
    'reports/academic/export' => ['export'],
    'reports/academic/analysis/detailed' => ['read'],
    'reports/academic/bulletins/generate-all' => ['create'],
    'reports/academic/comparison/classes' => ['read'],
    'reports/academic/trends/evolution' => ['read'],
    'reports/financial/index' => ['read'],
    'reports/administrative/index' => ['read'],
    'reports/custom/index' => ['read'],
    'reports/exports/config' => ['admin'],

    // ========================================
    // MODULE DISCIPLINE (10 pages)
    // ========================================
    'discipline/index' => ['read'],
    'discipline/incidents/index' => ['read'],
    'discipline/incidents/add' => ['create'],
    'discipline/incidents/view' => ['read'],
    'discipline/incidents/search_eleves' => ['read'],
    'discipline/sanctions/index' => ['read'],
    'discipline/sanctions/add' => ['create'],
    'discipline/sanctions/search_eleves' => ['read'],
    'discipline/recompenses/index' => ['read'],
    'discipline/recompenses/add' => ['create'],
    'discipline/reports/index' => ['read', 'export'],

    // ========================================
    // MODULE PERSONNEL (10 pages)
    // ========================================
    'personnel/index' => ['read'],
    'personnel/add' => ['create'],
    'personnel/edit' => ['edit'],
    'personnel/view' => ['read'],
    'personnel/delete' => ['delete'],
    'personnel/export' => ['export'],
    'personnel/import' => ['import'],
    'personnel/payroll' => ['read', 'admin'],
    'personnel/payslip' => ['read', 'print'],
    'personnel/create-account' => ['create'],

    // ========================================
    // MODULE USERS (8 pages)
    // ========================================
    'users/index' => ['read'],
    'users/add' => ['create'],
    'users/edit' => ['edit'],
    'users/view' => ['read'],
    'users/list' => ['read'],
    'users/logs/index' => ['read'],
    'users/roles/index' => ['admin'],
    'users/roles/get-role-permissions' => ['read'],
    'users/sessions/index' => ['read'],

    // ========================================
    // MODULE COMMUNICATION (7 pages)
    // ========================================
    'communication/index' => ['read'],
    'communication/annonces/add' => ['create'],
    'communication/messages/index' => ['read'],
    'communication/messages/compose' => ['create'],
    'communication/messages/view' => ['read'],
    'communication/sms/index' => ['read'],
    'communication/sms/send' => ['create'],
    'communication/templates/index' => ['read', 'admin'],

    // ========================================
    // MODULE COMPLEMENTARY (8 pages)
    // ========================================
    'complementary/index' => ['read'],
    'complementary/communication/index' => ['read'],
    'complementary/discipline/index' => ['read'],
    'complementary/health/index' => ['read'],
    'complementary/internat/index' => ['read'],
    'complementary/inventory/index' => ['read'],
    'complementary/library/index' => ['read'],
    'complementary/transport/index' => ['read'],

    // ========================================
    // MODULE ADMISSIONS (5 pages)
    // ========================================
    'admissions/index' => ['read'],
    'admissions/applications/list' => ['read'],
    'admissions/applications/view' => ['read'],
    'admissions/applications/evaluate' => ['edit'],
    'admissions/students/view' => ['read'],

    // ========================================
    // MODULE ADMIN (7 pages)
    // ========================================
    'admin/pending-users' => ['admin'],
    'admin/users/index' => ['admin'],
    'admin/roles_add' => ['create'],
    'admin/roles_edit' => ['edit'],
    'admin/roles_view' => ['read'],
    'admin/roles_delete' => ['delete'],
    'admin/roles_bulk' => ['edit', 'delete']
]);

// Fonction pour obtenir les permissions d'une page
function getPagePermissions($page_path) {
    return PAGE_PERMISSIONS[$page_path] ?? [];
}

// Fonction pour vérifier si une action est autorisée sur une page
function isPageActionAllowed($page_path, $action) {
    $permissions = getPagePermissions($page_path);
    return in_array($action, $permissions);
}

// Fonction pour obtenir toutes les pages d'un module
function getModulePagesConfig($module_name) {
    $pages = [];
    foreach (PAGE_PERMISSIONS as $page => $permissions) {
        if (strpos($page, $module_name . '/') === 0) {
            $pages[] = $page;
        }
    }
    return $pages;
}

// Fonction pour obtenir le nombre total de pages
function getTotalPagesConfig() {
    return count(PAGE_PERMISSIONS);
}

// Fonction pour obtenir les statistiques par module
function getModuleStatsConfig() {
    $stats = [];
    foreach (PAGE_PERMISSIONS as $page => $permissions) {
        $module = explode('/', $page)[0];
        if (!isset($stats[$module])) {
            $stats[$module] = 0;
        }
        $stats[$module]++;
    }
    return $stats;
}
?>
