<?php
/**
 * Nouveau transfert entrant d'élève
 * Application de gestion scolaire - République Démocratique du Congo
 */

require_once '../../../config/config.php';
require_once '../../../config/database.php';
require_once '../../../includes/functions.php';

// Vérifier l'authentification et les permissions
requireLogin();
if (!checkPermission('students')) {
    redirectTo('../../../login.php');
}

$page_title = "Nouveau transfert entrant";

// Récupérer l'année scolaire active
$current_year = $database->query("SELECT * FROM annees_scolaires WHERE status = 'active' LIMIT 1")->fetch();

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_transfer'])) {
    try {
        // Validation des données
        $required_fields = ['nom', 'prenom', 'date_naissance', 'ecole_origine', 'classe_destination_id', 'motif', 'date_demande'];
        foreach ($required_fields as $field) {
            if (empty($_POST[$field])) {
                throw new Exception("Le champ " . str_replace('_', ' ', $field) . " est requis");
            }
        }
        
        // Vérifier si l'élève existe déjà
        $existing_student = null;
        if (!empty($_POST['numero_matricule'])) {
            $existing_student = $database->query(
                "SELECT * FROM eleves WHERE numero_matricule = ?",
                [$_POST['numero_matricule']]
            )->fetch();
        }
        
        $database->beginTransaction();
        
        $eleve_id = null;
        
        if ($existing_student) {
            // Élève existant
            $eleve_id = $existing_student['id'];
        } else {
            // Créer un nouvel élève
            $numero_matricule = $_POST['numero_matricule'] ?: generateMatricule();
            
            $sql_eleve = "INSERT INTO eleves (numero_matricule, nom, prenom, date_naissance, lieu_naissance, sexe, adresse, telephone_parent, email_parent, nom_pere, nom_mere, profession_pere, profession_mere, created_at) 
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
            
            $database->query($sql_eleve, [
                $numero_matricule,
                $_POST['nom'],
                $_POST['prenom'],
                $_POST['date_naissance'],
                $_POST['lieu_naissance'] ?? '',
                $_POST['sexe'] ?? 'M',
                $_POST['adresse'] ?? '',
                $_POST['telephone_parent'] ?? '',
                $_POST['email_parent'] ?? '',
                $_POST['nom_pere'] ?? '',
                $_POST['nom_mere'] ?? '',
                $_POST['profession_pere'] ?? '',
                $_POST['profession_mere'] ?? ''
            ]);
            
            $eleve_id = $database->lastInsertId();
        }
        
        // Créer l'inscription pour l'année en cours
        $sql_inscription = "INSERT INTO inscriptions (eleve_id, classe_id, annee_scolaire_id, date_inscription, statut) 
                           VALUES (?, ?, ?, ?, 'active')
                           ON DUPLICATE KEY UPDATE classe_id = VALUES(classe_id), statut = 'active'";
        
        $database->query($sql_inscription, [
            $eleve_id,
            $_POST['classe_destination_id'],
            $current_year['id'],
            $_POST['date_demande']
        ]);
        
        // Créer le transfert
        $sql_transfer = "INSERT INTO transfers (eleve_id, type_mouvement, ecole_origine, ecole_destination, classe_destination_id, motif, date_demande, statut, frais_transfert, observations, traite_par, date_traitement) 
                        VALUES (?, 'transfert_entrant', ?, 'Notre École', ?, ?, ?, 'en_attente', ?, ?, ?, NOW())";
        
        $database->query($sql_transfer, [
            $eleve_id,
            $_POST['ecole_origine'],
            $_POST['classe_destination_id'],
            $_POST['motif'],
            $_POST['date_demande'],
            $_POST['frais_transfert'] ?? 0,
            $_POST['observations'] ?? '',
            $_SESSION['user_id']
        ]);
        
        $transfer_id = $database->lastInsertId();
        
        // Ajouter les documents requis
        $documents_requis = [
            ['nom' => 'Bulletin scolaire', 'type' => 'bulletin', 'obligatoire' => true],
            ['nom' => 'Certificat de scolarité', 'type' => 'certificat_scolarite', 'obligatoire' => true],
            ['nom' => 'Acte de naissance', 'type' => 'acte_naissance', 'obligatoire' => true],
            ['nom' => 'Photo d\'identité', 'type' => 'photo', 'obligatoire' => false]
        ];
        
        foreach ($documents_requis as $doc) {
            $sql_doc = "INSERT INTO transfer_documents (transfer_id, nom_document, type_document, obligatoire) VALUES (?, ?, ?, ?)";
            $database->query($sql_doc, [$transfer_id, $doc['nom'], $doc['type'], $doc['obligatoire']]);
        }
        
        // Ajouter les frais
        if (!empty($_POST['frais_transfert']) && $_POST['frais_transfert'] > 0) {
            $sql_frais = "INSERT INTO transfer_fees (transfer_id, type_frais, libelle, montant) VALUES (?, 'frais_transfert', 'Frais de transfert', ?)";
            $database->query($sql_frais, [$transfer_id, $_POST['frais_transfert']]);
        }
        
        // Enregistrer l'historique
        $sql_history = "INSERT INTO transfer_history (transfer_id, action, nouveau_statut, commentaire, user_id) VALUES (?, 'creation', 'en_attente', 'Transfert entrant créé', ?)";
        $database->query($sql_history, [$transfer_id, $_SESSION['user_id']]);
        
        // Logger l'action
        logUserAction('create_transfer', 'transfers', "Nouveau transfert entrant créé pour l'élève ID: $eleve_id", $transfer_id);
        
        $database->commit();
        
        showMessage('success', 'Transfert entrant créé avec succès !');
        redirectTo("view-transfer.php?id=$transfer_id");
        
    } catch (Exception $e) {
        $database->rollBack();
        showMessage('error', $e->getMessage());
    }
}

