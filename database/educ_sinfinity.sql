-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Hôte : localhost
-- Généré le : ven. 08 août 2025 à 22:30
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
  `type_absence` enum('absence','retard','absence_justifiee','retard_justifie') COLLATE utf8mb4_unicode_ci DEFAULT 'absence',
  `motif` text COLLATE utf8mb4_unicode_ci,
  `duree_retard` int DEFAULT NULL COMMENT 'Durée du retard en minutes',
  `justification` text COLLATE utf8mb4_unicode_ci,
  `document_justificatif` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `valide_par` int DEFAULT NULL,
  `date_validation` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `absences`
--

INSERT INTO `absences` (`id`, `eleve_id`, `classe_id`, `date_absence`, `type_absence`, `motif`, `duree_retard`, `justification`, `document_justificatif`, `valide_par`, `date_validation`, `created_at`, `updated_at`) VALUES
(4, 1, 1, '2025-08-08', 'absence', 'n,h,n,n,', NULL, NULL, NULL, NULL, NULL, '2025-08-08 18:14:57', NULL),
(6, 1, 1, '2025-08-07', 'absence_justifiee', 'Maladie', NULL, 'hghghghghghgvbfdfdfdf', '', 1, '2025-08-08 18:34:08', '2025-08-08 18:28:44', '2025-08-08 18:34:08'),
(7, 2, 2, '2025-08-06', 'retard', 'Rendez-vous médical', NULL, NULL, NULL, NULL, NULL, '2025-08-08 18:28:44', NULL),
(8, 3, 3, '2025-08-05', 'absence_justifiee', 'Problème de transport', NULL, NULL, NULL, NULL, NULL, '2025-08-08 18:28:44', NULL),
(9, 4, 4, '2025-08-04', 'retard_justifie', 'Urgence familiale', NULL, NULL, NULL, NULL, NULL, '2025-08-08 18:28:44', NULL),
(10, 5, 5, '2025-08-03', 'absence', 'Retard réveil', NULL, NULL, NULL, NULL, NULL, '2025-08-08 18:28:44', NULL),
(12, 8, 1, '2025-08-08', 'absence', 'k_yfeeddddfggghhh', NULL, NULL, NULL, NULL, NULL, '2025-08-08 19:22:23', NULL);

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

--
-- Déchargement des données de la table `annees_scolaires`
--

INSERT INTO `annees_scolaires` (`id`, `annee`, `date_debut`, `date_fin`, `status`, `created_at`, `updated_at`) VALUES
(1, '2023-2024', '2023-09-01', '2024-07-31', 'active', '2025-08-08 13:07:50', NULL);

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

--
-- Déchargement des données de la table `classes`
--

INSERT INTO `classes` (`id`, `nom`, `niveau`, `section`, `salle`, `description`, `titulaire_id`, `capacite_max`, `frais_inscription`, `frais_mensuel`, `annee_scolaire_id`, `created_at`, `updated_at`) VALUES
(1, '1ère Primaire A', 'primaire', 'A', NULL, NULL, NULL, 30, 50000.00, 25000.00, 1, '2025-08-08 14:29:52', NULL),
(2, '1ère Primaire B', 'primaire', 'B', NULL, NULL, NULL, 30, 50000.00, 25000.00, 1, '2025-08-08 14:29:52', NULL),
(3, '2ème Primaire A', 'primaire', 'A', NULL, NULL, NULL, 28, 50000.00, 25000.00, 1, '2025-08-08 14:29:52', NULL),
(4, '3ème Primaire A', 'primaire', 'A', NULL, NULL, NULL, 32, 50000.00, 25000.00, 1, '2025-08-08 14:29:52', NULL),
(5, '4ème Primaire A', 'primaire', 'A', NULL, NULL, NULL, 25, 50000.00, 25000.00, 1, '2025-08-08 14:29:52', NULL),
(6, '5ème Primaire A', 'primaire', 'A', NULL, NULL, NULL, 27, 50000.00, 25000.00, 1, '2025-08-08 14:29:52', NULL),
(7, '6ème Primaire A', 'primaire', 'A', NULL, NULL, NULL, 24, 50000.00, 25000.00, 1, '2025-08-08 14:29:52', NULL);

-- --------------------------------------------------------

--
-- Structure de la table `demandes_admission`
--

