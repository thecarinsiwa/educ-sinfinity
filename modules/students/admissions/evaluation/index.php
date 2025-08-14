<?php
/**
 * Module d'évaluation des candidatures
 * Application de gestion scolaire - République Démocratique du Congo
 */

require_once '../../../../config/config.php';
require_once '../../../../config/database.php';
require_once '../../../../includes/functions.php';

// Vérifier l'authentification et les permissions
requireLogin();
if (!checkPermission('students') && !checkPermission('students_view')) {
    showMessage('error', 'Accès refusé à ce module.');
    redirectTo('../index.php');
}

// Traitement des actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && checkPermission('students')) {
    try {
        $action = $_POST['action'] ?? '';
        
        switch ($action) {
            case 'evaluate':
                $candidature_id = intval($_POST['candidature_id']);
                $note_evaluation = floatval($_POST['note_evaluation']);
                $commentaire_evaluation = trim($_POST['commentaire_evaluation']);
                $recommandation = $_POST['recommandation'] ?? '';
                
                // Mettre à jour l'évaluation
                $database->execute(
                    "UPDATE demandes_admission 
                     SET note_evaluation = ?, commentaire_evaluation = ?, recommandation = ?,
                         evalue_par = ?, date_evaluation = NOW(), updated_at = NOW()
                     WHERE id = ?",
                    [$note_evaluation, $commentaire_evaluation, $recommandation, $_SESSION['user_id'], $candidature_id]
                );
                
                showMessage('success', 'Évaluation enregistrée avec succès.');
                break;
                
            case 'bulk_evaluate':
                $candidatures = $_POST['candidatures'] ?? [];
                $bulk_recommandation = $_POST['bulk_recommandation'] ?? '';
                
                foreach ($candidatures as $candidature_id) {
                    $database->execute(
                        "UPDATE demandes_admission 
                         SET recommandation = ?, evalue_par = ?, date_evaluation = NOW(), updated_at = NOW()
                         WHERE id = ?",
                        [$bulk_recommandation, $_SESSION['user_id'], intval($candidature_id)]
                    );
                }
                
                showMessage('success', count($candidatures) . ' candidatures évaluées.');
                break;
        }
    } catch (Exception $e) {
        showMessage('error', 'Erreur lors de l\'évaluation : ' . $e->getMessage());
    }
}

// Paramètres de pagination et filtres
$page = intval($_GET['page'] ?? 1);
$per_page = 20;
$offset = ($page - 1) * $per_page;

$status_filter = $_GET['status'] ?? '';
$recommandation_filter = $_GET['recommandation'] ?? '';
$search = trim($_GET['search'] ?? '');

// Construction de la requête
$where_conditions = ["1=1"];
$params = [];

if ($status_filter) {
    $where_conditions[] = "da.status = ?";
    $params[] = $status_filter;
}

if ($recommandation_filter) {
    $where_conditions[] = "da.recommandation = ?";
    $params[] = $recommandation_filter;
}

