<?php
/**
 * Test direct des permissions dans le navigateur
 */

require_once 'config/config.php';
require_once 'config/database.php';
require_once 'includes/permissions-pages.php';
require_once 'includes/ui-permissions.php';

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test Browser Permissions</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/permissions-ui.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <h1>🧪 Test Browser Permissions</h1>
        
        <div class="row">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5>Session Info</h5>
                    </div>
                    <div class="card-body">
                        <?php if (isset($_SESSION['user_id'])): ?>
                            <p><strong>User ID:</strong> <?php echo $_SESSION['user_id']; ?></p>
                            <p><strong>Role:</strong> <?php echo $_SESSION['user_role'] ?? 'N/A'; ?></p>
                        <?php else: ?>
                            <div class="alert alert-warning">Pas de session active</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5>Test hasPagePermission() Direct</h5>
                    </div>
                    <div class="card-body">
                        <?php if (isset($_SESSION['user_id'])): ?>
                            <?php
                            $test_permissions = [
                                ['students', 'add', 'create'],
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
                                        <?php echo $has_permission ? 'TRUE' : 'FALSE'; ?>
                                    </span>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="row mt-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5>Test generatePermissionLink() - HTML Généré</h5>
                    </div>
                    <div class="card-body">
                        <h6>Lien Students Add :</h6>
                        <pre class="bg-light p-3"><code><?php 
                        $html1 = generatePermissionLink(
                            'add.php',
                            'Ajouter un élève',
                            'students',
                            'add',
                            'create',
                            ['class' => 'btn btn-outline-primary', 'icon' => 'fas fa-user-plus me-2']
                        );
                        echo htmlspecialchars($html1);
                        ?></code></pre>
                        
                        <h6>Lien Academic Classes :</h6>
                        <pre class="bg-light p-3"><code><?php 
                        $html2 = generatePermissionLink(
                            'modules/academic/classes.php',
                            'Gérer les classes',
                            'academic',
                            'classes',
                            'read',
                            ['class' => 'btn btn-outline-info', 'icon' => 'fas fa-users me-2']
                        );
                        echo htmlspecialchars($html2);
                        ?></code></pre>
                        
                        <h6>Rendu Visuel :</h6>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="d-grid">
                                    <?php echo $html1; ?>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="d-grid">
                                    <?php echo $html2; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
