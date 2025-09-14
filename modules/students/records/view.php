<?php
/**
 * Affichage dÃ©taillÃ© d'un dossier scolaire
 * Application de gestion scolaire - République Démocratique du Congo
 */

require_once '../../../config/config.php';
require_once '../../../config/database.php';
require_once '../../../includes/functions.php';
require_once '../../../includes/permissions-pages.php';

// VÃ©rifier l'authentification et les permissions
requireLogin();

requirePagePermissionFromDB('students', 'records/view', 'read', '../../../dashboard.php');

$eleve_id = intval($_GET['id'] ?? 0);

if (!$eleve_id) {
    showMessage('error', 'ID d\'Ã©lÃ¨ve invalide.');
    redirectTo('index.php');
}

// Obtenir l'année scolaire actuelle
$current_year = getCurrentAcademicYear();

// RÃ©cupÃ©rer les informations complÃ¨tes de l'Ã©lÃ¨ve
try {
    $eleve = $database->query(
        "SELECT e.*, CONCAT('INS', YEAR(i.date_inscription), LPAD(i.id, 4, '0')) as numero_inscription, 
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
        showMessage('error', 'Ã‰lÃ¨ve non trouvÃ© ou non inscrit pour l\'année scolaire actuelle.');
        redirectTo('index.php');
    }
} catch (Exception $e) {
    showMessage('error', 'Erreur lors du chargement du dossier : ' . $e->getMessage());
    redirectTo('index.php');
}

$page_title = 'Dossier de ' . $eleve['nom'] . ' ' . $eleve['prenom'];

