-- Migration: Création de la table parametres_admission
-- Date: 2024
-- Description: Table pour stocker les paramètres généraux d'admission

CREATE TABLE IF NOT EXISTS `parametres_admission` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `annee_scolaire_id` int(11) NOT NULL,
  `delai_traitement` int(11) NOT NULL DEFAULT 7 COMMENT 'Délai de traitement en jours',
  `auto_refus` int(11) NOT NULL DEFAULT 30 COMMENT 'Délai avant refus automatique en jours',
  `notifications_email` tinyint(1) NOT NULL DEFAULT 1 COMMENT 'Notifications par email activées',
  `validation_auto` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Validation automatique activée',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_annee_scolaire` (`annee_scolaire_id`),
  KEY `fk_parametres_admission_annee_scolaire` (`annee_scolaire_id`),
  CONSTRAINT `fk_parametres_admission_annee_scolaire` FOREIGN KEY (`annee_scolaire_id`) REFERENCES `annees_scolaires` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Paramètres généraux pour les admissions';

-- Insérer les paramètres par défaut pour l'année scolaire active
INSERT IGNORE INTO `parametres_admission` (
    `annee_scolaire_id`, 
    `delai_traitement`, 
    `auto_refus`, 
    `notifications_email`, 
    `validation_auto`
) 
SELECT 
    id as annee_scolaire_id,
    7 as delai_traitement,
    30 as auto_refus,
    1 as notifications_email,
    0 as validation_auto
FROM `annees_scolaires` 
WHERE `id` = (SELECT id FROM `annees_scolaires` ORDER BY `id` DESC LIMIT 1);
