<?php
/**
 * Module de gestion financière - Ajouter un frais scolaire
 * Application de gestion scolaire - République Démocratique du Congo
 */

require_once '../../../config/config.php';
require_once '../../../config/database.php';
require_once '../../../includes/functions.php';
require_once '../../../includes/permissions-pages.php';
require_once 'types/functions.php';

// Vérifier l'authentification et les permissions
requireLogin();
requirePagePermissionFromDB('finance', 'fees/add', 'create', '../../dashboard.php');

$page_title = 'Ajouter un frais scolaire';

// Obtenir l'année scolaire actuelle
$current_year = getCurrentAcademicYear();

// Obtenir la devise par défaut
$devise_par_defaut = getDefaultCurrency();

$errors = [];
$success = false;

// Récupérer les classes
$classes = $database->query(
    "SELECT id, nom, niveau FROM classes WHERE annee_scolaire_id = ? ORDER BY niveau, nom",
    [$current_year['id'] ?? 0]
)->fetchAll();

// Récupérer les types de frais actifs pour l'année scolaire actuelle
$types_frais = [];
if ($current_year && isset($current_year['id'])) {
    $types_frais = $database->query(
        "SELECT id, nom, description FROM type_frais WHERE annee_scolaire_id = ? AND actif = 1 ORDER BY nom",
        [$current_year['id']]
    )->fetchAll();
}

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validation des données
    $classe_id = (int)($_POST['classe_id'] ?? 0);
    $type_frais_id = (int)($_POST['type_frais_id'] ?? 0);
    $libelle = cleanInputText(sanitizeInput($_POST['libelle'] ?? ''));
    $montant = (float)($_POST['montant'] ?? 0);
    $devise_id = (int)($_POST['devise_id'] ?? 0);
    $obligatoire = isset($_POST['obligatoire']) ? 1 : 0;
    $date_echeance = sanitizeInput($_POST['date_echeance'] ?? '');
    $description = cleanInputText(sanitizeInput($_POST['description'] ?? ''));
    
    // Validation des champs obligatoires
    if (!$classe_id) $errors[] = 'La classe est obligatoire.';
    if (!$type_frais_id) $errors[] = 'Le type de frais est obligatoire.';
    if (empty($libelle)) $errors[] = 'Le libellé est obligatoire.';
    if ($montant <= 0) $errors[] = 'Le montant doit être supérieur à 0.';
    if (!$devise_id) $errors[] = 'La devise est obligatoire.';
    
    // Validation de la date d'échéance
    if (!empty($date_echeance) && !isValidDate($date_echeance)) {
        $errors[] = 'La date d\'échéance n\'est pas valide.';
    }
    
    // Validation du montant
    if ($montant > 10000000) { // 10 millions FC max
        $errors[] = 'Le montant ne peut pas dépasser 10 000 000 FC.';
    }
    
    // Récupérer le nom du type de frais
    $type_frais_info = null;
    if ($type_frais_id) {
        $type_frais_info = $database->query(
            "SELECT nom FROM type_frais WHERE id = ? AND annee_scolaire_id = ? AND actif = 1",
            [$type_frais_id, $current_year['id']]
        )->fetch();
        
        if (!$type_frais_info) {
            $errors[] = 'Le type de frais sélectionné n\'est pas valide ou n\'est pas actif.';
        }
    }
    
    // Vérifier si ce type de frais existe déjà pour cette classe
    if (empty($errors) && $type_frais_info) {
        $existing = $database->query(
            "SELECT id FROM frais_scolaires WHERE classe_id = ? AND type_frais = ? AND annee_scolaire_id = ?",
            [$classe_id, $type_frais_info['nom'], $current_year['id']]
        )->fetch();
        
        if ($existing) {
            $errors[] = 'Ce type de frais existe déjà pour cette classe.';
        }
    }
    
    // Si pas d'erreurs, insérer le frais
    if (empty($errors)) {
        try {
            // Vérifier d'abord si la table existe avec la bonne structure
            $table_exists = $database->query("SHOW TABLES LIKE 'frais_scolaires'")->fetch();

            if (!$table_exists) {
                $errors[] = 'La table frais_scolaires n\'existe pas. Veuillez d\'abord créer la table.';
            } else {
                // Vérifier les colonnes
                $columns = $database->query("DESCRIBE frais_scolaires")->fetchAll();
                $existing_columns = array_column($columns, 'Field');

                $required_columns = ['classe_id', 'type_frais_id', 'libelle', 'montant', 'devise_id', 'montant_devise_par_defaut', 'annee_scolaire_id'];
                $missing_columns = array_diff($required_columns, $existing_columns);

                if (!empty($missing_columns)) {
                    $errors[] = 'Structure de table incorrecte. Colonnes manquantes : ' . implode(', ', $missing_columns) .
                               '. Veuillez corriger la structure de la table.';
                } else {
                    $database->beginTransaction();

                    // Construire la requête dynamiquement selon les colonnes disponibles
                    $insert_columns = ['classe_id', 'type_frais_id', 'libelle', 'montant', 'devise_id', 'montant_devise_par_defaut', 'annee_scolaire_id'];
                    
                    // Calculer le montant dans la devise par défaut
                    $montant_devise_par_defaut = convertToDefaultCurrency($montant, $devise_id);
                    
                    $insert_values = [$classe_id, $type_frais_id, $libelle, $montant, $devise_id, $montant_devise_par_defaut, $current_year['id']];

                    // Ajouter les colonnes optionnelles si elles existent
                    if (in_array('obligatoire', $existing_columns)) {
                        $insert_columns[] = 'obligatoire';
                        $insert_values[] = $obligatoire;
                    }

                    if (in_array('date_echeance', $existing_columns)) {
                        $insert_columns[] = 'date_echeance';
                        $insert_values[] = $date_echeance ?: null;
                    }

                    if (in_array('description', $existing_columns)) {
                        $insert_columns[] = 'description';
                        $insert_values[] = $description;
                    }

                    $sql = "INSERT INTO frais_scolaires (" . implode(', ', $insert_columns) . ")
                            VALUES (" . str_repeat('?,', count($insert_columns) - 1) . "?)";

                    $database->execute($sql, $insert_values);

                    $database->commit();

                    showMessage('success', 'Frais scolaire ajouté avec succès !');
                    redirectTo('index.php');
                }
            }

        } catch (Exception $e) {
            if (isset($database)) {
                $database->rollback();
            }
            $errors[] = 'Erreur lors de l\'ajout : ' . $e->getMessage();
        }
    }
}

