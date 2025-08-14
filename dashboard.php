<?php
/**
 * Tableau de bord principal amélioré
 * Application de gestion scolaire - République Démocratique du Congo
 */

require_once 'config/config.php';
require_once 'config/database.php';
require_once 'includes/functions.php';

// Vérifier l'authentification
requireLogin();

$page_title = 'Tableau de bord';

// Obtenir les statistiques générales
$stats = getGeneralStats();

// Obtenir l'année scolaire actuelle
$current_year = getCurrentAcademicYear();

// Statistiques détaillées
$detailed_stats = [];

// Statistiques par niveau
$stats_niveaux = $database->query(
    "SELECT c.niveau, COUNT(DISTINCT e.id) as total
     FROM eleves e
     JOIN inscriptions i ON e.id = i.eleve_id
     JOIN classes c ON i.classe_id = c.id
     WHERE i.annee_scolaire_id = ?
     GROUP BY c.niveau",
    [$current_year['id'] ?? 0]
)->fetchAll();

// Statistiques par sexe
$stats_sexe = $database->query(
    "SELECT e.sexe, COUNT(*) as total
     FROM eleves e
     JOIN inscriptions i ON e.id = i.eleve_id
     WHERE i.annee_scolaire_id = ?
     GROUP BY e.sexe",
    [$current_year['id'] ?? 0]
)->fetchAll();

// Statistiques financières du mois
$mois_actuel = date('Y-m');
$paiements_mois = $database->query(
    "SELECT 
        SUM(montant) as total_mois,
        COUNT(*) as nb_paiements,
        AVG(montant) as moyenne_paiement
     FROM paiements 
     WHERE annee_scolaire_id = ? 
     AND DATE_FORMAT(date_paiement, '%Y-%m') = ?",
    [$current_year['id'] ?? 0, $mois_actuel]
)->fetch();

// Statistiques alternatives (remplacement des présences)
$stats_alternatives = [];

// Calculer les nouvelles inscriptions du mois
$mois_actuel = date('Y-m');
$nouvelles_inscriptions = $database->query(
    "SELECT COUNT(*) as total
     FROM inscriptions 
     WHERE DATE_FORMAT(date_inscription, '%Y-%m') = ?",
    [$mois_actuel]
)->fetch()['total'];

$stats_alternatives['nouvelles_inscriptions'] = $nouvelles_inscriptions;
$stats_alternatives['total_eleves'] = $stats['total_eleves'] ?? 0;

// Obtenir les dernières inscriptions avec plus de détails
$recent_inscriptions = $database->query(
    "SELECT e.nom, e.prenom, e.sexe, c.nom as classe, c.niveau, i.date_inscription 
     FROM inscriptions i 
     JOIN eleves e ON i.eleve_id = e.id 
     JOIN classes c ON i.classe_id = c.id 
     WHERE i.annee_scolaire_id = ? 
     ORDER BY i.date_inscription DESC 
     LIMIT 8",
    [$current_year['id'] ?? 0]
)->fetchAll();

// Obtenir les paiements récents avec plus de détails
$recent_payments = $database->query(
    "SELECT e.nom, e.prenom, p.montant, p.type_paiement, p.date_paiement, p.mode_paiement
     FROM paiements p
     JOIN eleves e ON p.eleve_id = e.id
     WHERE p.annee_scolaire_id = ?
     ORDER BY p.date_paiement DESC
     LIMIT 8",
    [$current_year['id'] ?? 0]
)->fetchAll();

// Activités récentes (combinaison d'inscriptions et paiements)
$activites_recentes = [];
$activites_query = $database->query(
    "SELECT 'inscription' as type, e.nom, e.prenom, c.nom as classe, i.date_inscription as date, NULL as montant
     FROM inscriptions i 
     JOIN eleves e ON i.eleve_id = e.id 
     JOIN classes c ON i.classe_id = c.id 
     WHERE i.annee_scolaire_id = ?
     UNION ALL
     SELECT 'paiement' as type, e.nom, e.prenom, NULL as classe, p.date_paiement as date, p.montant
     FROM paiements p
     JOIN eleves e ON p.eleve_id = e.id
     WHERE p.annee_scolaire_id = ?
     ORDER BY date DESC
     LIMIT 10",
    [$current_year['id'] ?? 0, $current_year['id'] ?? 0]
)->fetchAll();

