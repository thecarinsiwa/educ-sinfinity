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
        redirectTo('../auth/login.php');
    }
}

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
            "SELECT u.*, p.nom, p.prenom, p.fonction 
             FROM users u 
             LEFT JOIN personnel p ON u.id = p.user_id 
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
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['user_role'] = $user['role'];
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
    
    // L'administrateur a toutes les permissions
    if ($user['role'] === 'admin') {
        return true;
    }
    
    // Vérifier les permissions basées sur le rôle
    $permissions = ROLES[$user['role']]['permissions'] ?? [];
    
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
?>
