<?php
/**
 * Inscription directe d'un élève (sans passer par le processus de candidature)
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
        // Validation des données de base
        $nom = trim($_POST['nom'] ?? '');
        $prenom = trim($_POST['prenom'] ?? '');
        $date_naissance = $_POST['date_naissance'] ?? '';
        $lieu_naissance = trim($_POST['lieu_naissance'] ?? '');
        $sexe = $_POST['sexe'] ?? '';
        $classe_id = intval($_POST['classe_id'] ?? 0);
        $adresse = trim($_POST['adresse'] ?? '');
        $telephone = trim($_POST['telephone'] ?? '');
        $email = trim($_POST['email'] ?? '');
        
        // Informations des parents
        $nom_pere = trim($_POST['nom_pere'] ?? '');
        $nom_mere = trim($_POST['nom_mere'] ?? '');
        $profession_pere = trim($_POST['profession_pere'] ?? '');
        $profession_mere = trim($_POST['profession_mere'] ?? '');
        $telephone_parent = trim($_POST['telephone_parent'] ?? '');
        $personne_contact = trim($_POST['personne_contact'] ?? '');
        $telephone_contact = trim($_POST['telephone_contact'] ?? '');
        $relation_contact = trim($_POST['relation_contact'] ?? '');
        
        // Informations financières
        $frais_inscription = floatval($_POST['frais_inscription'] ?? 0);
        $frais_scolarite = floatval($_POST['frais_scolarite'] ?? 0);
        $reduction_accordee = floatval($_POST['reduction_accordee'] ?? 0);
        
        // Validation
        if (empty($nom) || empty($prenom) || empty($date_naissance) || 
            empty($sexe) || !$classe_id || empty($telephone_parent)) {
            throw new Exception('Veuillez remplir tous les champs obligatoires.');
        }
        
        // Récupérer l'année scolaire courante
        $current_year = $database->query(
            "SELECT * FROM annees_scolaires WHERE status = 'active' LIMIT 1"
        )->fetch();
        
        if (!$current_year) {
            throw new Exception('Aucune année scolaire active trouvée.');
        }
        
        // Vérifier si l'élève n'existe pas déjà
        $existing_student = $database->query(
            "SELECT id FROM eleves WHERE nom = ? AND prenom = ? AND date_naissance = ?",
            [$nom, $prenom, $date_naissance]
        )->fetch();
        
        if ($existing_student) {
            throw new Exception('Un élève avec ces informations existe déjà.');
        }
        
        // Générer un numéro d'élève unique
        $annee_courante = date('Y');
        $last_student = $database->query(
            "SELECT numero_eleve FROM eleves WHERE numero_eleve LIKE ? ORDER BY numero_eleve DESC LIMIT 1",
            [$annee_courante . '%']
        )->fetch();
        
        if ($last_student) {
            $last_number = intval(substr($last_student['numero_eleve'], -4));
            $new_number = $last_number + 1;
        } else {
            $new_number = 1;
        }
        
        $numero_eleve = $annee_courante . str_pad($new_number, 4, '0', STR_PAD_LEFT);
        
        // Commencer une transaction
        $database->beginTransaction();
        
        try {
            // Créer l'élève
            $database->execute(
                "INSERT INTO eleves (
                    numero_eleve, nom, prenom, date_naissance, lieu_naissance, sexe,
                    adresse, telephone, email, nom_pere, nom_mere, profession_pere, profession_mere,
                    telephone_parent, personne_contact, telephone_contact, relation_contact,
                    classe_id, annee_scolaire_id, status, date_inscription, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'actif', ?, NOW())",
                [
                    $numero_eleve, $nom, $prenom, $date_naissance, $lieu_naissance, $sexe,
                    $adresse, $telephone, $email, $nom_pere, $nom_mere, 
                    $profession_pere, $profession_mere, $telephone_parent, 
                    $personne_contact, $telephone_contact, $relation_contact,
                    $classe_id, $current_year['id'], date('Y-m-d')
                ]
            );
            
            $student_id = $database->lastInsertId();
            
            // Créer l'enregistrement financier si des frais sont spécifiés
            if ($frais_inscription > 0 || $frais_scolarite > 0) {
                $montant_total = ($frais_inscription + $frais_scolarite) * (1 - $reduction_accordee / 100);
                
                $database->execute(
                    "INSERT INTO frais_eleves (
                        eleve_id, annee_scolaire_id, frais_inscription, frais_scolarite, 
                        reduction_accordee, montant_total, created_at
                    ) VALUES (?, ?, ?, ?, ?, ?, NOW())",
                    [
                        $student_id, $current_year['id'], $frais_inscription, 
                        $frais_scolarite, $reduction_accordee, $montant_total
                    ]
                );
            }
            
            $database->commit();
            
            showMessage('success', "Élève inscrit directement avec succès. Numéro d'élève : $numero_eleve");
            
            // Rediriger vers le dossier de l'élève
            redirectTo("../records/view.php?id=$student_id");
            
        } catch (Exception $e) {
            $database->rollback();
            throw $e;
        }
        
    } catch (Exception $e) {
        showMessage('error', 'Erreur lors de l\'inscription : ' . $e->getMessage());
    }
}

// Récupérer les classes disponibles
try {
    $classes = $database->query("SELECT * FROM classes ORDER BY niveau, nom")->fetchAll();
} catch (Exception $e) {
    $classes = [];
    showMessage('error', 'Erreur lors du chargement des classes : ' . $e->getMessage());
}

$page_title = "Inscription Directe";
include '../../../includes/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">
        <i class="fas fa-user-plus me-2"></i>
        Inscription Directe
    </h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <div class="btn-group me-2">
            <a href="index.php" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-1"></i>
                Retour aux Admissions
            </a>
        </div>
        <div class="btn-group me-2">
            <a href="new-application.php" class="btn btn-outline-info">
                <i class="fas fa-file-alt me-1"></i>
                Demande normale
            </a>
        </div>
    </div>
</div>

<div class="alert alert-warning">
    <i class="fas fa-exclamation-triangle me-2"></i>
    <strong>Inscription directe :</strong> Cette fonction permet d'inscrire immédiatement un élève 
    sans passer par le processus normal de candidature. À utiliser uniquement dans des cas exceptionnels 
    (réinscription, transfert urgent, etc.).
</div>

<form method="POST" class="needs-validation" novalidate>
    <div class="row">
        <div class="col-md-8">
            <!-- Informations personnelles -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-user me-2"></i>
                        Informations personnelles
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="nom" class="form-label">
                                Nom <span class="text-danger">*</span>
                            </label>
                            <input type="text" class="form-control" id="nom" name="nom" 
                                   value="<?php echo htmlspecialchars($_POST['nom'] ?? ''); ?>" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="prenom" class="form-label">
                                Prénom <span class="text-danger">*</span>
                            </label>
                            <input type="text" class="form-control" id="prenom" name="prenom" 
                                   value="<?php echo htmlspecialchars($_POST['prenom'] ?? ''); ?>" required>
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
                            <label for="classe_id" class="form-label">
                                Classe d'inscription <span class="text-danger">*</span>
                            </label>
                            <select class="form-select" id="classe_id" name="classe_id" required>
                                <option value="">-- Sélectionner une classe --</option>
                                <?php foreach ($classes as $classe): ?>
                                    <option value="<?php echo $classe['id']; ?>" 
                                            <?php echo (intval($_POST['classe_id'] ?? 0) === $classe['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($classe['nom'] . ' - ' . $classe['niveau']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="telephone" class="form-label">
                                Téléphone élève
                            </label>
                            <input type="tel" class="form-control" id="telephone" name="telephone" 
                                   value="<?php echo htmlspecialchars($_POST['telephone'] ?? ''); ?>">
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="email" class="form-label">
                                Email
                            </label>
                            <input type="email" class="form-control" id="email" name="email" 
                                   value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="adresse" class="form-label">
                                Adresse
                            </label>
                            <textarea class="form-control" id="adresse" name="adresse" rows="2"><?php echo htmlspecialchars($_POST['adresse'] ?? ''); ?></textarea>
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
                        <div class="col-md-6 mb-3">
                            <label for="nom_pere" class="form-label">
                                Nom du père
                            </label>
                            <input type="text" class="form-control" id="nom_pere" name="nom_pere" 
                                   value="<?php echo htmlspecialchars($_POST['nom_pere'] ?? ''); ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="profession_pere" class="form-label">
                                Profession du père
                            </label>
                            <input type="text" class="form-control" id="profession_pere" name="profession_pere" 
                                   value="<?php echo htmlspecialchars($_POST['profession_pere'] ?? ''); ?>">
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="nom_mere" class="form-label">
                                Nom de la mère
                            </label>
                            <input type="text" class="form-control" id="nom_mere" name="nom_mere" 
                                   value="<?php echo htmlspecialchars($_POST['nom_mere'] ?? ''); ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="profession_mere" class="form-label">
                                Profession de la mère
                            </label>
                            <input type="text" class="form-control" id="profession_mere" name="profession_mere" 
                                   value="<?php echo htmlspecialchars($_POST['profession_mere'] ?? ''); ?>">
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label for="telephone_parent" class="form-label">
                                Téléphone parent <span class="text-danger">*</span>
                            </label>
                            <input type="tel" class="form-control" id="telephone_parent" name="telephone_parent" 
                                   value="<?php echo htmlspecialchars($_POST['telephone_parent'] ?? ''); ?>" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="personne_contact" class="form-label">
                                Personne de contact
                            </label>
                            <input type="text" class="form-control" id="personne_contact" name="personne_contact" 
                                   value="<?php echo htmlspecialchars($_POST['personne_contact'] ?? ''); ?>">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="telephone_contact" class="form-label">
                                Téléphone contact
                            </label>
                            <input type="tel" class="form-control" id="telephone_contact" name="telephone_contact" 
                                   value="<?php echo htmlspecialchars($_POST['telephone_contact'] ?? ''); ?>">
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="relation_contact" class="form-label">
                            Relation avec l'élève
                        </label>
                        <input type="text" class="form-control" id="relation_contact" name="relation_contact" 
                               value="<?php echo htmlspecialchars($_POST['relation_contact'] ?? ''); ?>" 
                               placeholder="Ex: Oncle, Tante, Tuteur...">
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-4">
            <!-- Informations financières -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-money-bill me-2"></i>
                        Frais scolaires
                    </h5>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label for="frais_inscription" class="form-label">
                            Frais d'inscription (FC)
                        </label>
                        <input type="number" class="form-control" id="frais_inscription" name="frais_inscription" 
                               value="<?php echo htmlspecialchars($_POST['frais_inscription'] ?? ''); ?>" 
                               min="0" step="1000">
                    </div>
                    
                    <div class="mb-3">
                        <label for="frais_scolarite" class="form-label">
                            Frais de scolarité (FC)
                        </label>
                        <input type="number" class="form-control" id="frais_scolarite" name="frais_scolarite" 
                               value="<?php echo htmlspecialchars($_POST['frais_scolarite'] ?? ''); ?>" 
                               min="0" step="1000">
                    </div>
                    
                    <div class="mb-3">
                        <label for="reduction_accordee" class="form-label">
                            Réduction accordée (%)
                        </label>
                        <input type="number" class="form-control" id="reduction_accordee" name="reduction_accordee" 
                               value="<?php echo htmlspecialchars($_POST['reduction_accordee'] ?? '0'); ?>" 
                               min="0" max="100" step="5">
                    </div>
                    
                    <div class="alert alert-info">
                        <i class="fas fa-calculator me-2"></i>
                        <strong>Total :</strong>
                        <div id="montant_total" class="mt-2">0 FC</div>
                    </div>
                </div>
            </div>
            
            <!-- Actions -->
            <div class="card">
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-user-check me-1"></i>
                            Inscrire l'élève
                        </button>
                        <a href="index.php" class="btn btn-secondary">
                            <i class="fas fa-times me-1"></i>
                            Annuler
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</form>

<script>
// Calcul automatique du montant total
document.addEventListener('DOMContentLoaded', function() {
    const fraisInscription = document.getElementById('frais_inscription');
    const fraisScolarite = document.getElementById('frais_scolarite');
    const reduction = document.getElementById('reduction_accordee');
    const montantTotal = document.getElementById('montant_total');
    
    function calculateTotal() {
        const inscription = parseFloat(fraisInscription.value) || 0;
        const scolarite = parseFloat(fraisScolarite.value) || 0;
        const reductionPct = parseFloat(reduction.value) || 0;
        
        const total = (inscription + scolarite) * (1 - reductionPct / 100);
        
        montantTotal.textContent = total.toLocaleString() + ' FC';
    }
    
    fraisInscription.addEventListener('input', calculateTotal);
    fraisScolarite.addEventListener('input', calculateTotal);
    reduction.addEventListener('input', calculateTotal);
    
    // Calcul initial
    calculateTotal();
});

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
