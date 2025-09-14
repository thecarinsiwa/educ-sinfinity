<?php
/**
 * Module de gestion financière - Gestion des dépenses
 * Application de gestion scolaire - République Démocratique du Congo
 */

require_once '../../../config/config.php';
require_once '../../../config/database.php';
require_once '../../../includes/functions.php';
require_once '../../../includes/permissions-pages.php';
require_once '../../../includes/ui-permissions.php';

// Vérifier l'authentification et les permissions
requireLogin();
requirePagePermissionFromDB('finance', 'expenses/index', 'read', '../../dashboard.php');

$page_title = 'Gestion des dépenses';

// Obtenir l'année scolaire actuelle
$current_year = getCurrentAcademicYear();

// Obtenir la devise par défaut
$devise_par_defaut = getDefaultCurrency();

// Paramètres de recherche et filtrage
$search = sanitizeInput($_GET['search'] ?? '');
$type_filter = sanitizeInput($_GET['type'] ?? '');
$date_debut = sanitizeInput($_GET['date_debut'] ?? '');
$date_fin = sanitizeInput($_GET['date_fin'] ?? '');
$sync_filter = sanitizeInput($_GET['sync'] ?? '');

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
                devise_id INT NOT NULL DEFAULT 1,
                montant_devise_par_defaut DECIMAL(10,2) NOT NULL DEFAULT 0,
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
                FOREIGN KEY (user_id) REFERENCES users(id),
                FOREIGN KEY (devise_id) REFERENCES devises(id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ";
        $database->execute($create_table);
    } else {
        // Vérifier si les colonnes devise_id et montant_devise_par_defaut existent
        $columns = $database->query("SHOW COLUMNS FROM depenses LIKE 'devise_id'")->fetch();
        if (!$columns) {
            $database->execute("ALTER TABLE depenses ADD COLUMN devise_id INT NOT NULL DEFAULT 1 AFTER montant");
            $database->execute("ALTER TABLE depenses ADD COLUMN montant_devise_par_defaut DECIMAL(10,2) NOT NULL DEFAULT 0 AFTER devise_id");
            $database->execute("ALTER TABLE depenses ADD FOREIGN KEY (devise_id) REFERENCES devises(id)");
        }
    }
} catch (Exception $e) {
    // Table creation failed, continue anyway
}

// Construction de la requête
$sql = "SELECT d.*, u.username as enregistre_par,
               CASE WHEN EXISTS (
                   SELECT 1 FROM mouvements_caisse mc 
                   WHERE mc.reference = CONCAT('DEPENSE-', d.id)
               ) THEN 1 ELSE 0 END as synchronisee_caisse
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

if (!empty($sync_filter)) {
    if ($sync_filter === 'sync') {
        $sql .= " AND EXISTS (SELECT 1 FROM mouvements_caisse mc WHERE mc.reference = CONCAT('DEPENSE-', d.id))";
    } elseif ($sync_filter === 'nosync') {
        $sql .= " AND NOT EXISTS (SELECT 1 FROM mouvements_caisse mc WHERE mc.reference = CONCAT('DEPENSE-', d.id))";
    }
}

$sql .= " ORDER BY d.date_depense DESC, d.created_at DESC";

try {
    $depenses = $database->query($sql, $params)->fetchAll();
} catch (Exception $e) {
    $depenses = [];
}

// Statistiques des dépenses
$stats = [
    'total' => count($depenses),
    'en_attente' => count(array_filter($depenses, fn($d) => $d['statut'] === 'en_attente')),
    'payees' => count(array_filter($depenses, fn($d) => $d['statut'] === 'payee')),
    'annulees' => count(array_filter($depenses, fn($d) => $d['statut'] === 'annulee')),
    'montant_total' => 0 // Sera calculé avec conversion
];

// Calculer le montant total en devise par défaut
foreach ($depenses as $depense) {
    if ($depense['statut'] === 'payee') {
        // Convertir le montant en devise par défaut
        $montant_converti = convertToDefaultCurrency($depense['montant'], $depense['devise_id']);
        $stats['montant_total'] += $montant_converti;
    }
}

