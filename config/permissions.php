<?php
/**
 * Configuration des permissions
 * Application de gestion scolaire - République Démocratique du Congo
 */

// Définition des permissions disponibles
$PERMISSIONS = [
    // Module principal
    'dashboard' => 'Accès au tableau de bord',
    'profile' => 'Gestion du profil utilisateur',
    
    // Module des élèves
    'students' => 'Gestion des élèves',
    'students_view' => 'Visualisation des élèves',
    'students_add' => 'Ajout d\'élèves',
    'students_edit' => 'Modification des élèves',
    'students_delete' => 'Suppression des élèves',
    'students_export' => 'Export des données élèves',
    
    // Module des admissions
    'admissions' => 'Gestion des admissions',
    'admissions_view' => 'Visualisation des demandes d\'admission',
    'admissions_add' => 'Création de demandes d\'admission',
    'admissions_edit' => 'Modification des demandes d\'admission',
    'admissions_evaluate' => 'Évaluation des demandes d\'admission',
    'admissions_delete' => 'Suppression des demandes d\'admission',
    'admissions_export' => 'Export des demandes d\'admission',
    
    // Module académique
    'academic' => 'Gestion académique',
    'classes' => 'Gestion des classes',
    'subjects' => 'Gestion des matières',
    'evaluations' => 'Gestion des évaluations',
    'notes' => 'Gestion des notes',
    'schedule' => 'Gestion des emplois du temps',
    
    // Module du personnel
    'personnel' => 'Gestion du personnel',
    'personnel_view' => 'Visualisation du personnel',
    'personnel_add' => 'Ajout de personnel',
    'personnel_edit' => 'Modification du personnel',
    'personnel_delete' => 'Suppression du personnel',
    
    // Module financier
    'finance' => 'Gestion financière',
    'fees' => 'Gestion des frais',
    'payments' => 'Gestion des paiements',
    'expenses' => 'Gestion des dépenses',
    
    // Module de la bibliothèque
    'library' => 'Gestion de la bibliothèque',
    'books' => 'Gestion des livres',
    'loans' => 'Gestion des emprunts',
    
    // Module de communication
    'communication' => 'Communication',
    'messages' => 'Gestion des messages',
    'announcements' => 'Gestion des annonces',
    
    // Module des rapports
    'reports' => 'Génération de rapports',
    'reports_academic' => 'Rapports académiques',
    'reports_financial' => 'Rapports financiers',
    'reports_administrative' => 'Rapports administratifs',
    
    // Module d'administration
    'admin' => 'Administration système',
    'users' => 'Gestion des utilisateurs',
    'settings' => 'Paramètres système',
    'backup' => 'Sauvegarde et restauration',
    
    // Module de discipline
    'discipline' => 'Gestion de la discipline',
    'incidents' => 'Gestion des incidents',
    'sanctions' => 'Gestion des sanctions',
    'rewards' => 'Gestion des récompenses',
    
    // Module des évaluations
    'evaluations' => 'Gestion des évaluations',
    'bulletins' => 'Génération des bulletins',
    'statistics' => 'Statistiques académiques',
    
    // Module de recouvrement
    'recouvrement' => 'Gestion du recouvrement',
    'campaigns' => 'Campagnes de recouvrement',
    'payments_tracking' => 'Suivi des paiements',
    
    // Module des utilisateurs
    'users' => 'Gestion des utilisateurs',
    'users_view' => 'Visualisation des utilisateurs',
    'users_add' => 'Ajout d\'utilisateurs',
    'users_edit' => 'Modification des utilisateurs',
    'users_delete' => 'Suppression d\'utilisateurs',
    'users_logs' => 'Consultation des logs utilisateurs'
];

