<?php
/**
 * Module Bibliothèque - Gestion des catégories de livres
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

// Traitement des actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $action = $_POST['action'] ?? '';
        
        switch ($action) {
            case 'add_category':
                $nom = trim($_POST['nom'] ?? '');
                $description = trim($_POST['description'] ?? '');
                $couleur = $_POST['couleur'] ?? '#007bff';
                
                if (empty($nom)) {
                    throw new Exception('Le nom de la catégorie est obligatoire.');
                }
                
                // Vérifier si la catégorie existe déjà
                $existing = $database->query(
                    "SELECT id FROM categories_livres WHERE nom = ?",
                    [$nom]
                )->fetch();
                
                if ($existing) {
                    throw new Exception('Une catégorie avec ce nom existe déjà.');
                }
                
                $database->execute(
                    "INSERT INTO categories_livres (nom, description, couleur, created_at) VALUES (?, ?, ?, NOW())",
                    [$nom, $description, $couleur]
                );
                
                showMessage('success', 'Catégorie ajoutée avec succès.');
                break;
                
            case 'edit_category':
                $id = intval($_POST['id']);
                $nom = trim($_POST['nom'] ?? '');
                $description = trim($_POST['description'] ?? '');
                $couleur = $_POST['couleur'] ?? '#007bff';
                
                if (empty($nom)) {
                    throw new Exception('Le nom de la catégorie est obligatoire.');
                }
                
                // Vérifier si une autre catégorie a le même nom
                $existing = $database->query(
                    "SELECT id FROM categories_livres WHERE nom = ? AND id != ?",
                    [$nom, $id]
                )->fetch();
                
                if ($existing) {
                    throw new Exception('Une autre catégorie avec ce nom existe déjà.');
                }
                
                $database->execute(
                    "UPDATE categories_livres SET nom = ?, description = ?, couleur = ?, updated_at = NOW() WHERE id = ?",
                    [$nom, $description, $couleur, $id]
                );
                
                showMessage('success', 'Catégorie modifiée avec succès.');
                break;
                
            case 'delete_category':
                $id = intval($_POST['id']);
                
                // Vérifier s'il y a des livres dans cette catégorie
                $books_count = $database->query(
                    "SELECT COUNT(*) as count FROM livres WHERE categorie_id = ?",
                    [$id]
                )->fetch()['count'];
                
                if ($books_count > 0) {
                    throw new Exception("Impossible de supprimer cette catégorie car elle contient $books_count livre(s).");
                }
                
                $database->execute("DELETE FROM categories_livres WHERE id = ?", [$id]);
                
                showMessage('success', 'Catégorie supprimée avec succès.');
                break;
        }
    } catch (Exception $e) {
        showMessage('error', 'Erreur : ' . $e->getMessage());
    }
}

// Récupérer les catégories avec le nombre de livres
try {
    $categories = $database->query(
        "SELECT cl.*, COUNT(l.id) as nb_livres
         FROM categories_livres cl
         LEFT JOIN livres l ON cl.id = l.categorie_id
         GROUP BY cl.id
         ORDER BY cl.nom"
    )->fetchAll();
} catch (Exception $e) {
    $categories = [];
    showMessage('error', 'Erreur lors du chargement : ' . $e->getMessage());
}

// Statistiques
try {
    $stats = $database->query(
        "SELECT 
            COUNT(*) as total_categories,
            (SELECT COUNT(*) FROM livres WHERE categorie_id IS NOT NULL) as livres_categorises,
            (SELECT COUNT(*) FROM livres WHERE categorie_id IS NULL) as livres_non_categorises
         FROM categories_livres"
    )->fetch();
} catch (Exception $e) {
    $stats = [
        'total_categories' => 0,
        'livres_categorises' => 0,
        'livres_non_categorises' => 0
    ];
}

$page_title = "Gestion des Catégories";
include '../../../includes/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">
        <i class="fas fa-tags me-2"></i>
        Gestion des Catégories
    </h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <div class="btn-group me-2">
            <a href="index.php" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-1"></i>
                Retour au catalogue
            </a>
        </div>
        <div class="btn-group me-2">
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addCategoryModal">
                <i class="fas fa-plus me-1"></i>
                Nouvelle catégorie
            </button>
        </div>
    </div>
</div>

<!-- Statistiques -->
<div class="row mb-4">
    <div class="col-md-4">
        <div class="card text-center">
            <div class="card-body">
                <i class="fas fa-tags fa-2x text-primary mb-2"></i>
                <h5 class="card-title"><?php echo number_format($stats['total_categories'] ?? 0); ?></h5>
                <p class="card-text text-muted">Total catégories</p>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card text-center">
            <div class="card-body">
                <i class="fas fa-check-circle fa-2x text-success mb-2"></i>
                <h5 class="card-title"><?php echo number_format($stats['livres_categorises'] ?? 0); ?></h5>
                <p class="card-text text-muted">Livres catégorisés</p>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card text-center">
            <div class="card-body">
                <i class="fas fa-exclamation-triangle fa-2x text-warning mb-2"></i>
                <h5 class="card-title"><?php echo number_format($stats['livres_non_categorises'] ?? 0); ?></h5>
                <p class="card-text text-muted">Livres non catégorisés</p>
            </div>
        </div>
    </div>
</div>

<!-- Liste des catégories -->
<div class="card">
    <div class="card-header">
        <h5 class="mb-0">
            <i class="fas fa-list me-2"></i>
            Liste des catégories
        </h5>
    </div>
    <div class="card-body">
        <?php if (empty($categories)): ?>
            <div class="text-center py-4">
                <i class="fas fa-tags fa-3x text-muted mb-3"></i>
                <h5 class="text-muted">Aucune catégorie trouvée</h5>
                <p class="text-muted">Commencez par créer votre première catégorie.</p>
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addCategoryModal">
                    <i class="fas fa-plus me-1"></i>
                    Créer la première catégorie
                </button>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead class="table-light">
                        <tr>
                            <th>Couleur</th>
                            <th>Nom</th>
                            <th>Description</th>
                            <th>Nombre de livres</th>
                            <th>Date de création</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($categories as $categorie): ?>
                            <tr>
                                <td>
                                    <span class="badge" style="background-color: <?php echo htmlspecialchars($categorie['couleur']); ?>; width: 30px; height: 20px;">
                                        &nbsp;
                                    </span>
                                </td>
                                <td>
                                    <strong><?php echo htmlspecialchars($categorie['nom']); ?></strong>
                                </td>
                                <td>
                                    <?php if ($categorie['description']): ?>
                                        <?php echo htmlspecialchars($categorie['description']); ?>
                                    <?php else: ?>
                                        <span class="text-muted">Aucune description</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge bg-primary"><?php echo number_format($categorie['nb_livres'] ?? 0); ?></span>
                                    <?php if ($categorie['nb_livres'] > 0): ?>
                                        <a href="index.php?category=<?php echo $categorie['id']; ?>" class="btn btn-sm btn-outline-info ms-2">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php echo formatDate($categorie['created_at']); ?>
                                </td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <button type="button" class="btn btn-outline-primary" 
                                                onclick="editCategory(<?php echo htmlspecialchars(json_encode($categorie)); ?>)" 
                                                title="Modifier">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <?php if ($categorie['nb_livres'] == 0): ?>
                                            <button type="button" class="btn btn-outline-danger" 
                                                    onclick="deleteCategory(<?php echo $categorie['id']; ?>, '<?php echo htmlspecialchars($categorie['nom']); ?>')" 
                                                    title="Supprimer">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        <?php else: ?>
                                            <button type="button" class="btn btn-outline-secondary" 
                                                    title="Impossible de supprimer (contient des livres)" disabled>
                                                <i class="fas fa-lock"></i>
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Modal d'ajout de catégorie -->
<div class="modal fade" id="addCategoryModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-plus me-2"></i>
                    Nouvelle catégorie
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" id="addCategoryForm">
                <input type="hidden" name="action" value="add_category">
                
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="add_nom" class="form-label">
                            Nom <span class="text-danger">*</span>
                        </label>
                        <input type="text" class="form-control" id="add_nom" name="nom" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="add_description" class="form-label">Description</label>
                        <textarea class="form-control" id="add_description" name="description" rows="3" 
                                  placeholder="Description de la catégorie..."></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label for="add_couleur" class="form-label">Couleur</label>
                        <div class="d-flex align-items-center">
                            <input type="color" class="form-control form-control-color me-2" 
                                   id="add_couleur" name="couleur" value="#007bff" style="width: 60px;">
                            <span id="add_couleur_preview" class="badge" style="background-color: #007bff;">
                                Aperçu
                            </span>
                        </div>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times me-1"></i>
                        Annuler
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-1"></i>
                        Ajouter
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal de modification de catégorie -->
<div class="modal fade" id="editCategoryModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-edit me-2"></i>
                    Modifier la catégorie
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" id="editCategoryForm">
                <input type="hidden" name="action" value="edit_category">
                <input type="hidden" name="id" id="edit_id">
                
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="edit_nom" class="form-label">
                            Nom <span class="text-danger">*</span>
                        </label>
                        <input type="text" class="form-control" id="edit_nom" name="nom" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_description" class="form-label">Description</label>
                        <textarea class="form-control" id="edit_description" name="description" rows="3" 
                                  placeholder="Description de la catégorie..."></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_couleur" class="form-label">Couleur</label>
                        <div class="d-flex align-items-center">
                            <input type="color" class="form-control form-control-color me-2" 
                                   id="edit_couleur" name="couleur" style="width: 60px;">
                            <span id="edit_couleur_preview" class="badge">
                                Aperçu
                            </span>
                        </div>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times me-1"></i>
                        Annuler
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-1"></i>
                        Modifier
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Aperçu couleur pour l'ajout
document.getElementById('add_couleur').addEventListener('input', function() {
    const couleur = this.value;
    const preview = document.getElementById('add_couleur_preview');
    preview.style.backgroundColor = couleur;
});

// Aperçu couleur pour la modification
document.getElementById('edit_couleur').addEventListener('input', function() {
    const couleur = this.value;
    const preview = document.getElementById('edit_couleur_preview');
    preview.style.backgroundColor = couleur;
});

// Fonction pour modifier une catégorie
function editCategory(categorie) {
    document.getElementById('edit_id').value = categorie.id;
    document.getElementById('edit_nom').value = categorie.nom;
    document.getElementById('edit_description').value = categorie.description || '';
    document.getElementById('edit_couleur').value = categorie.couleur;

    // Mettre à jour l'aperçu
    const preview = document.getElementById('edit_couleur_preview');
    preview.style.backgroundColor = categorie.couleur;

    // Afficher le modal
    const modal = new bootstrap.Modal(document.getElementById('editCategoryModal'));
    modal.show();
}

// Fonction pour supprimer une catégorie
function deleteCategory(id, nom) {
    if (confirm(`Êtes-vous sûr de vouloir supprimer la catégorie "${nom}" ?\n\nCette action est irréversible.`)) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="action" value="delete_category">
            <input type="hidden" name="id" value="${id}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}

// Validation des formulaires
document.getElementById('addCategoryForm').addEventListener('submit', function(e) {
    const nom = document.getElementById('add_nom').value.trim();
    if (!nom) {
        e.preventDefault();
        alert('Le nom de la catégorie est obligatoire.');
        return false;
    }
});

document.getElementById('editCategoryForm').addEventListener('submit', function(e) {
    const nom = document.getElementById('edit_nom').value.trim();
    if (!nom) {
        e.preventDefault();
        alert('Le nom de la catégorie est obligatoire.');
        return false;
    }
});

// Réinitialiser le formulaire d'ajout quand le modal se ferme
document.getElementById('addCategoryModal').addEventListener('hidden.bs.modal', function() {
    document.getElementById('addCategoryForm').reset();
    document.getElementById('add_couleur_preview').style.backgroundColor = '#007bff';
});
</script>

<?php include '../../../includes/footer.php'; ?>
