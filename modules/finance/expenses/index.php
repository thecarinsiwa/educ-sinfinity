<?php
/**
 * Module de gestion financière - Gestion des dépenses
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

$page_title = 'Gestion des dépenses';

// Obtenir l'année scolaire actuelle
$current_year = getCurrentAcademicYear();

// Paramètres de recherche et filtrage
$search = sanitizeInput($_GET['search'] ?? '');
$type_filter = sanitizeInput($_GET['type'] ?? '');
$date_debut = sanitizeInput($_GET['date_debut'] ?? '');
$date_fin = sanitizeInput($_GET['date_fin'] ?? '');

// Vérifier si la table depenses existe, sinon la créer
try {
    $table_exists = $database->query("SHOW TABLES LIKE 'depenses'")->fetch();
    if (!$table_exists) {
        $create_table = "
            CREATE TABLE depenses (
                id INT PRIMARY KEY AUTO_INCREMENT,
                libelle VARCHAR(255) NOT NULL,
                description TEXT,
                montant DECIMAL(10,2) NOT NULL,
                type_depense ENUM('salaires', 'fournitures', 'maintenance', 'utilities', 'transport', 'autre') NOT NULL,
                date_depense DATE NOT NULL,
                fournisseur VARCHAR(255),
                numero_facture VARCHAR(100),
                mode_paiement ENUM('especes', 'cheque', 'virement', 'mobile_money') DEFAULT 'especes',
                statut ENUM('en_attente', 'payee', 'annulee') DEFAULT 'en_attente',
                annee_scolaire_id INT NOT NULL,
                user_id INT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (annee_scolaire_id) REFERENCES annees_scolaires(id),
                FOREIGN KEY (user_id) REFERENCES users(id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ";
        $database->execute($create_table);
    }
} catch (Exception $e) {
    // Table creation failed, continue anyway
}

// Construction de la requête
$sql = "SELECT d.*, u.username as enregistre_par
        FROM depenses d
        LEFT JOIN users u ON d.user_id = u.id
        WHERE d.annee_scolaire_id = ?";

$params = [$current_year['id'] ?? 0];

if (!empty($search)) {
    $sql .= " AND (d.libelle LIKE ? OR d.description LIKE ? OR d.fournisseur LIKE ? OR d.numero_facture LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

if (!empty($type_filter)) {
    $sql .= " AND d.type_depense = ?";
    $params[] = $type_filter;
}

if (!empty($date_debut)) {
    $sql .= " AND d.date_depense >= ?";
    $params[] = $date_debut;
}

if (!empty($date_fin)) {
    $sql .= " AND d.date_depense <= ?";
    $params[] = $date_fin;
}

$sql .= " ORDER BY d.date_depense DESC, d.created_at DESC";

try {
    $depenses = $database->query($sql, $params)->fetchAll();
} catch (Exception $e) {
    $depenses = [];
}

// Statistiques
$stats = [
    'total' => count($depenses),
    'en_attente' => count(array_filter($depenses, fn($d) => $d['statut'] === 'en_attente')),
    'payees' => count(array_filter($depenses, fn($d) => $d['statut'] === 'payee')),
    'annulees' => count(array_filter($depenses, fn($d) => $d['statut'] === 'annulee')),
    'montant_total' => array_sum(array_map(fn($d) => $d['statut'] === 'payee' ? $d['montant'] : 0, $depenses))
];

include '../../../includes/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">
        <i class="fas fa-money-bill-wave me-2"></i>
        Gestion des dépenses
    </h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <?php if (checkPermission('finance')): ?>
            <div class="btn-group me-2">
                <a href="add.php" class="btn btn-primary">
                    <i class="fas fa-plus me-1"></i>
                    Nouvelle dépense
                </a>
            </div>
        <?php endif; ?>
        <div class="btn-group">
            <a href="../reports/" class="btn btn-outline-secondary">
                <i class="fas fa-chart-bar me-1"></i>
                Rapports
            </a>
        </div>
    </div>
</div>

<!-- Statistiques -->
<div class="row mb-4">
    <div class="col-md-3">
        <div class="card text-center">
            <div class="card-body">
                <h5 class="card-title text-primary"><?php echo $stats['total']; ?></h5>
                <p class="card-text">Total dépenses</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-center">
            <div class="card-body">
                <h5 class="card-title text-warning"><?php echo $stats['en_attente']; ?></h5>
                <p class="card-text">En attente</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-center">
            <div class="card-body">
                <h5 class="card-title text-success"><?php echo $stats['payees']; ?></h5>
                <p class="card-text">Payées</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-center">
            <div class="card-body">
                <h5 class="card-title text-success"><?php echo formatMoney($stats['montant_total']); ?></h5>
                <p class="card-text">Montant total</p>
            </div>
        </div>
    </div>
</div>

<!-- Filtres de recherche -->
<div class="card mb-4">
    <div class="card-header">
        <h5 class="mb-0">
            <i class="fas fa-filter me-2"></i>
            Filtres de recherche
        </h5>
    </div>
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-md-3">
                <label for="search" class="form-label">Recherche</label>
                <input type="text" class="form-control" id="search" name="search" 
                       value="<?php echo htmlspecialchars($search); ?>"
                       placeholder="Libellé, fournisseur...">
            </div>
            <div class="col-md-2">
                <label for="type" class="form-label">Type</label>
                <select class="form-select" id="type" name="type">
                    <option value="">Tous les types</option>
                    <option value="salaires" <?php echo $type_filter === 'salaires' ? 'selected' : ''; ?>>Salaires</option>
                    <option value="fournitures" <?php echo $type_filter === 'fournitures' ? 'selected' : ''; ?>>Fournitures</option>
                    <option value="maintenance" <?php echo $type_filter === 'maintenance' ? 'selected' : ''; ?>>Maintenance</option>
                    <option value="utilities" <?php echo $type_filter === 'utilities' ? 'selected' : ''; ?>>Services publics</option>
                    <option value="transport" <?php echo $type_filter === 'transport' ? 'selected' : ''; ?>>Transport</option>
                    <option value="autre" <?php echo $type_filter === 'autre' ? 'selected' : ''; ?>>Autre</option>
                </select>
            </div>
            <div class="col-md-2">
                <label for="date_debut" class="form-label">Date début</label>
                <input type="date" class="form-control" id="date_debut" name="date_debut" 
                       value="<?php echo htmlspecialchars($date_debut); ?>">
            </div>
            <div class="col-md-2">
                <label for="date_fin" class="form-label">Date fin</label>
                <input type="date" class="form-control" id="date_fin" name="date_fin" 
                       value="<?php echo htmlspecialchars($date_fin); ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label">&nbsp;</label>
                <div class="d-grid gap-2 d-md-flex">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search me-1"></i>
                        Rechercher
                    </button>
                    <a href="index.php" class="btn btn-outline-secondary">
                        <i class="fas fa-times me-1"></i>
                        Effacer
                    </a>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Liste des dépenses -->
<div class="card">
    <div class="card-header">
        <h5 class="mb-0">
            <i class="fas fa-list me-2"></i>
            Liste des dépenses (<?php echo count($depenses); ?>)
        </h5>
    </div>
    <div class="card-body">
        <?php if (!empty($depenses)): ?>
            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Libellé</th>
                            <th>Type</th>
                            <th>Fournisseur</th>
                            <th>Montant</th>
                            <th>Statut</th>
                            <th>Enregistré par</th>
                            <th class="no-sort">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($depenses as $depense): ?>
                            <tr>
                                <td>
                                    <?php echo formatDate($depense['date_depense']); ?>
                                    <?php if ($depense['created_at']): ?>
                                        <br><small class="text-muted">
                                            <?php echo date('H:i', strtotime($depense['created_at'])); ?>
                                        </small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div>
                                        <strong><?php echo htmlspecialchars($depense['libelle']); ?></strong>
                                        <?php if ($depense['numero_facture']): ?>
                                            <br><small class="text-muted">
                                                Facture: <?php echo htmlspecialchars($depense['numero_facture']); ?>
                                            </small>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <?php
                                    $type_colors = [
                                        'salaires' => 'primary',
                                        'fournitures' => 'success',
                                        'maintenance' => 'warning',
                                        'utilities' => 'info',
                                        'transport' => 'secondary',
                                        'autre' => 'dark'
                                    ];
                                    $color = $type_colors[$depense['type_depense']] ?? 'secondary';
                                    ?>
                                    <span class="badge bg-<?php echo $color; ?>">
                                        <?php echo ucfirst($depense['type_depense']); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php echo htmlspecialchars($depense['fournisseur'] ?: '-'); ?>
                                </td>
                                <td>
                                    <strong class="text-danger">
                                        <?php echo formatMoney($depense['montant']); ?>
                                    </strong>
                                </td>
                                <td>
                                    <?php
                                    $status_colors = [
                                        'en_attente' => 'warning',
                                        'payee' => 'success',
                                        'annulee' => 'danger'
                                    ];
                                    $status_labels = [
                                        'en_attente' => 'En attente',
                                        'payee' => 'Payée',
                                        'annulee' => 'Annulée'
                                    ];
                                    $color = $status_colors[$depense['statut']] ?? 'secondary';
                                    $label = $status_labels[$depense['statut']] ?? $depense['statut'];
                                    ?>
                                    <span class="badge bg-<?php echo $color; ?>">
                                        <?php echo $label; ?>
                                    </span>
                                </td>
                                <td>
                                    <small><?php echo htmlspecialchars($depense['enregistre_par'] ?? 'Système'); ?></small>
                                </td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <a href="view.php?id=<?php echo $depense['id']; ?>" 
                                           class="btn btn-outline-info" 
                                           title="Voir">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <?php if (checkPermission('finance')): ?>
                                            <a href="edit.php?id=<?php echo $depense['id']; ?>" 
                                               class="btn btn-outline-primary" 
                                               title="Modifier">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <?php if ($depense['statut'] === 'en_attente'): ?>
                                                <a href="pay.php?id=<?php echo $depense['id']; ?>" 
                                                   class="btn btn-outline-success" 
                                                   title="Marquer comme payée">
                                                    <i class="fas fa-check"></i>
                                                </a>
                                            <?php endif; ?>
                                            <a href="delete.php?id=<?php echo $depense['id']; ?>" 
                                               class="btn btn-outline-danger" 
                                               title="Supprimer"
                                               onclick="return confirm('Êtes-vous sûr de vouloir supprimer cette dépense ?')">
                                                <i class="fas fa-trash"></i>
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
                <h5 class="text-muted">Aucune dépense trouvée</h5>
                <p class="text-muted">
                    <?php if (!empty($search) || !empty($type_filter)): ?>
                        Aucune dépense ne correspond aux critères de recherche.
                    <?php else: ?>
                        Aucune dépense n'a encore été enregistrée.
                    <?php endif; ?>
                </p>
                <?php if (checkPermission('finance')): ?>
                    <a href="add.php" class="btn btn-primary">
                        <i class="fas fa-plus me-1"></i>
                        Enregistrer la première dépense
                    </a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
// Initialiser les DataTables si disponible
$(document).ready(function() {
    if ($.fn.DataTable) {
        $('.table').DataTable({
            "language": {
                "url": "//cdn.datatables.net/plug-ins/1.10.24/i18n/French.json"
            },
            "order": [[ 0, "desc" ]],
            "columnDefs": [
                { "orderable": false, "targets": "no-sort" }
            ]
        });
    }
});
</script>

<?php include '../../../includes/footer.php'; ?>
