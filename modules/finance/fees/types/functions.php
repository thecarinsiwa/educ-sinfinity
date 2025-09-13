<?php
/**
 * Fonctions utilitaires pour le module Types de Frais
 * Application de gestion scolaire - République Démocratique du Congo
 */

/**
 * Affiche du texte en décodant les entités HTML et en protégeant contre XSS
 * 
 * @param string $text Le texte à afficher
 * @param bool $decode_entities Si true, décode les entités HTML
 * @param bool $nl2br Si true, convertit les retours à la ligne en <br>
 * @return string Le texte sécurisé pour l'affichage
 */
function displayText($text, $decode_entities = true, $nl2br = false) {
    if (empty($text)) {
        return '';
    }
    
    // Décoder les entités HTML si demandé
    if ($decode_entities) {
        $text = html_entity_decode($text, ENT_QUOTES, 'UTF-8');
    }
    
    // Protéger contre XSS
    $text = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    
    // Convertir les retours à la ligne si demandé
    if ($nl2br) {
        $text = nl2br($text);
    }
    
    return $text;
}

/**
 * Affiche une description tronquée
 * 
 * @param string $description La description complète
 * @param int $max_length Longueur maximale (défaut: 100)
 * @return string La description tronquée et sécurisée
 */
function displayDescription($description, $max_length = 100) {
    if (empty($description)) {
        return '<span class="text-muted">Aucune description</span>';
    }
    
    $decoded = html_entity_decode($description, ENT_QUOTES, 'UTF-8');
    $truncated = strlen($decoded) > $max_length ? substr($decoded, 0, $max_length) . '...' : $decoded;
    
    return '<span class="text-muted">' . htmlspecialchars($truncated, ENT_QUOTES, 'UTF-8') . '</span>';
}

/**
 * Affiche une description complète avec retours à la ligne
 * 
 * @param string $description La description complète
 * @return string La description sécurisée avec <br>
 */
function displayFullDescription($description) {
    if (empty($description)) {
        return '<span class="text-muted">Aucune description</span>';
    }
    
    $decoded = html_entity_decode($description, ENT_QUOTES, 'UTF-8');
    return nl2br(htmlspecialchars($decoded, ENT_QUOTES, 'UTF-8'));
}

/**
 * Prépare le texte pour l'affichage dans un formulaire
 * 
 * @param string $text Le texte à préparer
 * @return string Le texte prêt pour l'affichage dans un formulaire
 */
function prepareFormText($text) {
    if (empty($text)) {
        return '';
    }
    
    $decoded = html_entity_decode($text, ENT_QUOTES, 'UTF-8');
    return htmlspecialchars($decoded, ENT_QUOTES, 'UTF-8');
}

/**
 * Valide et nettoie le texte saisi par l'utilisateur
 * 
 * @param string $text Le texte à nettoyer
 * @return string Le texte nettoyé
 */
function cleanInputText($text) {
    if (empty($text)) {
        return '';
    }
    
    // Supprimer les espaces en début et fin
    $text = trim($text);
    
    // Normaliser les espaces multiples
    $text = preg_replace('/\s+/', ' ', $text);
    
    return $text;
}
?>
