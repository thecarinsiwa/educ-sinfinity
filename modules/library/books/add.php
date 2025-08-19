<?php
/**
 * Module Bibliothèque - Ajouter un livre
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

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Validation des données
        $isbn = trim($_POST['isbn'] ?? '');
        $titre = trim($_POST['titre'] ?? '');
        $auteur = trim($_POST['auteur'] ?? '');
        $editeur = trim($_POST['editeur'] ?? '');
        $annee_publication = intval($_POST['annee_publication'] ?? 0);
        $categorie_id = intval($_POST['categorie_id'] ?? 0);
        $nombre_pages = intval($_POST['nombre_pages'] ?? 0);
        $langue = trim($_POST['langue'] ?? 'Français');
        $resume = trim($_POST['resume'] ?? '');
        $emplacement = trim($_POST['emplacement'] ?? '');
        $cote = trim($_POST['cote'] ?? '');
        $prix_achat = floatval($_POST['prix_achat'] ?? 0);
        $date_acquisition = $_POST['date_acquisition'] ?? date('Y-m-d');
        $etat = $_POST['etat'] ?? 'bon';
        $nombre_exemplaires = intval($_POST['nombre_exemplaires'] ?? 1);
        $notes = trim($_POST['notes'] ?? '');
        
        // Validation
        if (empty($titre) || empty($auteur)) {
            throw new Exception('Le titre et l\'auteur sont obligatoires.');
        }
        
        if ($nombre_exemplaires < 1) {
            throw new Exception('Le nombre d\'exemplaires doit être au moins 1.');
        }
        
        // Vérifier si l'ISBN existe déjà
        if (!empty($isbn)) {
            $existing = $database->query(
                "SELECT id FROM livres WHERE isbn = ?",
                [$isbn]
            )->fetch();
            
            if ($existing) {
                throw new Exception('Un livre avec cet ISBN existe déjà.');
            }
        }
        
        // Insérer le livre
        $database->execute(
            "INSERT INTO livres (
                isbn, titre, auteur, editeur, annee_publication, categorie_id,
                nombre_pages, langue, resume, emplacement, cote, prix_achat,
                date_acquisition, etat, status, nombre_exemplaires, nombre_disponibles, exemplaires_disponibles,
                notes, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'disponible', ?, ?, ?, ?, NOW())",
            [
                $isbn ?: null, $titre, $auteur, $editeur, 
                $annee_publication ?: null, $categorie_id ?: null,
                $nombre_pages ?: null, $langue, $resume, $emplacement, $cote,
                $prix_achat ?: null, $date_acquisition, $etat, 
                $nombre_exemplaires, $nombre_exemplaires, $nombre_exemplaires, $notes
            ]
        );
        
        $livre_id = $database->lastInsertId();
        
        showMessage('success', 'Livre ajouté avec succès au catalogue.');
        redirectTo("view.php?id=$livre_id");
        
    } catch (Exception $e) {
        showMessage('error', 'Erreur lors de l\'ajout : ' . $e->getMessage());
    }
}

// Récupérer les catégories
try {
    $categories = $database->query("SELECT * FROM categories_livres ORDER BY nom")->fetchAll();
} catch (Exception $e) {
    $categories = [];
}

$page_title = "Ajouter un Livre";
include '../../../includes/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">
        <i class="fas fa-plus-circle me-2"></i>
        Ajouter un Livre
    </h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <div class="btn-group me-2">
            <a href="index.php" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-1"></i>
                Retour au catalogue
            </a>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-8">
        <form method="POST" class="needs-validation" novalidate>
            <!-- Informations principales -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-book me-2"></i>
                        Informations principales
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="titre" class="form-label">
                                Titre <span class="text-danger">*</span>
                            </label>
                            <input type="text" class="form-control" id="titre" name="titre" 
                                   value="<?php echo htmlspecialchars($_POST['titre'] ?? ''); ?>" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="auteur" class="form-label">
                                Auteur <span class="text-danger">*</span>
                            </label>
                            <input type="text" class="form-control" id="auteur" name="auteur" 
                                   value="<?php echo htmlspecialchars($_POST['auteur'] ?? ''); ?>" required>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label for="isbn" class="form-label">ISBN</label>
                            <input type="text" class="form-control" id="isbn" name="isbn" 
                                   value="<?php echo htmlspecialchars($_POST['isbn'] ?? ''); ?>"
                                   placeholder="978-2-1234-5678-9">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="editeur" class="form-label">Éditeur</label>
                            <input type="text" class="form-control" id="editeur" name="editeur" 
                                   value="<?php echo htmlspecialchars($_POST['editeur'] ?? ''); ?>">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="annee_publication" class="form-label">Année de publication</label>
                            <input type="number" class="form-control" id="annee_publication" name="annee_publication" 
                                   value="<?php echo htmlspecialchars($_POST['annee_publication'] ?? ''); ?>"
                                   min="1800" max="<?php echo date('Y'); ?>">
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="categorie_id" class="form-label">Catégorie</label>
                            <select class="form-select" id="categorie_id" name="categorie_id">
                                <option value="">-- Sélectionner une catégorie --</option>
                                <?php foreach ($categories as $categorie): ?>
                                    <option value="<?php echo $categorie['id']; ?>" 
                                            <?php echo (intval($_POST['categorie_id'] ?? 0) === $categorie['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($categorie['nom']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label for="langue" class="form-label">Langue</label>
                            <select class="form-select" id="langue" name="langue">
                                <option value="Français" <?php echo ($_POST['langue'] ?? 'Français') === 'Français' ? 'selected' : ''; ?>>Français</option>
                                <option value="Anglais" <?php echo ($_POST['langue'] ?? '') === 'Anglais' ? 'selected' : ''; ?>>Anglais</option>
                                <option value="Lingala" <?php echo ($_POST['langue'] ?? '') === 'Lingala' ? 'selected' : ''; ?>>Lingala</option>
                                <option value="Swahili" <?php echo ($_POST['langue'] ?? '') === 'Swahili' ? 'selected' : ''; ?>>Swahili</option>
                                <option value="Autre" <?php echo ($_POST['langue'] ?? '') === 'Autre' ? 'selected' : ''; ?>>Autre</option>
                            </select>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label for="nombre_pages" class="form-label">Nombre de pages</label>
                            <input type="number" class="form-control" id="nombre_pages" name="nombre_pages" 
                                   value="<?php echo htmlspecialchars($_POST['nombre_pages'] ?? ''); ?>" min="1">
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="resume" class="form-label">Résumé</label>
                        <textarea class="form-control" id="resume" name="resume" rows="3" 
                                  placeholder="Résumé ou description du livre..."><?php echo htmlspecialchars($_POST['resume'] ?? ''); ?></textarea>
                    </div>
                </div>
            </div>
            
            <!-- Informations de gestion -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-cogs me-2"></i>
                        Informations de gestion
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label for="emplacement" class="form-label">Emplacement</label>
                            <input type="text" class="form-control" id="emplacement" name="emplacement" 
                                   value="<?php echo htmlspecialchars($_POST['emplacement'] ?? ''); ?>"
                                   placeholder="Ex: Étagère A, Rayon 3">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="cote" class="form-label">Cote</label>
                            <input type="text" class="form-control" id="cote" name="cote" 
                                   value="<?php echo htmlspecialchars($_POST['cote'] ?? ''); ?>"
                                   placeholder="Ex: 843.912 DUB">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="nombre_exemplaires" class="form-label">
                                Nombre d'exemplaires <span class="text-danger">*</span>
                            </label>
                            <input type="number" class="form-control" id="nombre_exemplaires" name="nombre_exemplaires" 
                                   value="<?php echo htmlspecialchars($_POST['nombre_exemplaires'] ?? '1'); ?>" 
                                   min="1" required>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label for="prix_achat" class="form-label">Prix d'achat (FC)</label>
                            <input type="number" class="form-control" id="prix_achat" name="prix_achat" 
                                   value="<?php echo htmlspecialchars($_POST['prix_achat'] ?? ''); ?>" 
                                   min="0" step="100">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="date_acquisition" class="form-label">Date d'acquisition</label>
                            <input type="date" class="form-control" id="date_acquisition" name="date_acquisition" 
                                   value="<?php echo htmlspecialchars($_POST['date_acquisition'] ?? date('Y-m-d')); ?>">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="etat" class="form-label">État</label>
                            <select class="form-select" id="etat" name="etat">
                                <option value="excellent" <?php echo ($_POST['etat'] ?? 'bon') === 'excellent' ? 'selected' : ''; ?>>Excellent</option>
                                <option value="bon" <?php echo ($_POST['etat'] ?? 'bon') === 'bon' ? 'selected' : ''; ?>>Bon</option>
                                <option value="moyen" <?php echo ($_POST['etat'] ?? '') === 'moyen' ? 'selected' : ''; ?>>Moyen</option>
                                <option value="mauvais" <?php echo ($_POST['etat'] ?? '') === 'mauvais' ? 'selected' : ''; ?>>Mauvais</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="notes" class="form-label">Notes</label>
                        <textarea class="form-control" id="notes" name="notes" rows="2" 
                                  placeholder="Notes internes sur le livre..."><?php echo htmlspecialchars($_POST['notes'] ?? ''); ?></textarea>
                    </div>
                </div>
            </div>
            
            <!-- Boutons d'action -->
            <div class="card">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <a href="index.php" class="btn btn-secondary">
                                <i class="fas fa-times me-1"></i>
                                Annuler
                            </a>
                        </div>
                        <div>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-1"></i>
                                Ajouter le livre
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
    
    <div class="col-md-4">
        <!-- Aide -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-info-circle me-2"></i>
                    Aide
                </h5>
            </div>
            <div class="card-body">
                <div class="alert alert-info">
                    <h6><i class="fas fa-lightbulb me-2"></i>Conseils</h6>
                    <ul class="mb-0 small">
                        <li>Le titre et l'auteur sont obligatoires</li>
                        <li>L'ISBN doit être unique</li>
                        <li>Utilisez un emplacement précis pour faciliter la recherche</li>
                        <li>La cote aide au classement selon votre système</li>
                    </ul>
                </div>
                
                <div class="alert alert-warning">
                    <h6><i class="fas fa-exclamation-triangle me-2"></i>Important</h6>
                    <p class="mb-0 small">
                        Une fois ajouté, le livre sera automatiquement disponible pour l'emprunt.
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Validation Bootstrap
(function() {
    'use strict';
    window.addEventListener('load', function() {
        var forms = document.getElementsByClassName('needs-validation');
        var validation = Array.prototype.filter.call(forms, function(form) {
            form.addEventListener('submit', function(event) {
                if (form.checkValidity() === false) {
                    event.preventDefault();
                    event.stopPropagation();
                }
                form.classList.add('was-validated');
            }, false);
        });
    }, false);
})();
</script>

<?php include '../../../includes/footer.php'; ?>
