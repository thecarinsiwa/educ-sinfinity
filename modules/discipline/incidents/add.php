<?php
/**
 * Module Discipline - Ajouter un incident
 * Application de gestion scolaire - République Démocratique du Congo
 */

require_once '../../../config/config.php';
require_once '../../../config/database.php';
require_once '../../../includes/functions.php';

// Vérifier l'authentification et les permissions
requireLogin();
if (!checkPermission('discipline')) {
    showMessage('error', 'Accès refusé à cette page.');
    redirectTo('../../../index.php');
}

$errors = [];
$success = false;

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $eleve_id = intval($_POST['eleve_id'] ?? 0);
    $classe_id = intval($_POST['classe_id'] ?? 0);
    $date_incident = $_POST['date_incident'] ?? '';
    $heure_incident = $_POST['heure_incident'] ?? '';
    $lieu = trim($_POST['lieu'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $temoins = trim($_POST['temoins'] ?? '');
    $gravite = $_POST['gravite'] ?? 'moyenne';
    
    // Validation
    if ($eleve_id <= 0) {
        $errors[] = 'Veuillez sélectionner un élève.';
    }
    
    if (empty($date_incident)) {
        $errors[] = 'La date de l\'incident est obligatoire.';
    }
    
    if (empty($description)) {
        $errors[] = 'La description de l\'incident est obligatoire.';
    }
    
    if (!in_array($gravite, ['legere', 'moyenne', 'grave', 'tres_grave'])) {
        $errors[] = 'Niveau de gravité invalide.';
    }
    
    // Combiner date et heure
    $datetime_incident = $date_incident;
    if (!empty($heure_incident)) {
        $datetime_incident .= ' ' . $heure_incident;
    } else {
        $datetime_incident .= ' ' . date('H:i:s');
    }
    
    // Si pas d'erreurs, enregistrer l'incident
    if (empty($errors)) {
        try {
            $database->beginTransaction();
            
            // Vérifier si la table incidents existe avec la nouvelle structure
            $columns = $database->query("DESCRIBE incidents")->fetchAll();
            $column_names = array_column($columns, 'Field');
            
            if (in_array('rapporte_par', $column_names)) {
                // Nouvelle structure
                $sql = "INSERT INTO incidents (eleve_id, classe_id, rapporte_par, date_incident, lieu, description, temoins, gravite, status, created_at) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'nouveau', NOW())";
                $params = [$eleve_id, $classe_id ?: null, $_SESSION['user_id'], $datetime_incident, $lieu, $description, $temoins, $gravite];
            } else {
                // Structure basique
                $sql = "INSERT INTO incidents (eleve_id, classe_id, date_incident, lieu, description, temoins, gravite, status, created_at) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, 'nouveau', NOW())";
                $params = [$eleve_id, $classe_id ?: null, $datetime_incident, $lieu, $description, $temoins, $gravite];
            }
            
            $database->execute($sql, $params);
            
            $database->commit();
            
            showMessage('success', 'Incident signalé avec succès !');
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

$page_title = "Signaler un incident";
include '../../../includes/header.php';
?>

<style>
.eleve-result {
    transition: background-color 0.2s;
}

.eleve-result:hover,
.eleve-result.active {
    background-color: #f8f9fa;
}

.eleve-result:last-child {
    border-bottom: none !important;
}

#eleve_results {
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    border: 1px solid #dee2e6;
}

#eleve_results::-webkit-scrollbar {
    width: 6px;
}

#eleve_results::-webkit-scrollbar-track {
    background: #f1f1f1;
}

#eleve_results::-webkit-scrollbar-thumb {
    background: #c1c1c1;
    border-radius: 3px;
}

