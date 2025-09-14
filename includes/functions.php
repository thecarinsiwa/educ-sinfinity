<?php
/**
 * Fonctions utilitaires pour l'application
 * Application de gestion scolaire - République Démocratique du Congo
 */

// Ne pas inclure les fichiers de configuration ici pour éviter les inclusions circulaires
// Les fichiers de configuration doivent être inclus avant ce fichier

/**
 * Vérifier si l'utilisateur est connecté
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

/**
 * Vérifier si l'utilisateur est connecté, sinon rediriger vers login
 */
function requireLogin() {
    if (!isLoggedIn()) {
        // Déterminer le chemin correct selon le répertoire courant
        $current_dir = dirname($_SERVER['PHP_SELF']);
        if (strpos($current_dir, '/admin') !== false) {
            // Si on est dans le dossier admin, rediriger vers ../auth/login.php
            redirectTo('../auth/login.php');
        } else {
            // Sinon, rediriger vers auth/login.php
            redirectTo('auth/login.php');
        }
    }
}

/**
 * Vérifier la session et rediriger si nécessaire (version robuste)
 * Cette fonction doit être appelée au tout début de chaque page
 */
function checkSessionAndRedirect() {
    // Vérifier si la session est démarrée
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    // Vérifier si l'utilisateur est connecté
    if (!isLoggedIn()) {
        // Déterminer le chemin de redirection
        $current_dir = dirname($_SERVER['PHP_SELF']);
        $login_url = (strpos($current_dir, '/admin') !== false) ? '../auth/login.php' : 'auth/login.php';
        
        // Si les headers n'ont pas encore été envoyés, utiliser la redirection HTTP
        if (!headers_sent()) {
            header("Location: " . $login_url);
            exit;
        } else {
            // Sinon, utiliser JavaScript pour la redirection
            echo "<!DOCTYPE html><html><head><title>Redirection...</title></head><body>";
            echo "<script>window.location.href = '" . htmlspecialchars($login_url, ENT_QUOTES) . "';</script>";
            echo "<noscript><meta http-equiv='refresh' content='0;url=" . htmlspecialchars($login_url, ENT_QUOTES) . "'></noscript>";
            echo "<p>Redirection en cours... <a href='" . htmlspecialchars($login_url, ENT_QUOTES) . "'>Cliquez ici si la redirection ne fonctionne pas</a></p>";
            echo "</body></html>";
            exit;
        }
    }
}


/**
 * Vérifier l'accès à un module pour la sidebar
 * Utilise l'ancien système de rôles
 * 
 * @param string $module_key Clé du module
 * @return bool True si l'utilisateur peut accéder au module
 */
function checkModuleAccess($module_key) {
    if (!isLoggedIn()) {
        return false;
    }
    
    // Essayer d'abord le nouveau système basé sur la table roles
    global $database;
    
    if (isset($database) && isset($_SESSION['user_id'])) {
        try {
            // Récupérer les permissions du rôle de l'utilisateur
            $stmt = $database->query(
                "SELECT r.permissions 
                 FROM users u 
                 JOIN roles r ON u.role_id = r.id 
                 WHERE u.id = ? AND r.actif = 1",
                [$_SESSION['user_id']]
            );
            $result = $stmt->fetch();
            
            if ($result && $result['permissions']) {
                $permissions = json_decode($result['permissions'], true);
                
                // Vérifier si l'utilisateur a des permissions pour ce module
                if (is_array($permissions) && isset($permissions[$module_key])) {
                    $module_permissions = $permissions[$module_key];
                    if (is_array($module_permissions) && !empty($module_permissions)) {
                        return true;
                    }
                }
            }
        } catch (Exception $e) {
            error_log("Erreur dans checkModuleAccess (nouveau système): " . $e->getMessage());
        }
    }
    
    // Fallback vers l'ancien système
    return checkPermission($module_key);
}

/**
 * Vérifier les permissions de compatibilité pour la sidebar
 * 
 * @param string $module_key Clé du module
 * @return bool True si l'utilisateur peut accéder au module
 */
function checkSidebarPermissionCompat($module_key) {
    return checkModuleAccess($module_key);
}

/**
 * Obtenir l'URL correcte pour un élément de menu
 * Cette fonction est définie dans sidebar-url-fixer.php avec une implémentation plus complète
 * 
 * @param string $url URL de base
 * @param string $module_key Clé du module
 * @param string $submenu_key Clé du sous-menu
 * @return string URL corrigée
 */
// Fonction définie dans sidebar-url-fixer.php - pas de redéclaration ici

/**
 * Obtenir l'URL par défaut d'un module
 * Cette fonction est définie dans sidebar-url-fixer.php avec une implémentation plus complète
 * 
 * @param string $module_key Clé du module
 * @return string URL par défaut du module
 */
// Fonction définie dans sidebar-url-fixer.php - pas de redéclaration ici

/**
 * Obtenir les informations de l'utilisateur connecté
 */
