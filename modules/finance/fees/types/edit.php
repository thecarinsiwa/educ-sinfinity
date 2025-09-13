<?php
/**
 * Module de gestion financiÃ¨re - Modifier un type de frais
 * Application de gestion scolaire - RÃ©publique DÃ©mocratique du Congo
 */

require_once '../../../../config/config.php';
require_once '../../../../config/database.php';
require_once '../../../../includes/functions.php';
require_once '../../../../includes/permissions-pages.php';
require_once 'functions.php';

// Vérifier l'authentification et les permissions
requireLogin();
requirePagePermissionFromDB('finance', 'fees', 'edit', '../../../../dashboard.php');

$page_title = 'Modifier un type de frais';

// RÃ©cupÃ©rer l'ID du type de frais
$id = (int)($_GET['id'] ?? 0);

if (!$id) {
    showMessage('error', 'Type de frais non spÃ©cifiÃ©.');
    redirectTo('index.php');
}

// RÃ©cupÃ©rer les informations du type de frais
$type_frais = $database->query(
    "SELECT tf.*, as_annee.annee, as_annee.date_debut, as_annee.date_fin
     FROM type_frais tf
     JOIN annees_scolaires as_annee ON tf.annee_scolaire_id = as_annee.id
     WHERE tf.id = ?",
    [$id]
)->fetch();

if (!$type_frais) {
    showMessage('error', 'Type de frais non trouvÃ©.');
    redirectTo('index.php');
}

// VÃ©rifier si le type de frais est utilisÃ©
$usage_count = $database->query(
    "SELECT COUNT(*) as count FROM frais_scolaires WHERE type_frais_id = ? AND annee_scolaire_id = ?",
    [$type_frais['id'], $type_frais['annee_scolaire_id']]
)->fetch()['count'];

$errors = [];
$success = false;

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validation des donnÃ©es
    $nom = cleanInputText(sanitizeInput($_POST['nom'] ?? ''));
    $description = cleanInputText(sanitizeInput($_POST['description'] ?? ''));
    $priorite = (int)($_POST['priorite'] ?? 10);
    $actif = isset($_POST['actif']) ? 1 : 0;
    
    // Validation des champs obligatoires
    if (empty($nom)) {
        $errors[] = 'Le nom du type de frais est obligatoire.';
    }
    
    // VÃ©rifier que le nom n'existe pas dÃ©jÃ  pour cette annÃ©e scolaire (sauf pour le type actuel)
    if (!empty($nom) && $nom !== $type_frais['nom']) {
        $existing = $database->query(
            "SELECT id FROM type_frais WHERE nom = ? AND annee_scolaire_id = ? AND id != ?",
            [$nom, $type_frais['annee_scolaire_id'], $id]
        )->fetch();
        
        if ($existing) {
            $errors[] = 'Un type de frais avec ce nom existe dÃ©jÃ  pour cette annÃ©e scolaire.';
        }
    }
    
    // Si le type est utilisÃ©, on ne peut pas changer le nom
    if ($usage_count > 0 && $nom !== $type_frais['nom']) {
        $errors[] = 'Impossible de modifier le nom car ce type de frais est dÃ©jÃ  utilisÃ© dans ' . $usage_count . ' configuration(s).';
    }
    
    // Validation de la longueur
    if (strlen($nom) > 100) {
        $errors[] = 'Le nom ne peut pas dÃ©passer 100 caractÃ¨res.';
    }
    
    // Validation de la prioritÃ©
    if ($priorite < 1 || $priorite > 100) {
        $errors[] = 'La prioritÃ© doit Ãªtre comprise entre 1 et 100.';
    }
    
    // Si pas d'erreurs, mettre Ã  jour le type de frais
    if (empty($errors)) {
        try {
            $database->beginTransaction();
            
            $sql = "UPDATE type_frais SET nom = ?, description = ?, priorite = ?, actif = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?";
            
            $database->execute($sql, [
                $nom,
                $description,
                $priorite,
                $actif,
                $id
            ]);
            
            // Note: Avec la nouvelle structure, les frais scolaires utilisent type_frais_id
            // donc pas besoin de mettre Ã  jour les frais scolaires quand le nom change
            
            // Enregistrer l'action dans les logs
            logAction('type_frais_updated', "Type de frais modifiÃ©: {$nom}", $id);
            
            $database->commit();
            
            showMessage('success', 'Type de frais modifiÃ© avec succÃ¨s !');
            redirectTo('index.php');
            
        } catch (Exception $e) {
            $database->rollback();
            $errors[] = 'Erreur lors de la modification : ' . $e->getMessage();
        }
    }
}