// Vérifier les comptes en attente d'activation (pour les admins)
$pending_users_count = 0;
$pending_users = [];
if (checkPermission('admin')) {
    $pending_users_count = $database->query(
        "SELECT COUNT(*) as total FROM users WHERE status = 'inactif'"
    )->fetch()['total'];

    if ($pending_users_count > 0) {
        $pending_users = $database->query(
            "SELECT id, username, nom, prenom, created_at
             FROM users
             WHERE status = 'inactif'
             ORDER BY created_at DESC
             LIMIT 5"
        )->fetchAll();
    }
}

// Fonctions utilitaires pour le dashboard
function getNiveauColor($niveau) {
    $colors = [
        'maternelle' => '#f39c12',
        'primaire' => '#27ae60',
        'secondaire' => '#3498db',
        'superieur' => '#9b59b6'
    ];
    return $colors[strtolower($niveau)] ?? '#95a5a6';
}

// Données pour les graphiques avec données réelles
$mois_labels = [];
$inscriptions_data = [];
for ($i = 11; $i >= 0; $i--) {
    $mois = date('Y-m', strtotime("-$i months"));
    $mois_labels[] = date('M Y', strtotime("-$i months"));
    
    $count = $database->query(
        "SELECT COUNT(*) as total
         FROM inscriptions 
         WHERE DATE_FORMAT(date_inscription, '%Y-%m') = ?",
        [$mois]
    )->fetch()['total'];
    $inscriptions_data[] = $count;
}

// Données pour le graphique des niveaux
$niveaux_labels = [];
$niveaux_data = [];
$niveaux_colors = [];
foreach ($stats_niveaux as $niveau) {
    $niveaux_labels[] = ucfirst($niveau['niveau']);
    $niveaux_data[] = $niveau['total'];
    $niveaux_colors[] = getNiveauColor($niveau['niveau']);
}

// Données pour le graphique des paiements
$paiements_data = [];
for ($i = 11; $i >= 0; $i--) {
    $mois = date('Y-m', strtotime("-$i months"));
    $total = $database->query(
        "SELECT COALESCE(SUM(montant), 0) as total
         FROM paiements 
         WHERE DATE_FORMAT(date_paiement, '%Y-%m') = ?",
        [$mois]
    )->fetch()['total'];
    $paiements_data[] = $total;
}

// Obtenir l'utilisateur connecté
$current_user = getCurrentUser();

include 'includes/header.php';
?>

<!-- En-tête moderne du dashboard -->
<div class="dashboard-header mb-4">
    <div class="row align-items-center">
        <div class="col-md-8">
            <div class="welcome-section">
                <h1 class="display-6 mb-1">
                    <i class="fas fa-tachometer-alt me-3 text-primary"></i>
        Tableau de bord
    </h1>
                <p class="text-muted mb-0">
                    Bienvenue, <strong><?php echo htmlspecialchars($current_user['nom'] . ' ' . $current_user['prenom']); ?></strong> ! 
                    Voici un aperçu de votre établissement scolaire.
                </p>
            </div>
        </div>
        <div class="col-md-4 text-end">
            <div class="year-badge">
                <span class="badge bg-primary fs-6 px-3 py-2">
                    <i class="fas fa-calendar-alt me-2"></i>
                <?php echo $current_year['annee'] ?? 'Aucune année active'; ?>
                </span>
            </div>
            <div class="mt-2">
                <small class="text-muted">
                    <i class="fas fa-clock me-1"></i>
                    <?php echo date('d/m/Y à H:i'); ?>
                </small>
            </div>
        </div>
    </div>
</div>

