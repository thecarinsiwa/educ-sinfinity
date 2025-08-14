<?php
/**
 * Module de gestion financière - Gestion des paiements
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

$page_title = 'Gestion des Paiements';

// Obtenir l'année scolaire actuelle
$current_year = getCurrentAcademicYear();

// Paramètres de recherche et filtrage
$search = sanitizeInput($_GET['search'] ?? '');
$classe_filter = (int)($_GET['classe'] ?? 0);
$status_filter = sanitizeInput($_GET['status'] ?? '');
$type_filter = sanitizeInput($_GET['type'] ?? '');
$date_debut = sanitizeInput($_GET['date_debut'] ?? '');
$date_fin = sanitizeInput($_GET['date_fin'] ?? '');

// Construction de la requête
$sql = "SELECT p.*, 
               e.nom, e.prenom, e.numero_matricule,
               c.nom as classe_nom, c.niveau,
               u.username as enregistre_par
        FROM paiements p
        JOIN eleves e ON p.eleve_id = e.id
        JOIN inscriptions i ON e.id = i.eleve_id AND i.annee_scolaire_id = p.annee_scolaire_id
        JOIN classes c ON i.classe_id = c.id
        LEFT JOIN users u ON p.user_id = u.id
        WHERE p.annee_scolaire_id = ?";

$params = [$current_year['id'] ?? 0];

if (!empty($search)) {
    $sql .= " AND (e.nom LIKE ? OR e.prenom LIKE ? OR e.numero_matricule LIKE ? OR p.recu_numero LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

if ($classe_filter) {
    $sql .= " AND i.classe_id = ?";
    $params[] = $classe_filter;
}

// Suppression du filtre status car la colonne n'existe pas dans la table paiements
// if (!empty($status_filter)) {
//     $sql .= " AND p.status = ?";
//     $params[] = $status_filter;
// }

if (!empty($type_filter)) {
    $sql .= " AND p.type_paiement = ?";
    $params[] = $type_filter;
}

if (!empty($date_debut)) {
    $sql .= " AND p.date_paiement >= ?";
    $params[] = $date_debut;
}

if (!empty($date_fin)) {
    $sql .= " AND p.date_paiement <= ?";
    $params[] = $date_fin;
}

$sql .= " ORDER BY p.date_paiement DESC, p.created_at DESC";

$paiements = $database->query($sql, $params)->fetchAll();

// Récupérer les classes pour le filtre
$classes = $database->query(
    "SELECT id, nom, niveau FROM classes WHERE annee_scolaire_id = ? ORDER BY niveau, nom",
    [$current_year['id'] ?? 0]
)->fetchAll();

// Statistiques
$stats = [
    'total' => count($paiements),
    'valides' => count(array_filter($paiements, fn($p) => $p['status'] === 'valide')),
    'en_attente' => count(array_filter($paiements, fn($p) => $p['status'] === 'en_attente')),
    'annules' => count(array_filter($paiements, fn($p) => $p['status'] === 'annule')),
    'montant_total' => array_sum(array_map(fn($p) => $p['montant'], $paiements))
];

include '../../../includes/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">
        <i class="fas fa-money-bill-wave me-2"></i>
        Gestion des Paiements
    </h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <div class="btn-group me-2">
            <a href="../index.php" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-1"></i>
                Retour
            </a>
        </div>
        <?php if (checkPermission('finance')): ?>
            <div class="btn-group me-2">
                <a href="add.php" class="btn btn-primary">
                    <i class="fas fa-plus me-1"></i>
                    Nouveau paiement
                </a>
            </div>
        <?php endif; ?>
        <div class="btn-group">
            <button type="button" class="btn btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">
                <i class="fas fa-download me-1"></i>
                Exporter
            </button>
            <ul class="dropdown-menu">
                <li><a class="dropdown-item" href="export.php?format=excel&<?php echo http_build_query($_GET); ?>">
                    <i class="fas fa-file-excel me-2"></i>Excel
                </a></li>
                <li><a class="dropdown-item" href="export.php?format=pdf&<?php echo http_build_query($_GET); ?>">
                    <i class="fas fa-file-pdf me-2"></i>PDF
                </a></li>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item" href="receipts.php?<?php echo http_build_query($_GET); ?>">
                    <i class="fas fa-receipt me-2"></i>Reçus groupés
                </a></li>
            </ul>
        </div>
    </div>
</div>

<!-- Statistiques rapides -->
<div class="row mb-4">
    <div class="col-md-3">
        <div class="card text-white bg-primary">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h4><?php echo $stats['total']; ?></h4>
                        <p class="mb-0">Total paiements</p>
                    </div>
                    <div class="align-self-center">
                        <i class="fas fa-list fa-2x"></i>
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
                        <h4><?php echo $stats['valides']; ?></h4>
                        <p class="mb-0">Validés</p>
                    </div>
                    <div class="align-self-center">
                        <i class="fas fa-check-circle fa-2x"></i>
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
                        <h4><?php echo $stats['en_attente']; ?></h4>
                        <p class="mb-0">En attente</p>
                    </div>
                    <div class="align-self-center">
                        <i class="fas fa-clock fa-2x"></i>
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
                        <h5><?php echo formatMoney($stats['montant_total']); ?></h5>
                        <p class="mb-0">Montant total</p>
                    </div>
                    <div class="align-self-center">
                        <i class="fas fa-coins fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Filtres de recherche -->
<div class="card mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-md-3">
                <label for="search" class="form-label">Rechercher</label>
                <input type="text" 
                       class="form-control" 
                       id="search" 
                       name="search" 
                       placeholder="Nom, matricule, n° reçu..."
                       value="<?php echo htmlspecialchars($search); ?>">
            </div>
            <div class="col-md-2">
                <label for="classe" class="form-label">Classe</label>
                <select class="form-select" id="classe" name="classe">
                    <option value="">Toutes</option>
                    <?php foreach ($classes as $classe): ?>
                        <option value="<?php echo $classe['id']; ?>" 
                                <?php echo $classe_filter == $classe['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($classe['nom']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label for="status" class="form-label">Statut</label>
                <select class="form-select" id="status" name="status">
                    <option value="">Tous</option>
                    <option value="valide" <?php echo $status_filter === 'valide' ? 'selected' : ''; ?>>Validé</option>
                    <option value="en_attente" <?php echo $status_filter === 'en_attente' ? 'selected' : ''; ?>>En attente</option>
                    <option value="annule" <?php echo $status_filter === 'annule' ? 'selected' : ''; ?>>Annulé</option>
                </select>
            </div>
            <div class="col-md-2">
                <label for="type" class="form-label">Type</label>
                <select class="form-select" id="type" name="type">
                    <option value="">Tous</option>
                    <option value="inscription" <?php echo $type_filter === 'inscription' ? 'selected' : ''; ?>>Inscription</option>
                    <option value="mensualite" <?php echo $type_filter === 'mensualite' ? 'selected' : ''; ?>>Mensualité</option>
                    <option value="examen" <?php echo $type_filter === 'examen' ? 'selected' : ''; ?>>Examen</option>
                    <option value="autre" <?php echo $type_filter === 'autre' ? 'selected' : ''; ?>>Autre</option>
                </select>
            </div>
            <div class="col-md-2">
                <label for="date_debut" class="form-label">Du</label>
                <input type="date" 
                       class="form-control" 
                       id="date_debut" 
                       name="date_debut" 
                       value="<?php echo htmlspecialchars($date_debut); ?>">
            </div>
            <div class="col-md-2">
                <label for="date_fin" class="form-label">Au</label>
                <input type="date" 
                       class="form-control" 
                       id="date_fin" 
                       name="date_fin" 
                       value="<?php echo htmlspecialchars($date_fin); ?>">
            </div>
            <div class="col-md-1">
                <label class="form-label">&nbsp;</label>
                <div class="d-grid">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search"></i>
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Liste des paiements -->
<div class="card">
    <div class="card-header">
        <h5 class="mb-0">
            <i class="fas fa-list me-2"></i>
            Liste des paiements (<?php echo count($paiements); ?>)
        </h5>
    </div>
    <div class="card-body">
        <?php if (!empty($paiements)): ?>
            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead>
                        <tr>
                            <th>N° Reçu</th>
                            <th>Date</th>
                            <th>Élève</th>
                            <th>Classe</th>
                            <th>Type</th>
                            <th>Montant</th>
                            <th>Mode</th>
                            <th>Statut</th>
                            <th>Enregistré par</th>
                            <th class="no-sort">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($paiements as $paiement): ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($paiement['recu_numero']); ?></strong>
                                </td>
                                <td>
                                    <?php echo formatDate($paiement['date_paiement']); ?>
                                    <br><small class="text-muted">
                                        <?php echo date('H:i', strtotime($paiement['created_at'])); ?>
                                    </small>
                                </td>
                                <td>
                                    <div>
                                        <strong><?php echo htmlspecialchars($paiement['nom'] . ' ' . $paiement['prenom']); ?></strong>
                                        <br><small class="text-muted">
                                            <?php echo htmlspecialchars($paiement['numero_matricule']); ?>
                                        </small>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge bg-<?php 
                                        echo $paiement['niveau'] === 'maternelle' ? 'warning' : 
                                            ($paiement['niveau'] === 'primaire' ? 'success' : 'primary'); 
                                    ?>">
                                        <?php echo htmlspecialchars($paiement['classe_nom']); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php
                                    $type_colors = [
                                        'inscription' => 'primary',
                                        'mensualite' => 'success',
                                        'examen' => 'warning',
                                        'autre' => 'secondary'
                                    ];
                                    $color = $type_colors[$paiement['type_paiement']] ?? 'secondary';
                                    ?>
                                    <span class="badge bg-<?php echo $color; ?>">
                                        <?php echo ucfirst($paiement['type_paiement']); ?>
                                    </span>
                                </td>
                                <td>
                                    <strong class="text-success">
                                        <?php echo formatMoney($paiement['montant']); ?>
                                    </strong>
                                </td>
                                <td>
                                    <small>
                                        <?php
                                        $modes = [
                                            'especes' => 'Espèces',
                                            'cheque' => 'Chèque',
                                            'virement' => 'Virement',
                                            'mobile_money' => 'Mobile Money'
                                        ];
                                        echo $modes[$paiement['mode_paiement']] ?? ucfirst($paiement['mode_paiement']);
                                        ?>
                                    </small>
                                </td>
                                <td>
                                    <?php
                                    $status_colors = [
                                        'valide' => 'success',
                                        'en_attente' => 'warning',
                                        'annule' => 'danger'
                                    ];
                                    $color = $status_colors[$paiement['status']] ?? 'secondary';
                                    ?>
                                    <span class="badge bg-<?php echo $color; ?>">
                                        <?php echo ucfirst(str_replace('_', ' ', $paiement['status'])); ?>
                                    </span>
                                </td>
                                <td>
                                    <small class="text-muted">
                                        <?php echo htmlspecialchars($paiement['enregistre_par'] ?? 'Système'); ?>
                                    </small>
                                </td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <a href="view.php?id=<?php echo $paiement['id']; ?>" 
                                           class="btn btn-outline-info" 
                                           title="Voir détails">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="receipt.php?id=<?php echo $paiement['id']; ?>" 
                                           class="btn btn-outline-success" 
                                           title="Reçu"
                                           target="_blank">
                                            <i class="fas fa-receipt"></i>
                                        </a>
                                        <?php if (checkPermission('finance') && $paiement['status'] !== 'annule'): ?>
                                            <a href="edit.php?id=<?php echo $paiement['id']; ?>" 
                                               class="btn btn-outline-primary" 
                                               title="Modifier">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <?php if ($paiement['status'] === 'en_attente'): ?>
                                                <a href="validate.php?id=<?php echo $paiement['id']; ?>" 
                                                   class="btn btn-outline-success" 
                                                   title="Valider">
                                                    <i class="fas fa-check"></i>
                                                </a>
                                            <?php endif; ?>
                                            <a href="cancel.php?id=<?php echo $paiement['id']; ?>" 
                                               class="btn btn-outline-danger" 
                                               title="Annuler"
                                               onclick="return confirm('Êtes-vous sûr de vouloir annuler ce paiement ?')">
                                                <i class="fas fa-times"></i>
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="text-center py-5">
                <i class="fas fa-money-bill-wave fa-3x text-muted mb-3"></i>
                <h5 class="text-muted">Aucun paiement trouvé</h5>
                <p class="text-muted">
                    <?php if (!empty($search) || $classe_filter || !empty($status_filter)): ?>
                        Aucun paiement ne correspond aux critères de recherche.
                    <?php else: ?>
                        Aucun paiement n'a encore été enregistré.
                    <?php endif; ?>
                </p>
                <?php if (checkPermission('finance')): ?>
                    <a href="add.php" class="btn btn-primary">
                        <i class="fas fa-plus me-1"></i>
                        Enregistrer le premier paiement
                    </a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include '../../../includes/footer.php'; ?>
