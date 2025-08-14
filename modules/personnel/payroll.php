<?php
/**
 * Module de gestion du personnel - Gestion de la paie
 * Application de gestion scolaire - République Démocratique du Congo
 */

require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../includes/functions.php';

// Vérifier l'authentification et les permissions
requireLogin();
if (!checkPermission('personnel') && !checkPermission('finance')) {
    showMessage('error', 'Accès refusé à cette fonctionnalité.');
    redirectTo('index.php');
}

$page_title = 'Gestion de la Paie';

// Paramètres de la paie
$mois = $_GET['mois'] ?? date('m');
$annee = $_GET['annee'] ?? date('Y');
$mois_nom = getMonthName((int)$mois);

// Récupérer tout le personnel actif
$personnel = $database->query(
    "SELECT * FROM personnel WHERE status = 'actif' ORDER BY nom, prenom"
)->fetchAll();

// Calculer la paie pour chaque membre
$paie_data = [];
$total_salaires = 0;
$total_charges = 0;

foreach ($personnel as $membre) {
    $salaire_base = (float)$membre['salaire_base'];
    
    // Calcul des primes
    $prime_anciennete = 0;
    if ($membre['date_embauche']) {
        $anciennete = floor((time() - strtotime($membre['date_embauche'])) / (365.25 * 24 * 3600));
        $prime_anciennete = $anciennete * ($salaire_base * 0.02);
    }
    
    $prime_fonction = 0;
    switch ($membre['fonction']) {
        case 'directeur':
            $prime_fonction = $salaire_base * 0.30;
            break;
        case 'sous_directeur':
            $prime_fonction = $salaire_base * 0.20;
            break;
        case 'enseignant':
            $prime_fonction = $salaire_base * 0.10;
            break;
    }
    
    $indemnite_transport = 25000;
    $indemnite_logement = $membre['fonction'] === 'directeur' ? 50000 : 0;
    
    // Total gains
    $total_gains = $salaire_base + $prime_anciennete + $prime_fonction + $indemnite_transport + $indemnite_logement;
    
    // Déductions
    $cotisation_cnss = $salaire_base * 0.035;
    $impot_professionnel = max(0, ($salaire_base - 80000) * 0.15);
    $total_deductions = $cotisation_cnss + $impot_professionnel;
    
    // Salaire net
    $salaire_net = $total_gains - $total_deductions;
    
    // Charges patronales (CNSS employeur)
    $charges_patronales = $salaire_base * 0.065; // 6.5% CNSS employeur
    
    $paie_data[] = [
        'membre' => $membre,
        'salaire_base' => $salaire_base,
        'prime_anciennete' => $prime_anciennete,
        'prime_fonction' => $prime_fonction,
        'indemnite_transport' => $indemnite_transport,
        'indemnite_logement' => $indemnite_logement,
        'total_gains' => $total_gains,
        'cotisation_cnss' => $cotisation_cnss,
        'impot_professionnel' => $impot_professionnel,
        'total_deductions' => $total_deductions,
        'salaire_net' => $salaire_net,
        'charges_patronales' => $charges_patronales
    ];
    
    $total_salaires += $salaire_net;
    $total_charges += $charges_patronales;
}

include '../../includes/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">
        <i class="fas fa-money-bill-wave me-2"></i>
        Gestion de la Paie
    </h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <div class="btn-group me-2">
            <a href="index.php" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-1"></i>
                Retour
            </a>
        </div>
        <div class="btn-group">
            <button onclick="window.print()" class="btn btn-primary">
                <i class="fas fa-print me-1"></i>
                Imprimer
            </button>
            <button type="button" class="btn btn-outline-primary dropdown-toggle" data-bs-toggle="dropdown">
                <i class="fas fa-download me-1"></i>
                Exporter
            </button>
            <ul class="dropdown-menu">
                <li><a class="dropdown-item" href="export-payroll.php?format=excel&mois=<?php echo $mois; ?>&annee=<?php echo $annee; ?>">
                    <i class="fas fa-file-excel me-2"></i>Excel
                </a></li>
                <li><a class="dropdown-item" href="export-payroll.php?format=pdf&mois=<?php echo $mois; ?>&annee=<?php echo $annee; ?>">
                    <i class="fas fa-file-pdf me-2"></i>PDF
                </a></li>
            </ul>
        </div>
    </div>
