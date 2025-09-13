================================================================================
                    LISTE COMPLÈTE DES PERMISSIONS - MODULES
================================================================================

📋 Système de gestion scolaire - République Démocratique du Congo
🔧 Fonction: requirePagePermission('module', 'page', 'action', 'chemin_redirection')

📊 STATISTIQUES GÉNÉRALES:
- Total des fichiers PHP: 309 fichiers
- Total des pages configurées: 247 pages avec permissions
- Modules principaux: 15 modules
- Actions disponibles: 8 actions (read, create, edit, delete, export, import, print, admin)

================================================================================
                                MODULE ACADEMIC (30 pages)
================================================================================

// Page principale
requirePagePermission('academic', 'index', 'read', '../../dashboard.php');

// Classes
requirePagePermission('academic', 'classes/index', 'read', '../../../dashboard.php');
requirePagePermission('academic', 'classes/add', 'create', '../../../dashboard.php');
requirePagePermission('academic', 'classes/edit', 'edit', '../../../dashboard.php');
requirePagePermission('academic', 'classes/view', 'read', '../../../dashboard.php');
requirePagePermission('academic', 'classes/export', 'export', '../../../dashboard.php');

// Matières
requirePagePermission('academic', 'subjects/index', 'read', '../../../dashboard.php');
requirePagePermission('academic', 'subjects/add', 'create', '../../../dashboard.php');
requirePagePermission('academic', 'subjects/edit', 'edit', '../../../dashboard.php');
requirePagePermission('academic', 'subjects/delete', 'delete', '../../../dashboard.php');
requirePagePermission('academic', 'subjects/view', 'read', '../../../dashboard.php');
requirePagePermission('academic', 'subjects/export', 'export', '../../../dashboard.php');

// Emploi du temps
requirePagePermission('academic', 'schedule/index', 'read', '../../../dashboard.php');
requirePagePermission('academic', 'schedule/add', 'create', '../../../dashboard.php');
requirePagePermission('academic', 'schedule/add-schedule', 'create', '../../../dashboard.php');
requirePagePermission('academic', 'schedule/edit-schedule', 'edit', '../../../dashboard.php');
requirePagePermission('academic', 'schedule/class', 'read', '../../../dashboard.php');
requirePagePermission('academic', 'schedule/conflicts', 'read', '../../../dashboard.php');
requirePagePermission('academic', 'schedule/detect-conflicts', 'read', '../../../dashboard.php');
requirePagePermission('academic', 'schedule/resolve-conflict', 'edit', '../../../dashboard.php');
requirePagePermission('academic', 'schedule/generate', 'create', '../../../dashboard.php');
requirePagePermission('academic', 'schedule/export', 'export', '../../../dashboard.php');

// Années scolaires
requirePagePermission('academic', 'years/index', 'read', '../../../dashboard.php');
requirePagePermission('academic', 'years/add', 'create', '../../../dashboard.php');
requirePagePermission('academic', 'years/edit', 'edit', '../../../dashboard.php');

// Évaluations
requirePagePermission('academic', 'evaluations/index', 'read', '../../../dashboard.php');
requirePagePermission('academic', 'evaluations/view', 'read', '../../../dashboard.php');

// Notes
requirePagePermission('academic', 'notes/add', 'create', '../../../dashboard.php');
requirePagePermission('academic', 'notes/student', 'read', '../../../dashboard.php');

================================================================================
                                MODULE STUDENTS (65 pages)
================================================================================

// Page principale et gestion
requirePagePermission('students', 'index', 'read', '../../dashboard.php');
requirePagePermission('students', 'add', 'create', '../../dashboard.php');
requirePagePermission('students', 'edit', 'edit', '../../dashboard.php');
requirePagePermission('students', 'view', 'read', '../../dashboard.php');
requirePagePermission('students', 'list', 'read', '../../dashboard.php');
requirePagePermission('students', 'search', 'read', '../../dashboard.php');
requirePagePermission('students', 'reports', 'read', '../../dashboard.php');
requirePagePermission('students', 'enrollment', 'create', '../../dashboard.php');
requirePagePermission('students', 're-enrollment', 'create', '../../dashboard.php');
requirePagePermission('students', 'change-status', 'edit', '../../dashboard.php');
requirePagePermission('students', 'confirm-inscriptions', 'edit', '../../dashboard.php');
requirePagePermission('students', 'enrollment-history', 'read', '../../dashboard.php');

