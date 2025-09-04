-- Migration pour ajouter les colonnes manquantes à la table eleves
-- Application de gestion scolaire - République Démocratique du Congo

-- Ajouter la colonne relation_contact
ALTER TABLE `eleves` 
ADD COLUMN `relation_contact` VARCHAR(100) DEFAULT NULL 
AFTER `telephone_contact`;

-- Ajouter la colonne classe_id
ALTER TABLE `eleves` 
ADD COLUMN `classe_id` INT DEFAULT NULL 
AFTER `relation_contact`;

-- Ajouter la colonne annee_scolaire_id
ALTER TABLE `eleves` 
ADD COLUMN `annee_scolaire_id` INT DEFAULT NULL 
AFTER `classe_id`;

-- Ajouter les clés étrangères
ALTER TABLE `eleves` 
ADD CONSTRAINT `fk_eleves_classe` 
FOREIGN KEY (`classe_id`) REFERENCES `classes`(`id`) 
ON DELETE SET NULL ON UPDATE CASCADE;

ALTER TABLE `eleves` 
ADD CONSTRAINT `fk_eleves_annee_scolaire` 
FOREIGN KEY (`annee_scolaire_id`) REFERENCES `annees_scolaires`(`id`) 
ON DELETE SET NULL ON UPDATE CASCADE;

-- Ajouter des index pour améliorer les performances
ALTER TABLE `eleves` 
ADD INDEX `idx_classe_id` (`classe_id`),
ADD INDEX `idx_annee_scolaire_id` (`annee_scolaire_id`);

-- Commentaire sur la table
ALTER TABLE `eleves` 
COMMENT = 'Informations des élèves avec relations aux classes et années scolaires';
