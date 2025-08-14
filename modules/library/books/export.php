<?php
/**
 * Module Bibliothèque - Export des livres
 * Application de gestion scolaire - République Démocratique du Congo
 */

require_once '../../../config/config.php';
require_once '../../../config/database.php';
require_once '../../../includes/functions.php';

// Vérifier l'authentification et les permissions
requireLogin();
if (!checkPermission('library') && !checkPermission('library_view')) {
    showMessage('error', 'Accès refusé à ce module.');
    redirectTo('../index.php');
}

// Récupérer le format d'export
$format = strtolower($_GET['format'] ?? '');
$valid_formats = ['csv', 'excel', 'pdf'];

// Si aucun format n'est spécifié, afficher la page de sélection
if (empty($format)) {
    include '../../../includes/header.php';
    ?>
    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
        <h1 class="h2">
            <i class="fas fa-download me-2"></i>
            Export du catalogue
        </h1>
        <div class="btn-toolbar mb-2 mb-md-0">
            <a href="index.php" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-1"></i>
                Retour au catalogue
            </a>
        </div>
    </div>

    <div class="row">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-file-export me-2"></i>
                        Choisir le format d'export
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <div class="card border-primary h-100">
                                <div class="card-body text-center">
                                    <i class="fas fa-file-csv fa-3x text-primary mb-3"></i>
                                    <h5 class="card-title">Export CSV</h5>
                                    <p class="card-text">Format texte séparé par des points-virgules, compatible avec Excel et autres tableurs.</p>
                                    <a href="export.php?format=csv" class="btn btn-primary">
                                        <i class="fas fa-download me-1"></i>
                                        Exporter en CSV
                                    </a>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4 mb-3">
                            <div class="card border-success h-100">
                                <div class="card-body text-center">
                                    <i class="fas fa-file-excel fa-3x text-success mb-3"></i>
                                    <h5 class="card-title">Export Excel</h5>
                                    <p class="card-text">Format Excel (.xlsx) avec mise en forme et styles automatiques.</p>
                                    <a href="export.php?format=excel" class="btn btn-success">
                                        <i class="fas fa-download me-1"></i>
                                        Exporter en Excel
                                    </a>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4 mb-3">
                            <div class="card border-danger h-100">
                                <div class="card-body text-center">
                                    <i class="fas fa-file-pdf fa-3x text-danger mb-3"></i>
                                    <h5 class="card-title">Export PDF</h5>
                                    <p class="card-text">Document PDF formaté avec tableau et mise en page professionnelle.</p>
                                    <a href="export.php?format=pdf" class="btn btn-danger">
                                        <i class="fas fa-download me-1"></i>
                                        Exporter en PDF
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-info-circle me-2"></i>
                        Informations
                    </h5>
                </div>
                <div class="card-body">
                    <p><strong>Données exportées :</strong></p>
                    <ul class="mb-3">
                        <li>Informations complètes des livres</li>
                        <li>Statistiques d'emprunt</li>
                        <li>Catégories et statuts</li>
                        <li>Dates de création et modification</li>
                    </ul>
                    <p><strong>Note :</strong> L'export inclut tous les livres du catalogue, même ceux filtrés sur la page principale.</p>
                </div>
            </div>
        </div>
    </div>
    <?php
    include '../../../includes/footer.php';
    exit;
}

if (!in_array($format, $valid_formats)) {
    showMessage('error', 'Format d\'export non valide.');
    redirectTo('index.php');
}

// Paramètres de filtrage (optionnels)
$search = trim($_GET['search'] ?? '');
$category_filter = intval($_GET['category'] ?? 0);
$status_filter = $_GET['status'] ?? '';
$etat_filter = $_GET['etat'] ?? '';

// Construire l'URL de base pour les liens d'export
$export_base_url = 'export.php?format=' . $format;
if ($search) $export_base_url .= '&search=' . urlencode($search);
if ($category_filter) $export_base_url .= '&category=' . $category_filter;
if ($status_filter) $export_base_url .= '&status=' . urlencode($status_filter);
if ($etat_filter) $export_base_url .= '&etat=' . urlencode($etat_filter);

// Construction de la requête
$where_conditions = ["1=1"];
$params = [];

if ($search) {
    $where_conditions[] = "(l.titre LIKE ? OR l.auteur LIKE ? OR l.isbn LIKE ? OR l.editeur LIKE ?)";
    $search_param = "%$search%";
    $params = array_merge($params, [$search_param, $search_param, $search_param, $search_param]);
}

if ($category_filter) {
    $where_conditions[] = "l.categorie_id = ?";
    $params[] = $category_filter;
}

if ($status_filter) {
    $where_conditions[] = "l.status = ?";
    $params[] = $status_filter;
}

