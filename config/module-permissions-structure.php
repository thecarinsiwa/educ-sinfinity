<?php
/**
 * Configuration des permissions par module - Version Actualisée
 * Système de gestion scolaire - République Démocratique du Congo
 * 
 * Structure complète basée sur liste_permissions_modules.txt
 * Total: 15 modules, 247 pages, 8 actions
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

// Structure des permissions par module (basée sur liste_permissions_modules.txt)
define('MODULE_PERMISSIONS_STRUCTURE', [
    'academic' => [
        'name' => 'Gestion Académique',
        'icon' => 'fas fa-graduation-cap',
        'description' => 'Classes, matières, emploi du temps, années scolaires',
        'pages' => [
            'index' => ['read'],
            'classes/index' => ['read'],
            'classes/add' => ['create'],
            'classes/edit' => ['edit'],
            'classes/view' => ['read'],
            'classes/export' => ['export'],
            'subjects/index' => ['read'],
            'subjects/add' => ['create'],
            'subjects/edit' => ['edit'],
            'subjects/delete' => ['delete'],
            'subjects/view' => ['read'],
            'subjects/export' => ['export'],
            'schedule/index' => ['read'],
            'schedule/add' => ['create'],
            'schedule/add-schedule' => ['create'],
            'schedule/edit-schedule' => ['edit'],
            'schedule/class' => ['read'],
            'schedule/conflicts' => ['read'],
            'schedule/detect-conflicts' => ['read'],
            'schedule/resolve-conflict' => ['edit'],
            'schedule/generate' => ['create'],
            'schedule/export' => ['export'],
            'years/index' => ['read'],
            'years/add' => ['create'],
            'years/edit' => ['edit'],
            'evaluations/index' => ['read'],
            'evaluations/view' => ['read'],
            'notes/add' => ['create'],
            'notes/student' => ['read']
        ]
    ],
    'students' => [
        'name' => 'Gestion des Élèves',
        'icon' => 'fas fa-user-graduate',
        'description' => 'Inscriptions, présences, dossiers, transferts, admissions',
        'pages' => [
            'index' => ['read'],
            'add' => ['create'],
            'edit' => ['edit'],
            'view' => ['read'],
            'list' => ['read'],
            'search' => ['read'],
            'reports' => ['read', 'export'],
            'enrollment' => ['create', 'edit'],
            're-enrollment' => ['create', 'edit'],
            'change-status' => ['edit'],
            'confirm-inscriptions' => ['edit'],
            'enrollment-history' => ['read'],
            'admissions/index' => ['read'],
            'admissions/new-application' => ['create'],
            'admissions/direct-enrollment' => ['create'],
            'admissions/bulk-import' => ['import'],
            'admissions/applications/index' => ['read'],
            'admissions/applications/add' => ['create'],
            'admissions/applications/edit' => ['edit'],
            'admissions/applications/view' => ['read'],
            'admissions/applications/process' => ['edit'],
            'admissions/applications/update_status' => ['edit'],
            'admissions/enrollment/index' => ['read'],
            'admissions/enrollment/get-candidature' => ['read'],
            'admissions/evaluation/index' => ['read'],
            'admissions/evaluation/get-evaluation' => ['read'],
            'admissions/documents/index' => ['read'],
            'admissions/exports/applications' => ['export'],
            'admissions/reports/admission-stats' => ['read', 'export'],
            'admissions/settings/criteria' => ['admin'],
            'attendance/index' => ['read'],
            'attendance/add-absence' => ['create'],
            'attendance/add-delay' => ['create'],
            'attendance/bulk-attendance' => ['create', 'edit'],
            'attendance/edit' => ['edit'],
            'attendance/justify-absence' => ['edit'],
            'attendance/get-students' => ['read'],
            'attendance/get-absence-history' => ['read'],
            'attendance/log-action' => ['create'],
            'attendance/exports/attendance' => ['export'],
            'attendance/exports/preview-data' => ['read'],
            'attendance/notifications/parents' => ['create'],
            'attendance/notifications/send-single-notification' => ['create'],
            'attendance/reports/monthly' => ['read', 'export'],
            'records/index' => ['read'],
            'records/view' => ['read'],
            'records/edit' => ['edit'],
            'records/documents' => ['read', 'create'],
            'student-tracking/index' => ['read'],
            'student-tracking/follow-up/index' => ['read'],
            'student-tracking/evaluations/index' => ['read'],
            'student-tracking/evaluations/add' => ['create'],
            'student-tracking/decisions/index' => ['read'],
            'student-tracking/decisions/take-decision' => ['create'],
            'transfers/index' => ['read'],
            'transfers/view' => ['read'],
            'transfers/view-transfer' => ['read'],
            'transfers/new-transfer' => ['create'],
            'transfers/new-exit' => ['create'],
            'transfers/process' => ['edit'],
            'transfers/bulk-process' => ['edit'],
            'transfers/certificate' => ['read', 'print'],
            'transfers/certificates/index' => ['read'],
            'transfers/certificates/generate' => ['create'],
            'transfers/exports/movements' => ['export'],
            'transfers/reports/transfers' => ['read', 'export']
        ]
    ],
    'finance' => [
        'name' => 'Gestion Financière',
        'icon' => 'fas fa-money-bill-wave',
        'description' => 'Frais, paiements, dépenses, rapports financiers',
        'pages' => [
            'index' => ['read'],
            'devises/index' => ['read', 'admin'],
            'fees/index' => ['read'],
            'fees/add' => ['create'],
            'fees/edit' => ['edit'],
            'fees/delete' => ['delete'],
            'fees/view' => ['read'],
            'fees/bulk-add' => ['create'],
            'fees/duplicate' => ['create'],
            'fees/manage' => ['edit'],
            'fees/templates' => ['read', 'create'],
            'fees/types/index' => ['read'],
            'fees/types/add' => ['create'],
            'fees/types/edit' => ['edit'],
            'fees/types/delete' => ['delete'],
            'fees/types/view' => ['read'],
            'fees/types/toggle-status' => ['edit'],
            'fees/types/init-default-types' => ['admin'],
            'payments/index' => ['read'],
            'payments/add' => ['create'],
            'payments/edit' => ['edit'],
            'payments/cancel' => ['edit'],
            'payments/view' => ['read'],
            'payments/receipt' => ['read', 'print'],
            'payments/export' => ['export'],
            'payments/search_eleves' => ['read'],
            'payments/get_eleves_for_payment' => ['read'],
            'payments/get_frais_by_classe' => ['read'],
            'payments/get-priority-fee-type' => ['read'],
            'expenses/index' => ['read'],
            'expenses/add' => ['create'],
            'expenses/edit' => ['edit'],
            'expenses/view' => ['read'],
            'expenses/pay' => ['create'],
            'expenses/caisses' => ['read', 'admin'],
            'expenses/journal_caisse' => ['read'],
            'expenses/historique_caisses' => ['read'],
            'expenses/maintenance_caisses' => ['admin'],
            'expenses/integration_paiements' => ['edit'],
            'expenses/ajax_caisse_stats' => ['read'],
            'expenses/caisse_functions' => ['admin'],
            'reports/index' => ['read'],
            'reports/debtors' => ['read', 'export'],
            'reports/monthly' => ['read', 'export']
        ]
    ],
    'evaluations' => [
        'name' => 'Évaluations et Notes',
        'icon' => 'fas fa-chart-line',
        'description' => 'Évaluations, notes, bulletins, statistiques',
        'pages' => [
            'index' => ['read'],
            'class' => ['read'],
            'teacher' => ['read'],
            'evaluations/index' => ['read'],
            'evaluations/add' => ['create'],
            'evaluations/edit' => ['edit'],
            'evaluations/delete' => ['delete'],
            'evaluations/view' => ['read'],
            'notes/index' => ['read'],
            'notes/entry' => ['create'],
            'notes/batch-entry' => ['create'],
            'notes/student' => ['read'],
            'notes/reports' => ['read'],
            'notes/statistics' => ['read'],
            'notes/classe_report' => ['read', 'export'],
            'notes/matiere_report' => ['read', 'export'],
            'notes/periode_report' => ['read', 'export'],
            'notes/evaluation_report' => ['read', 'export'],
            'notes/predefined_report' => ['read', 'export'],
            'bulletins/index' => ['read'],
            'bulletins/generate' => ['create'],
            'bulletins/individual' => ['read'],
            'bulletins/batch_bulletins' => ['create'],
            'bulletins/preview' => ['read'],
            'bulletins/download' => ['read', 'export'],
            'bulletins/bulletin_template' => ['read', 'edit'],
            'statistics/index' => ['read'],
            'statistics/class-ranking' => ['read'],
            'statistics/student-performance' => ['read'],
            'statistics/subject-analysis' => ['read'],
            'statistics/evaluation-reports' => ['read', 'export']
        ]
    ],
    'recouvrement' => [
        'name' => 'Recouvrement',
        'icon' => 'fas fa-hand-holding-usd',
        'description' => 'Campagnes, cartes, paiements, rapports de recouvrement',
        'pages' => [
            'index' => ['read'],
            'campaigns/index' => ['read'],
            'campaigns/edit' => ['edit'],
            'campaigns/details' => ['read'],
            'cartes/index' => ['read'],
            'cartes/generate' => ['create'],
            'cartes/view' => ['read'],
            'cartes/print' => ['print'],
            'frais/index' => ['read'],
            'notifications/index' => ['read'],
            'paiements/index' => ['read'],
            'parametres/index' => ['admin'],
            'rapports/index' => ['read'],
            'rapports/export' => ['export'],
            'rapports/paiements' => ['read', 'export'],
            'rapports/presences' => ['read', 'export'],
            'rapports/comparatif' => ['read', 'export'],
            'rapports/solvabilite' => ['read', 'export'],
            'reports/index' => ['read'],
            'reports/export' => ['export'],
            'solvabilite/index' => ['read'],
            'scan-qr' => ['read']
        ]
    ],
    'cartes_eleves' => [
        'name' => 'Cartes d\'Élèves',
        'icon' => 'fas fa-id-card',
        'description' => 'Génération et gestion des cartes d\'élèves',
        'pages' => [
            'index' => ['read'],
            'view' => ['read'],
            'generate_card' => ['create'],
            'auto-generate' => ['create'],
            'regenerate-all' => ['edit'],
            'regenerate-qr-codes' => ['edit'],
            'print' => ['print'],
            'print-all' => ['print'],
            'download' => ['read'],
            'download-qr' => ['read'],
            'qr-generator' => ['create'],
            'simple-qr-generator' => ['create'],
            'qr-scanner' => ['read'],
            'qr-actions' => ['edit'],
            'actions' => ['edit'],
            'get-students' => ['read'],
            'integration-paiements' => ['edit'],
            'integration-presences' => ['edit'],
            'settings' => ['admin'],
            'install' => ['admin']
        ]
    ],
    'library' => [
        'name' => 'Bibliothèque',
        'icon' => 'fas fa-book',
        'description' => 'Gestion des livres, prêts et réservations',
        'pages' => [
            'index' => ['read'],
            'books/index' => ['read'],
            'books/add' => ['create'],
            'books/edit' => ['edit'],
            'books/view' => ['read'],
            'books/delete' => ['delete'],
            'books/export' => ['export'],
            'books/import' => ['import'],
            'books/categories' => ['read', 'admin'],
            'books/update_database' => ['admin'],
            'loans/index' => ['read'],
            'loans/add' => ['create'],
            'loans/returns' => ['edit'],
            'loans/check_table' => ['admin'],
            'loans/create_table' => ['admin'],
            'loans/fix_database' => ['admin'],
            'reservations/add' => ['create'],
            'reports/inventory' => ['read', 'export'],
            'settings/index' => ['admin']
        ]
    ],
    'reports' => [
        'name' => 'Rapports et Statistiques',
        'icon' => 'fas fa-chart-pie',
        'description' => 'Rapports académiques, financiers et administratifs',
        'pages' => [
            'index' => ['read'],
            'academic/index' => ['read'],
            'academic/export' => ['export'],
            'academic/analysis/detailed' => ['read'],
            'academic/bulletins/generate-all' => ['create'],
            'academic/comparison/classes' => ['read'],
            'academic/trends/evolution' => ['read'],
            'financial/index' => ['read'],
            'administrative/index' => ['read'],
            'custom/index' => ['read'],
            'exports/config' => ['admin']
        ]
    ],
    'discipline' => [
        'name' => 'Discipline',
        'icon' => 'fas fa-gavel',
        'description' => 'Incidents, sanctions et récompenses',
        'pages' => [
            'index' => ['read'],
            'incidents/index' => ['read'],
            'incidents/add' => ['create'],
            'incidents/view' => ['read'],
            'incidents/search_eleves' => ['read'],
            'sanctions/index' => ['read'],
            'sanctions/add' => ['create'],
            'sanctions/search_eleves' => ['read'],
            'recompenses/index' => ['read'],
            'recompenses/add' => ['create'],
            'reports/index' => ['read', 'export']
        ]
    ],
    'personnel' => [
        'name' => 'Personnel',
        'icon' => 'fas fa-users',
        'description' => 'Gestion du personnel et paie',
        'pages' => [
            'index' => ['read'],
            'add' => ['create'],
            'edit' => ['edit'],
            'view' => ['read'],
            'delete' => ['delete'],
            'export' => ['export'],
            'import' => ['import'],
            'payroll' => ['read', 'admin'],
            'payslip' => ['read', 'print'],
            'create-account' => ['create']
        ]
    ],
    'users' => [
        'name' => 'Utilisateurs',
        'icon' => 'fas fa-user-cog',
        'description' => 'Gestion des utilisateurs et sessions',
        'pages' => [
            'index' => ['read'],
            'add' => ['create'],
            'edit' => ['edit'],
            'view' => ['read'],
            'list' => ['read'],
            'logs/index' => ['read'],
            'roles/index' => ['admin'],
            'roles/get-role-permissions' => ['read'],
            'sessions/index' => ['read']
        ]
    ],
    'communication' => [
        'name' => 'Communication',
        'icon' => 'fas fa-comments',
        'description' => 'Messages, SMS et annonces',
        'pages' => [
            'index' => ['read'],
            'annonces/add' => ['create'],
            'messages/index' => ['read'],
            'messages/compose' => ['create'],
            'messages/view' => ['read'],
            'sms/index' => ['read'],
            'sms/send' => ['create'],
            'templates/index' => ['read', 'admin']
        ]
    ],
    'complementary' => [
        'name' => 'Services Complémentaires',
        'icon' => 'fas fa-plus-circle',
        'description' => 'Services additionnels de l\'école',
        'pages' => [
            'index' => ['read'],
            'communication/index' => ['read'],
            'discipline/index' => ['read'],
            'health/index' => ['read'],
            'internat/index' => ['read'],
            'inventory/index' => ['read'],
            'library/index' => ['read'],
            'transport/index' => ['read']
        ]
    ],
    'admissions' => [
        'name' => 'Admissions',
        'icon' => 'fas fa-user-plus',
        'description' => 'Gestion des admissions et candidatures',
        'pages' => [
            'index' => ['read'],
            'applications/list' => ['read'],
            'applications/view' => ['read'],
            'applications/evaluate' => ['edit'],
            'students/view' => ['read']
        ]
    ],
    'admin' => [
        'name' => 'Administration',
        'icon' => 'fas fa-cogs',
        'description' => 'Administration système et rôles',
        'pages' => [
            'pending-users' => ['admin'],
            'users/index' => ['admin'],
            'roles_add' => ['create'],
            'roles_edit' => ['edit'],
            'roles_view' => ['read'],
            'roles_delete' => ['delete'],
            'roles_bulk' => ['edit', 'delete']
        ]
    ]
]);

/**
 * Obtenir la structure des permissions par module
 */
