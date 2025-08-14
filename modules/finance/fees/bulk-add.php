<?php
/**
 * Module de gestion financière - Ajout en lot de frais scolaires
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

$page_title = 'Ajout en lot de frais scolaires';

// Obtenir l'année scolaire actuelle
$current_year = getCurrentAcademicYear();

$errors = [];
$success_count = 0;
$skipped_count = 0;

// Récupérer les classes
$classes = $database->query(
    "SELECT id, nom, niveau FROM classes WHERE annee_scolaire_id = ? ORDER BY niveau, nom",
    [$current_year['id'] ?? 0]
)->fetchAll();

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $selected_classes = $_POST['classes'] ?? [];
    $frais_data = $_POST['frais'] ?? [];
    
    // Validation
    if (empty($selected_classes)) {
        $errors[] = 'Veuillez sélectionner au moins une classe.';
    }
    
    if (empty($frais_data)) {
        $errors[] = 'Veuillez ajouter au moins un frais.';
    }
    
    // Valider chaque frais
    foreach ($frais_data as $index => $frais) {
        $frais_num = $index + 1;
        
        if (empty($frais['type_frais'])) {
            $errors[] = "Frais #{$frais_num}: Le type de frais est obligatoire.";
        }
        
        if (empty($frais['libelle'])) {
            $errors[] = "Frais #{$frais_num}: Le libellé est obligatoire.";
        }
        
        if (!isset($frais['montant']) || (float)$frais['montant'] <= 0) {
            $errors[] = "Frais #{$frais_num}: Le montant doit être supérieur à 0.";
        }
        
        if (!empty($frais['date_echeance']) && !isValidDate($frais['date_echeance'])) {
            $errors[] = "Frais #{$frais_num}: La date d'échéance n'est pas valide.";
        }
    }
    
    // Si pas d'erreurs, procéder à l'ajout en lot
    if (empty($errors)) {
        try {
            $database->beginTransaction();
            
            // Vérifier les colonnes disponibles
            $columns = $database->query("DESCRIBE frais_scolaires")->fetchAll();
            $existing_columns = array_column($columns, 'Field');
            
            foreach ($selected_classes as $classe_id) {
                foreach ($frais_data as $frais) {
                    // Vérifier si ce type de frais existe déjà pour cette classe
                    $existing = $database->query(
                        "SELECT id FROM frais_scolaires WHERE classe_id = ? AND type_frais = ? AND annee_scolaire_id = ?",
                        [$classe_id, $frais['type_frais'], $current_year['id']]
                    )->fetch();
                    
                    if ($existing) {
                        $skipped_count++;
                        continue;
                    }
                    
                    // Construire la requête d'insertion dynamiquement
                    $insert_columns = ['classe_id', 'type_frais', 'libelle', 'montant', 'annee_scolaire_id'];
                    $insert_values = [$classe_id, $frais['type_frais'], $frais['libelle'], (float)$frais['montant'], $current_year['id']];
                    
                    // Ajouter les colonnes optionnelles si elles existent
                    if (in_array('obligatoire', $existing_columns)) {
                        $insert_columns[] = 'obligatoire';
                        $insert_values[] = isset($frais['obligatoire']) ? 1 : 0;
                    }
                    
                    if (in_array('date_echeance', $existing_columns)) {
                        $insert_columns[] = 'date_echeance';
                        $insert_values[] = !empty($frais['date_echeance']) ? $frais['date_echeance'] : null;
                    }
                    
                    if (in_array('description', $existing_columns)) {
                        $insert_columns[] = 'description';
                        $insert_values[] = $frais['description'] ?? '';
                    }
                    
                    $sql = "INSERT INTO frais_scolaires (" . implode(', ', $insert_columns) . ") 
                            VALUES (" . str_repeat('?,', count($insert_columns) - 1) . "?)";
                    
                    $database->execute($sql, $insert_values);
                    $success_count++;
                }
            }
            
            $database->commit();
            
            $message = "Ajout en lot terminé : {$success_count} frais créés";
            if ($skipped_count > 0) {
                $message .= ", {$skipped_count} ignorés (déjà existants)";
            }
            
            showMessage('success', $message);
            redirectTo('index.php');
            
        } catch (Exception $e) {
            $database->rollback();
            $errors[] = 'Erreur lors de l\'ajout en lot : ' . $e->getMessage();
        }
    }
}

include '../../../includes/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">
        <i class="fas fa-plus-square me-2"></i>
        Ajout en lot de frais scolaires
    </h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <div class="btn-group me-2">
            <a href="index.php" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-1"></i>
                Retour à la liste
            </a>
        </div>
        <div class="btn-group">
            <a href="templates.php" class="btn btn-outline-info">
                <i class="fas fa-file-alt me-1"></i>
                Modèles
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
        <form method="POST" id="bulk-form">
            <!-- Sélection des classes -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-school me-2"></i>
                        1. Sélectionner les classes
                    </h5>
                </div>
                <div class="card-body">
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
                                               id="classe_<?php echo $classe['id']; ?>">
                                        <label class="form-check-label" for="classe_<?php echo $classe['id']; ?>">
                                            <?php echo htmlspecialchars($classe['nom']); ?>
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
            </div>

            <!-- Configuration des frais -->
            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        <i class="fas fa-tags me-2"></i>
                        2. Configurer les frais
                    </h5>
                    <button type="button" class="btn btn-sm btn-primary" onclick="addFrais()">
                        <i class="fas fa-plus me-1"></i>
                        Ajouter un frais
                    </button>
                </div>
                <div class="card-body">
                    <div id="frais-container">
                        <!-- Les frais seront ajoutés ici dynamiquement -->
                    </div>
                    
                    <div class="text-center mt-3" id="no-frais-message">
                        <p class="text-muted">Aucun frais configuré. Cliquez sur "Ajouter un frais" pour commencer.</p>
                    </div>
                </div>
            </div>

            <!-- Actions -->
            <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                <a href="index.php" class="btn btn-outline-secondary me-md-2">
                    <i class="fas fa-times me-1"></i>
                    Annuler
                </a>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save me-1"></i>
                    Créer tous les frais
                </button>
            </div>
        </form>
    </div>

    <div class="col-lg-4">
        <!-- Modèles rapides -->
        <div class="card mb-3">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-magic me-2"></i>
                    Modèles rapides
                </h5>
            </div>
            <div class="card-body">
                <div class="d-grid gap-2">
                    <button type="button" class="btn btn-outline-primary btn-sm" onclick="loadTemplate('standard')">
                        <i class="fas fa-graduation-cap me-1"></i>
                        Frais standard
                    </button>
                    <button type="button" class="btn btn-outline-success btn-sm" onclick="loadTemplate('primaire')">
                        <i class="fas fa-child me-1"></i>
                        Primaire uniquement
                    </button>
                    <button type="button" class="btn btn-outline-info btn-sm" onclick="loadTemplate('secondaire')">
                        <i class="fas fa-user-graduate me-1"></i>
                        Secondaire uniquement
                    </button>
                </div>
            </div>
        </div>

        <!-- Aide -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-question-circle me-2"></i>
                    Aide
                </h5>
            </div>
            <div class="card-body">
                <h6>Ajout en lot :</h6>
                <ul class="small">
                    <li>Sélectionnez les classes concernées</li>
                    <li>Configurez les frais à appliquer</li>
                    <li>Les doublons seront automatiquement ignorés</li>
                    <li>Utilisez les modèles pour gagner du temps</li>
                </ul>
                
                <h6 class="mt-3">Conseils :</h6>
                <ul class="small">
                    <li>Commencez par les frais obligatoires</li>
                    <li>Vérifiez les montants avant validation</li>
                    <li>Utilisez des libellés clairs</li>
                    <li>Définissez des échéances réalistes</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<!-- Template pour un frais -->
<template id="frais-template">
    <div class="card mb-3 frais-item">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h6 class="mb-0">Frais #<span class="frais-number">1</span></h6>
            <button type="button" class="btn btn-sm btn-outline-danger" onclick="removeFrais(this)">
                <i class="fas fa-trash"></i>
            </button>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Type de frais *</label>
                    <select class="form-select" name="frais[INDEX][type_frais]" required>
                        <option value="">Sélectionner le type</option>
                        <option value="inscription">Frais d'inscription</option>
                        <option value="mensualite">Mensualité</option>
                        <option value="examen">Frais d'examen</option>
                        <option value="uniforme">Uniforme scolaire</option>
                        <option value="transport">Transport scolaire</option>
                        <option value="cantine">Cantine</option>
                        <option value="autre">Autre</option>
                    </select>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Montant (FC) *</label>
                    <input type="number" class="form-control" name="frais[INDEX][montant]" min="1" step="0.01" required>
                </div>
            </div>
            <div class="row">
                <div class="col-md-8 mb-3">
                    <label class="form-label">Libellé *</label>
                    <input type="text" class="form-control" name="frais[INDEX][libelle]" required>
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">Date d'échéance</label>
                    <input type="date" class="form-control" name="frais[INDEX][date_echeance]">
                </div>
            </div>
            <div class="row">
                <div class="col-md-8 mb-3">
                    <label class="form-label">Description</label>
                    <textarea class="form-control" name="frais[INDEX][description]" rows="2"></textarea>
                </div>
                <div class="col-md-4 mb-3">
                    <div class="form-check mt-4">
                        <input class="form-check-input" type="checkbox" name="frais[INDEX][obligatoire]" checked>
                        <label class="form-check-label">
                            <strong>Obligatoire</strong>
                        </label>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
let fraisCount = 0;

// Fonctions de gestion des classes
function selectAllClasses() {
    document.querySelectorAll('input[name="classes[]"]').forEach(cb => cb.checked = true);
}

function deselectAllClasses() {
    document.querySelectorAll('input[name="classes[]"]').forEach(cb => cb.checked = false);
}

// Fonctions de gestion des frais
function addFrais() {
    const template = document.getElementById('frais-template');
    const container = document.getElementById('frais-container');
    const noMessage = document.getElementById('no-frais-message');
    
    const clone = template.content.cloneNode(true);
    
    // Remplacer INDEX par le numéro actuel
    const html = clone.querySelector('.frais-item').outerHTML.replace(/INDEX/g, fraisCount);
    
    container.insertAdjacentHTML('beforeend', html);
    
    // Mettre à jour le numéro affiché
    const newItem = container.lastElementChild;
    newItem.querySelector('.frais-number').textContent = fraisCount + 1;
    
    fraisCount++;
    noMessage.style.display = 'none';
    
    updateFraisNumbers();
}

function removeFrais(button) {
    const fraisItem = button.closest('.frais-item');
    fraisItem.remove();
    
    updateFraisNumbers();
    
    if (document.querySelectorAll('.frais-item').length === 0) {
        document.getElementById('no-frais-message').style.display = 'block';
    }
}

function updateFraisNumbers() {
    document.querySelectorAll('.frais-item').forEach((item, index) => {
        item.querySelector('.frais-number').textContent = index + 1;
    });
}

// Modèles prédéfinis
function loadTemplate(type) {
    // Effacer les frais existants
    document.getElementById('frais-container').innerHTML = '';
    document.getElementById('no-frais-message').style.display = 'none';
    fraisCount = 0;
    
    const templates = {
        'standard': [
            {type: 'inscription', libelle: 'Frais d\'inscription', montant: 50000, obligatoire: true},
            {type: 'mensualite', libelle: 'Mensualité', montant: 25000, obligatoire: true},
            {type: 'examen', libelle: 'Frais d\'examen', montant: 15000, obligatoire: true}
        ],
        'primaire': [
            {type: 'inscription', libelle: 'Inscription primaire', montant: 30000, obligatoire: true},
            {type: 'mensualite', libelle: 'Mensualité primaire', montant: 15000, obligatoire: true},
            {type: 'uniforme', libelle: 'Uniforme primaire', montant: 20000, obligatoire: false}
        ],
        'secondaire': [
            {type: 'inscription', libelle: 'Inscription secondaire', montant: 75000, obligatoire: true},
            {type: 'mensualite', libelle: 'Mensualité secondaire', montant: 35000, obligatoire: true},
            {type: 'examen', libelle: 'Frais d\'examen secondaire', montant: 25000, obligatoire: true}
        ]
    };
    
    if (templates[type]) {
        templates[type].forEach(frais => {
            addFrais();
            const lastItem = document.querySelector('.frais-item:last-child');
            lastItem.querySelector('select[name*="[type_frais]"]').value = frais.type;
            lastItem.querySelector('input[name*="[libelle]"]').value = frais.libelle;
            lastItem.querySelector('input[name*="[montant]"]').value = frais.montant;
            lastItem.querySelector('input[name*="[obligatoire]"]').checked = frais.obligatoire;
        });
    }
}

// Validation du formulaire
document.getElementById('bulk-form').addEventListener('submit', function(e) {
    const selectedClasses = document.querySelectorAll('input[name="classes[]"]:checked');
    const fraisItems = document.querySelectorAll('.frais-item');
    
    if (selectedClasses.length === 0) {
        e.preventDefault();
        alert('Veuillez sélectionner au moins une classe.');
        return false;
    }
    
    if (fraisItems.length === 0) {
        e.preventDefault();
        alert('Veuillez ajouter au moins un frais.');
        return false;
    }
});

// Ajouter un frais par défaut au chargement
document.addEventListener('DOMContentLoaded', function() {
    // Pas de frais par défaut, l'utilisateur doit cliquer pour ajouter
});
</script>

<?php include '../../../includes/footer.php'; ?>