function getCurrentUser($database = null) {
    if (!isLoggedIn()) {
        return null;
    }
    
    // Si $database n'est pas fourni, essayer de l'obtenir globalement
    if ($database === null) {
        global $database;
    }
    
    // Si toujours null, essayer de créer une nouvelle connexion
    if ($database === null) {
        try {
            require_once dirname(__DIR__) . '/config/database.php';
        } catch (Exception $e) {
            error_log("Erreur connexion DB dans getCurrentUser: " . $e->getMessage());
            return null;
        }
    }
    
    try {
        $stmt = $database->query(
            "SELECT u.*, p.nom, p.prenom, p.fonction, r.nom as role_nom, r.description as role_description
             FROM users u 
             LEFT JOIN personnel p ON u.id = p.user_id 
             LEFT JOIN roles r ON u.role_id = r.id
             WHERE u.id = ?", 
            [$_SESSION['user_id']]
        );
        
        return $stmt->fetch();
    } catch (Exception $e) {
        error_log("Erreur getCurrentUser: " . $e->getMessage());
        return null;
    }
}

/**
 * Mettre à jour les informations de session de l'utilisateur
 * Utile après modification du rôle ou des permissions
 */
function refreshUserSession() {
    global $database;
    
    if (!isset($_SESSION['user_id'])) {
        return false;
    }
    
    try {
        // Récupérer les informations mises à jour de l'utilisateur
        $stmt = $database->query(
            "SELECT u.*, r.nom as role_nom, r.actif as role_actif
             FROM users u 
             LEFT JOIN roles r ON u.role_id = r.id 
             WHERE u.id = ?",
            [$_SESSION['user_id']]
        );
        $user = $stmt->fetch();
        
        if ($user) {
            // Mettre à jour les informations de session
            $_SESSION['username'] = $user['username'];
            $_SESSION['user_role'] = $user['role_nom'] ?? 'user';
            $_SESSION['user_role_id'] = $user['role_id'];
            $_SESSION['user_full_name'] = $user['nom'] . ' ' . $user['prenom'];
            $_SESSION['last_activity'] = time();
            
            return true;
        }
    } catch (Exception $e) {
        error_log("Erreur refreshUserSession: " . $e->getMessage());
    }
    
    return false;
}

/**
 * Authentifier un utilisateur avec SHA1
 */
function authenticateUser($username, $password) {
    global $database;

    $stmt = $database->query(
        "SELECT * FROM users WHERE username = ? AND status = 'actif'",
        [$username]
    );

    $user = $stmt->fetch();

    // Vérifier avec SHA1
    $password_hash = sha1($password);

    if ($user && ($user['password'] === $password_hash || password_verify($password, $user['password']))) {
        // Récupérer le rôle depuis la table roles
        $role_stmt = $database->query(
            "SELECT r.nom FROM roles r WHERE r.id = ? AND r.actif = 1",
            [$user['role_id']]
        );
        $role = $role_stmt->fetch();
        
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['user_role'] = $role ? $role['nom'] : 'user';
        $_SESSION['user_role_id'] = $user['role_id'];
        $_SESSION['user_nature'] = $user['nature'] ?? 'staff';
        $_SESSION['user_full_name'] = $user['nom'] . ' ' . $user['prenom'];
        $_SESSION['last_activity'] = time();

        // Mettre à jour la dernière connexion
        $database->query(
            "UPDATE users SET derniere_connexion = NOW() WHERE id = ?",
            [$user['id']]
        );

        return true;
    }

    return false;
}

/**
 * Créer un hash SHA1 pour un mot de passe
 */
function hashPassword($password) {
    return sha1($password);
}

/**
 * Enregistrer une action utilisateur (alias pour logUserAction)
 */
function logAction($module, $details = null, $target_id = null) {
    return logUserAction('action', $module, $details, $target_id);
}

/**
 * Enregistrer une action utilisateur pour l'historique
 */
function logUserAction($action, $module, $details = null, $target_id = null) {
    global $database;

    if (!isLoggedIn()) {
        return false;
    }

    try {
        $database->query(
            "INSERT INTO user_actions_log (user_id, action, module, details, target_id, ip_address, user_agent, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, NOW())",
            [
                $_SESSION['user_id'],
                $action,
                $module,
                $details,
                $target_id,
                $_SERVER['REMOTE_ADDR'] ?? null,
                $_SERVER['HTTP_USER_AGENT'] ?? null
            ]
        );
        return true;
    } catch (Exception $e) {
        error_log("Erreur lors de l'enregistrement de l'action utilisateur: " . $e->getMessage());
        return false;
    }
}

/**
 * Déconnecter l'utilisateur
 */
function logoutUser() {
    session_unset();
    session_destroy();
    redirectTo('../auth/login.php');
}

/**
 * Vérifier la validité de la session
 */
function checkSessionValidity() {
    if (isLoggedIn()) {
        if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > SESSION_LIFETIME)) {
            logoutUser();
        }
        $_SESSION['last_activity'] = time();
    }
}

/**
 * Obtenir la liste des classes
 */
