<?php
/**
 * Administration - Paramètres généraux du système
 * Application de gestion scolaire - République Démocratique du Congo
 */

require_once '../config/config.php';
require_once '../config/database.php';
require_once '../includes/functions.php';

// Vérifier l'authentification et les permissions admin
requireLogin();
if (!checkPermission('admin')) {
    showMessage('error', 'Accès refusé. Seuls les administrateurs peuvent accéder à cette page.');
    redirectTo('../index.php');
}

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $action = $_POST['action'] ?? '';
        
        if ($action === 'update_settings') {
            $settings = $_POST['settings'] ?? [];
            
            if (empty($settings)) {
                throw new Exception('Aucun paramètre à mettre à jour.');
            }
            

            
            foreach ($settings as $cle => $valeur) {
                // Validation selon le type de paramètre
                switch ($cle) {
                    case 'school_name':
                    case 'school_address':
                    case 'school_city':
                        if (empty($valeur)) {
                            throw new Exception("Le paramètre '$cle' ne peut pas être vide.");
                        }
                        break;
                        
                    case 'school_phone':
                    case 'school_fax':
                        if (!empty($valeur) && !preg_match('/^[\+]?[0-9\s\-\(\)]+$/', $valeur)) {
                            throw new Exception("Format de téléphone invalide pour '$cle'.");
                        }
                        break;
                        
                    case 'school_email':
                    case 'admin_email':
                        if (!empty($valeur) && !filter_var($valeur, FILTER_VALIDATE_EMAIL)) {
                            throw new Exception("Format d'email invalide pour '$cle'.");
                        }
                        break;
                        
                    case 'school_website':
                        if (!empty($valeur) && !filter_var($valeur, FILTER_VALIDATE_URL)) {
                            throw new Exception("Format d'URL invalide pour '$cle'.");
                        }
                        break;
                        
                    case 'max_students_per_class':
                    case 'school_year_start_month':
                    case 'backup_retention_days':
                        $valeur = intval($valeur);
                        if ($valeur <= 0) {
                            throw new Exception("La valeur pour '$cle' doit être un nombre positif.");
                        }
                        break;
                        
                    case 'enable_sms':
                    case 'enable_email':
                    case 'enable_notifications':
                    case 'maintenance_mode':
                        $valeur = $valeur ? '1' : '0';
                        break;
                }
                
                // Insérer ou mettre à jour le paramètre
                $database->execute(
                    "INSERT INTO system_settings (cle, valeur, updated_at) VALUES (?, ?, NOW()) 
                     ON DUPLICATE KEY UPDATE valeur = VALUES(valeur), updated_at = NOW()",
                    [$cle, $valeur]
                );
            }
            
            showMessage('success', 'Paramètres mis à jour avec succès.');
        }
        
    } catch (Exception $e) {
        showMessage('error', 'Erreur : ' . $e->getMessage());
    }
}

