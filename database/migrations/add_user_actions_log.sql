-- Migration pour ajouter la table de log des actions utilisateurs
-- Application de gestion scolaire - République Démocratique du Congo

-- Table pour l'historique des actions utilisateurs
CREATE TABLE IF NOT EXISTS user_actions_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    action VARCHAR(100) NOT NULL,
    module VARCHAR(50) NOT NULL,
    details TEXT,
    target_id INT,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_user_id (user_id),
    INDEX idx_action (action),
    INDEX idx_module (module),
    INDEX idx_created_at (created_at),
    INDEX idx_target_id (target_id),
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Mettre à jour la table users pour SHA1 et ajouter des champs manquants
ALTER TABLE users 
ADD COLUMN IF NOT EXISTS derniere_connexion TIMESTAMP NULL,
ADD COLUMN IF NOT EXISTS tentatives_connexion INT DEFAULT 0,
ADD COLUMN IF NOT EXISTS compte_verrouille BOOLEAN DEFAULT FALSE,
ADD COLUMN IF NOT EXISTS date_verrouillage TIMESTAMP NULL;

-- Ajouter des index pour optimiser les performances
ALTER TABLE users 
ADD INDEX IF NOT EXISTS idx_username (username),
ADD INDEX IF NOT EXISTS idx_status (status),
ADD INDEX IF NOT EXISTS idx_role (role);

-- Mettre à jour la table absences pour inclure l'utilisateur qui a créé l'enregistrement
ALTER TABLE absences 
ADD COLUMN IF NOT EXISTS created_by INT,
ADD COLUMN IF NOT EXISTS updated_by INT,
ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP;

-- Ajouter les clés étrangères pour les absences
ALTER TABLE absences 
ADD CONSTRAINT IF NOT EXISTS fk_absences_created_by 
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
ADD CONSTRAINT IF NOT EXISTS fk_absences_updated_by 
    FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL;

-- Ajouter des index pour les absences
ALTER TABLE absences 
ADD INDEX IF NOT EXISTS idx_created_by (created_by),
ADD INDEX IF NOT EXISTS idx_updated_by (updated_by),
ADD INDEX IF NOT EXISTS idx_date_absence (date_absence),
ADD INDEX IF NOT EXISTS idx_type_absence (type_absence);

-- Table pour les sessions utilisateurs actives
CREATE TABLE IF NOT EXISTS user_sessions (
    id VARCHAR(128) PRIMARY KEY,
    user_id INT NOT NULL,
    ip_address VARCHAR(45),
    user_agent TEXT,
    last_activity TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_user_id (user_id),
    INDEX idx_last_activity (last_activity),
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Procédure pour nettoyer les anciennes sessions (plus de 24h)
DELIMITER //
CREATE PROCEDURE IF NOT EXISTS CleanOldSessions()
BEGIN
    DELETE FROM user_sessions 
    WHERE last_activity < DATE_SUB(NOW(), INTERVAL 24 HOUR);
END //
DELIMITER ;

-- Procédure pour nettoyer les anciens logs (plus de 6 mois)
DELIMITER //
CREATE PROCEDURE IF NOT EXISTS CleanOldLogs()
BEGIN
    DELETE FROM user_actions_log 
    WHERE created_at < DATE_SUB(NOW(), INTERVAL 6 MONTH);
END //
DELIMITER ;

-- Événement pour nettoyer automatiquement les données anciennes (exécuté quotidiennement)
CREATE EVENT IF NOT EXISTS daily_cleanup
ON SCHEDULE EVERY 1 DAY
STARTS CURRENT_TIMESTAMP
DO
BEGIN
    CALL CleanOldSessions();
    CALL CleanOldLogs();
END;

-- Activer l'événement scheduler si ce n'est pas déjà fait
SET GLOBAL event_scheduler = ON;
