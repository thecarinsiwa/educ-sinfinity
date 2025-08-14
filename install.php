<?php
/**
 * Script d'installation pour Educ-Sinfinity
 * Application de gestion scolaire - République Démocratique du Congo
 */

// Vérifier si l'installation a déjà été effectuée
if (file_exists('config/installed.lock')) {
    die('L\'application est déjà installée. Supprimez le fichier config/installed.lock pour réinstaller.');
}

$step = $_GET['step'] ?? 1;
$errors = [];
$success = [];

// Étape 1: Vérification des prérequis
if ($step == 1) {
    $requirements = [
        'PHP Version >= 7.4' => version_compare(PHP_VERSION, '7.4.0', '>='),
        'Extension PDO' => extension_loaded('pdo'),
        'Extension PDO MySQL' => extension_loaded('pdo_mysql'),
        'Extension GD' => extension_loaded('gd'),
        'Extension mbstring' => extension_loaded('mbstring'),
        'Extension fileinfo' => extension_loaded('fileinfo'),
        'Dossier uploads/ writable' => is_writable('uploads') || mkdir('uploads', 0755, true),
        'Dossier config/ writable' => is_writable('config') || mkdir('config', 0755, true),
    ];
}

// Étape 2: Configuration de la base de données
if ($step == 2 && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $db_host = $_POST['db_host'] ?? 'localhost';
    $db_name = $_POST['db_name'] ?? 'educ_sinfinity';
    $db_user = $_POST['db_user'] ?? 'root';
    $db_pass = $_POST['db_pass'] ?? '';
    
    try {
        // Test de connexion
        $pdo = new PDO("mysql:host=$db_host", $db_user, $db_pass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // Créer la base de données si elle n'existe pas
        $pdo->exec("CREATE DATABASE IF NOT EXISTS `$db_name` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $pdo->exec("USE `$db_name`");
        
        // Lire et exécuter le schéma SQL
        $sql = file_get_contents('database/schema.sql');
        $pdo->exec($sql);
        
        // Créer le fichier de configuration
        $config_content = "<?php\n";
        $config_content .= "define('DB_HOST', '$db_host');\n";
        $config_content .= "define('DB_NAME', '$db_name');\n";
        $config_content .= "define('DB_USER', '$db_user');\n";
        $config_content .= "define('DB_PASS', '$db_pass');\n";
        $config_content .= "?>";
        
        file_put_contents('config/database_config.php', $config_content);
        
        $success[] = 'Base de données configurée avec succès !';
        $step = 3;
        
    } catch (Exception $e) {
        $errors[] = 'Erreur de base de données : ' . $e->getMessage();
    }
}

// Étape 3: Configuration de l'administrateur
if ($step == 3 && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $admin_username = $_POST['admin_username'] ?? 'admin';
    $admin_email = $_POST['admin_email'] ?? 'admin@school.cd';
    $admin_password = $_POST['admin_password'] ?? '';
    $admin_confirm = $_POST['admin_confirm'] ?? '';
    $school_name = $_POST['school_name'] ?? 'École Sinfinity';
    
    if (empty($admin_password)) {
        $errors[] = 'Le mot de passe administrateur est obligatoire.';
    } elseif ($admin_password !== $admin_confirm) {
        $errors[] = 'Les mots de passe ne correspondent pas.';
    } else {
        try {
            include 'config/database_config.php';
            $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            // Mettre à jour l'utilisateur admin
            $hashed_password = password_hash($admin_password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE users SET username = ?, email = ?, password = ? WHERE id = 1");
            $stmt->execute([$admin_username, $admin_email, $hashed_password]);
            
            // Mettre à jour les informations de l'école
            $stmt = $pdo->prepare("UPDATE etablissements SET nom = ?, email = ? WHERE id = 1");
            $stmt->execute([$school_name, $admin_email]);
            
            // Créer le fichier de verrouillage
            file_put_contents('config/installed.lock', date('Y-m-d H:i:s'));
            
            $success[] = 'Installation terminée avec succès !';
            $step = 4;
            
        } catch (Exception $e) {
            $errors[] = 'Erreur lors de la configuration : ' . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Installation - Educ-Sinfinity</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .install-container {
            background: white;
            border-radius: 15px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
            margin: 50px auto;
            max-width: 800px;
        }
        .install-header {
            background: linear-gradient(135deg, #2c3e50, #34495e);
            color: white;
            padding: 30px;
            border-radius: 15px 15px 0 0;
            text-align: center;
        }
        .step-indicator {
            display: flex;
            justify-content: center;
            margin: 30px 0;
        }
        .step {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #e9ecef;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 10px;
            font-weight: bold;
        }
        .step.active {
            background: #3498db;
            color: white;
        }
        .step.completed {
            background: #27ae60;
            color: white;
        }
        .requirement {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 0;
            border-bottom: 1px solid #eee;
        }
        .requirement:last-child {
            border-bottom: none;
        }
        .status-ok {
            color: #27ae60;
        }
        .status-error {
            color: #e74c3c;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="install-container">
            <div class="install-header">
                <h1><i class="fas fa-graduation-cap me-2"></i>Educ-Sinfinity</h1>
                <p class="mb-0">Assistant d'installation</p>
            </div>
            
            <!-- Indicateur d'étapes -->
            <div class="step-indicator">
                <div class="step <?php echo $step >= 1 ? ($step > 1 ? 'completed' : 'active') : ''; ?>">1</div>
                <div class="step <?php echo $step >= 2 ? ($step > 2 ? 'completed' : 'active') : ''; ?>">2</div>
                <div class="step <?php echo $step >= 3 ? ($step > 3 ? 'completed' : 'active') : ''; ?>">3</div>
                <div class="step <?php echo $step >= 4 ? 'active' : ''; ?>">4</div>
            </div>
            
            <div class="p-4">
                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger">
                        <h6><i class="fas fa-exclamation-triangle me-2"></i>Erreurs détectées :</h6>
                        <ul class="mb-0">
                            <?php foreach ($errors as $error): ?>
                                <li><?php echo htmlspecialchars($error); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($success)): ?>
                    <div class="alert alert-success">
                        <h6><i class="fas fa-check-circle me-2"></i>Succès :</h6>
                        <ul class="mb-0">
                            <?php foreach ($success as $msg): ?>
                                <li><?php echo htmlspecialchars($msg); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
                
                <?php if ($step == 1): ?>
                    <!-- Étape 1: Vérification des prérequis -->
                    <h3><i class="fas fa-check-circle me-2"></i>Vérification des prérequis</h3>
                    <p class="text-muted">Vérification de la compatibilité de votre serveur avec Educ-Sinfinity.</p>
                    
                    <div class="requirements">
                        <?php foreach ($requirements as $name => $status): ?>
                            <div class="requirement">
                                <span><?php echo $name; ?></span>
                                <span class="<?php echo $status ? 'status-ok' : 'status-error'; ?>">
                                    <i class="fas fa-<?php echo $status ? 'check' : 'times'; ?>"></i>
                                    <?php echo $status ? 'OK' : 'Erreur'; ?>
                                </span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <?php if (array_product($requirements)): ?>
                        <div class="text-center mt-4">
                            <a href="?step=2" class="btn btn-primary btn-lg">
                                <i class="fas fa-arrow-right me-2"></i>
                                Continuer
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-warning mt-4">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            Veuillez corriger les erreurs avant de continuer.
                        </div>
                    <?php endif; ?>
                    
                <?php elseif ($step == 2): ?>
                    <!-- Étape 2: Configuration de la base de données -->
                    <h3><i class="fas fa-database me-2"></i>Configuration de la base de données</h3>
                    <p class="text-muted">Configurez la connexion à votre base de données MySQL.</p>
                    
                    <form method="POST">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="db_host" class="form-label">Hôte de la base de données</label>
                                <input type="text" class="form-control" id="db_host" name="db_host" value="localhost" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="db_name" class="form-label">Nom de la base de données</label>
                                <input type="text" class="form-control" id="db_name" name="db_name" value="educ_sinfinity" required>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="db_user" class="form-label">Utilisateur</label>
                                <input type="text" class="form-control" id="db_user" name="db_user" value="root" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="db_pass" class="form-label">Mot de passe</label>
                                <input type="password" class="form-control" id="db_pass" name="db_pass">
                            </div>
                        </div>
                        <div class="text-center">
                            <button type="submit" class="btn btn-primary btn-lg">
                                <i class="fas fa-database me-2"></i>
                                Configurer la base de données
                            </button>
                        </div>
                    </form>
                    
                <?php elseif ($step == 3): ?>
                    <!-- Étape 3: Configuration de l'administrateur -->
                    <h3><i class="fas fa-user-shield me-2"></i>Configuration de l'administrateur</h3>
                    <p class="text-muted">Créez le compte administrateur et configurez votre école.</p>
                    
                    <form method="POST">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="admin_username" class="form-label">Nom d'utilisateur</label>
                                <input type="text" class="form-control" id="admin_username" name="admin_username" value="admin" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="admin_email" class="form-label">Email</label>
                                <input type="email" class="form-control" id="admin_email" name="admin_email" value="admin@school.cd" required>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="admin_password" class="form-label">Mot de passe</label>
                                <input type="password" class="form-control" id="admin_password" name="admin_password" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="admin_confirm" class="form-label">Confirmer le mot de passe</label>
                                <input type="password" class="form-control" id="admin_confirm" name="admin_confirm" required>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="school_name" class="form-label">Nom de l'école</label>
                            <input type="text" class="form-control" id="school_name" name="school_name" value="École Sinfinity" required>
                        </div>
                        <div class="text-center">
                            <button type="submit" class="btn btn-success btn-lg">
                                <i class="fas fa-check me-2"></i>
                                Terminer l'installation
                            </button>
                        </div>
                    </form>
                    
                <?php elseif ($step == 4): ?>
                    <!-- Étape 4: Installation terminée -->
                    <div class="text-center">
                        <i class="fas fa-check-circle text-success" style="font-size: 4rem;"></i>
                        <h3 class="mt-3">Installation terminée !</h3>
                        <p class="text-muted">Educ-Sinfinity a été installé avec succès sur votre serveur.</p>
                        
                        <div class="alert alert-info">
                            <h6><i class="fas fa-info-circle me-2"></i>Prochaines étapes :</h6>
                            <ul class="text-start mb-0">
                                <li>Supprimez le fichier <code>install.php</code> pour des raisons de sécurité</li>
                                <li>Configurez les permissions des dossiers si nécessaire</li>
                                <li>Personnalisez les paramètres dans l'interface d'administration</li>
                                <li>Ajoutez vos premières données (classes, matières, etc.)</li>
                            </ul>
                        </div>
                        
                        <a href="auth/login.php" class="btn btn-primary btn-lg">
                            <i class="fas fa-sign-in-alt me-2"></i>
                            Accéder à l'application
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
