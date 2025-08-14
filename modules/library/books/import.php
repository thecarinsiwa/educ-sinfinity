<?php
/**
 * Module Bibliothèque - Importation de livres
 * Application de gestion scolaire - République Démocratique du Congo
 */

require_once '../../../config/config.php';
require_once '../../../config/database.php';
require_once '../../../includes/functions.php';

// Vérifier l'authentification et les permissions
requireLogin();
if (!checkPermission('library')) {
    showMessage('error', 'Accès refusé à cette page.');
    redirectTo('../../../index.php');
}

$errors = [];
$success_message = '';

// Vérifier et créer les tables nécessaires
try {
    // Vérifier la table livres
    $tables = $database->query("SHOW TABLES LIKE 'livres'")->fetch();
    if (!$tables) {
        $database->execute("
            CREATE TABLE IF NOT EXISTS livres (
                id INT PRIMARY KEY AUTO_INCREMENT,
                isbn VARCHAR(20),
                titre VARCHAR(200) NOT NULL,
                auteur VARCHAR(100),
                editeur VARCHAR(100),
                annee_publication YEAR,
                categorie_id INT,
                nombre_pages INT,
                langue VARCHAR(50) DEFAULT 'Français',
                resume TEXT,
                emplacement VARCHAR(100),
                cote VARCHAR(50),
                prix_achat DECIMAL(10,2),
                date_acquisition DATE,
                etat ENUM('neuf', 'bon', 'moyen', 'mauvais', 'deteriore') DEFAULT 'bon',
                status ENUM('disponible', 'emprunte', 'reserve', 'perdu', 'retire') DEFAULT 'disponible',
                nombre_exemplaires INT DEFAULT 1,
                exemplaires_disponibles INT DEFAULT 1,
                notes TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            )
        ");
    }
    
    // Vérifier la table categories_livres
    $categories_table = $database->query("SHOW TABLES LIKE 'categories_livres'")->fetch();
    if (!$categories_table) {
        $database->execute("
            CREATE TABLE IF NOT EXISTS categories_livres (
                id INT PRIMARY KEY AUTO_INCREMENT,
                nom VARCHAR(100) NOT NULL,
                description TEXT,
                couleur VARCHAR(7) DEFAULT '#007bff',
                actif TINYINT(1) DEFAULT 1,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ");
        
        // Insérer quelques catégories par défaut
        $default_categories = [
            ['Romans', 'Littérature et romans', '#28a745'],
            ['Sciences', 'Livres scientifiques', '#007bff'],
            ['Histoire', 'Livres d\'histoire', '#ffc107'],
            ['Mathématiques', 'Manuels de mathématiques', '#dc3545'],
            ['Langues', 'Apprentissage des langues', '#6f42c1'],
            ['Informatique', 'Technologies et informatique', '#20c997'],
            ['Arts', 'Beaux-arts et créativité', '#fd7e14'],
            ['Philosophie', 'Philosophie et pensée', '#6c757d']
        ];
        
        foreach ($default_categories as $cat) {
            $database->execute(
                "INSERT INTO categories_livres (nom, description, couleur) VALUES (?, ?, ?)",
                $cat
            );
        }
    } else {
        // Vérifier si la colonne 'actif' existe
        try {
            $columns = $database->query("DESCRIBE categories_livres")->fetchAll();
            $actif_exists = false;
            foreach ($columns as $column) {
                if ($column['Field'] === 'actif') {
                    $actif_exists = true;
                    break;
                }
            }

            if (!$actif_exists) {
                $database->execute("ALTER TABLE categories_livres ADD COLUMN actif TINYINT(1) DEFAULT 1");
            }
        } catch (Exception $e) {
            // Ignorer les erreurs de modification de table
        }
    }
} catch (Exception $e) {
    $errors[] = 'Erreur lors de la vérification des tables : ' . $e->getMessage();
}

// Traitement du formulaire d'import
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!isset($_FILES['import_file']) || $_FILES['import_file']['error'] !== UPLOAD_ERR_OK) {
            throw new Exception('Veuillez sélectionner un fichier valide.');
        }
        
        $file = $_FILES['import_file'];
        $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        
        if (!in_array($file_extension, ['csv', 'xlsx', 'xls'])) {
            throw new Exception('Format de fichier non supporté. Utilisez CSV ou Excel.');
        }
        
        if ($file['size'] > MAX_FILE_SIZE) {
            throw new Exception('Fichier trop volumineux. Taille maximale : ' . (MAX_FILE_SIZE / 1024 / 1024) . ' MB');
        }
        
        // Traitement de l'import
        $result = importLivres($file, $database);
        
        if ($result['imported'] > 0) {
            $success_message = $result['imported'] . ' livre(s) importé(s) avec succès.';
        }
        
        if (!empty($result['errors'])) {
            $errors = array_merge($errors, $result['errors']);
        }
        
    } catch (Exception $e) {
        $errors[] = 'Erreur lors de l\'import : ' . $e->getMessage();
    }
}