function getModulePermissionsStructure() {
    return MODULE_PERMISSIONS_STRUCTURE;
}

/**
 * Obtenir les actions disponibles (version module)
 */
function getModuleAvailableActions() {
    return AVAILABLE_ACTIONS;
}

/**
 * Obtenir les permissions d'un module spécifique
 */
function getModulePermissions($module_key) {
    return MODULE_PERMISSIONS_STRUCTURE[$module_key] ?? [];
}

/**
 * Obtenir toutes les pages d'un module
 */
function getModulePages($module_key) {
    $module = getModulePermissions($module_key);
    return $module['pages'] ?? [];
}

/**
 * Obtenir les statistiques des permissions
 */
function getPermissionsStats() {
    $stats = [
        'total_modules' => count(MODULE_PERMISSIONS_STRUCTURE),
        'total_pages' => 0,
        'total_actions' => count(AVAILABLE_ACTIONS),
        'modules' => []
    ];
    
    foreach (MODULE_PERMISSIONS_STRUCTURE as $module_key => $module) {
        $page_count = count($module['pages']);
        $stats['total_pages'] += $page_count;
        $stats['modules'][$module_key] = [
            'name' => $module['name'],
            'pages' => $page_count,
            'icon' => $module['icon']
        ];
    }
    
    return $stats;
}

