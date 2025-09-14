<?php
/**
 * Module de gestion financière - Ajouter un type de frais
    * Application de gestion scolaire - République Démocratique du Congo
 */

require_once '../../../../config/config.php';
require_once '../../../../config/database.php';
require_once '../../../../includes/functions.php';
require_once '../../../../includes/permissions-pages.php';
require_once 'functions.php';

// Vérifier l'authentification et les permissions
requireLogin();
requirePagePermissionFromDB('finance', 'fees/types/add', 'create', '../../../../dashboard.php');

$page_title = 'Ajouter un type de frais';

// Obtenir l'année scolaire actuelle
$current_year = getCurrentAcademicYear();

if (!$current_year || !isset($current_year['id'])) {
    showMessage('error', 'Aucune année scolaire active ou ID manquant.');
    redirectTo('../../index.php');
}

$errors = [];
$success = false;

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validation des données
    $nom = cleanInputText(sanitizeInput($_POST['nom'] ?? ''));
    $description = cleanInputText(sanitizeInput($_POST['description'] ?? ''));
    $priorite = (int)($_POST['priorite'] ?? 10);
    $actif = isset($_POST['actif']) ? 1 : 0;
    
    // Validation des champs obligatoires
    if (empty($nom)) {
        $errors[] = 'Le nom du type de frais est obligatoire.';
    }
    
    // Vérifier que le nom n'existe pas déjà pour cette année scolaire
    if (!empty($nom)) {
        $existing = $database->query(
            "SELECT id FROM type_frais WHERE nom = ? AND annee_scolaire_id = ?",
            [$nom, $current_year['id']]
        )->fetch();
        
        if ($existing) {
            $errors[] = 'Un type de frais avec ce nom existe déjà pour cette année scolaire.';
        }
    }
    
    // Validation de la longueur
    if (strlen($nom) > 100) {
            $errors[] = 'Le nom ne peut pas dépasser 100 caractères.';
    }
    
    // Validation de la priorité
    if ($priorite < 1 || $priorite > 100) {
        $errors[] = 'La priorité doit être comprise entre 1 et 100.';
    }
    
    // Si pas d'erreurs, insérer le type de frais
    if (empty($errors)) {
        try {
            $database->beginTransaction();
            
            $sql = "INSERT INTO type_frais (nom, description, annee_scolaire_id, priorite, actif) VALUES (?, ?, ?, ?, ?)";
            
            $database->execute($sql, [
                $nom,
                $description,
                $current_year['id'],
                $priorite,
                $actif
            ]);
            
            $type_id = $database->lastInsertId();
            
            // Enregistrer l'action dans les logs
            logAction('type_frais_created', "Type de frais créé: {$nom}", $type_id);
            
            $database->commit();
            
            showMessage('success', 'Type de frais créé avec succès !');
            redirectTo('index.php');
            
        } catch (Exception $e) {
            $database->rollback();
            $errors[] = 'Erreur lors de la création : ' . $e->getMessage();
        }
    }
}

