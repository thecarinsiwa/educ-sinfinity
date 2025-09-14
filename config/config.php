<?php
/**
 * Configuration principale de l'application
 * Système de gestion scolaire - République Démocratique du Congo
 * 
 * Reconfiguré le 10/09/2025 à 14:21:35
 */

// Configuration de l'application
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

// Démarrer la session
session_start();

// Configuration des rôles et permissions (ancien système)
define('ROLES', [
    'admin' => [
        'name' => 'Administrateur',
        'permissions' => ['all']
    ],
    'directeur' => [
        'name' => 'Directeur',
        'permissions' => ['students', 'personnel', 'academic', 'evaluations', 'finance', 'recouvrement', 'reports', 'cartes_eleves']
    ],
    'enseignant' => [
        'name' => 'Enseignant',
        'permissions' => ['students_view', 'evaluations', 'academic_view', 'cartes_eleves_scan']
    ],
    'secretaire' => [
        'name' => 'Secrétaire',
        'permissions' => ['students', 'academic', 'communication', 'cartes_eleves']
    ],
    'comptable' => [
        'name' => 'Comptable',
        'permissions' => ['finance', 'recouvrement', 'reports_finance', 'cartes_eleves']
    ]
]);

// Configuration des modules avec sous-menus (URLs corrigées)
define('MODULES', array (
  'students' => 
  array (
    'name' => 'Gestion des Élèves',
    'icon' => 'fas fa-user-graduate',
    'description' => 'Inscriptions, dossiers, transferts',
    'submenu' => 
    array (
      'add' => 
      array (
        'name' => 'Gérer les Élèves',
        'icon' => 'fas fa-users',
        'url' => 'modules/students/index.php',
      ),
      'admissions' => 
      array (
        'name' => 'Admissions',
        'icon' => 'fas fa-user-plus',
        'url' => 'modules/students/admissions/index.php',
      ),
      'attendance' => 
      array (
        'name' => 'Présences',
        'icon' => 'fas fa-calendar-check',
        'url' => 'modules/students/attendance/index.php',
      ),
      'transfers' => 
      array (
        'name' => 'Transferts',
        'icon' => 'fas fa-exchange-alt',
        'url' => 'modules/students/transfers/index.php',
      ),
      'student-tracking' => 
      array (
        'name' => 'Suivi des Élèves',
        'icon' => 'fas fa-tasks',
        'url' => 'modules/students/student-tracking/index.php',
      ),
    ),
  ),
  'personnel' => 
  array (
    'name' => 'Gestion du Personnel',
    'icon' => 'fas fa-users-cog',
    'description' => 'Employés, salaires, contrats',
    'submenu' => 
    array (
      'list' => 
      array (
        'name' => 'Liste du Personnel',
        'icon' => 'fas fa-list',
        'url' => 'modules/personnel/index.php',
      ),
      'add' => 
      array (
        'name' => 'Ajouter Personnel',
        'icon' => 'fas fa-user-plus',
        'url' => 'modules/personnel/add.php',
      ),
      'create-account' => 
      array (
        'name' => 'Créer Compte',
        'icon' => 'fas fa-user-plus',
        'url' => 'modules/personnel/create-account.php',
      ),
    ),
  ),
  'academic' => 
  array (
    'name' => 'Gestion Académique',
    'icon' => 'fas fa-book',
    'description' => 'Classes, matières, emplois du temps',
    'submenu' => 
    array (
      'classes' => 
      array (
        'name' => 'Classes',
        'icon' => 'fas fa-school',
        'url' => 'modules/academic/classes/index.php',
      ),
      'subjects' => 
      array (
        'name' => 'Matières',
        'icon' => 'fas fa-book-open',
        'url' => 'modules/academic/subjects/index.php',
      ),
      'schedule' => 
      array (
        'name' => 'Emplois du Temps',
        'icon' => 'fas fa-calendar-alt',
        'url' => 'modules/academic/schedule/index.php',
      ),
      'years' => 
      array (
        'name' => 'Années Scolaires',
        'icon' => 'fas fa-calendar',
        'url' => 'modules/academic/years/index.php',
      ),
    ),
  ),
  'evaluations' => 
  array (
    'name' => 'Évaluations et Notes',
    'icon' => 'fas fa-chart-line',
    'description' => 'Bulletins, examens, moyennes',
    'submenu' => 
    array (
      'evaluations' => 
      array (
        'name' => 'Évaluations',
        'icon' => 'fas fa-clipboard-list',
        'url' => 'modules/evaluations/evaluations/index.php',
      ),
      'notes' => 
      array (
        'name' => 'Saisie des Notes',
        'icon' => 'fas fa-edit',
        'url' => 'modules/evaluations/notes/index.php',
      ),
      'bulletins' => 
      array (
        'name' => 'Bulletins',
        'icon' => 'fas fa-file-alt',
        'url' => 'modules/evaluations/bulletins/index.php',
      ),
      'statistics' => 
      array (
        'name' => 'Statistiques',
        'icon' => 'fas fa-chart-bar',
        'url' => 'modules/evaluations/statistics/index.php',
      ),
    ),
  ),
  'finance' => 
  array (
    'name' => 'Gestion Financière',
    'icon' => 'fas fa-dollar-sign',
    'description' => 'Frais, paiements, comptabilité',
    'submenu' => 
    array (
      'dashboard' => 
      array (
        'name' => 'Tableau de Bord',
        'icon' => 'fas fa-tachometer-alt',
        'url' => 'modules/finance/index.php',
      ),
      'fees' => 
      array (
        'name' => 'Frais Scolaires',
        'icon' => 'fas fa-dollar-sign',
        'url' => 'modules/finance/fees/index.php',
      ),
      'payments' => 
      array (
        'name' => 'Paiements',
        'icon' => 'fas fa-credit-card',
        'url' => 'modules/finance/payments/index.php',
      ),
      'devises' => 
      array (
        'name' => 'Devises',
        'icon' => 'fas fa-exchange-alt',
        'url' => 'modules/finance/devises/index.php',
      ),
      'expenses' => 
      array (
        'name' => 'Dépenses',
        'icon' => 'fas fa-receipt',
        'url' => 'modules/finance/expenses/index.php',
      ),
      'reports' => 
      array (
        'name' => 'Rapports',
        'icon' => 'fas fa-chart-pie',
        'url' => 'modules/finance/reports/index.php',
      ),
    ),
  ),
  'recouvrement' => 
  array (
    'name' => 'Recouvrement',
    'icon' => 'fas fa-hand-holding-usd',
    'description' => 'Gestion des dettes et recouvrement',
    'submenu' => 
    array (
      'dashboard' => 
      array (
        'name' => 'Tableau de Bord',
        'icon' => 'fas fa-tachometer-alt',
        'url' => 'modules/recouvrement/index.php',
      ),
      'debtors' => 
      array (
        'name' => 'Liste des Débiteurs',
        'icon' => 'fas fa-exclamation-triangle',
        'url' => 'modules/finance/reports/debtors.php',
      ),
      'campaigns' => 
      array (
        'name' => 'Campagnes',
        'icon' => 'fas fa-bullhorn',
        'url' => 'modules/recouvrement/campaigns/index.php',
      ),
      'notifications' => 
      array (
        'name' => 'Notifications',
        'icon' => 'fas fa-bell',
        'url' => 'modules/recouvrement/notifications/index.php',
      ),
    ),
  ),
  'library' => 
  array (
    'name' => 'Bibliothèque',
    'icon' => 'fas fa-book',
    'description' => 'Gestion des livres et emprunts',
    'submenu' => 
    array (
      'books' => 
      array (
        'name' => 'Livres',
        'icon' => 'fas fa-book',
        'url' => 'modules/library/books/index.php',
      ),
      'loans' => 
      array (
        'name' => 'Emprunts',
        'icon' => 'fas fa-hand-holding',
        'url' => 'modules/library/loans/index.php',
      ),
      'reservations' => 
      array (
        'name' => 'Réservations',
        'icon' => 'fas fa-calendar-plus',
        'url' => 'modules/library/reservations/add.php',
      ),
      'reports' => 
      array (
        'name' => 'Rapports',
        'icon' => 'fas fa-chart-line',
        'url' => 'modules/library/reports/index.php',
      ),
    ),
  ),
  'discipline' => 
  array (
    'name' => 'Discipline',
    'icon' => 'fas fa-gavel',
    'description' => 'Incidents, sanctions, récompenses',
    'submenu' => 
    array (
      'incidents' => 
      array (
        'name' => 'Incidents',
        'icon' => 'fas fa-exclamation-triangle',
        'url' => 'modules/discipline/incidents/index.php',
      ),
      'sanctions' => 
      array (
        'name' => 'Sanctions',
        'icon' => 'fas fa-ban',
        'url' => 'modules/discipline/sanctions/index.php',
      ),
      'rewards' => 
      array (
        'name' => 'Récompenses',
        'icon' => 'fas fa-trophy',
        'url' => 'modules/discipline/recompenses/index.php',
      ),
      'reports' => 
      array (
        'name' => 'Rapports',
        'icon' => 'fas fa-chart-bar',
        'url' => 'modules/discipline/reports/index.php',
      ),
    ),
  ),
  'communication' => 
  array (
    'name' => 'Communication',
    'icon' => 'fas fa-comments',
    'description' => 'Messages, annonces, SMS',
    'submenu' => 
    array (
      'announcements' => 
      array (
        'name' => 'Annonces',
        'icon' => 'fas fa-bullhorn',
        'url' => 'modules/communication/annonces/add.php',
      ),
      'messages' => 
      array (
        'name' => 'Messages',
        'icon' => 'fas fa-envelope',
        'url' => 'modules/communication/messages/index.php',
      ),
      'sms' => 
      array (
        'name' => 'SMS',
        'icon' => 'fas fa-sms',
        'url' => 'modules/communication/sms/index.php',
      ),
      'templates' => 
      array (
        'name' => 'Modèles',
        'icon' => 'fas fa-file-alt',
        'url' => 'modules/communication/templates/index.php',
      ),
    ),
  ),
  'cartes_eleves' => 
  array (
    'name' => 'Cartes d\'Élèves',
    'icon' => 'fas fa-id-card',
    'description' => 'Génération et gestion des cartes',
    'submenu' => 
    array (
      'list' => 
      array (
        'name' => 'Liste des Cartes',
        'icon' => 'fas fa-list',
        'url' => 'modules/cartes_eleves/index.php',
      ),
      'scanner' => 
      array (
        'name' => 'Scanner QR Code',
        'icon' => 'fas fa-qrcode',
        'url' => 'modules/cartes_eleves/qr-scanner.php',
      ),
      'settings' => 
      array (
        'name' => 'Paramètres',
        'icon' => 'fas fa-cog',
        'url' => 'modules/cartes_eleves/settings.php',
      ),
    ),
  ),
  'reports' => 
  array (
    'name' => 'Rapports et Statistiques',
    'icon' => 'fas fa-chart-pie',
    'description' => 'Rapports académiques et financiers',
    'submenu' => 
    array (
      'academic' => 
      array (
        'name' => 'Rapports Académiques',
        'icon' => 'fas fa-graduation-cap',
        'url' => 'modules/reports/academic/index.php',
      ),
      'financial' => 
      array (
        'name' => 'Rapports Financiers',
        'icon' => 'fas fa-chart-line',
        'url' => 'modules/finance/reports/index.php',
      ),
      'administrative' => 
      array (
        'name' => 'Rapports Administratifs',
        'icon' => 'fas fa-clipboard-list',
        'url' => 'modules/reports/administrative/index.php',
      ),
      'custom' => 
      array (
        'name' => 'Rapports Personnalisés',
        'icon' => 'fas fa-cogs',
        'url' => 'modules/reports/custom/index.php',
      ),
    ),
  ),
));

