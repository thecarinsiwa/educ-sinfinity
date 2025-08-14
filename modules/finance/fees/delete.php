<?php
/**
 * Module de gestion financière - Supprimer un frais scolaire
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

// Récupérer l'ID du frais
$id = (int)($_GET['id'] ?? 0);
if (!$id) {
    showMessage('error', 'Frais non spécifié.');
    redirectTo('index.php');
}

// Récupérer les informations du frais
$sql = "SELECT f.*, c.nom as classe_nom, c.niveau
        FROM frais_scolaires f
        JOIN classes c ON f.classe_id = c.id
        WHERE f.id = ?";

$frais = $database->query($sql, [$id])->fetch();

if (!$frais) {
    showMessage('error', 'Frais non trouvé.');
    redirectTo('index.php');
}

$page_title = 'Supprimer le frais - ' . $frais['libelle'];

$errors = [];

// Vérifier s'il y a des paiements liés à ce frais
$paiements_lies = 0;
try {
    $paiements = $database->query(
        "SELECT COUNT(*) as total FROM paiements 
         WHERE type_paiement = ? AND annee_scolaire_id = ?",
        [$frais['type_frais'], $frais['annee_scolaire_id']]
    )->fetch();
    $paiements_lies = $paiements['total'] ?? 0;
} catch (Exception $e) {
    // Table paiements n'existe peut-être pas
}

// Traitement de la suppression
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $confirmation = sanitizeInput($_POST['confirmation'] ?? '');
    $force_delete = isset($_POST['force_delete']);
    
    // Validation
    if ($confirmation !== 'SUPPRIMER') {
        $errors[] = 'Veuillez taper "SUPPRIMER" pour confirmer la suppression.';
    }
    
    if ($paiements_lies > 0 && !$force_delete) {
        $errors[] = 'Ce frais a des paiements associés. Cochez "Forcer la suppression" pour continuer.';
    }
    
    // Si pas d'erreurs, procéder à la suppression
    if (empty($errors)) {
        try {
            $database->beginTransaction();
            
            // Supprimer le frais
            $database->execute("DELETE FROM frais_scolaires WHERE id = ?", [$id]);
            
            // Enregistrer l'action dans les logs si la table existe
            try {
                $log_sql = "INSERT INTO user_actions_log (user_id, action, table_name, record_id, details) 
                           VALUES (?, 'DELETE_FEE', 'frais_scolaires', ?, ?)";
                $database->execute($log_sql, [
                    $_SESSION['user_id'], 
                    $id, 
                    "Suppression du frais: {$frais['libelle']} ({$frais['classe_nom']})"
                ]);
            } catch (Exception $e) {
                // Table de logs n'existe pas, continuer sans erreur
            }
            
            $database->commit();
            
            showMessage('success', 'Frais scolaire supprimé avec succès !');
            redirectTo('index.php');
            
        } catch (Exception $e) {
            $database->rollback();
            $errors[] = 'Erreur lors de la suppression : ' . $e->getMessage();
        }
    }
}

include '../../../includes/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">
        <i class="fas fa-trash me-2 text-danger"></i>
        Supprimer le frais scolaire
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
        <div class="alert alert-danger">
            <h5 class="alert-heading">
                <i class="fas fa-exclamation-triangle me-2"></i>
                Attention !
            </h5>
            <p>Vous êtes sur le point de supprimer définitivement ce frais scolaire. Cette action :</p>
            <ul class="mb-0">
                <li>Supprimera le frais de la base de données</li>
                <li>Ne pourra pas être annulée</li>
                <li>Sera enregistrée dans les logs du système</li>
                <?php if ($paiements_lies > 0): ?>
                    <li class="text-warning"><strong>⚠️ Ce frais a <?php echo $paiements_lies; ?> paiement(s) associé(s)</strong></li>
                <?php endif; ?>
            </ul>
        </div>

        <!-- Informations du frais -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-info-circle me-2"></i>
                    Frais à supprimer
                </h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <table class="table table-borderless">
                            <tr>
                                <td class="fw-bold" style="width: 120px;">Libellé :</td>
                                <td><?php echo htmlspecialchars($frais['libelle']); ?></td>
                            </tr>
                            <tr>
                                <td class="fw-bold">Type :</td>
                                <td>
                                    <?php
                                    $type_colors = [
                                        'inscription' => 'primary',
                                        'mensualite' => 'success',
                                        'examen' => 'warning',
                                        'uniforme' => 'info',
                                        'transport' => 'secondary',
                                        'cantine' => 'dark',
                                        'autre' => 'light'
                                    ];
                                    $color = $type_colors[$frais['type_frais']] ?? 'secondary';
                                    ?>
                                    <span class="badge bg-<?php echo $color; ?>">
                                        <?php echo ucfirst($frais['type_frais']); ?>
                                    </span>
                                </td>
                            </tr>
                            <tr>
                                <td class="fw-bold">Classe :</td>
                                <td><?php echo htmlspecialchars($frais['classe_nom']); ?></td>
                            </tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <table class="table table-borderless">
                            <tr>
                                <td class="fw-bold" style="width: 120px;">Montant :</td>
                                <td>
                                    <span class="fs-5 text-success fw-bold">
                                        <?php echo formatMoney($frais['montant']); ?>
                                    </span>
                                </td>
                            </tr>
                            <tr>
                                <td class="fw-bold">Obligatoire :</td>
                                <td>
                                    <?php if ($frais['obligatoire']): ?>
                                        <span class="badge bg-danger">Obligatoire</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Optionnel</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <tr>
                                <td class="fw-bold">Créé le :</td>
                                <td><?php echo formatDate($frais['created_at']); ?></td>
                            </tr>
                        </table>
                    </div>
                </div>
                
                <?php if ($frais['description']): ?>
                <div class="mt-3">
                    <h6 class="fw-bold">Description :</h6>
                    <p class="text-muted"><?php echo nl2br(htmlspecialchars($frais['description'])); ?></p>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Impact de la suppression -->
        <?php if ($paiements_lies > 0): ?>
        <div class="card mb-4">
            <div class="card-header bg-warning text-dark">
                <h5 class="mb-0">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    Impact de la suppression
                </h5>
            </div>
            <div class="card-body">
                <div class="alert alert-warning">
                    <h6>⚠️ Paiements associés détectés</h6>
                    <p>Ce frais a <strong><?php echo $paiements_lies; ?> paiement(s)</strong> associé(s) dans le système.</p>
                    <p class="mb-0">La suppression de ce frais n'affectera pas les paiements existants, mais pourrait créer des incohérences dans les rapports.</p>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Formulaire de confirmation -->
        <div class="card">
            <div class="card-header bg-danger text-white">
                <h5 class="mb-0">
                    <i class="fas fa-trash me-2"></i>
                    Confirmer la suppression
                </h5>
            </div>
            <div class="card-body">
                <form method="POST" class="needs-validation" novalidate>
                    <?php if ($paiements_lies > 0): ?>
                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" 
                                   type="checkbox" 
                                   id="force_delete" 
                                   name="force_delete">
                            <label class="form-check-label text-warning" for="force_delete">
                                <strong>Forcer la suppression malgré les paiements associés</strong>
                            </label>
                        </div>
                        <div class="form-text">
                            Cochez cette case pour confirmer que vous comprenez les risques.
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <div class="mb-4">
                        <label for="confirmation" class="form-label">
                            Confirmation <span class="text-danger">*</span>
                        </label>
                        <input type="text" 
                               class="form-control" 
                               id="confirmation" 
                               name="confirmation" 
                               placeholder="Tapez SUPPRIMER pour confirmer"
                               required>
                        <div class="form-text">
                            Pour confirmer la suppression, tapez exactement : <strong>SUPPRIMER</strong>
                        </div>
                        <div class="invalid-feedback">
                            Veuillez taper "SUPPRIMER" pour confirmer.
                        </div>
                    </div>
                    
                    <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                        <a href="view.php?id=<?php echo $id; ?>" class="btn btn-outline-secondary me-md-2">
                            <i class="fas fa-arrow-left me-1"></i>
                            Retour sans supprimer
                        </a>
                        <button type="submit" class="btn btn-danger">
                            <i class="fas fa-trash me-1"></i>
                            Confirmer la suppression
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <!-- Alternatives -->
        <div class="card mb-3">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-lightbulb me-2"></i>
                    Alternatives
                </h5>
            </div>
            <div class="card-body">
                <h6>Avant de supprimer :</h6>
                <ul class="small">
                    <li>Pouvez-vous modifier le frais ?</li>
                    <li>Le frais est-il vraiment inutile ?</li>
                    <li>Y a-t-il des paiements associés ?</li>
                </ul>
                
                <div class="d-grid gap-2">
                    <a href="edit.php?id=<?php echo $id; ?>" class="btn btn-outline-primary btn-sm">
                        <i class="fas fa-edit me-1"></i>
                        Modifier plutôt
                    </a>
                    <a href="duplicate.php?id=<?php echo $id; ?>" class="btn btn-outline-info btn-sm">
                        <i class="fas fa-copy me-1"></i>
                        Dupliquer d'abord
                    </a>
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
                <h6>Quand supprimer un frais ?</h6>
                <ul class="small">
                    <li>Frais créé par erreur</li>
                    <li>Frais obsolète</li>
                    <li>Doublon détecté</li>
                    <li>Changement de politique</li>
                </ul>
                
                <h6 class="mt-3">Conséquences :</h6>
                <ul class="small">
                    <li>Suppression définitive</li>
                    <li>Paiements conservés</li>
                    <li>Rapports affectés</li>
                    <li>Action tracée</li>
                </ul>
                
                <div class="alert alert-warning mt-3">
                    <small>
                        <i class="fas fa-exclamation-triangle me-1"></i>
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
                if (confirmation !== 'SUPPRIMER') {
                    event.preventDefault();
                    event.stopPropagation();
                    document.getElementById('confirmation').setCustomValidity('Veuillez taper exactement "SUPPRIMER"');
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
    if (this.value === 'SUPPRIMER') {
        this.setCustomValidity('');
        this.classList.remove('is-invalid');
        this.classList.add('is-valid');
    } else {
        this.setCustomValidity('Veuillez taper exactement "SUPPRIMER"');
        this.classList.remove('is-valid');
        this.classList.add('is-invalid');
    }
});
</script>

<?php include '../../../includes/footer.php'; ?>
