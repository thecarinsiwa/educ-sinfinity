<?php
/**
 * Module Finance - Export des paiements en PDF
 * Application de gestion scolaire - République Démocratique du Congo
 */

require_once '../../../config/config.php';
require_once '../../../config/database.php';
require_once '../../../includes/functions.php';
require_once '../../../includes/permissions-pages.php';
require_once '../../../includes/ui-permissions.php';

// Vérifier l'authentification et les permissions
requireLogin();
requirePagePermissionFromDB('finance', 'payments/export', 'read', '../../dashboard.php');

// Récupérer les paramètres
$format = sanitizeInput($_GET['format'] ?? 'pdf');
$eleve_id = (int)($_GET['eleve_id'] ?? 0);
$annee_scolaire_id = (int)($_GET['annee_scolaire_id'] ?? 0);

if (!$eleve_id) {
    showMessage('error', 'ID élève non spécifié.');
    redirectTo('index.php');
}

// Récupérer l'année scolaire
if (!$annee_scolaire_id) {
    $current_year = getCurrentAcademicYear();
    if (!$current_year) {
        showMessage('error', 'Aucune année scolaire active.');
        redirectTo('index.php');
    }
    $annee_scolaire_id = $current_year['id'];
}

// Récupérer les informations de l'élève
$eleve = $database->query(
    "SELECT e.*, c.nom as classe_nom, c.niveau, a.annee as annee_scolaire
     FROM eleves e
     JOIN inscriptions i ON e.id = i.eleve_id AND i.annee_scolaire_id = ?
     JOIN classes c ON i.classe_id = c.id
     JOIN annees_scolaires a ON i.annee_scolaire_id = a.id
     WHERE e.id = ? AND i.status = 'inscrit'",
    [$annee_scolaire_id, $eleve_id]
)->fetch();

if (!$eleve) {
    showMessage('error', 'Élève non trouvé ou non inscrit pour cette année scolaire.');
    redirectTo('index.php');
}

// Récupérer l'historique des paiements
$paiements = $database->query(
    "SELECT p.*, d.code as devise_code, d.symbole as devise_symbole, d.nom as devise_nom,
            u.username as enregistre_par
     FROM paiements p
     LEFT JOIN devises d ON p.devise_id = d.id
     LEFT JOIN users u ON p.user_id = u.id
     WHERE p.eleve_id = ? AND p.annee_scolaire_id = ?
     ORDER BY p.date_paiement DESC, p.created_at DESC",
    [$eleve_id, $annee_scolaire_id]
)->fetchAll();

// Récupérer les informations de l'établissement
$etablissement = $database->query("SELECT * FROM etablissements LIMIT 1")->fetch();

// Obtenir la devise par défaut
$devise_par_defaut = getDefaultCurrency();

// Calculer les statistiques
$stats = [
    'total_paiements' => count($paiements),
    'montant_total' => 0,
    'montant_inscription' => 0,
    'montant_mensualites' => 0,
    'montant_autres' => 0,
    'dernier_paiement' => null
];

foreach ($paiements as $paiement) {
    $montant = $paiement['montant_devise_par_defaut'] ?? $paiement['montant'];
    $stats['montant_total'] += $montant;
    
    switch ($paiement['type_paiement']) {
        case 'inscription':
            $stats['montant_inscription'] += $montant;
            break;
        case 'mensualite':
            $stats['montant_mensualites'] += $montant;
            break;
        default:
            $stats['montant_autres'] += $montant;
            break;
    }
    
    if (!$stats['dernier_paiement']) {
        $stats['dernier_paiement'] = $paiement;
    }
}

