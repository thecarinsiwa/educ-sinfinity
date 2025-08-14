<?php
/**
 * Module Académique - Résolution de conflits d'emploi du temps
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

$page_title = "Résolution de conflits d'emploi du temps";

// Récupérer l'année scolaire active
$current_year = $database->query("SELECT * FROM annees_scolaires WHERE status = 'active' LIMIT 1")->fetch();
if (!$current_year) {
    showMessage('error', 'Aucune année scolaire active trouvée.');
    redirectTo('../../../index.php');
}

// Récupérer les IDs des cours en conflit
$cours1_id = $_GET['cours1'] ?? 0;
$cours2_id = $_GET['cours2'] ?? 0;

if (!$cours1_id || !$cours2_id) {
    showMessage('error', 'IDs de cours manquants.');
    redirectTo('index.php');
}

// Récupérer les détails des cours en conflit
$cours1 = $database->query(
    "SELECT e.*, m.nom as matiere_nom, m.code as matiere_code,
            CONCAT(p.nom, ' ', p.prenom) as enseignant_nom,
            c.nom as classe_nom, c.niveau
     FROM emplois_temps e
     JOIN matieres m ON e.matiere_id = m.id
     JOIN personnel p ON e.enseignant_id = p.id
     JOIN classes c ON e.classe_id = c.id
     WHERE e.id = ? AND e.annee_scolaire_id = ?",
    [$cours1_id, $current_year['id']]
)->fetch();

$cours2 = $database->query(
    "SELECT e.*, m.nom as matiere_nom, m.code as matiere_code,
            CONCAT(p.nom, ' ', p.prenom) as enseignant_nom,
            c.nom as classe_nom, c.niveau
     FROM emplois_temps e
     JOIN matieres m ON e.matiere_id = m.id
     JOIN personnel p ON e.enseignant_id = p.id
     JOIN classes c ON e.classe_id = c.id
     WHERE e.id = ? AND e.annee_scolaire_id = ?",
    [$cours2_id, $current_year['id']]
)->fetch();

if (!$cours1 || !$cours2) {
    showMessage('error', 'Un ou plusieurs cours non trouvés.');
    redirectTo('index.php');
}

// Analyser le type de conflit
$conflict_types = [];
if ($cours1['enseignant_id'] == $cours2['enseignant_id']) {
    $conflict_types[] = 'enseignant';
}
if ($cours1['salle'] && $cours2['salle'] && $cours1['salle'] == $cours2['salle']) {
    $conflict_types[] = 'salle';
}
if ($cours1['classe_id'] == $cours2['classe_id']) {
    $conflict_types[] = 'classe';
}

// Traitement de la résolution
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $action = $_POST['action'] ?? '';
        
        switch ($action) {
            case 'modify_time':
                $new_heure_debut = $_POST['new_heure_debut'];
                $new_heure_fin = $_POST['new_heure_fin'];
                $cours_to_modify = $_POST['cours_to_modify'];
                
                $database->execute(
                    "UPDATE emplois_temps SET heure_debut = ?, heure_fin = ? WHERE id = ?",
                    [$new_heure_debut, $new_heure_fin, $cours_to_modify]
                );
                
                logUserAction('resolve_conflict', 'academic', "Conflit résolu en modifiant l'horaire du cours ID: $cours_to_modify");
                showMessage('success', 'Conflit résolu en modifiant l\'horaire.');
                break;
                
            case 'change_teacher':
                $new_enseignant_id = $_POST['new_enseignant_id'];
                $cours_to_modify = $_POST['cours_to_modify'];
                
                $database->execute(
                    "UPDATE emplois_temps SET enseignant_id = ? WHERE id = ?",
                    [$new_enseignant_id, $cours_to_modify]
                );
                
                logUserAction('resolve_conflict', 'academic', "Conflit résolu en changeant l'enseignant du cours ID: $cours_to_modify");
                showMessage('success', 'Conflit résolu en changeant l\'enseignant.');
                break;
                
            case 'change_room':
                $new_salle = $_POST['new_salle'];
                $cours_to_modify = $_POST['cours_to_modify'];
                
                $database->execute(
                    "UPDATE emplois_temps SET salle = ? WHERE id = ?",
                    [$new_salle, $cours_to_modify]
                );
                
                logUserAction('resolve_conflict', 'academic', "Conflit résolu en changeant la salle du cours ID: $cours_to_modify");
                showMessage('success', 'Conflit résolu en changeant la salle.');
                break;
                
            case 'delete_course':
                $cours_to_delete = $_POST['cours_to_delete'];
                
                $database->execute("DELETE FROM emplois_temps WHERE id = ?", [$cours_to_delete]);
                
                logUserAction('resolve_conflict', 'academic', "Conflit résolu en supprimant le cours ID: $cours_to_delete");
                showMessage('success', 'Conflit résolu en supprimant le cours.');
                break;
        }
        
        redirectTo('index.php');
        
    } catch (Exception $e) {
        showMessage('error', 'Erreur lors de la résolution: ' . $e->getMessage());
    }
}

// Récupérer les enseignants disponibles
$enseignants = $database->query(
    "SELECT id, CONCAT(nom, ' ', prenom) as nom_complet, specialite 
     FROM personnel 
     WHERE (fonction = 'enseignant' OR type = 'enseignant') AND status = 'actif'
     ORDER BY nom, prenom"
)->fetchAll();

include '../../../includes/header.php';
?>

<div class="container mt-4">
    <div class="row justify-content-center">
        <div class="col-lg-10">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-warning text-dark">
                    <h4 class="mb-0">
                        <i class="bi bi-exclamation-triangle"></i>
                        Résolution de conflit d'emploi du temps
                    </h4>
                </div>
                <div class="card-body">
                    <!-- Affichage du conflit -->
                    <div class="alert alert-warning">
                        <h5><i class="bi bi-clock-history"></i> Conflit détecté</h5>
                        <p class="mb-0">
                            Les deux cours suivants sont programmés au même moment :
                            <strong><?php echo ucfirst($cours1['jour_semaine']); ?> de <?php echo substr($cours1['heure_debut'], 0, 5); ?> à <?php echo substr($cours1['heure_fin'], 0, 5); ?></strong>
                        </p>
                        
                        <?php if (in_array('enseignant', $conflict_types)): ?>
                            <div class="mt-2">
                                <span class="badge bg-danger">
                                    <i class="bi bi-person-x"></i> Conflit d'enseignant : <?php echo $cours1['enseignant_nom']; ?>
                                </span>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (in_array('salle', $conflict_types)): ?>
                            <div class="mt-2">
                                <span class="badge bg-warning text-dark">
                                    <i class="bi bi-door-closed"></i> Conflit de salle : <?php echo $cours1['salle']; ?>
                                </span>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (in_array('classe', $conflict_types)): ?>
                            <div class="mt-2">
                                <span class="badge bg-info">
                                    <i class="bi bi-people"></i> Conflit de classe : <?php echo $cours1['classe_nom']; ?>
                                </span>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Détails des cours en conflit -->
                    <div class="row">
                        <div class="col-md-6">
                            <div class="card border-danger">
                                <div class="card-header bg-danger text-white">
                                    <h6 class="mb-0">Cours #1</h6>
                                </div>
                                <div class="card-body">
                                    <p><strong>Matière :</strong> <?php echo htmlspecialchars($cours1['matiere_nom']); ?></p>
                                    <p><strong>Classe :</strong> <?php echo htmlspecialchars($cours1['classe_nom']); ?></p>
                                    <p><strong>Enseignant :</strong> <?php echo htmlspecialchars($cours1['enseignant_nom']); ?></p>
                                    <p><strong>Horaire :</strong> <?php echo ucfirst($cours1['jour_semaine']) . ' ' . substr($cours1['heure_debut'], 0, 5) . '-' . substr($cours1['heure_fin'], 0, 5); ?></p>
                                    <p><strong>Salle :</strong> <?php echo htmlspecialchars($cours1['salle'] ?: 'Non définie'); ?></p>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="card border-danger">
                                <div class="card-header bg-danger text-white">
                                    <h6 class="mb-0">Cours #2</h6>
                                </div>
                                <div class="card-body">
                                    <p><strong>Matière :</strong> <?php echo htmlspecialchars($cours2['matiere_nom']); ?></p>
                                    <p><strong>Classe :</strong> <?php echo htmlspecialchars($cours2['classe_nom']); ?></p>
                                    <p><strong>Enseignant :</strong> <?php echo htmlspecialchars($cours2['enseignant_nom']); ?></p>
                                    <p><strong>Horaire :</strong> <?php echo ucfirst($cours2['jour_semaine']) . ' ' . substr($cours2['heure_debut'], 0, 5) . '-' . substr($cours2['heure_fin'], 0, 5); ?></p>
                                    <p><strong>Salle :</strong> <?php echo htmlspecialchars($cours2['salle'] ?: 'Non définie'); ?></p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Options de résolution -->
                    <div class="mt-4">
                        <h5><i class="bi bi-tools"></i> Options de résolution</h5>
                        
                        <!-- Modifier l'horaire -->
                        <div class="card mb-3">
                            <div class="card-header">
                                <h6 class="mb-0">
                                    <i class="bi bi-clock"></i> Modifier l'horaire
                                </h6>
                            </div>
                            <div class="card-body">
                                <form method="POST" class="row g-3">
                                    <input type="hidden" name="action" value="modify_time">
                                    
                                    <div class="col-md-4">
                                        <label class="form-label">Cours à modifier</label>
                                        <select name="cours_to_modify" class="form-select" required>
                                            <option value="<?php echo $cours1['id']; ?>">Cours #1 - <?php echo $cours1['matiere_nom']; ?></option>
                                            <option value="<?php echo $cours2['id']; ?>">Cours #2 - <?php echo $cours2['matiere_nom']; ?></option>
                                        </select>
                                    </div>
                                    
                                    <div class="col-md-3">
                                        <label class="form-label">Nouvelle heure début</label>
                                        <input type="time" name="new_heure_debut" class="form-control" required>
                                    </div>
                                    
                                    <div class="col-md-3">
                                        <label class="form-label">Nouvelle heure fin</label>
                                        <input type="time" name="new_heure_fin" class="form-control" required>
                                    </div>
                                    
                                    <div class="col-md-2">
                                        <label class="form-label">&nbsp;</label>
                                        <button type="submit" class="btn btn-primary d-block">
                                            <i class="bi bi-check"></i> Modifier
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>

                        <?php if (in_array('enseignant', $conflict_types)): ?>
                        <!-- Changer l'enseignant -->
                        <div class="card mb-3">
                            <div class="card-header">
                                <h6 class="mb-0">
                                    <i class="bi bi-person-check"></i> Changer l'enseignant
                                </h6>
                            </div>
                            <div class="card-body">
                                <form method="POST" class="row g-3">
                                    <input type="hidden" name="action" value="change_teacher">
                                    
                                    <div class="col-md-4">
                                        <label class="form-label">Cours à modifier</label>
                                        <select name="cours_to_modify" class="form-select" required>
                                            <option value="<?php echo $cours1['id']; ?>">Cours #1 - <?php echo $cours1['matiere_nom']; ?></option>
                                            <option value="<?php echo $cours2['id']; ?>">Cours #2 - <?php echo $cours2['matiere_nom']; ?></option>
                                        </select>
                                    </div>
                                    
                                    <div class="col-md-6">
                                        <label class="form-label">Nouvel enseignant</label>
                                        <select name="new_enseignant_id" class="form-select" required>
                                            <option value="">Sélectionner un enseignant</option>
                                            <?php foreach ($enseignants as $enseignant): ?>
                                                <option value="<?php echo $enseignant['id']; ?>">
                                                    <?php echo htmlspecialchars($enseignant['nom_complet']); ?>
                                                    <?php if ($enseignant['specialite']): ?>
                                                        (<?php echo htmlspecialchars($enseignant['specialite']); ?>)
                                                    <?php endif; ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    
                                    <div class="col-md-2">
                                        <label class="form-label">&nbsp;</label>
                                        <button type="submit" class="btn btn-success d-block">
                                            <i class="bi bi-check"></i> Changer
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                        <?php endif; ?>

                        <?php if (in_array('salle', $conflict_types)): ?>
                        <!-- Changer la salle -->
                        <div class="card mb-3">
                            <div class="card-header">
                                <h6 class="mb-0">
                                    <i class="bi bi-door-open"></i> Changer la salle
                                </h6>
                            </div>
                            <div class="card-body">
                                <form method="POST" class="row g-3">
                                    <input type="hidden" name="action" value="change_room">
                                    
                                    <div class="col-md-4">
                                        <label class="form-label">Cours à modifier</label>
                                        <select name="cours_to_modify" class="form-select" required>
                                            <option value="<?php echo $cours1['id']; ?>">Cours #1 - <?php echo $cours1['matiere_nom']; ?></option>
                                            <option value="<?php echo $cours2['id']; ?>">Cours #2 - <?php echo $cours2['matiere_nom']; ?></option>
                                        </select>
                                    </div>
                                    
                                    <div class="col-md-6">
                                        <label class="form-label">Nouvelle salle</label>
                                        <input type="text" name="new_salle" class="form-control" placeholder="Ex: Salle 105" required>
                                    </div>
                                    
                                    <div class="col-md-2">
                                        <label class="form-label">&nbsp;</label>
                                        <button type="submit" class="btn btn-warning">
                                            <i class="bi bi-check"></i> Changer
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                        <?php endif; ?>

                        <!-- Supprimer un cours -->
                        <div class="card mb-3 border-danger">
                            <div class="card-header bg-danger text-white">
                                <h6 class="mb-0">
                                    <i class="bi bi-trash"></i> Supprimer un cours
                                </h6>
                            </div>
                            <div class="card-body">
                                <p class="text-muted">Cette action est irréversible.</p>
                                <form method="POST" class="row g-3" onsubmit="return confirm('Êtes-vous sûr de vouloir supprimer ce cours ?')">
                                    <input type="hidden" name="action" value="delete_course">
                                    
                                    <div class="col-md-8">
                                        <label class="form-label">Cours à supprimer</label>
                                        <select name="cours_to_delete" class="form-select" required>
                                            <option value="<?php echo $cours1['id']; ?>">Cours #1 - <?php echo $cours1['matiere_nom']; ?> (<?php echo $cours1['classe_nom']; ?>)</option>
                                            <option value="<?php echo $cours2['id']; ?>">Cours #2 - <?php echo $cours2['matiere_nom']; ?> (<?php echo $cours2['classe_nom']; ?>)</option>
                                        </select>
                                    </div>
                                    
                                    <div class="col-md-4">
                                        <label class="form-label">&nbsp;</label>
                                        <button type="submit" class="btn btn-danger d-block">
                                            <i class="bi bi-trash"></i> Supprimer
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>

                    <!-- Actions -->
                    <div class="d-flex justify-content-between mt-4">
                        <a href="index.php" class="btn btn-secondary">
                            <i class="bi bi-arrow-left"></i> Retour à l'emploi du temps
                        </a>
                        <a href="detect-conflicts.php" class="btn btn-outline-warning">
                            <i class="bi bi-search"></i> Détecter d'autres conflits
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../../../includes/footer.php'; ?>
