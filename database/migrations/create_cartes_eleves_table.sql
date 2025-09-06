-- Migration pour créer la table carte_eleve
-- Module Carte d'Élève - Application de gestion scolaire

CREATE TABLE IF NOT EXISTS `carte_eleve` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `eleve_id` int(11) NOT NULL,
  `annee_scolaire_id` int(11) NOT NULL,
  `numero_carte` varchar(50) NOT NULL,
  `qr_code` text NOT NULL,
  `qr_data` text NOT NULL COMMENT 'Données encodées dans le QR code',
  `statut` enum('active','expiree','suspendue','archivée') DEFAULT 'active',
  `date_generation` datetime NOT NULL,
  `date_expiration` datetime NOT NULL,
  `date_archivage` datetime NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_carte_annee` (`eleve_id`, `annee_scolaire_id`),
  UNIQUE KEY `unique_numero_carte` (`numero_carte`),
  KEY `idx_eleve_id` (`eleve_id`),
  KEY `idx_annee_scolaire` (`annee_scolaire_id`),
  KEY `idx_statut` (`statut`),
  KEY `idx_qr_code` (`qr_code`(100)),
  CONSTRAINT `fk_carte_eleve` FOREIGN KEY (`eleve_id`) REFERENCES `eleves` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_carte_annee_scolaire` FOREIGN KEY (`annee_scolaire_id`) REFERENCES `annees_scolaires` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table pour l'historique des cartes (archivage)
CREATE TABLE IF NOT EXISTS `carte_eleve_historique` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `carte_id` int(11) NOT NULL,
  `eleve_id` int(11) NOT NULL,
  `annee_scolaire_id` int(11) NOT NULL,
  `numero_carte` varchar(50) NOT NULL,
  `qr_code` text NOT NULL,
  `statut` enum('active','expiree','suspendue','archivée') NOT NULL,
  `date_generation` datetime NOT NULL,
  `date_expiration` datetime NOT NULL,
  `date_archivage` datetime NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_eleve_id` (`eleve_id`),
  KEY `idx_annee_scolaire` (`annee_scolaire_id`),
  KEY `idx_carte_id` (`carte_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table pour les paramètres de design des cartes
CREATE TABLE IF NOT EXISTS `parametres_cartes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nom_ecole` varchar(255) NOT NULL DEFAULT 'École Sinfinity',
  `logo_ecole` varchar(500) NULL,
  `couleur_principale` varchar(7) NOT NULL DEFAULT '#1e40af',
  `couleur_secondaire` varchar(7) NOT NULL DEFAULT '#3b82f6',
  `couleur_texte` varchar(7) NOT NULL DEFAULT '#1f2937',
  `format_carte` enum('pvc','pdf') NOT NULL DEFAULT 'pdf',
  `dimensions` varchar(20) NOT NULL DEFAULT '85.6x54mm',
  `qr_code_size` int(11) NOT NULL DEFAULT 100,
  `include_photo` tinyint(1) NOT NULL DEFAULT 1,
  `include_qr_code` tinyint(1) NOT NULL DEFAULT 1,
  `include_barcode` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insérer les paramètres par défaut
INSERT INTO `parametres_cartes` (`nom_ecole`, `couleur_principale`, `couleur_secondaire`, `couleur_texte`) 
VALUES ('École Sinfinity', '#1e40af', '#3b82f6', '#1f2937')
ON DUPLICATE KEY UPDATE `nom_ecole` = VALUES(`nom_ecole`);

-- Table pour les logs de scan des cartes (pointage)
CREATE TABLE IF NOT EXISTS `logs_scan_carte` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `carte_id` int(11) NOT NULL,
  `eleve_id` int(11) NOT NULL,
  `type_scan` enum('presence','solde','autre') NOT NULL,
  `ip_address` varchar(45) NULL,
  `user_agent` text NULL,
  `donnees_scan` text NULL COMMENT 'Données extraites du QR code',
  `resultat` text NULL COMMENT 'Résultat de l\'action effectuée',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_carte_id` (`carte_id`),
  KEY `idx_eleve_id` (`eleve_id`),
  KEY `idx_type_scan` (`type_scan`),
  KEY `idx_created_at` (`created_at`),
  CONSTRAINT `fk_logs_scan_carte` FOREIGN KEY (`carte_id`) REFERENCES `carte_eleve` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_logs_scan_eleve` FOREIGN KEY (`eleve_id`) REFERENCES `eleves` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
