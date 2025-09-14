<?php
/**
 * Module Discipline - Ajouter une récompense
 * Application de gestion scolaire - République Démocratique du Congo
 */

require_once '../../../config/config.php';
require_once '../../../config/database.php';
require_once '../../../includes/functions.php';
require_once '../../../includes/permissions-pages.php';

// Vérifier l'authentification et les permissions
requireLogin();
requirePagePermissionFromDB('discipline', 'recompenses/add', 'create', '../../../dashboard.php');

$errors = [];
$success = false;

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $eleve_id = intval($_POST['eleve_id'] ?? 0);
    $classe_id = intval($_POST['classe_id'] ?? 0);
    $type_recompense = trim($_POST['type_recompense'] ?? '');
    $motif = trim($_POST['motif'] ?? '');
    $date_recompense = $_POST['date_recompense'] ?? '';
    $valeur_points = intval($_POST['valeur_points'] ?? 0);
    $description = trim($_POST['description'] ?? '');
    $parent_informe = isset($_POST['parent_informe']) ? 1 : 0;
    
    // Validation
    if ($eleve_id <= 0) {
        $errors[] = 'Veuillez sélectionner un élève.';
    }
    
    if (empty($type_recompense)) {
        $errors[] = 'Veuillez sélectionner un type de récompense.';
    }
    
    if (empty($motif)) {
        $errors[] = 'Le motif de la récompense est obligatoire.';
    }
    
    if (empty($date_recompense)) {
        $errors[] = 'La date de la récompense est obligatoire.';
    }
    
    // Si pas d'erreurs, enregistrer la récompense
    if (empty($errors)) {
        try {
            $database->beginTransaction();
            
            // Vérifier si la table recompenses existe
            $tables = $database->query("SHOW TABLES LIKE 'recompenses'")->fetch();
            
            if ($tables) {
                // Table recompenses existe
                $sql = "INSERT INTO recompenses (eleve_id, classe_id, type_recompense, motif, date_recompense, attribuee_par, valeur_points, description, parent_informe, created_at) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
                $params = [$eleve_id, $classe_id ?: null, $type_recompense, $motif, $date_recompense, $_SESSION['user_id'], $valeur_points, $description, $parent_informe];
            } else {
                // Créer une table temporaire ou utiliser une table générique
                $database->execute("
                    CREATE TABLE IF NOT EXISTS recompenses (
                        id INT PRIMARY KEY AUTO_INCREMENT,
                        eleve_id INT NOT NULL,
                        classe_id INT,
                        type_recompense VARCHAR(100) NOT NULL,
                        motif TEXT NOT NULL,
                        date_recompense DATE NOT NULL,
                        attribuee_par INT NOT NULL,
                        valeur_points INT DEFAULT 0,
                        description TEXT,
                        parent_informe TINYINT(1) DEFAULT 0,
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                    )
                ");
                
                $sql = "INSERT INTO recompenses (eleve_id, classe_id, type_recompense, motif, date_recompense, attribuee_par, valeur_points, description, parent_informe, created_at) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
                $params = [$eleve_id, $classe_id ?: null, $type_recompense, $motif, $date_recompense, $_SESSION['user_id'], $valeur_points, $description, $parent_informe];
            }
            
            $database->execute($sql, $params);
            
            $database->commit();
            
            showMessage('success', 'Récompense attribuée avec succès !');
            redirectTo('../index.php');
            
        } catch (Exception $e) {
            $database->rollback();
            $errors[] = 'Erreur lors de l\'enregistrement : ' . $e->getMessage();
        }
    }
}

// Récupérer les élèves actifs avec leurs classes
try {
    $eleves = $database->query(
        "SELECT e.id, e.numero_matricule, e.nom, e.prenom, c.nom as classe_nom, c.id as classe_id
         FROM eleves e
         LEFT JOIN inscriptions i ON e.id = i.eleve_id AND i.status = 'inscrit'
         LEFT JOIN classes c ON i.classe_id = c.id
         WHERE e.status = 'actif'
         ORDER BY e.nom, e.prenom"
    )->fetchAll();
} catch (Exception $e) {
    $eleves = [];
}

// Récupérer les classes
try {
    $classes = $database->query(
        "SELECT id, nom, niveau FROM classes ORDER BY niveau, nom"
    )->fetchAll();
} catch (Exception $e) {
    $classes = [];
}

$page_title = "Attribuer une récompense";
include '../../../includes/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">
        <i class="fas fa-award me-2 text-success"></i>
        Attribuer une récompense
    </h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <a href="../index.php" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-left me-1"></i>
            Retour au tableau de bord
        </a>
    </div>
