<?php
/**
 * Administration - Paramètres généraux du système (Version améliorée)
 * Application de gestion scolaire - République Démocratique du Congo
 */

require_once '../config/config.php';
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/settings-manager.php';

// Vérifier l'authentification et les permissions admin
requireLogin();
if (!checkPermission('admin')) {
    showMessage('error', 'Accès refusé. Seuls les administrateurs peuvent accéder à cette page.');
    redirectTo('../index.php');
}

// Initialiser le gestionnaire de paramètres
$settings_manager = new SettingsManager($database);

// Traitement des requêtes AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_action'])) {
    header('Content-Type: application/json');
    
    try {
        $action = $_POST['ajax_action'];
        
        switch ($action) {
            case 'update_settings':
                $settings_json = $_POST['settings'] ?? '{}';
                $settings = json_decode($settings_json, true);
                
                if (json_last_error() !== JSON_ERROR_NONE) {
                    throw new Exception('Erreur de décodage JSON: ' . json_last_error_msg());
                }
                
                $success = $settings_manager->updateSettings($settings);
                
                if ($success) {
                    echo json_encode(['success' => true, 'message' => 'Paramètres mis à jour avec succès.']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Erreur lors de la mise à jour des paramètres.']);
                }
                break;
                
            case 'upload_file':
                $file_type = $_POST['file_type'] ?? '';
                $setting_key = $_POST['setting_key'] ?? '';
                
                if (empty($file_type) || empty($setting_key)) {
                    throw new Exception('Paramètres manquants pour l\'upload.');
                }
                
                $upload_result = handleFileUpload($file_type, $setting_key);
                echo json_encode($upload_result);
                break;
                
            case 'get_settings':
                $category = $_POST['category'] ?? 'all';
                
                if ($category === 'all') {
                    $settings = $settings_manager->getAllSettingsGrouped();
                } else {
                    $settings = $settings_manager->getSettingsByCategory($category);
                }
                
                echo json_encode(['success' => true, 'settings' => $settings]);
                break;
                
            case 'clear_cache':
                $settings_manager->clearCache();
                echo json_encode(['success' => true, 'message' => 'Cache vidé avec succès.']);
                break;
                
            default:
                throw new Exception('Action non reconnue.');
        }
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

// Fonction pour gérer l'upload de fichiers
function handleFileUpload($file_type, $setting_key) {
    global $settings_manager;
    
    if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('Erreur lors de l\'upload du fichier.');
    }
    
    $file = $_FILES['file'];
    $allowed_types = [];
    $max_size = 0;
    $upload_dir = '';
    
    switch ($file_type) {
        case 'logo':
            $allowed_types = ['jpg', 'jpeg', 'png', 'gif', 'svg'];
            $max_size = 2048; // 2MB
            $upload_dir = '../uploads/logos/';
            break;
        case 'favicon':
            $allowed_types = ['ico', 'png', 'gif'];
            $max_size = 512; // 512KB
            $upload_dir = '../uploads/favicons/';
            break;
        default:
            throw new Exception('Type de fichier non supporté.');
    }
    
    // Créer le dossier s'il n'existe pas
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }
    
    // Vérifier la taille
    if ($file['size'] > $max_size * 1024) {
        throw new Exception('Fichier trop volumineux. Taille maximale: ' . $max_size . 'KB');
    }
    
    // Vérifier l'extension
    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($extension, $allowed_types)) {
        throw new Exception('Type de fichier non autorisé. Types acceptés: ' . implode(', ', $allowed_types));
    }
    
    // Générer un nom unique
    $filename = $setting_key . '_' . time() . '.' . $extension;
    $filepath = $upload_dir . $filename;
    
    // Déplacer le fichier
    if (!move_uploaded_file($file['tmp_name'], $filepath)) {
        throw new Exception('Erreur lors du déplacement du fichier.');
    }
    
    // Mettre à jour le paramètre
    $relative_path = 'uploads/' . basename($upload_dir) . '/' . $filename;
    $settings_manager->updateSetting($setting_key, $relative_path);
    
    return [
        'success' => true,
        'message' => 'Fichier uploadé avec succès.',
        'file_path' => $relative_path,
        'file_url' => '../' . $relative_path
    ];
}

// Récupérer tous les paramètres groupés
$all_settings = $settings_manager->getAllSettingsGrouped();

