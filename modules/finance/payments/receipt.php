<?php
/**
 * Module de gestion financière - Reçu de paiement
 * Application de gestion scolaire - République Démocratique du Congo
 */

require_once '../../../config/config.php';
require_once '../../../config/database.php';
require_once '../../../includes/functions.php';
require_once '../../../includes/permissions-pages.php';

// Vérifier l'authentification et les permissions
requireLogin();
requirePagePermissionFromDB('finance', 'payments/receipt', 'read', '../../dashboard.php');

// Récupérer l'ID du paiement
$id = (int)($_GET['id'] ?? 0);
if (!$id) {
    showMessage('error', 'Paiement non spécifié.');
    redirectTo('index.php');
}

// Récupérer les informations du paiement avec devise
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

$page_title = 'Reçu de paiement - ' . $paiement['recu_numero'];

// Obtenir la devise par défaut
$devise_par_defaut = getDefaultCurrency();

// Informations de l'établissement
$etablissement = $database->query("SELECT * FROM etablissements LIMIT 1")->fetch();

// Convertir le montant en lettres
function nombreEnLettres($nombre) {
    $unites = ['', 'un', 'deux', 'trois', 'quatre', 'cinq', 'six', 'sept', 'huit', 'neuf'];
    $dizaines = ['', '', 'vingt', 'trente', 'quarante', 'cinquante', 'soixante', 'soixante-dix', 'quatre-vingt', 'quatre-vingt-dix'];
    $centaines = ['', 'cent', 'deux cents', 'trois cents', 'quatre cents', 'cinq cents', 'six cents', 'sept cents', 'huit cents', 'neuf cents'];
    
    if ($nombre == 0) return 'zéro';
    if ($nombre < 0) return 'moins ' . nombreEnLettres(-$nombre);
    
    $resultat = '';
    
    // Millions
    if ($nombre >= 1000000) {
        $millions = intval($nombre / 1000000);
        $resultat .= nombreEnLettres($millions) . ' million' . ($millions > 1 ? 's' : '') . ' ';
        $nombre %= 1000000;
    }
    
    // Milliers
    if ($nombre >= 1000) {
        $milliers = intval($nombre / 1000);
        if ($milliers == 1) {
            $resultat .= 'mille ';
        } else {
            $resultat .= nombreEnLettres($milliers) . ' mille ';
        }
        $nombre %= 1000;
    }
    
    // Centaines
    if ($nombre >= 100) {
        $cent = intval($nombre / 100);
        $resultat .= $centaines[$cent] . ' ';
        $nombre %= 100;
    }
    
    // Dizaines et unités
    if ($nombre >= 20) {
        $dix = intval($nombre / 10);
        $resultat .= $dizaines[$dix];
        $nombre %= 10;
        if ($nombre > 0) {
            $resultat .= '-' . $unites[$nombre];
        }
    } elseif ($nombre >= 10) {
        $dizainesSpeciales = ['dix', 'onze', 'douze', 'treize', 'quatorze', 'quinze', 'seize', 'dix-sept', 'dix-huit', 'dix-neuf'];
        $resultat .= $dizainesSpeciales[$nombre - 10];
    } elseif ($nombre > 0) {
        $resultat .= $unites[$nombre];
    }
    
    return trim($resultat);
}

// Formater le montant avec la devise
function formatMontant($montant, $devise_symbole = 'FC') {
    return number_format($montant, 2, ',', ' ') . ' ' . $devise_symbole;
}

// Déterminer la devise à utiliser
$devise_affichage = $paiement['devise_symbole'] ?? ($devise_par_defaut['symbole'] ?? 'FC');
$devise_code = $paiement['devise_code'] ?? ($devise_par_defaut['code'] ?? 'FC');

// Montant en lettres (utiliser le montant en devise par défaut si disponible)
$montant_pour_lettres = $paiement['montant_devise_par_defaut'] ?? $paiement['montant'];
$montant_lettres = ucfirst(nombreEnLettres(intval($montant_pour_lettres))) . ' ' . 
                   ($devise_par_defaut['nom'] ?? 'francs congolais');