include '../../../../includes/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">
        <i class="fas fa-edit me-2"></i>
        Modifier un type de frais
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
                AnnÃ©e: <?php echo htmlspecialchars($type_frais['annee'] ?? 'Non dÃ©finie'); ?>
            </span>
        </div>
    </div>
</div>

<?php if (!empty($errors)): ?>
    <div class="alert alert-danger">
        <h6><i class="fas fa-exclamation-triangle me-2"></i>Erreurs dÃ©tectÃ©es :</h6>
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
                                   placeholder="Ex: Inscription, MensualitÃ©, Examen..."
                                   value="<?php echo htmlspecialchars($_POST['nom'] ?? $type_frais['nom']); ?>"
                                   maxlength="100"
                                   required>
                            <div class="form-text">
                                Nom unique pour cette annÃ©e scolaire (max 100 caractÃ¨res)
                                <?php if ($usage_count > 0): ?>
                                    <br><span class="text-warning">
                                        <i class="fas fa-exclamation-triangle me-1"></i>
                                        Ce type est utilisÃ© dans <?php echo $usage_count; ?> configuration(s)
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="col-md-3 mb-3">
                            <label for="priorite" class="form-label">
                                PrioritÃ© <span class="text-danger">*</span>
                            </label>
                            <input type="number" 
                                   class="form-control" 
                                   id="priorite" 
                                   name="priorite" 
                                   min="1" 
                                   max="100" 
                                   value="<?php echo htmlspecialchars($_POST['priorite'] ?? $type_frais['priorite']); ?>"
                                   required>
                            <div class="form-text">
                                Plus le chiffre est bas, plus la prioritÃ© est haute (1 = prioritÃ© maximale)
                            </div>
                        </div>
                        
                        <div class="col-md-3 mb-3">
                            <label for="actif" class="form-label">Statut</label>
                            <div class="form-check form-switch">
                                <input class="form-check-input" 
                                       type="checkbox" 
                                       id="actif" 
                                       name="actif" 
                                       <?php echo (($_POST['actif'] ?? $type_frais['actif']) ? 'checked' : ''); ?>>
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
                                  placeholder="Description dÃ©taillÃ©e du type de frais (optionnel)..."><?php echo prepareFormText($_POST['description'] ?? $type_frais['description']); ?></textarea>
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
        <!-- Informations sur l'utilisation -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-info-circle me-2"></i>
                    Informations
                </h5>
            </div>
            <div class="card-body">
                <table class="table table-borderless table-sm">
                    <tr>
                        <td class="fw-bold">ID:</td>
                        <td><?php echo $type_frais['id']; ?></td>
                    </tr>
                    <tr>
                        <td class="fw-bold">CrÃ©Ã© le:</td>
                        <td><?php echo formatDate($type_frais['date_creation']); ?></td>
                    </tr>
                    <tr>
                        <td class="fw-bold">DerniÃ¨re modification:</td>
                        <td>
                            <?php if ($type_frais['updated_at']): ?>
                                <?php echo formatDate($type_frais['updated_at']); ?>
                            <?php else: ?>
                                <span class="text-muted">Jamais modifiÃ©</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <td class="fw-bold">UtilisÃ© dans:</td>
                        <td>
                            <?php if ($usage_count > 0): ?>
                                <span class="badge bg-info"><?php echo $usage_count; ?> configuration(s)</span>
                            <?php else: ?>
                                <span class="text-muted">Aucune configuration</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                </table>
            </div>
        </div>
        
        <!-- Aide -->
        <div class="card mt-3">
            <div class="card-header">
                <h6 class="mb-0">
                    <i class="fas fa-question-circle me-2"></i>
                    Aide
                </h6>
            </div>
            <div class="card-body">
                <div class="alert alert-info">
                    <small>
                        <i class="fas fa-info-circle me-1"></i>
                        <strong>Modification du nom :</strong> Si ce type de frais est dÃ©jÃ  utilisÃ©, le nom ne peut pas Ãªtre modifiÃ©.
                    </small>
                </div>
                
                <div class="alert alert-warning">
                    <small>
                        <i class="fas fa-exclamation-triangle me-1"></i>
                        <strong>DÃ©sactivation :</strong> DÃ©sactiver un type de frais le rendra indisponible pour les nouvelles configurations.
                    </small>
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

// Compteur de caractÃ¨res pour le nom
document.getElementById('nom').addEventListener('input', function() {
    const maxLength = 100;
    const currentLength = this.value.length;
    const remaining = maxLength - currentLength;
    
    // CrÃ©er ou mettre Ã  jour l'indicateur
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
    
    indicator.textContent = `${currentLength}/${maxLength} caractÃ¨res`;
});
</script>

<?php include '../../../../includes/footer.php'; ?>



