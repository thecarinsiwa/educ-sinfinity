<?php
/**
 * Module Historique des Inscriptions par Année Scolaire
 * Application de gestion scolaire - République Démocratique du Congo
 */

require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../includes/functions.php';

// Vérifier l'authentification et les permissions
requireLogin();
if (!checkPermission('students') && !checkPermission('students_view')) {
    showMessage('error', 'Accès refusé à ce module.');
    redirectTo('../../dashboard.php');
}

$page_title = 'Historique des Inscriptions';

// Paramètres de filtrage
$annee_scolaire_id = isset($_GET['annee']) ? intval($_GET['annee']) : 0;
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$classe_filter = isset($_GET['classe']) ? intval($_GET['classe']) : 0;
$search = isset($_GET['search']) ? sanitizeInput($_GET['search']) : '';

// Obtenir toutes les années scolaires
$annees_scolaires = [];
try {
    $stmt = $database->query("SELECT * FROM annees_scolaires ORDER BY annee DESC");
    $annees_scolaires = $stmt->fetchAll();
} catch (Exception $e) {
    showMessage('error', 'Erreur lors de la récupération des années scolaires: ' . $e->getMessage());
}

// Si aucune année n'est sélectionnée, prendre la plus récente
if ($annee_scolaire_id == 0 && !empty($annees_scolaires)) {
    $annee_scolaire_id = $annees_scolaires[0]['id'];
}

// Obtenir les classes pour le filtre
$classes = [];
try {
    $stmt = $database->query("SELECT id, nom, niveau FROM classes ORDER BY niveau, nom");
    $classes = $stmt->fetchAll();
} catch (Exception $e) {
    showMessage('error', 'Erreur lors de la récupération des classes: ' . $e->getMessage());
}

// Construire la requête pour l'historique des inscriptions
$where_conditions = [];
$params = [];

if ($annee_scolaire_id > 0) {
    $where_conditions[] = "i.annee_scolaire_id = ?";
    $params[] = $annee_scolaire_id;
}

if (!empty($status_filter)) {
    $where_conditions[] = "i.status = ?";
    $params[] = $status_filter;
}

if ($classe_filter > 0) {
    $where_conditions[] = "i.classe_id = ?";
    $params[] = $classe_filter;
}

