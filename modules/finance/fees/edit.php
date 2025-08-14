<?php
/**
 * Module de gestion financière - Modifier un frais scolaire
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

// Récupérer l'ID du frais
$id = (int)($_GET['id'] ?? 0);
if (!$id) {
    showMessage('error', 'Frais non spécifié.');
    redirectTo('index.php');
}

// Récupérer les informations du frais
$sql = "SELECT f.*, c.nom as classe_nom, c.niveau
        FROM frais_scolaires f
        JOIN classes c ON f.classe_id = c.id
        WHERE f.id = ?";

$frais = $database->query($sql, [$id])->fetch();

if (!$frais) {
    showMessage('error', 'Frais non trouvé.');
    redirectTo('index.php');
}

$page_title = 'Modifier le frais - ' . $frais['libelle'];

// Obtenir l'année scolaire actuelle
$current_year = getCurrentAcademicYear();

$errors = [];

// Récupérer les classes
$classes = $database->query(
    "SELECT id, nom, niveau FROM classes WHERE annee_scolaire_id = ? ORDER BY niveau, nom",
    [$current_year['id'] ?? 0]
)->fetchAll();

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validation des données
    $classe_id = (int)($_POST['classe_id'] ?? 0);
    $type_frais = sanitizeInput($_POST['type_frais'] ?? '');
    $libelle = sanitizeInput($_POST['libelle'] ?? '');
    $montant = (float)($_POST['montant'] ?? 0);
    $obligatoire = isset($_POST['obligatoire']) ? 1 : 0;
    $date_echeance = sanitizeInput($_POST['date_echeance'] ?? '');
    $description = sanitizeInput($_POST['description'] ?? '');
    
    // Validation des champs obligatoires
    if (!$classe_id) $errors[] = 'La classe est obligatoire.';
    if (empty($type_frais)) $errors[] = 'Le type de frais est obligatoire.';
    if (empty($libelle)) $errors[] = 'Le libellé est obligatoire.';
    if ($montant <= 0) $errors[] = 'Le montant doit être supérieur à 0.';
    
    // Validation de la date d'échéance
    if (!empty($date_echeance) && !isValidDate($date_echeance)) {
        $errors[] = 'La date d\'échéance n\'est pas valide.';
    }
    
    // Validation du montant
    if ($montant > 10000000) { // 10 millions FC max
        $errors[] = 'Le montant ne peut pas dépasser 10 000 000 FC.';
    }
    
    // Vérifier si ce type de frais existe déjà pour cette classe (sauf pour le frais actuel)
    if (empty($errors)) {
        $existing = $database->query(
            "SELECT id FROM frais_scolaires WHERE classe_id = ? AND type_frais = ? AND annee_scolaire_id = ? AND id != ?",
            [$classe_id, $type_frais, $current_year['id'], $id]
        )->fetch();
        
        if ($existing) {
            $errors[] = 'Ce type de frais existe déjà pour cette classe.';
        }
    }
    
    // Si pas d'erreurs, mettre à jour le frais
    if (empty($errors)) {
        try {
            $database->beginTransaction();
            
            // Vérifier les colonnes disponibles
            $columns = $database->query("DESCRIBE frais_scolaires")->fetchAll();
            $existing_columns = array_column($columns, 'Field');
            
            // Construire la requête dynamiquement
            $update_fields = ['classe_id = ?', 'type_frais = ?', 'libelle = ?', 'montant = ?'];
            $update_values = [$classe_id, $type_frais, $libelle, $montant];
            
            if (in_array('obligatoire', $existing_columns)) {
                $update_fields[] = 'obligatoire = ?';
                $update_values[] = $obligatoire;
            }
            
            if (in_array('date_echeance', $existing_columns)) {
                $update_fields[] = 'date_echeance = ?';
                $update_values[] = $date_echeance ?: null;
            }
            
            if (in_array('description', $existing_columns)) {
                $update_fields[] = 'description = ?';
                $update_values[] = $description;
            }
            
            if (in_array('updated_at', $existing_columns)) {
                $update_fields[] = 'updated_at = CURRENT_TIMESTAMP';
            }
            
            $update_values[] = $id; // Pour la clause WHERE
            
            $sql = "UPDATE frais_scolaires SET " . implode(', ', $update_fields) . " WHERE id = ?";
            
            $database->execute($sql, $update_values);
            
            $database->commit();
            
            showMessage('success', 'Frais scolaire modifié avec succès !');
            redirectTo('view.php?id=' . $id);
            
        } catch (Exception $e) {
            $database->rollback();
            $errors[] = 'Erreur lors de la modification : ' . $e->getMessage();
        }
    }
}

include '../../../includes/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">
        <i class="fas fa-edit me-2"></i>
        Modifier le frais scolaire
    </h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <div class="btn-group me-2">
            <a href="view.php?id=<?php echo $id; ?>" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-1"></i>
                Retour
            </a>
        </div>
        <div class="btn-group">
            <a href="duplicate.php?id=<?php echo $id; ?>" class="btn btn-outline-info">
                <i class="fas fa-copy me-1"></i>
                Dupliquer
            </a>
        </div>
    </div>
</div>

<?php if (!empty($errors)): ?>
    <div class="alert alert-danger">
        <h6><i class="fas fa-exclamation-triangle me-2"></i>Erreurs détectées :</h6>
        <ul class="mb-0">
            <?php foreach ($errors as $error): ?>
                <li><?php echo htmlspecialchars($error); ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<div class="row">
    <div class="col-lg-8">
        <!-- Informations actuelles -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-info-circle me-2"></i>
                    Informations actuelles
                </h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <p><strong>Classe actuelle :</strong> <?php echo htmlspecialchars($frais['classe_nom']); ?></p>
                        <p><strong>Type actuel :</strong> <?php echo ucfirst($frais['type_frais']); ?></p>
                    </div>
                    <div class="col-md-6">
                        <p><strong>Montant actuel :</strong> <?php echo formatMoney($frais['montant']); ?></p>
                        <p><strong>Dernière modification :</strong> <?php echo formatDate($frais['updated_at'] ?? $frais['created_at']); ?></p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Formulaire de modification -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-edit me-2"></i>
                    Modifier les informations
                </h5>
            </div>
            <div class="card-body">
                <form method="POST" class="needs-validation" novalidate>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="classe_id" class="form-label">
                                Classe <span class="text-danger">*</span>
                            </label>
                            <select class="form-select" id="classe_id" name="classe_id" required>
                                <option value="">Sélectionner une classe</option>
                                <?php foreach ($classes as $classe): ?>
                                    <option value="<?php echo $classe['id']; ?>" 
                                            <?php echo (($_POST['classe_id'] ?? $frais['classe_id']) == $classe['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($classe['nom'] . ' (' . ucfirst($classe['niveau']) . ')'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="invalid-feedback">
                                Veuillez sélectionner une classe.
                            </div>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="type_frais" class="form-label">
                                Type de frais <span class="text-danger">*</span>
                            </label>
                            <select class="form-select" id="type_frais" name="type_frais" required>
                                <option value="">Sélectionner le type</option>
                                <option value="inscription" <?php echo (($_POST['type_frais'] ?? $frais['type_frais']) === 'inscription') ? 'selected' : ''; ?>>
                                    Frais d'inscription
                                </option>
                                <option value="mensualite" <?php echo (($_POST['type_frais'] ?? $frais['type_frais']) === 'mensualite') ? 'selected' : ''; ?>>
                                    Mensualité
                                </option>
                                <option value="examen" <?php echo (($_POST['type_frais'] ?? $frais['type_frais']) === 'examen') ? 'selected' : ''; ?>>
                                    Frais d'examen
                                </option>
                                <option value="uniforme" <?php echo (($_POST['type_frais'] ?? $frais['type_frais']) === 'uniforme') ? 'selected' : ''; ?>>
                                    Uniforme scolaire
                                </option>
                                <option value="transport" <?php echo (($_POST['type_frais'] ?? $frais['type_frais']) === 'transport') ? 'selected' : ''; ?>>
                                    Transport scolaire
                                </option>
                                <option value="cantine" <?php echo (($_POST['type_frais'] ?? $frais['type_frais']) === 'cantine') ? 'selected' : ''; ?>>
                                    Cantine
                                </option>
                                <option value="autre" <?php echo (($_POST['type_frais'] ?? $frais['type_frais']) === 'autre') ? 'selected' : ''; ?>>
                                    Autre
                                </option>
                            </select>
                            <div class="invalid-feedback">
                                Veuillez sélectionner un type de frais.
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-8 mb-3">
                            <label for="libelle" class="form-label">
                                Libellé <span class="text-danger">*</span>
                            </label>
                            <input type="text" 
                                   class="form-control" 
                                   id="libelle" 
                                   name="libelle" 
                                   value="<?php echo htmlspecialchars($_POST['libelle'] ?? $frais['libelle']); ?>"
                                   required>
                            <div class="invalid-feedback">
                                Veuillez saisir un libellé.
                            </div>
                        </div>
                        
                        <div class="col-md-4 mb-3">
                            <label for="montant" class="form-label">
                                Montant (FC) <span class="text-danger">*</span>
                            </label>
                            <input type="number" 
                                   class="form-control" 
                                   id="montant" 
                                   name="montant" 
                                   value="<?php echo htmlspecialchars($_POST['montant'] ?? $frais['montant']); ?>"
                                   min="1" 
                                   max="10000000" 
                                   step="0.01" 
                                   required>
                            <div class="invalid-feedback">
                                Veuillez saisir un montant valide.
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="date_echeance" class="form-label">Date d'échéance</label>
                            <input type="date" 
                                   class="form-control" 
                                   id="date_echeance" 
                                   name="date_echeance" 
                                   value="<?php echo htmlspecialchars($_POST['date_echeance'] ?? $frais['date_echeance']); ?>">
                            <div class="form-text">Date limite de paiement (optionnel)</div>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <div class="form-check mt-4">
                                <input class="form-check-input" 
                                       type="checkbox" 
                                       id="obligatoire" 
                                       name="obligatoire" 
                                       <?php echo (($_POST['obligatoire'] ?? $frais['obligatoire']) ? 'checked' : ''); ?>>
                                <label class="form-check-label" for="obligatoire">
                                    <strong>Frais obligatoire</strong>
                                </label>
                                <div class="form-text">Les frais obligatoires doivent être payés pour l'inscription</div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="description" class="form-label">Description</label>
                        <textarea class="form-control" 
                                  id="description" 
                                  name="description" 
                                  rows="3"><?php echo htmlspecialchars($_POST['description'] ?? $frais['description']); ?></textarea>
                    </div>
                    
                    <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                        <a href="view.php?id=<?php echo $id; ?>" class="btn btn-outline-secondary me-md-2">
                            <i class="fas fa-times me-1"></i>
                            Annuler
                        </a>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-1"></i>
                            Enregistrer les modifications
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <!-- Actions rapides -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-bolt me-2"></i>
                    Actions rapides
                </h5>
            </div>
            <div class="card-body">
                <div class="d-grid gap-2">
                    <a href="view.php?id=<?php echo $id; ?>" class="btn btn-outline-info">
                        <i class="fas fa-eye me-2"></i>
                        Voir les détails
                    </a>
                    <a href="duplicate.php?id=<?php echo $id; ?>" class="btn btn-outline-secondary">
                        <i class="fas fa-copy me-2"></i>
                        Dupliquer ce frais
                    </a>
                    <a href="delete.php?id=<?php echo $id; ?>" class="btn btn-outline-danger" 
                       onclick="return confirm('Êtes-vous sûr de vouloir supprimer ce frais ?')">
                        <i class="fas fa-trash me-2"></i>
                        Supprimer
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Validation du formulaire
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
