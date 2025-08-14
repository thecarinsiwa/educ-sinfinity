<?php
/**
 * Module d'évaluations - Modifier une évaluation
 * Application de gestion scolaire - République Démocratique du Congo
 */

require_once '../../../config/config.php';
require_once '../../../config/database.php';
require_once '../../../includes/functions.php';

// Vérifier l'authentification et les permissions
requireLogin();
if (!checkPermission('evaluations')) {
    showMessage('error', 'Accès refusé à cette fonctionnalité.');
    redirectTo('index.php');
}

// Récupérer l'ID de l'évaluation
$evaluation_id = (int)($_GET['id'] ?? 0);
if (!$evaluation_id) {
    showMessage('error', 'ID d\'évaluation manquant.');
    redirectTo('index.php');
}

// Récupérer l'année scolaire active
$current_year = $database->query("SELECT * FROM annees_scolaires WHERE status = 'active' LIMIT 1")->fetch();
if (!$current_year) {
    showMessage('error', 'Aucune année scolaire active trouvée.');
    redirectTo('../../../index.php');
}

// Récupérer les données de l'évaluation
$evaluation = $database->query(
    "SELECT e.*, 
            m.nom as matiere_nom,
            c.nom as classe_nom,
            CONCAT(p.nom, ' ', p.prenom) as enseignant_nom
     FROM evaluations e
     JOIN matieres m ON e.matiere_id = m.id
     JOIN classes c ON e.classe_id = c.id
     LEFT JOIN personnel p ON e.enseignant_id = p.id
     WHERE e.id = ?",
    [$evaluation_id]
)->fetch();

if (!$evaluation) {
    showMessage('error', 'Évaluation non trouvée.');
    redirectTo('index.php');
}

$page_title = 'Modifier l\'évaluation : ' . $evaluation['nom'];

// Vérifier s'il y a des notes saisies
$notes_count = $database->query(
    "SELECT COUNT(*) as count FROM notes WHERE evaluation_id = ?",
    [$evaluation_id]
)->fetch()['count'];

// Récupérer les listes pour les sélecteurs
$classes = $database->query(
    "SELECT * FROM classes WHERE annee_scolaire_id = ? ORDER BY niveau, nom",
    [$current_year['id']]
)->fetchAll();

$matieres = $database->query(
    "SELECT * FROM matieres ORDER BY niveau, nom"
)->fetchAll();

$enseignants = $database->query(
    "SELECT id, nom, prenom, specialite
     FROM personnel
     WHERE fonction = 'enseignant' AND status = 'actif'
     ORDER BY nom, prenom"
)->fetchAll();