// Générer le PDF
if ($format === 'pdf') {
    // Inclure TCPDF
    require_once '../../../vendor/tcpdf/tcpdf.php';
    
    // Créer une nouvelle instance PDF
    $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
    
    // Informations du document
    $pdf->SetCreator('Educ-Sinfinity');
    $pdf->SetAuthor($etablissement['nom'] ?? 'École');
    $pdf->SetTitle('Historique des paiements - ' . $eleve['nom'] . ' ' . $eleve['prenom']);
    $pdf->SetSubject('Rapport financier');
    
    // Supprimer l'en-tête et le pied de page par défaut
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);
    
    // Définir les marges
    $pdf->SetMargins(15, 15, 15);
    $pdf->SetAutoPageBreak(TRUE, 25);
    
    // Ajouter une page
    $pdf->AddPage();
    
    // Couleurs
    $pdf->SetTextColor(0, 0, 0);
    $pdf->SetFillColor(52, 152, 219);
    $pdf->SetDrawColor(0, 0, 0);
    
    // En-tête
    $pdf->SetFont('helvetica', 'B', 16);
    $pdf->Cell(0, 10, $etablissement['nom'] ?? 'ÉCOLE', 0, 1, 'C');
    
    $pdf->SetFont('helvetica', '', 10);
    $pdf->Cell(0, 5, $etablissement['adresse'] ?? '', 0, 1, 'C');
    $pdf->Cell(0, 5, 'Tél: ' . ($etablissement['telephone'] ?? '') . ' | Email: ' . ($etablissement['email'] ?? ''), 0, 1, 'C');
    
    // Ligne de séparation
    $pdf->Ln(5);
    $pdf->SetLineWidth(0.5);
    $pdf->Line(15, $pdf->GetY(), 195, $pdf->GetY());
    $pdf->Ln(10);
    
    // Titre du rapport
    $pdf->SetFont('helvetica', 'B', 14);
    $pdf->Cell(0, 8, 'HISTORIQUE DES PAIEMENTS', 0, 1, 'C');
    $pdf->Ln(5);
    
    // Informations de l'élève
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->Cell(0, 6, 'INFORMATIONS DE L\'ÉLÈVE', 0, 1, 'L');
    $pdf->Ln(2);
    
    $pdf->SetFont('helvetica', '', 10);
    $pdf->Cell(40, 5, 'Nom complet:', 0, 0, 'L');
    $pdf->Cell(0, 5, $eleve['nom'] . ' ' . $eleve['prenom'], 0, 1, 'L');
    
    $pdf->Cell(40, 5, 'Matricule:', 0, 0, 'L');
    $pdf->Cell(0, 5, $eleve['numero_matricule'], 0, 1, 'L');
    
    $pdf->Cell(40, 5, 'Classe:', 0, 0, 'L');
    $pdf->Cell(0, 5, $eleve['classe_nom'] . ' (' . ucfirst($eleve['niveau']) . ')', 0, 1, 'L');
    
    $pdf->Cell(40, 5, 'Année scolaire:', 0, 0, 'L');
    $pdf->Cell(0, 5, $eleve['annee_scolaire'], 0, 1, 'L');
    
    $pdf->Cell(40, 5, 'Date de naissance:', 0, 0, 'L');
    $pdf->Cell(0, 5, formatDate($eleve['date_naissance']), 0, 1, 'L');
    
    $pdf->Ln(8);
    
    // Statistiques
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->Cell(0, 6, 'RÉSUMÉ FINANCIER', 0, 1, 'L');
    $pdf->Ln(2);
    
    $pdf->SetFont('helvetica', '', 10);
    $pdf->Cell(60, 5, 'Nombre total de paiements:', 0, 0, 'L');
    $pdf->Cell(0, 5, $stats['total_paiements'], 0, 1, 'L');
    
    $pdf->Cell(60, 5, 'Montant total payé:', 0, 0, 'L');
    $pdf->Cell(0, 5, formatMoney($stats['montant_total']) . ' ' . ($devise_par_defaut['symbole'] ?? 'FC'), 0, 1, 'L');
    
    $pdf->Cell(60, 5, 'Frais d\'inscription:', 0, 0, 'L');
    $pdf->Cell(0, 5, formatMoney($stats['montant_inscription']) . ' ' . ($devise_par_defaut['symbole'] ?? 'FC'), 0, 1, 'L');
    
    $pdf->Cell(60, 5, 'Mensualités:', 0, 0, 'L');
    $pdf->Cell(0, 5, formatMoney($stats['montant_mensualites']) . ' ' . ($devise_par_defaut['symbole'] ?? 'FC'), 0, 1, 'L');
    
    $pdf->Cell(60, 5, 'Autres frais:', 0, 0, 'L');
    $pdf->Cell(0, 5, formatMoney($stats['montant_autres']) . ' ' . ($devise_par_defaut['symbole'] ?? 'FC'), 0, 1, 'L');
    
    if ($stats['dernier_paiement']) {
        $pdf->Cell(60, 5, 'Dernier paiement:', 0, 0, 'L');
        $pdf->Cell(0, 5, formatDate($stats['dernier_paiement']['date_paiement']), 0, 1, 'L');
    }
    
    $pdf->Ln(10);
    
    // Tableau des paiements
    if (!empty($paiements)) {
        $pdf->SetFont('helvetica', 'B', 12);
        $pdf->Cell(0, 6, 'DÉTAIL DES PAIEMENTS', 0, 1, 'L');
        $pdf->Ln(3);
        
        // En-tête du tableau
        $pdf->SetFont('helvetica', 'B', 9);
        $pdf->SetFillColor(52, 152, 219);
        $pdf->SetTextColor(255, 255, 255);
        
        $pdf->Cell(25, 8, 'Date', 1, 0, 'C', true);
        $pdf->Cell(30, 8, 'Type', 1, 0, 'C', true);
        $pdf->Cell(50, 8, 'Description', 1, 0, 'C', true);
        $pdf->Cell(25, 8, 'Montant', 1, 0, 'C', true);
        $pdf->Cell(20, 8, 'Devise', 1, 0, 'C', true);
        $pdf->Cell(25, 8, 'Mode', 1, 0, 'C', true);
        $pdf->Cell(20, 8, 'Reçu', 1, 1, 'C', true);
        
        // Contenu du tableau
        $pdf->SetFont('helvetica', '', 8);
        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetFillColor(245, 245, 245);
        
        $fill = false;
        foreach ($paiements as $paiement) {
            $pdf->Cell(25, 6, date('d/m/Y', strtotime($paiement['date_paiement'])), 1, 0, 'C', $fill);
            
            $type_labels = [
                'inscription' => 'Inscription',
                'mensualite' => 'Mensualité',
                'examen' => 'Examen',
                'uniforme' => 'Uniforme',
                'transport' => 'Transport',
                'cantine' => 'Cantine',
                'autre' => 'Autre'
            ];
            $pdf->Cell(30, 6, $type_labels[$paiement['type_paiement']] ?? ucfirst($paiement['type_paiement']), 1, 0, 'C', $fill);
            
            $description = $paiement['observation'] ?: 'Paiement de ' . ($type_labels[$paiement['type_paiement']] ?? $paiement['type_paiement']);
            $pdf->Cell(50, 6, substr($description, 0, 30) . (strlen($description) > 30 ? '...' : ''), 1, 0, 'L', $fill);
            
            $montant = $paiement['montant_devise_par_defaut'] ?? $paiement['montant'];
            $pdf->Cell(25, 6, formatMoney($montant), 1, 0, 'R', $fill);
            
            $pdf->Cell(20, 6, $paiement['devise_code'] ?? 'FC', 1, 0, 'C', $fill);
            
            $mode_labels = [
                'especes' => 'Espèces',
                'cheque' => 'Chèque',
                'virement' => 'Virement',
                'mobile_money' => 'Mobile Money'
            ];
            $pdf->Cell(25, 6, $mode_labels[$paiement['mode_paiement']] ?? ucfirst($paiement['mode_paiement']), 1, 0, 'C', $fill);
            
            $pdf->Cell(20, 6, $paiement['recu_numero'] ?? '-', 1, 1, 'C', $fill);
            
            $fill = !$fill;
        }
    } else {
        $pdf->SetFont('helvetica', '', 10);
        $pdf->Cell(0, 10, 'Aucun paiement enregistré pour cet élève.', 0, 1, 'C');
    }
    
    // Pied de page
    $pdf->Ln(15);
    $pdf->SetFont('helvetica', '', 8);
    $pdf->SetTextColor(128, 128, 128);
    $pdf->Cell(0, 5, 'Rapport généré le ' . date('d/m/Y à H:i') . ' par Educ-Sinfinity', 0, 1, 'C');
    $pdf->Cell(0, 5, 'Système de Gestion Scolaire - République Démocratique du Congo', 0, 1, 'C');
    
    // Nom du fichier
    $filename = 'paiements_' . $eleve['numero_matricule'] . '_' . date('Y-m-d') . '.pdf';
    
    // Envoyer le PDF au navigateur
    $pdf->Output($filename, 'D');
    exit;
    
} else {
    // Format non supporté
    showMessage('error', 'Format d\'export non supporté.');
    redirectTo('index.php');
}
?>
