<?php
/**
 * Ajouter un horaire à une classe
 * URL: add-schedule.php?class_id=1
 */

require_once '../../../config/config.php';
require_once '../../../config/database.php';
require_once '../../../includes/functions.php';

requireLogin();
if (!checkPermission('academic')) {
    redirectTo('../../login.php');
}

$class_id = isset($_GET['class_id']) ? intval($_GET['class_id']) : 0;
if ($class_id <= 0) {
    die('ID de classe invalide.');
}

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
    $annee_scolaire_id = $classe['annee_scolaire_id'];

    if ($jour && $heure_debut && $heure_fin && $matiere_id && $enseignant_id) {
        $database->query(
            "INSERT INTO emplois_temps (classe_id, matiere_id, enseignant_id, jour_semaine, heure_debut, heure_fin, salle, annee_scolaire_id)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
            [$class_id, $matiere_id, $enseignant_id, $jour, $heure_debut, $heure_fin, $salle, $annee_scolaire_id]
        );
        showMessage('success', 'Horaire ajouté avec succès.');
        redirectTo("class.php?id=$class_id");
    } else {
        showMessage('error', 'Veuillez remplir tous les champs obligatoires.');
    }
}

include '../../../includes/header.php';
?>
<div class="container mt-4">
    <h2>Ajouter un horaire à la classe : <?php echo htmlspecialchars($classe['nom']); ?></h2>
    <form method="post" class="mt-4">
        <div class="row mb-3">
            <div class="col-md-4">
                <label for="jour_semaine" class="form-label">Jour de la semaine *</label>
                <select name="jour_semaine" id="jour_semaine" class="form-select" required>
                    <option value="">-- Choisir --</option>
                    <?php foreach (["lundi","mardi","mercredi","jeudi","vendredi","samedi"] as $jour): ?>
                        <option value="<?php echo $jour; ?>"><?php echo ucfirst($jour); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label for="heure_debut" class="form-label">Heure début *</label>
                <input type="time" name="heure_debut" id="heure_debut" class="form-control" required>
            </div>
            <div class="col-md-4">
                <label for="heure_fin" class="form-label">Heure fin *</label>
                <input type="time" name="heure_fin" id="heure_fin" class="form-control" required>
            </div>
        </div>
        <div class="row mb-3">
            <div class="col-md-6">
                <label for="matiere_id" class="form-label">Matière *</label>
                <select name="matiere_id" id="matiere_id" class="form-select" required>
                    <option value="">-- Choisir --</option>
                    <?php foreach ($matieres as $matiere): ?>
                        <option value="<?php echo $matiere['id']; ?>"><?php echo htmlspecialchars($matiere['nom']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-6">
                <label for="enseignant_id" class="form-label">Enseignant *</label>
                <select name="enseignant_id" id="enseignant_id" class="form-select" required>
                    <option value="">-- Choisir --</option>
                    <?php foreach ($enseignants as $ens): ?>
                        <option value="<?php echo $ens['id']; ?>"><?php echo htmlspecialchars($ens['nom'] . ' ' . $ens['prenom']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="row mb-3">
            <div class="col-md-6">
                <label for="salle" class="form-label">Salle</label>
                <input type="text" name="salle" id="salle" class="form-control">
            </div>
        </div>
        <button type="submit" class="btn btn-primary">Ajouter</button>
        <a href="class.php?id=<?php echo $class_id; ?>" class="btn btn-secondary ms-2">Annuler</a>
    </form>
</div>
<?php include '../../../includes/footer.php'; ?>