if ($search) {
    $where_conditions[] = "(da.nom_eleve LIKE ? OR da.prenom_eleve LIKE ? OR da.numero_demande LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

$where_clause = implode(' AND ', $where_conditions);

// Récupérer les candidatures à évaluer
try {
    $candidatures = $database->query(
        "SELECT da.*, c.nom as classe_demandee, c.niveau, c.section,
                u.username as evalue_par_nom,
                DATEDIFF(NOW(), da.created_at) as jours_depuis_demande,
                CASE 
                    WHEN da.note_evaluation IS NOT NULL THEN 'Évaluée'
                    ELSE 'En attente'
                END as statut_evaluation
         FROM demandes_admission da
         LEFT JOIN classes c ON da.classe_demandee_id = c.id
         LEFT JOIN users u ON da.evalue_par = u.id
         WHERE $where_clause
         ORDER BY 
            CASE da.priorite 
                WHEN 'tres_urgente' THEN 1 
                WHEN 'urgente' THEN 2 
                WHEN 'normale' THEN 3 
            END,
            da.created_at DESC
         LIMIT $per_page OFFSET $offset",
        $params
    )->fetchAll();

    // Compter le total pour la pagination
    $total_candidatures = $database->query(
        "SELECT COUNT(*) as total FROM demandes_admission da WHERE $where_clause",
        $params
    )->fetch()['total'];

} catch (Exception $e) {
    $candidatures = [];
    $total_candidatures = 0;
    showMessage('error', 'Erreur lors du chargement : ' . $e->getMessage());
}

// Statistiques d'évaluation
try {
    $stats = $database->query(
        "SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN note_evaluation IS NOT NULL THEN 1 ELSE 0 END) as evaluees,
            SUM(CASE WHEN recommandation = 'accepter' THEN 1 ELSE 0 END) as recommandees_accepter,
            SUM(CASE WHEN recommandation = 'refuser' THEN 1 ELSE 0 END) as recommandees_refuser,
            AVG(note_evaluation) as note_moyenne
         FROM demandes_admission"
    )->fetch();
} catch (Exception $e) {
    $stats = [
        'total' => 0,
        'evaluees' => 0,
        'recommandees_accepter' => 0,
        'recommandees_refuser' => 0,
        'note_moyenne' => 0
    ];
}

$total_pages = ceil($total_candidatures / $per_page);

$page_title = "Évaluation des Candidatures";
include '../../../../includes/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">
        <i class="fas fa-clipboard-check me-2"></i>
        Évaluation des Candidatures
    </h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <div class="btn-group me-2">
            <a href="../index.php" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-1"></i>
                Retour aux Admissions
            </a>
        </div>
        <?php if (checkPermission('students')): ?>
            <div class="btn-group me-2">
                <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#bulkEvaluationModal">
                    <i class="fas fa-tasks me-1"></i>
                    Évaluation en lot
                </button>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Statistiques -->
<div class="row mb-4">
    <div class="col-md-3">
        <div class="card text-center">
            <div class="card-body">
                <i class="fas fa-file-alt fa-2x text-primary mb-2"></i>
                <h5 class="card-title"><?php echo number_format($stats['total']); ?></h5>
                <p class="card-text text-muted">Total candidatures</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-center">
            <div class="card-body">
                <i class="fas fa-check-circle fa-2x text-success mb-2"></i>
                <h5 class="card-title"><?php echo number_format($stats['evaluees']); ?></h5>
                <p class="card-text text-muted">Évaluées</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-center">
            <div class="card-body">
                <i class="fas fa-thumbs-up fa-2x text-info mb-2"></i>
                <h5 class="card-title"><?php echo number_format($stats['recommandees_accepter']); ?></h5>
                <p class="card-text text-muted">Recommandées</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-center">
            <div class="card-body">
                <i class="fas fa-star fa-2x text-warning mb-2"></i>
                <h5 class="card-title"><?php echo number_format($stats['note_moyenne'] !== null ? $stats['note_moyenne'] : 0.0, 1); ?>/20</h5>
                <p class="card-text text-muted">Note moyenne</p>
            </div>
        </div>
    </div>
</div>

<!-- Filtres -->
<div class="card mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-md-3">
                <label for="status" class="form-label">Statut</label>
                <select class="form-select" id="status" name="status">
                    <option value="">Tous les statuts</option>
                    <option value="en_attente" <?php echo $status_filter === 'en_attente' ? 'selected' : ''; ?>>En attente</option>
                    <option value="en_cours_traitement" <?php echo $status_filter === 'en_cours_traitement' ? 'selected' : ''; ?>>En cours</option>
                    <option value="acceptee" <?php echo $status_filter === 'acceptee' ? 'selected' : ''; ?>>Acceptée</option>
                    <option value="refusee" <?php echo $status_filter === 'refusee' ? 'selected' : ''; ?>>Refusée</option>
                </select>
            </div>
            <div class="col-md-3">
                <label for="recommandation" class="form-label">Recommandation</label>
                <select class="form-select" id="recommandation" name="recommandation">
                    <option value="">Toutes</option>
                    <option value="accepter" <?php echo $recommandation_filter === 'accepter' ? 'selected' : ''; ?>>Accepter</option>
                    <option value="refuser" <?php echo $recommandation_filter === 'refuser' ? 'selected' : ''; ?>>Refuser</option>
                    <option value="attendre" <?php echo $recommandation_filter === 'attendre' ? 'selected' : ''; ?>>Attendre</option>
                </select>
            </div>
            <div class="col-md-4">
                <label for="search" class="form-label">Recherche</label>
                <input type="text" class="form-control" id="search" name="search" 
                       value="<?php echo htmlspecialchars($search); ?>" 
                       placeholder="Nom, prénom ou numéro...">
            </div>
            <div class="col-md-2">
                <label class="form-label">&nbsp;</label>
                <button type="submit" class="btn btn-primary d-block w-100">
                    <i class="fas fa-search me-1"></i>
                    Filtrer
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Liste des candidatures -->
<div class="card">
    <div class="card-header">
        <h5 class="mb-0">
            <i class="fas fa-list me-2"></i>
            Candidatures à évaluer (<?php echo number_format($total_candidatures); ?>)
        </h5>
    </div>
    <div class="card-body">
        <?php if (empty($candidatures)): ?>
            <div class="text-center py-4">
                <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                <h5 class="text-muted">Aucune candidature trouvée</h5>
                <p class="text-muted">Aucune candidature ne correspond aux critères sélectionnés.</p>
            </div>
        <?php else: ?>
            <form method="POST" id="bulkForm">
                <input type="hidden" name="action" value="bulk_evaluate">

                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead class="table-light">
                            <tr>
                                <?php if (checkPermission('students')): ?>
                                    <th width="40">
                                        <input type="checkbox" id="selectAll" class="form-check-input">
                                    </th>
                                <?php endif; ?>
                                <th>Candidat</th>
                                <th>Classe demandée</th>
                                <th>Priorité</th>
                                <th>Statut</th>
                                <th>Évaluation</th>
                                <th>Note</th>
                                <th>Recommandation</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($candidatures as $candidature): ?>
                                <tr>
                                    <?php if (checkPermission('students')): ?>
                                        <td>
                                            <input type="checkbox" name="candidatures[]"
                                                   value="<?php echo $candidature['id']; ?>"
                                                   class="form-check-input candidature-checkbox">
                                        </td>
                                    <?php endif; ?>
                                    <td>
                                        <div>
                                            <strong><?php echo htmlspecialchars($candidature['nom_eleve'] . ' ' . $candidature['prenom_eleve']); ?></strong>
                                            <br>
                                            <small class="text-muted"><?php echo htmlspecialchars($candidature['numero_demande']); ?></small>
                                        </div>
                                    </td>
                                    <td>
                                        <?php echo htmlspecialchars($candidature['classe_demandee'] . ' - ' . $candidature['niveau']); ?>
                                    </td>
                                    <td>
                                        <?php
                                        $priorite_classes = [
                                            'normale' => 'secondary',
                                            'urgente' => 'warning',
                                            'tres_urgente' => 'danger'
                                        ];
                                        $priorite_names = [
                                            'normale' => 'Normale',
                                            'urgente' => 'Urgente',
                                            'tres_urgente' => 'Très urgente'
                                        ];
                                        $priorite_class = $priorite_classes[$candidature['priorite']] ?? 'secondary';
                                        $priorite_name = $priorite_names[$candidature['priorite']] ?? $candidature['priorite'];
                                        ?>
                                        <span class="badge bg-<?php echo $priorite_class; ?>">
                                            <?php echo $priorite_name; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php
                                        $status_classes = [
                                            'en_attente' => 'warning',
                                            'acceptee' => 'success',
                                            'refusee' => 'danger',
                                            'en_cours_traitement' => 'info'
                                        ];
                                        $status_names = [
                                            'en_attente' => 'En attente',
                                            'acceptee' => 'Acceptée',
                                            'refusee' => 'Refusée',
                                            'en_cours_traitement' => 'En cours'
                                        ];
                                        $status_class = $status_classes[$candidature['status']] ?? 'secondary';
                                        $status_name = $status_names[$candidature['status']] ?? $candidature['status'];
                                        ?>
                                        <span class="badge bg-<?php echo $status_class; ?>">
                                            <?php echo $status_name; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($candidature['note_evaluation']): ?>
                                            <span class="badge bg-success">Évaluée</span>
                                            <?php if ($candidature['date_evaluation']): ?>
                                                <br><small class="text-muted"><?php echo formatDate($candidature['date_evaluation']); ?></small>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="badge bg-warning">En attente</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($candidature['note_evaluation']): ?>
                                            <strong><?php echo number_format($candidature['note_evaluation'], 1); ?>/20</strong>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($candidature['recommandation']): ?>
                                            <?php
                                            $recommandation_classes = [
                                                'accepter' => 'success',
                                                'refuser' => 'danger',
                                                'attendre' => 'warning'
                                            ];
                                            $recommandation_names = [
                                                'accepter' => 'Accepter',
                                                'refuser' => 'Refuser',
                                                'attendre' => 'Attendre'
                                            ];
                                            $rec_class = $recommandation_classes[$candidature['recommandation']] ?? 'secondary';
                                            $rec_name = $recommandation_names[$candidature['recommandation']] ?? $candidature['recommandation'];
                                            ?>
                                            <span class="badge bg-<?php echo $rec_class; ?>">
                                                <?php echo $rec_name; ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <a href="../applications/view.php?id=<?php echo $candidature['id']; ?>"
                                               class="btn btn-outline-info" title="Voir les détails">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <?php if (checkPermission('students')): ?>
                                                <button type="button" class="btn btn-outline-primary"
                                                        onclick="openEvaluationModal(<?php echo $candidature['id']; ?>)"
                                                        title="Évaluer">
                                                    <i class="fas fa-clipboard-check"></i>
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </form>
        <?php endif; ?>
    </div>
</div>

<!-- Pagination -->
<?php if ($total_pages > 1): ?>
<nav aria-label="Navigation des pages" class="mt-4">
    <ul class="pagination justify-content-center">
        <?php if ($page > 1): ?>
            <li class="page-item">
                <a class="page-link" href="?page=<?php echo $page - 1; ?>&<?php echo http_build_query($_GET); ?>">
                    <i class="fas fa-chevron-left"></i>
                </a>
            </li>
        <?php endif; ?>

        <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
            <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                <a class="page-link" href="?page=<?php echo $i; ?>&<?php echo http_build_query($_GET); ?>">
                    <?php echo $i; ?>
                </a>
            </li>
        <?php endfor; ?>

        <?php if ($page < $total_pages): ?>
            <li class="page-item">
                <a class="page-link" href="?page=<?php echo $page + 1; ?>&<?php echo http_build_query($_GET); ?>">
                    <i class="fas fa-chevron-right"></i>
                </a>
            </li>
        <?php endif; ?>
    </ul>
</nav>
<?php endif; ?>

<!-- Modal d'évaluation individuelle -->
<div class="modal fade" id="evaluationModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-clipboard-check me-2"></i>
                    Évaluation de la candidature
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" id="evaluationForm">
                <input type="hidden" name="action" value="evaluate">
                <input type="hidden" name="candidature_id" id="modal_candidature_id">

                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="note_evaluation" class="form-label">
                                    <i class="fas fa-star me-1"></i>
                                    Note d'évaluation (/20) *
                                </label>
                                <input type="number" class="form-control" id="note_evaluation"
                                       name="note_evaluation" min="0" max="20" step="0.5" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="recommandation" class="form-label">
                                    <i class="fas fa-thumbs-up me-1"></i>
                                    Recommandation *
                                </label>
                                <select class="form-select" id="recommandation" name="recommandation" required>
                                    <option value="">-- Sélectionner --</option>
                                    <option value="accepter">Accepter</option>
                                    <option value="refuser">Refuser</option>
                                    <option value="attendre">Attendre</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="commentaire_evaluation" class="form-label">
                            <i class="fas fa-comment me-1"></i>
                            Commentaire d'évaluation
                        </label>
                        <textarea class="form-control" id="commentaire_evaluation"
                                  name="commentaire_evaluation" rows="4"
                                  placeholder="Observations, points forts, points faibles, justification de la recommandation..."></textarea>
                    </div>

                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        <strong>Critères d'évaluation suggérés :</strong>
                        <ul class="mb-0 mt-2">
                            <li>Dossier scolaire précédent (0-5 points)</li>
                            <li>Motivation et projet scolaire (0-5 points)</li>
                            <li>Capacité d'adaptation (0-5 points)</li>
                            <li>Situation familiale et sociale (0-5 points)</li>
                        </ul>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times me-1"></i>
                        Annuler
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-1"></i>
                        Enregistrer l'évaluation
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal d'évaluation en lot -->
<div class="modal fade" id="bulkEvaluationModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-tasks me-2"></i>
                    Évaluation en lot
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    Sélectionnez d'abord les candidatures à évaluer dans la liste, puis choisissez une recommandation commune.
                </div>

                <div class="mb-3">
                    <label for="bulk_recommandation" class="form-label">
                        <i class="fas fa-thumbs-up me-1"></i>
                        Recommandation commune *
                    </label>
                    <select class="form-select" id="bulk_recommandation" name="bulk_recommandation" required>
                        <option value="">-- Sélectionner --</option>
                        <option value="accepter">Accepter toutes</option>
                        <option value="refuser">Refuser toutes</option>
                        <option value="attendre">Mettre en attente</option>
                    </select>
                </div>

                <div id="selectedCount" class="text-muted">
                    Aucune candidature sélectionnée
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="fas fa-times me-1"></i>
                    Annuler
                </button>
                <button type="button" class="btn btn-success" onclick="submitBulkEvaluation()">
                    <i class="fas fa-check me-1"></i>
                    Appliquer la recommandation
                </button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Gestion de la sélection multiple
    const selectAllCheckbox = document.getElementById('selectAll');
    const candidatureCheckboxes = document.querySelectorAll('.candidature-checkbox');

    if (selectAllCheckbox) {
        selectAllCheckbox.addEventListener('change', function() {
            candidatureCheckboxes.forEach(checkbox => {
                checkbox.checked = this.checked;
            });
            updateSelectedCount();
        });
    }

    candidatureCheckboxes.forEach(checkbox => {
        checkbox.addEventListener('change', updateSelectedCount);
    });

    function updateSelectedCount() {
        const selectedCount = document.querySelectorAll('.candidature-checkbox:checked').length;
        const countElement = document.getElementById('selectedCount');
        if (countElement) {
            countElement.textContent = selectedCount > 0
                ? `${selectedCount} candidature(s) sélectionnée(s)`
                : 'Aucune candidature sélectionnée';
        }
    }

    // Auto-submit du formulaire de recherche
    let searchTimeout;
    const searchInput = document.getElementById('search');
    if (searchInput) {
        searchInput.addEventListener('input', function() {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                this.form.submit();
            }, 500);
        });
    }
});