</div>

<!-- Sélection période -->
<div class="card mb-4 no-print">
    <div class="card-body">
        <form method="GET" class="row g-3 align-items-end">
            <div class="col-md-4">
                <label for="mois" class="form-label">Mois</label>
                <select class="form-select" id="mois" name="mois">
                    <?php for ($m = 1; $m <= 12; $m++): ?>
                        <option value="<?php echo str_pad($m, 2, '0', STR_PAD_LEFT); ?>" 
                                <?php echo $mois == str_pad($m, 2, '0', STR_PAD_LEFT) ? 'selected' : ''; ?>>
                            <?php echo getMonthName($m); ?>
                        </option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label for="annee" class="form-label">Année</label>
                <select class="form-select" id="annee" name="annee">
                    <?php for ($a = date('Y') - 2; $a <= date('Y') + 1; $a++): ?>
                        <option value="<?php echo $a; ?>" <?php echo $annee == $a ? 'selected' : ''; ?>>
                            <?php echo $a; ?>
                        </option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="col-md-4">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-search me-1"></i>
                    Générer la paie
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Résumé de la paie -->
<div class="row mb-4">
    <div class="col-md-3">
        <div class="card text-white bg-primary">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h4><?php echo count($personnel); ?></h4>
                        <p class="mb-0">Employés</p>
                    </div>
                    <div class="align-self-center">
                        <i class="fas fa-users fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-white bg-success">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h5><?php echo formatMoney($total_salaires); ?></h5>
                        <p class="mb-0">Total salaires nets</p>
                    </div>
                    <div class="align-self-center">
                        <i class="fas fa-money-bill fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-white bg-warning">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h5><?php echo formatMoney($total_charges); ?></h5>
                        <p class="mb-0">Charges patronales</p>
                    </div>
                    <div class="align-self-center">
                        <i class="fas fa-percentage fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-white bg-info">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h5><?php echo formatMoney($total_salaires + $total_charges); ?></h5>
                        <p class="mb-0">Coût total</p>
                    </div>
                    <div class="align-self-center">
                        <i class="fas fa-calculator fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Tableau détaillé de la paie -->
