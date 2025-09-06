<?php
/**
 * Paramètres du module Carte d'Élève
 * Application de gestion scolaire - République Démocratique du Congo
 */

require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/config/database.php';
require_once dirname(__DIR__, 2) . '/includes/functions.php';
requireLogin();

// Vérifier les permissions
if (!hasPermission('cartes_eleves', 'settings')) {
    showMessage('error', 'Vous n\'avez pas les permissions nécessaires pour accéder à ce module.');
    redirectTo('../dashboard.php');
}

$page_title = "Paramètres des Cartes d'Élèves";
$current_module = 'cartes_eleves';

// Récupérer les paramètres actuels
$parametres = $database->query("SELECT * FROM parametres_cartes LIMIT 1")->fetch();

if (!$parametres) {
    // Créer les paramètres par défaut
    $database->execute(
        "INSERT INTO parametres_cartes (nom_ecole, couleur_principale, couleur_secondaire, couleur_texte) 
         VALUES ('École Sinfinity', '#1e40af', '#3b82f6', '#1f2937')"
    );
    $parametres = $database->query("SELECT * FROM parametres_cartes LIMIT 1")->fetch();
}

// Traitement du formulaire
if ($_POST) {
    try {
        $nom_ecole = $_POST['nom_ecole'] ?? '';
        $couleur_principale = $_POST['couleur_principale'] ?? '#1e40af';
        $couleur_secondaire = $_POST['couleur_secondaire'] ?? '#3b82f6';
        $couleur_texte = $_POST['couleur_texte'] ?? '#1f2937';
        $format_carte = $_POST['format_carte'] ?? 'pdf';
        $dimensions = $_POST['dimensions'] ?? '85.6x54mm';
        $qr_code_size = intval($_POST['qr_code_size'] ?? 100);
        $include_photo = isset($_POST['include_photo']) ? 1 : 0;
        $include_qr_code = isset($_POST['include_qr_code']) ? 1 : 0;
        $include_barcode = isset($_POST['include_barcode']) ? 1 : 0;
        
        // Gestion de l'upload du logo
        $logo_ecole = $parametres['logo_ecole'];
        if (isset($_FILES['logo_ecole']) && $_FILES['logo_ecole']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = '../../uploads/logos/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            
            $file_extension = strtolower(pathinfo($_FILES['logo_ecole']['name'], PATHINFO_EXTENSION));
            $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'svg'];
            
            if (in_array($file_extension, $allowed_extensions)) {
                $new_filename = 'logo_' . time() . '.' . $file_extension;
                $upload_path = $upload_dir . $new_filename;
                
                if (move_uploaded_file($_FILES['logo_ecole']['tmp_name'], $upload_path)) {
                    // Supprimer l'ancien logo
                    if ($logo_ecole && file_exists('../../' . $logo_ecole)) {
                        unlink('../../' . $logo_ecole);
                    }
                    $logo_ecole = 'uploads/logos/' . $new_filename;
                }
            }
        }
        
        // Mettre à jour les paramètres
        $sql = "UPDATE parametres_cartes SET 
                nom_ecole = ?, couleur_principale = ?, couleur_secondaire = ?, couleur_texte = ?,
                logo_ecole = ?, format_carte = ?, dimensions = ?, qr_code_size = ?,
                include_photo = ?, include_qr_code = ?, include_barcode = ?,
                updated_at = NOW()
                WHERE id = ?";
        
        $database->execute($sql, [
            $nom_ecole, $couleur_principale, $couleur_secondaire, $couleur_texte,
            $logo_ecole, $format_carte, $dimensions, $qr_code_size,
            $include_photo, $include_qr_code, $include_barcode,
            $parametres['id']
        ]);
        
        logAction('cartes_eleves', 'Mise à jour des paramètres des cartes d\'élèves');
        showMessage('success', 'Paramètres mis à jour avec succès');
        
        // Recharger les paramètres
        $parametres = $database->query("SELECT * FROM parametres_cartes LIMIT 1")->fetch();
        
    } catch (Exception $e) {
        showMessage('error', 'Erreur lors de la mise à jour: ' . $e->getMessage());
    }
}

