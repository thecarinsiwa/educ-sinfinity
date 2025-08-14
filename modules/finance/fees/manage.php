<?php
/**
 * Module de gestion financière - Gestion avancée des frais scolaires
 * Application de gestion scolaire - République Démocratique du Congo
 */

require_once '../../../config/config.php';
require_once '../../../config/database.php';
require_once '../../../includes/functions.php';

// Vérifier l'authentification et les permissions
requireLogin();
if (!checkPermission('finance')) {
    showMessage('error', 'Accès refusé à cette fonctionnalité.');
    redirectTo('index.php');
}

$page_title = 'Gestion avancée des frais scolaires';

// Obtenir l'année scolaire actuelle
$current_year = getCurrentAcademicYear();

// Paramètres de filtrage et pagination
$niveau_filter = sanitizeInput($_GET['niveau'] ?? '');
$type_filter = sanitizeInput($_GET['type'] ?? '');
$classe_filter = (int)($_GET['classe_id'] ?? 0);
$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = 20;
$offset = ($page - 1) * $per_page;

// Actions en masse
$action = sanitizeInput($_POST['action'] ?? '');
$selected_frais = $_POST['selected_frais'] ?? [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($action) && !empty($selected_frais)) {
    $database->beginTransaction();
    
    try {
        switch ($action) {
            case 'delete':
                $placeholders = str_repeat('?,', count($selected_frais) - 1) . '?';
                $database->execute(
                    "DELETE FROM frais_scolaires WHERE id IN ($placeholders)",
                    $selected_frais
                );
                showMessage('success', count($selected_frais) . ' frais supprimés avec succès.');
                break;
                
            case 'duplicate':
                foreach ($selected_frais as $frais_id) {
                    $frais = $database->query(
                        "SELECT * FROM frais_scolaires WHERE id = ?",
                        [$frais_id]
                    )->fetch();
                    
                    if ($frais) {
                        $database->execute(
                            "INSERT INTO frais_scolaires (classe_id, type_frais, libelle, montant, obligatoire, date_echeance, description, annee_scolaire_id) 
                             VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
                            [
                                $frais['classe_id'],
                                $frais['type_frais'],
                                $frais['libelle'] . ' (Copie)',
                                $frais['montant'],
                                $frais['obligatoire'],
                                $frais['date_echeance'],
                                $frais['description'],
                                $current_year['id']
                            ]
                        );
                    }
                }
                showMessage('success', count($selected_frais) . ' frais dupliqués avec succès.');
                break;
                
            case 'update_amount':
                $new_amount = (float)($_POST['new_amount'] ?? 0);
                if ($new_amount > 0) {
                    $placeholders = str_repeat('?,', count($selected_frais) - 1) . '?';
                    $params = array_merge([$new_amount], $selected_frais);
                    $database->execute(
                        "UPDATE frais_scolaires SET montant = ? WHERE id IN ($placeholders)",
                        $params
                    );
                    showMessage('success', 'Montant mis à jour pour ' . count($selected_frais) . ' frais.');
                }
                break;
                
            case 'update_deadline':
                $new_deadline = sanitizeInput($_POST['new_deadline'] ?? '');
                if (!empty($new_deadline) && isValidDate($new_deadline)) {
                    $placeholders = str_repeat('?,', count($selected_frais) - 1) . '?';
                    $params = array_merge([$new_deadline], $selected_frais);
                    $database->execute(
                        "UPDATE frais_scolaires SET date_echeance = ? WHERE id IN ($placeholders)",
                        $params
                    );
                    showMessage('success', 'Date d\'échéance mise à jour pour ' . count($selected_frais) . ' frais.');
                }
                break;
        }
        
        $database->commit();
    } catch (Exception $e) {
        $database->rollback();
        showMessage('error', 'Erreur lors de l\'exécution de l\'action : ' . $e->getMessage());
    }
}

// Construction de la requête pour récupérer les frais
$frais = [];
$total_frais = 0;

