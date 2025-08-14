<?php
/**
 * Module Académique - Export d'emploi du temps
 * Application de gestion scolaire - République Démocratique du Congo
 */

require_once '../../../config/config.php';
require_once '../../../config/database.php';
require_once '../../../includes/functions.php';

// Vérifier l'authentification et les permissions
requireLogin();
if (!checkPermission('admin') && !checkPermission('academic')) {
    showMessage('error', 'Accès refusé à cette fonctionnalité.');
    redirectTo('../../../index.php');
}

// Récupérer l'année scolaire active
$current_year = $database->query("SELECT * FROM annees_scolaires WHERE status = 'active' LIMIT 1")->fetch();
if (!$current_year) {
    showMessage('error', 'Aucune année scolaire active trouvée.');
    redirectTo('../../../index.php');
}

// Paramètres d'export
$format = $_GET['format'] ?? 'pdf';
$classe_id = $_GET['classe_id'] ?? '';
$enseignant_id = $_GET['enseignant_id'] ?? '';
$export_type = $_GET['type'] ?? 'classe'; // classe, enseignant, global

// Récupérer les données selon le type d'export
$emploi_temps = [];
$title = '';

if ($export_type === 'classe' && $classe_id) {
    // Export pour une classe spécifique
    $classe = $database->query(
        "SELECT * FROM classes WHERE id = ? AND annee_scolaire_id = ?",
        [$classe_id, $current_year['id']]
    )->fetch();
    
    if (!$classe) {
        showMessage('error', 'Classe non trouvée.');
        redirectTo('generate.php');
    }
    
    $emploi_temps = $database->query(
        "SELECT et.*, m.nom as matiere_nom, m.code as matiere_code,
                CONCAT(p.nom, ' ', p.prenom) as enseignant_nom,
                c.nom as classe_nom, c.niveau
         FROM emploi_temps et
         JOIN matieres m ON et.matiere_id = m.id
         JOIN personnel p ON et.enseignant_id = p.id
         JOIN classes c ON et.classe_id = c.id
         WHERE et.classe_id = ? AND et.annee_scolaire_id = ?
         ORDER BY 
            CASE et.jour_semaine 
                WHEN 'Lundi' THEN 1 
                WHEN 'Mardi' THEN 2 
                WHEN 'Mercredi' THEN 3 
                WHEN 'Jeudi' THEN 4 
                WHEN 'Vendredi' THEN 5 
                WHEN 'Samedi' THEN 6 
                ELSE 7 
            END, et.heure_debut",
        [$classe_id, $current_year['id']]
    )->fetchAll();
    
    $title = 'Emploi du temps - ' . $classe['niveau'] . ' ' . $classe['nom'];
    
} elseif ($export_type === 'enseignant' && $enseignant_id) {
    // Export pour un enseignant spécifique
    $enseignant = $database->query(
        "SELECT * FROM personnel WHERE id = ? AND fonction = 'enseignant'",
        [$enseignant_id]
    )->fetch();
    
    if (!$enseignant) {
        showMessage('error', 'Enseignant non trouvé.');
        redirectTo('generate.php');
    }
    
    $emploi_temps = $database->query(
        "SELECT et.*, m.nom as matiere_nom, m.code as matiere_code,
                CONCAT(p.nom, ' ', p.prenom) as enseignant_nom,
                c.nom as classe_nom, c.niveau
         FROM emploi_temps et
         JOIN matieres m ON et.matiere_id = m.id
         JOIN personnel p ON et.enseignant_id = p.id
         JOIN classes c ON et.classe_id = c.id
         WHERE et.enseignant_id = ? AND et.annee_scolaire_id = ?
         ORDER BY 
            CASE et.jour_semaine 
                WHEN 'Lundi' THEN 1 
                WHEN 'Mardi' THEN 2 
                WHEN 'Mercredi' THEN 3 
                WHEN 'Jeudi' THEN 4 
                WHEN 'Vendredi' THEN 5 
                WHEN 'Samedi' THEN 6 
                ELSE 7 
            END, et.heure_debut",
        [$enseignant_id, $current_year['id']]
    )->fetchAll();
    
    $title = 'Emploi du temps - ' . $enseignant['nom'] . ' ' . $enseignant['prenom'];
    
} else {
    // Export global de tous les emplois du temps
    $emploi_temps = $database->query(
        "SELECT et.*, m.nom as matiere_nom, m.code as matiere_code,
                CONCAT(p.nom, ' ', p.prenom) as enseignant_nom,
                c.nom as classe_nom, c.niveau
         FROM emploi_temps et
         JOIN matieres m ON et.matiere_id = m.id
         JOIN personnel p ON et.enseignant_id = p.id
         JOIN classes c ON et.classe_id = c.id
         WHERE et.annee_scolaire_id = ?
         ORDER BY c.niveau, c.nom,
            CASE et.jour_semaine 
                WHEN 'Lundi' THEN 1 
                WHEN 'Mardi' THEN 2 
                WHEN 'Mercredi' THEN 3 
                WHEN 'Jeudi' THEN 4 
                WHEN 'Vendredi' THEN 5 
                WHEN 'Samedi' THEN 6 
                ELSE 7 
            END, et.heure_debut",
        [$current_year['id']]
    )->fetchAll();
    
    $title = 'Emplois du temps - Année scolaire ' . $current_year['annee'];
}