// Admissions
requirePagePermission('students', 'admissions/index', 'read', '../../../dashboard.php');
requirePagePermission('students', 'admissions/new-application', 'create', '../../../dashboard.php');
requirePagePermission('students', 'admissions/direct-enrollment', 'create', '../../../dashboard.php');
requirePagePermission('students', 'admissions/bulk-import', 'import', '../../../dashboard.php');
requirePagePermission('students', 'admissions/applications/index', 'read', '../../../dashboard.php');
requirePagePermission('students', 'admissions/applications/add', 'create', '../../../dashboard.php');
requirePagePermission('students', 'admissions/applications/edit', 'edit', '../../../dashboard.php');
requirePagePermission('students', 'admissions/applications/view', 'read', '../../../dashboard.php');
requirePagePermission('students', 'admissions/applications/process', 'edit', '../../../dashboard.php');
requirePagePermission('students', 'admissions/applications/update_status', 'edit', '../../../dashboard.php');
requirePagePermission('students', 'admissions/enrollment/index', 'read', '../../../dashboard.php');
requirePagePermission('students', 'admissions/enrollment/get-candidature', 'read', '../../../dashboard.php');
requirePagePermission('students', 'admissions/evaluation/index', 'read', '../../../dashboard.php');
requirePagePermission('students', 'admissions/evaluation/get-evaluation', 'read', '../../../dashboard.php');
requirePagePermission('students', 'admissions/documents/index', 'read', '../../../dashboard.php');
requirePagePermission('students', 'admissions/exports/applications', 'export', '../../../dashboard.php');
requirePagePermission('students', 'admissions/reports/admission-stats', 'read', '../../../dashboard.php');
requirePagePermission('students', 'admissions/settings/criteria', 'admin', '../../../dashboard.php');

// Présences
requirePagePermission('students', 'attendance/index', 'read', '../../../dashboard.php');
requirePagePermission('students', 'attendance/add-absence', 'create', '../../../dashboard.php');
requirePagePermission('students', 'attendance/add-delay', 'create', '../../../dashboard.php');
requirePagePermission('students', 'attendance/bulk-attendance', 'create', '../../../dashboard.php');
requirePagePermission('students', 'attendance/edit', 'edit', '../../../dashboard.php');
requirePagePermission('students', 'attendance/justify-absence', 'edit', '../../../dashboard.php');
requirePagePermission('students', 'attendance/get-students', 'read', '../../../dashboard.php');
requirePagePermission('students', 'attendance/get-absence-history', 'read', '../../../dashboard.php');
requirePagePermission('students', 'attendance/log-action', 'create', '../../../dashboard.php');
requirePagePermission('students', 'attendance/exports/attendance', 'export', '../../../dashboard.php');
requirePagePermission('students', 'attendance/exports/preview-data', 'read', '../../../dashboard.php');
requirePagePermission('students', 'attendance/notifications/parents', 'create', '../../../dashboard.php');
requirePagePermission('students', 'attendance/notifications/send-single-notification', 'create', '../../../dashboard.php');
requirePagePermission('students', 'attendance/reports/monthly', 'read', '../../../dashboard.php');

// Dossiers
requirePagePermission('students', 'records/index', 'read', '../../../dashboard.php');
requirePagePermission('students', 'records/view', 'read', '../../../dashboard.php');
requirePagePermission('students', 'records/edit', 'edit', '../../../dashboard.php');
requirePagePermission('students', 'records/documents', 'read', '../../../dashboard.php');

