<?php
/**
 * Debug de la page students pour voir le HTML généré
 */

require_once 'config/config.php';
require_once 'config/database.php';
require_once 'includes/functions.php';
require_once 'includes/permissions-pages.php';
require_once 'includes/ui-permissions.php';

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Debug Students Page</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/permissions-ui.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <h1>🔍 Debug Students Page</h1>
        
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
                        <h5>Test Permissions</h5>
                    </div>
                    <div class="card-body">
                        <?php if (isset($_SESSION['user_id'])): ?>
                            <p><strong>students:add:create:</strong> 
                                <?php echo hasPagePermission('students', 'add', 'create') ? '✅ Autorisé' : '❌ Refusé'; ?>
                            </p>
                            <p><strong>academic:classes:read:</strong> 
                                <?php echo hasPagePermission('academic', 'classes', 'read') ? '✅ Autorisé' : '❌ Refusé'; ?>
                            </p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="row mt-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5>Actions rapides - Structure identique à la page students</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-3 mb-2">
                                <div class="d-grid">
                                    <?php echo generatePermissionLink(
                                        'add.php',
                                        'Ajouter un élève',
                                        'students',
                                        'add',
                                        'create',
                                        ['class' => 'btn btn-outline-primary', 'icon' => 'fas fa-user-plus me-2']
                                    ); ?>
                                </div>
                            </div>
                            <div class="col-md-3 mb-2">
                                <div class="d-grid">
                                    <?php echo generatePermissionLink(
                                        'list.php',
                                        'Liste des élèves',
                                        'students',
                                        'list',
                                        'read',
                                        ['class' => 'btn btn-outline-success', 'icon' => 'fas fa-list me-2']
                                    ); ?>
                                </div>
                            </div>
                            <div class="col-md-3 mb-2">
                                <div class="d-grid">
                                    <?php echo generatePermissionLink(
                                        'modules/academic/classes.php',
                                        'Gérer les classes',
                                        'academic',
                                        'classes',
                                        'read',
                                        ['class' => 'btn btn-outline-info', 'icon' => 'fas fa-users me-2']
                                    ); ?>
                                </div>
                            </div>
                            <div class="col-md-3 mb-2">
                                <div class="d-grid">
                                    <?php echo generatePermissionLink(
                                        'modules/finance/fees.php',
                                        'Gestion des frais',
                                        'finance',
                                        'fees',
                                        'read',
                                        ['class' => 'btn btn-outline-warning', 'icon' => 'fas fa-money-bill me-2']
                                    ); ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="row mt-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5>Code Source HTML Généré</h5>
                    </div>
                    <div class="card-body">
                        <h6>Bouton Students Add (devrait être autorisé) :</h6>
                        <pre class="bg-light p-3"><code><?php echo htmlspecialchars(generatePermissionLink(
                            'add.php',
                            'Ajouter un élève',
                            'students',
                            'add',
                            'create',
                            ['class' => 'btn btn-outline-primary', 'icon' => 'fas fa-user-plus me-2']
                        )); ?></code></pre>
                        
                        <h6>Bouton Academic Classes (devrait être refusé) :</h6>
                        <pre class="bg-light p-3"><code><?php echo htmlspecialchars(generatePermissionLink(
                            'modules/academic/classes.php',
                            'Gérer les classes',
                            'academic',
                            'classes',
                            'read',
                            ['class' => 'btn btn-outline-info', 'icon' => 'fas fa-users me-2']
                        )); ?></code></pre>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="row mt-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5>Test CSS</h5>
                    </div>
                    <div class="card-body">
                        <h6>Boutons de test pour vérifier le CSS :</h6>
                        <button class="btn btn-primary me-2">Bouton normal</button>
                        <button class="btn btn-primary disabled me-2">Bouton disabled</button>
                        <span class="btn btn-primary me-2">Span avec classe btn</span>
                        <span class="btn btn-primary disabled me-2">Span avec btn disabled</span>
                        
                        <h6 class="mt-3">Classes CSS chargées :</h6>
                        <div class="bg-light p-3">
                            <code>permissions-ui.css</code> devrait être chargé et contenir les styles pour .disabled
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