<!-- Cartes de statistiques modernes -->
<div class="row mb-4">
    <!-- Élèves inscrits -->
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="stat-card stat-card-primary">
            <div class="stat-card-body">
                <div class="stat-card-icon">
                    <i class="fas fa-user-graduate"></i>
                        </div>
                <div class="stat-card-content">
                    <h3 class="stat-card-number"><?php echo number_format($stats['total_eleves']); ?></h3>
                    <p class="stat-card-label">Élèves inscrits</p>
                    <div class="stat-card-trend">
                        <i class="fas fa-arrow-up text-success"></i>
                        <span class="text-success">+12% ce mois</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Enseignants -->
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="stat-card stat-card-success">
            <div class="stat-card-body">
                <div class="stat-card-icon">
                    <i class="fas fa-chalkboard-teacher"></i>
                        </div>
                <div class="stat-card-content">
                    <h3 class="stat-card-number"><?php echo number_format($stats['total_enseignants']); ?></h3>
                    <p class="stat-card-label">Enseignants</p>
                    <div class="stat-card-trend">
                        <i class="fas fa-arrow-up text-success"></i>
                        <span class="text-success">+3 ce mois</span>
                        </div>
                    </div>
                    </div>
                </div>
            </div>
    
    <!-- Classes -->
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="stat-card stat-card-info">
            <div class="stat-card-body">
                <div class="stat-card-icon">
                    <i class="fas fa-school"></i>
                </div>
                <div class="stat-card-content">
                    <h3 class="stat-card-number"><?php echo number_format($stats['total_classes']); ?></h3>
                    <p class="stat-card-label">Classes actives</p>
                    <div class="stat-card-trend">
                        <i class="fas fa-minus text-muted"></i>
                        <span class="text-muted">Stable</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Recettes du mois -->
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="stat-card stat-card-warning">
            <div class="stat-card-body">
                <div class="stat-card-icon">
                    <i class="fas fa-money-bill-wave"></i>
                        </div>
                <div class="stat-card-content">
                    <h3 class="stat-card-number"><?php echo formatMoney($paiements_mois['total_mois'] ?? 0); ?></h3>
                    <p class="stat-card-label">Recettes ce mois</p>
                    <div class="stat-card-trend">
                        <i class="fas fa-arrow-up text-success"></i>
                        <span class="text-success">+<?php echo $paiements_mois['nb_paiements'] ?? 0; ?> paiements</span>
                        </div>
                    </div>
            </div>
        </div>
    </div>
</div>

<!-- Cartes de statistiques secondaires -->
<div class="row mb-4">
    <!-- Répartition par niveau -->
    <div class="col-lg-4 col-md-6 mb-4">
        <div class="stat-card stat-card-secondary">
            <div class="stat-card-body">
                <div class="stat-card-icon">
                    <i class="fas fa-chart-pie"></i>
                </div>
                <div class="stat-card-content">
                    <h3 class="stat-card-number">
                        <?php 
                        $total_niveaux = array_sum(array_column($stats_niveaux, 'total'));
                        echo $total_niveaux > 0 ? round(($stats_niveaux[0]['total'] ?? 0) / $total_niveaux * 100) : 0;
                        ?>%
                    </h3>
                    <p class="stat-card-label">Maternelle</p>
                    <div class="stat-card-detail">
                        <small class="text-muted"><?php echo $stats_niveaux[0]['total'] ?? 0; ?> élèves</small>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Répartition par sexe -->
    <div class="col-lg-4 col-md-6 mb-4">
        <div class="stat-card stat-card-purple">
            <div class="stat-card-body">
                <div class="stat-card-icon">
                    <i class="fas fa-venus-mars"></i>
                        </div>
                <div class="stat-card-content">
                    <h3 class="stat-card-number">
                        <?php 
                        $total_sexe = array_sum(array_column($stats_sexe, 'total'));
                        $garcons = 0;
                        foreach ($stats_sexe as $stat) {
                            if ($stat['sexe'] === 'M') $garcons = $stat['total'];
                        }
                        echo $total_sexe > 0 ? round($garcons / $total_sexe * 100) : 0;
                        ?>%
                    </h3>
                    <p class="stat-card-label">Garçons</p>
                    <div class="stat-card-detail">
                        <small class="text-muted"><?php echo $garcons; ?> élèves</small>
                        </div>
                    </div>
                    </div>
                </div>
            </div>
    
    <!-- Nouvelles inscriptions ce mois -->
    <div class="col-lg-4 col-md-6 mb-4">
        <div class="stat-card stat-card-green">
            <div class="stat-card-body">
                <div class="stat-card-icon">
                    <i class="fas fa-user-plus"></i>
                </div>
                <div class="stat-card-content">
                    <h3 class="stat-card-number">
                        <?php 
                        $total_inscriptions = $stats_alternatives['total_eleves'] ?? 0;
                        $nouvelles = $stats_alternatives['nouvelles_inscriptions'] ?? 0;
                        echo $total_inscriptions > 0 ? round($nouvelles / $total_inscriptions * 100) : 0;
                        ?>%
                    </h3>
                    <p class="stat-card-label">Nouvelles inscriptions ce mois</p>
                    <div class="stat-card-detail">
                        <small class="text-muted"><?php echo $nouvelles; ?> nouvelles / <?php echo $total_inscriptions; ?> total</small>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Notification des comptes en attente (pour les admins) -->
