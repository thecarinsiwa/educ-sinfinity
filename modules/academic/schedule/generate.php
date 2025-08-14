<?php
/**
 * Module Académique - Génération automatique d'emploi du temps
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

$page_title = 'Génération d\'emploi du temps';

// Récupérer l'année scolaire active
$current_year = $database->query("SELECT * FROM annees_scolaires WHERE status = 'active' LIMIT 1")->fetch();
if (!$current_year) {
    showMessage('error', 'Aucune année scolaire active trouvée.');
    redirectTo('../../../index.php');
}

// Paramètres de génération
$selected_class = $_POST['classe_id'] ?? '';
$generation_type = $_POST['generation_type'] ?? 'automatic';
$start_time = $_POST['start_time'] ?? '08:00';
$end_time = $_POST['end_time'] ?? '16:00';
$break_duration = $_POST['break_duration'] ?? 30;
$lunch_duration = $_POST['lunch_duration'] ?? 60;

// Traitement de la génération
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generate'])) {
    try {
        $classe_id = (int)$_POST['classe_id'];
        
        if (!$classe_id) {
            throw new Exception('Veuillez sélectionner une classe.');
        }
        
        // Récupérer les informations de la classe
        $classe = $database->query(
            "SELECT * FROM classes WHERE id = ? AND annee_scolaire_id = ?",
            [$classe_id, $current_year['id']]
        )->fetch();
        
        if (!$classe) {
            throw new Exception('Classe non trouvée.');
        }
        
        // Récupérer les matières pour cette classe
        $matieres_classe = $database->query(
            "SELECT m.*, p.nom as enseignant_nom, p.prenom as enseignant_prenom, p.id as enseignant_id
             FROM matieres m
             LEFT JOIN personnel p ON p.specialite = m.nom AND p.fonction = 'enseignant' AND p.status = 'actif'
             WHERE m.niveau = ?
             ORDER BY m.coefficient DESC, m.nom",
            [$classe['niveau']]
        )->fetchAll();

        if (empty($matieres_classe)) {
            throw new Exception('Aucune matière trouvée pour ce niveau.');
        }

        // Récupérer un enseignant par défaut pour les matières sans enseignant assigné
        $enseignant_defaut = $database->query(
            "SELECT id FROM personnel WHERE fonction = 'enseignant' AND status = 'actif' LIMIT 1"
        )->fetch();

        if (!$enseignant_defaut) {
            throw new Exception('Aucun enseignant actif trouvé dans le système. Veuillez d\'abord ajouter des enseignants.');
        }

        // Assigner l'enseignant par défaut aux matières sans enseignant
        foreach ($matieres_classe as &$matiere) {
            if (empty($matiere['enseignant_id'])) {
                $matiere['enseignant_id'] = $enseignant_defaut['id'];
                $matiere['enseignant_nom'] = 'Enseignant';
                $matiere['enseignant_prenom'] = 'Non assigné';
            }
        }
        unset($matiere); // Libérer la référence
        
        // Supprimer l'ancien emploi du temps pour cette classe
        $database->execute(
            "DELETE FROM emploi_temps WHERE classe_id = ? AND annee_scolaire_id = ?",
            [$classe_id, $current_year['id']]
        );
        
        // Générer le nouvel emploi du temps
        $jours_semaine = ['Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi'];
        $heures_debut = ['08:00', '09:00', '10:00', '11:00', '13:00', '14:00', '15:00'];
        $heures_fin = ['09:00', '10:00', '11:00', '12:00', '14:00', '15:00', '16:00'];
        
        $emploi_genere = [];
        $matiere_index = 0;
        
        foreach ($jours_semaine as $jour) {
            for ($h = 0; $h < count($heures_debut); $h++) {
                // Pause déjeuner
                if ($heures_debut[$h] === '12:00') continue;
                
                $matiere = $matieres_classe[$matiere_index % count($matieres_classe)];
                
                $database->execute(
                    "INSERT INTO emploi_temps (classe_id, matiere_id, enseignant_id, jour_semaine, heure_debut, heure_fin, salle, annee_scolaire_id, created_at)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())",
                    [
                        $classe_id,
                        $matiere['id'],
                        $matiere['enseignant_id'],
                        $jour,
                        $heures_debut[$h],
                        $heures_fin[$h],
                        'Salle ' . ($h + 1),
                        $current_year['id']
                    ]
                );
                
                $emploi_genere[] = [
                    'jour' => $jour,
                    'heure_debut' => $heures_debut[$h],
                    'heure_fin' => $heures_fin[$h],
                    'matiere' => $matiere['nom'],
                    'enseignant' => $matiere['enseignant_nom'] . ' ' . $matiere['enseignant_prenom']
                ];
                
                $matiere_index++;
            }
        }
        
        // Enregistrer l'action
        logUserAction(
            'generate_schedule',
            'academic',
            'Emploi du temps généré pour la classe: ' . $classe['nom'],
            $classe_id
        );
        
        showMessage('success', 'Emploi du temps généré avec succès pour la classe ' . $classe['nom'] . '.');
        
    } catch (Exception $e) {
        showMessage('error', 'Erreur lors de la génération: ' . $e->getMessage());
    }
}

// Récupérer les classes
$classes = $database->query(
    "SELECT * FROM classes WHERE annee_scolaire_id = ? ORDER BY niveau, nom",
    [$current_year['id']]
)->fetchAll();

// Récupérer les statistiques
$stats = [];
$stats['total_classes'] = count($classes);
$stats['emplois_generes'] = $database->query(
    "SELECT COUNT(DISTINCT classe_id) as total FROM emploi_temps WHERE annee_scolaire_id = ?",
    [$current_year['id']]
)->fetch()['total'];

include '../../../includes/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">
        <i class="fas fa-calendar-alt me-2"></i>
        Génération d'emploi du temps
    </h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <div class="btn-group me-2">
            <a href="../index.php" class="btn btn-outline-secondary">
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

<!-- Statistiques -->
<div class="row mb-4">
    <div class="col-md-4">
        <div class="card text-center">
            <div class="card-body">
                <i class="fas fa-school fa-2x text-primary mb-2"></i>
                <h3 class="mb-0"><?php echo $stats['total_classes']; ?></h3>
                <small class="text-muted">Classes totales</small>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card text-center">
            <div class="card-body">
                <i class="fas fa-calendar-check fa-2x text-success mb-2"></i>
                <h3 class="mb-0"><?php echo $stats['emplois_generes']; ?></h3>
                <small class="text-muted">Emplois générés</small>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card text-center">
            <div class="card-body">
                <i class="fas fa-percentage fa-2x text-info mb-2"></i>
                <h3 class="mb-0"><?php echo $stats['total_classes'] > 0 ? round(($stats['emplois_generes'] / $stats['total_classes']) * 100) : 0; ?>%</h3>
                <small class="text-muted">Taux de couverture</small>
            </div>
        </div>
    </div>
</div>

<!-- Formulaire de génération -->
<div class="row">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-cogs me-2"></i>
                    Paramètres de génération
                </h5>
            </div>
            <div class="card-body">
                <form method="POST">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="classe_id" class="form-label">Classe <span class="text-danger">*</span></label>
                            <select class="form-select" id="classe_id" name="classe_id" required>
                                <option value="">Sélectionner une classe</option>
                                <?php foreach ($classes as $classe): ?>
                                    <option value="<?php echo $classe['id']; ?>" <?php echo $selected_class == $classe['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($classe['niveau'] . ' - ' . $classe['nom']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="generation_type" class="form-label">Type de génération</label>
                            <select class="form-select" id="generation_type" name="generation_type">
                                <option value="automatic" <?php echo $generation_type === 'automatic' ? 'selected' : ''; ?>>Automatique</option>
                                <option value="balanced" <?php echo $generation_type === 'balanced' ? 'selected' : ''; ?>>Équilibrée</option>
                                <option value="priority" <?php echo $generation_type === 'priority' ? 'selected' : ''; ?>>Par priorité</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-3 mb-3">
                            <label for="start_time" class="form-label">Heure de début</label>
                            <input type="time" class="form-control" id="start_time" name="start_time" value="<?php echo $start_time; ?>">
                        </div>
                        <div class="col-md-3 mb-3">
                            <label for="end_time" class="form-label">Heure de fin</label>
                            <input type="time" class="form-control" id="end_time" name="end_time" value="<?php echo $end_time; ?>">
                        </div>
                        <div class="col-md-3 mb-3">
                            <label for="break_duration" class="form-label">Pause (minutes)</label>
                            <input type="number" class="form-control" id="break_duration" name="break_duration" value="<?php echo $break_duration; ?>" min="15" max="60">
                        </div>
                        <div class="col-md-3 mb-3">
                            <label for="lunch_duration" class="form-label">Déjeuner (minutes)</label>
                            <input type="number" class="form-control" id="lunch_duration" name="lunch_duration" value="<?php echo $lunch_duration; ?>" min="30" max="120">
                        </div>
                    </div>
                    
                    <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                        <button type="submit" name="generate" class="btn btn-primary">
                            <i class="fas fa-magic me-1"></i>
                            Générer l'emploi du temps
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
                    Informations
                </h5>
            </div>
            <div class="card-body">
                <div class="alert alert-info">
                    <h6><i class="fas fa-lightbulb me-2"></i>Comment ça marche ?</h6>
                    <ul class="mb-0">
                        <li><strong>Automatique :</strong> Répartition équitable des matières</li>
                        <li><strong>Équilibrée :</strong> Optimise la charge de travail</li>
                        <li><strong>Par priorité :</strong> Privilégie les matières importantes</li>
                    </ul>
                </div>
                
                <div class="alert alert-warning">
                    <h6><i class="fas fa-exclamation-triangle me-2"></i>Attention</h6>
                    <p class="mb-0">La génération remplacera l'emploi du temps existant pour la classe sélectionnée.</p>
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
                    <a href="add.php" class="btn btn-outline-primary">
                        <i class="fas fa-plus me-2"></i>
                        Ajouter manuellement
                    </a>
                    <a href="conflicts.php" class="btn btn-outline-warning">
                        <i class="fas fa-search me-2"></i>
                        Détecter les conflits
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

<?php if (isset($emploi_genere) && !empty($emploi_genere)): ?>
<!-- Aperçu de l'emploi du temps généré -->
<div class="row mt-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-eye me-2"></i>
                    Aperçu de l'emploi du temps généré
                </h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered">
                        <thead>
                            <tr>
                                <th>Jour</th>
                                <th>Heure</th>
                                <th>Matière</th>
                                <th>Enseignant</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($emploi_genere as $cours): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($cours['jour']); ?></td>
                                    <td><?php echo htmlspecialchars($cours['heure_debut'] . ' - ' . $cours['heure_fin']); ?></td>
                                    <td><?php echo htmlspecialchars($cours['matiere']); ?></td>
                                    <td><?php echo htmlspecialchars($cours['enseignant']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
// Validation du formulaire
document.querySelector('form').addEventListener('submit', function(e) {
    const classeId = document.getElementById('classe_id').value;
    
    if (!classeId) {
        e.preventDefault();
        alert('Veuillez sélectionner une classe.');
        return;
    }
    
    if (!confirm('Êtes-vous sûr de vouloir générer un nouvel emploi du temps ? Cela remplacera l\'emploi existant.')) {
        e.preventDefault();
    }
});
</script>

<?php include '../../../includes/footer.php'; ?>
