<?php
/**
 * Module Recouvrement - Impression d'une Carte
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

// Calculer les statistiques
$montant_limite = $carte['montant_limite'] ?? 0;
$montant_utilise = $carte['montant_utilise'] ?? 0;
$solde_actuel = $montant_limite - $montant_utilise;
$pourcentage_utilise = $montant_limite > 0 ? ($montant_utilise / $montant_limite) * 100 : 0;

// Récupérer les dernières transactions
try {
    $transactions = $database->query(
        "SELECT * FROM transactions_cartes WHERE carte_id = ? ORDER BY created_at DESC LIMIT 10",
        [$carte_id]
    )->fetchAll();
} catch (Exception $e) {
    $transactions = [];
}

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Impression Carte - <?php echo htmlspecialchars($carte['numero_carte']); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        @media print {
            .no-print {
                display: none !important;
            }
            .page-break {
                page-break-before: always;
            }
            body {
                margin: 0;
                padding: 20px;
            }
            .card {
                border: 1px solid #000;
                page-break-inside: avoid;
            }
        }
        
        .card-header {
            background: linear-gradient(135deg, #2c3e50, #34495e);
            color: white;
        }
        
        .carte-info {
            background: #f8f9fa;
            border: 2px solid #dee2e6;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
        }
        
        .numero-carte {
            font-size: 2rem;
            font-weight: bold;
            color: #2c3e50;
            text-align: center;
            letter-spacing: 2px;
            margin: 10px 0;
        }
        
        .statut-badge {
            font-size: 1.2rem;
            padding: 8px 16px;
        }
        
        .progress {
            height: 25px;
            font-size: 0.9rem;
        }
        
        .table th {
            background-color: #f8f9fa;
            font-weight: bold;
        }
        
        .footer {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #dee2e6;
            text-align: center;
            font-size: 0.9rem;
            color: #6c757d;
        }
        
        .qr-code-placeholder {
            width: 100px;
            height: 100px;
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto;
        }
    </style>
</head>
<body>
    <!-- Boutons d'impression -->
    <div class="no-print mb-3">
        <div class="container">
            <div class="d-flex justify-content-between align-items-center">
                <h4>Impression de la Carte</h4>
                <div class="btn-group">
                    <button onclick="window.print()" class="btn btn-primary">
                        <i class="fas fa-print me-2"></i>Imprimer
                    </button>
                    <a href="view.php?id=<?php echo $carte_id; ?>" class="btn btn-secondary">
                        <i class="fas fa-arrow-left me-2"></i>Retour
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="container">
        <!-- En-tête de l'établissement -->
        <div class="row mb-4">
            <div class="col-12 text-center">
                <h2 class="mb-1">ÉCOLE SINFINITY</h2>
                <p class="mb-1">Avenue de la Paix, Kinshasa - République Démocratique du Congo</p>
                <p class="mb-0">Tél: +243 123 456 789 | Email: contact@sinfinity-school.cd</p>
                <hr class="my-3">
                <h3 class="text-primary">CARTE ÉLÈVE</h3>
            </div>
        </div>

        <!-- Informations principales -->
        <div class="row">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-credit-card me-2"></i>
                            Informations de la Carte
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="numero-carte">
                            <?php echo htmlspecialchars($carte['numero_carte']); ?>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <table class="table table-borderless">
                                    <tr>
                                        <td class="fw-bold">Type :</td>
                                        <td>
                                            <span class="badge bg-<?php 
                                                echo $carte['type_carte'] === 'premium' ? 'warning' : 
                                                    ($carte['type_carte'] === 'temporaire' ? 'info' : 'secondary'); 
                                            ?> statut-badge">
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
                                            ?> statut-badge">
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
                                </table>
                                
                                <div class="mt-3">
                                    <label class="fw-bold">Utilisation :</label>
                                    <div class="progress">
                                        <div class="progress-bar bg-<?php echo $pourcentage_utilise > 80 ? 'danger' : ($pourcentage_utilise > 60 ? 'warning' : 'success'); ?>" 
                                             style="width: <?php echo $pourcentage_utilise; ?>%">
                                            <?php echo number_format($pourcentage_utilise, 1); ?>%
                                        </div>
                                    </div>
                                </div>
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
                            <div class="qr-code-placeholder">
                                <i class="fas fa-qrcode fa-2x text-muted"></i>
                            </div>
                            <small class="text-muted d-block mt-2">Code QR pour scan</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Dernières transactions -->
        <?php if (!empty($transactions)): ?>
        <div class="row mt-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-history me-2"></i>
                            Dernières Transactions
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Type</th>
                                        <th>Montant</th>
                                        <th>Solde après</th>
                                        <th>Description</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($transactions as $transaction): ?>
                                        <tr>
                                            <td><?php echo formatDate($transaction['created_at']); ?></td>
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
                                            <td class="fw-bold"><?php echo formatMoney($transaction['solde_apres']); ?></td>
                                            <td><?php echo htmlspecialchars($transaction['description']); ?></td>
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

        <!-- Conditions d'utilisation -->
        <div class="row mt-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-info-circle me-2"></i>
                            Conditions d'Utilisation
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <h6>Utilisation :</h6>
                                <ul class="small">
                                    <li>Cette carte est personnelle et non transférable</li>
                                    <li>Présentez la carte à chaque transaction</li>
                                    <li>Conservez la carte en bon état</li>
                                    <li>Signalez immédiatement toute perte ou vol</li>
                                </ul>
                            </div>
                            <div class="col-md-6">
                                <h6>Limitations :</h6>
                                <ul class="small">
                                                                         <li>Montant limite : <?php echo formatMoney($montant_limite); ?></li>
                                    <li>Validité : <?php echo $carte['date_expiration'] ? formatDate($carte['date_expiration']) : 'Illimitée'; ?></li>
                                    <li>Utilisation uniquement dans l'établissement</li>
                                    <li>Pas de remboursement en espèces</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Pied de page -->
        <div class="footer">
            <p><strong>École Sinfinity</strong> - Système de Gestion des Cartes Élèves</p>
                         <p>Document généré le <?php echo date('d/m/Y à H:i'); ?> par <?php echo htmlspecialchars(isset($_SESSION['user_nom']) ? $_SESSION['user_nom'] : '') . ' ' . htmlspecialchars(isset($_SESSION['user_prenom']) ? $_SESSION['user_prenom'] : ''); ?></p>
            <p>Pour toute question, contactez l'administration de l'école</p>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