// Suivi des élèves
requirePagePermission('students', 'student-tracking/index', 'read', '../../../dashboard.php');
requirePagePermission('students', 'student-tracking/follow-up/index', 'read', '../../../dashboard.php');
requirePagePermission('students', 'student-tracking/evaluations/index', 'read', '../../../dashboard.php');
requirePagePermission('students', 'student-tracking/evaluations/add', 'create', '../../../dashboard.php');
requirePagePermission('students', 'student-tracking/decisions/index', 'read', '../../../dashboard.php');
requirePagePermission('students', 'student-tracking/decisions/take-decision', 'create', '../../../dashboard.php');

// Transfers
requirePagePermission('students', 'transfers/index', 'read', '../../../dashboard.php');
requirePagePermission('students', 'transfers/view', 'read', '../../../dashboard.php');
requirePagePermission('students', 'transfers/view-transfer', 'read', '../../../dashboard.php');
requirePagePermission('students', 'transfers/new-transfer', 'create', '../../../dashboard.php');
requirePagePermission('students', 'transfers/new-exit', 'create', '../../../dashboard.php');
requirePagePermission('students', 'transfers/process', 'edit', '../../../dashboard.php');
requirePagePermission('students', 'transfers/bulk-process', 'edit', '../../../dashboard.php');
requirePagePermission('students', 'transfers/certificate', 'read', '../../../dashboard.php');
requirePagePermission('students', 'transfers/certificates/index', 'read', '../../../dashboard.php');
requirePagePermission('students', 'transfers/certificates/generate', 'create', '../../../dashboard.php');
requirePagePermission('students', 'transfers/exports/movements', 'export', '../../../dashboard.php');
requirePagePermission('students', 'transfers/reports/transfers', 'read', '../../../dashboard.php');

================================================================================
                                MODULE FINANCE (47 pages)
================================================================================

// Page principale
requirePagePermission('finance', 'index', 'read', '../../dashboard.php');

// Devises
requirePagePermission('finance', 'devises/index', 'read', '../../../dashboard.php');

// Frais
requirePagePermission('finance', 'fees/index', 'read', '../../../dashboard.php');
requirePagePermission('finance', 'fees/add', 'create', '../../../dashboard.php');
requirePagePermission('finance', 'fees/edit', 'edit', '../../../dashboard.php');
requirePagePermission('finance', 'fees/delete', 'delete', '../../../dashboard.php');
requirePagePermission('finance', 'fees/view', 'read', '../../../dashboard.php');
requirePagePermission('finance', 'fees/bulk-add', 'create', '../../../dashboard.php');
requirePagePermission('finance', 'fees/duplicate', 'create', '../../../dashboard.php');
requirePagePermission('finance', 'fees/manage', 'edit', '../../../dashboard.php');
requirePagePermission('finance', 'fees/templates', 'read', '../../../dashboard.php');

// Types de frais
requirePagePermission('finance', 'fees/types/index', 'read', '../../../dashboard.php');
requirePagePermission('finance', 'fees/types/add', 'create', '../../../dashboard.php');
requirePagePermission('finance', 'fees/types/edit', 'edit', '../../../dashboard.php');
requirePagePermission('finance', 'fees/types/delete', 'delete', '../../../dashboard.php');
requirePagePermission('finance', 'fees/types/view', 'read', '../../../dashboard.php');
requirePagePermission('finance', 'fees/types/toggle-status', 'edit', '../../../dashboard.php');
requirePagePermission('finance', 'fees/types/init-default-types', 'admin', '../../../dashboard.php');

// Paiements
requirePagePermission('finance', 'payments/index', 'read', '../../../dashboard.php');
requirePagePermission('finance', 'payments/add', 'create', '../../../dashboard.php');
requirePagePermission('finance', 'payments/edit', 'edit', '../../../dashboard.php');
requirePagePermission('finance', 'payments/cancel', 'edit', '../../../dashboard.php');
requirePagePermission('finance', 'payments/view', 'read', '../../../dashboard.php');
requirePagePermission('finance', 'payments/receipt', 'read', '../../../dashboard.php');
requirePagePermission('finance', 'payments/export', 'export', '../../../dashboard.php');
requirePagePermission('finance', 'payments/search_eleves', 'read', '../../../dashboard.php');
requirePagePermission('finance', 'payments/get_eleves_for_payment', 'read', '../../../dashboard.php');
requirePagePermission('finance', 'payments/get_frais_by_classe', 'read', '../../../dashboard.php');
requirePagePermission('finance', 'payments/get-priority-fee-type', 'read', '../../../dashboard.php');

