<?php
/**
 * Routeur de dashboards personnalisés
 * Système de gestion scolaire - République Démocratique du Congo
 * 
 * Gère la redirection vers le bon dashboard selon la nature de l'utilisateur
 */

require_once dirname(__DIR__) . '/config/dashboard-config.php';

/**
 * Obtenir l'URL du dashboard approprié selon la nature de l'utilisateur
 */
function getDashboardUrl($nature = null) {
    if ($nature === null) {
        $nature = $_SESSION['user_nature'] ?? 'staff';
    }
    
    // Vérifier si la nature est valide
    if (!isValidUserNature($nature)) {
        $nature = 'staff'; // Fallback par défaut
    }
    
    // Déterminer le chemin correct selon le contexte
    $current_dir = dirname($_SERVER['SCRIPT_NAME']);
    
    // Si on est dans le dossier auth, remonter d'un niveau
    if (strpos($current_dir, '/auth') !== false) {
        return "../dashboards/{$nature}.php";
    }
    // Si on est à la racine ou dans admin, utiliser le chemin direct
    else {
        return "dashboards/{$nature}.php";
    }
}

/**
 * Rediriger vers le dashboard approprié
 */
function redirectToDashboard($nature = null) {
    $url = getDashboardUrl($nature);
    redirectTo($url);
}

/**
 * Obtenir le nom du dashboard selon la nature
 */
function getDashboardName($nature = null) {
    if ($nature === null) {
        $nature = $_SESSION['user_nature'] ?? 'staff';
    }
    
    $config = getDashboardConfig($nature);
    return $config['name'] ?? 'Tableau de Bord';
}

/**
 * Obtenir le titre du dashboard selon la nature
 */
function getDashboardTitle($nature = null) {
    if ($nature === null) {
        $nature = $_SESSION['user_nature'] ?? 'staff';
    }
    
    $config = getDashboardConfig($nature);
    return $config['title'] ?? 'Tableau de Bord';
}

/**
 * Obtenir la description du dashboard selon la nature
 */
function getDashboardDescription($nature = null) {
    if ($nature === null) {
        $nature = $_SESSION['user_nature'] ?? 'staff';
    }
    
    $config = getDashboardConfig($nature);
    return $config['description'] ?? 'Tableau de bord personnalisé';
}

/**
 * Obtenir l'icône du dashboard selon la nature
 */
function getDashboardIcon($nature = null) {
    if ($nature === null) {
        $nature = $_SESSION['user_nature'] ?? 'staff';
    }
    
    $config = getDashboardConfig($nature);
    return $config['icon'] ?? 'fas fa-tachometer-alt';
}

/**
 * Obtenir la couleur du dashboard selon la nature
 */
function getDashboardColor($nature = null) {
    if ($nature === null) {
        $nature = $_SESSION['user_nature'] ?? 'staff';
    }
    
    $config = getDashboardConfig($nature);
    return $config['color'] ?? 'primary';
}

/**
 * Vérifier si l'utilisateur a accès à un module dans son dashboard
 */
function hasDashboardModuleAccess($module_key, $nature = null) {
    if ($nature === null) {
        $nature = $_SESSION['user_nature'] ?? 'staff';
    }
    
    $modules = getDashboardModules($nature);
    return isset($modules[$module_key]);
}

/**
 * Obtenir la liste des modules accessibles pour le dashboard actuel
 */
function getCurrentDashboardModules() {
    $nature = $_SESSION['user_nature'] ?? 'staff';
    return getDashboardModules($nature);
}

/**
 * Obtenir l'URL de redirection par défaut après connexion
 */
function getDefaultRedirectUrl() {
    return getDashboardUrl();
}

/**
 * Obtenir les statistiques du dashboard selon la nature
 */
function getDashboardStats($nature = null) {
    if ($nature === null) {
        $nature = $_SESSION['user_nature'] ?? 'staff';
    }
    
    global $database;
    
    if (!isset($database)) {
        return [];
    }
    
    try {
        $stats = [];
        
        switch ($nature) {
            case 'admin':
                // Statistiques pour admin
                $stats['total_users'] = $database->query("SELECT COUNT(*) as count FROM users")->fetch()['count'];
                $stats['total_personnel'] = $database->query("SELECT COUNT(*) as count FROM personnel")->fetch()['count'];
                $stats['total_students'] = $database->query("SELECT COUNT(*) as count FROM eleves")->fetch()['count'];
                break;
                
            case 'teacher':
                // Statistiques pour enseignant
                $teacher_id = $_SESSION['user_id'] ?? 0;
                $stats['my_classes'] = $database->query(
                    "SELECT COUNT(DISTINCT classe_id) as count FROM emploi_temps WHERE enseignant_id = ?",
                    [$teacher_id]
                )->fetch()['count'];
                break;
                
            case 'student':
                // Statistiques pour élève
                $student_id = $_SESSION['user_id'] ?? 0;
                $stats['my_notes'] = $database->query(
                    "SELECT COUNT(*) as count FROM notes WHERE eleve_id = ?",
                    [$student_id]
                )->fetch()['count'];
                break;
                
            case 'parent':
                // Statistiques pour parent
                $stats['child_notes'] = 0; // À implémenter selon la logique métier
                break;
                
            case 'staff':
                // Statistiques pour personnel administratif
                $stats['pending_payments'] = $database->query(
                    "SELECT COUNT(*) as count FROM paiements WHERE status = 'en_attente'"
                )->fetch()['count'];
                break;
        }
        
        return $stats;
        
    } catch (Exception $e) {
        error_log("Erreur getDashboardStats: " . $e->getMessage());
        return [];
    }
}
?>
