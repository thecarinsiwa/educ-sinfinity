<?php
/**
 * Module de gestion financiÃ¨re - Voir un type de frais
 * Application de gestion scolaire - RÃ©publique DÃ©mocratique du Congo
 */

require_once '../../../../config/config.php';
require_once '../../../../config/database.php';
require_once '../../../../includes/functions.php';
require_once '../../../../includes/permissions-pages.php';
require_once 'functions.php';

// Vérifier l'authentification et les permissions
requireLogin();
requirePagePermissionFromDB('finance', 'fees', 'read', '../../../../dashboard.php');

$page_title = 'DÃ©tails du type de frais';

// RÃ©cupÃ©rer l'ID du type de frais
$id = (int)($_GET['id'] ?? 0);

if (!$id) {
    showMessage('error', 'Type de frais non spÃ©cifiÃ©.');
    redirectTo('index.php');
}

// RÃ©cupÃ©rer les informations du type de frais
$type_frais = $database->query(
    "SELECT tf.*, as_annee.annee, as_annee.date_debut, as_annee.date_fin
     FROM type_frais tf
     JOIN annees_scolaires as_annee ON tf.annee_scolaire_id = as_annee.id
     WHERE tf.id = ?",
    [$id]
)->fetch();

if (!$type_frais) {
    showMessage('error', 'Type de frais non trouvÃ©.');
    redirectTo('index.php');
}

// RÃ©cupÃ©rer les frais scolaires qui utilisent ce type
$frais_utilisant_type = $database->query(
    "SELECT f.*, c.nom as classe_nom, c.niveau,
            d.code as devise_code, d.symbole as devise_symbole
     FROM frais_scolaires f
     JOIN classes c ON f.classe_id = c.id
     LEFT JOIN devises d ON f.devise_id = d.id
     WHERE f.type_frais = ? AND f.annee_scolaire_id = ?
     ORDER BY c.niveau, c.nom",
    [$type_frais['nom'], $type_frais['annee_scolaire_id']]
)->fetchAll();

// Statistiques d'utilisation
$stats = [
    'total_configurations' => count($frais_utilisant_type),
    'classes_utilisant' => count(array_unique(array_column($frais_utilisant_type, 'classe_id'))),
    'montant_total' => array_sum(array_map(fn($f) => $f['montant_devise_par_defaut'] ?? $f['montant'], $frais_utilisant_type))
];

