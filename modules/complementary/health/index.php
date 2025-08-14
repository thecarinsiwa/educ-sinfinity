<?php
/**
 * Module Santé - Page principale
 * Application de gestion scolaire - République Démocratique du Congo
 */

require_once '../../../config/config.php';
require_once '../../../config/database.php';
require_once '../../../includes/functions.php';

// Vérifier l'authentification et les permissions
requireLogin();
if (!checkPermission('health') && !checkPermission('health_view')) {
    showMessage('error', 'Accès refusé à ce module.');
    redirectTo('../index.php');
}

$page_title = 'Gestion de la Santé Scolaire';

// Obtenir l'année scolaire actuelle
$current_year = getCurrentAcademicYear();

// Statistiques de santé
$stats = [];

// Consultations ce mois
$stmt = $database->query(
    "SELECT COUNT(*) as total FROM consultations WHERE MONTH(date_consultation) = MONTH(CURDATE()) AND YEAR(date_consultation) = YEAR(CURDATE())"
);
$stats['consultations_mois'] = $stmt->fetch()['total'];

// Vaccinations cette année
$stmt = $database->query(
    "SELECT COUNT(*) as total FROM vaccinations WHERE annee_scolaire_id = ?",
    [$current_year['id'] ?? 0]
);
$stats['vaccinations_annee'] = $stmt->fetch()['total'];

// Élèves avec dossier médical
$stmt = $database->query(
    "SELECT COUNT(DISTINCT eleve_id) as total FROM dossiers_medicaux WHERE annee_scolaire_id = ?",
    [$current_year['id'] ?? 0]
);
$stats['dossiers_medicaux'] = $stmt->fetch()['total'];

// Alertes médicales actives
$stmt = $database->query(
    "SELECT COUNT(*) as total FROM alertes_medicales WHERE status = 'active' AND annee_scolaire_id = ?",
    [$current_year['id'] ?? 0]
);
$stats['alertes_actives'] = $stmt->fetch()['total'];

// Consultations récentes
$consultations_recentes = $database->query(
    "SELECT c.*, e.nom, e.prenom, e.numero_matricule, cl.nom as classe_nom,
            p.nom as medecin_nom, p.prenom as medecin_prenom
     FROM consultations c
     JOIN eleves e ON c.eleve_id = e.id
     JOIN inscriptions i ON e.id = i.eleve_id
     JOIN classes cl ON i.classe_id = cl.id
     LEFT JOIN personnel p ON c.medecin_id = p.id
     WHERE c.date_consultation >= DATE_SUB(NOW(), INTERVAL 7 DAY)
     ORDER BY c.date_consultation DESC, c.created_at DESC
     LIMIT 10"
)->fetchAll();

// Types de consultations les plus fréquents
$types_consultations = $database->query(
    "SELECT motif_consultation, COUNT(*) as nombre
     FROM consultations
     WHERE date_consultation >= DATE_SUB(NOW(), INTERVAL 30 DAY)
     GROUP BY motif_consultation
     ORDER BY nombre DESC
     LIMIT 8"
)->fetchAll();

// Vaccinations par type
$vaccinations_par_type = $database->query(
    "SELECT type_vaccin, COUNT(*) as nombre
     FROM vaccinations
     WHERE annee_scolaire_id = ?
     GROUP BY type_vaccin
     ORDER BY nombre DESC",
    [$current_year['id'] ?? 0]
)->fetchAll();

// Alertes médicales actives
$alertes_medicales = $database->query(
    "SELECT a.*, e.nom, e.prenom, e.numero_matricule, c.nom as classe_nom
     FROM alertes_medicales a
     JOIN eleves e ON a.eleve_id = e.id
     JOIN inscriptions i ON e.id = i.eleve_id
     JOIN classes c ON i.classe_id = c.id
     WHERE a.status = 'active' AND a.annee_scolaire_id = ?
     ORDER BY a.niveau_urgence DESC, a.created_at DESC
     LIMIT 8",
    [$current_year['id'] ?? 0]
)->fetchAll();

// Statistiques par classe
$stats_par_classe = $database->query(
    "SELECT c.nom as classe_nom, c.niveau,
            COUNT(DISTINCT dm.eleve_id) as eleves_avec_dossier,
            COUNT(DISTINCT cons.eleve_id) as eleves_consultes,
            COUNT(DISTINCT v.eleve_id) as eleves_vaccines
     FROM classes c
     JOIN inscriptions i ON c.id = i.classe_id
     LEFT JOIN dossiers_medicaux dm ON i.eleve_id = dm.eleve_id AND dm.annee_scolaire_id = ?
     LEFT JOIN consultations cons ON i.eleve_id = cons.eleve_id AND YEAR(cons.date_consultation) = YEAR(CURDATE())
     LEFT JOIN vaccinations v ON i.eleve_id = v.eleve_id AND v.annee_scolaire_id = ?
     WHERE c.annee_scolaire_id = ? AND i.status = 'inscrit'
     GROUP BY c.id
     ORDER BY c.niveau, c.nom",
    [$current_year['id'] ?? 0, $current_year['id'] ?? 0, $current_year['id'] ?? 0]
)->fetchAll();

