<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? $page_title . ' - ' . APP_NAME : APP_NAME; ?></title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    
    <!-- DataTables CSS -->
    <link href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    
    <!-- Custom CSS -->
    <link href="<?php echo APP_URL; ?>/assets/css/style.css" rel="stylesheet">

    <!-- Print CSS -->
    <link href="<?php echo APP_URL; ?>/assets/css/print.css" rel="stylesheet" media="print">
    
    <style>
        :root {
            --primary-color: #2c3e50;
            --secondary-color: #3498db;
            --success-color: #27ae60;
            --warning-color: #f39c12;
            --danger-color: #e74c3c;
            --dark-color: #34495e;
            --light-color: #ecf0f1;
        }
        
        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .navbar-brand {
            font-weight: bold;
            color: white !important;
        }
        
        .sidebar {
            min-height: 100vh;
            background: linear-gradient(135deg, var(--primary-color), var(--dark-color));
            box-shadow: 2px 0 5px rgba(0,0,0,0.1);
        }
        
        .sidebar .nav-link {
            color: rgba(255,255,255,0.8);
            padding: 12px 20px;
            margin: 2px 10px;
            border-radius: 8px;
            transition: all 0.3s ease;
        }
        
        .sidebar .nav-link:hover,
        .sidebar .nav-link.active {
            background-color: rgba(255,255,255,0.1);
            color: white;
            transform: translateX(5px);
        }
        
        .sidebar .nav-link i {
            width: 20px;
            margin-right: 10px;
        }
        
        /* Styles pour les sous-menus */
        .submenu {
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.3s ease;
            background-color: rgba(0,0,0,0.1);
            border-radius: 8px;
            margin: 0 10px;
        }
        
        .submenu.show {
            max-height: 500px;
        }
        
        .submenu .nav-link {
            padding: 8px 15px 8px 35px;
            font-size: 0.9em;
            margin: 1px 5px;
            border-radius: 6px;
        }
        
        .submenu .nav-link:hover {
            background-color: rgba(255,255,255,0.15);
        }
        
        .nav-item.has-submenu .nav-link {
            position: relative;
        }
        
        .nav-item.has-submenu .nav-link::after {
            content: '\f107';
            font-family: 'Font Awesome 6 Free';
            font-weight: 900;
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            transition: transform 0.3s ease;
        }
        
        .nav-item.has-submenu.open .nav-link::after {
            transform: translateY(-50%) rotate(180deg);
        }
        
        /* Amélioration de l'animation des sous-menus */
        .submenu {
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        .submenu.show {
            max-height: 1000px;
            opacity: 1;
        }
        
        .submenu:not(.show) {
            opacity: 0;
        }
        
        .main-content {
            padding: 20px;
        }
        
        .card {
            border: none;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            transition: transform 0.2s ease;
        }
        
        .card:hover {
            transform: translateY(-2px);
        }
        
        .card-header {
            background: linear-gradient(135deg, var(--secondary-color), #5dade2);
            color: white;
            border-radius: 10px 10px 0 0 !important;
            font-weight: 600;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, var(--secondary-color), #5dade2);
            border: none;
            border-radius: 8px;
            padding: 10px 20px;
            font-weight: 500;
        }
        
        .btn-success {
            background: linear-gradient(135deg, var(--success-color), #58d68d);
            border: none;
        }
        
        .btn-warning {
            background: linear-gradient(135deg, var(--warning-color), #f7dc6f);
            border: none;
        }
        
        .btn-danger {
            background: linear-gradient(135deg, var(--danger-color), #ec7063);
            border: none;
        }
        
        .stats-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
        }
        
        .stats-card h3 {
            font-size: 2.5rem;
            font-weight: bold;
            margin-bottom: 5px;
        }
        
        .stats-card p {
            margin: 0;
            opacity: 0.9;
        }
        
        .table {
            border-radius: 10px;
            overflow: hidden;
        }
        
        .table thead th {
            background-color: var(--primary-color);
            color: white;
            border: none;
            font-weight: 600;
        }
        
        .badge {
            font-size: 0.8rem;
            padding: 6px 12px;
            border-radius: 20px;
        }
        
        .form-control, .form-select {
            border-radius: 8px;
            border: 1px solid #ddd;
            padding: 12px 15px;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: var(--secondary-color);
            box-shadow: 0 0 0 0.2rem rgba(52, 152, 219, 0.25);
        }
        
        .alert {
            border-radius: 10px;
            border: none;
        }
        
        @media (max-width: 768px) {
            .sidebar {
                position: fixed;
                top: 0;
                left: -250px;
                width: 250px;
                height: 100vh;
                z-index: 1000;
                transition: left 0.3s ease;
            }
            
            .sidebar.show {
                left: 0;
            }
            
            .main-content {
                margin-left: 0;
            }
        }
    </style>
</head>
<body>
    <?php
    // Vérifier la session
    checkSessionValidity();
    
    // Obtenir les informations de l'utilisateur connecté
    $current_user = getCurrentUser();
    ?>
    
    <!-- Navigation principale -->
    <nav class="navbar navbar-expand-lg navbar-dark" style="background: linear-gradient(135deg, var(--primary-color), var(--dark-color));">
        <div class="container-fluid">
            <button class="navbar-toggler d-lg-none" type="button" id="sidebarToggle">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <a class="navbar-brand" href="<?php echo APP_URL; ?>/dashboard.php">
                <i class="fas fa-graduation-cap me-2"></i>
                <?php echo APP_NAME; ?>
            </a>
            
            <div class="navbar-nav ms-auto">
                <div class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle text-white" href="#" role="button" data-bs-toggle="dropdown">
                        <i class="fas fa-user-circle me-1"></i>
                        <?php echo $current_user['nom'] ?? $current_user['username']; ?>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item" href="#"><i class="fas fa-user me-2"></i>Profil</a></li>
                        <li><a class="dropdown-item" href="#"><i class="fas fa-cog me-2"></i>Paramètres</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#logoutModal"><i class="fas fa-sign-out-alt me-2"></i>Déconnexion</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </nav>
    
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <nav class="col-lg-2 d-lg-block sidebar" id="sidebar">
                <div class="position-sticky pt-3">
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : ''; ?>" 
                               href="<?php echo APP_URL; ?>/dashboard.php">
                                <i class="fas fa-tachometer-alt"></i>
                                Tableau de bord
                            </a>
                        </li>
                        
                        <?php foreach (MODULES as $module_key => $module): ?>
                            <?php if (checkPermission($module_key) || checkPermission($module_key . '_view')): ?>
                                <?php if (isset($module['submenu']) && !empty($module['submenu'])): ?>
                                    <li class="nav-item has-submenu">
                                        <a class="nav-link submenu-toggle" href="#" data-module="<?php echo $module_key; ?>">
                                            <i class="<?php echo $module['icon']; ?>"></i>
                                            <?php echo $module['name']; ?>
                                        </a>
                                        <div class="submenu" id="submenu-<?php echo $module_key; ?>">
                                            <ul class="nav flex-column">
                                                <?php foreach ($module['submenu'] as $submenu_key => $submenu_item): ?>
                                                    <li class="nav-item">
                                                        <a class="nav-link" href="<?php echo APP_URL; ?>/<?php echo $submenu_item['url']; ?>">
                                                            <i class="<?php echo $submenu_item['icon']; ?>"></i>
                                                            <?php echo $submenu_item['name']; ?>
                                                        </a>
                                                    </li>
                                                <?php endforeach; ?>
                                            </ul>
                                        </div>
                                    </li>
                                <?php else: ?>
                                    <li class="nav-item">
                                        <a class="nav-link" href="<?php echo APP_URL; ?>/modules/<?php echo $module_key; ?>/">
                                            <i class="<?php echo $module['icon']; ?>"></i>
                                            <?php echo $module['name']; ?>
                                        </a>
                                    </li>
                                <?php endif; ?>
                            <?php endif; ?>
                        <?php endforeach; ?>
                        
                        <?php if (checkPermission('admin')): ?>
                            <li class="nav-item mt-3">
                                <h6 class="sidebar-heading d-flex justify-content-between align-items-center px-3 mt-4 mb-1 text-muted text-uppercase">
                                    <span>Administration</span>
                                </h6>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="<?php echo APP_URL; ?>/admin/users.php">
                                    <i class="fas fa-users-cog"></i>
                                    Utilisateurs
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="<?php echo APP_URL; ?>/admin/settings.php">
                                    <i class="fas fa-cogs"></i>
                                    Paramètres
                                </a>
                            </li>
                        <?php endif; ?>
                    </ul>
                </div>
            </nav>
            
            <!-- Contenu principal -->
            <main class="col-lg-10 ms-sm-auto px-md-4 main-content">
                <?php displayMessage(); ?>


    
    <!-- Script pour les sous-menus -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Gestion des sous-menus
            const submenuToggles = document.querySelectorAll('.submenu-toggle');
            
            submenuToggles.forEach(toggle => {
                toggle.addEventListener('click', function(e) {
                    e.preventDefault();
                    
                    const navItem = this.closest('.nav-item');
                    const submenu = navItem.querySelector('.submenu');
                    
                    // Fermer tous les autres sous-menus
                    document.querySelectorAll('.submenu').forEach(menu => {
                        if (menu !== submenu) {
                            menu.classList.remove('show');
                            menu.closest('.nav-item').classList.remove('open');
                        }
                    });
                    
                    // Basculer le sous-menu actuel
                    submenu.classList.toggle('show');
                    navItem.classList.toggle('open');
                });
            });
            
            // Gestion du toggle sidebar sur mobile
            const sidebarToggle = document.getElementById('sidebarToggle');
            const sidebar = document.getElementById('sidebar');
            
            if (sidebarToggle && sidebar) {
                sidebarToggle.addEventListener('click', function() {
                    sidebar.classList.toggle('show');
                });
            }
            
            // Fermer le sidebar sur mobile quand on clique sur un lien
            const sidebarLinks = sidebar.querySelectorAll('.nav-link');
            sidebarLinks.forEach(link => {
                link.addEventListener('click', function() {
                    if (window.innerWidth < 992) {
                        sidebar.classList.remove('show');
                    }
                });
            });
            
            // Marquer le menu actif basé sur l'URL
            const currentPath = window.location.pathname;
            const navLinks = document.querySelectorAll('.nav-link');
            
            navLinks.forEach(link => {
                if (link.href && link.href.includes(currentPath)) {
                    link.classList.add('active');
                    
                    // Si c'est un sous-menu, ouvrir le parent
                    const submenuItem = link.closest('.submenu');
                    if (submenuItem) {
                        submenuItem.classList.add('show');
                        submenuItem.closest('.nav-item').classList.add('open');
                    }
                }
            });
        });
    </script>


