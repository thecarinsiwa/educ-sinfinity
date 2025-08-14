<?php
/**
 * Module de gestion financière - Dupliquer un frais scolaire
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

// Récupérer l'ID du frais à dupliquer
$id = (int)($_GET['id'] ?? 0);
if (!$id) {
    showMessage('error', 'Frais non spécifié.');
    redirectTo('index.php');
}

// Récupérer les informations du frais source
$sql = "SELECT f.*, c.nom as classe_nom, c.niveau
        FROM frais_scolaires f
        JOIN classes c ON f.classe_id = c.id
        WHERE f.id = ?";

$frais_source = $database->query($sql, [$id])->fetch();

if (!$frais_source) {
    showMessage('error', 'Frais source non trouvé.');
    redirectTo('index.php');
}

$page_title = 'Dupliquer le frais - ' . $frais_source['libelle'];

// Obtenir l'année scolaire actuelle
$current_year = getCurrentAcademicYear();

$errors = [];
$success = false;

// Récupérer les classes
$classes = $database->query(
    "SELECT id, nom, niveau FROM classes WHERE annee_scolaire_id = ? ORDER BY niveau, nom",
    [$current_year['id'] ?? 0]
)->fetchAll();

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $selected_classes = $_POST['classes'] ?? [];
    $nouveau_libelle = sanitizeInput($_POST['nouveau_libelle'] ?? '');
    $nouveau_montant = (float)($_POST['nouveau_montant'] ?? 0);
    $modifier_libelle = isset($_POST['modifier_libelle']);
    $modifier_montant = isset($_POST['modifier_montant']);
    
    // Validation
    if (empty($selected_classes)) {
        $errors[] = 'Veuillez sélectionner au moins une classe.';
    }
    
    if ($modifier_libelle && empty($nouveau_libelle)) {
        $errors[] = 'Veuillez saisir le nouveau libellé.';
    }
    
    if ($modifier_montant && $nouveau_montant <= 0) {
        $errors[] = 'Le nouveau montant doit être supérieur à 0.';
    }
    
    // Si pas d'erreurs, procéder à la duplication
    if (empty($errors)) {
        try {
            $database->beginTransaction();
            
            $duplicated_count = 0;
            $skipped_count = 0;
            
            foreach ($selected_classes as $classe_id) {
                // Vérifier si ce type de frais existe déjà pour cette classe
                $existing = $database->query(
                    "SELECT id FROM frais_scolaires WHERE classe_id = ? AND type_frais = ? AND annee_scolaire_id = ?",
                    [$classe_id, $frais_source['type_frais'], $current_year['id']]
                )->fetch();
                
                if ($existing) {
                    $skipped_count++;
                    continue;
                }
                
                // Préparer les données pour la duplication
                $libelle = $modifier_libelle ? $nouveau_libelle : $frais_source['libelle'];
                $montant = $modifier_montant ? $nouveau_montant : $frais_source['montant'];
                
                // Vérifier les colonnes disponibles
                $columns = $database->query("DESCRIBE frais_scolaires")->fetchAll();
                $existing_columns = array_column($columns, 'Field');
                
                // Construire la requête d'insertion dynamiquement
                $insert_columns = ['classe_id', 'type_frais', 'libelle', 'montant', 'annee_scolaire_id'];
                $insert_values = [$classe_id, $frais_source['type_frais'], $libelle, $montant, $current_year['id']];
                
                // Ajouter les colonnes optionnelles si elles existent
                if (in_array('obligatoire', $existing_columns)) {
                    $insert_columns[] = 'obligatoire';
                    $insert_values[] = $frais_source['obligatoire'];
                }
                
                if (in_array('date_echeance', $existing_columns)) {
                    $insert_columns[] = 'date_echeance';
                    $insert_values[] = $frais_source['date_echeance'];
                }
                
                if (in_array('description', $existing_columns)) {
                    $insert_columns[] = 'description';
                    $insert_values[] = $frais_source['description'];
                }
                
                $sql = "INSERT INTO frais_scolaires (" . implode(', ', $insert_columns) . ") 
                        VALUES (" . str_repeat('?,', count($insert_columns) - 1) . "?)";
                
                $database->execute($sql, $insert_values);
                $duplicated_count++;
            }
            
            $database->commit();
            
            $message = "Duplication terminée : {$duplicated_count} frais créés";
            if ($skipped_count > 0) {
                $message .= ", {$skipped_count} ignorés (déjà existants)";
            }
            
            showMessage('success', $message);
            redirectTo('index.php');
            
        } catch (Exception $e) {
            $database->rollback();
            $errors[] = 'Erreur lors de la duplication : ' . $e->getMessage();
        }
    }
}

include '../../../includes/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">
        <i class="fas fa-copy me-2"></i>
        Dupliquer le frais scolaire
    </h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <div class="btn-group me-2">
            <a href="view.php?id=<?php echo $id; ?>" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-1"></i>
                Retour
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
        <!-- Informations du frais source -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-info-circle me-2"></i>
                    Frais à dupliquer
                </h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <table class="table table-borderless">
                            <tr>
                                <td class="fw-bold" style="width: 120px;">Libellé :</td>
                                <td><?php echo htmlspecialchars($frais_source['libelle']); ?></td>
                            </tr>
                            <tr>
                                <td class="fw-bold">Type :</td>
                                <td><?php echo ucfirst($frais_source['type_frais']); ?></td>
                            </tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <table class="table table-borderless">
                            <tr>
                                <td class="fw-bold" style="width: 120px;">Classe source :</td>
                                <td><?php echo htmlspecialchars($frais_source['classe_nom']); ?></td>
                            </tr>
                            <tr>
                                <td class="fw-bold">Montant :</td>
                                <td><?php echo formatMoney($frais_source['montant']); ?></td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Formulaire de duplication -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-copy me-2"></i>
                    Options de duplication
                </h5>
            </div>
            <div class="card-body">
                <form method="POST" class="needs-validation" novalidate>
                    <!-- Sélection des classes -->
                    <div class="mb-4">
                        <label class="form-label">
                            Classes de destination <span class="text-danger">*</span>
                        </label>
                        <div class="row">
                            <?php 
                            $niveaux = [];
                            foreach ($classes as $classe) {
                                $niveaux[$classe['niveau']][] = $classe;
                            }
                            ?>
                            
                            <?php foreach ($niveaux as $niveau => $classes_niveau): ?>
                                <div class="col-md-4 mb-3">
                                    <h6 class="text-primary"><?php echo ucfirst($niveau); ?></h6>
                                    <?php foreach ($classes_niveau as $classe): ?>
                                        <div class="form-check">
                                            <input class="form-check-input" 
                                                   type="checkbox" 
                                                   name="classes[]" 
                                                   value="<?php echo $classe['id']; ?>"
                                                   id="classe_<?php echo $classe['id']; ?>"
                                                   <?php echo ($classe['id'] == $frais_source['classe_id']) ? 'disabled' : ''; ?>>
                                            <label class="form-check-label" for="classe_<?php echo $classe['id']; ?>">
                                                <?php echo htmlspecialchars($classe['nom']); ?>
                                                <?php if ($classe['id'] == $frais_source['classe_id']): ?>
                                                    <small class="text-muted">(source)</small>
                                                <?php endif; ?>
                                            </label>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <div class="form-text">
                            <button type="button" class="btn btn-sm btn-outline-primary" onclick="selectAllClasses()">
                                Tout sélectionner
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="deselectAllClasses()">
                                Tout désélectionner
                            </button>
                        </div>
                    </div>
                    
                    <!-- Options de modification -->
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <div class="form-check">
                                <input class="form-check-input" 
                                       type="checkbox" 
                                       id="modifier_libelle" 
                                       name="modifier_libelle">
                                <label class="form-check-label" for="modifier_libelle">
                                    <strong>Modifier le libellé</strong>
                                </label>
                            </div>
                            <input type="text" 
                                   class="form-control mt-2" 
                                   id="nouveau_libelle" 
                                   name="nouveau_libelle" 
                                   placeholder="Nouveau libellé"
                                   disabled>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <div class="form-check">
                                <input class="form-check-input" 
                                       type="checkbox" 
                                       id="modifier_montant" 
                                       name="modifier_montant">
                                <label class="form-check-label" for="modifier_montant">
                                    <strong>Modifier le montant</strong>
                                </label>
                            </div>
                            <input type="number" 
                                   class="form-control mt-2" 
                                   id="nouveau_montant" 
                                   name="nouveau_montant" 
                                   placeholder="Nouveau montant"
                                   min="1" 
                                   step="0.01"
                                   disabled>
                        </div>
                    </div>
                    
                    <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                        <a href="view.php?id=<?php echo $id; ?>" class="btn btn-outline-secondary me-md-2">
                            <i class="fas fa-times me-1"></i>
                            Annuler
                        </a>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-copy me-1"></i>
                            Dupliquer le frais
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <!-- Aide -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-question-circle me-2"></i>
                    Aide
                </h5>
            </div>
            <div class="card-body">
                <h6>Duplication de frais :</h6>
                <ul class="small">
                    <li>Sélectionnez les classes de destination</li>
                    <li>Modifiez le libellé si nécessaire</li>
                    <li>Ajustez le montant si différent</li>
                    <li>Les frais existants seront ignorés</li>
                </ul>
                
                <h6 class="mt-3">Cas d'usage :</h6>
                <ul class="small">
                    <li><strong>Même frais, toutes classes :</strong> Ne pas modifier</li>
                    <li><strong>Montants différents :</strong> Cocher "Modifier le montant"</li>
                    <li><strong>Libellés spécifiques :</strong> Cocher "Modifier le libellé"</li>
                </ul>
                
                <div class="alert alert-info mt-3">
                    <small>
                        <i class="fas fa-info-circle me-1"></i>
                        Les frais déjà existants pour le même type et la même classe seront automatiquement ignorés.
                    </small>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Gestion des checkboxes de modification
document.getElementById('modifier_libelle').addEventListener('change', function() {
    document.getElementById('nouveau_libelle').disabled = !this.checked;
    if (this.checked) {
        document.getElementById('nouveau_libelle').focus();
    }
});

document.getElementById('modifier_montant').addEventListener('change', function() {
    document.getElementById('nouveau_montant').disabled = !this.checked;
    if (this.checked) {
        document.getElementById('nouveau_montant').focus();
    }
});

// Fonctions de sélection des classes
function selectAllClasses() {
    const checkboxes = document.querySelectorAll('input[name="classes[]"]:not(:disabled)');
    checkboxes.forEach(cb => cb.checked = true);
}

function deselectAllClasses() {
    const checkboxes = document.querySelectorAll('input[name="classes[]"]:not(:disabled)');
    checkboxes.forEach(cb => cb.checked = false);
}

// Validation du formulaire
(function() {
    'use strict';
    window.addEventListener('load', function() {
        var forms = document.getElementsByClassName('needs-validation');
        var validation = Array.prototype.filter.call(forms, function(form) {
            form.addEventListener('submit', function(event) {
                // Vérifier qu'au moins une classe est sélectionnée
                const selectedClasses = document.querySelectorAll('input[name="classes[]"]:checked');
                if (selectedClasses.length === 0) {
                    event.preventDefault();
                    event.stopPropagation();
                    alert('Veuillez sélectionner au moins une classe.');
                    return false;
                }
                
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
