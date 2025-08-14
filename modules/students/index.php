<?php
/**
 * Module Gestion des Élèves - Page principale enrichie
 * Application de gestion scolaire - République Démocratique du Congo
 */

require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../includes/functions.php';

// Vérifier l'authentification et les permissions
requireLogin();
if (!checkPermission('students') && !checkPermission('students_view')) {
    showMessage('error', 'Accès refusé à ce module.');
    redirectTo('../../dashboard.php');
}

$page_title = 'Gestion des Élèves';

// Obtenir l'année scolaire actuelle
$current_year = getCurrentAcademicYear();

// Statistiques générales
$stats = [];

// Total des élèves inscrits
$stmt = $database->query(
    "SELECT COUNT(*) as total FROM inscriptions WHERE status = 'inscrit' AND annee_scolaire_id = ?",
    [$current_year['id'] ?? 0]
);
$stats['total_eleves'] = $stmt->fetch()['total'];

// Nouvelles inscriptions ce mois
try {
    $stmt = $database->query(
        "SELECT COUNT(*) as total FROM inscriptions
         WHERE MONTH(created_at) = MONTH(CURDATE())
         AND YEAR(created_at) = YEAR(CURDATE())
         AND annee_scolaire_id = ?",
        [$current_year['id'] ?? 0]
    );
    $stats['nouvelles_inscriptions'] = $stmt->fetch()['total'];
} catch (Exception $e) {
    // Si la table n'existe pas ou n'a pas la colonne created_at
    $stats['nouvelles_inscriptions'] = 0;
}

// Absences aujourd'hui
try {
    $stmt = $database->query(
        "SELECT COUNT(*) as total FROM absences
         WHERE DATE(date_absence) = CURDATE() AND type_absence = 'absence'"
    );
    $stats['absences_aujourd_hui'] = $stmt->fetch()['total'];
} catch (Exception $e) {
    // Si la table n'existe pas
    $stats['absences_aujourd_hui'] = 0;
}

// Transferts en attente
try {
    $stmt = $database->query(
        "SELECT COUNT(*) as total FROM transferts_sorties
         WHERE status = 'en_attente' AND annee_scolaire_id = ?",
        [$current_year['id'] ?? 0]
    );
    $stats['transferts_attente'] = $stmt->fetch()['total'];
} catch (Exception $e) {
    // Si la table n'existe pas
    $stats['transferts_attente'] = 0;
}

// Répartition par niveau
$repartition_niveaux = $database->query(
    "SELECT c.niveau, COUNT(i.id) as nombre
     FROM classes c
     LEFT JOIN inscriptions i ON c.id = i.classe_id AND i.status = 'inscrit'
     WHERE c.annee_scolaire_id = ?
     GROUP BY c.niveau
     ORDER BY 
        CASE c.niveau 
            WHEN 'maternelle' THEN 1 
            WHEN 'primaire' THEN 2 
            WHEN 'secondaire' THEN 3 
        END",
    [$current_year['id'] ?? 0]
)->fetchAll();

// Élèves récemment inscrits
try {
    $eleves_recents = $database->query(
        "SELECT e.nom, e.prenom, e.numero_matricule, c.nom as classe_nom, c.niveau, i.created_at
         FROM eleves e
         JOIN inscriptions i ON e.id = i.eleve_id
         JOIN classes c ON i.classe_id = c.id
         WHERE i.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
         AND i.annee_scolaire_id = ?
         ORDER BY i.created_at DESC
         LIMIT 8",
        [$current_year['id'] ?? 0]
    )->fetchAll();
} catch (Exception $e) {
    // Si les tables n'existent pas ou n'ont pas les bonnes colonnes
    $eleves_recents = [];
}

// Classes avec le plus d'élèves
$classes_nombreuses = $database->query(
    "SELECT c.nom, c.niveau, COUNT(i.id) as effectif
     FROM classes c
     LEFT JOIN inscriptions i ON c.id = i.classe_id AND i.status = 'inscrit'
     WHERE c.annee_scolaire_id = ?
     GROUP BY c.id
     ORDER BY effectif DESC
     LIMIT 5",
    [$current_year['id'] ?? 0]
)->fetchAll();

