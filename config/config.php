<?php
/**
 * Configuration générale de l'application
 * Application de gestion scolaire - République Démocratique du Congo
 */

// Démarrer la session
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Configuration générale
define('APP_NAME', 'Educ-Sinfinity');
define('APP_VERSION', '1.0.0');
define('APP_URL', 'http://localhost/educ-sinfinity');
define('APP_DEBUG', true); // Mettre à false en production
define('TIMEZONE', 'Africa/Kinshasa');

// Définir le fuseau horaire
date_default_timezone_set(TIMEZONE);

// Configuration des chemins
define('ROOT_PATH', dirname(__DIR__));
define('INCLUDES_PATH', ROOT_PATH . '/includes');
define('MODULES_PATH', ROOT_PATH . '/modules');
define('ASSETS_PATH', ROOT_PATH . '/assets');
define('UPLOADS_PATH', ROOT_PATH . '/uploads');

// Configuration de sécurité
define('HASH_ALGO', PASSWORD_DEFAULT);
define('SESSION_LIFETIME', 3600); // 1 heure

// Configuration de l'upload
define('MAX_FILE_SIZE', 5 * 1024 * 1024); // 5MB
define('ALLOWED_IMAGE_TYPES', ['jpg', 'jpeg', 'png', 'gif']);
define('ALLOWED_DOC_TYPES', ['pdf', 'doc', 'docx', 'xls', 'xlsx']);

// Messages de l'application
define('MESSAGES', [
    'success' => [
        'login' => 'Connexion réussie !',
        'logout' => 'Déconnexion réussie !',
        'save' => 'Données enregistrées avec succès !',
        'update' => 'Données mises à jour avec succès !',
        'delete' => 'Suppression effectuée avec succès !',
    ],
    'error' => [
        'login' => 'Nom d\'utilisateur ou mot de passe incorrect !',
        'access_denied' => 'Accès refusé !',
        'not_found' => 'Élément non trouvé !',
        'database' => 'Erreur de base de données !',
        'upload' => 'Erreur lors du téléchargement du fichier !',
    ],
    'warning' => [
        'required_fields' => 'Veuillez remplir tous les champs obligatoires !',
        'invalid_data' => 'Données invalides !',
    ]
]);

// Configuration des rôles et permissions
define('ROLES', [
    'admin' => [
        'name' => 'Administrateur',
        'permissions' => ['all']
    ],
    'directeur' => [
        'name' => 'Directeur',
        'permissions' => ['students', 'personnel', 'academic', 'evaluations', 'finance', 'recouvrement', 'reports']
    ],
    'enseignant' => [
        'name' => 'Enseignant',
        'permissions' => ['students_view', 'evaluations', 'academic_view']
    ],
    'secretaire' => [
        'name' => 'Secrétaire',
        'permissions' => ['students', 'academic', 'communication']
    ],
    'comptable' => [
        'name' => 'Comptable',
        'permissions' => ['finance', 'recouvrement', 'reports_finance']
    ]
]);

