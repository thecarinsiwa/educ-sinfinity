<?php
/**
 * Module de gestion du personnel - Voir les détails d'un membre
 * Application de gestion scolaire - République Démocratique du Congo
 */

require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../includes/functions.php';

// Vérifier l'authentification et les permissions
requireLogin();
if (!checkPermission('personnel') && !checkPermission('personnel_view')) {
    showMessage('error', 'Accès refusé à ce module.');
    redirectTo('index.php');
}

// Récupérer l'ID du membre
$id = (int)($_GET['id'] ?? 0);
if (!$id) {
    showMessage('error', 'Membre du personnel non spécifié.');
    redirectTo('index.php');
}

// Récupérer les informations du membre
$sql = "SELECT p.*, u.username, u.email as user_email, u.role, u.status as user_status
        FROM personnel p 
        LEFT JOIN users u ON p.user_id = u.id
        WHERE p.id = ?";

$membre = $database->query($sql, [$id])->fetch();

if (!$membre) {
    showMessage('error', 'Membre du personnel non trouvé.');
    redirectTo('index.php');
}

$page_title = 'Détails - ' . $membre['nom'] . ' ' . $membre['prenom'];

// Récupérer les statistiques si c'est un enseignant
$stats_enseignant = null;
if ($membre['fonction'] === 'enseignant') {
    // Nombre de classes enseignées
    $stmt = $database->query(
        "SELECT COUNT(DISTINCT classe_id) as nb_classes FROM emplois_temps WHERE enseignant_id = ?", 
        [$id]
    );
    $nb_classes = $stmt->fetch()['nb_classes'] ?? 0;
    
    // Nombre d'élèves
    $stmt = $database->query(
        "SELECT COUNT(DISTINCT i.eleve_id) as nb_eleves 
         FROM emplois_temps et 
         JOIN inscriptions i ON et.classe_id = i.classe_id 
         WHERE et.enseignant_id = ? AND i.status = 'inscrit'", 
        [$id]
    );
    $nb_eleves = $stmt->fetch()['nb_eleves'] ?? 0;
    
    // Matières enseignées
    $stmt = $database->query(
        "SELECT DISTINCT m.nom 
         FROM emplois_temps et 
         JOIN matieres m ON et.matiere_id = m.id 
         WHERE et.enseignant_id = ?", 
        [$id]
    );
    $matieres = $stmt->fetchAll();
    
    $stats_enseignant = [
        'nb_classes' => $nb_classes,
        'nb_eleves' => $nb_eleves,
        'matieres' => $matieres
    ];
}

include '../../includes/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">
        <i class="fas fa-user me-2"></i>
        <?php echo htmlspecialchars($membre['nom'] . ' ' . $membre['prenom']); ?>
    </h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <div class="btn-group me-2">
            <a href="index.php" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-1"></i>
                Retour à la liste
            </a>
            <?php if (checkPermission('personnel')): ?>
                <a href="edit.php?id=<?php echo $membre['id']; ?>" class="btn btn-primary">
                    <i class="fas fa-edit me-1"></i>
                    Modifier
                </a>
            <?php endif; ?>
        </div>
        <div class="btn-group">
            <button type="button" class="btn btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">
                <i class="fas fa-ellipsis-v me-1"></i>
                Actions
            </button>
            <ul class="dropdown-menu">
                <li><a class="dropdown-item" href="payslip.php?id=<?php echo $membre['id']; ?>">
                    <i class="fas fa-money-bill me-2"></i>Fiche de paie
                </a></li>
                <li><a class="dropdown-item" href="#" onclick="printElement('personnel-details')">
                    <i class="fas fa-print me-2"></i>Imprimer
                </a></li>
                <li><hr class="dropdown-divider"></li>
                <?php if (checkPermission('personnel')): ?>
                    <li><a class="dropdown-item text-danger" href="delete.php?id=<?php echo $membre['id']; ?>" 
                           onclick="return confirm('Êtes-vous sûr de vouloir supprimer ce membre ?')">
                        <i class="fas fa-trash me-2"></i>Supprimer
                    </a></li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</div>

