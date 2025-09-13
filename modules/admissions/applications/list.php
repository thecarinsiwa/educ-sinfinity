<?php
/**
 * Liste des demandes d'admission
 * Application de gestion scolaire - République Démocratique du Congo
 */

require_once '../../../config/config.php';
require_once '../../../config/database.php';
require_once '../../../includes/functions.php';
require_once '../../../includes/permissions-pages.php';

// Vérifier l'authentification et les permissions
requireLogin();
requirePagePermissionFromDB('admissions', 'applications', 'read', '../../../dashboard.php');

$page_title = 'Liste des Demandes d\'Admission';

// Paramètres de filtrage et pagination
$status_filter = $_GET['status'] ?? '';
$classe_filter = $_GET['classe'] ?? '';
$search = $_GET['search'] ?? '';
$page = max(1, intval($_GET['page'] ?? 1));
$per_page = 20;
$offset = ($page - 1) * $per_page;

// Construire la requête avec filtres
$where_conditions = [];
$params = [];

if ($status_filter) {
    $where_conditions[] = "da.status = ?";
    $params[] = $status_filter;
}

if ($classe_filter) {
    $where_conditions[] = "da.classe_demandee_id = ?";
    $params[] = $classe_filter;
}

if ($search) {
    $where_conditions[] = "(da.nom_eleve LIKE ? OR da.prenom_eleve LIKE ? OR da.numero_demande LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Compter le total des demandes
$count_sql = "SELECT COUNT(*) as total FROM demandes_admission da $where_clause";
$total_demandes = $database->query($count_sql, $params)->fetch()['total'];
$total_pages = ceil($total_demandes / $per_page);

// Récupérer les demandes avec pagination
$sql = "SELECT da.*, 
               c.nom as classe_nom, c.niveau as classe_niveau,
               e.status as eleve_status, e.numero_eleve
        FROM demandes_admission da 
        LEFT JOIN classes c ON da.classe_demandee_id = c.id 
        LEFT JOIN eleves e ON da.eleve_cree_id = e.id
        $where_clause
        ORDER BY da.created_at DESC 
        LIMIT ? OFFSET ?";

$params[] = $per_page;
$params[] = $offset;
$demandes = $database->query($sql, $params)->fetchAll();

// Récupérer les classes pour le filtre
$classes = $database->query("SELECT id, nom, niveau FROM classes ORDER BY niveau, nom")->fetchAll();

// Récupérer les statuts disponibles
$statuts = $database->query("SELECT DISTINCT status FROM demandes_admission ORDER BY status")->fetchAll();

include '../../../includes/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">
        <i class="fas fa-list me-2"></i>
        Liste des Demandes d'Admission
    </h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <a href="../index.php" class="btn btn-outline-secondary me-2">
            <i class="fas fa-arrow-left me-1"></i>
            Retour
        </a>
        <a href="../students/add.php" class="btn btn-primary">
            <i class="fas fa-plus me-1"></i>
            Nouvelle Demande
        </a>
    </div>
</div>

<!-- Filtres et recherche -->
<div class="card mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-md-3">
                <label for="search" class="form-label">Recherche</label>
                <input type="text" class="form-control" id="search" name="search" 
                       value="<?php echo htmlspecialchars($search); ?>" 
                       placeholder="Nom, prénom ou numéro...">
            </div>
            
            <div class="col-md-2">
                <label for="status" class="form-label">Statut</label>
                <select class="form-select" id="status" name="status">
                    <option value="">Tous</option>
                    <?php foreach ($statuts as $statut): ?>
                        <option value="<?php echo $statut['status']; ?>" 
                                <?php echo $status_filter === $statut['status'] ? 'selected' : ''; ?>>
                            <?php
                            $statut_text = '';
                            switch ($statut['status']) {
                                case 'en_cours_traitement':
                                    $statut_text = 'En cours';
                                    break;
                                case 'acceptee':
                                    $statut_text = 'Acceptée';
                                    break;
                                case 'refusee':
                                    $statut_text = 'Refusée';
                                    break;
                                case 'en_attente':
                                    $statut_text = 'En attente';
                                    break;
                                default:
                                    $statut_text = ucfirst($statut['status']);
                            }
                            echo $statut_text;
                            ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="col-md-3">
                <label for="classe" class="form-label">Classe</label>
                <select class="form-select" id="classe" name="classe">
                    <option value="">Toutes</option>
                    <?php foreach ($classes as $classe): ?>
                        <option value="<?php echo $classe['id']; ?>" 
                                <?php echo $classe_filter == $classe['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($classe['nom'] . ' (' . $classe['niveau'] . ')'); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="col-md-2">
                <label class="form-label">&nbsp;</label>
                <div class="d-grid">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search me-1"></i>
                        Filtrer
                    </button>
                </div>
            </div>
            
            <div class="col-md-2">
                <label class="form-label">&nbsp;</label>
                <div class="d-grid">
                    <a href="list.php" class="btn btn-outline-secondary">
                        <i class="fas fa-times me-1"></i>
                        Réinitialiser
                    </a>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Statistiques des filtres -->
<?php if ($status_filter || $classe_filter || $search): ?>
<div class="alert alert-info mb-4">
    <i class="fas fa-filter me-2"></i>
    <strong>Filtres actifs :</strong>
    <?php
    $filtres = [];
    if ($status_filter) {
        $statut_text = '';
        switch ($status_filter) {
            case 'en_cours_traitement': $statut_text = 'En cours'; break;
            case 'acceptee': $statut_text = 'Acceptée'; break;
            case 'refusee': $statut_text = 'Refusée'; break;
            case 'en_attente': $statut_text = 'En attente'; break;
            default: $statut_text = ucfirst($status_filter);
        }
        $filtres[] = "Statut : $statut_text";
    }
    if ($classe_filter) {
        foreach ($classes as $classe) {
            if ($classe['id'] == $classe_filter) {
                $filtres[] = "Classe : " . $classe['nom'] . " (" . $classe['niveau'] . ")";
                break;
            }
        }
    }
    if ($search) {
        $filtres[] = "Recherche : \"$search\"";
    }
    echo implode(', ', $filtres);
    ?>
    - <strong><?php echo $total_demandes; ?> demande(s) trouvée(s)</strong>
</div>
<?php endif; ?>

<!-- Liste des demandes -->
<div class="card">
    <div class="card-header">
        <div class="d-flex justify-content-between align-items-center">
            <h5 class="mb-0">
                <i class="fas fa-table me-2"></i>
                Demandes d'Admission
            </h5>
            <div>
                <span class="badge bg-primary"><?php echo $total_demandes; ?> total</span>
                <?php if ($total_pages > 1): ?>
                    <span class="badge bg-info ms-2">Page <?php echo $page; ?> sur <?php echo $total_pages; ?></span>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <div class="card-body">
        <?php if (empty($demandes)): ?>
            <div class="text-center py-5">
                <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                <h5 class="text-muted">Aucune demande d'admission trouvée</h5>
                <p class="text-muted">
                    <?php if ($status_filter || $classe_filter || $search): ?>
                        Essayez de modifier vos critères de recherche.
                    <?php else: ?>
                        Créez la première demande d'admission.
                    <?php endif; ?>
                </p>
                <?php if (!($status_filter || $classe_filter || $search)): ?>
                    <a href="../students/add.php" class="btn btn-primary">
                        <i class="fas fa-plus me-1"></i>
                        Créer une demande
                    </a>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>N° Demande</th>
                            <th>Élève</th>
                            <th>Classe</th>
                            <th>Statut</th>
                            <th>Élève Créé</th>
                            <th>Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($demandes as $demande): ?>
                            <tr>
                                <td>
                                    <span class="badge bg-primary"><?php echo htmlspecialchars($demande['numero_demande']); ?></span>
                                </td>
                                <td>
                                    <strong><?php echo htmlspecialchars($demande['nom_eleve'] . ' ' . $demande['prenom_eleve']); ?></strong>
                                    <br>
                                    <small class="text-muted">
                                        <?php echo date('d/m/Y', strtotime($demande['date_naissance'])); ?>
                                        (<?php echo date('Y') - date('Y', strtotime($demande['date_naissance'])); ?> ans)
                                    </small>
                                </td>
                                <td>
                                    <?php if ($demande['classe_nom']): ?>
                                        <?php echo htmlspecialchars($demande['classe_nom']); ?>
                                        <br>
                                        <small class="text-muted"><?php echo htmlspecialchars($demande['classe_niveau']); ?></small>
                                    <?php else: ?>
                                        <span class="text-muted">Non spécifiée</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php
                                    $status_class = '';
                                    $status_text = '';
                                    switch ($demande['status']) {
                                        case 'en_cours_traitement':
                                            $status_class = 'bg-warning';
                                            $status_text = 'En cours';
                                            break;
                                        case 'acceptee':
                                            $status_class = 'bg-success';
                                            $status_text = 'Acceptée';
                                            break;
                                        case 'refusee':
                                            $status_class = 'bg-danger';
                                            $status_text = 'Refusée';
                                            break;
                                        case 'en_attente':
                                            $status_class = 'bg-info';
                                            $status_text = 'En attente';
                                            break;
                                        default:
                                            $status_class = 'bg-secondary';
                                            $status_text = ucfirst($demande['status']);
                                    }
                                    ?>
                                    <span class="badge <?php echo $status_class; ?>">
                                        <?php echo $status_text; ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($demande['eleve_cree_id']): ?>
                                        <span class="badge bg-success">
                                            <i class="fas fa-check me-1"></i>
                                            Créé
                                        </span>
                                        <br>
                                        <small class="text-muted">
                                            N° <?php echo htmlspecialchars($demande['numero_eleve']); ?>
                                        </small>
                                        <br>
                                        <small class="text-muted">
                                            <?php
                                            $eleve_status_text = '';
                                            switch ($demande['eleve_status']) {
                                                case 'non-evalué':
                                                    $eleve_status_text = 'Non évalué';
                                                    break;
                                                case 'actif':
                                                    $eleve_status_text = 'Actif';
                                                    break;
                                                default:
                                                    $eleve_status_text = ucfirst($demande['eleve_status']);
                                            }
                                            echo $eleve_status_text;
                                            ?>
                                        </small>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">
                                            <i class="fas fa-times me-1"></i>
                                            Non créé
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php echo date('d/m/Y', strtotime($demande['created_at'])); ?>
                                    <br>
                                    <small class="text-muted">
                                        <?php echo date('H:i', strtotime($demande['created_at'])); ?>
                                    </small>
                                </td>
                                <td>
                                    <div class="btn-group" role="group">
                                        <a href="view.php?id=<?php echo $demande['id']; ?>" 
                                           class="btn btn-sm btn-outline-primary" 
                                           title="Voir les détails">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="edit.php?id=<?php echo $demande['id']; ?>" 
                                           class="btn btn-sm btn-outline-warning" 
                                           title="Modifier">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <?php if ($demande['status'] === 'en_cours_traitement'): ?>
                                            <a href="evaluate.php?id=<?php echo $demande['id']; ?>" 
                                               class="btn btn-sm btn-outline-success" 
                                               title="Évaluer">
                                                <i class="fas fa-clipboard-check"></i>
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
            <nav aria-label="Pagination des demandes d'admission">
                <ul class="pagination justify-content-center">
                    <?php if ($page > 1): ?>
                        <li class="page-item">
                            <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>">
                                <i class="fas fa-chevron-left"></i>
                                Précédent
                            </a>
                        </li>
                    <?php endif; ?>
                    
                    <?php
                    $start_page = max(1, $page - 2);
                    $end_page = min($total_pages, $page + 2);
                    
                    if ($start_page > 1): ?>
                        <li class="page-item">
                            <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => 1])); ?>">1</a>
                        </li>
                        <?php if ($start_page > 2): ?>
                            <li class="page-item disabled"><span class="page-link">...</span></li>
                        <?php endif; ?>
                    <?php endif; ?>
                    
                    <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                        <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                            <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>">
                                <?php echo $i; ?>
                            </a>
                        </li>
                    <?php endfor; ?>
                    
                    <?php if ($end_page < $total_pages): ?>
                        <?php if ($end_page < $total_pages - 1): ?>
                            <li class="page-item disabled"><span class="page-link">...</span></li>
                        <?php endif; ?>
                        <li class="page-item">
                            <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $total_pages])); ?>">
                                <?php echo $total_pages; ?>
                            </a>
                        </li>
                    <?php endif; ?>
                    
                    <?php if ($page < $total_pages): ?>
                        <li class="page-item">
                            <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>">
                                Suivant
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

<script>
// Auto-submit du formulaire lors du changement des filtres
document.getElementById('status').addEventListener('change', function() {
    this.form.submit();
});

document.getElementById('classe').addEventListener('change', function() {
    this.form.submit();
});
</script>

<?php include '../../../includes/footer.php'; ?>