include '../../../../includes/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">
        <i class="fas fa-plus me-2"></i>
        Ajouter un type de frais
    </h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <div class="btn-group me-2">
            <a href="index.php" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-1"></i>
                Retour
            </a>
        </div>
        <div class="btn-group me-2">
            <span class="btn btn-outline-info">
                <i class="fas fa-calendar me-1"></i>
                Année: <?php echo htmlspecialchars($current_year['annee'] ?? 'Non définie'); ?>
            </span>
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
        <!-- Formulaire principal -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-edit me-2"></i>
                    Informations du type de frais
                </h5>
            </div>
            <div class="card-body">
                <form method="POST" class="needs-validation" novalidate>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="nom" class="form-label">
                                Nom du type de frais <span class="text-danger">*</span>
                            </label>
                            <input type="text" 
                                   class="form-control" 
                                   id="nom" 
                                   name="nom" 
                                   placeholder="Ex: Inscription, Mensualité, Examen..."
                                   value="<?php echo htmlspecialchars($_POST['nom'] ?? ''); ?>"
                                   maxlength="100"
                                   required>
                            <div class="form-text">
                                Nom unique pour cette année scolaire (max 100 caractères)
                            </div>
                        </div>
                        
                        <div class="col-md-3 mb-3">
                            <label for="priorite" class="form-label">
                                Priorité <span class="text-danger">*</span>
                            </label>
                            <input type="number" 
                                   class="form-control" 
                                   id="priorite" 
                                   name="priorite" 
                                   min="1" 
                                   max="100" 
                                   value="<?php echo htmlspecialchars($_POST['priorite'] ?? '10'); ?>"
                                   required>
                            <div class="form-text">
                                    Plus le chiffre est bas, plus la priorité est haute (1 = priorité maximale)
                            </div>
                        </div>
                        
                        <div class="col-md-3 mb-3">
                            <label for="actif" class="form-label">Statut</label>
                            <div class="form-check form-switch">
                                <input class="form-check-input" 
                                       type="checkbox" 
                                       id="actif" 
                                       name="actif" 
                                       <?php echo (isset($_POST['actif']) || !isset($_POST['nom'])) ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="actif">
                                    <strong>Actif</strong>
                                </label>
                            </div>
                            <div class="form-text">
                                Les types inactifs ne seront pas disponibles lors de la configuration des frais
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="description" class="form-label">Description</label>
                        <textarea class="form-control" 
                                  id="description" 
                                  name="description" 
                                  rows="4" 
                                  placeholder="Description détaillée du type de frais (optionnel)..."><?php echo prepareFormText($_POST['description'] ?? ''); ?></textarea>
                        <div class="form-text">
                            Description optionnelle pour clarifier l'usage de ce type de frais
                        </div>
                    </div>
                    
                    <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                        <a href="index.php" class="btn btn-outline-secondary me-md-2">
                            <i class="fas fa-times me-1"></i>
                            Annuler
                        </a>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-1"></i>
                            Enregistrer
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
                <h6>Types de frais courants :</h6>
                <ul class="small">
                    <li><strong>Inscription</strong> - Frais d'inscription annuels</li>
                    <li><strong>Mensualité</strong> - Frais de scolarité mensuels</li>
                    <li><strong>Examen</strong> - Frais d'examens et évaluations</li>
                    <li><strong>Uniforme</strong> - Frais d'uniforme scolaire</li>
                    <li><strong>Transport</strong> - Frais de transport scolaire</li>
                    <li><strong>Cantine</strong> - Frais de restauration</li>
                    <li><strong>Matériel</strong> - Frais de matériel pédagogique</li>
                    <li><strong>Activités</strong> - Frais d'activités extra-scolaires</li>
                </ul>
                
                <div class="alert alert-info mt-3">
                    <small>
                        <i class="fas fa-info-circle me-1"></i>
                        <strong>Conseil :</strong> Créez des types de frais génériques qui pourront être utilisés pour toutes les classes.
                    </small>
                </div>
                
                <div class="alert alert-warning mt-3">
                    <small>
                        <i class="fas fa-exclamation-triangle me-1"></i>
                        <strong>Important :</strong> Une fois qu'un type de frais est utilisé dans des configurations, il ne pourra plus être supprimé.
                    </small>
                </div>
            </div>
        </div>
        
        <!-- Types existants -->
        <div class="card mt-3">
            <div class="card-header">
                <h6 class="mb-0">
                    <i class="fas fa-list me-2"></i>
                    Types existants
                </h6>
            </div>
            <div class="card-body">
                <?php
                $types_existants = $database->query(
                    "SELECT nom, actif FROM type_frais WHERE annee_scolaire_id = ? ORDER BY nom",
                    [$current_year['id']]
                )->fetchAll();
                ?>
                
                <?php if (!empty($types_existants)): ?>
                    <?php foreach ($types_existants as $type): ?>
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span><?php echo htmlspecialchars($type['nom']); ?></span>
                            <?php if ($type['actif']): ?>
                                <span class="badge bg-success">Actif</span>
                            <?php else: ?>
                                <span class="badge bg-warning">Inactif</span>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p class="text-muted small mb-0">Aucun type de frais créé pour cette année.</p>
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

// Compteur de caractères pour le nom
document.getElementById('nom').addEventListener('input', function() {
    const maxLength = 100;
    const currentLength = this.value.length;
    const remaining = maxLength - currentLength;
    
    // Créer ou mettre à jour l'indicateur
    let indicator = document.getElementById('char-count');
    if (!indicator) {
        indicator = document.createElement('small');
        indicator.id = 'char-count';
        indicator.className = 'form-text text-muted';
        this.parentNode.appendChild(indicator);
    }
    
    if (remaining < 20) {
        indicator.className = 'form-text text-warning';
    } else {
        indicator.className = 'form-text text-muted';
    }
    
    indicator.textContent = `${currentLength}/${maxLength} caractères`;
});
</script>

<?php include '../../../../includes/footer.php'; ?>