// Vérifier si les tables essentielles existent
$tables_missing = [];
$required_tables = ['eleves', 'inscriptions', 'classes', 'annees_scolaires'];

foreach ($required_tables as $table) {
    try {
        $database->query("SELECT 1 FROM $table LIMIT 1");
    } catch (Exception $e) {
        $tables_missing[] = $table;
    }
}

include '../../includes/header.php';
?>

<?php if (!empty($tables_missing)): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <h4 class="alert-heading">
            <i class="fas fa-exclamation-triangle me-2"></i>
            Tables manquantes détectées
        </h4>
        <p>Les tables suivantes sont manquantes ou inaccessibles :</p>
        <ul class="mb-3">
            <?php foreach ($tables_missing as $table): ?>
                <li><code><?php echo $table; ?></code></li>
            <?php endforeach; ?>
        </ul>
        <hr>
        <p class="mb-0">
            <a href="../../fix-students-tables.php" class="btn btn-warning me-2">
                <i class="fas fa-tools me-1"></i>
                Corriger automatiquement
            </a>
            <a href="../../debug-tables.php" class="btn btn-info">
                <i class="fas fa-search me-1"></i>
                Diagnostic complet
            </a>
        </p>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<!-- En-tête moderne du module students -->
<div class="students-header mb-4">
    <div class="row align-items-center">
        <div class="col-md-8">
            <div class="welcome-section">
                <h1 class="display-6 mb-1">
                    <i class="fas fa-user-graduate me-3 text-primary"></i>
                    Gestion des Élèves
                </h1>
                <p class="text-muted mb-0">
                    Gérez les inscriptions, dossiers scolaires, présences et transferts de vos élèves.
                </p>
            </div>
        </div>
        <div class="col-md-4 text-end">
            <div class="action-buttons">
                <?php if (checkPermission('students')): ?>
                    <div class="btn-group me-2">
                        <button type="button" class="btn btn-primary dropdown-toggle" data-bs-toggle="dropdown">
                            <i class="fas fa-plus me-1"></i>
                            Nouveau
                        </button>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="admissions/new-application.php">
                                <i class="fas fa-file-alt me-2"></i>Demande d'admission
                            </a></li>
                            <li><a class="dropdown-item" href="add.php">
                                <i class="fas fa-user-plus me-2"></i>Inscription directe
                            </a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="attendance/add-absence.php">
                                <i class="fas fa-user-times me-2"></i>Signaler absence
                            </a></li>
                        </ul>
                    </div>
                <?php endif; ?>
                <div class="btn-group me-2">
                    <button type="button" class="btn btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">
                        <i class="fas fa-file-export me-1"></i>
                        Exporter
                    </button>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="exports/students-list.php">
                            <i class="fas fa-file-excel me-2"></i>Liste Excel
                        </a></li>
                        <li><a class="dropdown-item" href="exports/students-cards.php">
                            <i class="fas fa-file-pdf me-2"></i>Fiches élèves PDF
                        </a></li>
                    </ul>
                </div>
                <div class="btn-group">
                    <button type="button" class="btn btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">
                        <i class="fas fa-tools me-1"></i>
                        Outils
                    </button>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="search.php">
                            <i class="fas fa-search me-2"></i>Recherche avancée
                        </a></li>
                        <li><a class="dropdown-item" href="reports.php">
                            <i class="fas fa-chart-bar me-2"></i>Rapports et statistiques
                        </a></li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Cartes de statistiques modernes -->