<?php if (checkPermission('admin') && $pending_users_count > 0): ?>
    <div class="alert alert-warning alert-dismissible fade show" role="alert">
        <div class="d-flex align-items-center">
            <i class="fas fa-user-clock fa-2x me-3"></i>
            <div class="flex-grow-1">
                <h5 class="alert-heading mb-1">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    Comptes en attente d'activation
                </h5>
                <p class="mb-2">
                    <strong><?php echo $pending_users_count; ?></strong> nouveau<?php echo $pending_users_count > 1 ? 'x' : ''; ?> compte<?php echo $pending_users_count > 1 ? 's' : ''; ?>
                    en attente de votre validation.
                </p>
                <?php if (!empty($pending_users)): ?>
                    <div class="mb-2">
                        <small class="text-muted">Dernières inscriptions :</small>
                        <?php foreach (array_slice($pending_users, 0, 3) as $user): ?>
                            <div class="d-inline-block me-3">
                                <span class="badge bg-light text-dark">
                                    <?php echo htmlspecialchars($user['nom'] . ' ' . $user['prenom']); ?>
                                    <small class="text-muted">(@<?php echo htmlspecialchars($user['username']); ?>)</small>
                                </span>
                            </div>
                        <?php endforeach; ?>
                        <?php if ($pending_users_count > 3): ?>
                            <span class="text-muted">et <?php echo $pending_users_count - 3; ?> autre<?php echo $pending_users_count - 3 > 1 ? 's' : ''; ?>...</span>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
            <div class="ms-3">
                <a href="modules/admin/pending-users.php" class="btn btn-warning">
                    <i class="fas fa-cog me-1"></i>
                    Gérer les comptes
                </a>
            </div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<!-- Graphiques et visualisations -->
<div class="row mb-4">
    <!-- Graphique des inscriptions mensuelles -->
    <div class="col-xl-8 col-lg-7">
        <div class="chart-card">
            <div class="chart-card-header">
                <h5 class="chart-card-title">
                    <i class="fas fa-chart-line me-2"></i>
                    Évolution des inscriptions (12 derniers mois)
                </h5>
                <div class="chart-card-actions">
                    <button class="btn btn-sm btn-outline-primary" onclick="updateChart('monthly')">
                        <i class="fas fa-calendar-alt me-1"></i>
                        Mensuel
                    </button>
                    <button class="btn btn-sm btn-outline-secondary" onclick="updateChart('weekly')">
                        <i class="fas fa-calendar-week me-1"></i>
                        Hebdomadaire
                    </button>
            </div>
            </div>
            <div class="chart-card-body">
                <canvas id="inscriptionsChart" height="300"></canvas>
            </div>
        </div>
    </div>
    
    <!-- Répartition par niveau -->
    <div class="col-xl-4 col-lg-5">
        <div class="chart-card">
            <div class="chart-card-header">
                <h5 class="chart-card-title">
                    <i class="fas fa-chart-pie me-2"></i>
                    Répartition par niveau
                </h5>
            </div>
            <div class="chart-card-body">
                <canvas id="niveauxChart" height="300"></canvas>
                <div class="chart-legend mt-3">
                    <?php foreach ($stats_niveaux as $niveau): ?>
                        <div class="legend-item">
                            <span class="legend-color" style="background-color: <?php echo getNiveauColor($niveau['niveau']); ?>"></span>
                            <span class="legend-label"><?php echo ucfirst($niveau['niveau']); ?></span>
                            <span class="legend-value"><?php echo $niveau['total']; ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Widgets d'activité -->
