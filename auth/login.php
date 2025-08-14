<?php
/**
 * Page de connexion
 * Application de gestion scolaire - République Démocratique du Congo
 */

require_once '../config/config.php';
require_once '../config/database.php';
require_once '../includes/functions.php';

// Rediriger si déjà connecté
if (isLoggedIn()) {
    redirectTo('../dashboard.php');
}

$error_message = '';

// Traitement du formulaire de connexion
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = sanitizeInput($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($username) || empty($password)) {
        $error_message = 'Veuillez remplir tous les champs.';
    } else {
        if (authenticateUser($username, $password)) {
            redirectTo('../dashboard.php');
        } else {
            $error_message = 'Nom d\'utilisateur ou mot de passe incorrect.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion - <?php echo APP_NAME; ?></title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .login-container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            max-width: 900px;
            width: 100%;
        }
        
        .login-left {
            background: linear-gradient(135deg, #2c3e50 0%, #34495e 100%);
            color: white;
            padding: 60px 40px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            text-align: center;
        }
        
        .login-right {
            padding: 60px 40px;
        }
        
        .login-logo {
            font-size: 4rem;
            margin-bottom: 20px;
            color: #3498db;
        }
        
        .login-title {
            font-size: 2.5rem;
            font-weight: bold;
            margin-bottom: 10px;
        }
        
        .login-subtitle {
            font-size: 1.1rem;
            opacity: 0.9;
            margin-bottom: 30px;
        }
        
        .form-control {
            border-radius: 10px;
            border: 2px solid #e9ecef;
            padding: 15px 20px;
            font-size: 1rem;
            transition: all 0.3s ease;
        }
        
        .form-control:focus {
            border-color: #3498db;
            box-shadow: 0 0 0 0.2rem rgba(52, 152, 219, 0.25);
        }
        
        .input-group-text {
            border-radius: 10px 0 0 10px;
            border: 2px solid #e9ecef;
            border-right: none;
            background-color: #f8f9fa;
            color: #6c757d;
        }
        
        .input-group .form-control {
            border-left: none;
            border-radius: 0 10px 10px 0;
        }
        
        .btn-login {
            background: linear-gradient(135deg, #3498db, #2980b9);
            border: none;
            border-radius: 10px;
            padding: 15px 30px;
            font-size: 1.1rem;
            font-weight: 600;
            color: white;
            width: 100%;
            transition: all 0.3s ease;
        }
        
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(52, 152, 219, 0.4);
        }
        
        .alert {
            border-radius: 10px;
            border: none;
        }
        
        .features {
            list-style: none;
            padding: 0;
        }
        
        .features li {
            padding: 10px 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .features li:last-child {
            border-bottom: none;
        }
        
        .features i {
            margin-right: 10px;
            color: #3498db;
        }
        
        @media (max-width: 768px) {
            .login-left {
                padding: 40px 20px;
            }
            
            .login-right {
                padding: 40px 20px;
            }
            
            .login-title {
                font-size: 2rem;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-10">
                <div class="login-container">
                    <div class="row g-0">
                        <!-- Partie gauche - Informations -->
                        <div class="col-lg-6 login-left">
                            <div>
                                <div class="login-logo">
                                    <i class="fas fa-graduation-cap"></i>
                                </div>
                                <h1 class="login-title"><?php echo APP_NAME; ?></h1>
                                <p class="login-subtitle">Système de gestion d'établissement scolaire</p>
                                
                                <ul class="features">
                                    <li><i class="fas fa-user-graduate"></i> Gestion des élèves</li>
                                    <li><i class="fas fa-users"></i> Gestion du personnel</li>
                                    <li><i class="fas fa-book"></i> Gestion académique</li>
                                    <li><i class="fas fa-chart-line"></i> Évaluations et notes</li>
                                    <li><i class="fas fa-money-bill-wave"></i> Gestion financière</li>
                                    <li><i class="fas fa-chart-bar"></i> Rapports et statistiques</li>
                                </ul>
                                
                                <div class="mt-4">
                                    <small>République Démocratique du Congo</small>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Partie droite - Formulaire de connexion -->
                        <div class="col-lg-6 login-right">
                            <div class="text-center mb-4">
                                <h2>Connexion</h2>
                                <p class="text-muted">Accédez à votre espace de gestion</p>
                            </div>
                            
                            <?php if (!empty($error_message)): ?>
                                <div class="alert alert-danger" role="alert">
                                    <i class="fas fa-exclamation-triangle me-2"></i>
                                    <?php echo $error_message; ?>
                                </div>
                            <?php endif; ?>
                            
                            <form method="POST" action="">
                                <div class="mb-3">
                                    <label for="username" class="form-label">Nom d'utilisateur</label>
                                    <div class="input-group">
                                        <span class="input-group-text">
                                            <i class="fas fa-user"></i>
                                        </span>
                                        <input type="text" 
                                               class="form-control" 
                                               id="username" 
                                               name="username" 
                                               placeholder="Entrez votre nom d'utilisateur"
                                               value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>"
                                               required>
                                    </div>
                                </div>
                                
                                <div class="mb-4">
                                    <label for="password" class="form-label">Mot de passe</label>
                                    <div class="input-group">
                                        <span class="input-group-text">
                                            <i class="fas fa-lock"></i>
                                        </span>
                                        <input type="password" 
                                               class="form-control" 
                                               id="password" 
                                               name="password" 
                                               placeholder="Entrez votre mot de passe"
                                               required>
                                        <button class="btn btn-outline-secondary" 
                                                type="button" 
                                                id="togglePassword">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                    </div>
                                </div>
                                
                                <div class="mb-3 form-check">
                                    <input type="checkbox" class="form-check-input" id="remember">
                                    <label class="form-check-label" for="remember">
                                        Se souvenir de moi
                                    </label>
                                </div>
                                
                                <button type="submit" class="btn btn-login">
                                    <i class="fas fa-sign-in-alt me-2"></i>
                                    Se connecter
                                </button>
                            </form>
                            
                            <div class="text-center mt-4">
                                <small class="text-muted">
                                    Mot de passe oublié ?
                                    <a href="#" class="text-decoration-none">Contactez l'administrateur</a>
                                </small>
                            </div>

                            <div class="text-center mt-3">
                                <div class="border-top pt-3">
                                    <p class="text-muted mb-2">Vous n'avez pas de compte ?</p>
                                    <a href="signup.php" class="btn btn-outline-primary btn-sm">
                                        <i class="fas fa-user-plus me-2"></i>
                                        Créer un compte
                                    </a>
                                </div>
                            </div>

                            <div class="text-center mt-4">
                                <small class="text-muted">
                                    Version <?php echo APP_VERSION; ?> |
                                    <i class="fas fa-shield-alt"></i> Connexion sécurisée
                                </small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Toggle password visibility
        document.getElementById('togglePassword').addEventListener('click', function() {
            const password = document.getElementById('password');
            const icon = this.querySelector('i');
            
            if (password.type === 'password') {
                password.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                password.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        });
        
        // Focus sur le premier champ
        document.getElementById('username').focus();
        
        // Animation d'entrée
        document.addEventListener('DOMContentLoaded', function() {
            const container = document.querySelector('.login-container');
            container.style.opacity = '0';
            container.style.transform = 'translateY(30px)';
            
            setTimeout(() => {
                container.style.transition = 'all 0.6s ease';
                container.style.opacity = '1';
                container.style.transform = 'translateY(0)';
            }, 100);
        });
    </script>
</body>
</html>
