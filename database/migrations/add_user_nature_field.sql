-- Migration: Ajout du champ 'nature' à la table users
-- Date: 2025-01-09
-- Description: Ajoute un champ enum pour déterminer la nature de l'utilisateur et le dashboard à afficher

-- Ajouter la colonne 'nature' à la table users
ALTER TABLE users 
ADD COLUMN nature ENUM('admin', 'teacher', 'student', 'parent', 'staff') 
NOT NULL DEFAULT 'staff' 
COMMENT 'Nature de l''utilisateur pour déterminer le dashboard';

-- Ajouter un index sur la colonne nature pour améliorer les performances
CREATE INDEX idx_users_nature ON users(nature);

-- Mettre à jour les utilisateurs existants selon leur rôle actuel
UPDATE users u
JOIN roles r ON u.role_id = r.id
SET u.nature = CASE 
    WHEN r.nom = 'Administrateur' OR r.nom = 'admin' THEN 'admin'
    WHEN r.nom = 'Enseignant' OR r.nom = 'teacher' THEN 'teacher'
    WHEN r.nom = 'Élève' OR r.nom = 'student' THEN 'student'
    WHEN r.nom = 'Parent' OR r.nom = 'parent' THEN 'parent'
    ELSE 'staff'
END;

-- Ajouter une contrainte de validation
ALTER TABLE users 
ADD CONSTRAINT chk_users_nature_valid 
CHECK (nature IN ('admin', 'teacher', 'student', 'parent', 'staff'));
