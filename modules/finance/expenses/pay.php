<?php
/**
 * Module de gestion financière - Marquer une dépense comme payée
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

// Récupérer l'ID de la dépense
$id = (int)($_GET['id'] ?? 0);
if (!$id) {
    showMessage('error', 'Dépense non spécifiée.');
    redirectTo('index.php');
}

// Récupérer les informations de la dépense
$sql = "SELECT * FROM depenses WHERE id = ?";
$depense = $database->query($sql, [$id])->fetch();

if (!$depense) {
    showMessage('error', 'Dépense non trouvée.');
    redirectTo('index.php');
}

if ($depense['statut'] === 'payee') {
    showMessage('info', 'Cette dépense est déjà marquée comme payée.');
    redirectTo('view.php?id=' . $id);
}

$errors = [];

// Traitement de la confirmation
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $date_paiement = sanitizeInput($_POST['date_paiement'] ?? '');
    $mode_paiement = sanitizeInput($_POST['mode_paiement'] ?? '');
    $reference_paiement = sanitizeInput($_POST['reference_paiement'] ?? '');
    $observation = sanitizeInput($_POST['observation'] ?? '');
    
    // Validation
    if (empty($date_paiement)) {
        $errors[] = 'La date de paiement est obligatoire.';
    }
    
    if (empty($mode_paiement)) {
        $errors[] = 'Le mode de paiement est obligatoire.';
    }
    
    // Validation de la date
    if (!empty($date_paiement) && !isValidDate($date_paiement)) {
        $errors[] = 'La date de paiement n\'est pas valide.';
    }
    
    // Si pas d'erreurs, marquer comme payée
    if (empty($errors)) {
        try {
            $database->beginTransaction();
            
            // Mettre à jour la dépense
            $sql = "UPDATE depenses SET 
                        statut = 'payee',
                        mode_paiement = ?,
                        description = CONCAT(COALESCE(description, ''), '\n\nPayé le ', ?, ' - ', COALESCE(?, '')),
                        updated_at = CURRENT_TIMESTAMP
                    WHERE id = ?";
            
            $database->execute($sql, [
                $mode_paiement,
                $date_paiement,
                $observation,
                $id
            ]);
            
            $database->commit();
            
            showMessage('success', 'Dépense marquée comme payée avec succès !');
            redirectTo('view.php?id=' . $id);
            
        } catch (Exception $e) {
            $database->rollback();
            $errors[] = 'Erreur lors de la mise à jour : ' . $e->getMessage();
        }
    }
}

$page_title = 'Marquer comme payée - ' . $depense['libelle'];

include '../../../includes/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">
        <i class="fas fa-check-circle me-2 text-success"></i>
        Marquer comme payée
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
        <!-- Informations de la dépense -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-info-circle me-2"></i>
                    Dépense à marquer comme payée
                </h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <table class="table table-borderless">
                            <tr>
                                <td class="fw-bold" style="width: 150px;">Libellé :</td>
                                <td><?php echo htmlspecialchars($depense['libelle']); ?></td>
                            </tr>
                            <tr>
                                <td class="fw-bold">Type :</td>
                                <td><?php echo ucfirst($depense['type_depense']); ?></td>
                            </tr>
                            <tr>
                                <td class="fw-bold">Montant :</td>
                                <td>
                                    <span class="fs-5 text-danger fw-bold">
                                        <?php echo formatMoney($depense['montant']); ?>
                                    </span>
                                </td>
                            </tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <table class="table table-borderless">
                            <tr>
                                <td class="fw-bold" style="width: 150px;">Date prévue :</td>
                                <td><?php echo formatDate($depense['date_depense']); ?></td>
                            </tr>
                            <tr>
                                <td class="fw-bold">Fournisseur :</td>
                                <td><?php echo htmlspecialchars($depense['fournisseur'] ?: '-'); ?></td>
                            </tr>
                            <tr>
                                <td class="fw-bold">Statut actuel :</td>
                                <td>
                                    <span class="badge bg-warning">En attente</span>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Formulaire de confirmation -->
        <div class="card">
            <div class="card-header bg-success text-white">
                <h5 class="mb-0">
                    <i class="fas fa-check-circle me-2"></i>
                    Confirmer le paiement
                </h5>
            </div>
            <div class="card-body">
                <form method="POST" class="needs-validation" novalidate>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="date_paiement" class="form-label">
                                Date de paiement <span class="text-danger">*</span>
                            </label>
                            <input type="date" 
                                   class="form-control" 
                                   id="date_paiement" 
                                   name="date_paiement" 
                                   value="<?php echo htmlspecialchars($_POST['date_paiement'] ?? date('Y-m-d')); ?>"
                                   required>
                            <div class="invalid-feedback">
                                Veuillez sélectionner la date de paiement.
                            </div>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="mode_paiement" class="form-label">
                                Mode de paiement <span class="text-danger">*</span>
                            </label>
                            <select class="form-select" id="mode_paiement" name="mode_paiement" required>
                                <option value="">Sélectionner le mode</option>
                                <option value="especes" <?php echo (($_POST['mode_paiement'] ?? $depense['mode_paiement']) === 'especes') ? 'selected' : ''; ?>>
                                    Espèces
                                </option>
                                <option value="cheque" <?php echo (($_POST['mode_paiement'] ?? $depense['mode_paiement']) === 'cheque') ? 'selected' : ''; ?>>
                                    Chèque
                                </option>
                                <option value="virement" <?php echo (($_POST['mode_paiement'] ?? $depense['mode_paiement']) === 'virement') ? 'selected' : ''; ?>>
                                    Virement bancaire
                                </option>
                                <option value="mobile_money" <?php echo (($_POST['mode_paiement'] ?? $depense['mode_paiement']) === 'mobile_money') ? 'selected' : ''; ?>>
                                    Mobile Money
                                </option>
                            </select>
                            <div class="invalid-feedback">
                                Veuillez sélectionner un mode de paiement.
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="reference_paiement" class="form-label">Référence de paiement</label>
                        <input type="text" 
                               class="form-control" 
                               id="reference_paiement" 
                               name="reference_paiement" 
                               value="<?php echo htmlspecialchars($_POST['reference_paiement'] ?? ''); ?>"
                               placeholder="Numéro de chèque, référence virement, etc.">
                    </div>
                    
                    <div class="mb-3">
                        <label for="observation" class="form-label">Observation</label>
                        <textarea class="form-control" 
                                  id="observation" 
                                  name="observation" 
                                  rows="3"
                                  placeholder="Observation sur le paiement..."><?php echo htmlspecialchars($_POST['observation'] ?? ''); ?></textarea>
                    </div>
                    
                    <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                        <a href="view.php?id=<?php echo $id; ?>" class="btn btn-outline-secondary me-md-2">
                            <i class="fas fa-arrow-left me-1"></i>
                            Retour sans marquer
                        </a>
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-check-circle me-1"></i>
                            Confirmer le paiement
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
                <h6>Marquer comme payée :</h6>
                <ul class="small">
                    <li>Confirme que la dépense a été réglée</li>
                    <li>Met à jour le statut dans le système</li>
                    <li>Enregistre la date et le mode de paiement</li>
                    <li>Ajoute une note dans l'historique</li>
                </ul>
                
                <h6 class="mt-3">Informations requises :</h6>
                <ul class="small">
                    <li><strong>Date :</strong> Date effective du paiement</li>
                    <li><strong>Mode :</strong> Comment le paiement a été effectué</li>
                    <li><strong>Référence :</strong> Numéro de transaction (optionnel)</li>
                </ul>
                
                <div class="alert alert-warning mt-3">
                    <small>
                        <i class="fas fa-exclamation-triangle me-1"></i>
                        Cette action ne peut pas être annulée facilement.
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
</script>

<?php include '../../../includes/footer.php'; ?>
