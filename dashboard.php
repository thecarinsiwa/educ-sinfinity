<?php
/**
 * Tableau de bord principal
 * Application de gestion scolaire - République Démocratique du Congo
 */

require_once 'config/config.php';
require_once 'config/database.php';
require_once 'includes/functions.php';
require_once 'includes/permissions.php';
require_once 'includes/sidebar-permissions.php';
require_once 'includes/sidebar-url-fixer.php';

// Vérifier l'authentification
requireLogin();

$page_title = 'Tableau de bord';

// Obtenir les statistiques générales
$stats = getGeneralStats();

// Obtenir l'année scolaire actuelle
$current_year = getCurrentAcademicYear();

// Obtenir la devise par défaut
$devise_par_defaut = getDefaultCurrency();

// Statistiques détaillées
$detailed_stats = [];

// Statistiques par niveau
$stats_niveaux = $database->query(
    "SELECT c.niveau, COUNT(DISTINCT e.id) as total
     FROM eleves e
     JOIN inscriptions i ON e.id = i.eleve_id
     JOIN classes c ON i.classe_id = c.id
     WHERE i.annee_scolaire_id = ? AND i.status IN ('inscrit', 'en_attente')
     GROUP BY c.niveau",
    [$current_year['id'] ?? 0]
)->fetchAll();