// Fonction d'import des livres
function importLivres($file, $database) {
    $imported = 0;
    $errors = [];
    
    // Récupérer les catégories existantes
    $categories = [];
    try {
        $cats = $database->query("SELECT id, nom FROM categories_livres WHERE actif = 1")->fetchAll();
        foreach ($cats as $cat) {
            $categories[strtolower($cat['nom'])] = $cat['id'];
        }
    } catch (Exception $e) {
        // Ignorer si la table n'existe pas encore
    }
    
    // Lire le fichier CSV
    if (($handle = fopen($file['tmp_name'], "r")) !== FALSE) {
        // Lire l'en-tête
        $header = fgetcsv($handle, 1000, ",");
        
        if (!$header) {
            throw new Exception('Fichier CSV vide ou format invalide.');
        }
        
        // Vérifier les colonnes obligatoires
        $required_columns = ['titre'];
        $header_lower = array_map('strtolower', $header);
        
        foreach ($required_columns as $col) {
            if (!in_array($col, $header_lower)) {
                throw new Exception("Colonne obligatoire manquante : $col");
            }
        }
        
        $line_number = 1;
        while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
            $line_number++;
            
            try {
                // Créer un tableau associatif avec les données
                $row = array_combine($header, $data);
                
                // Nettoyer et valider les données
                $titre = trim($row['titre'] ?? $row['Titre'] ?? '');
                if (empty($titre)) {
                    $errors[] = "Ligne $line_number: Titre obligatoire";
                    continue;
                }
                
                $auteur = trim($row['auteur'] ?? $row['Auteur'] ?? '');
                $isbn = trim($row['isbn'] ?? $row['ISBN'] ?? '');
                $editeur = trim($row['editeur'] ?? $row['Editeur'] ?? '');
                $annee_publication = intval($row['annee_publication'] ?? $row['Année'] ?? 0) ?: null;
                $nombre_pages = intval($row['nombre_pages'] ?? $row['Pages'] ?? 0) ?: null;
                $langue = trim($row['langue'] ?? $row['Langue'] ?? 'Français');
                $resume = trim($row['resume'] ?? $row['Résumé'] ?? '');
                $emplacement = trim($row['emplacement'] ?? $row['Emplacement'] ?? '');
                $cote = trim($row['cote'] ?? $row['Cote'] ?? '');
                $prix_achat = floatval($row['prix_achat'] ?? $row['Prix'] ?? 0) ?: null;
                $nombre_exemplaires = intval($row['nombre_exemplaires'] ?? $row['Exemplaires'] ?? 1);
                $notes = trim($row['notes'] ?? $row['Notes'] ?? '');
                
                // Gestion de la catégorie
                $categorie_id = null;
                $categorie_nom = trim($row['categorie'] ?? $row['Catégorie'] ?? '');
                if (!empty($categorie_nom)) {
                    $categorie_key = strtolower($categorie_nom);
                    if (isset($categories[$categorie_key])) {
                        $categorie_id = $categories[$categorie_key];
                    } else {
                        // Créer une nouvelle catégorie
                        try {
                            $database->execute(
                                "INSERT INTO categories_livres (nom, description) VALUES (?, ?)",
                                [$categorie_nom, "Catégorie créée automatiquement lors de l'import"]
                            );
                            $categorie_id = $database->lastInsertId();
                            $categories[$categorie_key] = $categorie_id;
                        } catch (Exception $e) {
                            // Ignorer l'erreur de création de catégorie
                        }
                    }
                }
                
                // Gestion de l'état
                $etat = 'bon'; // Par défaut
                $etat_input = strtolower(trim($row['etat'] ?? $row['État'] ?? ''));
                $etats_valides = ['neuf', 'bon', 'moyen', 'mauvais', 'deteriore'];
                if (in_array($etat_input, $etats_valides)) {
                    $etat = $etat_input;
                }
                
                // Gestion de la date d'acquisition
                $date_acquisition = date('Y-m-d');
                $date_input = trim($row['date_acquisition'] ?? $row['Date acquisition'] ?? '');
                if (!empty($date_input)) {
                    $date_parsed = date('Y-m-d', strtotime($date_input));
                    if ($date_parsed) {
                        $date_acquisition = $date_parsed;
                    }
                }
                
                // Vérifier si le livre existe déjà (par ISBN ou titre+auteur)
                $existing = null;
                if (!empty($isbn)) {
                    $existing = $database->query(
                        "SELECT id FROM livres WHERE isbn = ? AND isbn != ''",
                        [$isbn]
                    )->fetch();
                }
                
                if (!$existing && !empty($titre) && !empty($auteur)) {
                    $existing = $database->query(
                        "SELECT id FROM livres WHERE titre = ? AND auteur = ?",
                        [$titre, $auteur]
                    )->fetch();
                }
                
                if ($existing) {
                    // Mettre à jour le nombre d'exemplaires
                    $database->execute(
                        "UPDATE livres SET 
                            nombre_exemplaires = nombre_exemplaires + ?,
                            exemplaires_disponibles = exemplaires_disponibles + ?,
                            updated_at = NOW()
                         WHERE id = ?",
                        [$nombre_exemplaires, $nombre_exemplaires, $existing['id']]
                    );
                } else {
                    // Insérer un nouveau livre
                    $database->execute(
                        "INSERT INTO livres (
                            isbn, titre, auteur, editeur, annee_publication, categorie_id,
                            nombre_pages, langue, resume, emplacement, cote, prix_achat,
                            date_acquisition, etat, status, nombre_exemplaires, 
                            exemplaires_disponibles, notes, created_at
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'disponible', ?, ?, ?, NOW())",
                        [
                            $isbn ?: null, $titre, $auteur, $editeur, $annee_publication, $categorie_id,
                            $nombre_pages, $langue, $resume, $emplacement, $cote, $prix_achat,
                            $date_acquisition, $etat, $nombre_exemplaires, $nombre_exemplaires, $notes
                        ]
                    );
                }
                
                $imported++;
                
            } catch (Exception $e) {
                $errors[] = "Ligne $line_number: " . $e->getMessage();
            }
        }
        
        fclose($handle);
    }
    
    return [
        'imported' => $imported,
        'errors' => $errors
    ];
}