function getClasses($annee_scolaire_id = null) {
    global $database;
    
    $sql = "SELECT c.*, a.annee 
            FROM classes c 
            LEFT JOIN annees_scolaires a ON c.annee_scolaire_id = a.id";
    $params = [];
    
    if ($annee_scolaire_id) {
        $sql .= " WHERE c.annee_scolaire_id = ?";
        $params[] = $annee_scolaire_id;
    }
    
    $sql .= " ORDER BY c.niveau, c.nom";
    
    $stmt = $database->query($sql, $params);
    return $stmt->fetchAll();
}

/**
 * Obtenir la liste des matières
 */
function getMatieres($niveau = null) {
    global $database;
    
    $sql = "SELECT * FROM matieres";
    $params = [];
    
    if ($niveau) {
        $sql .= " WHERE niveau = ?";
        $params[] = $niveau;
    }
    
    $sql .= " ORDER BY nom";
    
    $stmt = $database->query($sql, $params);
    return $stmt->fetchAll();
}

/**
 * Obtenir la liste du personnel
 */
function getPersonnel($fonction = null) {
    global $database;
    
    $sql = "SELECT * FROM personnel WHERE status = 'actif'";
    $params = [];
    
    if ($fonction) {
        $sql .= " AND fonction = ?";
        $params[] = $fonction;
    }
    
    $sql .= " ORDER BY nom, prenom";
    
    $stmt = $database->query($sql, $params);
    return $stmt->fetchAll();
}

/**
 * Obtenir les statistiques générales
 */
function getGeneralStats() {
    global $database;
    
    $stats = [];
    
    // Nombre total d'élèves inscrits (actifs + inscrits)
    $stmt = $database->query("SELECT COUNT(*) as total FROM eleves WHERE status IN ('actif', 'inscrit')");
    $stats['total_eleves'] = $stmt->fetch()['total'];
    
    // Nombre total d'enseignants
    $stmt = $database->query("SELECT COUNT(*) as total FROM personnel WHERE fonction = 'enseignant' AND status = 'actif'");
    $stats['total_enseignants'] = $stmt->fetch()['total'];
    
    // Nombre total de classes
    $stmt = $database->query("SELECT COUNT(*) as total FROM classes");
    $stats['total_classes'] = $stmt->fetch()['total'];
    
    // Montant total des paiements ce mois
    $stmt = $database->query("SELECT SUM(montant) as total FROM paiements WHERE MONTH(date_paiement) = MONTH(CURRENT_DATE) AND YEAR(date_paiement) = YEAR(CURRENT_DATE)");
    $stats['paiements_mois'] = $stmt->fetch()['total'] ?? 0;
    
    return $stats;
}

/**
 * Calculer l'âge à partir de la date de naissance
 */
function calculateAge($birthdate) {
    $today = new DateTime();
    $birth = new DateTime($birthdate);
    return $today->diff($birth)->y;
}

/**
 * Générer un numéro de reçu unique
 */
function generateReceiptNumber() {
    global $database;

    $year = date('Y');
    $prefix = 'REC' . $year;

    // Obtenir le dernier numéro pour cette année
    $stmt = $database->query(
        "SELECT recu_numero FROM paiements
         WHERE recu_numero LIKE ?
         ORDER BY recu_numero DESC LIMIT 1",
        [$prefix . '%']
    );

    $last_receipt = $stmt->fetch();

    if ($last_receipt) {
        $last_number = (int)substr($last_receipt['recu_numero'], -4);
        $new_number = $last_number + 1;
    } else {
        $new_number = 1;
    }

    return $prefix . str_pad($new_number, 4, '0', STR_PAD_LEFT);
}

/**
 * Valider un email
 */
function isValidEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Valider une date
 */
function isValidDate($date, $format = 'Y-m-d') {
    if (empty($date)) {
        return false;
    }

    $dateObj = DateTime::createFromFormat($format, $date);
    return $dateObj && $dateObj->format($format) === $date;
}

/**
 * Valider un numéro de téléphone (format RDC)
 */
function isValidPhone($phone) {
    // Format: +243 XXX XXX XXX ou 0XXX XXX XXX
    $pattern = '/^(\+243|0)[0-9]{9}$/';
    return preg_match($pattern, str_replace(' ', '', $phone));
}

/**
 * Uploader un fichier
 */
function uploadFile($file, $destination_folder, $allowed_types = null) {
    if (!isset($file['tmp_name']) || empty($file['tmp_name'])) {
        return ['success' => false, 'message' => 'Aucun fichier sélectionné'];
    }
    
    $allowed_types = $allowed_types ?? array_merge(ALLOWED_IMAGE_TYPES, ALLOWED_DOC_TYPES);
    
    $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    
    if (!in_array($file_extension, $allowed_types)) {
        return ['success' => false, 'message' => 'Type de fichier non autorisé'];
    }
    
    if ($file['size'] > MAX_FILE_SIZE) {
        return ['success' => false, 'message' => 'Fichier trop volumineux'];
    }
    
    $new_filename = uniqid() . '.' . $file_extension;
    $destination = $destination_folder . '/' . $new_filename;
    
    if (!is_dir($destination_folder)) {
        mkdir($destination_folder, 0755, true);
    }
    
    if (move_uploaded_file($file['tmp_name'], $destination)) {
        return ['success' => true, 'filename' => $new_filename, 'path' => $destination];
    }
    
    return ['success' => false, 'message' => 'Erreur lors du téléchargement'];
}