try {
    $table_check = $database->query("SHOW TABLES LIKE 'frais_scolaires'")->fetch();
    
    if ($table_check) {
        // Requête pour le total
        $count_sql = "SELECT COUNT(*) as total
                      FROM frais_scolaires f
                      JOIN classes c ON f.classe_id = c.id
                      WHERE f.annee_scolaire_id = ?";
        $count_params = [$current_year['id'] ?? 0];
        
        if (!empty($niveau_filter)) {
            $count_sql .= " AND c.niveau = ?";
            $count_params[] = $niveau_filter;
        }
        
        if (!empty($type_filter)) {
            $count_sql .= " AND f.type_frais = ?";
            $count_params[] = $type_filter;
        }
        
        if ($classe_filter > 0) {
            $count_sql .= " AND f.classe_id = ?";
            $count_params[] = $classe_filter;
        }
        
        $total_result = $database->query($count_sql, $count_params)->fetch();
        $total_frais = $total_result['total'];
        
        // Requête pour les données
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
        
        if ($classe_filter > 0) {
            $sql .= " AND f.classe_id = ?";
            $params[] = $classe_filter;
        }
        
        $sql .= " ORDER BY c.niveau, c.nom, f.type_frais, f.libelle LIMIT ? OFFSET ?";
        $params[] = $per_page;
        $params[] = $offset;
        
        $frais = $database->query($sql, $params)->fetchAll();
    }
} catch (Exception $e) {
    showMessage('error', 'Erreur lors de la récupération des données : ' . $e->getMessage());
}

// Récupérer les classes pour le filtre
$classes = $database->query(
    "SELECT id, nom, niveau FROM classes WHERE annee_scolaire_id = ? ORDER BY niveau, nom",
    [$current_year['id'] ?? 0]
)->fetchAll();

// Calculer le nombre total de pages
$total_pages = ceil($total_frais / $per_page);

include '../../../includes/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">
        <i class="fas fa-cogs me-2"></i>
        Gestion avancée des frais scolaires
    </h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <div class="btn-group me-2">
            <a href="index.php" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-1"></i>
                Retour
            </a>
        </div>
        <div class="btn-group me-2">
            <a href="add.php" class="btn btn-primary">
                <i class="fas fa-plus me-1"></i>
                Nouveau frais
            </a>
        </div>
        <div class="btn-group">
            <button type="button" class="btn btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">
                <i class="fas fa-download me-1"></i>
                Exporter
            </button>
            <ul class="dropdown-menu">
                <li><a class="dropdown-item" href="export.php?format=excel&niveau=<?php echo $niveau_filter; ?>&type=<?php echo $type_filter; ?>">
                    <i class="fas fa-file-excel me-2"></i>Excel
                </a></li>
                <li><a class="dropdown-item" href="export.php?format=pdf&niveau=<?php echo $niveau_filter; ?>&type=<?php echo $type_filter; ?>">
                    <i class="fas fa-file-pdf me-2"></i>PDF
                </a></li>
            </ul>
        </div>
    </div>
