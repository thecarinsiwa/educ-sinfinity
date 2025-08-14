<?php
/**
 * Ajouter une nouvelle candidature
 * Application de gestion scolaire - République Démocratique du Congo
 */

require_once '../../../../config/config.php';
require_once '../../../../config/database.php';
require_once '../../../../includes/functions.php';

// Vérifier l'authentification et les permissions
requireLogin();
if (!checkPermission('students')) {
    showMessage('error', 'Accès refusé à ce module.');
    redirectTo('index.php');
}

$page_title = 'Nouvelle Candidature';

// Obtenir l'année scolaire actuelle
$current_year = getCurrentAcademicYear();

// Récupérer les classes disponibles
try {
    $classes = $database->query(
        "SELECT id, nom, niveau, section FROM classes WHERE annee_scolaire_id = ? ORDER BY niveau, nom",
        [$current_year['id'] ?? 0]
    )->fetchAll();
} catch (Exception $e) {
    $classes = [];
}

$errors = [];

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Récupérer et valider les données
    $nom_eleve = sanitizeInput($_POST['nom_eleve'] ?? '');
    $prenom_eleve = sanitizeInput($_POST['prenom_eleve'] ?? '');
    $date_naissance = $_POST['date_naissance'] ?? '';
    $lieu_naissance = sanitizeInput($_POST['lieu_naissance'] ?? '');
    $sexe = $_POST['sexe'] ?? '';
    $classe_demandee_id = intval($_POST['classe_demandee_id'] ?? 0);
    $priorite = $_POST['priorite'] ?? 'normale';
    
    // Informations de contact
    $adresse = sanitizeInput($_POST['adresse'] ?? '');
    $telephone = sanitizeInput($_POST['telephone'] ?? '');
    $email = sanitizeInput($_POST['email'] ?? '');
    
    // Informations des parents
    $nom_pere = sanitizeInput($_POST['nom_pere'] ?? '');
    $nom_mere = sanitizeInput($_POST['nom_mere'] ?? '');
    $profession_pere = sanitizeInput($_POST['profession_pere'] ?? '');
    $profession_mere = sanitizeInput($_POST['profession_mere'] ?? '');
    $telephone_parent = sanitizeInput($_POST['telephone_parent'] ?? '');
    
    // Personne de contact
    $personne_contact = sanitizeInput($_POST['personne_contact'] ?? '');
    $telephone_contact = sanitizeInput($_POST['telephone_contact'] ?? '');
    $relation_contact = sanitizeInput($_POST['relation_contact'] ?? '');
    
    // École précédente
    $ecole_precedente = sanitizeInput($_POST['ecole_precedente'] ?? '');
    $classe_precedente = sanitizeInput($_POST['classe_precedente'] ?? '');
    $annee_precedente = sanitizeInput($_POST['annee_precedente'] ?? '');
    $moyenne_precedente = floatval($_POST['moyenne_precedente'] ?? 0);
    
    // Documents (conversion en entier pour la base de données)
    $certificat_naissance = isset($_POST['certificat_naissance']) ? 1 : 0;
    $bulletin_precedent = isset($_POST['bulletin_precedent']) ? 1 : 0;
    $certificat_medical = isset($_POST['certificat_medical']) ? 1 : 0;
    $photo_identite = isset($_POST['photo_identite']) ? 1 : 0;
    $autres_documents = sanitizeInput($_POST['autres_documents'] ?? '');
    
    // Informations supplémentaires
    $motif_demande = sanitizeInput($_POST['motif_demande'] ?? '');
    $besoins_speciaux = sanitizeInput($_POST['besoins_speciaux'] ?? '');
    $allergies_medicales = sanitizeInput($_POST['allergies_medicales'] ?? '');
    $observations = sanitizeInput($_POST['observations'] ?? '');
    
    // Validation
    if (empty($nom_eleve)) $errors[] = 'Le nom de l\'élève est obligatoire.';
    if (empty($prenom_eleve)) $errors[] = 'Le prénom de l\'élève est obligatoire.';
    if (empty($date_naissance)) $errors[] = 'La date de naissance est obligatoire.';
    if (empty($sexe)) $errors[] = 'Le sexe est obligatoire.';
    if (!$classe_demandee_id) $errors[] = 'La classe demandée est obligatoire.';
    if (empty($telephone_parent)) $errors[] = 'Le téléphone des parents est obligatoire.';
    
    // Validation de la date de naissance
    if ($date_naissance) {
        $date_obj = DateTime::createFromFormat('Y-m-d', $date_naissance);
        if (!$date_obj || $date_obj->format('Y-m-d') !== $date_naissance) {
            $errors[] = 'Format de date de naissance invalide.';
        }
    }
    
    // Validation de l'email
    if ($email && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Format d\'email invalide.';
    }
    
    // Validation de la moyenne
    if ($moyenne_precedente && ($moyenne_precedente < 0 || $moyenne_precedente > 20)) {
        $errors[] = 'La moyenne précédente doit être entre 0 et 20.';
    }
    
    if (empty($errors)) {
        try {
            // Générer un numéro de demande unique
            $numero_demande = 'ADM' . date('Y') . str_pad(
                $database->query("SELECT COUNT(*) + 1 as next_num FROM demandes_admission WHERE YEAR(created_at) = YEAR(NOW())")->fetch()['next_num'],
                3, '0', STR_PAD_LEFT
            );
            
            // Insérer la candidature
            $sql = "INSERT INTO demandes_admission (
                numero_demande, annee_scolaire_id, classe_demandee_id, nom_eleve, prenom_eleve,
                date_naissance, lieu_naissance, sexe, adresse, telephone, email,
                nom_pere, nom_mere, profession_pere, profession_mere, telephone_parent,
                personne_contact, telephone_contact, relation_contact,
                ecole_precedente, classe_precedente, annee_precedente, moyenne_precedente,
                certificat_naissance, bulletin_precedent, certificat_medical, photo_identite, autres_documents,
                motif_demande, besoins_speciaux, allergies_medicales, observations,
                status, priorite, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
            
            $database->execute($sql, [
                $numero_demande, $current_year['id'], $classe_demandee_id, $nom_eleve, $prenom_eleve,
                $date_naissance, $lieu_naissance, $sexe, $adresse, $telephone, $email,
                $nom_pere, $nom_mere, $profession_pere, $profession_mere, $telephone_parent,
                $personne_contact, $telephone_contact, $relation_contact,
                $ecole_precedente, $classe_precedente, $annee_precedente, $moyenne_precedente ?: null,
                $certificat_naissance, $bulletin_precedent, $certificat_medical, $photo_identite, $autres_documents,
                $motif_demande, $besoins_speciaux, $allergies_medicales, $observations,
                'en_attente', $priorite
            ]);
            
            $candidature_id = $database->lastInsertId();
            
            showMessage('success', "Candidature créée avec succès. Numéro : $numero_demande");
            redirectTo("view.php?id=$candidature_id");
            
        } catch (Exception $e) {
            $errors[] = 'Erreur lors de la création de la candidature : ' . $e->getMessage();
        }
    }
}

