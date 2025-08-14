<?php
/**
 * Import en lot de candidatures ou d'élèves
 * Application de gestion scolaire - République Démocratique du Congo
 */

require_once '../../../config/config.php';
require_once '../../../config/database.php';
require_once '../../../includes/functions.php';

// Vérifier l'authentification et les permissions
requireLogin();
if (!checkPermission('students')) {
    showMessage('error', 'Accès refusé à cette page.');
    redirectTo('index.php');
}

// Traitement du formulaire d'import
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $import_type = $_POST['import_type'] ?? '';
        
        if (!isset($_FILES['import_file']) || $_FILES['import_file']['error'] !== UPLOAD_ERR_OK) {
            throw new Exception('Veuillez sélectionner un fichier valide.');
        }
        
        $file = $_FILES['import_file'];
        $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        
        if (!in_array($file_extension, ['csv', 'xlsx', 'xls'])) {
            throw new Exception('Format de fichier non supporté. Utilisez CSV ou Excel.');
        }
        
        // Traitement selon le type d'import
        switch ($import_type) {
            case 'candidatures':
                $result = importCandidatures($file, $database);
                break;
            case 'eleves':
                $result = importEleves($file, $database);
                break;
            default:
                throw new Exception('Type d\'import non spécifié.');
        }
        
        showMessage('success', $result['message']);
        
        if (!empty($result['errors'])) {
            showMessage('warning', 'Erreurs rencontrées : ' . implode(', ', $result['errors']));
        }
        
    } catch (Exception $e) {
        showMessage('error', 'Erreur lors de l\'import : ' . $e->getMessage());
    }
}

// Fonction d'import des candidatures
function importCandidatures($file, $database) {
    $imported = 0;
    $errors = [];
    
    // Lire le fichier CSV
    if (($handle = fopen($file['tmp_name'], "r")) !== FALSE) {
        $header = fgetcsv($handle, 1000, ","); // Lire l'en-tête
        
        // Récupérer l'année scolaire courante
        $current_year = $database->query(
            "SELECT * FROM annees_scolaires WHERE status = 'active' LIMIT 1"
        )->fetch();
        
        if (!$current_year) {
            throw new Exception('Aucune année scolaire active trouvée.');
        }
        
        $line_number = 1;
        while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
            $line_number++;
            
            try {
                // Mapper les données (adapter selon votre format CSV)
                $nom_eleve = trim($data[0] ?? '');
                $prenom_eleve = trim($data[1] ?? '');
                $date_naissance = $data[2] ?? '';
                $sexe = $data[3] ?? '';
                $classe_nom = trim($data[4] ?? '');
                $telephone_parent = trim($data[5] ?? '');
                $nom_pere = trim($data[6] ?? '');
                $nom_mere = trim($data[7] ?? '');
                
                // Validation
                if (empty($nom_eleve) || empty($prenom_eleve) || empty($date_naissance) || empty($sexe)) {
                    $errors[] = "Ligne $line_number: Données obligatoires manquantes";
                    continue;
                }
                
                // Trouver la classe
                $classe = $database->query(
                    "SELECT id FROM classes WHERE nom = ? LIMIT 1",
                    [$classe_nom]
                )->fetch();
                
                if (!$classe) {
                    $errors[] = "Ligne $line_number: Classe '$classe_nom' non trouvée";
                    continue;
                }
                
                // Générer un numéro de demande
                $year_suffix = date('Y');
                $numero_demande = "ADM{$year_suffix}" . str_pad($imported + 1, 3, '0', STR_PAD_LEFT);
                
                // Insérer la candidature
                $database->execute(
                    "INSERT INTO demandes_admission (
                        numero_demande, annee_scolaire_id, classe_demandee_id, nom_eleve, prenom_eleve,
                        date_naissance, sexe, telephone_parent, nom_pere, nom_mere,
                        status, priorite, created_at
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'en_attente', 'normale', NOW())",
                    [
                        $numero_demande, $current_year['id'], $classe['id'], $nom_eleve, $prenom_eleve,
                        $date_naissance, $sexe, $telephone_parent, $nom_pere, $nom_mere
                    ]
                );
                
                $imported++;
                
            } catch (Exception $e) {
                $errors[] = "Ligne $line_number: " . $e->getMessage();
            }
        }
        
        fclose($handle);
    }
    
    return [
        'message' => "$imported candidature(s) importée(s) avec succès.",
        'errors' => $errors
    ];
}