// Récupérer les classes disponibles
$classes = $database->query(
    "SELECT * FROM classes WHERE annee_scolaire_id = ? ORDER BY niveau, nom",
    [$current_year['id'] ?? 0]
)->fetchAll();


include '../../../includes/header.php';
?>

<!-- Styles CSS modernes -->
<style>
.transfer-header {
    background: linear-gradient(135deg, #007bff 0%, #6610f2 100%);
    color: white;
    padding: 2rem 0;
    margin: -20px -15px 30px -15px;
    border-radius: 0 0 20px 20px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.1);
}

.transfer-header h1 {
    font-weight: 300;
    font-size: 2.5rem;
    margin-bottom: 0.5rem;
}

.transfer-header .subtitle {
    opacity: 0.9;
    font-size: 1.1rem;
}

.form-card {
    background: white;
    border-radius: 15px;
    padding: 2rem;
    box-shadow: 0 5px 15px rgba(0,0,0,0.08);
    border: none;
    margin-bottom: 2rem;
}

.form-section {
    background: #f8f9fa;
    border-radius: 10px;
    padding: 1.5rem;
    margin-bottom: 1.5rem;
    border-left: 4px solid #007bff;
}

.form-section h6 {
    color: #007bff;
    font-weight: 600;
    margin-bottom: 1rem;
    display: flex;
    align-items: center;
}

.form-section h6 i {
    margin-right: 0.5rem;
}

.btn-modern {
    border-radius: 25px;
    padding: 0.75rem 2rem;
    font-weight: 600;
    transition: all 0.3s ease;
    border: none;
    box-shadow: 0 3px 15px rgba(0,0,0,0.1);
}

.btn-modern:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 25px rgba(0,0,0,0.2);
}

