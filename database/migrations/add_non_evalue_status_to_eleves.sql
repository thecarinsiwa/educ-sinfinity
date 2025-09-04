-- Migration: Ajouter le statut "non-evalué" à la table eleves
-- Date: 2024-01-XX
-- Description: Permet aux élèves d'avoir le statut "non-evalué" lors de leur ajout direct

-- Modifier l'ENUM de la colonne status pour inclure "non-evalué"
ALTER TABLE eleves 
MODIFY COLUMN status ENUM('actif', 'transfere', 'abandonne', 'diplome', 'non-evalué') DEFAULT 'actif';

-- Commentaire pour documenter le changement
COMMENT ON COLUMN eleves.status IS 'Statut de l''élève: actif, transfere, abandonne, diplome, non-evalué';
