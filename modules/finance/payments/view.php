<?php
/**
 * Module de gestion financière - Voir un paiement
 * Application de gestion scolaire - République Démocratique du Congo
 */

require_once '../../../config/config.php';
require_once '../../../config/database.php';
require_once '../../../includes/functions.php';
require_once '../../../includes/permissions-pages.php';
require_once '../../../includes/ui-permissions.php';

// Vérifier l'authentification et les permissions
requireLogin();
requirePagePermissionFromDB('finance', 'payments/view', 'read', '../../dashboard.php');

// Récupérer l'ID du paiement
$id = (int)($_GET['id'] ?? 0);
if (!$id) {
    showMessage('error', 'Paiement non spécifié.');
    redirectTo('index.php');
}

// Récupérer les informations du paiement
$sql = "SELECT p.*, 
               e.nom, e.prenom, e.numero_matricule, e.date_naissance,
               c.nom as classe_nom, c.niveau,
               u.username as enregistre_par,
               a.annee as annee_scolaire,
               d.code as devise_code, d.symbole as devise_symbole, d.nom as devise_nom,
               tf.nom as type_frais
        FROM paiements p
        JOIN eleves e ON p.eleve_id = e.id
        JOIN inscriptions i ON e.id = i.eleve_id AND i.annee_scolaire_id = p.annee_scolaire_id
        JOIN classes c ON i.classe_id = c.id
        JOIN type_frais tf ON p.type_frais_id = tf.id
        LEFT JOIN users u ON p.user_id = u.id
        JOIN annees_scolaires a ON p.annee_scolaire_id = a.id
        LEFT JOIN devises d ON p.devise_id = d.id
        WHERE p.id = ?";

$paiement = $database->query($sql, [$id])->fetch();

if (!$paiement) {
    showMessage('error', 'Paiement non trouvé.');
    redirectTo('index.php');
}

$page_title = 'Détails du paiement - ' . $paiement['recu_numero'];

// Obtenir la devise par défaut
$devise_par_defaut = getDefaultCurrency();

