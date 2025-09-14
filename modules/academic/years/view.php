<?php
/**
 * Détails d'une année scolaire
 * Application de gestion scolaire - République Démocratique du Congo
 */

require_once '../../../config/config.php';
require_once '../../../config/database.php';
require_once '../../../includes/functions.php';
require_once '../../../includes/permissions-pages.php';

// Vérifier l'authentification et les permissions
requireLogin();
requirePagePermissionFromDB('academic', 'years/view', 'read', '../../../dashboard.php');

$page_title = 'Détails de l\'année scolaire';

// Vérifier que l'ID est fourni
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    showMessage('error', 'ID d\'année scolaire invalide.');
    redirectTo('index.php');
    exit;
}

$annee_id = (int)$_GET['id'];

// Récupérer les informations de l'année scolaire
$annee = $database->query(
    "SELECT * FROM annees_scolaires WHERE id = ?",
    [$annee_id]
)->fetch();

if (!$annee) {
    showMessage('error', 'Année scolaire non trouvée.');
    redirectTo('index.php');
    exit;
}

// Récupérer les statistiques de l'année scolaire
$stats = [
    'classes' => $database->query(
        "SELECT COUNT(*) as count FROM classes WHERE annee_scolaire_id = ?",
        [$annee_id]
    )->fetch()['count'],
    'eleves' => $database->query(
        "SELECT COUNT(DISTINCT i.eleve_id) as count 
         FROM inscriptions i 
         WHERE i.annee_scolaire_id = ? AND i.status = 'inscrit'",
        [$annee_id]
    )->fetch()['count'],
    'enseignants' => $database->query(
        "SELECT COUNT(DISTINCT et.enseignant_id) as count 
         FROM emploi_temps et 
         JOIN classes c ON et.classe_id = c.id 
         WHERE c.annee_scolaire_id = ?",
        [$annee_id]
    )->fetch()['count']
];

// Récupérer les classes de cette année
$classes = $database->query(
    "SELECT c.*, 
            COUNT(DISTINCT i.eleve_id) as nb_eleves,
            p.nom as titulaire_nom, p.prenom as titulaire_prenom
     FROM classes c 
     LEFT JOIN inscriptions i ON c.id = i.classe_id AND i.status = 'inscrit'
     LEFT JOIN personnel p ON c.titulaire_id = p.id
     WHERE c.annee_scolaire_id = ?
     GROUP BY c.id
     ORDER BY c.niveau, c.nom",
    [$annee_id]
)->fetchAll();

include '../../../includes/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">
        <i class="fas fa-calendar me-2"></i>
        <?php echo htmlspecialchars($annee['annee']); ?>
    </h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <div class="btn-group me-2">
            <a href="index.php" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-1"></i>
                Retour
            </a>
        </div>
        <?php if (hasPagePermissionFromDB('academic', 'years/edit', 'edit')): ?>
        <div class="btn-group">
            <a href="edit.php?id=<?php echo $annee['id']; ?>" class="btn btn-primary">
                <i class="fas fa-edit me-1"></i>
                Modifier
            </a>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Informations générales -->