include dirname(__DIR__, 2) . '/includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="page-title-box">
                <div class="page-title-right">
                    <ol class="breadcrumb m-0">
                        <li class="breadcrumb-item"><a href="../dashboard.php">Tableau de bord</a></li>
                        <li class="breadcrumb-item"><a href="index.php">Cartes d'Élèves</a></li>
                        <li class="breadcrumb-item active">Paramètres</li>
                    </ol>
                </div>
                <h4 class="page-title">Paramètres des Cartes d'Élèves</h4>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title">Configuration du Design</h5>
                </div>
                <div class="card-body">
                    <form method="POST" enctype="multipart/form-data">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Nom de l'école</label>
                                    <input type="text" class="form-control" name="nom_ecole" 
                                           value="<?= htmlspecialchars($parametres['nom_ecole']) ?>" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Logo de l'école</label>
                                    <input type="file" class="form-control" name="logo_ecole" 
                                           accept="image/*">
                                    <?php if ($parametres['logo_ecole']): ?>
                                    <div class="mt-2">
                                        <img src="../../<?= htmlspecialchars($parametres['logo_ecole']) ?>" 
                                             class="img-thumbnail" style="max-width: 100px; max-height: 100px;">
                                        <small class="text-muted d-block">Logo actuel</small>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label">Couleur principale</label>
                                    <input type="color" class="form-control form-control-color" 
                                           name="couleur_principale" 
                                           value="<?= htmlspecialchars($parametres['couleur_principale']) ?>">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label">Couleur secondaire</label>
                                    <input type="color" class="form-control form-control-color" 
                                           name="couleur_secondaire" 
                                           value="<?= htmlspecialchars($parametres['couleur_secondaire']) ?>">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label">Couleur du texte</label>
                                    <input type="color" class="form-control form-control-color" 
                                           name="couleur_texte" 
                                           value="<?= htmlspecialchars($parametres['couleur_texte']) ?>">
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Format de carte</label>
                                    <select class="form-select" name="format_carte">
                                        <option value="pdf" <?= $parametres['format_carte'] === 'pdf' ? 'selected' : '' ?>>PDF</option>
                                        <option value="pvc" <?= $parametres['format_carte'] === 'pvc' ? 'selected' : '' ?>>PVC (Impression)</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Dimensions</label>
                                    <select class="form-select" name="dimensions">
                                        <option value="85.6x54mm" <?= $parametres['dimensions'] === '85.6x54mm' ? 'selected' : '' ?>>Carte de crédit (85.6x54mm)</option>
                                        <option value="90x60mm" <?= $parametres['dimensions'] === '90x60mm' ? 'selected' : '' ?>>Carte PVC (90x60mm)</option>
                                        <option value="100x70mm" <?= $parametres['dimensions'] === '100x70mm' ? 'selected' : '' ?>>Grande carte (100x70mm)</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Taille du QR Code (px)</label>
                                    <input type="number" class="form-control" name="qr_code_size" 
                                           value="<?= $parametres['qr_code_size'] ?>" min="50" max="200">
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-12">
                                <h6>Éléments à inclure sur la carte</h6>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="include_photo" 
                                           id="include_photo" <?= $parametres['include_photo'] ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="include_photo">
                                        Photo de l'élève
                                    </label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="include_qr_code" 
                                           id="include_qr_code" <?= $parametres['include_qr_code'] ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="include_qr_code">
                                        QR Code
                                    </label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="include_barcode" 
                                           id="include_barcode" <?= $parametres['include_barcode'] ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="include_barcode">
                                        Code-barres
                                    </label>
                                </div>
                            </div>
                        </div>

                        <div class="text-end">
                            <button type="submit" class="btn btn-primary">
                                <i class="mdi mdi-content-save me-1"></i> Enregistrer
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <!-- Aperçu de la carte -->
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title">Aperçu de la carte</h5>
                </div>
                <div class="card-body text-center">
                    <div id="cardPreview" class="mb-3">
                        <!-- L'aperçu sera généré ici -->
                    </div>
                    <button type="button" class="btn btn-outline-primary btn-sm" onclick="updatePreview()">
                        <i class="mdi mdi-refresh me-1"></i> Actualiser l'aperçu
                    </button>
                </div>
            </div>

            <!-- Actions -->
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title">Actions</h5>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <button type="button" class="btn btn-success" onclick="testCard()">
                            <i class="mdi mdi-test-tube me-1"></i> Tester une carte
                        </button>
                        <button type="button" class="btn btn-info" onclick="regenerateAllCards()">
                            <i class="mdi mdi-refresh me-1"></i> Régénérer toutes les cartes
                        </button>
                        <button type="button" class="btn btn-warning" onclick="exportSettings()">
                            <i class="mdi mdi-export me-1"></i> Exporter les paramètres
                        </button>
                        <button type="button" class="btn btn-outline-secondary" onclick="importSettings()">
                            <i class="mdi mdi-import me-1"></i> Importer les paramètres
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
/* Styles pour l'aperçu de la carte */
.card-preview {
    width: 200px;
    height: 126px;
    background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
    border-radius: 10px;
    padding: 15px;
    color: white;
    font-family: 'Arial', sans-serif;
    position: relative;
    margin: 0 auto;
    box-shadow: 0 4px 15px rgba(0,0,0,0.15);
    font-size: 10px;
}