// Récupérer les catégories pour l'aide
try {
    $categories = $database->query(
        "SELECT nom FROM categories_livres WHERE actif = 1 ORDER BY nom"
    )->fetchAll();
} catch (Exception $e) {
    $categories = [];
}

$page_title = "Importation de livres";
include '../../../includes/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">
        <i class="fas fa-file-import me-2 text-primary"></i>
        Importation de livres
    </h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <div class="btn-group me-2">
            <a href="index.php" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-1"></i>
                Retour à la liste
            </a>
        </div>
        <div class="btn-group">
            <a href="add.php" class="btn btn-success">
                <i class="fas fa-plus me-1"></i>
                Ajouter manuellement
            </a>
        </div>
    </div>
</div>

<?php if (!empty($errors)): ?>
    <div class="alert alert-danger">
        <h6><i class="fas fa-exclamation-circle me-1"></i> Erreurs rencontrées :</h6>
        <ul class="mb-0">
            <?php foreach ($errors as $error): ?>
                <li><?php echo htmlspecialchars($error); ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<?php if (!empty($success_message)): ?>
    <div class="alert alert-success">
        <i class="fas fa-check-circle me-2"></i>
        <?php echo htmlspecialchars($success_message); ?>
    </div>
<?php endif; ?>

<div class="row">
    <div class="col-lg-8">
        <div class="card shadow-sm">
            <div class="card-header bg-light">
                <h5 class="mb-0">
                    <i class="fas fa-upload me-2"></i>
                    Importer des livres
                </h5>
            </div>
            <div class="card-body">
                <form method="POST" enctype="multipart/form-data" class="needs-validation" novalidate>
                    <div class="mb-4">
                        <label for="import_file" class="form-label">
                            <i class="fas fa-file me-1"></i>
                            Fichier à importer <span class="text-danger">*</span>
                        </label>
                        <input type="file" class="form-control" id="import_file" name="import_file" 
                               accept=".csv,.xlsx,.xls" required>
                        <div class="form-text">
                            <strong>Formats acceptés :</strong> CSV, Excel (.xlsx, .xls)<br>
                            <strong>Taille maximale :</strong> <?php echo (MAX_FILE_SIZE / 1024 / 1024); ?> MB<br>
                            <strong>Encodage recommandé :</strong> UTF-8
                        </div>
                    </div>
                    
                    <div class="alert alert-info">
                        <h6><i class="fas fa-info-circle me-2"></i>Instructions importantes :</h6>
                        <ul class="mb-0">
                            <li>La première ligne doit contenir les en-têtes de colonnes</li>
                            <li>La colonne <strong>"titre"</strong> est obligatoire</li>
                            <li>Si un livre existe déjà (même ISBN ou titre+auteur), les exemplaires seront ajoutés</li>
                            <li>Les catégories inexistantes seront créées automatiquement</li>
                        </ul>
                    </div>
                    
                    <div class="d-flex justify-content-between">
                        <a href="index.php" class="btn btn-secondary">
                            <i class="fas fa-times me-1"></i>
                            Annuler
                        </a>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-upload me-1"></i>
                            Importer les livres
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <!-- Aide et format -->
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-success text-white">
                <h6 class="mb-0">
                    <i class="fas fa-question-circle me-2"></i>
                    Format du fichier
                </h6>
            </div>
            <div class="card-body">
                <h6>Colonnes supportées :</h6>
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Colonne</th>
                                <th>Obligatoire</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><code>titre</code></td>
                                <td><span class="badge bg-danger">Oui</span></td>
                            </tr>
                            <tr>
                                <td><code>auteur</code></td>
                                <td><span class="badge bg-secondary">Non</span></td>
                            </tr>
                            <tr>
                                <td><code>isbn</code></td>
                                <td><span class="badge bg-secondary">Non</span></td>
                            </tr>
                            <tr>
                                <td><code>editeur</code></td>
                                <td><span class="badge bg-secondary">Non</span></td>
                            </tr>
                            <tr>
                                <td><code>annee_publication</code></td>
                                <td><span class="badge bg-secondary">Non</span></td>
                            </tr>
                            <tr>
                                <td><code>categorie</code></td>
                                <td><span class="badge bg-secondary">Non</span></td>
                            </tr>
                            <tr>
                                <td><code>nombre_pages</code></td>
                                <td><span class="badge bg-secondary">Non</span></td>
                            </tr>
                            <tr>
                                <td><code>langue</code></td>
                                <td><span class="badge bg-secondary">Non</span></td>
                            </tr>
                            <tr>
                                <td><code>emplacement</code></td>
                                <td><span class="badge bg-secondary">Non</span></td>
                            </tr>
                            <tr>
                                <td><code>cote</code></td>
                                <td><span class="badge bg-secondary">Non</span></td>
                            </tr>
                            <tr>
                                <td><code>prix_achat</code></td>
                                <td><span class="badge bg-secondary">Non</span></td>
                            </tr>
                            <tr>
                                <td><code>nombre_exemplaires</code></td>
                                <td><span class="badge bg-secondary">Non</span></td>
                            </tr>
                            <tr>
                                <td><code>etat</code></td>
                                <td><span class="badge bg-secondary">Non</span></td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="mt-3">
                    <h6>États possibles :</h6>
                    <div class="d-flex flex-wrap gap-1">
                        <span class="badge bg-success">neuf</span>
                        <span class="badge bg-primary">bon</span>
                        <span class="badge bg-warning">moyen</span>
                        <span class="badge bg-danger">mauvais</span>
                        <span class="badge bg-dark">deteriore</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modèle CSV -->
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-info text-white">
                <h6 class="mb-0">
                    <i class="fas fa-download me-2"></i>
                    Modèle CSV
                </h6>
            </div>
            <div class="card-body">
                <p class="small">Téléchargez un modèle CSV pour vous aider :</p>
                <a href="#" class="btn btn-outline-info btn-sm w-100" onclick="downloadTemplate()">
                    <i class="fas fa-file-csv me-1"></i>
                    Télécharger le modèle
                </a>
            </div>
        </div>

        <!-- Catégories existantes -->
        <?php if (!empty($categories)): ?>
        <div class="card shadow-sm">
            <div class="card-header bg-warning text-dark">
                <h6 class="mb-0">
                    <i class="fas fa-tags me-2"></i>
                    Catégories existantes
                </h6>
            </div>
            <div class="card-body">
                <div class="d-flex flex-wrap gap-1">
                    <?php foreach ($categories as $category): ?>
                        <span class="badge bg-secondary"><?php echo htmlspecialchars($category['nom']); ?></span>
                    <?php endforeach; ?>
                </div>
                <small class="text-muted mt-2 d-block">
                    Utilisez ces noms exacts ou créez de nouvelles catégories.
                </small>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Exemples et conseils -->