/**
 * Obtenir le nom du mois en français
 */
function getMonthName($month_number) {
    $months = [
        1 => 'Janvier', 2 => 'Février', 3 => 'Mars', 4 => 'Avril',
        5 => 'Mai', 6 => 'Juin', 7 => 'Juillet', 8 => 'Août',
        9 => 'Septembre', 10 => 'Octobre', 11 => 'Novembre', 12 => 'Décembre'
    ];
    
    return $months[$month_number] ?? '';
}

/**
 * Obtenir le nom du jour en français
 */
function getDayName($day_name) {
    $days = [
        'monday' => 'Lundi', 'tuesday' => 'Mardi', 'wednesday' => 'Mercredi',
        'thursday' => 'Jeudi', 'friday' => 'Vendredi', 'saturday' => 'Samedi', 'sunday' => 'Dimanche'
    ];

    return $days[strtolower($day_name)] ?? $day_name;
}

/**
 * Formater une date/heure pour l'affichage
 */
function formatDateTime($datetime, $format = 'd/m/Y H:i') {
    if (empty($datetime) || $datetime === '0000-00-00 00:00:00') {
        return '-';
    }

    try {
        $date = new DateTime($datetime);
        return $date->format($format);
    } catch (Exception $e) {
        return $datetime; // Retourner la valeur originale en cas d'erreur
    }
}

/**
 * Formater une date pour l'affichage
 */
function formatDate($date, $format = 'd/m/Y') {
    if (empty($date) || $date === '0000-00-00') {
        return '-';
    }

    try {
        $dateObj = new DateTime($date);
        return $dateObj->format($format);
    } catch (Exception $e) {
        return $date; // Retourner la valeur originale en cas d'erreur
    }
}

/**
 * Formater une heure pour l'affichage
 */
function formatTime($time, $format = 'H:i') {
    if (empty($time)) {
        return '-';
    }

    try {
        $timeObj = new DateTime($time);
        return $timeObj->format($format);
    } catch (Exception $e) {
        return $time; // Retourner la valeur originale en cas d'erreur
    }
}

/**
 * Formater un montant d'argent pour l'affichage
 */
function formatMoney($amount, $currency = 'FC', $decimals = 0) {
    if ($amount === null || $amount === '') {
        return '0 ' . $currency;
    }
    
    // Convertir en nombre
    $amount = floatval($amount);
    
    // Formater avec des espaces comme séparateurs de milliers
    $formatted = number_format($amount, $decimals, ',', ' ');
    
    return $formatted . ' ' . $currency;
}


/**
 * Calculer le temps écoulé depuis une date
 */
function timeAgo($datetime) {
    $time = time() - strtotime($datetime);

    if ($time < 60) {
        return $time . ' seconde' . ($time > 1 ? 's' : '');
    } elseif ($time < 3600) {
        $minutes = floor($time / 60);
        return $minutes . ' minute' . ($minutes > 1 ? 's' : '');
    } elseif ($time < 86400) {
        $hours = floor($time / 3600);
        return $hours . ' heure' . ($hours > 1 ? 's' : '');
    } elseif ($time < 2592000) {
        $days = floor($time / 86400);
        return $days . ' jour' . ($days > 1 ? 's' : '');
    } elseif ($time < 31536000) {
        $months = floor($time / 2592000);
        return $months . ' mois';
    } else {
        $years = floor($time / 31536000);
        return $years . ' an' . ($years > 1 ? 's' : '');
    }
}

/**
 * Activer un compte utilisateur
 */
function activateUser($user_id, $activated_by = null) {
    global $database;

    if (!isset($database) || !$database) {
        return false;
    }

    try {
        // Récupérer les informations de l'utilisateur
        $user = $database->query(
            "SELECT username, nom, prenom, status FROM users WHERE id = ?",
            [$user_id]
        )->fetch();

        if (!$user) {
            throw new Exception('Utilisateur non trouvé');
        }

        if ($user['status'] === 'actif') {
            throw new Exception('L\'utilisateur est déjà actif');
        }

        // Activer l'utilisateur
        $database->query(
            "UPDATE users SET status = 'actif', updated_at = NOW() WHERE id = ?",
            [$user_id]
        );

        // Enregistrer l'action
        if ($activated_by && isLoggedIn()) {
            logUserAction(
                'activate_user',
                'users',
                'Compte activé pour: ' . $user['username'] . ' (' . $user['nom'] . ' ' . $user['prenom'] . ')',
                $user_id
            );
        }

        return true;

    } catch (Exception $e) {
        error_log("Erreur lors de l'activation de l'utilisateur: " . $e->getMessage());
        return false;
    }
}