// Dépenses
requirePagePermission('finance', 'expenses/index', 'read', '../../../dashboard.php');
requirePagePermission('finance', 'expenses/add', 'create', '../../../dashboard.php');
requirePagePermission('finance', 'expenses/edit', 'edit', '../../../dashboard.php');
requirePagePermission('finance', 'expenses/view', 'read', '../../../dashboard.php');
requirePagePermission('finance', 'expenses/pay', 'create', '../../../dashboard.php');
requirePagePermission('finance', 'expenses/caisses', 'read', '../../../dashboard.php');
requirePagePermission('finance', 'expenses/journal_caisse', 'read', '../../../dashboard.php');
requirePagePermission('finance', 'expenses/historique_caisses', 'read', '../../../dashboard.php');
requirePagePermission('finance', 'expenses/maintenance_caisses', 'admin', '../../../dashboard.php');
requirePagePermission('finance', 'expenses/integration_paiements', 'edit', '../../../dashboard.php');
requirePagePermission('finance', 'expenses/ajax_caisse_stats', 'read', '../../../dashboard.php');
requirePagePermission('finance', 'expenses/caisse_functions', 'admin', '../../../dashboard.php');

// Rapports financiers
requirePagePermission('finance', 'reports/index', 'read', '../../../dashboard.php');
requirePagePermission('finance', 'reports/debtors', 'read', '../../../dashboard.php');
requirePagePermission('finance', 'reports/monthly', 'read', '../../../dashboard.php');

================================================================================
                                MODULE EVALUATIONS (25 pages)
================================================================================

// Page principale
requirePagePermission('evaluations', 'index', 'read', '../../dashboard.php');
requirePagePermission('evaluations', 'class', 'read', '../../dashboard.php');
requirePagePermission('evaluations', 'teacher', 'read', '../../dashboard.php');

// Gestion des évaluations
requirePagePermission('evaluations', 'evaluations/index', 'read', '../../../dashboard.php');
requirePagePermission('evaluations', 'evaluations/add', 'create', '../../../dashboard.php');
requirePagePermission('evaluations', 'evaluations/edit', 'edit', '../../../dashboard.php');
requirePagePermission('evaluations', 'evaluations/delete', 'delete', '../../../dashboard.php');
requirePagePermission('evaluations', 'evaluations/view', 'read', '../../../dashboard.php');

// Gestion des notes
requirePagePermission('evaluations', 'notes/index', 'read', '../../../dashboard.php');
requirePagePermission('evaluations', 'notes/entry', 'create', '../../../dashboard.php');
requirePagePermission('evaluations', 'notes/batch-entry', 'create', '../../../dashboard.php');
requirePagePermission('evaluations', 'notes/student', 'read', '../../../dashboard.php');
requirePagePermission('evaluations', 'notes/reports', 'read', '../../../dashboard.php');
requirePagePermission('evaluations', 'notes/statistics', 'read', '../../../dashboard.php');
requirePagePermission('evaluations', 'notes/classe_report', 'read', '../../../dashboard.php');
requirePagePermission('evaluations', 'notes/matiere_report', 'read', '../../../dashboard.php');
requirePagePermission('evaluations', 'notes/periode_report', 'read', '../../../dashboard.php');
requirePagePermission('evaluations', 'notes/evaluation_report', 'read', '../../../dashboard.php');
requirePagePermission('evaluations', 'notes/predefined_report', 'read', '../../../dashboard.php');

