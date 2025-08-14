<?php
/**
 * Nouvelle sortie d'élève (transfert sortant ou sortie définitive)
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

$page_title = "Nouvelle sortie d'élève";

// Récupérer l'année scolaire active
$current_year = $database->query("SELECT * FROM annees_scolaires WHERE status = 'active' LIMIT 1")->fetch();

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_exit'])) {
    try {
        // Validation des données
        $required_fields = ['eleve_id', 'type_mouvement', 'motif', 'date_demande'];
        foreach ($required_fields as $field) {
            if (empty($_POST[$field])) {
                throw new Exception("Le champ " . str_replace('_', ' ', $field) . " est requis");
            }
        }
        
        // Vérifier que l'élève existe et est actif
        $eleve = $database->query(
            "SELECT e.*, i.classe_id, c.nom as classe_nom, c.niveau 
             FROM eleves e 
             JOIN inscriptions i ON e.id = i.eleve_id 
             JOIN classes c ON i.classe_id = c.id 
             WHERE e.id = ? AND i.annee_scolaire_id = ? AND i.status = 'active'",
            [$_POST['eleve_id'], $current_year['id']]
        )->fetch();
        
        if (!$eleve) {
            throw new Exception("Élève non trouvé ou non inscrit pour l'année en cours");
        }
        
        // Vérifier qu'il n'y a pas déjà une demande de sortie en cours
        $existing_transfer = $database->query(
            "SELECT * FROM transfers WHERE eleve_id = ? AND type_mouvement IN ('transfert_sortant', 'sortie_definitive') AND statut IN ('en_attente', 'approuve')",
            [$_POST['eleve_id']]
        )->fetch();
        
        if ($existing_transfer) {
            throw new Exception("Une demande de sortie est déjà en cours pour cet élève");
        }
        
        $database->beginTransaction();
        
        // Créer le transfert/sortie
        $sql_transfer = "INSERT INTO transfers (eleve_id, type_mouvement, ecole_origine, ecole_destination, classe_origine_id, motif, date_demande, date_effective, statut, frais_transfert, observations, traite_par, date_traitement) 
                        VALUES (?, ?, 'Notre École', ?, ?, ?, ?, ?, 'en_attente', ?, ?, ?, NOW())";
        
        $database->query($sql_transfer, [
            $_POST['eleve_id'],
            $_POST['type_mouvement'],
            $_POST['ecole_destination'] ?? null,
            $eleve['classe_id'],
            $_POST['motif'],
            $_POST['date_demande'],
            $_POST['date_effective'] ?? null,
            $_POST['frais_transfert'] ?? 0,
            $_POST['observations'] ?? '',
            $_SESSION['user_id']
        ]);
        
        $transfer_id = $database->lastInsertId();
        
        // Ajouter les documents requis selon le type
        $documents_requis = [];
        
        if ($_POST['type_mouvement'] === 'transfert_sortant') {
            $documents_requis = [
                ['nom' => 'Demande de transfert', 'type' => 'autre', 'obligatoire' => true],
                ['nom' => 'Bulletin scolaire', 'type' => 'bulletin', 'obligatoire' => true],
                ['nom' => 'Certificat de scolarité', 'type' => 'certificat_scolarite', 'obligatoire' => true],
                ['nom' => 'Quitus financier', 'type' => 'autre', 'obligatoire' => true]
            ];
        } else { // sortie_definitive
            $documents_requis = [
                ['nom' => 'Demande de sortie', 'type' => 'autre', 'obligatoire' => true],
                ['nom' => 'Bulletin scolaire final', 'type' => 'bulletin', 'obligatoire' => true],
                ['nom' => 'Quitus financier', 'type' => 'autre', 'obligatoire' => true]
            ];
        }
        
        foreach ($documents_requis as $doc) {
            $sql_doc = "INSERT INTO transfer_documents (transfer_id, nom_document, type_document, obligatoire) VALUES (?, ?, ?, ?)";
            $database->query($sql_doc, [$transfer_id, $doc['nom'], $doc['type'], $doc['obligatoire']]);
        }
        
        // Ajouter les frais
        $frais_types = [];
        if ($_POST['type_mouvement'] === 'transfert_sortant') {
            $frais_types = [
                ['type' => 'frais_transfert', 'libelle' => 'Frais de transfert', 'montant' => $_POST['frais_transfert'] ?? 0],
                ['type' => 'frais_certificat', 'libelle' => 'Frais de certificat', 'montant' => 15000]
            ];
        } else {
            $frais_types = [
                ['type' => 'frais_certificat', 'libelle' => 'Frais de certificat de fin d\'études', 'montant' => 25000]
            ];
        }
        
        foreach ($frais_types as $frais) {
            if ($frais['montant'] > 0) {
                $sql_frais = "INSERT INTO transfer_fees (transfer_id, type_frais, libelle, montant) VALUES (?, ?, ?, ?)";
                $database->query($sql_frais, [$transfer_id, $frais['type'], $frais['libelle'], $frais['montant']]);
            }
        }
        
        // Enregistrer l'historique
        $action_label = $_POST['type_mouvement'] === 'transfert_sortant' ? 'Demande de transfert sortant' : 'Demande de sortie définitive';
        $sql_history = "INSERT INTO transfer_history (transfer_id, action, nouveau_statut, commentaire, user_id) VALUES (?, 'creation', 'en_attente', ?, ?)";
        $database->query($sql_history, [$transfer_id, $action_label, $_SESSION['user_id']]);
        
        // Logger l'action
        logUserAction('create_exit', 'transfers', "Nouvelle sortie créée pour l'élève ID: {$_POST['eleve_id']}", $transfer_id);
        
        $database->commit();
        
        showMessage('success', 'Demande de sortie créée avec succès !');
        redirectTo("view-transfer.php?id=$transfer_id");
        
    } catch (Exception $e) {
        $database->rollBack();
        showMessage('error', $e->getMessage());
    }
}

// Récupérer les élèves actifs
$eleves = $database->query(
    "SELECT e.*, i.classe_id, c.nom as classe_nom, c.niveau 
     FROM eleves e 
     JOIN inscriptions i ON e.id = i.eleve_id 
     JOIN classes c ON i.classe_id = c.id 
    WHERE i.annee_scolaire_id = ? AND i.status = 'active'
     ORDER BY e.nom, e.prenom",
    [$current_year['id'] ?? 0]
)->fetchAll();

include '../../../includes/header.php';
?>

<!-- Styles CSS modernes -->
<style>
.exit-header {
    background: linear-gradient(135deg, #dc3545 0%, #fd7e14 100%);
    color: white;
    padding: 2rem 0;
    margin: -20px -15px 30px -15px;
    border-radius: 0 0 20px 20px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.1);
}

.exit-header h1 {
    font-weight: 300;
    font-size: 2.5rem;
    margin-bottom: 0.5rem;
}

.exit-header .subtitle {
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
    border-left: 4px solid #dc3545;
}

.form-section h6 {
    color: #dc3545;
    font-weight: 600;
    margin-bottom: 1rem;
    display: flex;
    align-items: center;
}

.form-section h6 i {
    margin-right: 0.5rem;
}

.student-info {
    background: linear-gradient(135deg, #e3f2fd 0%, #f3e5f5 100%);
    border-radius: 10px;
    padding: 1.5rem;
    margin-bottom: 1.5rem;
    border-left: 4px solid #2196f3;
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

.btn-danger.btn-modern {
    background: linear-gradient(135deg, #dc3545 0%, #fd7e14 100%);
}

.btn-secondary.btn-modern {
    background: linear-gradient(135deg, #6c757d 0%, #495057 100%);
}

.type-selection {
    display: flex;
    gap: 1rem;
    margin-bottom: 1.5rem;
}

.type-option {
    flex: 1;
    border: 2px solid #e9ecef;
    border-radius: 10px;
    padding: 1.5rem;
    text-align: center;
    cursor: pointer;
    transition: all 0.3s ease;
}

.type-option:hover {
    border-color: #dc3545;
    background-color: #fff5f5;
}

.type-option.selected {
    border-color: #dc3545;
    background-color: #ffe6e6;
}

.type-option i {
    font-size: 2rem;
    margin-bottom: 0.5rem;
    display: block;
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
    .exit-header {
        margin: -20px -15px 20px -15px;
        padding: 1.5rem 0;
    }
    
    .exit-header h1 {
        font-size: 2rem;
    }
    
    .form-card {
        padding: 1rem;
    }
    
    .type-selection {
        flex-direction: column;
    }
}
</style>

<!-- En-tête moderne -->
<div class="exit-header">
    <div class="container-fluid">
        <div class="row align-items-center">
            <div class="col-md-8">
                <h1 class="animate-fade-in">
                    <i class="fas fa-user-minus me-3"></i>
                    Nouvelle sortie d'élève
                </h1>
                <p class="subtitle animate-fade-in animate-delay-1">
                    Enregistrer le départ d'un élève (transfert sortant ou sortie définitive)
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
    <form method="POST" id="exitForm">
        <input type="hidden" name="create_exit" value="1">
        
        <!-- Sélection de l'élève -->
        <div class="form-section">
            <h6><i class="fas fa-search"></i>Sélection de l'élève</h6>
            <div class="row">
                <div class="col-md-12 mb-3">
                    <label for="eleve_id" class="form-label">Élève <span class="text-danger">*</span></label>
                    <select class="form-select form-select-lg" id="eleve_id" name="eleve_id" required onchange="showStudentInfo()">
                        <option value="">Sélectionner un élève</option>
                        <?php foreach ($eleves as $eleve): ?>
                            <option value="<?php echo $eleve['id']; ?>" 
                                    data-nom="<?php echo htmlspecialchars($eleve['nom']); ?>"
                                    data-prenom="<?php echo htmlspecialchars($eleve['prenom']); ?>"
                                    data-matricule="<?php echo htmlspecialchars($eleve['numero_matricule']); ?>"
                                    data-classe="<?php echo htmlspecialchars($eleve['niveau'] . ' - ' . $eleve['classe_nom']); ?>">
                                <?php echo htmlspecialchars($eleve['numero_matricule'] . ' - ' . $eleve['nom'] . ' ' . $eleve['prenom'] . ' (' . $eleve['niveau'] . ' - ' . $eleve['classe_nom'] . ')'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </div>

        <!-- Informations de l'élève sélectionné -->
        <div id="studentInfo" class="student-info" style="display: none;">
            <h6><i class="fas fa-user text-primary"></i>Informations de l'élève sélectionné</h6>
            <div class="row">
                <div class="col-md-3">
                    <strong>Matricule:</strong>
                    <div id="info-matricule" class="text-muted">-</div>
                </div>
                <div class="col-md-3">
                    <strong>Nom complet:</strong>
                    <div id="info-nom" class="text-muted">-</div>
                </div>
                <div class="col-md-3">
                    <strong>Classe actuelle:</strong>
                    <div id="info-classe" class="text-muted">-</div>
                </div>
                <div class="col-md-3">
                    <strong>Statut:</strong>
                    <div class="text-success">Actif</div>
                </div>
            </div>
        </div>

        <!-- Type de mouvement -->
        <div class="form-section">
            <h6><i class="fas fa-exchange-alt"></i>Type de mouvement</h6>
            <div class="type-selection">
                <div class="type-option" onclick="selectType('transfert_sortant')">
                    <input type="radio" name="type_mouvement" value="transfert_sortant" id="type_transfert" style="display: none;">
                    <i class="fas fa-exchange-alt text-warning"></i>
                    <div class="fw-bold">Transfert sortant</div>
                    <small class="text-muted">Vers une autre école</small>
                </div>
                
                <div class="type-option" onclick="selectType('sortie_definitive')">
                    <input type="radio" name="type_mouvement" value="sortie_definitive" id="type_sortie" style="display: none;">
                    <i class="fas fa-graduation-cap text-success"></i>
                    <div class="fw-bold">Sortie définitive</div>
                    <small class="text-muted">Fin de scolarité</small>
                </div>
            </div>
        </div>

        <!-- Informations du mouvement -->
        <div class="form-section">
            <h6><i class="fas fa-info-circle"></i>Informations du mouvement</h6>
            
            <div class="row" id="ecole-destination-row" style="display: none;">
                <div class="col-md-6 mb-3">
                    <label for="ecole_destination" class="form-label">École de destination</label>
                    <input type="text" class="form-control form-control-lg" id="ecole_destination" name="ecole_destination">
                </div>
                <div class="col-md-6 mb-3">
                    <label for="frais_transfert" class="form-label">Frais de transfert (FC)</label>
                    <input type="number" class="form-control form-control-lg" id="frais_transfert" name="frais_transfert" 
                           min="0" step="0.01" placeholder="0.00">
                </div>
            </div>
            
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="date_demande" class="form-label">Date de demande <span class="text-danger">*</span></label>
                    <input type="date" class="form-control form-control-lg" id="date_demande" name="date_demande" 
                           value="<?php echo date('Y-m-d'); ?>" required>
                </div>
                <div class="col-md-6 mb-3">
                    <label for="date_effective" class="form-label">Date effective</label>
                    <input type="date" class="form-control form-control-lg" id="date_effective" name="date_effective">
                </div>
            </div>
            
            <div class="row">
                <div class="col-md-12 mb-3">
                    <label for="motif" class="form-label">Motif <span class="text-danger">*</span></label>
                    <textarea class="form-control" id="motif" name="motif" rows="3" required 
                              placeholder="Expliquer la raison de la sortie..."></textarea>
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
            <button type="submit" class="btn btn-danger btn-modern">
                <i class="fas fa-save me-2"></i>
                Créer la demande de sortie
            </button>
        </div>
    </form>
</div>

<script>
// Afficher les informations de l'élève sélectionné
function showStudentInfo() {
    const select = document.getElementById('eleve_id');
    const selectedOption = select.options[select.selectedIndex];
    const infoDiv = document.getElementById('studentInfo');
    
    if (selectedOption.value) {
        document.getElementById('info-matricule').textContent = selectedOption.dataset.matricule;
        document.getElementById('info-nom').textContent = selectedOption.dataset.nom + ' ' + selectedOption.dataset.prenom;
        document.getElementById('info-classe').textContent = selectedOption.dataset.classe;
        infoDiv.style.display = 'block';
    } else {
        infoDiv.style.display = 'none';
    }
}

// Sélectionner le type de mouvement
function selectType(type) {
    // Réinitialiser toutes les options
    document.querySelectorAll('.type-option').forEach(option => {
        option.classList.remove('selected');
    });
    
    // Sélectionner l'option choisie
    document.querySelector(`input[value="${type}"]`).checked = true;
    document.querySelector(`input[value="${type}"]`).closest('.type-option').classList.add('selected');
    
    // Afficher/masquer les champs selon le type
    const ecoleDestinationRow = document.getElementById('ecole-destination-row');
    const ecoleDestinationInput = document.getElementById('ecole_destination');
    
    if (type === 'transfert_sortant') {
        ecoleDestinationRow.style.display = 'block';
        ecoleDestinationInput.required = true;
    } else {
        ecoleDestinationRow.style.display = 'none';
        ecoleDestinationInput.required = false;
        ecoleDestinationInput.value = '';
    }
}

// Validation du formulaire
document.getElementById('exitForm').addEventListener('submit', function(e) {
    const eleveId = document.getElementById('eleve_id').value;
    const typeMouvement = document.querySelector('input[name="type_mouvement"]:checked');
    const motif = document.getElementById('motif').value;
    const datedemande = document.getElementById('date_demande').value;
    
    if (!eleveId) {
        alert('Veuillez sélectionner un élève');
        e.preventDefault();
        return false;
    }
    
    if (!typeMouvement) {
        alert('Veuillez sélectionner le type de mouvement');
        e.preventDefault();
        return false;
    }
    
    if (!motif.trim()) {
        alert('Veuillez saisir le motif');
        e.preventDefault();
        return false;
    }
    
    if (!datedemande) {
        alert('Veuillez saisir la date de demande');
        e.preventDefault();
        return false;
    }
    
    // Vérification spécifique pour transfert sortant
    if (typeMouvement.value === 'transfert_sortant') {
        const ecoleDestination = document.getElementById('ecole_destination').value;
        if (!ecoleDestination.trim()) {
            alert('Veuillez saisir l\'école de destination pour un transfert sortant');
            e.preventDefault();
            return false;
        }
    }
    
    // Confirmation
    const typeLabel = typeMouvement.value === 'transfert_sortant' ? 'transfert sortant' : 'sortie définitive';
    if (!confirm(`Êtes-vous sûr de vouloir créer cette demande de ${typeLabel} ?`)) {
        e.preventDefault();
        return false;
    }
});
</script>

<?php include '../../../includes/footer.php'; ?>
