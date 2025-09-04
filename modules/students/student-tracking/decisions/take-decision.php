<?php
/**
 * Prendre une décision d'admission
 * Application de gestion scolaire - République Démocratique du Congo
 */

require_once '../../../../config/config.php';
require_once '../../../../config/database.php';
require_once '../../../../includes/functions.php';

// Vérifier l'authentification et les permissions
requireLogin();
if (!checkPermission('students') && !checkPermission('students_view')) {
    showMessage('error', 'Accès refusé à ce module.');
    redirectTo('../index.php');
}

$page_title = 'Prendre une Décision';

// Récupérer l'ID de la demande
$demande_id = isset($_GET['demande_id']) ? intval($_GET['demande_id']) : 0;

if ($demande_id <= 0) {
    showMessage('error', 'Paramètre invalide.');
    redirectTo('index.php');
}

// Récupérer les informations de la demande
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
    showMessage('error', 'Erreur lors de la récupération de la demande.');
    redirectTo('index.php');
}

if (!$demande) {
    showMessage('error', 'Demande non trouvée.');
    redirectTo('index.php');
}

// Vérifier que la demande n'a pas déjà une décision
try {
    $existing_decision = $database->query(
        "SELECT id FROM decisions_admission WHERE demande_admission_id = ?",
        [$demande_id]
    )->fetch();
    
    if ($existing_decision) {
        showMessage('error', 'Cette demande a déjà une décision.');
        redirectTo('index.php');
    }
} catch (Exception $e) {
    // Continuer si pas de décision existante
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
        
        // Validation des données
        if (empty($decision) || empty($motif_decision)) {
            showMessage('error', 'Les champs obligatoires doivent être remplis.');
        } else {
            // Insérer la décision
            $database->execute(
                "INSERT INTO decisions_admission (demande_admission_id, decision, date_decision, 
                 decideur_id, motif_decision, conditions_speciales, date_limite_reponse,
                 frais_inscription_final, frais_scolarite_final, reduction_finale, commentaire) 
                 VALUES (?, ?, CURDATE(), ?, ?, ?, ?, ?, ?, ?, ?)",
                [$demande_id, $decision, $_SESSION['user_id'], $motif_decision, $conditions_speciales,
                 $date_limite_reponse, $frais_inscription_final, $frais_scolarite_final, 
                 $reduction_finale, $commentaire]
            );
            
            // Mettre à jour le statut de la demande
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
                "Décision d'admission prise pour la demande ID: $demande_id - Statut: $decision"
            );
            
            showMessage('success', 'Décision enregistrée avec succès.');
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
                        <li class="breadcrumb-item"><a href="../../index.php">Suivi des Élèves</a></li>
                        <li class="breadcrumb-item"><a href="index.php">Gestion des Décisions</a></li>
                        <li class="breadcrumb-item active">Prendre une Décision</li>
                    </ol>
                </div>
                <h4 class="page-title">
                    <i class="mdi mdi-gavel me-2"></i>
                    Prendre une Décision d'Admission
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
                        Décision pour <?php echo $demande['prenom_eleve'] . ' ' . $demande['nom_eleve']; ?>
                    </h4>
                </div>
                <div class="card-body">
                    <!-- Informations de la demande -->
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <h6 class="text-muted">Informations de la Demande</h6>
                            <table class="table table-borderless">
                                <tr>
                                    <td><strong>Numéro :</strong></td>
                                    <td><span class="badge bg-light text-dark"><?php echo $demande['numero_demande']; ?></span></td>
                                </tr>
                                <tr>
                                    <td><strong>Classe demandée :</strong></td>
                                    <td><?php echo $demande['classe_demandee']; ?> (<?php echo $demande['niveau']; ?>)</td>
                                </tr>
                                <tr>
                                    <td><strong>Date de demande :</strong></td>
                                    <td><?php echo date('d/m/Y', strtotime($demande['created_at'])); ?></td>
                                </tr>
                            </table>
                        </div>
                        <div class="col-md-6">
                            <h6 class="text-muted">Résultats de l'Évaluation</h6>
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
                                                <span class="badge bg-secondary">Non spécifiée</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                </table>
                            <?php else: ?>
                                <div class="alert alert-warning">
                                    <i class="mdi mdi-alert-outline me-2"></i>
                                    Aucune évaluation disponible
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Formulaire de décision -->
                    <form method="POST" class="needs-validation" novalidate>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="decision" class="form-label">Décision *</label>
                                    <select class="form-select" id="decision" name="decision" required>
                                        <option value="">Sélectionner une décision</option>
                                        <option value="acceptee">Acceptée</option>
                                        <option value="refusee">Refusée</option>
                                        <option value="acceptee_conditionnelle">Acceptation conditionnelle</option>
                                        <option value="mise_en_liste_attente">Mise en liste d'attente</option>
                                    </select>
                                    <div class="invalid-feedback">
                                        Veuillez sélectionner une décision.
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="date_limite_reponse" class="form-label">Date limite de réponse</label>
                                    <input type="date" class="form-control" id="date_limite_reponse" name="date_limite_reponse"
                                           value="<?php echo date('Y-m-d', strtotime('+7 days')); ?>">
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="motif_decision" class="form-label">Motif de la décision *</label>
                            <textarea class="form-control" id="motif_decision" name="motif_decision" rows="3" 
                                      placeholder="Expliquez les raisons de cette décision..." required></textarea>
                            <div class="invalid-feedback">
                                Veuillez expliquer le motif de la décision.
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="conditions_speciales" class="form-label">Conditions spéciales</label>
                            <textarea class="form-control" id="conditions_speciales" name="conditions_speciales" rows="2" 
                                      placeholder="Conditions particulières si acceptation conditionnelle..."></textarea>
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
                                    <label for="frais_scolarite_final" class="form-label">Frais de scolarité (FC)</label>
                                    <input type="number" class="form-control" id="frais_scolarite_final" name="frais_scolarite_final" 
                                           min="0" step="100" value="<?php echo $demande['frais_scolarite'] ?? 0; ?>">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="reduction_finale" class="form-label">Réduction accordée (%)</label>
                                    <input type="number" class="form-control" id="reduction_finale" name="reduction_finale" 
                                           min="0" max="100" step="0.1" value="<?php echo $demande['reduction_accordee'] ?? 0; ?>">
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="commentaire" class="form-label">Commentaire additionnel</label>
                            <textarea class="form-control" id="commentaire" name="commentaire" rows="3" 
                                      placeholder="Observations supplémentaires..."></textarea>
                        </div>
                        
                        <div class="d-flex justify-content-between">
                            <a href="index.php" class="btn btn-secondary">
                                <i class="mdi mdi-arrow-left me-1"></i>
                                Annuler
                            </a>
                            <button type="submit" class="btn btn-primary">
                                <i class="mdi mdi-check me-1"></i>
                                Enregistrer la Décision
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
                        Guide des Décisions
                    </h5>
                </div>
                <div class="card-body">
                    <h6>Types de décisions :</h6>
                    <ul class="list-unstyled">
                        <li class="mb-2">
                            <i class="mdi mdi-check-circle text-success me-2"></i>
                            <strong>Acceptée :</strong> L'élève est admis sans conditions
                        </li>
                        <li class="mb-2">
                            <i class="mdi mdi-close-circle text-danger me-2"></i>
                            <strong>Refusée :</strong> L'élève n'est pas admis
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
                    
                    <h6>Critères de décision :</h6>
                    <ul class="list-unstyled">
                        <li class="mb-2">
                            <i class="mdi mdi-lightbulb text-warning me-2"></i>
                            Résultats de l'évaluation
                        </li>
                        <li class="mb-2">
                            <i class="mdi mdi-lightbulb text-warning me-2"></i>
                            Disponibilité des places
                        </li>
                        <li class="mb-2">
                            <i class="mdi mdi-lightbulb text-warning me-2"></i>
                            Profil de l'élève
                        </li>
                        <li class="mb-2">
                            <i class="mdi mdi-lightbulb text-warning me-2"></i>
                            Capacité d'accueil
                        </li>
                    </ul>
                    
                    <div class="alert alert-info mt-3">
                        <i class="mdi mdi-information-outline me-2"></i>
                        <strong>Note :</strong> Toute décision sera enregistrée dans l'historique et pourra être consultée ultérieurement.
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
    
    // Gestion dynamique des champs selon la décision
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
        
        // Déclencher l'événement au chargement
        decisionSelect.dispatchEvent(new Event('change'));
    }
    
    // Validation de la date limite
    const dateLimiteInput = document.getElementById('date_limite_reponse');
    if (dateLimiteInput) {
        dateLimiteInput.addEventListener('change', function() {
            const selectedDate = new Date(this.value);
            const today = new Date();
            
            if (selectedDate <= today) {
                alert('La date limite de réponse doit être dans le futur.');
                this.value = '';
            }
        });
    }
});
</script>

<?php include '../../../../includes/footer.php'; ?>
