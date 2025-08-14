<?php
/**
 * Ajouter un retard
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

$page_title = "Ajouter un retard";

// Récupérer l'année scolaire active
$current_year = $database->query("SELECT * FROM annees_scolaires WHERE status = 'active' LIMIT 1")->fetch();

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $eleve_id = (int)($_POST['eleve_id'] ?? 0);
        $date_retard = sanitizeInput($_POST['date_retard'] ?? '');
        $heure_retard = sanitizeInput($_POST['heure_retard'] ?? '');
        $duree_retard = (int)($_POST['duree_retard'] ?? 0);
        $motif = sanitizeInput($_POST['motif'] ?? '');
        $justifie = isset($_POST['justifie']) ? 1 : 0;
        
        // Validation
        if (!$eleve_id) {
            throw new Exception('Élève requis');
        }
        
        if (!$date_retard || !$heure_retard) {
            throw new Exception('Date et heure requises');
        }
        
        if ($duree_retard <= 0) {
            throw new Exception('Durée du retard requise');
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
            throw new Exception('Élève non trouvé ou non inscrit pour cette année scolaire');
        }
        
        // Combiner date et heure
        $datetime_retard = $date_retard . ' ' . $heure_retard;
        
        // Déterminer le type de retard
        $type_retard = $justifie ? 'retard_justifie' : 'retard';
        
        // Commencer une transaction
        $database->beginTransaction();
        
        try {
            // Insérer le retard
            $database->execute(
                "INSERT INTO absences (eleve_id, classe_id, type_absence, date_absence, motif, duree_retard, created_at) 
                 VALUES (?, ?, ?, ?, ?, ?, NOW())",
                [$eleve_id, $eleve['classe_id'], $type_retard, $datetime_retard, $motif, $duree_retard]
            );
            
            $retard_id = $database->lastInsertId();
            
            // Enregistrer l'action dans l'historique
            logUserAction(
                'create_delay',
                'attendance',
                'Retard ajouté - Élève: ' . $eleve['nom'] . ' ' . $eleve['prenom'] . 
                ', Date: ' . formatDateTime($datetime_retard) . 
                ', Durée: ' . $duree_retard . ' min' .
                ($motif ? ', Motif: ' . $motif : ''),
                $retard_id
            );
            
            $database->commit();
            showMessage('success', 'Retard ajouté avec succès');
            
            // Rediriger vers la liste ou rester sur la page selon le choix
            if (isset($_POST['action_after']) && $_POST['action_after'] === 'stay') {
                // Rester sur la page pour ajouter un autre retard
                $_POST = []; // Vider le formulaire
            } else {
                redirectTo('index.php');
            }
            
        } catch (Exception $e) {
            $database->rollback();
            throw $e;
        }
        
    } catch (Exception $e) {
        showMessage('error', $e->getMessage());
    }
}

// Récupérer les classes pour le filtre
$classes = $database->query(
    "SELECT * FROM classes WHERE annee_scolaire_id = ? ORDER BY niveau, nom",
    [$current_year['id'] ?? 0]
)->fetchAll();

include '../../../includes/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">
        <i class="fas fa-clock me-2"></i>
        Ajouter un retard
    </h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <div class="btn-group me-2">
            <a href="index.php" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-1"></i>
                Retour à la liste
            </a>
        </div>
        <div class="btn-group">
            <a href="add-absence.php" class="btn btn-outline-primary">
                <i class="fas fa-user-times me-1"></i>
                Ajouter une absence
            </a>
        </div>
    </div>
</div>

<div class="row">
    <!-- Formulaire d'ajout de retard -->
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-plus-circle me-2"></i>
                    Informations du retard
                </h5>
            </div>
            <div class="card-body">
                <form method="POST" id="addDelayForm">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="classe_filter" class="form-label">Filtrer par classe</label>
                            <select class="form-select" id="classe_filter" onchange="loadStudents()">
                                <option value="">Sélectionner une classe</option>
                                <?php foreach ($classes as $class): ?>
                                    <option value="<?php echo $class['id']; ?>">
                                        <?php echo htmlspecialchars($class['niveau'] . ' - ' . $class['nom']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="eleve_id" class="form-label">Élève <span class="text-danger">*</span></label>
                            <select class="form-select" id="eleve_id" name="eleve_id" required>
                                <option value="">Sélectionner un élève</option>
                            </select>
                            <div class="form-text">Sélectionnez d'abord une classe pour voir les élèves</div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label for="date_retard" class="form-label">Date du retard <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" id="date_retard" name="date_retard" 
                                   value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                        
                        <div class="col-md-4 mb-3">
                            <label for="heure_retard" class="form-label">Heure d'arrivée <span class="text-danger">*</span></label>
                            <input type="time" class="form-control" id="heure_retard" name="heure_retard" 
                                   value="<?php echo date('H:i'); ?>" required>
                        </div>
                        
                        <div class="col-md-4 mb-3">
                            <label for="duree_retard" class="form-label">Durée (minutes) <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" id="duree_retard" name="duree_retard" 
                                   min="1" max="480" placeholder="Ex: 15" required>
                            <div class="form-text">Durée du retard en minutes</div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="motif" class="form-label">Motif du retard</label>
                        <textarea class="form-control" id="motif" name="motif" rows="3" 
                                  placeholder="Raison du retard (optionnel)"></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="justifie" name="justifie">
                            <label class="form-check-label" for="justifie">
                                Retard justifié
                            </label>
                            <div class="form-text">Cochez si le retard est justifié (certificat médical, etc.)</div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Action après ajout</label>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="action_after" id="action_list" value="list" checked>
                            <label class="form-check-label" for="action_list">
                                Retourner à la liste des absences
                            </label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="action_after" id="action_stay" value="stay">
                            <label class="form-check-label" for="action_stay">
                                Rester sur cette page pour ajouter un autre retard
                            </label>
                        </div>
                    </div>
                    
                    <div class="d-flex justify-content-end">
                        <button type="button" class="btn btn-secondary me-2" onclick="resetForm()">
                            <i class="fas fa-undo me-1"></i>
                            Réinitialiser
                        </button>
                        <button type="submit" class="btn btn-warning">
                            <i class="fas fa-clock me-1"></i>
                            Ajouter le retard
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Informations et aide -->
    <div class="col-lg-4">
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-info-circle me-2"></i>
                    Informations
                </h5>
            </div>
            <div class="card-body">
                <div class="alert alert-info">
                    <i class="fas fa-lightbulb me-2"></i>
                    <strong>Conseil :</strong> Un retard est considéré comme significatif à partir de 5 minutes.
                </div>
                
                <h6>Types de retards :</h6>
                <ul class="list-unstyled">
                    <li><span class="badge bg-warning me-2">Retard</span> Non justifié</li>
                    <li><span class="badge bg-info me-2">Retard justifié</span> Avec justification</li>
                </ul>
                
                <h6>Durées courantes :</h6>
                <div class="d-grid gap-2">
                    <button type="button" class="btn btn-outline-secondary btn-sm" onclick="setDuration(5)">
                        5 minutes
                    </button>
                    <button type="button" class="btn btn-outline-secondary btn-sm" onclick="setDuration(10)">
                        10 minutes
                    </button>
                    <button type="button" class="btn btn-outline-secondary btn-sm" onclick="setDuration(15)">
                        15 minutes
                    </button>
                    <button type="button" class="btn btn-outline-secondary btn-sm" onclick="setDuration(30)">
                        30 minutes
                    </button>
                </div>
            </div>
        </div>
        
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-chart-bar me-2"></i>
                    Statistiques du jour
                </h5>
            </div>
            <div class="card-body">
                <?php
                // Statistiques du jour
                $today_stats = $database->query(
                    "SELECT 
                        COUNT(*) as total_today,
                        COUNT(CASE WHEN type_absence IN ('retard', 'retard_justifie') THEN 1 END) as retards_today,
                        AVG(CASE WHEN duree_retard > 0 THEN duree_retard END) as duree_moyenne
                     FROM absences 
                     WHERE DATE(date_absence) = CURDATE()"
                )->fetch();
                ?>
                
                <div class="row text-center">
                    <div class="col-6">
                        <h4 class="text-warning"><?php echo $today_stats['retards_today'] ?? 0; ?></h4>
                        <small class="text-muted">Retards aujourd'hui</small>
                    </div>
                    <div class="col-6">
                        <h4 class="text-info"><?php echo round($today_stats['duree_moyenne'] ?? 0); ?> min</h4>
                        <small class="text-muted">Durée moyenne</small>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Charger les élèves selon la classe sélectionnée
function loadStudents() {
    const classeId = document.getElementById('classe_filter').value;
    const eleveSelect = document.getElementById('eleve_id');
    
    // Réinitialiser la liste des élèves
    eleveSelect.innerHTML = '<option value="">Chargement...</option>';
    
    if (!classeId) {
        eleveSelect.innerHTML = '<option value="">Sélectionner un élève</option>';
        return;
    }
    
    // Charger les élèves via AJAX
    fetch('get-students.php?classe_id=' + classeId)
        .then(response => response.json())
        .then(data => {
            eleveSelect.innerHTML = '<option value="">Sélectionner un élève</option>';
            
            if (data.success && data.students) {
                data.students.forEach(student => {
                    const option = document.createElement('option');
                    option.value = student.id;
                    option.textContent = student.nom + ' ' + student.prenom + ' (' + student.numero_matricule + ')';
                    eleveSelect.appendChild(option);
                });
            } else {
                eleveSelect.innerHTML = '<option value="">Aucun élève trouvé</option>';
            }
        })
        .catch(error => {
            console.error('Erreur:', error);
            eleveSelect.innerHTML = '<option value="">Erreur de chargement</option>';
        });
}

// Définir une durée prédéfinie
function setDuration(minutes) {
    document.getElementById('duree_retard').value = minutes;
}

// Réinitialiser le formulaire
function resetForm() {
    document.getElementById('addDelayForm').reset();
    document.getElementById('date_retard').value = '<?php echo date('Y-m-d'); ?>';
    document.getElementById('heure_retard').value = '<?php echo date('H:i'); ?>';
    document.getElementById('eleve_id').innerHTML = '<option value="">Sélectionner un élève</option>';
}

// Validation du formulaire
document.getElementById('addDelayForm').addEventListener('submit', function(e) {
    const eleveId = document.getElementById('eleve_id').value;
    const dureeRetard = parseInt(document.getElementById('duree_retard').value);
    
    if (!eleveId) {
        e.preventDefault();
        alert('Veuillez sélectionner un élève');
        return false;
    }
    
    if (dureeRetard <= 0 || dureeRetard > 480) {
        e.preventDefault();
        alert('La durée du retard doit être entre 1 et 480 minutes');
        return false;
    }
    
    // Confirmation pour les retards très longs
    if (dureeRetard > 120) {
        if (!confirm('Le retard est de plus de 2 heures. Êtes-vous sûr ?')) {
            e.preventDefault();
            return false;
        }
    }
});

// Calculer automatiquement la durée si l'heure de début de cours est connue
document.getElementById('heure_retard').addEventListener('change', function() {
    const heureRetard = this.value;
    const heureDebut = '08:00'; // Heure de début des cours (à adapter)
    
    if (heureRetard && heureRetard > heureDebut) {
        const [h1, m1] = heureDebut.split(':').map(Number);
        const [h2, m2] = heureRetard.split(':').map(Number);
        
        const debut = h1 * 60 + m1;
        const arrivee = h2 * 60 + m2;
        const duree = arrivee - debut;
        
        if (duree > 0 && duree <= 480) {
            document.getElementById('duree_retard').value = duree;
        }
    }
});
</script>

<?php include '../../../includes/footer.php'; ?>