include '../../../includes/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom no-print">
    <h1 class="h2">
        <i class="fas fa-receipt me-2"></i>
        Reçu de paiement
    </h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <div class="btn-group me-2">
                    <a href="index.php" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-left me-1"></i>
            Retour
        </a>
        <?php if ($devise_par_defaut): ?>
            <div class="btn-group me-2">
                <button type="button" class="btn btn-outline-info">
                    <i class="fas fa-exchange-alt me-1"></i>
                    Devise par défaut : <?php echo htmlspecialchars($devise_par_defaut['code']); ?> 
                    (<?php echo htmlspecialchars($devise_par_defaut['symbole']); ?>)
                </button>
            </div>
        <?php endif; ?>
        </div>
        <div class="btn-group">
            <button onclick="window.print()" class="btn btn-primary">
                <i class="fas fa-print me-1"></i>
                Imprimer
            </button>
            <button onclick="exportToPDFWithFallback('receipt-content', 'recu_<?php echo $paiement['recu_numero']; ?>.pdf', 'Reçu de paiement N° <?php echo $paiement['recu_numero']; ?>')"
                    class="btn btn-outline-primary">
                <i class="fas fa-file-pdf me-1"></i>
                PDF
            </button>
        </div>
    </div>
</div>

<!-- Reçu de paiement -->
<div id="receipt-content" class="card">
    <div class="card-body">
        <!-- En-tête -->
        <div class="row mb-4">
            <div class="col-md-8">
                <h2 class="text-primary"><?php echo htmlspecialchars($etablissement['nom'] ?? 'École'); ?></h2>
                <p class="mb-1"><?php echo htmlspecialchars($etablissement['adresse'] ?? ''); ?></p>
                <p class="mb-1">Tél: <?php echo htmlspecialchars($etablissement['telephone'] ?? ''); ?></p>
                <p class="mb-1">Email: <?php echo htmlspecialchars($etablissement['email'] ?? ''); ?></p>

            </div>
            <div class="col-md-4 text-end">
                <h1 class="text-success">REÇU DE PAIEMENT</h1>
                <div class="mt-3">
                    <h4 class="text-primary">N° <?php echo htmlspecialchars($paiement['recu_numero']); ?></h4>
                    <p class="mb-1"><strong>Date:</strong> <?php echo formatDate($paiement['date_paiement']); ?></p>
                    <p class="mb-0"><strong>Année scolaire:</strong> <?php echo htmlspecialchars($paiement['annee_scolaire']); ?></p>
                </div>
            </div>
        </div>
        
        <!-- Informations de l'élève -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="border p-3 bg-light">
                    <h5 class="mb-3">Informations de l'élève</h5>
                    <div class="row">
                        <div class="col-md-6">
                            <table class="table table-borderless table-sm">
                                <tr>
                                    <td class="fw-bold" style="width: 150px;">Nom complet :</td>
                                    <td><?php echo htmlspecialchars($paiement['nom'] . ' ' . $paiement['prenom']); ?></td>
                                </tr>
                                <tr>
                                    <td class="fw-bold">Matricule :</td>
                                    <td><?php echo htmlspecialchars($paiement['numero_matricule']); ?></td>
                                </tr>
                                <tr>
                                    <td class="fw-bold">Classe :</td>
                                    <td><?php echo htmlspecialchars($paiement['classe_nom']); ?></td>
                                </tr>
                            </table>
                        </div>
                        <div class="col-md-6">
                            <table class="table table-borderless table-sm">
                                <tr>
                                    <td class="fw-bold" style="width: 150px;">Niveau :</td>
                                    <td><?php echo ucfirst($paiement['niveau']); ?></td>
                                </tr>
                                <?php if ($paiement['date_naissance']): ?>
                                <tr>
                                    <td class="fw-bold">Date de naissance :</td>
                                    <td><?php echo formatDate($paiement['date_naissance']); ?></td>
                                </tr>
                                <?php endif; ?>
                                <tr>
                                    <td class="fw-bold">Année scolaire :</td>
                                    <td><?php echo htmlspecialchars($paiement['annee_scolaire']); ?></td>
                                </tr>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Détails du paiement -->
        <div class="row mb-4">
            <div class="col-12">
                <h5 class="mb-3">Détails du paiement</h5>
                <div class="table-responsive">
                    <table class="table table-bordered">
                        <thead class="table-dark">
                            <tr>
                                <th>Type de paiement</th>
                                <th>Description</th>
                                <th>Mode de paiement</th>
                                <th class="text-end">Montant</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>
                                    <strong>
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
                                    </strong>
                                </td>
                                <td>
                                    <?php echo htmlspecialchars($paiement['observation'] ?: 'Paiement de ' . $paiement['type_frais']); ?>
                                </td>
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
                                <td class="text-end">
                                    <strong class="fs-5"><?php echo formatMontant($paiement['montant'], $devise_affichage); ?></strong>
                                </td>
                            </tr>
                        </tbody>
                        <tfoot class="table-success">
                            <tr>
                                <th colspan="3" class="text-end">TOTAL PAYÉ :</th>
                                <th class="text-end">
                                    <h4 class="mb-0"><?php echo formatMontant($paiement['montant'], $devise_affichage); ?></h4>
                                </th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                
                <!-- Informations de devise et conversion -->
                <?php if ($paiement['devise_id'] && $paiement['montant_devise_par_defaut']): ?>
                <div class="row mt-3">
                    <div class="col-12">
                        <div class="alert alert-info">
                            <div class="row">
                                <div class="col-md-6">
                                    <strong>Devise du paiement :</strong> <?php echo htmlspecialchars($devise_code); ?> (<?php echo htmlspecialchars($devise_affichage); ?>)
                                </div>
                                <div class="col-md-6">
                                    <strong>Équivalent en devise par défaut :</strong> 
                                    <?php echo formatMontant($paiement['montant_devise_par_defaut'], $devise_par_defaut['symbole'] ?? 'FC'); ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Montant en lettres -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="border p-3">
                    <strong>Arrêté la présente facture à la somme de :</strong><br>
                    <em class="fs-5"><?php echo $montant_lettres; ?></em>
                </div>
            </div>
        </div>
        
        <!-- Statut du paiement -->
        <div class="row mb-4">
            <div class="col-md-6">
                <div class="alert alert-success">
                    <h6 class="mb-1">
                        <i class="fas fa-check-circle me-2"></i>
                        Statut : Paiement validé
                    </h6>
                    <small>Ce paiement a été validé et enregistré dans nos comptes.</small>
                </div>
            </div>
            <div class="col-md-6">
                <div class="text-end">
                    <p class="mb-1"><strong>Enregistré par :</strong> <?php echo htmlspecialchars($paiement['enregistre_par'] ?? 'Système'); ?></p>
                    <p class="mb-1"><strong>Date d'enregistrement :</strong> <?php echo formatDate($paiement['created_at'] ?? $paiement['date_paiement']); ?></p>
                    <p class="mb-0"><strong>Heure :</strong> <?php echo date('H:i', strtotime($paiement['created_at'] ?? $paiement['date_paiement'])); ?></p>
                </div>
            </div>
        </div>
        
        <!-- Signatures -->
        <div class="row mt-5">
            <div class="col-md-6">
                <div class="text-center">
                    <p class="mb-4">Signature du parent/tuteur</p>
                    <div style="height: 60px; border-bottom: 1px solid #000; width: 200px; margin: 0 auto;"></div>
                    <p class="mt-2">Date : _______________</p>
                </div>
            </div>
            <div class="col-md-6">
                <div class="text-center">
                    <p class="mb-4">Cachet et signature de l'école</p>
                    <div style="height: 60px; border-bottom: 1px solid #000; width: 200px; margin: 0 auto;"></div>
                    <p class="mt-2"><?php echo htmlspecialchars($etablissement['nom'] ?? 'École'); ?></p>
                </div>
            </div>
        </div>
        
        <!-- Note légale -->
        <div class="row mt-4">
            <div class="col-12">
                <div class="border-top pt-3">
                    <small class="text-muted">
                        <strong>Note importante :</strong> Ce reçu fait foi de paiement. Conservez-le précieusement pour vos démarches administratives. 
                        En cas de perte, une attestation de paiement pourra être délivrée moyennant des frais administratifs.
                        <br><br>
                        <strong>Conditions :</strong> Tout paiement effectué est définitif et ne peut faire l'objet d'un remboursement sauf cas exceptionnel 
                        validé par la direction. Les frais de scolarité sont dus même en cas d'absence de l'élève.
                    </small>
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
        font-size: 12px;
    }
    
    .card {
        border: none;
        box-shadow: none;
    }
    
    .table {
        font-size: 11px;
    }
    
    .alert {
        border: 1px solid #000 !important;
        background-color: #f8f9fa !important;
        color: #000 !important;
    }
    
    .text-primary {
        color: #000 !important;
    }
    
    .text-success {
        color: #000 !important;
    }
    
    .bg-light {
        background-color: #f8f9fa !important;
    }
    
    .table-dark th {
        background-color: #000 !important;
        color: #fff !important;
    }
    
    .table-success {
        background-color: #d4edda !important;
    }
}
</style>