/**
 * Obtenir un résumé des permissions pour un rôle
 */
function getRolePermissionsSummary($permissions_json) {
    if (empty($permissions_json)) {
        return ['total_permissions' => 0, 'total_modules' => 0, 'modules' => []];
    }
    
    $permissions = json_decode($permissions_json, true);
    if (!$permissions) {
        return ['total_permissions' => 0, 'total_modules' => 0, 'modules' => []];
    }
    
    $summary = [
        'total_permissions' => 0,
        'total_modules' => count($permissions),
        'modules' => []
    ];
    
    foreach ($permissions as $module_key => $module_data) {
        $module_permissions = 0;
        if (isset($module_data['pages'])) {
            foreach ($module_data['pages'] as $page_key => $page_data) {
                if (isset($page_data['permissions'])) {
                    $module_permissions += count($page_data['permissions']);
                } elseif (isset($page_data['pages'])) {
                    foreach ($page_data['pages'] as $subpage_data) {
                        if (isset($subpage_data['permissions'])) {
                            $module_permissions += count($subpage_data['permissions']);
                        }
                    }
                }
            }
        }
        
        $summary['total_permissions'] += $module_permissions;
        $summary['modules'][$module_key] = [
            'name' => $module_data['name'] ?? $module_key,
            'permissions' => $module_permissions
        ];
    }
    
    return $summary;
}