if (!empty($search)) {
    $where_conditions[] = "(e.nom LIKE ? OR e.prenom LIKE ? OR e.numero_matricule LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

$where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";

// Obtenir l'historique des inscriptions
$inscriptions = [];
$total_inscriptions = 0;

if ($annee_scolaire_id > 0) {
    try {
        // Requête pour le total
        $count_sql = "SELECT COUNT(*) as total 
                      FROM inscriptions i 
                      JOIN eleves e ON i.eleve_id = e.id 
                      JOIN classes c ON i.classe_id = c.id 
                      JOIN annees_scolaires a ON i.annee_scolaire_id = a.id 
                      $where_clause";
        
        $stmt = $database->query($count_sql, $params);
        $total_inscriptions = $stmt->fetch()['total'];
        
        // Requête pour les données
        $sql = "SELECT i.*, e.numero_matricule, e.nom, e.prenom, e.sexe, e.date_naissance, 
                       c.nom as classe_nom, c.niveau, a.annee as annee_scolaire,
                       CASE 
                           WHEN i.status = 'inscrit' THEN 'Inscrit'
                           WHEN i.status = 'transfere' THEN 'Transféré'
                           WHEN i.status = 'abandonne' THEN 'Abandonné'
                           ELSE i.status
                       END as status_lisible
                FROM inscriptions i 
                JOIN eleves e ON i.eleve_id = e.id 
                JOIN classes c ON i.classe_id = c.id 
                JOIN annees_scolaires a ON i.annee_scolaire_id = a.id 
                $where_clause
                ORDER BY e.nom, e.prenom, i.date_inscription DESC";
        
        $stmt = $database->query($sql, $params);
        $inscriptions = $stmt->fetchAll();
        
    } catch (Exception $e) {
        showMessage('error', 'Erreur lors de la récupération de l\'historique: ' . $e->getMessage());
    }
}

// Statistiques par année
$stats_par_annee = [];
try {
    $stmt = $database->query(
        "SELECT a.annee, a.id,
                COUNT(CASE WHEN i.status = 'inscrit' THEN 1 END) as inscrits,
                COUNT(CASE WHEN i.status = 'transfere' THEN 1 END) as transferes,
                COUNT(CASE WHEN i.status = 'abandonne' THEN 1 END) as abandonnes,
                COUNT(*) as total
         FROM annees_scolaires a
         LEFT JOIN inscriptions i ON a.id = i.annee_scolaire_id
         GROUP BY a.id, a.annee
         ORDER BY a.annee DESC"
    );
    $stats_par_annee = $stmt->fetchAll();
} catch (Exception $e) {
    // Ignorer l'erreur si la table n'existe pas encore
}

include '../../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="page-title-box">
                <div class="page-title-right">
                    <ol class="breadcrumb m-0">
                        <li class="breadcrumb-item"><a href="../../dashboard.php">Tableau de bord</a></li>
                        <li class="breadcrumb-item"><a href="../students/">Gestion des Élèves</a></li>
                        <li class="breadcrumb-item active">Historique des Inscriptions</li>
                    </ol>
                </div>
                <h4 class="page-title">Historique des Inscriptions par Année Scolaire</h4>
            </div>
        </div>
    </div>

    <?php displayMessage(); ?>

    <!-- Statistiques par année -->
    <div class="row">
        <?php foreach ($stats_par_annee as $stat): ?>
            <div class="col-xl-3 col-md-6">
                <div class="card <?php echo ($stat['id'] == $annee_scolaire_id) ? 'border-primary' : ''; ?>">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h5 class="text-muted fw-normal mt-0"><?php echo $stat['annee']; ?></h5>
                                <h3 class="mt-3 mb-3"><?php echo $stat['total']; ?></h3>
                                <div class="row text-center mt-3">
                                    <div class="col-6">
                                        <h4 class="font-weight-normal text-success"><?php echo $stat['inscrits']; ?></h4>
                                        <p class="text-muted mb-0">Inscrits</p>
                                    </div>
                                    <div class="col-6">
                                        <h4 class="font-weight-normal text-warning"><?php echo $stat['transferes'] + $stat['abandonnes']; ?></h4>
                                        <p class="text-muted mb-0">Sortis</p>
                                    </div>
                                </div>
                            </div>
                            <div class="avatar-sm">
                                <span class="avatar-title bg-soft-primary rounded">
                                    <i class="mdi mdi-calendar-multiple font-20 text-primary"></i>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- Filtres -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <form method="GET" class="row g-3">
                        <div class="col-md-3">
                            <label for="annee" class="form-label">Année Scolaire</label>
                            <select class="form-select" id="annee" name="annee">
                                <option value="">Toutes les années</option>
                                <?php foreach ($annees_scolaires as $annee): ?>
                                    <option value="<?php echo $annee['id']; ?>" 
                                            <?php echo ($annee['id'] == $annee_scolaire_id) ? 'selected' : ''; ?>>
                                        <?php echo $annee['annee']; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="col-md-2">
                            <label for="status" class="form-label">Statut</label>
                            <select class="form-select" id="status" name="status">
                                <option value="">Tous les statuts</option>
                                <option value="inscrit" <?php echo ($status_filter == 'inscrit') ? 'selected' : ''; ?>>Inscrit</option>
                                <option value="transfere" <?php echo ($status_filter == 'transfere') ? 'selected' : ''; ?>>Transféré</option>
                                <option value="abandonne" <?php echo ($status_filter == 'abandonne') ? 'selected' : ''; ?>>Abandonné</option>
                            </select>
                        </div>
                        
                        <div class="col-md-3">
                            <label for="classe" class="form-label">Classe</label>
                            <select class="form-select" id="classe" name="classe">
                                <option value="">Toutes les classes</option>
                                <?php foreach ($classes as $classe): ?>
                                    <option value="<?php echo $classe['id']; ?>" 
                                            <?php echo ($classe['id'] == $classe_filter) ? 'selected' : ''; ?>>
                                        <?php echo $classe['nom']; ?> (<?php echo $classe['niveau']; ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="col-md-3">
                            <label for="search" class="form-label">Recherche</label>
                            <input type="text" class="form-control" id="search" name="search" 
                                   placeholder="Nom, prénom ou matricule" value="<?php echo htmlspecialchars($search); ?>">
                        </div>
                        
                        <div class="col-md-1">
                            <label class="form-label">&nbsp;</label>
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="mdi mdi-magnify"></i>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Résultats -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h4 class="header-title">
                        <i class="mdi mdi-history me-2"></i>
                        Historique des Inscriptions
                    </h4>
                    <p class="text-muted mb-0">
                        <?php if ($annee_scolaire_id > 0): ?>
                            Année scolaire: <?php echo $annees_scolaires[array_search($annee_scolaire_id, array_column($annees_scolaires, 'id'))]['annee']; ?>
                        <?php endif; ?>
                        • Total: <?php echo $total_inscriptions; ?> inscription(s)
                    </p>
                </div>
                <div class="card-body">
                    <?php if (empty($inscriptions)): ?>
                        <div class="alert alert-info">
                            <i class="mdi mdi-information-outline me-2"></i>
                            Aucune inscription trouvée avec les critères sélectionnés.
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-centered table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>Matricule</th>
                                        <th>Nom et Prénom</th>
                                        <th>Année Scolaire</th>
                                        <th>Classe</th>
                                        <th>Date d'Inscription</th>
                                        <th>Statut</th>
                                        <th>Frais Payés</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($inscriptions as $inscription): ?>
                                        <tr>
                                            <td>
                                                <span class="badge bg-light text-dark"><?php echo $inscription['numero_matricule']; ?></span>
                                            </td>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <div class="avatar-sm me-3">
                                                        <span class="avatar-title bg-soft-primary rounded-circle">
                                                            <?php echo strtoupper(substr($inscription['prenom'], 0, 1) . substr($inscription['nom'], 0, 1)); ?>
                                                        </span>
                                                    </div>
                                                    <div>
                                                        <h6 class="mb-0"><?php echo $inscription['prenom'] . ' ' . $inscription['nom']; ?></h6>
                                                        <small class="text-muted">
                                                            <?php echo $inscription['sexe'] === 'M' ? 'Garçon' : 'Fille'; ?> • 
                                                            <?php echo date('d/m/Y', strtotime($inscription['date_naissance'])); ?>
                                                        </small>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="badge bg-info"><?php echo $inscription['annee_scolaire']; ?></span>
                                            </td>
                                            <td>
                                                <span class="badge bg-light text-dark"><?php echo $inscription['classe_nom']; ?></span>
                                                <br><small class="text-muted"><?php echo ucfirst($inscription['niveau']); ?></small>
                                            </td>
                                            <td>
                                                <?php echo date('d/m/Y', strtotime($inscription['date_inscription'])); ?>
                                                <br><small class="text-muted"><?php echo date('H:i', strtotime($inscription['date_inscription'])); ?></small>
                                            </td>
                                            <td>
                                                <?php 
                                                $status_class = '';
                                                switch ($inscription['status']) {
                                                    case 'inscrit':
                                                        $status_class = 'bg-success';
                                                        break;
                                                    case 'transfere':
                                                        $status_class = 'bg-warning';
                                                        break;
                                                    case 'abandonne':
                                                        $status_class = 'bg-danger';
                                                        break;
                                                    default:
                                                        $status_class = 'bg-secondary';
                                                }
                                                ?>
                                                <span class="badge <?php echo $status_class; ?>"><?php echo $inscription['status_lisible']; ?></span>
                                            </td>
                                            <td>
                                                <?php if ($inscription['frais_inscription_paye'] > 0): ?>
                                                    <span class="text-success fw-bold"><?php echo formatMoney($inscription['frais_inscription_paye']); ?></span>
                                                <?php else: ?>
                                                    <span class="text-muted">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <div class="btn-group" role="group">
                                                    <a href="../students/view.php?id=<?php echo $inscription['eleve_id']; ?>" 
                                                       class="btn btn-sm btn-outline-primary" title="Voir l'élève">
                                                        <i class="mdi mdi-eye"></i>
                                                    </a>
                                                    <?php if ($inscription['status'] === 'inscrit'): ?>
                                                        <button type="button" class="btn btn-sm btn-outline-warning" 
                                                                onclick="changerStatut(<?php echo $inscription['id']; ?>, 'transfere')" 
                                                                title="Marquer comme transféré">
                                                            <i class="mdi mdi-account-arrow-right"></i>
                                                        </button>
                                                        <button type="button" class="btn btn-sm btn-outline-danger" 
                                                                onclick="changerStatut(<?php echo $inscription['id']; ?>, 'abandonne')" 
                                                                title="Marquer comme abandonné">
                                                            <i class="mdi mdi-account-remove"></i>
                                                        </button>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <!-- Export -->
                        <div class="mt-3">
                            <a href="?annee=<?php echo $annee_scolaire_id; ?>&status=<?php echo $status_filter; ?>&classe=<?php echo $classe_filter; ?>&search=<?php echo urlencode($search); ?>&export=1" 
                               class="btn btn-outline-success">
                                <i class="mdi mdi-file-excel me-1"></i>
                                Exporter en Excel
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function changerStatut(inscriptionId, nouveauStatut) {
    const statuts = {
        'transfere': 'transféré',
        'abandonne': 'abandonné'
    };
    
    if (confirm(`Êtes-vous sûr de vouloir marquer cet élève comme ${statuts[nouveauStatut]} ?`)) {
        // Ici vous pouvez ajouter une requête AJAX pour changer le statut
        // ou rediriger vers une page de traitement
        window.location.href = `change-status.php?id=${inscriptionId}&status=${nouveauStatut}`;
    }
}

// Auto-submit du formulaire lors du changement d'année
document.getElementById('annee').addEventListener('change', function() {
    this.form.submit();
});
</script>

<?php include '../../includes/footer.php'; ?>