// Bulletins
requirePagePermission('evaluations', 'bulletins/index', 'read', '../../../dashboard.php');
requirePagePermission('evaluations', 'bulletins/generate', 'create', '../../../dashboard.php');
requirePagePermission('evaluations', 'bulletins/individual', 'read', '../../../dashboard.php');
requirePagePermission('evaluations', 'bulletins/batch_bulletins', 'create', '../../../dashboard.php');
requirePagePermission('evaluations', 'bulletins/preview', 'read', '../../../dashboard.php');
requirePagePermission('evaluations', 'bulletins/download', 'read', '../../../dashboard.php');
requirePagePermission('evaluations', 'bulletins/bulletin_template', 'read', '../../../dashboard.php');

// Statistiques
requirePagePermission('evaluations', 'statistics/index', 'read', '../../../dashboard.php');
requirePagePermission('evaluations', 'statistics/class-ranking', 'read', '../../../dashboard.php');
requirePagePermission('evaluations', 'statistics/student-performance', 'read', '../../../dashboard.php');
requirePagePermission('evaluations', 'statistics/subject-analysis', 'read', '../../../dashboard.php');
requirePagePermission('evaluations', 'statistics/evaluation-reports', 'read', '../../../dashboard.php');

================================================================================
                                MODULE RECOUVREMENT (23 pages)
================================================================================

// Page principale
requirePagePermission('recouvrement', 'index', 'read', '../../dashboard.php');

// Campagnes
requirePagePermission('recouvrement', 'campaigns/index', 'read', '../../../dashboard.php');
requirePagePermission('recouvrement', 'campaigns/edit', 'edit', '../../../dashboard.php');
requirePagePermission('recouvrement', 'campaigns/details', 'read', '../../../dashboard.php');

// Cartes
requirePagePermission('recouvrement', 'cartes/index', 'read', '../../../dashboard.php');
requirePagePermission('recouvrement', 'cartes/generate', 'create', '../../../dashboard.php');
requirePagePermission('recouvrement', 'cartes/view', 'read', '../../../dashboard.php');
requirePagePermission('recouvrement', 'cartes/print', 'print', '../../../dashboard.php');

// Frais
requirePagePermission('recouvrement', 'frais/index', 'read', '../../../dashboard.php');

// Notifications
requirePagePermission('recouvrement', 'notifications/index', 'read', '../../../dashboard.php');

// Paiements
requirePagePermission('recouvrement', 'paiements/index', 'read', '../../../dashboard.php');

// Paramètres
requirePagePermission('recouvrement', 'parametres/index', 'admin', '../../../dashboard.php');

// Rapports
requirePagePermission('recouvrement', 'rapports/index', 'read', '../../../dashboard.php');
requirePagePermission('recouvrement', 'rapports/export', 'export', '../../../dashboard.php');
requirePagePermission('recouvrement', 'rapports/paiements', 'read', '../../../dashboard.php');
requirePagePermission('recouvrement', 'rapports/presences', 'read', '../../../dashboard.php');
requirePagePermission('recouvrement', 'rapports/comparatif', 'read', '../../../dashboard.php');
requirePagePermission('recouvrement', 'rapports/solvabilite', 'read', '../../../dashboard.php');

// Reports
requirePagePermission('recouvrement', 'reports/index', 'read', '../../../dashboard.php');
requirePagePermission('recouvrement', 'reports/export', 'export', '../../../dashboard.php');

// Solvabilité
requirePagePermission('recouvrement', 'solvabilite/index', 'read', '../../../dashboard.php');

// Scanner QR
requirePagePermission('recouvrement', 'scan-qr', 'read', '../../../dashboard.php');

================================================================================
                                MODULE CARTES ELEVES (20 pages)
================================================================================

// Page principale
requirePagePermission('cartes_eleves', 'index', 'read', '../../dashboard.php');
requirePagePermission('cartes_eleves', 'view', 'read', '../../dashboard.php');