include '../../../includes/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">
        <i class="fas fa-folder-open me-2"></i>
        Dossier Scolaire
    </h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <div class="btn-group me-2">
            <a href="index.php" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-1"></i>
                Retour Ã  la liste
            </a>
        </div>
        <?php if (checkPagePermission('students')): ?>
        <div class="btn-group me-2">
            <a href="edit.php?id=<?php echo $eleve_id; ?>" class="btn btn-outline-primary">
                <i class="fas fa-edit me-1"></i>
                Modifier
            </a>
        </div>
        <div class="btn-group">
            <a href="documents.php?id=<?php echo $eleve_id; ?>" class="btn btn-outline-info">
                <i class="fas fa-file-alt me-1"></i>
                Documents (<?php echo $eleve['nb_documents']; ?>)
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
                    <i class="fas fa-user me-2"></i>
                    Informations GÃ©nÃ©rales
                </h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <table class="table table-borderless">
                            <tr>
                                <td><strong>Nom complet :</strong></td>
                                <td><?php echo htmlspecialchars($eleve['nom'] . ' ' . $eleve['prenom']); ?></td>
                            </tr>
                            <tr>
                                <td><strong>Sexe :</strong></td>
                                <td>
                                    <?php if ($eleve['sexe'] === 'M'): ?>
                                        <span class="badge bg-primary">Masculin</span>
                                    <?php elseif ($eleve['sexe'] === 'F'): ?>
                                        <span class="badge bg-pink">FÃ©minin</span>
                                    <?php else: ?>
                                        <span class="text-muted">Non spÃ©cifiÃ©</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <tr>
                                <td><strong>Date de naissance :</strong></td>
                                <td>
                                    <?php if ($eleve['date_naissance']): ?>
                                        <?php echo formatDate($eleve['date_naissance']); ?>
                                        <small class="text-muted">(<?php echo calculateAge($eleve['date_naissance']); ?> ans)</small>
                                    <?php else: ?>
                                        <span class="text-danger">Non renseignÃ©e</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <tr>
                                <td><strong>Lieu de naissance :</strong></td>
                                <td><?php echo $eleve['lieu_naissance'] ? htmlspecialchars($eleve['lieu_naissance']) : '<span class="text-danger">Non renseignÃ©</span>'; ?></td>
                            </tr>
                            <tr>
                                <td><strong>Statut :</strong></td>
                                <td>
                                    <?php
                                    $status_colors = [
                                        'actif' => 'success',
                                        'transfere' => 'info',
                                        'abandonne' => 'warning',
                                        'diplome' => 'primary'
                                    ];
                                    $color = $status_colors[$eleve['status']] ?? 'secondary';
                                    ?>
                                    <span class="badge bg-<?php echo $color; ?>">
                                        <?php echo ucfirst($eleve['status']); ?>
                                    </span>
                                </td>
                            </tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <table class="table table-borderless">
                            <tr>
                                <td><strong>NumÃ©ro d'inscription :</strong></td>
                                <td><code><?php echo htmlspecialchars($eleve['numero_inscription']); ?></code></td>
                            </tr>
                            <tr>
                                <td><strong>Date d'inscription :</strong></td>
                                <td>
                                    <?php echo formatDate($eleve['date_inscription']); ?>
                                    <small class="text-muted">(il y a <?php echo $eleve['jours_depuis_inscription']; ?> jours)</small>
                                </td>
                            </tr>
                            <tr>
                                <td><strong>Classe actuelle :</strong></td>
                                <td>
                                    <strong><?php echo htmlspecialchars($eleve['classe_nom']); ?></strong>
                                    <br><small class="text-muted"><?php echo htmlspecialchars($eleve['niveau']); ?></small>
                                </td>
                            </tr>
                            <tr>
                                <td><strong>Statut inscription :</strong></td>
                                <td>
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
                                </td>
                            </tr>
                            <tr>
                                <td><strong>Statut du dossier :</strong></td>
                                <td>
                                    <?php if ($eleve['statut_dossier'] === 'complet'): ?>
                                        <span class="badge bg-success">
                                            <i class="fas fa-check me-1"></i>Complet
                                        </span>
                                    <?php else: ?>
                                        <span class="badge bg-warning">
                                            <i class="fas fa-exclamation-triangle me-1"></i>Incomplet
                                        </span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-4">
        <!-- Photo de l'Ã©lÃ¨ve -->
        <div class="card mb-3">
            <div class="card-header">
                <h6 class="mb-0">
                    <i class="fas fa-camera me-2"></i>
                    Photo
                </h6>
            </div>
            <div class="card-body text-center">
                <?php if ($eleve['photo']): ?>
                    <?php 
                    // VÃ©rifier si le chemin contient dÃ©jÃ  'uploads/photos/'
                    $photo_path = $eleve['photo'];
                    if (strpos($photo_path, 'uploads/photos/') === 0) {
                        // Le chemin contient dÃ©jÃ  le dossier, on l'utilise tel quel
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
                            <br>Photo non trouvÃ©e
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
        
        <!-- Statistiques des documents -->
        <div class="card">
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
                        <small class="text-muted">VÃ©rifiÃ©s</small>
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
                        <small class="text-muted">Documents vÃ©rifiÃ©s</small>
                    <?php else: ?>
                        <div class="alert alert-warning alert-sm mb-0">
                            <i class="fas fa-exclamation-triangle me-1"></i>
                            Aucun document
                        </div>
                    <?php endif; ?>
                </div>
                <div class="mt-3">
                    <a href="documents.php?id=<?php echo $eleve_id; ?>" class="btn btn-outline-info btn-sm w-100">
                        <i class="fas fa-file-alt me-1"></i>
                        GÃ©rer les documents
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Informations de contact -->
<div class="row mb-4">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-address-book me-2"></i>
                    Informations de Contact
                </h5>
            </div>
            <div class="card-body">
                <table class="table table-borderless">
                    <tr>
                        <td><strong>Adresse :</strong></td>
                        <td><?php echo $eleve['adresse'] ? nl2br(htmlspecialchars($eleve['adresse'])) : '<span class="text-danger">Non renseignÃ©e</span>'; ?></td>
                    </tr>
                    <tr>
                        <td><strong>TÃ©lÃ©phone :</strong></td>
                        <td>
                            <?php if ($eleve['telephone']): ?>
                                <a href="tel:<?php echo htmlspecialchars($eleve['telephone']); ?>">
                                    <?php echo htmlspecialchars($eleve['telephone']); ?>
                                </a>
                            <?php else: ?>
                                <span class="text-muted">Non renseignÃ©</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <td><strong>Email :</strong></td>
                        <td>
                            <?php if ($eleve['email']): ?>
                                <a href="mailto:<?php echo htmlspecialchars($eleve['email']); ?>">
                                    <?php echo htmlspecialchars($eleve['email']); ?>
                                </a>
                            <?php else: ?>
                                <span class="text-muted">Non renseignÃ©</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                </table>
            </div>
        </div>
    </div>
    
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-users me-2"></i>
                    Informations Familiales
                </h5>
            </div>
            <div class="card-body">
                <table class="table table-borderless">
                    <tr>
                        <td><strong>Nom du pÃ¨re :</strong></td>
                        <td><?php echo $eleve['nom_pere'] ? htmlspecialchars($eleve['nom_pere']) : '<span class="text-muted">Non renseignÃ©</span>'; ?></td>
                    </tr>
                    <tr>
                        <td><strong>Nom de la mÃ¨re :</strong></td>
                        <td><?php echo $eleve['nom_mere'] ? htmlspecialchars($eleve['nom_mere']) : '<span class="text-muted">Non renseignÃ©</span>'; ?></td>
                    </tr>
                    <tr>
                        <td><strong>Profession du pÃ¨re :</strong></td>
                        <td><?php echo $eleve['profession_pere'] ? htmlspecialchars($eleve['profession_pere']) : '<span class="text-muted">Non renseignÃ©e</span>'; ?></td>
                    </tr>
                    <tr>
                        <td><strong>Profession de la mÃ¨re :</strong></td>
                        <td><?php echo $eleve['profession_mere'] ? htmlspecialchars($eleve['profession_mere']) : '<span class="text-muted">Non renseignÃ©e</span>'; ?></td>
                    </tr>
                    <tr>
                        <td><strong>TÃ©lÃ©phone des parents :</strong></td>
                        <td>
                            <?php if ($eleve['telephone_parent']): ?>
                                <a href="tel:<?php echo htmlspecialchars($eleve['telephone_parent']); ?>">
                                    <?php echo htmlspecialchars($eleve['telephone_parent']); ?>
                                </a>
                            <?php else: ?>
                                <span class="text-muted">Non renseignÃ©</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                </table>
            </div>
        </div>
    </div>
</div>

<?php include '../../../includes/footer.php'; ?>




