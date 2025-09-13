# 📚 Documentation Complète - Educ-Sinfinity
## Système de Gestion Scolaire - République Démocratique du Congo

---

## 📋 Table des Matières

1. [Vue d'ensemble](#vue-densemble)
2. [Architecture du Système](#architecture-du-système)
3. [Modules Principaux](#modules-principaux)
4. [Système de Rôles et Permissions](#système-de-rôles-et-permissions)
5. [Gestion des Élèves](#gestion-des-élèves)
6. [Module Financier](#module-financier)
7. [Module Académique](#module-académique)
8. [Cartes d'Élèves](#cartes-délèves)
9. [Impression et Export](#impression-et-export)
10. [Installation et Configuration](#installation-et-configuration)
11. [Sécurité](#sécurité)
12. [Maintenance](#maintenance)
13. [Support](#support)

---

## 🎯 Vue d'ensemble

**Educ-Sinfinity** est un système de gestion scolaire complet développé spécifiquement pour les établissements scolaires de la République Démocratique du Congo. Le système offre une solution intégrée pour la gestion des élèves, des finances, des évaluations et de l'administration scolaire.

### Caractéristiques Principales
- ✅ **Interface moderne** et responsive
- ✅ **Système de rôles** granulaires
- ✅ **Gestion multi-devises** (CDF, USD, EUR)
- ✅ **Cartes d'élèves** avec QR codes
- ✅ **Rapports** et exports avancés
- ✅ **Système de permissions** complet
- ✅ **Conformité RDC** (modèle de carte officiel)

---

## 🏗️ Architecture du Système

### Structure des Dossiers
```
educ-sinfinity/
├── admin/                    # Administration système
├── assets/                   # Ressources (CSS, JS, images)
├── auth/                     # Authentification
├── config/                   # Configuration
├── database/                 # Base de données et migrations
├── includes/                 # Fichiers communs
├── modules/                  # Modules fonctionnels
│   ├── academic/            # Gestion académique
│   ├── admissions/          # Admissions
│   ├── cartes_eleves/       # Cartes d'élèves
│   ├── finance/             # Gestion financière
│   ├── students/            # Gestion des élèves
│   └── users/               # Gestion des utilisateurs
├── uploads/                  # Fichiers uploadés
└── vendor/                   # Bibliothèques externes
```

### Technologies Utilisées
- **Backend** : PHP 7.4+, MySQL 5.7+
- **Frontend** : HTML5, CSS3, JavaScript, Bootstrap 5
- **Bibliothèques** : TCPDF, jsPDF, html2canvas, phpqrcode
- **Base de données** : MySQL avec PDO

---

## 🔧 Modules Principaux

### 1. Module de Gestion des Élèves

#### Fonctionnalités
- **Inscription directe** d'élèves avec demande d'admission automatique
- **Gestion des statuts** : actif, non-évalué, transféré, abandonné, diplômé
- **Historique complet** des inscriptions par année scolaire
- **Promotion automatique** entre les classes
- **Suivi des absences** et transferts

#### Structure des Données
```sql
-- Table principale des élèves
CREATE TABLE eleves (
    id INT PRIMARY KEY AUTO_INCREMENT,
    numero_matricule VARCHAR(50) UNIQUE NOT NULL,
    nom VARCHAR(100) NOT NULL,
    prenom VARCHAR(100) NOT NULL,
    date_naissance DATE,
    lieu_naissance VARCHAR(100),
    genre ENUM('M','F') NOT NULL,
    statut ENUM('actif','inactif','diplome','abandonne','non-evalué') DEFAULT 'actif',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP
);

-- Table des inscriptions
CREATE TABLE inscriptions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    eleve_id INT NOT NULL,
    classe_id INT NOT NULL,
    annee_scolaire_id INT NOT NULL,
    date_inscription DATE NOT NULL,
    status ENUM('inscrit','en_attente','annule','transfere') DEFAULT 'inscrit',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (eleve_id) REFERENCES eleves(id) ON DELETE CASCADE
);
```

### 2. Module des Admissions

#### Processus d'Admission
1. **Demande d'admission** : Création automatique lors de l'ajout d'élève
2. **Évaluation** : Tests, entretiens, examens médicaux
3. **Décision** : Acceptation, refus, ou mise en attente
4. **Inscription** : Finalisation du processus

#### Statuts des Demandes
- `en_cours_traitement` : Demande créée, en attente d'évaluation
- `acceptee` : Demande acceptée, élève définitivement inscrit
- `refusee` : Demande refusée, élève non inscrit
- `en_attente` : Demande suspendue temporairement

### 3. Module Financier

#### Gestion des Frais Scolaires
- **Types de frais** configurables par année scolaire
- **Système de priorité** des paiements
- **Gestion multi-devises** avec conversion automatique
- **Suivi des soldes** et relances

#### Types de Frais par Défaut
- 📝 **Inscription** - Frais d'inscription annuels
- 💰 **Mensualité** - Frais de scolarité mensuels
- 📚 **Examen** - Frais d'examens et évaluations
- 👕 **Uniforme** - Frais d'uniforme scolaire
- 🚌 **Transport** - Frais de transport scolaire
- 🍽️ **Cantine** - Frais de restauration

#### Système de Priorité des Paiements
Le système garantit que les élèves paient leurs frais dans l'ordre de priorité :
1. **Priorité 1** : Frais d'inscription (doivent être soldés en premier)
2. **Priorité 2** : Frais mensuels (après inscription complète)
3. **Priorité 3** : Autres frais (uniforme, transport, cantine, etc.)

### 4. Module des Cartes d'Élèves

#### Fonctionnalités
- **Génération automatique** lors de l'inscription
- **QR Code** avec données de l'élève
- **Modèle RDC officiel** conforme aux standards nationaux
- **Impression** en format PDF ou PVC
- **Scanner QR** pour pointage de présence

#### Modèle RDC Officiel
- **En-tête officiel** : "REPUBLIQUE DEMOCRATIQUE DU CONGO"
- **Emblèmes nationaux** : Drapeau RDC et emblème circulaire
- **Informations structurées** : Nom, post-nom, sexe, lieu & date de naissance
- **Zone photo** avec cadre rectangulaire
- **Signature officielle** et QR code

---

## 🔐 Système de Rôles et Permissions

### Rôles par Défaut

#### Administrateur
- **Accès complet** à tous les modules
- **Gestion des utilisateurs** et rôles
- **Configuration système**
- **Permissions** : Toutes les actions sur tous les modules

#### Directeur
- **Gestion pédagogique** et administrative
- **Prise de décisions** d'admission
- **Validation** des rapports
- **Permissions** : Lecture, création, modification (sauf suppression utilisateurs)

#### Enseignant
- **Gestion académique** des élèves
- **Saisie des notes** et évaluations
- **Suivi des élèves**
- **Permissions** : Accès aux modules académiques et élèves

#### Secrétaire
- **Gestion administrative** des élèves
- **Création de demandes** d'admission
- **Génération de rapports**
- **Permissions** : Modules administratifs et élèves

#### Comptable
- **Gestion financière** complète
- **Enregistrement des paiements**
- **Rapports financiers**
- **Permissions** : Accès complet au module finance

#### Surveillant
- **Gestion de la discipline**
- **Pointage de présence**
- **Suivi des absences**
- **Permissions** : Accès aux modules de discipline et élèves

### Système de Permissions Granulaires

#### Format JSON des Permissions
```json
{
  "students": ["read", "create", "edit", "delete"],
  "users": ["read", "create", "edit", "delete"],
  "finance": ["read", "create", "edit", "delete"],
  "academic": ["read", "create", "edit", "delete"],
  "reports": ["read", "create", "edit", "delete"],
  "settings": ["read", "create", "edit", "delete"]
}
```

#### Actions Disponibles
- `read` : Lire/consulter
- `create` : Créer/ajouter
- `edit` : Modifier
- `delete` : Supprimer

---

## 👥 Gestion des Élèves

### Processus d'Inscription

#### 1. Ajout Direct d'Élève
- **Création automatique** de la demande d'admission
- **Statut initial** : "non-évalué"
- **Génération automatique** des numéros uniques
- **Processus d'évaluation** ultérieur obligatoire

#### 2. Réinscription Annuelle
- **Liste automatique** des élèves de l'année précédente
- **Promotion automatique** selon le niveau
- **Sélection multiple** pour réinscription en lot
- **Vérification des doublons**

#### 3. Gestion des Statuts
- **Changement de statut** avec motifs prédéfinis
- **Historique complet** des modifications
- **Traçabilité** de toutes les actions
- **Validation** obligatoire avant changement

### Système de Suivi des Élèves

#### Étapes du Processus
1. **Demande d'Admission** : Enregistrement des informations
2. **Évaluation** : Tests et entretiens
3. **Acceptation** : Décision d'admission
4. **Inscription** : Finalisation du processus
5. **Gestion** : Suivi de la scolarité
6. **Transfert/Sortie** : Fin de scolarité

#### Workflow
```
Demande d'Admission → Vérification Documents → Évaluation → 
Décision d'Admission → Inscription → Suivi Scolaire → 
Transfert/Sortie → Archivage Dossier
```

---

## 💰 Module Financier

### Gestion des Devises

#### Devises Supportées
- **CDF** (Franc Congolais) - Devise par défaut
- **USD** (Dollar Américain)
- **EUR** (Euro)

#### Conversion Automatique
- **Taux de conversion** configurables
- **Calcul automatique** des équivalents
- **Affichage double** : montant original + équivalent
- **Mise à jour** des taux selon les fluctuations

### Types de Frais Scolaires

#### Configuration
- **Types personnalisables** par année scolaire
- **Priorités** configurables
- **Activation/désactivation** des types
- **Descriptions** détaillées

#### Gestion des Paiements
- **Enregistrement** avec sélection de devise
- **Validation** des règles de priorité
- **Suivi des soldes** en temps réel
- **Génération** de reçus

### Rapports Financiers

#### Types de Rapports
- **Rapports quotidiens** des nouveaux paiements
- **Rapports hebdomadaires** de progression
- **Rapports mensuels** de synthèse
- **Rapports annuels** de performance

#### Métriques Disponibles
- **Total des paiements** par période
- **Répartition par type** de frais
- **Taux de recouvrement** par classe
- **Évolution** des paiements dans le temps

---

## 📚 Module Académique

### Gestion des Classes
- **Création** et configuration des classes
- **Attribution** des enseignants
- **Gestion** des effectifs
- **Historique** des promotions

### Système d'Évaluations
- **Types d'évaluations** configurables
- **Saisie des notes** par matière
- **Calcul automatique** des moyennes
- **Bulletins** de notes

### Gestion des Absences
- **Pointage** de présence
- **Suivi** des absences
- **Rapports** de fréquentation
- **Alertes** automatiques

---

## 🎫 Cartes d'Élèves

### Génération Automatique
- **Lors de l'inscription** : Carte créée automatiquement
- **Lors de la réinscription** : Nouvelle carte pour l'année suivante
- **Génération manuelle** : Pour des élèves ou classes spécifiques

### Contenu des Cartes
- **Informations de l'élève** : Nom, matricule, classe, année
- **Photo** de l'élève (si disponible)
- **QR Code** avec données de l'élève
- **Design personnalisable** : Couleurs, logo, format

### QR Code et Fonctionnalités
- **Pointage de présence** : Scanner pour marquer la présence
- **Consultation du solde** : Vérifier les frais scolaires
- **Informations élève** : Accès rapide aux données
- **Format standard** : Compatible avec tous les lecteurs

### Modèle RDC Officiel
- **Conformité** aux standards nationaux
- **En-tête officiel** complet
- **Emblèmes nationaux** (drapeau, emblème)
- **Structure** conforme au modèle officiel
- **Fond blanc** comme spécifié

---

## 📄 Impression et Export

### Formats Supportés
- **Impression directe** avec mise en page optimisée
- **Export PDF** avec jsPDF et html2canvas
- **Export Excel** (.xls) compatible
- **Export CSV** avec encodage UTF-8
- **Aperçu avant impression** dans une modal

### Utilisation
```php
// Méthode simple avec le composant PHP
showExportButtons('mon-element', 'Mon Document', 'mon-fichier', 'mon-tableau');

// Configuration avancée
renderExportButtons([
    'element_id' => 'rapport-content',
    'table_id' => 'rapport-table',
    'title' => 'Rapport Mensuel',
    'filename' => 'rapport-mensuel',
    'show_print' => true,
    'show_preview' => true,
    'show_pdf' => true,
    'show_excel' => true,
    'show_csv' => true
]);
```

### Styles d'Impression
```css
.no-print          /* Masquer à l'impression */
.print-only        /* Afficher uniquement à l'impression */
.page-break        /* Saut de page avant */
.page-break-after  /* Saut de page après */
.page-break-inside-avoid /* Éviter les coupures */
```

---

## ⚙️ Installation et Configuration

### Prérequis
- **PHP** 7.4 ou supérieur
- **MySQL** 5.7 ou supérieur
- **Apache** ou Nginx
- **Extensions PHP** : PDO, GD, mbstring, json

### Installation
1. **Télécharger** les fichiers du projet
2. **Configurer** la base de données dans `config/database.php`
3. **Exécuter** le script d'installation : `install.php`
4. **Configurer** les paramètres dans `config/config.php`
5. **Tester** l'installation avec `setup.php`

### Configuration Initiale
1. **Créer** le compte administrateur
2. **Configurer** l'année scolaire active
3. **Définir** les classes et niveaux
4. **Configurer** les types de frais
5. **Paramétrer** les devises

### Migrations
```bash
# Exécuter les migrations
http://votre-domaine/educ-sinfinity/install-roles-with-permissions.php

# Tester le système
http://votre-domaine/educ-sinfinity/test-permissions-system.php
```

---

## 🔒 Sécurité

### Authentification
- **Connexion sécurisée** avec validation
- **Sessions** avec expiration automatique
- **Protection** contre les attaques par force brute
- **Logs** de connexion et déconnexion

### Autorisation
- **Système de rôles** granulaires
- **Vérification** des permissions à chaque action
- **Protection** des routes sensibles
- **Audit trail** complet

### Protection des Données
- **Validation** côté serveur et client
- **Protection** contre les injections SQL (PDO)
- **Échappement** des données utilisateur
- **Chiffrement** des mots de passe

### Bonnes Pratiques
1. **Toujours vérifier** les permissions avant d'afficher du contenu
2. **Utiliser** les fonctions de vérification au lieu de vérifications manuelles
3. **Logger** les tentatives d'accès non autorisées
4. **Tester régulièrement** les permissions après les modifications

---

## 🔧 Maintenance

### Sauvegarde
- **Sauvegarde automatique** quotidienne
- **Sauvegarde** avant mise à jour
- **Récupération** en cas de problème
- **Archivage** des anciennes sauvegardes

### Monitoring
- **Surveillance** des performances
- **Alertes** en cas d'erreur
- **Logs** de système
- **Métriques** d'utilisation

### Mises à Jour
- **Gestion** des versions
- **Migration automatique** des données
- **Tests** de compatibilité
- **Rollback** en cas de problème

### Nettoyage
- **Nettoyage** des anciennes données
- **Archivage** des données obsolètes
- **Optimisation** de la base de données
- **Suppression** des fichiers temporaires

---

## 📞 Support

### Documentation
- **Guide utilisateur** complet
- **Tutoriels vidéo** des fonctionnalités
- **FAQ** en ligne
- **Base de connaissances** complète

### Formation
- **Sessions d'initiation** pour nouveaux utilisateurs
- **Formation avancée** pour les administrateurs
- **Support technique** en ligne et par téléphone
- **Communauté** d'utilisateurs et forum

### Contact
- **Email** : support@educ-sinfinity.cd
- **Téléphone** : +243 XXX XXX XXX
- **Chat en ligne** : Disponible sur le portail
- **Tickets** : Système de tickets intégré

### Dépannage
1. **Consulter** les logs d'erreur
2. **Vérifier** la documentation technique
3. **Contacter** l'équipe de développement
4. **Consulter** les forums de support

---

## 🚀 Évolutions Futures

### Fonctionnalités Prévues
- **API REST** pour l'intégration externe
- **Notifications push** en temps réel
- **Workflow automatisé** d'évaluation
- **Intégration** avec les systèmes externes
- **Analytics avancés** et prédictifs

### Améliorations Techniques
- **Cache Redis** pour les performances
- **Indexation** avancée de la base de données
- **Compression** des données d'export
- **Monitoring** des performances en temps réel

### Permissions Avancées
- **Permissions conditionnelles** basées sur des critères spécifiques
- **Permissions temporelles** avec dates de début et fin
- **Permissions hiérarchiques** avec héritage
- **Permissions par enregistrement** au niveau des données

---

## 📊 Statistiques et Métriques

### Indicateurs Clés
- **Nombre d'élèves** inscrits par année
- **Taux d'acceptation** des demandes d'admission
- **Taux de recouvrement** des frais scolaires
- **Fréquentation** des élèves
- **Performance** académique par classe

### Rapports Disponibles
- **Rapports financiers** détaillés
- **Statistiques d'admission** par période
- **Rapports de présence** des élèves
- **Analyses** de performance académique
- **Rapports** de gestion administrative

---

## 🎯 Conclusion

**Educ-Sinfinity** est un système de gestion scolaire complet et moderne, spécialement conçu pour répondre aux besoins des établissements scolaires de la République Démocratique du Congo. Avec ses fonctionnalités avancées, son interface intuitive et sa conformité aux standards nationaux, il offre une solution complète pour la gestion efficace de tous les aspects de la vie scolaire.

Le système continue d'évoluer avec de nouvelles fonctionnalités et améliorations, garantissant ainsi une solution durable et adaptée aux besoins changeants des établissements scolaires.

---

**Version** : 1.0  
**Date** : Janvier 2025  
**Auteur** : Équipe de développement Educ-Sinfinity  
**Pays** : République Démocratique du Congo 🇨🇩  
**Contact** : support@educ-sinfinity.cd