<div class="row mb-4">
    <!-- Élèves inscrits -->
    <div class="col-lg-3 col-md-6 mb-4">
        <div class="stat-card stat-card-primary">
            <div class="stat-card-body">
                <div class="stat-card-icon">
                    <i class="fas fa-users"></i>
                </div>
                <div class="stat-card-content">
                    <h3 class="stat-card-number"><?php echo $stats['total_eleves']; ?></h3>
                    <p class="stat-card-label">Élèves inscrits</p>
                    <div class="stat-card-trend">
                        <i class="fas fa-arrow-up text-success"></i>
                        <span class="text-success">Actifs</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Nouvelles inscriptions -->
    <div class="col-lg-3 col-md-6 mb-4">
        <div class="stat-card stat-card-success">
            <div class="stat-card-body">
                <div class="stat-card-icon">
                    <i class="fas fa-user-plus"></i>
                </div>
                <div class="stat-card-content">
                    <h3 class="stat-card-number"><?php echo $stats['nouvelles_inscriptions']; ?></h3>
                    <p class="stat-card-label">Nouvelles inscriptions</p>
                    <div class="stat-card-trend">
                        <i class="fas fa-calendar-alt text-success"></i>
                        <span class="text-success">Ce mois</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Absences aujourd'hui -->
    <div class="col-lg-3 col-md-6 mb-4">
        <div class="stat-card stat-card-warning">
            <div class="stat-card-body">
                <div class="stat-card-icon">
                    <i class="fas fa-user-times"></i>
                </div>
                <div class="stat-card-content">
                    <h3 class="stat-card-number"><?php echo $stats['absences_aujourd_hui']; ?></h3>
                    <p class="stat-card-label">Absences aujourd'hui</p>
                    <div class="stat-card-trend">
                        <i class="fas fa-calendar-day text-warning"></i>
                        <span class="text-warning">À surveiller</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Transferts en attente -->
    <div class="col-lg-3 col-md-6 mb-4">
        <div class="stat-card stat-card-info">
            <div class="stat-card-body">
                <div class="stat-card-icon">
                    <i class="fas fa-exchange-alt"></i>
                </div>
                <div class="stat-card-content">
                    <h3 class="stat-card-number"><?php echo $stats['transferts_attente']; ?></h3>
                    <p class="stat-card-label">Transferts en attente</p>
                    <div class="stat-card-trend">
                        <i class="fas fa-clock text-info"></i>
                        <span class="text-info">En cours</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modules de gestion des élèves -->