</div>

<?php if (!empty($errors)): ?>
    <div class="alert alert-danger">
        <h6><i class="fas fa-exclamation-circle me-1"></i> Erreurs détectées :</h6>
        <ul class="mb-0">
            <?php foreach ($errors as $error): ?>
                <li><?php echo htmlspecialchars($error); ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<div class="row">
    <div class="col-lg-8">
        <div class="card shadow-sm">
            <div class="card-header bg-light">
                <h5 class="mb-0">
                    <i class="fas fa-star me-2"></i>
                    Informations sur la récompense
                </h5>
            </div>
            <div class="card-body">
                <form method="POST" class="needs-validation" novalidate>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="eleve_id" class="form-label">Élève à récompenser <span class="text-danger">*</span></label>
                            <select class="form-select" id="eleve_id" name="eleve_id" required>
                                <option value="">Sélectionner un élève...</option>
                                <?php foreach ($eleves as $eleve): ?>
                                    <option value="<?php echo $eleve['id']; ?>" 
                                            data-classe="<?php echo $eleve['classe_id']; ?>"
                                            <?php echo (isset($_POST['eleve_id']) && $_POST['eleve_id'] == $eleve['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($eleve['numero_matricule'] . ' - ' . $eleve['nom'] . ' ' . $eleve['prenom']); ?>
                                        <?php if ($eleve['classe_nom']): ?>
                                            (<?php echo htmlspecialchars($eleve['classe_nom']); ?>)
                                        <?php endif; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="invalid-feedback">
                                Veuillez sélectionner un élève.
                            </div>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="classe_id" class="form-label">Classe</label>
                            <select class="form-select" id="classe_id" name="classe_id">
                                <option value="">Classe automatique</option>
                                <?php foreach ($classes as $classe): ?>
                                    <option value="<?php echo $classe['id']; ?>"
                                            <?php echo (isset($_POST['classe_id']) && $_POST['classe_id'] == $classe['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($classe['nom'] . ' (' . ucfirst($classe['niveau']) . ')'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="form-text">
                                La classe sera automatiquement définie selon l'élève sélectionné
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="type_recompense" class="form-label">Type de récompense <span class="text-danger">*</span></label>
                            <select class="form-select" id="type_recompense" name="type_recompense" required>
                                <option value="">Sélectionner un type...</option>
                                <option value="felicitations" <?php echo (($_POST['type_recompense'] ?? '') === 'felicitations') ? 'selected' : ''; ?>>
                                    🎉 Félicitations
                                </option>
                                <option value="encouragements" <?php echo (($_POST['type_recompense'] ?? '') === 'encouragements') ? 'selected' : ''; ?>>
                                    👏 Encouragements
                                </option>
                                <option value="mention_honneur" <?php echo (($_POST['type_recompense'] ?? '') === 'mention_honneur') ? 'selected' : ''; ?>>
                                    🏆 Mention d'honneur
                                </option>
                                <option value="prix_excellence" <?php echo (($_POST['type_recompense'] ?? '') === 'prix_excellence') ? 'selected' : ''; ?>>
                                    🥇 Prix d'excellence
                                </option>
                                <option value="bon_comportement" <?php echo (($_POST['type_recompense'] ?? '') === 'bon_comportement') ? 'selected' : ''; ?>>
                                    😊 Bon comportement
                                </option>
                                <option value="merite_scolaire" <?php echo (($_POST['type_recompense'] ?? '') === 'merite_scolaire') ? 'selected' : ''; ?>>
                                    📚 Mérite scolaire
                                </option>
                                <option value="esprit_equipe" <?php echo (($_POST['type_recompense'] ?? '') === 'esprit_equipe') ? 'selected' : ''; ?>>
                                    🤝 Esprit d'équipe
                                </option>
                                <option value="progres_remarquable" <?php echo (($_POST['type_recompense'] ?? '') === 'progres_remarquable') ? 'selected' : ''; ?>>
                                    📈 Progrès remarquable
                                </option>
                                <option value="autre" <?php echo (($_POST['type_recompense'] ?? '') === 'autre') ? 'selected' : ''; ?>>
                                    ✨ Autre
                                </option>
                            </select>
                            <div class="invalid-feedback">
                                Veuillez sélectionner un type de récompense.
                            </div>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="date_recompense" class="form-label">Date de la récompense <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" id="date_recompense" name="date_recompense" 
                                   value="<?php echo $_POST['date_recompense'] ?? date('Y-m-d'); ?>" required>
                            <div class="invalid-feedback">
                                Veuillez indiquer la date de la récompense.
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="motif" class="form-label">Motif de la récompense <span class="text-danger">*</span></label>
                        <textarea class="form-control" id="motif" name="motif" rows="3" required
                                  placeholder="Décrivez pourquoi cet élève mérite cette récompense..."><?php echo htmlspecialchars($_POST['motif'] ?? ''); ?></textarea>
                        <div class="invalid-feedback">
                            Veuillez préciser le motif de la récompense.
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="description" class="form-label">Description détaillée</label>
                        <textarea class="form-control" id="description" name="description" rows="2"
                                  placeholder="Détails supplémentaires sur la récompense..."><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
                        <div class="form-text">
                            Informations complémentaires sur les circonstances ou modalités
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="valeur_points" class="form-label">Valeur en points</label>
                            <input type="number" class="form-control" id="valeur_points" name="valeur_points" 
                                   min="0" max="100" value="<?php echo $_POST['valeur_points'] ?? '10'; ?>">
                            <div class="form-text">
                                Points à ajouter au dossier de l'élève (0-100)
                            </div>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <div class="form-check mt-4">
                                <input class="form-check-input" type="checkbox" id="parent_informe" name="parent_informe" value="1"
                                       <?php echo (isset($_POST['parent_informe'])) ? 'checked' : 'checked'; ?>>
                                <label class="form-check-label" for="parent_informe">
                                    Informer les parents de cette récompense
                                </label>
                            </div>
                        </div>
                    </div>
                    
                    <div class="d-flex justify-content-between">
                        <a href="../index.php" class="btn btn-secondary">
                            <i class="fas fa-times me-1"></i>
                            Annuler
                        </a>
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-award me-1"></i>
                            Attribuer la récompense
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <div class="col-lg-4">
        <div class="card shadow-sm">
            <div class="card-header bg-success text-white">
                <h6 class="mb-0">
                    <i class="fas fa-lightbulb me-2"></i>
                    Guide des récompenses
                </h6>
            </div>
            <div class="card-body">
                <h6 class="text-primary">Types de récompenses :</h6>
                <ul class="list-unstyled small">
                    <li class="mb-2">
                        <strong>🎉 Félicitations :</strong> Résultats exceptionnels
                    </li>
                    <li class="mb-2">
                        <strong>👏 Encouragements :</strong> Efforts soutenus
                    </li>
                    <li class="mb-2">
                        <strong>🏆 Mention d'honneur :</strong> Excellence académique
                    </li>
                    <li class="mb-2">
                        <strong>🥇 Prix d'excellence :</strong> Performance remarquable
                    </li>
                    <li class="mb-2">
                        <strong>😊 Bon comportement :</strong> Attitude exemplaire
                    </li>
                    <li class="mb-2">
                        <strong>📚 Mérite scolaire :</strong> Travail assidu
                    </li>
                    <li class="mb-2">
                        <strong>🤝 Esprit d'équipe :</strong> Collaboration positive
                    </li>
                    <li class="mb-2">
                        <strong>📈 Progrès remarquable :</strong> Amélioration notable
                    </li>
                </ul>
                
                <hr>
                
                <h6 class="text-primary">Conseils :</h6>
                <ul class="small">
                    <li>Soyez spécifique dans le motif</li>
                    <li>Valorisez les efforts autant que les résultats</li>
                    <li>Informez les parents des réussites</li>
                    <li>Encouragez la persévérance</li>
                </ul>
                
                <div class="mt-3 p-2 bg-light rounded">
                    <small class="text-muted">
                        <i class="fas fa-info-circle me-1"></i>
                        Les récompenses motivent et renforcent les comportements positifs.
                    </small>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Auto-sélection de la classe selon l'élève
document.getElementById('eleve_id').addEventListener('change', function() {
    const selectedOption = this.options[this.selectedIndex];
    const classeId = selectedOption.getAttribute('data-classe');
    
    if (classeId) {
        document.getElementById('classe_id').value = classeId;
    }
});

// Ajustement automatique des points selon le type de récompense
document.getElementById('type_recompense').addEventListener('change', function() {
    const pointsInput = document.getElementById('valeur_points');
    const pointsMap = {
        'felicitations': 20,
        'encouragements': 10,
        'mention_honneur': 30,
        'prix_excellence': 50,
        'bon_comportement': 15,
        'merite_scolaire': 25,
        'esprit_equipe': 15,
        'progres_remarquable': 20,
        'autre': 10
    };
    
    if (pointsMap[this.value]) {
        pointsInput.value = pointsMap[this.value];
    }
});

// Validation du formulaire
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
</script>

<?php include '../../../includes/footer.php'; ?>