include '../../../../includes/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">
        <i class="fas fa-plus me-2"></i>
        Nouvelle Candidature
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

<form method="POST" class="needs-validation" novalidate>
    <!-- Informations de base -->
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="mb-0">
                <i class="fas fa-user me-2"></i>
                Informations de Base
            </h5>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-4 mb-3">
                    <label for="nom_eleve" class="form-label">Nom de l'élève <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="nom_eleve" name="nom_eleve" 
                           value="<?php echo htmlspecialchars($_POST['nom_eleve'] ?? ''); ?>" required>
                </div>
                <div class="col-md-4 mb-3">
                    <label for="prenom_eleve" class="form-label">Prénom de l'élève <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="prenom_eleve" name="prenom_eleve" 
                           value="<?php echo htmlspecialchars($_POST['prenom_eleve'] ?? ''); ?>" required>
                </div>
                <div class="col-md-4 mb-3">
                    <label for="sexe" class="form-label">Sexe <span class="text-danger">*</span></label>
                    <select class="form-select" id="sexe" name="sexe" required>
                        <option value="">Sélectionner...</option>
                        <option value="M" <?php echo ($_POST['sexe'] ?? '') === 'M' ? 'selected' : ''; ?>>Masculin</option>
                        <option value="F" <?php echo ($_POST['sexe'] ?? '') === 'F' ? 'selected' : ''; ?>>Féminin</option>
                    </select>
                </div>
            </div>
            <div class="row">
                <div class="col-md-4 mb-3">
                    <label for="date_naissance" class="form-label">Date de naissance <span class="text-danger">*</span></label>
                    <input type="date" class="form-control" id="date_naissance" name="date_naissance" 
                           value="<?php echo htmlspecialchars($_POST['date_naissance'] ?? ''); ?>" required>
                </div>
                <div class="col-md-4 mb-3">
                    <label for="lieu_naissance" class="form-label">Lieu de naissance</label>
                    <input type="text" class="form-control" id="lieu_naissance" name="lieu_naissance" 
                           value="<?php echo htmlspecialchars($_POST['lieu_naissance'] ?? ''); ?>">
                </div>
                <div class="col-md-4 mb-3">
                    <label for="classe_demandee_id" class="form-label">Classe demandée <span class="text-danger">*</span></label>
                    <select class="form-select" id="classe_demandee_id" name="classe_demandee_id" required>
                        <option value="">Sélectionner une classe...</option>
                        <?php foreach ($classes as $classe): ?>
                            <option value="<?php echo $classe['id']; ?>" 
                                    <?php echo ($_POST['classe_demandee_id'] ?? '') == $classe['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($classe['nom'] . ' (' . $classe['niveau'] . ')'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="priorite" class="form-label">Priorité</label>
                    <select class="form-select" id="priorite" name="priorite">
                        <option value="normale" <?php echo ($_POST['priorite'] ?? 'normale') === 'normale' ? 'selected' : ''; ?>>Normale</option>
                        <option value="urgente" <?php echo ($_POST['priorite'] ?? '') === 'urgente' ? 'selected' : ''; ?>>Urgente</option>
                        <option value="tres_urgente" <?php echo ($_POST['priorite'] ?? '') === 'tres_urgente' ? 'selected' : ''; ?>>Très urgente</option>
                    </select>
                </div>
            </div>
        </div>
    </div>

    <!-- Informations de contact -->
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="mb-0">
                <i class="fas fa-address-book me-2"></i>
                Informations de Contact
            </h5>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-12 mb-3">
                    <label for="adresse" class="form-label">Adresse</label>
                    <textarea class="form-control" id="adresse" name="adresse" rows="2"><?php echo htmlspecialchars($_POST['adresse'] ?? ''); ?></textarea>
                </div>
            </div>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="telephone" class="form-label">Téléphone de l'élève</label>
                    <input type="tel" class="form-control" id="telephone" name="telephone" 
                           value="<?php echo htmlspecialchars($_POST['telephone'] ?? ''); ?>">
                </div>
                <div class="col-md-6 mb-3">
                    <label for="email" class="form-label">Email</label>
                    <input type="email" class="form-control" id="email" name="email" 
                           value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
                </div>
            </div>
        </div>
    </div>

    <!-- Informations des parents -->
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="mb-0">
                <i class="fas fa-users me-2"></i>
                Informations des Parents
            </h5>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="nom_pere" class="form-label">Nom du père</label>
                    <input type="text" class="form-control" id="nom_pere" name="nom_pere" 
                           value="<?php echo htmlspecialchars($_POST['nom_pere'] ?? ''); ?>">
                </div>
                <div class="col-md-6 mb-3">
                    <label for="profession_pere" class="form-label">Profession du père</label>
                    <input type="text" class="form-control" id="profession_pere" name="profession_pere" 
                           value="<?php echo htmlspecialchars($_POST['profession_pere'] ?? ''); ?>">
                </div>
            </div>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="nom_mere" class="form-label">Nom de la mère</label>
                    <input type="text" class="form-control" id="nom_mere" name="nom_mere" 
                           value="<?php echo htmlspecialchars($_POST['nom_mere'] ?? ''); ?>">
                </div>
                <div class="col-md-6 mb-3">
                    <label for="profession_mere" class="form-label">Profession de la mère</label>
                    <input type="text" class="form-control" id="profession_mere" name="profession_mere" 
                           value="<?php echo htmlspecialchars($_POST['profession_mere'] ?? ''); ?>">
                </div>
            </div>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="telephone_parent" class="form-label">Téléphone des parents <span class="text-danger">*</span></label>
                    <input type="tel" class="form-control" id="telephone_parent" name="telephone_parent" 
                           value="<?php echo htmlspecialchars($_POST['telephone_parent'] ?? ''); ?>" required>
                </div>
            </div>
        </div>
    </div>

    <!-- Personne de contact -->
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="mb-0">
                <i class="fas fa-phone me-2"></i>
                Personne de Contact (si différente des parents)
            </h5>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-4 mb-3">
                    <label for="personne_contact" class="form-label">Nom de la personne</label>
                    <input type="text" class="form-control" id="personne_contact" name="personne_contact" 
                           value="<?php echo htmlspecialchars($_POST['personne_contact'] ?? ''); ?>">
                </div>
                <div class="col-md-4 mb-3">
                    <label for="telephone_contact" class="form-label">Téléphone</label>
                    <input type="tel" class="form-control" id="telephone_contact" name="telephone_contact" 
                           value="<?php echo htmlspecialchars($_POST['telephone_contact'] ?? ''); ?>">
                </div>
                <div class="col-md-4 mb-3">
                    <label for="relation_contact" class="form-label">Relation avec l'élève</label>
                    <input type="text" class="form-control" id="relation_contact" name="relation_contact" 
                           value="<?php echo htmlspecialchars($_POST['relation_contact'] ?? ''); ?>" 
                           placeholder="Ex: Oncle, Tante, Tuteur...">
                </div>
            </div>
        </div>
    </div>

    <!-- École précédente -->
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="mb-0">
                <i class="fas fa-school me-2"></i>
                École Précédente
            </h5>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="ecole_precedente" class="form-label">Nom de l'école</label>
                    <input type="text" class="form-control" id="ecole_precedente" name="ecole_precedente" 
                           value="<?php echo htmlspecialchars($_POST['ecole_precedente'] ?? ''); ?>">
                </div>
                <div class="col-md-3 mb-3">
                    <label for="classe_precedente" class="form-label">Dernière classe</label>
                    <input type="text" class="form-control" id="classe_precedente" name="classe_precedente" 
                           value="<?php echo htmlspecialchars($_POST['classe_precedente'] ?? ''); ?>">
                </div>
                <div class="col-md-3 mb-3">
                    <label for="annee_precedente" class="form-label">Année scolaire</label>
                    <input type="text" class="form-control" id="annee_precedente" name="annee_precedente" 
                           value="<?php echo htmlspecialchars($_POST['annee_precedente'] ?? ''); ?>" 
                           placeholder="Ex: 2023-2024">
                </div>
            </div>
            <div class="row">
                <div class="col-md-3 mb-3">
                    <label for="moyenne_precedente" class="form-label">Moyenne générale (/20)</label>
                    <input type="number" class="form-control" id="moyenne_precedente" name="moyenne_precedente" 
                           value="<?php echo htmlspecialchars($_POST['moyenne_precedente'] ?? ''); ?>" 
                           min="0" max="20" step="0.01">
                </div>
            </div>
        </div>
    </div>

    <!-- Documents requis -->
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="mb-0">
                <i class="fas fa-file-alt me-2"></i>
                Documents Requis
            </h5>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" id="certificat_naissance" name="certificat_naissance" 
                               <?php echo isset($_POST['certificat_naissance']) ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="certificat_naissance">
                            Certificat de naissance
                        </label>
                    </div>
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" id="bulletin_precedent" name="bulletin_precedent" 
                               <?php echo isset($_POST['bulletin_precedent']) ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="bulletin_precedent">
                            Bulletin de l'année précédente
                        </label>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" id="certificat_medical" name="certificat_medical" 
                               <?php echo isset($_POST['certificat_medical']) ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="certificat_medical">
                            Certificat médical
                        </label>
                    </div>
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" id="photo_identite" name="photo_identite" 
                               <?php echo isset($_POST['photo_identite']) ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="photo_identite">
                            Photo d'identité
                        </label>
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="col-12 mb-3">
                    <label for="autres_documents" class="form-label">Autres documents</label>
                    <textarea class="form-control" id="autres_documents" name="autres_documents" rows="2" 
                              placeholder="Listez ici d'autres documents fournis..."><?php echo htmlspecialchars($_POST['autres_documents'] ?? ''); ?></textarea>
                </div>
            </div>
        </div>
    </div>

    <!-- Informations supplémentaires -->
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="mb-0">
                <i class="fas fa-info-circle me-2"></i>
                Informations Supplémentaires
            </h5>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-12 mb-3">
                    <label for="motif_demande" class="form-label">Motif de la demande d'admission</label>
                    <textarea class="form-control" id="motif_demande" name="motif_demande" rows="3" 
                              placeholder="Expliquez pourquoi vous souhaitez inscrire votre enfant dans notre établissement..."><?php echo htmlspecialchars($_POST['motif_demande'] ?? ''); ?></textarea>
                </div>
            </div>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="besoins_speciaux" class="form-label">Besoins spéciaux</label>
                    <textarea class="form-control" id="besoins_speciaux" name="besoins_speciaux" rows="3" 
                              placeholder="Décrivez tout besoin spécial de l'élève..."><?php echo htmlspecialchars($_POST['besoins_speciaux'] ?? ''); ?></textarea>
                </div>
                <div class="col-md-6 mb-3">
                    <label for="allergies_medicales" class="form-label">Allergies médicales</label>
                    <textarea class="form-control" id="allergies_medicales" name="allergies_medicales" rows="3" 
                              placeholder="Mentionnez toute allergie ou condition médicale..."><?php echo htmlspecialchars($_POST['allergies_medicales'] ?? ''); ?></textarea>
                </div>
            </div>
            <div class="row">
                <div class="col-12 mb-3">
                    <label for="observations" class="form-label">Observations générales</label>
                    <textarea class="form-control" id="observations" name="observations" rows="3" 
                              placeholder="Toute autre information utile..."><?php echo htmlspecialchars($_POST['observations'] ?? ''); ?></textarea>
                </div>
            </div>
        </div>
    </div>

    <!-- Boutons d'action -->
    <div class="card">
        <div class="card-body">
            <div class="d-flex justify-content-between">
                <a href="index.php" class="btn btn-secondary">
                    <i class="fas fa-times me-1"></i>
                    Annuler
                </a>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save me-1"></i>
                    Enregistrer la candidature
                </button>
            </div>
        </div>
    </div>
</form>

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

<?php include '../../../../includes/footer.php'; ?>
