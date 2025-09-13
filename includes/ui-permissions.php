<?php
/**
 * Fonctions pour gérer les permissions d'interface utilisateur
 * Système de gestion scolaire - République Démocratique du Congo
 * 
 * Ce fichier contient les fonctions pour désactiver automatiquement
 * les boutons et liens selon les permissions de l'utilisateur.
 */

require_once __DIR__ . '/permissions-pages.php';

/**
 * Générer un lien avec vérification des permissions
 * 
 * @param string $url URL du lien
 * @param string $classes Classes CSS du lien
 * @param string $text Texte du lien
 * @param string $icon Icône du lien
 * @param string $module Module
 * @param string $page Page
 * @param string $action Action requise
 * @param array $attributes Attributs HTML supplémentaires
 * @return string HTML du lien
 */
function generatePermissionLink($url, $classes, $text, $icon, $module, $page = '', $action = 'read', $attributes = []) {
    $has_permission = hasPagePermission($module, $page, $action);
    
    $default_attributes = [
        'class' => $classes,
        'title' => $text
    ];
    
    $attributes = array_merge($default_attributes, $attributes);
    
    if (!$has_permission) {
        // Pour les éléments non autorisés, modifier la classe pour les désactiver
        $original_class = $attributes['class'] ?? 'btn btn-outline-primary';
        $attributes['class'] = str_replace(['btn-outline-primary', 'btn-outline-success', 'btn-outline-info', 'btn-outline-warning', 'btn-outline-danger'], 'btn-outline-secondary disabled', $original_class);
        $attributes['title'] = 'Accès non autorisé - ' . $text;
        $attributes['onclick'] = 'return false;';
    }
    
    $attr_string = '';
    foreach ($attributes as $key => $value) {
        $attr_string .= ' ' . $key . '="' . htmlspecialchars($value) . '"';
    }
    
    $icon_html = '';
    if (!empty($icon)) {
        $icon_html = '<i class="' . htmlspecialchars($icon) . '"></i> ';
    }
    
    if ($has_permission) {
        return '<a href="' . htmlspecialchars($url) . '"' . $attr_string . '>' . $icon_html . htmlspecialchars($text) . '</a>';
    } else {
        return '<span' . $attr_string . '>' . $icon_html . htmlspecialchars($text) . '</span>';
    }
}

/**
 * Générer un bouton avec vérification des permissions
 * 
 * @param string $text Texte du bouton
 * @param string $module Module
 * @param string $page Page
 * @param string $action Action requise
 * @param string $type Type de bouton (button, submit)
 * @param array $attributes Attributs HTML supplémentaires
 * @return string HTML du bouton
 */
function generatePermissionButton($text, $module, $page = '', $action = 'read', $type = 'button', $attributes = []) {
    $has_permission = hasPagePermission($module, $page, $action);
    
    $default_attributes = [
        'class' => 'btn btn-primary',
        'title' => $text,
        'type' => $type
    ];
    
    if (!$has_permission) {
        $default_attributes['class'] = 'btn btn-secondary disabled';
        $default_attributes['title'] = 'Accès non autorisé - ' . $text;
        $default_attributes['disabled'] = 'disabled';
        $default_attributes['onclick'] = 'return false;';
    }
    
    $attributes = array_merge($default_attributes, $attributes);
    
    $attr_string = '';
    foreach ($attributes as $key => $value) {
        $attr_string .= ' ' . $key . '="' . htmlspecialchars($value) . '"';
    }
    
    return '<button' . $attr_string . '>' . htmlspecialchars($text) . '</button>';
}

/**
 * Vérifier si un utilisateur peut voir un élément d'interface
 * 
 * @param string $module Module
 * @param string $page Page
 * @param string $action Action requise
 * @return bool True si l'élément doit être visible
 */
function canShowElement($module, $page = '', $action = 'read') {
    return hasPagePermission($module, $page, $action);
}

/**
 * Générer des classes CSS conditionnelles selon les permissions
 * 
 * @param string $module Module
 * @param string $page Page
 * @param string $action Action requise
 * @param string $base_classes Classes CSS de base
 * @param string $disabled_classes Classes CSS quand désactivé
 * @return string Classes CSS
 */