// Organiser les données par jour et heure pour l'affichage en grille
function organizeScheduleData($emploi_temps) {
    $organized = [];
    $jours = ['Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi'];
    
    foreach ($emploi_temps as $cours) {
        $key = $cours['jour_semaine'] . '_' . $cours['heure_debut'];
        $organized[$key] = $cours;
    }
    
    return $organized;
}

$organized_schedule = organizeScheduleData($emploi_temps);

// Traitement selon le format demandé
if ($format === 'pdf') {
    // Export PDF
    require_once '../../../vendor/tcpdf/tcpdf.php';
    
    // Créer un nouveau document PDF
    $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
    
    // Informations du document
    $pdf->SetCreator('Educ-Sinfinity');
    $pdf->SetAuthor('École Sinfinity');
    $pdf->SetTitle($title);
    $pdf->SetSubject('Emploi du temps');
    
    // Supprimer header/footer par défaut
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);
    
    // Marges
    $pdf->SetMargins(10, 10, 10);
    $pdf->SetAutoPageBreak(TRUE, 10);
    
    // Ajouter une page
    $pdf->AddPage();
    
    // Police
    $pdf->SetFont('helvetica', 'B', 16);
    
    // Titre
    $pdf->Cell(0, 10, $title, 0, 1, 'C');
    $pdf->Cell(0, 5, 'Année scolaire ' . $current_year['annee'], 0, 1, 'C');
    $pdf->Ln(10);
    
    // Tableau
    $pdf->SetFont('helvetica', 'B', 10);
    
    // En-têtes
    $pdf->Cell(25, 8, 'Jour', 1, 0, 'C');
    $pdf->Cell(25, 8, 'Heure', 1, 0, 'C');
    $pdf->Cell(40, 8, 'Matière', 1, 0, 'C');
    $pdf->Cell(40, 8, 'Enseignant', 1, 0, 'C');
    if ($export_type !== 'classe') {
        $pdf->Cell(30, 8, 'Classe', 1, 0, 'C');
    }
    $pdf->Cell(25, 8, 'Salle', 1, 1, 'C');
    
    // Données
    $pdf->SetFont('helvetica', '', 9);
    foreach ($emploi_temps as $cours) {
        $pdf->Cell(25, 6, $cours['jour_semaine'], 1, 0, 'C');
        $pdf->Cell(25, 6, $cours['heure_debut'] . '-' . $cours['heure_fin'], 1, 0, 'C');
        $pdf->Cell(40, 6, $cours['matiere_nom'], 1, 0, 'L');
        $pdf->Cell(40, 6, $cours['enseignant_nom'], 1, 0, 'L');
        if ($export_type !== 'classe') {
            $pdf->Cell(30, 6, $cours['classe_nom'], 1, 0, 'C');
        }
        $pdf->Cell(25, 6, $cours['salle'] ?: '-', 1, 1, 'C');
    }
    
    // Pied de page
    $pdf->Ln(10);
    $pdf->SetFont('helvetica', 'I', 8);
    $pdf->Cell(0, 5, 'Généré le ' . date('d/m/Y à H:i'), 0, 1, 'R');
    
    // Sortie du PDF
    $filename = 'emploi_temps_' . date('Y-m-d_H-i-s') . '.pdf';
    $pdf->Output($filename, 'D');
    
} elseif ($format === 'excel') {
    // Export Excel (CSV)
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="emploi_temps_' . date('Y-m-d_H-i-s') . '.csv"');
    
    $output = fopen('php://output', 'w');
    
    // BOM pour UTF-8
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    // En-têtes
    $headers = ['Jour', 'Heure début', 'Heure fin', 'Matière', 'Enseignant', 'Salle'];
    if ($export_type !== 'classe') {
        array_splice($headers, 5, 0, 'Classe');
    }
    fputcsv($output, $headers, ';');
    
    // Données
    foreach ($emploi_temps as $cours) {
        $row = [
            $cours['jour_semaine'],
            $cours['heure_debut'],
            $cours['heure_fin'],
            $cours['matiere_nom'],
            $cours['enseignant_nom'],
            $cours['salle'] ?: ''
        ];
        
        if ($export_type !== 'classe') {
            array_splice($row, 5, 0, $cours['classe_nom']);
        }
        
        fputcsv($output, $row, ';');
    }
    
    fclose($output);
    
} elseif ($format === 'html') {
    // Export HTML pour impression
    ?>
    <!DOCTYPE html>
    <html lang="fr">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title><?php echo htmlspecialchars($title); ?></title>
        <style>
            body { font-family: Arial, sans-serif; margin: 20px; }
            .header { text-align: center; margin-bottom: 30px; }
            .header h1 { margin: 0; color: #333; }
            .header p { margin: 5px 0; color: #666; }
            table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
            th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
            th { background-color: #f2f2f2; font-weight: bold; }
            .center { text-align: center; }
            .footer { margin-top: 30px; text-align: right; font-size: 12px; color: #666; }
            @media print {
                body { margin: 0; }
                .no-print { display: none; }
            }
        </style>
    </head>
    <body>
        <div class="header">
            <h1><?php echo htmlspecialchars($title); ?></h1>
            <p>Année scolaire <?php echo htmlspecialchars($current_year['annee']); ?></p>
        </div>
        
        <div class="no-print" style="margin-bottom: 20px;">
            <button onclick="window.print()">Imprimer</button>
            <button onclick="window.close()">Fermer</button>
        </div>
        
        <table>
            <thead>
                <tr>
                    <th>Jour</th>
                    <th>Heure</th>
                    <th>Matière</th>
                    <th>Enseignant</th>
                    <?php if ($export_type !== 'classe'): ?>
                        <th>Classe</th>
                    <?php endif; ?>
                    <th>Salle</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($emploi_temps as $cours): ?>
                    <tr>
                        <td class="center"><?php echo htmlspecialchars($cours['jour_semaine']); ?></td>
                        <td class="center"><?php echo htmlspecialchars($cours['heure_debut'] . ' - ' . $cours['heure_fin']); ?></td>
                        <td><?php echo htmlspecialchars($cours['matiere_nom']); ?></td>
                        <td><?php echo htmlspecialchars($cours['enseignant_nom']); ?></td>
                        <?php if ($export_type !== 'classe'): ?>
                            <td class="center"><?php echo htmlspecialchars($cours['classe_nom']); ?></td>
                        <?php endif; ?>
                        <td class="center"><?php echo htmlspecialchars($cours['salle'] ?: '-'); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        
        <div class="footer">
            Généré le <?php echo date('d/m/Y à H:i'); ?>
        </div>
        
        <script>
            // Auto-print si demandé
            if (new URLSearchParams(window.location.search).get('auto_print') === '1') {
                window.onload = function() {
                    window.print();
                };
            }
        </script>
    </body>
    </html>
    <?php
    
} else {
    // Format non supporté
    showMessage('error', 'Format d\'export non supporté.');
    redirectTo('generate.php');
}

// Enregistrer l'action
logUserAction(
    'export_schedule',
    'academic',
    'Export emploi du temps - Format: ' . $format . ', Type: ' . $export_type,
    null
);

exit;
?>
