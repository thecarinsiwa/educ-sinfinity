<?php
/**
 * Module de Suivi des Élèves - Tableau de bord principal
 * Application de gestion scolaire - République Démocratique du Congo
 */

require_once '../../../config/config.php';
require_once '../../../config/database.php';
require_once '../../../includes/functions.php';

// Vérifier l'authentification et les permissions
requireLogin();
if (!checkPermission('students') && !checkPermission('students_view')) {
    showMessage('error', 'Accès refusé à ce module.');
    redirectTo('../index.php');
}

$page_title = 'Suivi des Élèves';

// Obtenir l'année scolaire actuelle
$current_year = getCurrentAcademicYear();

// Statistiques générales du suivi
$stats = [];

// Demandes en cours de traitement
try {
    $stmt = $database->query(
        "SELECT COUNT(*) as total FROM demandes_admission WHERE status = 'en_cours_traitement' AND annee_scolaire_id = ?",
        [$current_year['id'] ?? 0]
    );
    $stats['en_cours_traitement'] = $stmt->fetch()['total'];
} catch (Exception $e) {
    $stats['en_cours_traitement'] = 0;
}

// Évaluations en attente
try {
    $stmt = $database->query(
        "SELECT COUNT(*) as total FROM demandes_admission WHERE status = 'en_attente' AND annee_scolaire_id = ?",
        [$current_year['id'] ?? 0]
    );
    $stats['evaluations_attente'] = $stmt->fetch()['total'];
} catch (Exception $e) {
    $stats['evaluations_attente'] = 0;
}

// Décisions en attente
try {
    $stmt = $database->query(
        "SELECT COUNT(*) as total FROM demandes_admission WHERE status = 'acceptee' AND annee_scolaire_id = ? AND eleve_cree_id IS NULL",
        [$current_year['id'] ?? 0]
    );
    $stats['decisions_attente'] = $stmt->fetch()['total'];
} catch (Exception $e) {
    $stats['decisions_attente'] = 0;
}

// Inscriptions finalisées
try {
    $stmt = $database->query(
        "SELECT COUNT(*) as total FROM demandes_admission WHERE status = 'inscrit' AND annee_scolaire_id = ?",
        [$current_year['id'] ?? 0]
    );
    $stats['inscriptions_finalisees'] = $stmt->fetch()['total'];
} catch (Exception $e) {
    $stats['inscriptions_finalisees'] = 0;
}

// Élèves actifs
try {
    $stmt = $database->query(
        "SELECT COUNT(*) as total FROM eleves WHERE status = 'actif'"
    );
    $stats['eleves_actifs'] = $stmt->fetch()['total'];
} catch (Exception $e) {
    $stats['eleves_actifs'] = 0;
}

// Transferts et sorties récents
try {
    $stmt = $database->query(
        "SELECT COUNT(*) as total FROM transferts_sorties WHERE date_effective >= DATE_SUB(NOW(), INTERVAL 30 DAY)"
    );
    $stats['transferts_recents'] = $stmt->fetch()['total'];
} catch (Exception $e) {
    $stats['transferts_recents'] = 0;
}

// Demandes récentes (7 derniers jours)
try {
    $demandes_recentes = $database->query(
        "SELECT da.*, c.nom as classe_demandee, c.niveau,
                CASE 
                    WHEN da.status = 'en_attente' THEN 'Évaluation en attente'
                    WHEN da.status = 'en_cours_traitement' THEN 'En cours de traitement'
                    WHEN da.status = 'acceptee' THEN 'Acceptée'
                    WHEN da.status = 'refusee' THEN 'Refusée'
                    WHEN da.status = 'inscrit' THEN 'Inscrit'
                    ELSE da.status
                END as status_lisible
         FROM demandes_admission da
         LEFT JOIN classes c ON da.classe_demandee_id = c.id
         WHERE da.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
         AND da.annee_scolaire_id = ?
         ORDER BY da.created_at DESC
         LIMIT 10",
        [$current_year['id'] ?? 0]
    )->fetchAll();
} catch (Exception $e) {
    $demandes_recentes = [];
}

