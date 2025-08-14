<?php
/**
 * Nouvelle demande d'admission - Formulaire simplifié
 * Application de gestion scolaire - République Démocratique du Congo
 */

require_once '../../../config/config.php';
require_once '../../../config/database.php';
require_once '../../../includes/functions.php';

// Vérifier l'authentification et les permissions
requireLogin();
if (!checkPermission('students')) {
    showMessage('error', 'Accès refusé à cette page.');
    redirectTo('index.php');
}

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Validation des données
        $nom_eleve = trim($_POST['nom_eleve'] ?? '');
        $prenom_eleve = trim($_POST['prenom_eleve'] ?? '');
        $date_naissance = $_POST['date_naissance'] ?? '';
        $lieu_naissance = trim($_POST['lieu_naissance'] ?? '');
        $sexe = $_POST['sexe'] ?? '';
        $classe_demandee_id = intval($_POST['classe_demandee_id'] ?? 0);
        $telephone_parent = trim($_POST['telephone_parent'] ?? '');
        $nom_pere = trim($_POST['nom_pere'] ?? '');
        $nom_mere = trim($_POST['nom_mere'] ?? '');
        $adresse = trim($_POST['adresse'] ?? '');
        $priorite = $_POST['priorite'] ?? 'normale';
        $motif_demande = trim($_POST['motif_demande'] ?? '');
        
        // Validation
        if (empty($nom_eleve) || empty($prenom_eleve) || empty($date_naissance) || 
            empty($sexe) || !$classe_demandee_id || empty($telephone_parent)) {
            throw new Exception('Veuillez remplir tous les champs obligatoires.');
        }
        
        // Récupérer l'année scolaire courante
        $current_year = $database->query(
            "SELECT * FROM annees_scolaires WHERE status = 'active' LIMIT 1"
        )->fetch();
        
        if (!$current_year) {
            throw new Exception('Aucune année scolaire active trouvée.');
        }
        
        // Générer un numéro de demande unique
        $year_suffix = date('Y');
        $last_demande = $database->query(
            "SELECT numero_demande FROM demandes_admission 
             WHERE numero_demande LIKE ? 
             ORDER BY numero_demande DESC LIMIT 1",
            ["ADM{$year_suffix}%"]
        )->fetch();
        
        if ($last_demande) {
            $last_number = intval(substr($last_demande['numero_demande'], -3));
            $new_number = $last_number + 1;
        } else {
            $new_number = 1;
        }
        
        $numero_demande = "ADM{$year_suffix}" . str_pad($new_number, 3, '0', STR_PAD_LEFT);
        
        // Insérer la candidature
        $database->execute(
            "INSERT INTO demandes_admission (
                numero_demande, annee_scolaire_id, classe_demandee_id, nom_eleve, prenom_eleve,
                date_naissance, lieu_naissance, sexe, adresse, telephone_parent,
                nom_pere, nom_mere, motif_demande, status, priorite, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'en_attente', ?, NOW())",
            [
                $numero_demande, $current_year['id'], $classe_demandee_id, $nom_eleve, $prenom_eleve,
                $date_naissance, $lieu_naissance, $sexe, $adresse, $telephone_parent,
                $nom_pere, $nom_mere, $motif_demande, $priorite
            ]
        );
        
        $candidature_id = $database->lastInsertId();
        
        showMessage('success', "Demande d'admission créée avec succès. Numéro : $numero_demande");
        
        // Rediriger vers la page de détails
        redirectTo("applications/view.php?id=$candidature_id");
        
    } catch (Exception $e) {
        showMessage('error', 'Erreur lors de la création : ' . $e->getMessage());
    }
}

// Récupérer les classes disponibles
try {
    $classes = $database->query("SELECT * FROM classes ORDER BY niveau, nom")->fetchAll();
} catch (Exception $e) {
    $classes = [];
    showMessage('error', 'Erreur lors du chargement des classes : ' . $e->getMessage());
}

