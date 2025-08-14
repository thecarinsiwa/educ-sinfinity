<?php
/**
 * Module de gestion financière - Voir une dépense
 * Application de gestion scolaire - République Démocratique du Congo
 */

require_once '../../../config/config.php';
require_once '../../../config/database.php';
require_once '../../../includes/functions.php';

// Vérifier l'authentification et les permissions
requireLogin();
if (!checkPermission('finance') && !checkPermission('finance_view')) {
    showMessage('error', 'Accès refusé à cette fonctionnalité.');
    redirectTo('index.php');
}

// Récupérer l'ID de la dépense
$id = (int)($_GET['id'] ?? 0);
if (!$id) {
    showMessage('error', 'Dépense non spécifiée.');
    redirectTo('index.php');
}

// Récupérer les informations de la dépense
$sql = "SELECT d.*, u.username as enregistre_par
        FROM depenses d
        LEFT JOIN users u ON d.user_id = u.id
        WHERE d.id = ?";

$depense = $database->query($sql, [$id])->fetch();

if (!$depense) {
    showMessage('error', 'Dépense non trouvée.');
    redirectTo('index.php');
}

$page_title = 'Détails de la dépense - ' . $depense['libelle'];

include '../../../includes/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">
        <i class="fas fa-eye me-2"></i>
        Détails de la dépense
    </h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <div class="btn-group me-2">
            <a href="index.php" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-1"></i>
                Retour à la liste
            </a>
        </div>
        <?php if (checkPermission('finance')): ?>
            <div class="btn-group">
                <a href="edit.php?id=<?php echo $depense['id']; ?>" class="btn btn-outline-primary">
                    <i class="fas fa-edit me-1"></i>
                    Modifier
                </a>
                <?php if ($depense['statut'] === 'en_attente'): ?>
                    <a href="pay.php?id=<?php echo $depense['id']; ?>" class="btn btn-outline-success">
                        <i class="fas fa-check me-1"></i>
                        Marquer comme payée
                    </a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="row">
    <div class="col-lg-8">
        <!-- Informations de la dépense -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-info-circle me-2"></i>
                    Informations de la dépense
                </h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <table class="table table-borderless">
                            <tr>
                                <td class="fw-bold" style="width: 150px;">Libellé :</td>
                                <td><?php echo htmlspecialchars($depense['libelle']); ?></td>
                            </tr>
                            <tr>
                                <td class="fw-bold">Type :</td>
                                <td>
                                    <?php
                                    $type_colors = [
                                        'salaires' => 'primary',
                                        'fournitures' => 'success',
                                        'maintenance' => 'warning',
                                        'utilities' => 'info',
                                        'transport' => 'secondary',
                                        'autre' => 'dark'
                                    ];
                                    $color = $type_colors[$depense['type_depense']] ?? 'secondary';
                                    ?>
                                    <span class="badge bg-<?php echo $color; ?> fs-6">
                                        <?php echo ucfirst($depense['type_depense']); ?>
                                    </span>
                                </td>
                            </tr>
                            <tr>
                                <td class="fw-bold">Montant :</td>
                                <td>
                                    <span class="fs-4 text-danger fw-bold">
                                        <?php echo formatMoney($depense['montant']); ?>
                                    </span>
                                </td>
                            </tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <table class="table table-borderless">
                            <tr>
                                <td class="fw-bold" style="width: 150px;">Date :</td>
                                <td><?php echo formatDate($depense['date_depense']); ?></td>
                            </tr>
                            <tr>
                                <td class="fw-bold">Mode de paiement :</td>
                                <td>
                                    <?php
                                    $modes = [
                                        'especes' => 'Espèces',
                                        'cheque' => 'Chèque',
                                        'virement' => 'Virement bancaire',
                                        'mobile_money' => 'Mobile Money'
                                    ];
                                    echo $modes[$depense['mode_paiement']] ?? ucfirst($depense['mode_paiement']);
                                    ?>
                                </td>
                            </tr>
                            <tr>
                                <td class="fw-bold">Statut :</td>
                                <td>
                                    <?php
                                    $status_colors = [
                                        'en_attente' => 'warning',
                                        'payee' => 'success',
                                        'annulee' => 'danger'
                                    ];
                                    $status_labels = [
                                        'en_attente' => 'En attente',
                                        'payee' => 'Payée',
                                        'annulee' => 'Annulée'
                                    ];
                                    $color = $status_colors[$depense['statut']] ?? 'secondary';
                                    $label = $status_labels[$depense['statut']] ?? $depense['statut'];
                                    ?>
                                    <span class="badge bg-<?php echo $color; ?> fs-6">
                                        <?php echo $label; ?>
                                    </span>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>
                
                <?php if ($depense['fournisseur'] || $depense['numero_facture']): ?>
                <div class="row mt-3">
                    <div class="col-md-6">
                        <?php if ($depense['fournisseur']): ?>
                            <h6 class="fw-bold">Fournisseur :</h6>
                            <p><?php echo htmlspecialchars($depense['fournisseur']); ?></p>
                        <?php endif; ?>
                    </div>
                    <div class="col-md-6">
                        <?php if ($depense['numero_facture']): ?>
                            <h6 class="fw-bold">Numéro de facture :</h6>
                            <p><?php echo htmlspecialchars($depense['numero_facture']); ?></p>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
                
                <?php if ($depense['description']): ?>
                <div class="row mt-3">
                    <div class="col-12">
                        <h6 class="fw-bold">Description :</h6>
                        <p class="text-muted"><?php echo nl2br(htmlspecialchars($depense['description'])); ?></p>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
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
                    <?php if (checkPermission('finance')): ?>
                        <a href="edit.php?id=<?php echo $depense['id']; ?>" class="btn btn-outline-primary">
                            <i class="fas fa-edit me-2"></i>
                            Modifier
                        </a>
                        <?php if ($depense['statut'] === 'en_attente'): ?>
                            <a href="pay.php?id=<?php echo $depense['id']; ?>" class="btn btn-outline-success">
                                <i class="fas fa-check me-2"></i>
                                Marquer comme payée
                            </a>
                        <?php endif; ?>
                        <a href="delete.php?id=<?php echo $depense['id']; ?>" class="btn btn-outline-danger" 
                           onclick="return confirm('Êtes-vous sûr de vouloir supprimer cette dépense ?')">
                            <i class="fas fa-trash me-2"></i>
                            Supprimer
                        </a>
                    <?php endif; ?>
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
                <table class="table table-borderless table-sm">
                    <tr>
                        <td class="fw-bold">ID :</td>
                        <td><?php echo $depense['id']; ?></td>
                    </tr>
                    <tr>
                        <td class="fw-bold">Enregistré par :</td>
                        <td><?php echo htmlspecialchars($depense['enregistre_par'] ?? 'Système'); ?></td>
                    </tr>
                    <tr>
                        <td class="fw-bold">Créé le :</td>
                        <td><?php echo formatDate($depense['created_at']); ?></td>
                    </tr>
                    <?php if ($depense['updated_at']): ?>
                    <tr>
                        <td class="fw-bold">Modifié le :</td>
                        <td><?php echo formatDate($depense['updated_at']); ?></td>
                    </tr>
                    <?php endif; ?>
                </table>
            </div>
        </div>
    </div>
</div>

<?php include '../../../includes/footer.php'; ?>
