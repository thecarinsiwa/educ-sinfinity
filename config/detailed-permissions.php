<?php
/**
 * Configuration détaillée des permissions par module et page
 * Application de gestion scolaire - République Démocratique du Congo
 */

// Permissions détaillées par module et page
define('DETAILED_PERMISSIONS', [
    'students' => [
        'name' => 'Gestion des Élèves',
        'pages' => [
            'index' => ['name' => 'Tableau de bord', 'permissions' => ['read']],
            'add' => ['name' => 'Ajouter un élève', 'permissions' => ['create']],
            'list' => ['name' => 'Liste des élèves', 'permissions' => ['read']],
            'view' => ['name' => 'Voir un élève', 'permissions' => ['read']],
            'edit' => ['name' => 'Modifier un élève', 'permissions' => ['edit']],
            'delete' => ['name' => 'Supprimer un élève', 'permissions' => ['delete']],
            'search' => ['name' => 'Rechercher des élèves', 'permissions' => ['read']],
            'change-status' => ['name' => 'Changer le statut', 'permissions' => ['edit']],
            'confirm-inscriptions' => ['name' => 'Confirmer inscriptions', 'permissions' => ['edit']],
            'enrollment' => ['name' => 'Inscription', 'permissions' => ['create']],
            're-enrollment' => ['name' => 'Réinscription', 'permissions' => ['create']],
            'enrollment-history' => ['name' => 'Historique inscriptions', 'permissions' => ['read']],
            'reports' => ['name' => 'Rapports élèves', 'permissions' => ['read']],
            'attendance' => [
                'name' => 'Présences',
                'pages' => [
                    'index' => ['name' => 'Liste présences', 'permissions' => ['read']],
                    'add' => ['name' => 'Ajouter présence', 'permissions' => ['create']],
                    'edit' => ['name' => 'Modifier présence', 'permissions' => ['edit']],
                    'view' => ['name' => 'Voir présence', 'permissions' => ['read']],
                    'export' => ['name' => 'Exporter présences', 'permissions' => ['read']],
                    'statistics' => ['name' => 'Statistiques', 'permissions' => ['read']]
                ]
            ],
            'transfers' => [
                'name' => 'Transferts',
                'pages' => [
                    'index' => ['name' => 'Liste transferts', 'permissions' => ['read']],
                    'new-transfer' => ['name' => 'Nouveau transfert', 'permissions' => ['create']],
                    'new-exit' => ['name' => 'Nouvelle sortie', 'permissions' => ['create']],
                    'view' => ['name' => 'Voir transfert', 'permissions' => ['read']],
                    'process' => ['name' => 'Traiter transfert', 'permissions' => ['edit']],
                    'certificate' => ['name' => 'Certificat transfert', 'permissions' => ['read']]
                ]
            ],
            'student-tracking' => [
                'name' => 'Suivi des élèves',
                'pages' => [
                    'index' => ['name' => 'Tableau de bord suivi', 'permissions' => ['read']],
                    'evaluations' => [
                        'name' => 'Évaluations',
                        'pages' => [
                            'index' => ['name' => 'Liste évaluations', 'permissions' => ['read']],
                            'add' => ['name' => 'Nouvelle évaluation', 'permissions' => ['create']]
                        ]
                    ],
                    'decisions' => [
                        'name' => 'Décisions',
                        'pages' => [
                            'index' => ['name' => 'Liste décisions', 'permissions' => ['read']],
                            'take-decision' => ['name' => 'Prendre décision', 'permissions' => ['create']]
                        ]
                    ],
                    'follow-up' => [
                        'name' => 'Suivi',
                        'pages' => [
                            'index' => ['name' => 'Suivi personnalisé', 'permissions' => ['read']]
                        ]
                    ]
                ]
            ],
            'records' => [
                'name' => 'Dossiers',
                'pages' => [
                    'index' => ['name' => 'Liste dossiers', 'permissions' => ['read']],
                    'view' => ['name' => 'Voir dossier', 'permissions' => ['read']],
                    'edit' => ['name' => 'Modifier dossier', 'permissions' => ['edit']],
                    'documents' => ['name' => 'Documents', 'permissions' => ['read']]
                ]
            ]
        ]
    ],
    'academic' => [
        'name' => 'Gestion Académique',
        'pages' => [
            'index' => ['name' => 'Tableau de bord', 'permissions' => ['read']],
            'schedule' => ['name' => 'Emploi du temps enseignant', 'permissions' => ['read']],
            'classes' => [
                'name' => 'Classes',
                'pages' => [
                    'index' => ['name' => 'Liste classes', 'permissions' => ['read']],
                    'add' => ['name' => 'Ajouter classe', 'permissions' => ['create']],
                    'edit' => ['name' => 'Modifier classe', 'permissions' => ['edit']],
                    'view' => ['name' => 'Voir classe', 'permissions' => ['read']],
                    'delete' => ['name' => 'Supprimer classe', 'permissions' => ['delete']],
                    'export' => ['name' => 'Exporter classes', 'permissions' => ['read']]
                ]
            ],
            'subjects' => [
                'name' => 'Matières',
                'pages' => [
                    'index' => ['name' => 'Liste matières', 'permissions' => ['read']],
                    'add' => ['name' => 'Ajouter matière', 'permissions' => ['create']],
                    'edit' => ['name' => 'Modifier matière', 'permissions' => ['edit']],
                    'view' => ['name' => 'Voir matière', 'permissions' => ['read']],
                    'delete' => ['name' => 'Supprimer matière', 'permissions' => ['delete']],
                    'export' => ['name' => 'Exporter matières', 'permissions' => ['read']]
                ]
            ],
            'schedule' => [
                'name' => 'Emplois du temps',
                'pages' => [
                    'index' => ['name' => 'Liste emplois', 'permissions' => ['read']],
                    'add' => ['name' => 'Ajouter emploi', 'permissions' => ['create']],
                    'add-schedule' => ['name' => 'Ajouter horaire', 'permissions' => ['create']],
                    'edit-schedule' => ['name' => 'Modifier emploi', 'permissions' => ['edit']],
                    'class' => ['name' => 'Emploi par classe', 'permissions' => ['read']],
                    'generate' => ['name' => 'Générer emploi', 'permissions' => ['create']],
                    'conflicts' => ['name' => 'Conflits', 'permissions' => ['read']],
                    'detect-conflicts' => ['name' => 'Détecter conflits', 'permissions' => ['read']],
                    'resolve-conflict' => ['name' => 'Résoudre conflit', 'permissions' => ['edit']],
                    'export' => ['name' => 'Exporter emplois', 'permissions' => ['read']]
                ]
            ],
            'years' => [
                'name' => 'Années scolaires',
                'pages' => [
                    'index' => ['name' => 'Liste années', 'permissions' => ['read']],
                    'add' => ['name' => 'Ajouter année', 'permissions' => ['create']],
                    'edit' => ['name' => 'Modifier année', 'permissions' => ['edit']]
                ]
            ],
            'notes' => [
                'name' => 'Notes',
                'pages' => [
                    'add' => ['name' => 'Ajouter note', 'permissions' => ['create']],
                    'student' => ['name' => 'Notes élève', 'permissions' => ['read']]
                ]
            ],
            'evaluations' => [
                'name' => 'Évaluations',
                'pages' => [
                    'index' => ['name' => 'Liste évaluations', 'permissions' => ['read']],
                    'view' => ['name' => 'Voir évaluation', 'permissions' => ['read']]
                ]
            ]
        ]
    ],
    'finance' => [
        'name' => 'Gestion Financière',
        'pages' => [
            'index' => ['name' => 'Tableau de bord', 'permissions' => ['read']],
            'payments' => [
                'name' => 'Paiements',
                'pages' => [
                    'index' => ['name' => 'Liste paiements', 'permissions' => ['read']],
                    'add' => ['name' => 'Nouveau paiement', 'permissions' => ['create']],
                    'edit' => ['name' => 'Modifier paiement', 'permissions' => ['edit']],
                    'view' => ['name' => 'Voir paiement', 'permissions' => ['read']],
                    'cancel' => ['name' => 'Annuler paiement', 'permissions' => ['delete']],
                    'receipt' => ['name' => 'Reçu paiement', 'permissions' => ['read']],
                    'export' => ['name' => 'Exporter paiements', 'permissions' => ['read']]
                ]
            ],
            'fees' => [
                'name' => 'Frais scolaires',
                'pages' => [
                    'index' => ['name' => 'Liste frais', 'permissions' => ['read']],
                    'add' => ['name' => 'Ajouter frais', 'permissions' => ['create']],
                    'edit' => ['name' => 'Modifier frais', 'permissions' => ['edit']],
                    'view' => ['name' => 'Voir frais', 'permissions' => ['read']],
                    'delete' => ['name' => 'Supprimer frais', 'permissions' => ['delete']],
                    'types' => [
                        'name' => 'Types de frais',
                        'pages' => [
                            'index' => ['name' => 'Liste types', 'permissions' => ['read']],
                            'add' => ['name' => 'Ajouter type', 'permissions' => ['create']],
                            'edit' => ['name' => 'Modifier type', 'permissions' => ['edit']],
                            'view' => ['name' => 'Voir type', 'permissions' => ['read']]
                        ]
                    ]
                ]
            ],
            'expenses' => [
                'name' => 'Dépenses',
                'pages' => [
                    'index' => ['name' => 'Liste dépenses', 'permissions' => ['read']],
                    'add' => ['name' => 'Nouvelle dépense', 'permissions' => ['create']],
                    'edit' => ['name' => 'Modifier dépense', 'permissions' => ['edit']],
                    'view' => ['name' => 'Voir dépense', 'permissions' => ['read']],
                    'pay' => ['name' => 'Payer dépense', 'permissions' => ['edit']]
                ]
            ],
            'devises' => [
                'name' => 'Devises',
                'pages' => [
                    'index' => ['name' => 'Liste devises', 'permissions' => ['read']]
                ]
            ],
            'reports' => [
                'name' => 'Rapports financiers',
                'pages' => [
                    'index' => ['name' => 'Tableau de bord rapports', 'permissions' => ['read']],
                    'monthly' => ['name' => 'Rapport mensuel', 'permissions' => ['read']],
                    'debtors' => ['name' => 'Rapport débiteurs', 'permissions' => ['read']]
                ]
            ]
        ]
    ],
    'evaluations' => [
        'name' => 'Évaluations et Notes',
        'pages' => [
            'index' => ['name' => 'Tableau de bord', 'permissions' => ['read']],
            'evaluations' => [
                'name' => 'Évaluations',
                'pages' => [
                    'index' => ['name' => 'Liste évaluations', 'permissions' => ['read']],
                    'add' => ['name' => 'Nouvelle évaluation', 'permissions' => ['create']],
                    'edit' => ['name' => 'Modifier évaluation', 'permissions' => ['edit']],
                    'view' => ['name' => 'Voir évaluation', 'permissions' => ['read']],
                    'delete' => ['name' => 'Supprimer évaluation', 'permissions' => ['delete']]
                ]
            ],
            'notes' => [
                'name' => 'Notes',
                'pages' => [
                    'index' => ['name' => 'Liste notes', 'permissions' => ['read']],
                    'entry' => ['name' => 'Saisie notes', 'permissions' => ['create']],
                    'batch-entry' => ['name' => 'Saisie groupée', 'permissions' => ['create']],
                    'student' => ['name' => 'Notes élève', 'permissions' => ['read']],
                    'reports' => ['name' => 'Rapports notes', 'permissions' => ['read']],
                    'statistics' => ['name' => 'Statistiques', 'permissions' => ['read']]
                ]
            ],
            'bulletins' => [
                'name' => 'Bulletins',
                'pages' => [
                    'index' => ['name' => 'Liste bulletins', 'permissions' => ['read']],
                    'generate' => ['name' => 'Générer bulletin', 'permissions' => ['create']],
                    'individual' => ['name' => 'Bulletin individuel', 'permissions' => ['read']],
                    'batch_bulletins' => ['name' => 'Bulletins groupés', 'permissions' => ['create']],
                    'preview' => ['name' => 'Aperçu bulletin', 'permissions' => ['read']],
                    'download' => ['name' => 'Télécharger bulletin', 'permissions' => ['read']]
                ]
            ],
            'statistics' => [
                'name' => 'Statistiques',
                'pages' => [
                    'index' => ['name' => 'Tableau de bord stats', 'permissions' => ['read']],
                    'class-ranking' => ['name' => 'Classement classes', 'permissions' => ['read']],
                    'student-performance' => ['name' => 'Performance élèves', 'permissions' => ['read']],
                    'evaluation-reports' => ['name' => 'Rapports évaluations', 'permissions' => ['read']],
                    'subject-analysis' => ['name' => 'Analyse matières', 'permissions' => ['read']]
                ]
            ],
            'class' => ['name' => 'Évaluations par classe', 'permissions' => ['read']],
            'teacher' => ['name' => 'Évaluations par enseignant', 'permissions' => ['read']]
        ]
    ],
    'personnel' => [
        'name' => 'Gestion du Personnel',
        'pages' => [
            'index' => ['name' => 'Tableau de bord', 'permissions' => ['read']],
            'add' => ['name' => 'Ajouter personnel', 'permissions' => ['create']],
            'edit' => ['name' => 'Modifier personnel', 'permissions' => ['edit']],
            'view' => ['name' => 'Voir personnel', 'permissions' => ['read']],
            'delete' => ['name' => 'Supprimer personnel', 'permissions' => ['delete']],
            'create-account' => ['name' => 'Créer compte', 'permissions' => ['create']],
            'export' => ['name' => 'Exporter personnel', 'permissions' => ['read']],
            'import' => ['name' => 'Importer personnel', 'permissions' => ['create']],
            'payroll' => ['name' => 'Paie', 'permissions' => ['read']],
            'payslip' => ['name' => 'Bulletin de paie', 'permissions' => ['read']]
        ]
    ],
    'communication' => [
        'name' => 'Communication',
        'pages' => [
            'index' => ['name' => 'Tableau de bord', 'permissions' => ['read']],
            'messages' => [
                'name' => 'Messages',
                'pages' => [
                    'index' => ['name' => 'Liste messages', 'permissions' => ['read']],
                    'compose' => ['name' => 'Composer message', 'permissions' => ['create']],
                    'view' => ['name' => 'Voir message', 'permissions' => ['read']]
                ]
            ],
            'annonces' => [
                'name' => 'Annonces',
                'pages' => [
                    'add' => ['name' => 'Nouvelle annonce', 'permissions' => ['create']]
                ]
            ],
            'sms' => [
                'name' => 'SMS',
                'pages' => [
                    'index' => ['name' => 'Liste SMS', 'permissions' => ['read']],
                    'send' => ['name' => 'Envoyer SMS', 'permissions' => ['create']]
                ]
            ],
            'templates' => [
                'name' => 'Modèles',
                'pages' => [
                    'index' => ['name' => 'Liste modèles', 'permissions' => ['read']]
                ]
            ]
        ]
    ],
    'reports' => [
        'name' => 'Rapports',
        'pages' => [
            'index' => ['name' => 'Tableau de bord', 'permissions' => ['read']],
            'academic' => [
                'name' => 'Rapports académiques',
                'pages' => [
                    'index' => ['name' => 'Tableau de bord', 'permissions' => ['read']],
                    'trends' => [
                        'name' => 'Tendances',
                        'pages' => [
                            'evolution' => ['name' => 'Évolution', 'permissions' => ['read']]
                        ]
                    ],
                    'comparison' => [
                        'name' => 'Comparaisons',
                        'pages' => [
                            'classes' => ['name' => 'Classes', 'permissions' => ['read']]
                        ]
                    ],
                    'analysis' => [
                        'name' => 'Analyses',
                        'pages' => [
                            'detailed' => ['name' => 'Détaillée', 'permissions' => ['read']]
                        ]
                    ],
                    'bulletins' => [
                        'name' => 'Bulletins',
                        'pages' => [
                            'generate-all' => ['name' => 'Générer tous', 'permissions' => ['create']]
                        ]
                    ],
                    'export' => ['name' => 'Exporter', 'permissions' => ['read']]
                ]
            ],
            'financial' => [
                'name' => 'Rapports financiers',
                'pages' => [
                    'index' => ['name' => 'Tableau de bord', 'permissions' => ['read']]
                ]
            ],
            'administrative' => [
                'name' => 'Rapports administratifs',
                'pages' => [
                    'index' => ['name' => 'Tableau de bord', 'permissions' => ['read']]
                ]
            ],
            'custom' => [
                'name' => 'Rapports personnalisés',
                'pages' => [
                    'index' => ['name' => 'Tableau de bord', 'permissions' => ['read']]
                ]
            ],
            'exports' => [
                'name' => 'Exports',
                'pages' => [
                    'index' => ['name' => 'Tableau de bord', 'permissions' => ['read']]
                ]
            ]
        ]
    ],
    'users' => [
        'name' => 'Gestion des Utilisateurs',
        'pages' => [
            'index' => ['name' => 'Tableau de bord', 'permissions' => ['read']],
            'add' => ['name' => 'Ajouter utilisateur', 'permissions' => ['create']],
            'edit' => ['name' => 'Modifier utilisateur', 'permissions' => ['edit']],
            'view' => ['name' => 'Voir utilisateur', 'permissions' => ['read']],
            'delete' => ['name' => 'Supprimer utilisateur', 'permissions' => ['delete']],
            'list' => ['name' => 'Liste utilisateurs', 'permissions' => ['read']],
            'roles' => [
                'name' => 'Rôles',
                'pages' => [
                    'index' => ['name' => 'Liste rôles', 'permissions' => ['read']],
                    'manage' => ['name' => 'Gérer rôles', 'permissions' => ['edit']],
                    'add' => ['name' => 'Ajouter rôle', 'permissions' => ['create']]
                ]
            ],
            'sessions' => [
                'name' => 'Sessions',
                'pages' => [
                    'index' => ['name' => 'Sessions actives', 'permissions' => ['read']]
                ]
            ],
            'logs' => [
                'name' => 'Journaux',
                'pages' => [
                    'index' => ['name' => 'Historique actions', 'permissions' => ['read']]
                ]
            ]
        ]
    ],
    'library' => [
        'name' => 'Bibliothèque',
        'pages' => [
            'index' => ['name' => 'Tableau de bord', 'permissions' => ['read']],
            'books' => [
                'name' => 'Livres',
                'pages' => [
                    'index' => ['name' => 'Liste livres', 'permissions' => ['read']],
                    'add' => ['name' => 'Ajouter livre', 'permissions' => ['create']],
                    'edit' => ['name' => 'Modifier livre', 'permissions' => ['edit']],
                    'view' => ['name' => 'Voir livre', 'permissions' => ['read']],
                    'categories' => ['name' => 'Catégories', 'permissions' => ['read']],
                    'import' => ['name' => 'Importer livres', 'permissions' => ['create']],
                    'export' => ['name' => 'Exporter livres', 'permissions' => ['read']]
                ]
            ],
            'loans' => [
                'name' => 'Emprunts',
                'pages' => [
                    'index' => ['name' => 'Liste emprunts', 'permissions' => ['read']],
                    'add' => ['name' => 'Nouvel emprunt', 'permissions' => ['create']],
                    'returns' => ['name' => 'Retours', 'permissions' => ['edit']]
                ]
            ],
            'reservations' => [
                'name' => 'Réservations',
                'pages' => [
                    'add' => ['name' => 'Nouvelle réservation', 'permissions' => ['create']]
                ]
            ],
            'reports' => [
                'name' => 'Rapports',
                'pages' => [
                    'inventory' => ['name' => 'Inventaire', 'permissions' => ['read']]
                ]
            ],
            'settings' => [
                'name' => 'Paramètres',
                'pages' => [
                    'index' => ['name' => 'Configuration', 'permissions' => ['edit']]
                ]
            ]
        ]
    ],
    'discipline' => [
        'name' => 'Discipline',
        'pages' => [
            'index' => ['name' => 'Tableau de bord', 'permissions' => ['read']],
            'incidents' => [
                'name' => 'Incidents',
                'pages' => [
                    'index' => ['name' => 'Liste incidents', 'permissions' => ['read']],
                    'add' => ['name' => 'Nouvel incident', 'permissions' => ['create']],
                    'view' => ['name' => 'Voir incident', 'permissions' => ['read']],
                    'search_eleves' => ['name' => 'Rechercher élèves', 'permissions' => ['read']]
                ]
            ],
            'sanctions' => [
                'name' => 'Sanctions',
                'pages' => [
                    'index' => ['name' => 'Liste sanctions', 'permissions' => ['read']],
                    'add' => ['name' => 'Nouvelle sanction', 'permissions' => ['create']],
                    'search_eleves' => ['name' => 'Rechercher élèves', 'permissions' => ['read']]
                ]
            ],
            'recompenses' => [
                'name' => 'Récompenses',
                'pages' => [
                    'index' => ['name' => 'Liste récompenses', 'permissions' => ['read']],
                    'add' => ['name' => 'Nouvelle récompense', 'permissions' => ['create']]
                ]
            ],
            'reports' => [
                'name' => 'Rapports',
                'pages' => [
                    'index' => ['name' => 'Tableau de bord', 'permissions' => ['read']]
                ]
            ]
        ]
    ],
    'cartes_eleves' => [
        'name' => 'Cartes d\'Élèves',
        'pages' => [
            'index' => ['name' => 'Tableau de bord', 'permissions' => ['read']],
            'generate_card' => ['name' => 'Générer carte', 'permissions' => ['create']],
            'print' => ['name' => 'Imprimer carte', 'permissions' => ['read']],
            'print-all' => ['name' => 'Imprimer toutes', 'permissions' => ['read']],
            'view' => ['name' => 'Voir carte', 'permissions' => ['read']],
            'download' => ['name' => 'Télécharger', 'permissions' => ['read']],
            'qr-scanner' => ['name' => 'Scanner QR', 'permissions' => ['read']],
            'qr-generator' => ['name' => 'Générateur QR', 'permissions' => ['create']],
            'settings' => ['name' => 'Paramètres', 'permissions' => ['edit']]
        ]
    ],
    'recouvrement' => [
        'name' => 'Recouvrement',
        'pages' => [
            'index' => ['name' => 'Tableau de bord', 'permissions' => ['read']],
            'campaigns' => [
                'name' => 'Campagnes',
                'pages' => [
                    'index' => ['name' => 'Liste campagnes', 'permissions' => ['read']],
                    'add' => ['name' => 'Nouvelle campagne', 'permissions' => ['create']],
                    'edit' => ['name' => 'Modifier campagne', 'permissions' => ['edit']],
                    'details' => ['name' => 'Détails campagne', 'permissions' => ['read']]
                ]
            ],
            'cartes' => [
                'name' => 'Cartes',
                'pages' => [
                    'index' => ['name' => 'Liste cartes', 'permissions' => ['read']],
                    'generate' => ['name' => 'Générer cartes', 'permissions' => ['create']],
                    'view' => ['name' => 'Voir carte', 'permissions' => ['read']],
                    'print' => ['name' => 'Imprimer carte', 'permissions' => ['read']]
                ]
            ],
            'paiements' => [
                'name' => 'Paiements',
                'pages' => [
                    'index' => ['name' => 'Liste paiements', 'permissions' => ['read']]
                ]
            ],
            'rapports' => [
                'name' => 'Rapports',
                'pages' => [
                    'index' => ['name' => 'Tableau de bord', 'permissions' => ['read']],
                    'export' => ['name' => 'Exporter', 'permissions' => ['read']],
                    'paiements' => ['name' => 'Paiements', 'permissions' => ['read']],
                    'presences' => ['name' => 'Présences', 'permissions' => ['read']],
                    'solvabilite' => ['name' => 'Solvabilité', 'permissions' => ['read']],
                    'comparatif' => ['name' => 'Comparatif', 'permissions' => ['read']]
                ]
            ],
            'scan-qr' => ['name' => 'Scanner QR', 'permissions' => ['read']]
        ]
    ],
    'admissions' => [
        'name' => 'Gestion des Admissions',
        'pages' => [
            'index' => ['name' => 'Tableau de bord', 'permissions' => ['read']],
            'applications' => [
                'name' => 'Candidatures',
                'pages' => [
                    'list' => ['name' => 'Liste candidatures', 'permissions' => ['read']],
                    'view' => ['name' => 'Voir candidature', 'permissions' => ['read']],
                    'evaluate' => ['name' => 'Évaluer candidature', 'permissions' => ['edit']]
                ]
            ],
            'students' => [
                'name' => 'Élèves',
                'pages' => [
                    'view' => ['name' => 'Voir élève', 'permissions' => ['read']]
                ]
            ]
        ]
    ]
]);

