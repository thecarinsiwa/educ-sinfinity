<?php
/**
 * Module de gestion financière - Ajouter une dépense
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

$page_title = 'Ajouter une dépense';

// Obtenir l'année scolaire actuelle
$current_year = getCurrentAcademicYear();

$errors = [];
$success = false;

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validation des données
    $libelle = sanitizeInput($_POST['libelle'] ?? '');
    $description = sanitizeInput($_POST['description'] ?? '');
    $montant = (float)($_POST['montant'] ?? 0);
    $type_depense = sanitizeInput($_POST['type_depense'] ?? '');
    $date_depense = sanitizeInput($_POST['date_depense'] ?? '');
    $fournisseur = sanitizeInput($_POST['fournisseur'] ?? '');
    $numero_facture = sanitizeInput($_POST['numero_facture'] ?? '');
    $mode_paiement = sanitizeInput($_POST['mode_paiement'] ?? '');
    $statut = sanitizeInput($_POST['statut'] ?? 'en_attente');
    
    // Validation des champs obligatoires
    if (empty($libelle)) $errors[] = 'Le libellé est obligatoire.';
    if ($montant <= 0) $errors[] = 'Le montant doit être supérieur à 0.';
    if (empty($type_depense)) $errors[] = 'Le type de dépense est obligatoire.';
    if (empty($date_depense)) $errors[] = 'La date de dépense est obligatoire.';
    if (empty($mode_paiement)) $errors[] = 'Le mode de paiement est obligatoire.';
    
    // Validation de la date
    if (!empty($date_depense) && !isValidDate($date_depense)) {
        $errors[] = 'La date de dépense n\'est pas valide.';
    }
    
    // Validation du montant
    if ($montant > 100000000) { // 100 millions FC max
        $errors[] = 'Le montant ne peut pas dépasser 100 000 000 FC.';
    }
    
    // Si pas d'erreurs, insérer la dépense
    if (empty($errors)) {
        try {
            $database->beginTransaction();
            
            // Insérer la dépense
            $sql = "INSERT INTO depenses (
                        libelle, description, montant, type_depense, date_depense,
                        fournisseur, numero_facture, mode_paiement, statut,
                        annee_scolaire_id, user_id
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
            $database->execute($sql, [
                $libelle, $description, $montant, $type_depense, $date_depense,
                $fournisseur, $numero_facture, $mode_paiement, $statut,
                $current_year['id'], $_SESSION['user_id']
            ]);
            
            $database->commit();
            
            showMessage('success', 'Dépense ajoutée avec succès !');
            redirectTo('index.php');
            
        } catch (Exception $e) {
            $database->rollback();
            $errors[] = 'Erreur lors de l\'ajout : ' . $e->getMessage();
        }
    }
}

include '../../../includes/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">
        <i class="fas fa-plus me-2"></i>
        Ajouter une dépense
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
                    Informations de la dépense
                </h5>
            </div>
            <div class="card-body">
                <form method="POST" class="needs-validation" novalidate>
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
                                   placeholder="Ex: Achat de fournitures scolaires"
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
                                   max="100000000" 
                                   step="0.01" 
                                   required>
                            <div class="invalid-feedback">
                                Veuillez saisir un montant valide.
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="type_depense" class="form-label">
                                Type de dépense <span class="text-danger">*</span>
                            </label>
                            <select class="form-select" id="type_depense" name="type_depense" required>
                                <option value="">Sélectionner le type</option>
                                <option value="salaires" <?php echo (($_POST['type_depense'] ?? '') === 'salaires') ? 'selected' : ''; ?>>
                                    Salaires et charges
                                </option>
                                <option value="fournitures" <?php echo (($_POST['type_depense'] ?? '') === 'fournitures') ? 'selected' : ''; ?>>
                                    Fournitures scolaires
                                </option>
                                <option value="maintenance" <?php echo (($_POST['type_depense'] ?? '') === 'maintenance') ? 'selected' : ''; ?>>
                                    Maintenance et réparations
                                </option>
                                <option value="utilities" <?php echo (($_POST['type_depense'] ?? '') === 'utilities') ? 'selected' : ''; ?>>
                                    Services publics (eau, électricité)
                                </option>
                                <option value="transport" <?php echo (($_POST['type_depense'] ?? '') === 'transport') ? 'selected' : ''; ?>>
                                    Transport
                                </option>
                                <option value="autre" <?php echo (($_POST['type_depense'] ?? '') === 'autre') ? 'selected' : ''; ?>>
                                    Autre
                                </option>
                            </select>
                            <div class="invalid-feedback">
                                Veuillez sélectionner un type de dépense.
                            </div>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="date_depense" class="form-label">
                                Date de dépense <span class="text-danger">*</span>
                            </label>
                            <input type="date" 
                                   class="form-control" 
                                   id="date_depense" 
                                   name="date_depense" 
                                   value="<?php echo htmlspecialchars($_POST['date_depense'] ?? date('Y-m-d')); ?>"
                                   required>
                            <div class="invalid-feedback">
                                Veuillez sélectionner une date de dépense.
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="fournisseur" class="form-label">Fournisseur</label>
                            <input type="text" 
                                   class="form-control" 
                                   id="fournisseur" 
                                   name="fournisseur" 
                                   value="<?php echo htmlspecialchars($_POST['fournisseur'] ?? ''); ?>"
                                   placeholder="Nom du fournisseur ou prestataire">
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="numero_facture" class="form-label">Numéro de facture</label>
                            <input type="text" 
                                   class="form-control" 
                                   id="numero_facture" 
                                   name="numero_facture" 
                                   value="<?php echo htmlspecialchars($_POST['numero_facture'] ?? ''); ?>"
                                   placeholder="Numéro de facture ou reçu">
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="mode_paiement" class="form-label">
                                Mode de paiement <span class="text-danger">*</span>
                            </label>
                            <select class="form-select" id="mode_paiement" name="mode_paiement" required>
                                <option value="">Sélectionner le mode</option>
                                <option value="especes" <?php echo (($_POST['mode_paiement'] ?? '') === 'especes') ? 'selected' : ''; ?>>
                                    Espèces
                                </option>
                                <option value="cheque" <?php echo (($_POST['mode_paiement'] ?? '') === 'cheque') ? 'selected' : ''; ?>>
                                    Chèque
                                </option>
                                <option value="virement" <?php echo (($_POST['mode_paiement'] ?? '') === 'virement') ? 'selected' : ''; ?>>
                                    Virement bancaire
                                </option>
                                <option value="mobile_money" <?php echo (($_POST['mode_paiement'] ?? '') === 'mobile_money') ? 'selected' : ''; ?>>
                                    Mobile Money
                                </option>
                            </select>
                            <div class="invalid-feedback">
                                Veuillez sélectionner un mode de paiement.
                            </div>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="statut" class="form-label">Statut</label>
                            <select class="form-select" id="statut" name="statut">
                                <option value="en_attente" <?php echo (($_POST['statut'] ?? 'en_attente') === 'en_attente') ? 'selected' : ''; ?>>
                                    En attente de paiement
                                </option>
                                <option value="payee" <?php echo (($_POST['statut'] ?? '') === 'payee') ? 'selected' : ''; ?>>
                                    Payée
                                </option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="description" class="form-label">Description</label>
                        <textarea class="form-control" 
                                  id="description" 
                                  name="description" 
                                  rows="4"
                                  placeholder="Description détaillée de la dépense..."><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
                    </div>
                    
                    <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                        <a href="index.php" class="btn btn-outline-secondary me-md-2">
                            <i class="fas fa-times me-1"></i>
                            Annuler
                        </a>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-1"></i>
                            Enregistrer la dépense
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
                <h6>Types de dépenses :</h6>
                <ul class="small">
                    <li><strong>Salaires :</strong> Rémunérations et charges sociales</li>
                    <li><strong>Fournitures :</strong> Matériel pédagogique et administratif</li>
                    <li><strong>Maintenance :</strong> Réparations et entretien</li>
                    <li><strong>Services publics :</strong> Eau, électricité, internet</li>
                    <li><strong>Transport :</strong> Frais de déplacement</li>
                    <li><strong>Autre :</strong> Autres dépenses spécifiques</li>
                </ul>
                
                <h6 class="mt-3">Statuts :</h6>
                <ul class="small">
                    <li><strong>En attente :</strong> Dépense prévue mais non payée</li>
                    <li><strong>Payée :</strong> Dépense effectivement réglée</li>
                </ul>
                
                <div class="alert alert-info mt-3">
                    <small>
                        <i class="fas fa-info-circle me-1"></i>
                        Conservez tous les justificatifs de paiement.
                    </small>
                </div>
            </div>
        </div>

        <!-- Statistiques rapides -->
        <div class="card mt-3">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-chart-bar me-2"></i>
                    Statistiques du mois
                </h5>
            </div>
            <div class="card-body">
                <?php
                // Statistiques du mois en cours
                try {
                    $stats_sql = "SELECT 
                                    COUNT(*) as total_depenses,
                                    SUM(CASE WHEN statut = 'payee' THEN montant ELSE 0 END) as total_paye,
                                    SUM(CASE WHEN statut = 'en_attente' THEN montant ELSE 0 END) as total_attente
                                  FROM depenses 
                                  WHERE MONTH(date_depense) = MONTH(CURRENT_DATE()) 
                                  AND YEAR(date_depense) = YEAR(CURRENT_DATE())
                                  AND annee_scolaire_id = ?";
                    
                    $stats = $database->query($stats_sql, [$current_year['id']])->fetch();
                } catch (Exception $e) {
                    $stats = ['total_depenses' => 0, 'total_paye' => 0, 'total_attente' => 0];
                }
                ?>
                
                <div class="row text-center">
                    <div class="col-12 mb-2">
                        <h6 class="text-primary"><?php echo $stats['total_depenses'] ?? 0; ?></h6>
                        <small class="text-muted">Dépenses ce mois</small>
                    </div>
                    <div class="col-6">
                        <h6 class="text-success"><?php echo formatMoney($stats['total_paye'] ?? 0); ?></h6>
                        <small class="text-muted">Payé</small>
                    </div>
                    <div class="col-6">
                        <h6 class="text-warning"><?php echo formatMoney($stats['total_attente'] ?? 0); ?></h6>
                        <small class="text-muted">En attente</small>
                    </div>
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

// Auto-génération du libellé basé sur le type
document.getElementById('type_depense').addEventListener('change', function() {
    const libelle = document.getElementById('libelle');
    
    if (this.value && !libelle.value) {
        const types = {
            'salaires': 'Paiement des salaires',
            'fournitures': 'Achat de fournitures',
            'maintenance': 'Frais de maintenance',
            'utilities': 'Facture services publics',
            'transport': 'Frais de transport',
            'autre': 'Autre dépense'
        };
        
        const mois = new Date().toLocaleDateString('fr-FR', { month: 'long', year: 'numeric' });
        libelle.value = types[this.value] + ' - ' + mois;
    }
});
</script>

<?php include '../../../includes/footer.php'; ?>
