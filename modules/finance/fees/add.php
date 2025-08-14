<?php
/**
 * Module de gestion financière - Ajouter un frais scolaire
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

$page_title = 'Ajouter un frais scolaire';

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
    
    // Vérifier si ce type de frais existe déjà pour cette classe
    if (!empty($errors) === false) {
        $existing = $database->query(
            "SELECT id FROM frais_scolaires WHERE classe_id = ? AND type_frais = ? AND annee_scolaire_id = ?",
            [$classe_id, $type_frais, $current_year['id']]
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

                $required_columns = ['classe_id', 'type_frais', 'libelle', 'montant', 'annee_scolaire_id'];
                $missing_columns = array_diff($required_columns, $existing_columns);

                if (!empty($missing_columns)) {
                    $errors[] = 'Structure de table incorrecte. Colonnes manquantes : ' . implode(', ', $missing_columns) .
                               '. Veuillez corriger la structure de la table.';
                } else {
                    $database->beginTransaction();

                    // Construire la requête dynamiquement selon les colonnes disponibles
                    $insert_columns = ['classe_id', 'type_frais', 'libelle', 'montant', 'annee_scolaire_id'];
                    $insert_values = [$classe_id, $type_frais, $libelle, $montant, $current_year['id']];

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

        $required_columns = ['classe_id', 'type_frais', 'libelle', 'montant', 'annee_scolaire_id'];
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
                            <label for="type_frais" class="form-label">
                                Type de frais <span class="text-danger">*</span>
                            </label>
                            <select class="form-select" id="type_frais" name="type_frais" required>
                                <option value="">Sélectionner le type</option>
                                <option value="inscription" <?php echo (($_POST['type_frais'] ?? '') === 'inscription') ? 'selected' : ''; ?>>
                                    Frais d'inscription
                                </option>
                                <option value="mensualite" <?php echo (($_POST['type_frais'] ?? '') === 'mensualite') ? 'selected' : ''; ?>>
                                    Mensualité
                                </option>
                                <option value="examen" <?php echo (($_POST['type_frais'] ?? '') === 'examen') ? 'selected' : ''; ?>>
                                    Frais d'examen
                                </option>
                                <option value="uniforme" <?php echo (($_POST['type_frais'] ?? '') === 'uniforme') ? 'selected' : ''; ?>>
                                    Uniforme scolaire
                                </option>
                                <option value="transport" <?php echo (($_POST['type_frais'] ?? '') === 'transport') ? 'selected' : ''; ?>>
                                    Transport scolaire
                                </option>
                                <option value="cantine" <?php echo (($_POST['type_frais'] ?? '') === 'cantine') ? 'selected' : ''; ?>>
                                    Cantine
                                </option>
                                <option value="autre" <?php echo (($_POST['type_frais'] ?? '') === 'autre') ? 'selected' : ''; ?>>
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
                                   value="<?php echo htmlspecialchars($_POST['libelle'] ?? ''); ?>"
                                   placeholder="Ex: Frais d'inscription 2024"
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
                                   value="<?php echo htmlspecialchars($_POST['montant'] ?? ''); ?>"
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
                                   value="<?php echo htmlspecialchars($_POST['date_echeance'] ?? ''); ?>">
                            <div class="form-text">Date limite de paiement (optionnel)</div>
                        </div>
                        
                        <div class="col-md-6 mb-3">
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
                                  placeholder="Description détaillée du frais (optionnel)..."><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
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
document.getElementById('type_frais').addEventListener('change', function() {
    const libelle = document.getElementById('libelle');
    const classe = document.getElementById('classe_id');
    
    if (this.value && !libelle.value) {
        const types = {
            'inscription': 'Frais d\'inscription',
            'mensualite': 'Mensualité',
            'examen': 'Frais d\'examen',
            'uniforme': 'Uniforme scolaire',
            'transport': 'Transport scolaire',
            'cantine': 'Cantine',
            'autre': 'Autre frais'
        };
        
        libelle.value = types[this.value] + ' ' + new Date().getFullYear();
    }
});
</script>

<?php include '../../../includes/footer.php'; ?>