// Élèves nécessitant une attention
try {
    $eleves_attention = $database->query(
        "SELECT e.*, c.nom as classe_nom, 
                (SELECT COUNT(*) FROM paiements p WHERE p.eleve_id = e.id AND p.type_paiement = 'mensualite' AND p.status = 'en_attente') as paiements_en_retard,
                (SELECT COUNT(*) FROM sanctions s WHERE s.eleve_id = e.id AND s.status = 'active') as sanctions_actives
         FROM eleves e
         LEFT JOIN inscriptions i ON e.id = i.eleve_id
         LEFT JOIN classes c ON i.classe_id = c.id
         WHERE e.status = 'actif'
         AND (i.annee_scolaire_id = ? OR i.annee_scolaire_id IS NULL)
         HAVING paiements_en_retard > 0 OR sanctions_actives > 0
         ORDER BY (paiements_en_retard + sanctions_actives) DESC
         LIMIT 10",
        [$current_year['id'] ?? 0]
    )->fetchAll();
} catch (Exception $e) {
    $eleves_attention = [];
}

// Progression par étape
try {
    $progression_etapes = $database->query(
        "SELECT ea.nom, ea.ordre,
                COUNT(sea.id) as total_demandes,
                SUM(CASE WHEN sea.status = 'terminee' THEN 1 ELSE 0 END) as terminees,
                SUM(CASE WHEN sea.status = 'en_cours' THEN 1 ELSE 0 END) as en_cours,
                SUM(CASE WHEN sea.status = 'en_attente' THEN 1 ELSE 0 END) as en_attente
         FROM etapes_admission ea
         LEFT JOIN suivi_etapes_admission sea ON ea.id = sea.etape_id
         LEFT JOIN demandes_admission da ON sea.demande_admission_id = da.id
         WHERE da.annee_scolaire_id = ? OR da.annee_scolaire_id IS NULL
         GROUP BY ea.id, ea.nom, ea.ordre
         ORDER BY ea.ordre",
        [$current_year['id'] ?? 0]
    )->fetchAll();
} catch (Exception $e) {
    $progression_etapes = [];
}

include '../../../includes/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">
        <i class="fas fa-user-check me-2"></i>
        Suivi des Élèves
    </h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <div class="btn-group me-2">
            <a href="../index.php" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-1"></i>
                Retour
            </a>
        </div>
        <div class="btn-group">
            <a href="../admissions/new-application.php" class="btn btn-primary">
                <i class="fas fa-plus me-1"></i>
                Nouvelle demande
            </a>
        </div>
    </div>
</div>

<?php displayMessage(); ?>

<div class="container-fluid">
    <!-- Statistiques générales -->
<div class="row mb-4">
    <div class="col-xl-3 col-md-6 mb-3">
        <div class="card text-white bg-primary">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h4><?php echo $stats['en_cours_traitement']; ?></h4>
                        <p class="mb-0">En cours de traitement</p>
                    </div>
                    <div class="align-self-center">
                        <i class="fas fa-clock fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-3 col-md-6 mb-3">
        <div class="card text-white bg-warning">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h4><?php echo $stats['evaluations_attente']; ?></h4>
                        <p class="mb-0">Évaluations en attente</p>
                    </div>
                    <div class="align-self-center">
                        <i class="fas fa-clipboard-check fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-3 col-md-6 mb-3">
        <div class="card text-white bg-info">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h4><?php echo $stats['decisions_attente']; ?></h4>
                        <p class="mb-0">Décisions en attente</p>
                    </div>
                    <div class="align-self-center">
                        <i class="fas fa-user-check fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-3 col-md-6 mb-3">
        <div class="card text-white bg-success">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h4><?php echo $stats['eleves_actifs']; ?></h4>
                        <p class="mb-0">Élèves actifs</p>
                    </div>
                    <div class="align-self-center">
                        <i class="fas fa-users fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Section principale -->