CREATE TABLE `demandes_admission` (
  `id` int NOT NULL,
  `numero_demande` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `annee_scolaire_id` int NOT NULL,
  `classe_demandee_id` int NOT NULL,
  `nom_eleve` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `prenom_eleve` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `date_naissance` date NOT NULL,
  `lieu_naissance` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sexe` enum('M','F') COLLATE utf8mb4_unicode_ci NOT NULL,
  `adresse` text COLLATE utf8mb4_unicode_ci,
  `telephone` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `nom_pere` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `nom_mere` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `profession_pere` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `profession_mere` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `telephone_parent` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `personne_contact` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `telephone_contact` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `relation_contact` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ecole_precedente` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `classe_precedente` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `annee_precedente` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `moyenne_precedente` decimal(4,2) DEFAULT NULL,
  `certificat_naissance` tinyint(1) DEFAULT '0',
  `bulletin_precedent` tinyint(1) DEFAULT '0',
  `certificat_medical` tinyint(1) DEFAULT '0',
  `photo_identite` tinyint(1) DEFAULT '0',
  `autres_documents` text COLLATE utf8mb4_unicode_ci,
  `motif_demande` text COLLATE utf8mb4_unicode_ci,
  `besoins_speciaux` text COLLATE utf8mb4_unicode_ci,
  `allergies_medicales` text COLLATE utf8mb4_unicode_ci,
  `status` enum('en_attente','acceptee','refusee','en_cours_traitement','inscrit') COLLATE utf8mb4_unicode_ci DEFAULT 'en_attente',
  `priorite` enum('normale','urgente','tres_urgente') COLLATE utf8mb4_unicode_ci DEFAULT 'normale',
  `date_entretien` datetime DEFAULT NULL,
  `notes_entretien` text COLLATE utf8mb4_unicode_ci,
  `decision_motif` text COLLATE utf8mb4_unicode_ci,
  `traite_par` int DEFAULT NULL,
  `date_traitement` timestamp NULL DEFAULT NULL,
  `frais_inscription` decimal(10,2) DEFAULT '0.00',
  `frais_scolarite` decimal(10,2) DEFAULT '0.00',
  `reduction_accordee` decimal(5,2) DEFAULT '0.00',
  `observations` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `demandes_admission`
--

INSERT INTO `demandes_admission` (`id`, `numero_demande`, `annee_scolaire_id`, `classe_demandee_id`, `nom_eleve`, `prenom_eleve`, `date_naissance`, `lieu_naissance`, `sexe`, `adresse`, `telephone`, `email`, `nom_pere`, `nom_mere`, `profession_pere`, `profession_mere`, `telephone_parent`, `personne_contact`, `telephone_contact`, `relation_contact`, `ecole_precedente`, `classe_precedente`, `annee_precedente`, `moyenne_precedente`, `certificat_naissance`, `bulletin_precedent`, `certificat_medical`, `photo_identite`, `autres_documents`, `motif_demande`, `besoins_speciaux`, `allergies_medicales`, `status`, `priorite`, `date_entretien`, `notes_entretien`, `decision_motif`, `traite_par`, `date_traitement`, `frais_inscription`, `frais_scolarite`, `reduction_accordee`, `observations`, `created_at`, `updated_at`) VALUES
(1, 'ADM2025001', 1, 1, 'DEMANDE ghghghg', 'Test1', '2017-06-17', 'Kinshasa', 'M', '', '', '', 'DEMANDE Père', 'DEMANDE Mère', '', '', '+243 123 456 789', '', '', '', 'École Maternelle Saint-Pierre', '', '', NULL, 0, 0, 0, 0, '', 'Demande d&amp;#039;admission standard', '', '', 'en_attente', 'normale', NULL, NULL, NULL, NULL, NULL, 0.00, 0.00, 0.00, '', '2025-08-08 16:11:20', '2025-08-08 16:56:04'),
(2, 'ADM2025002', 1, 2, 'CANDIDAT', 'Marie', '2010-07-22', 'Lubumbashi', 'F', NULL, NULL, NULL, 'CANDIDAT Papa', 'CANDIDAT Maman', NULL, NULL, '+243 987 654 321', NULL, NULL, NULL, 'École Primaire Notre-Dame', NULL, NULL, NULL, 0, 0, 0, 0, NULL, 'Demande d\'admission standard', NULL, NULL, 'acceptee', 'normale', NULL, NULL, NULL, NULL, NULL, 0.00, 0.00, 0.00, NULL, '2025-08-08 16:11:20', NULL),
(3, 'ADM2025003', 1, 3, 'URGENT', 'Paul', '2011-01-10', 'Goma', 'M', NULL, NULL, NULL, 'URGENT Père', 'URGENT Mère', NULL, NULL, '+243 555 666 777', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0, 0, 0, NULL, 'Déménagement urgent de la famille', NULL, NULL, 'acceptee', 'urgente', NULL, NULL, NULL, 1, '2025-08-08 16:39:17', 0.00, 0.00, 0.00, NULL, '2025-08-08 16:11:20', '2025-08-08 16:39:17');

-- --------------------------------------------------------

--
-- Structure de la table `documents_eleves`
--

CREATE TABLE `documents_eleves` (
  `id` int NOT NULL,
  `eleve_id` int NOT NULL,
  `type_document` enum('certificat_naissance','bulletin_precedent','certificat_medical','photo_identite','fiche_inscription','attestation_scolarite','releve_notes','certificat_conduite','autre') COLLATE utf8mb4_unicode_ci NOT NULL,
  `nom_document` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `nom_fichier` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `chemin_fichier` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `taille_fichier` int DEFAULT NULL,
  `type_mime` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `date_ajout` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `ajoute_par` int DEFAULT NULL,
  `date_verification` datetime DEFAULT NULL,
  `verifie_par` int DEFAULT NULL,
  `statut_verification` enum('en_attente','verifie','rejete') COLLATE utf8mb4_unicode_ci DEFAULT 'en_attente',
  `commentaire_verification` text COLLATE utf8mb4_unicode_ci,
  `obligatoire` tinyint(1) DEFAULT '0',
  `date_expiration` date DEFAULT NULL,
  `numero_document` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `organisme_delivrance` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `documents_eleves`
--

INSERT INTO `documents_eleves` (`id`, `eleve_id`, `type_document`, `nom_document`, `nom_fichier`, `chemin_fichier`, `taille_fichier`, `type_mime`, `description`, `date_ajout`, `ajoute_par`, `date_verification`, `verifie_par`, `statut_verification`, `commentaire_verification`, `obligatoire`, `date_expiration`, `numero_document`, `organisme_delivrance`, `created_at`, `updated_at`) VALUES
(1, 1, 'certificat_naissance', 'Certificat de naissance - MUKENDI Jean', NULL, NULL, NULL, NULL, 'Document de test pour MUKENDI Jean', '2025-08-08 19:05:50', NULL, NULL, NULL, 'en_attente', NULL, 0, NULL, NULL, NULL, '2025-08-08 17:05:50', '2025-08-08 17:05:50'),
(2, 1, 'photo_identite', 'Photo d\'identité - MUKENDI Jean', NULL, NULL, NULL, NULL, 'Document de test pour MUKENDI Jean', '2025-08-08 19:05:50', NULL, NULL, NULL, 'en_attente', NULL, 0, NULL, NULL, NULL, '2025-08-08 17:05:50', '2025-08-08 17:05:50'),
(3, 1, 'bulletin_precedent', 'Bulletin de l\'année précédente - MUKENDI Jean', NULL, NULL, NULL, NULL, 'Document de test pour MUKENDI Jean', '2025-08-08 19:05:50', NULL, NULL, NULL, 'en_attente', NULL, 0, NULL, NULL, NULL, '2025-08-08 17:05:50', '2025-08-08 17:05:50'),
(4, 2, 'certificat_naissance', 'Certificat de naissance - KABILA Marie', NULL, NULL, NULL, NULL, 'Document de test pour KABILA Marie', '2025-08-08 19:05:50', NULL, NULL, NULL, 'verifie', NULL, 0, NULL, NULL, NULL, '2025-08-08 17:05:50', '2025-08-08 17:05:50'),
(5, 2, 'photo_identite', 'Photo d\'identité - KABILA Marie', NULL, NULL, NULL, NULL, 'Document de test pour KABILA Marie', '2025-08-08 19:05:50', NULL, NULL, NULL, 'verifie', NULL, 0, NULL, NULL, NULL, '2025-08-08 17:05:50', '2025-08-08 17:05:50'),
(6, 2, 'bulletin_precedent', 'Bulletin de l\'année précédente - KABILA Marie', NULL, NULL, NULL, NULL, 'Document de test pour KABILA Marie', '2025-08-08 19:05:50', NULL, NULL, NULL, 'en_attente', NULL, 0, NULL, NULL, NULL, '2025-08-08 17:05:50', '2025-08-08 17:05:50'),
(7, 3, 'certificat_naissance', 'Certificat de naissance - TSHISEKEDI Paul', NULL, NULL, NULL, NULL, 'Document de test pour TSHISEKEDI Paul', '2025-08-08 19:05:50', NULL, NULL, NULL, 'en_attente', NULL, 0, NULL, NULL, NULL, '2025-08-08 17:05:50', '2025-08-08 17:05:50'),
(8, 3, 'photo_identite', 'Photo d\'identité - TSHISEKEDI Paul', NULL, NULL, NULL, NULL, 'Document de test pour TSHISEKEDI Paul', '2025-08-08 19:05:50', NULL, NULL, NULL, 'verifie', NULL, 1, NULL, NULL, NULL, '2025-08-08 17:05:50', '2025-08-08 17:05:50'),
(9, 4, 'certificat_naissance', 'Certificat de naissance - MBUYI Grace', NULL, NULL, NULL, NULL, 'Document de test pour MBUYI Grace', '2025-08-08 19:05:50', NULL, NULL, NULL, 'verifie', NULL, 0, NULL, NULL, NULL, '2025-08-08 17:05:50', '2025-08-08 17:05:50'),
(10, 4, 'photo_identite', 'Photo d\'identité - MBUYI Grace', NULL, NULL, NULL, NULL, 'Document de test pour MBUYI Grace', '2025-08-08 19:05:50', NULL, NULL, NULL, 'en_attente', NULL, 1, NULL, NULL, NULL, '2025-08-08 17:05:50', '2025-08-08 17:05:50'),
(11, 4, 'bulletin_precedent', 'Bulletin de l\'année précédente - MBUYI Grace', NULL, NULL, NULL, NULL, 'Document de test pour MBUYI Grace', '2025-08-08 19:05:50', NULL, NULL, NULL, 'en_attente', NULL, 0, NULL, NULL, NULL, '2025-08-08 17:05:50', '2025-08-08 17:05:50'),
(12, 5, 'certificat_naissance', 'Certificat de naissance - KASONGO David', NULL, NULL, NULL, NULL, 'Document de test pour KASONGO David', '2025-08-08 19:05:50', NULL, NULL, NULL, 'verifie', NULL, 1, NULL, NULL, NULL, '2025-08-08 17:05:50', '2025-08-08 17:05:50'),
(13, 5, 'photo_identite', 'Photo d\'identité - KASONGO David', NULL, NULL, NULL, NULL, 'Document de test pour KASONGO David', '2025-08-08 19:05:50', NULL, NULL, NULL, 'verifie', NULL, 0, NULL, NULL, NULL, '2025-08-08 17:05:50', '2025-08-08 17:05:50'),
(14, 12, 'certificat_medical', 'jhjhjh', NULL, NULL, NULL, NULL, 'nvbvbvbvbv', '2025-08-08 19:35:36', 1, '2025-08-09 00:20:28', 1, 'en_attente', 'kjjhjhjhjhhvgfgfgf', 0, NULL, NULL, NULL, '2025-08-08 17:35:36', '2025-08-08 22:20:28');

-- --------------------------------------------------------

--
-- Structure de la table `eleves`
--

CREATE TABLE `eleves` (
  `id` int NOT NULL,
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
  `photo` varchar(255) DEFAULT NULL,
  `status` enum('actif','transfere','abandonne','diplome') DEFAULT 'actif',
  `date_inscription` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  `email_parent` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Déchargement des données de la table `eleves`
--

INSERT INTO `eleves` (`id`, `numero_matricule`, `parent_id`, `nom`, `prenom`, `sexe`, `date_naissance`, `lieu_naissance`, `adresse`, `telephone`, `email`, `nom_pere`, `nom_mere`, `profession_pere`, `profession_mere`, `telephone_parent`, `personne_contact`, `telephone_contact`, `photo`, `status`, `date_inscription`, `created_at`, `updated_at`, `email_parent`) VALUES
(1, 'MAT2024001', 1, 'MUKENDI', 'Jean', 'M', '2010-05-15', 'Kinshasa', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'actif', '2025-08-08 14:36:40', '2025-08-08 16:36:40', '2025-08-08 18:59:25', NULL),
(2, 'MAT2024002', 2, 'KABILA', 'Marie', 'F', '2011-03-22', 'Lubumbashi', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'actif', '2025-08-08 14:36:40', '2025-08-08 16:36:40', '2025-08-08 18:59:25', NULL),
(3, 'MAT2024003', 3, 'TSHISEKEDI', 'Paul', 'M', '2010-08-10', 'Mbuji-Mayi', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'diplome', '2025-08-08 14:36:40', '2025-08-08 16:36:40', '2025-08-08 21:00:10', NULL),
(4, 'MAT2024004', 4, 'MBUYI', 'Grace', 'F', '2011-01-18', 'Kananga', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'actif', '2025-08-08 14:36:40', '2025-08-08 16:36:40', '2025-08-08 18:59:25', NULL),
(5, 'MAT2024005', 5, 'KASONGO', 'David', 'M', '2010-12-05', 'Kisangani', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'actif', '2025-08-08 14:36:40', '2025-08-08 16:36:40', '2025-08-08 18:59:25', NULL),
(6, 'MAT2024006', NULL, 'NGOZI', 'Sarah', 'F', '2011-07-30', 'Goma', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'actif', '2025-08-08 14:36:40', '2025-08-08 16:36:40', NULL, NULL),
(7, 'MAT2024007', NULL, 'LUMUMBA', 'Patrick', 'M', '2010-09-12', 'Kinshasa', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'actif', '2025-08-08 14:36:40', '2025-08-08 16:36:40', NULL, NULL),
(8, 'MAT2024008', NULL, 'KALONJI', 'Esther', 'F', '2011-02-28', 'Mbuji-Mayi', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'actif', '2025-08-08 14:36:40', '2025-08-08 16:36:40', NULL, NULL),
(9, 'MAT2024009', NULL, 'MOBUTU', 'Joseph', 'M', '2010-11-03', 'Gbadolite', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'actif', '2025-08-08 14:36:40', '2025-08-08 16:36:40', NULL, NULL),
(10, 'MAT2024010', NULL, 'KIMBANGU', 'Ruth', 'F', '2011-06-14', 'Nkamba', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'actif', '2025-08-08 14:36:40', '2025-08-08 16:36:40', NULL, NULL),
(12, 'AUTO20250808170719', NULL, 'AUTOTEST', 'Élève', 'M', '2011-06-15', 'Kinshasa', '123 Avenue de la Paix, Kinshasa', '+243 123 456 789', 'autotest@example.com', 'AUTOTEST Père', 'AUTOTEST Mère', 'Ingénieur', 'Enseignante', '+243 987 654 321', 'AUTOTEST Contact', '+243 111 222 333', NULL, 'actif', '2025-08-08 16:07:19', '2025-08-08 18:07:19', NULL, NULL);

-- --------------------------------------------------------

--
-- Structure de la table `emplois_temps`
--

CREATE TABLE `emplois_temps` (
  `id` int NOT NULL,
  `classe_id` int NOT NULL,
  `matiere_id` int NOT NULL,
  `enseignant_id` int NOT NULL,
  `jour_semaine` enum('lundi','mardi','mercredi','jeudi','vendredi','samedi') NOT NULL,
  `heure_debut` time NOT NULL,
  `heure_fin` time NOT NULL,
  `salle` varchar(50) DEFAULT NULL,
  `annee_scolaire_id` int NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

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

--
-- Déchargement des données de la table `etablissements`
--

INSERT INTO `etablissements` (`id`, `nom`, `adresse`, `telephone`, `email`, `directeur`, `code_etablissement`, `province`, `ville`, `created_at`) VALUES
(1, 'École Sinfinity', 'Avenue de la Paix, Kinshasa', '+243 123 456 789', 'contact@sinfinity-school.cd', NULL, 'SINF001', 'Kinshasa', 'Kinshasa', '2025-08-08 13:07:49');

-- --------------------------------------------------------

--
-- Structure de la table `evaluations`
--

CREATE TABLE `evaluations` (
  `id` int NOT NULL,
  `nom` varchar(100) NOT NULL,
  `type` enum('interrogation','devoir','examen','composition') NOT NULL,
  `classe_id` int NOT NULL,
  `matiere_id` int NOT NULL,
  `type_evaluation` enum('interrogation','devoir','examen','composition') NOT NULL DEFAULT 'interrogation',
  `enseignant_id` int NOT NULL,
  `date_evaluation` date NOT NULL,
  `note_max` decimal(5,2) DEFAULT '20.00',
  `coefficient` decimal(3,2) DEFAULT '1.00',
  `periode` enum('1er_trimestre','2eme_trimestre','3eme_trimestre','annuelle') NOT NULL,
  `annee_scolaire_id` int NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

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
  `status` enum('inscrit','transfere','abandonne') DEFAULT 'inscrit',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Déchargement des données de la table `inscriptions`
--

INSERT INTO `inscriptions` (`id`, `eleve_id`, `classe_id`, `annee_scolaire_id`, `date_inscription`, `frais_inscription_paye`, `status`, `created_at`, `updated_at`) VALUES
(1, 1, 1, 1, '2025-08-08 14:36:40', 50000.00, 'inscrit', '2025-08-08 14:36:40', NULL),
(2, 2, 2, 1, '2025-08-08 14:36:40', 50000.00, 'inscrit', '2025-08-08 14:36:40', NULL),
(3, 3, 3, 1, '2025-08-08 14:36:40', 50000.00, 'transfere', '2025-08-08 14:36:40', '2025-08-08 21:00:10'),
(4, 4, 4, 1, '2025-08-08 14:36:40', 50000.00, 'inscrit', '2025-08-08 14:36:40', NULL),
(5, 5, 5, 1, '2025-08-08 14:36:40', 50000.00, 'inscrit', '2025-08-08 14:36:40', NULL),
(6, 6, 6, 1, '2025-08-08 14:36:40', 50000.00, 'inscrit', '2025-08-08 14:36:40', NULL),
(7, 7, 7, 1, '2025-08-08 14:36:40', 50000.00, 'inscrit', '2025-08-08 14:36:40', NULL),
(8, 8, 1, 1, '2025-08-08 14:36:40', 50000.00, 'inscrit', '2025-08-08 14:36:40', NULL),
(9, 9, 2, 1, '2025-08-08 14:36:40', 50000.00, 'inscrit', '2025-08-08 14:36:40', NULL),
(10, 10, 3, 1, '2025-08-08 14:36:40', 50000.00, 'inscrit', '2025-08-08 14:36:40', NULL),
(11, 12, 1, 1, '2025-08-08 16:07:19', 50000.00, 'inscrit', '2025-08-08 16:07:19', NULL);

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
  `categorie` varchar(50) DEFAULT NULL,
  `nombre_exemplaires` int DEFAULT '1',
  `nombre_disponibles` int DEFAULT '1',
  `emplacement` varchar(50) DEFAULT NULL,
  `status` enum('disponible','indisponible') DEFAULT 'disponible',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

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

--
-- Déchargement des données de la table `matieres`
--

INSERT INTO `matieres` (`id`, `nom`, `code`, `coefficient`, `volume_horaire`, `objectifs`, `niveau`, `type`, `description`, `created_at`) VALUES
(1, 'Mathématiques', NULL, 4, 6, 'dsdsd', 'primaire', 'obligatoire', 'dsdsd', '2025-08-08 22:07:02');

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
-- Structure de la table `notifications_parents`
--

CREATE TABLE `notifications_parents` (
  `id` int NOT NULL,
  `absence_id` int NOT NULL,
  `parent_id` int DEFAULT NULL,
  `type_notification` enum('sms','email') COLLATE utf8mb4_unicode_ci NOT NULL,
  `message` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` enum('pending','sent','failed') COLLATE utf8mb4_unicode_ci DEFAULT 'pending',
  `sent_at` timestamp NULL DEFAULT NULL,
  `error_message` text COLLATE utf8mb4_unicode_ci,
  `created_by` int NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `notifications_parents`
--

INSERT INTO `notifications_parents` (`id`, `absence_id`, `parent_id`, `type_notification`, `message`, `status`, `sent_at`, `error_message`, `created_by`, `created_at`, `updated_at`) VALUES
(2, 4, 1, 'email', 'Notification pour MUKENDI Jean - absence', 'sent', '2025-08-08 19:09:54', NULL, 1, '2025-08-08 19:09:54', NULL);

-- --------------------------------------------------------

--
-- Structure de la table `paiements`
--

CREATE TABLE `paiements` (
  `id` int NOT NULL,
  `eleve_id` int NOT NULL,
  `type_paiement` enum('inscription','mensualite','examen','autre') NOT NULL,
  `montant` decimal(10,2) NOT NULL,
  `date_paiement` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `mois_concerne` varchar(20) DEFAULT NULL,
  `annee_scolaire_id` int NOT NULL,
  `recu_numero` varchar(50) DEFAULT NULL,
  `mode_paiement` enum('especes','cheque','virement','mobile_money') DEFAULT 'especes',
  `observation` text,
  `user_id` int DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Structure de la table `parents`
--

CREATE TABLE `parents` (
  `id` int NOT NULL,
  `nom` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `prenom` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `telephone` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `adresse` text COLLATE utf8mb4_unicode_ci,
  `profession` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `parents`
--

INSERT INTO `parents` (`id`, `nom`, `prenom`, `telephone`, `email`, `adresse`, `profession`, `created_at`, `updated_at`) VALUES
(1, 'MUKENDI', 'Joseph', '0812345678', 'joseph.mukendi@email.com', 'Kinshasa, Gombe', 'Parent d\'élève', '2025-08-08 18:59:25', NULL),
(2, 'KASONGO', 'Marie', '0823456789', 'marie.kasongo@email.com', 'Kinshasa, Lemba', 'Parent d\'élève', '2025-08-08 18:59:25', NULL),
(3, 'TSHISEKEDI', 'Pierre', '0834567890', 'pierre.tshisekedi@email.com', 'Kinshasa, Kintambo', 'Parent d\'élève', '2025-08-08 18:59:25', NULL),
(4, 'KABILA', 'Françoise', '0845678901', 'francoise.kabila@email.com', 'Kinshasa, Ngaliema', 'Parent d\'élève', '2025-08-08 18:59:25', NULL),
(5, 'MBUYI', 'André', '0856789012', 'andre.mbuyi@email.com', 'Kinshasa, Kalamu', 'Parent d\'élève', '2025-08-08 18:59:25', NULL);

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

--
-- Déchargement des données de la table `personnel`
--

INSERT INTO `personnel` (`id`, `matricule`, `nom`, `prenom`, `sexe`, `date_naissance`, `lieu_naissance`, `adresse`, `telephone`, `email`, `fonction`, `specialite`, `diplome`, `date_embauche`, `salaire_base`, `status`, `user_id`, `created_at`) VALUES
(1, 'EMP20259771', 'Siwa', 'Carin', 'M', '1998-07-09', 'Goma', 'AV. ITEBERO N°100 Q/ MABANGA NORD C/ KARISIMBI', '+243975579097', 'thecarinsiwa@gmail.com', 'directeur', 'dfdf', 'fdfdf', '2025-08-08', 2000000.00, 'actif', NULL, '2025-08-08 19:48:45');

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
-- Structure de la table `transfers`
--

CREATE TABLE `transfers` (
  `id` int NOT NULL,
  `eleve_id` int NOT NULL,
  `type_mouvement` enum('transfert_entrant','transfert_sortant','sortie_definitive') COLLATE utf8mb4_unicode_ci NOT NULL,
  `ecole_origine` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ecole_destination` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `classe_origine_id` int DEFAULT NULL,
  `classe_destination_id` int DEFAULT NULL,
  `motif` text COLLATE utf8mb4_unicode_ci,
  `date_demande` date NOT NULL,
  `date_effective` date DEFAULT NULL,
  `statut` enum('en_attente','approuve','rejete','complete') COLLATE utf8mb4_unicode_ci DEFAULT 'en_attente',
  `documents_requis` json DEFAULT NULL,
  `documents_fournis` json DEFAULT NULL,
  `frais_transfert` decimal(10,2) DEFAULT '0.00',
  `frais_payes` decimal(10,2) DEFAULT '0.00',
  `observations` text COLLATE utf8mb4_unicode_ci,
  `approuve_par` int DEFAULT NULL,
  `date_approbation` datetime DEFAULT NULL,
  `traite_par` int DEFAULT NULL,
  `date_traitement` datetime DEFAULT NULL,
  `certificat_genere` tinyint(1) DEFAULT '0',
  `numero_certificat` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `transfers`
--

INSERT INTO `transfers` (`id`, `eleve_id`, `type_mouvement`, `ecole_origine`, `ecole_destination`, `classe_origine_id`, `classe_destination_id`, `motif`, `date_demande`, `date_effective`, `statut`, `documents_requis`, `documents_fournis`, `frais_transfert`, `frais_payes`, `observations`, `approuve_par`, `date_approbation`, `traite_par`, `date_traitement`, `certificat_genere`, `numero_certificat`, `created_at`, `updated_at`) VALUES
(1, 1, 'transfert_entrant', 'École Primaire Saint-Joseph', 'Notre École', NULL, NULL, 'Déménagement de la famille', '2025-07-24', '2025-07-29', 'complete', NULL, NULL, 50000.00, 50000.00, NULL, NULL, NULL, NULL, NULL, 0, NULL, '2025-08-08 19:50:24', '2025-08-08 19:50:24'),
(2, 2, 'transfert_sortant', 'Notre École', 'Collège Moderne de Kinshasa', NULL, NULL, 'Changement de niveau d\'études', '2025-08-03', NULL, 'en_attente', NULL, NULL, 75000.00, 0.00, NULL, NULL, NULL, NULL, NULL, 0, NULL, '2025-08-08 19:50:24', '2025-08-08 19:50:24'),
(3, 3, 'sortie_definitive', 'Notre École', NULL, NULL, NULL, 'Fin de scolarité', '2025-08-06', '2025-08-08', 'complete', NULL, NULL, 25000.00, 25000.00, NULL, NULL, NULL, NULL, NULL, 1, 'CERT2025000003', '2025-08-08 19:50:24', '2025-08-08 21:00:10');

-- --------------------------------------------------------

--
-- Structure de la table `transferts`
--

CREATE TABLE `transferts` (
  `id` int NOT NULL,
  `eleve_id` int NOT NULL,
  `classe_origine_id` int NOT NULL,
  `classe_destination_id` int DEFAULT NULL,
  `ecole_destination` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `motif` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `date_demande` date NOT NULL,
  `date_transfert` date DEFAULT NULL,
  `status` enum('en_attente','approuve','refuse','effectue') COLLATE utf8mb4_unicode_ci DEFAULT 'en_attente',
  `approuve_par` int DEFAULT NULL,
  `date_approbation` timestamp NULL DEFAULT NULL,
  `observations` text COLLATE utf8mb4_unicode_ci,
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
  `type_mouvement` enum('transfert','sortie_definitive','abandon','exclusion') COLLATE utf8mb4_unicode_ci NOT NULL,
  `motif` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `date_demande` date NOT NULL,
  `date_effective` date DEFAULT NULL,
  `ecole_destination` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `adresse_destination` text COLLATE utf8mb4_unicode_ci,
  `contact_destination` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `telephone_destination` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` enum('en_attente','approuve','rejete','effectue') COLLATE utf8mb4_unicode_ci DEFAULT 'en_attente',
  `traite_par` int DEFAULT NULL,
  `date_traitement` timestamp NULL DEFAULT NULL,
  `observations_demande` text COLLATE utf8mb4_unicode_ci,
  `observations_traitement` text COLLATE utf8mb4_unicode_ci,
  `document_justificatif` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `certificat_genere` tinyint(1) DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `transferts_sorties`
--

INSERT INTO `transferts_sorties` (`id`, `eleve_id`, `annee_scolaire_id`, `type_mouvement`, `motif`, `date_demande`, `date_effective`, `ecole_destination`, `adresse_destination`, `contact_destination`, `telephone_destination`, `status`, `traite_par`, `date_traitement`, `observations_demande`, `observations_traitement`, `document_justificatif`, `certificat_genere`, `created_at`, `updated_at`) VALUES
(1, 1, 1, 'transfert', 'Déménagement de la famille vers un autre quartier', '2025-08-08', NULL, 'École Primaire Saint-Joseph', NULL, NULL, NULL, 'en_attente', NULL, NULL, NULL, NULL, NULL, 0, '2025-08-08 14:36:40', NULL),
(2, 2, 1, 'sortie_definitive', 'Fin de scolarité primaire - passage au secondaire', '2025-08-08', NULL, 'Collège Notre-Dame de Kinshasa', NULL, NULL, NULL, 'approuve', NULL, NULL, NULL, NULL, NULL, 0, '2025-08-08 14:36:40', NULL),
(3, 3, 1, 'transfert', 'Changement de quartier pour raisons professionnelles des parents', '2025-08-08', NULL, 'École Communautaire de Gombe', NULL, NULL, NULL, 'en_attente', NULL, NULL, NULL, NULL, NULL, 0, '2025-08-08 14:36:40', NULL);

-- --------------------------------------------------------

--
-- Structure de la table `transfer_documents`
--

CREATE TABLE `transfer_documents` (
  `id` int NOT NULL,
  `transfer_id` int NOT NULL,
  `nom_document` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `type_document` enum('bulletin','certificat_scolarite','acte_naissance','photo','autre') COLLATE utf8mb4_unicode_ci NOT NULL,
  `chemin_fichier` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `taille_fichier` int DEFAULT NULL,
  `type_mime` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `obligatoire` tinyint(1) DEFAULT '0',
  `fourni` tinyint(1) DEFAULT '0',
  `date_upload` datetime DEFAULT NULL,
  `uploaded_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `transfer_documents`
--

INSERT INTO `transfer_documents` (`id`, `transfer_id`, `nom_document`, `type_document`, `chemin_fichier`, `taille_fichier`, `type_mime`, `obligatoire`, `fourni`, `date_upload`, `uploaded_by`, `created_at`) VALUES
(1, 1, 'Bulletin scolaire', 'bulletin', NULL, NULL, NULL, 1, 1, NULL, NULL, '2025-08-08 19:50:24');

-- --------------------------------------------------------

--
-- Structure de la table `transfer_fees`
--

CREATE TABLE `transfer_fees` (
  `id` int NOT NULL,
  `transfer_id` int NOT NULL,
  `type_frais` enum('frais_transfert','frais_certificat','frais_dossier','autre') COLLATE utf8mb4_unicode_ci NOT NULL,
  `libelle` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `montant` decimal(10,2) NOT NULL,
  `paye` tinyint(1) DEFAULT '0',
  `date_paiement` datetime DEFAULT NULL,
  `mode_paiement` enum('especes','virement','cheque','mobile_money') COLLATE utf8mb4_unicode_ci DEFAULT 'especes',
  `reference_paiement` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `transfer_history`
--

CREATE TABLE `transfer_history` (
  `id` int NOT NULL,
  `transfer_id` int NOT NULL,
  `action` enum('creation','modification','approbation','rejet','completion','annulation') COLLATE utf8mb4_unicode_ci NOT NULL,
  `ancien_statut` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `nouveau_statut` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `commentaire` text COLLATE utf8mb4_unicode_ci,
  `user_id` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `transfer_history`
--

INSERT INTO `transfer_history` (`id`, `transfer_id`, `action`, `ancien_statut`, `nouveau_statut`, `commentaire`, `user_id`, `created_at`) VALUES
(1, 3, 'modification', 'approuve', 'approuve', 'Documents mis à jour', 1, '2025-08-08 20:28:19'),
(2, 3, 'completion', 'approuve', 'complete', 'Il peut partir', 1, '2025-08-08 21:00:10');

-- --------------------------------------------------------

--
-- Structure de la table `users`
--

CREATE TABLE `users` (
  `id` int NOT NULL,
  `username` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `password` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `nom` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `prenom` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `telephone` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `role` enum('admin','directeur','enseignant','secretaire','comptable','surveillant') COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` enum('actif','inactif') COLLATE utf8mb4_unicode_ci DEFAULT 'actif',
  `photo` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `adresse` text COLLATE utf8mb4_unicode_ci,
  `date_naissance` date DEFAULT NULL,
  `genre` enum('M','F') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `derniere_connexion` timestamp NULL DEFAULT NULL,
  `tentatives_connexion` int DEFAULT '0',
  `compte_verrouille` tinyint(1) DEFAULT '0',
  `date_verrouillage` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `users`
--

INSERT INTO `users` (`id`, `username`, `password`, `nom`, `prenom`, `email`, `telephone`, `role`, `status`, `photo`, `adresse`, `date_naissance`, `genre`, `derniere_connexion`, `tentatives_connexion`, `compte_verrouille`, `date_verrouillage`, `created_at`, `updated_at`) VALUES
(1, 'admin', '7c4a8d09ca3762af61e59520943dc26494f8941b', 'Siwa', 'Carin', 'thecarinsiwa@gmail.com', '0975579097', 'admin', 'actif', NULL, NULL, NULL, NULL, '2025-08-08 22:17:41', 0, 0, NULL, '2025-08-08 13:26:12', '2025-08-08 22:17:41');

-- --------------------------------------------------------

--
-- Structure de la table `user_actions_log`
--

CREATE TABLE `user_actions_log` (
  `id` int NOT NULL,
  `user_id` int NOT NULL,
  `action` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `module` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `details` text COLLATE utf8mb4_unicode_ci,
  `target_id` int DEFAULT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `user_actions_log`
--

INSERT INTO `user_actions_log` (`id`, `user_id`, `action`, `module`, `details`, `target_id`, `ip_address`, `user_agent`, `created_at`) VALUES
(1, 1, 'test_creation_table', 'system', 'Test de création de la table user_actions_log', NULL, '127.0.0.1', 'Test Script', '2025-08-08 18:14:05'),
(2, 1, 'create_absence', 'attendance', 'Absence créée pour MUKENDI Jean (1ère Primaire A) - Date: 08/08/2025 19:14 - Motif: n,h,n,n,', 4, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-08-08 18:14:57'),
(3, 1, 'test_final', 'attendance', 'Test final de la table user_actions_log', NULL, NULL, NULL, '2025-08-08 18:15:42'),
(4, 1, 'add_absence', 'attendance', 'Test d\'ajout d\'absence', NULL, NULL, NULL, '2025-08-08 18:15:42'),
(5, 1, 'view_records', 'records', 'Test de consultation des dossiers', NULL, NULL, NULL, '2025-08-08 18:15:42'),
(6, 1, 'view_absence_edit', 'attendance', 'Consultation de la page d\'édition de l\'absence ID 4', 4, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-08-08 18:24:31'),
(7, 1, 'view_absence_edit', 'attendance', 'Consultation de la page d\'édition de l\'absence ID 4', 4, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-08-08 18:26:39'),
(8, 1, 'view_absence_edit', 'attendance', 'Consultation de la page d\'édition de l\'absence ID 6', 6, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-08-08 18:28:58'),
(9, 1, 'justify_absence', 'attendance', 'Absence justifiée - Justification: hghghghghghgvbfdfdfdf', 6, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-08-08 18:34:08'),
(10, 1, 'view_absence_edit', 'attendance', 'Consultation de la page d\'édition de l\'absence ID 6', 6, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-08-08 18:34:08'),
(11, 1, 'view_monthly_report', 'attendance', 'Consultation du rapport mensuel - Mois: 2025-08', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-08-08 18:42:34'),
(12, 1, 'view_monthly_report', 'attendance', 'Consultation du rapport mensuel - Mois: 2025-09', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-08-08 18:42:56'),
(13, 1, 'view_monthly_report', 'attendance', 'Consultation du rapport mensuel - Mois: 2025-12', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-08-08 18:43:09'),
(14, 1, 'view_monthly_report', 'attendance', 'Consultation du rapport mensuel - Mois: 2025-11', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-08-08 18:43:14'),
(15, 1, 'view_monthly_report', 'attendance', 'Consultation du rapport mensuel - Mois: 2025-02', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-08-08 18:43:17'),
(16, 1, 'view_monthly_report', 'attendance', 'Consultation du rapport mensuel - Mois: 2025-07', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-08-08 18:43:23'),
(17, 1, 'view_monthly_report', 'attendance', 'Consultation du rapport mensuel - Mois: 2025-08', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-08-08 18:43:28'),
(18, 1, 'view_monthly_report', 'attendance', 'Consultation du rapport mensuel - Mois: 2025-08', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-08-08 18:43:29'),
(19, 1, 'view_monthly_report', 'attendance', 'Consultation du rapport mensuel - Mois: 2025-08', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-08-08 18:43:45'),
(20, 1, 'view_monthly_report', 'attendance', 'Consultation du rapport mensuel - Mois: 2025-08', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-08-08 18:44:01'),
(21, 1, 'view_monthly_report', 'attendance', 'Consultation du rapport mensuel - Mois: 2025-08', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-08-08 18:45:31'),
(22, 1, 'view_monthly_report', 'attendance', 'Consultation du rapport mensuel - Mois: 2025-08', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-08-08 18:45:35'),
(23, 1, 'view_monthly_report', 'attendance', 'Consultation du rapport mensuel - Mois: 2025-07', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-08-08 18:45:36'),
(24, 1, 'view_monthly_report', 'attendance', 'Consultation du rapport mensuel - Mois: 2025-02', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-08-08 18:45:37'),
(25, 1, 'view_monthly_report', 'attendance', 'Consultation du rapport mensuel - Mois: 2025-11', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-08-08 18:45:38'),
(26, 1, 'view_monthly_report', 'attendance', 'Consultation du rapport mensuel - Mois: 2025-12', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-08-08 18:45:39'),
(27, 1, 'view_monthly_report', 'attendance', 'Consultation du rapport mensuel - Mois: 2025-09', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-08-08 18:45:39'),
(28, 1, 'view_monthly_report', 'attendance', 'Consultation du rapport mensuel - Mois: 2025-08', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-08-08 18:45:40'),
(29, 1, 'view_monthly_report', 'attendance', 'Consultation du rapport mensuel - Mois: 2025-08', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-08-08 18:45:50'),
(30, 1, 'view_monthly_report', 'attendance', 'Consultation du rapport mensuel - Mois: 2025-08', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-08-08 18:48:53'),
(31, 1, 'get_students', 'attendance', 'Récupération des élèves - Classe ID: 3, Date: 2025-08-08', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-08-08 18:50:42'),
(32, 1, 'get_students', 'attendance', 'Récupération des élèves - Classe ID: 7, Date: 2025-08-08', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-08-08 18:50:57'),
(33, 1, 'get_students', 'attendance', 'Récupération des élèves - Classe ID: 6, Date: 2025-08-08', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-08-08 18:51:04'),
(34, 1, 'view_monthly_report', 'attendance', 'Consultation du rapport mensuel - Mois: 2025-08', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-08-08 18:53:11'),
(35, 1, 'view_monthly_report', 'attendance', 'Consultation du rapport mensuel - Mois: 2025-08', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-08-08 18:53:57'),
(36, 1, 'view_monthly_report', 'attendance', 'Consultation du rapport mensuel - Mois: 2025-08, Classe: 1', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-08-08 18:54:08'),
(37, 1, 'get_students', 'attendance', 'Récupération des élèves - Classe ID: 2, Date: 2025-08-08', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-08-08 19:05:31'),
(38, 1, 'view_parent_notifications', 'attendance', 'Consultation de la page notifications parents', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-08-08 19:08:16'),
(39, 1, 'send_single_notification', 'attendance', 'Notification individuelle - Type: email, Élève: MUKENDI Jean, Statut: sent', 4, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-08-08 19:09:54'),
(40, 1, 'view_parent_notifications', 'attendance', 'Consultation de la page notifications parents', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-08-08 19:09:56'),
(41, 1, 'view_absence_edit', 'attendance', 'Consultation de la page d\'édition de l\'absence ID 4', 4, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-08-08 19:10:02'),
(42, 1, 'view_parent_notifications', 'attendance', 'Consultation de la page notifications parents', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-08-08 19:11:19'),
(43, 1, 'view_absence_edit', 'attendance', 'Consultation de la page d\'édition de l\'absence ID 4', 4, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-08-08 19:11:22'),
(44, 1, 'view_monthly_report', 'attendance', 'Consultation du rapport mensuel - Mois: 2025-08', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-08-08 19:11:53'),
(45, 1, 'view_monthly_report', 'attendance', 'Consultation du rapport mensuel - Mois: 2025-08', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-08-08 19:13:24'),
(46, 1, 'view_monthly_report', 'attendance', 'Consultation du rapport mensuel - Mois: 2025-08', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-08-08 19:14:20'),
(47, 1, 'view_monthly_report', 'attendance', 'Consultation du rapport mensuel - Mois: 2025-08', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-08-08 19:14:52'),
(48, 1, 'view_monthly_report', 'attendance', 'Consultation du rapport mensuel - Mois: 2025-08', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-08-08 19:17:28'),
(49, 1, 'view_monthly_report', 'attendance', 'Consultation du rapport mensuel - Mois: 2025-08, Classe: 2', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-08-08 19:17:45'),
(50, 1, 'view_monthly_report', 'attendance', 'Consultation du rapport mensuel - Mois: 2025-08, Classe: 1', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-08-08 19:17:49'),
(51, 1, 'view_monthly_report', 'attendance', 'Consultation du rapport mensuel - Mois: 2025-08, Classe: 2', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-08-08 19:17:55'),
(52, 1, 'view_monthly_report', 'attendance', 'Consultation du rapport mensuel - Mois: 2025-08, Classe: 4', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-08-08 19:18:01'),
(53, 1, 'view_monthly_report', 'attendance', 'Consultation du rapport mensuel - Mois: 2025-08, Classe: 4', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-08-08 19:19:17'),
(54, 1, 'view_monthly_report', 'attendance', 'Consultation du rapport mensuel - Mois: 2025-08', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-08-08 19:19:32'),
(55, 1, 'view_monthly_report', 'attendance', 'Consultation du rapport mensuel - Mois: 2025-08', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-08-08 19:20:55'),
(56, 1, 'view_monthly_report', 'attendance', 'Consultation du rapport mensuel - Mois: 2025-08', NULL, '::1', 'Mozilla/5.0 (Linux; Android 6.0; Nexus 5 Build/MRA58N) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Mobile Safari/537.36', '2025-08-08 19:21:17'),
(57, 1, 'create_absence', 'attendance', 'Absence créée pour KALONJI Esther (1ère Primaire A) - Date: 08/08/2025 20:21 - Motif: k_yfeeddddfggghhh', 12, '::1', 'Mozilla/5.0 (Linux; Android 6.0; Nexus 5 Build/MRA58N) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Mobile Safari/537.36', '2025-08-08 19:22:23'),
(58, 1, 'view_monthly_report', 'attendance', 'Consultation du rapport mensuel - Mois: 2025-08', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-08-08 19:24:24'),
(59, 1, 'view_monthly_report', 'attendance', 'Consultation du rapport mensuel - Mois: 2025-08', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-08-08 19:25:11'),
(60, 1, 'view_monthly_report', 'attendance', 'Consultation du rapport mensuel - Mois: 2025-08', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-08-08 19:29:15'),
(61, 1, 'view_monthly_report', 'attendance', 'Consultation du rapport mensuel - Mois: 2025-08', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-08-08 19:29:35'),
(62, 1, 'view_monthly_report', 'attendance', 'Consultation du rapport mensuel - Mois: 2025-08', NULL, '::1', 'Mozilla/5.0 (Linux; Android 6.0; Nexus 5 Build/MRA58N) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Mobile Safari/537.36', '2025-08-08 19:30:56'),
(63, 1, 'view_export_page', 'attendance', 'Consultation de la page d\'export des données', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-08-08 19:35:13'),
(64, 1, 'export_attendance', 'attendance', 'Export excel - Type: summary, Période: 2025-08-01 à 2025-08-31, Enregistrements: 5', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-08-08 19:35:45'),
(65, 1, 'export_attendance', 'attendance', 'Export pdf - Type: summary, Période: 2025-08-01 à 2025-08-31, Enregistrements: 5', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-08-08 19:36:11'),
(66, 1, 'view_export_page', 'attendance', 'Consultation de la page d\'export des données', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-08-08 19:36:32'),
(67, 1, 'preview_export_data', 'attendance', 'Aperçu export - Type: summary, Période: 2025-08-01 à 2025-08-31, Résultats: 5', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-08-08 19:36:41'),
(68, 1, 'view_export_page', 'attendance', 'Consultation de la page d\'export des données', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-08-08 19:40:01'),
(69, 1, 'export_attendance', 'attendance', 'Export excel - Type: summary, Période: 2025-08-01 à 2025-08-31, Enregistrements: 1', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-08-08 19:40:16'),
(70, 1, 'view_export_page', 'attendance', 'Consultation de la page d\'export des données', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-08-08 19:40:46'),
(71, 1, 'view_export_page', 'attendance', 'Consultation de la page d\'export des données', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-08-08 19:47:01'),
(72, 1, 'export_movements', 'transfers', 'Export excel - Type: detailed, Période: 2025-08-01 à 2025-08-31, Enregistrements: 2', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-08-08 20:05:02'),
(73, 1, 'process_transfer', 'transfers', 'Action \'update_documents\' sur le transfert ID: 3', 3, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-08-08 20:28:19'),
(74, 1, 'process_transfer', 'transfers', 'Action \'complete\' sur le transfert ID: 3', 3, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-08-08 21:00:10');

-- --------------------------------------------------------

--
-- Structure de la table `campagnes_recouvrement`
--

CREATE TABLE `campagnes_recouvrement` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nom` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `type_cible` enum('tous','retard','montant','niveau') COLLATE utf8mb4_unicode_ci NOT NULL,
  `montant_min` decimal(10,2) DEFAULT NULL,
  `montant_max` decimal(10,2) DEFAULT NULL,
  `date_debut` date NOT NULL,
  `date_fin` date NOT NULL,
  `strategie` enum('appel_telephonique','sms','email','visite_domicile','lettre','mixte') COLLATE utf8mb4_unicode_ci DEFAULT 'mixte',
  `budget` decimal(10,2) DEFAULT '0.00',
  `annee_scolaire_id` int NOT NULL,
  `status` enum('active','paused','completed','cancelled') COLLATE utf8mb4_unicode_ci DEFAULT 'active',
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `annee_scolaire_id` (`annee_scolaire_id`),
  KEY `status` (`status`),
  KEY `created_by` (`created_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `campagnes_cibles_dettes`
--

CREATE TABLE `campagnes_cibles_dettes` (
  `id` int NOT NULL AUTO_INCREMENT,
  `campagne_id` int NOT NULL,
  `eleve_id` int NOT NULL,
  `montant_dette` decimal(10,2) NOT NULL,
  `status` enum('pending','contacte','paye','refuse','injoignable') COLLATE utf8mb4_unicode_ci DEFAULT 'pending',
  `montant_recouvre` decimal(10,2) DEFAULT '0.00',
  `date_contact` date DEFAULT NULL,
  `methode_contact` enum('appel','sms','email','visite','lettre') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `commentaire` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `campagne_id` (`campagne_id`),
  KEY `eleve_id` (`eleve_id`),
  KEY `status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `notifications_recouvrement`
--

CREATE TABLE `notifications_recouvrement` (
  `id` int NOT NULL AUTO_INCREMENT,
  `type_notification` enum('sms','email','lettre') COLLATE utf8mb4_unicode_ci NOT NULL,
  `sujet` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `message` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `campagne_id` int DEFAULT NULL,
  `annee_scolaire_id` int NOT NULL,
  `status` enum('pending','sent','failed','cancelled') COLLATE utf8mb4_unicode_ci DEFAULT 'pending',
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `campagne_id` (`campagne_id`),
  KEY `annee_scolaire_id` (`annee_scolaire_id`),
  KEY `type_notification` (`type_notification`),
  KEY `status` (`status`),
  KEY `created_by` (`created_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `notifications_destinataires`
--

CREATE TABLE `notifications_destinataires` (
  `id` int NOT NULL AUTO_INCREMENT,
  `notification_id` int NOT NULL,
  `eleve_id` int NOT NULL,
  `status` enum('pending','sent','failed','delivered','read') COLLATE utf8mb4_unicode_ci DEFAULT 'pending',
  `sent_at` timestamp NULL DEFAULT NULL,
  `delivered_at` timestamp NULL DEFAULT NULL,
  `read_at` timestamp NULL DEFAULT NULL,
  `error_message` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `notification_id` (`notification_id`),
  KEY `eleve_id` (`eleve_id`),
  KEY `status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Données pour la table `campagnes_recouvrement`
--

INSERT INTO `campagnes_recouvrement` (`nom`, `description`, `type_cible`, `montant_min`, `montant_max`, `date_debut`, `date_fin`, `strategie`, `budget`, `annee_scolaire_id`, `status`, `created_by`) VALUES
('Campagne de rappel général', 'Rappel général pour tous les débiteurs', 'tous', NULL, NULL, '2025-08-01', '2025-08-31', 'mixte', 50000.00, 1, 'active', 1),
('Campagne gros débiteurs', 'Focus sur les dettes supérieures à 100,000 FC', 'montant', 100000.00, NULL, '2025-08-15', '2025-09-15', 'visite_domicile', 75000.00, 1, 'active', 1),
('Campagne primaire', 'Récupération spécifique niveau primaire', 'niveau', NULL, NULL, '2025-08-10', '2025-08-25', 'sms', 25000.00, 1, 'completed', 1);

-- --------------------------------------------------------

--
-- Données pour la table `notifications_recouvrement`
--

INSERT INTO `notifications_recouvrement` (`type_notification`, `sujet`, `message`, `campagne_id`, `annee_scolaire_id`, `status`, `created_by`) VALUES
('sms', 'Rappel paiement', 'Bonjour {nom_parent}, votre enfant {nom_eleve} a une dette de {montant} FC. Merci de régulariser.', 1, 1, 'sent', 1),
('email', 'Lettre de rappel', 'Madame, Monsieur, nous vous rappelons que votre enfant {nom_eleve} a une dette de {montant} FC.', 1, 1, 'sent', 1),
('lettre', 'Mise en demeure', 'Suite à nos relances, nous vous mettons en demeure de régulariser la dette de {montant} FC.', 2, 1, 'pending', 1);

-- --------------------------------------------------------

--
-- Structure de la table `criteres_admission`
--

CREATE TABLE `criteres_admission` (
  `id` int NOT NULL AUTO_INCREMENT,
  `annee_scolaire_id` int NOT NULL,
  `niveau` enum('maternelle','primaire','secondaire','superieur') NOT NULL,
  `age_min` int DEFAULT NULL COMMENT 'Âge minimum en années',
  `age_max` int DEFAULT NULL COMMENT 'Âge maximum en années',
  `capacite_max` int DEFAULT NULL COMMENT 'Capacité maximale pour ce niveau',
  `note_min` decimal(4,2) DEFAULT NULL COMMENT 'Note minimale requise (sur 20)',
  `documents_requis` text COLLATE utf8mb4_unicode_ci COMMENT 'Liste des documents requis',
  `conditions_speciales` text COLLATE utf8mb4_unicode_ci COMMENT 'Conditions spéciales d''admission',
  `actif` tinyint(1) DEFAULT '1' COMMENT 'Critères actifs ou non',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `annee_scolaire_id` (`annee_scolaire_id`),
  KEY `niveau` (`niveau`),
  CONSTRAINT `criteres_admission_ibfk_1` FOREIGN KEY (`annee_scolaire_id`) REFERENCES `annees_scolaires` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `criteres_admission`
--

INSERT INTO `criteres_admission` (`annee_scolaire_id`, `niveau`, `age_min`, `age_max`, `capacite_max`, `note_min`, `documents_requis`, `conditions_speciales`, `actif`) VALUES
(1, 'maternelle', 3, 6, 25, 0.00, 'Acte de naissance, Carnet de vaccination, Photo 4x4', 'Enfant propre et autonome', 1),
(1, 'primaire', 6, 12, 35, 10.00, 'Acte de naissance, Certificat de fin de maternelle, Photo 4x4', 'Test d''évaluation obligatoire', 1),
(1, 'secondaire', 12, 18, 40, 12.00, 'Acte de naissance, Certificat de fin de primaire, Photo 4x4', 'Entretien avec les parents', 1),
(1, 'superieur', 18, 25, 30, 14.00, 'Acte de naissance, Diplôme de fin de secondaire, Photo 4x4', 'Test d''admission et entretien', 1);

-- --------------------------------------------------------

--
-- Structure de la table `criteres_admission_classes`
--

CREATE TABLE `criteres_admission_classes` (
  `id` int NOT NULL AUTO_INCREMENT,
  `annee_scolaire_id` int NOT NULL,
  `classe_id` int NOT NULL,
  `capacite_max` int DEFAULT NULL COMMENT 'Capacité maximale pour cette classe',
  `note_min` decimal(4,2) DEFAULT NULL COMMENT 'Note minimale requise pour cette classe',
  `actif` tinyint(1) DEFAULT '1' COMMENT 'Critères actifs ou non',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `annee_scolaire_id` (`annee_scolaire_id`),
  KEY `classe_id` (`classe_id`),
  CONSTRAINT `criteres_admission_classes_ibfk_1` FOREIGN KEY (`annee_scolaire_id`) REFERENCES `annees_scolaires` (`id`) ON DELETE CASCADE,
  CONSTRAINT `criteres_admission_classes_ibfk_2` FOREIGN KEY (`classe_id`) REFERENCES `classes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `criteres_admission_classes`
--

INSERT INTO `criteres_admission_classes` (`annee_scolaire_id`, `classe_id`, `capacite_max`, `note_min`, `actif`) 
SELECT 1, id, capacite_max, 10.00, 1 
FROM classes 
WHERE annee_scolaire_id = 1;

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
-- Index pour la table `classes`
--
ALTER TABLE `classes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `annee_scolaire_id` (`annee_scolaire_id`),
  ADD KEY `fk_titulaire` (`titulaire_id`);

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
  ADD KEY `traite_par` (`traite_par`);

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
  ADD KEY `idx_parent_id` (`parent_id`);

--
-- Index pour la table `emplois_temps`
--
ALTER TABLE `emplois_temps`
  ADD PRIMARY KEY (`id`),
  ADD KEY `classe_id` (`classe_id`),
  ADD KEY `matiere_id` (`matiere_id`),
  ADD KEY `enseignant_id` (`enseignant_id`),
  ADD KEY `annee_scolaire_id` (`annee_scolaire_id`);

--
-- Index pour la table `emprunts`
--
ALTER TABLE `emprunts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `livre_id` (`livre_id`),
  ADD KEY `eleve_id` (`eleve_id`);

--
-- Index pour la table `etablissements`
--
ALTER TABLE `etablissements`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `code_etablissement` (`code_etablissement`);

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
-- Index pour la table `inscriptions`
--
ALTER TABLE `inscriptions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_inscription` (`eleve_id`,`annee_scolaire_id`),
  ADD KEY `classe_id` (`classe_id`),
  ADD KEY `annee_scolaire_id` (`annee_scolaire_id`);

--
-- Index pour la table `livres`
--
ALTER TABLE `livres`
  ADD PRIMARY KEY (`id`);

--
-- Index pour la table `matieres`
--
ALTER TABLE `matieres`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `code` (`code`);

--
-- Index pour la table `notes`
--
ALTER TABLE `notes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_note` (`evaluation_id`,`eleve_id`),
  ADD KEY `eleve_id` (`eleve_id`);

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
-- Index pour la table `paiements`
--
ALTER TABLE `paiements`
  ADD PRIMARY KEY (`id`),
  ADD KEY `eleve_id` (`eleve_id`),
  ADD KEY `annee_scolaire_id` (`annee_scolaire_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Index pour la table `parents`
--
ALTER TABLE `parents`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_telephone` (`telephone`),
  ADD KEY `idx_email` (`email`);

--
-- Index pour la table `personnel`
--
ALTER TABLE `personnel`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `matricule` (`matricule`),
  ADD KEY `user_id` (`user_id`);

--
-- Index pour la table `sanctions`
--
ALTER TABLE `sanctions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `eleve_id` (`eleve_id`),
  ADD KEY `enseignant_id` (`enseignant_id`);

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
  ADD KEY `traite_par` (`traite_par`);

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
-- Index pour la table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `idx_username` (`username`),
  ADD KEY `idx_email` (`email`),
  ADD KEY `idx_role` (`role`),
  ADD KEY `idx_status` (`status`);

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
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT pour la table `annees_scolaires`
--
ALTER TABLE `annees_scolaires`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT pour la table `classes`
--
ALTER TABLE `classes`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT pour la table `demandes_admission`
--
ALTER TABLE `demandes_admission`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT pour la table `documents_eleves`
--
ALTER TABLE `documents_eleves`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT pour la table `eleves`
--
ALTER TABLE `eleves`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT pour la table `emplois_temps`
--
ALTER TABLE `emplois_temps`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `emprunts`
--
ALTER TABLE `emprunts`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `etablissements`
--
ALTER TABLE `etablissements`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT pour la table `evaluations`
--
ALTER TABLE `evaluations`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `inscriptions`
--
ALTER TABLE `inscriptions`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT pour la table `livres`
--
ALTER TABLE `livres`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `matieres`
--
ALTER TABLE `matieres`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT pour la table `notes`
--
ALTER TABLE `notes`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `notifications_parents`
--
ALTER TABLE `notifications_parents`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT pour la table `paiements`
--
ALTER TABLE `paiements`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `parents`
--
ALTER TABLE `parents`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT pour la table `personnel`
--
ALTER TABLE `personnel`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT pour la table `sanctions`
--
ALTER TABLE `sanctions`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `transfers`
--
ALTER TABLE `transfers`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT pour la table `transferts`
--
ALTER TABLE `transferts`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `transferts_sorties`
--
ALTER TABLE `transferts_sorties`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT pour la table `transfer_documents`
--
ALTER TABLE `transfer_documents`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT pour la table `transfer_fees`
--
ALTER TABLE `transfer_fees`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `transfer_history`
--
ALTER TABLE `transfer_history`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT pour la table `users`
--
ALTER TABLE `users`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT pour la table `user_actions_log`
--
ALTER TABLE `user_actions_log`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=75;

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
-- Contraintes pour la table `classes`
--
ALTER TABLE `classes`
  ADD CONSTRAINT `classes_ibfk_1` FOREIGN KEY (`annee_scolaire_id`) REFERENCES `annees_scolaires` (`id`),
  ADD CONSTRAINT `fk_titulaire` FOREIGN KEY (`titulaire_id`) REFERENCES `personnel` (`id`);

--
-- Contraintes pour la table `demandes_admission`
--
ALTER TABLE `demandes_admission`
  ADD CONSTRAINT `demandes_admission_ibfk_1` FOREIGN KEY (`annee_scolaire_id`) REFERENCES `annees_scolaires` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `demandes_admission_ibfk_2` FOREIGN KEY (`classe_demandee_id`) REFERENCES `classes` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `demandes_admission_ibfk_3` FOREIGN KEY (`traite_par`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Contraintes pour la table `emplois_temps`
--
ALTER TABLE `emplois_temps`
  ADD CONSTRAINT `emplois_temps_ibfk_1` FOREIGN KEY (`classe_id`) REFERENCES `classes` (`id`),
  ADD CONSTRAINT `emplois_temps_ibfk_2` FOREIGN KEY (`matiere_id`) REFERENCES `matieres` (`id`),
  ADD CONSTRAINT `emplois_temps_ibfk_3` FOREIGN KEY (`enseignant_id`) REFERENCES `personnel` (`id`),
  ADD CONSTRAINT `emplois_temps_ibfk_4` FOREIGN KEY (`annee_scolaire_id`) REFERENCES `annees_scolaires` (`id`);

--
-- Contraintes pour la table `emprunts`
--
ALTER TABLE `emprunts`
  ADD CONSTRAINT `emprunts_ibfk_1` FOREIGN KEY (`livre_id`) REFERENCES `livres` (`id`),
  ADD CONSTRAINT `emprunts_ibfk_2` FOREIGN KEY (`eleve_id`) REFERENCES `eleves` (`id`);

--
-- Contraintes pour la table `evaluations`
--
ALTER TABLE `evaluations`
  ADD CONSTRAINT `evaluations_ibfk_1` FOREIGN KEY (`classe_id`) REFERENCES `classes` (`id`),
  ADD CONSTRAINT `evaluations_ibfk_2` FOREIGN KEY (`matiere_id`) REFERENCES `matieres` (`id`),
  ADD CONSTRAINT `evaluations_ibfk_3` FOREIGN KEY (`enseignant_id`) REFERENCES `personnel` (`id`),
  ADD CONSTRAINT `evaluations_ibfk_4` FOREIGN KEY (`annee_scolaire_id`) REFERENCES `annees_scolaires` (`id`);

--
-- Contraintes pour la table `inscriptions`
--
ALTER TABLE `inscriptions`
  ADD CONSTRAINT `inscriptions_ibfk_1` FOREIGN KEY (`eleve_id`) REFERENCES `eleves` (`id`),
  ADD CONSTRAINT `inscriptions_ibfk_2` FOREIGN KEY (`classe_id`) REFERENCES `classes` (`id`),
  ADD CONSTRAINT `inscriptions_ibfk_3` FOREIGN KEY (`annee_scolaire_id`) REFERENCES `annees_scolaires` (`id`);

--
-- Contraintes pour la table `notes`
--
ALTER TABLE `notes`
  ADD CONSTRAINT `notes_ibfk_1` FOREIGN KEY (`evaluation_id`) REFERENCES `evaluations` (`id`),
  ADD CONSTRAINT `notes_ibfk_2` FOREIGN KEY (`eleve_id`) REFERENCES `eleves` (`id`);

--
-- Contraintes pour la table `notifications_parents`
--
ALTER TABLE `notifications_parents`
  ADD CONSTRAINT `notifications_parents_ibfk_1` FOREIGN KEY (`absence_id`) REFERENCES `absences` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `notifications_parents_ibfk_2` FOREIGN KEY (`parent_id`) REFERENCES `parents` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `notifications_parents_ibfk_3` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`);

--
-- Contraintes pour la table `paiements`
--
ALTER TABLE `paiements`
  ADD CONSTRAINT `paiements_ibfk_1` FOREIGN KEY (`eleve_id`) REFERENCES `eleves` (`id`),
  ADD CONSTRAINT `paiements_ibfk_2` FOREIGN KEY (`annee_scolaire_id`) REFERENCES `annees_scolaires` (`id`),
  ADD CONSTRAINT `paiements_ibfk_3` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Contraintes pour la table `personnel`
--
ALTER TABLE `personnel`
  ADD CONSTRAINT `personnel_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Contraintes pour la table `sanctions`
--
ALTER TABLE `sanctions`
  ADD CONSTRAINT `sanctions_ibfk_1` FOREIGN KEY (`eleve_id`) REFERENCES `eleves` (`id`),
  ADD CONSTRAINT `sanctions_ibfk_2` FOREIGN KEY (`enseignant_id`) REFERENCES `personnel` (`id`);

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
-- Contraintes pour la table `campagnes_recouvrement`
--
ALTER TABLE `campagnes_recouvrement`
  ADD CONSTRAINT `campagnes_recouvrement_ibfk_1` FOREIGN KEY (`annee_scolaire_id`) REFERENCES `annees_scolaires` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `campagnes_recouvrement_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Contraintes pour la table `campagnes_cibles_dettes`
--
ALTER TABLE `campagnes_cibles_dettes`
  ADD CONSTRAINT `campagnes_cibles_dettes_ibfk_1` FOREIGN KEY (`campagne_id`) REFERENCES `campagnes_recouvrement` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `campagnes_cibles_dettes_ibfk_2` FOREIGN KEY (`eleve_id`) REFERENCES `eleves` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `notifications_recouvrement`
--
ALTER TABLE `notifications_recouvrement`
  ADD CONSTRAINT `notifications_recouvrement_ibfk_1` FOREIGN KEY (`campagne_id`) REFERENCES `campagnes_recouvrement` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `notifications_recouvrement_ibfk_2` FOREIGN KEY (`annee_scolaire_id`) REFERENCES `annees_scolaires` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `notifications_recouvrement_ibfk_3` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Contraintes pour la table `notifications_destinataires`
--
ALTER TABLE `notifications_destinataires`
  ADD CONSTRAINT `notifications_destinataires_ibfk_1` FOREIGN KEY (`notification_id`) REFERENCES `notifications_recouvrement` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `notifications_destinataires_ibfk_2` FOREIGN KEY (`eleve_id`) REFERENCES `eleves` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
