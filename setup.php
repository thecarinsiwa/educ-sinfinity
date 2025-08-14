<?php
/**
 * Configuration initiale et diagnostic de l'application
 * Application de gestion scolaire - République Démocratique du Congo
 */

// Activer l'affichage des erreurs pour le diagnostic
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Définir le mode setup pour éviter les erreurs de base de données
define('SETUP_MODE', true);

$errors = [];
$warnings = [];
$success = [];

// Vérifier la version de PHP
if (version_compare(PHP_VERSION, '7.4.0', '<')) {
    $errors[] = "PHP 7.4.0 ou supérieur requis. Version actuelle: " . PHP_VERSION;
} else {
    $success[] = "Version PHP: " . PHP_VERSION . " ✓";
}

// Vérifier les extensions PHP requises
$required_extensions = ['pdo', 'pdo_mysql', 'mbstring', 'json', 'session'];
foreach ($required_extensions as $ext) {
    if (!extension_loaded($ext)) {
        $errors[] = "Extension PHP manquante: $ext";
    } else {
        $success[] = "Extension PHP: $ext ✓";
    }
}

// Vérifier les fichiers de configuration
$config_files = [
    'config/config.php' => 'Configuration générale',
    'config/database.php' => 'Configuration base de données',
    'includes/functions.php' => 'Fonctions utilitaires'
];

foreach ($config_files as $file => $description) {
    if (!file_exists($file)) {
        $errors[] = "Fichier manquant: $file ($description)";
    } else {
        $success[] = "Fichier: $file ✓";
    }
}

// Vérifier les permissions des dossiers
$directories = [
    'uploads' => 'Dossier des téléchargements',
    'assets' => 'Dossier des ressources',
    'modules' => 'Dossier des modules'
];

foreach ($directories as $dir => $description) {
    if (!is_dir($dir)) {
        $warnings[] = "Dossier manquant: $dir ($description)";
    } elseif (!is_writable($dir)) {
        $warnings[] = "Dossier non accessible en écriture: $dir";
    } else {
        $success[] = "Dossier: $dir ✓";
    }
}

// Test de connexion à la base de données
$db_status = "Non testé";
$db_error = "";

if (file_exists('config/database.php')) {
    try {
        require_once 'config/database.php';
        if (isset($database) && $database) {
            // Tester une requête simple
            $stmt = $database->query("SELECT 1");
            if ($stmt) {
                $success[] = "Connexion à la base de données ✓";
                $db_status = "Connecté";
                
                // Vérifier si les tables principales existent
                $tables = ['users', 'annees_scolaires', 'classes', 'eleves'];
                foreach ($tables as $table) {
                    try {
                        $stmt = $database->query("SHOW TABLES LIKE '$table'");
                        if ($stmt->rowCount() > 0) {
                            $success[] = "Table: $table ✓";
                        } else {
                            $warnings[] = "Table manquante: $table";
                        }
                    } catch (Exception $e) {
                        $warnings[] = "Erreur lors de la vérification de la table $table: " . $e->getMessage();
                    }
                }
            }
        }
    } catch (Exception $e) {
        $errors[] = "Erreur de connexion à la base de données: " . $e->getMessage();
        $db_status = "Erreur";
        $db_error = $e->getMessage();
    }
}