$errors = [];
$success = false;

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Récupération et nettoyage des données
    $nom = sanitizeInput($_POST['nom'] ?? '');
    $description = sanitizeInput($_POST['description'] ?? '');
    $type_evaluation = sanitizeInput($_POST['type_evaluation'] ?? '');
    $classe_id = (int)($_POST['classe_id'] ?? 0);
    $matiere_id = (int)($_POST['matiere_id'] ?? 0);
    $enseignant_id = (int)($_POST['enseignant_id'] ?? 0);
    $periode = sanitizeInput($_POST['periode'] ?? '');
    $date_evaluation = sanitizeInput($_POST['date_evaluation'] ?? '');
    $heure_debut = sanitizeInput($_POST['heure_debut'] ?? '');
    $heure_fin = sanitizeInput($_POST['heure_fin'] ?? '');
    $duree_minutes = (int)($_POST['duree_minutes'] ?? 0) ?: null;
    $note_max = (float)($_POST['note_max'] ?? 20);
    $coefficient_evaluation = (float)($_POST['coefficient_evaluation'] ?? 1);
    $bareme = sanitizeInput($_POST['bareme'] ?? '');
    $consignes = sanitizeInput($_POST['consignes'] ?? '');
    $status = sanitizeInput($_POST['status'] ?? 'programmee');
    
    // Validation des champs obligatoires
    if (empty($nom)) $errors[] = 'Le nom de l\'évaluation est obligatoire.';
    if (empty($type_evaluation)) $errors[] = 'Le type d\'évaluation est obligatoire.';
    if (!$classe_id) $errors[] = 'La classe est obligatoire.';
    if (!$matiere_id) $errors[] = 'La matière est obligatoire.';
    if (!$enseignant_id) $errors[] = 'L\'enseignant est obligatoire.';
    if (empty($periode)) $errors[] = 'La période est obligatoire.';
    if (empty($date_evaluation)) $errors[] = 'La date d\'évaluation est obligatoire.';
    
    // Validation de la date
    if (!empty($date_evaluation) && !isValidDate($date_evaluation)) {
        $errors[] = 'La date d\'évaluation n\'est pas valide.';
    }
    
    // Validation des heures
    if (!empty($heure_debut) && !empty($heure_fin)) {
        if ($heure_debut >= $heure_fin) {
            $errors[] = 'L\'heure de fin doit être postérieure à l\'heure de début.';
        }
    }
    
    // Validation de la note max
    if ($note_max <= 0 || $note_max > 100) {
        $errors[] = 'La note maximale doit être comprise entre 1 et 100.';
    }
    
    // Validation du coefficient
    if ($coefficient_evaluation <= 0 || $coefficient_evaluation > 10) {
        $errors[] = 'Le coefficient doit être compris entre 0.1 et 10.';
    }
    
    // Vérifier que la classe existe
    if ($classe_id) {
        $stmt = $database->query("SELECT id FROM classes WHERE id = ? AND annee_scolaire_id = ?", [$classe_id, $current_year['id']]);
        if (!$stmt->fetch()) {
            $errors[] = 'La classe sélectionnée n\'existe pas.';
        }
    }
    
    // Vérifier que la matière existe
    if ($matiere_id) {
        $stmt = $database->query("SELECT id FROM matieres WHERE id = ?", [$matiere_id]);
        if (!$stmt->fetch()) {
            $errors[] = 'La matière sélectionnée n\'existe pas.';
        }
    }
    
    // Vérifier que l'enseignant existe
    if ($enseignant_id) {
        $stmt = $database->query("SELECT id FROM personnel WHERE id = ?", [$enseignant_id]);
        if (!$stmt->fetch()) {
            $errors[] = 'L\'enseignant sélectionné n\'existe pas.';
        }
    }
    
    // Vérification des conflits d'horaires (si les heures sont spécifiées)
    if (!empty($heure_debut) && !empty($heure_fin) && $classe_id && empty($errors)) {
        $conflict_check = $database->query(
            "SELECT id, nom FROM evaluations 
             WHERE id != ? AND classe_id = ? AND date_evaluation = ? 
             AND ((heure_debut < ? AND heure_fin > ?) OR (heure_debut < ? AND heure_fin > ?))
             AND annee_scolaire_id = ?",
            [
                $evaluation_id, $classe_id, $date_evaluation,
                $heure_fin, $heure_debut, $heure_debut, $heure_fin,
                $current_year['id']
            ]
        )->fetch();
        
        if ($conflict_check) {
            $errors[] = 'Conflit d\'horaire avec l\'évaluation "' . $conflict_check['nom'] . '".';
        }
    }
    
    // Si pas d'erreurs, mettre à jour l'évaluation
    if (empty($errors)) {
        try {
            $sql = "UPDATE evaluations SET
                        nom = ?, description = ?, type_evaluation = ?, classe_id = ?, matiere_id = ?,
                        enseignant_id = ?, periode = ?, date_evaluation = ?, heure_debut = ?,
                        heure_fin = ?, duree_minutes = ?, note_max = ?, coefficient = ?,
                        bareme = ?, consignes = ?, status = ?, updated_at = NOW()
                    WHERE id = ?";
            
            $database->execute($sql, [
                $nom, $description, $type_evaluation, $classe_id, $matiere_id,
                $enseignant_id, $periode, $date_evaluation, $heure_debut,
                $heure_fin, $duree_minutes, $note_max, $coefficient_evaluation,
                $bareme, $consignes, $status, $evaluation_id
            ]);
            
            // Enregistrer l'action
            logUserAction(
                'update_evaluation',
                'evaluations',
                "Évaluation modifiée: $nom (ID: $evaluation_id)",
                $evaluation_id
            );
            
            showMessage('success', 'Évaluation modifiée avec succès.');
            
            // Recharger les données mises à jour
            $evaluation = $database->query(
                "SELECT e.*, 
                        m.nom as matiere_nom,
                        c.nom as classe_nom,
                        CONCAT(p.nom, ' ', p.prenom) as enseignant_nom
                 FROM evaluations e
                 JOIN matieres m ON e.matiere_id = m.id
                 JOIN classes c ON e.classe_id = c.id
                 LEFT JOIN personnel p ON e.enseignant_id = p.id
                 WHERE e.id = ?",
                [$evaluation_id]
            )->fetch();
            
            $success = true;
            
        } catch (Exception $e) {
            $errors[] = 'Erreur lors de la modification: ' . $e->getMessage();
        }
    }
}