// Vérifier la structure de la table avant d'afficher le formulaire
$table_structure_ok = false;
$structure_message = '';

try {
    $table_exists = $database->query("SHOW TABLES LIKE 'frais_scolaires'")->fetch();

    if (!$table_exists) {
        $structure_message = '<div class="alert alert-warning">
            <h6><i class="fas fa-exclamation-triangle me-2"></i>Table manquante</h6>
            <p>La table frais_scolaires n\'existe pas.
            <a href="../../check_fees_table_structure.php" class="alert-link">Cliquez ici pour la créer</a>.</p>
        </div>';
    } else {
        $columns = $database->query("DESCRIBE frais_scolaires")->fetchAll();
        $existing_columns = array_column($columns, 'Field');

        $required_columns = ['classe_id', 'type_frais_id', 'libelle', 'montant', 'devise_id', 'montant_devise_par_defaut', 'annee_scolaire_id'];
        $missing_columns = array_diff($required_columns, $existing_columns);

        if (!empty($missing_columns)) {
            $structure_message = '<div class="alert alert-warning">
                <h6><i class="fas fa-exclamation-triangle me-2"></i>Structure de table incorrecte</h6>
                <p>Colonnes manquantes : <strong>' . implode(', ', $missing_columns) . '</strong><br>
                <a href="../../check_fees_table_structure.php" class="alert-link">Cliquez ici pour corriger la structure</a>.</p>
            </div>';
        } else {
            $table_structure_ok = true;
        }
    }
} catch (Exception $e) {
    $structure_message = '<div class="alert alert-danger">
        <h6><i class="fas fa-times-circle me-2"></i>Erreur de base de données</h6>
        <p>' . htmlspecialchars($e->getMessage()) . '</p>
    </div>';
}

include '../../../includes/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">
        <i class="fas fa-plus me-2"></i>
        Ajouter un frais scolaire
    </h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <?php if ($devise_par_defaut): ?>
            <div class="btn-group me-2">
                <button type="button" class="btn btn-outline-info" disabled>
                    <i class="fas fa-coins me-1"></i>
                    Devise par défaut: <?php echo htmlspecialchars($devise_par_defaut['code']); ?> (<?php echo htmlspecialchars($devise_par_defaut['symbole']); ?>)
                </button>
            </div>
        <?php endif; ?>
        <div class="btn-group me-2">
            <a href="index.php" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-1"></i>
                Retour à la liste
            </a>
        </div>
    </div>
</div>

