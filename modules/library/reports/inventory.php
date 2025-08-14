<?php
/**
 * Module Bibliothèque - Rapport d'inventaire
 * Application de gestion scolaire - République Démocratique du Congo
 */

require_once '../../../config/config.php';
require_once '../../../config/database.php';
require_once '../../../includes/functions.php';

// Vérifier l'authentification et les permissions
requireLogin();
if (!checkPermission('library')) {
    showMessage('error', 'Accès refusé à cette page.');
    redirectTo('index.php');
}

// Paramètres de filtrage
$category_filter = $_GET['category'] ?? '';
$status_filter = $_GET['status'] ?? '';
$etat_filter = $_GET['etat'] ?? '';

// Construction de la requête WHERE
$where_conditions = [];
$params = [];

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

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Statistiques générales
try {
    $stats_generales = $database->query(
        "SELECT 
            COUNT(*) as total_livres,
            SUM(nombre_exemplaires) as total_exemplaires,
            SUM(CASE WHEN status = 'disponible' THEN nombre_exemplaires ELSE 0 END) as exemplaires_disponibles,
            SUM(CASE WHEN status = 'emprunte' THEN nombre_exemplaires ELSE 0 END) as exemplaires_empruntes,
            SUM(CASE WHEN status = 'perdu' THEN nombre_exemplaires ELSE 0 END) as exemplaires_perdus,
            SUM(CASE WHEN status = 'retire' THEN nombre_exemplaires ELSE 0 END) as exemplaires_retires,
            SUM(CASE WHEN prix_achat IS NOT NULL THEN prix_achat * nombre_exemplaires ELSE 0 END) as valeur_totale
         FROM livres l $where_clause",
        $params
    )->fetch();
} catch (Exception $e) {
    $stats_generales = [
        'total_livres' => 0,
        'total_exemplaires' => 0,
        'exemplaires_disponibles' => 0,
        'exemplaires_empruntes' => 0,
        'exemplaires_perdus' => 0,
        'exemplaires_retires' => 0,
        'valeur_totale' => 0
    ];
}

// Statistiques par catégorie
try {
    $stats_categories = $database->query(
        "SELECT cl.nom as categorie, cl.couleur,
                COUNT(l.id) as nb_titres,
                SUM(l.nombre_exemplaires) as nb_exemplaires,
                SUM(CASE WHEN l.status = 'disponible' THEN l.nombre_exemplaires ELSE 0 END) as disponibles,
                SUM(CASE WHEN l.prix_achat IS NOT NULL THEN l.prix_achat * l.nombre_exemplaires ELSE 0 END) as valeur
         FROM categories_livres cl
         LEFT JOIN livres l ON cl.id = l.categorie_id
         " . ($where_clause ? str_replace('WHERE', 'AND', $where_clause) : '') . "
         GROUP BY cl.id, cl.nom, cl.couleur
         ORDER BY nb_titres DESC",
        $params
    )->fetchAll();
} catch (Exception $e) {
    $stats_categories = [];
}

// Statistiques par état
try {
    $stats_etats = $database->query(
        "SELECT etat,
                COUNT(*) as nb_titres,
                SUM(nombre_exemplaires) as nb_exemplaires
         FROM livres l $where_clause
         GROUP BY etat
         ORDER BY nb_exemplaires DESC",
        $params
    )->fetchAll();
} catch (Exception $e) {
    $stats_etats = [];
}

// Livres les plus anciens
try {
    $livres_anciens = $database->query(
        "SELECT l.titre, l.auteur, l.date_acquisition, l.etat, cl.nom as categorie
         FROM livres l
         LEFT JOIN categories_livres cl ON l.categorie_id = cl.id
         $where_clause
         ORDER BY l.date_acquisition ASC
         LIMIT 10",
        $params
    )->fetchAll();
} catch (Exception $e) {
    $livres_anciens = [];
}

// Récupérer les catégories pour le filtre
try {
    $categories = $database->query("SELECT * FROM categories_livres ORDER BY nom")->fetchAll();
} catch (Exception $e) {
    $categories = [];
}

$page_title = "Rapport d'Inventaire";
include '../../../includes/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">
        <i class="fas fa-chart-bar me-2"></i>
        Rapport d'Inventaire
    </h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <div class="btn-group me-2">
            <a href="../" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-1"></i>
                Retour à la bibliothèque
            </a>
        </div>
        <div class="btn-group me-2">
            <button type="button" class="btn btn-success" onclick="window.print()">
                <i class="fas fa-print me-1"></i>
                Imprimer
            </button>
        </div>
    </div>
