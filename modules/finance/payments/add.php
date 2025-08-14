<?php
/**
 * Module de gestion financière - Ajouter un paiement
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

$page_title = 'Enregistrer un paiement';

// Obtenir l'année scolaire actuelle
$current_year = getCurrentAcademicYear();

if (!$current_year) {
    showMessage('error', 'Aucune année scolaire active.');
    redirectTo('../index.php');
}

// Récupérer les élèves inscrits
$eleves = $database->query(
    "SELECT e.id, e.nom, e.prenom, e.numero_matricule, c.nom as classe_nom, c.niveau
     FROM eleves e
     JOIN inscriptions i ON e.id = i.eleve_id
     JOIN classes c ON i.classe_id = c.id
     WHERE i.status = 'inscrit' AND i.annee_scolaire_id = ?
     ORDER BY e.nom, e.prenom",
    [$current_year['id']]
)->fetchAll();

$errors = [];
$success = false;

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validation des données
    $eleve_id = (int)($_POST['eleve_id'] ?? 0);
    $type_paiement = sanitizeInput($_POST['type_paiement'] ?? '');
    $montant = (float)($_POST['montant'] ?? 0);
    $mode_paiement = sanitizeInput($_POST['mode_paiement'] ?? '');
    $date_paiement = sanitizeInput($_POST['date_paiement'] ?? '');
    $description = sanitizeInput($_POST['description'] ?? '');
    $reference = sanitizeInput($_POST['reference'] ?? '');
    
    // Validation des champs obligatoires
    if (!$eleve_id) $errors[] = 'L\'élève est obligatoire.';
    if (empty($type_paiement)) $errors[] = 'Le type de paiement est obligatoire.';
    if ($montant <= 0) $errors[] = 'Le montant doit être supérieur à zéro.';
    if (empty($mode_paiement)) $errors[] = 'Le mode de paiement est obligatoire.';
    if (empty($date_paiement)) $errors[] = 'La date de paiement est obligatoire.';
    
    // Vérifier que l'élève existe et est inscrit
    if ($eleve_id) {
        $stmt = $database->query(
            "SELECT e.id FROM eleves e 
             JOIN inscriptions i ON e.id = i.eleve_id 
             WHERE e.id = ? AND i.status = 'inscrit' AND i.annee_scolaire_id = ?",
            [$eleve_id, $current_year['id']]
        );
        if (!$stmt->fetch()) {
            $errors[] = 'L\'élève sélectionné n\'est pas inscrit pour cette année scolaire.';
        }
    }
    
    // Validation de la date
    if (!empty($date_paiement) && !isValidDate($date_paiement)) {
        $errors[] = 'La date de paiement n\'est pas valide.';
    }
    
    // Validation du montant
    if ($montant > 10000000) { // 10 millions FC max
        $errors[] = 'Le montant ne peut pas dépasser 10 000 000 FC.';
    }
    
    // Si pas d'erreurs, enregistrer le paiement
    if (empty($errors)) {
        try {
            $database->beginTransaction();
            
            // Générer le numéro de reçu
            $numero_recu = generateReceiptNumber();
            
            // Insérer le paiement
            $sql = "INSERT INTO paiements (
                        eleve_id, annee_scolaire_id, recu_numero, type_paiement,
                        montant, mode_paiement, date_paiement, observation,
                        user_id
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $database->execute($sql, [
                $eleve_id, $current_year['id'], $numero_recu, $type_paiement,
                $montant, $mode_paiement, $date_paiement, $description,
                $_SESSION['user_id']
            ]);
            
            $paiement_id = $database->lastInsertId();
            
            $database->commit();
            
            showMessage('success', 'Paiement enregistré avec succès !');
            redirectTo('receipt.php?id=' . $paiement_id);
            
        } catch (Exception $e) {
            $database->rollback();
            $errors[] = 'Erreur lors de l\'enregistrement : ' . $e->getMessage();
        }
    }
}



include '../../../includes/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">
        <i class="fas fa-plus me-2"></i>
        Enregistrer un paiement
    </h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <a href="index.php" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-left me-1"></i>
            Retour à la liste
        </a>
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

<form method="POST" class="needs-validation" novalidate>
    <div class="row">
        <!-- Informations du paiement -->
        <div class="col-lg-8">
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-money-bill me-2"></i>
                        Informations du paiement
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="eleve_id" class="form-label">Élève <span class="text-danger">*</span></label>
                            <select class="form-select select2" id="eleve_id" name="eleve_id" required>
                                <option value="">Sélectionner un élève...</option>
                                <?php foreach ($eleves as $eleve): ?>
                                    <option value="<?php echo $eleve['id']; ?>" 
                                            <?php echo ($_POST['eleve_id'] ?? '') == $eleve['id'] ? 'selected' : ''; ?>
                                            data-classe="<?php echo htmlspecialchars($eleve['classe_nom']); ?>"
                                            data-niveau="<?php echo htmlspecialchars($eleve['niveau']); ?>">
                                        <?php echo htmlspecialchars($eleve['nom'] . ' ' . $eleve['prenom']); ?> 
                                        (<?php echo htmlspecialchars($eleve['numero_matricule']); ?>) - 
                                        <?php echo htmlspecialchars($eleve['classe_nom']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="type_paiement" class="form-label">Type de paiement <span class="text-danger">*</span></label>
                            <select class="form-select" id="type_paiement" name="type_paiement" required>
                                <option value="">Sélectionner un type...</option>
                                <option value="inscription" <?php echo ($_POST['type_paiement'] ?? '') === 'inscription' ? 'selected' : ''; ?>>Frais d'inscription</option>
                                <option value="mensualite" <?php echo ($_POST['type_paiement'] ?? '') === 'mensualite' ? 'selected' : ''; ?>>Mensualité</option>
                                <option value="examen" <?php echo ($_POST['type_paiement'] ?? '') === 'examen' ? 'selected' : ''; ?>>Frais d'examen</option>
                                <option value="uniforme" <?php echo ($_POST['type_paiement'] ?? '') === 'uniforme' ? 'selected' : ''; ?>>Uniforme</option>
                                <option value="transport" <?php echo ($_POST['type_paiement'] ?? '') === 'transport' ? 'selected' : ''; ?>>Transport</option>
                                <option value="cantine" <?php echo ($_POST['type_paiement'] ?? '') === 'cantine' ? 'selected' : ''; ?>>Cantine</option>
                                <option value="autre" <?php echo ($_POST['type_paiement'] ?? '') === 'autre' ? 'selected' : ''; ?>>Autre</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label for="montant" class="form-label">Montant (FC) <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <input type="number" 
                                       class="form-control" 
                                       id="montant" 
                                       name="montant" 
                                       min="1" 
                                       max="10000000"
                                       step="1"
                                       placeholder="Ex: 50000"
                                       value="<?php echo htmlspecialchars($_POST['montant'] ?? ''); ?>"
                                       required>
                                <span class="input-group-text">FC</span>
                            </div>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="mode_paiement" class="form-label">Mode de paiement <span class="text-danger">*</span></label>
                            <select class="form-select" id="mode_paiement" name="mode_paiement" required>
                                <option value="">Sélectionner...</option>
                                <option value="especes" <?php echo ($_POST['mode_paiement'] ?? '') === 'especes' ? 'selected' : ''; ?>>Espèces</option>
                                <option value="cheque" <?php echo ($_POST['mode_paiement'] ?? '') === 'cheque' ? 'selected' : ''; ?>>Chèque</option>
                                <option value="virement" <?php echo ($_POST['mode_paiement'] ?? '') === 'virement' ? 'selected' : ''; ?>>Virement bancaire</option>
                                <option value="mobile_money" <?php echo ($_POST['mode_paiement'] ?? '') === 'mobile_money' ? 'selected' : ''; ?>>Mobile Money</option>
                            </select>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="date_paiement" class="form-label">Date de paiement <span class="text-danger">*</span></label>
                            <input type="date" 
                                   class="form-control" 
                                   id="date_paiement" 
                                   name="date_paiement" 
                                   value="<?php echo htmlspecialchars($_POST['date_paiement'] ?? date('Y-m-d')); ?>"
                                   max="<?php echo date('Y-m-d'); ?>"
                                   required>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="reference" class="form-label">Référence</label>
                            <input type="text" 
                                   class="form-control" 
                                   id="reference" 
                                   name="reference" 
                                   placeholder="N° chèque, référence virement..."
                                   value="<?php echo htmlspecialchars($_POST['reference'] ?? ''); ?>">
                            <div class="form-text">Optionnel - pour chèques, virements, etc.</div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="description" class="form-label">Description</label>
                            <input type="text" 
                                   class="form-control" 
                                   id="description" 
                                   name="description" 
                                   placeholder="Description optionnelle..."
                                   value="<?php echo htmlspecialchars($_POST['description'] ?? ''); ?>">
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Informations complémentaires -->
        <div class="col-lg-4">
            <!-- Informations de l'élève sélectionné -->
            <div class="card mb-4" id="eleve-info" style="display: none;">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-user me-2"></i>
                        Informations élève
                    </h5>
                </div>
                <div class="card-body">
                    <div id="eleve-details">
                        <!-- Rempli par JavaScript -->
                    </div>
                </div>
            </div>
            
            <!-- Montants suggérés -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-calculator me-2"></i>
                        Montants suggérés
                    </h5>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2" id="montants-suggeres">
                        <button type="button" class="btn btn-outline-primary btn-montant" data-montant="25000">
                            Inscription : 25 000 FC
                        </button>
                        <button type="button" class="btn btn-outline-success btn-montant" data-montant="50000">
                            Mensualité : 50 000 FC
                        </button>
                        <button type="button" class="btn btn-outline-warning btn-montant" data-montant="15000">
                            Examen : 15 000 FC
                        </button>
                        <button type="button" class="btn btn-outline-info btn-montant" data-montant="75000">
                            Inscription + 1 mois : 75 000 FC
                        </button>
                    </div>
                    <small class="text-muted mt-2 d-block">
                        Cliquez sur un montant pour le sélectionner
                    </small>
                </div>
            </div>
            
            <!-- Aide -->
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-info-circle me-2"></i>
                        Aide
                    </h5>
                </div>
                <div class="card-body">
                    <h6>Types de paiement :</h6>
                    <ul class="list-unstyled small">
                        <li><strong>Inscription :</strong> Frais d'inscription annuelle</li>
                        <li><strong>Mensualité :</strong> Frais mensuels de scolarité</li>
                        <li><strong>Examen :</strong> Frais d'examens et compositions</li>
                        <li><strong>Autre :</strong> Autres frais (uniforme, transport, etc.)</li>
                    </ul>
                    
                    <h6 class="mt-3">Modes de paiement :</h6>
                    <ul class="list-unstyled small">
                        <li><strong>Espèces :</strong> Paiement en liquide</li>
                        <li><strong>Chèque :</strong> Paiement par chèque bancaire</li>
                        <li><strong>Virement :</strong> Virement bancaire</li>
                        <li><strong>Mobile Money :</strong> Airtel Money, Orange Money, etc.</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Boutons d'action -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <a href="index.php" class="btn btn-secondary">
                            <i class="fas fa-times me-1"></i>
                            Annuler
                        </a>
                        <div>
                            <button type="reset" class="btn btn-outline-secondary me-2">
                                <i class="fas fa-undo me-1"></i>
                                Réinitialiser
                            </button>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-1"></i>
                                Enregistrer le paiement
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</form>

<script>
// Affichage des informations de l'élève sélectionné
document.getElementById('eleve_id').addEventListener('change', function() {
    const eleveInfo = document.getElementById('eleve-info');
    const eleveDetails = document.getElementById('eleve-details');
    
    if (this.value) {
        const option = this.options[this.selectedIndex];
        const classe = option.dataset.classe;
        const niveau = option.dataset.niveau;
        const nom = option.text.split(' (')[0];
        
        eleveDetails.innerHTML = `
            <table class="table table-borderless table-sm">
                <tr>
                    <td><strong>Nom :</strong></td>
                    <td>${nom}</td>
                </tr>
                <tr>
                    <td><strong>Classe :</strong></td>
                    <td><span class="badge bg-primary">${classe}</span></td>
                </tr>
                <tr>
                    <td><strong>Niveau :</strong></td>
                    <td>${niveau}</td>
                </tr>
            </table>
        `;
        
        eleveInfo.style.display = 'block';
    } else {
        eleveInfo.style.display = 'none';
    }
});

// Sélection des montants suggérés
document.querySelectorAll('.btn-montant').forEach(function(btn) {
    btn.addEventListener('click', function() {
        const montant = this.dataset.montant;
        document.getElementById('montant').value = montant;
        
        // Highlight temporaire
        this.classList.add('active');
        setTimeout(() => {
            this.classList.remove('active');
        }, 1000);
    });
});

// Formatage du montant en temps réel
document.getElementById('montant').addEventListener('input', function() {
    let value = this.value.replace(/\D/g, '');
    if (value) {
        this.value = parseInt(value);
    }
});

// Gestion des champs conditionnels selon le mode de paiement
document.getElementById('mode_paiement').addEventListener('change', function() {
    const referenceField = document.getElementById('reference');
    const referenceLabel = referenceField.previousElementSibling;
    
    switch(this.value) {
        case 'cheque':
            referenceLabel.innerHTML = 'Numéro de chèque <span class="text-danger">*</span>';
            referenceField.required = true;
            referenceField.placeholder = 'Numéro du chèque';
            break;
        case 'virement':
            referenceLabel.innerHTML = 'Référence virement <span class="text-danger">*</span>';
            referenceField.required = true;
            referenceField.placeholder = 'Référence du virement';
            break;
        case 'mobile_money':
            referenceLabel.innerHTML = 'ID transaction';
            referenceField.required = false;
            referenceField.placeholder = 'ID de la transaction';
            break;
        default:
            referenceLabel.innerHTML = 'Référence';
            referenceField.required = false;
            referenceField.placeholder = 'Référence optionnelle';
    }
});

// Validation du formulaire
document.querySelector('form').addEventListener('submit', function(e) {
    const requiredFields = this.querySelectorAll('[required]');
    let isValid = true;
    
    requiredFields.forEach(function(field) {
        if (!field.value.trim()) {
            field.classList.add('is-invalid');
            isValid = false;
        } else {
            field.classList.remove('is-invalid');
        }
    });
    
    if (!isValid) {
        e.preventDefault();
        showError('Veuillez remplir tous les champs obligatoires.');
    }
});
</script>

<?php include '../../../includes/footer.php'; ?>