// Récupérer les établissements
$etablissements = $database->query("SELECT * FROM etablissements WHERE is_active = 1 ORDER BY is_principal DESC, nom ASC")->fetchAll();

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
            <button type="button" class="btn btn-outline-secondary" onclick="clearCache()">
                <i class="fas fa-trash me-1"></i>
                Vider le cache
            </button>
            <a href="../index.php" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-1"></i>
                Retour au tableau de bord
            </a>
        </div>
    </div>
</div>

<!-- Messages de notification -->
<div id="notification-area"></div>

<!-- Onglets de navigation -->
<ul class="nav nav-tabs" id="settingsTabs" role="tablist">
    <li class="nav-item" role="presentation">
        <button class="nav-link active" id="general-tab" data-bs-toggle="tab" data-bs-target="#general" type="button" role="tab">
            <i class="fas fa-cog me-1"></i> Général
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="school-tab" data-bs-toggle="tab" data-bs-target="#school" type="button" role="tab">
            <i class="fas fa-school me-1"></i> Établissement
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="appearance-tab" data-bs-toggle="tab" data-bs-target="#appearance" type="button" role="tab">
            <i class="fas fa-palette me-1"></i> Apparence
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="communication-tab" data-bs-toggle="tab" data-bs-target="#communication" type="button" role="tab">
            <i class="fas fa-comments me-1"></i> Communication
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="security-tab" data-bs-toggle="tab" data-bs-target="#security" type="button" role="tab">
            <i class="fas fa-shield-alt me-1"></i> Sécurité
        </button>
    </li>
</ul>

