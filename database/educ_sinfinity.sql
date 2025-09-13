-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Hôte : localhost
-- Généré le : jeu. 11 sep. 2025 à 10:09
-- Version du serveur : 8.0.30
-- Version de PHP : 8.1.10

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de données : `educ_sinfinity`
--

-- --------------------------------------------------------

--
-- Structure de la table `absences`
--

CREATE TABLE `absences` (
  `id` int NOT NULL,
  `eleve_id` int NOT NULL,
  `classe_id` int NOT NULL,
  `date_absence` date NOT NULL,
  `type_absence` enum('absence','retard','absence_justifiee','retard_justifie') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'absence',
  `motif` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `duree_retard` int DEFAULT NULL COMMENT 'Durée du retard en minutes',
  `justification` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `document_justificatif` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `valide_par` int DEFAULT NULL,
  `date_validation` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `annees_scolaires`
--

CREATE TABLE `annees_scolaires` (
  `id` int NOT NULL,
  `annee` varchar(20) NOT NULL,
  `date_debut` date NOT NULL,
  `date_fin` date NOT NULL,
  `status` enum('active','fermee') DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;


-- --------------------------------------------------------

--
-- Structure de la table `annonces`
--

CREATE TABLE `annonces` (
  `id` int NOT NULL,
  `titre` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `contenu` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `auteur_id` int NOT NULL,
  `type_annonce` enum('generale','urgente','evenement','administrative','pedagogique') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'generale',
  `cible` enum('tous','eleves','personnel','parents','classe_specifique') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'tous',
  `classe_id` int DEFAULT NULL,
  `date_publication` datetime DEFAULT NULL,
  `date_expiration` datetime DEFAULT NULL,
  `epinglee` tinyint(1) DEFAULT '0',
  `active` tinyint(1) DEFAULT '1',
  `couleur` varchar(7) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT '#007bff',
  `fichiers_joints` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT 'JSON des fichiers',
  `vues` int DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `caisses`
--

CREATE TABLE `caisses` (
  `id` int NOT NULL,
  `nom` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `solde_initial` decimal(15,2) DEFAULT '0.00',
  `devise_id` int NOT NULL,
  `statut` enum('active','inactive') COLLATE utf8mb4_unicode_ci DEFAULT 'active',
  `annee_scolaire_id` int NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `campagnes_cibles_dettes`
--

CREATE TABLE `campagnes_cibles_dettes` (
  `id` int NOT NULL,
  `campagne_id` int NOT NULL,
  `eleve_id` int NOT NULL,
  `montant_dette` decimal(10,2) NOT NULL,
  `status` enum('pending','contacte','paye','refuse','injoignable') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'pending',
  `montant_recouvre` decimal(10,2) DEFAULT '0.00',
  `date_contact` date DEFAULT NULL,
  `methode_contact` enum('appel','sms','email','visite','lettre') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `commentaire` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `campagnes_recouvrement`
--

CREATE TABLE `campagnes_recouvrement` (
  `id` int NOT NULL,
  `nom` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `type_cible` enum('tous','retard','montant','niveau') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `montant_min` decimal(10,2) DEFAULT NULL,
  `montant_max` decimal(10,2) DEFAULT NULL,
  `date_debut` date NOT NULL,
  `date_fin` date NOT NULL,
  `strategie` enum('appel_telephonique','sms','email','visite_domicile','lettre','mixte') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'mixte',
  `budget` decimal(10,2) DEFAULT '0.00',
  `annee_scolaire_id` int NOT NULL,
  `status` enum('active','paused','completed','cancelled') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'active',
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `cartes_eleves`
--

CREATE TABLE `cartes_eleves` (
  `id` int NOT NULL,
  `eleve_id` int NOT NULL,
  `numero_carte` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `type_carte` enum('standard','premium','temporaire') COLLATE utf8mb4_unicode_ci DEFAULT 'standard',
  `status` enum('active','inactive','perdue','bloquee') COLLATE utf8mb4_unicode_ci DEFAULT 'active',
  `date_emission` date NOT NULL,
  `date_expiration` date DEFAULT NULL,
  `montant_limite` decimal(10,2) DEFAULT '0.00',
  `montant_utilise` decimal(10,2) DEFAULT '0.00',
  `observations` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `carte_eleve`
--

CREATE TABLE `carte_eleve` (
  `id` int NOT NULL,
  `eleve_id` int NOT NULL,
  `annee_scolaire_id` int NOT NULL,
  `annee_scolaire` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Année scolaire (ex: 2025-2026)',
  `numero_carte` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `qr_code` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `qr_data` text COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Données encodées dans le QR code',
  `qr_code_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Chemin vers le fichier PNG du QR code',
  `statut` enum('active','expiree','suspendue','archivée') COLLATE utf8mb4_unicode_ci DEFAULT 'active',
  `date_generation` datetime NOT NULL,
  `date_expiration` datetime NOT NULL,
  `date_archivage` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `carte_eleve_historique`
--

CREATE TABLE `carte_eleve_historique` (
  `id` int NOT NULL,
  `carte_id` int NOT NULL,
  `eleve_id` int NOT NULL,
  `annee_scolaire_id` int NOT NULL,
  `numero_carte` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `qr_code` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `statut` enum('active','expiree','suspendue','archivée') COLLATE utf8mb4_unicode_ci NOT NULL,
  `date_generation` datetime NOT NULL,
  `date_expiration` datetime NOT NULL,
  `date_archivage` datetime NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `categories_livres`
--

CREATE TABLE `categories_livres` (
  `id` int NOT NULL,
  `nom` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `couleur` varchar(7) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT '#007bff',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  `actif` tinyint(1) DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `classes`
--

CREATE TABLE `classes` (
  `id` int NOT NULL,
  `nom` varchar(50) NOT NULL,
  `niveau` enum('maternelle','primaire','secondaire') NOT NULL,
  `section` varchar(50) DEFAULT NULL,
  `salle` varchar(50) DEFAULT NULL,
  `description` text,
  `titulaire_id` int DEFAULT NULL,
  `capacite_max` int DEFAULT '50',
  `frais_inscription` decimal(10,2) DEFAULT '0.00',
  `frais_mensuel` decimal(10,2) DEFAULT '0.00',
  `annee_scolaire_id` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Structure de la table `commandes`
--

CREATE TABLE `commandes` (
  `id` int NOT NULL,
  `eleve_id` int NOT NULL,
  `type_commande` enum('frais_scolaires','fournitures','uniforme','transport','cantine','autre') COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `montant` decimal(10,2) NOT NULL,
  `status` enum('en_attente','approuvee','facturee','annulee') COLLATE utf8mb4_unicode_ci DEFAULT 'en_attente',
  `facture_id` int DEFAULT NULL,
  `annee_scolaire_id` int NOT NULL,
  `user_id` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `criteres_admission`
--

CREATE TABLE `criteres_admission` (
  `id` int NOT NULL,
  `annee_scolaire_id` int NOT NULL,
  `niveau` enum('maternelle','primaire','secondaire','superieur') COLLATE utf8mb4_unicode_ci NOT NULL,
  `age_min` int DEFAULT NULL COMMENT '├ége minimum en ann├®es',
  `age_max` int DEFAULT NULL COMMENT '├ége maximum en ann├®es',
  `capacite_max` int DEFAULT NULL COMMENT 'Capacit├® maximale pour ce niveau',
  `note_min` decimal(4,2) DEFAULT NULL COMMENT 'Note minimale requise (sur 20)',
  `documents_requis` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT 'Liste des documents requis',
  `conditions_speciales` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT 'Conditions sp├®ciales d''admission',
  `actif` tinyint(1) DEFAULT '1' COMMENT 'Crit├¿res actifs ou non',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `criteres_admission_classes`
--

CREATE TABLE `criteres_admission_classes` (
  `id` int NOT NULL,
  `annee_scolaire_id` int NOT NULL,
  `classe_id` int NOT NULL,
  `capacite_max` int DEFAULT NULL COMMENT 'Capacit├® maximale pour cette classe',
  `note_min` decimal(4,2) DEFAULT NULL COMMENT 'Note minimale requise pour cette classe',
  `actif` tinyint(1) DEFAULT '1' COMMENT 'Crit├¿res actifs ou non',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `decisions_admission`
--

CREATE TABLE `decisions_admission` (
  `id` int NOT NULL,
  `demande_admission_id` int NOT NULL,
  `decision` enum('acceptee','refusee','acceptee_conditionnelle','mise_en_liste_attente') NOT NULL,
  `date_decision` date NOT NULL,
  `decideur_id` int NOT NULL,
  `motif_decision` text NOT NULL,
  `conditions_speciales` text,
  `date_limite_reponse` date DEFAULT NULL,
  `frais_inscription_final` decimal(10,2) DEFAULT NULL,
  `frais_scolarite_final` decimal(10,2) DEFAULT NULL,
  `reduction_finale` decimal(5,2) DEFAULT NULL,
  `commentaire` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Structure de la table `demandes_admission`
--

CREATE TABLE `demandes_admission` (
  `id` int NOT NULL,
  `numero_demande` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `annee_scolaire_id` int NOT NULL,
  `classe_demandee_id` int NOT NULL,
  `nom_eleve` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `prenom_eleve` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `date_naissance` date NOT NULL,
  `lieu_naissance` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sexe` enum('M','F') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `adresse` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `telephone` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `nom_pere` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `nom_mere` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `profession_pere` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `profession_mere` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `telephone_parent` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `personne_contact` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `telephone_contact` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `relation_contact` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ecole_precedente` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `classe_precedente` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `annee_precedente` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `moyenne_precedente` decimal(4,2) DEFAULT NULL,
  `certificat_naissance` enum('non_fourni','fourni','verifie','rejete') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'non_fourni',
  `bulletin_precedent` enum('non_fourni','fourni','verifie','rejete') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'non_fourni',
  `certificat_medical` enum('non_fourni','fourni','verifie','rejete') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'non_fourni',
  `photo_identite` enum('non_fourni','fourni','verifie','rejete') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'non_fourni',
  `autres_documents` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `motif_demande` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `besoins_speciaux` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `allergies_medicales` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `status` enum('en_attente','acceptee','refusee','en_cours_traitement','inscrit') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'en_attente',
  `priorite` enum('normale','urgente','tres_urgente') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'normale',
  `date_entretien` datetime DEFAULT NULL,
  `notes_entretien` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `decision_motif` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `traite_par` int DEFAULT NULL,
  `date_traitement` timestamp NULL DEFAULT NULL,
  `frais_inscription` decimal(10,2) DEFAULT '0.00',
  `frais_scolarite` decimal(10,2) DEFAULT '0.00',
  `reduction_accordee` decimal(5,2) DEFAULT '0.00',
  `observations` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  `note_evaluation` decimal(4,2) DEFAULT NULL COMMENT 'Note d''évaluation sur 20',
  `commentaire_evaluation` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT 'Commentaire de l''évaluateur',
  `recommandation` enum('accepter','refuser','attendre') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Recommandation de l''évaluateur',
  `evalue_par` int DEFAULT NULL COMMENT 'ID de l''utilisateur qui a évalué',
  `date_evaluation` timestamp NULL DEFAULT NULL COMMENT 'Date de l''évaluation',
  `verifie_par` int DEFAULT NULL COMMENT 'ID de l''utilisateur qui a vérifié les documents',
  `date_verification` timestamp NULL DEFAULT NULL COMMENT 'Date de vérification des documents',
  `commentaire_documents` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT 'Commentaires sur les documents',
  `eleve_cree_id` int DEFAULT NULL COMMENT 'ID de l''élève créé après inscription',
  `date_inscription` date DEFAULT NULL COMMENT 'Date d''inscription effective',
  `commentaire_traitement` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT 'Commentaire du traitement'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `depenses`
--

CREATE TABLE `depenses` (
  `id` int NOT NULL,
  `libelle` varchar(255) NOT NULL,
  `description` text,
  `montant` decimal(10,2) NOT NULL,
  `devise_id` int NOT NULL DEFAULT '1',
  `montant_devise_par_defaut` decimal(10,2) NOT NULL DEFAULT '0.00',
  `type_depense` enum('salaires','fournitures','maintenance','utilities','transport','autre') DEFAULT NULL,
  `date_depense` date NOT NULL,
  `fournisseur` varchar(255) DEFAULT NULL,
  `numero_facture` varchar(100) DEFAULT NULL,
  `mode_paiement` enum('especes','cheque','virement','mobile_money') DEFAULT NULL,
  `statut` enum('en_attente','payee','annulee') DEFAULT NULL,
  `annee_scolaire_id` int NOT NULL,
  `user_id` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Structure de la table `devises`
--

CREATE TABLE `devises` (
  `id` int NOT NULL,
  `code` varchar(3) COLLATE utf8mb4_unicode_ci NOT NULL,
  `nom` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `symbole` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL,
  `taux_conversion` decimal(15,6) NOT NULL DEFAULT '1.000000',
  `devise_par_defaut` tinyint(1) DEFAULT '0',
  `active` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- --------------------------------------------------------

--
-- Structure de la table `documents_eleve`
--

CREATE TABLE `documents_eleve` (
  `id` int NOT NULL,
  `eleve_id` int NOT NULL,
  `type_document` enum('certificat_naissance','bulletin_precedent','certificat_medical','photo_identite','carte_etudiant','certificat_scolarite','autre') NOT NULL,
  `nom_fichier` varchar(255) NOT NULL,
  `chemin_fichier` varchar(500) NOT NULL,
  `taille_fichier` int DEFAULT NULL,
  `type_mime` varchar(100) DEFAULT NULL,
  `status` enum('en_attente','valide','rejete','expire') DEFAULT 'en_attente',
  `date_upload` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `date_validation` timestamp NULL DEFAULT NULL,
  `valide_par` int DEFAULT NULL,
  `commentaire` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Structure de la table `documents_eleves`
--

CREATE TABLE `documents_eleves` (
  `id` int NOT NULL,
  `eleve_id` int NOT NULL,
  `type_document` enum('certificat_naissance','bulletin_precedent','certificat_medical','photo_identite','fiche_inscription','attestation_scolarite','releve_notes','certificat_conduite','autre') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `nom_document` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `nom_fichier` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `chemin_fichier` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `taille_fichier` int DEFAULT NULL,
  `type_mime` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `date_ajout` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `ajoute_par` int DEFAULT NULL,
  `date_verification` datetime DEFAULT NULL,
  `verifie_par` int DEFAULT NULL,
  `statut_verification` enum('en_attente','verifie','rejete') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'en_attente',
  `commentaire_verification` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `obligatoire` tinyint(1) DEFAULT '0',
  `date_expiration` date DEFAULT NULL,
  `numero_document` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `organisme_delivrance` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `eleves`
--

CREATE TABLE `eleves` (
  `id` int NOT NULL,
  `numero_eleve` varchar(20) NOT NULL,
  `numero_matricule` varchar(20) NOT NULL,
  `parent_id` int DEFAULT NULL,
  `nom` varchar(100) NOT NULL,
  `prenom` varchar(100) NOT NULL,
  `sexe` enum('M','F') NOT NULL,
  `date_naissance` date NOT NULL,
  `lieu_naissance` varchar(100) DEFAULT NULL,
  `adresse` text,
  `telephone` varchar(20) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `nom_pere` varchar(100) DEFAULT NULL,
  `nom_mere` varchar(100) DEFAULT NULL,
  `profession_pere` varchar(100) DEFAULT NULL,
  `profession_mere` varchar(100) DEFAULT NULL,
  `telephone_parent` varchar(20) DEFAULT NULL,
  `personne_contact` varchar(100) DEFAULT NULL,
  `telephone_contact` varchar(20) DEFAULT NULL,
  `relation_contact` varchar(100) DEFAULT NULL,
  `classe_id` int DEFAULT NULL,
  `annee_scolaire_id` int DEFAULT NULL,
  `photo` varchar(255) DEFAULT NULL,
  `status` enum('actif','transfere','abandonne','diplome','non-evalue','admis','evalue','non-admis','inscrit') CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT 'non-admis',
  `date_inscription` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  `email_parent` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Informations des ├®l├¿ves avec relations aux classes et ann├®es scolaires';

-- --------------------------------------------------------

--
-- Structure de la table `emploi_temps`
--

CREATE TABLE `emploi_temps` (
  `id` int NOT NULL,
  `classe_id` int NOT NULL,
  `matiere_id` int NOT NULL,
  `enseignant_id` int NOT NULL,
  `jour_semaine` enum('Lundi','Mardi','Mercredi','Jeudi','Vendredi','Samedi','Dimanche') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `heure_debut` time NOT NULL,
  `heure_fin` time NOT NULL,
  `salle` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `recurrence` enum('unique','hebdomadaire','bihebdomadaire','mensuelle') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'hebdomadaire',
  `date_debut` date DEFAULT NULL,
  `date_fin` date DEFAULT NULL,
  `status` enum('actif','suspendu','annule') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'actif',
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `annee_scolaire_id` int NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `emprunts`
--

CREATE TABLE `emprunts` (
  `id` int NOT NULL,
  `livre_id` int NOT NULL,
  `eleve_id` int NOT NULL,
  `date_emprunt` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `date_retour_prevue` date NOT NULL,
  `date_retour_effective` date DEFAULT NULL,
  `status` enum('en_cours','retourne','perdu') DEFAULT 'en_cours',
  `amende` decimal(8,2) DEFAULT '0.00'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Structure de la table `emprunts_livres`
--

CREATE TABLE `emprunts_livres` (
  `id` int NOT NULL,
  `livre_id` int NOT NULL,
  `emprunteur_type` enum('eleve','personnel') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `emprunteur_id` int NOT NULL,
  `date_emprunt` date NOT NULL,
  `date_retour_prevue` date NOT NULL,
  `date_retour_effective` date DEFAULT NULL,
  `duree_jours` int DEFAULT '14',
  `status` enum('en_cours','rendu','en_retard','perdu','annule') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'en_cours',
  `notes_emprunt` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `notes_retour` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `penalite` decimal(8,2) DEFAULT '0.00',
  `traite_par` int DEFAULT NULL,
  `rendu_par` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `etablissements`
--

CREATE TABLE `etablissements` (
  `id` int NOT NULL,
  `nom` varchar(200) NOT NULL,
  `adresse` text,
  `telephone` varchar(20) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `directeur` varchar(100) DEFAULT NULL,
  `code_etablissement` varchar(20) DEFAULT NULL,
  `province` varchar(50) DEFAULT NULL,
  `ville` varchar(50) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Structure de la table `etapes_admission`
--

CREATE TABLE `etapes_admission` (
  `id` int NOT NULL,
  `nom` varchar(100) NOT NULL,
  `description` text,
  `ordre` int NOT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Structure de la table `evaluations`
--

CREATE TABLE `evaluations` (
  `id` int NOT NULL,
  `nom` varchar(100) NOT NULL,
  `description` text,
  `type` enum('interrogation','devoir','examen','composition') NOT NULL,
  `classe_id` int NOT NULL,
  `matiere_id` int NOT NULL,
  `type_evaluation` enum('interrogation','devoir','examen','composition') NOT NULL DEFAULT 'interrogation',
  `enseignant_id` int NOT NULL,
  `date_evaluation` date NOT NULL,
  `heure_debut` time DEFAULT NULL,
  `heure_fin` time DEFAULT NULL,
  `duree_minutes` int DEFAULT NULL,
  `note_max` decimal(5,2) DEFAULT '20.00',
  `bareme` text,
  `consignes` text,
  `status` enum('programmee','en_cours','terminee','annulee') DEFAULT 'programmee',
  `coefficient` decimal(3,2) DEFAULT '1.00',
  `periode` enum('1er_trimestre','2eme_trimestre','3eme_trimestre','annuelle') NOT NULL,
  `annee_scolaire_id` int NOT NULL,
  `user_id` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Structure de la table `evaluations_admission`
--

CREATE TABLE `evaluations_admission` (
  `id` int NOT NULL,
  `demande_admission_id` int NOT NULL,
  `type_evaluation` enum('test_ecrit','entretien','examen_medical','evaluation_psychologique','test_niveau') NOT NULL,
  `date_evaluation` date NOT NULL,
  `heure_debut` time DEFAULT NULL,
  `heure_fin` time DEFAULT NULL,
  `lieu` varchar(255) DEFAULT NULL,
  `evaluateur_id` int DEFAULT NULL,
  `note_sur_20` decimal(4,2) DEFAULT NULL,
  `note_sur_100` decimal(5,2) DEFAULT NULL,
  `coefficient` decimal(3,2) DEFAULT '1.00',
  `resultat` enum('excellent','tres_bien','bien','moyen','insuffisant','nul') DEFAULT 'moyen',
  `commentaire` text,
  `decision_provisoire` enum('accepter','refuser','attendre','conditionnel') DEFAULT 'attendre',
  `conditions_acceptation` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Structure de la table `factures`
--

CREATE TABLE `factures` (
  `id` int NOT NULL,
  `numero_facture` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `date_facture` date NOT NULL,
  `eleve_id` int NOT NULL,
  `type_facture` enum('frais_scolaires','fournitures','uniforme','transport','cantine','autre') COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `montant_ht` decimal(10,2) NOT NULL,
  `tva` decimal(5,2) DEFAULT '0.00',
  `montant_ttc` decimal(10,2) NOT NULL,
  `mode_paiement` enum('especes','cheque','virement','mobile_money','') COLLATE utf8mb4_unicode_ci DEFAULT '',
  `echeance` date DEFAULT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `annee_scolaire_id` int NOT NULL,
  `user_id` int DEFAULT NULL,
  `status` enum('en_attente','payee','annulee','en_retard') COLLATE utf8mb4_unicode_ci DEFAULT 'en_attente',
  `date_paiement` date DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `frais_eleves`
--

CREATE TABLE `frais_eleves` (
  `id` int NOT NULL,
  `eleve_id` int NOT NULL,
  `annee_scolaire_id` int NOT NULL,
  `frais_inscription` decimal(10,2) DEFAULT '0.00',
  `frais_scolarite` decimal(10,2) DEFAULT '0.00',
  `reduction_accordee` decimal(5,2) DEFAULT '0.00',
  `montant_total` decimal(10,2) DEFAULT '0.00',
  `montant_paye` decimal(10,2) DEFAULT '0.00',
  `solde` decimal(10,2) DEFAULT '0.00',
  `status` enum('impaye','partiel','paye') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'impaye',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `frais_scolaires`
--

CREATE TABLE `frais_scolaires` (
  `id` int NOT NULL,
  `classe_id` int NOT NULL,
  `type_frais_id` int NOT NULL COMMENT 'ID du type de frais (clé étrangère vers type_frais.id)',
  `libelle` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `montant` decimal(10,2) NOT NULL,
  `devise_id` int DEFAULT NULL,
  `montant_devise_par_defaut` decimal(15,2) DEFAULT NULL,
  `obligatoire` tinyint(1) DEFAULT '1',
  `date_echeance` date DEFAULT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `annee_scolaire_id` int NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `incidents`
--

CREATE TABLE `incidents` (
  `id` int NOT NULL,
  `eleve_id` int NOT NULL,
  `classe_id` int DEFAULT NULL,
  `rapporte_par` int NOT NULL COMMENT 'ID du personnel',
  `date_incident` datetime NOT NULL,
  `lieu` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `temoins` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `gravite` enum('legere','moyenne','grave','tres_grave') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'moyenne',
  `status` enum('nouveau','en_cours','resolu','archive') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'nouveau',
  `notes_internes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `inscriptions`
--

CREATE TABLE `inscriptions` (
  `id` int NOT NULL,
  `eleve_id` int NOT NULL,
  `classe_id` int NOT NULL,
  `annee_scolaire_id` int NOT NULL,
  `date_inscription` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `frais_inscription_paye` decimal(10,2) DEFAULT '0.00',
  `status` enum('inscrit','en_attente','transfere','abandonne') DEFAULT 'en_attente',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Structure de la table `inscriptions_detaillees`
--

CREATE TABLE `inscriptions_detaillees` (
  `id` int NOT NULL,
  `demande_admission_id` int NOT NULL,
  `eleve_id` int NOT NULL,
  `annee_scolaire_id` int NOT NULL,
  `classe_id` int NOT NULL,
  `section_id` int DEFAULT NULL,
  `date_inscription` date NOT NULL,
  `frais_inscription_paye` decimal(10,2) DEFAULT '0.00',
  `frais_scolarite_paye` decimal(10,2) DEFAULT '0.00',
  `reduction_appliquee` decimal(5,2) DEFAULT '0.00',
  `mode_paiement` enum('especes','cheque','virement','mobile_money','carte') DEFAULT 'especes',
  `numero_recu` varchar(100) DEFAULT NULL,
  `status` enum('inscrit','en_attente_paiement','suspendu','transfere','abandonne','diplome') DEFAULT 'inscrit',
  `date_debut_scolarite` date DEFAULT NULL,
  `date_fin_scolarite` date DEFAULT NULL,
  `observations` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Structure de la table `livres`
--

CREATE TABLE `livres` (
  `id` int NOT NULL,
  `titre` varchar(200) NOT NULL,
  `auteur` varchar(100) DEFAULT NULL,
  `isbn` varchar(20) DEFAULT NULL,
  `editeur` varchar(100) DEFAULT NULL,
  `annee_publication` year DEFAULT NULL,
  `categorie_id` int DEFAULT NULL,
  `categorie` varchar(50) DEFAULT NULL,
  `nombre_pages` int DEFAULT NULL,
  `langue` varchar(50) DEFAULT 'Français',
  `resume` text,
  `nombre_exemplaires` int DEFAULT '1',
  `exemplaires_disponibles` int DEFAULT '1',
  `nombre_disponibles` int DEFAULT '1',
  `emplacement` varchar(50) DEFAULT NULL,
  `status` enum('disponible','indisponible') DEFAULT 'disponible',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `prix_achat` decimal(10,2) DEFAULT NULL,
  `date_acquisition` date DEFAULT NULL,
  `etat` enum('excellent','bon','moyen','mauvais','hors_service') DEFAULT 'bon',
  `cote` varchar(50) DEFAULT NULL,
  `notes` text
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Structure de la table `logs_scan_carte`
--

CREATE TABLE `logs_scan_carte` (
  `id` int NOT NULL,
  `carte_id` int NOT NULL,
  `eleve_id` int NOT NULL,
  `type_scan` enum('presence','solde','autre') COLLATE utf8mb4_unicode_ci NOT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` text COLLATE utf8mb4_unicode_ci,
  `donnees_scan` text COLLATE utf8mb4_unicode_ci COMMENT 'Données extraites du QR code',
  `resultat` text COLLATE utf8mb4_unicode_ci COMMENT 'Résultat de l''action effectuée',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `matieres`
--

CREATE TABLE `matieres` (
  `id` int NOT NULL,
  `nom` varchar(100) NOT NULL,
  `code` varchar(20) DEFAULT NULL,
  `coefficient` int DEFAULT '1',
  `volume_horaire` int DEFAULT '0',
  `objectifs` text,
  `niveau` enum('maternelle','primaire','secondaire') NOT NULL,
  `type` enum('obligatoire','optionnelle') NOT NULL DEFAULT 'obligatoire',
  `description` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Structure de la table `messages`
--

CREATE TABLE `messages` (
  `id` int NOT NULL,
  `expediteur_id` int NOT NULL,
  `destinataire_id` int DEFAULT NULL,
  `destinataire_type` enum('user','classe','all_students','all_teachers','all_parents','custom') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'user',
  `destinataires_custom` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT 'JSON des destinataires personnalisés',
  `sujet` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `contenu` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `type_message` enum('info','urgent','rappel','felicitation','convocation') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'info',
  `priorite` enum('basse','normale','haute','critique') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'normale',
  `date_envoi` datetime DEFAULT NULL,
  `programme` tinyint(1) DEFAULT '0',
  `date_programmee` datetime DEFAULT NULL,
  `status` enum('brouillon','envoye','programme','annule') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'brouillon',
  `lu_par` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT 'JSON des IDs qui ont lu',
  `accuse_reception` tinyint(1) DEFAULT '0',
  `fichiers_joints` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT 'JSON des fichiers',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `modeles_frais`
--

CREATE TABLE `modeles_frais` (
  `id` int NOT NULL,
  `nom` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `niveau` enum('maternelle','primaire','secondaire','tous') COLLATE utf8mb4_unicode_ci NOT NULL,
  `type_etablissement` enum('public','prive','tous') COLLATE utf8mb4_unicode_ci DEFAULT 'tous',
  `frais_data` json NOT NULL,
  `created_by` int NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  `is_active` tinyint(1) DEFAULT '1',
  `usage_count` int DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `mouvements_caisse`
--

CREATE TABLE `mouvements_caisse` (
  `id` int NOT NULL,
  `session_caisse_id` int NOT NULL,
  `type_mouvement` enum('entree','sortie') COLLATE utf8mb4_unicode_ci NOT NULL,
  `categorie` enum('paiement_eleve','don','subvention','depense_ecole','retrait','versement','autre') COLLATE utf8mb4_unicode_ci NOT NULL,
  `libelle` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `montant` decimal(15,2) NOT NULL,
  `devise_id` int NOT NULL,
  `reference` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `date_mouvement` datetime NOT NULL,
  `user_id` int NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `notes`
--

CREATE TABLE `notes` (
  `id` int NOT NULL,
  `evaluation_id` int NOT NULL,
  `eleve_id` int NOT NULL,
  `note` decimal(5,2) NOT NULL,
  `observation` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Structure de la table `notifications`
--

CREATE TABLE `notifications` (
  `id` int NOT NULL,
  `user_id` int NOT NULL,
  `titre` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `contenu` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `type` enum('info','success','warning','error','message') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'info',
  `icone` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'fas fa-info-circle',
  `lien` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `lu` tinyint(1) DEFAULT '0',
  `date_lecture` datetime DEFAULT NULL,
  `expire_le` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `notifications_destinataires`
--

CREATE TABLE `notifications_destinataires` (
  `id` int NOT NULL,
  `notification_id` int NOT NULL,
  `eleve_id` int NOT NULL,
  `status` enum('pending','sent','failed','delivered','read') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'pending',
  `sent_at` timestamp NULL DEFAULT NULL,
  `delivered_at` timestamp NULL DEFAULT NULL,
  `read_at` timestamp NULL DEFAULT NULL,
  `error_message` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `notifications_parents`
--

CREATE TABLE `notifications_parents` (
  `id` int NOT NULL,
  `absence_id` int NOT NULL,
  `parent_id` int DEFAULT NULL,
  `type_notification` enum('sms','email') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `message` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` enum('pending','sent','failed') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'pending',
  `sent_at` timestamp NULL DEFAULT NULL,
  `error_message` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_by` int NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `notifications_recouvrement`
--

CREATE TABLE `notifications_recouvrement` (
  `id` int NOT NULL,
  `type_notification` enum('sms','email','lettre') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `sujet` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `message` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `campagne_id` int DEFAULT NULL,
  `annee_scolaire_id` int NOT NULL,
  `status` enum('pending','sent','failed','cancelled') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'pending',
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `notifications_suivi`
--

CREATE TABLE `notifications_suivi` (
  `id` int NOT NULL,
  `eleve_id` int NOT NULL,
  `type_notification` enum('evaluation_pending','decision_pending','inscription_pending','paiement_pending','document_missing','reminder') NOT NULL,
  `titre` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `priorite` enum('basse','normale','haute','urgente') DEFAULT 'normale',
  `status` enum('non_lue','lue','traitee') DEFAULT 'non_lue',
  `destinataire_id` int DEFAULT NULL,
  `date_envoi` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `date_lecture` timestamp NULL DEFAULT NULL,
  `date_traitement` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Structure de la table `paiements`
--

CREATE TABLE `paiements` (
  `id` int NOT NULL,
  `eleve_id` int NOT NULL,
  `type_frais_id` int NOT NULL,
  `montant` decimal(10,2) NOT NULL,
  `devise_id` int DEFAULT NULL,
  `montant_devise_par_defaut` decimal(15,2) DEFAULT NULL,
  `status` enum('en_attente','valide','annule') DEFAULT 'valide',
  `date_paiement` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `mois_concerne` varchar(20) DEFAULT NULL,
  `annee_scolaire_id` int NOT NULL,
  `recu_numero` varchar(50) DEFAULT NULL,
  `mode_paiement` enum('especes','cheque','virement','mobile_money') DEFAULT 'especes',
  `reference` varchar(100) DEFAULT NULL,
  `observation` text,
  `user_id` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Structure de la table `paiements_cartes`
--

CREATE TABLE `paiements_cartes` (
  `id` int NOT NULL,
  `carte_id` int NOT NULL,
  `montant` decimal(10,2) NOT NULL,
  `devise_id` int DEFAULT NULL,
  `montant_devise_par_defaut` decimal(15,2) DEFAULT NULL,
  `type_paiement` enum('especes','carte_bancaire','mobile_money','virement') COLLATE utf8mb4_unicode_ci NOT NULL,
  `reference` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` enum('en_attente','valide','annule','refuse') COLLATE utf8mb4_unicode_ci DEFAULT 'en_attente',
  `date_paiement` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `user_id` int DEFAULT NULL,
  `observations` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `parametres_admission`
--

CREATE TABLE `parametres_admission` (
  `id` int NOT NULL,
  `annee_scolaire_id` int NOT NULL,
  `delai_traitement` int NOT NULL DEFAULT '7' COMMENT 'Délai de traitement en jours',
  `auto_refus` int NOT NULL DEFAULT '30' COMMENT 'Délai avant refus automatique en jours',
  `notifications_email` tinyint(1) NOT NULL DEFAULT '1' COMMENT 'Notifications par email activées',
  `validation_auto` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'Validation automatique activée',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Paramètres généraux pour les admissions';

-- --------------------------------------------------------

--
-- Structure de la table `parametres_bibliotheque`
--

CREATE TABLE `parametres_bibliotheque` (
  `id` int NOT NULL,
  `cle` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `valeur` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `type` enum('text','number','boolean','json') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'text',
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `parametres_cartes`
--

CREATE TABLE `parametres_cartes` (
  `id` int NOT NULL,
  `nom_ecole` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'École Sinfinity',
  `logo_ecole` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `couleur_principale` varchar(7) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '#1e40af',
  `couleur_secondaire` varchar(7) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '#3b82f6',
  `couleur_texte` varchar(7) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '#1f2937',
  `format_carte` enum('pvc','pdf') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pdf',
  `dimensions` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '85.6x54mm',
  `qr_code_size` int NOT NULL DEFAULT '100',
  `include_photo` tinyint(1) NOT NULL DEFAULT '1',
  `include_qr_code` tinyint(1) NOT NULL DEFAULT '1',
  `include_barcode` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `parametres_recouvrement`
--

CREATE TABLE `parametres_recouvrement` (
  `id` int NOT NULL,
  `cle` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `valeur` text COLLATE utf8mb4_unicode_ci,
  `description` text COLLATE utf8mb4_unicode_ci,
  `type` enum('string','number','boolean','json') COLLATE utf8mb4_unicode_ci DEFAULT 'string',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `parents`
--

CREATE TABLE `parents` (
  `id` int NOT NULL,
  `nom` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `prenom` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `telephone` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `adresse` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `profession` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `penalites_bibliotheque`
--

CREATE TABLE `penalites_bibliotheque` (
  `id` int NOT NULL,
  `emprunt_id` int NOT NULL,
  `type_penalite` enum('retard','deterioration','perte') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `montant` decimal(8,2) NOT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `status` enum('impayee','payee','annulee') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'impayee',
  `date_penalite` date NOT NULL,
  `date_paiement` date DEFAULT NULL,
  `traite_par` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `personnel`
--

CREATE TABLE `personnel` (
  `id` int NOT NULL,
  `matricule` varchar(20) NOT NULL,
  `nom` varchar(100) NOT NULL,
  `prenom` varchar(100) NOT NULL,
  `sexe` enum('M','F') NOT NULL,
  `date_naissance` date DEFAULT NULL,
  `lieu_naissance` varchar(100) DEFAULT NULL,
  `adresse` text,
  `telephone` varchar(20) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `fonction` enum('enseignant','directeur','sous_directeur','secretaire','comptable','surveillant','gardien','autre') NOT NULL,
  `specialite` varchar(100) DEFAULT NULL,
  `diplome` varchar(100) DEFAULT NULL,
  `date_embauche` date DEFAULT NULL,
  `salaire_base` decimal(10,2) DEFAULT NULL,
  `status` enum('actif','suspendu','demissionne') DEFAULT 'actif',
  `user_id` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Structure de la table `presences_qr`
--

CREATE TABLE `presences_qr` (
  `id` int NOT NULL,
  `eleve_id` int NOT NULL,
  `carte_id` int NOT NULL,
  `date_presence` date NOT NULL,
  `heure_entree` time DEFAULT NULL,
  `heure_sortie` time DEFAULT NULL,
  `type_scan` enum('entree','sortie') NOT NULL,
  `lieu_scan` varchar(100) DEFAULT NULL,
  `scanne_par` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Structure de la table `recompenses`
--

CREATE TABLE `recompenses` (
  `id` int NOT NULL,
  `eleve_id` int NOT NULL,
  `classe_id` int DEFAULT NULL,
  `type_recompense` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `motif` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `date_recompense` date NOT NULL,
  `attribuee_par` int NOT NULL COMMENT 'ID du personnel',
  `valeur_points` int DEFAULT '0',
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `parent_informe` tinyint(1) DEFAULT '0',
  `date_information_parent` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `reservations_livres`
--

CREATE TABLE `reservations_livres` (
  `id` int NOT NULL,
  `livre_id` int NOT NULL,
  `reserver_type` enum('eleve','personnel') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `reserver_id` int NOT NULL,
  `date_reservation` date NOT NULL,
  `date_expiration` date NOT NULL,
  `status` enum('active','expiree','annulee','convertie') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'active',
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `traite_par` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `roles`
--

CREATE TABLE `roles` (
  `id` int NOT NULL,
  `nom` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `actif` tinyint(1) DEFAULT '1' COMMENT '1=actif, 0=inactif',
  `date_creation` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `date_modification` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  `permissions` json DEFAULT NULL COMMENT 'Permissions granulaires au format JSON'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- --------------------------------------------------------

--
-- Structure de la table `sanctions`
--

CREATE TABLE `sanctions` (
  `id` int NOT NULL,
  `eleve_id` int NOT NULL,
  `type_sanction` enum('avertissement','blame','exclusion_temporaire','exclusion_definitive','travaux_supplementaires') NOT NULL,
  `motif` text NOT NULL,
  `date_sanction` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `duree_jours` int DEFAULT NULL,
  `enseignant_id` int DEFAULT NULL,
  `status` enum('active','levee') DEFAULT 'active',
  `observation` text
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Structure de la table `sections`
--

CREATE TABLE `sections` (
  `id` int NOT NULL,
  `nom` varchar(100) NOT NULL,
  `niveau` enum('primaire','secondaire') NOT NULL,
  `description` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Structure de la table `sessions_caisse`
--

CREATE TABLE `sessions_caisse` (
  `id` int NOT NULL,
  `caisse_id` int NOT NULL,
  `user_id` int NOT NULL,
  `date_ouverture` datetime NOT NULL,
  `date_fermeture` datetime DEFAULT NULL,
  `solde_ouverture` decimal(15,2) NOT NULL,
  `solde_fermeture` decimal(15,2) DEFAULT NULL,
  `statut` enum('ouverte','fermee') COLLATE utf8mb4_unicode_ci DEFAULT 'ouverte',
  `observation_ouverture` text COLLATE utf8mb4_unicode_ci,
  `observation_fermeture` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `sms_logs`
--

CREATE TABLE `sms_logs` (
  `id` int NOT NULL,
  `expediteur_id` int NOT NULL,
  `destinataire_telephone` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `destinataire_nom` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `message` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `type_sms` enum('info','urgent','rappel','absence','retard','discipline') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'info',
  `cout` decimal(8,2) DEFAULT NULL,
  `status` enum('en_attente','envoye','echec','livre') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'en_attente',
  `provider_response` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `date_envoi` datetime DEFAULT NULL,
  `date_livraison` datetime DEFAULT NULL,
  `tentatives` int DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `solvabilite_eleves`
--

CREATE TABLE `solvabilite_eleves` (
  `id` int NOT NULL,
  `eleve_id` int NOT NULL,
  `annee_scolaire_id` int NOT NULL,
  `total_frais` decimal(10,2) DEFAULT '0.00',
  `total_paye` decimal(10,2) DEFAULT '0.00',
  `solde_restant` decimal(10,2) DEFAULT '0.00',
  `pourcentage_paye` decimal(5,2) DEFAULT '0.00',
  `status_solvabilite` enum('solvable','partiellement_solvable','non_solvable') DEFAULT 'non_solvable',
  `derniere_maj` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Structure de la table `suivi_etapes_admission`
--

CREATE TABLE `suivi_etapes_admission` (
  `id` int NOT NULL,
  `demande_admission_id` int NOT NULL,
  `etape_id` int NOT NULL,
  `status` enum('en_attente','en_cours','terminee','annulee') DEFAULT 'en_attente',
  `date_debut` timestamp NULL DEFAULT NULL,
  `date_fin` timestamp NULL DEFAULT NULL,
  `user_id` int DEFAULT NULL,
  `commentaire` text,
  `documents_requis` text,
  `documents_fournis` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Structure de la table `suivi_scolaire`
--

CREATE TABLE `suivi_scolaire` (
  `id` int NOT NULL,
  `eleve_id` int NOT NULL,
  `annee_scolaire_id` int NOT NULL,
  `classe_id` int NOT NULL,
  `trimestre` enum('1er_trimestre','2eme_trimestre','3eme_trimestre','annuel') NOT NULL,
  `moyenne_generale` decimal(4,2) DEFAULT NULL,
  `rang_classe` int DEFAULT NULL,
  `effectif_classe` int DEFAULT NULL,
  `appreciation` text,
  `decision_conseil` enum('admis','admis_avec_reserves','redouble','exclu') DEFAULT 'admis',
  `commentaire_conseil` text,
  `date_conseil` date DEFAULT NULL,
  `signature_prof_principal` varchar(100) DEFAULT NULL,
  `signature_directeur` varchar(100) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Structure de la table `system_settings`
--

CREATE TABLE `system_settings` (
  `id` int NOT NULL,
  `cle` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `valeur` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `type` enum('text','number','boolean','email','url','textarea','select') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'text',
  `options` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT 'JSON pour les select',
  `categorie` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'general',
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `templates_messages`
--

CREATE TABLE `templates_messages` (
  `id` int NOT NULL,
  `nom` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `sujet` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `contenu` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `type` enum('email','sms','notification','annonce') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'email',
  `categorie` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `variables` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT 'JSON des variables disponibles',
  `actif` tinyint(1) DEFAULT '1',
  `created_by` int NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `temp_documents_backup`
--

CREATE TABLE `temp_documents_backup` (
  `id` int NOT NULL DEFAULT '0',
  `certificat_naissance` enum('non_fourni','fourni','verifie','rejete') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'non_fourni',
  `bulletin_precedent` enum('non_fourni','fourni','verifie','rejete') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'non_fourni',
  `certificat_medical` enum('non_fourni','fourni','verifie','rejete') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'non_fourni',
  `photo_identite` enum('non_fourni','fourni','verifie','rejete') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'non_fourni',
  `autres_documents` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Structure de la table `transactions_cartes`
--

CREATE TABLE `transactions_cartes` (
  `id` int NOT NULL,
  `carte_id` int NOT NULL,
  `type_transaction` enum('debit','credit','recharge','remboursement') COLLATE utf8mb4_unicode_ci NOT NULL,
  `montant` decimal(10,2) NOT NULL,
  `solde_avant` decimal(10,2) NOT NULL,
  `solde_apres` decimal(10,2) NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `reference_paiement` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_id` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `transfers`
--

CREATE TABLE `transfers` (
  `id` int NOT NULL,
  `eleve_id` int NOT NULL,
  `type_mouvement` enum('transfert_entrant','transfert_sortant','sortie_definitive') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `ecole_origine` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ecole_destination` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `classe_origine_id` int DEFAULT NULL,
  `classe_destination_id` int DEFAULT NULL,
  `motif` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `date_demande` date NOT NULL,
  `date_effective` date DEFAULT NULL,
  `statut` enum('en_attente','approuve','rejete','complete') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'en_attente',
  `documents_requis` json DEFAULT NULL,
  `documents_fournis` json DEFAULT NULL,
  `frais_transfert` decimal(10,2) DEFAULT '0.00',
  `frais_payes` decimal(10,2) DEFAULT '0.00',
  `observations` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `approuve_par` int DEFAULT NULL,
  `date_approbation` datetime DEFAULT NULL,
  `traite_par` int DEFAULT NULL,
  `date_traitement` datetime DEFAULT NULL,
  `certificat_genere` tinyint(1) DEFAULT '0',
  `numero_certificat` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `transferts`
--

CREATE TABLE `transferts` (
  `id` int NOT NULL,
  `eleve_id` int NOT NULL,
  `classe_origine_id` int NOT NULL,
  `classe_destination_id` int DEFAULT NULL,
  `ecole_destination` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `motif` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `date_demande` date NOT NULL,
  `date_transfert` date DEFAULT NULL,
  `status` enum('en_attente','approuve','refuse','effectue') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'en_attente',
  `approuve_par` int DEFAULT NULL,
  `date_approbation` timestamp NULL DEFAULT NULL,
  `observations` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `transferts_sorties`
--

CREATE TABLE `transferts_sorties` (
  `id` int NOT NULL,
  `eleve_id` int NOT NULL,
  `annee_scolaire_id` int NOT NULL,
  `type_mouvement` enum('transfert','sortie_definitive','abandon','exclusion') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `motif` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `date_demande` date NOT NULL,
  `date_effective` date DEFAULT NULL,
  `ecole_destination` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `adresse_destination` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `contact_destination` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `telephone_destination` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` enum('en_attente','approuve','rejete','effectue') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'en_attente',
  `traite_par` int DEFAULT NULL,
  `date_traitement` timestamp NULL DEFAULT NULL,
  `observations_demande` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `observations_traitement` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `document_justificatif` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `certificat_genere` tinyint(1) DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `transfer_documents`
--

CREATE TABLE `transfer_documents` (
  `id` int NOT NULL,
  `transfer_id` int NOT NULL,
  `nom_document` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `type_document` enum('bulletin','certificat_scolarite','acte_naissance','photo','autre') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `chemin_fichier` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `taille_fichier` int DEFAULT NULL,
  `type_mime` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `obligatoire` tinyint(1) DEFAULT '0',
  `fourni` tinyint(1) DEFAULT '0',
  `date_upload` datetime DEFAULT NULL,
  `uploaded_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `transfer_fees`
--

CREATE TABLE `transfer_fees` (
  `id` int NOT NULL,
  `transfer_id` int NOT NULL,
  `type_frais` enum('frais_transfert','frais_certificat','frais_dossier','autre') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `libelle` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `montant` decimal(10,2) NOT NULL,
  `paye` tinyint(1) DEFAULT '0',
  `date_paiement` datetime DEFAULT NULL,
  `mode_paiement` enum('especes','virement','cheque','mobile_money') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'especes',
  `reference_paiement` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `transfer_history`
--

CREATE TABLE `transfer_history` (
  `id` int NOT NULL,
  `transfer_id` int NOT NULL,
  `action` enum('creation','modification','approbation','rejet','completion','annulation') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `ancien_statut` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `nouveau_statut` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `commentaire` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `user_id` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `types_sanctions`
--

CREATE TABLE `types_sanctions` (
  `id` int NOT NULL,
  `nom` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `gravite` enum('legere','moyenne','grave','tres_grave') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'moyenne',
  `duree_defaut` int DEFAULT NULL COMMENT 'Durée en jours',
  `couleur` varchar(7) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT '#ffc107',
  `active` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `type_frais`
--

CREATE TABLE `type_frais` (
  `id` int NOT NULL,
  `nom` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Nom du type de frais (ex: Inscription, Mensualité, Examen)',
  `description` text COLLATE utf8mb4_unicode_ci COMMENT 'Description détaillée du type de frais',
  `annee_scolaire_id` int NOT NULL COMMENT 'ID de l''année scolaire à laquelle ce type de frais est attaché',
  `actif` tinyint(1) DEFAULT '1' COMMENT '1 = actif, 0 = inactif',
  `priorite` int NOT NULL DEFAULT '10' COMMENT 'Priorité de paiement (plus le chiffre est bas, plus la priorité est haute)',
  `date_creation` timestamp NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Date de création du type de frais',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Types de frais programmables par année scolaire';

-- --------------------------------------------------------

--
-- Structure de la table `users`
--

CREATE TABLE `users` (
  `id` int NOT NULL,
  `username` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `password` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `nom` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `prenom` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `telephone` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `role_id` int DEFAULT NULL,
  `status` enum('actif','inactif') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'actif',
  `photo` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `adresse` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `date_naissance` date DEFAULT NULL,
  `genre` enum('M','F') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `derniere_connexion` timestamp NULL DEFAULT NULL,
  `tentatives_connexion` int DEFAULT '0',
  `compte_verrouille` tinyint(1) DEFAULT '0',
  `date_verrouillage` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- --------------------------------------------------------

--
-- Structure de la table `user_actions_log`
--

CREATE TABLE `user_actions_log` (
  `id` int NOT NULL,
  `user_id` int NOT NULL,
  `action` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `module` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `details` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `target_id` int DEFAULT NULL,
  `ip_address` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


--
-- Index pour les tables déchargées
--

--
-- Index pour la table `absences`
--
ALTER TABLE `absences`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_eleve` (`eleve_id`),
  ADD KEY `idx_classe` (`classe_id`),
  ADD KEY `idx_date` (`date_absence`),
  ADD KEY `idx_type` (`type_absence`),
  ADD KEY `valide_par` (`valide_par`);

--
-- Index pour la table `annees_scolaires`
--
ALTER TABLE `annees_scolaires`
  ADD PRIMARY KEY (`id`);

--
-- Index pour la table `annonces`
--
ALTER TABLE `annonces`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_auteur` (`auteur_id`),
  ADD KEY `idx_type` (`type_annonce`),
  ADD KEY `idx_cible` (`cible`),
  ADD KEY `idx_classe` (`classe_id`),
  ADD KEY `idx_date_pub` (`date_publication`),
  ADD KEY `idx_active` (`active`);

--
-- Index pour la table `caisses`
--
ALTER TABLE `caisses`
  ADD PRIMARY KEY (`id`),
  ADD KEY `devise_id` (`devise_id`),
  ADD KEY `annee_scolaire_id` (`annee_scolaire_id`);

--
-- Index pour la table `campagnes_cibles_dettes`
--
ALTER TABLE `campagnes_cibles_dettes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `campagne_id` (`campagne_id`),
  ADD KEY `eleve_id` (`eleve_id`),
  ADD KEY `status` (`status`);

--
-- Index pour la table `campagnes_recouvrement`
--
ALTER TABLE `campagnes_recouvrement`
  ADD PRIMARY KEY (`id`),
  ADD KEY `annee_scolaire_id` (`annee_scolaire_id`),
  ADD KEY `status` (`status`),
  ADD KEY `created_by` (`created_by`);

--
-- Index pour la table `cartes_eleves`
--
ALTER TABLE `cartes_eleves`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `numero_carte` (`numero_carte`),
  ADD KEY `idx_eleve_id` (`eleve_id`),
  ADD KEY `idx_numero_carte` (`numero_carte`),
  ADD KEY `idx_status` (`status`);

--
-- Index pour la table `carte_eleve`
--
ALTER TABLE `carte_eleve`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_carte_annee` (`eleve_id`,`annee_scolaire_id`),
  ADD UNIQUE KEY `unique_numero_carte` (`numero_carte`),
  ADD KEY `idx_eleve_id` (`eleve_id`),
  ADD KEY `idx_annee_scolaire` (`annee_scolaire_id`),
  ADD KEY `idx_statut` (`statut`),
  ADD KEY `idx_qr_code` (`qr_code`(100)),
  ADD KEY `idx_qr_code_path` (`qr_code_path`);

--
-- Index pour la table `carte_eleve_historique`
--
ALTER TABLE `carte_eleve_historique`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_eleve_id` (`eleve_id`),
  ADD KEY `idx_annee_scolaire` (`annee_scolaire_id`),
  ADD KEY `idx_carte_id` (`carte_id`);

--
-- Index pour la table `categories_livres`
--
ALTER TABLE `categories_livres`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `nom` (`nom`);

--
-- Index pour la table `classes`
--
ALTER TABLE `classes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `annee_scolaire_id` (`annee_scolaire_id`),
  ADD KEY `fk_titulaire` (`titulaire_id`);

--
-- Index pour la table `commandes`
--
ALTER TABLE `commandes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `idx_eleve_id` (`eleve_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_annee_scolaire` (`annee_scolaire_id`),
  ADD KEY `facture_id` (`facture_id`);

--
-- Index pour la table `criteres_admission`
--
ALTER TABLE `criteres_admission`
  ADD PRIMARY KEY (`id`),
  ADD KEY `annee_scolaire_id` (`annee_scolaire_id`),
  ADD KEY `niveau` (`niveau`);

--
-- Index pour la table `criteres_admission_classes`
--
ALTER TABLE `criteres_admission_classes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `annee_scolaire_id` (`annee_scolaire_id`),
  ADD KEY `classe_id` (`classe_id`);

--
-- Index pour la table `decisions_admission`
--
ALTER TABLE `decisions_admission`
  ADD PRIMARY KEY (`id`),
  ADD KEY `decideur_id` (`decideur_id`),
  ADD KEY `idx_decisions_demande` (`demande_admission_id`);

--
-- Index pour la table `demandes_admission`
--
ALTER TABLE `demandes_admission`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `numero_demande` (`numero_demande`),
  ADD UNIQUE KEY `unique_numero_demande` (`numero_demande`),
  ADD KEY `idx_annee_scolaire` (`annee_scolaire_id`),
  ADD KEY `idx_classe_demandee` (`classe_demandee_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_priorite` (`priorite`),
  ADD KEY `idx_date_entretien` (`date_entretien`),
  ADD KEY `idx_nom_eleve` (`nom_eleve`),
  ADD KEY `traite_par` (`traite_par`),
  ADD KEY `idx_note_evaluation` (`note_evaluation`),
  ADD KEY `idx_recommandation` (`recommandation`),
  ADD KEY `idx_evalue_par` (`evalue_par`),
  ADD KEY `idx_date_evaluation` (`date_evaluation`),
  ADD KEY `idx_verifie_par` (`verifie_par`),
  ADD KEY `idx_eleve_cree_id` (`eleve_cree_id`);

--
-- Index pour la table `depenses`
--
ALTER TABLE `depenses`
  ADD PRIMARY KEY (`id`),
  ADD KEY `devise_id` (`devise_id`);

--
-- Index pour la table `devises`
--
ALTER TABLE `devises`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `code` (`code`),
  ADD KEY `idx_code` (`code`),
  ADD KEY `idx_active` (`active`);

--
-- Index pour la table `documents_eleve`
--
ALTER TABLE `documents_eleve`
  ADD PRIMARY KEY (`id`),
  ADD KEY `valide_par` (`valide_par`),
  ADD KEY `idx_documents_eleve` (`eleve_id`);

--
-- Index pour la table `documents_eleves`
--
ALTER TABLE `documents_eleves`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_eleve_id` (`eleve_id`),
  ADD KEY `idx_type_document` (`type_document`),
  ADD KEY `idx_statut_verification` (`statut_verification`),
  ADD KEY `idx_date_ajout` (`date_ajout`);

--
-- Index pour la table `eleves`
--
ALTER TABLE `eleves`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `numero_matricule` (`numero_matricule`),
  ADD KEY `idx_parent_id` (`parent_id`),
  ADD KEY `idx_classe_id` (`classe_id`),
  ADD KEY `idx_annee_scolaire_id` (`annee_scolaire_id`);

--
-- Index pour la table `emploi_temps`
--
ALTER TABLE `emploi_temps`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_classe_id` (`classe_id`),
  ADD KEY `idx_matiere_id` (`matiere_id`),
  ADD KEY `idx_enseignant_id` (`enseignant_id`),
  ADD KEY `idx_jour_semaine` (`jour_semaine`),
  ADD KEY `idx_heure_debut` (`heure_debut`),
  ADD KEY `idx_annee_scolaire_id` (`annee_scolaire_id`),
  ADD KEY `idx_status` (`status`);

--
-- Index pour la table `emprunts`
--
ALTER TABLE `emprunts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `livre_id` (`livre_id`),
  ADD KEY `eleve_id` (`eleve_id`);

--
-- Index pour la table `emprunts_livres`
--
ALTER TABLE `emprunts_livres`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_livre_id` (`livre_id`),
  ADD KEY `idx_emprunteur` (`emprunteur_type`,`emprunteur_id`),
  ADD KEY `idx_date_emprunt` (`date_emprunt`),
  ADD KEY `idx_date_retour_prevue` (`date_retour_prevue`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_traite_par` (`traite_par`),
  ADD KEY `rendu_par` (`rendu_par`);

--
-- Index pour la table `etablissements`
--
ALTER TABLE `etablissements`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `code_etablissement` (`code_etablissement`);

--
-- Index pour la table `etapes_admission`
--
ALTER TABLE `etapes_admission`
  ADD PRIMARY KEY (`id`);

--
-- Index pour la table `evaluations`
--
ALTER TABLE `evaluations`
  ADD PRIMARY KEY (`id`),
  ADD KEY `classe_id` (`classe_id`),
  ADD KEY `matiere_id` (`matiere_id`),
  ADD KEY `enseignant_id` (`enseignant_id`),
  ADD KEY `annee_scolaire_id` (`annee_scolaire_id`);

--
-- Index pour la table `evaluations_admission`
--
ALTER TABLE `evaluations_admission`
  ADD PRIMARY KEY (`id`),
  ADD KEY `evaluateur_id` (`evaluateur_id`),
  ADD KEY `idx_evaluations_demande` (`demande_admission_id`);

--
-- Index pour la table `factures`
--
ALTER TABLE `factures`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `numero_facture` (`numero_facture`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `idx_numero_facture` (`numero_facture`),
  ADD KEY `idx_date_facture` (`date_facture`),
  ADD KEY `idx_eleve_id` (`eleve_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_annee_scolaire` (`annee_scolaire_id`);

--
-- Index pour la table `frais_eleves`
--
ALTER TABLE `frais_eleves`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_eleve_id` (`eleve_id`),
  ADD KEY `idx_annee_scolaire_id` (`annee_scolaire_id`),
  ADD KEY `idx_status` (`status`);

--
-- Index pour la table `frais_scolaires`
--
ALTER TABLE `frais_scolaires`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_classe_type` (`classe_id`),
  ADD KEY `idx_annee_scolaire` (`annee_scolaire_id`),
  ADD KEY `fk_frais_devise` (`devise_id`),
  ADD KEY `idx_type_frais_id` (`type_frais_id`);

--
-- Index pour la table `incidents`
--
ALTER TABLE `incidents`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_eleve` (`eleve_id`),
  ADD KEY `idx_classe` (`classe_id`),
  ADD KEY `idx_rapporte_par` (`rapporte_par`),
  ADD KEY `idx_date` (`date_incident`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_gravite` (`gravite`);

--
-- Index pour la table `inscriptions`
--
ALTER TABLE `inscriptions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_inscription` (`eleve_id`,`annee_scolaire_id`),
  ADD KEY `classe_id` (`classe_id`),
  ADD KEY `annee_scolaire_id` (`annee_scolaire_id`);

--
-- Index pour la table `inscriptions_detaillees`
--
ALTER TABLE `inscriptions_detaillees`
  ADD PRIMARY KEY (`id`),
  ADD KEY `demande_admission_id` (`demande_admission_id`),
  ADD KEY `annee_scolaire_id` (`annee_scolaire_id`),
  ADD KEY `classe_id` (`classe_id`),
  ADD KEY `idx_inscriptions_eleve` (`eleve_id`);

--
-- Index pour la table `livres`
--
ALTER TABLE `livres`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_categorie_id` (`categorie_id`),
  ADD KEY `idx_isbn` (`isbn`),
  ADD KEY `idx_titre` (`titre`),
  ADD KEY `idx_auteur` (`auteur`),
  ADD KEY `idx_status` (`status`);

--
-- Index pour la table `logs_scan_carte`
--
ALTER TABLE `logs_scan_carte`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_carte_id` (`carte_id`),
  ADD KEY `idx_eleve_id` (`eleve_id`),
  ADD KEY `idx_type_scan` (`type_scan`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Index pour la table `matieres`
--
ALTER TABLE `matieres`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `code` (`code`);

--
-- Index pour la table `messages`
--
ALTER TABLE `messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_expediteur` (`expediteur_id`),
  ADD KEY `idx_destinataire` (`destinataire_id`),
  ADD KEY `idx_type` (`destinataire_type`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_date_envoi` (`date_envoi`);

--
-- Index pour la table `modeles_frais`
--
ALTER TABLE `modeles_frais`
  ADD PRIMARY KEY (`id`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `idx_niveau` (`niveau`),
  ADD KEY `idx_active` (`is_active`);

--
-- Index pour la table `mouvements_caisse`
--
ALTER TABLE `mouvements_caisse`
  ADD PRIMARY KEY (`id`),
  ADD KEY `session_caisse_id` (`session_caisse_id`),
  ADD KEY `devise_id` (`devise_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Index pour la table `notes`
--
ALTER TABLE `notes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_note` (`evaluation_id`,`eleve_id`),
  ADD KEY `eleve_id` (`eleve_id`);

--
-- Index pour la table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user` (`user_id`),
  ADD KEY `idx_lu` (`lu`),
  ADD KEY `idx_type` (`type`),
  ADD KEY `idx_created` (`created_at`);

--
-- Index pour la table `notifications_destinataires`
--
ALTER TABLE `notifications_destinataires`
  ADD PRIMARY KEY (`id`),
  ADD KEY `notification_id` (`notification_id`),
  ADD KEY `eleve_id` (`eleve_id`),
  ADD KEY `status` (`status`);

--
-- Index pour la table `notifications_parents`
--
ALTER TABLE `notifications_parents`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_absence_id` (`absence_id`),
  ADD KEY `idx_parent_id` (`parent_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_type` (`type_notification`),
  ADD KEY `idx_created_at` (`created_at`),
  ADD KEY `created_by` (`created_by`);

--
-- Index pour la table `notifications_recouvrement`
--
ALTER TABLE `notifications_recouvrement`
  ADD PRIMARY KEY (`id`),
  ADD KEY `campagne_id` (`campagne_id`),
  ADD KEY `annee_scolaire_id` (`annee_scolaire_id`),
  ADD KEY `type_notification` (`type_notification`),
  ADD KEY `status` (`status`),
  ADD KEY `created_by` (`created_by`);

--
-- Index pour la table `notifications_suivi`
--
ALTER TABLE `notifications_suivi`
  ADD PRIMARY KEY (`id`),
  ADD KEY `destinataire_id` (`destinataire_id`),
  ADD KEY `idx_notifications_eleve` (`eleve_id`);

--
-- Index pour la table `paiements`
--
ALTER TABLE `paiements`
  ADD PRIMARY KEY (`id`),
  ADD KEY `eleve_id` (`eleve_id`),
  ADD KEY `annee_scolaire_id` (`annee_scolaire_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `idx_type_frais_id_temp` (`type_frais_id`);

--
-- Index pour la table `paiements_cartes`
--
ALTER TABLE `paiements_cartes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `idx_carte_id` (`carte_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_date_paiement` (`date_paiement`),
  ADD KEY `fk_paiements_cartes_devise` (`devise_id`);

--
-- Index pour la table `parametres_admission`
--
ALTER TABLE `parametres_admission`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_annee_scolaire` (`annee_scolaire_id`),
  ADD KEY `fk_parametres_admission_annee_scolaire` (`annee_scolaire_id`);

--
-- Index pour la table `parametres_bibliotheque`
--
ALTER TABLE `parametres_bibliotheque`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `cle` (`cle`);

--
-- Index pour la table `parametres_cartes`
--
ALTER TABLE `parametres_cartes`
  ADD PRIMARY KEY (`id`);

--
-- Index pour la table `parametres_recouvrement`
--
ALTER TABLE `parametres_recouvrement`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `cle` (`cle`);

--
-- Index pour la table `parents`
--
ALTER TABLE `parents`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_telephone` (`telephone`),
  ADD KEY `idx_email` (`email`);

--
-- Index pour la table `penalites_bibliotheque`
--
ALTER TABLE `penalites_bibliotheque`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_emprunt_id` (`emprunt_id`),
  ADD KEY `idx_type_penalite` (`type_penalite`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_date_penalite` (`date_penalite`),
  ADD KEY `traite_par` (`traite_par`);

--
-- Index pour la table `personnel`
--
ALTER TABLE `personnel`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `matricule` (`matricule`),
  ADD KEY `user_id` (`user_id`);

--
-- Index pour la table `presences_qr`
--
ALTER TABLE `presences_qr`
  ADD PRIMARY KEY (`id`),
  ADD KEY `eleve_id` (`eleve_id`),
  ADD KEY `carte_id` (`carte_id`),
  ADD KEY `scanne_par` (`scanne_par`);

--
-- Index pour la table `recompenses`
--
ALTER TABLE `recompenses`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_eleve` (`eleve_id`),
  ADD KEY `idx_classe` (`classe_id`),
  ADD KEY `idx_date` (`date_recompense`),
  ADD KEY `idx_type` (`type_recompense`);

--
-- Index pour la table `reservations_livres`
--
ALTER TABLE `reservations_livres`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_livre_id` (`livre_id`),
  ADD KEY `idx_reserver` (`reserver_type`,`reserver_id`),
  ADD KEY `idx_date_reservation` (`date_reservation`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `traite_par` (`traite_par`);

--
-- Index pour la table `roles`
--
ALTER TABLE `roles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `nom_unique` (`nom`);

--
-- Index pour la table `sanctions`
--
ALTER TABLE `sanctions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `eleve_id` (`eleve_id`),
  ADD KEY `enseignant_id` (`enseignant_id`);

--
-- Index pour la table `sections`
--
ALTER TABLE `sections`
  ADD PRIMARY KEY (`id`);

--
-- Index pour la table `sessions_caisse`
--
ALTER TABLE `sessions_caisse`
  ADD PRIMARY KEY (`id`),
  ADD KEY `caisse_id` (`caisse_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Index pour la table `sms_logs`
--
ALTER TABLE `sms_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_expediteur` (`expediteur_id`),
  ADD KEY `idx_telephone` (`destinataire_telephone`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_type` (`type_sms`),
  ADD KEY `idx_date_envoi` (`date_envoi`);

--
-- Index pour la table `solvabilite_eleves`
--
ALTER TABLE `solvabilite_eleves`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_eleve_annee` (`eleve_id`,`annee_scolaire_id`),
  ADD KEY `annee_scolaire_id` (`annee_scolaire_id`);

--
-- Index pour la table `suivi_etapes_admission`
--
ALTER TABLE `suivi_etapes_admission`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `idx_suivi_etapes_demande` (`demande_admission_id`),
  ADD KEY `idx_suivi_etapes_etape` (`etape_id`);

--
-- Index pour la table `suivi_scolaire`
--
ALTER TABLE `suivi_scolaire`
  ADD PRIMARY KEY (`id`),
  ADD KEY `annee_scolaire_id` (`annee_scolaire_id`),
  ADD KEY `classe_id` (`classe_id`),
  ADD KEY `idx_suivi_scolaire_eleve` (`eleve_id`);

--
-- Index pour la table `system_settings`
--
ALTER TABLE `system_settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `cle` (`cle`),
  ADD KEY `idx_categorie` (`categorie`);

--
-- Index pour la table `templates_messages`
--
ALTER TABLE `templates_messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_type` (`type`),
  ADD KEY `idx_categorie` (`categorie`),
  ADD KEY `idx_actif` (`actif`);

--
-- Index pour la table `transactions_cartes`
--
ALTER TABLE `transactions_cartes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `idx_carte_id` (`carte_id`),
  ADD KEY `idx_type_transaction` (`type_transaction`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Index pour la table `transfers`
--
ALTER TABLE `transfers`
  ADD PRIMARY KEY (`id`),
  ADD KEY `classe_origine_id` (`classe_origine_id`),
  ADD KEY `classe_destination_id` (`classe_destination_id`),
  ADD KEY `approuve_par` (`approuve_par`),
  ADD KEY `traite_par` (`traite_par`),
  ADD KEY `idx_eleve_id` (`eleve_id`),
  ADD KEY `idx_type_mouvement` (`type_mouvement`),
  ADD KEY `idx_statut` (`statut`),
  ADD KEY `idx_date_demande` (`date_demande`);

--
-- Index pour la table `transferts`
--
ALTER TABLE `transferts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_eleve` (`eleve_id`),
  ADD KEY `idx_classe_origine` (`classe_origine_id`),
  ADD KEY `idx_classe_destination` (`classe_destination_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `approuve_par` (`approuve_par`);

--
-- Index pour la table `transferts_sorties`
--
ALTER TABLE `transferts_sorties`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_eleve` (`eleve_id`),
  ADD KEY `idx_annee_scolaire` (`annee_scolaire_id`),
  ADD KEY `idx_type_mouvement` (`type_mouvement`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_date_demande` (`date_demande`),
  ADD KEY `traite_par` (`traite_par`),
  ADD KEY `idx_transferts_eleve` (`eleve_id`);

--
-- Index pour la table `transfer_documents`
--
ALTER TABLE `transfer_documents`
  ADD PRIMARY KEY (`id`),
  ADD KEY `uploaded_by` (`uploaded_by`),
  ADD KEY `idx_transfer_id` (`transfer_id`),
  ADD KEY `idx_type_document` (`type_document`);

--
-- Index pour la table `transfer_fees`
--
ALTER TABLE `transfer_fees`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_transfer_id` (`transfer_id`),
  ADD KEY `idx_type_frais` (`type_frais`);

--
-- Index pour la table `transfer_history`
--
ALTER TABLE `transfer_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `idx_transfer_id` (`transfer_id`),
  ADD KEY `idx_action` (`action`);

--
-- Index pour la table `types_sanctions`
--
ALTER TABLE `types_sanctions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_gravite` (`gravite`),
  ADD KEY `idx_active` (`active`);

--
-- Index pour la table `type_frais`
--
ALTER TABLE `type_frais`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_annee_scolaire` (`annee_scolaire_id`),
  ADD KEY `idx_actif` (`actif`),
  ADD KEY `idx_nom` (`nom`),
  ADD KEY `idx_priorite` (`priorite`),
  ADD KEY `idx_annee_priorite` (`annee_scolaire_id`,`priorite`,`actif`);

--
-- Index pour la table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `idx_username` (`username`),
  ADD KEY `idx_email` (`email`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_users_role_id` (`role_id`),
  ADD KEY `idx_users_role_id_active` (`role_id`,`status`);

--
-- Index pour la table `user_actions_log`
--
ALTER TABLE `user_actions_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_action` (`action`),
  ADD KEY `idx_module` (`module`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- AUTO_INCREMENT pour les tables déchargées
--

--
-- AUTO_INCREMENT pour la table `absences`
--
ALTER TABLE `absences`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `annees_scolaires`
--
ALTER TABLE `annees_scolaires`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT pour la table `annonces`
--
ALTER TABLE `annonces`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `caisses`
--
ALTER TABLE `caisses`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `campagnes_cibles_dettes`
--
ALTER TABLE `campagnes_cibles_dettes`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `campagnes_recouvrement`
--
ALTER TABLE `campagnes_recouvrement`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `cartes_eleves`
--
ALTER TABLE `cartes_eleves`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `carte_eleve`
--
ALTER TABLE `carte_eleve`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `carte_eleve_historique`
--
ALTER TABLE `carte_eleve_historique`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `categories_livres`
--
ALTER TABLE `categories_livres`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `classes`
--
ALTER TABLE `classes`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `commandes`
--
ALTER TABLE `commandes`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `criteres_admission`
--
ALTER TABLE `criteres_admission`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `criteres_admission_classes`
--
ALTER TABLE `criteres_admission_classes`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `decisions_admission`
--
ALTER TABLE `decisions_admission`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `demandes_admission`
--
ALTER TABLE `demandes_admission`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `depenses`
--
ALTER TABLE `depenses`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `devises`
--
ALTER TABLE `devises`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT pour la table `documents_eleve`
--
ALTER TABLE `documents_eleve`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `documents_eleves`
--
ALTER TABLE `documents_eleves`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `eleves`
--
ALTER TABLE `eleves`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `emploi_temps`
--
ALTER TABLE `emploi_temps`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `emprunts`
--
ALTER TABLE `emprunts`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `emprunts_livres`
--
ALTER TABLE `emprunts_livres`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `etablissements`
--
ALTER TABLE `etablissements`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `etapes_admission`
--
ALTER TABLE `etapes_admission`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `evaluations`
--
ALTER TABLE `evaluations`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `evaluations_admission`
--
ALTER TABLE `evaluations_admission`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `factures`
--
ALTER TABLE `factures`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `frais_eleves`
--
ALTER TABLE `frais_eleves`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `frais_scolaires`
--
ALTER TABLE `frais_scolaires`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `incidents`
--
ALTER TABLE `incidents`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `inscriptions`
--
ALTER TABLE `inscriptions`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `inscriptions_detaillees`
--
ALTER TABLE `inscriptions_detaillees`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `livres`
--
ALTER TABLE `livres`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `logs_scan_carte`
--
ALTER TABLE `logs_scan_carte`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `matieres`
--
ALTER TABLE `matieres`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `messages`
--
ALTER TABLE `messages`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `modeles_frais`
--
ALTER TABLE `modeles_frais`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `mouvements_caisse`
--
ALTER TABLE `mouvements_caisse`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `notes`
--
ALTER TABLE `notes`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `notifications_destinataires`
--
ALTER TABLE `notifications_destinataires`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `notifications_parents`
--
ALTER TABLE `notifications_parents`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `notifications_recouvrement`
--
ALTER TABLE `notifications_recouvrement`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `notifications_suivi`
--
ALTER TABLE `notifications_suivi`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `paiements`
--
ALTER TABLE `paiements`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `paiements_cartes`
--
ALTER TABLE `paiements_cartes`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `parametres_admission`
--
ALTER TABLE `parametres_admission`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `parametres_bibliotheque`
--
ALTER TABLE `parametres_bibliotheque`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `parametres_cartes`
--
ALTER TABLE `parametres_cartes`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `parametres_recouvrement`
--
ALTER TABLE `parametres_recouvrement`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `parents`
--
ALTER TABLE `parents`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `penalites_bibliotheque`
--
ALTER TABLE `penalites_bibliotheque`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `personnel`
--
ALTER TABLE `personnel`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `presences_qr`
--
ALTER TABLE `presences_qr`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `recompenses`
--
ALTER TABLE `recompenses`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `reservations_livres`
--
ALTER TABLE `reservations_livres`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `roles`
--
ALTER TABLE `roles`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=28;

--
-- AUTO_INCREMENT pour la table `sanctions`
--
ALTER TABLE `sanctions`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `sections`
--
ALTER TABLE `sections`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `sessions_caisse`
--
ALTER TABLE `sessions_caisse`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `sms_logs`
--
ALTER TABLE `sms_logs`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `solvabilite_eleves`
--
ALTER TABLE `solvabilite_eleves`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `suivi_etapes_admission`
--
ALTER TABLE `suivi_etapes_admission`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `suivi_scolaire`
--
ALTER TABLE `suivi_scolaire`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `system_settings`
--
ALTER TABLE `system_settings`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `templates_messages`
--
ALTER TABLE `templates_messages`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `transactions_cartes`
--
ALTER TABLE `transactions_cartes`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `transfers`
--
ALTER TABLE `transfers`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `transferts`
--
ALTER TABLE `transferts`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `transferts_sorties`
--
ALTER TABLE `transferts_sorties`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `transfer_documents`
--
ALTER TABLE `transfer_documents`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `transfer_fees`
--
ALTER TABLE `transfer_fees`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `transfer_history`
--
ALTER TABLE `transfer_history`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `types_sanctions`
--
ALTER TABLE `types_sanctions`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `type_frais`
--
ALTER TABLE `type_frais`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `users`
--
ALTER TABLE `users`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT pour la table `user_actions_log`
--
ALTER TABLE `user_actions_log`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- Contraintes pour les tables déchargées
--

--
-- Contraintes pour la table `absences`
--
ALTER TABLE `absences`
  ADD CONSTRAINT `absences_ibfk_1` FOREIGN KEY (`eleve_id`) REFERENCES `eleves` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `absences_ibfk_2` FOREIGN KEY (`classe_id`) REFERENCES `classes` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `absences_ibfk_3` FOREIGN KEY (`valide_par`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Contraintes pour la table `caisses`
--
ALTER TABLE `caisses`
  ADD CONSTRAINT `caisses_ibfk_1` FOREIGN KEY (`devise_id`) REFERENCES `devises` (`id`),
  ADD CONSTRAINT `caisses_ibfk_2` FOREIGN KEY (`annee_scolaire_id`) REFERENCES `annees_scolaires` (`id`);

--
-- Contraintes pour la table `campagnes_cibles_dettes`
--
ALTER TABLE `campagnes_cibles_dettes`
  ADD CONSTRAINT `campagnes_cibles_dettes_ibfk_1` FOREIGN KEY (`campagne_id`) REFERENCES `campagnes_recouvrement` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `campagnes_cibles_dettes_ibfk_2` FOREIGN KEY (`eleve_id`) REFERENCES `eleves` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `campagnes_recouvrement`
--
ALTER TABLE `campagnes_recouvrement`
  ADD CONSTRAINT `campagnes_recouvrement_ibfk_1` FOREIGN KEY (`annee_scolaire_id`) REFERENCES `annees_scolaires` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `campagnes_recouvrement_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Contraintes pour la table `cartes_eleves`
--
ALTER TABLE `cartes_eleves`
  ADD CONSTRAINT `cartes_eleves_ibfk_1` FOREIGN KEY (`eleve_id`) REFERENCES `eleves` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `carte_eleve`
--
ALTER TABLE `carte_eleve`
  ADD CONSTRAINT `fk_carte_annee_scolaire` FOREIGN KEY (`annee_scolaire_id`) REFERENCES `annees_scolaires` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_carte_eleve` FOREIGN KEY (`eleve_id`) REFERENCES `eleves` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `classes`
--
ALTER TABLE `classes`
  ADD CONSTRAINT `classes_ibfk_1` FOREIGN KEY (`annee_scolaire_id`) REFERENCES `annees_scolaires` (`id`),
  ADD CONSTRAINT `fk_titulaire` FOREIGN KEY (`titulaire_id`) REFERENCES `personnel` (`id`);

--
-- Contraintes pour la table `commandes`
--
ALTER TABLE `commandes`
  ADD CONSTRAINT `commandes_ibfk_1` FOREIGN KEY (`eleve_id`) REFERENCES `eleves` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `commandes_ibfk_2` FOREIGN KEY (`annee_scolaire_id`) REFERENCES `annees_scolaires` (`id`),
  ADD CONSTRAINT `commandes_ibfk_3` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `commandes_ibfk_4` FOREIGN KEY (`facture_id`) REFERENCES `factures` (`id`) ON DELETE SET NULL;

--
-- Contraintes pour la table `criteres_admission`
--
ALTER TABLE `criteres_admission`
  ADD CONSTRAINT `criteres_admission_ibfk_1` FOREIGN KEY (`annee_scolaire_id`) REFERENCES `annees_scolaires` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `criteres_admission_classes`
--
ALTER TABLE `criteres_admission_classes`
  ADD CONSTRAINT `criteres_admission_classes_ibfk_1` FOREIGN KEY (`annee_scolaire_id`) REFERENCES `annees_scolaires` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `criteres_admission_classes_ibfk_2` FOREIGN KEY (`classe_id`) REFERENCES `classes` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `decisions_admission`
--
ALTER TABLE `decisions_admission`
  ADD CONSTRAINT `decisions_admission_ibfk_1` FOREIGN KEY (`demande_admission_id`) REFERENCES `demandes_admission` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `decisions_admission_ibfk_2` FOREIGN KEY (`decideur_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `demandes_admission`
--
ALTER TABLE `demandes_admission`
  ADD CONSTRAINT `demandes_admission_ibfk_1` FOREIGN KEY (`annee_scolaire_id`) REFERENCES `annees_scolaires` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `demandes_admission_ibfk_2` FOREIGN KEY (`classe_demandee_id`) REFERENCES `classes` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `demandes_admission_ibfk_3` FOREIGN KEY (`traite_par`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_demandes_admission_eleve_cree` FOREIGN KEY (`eleve_cree_id`) REFERENCES `eleves` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_demandes_admission_evalue_par` FOREIGN KEY (`evalue_par`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_demandes_admission_verifie_par` FOREIGN KEY (`verifie_par`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Contraintes pour la table `depenses`
--
ALTER TABLE `depenses`
  ADD CONSTRAINT `depenses_ibfk_1` FOREIGN KEY (`devise_id`) REFERENCES `devises` (`id`);

--
-- Contraintes pour la table `documents_eleve`
--
ALTER TABLE `documents_eleve`
  ADD CONSTRAINT `documents_eleve_ibfk_1` FOREIGN KEY (`eleve_id`) REFERENCES `eleves` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `documents_eleve_ibfk_2` FOREIGN KEY (`valide_par`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Contraintes pour la table `eleves`
--
ALTER TABLE `eleves`
  ADD CONSTRAINT `fk_eleves_annee_scolaire` FOREIGN KEY (`annee_scolaire_id`) REFERENCES `annees_scolaires` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_eleves_classe` FOREIGN KEY (`classe_id`) REFERENCES `classes` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Contraintes pour la table `emploi_temps`
--
ALTER TABLE `emploi_temps`
  ADD CONSTRAINT `fk_emploi_temps_annee` FOREIGN KEY (`annee_scolaire_id`) REFERENCES `annees_scolaires` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_emploi_temps_classe` FOREIGN KEY (`classe_id`) REFERENCES `classes` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_emploi_temps_enseignant` FOREIGN KEY (`enseignant_id`) REFERENCES `personnel` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_emploi_temps_matiere` FOREIGN KEY (`matiere_id`) REFERENCES `matieres` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `emprunts`
--
ALTER TABLE `emprunts`
  ADD CONSTRAINT `emprunts_ibfk_1` FOREIGN KEY (`livre_id`) REFERENCES `livres` (`id`),
  ADD CONSTRAINT `emprunts_ibfk_2` FOREIGN KEY (`eleve_id`) REFERENCES `eleves` (`id`);

--
-- Contraintes pour la table `emprunts_livres`
--
ALTER TABLE `emprunts_livres`
  ADD CONSTRAINT `emprunts_livres_ibfk_1` FOREIGN KEY (`livre_id`) REFERENCES `livres` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `emprunts_livres_ibfk_2` FOREIGN KEY (`traite_par`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `emprunts_livres_ibfk_3` FOREIGN KEY (`rendu_par`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Contraintes pour la table `evaluations`
--
ALTER TABLE `evaluations`
  ADD CONSTRAINT `evaluations_ibfk_1` FOREIGN KEY (`classe_id`) REFERENCES `classes` (`id`),
  ADD CONSTRAINT `evaluations_ibfk_2` FOREIGN KEY (`matiere_id`) REFERENCES `matieres` (`id`),
  ADD CONSTRAINT `evaluations_ibfk_3` FOREIGN KEY (`enseignant_id`) REFERENCES `personnel` (`id`),
  ADD CONSTRAINT `evaluations_ibfk_4` FOREIGN KEY (`annee_scolaire_id`) REFERENCES `annees_scolaires` (`id`);

--
-- Contraintes pour la table `evaluations_admission`
--
ALTER TABLE `evaluations_admission`
  ADD CONSTRAINT `evaluations_admission_ibfk_1` FOREIGN KEY (`demande_admission_id`) REFERENCES `demandes_admission` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `evaluations_admission_ibfk_2` FOREIGN KEY (`evaluateur_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Contraintes pour la table `factures`
--
ALTER TABLE `factures`
  ADD CONSTRAINT `factures_ibfk_1` FOREIGN KEY (`eleve_id`) REFERENCES `eleves` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `factures_ibfk_2` FOREIGN KEY (`annee_scolaire_id`) REFERENCES `annees_scolaires` (`id`),
  ADD CONSTRAINT `factures_ibfk_3` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Contraintes pour la table `frais_scolaires`
--
ALTER TABLE `frais_scolaires`
  ADD CONSTRAINT `fk_frais_devise` FOREIGN KEY (`devise_id`) REFERENCES `devises` (`id`),
  ADD CONSTRAINT `fk_frais_scolaires_type_frais` FOREIGN KEY (`type_frais_id`) REFERENCES `type_frais` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  ADD CONSTRAINT `frais_scolaires_ibfk_1` FOREIGN KEY (`classe_id`) REFERENCES `classes` (`id`),
  ADD CONSTRAINT `frais_scolaires_ibfk_2` FOREIGN KEY (`annee_scolaire_id`) REFERENCES `annees_scolaires` (`id`);

--
-- Contraintes pour la table `inscriptions`
--
ALTER TABLE `inscriptions`
  ADD CONSTRAINT `inscriptions_ibfk_1` FOREIGN KEY (`eleve_id`) REFERENCES `eleves` (`id`),
  ADD CONSTRAINT `inscriptions_ibfk_2` FOREIGN KEY (`classe_id`) REFERENCES `classes` (`id`),
  ADD CONSTRAINT `inscriptions_ibfk_3` FOREIGN KEY (`annee_scolaire_id`) REFERENCES `annees_scolaires` (`id`);

--
-- Contraintes pour la table `inscriptions_detaillees`
--
ALTER TABLE `inscriptions_detaillees`
  ADD CONSTRAINT `inscriptions_detaillees_ibfk_1` FOREIGN KEY (`demande_admission_id`) REFERENCES `demandes_admission` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `inscriptions_detaillees_ibfk_2` FOREIGN KEY (`eleve_id`) REFERENCES `eleves` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `inscriptions_detaillees_ibfk_3` FOREIGN KEY (`annee_scolaire_id`) REFERENCES `annees_scolaires` (`id`),
  ADD CONSTRAINT `inscriptions_detaillees_ibfk_4` FOREIGN KEY (`classe_id`) REFERENCES `classes` (`id`);

--
-- Contraintes pour la table `livres`
--
ALTER TABLE `livres`
  ADD CONSTRAINT `fk_livres_categorie` FOREIGN KEY (`categorie_id`) REFERENCES `categories_livres` (`id`) ON DELETE SET NULL;

--
-- Contraintes pour la table `logs_scan_carte`
--
ALTER TABLE `logs_scan_carte`
  ADD CONSTRAINT `fk_logs_scan_carte` FOREIGN KEY (`carte_id`) REFERENCES `carte_eleve` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_logs_scan_eleve` FOREIGN KEY (`eleve_id`) REFERENCES `eleves` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `modeles_frais`
--
ALTER TABLE `modeles_frais`
  ADD CONSTRAINT `modeles_frais_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`);

--
-- Contraintes pour la table `mouvements_caisse`
--
ALTER TABLE `mouvements_caisse`
  ADD CONSTRAINT `mouvements_caisse_ibfk_1` FOREIGN KEY (`session_caisse_id`) REFERENCES `sessions_caisse` (`id`),
  ADD CONSTRAINT `mouvements_caisse_ibfk_2` FOREIGN KEY (`devise_id`) REFERENCES `devises` (`id`),
  ADD CONSTRAINT `mouvements_caisse_ibfk_3` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Contraintes pour la table `notes`
--
ALTER TABLE `notes`
  ADD CONSTRAINT `notes_ibfk_1` FOREIGN KEY (`evaluation_id`) REFERENCES `evaluations` (`id`),
  ADD CONSTRAINT `notes_ibfk_2` FOREIGN KEY (`eleve_id`) REFERENCES `eleves` (`id`);

--
-- Contraintes pour la table `notifications_destinataires`
--
ALTER TABLE `notifications_destinataires`
  ADD CONSTRAINT `notifications_destinataires_ibfk_1` FOREIGN KEY (`notification_id`) REFERENCES `notifications_recouvrement` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `notifications_destinataires_ibfk_2` FOREIGN KEY (`eleve_id`) REFERENCES `eleves` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `notifications_parents`
--
ALTER TABLE `notifications_parents`
  ADD CONSTRAINT `notifications_parents_ibfk_1` FOREIGN KEY (`absence_id`) REFERENCES `absences` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `notifications_parents_ibfk_2` FOREIGN KEY (`parent_id`) REFERENCES `parents` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `notifications_parents_ibfk_3` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`);

--
-- Contraintes pour la table `notifications_recouvrement`
--
ALTER TABLE `notifications_recouvrement`
  ADD CONSTRAINT `notifications_recouvrement_ibfk_1` FOREIGN KEY (`campagne_id`) REFERENCES `campagnes_recouvrement` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `notifications_recouvrement_ibfk_2` FOREIGN KEY (`annee_scolaire_id`) REFERENCES `annees_scolaires` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `notifications_recouvrement_ibfk_3` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Contraintes pour la table `notifications_suivi`
--
ALTER TABLE `notifications_suivi`
  ADD CONSTRAINT `notifications_suivi_ibfk_1` FOREIGN KEY (`eleve_id`) REFERENCES `eleves` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `notifications_suivi_ibfk_2` FOREIGN KEY (`destinataire_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Contraintes pour la table `paiements`
--
ALTER TABLE `paiements`
  ADD CONSTRAINT `fk_paiements_type_frais` FOREIGN KEY (`type_frais_id`) REFERENCES `type_frais` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  ADD CONSTRAINT `paiements_ibfk_1` FOREIGN KEY (`eleve_id`) REFERENCES `eleves` (`id`),
  ADD CONSTRAINT `paiements_ibfk_2` FOREIGN KEY (`annee_scolaire_id`) REFERENCES `annees_scolaires` (`id`),
  ADD CONSTRAINT `paiements_ibfk_3` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Contraintes pour la table `paiements_cartes`
--
ALTER TABLE `paiements_cartes`
  ADD CONSTRAINT `fk_paiements_cartes_devise` FOREIGN KEY (`devise_id`) REFERENCES `devises` (`id`),
  ADD CONSTRAINT `paiements_cartes_ibfk_1` FOREIGN KEY (`carte_id`) REFERENCES `cartes_eleves` (`id`),
  ADD CONSTRAINT `paiements_cartes_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Contraintes pour la table `parametres_admission`
--
ALTER TABLE `parametres_admission`
  ADD CONSTRAINT `fk_parametres_admission_annee_scolaire` FOREIGN KEY (`annee_scolaire_id`) REFERENCES `annees_scolaires` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Contraintes pour la table `penalites_bibliotheque`
--
ALTER TABLE `penalites_bibliotheque`
  ADD CONSTRAINT `penalites_bibliotheque_ibfk_1` FOREIGN KEY (`emprunt_id`) REFERENCES `emprunts_livres` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `penalites_bibliotheque_ibfk_2` FOREIGN KEY (`traite_par`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Contraintes pour la table `personnel`
--
ALTER TABLE `personnel`
  ADD CONSTRAINT `personnel_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Contraintes pour la table `presences_qr`
--
ALTER TABLE `presences_qr`
  ADD CONSTRAINT `presences_qr_ibfk_1` FOREIGN KEY (`eleve_id`) REFERENCES `eleves` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `presences_qr_ibfk_2` FOREIGN KEY (`carte_id`) REFERENCES `cartes_eleves` (`id`),
  ADD CONSTRAINT `presences_qr_ibfk_3` FOREIGN KEY (`scanne_par`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Contraintes pour la table `reservations_livres`
--
ALTER TABLE `reservations_livres`
  ADD CONSTRAINT `reservations_livres_ibfk_1` FOREIGN KEY (`livre_id`) REFERENCES `livres` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `reservations_livres_ibfk_2` FOREIGN KEY (`traite_par`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Contraintes pour la table `sanctions`
--
ALTER TABLE `sanctions`
  ADD CONSTRAINT `sanctions_ibfk_1` FOREIGN KEY (`eleve_id`) REFERENCES `eleves` (`id`),
  ADD CONSTRAINT `sanctions_ibfk_2` FOREIGN KEY (`enseignant_id`) REFERENCES `personnel` (`id`);

--
-- Contraintes pour la table `sessions_caisse`
--
ALTER TABLE `sessions_caisse`
  ADD CONSTRAINT `sessions_caisse_ibfk_1` FOREIGN KEY (`caisse_id`) REFERENCES `caisses` (`id`),
  ADD CONSTRAINT `sessions_caisse_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Contraintes pour la table `solvabilite_eleves`
--
ALTER TABLE `solvabilite_eleves`
  ADD CONSTRAINT `solvabilite_eleves_ibfk_1` FOREIGN KEY (`eleve_id`) REFERENCES `eleves` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `solvabilite_eleves_ibfk_2` FOREIGN KEY (`annee_scolaire_id`) REFERENCES `annees_scolaires` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `suivi_etapes_admission`
--
ALTER TABLE `suivi_etapes_admission`
  ADD CONSTRAINT `suivi_etapes_admission_ibfk_1` FOREIGN KEY (`demande_admission_id`) REFERENCES `demandes_admission` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `suivi_etapes_admission_ibfk_2` FOREIGN KEY (`etape_id`) REFERENCES `etapes_admission` (`id`),
  ADD CONSTRAINT `suivi_etapes_admission_ibfk_3` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Contraintes pour la table `suivi_scolaire`
--
ALTER TABLE `suivi_scolaire`
  ADD CONSTRAINT `suivi_scolaire_ibfk_1` FOREIGN KEY (`eleve_id`) REFERENCES `eleves` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `suivi_scolaire_ibfk_2` FOREIGN KEY (`annee_scolaire_id`) REFERENCES `annees_scolaires` (`id`),
  ADD CONSTRAINT `suivi_scolaire_ibfk_3` FOREIGN KEY (`classe_id`) REFERENCES `classes` (`id`);

--
-- Contraintes pour la table `transactions_cartes`
--
ALTER TABLE `transactions_cartes`
  ADD CONSTRAINT `transactions_cartes_ibfk_1` FOREIGN KEY (`carte_id`) REFERENCES `cartes_eleves` (`id`),
  ADD CONSTRAINT `transactions_cartes_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Contraintes pour la table `transfers`
--
ALTER TABLE `transfers`
  ADD CONSTRAINT `transfers_ibfk_1` FOREIGN KEY (`eleve_id`) REFERENCES `eleves` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `transfers_ibfk_2` FOREIGN KEY (`classe_origine_id`) REFERENCES `classes` (`id`),
  ADD CONSTRAINT `transfers_ibfk_3` FOREIGN KEY (`classe_destination_id`) REFERENCES `classes` (`id`),
  ADD CONSTRAINT `transfers_ibfk_4` FOREIGN KEY (`approuve_par`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `transfers_ibfk_5` FOREIGN KEY (`traite_par`) REFERENCES `users` (`id`);

--
-- Contraintes pour la table `transferts`
--
ALTER TABLE `transferts`
  ADD CONSTRAINT `transferts_ibfk_1` FOREIGN KEY (`eleve_id`) REFERENCES `eleves` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `transferts_ibfk_2` FOREIGN KEY (`classe_origine_id`) REFERENCES `classes` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `transferts_ibfk_3` FOREIGN KEY (`classe_destination_id`) REFERENCES `classes` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `transferts_ibfk_4` FOREIGN KEY (`approuve_par`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Contraintes pour la table `transferts_sorties`
--
ALTER TABLE `transferts_sorties`
  ADD CONSTRAINT `transferts_sorties_ibfk_1` FOREIGN KEY (`eleve_id`) REFERENCES `eleves` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `transferts_sorties_ibfk_2` FOREIGN KEY (`annee_scolaire_id`) REFERENCES `annees_scolaires` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `transferts_sorties_ibfk_3` FOREIGN KEY (`traite_par`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Contraintes pour la table `transfer_documents`
--
ALTER TABLE `transfer_documents`
  ADD CONSTRAINT `transfer_documents_ibfk_1` FOREIGN KEY (`transfer_id`) REFERENCES `transfers` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `transfer_documents_ibfk_2` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`);

--
-- Contraintes pour la table `transfer_fees`
--
ALTER TABLE `transfer_fees`
  ADD CONSTRAINT `transfer_fees_ibfk_1` FOREIGN KEY (`transfer_id`) REFERENCES `transfers` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `transfer_history`
--
ALTER TABLE `transfer_history`
  ADD CONSTRAINT `transfer_history_ibfk_1` FOREIGN KEY (`transfer_id`) REFERENCES `transfers` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `transfer_history_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Contraintes pour la table `type_frais`
--
ALTER TABLE `type_frais`
  ADD CONSTRAINT `fk_type_frais_annee_scolaire` FOREIGN KEY (`annee_scolaire_id`) REFERENCES `annees_scolaires` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Contraintes pour la table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `fk_users_role_id` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