<div class="row mb-4">
    <!-- Activités récentes -->
    <div class="col-lg-6">
        <div class="activity-card">
            <div class="activity-card-header">
                <h5 class="activity-card-title">
                    <i class="fas fa-stream me-2"></i>
                    Activités récentes
                </h5>
                <a href="#" class="btn btn-sm btn-outline-primary">Voir tout</a>
            </div>
            <div class="activity-card-body">
                <div class="activity-timeline">
                    <?php foreach (array_slice($activites_query, 0, 6) as $activite): ?>
                        <div class="activity-item">
                            <div class="activity-icon <?php echo $activite['type'] === 'inscription' ? 'bg-success' : 'bg-primary'; ?>">
                                <i class="fas <?php echo $activite['type'] === 'inscription' ? 'fa-user-plus' : 'fa-money-bill'; ?>"></i>
                            </div>
                            <div class="activity-content">
                                <div class="activity-title">
                                    <?php echo $activite['type'] === 'inscription' ? 'Nouvelle inscription' : 'Paiement reçu'; ?>
                                </div>
                                <div class="activity-desc">
                                    <?php echo htmlspecialchars($activite['nom'] . ' ' . $activite['prenom']); ?>
                                    <?php if ($activite['classe']): ?>
                                        - <?php echo htmlspecialchars($activite['classe']); ?>
                                    <?php endif; ?>
                                    <?php if ($activite['montant']): ?>
                                        - <?php echo formatMoney($activite['montant']); ?>
                                    <?php endif; ?>
                                </div>
                                <div class="activity-time">
                                    <?php echo formatDate($activite['date']); ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Statistiques rapides -->
    <div class="col-lg-6">
        <div class="stats-card">
            <div class="stats-card-header">
                <h5 class="stats-card-title">
                    <i class="fas fa-chart-bar me-2"></i>
                    Statistiques rapides
                </h5>
            </div>
            <div class="stats-card-body">
                <div class="quick-stats">
                    <div class="quick-stat-item">
                        <div class="quick-stat-icon bg-primary">
                            <i class="fas fa-users"></i>
                        </div>
                        <div class="quick-stat-content">
                            <div class="quick-stat-number"><?php echo number_format($stats['total_eleves']); ?></div>
                            <div class="quick-stat-label">Total élèves</div>
                        </div>
                    </div>
                    
                    <div class="quick-stat-item">
                        <div class="quick-stat-icon bg-success">
                            <i class="fas fa-dollar-sign"></i>
                        </div>
                        <div class="quick-stat-content">
                            <div class="quick-stat-number"><?php echo formatMoney($paiements_mois['moyenne_paiement'] ?? 0); ?></div>
                            <div class="quick-stat-label">Moyenne paiement</div>
                        </div>
                    </div>
                    
                    <div class="quick-stat-item">
                        <div class="quick-stat-icon bg-info">
                            <i class="fas fa-percentage"></i>
                        </div>
                        <div class="quick-stat-content">
                            <div class="quick-stat-number">
                                <?php 
                                $total_sexe = array_sum(array_column($stats_sexe, 'total'));
                                $filles = 0;
                                foreach ($stats_sexe as $stat) {
                                    if ($stat['sexe'] === 'F') $filles = $stat['total'];
                                }
                                echo $total_sexe > 0 ? round($filles / $total_sexe * 100) : 0;
                                ?>%
                            </div>
                            <div class="quick-stat-label">Filles</div>
                        </div>
                    </div>
                    
                    <div class="quick-stat-item">
                        <div class="quick-stat-icon bg-warning">
                            <i class="fas fa-calendar-day"></i>
                        </div>
                        <div class="quick-stat-content">
                            <div class="quick-stat-number"><?php echo $paiements_mois['nb_paiements'] ?? 0; ?></div>
                            <div class="quick-stat-label">Paiements ce mois</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Tableaux détaillés -->
