<?php
/**
 * Module de gestion des admissions
 * Application de gestion scolaire - République Démocratique du Congo
 */

require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../includes/functions.php';

// Vérifier l'authentification et les permissions
requireLogin();
if (!checkPermission('admissions')) {
    showMessage('error', 'Accès refusé à cette fonctionnalité.');
    redirectTo('../../index.php');
}

$page_title = 'Gestion des Admissions';

// Obtenir les statistiques des demandes d'admission
$stats = [
    'total' => $database->query("SELECT COUNT(*) as total FROM demandes_admission")->fetch()['total'],
    'en_cours' => $database->query("SELECT COUNT(*) as total FROM demandes_admission WHERE status = 'en_cours_traitement'")->fetch()['total'],
    'acceptees' => $database->query("SELECT COUNT(*) as total FROM demandes_admission WHERE status = 'acceptee'")->fetch()['total'],
    'refusees' => $database->query("SELECT COUNT(*) as total FROM demandes_admission WHERE status = 'refusee'")->fetch()['total'],
    'en_attente' => $database->query("SELECT COUNT(*) as total FROM demandes_admission WHERE status = 'en_attente'")->fetch()['total']
];

// Obtenir les dernières demandes
$recent_applications = $database->query(
    "SELECT da.*, c.nom as classe_nom, c.niveau as classe_niveau 
     FROM demandes_admission da 
     LEFT JOIN classes c ON da.classe_demandee_id = c.id 
     ORDER BY da.created_at DESC 
     LIMIT 10"
)->fetchAll();

include '../../includes/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">
        <i class="fas fa-graduation-cap me-2"></i>
        Gestion des Admissions
    </h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <a href="../students/add.php" class="btn btn-primary">
            <i class="fas fa-user-plus me-1"></i>
            Nouvelle Demande
        </a>
    </div>
</div>

<!-- Statistiques -->
<div class="row mb-4">
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-left-primary shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                            Total Demandes
                        </div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $stats['total']; ?></div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-calendar fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-left-warning shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                            En Cours
                        </div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $stats['en_cours']; ?></div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-clock fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-left-success shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                            Acceptées
                        </div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $stats['acceptees']; ?></div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-check-circle fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-left-danger shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">
                            Refusées
                        </div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $stats['refusees']; ?></div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-times-circle fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Actions rapides -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-bolt me-2"></i>
                    Actions Rapides
                </h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-3 mb-3">
                        <a href="applications/list.php" class="btn btn-outline-primary w-100">
                            <i class="fas fa-list me-2"></i>
                            Voir Toutes les Demandes
                        </a>
                    </div>
                    <div class="col-md-3 mb-3">
                        <a href="applications/pending.php" class="btn btn-outline-warning w-100">
                            <i class="fas fa-clock me-2"></i>
                            Demandes en Attente
                        </a>
                    </div>
                    <div class="col-md-3 mb-3">
                        <a href="evaluations/index.php" class="btn btn-outline-info w-100">
                            <i class="fas fa-clipboard-check me-2"></i>
                            Évaluations
                        </a>
                    </div>
                    <div class="col-md-3 mb-3">
                        <a href="reports/index.php" class="btn btn-outline-secondary w-100">
                            <i class="fas fa-chart-bar me-2"></i>
                            Rapports
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Dernières demandes -->
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-history me-2"></i>
                    Dernières Demandes d'Admission
                </h5>
            </div>
            <div class="card-body">
                <?php if (empty($recent_applications)): ?>
                    <div class="text-center py-4">
                        <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                        <p class="text-muted">Aucune demande d'admission trouvée.</p>
                        <a href="../students/add.php" class="btn btn-primary">
                            <i class="fas fa-plus me-1"></i>
                            Créer la première demande
                        </a>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>N° Demande</th>
                                    <th>Élève</th>
                                    <th>Classe</th>
                                    <th>Statut</th>
                                    <th>Date</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent_applications as $app): ?>
                                    <tr>
                                        <td>
                                            <span class="badge bg-primary"><?php echo htmlspecialchars($app['numero_demande']); ?></span>
                                        </td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($app['nom_eleve'] . ' ' . $app['prenom_eleve']); ?></strong>
                                        </td>
                                        <td>
                                            <?php if ($app['classe_nom']): ?>
                                                <?php echo htmlspecialchars($app['classe_nom'] . ' (' . $app['classe_niveau'] . ')'); ?>
                                            <?php else: ?>
                                                <span class="text-muted">Non spécifiée</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php
                                            $status_class = '';
                                            $status_text = '';
                                            switch ($app['status']) {
                                                case 'en_cours_traitement':
                                                    $status_class = 'bg-warning';
                                                    $status_text = 'En cours';
                                                    break;
                                                case 'acceptee':
                                                    $status_class = 'bg-success';
                                                    $status_text = 'Acceptée';
                                                    break;
                                                case 'refusee':
                                                    $status_class = 'bg-danger';
                                                    $status_text = 'Refusée';
                                                    break;
                                                case 'en_attente':
                                                    $status_class = 'bg-info';
                                                    $status_text = 'En attente';
                                                    break;
                                                default:
                                                    $status_class = 'bg-secondary';
                                                    $status_text = ucfirst($app['status']);
                                            }
                                            ?>
                                            <span class="badge <?php echo $status_class; ?>">
                                                <?php echo $status_text; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php echo date('d/m/Y', strtotime($app['created_at'])); ?>
                                        </td>
                                        <td>
                                            <a href="applications/view.php?id=<?php echo $app['id']; ?>" 
                                               class="btn btn-sm btn-outline-primary" 
                                               title="Voir les détails">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <a href="applications/edit.php?id=<?php echo $app['id']; ?>" 
                                               class="btn btn-sm btn-outline-warning" 
                                               title="Modifier">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="text-center mt-3">
                        <a href="applications/list.php" class="btn btn-outline-primary">
                            <i class="fas fa-list me-1"></i>
                            Voir toutes les demandes
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>
