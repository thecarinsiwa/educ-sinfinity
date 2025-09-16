<?php
/**
 * Module de gestion financière - Modèles de frais scolaires
 * Application de gestion scolaire - République Démocratique du Congo
 */

require_once '../../../config/config.php';
require_once '../../../config/database.php';
require_once '../../../includes/functions.php';
require_once '../../../includes/permissions-pages.php';
require_once '../../../includes/ui-permissions.php';

// Vérifier l'authentification et les permissions
requireLogin();
requirePagePermissionFromDB('finance', 'fees/templates', 'read', '../../dashboard.php');

$page_title = 'Modèles de Frais Scolaires';

// Obtenir l'année scolaire actuelle
$current_year = getCurrentAcademicYear();

// Obtenir la devise par défaut
$devise_par_defaut = getDefaultCurrency();

// Vérifier si la table modeles_frais existe, sinon la créer
try {
    $table_exists = $database->query("SHOW TABLES LIKE 'modeles_frais'")->fetch();
    if (!$table_exists) {
        $create_table = "
            CREATE TABLE modeles_frais (
                id INT PRIMARY KEY AUTO_INCREMENT,
                nom VARCHAR(255) NOT NULL,
                description TEXT,
                niveau ENUM('maternelle', 'primaire', 'secondaire', 'tous') NOT NULL,
                type_etablissement ENUM('public', 'prive', 'tous') DEFAULT 'tous',
                frais_data JSON NOT NULL,
                created_by INT NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
                is_active BOOLEAN DEFAULT TRUE,
                usage_count INT DEFAULT 0,
                FOREIGN KEY (created_by) REFERENCES users(id),
                INDEX idx_niveau (niveau),
                INDEX idx_active (is_active)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ";
        $database->execute($create_table);
    }
} catch (Exception $e) {
    // Table creation failed, continue anyway
}

// Traitement des actions
$action = sanitizeInput($_GET['action'] ?? '');
$template_id = (int)($_GET['id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hasPagePermissionFromDB('finance', 'index', 'read')) {
        showMessage('error', 'Permissions insuffisantes pour cette action.');
        redirectTo('templates.php');
    }

    $action = sanitizeInput($_POST['action'] ?? '');
    
    switch ($action) {
        case 'create':
            $nom = sanitizeInput($_POST['nom'] ?? '');
            $description = sanitizeInput($_POST['description'] ?? '');
            $niveau = sanitizeInput($_POST['niveau'] ?? '');
            $type_etablissement = sanitizeInput($_POST['type_etablissement'] ?? 'tous');
            $frais_data = $_POST['frais_data'] ?? [];
            
            if (empty($nom) || empty($niveau) || empty($frais_data)) {
                showMessage('error', 'Veuillez remplir tous les champs obligatoires.');
            } else {
                try {
                    $frais_json = json_encode($frais_data);
                    $database->execute(
                        "INSERT INTO modeles_frais (nom, description, niveau, type_etablissement, frais_data, created_by) 
                         VALUES (?, ?, ?, ?, ?, ?)",
                        [$nom, $description, $niveau, $type_etablissement, $frais_json, $_SESSION['user_id']]
                    );
                    showMessage('success', 'Modèle créé avec succès.');
                    redirectTo('templates.php');
                } catch (Exception $e) {
                    showMessage('error', 'Erreur lors de la création du modèle : ' . $e->getMessage());
                }
            }
            break;
            
        case 'apply':
            $template_id = (int)($_POST['template_id'] ?? 0);
            $classes_selected = $_POST['classes'] ?? [];
            
            if (empty($template_id) || empty($classes_selected)) {
                showMessage('error', 'Veuillez sélectionner un modèle et au moins une classe.');
            } else {
                try {
                    // Récupérer le modèle
                    $template = $database->query(
                        "SELECT * FROM modeles_frais WHERE id = ? AND is_active = 1",
                        [$template_id]
                    )->fetch();
                    
                    if (!$template) {
                        showMessage('error', 'Modèle introuvable.');
                        break;
                    }
                    
                    $frais_data = json_decode($template['frais_data'], true);
                    $applied_count = 0;
                    
                    foreach ($classes_selected as $classe_id) {
                        // Vérifier si la classe existe et correspond au niveau du modèle
                        $classe = $database->query(
                            "SELECT * FROM classes WHERE id = ? AND annee_scolaire_id = ?",
                            [$classe_id, $current_year['id']]
                        )->fetch();
                        
                        if ($classe && ($template['niveau'] === 'tous' || $classe['niveau'] === $template['niveau'])) {
                            // Supprimer les frais existants pour cette classe
                            $database->execute(
                                "DELETE FROM frais_scolaires WHERE classe_id = ? AND annee_scolaire_id = ?",
                                [$classe_id, $current_year['id']]
                            );
                            
                            // Appliquer les frais du modèle
                            foreach ($frais_data as $frais_item) {
                                $database->execute(
                                    "INSERT INTO frais_scolaires (classe_id, type_frais, libelle, montant, obligatoire, date_echeance, description, annee_scolaire_id) 
                                     VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
                                    [
                                        $classe_id,
                                        $frais_item['type_frais'],
                                        $frais_item['libelle'],
                                        $frais_item['montant'],
                                        $frais_item['obligatoire'] ?? 1,
                                        $frais_item['date_echeance'] ?? null,
                                        $frais_item['description'] ?? '',
                                        $current_year['id']
                                    ]
                                );
                            }
                            $applied_count++;
                        }
                    }
                    
                    // Mettre à jour le compteur d'utilisation
                    $database->execute(
                        "UPDATE modeles_frais SET usage_count = usage_count + 1 WHERE id = ?",
                        [$template_id]
                    );
                    
                    showMessage('success', "Modèle appliqué avec succès à {$applied_count} classe(s).");
                    redirectTo('templates.php');
                } catch (Exception $e) {
                    showMessage('error', 'Erreur lors de l\'application du modèle : ' . $e->getMessage());
                }
            }
            break;
            
        case 'delete':
            if ($template_id > 0) {
                try {
                    $database->execute(
                        "UPDATE modeles_frais SET is_active = 0 WHERE id = ?",
                        [$template_id]
                    );
                    showMessage('success', 'Modèle supprimé avec succès.');
                    redirectTo('templates.php');
                } catch (Exception $e) {
                    showMessage('error', 'Erreur lors de la suppression : ' . $e->getMessage());
                }
            }
            break;
    }
}

// Récupérer les modèles
$modeles = [];
try {
    $table_check = $database->query("SHOW TABLES LIKE 'modeles_frais'")->fetch();
    if ($table_check) {
        $modeles = $database->query(
            "SELECT m.*, u.nom as created_by_nom, u.prenom as created_by_prenom
             FROM modeles_frais m
             LEFT JOIN users u ON m.created_by = u.id
             WHERE m.is_active = 1
             ORDER BY m.created_at DESC"
        )->fetchAll();
    }
} catch (Exception $e) {
    $modeles = [];
}

// Récupérer les classes pour l'application des modèles
$classes = [];
try {
    $classes = $database->query(
        "SELECT id, nom, niveau FROM classes WHERE annee_scolaire_id = ? ORDER BY niveau, nom",
        [$current_year['id'] ?? 0]
    )->fetchAll();
} catch (Exception $e) {
    $classes = [];
}

// Statistiques
$stats = [];
$stats['total_modeles'] = count($modeles);
$stats['modeles_par_niveau'] = [];
foreach ($modeles as $modele) {
    $niveau = $modele['niveau'];
    if (!isset($stats['modeles_par_niveau'][$niveau])) {
        $stats['modeles_par_niveau'][$niveau] = 0;
    }
    $stats['modeles_par_niveau'][$niveau]++;
}

include '../../../includes/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">
        <i class="fas fa-copy me-2"></i>
        Modèles de Frais Scolaires
    </h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <div class="btn-group me-2">
            <a href="index.php" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-1"></i>
                Retour aux frais
            </a>
        </div>
        <?php if (hasPagePermissionFromDB('finance', 'index', 'read')): ?>
            <div class="btn-group">
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createTemplateModal">
                    <i class="fas fa-plus me-1"></i>
                    Nouveau modèle
                </button>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Statistiques -->
<div class="row mb-4">
    <div class="col-md-3">
        <div class="card text-white bg-primary">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h4><?php echo $stats['total_modeles']; ?></h4>
                        <p class="mb-0">Modèles disponibles</p>
                    </div>
                    <div class="align-self-center">
                        <i class="fas fa-copy fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-white bg-success">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h4><?php echo $stats['modeles_par_niveau']['maternelle'] ?? 0; ?></h4>
                        <p class="mb-0">Maternelle</p>
                    </div>
                    <div class="align-self-center">
                        <i class="fas fa-baby fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-white bg-info">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h4><?php echo $stats['modeles_par_niveau']['primaire'] ?? 0; ?></h4>
                        <p class="mb-0">Primaire</p>
                    </div>
                    <div class="align-self-center">
                        <i class="fas fa-graduation-cap fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-white bg-warning">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h4><?php echo $stats['modeles_par_niveau']['secondaire'] ?? 0; ?></h4>
                        <p class="mb-0">Secondaire</p>
                    </div>
                    <div class="align-self-center">
                        <i class="fas fa-university fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Liste des modèles -->
<div class="card">
    <div class="card-header">
        <h5 class="mb-0">
            <i class="fas fa-list me-2"></i>
            Modèles disponibles (<?php echo count($modeles); ?>)
        </h5>
    </div>
    <div class="card-body">
        <?php if (!empty($modeles)): ?>
            <div class="row">
                <?php foreach ($modeles as $modele): ?>
                    <?php 
                    $frais_data = json_decode($modele['frais_data'], true);
                    $total_frais = count($frais_data);
                    $montant_total = array_sum(array_column($frais_data, 'montant'));
                    ?>
                    <div class="col-lg-4 col-md-6 mb-4">
                        <div class="card h-100 border-0 shadow-sm">
                            <div class="card-header bg-<?php 
                                echo $modele['niveau'] === 'maternelle' ? 'warning' : 
                                    ($modele['niveau'] === 'primaire' ? 'success' : 
                                    ($modele['niveau'] === 'secondaire' ? 'primary' : 'secondary')); 
                            ?> text-white">
                                <div class="d-flex justify-content-between align-items-center">
                                    <h6 class="mb-0"><?php echo htmlspecialchars($modele['nom']); ?></h6>
                                    <span class="badge bg-light text-dark">
                                        <?php echo ucfirst($modele['niveau']); ?>
                                    </span>
                                </div>
                            </div>
                            <div class="card-body">
                                <?php if ($modele['description']): ?>
                                    <p class="card-text text-muted">
                                        <?php echo htmlspecialchars(substr($modele['description'], 0, 100)); ?>
                                        <?php echo strlen($modele['description']) > 100 ? '...' : ''; ?>
                                    </p>
                                <?php endif; ?>
                                
                                <div class="row text-center mb-3">
                                    <div class="col-6">
                                        <h5 class="text-primary"><?php echo $total_frais; ?></h5>
                                        <small class="text-muted">Types de frais</small>
                                    </div>
                                    <div class="col-6">
                                        <h5 class="text-success"><?php echo formatCurrency($montant_total); ?></h5>
                                        <small class="text-muted">Montant total</small>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <small class="text-muted">
                                        <i class="fas fa-user me-1"></i>
                                        Créé par <?php echo htmlspecialchars($modele['created_by_nom'] . ' ' . $modele['created_by_prenom']); ?>
                                        <br>
                                        <i class="fas fa-calendar me-1"></i>
                                        <?php echo formatDate($modele['created_at']); ?>
                                        <br>
                                        <i class="fas fa-chart-line me-1"></i>
                                        Utilisé <?php echo $modele['usage_count']; ?> fois
                                    </small>
                                </div>
                                
                                <!-- Aperçu des frais -->
                                <div class="mb-3">
                                    <h6>Types de frais :</h6>
                                    <div class="d-flex flex-wrap gap-1">
                                        <?php foreach (array_slice($frais_data, 0, 4) as $frais_item): ?>
                                            <span class="badge bg-light text-dark">
                                                <?php echo htmlspecialchars($frais_item['libelle']); ?>
                                            </span>
                                        <?php endforeach; ?>
                                        <?php if (count($frais_data) > 4): ?>
                                            <span class="badge bg-secondary">
                                                +<?php echo count($frais_data) - 4; ?> autres
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            <div class="card-footer bg-transparent">
                                <div class="btn-group w-100" role="group">
                                    <button type="button" class="btn btn-outline-info btn-sm" 
                                            data-bs-toggle="modal" data-bs-target="#previewModal"
                                            onclick="previewTemplate(<?php echo htmlspecialchars(json_encode($modele)); ?>)">
                                        <i class="fas fa-eye"></i> Aperçu
                                    </button>
                                    <?php if (hasPagePermissionFromDB('finance', 'index', 'read')): ?>
                                        <button type="button" class="btn btn-outline-primary btn-sm"
                                                data-bs-toggle="modal" data-bs-target="#applyModal"
                                                onclick="applyTemplate(<?php echo $modele['id']; ?>, '<?php echo $modele['niveau']; ?>')">
                                            <i class="fas fa-check"></i> Appliquer
                                        </button>
                                        <a href="?action=delete&id=<?php echo $modele['id']; ?>" 
                                           class="btn btn-outline-danger btn-sm"
                                           onclick="return confirm('Êtes-vous sûr de vouloir supprimer ce modèle ?')">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="text-center py-5">
                <i class="fas fa-copy fa-3x text-muted mb-3"></i>
                <h5 class="text-muted">Aucun modèle disponible</h5>
                <p class="text-muted">
                    Créez votre premier modèle de frais pour faciliter la configuration des classes.
                </p>
                <?php if (hasPagePermissionFromDB('finance', 'index', 'read')): ?>
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createTemplateModal">
                        <i class="fas fa-plus me-1"></i>
                        Créer un modèle
                    </button>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Modal de création de modèle -->
<?php if (hasPagePermissionFromDB('finance', 'index', 'read')): ?>
<div class="modal fade" id="createTemplateModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="create">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-plus me-2"></i>
                        Créer un nouveau modèle
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="nom" class="form-label">Nom du modèle *</label>
                                <input type="text" class="form-control" id="nom" name="nom" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="niveau" class="form-label">Niveau *</label>
                                <select class="form-select" id="niveau" name="niveau" required>
                                    <option value="">Sélectionner un niveau</option>
                                    <option value="maternelle">Maternelle</option>
                                    <option value="primaire">Primaire</option>
                                    <option value="secondaire">Secondaire</option>
                                    <option value="tous">Tous les niveaux</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="description" class="form-label">Description</label>
                        <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label for="type_etablissement" class="form-label">Type d'établissement</label>
                        <select class="form-select" id="type_etablissement" name="type_etablissement">
                            <option value="tous">Tous</option>
                            <option value="public">Public</option>
                            <option value="prive">Privé</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Configuration des frais *</label>
                        <div id="frais-container">
                            <div class="frais-item border rounded p-3 mb-3">
                                <div class="row">
                                    <div class="col-md-4">
                                        <label class="form-label">Type de frais</label>
                                        <select class="form-select" name="frais_data[0][type_frais]" required>
                                            <option value="inscription">Inscription</option>
                                            <option value="mensualite">Mensualité</option>
                                            <option value="examen">Examen</option>
                                            <option value="uniforme">Uniforme</option>
                                            <option value="transport">Transport</option>
                                            <option value="cantine">Cantine</option>
                                            <option value="autre">Autre</option>
                                        </select>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">Libellé</label>
                                        <input type="text" class="form-control" name="frais_data[0][libelle]" required>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">Montant</label>
                                        <input type="number" class="form-control" name="frais_data[0][montant]" step="0.01" required>
                                    </div>
                                </div>
                                <div class="row mt-2">
                                    <div class="col-md-6">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="frais_data[0][obligatoire]" value="1" checked>
                                            <label class="form-check-label">Obligatoire</label>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Date d'échéance</label>
                                        <input type="date" class="form-control" name="frais_data[0][date_echeance]">
                                    </div>
                                </div>
                                <div class="row mt-2">
                                    <div class="col-12">
                                        <label class="form-label">Description</label>
                                        <textarea class="form-control" name="frais_data[0][description]" rows="2"></textarea>
                                    </div>
                                </div>
                                <div class="mt-2">
                                    <button type="button" class="btn btn-outline-danger btn-sm" onclick="removeFraisItem(this)">
                                        <i class="fas fa-trash"></i> Supprimer
                                    </button>
                                </div>
                            </div>
                        </div>
                        <button type="button" class="btn btn-outline-primary" onclick="addFraisItem()">
                            <i class="fas fa-plus"></i> Ajouter un frais
                        </button>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-1"></i>
                        Créer le modèle
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal d'application de modèle -->
<div class="modal fade" id="applyModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="apply">
                <input type="hidden" name="template_id" id="apply_template_id">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-check me-2"></i>
                        Appliquer le modèle
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <strong>Attention :</strong> Cette action remplacera tous les frais existants des classes sélectionnées.
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Sélectionner les classes :</label>
                        <div id="classes-container" style="max-height: 300px; overflow-y: auto;">
                            <!-- Les classes seront chargées dynamiquement -->
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-check me-1"></i>
                        Appliquer le modèle
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Modal d'aperçu -->
<div class="modal fade" id="previewModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-eye me-2"></i>
                    Aperçu du modèle
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="preview-content">
                <!-- Le contenu sera chargé dynamiquement -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fermer</button>
            </div>
        </div>
    </div>
</div>

<script>
let fraisIndex = 1;

function addFraisItem() {
    const container = document.getElementById('frais-container');
    const newItem = document.createElement('div');
    newItem.className = 'frais-item border rounded p-3 mb-3';
    newItem.innerHTML = `
        <div class="row">
            <div class="col-md-4">
                <label class="form-label">Type de frais</label>
                <select class="form-select" name="frais_data[${fraisIndex}][type_frais]" required>
                    <option value="inscription">Inscription</option>
                    <option value="mensualite">Mensualité</option>
                    <option value="examen">Examen</option>
                    <option value="uniforme">Uniforme</option>
                    <option value="transport">Transport</option>
                    <option value="cantine">Cantine</option>
                    <option value="autre">Autre</option>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label">Libellé</label>
                <input type="text" class="form-control" name="frais_data[${fraisIndex}][libelle]" required>
            </div>
            <div class="col-md-4">
                <label class="form-label">Montant</label>
                <input type="number" class="form-control" name="frais_data[${fraisIndex}][montant]" step="0.01" required>
            </div>
        </div>
        <div class="row mt-2">
            <div class="col-md-6">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="frais_data[${fraisIndex}][obligatoire]" value="1" checked>
                    <label class="form-check-label">Obligatoire</label>
                </div>
            </div>
            <div class="col-md-6">
                <label class="form-label">Date d'échéance</label>
                <input type="date" class="form-control" name="frais_data[${fraisIndex}][date_echeance]">
            </div>
        </div>
        <div class="row mt-2">
            <div class="col-12">
                <label class="form-label">Description</label>
                <textarea class="form-control" name="frais_data[${fraisIndex}][description]" rows="2"></textarea>
            </div>
        </div>
        <div class="mt-2">
            <button type="button" class="btn btn-outline-danger btn-sm" onclick="removeFraisItem(this)">
                <i class="fas fa-trash"></i> Supprimer
            </button>
        </div>
    `;
    container.appendChild(newItem);
    fraisIndex++;
}

function removeFraisItem(button) {
    button.closest('.frais-item').remove();
}

function applyTemplate(templateId, niveau) {
    document.getElementById('apply_template_id').value = templateId;
    const container = document.getElementById('classes-container');
    
    // Filtrer les classes selon le niveau du modèle
    const classes = <?php echo json_encode($classes); ?>;
    let filteredClasses = classes;
    
    if (niveau !== 'tous') {
        filteredClasses = classes.filter(classe => classe.niveau === niveau);
    }
    
    container.innerHTML = '';
    
    if (filteredClasses.length === 0) {
        container.innerHTML = '<div class="alert alert-info">Aucune classe disponible pour ce niveau.</div>';
        return;
    }
    
    filteredClasses.forEach(classe => {
        const div = document.createElement('div');
        div.className = 'form-check';
        div.innerHTML = `
            <input class="form-check-input" type="checkbox" name="classes[]" value="${classe.id}" id="classe_${classe.id}">
            <label class="form-check-label" for="classe_${classe.id}">
                ${classe.nom} (${classe.niveau})
            </label>
        `;
        container.appendChild(div);
    });
}

function previewTemplate(template) {
    const content = document.getElementById('preview-content');
    const fraisData = JSON.parse(template.frais_data);
    
    let html = `
        <div class="mb-3">
            <h6>${template.nom}</h6>
            <p class="text-muted">${template.description || 'Aucune description'}</p>
            <div class="row">
                <div class="col-md-6">
                    <strong>Niveau :</strong> ${template.niveau}
                </div>
                <div class="col-md-6">
                    <strong>Type d'établissement :</strong> ${template.type_etablissement}
                </div>
            </div>
        </div>
        
        <h6>Configuration des frais :</h6>
        <div class="table-responsive">
            <table class="table table-sm">
                <thead>
                    <tr>
                        <th>Type</th>
                        <th>Libellé</th>
                        <th>Montant</th>
                        <th>Obligatoire</th>
                        <th>Échéance</th>
                    </tr>
                </thead>
                <tbody>
    `;
    
    fraisData.forEach(frais => {
        html += `
            <tr>
                <td><span class="badge bg-primary">${frais.type_frais}</span></td>
                <td>${frais.libelle}</td>
                <td><strong>${formatCurrency(frais.montant)}</strong></td>
                <td>${frais.obligatoire ? '<span class="badge bg-danger">Oui</span>' : '<span class="badge bg-secondary">Non</span>'}</td>
                <td>${frais.date_echeance || '-'}</td>
            </tr>
        `;
    });
    
    html += `
                </tbody>
            </table>
        </div>
        
        <div class="alert alert-info">
            <strong>Total :</strong> ${formatCurrency(fraisData.reduce((sum, frais) => sum + parseFloat(frais.montant), 0))}
        </div>
    `;
    
    content.innerHTML = html;
}

function formatCurrency(amount) {
    return new Intl.NumberFormat('fr-CD', {
        style: 'currency',
        currency: 'CDF',
        minimumFractionDigits: 0
    }).format(amount);
}
</script>

<?php include '../../../includes/footer.php'; ?>