function getPermissionClasses($module, $page = '', $action = 'read', $base_classes = '', $disabled_classes = 'disabled text-muted') {
    $has_permission = hasPagePermission($module, $page, $action);
    
    if ($has_permission) {
        return $base_classes;
    } else {
        return $base_classes . ' ' . $disabled_classes;
    }
}

/**
 * Générer un tableau de boutons d'actions avec permissions
 * 
 * @param array $actions Actions possibles
 * @param array $params Paramètres pour les URLs
 * @return string HTML du tableau de boutons
 */
function generateActionButtons($actions, $params = []) {
    $html = '<div class="btn-group" role="group">';
    
    foreach ($actions as $action) {
        $module = $action['module'] ?? '';
        $page = $action['page'] ?? '';
        $action_type = $action['action'] ?? 'read';
        $url = $action['url'] ?? '#';
        $text = $action['text'] ?? 'Action';
        $icon = $action['icon'] ?? '';
        $class = $action['class'] ?? 'btn btn-outline-primary';
        
        $has_permission = hasPagePermission($module, $page, $action_type);
        
        if ($has_permission) {
            $html .= '<a href="' . htmlspecialchars($url) . '" class="' . htmlspecialchars($class) . '" title="' . htmlspecialchars($text) . '">';
            if ($icon) {
                $html .= '<i class="' . htmlspecialchars($icon) . '"></i> ';
            }
            $html .= htmlspecialchars($text) . '</a>';
        } else {
            $html .= '<span class="' . htmlspecialchars($class) . ' disabled" title="Accès non autorisé - ' . htmlspecialchars($text) . '">';
            if ($icon) {
                $html .= '<i class="' . htmlspecialchars($icon) . '"></i> ';
            }
            $html .= htmlspecialchars($text) . '</span>';
        }
    }
    
    $html .= '</div>';
    return $html;
}

/**
 * Générer un menu déroulant avec permissions
 * 
 * @param string $title Titre du menu
 * @param array $items Éléments du menu
 * @param array $attributes Attributs HTML
 * @return string HTML du menu
 */
function generatePermissionDropdown($title, $items, $attributes = []) {
    $default_attributes = [
        'class' => 'dropdown-toggle',
        'data-bs-toggle' => 'dropdown'
    ];
    
    $attributes = array_merge($default_attributes, $attributes);
    
    $attr_string = '';
    foreach ($attributes as $key => $value) {
        $attr_string .= ' ' . $key . '="' . htmlspecialchars($value) . '"';
    }
    
    $html = '<div class="dropdown">';
    $html .= '<button class="btn btn-outline-primary dropdown-toggle"' . $attr_string . '>';
    $html .= htmlspecialchars($title);
    $html .= '</button>';
    $html .= '<ul class="dropdown-menu">';
    
    foreach ($items as $item) {
        $module = $item['module'] ?? '';
        $page = $item['page'] ?? '';
        $action_type = $item['action'] ?? 'read';
        $url = $item['url'] ?? '#';
        $text = $item['text'] ?? 'Item';
        $icon = $item['icon'] ?? '';
        
        $has_permission = hasPagePermission($module, $page, $action_type);
        
        if ($has_permission) {
            $html .= '<li><a class="dropdown-item" href="' . htmlspecialchars($url) . '">';
            if ($icon) {
                $html .= '<i class="' . htmlspecialchars($icon) . ' me-2"></i>';
            }
            $html .= htmlspecialchars($text) . '</a></li>';
        } else {
            $html .= '<li><span class="dropdown-item disabled text-muted">';
            if ($icon) {
                $html .= '<i class="' . htmlspecialchars($icon) . ' me-2"></i>';
            }
            $html .= htmlspecialchars($text) . ' (Non autorisé)</span></li>';
        }
    }
    
    $html .= '</ul>';
    $html .= '</div>';
    
    return $html;
}

/**
 * Générer un tableau avec colonnes conditionnelles selon les permissions
 * 
 * @param array $columns Colonnes du tableau
 * @param array $data Données du tableau
 * @return string HTML du tableau
 */