$page_title = "Nouvelle Demande d'Admission";
include '../../../includes/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">
        <i class="fas fa-plus-circle me-2"></i>
        Nouvelle Demande d'Admission
    </h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <div class="btn-group me-2">
            <a href="applications/" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-1"></i>
                Retour aux candidatures
            </a>
        </div>
        <div class="btn-group me-2">
            <a href="index.php" class="btn btn-outline-secondary">
                <i class="fas fa-home me-1"></i>
                Admissions
            </a>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-8">
        <form method="POST" class="needs-validation" novalidate>
            <!-- Informations de l'élève -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-user me-2"></i>
                        Informations de l'élève
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="nom_eleve" class="form-label">
                                Nom de l'élève <span class="text-danger">*</span>
                            </label>
                            <input type="text" class="form-control" id="nom_eleve" name="nom_eleve" 
                                   value="<?php echo htmlspecialchars($_POST['nom_eleve'] ?? ''); ?>" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="prenom_eleve" class="form-label">
                                Prénom de l'élève <span class="text-danger">*</span>
                            </label>
                            <input type="text" class="form-control" id="prenom_eleve" name="prenom_eleve" 
                                   value="<?php echo htmlspecialchars($_POST['prenom_eleve'] ?? ''); ?>" required>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label for="date_naissance" class="form-label">
                                Date de naissance <span class="text-danger">*</span>
                            </label>
                            <input type="date" class="form-control" id="date_naissance" name="date_naissance" 
                                   value="<?php echo htmlspecialchars($_POST['date_naissance'] ?? ''); ?>" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="lieu_naissance" class="form-label">
                                Lieu de naissance
                            </label>
                            <input type="text" class="form-control" id="lieu_naissance" name="lieu_naissance" 
                                   value="<?php echo htmlspecialchars($_POST['lieu_naissance'] ?? ''); ?>">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="sexe" class="form-label">
                                Sexe <span class="text-danger">*</span>
                            </label>
                            <select class="form-select" id="sexe" name="sexe" required>
                                <option value="">-- Sélectionner --</option>
                                <option value="M" <?php echo ($_POST['sexe'] ?? '') === 'M' ? 'selected' : ''; ?>>Masculin</option>
                                <option value="F" <?php echo ($_POST['sexe'] ?? '') === 'F' ? 'selected' : ''; ?>>Féminin</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="classe_demandee_id" class="form-label">
                                Classe demandée <span class="text-danger">*</span>
                            </label>
                            <select class="form-select" id="classe_demandee_id" name="classe_demandee_id" required>
                                <option value="">-- Sélectionner une classe --</option>
                                <?php foreach ($classes as $classe): ?>
                                    <option value="<?php echo $classe['id']; ?>" 
                                            <?php echo (intval($_POST['classe_demandee_id'] ?? 0) === $classe['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($classe['nom'] . ' - ' . $classe['niveau']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="priorite" class="form-label">
                                Priorité
                            </label>
                            <select class="form-select" id="priorite" name="priorite">
                                <option value="normale" <?php echo ($_POST['priorite'] ?? 'normale') === 'normale' ? 'selected' : ''; ?>>Normale</option>
                                <option value="urgente" <?php echo ($_POST['priorite'] ?? '') === 'urgente' ? 'selected' : ''; ?>>Urgente</option>
                                <option value="tres_urgente" <?php echo ($_POST['priorite'] ?? '') === 'tres_urgente' ? 'selected' : ''; ?>>Très urgente</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Informations des parents -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-users me-2"></i>
                        Informations des parents
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label for="nom_pere" class="form-label">
                                Nom du père
                            </label>
                            <input type="text" class="form-control" id="nom_pere" name="nom_pere" 
                                   value="<?php echo htmlspecialchars($_POST['nom_pere'] ?? ''); ?>">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="nom_mere" class="form-label">
                                Nom de la mère
                            </label>
                            <input type="text" class="form-control" id="nom_mere" name="nom_mere" 
                                   value="<?php echo htmlspecialchars($_POST['nom_mere'] ?? ''); ?>">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="telephone_parent" class="form-label">
                                Téléphone parent <span class="text-danger">*</span>
                            </label>
                            <input type="tel" class="form-control" id="telephone_parent" name="telephone_parent" 
                                   value="<?php echo htmlspecialchars($_POST['telephone_parent'] ?? ''); ?>" required>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="adresse" class="form-label">
                            Adresse
                        </label>
                        <textarea class="form-control" id="adresse" name="adresse" rows="2" 
                                  placeholder="Adresse complète de la famille"><?php echo htmlspecialchars($_POST['adresse'] ?? ''); ?></textarea>
                    </div>
                </div>
            </div>
            
            <!-- Motif de la demande -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-comment me-2"></i>
                        Motif de la demande
                    </h5>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label for="motif_demande" class="form-label">
                            Pourquoi souhaitez-vous inscrire cet élève dans notre établissement ?
                        </label>
                        <textarea class="form-control" id="motif_demande" name="motif_demande" rows="3" 
                                  placeholder="Expliquez les raisons de votre demande d'admission..."><?php echo htmlspecialchars($_POST['motif_demande'] ?? ''); ?></textarea>
                    </div>
                </div>
            </div>
            
            <!-- Boutons d'action -->
            <div class="card">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <a href="applications/" class="btn btn-secondary">
                                <i class="fas fa-times me-1"></i>
                                Annuler
                            </a>
                        </div>
                        <div>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-1"></i>
                                Créer la demande
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
    
    <div class="col-md-4">
        <!-- Aide et informations -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-info-circle me-2"></i>
                    Informations importantes
                </h5>
            </div>
            <div class="card-body">
                <div class="alert alert-info">
                    <h6><i class="fas fa-lightbulb me-2"></i>Conseils</h6>
                    <ul class="mb-0 small">
                        <li>Remplissez tous les champs obligatoires (*)</li>
                        <li>Vérifiez l'orthographe des noms</li>
                        <li>Le numéro de téléphone doit être valide</li>
                        <li>Choisissez la classe appropriée à l'âge</li>
                    </ul>
                </div>
                
                <div class="alert alert-warning">
                    <h6><i class="fas fa-exclamation-triangle me-2"></i>Prochaines étapes</h6>
                    <ol class="mb-0 small">
                        <li>Soumission de la demande</li>
                        <li>Vérification des documents</li>
                        <li>Évaluation du dossier</li>
                        <li>Décision d'admission</li>
                        <li>Inscription si accepté</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Validation Bootstrap
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
