-- Migration pour ajouter la gestion des devises
-- Application de gestion scolaire - République Démocratique du Congo

-- 1. Créer la table des devises
CREATE TABLE IF NOT EXISTS devises (
    id INT PRIMARY KEY AUTO_INCREMENT,
    code VARCHAR(3) NOT NULL UNIQUE, -- ISO 4217 (USD, EUR, CDF, etc.)
    nom VARCHAR(100) NOT NULL,
    symbole VARCHAR(10) NOT NULL,
    taux_conversion DECIMAL(15,6) NOT NULL DEFAULT 1.000000, -- Taux par rapport à la devise par défaut
    devise_par_defaut BOOLEAN DEFAULT FALSE,
    active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_code (code),
    INDEX idx_active (active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Ajouter la colonne devise_id aux tables financières existantes

-- Table paiements
ALTER TABLE paiements 
ADD COLUMN devise_id INT NULL AFTER montant,
ADD COLUMN montant_devise_par_defaut DECIMAL(15,2) NULL AFTER devise_id,
ADD FOREIGN KEY (devise_id) REFERENCES devises(id);

-- Table frais_scolaires
ALTER TABLE frais_scolaires 
ADD COLUMN devise_id INT NULL AFTER montant,
ADD COLUMN montant_devise_par_defaut DECIMAL(15,2) NULL AFTER devise_id,
ADD FOREIGN KEY (devise_id) REFERENCES devises(id);

-- Table paiements_cartes (si elle existe)
ALTER TABLE paiements_cartes 
ADD COLUMN devise_id INT NULL AFTER montant,
ADD COLUMN montant_devise_par_defaut DECIMAL(15,2) NULL AFTER devise_id,
ADD FOREIGN KEY (devise_id) REFERENCES devises(id);

-- 3. Insérer les devises de base
INSERT INTO devises (code, nom, symbole, taux_conversion, devise_par_defaut, active) VALUES
('CDF', 'Franc Congolais', 'FC', 1.000000, TRUE, TRUE),
('USD', 'Dollar Américain', '$', 0.000400, FALSE, TRUE),
('EUR', 'Euro', '€', 0.000370, FALSE, TRUE),
('GBP', 'Livre Sterling', '£', 0.000320, FALSE, TRUE);

-- 4. Mettre à jour les montants existants pour utiliser la devise par défaut (CDF)
UPDATE paiements SET devise_id = (SELECT id FROM devises WHERE devise_par_defaut = TRUE LIMIT 1), montant_devise_par_defaut = montant WHERE devise_id IS NULL;
UPDATE frais_scolaires SET devise_id = (SELECT id FROM devises WHERE devise_par_defaut = TRUE LIMIT 1), montant_devise_par_defaut = montant WHERE devise_id IS NULL;
UPDATE paiements_cartes SET devise_id = (SELECT id FROM devises WHERE devise_par_defaut = TRUE LIMIT 1), montant_devise_par_defaut = montant WHERE devise_id IS NULL;