<!-- Contenu des onglets -->
<div class="tab-content" id="settingsTabContent">
    <!-- Onglet Général -->
    <div class="tab-pane fade show active" id="general" role="tabpanel">
        <div class="row mt-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-cog me-2"></i>
                            Paramètres généraux
                        </h5>
                    </div>
                    <div class="card-body">
                        <form id="general-form">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="app_name" class="form-label">
                                        Nom de l'application <span class="text-danger">*</span>
                                    </label>
                                    <input type="text" class="form-control" id="app_name" name="app_name" 
                                           value="<?php echo htmlspecialchars(getSetting('app_name', 'École Sinfinity')); ?>" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="current_academic_year" class="form-label">
                                        Année scolaire en cours <span class="text-danger">*</span>
                                    </label>
                                    <select class="form-select" id="current_academic_year" name="current_academic_year" required>
                                        <option value="">Sélectionner une année</option>
                                        <?php
                                        $years = $database->query("SELECT * FROM annees_scolaires ORDER BY date_debut DESC")->fetchAll();
                                        foreach ($years as $year):
                                        ?>
                                            <option value="<?php echo $year['id']; ?>" 
                                                    <?php echo getSetting('current_academic_year') == $year['id'] ? 'selected' : ''; ?>>
                                                <?php echo $year['annee']; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label for="timezone" class="form-label">Fuseau horaire</label>
                                    <select class="form-select" id="timezone" name="timezone">
                                        <option value="Africa/Kinshasa" <?php echo getSetting('timezone') === 'Africa/Kinshasa' ? 'selected' : ''; ?>>
                                            Africa/Kinshasa (UTC+1)
                                        </option>
                                        <option value="Africa/Lubumbashi" <?php echo getSetting('timezone') === 'Africa/Lubumbashi' ? 'selected' : ''; ?>>
                                            Africa/Lubumbashi (UTC+2)
                                        </option>
                                    </select>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label for="language" class="form-label">Langue</label>
                                    <select class="form-select" id="language" name="language">
                                        <option value="fr" <?php echo getSetting('language') === 'fr' ? 'selected' : ''; ?>>
                                            Français
                                        </option>
                                        <option value="en" <?php echo getSetting('language') === 'en' ? 'selected' : ''; ?>>
                                            English
                                        </option>
                                    </select>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label for="currency" class="form-label">Devise</label>
                                    <select class="form-select" id="currency" name="currency">
                                        <option value="FC" <?php echo getSetting('currency') === 'FC' ? 'selected' : ''; ?>>
                                            Franc Congolais (FC)
                                        </option>
                                        <option value="USD" <?php echo getSetting('currency') === 'USD' ? 'selected' : ''; ?>>
                                            Dollar US ($)
                                        </option>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="d-flex justify-content-end">
                                <button type="button" class="btn btn-primary" onclick="saveSettings('general')">
                                    <i class="fas fa-save me-1"></i>
                                    Enregistrer
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Onglet Établissement -->
    <div class="tab-pane fade" id="school" role="tabpanel">
        <div class="row mt-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-school me-2"></i>
                            Informations de l'établissement
                        </h5>
                    </div>
                    <div class="card-body">
                        <form id="school-form">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="school_name" class="form-label">
                                        Nom de l'établissement <span class="text-danger">*</span>
                                    </label>
                                    <input type="text" class="form-control" id="school_name" name="school_name" 
                                           value="<?php echo htmlspecialchars(getSetting('school_name', 'École Sinfinity')); ?>" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="school_slogan" class="form-label">Slogan</label>
                                    <input type="text" class="form-control" id="school_slogan" name="school_slogan" 
                                           value="<?php echo htmlspecialchars(getSetting('school_slogan', '')); ?>">
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="school_address" class="form-label">
                                    Adresse complète <span class="text-danger">*</span>
                                </label>
                                <textarea class="form-control" id="school_address" name="school_address" rows="3" required><?php echo htmlspecialchars(getSetting('school_address', '')); ?></textarea>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label for="school_phone" class="form-label">Téléphone</label>
                                    <input type="tel" class="form-control" id="school_phone" name="school_phone" 
                                           value="<?php echo htmlspecialchars(getSetting('school_phone', '')); ?>">
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label for="school_email" class="form-label">Email</label>
                                    <input type="email" class="form-control" id="school_email" name="school_email" 
                                           value="<?php echo htmlspecialchars(getSetting('school_email', '')); ?>">
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label for="school_website" class="form-label">Site web</label>
                                    <input type="url" class="form-control" id="school_website" name="school_website" 
                                           value="<?php echo htmlspecialchars(getSetting('school_website', '')); ?>">
                                </div>
                            </div>
                            
                            <div class="d-flex justify-content-end">
                                <button type="button" class="btn btn-primary" onclick="saveSettings('school')">
                                    <i class="fas fa-save me-1"></i>
                                    Enregistrer
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Onglet Apparence -->
    <div class="tab-pane fade" id="appearance" role="tabpanel">
        <div class="row mt-4">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-image me-2"></i>
                            Logo et Favicon
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label for="logo_upload" class="form-label">Logo de l'établissement</label>
                            <div class="d-flex align-items-center">
                                <div class="me-3">
                                    <img id="logo_preview" src="<?php echo getSetting('logo') ? '../' . getSetting('logo') : '../assets/images/default-avatar.svg'; ?>" 
                                         alt="Logo" style="width: 80px; height: 80px; object-fit: contain; border: 1px solid #ddd; border-radius: 4px;">
                                </div>
                                <div class="flex-grow-1">
                                    <input type="file" class="form-control" id="logo_upload" accept="image/*" onchange="uploadFile('logo', 'logo')">
                                    <div class="form-text">Formats acceptés: JPG, PNG, GIF, SVG. Taille max: 2MB</div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="favicon_upload" class="form-label">Favicon</label>
                            <div class="d-flex align-items-center">
                                <div class="me-3">
                                    <img id="favicon_preview" src="<?php echo getSetting('favicon') ? '../' . getSetting('favicon') : '../assets/images/default-avatar.svg'; ?>" 
                                         alt="Favicon" style="width: 32px; height: 32px; object-fit: contain; border: 1px solid #ddd; border-radius: 4px;">
                                </div>
                                <div class="flex-grow-1">
                                    <input type="file" class="form-control" id="favicon_upload" accept=".ico,.png,.gif" onchange="uploadFile('favicon', 'favicon')">
                                    <div class="form-text">Formats acceptés: ICO, PNG, GIF. Taille max: 512KB</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-palette me-2"></i>
                            Couleurs et Thème
                        </h5>
                    </div>
                    <div class="card-body">
                        <form id="appearance-form">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="primary_color" class="form-label">Couleur principale</label>
                                    <input type="color" class="form-control form-control-color" id="primary_color" name="primary_color" 
                                           value="<?php echo getSetting('primary_color', '#007bff'); ?>">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="secondary_color" class="form-label">Couleur secondaire</label>
                                    <input type="color" class="form-control form-control-color" id="secondary_color" name="secondary_color" 
                                           value="<?php echo getSetting('secondary_color', '#6c757d'); ?>">
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="theme" class="form-label">Thème</label>
                                <select class="form-select" id="theme" name="theme">
                                    <option value="default" <?php echo getSetting('theme') === 'default' ? 'selected' : ''; ?>>
                                        Thème par défaut
                                    </option>
                                    <option value="dark" <?php echo getSetting('theme') === 'dark' ? 'selected' : ''; ?>>
                                        Thème sombre
                                    </option>
                                    <option value="light" <?php echo getSetting('theme') === 'light' ? 'selected' : ''; ?>>
                                        Thème clair
                                    </option>
                                </select>
                            </div>
                            
                            <div class="d-flex justify-content-end">
                                <button type="button" class="btn btn-primary" onclick="saveSettings('appearance')">
                                    <i class="fas fa-save me-1"></i>
                                    Enregistrer
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Onglet Communication -->
    <div class="tab-pane fade" id="communication" role="tabpanel">
        <div class="row mt-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-comments me-2"></i>
                            Paramètres de communication
                        </h5>
                    </div>
                    <div class="card-body">
                        <form id="communication-form">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="admin_email" class="form-label">
                                        Email administrateur <span class="text-danger">*</span>
                                    </label>
                                    <input type="email" class="form-control" id="admin_email" name="admin_email" 
                                           value="<?php echo htmlspecialchars(getSetting('admin_email', '')); ?>" required>
                                    <div class="form-text">Email pour recevoir les notifications système</div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="whatsapp_number" class="form-label">Numéro WhatsApp</label>
                                    <input type="tel" class="form-control" id="whatsapp_number" name="whatsapp_number" 
                                           value="<?php echo htmlspecialchars(getSetting('whatsapp_number', '')); ?>">
                                    <div class="form-text">Format: +243XXXXXXXXX</div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="enable_email" name="enable_email" value="1"
                                               <?php echo getSetting('enable_email') ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="enable_email">
                                            Activer les emails
                                        </label>
                                    </div>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="enable_sms" name="enable_sms" value="1"
                                               <?php echo getSetting('enable_sms') ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="enable_sms">
                                            Activer les SMS
                                        </label>
                                    </div>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="enable_notifications" name="enable_notifications" value="1"
                                               <?php echo getSetting('enable_notifications') ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="enable_notifications">
                                            Activer les notifications
                                        </label>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="d-flex justify-content-end">
                                <button type="button" class="btn btn-primary" onclick="saveSettings('communication')">
                                    <i class="fas fa-save me-1"></i>
                                    Enregistrer
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Onglet Sécurité -->
    <div class="tab-pane fade" id="security" role="tabpanel">
        <div class="row mt-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-shield-alt me-2"></i>
                            Sécurité et Maintenance
                        </h5>
                    </div>
                    <div class="card-body">
                        <form id="security-form">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="backup_retention_days" class="form-label">
                                        Rétention des sauvegardes (jours)
                                    </label>
                                    <input type="number" class="form-control" id="backup_retention_days" name="backup_retention_days" 
                                           value="<?php echo getSetting('backup_retention_days', 30); ?>" min="1" max="365">
                                    <div class="form-text">Nombre de jours de conservation des sauvegardes</div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="session_lifetime" class="form-label">
                                        Durée de session (secondes)
                                    </label>
                                    <input type="number" class="form-control" id="session_lifetime" name="session_lifetime" 
                                           value="<?php echo getSetting('session_lifetime', 7200); ?>" min="300" max="86400">
                                    <div class="form-text">Durée avant expiration de la session (300-86400s)</div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="max_login_attempts" class="form-label">
                                        Tentatives de connexion max
                                    </label>
                                    <input type="number" class="form-control" id="max_login_attempts" name="max_login_attempts" 
                                           value="<?php echo getSetting('max_login_attempts', 5); ?>" min="3" max="20">
                                    <div class="form-text">Nombre maximum de tentatives de connexion</div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="password_min_length" class="form-label">
                                        Longueur minimale du mot de passe
                                    </label>
                                    <input type="number" class="form-control" id="password_min_length" name="password_min_length" 
                                           value="<?php echo getSetting('password_min_length', 8); ?>" min="6" max="32">
                                    <div class="form-text">Longueur minimale requise pour les mots de passe</div>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="maintenance_mode" name="maintenance_mode" value="1"
                                           <?php echo getSetting('maintenance_mode') ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="maintenance_mode">
                                        Mode maintenance
                                    </label>
                                </div>
                                <div class="form-text text-warning">
                                    ⚠️ Active le mode maintenance (seuls les admins peuvent accéder)
                                </div>
                            </div>
                            
                            <div class="d-flex justify-content-end">
                                <button type="button" class="btn btn-primary" onclick="saveSettings('security')">
                                    <i class="fas fa-save me-1"></i>
                                    Enregistrer
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

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
                    <li><strong>Version :</strong> <?php echo getSetting('app_version', '1.0.0'); ?></li>
                    <li><strong>Dernière mise à jour :</strong> <?php echo date('d/m/Y H:i'); ?></li>
                    <li><strong>Mode debug :</strong> <?php echo defined('DEBUG') && DEBUG ? 'Activé' : 'Désactivé'; ?></li>
                    <li><strong>Utilisateurs connectés :</strong> <?php echo $_SESSION['user_id'] ? '1+' : '0'; ?></li>
                </ul>
            </div>
        </div>
    </div>
