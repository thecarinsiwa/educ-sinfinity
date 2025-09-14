<?php
/**
 * Gestionnaire centralisé des paramètres système
 * Application de gestion scolaire - République Démocratique du Congo
 */

class SettingsManager {
    private $database;
    private $cache = [];
    private $cache_duration = 3600; // 1 heure
    
    public function __construct($database) {
        $this->database = $database;
    }
    
    /**
     * Récupère un paramètre système
     * @param string $key Clé du paramètre
     * @param mixed $default_value Valeur par défaut si le paramètre n'existe pas
     * @return mixed Valeur du paramètre
     */
    public function getSetting($key, $default_value = null) {
        // Vérifier le cache en mémoire
        if (isset($this->cache[$key])) {
            return $this->cache[$key];
        }
        
        // Vérifier le cache en base de données
        $cached_value = $this->getCachedValue($key);
        if ($cached_value !== null) {
            $this->cache[$key] = $cached_value;
            return $cached_value;
        }
        
        try {
            $result = $this->database->query(
                "SELECT valeur, type FROM system_settings WHERE cle = ?",
                [$key]
            )->fetch();
            
            if ($result) {
                $value = $this->formatValue($result['valeur'], $result['type']);
                $this->cache[$key] = $value;
                $this->setCachedValue($key, $value);
                return $value;
            }
            
            // Retourner la valeur par défaut si définie
            if ($default_value !== null) {
                return $default_value;
            }
            
            // Récupérer la valeur par défaut depuis la base
            $default_result = $this->database->query(
                "SELECT default_value, type FROM system_settings WHERE cle = ?",
                [$key]
            )->fetch();
            
            if ($default_result && $default_result['default_value'] !== null) {
                $value = $this->formatValue($default_result['default_value'], $default_result['type']);
                $this->cache[$key] = $value;
                return $value;
            }
            
            return null;
            
        } catch (Exception $e) {
            error_log("Erreur lors de la récupération du paramètre '$key': " . $e->getMessage());
            return $default_value;
        }
    }
    