<div class="row mb-4">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-info-circle me-2"></i>
                    Informations générales
                </h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <h6>Année scolaire</h6>
                        <p class="text-primary fs-5"><?php echo htmlspecialchars($annee['annee']); ?></p>
                        
                        <h6>Période</h6>
                        <p>
                            <i class="fas fa-calendar-alt me-1"></i>
                            Du <?php echo date('d/m/Y', strtotime($annee['date_debut'])); ?> 
                            au <?php echo date('d/m/Y', strtotime($annee['date_fin'])); ?>
                        </p>
                        
                        <h6>Durée</h6>
                        <p>
                            <?php 
                            $debut = new DateTime($annee['date_debut']);
                            $fin = new DateTime($annee['date_fin']);
                            $duree = $debut->diff($fin);
                            echo $duree->days . ' jours';
                            ?>
                        </p>
                    </div>
                    <div class="col-md-6">
                        <h6>Statut</h6>
                        <p>
                            <span class="badge bg-<?php echo $annee['status'] === 'active' ? 'success' : 'secondary'; ?> fs-6">
                                <?php echo $annee['status'] === 'active' ? 'Active' : 'Fermée'; ?>
                            </span>
                        </p>
                        
                        <h6>Créée le</h6>
                        <p>
                            <i class="fas fa-clock me-1"></i>
                            <?php echo date('d/m/Y H:i', strtotime($annee['created_at'])); ?>
                        </p>
                        
                        <?php if ($annee['updated_at']): ?>
                        <h6>Modifiée le</h6>
                        <p>
                            <i class="fas fa-edit me-1"></i>
                            <?php echo date('d/m/Y H:i', strtotime($annee['updated_at'])); ?>
                        </p>
                        <?php endif; ?>
                    </div>
                </div>
                
                <?php if ($annee['description']): ?>
                <div class="mt-3">
                    <h6>Description</h6>
                    <p class="text-muted"><?php echo nl2br(htmlspecialchars($annee['description'])); ?></p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <div class="col-md-4">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-chart-bar me-2"></i>
                    Statistiques
                </h5>
            </div>
            <div class="card-body">
                <div class="row text-center">
                    <div class="col-4">
                        <div class="border-end">
                            <h4 class="text-primary mb-0"><?php echo $stats['classes']; ?></h4>
                            <small class="text-muted">Classes</small>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="border-end">
                            <h4 class="text-success mb-0"><?php echo $stats['eleves']; ?></h4>
                            <small class="text-muted">Élèves</small>
                        </div>
                    </div>
                    <div class="col-4">
                        <h4 class="text-info mb-0"><?php echo $stats['enseignants']; ?></h4>
                        <small class="text-muted">Enseignants</small>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Classes de cette année -->
<div class="card">
    <div class="card-header">
        <h5 class="mb-0">
            <i class="fas fa-school me-2"></i>
            Classes de cette année (<?php echo count($classes); ?>)
        </h5>
    </div>
    <div class="card-body">
        <?php if (!empty($classes)): ?>
            <div class="row">
                <?php foreach ($classes as $classe): ?>
                    <div class="col-lg-4 col-md-6 mb-3">
                        <div class="card h-100 border-0 shadow-sm">
                            <div class="card-header bg-<?php 
                                echo $classe['niveau'] === 'maternelle' ? 'warning' : 
                                    ($classe['niveau'] === 'primaire' ? 'success' : 'primary'); 
                            ?> text-white">
                                <div class="d-flex justify-content-between align-items-center">
                                    <h6 class="mb-0"><?php echo htmlspecialchars($classe['nom']); ?></h6>
                                    <span class="badge bg-light text-dark">
                                        <?php echo ucfirst($classe['niveau']); ?>
                                    </span>
                                </div>
                            </div>
                            <div class="card-body">
                                <div class="row text-center mb-2">
                                    <div class="col-6">
                                        <h6 class="text-primary mb-0"><?php echo $classe['nb_eleves']; ?></h6>
                                        <small class="text-muted">Élèves</small>
                                    </div>
                                    <div class="col-6">
                                        <h6 class="text-info mb-0"><?php echo $classe['capacite_max'] ?? 'N/A'; ?></h6>
                                        <small class="text-muted">Capacité</small>
                                    </div>
                                </div>
                                
                                <?php if ($classe['titulaire_nom']): ?>
                                    <div class="mb-2">
                                        <small class="text-muted">Titulaire :</small><br>
                                        <strong><?php echo htmlspecialchars($classe['titulaire_nom'] . ' ' . $classe['titulaire_prenom']); ?></strong>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="d-grid">
                                    <a href="../classes/view.php?id=<?php echo $classe['id']; ?>" 
                                       class="btn btn-outline-primary btn-sm">
                                        <i class="fas fa-eye me-1"></i>
                                        Voir détails
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="text-center py-4">
                <i class="fas fa-school fa-3x text-muted mb-3"></i>
                <h5 class="text-muted">Aucune classe</h5>
                <p class="text-muted">Aucune classe n'a encore été créée pour cette année scolaire.</p>
                <?php if (hasPagePermissionFromDB('academic', 'classes/add', 'create')): ?>
                    <a href="../classes/add.php?annee_id=<?php echo $annee['id']; ?>" class="btn btn-primary">
                        <i class="fas fa-plus me-1"></i>
                        Créer une classe
                    </a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include '../../../includes/footer.php'; ?>