#eleve_results::-webkit-scrollbar-thumb:hover {
    background: #a8a8a8;
}
</style>
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">
        <i class="fas fa-exclamation-triangle me-2 text-warning"></i>
        Signaler un incident
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
                    <i class="fas fa-file-alt me-2"></i>
                    Informations sur l'incident
                </h5>
            </div>
            <div class="card-body">
                <form method="POST" class="needs-validation" novalidate>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="eleve_search" class="form-label">Rechercher un élève <span class="text-danger">*</span></label>
                            <div class="position-relative">
                                <input type="text" 
                                       class="form-control" 
                                       id="eleve_search" 
                                       placeholder="Tapez le nom, prénom ou matricule..."
                                       autocomplete="off"
                                       required>
                                <input type="hidden" id="eleve_id" name="eleve_id" value="<?php echo htmlspecialchars($_POST['eleve_id'] ?? ''); ?>">
                                <div id="eleve_results" class="position-absolute w-100 bg-white border rounded shadow-sm" style="z-index: 1000; max-height: 200px; overflow-y: auto; display: none; top: 100%;"></div>
                            </div>
                            <div class="form-text">Commencez à taper pour rechercher un élève</div>
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
                            <label for="date_incident" class="form-label">Date de l'incident <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" id="date_incident" name="date_incident" 
                                   value="<?php echo $_POST['date_incident'] ?? date('Y-m-d'); ?>" required>
                            <div class="invalid-feedback">
                                Veuillez indiquer la date de l'incident.
                            </div>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="heure_incident" class="form-label">Heure de l'incident</label>
                            <input type="time" class="form-control" id="heure_incident" name="heure_incident" 
                                   value="<?php echo $_POST['heure_incident'] ?? date('H:i'); ?>">
                            <div class="form-text">
                                Heure approximative de l'incident
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="lieu" class="form-label">Lieu de l'incident</label>
                        <input type="text" class="form-control" id="lieu" name="lieu" 
                               value="<?php echo htmlspecialchars($_POST['lieu'] ?? ''); ?>"
                               placeholder="Ex: Salle de classe, Cour de récréation, Couloir...">
                        <div class="form-text">
                            Précisez où s'est déroulé l'incident
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="description" class="form-label">Description de l'incident <span class="text-danger">*</span></label>
                        <textarea class="form-control" id="description" name="description" rows="4" required
                                  placeholder="Décrivez précisément ce qui s'est passé..."><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
                        <div class="invalid-feedback">
                            Veuillez décrire l'incident.
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="temoins" class="form-label">Témoins</label>
                        <textarea class="form-control" id="temoins" name="temoins" rows="2"
                                  placeholder="Noms des témoins présents lors de l'incident..."><?php echo htmlspecialchars($_POST['temoins'] ?? ''); ?></textarea>
                        <div class="form-text">
                            Listez les personnes qui ont assisté à l'incident
                        </div>
                    </div>
                    
                    <div class="mb-4">
                        <label for="gravite" class="form-label">Niveau de gravité <span class="text-danger">*</span></label>
                        <select class="form-select" id="gravite" name="gravite" required>
                            <option value="legere" <?php echo (($_POST['gravite'] ?? 'moyenne') === 'legere') ? 'selected' : ''; ?>>
                                🟢 Légère - Incident mineur
                            </option>
                            <option value="moyenne" <?php echo (($_POST['gravite'] ?? 'moyenne') === 'moyenne') ? 'selected' : ''; ?>>
                                🟡 Moyenne - Incident modéré
                            </option>
                            <option value="grave" <?php echo (($_POST['gravite'] ?? 'moyenne') === 'grave') ? 'selected' : ''; ?>>
                                🟠 Grave - Incident sérieux
                            </option>
                            <option value="tres_grave" <?php echo (($_POST['gravite'] ?? 'moyenne') === 'tres_grave') ? 'selected' : ''; ?>>
                                🔴 Très grave - Incident majeur
                            </option>
                        </select>
                        <div class="invalid-feedback">
                            Veuillez sélectionner le niveau de gravité.
                        </div>
                    </div>
                    
                    <div class="d-flex justify-content-between">
                        <a href="../index.php" class="btn btn-secondary">
                            <i class="fas fa-times me-1"></i>
                            Annuler
                        </a>
                        <button type="submit" class="btn btn-warning">
                            <i class="fas fa-exclamation-triangle me-1"></i>
                            Signaler l'incident
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <div class="col-lg-4">
        <div class="card shadow-sm">
            <div class="card-header bg-info text-white">
                <h6 class="mb-0">
                    <i class="fas fa-info-circle me-2"></i>
                    Guide de signalement
                </h6>
            </div>
            <div class="card-body">
                <h6 class="text-primary">Niveaux de gravité :</h6>
                <ul class="list-unstyled">
                    <li class="mb-2">
                        <span class="badge bg-success me-2">Légère</span>
                        <small>Bavardage, retard, oubli de matériel</small>
                    </li>
                    <li class="mb-2">
                        <span class="badge bg-warning me-2">Moyenne</span>
                        <small>Perturbation de cours, insolence légère</small>
                    </li>
                    <li class="mb-2">
                        <span class="badge bg-danger me-2">Grave</span>
                        <small>Bagarre, insultes, dégradation</small>
                    </li>
                    <li class="mb-2">
                        <span class="badge bg-dark me-2">Très grave</span>
                        <small>Violence, vol, comportement dangereux</small>
                    </li>
                </ul>
                
                <hr>
                
                <h6 class="text-primary">Conseils :</h6>
                <ul class="small">
                    <li>Soyez précis dans la description</li>
                    <li>Mentionnez l'heure et le lieu</li>
                    <li>Listez tous les témoins</li>
                    <li>Restez objectif et factuel</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<script>
