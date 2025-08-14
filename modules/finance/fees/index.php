<?php
/**
 * Module de gestion financière - Gestion des frais scolaires
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

$page_title = 'Gestion des Frais Scolaires';

// Obtenir l'année scolaire actuelle
$current_year = getCurrentAcademicYear();

// Paramètres de filtrage
$niveau_filter = sanitizeInput($_GET['niveau'] ?? '');
$type_filter = sanitizeInput($_GET['type'] ?? '');

// Vérifier si la table frais_scolaires existe, sinon la créer
try {
    $table_exists = $database->query("SHOW TABLES LIKE 'frais_scolaires'")->fetch();
    if (!$table_exists) {
        $create_table = "
            CREATE TABLE frais_scolaires (
                id INT PRIMARY KEY AUTO_INCREMENT,
                classe_id INT NOT NULL,
                type_frais ENUM('inscription', 'mensualite', 'examen', 'uniforme', 'transport', 'cantine', 'autre') NOT NULL,
                libelle VARCHAR(255) NOT NULL,
                montant DECIMAL(10,2) NOT NULL,
                obligatoire BOOLEAN DEFAULT TRUE,
                date_echeance DATE,
                description TEXT,
                annee_scolaire_id INT NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (classe_id) REFERENCES classes(id),
                FOREIGN KEY (annee_scolaire_id) REFERENCES annees_scolaires(id),
                INDEX idx_classe_type (classe_id, type_frais),
                INDEX idx_annee_scolaire (annee_scolaire_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ";
        $database->execute($create_table);
    }
} catch (Exception $e) {
    // Table creation failed, continue anyway
}

// Construction de la requête
$frais = [];

try {
    // Vérifier d'abord si la table existe
    $table_check = $database->query("SHOW TABLES LIKE 'frais_scolaires'")->fetch();

    if ($table_check) {
        $sql = "SELECT f.*, c.nom as classe_nom, c.niveau
                FROM frais_scolaires f
                JOIN classes c ON f.classe_id = c.id
                WHERE f.annee_scolaire_id = ?";

        $params = [$current_year['id'] ?? 0];

        if (!empty($niveau_filter)) {
            $sql .= " AND c.niveau = ?";
            $params[] = $niveau_filter;
        }

        if (!empty($type_filter)) {
            $sql .= " AND f.type_frais = ?";
            $params[] = $type_filter;
        }

        $sql .= " ORDER BY c.niveau, c.nom, f.type_frais";

        $frais = $database->query($sql, $params)->fetchAll();
    }
} catch (Exception $e) {
    $frais = [];
    // Optionnel: log l'erreur
    error_log("Erreur frais_scolaires: " . $e->getMessage());
}

// Récupérer les classes pour les statistiques
$classes = $database->query(
    "SELECT id, nom, niveau FROM classes WHERE annee_scolaire_id = ? ORDER BY niveau, nom",
    [$current_year['id'] ?? 0]
)->fetchAll();

// Statistiques
$stats = [];
$stats['total_configurations'] = count($frais);
$stats['classes_configurees'] = count(array_unique(array_column($frais, 'classe_id')));
$stats['classes_total'] = count($classes);
$stats['montant_moyen'] = count($frais) > 0 ? round(array_sum(array_column($frais, 'montant')) / count($frais)) : 0;

// Répartition par niveau
$frais_par_niveau = [];
foreach ($frais as $frais_item) {
    $niveau = $frais_item['niveau'];
    if (!isset($frais_par_niveau[$niveau])) {
        $frais_par_niveau[$niveau] = [
            'count' => 0,
            'total' => 0,
            'types' => []
        ];
    }
    $frais_par_niveau[$niveau]['count']++;
    $frais_par_niveau[$niveau]['total'] += $frais_item['montant'];
    $frais_par_niveau[$niveau]['types'][$frais_item['type_frais']] = 
        ($frais_par_niveau[$niveau]['types'][$frais_item['type_frais']] ?? 0) + $frais_item['montant'];
}

include '../../../includes/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">
        <i class="fas fa-tags me-2"></i>
        Gestion des Frais Scolaires
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
                <button type="button" class="btn btn-primary dropdown-toggle" data-bs-toggle="dropdown">
                    <i class="fas fa-plus me-1"></i>
                    Nouveau
                </button>
                <ul class="dropdown-menu">
                    <li><a class="dropdown-item" href="add.php">
                        <i class="fas fa-plus me-2"></i>Configurer frais
                    </a></li>
                    <li><a class="dropdown-item" href="bulk-add.php">
                        <i class="fas fa-layer-group me-2"></i>Configuration en lot
                    </a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item" href="templates.php">
                        <i class="fas fa-copy me-2"></i>Modèles de frais
                    </a></li>
                </ul>
            </div>
        <?php endif; ?>
        <div class="btn-group">
            <button type="button" class="btn btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">
                <i class="fas fa-download me-1"></i>
                Exporter
            </button>
            <ul class="dropdown-menu">
                <li><a class="dropdown-item" href="export.php?format=excel">
                    <i class="fas fa-file-excel me-2"></i>Excel
                </a></li>
                <li><a class="dropdown-item" href="export.php?format=pdf">
                    <i class="fas fa-file-pdf me-2"></i>PDF
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
                        <h4><?php echo $stats['total_configurations']; ?></h4>
                        <p class="mb-0">Configurations</p>
                    </div>
                    <div class="align-self-center">
                        <i class="fas fa-cog fa-2x"></i>
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
                        <h4><?php echo $stats['classes_configurees']; ?>/<?php echo $stats['classes_total']; ?></h4>
                        <p class="mb-0">Classes configurées</p>
                    </div>
                    <div class="align-self-center">
                        <i class="fas fa-school fa-2x"></i>
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
                        <h5><?php echo formatMoney($stats['montant_moyen']); ?></h5>
                        <p class="mb-0">Montant moyen</p>
                    </div>
                    <div class="align-self-center">
                        <i class="fas fa-calculator fa-2x"></i>
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
                        <h4><?php echo $stats['classes_total'] - $stats['classes_configurees']; ?></h4>
                        <p class="mb-0">Classes sans frais</p>
                    </div>
                    <div class="align-self-center">
                        <i class="fas fa-exclamation-triangle fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Filtres -->
<div class="card mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-md-4">
                <label for="niveau" class="form-label">Niveau</label>
                <select class="form-select" id="niveau" name="niveau">
                    <option value="">Tous les niveaux</option>
                    <option value="maternelle" <?php echo $niveau_filter === 'maternelle' ? 'selected' : ''; ?>>Maternelle</option>
                    <option value="primaire" <?php echo $niveau_filter === 'primaire' ? 'selected' : ''; ?>>Primaire</option>
                    <option value="secondaire" <?php echo $niveau_filter === 'secondaire' ? 'selected' : ''; ?>>Secondaire</option>
                </select>
            </div>
            <div class="col-md-4">
                <label for="type" class="form-label">Type de frais</label>
                <select class="form-select" id="type" name="type">
                    <option value="">Tous les types</option>
                    <option value="inscription" <?php echo $type_filter === 'inscription' ? 'selected' : ''; ?>>Inscription</option>
                    <option value="mensualite" <?php echo $type_filter === 'mensualite' ? 'selected' : ''; ?>>Mensualité</option>
                    <option value="examen" <?php echo $type_filter === 'examen' ? 'selected' : ''; ?>>Examen</option>
                    <option value="uniforme" <?php echo $type_filter === 'uniforme' ? 'selected' : ''; ?>>Uniforme</option>
                    <option value="transport" <?php echo $type_filter === 'transport' ? 'selected' : ''; ?>>Transport</option>
                    <option value="cantine" <?php echo $type_filter === 'cantine' ? 'selected' : ''; ?>>Cantine</option>
                    <option value="autre" <?php echo $type_filter === 'autre' ? 'selected' : ''; ?>>Autre</option>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label">&nbsp;</label>
                <div class="d-grid">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search me-1"></i>
                        Filtrer
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Configuration des frais -->
<div class="card">
    <div class="card-header">
        <h5 class="mb-0">
            <i class="fas fa-list me-2"></i>
            Configuration des frais (<?php echo count($frais); ?>)
        </h5>
    </div>
    <div class="card-body">
        <?php if (!empty($frais)): ?>
            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead>
                        <tr>
                            <th>Classe</th>
                            <th>Niveau</th>
                            <th>Type de frais</th>
                            <th>Montant</th>
                            <th>Obligatoire</th>
                            <th>Échéance</th>
                            <th>Description</th>
                            <th class="no-sort">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($frais as $frais_item): ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($frais_item['classe_nom']); ?></strong>
                                </td>
                                <td>
                                    <?php
                                    $niveau_colors = [
                                        'maternelle' => 'warning',
                                        'primaire' => 'success',
                                        'secondaire' => 'primary'
                                    ];
                                    $color = $niveau_colors[$frais_item['niveau']] ?? 'secondary';
                                    ?>
                                    <span class="badge bg-<?php echo $color; ?>">
                                        <?php echo ucfirst($frais_item['niveau']); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php
                                    $type_colors = [
                                        'inscription' => 'primary',
                                        'mensualite' => 'success',
                                        'examen' => 'warning',
                                        'uniforme' => 'info',
                                        'transport' => 'secondary',
                                        'cantine' => 'dark',
                                        'autre' => 'light'
                                    ];
                                    $color = $type_colors[$frais_item['type_frais']] ?? 'secondary';
                                    ?>
                                    <span class="badge bg-<?php echo $color; ?>">
                                        <?php echo ucfirst($frais_item['type_frais']); ?>
                                    </span>
                                </td>
                                <td>
                                    <strong class="text-success">
                                        <?php echo formatMoney($frais_item['montant']); ?>
                                    </strong>
                                </td>
                                <td>
                                    <?php if ($frais_item['obligatoire']): ?>
                                        <span class="badge bg-danger">
                                            <i class="fas fa-star me-1"></i>Obligatoire
                                        </span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Optionnel</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($frais_item['date_echeance']): ?>
                                        <?php echo formatDate($frais_item['date_echeance']); ?>
                                        <?php
                                        $jours_restants = (strtotime($frais_item['date_echeance']) - time()) / (24 * 3600);
                                        if ($jours_restants < 0): ?>
                                            <br><small class="text-danger">Échue</small>
                                        <?php elseif ($jours_restants < 30): ?>
                                            <br><small class="text-warning"><?php echo round($jours_restants); ?> jours</small>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="text-muted">Non définie</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($frais_item['description']): ?>
                                        <small><?php echo htmlspecialchars(substr($frais_item['description'], 0, 50)); ?>
                                        <?php echo strlen($frais_item['description']) > 50 ? '...' : ''; ?></small>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <a href="view.php?id=<?php echo $frais_item['id']; ?>" 
                                           class="btn btn-outline-info" 
                                           title="Voir détails">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <?php if (checkPermission('finance')): ?>
                                            <a href="edit.php?id=<?php echo $frais_item['id']; ?>" 
                                               class="btn btn-outline-primary" 
                                               title="Modifier">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <a href="duplicate.php?id=<?php echo $frais_item['id']; ?>" 
                                               class="btn btn-outline-secondary" 
                                               title="Dupliquer">
                                                <i class="fas fa-copy"></i>
                                            </a>
                                            <a href="delete.php?id=<?php echo $frais_item['id']; ?>" 
                                               class="btn btn-outline-danger" 
                                               title="Supprimer"
                                               onclick="return confirm('Êtes-vous sûr de vouloir supprimer cette configuration ?')">
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
                <i class="fas fa-tags fa-3x text-muted mb-3"></i>
                <h5 class="text-muted">Aucun frais configuré</h5>
                <p class="text-muted">
                    <?php if (!empty($niveau_filter) || !empty($type_filter)): ?>
                        Aucun frais ne correspond aux critères sélectionnés.
                    <?php else: ?>
                        <?php
                        // Vérifier si la table existe
                        try {
                            $table_exists = $database->query("SHOW TABLES LIKE 'frais_scolaires'")->fetch();
                            if (!$table_exists): ?>
                                La table des frais scolaires n'existe pas encore.
                                <br><strong>Veuillez d'abord créer la table en visitant :</strong>
                                <br><a href="../../create_fees_table.php" class="text-primary">create_fees_table.php</a>
                            <?php else: ?>
                                Aucun frais scolaire n'a encore été configuré.
                            <?php endif;
                        } catch (Exception $e) {
                            echo "Aucun frais scolaire n'a encore été configuré.";
                        } ?>
                    <?php endif; ?>
                </p>
                <?php if (checkPermission('finance')): ?>
                    <div class="mt-3">
                        <a href="add.php" class="btn btn-primary me-2">
                            <i class="fas fa-plus me-1"></i>
                            Configurer les premiers frais
                        </a>
                        <a href="templates.php" class="btn btn-outline-primary">
                            <i class="fas fa-copy me-1"></i>
                            Utiliser un modèle
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Résumé par niveau -->
<?php if (!empty($frais_par_niveau)): ?>
<div class="row mt-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-chart-bar me-2"></i>
                    Résumé par niveau
                </h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <?php foreach ($frais_par_niveau as $niveau => $data): ?>
                        <div class="col-lg-4 mb-3">
                            <div class="card border-0 shadow-sm">
                                <div class="card-header bg-<?php 
                                    echo $niveau === 'maternelle' ? 'warning' : 
                                        ($niveau === 'primaire' ? 'success' : 'primary'); 
                                ?> text-white">
                                    <h6 class="mb-0"><?php echo ucfirst($niveau); ?></h6>
                                </div>
                                <div class="card-body">
                                    <div class="row text-center mb-3">
                                        <div class="col-6">
                                            <h5 class="text-primary"><?php echo $data['count']; ?></h5>
                                            <small class="text-muted">Configurations</small>
                                        </div>
                                        <div class="col-6">
                                            <h5 class="text-success"><?php echo formatMoney($data['total']); ?></h5>
                                            <small class="text-muted">Total</small>
                                        </div>
                                    </div>
                                    
                                    <h6>Types de frais :</h6>
                                    <?php foreach ($data['types'] as $type => $montant): ?>
                                        <div class="d-flex justify-content-between align-items-center mb-1">
                                            <small><?php echo ucfirst($type); ?></small>
                                            <span class="badge bg-light text-dark"><?php echo formatMoney($montant); ?></span>
                                        </div>
                                    <?php endforeach; ?>
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

<!-- Actions rapides -->
<?php if (checkPermission('finance')): ?>
<div class="row mt-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-bolt me-2"></i>
                    Actions rapides
                </h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-2 mb-2">
                        <div class="d-grid">
                            <a href="add.php" class="btn btn-outline-primary">
                                <i class="fas fa-plus me-2"></i>
                                Nouveau frais
                            </a>
                        </div>
                    </div>
                    <div class="col-md-2 mb-2">
                        <div class="d-grid">
                            <a href="manage.php" class="btn btn-outline-warning">
                                <i class="fas fa-cogs me-2"></i>
                                Gestion avancée
                            </a>
                        </div>
                    </div>
                    <div class="col-md-2 mb-2">
                        <div class="d-grid">
                            <a href="bulk-add.php" class="btn btn-outline-success">
                                <i class="fas fa-layer-group me-2"></i>
                                Configuration en lot
                            </a>
                        </div>
                    </div>
                    <div class="col-md-2 mb-2">
                        <div class="d-grid">
                            <a href="templates.php" class="btn btn-outline-info">
                                <i class="fas fa-copy me-2"></i>
                                Modèles de frais
                            </a>
                        </div>
                    </div>
                    <div class="col-md-2 mb-2">
                        <div class="d-grid">
                            <a href="../reports/fees-analysis.php" class="btn btn-outline-secondary">
                                <i class="fas fa-chart-bar me-2"></i>
                                Analyse des frais
                            </a>
                        </div>
                    </div>
                    <div class="col-md-2 mb-2">
                        <div class="d-grid">
                            <a href="export.php?format=excel" class="btn btn-outline-dark">
                                <i class="fas fa-file-excel me-2"></i>
                                Exporter Excel
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<?php include '../../../includes/footer.php'; ?>