.btn-primary.btn-modern {
    background: linear-gradient(135deg, #007bff 0%, #6610f2 100%);
}

.btn-secondary.btn-modern {
    background: linear-gradient(135deg, #6c757d 0%, #495057 100%);
}

@keyframes fadeInUp {
    from {
        opacity: 0;
        transform: translateY(30px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.animate-fade-in {
    animation: fadeInUp 0.6s ease-out;
}

.animate-delay-1 { animation-delay: 0.1s; }
.animate-delay-2 { animation-delay: 0.2s; }
.animate-delay-3 { animation-delay: 0.3s; }

@media (max-width: 768px) {
    .transfer-header {
        margin: -20px -15px 20px -15px;
        padding: 1.5rem 0;
    }
    
    .transfer-header h1 {
        font-size: 2rem;
    }
    
    .form-card {
        padding: 1rem;
    }
}
</style>

<!-- En-tête moderne -->
<div class="transfer-header">
    <div class="container-fluid">
        <div class="row align-items-center">
            <div class="col-md-8">
                <h1 class="animate-fade-in">
                    <i class="fas fa-user-plus me-3"></i>
                    Nouveau transfert entrant
                </h1>
                <p class="subtitle animate-fade-in animate-delay-1">
                    Enregistrer l'arrivée d'un nouvel élève par transfert
                </p>
            </div>
            <div class="col-md-4 text-end">
                <div class="animate-fade-in animate-delay-2">
                    <a href="../index.php" class="btn btn-light btn-modern">
                        <i class="fas fa-arrow-left me-2"></i>
                        Retour
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Formulaire de création -->
<div class="form-card animate-fade-in animate-delay-1">
    <form method="POST" id="transferForm">
        <input type="hidden" name="create_transfer" value="1">
        
        <!-- Informations de l'élève -->
        <div class="form-section">
            <h6><i class="fas fa-user"></i>Informations de l'élève</h6>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="numero_matricule" class="form-label">Numéro de matricule</label>
                    <input type="text" class="form-control form-control-lg" id="numero_matricule" name="numero_matricule" 
                           placeholder="Laisser vide pour génération automatique">
                    <small class="form-text text-muted">Si vide, un matricule sera généré automatiquement</small>
                </div>
                <div class="col-md-3 mb-3">
                    <label for="nom" class="form-label">Nom <span class="text-danger">*</span></label>
                    <input type="text" class="form-control form-control-lg" id="nom" name="nom" required>
                </div>
                <div class="col-md-3 mb-3">
                    <label for="prenom" class="form-label">Prénom <span class="text-danger">*</span></label>
                    <input type="text" class="form-control form-control-lg" id="prenom" name="prenom" required>
                </div>
            </div>
            
            <div class="row">
                <div class="col-md-4 mb-3">
                    <label for="date_naissance" class="form-label">Date de naissance <span class="text-danger">*</span></label>
                    <input type="date" class="form-control form-control-lg" id="date_naissance" name="date_naissance" required>
                </div>
                <div class="col-md-4 mb-3">
                    <label for="lieu_naissance" class="form-label">Lieu de naissance</label>
                    <input type="text" class="form-control form-control-lg" id="lieu_naissance" name="lieu_naissance">
                </div>
                <div class="col-md-4 mb-3">
                    <label for="sexe" class="form-label">Sexe</label>
                    <select class="form-select form-select-lg" id="sexe" name="sexe">
                        <option value="M">Masculin</option>
                        <option value="F">Féminin</option>
                    </select>
                </div>
            </div>
            
            <div class="row">
                <div class="col-md-12 mb-3">
                    <label for="adresse" class="form-label">Adresse</label>
                    <textarea class="form-control" id="adresse" name="adresse" rows="2"></textarea>
                </div>
            </div>
        </div>

        <!-- Informations des parents -->
        <div class="form-section">
            <h6><i class="fas fa-users"></i>Informations des parents</h6>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="nom_pere" class="form-label">Nom du père</label>
                    <input type="text" class="form-control form-control-lg" id="nom_pere" name="nom_pere">
                </div>
                <div class="col-md-6 mb-3">
                    <label for="nom_mere" class="form-label">Nom de la mère</label>
                    <input type="text" class="form-control form-control-lg" id="nom_mere" name="nom_mere">
                </div>
            </div>
            
            <div class="row">
                <div class="col-md-4 mb-3">
                    <label for="telephone_parent" class="form-label">Téléphone</label>
                    <input type="tel" class="form-control form-control-lg" id="telephone_parent" name="telephone_parent">
                </div>
                <div class="col-md-4 mb-3">
                    <label for="email_parent" class="form-label">Email</label>
                    <input type="email" class="form-control form-control-lg" id="email_parent" name="email_parent">
                </div>
                <div class="col-md-4 mb-3">
                    <label for="profession_pere" class="form-label">Profession du père</label>
                    <input type="text" class="form-control form-control-lg" id="profession_pere" name="profession_pere">
                </div>
            </div>
        </div>

        <!-- Informations du transfert -->
        <div class="form-section">
            <h6><i class="fas fa-exchange-alt"></i>Informations du transfert</h6>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="ecole_origine" class="form-label">École d'origine <span class="text-danger">*</span></label>
                    <input type="text" class="form-control form-control-lg" id="ecole_origine" name="ecole_origine" required>
                </div>
                <div class="col-md-6 mb-3">
                    <label for="classe_destination_id" class="form-label">Classe de destination <span class="text-danger">*</span></label>
                    <select class="form-select form-select-lg" id="classe_destination_id" name="classe_destination_id" required>
                        <option value="">Sélectionner une classe</option>
                        <?php foreach ($classes as $classe): ?>
                            <option value="<?php echo $classe['id']; ?>">
                                <?php echo htmlspecialchars($classe['niveau'] . ' - ' . $classe['nom']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="date_demande" class="form-label">Date de demande <span class="text-danger">*</span></label>
                    <input type="date" class="form-control form-control-lg" id="date_demande" name="date_demande" 
                           value="<?php echo date('Y-m-d'); ?>" required>
                </div>
                <div class="col-md-6 mb-3">
                    <label for="frais_transfert" class="form-label">Frais de transfert (FC)</label>
                    <input type="number" class="form-control form-control-lg" id="frais_transfert" name="frais_transfert" 
                           min="0" step="0.01" placeholder="0.00">
                </div>
            </div>
            
            <div class="row">
                <div class="col-md-12 mb-3">
                    <label for="motif" class="form-label">Motif du transfert <span class="text-danger">*</span></label>
                    <textarea class="form-control" id="motif" name="motif" rows="3" required 
                              placeholder="Expliquer la raison du transfert..."></textarea>
                </div>
            </div>
            
            <div class="row">
                <div class="col-md-12 mb-3">
                    <label for="observations" class="form-label">Observations</label>
                    <textarea class="form-control" id="observations" name="observations" rows="2" 
                              placeholder="Observations particulières..."></textarea>
                </div>
            </div>
        </div>

        <!-- Boutons d'action -->
        <div class="d-flex justify-content-between">
            <a href="../index.php" class="btn btn-secondary btn-modern">
                <i class="fas fa-times me-2"></i>
                Annuler
            </a>
            <button type="submit" class="btn btn-primary btn-modern">
                <i class="fas fa-save me-2"></i>
                Créer le transfert
            </button>
        </div>
    </form>
</div>

<script>
// Validation du formulaire
document.getElementById('transferForm').addEventListener('submit', function(e) {
    const requiredFields = ['nom', 'prenom', 'date_naissance', 'ecole_origine', 'classe_destination_id', 'motif', 'date_demande'];
    let isValid = true;
    
    requiredFields.forEach(field => {
        const element = document.getElementById(field);
        if (!element.value.trim()) {
            element.classList.add('is-invalid');
            isValid = false;
        } else {
            element.classList.remove('is-invalid');
        }
    });
    
    if (!isValid) {
        e.preventDefault();
        alert('Veuillez remplir tous les champs obligatoires');
        return false;
    }
    
    // Confirmation
    if (!confirm('Êtes-vous sûr de vouloir créer ce transfert entrant ?')) {
        e.preventDefault();
        return false;
    }
});

// Auto-génération du matricule si nécessaire
document.getElementById('nom').addEventListener('blur', function() {
    const matriculeField = document.getElementById('numero_matricule');
    if (!matriculeField.value) {
        // Optionnel : suggérer un matricule basé sur le nom
        // matriculeField.placeholder = 'MAT' + new Date().getFullYear() + '...';
    }
});
</script>

<?php include '../../../includes/footer.php'; ?>