function generatePermissionTable($columns, $data) {
    $html = '<table class="table table-striped table-hover">';
    
    // En-têtes
    $html .= '<thead><tr>';
    foreach ($columns as $column) {
        $module = $column['module'] ?? '';
        $page = $column['page'] ?? '';
        $action_type = $column['action'] ?? 'read';
        
        if (canShowElement($module, $page, $action_type)) {
            $html .= '<th>' . htmlspecialchars($column['title']) . '</th>';
        }
    }
    $html .= '</tr></thead>';
    
    // Corps du tableau
    $html .= '<tbody>';
    foreach ($data as $row) {
        $html .= '<tr>';
        foreach ($columns as $column_key => $column) {
            $module = $column['module'] ?? '';
            $page = $column['page'] ?? '';
            $action_type = $column['action'] ?? 'read';
            
            if (canShowElement($module, $page, $action_type)) {
                $html .= '<td>' . ($row[$column_key] ?? '') . '</td>';
            }
        }
        $html .= '</tr>';
    }
    $html .= '</tbody>';
    
    $html .= '</table>';
    return $html;
}

/**
 * Générer un formulaire avec champs conditionnels selon les permissions
 * 
 * @param array $fields Champs du formulaire
 * @param array $attributes Attributs du formulaire
 * @return string HTML du formulaire
 */
function generatePermissionForm($fields, $attributes = []) {
    $default_attributes = [
        'method' => 'POST',
        'class' => 'needs-validation'
    ];
    
    $attributes = array_merge($default_attributes, $attributes);
    
    $attr_string = '';
    foreach ($attributes as $key => $value) {
        $attr_string .= ' ' . $key . '="' . htmlspecialchars($value) . '"';
    }
    
    $html = '<form' . $attr_string . '>';
    
    foreach ($fields as $field) {
        $module = $field['module'] ?? '';
        $page = $field['page'] ?? '';
        $action_type = $field['action'] ?? 'read';
        
        if (canShowElement($module, $page, $action_type)) {
            $html .= '<div class="mb-3">';
            $html .= '<label for="' . htmlspecialchars($field['name']) . '" class="form-label">';
            $html .= htmlspecialchars($field['label']);
            if ($field['required'] ?? false) {
                $html .= ' <span class="text-danger">*</span>';
            }
            $html .= '</label>';
            
            if ($field['type'] === 'textarea') {
                $html .= '<textarea class="form-control" id="' . htmlspecialchars($field['name']) . '" name="' . htmlspecialchars($field['name']) . '"';
                if ($field['required'] ?? false) {
                    $html .= ' required';
                }
                $html .= '>' . htmlspecialchars($field['value'] ?? '') . '</textarea>';
            } else {
                $html .= '<input type="' . htmlspecialchars($field['type'] ?? 'text') . '" class="form-control" id="' . htmlspecialchars($field['name']) . '" name="' . htmlspecialchars($field['name']) . '" value="' . htmlspecialchars($field['value'] ?? '') . '"';
                if ($field['required'] ?? false) {
                    $html .= ' required';
                }
                $html .= '>';
            }
            
            $html .= '</div>';
        }
    }
    
    $html .= '</form>';
    return $html;
}

/**
 * Générer un message d'information selon les permissions
 * 
 * @param string $module Module
 * @param string $page Page
 * @param string $action Action requise
 * @param string $message Message à afficher
 * @return string HTML du message
 */
function generatePermissionMessage($module, $page, $action, $message) {
    $has_permission = hasPagePermission($module, $page, $action);
    
    if ($has_permission) {
        return '<div class="alert alert-info">' . htmlspecialchars($message) . '</div>';
    } else {
        return '<div class="alert alert-warning">Vous n\'avez pas les permissions nécessaires pour cette action.</div>';
    }
}

/**
 * Obtenir les statistiques des permissions de l'utilisateur pour l'interface
 * Utilise la fonction existante de permissions-pages.php
 * 
 * @return array Statistiques des permissions formatées pour l'UI
 */
function getUIPermissionStats() {
    $user_id = $_SESSION['user_id'] ?? 0;
    $stats = getUserPermissionStats($user_id); // Utilise la fonction existante avec l'ID utilisateur
    
    // Reformater pour l'interface utilisateur
    if ($stats) {
        return [
            'total_modules' => $stats['modules'] ? count($stats['modules']) : 0,
            'total_pages' => $stats['total_pages'] ?? 0,
            'total_actions' => $stats['total_actions'] ?? 0,
            'modules' => $stats['modules'] ?? []
        ];
    }
    
    return [
        'total_modules' => 0,
        'total_pages' => 0,
        'total_actions' => 0,
        'modules' => []
    ];
}
?>