include '../../../../includes/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">
        <i class="fas fa-eye me-2"></i>
        DÃ©tails du type de frais
    </h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <div class="btn-group me-2">
            <a href="index.php" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-1"></i>
                Retour
            </a>
        </div>
        <div class="btn-group me-2">
            <span class="btn btn-outline-info">
                <i class="fas fa-calendar me-1"></i>
                AnnÃ©e: <?php echo htmlspecialchars($type_frais['annee'] ?? 'Non dÃ©finie'); ?>
            </span>
        </div>
        <?php if (checkPagePermission('finance')): ?>
            <div class="btn-group me-2">
                <a href="edit.php?id=<?php echo $type_frais['id']; ?>" class="btn btn-primary">
                    <i class="fas fa-edit me-1"></i>
                    Modifier
                </a>
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="row">
    <div class="col-lg-8">
        <!-- Informations principales -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-info-circle me-2"></i>
                    Informations gÃ©nÃ©rales
                </h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <table class="table table-borderless">
                            <tr>
                                <td class="fw-bold" style="width: 120px;">Nom:</td>
                                <td><?php echo htmlspecialchars($type_frais['nom']); ?></td>
                            </tr>
                            <tr>
                                <td class="fw-bold">Statut:</td>
                                <td>
                                    <?php if ($type_frais['actif']): ?>
                                        <span class="badge bg-success">Actif</span>
                                    <?php else: ?>
                                        <span class="badge bg-warning">Inactif</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <tr>
                                <td class="fw-bold">ID:</td>
                                <td><?php echo $type_frais['id']; ?></td>
                            </tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <table class="table table-borderless">
                            <tr>
                                <td class="fw-bold" style="width: 120px;">CrÃ©Ã© le:</td>
                                <td><?php echo formatDate($type_frais['date_creation']); ?></td>
                            </tr>
                            <tr>
                                <td class="fw-bold">ModifiÃ© le:</td>
                                <td>
                                    <?php if ($type_frais['updated_at']): ?>
                                        <?php echo formatDate($type_frais['updated_at']); ?>
                                    <?php else: ?>
                                        <span class="text-muted">Jamais modifiÃ©</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <tr>
                                <td class="fw-bold">AnnÃ©e scolaire:</td>
                                <td><?php echo htmlspecialchars($type_frais['annee'] ?? 'Non dÃ©finie'); ?></td>
                            </tr>
                        </table>
                    </div>
                </div>
                
                <?php if (!empty($type_frais['description'])): ?>
                    <div class="mt-3">
                        <h6>Description:</h6>
                        <p class="text-muted"><?php echo displayFullDescription($type_frais['description']); ?></p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Statistiques d'utilisation -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-chart-bar me-2"></i>
                    Statistiques d'utilisation
                </h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-4">
                        <div class="text-center">
                            <h3 class="text-primary"><?php echo $stats['total_configurations']; ?></h3>
                            <p class="text-muted mb-0">Configuration(s)</p>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="text-center">
                            <h3 class="text-success"><?php echo $stats['classes_utilisant']; ?></h3>
                            <p class="text-muted mb-0">Classe(s) utilisant</p>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="text-center">
                            <h3 class="text-info"><?php echo formatCurrency($stats['montant_total']); ?></h3>
                            <p class="text-muted mb-0">Montant total</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Liste des configurations utilisant ce type -->
        <?php if (!empty($frais_utilisant_type)): ?>
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-list me-2"></i>
                        Configurations utilisant ce type (<?php echo count($frais_utilisant_type); ?>)
                    </h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead>
                                <tr>
                                    <th>Classe</th>
                                    <th>LibellÃ©</th>
                                    <th>Montant</th>
                                    <th>Obligatoire</th>
                                    <th>Ã‰chÃ©ance</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($frais_utilisant_type as $frais): ?>
                                    <tr>
                                        <td>
                                            <span class="badge bg-<?php 
                                                echo $frais['niveau'] === 'maternelle' ? 'warning' : 
                                                    ($frais['niveau'] === 'primaire' ? 'success' : 'primary'); 
                                            ?>">
                                                <?php echo htmlspecialchars($frais['classe_nom']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo htmlspecialchars($frais['libelle']); ?></td>
                                        <td>
                                            <strong class="text-success">
                                                <?php echo formatCurrency($frais['montant'], $frais['devise_id']); ?>
                                            </strong>
                                            <?php if ($frais['devise_id'] && $frais['montant_devise_par_defaut']): ?>
                                                <br><small class="text-muted">
                                                    <?= formatCurrency($frais['montant_devise_par_defaut']) ?> (Ã©quivalent)
                                                </small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($frais['obligatoire']): ?>
                                                <span class="badge bg-danger">Obligatoire</span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary">Optionnel</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($frais['date_echeance']): ?>
                                                <?php echo formatDate($frais['date_echeance']); ?>
                                            <?php else: ?>
                                                <span class="text-muted">Non dÃ©finie</span>
                                            <?php endif; ?>
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

    <div class="col-lg-4">
        <!-- Actions rapides -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-bolt me-2"></i>
                    Actions rapides
                </h5>
            </div>
            <div class="card-body">
                <?php if (checkPagePermission('finance')): ?>
                    <div class="d-grid gap-2">
                        <a href="edit.php?id=<?php echo $type_frais['id']; ?>" class="btn btn-primary">
                            <i class="fas fa-edit me-1"></i>
                            Modifier
                        </a>
                        
                        <?php if ($type_frais['actif']): ?>
                            <a href="toggle-status.php?id=<?php echo $type_frais['id']; ?>&action=desactiver" 
                               class="btn btn-warning"
                               onclick="return confirm('ÃŠtes-vous sÃ»r de vouloir dÃ©sactiver ce type de frais ?')">
                                <i class="fas fa-pause me-1"></i>
                                DÃ©sactiver
                            </a>
                        <?php else: ?>
                            <a href="toggle-status.php?id=<?php echo $type_frais['id']; ?>&action=activer" 
                               class="btn btn-success"
                               onclick="return confirm('ÃŠtes-vous sÃ»r de vouloir activer ce type de frais ?')">
                                <i class="fas fa-play me-1"></i>
                                Activer
                            </a>
                        <?php endif; ?>
                        
                        <?php if ($stats['total_configurations'] == 0): ?>
                            <a href="delete.php?id=<?php echo $type_frais['id']; ?>" 
                               class="btn btn-danger"
                               onclick="return confirm('ÃŠtes-vous sÃ»r de vouloir supprimer ce type de frais ? Cette action est irrÃ©versible.')">
                                <i class="fas fa-trash me-1"></i>
                                Supprimer
                            </a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Informations sur l'utilisation -->
        <div class="card mt-3">
            <div class="card-header">
                <h6 class="mb-0">
                    <i class="fas fa-info-circle me-2"></i>
                    Utilisation
                </h6>
            </div>
            <div class="card-body">
                <?php if ($stats['total_configurations'] > 0): ?>
                    <div class="alert alert-info">
                        <small>
                            <i class="fas fa-info-circle me-1"></i>
                            Ce type de frais est utilisÃ© dans <strong><?php echo $stats['total_configurations']; ?></strong> configuration(s) 
                            rÃ©partie(s) sur <strong><?php echo $stats['classes_utilisant']; ?></strong> classe(s).
                        </small>
                    </div>
                <?php else: ?>
                    <div class="alert alert-warning">
                        <small>
                            <i class="fas fa-exclamation-triangle me-1"></i>
                            Ce type de frais n'est pas encore utilisÃ© dans des configurations.
                        </small>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php include '../../../../includes/footer.php'; ?>



