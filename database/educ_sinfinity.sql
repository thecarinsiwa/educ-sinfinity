-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Hôte : localhost
-- Généré le : mar. 02 sep. 2025 à 21:05
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
(1, '2023-2024', '2023-09-01', '2024-07-31', 'fermee', '2025-08-08 13:07:50', '2025-09-02 17:15:40'),
(2, '2025-2026', '2025-09-01', '2026-07-31', 'active', '2025-08-08 23:34:15', '2025-09-02 18:40:18'),
(3, '2026-2027', '2026-09-01', '2027-07-31', 'fermee', '2025-09-02 16:59:15', '2025-09-02 18:40:18');

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

--
-- Déchargement des données de la table `annonces`
--

INSERT INTO `annonces` (`id`, `titre`, `contenu`, `auteur_id`, `type_annonce`, `cible`, `classe_id`, `date_publication`, `date_expiration`, `epinglee`, `active`, `couleur`, `fichiers_joints`, `vues`, `created_at`, `updated_at`) VALUES
(1, 'Rentrée scolaire 2024-2025', 'La rentrée scolaire aura lieu le lundi 2 septembre 2024 à 8h00. Tous les élèves doivent se présenter avec leur matériel scolaire complet.', 1, 'generale', 'tous', NULL, '2025-08-09 20:16:12', '2025-09-08 20:16:12', 1, 1, '#007bff', NULL, 0, '2025-08-09 19:16:12', NULL),
(2, 'Réunion des parents', 'Une réunion générale des parents d\'élèves aura lieu le samedi 15 septembre à 9h00 dans la salle de conférence.', 1, 'evenement', 'parents', NULL, '2025-08-09 20:16:12', '2025-08-24 20:16:12', 0, 1, '#28a745', NULL, 0, '2025-08-09 19:16:12', NULL),
(3, 'Examens du premier trimestre', 'Les examens du premier trimestre se dérouleront du 25 novembre au 2 décembre 2024. Planning détaillé disponible au secrétariat.', 1, 'pedagogique', 'eleves', NULL, '2025-08-09 20:16:12', '2025-10-08 20:16:12', 0, 1, '#ffc107', NULL, 0, '2025-08-09 19:16:12', NULL);

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

--
-- Déchargement des données de la table `campagnes_recouvrement`
--

INSERT INTO `campagnes_recouvrement` (`id`, `nom`, `description`, `type_cible`, `montant_min`, `montant_max`, `date_debut`, `date_fin`, `strategie`, `budget`, `annee_scolaire_id`, `status`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 'Campagne de rappel g├®n├®ral', 'Rappel g├®n├®ral pour tous les d├®biteurs', 'tous', NULL, NULL, '2025-08-01', '2025-08-31', 'mixte', 50000.00, 1, 'active', 1, '2025-08-14 13:36:55', NULL),
(2, 'Campagne gros d├®biteurs', 'Focus sur les dettes sup├®rieures ├á 100,000 FC', 'montant', 100000.00, NULL, '2025-08-15', '2025-09-15', 'visite_domicile', 75000.00, 1, 'active', 1, '2025-08-14 13:36:55', NULL),
(3, 'Campagne primaire', 'R├®cup├®ration sp├®cifique niveau primaire', 'niveau', NULL, NULL, '2025-08-10', '2025-08-25', 'sms', 25000.00, 1, 'completed', 1, '2025-08-14 13:36:55', NULL),
(4, 'Chargeur Samsung', 'sdsdsd', 'tous', 1000.00, 1000.00, '2025-08-18', '2025-08-31', 'sms', 1000.00, 1, 'active', 1, '2025-08-18 18:18:00', NULL);

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

--
-- Déchargement des données de la table `cartes_eleves`
--

INSERT INTO `cartes_eleves` (`id`, `eleve_id`, `numero_carte`, `type_carte`, `status`, `date_emission`, `date_expiration`, `montant_limite`, `montant_utilise`, `observations`, `created_at`, `updated_at`) VALUES
(1, 1, 'CARD20250001', 'standard', 'active', '2025-08-14', NULL, 50000.00, 0.00, NULL, '2025-08-14 08:25:58', NULL);

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

--
-- Déchargement des données de la table `categories_livres`
--

INSERT INTO `categories_livres` (`id`, `nom`, `description`, `couleur`, `created_at`, `updated_at`, `actif`) VALUES
(1, 'Roman', 'Romans et littérature générale', '#e74c3c', '2025-08-09 17:42:45', NULL, 1),
(2, 'Sciences', 'Livres scientifiques et techniques', '#3498db', '2025-08-09 17:42:45', NULL, 1),
(3, 'Histoire', 'Livres d\'histoire et géographie', '#f39c12', '2025-08-09 17:42:45', NULL, 1),
(4, 'Mathématiques', 'Manuels et livres de mathématiques', '#9b59b6', '2025-08-09 17:42:45', NULL, 1),
(5, 'Langues', 'Dictionnaires et livres de langues', '#2ecc71', '2025-08-09 17:42:45', NULL, 1),
(6, 'Philosophie', 'Livres de philosophie et religion', '#34495e', '2025-08-09 17:42:45', NULL, 1),
(7, 'Arts', 'Livres d\'art et de culture', '#e67e22', '2025-08-09 17:42:45', NULL, 1),
(8, 'Jeunesse', 'Livres pour enfants et adolescents', '#f1c40f', '2025-08-09 17:42:45', NULL, 1),
(9, 'Référence', 'Encyclopédies et ouvrages de référence', '#750000', '2025-08-09 17:42:45', '2025-08-09 18:43:15', 1),
(10, 'Périodiques', 'Magazines et journaux', '#1abc9c', '2025-08-09 17:42:45', NULL, 1),
(11, 'Littérature', 'Romans, nouvelles, poésie', '#28a745', '2025-08-18 18:31:27', NULL, 1),
(12, 'Géographie', 'Géographie physique et humaine', '#6f42c1', '2025-08-18 18:31:27', NULL, 1),
(13, 'Technologie', 'Informatique, électronique', '#20c997', '2025-08-18 18:31:27', NULL, 1),
(14, 'Religion', 'Textes religieux et spirituels', '#495057', '2025-08-18 18:31:27', NULL, 1),
(15, 'Autres', 'Autres catégories', '#dee2e6', '2025-08-18 18:31:27', NULL, 1);

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
(7, '6ème Primaire A', 'primaire', 'A', NULL, NULL, NULL, 24, 50000.00, 25000.00, 1, '2025-08-08 14:29:52', NULL),
(8, '2ème Primaire A', 'primaire', NULL, NULL, NULL, NULL, 30, 50000.00, 25000.00, 2, '2025-09-02 13:43:36', '2025-09-02 13:43:36'),
(9, '2ème Primaire B', 'primaire', NULL, NULL, NULL, NULL, 30, 50000.00, 25000.00, 2, '2025-09-02 13:43:36', '2025-09-02 13:43:36'),
(10, '3ème Primaire A', 'primaire', NULL, NULL, NULL, NULL, 28, 50000.00, 25000.00, 2, '2025-09-02 13:43:36', '2025-09-02 13:43:36'),
(11, '4ème Primaire A', 'primaire', NULL, NULL, NULL, NULL, 32, 50000.00, 25000.00, 2, '2025-09-02 13:43:36', '2025-09-02 13:43:36'),
(12, '5ème Primaire A', 'primaire', NULL, NULL, NULL, NULL, 25, 50000.00, 25000.00, 2, '2025-09-02 13:43:36', '2025-09-02 13:43:36'),
(13, '6ème Primaire A', 'primaire', NULL, NULL, NULL, NULL, 27, 50000.00, 25000.00, 2, '2025-09-02 13:43:36', '2025-09-02 13:43:36'),
(14, '1ère Secondaire Secondaire A', 'primaire', NULL, NULL, NULL, NULL, 24, 50000.00, 25000.00, 2, '2025-09-02 13:43:36', '2025-09-02 13:43:36'),
(15, '1ère Primaire', 'primaire', NULL, 'Salle 201', 'Classe debutante', 1, 30, 0.00, 0.00, 3, '2025-09-02 17:18:37', NULL);

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

--
-- Déchargement des données de la table `criteres_admission`
--

INSERT INTO `criteres_admission` (`id`, `annee_scolaire_id`, `niveau`, `age_min`, `age_max`, `capacite_max`, `note_min`, `documents_requis`, `conditions_speciales`, `actif`, `created_at`, `updated_at`) VALUES
(1, 1, 'maternelle', 3, 6, 25, 0.00, 'Acte de naissance, Carnet de vaccination, Photo 4x4', 'Enfant propre et autonome', 1, '2025-08-14 13:07:53', NULL),
(2, 1, 'primaire', 6, 12, 35, 10.00, 'Acte de naissance, Certificat de fin de maternelle, Photo 4x4', 'Test d\'├®valuation obligatoire', 1, '2025-08-14 13:07:53', NULL),
(3, 1, 'secondaire', 12, 18, 40, 12.00, 'Acte de naissance, Certificat de fin de primaire, Photo 4x4', 'Entretien avec les parents', 1, '2025-08-14 13:07:53', NULL),
(4, 1, 'superieur', 18, 25, 30, 14.00, 'Acte de naissance, Dipl├┤me de fin de secondaire, Photo 4x4', 'Test d\'admission et entretien', 1, '2025-08-14 13:07:53', NULL);

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

--
-- Déchargement des données de la table `criteres_admission_classes`
--

INSERT INTO `criteres_admission_classes` (`id`, `annee_scolaire_id`, `classe_id`, `capacite_max`, `note_min`, `actif`, `created_at`, `updated_at`) VALUES
(1, 1, 1, 30, 10.00, 1, '2025-08-14 13:07:53', NULL),
(2, 1, 2, 30, 10.00, 1, '2025-08-14 13:07:53', NULL),
(3, 1, 3, 28, 10.00, 1, '2025-08-14 13:07:53', NULL),
(4, 1, 4, 32, 10.00, 1, '2025-08-14 13:07:53', NULL),
(5, 1, 5, 25, 10.00, 1, '2025-08-14 13:07:53', NULL),
(6, 1, 6, 27, 10.00, 1, '2025-08-14 13:07:53', NULL),
(7, 1, 7, 24, 10.00, 1, '2025-08-14 13:07:53', NULL);

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

--
-- Déchargement des données de la table `demandes_admission`
--