<div class="row mb-4">
    <!-- Progression par étapes -->
    <div class="col-lg-8 mb-4">
        <div class="card h-100">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-tasks me-2"></i>
                    Progression par Étapes
                </h5>
                <p class="text-muted mb-0">Suivi de l'avancement des demandes d'admission</p>
            </div>
            <div class="card-body">
                <?php if (!empty($progression_etapes)): ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead class="table-dark">
                                <tr>
                                    <th>Étape</th>
                                    <th>Total</th>
                                    <th>Terminées</th>
                                    <th>En cours</th>
                                    <th>En attente</th>
                                    <th>Progression</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($progression_etapes as $etape): ?>
                                    <?php 
                                    $total = $etape['total_demandes'];
                                    $terminees = $etape['terminees'];
                                    $en_cours = $etape['en_cours'];
                                    $en_attente = $etape['en_attente'];
                                    $progression = $total > 0 ? round(($terminees / $total) * 100) : 0;
                                    ?>
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div class="me-3">
                                                    <span class="badge bg-primary rounded-circle">
                                                        <?php echo $etape['ordre']; ?>
                                                    </span>
                                                </div>
                                                <div>
                                                    <strong><?php echo $etape['nom']; ?></strong>
                                                    <br><small class="text-muted">Étape <?php echo $etape['ordre']; ?></small>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="badge bg-light text-dark"><?php echo $total; ?></span>
                                        </td>
                                        <td>
                                            <span class="badge bg-success"><?php echo $terminees; ?></span>
                                        </td>
                                        <td>
                                            <span class="badge bg-warning"><?php echo $en_cours; ?></span>
                                        </td>
                                        <td>
                                            <span class="badge bg-secondary"><?php echo $en_attente; ?></span>
                                        </td>
                                        <td>
                                            <div class="progress" style="height: 20px;">
                                                <div class="progress-bar bg-success" role="progressbar" 
                                                     style="width: <?php echo $progression; ?>%" 
                                                     aria-valuenow="<?php echo $progression; ?>" 
                                                     aria-valuemin="0" aria-valuemax="100">
                                                    <?php echo $progression; ?>%
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="text-center py-4">
                        <i class="fas fa-info-circle text-muted" style="font-size: 48px;"></i>
                        <p class="text-muted mt-2">Aucune donnée de progression disponible</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Actions rapides -->
        <div class="col-lg-4 mb-4">
            <div class="card h-100">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-bolt me-2"></i>
                        Actions Rapides
                    </h5>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <a href="../admissions/new-application.php" class="btn btn-primary">
                            <i class="fas fa-plus me-2"></i>
                            Nouvelle demande d'admission
                        </a>
                        <a href="../admissions/evaluation/" class="btn btn-warning">
                            <i class="fas fa-clipboard-check me-2"></i>
                            Évaluer les candidatures
                        </a>
                        <a href="../admissions/enrollment/" class="btn btn-success">
                            <i class="fas fa-user-check me-2"></i>
                            Finaliser les inscriptions
                        </a>
                        <a href="evaluations/" class="btn btn-info">
                            <i class="fas fa-chart-line me-2"></i>
                            Gérer les évaluations
                        </a>
                        <a href="decisions/" class="btn btn-secondary">
                            <i class="fas fa-gavel me-2"></i>
                            Prendre les décisions
                        </a>
                        <a href="follow-up/" class="btn btn-dark">
                            <i class="fas fa-user-check me-2"></i>
                            Suivi scolaire
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Section des tableaux -->
<div class="row">
    <!-- Demandes récentes -->
    <div class="col-lg-6 mb-4">
        <div class="card h-100">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-clock me-2"></i>
                    Demandes Récentes (7 derniers jours)
                </h5>
            </div>
            <div class="card-body">
                <?php if (!empty($demandes_recentes)): ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead class="table-dark">
                                <tr>
                                    <th>Élève</th>
                                    <th>Classe</th>
                                    <th>Statut</th>
                                    <th>Date</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($demandes_recentes as $demande): ?>
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div class="me-3">
                                                    <span class="badge bg-primary rounded-circle">
                                                        <?php echo strtoupper(substr($demande['prenom_eleve'], 0, 1)); ?>
                                                    </span>
                                                </div>
                                                <div>
                                                    <strong><?php echo $demande['prenom_eleve'] . ' ' . $demande['nom_eleve']; ?></strong>
                                                    <br><small class="text-muted"><?php echo $demande['numero_demande']; ?></small>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="badge bg-light text-dark">
                                                <?php echo $demande['classe_demandee']; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php
                                            $status_class = '';
                                            switch ($demande['status']) {
                                                case 'en_attente':
                                                    $status_class = 'bg-warning';
                                                    break;
                                                case 'en_cours_traitement':
                                                    $status_class = 'bg-info';
                                                    break;
                                                case 'acceptee':
                                                    $status_class = 'bg-success';
                                                    break;
                                                case 'refusee':
                                                    $status_class = 'bg-danger';
                                                    break;
                                                case 'inscrit':
                                                    $status_class = 'bg-primary';
                                                    break;
                                                default:
                                                    $status_class = 'bg-secondary';
                                            }
                                            ?>
                                            <span class="badge <?php echo $status_class; ?>">
                                                <?php echo $demande['status_lisible']; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <small class="text-muted">
                                                <?php echo date('d/m/Y', strtotime($demande['created_at'])); ?>
                                            </small>
                                        </td>
                                        <td>
                                            <a href="../admissions/view.php?id=<?php echo $demande['id']; ?>" 
                                               class="btn btn-sm btn-outline-primary">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="text-center py-4">
                        <i class="fas fa-info-circle text-muted" style="font-size: 48px;"></i>
                        <p class="text-muted mt-2">Aucune nouvelle demande récente</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Élèves nécessitant une attention -->
        <div class="col-lg-6 mb-4">
            <div class="card h-100">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        Élèves Nécessitant une Attention
                    </h5>
                </div>
            <div class="card-body">
                <?php if (!empty($eleves_attention)): ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead class="table-dark">
                                <tr>
                                    <th>Élève</th>
                                    <th>Classe</th>
                                    <th>Paiements</th>
                                    <th>Sanctions</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($eleves_attention as $eleve): ?>
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div class="me-3">
                                                    <?php if ($eleve['photo']): ?>
                                                        <img src="../../../uploads/photos/<?php echo $eleve['photo']; ?>" 
                                                             class="rounded-circle" width="40" height="40" alt="Photo">
                                                    <?php else: ?>
                                                        <span class="badge bg-primary rounded-circle">
                                                            <?php echo strtoupper(substr($eleve['prenom'], 0, 1)); ?>
                                                        </span>
                                                    <?php endif; ?>
                                                </div>
                                                <div>
                                                    <strong><?php echo $eleve['prenom'] . ' ' . $eleve['nom']; ?></strong>
                                                    <br><small class="text-muted"><?php echo $eleve['numero_matricule']; ?></small>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="badge bg-light text-dark">
                                                <?php echo $eleve['classe_nom'] ?? 'Non assigné'; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($eleve['paiements_en_retard'] > 0): ?>
                                                <span class="badge bg-danger">
                                                    <?php echo $eleve['paiements_en_retard']; ?> en retard
                                                </span>
                                            <?php else: ?>
                                                <span class="badge bg-success">À jour</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($eleve['sanctions_actives'] > 0): ?>
                                                <span class="badge bg-warning">
                                                    <?php echo $eleve['sanctions_actives']; ?> active(s)
                                                </span>
                                            <?php else: ?>
                                                <span class="badge bg-success">Aucune</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <a href="../view.php?id=<?php echo $eleve['id']; ?>" 
                                               class="btn btn-sm btn-outline-primary">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="text-center py-4">
                        <i class="fas fa-check-circle text-success" style="font-size: 48px;"></i>
                        <p class="text-success mt-2">Tous les élèves sont à jour !</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialisation des tooltips
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });

    // Actualisation automatique des statistiques (toutes les 5 minutes)
    setInterval(function() {
        // Ici on pourrait ajouter une requête AJAX pour actualiser les stats
        console.log('Actualisation des statistiques...');
    }, 300000);
});
</script>

<?php include '../../../includes/footer.php'; ?>
