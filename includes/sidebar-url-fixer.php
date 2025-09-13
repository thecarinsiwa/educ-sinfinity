<?php
/**
 * Correcteur d'URLs pour le sidebar
 * Système de gestion scolaire - République Démocratique du Congo
 * 
 * Ce fichier contient les fonctions pour corriger les URLs
 * générées par le sidebar pour qu'elles pointent vers les bons fichiers.
 */

/**
 * Corriger une URL de module pour pointer vers le bon fichier
 * 
 * @param string $url URL originale
 * @return string URL corrigée
 */
function fixSidebarUrl($url) {
    // Si l'URL se termine par un slash, ajouter index.php
    if (substr($url, -1) === '/') {
        $url .= 'index.php';
    }
    
    // Si l'URL ne contient pas d'extension, ajouter .php
    if (!strpos($url, '.php')) {
        $url .= '.php';
    }
    
    return $url;
}

/**
 * Obtenir l'URL correcte pour un module et une page
 * 
 * @param string $module Module
 * @param string $page Page (optionnel)
 * @return string URL correcte
 */
function getModuleUrl($module, $page = 'index') {
    $base_url = APP_URL . '/modules/' . $module . '/';
    
    // Si c'est juste le module, pointer vers index.php
    if ($page === 'index') {
        return $base_url . 'index.php';
    }
    
    // Sinon, pointer vers la page spécifique
    return $base_url . $page . '.php';
}

/**
 * Corriger toutes les URLs d'un sous-menu
 * 
 * @param array $submenu Sous-menu à corriger
 * @return array Sous-menu avec URLs corrigées
 */
function fixSubmenuUrls($submenu) {
    $fixed_submenu = [];
    
    foreach ($submenu as $key => $item) {
        $fixed_item = $item;
        
        // Corriger l'URL si elle existe
        if (isset($item['url'])) {
            $fixed_item['url'] = fixSidebarUrl($item['url']);
        }
        
        $fixed_submenu[$key] = $fixed_item;
    }
    
    return $fixed_submenu;
}

/**
 * Obtenir l'URL par défaut pour un module
 * 
 * @param string $module_key Clé du module
 * @return string URL par défaut
 */
function getDefaultModuleUrl($module_key) {
    // URLs par défaut pour chaque module
    $default_urls = [
        'students' => 'modules/students/index.php',
        'academic' => 'modules/academic/index.php',
        'evaluations' => 'modules/evaluations/index.php',
        'finance' => 'modules/finance/index.php',
        'recouvrement' => 'modules/recouvrement/index.php',
        'library' => 'modules/library/index.php',
        'discipline' => 'modules/discipline/index.php',
        'communication' => 'modules/communication/index.php',
        'cartes_eleves' => 'modules/cartes_eleves/index.php',
        'reports' => 'modules/reports/index.php',
        'personnel' => 'modules/personnel/index.php',
        'users' => 'admin/users.php',
        'admin' => 'admin/settings.php'
    ];
    
    return $default_urls[$module_key] ?? 'modules/' . $module_key . '/index.php';
}

/**
 * Vérifier si une URL existe
 * 
 * @param string $url URL à vérifier
 * @return bool True si l'URL existe
 */
function urlExists($url) {
    // Enlever APP_URL du début si présent
    $relative_url = str_replace(APP_URL . '/', '', $url);
    $file_path = __DIR__ . '/../' . $relative_url;
    
    return file_exists($file_path);
}

/**
 * Obtenir l'URL de fallback si l'URL principale n'existe pas
 * 
 * @param string $module Module
 * @return string URL de fallback
 */
function getFallbackUrl($module) {
    // Essayer différentes pages communes
    $fallback_pages = ['index.php', 'list.php', 'view.php', 'add.php'];
    
    foreach ($fallback_pages as $page) {
        $url = 'modules/' . $module . '/' . $page;
        if (urlExists($url)) {
            return $url;
        }
    }
    
    // Si rien n'est trouvé, retourner l'URL par défaut
    return getDefaultModuleUrl($module);
}

/**
 * Corriger une URL avec vérification d'existence
 * 
 * @param string $url URL à corriger
 * @param string $module Module (pour fallback)
 * @return string URL corrigée et vérifiée
 */
function fixAndVerifyUrl($url, $module = null) {
    // Corriger l'URL
    $fixed_url = fixSidebarUrl($url);
    
    // Vérifier si l'URL existe
    if (urlExists($fixed_url)) {
        return $fixed_url;
    }
    
    // Si l'URL n'existe pas et qu'on a un module, utiliser le fallback
    if ($module) {
        return getFallbackUrl($module);
    }
    
    // Sinon, retourner l'URL corrigée même si elle n'existe pas
    return $fixed_url;
}

/**
 * Corriger toutes les URLs des modules
 * 
 * @param array $modules Modules à corriger
 * @return array Modules avec URLs corrigées
 */
function fixAllModuleUrls($modules) {
    $fixed_modules = [];
    
    foreach ($modules as $module_key => $module) {
        $fixed_module = $module;
        
        // Corriger les sous-menus s'ils existent
        if (isset($module['submenu'])) {
            $fixed_module['submenu'] = fixSubmenuUrls($module['submenu']);
        }
        
        $fixed_modules[$module_key] = $fixed_module;
    }
    
    return $fixed_modules;
}

/**
 * Obtenir l'URL correcte pour un élément de menu
 * 
 * @param string $url URL originale
 * @param string $module Module parent
 * @param string $submenu_key Clé du sous-menu
 * @return string URL correcte
 */
function getCorrectMenuUrl($url, $module, $submenu_key = null) {
    // Si l'URL est vide ou invalide, générer une URL par défaut
    if (empty($url) || $url === '#') {
        if ($submenu_key) {
            return getModuleUrl($module, $submenu_key);
        } else {
            return getDefaultModuleUrl($module);
        }
    }
    
    // Corriger et vérifier l'URL
    return fixAndVerifyUrl($url, $module);
}
?>