// Statistiques des caisses
$stats_caisses = [];
try {
    // Sessions ouvertes
    $stats_caisses['sessions_ouvertes'] = $database->query(
        "SELECT COUNT(*) as total FROM sessions_caisse WHERE statut = 'ouverte'"
    )->fetch()['total'];
    
    // Mouvements aujourd'hui
    $stats_caisses['mouvements_aujourdhui'] = $database->query(
        "SELECT COUNT(*) as total FROM mouvements_caisse WHERE DATE(date_mouvement) = CURDATE()"
    )->fetch()['total'];
    
    // Total entrées aujourd'hui (converties en devise par défaut)
    $stats_caisses['entrees_aujourdhui'] = $database->query(
        "SELECT COALESCE(SUM(mc.montant / d.taux_conversion), 0) as total 
         FROM mouvements_caisse mc
         JOIN devises d ON mc.devise_id = d.id
         WHERE DATE(mc.date_mouvement) = CURDATE() AND mc.type_mouvement = 'entree'"
    )->fetch()['total'];
    
    // Total sorties aujourd'hui (converties en devise par défaut)
    $stats_caisses['sorties_aujourdhui'] = $database->query(
        "SELECT COALESCE(SUM(mc.montant / d.taux_conversion), 0) as total 
         FROM mouvements_caisse mc
         JOIN devises d ON mc.devise_id = d.id
         WHERE DATE(mc.date_mouvement) = CURDATE() AND mc.type_mouvement = 'sortie'"
    )->fetch()['total'];
    
    // Dépenses synchronisées avec les caisses
    $stats_caisses['depenses_synchronisees'] = $database->query(
        "SELECT COUNT(*) as total FROM depenses d
         WHERE d.annee_scolaire_id = ? 
         AND d.id IN (
             SELECT DISTINCT CAST(SUBSTRING_INDEX(mc.reference, '-', -1) AS UNSIGNED)
             FROM mouvements_caisse mc
             WHERE mc.reference LIKE 'DEPENSE-%'
         )",
        [$current_year['id']]
    )->fetch()['total'];
    
} catch (Exception $e) {
    $stats_caisses = [
        'sessions_ouvertes' => 0,
        'mouvements_aujourdhui' => 0,
        'entrees_aujourdhui' => 0,
        'sorties_aujourdhui' => 0,
        'depenses_synchronisees' => 0
    ];
}

include '../../../includes/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">
        <i class="fas fa-money-bill-wave me-2"></i>
        Gestion des dépenses
    </h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <?php if (hasPagePermissionFromDB('finance', 'expenses/add', 'create')): ?>
            <div class="btn-group me-2">
                <a href="add.php" class="btn btn-primary">
                    <i class="fas fa-plus me-1"></i>
                    Nouvelle dépense
                </a>
            </div>
        <?php endif; ?>
        <div class="btn-group me-2">
            <button type="button" class="btn btn-success dropdown-toggle" data-bs-toggle="dropdown">
                <i class="fas fa-cash-register me-1"></i>
                Caisses
            </button>
            <ul class="dropdown-menu">
                <?php if (hasPagePermissionFromDB('finance', 'expenses/caisses', 'read')): ?>
                <li><a class="dropdown-item" href="caisses.php">
                    <i class="fas fa-cash-register me-2"></i>Gestion des caisses
                </a></li>
                <?php endif; ?>
                <?php if (hasPagePermissionFromDB('finance', 'expenses/historique_caisses', 'read')): ?>
                <li><a class="dropdown-item" href="historique_caisses.php">
                    <i class="fas fa-history me-2"></i>Historique des caisses
                </a></li>
                <?php endif; ?>
                <?php if (hasPagePermissionFromDB('finance', 'expenses/integration_paiements', 'read')): ?>
                <li><a class="dropdown-item" href="integration_paiements.php">
                    <i class="fas fa-sync me-2"></i>Intégration paiements
                </a></li>
                <?php endif; ?>
                <?php if (hasPagePermissionFromDB('finance', 'expenses/maintenance_caisses', 'read')): ?>
                <li><a class="dropdown-item" href="maintenance_caisses.php">
                    <i class="fas fa-tools me-2"></i>Maintenance
                </a></li>
                <?php endif; ?>
            </ul>
        </div>
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
                <h5 class="card-title text-success"><?php echo number_format($stats['montant_total'], 0, ',', ' '); ?> <?php echo htmlspecialchars($devise_par_defaut['symbole'] ?? 'FC'); ?></h5>
                <p class="card-text">Montant total</p>
            </div>
        </div>
    </div>
</div>