// Configuration des modules avec sous-menus
define('MODULES', [
    'students' => [
        'name' => 'Gestion des Élèves',
        'icon' => 'fas fa-user-graduate',
        'description' => 'Inscriptions, dossiers, transferts',
        'submenu' => [
            'list' => [
                'name' => 'Liste des Élèves',
                'icon' => 'fas fa-list',
                'url' => 'modules/students/list.php'
            ],
            'add' => [
                'name' => 'Ajouter un Élève',
                'icon' => 'fas fa-plus',
                'url' => 'modules/students/add.php'
            ],
            'admissions' => [
                'name' => 'Admissions',
                'icon' => 'fas fa-user-plus',
                'url' => 'modules/students/admissions/'
            ],
            'attendance' => [
                'name' => 'Présences',
                'icon' => 'fas fa-calendar-check',
                'url' => 'modules/students/attendance/'
            ],
            'transfers' => [
                'name' => 'Transferts',
                'icon' => 'fas fa-exchange-alt',
                'url' => 'modules/students/transfers/'
            ]
        ]
    ],
    'personnel' => [
        'name' => 'Gestion du Personnel',
        'icon' => 'fas fa-users',
        'description' => 'Enseignants, administratifs, paie',
        'submenu' => [
            'list' => [
                'name' => 'Liste du Personnel',
                'icon' => 'fas fa-list',
                'url' => 'modules/personnel/'
            ],
            'add' => [
                'name' => 'Ajouter Personnel',
                'icon' => 'fas fa-plus',
                'url' => 'modules/personnel/add.php'
            ],
            'create-account' => [
                'name' => 'Créer Compte',
                'icon' => 'fas fa-user-plus',
                'url' => 'modules/personnel/create-account.php'
            ]
        ]
    ],
    'academic' => [
        'name' => 'Gestion Académique',
        'icon' => 'fas fa-book',
        'description' => 'Classes, matières, emplois du temps',
        'submenu' => [
            'classes' => [
                'name' => 'Classes',
                'icon' => 'fas fa-school',
                'url' => 'modules/academic/classes/'
            ],
            'subjects' => [
                'name' => 'Matières',
                'icon' => 'fas fa-book-open',
                'url' => 'modules/academic/subjects/'
            ],
            'schedule' => [
                'name' => 'Emplois du Temps',
                'icon' => 'fas fa-calendar-alt',
                'url' => 'modules/academic/schedule/'
            ],
            'years' => [
                'name' => 'Années Scolaires',
                'icon' => 'fas fa-calendar',
                'url' => 'modules/academic/years/'
            ]
        ]
    ],
    'evaluations' => [
        'name' => 'Évaluations et Notes',
        'icon' => 'fas fa-chart-line',
        'description' => 'Bulletins, examens, moyennes',
        'submenu' => [
            'evaluations' => [
                'name' => 'Évaluations',
                'icon' => 'fas fa-edit',
                'url' => 'modules/evaluations/evaluations/'
            ],
            'notes' => [
                'name' => 'Saisie des Notes',
                'icon' => 'fas fa-pencil-alt',
                'url' => 'modules/evaluations/notes/'
            ],
            'bulletins' => [
                'name' => 'Bulletins',
                'icon' => 'fas fa-file-alt',
                'url' => 'modules/evaluations/bulletins/'
            ],
            'statistics' => [
                'name' => 'Statistiques',
                'icon' => 'fas fa-chart-bar',
                'url' => 'modules/evaluations/statistics/'
            ]
        ]
    ],
    'finance' => [
        'name' => 'Gestion Financière',
        'icon' => 'fas fa-money-bill-wave',
        'description' => 'Frais scolaires, comptabilité',
        'submenu' => [
            'fees' => [
                'name' => 'Frais Scolaires',
                'icon' => 'fas fa-dollar-sign',
                'url' => 'modules/finance/fees/'
            ],
            'payments' => [
                'name' => 'Paiements',
                'icon' => 'fas fa-credit-card',
                'url' => 'modules/finance/payments/'
            ],
            'expenses' => [
                'name' => 'Dépenses',
                'icon' => 'fas fa-receipt',
                'url' => 'modules/finance/expenses/'
            ],
            'reports' => [
                'name' => 'Rapports',
                'icon' => 'fas fa-chart-pie',
                'url' => 'modules/finance/reports/'
            ]
        ]
    ],
    'recouvrement' => [
        'name' => 'Recouvrement',
        'icon' => 'fas fa-hand-holding-usd',
        'description' => 'Gestion des dettes et recouvrement',
        'submenu' => [
            'dashboard' => [
                'name' => 'Tableau de Bord',
                'icon' => 'fas fa-tachometer-alt',
                'url' => 'modules/recouvrement/'
            ],
            'debtors' => [
                'name' => 'Liste des Débiteurs',
                'icon' => 'fas fa-exclamation-triangle',
                'url' => 'modules/finance/reports/debtors.php'
            ],
            'campaigns' => [
                'name' => 'Campagnes',
                'icon' => 'fas fa-bullhorn',
                'url' => 'modules/recouvrement/campaigns/'
            ],
            'notifications' => [
                'name' => 'Notifications',
                'icon' => 'fas fa-bell',
                'url' => 'modules/recouvrement/notifications/'
            ]
        ]
    ],
    'library' => [
        'name' => 'Bibliothèque',
        'icon' => 'fas fa-book-open',
        'description' => 'Livres, emprunts',
        'submenu' => [
            'books' => [
                'name' => 'Livres',
                'icon' => 'fas fa-book',
                'url' => 'modules/library/books/'
            ],
            'loans' => [
                'name' => 'Emprunts',
                'icon' => 'fas fa-handshake',
                'url' => 'modules/library/loans/'
            ],
            'reservations' => [
                'name' => 'Réservations',
                'icon' => 'fas fa-clock',
                'url' => 'modules/library/reservations/'
            ],
            'reports' => [
                'name' => 'Rapports',
                'icon' => 'fas fa-chart-bar',
                'url' => 'modules/library/reports/'
            ]
        ]
    ],
    'discipline' => [
        'name' => 'Discipline',
        'icon' => 'fas fa-gavel',
        'description' => 'Sanctions, comportement',
        'submenu' => [
            'incidents' => [
                'name' => 'Incidents',
                'icon' => 'fas fa-exclamation-circle',
                'url' => 'modules/discipline/incidents/'
            ],
            'sanctions' => [
                'name' => 'Sanctions',
                'icon' => 'fas fa-ban',
                'url' => 'modules/discipline/sanctions/'
            ],
            'rewards' => [
                'name' => 'Récompenses',
                'icon' => 'fas fa-trophy',
                'url' => 'modules/discipline/recompenses/'
            ],
            'reports' => [
                'name' => 'Rapports',
                'icon' => 'fas fa-file-alt',
                'url' => 'modules/discipline/reports/'
            ]
        ]
    ],
    'communication' => [
        'name' => 'Communication',
        'icon' => 'fas fa-bullhorn',
        'description' => 'Parents, circulaires',
        'submenu' => [
            'announcements' => [
                'name' => 'Annonces',
                'icon' => 'fas fa-bullhorn',
                'url' => 'modules/communication/annonces/'
            ],
            'messages' => [
                'name' => 'Messages',
                'icon' => 'fas fa-envelope',
                'url' => 'modules/communication/messages/'
            ],
            'sms' => [
                'name' => 'SMS',
                'icon' => 'fas fa-mobile-alt',
                'url' => 'modules/communication/sms/'
            ],
            'templates' => [
                'name' => 'Modèles',
                'icon' => 'fas fa-file-alt',
                'url' => 'modules/communication/templates/'
            ]
        ]
    ],
    'reports' => [
        'name' => 'Rapports et Statistiques',
        'icon' => 'fas fa-chart-bar',
        'description' => 'Tableaux de bord',
        'submenu' => [
            'academic' => [
                'name' => 'Rapports Académiques',
                'icon' => 'fas fa-graduation-cap',
                'url' => 'modules/reports/academic/'
            ],
            'financial' => [
                'name' => 'Rapports Financiers',
                'icon' => 'fas fa-chart-line',
                'url' => 'modules/finance/reports/'
            ],
            'administrative' => [
                'name' => 'Rapports Administratifs',
                'icon' => 'fas fa-clipboard-list',
                'url' => 'modules/reports/administrative/'
            ],
            'custom' => [
                'name' => 'Rapports Personnalisés',
                'icon' => 'fas fa-cogs',
                'url' => 'modules/reports/custom/'
            ]
        ]
    ]
]);