<div class="row mt-4">
    <div class="col-12">
        <div class="card shadow-sm">
            <div class="card-header bg-light">
                <h6 class="mb-0">
                    <i class="fas fa-lightbulb me-2"></i>
                    Exemples et conseils
                </h6>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <h6>Exemple de fichier CSV :</h6>
                        <pre class="bg-light p-3 rounded"><code>titre,auteur,isbn,editeur,annee_publication,categorie,nombre_exemplaires
"Les Misérables","Victor Hugo","978-2-07-041119-1","Gallimard",1862,"Romans",2
"Mathématiques 6ème","Jean Dupont","978-2-01-125456-7","Hachette",2023,"Mathématiques",5
"Histoire du Congo","Marie Kabila","","Editions Universitaires",2020,"Histoire",1</code></pre>
                    </div>

                    <div class="col-md-6">
                        <h6>Conseils d'importation :</h6>
                        <ul class="small">
                            <li><strong>Encodage :</strong> Utilisez UTF-8 pour les caractères spéciaux</li>
                            <li><strong>Séparateur :</strong> Utilisez la virgule (,) comme séparateur</li>
                            <li><strong>Guillemets :</strong> Entourez les textes contenant des virgules de guillemets</li>
                            <li><strong>Dates :</strong> Format YYYY-MM-DD ou DD/MM/YYYY</li>
                            <li><strong>Nombres :</strong> Utilisez le point (.) pour les décimales</li>
                            <li><strong>Doublons :</strong> Les livres existants verront leurs exemplaires augmentés</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.table th, .table td {
    padding: 0.5rem;
    font-size: 0.875rem;
}

