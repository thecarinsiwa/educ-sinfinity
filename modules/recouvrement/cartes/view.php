<?php
/**
 * Module Recouvrement - Visualisation d'une Carte
 * Application de gestion scolaire - République Démocratique du Congo
 */

require_once '../../../config/config.php';
require_once '../../../config/database.php';
require_once '../../../includes/functions.php';

// Vérifier l'authentification et les permissions
requireLogin();
if (!checkPermission('recouvrement') && !checkPermission('admin')) {
    showMessage('error', 'Accès refusé à cette page.');
    redirectTo('../../../index.php');
}

$page_title = 'Détails de la Carte';

// Récupérer l'ID de la carte
$carte_id = (int)($_GET['id'] ?? 0);

if ($carte_id <= 0) {
    showMessage('error', 'ID de carte invalide.');
    redirectTo('index.php');
}

// Récupérer les détails de la carte
try {
    $sql = "
        SELECT 
            c.*,
            e.nom as eleve_nom,
            e.prenom as eleve_prenom,
            e.numero_matricule,
            e.date_naissance,
            e.sexe,
            cl.nom as classe_nom,
            cl.niveau
        FROM cartes_eleves c
        JOIN eleves e ON c.eleve_id = e.id
        LEFT JOIN inscriptions i ON e.id = i.eleve_id AND i.status = 'inscrit'
        LEFT JOIN classes cl ON i.classe_id = cl.id
        WHERE c.id = ?
    ";
    
    $carte = $database->query($sql, [$carte_id])->fetch();
    
    if (!$carte) {
        showMessage('error', 'Carte non trouvée.');
        redirectTo('index.php');
    }
    
} catch (Exception $e) {
    showMessage('error', 'Erreur lors de la récupération des données : ' . $e->getMessage());
    redirectTo('index.php');
}

// Récupérer l'historique des transactions
try {
    $transactions = $database->query(
        "SELECT 
            t.*,
            u.nom as user_nom,
            u.prenom as user_prenom
        FROM transactions_cartes t
        LEFT JOIN users u ON t.user_id = u.id
        WHERE t.carte_id = ?
        ORDER BY t.created_at DESC
        LIMIT 50",
        [$carte_id]
    )->fetchAll();
} catch (Exception $e) {
    $transactions = [];
}

// Récupérer l'historique des paiements
try {
    $paiements = $database->query(
        "SELECT 
            p.*,
            u.nom as user_nom,
            u.prenom as user_prenom
        FROM paiements_cartes p
        LEFT JOIN users u ON p.user_id = u.id
        WHERE p.carte_id = ?
        ORDER BY p.date_paiement DESC
        LIMIT 20",
        [$carte_id]
    )->fetchAll();
} catch (Exception $e) {
    $paiements = [];
}

// Calculer les statistiques
$montant_limite = $carte['montant_limite'] ?? 0;
$montant_utilise = $carte['montant_utilise'] ?? 0;
$solde_actuel = $montant_limite - $montant_utilise;
$pourcentage_utilise = $montant_limite > 0 ? ($montant_utilise / $montant_limite) * 100 : 0;

include '../../../includes/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">
        <i class="fas fa-credit-card me-2"></i>
        Détails de la Carte
    </h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <div class="btn-group me-2">
            <a href="index.php" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-1"></i>
                Retour
            </a>
            <a href="print.php?id=<?php echo $carte_id; ?>" class="btn btn-outline-primary" target="_blank">
                <i class="fas fa-print me-1"></i>
                Imprimer
            </a>
        </div>
        <div class="btn-group">
            <button type="button" class="btn btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">
                <i class="fas fa-cog me-1"></i>
                Actions
            </button>
            <ul class="dropdown-menu">
                <li><a class="dropdown-item" href="edit.php?id=<?php echo $carte_id; ?>">
                    <i class="fas fa-edit me-2"></i>Modifier
                </a></li>
                <li><a class="dropdown-item" href="recharge.php?id=<?php echo $carte_id; ?>">
                    <i class="fas fa-plus me-2"></i>Recharger
                </a></li>
                <li><hr class="dropdown-divider"></li>
                <?php if ($carte['status'] === 'active'): ?>
                    <li><a class="dropdown-item text-warning" href="block.php?id=<?php echo $carte_id; ?>" onclick="return confirm('Bloquer cette carte ?')">
                        <i class="fas fa-ban me-2"></i>Bloquer
                    </a></li>
                <?php else: ?>
                    <li><a class="dropdown-item text-success" href="activate.php?id=<?php echo $carte_id; ?>">
                        <i class="fas fa-check me-2"></i>Activer
                    </a></li>
                <?php endif; ?>
                <li><a class="dropdown-item text-danger" href="delete.php?id=<?php echo $carte_id; ?>" onclick="return confirm('Supprimer définitivement cette carte ?')">
                    <i class="fas fa-trash me-2"></i>Supprimer
                </a></li>
            </ul>
        </div>
    </div>
