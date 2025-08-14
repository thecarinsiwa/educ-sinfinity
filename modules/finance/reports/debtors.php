<?php
/**
 * Module de gestion financière - Rapport des débiteurs
 * Application de gestion scolaire - République Démocratique du Congo
 */

require_once '../../../config/config.php';
require_once '../../../config/database.php';
require_once '../../../includes/functions.php';

// Vérifier l'authentification et les permissions
requireLogin();
if (!checkPermission('finance') && !checkPermission('finance_view')) {
    showMessage('error', 'Accès refusé à ce module.');
    redirectTo('../index.php');
}

$page_title = 'Rapport des Débiteurs';

// Obtenir l'année scolaire actuelle
$current_year = getCurrentAcademicYear();

// Paramètres de filtrage
$niveau_filter = sanitizeInput($_GET['niveau'] ?? '');
$classe_filter = (int)($_GET['classe_id'] ?? 0);
$montant_min = (float)($_GET['montant_min'] ?? 0);
$montant_max = (float)($_GET['montant_max'] ?? 0);
$delai_filter = sanitizeInput($_GET['delai'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = 25;
$offset = ($page - 1) * $per_page;

// Calculer les dates pour les filtres de délai
$date_limite = '';
switch ($delai_filter) {
    case '7_jours':
        $date_limite = date('Y-m-d', strtotime('-7 days'));
        break;
    case '15_jours':
        $date_limite = date('Y-m-d', strtotime('-15 days'));
        break;
    case '30_jours':
        $date_limite = date('Y-m-d', strtotime('-30 days'));
        break;
    case '60_jours':
        $date_limite = date('Y-m-d', strtotime('-60 days'));
        break;
    case '90_jours':
        $date_limite = date('Y-m-d', strtotime('-90 days'));
        break;
}

// Récupérer les débiteurs
$debiteurs = [];
$total_debiteurs = 0;
$total_dette = 0;

try {
    // Requête pour compter le total
    $count_sql = "
        SELECT COUNT(DISTINCT e.id) as total, SUM(dette.montant_du) as total_dette
        FROM eleves e
        JOIN inscriptions i ON e.id = i.eleve_id
        JOIN classes c ON i.classe_id = c.id
        JOIN (
            SELECT 
                e.id as eleve_id,
                SUM(fs.montant) as montant_total,
                COALESCE(SUM(p.montant), 0) as montant_paye,
                SUM(fs.montant) - COALESCE(SUM(p.montant), 0) as montant_du
            FROM eleves e
            JOIN inscriptions i ON e.id = i.eleve_id
            JOIN frais_scolaires fs ON i.classe_id = fs.classe_id
            LEFT JOIN paiements p ON e.id = p.eleve_id 
                AND fs.type_frais COLLATE utf8mb4_unicode_ci = p.type_paiement COLLATE utf8mb4_unicode_ci
                AND p.annee_scolaire_id = fs.annee_scolaire_id
            WHERE i.annee_scolaire_id = ? 
                AND fs.annee_scolaire_id = ?
            GROUP BY e.id
            HAVING montant_du > 0
        ) dette ON e.id = dette.eleve_id
        WHERE i.annee_scolaire_id = ?
    ";
    
    $count_params = [$current_year['id'], $current_year['id'], $current_year['id']];
    
    if (!empty($niveau_filter)) {
        $count_sql .= " AND c.niveau = ?";
        $count_params[] = $niveau_filter;
    }
    
    if ($classe_filter > 0) {
        $count_sql .= " AND c.id = ?";
        $count_params[] = $classe_filter;
    }
    
    if ($montant_min > 0) {
        $count_sql .= " AND dette.montant_du >= ?";
        $count_params[] = $montant_min;
    }
    
    if ($montant_max > 0) {
        $count_sql .= " AND dette.montant_du <= ?";
        $count_params[] = $montant_max;
    }
    
    $count_result = $database->query($count_sql, $count_params)->fetch();
    $total_debiteurs = $count_result['total'];
    $total_dette = $count_result['total_dette'] ?? 0;
    
    // Requête pour les données détaillées
    $sql = "
        SELECT 
            e.id,
            e.nom,
            e.prenom,
            e.numero_matricule,
            c.nom as classe_nom,
            c.niveau,
            dette.montant_total,
            dette.montant_paye,
            dette.montant_du,
            i.date_inscription,
            DATEDIFF(CURDATE(), i.date_inscription) as jours_retard
        FROM eleves e
        JOIN inscriptions i ON e.id = i.eleve_id
        JOIN classes c ON i.classe_id = c.id
        JOIN (
            SELECT 
                e.id as eleve_id,
                SUM(fs.montant) as montant_total,
                COALESCE(SUM(p.montant), 0) as montant_paye,
                SUM(fs.montant) - COALESCE(SUM(p.montant), 0) as montant_du
            FROM eleves e
            JOIN inscriptions i ON e.id = i.eleve_id
            JOIN frais_scolaires fs ON i.classe_id = fs.classe_id
            LEFT JOIN paiements p ON e.id = p.eleve_id 
                AND fs.type_frais COLLATE utf8mb4_unicode_ci = p.type_paiement COLLATE utf8mb4_unicode_ci
                AND p.annee_scolaire_id = fs.annee_scolaire_id
            WHERE i.annee_scolaire_id = ? 
                AND fs.annee_scolaire_id = ?
            GROUP BY e.id
            HAVING montant_du > 0
        ) dette ON e.id = dette.eleve_id
        WHERE i.annee_scolaire_id = ?
    ";
    
    $params = [$current_year['id'], $current_year['id'], $current_year['id']];
    
    if (!empty($niveau_filter)) {
        $sql .= " AND c.niveau = ?";
        $params[] = $niveau_filter;
    }
    
    if ($classe_filter > 0) {
        $sql .= " AND c.id = ?";
        $params[] = $classe_filter;
    }
    
    if ($montant_min > 0) {
        $sql .= " AND dette.montant_du >= ?";
        $params[] = $montant_min;
    }
    
    if ($montant_max > 0) {
        $sql .= " AND dette.montant_du <= ?";
        $params[] = $montant_max;
    }
    
    if (!empty($date_limite)) {
        $sql .= " AND i.date_inscription <= ?";
        $params[] = $date_limite;
    }
    
    $sql .= " ORDER BY dette.montant_du DESC, e.nom, e.prenom LIMIT ? OFFSET ?";
    $params[] = $per_page;
    $params[] = $offset;
    
    $debiteurs = $database->query($sql, $params)->fetchAll();
    
} catch (Exception $e) {
    showMessage('error', 'Erreur lors de la récupération des données : ' . $e->getMessage());
}

// Récupérer les classes pour le filtre
$classes = $database->query(
    "SELECT id, nom, niveau FROM classes WHERE annee_scolaire_id = ? ORDER BY niveau, nom",
    [$current_year['id'] ?? 0]
)->fetchAll();

// Statistiques par niveau
$stats_par_niveau = [];
try {
    $stats_sql = "
        SELECT 
            c.niveau,
            COUNT(DISTINCT e.id) as nombre_debiteurs,
            SUM(dette.montant_du) as total_dette
        FROM eleves e
        JOIN inscriptions i ON e.id = i.eleve_id
        JOIN classes c ON i.classe_id = c.id
        JOIN (
            SELECT 
                e.id as eleve_id,
                SUM(fs.montant) - COALESCE(SUM(p.montant), 0) as montant_du
            FROM eleves e
            JOIN inscriptions i ON e.id = i.eleve_id
            JOIN frais_scolaires fs ON i.classe_id = fs.classe_id
            LEFT JOIN paiements p ON e.id = p.eleve_id 
                AND fs.type_frais COLLATE utf8mb4_unicode_ci = p.type_paiement COLLATE utf8mb4_unicode_ci
                AND p.annee_scolaire_id = fs.annee_scolaire_id
            WHERE i.annee_scolaire_id = ? 
                AND fs.annee_scolaire_id = ?
            GROUP BY e.id
            HAVING montant_du > 0
        ) dette ON e.id = dette.eleve_id
        WHERE i.annee_scolaire_id = ?
        GROUP BY c.niveau
        ORDER BY total_dette DESC
    ";
    
    $stats_par_niveau = $database->query($stats_sql, [$current_year['id'], $current_year['id'], $current_year['id']])->fetchAll();
} catch (Exception $e) {
    // Ignorer l'erreur pour les statistiques
}

// Calculer le nombre total de pages
$total_pages = ceil($total_debiteurs / $per_page);

include '../../../includes/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">
        <i class="fas fa-exclamation-triangle me-2"></i>
        Rapport des Débiteurs
    </h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <div class="btn-group me-2">
            <a href="index.php" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-1"></i>
                Retour
            </a>
        </div>
        <div class="btn-group">
            <button type="button" class="btn btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">
                <i class="fas fa-download me-1"></i>
                Exporter
            </button>
            <ul class="dropdown-menu">
                <li><a class="dropdown-item" href="export-debtors.php?format=excel&niveau=<?php echo $niveau_filter; ?>&classe_id=<?php echo $classe_filter; ?>">
                    <i class="fas fa-file-excel me-2"></i>Excel
                </a></li>
                <li><a class="dropdown-item" href="export-debtors.php?format=pdf&niveau=<?php echo $niveau_filter; ?>&classe_id=<?php echo $classe_filter; ?>">
                    <i class="fas fa-file-pdf me-2"></i>PDF
                </a></li>
            </ul>
        </div>
    </div>
</div>

<!-- Statistiques générales -->
<div class="row mb-4">
    <div class="col-md-4">
        <div class="card text-white bg-danger">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h4><?php echo $total_debiteurs; ?></h4>
                        <p class="mb-0">Débiteurs</p>
                    </div>
                    <div class="align-self-center">
                        <i class="fas fa-users fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card text-white bg-warning">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h4><?php echo formatMoney($total_dette); ?></h4>
                        <p class="mb-0">Total des dettes</p>
                    </div>
                    <div class="align-self-center">
                        <i class="fas fa-money-bill-wave fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card text-white bg-info">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h4><?php echo $total_debiteurs > 0 ? formatMoney($total_dette / $total_debiteurs) : '0 FC'; ?></h4>
                        <p class="mb-0">Dette moyenne</p>
                    </div>
                    <div class="align-self-center">
                        <i class="fas fa-calculator fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Statistiques par niveau -->
<?php if (!empty($stats_par_niveau)): ?>
<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-chart-bar me-2"></i>
                    Répartition par niveau
                </h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <?php foreach ($stats_par_niveau as $stat): ?>
                        <div class="col-md-4 mb-3">
                            <div class="card border-0 shadow-sm">
                                <div class="card-header bg-<?php 
                                    echo $stat['niveau'] === 'maternelle' ? 'warning' : 
                                        ($stat['niveau'] === 'primaire' ? 'success' : 'primary'); 
                                ?> text-white">
                                    <h6 class="mb-0"><?php echo ucfirst($stat['niveau']); ?></h6>
                                </div>
                                <div class="card-body">
                                    <div class="row text-center">
                                        <div class="col-6">
                                            <h5 class="text-danger"><?php echo $stat['nombre_debiteurs']; ?></h5>
                                            <small class="text-muted">Débiteurs</small>
                                        </div>
                                        <div class="col-6">
                                            <h5 class="text-warning"><?php echo formatMoney($stat['total_dette']); ?></h5>
                                            <small class="text-muted">Total dette</small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Filtres -->
<div class="card mb-4">
    <div class="card-header">
        <h5 class="mb-0">
            <i class="fas fa-filter me-2"></i>
            Filtres
        </h5>
    </div>
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-md-2">
                <label for="niveau" class="form-label">Niveau</label>
                <select name="niveau" id="niveau" class="form-select">
                    <option value="">Tous</option>
                    <option value="maternelle" <?php echo $niveau_filter === 'maternelle' ? 'selected' : ''; ?>>Maternelle</option>
                    <option value="primaire" <?php echo $niveau_filter === 'primaire' ? 'selected' : ''; ?>>Primaire</option>
                    <option value="secondaire" <?php echo $niveau_filter === 'secondaire' ? 'selected' : ''; ?>>Secondaire</option>
                </select>
            </div>
            <div class="col-md-3">
                <label for="classe_id" class="form-label">Classe</label>
                <select name="classe_id" id="classe_id" class="form-select">
                    <option value="">Toutes les classes</option>
                    <?php foreach ($classes as $classe): ?>
                        <option value="<?php echo $classe['id']; ?>" <?php echo $classe_filter == $classe['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($classe['nom']); ?> (<?php echo ucfirst($classe['niveau']); ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label for="montant_min" class="form-label">Montant min</label>
                <input type="number" name="montant_min" id="montant_min" class="form-control" value="<?php echo $montant_min; ?>" min="0" step="0.01">
            </div>
            <div class="col-md-2">
                <label for="montant_max" class="form-label">Montant max</label>
                <input type="number" name="montant_max" id="montant_max" class="form-control" value="<?php echo $montant_max; ?>" min="0" step="0.01">
            </div>
            <div class="col-md-2">
                <label for="delai" class="form-label">Délai</label>
                <select name="delai" id="delai" class="form-select">
                    <option value="">Tous</option>
                    <option value="7_jours" <?php echo $delai_filter === '7_jours' ? 'selected' : ''; ?>>+7 jours</option>
                    <option value="15_jours" <?php echo $delai_filter === '15_jours' ? 'selected' : ''; ?>>+15 jours</option>
                    <option value="30_jours" <?php echo $delai_filter === '30_jours' ? 'selected' : ''; ?>>+30 jours</option>
                    <option value="60_jours" <?php echo $delai_filter === '60_jours' ? 'selected' : ''; ?>>+60 jours</option>
                    <option value="90_jours" <?php echo $delai_filter === '90_jours' ? 'selected' : ''; ?>>+90 jours</option>
                </select>
            </div>
            <div class="col-md-1 d-flex align-items-end">
                <div class="d-grid gap-2 w-100">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search"></i>
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Liste des débiteurs -->
<div class="card">
    <div class="card-header">
        <h5 class="mb-0">
            <i class="fas fa-list me-2"></i>
            Liste des débiteurs (<?php echo $total_debiteurs; ?> résultats)
        </h5>
    </div>
    <div class="card-body">
        <?php if (empty($debiteurs)): ?>
            <div class="text-center py-4">
                <i class="fas fa-check-circle fa-3x text-success mb-3"></i>
                <h5 class="text-success">Aucun débiteur trouvé</h5>
                <p class="text-muted">Tous les élèves sont à jour dans leurs paiements.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Élève</th>
                            <th>Classe</th>
                            <th>Montant total</th>
                            <th>Montant payé</th>
                            <th>Montant dû</th>
                            <th>Jours de retard</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($debiteurs as $debiteur): ?>
                            <tr>
                                <td>
                                    <div>
                                        <strong><?php echo htmlspecialchars($debiteur['nom'] . ' ' . $debiteur['prenom']); ?></strong>
                                        <br>
                                        <small class="text-muted"><?php echo htmlspecialchars($debiteur['numero_matricule']); ?></small>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge bg-<?php 
                                        echo $debiteur['niveau'] === 'maternelle' ? 'warning' : 
                                            ($debiteur['niveau'] === 'primaire' ? 'success' : 'primary'); 
                                    ?>">
                                        <?php echo htmlspecialchars($debiteur['classe_nom']); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="text-primary">
                                        <?php echo formatMoney($debiteur['montant_total']); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="text-success">
                                        <?php echo formatMoney($debiteur['montant_paye']); ?>
                                    </span>
                                </td>
                                <td>
                                    <strong class="text-danger">
                                        <?php echo formatMoney($debiteur['montant_du']); ?>
                                    </strong>
                                </td>
                                <td>
                                    <?php if ($debiteur['jours_retard'] > 30): ?>
                                        <span class="badge bg-danger"><?php echo $debiteur['jours_retard']; ?> jours</span>
                                    <?php elseif ($debiteur['jours_retard'] > 15): ?>
                                        <span class="badge bg-warning"><?php echo $debiteur['jours_retard']; ?> jours</span>
                                    <?php else: ?>
                                        <span class="badge bg-info"><?php echo $debiteur['jours_retard']; ?> jours</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <a href="../payments/add.php?eleve_id=<?php echo $debiteur['id']; ?>" class="btn btn-outline-success" title="Enregistrer paiement">
                                            <i class="fas fa-money-bill-wave"></i>
                                        </a>
                                        <a href="../../students/records/view.php?id=<?php echo $debiteur['id']; ?>" class="btn btn-outline-info" title="Voir dossier">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="debtor-details.php?eleve_id=<?php echo $debiteur['id']; ?>" class="btn btn-outline-primary" title="Détails dette">
                                            <i class="fas fa-chart-line"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
                <nav aria-label="Pagination des débiteurs">
                    <ul class="pagination justify-content-center">
                        <?php if ($page > 1): ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?php echo $page - 1; ?>&niveau=<?php echo $niveau_filter; ?>&classe_id=<?php echo $classe_filter; ?>&montant_min=<?php echo $montant_min; ?>&montant_max=<?php echo $montant_max; ?>&delai=<?php echo $delai_filter; ?>">
                                    <i class="fas fa-chevron-left"></i>
                                </a>
                            </li>
                        <?php endif; ?>
                        
                        <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                            <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $i; ?>&niveau=<?php echo $niveau_filter; ?>&classe_id=<?php echo $classe_filter; ?>&montant_min=<?php echo $montant_min; ?>&montant_max=<?php echo $montant_max; ?>&delai=<?php echo $delai_filter; ?>">
                                    <?php echo $i; ?>
                                </a>
                            </li>
                        <?php endfor; ?>
                        
                        <?php if ($page < $total_pages): ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?php echo $page + 1; ?>&niveau=<?php echo $niveau_filter; ?>&classe_id=<?php echo $classe_filter; ?>&montant_min=<?php echo $montant_min; ?>&montant_max=<?php echo $montant_max; ?>&delai=<?php echo $delai_filter; ?>">
                                    <i class="fas fa-chevron-right"></i>
                                </a>
                            </li>
                        <?php endif; ?>
                    </ul>
                </nav>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<?php include '../../../includes/footer.php'; ?>