INSERT INTO `demandes_admission` (`id`, `numero_demande`, `annee_scolaire_id`, `classe_demandee_id`, `nom_eleve`, `prenom_eleve`, `date_naissance`, `lieu_naissance`, `sexe`, `adresse`, `telephone`, `email`, `nom_pere`, `nom_mere`, `profession_pere`, `profession_mere`, `telephone_parent`, `personne_contact`, `telephone_contact`, `relation_contact`, `ecole_precedente`, `classe_precedente`, `annee_precedente`, `moyenne_precedente`, `certificat_naissance`, `bulletin_precedent`, `certificat_medical`, `photo_identite`, `autres_documents`, `motif_demande`, `besoins_speciaux`, `allergies_medicales`, `status`, `priorite`, `date_entretien`, `notes_entretien`, `decision_motif`, `traite_par`, `date_traitement`, `frais_inscription`, `frais_scolarite`, `reduction_accordee`, `observations`, `created_at`, `updated_at`, `note_evaluation`, `commentaire_evaluation`, `recommandation`, `evalue_par`, `date_evaluation`, `verifie_par`, `date_verification`, `commentaire_documents`, `eleve_cree_id`, `date_inscription`, `commentaire_traitement`) VALUES
(1, 'ADM2025001', 1, 1, 'DEMANDE ghghghg', 'Test1', '2017-06-17', 'Kinshasa', 'M', '', '', '', 'DEMANDE Père', 'DEMANDE Mère', '', '', '+243 123 456 789', '', '', '', 'École Maternelle Saint-Pierre', '', '', NULL, 'fourni', '', '', '', '', 'Demande d&amp;#039;admission standard', '', '', 'inscrit', 'normale', NULL, NULL, NULL, 1, '2025-08-09 22:12:55', 0.00, 0.00, 0.00, '', '2025-08-08 16:11:20', '2025-09-02 20:44:52', NULL, NULL, 'accepter', 2, '2025-09-02 20:44:52', 1, '2025-08-09 17:28:46', 'sdsdsd', 16, '2025-09-02', NULL),
(2, 'ADM2025002', 1, 2, 'CANDIDAT', 'Marie', '2010-07-22', 'Lubumbashi', 'F', NULL, NULL, NULL, 'CANDIDAT Papa', 'CANDIDAT Maman', NULL, NULL, '+243 987 654 321', NULL, NULL, NULL, 'École Primaire Notre-Dame', NULL, NULL, NULL, '', '', '', '', NULL, 'Demande d\'admission standard', NULL, NULL, 'inscrit', 'normale', NULL, NULL, NULL, NULL, NULL, 0.00, 0.00, 0.00, NULL, '2025-08-08 16:11:20', '2025-09-02 20:44:52', NULL, NULL, 'accepter', 2, '2025-09-02 20:44:52', NULL, NULL, NULL, 15, '2025-09-02', NULL),
(3, 'ADM2025003', 1, 3, 'URGENT', 'Paul', '2011-01-10', 'Goma', 'M', NULL, NULL, NULL, 'URGENT Père', 'URGENT Mère', NULL, NULL, '+243 555 666 777', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '', '', '', '', NULL, 'Déménagement urgent de la famille', NULL, NULL, 'inscrit', 'urgente', NULL, NULL, NULL, 1, '2025-08-08 16:39:17', 0.00, 0.00, 0.00, NULL, '2025-08-08 16:11:20', '2025-09-02 20:30:04', 12.00, 'scdsdsd', 'accepter', 2, '2025-09-02 20:30:04', NULL, NULL, NULL, 17, '2025-09-02', NULL),
(7, 'ADM2025004', 2, 14, 'SDSDSD', 'DSDSDS', '2014-06-02', 'Goma', 'M', 'AV. ITEBERO N°100 Q/ MABANGA NORD C/ KARISIMBI\r\nAV. ITEBERO N°100 Q/ MABANGA NOR', '0975579097', 'thecarinsiwa@gmail.com', 'Carin Mumbere Siwa', 'Carin Mumbere Siwa', 'Commerçant', 'Enseignante', '0975579097', 'Carin Mumbere Siwa', '0975579097', 'DSDS', 'Carin Mumbere Siwa', 'DSDSD', 'SDSDSDSDSD', 12.00, 'non_fourni', '', '', 'non_fourni', '', '', 'SDSDSDSD', 'DSDSDSD', 'inscrit', 'normale', NULL, NULL, NULL, 2, '2025-09-02 16:36:47', 5000.00, 5000.00, 0.00, 'DSDSDSD', '2025-09-02 16:31:03', '2025-09-02 20:44:52', NULL, NULL, 'accepter', 2, '2025-09-02 20:44:52', NULL, NULL, NULL, 14, '2025-09-02', NULL),
(8, 'TEST001', 1, 1, 'CANDIDAT', 'Test Accepté', '2010-01-01', NULL, 'M', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'non_fourni', 'non_fourni', 'non_fourni', 'non_fourni', NULL, NULL, NULL, NULL, 'inscrit', 'normale', NULL, NULL, NULL, NULL, NULL, 0.00, 0.00, 0.00, NULL, '2025-09-02 19:21:13', '2025-09-02 21:02:39', NULL, NULL, 'accepter', 2, '2025-09-02 20:44:52', NULL, NULL, NULL, 20, '2025-09-02', NULL),
(9, 'TEST002', 1, 2, 'CANDIDATE', 'Test Acceptée', '2011-02-02', NULL, 'F', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'non_fourni', 'non_fourni', 'non_fourni', 'non_fourni', NULL, NULL, NULL, NULL, 'inscrit', 'normale', NULL, NULL, NULL, NULL, NULL, 0.00, 0.00, 0.00, NULL, '2025-09-02 19:21:19', '2025-09-02 21:02:34', NULL, NULL, 'accepter', 2, '2025-09-02 20:44:52', NULL, NULL, NULL, 19, '2025-09-02', NULL),
(10, 'ADM2025007', 2, 8, 'fdvcvcv', 'vcvcvcv', '2019-06-03', 'vvcvcv', 'M', 'AV. ITEBERO N°100 Q/ MABANGA NORD C/ KARISIMBI\r\nAV. ITEBERO N°100 Q/ MABANGA NOR', '0975579097', 'thecarinsiwa@gmail.com', 'Carin Mumbere Siwa', 'Carin Mumbere Siwa', 'Commerçant', 'Enseignante', '0975579097', 'Carin Mumbere Siwa', '0975579097', 'DSDS', 'Carin Mumbere Siwa', 'DSDSDdddcx', 'SDSDSDSDSDcccdgttt', 17.00, '', 'non_fourni', '', '', 'dfdfdf', 'fdfdf', 'dfdf', 'fdfdf', 'inscrit', 'urgente', '2025-10-28 12:30:00', NULL, NULL, 2, '2025-09-02 20:43:04', 0.00, 0.00, 0.00, 'fdfdf', '2025-09-02 20:37:03', '2025-09-02 21:02:31', 15.00, 'DSDSD', 'accepter', 2, '2025-09-02 20:44:17', NULL, NULL, NULL, 18, '2025-09-02', '');

-- --------------------------------------------------------

--
-- Structure de la table `depenses`
--

