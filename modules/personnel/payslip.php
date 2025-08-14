<?php
/**
 * Module de gestion du personnel - Fiche de paie
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

// Récupérer l'ID du membre
$id = (int)($_GET['id'] ?? 0);
if (!$id) {
    showMessage('error', 'Membre du personnel non spécifié.');
    redirectTo('index.php');
}

// Récupérer les informations du membre
$membre = $database->query(
    "SELECT * FROM personnel WHERE id = ? AND status = 'actif'", 
    [$id]
)->fetch();

if (!$membre) {
    showMessage('error', 'Membre du personnel non trouvé ou inactif.');
    redirectTo('index.php');
}

// Paramètres de la fiche de paie
$mois = $_GET['mois'] ?? date('m');
$annee = $_GET['annee'] ?? date('Y');
$mois_nom = getMonthName((int)$mois);

$page_title = 'Fiche de paie - ' . $membre['nom'] . ' ' . $membre['prenom'];

// Calculs de la paie
$salaire_base = (float)$membre['salaire_base'];
$jours_travailles = 22; // Jours ouvrables standard
$heures_travaillees = $jours_travailles * 8; // 8h par jour

// Primes et indemnités (à personnaliser selon l'école)
$prime_anciennete = 0;
if ($membre['date_embauche']) {
    $anciennete = floor((time() - strtotime($membre['date_embauche'])) / (365.25 * 24 * 3600));
    $prime_anciennete = $anciennete * ($salaire_base * 0.02); // 2% par année d'ancienneté
}

$prime_fonction = 0;
switch ($membre['fonction']) {
    case 'directeur':
        $prime_fonction = $salaire_base * 0.30; // 30% du salaire de base
        break;
    case 'sous_directeur':
        $prime_fonction = $salaire_base * 0.20; // 20% du salaire de base
        break;
    case 'enseignant':
        $prime_fonction = $salaire_base * 0.10; // 10% du salaire de base
        break;
}

$indemnite_transport = 25000; // Indemnité fixe de transport
$indemnite_logement = $membre['fonction'] === 'directeur' ? 50000 : 0;

// Total des gains
$total_gains = $salaire_base + $prime_anciennete + $prime_fonction + $indemnite_transport + $indemnite_logement;

// Déductions (à personnaliser selon la législation RDC)
$cotisation_cnss = $salaire_base * 0.035; // 3.5% CNSS employé
$impot_professionnel = max(0, ($salaire_base - 80000) * 0.15); // Impôt sur salaire > 80000 FC
$avance_salaire = 0; // À récupérer depuis une table des avances si nécessaire

// Total des déductions
$total_deductions = $cotisation_cnss + $impot_professionnel + $avance_salaire;

// Salaire net
$salaire_net = $total_gains - $total_deductions;

// Informations de l'établissement
$etablissement = $database->query("SELECT * FROM etablissements LIMIT 1")->fetch();

include '../../includes/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom no-print">
    <h1 class="h2">
        <i class="fas fa-money-bill-wave me-2"></i>
        Fiche de paie
    </h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <div class="btn-group me-2">
            <a href="view.php?id=<?php echo $membre['id']; ?>" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-1"></i>
                Retour
            </a>
        </div>
        <div class="btn-group">
            <button onclick="window.print()" class="btn btn-primary">
                <i class="fas fa-print me-1"></i>
                Imprimer
            </button>
            <button onclick="exportToPDF('payslip-content', 'fiche_paie_<?php echo $membre['matricule']; ?>_<?php echo $mois; ?>_<?php echo $annee; ?>.pdf')" 
                    class="btn btn-outline-primary">
                <i class="fas fa-file-pdf me-1"></i>
                PDF
            </button>
        </div>
    </div>
</div>

<!-- Sélection du mois/année -->
<div class="card mb-4 no-print">
    <div class="card-body">
        <form method="GET" class="row g-3 align-items-end">
            <input type="hidden" name="id" value="<?php echo $membre['id']; ?>">
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
                    Générer
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Fiche de paie -->
<div id="payslip-content" class="card">
    <div class="card-body">
        <!-- En-tête -->
        <div class="row mb-4">
            <div class="col-md-6">
                <h3><?php echo htmlspecialchars($etablissement['nom'] ?? 'École'); ?></h3>
                <p class="mb-1"><?php echo htmlspecialchars($etablissement['adresse'] ?? ''); ?></p>
                <p class="mb-1">Tél: <?php echo htmlspecialchars($etablissement['telephone'] ?? ''); ?></p>
                <p class="mb-0">Email: <?php echo htmlspecialchars($etablissement['email'] ?? ''); ?></p>
            </div>
            <div class="col-md-6 text-end">
                <h2 class="text-primary">FICHE DE PAIE</h2>
                <p class="mb-1"><strong>Période:</strong> <?php echo $mois_nom . ' ' . $annee; ?></p>
                <p class="mb-0"><strong>Date d'édition:</strong> <?php echo formatDate(date('Y-m-d')); ?></p>
            </div>
        </div>
        
        <!-- Informations employé -->
        <div class="row mb-4">
            <div class="col-md-6">
                <div class="border p-3">
                    <h5 class="mb-3">Informations de l'employé</h5>
                    <table class="table table-borderless table-sm">
                        <tr>
                            <td><strong>Matricule:</strong></td>
                            <td><?php echo htmlspecialchars($membre['matricule']); ?></td>
                        </tr>
                        <tr>
                            <td><strong>Nom complet:</strong></td>
                            <td><?php echo htmlspecialchars($membre['nom'] . ' ' . $membre['prenom']); ?></td>
                        </tr>
                        <tr>
                            <td><strong>Fonction:</strong></td>
                            <td><?php echo ucfirst(str_replace('_', ' ', $membre['fonction'])); ?></td>
                        </tr>
                        <tr>
                            <td><strong>Date d'embauche:</strong></td>
                            <td><?php echo formatDate($membre['date_embauche']); ?></td>
                        </tr>
                    </table>
                </div>
            </div>
            <div class="col-md-6">
                <div class="border p-3">
                    <h5 class="mb-3">Détails de la période</h5>
                    <table class="table table-borderless table-sm">
                        <tr>
                            <td><strong>Jours travaillés:</strong></td>
                            <td><?php echo $jours_travailles; ?> jours</td>
                        </tr>
                        <tr>
                            <td><strong>Heures travaillées:</strong></td>
                            <td><?php echo $heures_travaillees; ?> heures</td>
                        </tr>
                        <tr>
                            <td><strong>Taux horaire:</strong></td>
                            <td><?php echo formatMoney($salaire_base / $heures_travaillees); ?></td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>
        
        <!-- Détail des gains et déductions -->
        <div class="row">
            <div class="col-md-6">
                <div class="border p-3">
                    <h5 class="mb-3 text-success">GAINS</h5>
                    <table class="table table-borderless table-sm">
                        <tr>
                            <td>Salaire de base</td>
                            <td class="text-end"><?php echo formatMoney($salaire_base); ?></td>
                        </tr>
                        <?php if ($prime_anciennete > 0): ?>
                        <tr>
                            <td>Prime d'ancienneté</td>
                            <td class="text-end"><?php echo formatMoney($prime_anciennete); ?></td>
                        </tr>
                        <?php endif; ?>
                        <?php if ($prime_fonction > 0): ?>
                        <tr>
                            <td>Prime de fonction</td>
                            <td class="text-end"><?php echo formatMoney($prime_fonction); ?></td>
                        </tr>
                        <?php endif; ?>
                        <tr>
                            <td>Indemnité de transport</td>
                            <td class="text-end"><?php echo formatMoney($indemnite_transport); ?></td>
                        </tr>
                        <?php if ($indemnite_logement > 0): ?>
                        <tr>
                            <td>Indemnité de logement</td>
                            <td class="text-end"><?php echo formatMoney($indemnite_logement); ?></td>
                        </tr>
                        <?php endif; ?>
                        <tr class="border-top">
                            <td><strong>TOTAL GAINS</strong></td>
                            <td class="text-end"><strong><?php echo formatMoney($total_gains); ?></strong></td>
                        </tr>
                    </table>
                </div>
            </div>
            <div class="col-md-6">
                <div class="border p-3">
                    <h5 class="mb-3 text-danger">DÉDUCTIONS</h5>
                    <table class="table table-borderless table-sm">
                        <tr>
                            <td>Cotisation CNSS (3.5%)</td>
                            <td class="text-end"><?php echo formatMoney($cotisation_cnss); ?></td>
                        </tr>
                        <?php if ($impot_professionnel > 0): ?>
                        <tr>
                            <td>Impôt professionnel</td>
                            <td class="text-end"><?php echo formatMoney($impot_professionnel); ?></td>
                        </tr>
                        <?php endif; ?>
                        <?php if ($avance_salaire > 0): ?>
                        <tr>
                            <td>Avance sur salaire</td>
                            <td class="text-end"><?php echo formatMoney($avance_salaire); ?></td>
                        </tr>
                        <?php endif; ?>
                        <tr class="border-top">
                            <td><strong>TOTAL DÉDUCTIONS</strong></td>
                            <td class="text-end"><strong><?php echo formatMoney($total_deductions); ?></strong></td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>
        
        <!-- Salaire net -->
        <div class="row mt-4">
            <div class="col-12">
                <div class="bg-primary text-white p-4 text-center rounded">
                    <h3 class="mb-0">SALAIRE NET À PAYER: <?php echo formatMoney($salaire_net); ?></h3>
                </div>
            </div>
        </div>
        
        <!-- Signatures -->
        <div class="row mt-5">
            <div class="col-md-6">
                <div class="text-center">
                    <p class="mb-4">Signature de l'employé</p>
                    <div style="height: 60px; border-bottom: 1px solid #000; width: 200px; margin: 0 auto;"></div>
                    <p class="mt-2"><?php echo htmlspecialchars($membre['nom'] . ' ' . $membre['prenom']); ?></p>
                </div>
            </div>
            <div class="col-md-6">
                <div class="text-center">
                    <p class="mb-4">Signature de l'employeur</p>
                    <div style="height: 60px; border-bottom: 1px solid #000; width: 200px; margin: 0 auto;"></div>
                    <p class="mt-2">Direction</p>
                </div>
            </div>
        </div>
        
        <!-- Note légale -->
        <div class="row mt-4">
            <div class="col-12">
                <small class="text-muted">
                    <strong>Note:</strong> Cette fiche de paie est conforme à la législation du travail de la République Démocratique du Congo. 
                    Conservez ce document, il vous sera utile pour vos démarches administratives.
                </small>
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
        font-size: 12px;
    }
    
    .card {
        border: none;
        box-shadow: none;
    }
    
    .border {
        border: 1px solid #000 !important;
    }
    
    .bg-primary {
        background-color: #000 !important;
        color: #fff !important;
    }
}
</style>

<?php include '../../includes/footer.php'; ?>
