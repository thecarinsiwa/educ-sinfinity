<?php
/**
 * Module de gestion du personnel - Import en lot
 * Application de gestion scolaire - République Démocratique du Congo
 */

require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../includes/functions.php';

// Vérifier l'authentification et les permissions
requireLogin();
if (!checkPermission('personnel')) {
    showMessage('error', 'Accès refusé à cette fonctionnalité.');
    redirectTo('index.php');
}

$page_title = 'Importer du personnel';
$errors = [];
$success = false;
$import_results = [];

// Traitement de l'import
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['csv_file'];
        $filename = $file['name'];
        $tmp_path = $file['tmp_name'];
        
        // Vérifier l'extension
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        if ($extension !== 'csv') {
            $errors[] = 'Le fichier doit être au format CSV.';
        } else {
            // Lire le fichier CSV
            if (($handle = fopen($tmp_path, "r")) !== FALSE) {
                $row = 1;
                $imported = 0;
                $skipped = 0;
                $errors_import = [];
                
                // Lire l'en-tête
                $headers = fgetcsv($handle, 1000, ",");
                $expected_headers = [
                    'matricule', 'nom', 'prenom', 'sexe', 'date_naissance', 
                    'lieu_naissance', 'adresse', 'telephone', 'email', 
                    'fonction', 'specialite', 'diplome', 'date_embauche', 'salaire_base'
                ];
                
                // Vérifier les en-têtes
                if (count(array_intersect($headers, $expected_headers)) < 5) {
                    $errors[] = 'Le fichier CSV ne contient pas les colonnes requises.';
                } else {
                    // Traiter chaque ligne
                    while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
                        $row++;
                        
                        // Créer un tableau associatif
                        $row_data = array_combine($headers, $data);
                        
                        // Validation des données
                        $row_errors = [];
                        
                        // Champs obligatoires
                        if (empty($row_data['matricule'])) {
                            $row_errors[] = 'Matricule manquant';
                        }
                        if (empty($row_data['nom'])) {
                            $row_errors[] = 'Nom manquant';
                        }
                        if (empty($row_data['prenom'])) {
                            $row_errors[] = 'Prénom manquant';
                        }
                        if (empty($row_data['sexe'])) {
                            $row_errors[] = 'Sexe manquant';
                        }
                        if (empty($row_data['fonction'])) {
                            $row_errors[] = 'Fonction manquant';
                        }
                        
                        // Vérifier l'unicité du matricule
                        if (!empty($row_data['matricule'])) {
                            $stmt = $database->query("SELECT id FROM personnel WHERE matricule = ?", [$row_data['matricule']]);
                            if ($stmt->fetch()) {
                                $row_errors[] = 'Matricule déjà existant';
                            }
                        }
                        
                        // Validation du sexe
                        if (!empty($row_data['sexe']) && !in_array(strtoupper($row_data['sexe']), ['M', 'F'])) {
                            $row_errors[] = 'Sexe invalide (M ou F)';
                        }
                        
                        // Validation de la fonction
                        $fonctions_valides = ['enseignant', 'directeur', 'sous_directeur', 'secretaire', 'comptable', 'surveillant', 'gardien', 'autre'];
                        if (!empty($row_data['fonction']) && !in_array(strtolower($row_data['fonction']), $fonctions_valides)) {
                            $row_errors[] = 'Fonction invalide';
                        }
                        
                        // Validation de l'email
                        if (!empty($row_data['email']) && !isValidEmail($row_data['email'])) {
                            $row_errors[] = 'Email invalide';
                        }
                        
                        // Si pas d'erreurs, insérer
                        if (empty($row_errors)) {
                            try {
                                $sql = "INSERT INTO personnel (
                                    matricule, nom, prenom, sexe, date_naissance, lieu_naissance,
                                    adresse, telephone, email, fonction, specialite, diplome,
                                    date_embauche, salaire_base, status
                                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'actif')";
                                
                                $params = [
                                    $row_data['matricule'],
                                    $row_data['nom'],
                                    $row_data['prenom'],
                                    strtoupper($row_data['sexe']),
                                    !empty($row_data['date_naissance']) ? $row_data['date_naissance'] : null,
                                    $row_data['lieu_naissance'] ?? null,
                                    $row_data['adresse'] ?? null,
                                    $row_data['telephone'] ?? null,
                                    $row_data['email'] ?? null,
                                    strtolower($row_data['fonction']),
                                    $row_data['specialite'] ?? null,
                                    $row_data['diplome'] ?? null,
                                    $row_data['date_embauche'] ?? null,
                                    !empty($row_data['salaire_base']) ? $row_data['salaire_base'] : null
                                ];
                                
                                $database->query($sql, $params);
                                $imported++;
                                
                                // Logger l'action
                                logAction('personnel_import', "Import du personnel: {$row_data['nom']} {$row_data['prenom']} ({$row_data['matricule']})");
                                
                            } catch (Exception $e) {
                                $row_errors[] = 'Erreur lors de l\'insertion: ' . $e->getMessage();
                            }
                        }
                        
                        if (!empty($row_errors)) {
                            $errors_import[] = "Ligne $row: " . implode(', ', $row_errors);
                            $skipped++;
                        }
                    }
                    
                    fclose($handle);
                    
                    $import_results = [
                        'imported' => $imported,
                        'skipped' => $skipped,
                        'errors' => $errors_import
                    ];
                    
                    if ($imported > 0) {
                        $success = true;
                    }
                }
            } else {
                $errors[] = 'Impossible de lire le fichier CSV.';
            }
        }
    } else {
        $errors[] = 'Veuillez sélectionner un fichier CSV valide.';
    }
}

