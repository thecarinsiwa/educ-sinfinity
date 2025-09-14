-- Migration pour améliorer les tables de paramètres
-- Date: 2025-01-09

-- 1. Améliorer la table system_settings
-- Ajouter des colonnes manquantes et améliorer la structure
ALTER TABLE `system_settings` 
ADD COLUMN IF NOT EXISTS `is_public` TINYINT(1) DEFAULT 0 COMMENT 'Paramètre visible par tous les utilisateurs',
ADD COLUMN IF NOT EXISTS `is_required` TINYINT(1) DEFAULT 0 COMMENT 'Paramètre obligatoire',
ADD COLUMN IF NOT EXISTS `validation_rule` VARCHAR(255) DEFAULT NULL COMMENT 'Règle de validation (regex, min, max, etc.)',
ADD COLUMN IF NOT EXISTS `default_value` TEXT DEFAULT NULL COMMENT 'Valeur par défaut',
ADD COLUMN IF NOT EXISTS `help_text` TEXT DEFAULT NULL COMMENT 'Texte d\'aide pour l\'utilisateur',
ADD COLUMN IF NOT EXISTS `sort_order` INT DEFAULT 0 COMMENT 'Ordre d\'affichage dans l\'interface',
ADD COLUMN IF NOT EXISTS `group_name` VARCHAR(100) DEFAULT 'general' COMMENT 'Groupe de paramètres',
ADD COLUMN IF NOT EXISTS `is_file` TINYINT(1) DEFAULT 0 COMMENT 'Indique si c\'est un fichier uploadé',
ADD COLUMN IF NOT EXISTS `file_types` VARCHAR(255) DEFAULT NULL COMMENT 'Types de fichiers acceptés (ex: jpg,png,gif)',
ADD COLUMN IF NOT EXISTS `max_file_size` INT DEFAULT NULL COMMENT 'Taille maximale du fichier en KB';

-- Modifier le type de la colonne type pour inclure plus d'options
ALTER TABLE `system_settings` 
MODIFY COLUMN `type` ENUM('text','number','boolean','email','url','textarea','select','file','color','date','time','datetime','password','json') 
DEFAULT 'text';

