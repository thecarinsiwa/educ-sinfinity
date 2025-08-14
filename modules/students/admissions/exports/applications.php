<?php
/**
 * Module Admissions - Export des demandes d'admission
 * Application de gestion scolaire - République Démocratique du Congo
 */

require_once '../../../../config/config.php';
require_once '../../../../config/database.php';
require_once '../../../../includes/functions.php';

// Vérifier l'authentification et les permissions
requireLogin();
if (!checkPermission('students') && !checkPermission('students_view')) {
    showMessage('error', 'Accès refusé à cette fonctionnalité.');
    redirectTo('../../index.php');
}

$page_title = 'Export des demandes d\'admission';

// Récupérer l'année scolaire active
$current_year = getCurrentAcademicYear();

if (!$current_year) {
    showMessage('error', 'Aucune année scolaire active.');
    redirectTo('../../index.php');
}

// Paramètres de filtrage
$status_filter = sanitizeInput($_GET['status'] ?? '');
$niveau_filter = sanitizeInput($_GET['niveau'] ?? '');
$classe_filter = (int)($_GET['classe'] ?? 0);
$periode_filter = sanitizeInput($_GET['periode'] ?? '');
$date_debut = sanitizeInput($_GET['date_debut'] ?? '');
$date_fin = sanitizeInput($_GET['date_fin'] ?? '');
$format = sanitizeInput($_GET['format'] ?? 'html');

// Construire les conditions WHERE
$where_conditions = ["da.annee_scolaire_id = ?"];
$params = [$current_year['id']];

if ($status_filter) {
    $where_conditions[] = "da.status = ?";
    $params[] = $status_filter;
}

if ($niveau_filter) {
    $where_conditions[] = "c.niveau = ?";
    $params[] = $niveau_filter;
}

if ($classe_filter) {
    $where_conditions[] = "da.classe_demandee_id = ?";
    $params[] = $classe_filter;
}

if ($periode_filter) {
    $where_conditions[] = "DATE_FORMAT(da.created_at, '%Y-%m') = ?";
    $params[] = $periode_filter;
}

if ($date_debut) {
    $where_conditions[] = "DATE(da.created_at) >= ?";
    $params[] = $date_debut;
}

if ($date_fin) {
    $where_conditions[] = "DATE(da.created_at) <= ?";
    $params[] = $date_fin;
}

$where_clause = implode(' AND ', $where_conditions);

// Récupérer les données
$demandes = $database->query(
    "SELECT 
        da.id,
        da.numero_demande,
        da.nom_eleve as nom_demandeur,
        da.prenom_eleve as prenom_demandeur,
        da.sexe as sexe_demandeur,
        da.date_naissance as date_naissance_demandeur,
        da.lieu_naissance as lieu_naissance_demandeur,
        da.adresse as adresse_demandeur,
        da.telephone as telephone_demandeur,
        da.email as email_demandeur,
        CONCAT(da.nom_pere, ' ', da.nom_mere) as nom_parent,
        '' as prenom_parent,
        da.telephone_parent,
        '' as email_parent,
        CONCAT(da.profession_pere, ' / ', da.profession_mere) as profession_parent,
        da.classe_demandee_id,
        c.nom as classe_nom,
        c.niveau,
        DATE_FORMAT(da.created_at, '%Y-%m') as periode_admission,
        da.status,
        da.decision_motif as motif_refus,
        da.observations as observation,
        da.created_at,
        da.updated_at,
        CONCAT(p.nom, ' ', p.prenom) as traite_par
     FROM demandes_admission da
     LEFT JOIN classes c ON da.classe_demandee_id = c.id
     LEFT JOIN personnel p ON da.traite_par = p.id
     WHERE $where_clause
     ORDER BY da.created_at DESC",
    $params
)->fetchAll();

// Statistiques pour l'export
$stats_export = [
    'total' => count($demandes),
    'en_attente' => 0,
    'acceptees' => 0,
    'refusees' => 0,
    'annulees' => 0
];

