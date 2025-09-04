-- Migration pour ajouter le statut "en_attente" à la table inscriptions
-- Date: 2025-01-27
-- Description: Ajoute le statut "en_attente" à l'énumération du champ status de la table inscriptions

-- Vérifier si la table existe et si le champ status existe
SET @sql = '';

-- Vérifier si la table inscriptions existe
SELECT COUNT(*) INTO @table_exists 
FROM information_schema.tables 
WHERE table_schema = DATABASE() 
AND table_name = 'inscriptions';

-- Si la table existe, modifier l'énumération
IF @table_exists > 0 THEN
    -- Vérifier si le champ status existe et s'il est de type ENUM
    SELECT COLUMN_TYPE INTO @column_type
    FROM information_schema.columns 
    WHERE table_schema = DATABASE() 
    AND table_name = 'inscriptions' 
    AND column_name = 'status';
    
    -- Si le champ status est de type ENUM et ne contient pas 'en_attente'
    IF @column_type LIKE 'enum%' AND @column_type NOT LIKE '%en_attente%' THEN
        -- Créer une table temporaire avec la nouvelle structure
        CREATE TABLE inscriptions_temp LIKE inscriptions;
        
        -- Modifier la structure de la table temporaire
        ALTER TABLE inscriptions_temp 
        MODIFY COLUMN status ENUM('inscrit', 'en_attente', 'transfere', 'abandonne') DEFAULT 'en_attente';
        
        -- Copier les données existantes
        INSERT INTO inscriptions_temp 
        SELECT * FROM inscriptions;
        
        -- Supprimer l'ancienne table
        DROP TABLE inscriptions;
        
        -- Renommer la table temporaire
        RENAME TABLE inscriptions_temp TO inscriptions;
        
        -- Mettre à jour les inscriptions existantes qui n'ont pas de frais d'inscription payés
        UPDATE inscriptions 
        SET status = 'en_attente' 
        WHERE frais_inscription_paye = 0 OR frais_inscription_paye IS NULL;
        
        SELECT 'Migration réussie: Statut "en_attente" ajouté à la table inscriptions' AS message;
    ELSE
        SELECT 'Le champ status existe déjà avec le statut "en_attente" ou n\'est pas de type ENUM' AS message;
    END IF;
ELSE
    SELECT 'La table inscriptions n\'existe pas' AS message;
END IF;
