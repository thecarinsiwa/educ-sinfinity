-- Migration pour ajouter les champs QR code à la table carte_eleve
-- Date: 2025-01-09

-- Ajouter les nouveaux champs à la table carte_eleve
ALTER TABLE `carte_eleve` 
ADD COLUMN `qr_code_path` VARCHAR(255) NULL COMMENT 'Chemin vers le fichier PNG du QR code' AFTER `qr_data`,
ADD COLUMN `annee_scolaire` VARCHAR(20) NULL COMMENT 'Année scolaire (ex: 2025-2026)' AFTER `annee_scolaire_id`;

-- Ajouter un index sur le nouveau champ
ALTER TABLE `carte_eleve` 
ADD INDEX `idx_qr_code_path` (`qr_code_path`);

-- Mettre à jour les données existantes si nécessaire
UPDATE `carte_eleve` ce
JOIN `annees_scolaires` a ON ce.annee_scolaire_id = a.id
SET ce.annee_scolaire = a.annee
WHERE ce.annee_scolaire IS NULL;
