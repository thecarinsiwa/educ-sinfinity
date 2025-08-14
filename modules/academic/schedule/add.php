<?php
/**
 * Module Académique - Ajouter un cours à l'emploi du temps
 * Application de gestion scolaire - République Démocratique du Congo
 */

require_once '../../../config/config.php';
require_once '../../../config/database.php';
require_once '../../../includes/functions.php';

// Vérifier l'authentification et les permissions
requireLogin();
if (!checkPermission('admin') && !checkPermission('academic')) {
    showMessage('error', 'Accès refusé à cette fonctionnalité.');
    redirectTo('../../../index.php');
}

$page_title = 'Ajouter un cours';

// Récupérer l'année scolaire active
$current_year = $database->query("SELECT * FROM annees_scolaires WHERE status = 'active' LIMIT 1")->fetch();
if (!$current_year) {
    showMessage('error', 'Aucune année scolaire active trouvée.');
    redirectTo('../../../index.php');
}

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $classe_id = (int)$_POST['classe_id'];
        $matiere_id = (int)$_POST['matiere_id'];
        $enseignant_id = (int)$_POST['enseignant_id'];
        $jour_semaine = sanitizeInput($_POST['jour_semaine']);
        $heure_debut = sanitizeInput($_POST['heure_debut']);
        $heure_fin = sanitizeInput($_POST['heure_fin']);
        $salle = sanitizeInput($_POST['salle']);
        $recurrence = $_POST['recurrence'] ?? 'unique';
        $date_debut = $_POST['date_debut'] ?? null;
        $date_fin = $_POST['date_fin'] ?? null;
        
        // Validations
        if (!$classe_id || !$matiere_id || !$enseignant_id || !$jour_semaine || !$heure_debut || !$heure_fin) {
            throw new Exception('Tous les champs obligatoires doivent être remplis.');
        }
        
        if ($heure_debut >= $heure_fin) {
            throw new Exception('L\'heure de fin doit être postérieure à l\'heure de début.');
        }
        
        // Vérifier les conflits
        $conflits = $database->query(
            "SELECT COUNT(*) as total FROM emploi_temps 
             WHERE ((classe_id = ? OR enseignant_id = ?) OR salle = ?)
             AND jour_semaine = ? 
             AND ((heure_debut < ? AND heure_fin > ?) OR (heure_debut < ? AND heure_fin > ?))
             AND annee_scolaire_id = ?",
            [$classe_id, $enseignant_id, $salle, $jour_semaine, $heure_fin, $heure_debut, $heure_debut, $heure_fin, $current_year['id']]
        )->fetch();
        
        if ($conflits['total'] > 0) {
            throw new Exception('Conflit détecté : la classe, l\'enseignant ou la salle est déjà occupée à cette heure.');
        }
        
        // Insérer le cours
        $database->execute(
            "INSERT INTO emploi_temps (classe_id, matiere_id, enseignant_id, jour_semaine, heure_debut, heure_fin, salle, recurrence, date_debut, date_fin, annee_scolaire_id, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())",
            [$classe_id, $matiere_id, $enseignant_id, $jour_semaine, $heure_debut, $heure_fin, $salle, $recurrence, $date_debut, $date_fin, $current_year['id']]
        );
        
        $cours_id = $database->lastInsertId();
        
        // Récupérer les informations pour le log
        $classe = $database->query("SELECT nom FROM classes WHERE id = ?", [$classe_id])->fetch();
        $matiere = $database->query("SELECT nom FROM matieres WHERE id = ?", [$matiere_id])->fetch();
        
        // Enregistrer l'action
        logUserAction(
            'add_schedule_course',
            'academic',
            'Cours ajouté - Classe: ' . $classe['nom'] . ', Matière: ' . $matiere['nom'] . ', Jour: ' . $jour_semaine . ' ' . $heure_debut . '-' . $heure_fin,
            $cours_id
        );
        
        showMessage('success', 'Cours ajouté avec succès à l\'emploi du temps.');
        
        // Rediriger vers la liste ou rester sur la page selon le choix
        if (isset($_POST['save_and_continue'])) {
            // Rester sur la page pour ajouter un autre cours
            $_POST = []; // Vider le formulaire
        } else {
            redirectTo('generate.php');
        }
        
    } catch (Exception $e) {
        showMessage('error', 'Erreur lors de l\'ajout: ' . $e->getMessage());
    }
}

// Récupérer les données pour les formulaires
$classes = $database->query(
    "SELECT * FROM classes WHERE annee_scolaire_id = ? ORDER BY niveau, nom",
    [$current_year['id']]
)->fetchAll();

$matieres = $database->query(
    "SELECT * FROM matieres ORDER BY nom"
)->fetchAll();

$enseignants = $database->query(
    "SELECT * FROM personnel WHERE fonction = 'enseignant' AND status = 'actif' ORDER BY nom, prenom"
)->fetchAll();