/**
 * Désactiver un compte utilisateur
 */
function deactivateUser($user_id, $deactivated_by = null) {
    global $database;

    if (!isset($database) || !$database) {
        return false;
    }

    try {
        // Récupérer les informations de l'utilisateur
        $user = $database->query(
            "SELECT username, nom, prenom, status FROM users WHERE id = ?",
            [$user_id]
        )->fetch();

        if (!$user) {
            throw new Exception('Utilisateur non trouvé');
        }

        if ($user['status'] === 'inactif') {
            throw new Exception('L\'utilisateur est déjà inactif');
        }

        // Désactiver l'utilisateur
        $database->query(
            "UPDATE users SET status = 'inactif', updated_at = NOW() WHERE id = ?",
            [$user_id]
        );

        // Supprimer les sessions actives de cet utilisateur
        $database->query(
            "DELETE FROM user_sessions WHERE user_id = ?",
            [$user_id]
        );

        // Enregistrer l'action
        if ($deactivated_by && isLoggedIn()) {
            logUserAction(
                'deactivate_user',
                'users',
                'Compte désactivé pour: ' . $user['username'] . ' (' . $user['nom'] . ' ' . $user['prenom'] . ')',
                $user_id
            );
        }

        return true;

    } catch (Exception $e) {
        error_log("Erreur lors de la désactivation de l'utilisateur: " . $e->getMessage());
        return false;
    }
}

/**
 * Fonctions de gestion des devises
 */

/**
 * Obtenir la devise par défaut
 */
function getDefaultCurrency() {
    global $database;
    
    try {
        $stmt = $database->query("SELECT * FROM devises WHERE devise_par_defaut = TRUE AND active = TRUE LIMIT 1");
        return $stmt->fetch();
    } catch (Exception $e) {
        error_log("Erreur lors de la récupération de la devise par défaut: " . $e->getMessage());
        return null;
    }
}

/**
 * Obtenir toutes les devises actives
 */
function getActiveCurrencies() {
    global $database;
    
    try {
        $stmt = $database->query("SELECT * FROM devises WHERE active = TRUE ORDER BY devise_par_defaut DESC, code");
        return $stmt->fetchAll();
    } catch (Exception $e) {
        error_log("Erreur lors de la récupération des devises actives: " . $e->getMessage());
        return [];
    }
}

/**
 * Obtenir une devise par son ID
 */
function getCurrencyById($id) {
    global $database;
    
    try {
        $stmt = $database->query("SELECT * FROM devises WHERE id = ?", [$id]);
        return $stmt->fetch();
    } catch (Exception $e) {
        error_log("Erreur lors de la récupération de la devise: " . $e->getMessage());
        return null;
    }
}

/**
 * Convertir un montant d'une devise vers la devise par défaut
 */
function convertToDefaultCurrency($montant, $devise_id) {
    global $database;
    
    try {
        if (!$devise_id) {
            return $montant; // Pas de conversion si pas de devise spécifiée
        }
        
        $devise = getCurrencyById($devise_id);
        if (!$devise) {
            return $montant;
        }
        
        return $montant * $devise['taux_conversion'];
    } catch (Exception $e) {
        error_log("Erreur lors de la conversion de devise: " . $e->getMessage());
        return $montant;
    }
}

/**
 * Convertir un montant de la devise par défaut vers une autre devise
 */
function convertFromDefaultCurrency($montant, $devise_id) {
    global $database;
    
    try {
        if (!$devise_id) {
            return $montant;
        }
        
        $devise = getCurrencyById($devise_id);
        if (!$devise || $devise['taux_conversion'] == 0) {
            return $montant;
        }
        
        return $montant / $devise['taux_conversion'];
    } catch (Exception $e) {
        error_log("Erreur lors de la conversion de devise: " . $e->getMessage());
        return $montant;
    }
}

/**
 * Formater un montant avec sa devise
 */
function formatCurrency($montant, $devise_id = null, $show_symbol = true) {
    global $database;
    
    try {
        if (!$devise_id) {
            $devise = getDefaultCurrency();
        } else {
            $devise = getCurrencyById($devise_id);
        }
        
        if (!$devise) {
            return number_format($montant, 2);
        }
        
        $formatted = number_format($montant, 2);
        
        if ($show_symbol) {
            if ($devise['symbole'] === '$' || $devise['symbole'] === '€' || $devise['symbole'] === '£') {
                return $devise['symbole'] . $formatted;
            } else {
                return $formatted . ' ' . $devise['symbole'];
            }
        }
        
        return $formatted . ' ' . $devise['code'];
    } catch (Exception $e) {
        error_log("Erreur lors du formatage de la devise: " . $e->getMessage());
        return number_format($montant, 2);
    }
}

/**
 * Obtenir le taux de conversion entre deux devises
 */