function openEvaluationModal(candidatureId) {
    document.getElementById('modal_candidature_id').value = candidatureId;

    // Charger les données existantes si disponibles
    fetch(`get-evaluation.php?id=${candidatureId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                document.getElementById('note_evaluation').value = data.note_evaluation || '';
                document.getElementById('recommandation').value = data.recommandation || '';
                document.getElementById('commentaire_evaluation').value = data.commentaire_evaluation || '';
            }
        })
        .catch(error => console.error('Erreur:', error));

    new bootstrap.Modal(document.getElementById('evaluationModal')).show();
}

function submitBulkEvaluation() {
    const selectedCheckboxes = document.querySelectorAll('.candidature-checkbox:checked');
    const recommandation = document.getElementById('bulk_recommandation').value;

    if (selectedCheckboxes.length === 0) {
        alert('Veuillez sélectionner au moins une candidature.');
        return;
    }

    if (!recommandation) {
        alert('Veuillez choisir une recommandation.');
        return;
    }

    if (confirm(`Êtes-vous sûr de vouloir appliquer la recommandation "${recommandation}" à ${selectedCheckboxes.length} candidature(s) ?`)) {
        // Créer un formulaire dynamique
        const form = document.createElement('form');
        form.method = 'POST';
        form.style.display = 'none';

        // Action
        const actionInput = document.createElement('input');
        actionInput.type = 'hidden';
        actionInput.name = 'action';
        actionInput.value = 'bulk_evaluate';
        form.appendChild(actionInput);

        // Recommandation
        const recInput = document.createElement('input');
        recInput.type = 'hidden';
        recInput.name = 'bulk_recommandation';
        recInput.value = recommandation;
        form.appendChild(recInput);

        // Candidatures sélectionnées
        selectedCheckboxes.forEach(checkbox => {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'candidatures[]';
            input.value = checkbox.value;
            form.appendChild(input);
        });

        document.body.appendChild(form);
        form.submit();
    }
}
</script>

<?php include '../../../../includes/footer.php'; ?>
