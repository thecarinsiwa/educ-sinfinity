<?php
/**
 * Script de mise à jour de la base de données pour le module bibliothèque
 * Application de gestion scolaire - République Démocratique du Congo
 */

require_once '../../../config/config.php';
require_once '../../../config/database.php';
require_once '../../../includes/functions.php';

// Vérifier l'authentification et les permissions
requireLogin();
if (!checkPermission('admin')) {
    showMessage('error', 'Accès refusé. Permissions administrateur requises.');
    redirectTo('../../../index.php');
}

$success_messages = [];
$error_messages = [];

try {
    // Vérifier si les colonnes existent déjà
    $columns_check = $database->query("SHOW COLUMNS FROM livres LIKE 'nombre_pages'")->fetch();
    
    if (!$columns_check) {
        // Ajouter les nouvelles colonnes une par une pour éviter les erreurs de syntaxe
        $columns_to_add = [
            "ADD COLUMN `nombre_pages` int DEFAULT NULL AFTER `categorie`",
            "ADD COLUMN `langue` varchar(50) DEFAULT 'Français' AFTER `nombre_pages`",
            "ADD COLUMN `resume` text AFTER `langue`",
            "ADD COLUMN `cote` varchar(50) DEFAULT NULL AFTER `resume`",
            "ADD COLUMN `prix_achat` decimal(10,2) DEFAULT NULL AFTER `cote`",
            "ADD COLUMN `date_acquisition` date DEFAULT NULL AFTER `prix_achat`",
            "ADD COLUMN `etat` enum('excellent','bon','moyen','mauvais') DEFAULT 'bon' AFTER `date_acquisition`",
            "ADD COLUMN `notes` text AFTER `etat`",
            "ADD COLUMN `categorie_id` int DEFAULT NULL AFTER `categorie`",
            "ADD COLUMN `exemplaires_disponibles` int DEFAULT 1 AFTER `nombre_disponibles`",
            "ADD COLUMN `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP AFTER `created_at`"
        ];
        
        foreach ($columns_to_add as $column_definition) {
            try {
                $database->execute("ALTER TABLE `livres` $column_definition");
            } catch (Exception $e) {
                // Ignorer si la colonne existe déjà
                if (strpos($e->getMessage(), 'Duplicate column name') === false) {
                    throw $e;
                }
            }
        }
        $success_messages[] = "Nouvelles colonnes ajoutées à la table livres";
    } else {
        $success_messages[] = "Les colonnes existent déjà dans la table livres";
    }
    
    // Créer la table des catégories si elle n'existe pas
    $database->execute("
        CREATE TABLE IF NOT EXISTS `categories_livres` (
            `id` int NOT NULL AUTO_INCREMENT,
            `nom` varchar(100) NOT NULL,
            `description` text,
            `couleur` varchar(7) DEFAULT '#007bff',
            `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    $success_messages[] = "Table categories_livres créée/vérifiée";
    
    // Insérer les catégories par défaut
    $default_categories = [
        ['Littérature', 'Romans, nouvelles, poésie', '#28a745'],
        ['Sciences', 'Mathématiques, physique, chimie, biologie', '#17a2b8'],
        ['Histoire', 'Histoire générale et spécialisée', '#ffc107'],
        ['Géographie', 'Géographie physique et humaine', '#6f42c1'],
        ['Langues', 'Manuels de langues étrangères', '#fd7e14'],
        ['Arts', 'Musique, peinture, sculpture', '#e83e8c'],
        ['Technologie', 'Informatique, électronique', '#20c997'],
        ['Philosophie', 'Philosophie et éthique', '#6c757d'],
        ['Religion', 'Textes religieux et spirituels', '#495057'],
        ['Autres', 'Autres catégories', '#dee2e6']
    ];
    
    foreach ($default_categories as $category) {
        $existing = $database->query(
            "SELECT id FROM categories_livres WHERE nom = ?",
            [$category[0]]
        )->fetch();
        
        if (!$existing) {
            $database->execute(
                "INSERT INTO categories_livres (nom, description, couleur) VALUES (?, ?, ?)",
                $category
            );
        }
    }
    $success_messages[] = "Catégories par défaut ajoutées";
    
    // Ajouter les index (ignorer les erreurs si ils existent déjà)
    $indexes_to_add = [
        ['idx_categorie_id', 'categorie_id'],
        ['idx_isbn', 'isbn'],
        ['idx_titre', 'titre'],
        ['idx_auteur', 'auteur'],
        ['idx_status', 'status']
    ];
    
    foreach ($indexes_to_add as $index) {
        try {
            // Vérifier si l'index existe déjà
            $index_exists = $database->query("SHOW INDEX FROM livres WHERE Key_name = ?", [$index[0]])->fetch();
            if (!$index_exists) {
                $database->execute("ALTER TABLE `livres` ADD INDEX `{$index[0]}` (`{$index[1]}`)");
            }
        } catch (Exception $e) {
            // Ignorer les erreurs d'index existants
            if (strpos($e->getMessage(), 'Duplicate key name') === false) {
                $error_messages[] = "Erreur lors de l'ajout de l'index {$index[0]}: " . $e->getMessage();
            }
        }
    }
    
    $success_messages[] = "Index vérifiés/ajoutés à la table livres";
    
    // Ajouter la contrainte de clé étrangère (ignorer si elle existe déjà)
    try {
        // Vérifier si la contrainte existe déjà
        $constraint_exists = $database->query("
            SELECT CONSTRAINT_NAME 
            FROM information_schema.TABLE_CONSTRAINTS 
            WHERE TABLE_SCHEMA = DATABASE() 
            AND TABLE_NAME = 'livres' 
            AND CONSTRAINT_NAME = 'fk_livres_categorie'
        ")->fetch();
        
        if (!$constraint_exists) {
            $database->execute("
                ALTER TABLE `livres` 
                ADD CONSTRAINT `fk_livres_categorie` 
                FOREIGN KEY (`categorie_id`) REFERENCES `categories_livres` (`id`) 
                ON DELETE SET NULL ON UPDATE CASCADE
            ");
            $success_messages[] = "Contrainte de clé étrangère ajoutée";
        } else {
            $success_messages[] = "Contrainte de clé étrangère déjà présente";
        }
    } catch (Exception $e) {
        // Ignorer les erreurs de contrainte existante
        if (strpos($e->getMessage(), 'Duplicate foreign key constraint name') === false) {
            $error_messages[] = "Erreur lors de l'ajout de la contrainte de clé étrangère: " . $e->getMessage();
        } else {
            $success_messages[] = "Contrainte de clé étrangère déjà présente";
        }
    }
    
    // Mettre à jour les livres existants
    $database->execute("
        UPDATE livres 
        SET exemplaires_disponibles = nombre_disponibles 
        WHERE exemplaires_disponibles IS NULL OR exemplaires_disponibles = 0
    ");
    
    // Mettre à jour la date d'acquisition pour les livres existants
    $database->execute("
        UPDATE livres 
        SET date_acquisition = created_at 
        WHERE date_acquisition IS NULL
    ");
    
    $success_messages[] = "Livres existants mis à jour";
    
} catch (Exception $e) {
    $error_messages[] = "Erreur lors de la mise à jour : " . $e->getMessage();
}

$page_title = "Mise à jour de la base de données";
include '../../../includes/header.php';
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
        <h1 class="h2">
            <i class="fas fa-database me-2"></i>
            Mise à jour de la base de données
        </h1>
        <div class="btn-toolbar mb-2 mb-md-0">
            <a href="index.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left me-2"></i>
                Retour à la bibliothèque
            </a>
        </div>
    </div>

    <?php if (!empty($success_messages)): ?>
        <div class="alert alert-success">
            <h5><i class="fas fa-check-circle me-2"></i>Mise à jour réussie</h5>
            <ul class="mb-0">
                <?php foreach ($success_messages as $message): ?>
                    <li><?php echo htmlspecialchars($message); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <?php if (!empty($error_messages)): ?>
        <div class="alert alert-danger">
            <h5><i class="fas fa-exclamation-triangle me-2"></i>Erreurs</h5>
            <ul class="mb-0">
                <?php foreach ($error_messages as $message): ?>
                    <li><?php echo htmlspecialchars($message); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <div class="row">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5><i class="fas fa-info-circle me-2"></i>Informations</h5>
                </div>
                <div class="card-body">
                    <p>Cette page met à jour automatiquement la structure de la base de données pour le module bibliothèque.</p>
                    <p>Les modifications incluent :</p>
                    <ul>
                        <li>Ajout de nouvelles colonnes à la table <code>livres</code></li>
                        <li>Création de la table <code>categories_livres</code></li>
                        <li>Ajout d'index pour améliorer les performances</li>
                        <li>Ajout de contraintes de clé étrangère</li>
                    </ul>
                </div>
            </div>
        </div>
        
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5><i class="fas fa-list me-2"></i>Actions</h5>
                </div>
                <div class="card-body">
                    <a href="add.php" class="btn btn-primary mb-2">
                        <i class="fas fa-plus me-2"></i>
                        Ajouter un livre
                    </a>
                    <br>
                    <a href="index.php" class="btn btn-info mb-2">
                        <i class="fas fa-book me-2"></i>
                        Voir le catalogue
                    </a>
                    <br>
                    <a href="categories.php" class="btn btn-success">
                        <i class="fas fa-tags me-2"></i>
                        Gérer les catégories
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../../../includes/footer.php'; ?>