// Fonctions utilitaires
function getCurrentAcademicYear() {
    global $database;

    // Vérifier si la connexion à la base de données existe
    if (!isset($database) || !$database) {
        return null;
    }

    try {
        $stmt = $database->query("SELECT * FROM annees_scolaires WHERE status = 'active' ORDER BY date_debut DESC LIMIT 1");
        $annee = $stmt->fetch();
        
        if ($annee) {
            return $annee;
        }
    } catch (Exception $e) {
        error_log("Erreur lors de la récupération de l\'année scolaire : " . $e->getMessage());
    }
    
    return null;
}

function getCurrentAcademicYearId() {
    $annee = getCurrentAcademicYear();
    return $annee ? $annee['id'] : null;
}

function getCurrentAcademicYearName() {
    $annee = getCurrentAcademicYear();
    return $annee ? $annee['nom'] : 'Année non définie';
}

function redirectTo($url) {
    // Vérifier si les headers ont déjà été envoyés
    if (headers_sent()) {
        // Utiliser JavaScript pour la redirection
        echo "<script>window.location.href = '" . htmlspecialchars($url, ENT_QUOTES) . "';</script>";
        echo "<noscript><meta http-equiv='refresh' content='0;url=" . htmlspecialchars($url, ENT_QUOTES) . "'></noscript>";
        exit;
    } else {
        // Utiliser la redirection HTTP normale
        header("Location: " . $url);
        exit;
    }
}

function showMessage($type, $message) {
    $_SESSION['message'] = [
        'type' => $type,
        'text' => $message
    ];
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

function checkPermission($required_permission) {
    // Utiliser uniquement l'ancien système de rôles
    if (!isset($_SESSION['user_role'])) {
        return false;
    }
    
    $role = $_SESSION['user_role'];
    
    // Vérifier que la clé existe et que permissions est bien un array
    if (!defined('ROLES') || !isset(ROLES[$role]['permissions']) || !is_array(ROLES[$role]['permissions'])) {
        return false;
    }
    
    $user_permissions = ROLES[$role]['permissions'];
    
    // Si l'utilisateur a la permission 'all', il a accès à tout
    if (in_array('all', $user_permissions)) {
        return true;
    }
    
    // Vérifier si l'utilisateur a la permission spécifique
    return in_array($required_permission, $user_permissions);
}

// Inclure le gestionnaire de paramètres
require_once __DIR__ . '/../includes/settings-manager.php';

// Initialiser le gestionnaire de paramètres global
if (isset($database) && class_exists('SettingsManager')) {
    $settings_manager = new SettingsManager($database);
}
?>