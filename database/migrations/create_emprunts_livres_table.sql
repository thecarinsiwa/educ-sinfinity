-- Migration pour créer la table emprunts_livres
-- Application de gestion scolaire - République Démocratique du Congo

-- Créer la table emprunts_livres
CREATE TABLE IF NOT EXISTS `emprunts_livres` (
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
    KEY `idx_rendu_par` (`rendu_par`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Créer la table reservations_livres
CREATE TABLE IF NOT EXISTS `reservations_livres` (
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
    KEY `idx_traite_par` (`traite_par`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Créer la table parametres_bibliotheque
CREATE TABLE IF NOT EXISTS `parametres_bibliotheque` (
    `id` int NOT NULL AUTO_INCREMENT,
    `cle` varchar(100) NOT NULL,
    `valeur` text NOT NULL,
    `description` text,
    `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_cle` (`cle`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insérer les paramètres par défaut
INSERT IGNORE INTO `parametres_bibliotheque` (`cle`, `valeur`, `description`) VALUES
('duree_emprunt_eleve', '14', 'Durée d\'emprunt par défaut pour les élèves (en jours)'),
('duree_emprunt_personnel', '21', 'Durée d\'emprunt par défaut pour le personnel (en jours)'),
('max_emprunts_eleve', '3', 'Nombre maximum d\'emprunts simultanés pour un élève'),
('max_emprunts_personnel', '5', 'Nombre maximum d\'emprunts simultanés pour le personnel'),
('amende_retard', '100', 'Montant de l\'amende par jour de retard (en FC)'),
('duree_reservation', '7', 'Durée de validité d\'une réservation (en jours)');