    /**
     * Met à jour un paramètre système
     * @param string $key Clé du paramètre
     * @param mixed $value Nouvelle valeur
     * @param string $type Type du paramètre (optionnel, sera détecté automatiquement)
     * @return bool Succès de l'opération
     */
    public function updateSetting($key, $value, $type = null) {
        try {
            // Détecter le type si non fourni
            if ($type === null) {
                $type = $this->detectValueType($value);
            }
            
            // Formater la valeur selon le type
            $formatted_value = $this->formatValueForStorage($value, $type);
            
            // Mettre à jour en base
            $this->database->execute(
                "INSERT INTO system_settings (cle, valeur, type, updated_at) VALUES (?, ?, ?, NOW()) 
                 ON DUPLICATE KEY UPDATE valeur = VALUES(valeur), type = VALUES(type), updated_at = NOW()",
                [$key, $formatted_value, $type]
            );
            
            // Mettre à jour le cache
            $this->cache[$key] = $value;
            $this->setCachedValue($key, $value);
            
            return true;
            
        } catch (Exception $e) {
            error_log("Erreur lors de la mise à jour du paramètre '$key': " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Met à jour plusieurs paramètres en une seule transaction
     * @param array $settings Tableau associatif clé => valeur
     * @return bool Succès de l'opération
     */
    public function updateSettings($settings) {
        try {
            $this->database->beginTransaction();
            
            foreach ($settings as $key => $value) {
                $this->updateSetting($key, $value);
            }
            
            $this->database->commit();
            return true;
            
        } catch (Exception $e) {
            $this->database->rollback();
            error_log("Erreur lors de la mise à jour des paramètres: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Récupère tous les paramètres d'une catégorie
     * @param string $category Catégorie des paramètres
     * @return array Tableau associatif des paramètres
     */
    public function getSettingsByCategory($category) {
        try {
            $results = $this->database->query(
                "SELECT cle, valeur, type, description, help_text, is_required, default_value 
                 FROM system_settings 
                 WHERE categorie = ? 
                 ORDER BY sort_order ASC, cle ASC",
                [$category]
            )->fetchAll();
            
            $settings = [];
            foreach ($results as $result) {
                $settings[$result['cle']] = [
                    'value' => $this->formatValue($result['valeur'], $result['type']),
                    'type' => $result['type'],
                    'description' => $result['description'],
                    'help_text' => $result['help_text'],
                    'is_required' => (bool)$result['is_required'],
                    'default_value' => $result['default_value']
                ];
            }
            
            return $settings;
            
        } catch (Exception $e) {
            error_log("Erreur lors de la récupération des paramètres de catégorie '$category': " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Récupère tous les paramètres groupés
     * @return array Tableau des paramètres groupés par groupe
     */
    public function getAllSettingsGrouped() {
        try {
            $results = $this->database->query(
                "SELECT cle, valeur, type, description, help_text, is_required, default_value, group_name, sort_order
                 FROM system_settings 
                 ORDER BY group_name ASC, sort_order ASC, cle ASC"
            )->fetchAll();
            
            $grouped_settings = [];
            foreach ($results as $result) {
                $group = $result['group_name'] ?: 'general';
                if (!isset($grouped_settings[$group])) {
                    $grouped_settings[$group] = [];
                }
                
                $grouped_settings[$group][$result['cle']] = [
                    'value' => $this->formatValue($result['valeur'], $result['type']),
                    'type' => $result['type'],
                    'description' => $result['description'],
                    'help_text' => $result['help_text'],
                    'is_required' => (bool)$result['is_required'],
                    'default_value' => $result['default_value'],
                    'sort_order' => $result['sort_order']
                ];
            }
            
            return $grouped_settings;
            
        } catch (Exception $e) {
            error_log("Erreur lors de la récupération de tous les paramètres: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Supprime un paramètre
     * @param string $key Clé du paramètre
     * @return bool Succès de l'opération
     */
    public function deleteSetting($key) {
        try {
            $this->database->execute("DELETE FROM system_settings WHERE cle = ?", [$key]);
            
            // Supprimer du cache
            unset($this->cache[$key]);
            $this->deleteCachedValue($key);
            
            return true;
            
        } catch (Exception $e) {
            error_log("Erreur lors de la suppression du paramètre '$key': " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Vide le cache des paramètres
     */
    public function clearCache() {
        $this->cache = [];
        try {
            $this->database->execute("DELETE FROM settings_cache WHERE expires_at < NOW()");
        } catch (Exception $e) {
            error_log("Erreur lors du nettoyage du cache: " . $e->getMessage());
        }
    }
    
    /**
     * Formate une valeur selon son type
     * @param mixed $value Valeur à formater
     * @param string $type Type de la valeur
     * @return mixed Valeur formatée
     */
    private function formatValue($value, $type) {
        if ($value === null) {
            return null;
        }
        
        switch ($type) {
            case 'boolean':
                return (bool)$value;
            case 'number':
                return is_numeric($value) ? (float)$value : 0;
            case 'json':
                return json_decode($value, true);
            case 'file':
                return $value; // Chemin du fichier
            default:
                return $value;
        }
    }
    
    /**
     * Formate une valeur pour le stockage
     * @param mixed $value Valeur à formater
     * @param string $type Type de la valeur
     * @return string Valeur formatée pour le stockage
     */
    private function formatValueForStorage($value, $type) {
        switch ($type) {
            case 'boolean':
                return $value ? '1' : '0';
            case 'json':
                return is_array($value) ? json_encode($value) : $value;
            default:
                return (string)$value;
        }
    }
    
    /**
     * Détecte le type d'une valeur
     * @param mixed $value Valeur à analyser
     * @return string Type détecté
     */
    private function detectValueType($value) {
        if (is_bool($value)) {
            return 'boolean';
        } elseif (is_numeric($value)) {
            return 'number';
        } elseif (filter_var($value, FILTER_VALIDATE_EMAIL)) {
            return 'email';
        } elseif (filter_var($value, FILTER_VALIDATE_URL)) {
            return 'url';
        } elseif (is_array($value)) {
            return 'json';
        } else {
            return 'text';
        }
    }
    
    /**
     * Récupère une valeur du cache
     * @param string $key Clé du cache
     * @return mixed Valeur en cache ou null
     */
    private function getCachedValue($key) {
        try {
            $result = $this->database->query(
                "SELECT cache_value FROM settings_cache WHERE cache_key = ? AND expires_at > NOW()",
                [$key]
            )->fetch();
            
            return $result ? unserialize($result['cache_value']) : null;
            
        } catch (Exception $e) {
            return null;
        }
    }
    
    /**
     * Met une valeur en cache
     * @param string $key Clé du cache
     * @param mixed $value Valeur à mettre en cache
     */
    private function setCachedValue($key, $value) {
        try {
            $expires_at = date('Y-m-d H:i:s', time() + $this->cache_duration);
            $serialized_value = serialize($value);
            
            $this->database->execute(
                "INSERT INTO settings_cache (cache_key, cache_value, expires_at) VALUES (?, ?, ?) 
                 ON DUPLICATE KEY UPDATE cache_value = VALUES(cache_value), expires_at = VALUES(expires_at)",
                [$key, $serialized_value, $expires_at]
            );
            
        } catch (Exception $e) {
            // Ignorer les erreurs de cache
        }
    }
    
    /**
     * Supprime une valeur du cache
     * @param string $key Clé du cache
     */
    private function deleteCachedValue($key) {
        try {
            $this->database->execute("DELETE FROM settings_cache WHERE cache_key = ?", [$key]);
        } catch (Exception $e) {
            // Ignorer les erreurs de cache
        }
    }
}

// Fonctions globales pour faciliter l'utilisation
function getSetting($key, $default_value = null) {
    global $settings_manager;
    return $settings_manager->getSetting($key, $default_value);
}

function updateSetting($key, $value) {
    global $settings_manager;
    return $settings_manager->updateSetting($key, $value);
}

function getSettingsByCategory($category) {
    global $settings_manager;
    return $settings_manager->getSettingsByCategory($category);
}

function getAllSettingsGrouped() {
    global $settings_manager;
    return $settings_manager->getAllSettingsGrouped();
}