include '../../../includes/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">
        <i class="fas fa-eye me-2"></i>
        Détails du paiement
    </h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <div class="btn-group me-2">
            <a href="index.php" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-1"></i>
                Retour à la liste
            </a>
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
            <a href="receipt.php?id=<?php echo $paiement['id']; ?>" class="btn btn-primary">
                <i class="fas fa-receipt me-1"></i>
                Voir le reçu
            </a>
            <?php if (hasPagePermissionFromDB('finance', 'payments/edit', 'update')): ?>
                <a href="edit.php?id=<?php echo $paiement['id']; ?>" class="btn btn-outline-primary">
                    <i class="fas fa-edit me-1"></i>
                    Modifier
                </a>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-lg-8">
        <!-- Informations du paiement -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-info-circle me-2"></i>
                    Informations du paiement
                </h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <table class="table table-borderless">
                            <tr>
                                <td class="fw-bold" style="width: 150px;">N° Reçu :</td>
                                <td>
                                    <span class="badge bg-primary fs-6">
                                        <?php echo htmlspecialchars($paiement['recu_numero']); ?>
                                    </span>
                                </td>
                            </tr>
                            <tr>
                                <td class="fw-bold">Date de paiement :</td>
                                <td><?php echo formatDate($paiement['date_paiement']); ?></td>
                            </tr>
                            <tr>
                                <td class="fw-bold">Type de paiement :</td>
                                <td>
                                    <?php
                                    $types = [
                                        'inscription' => 'Frais d\'inscription',
                                        'mensualite' => 'Mensualité',
                                        'examen' => 'Frais d\'examen',
                                        'uniforme' => 'Uniforme',
                                        'transport' => 'Transport',
                                        'cantine' => 'Cantine',
                                        'autre' => 'Autre'
                                    ];
                                    echo htmlspecialchars($paiement['type_frais']);
                                    ?>
                                </td>
                            </tr>
                            <tr>
                                <td class="fw-bold">Devise :</td>
                                <td>
                                    <?php if ($paiement['devise_code']): ?>
                                        <span class="badge bg-info">
                                            <?php echo htmlspecialchars($paiement['devise_code']); ?> - 
                                            <?php echo htmlspecialchars($paiement['devise_nom']); ?>
                                        </span>
                                        <?php if ($paiement['devise_code'] !== $devise_par_defaut['code']): ?>
                                            <br>
                                            <small class="text-muted">
                                                Devise différente de la devise par défaut
                                            </small>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="text-muted">Non spécifiée</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <tr>
                                <td class="fw-bold">Montant :</td>
                                <td>
                                    <span class="fs-4 text-success fw-bold">
                                        <?php echo formatMoneyWithDefault($paiement['montant'], $paiement['devise_id'], $paiement['montant_devise_par_defaut']); ?>
                                    </span>
                                </td>
                            </tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <table class="table table-borderless">
                            <tr>
                                <td class="fw-bold" style="width: 150px;">Mode de paiement :</td>
                                <td>
                                    <?php
                                    $modes = [
                                        'especes' => 'Espèces',
                                        'cheque' => 'Chèque',
                                        'virement' => 'Virement bancaire',
                                        'mobile_money' => 'Mobile Money'
                                    ];
                                    echo $modes[$paiement['mode_paiement']] ?? ucfirst($paiement['mode_paiement']);
                                    ?>
                                </td>
                            </tr>
                            <?php if ($paiement['mois_concerne']): ?>
                            <tr>
                                <td class="fw-bold">Mois concerné :</td>
                                <td><?php echo htmlspecialchars($paiement['mois_concerne']); ?></td>
                            </tr>
                            <?php endif; ?>
                            <tr>
                                <td class="fw-bold">Année scolaire :</td>
                                <td><?php echo htmlspecialchars($paiement['annee_scolaire']); ?></td>
                            </tr>
                            <tr>
                                <td class="fw-bold">Enregistré par :</td>
                                <td><?php echo htmlspecialchars($paiement['enregistre_par'] ?? 'Système'); ?></td>
                            </tr>
                        </table>
                    </div>
                </div>
                
                <?php if ($paiement['observation']): ?>
                <div class="mt-3">
                    <h6 class="fw-bold">Observation :</h6>
                    <p class="text-muted"><?php echo htmlspecialchars($paiement['observation']); ?></p>
                </div>
                <?php endif; ?>
                
                <!-- Informations de conversion -->
                <?php if ($devise_par_defaut && $paiement['devise_code'] && $paiement['devise_code'] !== $devise_par_defaut['code']): ?>
                <div class="mt-3">
                    <div class="alert alert-info">
                        <h6 class="fw-bold mb-2">
                            <i class="fas fa-exchange-alt me-2"></i>
                            Informations de conversion
                        </h6>
                        <div class="row">
                            <div class="col-md-6">
                                <strong>Montant original :</strong><br>
                                <?php echo formatMoney($paiement['montant']); ?> 
                                <?php echo htmlspecialchars($paiement['devise_symbole']); ?>
                                (<?php echo htmlspecialchars($paiement['devise_code']); ?>)
                            </div>
                            <div class="col-md-6">
                                <strong>Équivalent en devise par défaut :</strong><br>
                                <?php echo formatMoney($paiement['montant_devise_par_defaut'] ?? $paiement['montant']); ?> 
                                <?php echo htmlspecialchars($devise_par_defaut['symbole']); ?>
                                (<?php echo htmlspecialchars($devise_par_defaut['code']); ?>)
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Informations de l'élève -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-user-graduate me-2"></i>
                    Informations de l'élève
                </h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <table class="table table-borderless">
                            <tr>
                                <td class="fw-bold" style="width: 150px;">Nom complet :</td>
                                <td><?php echo htmlspecialchars($paiement['nom'] . ' ' . $paiement['prenom']); ?></td>
                            </tr>
                            <tr>
                                <td class="fw-bold">Matricule :</td>
                                <td><?php echo htmlspecialchars($paiement['numero_matricule']); ?></td>
                            </tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <table class="table table-borderless">
                            <tr>
                                <td class="fw-bold" style="width: 150px;">Classe :</td>
                                <td><?php echo htmlspecialchars($paiement['classe_nom']); ?></td>
                            </tr>
                            <tr>
                                <td class="fw-bold">Niveau :</td>
                                <td><?php echo ucfirst($paiement['niveau']); ?></td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <!-- Informations de devise -->
        <div class="card mb-4">
            <div class="card-header bg-info text-white">
                <h5 class="mb-0">
                    <i class="fas fa-exchange-alt me-2"></i>
                    Informations de devise
                </h5>
            </div>
            <div class="card-body">
                <?php if ($devise_par_defaut): ?>
                    <div class="text-center mb-3">
                        <h6 class="text-muted">Devise par défaut</h6>
                        <h4 class="text-info">
                            <?php echo htmlspecialchars($devise_par_defaut['symbole']); ?>
                            <?php echo htmlspecialchars($devise_par_defaut['code']); ?>
                        </h4>
                        <small class="text-muted"><?php echo htmlspecialchars($devise_par_defaut['nom']); ?></small>
                    </div>
                    
                    <?php if ($paiement['devise_code'] && $paiement['devise_code'] !== $devise_par_defaut['code']): ?>
                        <div class="alert alert-warning">
                            <small>
                                <i class="fas fa-info-circle me-1"></i>
                                Ce paiement a été effectué dans une devise différente de la devise par défaut.
                            </small>
                        </div>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="text-center text-muted">
                        <i class="fas fa-exclamation-triangle fa-2x mb-2"></i>
                        <p>Aucune devise par défaut configurée</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

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
                    <a href="receipt.php?id=<?php echo $paiement['id']; ?>" class="btn btn-primary">
                        <i class="fas fa-receipt me-2"></i>
                        Voir le reçu
                    </a>
                    <a href="receipt.php?id=<?php echo $paiement['id']; ?>" class="btn btn-outline-primary" onclick="window.open(this.href); return false;">
                        <i class="fas fa-print me-2"></i>
                        Imprimer le reçu
                    </a>
                    <?php if (hasPagePermissionFromDB('finance', 'index', 'read')): ?>
                        <a href="edit.php?id=<?php echo $paiement['id']; ?>" class="btn btn-outline-warning">
                            <i class="fas fa-edit me-2"></i>
                            Modifier
                        </a>
                        <a href="cancel.php?id=<?php echo $paiement['id']; ?>" class="btn btn-outline-danger" 
                           onclick="return confirm('Êtes-vous sûr de vouloir annuler ce paiement ?')">
                            <i class="fas fa-times me-2"></i>
                            Annuler
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Statut -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-info-circle me-2"></i>
                    Statut
                </h5>
            </div>
            <div class="card-body">
                <div class="alert alert-success">
                    <h6 class="mb-1">
                        <i class="fas fa-check-circle me-2"></i>
                        Paiement validé
                    </h6>
                    <small>Ce paiement a été validé et enregistré dans nos comptes.</small>
                </div>
                
                <div class="mt-3">
                    <small class="text-muted">
                        <strong>Date d'enregistrement :</strong><br>
                        <?php echo formatDate($paiement['created_at'] ?? $paiement['date_paiement']); ?>
                        à <?php echo date('H:i', strtotime($paiement['created_at'] ?? $paiement['date_paiement'])); ?>
                    </small>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../../../includes/footer.php'; ?>
