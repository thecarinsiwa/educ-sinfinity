# Educ-Sinfinity - Système de Gestion Scolaire

## Description

Educ-Sinfinity est une application web complète de gestion d'établissement scolaire développée spécialement pour les écoles de la République Démocratique du Congo. Cette application offre une solution moderne et intuitive pour gérer tous les aspects d'un établissement scolaire.

## Fonctionnalités

### Modules Principaux

1. **Gestion des Élèves**
   - Inscriptions et dossiers élèves
   - Transferts et historique
   - Photos et informations personnelles
   - Gestion des parents/tuteurs

2. **Gestion du Personnel**
   - Enseignants et personnel administratif
   - Système de paie
   - Gestion des horaires

3. **Gestion Académique**
   - Classes et niveaux
   - Matières et programmes
   - Emplois du temps

4. **Évaluations et Notes**
   - Système de notation
   - Bulletins automatisés
   - Examens et compositions
   - Calcul des moyennes

5. **Gestion Financière**
   - Frais scolaires
   - Paiements et reçus
   - Comptabilité de base

6. **Bibliothèque**
   - Gestion des livres
   - Système d'emprunts

7. **Discipline**
   - Sanctions et avertissements
   - Suivi comportemental

8. **Communication**
   - Messages aux parents
   - Circulaires et annonces

9. **Rapports et Statistiques**
   - Tableaux de bord
   - Rapports personnalisés
   - Statistiques en temps réel

### Modules Avancés

- **Internat/Pensionnat** - Gestion de l'hébergement et des repas
- **Transport Scolaire** - Circuits et véhicules
- **Inventaire** - Matériel et fournitures
- **Santé Scolaire** - Infirmerie et vaccinations
- **Activités Parascolaires** - Clubs et sports
- **Examens d'État** - TENAFEP, Exetat

## Technologies Utilisées

- **Backend**: PHP 7.4+
- **Frontend**: HTML5, CSS3, JavaScript (ES6+)
- **Framework CSS**: Bootstrap 5.3
- **Base de données**: MySQL 8.0+
- **Icônes**: Font Awesome 6.4
- **Graphiques**: Chart.js
- **Tables**: DataTables
- **Notifications**: SweetAlert2

## Prérequis

- Serveur web (Apache/Nginx)
- PHP 7.4 ou supérieur
- MySQL 8.0 ou supérieur
- Extensions PHP requises:
  - PDO
  - PDO_MySQL
  - GD (pour les images)
  - mbstring
  - fileinfo

## Installation

### 1. Cloner le projet

```bash
git clone https://github.com/votre-repo/educ-sinfinity.git
cd educ-sinfinity
```

### 2. Configuration de la base de données

1. Créer une base de données MySQL
2. Importer le schéma depuis `database/schema.sql`
3. Configurer les paramètres dans `config/database.php`

```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'educ_sinfinity');
define('DB_USER', 'votre_utilisateur');
define('DB_PASS', 'votre_mot_de_passe');
```

### 3. Configuration des permissions

```bash
chmod 755 uploads/
chmod 755 assets/
```

### 4. Accès à l'application

- URL: `http://votre-domaine/educ-sinfinity`
- Utilisateur par défaut: `admin`
- Mot de passe par défaut: `admin123`

## Structure du Projet

```
educ-sinfinity/
├── config/                 # Configuration
│   ├── database.php        # Configuration BDD
│   └── config.php          # Configuration générale
├── includes/               # Fichiers inclus
│   ├── header.php          # En-tête
│   ├── footer.php          # Pied de page
│   └── functions.php       # Fonctions utilitaires
├── assets/                 # Ressources statiques
│   ├── css/               # Styles CSS
│   ├── js/                # Scripts JavaScript
│   └── images/            # Images
├── modules/               # Modules de l'application
│   ├── students/          # Gestion des élèves
│   ├── personnel/         # Gestion du personnel
│   ├── academic/          # Gestion académique
│   ├── evaluations/       # Évaluations
│   ├── finance/           # Gestion financière
│   └── ...               # Autres modules
├── database/              # Base de données
│   └── schema.sql         # Schéma de la BDD
├── uploads/               # Fichiers uploadés
├── auth/                  # Authentification
│   ├── login.php          # Connexion
│   └── logout.php         # Déconnexion
├── dashboard.php          # Tableau de bord
└── index.php             # Page d'accueil
```

## Utilisation

### Connexion

1. Accédez à l'application via votre navigateur
2. Utilisez les identifiants par défaut (admin/admin123)
3. Changez le mot de passe lors de la première connexion

### Gestion des Utilisateurs

L'application supporte plusieurs rôles:
- **Administrateur**: Accès complet
- **Directeur**: Gestion générale
- **Enseignant**: Gestion des notes et élèves
- **Secrétaire**: Gestion administrative
- **Comptable**: Gestion financière

### Ajout d'Élèves

1. Aller dans "Gestion des Élèves"
2. Cliquer sur "Nouvel élève"
3. Remplir le formulaire complet
4. Assigner à une classe
5. Enregistrer

## Sécurité

- Authentification par session
- Validation des données côté serveur
- Protection contre les injections SQL (PDO)
- Contrôle d'accès basé sur les rôles
- Upload sécurisé des fichiers
- Protection CSRF

## Sauvegarde

Il est recommandé de sauvegarder régulièrement:
- La base de données MySQL
- Le dossier `uploads/`
- Les fichiers de configuration

## Support

Pour toute question ou problème:
- Email: support@educ-sinfinity.cd
- Documentation: [Wiki du projet]
- Issues: [GitHub Issues]

## Licence

Ce projet est sous licence MIT. Voir le fichier `LICENSE` pour plus de détails.

## Contributeurs

- Développeur principal: [Votre nom]
- Design UI/UX: [Nom du designer]
- Tests: [Nom du testeur]

## Changelog

### Version 1.0.0 (2024-01-01)
- Version initiale
- Modules de base implémentés
- Interface utilisateur responsive
- Système d'authentification

---

**République Démocratique du Congo** - Système de gestion scolaire moderne et efficace.