function getExchangeRate($from_currency_id, $to_currency_id) {
    global $database;
    
    try {
        if ($from_currency_id == $to_currency_id) {
            return 1.0;
        }
        
        $from_currency = getCurrencyById($from_currency_id);
        $to_currency = getCurrencyById($to_currency_id);
        
        if (!$from_currency || !$to_currency) {
            return 1.0;
        }
        
        // Conversion via la devise par défaut
        if ($from_currency['devise_par_defaut']) {
            return $to_currency['taux_conversion'];
        } elseif ($to_currency['devise_par_defaut']) {
            return 1 / $from_currency['taux_conversion'];
        } else {
            return $to_currency['taux_conversion'] / $from_currency['taux_conversion'];
        }
    } catch (Exception $e) {
        error_log("Erreur lors du calcul du taux de change: " . $e->getMessage());
        return 1.0;
    }
}

/**
 * Formater un montant avec la devise par défaut
 * Cette fonction est un alias de formatCurrency avec la devise par défaut
 */
function formatMoneyDefault($montant) {
    return formatCurrency($montant, null, true);
}

/**
 * Formater un montant avec sa devise et afficher l'équivalent en devise par défaut
 */
function formatMoneyWithDefault($montant, $devise_id, $montant_devise_par_defaut = null) {
    global $database;
    
    try {
        $devise = getCurrencyById($devise_id);
        $devise_par_defaut = getDefaultCurrency();
        
        if (!$devise || !$devise_par_defaut) {
            return formatCurrency($montant, null, true);
        }
        
        $formatted = formatCurrency($montant, $devise_id, true);
        
        // Si c'est une devise différente de la devise par défaut, afficher l'équivalent
        if ($devise['code'] !== $devise_par_defaut['code']) {
            $equivalent = $montant_devise_par_defaut ?: convertToDefaultCurrency($montant, $devise_id);
            $formatted .= ' <small class="text-muted">(' . formatCurrency($equivalent, null, true) . ')</small>';
        }
        
        return $formatted;
    } catch (Exception $e) {
        error_log("Erreur lors du formatage avec devise par défaut: " . $e->getMessage());
        return formatCurrency($montant, null, true);
    }
}

/**
 * Vérifier si l'utilisateur a une permission spécifique
 */
function hasPermission($module, $action = 'view', $database = null) {
    if (!isLoggedIn()) {
        return false;
    }
    
    $user = getCurrentUser($database);
    if (!$user) {
        return false;
    }
    
    // Récupérer le rôle de l'utilisateur
    global $database;
    $stmt = $database->query("SELECT * FROM roles WHERE id = ?", [$user['role_id']]);
    $role = $stmt->fetch();
    
    if (!$role) {
        return false;
    }
    
    // L'administrateur a toutes les permissions
    if ($role['nom'] === 'admin' || in_array('all', json_decode($role['permissions'], true) ?: [])) {
        return true;
    }
    
    // Vérifier les permissions basées sur le rôle
    $permissions = json_decode($role['permissions'], true) ?: [];
    
    // Permission spécifique pour le module
    $specific_permission = $module . '_' . $action;
    
    // Vérifier les permissions
    if (in_array('all', $permissions)) {
        return true;
    }
    
    if (in_array($module, $permissions)) {
        return true;
    }
    
    if (in_array($specific_permission, $permissions)) {
        return true;
    }
    
    return false;
}

/**
 * Accorder des permissions d'accès à des pages pour un rôle spécifique
 * Utilise les données de la base de données de la table 'roles'
 * 
 * @param string $role_nom Nom du rôle dans la base de données
 * @param string $module Nom du module (ex: 'academic', 'students', 'finance')
 * @param array $pages Liste des pages avec leurs permissions
 * @return bool True si succès, False si erreur
 */
function grantPagePermissions($role_nom, $module, $pages) {
    global $database;
    
    try {
        // Vérifier que le rôle existe
        $role = $database->query(
            "SELECT id, nom, permissions FROM roles WHERE nom = ? AND actif = 1",
            [$role_nom]
        )->fetch();
        
        if (!$role) {
            error_log("Erreur: Le rôle '$role_nom' n'existe pas ou est inactif");
            return false;
        }
        
        // Décoder les permissions existantes
        $existing_permissions = [];
        if ($role['permissions']) {
            $decoded = json_decode($role['permissions'], true);
            if ($decoded) {
                $existing_permissions = $decoded;
            }
        }
        
        // Ajouter ou mettre à jour les permissions du module
        if (!isset($existing_permissions[$module])) {
            $existing_permissions[$module] = [
                'name' => ucfirst($module),
                'pages' => []
            ];
        }
        
        // Traiter chaque page
        foreach ($pages as $page_name => $page_data) {
            if (is_array($page_data) && isset($page_data['permissions'])) {
                // Page avec permissions directes
                $existing_permissions[$module]['pages'][$page_name] = [
                    'name' => $page_data['name'] ?? ucfirst($page_name),
                    'permissions' => array_unique($page_data['permissions'])
                ];
            } elseif (is_array($page_data) && isset($page_data['pages'])) {
                // Page avec sous-pages
                $existing_permissions[$module]['pages'][$page_name] = [
                    'name' => $page_data['name'] ?? ucfirst($page_name),
                    'pages' => []
                ];
                
                foreach ($page_data['pages'] as $subpage_name => $subpage_data) {
                    if (isset($subpage_data['permissions'])) {
                        $existing_permissions[$module]['pages'][$page_name]['pages'][$subpage_name] = [
                            'name' => $subpage_data['name'] ?? ucfirst($subpage_name),
                            'permissions' => array_unique($subpage_data['permissions'])
                        ];
                    }
                }
            }
        }
        
        // Encoder et sauvegarder
        $permissions_json = json_encode($existing_permissions, JSON_UNESCAPED_UNICODE);
        
        $result = $database->execute(
            "UPDATE roles SET permissions = ?, date_modification = NOW() WHERE id = ?",
            [$permissions_json, $role['id']]
        );
        
        if ($result) {
            error_log("Succès: Permissions accordées au rôle '$role_nom' pour le module '$module'");
            return true;
        } else {
            error_log("Erreur: Impossible de mettre à jour les permissions pour le rôle '$role_nom'");
            return false;
        }
        
    } catch (Exception $e) {
        error_log("Erreur lors de l'octroi des permissions: " . $e->getMessage());
        return false;
    }
}