include '../../includes/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">
        <i class="fas fa-upload me-2"></i>
        Importer du personnel
    </h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <a href="index.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left me-1"></i>
            Retour à la liste
        </a>
    </div>
</div>

<?php if ($success): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="fas fa-check-circle me-2"></i>
        <strong>Import réussi !</strong> <?php echo $import_results['imported']; ?> membre(s) du personnel importé(s) avec succès.
        <?php if ($import_results['skipped'] > 0): ?>
            <?php echo $import_results['skipped']; ?> ligne(s) ignorée(s) à cause d'erreurs.
        <?php endif; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if (!empty($errors)): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="fas fa-exclamation-triangle me-2"></i>
        <strong>Erreurs :</strong>
        <ul class="mb-0 mt-2">
            <?php foreach ($errors as $error): ?>
                <li><?php echo htmlspecialchars($error); ?></li>
            <?php endforeach; ?>
        </ul>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if (!empty($import_results['errors'])): ?>
    <div class="alert alert-warning alert-dismissible fade show" role="alert">
        <i class="fas fa-exclamation-triangle me-2"></i>
        <strong>Erreurs d'import :</strong>
        <div class="mt-2" style="max-height: 200px; overflow-y: auto;">
            <?php foreach (array_slice($import_results['errors'], 0, 10) as $error): ?>
                <div class="text-muted small"><?php echo htmlspecialchars($error); ?></div>
            <?php endforeach; ?>
            <?php if (count($import_results['errors']) > 10): ?>
                <div class="text-muted small">... et <?php echo count($import_results['errors']) - 10; ?> autres erreurs</div>
            <?php endif; ?>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="row">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-file-csv me-2"></i>
                    Importer un fichier CSV
                </h5>
            </div>
            <div class="card-body">
                <form method="POST" enctype="multipart/form-data">
                    <div class="mb-3">
                        <label for="csv_file" class="form-label">Fichier CSV</label>
                        <input type="file" 
                               class="form-control" 
                               id="csv_file" 
                               name="csv_file" 
                               accept=".csv"
                               required>
                        <div class="form-text">
                            Le fichier doit être au format CSV avec les colonnes suivantes : 
                            matricule, nom, prenom, sexe, date_naissance, lieu_naissance, adresse, 
                            telephone, email, fonction, specialite, diplome, date_embauche, salaire_base
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-upload me-1"></i>
                            Importer
                        </button>
                        <a href="template.csv" class="btn btn-outline-secondary ms-2">
                            <i class="fas fa-download me-1"></i>
                            Télécharger le modèle
                        </a>
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
                    Instructions
                </h5>
            </div>
            <div class="card-body">
                <h6>Format du fichier CSV :</h6>
                <ul class="small">
                    <li><strong>matricule</strong> : Code unique (obligatoire)</li>
                    <li><strong>nom</strong> : Nom de famille (obligatoire)</li>
                    <li><strong>prenom</strong> : Prénom (obligatoire)</li>
                    <li><strong>sexe</strong> : M ou F (obligatoire)</li>
                    <li><strong>date_naissance</strong> : AAAA-MM-JJ (optionnel)</li>
                    <li><strong>lieu_naissance</strong> : Ville de naissance (optionnel)</li>
                    <li><strong>adresse</strong> : Adresse complète (optionnel)</li>
                    <li><strong>telephone</strong> : Numéro de téléphone (optionnel)</li>
                    <li><strong>email</strong> : Adresse email (optionnel)</li>
                    <li><strong>fonction</strong> : enseignant, directeur, sous_directeur, secretaire, comptable, surveillant, gardien, autre (obligatoire)</li>
                    <li><strong>specialite</strong> : Spécialité pour les enseignants (optionnel)</li>
                    <li><strong>diplome</strong> : Diplôme obtenu (optionnel)</li>
                    <li><strong>date_embauche</strong> : AAAA-MM-JJ (optionnel)</li>
                    <li><strong>salaire_base</strong> : Salaire de base (optionnel)</li>
                </ul>
                
                <h6 class="mt-3">Notes importantes :</h6>
                <ul class="small text-muted">
                    <li>Le matricule doit être unique</li>
                    <li>Les dates doivent être au format AAAA-MM-JJ</li>
                    <li>Le salaire doit être un nombre sans symbole monétaire</li>
                    <li>Les lignes avec erreurs seront ignorées</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>