<div class="row mb-4">
    <div class="col-12">
        <div class="modules-card">
            <div class="modules-card-header">
                <h5 class="modules-card-title">
                    <i class="fas fa-th-large me-2"></i>
                    Modules de gestion des élèves
                </h5>
                <p class="modules-card-subtitle">Accédez aux différentes fonctionnalités de gestion des élèves</p>
            </div>
            <div class="modules-card-body">
                <div class="modules-grid">
                    <!-- Inscriptions & Admissions -->
                    <a href="admissions/" class="module-item">
                        <div class="module-icon">
                            <i class="fas fa-user-plus"></i>
                        </div>
                        <div class="module-content">
                            <h6 class="module-title">Inscriptions & Admissions</h6>
                            <p class="module-description">Gestion des demandes d'admission et processus d'inscription</p>
                        </div>
                        <div class="module-badge">
                            <span class="badge bg-primary">Processus complet</span>
                        </div>
                        <div class="module-arrow">
                            <i class="fas fa-chevron-right"></i>
                        </div>
                    </a>
                    
                    <!-- Dossiers Scolaires -->
                    <a href="records/" class="module-item">
                        <div class="module-icon">
                            <i class="fas fa-folder-open"></i>
                        </div>
                        <div class="module-content">
                            <h6 class="module-title">Dossiers Scolaires</h6>
                            <p class="module-description">Informations personnelles et historique académique</p>
                        </div>
                        <div class="module-badge">
                            <span class="badge bg-success"><?php echo $stats['total_eleves']; ?> dossiers</span>
                        </div>
                        <div class="module-arrow">
                            <i class="fas fa-chevron-right"></i>
                        </div>
                    </a>
                    
                    <!-- Absences & Retards -->
                    <a href="attendance/" class="module-item">
                        <div class="module-icon">
                            <i class="fas fa-calendar-check"></i>
                        </div>
                        <div class="module-content">
                            <h6 class="module-title">Absences & Retards</h6>
                            <p class="module-description">Suivi de l'assiduité et de la ponctualité</p>
                        </div>
                        <div class="module-badge">
                            <span class="badge bg-info">Suivi quotidien</span>
                        </div>
                        <div class="module-arrow">
                            <i class="fas fa-chevron-right"></i>
                        </div>
                    </a>
                    
                    <!-- Transferts & Sorties -->
                    <a href="transfers/" class="module-item">
                        <div class="module-icon">
                            <i class="fas fa-exchange-alt"></i>
                        </div>
                        <div class="module-content">
                            <h6 class="module-title">Transferts & Sorties</h6>
                            <p class="module-description">Gestion des mouvements et certificats</p>
                        </div>
                        <div class="module-badge">
                            <span class="badge bg-warning">Certificats inclus</span>
                        </div>
                        <div class="module-arrow">
                            <i class="fas fa-chevron-right"></i>
                        </div>
                    </a>
                    
                    <!-- Liste des Élèves -->
                    <a href="list.php" class="module-item">
                        <div class="module-icon">
                            <i class="fas fa-list"></i>
                        </div>
                        <div class="module-content">
                            <h6 class="module-title">Liste des Élèves</h6>
                            <p class="module-description">Consulter et gérer la liste complète</p>
                        </div>
                        <div class="module-badge">
                            <span class="badge bg-secondary"><?php echo $stats['total_eleves']; ?> élèves</span>
                        </div>
                        <div class="module-arrow">
                            <i class="fas fa-chevron-right"></i>
                        </div>
                    </a>
                    
                    <!-- Inscription Directe -->
                    <a href="add.php" class="module-item">
                        <div class="module-icon">
                            <i class="fas fa-user-check"></i>
                        </div>
                        <div class="module-content">
                            <h6 class="module-title">Inscription Directe</h6>
                            <p class="module-description">Ajouter un élève rapidement</p>
                        </div>
                        <div class="module-badge">
                            <span class="badge bg-dark">Inscription rapide</span>
                        </div>
                        <div class="module-arrow">
                            <i class="fas fa-chevron-right"></i>
                        </div>
                    </a>
                    
                    <!-- Recherche Avancée -->
                    <a href="search.php" class="module-item">
                        <div class="module-icon">
                            <i class="fas fa-search"></i>
                        </div>
                        <div class="module-content">
                            <h6 class="module-title">Recherche Avancée</h6>
                            <p class="module-description">Rechercher des élèves par critères</p>
                        </div>
                        <div class="module-badge">
                            <span class="badge bg-light text-dark">Filtres multiples</span>
                        </div>
                        <div class="module-arrow">
                            <i class="fas fa-chevron-right"></i>
                        </div>
                    </a>
                    
                    <!-- Rapports -->
                    <a href="reports.php" class="module-item">
                        <div class="module-icon">
                            <i class="fas fa-chart-bar"></i>
                        </div>
                        <div class="module-content">
                            <h6 class="module-title">Rapports</h6>
                            <p class="module-description">Statistiques et analyses</p>
                        </div>
                        <div class="module-badge">
                            <span class="badge bg-light text-dark">Analyses détaillées</span>
                        </div>
                        <div class="module-arrow">
                            <i class="fas fa-chevron-right"></i>
                        </div>
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Styles CSS pour le module students -->
<style>
/* En-tête du module students */
.students-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 2rem;
    border-radius: 15px;
    margin-bottom: 2rem;
}

.welcome-section h1 {
    color: white;
    font-weight: 600;
}

.action-buttons {
    display: flex;
    gap: 0.5rem;
    flex-wrap: wrap;
    justify-content: flex-end;
}

/* Cartes de statistiques */
.stat-card {
    background: white;
    border-radius: 15px;
    padding: 1.5rem;
    box-shadow: 0 4px 20px rgba(0,0,0,0.08);
    transition: all 0.3s ease;
    border: 1px solid rgba(0,0,0,0.05);
    position: relative;
    overflow: hidden;
}

.stat-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
    background: linear-gradient(90deg, var(--card-color), var(--card-color-light));
}