// Recherche d'élèves avec autocomplétion
let searchTimeout;
const eleveSearch = document.getElementById('eleve_search');
const eleveResults = document.getElementById('eleve_results');
const eleveIdInput = document.getElementById('eleve_id');

// Fonction de recherche d'élèves
function searchEleves(query) {
    if (query.length < 2) {
        eleveResults.style.display = 'none';
        return;
    }
    
    fetch('search_eleves.php?q=' + encodeURIComponent(query))
        .then(response => response.json())
        .then(data => {
            if (data.length > 0) {
                displayResults(data);
            } else {
                eleveResults.innerHTML = '<div class="p-3 text-muted">Aucun élève trouvé</div>';
                eleveResults.style.display = 'block';
            }
        })
        .catch(error => {
            console.error('Erreur de recherche:', error);
            eleveResults.innerHTML = '<div class="p-3 text-danger">Erreur lors de la recherche</div>';
            eleveResults.style.display = 'block';
        });
}

// Affichage des résultats
function displayResults(eleves) {
    eleveResults.innerHTML = '';
    
    eleves.forEach(eleve => {
        const div = document.createElement('div');
        div.className = 'p-2 border-bottom eleve-result';
        div.style.cursor = 'pointer';
        div.innerHTML = `
            <div class="fw-bold">${eleve.nom} ${eleve.prenom}</div>
            <div class="small text-muted">
                Matricule: ${eleve.numero_matricule} | Classe: ${eleve.classe_nom}
            </div>
        `;
        
        div.addEventListener('click', () => selectEleve(eleve));
        eleveResults.appendChild(div);
    });
    
    eleveResults.style.display = 'block';
}

// Sélection d'un élève
function selectEleve(eleve) {
    eleveSearch.value = `${eleve.nom} ${eleve.prenom} (${eleve.numero_matricule})`;
    eleveIdInput.value = eleve.id;
    eleveResults.style.display = 'none';
    
    // Auto-sélection de la classe
    if (eleve.classe_id) {
        document.getElementById('classe_id').value = eleve.classe_id;
    }
}

// Événements de recherche
eleveSearch.addEventListener('input', function() {
    clearTimeout(searchTimeout);
    const query = this.value.trim();
    
    if (query.length >= 2) {
        searchTimeout = setTimeout(() => {
            searchEleves(query);
        }, 300);
    } else {
        eleveResults.style.display = 'none';
        eleveIdInput.value = '';
    }
});

// Masquer les résultats quand on clique ailleurs
document.addEventListener('click', function(e) {
    if (!eleveSearch.contains(e.target) && !eleveResults.contains(e.target)) {
        eleveResults.style.display = 'none';
    }
});

// Navigation au clavier dans les résultats
eleveSearch.addEventListener('keydown', function(e) {
    const results = eleveResults.querySelectorAll('.eleve-result');
    const currentIndex = Array.from(results).findIndex(el => el.classList.contains('active'));
    
    if (e.key === 'ArrowDown') {
        e.preventDefault();
        if (currentIndex < results.length - 1) {
            if (currentIndex >= 0) results[currentIndex].classList.remove('active');
            results[currentIndex + 1].classList.add('active');
        }
    } else if (e.key === 'ArrowUp') {
        e.preventDefault();
        if (currentIndex > 0) {
            results[currentIndex].classList.remove('active');
            results[currentIndex - 1].classList.add('active');
        }
    } else if (e.key === 'Enter') {
        e.preventDefault();
        const activeResult = eleveResults.querySelector('.eleve-result.active');
        if (activeResult) {
            activeResult.click();
        }
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