// Traitement des actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'create_database') {
        try {
            // Créer la base de données si elle n'existe pas
            $host = 'localhost';
            $username = 'root';
            $password = '';
            $dbname = 'educ_sinfinity';
            
            $pdo = new PDO("mysql:host=$host", $username, $password);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            $pdo->exec("CREATE DATABASE IF NOT EXISTS `$dbname` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            $success[] = "Base de données '$dbname' créée avec succès!";
            
        } catch (Exception $e) {
            $errors[] = "Erreur lors de la création de la base de données: " . $e->getMessage();
        }
    }
    
    if ($action === 'run_migrations') {
        try {
            require_once 'config/database.php';

            // Exécuter le script de création des tables
            if (file_exists('database/schema.sql')) {
                $sql = file_get_contents('database/schema.sql');

                // Nettoyer le SQL (supprimer les commentaires et lignes vides)
                $lines = explode("\n", $sql);
                $clean_sql = '';
                foreach ($lines as $line) {
                    $line = trim($line);
                    if (!empty($line) && !preg_match('/^(--|#)/', $line)) {
                        $clean_sql .= $line . "\n";
                    }
                }

                // Diviser en requêtes individuelles
                $queries = array_filter(array_map('trim', explode(';', $clean_sql)));

                $executed = 0;
                $errors_count = 0;

                foreach ($queries as $query) {
                    if (!empty($query)) {
                        try {
                            $database->execute($query);
                            $executed++;
                        } catch (Exception $e) {
                            // Ignorer certaines erreurs courantes
                            $error_msg = $e->getMessage();
                            if (strpos($error_msg, 'already exists') === false &&
                                strpos($error_msg, 'Duplicate entry') === false) {
                                $errors_count++;
                                if ($errors_count <= 3) { // Limiter l'affichage des erreurs
                                    $warnings[] = "Erreur SQL: " . $error_msg;
                                }
                            }
                        }
                    }
                }

                if ($executed > 0) {
                    $success[] = "Schema exécuté avec succès! ($executed requêtes traitées)";
                    if ($errors_count > 0) {
                        $warnings[] = "$errors_count erreurs ignorées (probablement des tables existantes)";
                    }
                } else {
                    $errors[] = "Aucune requête n'a pu être exécutée";
                }
            } else {
                $errors[] = "Fichier schema.sql non trouvé";
            }

        } catch (Exception $e) {
            $errors[] = "Erreur lors de la création des tables: " . $e->getMessage();
        }
    }
}

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configuration - Educ-Sinfinity</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .status-card { margin-bottom: 20px; }
        .error-item { color: #d32f2f; }
        .warning-item { color: #f57c00; }
        .success-item { color: #388e3c; }
        .code-block { background: #f5f5f5; padding: 15px; border-radius: 4px; font-family: monospace; }
    </style>
</head>
<body class="bg-light">
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-lg-10">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h1 class="h3 mb-0">
                            <i class="fas fa-cog me-2"></i>
                            Configuration Educ-Sinfinity
                        </h1>
                    </div>
                    <div class="card-body">
                        
                        <!-- Messages -->
                        <?php if (!empty($errors)): ?>
                            <div class="alert alert-danger">
                                <h5><i class="fas fa-exclamation-triangle me-2"></i>Erreurs détectées</h5>
                                <ul class="mb-0">
                                    <?php foreach ($errors as $error): ?>
                                        <li class="error-item"><?php echo htmlspecialchars($error); ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($warnings)): ?>
                            <div class="alert alert-warning">
                                <h5><i class="fas fa-exclamation-circle me-2"></i>Avertissements</h5>
                                <ul class="mb-0">
                                    <?php foreach ($warnings as $warning): ?>
                                        <li class="warning-item"><?php echo htmlspecialchars($warning); ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($success)): ?>
                            <div class="alert alert-success">
                                <h5><i class="fas fa-check-circle me-2"></i>Configuration correcte</h5>
                                <ul class="mb-0">
                                    <?php foreach ($success as $item): ?>
                                        <li class="success-item"><?php echo htmlspecialchars($item); ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>
                        
                        <!-- Informations système -->
                        <div class="row">
                            <div class="col-md-6">
                                <div class="card status-card">
                                    <div class="card-header">
                                        <h5><i class="fas fa-server me-2"></i>Informations Système</h5>
                                    </div>
                                    <div class="card-body">
                                        <p><strong>PHP Version:</strong> <?php echo PHP_VERSION; ?></p>
                                        <p><strong>Serveur:</strong> <?php echo $_SERVER['SERVER_SOFTWARE'] ?? 'Non disponible'; ?></p>
                                        <p><strong>OS:</strong> <?php echo PHP_OS; ?></p>
                                        <p><strong>Mémoire limite:</strong> <?php echo ini_get('memory_limit'); ?></p>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="card status-card">
                                    <div class="card-header">
                                        <h5><i class="fas fa-database me-2"></i>Base de Données</h5>
                                    </div>
                                    <div class="card-body">
                                        <p><strong>Statut:</strong> 
                                            <span class="badge bg-<?php echo $db_status === 'Connecté' ? 'success' : 'danger'; ?>">
                                                <?php echo $db_status; ?>
                                            </span>
                                        </p>
                                        <?php if ($db_error): ?>
                                            <p><strong>Erreur:</strong> <code><?php echo htmlspecialchars($db_error); ?></code></p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Actions de configuration -->
                        <div class="card">
                            <div class="card-header">
                                <h5><i class="fas fa-tools me-2"></i>Actions de Configuration</h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <form method="POST">
                                            <input type="hidden" name="action" value="create_database">
                                            <button type="submit" class="btn btn-primary w-100">
                                                <i class="fas fa-database me-2"></i>
                                                Créer la Base de Données
                                            </button>
                                        </form>
                                        <small class="text-muted">Crée la base de données 'educ_sinfinity' si elle n'existe pas</small>
                                    </div>
                                    
                                    <div class="col-md-6 mb-3">
                                        <form method="POST">
                                            <input type="hidden" name="action" value="run_migrations">
                                            <button type="submit" class="btn btn-success w-100">
                                                <i class="fas fa-table me-2"></i>
                                                Créer les Tables
                                            </button>
                                        </form>
                                        <small class="text-muted">Exécute le script de création des tables</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Configuration manuelle -->
                        <div class="card mt-4">
                            <div class="card-header">
                                <h5><i class="fas fa-wrench me-2"></i>Configuration Manuelle</h5>
                            </div>
                            <div class="card-body">
                                <h6>1. Configuration de la base de données</h6>
                                <p>Modifiez le fichier <code>config/database.php</code> avec vos paramètres :</p>
                                <div class="code-block">
$host = 'localhost';<br>
$username = 'root';<br>
$password = '';<br>
$database_name = 'educ_sinfinity';
                                </div>
                                
                                <h6 class="mt-4">2. Permissions des dossiers</h6>
                                <p>Assurez-vous que ces dossiers sont accessibles en écriture :</p>
                                <ul>
                                    <li><code>uploads/</code> - Pour les fichiers téléchargés</li>
                                    <li><code>assets/</code> - Pour les ressources</li>
                                </ul>
                            </div>
                        </div>
                        
                        <!-- Navigation -->
                        <div class="text-center mt-4">
                            <?php if (empty($errors)): ?>
                                <a href="index.php" class="btn btn-success btn-lg">
                                    <i class="fas fa-home me-2"></i>
                                    Accéder à l'Application
                                </a>
                            <?php else: ?>
                                <button class="btn btn-secondary btn-lg" disabled>
                                    <i class="fas fa-exclamation-triangle me-2"></i>
                                    Corrigez les erreurs avant de continuer
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