</div>

<!-- Informations de la carte -->
<div class="row mb-4">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-info-circle me-2"></i>
                    Informations de la Carte
                </h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <table class="table table-borderless">
                            <tr>
                                <td class="fw-bold">Numéro de carte :</td>
                                <td><span class="badge bg-primary fs-6"><?php echo htmlspecialchars($carte['numero_carte']); ?></span></td>
                            </tr>
                            <tr>
                                <td class="fw-bold">Type :</td>
                                <td>
                                    <span class="badge bg-<?php 
                                        echo $carte['type_carte'] === 'premium' ? 'warning' : 
                                            ($carte['type_carte'] === 'temporaire' ? 'info' : 'secondary'); 
                                    ?>">
                                        <?php echo ucfirst($carte['type_carte']); ?>
                                    </span>
                                </td>
                            </tr>
                            <tr>
                                <td class="fw-bold">Statut :</td>
                                <td>
                                    <span class="badge bg-<?php 
                                        echo $carte['status'] === 'active' ? 'success' : 
                                            ($carte['status'] === 'inactive' ? 'secondary' : 
                                            ($carte['status'] === 'perdue' ? 'warning' : 'danger')); 
                                    ?>">
                                        <?php echo ucfirst($carte['status']); ?>
                                    </span>
                                </td>
                            </tr>
                            <tr>
                                <td class="fw-bold">Date d'émission :</td>
                                <td><?php echo formatDate($carte['date_emission']); ?></td>
                            </tr>
                            <?php if ($carte['date_expiration']): ?>
                            <tr>
                                <td class="fw-bold">Date d'expiration :</td>
                                <td><?php echo formatDate($carte['date_expiration']); ?></td>
                            </tr>
                            <?php endif; ?>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <table class="table table-borderless">
                            <tr>
                                <td class="fw-bold">Montant limite :</td>
                                <td class="text-primary fw-bold"><?php echo formatMoney($montant_limite); ?></td>
                            </tr>
                            <tr>
                                <td class="fw-bold">Montant utilisé :</td>
                                <td class="text-danger"><?php echo formatMoney($montant_utilise); ?></td>
                            </tr>
                            <tr>
                                <td class="fw-bold">Solde disponible :</td>
                                <td class="text-success fw-bold"><?php echo formatMoney($solde_actuel); ?></td>
                            </tr>
                            <tr>
                                <td class="fw-bold">Utilisation :</td>
                                <td>
                                    <div class="progress" style="height: 20px;">
                                        <div class="progress-bar bg-<?php echo $pourcentage_utilise > 80 ? 'danger' : ($pourcentage_utilise > 60 ? 'warning' : 'success'); ?>" 
                                             style="width: <?php echo $pourcentage_utilise; ?>%">
                                            <?php echo number_format($pourcentage_utilise, 1); ?>%
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>
                
                <?php if ($carte['observations']): ?>
                <div class="mt-3">
                    <h6>Observations :</h6>
                    <p class="text-muted"><?php echo nl2br(htmlspecialchars($carte['observations'])); ?></p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <div class="col-md-4">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-user me-2"></i>
                    Informations de l'Élève
                </h5>
            </div>
            <div class="card-body">
                <div class="text-center mb-3">
                    <div class="avatar-placeholder bg-primary text-white rounded-circle d-inline-flex align-items-center justify-content-center" style="width: 80px; height: 80px; font-size: 2rem;">
                        <?php echo strtoupper(substr($carte['eleve_nom'], 0, 1) . substr($carte['eleve_prenom'], 0, 1)); ?>
                    </div>
                </div>
                
                <table class="table table-borderless">
                    <tr>
                        <td class="fw-bold">Nom complet :</td>
                        <td><?php echo htmlspecialchars($carte['eleve_nom'] . ' ' . $carte['eleve_prenom']); ?></td>
                    </tr>
                    <tr>
                        <td class="fw-bold">Matricule :</td>
                        <td><?php echo htmlspecialchars($carte['numero_matricule']); ?></td>
                    </tr>
                    <tr>
                        <td class="fw-bold">Classe :</td>
                        <td>
                            <span class="badge bg-<?php 
                                echo $carte['niveau'] === 'maternelle' ? 'warning' : 
                                    ($carte['niveau'] === 'primaire' ? 'success' : 'primary'); 
                            ?>">
                                <?php echo htmlspecialchars($carte['classe_nom']); ?>
                            </span>
                        </td>
                    </tr>
                    <tr>
                        <td class="fw-bold">Date de naissance :</td>
                        <td><?php echo formatDate($carte['date_naissance']); ?></td>
                    </tr>
                    <tr>
                        <td class="fw-bold">Genre :</td>
                        <td><?php echo $carte['sexe'] === 'M' ? 'Masculin' : 'Féminin'; ?></td>
                    </tr>
                </table>
                
                <div class="text-center mt-3">
                    <a href="../../students/records/view.php?id=<?php echo $carte['eleve_id']; ?>" class="btn btn-outline-primary btn-sm">
                        <i class="fas fa-eye me-1"></i>
                        Voir le dossier
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Historique des transactions -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-history me-2"></i>
                    Historique des Transactions
                </h5>
            </div>
            <div class="card-body">
                <?php if (empty($transactions)): ?>
                    <div class="text-center py-4">
                        <i class="fas fa-info-circle fa-2x text-muted mb-3"></i>
                        <p class="text-muted">Aucune transaction enregistrée pour cette carte.</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Type</th>
                                    <th>Montant</th>
                                    <th>Solde avant</th>
                                    <th>Solde après</th>
                                    <th>Description</th>
                                    <th>Utilisateur</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($transactions as $transaction): ?>
                                    <tr>
                                        <td><?php echo formatDateTime($transaction['created_at']); ?></td>
                                        <td>
                                            <span class="badge bg-<?php 
                                                echo $transaction['type_transaction'] === 'debit' ? 'danger' : 
                                                    ($transaction['type_transaction'] === 'credit' ? 'success' : 
                                                    ($transaction['type_transaction'] === 'recharge' ? 'primary' : 'warning')); 
                                            ?>">
                                                <?php echo ucfirst($transaction['type_transaction']); ?>
                                            </span>
                                        </td>
                                        <td class="fw-bold <?php echo $transaction['type_transaction'] === 'debit' ? 'text-danger' : 'text-success'; ?>">
                                            <?php echo ($transaction['type_transaction'] === 'debit' ? '-' : '+') . formatMoney($transaction['montant']); ?>
                                        </td>
                                        <td><?php echo formatMoney($transaction['solde_avant']); ?></td>
                                        <td class="fw-bold"><?php echo formatMoney($transaction['solde_apres']); ?></td>
                                        <td><?php echo htmlspecialchars($transaction['description']); ?></td>
                                        <td>
                                            <?php if ($transaction['user_nom']): ?>
                                                <?php echo htmlspecialchars($transaction['user_nom'] . ' ' . $transaction['user_prenom']); ?>
                                            <?php else: ?>
                                                <span class="text-muted">Système</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Historique des paiements -->
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-money-bill-wave me-2"></i>
                    Historique des Paiements
                </h5>
            </div>
            <div class="card-body">
                <?php if (empty($paiements)): ?>
                    <div class="text-center py-4">
                        <i class="fas fa-info-circle fa-2x text-muted mb-3"></i>
                        <p class="text-muted">Aucun paiement enregistré pour cette carte.</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Montant</th>
                                    <th>Mode de paiement</th>
                                    <th>Statut</th>
                                    <th>Référence</th>
                                    <th>Utilisateur</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($paiements as $paiement): ?>
                                    <tr>
                                        <td><?php echo formatDateTime($paiement['date_paiement']); ?></td>
                                        <td class="fw-bold text-success"><?php echo formatMoney($paiement['montant']); ?></td>
                                        <td>
                                            <span class="badge bg-secondary">
                                                <?php echo ucfirst(str_replace('_', ' ', $paiement['type_paiement'])); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?php 
                                                echo $paiement['status'] === 'valide' ? 'success' : 
                                                    ($paiement['status'] === 'en_attente' ? 'warning' : 
                                                    ($paiement['status'] === 'annule' ? 'secondary' : 'danger')); 
                                            ?>">
                                                <?php echo ucfirst($paiement['status']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo htmlspecialchars($paiement['reference']); ?></td>
                                        <td>
                                            <?php if ($paiement['user_nom']): ?>
                                                <?php echo htmlspecialchars($paiement['user_nom'] . ' ' . $paiement['user_prenom']); ?>
                                            <?php else: ?>
                                                <span class="text-muted">Système</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <a href="receipt.php?id=<?php echo $paiement['id']; ?>" class="btn btn-outline-primary" title="Voir reçu">
                                                    <i class="fas fa-receipt"></i>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php include '../../../includes/footer.php'; ?>
