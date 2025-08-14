<?php
/**
 * Module Communication - Ajouter une annonce
 * Application de gestion scolaire - République Démocratique du Congo
 */

require_once '../../../config/config.php';
require_once '../../../config/database.php';
require_once '../../../includes/functions.php';

// Vérifier l'authentification et les permissions
requireLogin();
if (!checkPermission('communication')) {
    showMessage('error', 'Accès refusé à cette page.');
    redirectTo('../index.php');
}

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $action = $_POST['action'] ?? '';
        
        if ($action === 'add_annonce') {
            $titre = trim($_POST['titre'] ?? '');
            $contenu = trim($_POST['contenu'] ?? '');
            $type_annonce = $_POST['type_annonce'] ?? 'generale';
            $cible = $_POST['cible'] ?? 'tous';
            $classe_id = intval($_POST['classe_id'] ?? 0);
            $date_publication = $_POST['date_publication'] ?? date('Y-m-d H:i:s');
            $date_expiration = $_POST['date_expiration'] ?? null;
            $epinglee = isset($_POST['epinglee']) ? 1 : 0;
            $couleur = $_POST['couleur'] ?? '#007bff';
            
            // Validation
            if (empty($titre) || empty($contenu)) {
                throw new Exception('Le titre et le contenu sont obligatoires.');
            }
            
            if (!in_array($type_annonce, ['generale', 'urgente', 'evenement', 'administrative', 'pedagogique'])) {
                throw new Exception('Type d\'annonce invalide.');
            }
            
            if (!in_array($cible, ['tous', 'eleves', 'personnel', 'parents', 'classe_specifique'])) {
                throw new Exception('Cible invalide.');
            }
            
            if ($cible === 'classe_specifique' && !$classe_id) {
                throw new Exception('Veuillez sélectionner une classe.');
            }
            
            if ($date_expiration && strtotime($date_expiration) <= strtotime($date_publication)) {
                throw new Exception('La date d\'expiration doit être postérieure à la date de publication.');
            }
            
            // Valider la couleur
            if (!preg_match('/^#[0-9A-Fa-f]{6}$/', $couleur)) {
                $couleur = '#007bff';
            }
            
            // Créer l'annonce
            $database->execute(
                "INSERT INTO annonces (
                    titre, contenu, auteur_id, type_annonce, cible, classe_id,
                    date_publication, date_expiration, epinglee, couleur, active, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, NOW())",
                [
                    $titre, $contenu, $_SESSION['user_id'], $type_annonce, $cible,
                    $classe_id ?: null, $date_publication, $date_expiration, $epinglee, $couleur
                ]
            );
            
            showMessage('success', 'Annonce créée avec succès.');
            redirectTo('index.php');
        }
        
    } catch (Exception $e) {
        showMessage('error', 'Erreur : ' . $e->getMessage());
    }
}

// Récupérer les classes
try {
    $classes = $database->query(
        "SELECT id, nom, niveau FROM classes ORDER BY niveau, nom"
    )->fetchAll();
} catch (Exception $e) {
    $classes = [];
}

