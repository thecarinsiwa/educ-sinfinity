<?php
/**
 * Visualisation d'un élève - Module Admissions
 * Application de gestion scolaire - République Démocratique du Congo
 */

require_once '../../../config/config.php';
require_once '../../../config/database.php';
require_once '../../../includes/functions.php';
require_once '../../../includes/permissions-pages.php';

// Vérifier l'authentification et les permissions
requireLogin();
requirePagePermissionFromDB('admissions', 'students', 'read', '../../../dashboard.php');

// Récupérer l'ID de l'élève
$eleve_id = intval($_GET['id'] ?? 0);

if (!$eleve_id) {
    showMessage('error', 'ID d\'élève non spécifié.');
    redirectTo('../index.php');
}

// Obtenir l'année scolaire actuelle
$current_year = getCurrentAcademicYear();

// Récupérer les informations complètes de l'élève
try {
    $eleve = $database->query(
        "SELECT e.*, 
                CONCAT('INS', YEAR(i.date_inscription), LPAD(i.id, 4, '0')) as numero_inscription, 
                i.date_inscription, i.status as statut_inscription,
                c.nom as classe_nom, c.niveau, c.section,
                CASE 
                    WHEN e.photo IS NOT NULL AND e.date_naissance IS NOT NULL 
                         AND e.lieu_naissance IS NOT NULL AND e.adresse IS NOT NULL 
                    THEN 'complet' 
                    ELSE 'incomplet' 
                END as statut_dossier,
                (SELECT COUNT(*) FROM documents_eleves de WHERE de.eleve_id = e.id) as nb_documents,
                (SELECT COUNT(*) FROM documents_eleves de WHERE de.eleve_id = e.id AND de.statut_verification = 'verifie') as nb_documents_verifies,
                DATEDIFF(NOW(), i.date_inscription) as jours_depuis_inscription
         FROM eleves e
         JOIN inscriptions i ON e.id = i.eleve_id
         JOIN classes c ON i.classe_id = c.id
         WHERE e.id = ? AND i.annee_scolaire_id = ?",
        [$eleve_id, $current_year['id'] ?? 0]
    )->fetch();

    if (!$eleve) {
        showMessage('error', 'Élève non trouvé ou non inscrit pour l\'année scolaire actuelle.');
        redirectTo('../index.php');
    }
} catch (Exception $e) {
    showMessage('error', 'Erreur lors du chargement des informations : ' . $e->getMessage());
    redirectTo('../index.php');
}

$page_title = 'Élève - ' . $eleve['nom'] . ' ' . $eleve['prenom'];

// Récupérer l'historique des actions
$historique = $database->query(
    "SELECT ual.*, u.nom as user_name 
     FROM user_actions_log ual
     LEFT JOIN users u ON ual.user_id = u.id
     WHERE ual.module = 'admissions' AND ual.target_id = ? 
     ORDER BY ual.created_at DESC
     LIMIT 10",
    [$eleve_id]
)->fetchAll();

// Récupérer les demandes d'admission liées
$demandes_admission = $database->query(
    "SELECT da.*, c.nom as classe_demandee, c.niveau as classe_niveau
     FROM demandes_admission da
     LEFT JOIN classes c ON da.classe_demandee_id = c.id
     WHERE da.eleve_cree_id = ? OR (da.nom_eleve = ? AND da.prenom_eleve = ?)
     ORDER BY da.created_at DESC",
    [$eleve_id, $eleve['nom'], $eleve['prenom']]
)->fetchAll();

include '../../../includes/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">
        <i class="fas fa-user me-2"></i>
        Détails de l'Élève
        <span class="badge bg-primary ms-2"><?php echo htmlspecialchars($eleve['numero_matricule']); ?></span>
    </h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <a href="../index.php" class="btn btn-outline-secondary me-2">
            <i class="fas fa-arrow-left me-1"></i>
            Retour
        </a>
        <a href="../../students/view.php?id=<?php echo $eleve_id; ?>" class="btn btn-outline-info me-2">
            <i class="fas fa-eye me-1"></i>
            Voir dans le module Élèves
        </a>
        <a href="edit.php?id=<?php echo $eleve_id; ?>" class="btn btn-warning me-2">
            <i class="fas fa-edit me-1"></i>
            Modifier
        </a>
        <a href="print.php?id=<?php echo $eleve_id; ?>" class="btn btn-info" target="_blank">
            <i class="fas fa-print me-1"></i>
            Imprimer
        </a>
    </div>
</div>

<?php displayMessage(); ?>