.stat-card-primary { --card-color: #3498db; --card-color-light: #5dade2; }
.stat-card-success { --card-color: #27ae60; --card-color-light: #58d68d; }
.stat-card-warning { --card-color: #f39c12; --card-color-light: #f8c471; }
.stat-card-info { --card-color: #17a2b8; --card-color-light: #5bc0de; }

.stat-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 30px rgba(0,0,0,0.12);
}

.stat-card-body {
    display: flex;
    align-items: center;
    gap: 1rem;
}

.stat-card-icon {
    width: 60px;
    height: 60px;
    border-radius: 50%;
    background: linear-gradient(135deg, var(--card-color), var(--card-color-light));
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 1.5rem;
    flex-shrink: 0;
}

.stat-card-content {
    flex: 1;
}

.stat-card-number {
    font-size: 2rem;
    font-weight: 700;
    margin: 0;
    color: #2c3e50;
}

.stat-card-label {
    color: #7f8c8d;
    font-size: 0.9rem;
    margin: 0.25rem 0;
    font-weight: 500;
}

.stat-card-trend {
    font-size: 0.8rem;
    margin-top: 0.5rem;
}

/* Grille des modules */
.modules-card {
    background: white;
    border-radius: 15px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.08);
    border: 1px solid rgba(0,0,0,0.05);
    overflow: hidden;
}

.modules-card-header {
    padding: 2rem 1.5rem 1rem;
    text-align: center;
}

.modules-card-title {
    margin: 0 0 0.5rem 0;
    font-weight: 600;
    color: #2c3e50;
}

.modules-card-subtitle {
    color: #7f8c8d;
    margin: 0;
}

.modules-card-body {
    padding: 1.5rem;
}

.modules-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 1.5rem;
}

.module-item {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 1.5rem;
    border-radius: 12px;
    background: rgba(0,0,0,0.02);
    text-decoration: none;
    color: inherit;
    transition: all 0.3s ease;
    border: 1px solid rgba(0,0,0,0.05);
    position: relative;
}

.module-item:hover {
    background: rgba(52, 152, 219, 0.1);
    transform: translateY(-3px);
    box-shadow: 0 8px 25px rgba(0,0,0,0.1);
    text-decoration: none;
    color: inherit;
}

.module-icon {
    width: 60px;
    height: 60px;
    border-radius: 50%;
    background: linear-gradient(135deg, #3498db, #5dade2);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 1.5rem;
    flex-shrink: 0;
}

.module-content {
    flex: 1;
}

.module-title {
    font-weight: 600;
    color: #2c3e50;
    margin: 0 0 0.5rem 0;
}

.module-description {
    color: #7f8c8d;
    font-size: 0.9rem;
    margin: 0;
    line-height: 1.4;
}

.module-badge {
    margin-right: 1rem;
}

.module-arrow {
    color: #bdc3c7;
    font-size: 1.2rem;
    transition: all 0.3s ease;
}

.module-item:hover .module-arrow {
    color: #3498db;
    transform: translateX(5px);
}

/* Responsive */
@media (max-width: 768px) {
    .students-header {
        padding: 1.5rem;
    }
    
    .stat-card {
        padding: 1rem;
    }
    
    .stat-card-number {
        font-size: 1.5rem;
    }
    
    .modules-grid {
        grid-template-columns: 1fr;
    }
    
    .action-buttons {
        justify-content: center;
    }
    
    .module-item {
        flex-direction: column;
        text-align: center;
        padding: 2rem 1rem;
    }
    
    .module-badge {
        margin-right: 0;
        margin-bottom: 1rem;
    }
}

/* Animations */
@keyframes fadeInUp {
    from {
        opacity: 0;
        transform: translateY(30px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.stat-card, .modules-card {
    animation: fadeInUp 0.6s ease-out;
}

.stat-card:nth-child(1) { animation-delay: 0.1s; }
.stat-card:nth-child(2) { animation-delay: 0.2s; }
.stat-card:nth-child(3) { animation-delay: 0.3s; }
.stat-card:nth-child(4) { animation-delay: 0.4s; }

.module-item {
    animation: fadeInUp 0.6s ease-out;
}

.module-item:nth-child(1) { animation-delay: 0.1s; }
.module-item:nth-child(2) { animation-delay: 0.2s; }
.module-item:nth-child(3) { animation-delay: 0.3s; }
.module-item:nth-child(4) { animation-delay: 0.4s; }
.module-item:nth-child(5) { animation-delay: 0.5s; }
.module-item:nth-child(6) { animation-delay: 0.6s; }
.module-item:nth-child(7) { animation-delay: 0.7s; }
.module-item:nth-child(8) { animation-delay: 0.8s; }
</style>

<?php include '../../includes/footer.php'; ?>
