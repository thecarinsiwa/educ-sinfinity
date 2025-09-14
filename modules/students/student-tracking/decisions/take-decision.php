<?php
/**
 * Prendre une dÃ©cision d'admission
 * Application de gestion scolaire - République Démocratique du Congo
 */

require_once '../../../../config/config.php';
require_once '../../../../config/database.php';
require_once '../../../../includes/functions.php';
require_once '../../../../includes/permissions-pages.php';

// VÃ©rifier l'authentification et les permissions
requireLogin();

requirePagePermissionFromDB('students', 'student-tracking/decisions/take-decision', 'create', '../../../../dashboard.php');

$page_title = 'Prendre une DÃ©cision';

// RÃ©cupÃ©rer l'ID de la demande
$demande_id = isset($_GET['demande_id']) ? intval($_GET['demande_id']) : 0;

if ($demande_id <= 0) {
    showMessage('error', 'ParamÃ¨tre invalide.');
    redirectTo('index.php');
}

// RÃ©cupÃ©rer les informations de la demande
try {
    $stmt = $database->query(
        "SELECT da.*, c.nom as classe_demandee, c.niveau,
                ea.note_evaluation, ea.commentaire_evaluation, ea.recommandation,
                ea.type_evaluation, ea.date_evaluation,
                u.username as evaluateur_nom
         FROM demandes_admission da
         LEFT JOIN classes c ON da.classe_demandee_id = c.id
         LEFT JOIN evaluations_admission ea ON da.id = ea.demande_admission_id
         LEFT JOIN users u ON ea.evaluateur_id = u.id
         WHERE da.id = ?",
        [$demande_id]
    );
    $demande = $stmt->fetch();
} catch (Exception $e) {
    showMessage('error', 'Erreur lors de la rÃ©cupÃ©ration de la demande.');
    redirectTo('index.php');
}

if (!$demande) {
    showMessage('error', 'Demande non trouvÃ©e.');
    redirectTo('index.php');
}

// VÃ©rifier que la demande n'a pas dÃ©jÃ  une dÃ©cision
try {
    $existing_decision = $database->query(
        "SELECT id FROM decisions_admission WHERE demande_admission_id = ?",
        [$demande_id]
    )->fetch();
    
    if ($existing_decision) {
        showMessage('error', 'Cette demande a dÃ©jÃ  une dÃ©cision.');
        redirectTo('index.php');
    }
} catch (Exception $e) {
    // Continuer si pas de dÃ©cision existante
}

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $decision = $_POST['decision'];
        $motif_decision = sanitizeInput($_POST['motif_decision']);
        $conditions_speciales = sanitizeInput($_POST['conditions_speciales']);
        $date_limite_reponse = $_POST['date_limite_reponse'];
        $frais_inscription_final = floatval($_POST['frais_inscription_final']);
        $frais_scolarite_final = floatval($_POST['frais_scolarite_final']);
        $reduction_finale = floatval($_POST['reduction_finale']);
        $commentaire = sanitizeInput($_POST['commentaire']);
        
        // Validation des donnÃ©es
        if (empty($decision) || empty($motif_decision)) {
            showMessage('error', 'Les champs obligatoires doivent Ãªtre remplis.');
        } else {
            // InsÃ©rer la dÃ©cision
            $database->execute(
                "INSERT INTO decisions_admission (demande_admission_id, decision, date_decision, 
                 decideur_id, motif_decision, conditions_speciales, date_limite_reponse,
                 frais_inscription_final, frais_scolarite_final, reduction_finale, commentaire) 
                 VALUES (?, ?, CURDATE(), ?, ?, ?, ?, ?, ?, ?, ?)",
                [$demande_id, $decision, $_SESSION['user_id'], $motif_decision, $conditions_speciales,
                 $date_limite_reponse, $frais_inscription_final, $frais_scolarite_final, 
                 $reduction_finale, $commentaire]
            );
            
            // Mettre Ã  jour le statut de la demande
            $new_status = '';
            switch ($decision) {
                case 'acceptee':
                    $new_status = 'acceptee';
                    break;
                case 'refusee':
                    $new_status = 'refusee';
                    break;
                case 'acceptee_conditionnelle':
                    $new_status = 'acceptee';
                    break;
                case 'mise_en_liste_attente':
                    $new_status = 'en_attente';
                    break;
            }
            
            if ($new_status) {
                $database->execute(
                    "UPDATE demandes_admission SET status = ?, updated_at = NOW() WHERE id = ?",
                    [$new_status, $demande_id]
                );
            }
            
            // Logger l'action
            logUserAction(
                'decision_admission', 
                'students', 
                "DÃ©cision d'admission prise pour la demande ID: $demande_id - Statut: $decision"
            );
            
            showMessage('success', 'DÃ©cision enregistrÃ©e avec succÃ¨s.');
            redirectTo('index.php');
        }
    } catch (Exception $e) {
        showMessage('error', 'Erreur lors de l\'enregistrement : ' . $e->getMessage());
    }
}