foreach ($demandes as $demande) {
    switch ($demande['status']) {
        case 'en_attente':
            $stats_export['en_attente']++;
            break;
        case 'acceptee':
            $stats_export['acceptees']++;
            break;
        case 'refusee':
            $stats_export['refusees']++;
            break;
        case 'annulee':
            $stats_export['annulees']++;
            break;
    }
}

// Récupérer les classes pour le filtre
$classes = $database->query(
    "SELECT c.id, c.nom, c.niveau
     FROM classes c
     WHERE c.annee_scolaire_id = ?
     ORDER BY c.niveau, c.nom",
    [$current_year['id']]
)->fetchAll();

// Récupérer les périodes d'admission (utiliser la date de création comme période)
$periodes = $database->query(
    "SELECT DISTINCT DATE_FORMAT(created_at, '%Y-%m') as periode_admission 
     FROM demandes_admission 
     WHERE annee_scolaire_id = ?
     ORDER BY periode_admission",
    [$current_year['id']]
)->fetchAll();

// Traitement de l'export Excel
if ($format === 'excel' && !empty($demandes)) {
    // Définir les en-têtes pour le téléchargement
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="demandes_admission_' . date('Y-m-d_H-i-s') . '.csv"');
    
    // Créer le fichier CSV
    $output = fopen('php://output', 'w');
    
    // En-têtes CSV
    fputcsv($output, [
        'Numéro demande',
        'Nom demandeur',
        'Prénom demandeur',
        'Sexe',
        'Date naissance',
        'Lieu naissance',
        'Adresse',
        'Téléphone',
        'Email',
        'Nom parent',
        'Prénom parent',
        'Téléphone parent',
        'Email parent',
        'Profession parent',
        'Classe demandée',
        'Niveau',
        'Période admission',
        'Statut',
        'Motif refus',
        'Observation',
        'Date création',
        'Date modification',
        'Traité par'
    ]);
    
    // Données
    foreach ($demandes as $demande) {
        fputcsv($output, [
            $demande['numero_demande'],
            $demande['nom_demandeur'],
            $demande['prenom_demandeur'],
            $demande['sexe_demandeur'],
            $demande['date_naissance_demandeur'],
            $demande['lieu_naissance_demandeur'],
            $demande['adresse_demandeur'],
            $demande['telephone_demandeur'],
            $demande['email_demandeur'],
            $demande['nom_parent'],
            $demande['prenom_parent'],
            $demande['telephone_parent'],
            $demande['email_parent'],
            $demande['profession_parent'],
            $demande['classe_nom'],
            $demande['niveau'],
            $demande['periode_admission'],
            $demande['status'],
            $demande['motif_refus'],
            $demande['observation'],
            $demande['created_at'],
            $demande['updated_at'],
            $demande['traite_par']
        ]);
    }
    
    fclose($output);
    exit;
}

// Traitement de l'export PDF
if ($format === 'pdf' && !empty($demandes)) {
    // Pour l'instant, on redirige vers une version HTML optimisée pour l'impression
    // En production, vous pourriez utiliser une bibliothèque comme TCPDF ou FPDF
         header('Content-Type: text/html; charset=utf-8');
     include '../../../../includes/header.php';
     ?>
    <style>
        @media print {
            .no-print { display: none !important; }
            body { font-size: 12px; }
            .table { font-size: 10px; }
        }
    </style>
    <?php
 } else {
     include '../../../../includes/header.php';
 }
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">
        <i class="fas fa-download me-2"></i>
        Export des demandes d'admission
    </h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <div class="btn-group me-2">
            <a href="../index.php" class="btn btn-secondary no-print">
                <i class="fas fa-arrow-left me-1"></i>
                Retour aux admissions
            </a>
        </div>
        <div class="btn-group">
            <a href="?format=excel<?php echo !empty($_GET) ? '&' . http_build_query(array_diff_key($_GET, ['format' => ''])) : ''; ?>" class="btn btn-outline-success no-print">
                <i class="fas fa-file-excel me-1"></i>
                Exporter Excel
            </a>
            <a href="?format=pdf<?php echo !empty($_GET) ? '&' . http_build_query(array_diff_key($_GET, ['format' => ''])) : ''; ?>" class="btn btn-outline-danger no-print">
                <i class="fas fa-file-pdf me-1"></i>
                Exporter PDF
            </a>
            <button onclick="window.print()" class="btn btn-outline-primary no-print">
                <i class="fas fa-print me-1"></i>
                Imprimer
            </button>
        </div>
    </div>