</div>

<script>
// Variables globales
let currentTab = 'general';

// Fonction pour sauvegarder les paramètres
function saveSettings(category) {
    const form = document.getElementById(category + '-form');
    const formData = new FormData(form);
    const settings = {};
    
    // Collecter les données du formulaire
    for (let [key, value] of formData.entries()) {
        if (form.querySelector(`[name="${key}"]`).type === 'checkbox') {
            settings[key] = form.querySelector(`[name="${key}"]`).checked ? '1' : '0';
        } else {
            settings[key] = value;
        }
    }
    
    // Envoyer la requête AJAX
    fetch('', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: new URLSearchParams({
            'ajax_action': 'update_settings',
            'settings': JSON.stringify(settings)
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification('success', data.message);
        } else {
            showNotification('error', data.message);
        }
    })
    .catch(error => {
        console.error('Erreur:', error);
        showNotification('error', 'Erreur lors de la sauvegarde des paramètres.');
    });
}

// Fonction pour uploader un fichier
function uploadFile(fileType, settingKey) {
    const fileInput = document.getElementById(fileType + '_upload');
    const file = fileInput.files[0];
    
    if (!file) {
        showNotification('error', 'Veuillez sélectionner un fichier.');
        return;
    }
    
    const formData = new FormData();
    formData.append('ajax_action', 'upload_file');
    formData.append('file_type', fileType);
    formData.append('setting_key', settingKey);
    formData.append('file', file);
    
    fetch('', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification('success', data.message);
            // Mettre à jour la prévisualisation
            const preview = document.getElementById(fileType + '_preview');
            preview.src = data.file_url + '?t=' + Date.now();
        } else {
            showNotification('error', data.message);
        }
    })
    .catch(error => {
        console.error('Erreur:', error);
        showNotification('error', 'Erreur lors de l\'upload du fichier.');
    });
}