-- 2. Améliorer la table etablissements pour gérer plusieurs établissements
ALTER TABLE `etablissements` 
ADD COLUMN IF NOT EXISTS `slogan` VARCHAR(255) DEFAULT NULL COMMENT 'Slogan de l\'établissement',
ADD COLUMN IF NOT EXISTS `site_web` VARCHAR(255) DEFAULT NULL COMMENT 'Site web officiel',
ADD COLUMN IF NOT EXISTS `logo` VARCHAR(255) DEFAULT NULL COMMENT 'Chemin vers le logo',
ADD COLUMN IF NOT EXISTS `favicon` VARCHAR(255) DEFAULT NULL COMMENT 'Chemin vers la favicon',
ADD COLUMN IF NOT EXISTS `type_enseignement` ENUM('maternelle','primaire','secondaire','superieur','mixte') DEFAULT 'mixte' COMMENT 'Type d\'enseignement',
ADD COLUMN IF NOT EXISTS `directeur_nom` VARCHAR(100) DEFAULT NULL COMMENT 'Nom du directeur',
ADD COLUMN IF NOT EXISTS `directeur_prenom` VARCHAR(100) DEFAULT NULL COMMENT 'Prénom du directeur',
ADD COLUMN IF NOT EXISTS `directeur_telephone` VARCHAR(20) DEFAULT NULL COMMENT 'Téléphone du directeur',
ADD COLUMN IF NOT EXISTS `directeur_email` VARCHAR(100) DEFAULT NULL COMMENT 'Email du directeur',
ADD COLUMN IF NOT EXISTS `annee_creation` YEAR DEFAULT NULL COMMENT 'Année de création',
ADD COLUMN IF NOT EXISTS `numero_agrement` VARCHAR(100) DEFAULT NULL COMMENT 'Numéro d\'agrément officiel',
ADD COLUMN IF NOT EXISTS `is_active` TINYINT(1) DEFAULT 1 COMMENT 'Établissement actif',
ADD COLUMN IF NOT EXISTS `is_principal` TINYINT(1) DEFAULT 0 COMMENT 'Établissement principal',
ADD COLUMN IF NOT EXISTS `couleur_principale` VARCHAR(7) DEFAULT '#007bff' COMMENT 'Couleur principale (hex)',
ADD COLUMN IF NOT EXISTS `couleur_secondaire` VARCHAR(7) DEFAULT '#6c757d' COMMENT 'Couleur secondaire (hex)',
ADD COLUMN IF NOT EXISTS `theme` VARCHAR(50) DEFAULT 'default' COMMENT 'Thème de l\'interface',
ADD COLUMN IF NOT EXISTS `timezone` VARCHAR(50) DEFAULT 'Africa/Kinshasa' COMMENT 'Fuseau horaire',
ADD COLUMN IF NOT EXISTS `langue` VARCHAR(5) DEFAULT 'fr' COMMENT 'Langue par défaut',
ADD COLUMN IF NOT EXISTS `devise` VARCHAR(10) DEFAULT 'FC' COMMENT 'Devise utilisée',
ADD COLUMN IF NOT EXISTS `updated_at` TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
ADD COLUMN IF NOT EXISTS `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP;

-- Renommer la colonne directeur en directeur_nom pour plus de clarté
ALTER TABLE `etablissements` 
CHANGE COLUMN `directeur` `directeur_nom` VARCHAR(100) DEFAULT NULL COMMENT 'Nom du directeur';

-- 3. Créer une table pour les paramètres de cache
CREATE TABLE IF NOT EXISTS `settings_cache` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `cache_key` VARCHAR(255) NOT NULL,
  `cache_value` LONGTEXT NOT NULL,
  `expires_at` TIMESTAMP NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `cache_key` (`cache_key`),
  KEY `idx_expires_at` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Insérer les paramètres par défaut améliorés
INSERT IGNORE INTO `system_settings` (`cle`, `valeur`, `description`, `type`, `categorie`, `is_required`, `default_value`, `help_text`, `sort_order`, `group_name`) VALUES
-- Général
('app_name', 'École Sinfinity', 'Nom de l\'application', 'text', 'general', 1, 'École Sinfinity', 'Nom affiché dans l\'interface', 1, 'general'),
('app_version', '1.0.0', 'Version de l\'application', 'text', 'general', 0, '1.0.0', 'Version actuelle du système', 2, 'general'),
('current_academic_year', '', 'Année scolaire en cours', 'select', 'general', 1, '', 'Sélectionnez l\'année scolaire active', 3, 'general'),
('timezone', 'Africa/Kinshasa', 'Fuseau horaire', 'select', 'general', 1, 'Africa/Kinshasa', 'Fuseau horaire du système', 4, 'general'),
('language', 'fr', 'Langue par défaut', 'select', 'general', 1, 'fr', 'Langue de l\'interface', 5, 'general'),
('currency', 'FC', 'Devise', 'select', 'general', 1, 'FC', 'Devise utilisée pour les montants', 6, 'general'),

-- Établissement
('school_name', 'École Sinfinity', 'Nom de l\'établissement', 'text', 'school', 1, 'École Sinfinity', 'Nom officiel de l\'établissement', 1, 'school'),
('school_slogan', 'Excellence et Innovation', 'Slogan de l\'établissement', 'text', 'school', 0, '', 'Slogan ou devise de l\'école', 2, 'school'),
('school_address', 'Avenue de la Paix, Kinshasa', 'Adresse complète', 'textarea', 'school', 1, '', 'Adresse postale complète', 3, 'school'),
('school_phone', '+243 123 456 789', 'Téléphone principal', 'text', 'school', 0, '', 'Numéro de téléphone principal', 4, 'school'),
('school_email', 'contact@ecole-sinfinity.cd', 'Email de contact', 'email', 'school', 0, '', 'Adresse email de contact', 5, 'school'),
('school_website', 'https://www.ecole-sinfinity.cd', 'Site web', 'url', 'school', 0, '', 'Site web officiel', 6, 'school'),

-- Apparence
('logo', '', 'Logo de l\'établissement', 'file', 'appearance', 0, '', 'Logo principal (recommandé: 200x200px)', 1, 'appearance'),
('favicon', '', 'Favicon', 'file', 'appearance', 0, '', 'Icône du navigateur (16x16px ou 32x32px)', 2, 'appearance'),
('primary_color', '#007bff', 'Couleur principale', 'color', 'appearance', 0, '#007bff', 'Couleur principale de l\'interface', 3, 'appearance'),
('secondary_color', '#6c757d', 'Couleur secondaire', 'color', 'appearance', 0, '#6c757d', 'Couleur secondaire de l\'interface', 4, 'appearance'),
('theme', 'default', 'Thème de l\'interface', 'select', 'appearance', 0, 'default', 'Thème visuel de l\'application', 5, 'appearance'),

-- Communication
('admin_email', 'admin@ecole-sinfinity.cd', 'Email administrateur', 'email', 'communication', 1, '', 'Email pour les notifications système', 1, 'communication'),
('enable_email', '1', 'Activer les emails', 'boolean', 'communication', 0, '1', 'Permettre l\'envoi d\'emails', 2, 'communication'),
('enable_sms', '1', 'Activer les SMS', 'boolean', 'communication', 0, '1', 'Permettre l\'envoi de SMS', 3, 'communication'),
('enable_notifications', '1', 'Activer les notifications', 'boolean', 'communication', 0, '1', 'Permettre les notifications push', 4, 'communication'),
('whatsapp_number', '', 'Numéro WhatsApp', 'text', 'communication', 0, '', 'Numéro WhatsApp pour la communication', 5, 'communication'),

-- Sécurité & Maintenance
('maintenance_mode', '0', 'Mode maintenance', 'boolean', 'security', 0, '0', 'Activer le mode maintenance', 1, 'security'),
('backup_retention_days', '30', 'Rétention des sauvegardes (jours)', 'number', 'security', 0, '30', 'Nombre de jours de conservation des sauvegardes', 2, 'security'),
('session_lifetime', '7200', 'Durée de session (secondes)', 'number', 'security', 0, '7200', 'Durée avant expiration de la session', 3, 'security'),
('max_login_attempts', '5', 'Tentatives de connexion max', 'number', 'security', 0, '5', 'Nombre maximum de tentatives de connexion', 4, 'security'),
('password_min_length', '8', 'Longueur minimale du mot de passe', 'number', 'security', 0, '8', 'Longueur minimale requise pour les mots de passe', 5, 'security');

-- 5. Insérer un établissement par défaut s'il n'en existe pas
INSERT IGNORE INTO `etablissements` (`nom`, `adresse`, `telephone`, `email`, `directeur_nom`, `is_principal`, `is_active`) 
VALUES ('École Sinfinity', 'Avenue de la Paix, Kinshasa', '+243 123 456 789', 'contact@ecole-sinfinity.cd', 'Directeur Principal', 1, 1);

-- 6. Créer des index pour améliorer les performances
CREATE INDEX `idx_system_settings_categorie` ON `system_settings` (`categorie`);
CREATE INDEX `idx_system_settings_group` ON `system_settings` (`group_name`);
CREATE INDEX `idx_etablissements_active` ON `etablissements` (`is_active`);
CREATE INDEX `idx_etablissements_principal` ON `etablissements` (`is_principal`);