<!-- Statut de l'élève -->
<div class="row mb-4">
    <div class="col-12">
        <?php
        $status_class = '';
        $status_text = '';
        $status_icon = '';
        
        switch ($eleve['status']) {
            case 'actif':
                $status_class = 'bg-success';
                $status_text = 'Actif';
                $status_icon = 'check-circle';
                break;
            case 'non-evalué':
                $status_class = 'bg-warning';
                $status_text = 'Non évalué';
                $status_icon = 'clock';
                break;
            case 'transfere':
                $status_class = 'bg-info';
                $status_text = 'Transféré';
                $status_icon = 'exchange-alt';
                break;
            case 'abandonne':
                $status_class = 'bg-danger';
                $status_text = 'Abandonné';
                $status_icon = 'times-circle';
                break;
            case 'diplome':
                $status_class = 'bg-primary';
                $status_text = 'Diplômé';
                $status_icon = 'graduation-cap';
                break;
            default:
                $status_class = 'bg-secondary';
                $status_text = ucfirst($eleve['status']);
                $status_icon = 'question-circle';
        }
        ?>
        <div class="alert alert-<?php echo str_replace('bg-', 'alert-', $status_class); ?> d-flex align-items-center">
            <i class="fas fa-<?php echo $status_icon; ?> me-2"></i>
            <strong>Statut :</strong> <?php echo $status_text; ?>
            <?php if ($eleve['statut_dossier'] === 'incomplet'): ?>
                <span class="ms-2">- Dossier incomplet</span>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="row">
    <!-- Informations principales -->
    <div class="col-lg-8">
        <!-- Informations personnelles -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-user me-2"></i>
                    Informations Personnelles
                </h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold">Nom complet</label>
                        <div class="form-control-plaintext">
                            <?php echo htmlspecialchars($eleve['nom'] . ' ' . $eleve['prenom']); ?>
                        </div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold">Sexe</label>
                        <div class="form-control-plaintext">
                            <?php if ($eleve['sexe'] === 'M'): ?>
                                <span class="badge bg-primary">Masculin</span>
                            <?php elseif ($eleve['sexe'] === 'F'): ?>
                                <span class="badge bg-pink">Féminin</span>
                            <?php else: ?>
                                <span class="text-muted">Non spécifié</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold">Date de naissance</label>
                        <div class="form-control-plaintext">
                            <?php if ($eleve['date_naissance']): ?>
                                <?php echo formatDate($eleve['date_naissance']); ?>
                                <small class="text-muted">(<?php echo calculateAge($eleve['date_naissance']); ?> ans)</small>
                            <?php else: ?>
                                <span class="text-danger">Non renseignée</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold">Lieu de naissance</label>
                        <div class="form-control-plaintext">
                            <?php echo $eleve['lieu_naissance'] ? htmlspecialchars($eleve['lieu_naissance']) : '<span class="text-danger">Non renseigné</span>'; ?>
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold">Téléphone</label>
                        <div class="form-control-plaintext">
                            <?php if ($eleve['telephone']): ?>
                                <a href="tel:<?php echo htmlspecialchars($eleve['telephone']); ?>">
                                    <?php echo htmlspecialchars($eleve['telephone']); ?>
                                </a>
                            <?php else: ?>
                                <span class="text-muted">Non renseigné</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold">Email</label>
                        <div class="form-control-plaintext">
                            <?php if ($eleve['email']): ?>
                                <a href="mailto:<?php echo htmlspecialchars($eleve['email']); ?>">
                                    <?php echo htmlspecialchars($eleve['email']); ?>
                                </a>
                            <?php else: ?>
                                <span class="text-muted">Non renseigné</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-12 mb-3">
                        <label class="form-label fw-bold">Adresse</label>
                        <div class="form-control-plaintext">
                            <?php echo $eleve['adresse'] ? nl2br(htmlspecialchars($eleve['adresse'])) : '<span class="text-danger">Non renseignée</span>'; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Informations des parents -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-users me-2"></i>
                    Informations des Parents/Tuteurs
                </h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold">Nom du père</label>
                        <div class="form-control-plaintext">
                            <?php echo $eleve['nom_pere'] ? htmlspecialchars($eleve['nom_pere']) : '<span class="text-muted">Non renseigné</span>'; ?>
                        </div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold">Profession du père</label>
                        <div class="form-control-plaintext">
                            <?php echo $eleve['profession_pere'] ? htmlspecialchars($eleve['profession_pere']) : '<span class="text-muted">Non renseignée</span>'; ?>
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold">Nom de la mère</label>
                        <div class="form-control-plaintext">
                            <?php echo $eleve['nom_mere'] ? htmlspecialchars($eleve['nom_mere']) : '<span class="text-muted">Non renseigné</span>'; ?>
                        </div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold">Profession de la mère</label>
                        <div class="form-control-plaintext">
                            <?php echo $eleve['profession_mere'] ? htmlspecialchars($eleve['profession_mere']) : '<span class="text-muted">Non renseignée</span>'; ?>
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold">Téléphone des parents</label>
                        <div class="form-control-plaintext">
                            <?php if ($eleve['telephone_parent']): ?>
                                <a href="tel:<?php echo htmlspecialchars($eleve['telephone_parent']); ?>">
                                    <?php echo htmlspecialchars($eleve['telephone_parent']); ?>
                                </a>
                            <?php else: ?>
                                <span class="text-muted">Non renseigné</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Informations scolaires -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-graduation-cap me-2"></i>
                    Informations Scolaires
                </h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold">Classe actuelle</label>
                        <div class="form-control-plaintext">
                            <strong><?php echo htmlspecialchars($eleve['classe_nom']); ?></strong>
                            <br><small class="text-muted"><?php echo htmlspecialchars($eleve['niveau']); ?></small>
                        </div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold">Numéro d'inscription</label>
                        <div class="form-control-plaintext">
                            <code><?php echo htmlspecialchars($eleve['numero_inscription']); ?></code>
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold">Date d'inscription</label>
                        <div class="form-control-plaintext">
                            <?php echo formatDate($eleve['date_inscription']); ?>
                            <small class="text-muted">(il y a <?php echo $eleve['jours_depuis_inscription']; ?> jours)</small>
                        </div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold">Statut d'inscription</label>
                        <div class="form-control-plaintext">
                            <?php
                            $status_colors = [
                                'active' => 'success',
                                'inactive' => 'secondary',
                                'suspended' => 'warning',
                                'expelled' => 'danger'
                            ];
                            $color = $status_colors[$eleve['statut_inscription']] ?? 'secondary';
                            ?>
                            <span class="badge bg-<?php echo $color; ?>">
                                <?php echo ucfirst($eleve['statut_inscription']); ?>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Demandes d'admission liées -->
        <?php if (!empty($demandes_admission)): ?>
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-file-alt me-2"></i>
                    Demandes d'Admission Liées
                </h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead class="table-dark">
                            <tr>
                                <th>Numéro</th>
                                <th>Classe demandée</th>
                                <th>Statut</th>
                                <th>Date</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($demandes_admission as $demande): ?>
                                <tr>
                                    <td>
                                        <code><?php echo htmlspecialchars($demande['numero_demande']); ?></code>
                                    </td>
                                    <td>
                                        <?php if ($demande['classe_demandee']): ?>
                                            <?php echo htmlspecialchars($demande['classe_demandee'] . ' (' . $demande['classe_niveau'] . ')'); ?>
                                        <?php else: ?>
                                            <span class="text-muted">Non spécifiée</span>
                                        <?php endif; ?>
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
                                            <?php echo ucfirst($demande['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <small class="text-muted">
                                            <?php echo date('d/m/Y', strtotime($demande['created_at'])); ?>
                                        </small>
                                    </td>
                                    <td>
                                        <a href="../applications/view.php?id=<?php echo $demande['id']; ?>" 
                                           class="btn btn-sm btn-outline-primary">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Sidebar avec informations complémentaires -->
    <div class="col-lg-4">
        <!-- Photo de l'élève -->
        <div class="card mb-4">
            <div class="card-header">
                <h6 class="mb-0">
                    <i class="fas fa-camera me-2"></i>
                    Photo
                </h6>
            </div>
            <div class="card-body text-center">
                <?php if ($eleve['photo']): ?>
                    <?php 
                    // Vérifier si le chemin contient déjà 'uploads/photos/'
                    $photo_path = $eleve['photo'];
                    if (strpos($photo_path, 'uploads/photos/') === 0) {
                        // Le chemin contient déjà le dossier, on l'utilise tel quel
                        $photo_src = "../../../" . $photo_path;
                    } else {
                        // Le chemin ne contient que le nom du fichier
                        $photo_src = "../../../uploads/photos/" . $photo_path;
                    }
                    ?>
                    <img src="<?php echo htmlspecialchars($photo_src); ?>" 
                         alt="Photo de <?php echo htmlspecialchars($eleve['nom'] . ' ' . $eleve['prenom']); ?>"
                         class="img-fluid rounded" style="max-height: 200px;"
                         onerror="this.style.display='none'; this.nextElementSibling.style.display='block';">
                    <div class="bg-light rounded d-flex align-items-center justify-content-center" style="height: 200px; display: none;">
                        <div class="text-center text-muted">
                            <i class="fas fa-user fa-3x mb-2"></i>
                            <br>Photo non trouvée
                        </div>
                    </div>
                <?php else: ?>
                    <div class="bg-light rounded d-flex align-items-center justify-content-center" style="height: 200px;">
                        <div class="text-center text-muted">
                            <i class="fas fa-user fa-3x mb-2"></i>
                            <br>Aucune photo
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Statut et informations -->
        <div class="card mb-4">
            <div class="card-header">
                <h6 class="mb-0">
                    <i class="fas fa-info-circle me-2"></i>
                    Statut et Informations
                </h6>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <label class="form-label fw-bold">Statut de l'élève</label>
                    <div class="d-grid">
                        <span class="badge <?php echo $status_class; ?> fs-6 py-2">
                            <i class="fas fa-<?php echo $status_icon; ?> me-1"></i>
                            <?php echo $status_text; ?>
                        </span>
                    </div>
                </div>
                
                <div class="mb-3">
                    <label class="form-label fw-bold">Statut du dossier</label>
                    <div class="form-control-plaintext">
                        <?php if ($eleve['statut_dossier'] === 'complet'): ?>
                            <span class="badge bg-success">
                                <i class="fas fa-check me-1"></i>Complet
                            </span>
                        <?php else: ?>
                            <span class="badge bg-warning">
                                <i class="fas fa-exclamation-triangle me-1"></i>Incomplet
                            </span>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="mb-3">
                    <label class="form-label fw-bold">Numéro de matricule</label>
                    <div class="form-control-plaintext">
                        <span class="badge bg-info"><?php echo htmlspecialchars($eleve['numero_matricule']); ?></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Statistiques des documents -->
        <div class="card mb-4">
            <div class="card-header">
                <h6 class="mb-0">
                    <i class="fas fa-chart-pie me-2"></i>
                    Documents
                </h6>
            </div>
            <div class="card-body">
                <div class="row text-center">
                    <div class="col-6">
                        <div class="border-end">
                            <h4 class="text-primary mb-0"><?php echo $eleve['nb_documents']; ?></h4>
                            <small class="text-muted">Total</small>
                        </div>
                    </div>
                    <div class="col-6">
                        <h4 class="text-success mb-0"><?php echo $eleve['nb_documents_verifies']; ?></h4>
                        <small class="text-muted">Vérifiés</small>
                    </div>
                </div>
                <div class="mt-3">
                    <?php if ($eleve['nb_documents'] > 0): ?>
                        <?php $pourcentage = round(($eleve['nb_documents_verifies'] / $eleve['nb_documents']) * 100); ?>
                        <div class="progress">
                            <div class="progress-bar bg-success" role="progressbar" 
                                 style="width: <?php echo $pourcentage; ?>%" 
                                 aria-valuenow="<?php echo $pourcentage; ?>" 
                                 aria-valuemin="0" aria-valuemax="100">
                                <?php echo $pourcentage; ?>%
                            </div>
                        </div>
                        <small class="text-muted">Documents vérifiés</small>
                    <?php else: ?>
                        <div class="alert alert-warning alert-sm mb-0">
                            <i class="fas fa-exclamation-triangle me-1"></i>
                            Aucun document
                        </div>
                    <?php endif; ?>
                </div>
                <div class="mt-3">
                    <a href="../../students/records/documents.php?id=<?php echo $eleve_id; ?>" class="btn btn-outline-info btn-sm w-100">
                        <i class="fas fa-file-alt me-1"></i>
                        Gérer les documents
                    </a>
                </div>
            </div>
        </div>

        <!-- Actions rapides -->
        <div class="card">
            <div class="card-header">
                <h6 class="mb-0">
                    <i class="fas fa-bolt me-2"></i>
                    Actions Rapides
                </h6>
            </div>
            <div class="card-body">
                <div class="d-grid gap-2">
                    <a href="../../students/view.php?id=<?php echo $eleve_id; ?>" class="btn btn-outline-primary">
                        <i class="fas fa-eye me-1"></i>
                        Voir dans le module Élèves
                    </a>
                    
                    <a href="edit.php?id=<?php echo $eleve_id; ?>" class="btn btn-warning">
                        <i class="fas fa-edit me-1"></i>
                        Modifier
                    </a>
                    
                    <a href="print.php?id=<?php echo $eleve_id; ?>" class="btn btn-info" target="_blank">
                        <i class="fas fa-print me-1"></i>
                        Imprimer
                    </a>
                    
                    <a href="../index.php" class="btn btn-outline-secondary">
                        <i class="fas fa-list me-1"></i>
                        Retour aux admissions
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Historique des actions -->
<?php if (!empty($historique)): ?>
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-history me-2"></i>
                    Historique des Actions
                </h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Utilisateur</th>
                                <th>Action</th>
                                <th>Détails</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($historique as $action): ?>
                                <tr>
                                    <td><?php echo date('d/m/Y H:i', strtotime($action['created_at'])); ?></td>
                                    <td><?php echo htmlspecialchars($action['user_name']); ?></td>
                                    <td>
                                        <span class="badge bg-info"><?php echo htmlspecialchars($action['action']); ?></span>
                                    </td>
                                    <td><?php echo htmlspecialchars($action['details']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
// Initialisation des tooltips Bootstrap
var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
    return new bootstrap.Tooltip(tooltipTriggerEl);
});
</script>

<?php include '../../../includes/footer.php'; ?>