include '../../../includes/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">
        <i class="fas fa-edit me-2"></i>
        Modifier l'évaluation
    </h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <div class="btn-group me-2">
            <a href="index.php" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-1"></i>
                Retour à la liste
            </a>
            <a href="view.php?id=<?php echo $evaluation_id; ?>" class="btn btn-outline-info">
                <i class="fas fa-eye me-1"></i>
                Voir détails
            </a>
        </div>
    </div>
</div>

<?php if (!empty($errors)): ?>
    <div class="alert alert-danger">
        <h5><i class="fas fa-exclamation-triangle me-2"></i>Erreurs de validation</h5>
        <ul class="mb-0">
            <?php foreach ($errors as $error): ?>
                <li><?php echo htmlspecialchars($error); ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<?php if ($success): ?>
    <div class="alert alert-success">
        <i class="fas fa-check-circle me-2"></i>
        L'évaluation a été modifiée avec succès.
    </div>
<?php endif; ?>

<?php if ($notes_count > 0): ?>
    <div class="alert alert-warning">
        <h6><i class="fas fa-exclamation-triangle me-2"></i>Attention</h6>
        <p class="mb-0">
            Cette évaluation a déjà <strong><?php echo $notes_count; ?> note(s) saisie(s)</strong>. 
            Certaines modifications (note max, coefficient) peuvent affecter les calculs existants.
        </p>
    </div>
<?php endif; ?>