include '../../../../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="page-title-box">
                <div class="page-title-right">
                    <ol class="breadcrumb m-0">
                        <li class="breadcrumb-item"><a href="../../../../dashboard.php">Tableau de bord</a></li>
                        <li class="breadcrumb-item"><a href="../../index.php">Suivi des Ã‰lÃ¨ves</a></li>
                        <li class="breadcrumb-item"><a href="index.php">Gestion des DÃ©cisions</a></li>
                        <li class="breadcrumb-item active">Prendre une DÃ©cision</li>
                    </ol>
                </div>
                <h4 class="page-title">
                    <i class="mdi mdi-gavel me-2"></i>
                    Prendre une DÃ©cision d'Admission
                </h4>
            </div>
        </div>
    </div>

    <?php displayMessage(); ?>

    <div class="row">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header">
                    <h4 class="header-title">
                        <i class="mdi mdi-account-check me-2"></i>
                        DÃ©cision pour <?php echo $demande['prenom_eleve'] . ' ' . $demande['nom_eleve']; ?>
                    </h4>
                </div>
                <div class="card-body">
                    <!-- Informations de la demande -->
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <h6 class="text-muted">Informations de la Demande</h6>
                            <table class="table table-borderless">
                                <tr>
                                    <td><strong>NumÃ©ro :</strong></td>
                                    <td><span class="badge bg-light text-dark"><?php echo $demande['numero_demande']; ?></span></td>
                                </tr>
                                <tr>
                                    <td><strong>Classe demandÃ©e :</strong></td>
                                    <td><?php echo $demande['classe_demandee']; ?> (<?php echo $demande['niveau']; ?>)</td>
                                </tr>
                                <tr>
                                    <td><strong>Date de demande :</strong></td>
                                    <td><?php echo date('d/m/Y', strtotime($demande['created_at'])); ?></td>
                                </tr>
                            </table>
                        </div>
                        <div class="col-md-6">
                            <h6 class="text-muted">RÃ©sultats de l'Ã‰valuation</h6>
                            <?php if ($demande['note_evaluation']): ?>
                                <table class="table table-borderless">
                                    <tr>
                                        <td><strong>Note :</strong></td>
                                        <td><span class="badge bg-info"><?php echo $demande['note_evaluation']; ?>/20</span></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Type :</strong></td>
                                        <td><?php echo ucfirst(str_replace('_', ' ', $demande['type_evaluation'])); ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Recommandation :</strong></td>
                                        <td>
                                            <?php if ($demande['recommandation']): ?>
                                                <span class="badge bg-warning"><?php echo ucfirst($demande['recommandation']); ?></span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary">Non spÃ©cifiÃ©e</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                </table>
                            <?php else: ?>
                                <div class="alert alert-warning">
                                    <i class="mdi mdi-alert-outline me-2"></i>
                                    Aucune Ã©valuation disponible
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Formulaire de dÃ©cision -->
                    <form method="POST" class="needs-validation" novalidate>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="decision" class="form-label">DÃ©cision *</label>
                                    <select class="form-select" id="decision" name="decision" required>
                                        <option value="">SÃ©lectionner une dÃ©cision</option>
                                        <option value="acceptee">AcceptÃ©e</option>
                                        <option value="refusee">RefusÃ©e</option>
                                        <option value="acceptee_conditionnelle">Acceptation conditionnelle</option>
                                        <option value="mise_en_liste_attente">Mise en liste d'attente</option>
                                    </select>
                                    <div class="invalid-feedback">
                                        Veuillez sÃ©lectionner une dÃ©cision.
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="date_limite_reponse" class="form-label">Date limite de rÃ©ponse</label>
                                    <input type="date" class="form-control" id="date_limite_reponse" name="date_limite_reponse"
                                           value="<?php echo date('Y-m-d', strtotime('+7 days')); ?>">
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="motif_decision" class="form-label">Motif de la dÃ©cision *</label>
                            <textarea class="form-control" id="motif_decision" name="motif_decision" rows="3" 
                                      placeholder="Expliquez les raisons de cette dÃ©cision..." required></textarea>
                            <div class="invalid-feedback">
                                Veuillez expliquer le motif de la dÃ©cision.
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="conditions_speciales" class="form-label">Conditions spéciales</label>
                            <textarea class="form-control" id="conditions_speciales" name="conditions_speciales" rows="2" 
                                      placeholder="Conditions particuliÃ¨res si acceptation conditionnelle..."></textarea>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="frais_inscription_final" class="form-label">Frais d'inscription (FC)</label>
                                    <input type="number" class="form-control" id="frais_inscription_final" name="frais_inscription_final" 
                                           min="0" step="100" value="<?php echo $demande['frais_inscription'] ?? 0; ?>">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="frais_scolarite_final" class="form-label">Frais de scolaritÃ© (FC)</label>
                                    <input type="number" class="form-control" id="frais_scolarite_final" name="frais_scolarite_final" 
                                           min="0" step="100" value="<?php echo $demande['frais_scolarite'] ?? 0; ?>">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="reduction_finale" class="form-label">RÃ©duction accordÃ©e (%)</label>
                                    <input type="number" class="form-control" id="reduction_finale" name="reduction_finale" 
                                           min="0" max="100" step="0.1" value="<?php echo $demande['reduction_accordee'] ?? 0; ?>">
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="commentaire" class="form-label">Commentaire additionnel</label>
                            <textarea class="form-control" id="commentaire" name="commentaire" rows="3" 
                                      placeholder="Observations supplÃ©mentaires..."></textarea>
                        </div>
                        
                        <div class="d-flex justify-content-between">
                            <a href="index.php" class="btn btn-secondary">
                                <i class="mdi mdi-arrow-left me-1"></i>
                                Annuler
                            </a>
                            <button type="submit" class="btn btn-primary">
                                <i class="mdi mdi-check me-1"></i>
                                Enregistrer la DÃ©cision
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
        <div class="col-lg-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="header-title">
                        <i class="mdi mdi-information-outline me-2"></i>
                        Guide des DÃ©cisions
                    </h5>
                </div>
                <div class="card-body">
                    <h6>Types de dÃ©cisions :</h6>
                    <ul class="list-unstyled">
                        <li class="mb-2">
                            <i class="mdi mdi-check-circle text-success me-2"></i>
                            <strong>AcceptÃ©e :</strong> L'Ã©lÃ¨ve est admis sans conditions
                        </li>
                        <li class="mb-2">
                            <i class="mdi mdi-close-circle text-danger me-2"></i>
                            <strong>RefusÃ©e :</strong> L'Ã©lÃ¨ve n'est pas admis
                        </li>
                        <li class="mb-2">
                            <i class="mdi mdi-alert-circle text-warning me-2"></i>
                            <strong>Acceptation conditionnelle :</strong> Admission sous conditions
                        </li>
                        <li class="mb-2">
                            <i class="mdi mdi-clock text-info me-2"></i>
                            <strong>Liste d'attente :</strong> En attente de place disponible
                        </li>
                    </ul>
                    
                    <hr>
                    
                    <h6>CritÃ¨res de dÃ©cision :</h6>
                    <ul class="list-unstyled">
                        <li class="mb-2">
                            <i class="mdi mdi-lightbulb text-warning me-2"></i>
                            RÃ©sultats de l'Ã©valuation
                        </li>
                        <li class="mb-2">
                            <i class="mdi mdi-lightbulb text-warning me-2"></i>
                            DisponibilitÃ© des places
                        </li>
                        <li class="mb-2">
                            <i class="mdi mdi-lightbulb text-warning me-2"></i>
                            Profil de l'Ã©lÃ¨ve
                        </li>
                        <li class="mb-2">
                            <i class="mdi mdi-lightbulb text-warning me-2"></i>
                            CapacitÃ© d'accueil
                        </li>
                    </ul>
                    
                    <div class="alert alert-info mt-3">
                        <i class="mdi mdi-information-outline me-2"></i>
                        <strong>Note :</strong> Toute dÃ©cision sera enregistrÃ©e dans l'historique et pourra Ãªtre consultÃ©e ultÃ©rieurement.
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Validation des formulaires
    const forms = document.querySelectorAll('.needs-validation');
    
    Array.from(forms).forEach(form => {
        form.addEventListener('submit', event => {
            if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            }
            form.classList.add('was-validated');
        }, false);
    });
    
    // Gestion dynamique des champs selon la dÃ©cision
    const decisionSelect = document.getElementById('decision');
    const conditionsField = document.getElementById('conditions_speciales');
    const fraisFields = document.querySelectorAll('#frais_inscription_final, #frais_scolarite_final, #reduction_finale');
    
    if (decisionSelect) {
        decisionSelect.addEventListener('change', function() {
            const decision = this.value;
            
            // Afficher/masquer les conditions spéciales
            if (decision === 'acceptee_conditionnelle') {
                conditionsField.parentElement.style.display = 'block';
                conditionsField.setAttribute('required', 'required');
            } else {
                conditionsField.parentElement.style.display = 'none';
                conditionsField.removeAttribute('required');
            }
            
            // Afficher/masquer les champs de frais
            if (decision === 'acceptee' || decision === 'acceptee_conditionnelle') {
                fraisFields.forEach(field => {
                    field.parentElement.style.display = 'block';
                });
            } else {
                fraisFields.forEach(field => {
                    field.parentElement.style.display = 'none';
                });
            }
        });
        
        // DÃ©clencher l'événement au chargement
        decisionSelect.dispatchEvent(new Event('change'));
    }
    
    // Validation de la date limite
    const dateLimiteInput = document.getElementById('date_limite_reponse');
    if (dateLimiteInput) {
        dateLimiteInput.addEventListener('change', function() {
            const selectedDate = new Date(this.value);
            const today = new Date();
            
            if (selectedDate <= today) {
                alert('La date limite de rÃ©ponse doit Ãªtre dans le futur.');
                this.value = '';
            }
        });
    }
});
</script>

<?php include '../../../../includes/footer.php'; ?>