// Fonction pour vider le cache
function clearCache() {
    if (confirm('Êtes-vous sûr de vouloir vider le cache des paramètres ?')) {
        fetch('', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: new URLSearchParams({
                'ajax_action': 'clear_cache'
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showNotification('success', data.message);
            } else {
                showNotification('error', data.message);
            }
        })
        .catch(error => {
            console.error('Erreur:', error);
            showNotification('error', 'Erreur lors du vidage du cache.');
        });
    }
}

// Fonction pour afficher les notifications
function showNotification(type, message) {
    const notificationArea = document.getElementById('notification-area');
    const alertClass = type === 'success' ? 'alert-success' : 'alert-danger';
    
    const notification = document.createElement('div');
    notification.className = `alert ${alertClass} alert-dismissible fade show`;
    notification.innerHTML = `
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    
    notificationArea.appendChild(notification);
    
    // Supprimer automatiquement après 5 secondes
    setTimeout(() => {
        if (notification.parentNode) {
            notification.parentNode.removeChild(notification);
        }
    }, 5000);
}

// Initialisation
document.addEventListener('DOMContentLoaded', function() {
    // Gérer le changement d'onglet
    const tabButtons = document.querySelectorAll('#settingsTabs button[data-bs-toggle="tab"]');
    tabButtons.forEach(button => {
        button.addEventListener('shown.bs.tab', function(event) {
            currentTab = event.target.getAttribute('data-bs-target').substring(1);
        });
    });
});
</script>

<?php include '../includes/footer.php'; ?>