// Fonction d'import des élèves
function importEleves($file, $database) {
    $imported = 0;
    $errors = [];
    
    // Lire le fichier CSV
    if (($handle = fopen($file['tmp_name'], "r")) !== FALSE) {
        $header = fgetcsv($handle, 1000, ","); // Lire l'en-tête
        
        // Récupérer l'année scolaire courante
        $current_year = $database->query(
            "SELECT * FROM annees_scolaires WHERE status = 'active' LIMIT 1"
        )->fetch();
        
        if (!$current_year) {
            throw new Exception('Aucune année scolaire active trouvée.');
        }
        
        $line_number = 1;
        while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
            $line_number++;
            
            try {
                // Mapper les données
                $nom = trim($data[0] ?? '');
                $prenom = trim($data[1] ?? '');
                $date_naissance = $data[2] ?? '';
                $sexe = $data[3] ?? '';
                $classe_nom = trim($data[4] ?? '');
                $telephone_parent = trim($data[5] ?? '');
                
                // Validation
                if (empty($nom) || empty($prenom) || empty($date_naissance) || empty($sexe)) {
                    $errors[] = "Ligne $line_number: Données obligatoires manquantes";
                    continue;
                }
                
                // Vérifier si l'élève existe déjà
                $existing = $database->query(
                    "SELECT id FROM eleves WHERE nom = ? AND prenom = ? AND date_naissance = ?",
                    [$nom, $prenom, $date_naissance]
                )->fetch();
                
                if ($existing) {
                    $errors[] = "Ligne $line_number: Élève déjà existant";
                    continue;
                }
                
                // Trouver la classe
                $classe = $database->query(
                    "SELECT id FROM classes WHERE nom = ? LIMIT 1",
                    [$classe_nom]
                )->fetch();
                
                if (!$classe) {
                    $errors[] = "Ligne $line_number: Classe '$classe_nom' non trouvée";
                    continue;
                }
                
                // Générer un numéro d'élève
                $annee_courante = date('Y');
                $numero_eleve = $annee_courante . str_pad($imported + 1, 4, '0', STR_PAD_LEFT);
                
                // Insérer l'élève
                $database->execute(
                    "INSERT INTO eleves (
                        numero_eleve, nom, prenom, date_naissance, sexe, telephone_parent,
                        classe_id, annee_scolaire_id, status, date_inscription, created_at
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'actif', ?, NOW())",
                    [
                        $numero_eleve, $nom, $prenom, $date_naissance, $sexe, $telephone_parent,
                        $classe['id'], $current_year['id'], date('Y-m-d')
                    ]
                );
                
                $imported++;
                
            } catch (Exception $e) {
                $errors[] = "Ligne $line_number: " . $e->getMessage();
            }
        }
        
        fclose($handle);
    }
    
    return [
        'message' => "$imported élève(s) importé(s) avec succès.",
        'errors' => $errors
    ];
}