.card-preview .school-name {
    font-size: 8px;
    font-weight: bold;
    opacity: 0.9;
    margin-bottom: 5px;
}

.card-preview .student-name {
    font-size: 12px;
    font-weight: bold;
    margin-bottom: 3px;
}

.card-preview .student-info {
    font-size: 8px;
    opacity: 0.9;
    margin-bottom: 2px;
}

.card-preview .matricule {
    font-size: 8px;
    font-weight: bold;
    background: rgba(255,255,255,0.2);
    padding: 2px 6px;
    border-radius: 8px;
    display: inline-block;
    margin-top: 5px;
}

.card-preview .qr-placeholder {
    position: absolute;
    right: 10px;
    bottom: 10px;
    width: 25px;
    height: 25px;
    background: white;
    border-radius: 3px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #333;
    font-size: 8px;
}
</style>

<script>
// Variables CSS pour les couleurs
function updateCSSVariables() {
    const primaryColor = document.querySelector('input[name="couleur_principale"]').value;
    const secondaryColor = document.querySelector('input[name="couleur_secondaire"]').value;
    
    document.documentElement.style.setProperty('--primary-color', primaryColor);
    document.documentElement.style.setProperty('--secondary-color', secondaryColor);
}

// Générer l'aperçu de la carte
function generateCardPreview() {
    const nomEcole = document.querySelector('input[name="nom_ecole"]').value || 'École Sinfinity';
    const includePhoto = document.querySelector('input[name="include_photo"]').checked;
    const includeQR = document.querySelector('input[name="include_qr_code"]').checked;
    
    const cardHtml = `
        <div class="card-preview">
            <div class="school-name">${nomEcole}</div>
            <div class="student-name">Jean Dupont</div>
            <div class="student-info">Classe: 6ème A</div>
            <div class="student-info">Année: 2024-2025</div>
            <div class="matricule">MAT2024001</div>
            ${includeQR ? '<div class="qr-placeholder">QR</div>' : ''}
        </div>
    `;
    
    document.getElementById('cardPreview').innerHTML = cardHtml;
}

// Mettre à jour l'aperçu
function updatePreview() {
    updateCSSVariables();
    generateCardPreview();
}

// Actions
function testCard() {
    // Récupérer un élève au hasard pour tester
    fetch('get-test-student.php')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                window.open(`view.php?id=${data.carte_id}`, '_blank');
            } else {
                alert('Aucune carte disponible pour le test');
            }
        });
}

function regenerateAllCards() {
    if (confirm('Êtes-vous sûr de vouloir régénérer toutes les cartes ? Cette action peut prendre du temps.')) {
        fetch('regenerate-all.php', {
            method: 'POST'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert(`Cartes régénérées avec succès: ${data.count} carte(s)`);
            } else {
                alert('Erreur: ' + data.message);
            }
        });
    }
}

function exportSettings() {
    window.open('export-settings.php', '_blank');
}

function importSettings() {
    const input = document.createElement('input');
    input.type = 'file';
    input.accept = '.json';
    input.onchange = function(e) {
        const file = e.target.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = function(e) {
                try {
                    const settings = JSON.parse(e.target.result);
                    // Appliquer les paramètres
                    Object.keys(settings).forEach(key => {
                        const input = document.querySelector(`[name="${key}"]`);
                        if (input) {
                            if (input.type === 'checkbox') {
                                input.checked = settings[key];
                            } else {
                                input.value = settings[key];
                            }
                        }
                    });
                    updatePreview();
                    alert('Paramètres importés avec succès');
                } catch (error) {
                    alert('Erreur lors de l\'importation: ' + error.message);
                }
            };
            reader.readAsText(file);
        }
    };
    input.click();
}

// Écouter les changements de couleur
document.querySelectorAll('input[type="color"]').forEach(input => {
    input.addEventListener('change', updatePreview);
});

// Écouter les changements de texte
document.querySelector('input[name="nom_ecole"]').addEventListener('input', updatePreview);

// Écouter les changements de checkboxes
document.querySelectorAll('input[type="checkbox"]').forEach(input => {
    input.addEventListener('change', updatePreview);
});

// Initialiser l'aperçu
document.addEventListener('DOMContentLoaded', function() {
    updatePreview();
});
</script>

<?php include dirname(__DIR__, 2) . '/includes/footer.php'; ?>