<?php echo $structure_message; ?>

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
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-info-circle me-2"></i>
                    Informations du frais scolaire
                </h5>
            </div>
            <div class="card-body">
                <?php if (!$table_structure_ok): ?>
                    <div class="alert alert-info">
                        <p class="mb-0">Le formulaire est désactivé car la structure de la table n'est pas correcte.
                        Veuillez d'abord corriger la structure de la table.</p>
                    </div>
                <?php endif; ?>

                <form method="POST" class="needs-validation" novalidate <?php echo !$table_structure_ok ? 'style="opacity: 0.5; pointer-events: none;"' : ''; ?>>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="classe_id" class="form-label">
                                Classe <span class="text-danger">*</span>
                            </label>
                            <select class="form-select" id="classe_id" name="classe_id" required>
                                <option value="">Sélectionner une classe</option>
                                <?php foreach ($classes as $classe): ?>
                                    <option value="<?php echo $classe['id']; ?>" 
                                            <?php echo (($_POST['classe_id'] ?? '') == $classe['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($classe['nom'] . ' (' . ucfirst($classe['niveau']) . ')'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="invalid-feedback">
                                Veuillez sélectionner une classe.
                            </div>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="type_frais_id" class="form-label">
                                Type de frais <span class="text-danger">*</span>
                            </label>
                            <select class="form-select" id="type_frais_id" name="type_frais_id" required>
                                <option value="">Sélectionner le type</option>
                                <?php foreach ($types_frais as $type): ?>
                                    <option value="<?php echo $type['id']; ?>" 
                                            <?php echo (($_POST['type_frais_id'] ?? '') == $type['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($type['nom']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="invalid-feedback">
                                Veuillez sélectionner un type de frais.
                            </div>
                            <?php if (empty($types_frais)): ?>
                                <div class="form-text">
                                    <a href="types/index.php" class="text-warning">
                                        <i class="fas fa-exclamation-triangle me-1"></i>
                                        Aucun type de frais configuré. Cliquez ici pour en créer.
                                    </a>
                                </div>
                            <?php endif; ?>
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
                                   value="<?php echo htmlspecialchars($_POST['libelle'] ?? ''); ?>"
                                   placeholder="Ex: Frais d'inscription 2024"
                                   required>
                            <div class="invalid-feedback">
                                Veuillez saisir un libellé.
                            </div>
                        </div>
                        
                        <div class="col-md-4 mb-3">
                            <label for="montant" class="form-label">
                                Montant <span class="text-danger">*</span>
                            </label>
                            <div class="input-group">
                                <input type="number" 
                                       class="form-control" 
                                       id="montant" 
                                       name="montant" 
                                       value="<?php echo htmlspecialchars($_POST['montant'] ?? ''); ?>"
                                       min="1" 
                                       max="10000000" 
                                       step="0.01" 
                                       required>
                                <span class="input-group-text" id="montant-symbole">FC</span>
                            </div>
                            <div class="invalid-feedback">
                                Veuillez saisir un montant valide.
                            </div>
                        </div>
                    </div>
                    
                    <!-- Affichage de la conversion -->
                    <div class="row" id="conversion-display" style="display: none;">
                        <div class="col-12">
                            <div class="alert alert-info">
                                <h6><i class="fas fa-exchange-alt me-2"></i>Conversion automatique</h6>
                                <div id="conversion-details">
                                    <!-- Rempli par JavaScript -->
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label for="devise_id" class="form-label">Devise <span class="text-danger">*</span></label>
                            <select class="form-select" id="devise_id" name="devise_id" required onchange="updateMontantSymbole()">
                                <option value="">Sélectionner...</option>
                                <?php 
                                $devises = getActiveCurrencies();
                                foreach ($devises as $devise): 
                                ?>
                                    <option value="<?= $devise['id'] ?>" 
                                            <?= ($_POST['devise_id'] ?? '') == $devise['id'] ? 'selected' : '' ?>
                                            data-symbole="<?= htmlspecialchars($devise['symbole']) ?>"
                                            data-taux="<?= $devise['taux_conversion'] ?>">
                                        <?= htmlspecialchars($devise['code']) ?> - <?= htmlspecialchars($devise['nom']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="col-md-4 mb-3">
                            <label for="date_echeance" class="form-label">Date d'échéance</label>
                            <input type="date" 
                                   class="form-control" 
                                   id="date_echeance" 
                                   name="date_echeance" 
                                   value="<?php echo htmlspecialchars($_POST['date_echeance'] ?? ''); ?>">
                            <div class="form-text">Date limite de paiement (optionnel)</div>
                        </div>
                        
                        <div class="col-md-4 mb-3">
                            <div class="form-check mt-4">
                                <input class="form-check-input" 
                                       type="checkbox" 
                                       id="obligatoire" 
                                       name="obligatoire" 
                                       <?php echo isset($_POST['obligatoire']) ? 'checked' : 'checked'; ?>>
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
                                  rows="3"
                                  placeholder="Description détaillée du frais (optionnel)..."><?php echo prepareFormText($_POST['description'] ?? ''); ?></textarea>
                    </div>
                    
                    <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                        <a href="index.php" class="btn btn-outline-secondary me-md-2">
                            <i class="fas fa-times me-1"></i>
                            Annuler
                        </a>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-1"></i>
                            Enregistrer le frais
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
                <h6>Types de frais :</h6>
                <ul class="small">
                    <li><strong>Inscription :</strong> Frais d'inscription annuelle</li>
                    <li><strong>Mensualité :</strong> Frais mensuels de scolarité</li>
                    <li><strong>Examen :</strong> Frais d'examens et évaluations</li>
                    <li><strong>Uniforme :</strong> Achat d'uniformes scolaires</li>
                    <li><strong>Transport :</strong> Frais de transport scolaire</li>
                    <li><strong>Cantine :</strong> Frais de restauration</li>
                    <li><strong>Autre :</strong> Autres frais spécifiques</li>
                </ul>
                
                <h6 class="mt-3">Conseils :</h6>
                <ul class="small">
                    <li>Utilisez des libellés clairs et précis</li>
                    <li>Définissez des échéances réalistes</li>
                    <li>Marquez comme obligatoire les frais essentiels</li>
                    <li>Ajoutez une description pour plus de clarté</li>
                </ul>
            </div>
        </div>

        <!-- Aperçu des classes -->
        <div class="card mt-3">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-school me-2"></i>
                    Classes disponibles
                </h5>
            </div>
            <div class="card-body">
                <?php if (!empty($classes)): ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($classes as $classe): ?>
                            <div class="list-group-item d-flex justify-content-between align-items-center px-0">
                                <div>
                                    <strong><?php echo htmlspecialchars($classe['nom']); ?></strong>
                                    <br><small class="text-muted"><?php echo ucfirst($classe['niveau']); ?></small>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p class="text-muted">Aucune classe disponible</p>
                <?php endif; ?>
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

// Auto-génération du libellé basé sur le type
document.getElementById('type_frais_id').addEventListener('change', function() {
    const libelle = document.getElementById('libelle');
    const classe = document.getElementById('classe_id');
    
    if (this.value && !libelle.value) {
        // Récupérer le nom du type de frais sélectionné
        const selectedOption = this.options[this.selectedIndex];
        const typeName = selectedOption.text;
        
        libelle.value = typeName + ' ' + new Date().getFullYear();
    }
});

// Mise à jour du symbole de la devise selon la sélection
function updateMontantSymbole() {
    const deviseSelect = document.getElementById('devise_id');
    const symboleSpan = document.getElementById('montant-symbole');
    const selectedOption = deviseSelect.options[deviseSelect.selectedIndex];
    
    if (selectedOption && selectedOption.dataset.symbole) {
        symboleSpan.textContent = selectedOption.dataset.symbole;
    } else {
        symboleSpan.textContent = 'FC';
    }
    
    // Mettre à jour la conversion
    updateConversion();
}

// Mise à jour de l'affichage de la conversion
function updateConversion() {
    const montant = parseFloat(document.getElementById('montant').value) || 0;
    const deviseSelect = document.getElementById('devise_id');
    const selectedOption = deviseSelect.options[deviseSelect.selectedIndex];
    const conversionDisplay = document.getElementById('conversion-display');
    const conversionDetails = document.getElementById('conversion-details');
    
    if (montant > 0 && selectedOption && selectedOption.dataset.taux) {
        const taux = parseFloat(selectedOption.dataset.taux);
        const symbole = selectedOption.dataset.symbole;
        const code = selectedOption.text.split(' - ')[0];
        
        // Calculer le montant en devise par défaut (CDF)
        const montantCDF = montant / taux;
        
        conversionDetails.innerHTML = `
            <div class="row">
                <div class="col-md-6">
                    <strong>Montant saisi :</strong> ${montant.toLocaleString()} ${symbole} (${code})
                </div>
                <div class="col-md-6">
                    <strong>Équivalent en CDF :</strong> ${montantCDF.toLocaleString()} FC
                </div>
            </div>
            <small class="text-muted mt-2 d-block">
                Taux de conversion : 1 ${code} = ${(1/taux).toLocaleString()} FC
            </small>
        `;
        
        conversionDisplay.style.display = 'block';
    } else {
        conversionDisplay.style.display = 'none';
    }
}

// Événements pour la conversion en temps réel
document.getElementById('montant').addEventListener('input', updateConversion);
document.getElementById('devise_id').addEventListener('change', updateConversion);

// Initialiser le symbole au chargement de la page
document.addEventListener('DOMContentLoaded', function() {
    updateMontantSymbole();
});
</script>

<?php include '../../../includes/footer.php'; ?>