</div>

<!-- Filtres -->
<div class="card mb-4 d-print-none">
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-md-4">
                <label for="category" class="form-label">Catégorie</label>
                <select class="form-select" id="category" name="category">
                    <option value="">Toutes les catégories</option>
                    <?php foreach ($categories as $categorie): ?>
                        <option value="<?php echo $categorie['id']; ?>" 
                                <?php echo $category_filter == $categorie['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($categorie['nom']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label for="status" class="form-label">Statut</label>
                <select class="form-select" id="status" name="status">
                    <option value="">Tous les statuts</option>
                    <option value="disponible" <?php echo $status_filter === 'disponible' ? 'selected' : ''; ?>>Disponible</option>
                    <option value="emprunte" <?php echo $status_filter === 'emprunte' ? 'selected' : ''; ?>>Emprunté</option>
                    <option value="reserve" <?php echo $status_filter === 'reserve' ? 'selected' : ''; ?>>Réservé</option>
                    <option value="perdu" <?php echo $status_filter === 'perdu' ? 'selected' : ''; ?>>Perdu</option>
                    <option value="retire" <?php echo $status_filter === 'retire' ? 'selected' : ''; ?>>Retiré</option>
                </select>
            </div>
            <div class="col-md-3">
                <label for="etat" class="form-label">État</label>
                <select class="form-select" id="etat" name="etat">
                    <option value="">Tous les états</option>
                    <option value="excellent" <?php echo $etat_filter === 'excellent' ? 'selected' : ''; ?>>Excellent</option>
                    <option value="bon" <?php echo $etat_filter === 'bon' ? 'selected' : ''; ?>>Bon</option>
                    <option value="moyen" <?php echo $etat_filter === 'moyen' ? 'selected' : ''; ?>>Moyen</option>
                    <option value="mauvais" <?php echo $etat_filter === 'mauvais' ? 'selected' : ''; ?>>Mauvais</option>
                    <option value="hors_service" <?php echo $etat_filter === 'hors_service' ? 'selected' : ''; ?>>Hors service</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">&nbsp;</label>
                <button type="submit" class="btn btn-primary d-block w-100">
                    <i class="fas fa-filter me-1"></i>
                    Filtrer
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Statistiques générales -->
<div class="row mb-4">
    <div class="col-md-2">
        <div class="card text-center shadow-sm">
            <div class="card-body">
                <i class="fas fa-book fa-2x text-primary mb-2"></i>
                <h5 class="card-title"><?php echo number_format($stats_generales['total_livres'] ?? 0); ?></h5>
                <p class="card-text text-muted">Titres</p>
            </div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card text-center shadow-sm">
            <div class="card-body">
                <i class="fas fa-books fa-2x text-info mb-2"></i>
                <h5 class="card-title"><?php echo number_format($stats_generales['total_exemplaires'] ?? 0); ?></h5>
                <p class="card-text text-muted">Exemplaires</p>
            </div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card text-center shadow-sm">
            <div class="card-body">
                <i class="fas fa-check-circle fa-2x text-success mb-2"></i>
                <h5 class="card-title"><?php echo number_format($stats_generales['exemplaires_disponibles'] ?? 0); ?></h5>
                <p class="card-text text-muted">Disponibles</p>
            </div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card text-center shadow-sm">
            <div class="card-body">
                <i class="fas fa-hand-holding fa-2x text-warning mb-2"></i>
                <h5 class="card-title"><?php echo number_format($stats_generales['exemplaires_empruntes'] ?? 0); ?></h5>
                <p class="card-text text-muted">Empruntés</p>
            </div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card text-center shadow-sm">
            <div class="card-body">
                <i class="fas fa-exclamation-triangle fa-2x text-danger mb-2"></i>
                <h5 class="card-title"><?php echo number_format($stats_generales['exemplaires_perdus'] ?? 0); ?></h5>
                <p class="card-text text-muted">Perdus</p>
            </div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card text-center shadow-sm">
            <div class="card-body">
                <i class="fas fa-coins fa-2x text-success mb-2"></i>
                <h5 class="card-title"><?php echo number_format($stats_generales['valeur_totale'] ?? 0); ?></h5>
                <p class="card-text text-muted">FC (Valeur)</p>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <!-- Répartition par catégorie -->
    <div class="col-md-6 mb-4">
        <div class="card shadow-sm">
            <div class="card-header bg-light">
                <h5 class="mb-0">
                    <i class="fas fa-tags me-2"></i>
                    Répartition par catégorie
                </h5>
            </div>
            <div class="card-body">
                <?php if (!empty($stats_categories)): ?>
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Catégorie</th>
                                    <th>Titres</th>
                                    <th>Exemplaires</th>
                                    <th>Disponibles</th>
                                    <th>Valeur (FC)</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($stats_categories as $cat): ?>
                                    <tr>
                                        <td>
                                            <span class="badge" style="background-color: <?php echo $cat['couleur']; ?>; width: 15px; height: 15px;"></span>
                                            <?php echo htmlspecialchars($cat['categorie']); ?>
                                        </td>
                                        <td><?php echo number_format($cat['nb_titres'] ?? 0); ?></td>
                                        <td><?php echo number_format($cat['nb_exemplaires'] ?? 0); ?></td>
                                        <td><?php echo number_format($cat['disponibles'] ?? 0); ?></td>
                                        <td><?php echo number_format($cat['valeur'] ?? 0); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p class="text-muted">Aucune donnée disponible.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Répartition par état -->
    <div class="col-md-6 mb-4">
        <div class="card shadow-sm">
            <div class="card-header bg-light">
                <h5 class="mb-0">
                    <i class="fas fa-clipboard-check me-2"></i>
                    Répartition par état
                </h5>
            </div>
            <div class="card-body">
                <?php if (!empty($stats_etats)): ?>
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>État</th>
                                    <th>Titres</th>
                                    <th>Exemplaires</th>
                                    <th>Pourcentage</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $total_exemplaires = $stats_generales['total_exemplaires'] ?? 1;
                                foreach ($stats_etats as $etat): 
                                    $pourcentage = ($etat['nb_exemplaires'] / $total_exemplaires) * 100;
                                ?>
                                    <tr>
                                        <td>
                                            <?php
                                            $etat_colors = [
                                                'excellent' => 'success',
                                                'bon' => 'primary',
                                                'moyen' => 'warning',
                                                'mauvais' => 'danger',
                                                'hors_service' => 'secondary'
                                            ];
                                            $color = $etat_colors[$etat['etat']] ?? 'secondary';
                                            ?>
                                            <span class="badge bg-<?php echo $color; ?>">
                                                <?php echo ucfirst($etat['etat']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo number_format($etat['nb_titres'] ?? 0); ?></td>
                                        <td><?php echo number_format($etat['nb_exemplaires'] ?? 0); ?></td>
                                        <td><?php echo number_format($pourcentage, 1); ?>%</td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p class="text-muted">Aucune donnée disponible.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Livres les plus anciens -->
<div class="card shadow-sm">
    <div class="card-header bg-light">
        <h5 class="mb-0">
            <i class="fas fa-history me-2"></i>
            Livres les plus anciens (Top 10)
        </h5>
    </div>
    <div class="card-body">
        <?php if (!empty($livres_anciens)): ?>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead class="table-light">
                        <tr>
                            <th>Titre</th>
                            <th>Auteur</th>
                            <th>Catégorie</th>
                            <th>Date d'acquisition</th>
                            <th>Ancienneté</th>
                            <th>État</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($livres_anciens as $livre): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($livre['titre']); ?></strong></td>
                                <td><?php echo htmlspecialchars($livre['auteur']); ?></td>
                                <td><?php echo htmlspecialchars($livre['categorie'] ?? 'Non catégorisé'); ?></td>
                                <td><?php echo formatDate($livre['date_acquisition']); ?></td>
                                <td>
                                    <?php 
                                    $anciennete = floor((time() - strtotime($livre['date_acquisition'])) / (365 * 24 * 60 * 60));
                                    echo $anciennete . ' an(s)';
                                    ?>
                                </td>
                                <td>
                                    <?php
                                    $etat_colors = [
                                        'excellent' => 'success',
                                        'bon' => 'primary',
                                        'moyen' => 'warning',
                                        'mauvais' => 'danger',
                                        'hors_service' => 'secondary'
                                    ];
                                    $color = $etat_colors[$livre['etat']] ?? 'secondary';
                                    ?>
                                    <span class="badge bg-<?php echo $color; ?>">
                                        <?php echo ucfirst($livre['etat']); ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <p class="text-muted">Aucun livre trouvé.</p>
        <?php endif; ?>
    </div>
</div>

<style>
@media print {
    .d-print-none {
        display: none !important;
    }
    
    .card {
        border: 1px solid #dee2e6 !important;
        break-inside: avoid;
    }
    
    .card-header {
        background-color: #f8f9fa !important;
        -webkit-print-color-adjust: exact;
    }
}
</style>

<?php include '../../../includes/footer.php'; ?>
