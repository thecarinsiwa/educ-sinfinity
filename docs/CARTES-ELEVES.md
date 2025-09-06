# Module Carte d'Élève

## Description
Le module "Carte d'Élève" permet de générer automatiquement des cartes d'identification pour tous les élèves de l'école. Ces cartes contiennent un QR Code qui permet de gérer les présences et de consulter les soldes de frais scolaires.

## Fonctionnalités

### 1. Génération automatique de cartes
- **Lors de l'inscription** : Une carte est automatiquement générée pour chaque nouvel élève
- **Lors de la réinscription** : Une nouvelle carte est créée pour l'année scolaire suivante
- **Génération manuelle** : Possibilité de générer des cartes pour des élèves spécifiques ou des classes entières

### 2. Contenu des cartes
- **Informations de l'élève** : Nom complet, matricule, classe, année scolaire
- **Photo de l'élève** : Si disponible dans le système
- **QR Code** : Contient les données de l'élève pour le pointage et la consultation du solde
- **Design personnalisable** : Couleurs, logo de l'école, format de carte

### 3. QR Code et fonctionnalités
- **Pointage de présence** : Scanner le QR Code pour marquer automatiquement la présence
- **Consultation du solde** : Vérifier le solde des frais scolaires de l'élève
- **Informations élève** : Accès rapide aux données de l'élève

### 4. Gestion des cartes
- **Statuts** : Active, expirée, suspendue, archivée
- **Validité annuelle** : Chaque année scolaire génère de nouvelles cartes
- **Archivage automatique** : Les cartes de l'année précédente sont archivées
- **Régénération** : Possibilité de régénérer une carte en cas de perte

### 5. Impression et export
- **Format PDF** : Impression des cartes au format PDF
- **Format PVC** : Support pour l'impression sur cartes PVC
- **Impression en lot** : Impression de toutes les cartes d'une classe ou de l'école
- **Téléchargement QR** : Export individuel du QR Code

## Structure de la base de données

### Table `cartes_eleves`
```sql
CREATE TABLE `cartes_eleves` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `eleve_id` int(11) NOT NULL,
  `annee_scolaire_id` int(11) NOT NULL,
  `numero_carte` varchar(50) NOT NULL,
  `qr_code` text NOT NULL,
  `qr_data` text NOT NULL,
  `statut` enum('active','expiree','suspendue','archivée') DEFAULT 'active',
  `date_generation` datetime NOT NULL,
  `date_expiration` datetime NOT NULL,
  `date_archivage` datetime NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_carte_annee` (`eleve_id`, `annee_scolaire_id`),
  UNIQUE KEY `unique_numero_carte` (`numero_carte`)
);
```

### Table `cartes_eleves_historique`
```sql
CREATE TABLE `cartes_eleves_historique` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `carte_id` int(11) NOT NULL,
  `eleve_id` int(11) NOT NULL,
  `annee_scolaire_id` int(11) NOT NULL,
  `numero_carte` varchar(50) NOT NULL,
  `qr_code` text NOT NULL,
  `statut` enum('active','expiree','suspendue','archivée') NOT NULL,
  `date_generation` datetime NOT NULL,
  `date_expiration` datetime NOT NULL,
  `date_archivage` datetime NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
);
```

### Table `parametres_cartes`
```sql
CREATE TABLE `parametres_cartes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nom_ecole` varchar(255) NOT NULL DEFAULT 'École Sinfinity',
  `logo_ecole` varchar(500) NULL,
  `couleur_principale` varchar(7) NOT NULL DEFAULT '#1e40af',
  `couleur_secondaire` varchar(7) NOT NULL DEFAULT '#3b82f6',
  `couleur_texte` varchar(7) NOT NULL DEFAULT '#1f2937',
  `format_carte` enum('pvc','pdf') NOT NULL DEFAULT 'pdf',
  `dimensions` varchar(20) NOT NULL DEFAULT '85.6x54mm',
  `qr_code_size` int(11) NOT NULL DEFAULT 100,
  `include_photo` tinyint(1) NOT NULL DEFAULT 1,
  `include_qr_code` tinyint(1) NOT NULL DEFAULT 1,
  `include_barcode` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
);
```

### Table `logs_scan_cartes`
```sql
CREATE TABLE `logs_scan_cartes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `carte_id` int(11) NOT NULL,
  `eleve_id` int(11) NOT NULL,
  `type_scan` enum('presence','solde','autre') NOT NULL,
  `ip_address` varchar(45) NULL,
  `user_agent` text NULL,
  `donnees_scan` text NULL,
  `resultat` text NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
);
```