<div class="card">
    <div class="card-header">
        <h5 class="mb-0">
            <i class="fas fa-table me-2"></i>
            Détail de la paie - <?php echo $mois_nom . ' ' . $annee; ?>
        </h5>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-striped table-hover">
                <thead class="table-dark">
                    <tr>
                        <th>Employé</th>
                        <th>Fonction</th>
                        <th class="text-end">Salaire base</th>
                        <th class="text-end">Primes</th>
                        <th class="text-end">Indemnités</th>
                        <th class="text-end">Total gains</th>
                        <th class="text-end">Déductions</th>
                        <th class="text-end">Salaire net</th>
                        <th class="text-center no-print">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($paie_data as $data): ?>
                        <tr>
                            <td>
                                <strong><?php echo htmlspecialchars($data['membre']['nom'] . ' ' . $data['membre']['prenom']); ?></strong>
                                <br><small class="text-muted"><?php echo htmlspecialchars($data['membre']['matricule']); ?></small>
                            </td>
                            <td>
                                <span class="badge bg-secondary">
                                    <?php echo ucfirst(str_replace('_', ' ', $data['membre']['fonction'])); ?>
                                </span>
                            </td>
                            <td class="text-end"><?php echo formatMoney($data['salaire_base']); ?></td>
                            <td class="text-end">
                                <?php 
                                $total_primes = $data['prime_anciennete'] + $data['prime_fonction'];
                                echo formatMoney($total_primes); 
                                ?>
                                <?php if ($total_primes > 0): ?>
                                    <br><small class="text-muted">
                                        <?php if ($data['prime_anciennete'] > 0): ?>
                                            Anc: <?php echo formatMoney($data['prime_anciennete']); ?><br>
                                        <?php endif; ?>
                                        <?php if ($data['prime_fonction'] > 0): ?>
                                            Fonc: <?php echo formatMoney($data['prime_fonction']); ?>
                                        <?php endif; ?>
                                    </small>
                                <?php endif; ?>
                            </td>
                            <td class="text-end">
                                <?php 
                                $total_indemnites = $data['indemnite_transport'] + $data['indemnite_logement'];
                                echo formatMoney($total_indemnites); 
                                ?>
                                <?php if ($total_indemnites > 0): ?>
                                    <br><small class="text-muted">
                                        Transport: <?php echo formatMoney($data['indemnite_transport']); ?>
                                        <?php if ($data['indemnite_logement'] > 0): ?>
                                            <br>Logement: <?php echo formatMoney($data['indemnite_logement']); ?>
                                        <?php endif; ?>
                                    </small>
                                <?php endif; ?>
                            </td>
                            <td class="text-end">
                                <strong><?php echo formatMoney($data['total_gains']); ?></strong>
                            </td>
                            <td class="text-end">
                                <?php echo formatMoney($data['total_deductions']); ?>
                                <?php if ($data['total_deductions'] > 0): ?>
                                    <br><small class="text-muted">
                                        CNSS: <?php echo formatMoney($data['cotisation_cnss']); ?>
                                        <?php if ($data['impot_professionnel'] > 0): ?>
                                            <br>Impôt: <?php echo formatMoney($data['impot_professionnel']); ?>
                                        <?php endif; ?>
                                    </small>
                                <?php endif; ?>
                            </td>
                            <td class="text-end">
                                <strong class="text-success"><?php echo formatMoney($data['salaire_net']); ?></strong>
                            </td>
                            <td class="text-center no-print">
                                <div class="btn-group btn-group-sm">
                                    <a href="payslip.php?id=<?php echo $data['membre']['id']; ?>&mois=<?php echo $mois; ?>&annee=<?php echo $annee; ?>" 
                                       class="btn btn-outline-primary" 
                                       title="Fiche de paie">
                                        <i class="fas fa-file-alt"></i>
                                    </a>
                                    <a href="view.php?id=<?php echo $data['membre']['id']; ?>" 
                                       class="btn btn-outline-info" 
                                       title="Voir détails">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot class="table-dark">
                    <tr>
                        <th colspan="7">TOTAUX</th>
                        <th class="text-end"><?php echo formatMoney($total_salaires); ?></th>
                        <th class="no-print"></th>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>

<!-- Charges patronales détaillées -->
<div class="card mt-4">
    <div class="card-header">
        <h5 class="mb-0">
            <i class="fas fa-chart-pie me-2"></i>
            Charges patronales
        </h5>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-6">
                <table class="table table-borderless">
                    <tr>
                        <td><strong>Total salaires bruts :</strong></td>
                        <td class="text-end"><?php echo formatMoney(array_sum(array_column($paie_data, 'salaire_base'))); ?></td>
                    </tr>
                    <tr>
                        <td><strong>CNSS employeur (6.5%) :</strong></td>
                        <td class="text-end"><?php echo formatMoney($total_charges); ?></td>
                    </tr>
                    <tr class="border-top">
                        <td><strong>Coût total employeur :</strong></td>
                        <td class="text-end"><strong><?php echo formatMoney($total_salaires + $total_charges); ?></strong></td>
                    </tr>
                </table>
            </div>
            <div class="col-md-6">
                <div class="alert alert-info">
                    <h6><i class="fas fa-info-circle me-2"></i>Informations</h6>
                    <ul class="mb-0">
                        <li>CNSS employé : 3.5% du salaire de base</li>
                        <li>CNSS employeur : 6.5% du salaire de base</li>
                        <li>Impôt professionnel : 15% sur la tranche > 80 000 FC</li>
                        <li>Prime d'ancienneté : 2% par année d'ancienneté</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
@media print {
    .no-print {
        display: none !important;
    }
    
    body {
        font-size: 11px;
    }
    
    .card {
        border: none;
        box-shadow: none;
    }
    
    .table {
        font-size: 10px;
    }
}
</style>

<?php include '../../includes/footer.php'; ?>