include '../../../includes/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">
        <i class="fas fa-heartbeat me-2"></i>
        Gestion de la Santé Scolaire
    </h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <div class="btn-group me-2">
            <a href="../index.php" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-1"></i>
                Retour
            </a>
        </div>
        <?php if (checkPermission('health')): ?>
            <div class="btn-group me-2">
                <button type="button" class="btn btn-danger dropdown-toggle" data-bs-toggle="dropdown">
                    <i class="fas fa-plus me-1"></i>
                    Nouveau
                </button>
                <ul class="dropdown-menu">
                    <li><a class="dropdown-item" href="consultations/add.php">
                        <i class="fas fa-stethoscope me-2"></i>Nouvelle consultation
                    </a></li>
                    <li><a class="dropdown-item" href="vaccinations/add.php">
                        <i class="fas fa-syringe me-2"></i>Nouvelle vaccination
                    </a></li>
                    <li><a class="dropdown-item" href="medical-records/add.php">
                        <i class="fas fa-file-medical me-2"></i>Dossier médical
                    </a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item" href="alerts/add.php">
                        <i class="fas fa-exclamation-triangle me-2"></i>Alerte médicale
                    </a></li>
                </ul>
            </div>
        <?php endif; ?>
        <div class="btn-group">
            <button type="button" class="btn btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">
                <i class="fas fa-chart-bar me-1"></i>
                Rapports
            </button>
            <ul class="dropdown-menu">
                <li><a class="dropdown-item" href="reports/consultations.php">
                    <i class="fas fa-stethoscope me-2"></i>Rapport consultations
                </a></li>
                <li><a class="dropdown-item" href="reports/vaccinations.php">
                    <i class="fas fa-syringe me-2"></i>Suivi vaccinations
                </a></li>
                <li><a class="dropdown-item" href="reports/health-status.php">
                    <i class="fas fa-heartbeat me-2"></i>État de santé
                </a></li>
            </ul>
        </div>
    </div>
</div>

<!-- Statistiques rapides -->
<div class="row mb-4">
    <div class="col-lg-3 col-md-6 mb-3">
        <div class="card text-white bg-primary">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h4><?php echo $stats['consultations_mois']; ?></h4>
                        <p class="mb-0">Consultations ce mois</p>
                    </div>
                    <div class="align-self-center">
                        <i class="fas fa-stethoscope fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-3 col-md-6 mb-3">
        <div class="card text-white bg-success">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h4><?php echo $stats['vaccinations_annee']; ?></h4>
                        <p class="mb-0">Vaccinations</p>
                    </div>
                    <div class="align-self-center">
                        <i class="fas fa-syringe fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-3 col-md-6 mb-3">
        <div class="card text-white bg-info">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h4><?php echo $stats['dossiers_medicaux']; ?></h4>
                        <p class="mb-0">Dossiers médicaux</p>
                    </div>
                    <div class="align-self-center">
                        <i class="fas fa-file-medical fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-3 col-md-6 mb-3">
        <div class="card text-white bg-warning">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h4><?php echo $stats['alertes_actives']; ?></h4>
                        <p class="mb-0">Alertes actives</p>
                    </div>
                    <div class="align-self-center">
                        <i class="fas fa-exclamation-triangle fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modules de santé -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-th-large me-2"></i>
                    Modules de santé
                </h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-lg-3 col-md-6 mb-3">
                        <a href="consultations/" class="text-decoration-none">
                            <div class="card h-100 border-0 shadow-sm hover-card">
                                <div class="card-body text-center">
                                    <i class="fas fa-stethoscope fa-3x text-primary mb-3"></i>
                                    <h5 class="card-title">Consultations</h5>
                                    <p class="card-text text-muted">
                                        Consultations médicales et infirmerie
                                    </p>
                                    <div class="mt-3">
                                        <span class="badge bg-primary"><?php echo $stats['consultations_mois']; ?> ce mois</span>
                                    </div>
                                </div>
                            </div>
                        </a>
                    </div>
                    
                    <div class="col-lg-3 col-md-6 mb-3">
                        <a href="vaccinations/" class="text-decoration-none">
                            <div class="card h-100 border-0 shadow-sm hover-card">
                                <div class="card-body text-center">
                                    <i class="fas fa-syringe fa-3x text-success mb-3"></i>
                                    <h5 class="card-title">Vaccinations</h5>
                                    <p class="card-text text-muted">
                                        Suivi des vaccinations obligatoires
                                    </p>
                                    <div class="mt-3">
                                        <span class="badge bg-success"><?php echo $stats['vaccinations_annee']; ?> cette année</span>
                                    </div>
                                </div>
                            </div>
                        </a>
                    </div>
                    
                    <div class="col-lg-3 col-md-6 mb-3">
                        <a href="medical-records/" class="text-decoration-none">
                            <div class="card h-100 border-0 shadow-sm hover-card">
                                <div class="card-body text-center">
                                    <i class="fas fa-file-medical fa-3x text-info mb-3"></i>
                                    <h5 class="card-title">Dossiers médicaux</h5>
                                    <p class="card-text text-muted">
                                        Dossiers de santé des élèves
                                    </p>
                                    <div class="mt-3">
                                        <span class="badge bg-info"><?php echo $stats['dossiers_medicaux']; ?> dossiers</span>
                                    </div>
                                </div>
                            </div>
                        </a>
                    </div>
                    
                    <div class="col-lg-3 col-md-6 mb-3">
                        <a href="infirmary/" class="text-decoration-none">
                            <div class="card h-100 border-0 shadow-sm hover-card">
                                <div class="card-body text-center">
                                    <i class="fas fa-clinic-medical fa-3x text-danger mb-3"></i>
                                    <h5 class="card-title">Infirmerie</h5>
                                    <p class="card-text text-muted">
                                        Gestion de l'infirmerie scolaire
                                    </p>
                                    <div class="mt-3">
                                        <span class="badge bg-danger">Soins & Urgences</span>
                                    </div>
                                </div>
                            </div>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Contenu principal -->