/**
 * Révoquer des permissions d'accès à des pages pour un rôle spécifique
 * 
 * @param string $role_nom Nom du rôle dans la base de données
 * @param string $module Nom du module
 * @param array $pages Liste des pages à révoquer (optionnel, si vide révoque tout le module)
 * @return bool True si succès, False si erreur
 */
function revokePagePermissions($role_nom, $module, $pages = []) {
    global $database;
    
    try {
        // Vérifier que le rôle existe
        $role = $database->query(
            "SELECT id, nom, permissions FROM roles WHERE nom = ? AND actif = 1",
            [$role_nom]
        )->fetch();
        
        if (!$role) {
            error_log("Erreur: Le rôle '$role_nom' n'existe pas ou est inactif");
            return false;
        }
        
        // Décoder les permissions existantes
        $existing_permissions = [];
        if ($role['permissions']) {
            $decoded = json_decode($role['permissions'], true);
            if ($decoded) {
                $existing_permissions = $decoded;
            }
        }
        
        if (empty($pages)) {
            // Révoquer tout le module
            if (isset($existing_permissions[$module])) {
                unset($existing_permissions[$module]);
            }
        } else {
            // Révoquer des pages spécifiques
            if (isset($existing_permissions[$module]['pages'])) {
                foreach ($pages as $page_name) {
                    if (isset($existing_permissions[$module]['pages'][$page_name])) {
                        unset($existing_permissions[$module]['pages'][$page_name]);
                    }
                }
                
                // Si plus de pages, supprimer le module
                if (empty($existing_permissions[$module]['pages'])) {
                    unset($existing_permissions[$module]);
                }
            }
        }
        
        // Encoder et sauvegarder
        $permissions_json = json_encode($existing_permissions, JSON_UNESCAPED_UNICODE);
        
        $result = $database->execute(
            "UPDATE roles SET permissions = ?, date_modification = NOW() WHERE id = ?",
            [$permissions_json, $role['id']]
        );
        
        if ($result) {
            error_log("Succès: Permissions révoquées pour le rôle '$role_nom' dans le module '$module'");
            return true;
        } else {
            error_log("Erreur: Impossible de révoquer les permissions pour le rôle '$role_nom'");
            return false;
        }
        
    } catch (Exception $e) {
        error_log("Erreur lors de la révocation des permissions: " . $e->getMessage());
        return false;
    }
}

/**
 * Obtenir toutes les permissions d'un rôle spécifique
 * 
 * @param string $role_nom Nom du rôle dans la base de données
 * @return array|false Permissions du rôle ou False si erreur
 */
function getRolePermissions($role_nom) {
    global $database;
    
    try {
        $role = $database->query(
            "SELECT id, nom, permissions FROM roles WHERE nom = ? AND actif = 1",
            [$role_nom]
        )->fetch();
        
        if (!$role) {
            error_log("Erreur: Le rôle '$role_nom' n'existe pas ou est inactif");
            return false;
        }
        
        $permissions = [];
        if ($role['permissions']) {
            $decoded = json_decode($role['permissions'], true);
            if ($decoded) {
                $permissions = $decoded;
            }
        }
        
        return $permissions;
        
    } catch (Exception $e) {
        error_log("Erreur lors de la récupération des permissions: " . $e->getMessage());
        return false;
    }
}

/**
 * Vérifier si un rôle a des permissions pour un module/page/action spécifique
 * 
 * @param string $role_nom Nom du rôle dans la base de données
 * @param string $module Nom du module
 * @param string $page Nom de la page
 * @param string $action Action à vérifier
 * @param string $subpage Nom de la sous-page (optionnel)
 * @return bool True si le rôle a la permission, False sinon
 */