<div class="row">
    <div class="col-lg-8">
        <!-- Formulaire de modification -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-edit me-2"></i>
                    Informations de l'évaluation
                </h5>
            </div>
            <div class="card-body">
                <form method="POST" class="needs-validation" novalidate>
                    <div class="row">
                        <div class="col-md-8 mb-3">
                            <label for="nom" class="form-label">
                                Nom de l'évaluation <span class="text-danger">*</span>
                            </label>
                            <input type="text" 
                                   class="form-control" 
                                   id="nom" 
                                   name="nom" 
                                   value="<?php echo htmlspecialchars($evaluation['nom']); ?>"
                                   placeholder="Ex: Composition du 1er trimestre"
                                   required>
                            <div class="invalid-feedback">
                                Veuillez saisir le nom de l'évaluation.
                            </div>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="type_evaluation" class="form-label">Type <span class="text-danger">*</span></label>
                            <select class="form-select" id="type_evaluation" name="type_evaluation" required>
                                <option value="">Sélectionner...</option>
                                <option value="interrogation" <?php echo $evaluation['type_evaluation'] === 'interrogation' ? 'selected' : ''; ?>>Interrogation</option>
                                <option value="devoir" <?php echo $evaluation['type_evaluation'] === 'devoir' ? 'selected' : ''; ?>>Devoir</option>
                                <option value="examen" <?php echo $evaluation['type_evaluation'] === 'examen' ? 'selected' : ''; ?>>Examen</option>
                                <option value="composition" <?php echo $evaluation['type_evaluation'] === 'composition' ? 'selected' : ''; ?>>Composition</option>
                            </select>
                            <div class="invalid-feedback">
                                Veuillez sélectionner un type.
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="classe_id" class="form-label">Classe <span class="text-danger">*</span></label>
                            <select class="form-select" id="classe_id" name="classe_id" required>
                                <option value="">Sélectionner une classe...</option>
                                <?php foreach ($classes as $classe): ?>
                                    <option value="<?php echo $classe['id']; ?>" 
                                            <?php echo $evaluation['classe_id'] == $classe['id'] ? 'selected' : ''; ?>
                                            data-niveau="<?php echo $classe['niveau']; ?>">
                                        <?php echo htmlspecialchars($classe['nom']); ?> 
                                        (<?php echo ucfirst($classe['niveau']); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="invalid-feedback">
                                Veuillez sélectionner une classe.
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="matiere_id" class="form-label">Matière <span class="text-danger">*</span></label>
                            <select class="form-select" id="matiere_id" name="matiere_id" required>
                                <option value="">Sélectionner une matière...</option>
                                <?php foreach ($matieres as $matiere): ?>
                                    <option value="<?php echo $matiere['id']; ?>" 
                                            <?php echo $evaluation['matiere_id'] == $matiere['id'] ? 'selected' : ''; ?>
                                            data-coefficient="<?php echo $matiere['coefficient']; ?>">
                                        <?php echo htmlspecialchars($matiere['nom']); ?>
                                        <?php if ($matiere['coefficient']): ?>
                                            (Coef. <?php echo $matiere['coefficient']; ?>)
                                        <?php endif; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="invalid-feedback">
                                Veuillez sélectionner une matière.
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="enseignant_id" class="form-label">Enseignant <span class="text-danger">*</span></label>
                            <select class="form-select" id="enseignant_id" name="enseignant_id" required>
                                <option value="">Sélectionner un enseignant...</option>
                                <?php foreach ($enseignants as $enseignant): ?>
                                    <option value="<?php echo $enseignant['id']; ?>" 
                                            <?php echo $evaluation['enseignant_id'] == $enseignant['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($enseignant['nom'] . ' ' . $enseignant['prenom']); ?>
                                        <?php if ($enseignant['specialite']): ?>
                                            (<?php echo htmlspecialchars($enseignant['specialite']); ?>)
                                        <?php endif; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="invalid-feedback">
                                Veuillez sélectionner un enseignant.
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="periode" class="form-label">Période <span class="text-danger">*</span></label>
                            <select class="form-select" id="periode" name="periode" required>
                                <option value="">Sélectionner...</option>
                                <option value="1er_trimestre" <?php echo $evaluation['periode'] === '1er_trimestre' ? 'selected' : ''; ?>>1er Trimestre</option>
                                <option value="2eme_trimestre" <?php echo $evaluation['periode'] === '2eme_trimestre' ? 'selected' : ''; ?>>2ème Trimestre</option>
                                <option value="3eme_trimestre" <?php echo $evaluation['periode'] === '3eme_trimestre' ? 'selected' : ''; ?>>3ème Trimestre</option>
                                <option value="annuelle" <?php echo $evaluation['periode'] === 'annuelle' ? 'selected' : ''; ?>>Annuelle</option>
                            </select>
                            <div class="invalid-feedback">
                                Veuillez sélectionner une période.
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="description" class="form-label">Description</label>
                        <textarea class="form-control" 
                                  id="description" 
                                  name="description" 
                                  rows="3"
                                  placeholder="Description de l'évaluation..."><?php echo htmlspecialchars($evaluation['description'] ?? ''); ?></textarea>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label for="date_evaluation" class="form-label">Date <span class="text-danger">*</span></label>
                            <input type="date" 
                                   class="form-control" 
                                   id="date_evaluation" 
                                   name="date_evaluation" 
                                   value="<?php echo $evaluation['date_evaluation']; ?>"
                                   required>
                            <div class="invalid-feedback">
                                Veuillez sélectionner une date.
                            </div>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="heure_debut" class="form-label">Heure début</label>
                            <input type="time" 
                                   class="form-control" 
                                   id="heure_debut" 
                                   name="heure_debut" 
                                   value="<?php echo $evaluation['heure_debut'] ? substr($evaluation['heure_debut'], 0, 5) : ''; ?>">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="heure_fin" class="form-label">Heure fin</label>
                            <input type="time" 
                                   class="form-control" 
                                   id="heure_fin" 
                                   name="heure_fin" 
                                   value="<?php echo $evaluation['heure_fin'] ? substr($evaluation['heure_fin'], 0, 5) : ''; ?>">
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label for="duree_minutes" class="form-label">Durée (minutes)</label>
                            <input type="number" 
                                   class="form-control" 
                                   id="duree_minutes" 
                                   name="duree_minutes" 
                                   value="<?php echo $evaluation['duree_minutes'] ?? ''; ?>"
                                   min="1" 
                                   max="480"
                                   placeholder="Ex: 60">
                            <small class="form-text text-muted">Optionnel (1-480 minutes)</small>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="note_max" class="form-label">Note maximale <span class="text-danger">*</span></label>
                            <input type="number" 
                                   class="form-control" 
                                   id="note_max" 
                                   name="note_max" 
                                   value="<?php echo $evaluation['note_max']; ?>"
                                   min="1" 
                                   max="100" 
                                   step="0.25"
                                   required>
                            <div class="invalid-feedback">
                                Note entre 1 et 100.
                            </div>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="coefficient_evaluation" class="form-label">Coefficient <span class="text-danger">*</span></label>
                            <input type="number" 
                                   class="form-control" 
                                   id="coefficient_evaluation" 
                                   name="coefficient_evaluation" 
                                   value="<?php echo $evaluation['coefficient']; ?>"
                                   min="0.1" 
                                   max="10" 
                                   step="0.1"
                                   required>
                            <div class="invalid-feedback">
                                Coefficient entre 0.1 et 10.
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="bareme" class="form-label">Barème</label>
                        <textarea class="form-control" 
                                  id="bareme" 
                                  name="bareme" 
                                  rows="3"
                                  placeholder="Ex: Exercice 1: 8 pts, Exercice 2: 7 pts, Exercice 3: 5 pts"><?php echo htmlspecialchars($evaluation['bareme'] ?? ''); ?></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label for="consignes" class="form-label">Consignes</label>
                        <textarea class="form-control" 
                                  id="consignes" 
                                  name="consignes" 
                                  rows="4"
                                  placeholder="Consignes et instructions pour l'évaluation..."><?php echo htmlspecialchars($evaluation['consignes'] ?? ''); ?></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label for="status" class="form-label">Statut</label>
                        <select class="form-select" id="status" name="status">
                            <option value="programmee" <?php echo ($evaluation['status'] ?? 'programmee') === 'programmee' ? 'selected' : ''; ?>>Programmée</option>
                            <option value="en_cours" <?php echo ($evaluation['status'] ?? '') === 'en_cours' ? 'selected' : ''; ?>>En cours</option>
                            <option value="terminee" <?php echo ($evaluation['status'] ?? '') === 'terminee' ? 'selected' : ''; ?>>Terminée</option>
                            <option value="annulee" <?php echo ($evaluation['status'] ?? '') === 'annulee' ? 'selected' : ''; ?>>Annulée</option>
                        </select>
                    </div>
                    
                    <div class="d-flex justify-content-between">
                        <a href="view.php?id=<?php echo $evaluation_id; ?>" class="btn btn-secondary">
                            <i class="fas fa-times me-1"></i>
                            Annuler
                        </a>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-1"></i>
                            Enregistrer les modifications
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <div class="col-lg-4">
        <!-- Informations actuelles -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-info-circle me-2"></i>
                    Informations actuelles
                </h5>
            </div>
            <div class="card-body">
                <table class="table table-borderless table-sm">
                    <tr>
                        <td><strong>Nom :</strong></td>
                        <td><?php echo htmlspecialchars($evaluation['nom']); ?></td>
                    </tr>
                    <tr>
                        <td><strong>Type :</strong></td>
                        <td>
                            <span class="badge bg-primary">
                                <?php echo ucfirst($evaluation['type_evaluation']); ?>
                            </span>
                        </td>
                    </tr>
                    <tr>
                        <td><strong>Classe :</strong></td>
                        <td><?php echo htmlspecialchars($evaluation['classe_nom']); ?></td>
                    </tr>
                    <tr>
                        <td><strong>Matière :</strong></td>
                        <td><?php echo htmlspecialchars($evaluation['matiere_nom']); ?></td>
                    </tr>
                    <tr>
                        <td><strong>Enseignant :</strong></td>
                        <td><?php echo htmlspecialchars($evaluation['enseignant_nom']); ?></td>
                    </tr>
                    <tr>
                        <td><strong>Date :</strong></td>
                        <td><?php echo date('d/m/Y', strtotime($evaluation['date_evaluation'])); ?></td>
                    </tr>
                    <?php if ($evaluation['status']): ?>
                    <tr>
                        <td><strong>Statut :</strong></td>
                        <td>
                            <?php
                            $status_colors = [
                                'programmee' => 'warning',
                                'en_cours' => 'info',
                                'terminee' => 'success',
                                'annulee' => 'danger'
                            ];
                            $color = $status_colors[$evaluation['status']] ?? 'secondary';
                            ?>
                            <span class="badge bg-<?php echo $color; ?>">
                                <?php echo ucfirst(str_replace('_', ' ', $evaluation['status'])); ?>
                            </span>
                        </td>
                    </tr>
                    <?php endif; ?>
                </table>
            </div>
        </div>
        
        <!-- Statistiques -->
        <?php if ($notes_count > 0): ?>
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-chart-bar me-2"></i>
                    Notes saisies
                </h5>
            </div>
            <div class="card-body text-center">
                <h3 class="text-primary"><?php echo $notes_count; ?></h3>
                <p class="mb-0">note(s) saisie(s)</p>
                <div class="mt-3">
                    <a href="../notes/entry.php?evaluation_id=<?php echo $evaluation_id; ?>" class="btn btn-sm btn-outline-success">
                        <i class="fas fa-edit me-1"></i>
                        Gérer les notes
                    </a>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Actions rapides -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-tools me-2"></i>
                    Actions rapides
                </h5>
            </div>
            <div class="card-body">
                <div class="d-grid gap-2">
                    <a href="view.php?id=<?php echo $evaluation_id; ?>" class="btn btn-outline-info btn-sm">
                        <i class="fas fa-eye me-1"></i>
                        Voir les détails complets
                    </a>
                    
                    <a href="../notes/entry.php?evaluation_id=<?php echo $evaluation_id; ?>" class="btn btn-outline-success btn-sm">
                        <i class="fas fa-edit me-1"></i>
                        Saisir/Modifier les notes
                    </a>
                    
                    <hr>
                    
                    <a href="delete.php?id=<?php echo $evaluation_id; ?>" 
                       class="btn btn-outline-danger btn-sm btn-delete"
                       data-name="<?php echo htmlspecialchars($evaluation['nom']); ?>"
                       data-notes="<?php echo $notes_count; ?>">
                        <i class="fas fa-trash me-1"></i>
                        Supprimer cette évaluation
                    </a>
                </div>
            </div>
        </div>
        
        <!-- Informations système -->
        <div class="card mt-3">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-info-circle me-2"></i>
                    Informations système
                </h5>
            </div>
            <div class="card-body">
                <small class="text-muted">
                    <strong>Créée le :</strong> 
                    <?php echo $evaluation['created_at'] ? date('d/m/Y à H:i', strtotime($evaluation['created_at'])) : 'Non disponible'; ?>
                </small>
                <?php if ($evaluation['updated_at']): ?>
                    <br>
                    <small class="text-muted">
                        <strong>Modifiée le :</strong> 
                        <?php echo date('d/m/Y à H:i', strtotime($evaluation['updated_at'])); ?>
                    </small>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

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

// Calcul automatique de la durée
document.addEventListener('DOMContentLoaded', function() {
    const heureDebut = document.getElementById('heure_debut');
    const heureFin = document.getElementById('heure_fin');
    const dureeMinutes = document.getElementById('duree_minutes');
    
    function calculateDuration() {
        if (heureDebut.value && heureFin.value) {
            const debut = new Date('2000-01-01 ' + heureDebut.value);
            const fin = new Date('2000-01-01 ' + heureFin.value);
            
            if (fin > debut) {
                const duree = (fin - debut) / (1000 * 60); // en minutes
                dureeMinutes.value = duree;
            }
        }
    }
    
    heureDebut.addEventListener('change', calculateDuration);
    heureFin.addEventListener('change', calculateDuration);
    
    // Validation des heures
    function validateHours() {
        if (heureDebut.value && heureFin.value) {
            if (heureDebut.value >= heureFin.value) {
                heureFin.setCustomValidity('L\'heure de fin doit être postérieure à l\'heure de début');
            } else {
                heureFin.setCustomValidity('');
            }
        }
    }
    
    heureDebut.addEventListener('change', validateHours);
    heureFin.addEventListener('change', validateHours);
});

// Confirmation de suppression
document.addEventListener('DOMContentLoaded', function() {
    const deleteButtons = document.querySelectorAll('.btn-delete');
    deleteButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            const name = this.getAttribute('data-name');
            const notesCount = this.getAttribute('data-notes');
            
            let message = `Êtes-vous sûr de vouloir supprimer l'évaluation "${name}" ?`;
            if (notesCount > 0) {
                message += `\n\nATTENTION: Cette évaluation a ${notesCount} note(s) saisie(s) qui seront également supprimées.`;
            }
            message += '\n\nCette action ne peut pas être annulée.';
            
            if (confirm(message)) {
                window.location.href = this.href;
            }
        });
    });
});
</script>

<?php include '../../../includes/footer.php'; ?>
