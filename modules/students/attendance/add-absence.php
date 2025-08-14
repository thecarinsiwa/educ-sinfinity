<?php
/**
 * Ajouter une absence ou un retard
 * Application de gestion scolaire - République Démocratique du Congo
 */

require_once '../../../config/config.php';
require_once '../../../config/database.php';
require_once '../../../includes/functions.php';

// Vérifier l'authentification et les permissions
requireLogin();
if (!checkPermission('students')) {
    showMessage('error', 'Accès refusé à ce module.');
    redirectTo('../index.php');
}

$page_title = 'Signaler une Absence ou un Retard';

// Obtenir l'année scolaire actuelle
$current_year = getCurrentAcademicYear();

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $eleve_id = (int)($_POST['eleve_id'] ?? 0);
        $type_absence = sanitizeInput($_POST['type_absence'] ?? '');
        $date_absence = sanitizeInput($_POST['date_absence'] ?? '');
        $heure_absence = sanitizeInput($_POST['heure_absence'] ?? '');
        $motif = sanitizeInput($_POST['motif'] ?? '');
        $justifiee = isset($_POST['justifiee']) ? 1 : 0;

        // Adapter le type d'absence selon la justification
        if ($justifiee && $type_absence === 'absence') {
            $type_absence = 'absence_justifiee';
        } elseif ($justifiee && $type_absence === 'retard') {
            $type_absence = 'retard_justifie';
        }
        
        // Validation
        if (!$eleve_id) {
            throw new Exception('Veuillez sélectionner un élève');
        }
        
        if (!in_array($type_absence, ['absence', 'retard', 'absence_justifiee', 'retard_justifie'])) {
            throw new Exception('Type d\'absence invalide');
        }
        
        if (!$date_absence) {
            throw new Exception('Date requise');
        }
        
        if (!$heure_absence) {
            throw new Exception('Heure requise');
        }
        
        // Vérifier que l'élève existe et est inscrit
        $eleve = $database->query(
            "SELECT e.*, c.nom as classe_nom, c.niveau, i.classe_id
             FROM eleves e
             JOIN inscriptions i ON e.id = i.eleve_id
             JOIN classes c ON i.classe_id = c.id
             WHERE e.id = ? AND i.status = 'inscrit' AND i.annee_scolaire_id = ?",
            [$eleve_id, $current_year['id'] ?? 0]
        )->fetch();
        
        if (!$eleve) {
            throw new Exception('Élève non trouvé ou non inscrit');
        }
        
        // Combiner date et heure
        $datetime_absence = $date_absence . ' ' . $heure_absence;
        
        // Vérifier qu'il n'y a pas déjà une absence/retard pour cet élève à cette date/heure
        $existing = $database->query(
            "SELECT id FROM absences 
             WHERE eleve_id = ? AND date_absence = ?",
            [$eleve_id, $datetime_absence]
        )->fetch();
        
        if ($existing) {
            throw new Exception('Une absence/retard est déjà enregistrée pour cet élève à cette date et heure');
        }
        
        // Commencer une transaction
        $database->beginTransaction();
        
        try {
            // Insérer l'absence
            $database->query(
                "INSERT INTO absences (eleve_id, classe_id, type_absence, date_absence, motif, created_at)
                 VALUES (?, ?, ?, ?, ?, NOW())",
                [$eleve_id, $eleve['classe_id'], $type_absence, $datetime_absence, $motif]
            );
            
            $absence_id = $database->lastInsertId();
            
            // Enregistrer l'action dans l'historique
            logUserAction(
                'create_absence',
                'attendance',
                ucfirst($type_absence) . ' créée pour ' . $eleve['nom'] . ' ' . $eleve['prenom'] . 
                ' (' . $eleve['classe_nom'] . ') - Date: ' . formatDateTime($datetime_absence) .
                ($motif ? ' - Motif: ' . $motif : ''),
                $absence_id
            );
            
            // Valider la transaction
            $database->commit();
            
            showMessage('success', ucfirst($type_absence) . ' enregistrée avec succès pour ' . $eleve['nom'] . ' ' . $eleve['prenom']);
            redirectTo('index.php');
            
        } catch (Exception $e) {
            $database->rollback();
            throw $e;
        }
        
    } catch (Exception $e) {
        showMessage('error', $e->getMessage());
    }
}

// Récupérer les élèves pour le formulaire
$eleves = $database->query(
    "SELECT e.id, e.nom, e.prenom, e.numero_matricule, c.nom as classe_nom, c.niveau
     FROM eleves e
     JOIN inscriptions i ON e.id = i.eleve_id
     JOIN classes c ON i.classe_id = c.id
     WHERE i.status = 'inscrit' AND i.annee_scolaire_id = ?
     ORDER BY c.niveau, c.nom, e.nom, e.prenom",
    [$current_year['id'] ?? 0]
)->fetchAll();

// Récupérer les classes pour le filtre
$classes = $database->query(
    "SELECT id, nom, niveau FROM classes WHERE annee_scolaire_id = ? ORDER BY niveau, nom",
    [$current_year['id'] ?? 0]
)->fetchAll();

