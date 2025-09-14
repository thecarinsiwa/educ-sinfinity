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

// Traductions françaises des pages
define('PAGE_TRANSLATIONS', [
    // Pages générales
    'index' => 'Accueil',
    'add' => 'Ajouter',
    'edit' => 'Modifier',
    'delete' => 'Supprimer',
    'view' => 'Voir',
    'list' => 'Liste',
    'search' => 'Rechercher',
    'reports' => 'Rapports',
    'export' => 'Exporter',
    'import' => 'Importer',
    'print' => 'Imprimer',
    'settings' => 'Paramètres',
    'details' => 'Détails',
    'process' => 'Traiter',
    'generate' => 'Générer',
    'activate' => 'Activer',
    'close' => 'Fermer',
    'cancel' => 'Annuler',
    'confirm' => 'Confirmer',
    'approve' => 'Approuver',
    'reject' => 'Rejeter',
    'evaluate' => 'Évaluer',
    'bulk' => 'En lot',
    'batch' => 'Par lot',
    'template' => 'Modèle',
    'preview' => 'Aperçu',
    'download' => 'Télécharger',
    'upload' => 'Téléverser',
    'scan' => 'Scanner',
    'qr' => 'QR Code',
    'auto' => 'Automatique',
    'manual' => 'Manuel',
    'direct' => 'Direct',
    'new' => 'Nouveau',
    'old' => 'Ancien',
    'history' => 'Historique',
    'log' => 'Journal',
    'session' => 'Session',
    'role' => 'Rôle',
    'permission' => 'Permission',
    'user' => 'Utilisateur',
    'student' => 'Élève',
    'teacher' => 'Enseignant',
    'parent' => 'Parent',
    'class' => 'Classe',
    'subject' => 'Matière',
    'year' => 'Année',
    'schedule' => 'Emploi du temps',
    'timetable' => 'Horaire',
    'evaluation' => 'Évaluation',
    'note' => 'Note',
    'grade' => 'Note',
    'bulletin' => 'Bulletin',
    'attendance' => 'Présence',
    'absence' => 'Absence',
    'delay' => 'Retard',
    'justify' => 'Justifier',
    'transfer' => 'Transfert',
    'admission' => 'Admission',
    'application' => 'Candidature',
    'candidate' => 'Candidat',
    'enrollment' => 'Inscription',
    're-enrollment' => 'Réinscription',
    'fee' => 'Frais',
    'payment' => 'Paiement',
    'expense' => 'Dépense',
    'income' => 'Revenu',
    'budget' => 'Budget',
    'financial' => 'Financier',
    'academic' => 'Académique',
    'administrative' => 'Administratif',
    'statistics' => 'Statistiques',
    'analysis' => 'Analyse',
    'comparison' => 'Comparaison',
    'trend' => 'Tendance',
    'evolution' => 'Évolution',
    'ranking' => 'Classement',
    'performance' => 'Performance',
    'incident' => 'Incident',
    'sanction' => 'Sanction',
    'reward' => 'Récompense',
    'recompense' => 'Récompense',
    'discipline' => 'Discipline',
    'communication' => 'Communication',
    'message' => 'Message',
    'announcement' => 'Annonce',
    'annonce' => 'Annonce',
    'sms' => 'SMS',
    'email' => 'Email',
    'notification' => 'Notification',
    'template' => 'Modèle',
    'library' => 'Bibliothèque',
    'book' => 'Livre',
    'loan' => 'Prêt',
    'reservation' => 'Réservation',
    'inventory' => 'Inventaire',
    'personnel' => 'Personnel',
    'staff' => 'Personnel',
    'employee' => 'Employé',
    'payroll' => 'Paie',
    'payslip' => 'Bulletin de paie',
    'salary' => 'Salaire',
    'wage' => 'Salaire',
    'complementary' => 'Complémentaire',
    'health' => 'Santé',
    'internat' => 'Internat',
    'transport' => 'Transport',
    'carte' => 'Carte',
    'card' => 'Carte',
    'campaign' => 'Campagne',
    'recouvrement' => 'Recouvrement',
    'collection' => 'Recouvrement',
    'debtor' => 'Débiteur',
    'solvability' => 'Solvabilité',
    'solvabilite' => 'Solvabilité',
    'comparatif' => 'Comparatif',
    'presence' => 'Présence',
    'paiement' => 'Paiement',
    'rapport' => 'Rapport',
    'report' => 'Rapport',
    'frais' => 'Frais',
    'devise' => 'Devise',
    'currency' => 'Devise',
    'caisse' => 'Caisse',
    'cash' => 'Caisse',
    'journal' => 'Journal',
    'historique' => 'Historique',
    'maintenance' => 'Maintenance',
    'integration' => 'Intégration',
    'ajax' => 'AJAX',
    'function' => 'Fonction',
    'action' => 'Action',
    'get' => 'Obtenir',
    'send' => 'Envoyer',
    'receive' => 'Recevoir',
    'update' => 'Mettre à jour',
    'create' => 'Créer',
    'read' => 'Lire',
    'edit' => 'Modifier',
    'delete' => 'Supprimer',
    'view' => 'Voir',
    'show' => 'Afficher',
    'hide' => 'Masquer',
    'toggle' => 'Basculer',
    'switch' => 'Changer',
    'change' => 'Changer',
    'modify' => 'Modifier',
    'update' => 'Mettre à jour',
    'refresh' => 'Actualiser',
    'reload' => 'Recharger',
    'reset' => 'Réinitialiser',
    'clear' => 'Effacer',
    'clean' => 'Nettoyer',
    'fix' => 'Réparer',
    'repair' => 'Réparer',
    'check' => 'Vérifier',
    'verify' => 'Vérifier',
    'validate' => 'Valider',
    'approve' => 'Approuver',
    'reject' => 'Rejeter',
    'accept' => 'Accepter',
    'deny' => 'Refuser',
    'allow' => 'Autoriser',
    'forbid' => 'Interdire',
    'permit' => 'Permettre',
    'enable' => 'Activer',
    'disable' => 'Désactiver',
    'activate' => 'Activer',
    'deactivate' => 'Désactiver',
    'open' => 'Ouvrir',
    'close' => 'Fermer',
    'start' => 'Commencer',
    'stop' => 'Arrêter',
    'begin' => 'Commencer',
    'end' => 'Terminer',
    'finish' => 'Terminer',
    'complete' => 'Compléter',
    'finish' => 'Terminer',
    'done' => 'Terminé',
    'ready' => 'Prêt',
    'pending' => 'En attente',
    'waiting' => 'En attente',
    'processing' => 'En cours',
    'progress' => 'Progrès',
    'status' => 'Statut',
    'state' => 'État',
    'condition' => 'Condition',
    'situation' => 'Situation',
    'position' => 'Position',
    'location' => 'Emplacement',
    'place' => 'Lieu',
    'address' => 'Adresse',
    'contact' => 'Contact',
    'phone' => 'Téléphone',
    'mobile' => 'Mobile',
    'email' => 'Email',
    'website' => 'Site web',
    'url' => 'URL',
    'link' => 'Lien',
    'reference' => 'Référence',
    'code' => 'Code',
    'id' => 'ID',
    'number' => 'Numéro',
    'num' => 'Num',
    'date' => 'Date',
    'time' => 'Heure',
    'datetime' => 'Date et heure',
    'timestamp' => 'Horodatage',
    'created' => 'Créé',
    'updated' => 'Mis à jour',
    'modified' => 'Modifié',
    'deleted' => 'Supprimé',
    'active' => 'Actif',
    'inactive' => 'Inactif',
    'enabled' => 'Activé',
    'disabled' => 'Désactivé',
    'visible' => 'Visible',
    'hidden' => 'Masqué',
    'public' => 'Public',
    'private' => 'Privé',
    'confidential' => 'Confidentiel',
    'secret' => 'Secret',
    'secure' => 'Sécurisé',
    'safe' => 'Sûr',
    'dangerous' => 'Dangereux',
    'risky' => 'Risqué',
    'important' => 'Important',
    'urgent' => 'Urgent',
    'critical' => 'Critique',
    'major' => 'Majeur',
    'minor' => 'Mineur',
    'small' => 'Petit',
    'large' => 'Grand',
    'big' => 'Grand',
    'huge' => 'Énorme',
    'tiny' => 'Minuscule',
    'micro' => 'Micro',
    'macro' => 'Macro',
    'mini' => 'Mini',
    'maxi' => 'Maxi',
    'super' => 'Super',
    'ultra' => 'Ultra',
    'mega' => 'Méga',
    'giga' => 'Giga',
    'tera' => 'Téra',
    'peta' => 'Péta',
    'exa' => 'Exa',
    'zetta' => 'Zetta',
    'yotta' => 'Yotta'
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
            'classes/delete' => ['delete'],
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
            'schedule/delete' => ['delete'],
            'years/index' => ['read'],
            'years/add' => ['create'],
            'years/edit' => ['edit'],
            'years/delete' => ['delete'],
            'years/view' => ['read'],
            'years/export' => ['export'],
            'years/import' => ['import'],
            'years/print' => ['print'],
            'years/activate' => ['edit']
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
            'delete' => ['delete'],
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
            'admissions/applications/delete' => ['delete'],
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
            'class/index' => ['read'],
            'evaluations/index' => ['read'],
            'teacher/index' => ['read'],
            'bulletins/batch_bulletins' => ['create'],
            'bulletins/download' => ['read'],
            'bulletins/generate' => ['create'],
            'bulletins/individual' => ['read'],
            'bulletins/preview' => ['read'],
            'evaluations/add' => ['create'],
            'evaluations/edit' => ['edit'],
            'evaluations/view' => ['read'],
            'evaluations/delete' => ['delete'],
            'notes/add' => ['create'],
            'notes/edit' => ['edit'],
            'notes/view' => ['read'],
            'notes/index' => ['read'],
            'notes/delete' => ['delete'],
            'notes/student' => ['read', 'export'],
            'notes/batch-entry' => ['create'],
            'notes/classe_report' => ['read'],
            'notes/entry' => ['create'],
            'notes/evaluation_report' => ['read'],
            'notes/matiere_report' => ['read'],
            'notes/periode_report' => ['read'],
            'notes/predefined_report' => ['read'],
            'notes/reports' => ['read'],
            'notes/statistics' => ['read'],
            'statistics/class-ranking' => ['read'],
            'statistics/evaluation-reports' => ['read'],
            'statistics/index' => ['read'],
            'statistics/student-performance' => ['read'],
            'statistics/subject-analysis' => ['read']
        ]
    ],
    'recouvrement' => [
        'name' => 'Recouvrement',
        'icon' => 'fas fa-hand-holding-usd',
        'description' => 'Campagnes, cartes, paiements, rapports de recouvrement',
        'pages' => [
            'index' => ['read'],
            'campaigns/index' => ['read'],
            'campaigns/add' => ['create'],
            'campaigns/edit' => ['edit'],
            'campaigns/delete' => ['delete'],
            'campaigns/details' => ['read'],
            'cartes/index' => ['read'],
            'cartes/generate' => ['create'],
            'cartes/edit' => ['edit'],
            'cartes/delete' => ['delete'],
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
            'delete' => ['delete'],
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
            'books/import' => ['create'],
            'books/categories' => ['read'],
            'books/update_database' => ['edit'],
            'loans/index' => ['read'],
            'loans/add' => ['create'],
            'loans/edit' => ['edit'],
            'loans/view' => ['read'],
            'loans/delete' => ['delete'],
            'loans/returns' => ['edit'],
            'loans/check_table' => ['admin'],
            'loans/create_table' => ['admin'],
            'loans/fix_database' => ['admin'],
            'reservations/add' => ['create'],
            'reservations/index' => ['read'],
            'reports/index' => ['read'],
            'reports/inventory' => ['read'],
            'settings/index' => ['edit']
        ]
    ],
    'reports' => [
        'name' => 'Rapports et Statistiques',
        'icon' => 'fas fa-chart-pie',
        'description' => 'Rapports académiques, financiers et administratifs',
        'pages' => [
            'index' => ['read'],
            'academic/index' => ['read'],
            'academic/add' => ['create'],
            'academic/edit' => ['edit'],
            'academic/delete' => ['delete'],
            'academic/export' => ['export'],
            'academic/analysis/detailed' => ['read'],
            'academic/bulletins/generate-all' => ['create'],
            'academic/comparison/classes' => ['read'],
            'academic/trends/evolution' => ['read'],
            'financial/index' => ['read'],
            'administrative/index' => ['read'],
            'custom/index' => ['read'],
            'library/reports/index' => ['read'],
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
            'incidents/edit' => ['edit'],
            'incidents/delete' => ['delete'],
            'incidents/view' => ['read'],
            'incidents/search_eleves' => ['read'],
            'sanctions/index' => ['read'],
            'sanctions/add' => ['create'],
            'sanctions/edit' => ['edit'],
            'sanctions/delete' => ['delete'],
            'sanctions/search_eleves' => ['read'],
            'recompenses/index' => ['read'],
            'recompenses/add' => ['create'],
            'recompenses/edit' => ['edit'],
            'recompenses/delete' => ['delete'],
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
            'delete' => ['delete'],
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
            'annonces/edit' => ['edit'],
            'annonces/delete' => ['delete'],
            'messages/index' => ['read'],
            'messages/compose' => ['create'],
            'messages/edit' => ['edit'],
            'messages/delete' => ['delete'],
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
            'add' => ['create'],
            'edit' => ['edit'],
            'delete' => ['delete'],
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
            'applications/add' => ['create'],
            'applications/list' => ['read'],
            'applications/edit' => ['edit'],
            'applications/delete' => ['delete'],
            'applications/view' => ['read'],
            'applications/evaluate' => ['edit'],
            'students/add' => ['create'],
            'students/edit' => ['edit'],
            'students/delete' => ['delete'],
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
 * Traduire un nom de page en français
 */
function translatePageName($page_key) {
    $translations = PAGE_TRANSLATIONS;
    
    // Traductions spéciales pour les pages complètes
    $special_translations = [
        'classes/index' => 'Liste des Classes',
        'classes/add' => 'Ajouter une Classe',
        'classes/edit' => 'Modifier une Classe',
        'classes/delete' => 'Supprimer une Classe',
        'classes/view' => 'Voir une Classe',
        'classes/export' => 'Exporter les Classes',
        'subjects/index' => 'Liste des Matières',
        'subjects/add' => 'Ajouter une Matière',
        'subjects/edit' => 'Modifier une Matière',
        'subjects/delete' => 'Supprimer une Matière',
        'subjects/view' => 'Voir une Matière',
        'subjects/export' => 'Exporter les Matières',
        'years/index' => 'Liste des Années Scolaires',
        'years/add' => 'Ajouter une Année Scolaire',
        'years/edit' => 'Modifier une Année Scolaire',
        'years/delete' => 'Supprimer une Année Scolaire',
        'years/view' => 'Voir une Année Scolaire',
        'years/export' => 'Exporter les Années Scolaires',
        'years/import' => 'Importer les Années Scolaires',
        'years/print' => 'Imprimer les Années Scolaires',
        'years/activate' => 'Activer une Année Scolaire',
        'years/close' => 'Fermer une Année Scolaire',
        'schedule/index' => 'Liste des Emplois du Temps',
        'schedule/add' => 'Ajouter un Emploi du Temps',
        'schedule/edit' => 'Modifier un Emploi du Temps',
        'schedule/delete' => 'Supprimer un Emploi du Temps',
        'schedule/view' => 'Voir un Emploi du Temps',
        'schedule/export' => 'Exporter les Emplois du Temps',
        'attendance/index' => 'Liste des Présences',
        'attendance/add-absence' => 'Ajouter une Absence',
        'attendance/add-delay' => 'Ajouter un Retard',
        'attendance/bulk-attendance' => 'Présences en Lot',
        'attendance/justify-absence' => 'Justifier une Absence',
        'payments/index' => 'Liste des Paiements',
        'payments/add' => 'Ajouter un Paiement',
        'payments/edit' => 'Modifier un Paiement',
        'payments/delete' => 'Supprimer un Paiement',
        'payments/view' => 'Voir un Paiement',
        'payments/cancel' => 'Annuler un Paiement',
        'payments/receipt' => 'Reçu de Paiement',
        'fees/index' => 'Liste des Frais',
        'fees/add' => 'Ajouter des Frais',
        'fees/edit' => 'Modifier des Frais',
        'fees/delete' => 'Supprimer des Frais',
        'fees/view' => 'Voir des Frais',
        'fees/bulk-add' => 'Ajouter des Frais en Lot',
        'fees/duplicate' => 'Dupliquer des Frais',
        'fees/manage' => 'Gérer les Frais',
        'fees/templates' => 'Modèles de Frais',
        'fees/types/index' => 'Types de Frais',
        'fees/types/add' => 'Ajouter un Type de Frais',
        'fees/types/edit' => 'Modifier un Type de Frais',
        'fees/types/delete' => 'Supprimer un Type de Frais',
        'fees/types/view' => 'Voir un Type de Frais',
        'fees/types/toggle-status' => 'Activer/Désactiver un Type',
        'incidents/index' => 'Liste des Incidents',
        'incidents/add' => 'Ajouter un Incident',
        'incidents/edit' => 'Modifier un Incident',
        'incidents/delete' => 'Supprimer un Incident',
        'incidents/view' => 'Voir un Incident',
        'sanctions/index' => 'Liste des Sanctions',
        'sanctions/add' => 'Ajouter une Sanction',
        'sanctions/edit' => 'Modifier une Sanction',
        'sanctions/delete' => 'Supprimer une Sanction',
        'recompenses/index' => 'Liste des Récompenses',
        'recompenses/add' => 'Ajouter une Récompense',
        'recompenses/edit' => 'Modifier une Récompense',
        'recompenses/delete' => 'Supprimer une Récompense',
        'messages/index' => 'Liste des Messages',
        'messages/compose' => 'Composer un Message',
        'messages/edit' => 'Modifier un Message',
        'messages/delete' => 'Supprimer un Message',
        'messages/view' => 'Voir un Message',
        'annonces/add' => 'Ajouter une Annonce',
        'annonces/edit' => 'Modifier une Annonce',
        'annonces/delete' => 'Supprimer une Annonce',
        'sms/index' => 'Liste des SMS',
        'sms/send' => 'Envoyer un SMS',
        'templates/index' => 'Modèles de Messages',
        'books/index' => 'Liste des Livres',
        'books/add' => 'Ajouter un Livre',
        'books/edit' => 'Modifier un Livre',
        'books/delete' => 'Supprimer un Livre',
        'books/view' => 'Voir un Livre',
        'books/export' => 'Exporter les Livres',
        'books/import' => 'Importer les Livres',
        'loans/index' => 'Liste des Prêts',
        'loans/add' => 'Ajouter un Prêt',
        'loans/returns' => 'Retour de Prêt',
        'reservations/add' => 'Ajouter une Réservation',
        'reports/inventory' => 'Inventaire de la Bibliothèque',
        'settings/index' => 'Paramètres',
        'academic/index' => 'Rapports Académiques',
        'academic/add' => 'Ajouter un Rapport Académique',
        'academic/edit' => 'Modifier un Rapport Académique',
        'academic/delete' => 'Supprimer un Rapport Académique',
        'academic/export' => 'Exporter les Rapports Académiques',
        'financial/index' => 'Rapports Financiers',
        'administrative/index' => 'Rapports Administratifs',
        'custom/index' => 'Rapports Personnalisés',
        'users/index' => 'Liste des Utilisateurs',
        'users/add' => 'Ajouter un Utilisateur',
        'users/edit' => 'Modifier un Utilisateur',
        'users/delete' => 'Supprimer un Utilisateur',
        'users/view' => 'Voir un Utilisateur',
        'users/list' => 'Liste des Utilisateurs',
        'logs/index' => 'Journal des Activités',
        'roles/index' => 'Liste des Rôles',
        'roles/get-role-permissions' => 'Permissions d\'un Rôle',
        'sessions/index' => 'Sessions Actives',
        'personnel/index' => 'Liste du Personnel',
        'personnel/add' => 'Ajouter un Membre du Personnel',
        'personnel/edit' => 'Modifier un Membre du Personnel',
        'personnel/delete' => 'Supprimer un Membre du Personnel',
        'personnel/view' => 'Voir un Membre du Personnel',
        'personnel/export' => 'Exporter le Personnel',
        'personnel/import' => 'Importer le Personnel',
        'payroll' => 'Paie du Personnel',
        'payslip' => 'Bulletin de Paie',
        'create-account' => 'Créer un Compte',
        'cartes/index' => 'Liste des Cartes',
        'cartes/generate' => 'Générer des Cartes',
        'cartes/edit' => 'Modifier une Carte',
        'cartes/delete' => 'Supprimer une Carte',
        'cartes/view' => 'Voir une Carte',
        'cartes/print' => 'Imprimer des Cartes',
        'campaigns/index' => 'Liste des Campagnes',
        'campaigns/add' => 'Ajouter une Campagne',
        'campaigns/edit' => 'Modifier une Campagne',
        'campaigns/delete' => 'Supprimer une Campagne',
        'campaigns/details' => 'Détails d\'une Campagne',
        'rapports/index' => 'Liste des Rapports',
        'rapports/export' => 'Exporter les Rapports',
        'rapports/paiements' => 'Rapport des Paiements',
        'rapports/presences' => 'Rapport des Présences',
        'rapports/comparatif' => 'Rapport Comparatif',
        'rapports/solvabilite' => 'Rapport de Solvabilité',
        'scan-qr' => 'Scanner QR Code',
        'qr-generator' => 'Générateur QR Code',
        'qr-scanner' => 'Scanner QR Code',
        'qr-actions' => 'Actions QR Code',
        'integration-paiements' => 'Intégration Paiements',
        'integration-presences' => 'Intégration Présences'
    ];
    
    // Vérifier d'abord les traductions spéciales
    if (isset($special_translations[$page_key])) {
        return $special_translations[$page_key];
    }
    
    // Diviser le nom de la page par les séparateurs
    $parts = preg_split('/[\/_\-]/', $page_key);
    $translated_parts = [];
    
    foreach ($parts as $part) {
        if (isset($translations[$part])) {
            $translated_parts[] = $translations[$part];
        } else {
            // Si pas de traduction, capitaliser la première lettre
            $translated_parts[] = ucfirst($part);
        }
    }
    
    return implode(' ', $translated_parts);
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