// Génération
requirePagePermission('cartes_eleves', 'generate_card', 'create', '../../dashboard.php');
requirePagePermission('cartes_eleves', 'auto-generate', 'create', '../../dashboard.php');
requirePagePermission('cartes_eleves', 'regenerate-all', 'edit', '../../dashboard.php');
requirePagePermission('cartes_eleves', 'regenerate-qr-codes', 'edit', '../../dashboard.php');

// Impression
requirePagePermission('cartes_eleves', 'print', 'print', '../../dashboard.php');
requirePagePermission('cartes_eleves', 'print-all', 'print', '../../dashboard.php');

// Téléchargement
requirePagePermission('cartes_eleves', 'download', 'read', '../../dashboard.php');
requirePagePermission('cartes_eleves', 'download-qr', 'read', '../../dashboard.php');

// QR Code
requirePagePermission('cartes_eleves', 'qr-generator', 'create', '../../dashboard.php');
requirePagePermission('cartes_eleves', 'simple-qr-generator', 'create', '../../dashboard.php');
requirePagePermission('cartes_eleves', 'qr-scanner', 'read', '../../dashboard.php');
requirePagePermission('cartes_eleves', 'qr-actions', 'edit', '../../dashboard.php');

// Actions
requirePagePermission('cartes_eleves', 'actions', 'edit', '../../dashboard.php');
requirePagePermission('cartes_eleves', 'get-students', 'read', '../../dashboard.php');

// Intégrations
requirePagePermission('cartes_eleves', 'integration-paiements', 'edit', '../../dashboard.php');
requirePagePermission('cartes_eleves', 'integration-presences', 'edit', '../../dashboard.php');

// Paramètres
requirePagePermission('cartes_eleves', 'settings', 'admin', '../../dashboard.php');
requirePagePermission('cartes_eleves', 'install', 'admin', '../../dashboard.php');

================================================================================
                                MODULE LIBRARY (18 pages)
================================================================================

// Page principale
requirePagePermission('library', 'index', 'read', '../../dashboard.php');

// Livres
requirePagePermission('library', 'books/index', 'read', '../../../dashboard.php');
requirePagePermission('library', 'books/add', 'create', '../../../dashboard.php');
requirePagePermission('library', 'books/edit', 'edit', '../../../dashboard.php');
requirePagePermission('library', 'books/view', 'read', '../../../dashboard.php');
requirePagePermission('library', 'books/delete', 'delete', '../../../dashboard.php');
requirePagePermission('library', 'books/export', 'export', '../../../dashboard.php');
requirePagePermission('library', 'books/import', 'import', '../../../dashboard.php');
requirePagePermission('library', 'books/categories', 'read', '../../../dashboard.php');
requirePagePermission('library', 'books/update_database', 'admin', '../../../dashboard.php');

// Prêts
requirePagePermission('library', 'loans/index', 'read', '../../../dashboard.php');
requirePagePermission('library', 'loans/add', 'create', '../../../dashboard.php');
requirePagePermission('library', 'loans/returns', 'edit', '../../../dashboard.php');
requirePagePermission('library', 'loans/check_table', 'admin', '../../../dashboard.php');
requirePagePermission('library', 'loans/create_table', 'admin', '../../../dashboard.php');
requirePagePermission('library', 'loans/fix_database', 'admin', '../../../dashboard.php');

// Réservations
requirePagePermission('library', 'reservations/add', 'create', '../../../dashboard.php');

// Rapports
requirePagePermission('library', 'reports/inventory', 'read', '../../../dashboard.php');

// Paramètres
requirePagePermission('library', 'settings/index', 'admin', '../../../dashboard.php');

================================================================================
                                MODULE REPORTS (11 pages)
================================================================================

// Page principale
requirePagePermission('reports', 'index', 'read', '../../dashboard.php');

// Rapports académiques
requirePagePermission('reports', 'academic/index', 'read', '../../../dashboard.php');
requirePagePermission('reports', 'academic/export', 'export', '../../../dashboard.php');
requirePagePermission('reports', 'academic/analysis/detailed', 'read', '../../../dashboard.php');
requirePagePermission('reports', 'academic/bulletins/generate-all', 'create', '../../../dashboard.php');
requirePagePermission('reports', 'academic/comparison/classes', 'read', '../../../dashboard.php');
requirePagePermission('reports', 'academic/trends/evolution', 'read', '../../../dashboard.php');

