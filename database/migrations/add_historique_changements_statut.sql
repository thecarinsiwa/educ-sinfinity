-- Migration: Ajout de la table historique_changements_statut
-- Date: 2025-01-27
-- Description: Table pour tracer l'historique des changements de statut des inscriptions

-- Création de la table historique_changements_statut
CREATE TABLE IF NOT EXISTS `historique_changements_statut` (
  `id` int NOT NULL AUTO_INCREMENT,
  `inscription_id` int NOT NULL COMMENT 'ID de l\'inscription concernée',
  `eleve_id` int NOT NULL COMMENT 'ID de l\'élève',
  `ancien_statut` enum('inscrit','transfere','abandonne') NOT NULL COMMENT 'Statut précédent',
  `nouveau_statut` enum('transfere','abandonne') NOT NULL COMMENT 'Nouveau statut',
  `motif` varchar(255) NOT NULL COMMENT 'Motif du changement',
  `date_effet` date NOT NULL COMMENT 'Date d\'effet du changement',
  `commentaire` text COMMENT 'Commentaire additionnel',
  `user_id` int NOT NULL COMMENT 'ID de l\'utilisateur qui a effectué le changement',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Date de création de l\'enregistrement',
  PRIMARY KEY (`id`),
  KEY `idx_inscription_id` (`inscription_id`),
  KEY `idx_eleve_id` (`eleve_id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_date_effet` (`date_effet`),
  KEY `idx_created_at` (`created_at`),
  CONSTRAINT `fk_historique_inscription` FOREIGN KEY (`inscription_id`) REFERENCES `inscriptions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_historique_eleve` FOREIGN KEY (`eleve_id`) REFERENCES `eleves` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_historique_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Historique des changements de statut des inscriptions';

-- Ajout d'index pour améliorer les performances des requêtes
CREATE INDEX `idx_historique_statut_complet` ON `historique_changements_statut` (`eleve_id`, `date_effet`, `nouveau_statut`);

-- Ajout d'un commentaire sur la table inscriptions pour clarifier l'usage
ALTER TABLE `inscriptions` COMMENT = 'Inscriptions des élèves par année scolaire et classe. Le statut peut être: inscrit, transfere, abandonne';

-- Ajout d'un commentaire sur la table eleves pour clarifier l'usage du statut
ALTER TABLE `eleves` COMMENT = 'Informations des élèves. Le statut peut être: actif, transfere, abandonne, diplome';

-- Vérification de l'intégrité des données existantes
-- Cette requête peut être exécutée pour vérifier la cohérence des données
/*
SELECT 
    e.id,
    e.nom,
    e.prenom,
    e.status as statut_eleve,
    i.status as statut_inscription,
    i.annee_scolaire_id
FROM eleves e
LEFT JOIN inscriptions i ON e.id = i.eleve_id AND i.annee_scolaire_id = (SELECT id FROM annees_scolaires WHERE status = 'active' LIMIT 1)
WHERE e.status != 'actif' OR i.status != 'inscrit';
*/
