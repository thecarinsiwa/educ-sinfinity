<?php
/**
 * Ajout d'une nouvelle évaluation d'admission
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

$page_title = 'Ajouter une Évaluation';

// Récupérer l'ID de la demande si fourni
$demande_id = isset($_GET['demande_id']) ? intval($_GET['demande_id']) : 0;

// Récupérer les informations de la demande si spécifiée
$demande = null;
if ($demande_id > 0) {
    try {
        $stmt = $database->query(
            "SELECT da.*, c.nom as classe_demandee, c.niveau
             FROM demandes_admission da
             LEFT JOIN classes c ON da.classe_demandee_id = c.id
             WHERE da.id = ?",
            [$demande_id]
        );
        $demande = $stmt->fetch();
    } catch (Exception $e) {
        showMessage('error', 'Erreur lors de la récupération de la demande.');
        redirectTo('index.php');
    }
}

// Récupérer les utilisateurs évaluateurs
try {
    $evaluateurs = $database->query(
        "SELECT id, username, nom, prenom FROM users WHERE role IN ('enseignant', 'directeur', 'admin') AND status = 'actif'"
    )->fetchAll();
} catch (Exception $e) {
    $evaluateurs = [];
}

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $demande_id = intval($_POST['demande_id']);
        $type_evaluation = $_POST['type_evaluation'];
        $date_evaluation = $_POST['date_evaluation'];
        $heure_debut = $_POST['heure_debut'];
        $heure_fin = $_POST['heure_fin'];
        $lieu = sanitizeInput($_POST['lieu']);
        $evaluateur_id = intval($_POST['evaluateur_id']);
        
        // Validation des données
        if (empty($demande_id) || empty($type_evaluation) || empty($date_evaluation) || empty($evaluateur_id)) {
            showMessage('error', 'Tous les champs obligatoires doivent être remplis.');
        } else {
            // Vérifier que la demande n'a pas déjà une évaluation
            $existing_evaluation = $database->query(
                "SELECT id FROM evaluations_admission WHERE demande_admission_id = ?",
                [$demande_id]
            )->fetch();
            
            if ($existing_evaluation) {
                showMessage('error', 'Cette demande a déjà une évaluation programmée.');
            } else {
                // Insérer l'évaluation
                $database->execute(
                    "INSERT INTO evaluations_admission (demande_admission_id, type_evaluation, date_evaluation, 
                     heure_debut, heure_fin, lieu, evaluateur_id, created_at) 
                     VALUES (?, ?, ?, ?, ?, ?, ?, NOW())",
                    [$demande_id, $type_evaluation, $date_evaluation, $heure_debut, $heure_fin, $lieu, $evaluateur_id]
                );
                
                // Mettre à jour le statut de la demande
                $database->execute(
                    "UPDATE demandes_admission SET status = 'en_cours_traitement', updated_at = NOW() WHERE id = ?",
                    [$demande_id]
                );
                
                // Logger l'action
                logUserAction(
                    'ajout_evaluation_admission', 
                    'students', 
                    "Nouvelle évaluation programmée pour la demande d'admission ID: $demande_id"
                );
                
                showMessage('success', 'Évaluation programmée avec succès.');
                redirectTo('index.php');
            }
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
                        <li class="breadcrumb-item"><a href="index.php">Gestion des Évaluations</a></li>
                        <li class="breadcrumb-item active">Ajouter une Évaluation</li>
                    </ol>
                </div>
                <h4 class="page-title">
                    <i class="mdi mdi-plus me-2"></i>
                    Programmer une Évaluation
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
                        <i class="mdi mdi-calendar-plus me-2"></i>
                        Informations de l'Évaluation
                    </h4>
                </div>
                <div class="card-body">
                    <form method="POST" class="needs-validation" novalidate>
                        <!-- Sélection de la demande -->
                        <div class="mb-3">
                            <label for="demande_id" class="form-label">Demande d'admission *</label>
                            <?php if ($demande): ?>
                                <input type="hidden" name="demande_id" value="<?php echo $demande['id']; ?>">
                                <div class="form-control-plaintext">
                                    <strong><?php echo $demande['prenom_eleve'] . ' ' . $demande['nom_eleve']; ?></strong>
                                    <br>
                                    <small class="text-muted">
                                        <?php echo $demande['numero_demande']; ?> - 
                                        <?php echo $demande['classe_demandee']; ?> (<?php echo $demande['niveau']; ?>)
                                    </small>
                                </div>
                            <?php else: ?>
                                <select class="form-select" id="demande_id" name="demande_id" required>
                                    <option value="">Sélectionner une demande d'admission</option>
                                    <?php
                                    try {
                                        $demandes_disponibles = $database->query(
                                            "SELECT da.*, c.nom as classe_demandee, c.niveau
                                             FROM demandes_admission da
                                             LEFT JOIN classes c ON da.classe_demandee_id = c.id
                                             WHERE da.status = 'en_attente' 
                                             AND da.annee_scolaire_id = ?
                                             AND NOT EXISTS (SELECT 1 FROM evaluations_admission ea WHERE ea.demande_admission_id = da.id)
                                             ORDER BY da.created_at ASC",
                                            [getCurrentAcademicYear()['id'] ?? 0]
                                        )->fetchAll();
                                        
                                        foreach ($demandes_disponibles as $demande_dispo):
                                    ?>
                                        <option value="<?php echo $demande_dispo['id']; ?>">
                                            <?php echo $demande_dispo['prenom_eleve'] . ' ' . $demande_dispo['nom_eleve']; ?> - 
                                            <?php echo $demande_dispo['numero_demande']; ?> 
                                            (<?php echo $demande_dispo['classe_demandee']; ?>)
                                        </option>
                                    <?php 
                                        endforeach;
                                    } catch (Exception $e) {
                                        // Gérer l'erreur silencieusement
                                    }
                                    ?>
                                </select>
                                <div class="invalid-feedback">
                                    Veuillez sélectionner une demande d'admission.
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="type_evaluation" class="form-label">Type d'évaluation *</label>
                                    <select class="form-select" id="type_evaluation" name="type_evaluation" required>
                                        <option value="">Sélectionner un type</option>
                                        <option value="test_ecrit">Test écrit</option>
                                        <option value="entretien">Entretien</option>
                                        <option value="examen_medical">Examen médical</option>
                                        <option value="evaluation_psychologique">Évaluation psychologique</option>
                                        <option value="test_niveau">Test de niveau</option>
                                    </select>
                                    <div class="invalid-feedback">
                                        Veuillez sélectionner un type d'évaluation.
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="evaluateur_id" class="form-label">Évaluateur *</label>
                                    <select class="form-select" id="evaluateur_id" name="evaluateur_id" required>
                                        <option value="">Sélectionner un évaluateur</option>
                                        <?php foreach ($evaluateurs as $evaluateur): ?>
                                            <option value="<?php echo $evaluateur['id']; ?>">
                                                <?php echo $evaluateur['prenom'] . ' ' . $evaluateur['nom']; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div class="invalid-feedback">
                                        Veuillez sélectionner un évaluateur.
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="date_evaluation" class="form-label">Date d'évaluation *</label>
                                    <input type="date" class="form-control" id="date_evaluation" name="date_evaluation" 
                                           value="<?php echo date('Y-m-d'); ?>" required>
                                    <div class="invalid-feedback">
                                        Veuillez sélectionner une date d'évaluation.
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="heure_debut" class="form-label">Heure de début</label>
                                    <input type="time" class="form-control" id="heure_debut" name="heure_debut">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="heure_fin" class="form-label">Heure de fin</label>
                                    <input type="time" class="form-control" id="heure_fin" name="heure_fin">
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="lieu" class="form-label">Lieu d'évaluation</label>
                            <input type="text" class="form-control" id="lieu" name="lieu" 
                                   placeholder="Salle, bureau, etc.">
                        </div>
                        
                        <div class="d-flex justify-content-between">
                            <a href="index.php" class="btn btn-secondary">
                                <i class="mdi mdi-arrow-left me-1"></i>
                                Annuler
                            </a>
                            <button type="submit" class="btn btn-primary">
                                <i class="mdi mdi-check me-1"></i>
                                Programmer l'Évaluation
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
                        Informations Importantes
                    </h5>
                </div>
                <div class="card-body">
                    <h6>Types d'évaluation :</h6>
                    <ul class="list-unstyled">
                        <li class="mb-2">
                            <i class="mdi mdi-check-circle text-success me-2"></i>
                            <strong>Test écrit :</strong> Évaluation des connaissances
                        </li>
                        <li class="mb-2">
                            <i class="mdi mdi-check-circle text-success me-2"></i>
                            <strong>Entretien :</strong> Évaluation comportementale
                        </li>
                        <li class="mb-2">
                            <i class="mdi mdi-check-circle text-success me-2"></i>
                            <strong>Examen médical :</strong> Vérification de la santé
                        </li>
                        <li class="mb-2">
                            <i class="mdi mdi-check-circle text-success me-2"></i>
                            <strong>Évaluation psychologique :</strong> Test de personnalité
                        </li>
                        <li class="mb-2">
                            <i class="mdi mdi-check-circle text-success me-2"></i>
                            <strong>Test de niveau :</strong> Évaluation des compétences
                        </li>
                    </ul>
                    
                    <hr>
                    
                    <h6>Conseils :</h6>
                    <ul class="list-unstyled">
                        <li class="mb-2">
                            <i class="mdi mdi-lightbulb text-warning me-2"></i>
                            Planifiez les évaluations en fonction de la disponibilité des évaluateurs
                        </li>
                        <li class="mb-2">
                            <i class="mdi mdi-lightbulb text-warning me-2"></i>
                            Prévoyez un délai suffisant entre les évaluations
                        </li>
                        <li class="mb-2">
                            <i class="mdi mdi-lightbulb text-warning me-2"></i>
                            Assurez-vous que le lieu est approprié pour le type d'évaluation
                        </li>
                    </ul>
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
    
    // Validation de la date d'évaluation
    const dateEvaluationInput = document.getElementById('date_evaluation');
    if (dateEvaluationInput) {
        dateEvaluationInput.addEventListener('change', function() {
            const selectedDate = new Date(this.value);
            const today = new Date();
            
            if (selectedDate < today) {
                alert('La date d\'évaluation ne peut pas être dans le passé.');
                this.value = today.toISOString().split('T')[0];
            }
        });
    }
    
    // Validation des heures
    const heureDebutInput = document.getElementById('heure_debut');
    const heureFinInput = document.getElementById('heure_fin');
    
    if (heureDebutInput && heureFinInput) {
        heureFinInput.addEventListener('change', function() {
            if (heureDebutInput.value && this.value) {
                if (heureDebutInput.value >= this.value) {
                    alert('L\'heure de fin doit être postérieure à l\'heure de début.');
                    this.value = '';
                }
            }
        });
    }
});
</script>

<?php include '../../../../includes/footer.php'; ?>