// Créer la table des paramètres si elle n'existe pas
try {
    $database->execute("
        CREATE TABLE IF NOT EXISTS `system_settings` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `cle` varchar(100) NOT NULL,
          `valeur` text DEFAULT NULL,
          `description` text DEFAULT NULL,
          `type` enum('text','number','boolean','email','url','textarea','select') DEFAULT 'text',
          `options` text DEFAULT NULL COMMENT 'JSON pour les select',
          `categorie` varchar(50) DEFAULT 'general',
          `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
          `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
          PRIMARY KEY (`id`),
          UNIQUE KEY `cle` (`cle`),
          KEY `idx_categorie` (`categorie`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
} catch (Exception $e) {
    // Table existe déjà ou erreur de création
}

// Récupérer les paramètres actuels
try {
    $settings_raw = $database->query("SELECT * FROM system_settings")->fetchAll();
    $settings = [];
    foreach ($settings_raw as $setting) {
        $settings[$setting['cle']] = $setting['valeur'];
    }
} catch (Exception $e) {
    $settings = [];
}

// Valeurs par défaut
$default_settings = [
    'school_name' => 'École Sinfinity',
    'school_address' => 'Avenue de la Paix, Kinshasa',
    'school_city' => 'Kinshasa',
    'school_country' => 'République Démocratique du Congo',
    'school_phone' => '+243 123 456 789',
    'school_fax' => '',
    'school_email' => 'contact@ecole-sinfinity.cd',
    'school_website' => 'https://www.ecole-sinfinity.cd',
    'admin_email' => 'admin@ecole-sinfinity.cd',
    'max_students_per_class' => '30',
    'school_year_start_month' => '9',
    'enable_sms' => '1',
    'enable_email' => '1',
    'enable_notifications' => '1',
    'maintenance_mode' => '0',
    'backup_retention_days' => '30',
    'timezone' => 'Africa/Kinshasa',
    'language' => 'fr',
    'currency' => 'FC'
];

// Fusionner avec les valeurs par défaut
foreach ($default_settings as $key => $default_value) {
    if (!isset($settings[$key])) {
        $settings[$key] = $default_value;
    }
}

$page_title = "Paramètres du Système";
include '../includes/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">
        <i class="fas fa-cogs me-2"></i>
        Paramètres du Système
    </h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <div class="btn-group me-2">
            <a href="../index.php" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-1"></i>
                Retour au tableau de bord
            </a>
        </div>
    </div>
</div>

<form method="POST">
    <input type="hidden" name="action" value="update_settings">
    
    <div class="row">
        <!-- Informations de l'établissement -->
        <div class="col-md-6 mb-4">
            <div class="card shadow-sm">
                <div class="card-header bg-light">
                    <h5 class="mb-0">
                        <i class="fas fa-school me-2"></i>
                        Informations de l'établissement
                    </h5>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label for="school_name" class="form-label">
                            Nom de l'établissement <span class="text-danger">*</span>
                        </label>
                        <input type="text" class="form-control" id="school_name" name="settings[school_name]" 
                               value="<?php echo htmlspecialchars($settings['school_name']); ?>" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="school_address" class="form-label">
                            Adresse <span class="text-danger">*</span>
                        </label>
                        <input type="text" class="form-control" id="school_address" name="settings[school_address]" 
                               value="<?php echo htmlspecialchars($settings['school_address']); ?>" required>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="school_city" class="form-label">
                                Ville <span class="text-danger">*</span>
                            </label>
                            <input type="text" class="form-control" id="school_city" name="settings[school_city]" 
                                   value="<?php echo htmlspecialchars($settings['school_city']); ?>" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="school_country" class="form-label">Pays</label>
                            <input type="text" class="form-control" id="school_country" name="settings[school_country]" 
                                   value="<?php echo htmlspecialchars($settings['school_country']); ?>">
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="school_phone" class="form-label">Téléphone</label>
                            <input type="tel" class="form-control" id="school_phone" name="settings[school_phone]" 
                                   value="<?php echo htmlspecialchars($settings['school_phone']); ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="school_fax" class="form-label">Fax</label>
                            <input type="tel" class="form-control" id="school_fax" name="settings[school_fax]" 
                                   value="<?php echo htmlspecialchars($settings['school_fax']); ?>">
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="school_email" class="form-label">Email</label>
                            <input type="email" class="form-control" id="school_email" name="settings[school_email]" 
                                   value="<?php echo htmlspecialchars($settings['school_email']); ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="school_website" class="form-label">Site web</label>
                            <input type="url" class="form-control" id="school_website" name="settings[school_website]" 
                                   value="<?php echo htmlspecialchars($settings['school_website']); ?>">
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Paramètres académiques -->
        <div class="col-md-6 mb-4">
            <div class="card shadow-sm">
                <div class="card-header bg-light">
                    <h5 class="mb-0">
                        <i class="fas fa-graduation-cap me-2"></i>
                        Paramètres académiques
                    </h5>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label for="max_students_per_class" class="form-label">
                            Nombre maximum d'élèves par classe
                        </label>
                        <input type="number" class="form-control" id="max_students_per_class" 
                               name="settings[max_students_per_class]" 
                               value="<?php echo htmlspecialchars($settings['max_students_per_class']); ?>" 
                               min="1" max="100">
                        <div class="form-text">Recommandé : 25-35 élèves par classe</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="school_year_start_month" class="form-label">
                            Mois de début d'année scolaire
                        </label>
                        <select class="form-select" id="school_year_start_month" name="settings[school_year_start_month]">
                            <?php
                            $months = [
                                1 => 'Janvier', 2 => 'Février', 3 => 'Mars', 4 => 'Avril',
                                5 => 'Mai', 6 => 'Juin', 7 => 'Juillet', 8 => 'Août',
                                9 => 'Septembre', 10 => 'Octobre', 11 => 'Novembre', 12 => 'Décembre'
                            ];
                            foreach ($months as $num => $name):
                            ?>
                                <option value="<?php echo $num; ?>" 
                                        <?php echo $settings['school_year_start_month'] == $num ? 'selected' : ''; ?>>
                                    <?php echo $name; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="timezone" class="form-label">Fuseau horaire</label>
                        <select class="form-select" id="timezone" name="settings[timezone]">
                            <option value="Africa/Kinshasa" <?php echo $settings['timezone'] === 'Africa/Kinshasa' ? 'selected' : ''; ?>>
                                Africa/Kinshasa (UTC+1)
                            </option>
                            <option value="Africa/Lubumbashi" <?php echo $settings['timezone'] === 'Africa/Lubumbashi' ? 'selected' : ''; ?>>
                                Africa/Lubumbashi (UTC+2)
                            </option>
                        </select>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="language" class="form-label">Langue</label>
                            <select class="form-select" id="language" name="settings[language]">
                                <option value="fr" <?php echo $settings['language'] === 'fr' ? 'selected' : ''; ?>>
                                    Français
                                </option>
                                <option value="en" <?php echo $settings['language'] === 'en' ? 'selected' : ''; ?>>
                                    English
                                </option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="currency" class="form-label">Devise</label>
                            <select class="form-select" id="currency" name="settings[currency]">
                                <option value="FC" <?php echo $settings['currency'] === 'FC' ? 'selected' : ''; ?>>
                                    Franc Congolais (FC)
                                </option>
                                <option value="USD" <?php echo $settings['currency'] === 'USD' ? 'selected' : ''; ?>>
                                    Dollar US ($)
                                </option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Paramètres de communication -->
        <div class="col-md-6 mb-4">
            <div class="card shadow-sm">
                <div class="card-header bg-light">
                    <h5 class="mb-0">
                        <i class="fas fa-comments me-2"></i>
                        Communication
                    </h5>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label for="admin_email" class="form-label">Email administrateur</label>
                        <input type="email" class="form-control" id="admin_email" name="settings[admin_email]" 
                               value="<?php echo htmlspecialchars($settings['admin_email']); ?>">
                        <div class="form-text">Email pour recevoir les notifications système</div>
                    </div>
                    
                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="enable_email" 
                                   name="settings[enable_email]" value="1"
                                   <?php echo $settings['enable_email'] === '1' ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="enable_email">
                                Activer les emails
                            </label>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="enable_sms" 
                                   name="settings[enable_sms]" value="1"
                                   <?php echo $settings['enable_sms'] === '1' ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="enable_sms">
                                Activer les SMS
                            </label>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="enable_notifications" 
                                   name="settings[enable_notifications]" value="1"
                                   <?php echo $settings['enable_notifications'] === '1' ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="enable_notifications">
                                Activer les notifications
                            </label>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Paramètres système -->
        <div class="col-md-6 mb-4">
            <div class="card shadow-sm">
                <div class="card-header bg-light">
                    <h5 class="mb-0">
                        <i class="fas fa-server me-2"></i>
                        Système
                    </h5>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label for="backup_retention_days" class="form-label">
                            Rétention des sauvegardes (jours)
                        </label>
                        <input type="number" class="form-control" id="backup_retention_days" 
                               name="settings[backup_retention_days]" 
                               value="<?php echo htmlspecialchars($settings['backup_retention_days']); ?>" 
                               min="1" max="365">
                        <div class="form-text">Nombre de jours de conservation des sauvegardes</div>
                    </div>
                    
                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="maintenance_mode" 
                                   name="settings[maintenance_mode]" value="1"
                                   <?php echo $settings['maintenance_mode'] === '1' ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="maintenance_mode">
                                Mode maintenance
                            </label>
                        </div>
                        <div class="form-text text-warning">
                            ⚠️ Active le mode maintenance (seuls les admins peuvent accéder)
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Boutons d'action -->
    <div class="card shadow-sm">
        <div class="card-body">
            <div class="d-flex justify-content-between">
                <a href="../index.php" class="btn btn-secondary">
                    <i class="fas fa-times me-1"></i>
                    Annuler
                </a>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save me-1"></i>
                    Enregistrer les paramètres
                </button>
            </div>
        </div>
    </div>
</form>

<!-- Informations système -->
<div class="card shadow-sm mt-4">
    <div class="card-header bg-light">
        <h5 class="mb-0">
            <i class="fas fa-info-circle me-2"></i>
            Informations système
        </h5>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-6">
                <h6>Environnement</h6>
                <ul class="list-unstyled small">
                    <li><strong>Version PHP :</strong> <?php echo PHP_VERSION; ?></li>
                    <li><strong>Serveur web :</strong> <?php echo $_SERVER['SERVER_SOFTWARE'] ?? 'Inconnu'; ?></li>
                    <li><strong>Base de données :</strong> MySQL</li>
                    <li><strong>Fuseau horaire :</strong> <?php echo date_default_timezone_get(); ?></li>
                </ul>
            </div>
            <div class="col-md-6">
                <h6>Application</h6>
                <ul class="list-unstyled small">
                    <li><strong>Version :</strong> 1.0.0</li>
                    <li><strong>Dernière mise à jour :</strong> <?php echo date('d/m/Y H:i'); ?></li>
                    <li><strong>Mode debug :</strong> <?php echo defined('DEBUG') && DEBUG ? 'Activé' : 'Désactivé'; ?></li>
                    <li><strong>Utilisateurs connectés :</strong> <?php echo $_SESSION['user_id'] ? '1+' : '0'; ?></li>
                </ul>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