<div id="personnel-details">
    <div class="row">
        <!-- Informations principales -->
        <div class="col-lg-8">
            <!-- Informations personnelles -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-user me-2"></i>
                        Informations personnelles
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <table class="table table-borderless">
                                <tr>
                                    <td class="fw-bold">Matricule :</td>
                                    <td><?php echo htmlspecialchars($membre['matricule']); ?></td>
                                </tr>
                                <tr>
                                    <td class="fw-bold">Nom complet :</td>
                                    <td><?php echo htmlspecialchars($membre['nom'] . ' ' . $membre['prenom']); ?></td>
                                </tr>
                                <tr>
                                    <td class="fw-bold">Sexe :</td>
                                    <td>
                                        <i class="fas fa-<?php echo $membre['sexe'] === 'M' ? 'mars text-primary' : 'venus text-pink'; ?>"></i>
                                        <?php echo $membre['sexe'] === 'M' ? 'Masculin' : 'Féminin'; ?>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="fw-bold">Date de naissance :</td>
                                    <td>
                                        <?php if ($membre['date_naissance']): ?>
                                            <?php echo formatDate($membre['date_naissance']); ?>
                                            <small class="text-muted">(<?php echo calculateAge($membre['date_naissance']); ?> ans)</small>
                                        <?php else: ?>
                                            <span class="text-muted">Non renseignée</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="fw-bold">Lieu de naissance :</td>
                                    <td><?php echo htmlspecialchars($membre['lieu_naissance'] ?: 'Non renseigné'); ?></td>
                                </tr>
                            </table>
                        </div>
                        <div class="col-md-6">
                            <table class="table table-borderless">
                                <tr>
                                    <td class="fw-bold">Téléphone :</td>
                                    <td>
                                        <?php if ($membre['telephone']): ?>
                                            <a href="tel:<?php echo $membre['telephone']; ?>" class="text-decoration-none">
                                                <i class="fas fa-phone me-1"></i>
                                                <?php echo htmlspecialchars($membre['telephone']); ?>
                                            </a>
                                        <?php else: ?>
                                            <span class="text-muted">Non renseigné</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="fw-bold">Email :</td>
                                    <td>
                                        <?php if ($membre['email']): ?>
                                            <a href="mailto:<?php echo $membre['email']; ?>" class="text-decoration-none">
                                                <i class="fas fa-envelope me-1"></i>
                                                <?php echo htmlspecialchars($membre['email']); ?>
                                            </a>
                                        <?php else: ?>
                                            <span class="text-muted">Non renseigné</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="fw-bold">Adresse :</td>
                                    <td>
                                        <?php if ($membre['adresse']): ?>
                                            <i class="fas fa-map-marker-alt me-1"></i>
                                            <?php echo nl2br(htmlspecialchars($membre['adresse'])); ?>
                                        <?php else: ?>
                                            <span class="text-muted">Non renseignée</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Informations professionnelles -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-briefcase me-2"></i>
                        Informations professionnelles
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <table class="table table-borderless">
                                <tr>
                                    <td class="fw-bold">Fonction :</td>
                                    <td>
                                        <?php
                                        $fonction_colors = [
                                            'enseignant' => 'primary',
                                            'directeur' => 'danger',
                                            'sous_directeur' => 'warning',
                                            'secretaire' => 'info',
                                            'comptable' => 'success',
                                            'surveillant' => 'secondary',
                                            'gardien' => 'dark',
                                            'autre' => 'light'
                                        ];
                                        $color = $fonction_colors[$membre['fonction']] ?? 'secondary';
                                        ?>
                                        <span class="badge bg-<?php echo $color; ?> fs-6">
                                            <?php echo ucfirst(str_replace('_', ' ', $membre['fonction'])); ?>
                                        </span>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="fw-bold">Spécialité :</td>
                                    <td><?php echo htmlspecialchars($membre['specialite'] ?: 'Non renseignée'); ?></td>
                                </tr>
                                <tr>
                                    <td class="fw-bold">Diplôme :</td>
                                    <td><?php echo htmlspecialchars($membre['diplome'] ?: 'Non renseigné'); ?></td>
                                </tr>
                            </table>
                        </div>
                        <div class="col-md-6">
                            <table class="table table-borderless">
                                <tr>
                                    <td class="fw-bold">Date d'embauche :</td>
                                    <td>
                                        <?php if ($membre['date_embauche']): ?>
                                            <?php echo formatDate($membre['date_embauche']); ?>
                                            <?php
                                            $anciennete = floor((time() - strtotime($membre['date_embauche'])) / (365.25 * 24 * 3600));
                                            if ($anciennete > 0): ?>
                                                <small class="text-muted">(<?php echo $anciennete; ?> an<?php echo $anciennete > 1 ? 's' : ''; ?> d'ancienneté)</small>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="text-muted">Non renseignée</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="fw-bold">Salaire de base :</td>
                                    <td>
                                        <?php if ($membre['salaire_base']): ?>
                                            <strong class="text-success"><?php echo formatMoney($membre['salaire_base']); ?></strong>
                                        <?php else: ?>
                                            <span class="text-muted">Non défini</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="fw-bold">Statut :</td>
                                    <td>
                                        <?php
                                        $status_colors = [
                                            'actif' => 'success',
                                            'suspendu' => 'warning',
                                            'demissionne' => 'danger'
                                        ];
                                        $color = $status_colors[$membre['status']] ?? 'secondary';
                                        ?>
                                        <span class="badge bg-<?php echo $color; ?> fs-6">
                                            <?php echo ucfirst($membre['status']); ?>
                                        </span>
                                    </td>
                                </tr>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Statistiques pour les enseignants -->
            <?php if ($membre['fonction'] === 'enseignant' && $stats_enseignant): ?>
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-chart-bar me-2"></i>
                            Statistiques d'enseignement
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row text-center">
                            <div class="col-md-4">
                                <div class="border rounded p-3">
                                    <h3 class="text-primary"><?php echo $stats_enseignant['nb_classes']; ?></h3>
                                    <p class="mb-0">Classes enseignées</p>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="border rounded p-3">
                                    <h3 class="text-success"><?php echo $stats_enseignant['nb_eleves']; ?></h3>
                                    <p class="mb-0">Élèves au total</p>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="border rounded p-3">
                                    <h3 class="text-info"><?php echo count($stats_enseignant['matieres']); ?></h3>
                                    <p class="mb-0">Matières enseignées</p>
                                </div>
                            </div>
                        </div>
                        
                        <?php if (!empty($stats_enseignant['matieres'])): ?>
                            <div class="mt-3">
                                <h6>Matières enseignées :</h6>
                                <?php foreach ($stats_enseignant['matieres'] as $matiere): ?>
                                    <span class="badge bg-light text-dark me-1"><?php echo htmlspecialchars($matiere['nom']); ?></span>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Sidebar avec informations complémentaires -->
        <div class="col-lg-4">
            <!-- Compte utilisateur -->
            <?php if ($membre['user_id']): ?>
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-user-cog me-2"></i>
                            Compte utilisateur
                        </h5>
                    </div>
                    <div class="card-body">
                        <table class="table table-borderless table-sm">
                            <tr>
                                <td class="fw-bold">Nom d'utilisateur :</td>
                                <td><?php echo htmlspecialchars($membre['username']); ?></td>
                            </tr>
                            <tr>
                                <td class="fw-bold">Email de connexion :</td>
                                <td><?php echo htmlspecialchars($membre['user_email']); ?></td>
                            </tr>
                            <tr>
                                <td class="fw-bold">Rôle :</td>
                                <td>
                                    <span class="badge bg-primary">
                                        <?php echo ucfirst($membre['role']); ?>
                                    </span>
                                </td>
                            </tr>
                            <tr>
                                <td class="fw-bold">Statut du compte :</td>
                                <td>
                                    <span class="badge bg-<?php echo $membre['user_status'] === 'actif' ? 'success' : 'danger'; ?>">
                                        <?php echo ucfirst($membre['user_status']); ?>
                                    </span>
                                </td>
                            </tr>
                        </table>
                        
                        <?php if (checkPermission('admin')): ?>
                            <div class="mt-3">
                                <a href="../../admin/users.php?edit=<?php echo $membre['user_id']; ?>" 
                                   class="btn btn-sm btn-outline-primary">
                                    <i class="fas fa-cog me-1"></i>
                                    Gérer le compte
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php else: ?>
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-user-slash me-2"></i>
                            Compte utilisateur
                        </h5>
                    </div>
                    <div class="card-body text-center">
                        <i class="fas fa-user-slash fa-3x text-muted mb-3"></i>
                        <p class="text-muted">Aucun compte utilisateur associé</p>
                        <?php if (checkPermission('personnel')): ?>
                            <a href="create-account.php?id=<?php echo $membre['id']; ?>" 
                               class="btn btn-sm btn-primary">
                                <i class="fas fa-plus me-1"></i>
                                Créer un compte
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
            
            <!-- Actions rapides -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-bolt me-2"></i>
                        Actions rapides
                    </h5>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <a href="payslip.php?id=<?php echo $membre['id']; ?>" class="btn btn-outline-success">
                            <i class="fas fa-money-bill me-2"></i>
                            Générer fiche de paie
                        </a>
                        
                        <?php if ($membre['fonction'] === 'enseignant'): ?>
                            <a href="../academic/schedule.php?teacher=<?php echo $membre['id']; ?>" class="btn btn-outline-info">
                                <i class="fas fa-calendar me-2"></i>
                                Emploi du temps
                            </a>
                            <a href="../evaluations/teacher.php?id=<?php echo $membre['id']; ?>" class="btn btn-outline-primary">
                                <i class="fas fa-chart-line me-2"></i>
                                Évaluations
                            </a>
                        <?php endif; ?>
                        
                        <button onclick="printElement('personnel-details')" class="btn btn-outline-secondary">
                            <i class="fas fa-print me-2"></i>
                            Imprimer la fiche
                        </button>
                    </div>
                </div>
            </div>
            
            <!-- Informations système -->
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-info-circle me-2"></i>
                        Informations système
                    </h5>
                </div>
                <div class="card-body">
                    <small class="text-muted">
                        <strong>Créé le :</strong> <?php echo formatDate($membre['created_at']); ?><br>
                        <?php if (isset($membre['updated_at']) && $membre['updated_at']): ?>
                            <strong>Dernière modification :</strong> <?php echo formatDate($membre['updated_at']); ?><br>
                        <?php endif; ?>
                        <strong>ID système :</strong> #<?php echo $membre['id']; ?>
                    </small>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>
