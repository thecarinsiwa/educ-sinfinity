<?php
/**
 * Module de gestion financière - Voir un paiement
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
               a.annee as annee_scolaire
        FROM paiements p
        JOIN eleves e ON p.eleve_id = e.id
        JOIN inscriptions i ON e.id = i.eleve_id AND i.annee_scolaire_id = p.annee_scolaire_id
        JOIN classes c ON i.classe_id = c.id
        LEFT JOIN users u ON p.user_id = u.id
        JOIN annees_scolaires a ON p.annee_scolaire_id = a.id
        WHERE p.id = ?";

$paiement = $database->query($sql, [$id])->fetch();

if (!$paiement) {
    showMessage('error', 'Paiement non trouvé.');
    redirectTo('index.php');
}

$page_title = 'Détails du paiement - ' . $paiement['recu_numero'];

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
        <div class="btn-group">
            <a href="receipt.php?id=<?php echo $paiement['id']; ?>" class="btn btn-primary">
                <i class="fas fa-receipt me-1"></i>
                Voir le reçu
            </a>
            <?php if (checkPermission('finance')): ?>
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
                                        'autre' => 'Autre'
                                    ];
                                    echo $types[$paiement['type_paiement']] ?? ucfirst($paiement['type_paiement']);
                                    ?>
                                </td>
                            </tr>
                            <tr>
                                <td class="fw-bold">Montant :</td>
                                <td>
                                    <span class="fs-4 text-success fw-bold">
                                        <?php echo formatMoney($paiement['montant']); ?>
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
                    <?php if (checkPermission('finance')): ?>
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