// Fonctions utilitaires
function getCurrentAcademicYear() {
    global $database;

    // Vérifier si la connexion à la base de données existe
    if (!isset($database) || !$database) {
        return null;
    }

    try {
        $stmt = $database->query("SELECT * FROM annees_scolaires WHERE status = 'active' LIMIT 1");
        return $stmt->fetch();
    } catch (Exception $e) {
        // Retourner null si la table n'existe pas encore
        return null;
    }
}

function formatDate($date, $format = 'd/m/Y') {
    if (empty($date)) {
        return '';
    }
    return date($format, strtotime($date));
}

function formatMoney($amount) {
    return number_format($amount, 2, ',', ' ') . ' FC';
}

function sanitizeInput($data) {
    return htmlspecialchars(strip_tags(trim($data)));
}

if (!function_exists('generateMatricule')) {
    function generateMatricule($prefix = 'MAT') {
        global $database;

        try {
            $year = date('Y');
            $pattern = $prefix . $year . '%';

            // Récupérer le dernier matricule de l'année
            $last_matricule = $database->query(
                "SELECT numero_matricule FROM eleves WHERE numero_matricule LIKE ? ORDER BY numero_matricule DESC LIMIT 1",
                [$pattern]
            )->fetch();

            if ($last_matricule) {
                // Extraire le numéro séquentiel (les 3 derniers chiffres)
                $last_number = intval(substr($last_matricule['numero_matricule'], -3));
                $new_number = $last_number + 1;
            } else {
                $new_number = 1;
            }

            return $prefix . $year . str_pad($new_number, 3, '0', STR_PAD_LEFT);

        } catch (Exception $e) {
            // En cas d'erreur, générer un matricule aléatoire
            return $prefix . date('Y') . str_pad(rand(1, 999), 3, '0', STR_PAD_LEFT);
        }
    }
}

function checkPermission($required_permission) {
    if (!isset($_SESSION['user_role'])) {
        return false;
    }
    $role = $_SESSION['user_role'];
    // Vérifier que la clé existe et que permissions est bien un array
    if (!defined('ROLES') || !isset(ROLES[$role]['permissions']) || !is_array(ROLES[$role]['permissions'])) {
        return false;
    }
    $user_permissions = ROLES[$role]['permissions'];
    if (in_array('all', $user_permissions)) {
        return true;
    }
    return in_array($required_permission, $user_permissions);
}

function redirectTo($url) {
    header("Location: $url");
    exit();
}

function showMessage($type, $message) {
    $_SESSION['message'] = ['type' => $type, 'text' => $message];
}

function displayMessage() {
    if (isset($_SESSION['message'])) {
        $message = $_SESSION['message'];
        echo "<div class='alert alert-{$message['type']} alert-dismissible fade show' role='alert'>
                {$message['text']}
                <button type='button' class='btn-close' data-bs-dismiss='alert'></button>
              </div>";
        unset($_SESSION['message']);
    }
}
?>