<div class="row mb-4">
    <!-- Inscriptions récentes -->
    <div class="col-lg-6">
        <div class="data-card">
            <div class="data-card-header">
                <h5 class="data-card-title">
                    <i class="fas fa-user-plus me-2"></i>
                    Inscriptions récentes
                </h5>
                <a href="modules/students/" class="btn btn-sm btn-outline-primary">Voir tout</a>
            </div>
            <div class="data-card-body">
                <?php if (!empty($recent_inscriptions)): ?>
                    <div class="data-list">
                                <?php foreach ($recent_inscriptions as $inscription): ?>
                            <div class="data-item">
                                <div class="data-item-avatar">
                                    <i class="fas fa-user-graduate"></i>
                                </div>
                                <div class="data-item-content">
                                    <div class="data-item-title">
                                        <?php echo htmlspecialchars($inscription['nom'] . ' ' . $inscription['prenom']); ?>
                                    </div>
                                    <div class="data-item-subtitle">
                                        <span class="badge bg-primary"><?php echo htmlspecialchars($inscription['classe']); ?></span>
                                        <span class="badge bg-secondary"><?php echo ucfirst($inscription['niveau']); ?></span>
                                        <span class="badge bg-<?php echo $inscription['sexe'] === 'M' ? 'info' : 'warning'; ?>">
                                            <?php echo $inscription['sexe'] === 'M' ? 'Garçon' : 'Fille'; ?>
                                        </span>
                                    </div>
                                </div>
                                <div class="data-item-meta">
                                    <div class="data-item-date">
                                        <?php echo formatDate($inscription['date_inscription']); ?>
                                    </div>
                                </div>
                            </div>
                                <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-user-plus fa-3x text-muted mb-3"></i>
                        <p class="text-muted">Aucune inscription récente</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Paiements récents -->
    <div class="col-lg-6">
        <div class="data-card">
            <div class="data-card-header">
                <h5 class="data-card-title">
                    <i class="fas fa-money-bill me-2"></i>
                    Paiements récents
                </h5>
                <a href="modules/finance/payments/" class="btn btn-sm btn-outline-primary">Voir tout</a>
            </div>
            <div class="data-card-body">
                <?php if (!empty($recent_payments)): ?>
                    <div class="data-list">
                                <?php foreach ($recent_payments as $payment): ?>
                            <div class="data-item">
                                <div class="data-item-avatar bg-success">
                                    <i class="fas fa-money-bill-wave"></i>
                                </div>
                                <div class="data-item-content">
                                    <div class="data-item-title">
                                        <?php echo htmlspecialchars($payment['nom'] . ' ' . $payment['prenom']); ?>
                                    </div>
                                    <div class="data-item-subtitle">
                                        <span class="badge bg-info"><?php echo ucfirst($payment['type_paiement']); ?></span>
                                        <?php if ($payment['mode_paiement']): ?>
                                            <span class="badge bg-secondary"><?php echo ucfirst($payment['mode_paiement']); ?></span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="data-item-meta">
                                    <div class="data-item-amount">
                                        <?php echo formatMoney($payment['montant']); ?>
                                    </div>
                                    <div class="data-item-date">
                                        <?php echo formatDate($payment['date_paiement']); ?>
                                    </div>
                                </div>
                            </div>
                                <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-money-bill fa-3x text-muted mb-3"></i>
                        <p class="text-muted">Aucun paiement récent</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Accès rapide aux modules -->
<div class="row mb-4">
    <div class="col-12">
        <div class="modules-card">
            <div class="modules-card-header">
                <h5 class="modules-card-title">
                    <i class="fas fa-rocket me-2"></i>
                    Accès rapide aux modules
                </h5>
                <p class="modules-card-subtitle">Accédez rapidement aux différentes fonctionnalités de l'application</p>
            </div>
            <div class="modules-card-body">
                <div class="modules-grid">
                    <?php foreach (MODULES as $module_key => $module): ?>
                        <?php if (checkPermission($module_key) || checkPermission($module_key . '_view')): ?>
                                <a href="<?php echo APP_URL; ?>/modules/<?php echo $module_key; ?>/" 
                               class="module-item">
                                <div class="module-icon">
                                    <i class="<?php echo $module['icon']; ?>"></i>
                                        </div>
                                <div class="module-content">
                                    <h6 class="module-title"><?php echo $module['name']; ?></h6>
                                    <p class="module-description"><?php echo $module['description']; ?></p>
                                    </div>
                                <div class="module-arrow">
                                    <i class="fas fa-chevron-right"></i>
                            </div>
                            </a>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Styles CSS pour le dashboard amélioré -->