// Récupérer les salles disponibles
$salles_emploi = $database->query(
    "SELECT DISTINCT salle FROM emploi_temps WHERE salle IS NOT NULL AND salle != ''"
)->fetchAll();

$salles_classes = $database->query(
    "SELECT DISTINCT salle FROM classes WHERE salle IS NOT NULL AND salle != ''"
)->fetchAll();

// Fusionner et dédupliquer les salles
$salles_array = [];
foreach ($salles_emploi as $salle) {
    if (!in_array($salle['salle'], $salles_array)) {
        $salles_array[] = $salle['salle'];
    }
}
foreach ($salles_classes as $salle) {
    if (!in_array($salle['salle'], $salles_array)) {
        $salles_array[] = $salle['salle'];
    }
}
sort($salles_array);

// Convertir en format attendu
$salles = [];
foreach ($salles_array as $salle_nom) {
    $salles[] = ['salle' => $salle_nom];
}

include '../../../includes/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">
        <i class="fas fa-plus me-2"></i>
        Ajouter un cours
    </h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <div class="btn-group me-2">
            <a href="generate.php" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-1"></i>
                Retour
            </a>
        </div>
        <div class="btn-group">
            <a href="conflicts.php" class="btn btn-outline-warning">
                <i class="fas fa-exclamation-triangle me-1"></i>
                Vérifier les conflits
            </a>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-calendar-plus me-2"></i>
                    Informations du cours
                </h5>
            </div>
            <div class="card-body">
                <form method="POST" id="addCourseForm">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="classe_id" class="form-label">Classe <span class="text-danger">*</span></label>
                            <select class="form-select" id="classe_id" name="classe_id" required>
                                <option value="">Sélectionner une classe</option>
                                <?php foreach ($classes as $classe): ?>
                                    <option value="<?php echo $classe['id']; ?>" <?php echo (isset($_POST['classe_id']) && $_POST['classe_id'] == $classe['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($classe['niveau'] . ' - ' . $classe['nom']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="matiere_id" class="form-label">Matière <span class="text-danger">*</span></label>
                            <select class="form-select" id="matiere_id" name="matiere_id" required>
                                <option value="">Sélectionner une matière</option>
                                <?php foreach ($matieres as $matiere): ?>
                                    <option value="<?php echo $matiere['id']; ?>" <?php echo (isset($_POST['matiere_id']) && $_POST['matiere_id'] == $matiere['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($matiere['nom']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="enseignant_id" class="form-label">Enseignant <span class="text-danger">*</span></label>
                            <select class="form-select" id="enseignant_id" name="enseignant_id" required>
                                <option value="">Sélectionner un enseignant</option>
                                <?php foreach ($enseignants as $enseignant): ?>
                                    <option value="<?php echo $enseignant['id']; ?>" <?php echo (isset($_POST['enseignant_id']) && $_POST['enseignant_id'] == $enseignant['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($enseignant['nom'] . ' ' . $enseignant['prenom']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="salle" class="form-label">Salle</label>
                            <input type="text" class="form-control" id="salle" name="salle" 
                                   value="<?php echo htmlspecialchars($_POST['salle'] ?? ''); ?>"
                                   list="salles_list" placeholder="Ex: Salle 101">
                            <datalist id="salles_list">
                                <?php foreach ($salles as $salle): ?>
                                    <option value="<?php echo htmlspecialchars($salle['salle']); ?>">
                                <?php endforeach; ?>
                            </datalist>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label for="jour_semaine" class="form-label">Jour <span class="text-danger">*</span></label>
                            <select class="form-select" id="jour_semaine" name="jour_semaine" required>
                                <option value="">Sélectionner un jour</option>
                                <?php 
                                $jours = ['Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi'];
                                foreach ($jours as $jour): 
                                ?>
                                    <option value="<?php echo $jour; ?>" <?php echo (isset($_POST['jour_semaine']) && $_POST['jour_semaine'] == $jour) ? 'selected' : ''; ?>>
                                        <?php echo $jour; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="heure_debut" class="form-label">Heure de début <span class="text-danger">*</span></label>
                            <input type="time" class="form-control" id="heure_debut" name="heure_debut" 
                                   value="<?php echo htmlspecialchars($_POST['heure_debut'] ?? '08:00'); ?>" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="heure_fin" class="form-label">Heure de fin <span class="text-danger">*</span></label>
                            <input type="time" class="form-control" id="heure_fin" name="heure_fin" 
                                   value="<?php echo htmlspecialchars($_POST['heure_fin'] ?? '09:00'); ?>" required>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label for="recurrence" class="form-label">Récurrence</label>
                            <select class="form-select" id="recurrence" name="recurrence">
                                <option value="unique" <?php echo (isset($_POST['recurrence']) && $_POST['recurrence'] == 'unique') ? 'selected' : ''; ?>>Cours unique</option>
                                <option value="hebdomadaire" <?php echo (isset($_POST['recurrence']) && $_POST['recurrence'] == 'hebdomadaire') ? 'selected' : ''; ?>>Hebdomadaire</option>
                                <option value="bihebdomadaire" <?php echo (isset($_POST['recurrence']) && $_POST['recurrence'] == 'bihebdomadaire') ? 'selected' : ''; ?>>Bi-hebdomadaire</option>
                            </select>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="date_debut" class="form-label">Date de début</label>
                            <input type="date" class="form-control" id="date_debut" name="date_debut" 
                                   value="<?php echo htmlspecialchars($_POST['date_debut'] ?? ''); ?>">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="date_fin" class="form-label">Date de fin</label>
                            <input type="date" class="form-control" id="date_fin" name="date_fin" 
                                   value="<?php echo htmlspecialchars($_POST['date_fin'] ?? ''); ?>">
                        </div>
                    </div>
                    
                    <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                        <button type="submit" name="save_and_continue" class="btn btn-outline-primary">
                            <i class="fas fa-plus me-1"></i>
                            Enregistrer et continuer
                        </button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-1"></i>
                            Enregistrer et terminer
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-info-circle me-2"></i>
                    Aide
                </h5>
            </div>
            <div class="card-body">
                <div class="alert alert-info">
                    <h6><i class="fas fa-lightbulb me-2"></i>Conseils</h6>
                    <ul class="mb-0">
                        <li>Vérifiez les conflits avant d'ajouter</li>
                        <li>Utilisez des créneaux de 1h minimum</li>
                        <li>Évitez les chevauchements</li>
                        <li>Planifiez les pauses entre les cours</li>
                    </ul>
                </div>
                
                <div class="alert alert-warning">
                    <h6><i class="fas fa-clock me-2"></i>Horaires recommandés</h6>
                    <ul class="mb-0">
                        <li><strong>Matin :</strong> 08:00 - 12:00</li>
                        <li><strong>Pause :</strong> 12:00 - 13:00</li>
                        <li><strong>Après-midi :</strong> 13:00 - 16:00</li>
                    </ul>
                </div>
            </div>
        </div>
        
        <div class="card mt-3">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-bolt me-2"></i>
                    Actions rapides
                </h5>
            </div>
            <div class="card-body">
                <div class="d-grid gap-2">
                    <button type="button" class="btn btn-outline-info" onclick="checkConflicts()">
                        <i class="fas fa-search me-2"></i>
                        Vérifier les conflits
                    </button>
                    <a href="generate.php" class="btn btn-outline-secondary">
                        <i class="fas fa-magic me-2"></i>
                        Génération automatique
                    </a>
                    <a href="export.php?format=pdf" class="btn btn-outline-success">
                        <i class="fas fa-file-pdf me-2"></i>
                        Exporter en PDF
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Validation du formulaire
document.getElementById('addCourseForm').addEventListener('submit', function(e) {
    const heureDebut = document.getElementById('heure_debut').value;
    const heureFin = document.getElementById('heure_fin').value;
    
    if (heureDebut >= heureFin) {
        e.preventDefault();
        alert('L\'heure de fin doit être postérieure à l\'heure de début.');
        return;
    }
    
    // Calculer la durée
    const debut = new Date('2000-01-01 ' + heureDebut);
    const fin = new Date('2000-01-01 ' + heureFin);
    const duree = (fin - debut) / (1000 * 60); // en minutes
    
    if (duree < 30) {
        if (!confirm('La durée du cours est inférieure à 30 minutes. Continuer ?')) {
            e.preventDefault();
            return;
        }
    }
});

// Fonction pour vérifier les conflits
function checkConflicts() {
    const classeId = document.getElementById('classe_id').value;
    const enseignantId = document.getElementById('enseignant_id').value;
    const jour = document.getElementById('jour_semaine').value;
    const heureDebut = document.getElementById('heure_debut').value;
    const heureFin = document.getElementById('heure_fin').value;
    const salle = document.getElementById('salle').value;
    
    if (!classeId || !enseignantId || !jour || !heureDebut || !heureFin) {
        alert('Veuillez remplir tous les champs obligatoires avant de vérifier les conflits.');
        return;
    }
    
    // Rediriger vers la page de vérification des conflits avec les paramètres
    const params = new URLSearchParams({
        classe_id: classeId,
        enseignant_id: enseignantId,
        jour_semaine: jour,
        heure_debut: heureDebut,
        heure_fin: heureFin,
        salle: salle
    });
    
    window.open('conflicts.php?' + params.toString(), '_blank');
}

// Auto-complétion intelligente
document.getElementById('matiere_id').addEventListener('change', function() {
    const matiereId = this.value;
    if (matiereId) {
        // Ici on pourrait faire un appel AJAX pour récupérer l'enseignant principal de la matière
        // et le sélectionner automatiquement
    }
});
</script>

<?php include '../../../includes/footer.php'; ?>