## Fichiers du module

### Fichiers principaux
- `index.php` - Interface principale de gestion des cartes
- `actions.php` - Actions AJAX pour la gestion des cartes
- `view.php` - Visualisation d'une carte individuelle
- `print.php` - Impression d'une carte
- `print-all.php` - Impression de toutes les cartes
- `settings.php` - Paramètres de design des cartes

### Fichiers de génération
- `auto-generate.php` - Fonctions de génération automatique
- `get-students.php` - Récupération des élèves pour la génération

### Fichiers QR Code
- `qr-scanner.php` - Interface de scan des QR Codes
- `qr-actions.php` - Actions pour le scanner QR
- `download-qr.php` - Téléchargement du QR Code

### Fichiers d'intégration
- `integration-presences.php` - Intégration avec le module Présences
- `integration-paiements.php` - Intégration avec le module Paiements

## Installation

### 1. Exécuter les migrations
```sql
-- Exécuter le fichier de migration
SOURCE database/migrations/create_cartes_eleves_table.sql;
```

### 2. Configurer les permissions
Ajouter les permissions suivantes dans le système :
- `cartes_eleves.view` - Voir les cartes
- `cartes_eleves.manage` - Gérer les cartes
- `cartes_eleves.print` - Imprimer les cartes
- `cartes_eleves.scan` - Scanner les QR Codes
- `cartes_eleves.settings` - Modifier les paramètres

### 3. Intégration automatique
Le module s'intègre automatiquement avec :
- Le module d'inscription des élèves
- Le module de réinscription
- Le module de présences
- Le module de paiements

## Utilisation

### 1. Génération de cartes
1. Aller dans le module "Cartes d'Élèves"
2. Cliquer sur "Générer des cartes"
3. Sélectionner la classe ou les élèves
4. Choisir le type de génération
5. Valider la génération

### 2. Impression des cartes
1. Sélectionner les cartes à imprimer
2. Cliquer sur "Imprimer"
3. Choisir le format (PDF ou PVC)
4. Lancer l'impression

### 3. Scanner QR Code
1. Aller dans "Scanner QR Code"
2. Démarrer le scanner
3. Scanner la carte d'un élève
4. Choisir l'action (présence, solde, infos)

### 4. Configuration
1. Aller dans "Paramètres"
2. Personnaliser le design des cartes
3. Configurer les couleurs et le logo
4. Sauvegarder les modifications

## Personnalisation

### Design des cartes
- **Couleurs** : Personnaliser les couleurs principale, secondaire et du texte
- **Logo** : Ajouter le logo de l'école
- **Format** : Choisir entre PDF et PVC
- **Dimensions** : Différentes tailles de cartes disponibles
- **Éléments** : Activer/désactiver photo, QR Code, code-barres

### QR Code
- **Taille** : Ajustable de 50px à 200px
- **Données** : Contient l'ID élève, matricule, numéro de carte, année
- **Actions** : Pointage de présence, consultation du solde

## Maintenance

### Archivage automatique
- Les cartes de l'année précédente sont automatiquement archivées
- L'historique est conservé dans la table `cartes_eleves_historique`

### Mise à jour des statuts
- Les cartes expirées sont automatiquement marquées comme "expirées"
- Possibilité de suspendre ou réactiver une carte

### Logs et statistiques
- Tous les scans de QR Code sont enregistrés
- Statistiques de scans disponibles
- Historique des actions sur les cartes

## Sécurité

### Permissions
- Contrôle d'accès basé sur les rôles utilisateur
- Permissions granulaires pour chaque action

### Logs
- Enregistrement de toutes les actions
- Traçabilité des scans de QR Code
- Historique des modifications

### Validation
- Vérification de l'existence de l'élève
- Validation des données du QR Code
- Contrôle de la validité des cartes

## Support technique

### Dépendances
- TCPDF pour l'impression PDF
- Bibliothèque QR Code (recommandée : phpqrcode)
- Accès caméra pour le scanner

### Résolution de problèmes
1. **Carte non générée** : Vérifier les permissions et les données de l'élève
2. **QR Code non scanné** : Vérifier la qualité de l'image et la caméra
3. **Erreur d'impression** : Vérifier la configuration TCPDF et les permissions

### Maintenance
- Nettoyer régulièrement les logs anciens
- Vérifier l'espace disque pour les photos
- Mettre à jour les paramètres de design selon les besoins
