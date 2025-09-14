<?php
/**
 * Fonctions utilitaires pour les URLs des modules
 * Système de gestion scolaire - République Démocratique du Congo
 * 
 * Gère la génération des URLs correctes pour les modules et pages
 */

/**
 * Obtenir l'URL par défaut d'un module
 */
function getModuleDefaultUrl($module_key) {
    $default_pages = [
        'admin' => 'modules/admin/users/index.php',
        'users' => 'modules/users/index.php',
        'personnel' => 'modules/personnel/index.php',
        'finance' => 'modules/finance/index.php',
        'reports' => 'modules/reports/index.php',
        'communication' => 'modules/communication/index.php',
        'complementary' => 'modules/complementary/index.php',
        'academic' => 'modules/academic/index.php',
        'evaluations' => 'modules/evaluations/index.php',
        'discipline' => 'modules/discipline/index.php',
        'library' => 'modules/library/index.php',
        'students' => 'modules/students/index.php',
        'cartes_eleves' => 'modules/cartes_eleves/index.php',
        'recouvrement' => 'modules/recouvrement/index.php',
        'admissions' => 'modules/admissions/index.php'
    ];
    
    $url = $default_pages[$module_key] ?? 'dashboard.php';
    return getRelativePath($url);
}

/**
 * Obtenir l'URL d'une page spécifique dans un module
 */
function getModuleUrl($module_key, $page_key) {
    // Si c'est une page index, utiliser l'URL par défaut du module
    if ($page_key === 'index') {
        return getModuleDefaultUrl($module_key);
    }
    
    // Construire l'URL selon la structure des modules
    $module_paths = [
        'admin' => 'modules/admin',
        'users' => 'modules/users',
        'personnel' => 'modules/personnel',
        'finance' => 'modules/finance',
        'reports' => 'modules/reports',
        'communication' => 'modules/communication',
        'complementary' => 'modules/complementary',
        'academic' => 'modules/academic',
        'evaluations' => 'modules/evaluations',
        'discipline' => 'modules/discipline',
        'library' => 'modules/library',
        'students' => 'modules/students',
        'cartes_eleves' => 'modules/cartes_eleves',
        'recouvrement' => 'modules/recouvrement',
        'admissions' => 'modules/admissions'
    ];
    
    $base_path = $module_paths[$module_key] ?? 'modules/' . $module_key;
    
    // Si la page contient un slash, c'est un sous-module
    if (strpos($page_key, '/') !== false) {
        $url = $base_path . '/' . $page_key . '.php';
    } else {
        $url = $base_path . '/' . $page_key . '.php';
    }
    
    return getRelativePath($url);
}

/**
 * Vérifier si une URL de module existe
 */
function moduleUrlExists($url) {
    $full_path = dirname(__DIR__) . '/' . $url;
    return file_exists($full_path);
}

/**
 * Obtenir l'URL corrigée pour un élément de menu
 */
function getCorrectedModuleUrl($url, $module_key = '', $submenu_key = '') {
    // Si l'URL est déjà complète, la retourner
    if (strpos($url, 'http') === 0 || strpos($url, '/') === 0) {
        return $url;
    }
    
    // Si c'est un module avec une page spécifique
    if (!empty($module_key) && !empty($submenu_key)) {
        return getModuleUrl($module_key, $submenu_key);
    }
    
    // Si c'est juste un module
    if (!empty($module_key)) {
        return getModuleDefaultUrl($module_key);
    }
    
    // Retourner l'URL telle quelle
    return $url;
}

/**
 * Obtenir le chemin relatif correct selon le répertoire courant
 */
function getRelativePath($target_url) {
    $current_dir = dirname($_SERVER['PHP_SELF']);
    
    // Si on est dans le dossier dashboards
    if (strpos($current_dir, '/dashboards') !== false) {
        return '../' . $target_url;
    }
    
    // Si on est dans le dossier admin
    if (strpos($current_dir, '/admin') !== false) {
        return '../' . $target_url;
    }
    
    // Si on est dans un sous-dossier de modules
    if (strpos($current_dir, '/modules') !== false) {
        $depth = substr_count($current_dir, '/') - 1; // -1 pour enlever le '/'
        return str_repeat('../', $depth) . $target_url;
    }
    
    // Par défaut, retourner l'URL telle quelle
    return $target_url;
}

/**
 * Obtenir l'URL de redirection après connexion
 */
function getPostLoginRedirectUrl() {
    require_once dirname(__DIR__) . '/includes/dashboard-router.php';
    return getDashboardUrl();
}

/**
 * Obtenir l'URL du dashboard actuel
 */
function getCurrentDashboardUrl() {
    $nature = $_SESSION['user_nature'] ?? 'staff';
    require_once dirname(__DIR__) . '/includes/dashboard-router.php';
    return getDashboardUrl($nature);
}
?>