if ($etat_filter) {
    $where_conditions[] = "l.etat = ?";
    $params[] = $etat_filter;
}

$where_clause = implode(' AND ', $where_conditions);

// Récupérer tous les livres (sans pagination pour l'export)
try {
    $livres = $database->query(
        "SELECT l.*, cl.nom as categorie_nom, cl.couleur as categorie_couleur,
                COUNT(el.id) as nb_emprunts_total,
                COUNT(CASE WHEN el.status = 'en_cours' THEN 1 END) as nb_emprunts_actifs
         FROM livres l
         LEFT JOIN categories_livres cl ON l.categorie_id = cl.id
         LEFT JOIN emprunts_livres el ON l.id = el.livre_id
         WHERE $where_clause
         GROUP BY l.id
         ORDER BY l.titre",
        $params
    )->fetchAll();

    // Compter le total
    $total_livres = $database->query(
        "SELECT COUNT(DISTINCT l.id) as total FROM livres l WHERE $where_clause",
        $params
    )->fetch()['total'];

} catch (Exception $e) {
    showMessage('error', 'Erreur lors du chargement : ' . $e->getMessage());
    redirectTo('index.php');
}

// Préparer les données pour l'export
$export_data = [];
foreach ($livres as $livre) {
    $export_data[] = [
        'ID' => $livre['id'],
        'Titre' => $livre['titre'],
        'Auteur' => $livre['auteur'],
        'ISBN' => $livre['isbn'],
        'Éditeur' => $livre['editeur'],
        'Année' => $livre['annee_edition'],
        'Catégorie' => $livre['categorie_nom'],
        'Statut' => ucfirst($livre['status']),
        'État' => ucfirst($livre['etat']),
        'Prix' => number_format($livre['prix'] ?? 0, 0, ',', ' ') . ' FC',
        'Emprunts totaux' => $livre['nb_emprunts_total'],
        'Emprunts actifs' => $livre['nb_emprunts_actifs'],
        'Date d\'ajout' => formatDate($livre['created_at']),
        'Dernière modification' => formatDate($livre['updated_at'])
    ];
}

// Générer le nom du fichier
$filename = 'catalogue_livres_' . date('Y-m-d_H-i-s');

// Fonction pour formater les statuts
function formatStatus($status) {
    $status_labels = [
        'disponible' => 'Disponible',
        'emprunte' => 'Emprunté',
        'reserve' => 'Réservé',
        'perdu' => 'Perdu',
        'retire' => 'Retiré'
    ];
    return $status_labels[$status] ?? ucfirst($status);
}

// Fonction pour formater l'état
function formatEtat($etat) {
    $etat_labels = [
        'excellent' => 'Excellent',
        'bon' => 'Bon',
        'moyen' => 'Moyen',
        'mauvais' => 'Mauvais',
        'tres_mauvais' => 'Très mauvais'
    ];
    return $etat_labels[$etat] ?? ucfirst($etat);
}

// Export selon le format
switch ($format) {
    case 'csv':
        exportCSV($export_data, $filename);
        break;
    case 'excel':
        exportExcel($export_data, $filename);
        break;
    case 'pdf':
        exportPDF($export_data, $filename);
        break;
}

function exportCSV($data, $filename) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
    
    // BOM pour UTF-8
    echo "\xEF\xBB\xBF";
    
    $output = fopen('php://output', 'w');
    
    // En-têtes
    if (!empty($data)) {
        fputcsv($output, array_keys($data[0]), ';');
    }
    
    // Données
    foreach ($data as $row) {
        fputcsv($output, $row, ';');
    }
    
    fclose($output);
    exit;
}

function exportExcel($data, $filename) {
    // Inclure la bibliothèque PhpSpreadsheet si disponible
    if (!class_exists('PhpOffice\PhpSpreadsheet\Spreadsheet')) {
        // Fallback vers CSV avec extension .xls
        header('Content-Type: application/vnd.ms-excel');
        header('Content-Disposition: attachment; filename="' . $filename . '.xls"');
        
        echo "\xEF\xBB\xBF"; // BOM UTF-8
        
        echo '<table border="1">';
        
        // En-têtes
        if (!empty($data)) {
            echo '<tr>';
            foreach (array_keys($data[0]) as $header) {
                echo '<th>' . htmlspecialchars($header) . '</th>';
            }
            echo '</tr>';
        }
        
        // Données
        foreach ($data as $row) {
            echo '<tr>';
            foreach ($row as $value) {
                echo '<td>' . htmlspecialchars($value) . '</td>';
            }
            echo '</tr>';
        }
        
        echo '</table>';
        exit;
    }
    
    // Code pour PhpSpreadsheet (si disponible)
    require_once '../../../vendor/autoload.php';
    
    $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    
    // En-têtes
    if (!empty($data)) {
        $headers = array_keys($data[0]);
        $col = 'A';
        foreach ($headers as $header) {
            $sheet->setCellValue($col . '1', $header);
            $sheet->getColumnDimension($col)->setAutoSize(true);
            $col++;
        }
        
        // Données
        $row = 2;
        foreach ($data as $rowData) {
            $col = 'A';
            foreach ($rowData as $value) {
                $sheet->setCellValue($col . $row, $value);
                $col++;
            }
            $row++;
        }
    }
    
    // Style des en-têtes
    $sheet->getStyle('A1:' . $sheet->getHighestColumn() . '1')->getFont()->setBold(true);
    
    // Créer le fichier
    $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
    
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $filename . '.xlsx"');
    header('Cache-Control: max-age=0');
    
    $writer->save('php://output');
    exit;
}