// Rôles et leurs permissions par défaut
$ROLES_PERMISSIONS = [
    'super_admin' => array_keys($PERMISSIONS), // Toutes les permissions
    
    'admin' => [
        'dashboard', 'profile',
        'students', 'students_view', 'students_add', 'students_edit', 'students_export',
        'admissions', 'admissions_view', 'admissions_add', 'admissions_edit', 'admissions_evaluate', 'admissions_export',
        'academic', 'classes', 'subjects', 'evaluations', 'notes', 'schedule',
        'personnel', 'personnel_view', 'personnel_add', 'personnel_edit',
        'finance', 'fees', 'payments', 'expenses',
        'library', 'books', 'loans',
        'communication', 'messages', 'announcements',
        'reports', 'reports_academic', 'reports_financial', 'reports_administrative',
        'discipline', 'incidents', 'sanctions', 'rewards',
        'evaluations', 'bulletins', 'statistics',
        'recouvrement', 'campaigns', 'payments_tracking',
        'users', 'users_view', 'users_add', 'users_edit'
    ],
    
    'directeur' => [
        'dashboard', 'profile',
        'students', 'students_view', 'students_edit', 'students_export',
        'admissions', 'admissions_view', 'admissions_edit', 'admissions_evaluate', 'admissions_export',
        'academic', 'classes', 'subjects', 'evaluations', 'notes', 'schedule',
        'personnel', 'personnel_view', 'personnel_edit',
        'finance', 'fees', 'payments', 'expenses',
        'library', 'books', 'loans',
        'communication', 'messages', 'announcements',
        'reports', 'reports_academic', 'reports_financial', 'reports_administrative',
        'discipline', 'incidents', 'sanctions', 'rewards',
        'evaluations', 'bulletins', 'statistics',
        'recouvrement', 'campaigns', 'payments_tracking'
    ],
    
    'secretaire' => [
        'dashboard', 'profile',
        'students', 'students_view', 'students_add', 'students_edit', 'students_export',
        'admissions', 'admissions_view', 'admissions_add', 'admissions_edit', 'admissions_export',
        'academic', 'classes', 'subjects',
        'finance', 'fees', 'payments',
        'library', 'books', 'loans',
        'communication', 'messages', 'announcements',
        'reports', 'reports_administrative'
    ],
    
    'enseignant' => [
        'dashboard', 'profile',
        'students', 'students_view',
        'academic', 'classes', 'subjects', 'evaluations', 'notes',
        'evaluations', 'bulletins',
        'communication', 'messages', 'announcements'
    ],
    
    'comptable' => [
        'dashboard', 'profile',
        'students', 'students_view',
        'finance', 'fees', 'payments', 'expenses',
        'reports', 'reports_financial',
        'recouvrement', 'campaigns', 'payments_tracking'
    ],
    
    'bibliothecaire' => [
        'dashboard', 'profile',
        'library', 'books', 'loans',
        'reports'
    ],
    
    'utilisateur' => [
        'dashboard', 'profile',
        'students', 'students_view',
        'communication', 'messages', 'announcements'
    ]
];

/**
 * Vérifier si un utilisateur a une permission spécifique
 * @param string $permission Nom de la permission
 * @return bool True si l'utilisateur a la permission
 */
function checkPermission($permission) {
    global $ROLES_PERMISSIONS, $database;
    
    // Vérifier si l'utilisateur est connecté
    if (!isset($_SESSION['user_id'])) {
        return false;
    }
    
    // Essayer d'abord le nouveau système basé sur la table roles
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
                
                // Vérifier si l'utilisateur a la permission dans le nouveau format
                // Format: module:page:action
                if (is_array($permissions)) {
                    foreach ($permissions as $module => $module_permissions) {
                        if (is_array($module_permissions)) {
                            foreach ($module_permissions as $page => $actions) {
                                if (is_array($actions)) {
                                    // Vérifier si la permission correspond au module
                                    if ($permission === $module || 
                                        $permission === $module . '_view' || 
                                        $permission === $module . '_edit') {
                                        return true;
                                    }
                                    
                                    // Vérifier les permissions spécifiques
                                    foreach ($actions as $action) {
                                        if ($permission === $module . ':' . $page . ':' . $action) {
                                            return true;
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }
        } catch (Exception $e) {
            error_log("Erreur dans checkPermission (nouveau système): " . $e->getMessage());
        }
    }
    
    // Fallback vers l'ancien système si le nouveau ne fonctionne pas
    if (isset($_SESSION['user_role'])) {
        $user_role = $_SESSION['user_role'];
        
        // Vérifier si le rôle existe et a la permission
        if (isset($ROLES_PERMISSIONS[$user_role])) {
            return in_array($permission, $ROLES_PERMISSIONS[$user_role]);
        }
    }
    
    return false;
}

/**
 * Vérifier si un utilisateur a au moins une des permissions spécifiées
 * @param array $permissions Tableau de permissions
 * @return bool True si l'utilisateur a au moins une permission
 */
function checkAnyPermission($permissions) {
    foreach ($permissions as $permission) {
        if (checkPermission($permission)) {
            return true;
        }
    }
    return false;
}

/**
 * Vérifier si un utilisateur a toutes les permissions spécifiées
 * @param array $permissions Tableau de permissions
 * @return bool True si l'utilisateur a toutes les permissions
 */
function checkAllPermissions($permissions) {
    foreach ($permissions as $permission) {
        if (!checkPermission($permission)) {
            return false;
        }
    }
    return true;
}

/**
 * Obtenir toutes les permissions d'un utilisateur
 * @return array Tableau des permissions de l'utilisateur
 */
function getUserPermissions() {
    global $ROLES_PERMISSIONS;
    
    if (!isset($_SESSION['user_role'])) {
        return [];
    }
    
    $user_role = $_SESSION['user_role'];
    
    if (isset($ROLES_PERMISSIONS[$user_role])) {
        return $ROLES_PERMISSIONS[$user_role];
    }
    
    return [];
}

/**
 * Obtenir le nom lisible d'une permission
 * @param string $permission Nom de la permission
 * @return string Nom lisible de la permission
 */
function getPermissionName($permission) {
    global $PERMISSIONS;
    
    return $PERMISSIONS[$permission] ?? $permission;
}

/**
 * Obtenir toutes les permissions disponibles
 * @return array Tableau de toutes les permissions
 */
function getAllPermissions() {
    global $PERMISSIONS;
    return $PERMISSIONS;
}

/**
 * Obtenir tous les rôles disponibles
 * @return array Tableau de tous les rôles
 */
function getAllRoles() {
    global $ROLES_PERMISSIONS;
    return array_keys($ROLES_PERMISSIONS);
}
?>
