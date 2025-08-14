<?php
/**
 * Module Discipline - Ajouter une sanction
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

// Récupérer les paramètres de l'URL pour pré-remplir le formulaire
$url_incident_id = intval($_GET['incident_id'] ?? 0);
$url_eleve_id = intval($_GET['eleve_id'] ?? 0);

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $eleve_id = intval($_POST['eleve_id'] ?? 0);
    $incident_id = intval($_POST['incident_id'] ?? 0);
    $type_sanction_id = intval($_POST['type_sanction_id'] ?? 0);
    $type_sanction = $_POST['type_sanction'] ?? '';
    $date_sanction = $_POST['date_sanction'] ?? '';
    $date_debut = $_POST['date_debut'] ?? '';
    $date_fin = $_POST['date_fin'] ?? '';
    $duree_jours = intval($_POST['duree_jours'] ?? 0);
    $description = trim($_POST['description'] ?? '');
    $notes = trim($_POST['notes'] ?? '');
    $parent_informe = isset($_POST['parent_informe']) ? 1 : 0;
    
    // Validation
    if ($eleve_id <= 0) {
        $errors[] = 'Veuillez sélectionner un élève.';
    }
    
    if (empty($date_sanction)) {
        $errors[] = 'La date de la sanction est obligatoire.';
    }
    
    // Vérifier la structure de la table sanctions
    $columns = $database->query("DESCRIBE sanctions")->fetchAll();
    $column_names = array_column($columns, 'Field');
    $has_type_sanction_id = in_array('type_sanction_id', $column_names);
    
    if ($has_type_sanction_id && $type_sanction_id <= 0) {
        $errors[] = 'Veuillez sélectionner un type de sanction.';
    } elseif (!$has_type_sanction_id && empty($type_sanction)) {
        $errors[] = 'Veuillez sélectionner un type de sanction.';
    }
    
    if (empty($description)) {
        $errors[] = 'La description de la sanction est obligatoire.';
    }
    
    // Validation des dates
    if (!empty($date_debut) && !empty($date_fin)) {
        if (strtotime($date_fin) < strtotime($date_debut)) {
            $errors[] = 'La date de fin doit être postérieure à la date de début.';
        }
    }
    
    // Si pas d'erreurs, enregistrer la sanction
    if (empty($errors)) {
        try {
            $database->beginTransaction();
            
            if ($has_type_sanction_id) {
                // Nouvelle structure avec types_sanctions
                $sql = "INSERT INTO sanctions (incident_id, eleve_id, type_sanction_id, date_sanction, date_debut, date_fin, duree_jours, description, prononcee_par, status, notes, parent_informe, created_at) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'active', ?, ?, NOW())";
                $params = [
                    $incident_id ?: null, 
                    $eleve_id, 
                    $type_sanction_id, 
                    $date_sanction, 
                    $date_debut ?: null, 
                    $date_fin ?: null, 
                    $duree_jours ?: null, 
                    $description, 
                    $_SESSION['user_id'], 
                    $notes, 
                    $parent_informe
                ];
            } else {
                // Ancienne structure
                $sql = "INSERT INTO sanctions (eleve_id, type_sanction, motif, date_sanction, duree_jours, enseignant_id, status, observation) 
                        VALUES (?, ?, ?, ?, ?, ?, 'active', ?)";
                $params = [$eleve_id, $type_sanction, $description, $date_sanction, $duree_jours ?: null, $_SESSION['user_id'], $notes];
            }
            
            $database->execute($sql, $params);
            
            $database->commit();
            
            showMessage('success', 'Sanction prononcée avec succès !');
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
        "SELECT e.id, e.numero_matricule, e.nom, e.prenom, c.nom as classe_nom
         FROM eleves e
         LEFT JOIN inscriptions i ON e.id = i.eleve_id AND i.status = 'inscrit'
         LEFT JOIN classes c ON i.classe_id = c.id
         WHERE e.status = 'actif'
         ORDER BY e.nom, e.prenom"
    )->fetchAll();
} catch (Exception $e) {
    $eleves = [];
}

// Récupérer les incidents non résolus
try {
    $incidents = $database->query(
        "SELECT i.id, i.description, i.date_incident, i.gravite,
                CONCAT(e.nom, ' ', e.prenom) as eleve_nom
         FROM incidents i
         JOIN eleves e ON i.eleve_id = e.id
         WHERE i.status IN ('nouveau', 'en_cours')
         ORDER BY i.date_incident DESC"
    )->fetchAll();
} catch (Exception $e) {
    $incidents = [];
}

// Récupérer les types de sanctions si la table existe
$types_sanctions = [];
try {
    $types_sanctions_exists = $database->query("SHOW TABLES LIKE 'types_sanctions'")->fetch();
    if ($types_sanctions_exists) {
        $types_sanctions = $database->query(
            "SELECT id, nom, description, gravite, duree_defaut, couleur
             FROM types_sanctions 
             WHERE active = 1
             ORDER BY gravite, nom"
        )->fetchAll();
    }
} catch (Exception $e) {
    $types_sanctions = [];
}

$page_title = "Prononcer une sanction";
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
        <i class="fas fa-gavel me-2 text-danger"></i>
        Prononcer une sanction
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
                    <i class="fas fa-balance-scale me-2"></i>
                    Informations sur la sanction
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
                                <input type="hidden" id="eleve_id" name="eleve_id" value="<?php 
                                    if (isset($_POST['eleve_id'])) {
                                        echo htmlspecialchars($_POST['eleve_id']);
                                    } elseif ($url_eleve_id) {
                                        echo htmlspecialchars($url_eleve_id);
                                    }
                                ?>">
                                <div id="eleve_results" class="position-absolute w-100 bg-white border rounded shadow-sm" style="z-index: 1000; max-height: 200px; overflow-y: auto; display: none; top: 100%;"></div>
                            </div>
                            <div class="form-text">Commencez à taper pour rechercher un élève</div>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label for="incident_id" class="form-label">Incident lié (optionnel)</label>
                            <select class="form-select" id="incident_id" name="incident_id">
                                <option value="">Aucun incident spécifique</option>
                                <?php foreach ($incidents as $incident): ?>
                                    <option value="<?php echo $incident['id']; ?>"
                                            <?php
                                            $selected = false;
                                            if (isset($_POST['incident_id']) && $_POST['incident_id'] == $incident['id']) {
                                                $selected = true;
                                            } elseif (!isset($_POST['incident_id']) && $url_incident_id == $incident['id']) {
                                                $selected = true;
                                            }
                                            echo $selected ? 'selected' : '';
                                            ?>>
                                        <?php echo htmlspecialchars(substr($incident['description'], 0, 50) . '... - ' . $incident['eleve_nom']); ?>
                                        (<?php echo formatDate($incident['date_incident']); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="form-text">
                                Sélectionnez l'incident qui a motivé cette sanction
                            </div>
                        </div>
                    </div>
                    
                    <?php if (!empty($types_sanctions)): ?>
                    <div class="mb-3">
                        <label for="type_sanction_id" class="form-label">Type de sanction <span class="text-danger">*</span></label>
                        <select class="form-select" id="type_sanction_id" name="type_sanction_id" required>
                            <option value="">Sélectionner un type de sanction...</option>
                            <?php foreach ($types_sanctions as $type): ?>
                                <option value="<?php echo $type['id']; ?>" 
                                        data-duree="<?php echo $type['duree_defaut']; ?>"
                                        data-couleur="<?php echo $type['couleur']; ?>"
                                        <?php echo (isset($_POST['type_sanction_id']) && $_POST['type_sanction_id'] == $type['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($type['nom']); ?>
                                    <?php if ($type['duree_defaut']): ?>
                                        (<?php echo $type['duree_defaut']; ?> jour<?php echo $type['duree_defaut'] > 1 ? 's' : ''; ?>)
                                    <?php endif; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="invalid-feedback">
                            Veuillez sélectionner un type de sanction.
                        </div>
                    </div>
                    <?php else: ?>
                    <div class="mb-3">
                        <label for="type_sanction" class="form-label">Type de sanction <span class="text-danger">*</span></label>
                        <select class="form-select" id="type_sanction" name="type_sanction" required>
                            <option value="">Sélectionner un type de sanction...</option>
                            <option value="avertissement" <?php echo (($_POST['type_sanction'] ?? '') === 'avertissement') ? 'selected' : ''; ?>>
                                Avertissement
                            </option>
                            <option value="blame" <?php echo (($_POST['type_sanction'] ?? '') === 'blame') ? 'selected' : ''; ?>>
                                Blâme
                            </option>
                            <option value="exclusion_temporaire" <?php echo (($_POST['type_sanction'] ?? '') === 'exclusion_temporaire') ? 'selected' : ''; ?>>
                                Exclusion temporaire
                            </option>
                            <option value="exclusion_definitive" <?php echo (($_POST['type_sanction'] ?? '') === 'exclusion_definitive') ? 'selected' : ''; ?>>
                                Exclusion définitive
                            </option>
                            <option value="travaux_supplementaires" <?php echo (($_POST['type_sanction'] ?? '') === 'travaux_supplementaires') ? 'selected' : ''; ?>>
                                Travaux supplémentaires
                            </option>
                        </select>
                        <div class="invalid-feedback">
                            Veuillez sélectionner un type de sanction.
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label for="date_sanction" class="form-label">Date de la sanction <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" id="date_sanction" name="date_sanction" 
                                   value="<?php echo $_POST['date_sanction'] ?? date('Y-m-d'); ?>" required>
                            <div class="invalid-feedback">
                                Veuillez indiquer la date de la sanction.
                            </div>
                        </div>
                        
                        <div class="col-md-4 mb-3">
                            <label for="date_debut" class="form-label">Date de début</label>
                            <input type="date" class="form-control" id="date_debut" name="date_debut" 
                                   value="<?php echo $_POST['date_debut'] ?? ''; ?>">
                            <div class="form-text">
                                Pour les sanctions temporaires
                            </div>
                        </div>
                        
                        <div class="col-md-4 mb-3">
                            <label for="date_fin" class="form-label">Date de fin</label>
                            <input type="date" class="form-control" id="date_fin" name="date_fin" 
                                   value="<?php echo $_POST['date_fin'] ?? ''; ?>">
                            <div class="form-text">
                                Calculée automatiquement si durée spécifiée
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="duree_jours" class="form-label">Durée (en jours)</label>
                        <input type="number" class="form-control" id="duree_jours" name="duree_jours" min="0" max="365"
                               value="<?php echo $_POST['duree_jours'] ?? ''; ?>">
                        <div class="form-text">
                            Pour les exclusions temporaires ou travaux supplémentaires
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="description" class="form-label">Description de la sanction <span class="text-danger">*</span></label>
                        <textarea class="form-control" id="description" name="description" rows="3" required
                                  placeholder="Décrivez la sanction et ses modalités..."><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
                        <div class="invalid-feedback">
                            Veuillez décrire la sanction.
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="notes" class="form-label">Notes internes</label>
                        <textarea class="form-control" id="notes" name="notes" rows="2"
                                  placeholder="Notes pour le dossier disciplinaire..."><?php echo htmlspecialchars($_POST['notes'] ?? ''); ?></textarea>
                        <div class="form-text">
                            Ces notes ne seront visibles que par le personnel
                        </div>
                    </div>
                    
                    <div class="mb-4">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="parent_informe" name="parent_informe" value="1"
                                   <?php echo (isset($_POST['parent_informe'])) ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="parent_informe">
                                Parents informés de cette sanction
                            </label>
                        </div>
                    </div>
                    
                    <div class="d-flex justify-content-between">
                        <a href="../index.php" class="btn btn-secondary">
                            <i class="fas fa-times me-1"></i>
                            Annuler
                        </a>
                        <button type="submit" class="btn btn-danger">
                            <i class="fas fa-gavel me-1"></i>
                            Prononcer la sanction
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <div class="col-lg-4">
        <div class="card shadow-sm">
            <div class="card-header bg-warning text-dark">
                <h6 class="mb-0">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    Guide des sanctions
                </h6>
            </div>
            <div class="card-body">
                <h6 class="text-primary">Types de sanctions :</h6>
                <ul class="list-unstyled small">
                    <li class="mb-2">
                        <strong>Avertissement :</strong> Mise en garde orale ou écrite
                    </li>
                    <li class="mb-2">
                        <strong>Blâme :</strong> Sanction inscrite au dossier
                    </li>
                    <li class="mb-2">
                        <strong>Exclusion temporaire :</strong> Suspension de cours
                    </li>
                    <li class="mb-2">
                        <strong>Travaux supplémentaires :</strong> Activités éducatives
                    </li>
                    <li class="mb-2">
                        <strong>Exclusion définitive :</strong> Renvoi de l'établissement
                    </li>
                </ul>
                
                <hr>
                
                <h6 class="text-primary">Rappels importants :</h6>
                <ul class="small">
                    <li>Respecter la proportionnalité</li>
                    <li>Informer les parents</li>
                    <li>Documenter la décision</li>
                    <li>Prévoir un suivi</li>
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

// Auto-calcul de la date de fin selon la durée
document.getElementById('duree_jours').addEventListener('input', function() {
    const duree = parseInt(this.value);
    const dateDebut = document.getElementById('date_debut').value || document.getElementById('date_sanction').value;
    
    if (duree > 0 && dateDebut) {
        const debut = new Date(dateDebut);
        debut.setDate(debut.getDate() + duree);
        document.getElementById('date_fin').value = debut.toISOString().split('T')[0];
    }
});

// Auto-remplissage de la durée selon le type de sanction
document.getElementById('type_sanction_id')?.addEventListener('change', function() {
    const selectedOption = this.options[this.selectedIndex];
    const dureeDefaut = selectedOption.getAttribute('data-duree');
    
    if (dureeDefaut && dureeDefaut > 0) {
        document.getElementById('duree_jours').value = dureeDefaut;
        // Déclencher le calcul de la date de fin
        document.getElementById('duree_jours').dispatchEvent(new Event('input'));
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