CREATE TABLE `depenses` (
  `id` int NOT NULL,
  `libelle` varchar(255) NOT NULL,
  `description` text,
  `montant` decimal(10,2) NOT NULL,
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

--
-- Déchargement des données de la table `depenses`
--

INSERT INTO `depenses` (`id`, `libelle`, `description`, `montant`, `type_depense`, `date_depense`, `fournisseur`, `numero_facture`, `mode_paiement`, `statut`, `annee_scolaire_id`, `user_id`, `created_at`, `updated_at`) VALUES
(1, 'Achat de matériel de bureau', 'dfdfdfdfdfdfd', 120000.00, 'autre', '2025-08-09', 'Yhug Hung Chine', '00SDEEEE', 'especes', 'en_attente', 1, 1, '2025-08-09 17:33:09', NULL);

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
(14, 12, 'certificat_medical', 'jhjhjh', NULL, NULL, NULL, NULL, 'nvbvbvbvbv', '2025-08-08 19:35:36', 1, '2025-08-14 11:16:33', 1, 'en_attente', 'kjjhjhjhjhhvgfgfgf', 0, NULL, NULL, NULL, '2025-08-08 17:35:36', '2025-08-14 09:16:33'),
(15, 13, 'certificat_naissance', 'jhjhjh', NULL, NULL, NULL, NULL, 'ezee', '2025-08-26 08:30:39', 1, '2025-08-26 08:58:54', 1, 'verifie', 'ez', 0, NULL, NULL, NULL, '2025-08-26 06:30:39', '2025-08-26 06:58:54');

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
  `status` enum('actif','transfere','abandonne','diplome') DEFAULT 'actif',
  `date_inscription` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  `email_parent` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Informations des ├®l├¿ves avec relations aux classes et ann├®es scolaires';

--
-- Déchargement des données de la table `eleves`
--

INSERT INTO `eleves` (`id`, `numero_eleve`, `numero_matricule`, `parent_id`, `nom`, `prenom`, `sexe`, `date_naissance`, `lieu_naissance`, `adresse`, `telephone`, `email`, `nom_pere`, `nom_mere`, `profession_pere`, `profession_mere`, `telephone_parent`, `personne_contact`, `telephone_contact`, `relation_contact`, `classe_id`, `annee_scolaire_id`, `photo`, `status`, `date_inscription`, `created_at`, `updated_at`, `email_parent`) VALUES
(1, '', 'MAT2024001', 1, 'MUKENDI', 'Jean', 'M', '2010-05-15', 'Kinshasa', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'actif', '2025-08-08 14:36:40', '2025-08-08 16:36:40', '2025-08-08 18:59:25', NULL),
(2, '', 'MAT2024002', 2, 'KABILA', 'Marie', 'F', '2011-03-22', 'Lubumbashi', '', '', '', 'DSD', 'DSD', 'DSD', 'DSD', '45555555', NULL, NULL, NULL, NULL, NULL, 'uploads/photos/eleve_2_1755540852.jpg', 'actif', '2025-08-08 14:36:40', '2025-08-08 16:36:40', '2025-08-18 18:14:12', NULL),
(3, '', 'MAT2024003', 3, 'TSHISEKEDI', 'Paul', 'M', '2010-08-10', 'Mbuji-Mayi', '', '', '', '', '', '', '', '', NULL, NULL, NULL, NULL, NULL, 'uploads/photos/eleve_3_1755163116.png', 'diplome', '2025-08-08 14:36:40', '2025-08-08 16:36:40', '2025-08-14 09:18:36', NULL),
(4, '', 'MAT2024004', 4, 'MBUYI', 'Grace', 'F', '2011-01-18', 'Kananga', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'actif', '2025-08-08 14:36:40', '2025-08-08 16:36:40', '2025-09-02 13:48:00', NULL),
(5, '', 'MAT2024005', 5, 'KASONGO', 'David', 'M', '2010-12-05', 'Kisangani', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'actif', '2025-08-08 14:36:40', '2025-08-08 16:36:40', '2025-08-08 18:59:25', NULL),
(6, '', 'MAT2024006', NULL, 'NGOZI', 'Sarah', 'F', '2011-07-30', 'Goma', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'actif', '2025-08-08 14:36:40', '2025-08-08 16:36:40', NULL, NULL),
(7, '', 'MAT2024007', NULL, 'LUMUMBA', 'Patrick', 'M', '2010-09-12', 'Kinshasa', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'actif', '2025-08-08 14:36:40', '2025-08-08 16:36:40', NULL, NULL),
(8, '', 'MAT2024008', NULL, 'KALONJI', 'Esther', 'F', '2011-02-28', 'Mbuji-Mayi', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'actif', '2025-08-08 14:36:40', '2025-08-08 16:36:40', NULL, NULL),
(9, '', 'MAT2024009', NULL, 'MOBUTU', 'Joseph', 'M', '2010-11-03', 'Gbadolite', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'actif', '2025-08-08 14:36:40', '2025-08-08 16:36:40', NULL, NULL),
(10, '', 'MAT2024010', NULL, 'KIMBANGU', 'Ruth', 'F', '2011-06-14', 'Nkamba', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'actif', '2025-08-08 14:36:40', '2025-08-08 16:36:40', NULL, NULL),
(12, '', 'AUTO20250808170719', NULL, 'BARAKA BIGEGA', 'ESPOIR', 'M', '2011-06-15', 'Goma', '123 Avenue de la Paix, Kinshasa', '+243 123 456 789', 'bigega@gmail.com', 'BIGEGA', 'BARAKA', 'Ingénieur', 'Enseignante', '+243 987 654 321', 'AUTOTEST Contact', '+243 111 222 333', NULL, NULL, NULL, 'uploads/photos/eleve_12_1756191509.jpg', 'actif', '2025-08-08 16:07:19', '2025-08-08 18:07:19', '2025-08-26 06:58:29', NULL),
(13, '20250001', 'STU20253643', NULL, 'KAMBALE MBOKANI', 'ISAAC', 'M', '1998-02-26', 'Goma', 'AV. ITEBERO N°100 Q/ MABANGA NORD C/ KARISIMBI\r\nAV. ITEBERO N°100 Q/ MABANGA NOR', '+243975579097', 'thecarinsiwa@gmail.com', 'Carin Mumbere Siwa', 'Carin Mumbere Siwa', 'Commerçant', 'Enseignante', '+243975579097', '+243975579097', '+243975579097', NULL, NULL, NULL, '68ad5330239c5.jpg', 'actif', '2025-08-26 06:24:48', '2025-08-26 08:24:48', '2025-08-26 06:28:56', NULL),
(14, '20250002', 'MAT2025001', NULL, 'SDSDSD', 'DSDSDS', 'M', '2014-06-02', 'Goma', 'AV. ITEBERO N°100 Q/ MABANGA NORD C/ KARISIMBI\r\nAV. ITEBERO N°100 Q/ MABANGA NOR', '0975579097', 'thecarinsiwa@gmail.com', 'Carin Mumbere Siwa', 'Carin Mumbere Siwa', 'Commerçant', 'Enseignante', '0975579097', 'Carin Mumbere Siwa', '0975579097', 'DSDS', 14, 2, NULL, 'actif', '2025-09-01 22:00:00', '2025-09-02 20:46:13', NULL, NULL),
(15, '20250003', 'MAT2025002', NULL, 'CANDIDAT', 'Marie', 'F', '2010-07-22', 'Lubumbashi', NULL, NULL, NULL, 'CANDIDAT Papa', 'CANDIDAT Maman', NULL, NULL, '+243 987 654 321', NULL, NULL, NULL, 3, 1, NULL, 'actif', '2025-09-01 22:00:00', '2025-09-02 20:49:11', NULL, NULL),
(16, '20250004', 'MAT2025003', NULL, 'DEMANDE ghghghg', 'Test1', 'M', '2017-06-17', 'Kinshasa', '', '', '', 'DEMANDE Père', 'DEMANDE Mère', '', '', '+243 123 456 789', '', '', '', 1, 1, NULL, 'actif', '2025-09-01 22:00:00', '2025-09-02 20:50:05', NULL, NULL),
(17, '20250005', 'MAT2025004', NULL, 'URGENT', 'Paul', 'M', '2011-01-10', 'Goma', NULL, NULL, NULL, 'URGENT Père', 'URGENT Mère', NULL, NULL, '+243 555 666 777', NULL, NULL, NULL, 3, 1, NULL, 'actif', '2025-09-01 22:00:00', '2025-09-02 20:50:09', NULL, NULL),
(18, '20250006', 'MAT2025005', NULL, 'fdvcvcv', 'vcvcvcv', 'M', '2019-06-03', 'vvcvcv', 'AV. ITEBERO N°100 Q/ MABANGA NORD C/ KARISIMBI\r\nAV. ITEBERO N°100 Q/ MABANGA NOR', '0975579097', 'thecarinsiwa@gmail.com', 'Carin Mumbere Siwa', 'Carin Mumbere Siwa', 'Commerçant', 'Enseignante', '0975579097', 'Carin Mumbere Siwa', '0975579097', 'DSDS', 8, 2, NULL, 'actif', '2025-09-01 22:00:00', '2025-09-02 23:02:31', NULL, NULL),
(19, '20250007', 'MAT2025006', NULL, 'CANDIDATE', 'Test Acceptée', 'F', '2011-02-02', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 2, 1, NULL, 'actif', '2025-09-01 22:00:00', '2025-09-02 23:02:34', NULL, NULL),
(20, '20250008', 'MAT2025007', NULL, 'CANDIDAT', 'Test Accepté', 'M', '2010-01-01', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, 1, NULL, 'actif', '2025-09-01 22:00:00', '2025-09-02 23:02:39', NULL, NULL);

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

--
-- Déchargement des données de la table `emplois_temps`
--

INSERT INTO `emplois_temps` (`id`, `classe_id`, `matiere_id`, `enseignant_id`, `jour_semaine`, `heure_debut`, `heure_fin`, `salle`, `annee_scolaire_id`, `created_at`) VALUES
(1, 1, 1, 1, 'mardi', '09:00:00', '15:00:00', 'Salle 201', 1, '2025-08-08 23:56:40');

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

--
-- Déchargement des données de la table `emploi_temps`
--

INSERT INTO `emploi_temps` (`id`, `classe_id`, `matiere_id`, `enseignant_id`, `jour_semaine`, `heure_debut`, `heure_fin`, `salle`, `recurrence`, `date_debut`, `date_fin`, `status`, `notes`, `annee_scolaire_id`, `created_at`, `updated_at`) VALUES
(3, 7, 1, 1, 'Lundi', '08:00:00', '09:00:00', 'Salle 005', 'unique', '2025-08-09', '2025-08-29', 'actif', NULL, 1, '2025-08-08 23:45:14', NULL),
(46, 1, 1, 1, 'Lundi', '08:00:00', '09:00:00', 'Salle 1', 'hebdomadaire', NULL, NULL, 'actif', NULL, 1, '2025-08-14 09:40:02', NULL),
(47, 1, 1, 1, 'Lundi', '09:00:00', '10:00:00', 'Salle 2', 'hebdomadaire', NULL, NULL, 'actif', NULL, 1, '2025-08-14 09:40:02', NULL),
(48, 1, 1, 1, 'Lundi', '10:00:00', '11:00:00', 'Salle 3', 'hebdomadaire', NULL, NULL, 'actif', NULL, 1, '2025-08-14 09:40:02', NULL),
(49, 1, 1, 1, 'Lundi', '11:00:00', '12:00:00', 'Salle 4', 'hebdomadaire', NULL, NULL, 'actif', NULL, 1, '2025-08-14 09:40:02', NULL),
(50, 1, 1, 1, 'Lundi', '13:00:00', '14:00:00', 'Salle 5', 'hebdomadaire', NULL, NULL, 'actif', NULL, 1, '2025-08-14 09:40:02', NULL),
(51, 1, 1, 1, 'Lundi', '14:00:00', '15:00:00', 'Salle 6', 'hebdomadaire', NULL, NULL, 'actif', NULL, 1, '2025-08-14 09:40:02', NULL),
(52, 1, 1, 1, 'Lundi', '15:00:00', '16:00:00', 'Salle 7', 'hebdomadaire', NULL, NULL, 'actif', NULL, 1, '2025-08-14 09:40:02', NULL),
(53, 1, 1, 1, 'Mardi', '08:00:00', '09:00:00', 'Salle 1', 'hebdomadaire', NULL, NULL, 'actif', NULL, 1, '2025-08-14 09:40:02', NULL),
(54, 1, 1, 1, 'Mardi', '09:00:00', '10:00:00', 'Salle 2', 'hebdomadaire', NULL, NULL, 'actif', NULL, 1, '2025-08-14 09:40:02', NULL),
(55, 1, 1, 1, 'Mardi', '10:00:00', '11:00:00', 'Salle 3', 'hebdomadaire', NULL, NULL, 'actif', NULL, 1, '2025-08-14 09:40:02', NULL),
(56, 1, 1, 1, 'Mardi', '11:00:00', '12:00:00', 'Salle 4', 'hebdomadaire', NULL, NULL, 'actif', NULL, 1, '2025-08-14 09:40:02', NULL),
(57, 1, 1, 1, 'Mardi', '13:00:00', '14:00:00', 'Salle 5', 'hebdomadaire', NULL, NULL, 'actif', NULL, 1, '2025-08-14 09:40:02', NULL),
(58, 1, 1, 1, 'Mardi', '14:00:00', '15:00:00', 'Salle 6', 'hebdomadaire', NULL, NULL, 'actif', NULL, 1, '2025-08-14 09:40:02', NULL),
(59, 1, 1, 1, 'Mardi', '15:00:00', '16:00:00', 'Salle 7', 'hebdomadaire', NULL, NULL, 'actif', NULL, 1, '2025-08-14 09:40:02', NULL),
(60, 1, 1, 1, 'Mercredi', '08:00:00', '09:00:00', 'Salle 1', 'hebdomadaire', NULL, NULL, 'actif', NULL, 1, '2025-08-14 09:40:02', NULL),
(61, 1, 1, 1, 'Mercredi', '09:00:00', '10:00:00', 'Salle 2', 'hebdomadaire', NULL, NULL, 'actif', NULL, 1, '2025-08-14 09:40:02', NULL),
(62, 1, 1, 1, 'Mercredi', '10:00:00', '11:00:00', 'Salle 3', 'hebdomadaire', NULL, NULL, 'actif', NULL, 1, '2025-08-14 09:40:02', NULL),
(63, 1, 1, 1, 'Mercredi', '11:00:00', '12:00:00', 'Salle 4', 'hebdomadaire', NULL, NULL, 'actif', NULL, 1, '2025-08-14 09:40:02', NULL),
(64, 1, 1, 1, 'Mercredi', '13:00:00', '14:00:00', 'Salle 5', 'hebdomadaire', NULL, NULL, 'actif', NULL, 1, '2025-08-14 09:40:02', NULL),
(65, 1, 1, 1, 'Mercredi', '14:00:00', '15:00:00', 'Salle 6', 'hebdomadaire', NULL, NULL, 'actif', NULL, 1, '2025-08-14 09:40:02', NULL),
(66, 1, 1, 1, 'Mercredi', '15:00:00', '16:00:00', 'Salle 7', 'hebdomadaire', NULL, NULL, 'actif', NULL, 1, '2025-08-14 09:40:02', NULL),
(67, 1, 1, 1, 'Jeudi', '08:00:00', '09:00:00', 'Salle 1', 'hebdomadaire', NULL, NULL, 'actif', NULL, 1, '2025-08-14 09:40:02', NULL),
(68, 1, 1, 1, 'Jeudi', '09:00:00', '10:00:00', 'Salle 2', 'hebdomadaire', NULL, NULL, 'actif', NULL, 1, '2025-08-14 09:40:02', NULL),
(69, 1, 1, 1, 'Jeudi', '10:00:00', '11:00:00', 'Salle 3', 'hebdomadaire', NULL, NULL, 'actif', NULL, 1, '2025-08-14 09:40:02', NULL),
(70, 1, 1, 1, 'Jeudi', '11:00:00', '12:00:00', 'Salle 4', 'hebdomadaire', NULL, NULL, 'actif', NULL, 1, '2025-08-14 09:40:02', NULL),
(71, 1, 1, 1, 'Jeudi', '13:00:00', '14:00:00', 'Salle 5', 'hebdomadaire', NULL, NULL, 'actif', NULL, 1, '2025-08-14 09:40:02', NULL),
(72, 1, 1, 1, 'Jeudi', '14:00:00', '15:00:00', 'Salle 6', 'hebdomadaire', NULL, NULL, 'actif', NULL, 1, '2025-08-14 09:40:02', NULL),
(73, 1, 1, 1, 'Jeudi', '15:00:00', '16:00:00', 'Salle 7', 'hebdomadaire', NULL, NULL, 'actif', NULL, 1, '2025-08-14 09:40:02', NULL),
(74, 1, 1, 1, 'Vendredi', '08:00:00', '09:00:00', 'Salle 1', 'hebdomadaire', NULL, NULL, 'actif', NULL, 1, '2025-08-14 09:40:02', NULL),
(75, 1, 1, 1, 'Vendredi', '09:00:00', '10:00:00', 'Salle 2', 'hebdomadaire', NULL, NULL, 'actif', NULL, 1, '2025-08-14 09:40:02', NULL),
(76, 1, 1, 1, 'Vendredi', '10:00:00', '11:00:00', 'Salle 3', 'hebdomadaire', NULL, NULL, 'actif', NULL, 1, '2025-08-14 09:40:02', NULL),
(77, 1, 1, 1, 'Vendredi', '11:00:00', '12:00:00', 'Salle 4', 'hebdomadaire', NULL, NULL, 'actif', NULL, 1, '2025-08-14 09:40:02', NULL),
(78, 1, 1, 1, 'Vendredi', '13:00:00', '14:00:00', 'Salle 5', 'hebdomadaire', NULL, NULL, 'actif', NULL, 1, '2025-08-14 09:40:02', NULL),
(79, 1, 1, 1, 'Vendredi', '14:00:00', '15:00:00', 'Salle 6', 'hebdomadaire', NULL, NULL, 'actif', NULL, 1, '2025-08-14 09:40:02', NULL),
(80, 1, 1, 1, 'Vendredi', '15:00:00', '16:00:00', 'Salle 7', 'hebdomadaire', NULL, NULL, 'actif', NULL, 1, '2025-08-14 09:40:02', NULL),
(81, 1, 1, 1, 'Samedi', '08:00:00', '09:00:00', 'Salle 1', 'hebdomadaire', NULL, NULL, 'actif', NULL, 1, '2025-08-14 09:40:02', NULL),
(82, 1, 1, 1, 'Samedi', '09:00:00', '10:00:00', 'Salle 2', 'hebdomadaire', NULL, NULL, 'actif', NULL, 1, '2025-08-14 09:40:02', NULL),
(83, 1, 1, 1, 'Samedi', '10:00:00', '11:00:00', 'Salle 3', 'hebdomadaire', NULL, NULL, 'actif', NULL, 1, '2025-08-14 09:40:02', NULL),
(84, 1, 1, 1, 'Samedi', '11:00:00', '12:00:00', 'Salle 4', 'hebdomadaire', NULL, NULL, 'actif', NULL, 1, '2025-08-14 09:40:02', NULL),
(85, 1, 1, 1, 'Samedi', '13:00:00', '14:00:00', 'Salle 5', 'hebdomadaire', NULL, NULL, 'actif', NULL, 1, '2025-08-14 09:40:02', NULL),
(86, 1, 1, 1, 'Samedi', '14:00:00', '15:00:00', 'Salle 6', 'hebdomadaire', NULL, NULL, 'actif', NULL, 1, '2025-08-14 09:40:02', NULL),
(87, 1, 1, 1, 'Samedi', '15:00:00', '16:00:00', 'Salle 7', 'hebdomadaire', NULL, NULL, 'actif', NULL, 1, '2025-08-14 09:40:02', NULL);

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

--
-- Déchargement des données de la table `emprunts_livres`
--

INSERT INTO `emprunts_livres` (`id`, `livre_id`, `emprunteur_type`, `emprunteur_id`, `date_emprunt`, `date_retour_prevue`, `date_retour_effective`, `duree_jours`, `status`, `notes_emprunt`, `notes_retour`, `penalite`, `traite_par`, `rendu_par`, `created_at`, `updated_at`) VALUES
(1, 1, 'personnel', 12, '2025-08-18', '2025-08-23', NULL, 5, 'en_cours', 'nbnbn', NULL, 0.00, 1, NULL, '2025-08-18 18:59:04', NULL),
(2, 1, 'personnel', 2122, '2025-08-18', '2025-08-28', NULL, 10, 'en_cours', 'GHGHGHG', NULL, 0.00, 1, NULL, '2025-08-18 19:03:18', NULL),
(3, 1, 'eleve', 56565, '2025-08-18', '2025-09-01', NULL, 14, 'en_cours', 'GHHGsdsdsd', NULL, 0.00, 1, NULL, '2025-08-18 19:05:47', NULL),
(4, 1, 'eleve', 45455, '2025-08-18', '2025-09-01', NULL, 14, 'en_cours', 'GHGHGHGHBVYTT', NULL, 0.00, 1, NULL, '2025-08-18 19:07:35', NULL),
(5, 1, 'personnel', 565656, '2025-08-18', '2025-09-08', NULL, 21, 'en_cours', 'JJHJHJ', NULL, 0.00, 1, NULL, '2025-08-18 19:09:51', NULL);

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

--
-- Déchargement des données de la table `etapes_admission`
--

INSERT INTO `etapes_admission` (`id`, `nom`, `description`, `ordre`, `status`, `created_at`) VALUES
(1, 'Demande d\'admission', 'Enregistrement des informations de base et génération du numéro de dossier', 1, 'active', '2025-09-02 20:06:00'),
(2, 'Vérification des documents', 'Contrôle et validation des pièces jointes', 2, 'active', '2025-09-02 20:06:00'),
(3, 'Évaluation', 'Tests, entretiens et examens d\'admission', 3, 'active', '2025-09-02 20:06:00'),
(4, 'Décision d\'admission', 'Acceptation, refus ou acceptation conditionnelle', 4, 'active', '2025-09-02 20:06:00'),
(5, 'Inscription', 'Finalisation de l\'inscription et paiement des frais', 5, 'active', '2025-09-02 20:06:00'),
(6, 'Intégration', 'Accueil et intégration dans la classe', 6, 'active', '2025-09-02 20:06:00'),
(7, 'Demande d\'admission', 'Enregistrement des informations de base et génération du numéro de dossier', 1, 'active', '2025-09-02 20:08:53'),
(8, 'Vérification des documents', 'Contrôle et validation des pièces jointes', 2, 'active', '2025-09-02 20:08:53'),
(9, 'Évaluation', 'Tests, entretiens et examens d\'admission', 3, 'active', '2025-09-02 20:08:53'),
(10, 'Décision d\'admission', 'Acceptation, refus ou acceptation conditionnelle', 4, 'active', '2025-09-02 20:08:53'),
(11, 'Inscription', 'Finalisation de l\'inscription et paiement des frais', 5, 'active', '2025-09-02 20:08:53'),
(12, 'Intégration', 'Accueil et intégration dans la classe', 6, 'active', '2025-09-02 20:08:53'),
(13, 'Demande d\'admission', 'Enregistrement des informations de base et génération du numéro de dossier', 1, 'active', '2025-09-02 20:10:15'),
(14, 'Vérification des documents', 'Contrôle et validation des pièces jointes', 2, 'active', '2025-09-02 20:10:15'),
(15, 'Évaluation', 'Tests, entretiens et examens d\'admission', 3, 'active', '2025-09-02 20:10:15'),
(16, 'Décision d\'admission', 'Acceptation, refus ou acceptation conditionnelle', 4, 'active', '2025-09-02 20:10:15'),
(17, 'Inscription', 'Finalisation de l\'inscription et paiement des frais', 5, 'active', '2025-09-02 20:10:15'),
(18, 'Intégration', 'Accueil et intégration dans la classe', 6, 'active', '2025-09-02 20:10:15'),
(19, 'Demande d\'admission', 'Enregistrement des informations de base et génération du numéro de dossier', 1, 'active', '2025-09-02 20:24:19'),
(20, 'Vérification des documents', 'Contrôle et validation des pièces jointes', 2, 'active', '2025-09-02 20:24:19'),
(21, 'Évaluation', 'Tests, entretiens et examens d\'admission', 3, 'active', '2025-09-02 20:24:19'),
(22, 'Décision d\'admission', 'Acceptation, refus ou acceptation conditionnelle', 4, 'active', '2025-09-02 20:24:19'),
(23, 'Inscription', 'Finalisation de l\'inscription et paiement des frais', 5, 'active', '2025-09-02 20:24:19'),
(24, 'Intégration', 'Accueil et intégration dans la classe', 6, 'active', '2025-09-02 20:24:19'),
(25, 'Demande d\'admission', 'Enregistrement des informations de base et génération du numéro de dossier', 1, 'active', '2025-09-02 20:25:47'),
(26, 'Vérification des documents', 'Contrôle et validation des pièces jointes', 2, 'active', '2025-09-02 20:25:47'),
(27, 'Évaluation', 'Tests, entretiens et examens d\'admission', 3, 'active', '2025-09-02 20:25:47'),
(28, 'Décision d\'admission', 'Acceptation, refus ou acceptation conditionnelle', 4, 'active', '2025-09-02 20:25:47'),
(29, 'Inscription', 'Finalisation de l\'inscription et paiement des frais', 5, 'active', '2025-09-02 20:25:47'),
(30, 'Intégration', 'Accueil et intégration dans la classe', 6, 'active', '2025-09-02 20:25:47');

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

--
-- Déchargement des données de la table `evaluations`
--

INSERT INTO `evaluations` (`id`, `nom`, `description`, `type`, `classe_id`, `matiere_id`, `type_evaluation`, `enseignant_id`, `date_evaluation`, `heure_debut`, `heure_fin`, `duree_minutes`, `note_max`, `bareme`, `consignes`, `status`, `coefficient`, `periode`, `annee_scolaire_id`, `user_id`, `created_at`, `updated_at`) VALUES
(2, 'Interrogation - Tables de multiplication', 'Évaluation des tables de multiplication de 1 à 10', 'interrogation', 1, 1, 'interrogation', 1, '2024-10-15', '08:00:00', '09:00:00', 60, 20.00, '20 questions à 1 point chacune', 'Calculer les multiplications sans calculatrice. Écrire lisiblement.gfgfgfgfgf', 'terminee', 1.00, '1er_trimestre', 1, 1, '2025-08-09 00:25:04', '2025-08-09 00:42:37'),
(3, 'Devoir - Conjugaison', 'Évaluation sur la conjugaison des verbes du 1er groupe', 'devoir', 2, 1, 'interrogation', 1, '2024-10-20', '10:00:00', '11:30:00', 90, 20.00, 'Exercice 1: 8 pts, Exercice 2: 7 pts, Exercice 3: 5 pts', 'Conjuguer les verbes aux temps demandés. Attention à l\'orthographe.', 'terminee', 2.00, '1er_trimestre', 1, 1, '2025-08-09 00:25:04', NULL),
(4, 'Examen - Sciences naturelles', 'Examen sur le corps humain et la digestion', 'examen', 3, 1, 'interrogation', 1, '2024-11-05', '14:00:00', '16:00:00', 120, 20.00, 'QCM: 10 pts, Questions ouvertes: 10 pts', 'Répondre à toutes les questions. Justifier les réponses ouvertes.', 'programmee', 3.00, '1er_trimestre', 1, 1, '2025-08-09 00:25:04', NULL);

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

--
-- Déchargement des données de la table `frais_eleves`
--

INSERT INTO `frais_eleves` (`id`, `eleve_id`, `annee_scolaire_id`, `frais_inscription`, `frais_scolarite`, `reduction_accordee`, `montant_total`, `montant_paye`, `solde`, `status`, `created_at`, `updated_at`) VALUES
(1, 14, 2, 5000.00, 5000.00, 0.00, 10000.00, 0.00, 0.00, 'impaye', '2025-09-02 18:46:13', NULL),
(2, 15, 1, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 'impaye', '2025-09-02 18:49:11', NULL),
(3, 16, 1, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 'impaye', '2025-09-02 18:50:05', NULL),
(4, 17, 1, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 'impaye', '2025-09-02 18:50:09', NULL),
(5, 18, 2, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 'impaye', '2025-09-02 21:02:31', NULL),
(6, 19, 1, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 'impaye', '2025-09-02 21:02:34', NULL),
(7, 20, 1, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 'impaye', '2025-09-02 21:02:39', NULL);

-- --------------------------------------------------------

--
-- Structure de la table `frais_scolaires`
--

CREATE TABLE `frais_scolaires` (
  `id` int NOT NULL,
  `classe_id` int NOT NULL,
  `type_frais` enum('inscription','mensualite','examen','uniforme','transport','cantine','autre') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `libelle` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `montant` decimal(10,2) NOT NULL,
  `obligatoire` tinyint(1) DEFAULT '1',
  `date_echeance` date DEFAULT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `annee_scolaire_id` int NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `frais_scolaires`
--

INSERT INTO `frais_scolaires` (`id`, `classe_id`, `type_frais`, `libelle`, `montant`, `obligatoire`, `date_echeance`, `description`, `annee_scolaire_id`, `created_at`, `updated_at`) VALUES
(1, 1, 'inscription', 'Frais d\'inscription 1ère Primaire A', 50000.00, 1, NULL, 'Frais d\'inscription pour l\'année scolaire', 1, '2025-08-09 02:55:41', NULL),
(2, 1, 'mensualite', 'Mensualité 1ère Primaire A', 25000.00, 1, NULL, 'Frais de scolarité mensuelle', 1, '2025-08-09 02:55:41', NULL),
(3, 2, 'inscription', 'Frais d\'inscription 1ère Primaire B', 50000.00, 1, NULL, 'Frais d\'inscription pour l\'année scolaire', 1, '2025-08-09 02:55:41', NULL),
(4, 2, 'mensualite', 'Mensualité 1ère Primaire B', 25000.00, 1, NULL, 'Frais de scolarité mensuelle', 1, '2025-08-09 02:55:41', NULL),
(5, 3, 'inscription', 'Frais d\'inscription 2ème Primaire A', 50000.00, 1, NULL, 'Frais d\'inscription pour l\'année scolaire', 1, '2025-08-09 02:55:41', NULL),
(6, 3, 'mensualite', 'Mensualité 2ème Primaire A', 25000.00, 1, NULL, 'Frais de scolarité mensuelle', 1, '2025-08-09 02:55:41', NULL);

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

--
-- Déchargement des données de la table `incidents`
--

INSERT INTO `incidents` (`id`, `eleve_id`, `classe_id`, `rapporte_par`, `date_incident`, `lieu`, `description`, `temoins`, `gravite`, `status`, `notes_internes`, `created_at`, `updated_at`) VALUES
(1, 8, 1, 1, '2025-08-09 21:00:00', 'oioioioioi', 'yuyuyuyuy', 'jhjhjhjhjhj', 'grave', 'nouveau', NULL, '2025-08-09 20:01:14', NULL);

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
(11, 12, 1, 1, '2025-08-08 16:07:19', 50000.00, 'inscrit', '2025-08-08 16:07:19', NULL),
(12, 13, 7, 1, '2025-08-25 22:00:00', 0.00, 'inscrit', '2025-08-26 06:24:48', NULL),
(13, 4, 11, 2, '2025-09-02 13:48:00', 0.00, 'inscrit', '2025-09-02 13:48:00', NULL);

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

--
-- Déchargement des données de la table `livres`
--

INSERT INTO `livres` (`id`, `titre`, `auteur`, `isbn`, `editeur`, `annee_publication`, `categorie_id`, `categorie`, `nombre_pages`, `langue`, `resume`, `nombre_exemplaires`, `exemplaires_disponibles`, `nombre_disponibles`, `emplacement`, `status`, `created_at`, `prix_achat`, `date_acquisition`, `etat`, `cote`, `notes`) VALUES
(1, 'Brevet du Professionnalisme', 'qs', 'dfdfd', 'fdfdf', '2002', 2, NULL, 12, 'Anglais', 'SQS', 16, 16, 16, 'S', 'disponible', '2025-08-18 18:33:29', 222200.00, '2025-08-06', 'bon', 'fdfdf', 'SDSD');

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

--
-- Déchargement des données de la table `messages`
--

INSERT INTO `messages` (`id`, `expediteur_id`, `destinataire_id`, `destinataire_type`, `destinataires_custom`, `sujet`, `contenu`, `type_message`, `priorite`, `date_envoi`, `programme`, `date_programmee`, `status`, `lu_par`, `accuse_reception`, `fichiers_joints`, `created_at`, `updated_at`) VALUES
(1, 1, 0, 'all_students', '', 'Convocation - {eleve_nom}', 'Chers(es) parents,\r\nNous vous prions de bien vouloir vous présenter à l\'établissement le 12/08/2025 à 20:00 pour un entretien concernant vos enfants.\r\nMotif: \r\n\r\nCordialement,\r\nL\'administration', 'urgent', 'haute', '2025-08-09 21:48:15', 0, NULL, 'envoye', NULL, 1, NULL, '2025-08-09 20:48:15', NULL);

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

--
-- Déchargement des données de la table `notes`
--

INSERT INTO `notes` (`id`, `evaluation_id`, `eleve_id`, `note`, `observation`, `created_at`) VALUES
(1, 2, 1, 11.00, '', '2025-08-09 00:25:04'),
(2, 2, 8, 10.00, 'Excellent', '2025-08-09 00:25:04'),
(3, 2, 12, 15.00, 'Bien présenté', '2025-08-09 00:25:04'),
(4, 3, 2, 15.00, '', '2025-08-09 00:25:04'),
(5, 3, 9, 10.00, 'Très bon travail', '2025-08-09 00:25:04');

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

--
-- Déchargement des données de la table `notifications_parents`
--

INSERT INTO `notifications_parents` (`id`, `absence_id`, `parent_id`, `type_notification`, `message`, `status`, `sent_at`, `error_message`, `created_by`, `created_at`, `updated_at`) VALUES
(2, 4, 1, 'email', 'Notification pour MUKENDI Jean - absence', 'sent', '2025-08-08 19:09:54', NULL, 1, '2025-08-08 19:09:54', NULL);

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

--
-- Déchargement des données de la table `notifications_recouvrement`
--

INSERT INTO `notifications_recouvrement` (`id`, `type_notification`, `sujet`, `message`, `campagne_id`, `annee_scolaire_id`, `status`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 'sms', 'Rappel paiement', 'Bonjour {nom_parent}, votre enfant {nom_eleve} a une dette de {montant} FC. Merci de r├®gulariser.', 1, 1, 'sent', 1, '2025-08-14 13:36:55', NULL),
(2, 'email', 'Lettre de rappel', 'Madame, Monsieur, nous vous rappelons que votre enfant {nom_eleve} a une dette de {montant} FC.', 1, 1, 'sent', 1, '2025-08-14 13:36:55', NULL),
(3, 'lettre', 'Mise en demeure', 'Suite ├á nos relances, nous vous mettons en demeure de r├®gulariser la dette de {montant} FC.', 2, 1, 'pending', 1, '2025-08-14 13:36:55', NULL);

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
  `type_paiement` enum('inscription','mensualite','examen','autre') NOT NULL,
  `montant` decimal(10,2) NOT NULL,
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

--
-- Déchargement des données de la table `paiements`
--

INSERT INTO `paiements` (`id`, `eleve_id`, `type_paiement`, `montant`, `status`, `date_paiement`, `mois_concerne`, `annee_scolaire_id`, `recu_numero`, `mode_paiement`, `reference`, `observation`, `user_id`, `created_at`, `updated_at`) VALUES
(1, 9, 'inscription', 15000.00, 'valide', '2025-08-09 21:00:00', '', 1, 'REC20250001', 'mobile_money', NULL, 'jhgjjjjjjjjjjjjjjggj', 1, '2025-08-09 02:17:50', '2025-08-09 02:32:19');

-- --------------------------------------------------------

--
-- Structure de la table `paiements_cartes`
--

CREATE TABLE `paiements_cartes` (
  `id` int NOT NULL,
  `carte_id` int NOT NULL,
  `montant` decimal(10,2) NOT NULL,
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

--
-- Déchargement des données de la table `parametres_bibliotheque`
--

INSERT INTO `parametres_bibliotheque` (`id`, `cle`, `valeur`, `description`, `type`, `updated_at`) VALUES
(1, 'duree_emprunt_eleve', '14', 'Durée d\'emprunt par défaut pour les élèves (en jours)', 'number', NULL),
(2, 'duree_emprunt_personnel', '21', 'Durée d\'emprunt par défaut pour le personnel (en jours)', 'number', NULL),
(3, 'max_emprunts_eleve', '3', 'Nombre maximum d\'emprunts simultanés pour un élève', 'number', NULL),
(4, 'max_emprunts_personnel', '5', 'Nombre maximum d\'emprunts simultanés pour le personnel', 'number', NULL),
(5, 'penalite_retard_jour', '100', 'Pénalité par jour de retard (en FC)', 'number', NULL),
(6, 'penalite_perte', '5000', 'Pénalité pour perte d\'un livre (en FC)', 'number', NULL),
(7, 'duree_reservation', '7', 'Durée de validité d\'une réservation (en jours)', 'number', NULL),
(8, 'notifications_actives', '1', 'Activer les notifications de rappel', 'boolean', NULL),
(9, 'rappel_avant_echeance', '3', 'Nombre de jours avant échéance pour envoyer un rappel', 'number', NULL),
(10, 'bibliothecaire_principal', '1', 'ID de l\'utilisateur bibliothécaire principal', 'number', NULL),
(11, 'amende_retard', '100', 'Montant de l\'amende par jour de retard (en FC)', 'text', NULL);

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

--
-- Déchargement des données de la table `parametres_recouvrement`
--

INSERT INTO `parametres_recouvrement` (`id`, `cle`, `valeur`, `description`, `type`, `created_at`, `updated_at`) VALUES
(1, 'prefixe_carte', 'CARD', 'Préfixe pour les numéros de cartes', 'string', '2025-08-14 07:55:26', NULL),
(2, 'montant_limite_defaut', '50000', 'Montant limite par défaut des cartes (en FC)', 'number', '2025-08-14 07:55:26', NULL),
(3, 'duree_validite', '365', 'Durée de validité des cartes en jours', 'number', '2025-08-14 07:55:26', NULL),
(4, 'frais_emission', '5000', 'Frais d\'émission de carte (en FC)', 'number', '2025-08-14 07:55:26', NULL),
(5, 'frais_recharge', '1000', 'Frais de recharge de carte (en FC)', 'number', '2025-08-14 07:55:26', NULL),
(6, 'seuil_alerte', '10000', 'Seuil d\'alerte pour solde faible (en FC)', 'number', '2025-08-14 07:55:26', NULL),
(7, 'activer_notifications', 'true', 'Activer les notifications SMS/Email', 'boolean', '2025-08-14 07:55:26', NULL),
(8, 'mode_maintenance', 'false', 'Mode maintenance du système de cartes', 'boolean', '2025-08-14 07:55:26', NULL);

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

--
-- Déchargement des données de la table `personnel`
--

INSERT INTO `personnel` (`id`, `matricule`, `nom`, `prenom`, `sexe`, `date_naissance`, `lieu_naissance`, `adresse`, `telephone`, `email`, `fonction`, `specialite`, `diplome`, `date_embauche`, `salaire_base`, `status`, `user_id`, `created_at`) VALUES
(1, 'EMP20259771', 'Siwa', 'Carin', 'M', '1998-07-09', 'Goma', 'AV. ITEBERO N°100 Q/ MABANGA NORD C/ KARISIMBI', '+243975579097', 'thecarinsiwa@gmail.com', 'enseignant', 'dfdf', 'fdfdf', '2025-08-08', 2000000.00, 'actif', NULL, '2025-08-08 19:48:45');

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

--
-- Déchargement des données de la table `recompenses`
--

INSERT INTO `recompenses` (`id`, `eleve_id`, `classe_id`, `type_recompense`, `motif`, `date_recompense`, `attribuee_par`, `valeur_points`, `description`, `parent_informe`, `date_information_parent`, `created_at`, `updated_at`) VALUES
(1, 2, 2, 'felicitations', 'lklklk', '2025-08-09', 1, 20, 'kjkjkjjk', 1, NULL, '2025-08-09 20:00:29', NULL);

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

--
-- Déchargement des données de la table `sections`
--

INSERT INTO `sections` (`id`, `nom`, `niveau`, `description`, `created_at`) VALUES
(1, 'Générale', 'primaire', 'Section générale pour l\'enseignement primaire', '2025-09-02 20:06:00'),
(2, 'Scientifique', 'secondaire', 'Section scientifique pour l\'enseignement secondaire', '2025-09-02 20:06:00'),
(3, 'Littéraire', 'secondaire', 'Section littéraire pour l\'enseignement secondaire', '2025-09-02 20:06:00'),
(4, 'Commerciale', 'secondaire', 'Section commerciale pour l\'enseignement secondaire', '2025-09-02 20:06:00'),
(5, 'Technique', 'secondaire', 'Section technique pour l\'enseignement secondaire', '2025-09-02 20:06:00'),
(6, 'Générale', 'primaire', 'Section générale pour l\'enseignement primaire', '2025-09-02 20:08:53'),
(7, 'Scientifique', 'secondaire', 'Section scientifique pour l\'enseignement secondaire', '2025-09-02 20:08:53'),
(8, 'Littéraire', 'secondaire', 'Section littéraire pour l\'enseignement secondaire', '2025-09-02 20:08:53'),
(9, 'Commerciale', 'secondaire', 'Section commerciale pour l\'enseignement secondaire', '2025-09-02 20:08:53'),
(10, 'Technique', 'secondaire', 'Section technique pour l\'enseignement secondaire', '2025-09-02 20:08:53'),
(11, 'Générale', 'primaire', 'Section générale pour l\'enseignement primaire', '2025-09-02 20:10:15'),
(12, 'Scientifique', 'secondaire', 'Section scientifique pour l\'enseignement secondaire', '2025-09-02 20:10:15'),
(13, 'Littéraire', 'secondaire', 'Section littéraire pour l\'enseignement secondaire', '2025-09-02 20:10:15'),
(14, 'Commerciale', 'secondaire', 'Section commerciale pour l\'enseignement secondaire', '2025-09-02 20:10:15'),
(15, 'Technique', 'secondaire', 'Section technique pour l\'enseignement secondaire', '2025-09-02 20:10:15'),
(16, 'Générale', 'primaire', 'Section générale pour l\'enseignement primaire', '2025-09-02 20:24:19'),
(17, 'Scientifique', 'secondaire', 'Section scientifique pour l\'enseignement secondaire', '2025-09-02 20:24:19'),
(18, 'Littéraire', 'secondaire', 'Section littéraire pour l\'enseignement secondaire', '2025-09-02 20:24:19'),
(19, 'Commerciale', 'secondaire', 'Section commerciale pour l\'enseignement secondaire', '2025-09-02 20:24:19'),
(20, 'Technique', 'secondaire', 'Section technique pour l\'enseignement secondaire', '2025-09-02 20:24:19'),
(21, 'Générale', 'primaire', 'Section générale pour l\'enseignement primaire', '2025-09-02 20:25:47'),
(22, 'Scientifique', 'secondaire', 'Section scientifique pour l\'enseignement secondaire', '2025-09-02 20:25:47'),
(23, 'Littéraire', 'secondaire', 'Section littéraire pour l\'enseignement secondaire', '2025-09-02 20:25:47'),
(24, 'Commerciale', 'secondaire', 'Section commerciale pour l\'enseignement secondaire', '2025-09-02 20:25:47'),
(25, 'Technique', 'secondaire', 'Section technique pour l\'enseignement secondaire', '2025-09-02 20:25:47');

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

--
-- Déchargement des données de la table `sms_logs`
--

INSERT INTO `sms_logs` (`id`, `expediteur_id`, `destinataire_telephone`, `destinataire_nom`, `message`, `type_sms`, `cout`, `status`, `provider_response`, `date_envoi`, `date_livraison`, `tentatives`, `created_at`) VALUES
(1, 1, '0975579097', 'Siwa Carin', 'Votre enfant {eleve_nom} est absent aujourd\'hui {date}. Si cette absence est justifiée, merci de nous en informer.', 'retard', 50.00, 'envoye', NULL, '2025-08-14 08:23:36', NULL, 0, '2025-08-14 07:23:36');

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

--
-- Déchargement des données de la table `system_settings`
--

INSERT INTO `system_settings` (`id`, `cle`, `valeur`, `description`, `type`, `options`, `categorie`, `updated_at`, `created_at`) VALUES
(1, 'school_name', 'École de Maris', 'Nom de l\'établissement', 'text', NULL, 'etablissement', '2025-08-10 22:02:28', '2025-08-09 19:38:10'),
(2, 'school_address', 'Avenue de la Paix, Kinshasa', 'Adresse de l\'établissement', 'text', NULL, 'etablissement', '2025-08-10 22:02:28', '2025-08-09 19:38:10'),
(3, 'school_city', 'Kinshasa', 'Ville de l\'établissement', 'text', NULL, 'etablissement', '2025-08-10 22:02:28', '2025-08-09 19:38:10'),
(4, 'school_country', 'République Démocratique du Congo', 'Pays de l\'établissement', 'text', NULL, 'etablissement', '2025-08-10 22:02:28', '2025-08-09 19:38:10'),
(5, 'school_phone', '+243 123 456 789', 'Téléphone de l\'établissement', 'text', NULL, 'etablissement', '2025-08-10 22:02:28', '2025-08-09 19:38:10'),
(6, 'school_fax', '', 'Fax de l\'établissement', 'text', NULL, 'etablissement', '2025-08-10 22:02:28', '2025-08-09 19:38:10'),
(7, 'school_email', 'contact@ecole-sinfinity.cd', 'Email de l\'établissement', 'email', NULL, 'etablissement', '2025-08-10 22:02:28', '2025-08-09 19:38:10'),
(8, 'school_website', 'https://www.ecole-sinfinity.cd', 'Site web de l\'établissement', 'url', NULL, 'etablissement', '2025-08-10 22:02:28', '2025-08-09 19:38:10'),
(9, 'max_students_per_class', '30', 'Nombre maximum d\'élèves par classe', 'number', NULL, 'academique', '2025-08-10 22:02:28', '2025-08-09 19:38:10'),
(10, 'school_year_start_month', '9', 'Mois de début d\'année scolaire', 'select', '{\"1\":\"Janvier\",\"2\":\"Février\",\"3\":\"Mars\",\"4\":\"Avril\",\"5\":\"Mai\",\"6\":\"Juin\",\"7\":\"Juillet\",\"8\":\"Août\",\"9\":\"Septembre\",\"10\":\"Octobre\",\"11\":\"Novembre\",\"12\":\"Décembre\"}', 'academique', '2025-08-10 22:02:28', '2025-08-09 19:38:10'),
(11, 'timezone', 'Africa/Kinshasa', 'Fuseau horaire', 'select', '{\"Africa/Kinshasa\":\"Africa/Kinshasa (UTC+1)\",\"Africa/Lubumbashi\":\"Africa/Lubumbashi (UTC+2)\"}', 'academique', '2025-08-10 22:02:28', '2025-08-09 19:38:10'),
(12, 'language', 'fr', 'Langue par défaut', 'select', '{\"fr\":\"Français\",\"en\":\"English\"}', 'academique', '2025-08-10 22:02:28', '2025-08-09 19:38:10'),
(13, 'currency', 'FC', 'Devise', 'select', '{\"FC\":\"Franc Congolais (FC)\",\"USD\":\"Dollar US ($)\"}', 'academique', '2025-08-10 22:02:28', '2025-08-09 19:38:10'),
(14, 'admin_email', 'admin@ecole-sinfinity.cd', 'Email administrateur', 'email', NULL, 'communication', '2025-08-10 22:02:28', '2025-08-09 19:38:10'),
(15, 'enable_email', '1', 'Activer les emails', 'boolean', NULL, 'communication', '2025-08-10 22:02:28', '2025-08-09 19:38:10'),
(16, 'enable_sms', '1', 'Activer les SMS', 'boolean', NULL, 'communication', '2025-08-10 22:02:28', '2025-08-09 19:38:10'),
(17, 'enable_notifications', '1', 'Activer les notifications', 'boolean', NULL, 'communication', '2025-08-10 22:02:28', '2025-08-09 19:38:10'),
(18, 'backup_retention_days', '30', 'Rétention des sauvegardes (jours)', 'number', NULL, 'systeme', '2025-08-10 22:02:28', '2025-08-09 19:38:10'),
(19, 'maintenance_mode', '0', 'Mode maintenance', 'boolean', NULL, 'systeme', NULL, '2025-08-09 19:38:10');

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

--
-- Déchargement des données de la table `templates_messages`
--

INSERT INTO `templates_messages` (`id`, `nom`, `description`, `sujet`, `contenu`, `type`, `categorie`, `variables`, `actif`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 'Convocation parent', 'Template pour convoquer un parent', 'Convocation - {eleve_nom}', 'Cher(e) parent de {eleve_nom},\\n\\nNous vous prions de bien vouloir vous présenter à l\'établissement le {date_rdv} à {heure_rdv} pour un entretien concernant votre enfant.\\n\\nMotif: {motif}\\n\\nCordialement,\\nL\'administration', 'email', 'discipline', '[\"eleve_nom\", \"date_rdv\", \"heure_rdv\", \"motif\"]', 1, 1, '2025-08-09 19:16:12', '2025-08-18 18:18:57'),
(2, 'Absence élève', 'Notification d\'absence d\'un élève', 'Absence de {eleve_nom}', 'Votre enfant {eleve_nom} est absent aujourd\'hui {date}. Si cette absence est justifiée, merci de nous en informer.', 'sms', 'absence', '[\"eleve_nom\", \"date\", \"classe\"]', 1, 1, '2025-08-09 19:16:12', NULL),
(3, 'Félicitations', 'Message de félicitations pour bons résultats', 'Félicitations pour {eleve_nom}', 'Nous tenons à vous féliciter pour les excellents résultats de {eleve_nom} en {matiere}. Continuez ainsi !', 'email', 'pedagogique', '[\"eleve_nom\", \"matiere\", \"note\", \"moyenne\"]', 1, 1, '2025-08-09 19:16:12', NULL);

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

--
-- Déchargement des données de la table `temp_documents_backup`
--

INSERT INTO `temp_documents_backup` (`id`, `certificat_naissance`, `bulletin_precedent`, `certificat_medical`, `photo_identite`, `autres_documents`) VALUES
(1, 'fourni', '', '', '', ''),
(2, '', '', '', '', NULL),
(3, '', '', '', '', NULL);

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

--
-- Déchargement des données de la table `transfer_history`
--

INSERT INTO `transfer_history` (`id`, `transfer_id`, `action`, `ancien_statut`, `nouveau_statut`, `commentaire`, `user_id`, `created_at`) VALUES
(1, 3, 'modification', 'approuve', 'approuve', 'Documents mis à jour', 1, '2025-08-08 20:28:19'),
(2, 3, 'completion', 'approuve', 'complete', 'Il peut partir', 1, '2025-08-08 21:00:10');

-- --------------------------------------------------------

--
-- Structure de la table `types_frais`
--

CREATE TABLE `types_frais` (
  `id` int NOT NULL,
  `nom` varchar(100) NOT NULL,
  `description` text,
  `montant_defaut` decimal(10,2) DEFAULT NULL,
  `obligatoire` tinyint(1) DEFAULT '1',
  `periode` enum('annuel','trimestriel','mensuel','ponctuel') DEFAULT 'annuel',
  `status` enum('actif','inactif') DEFAULT 'actif',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

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

--
-- Déchargement des données de la table `types_sanctions`
--

INSERT INTO `types_sanctions` (`id`, `nom`, `description`, `gravite`, `duree_defaut`, `couleur`, `active`, `created_at`, `updated_at`) VALUES
(1, 'Avertissement oral', 'Rappel à l\'ordre verbal', 'legere', 0, '#28a745', 1, '2025-08-09 19:16:07', NULL),
(2, 'Avertissement écrit', 'Mise en garde officielle par écrit', 'legere', 0, '#ffc107', 1, '2025-08-09 19:16:07', NULL),
(3, 'Retenue', 'Retenue après les cours', 'moyenne', 1, '#fd7e14', 1, '2025-08-09 19:16:07', NULL),
(4, 'Exclusion temporaire', 'Exclusion de cours pour une durée déterminée', 'grave', 3, '#dc3545', 1, '2025-08-09 19:16:07', NULL),
(5, 'Travaux d\'intérêt général', 'Participation à des activités d\'utilité collective', 'moyenne', 5, '#6f42c1', 1, '2025-08-09 19:16:07', NULL),
(6, 'Convocation des parents', 'Rencontre obligatoire avec les parents', 'moyenne', 0, '#20c997', 1, '2025-08-09 19:16:07', NULL),
(7, 'Exclusion définitive', 'Renvoi définitif de l\'établissement', 'tres_grave', 0, '#000000', 1, '2025-08-09 19:16:07', NULL),
(8, 'Blâme', 'Sanction disciplinaire inscrite au dossier', 'grave', 0, '#e83e8c', 1, '2025-08-09 19:16:07', NULL),
(9, 'Avertissement oral', 'Rappel à l\'ordre verbal', 'legere', 0, '#28a745', 1, '2025-08-09 19:49:10', NULL),
(10, 'Avertissement écrit', 'Mise en garde officielle par écrit', 'legere', 0, '#ffc107', 1, '2025-08-09 19:49:10', NULL),
(11, 'Retenue', 'Retenue après les cours', 'moyenne', 1, '#fd7e14', 1, '2025-08-09 19:49:10', NULL),
(12, 'Exclusion temporaire', 'Exclusion de cours pour une durée déterminée', 'grave', 3, '#dc3545', 1, '2025-08-09 19:49:10', NULL),
(13, 'Travaux d\'intérêt général', 'Participation à des activités d\'utilité collective', 'moyenne', 5, '#6f42c1', 1, '2025-08-09 19:49:10', NULL),
(14, 'Convocation des parents', 'Rencontre obligatoire avec les parents', 'moyenne', 0, '#20c997', 1, '2025-08-09 19:49:10', NULL),
(15, 'Exclusion définitive', 'Renvoi définitif de l\'établissement', 'tres_grave', 0, '#000000', 1, '2025-08-09 19:49:10', NULL),
(16, 'Blâme', 'Sanction disciplinaire inscrite au dossier', 'grave', 0, '#e83e8c', 1, '2025-08-09 19:49:10', NULL);

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
  `role` enum('admin','directeur','enseignant','secretaire','comptable','surveillant') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
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

--
-- Déchargement des données de la table `users`
--

INSERT INTO `users` (`id`, `username`, `password`, `nom`, `prenom`, `email`, `telephone`, `role`, `status`, `photo`, `adresse`, `date_naissance`, `genre`, `derniere_connexion`, `tentatives_connexion`, `compte_verrouille`, `date_verrouillage`, `created_at`, `updated_at`) VALUES
(1, 'admin', '7c4a8d09ca3762af61e59520943dc26494f8941b', 'Siwa', 'Carin', 'thecarinsiwa@gmail.com', '0975579097', 'comptable', 'actif', NULL, NULL, NULL, NULL, '2025-09-02 17:05:53', 0, 0, NULL, '2025-08-08 13:26:12', '2025-09-02 17:05:53'),
(2, 'csiwa', '7c222fb2927d828af22f592134e8932480637c0d', 'Siwa', 'Carin', 'carin@gmail.com', '0975579097', 'admin', 'actif', NULL, NULL, NULL, 'M', '2025-09-02 19:17:38', 0, 0, NULL, '2025-08-31 08:29:36', '2025-09-02 19:17:38');

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
(74, 1, 'process_transfer', 'transfers', 'Action \'complete\' sur le transfert ID: 3', 3, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-08-08 21:00:10'),
(75, 1, 'create_academic_year', 'academic', 'Nouvelle année scolaire créée: 2025-2026 (active)', 2, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-08-08 23:34:15'),
(76, 1, 'update_academic_year', 'academic', 'Année scolaire modifiée: 2023-2024 (active)', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-08-08 23:34:32'),
(77, 1, 'add_schedule_course', 'academic', 'Cours ajouté - Classe: 1ère Primaire A, Matière: Mathématiques, Jour: Lundi 08:00-09:00', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-08-08 23:36:54'),
(78, 1, 'add_schedule_course', 'academic', 'Cours ajouté - Classe: 1ère Primaire A, Matière: Mathématiques, Jour: Mercredi 08:00-09:00', 2, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-08-08 23:39:02'),
(79, 1, 'add_schedule_course', 'academic', 'Cours ajouté - Classe: 6ème Primaire A, Matière: Mathématiques, Jour: Lundi 08:00-09:00', 3, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-08-08 23:45:14'),
(80, 1, 'generate_schedule', 'academic', 'Emploi du temps généré pour la classe: 1ère Primaire A', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-08-08 23:52:17'),
(81, 1, 'export_schedule', 'academic', 'Export emploi du temps - Format: pdf, Type: classe', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-08-08 23:55:56'),
(82, 1, 'export_schedule', 'academic', 'Export emploi du temps - Format: pdf, Type: classe', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-08-08 23:55:56'),
(83, 1, 'update_evaluation', 'evaluations', 'Évaluation modifiée: Interrogation - Tables de multiplication (ID: 2)', 2, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-08-09 00:42:37'),
(84, 1, 'view_absence_edit', 'attendance', 'Consultation de la page d\'édition de l\'absence ID 12', 12, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-08-10 01:42:12'),
(85, 1, 'generate_schedule', 'academic', 'Emploi du temps généré pour la classe: 1ère Primaire A', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-08-14 09:40:02'),
(86, 1, 'close_academic_year', 'academic', 'Année scolaire fermée: 2023-2024', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-08-14 10:06:33'),
(87, 1, 'activate_academic_year', 'academic', 'Année scolaire activée: 2025-2026', 2, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-08-14 10:18:37'),
(88, 1, 'activate_academic_year', 'academic', 'Année scolaire activée: 2023-2024', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-08-14 10:18:57'),
(89, 1, 'activate_academic_year', 'academic', 'Année scolaire activée: 2025-2026', 2, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-08-14 11:51:55'),
(90, 1, 'activate_academic_year', 'academic', 'Année scolaire activée: 2023-2024', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-08-14 11:52:02'),
(91, 1, 'activate_academic_year', 'academic', 'Année scolaire activée: 2025-2026', 2, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-08-14 11:52:29'),
(92, 1, 'close_academic_year', 'academic', 'Année scolaire fermée: 2025-2026', 2, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-08-14 11:53:13'),
(93, 1, 'activate_academic_year', 'academic', 'Année scolaire activée: 2023-2024', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-08-14 11:53:17'),
(94, 1, 'view_monthly_report', 'attendance', 'Consultation du rapport mensuel - Mois: 2025-08', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-08-14 11:53:46'),
(95, 1, 'view_export_page', 'attendance', 'Consultation de la page d\'export des données', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-08-14 11:53:51'),
(96, 1, 'preview_export_data', 'attendance', 'Aperçu export - Type: summary, Période: 2025-08-01 à 2025-08-31, Résultats: 5', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-08-14 11:53:58'),
(97, 1, 'view_monthly_report', 'attendance', 'Consultation du rapport mensuel - Mois: 2025-08', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-08-14 13:14:48'),
(98, 1, 'view_parent_notifications', 'attendance', 'Consultation de la page notifications parents', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-08-14 13:14:58'),
(99, 1, 'view_monthly_report', 'attendance', 'Consultation du rapport mensuel - Mois: 2025-08', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-08-14 13:15:17'),
(100, 1, 'view_monthly_report', 'attendance', 'Consultation du rapport mensuel - Mois: 2025-08', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-08-14 13:15:20'),
(101, 1, 'view_parent_notifications', 'attendance', 'Consultation de la page notifications parents', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-08-14 13:15:28'),
(102, 1, 'export_schedule', 'academic', 'Export emploi du temps - Format: pdf, Type: classe', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-08-14 14:12:45'),
(103, 1, 'export_schedule', 'academic', 'Export emploi du temps - Format: pdf, Type: classe', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-08-14 14:13:19'),
(104, 1, 'update_user', 'admin', 'Utilisateur modifié: admin (Siwa Carin)', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-08-25 17:52:55'),
(105, 1, 'update_user', 'admin', 'Utilisateur modifié: admin (Siwa Carin)', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-08-25 17:53:28'),
(106, 2, 'close_academic_year', 'academic', 'Année scolaire fermée: 2023-2024', 1, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-08-31 08:34:46'),
(107, 2, 'activate_academic_year', 'academic', 'Année scolaire activée: 2025-2026', 2, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-08-31 08:35:27'),
(108, 2, 'reinscription', 'students', 'Réinscription de l\'élève ID: 4 pour l\'année 2025-2026', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-09-02 13:48:00'),
(109, 2, 'update_password', 'admin', 'Mot de passe modifié pour: admin (Siwa Carin)', 1, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-09-02 13:54:10'),
(110, 2, 'create_academic_year', 'academic', 'Nouvelle année scolaire créée: 2026-2027 (active)', 3, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-09-02 16:59:15'),
(111, 2, 'activate_academic_year', 'academic', 'Année scolaire activée: 2023-2024', 1, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-09-02 17:00:29'),
(112, 2, 'update_user', 'admin', 'Utilisateur modifié: admin (Siwa Carin)', 1, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-09-02 17:03:55'),
(113, 2, 'update_user', 'admin', 'Utilisateur modifié: admin (Siwa Carin)', 1, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-09-02 17:05:35'),
(114, 2, 'activate_academic_year', 'academic', 'Année scolaire activée: 2026-2027', 3, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-09-02 17:15:40'),
(115, 2, 'activate_academic_year', 'academic', 'Année scolaire activée: 2025-2026', 2, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-09-02 18:40:18');

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
  ADD PRIMARY KEY (`id`);

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
-- Index pour la table `emplois_temps`
--
ALTER TABLE `emplois_temps`
  ADD PRIMARY KEY (`id`),
  ADD KEY `classe_id` (`classe_id`),
  ADD KEY `matiere_id` (`matiere_id`),
  ADD KEY `enseignant_id` (`enseignant_id`),
  ADD KEY `annee_scolaire_id` (`annee_scolaire_id`);

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
  ADD KEY `idx_classe_type` (`classe_id`,`type_frais`),
  ADD KEY `idx_annee_scolaire` (`annee_scolaire_id`);

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
  ADD KEY `user_id` (`user_id`);

--
-- Index pour la table `paiements_cartes`
--
ALTER TABLE `paiements_cartes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `idx_carte_id` (`carte_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_date_paiement` (`date_paiement`);

--
-- Index pour la table `parametres_bibliotheque`
--
ALTER TABLE `parametres_bibliotheque`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `cle` (`cle`);

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
-- Index pour la table `types_frais`
--
ALTER TABLE `types_frais`
  ADD PRIMARY KEY (`id`);

--
-- Index pour la table `types_sanctions`
--
ALTER TABLE `types_sanctions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_gravite` (`gravite`),
  ADD KEY `idx_active` (`active`);

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
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT pour la table `annonces`
--
ALTER TABLE `annonces`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT pour la table `campagnes_cibles_dettes`
--
ALTER TABLE `campagnes_cibles_dettes`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `campagnes_recouvrement`
--
ALTER TABLE `campagnes_recouvrement`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT pour la table `cartes_eleves`
--
ALTER TABLE `cartes_eleves`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT pour la table `categories_livres`
--
ALTER TABLE `categories_livres`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT pour la table `classes`
--
ALTER TABLE `classes`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT pour la table `commandes`
--
ALTER TABLE `commandes`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `criteres_admission`
--
ALTER TABLE `criteres_admission`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT pour la table `criteres_admission_classes`
--
ALTER TABLE `criteres_admission_classes`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT pour la table `decisions_admission`
--
ALTER TABLE `decisions_admission`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `demandes_admission`
--
ALTER TABLE `demandes_admission`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT pour la table `depenses`
--
ALTER TABLE `depenses`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT pour la table `documents_eleve`
--
ALTER TABLE `documents_eleve`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `documents_eleves`
--
ALTER TABLE `documents_eleves`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT pour la table `eleves`
--
ALTER TABLE `eleves`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT pour la table `emplois_temps`
--
ALTER TABLE `emplois_temps`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT pour la table `emploi_temps`
--
ALTER TABLE `emploi_temps`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=88;

--
-- AUTO_INCREMENT pour la table `emprunts`
--
ALTER TABLE `emprunts`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `emprunts_livres`
--
ALTER TABLE `emprunts_livres`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT pour la table `etablissements`
--
ALTER TABLE `etablissements`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT pour la table `etapes_admission`
--
ALTER TABLE `etapes_admission`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=31;

--
-- AUTO_INCREMENT pour la table `evaluations`
--
ALTER TABLE `evaluations`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

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
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT pour la table `frais_scolaires`
--
ALTER TABLE `frais_scolaires`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT pour la table `incidents`
--
ALTER TABLE `incidents`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT pour la table `inscriptions`
--
ALTER TABLE `inscriptions`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT pour la table `inscriptions_detaillees`
--
ALTER TABLE `inscriptions_detaillees`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `livres`
--
ALTER TABLE `livres`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT pour la table `matieres`
--
ALTER TABLE `matieres`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT pour la table `messages`
--
ALTER TABLE `messages`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT pour la table `notes`
--
ALTER TABLE `notes`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

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
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT pour la table `notifications_recouvrement`
--
ALTER TABLE `notifications_recouvrement`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT pour la table `notifications_suivi`
--
ALTER TABLE `notifications_suivi`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `paiements`
--
ALTER TABLE `paiements`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT pour la table `paiements_cartes`
--
ALTER TABLE `paiements_cartes`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `parametres_bibliotheque`
--
ALTER TABLE `parametres_bibliotheque`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT pour la table `parametres_recouvrement`
--
ALTER TABLE `parametres_recouvrement`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=25;

--
-- AUTO_INCREMENT pour la table `parents`
--
ALTER TABLE `parents`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT pour la table `penalites_bibliotheque`
--
ALTER TABLE `penalites_bibliotheque`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `personnel`
--
ALTER TABLE `personnel`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT pour la table `presences_qr`
--
ALTER TABLE `presences_qr`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `recompenses`
--
ALTER TABLE `recompenses`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT pour la table `reservations_livres`
--
ALTER TABLE `reservations_livres`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `sanctions`
--
ALTER TABLE `sanctions`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `sections`
--
ALTER TABLE `sections`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=26;

--
-- AUTO_INCREMENT pour la table `sms_logs`
--
ALTER TABLE `sms_logs`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

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
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=93;

--
-- AUTO_INCREMENT pour la table `templates_messages`
--
ALTER TABLE `templates_messages`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT pour la table `transactions_cartes`
--
ALTER TABLE `transactions_cartes`
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
-- AUTO_INCREMENT pour la table `types_frais`
--
ALTER TABLE `types_frais`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `types_sanctions`
--
ALTER TABLE `types_sanctions`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT pour la table `users`
--
ALTER TABLE `users`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT pour la table `user_actions_log`
--
ALTER TABLE `user_actions_log`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=116;

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
-- Contraintes pour la table `emplois_temps`
--
ALTER TABLE `emplois_temps`
  ADD CONSTRAINT `emplois_temps_ibfk_1` FOREIGN KEY (`classe_id`) REFERENCES `classes` (`id`),
  ADD CONSTRAINT `emplois_temps_ibfk_2` FOREIGN KEY (`matiere_id`) REFERENCES `matieres` (`id`),
  ADD CONSTRAINT `emplois_temps_ibfk_3` FOREIGN KEY (`enseignant_id`) REFERENCES `personnel` (`id`),
  ADD CONSTRAINT `emplois_temps_ibfk_4` FOREIGN KEY (`annee_scolaire_id`) REFERENCES `annees_scolaires` (`id`);

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
  ADD CONSTRAINT `paiements_ibfk_1` FOREIGN KEY (`eleve_id`) REFERENCES `eleves` (`id`),
  ADD CONSTRAINT `paiements_ibfk_2` FOREIGN KEY (`annee_scolaire_id`) REFERENCES `annees_scolaires` (`id`),
  ADD CONSTRAINT `paiements_ibfk_3` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Contraintes pour la table `paiements_cartes`
--
ALTER TABLE `paiements_cartes`
  ADD CONSTRAINT `paiements_cartes_ibfk_1` FOREIGN KEY (`carte_id`) REFERENCES `cartes_eleves` (`id`),
  ADD CONSTRAINT `paiements_cartes_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

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
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
