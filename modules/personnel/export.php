<?php
/**
 * Module de gestion du personnel - Export des données
 * Application de gestion scolaire - République Démocratique du Congo
 */

require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../includes/functions.php';

// Vérifier l'authentification et les permissions
requireLogin();
if (!checkPermission('personnel') && !checkPermission('personnel_view')) {
    showMessage('error', 'Accès refusé à cette fonctionnalité.');
    redirectTo('index.php');
}

// Paramètres d'export
$format = $_GET['format'] ?? 'excel';
$fonction_filter = $_GET['fonction'] ?? '';
$status_filter = $_GET['status'] ?? '';

// Construction de la requête
$sql = "SELECT p.*, u.username, u.role 
        FROM personnel p 
        LEFT JOIN users u ON p.user_id = u.id
        WHERE 1=1";

$params = [];

if (!empty($fonction_filter)) {
    $sql .= " AND p.fonction = ?";
    $params[] = $fonction_filter;
}

if (!empty($status_filter)) {
    $sql .= " AND p.status = ?";
    $params[] = $status_filter;
}

$sql .= " ORDER BY p.nom, p.prenom";

$personnel = $database->query($sql, $params)->fetchAll();

// Nom du fichier
$filename = 'personnel_' . date('Y-m-d_H-i-s');

if ($format === 'excel') {
    // Export Excel (CSV)
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
    
    // BOM pour UTF-8
    echo "\xEF\xBB\xBF";
    
    $output = fopen('php://output', 'w');
    
    // En-têtes
    fputcsv($output, [
        'Matricule',
        'Nom',
        'Prénom',
        'Sexe',
        'Date de naissance',
        'Lieu de naissance',
        'Téléphone',
        'Email',
        'Adresse',
        'Fonction',
        'Spécialité',
        'Diplôme',
        'Date d\'embauche',
        'Salaire de base',
        'Statut',
        'Compte utilisateur',
        'Rôle système'
    ], ';');
    
    // Données
    foreach ($personnel as $membre) {
        fputcsv($output, [
            $membre['matricule'],
            $membre['nom'],
            $membre['prenom'],
            $membre['sexe'] === 'M' ? 'Masculin' : 'Féminin',
            $membre['date_naissance'] ? formatDate($membre['date_naissance']) : '',
            $membre['lieu_naissance'],
            $membre['telephone'],
            $membre['email'],
            $membre['adresse'],
            ucfirst(str_replace('_', ' ', $membre['fonction'])),
            $membre['specialite'],
            $membre['diplome'],
            $membre['date_embauche'] ? formatDate($membre['date_embauche']) : '',
            $membre['salaire_base'] ? number_format($membre['salaire_base'], 0, ',', ' ') . ' FC' : '',
            ucfirst($membre['status']),
            $membre['username'] ?: 'Aucun',
            $membre['role'] ? ucfirst($membre['role']) : ''
        ], ';');
    }
    
    fclose($output);
    exit;
    
} elseif ($format === 'pdf') {
    // Export PDF (HTML vers PDF)
    ob_start();
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>Liste du Personnel</title>
        <style>
            body { font-family: Arial, sans-serif; font-size: 12px; }
            .header { text-align: center; margin-bottom: 20px; }
            .header h1 { margin: 0; color: #2c3e50; }
            .header p { margin: 5px 0; color: #666; }
            table { width: 100%; border-collapse: collapse; margin-top: 20px; }
            th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
            th { background-color: #f8f9fa; font-weight: bold; }
            .text-center { text-align: center; }
            .badge { padding: 2px 6px; border-radius: 3px; font-size: 10px; }
            .badge-success { background-color: #d4edda; color: #155724; }
            .badge-warning { background-color: #fff3cd; color: #856404; }
            .badge-danger { background-color: #f8d7da; color: #721c24; }
            .footer { margin-top: 30px; text-align: center; font-size: 10px; color: #666; }
        </style>
    </head>
    <body>
        <div class="header">
            <h1>Liste du Personnel</h1>
            <p>Établissement scolaire - République Démocratique du Congo</p>
            <p>Généré le <?php echo formatDate(date('Y-m-d')); ?> à <?php echo date('H:i'); ?></p>
            <?php if ($fonction_filter || $status_filter): ?>
                <p>
                    Filtres appliqués: 
                    <?php if ($fonction_filter): ?>Fonction: <?php echo ucfirst(str_replace('_', ' ', $fonction_filter)); ?><?php endif; ?>
                    <?php if ($status_filter): ?><?php echo $fonction_filter ? ', ' : ''; ?>Statut: <?php echo ucfirst($status_filter); ?><?php endif; ?>
                </p>
            <?php endif; ?>
        </div>
        
        <table>
            <thead>
                <tr>
                    <th>Matricule</th>
                    <th>Nom complet</th>
                    <th>Fonction</th>
                    <th>Contact</th>
                    <th>Embauche</th>
                    <th>Salaire</th>
                    <th>Statut</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($personnel as $membre): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($membre['matricule']); ?></td>
                        <td>
                            <?php echo htmlspecialchars($membre['nom'] . ' ' . $membre['prenom']); ?>
                            <br><small><?php echo $membre['sexe'] === 'M' ? 'M' : 'F'; ?></small>
                        </td>
                        <td>
                            <?php echo ucfirst(str_replace('_', ' ', $membre['fonction'])); ?>
                            <?php if ($membre['specialite']): ?>
                                <br><small><?php echo htmlspecialchars($membre['specialite']); ?></small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($membre['telephone']): ?>
                                <?php echo htmlspecialchars($membre['telephone']); ?><br>
                            <?php endif; ?>
                            <?php if ($membre['email']): ?>
                                <small><?php echo htmlspecialchars($membre['email']); ?></small>
                            <?php endif; ?>
                        </td>
                        <td class="text-center">
                            <?php echo $membre['date_embauche'] ? formatDate($membre['date_embauche']) : '-'; ?>
                        </td>
                        <td class="text-center">
                            <?php echo $membre['salaire_base'] ? number_format($membre['salaire_base'], 0, ',', ' ') . ' FC' : '-'; ?>
                        </td>
                        <td class="text-center">
                            <?php
                            $status_class = $membre['status'] === 'actif' ? 'success' : 
                                          ($membre['status'] === 'suspendu' ? 'warning' : 'danger');
                            ?>
                            <span class="badge badge-<?php echo $status_class; ?>">
                                <?php echo ucfirst($membre['status']); ?>
                            </span>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        
        <div class="footer">
            <p>Total: <?php echo count($personnel); ?> membre(s) du personnel</p>
            <p>Document généré par le système de gestion scolaire Educ-Sinfinity</p>
        </div>
    </body>
    </html>
    <?php
    
    $html = ob_get_clean();
    
    // Headers pour PDF
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . $filename . '.pdf"');
    
    // Note: Pour une vraie conversion PDF, vous devriez utiliser une bibliothèque comme TCPDF ou DomPDF
    // Ici, nous retournons le HTML qui peut être converti côté client
    echo $html;
    exit;
    
} else {
    // Format non supporté
    showMessage('error', 'Format d\'export non supporté.');
    redirectTo('index.php');
}
?>