<script>
// Initialiser les tooltips Bootstrap si disponible
if (typeof bootstrap !== 'undefined') {
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
}

// Fonction PDF simplifiée
function exportToPDFWithFallback(elementId, filename, title) {
    console.log('Début de la fonction PDF');
    
    // Vérifier si les bibliothèques sont disponibles
    if (typeof window.jsPDF !== 'undefined' && typeof html2canvas !== 'undefined') {
        console.log('Bibliothèques PDF disponibles, utilisation de exportToPDF');
        try {
            exportToPDF(elementId, filename, title);
        } catch (error) {
            console.error('Erreur avec exportToPDF:', error);
            // Fallback vers l'impression
            printReceipt(elementId, title);
        }
    } else {
        console.log('Bibliothèques PDF non disponibles, utilisation de l\'impression');
        printReceipt(elementId, title);
    }
}

// Fonction d'impression simplifiée
function printReceipt(elementId, title) {
    const element = document.getElementById(elementId);
    if (!element) {
        alert('Erreur: Élément à exporter non trouvé');
        return;
    }
    
    const printWindow = window.open('', '_blank', 'width=800,height=600');
    if (!printWindow) {
        alert('Erreur: Impossible d\'ouvrir la fenêtre d\'impression. Vérifiez que les popups ne sont pas bloqués.');
        return;
    }
    
    const currentDate = new Date().toLocaleDateString('fr-FR');
    const currentTime = new Date().toLocaleTimeString('fr-FR');
    
    printWindow.document.write('<!DOCTYPE html>');
    printWindow.document.write('<html lang="fr">');
    printWindow.document.write('<head>');
    printWindow.document.write('<meta charset="UTF-8">');
    printWindow.document.write('<title>' + title + ' - Educ-Sinfinity</title>');
    printWindow.document.write('<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">');
    printWindow.document.write('<style>');
    printWindow.document.write('@media print { body { font-size: 12px; } .no-print { display: none !important; } }');
    printWindow.document.write('@page { margin: 2cm; size: A4; }');
    printWindow.document.write('.print-header { text-align: center; margin-bottom: 30px; border-bottom: 2px solid #000; padding-bottom: 20px; }');
    printWindow.document.write('</style>');
    printWindow.document.write('</head>');
    printWindow.document.write('<body>');
    printWindow.document.write('<div class="print-header">');
    printWindow.document.write('<h2>EDUC-SINFINITY</h2>');
    printWindow.document.write('<p>Système de Gestion Scolaire - République Démocratique du Congo</p>');
    printWindow.document.write('<h3>' + title + '</h3>');
    printWindow.document.write('<p><small>Généré le ' + currentDate + ' à ' + currentTime + '</small></p>');
    printWindow.document.write('</div>');
    printWindow.document.write('<div class="print-content">');
    printWindow.document.write(element.innerHTML);
    printWindow.document.write('</div>');
    printWindow.document.write('</body>');
    printWindow.document.write('</html>');
    printWindow.document.close();
    
    // Lancer l'impression après un court délai
    setTimeout(function() {
        printWindow.print();
        setTimeout(function() {
            printWindow.close();
        }, 1000);
    }, 500);
}

// Debug: Vérifier les bibliothèques PDF
document.addEventListener('DOMContentLoaded', function() {
    console.log('=== DEBUG BIBLIOTHÈQUES PDF ===');
    console.log('window.jsPDF:', typeof window.jsPDF);
    console.log('jsPDF:', typeof jsPDF);
    console.log('html2canvas:', typeof html2canvas);
    
    if (typeof window.jsPDF !== 'undefined') {
        console.log('jsPDF disponible via window.jsPDF');
    } else if (typeof jsPDF !== 'undefined') {
        console.log('jsPDF disponible via jsPDF');
    } else {
        console.error('jsPDF non disponible');
    }
    
    if (typeof html2canvas !== 'undefined') {
        console.log('html2canvas disponible');
    } else {
        console.error('html2canvas non disponible');
    }
    console.log('=== FIN DEBUG ===');
});
</script>

<?php include '../../../includes/footer.php'; ?>
