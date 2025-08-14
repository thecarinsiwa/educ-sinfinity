-- Schéma de base de données pour Educ-Sinfinity
-- Application de gestion scolaire - République Démocratique du Congo

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;
SET time_zone = "+00:00";

-- Table des utilisateurs
CREATE TABLE IF NOT EXISTS `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL UNIQUE,
  `password` varchar(255) NOT NULL,
  `nom` varchar(100) NOT NULL,
  `prenom` varchar(100) NOT NULL,
  `email` varchar(255) DEFAULT NULL UNIQUE,
  `telephone` varchar(20) DEFAULT NULL,
  `role` enum('admin','directeur','enseignant','secretaire','comptable','surveillant') NOT NULL,
  `status` enum('actif','inactif') DEFAULT 'actif',
  `photo` varchar(255) DEFAULT NULL,
  `adresse` text DEFAULT NULL,
  `date_naissance` date DEFAULT NULL,
  `genre` enum('M','F') DEFAULT NULL,
  `derniere_connexion` timestamp NULL DEFAULT NULL,
  `tentatives_connexion` int(11) DEFAULT 0,
  `compte_verrouille` boolean DEFAULT FALSE,
  `date_verrouillage` timestamp NULL DEFAULT NULL,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_username` (`username`),
  KEY `idx_email` (`email`),
  KEY `idx_role` (`role`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table des établissements
CREATE TABLE etablissements (
    id INT PRIMARY KEY AUTO_INCREMENT,
    nom VARCHAR(200) NOT NULL,
    adresse TEXT,
    telephone VARCHAR(20),
    email VARCHAR(100),
    directeur VARCHAR(100),
    code_etablissement VARCHAR(20) UNIQUE,
    province VARCHAR(50),
    ville VARCHAR(50),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Table des années scolaires
CREATE TABLE annees_scolaires (
    id INT PRIMARY KEY AUTO_INCREMENT,
    annee VARCHAR(20) NOT NULL, -- Ex: 2023-2024
    date_debut DATE NOT NULL,
    date_fin DATE NOT NULL,
    status ENUM('active', 'fermee') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Table des classes/niveaux
CREATE TABLE classes (
    id INT PRIMARY KEY AUTO_INCREMENT,
    nom VARCHAR(50) NOT NULL, -- Ex: 1ère Primaire, 6ème Secondaire
    niveau ENUM('maternelle', 'primaire', 'secondaire') NOT NULL,
    section VARCHAR(50), -- Ex: Scientifique, Littéraire, Commerciale
    capacite_max INT DEFAULT 50,
    frais_inscription DECIMAL(10,2) DEFAULT 0,
    frais_mensuel DECIMAL(10,2) DEFAULT 0,
    annee_scolaire_id INT,
    FOREIGN KEY (annee_scolaire_id) REFERENCES annees_scolaires(id)
);

-- Table des matières
CREATE TABLE matieres (
    id INT PRIMARY KEY AUTO_INCREMENT,
    nom VARCHAR(100) NOT NULL,
    code VARCHAR(20) UNIQUE,
    coefficient INT DEFAULT 1,
    niveau ENUM('maternelle', 'primaire', 'secondaire') NOT NULL,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Table du personnel
CREATE TABLE personnel (
    id INT PRIMARY KEY AUTO_INCREMENT,
    matricule VARCHAR(20) UNIQUE NOT NULL,
    nom VARCHAR(100) NOT NULL,
    prenom VARCHAR(100) NOT NULL,
    sexe ENUM('M', 'F') NOT NULL,
    date_naissance DATE,
    lieu_naissance VARCHAR(100),
    adresse TEXT,
    telephone VARCHAR(20),
    email VARCHAR(100),
    fonction ENUM('enseignant', 'directeur', 'sous_directeur', 'secretaire', 'comptable', 'surveillant', 'gardien', 'autre') NOT NULL,
    specialite VARCHAR(100), -- Pour les enseignants
    diplome VARCHAR(100),
    date_embauche DATE,
    salaire_base DECIMAL(10,2),
    status ENUM('actif', 'suspendu', 'demissionne') DEFAULT 'actif',
    user_id INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

-- Table des élèves
CREATE TABLE eleves (
    id INT PRIMARY KEY AUTO_INCREMENT,
    numero_matricule VARCHAR(20) UNIQUE NOT NULL,
    nom VARCHAR(100) NOT NULL,
    prenom VARCHAR(100) NOT NULL,
    sexe ENUM('M', 'F') NOT NULL,
    date_naissance DATE NOT NULL,
    lieu_naissance VARCHAR(100),
    adresse TEXT,
    telephone VARCHAR(20),
    email VARCHAR(100),
    nom_pere VARCHAR(100),
    nom_mere VARCHAR(100),
    profession_pere VARCHAR(100),
    profession_mere VARCHAR(100),
    telephone_parent VARCHAR(20),
    personne_contact VARCHAR(100),
    telephone_contact VARCHAR(20),
    photo VARCHAR(255),
    status ENUM('actif', 'transfere', 'abandonne', 'diplome') DEFAULT 'actif',
    date_inscription DATE DEFAULT CURRENT_DATE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Table des inscriptions (relation élève-classe-année)
CREATE TABLE inscriptions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    eleve_id INT NOT NULL,
    classe_id INT NOT NULL,
    annee_scolaire_id INT NOT NULL,
    date_inscription DATE DEFAULT CURRENT_DATE,
    frais_inscription_paye DECIMAL(10,2) DEFAULT 0,
    status ENUM('inscrit', 'transfere', 'abandonne') DEFAULT 'inscrit',
    FOREIGN KEY (eleve_id) REFERENCES eleves(id),
    FOREIGN KEY (classe_id) REFERENCES classes(id),
    FOREIGN KEY (annee_scolaire_id) REFERENCES annees_scolaires(id),
    UNIQUE KEY unique_inscription (eleve_id, annee_scolaire_id)
);

-- Table des emplois du temps
CREATE TABLE emplois_temps (
    id INT PRIMARY KEY AUTO_INCREMENT,
    classe_id INT NOT NULL,
    matiere_id INT NOT NULL,
    enseignant_id INT NOT NULL,
    jour_semaine ENUM('lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi') NOT NULL,
    heure_debut TIME NOT NULL,
    heure_fin TIME NOT NULL,
    salle VARCHAR(50),
    annee_scolaire_id INT NOT NULL,
    FOREIGN KEY (classe_id) REFERENCES classes(id),
    FOREIGN KEY (matiere_id) REFERENCES matieres(id),
    FOREIGN KEY (enseignant_id) REFERENCES personnel(id),
    FOREIGN KEY (annee_scolaire_id) REFERENCES annees_scolaires(id)
);

-- Table des évaluations/examens
CREATE TABLE evaluations (
    id INT PRIMARY KEY AUTO_INCREMENT,
    nom VARCHAR(100) NOT NULL,
    type ENUM('interrogation', 'devoir', 'examen', 'composition') NOT NULL,
    classe_id INT NOT NULL,
    matiere_id INT NOT NULL,
    enseignant_id INT NOT NULL,
    date_evaluation DATE NOT NULL,
    note_max DECIMAL(5,2) DEFAULT 20.00,
    coefficient DECIMAL(3,2) DEFAULT 1.00,
    periode ENUM('1er_trimestre', '2eme_trimestre', '3eme_trimestre', 'annuelle') NOT NULL,
    annee_scolaire_id INT NOT NULL,
    FOREIGN KEY (classe_id) REFERENCES classes(id),
    FOREIGN KEY (matiere_id) REFERENCES matieres(id),
    FOREIGN KEY (enseignant_id) REFERENCES personnel(id),
    FOREIGN KEY (annee_scolaire_id) REFERENCES annees_scolaires(id)
);

-- Table des notes
CREATE TABLE notes (
    id INT PRIMARY KEY AUTO_INCREMENT,
    evaluation_id INT NOT NULL,
    eleve_id INT NOT NULL,
    note DECIMAL(5,2) NOT NULL,
    observation TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (evaluation_id) REFERENCES evaluations(id),
    FOREIGN KEY (eleve_id) REFERENCES eleves(id),
    UNIQUE KEY unique_note (evaluation_id, eleve_id)
);

-- Table des paiements
CREATE TABLE paiements (
    id INT PRIMARY KEY AUTO_INCREMENT,
    eleve_id INT NOT NULL,
    type_paiement ENUM('inscription', 'mensualite', 'examen', 'autre') NOT NULL,
    montant DECIMAL(10,2) NOT NULL,
    date_paiement DATE DEFAULT CURRENT_DATE,
    mois_concerne VARCHAR(20), -- Pour les mensualités
    annee_scolaire_id INT NOT NULL,
    recu_numero VARCHAR(50),
    mode_paiement ENUM('especes', 'cheque', 'virement', 'mobile_money') DEFAULT 'especes',
    observation TEXT,
    user_id INT, -- Qui a enregistré le paiement
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (eleve_id) REFERENCES eleves(id),
    FOREIGN KEY (annee_scolaire_id) REFERENCES annees_scolaires(id),
    FOREIGN KEY (user_id) REFERENCES users(id)
);

-- Table de la bibliothèque - Livres
CREATE TABLE livres (
    id INT PRIMARY KEY AUTO_INCREMENT,
    titre VARCHAR(200) NOT NULL,
    auteur VARCHAR(100),
    isbn VARCHAR(20),
    editeur VARCHAR(100),
    annee_publication YEAR,
    categorie VARCHAR(50),
    nombre_exemplaires INT DEFAULT 1,
    nombre_disponibles INT DEFAULT 1,
    emplacement VARCHAR(50),
    status ENUM('disponible', 'indisponible') DEFAULT 'disponible',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Table des emprunts de livres
CREATE TABLE emprunts (
    id INT PRIMARY KEY AUTO_INCREMENT,
    livre_id INT NOT NULL,
    eleve_id INT NOT NULL,
    date_emprunt DATE DEFAULT CURRENT_DATE,
    date_retour_prevue DATE NOT NULL,
    date_retour_effective DATE,
    status ENUM('en_cours', 'retourne', 'perdu') DEFAULT 'en_cours',
    amende DECIMAL(8,2) DEFAULT 0,
    FOREIGN KEY (livre_id) REFERENCES livres(id),
    FOREIGN KEY (eleve_id) REFERENCES eleves(id)
);

-- Table de discipline
CREATE TABLE sanctions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    eleve_id INT NOT NULL,
    type_sanction ENUM('avertissement', 'blame', 'exclusion_temporaire', 'exclusion_definitive', 'travaux_supplementaires') NOT NULL,
    motif TEXT NOT NULL,
    date_sanction DATE DEFAULT CURRENT_DATE,
    duree_jours INT, -- Pour exclusions temporaires
    enseignant_id INT, -- Qui a donné la sanction
    status ENUM('active', 'levee') DEFAULT 'active',
    observation TEXT,
    FOREIGN KEY (eleve_id) REFERENCES eleves(id),
    FOREIGN KEY (enseignant_id) REFERENCES personnel(id)
);

-- Insertion des données de base
INSERT INTO etablissements (nom, adresse, telephone, email, code_etablissement, province, ville) 
VALUES ('École Sinfinity', 'Avenue de la Paix, Kinshasa', '+243 123 456 789', 'contact@sinfinity-school.cd', 'SINF001', 'Kinshasa', 'Kinshasa');

INSERT INTO annees_scolaires (annee, date_debut, date_fin, status) 
VALUES ('2023-2024', '2023-09-01', '2024-07-31', 'active');

-- Utilisateur administrateur par défaut (mot de passe: admin123)
INSERT INTO users (username, email, password, role) 
VALUES ('admin', 'admin@sinfinity-school.cd', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin');

-- Tables du module Recouvrement

-- Table des paramètres de recouvrement
CREATE TABLE IF NOT EXISTS parametres_recouvrement (
    id INT PRIMARY KEY AUTO_INCREMENT,
    cle VARCHAR(100) NOT NULL UNIQUE,
    valeur TEXT,
    description TEXT,
    type ENUM('string', 'number', 'boolean', 'json') DEFAULT 'string',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table des cartes élèves
CREATE TABLE IF NOT EXISTS cartes_eleves (
    id INT PRIMARY KEY AUTO_INCREMENT,
    eleve_id INT NOT NULL,
    numero_carte VARCHAR(50) NOT NULL UNIQUE,
    type_carte ENUM('standard', 'premium', 'temporaire') DEFAULT 'standard',
    status ENUM('active', 'inactive', 'perdue', 'bloquee') DEFAULT 'active',
    date_emission DATE NOT NULL,
    date_expiration DATE,
    montant_limite DECIMAL(10,2) DEFAULT 0,
    montant_utilise DECIMAL(10,2) DEFAULT 0,
    observations TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (eleve_id) REFERENCES eleves(id) ON DELETE CASCADE,
    INDEX idx_eleve_id (eleve_id),
    INDEX idx_numero_carte (numero_carte),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table des transactions de cartes
CREATE TABLE IF NOT EXISTS transactions_cartes (
    id INT PRIMARY KEY AUTO_INCREMENT,
    carte_id INT NOT NULL,
    type_transaction ENUM('debit', 'credit', 'recharge', 'remboursement') NOT NULL,
    montant DECIMAL(10,2) NOT NULL,
    solde_avant DECIMAL(10,2) NOT NULL,
    solde_apres DECIMAL(10,2) NOT NULL,
    description TEXT,
    reference_paiement VARCHAR(100),
    user_id INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (carte_id) REFERENCES cartes_eleves(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_carte_id (carte_id),
    INDEX idx_type_transaction (type_transaction),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table des paiements de cartes
CREATE TABLE IF NOT EXISTS paiements_cartes (
    id INT PRIMARY KEY AUTO_INCREMENT,
    carte_id INT NOT NULL,
    montant DECIMAL(10,2) NOT NULL,
    type_paiement ENUM('especes', 'carte_bancaire', 'mobile_money', 'virement') NOT NULL,
    reference VARCHAR(100),
    status ENUM('en_attente', 'valide', 'annule', 'refuse') DEFAULT 'en_attente',
    date_paiement TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    user_id INT,
    observations TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (carte_id) REFERENCES cartes_eleves(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_carte_id (carte_id),
    INDEX idx_status (status),
    INDEX idx_date_paiement (date_paiement)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insertion des paramètres par défaut pour le recouvrement
INSERT IGNORE INTO parametres_recouvrement (cle, valeur, description, type) VALUES
('prefixe_carte', 'CARD', 'Préfixe pour les numéros de cartes', 'string'),
('montant_limite_defaut', '50000', 'Montant limite par défaut des cartes (en FC)', 'number'),
('duree_validite', '365', 'Durée de validité des cartes en jours', 'number'),
('frais_emission', '5000', 'Frais d\'émission de carte (en FC)', 'number'),
('frais_recharge', '1000', 'Frais de recharge de carte (en FC)', 'number'),
('seuil_alerte', '10000', 'Seuil d\'alerte pour solde faible (en FC)', 'number'),
('activer_notifications', 'true', 'Activer les notifications SMS/Email', 'boolean'),
('mode_maintenance', 'false', 'Mode maintenance du système de cartes', 'boolean');