$page_title = "Import en Lot";
include '../../../includes/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">
        <i class="fas fa-upload me-2"></i>
        Import en Lot
    </h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <div class="btn-group me-2">
            <a href="index.php" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-1"></i>
                Retour aux Admissions
            </a>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-file-import me-2"></i>
                    Importer des données
                </h5>
            </div>
            <div class="card-body">
                <form method="POST" enctype="multipart/form-data" class="needs-validation" novalidate>
                    <div class="mb-4">
                        <label class="form-label">
                            <i class="fas fa-list me-1"></i>
                            Type d'import <span class="text-danger">*</span>
                        </label>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="import_type" 
                                           id="import_candidatures" value="candidatures" required>
                                    <label class="form-check-label" for="import_candidatures">
                                        <i class="fas fa-file-alt me-2"></i>
                                        <strong>Candidatures</strong>
                                        <br>
                                        <small class="text-muted">Importer des demandes d'admission</small>
                                    </label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="import_type" 
                                           id="import_eleves" value="eleves" required>
                                    <label class="form-check-label" for="import_eleves">
                                        <i class="fas fa-user-graduate me-2"></i>
                                        <strong>Élèves</strong>
                                        <br>
                                        <small class="text-muted">Inscription directe d'élèves</small>
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-4">
                        <label for="import_file" class="form-label">
                            <i class="fas fa-file me-1"></i>
                            Fichier à importer <span class="text-danger">*</span>
                        </label>
                        <input type="file" class="form-control" id="import_file" name="import_file" 
                               accept=".csv,.xlsx,.xls" required>
                        <div class="form-text">
                            Formats acceptés : CSV, Excel (.xlsx, .xls). Taille maximale : 5 MB
                        </div>
                    </div>
                    
                    <div class="d-flex justify-content-between">
                        <a href="index.php" class="btn btn-secondary">
                            <i class="fas fa-times me-1"></i>
                            Annuler
                        </a>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-upload me-1"></i>
                            Importer les données
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <div class="col-md-4">
        <!-- Instructions -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-info-circle me-2"></i>
                    Instructions
                </h5>
            </div>
            <div class="card-body">
                <div class="alert alert-info">
                    <h6><i class="fas fa-lightbulb me-2"></i>Format requis</h6>
                    <p class="small mb-2">Votre fichier doit contenir les colonnes suivantes :</p>
                    
                    <div id="format-candidatures" style="display: none;">
                        <strong>Pour les candidatures :</strong>
                        <ol class="small mb-0">
                            <li>Nom</li>
                            <li>Prénom</li>
                            <li>Date de naissance (YYYY-MM-DD)</li>
                            <li>Sexe (M/F)</li>
                            <li>Classe demandée</li>
                            <li>Téléphone parent</li>
                            <li>Nom du père</li>
                            <li>Nom de la mère</li>
                        </ol>
                    </div>
                    
                    <div id="format-eleves" style="display: none;">
                        <strong>Pour les élèves :</strong>
                        <ol class="small mb-0">
                            <li>Nom</li>
                            <li>Prénom</li>
                            <li>Date de naissance (YYYY-MM-DD)</li>
                            <li>Sexe (M/F)</li>
                            <li>Classe</li>
                            <li>Téléphone parent</li>
                        </ol>
                    </div>
                </div>
                
                <div class="alert alert-warning">
                    <h6><i class="fas fa-exclamation-triangle me-2"></i>Important</h6>
                    <ul class="small mb-0">
                        <li>La première ligne doit contenir les en-têtes</li>
                        <li>Les noms de classes doivent exister dans le système</li>
                        <li>Les dates doivent être au format YYYY-MM-DD</li>
                        <li>Vérifiez vos données avant l'import</li>
                    </ul>
                </div>
            </div>
        </div>
        
        <!-- Modèles -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-download me-2"></i>
                    Modèles
                </h5>
            </div>
            <div class="card-body">
                <p class="small text-muted">Téléchargez un modèle pour vous aider :</p>
                <div class="d-grid gap-2">
                    <a href="templates/modele-candidatures.csv" class="btn btn-outline-primary btn-sm" download>
                        <i class="fas fa-file-csv me-1"></i>
                        Modèle Candidatures
                    </a>
                    <a href="templates/modele-eleves.csv" class="btn btn-outline-success btn-sm" download>
                        <i class="fas fa-file-csv me-1"></i>
                        Modèle Élèves
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const candidaturesRadio = document.getElementById('import_candidatures');
    const elevesRadio = document.getElementById('import_eleves');
    const formatCandidatures = document.getElementById('format-candidatures');
    const formatEleves = document.getElementById('format-eleves');
    
    function updateFormat() {
        if (candidaturesRadio.checked) {
            formatCandidatures.style.display = 'block';
            formatEleves.style.display = 'none';
        } else if (elevesRadio.checked) {
            formatCandidatures.style.display = 'none';
            formatEleves.style.display = 'block';
        }
    }
    
    candidaturesRadio.addEventListener('change', updateFormat);
    elevesRadio.addEventListener('change', updateFormat);
});

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
</script>

<?php include '../../../includes/footer.php'; ?>