include '../../../includes/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">
        <i class="fas fa-user-times me-2"></i>
        Signaler une Absence ou un Retard
    </h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <div class="btn-group me-2">
            <a href="index.php" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-1"></i>
                Retour
            </a>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-plus me-2"></i>
                    Nouvelle absence ou retard
                </h5>
            </div>
            <div class="card-body">
                <form method="POST" id="absenceForm">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="classe_filter" class="form-label">Filtrer par classe</label>
                            <select class="form-select" id="classe_filter" onchange="filterStudents()">
                                <option value="">Toutes les classes</option>
                                <?php foreach ($classes as $classe): ?>
                                    <option value="<?php echo $classe['id']; ?>">
                                        <?php echo htmlspecialchars($classe['nom'] . ' (' . ucfirst($classe['niveau']) . ')'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="search_student" class="form-label">Rechercher un élève</label>
                            <input type="text" class="form-control" id="search_student" 
                                   placeholder="Nom, prénom ou matricule..." onkeyup="filterStudents()">
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="eleve_id" class="form-label">Élève <span class="text-danger">*</span></label>
                        <select class="form-select" id="eleve_id" name="eleve_id" required>
                            <option value="">Sélectionner un élève</option>
                            <?php foreach ($eleves as $eleve): ?>
                                <option value="<?php echo $eleve['id']; ?>" 
                                        data-classe="<?php echo $eleve['classe_nom']; ?>"
                                        data-niveau="<?php echo $eleve['niveau']; ?>"
                                        data-search="<?php echo strtolower($eleve['nom'] . ' ' . $eleve['prenom'] . ' ' . $eleve['numero_matricule']); ?>">
                                    <?php echo htmlspecialchars($eleve['nom'] . ' ' . $eleve['prenom']); ?> 
                                    (<?php echo htmlspecialchars($eleve['numero_matricule']); ?>) - 
                                    <?php echo htmlspecialchars($eleve['classe_nom']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label for="type_absence" class="form-label">Type <span class="text-danger">*</span></label>
                            <select class="form-select" id="type_absence" name="type_absence" required>
                                <option value="">Sélectionner</option>
                                <option value="absence">Absence</option>
                                <option value="retard">Retard</option>
                            </select>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="date_absence" class="form-label">Date <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" id="date_absence" name="date_absence" 
                                   value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="heure_absence" class="form-label">Heure <span class="text-danger">*</span></label>
                            <input type="time" class="form-control" id="heure_absence" name="heure_absence" 
                                   value="<?php echo date('H:i'); ?>" required>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="motif" class="form-label">Motif</label>
                        <textarea class="form-control" id="motif" name="motif" rows="3" 
                                  placeholder="Raison de l'absence ou du retard..."></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="justifiee" name="justifiee">
                            <label class="form-check-label" for="justifiee">
                                Absence/retard justifiée
                            </label>
                        </div>
                    </div>
                    
                    <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                        <a href="index.php" class="btn btn-secondary me-md-2">
                            <i class="fas fa-times me-1"></i>
                            Annuler
                        </a>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-1"></i>
                            Enregistrer
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header">
                <h6 class="mb-0">
                    <i class="fas fa-info-circle me-2"></i>
                    Informations
                </h6>
            </div>
            <div class="card-body">
                <div class="alert alert-info">
                    <h6><i class="fas fa-lightbulb me-2"></i>Conseils</h6>
                    <ul class="mb-0">
                        <li>Utilisez les filtres pour trouver rapidement un élève</li>
                        <li>Vérifiez la date et l'heure avant d'enregistrer</li>
                        <li>Précisez le motif pour faciliter le suivi</li>
                        <li>Cochez "justifiée" si vous avez reçu un justificatif</li>
                    </ul>
                </div>
                
                <div class="alert alert-warning">
                    <h6><i class="fas fa-exclamation-triangle me-2"></i>Attention</h6>
                    <p class="mb-0">
                        Cette action sera enregistrée dans l'historique avec votre nom d'utilisateur 
                        (<strong><?php echo htmlspecialchars($_SESSION['username']); ?></strong>).
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function filterStudents() {
    const classeFilter = document.getElementById('classe_filter').value;
    const searchText = document.getElementById('search_student').value.toLowerCase();
    const eleveSelect = document.getElementById('eleve_id');
    const options = eleveSelect.querySelectorAll('option');
    
    options.forEach(option => {
        if (option.value === '') {
            option.style.display = 'block';
            return;
        }
        
        const classeMatch = !classeFilter || option.dataset.classe === classeFilter;
        const searchMatch = !searchText || option.dataset.search.includes(searchText);
        
        option.style.display = (classeMatch && searchMatch) ? 'block' : 'none';
    });
    
    // Réinitialiser la sélection si l'option sélectionnée n'est plus visible
    const selectedOption = eleveSelect.querySelector('option:checked');
    if (selectedOption && selectedOption.style.display === 'none') {
        eleveSelect.value = '';
    }
}

// Validation du formulaire
document.getElementById('absenceForm').addEventListener('submit', function(e) {
    const eleveId = document.getElementById('eleve_id').value;
    const typeAbsence = document.getElementById('type_absence').value;
    const dateAbsence = document.getElementById('date_absence').value;
    const heureAbsence = document.getElementById('heure_absence').value;
    
    if (!eleveId || !typeAbsence || !dateAbsence || !heureAbsence) {
        e.preventDefault();
        alert('Veuillez remplir tous les champs obligatoires');
        return;
    }
    
    // Vérifier que la date n'est pas dans le futur
    const selectedDate = new Date(dateAbsence + ' ' + heureAbsence);
    const now = new Date();
    
    if (selectedDate > now) {
        e.preventDefault();
        alert('La date et l\'heure ne peuvent pas être dans le futur');
        return;
    }
});
</script>

<?php include '../../../includes/footer.php'; ?>
