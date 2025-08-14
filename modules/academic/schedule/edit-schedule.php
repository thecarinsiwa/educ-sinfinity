<?php
/**
 * Modifier un horaire d'une classe
 * URL: edit-schedule.php?id=1
 */

require_once '../../../config/config.php';
require_once '../../../config/database.php';
require_once '../../../includes/functions.php';

requireLogin();
if (!checkPermission('academic')) {
    redirectTo('../../login.php');
}

// Récupérer l'ID de l'horaire
$schedule_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($schedule_id <= 0) {
    die('ID d\'horaire invalide.');
}

// Récupérer l'horaire
$schedule = $database->query(
    "SELECT * FROM emplois_temps WHERE id = ?",
    [$schedule_id]
)->fetch();
if (!$schedule) {
    die('Horaire non trouvé.');
}
$class_id = $schedule['classe_id'];

// Récupérer la classe
$classe = $database->query(
    "SELECT * FROM classes WHERE id = ?",
    [$class_id]
)->fetch();
if (!$classe) {
    die('Classe non trouvée.');
}

// Récupérer les matières et enseignants
$matieres = $database->query("SELECT * FROM matieres ORDER BY nom")->fetchAll();
$enseignants = $database->query("SELECT * FROM personnel WHERE fonction = 'enseignant' ORDER BY nom, prenom")->fetchAll();

// Gestion du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $jour = $_POST['jour_semaine'] ?? '';
    $heure_debut = $_POST['heure_debut'] ?? '';
    $heure_fin = $_POST['heure_fin'] ?? '';
    $matiere_id = intval($_POST['matiere_id'] ?? 0);
    $enseignant_id = intval($_POST['enseignant_id'] ?? 0);
    $salle = trim($_POST['salle'] ?? '');

    if ($jour && $heure_debut && $heure_fin && $matiere_id && $enseignant_id) {
        $database->query(
            "UPDATE emplois_temps SET matiere_id=?, enseignant_id=?, jour_semaine=?, heure_debut=?, heure_fin=?, salle=? WHERE id=?",
            [$matiere_id, $enseignant_id, $jour, $heure_debut, $heure_fin, $salle, $schedule_id]
        );
        showMessage('success', 'Horaire modifié avec succès.');
        redirectTo("class.php?id=$class_id");
    } else {
        showMessage('error', 'Veuillez remplir tous les champs obligatoires.');
    }
}

include '../../../includes/header.php';
?>
<div class="container mt-4">
    <h2>Modifier un horaire de la classe : <?php echo htmlspecialchars($classe['nom']); ?></h2>
    <form method="post" class="mt-4">
        <div class="row mb-3">
            <div class="col-md-4">
                <label for="jour_semaine" class="form-label">Jour de la semaine *</label>
                <select name="jour_semaine" id="jour_semaine" class="form-select" required>
                    <option value="">-- Choisir --</option>
                    <?php foreach (["lundi","mardi","mercredi","jeudi","vendredi","samedi"] as $jour): ?>
                        <option value="<?php echo $jour; ?>" <?php if ($schedule['jour_semaine'] === $jour) echo 'selected'; ?>><?php echo ucfirst($jour); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label for="heure_debut" class="form-label">Heure début *</label>
                <input type="time" name="heure_debut" id="heure_debut" class="form-control" value="<?php echo htmlspecialchars(substr($schedule['heure_debut'],0,5)); ?>" required>
            </div>
            <div class="col-md-4">
                <label for="heure_fin" class="form-label">Heure fin *</label>
                <input type="time" name="heure_fin" id="heure_fin" class="form-control" value="<?php echo htmlspecialchars(substr($schedule['heure_fin'],0,5)); ?>" required>
            </div>
        </div>
        <div class="row mb-3">
            <div class="col-md-6">
                <label for="matiere_id" class="form-label">Matière *</label>
                <select name="matiere_id" id="matiere_id" class="form-select" required>
                    <option value="">-- Choisir --</option>
                    <?php foreach ($matieres as $matiere): ?>
                        <option value="<?php echo $matiere['id']; ?>" <?php if ($schedule['matiere_id'] == $matiere['id']) echo 'selected'; ?>><?php echo htmlspecialchars($matiere['nom']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-6">
                <label for="enseignant_id" class="form-label">Enseignant *</label>
                <select name="enseignant_id" id="enseignant_id" class="form-select" required>
                    <option value="">-- Choisir --</option>
                    <?php foreach ($enseignants as $ens): ?>
                        <option value="<?php echo $ens['id']; ?>" <?php if ($schedule['enseignant_id'] == $ens['id']) echo 'selected'; ?>><?php echo htmlspecialchars($ens['nom'] . ' ' . $ens['prenom']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="row mb-3">
            <div class="col-md-6">
                <label for="salle" class="form-label">Salle</label>
                <input type="text" name="salle" id="salle" class="form-control" value="<?php echo htmlspecialchars($schedule['salle'] ?? ''); ?>">
            </div>
        </div>
        <button type="submit" class="btn btn-primary">Enregistrer</button>
        <a href="class.php?id=<?php echo $class_id; ?>" class="btn btn-secondary ms-2">Annuler</a>
    </form>
</div>
<?php include '../../../includes/footer.php'; ?>