<div class="row">
    <div class="col-lg-8">
        <!-- Consultations récentes -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-clock me-2"></i>
                    Consultations récentes (7 derniers jours)
                </h5>
            </div>
            <div class="card-body">
                <?php if (!empty($consultations_recentes)): ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Élève</th>
                                    <th>Classe</th>
                                    <th>Motif</th>
                                    <th>Médecin</th>
                                    <th>Urgence</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($consultations_recentes as $consultation): ?>
                                    <tr>
                                        <td><?php echo formatDate($consultation['date_consultation']); ?></td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($consultation['nom'] . ' ' . $consultation['prenom']); ?></strong>
                                            <br><small class="text-muted">
                                                <?php echo htmlspecialchars($consultation['numero_matricule']); ?>
                                            </small>
                                        </td>
                                        <td>
                                            <span class="badge bg-secondary">
                                                <?php echo htmlspecialchars($consultation['classe_nom']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo htmlspecialchars($consultation['motif_consultation']); ?></td>
                                        <td>
                                            <?php if ($consultation['medecin_nom']): ?>
                                                <small>
                                                    <?php echo htmlspecialchars($consultation['medecin_nom'] . ' ' . substr($consultation['medecin_prenom'], 0, 1) . '.'); ?>
                                                </small>
                                            <?php else: ?>
                                                <span class="text-muted">Infirmier(ère)</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php
                                            $urgence_colors = [
                                                'faible' => 'success',
                                                'moyenne' => 'warning',
                                                'elevee' => 'danger'
                                            ];
                                            $color = $urgence_colors[$consultation['niveau_urgence']] ?? 'secondary';
                                            ?>
                                            <span class="badge bg-<?php echo $color; ?>">
                                                <?php echo ucfirst($consultation['niveau_urgence']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <a href="consultations/view.php?id=<?php echo $consultation['id']; ?>" 
                                                   class="btn btn-outline-info" title="Voir">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                <?php if (checkPermission('health')): ?>
                                                    <a href="consultations/edit.php?id=<?php echo $consultation['id']; ?>" 
                                                       class="btn btn-outline-primary" title="Modifier">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="text-center py-4">
                        <i class="fas fa-stethoscope fa-3x text-muted mb-3"></i>
                        <p class="text-muted">Aucune consultation récente</p>
                        <?php if (checkPermission('health')): ?>
                            <a href="consultations/add.php" class="btn btn-primary">
                                <i class="fas fa-plus me-1"></i>
                                Nouvelle consultation
                            </a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Alertes médicales -->
        <?php if (!empty($alertes_medicales)): ?>
        <div class="card">
            <div class="card-header bg-warning text-dark">
                <h5 class="mb-0">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    Alertes médicales actives
                </h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <?php foreach ($alertes_medicales as $alerte): ?>
                        <div class="col-md-6 mb-3">
                            <div class="card border-<?php 
                                echo $alerte['niveau_urgence'] === 'elevee' ? 'danger' : 
                                    ($alerte['niveau_urgence'] === 'moyenne' ? 'warning' : 'info'); 
                            ?>">
                                <div class="card-body">
                                    <h6 class="card-title">
                                        <?php echo htmlspecialchars($alerte['nom'] . ' ' . $alerte['prenom']); ?>
                                    </h6>
                                    <p class="card-text">
                                        <strong><?php echo htmlspecialchars($alerte['type_alerte']); ?></strong>
                                        <br><?php echo htmlspecialchars($alerte['description']); ?>
                                        <br><small class="text-muted">
                                            <?php echo htmlspecialchars($alerte['classe_nom']); ?>
                                        </small>
                                    </p>
                                    <div class="d-flex justify-content-between align-items-center">
                                        <span class="badge bg-<?php 
                                            echo $alerte['niveau_urgence'] === 'elevee' ? 'danger' : 
                                                ($alerte['niveau_urgence'] === 'moyenne' ? 'warning' : 'info'); 
                                        ?>">
                                            <?php echo ucfirst($alerte['niveau_urgence']); ?>
                                        </span>
                                        <a href="alerts/view.php?id=<?php echo $alerte['id']; ?>" 
                                           class="btn btn-sm btn-outline-primary">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
    
    <div class="col-lg-4">
        <!-- Types de consultations -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-chart-pie me-2"></i>
                    Motifs de consultation (30j)
                </h5>
            </div>
            <div class="card-body">
                <?php if (!empty($types_consultations)): ?>
                    <?php foreach ($types_consultations as $type): ?>
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span><?php echo htmlspecialchars($type['motif_consultation']); ?></span>
                            <span class="badge bg-primary"><?php echo $type['nombre']; ?></span>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p class="text-muted text-center">Aucune consultation ce mois</p>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Vaccinations par type -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-syringe me-2"></i>
                    Vaccinations par type
                </h5>
            </div>
            <div class="card-body">
                <?php if (!empty($vaccinations_par_type)): ?>
                    <?php foreach ($vaccinations_par_type as $vaccin): ?>
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span><?php echo htmlspecialchars($vaccin['type_vaccin']); ?></span>
                            <span class="badge bg-success"><?php echo $vaccin['nombre']; ?></span>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p class="text-muted text-center">Aucune vaccination enregistrée</p>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Statistiques par classe -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-school me-2"></i>
                    Suivi par classe
                </h5>
            </div>
            <div class="card-body">
                <?php if (!empty($stats_par_classe)): ?>
                    <?php foreach (array_slice($stats_par_classe, 0, 6) as $classe): ?>
                        <div class="mb-3">
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="badge bg-<?php 
                                    echo $classe['niveau'] === 'maternelle' ? 'warning' : 
                                        ($classe['niveau'] === 'primaire' ? 'success' : 'primary'); 
                                ?>">
                                    <?php echo htmlspecialchars($classe['classe_nom']); ?>
                                </span>
                            </div>
                            <div class="row text-center mt-2">
                                <div class="col-4">
                                    <small class="text-muted">Dossiers</small>
                                    <br><span class="badge bg-info"><?php echo $classe['eleves_avec_dossier']; ?></span>
                                </div>
                                <div class="col-4">
                                    <small class="text-muted">Consultés</small>
                                    <br><span class="badge bg-primary"><?php echo $classe['eleves_consultes']; ?></span>
                                </div>
                                <div class="col-4">
                                    <small class="text-muted">Vaccinés</small>
                                    <br><span class="badge bg-success"><?php echo $classe['eleves_vaccines']; ?></span>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p class="text-muted text-center">Aucune donnée disponible</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Actions rapides -->
<?php if (checkPermission('health')): ?>
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
                    <div class="col-md-3 mb-2">
                        <div class="d-grid">
                            <a href="consultations/add.php" class="btn btn-outline-primary">
                                <i class="fas fa-stethoscope me-2"></i>
                                Nouvelle consultation
                            </a>
                        </div>
                    </div>
                    <div class="col-md-3 mb-2">
                        <div class="d-grid">
                            <a href="vaccinations/add.php" class="btn btn-outline-success">
                                <i class="fas fa-syringe me-2"></i>
                                Nouvelle vaccination
                            </a>
                        </div>
                    </div>
                    <div class="col-md-3 mb-2">
                        <div class="d-grid">
                            <a href="medical-records/add.php" class="btn btn-outline-info">
                                <i class="fas fa-file-medical me-2"></i>
                                Dossier médical
                            </a>
                        </div>
                    </div>
                    <div class="col-md-3 mb-2">
                        <div class="d-grid">
                            <a href="reports/health-status.php" class="btn btn-outline-secondary">
                                <i class="fas fa-chart-bar me-2"></i>
                                État de santé
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<style>
.hover-card {
    transition: transform 0.2s ease-in-out;
}

.hover-card:hover {
    transform: translateY(-5px);
}
</style>

<?php include '../../../includes/footer.php'; ?>