function roleHasPagePermission($role_nom, $module, $page, $action, $subpage = null) {
    $permissions = getRolePermissions($role_nom);
    
    if (!$permissions || !isset($permissions[$module])) {
        return false;
    }
    
    $module_permissions = $permissions[$module];
    
    if (!isset($module_permissions['pages'][$page])) {
        return false;
    }
    
    $page_data = $module_permissions['pages'][$page];
    
    if ($subpage) {
        // Vérifier dans les sous-pages
        if (isset($page_data['pages'][$subpage]['permissions'])) {
            return in_array($action, $page_data['pages'][$subpage]['permissions']);
        }
    } else {
        // Vérifier dans la page directe
        if (isset($page_data['permissions'])) {
            return in_array($action, $page_data['permissions']);
        }
    }
    
    return false;
}

/**
 * Synchroniser les permissions d'un module pour tous les rôles actifs
 * 
 * @param string $module Nom du module
 * @param array $module_pages Structure des pages du module (format detailed-permissions)
 * @return array Résultat de la synchronisation par rôle
 */
function syncModulePermissions($module, $module_pages) {
    global $database;
    
    $results = [];
    
    try {
        // Récupérer tous les rôles actifs
        $roles = $database->query("SELECT id, nom, permissions FROM roles WHERE actif = 1")->fetchAll();
        
        foreach ($roles as $role) {
            $result = grantPagePermissions($role['nom'], $module, $module_pages['pages']);
            $results[$role['nom']] = [
                'success' => $result,
                'message' => $result ? 'Permissions synchronisées' : 'Erreur lors de la synchronisation'
            ];
        }
        
        return $results;
        
    } catch (Exception $e) {
        error_log("Erreur lors de la synchronisation des permissions: " . $e->getMessage());
        return ['error' => $e->getMessage()];
    }
}

/**
 * Nettoyer les données d'entrée
 */
function sanitizeInput($data) {
    return htmlspecialchars(strip_tags(trim($data)));
}

/**
 * Obtenir l'icône FontAwesome pour une action
 */
if (!function_exists('getActionIcon')) {
function getActionIcon($action) {
    $icons = [
        'create_user' => 'user-plus',
        'update_user' => 'user-edit',
        'delete_user' => 'user-times',
        'login' => 'sign-in-alt',
        'logout' => 'sign-out-alt',
        'change_password' => 'key',
        'update_profile' => 'user-cog',
        'view_user_profile' => 'eye',
        'create_student' => 'user-plus',
        'update_student' => 'user-edit',
        'delete_student' => 'user-times',
        'view_student' => 'eye',
        'create_class' => 'school',
        'update_class' => 'edit',
        'delete_class' => 'trash',
        'view_class' => 'eye',
        'create_subject' => 'book',
        'update_subject' => 'edit',
        'delete_subject' => 'trash',
        'view_subject' => 'eye',
        'create_payment' => 'credit-card',
        'update_payment' => 'edit',
        'delete_payment' => 'trash',
        'view_payment' => 'eye',
        'create_fee' => 'money-bill',
        'update_fee' => 'edit',
        'delete_fee' => 'trash',
        'view_fee' => 'eye',
        'create_absence' => 'user-times',
        'justify_absence' => 'check',
        'update_absence' => 'edit'
    ];
    return $icons[$action] ?? 'info-circle';
}
}

/**
 * Obtenir le libellé lisible pour une action
 */
if (!function_exists('getActionLabel')) {
function getActionLabel($action) {
    $labels = [
        'create_user' => 'Utilisateur créé',
        'update_user' => 'Utilisateur modifié',
        'delete_user' => 'Utilisateur supprimé',
        'login' => 'Connexion',
        'logout' => 'Déconnexion',
        'change_password' => 'Mot de passe changé',
        'update_profile' => 'Profil mis à jour',
        'view_user_profile' => 'Profil consulté',
        'create_student' => 'Élève créé',
        'update_student' => 'Élève modifié',
        'delete_student' => 'Élève supprimé',
        'view_student' => 'Élève consulté',
        'create_class' => 'Classe créée',
        'update_class' => 'Classe modifiée',
        'delete_class' => 'Classe supprimée',
        'view_class' => 'Classe consultée',
        'create_subject' => 'Matière créée',
        'update_subject' => 'Matière modifiée',
        'delete_subject' => 'Matière supprimée',
        'view_subject' => 'Matière consultée',
        'create_payment' => 'Paiement créé',
        'update_payment' => 'Paiement modifié',
        'delete_payment' => 'Paiement supprimé',
        'view_payment' => 'Paiement consulté',
        'create_fee' => 'Frais créé',
        'update_fee' => 'Frais modifié',
        'delete_fee' => 'Frais supprimé',
        'view_fee' => 'Frais consulté',
        'create_absence' => 'Absence créée',
        'justify_absence' => 'Absence justifiée',
        'update_absence' => 'Absence modifiée'
    ];
    return $labels[$action] ?? 'Action inconnue';
}
}
?>