/**
 * Vérifier si un module existe dans la structure
 */
function moduleExists($module_key) {
    return isset(MODULE_PERMISSIONS_STRUCTURE[$module_key]);
}

/**
 * Vérifier si une page existe dans un module
 */
function pageExists($module_key, $page_key) {
    $module = getModulePermissions($module_key);
    return isset($module['pages'][$page_key]);
}

/**
 * Obtenir les actions disponibles pour une page
 */
function getPageActions($module_key, $page_key) {
    $module = getModulePermissions($module_key);
    return $module['pages'][$page_key] ?? [];
}

/**
 * Générer un aperçu des permissions pour l'affichage
 */
function generatePermissionsPreview($permissions_json, $max_items = 5) {
    if (empty($permissions_json)) {
        return 'Aucune permission';
    }
    
    $permissions = json_decode($permissions_json, true);
    if (!$permissions) {
        return 'Aucune permission';
    }
    
    $preview = [];
    $count = 0;
    
    foreach ($permissions as $module_key => $module_data) {
        if ($count >= $max_items) break;
        
        $module_name = $module_data['name'] ?? $module_key;
        $preview[] = "<span class='badge bg-primary'>{$module_name}</span>";
        $count++;
    }
    
    $remaining = count($permissions) - $max_items;
    if ($remaining > 0) {
        $preview[] = "<small class='text-muted'>+ {$remaining} autres</small>";
    }
    
    return implode(' ', $preview);
}
?>