</div>

<!-- Filtres -->
<div class="card mb-4 no-print">
    <div class="card-header">
        <h5 class="mb-0">
            <i class="fas fa-filter me-2"></i>
            Paramètres d'export
        </h5>
    </div>
    <div class="card-body">
        <form method="GET" action="" class="row g-3">
            <div class="col-md-2">
                <label for="status" class="form-label">Statut</label>
                <select class="form-select" id="status" name="status">
                    <option value="">Tous les statuts</option>
                    <option value="en_attente" <?php echo $status_filter === 'en_attente' ? 'selected' : ''; ?>>En attente</option>
                    <option value="acceptee" <?php echo $status_filter === 'acceptee' ? 'selected' : ''; ?>>Acceptée</option>
                    <option value="refusee" <?php echo $status_filter === 'refusee' ? 'selected' : ''; ?>>Refusée</option>
                    <option value="annulee" <?php echo $status_filter === 'annulee' ? 'selected' : ''; ?>>Annulée</option>
                </select>
            </div>
            
            <div class="col-md-2">
                <label for="niveau" class="form-label">Niveau</label>
                <select class="form-select" id="niveau" name="niveau">
                    <option value="">Tous les niveaux</option>
                    <option value="maternelle" <?php echo $niveau_filter === 'maternelle' ? 'selected' : ''; ?>>Maternelle</option>
                    <option value="primaire" <?php echo $niveau_filter === 'primaire' ? 'selected' : ''; ?>>Primaire</option>
                    <option value="secondaire" <?php echo $niveau_filter === 'secondaire' ? 'selected' : ''; ?>>Secondaire</option>
                    <option value="superieur" <?php echo $niveau_filter === 'superieur' ? 'selected' : ''; ?>>Supérieur</option>
                </select>
            </div>
            
            <div class="col-md-2">
                <label for="classe" class="form-label">Classe</label>
                <select class="form-select" id="classe" name="classe">
                    <option value="">Toutes les classes</option>
                    <?php foreach ($classes as $c): ?>
                        <option value="<?php echo $c['id']; ?>" <?php echo $classe_filter == $c['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($c['nom']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="col-md-2">
                <label for="periode" class="form-label">Période</label>
                <select class="form-select" id="periode" name="periode">
                    <option value="">Toutes les périodes</option>
                    <?php foreach ($periodes as $p): ?>
                        <option value="<?php echo htmlspecialchars($p['periode_admission']); ?>" <?php echo $periode_filter === $p['periode_admission'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($p['periode_admission']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="col-md-2">
                <label for="date_debut" class="form-label">Date début</label>
                <input type="date" class="form-control" id="date_debut" name="date_debut" value="<?php echo $date_debut; ?>">
            </div>
            
            <div class="col-md-2">
                <label for="date_fin" class="form-label">Date fin</label>
                <input type="date" class="form-control" id="date_fin" name="date_fin" value="<?php echo $date_fin; ?>">
            </div>
            
            <div class="col-12">
                <button type="submit" class="btn btn-primary me-2">
                    <i class="fas fa-search me-1"></i>
                    Filtrer
                </button>
                <a href="?" class="btn btn-outline-secondary">
                    <i class="fas fa-times me-1"></i>
                    Réinitialiser
                </a>
            </div>
        </form>
    </div>
</div>

<!-- Résumé de l'export -->
<div class="row mb-4">
    <div class="col-md-3">
        <div class="card text-center">
            <div class="card-body">
                <h3 class="text-primary"><?php echo $stats_export['total']; ?></h3>
                <p class="card-text">Total demandes</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-center">
            <div class="card-body">
                <h3 class="text-warning"><?php echo $stats_export['en_attente']; ?></h3>
                <p class="card-text">En attente</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-center">
            <div class="card-body">
                <h3 class="text-success"><?php echo $stats_export['acceptees']; ?></h3>
                <p class="card-text">Acceptées</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-center">
            <div class="card-body">
                <h3 class="text-danger"><?php echo $stats_export['refusees']; ?></h3>
                <p class="card-text">Refusées</p>
            </div>
        </div>
    </div>
</div>

<!-- Liste des demandes -->
<div class="card">
    <div class="card-header">
        <h5 class="mb-0">
            <i class="fas fa-list me-2"></i>
            Demandes d'admission (<?php echo count($demandes); ?> résultat<?php echo count($demandes) > 1 ? 's' : ''; ?>)
        </h5>
    </div>
    <div class="card-body">
        <?php if (empty($demandes)): ?>
            <div class="alert alert-info">
                <i class="fas fa-info-circle me-2"></i>
                Aucune demande d'admission trouvée avec les critères sélectionnés.
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Numéro</th>
                            <th>Demandeur</th>
                            <th>Parent</th>
                            <th>Classe demandée</th>
                            <th>Période</th>
                            <th>Statut</th>
                            <th>Date création</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($demandes as $index => $demande): ?>
                            <tr>
                                <td><?php echo $index + 1; ?></td>
                                <td>
                                    <strong><?php echo htmlspecialchars($demande['numero_demande']); ?></strong>
                                </td>
                                <td>
                                    <div>
                                        <strong><?php echo htmlspecialchars($demande['nom_demandeur'] . ' ' . $demande['prenom_demandeur']); ?></strong>
                                        <br>
                                        <small class="text-muted">
                                            <?php echo htmlspecialchars($demande['sexe_demandeur']); ?> • 
                                            <?php echo $demande['date_naissance_demandeur'] ? formatDate($demande['date_naissance_demandeur']) : 'Non spécifié'; ?>
                                        </small>
                                    </div>
                                </td>
                                <td>
                                    <div>
                                        <strong><?php echo htmlspecialchars($demande['nom_parent'] . ' ' . $demande['prenom_parent']); ?></strong>
                                        <br>
                                        <small class="text-muted">
                                            <?php echo htmlspecialchars($demande['telephone_parent']); ?>
                                        </small>
                                    </div>
                                </td>
                                <td>
                                    <div>
                                        <strong><?php echo htmlspecialchars($demande['classe_nom'] ?? 'Non spécifiée'); ?></strong>
                                        <br>
                                        <small class="text-muted">
                                            <?php echo ucfirst($demande['niveau'] ?? 'Non spécifié'); ?>
                                        </small>
                                    </div>
                                </td>
                                <td>
                                    <?php echo htmlspecialchars($demande['periode_admission'] ?? 'Non spécifiée'); ?>
                                </td>
                                <td>
                                    <?php
                                    $status_badges = [
                                        'en_attente' => 'warning',
                                        'acceptee' => 'success',
                                        'refusee' => 'danger',
                                        'annulee' => 'secondary'
                                    ];
                                    $status_labels = [
                                        'en_attente' => 'En attente',
                                        'acceptee' => 'Acceptée',
                                        'refusee' => 'Refusée',
                                        'annulee' => 'Annulée'
                                    ];
                                    $badge_class = $status_badges[$demande['status']] ?? 'secondary';
                                    $status_label = $status_labels[$demande['status']] ?? $demande['status'];
                                    ?>
                                    <span class="badge bg-<?php echo $badge_class; ?>">
                                        <?php echo $status_label; ?>
                                    </span>
                                    <?php if ($demande['motif_refus']): ?>
                                        <br>
                                        <small class="text-muted">
                                            <?php echo htmlspecialchars($demande['motif_refus']); ?>
                                        </small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php echo formatDateTime($demande['created_at']); ?>
                                </td>
                                <td class="no-print">
                                    <div class="btn-group btn-group-sm">
                                        <a href="../applications/view.php?id=<?php echo $demande['id']; ?>" class="btn btn-outline-primary" title="Voir les détails">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <?php if ($demande['status'] === 'en_attente'): ?>
                                            <a href="../applications/process.php?id=<?php echo $demande['id']; ?>" class="btn btn-outline-success" title="Traiter">
                                                <i class="fas fa-check"></i>
                                            </a>
                                        <?php endif; ?>
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

 <?php include '../../../../includes/footer.php'; ?>