// Rapports financiers
requirePagePermission('reports', 'financial/index', 'read', '../../../dashboard.php');

// Rapports administratifs
requirePagePermission('reports', 'administrative/index', 'read', '../../../dashboard.php');

// Rapports personnalisés
requirePagePermission('reports', 'custom/index', 'read', '../../../dashboard.php');

// Configuration des exports
requirePagePermission('reports', 'exports/config', 'admin', '../../../dashboard.php');

================================================================================
                                MODULE DISCIPLINE (10 pages)
================================================================================

// Page principale
requirePagePermission('discipline', 'index', 'read', '../../dashboard.php');

// Incidents
requirePagePermission('discipline', 'incidents/index', 'read', '../../../dashboard.php');
requirePagePermission('discipline', 'incidents/add', 'create', '../../../dashboard.php');
requirePagePermission('discipline', 'incidents/view', 'read', '../../../dashboard.php');
requirePagePermission('discipline', 'incidents/search_eleves', 'read', '../../../dashboard.php');

// Sanctions
requirePagePermission('discipline', 'sanctions/index', 'read', '../../../dashboard.php');
requirePagePermission('discipline', 'sanctions/add', 'create', '../../../dashboard.php');
requirePagePermission('discipline', 'sanctions/search_eleves', 'read', '../../../dashboard.php');

// Récompenses
requirePagePermission('discipline', 'recompenses/index', 'read', '../../../dashboard.php');
requirePagePermission('discipline', 'recompenses/add', 'create', '../../../dashboard.php');

// Rapports
requirePagePermission('discipline', 'reports/index', 'read', '../../../dashboard.php');

================================================================================
                                MODULE PERSONNEL (10 pages)
================================================================================

// Page principale
requirePagePermission('personnel', 'index', 'read', '../../dashboard.php');
requirePagePermission('personnel', 'add', 'create', '../../dashboard.php');
requirePagePermission('personnel', 'edit', 'edit', '../../dashboard.php');
requirePagePermission('personnel', 'view', 'read', '../../dashboard.php');
requirePagePermission('personnel', 'delete', 'delete', '../../dashboard.php');
requirePagePermission('personnel', 'export', 'export', '../../dashboard.php');
requirePagePermission('personnel', 'import', 'import', '../../dashboard.php');
requirePagePermission('personnel', 'payroll', 'read', '../../dashboard.php');
requirePagePermission('personnel', 'payslip', 'read', '../../dashboard.php');
requirePagePermission('personnel', 'create-account', 'create', '../../dashboard.php');

================================================================================
                                MODULE USERS (8 pages)
================================================================================

// Page principale
requirePagePermission('users', 'index', 'read', '../../dashboard.php');
requirePagePermission('users', 'add', 'create', '../../dashboard.php');
requirePagePermission('users', 'edit', 'edit', '../../dashboard.php');
requirePagePermission('users', 'view', 'read', '../../dashboard.php');
requirePagePermission('users', 'list', 'read', '../../dashboard.php');

// Logs
requirePagePermission('users', 'logs/index', 'read', '../../../dashboard.php');

// Rôles
requirePagePermission('users', 'roles/index', 'admin', '../../../dashboard.php');
requirePagePermission('users', 'roles/get-role-permissions', 'read', '../../../dashboard.php');

// Sessions
requirePagePermission('users', 'sessions/index', 'read', '../../../dashboard.php');

================================================================================
                                MODULE COMMUNICATION (7 pages)
================================================================================

// Page principale
requirePagePermission('communication', 'index', 'read', '../../dashboard.php');

// Annonces
requirePagePermission('communication', 'annonces/add', 'create', '../../../dashboard.php');

