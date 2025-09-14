<?php
/**
 * Configuration des dashboards personnalisés
 * Système de gestion scolaire - République Démocratique du Congo
 * 
 * Définit les modules et menus pour chaque nature d'utilisateur
 */

// Configuration des dashboards par nature d'utilisateur
define('DASHBOARD_CONFIG', [
    'admin' => [
        'name' => 'Administration',
        'title' => 'Tableau de Bord Administrateur',
        'description' => 'Gestion complète de l\'établissement scolaire',
        'icon' => 'fas fa-crown',
        'color' => 'danger',
        'modules' => [
            'admin' => [
                'name' => 'Administration',
                'icon' => 'fas fa-cogs',
                'description' => 'Gestion système et configuration',
                'pages' => [
                    'users/index' => ['name' => 'Utilisateurs', 'icon' => 'fas fa-users'],
                    'roles/index' => ['name' => 'Rôles', 'icon' => 'fas fa-user-shield'],
                    'settings/index' => ['name' => 'Paramètres', 'icon' => 'fas fa-cog']
                ]
            ],
            'users' => [
                'name' => 'Utilisateurs',
                'icon' => 'fas fa-user-cog',
                'description' => 'Gestion des comptes utilisateurs',
                'pages' => [
                    'index' => ['name' => 'Liste des Utilisateurs', 'icon' => 'fas fa-list'],
                    'add' => ['name' => 'Ajouter Utilisateur', 'icon' => 'fas fa-plus'],
                    'sessions/index' => ['name' => 'Sessions Actives', 'icon' => 'fas fa-clock']
                ]
            ],
            'personnel' => [
                'name' => 'Personnel',
                'icon' => 'fas fa-users',
                'description' => 'Gestion du personnel',
                'pages' => [
                    'index' => ['name' => 'Liste du Personnel', 'icon' => 'fas fa-list'],
                    'add' => ['name' => 'Ajouter Membre', 'icon' => 'fas fa-plus'],
                    'payroll' => ['name' => 'Paie', 'icon' => 'fas fa-money-bill']
                ]
            ],
            'finance' => [
                'name' => 'Gestion Financière',
                'icon' => 'fas fa-money-bill-wave',
                'description' => 'Frais, paiements et comptabilité',
                'pages' => [
                    'index' => ['name' => 'Vue d\'ensemble', 'icon' => 'fas fa-chart-pie'],
                    'fees/index' => ['name' => 'Frais Scolaires', 'icon' => 'fas fa-receipt'],
                    'payments/index' => ['name' => 'Paiements', 'icon' => 'fas fa-credit-card']
                ]
            ],
            'reports' => [
                'name' => 'Rapports',
                'icon' => 'fas fa-chart-bar',
                'description' => 'Statistiques et analyses',
                'pages' => [
                    'index' => ['name' => 'Vue d\'ensemble', 'icon' => 'fas fa-chart-pie'],
                    'academic/index' => ['name' => 'Rapports Académiques', 'icon' => 'fas fa-graduation-cap'],
                    'financial/index' => ['name' => 'Rapports Financiers', 'icon' => 'fas fa-dollar-sign']
                ]
            ],
            'communication' => [
                'name' => 'Communication',
                'icon' => 'fas fa-comments',
                'description' => 'Messages et annonces',
                'pages' => [
                    'index' => ['name' => 'Vue d\'ensemble', 'icon' => 'fas fa-comment'],
                    'messages/index' => ['name' => 'Messages', 'icon' => 'fas fa-envelope'],
                    'annonces/add' => ['name' => 'Annonces', 'icon' => 'fas fa-bullhorn']
                ]
            ],
            'complementary' => [
                'name' => 'Services Complémentaires',
                'icon' => 'fas fa-plus-circle',
                'description' => 'Services additionnels',
                'pages' => [
                    'index' => ['name' => 'Vue d\'ensemble', 'icon' => 'fas fa-home'],
                    'transport/index' => ['name' => 'Transport', 'icon' => 'fas fa-bus'],
                    'internat/index' => ['name' => 'Internat', 'icon' => 'fas fa-bed']
                ]
            ]
        ]
    ],
    
    'teacher' => [
        'name' => 'Enseignant',
        'title' => 'Tableau de Bord Enseignant',
        'description' => 'Gestion de vos classes et évaluations',
        'icon' => 'fas fa-chalkboard-teacher',
        'color' => 'primary',
        'modules' => [
            'academic' => [
                'name' => 'Gestion Académique',
                'icon' => 'fas fa-graduation-cap',
                'description' => 'Classes, matières et emploi du temps',
                'pages' => [
                    'index' => ['name' => 'Vue d\'ensemble', 'icon' => 'fas fa-home'],
                    'classes/index' => ['name' => 'Mes Classes', 'icon' => 'fas fa-users'],
                    'subjects/index' => ['name' => 'Matières', 'icon' => 'fas fa-book'],
                    'schedule/index' => ['name' => 'Emploi du Temps', 'icon' => 'fas fa-calendar']
                ]
            ],
            'evaluations' => [
                'name' => 'Évaluations et Notes',
                'icon' => 'fas fa-chart-line',
                'description' => 'Évaluations, notes et bulletins',
                'pages' => [
                    'index' => ['name' => 'Vue d\'ensemble', 'icon' => 'fas fa-chart-pie'],
                    'evaluations/index' => ['name' => 'Évaluations', 'icon' => 'fas fa-clipboard-check'],
                    'notes/index' => ['name' => 'Saisie des Notes', 'icon' => 'fas fa-edit'],
                    'bulletins/index' => ['name' => 'Bulletins', 'icon' => 'fas fa-file-alt']
                ]
            ],
            'discipline' => [
                'name' => 'Discipline',
                'icon' => 'fas fa-gavel',
                'description' => 'Incidents et sanctions',
                'pages' => [
                    'index' => ['name' => 'Vue d\'ensemble', 'icon' => 'fas fa-chart-pie'],
                    'incidents/index' => ['name' => 'Incidents', 'icon' => 'fas fa-exclamation-triangle'],
                    'sanctions/index' => ['name' => 'Sanctions', 'icon' => 'fas fa-ban'],
                    'recompenses/index' => ['name' => 'Récompenses', 'icon' => 'fas fa-medal']
                ]
            ],
            'library' => [
                'name' => 'Bibliothèque',
                'icon' => 'fas fa-book',
                'description' => 'Gestion des prêts',
                'pages' => [
                    'index' => ['name' => 'Vue d\'ensemble', 'icon' => 'fas fa-chart-pie'],
                    'books/index' => ['name' => 'Catalogue', 'icon' => 'fas fa-book-open'],
                    'loans/index' => ['name' => 'Mes Prêts', 'icon' => 'fas fa-hand-holding']
                ]
            ],
            'reports' => [
                'name' => 'Rapports',
                'icon' => 'fas fa-chart-bar',
                'description' => 'Rapports de classes',
                'pages' => [
                    'index' => ['name' => 'Vue d\'ensemble', 'icon' => 'fas fa-chart-pie'],
                    'academic/index' => ['name' => 'Rapports Académiques', 'icon' => 'fas fa-graduation-cap']
                ]
            ]
        ]
    ],
    
    'student' => [
        'name' => 'Élève',
        'title' => 'Tableau de Bord Élève',
        'description' => 'Votre espace personnel d\'apprentissage',
        'icon' => 'fas fa-user-graduate',
        'color' => 'success',
        'modules' => [
            'students' => [
                'name' => 'Mon Profil',
                'icon' => 'fas fa-user',
                'description' => 'Informations personnelles',
                'pages' => [
                    'index' => ['name' => 'Mon Profil', 'icon' => 'fas fa-user-circle'],
                    'view' => ['name' => 'Mes Informations', 'icon' => 'fas fa-id-card']
                ]
            ],
            'academic' => [
                'name' => 'Vie Académique',
                'icon' => 'fas fa-graduation-cap',
                'description' => 'Classes, matières et emploi du temps',
                'pages' => [
                    'index' => ['name' => 'Vue d\'ensemble', 'icon' => 'fas fa-home'],
                    'classes/view' => ['name' => 'Ma Classe', 'icon' => 'fas fa-users'],
                    'schedule/index' => ['name' => 'Mon Emploi du Temps', 'icon' => 'fas fa-calendar']
                ]
            ],
            'evaluations' => [
                'name' => 'Évaluations et Notes',
                'icon' => 'fas fa-chart-line',
                'description' => 'Mes notes et bulletins',
                'pages' => [
                    'index' => ['name' => 'Vue d\'ensemble', 'icon' => 'fas fa-chart-pie'],
                    'notes/student' => ['name' => 'Mes Notes', 'icon' => 'fas fa-star'],
                    'bulletins/index' => ['name' => 'Mes Bulletins', 'icon' => 'fas fa-file-alt']
                ]
            ],
            'cartes_eleves' => [
                'name' => 'Ma Carte',
                'icon' => 'fas fa-id-card',
                'description' => 'Carte d\'élève et QR Code',
                'pages' => [
                    'index' => ['name' => 'Ma Carte', 'icon' => 'fas fa-id-card'],
                    'view' => ['name' => 'Détails', 'icon' => 'fas fa-info-circle']
                ]
            ],
            'library' => [
                'name' => 'Bibliothèque',
                'icon' => 'fas fa-book',
                'description' => 'Mes emprunts',
                'pages' => [
                    'index' => ['name' => 'Vue d\'ensemble', 'icon' => 'fas fa-chart-pie'],
                    'loans/index' => ['name' => 'Mes Emprunts', 'icon' => 'fas fa-hand-holding']
                ]
            ],
            'discipline' => [
                'name' => 'Discipline',
                'icon' => 'fas fa-gavel',
                'description' => 'Mon dossier disciplinaire',
                'pages' => [
                    'index' => ['name' => 'Vue d\'ensemble', 'icon' => 'fas fa-chart-pie'],
                    'incidents/view' => ['name' => 'Mes Incidents', 'icon' => 'fas fa-exclamation-triangle']
                ]
            ]
        ]
    ],
    
    'parent' => [
        'name' => 'Parent',
        'title' => 'Tableau de Bord Parent',
        'description' => 'Suivi de la scolarité de votre enfant',
        'icon' => 'fas fa-user-friends',
        'color' => 'info',
        'modules' => [
            'students' => [
                'name' => 'Mon Enfant',
                'icon' => 'fas fa-child',
                'description' => 'Informations sur votre enfant',
                'pages' => [
                    'index' => ['name' => 'Vue d\'ensemble', 'icon' => 'fas fa-home'],
                    'view' => ['name' => 'Profil', 'icon' => 'fas fa-user-circle']
                ]
            ],
            'evaluations' => [
                'name' => 'Évaluations',
                'icon' => 'fas fa-chart-line',
                'description' => 'Notes et bulletins de votre enfant',
                'pages' => [
                    'index' => ['name' => 'Vue d\'ensemble', 'icon' => 'fas fa-chart-pie'],
                    'notes/student' => ['name' => 'Notes', 'icon' => 'fas fa-star'],
                    'bulletins/index' => ['name' => 'Bulletins', 'icon' => 'fas fa-file-alt']
                ]
            ],
            'recouvrement' => [
                'name' => 'Paiements',
                'icon' => 'fas fa-hand-holding-usd',
                'description' => 'Frais scolaires et paiements',
                'pages' => [
                    'index' => ['name' => 'Vue d\'ensemble', 'icon' => 'fas fa-chart-pie'],
                    'paiements/index' => ['name' => 'Mes Paiements', 'icon' => 'fas fa-credit-card']
                ]
            ],
            'communication' => [
                'name' => 'Communication',
                'icon' => 'fas fa-comments',
                'description' => 'Messages de l\'école',
                'pages' => [
                    'index' => ['name' => 'Vue d\'ensemble', 'icon' => 'fas fa-chart-pie'],
                    'messages/index' => ['name' => 'Messages', 'icon' => 'fas fa-envelope'],
                    'annonces/add' => ['name' => 'Annonces', 'icon' => 'fas fa-bullhorn']
                ]
            ],
            'reports' => [
                'name' => 'Rapports',
                'icon' => 'fas fa-chart-bar',
                'description' => 'Suivi académique',
                'pages' => [
                    'index' => ['name' => 'Vue d\'ensemble', 'icon' => 'fas fa-chart-pie'],
                    'academic/index' => ['name' => 'Rapport Académique', 'icon' => 'fas fa-graduation-cap']
                ]
            ]
        ]
    ],
    
    'staff' => [
        'name' => 'Personnel Administratif',
        'title' => 'Tableau de Bord Personnel',
        'description' => 'Gestion administrative de l\'établissement',
        'icon' => 'fas fa-briefcase',
        'color' => 'warning',
        'modules' => [
            'finance' => [
                'name' => 'Gestion Financière',
                'icon' => 'fas fa-money-bill-wave',
                'description' => 'Frais, paiements et comptabilité',
                'pages' => [
                    'index' => ['name' => 'Vue d\'ensemble', 'icon' => 'fas fa-chart-pie'],
                    'fees/index' => ['name' => 'Frais Scolaires', 'icon' => 'fas fa-receipt'],
                    'payments/index' => ['name' => 'Paiements', 'icon' => 'fas fa-credit-card'],
                    'expenses/index' => ['name' => 'Dépenses', 'icon' => 'fas fa-money-bill']
                ]
            ],
            'recouvrement' => [
                'name' => 'Recouvrement',
                'icon' => 'fas fa-hand-holding-usd',
                'description' => 'Campagnes et recouvrement des frais',
                'pages' => [
                    'index' => ['name' => 'Vue d\'ensemble', 'icon' => 'fas fa-chart-pie'],
                    'campaigns/index' => ['name' => 'Campagnes', 'icon' => 'fas fa-bullhorn'],
                    'cartes/index' => ['name' => 'Cartes Élèves', 'icon' => 'fas fa-id-card'],
                    'rapports/index' => ['name' => 'Rapports', 'icon' => 'fas fa-chart-bar']
                ]
            ],
            'admissions' => [
                'name' => 'Admissions',
                'icon' => 'fas fa-user-plus',
                'description' => 'Gestion des admissions',
                'pages' => [
                    'index' => ['name' => 'Vue d\'ensemble', 'icon' => 'fas fa-chart-pie'],
                    'applications/index' => ['name' => 'Candidatures', 'icon' => 'fas fa-file-alt'],
                    'students/index' => ['name' => 'Nouveaux Élèves', 'icon' => 'fas fa-user-plus']
                ]
            ],
            'complementary' => [
                'name' => 'Services Complémentaires',
                'icon' => 'fas fa-plus-circle',
                'description' => 'Services additionnels',
                'pages' => [
                    'index' => ['name' => 'Vue d\'ensemble', 'icon' => 'fas fa-home'],
                    'transport/index' => ['name' => 'Transport', 'icon' => 'fas fa-bus'],
                    'internat/index' => ['name' => 'Internat', 'icon' => 'fas fa-bed'],
                    'inventory/index' => ['name' => 'Inventaire', 'icon' => 'fas fa-boxes']
                ]
            ],
            'communication' => [
                'name' => 'Communication',
                'icon' => 'fas fa-comments',
                'description' => 'Messages et annonces',
                'pages' => [
                    'index' => ['name' => 'Vue d\'ensemble', 'icon' => 'fas fa-chart-pie'],
                    'messages/index' => ['name' => 'Messages', 'icon' => 'fas fa-envelope'],
                    'sms/index' => ['name' => 'SMS', 'icon' => 'fas fa-sms']
                ]
            ]
        ]
    ]
]);

/**
 * Obtenir la configuration d'un dashboard
 */
function getDashboardConfig($nature) {
    return DASHBOARD_CONFIG[$nature] ?? DASHBOARD_CONFIG['staff'];
}

/**
 * Obtenir les modules d'un dashboard
 */
function getDashboardModules($nature) {
    $config = getDashboardConfig($nature);
    return $config['modules'] ?? [];
}

/**
 * Obtenir les informations de base d'un dashboard
 */
function getDashboardInfo($nature) {
    $config = getDashboardConfig($nature);
    return [
        'name' => $config['name'],
        'title' => $config['title'],
        'description' => $config['description'],
        'icon' => $config['icon'],
        'color' => $config['color']
    ];
}

/**
 * Vérifier si une nature d'utilisateur est valide
 */
function isValidUserNature($nature) {
    return array_key_exists($nature, DASHBOARD_CONFIG);
}

/**
 * Obtenir toutes les natures d'utilisateurs disponibles
 */
function getAvailableUserNatures() {
    return array_keys(DASHBOARD_CONFIG);
}
?>
