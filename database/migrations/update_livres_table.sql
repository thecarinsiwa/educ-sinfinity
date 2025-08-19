-- Migration pour mettre à jour la table livres
-- Ajout des colonnes manquantes pour le module bibliothèque

-- Ajouter les nouvelles colonnes à la table livres
ALTER TABLE `livres` 
ADD COLUMN `nombre_pages` int DEFAULT NULL AFTER `categorie`,
ADD COLUMN `langue` varchar(50) DEFAULT 'Français' AFTER `nombre_pages`,
ADD COLUMN `resume` text AFTER `langue`,
ADD COLUMN `cote` varchar(50) DEFAULT NULL AFTER `resume`,
ADD COLUMN `prix_achat` decimal(10,2) DEFAULT NULL AFTER `cote`,
ADD COLUMN `date_acquisition` date DEFAULT CURRENT_DATE AFTER `prix_achat`,
ADD COLUMN `etat` enum('excellent','bon','moyen','mauvais') DEFAULT 'bon' AFTER `date_acquisition`,
ADD COLUMN `notes` text AFTER `etat`,
ADD COLUMN `categorie_id` int DEFAULT NULL AFTER `categorie`,
ADD COLUMN `exemplaires_disponibles` int DEFAULT 1 AFTER `nombre_disponibles`,
ADD COLUMN `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP AFTER `created_at`;

-- Créer la table des catégories de livres si elle n'existe pas
CREATE TABLE IF NOT EXISTS `categories_livres` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nom` varchar(100) NOT NULL,
  `description` text,
  `couleur` varchar(7) DEFAULT '#007bff',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insérer quelques catégories par défaut
INSERT IGNORE INTO `categories_livres` (`nom`, `description`, `couleur`) VALUES
('Littérature', 'Romans, nouvelles, poésie', '#28a745'),
('Sciences', 'Mathématiques, physique, chimie, biologie', '#17a2b8'),
('Histoire', 'Histoire générale et spécialisée', '#ffc107'),
('Géographie', 'Géographie physique et humaine', '#6f42c1'),
('Langues', 'Manuels de langues étrangères', '#fd7e14'),
('Arts', 'Musique, peinture, sculpture', '#e83e8c'),
('Technologie', 'Informatique, électronique', '#20c997'),
('Philosophie', 'Philosophie et éthique', '#6c757d'),
('Religion', 'Textes religieux et spirituels', '#495057'),
('Autres', 'Autres catégories', '#dee2e6');

-- Ajouter des index pour améliorer les performances
ALTER TABLE `livres` 
ADD INDEX `idx_categorie_id` (`categorie_id`),
ADD INDEX `idx_isbn` (`isbn`),
ADD INDEX `idx_titre` (`titre`),
ADD INDEX `idx_auteur` (`auteur`),
ADD INDEX `idx_status` (`status`);

-- Ajouter une contrainte de clé étrangère pour la catégorie
ALTER TABLE `livres` 
ADD CONSTRAINT `fk_livres_categorie` 
FOREIGN KEY (`categorie_id`) REFERENCES `categories_livres` (`id`) 
ON DELETE SET NULL ON UPDATE CASCADE;