pre code {
    font-size: 0.8rem;
    line-height: 1.4;
}

.badge {
    font-size: 0.75rem;
}
</style>

<script>
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

// Télécharger le modèle CSV
function downloadTemplate() {
    const csvContent = `titre,auteur,isbn,editeur,annee_publication,categorie,nombre_pages,langue,emplacement,cote,prix_achat,nombre_exemplaires,etat,resume,notes
"Les Misérables","Victor Hugo","978-2-07-041119-1","Gallimard",1862,"Romans",1500,"Français","A1-001","ROM-001",25.50,2,"bon","Chef-d'œuvre de la littérature française","Édition reliée"
"Mathématiques 6ème","Jean Dupont","978-2-01-125456-7","Hachette",2023,"Mathématiques",250,"Français","B2-015","MAT-6-001",18.90,5,"neuf","Manuel scolaire conforme au programme","Nouvelle édition"
"Histoire du Congo","Marie Kabila","","Editions Universitaires",2020,"Histoire",320,"Français","C1-008","HIS-CON-001",22.00,1,"bon","Histoire contemporaine du Congo","Ouvrage de référence"`;

    const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');
    const url = URL.createObjectURL(blob);
    link.setAttribute('href', url);
    link.setAttribute('download', 'modele_import_livres.csv');
    link.style.visibility = 'hidden';
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}

// Preview du fichier sélectionné
document.getElementById('import_file').addEventListener('change', function(e) {
    const file = e.target.files[0];
    if (file) {
        const fileSize = (file.size / 1024 / 1024).toFixed(2);
        const fileInfo = document.createElement('div');
        fileInfo.className = 'mt-2 small text-muted';
        fileInfo.innerHTML = `
            <i class="fas fa-file me-1"></i>
            <strong>${file.name}</strong> (${fileSize} MB)
        `;

        // Supprimer l'ancien aperçu s'il existe
        const existingInfo = e.target.parentNode.querySelector('.file-info');
        if (existingInfo) {
            existingInfo.remove();
        }

        fileInfo.className += ' file-info';
        e.target.parentNode.appendChild(fileInfo);
    }
});
</script>

<?php include '../../../includes/footer.php'; ?>
