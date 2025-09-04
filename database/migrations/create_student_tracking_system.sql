-- Migration pour créer le système de suivi des élèves
-- Application de gestion scolaire - République Démocratique du Congo

-- Table des étapes du processus d'admission
CREATE TABLE IF NOT EXISTS etapes_admission (
    id INT PRIMARY KEY AUTO_INCREMENT,
    nom VARCHAR(100) NOT NULL,
    description TEXT,
    ordre INT NOT NULL,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Table des étapes par demande d'admission
CREATE TABLE IF NOT EXISTS suivi_etapes_admission (
    id INT PRIMARY KEY AUTO_INCREMENT,
    demande_admission_id INT NOT NULL,
    etape_id INT NOT NULL,
    status ENUM('en_attente', 'en_cours', 'terminee', 'annulee') DEFAULT 'en_attente',
    date_debut TIMESTAMP NULL,
    date_fin TIMESTAMP NULL,
    user_id INT,
    commentaire TEXT,
    documents_requis TEXT,
    documents_fournis TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (demande_admission_id) REFERENCES demandes_admission(id) ON DELETE CASCADE,
    FOREIGN KEY (etape_id) REFERENCES etapes_admission(id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

-- Table des évaluations détaillées
CREATE TABLE IF NOT EXISTS evaluations_admission (
    id INT PRIMARY KEY AUTO_INCREMENT,
    demande_admission_id INT NOT NULL,
    type_evaluation ENUM('test_ecrit', 'entretien', 'examen_medical', 'evaluation_psychologique', 'test_niveau') NOT NULL,
    date_evaluation DATE NOT NULL,
    heure_debut TIME,
    heure_fin TIME,
    lieu VARCHAR(255),
    evaluateur_id INT,
    note_sur_20 DECIMAL(4,2),
    note_sur_100 DECIMAL(5,2),
    coefficient DECIMAL(3,2) DEFAULT 1.00,
    resultat ENUM('excellent', 'tres_bien', 'bien', 'moyen', 'insuffisant', 'nul') DEFAULT 'moyen',
    commentaire TEXT,
    decision_provisoire ENUM('accepter', 'refuser', 'attendre', 'conditionnel') DEFAULT 'attendre',
    conditions_acceptation TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (demande_admission_id) REFERENCES demandes_admission(id) ON DELETE CASCADE,
    FOREIGN KEY (evaluateur_id) REFERENCES users(id) ON DELETE SET NULL
);

-- Table des décisions d'admission
CREATE TABLE IF NOT EXISTS decisions_admission (
    id INT PRIMARY KEY AUTO_INCREMENT,
    demande_admission_id INT NOT NULL,
    decision ENUM('acceptee', 'refusee', 'acceptee_conditionnelle', 'mise_en_liste_attente') NOT NULL,
    date_decision DATE NOT NULL,
    decideur_id INT NOT NULL,
    motif_decision TEXT NOT NULL,
    conditions_speciales TEXT,
    date_limite_reponse DATE,
    frais_inscription_final DECIMAL(10,2),
    frais_scolarite_final DECIMAL(10,2),
    reduction_finale DECIMAL(5,2),
    commentaire TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (demande_admission_id) REFERENCES demandes_admission(id) ON DELETE CASCADE,
    FOREIGN KEY (decideur_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Table des inscriptions détaillées
CREATE TABLE IF NOT EXISTS inscriptions_detaillees (
    id INT PRIMARY KEY AUTO_INCREMENT,
    demande_admission_id INT NOT NULL,
    eleve_id INT NOT NULL,
    annee_scolaire_id INT NOT NULL,
    classe_id INT NOT NULL,
    section_id INT,
    date_inscription DATE NOT NULL,
    frais_inscription_paye DECIMAL(10,2) DEFAULT 0,
    frais_scolarite_paye DECIMAL(10,2) DEFAULT 0,
    reduction_appliquee DECIMAL(5,2) DEFAULT 0,
    mode_paiement ENUM('especes', 'cheque', 'virement', 'mobile_money', 'carte') DEFAULT 'especes',
    numero_recu VARCHAR(100),
    status ENUM('inscrit', 'en_attente_paiement', 'suspendu', 'transfere', 'abandonne', 'diplome') DEFAULT 'inscrit',
    date_debut_scolarite DATE,
    date_fin_scolarite DATE,
    observations TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (demande_admission_id) REFERENCES demandes_admission(id) ON DELETE CASCADE,
    FOREIGN KEY (eleve_id) REFERENCES eleves(id) ON DELETE CASCADE,
    FOREIGN KEY (annee_scolaire_id) REFERENCES annees_scolaires(id),
    FOREIGN KEY (classe_id) REFERENCES classes(id),
    FOREIGN KEY (section_id) REFERENCES sections(id) ON DELETE SET NULL
);

-- Table des sections (pour les classes du secondaire)
CREATE TABLE IF NOT EXISTS sections (
    id INT PRIMARY KEY AUTO_INCREMENT,
    nom VARCHAR(100) NOT NULL,
    niveau ENUM('primaire', 'secondaire') NOT NULL,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Table du suivi scolaire
CREATE TABLE IF NOT EXISTS suivi_scolaire (
    id INT PRIMARY KEY AUTO_INCREMENT,
    eleve_id INT NOT NULL,
    annee_scolaire_id INT NOT NULL,
    classe_id INT NOT NULL,
    trimestre ENUM('1er_trimestre', '2eme_trimestre', '3eme_trimestre', 'annuel') NOT NULL,
    moyenne_generale DECIMAL(4,2),
    rang_classe INT,
    effectif_classe INT,
    appreciation TEXT,
    decision_conseil ENUM('admis', 'admis_avec_reserves', 'redouble', 'exclu') DEFAULT 'admis',
    commentaire_conseil TEXT,
    date_conseil DATE,
    signature_prof_principal VARCHAR(100),
    signature_directeur VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (eleve_id) REFERENCES eleves(id) ON DELETE CASCADE,
    FOREIGN KEY (annee_scolaire_id) REFERENCES annees_scolaires(id),
    FOREIGN KEY (classe_id) REFERENCES classes(id)
);

-- Table des transferts et sorties
CREATE TABLE IF NOT EXISTS transferts_sorties (
    id INT PRIMARY KEY AUTO_INCREMENT,
    eleve_id INT NOT NULL,
    type ENUM('transfert', 'sortie_definitive', 'exclusion', 'abandon') NOT NULL,
    date_effet DATE NOT NULL,
    motif TEXT NOT NULL,
    ecole_destination VARCHAR(255),
    adresse_ecole_destination TEXT,
    telephone_ecole_destination VARCHAR(20),
    documents_remis TEXT,
    montant_remboursement DECIMAL(10,2) DEFAULT 0,
    observations TEXT,
    traite_par INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (eleve_id) REFERENCES eleves(id) ON DELETE CASCADE,
    FOREIGN KEY (traite_par) REFERENCES users(id) ON DELETE CASCADE
);

-- Table des documents d'élève
CREATE TABLE IF NOT EXISTS documents_eleve (
    id INT PRIMARY KEY AUTO_INCREMENT,
    eleve_id INT NOT NULL,
    type_document ENUM('certificat_naissance', 'bulletin_precedent', 'certificat_medical', 'photo_identite', 'carte_etudiant', 'certificat_scolarite', 'autre') NOT NULL,
    nom_fichier VARCHAR(255) NOT NULL,
    chemin_fichier VARCHAR(500) NOT NULL,
    taille_fichier INT,
    type_mime VARCHAR(100),
    status ENUM('en_attente', 'valide', 'rejete', 'expire') DEFAULT 'en_attente',
    date_upload TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    date_validation TIMESTAMP NULL,
    valide_par INT,
    commentaire TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (eleve_id) REFERENCES eleves(id) ON DELETE CASCADE,
    FOREIGN KEY (valide_par) REFERENCES users(id) ON DELETE SET NULL
);

-- Table des notifications de suivi
CREATE TABLE IF NOT EXISTS notifications_suivi (
    id INT PRIMARY KEY AUTO_INCREMENT,
    eleve_id INT NOT NULL,
    type_notification ENUM('evaluation_pending', 'decision_pending', 'inscription_pending', 'paiement_pending', 'document_missing', 'reminder') NOT NULL,
    titre VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    priorite ENUM('basse', 'normale', 'haute', 'urgente') DEFAULT 'normale',
    status ENUM('non_lue', 'lue', 'traitee') DEFAULT 'non_lue',
    destinataire_id INT,
    date_envoi TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    date_lecture TIMESTAMP NULL,
    date_traitement TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (eleve_id) REFERENCES eleves(id) ON DELETE CASCADE,
    FOREIGN KEY (destinataire_id) REFERENCES users(id) ON DELETE SET NULL
);

-- Insertion des étapes par défaut
INSERT INTO etapes_admission (nom, description, ordre) VALUES
('Demande d\'admission', 'Enregistrement des informations de base et génération du numéro de dossier', 1),
('Vérification des documents', 'Contrôle et validation des pièces jointes', 2),
('Évaluation', 'Tests, entretiens et examens d\'admission', 3),
('Décision d\'admission', 'Acceptation, refus ou acceptation conditionnelle', 4),
('Inscription', 'Finalisation de l\'inscription et paiement des frais', 5),
('Intégration', 'Accueil et intégration dans la classe', 6);

-- Insertion des sections par défaut
INSERT INTO sections (nom, niveau, description) VALUES
('Générale', 'primaire', 'Section générale pour l\'enseignement primaire'),
('Scientifique', 'secondaire', 'Section scientifique pour l\'enseignement secondaire'),
('Littéraire', 'secondaire', 'Section littéraire pour l\'enseignement secondaire'),
('Commerciale', 'secondaire', 'Section commerciale pour l\'enseignement secondaire'),
('Technique', 'secondaire', 'Section technique pour l\'enseignement secondaire');

-- Index pour optimiser les performances
CREATE INDEX idx_suivi_etapes_demande ON suivi_etapes_admission(demande_admission_id);
CREATE INDEX idx_suivi_etapes_etape ON suivi_etapes_admission(etape_id);
CREATE INDEX idx_evaluations_demande ON evaluations_admission(demande_admission_id);
CREATE INDEX idx_decisions_demande ON decisions_admission(demande_admission_id);
CREATE INDEX idx_inscriptions_eleve ON inscriptions_detaillees(eleve_id);
CREATE INDEX idx_suivi_scolaire_eleve ON suivi_scolaire(eleve_id);
CREATE INDEX idx_transferts_eleve ON transferts_sorties(eleve_id);
CREATE INDEX idx_documents_eleve ON documents_eleve(eleve_id);
CREATE INDEX idx_notifications_eleve ON notifications_suivi(eleve_id);
