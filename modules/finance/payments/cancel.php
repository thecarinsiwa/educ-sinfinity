<?php
/**
 * Module de gestion financière - Annuler un paiement
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

// Récupérer l'ID du paiement
$id = (int)($_GET['id'] ?? 0);
if (!$id) {
    showMessage('error', 'Paiement non spécifié.');
    redirectTo('index.php');
}

// Récupérer les informations du paiement
$sql = "SELECT p.*, 
               e.nom, e.prenom, e.numero_matricule,
               c.nom as classe_nom, c.niveau
        FROM paiements p
        JOIN eleves e ON p.eleve_id = e.id
        JOIN inscriptions i ON e.id = i.eleve_id AND i.annee_scolaire_id = p.annee_scolaire_id
        JOIN classes c ON i.classe_id = c.id
        WHERE p.id = ?";

$paiement = $database->query($sql, [$id])->fetch();

if (!$paiement) {
    showMessage('error', 'Paiement non trouvé.');
    redirectTo('index.php');
}

$page_title = 'Annuler le paiement - ' . $paiement['recu_numero'];

$errors = [];
$success = false;

// Traitement de l'annulation
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $motif_annulation = sanitizeInput($_POST['motif_annulation'] ?? '');
    $confirmation = $_POST['confirmation'] ?? '';
    
    // Validation
    if (empty($motif_annulation)) {
        $errors[] = 'Le motif d\'annulation est obligatoire.';
    }
    
    if ($confirmation !== 'ANNULER') {
        $errors[] = 'Veuillez taper "ANNULER" pour confirmer l\'annulation.';
    }
    
    // Si pas d'erreurs, procéder à l'annulation
    if (empty($errors)) {
        try {
            $database->beginTransaction();
            
            // Vérifier si la table a une colonne statut
            $columns = $database->query("DESCRIBE paiements")->fetchAll();
            $has_statut = false;
            foreach ($columns as $col) {
                if ($col['Field'] === 'statut') {
                    $has_statut = true;
                    break;
                }
            }
            
            if ($has_statut) {
                // Mettre à jour le statut si la colonne existe
                $sql = "UPDATE paiements SET 
                            statut = 'annule',
                            observation = CONCAT(COALESCE(observation, ''), '\n\nANNULÉ le ', NOW(), ' - Motif: ', ?),
                            updated_at = CURRENT_TIMESTAMP
                        WHERE id = ?";
                $database->execute($sql, [$motif_annulation, $id]);
            } else {
                // Sinon, juste ajouter le motif dans l'observation
                $sql = "UPDATE paiements SET 
                            observation = CONCAT(COALESCE(observation, ''), '\n\nANNULÉ le ', NOW(), ' - Motif: ', ?),
                            updated_at = CURRENT_TIMESTAMP
                        WHERE id = ?";
                $database->execute($sql, [$motif_annulation, $id]);
            }
            
            // Enregistrer l'action dans les logs si la table existe
            try {
                $log_sql = "INSERT INTO user_actions_log (user_id, action, table_name, record_id, details) 
                           VALUES (?, 'CANCEL_PAYMENT', 'paiements', ?, ?)";
                $database->execute($log_sql, [
                    $_SESSION['user_id'], 
                    $id, 
                    "Annulation du paiement {$paiement['recu_numero']} - Motif: {$motif_annulation}"
                ]);
            } catch (Exception $e) {
                // Table de logs n'existe pas, continuer sans erreur
            }
            
            $database->commit();
            
            showMessage('success', 'Paiement annulé avec succès !');
            redirectTo('index.php');
            
        } catch (Exception $e) {
            $database->rollback();
            $errors[] = 'Erreur lors de l\'annulation : ' . $e->getMessage();
        }
    }
}

include '../../../includes/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">
        <i class="fas fa-times-circle me-2 text-danger"></i>
        Annuler le paiement
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
        <!-- Avertissement -->
        <div class="alert alert-warning">
            <h5 class="alert-heading">
                <i class="fas fa-exclamation-triangle me-2"></i>
                Attention !
            </h5>
            <p>Vous êtes sur le point d'annuler ce paiement. Cette action :</p>
            <ul class="mb-0">
                <li>Marquera le paiement comme annulé</li>
                <li>Ajoutera une note d'annulation dans l'observation</li>
                <li>Sera enregistrée dans les logs du système</li>
                <li><strong>Ne pourra pas être annulée</strong></li>
            </ul>
        </div>

        <!-- Informations du paiement -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-receipt me-2"></i>
                    Informations du paiement à annuler
                </h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <table class="table table-borderless">
                            <tr>
                                <td class="fw-bold" style="width: 150px;">N° Reçu :</td>
                                <td>
                                    <span class="badge bg-primary fs-6">
                                        <?php echo htmlspecialchars($paiement['recu_numero']); ?>
                                    </span>
                                </td>
                            </tr>
                            <tr>
                                <td class="fw-bold">Élève :</td>
                                <td><?php echo htmlspecialchars($paiement['nom'] . ' ' . $paiement['prenom']); ?></td>
                            </tr>
                            <tr>
                                <td class="fw-bold">Classe :</td>
                                <td><?php echo htmlspecialchars($paiement['classe_nom']); ?></td>
                            </tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <table class="table table-borderless">
                            <tr>
                                <td class="fw-bold" style="width: 150px;">Date :</td>
                                <td><?php echo formatDate($paiement['date_paiement']); ?></td>
                            </tr>
                            <tr>
                                <td class="fw-bold">Type :</td>
                                <td><?php echo ucfirst($paiement['type_paiement']); ?></td>
                            </tr>
                            <tr>
                                <td class="fw-bold">Montant :</td>
                                <td>
                                    <span class="fs-5 text-success fw-bold">
                                        <?php echo formatMoney($paiement['montant']); ?>
                                    </span>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Formulaire d'annulation -->
        <div class="card">
            <div class="card-header bg-danger text-white">
                <h5 class="mb-0">
                    <i class="fas fa-times-circle me-2"></i>
                    Confirmer l'annulation
                </h5>
            </div>
            <div class="card-body">
                <form method="POST" class="needs-validation" novalidate>
                    <div class="mb-3">
                        <label for="motif_annulation" class="form-label">
                            Motif de l'annulation <span class="text-danger">*</span>
                        </label>
                        <textarea class="form-control" 
                                  id="motif_annulation" 
                                  name="motif_annulation" 
                                  rows="4"
                                  placeholder="Expliquez pourquoi ce paiement doit être annulé..."
                                  required><?php echo htmlspecialchars($_POST['motif_annulation'] ?? ''); ?></textarea>
                        <div class="invalid-feedback">
                            Veuillez saisir le motif de l'annulation.
                        </div>
                    </div>
                    
                    <div class="mb-4">
                        <label for="confirmation" class="form-label">
                            Confirmation <span class="text-danger">*</span>
                        </label>
                        <input type="text" 
                               class="form-control" 
                               id="confirmation" 
                               name="confirmation" 
                               placeholder="Tapez ANNULER pour confirmer"
                               required>
                        <div class="form-text">
                            Pour confirmer l'annulation, tapez exactement : <strong>ANNULER</strong>
                        </div>
                        <div class="invalid-feedback">
                            Veuillez taper "ANNULER" pour confirmer.
                        </div>
                    </div>
                    
                    <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                        <a href="view.php?id=<?php echo $id; ?>" class="btn btn-outline-secondary me-md-2">
                            <i class="fas fa-arrow-left me-1"></i>
                            Retour sans annuler
                        </a>
                        <button type="submit" class="btn btn-danger">
                            <i class="fas fa-times-circle me-1"></i>
                            Confirmer l'annulation
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
                <h6>Quand annuler un paiement ?</h6>
                <ul class="small">
                    <li>Erreur de saisie</li>
                    <li>Paiement en double</li>
                    <li>Demande de remboursement</li>
                    <li>Fraude détectée</li>
                </ul>
                
                <h6 class="mt-3">Conséquences :</h6>
                <ul class="small">
                    <li>Le paiement sera marqué comme annulé</li>
                    <li>Il n'apparaîtra plus dans les totaux</li>
                    <li>L'action sera tracée</li>
                    <li>Le reçu restera accessible</li>
                </ul>
                
                <div class="alert alert-info mt-3">
                    <small>
                        <i class="fas fa-info-circle me-1"></i>
                        En cas de doute, contactez votre responsable financier.
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
                var confirmation = document.getElementById('confirmation').value;
                if (confirmation !== 'ANNULER') {
                    event.preventDefault();
                    event.stopPropagation();
                    document.getElementById('confirmation').setCustomValidity('Veuillez taper exactement "ANNULER"');
                } else {
                    document.getElementById('confirmation').setCustomValidity('');
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

// Validation en temps réel du champ confirmation
document.getElementById('confirmation').addEventListener('input', function() {
    if (this.value === 'ANNULER') {
        this.setCustomValidity('');
        this.classList.remove('is-invalid');
        this.classList.add('is-valid');
    } else {
        this.setCustomValidity('Veuillez taper exactement "ANNULER"');
        this.classList.remove('is-valid');
        this.classList.add('is-invalid');
    }
});
</script>

<?php include '../../../includes/footer.php'; ?>
