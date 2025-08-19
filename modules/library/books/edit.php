<?php
/**
 * Module Bibliothèque - Modifier un livre
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

// Récupérer l'ID du livre
$livre_id = intval($_GET['id'] ?? 0);
if (!$livre_id) {
    showMessage('error', 'ID du livre manquant.');
    redirectTo('index.php');
}

// Récupérer les informations du livre
try {
    $livre = $database->query(
        "SELECT l.*, cl.nom as categorie_nom, cl.couleur as categorie_couleur
         FROM livres l
         LEFT JOIN categories_livres cl ON l.categorie_id = cl.id
         WHERE l.id = ?",
        [$livre_id]
    )->fetch();
    
    if (!$livre) {
        showMessage('error', 'Livre non trouvé.');
        redirectTo('index.php');
    }
    
} catch (Exception $e) {
    showMessage('error', 'Erreur lors du chargement : ' . $e->getMessage());
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
        
        // Vérifier si l'ISBN existe déjà (sauf pour ce livre)
        if (!empty($isbn)) {
            $existing = $database->query(
                "SELECT id FROM livres WHERE isbn = ? AND id != ?",
                [$isbn, $livre_id]
            )->fetch();
            
            if ($existing) {
                throw new Exception('Un autre livre avec cet ISBN existe déjà.');
            }
        }
        
        // Calculer la différence d'exemplaires
        $ancien_nombre = isset($livre['nombre_disponibles']) ? $livre['nombre_disponibles'] : 1;
        $difference = $nombre_exemplaires - $ancien_nombre;
        $nouveaux_disponibles = isset($livre['exemplaires_disponibles']) ? $livre['exemplaires_disponibles'] : 1;
        
        // Ajuster les exemplaires disponibles
        if ($difference > 0) {
            $nouveaux_disponibles += $difference;
        } elseif ($difference < 0) {
            $nouveaux_disponibles = max(0, $nouveaux_disponibles + $difference);
        }
        
        // Mettre à jour le livre
        $database->execute(
            "UPDATE livres SET 
                isbn = ?, titre = ?, auteur = ?, editeur = ?, annee_publication = ?, 
                categorie_id = ?, nombre_pages = ?, langue = ?, resume = ?, emplacement = ?, 
                cote = ?, prix_achat = ?, date_acquisition = ?, etat = ?, 
                nombre_disponibles = ?, exemplaires_disponibles = ?, notes = ?, updated_at = NOW()
             WHERE id = ?",
            [
                $isbn ?: null, $titre, $auteur, $editeur, 
                $annee_publication ?: null, $categorie_id ?: null,
                $nombre_pages ?: null, $langue, $resume, $emplacement, $cote,
                $prix_achat ?: null, $date_acquisition, $etat, 
                $nombre_exemplaires, $nouveaux_disponibles, $notes, $livre_id
            ]
        );
        
        showMessage('success', 'Livre modifié avec succès.');
        redirectTo("view.php?id=$livre_id");
        
    } catch (Exception $e) {
        showMessage('error', 'Erreur lors de la modification : ' . $e->getMessage());
    }
}

// Récupérer les catégories
try {
    $categories = $database->query("SELECT * FROM categories_livres ORDER BY nom")->fetchAll();
} catch (Exception $e) {
    $categories = [];
}

$page_title = "Modifier le livre : " . htmlspecialchars($livre['titre']);
include '../../../includes/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">
        <i class="fas fa-edit me-2"></i>
        Modifier le livre
    </h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <div class="btn-group me-2">
            <a href="view.php?id=<?php echo $livre_id; ?>" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-1"></i>
                Retour au livre
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
                                   value="<?php echo htmlspecialchars($_POST['titre'] ?? $livre['titre']); ?>" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="auteur" class="form-label">
                                Auteur <span class="text-danger">*</span>
                            </label>
                            <input type="text" class="form-control" id="auteur" name="auteur" 
                                   value="<?php echo htmlspecialchars($_POST['auteur'] ?? $livre['auteur']); ?>" required>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label for="isbn" class="form-label">ISBN</label>
                            <input type="text" class="form-control" id="isbn" name="isbn" 
                                   value="<?php echo htmlspecialchars($_POST['isbn'] ?? ($livre['isbn'] ?? '')); ?>"
                                   placeholder="978-2-1234-5678-9">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="editeur" class="form-label">Éditeur</label>
                            <input type="text" class="form-control" id="editeur" name="editeur" 
                                   value="<?php echo htmlspecialchars($_POST['editeur'] ?? ($livre['editeur'] ?? '')); ?>">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="annee_publication" class="form-label">Année de publication</label>
                            <input type="number" class="form-control" id="annee_publication" name="annee_publication" 
                                   value="<?php echo htmlspecialchars($_POST['annee_publication'] ?? ($livre['annee_publication'] ?? '')); ?>"
                                   min="1800" max="<?php echo date('Y'); ?>">
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="categorie_id" class="form-label">Catégorie</label>
                            <select class="form-select" id="categorie_id" name="categorie_id">
                                <option value="">-- Sélectionner une catégorie --</option>
                                <?php foreach ($categories as $categorie): ?>
                                    <?php 
                                    $selected_categorie = intval($_POST['categorie_id'] ?? ($livre['categorie_id'] ?? 0));
                                    ?>
                                    <option value="<?php echo $categorie['id']; ?>" 
                                            <?php echo ($selected_categorie === $categorie['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($categorie['nom']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label for="langue" class="form-label">Langue</label>
                            <select class="form-select" id="langue" name="langue">
                                <?php 
                                $selected_langue = $_POST['langue'] ?? ($livre['langue'] ?? 'Français');
                                ?>
                                <option value="Français" <?php echo $selected_langue === 'Français' ? 'selected' : ''; ?>>Français</option>
                                <option value="Anglais" <?php echo $selected_langue === 'Anglais' ? 'selected' : ''; ?>>Anglais</option>
                                <option value="Lingala" <?php echo $selected_langue === 'Lingala' ? 'selected' : ''; ?>>Lingala</option>
                                <option value="Swahili" <?php echo $selected_langue === 'Swahili' ? 'selected' : ''; ?>>Swahili</option>
                                <option value="Autre" <?php echo $selected_langue === 'Autre' ? 'selected' : ''; ?>>Autre</option>
                            </select>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label for="nombre_pages" class="form-label">Nombre de pages</label>
                            <input type="number" class="form-control" id="nombre_pages" name="nombre_pages" 
                                   value="<?php echo htmlspecialchars($_POST['nombre_pages'] ?? ($livre['nombre_pages'] ?? '')); ?>" min="1">
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="resume" class="form-label">Résumé</label>
                        <textarea class="form-control" id="resume" name="resume" rows="3" 
                                  placeholder="Résumé ou description du livre..."><?php echo htmlspecialchars($_POST['resume'] ?? ($livre['resume'] ?? '')); ?></textarea>
                    </div>
                </div>
            </div>
            
            <!-- Informations de gestion -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-cog me-2"></i>
                        Informations de gestion
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label for="emplacement" class="form-label">Emplacement</label>
                            <input type="text" class="form-control" id="emplacement" name="emplacement" 
                                   value="<?php echo htmlspecialchars($_POST['emplacement'] ?? ($livre['emplacement'] ?? '')); ?>"
                                   placeholder="Ex: Rayon A, Étagère 3">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="cote" class="form-label">Cote</label>
                            <input type="text" class="form-control" id="cote" name="cote" 
                                   value="<?php echo htmlspecialchars($_POST['cote'] ?? ($livre['cote'] ?? '')); ?>"
                                   placeholder="Ex: 823.914 ROW">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="etat" class="form-label">État</label>
                            <select class="form-select" id="etat" name="etat">
                                <?php 
                                $selected_etat = $_POST['etat'] ?? ($livre['etat'] ?? 'bon');
                                ?>
                                <option value="excellent" <?php echo $selected_etat === 'excellent' ? 'selected' : ''; ?>>Excellent</option>
                                <option value="bon" <?php echo $selected_etat === 'bon' ? 'selected' : ''; ?>>Bon</option>
                                <option value="moyen" <?php echo $selected_etat === 'moyen' ? 'selected' : ''; ?>>Moyen</option>
                                <option value="mauvais" <?php echo $selected_etat === 'mauvais' ? 'selected' : ''; ?>>Mauvais</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label for="prix_achat" class="form-label">Prix d'achat (FC)</label>
                            <input type="number" class="form-control" id="prix_achat" name="prix_achat" 
                                   value="<?php echo htmlspecialchars($_POST['prix_achat'] ?? ($livre['prix_achat'] ?? '')); ?>"
                                   step="0.01" min="0">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="date_acquisition" class="form-label">Date d'acquisition</label>
                            <input type="date" class="form-control" id="date_acquisition" name="date_acquisition" 
                                   value="<?php echo htmlspecialchars($_POST['date_acquisition'] ?? ($livre['date_acquisition'] ?? date('Y-m-d'))); ?>">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="nombre_exemplaires" class="form-label">
                                Nombre d'exemplaires <span class="text-danger">*</span>
                            </label>
                            <input type="number" class="form-control" id="nombre_exemplaires" name="nombre_exemplaires" 
                                   value="<?php echo htmlspecialchars($_POST['nombre_exemplaires'] ?? (isset($livre['nombre_disponibles']) ? $livre['nombre_disponibles'] : 1)); ?>"
                                   min="1" required>
                            <small class="form-text text-muted">
                                Actuellement : <?php echo isset($livre['exemplaires_disponibles']) ? $livre['exemplaires_disponibles'] : 1; ?> disponible(s)
                            </small>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="notes" class="form-label">Notes</label>
                        <textarea class="form-control" id="notes" name="notes" rows="3" 
                                  placeholder="Notes internes, observations..."><?php echo htmlspecialchars($_POST['notes'] ?? ($livre['notes'] ?? '')); ?></textarea>
                    </div>
                </div>
            </div>
            
            <!-- Boutons d'action -->
            <div class="card">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <a href="view.php?id=<?php echo $livre_id; ?>" class="btn btn-secondary">
                            <i class="fas fa-times me-1"></i>
                            Annuler
                        </a>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-1"></i>
                            Enregistrer les modifications
                        </button>
                    </div>
                </div>
            </div>
        </form>
    </div>
    
    <!-- Informations complémentaires -->
    <div class="col-md-4">
        <div class="card mb-4">
            <div class="card-header">
                <h6 class="mb-0">
                    <i class="fas fa-info-circle me-2"></i>
                    Informations actuelles
                </h6>
            </div>
            <div class="card-body">
                <table class="table table-sm">
                    <tr>
                        <td class="fw-bold">Statut :</td>
                        <td>
                            <?php
                            $status_colors = [
                                'disponible' => 'success',
                                'emprunte' => 'info',
                                'reserve' => 'warning',
                                'perdu' => 'danger',
                                'retire' => 'secondary'
                            ];
                            $status_labels = [
                                'disponible' => 'Disponible',
                                'emprunte' => 'Emprunté',
                                'reserve' => 'Réservé',
                                'perdu' => 'Perdu',
                                'retire' => 'Retiré'
                            ];
                            $status = $livre['status'] ?? 'disponible';
                            ?>
                            <span class="badge bg-<?php echo $status_colors[$status] ?? 'secondary'; ?>">
                                <?php echo $status_labels[$status] ?? ucfirst($status); ?>
                            </span>
                        </td>
                    </tr>
                    <tr>
                        <td class="fw-bold">Exemplaires :</td>
                        <td>
                            <?php echo isset($livre['nombre_disponibles']) ? $livre['nombre_disponibles'] : 1; ?> total
                            (<?php echo isset($livre['exemplaires_disponibles']) ? $livre['exemplaires_disponibles'] : 1; ?> disponible)
                        </td>
                    </tr>
                    <tr>
                        <td class="fw-bold">Créé le :</td>
                        <td><?php echo date('d/m/Y', strtotime($livre['created_at'])); ?></td>
                    </tr>
                    <?php if (isset($livre['updated_at']) && $livre['updated_at']): ?>
                        <tr>
                            <td class="fw-bold">Modifié le :</td>
                            <td><?php echo date('d/m/Y', strtotime($livre['updated_at'])); ?></td>
                        </tr>
                    <?php endif; ?>
                </table>
            </div>
        </div>
        
        <div class="card">
            <div class="card-header">
                <h6 class="mb-0">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    Attention
                </h6>
            </div>
            <div class="card-body">
                <div class="alert alert-warning mb-0">
                    <small>
                        <strong>Modification des exemplaires :</strong><br>
                        Si vous réduisez le nombre d'exemplaires, les exemplaires disponibles seront ajustés automatiquement. 
                        Assurez-vous qu'aucun exemplaire n'est actuellement emprunté avant de réduire le nombre total.
                    </small>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../../../includes/footer.php'; ?>