function exportPDF($data, $filename) {
    // Inclure TCPDF
    require_once '../../../vendor/tcpdf/tcpdf.php';
    
    // Créer un nouveau document PDF
    $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
    
    // Définir les informations du document
    $pdf->SetCreator('Educ-Sinfinity');
    $pdf->SetAuthor('Système de gestion scolaire');
    $pdf->SetTitle('Catalogue des Livres');
    $pdf->SetSubject('Export du catalogue de la bibliothèque');
    
    // Supprimer les en-têtes et pieds de page par défaut
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);
    
    // Définir les marges
    $pdf->SetMargins(15, 15, 15);
    $pdf->SetHeaderMargin(5);
    $pdf->SetFooterMargin(10);
    
    // Définir l'espacement automatique des paragraphes
    $pdf->SetAutoPageBreak(TRUE, 25);
    
    // Définir la police
    $pdf->SetFont('helvetica', '', 10);
    
    // Ajouter une page
    $pdf->AddPage();
    
    // Titre
    $pdf->SetFont('helvetica', 'B', 16);
    $pdf->Cell(0, 10, 'Catalogue des Livres', 0, 1, 'C');
    $pdf->Ln(5);
    
    // Informations sur l'export
    $pdf->SetFont('helvetica', '', 10);
    $pdf->Cell(0, 6, 'Date d\'export : ' . date('d/m/Y à H:i'), 0, 1);
    $pdf->Cell(0, 6, 'Total de livres : ' . count($data), 0, 1);
    $pdf->Ln(5);
    
    // Tableau des données
    if (!empty($data)) {
        $pdf->SetFont('helvetica', 'B', 8);
        
        // En-têtes du tableau
        $headers = array_keys($data[0]);
        $colWidths = [15, 40, 25, 20, 25, 15, 20, 20, 20, 20, 25, 25, 25, 25];
        
        // Dessiner les en-têtes
        $pdf->SetFillColor(240, 240, 240);
        $x = $pdf->GetX();
        $y = $pdf->GetY();
        
        foreach ($headers as $i => $header) {
            $pdf->Cell($colWidths[$i], 8, $header, 1, 0, 'C', true);
        }
        $pdf->Ln();
        
        // Données
        $pdf->SetFont('helvetica', '', 7);
        $pdf->SetFillColor(255, 255, 255);
        
        foreach ($data as $row) {
            $rowValues = array_values($row);
            
            // Vérifier si on a besoin d'une nouvelle page
            if ($pdf->GetY() > 250) {
                $pdf->AddPage();
                // Redessiner les en-têtes
                $pdf->SetFont('helvetica', 'B', 8);
                $pdf->SetFillColor(240, 240, 240);
                foreach ($headers as $i => $header) {
                    $pdf->Cell($colWidths[$i], 8, $header, 1, 0, 'C', true);
                }
                $pdf->Ln();
                $pdf->SetFont('helvetica', '', 7);
                $pdf->SetFillColor(255, 255, 255);
            }
            
            foreach ($rowValues as $i => $value) {
                // Tronquer le texte si nécessaire
                $text = strlen($value) > 20 ? substr($value, 0, 17) . '...' : $value;
                $pdf->Cell($colWidths[$i], 6, $text, 1, 0, 'L', true);
            }
            $pdf->Ln();
        }
    } else {
        $pdf->SetFont('helvetica', '', 12);
        $pdf->Cell(0, 10, 'Aucun livre trouvé.', 0, 1, 'C');
    }
    
    // Pied de page
    $pdf->SetY(-15);
    $pdf->SetFont('helvetica', 'I', 8);
    $pdf->Cell(0, 10, 'Page ' . $pdf->getAliasNumPage() . '/' . $pdf->getAliasNbPages(), 0, 0, 'C');
    
    // Sortie du PDF
    $pdf->Output($filename . '.pdf', 'D');
    exit;
}

// Si on arrive ici, c'est qu'il y a eu une erreur
showMessage('error', 'Erreur lors de l\'export.');
redirectTo('index.php');
?>
