<?php
/**
 * Vérification de la session utilisateur
 */

require_once 'config/config.php';
require_once 'config/database.php';

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vérification Session</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <h1>🔍 Vérification de la Session</h1>
        
        <div class="card">
            <div class="card-header">
                <h5>État de la Session</h5>
            </div>
            <div class="card-body">
                <?php if (isset($_SESSION['user_id'])): ?>
                    <div class="alert alert-success">
                        <h6>✅ Session Active</h6>
                        <p><strong>User ID:</strong> <?php echo $_SESSION['user_id']; ?></p>
                        <p><strong>Username:</strong> <?php echo $_SESSION['username'] ?? 'N/A'; ?></p>
                        <p><strong>Role:</strong> <?php echo $_SESSION['user_role'] ?? 'N/A'; ?></p>
                        <p><strong>Role ID:</strong> <?php echo $_SESSION['user_role_id'] ?? 'N/A'; ?></p>
                        <p><strong>Full Name:</strong> <?php echo $_SESSION['user_full_name'] ?? 'N/A'; ?></p>
                    </div>
                    
                    <div class="mt-3">
                        <h6>Test des Permissions :</h6>
                        <?php
                        require_once 'includes/permissions-pages.php';
                        
                        $test_permissions = [
                            ['students', 'add', 'create'],
                            ['students', 'list', 'read'],
                            ['academic', 'classes', 'read'],
                            ['finance', 'fees', 'read']
                        ];
                        
                        foreach ($test_permissions as $permission):
                            list($module, $page, $action) = $permission;
                            $has_permission = hasPagePermission($module, $page, $action);
                        ?>
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span><?php echo "$module:$page:$action"; ?></span>
                                <span class="badge <?php echo $has_permission ? 'bg-success' : 'bg-danger'; ?>">
                                    <?php echo $has_permission ? 'Autorisé' : 'Refusé'; ?>
                                </span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                <?php else: ?>
                    <div class="alert alert-warning">
                        <h6>❌ Aucune Session Active</h6>
                        <p>Vous n'êtes pas connecté.</p>
                        <a href="auth/login.php" class="btn btn-primary">Se connecter</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="card mt-4">
            <div class="card-header">
                <h5>Informations de Session Brutes</h5>
            </div>
            <div class="card-body">
                <pre class="bg-light p-3"><?php 
                echo "Session ID: " . session_id() . "\n";
                echo "Session Name: " . session_name() . "\n";
                echo "Session Data:\n";
                print_r($_SESSION);
                ?></pre>
            </div>
        </div>
        
        <div class="card mt-4">
            <div class="card-header">
                <h5>Actions</h5>
            </div>
            <div class="card-body">
                <a href="auth/login.php" class="btn btn-primary">Se connecter</a>
                <a href="auth/logout.php" class="btn btn-secondary">Se déconnecter</a>
                <a href="modules/students/index.php" class="btn btn-info">Aller à la page Students</a>
            </div>
        </div>
    </div>
</body>
</html>
