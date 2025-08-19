<?php
/**
 * Script de création de la table emprunts_livres
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
    // Vérifier si la table existe déjà
    $table_exists = $database->query("SHOW TABLES LIKE 'emprunts_livres'")->fetch();
    
    if (!$table_exists) {
        // Créer la table emprunts_livres
        $database->execute("
            CREATE TABLE `emprunts_livres` (
                `id` int NOT NULL AUTO_INCREMENT,
                `livre_id` int NOT NULL,
                `emprunteur_type` enum('eleve','personnel') NOT NULL,
                `emprunteur_id` int NOT NULL,
                `date_emprunt` date NOT NULL,
                `date_retour_prevue` date NOT NULL,
                `date_retour_effective` date NULL,
                `duree_jours` int NOT NULL DEFAULT 14,
                `status` enum('en_cours','rendu','perdu','en_retard') NOT NULL DEFAULT 'en_cours',
                `notes_emprunt` text,
                `notes_retour` text,
                `traite_par` int NULL,
                `rendu_par` int NULL,
                `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                KEY `idx_livre_id` (`livre_id`),
                KEY `idx_emprunteur` (`emprunteur_type`, `emprunteur_id`),
                KEY `idx_status` (`status`),
                KEY `idx_date_retour` (`date_retour_prevue`),
                KEY `idx_traite_par` (`traite_par`),
                KEY `idx_rendu_par` (`rendu_par`),
                CONSTRAINT `fk_emprunts_livres_livre` FOREIGN KEY (`livre_id`) REFERENCES `livres` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT `fk_emprunts_livres_traite_par` FOREIGN KEY (`traite_par`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
                CONSTRAINT `fk_emprunts_livres_rendu_par` FOREIGN KEY (`rendu_par`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        $success_messages[] = "Table emprunts_livres créée avec succès";
    } else {
        $success_messages[] = "Table emprunts_livres existe déjà";
    }
    
    // Vérifier si la table reservations_livres existe
    $table_reservations_exists = $database->query("SHOW TABLES LIKE 'reservations_livres'")->fetch();
    
    if (!$table_reservations_exists) {
        // Créer la table reservations_livres
        $database->execute("
            CREATE TABLE `reservations_livres` (
                `id` int NOT NULL AUTO_INCREMENT,
                `livre_id` int NOT NULL,
                `reserver_type` enum('eleve','personnel') NOT NULL,
                `reserver_id` int NOT NULL,
                `date_reservation` date NOT NULL,
                `date_expiration` date NOT NULL,
                `status` enum('active','expiree','convertie','annulee') NOT NULL DEFAULT 'active',
                `notes` text,
                `traite_par` int NULL,
                `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                KEY `idx_livre_id` (`livre_id`),
                KEY `idx_reserver` (`reserver_type`, `reserver_id`),
                KEY `idx_status` (`status`),
                KEY `idx_date_expiration` (`date_expiration`),
                KEY `idx_traite_par` (`traite_par`),
                CONSTRAINT `fk_reservations_livres_livre` FOREIGN KEY (`livre_id`) REFERENCES `livres` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT `fk_reservations_livres_traite_par` FOREIGN KEY (`traite_par`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        $success_messages[] = "Table reservations_livres créée avec succès";
    } else {
        $success_messages[] = "Table reservations_livres existe déjà";
    }
    
    // Vérifier si la table parametres_bibliotheque existe
    $table_parametres_exists = $database->query("SHOW TABLES LIKE 'parametres_bibliotheque'")->fetch();
    
    if (!$table_parametres_exists) {
        // Créer la table parametres_bibliotheque
        $database->execute("
            CREATE TABLE `parametres_bibliotheque` (
                `id` int NOT NULL AUTO_INCREMENT,
                `cle` varchar(100) NOT NULL,
                `valeur` text NOT NULL,
                `description` text,
                `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uk_cle` (`cle`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        $success_messages[] = "Table parametres_bibliotheque créée avec succès";
        
        // Insérer les paramètres par défaut
        $parametres_defaut = [
            ['duree_emprunt_eleve', '14', 'Durée d\'emprunt par défaut pour les élèves (en jours)'],
            ['duree_emprunt_personnel', '21', 'Durée d\'emprunt par défaut pour le personnel (en jours)'],
            ['max_emprunts_eleve', '3', 'Nombre maximum d\'emprunts simultanés pour un élève'],
            ['max_emprunts_personnel', '5', 'Nombre maximum d\'emprunts simultanés pour le personnel'],
            ['amende_retard', '100', 'Montant de l\'amende par jour de retard (en FC)'],
            ['duree_reservation', '7', 'Durée de validité d\'une réservation (en jours)']
        ];
        
        foreach ($parametres_defaut as $parametre) {
            $database->execute(
                "INSERT INTO parametres_bibliotheque (cle, valeur, description) VALUES (?, ?, ?)",
                $parametre
            );
        }
        $success_messages[] = "Paramètres par défaut ajoutés";
    } else {
        $success_messages[] = "Table parametres_bibliotheque existe déjà";
    }
    
} catch (Exception $e) {
    $error_messages[] = "Erreur lors de la création : " . $e->getMessage();
}

$page_title = "Création des tables bibliothèque";
include '../../../includes/header.php';
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
        <h1 class="h2">
            <i class="fas fa-database me-2"></i>
            Création des tables bibliothèque
        </h1>
        <div class="btn-toolbar mb-2 mb-md-0">
            <a href="../" class="btn btn-secondary">
                <i class="fas fa-arrow-left me-2"></i>
                Retour à la bibliothèque
            </a>
        </div>
    </div>

    <?php if (!empty($success_messages)): ?>
        <div class="alert alert-success">
            <h5><i class="fas fa-check-circle me-2"></i>Opération réussie</h5>
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
                    <p>Ce script crée les tables nécessaires pour le module bibliothèque :</p>
                    <ul>
                        <li><strong>emprunts_livres</strong> : Gestion des emprunts de livres</li>
                        <li><strong>reservations_livres</strong> : Gestion des réservations</li>
                        <li><strong>parametres_bibliotheque</strong> : Paramètres de configuration</li>
                    </ul>
                    <p>Les tables incluent toutes les contraintes de clé étrangère et les index nécessaires.</p>
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
                        Nouvel emprunt
                    </a>
                    <br>
                    <a href="index.php" class="btn btn-info mb-2">
                        <i class="fas fa-list me-2"></i>
                        Gestion des emprunts
                    </a>
                    <br>
                    <a href="../books/" class="btn btn-success">
                        <i class="fas fa-book me-2"></i>
                        Gestion des livres
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../../../includes/footer.php'; ?>