// Actions disponibles (seulement si pas déjà définies)
if (!defined('AVAILABLE_ACTIONS')) {
    define('AVAILABLE_ACTIONS', [
        'read' => 'Lire',
        'create' => 'Créer',
        'edit' => 'Modifier',
        'delete' => 'Supprimer'
    ]);
}

/**
 * Obtenir toutes les permissions détaillées
 */
function getDetailedPermissions() {
    return DETAILED_PERMISSIONS;
}

/**
 * Obtenir les permissions d'un module spécifique
 */
function getModulePermissions($module) {
    return DETAILED_PERMISSIONS[$module] ?? null;
}

/**
 * Obtenir les actions disponibles pour les permissions détaillées
 */
function getDetailedActions() {
    return AVAILABLE_ACTIONS;
}

/**
 * Générer une liste plate de toutes les permissions
 */
function getFlatPermissions() {
    $permissions = [];
    
    foreach (DETAILED_PERMISSIONS as $module_key => $module) {
        $permissions[$module_key] = [];
        
        foreach ($module['pages'] as $page_key => $page) {
            if (isset($page['permissions'])) {
                // Page directe
                foreach ($page['permissions'] as $action) {
                    $permissions[$module_key][] = $action;
                }
            } elseif (isset($page['pages'])) {
                // Sous-module
                foreach ($page['pages'] as $subpage_key => $subpage) {
                    if (isset($subpage['permissions'])) {
                        foreach ($subpage['permissions'] as $action) {
                            $permissions[$module_key][] = $action;
                        }
                    } elseif (isset($subpage['pages'])) {
                        // Sous-sous-module
                        foreach ($subpage['pages'] as $subsubpage_key => $subsubpage) {
                            if (isset($subsubpage['permissions'])) {
                                foreach ($subsubpage['permissions'] as $action) {
                                    $permissions[$module_key][] = $action;
                                }
                            }
                        }
                    }
                }
            }
        }
        
        // Supprimer les doublons
        $permissions[$module_key] = array_unique($permissions[$module_key]);
    }
    
    return $permissions;
}
?>