</div>

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
            <div class="col-md-3">
                <label for="niveau" class="form-label">Niveau</label>
                <select name="niveau" id="niveau" class="form-select">
                    <option value="">Tous les niveaux</option>
                    <option value="maternelle" <?php echo $niveau_filter === 'maternelle' ? 'selected' : ''; ?>>Maternelle</option>
                    <option value="primaire" <?php echo $niveau_filter === 'primaire' ? 'selected' : ''; ?>>Primaire</option>
                    <option value="secondaire" <?php echo $niveau_filter === 'secondaire' ? 'selected' : ''; ?>>Secondaire</option>
                </select>
            </div>
            <div class="col-md-3">
                <label for="type_filter" class="form-label">Type de frais</label>
                <select name="type" id="type_filter" class="form-select">
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
            <div class="col-md-3 d-flex align-items-end">
                <div class="d-grid gap-2 w-100">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search me-1"></i>
                        Filtrer
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Actions en masse -->
<form method="POST" id="bulk-actions-form">
    <div class="card mb-4">
        <div class="card-header">
            <div class="d-flex justify-content-between align-items-center">
                <h5 class="mb-0">
                    <i class="fas fa-list me-2"></i>
                    Liste des frais (<?php echo $total_frais; ?> résultats)
                </h5>
                <div class="btn-group" id="bulk-actions" style="display: none;">
                    <button type="button" class="btn btn-outline-danger dropdown-toggle" data-bs-toggle="dropdown">
                        <i class="fas fa-tools me-1"></i>
                        Actions en masse
                    </button>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="#" onclick="showBulkAction('delete')">
                            <i class="fas fa-trash me-2"></i>Supprimer
                        </a></li>
                        <li><a class="dropdown-item" href="#" onclick="showBulkAction('duplicate')">
                            <i class="fas fa-copy me-2"></i>Dupliquer
                        </a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="#" onclick="showBulkAction('update_amount')">
                            <i class="fas fa-dollar-sign me-2"></i>Modifier montant
                        </a></li>
                        <li><a class="dropdown-item" href="#" onclick="showBulkAction('update_deadline')">
                            <i class="fas fa-calendar me-2"></i>Modifier échéance
                        </a></li>
                    </ul>
                </div>
            </div>
        </div>
        <div class="card-body">
            <?php if (empty($frais)): ?>
                <div class="text-center py-4">
                    <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                    <h5 class="text-muted">Aucun frais trouvé</h5>
                    <p class="text-muted">Aucun frais ne correspond aux critères de recherche.</p>
                    <a href="add.php" class="btn btn-primary">
                        <i class="fas fa-plus me-1"></i>
                        Ajouter le premier frais
                    </a>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>
                                    <input type="checkbox" id="select-all" class="form-check-input">
                                </th>
                                <th>Classe</th>
                                <th>Type</th>
                                <th>Libellé</th>
                                <th>Montant</th>
                                <th>Échéance</th>
                                <th>Obligatoire</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($frais as $frais_item): ?>
                                <tr>
                                    <td>
                                        <input type="checkbox" name="selected_frais[]" value="<?php echo $frais_item['id']; ?>" class="form-check-input frais-checkbox">
                                    </td>
                                    <td>
                                        <span class="badge bg-<?php 
                                            echo $frais_item['niveau'] === 'maternelle' ? 'warning' : 
                                                ($frais_item['niveau'] === 'primaire' ? 'success' : 'primary'); 
                                        ?>">
                                            <?php echo htmlspecialchars($frais_item['classe_nom']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge bg-secondary">
                                            <?php echo ucfirst($frais_item['type_frais']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo htmlspecialchars($frais_item['libelle']); ?></td>
                                    <td>
                                        <strong class="text-success">
                                            <?php echo formatMoney($frais_item['montant']); ?>
                                        </strong>
                                    </td>
                                    <td>
                                        <?php if (!empty($frais_item['date_echeance'])): ?>
                                            <span class="badge bg-info">
                                                <?php echo formatDate($frais_item['date_echeance']); ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($frais_item['obligatoire']): ?>
                                            <span class="badge bg-success">Oui</span>
                                        <?php else: ?>
                                            <span class="badge bg-warning">Non</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <a href="view.php?id=<?php echo $frais_item['id']; ?>" class="btn btn-outline-info" title="Voir">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <a href="edit.php?id=<?php echo $frais_item['id']; ?>" class="btn btn-outline-primary" title="Modifier">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <a href="duplicate.php?id=<?php echo $frais_item['id']; ?>" class="btn btn-outline-secondary" title="Dupliquer">
                                                <i class="fas fa-copy"></i>
                                            </a>
                                            <a href="delete.php?id=<?php echo $frais_item['id']; ?>" class="btn btn-outline-danger" title="Supprimer" 
                                               onclick="return confirm('Êtes-vous sûr de vouloir supprimer ce frais ?')">
                                                <i class="fas fa-trash"></i>
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
                    <nav aria-label="Pagination des frais">
                        <ul class="pagination justify-content-center">
                            <?php if ($page > 1): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?page=<?php echo $page - 1; ?>&niveau=<?php echo $niveau_filter; ?>&type=<?php echo $type_filter; ?>&classe_id=<?php echo $classe_filter; ?>">
                                        <i class="fas fa-chevron-left"></i>
                                    </a>
                                </li>
                            <?php endif; ?>
                            
                            <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                                <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $i; ?>&niveau=<?php echo $niveau_filter; ?>&type=<?php echo $type_filter; ?>&classe_id=<?php echo $classe_filter; ?>">
                                        <?php echo $i; ?>
                                    </a>
                                </li>
                            <?php endfor; ?>
                            
                            <?php if ($page < $total_pages): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?page=<?php echo $page + 1; ?>&niveau=<?php echo $niveau_filter; ?>&type=<?php echo $type_filter; ?>&classe_id=<?php echo $classe_filter; ?>">
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
    
    <!-- Champs cachés pour les actions en masse -->
    <input type="hidden" name="action" id="bulk-action" value="">
    <input type="hidden" name="new_amount" id="new-amount" value="">
    <input type="hidden" name="new_deadline" id="new-deadline" value="">
</form>

<!-- Modals pour les actions en masse -->
<div class="modal fade" id="bulkActionModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="bulkActionModalTitle">Action en masse</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="bulkActionModalBody">
                <!-- Le contenu sera injecté dynamiquement -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                <button type="button" class="btn btn-primary" onclick="executeBulkAction()">Confirmer</button>
            </div>
        </div>
    </div>
</div>

<script>
// Gestion des cases à cocher
document.getElementById('select-all').addEventListener('change', function() {
    const checkboxes = document.querySelectorAll('.frais-checkbox');
    checkboxes.forEach(checkbox => {
        checkbox.checked = this.checked;
    });
    updateBulkActions();
});

document.querySelectorAll('.frais-checkbox').forEach(checkbox => {
    checkbox.addEventListener('change', updateBulkActions);
});

function updateBulkActions() {
    const checkedBoxes = document.querySelectorAll('.frais-checkbox:checked');
    const bulkActions = document.getElementById('bulk-actions');
    
    if (checkedBoxes.length > 0) {
        bulkActions.style.display = 'block';
    } else {
        bulkActions.style.display = 'none';
    }
}

function showBulkAction(action) {
    const modal = new bootstrap.Modal(document.getElementById('bulkActionModal'));
    const title = document.getElementById('bulkActionModalTitle');
    const body = document.getElementById('bulkActionModalBody');
    
    document.getElementById('bulk-action').value = action;
    
    switch (action) {
        case 'delete':
            title.textContent = 'Confirmer la suppression';
            body.innerHTML = `
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    Êtes-vous sûr de vouloir supprimer les frais sélectionnés ? Cette action est irréversible.
                </div>
            `;
            break;
            
        case 'duplicate':
            title.textContent = 'Confirmer la duplication';
            body.innerHTML = `
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i>
                    Les frais sélectionnés seront dupliqués avec le libellé "(Copie)" ajouté.
                </div>
            `;
            break;
            
        case 'update_amount':
            title.textContent = 'Modifier le montant';
            body.innerHTML = `
                <div class="mb-3">
                    <label for="amount-input" class="form-label">Nouveau montant (FC)</label>
                    <input type="number" class="form-control" id="amount-input" min="0" step="0.01" required>
                </div>
            `;
            break;
            
        case 'update_deadline':
            title.textContent = 'Modifier la date d\'échéance';
            body.innerHTML = `
                <div class="mb-3">
                    <label for="deadline-input" class="form-label">Nouvelle date d'échéance</label>
                    <input type="date" class="form-control" id="deadline-input" required>
                </div>
            `;
            break;
    }
    
    modal.show();
}

function executeBulkAction() {
    const action = document.getElementById('bulk-action').value;
    
    if (action === 'update_amount') {
        const amount = document.getElementById('amount-input').value;
        if (!amount || amount <= 0) {
            alert('Veuillez saisir un montant valide.');
            return;
        }
        document.getElementById('new-amount').value = amount;
    } else if (action === 'update_deadline') {
        const deadline = document.getElementById('deadline-input').value;
        if (!deadline) {
            alert('Veuillez saisir une date valide.');
            return;
        }
        document.getElementById('new-deadline').value = deadline;
    }
    
    document.getElementById('bulk-actions-form').submit();
}
</script>

<?php include '../../../includes/footer.php'; ?>