// Statistiques par sexe
$stats_sexe = $database->query(
    "SELECT e.sexe, COUNT(*) as total
     FROM eleves e
     JOIN inscriptions i ON e.id = i.eleve_id
     WHERE i.annee_scolaire_id = ? AND i.status IN ('inscrit', 'en_attente')
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

// Nouvelles inscriptions du mois
$mois_actuel = date('Y-m');
$nouvelles_inscriptions = $database->query(
    "SELECT COUNT(*) as total
     FROM inscriptions 
     WHERE DATE_FORMAT(date_inscription, '%Y-%m') = ?",
    [$mois_actuel]
)->fetch()['total'];

// Obtenir les dernières inscriptions
$recent_inscriptions = $database->query(
    "SELECT e.nom, e.prenom, e.sexe, c.nom as classe, c.niveau, i.created_at as date_inscription 
     FROM inscriptions i 
     JOIN eleves e ON i.eleve_id = e.id 
     JOIN classes c ON i.classe_id = c.id 
     WHERE i.annee_scolaire_id = ? AND i.status IN ('inscrit', 'en_attente')
     ORDER BY i.created_at DESC 
     LIMIT 8",
    [$current_year['id'] ?? 0]
)->fetchAll();

// Obtenir les paiements récents
$recent_payments = $database->query(
    "SELECT e.nom, e.prenom, p.montant, tf.nom as type_paiement, p.date_paiement, p.mode_paiement
     FROM paiements p
     JOIN eleves e ON p.eleve_id = e.id
     JOIN type_frais tf ON p.type_frais_id = tf.id
     WHERE p.annee_scolaire_id = ?
     ORDER BY p.date_paiement DESC
     LIMIT 8",
    [$current_year['id'] ?? 0]
)->fetchAll();

// Vérifier les comptes en attente d'activation (pour les admins)
$pending_users_count = 0;
$pending_users = [];
if (checkUserPermission('users', 'read') || checkPermission('admin')) {
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

// Obtenir l'utilisateur connecté
$current_user = getCurrentUser();

include 'includes/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">
        <i class="fas fa-tachometer-alt me-2"></i>
        Tableau de bord
    </h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <div class="btn-group me-2">
            <button type="button" class="btn btn-outline-secondary">
                <i class="fas fa-calendar-alt me-1"></i>
                <?php echo $current_year['annee'] ?? 'Aucune année active'; ?>
            </button>
        </div>
        <?php if ($devise_par_defaut): ?>
            <div class="btn-group me-2">
                <button type="button" class="btn btn-outline-info">
                    <i class="fas fa-exchange-alt me-1"></i>
                    Devise par défaut : <?php echo htmlspecialchars($devise_par_defaut['code']); ?> 
                    (<?php echo htmlspecialchars($devise_par_defaut['symbole']); ?>)
                </button>
            </div>
        <?php endif; ?>
        <div class="btn-group">
            <button type="button" class="btn btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">
                <i class="fas fa-tools me-1"></i>
                Actions rapides
            </button>
            <ul class="dropdown-menu">
                <?php if (checkUserPermission('students', 'create')): ?>
                    <li><a class="dropdown-item" href="modules/students/add.php">
                        <i class="fas fa-user-plus me-2"></i>Ajouter un élève
                    </a></li>
                <?php endif; ?>
                <?php if (checkUserPermission('academic', 'create')): ?>
                    <li><a class="dropdown-item" href="modules/academic/classes/add.php">
                        <i class="fas fa-school me-2"></i>Nouvelle classe
                    </a></li>
                <?php endif; ?>
                <?php if (checkUserPermission('finance', 'create')): ?>
                    <li><a class="dropdown-item" href="modules/finance/payments/add.php">
                        <i class="fas fa-money-bill me-2"></i>Nouveau paiement
                    </a></li>
                <?php endif; ?>
                <li><hr class="dropdown-divider"></li>
                <?php if (checkUserPermission('reports', 'read')): ?>
                    <li><a class="dropdown-item" href="modules/reports/">
                        <i class="fas fa-chart-bar me-2"></i>Rapports
                    </a></li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</div>

<!-- Statistiques rapides -->
<div class="row mb-4">
    <div class="col-md-3">
        <div class="card text-white bg-primary">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h4><?php echo number_format($stats['total_eleves']); ?></h4>
                        <p class="mb-0">Élèves inscrits</p>
                    </div>
                    <div class="align-self-center">
                        <i class="fas fa-users fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-white bg-success">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h4><?php echo number_format($stats['total_enseignants']); ?></h4>
                        <p class="mb-0">Enseignants</p>
                    </div>
                    <div class="align-self-center">
                        <i class="fas fa-chalkboard-teacher fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-white bg-warning">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h4><?php echo number_format($stats['total_classes']); ?></h4>
                        <p class="mb-0">Classes actives</p>
                    </div>
                    <div class="align-self-center">
                        <i class="fas fa-school fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-white bg-info">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h4><?php echo formatMoneyDefault($paiements_mois['total_mois'] ?? 0); ?></h4>
                        <p class="mb-0">Recettes ce mois</p>
                        <?php if ($devise_par_defaut): ?>
                            <small class="opacity-75">en <?php echo htmlspecialchars($devise_par_defaut['code']); ?></small>
                        <?php endif; ?>
                    </div>
                    <div class="align-self-center">
                        <i class="fas fa-money-bill-wave fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modules principaux -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-th-large me-2"></i>
                    Modules principaux
                </h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <?php if (checkUserPermission('students', 'read')): ?>
                        <div class="col-lg-3 col-md-6 mb-3">
                            <a href="modules/students/" class="text-decoration-none">
                                <div class="card h-100 border-0 shadow-sm hover-card">
                                    <div class="card-body text-center">
                                        <i class="fas fa-users fa-3x text-primary mb-3"></i>
                                        <h5 class="card-title">Élèves</h5>
                                        <p class="card-text text-muted">
                                            Gestion des élèves et inscriptions
                                        </p>
                                        <div class="mt-3">
                                            <span class="badge bg-primary"><?php echo number_format($stats['total_eleves']); ?> élèves</span>
                                        </div>
                                    </div>
                                </div>
                            </a>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (checkUserPermission('academic', 'read')): ?>
                        <div class="col-lg-3 col-md-6 mb-3">
                            <a href="modules/academic/" class="text-decoration-none">
                                <div class="card h-100 border-0 shadow-sm hover-card">
                                    <div class="card-body text-center">
                                        <i class="fas fa-book fa-3x text-success mb-3"></i>
                                        <h5 class="card-title">Académique</h5>
                                        <p class="card-text text-muted">
                                            Classes, matières et emplois du temps
                                        </p>
                                        <div class="mt-3">
                                            <span class="badge bg-success"><?php echo number_format($stats['total_classes']); ?> classes</span>
                                        </div>
                                    </div>
                                </div>
                            </a>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (checkUserPermission('finance', 'read')): ?>
                        <div class="col-lg-3 col-md-6 mb-3">
                            <a href="modules/finance/" class="text-decoration-none">
                                <div class="card h-100 border-0 shadow-sm hover-card">
                                    <div class="card-body text-center">
                                        <i class="fas fa-money-bill-wave fa-3x text-warning mb-3"></i>
                                        <h5 class="card-title">Finance</h5>
                                        <p class="card-text text-muted">
                                            Gestion financière et paiements
                                        </p>
                                        <div class="mt-3">
                                            <span class="badge bg-warning"><?php echo $paiements_mois['nb_paiements'] ?? 0; ?> paiements</span>
                                        </div>
                                    </div>
                                </div>
                            </a>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (checkUserPermission('users', 'read')): ?>
                        <div class="col-lg-3 col-md-6 mb-3">
                            <a href="modules/personnel/" class="text-decoration-none">
                                <div class="card h-100 border-0 shadow-sm hover-card">
                                    <div class="card-body text-center">
                                        <i class="fas fa-chalkboard-teacher fa-3x text-info mb-3"></i>
                                        <h5 class="card-title">Personnel</h5>
                                        <p class="card-text text-muted">
                                            Gestion du personnel enseignant
                                        </p>
                                        <div class="mt-3">
                                            <span class="badge bg-info"><?php echo number_format($stats['total_enseignants']); ?> enseignants</span>
                                        </div>
                                    </div>
                                </div>
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Répartition par niveau et activités récentes -->
<div class="row">
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-chart-pie me-2"></i>
                    Répartition par niveau
                </h5>
            </div>
            <div class="card-body">
                <?php if (!empty($stats_niveaux)): ?>
                    <canvas id="niveauxChart" width="100%" height="200"></canvas>
                    <div class="mt-3">
                        <?php foreach ($stats_niveaux as $niveau): ?>
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span class="badge bg-<?php 
                                    echo $niveau['niveau'] === 'maternelle' ? 'warning' : 
                                        ($niveau['niveau'] === 'primaire' ? 'success' : 'primary'); 
                                ?>">
                                    <?php echo ucfirst($niveau['niveau']); ?>
                                </span>
                                <span><?php echo $niveau['total']; ?> élève<?php echo $niveau['total'] > 1 ? 's' : ''; ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="text-center py-4">
                        <i class="fas fa-chart-pie fa-3x text-muted mb-3"></i>
                        <p class="text-muted">Aucun élève inscrit</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-clock me-2"></i>
                    Activités récentes
                </h5>
            </div>
            <div class="card-body">
                <?php if (!empty($recent_inscriptions) || !empty($recent_payments)): ?>
                    <div class="list-group list-group-flush">
                        <?php 
                        $activites = [];
                        foreach ($recent_inscriptions as $inscription) {
                            $activites[] = [
                                'type' => 'inscription',
                                'nom' => $inscription['nom'] . ' ' . $inscription['prenom'],
                                'detail' => $inscription['classe'],
                                'date' => $inscription['date_inscription']
                            ];
                        }
                        foreach ($recent_payments as $paiement) {
                            $activites[] = [
                                'type' => 'paiement',
                                'nom' => $paiement['nom'] . ' ' . $paiement['prenom'],
                                'detail' => formatMoneyDefault($paiement['montant']),
                                'date' => $paiement['date_paiement']
                            ];
                        }
                        // Trier par date
                        usort($activites, function($a, $b) {
                            return strtotime($b['date']) - strtotime($a['date']);
                        });
                        $activites = array_slice($activites, 0, 8);
                        ?>
                        
                        <?php foreach ($activites as $activite): ?>
                            <div class="list-group-item d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="mb-1"><?php echo htmlspecialchars($activite['nom']); ?></h6>
                                    <small class="text-muted">
                                        <?php if ($activite['type'] === 'inscription'): ?>
                                            <i class="fas fa-user-plus text-success me-1"></i>
                                            Inscription en <?php echo htmlspecialchars($activite['detail']); ?>
                                        <?php else: ?>
                                            <i class="fas fa-money-bill text-warning me-1"></i>
                                            Paiement de <?php echo $activite['detail']; ?>
                                        <?php endif; ?>
                                    </small>
                                </div>
                                <div>
                                    <small class="text-muted"><?php echo formatDate($activite['date']); ?></small>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="text-center py-4">
                        <i class="fas fa-clock fa-3x text-muted mb-3"></i>
                        <p class="text-muted">Aucune activité récente</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Actions rapides -->
<div class="row mt-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-bolt me-2"></i>
                    Actions rapides
                </h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <?php if (checkUserPermission('students', 'create')): ?>
                        <div class="col-md-3 mb-2">
                            <div class="d-grid">
                                <a href="modules/students/add.php" class="btn btn-outline-primary">
                                    <i class="fas fa-user-plus me-2"></i>
                                    Ajouter un élève
                                </a>
                            </div>
                        </div>
                    <?php endif; ?>
                    <?php if (checkUserPermission('academic', 'create')): ?>
                        <div class="col-md-3 mb-2">
                            <div class="d-grid">
                                <a href="modules/academic/classes/add.php" class="btn btn-outline-success">
                                    <i class="fas fa-school me-2"></i>
                                    Nouvelle classe
                                </a>
                            </div>
                        </div>
                    <?php endif; ?>
                    <?php if (checkUserPermission('finance', 'create')): ?>
                        <div class="col-md-3 mb-2">
                            <div class="d-grid">
                                <a href="modules/finance/payments/add.php" class="btn btn-outline-warning">
                                    <i class="fas fa-money-bill me-2"></i>
                                    Nouveau paiement
                                </a>
                            </div>
                        </div>
                    <?php endif; ?>
                    <?php if (checkUserPermission('reports', 'read')): ?>
                        <div class="col-md-3 mb-2">
                            <div class="d-grid">
                                <a href="modules/reports/" class="btn btn-outline-info">
                                    <i class="fas fa-chart-bar me-2"></i>
                                    Rapports
                                </a>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Alertes pour les administrateurs -->
<?php if ((checkUserPermission('users', 'read') || checkPermission('admin')) && $pending_users_count > 0): ?>
<div class="row mt-4">
    <div class="col-12">
        <div class="card border-warning">
            <div class="card-header bg-warning text-dark">
                <h5 class="mb-0">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    Comptes en attente d'activation
                </h5>
            </div>
            <div class="card-body">
                <p class="mb-3"><?php echo $pending_users_count; ?> compte(s) utilisateur en attente d'activation.</p>
                <div class="list-group list-group-flush">
                    <?php foreach ($pending_users as $user): ?>
                        <div class="list-group-item d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="mb-1"><?php echo htmlspecialchars($user['nom'] . ' ' . $user['prenom']); ?></h6>
                                <small class="text-muted"><?php echo htmlspecialchars($user['username']); ?></small>
                            </div>
                            <div>
                                <a href="admin/users/view.php?id=<?php echo $user['id']; ?>" 
                                   class="btn btn-sm btn-outline-primary">
                                    <i class="fas fa-eye"></i>
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div class="text-center mt-3">
                    <a href="admin/users/" class="btn btn-warning">
                        <i class="fas fa-users-cog me-1"></i>
                        Gérer les utilisateurs
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<style>
.hover-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.1) !important;
    transition: all 0.3s ease;
}
</style>

<script>
// Graphique de répartition par niveau
<?php if (!empty($stats_niveaux)): ?>
const niveauxCtx = document.getElementById('niveauxChart').getContext('2d');
const niveauxChart = new Chart(niveauxCtx, {
    type: 'doughnut',
    data: {
        labels: [<?php echo implode(',', array_map(function($n) { return "'" . ucfirst($n['niveau']) . "'"; }, $stats_niveaux)); ?>],
        datasets: [{
            data: [<?php echo implode(',', array_column($stats_niveaux, 'total')); ?>],
            backgroundColor: ['#f39c12', '#27ae60', '#3498db', '#9b59b6'],
            borderWidth: 0
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: 'bottom'
            }
        }
    }
});
<?php endif; ?>
</script>

<?php include 'includes/footer.php'; ?>