$page_title = "Nouvelle Annonce";
include '../../../includes/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">
        <i class="fas fa-bullhorn me-2"></i>
        Nouvelle Annonce
    </h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <div class="btn-group me-2">
            <a href="../" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-1"></i>
                Retour à la communication
            </a>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-lg-8">
        <form method="POST" class="needs-validation" novalidate>
            <input type="hidden" name="action" value="add_annonce">
            
            <div class="card shadow-sm">
                <div class="card-header bg-light">
                    <h5 class="mb-0">
                        <i class="fas fa-plus me-2"></i>
                        Créer une annonce
                    </h5>
                </div>
                <div class="card-body">
                    <!-- Titre -->
                    <div class="mb-3">
                        <label for="titre" class="form-label">
                            Titre de l'annonce <span class="text-danger">*</span>
                        </label>
                        <input type="text" class="form-control" id="titre" name="titre" 
                               value="<?php echo htmlspecialchars($_POST['titre'] ?? ''); ?>" 
                               maxlength="255" required>
                        <div class="form-text">Titre accrocheur et descriptif</div>
                    </div>
                    
                    <!-- Type et cible -->
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="type_annonce" class="form-label">Type d'annonce</label>
                            <select class="form-select" id="type_annonce" name="type_annonce">
                                <option value="generale">Générale</option>
                                <option value="urgente">Urgente</option>
                                <option value="evenement">Événement</option>
                                <option value="administrative">Administrative</option>
                                <option value="pedagogique">Pédagogique</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="cible" class="form-label">Public cible</label>
                            <select class="form-select" id="cible" name="cible">
                                <option value="tous">Tout le monde</option>
                                <option value="eleves">Élèves uniquement</option>
                                <option value="personnel">Personnel uniquement</option>
                                <option value="parents">Parents uniquement</option>
                                <option value="classe_specifique">Classe spécifique</option>
                            </select>
                        </div>
                    </div>
                    
                    <!-- Classe spécifique -->
                    <div class="mb-3" id="classe_specifique_div" style="display: none;">
                        <label for="classe_id" class="form-label">Classe concernée</label>
                        <select class="form-select" id="classe_id" name="classe_id">
                            <option value="">-- Sélectionner une classe --</option>
                            <?php foreach ($classes as $classe): ?>
                                <option value="<?php echo $classe['id']; ?>">
                                    <?php echo htmlspecialchars($classe['nom'] . ' (' . $classe['niveau'] . ')'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <!-- Contenu -->
                    <div class="mb-3">
                        <label for="contenu" class="form-label">
                            Contenu de l'annonce <span class="text-danger">*</span>
                        </label>
                        <textarea class="form-control" id="contenu" name="contenu" rows="8" required
                                  placeholder="Rédigez le contenu de votre annonce..."><?php echo htmlspecialchars($_POST['contenu'] ?? ''); ?></textarea>
                        <div class="form-text">
                            <span id="char_count">0</span> caractère(s) - Soyez clair et concis
                        </div>
                    </div>
                    
                    <!-- Dates -->
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="date_publication" class="form-label">Date de publication</label>
                            <input type="datetime-local" class="form-control" id="date_publication" name="date_publication" 
                                   value="<?php echo date('Y-m-d\TH:i'); ?>">
                            <div class="form-text">Laissez vide pour publier immédiatement</div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="date_expiration" class="form-label">Date d'expiration (optionnel)</label>
                            <input type="datetime-local" class="form-control" id="date_expiration" name="date_expiration">
                            <div class="form-text">L'annonce sera automatiquement désactivée</div>
                        </div>
                    </div>
                    
                    <!-- Options -->
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="couleur" class="form-label">Couleur de l'annonce</label>
                            <div class="input-group">
                                <input type="color" class="form-control form-control-color" id="couleur" name="couleur" value="#007bff">
                                <span class="input-group-text">Couleur</span>
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Options</label>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="epinglee" name="epinglee">
                                <label class="form-check-label" for="epinglee">
                                    <i class="fas fa-thumbtack me-1"></i>
                                    Épingler cette annonce
                                </label>
                            </div>
                            <div class="form-text">Les annonces épinglées apparaissent en premier</div>
                        </div>
                    </div>
                </div>
                
                <div class="card-footer bg-light">
                    <div class="d-flex justify-content-between">
                        <a href="../" class="btn btn-secondary">
                            <i class="fas fa-times me-1"></i>
                            Annuler
                        </a>
                        <div>
                            <button type="button" class="btn btn-outline-primary me-2" onclick="previewAnnonce()">
                                <i class="fas fa-eye me-1"></i>
                                Aperçu
                            </button>
                            <button type="submit" class="btn btn-success">
                                <i class="fas fa-bullhorn me-1"></i>
                                Publier l'annonce
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
    
    <!-- Aide et conseils -->
    <div class="col-lg-4">
        <div class="card shadow-sm">
            <div class="card-header bg-light">
                <h5 class="mb-0">
                    <i class="fas fa-lightbulb me-2"></i>
                    Conseils pour une bonne annonce
                </h5>
            </div>
            <div class="card-body">
                <div class="alert alert-info">
                    <h6><i class="fas fa-info-circle me-2"></i>Titre efficace</h6>
                    <ul class="mb-0 small">
                        <li>Utilisez des mots-clés importants</li>
                        <li>Soyez précis et concis</li>
                        <li>Évitez les majuscules excessives</li>
                        <li>Mentionnez la date si pertinent</li>
                    </ul>
                </div>
                
                <div class="alert alert-success">
                    <h6><i class="fas fa-users me-2"></i>Public cible</h6>
                    <ul class="mb-0 small">
                        <li><strong>Tous :</strong> Visible par toute la communauté</li>
                        <li><strong>Élèves :</strong> Informations scolaires</li>
                        <li><strong>Personnel :</strong> Communications internes</li>
                        <li><strong>Parents :</strong> Informations familiales</li>
                        <li><strong>Classe :</strong> Informations spécifiques</li>
                    </ul>
                </div>
                
                <div class="alert alert-warning">
                    <h6><i class="fas fa-palette me-2"></i>Types d'annonces</h6>
                    <ul class="mb-0 small">
                        <li><strong>Générale :</strong> Informations courantes</li>
                        <li><strong>Urgente :</strong> Informations prioritaires</li>
                        <li><strong>Événement :</strong> Activités et sorties</li>
                        <li><strong>Administrative :</strong> Procédures officielles</li>
                        <li><strong>Pédagogique :</strong> Contenu éducatif</li>
                    </ul>
                </div>
            </div>
        </div>
        
        <!-- Aperçu en temps réel -->
        <div class="card shadow-sm mt-4">
            <div class="card-header bg-light">
                <h5 class="mb-0">
                    <i class="fas fa-eye me-2"></i>
                    Aperçu en temps réel
                </h5>
            </div>
            <div class="card-body">
                <div id="preview_card" class="card border-start border-4" style="border-left-color: #007bff !important;">
                    <div class="card-body p-3">
                        <div class="d-flex justify-content-between align-items-start">
                            <h6 class="card-title mb-1" id="preview_titre">
                                <i class="fas fa-thumbtack text-warning me-1" id="preview_pin" style="display: none;"></i>
                                Titre de votre annonce
                            </h6>
                            <span class="badge" id="preview_badge" style="background-color: #007bff;">
                                Générale
                            </span>
                        </div>
                        <p class="card-text small text-muted mb-2" id="preview_contenu">
                            Le contenu de votre annonce apparaîtra ici...
                        </p>
                        <small class="text-muted" id="preview_info">
                            Par Vous • Maintenant • Public: Tout le monde
                        </small>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal d'aperçu -->
<div class="modal fade" id="previewModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-eye me-2"></i>
                    Aperçu de l'annonce
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="full_preview"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="fas fa-times me-1"></i>
                    Fermer
                </button>
                <button type="button" class="btn btn-success" onclick="document.querySelector('form').submit()">
                    <i class="fas fa-bullhorn me-1"></i>
                    Publier maintenant
                </button>
            </div>
        </div>
    </div>
</div>

<script>
// Gestion de la cible
document.getElementById('cible').addEventListener('change', function() {
    const classeDiv = document.getElementById('classe_specifique_div');
    if (this.value === 'classe_specifique') {
        classeDiv.style.display = 'block';
    } else {
        classeDiv.style.display = 'none';
    }
    updatePreview();
});

// Compteur de caractères
function updateCharCount() {
    const contenu = document.getElementById('contenu').value;
    document.getElementById('char_count').textContent = contenu.length;
}

document.getElementById('contenu').addEventListener('input', function() {
    updateCharCount();
    updatePreview();
});

// Mise à jour de l'aperçu en temps réel
function updatePreview() {
    const titre = document.getElementById('titre').value || 'Titre de votre annonce';
    const contenu = document.getElementById('contenu').value || 'Le contenu de votre annonce apparaîtra ici...';
    const type = document.getElementById('type_annonce').value;
    const cible = document.getElementById('cible').value;
    const couleur = document.getElementById('couleur').value;
    const epinglee = document.getElementById('epinglee').checked;
    
    // Mettre à jour l'aperçu
    document.getElementById('preview_titre').innerHTML = 
        (epinglee ? '<i class="fas fa-thumbtack text-warning me-1"></i>' : '') + titre;
    document.getElementById('preview_contenu').textContent = contenu.substring(0, 100) + (contenu.length > 100 ? '...' : '');
    document.getElementById('preview_badge').textContent = type.charAt(0).toUpperCase() + type.slice(1);
    document.getElementById('preview_badge').style.backgroundColor = couleur;
    document.getElementById('preview_card').style.borderLeftColor = couleur + ' !important';
    
    // Mettre à jour les informations
    const cibleTexts = {
        'tous': 'Tout le monde',
        'eleves': 'Élèves',
        'personnel': 'Personnel',
        'parents': 'Parents',
        'classe_specifique': 'Classe spécifique'
    };
    document.getElementById('preview_info').textContent = 
        `Par Vous • Maintenant • Public: ${cibleTexts[cible]}`;
}

// Événements pour la mise à jour en temps réel
['titre', 'type_annonce', 'couleur'].forEach(id => {
    document.getElementById(id).addEventListener('input', updatePreview);
    document.getElementById(id).addEventListener('change', updatePreview);
});

document.getElementById('epinglee').addEventListener('change', updatePreview);

// Aperçu complet
function previewAnnonce() {
    const titre = document.getElementById('titre').value;
    const contenu = document.getElementById('contenu').value;
    const type = document.getElementById('type_annonce').value;
    const couleur = document.getElementById('couleur').value;
    const epinglee = document.getElementById('epinglee').checked;
    
    const preview = `
        <div class="card border-start border-4" style="border-left-color: ${couleur} !important;">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start mb-3">
                    <h4 class="card-title">
                        ${epinglee ? '<i class="fas fa-thumbtack text-warning me-2"></i>' : ''}
                        ${titre || 'Titre de l\'annonce'}
                    </h4>
                    <span class="badge fs-6" style="background-color: ${couleur};">
                        ${type.charAt(0).toUpperCase() + type.slice(1)}
                    </span>
                </div>
                <div class="card-text">
                    ${contenu.replace(/\n/g, '<br>') || 'Contenu de l\'annonce'}
                </div>
                <hr>
                <small class="text-muted">
                    <i class="fas fa-user me-1"></i>Par Vous
                    <i class="fas fa-calendar ms-3 me-1"></i>${new Date().toLocaleDateString('fr-FR')}
                    <i class="fas fa-eye ms-3 me-1"></i>0 vue(s)
                </small>
            </div>
        </div>
    `;
    
    document.getElementById('full_preview').innerHTML = preview;
    const modal = new bootstrap.Modal(document.getElementById('previewModal'));
    modal.show();
}

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

// Initialiser
updateCharCount();
updatePreview();
</script>

<?php include '../../../includes/footer.php'; ?>