// Messages
requirePagePermission('communication', 'messages/index', 'read', '../../../dashboard.php');
requirePagePermission('communication', 'messages/compose', 'create', '../../../dashboard.php');
requirePagePermission('communication', 'messages/view', 'read', '../../../dashboard.php');

// SMS
requirePagePermission('communication', 'sms/index', 'read', '../../../dashboard.php');
requirePagePermission('communication', 'sms/send', 'create', '../../../dashboard.php');

// Templates
requirePagePermission('communication', 'templates/index', 'read', '../../../dashboard.php');

================================================================================
                                MODULE COMPLEMENTARY (8 pages)
================================================================================

// Page principale
requirePagePermission('complementary', 'index', 'read', '../../dashboard.php');
requirePagePermission('complementary', 'communication/index', 'read', '../../../dashboard.php');
requirePagePermission('complementary', 'discipline/index', 'read', '../../../dashboard.php');
requirePagePermission('complementary', 'health/index', 'read', '../../../dashboard.php');
requirePagePermission('complementary', 'internat/index', 'read', '../../../dashboard.php');
requirePagePermission('complementary', 'inventory/index', 'read', '../../../dashboard.php');
requirePagePermission('complementary', 'library/index', 'read', '../../../dashboard.php');
requirePagePermission('complementary', 'transport/index', 'read', '../../../dashboard.php');

================================================================================
                                MODULE ADMISSIONS (5 pages)
================================================================================

// Page principale
requirePagePermission('admissions', 'index', 'read', '../../dashboard.php');

// Applications
requirePagePermission('admissions', 'applications/list', 'read', '../../../dashboard.php');
requirePagePermission('admissions', 'applications/view', 'read', '../../../dashboard.php');
requirePagePermission('admissions', 'applications/evaluate', 'edit', '../../../dashboard.php');

// Étudiants
requirePagePermission('admissions', 'students/view', 'read', '../../../dashboard.php');

================================================================================
                                MODULE ADMIN (7 pages)
================================================================================

// Utilisateurs en attente
requirePagePermission('admin', 'pending-users', 'admin', '../../dashboard.php');

// Gestion des utilisateurs
requirePagePermission('admin', 'users/index', 'admin', '../../../dashboard.php');

// Gestion des rôles
requirePagePermission('admin', 'roles_add', 'create', '../../dashboard.php');
requirePagePermission('admin', 'roles_edit', 'edit', '../../dashboard.php');
requirePagePermission('admin', 'roles_view', 'read', '../../dashboard.php');
requirePagePermission('admin', 'roles_delete', 'delete', '../../dashboard.php');
requirePagePermission('admin', 'roles_bulk', 'edit', '../../dashboard.php');

================================================================================
                                    GUIDE D'UTILISATION
================================================================================

📌 RÈGLES DE CALCUL DU CHEMIN DE REDIRECTION:

1. Pour les fichiers dans modules/module/ (ex: modules/finance/index.php):
   Chemin: ../../dashboard.php

2. Pour les fichiers dans modules/module/sous-dossier/ (ex: modules/students/records/view.php):
   Chemin: ../../../dashboard.php

3. Pour les fichiers dans modules/module/sous-dossier/sous-sous-dossier/:
   Chemin: ../../../../dashboard.php

📌 ACTIONS DISPONIBLES:
- read: Lire/Consulter
- create: Créer/Ajouter
- edit: Modifier/Éditer
- delete: Supprimer
- export: Exporter
- import: Importer
- print: Imprimer
- admin: Administrer

📌 EXEMPLE D'UTILISATION:
```php
// En haut de chaque fichier PHP
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../includes/functions.php';
require_once '../../includes/permissions-pages.php';

// Vérifier l'authentification et les permissions
requireLogin();
requirePagePermission('students', 'enrollment-history', 'read', '../../dashboard.php');
```

================================================================================
                                    FIN DU DOCUMENT
================================================================================

Généré le: <?php echo date('Y-m-d H:i:s'); ?>
Total des pages configurées: 247 pages
Total des modules: 15 modules
Système de gestion scolaire - République Démocratique du Congo
