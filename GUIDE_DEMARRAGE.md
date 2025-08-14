# Guide de Démarrage Rapide - Educ-Sinfinity

## 🚀 Installation Rapide

### 1. Prérequis
- Serveur web (Apache/Nginx) avec PHP 7.4+
- MySQL 8.0+
- Extensions PHP : PDO, PDO_MySQL, GD, mbstring, fileinfo

### 2. Installation
```bash
# 1. Télécharger les fichiers dans votre dossier web
# 2. Créer une base de données MySQL
# 3. Lancer l'installation
http://votre-domaine/educ-sinfinity/install.php
```

### 3. Configuration Initiale
- **Base de données** : Configurez vos paramètres MySQL
- **Administrateur** : Créez votre compte admin
- **École** : Renseignez les informations de votre établissement

## 🔐 Première Connexion

**Identifiants par défaut :**
- Utilisateur : `admin`
- Mot de passe : `admin123` (à changer lors de la première connexion)

## 📋 Premiers Pas

### 1. Configuration de Base

#### Années Scolaires
1. Aller dans **Administration** → **Paramètres**
2. Créer l'année scolaire actuelle (ex: 2023-2024)
3. Définir les dates de début et fin

#### Classes et Niveaux
1. **Gestion Académique** → **Classes**
2. Créer vos classes par niveau :
   - **Maternelle** : Petite Section, Moyenne Section, Grande Section
   - **Primaire** : 1ère à 6ème Primaire
   - **Secondaire** : 1ère à 6ème Secondaire

#### Matières
1. **Gestion Académique** → **Matières**
2. Ajouter les matières par niveau :
   - Français, Mathématiques, Sciences, Histoire, etc.
   - Définir les coefficients

### 2. Gestion du Personnel

#### Ajouter des Enseignants
1. **Gestion du Personnel** → **Ajouter**
2. Remplir les informations personnelles
3. Définir la fonction et la spécialité
4. Créer un compte utilisateur si nécessaire

#### Rôles et Permissions
- **Admin** : Accès complet
- **Directeur** : Gestion générale
- **Enseignant** : Notes et élèves de ses classes
- **Secrétaire** : Inscriptions et administration
- **Comptable** : Gestion financière

### 3. Inscription des Élèves

#### Processus d'Inscription
1. **Gestion des Élèves** → **Nouvel élève**
2. **Informations personnelles** :
   - Nom, prénom, sexe, date de naissance
   - Adresse, téléphone, email
   - Photo (optionnelle)

3. **Informations des parents** :
   - Noms et professions des parents
   - Contacts d'urgence

4. **Informations scolaires** :
   - Numéro de matricule (auto-généré)
   - Classe d'affectation
   - Année scolaire

#### Génération de Matricules
- Format automatique : `STU2024XXXX`
- Personnalisable selon vos besoins

### 4. Gestion Académique

#### Emplois du Temps
1. **Gestion Académique** → **Emplois du temps**
2. Assigner les enseignants aux matières
3. Définir les créneaux horaires
4. Gérer les salles de classe

#### Évaluations
1. **Évaluations et Notes** → **Nouvelle évaluation**
2. Types : Interrogation, Devoir, Examen, Composition
3. Définir les coefficients et barèmes
4. Saisir les notes par classe

### 5. Gestion Financière

#### Configuration des Frais
1. Définir les frais par classe :
   - Frais d'inscription
   - Mensualités
   - Frais d'examen

#### Enregistrement des Paiements
1. **Gestion Financière** → **Nouveau paiement**
2. Sélectionner l'élève
3. Type de paiement et montant
4. Générer automatiquement le reçu

## 🎯 Fonctionnalités Avancées

### Bulletins Automatisés
- Calcul automatique des moyennes
- Génération de bulletins PDF
- Classements par classe

### Rapports et Statistiques
- Tableaux de bord en temps réel
- Statistiques d'inscription
- Rapports financiers
- Analyses de performance

### Communication
- Messages aux parents
- Circulaires et annonces
- Notifications automatiques

## 🔧 Maintenance

### Sauvegardes
```bash
# Base de données
mysqldump -u username -p educ_sinfinity > backup.sql

# Fichiers uploadés
tar -czf uploads_backup.tar.gz uploads/
```

### Mises à jour
1. Sauvegarder la base de données
2. Sauvegarder les fichiers personnalisés
3. Remplacer les fichiers de l'application
4. Exécuter les scripts de migration si nécessaire

## 🆘 Support et Dépannage

### Problèmes Courants

#### Erreur de connexion à la base de données
- Vérifier les paramètres dans `config/database.php`
- Tester la connexion MySQL
- Vérifier les permissions utilisateur

#### Problèmes d'upload de fichiers
- Vérifier les permissions du dossier `uploads/`
- Augmenter `upload_max_filesize` dans PHP
- Vérifier l'espace disque disponible

#### Erreurs de permissions
```bash
chmod 755 uploads/
chmod 644 config/*.php
```

### Logs et Débogage
- Activer les logs d'erreur PHP
- Consulter les logs du serveur web
- Utiliser le fichier `test.php` pour diagnostiquer

## 📞 Contact et Support

- **Email** : support@educ-sinfinity.cd
- **Documentation** : Consultez le README.md
- **Issues** : Signalez les bugs sur GitHub

## 🎓 Formation

### Ressources d'Apprentissage
1. **Vidéos tutoriels** : Formation complète disponible
2. **Documentation utilisateur** : Guide détaillé par module
3. **FAQ** : Questions fréquemment posées

### Formation du Personnel
1. **Administrateurs** : Formation complète (2 jours)
2. **Enseignants** : Formation sur les notes et évaluations (1 jour)
3. **Secrétaires** : Formation sur les inscriptions (1 jour)

## 🔄 Workflow Recommandé

### Début d'Année Scolaire
1. ✅ Créer la nouvelle année scolaire
2. ✅ Configurer les classes et matières
3. ✅ Mettre à jour les frais scolaires
4. ✅ Former le personnel
5. ✅ Lancer les inscriptions

### Pendant l'Année
1. 📝 Saisie régulière des notes
2. 💰 Suivi des paiements
3. 📊 Génération des bulletins
4. 📈 Analyse des statistiques

### Fin d'Année
1. 🎓 Finaliser les résultats
2. 📋 Générer les rapports annuels
3. 💾 Sauvegarder les données
4. 🔄 Préparer l'année suivante

---

**Bonne utilisation d'Educ-Sinfinity !** 🎉

*Pour toute question, n'hésitez pas à consulter la documentation complète ou à contacter le support technique.*