<!-- Statistiques des caisses -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card border-success">
            <div class="card-header bg-success text-white">
                <h6 class="mb-0">
                    <i class="fas fa-cash-register me-2"></i>
                    Activité des Caisses - Aujourd'hui
                </h6>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-2">
                        <div class="text-center">
                            <h4 class="text-warning stats-sessions-ouvertes"><?php echo $stats_caisses['sessions_ouvertes']; ?></h4>
                            <small>Sessions ouvertes</small>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="text-center">
                            <h4 class="text-primary stats-mouvements"><?php echo $stats_caisses['mouvements_aujourdhui']; ?></h4>
                            <small>Mouvements</small>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="text-center">
                            <h4 class="text-success stats-entrees"><?php echo number_format($stats_caisses['entrees_aujourdhui'], 0, ',', ' '); ?> <?php echo htmlspecialchars($devise_par_defaut['symbole'] ?? 'FC'); ?></h4>
                            <small>Entrées</small>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="text-center">
                            <h4 class="text-danger stats-sorties"><?php echo number_format($stats_caisses['sorties_aujourdhui'], 0, ',', ' '); ?> <?php echo htmlspecialchars($devise_par_defaut['symbole'] ?? 'FC'); ?></h4>
                            <small>Sorties</small>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="text-center">
                            <h4 class="text-info stats-depenses-sync"><?php echo $stats_caisses['depenses_synchronisees']; ?></h4>
                            <small>Dépenses synchronisées</small>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="text-center">
                            <a href="caisses.php" class="btn btn-success btn-sm">
                                <i class="fas fa-cash-register me-1"></i>
                                Gérer les caisses
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Alertes importantes -->
<?php if ($stats_caisses['sessions_ouvertes'] > 0): ?>
<div class="row mb-4">
    <div class="col-12">
        <div class="alert alert-info">
            <h6><i class="fas fa-info-circle me-2"></i>Information</h6>
            <p class="mb-2">
                <strong><?php echo $stats_caisses['sessions_ouvertes']; ?> session(s) de caisse ouverte(s)</strong> - 
                Les nouvelles dépenses seront automatiquement enregistrées dans la caisse active.
            </p>
            <a href="caisses.php" class="btn btn-sm btn-outline-info">
                <i class="fas fa-eye me-1"></i>Voir les sessions ouvertes
            </a>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Actions rapides -->
<?php if (checkPagePermission('finance') && $stats_caisses['sessions_ouvertes'] > 0): ?>
<div class="row mb-4">
    <div class="col-12">
        <div class="card border-warning">
            <div class="card-header bg-warning text-dark">
                <h6 class="mb-0">
                    <i class="fas fa-bolt me-2"></i>
                    Actions Rapides
                </h6>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-4">
                        <div class="d-grid">
                            <a href="integration_paiements.php" class="btn btn-outline-primary">
                                <i class="fas fa-sync me-2"></i>
                                Synchroniser les paiements
                            </a>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="d-grid">
                            <a href="integration_paiements.php" class="btn btn-outline-danger">
                                <i class="fas fa-sync me-2"></i>
                                Synchroniser les dépenses
                            </a>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="d-grid">
                            <a href="caisses.php" class="btn btn-outline-success">
                                <i class="fas fa-cash-register me-2"></i>
                                Ouvrir une session
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

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
            <div class="col-md-2">
                <label for="sync" class="form-label">Synchronisation</label>
                <select class="form-select" id="sync" name="sync">
                    <option value="">Toutes</option>
                    <option value="sync" <?php echo $sync_filter === 'sync' ? 'selected' : ''; ?>>Synchronisées</option>
                    <option value="nosync" <?php echo $sync_filter === 'nosync' ? 'selected' : ''; ?>>Non synchronisées</option>
                </select>
            </div>
            <div class="col-md-2">
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
                            <th>Caisse</th>
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
                                        <?php 
                                        $montant_converti = convertToDefaultCurrency($depense['montant'], $depense['devise_id']);
                                        echo number_format($montant_converti, 0, ',', ' ') . ' ' . htmlspecialchars($devise_par_defaut['symbole'] ?? 'FC');
                                        ?>
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
                                    <?php if ($depense['synchronisee_caisse']): ?>
                                        <span class="badge bg-success">
                                            <i class="fas fa-check me-1"></i>Synchronisée
                                        </span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">
                                            <i class="fas fa-times me-1"></i>Non synchronisée
                                        </span>
                                    <?php endif; ?>
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
                                        <?php if (checkPagePermission('finance')): ?>
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
                                            <?php if (!$depense['synchronisee_caisse'] && $stats_caisses['sessions_ouvertes'] > 0): ?>
                                                <a href="integration_paiements.php?sync_depense=<?php echo $depense['id']; ?>" 
                                                   class="btn btn-outline-warning" 
                                                   title="Synchroniser avec la caisse">
                                                    <i class="fas fa-sync"></i>
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
                <?php if (checkPagePermission('finance')): ?>
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
    
    // Actualiser les statistiques des caisses toutes les 30 secondes
    setInterval(function() {
        $.get('ajax_caisse_stats.php', function(data) {
            if (data.success) {
                // Mettre à jour les statistiques
                $('.stats-sessions-ouvertes').text(data.sessions_ouvertes);
                $('.stats-mouvements').text(data.mouvements_aujourdhui);
                $('.stats-entrees').text(data.entrees_aujourdhui.toLocaleString() + ' ' + data.devise_symbole);
                $('.stats-sorties').text(data.sorties_aujourdhui.toLocaleString() + ' ' + data.devise_symbole);
                $('.stats-depenses-sync').text(data.depenses_synchronisees);
            }
        }).fail(function() {
            console.log('Erreur lors de la mise à jour des statistiques');
        });
    }, 30000); // 30 secondes
});
</script>

<?php include '../../../includes/footer.php'; ?>