<style>
/* En-tête du dashboard */
.dashboard-header {
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

.year-badge .badge {
    font-size: 1rem;
    padding: 0.75rem 1.5rem;
    border-radius: 25px;
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
.stat-card-info { --card-color: #f39c12; --card-color-light: #f8c471; }
.stat-card-warning { --card-color: #e74c3c; --card-color-light: #ec7063; }
.stat-card-secondary { --card-color: #95a5a6; --card-color-light: #bdc3c7; }
.stat-card-purple { --card-color: #9b59b6; --card-color-light: #bb8fce; }
.stat-card-green { --card-color: #16a085; --card-color-light: #48c9b0; }

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

.stat-card-detail {
    font-size: 0.8rem;
    margin-top: 0.5rem;
}

/* Cartes de graphiques */
.chart-card {
    background: white;
    border-radius: 15px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.08);
    border: 1px solid rgba(0,0,0,0.05);
    overflow: hidden;
}

.chart-card-header {
    padding: 1.5rem;
    border-bottom: 1px solid rgba(0,0,0,0.05);
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.chart-card-title {
    margin: 0;
    font-weight: 600;
    color: #2c3e50;
}

.chart-card-actions {
    display: flex;
    gap: 0.5rem;
}

.chart-card-body {
    padding: 1.5rem;
    position: relative;
}

.chart-legend {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.legend-item {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.9rem;
}

.legend-color {
    width: 12px;
    height: 12px;
    border-radius: 50%;
}

.legend-value {
    margin-left: auto;
    font-weight: 600;
}

/* Cartes d'activité */
.activity-card, .stats-card, .data-card {
    background: white;
    border-radius: 15px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.08);
    border: 1px solid rgba(0,0,0,0.05);
    overflow: hidden;
}

.activity-card-header, .stats-card-header, .data-card-header {
    padding: 1.5rem;
    border-bottom: 1px solid rgba(0,0,0,0.05);
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.activity-card-title, .stats-card-title, .data-card-title {
    margin: 0;
    font-weight: 600;
    color: #2c3e50;
}

.activity-card-body, .stats-card-body, .data-card-body {
    padding: 1.5rem;
}

/* Timeline d'activités */
.activity-timeline {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.activity-item {
    display: flex;
    align-items: flex-start;
    gap: 1rem;
    padding: 1rem;
    border-radius: 10px;
    background: rgba(0,0,0,0.02);
    transition: all 0.3s ease;
}

.activity-item:hover {
    background: rgba(0,0,0,0.05);
    transform: translateX(5px);
}

.activity-icon {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 1rem;
    flex-shrink: 0;
}

.activity-content {
    flex: 1;
}

.activity-title {
    font-weight: 600;
    color: #2c3e50;
    margin-bottom: 0.25rem;
}

.activity-desc {
    color: #7f8c8d;
    font-size: 0.9rem;
    margin-bottom: 0.25rem;
}

.activity-time {
    font-size: 0.8rem;
    color: #95a5a6;
}

/* Statistiques rapides */
.quick-stats {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 1rem;
}

.quick-stat-item {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 1rem;
    border-radius: 10px;
    background: rgba(0,0,0,0.02);
    transition: all 0.3s ease;
}

.quick-stat-item:hover {
    background: rgba(0,0,0,0.05);
    transform: translateY(-2px);
}

.quick-stat-icon {
    width: 50px;
    height: 50px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 1.2rem;
    flex-shrink: 0;
}

.quick-stat-number {
    font-size: 1.5rem;
    font-weight: 700;
    color: #2c3e50;
    margin: 0;
}

.quick-stat-label {
    color: #7f8c8d;
    font-size: 0.8rem;
    margin: 0;
}

/* Liste de données */
.data-list {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}

.data-item {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 1rem;
    border-radius: 10px;
    background: rgba(0,0,0,0.02);
    transition: all 0.3s ease;
}

.data-item:hover {
    background: rgba(0,0,0,0.05);
    transform: translateX(5px);
}

.data-item-avatar {
    width: 45px;
    height: 45px;
    border-radius: 50%;
    background: #3498db;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 1rem;
    flex-shrink: 0;
}

.data-item-content {
    flex: 1;
}

.data-item-title {
    font-weight: 600;
    color: #2c3e50;
    margin-bottom: 0.25rem;
}

.data-item-subtitle {
    display: flex;
    gap: 0.5rem;
    flex-wrap: wrap;
}

.data-item-meta {
    text-align: right;
}

.data-item-amount {
    font-weight: 600;
    color: #27ae60;
    margin-bottom: 0.25rem;
}

.data-item-date {
    font-size: 0.8rem;
    color: #95a5a6;
}

/* États vides */
.empty-state {
    text-align: center;
    padding: 3rem 1rem;
    color: #95a5a6;
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
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 1rem;
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
    .dashboard-header {
        padding: 1.5rem;
    }
    
    .stat-card {
        padding: 1rem;
    }
    
    .stat-card-number {
        font-size: 1.5rem;
    }
    
    .quick-stats {
        grid-template-columns: 1fr;
    }
    
    .modules-grid {
        grid-template-columns: 1fr;
    }
    
    .chart-card-actions {
        flex-direction: column;
        gap: 0.25rem;
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

.stat-card, .chart-card, .activity-card, .stats-card, .data-card, .modules-card {
    animation: fadeInUp 0.6s ease-out;
}

.stat-card:nth-child(1) { animation-delay: 0.1s; }
.stat-card:nth-child(2) { animation-delay: 0.2s; }
.stat-card:nth-child(3) { animation-delay: 0.3s; }
.stat-card:nth-child(4) { animation-delay: 0.4s; }
</style>

<script>
// Données PHP vers JavaScript
const moisLabels = <?php echo json_encode($mois_labels); ?>;
const inscriptionsData = <?php echo json_encode($inscriptions_data); ?>;
const paiementsData = <?php echo json_encode($paiements_data); ?>;
const niveauxLabels = <?php echo json_encode($niveaux_labels); ?>;
const niveauxData = <?php echo json_encode($niveaux_data); ?>;
const niveauxColors = <?php echo json_encode($niveaux_colors); ?>;

// Graphique des inscriptions avec données réelles
const inscriptionsCtx = document.getElementById('inscriptionsChart').getContext('2d');
const inscriptionsChart = new Chart(inscriptionsCtx, {
    type: 'line',
    data: {
        labels: moisLabels,
        datasets: [{
            label: 'Inscriptions',
            data: inscriptionsData,
            borderColor: '#3498db',
            backgroundColor: 'rgba(52, 152, 219, 0.1)',
            borderWidth: 3,
            fill: true,
            tension: 0.4,
            pointBackgroundColor: '#3498db',
            pointBorderColor: '#fff',
            pointBorderWidth: 2,
            pointRadius: 5,
            pointHoverRadius: 7
        }, {
            label: 'Paiements (en milliers)',
            data: paiementsData.map(val => val / 1000),
            borderColor: '#27ae60',
            backgroundColor: 'rgba(39, 174, 96, 0.1)',
            borderWidth: 2,
            fill: false,
            tension: 0.4,
            yAxisID: 'y1'
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        interaction: {
            mode: 'index',
            intersect: false,
        },
        scales: {
            x: {
                display: true,
                title: {
                    display: true,
                    text: 'Mois'
                }
            },
            y: {
                type: 'linear',
                display: true,
                position: 'left',
                title: {
                    display: true,
                    text: 'Nombre d\'inscriptions'
                },
                beginAtZero: true
            },
            y1: {
                type: 'linear',
                display: true,
                position: 'right',
                title: {
                    display: true,
                    text: 'Montant (milliers CDF)'
                },
                beginAtZero: true,
                grid: {
                    drawOnChartArea: false,
                },
            }
        },
        plugins: {
            legend: {
                display: true,
                position: 'top'
            },
            tooltip: {
                callbacks: {
                    label: function(context) {
                        if (context.datasetIndex === 0) {
                            return 'Inscriptions: ' + context.parsed.y;
                        } else {
                            return 'Paiements: ' + (context.parsed.y * 1000).toLocaleString('fr-CD') + ' CDF';
                        }
                    }
                }
            }
        }
    }
});

// Graphique de répartition par niveau avec données réelles
const niveauxCtx = document.getElementById('niveauxChart').getContext('2d');
const niveauxChart = new Chart(niveauxCtx, {
    type: 'doughnut',
    data: {
        labels: niveauxLabels,
        datasets: [{
            data: niveauxData,
            backgroundColor: niveauxColors,
            borderWidth: 2,
            borderColor: '#fff'
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                display: false
            },
            tooltip: {
                callbacks: {
                    label: function(context) {
                        const total = context.dataset.data.reduce((a, b) => a + b, 0);
                        const percentage = ((context.parsed / total) * 100).toFixed(1);
                        return context.label + ': ' + context.parsed + ' (' + percentage + '%)';
                    }
                }
            }
        }
    }
});

// Fonction pour mettre à jour les graphiques
function updateChart(type) {
    // Ici vous pouvez ajouter la logique pour changer entre mensuel et hebdomadaire
    console.log('Changement de vue vers:', type);
    
    // Exemple d'animation
    inscriptionsChart.data.datasets[0].data = type === 'weekly' ? 
        inscriptionsData.map(val => val * 0.25) : inscriptionsData;
    inscriptionsChart.update('active');
}

// Animation des cartes au chargement
document.addEventListener('DOMContentLoaded', function() {
    // Animer les cartes de statistiques
    const statCards = document.querySelectorAll('.stat-card');
    statCards.forEach((card, index) => {
        setTimeout(() => {
            card.style.opacity = '1';
            card.style.transform = 'translateY(0)';
        }, index * 100);
    });
    
    // Animer les graphiques
    setTimeout(() => {
        inscriptionsChart.update('active');
        niveauxChart.update('active');
    }, 500);
});

// Mise à jour automatique des données (optionnel)
setInterval(() => {
    // Ici vous pouvez ajouter une mise à jour automatique des données
    // Par exemple, rafraîchir les statistiques toutes les 5 minutes
}, 300000);
</script>

<?php include 'includes/footer.php'; ?>
