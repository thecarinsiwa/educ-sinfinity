# Amélioration du Système de Paramètres

## Vue d'ensemble

Le système de paramètres a été complètement refondu pour offrir une interface moderne, flexible et performante. Cette amélioration permet une gestion centralisée des paramètres système et des informations d'établissement.

## Nouvelles Fonctionnalités

### 1. Structure de Base de Données Améliorée

#### Table `system_settings` (améliorée)
- **Colonnes ajoutées :**
  - `is_public` : Paramètre visible par tous les utilisateurs
  - `is_required` : Paramètre obligatoire
  - `validation_rule` : Règle de validation (regex, min, max, etc.)
  - `default_value` : Valeur par défaut
  - `help_text` : Texte d'aide pour l'utilisateur
  - `sort_order` : Ordre d'affichage dans l'interface
  - `group_name` : Groupe de paramètres
  - `is_file` : Indique si c'est un fichier uploadé
  - `file_types` : Types de fichiers acceptés
  - `max_file_size` : Taille maximale du fichier en KB

- **Types de paramètres étendus :**
  - `text`, `number`, `boolean`, `email`, `url`, `textarea`, `select`
  - `file`, `color`, `date`, `time`, `datetime`, `password`, `json`

#### Table `etablissements` (améliorée)
- **Nouvelles colonnes :**
  - `slogan` : Slogan de l'établissement
  - `site_web` : Site web officiel
  - `logo` : Chemin vers le logo
  - `favicon` : Chemin vers la favicon
  - `type_enseignement` : Type d'enseignement (maternelle, primaire, secondaire, supérieur, mixte)
  - `directeur_nom`, `directeur_prenom` : Informations du directeur
  - `directeur_telephone`, `directeur_email` : Contact du directeur
  - `annee_creation` : Année de création
  - `numero_agrement` : Numéro d'agrément officiel
  - `is_active` : Établissement actif
  - `is_principal` : Établissement principal
  - `couleur_principale`, `couleur_secondaire` : Couleurs de l'interface
  - `theme` : Thème de l'interface
  - `timezone`, `langue`, `devise` : Paramètres régionaux

#### Table `settings_cache` (nouvelle)
- Cache des paramètres pour améliorer les performances
- Expiration automatique des données en cache

### 2. Gestionnaire de Paramètres Centralisé

#### Classe `SettingsManager`
- **Méthodes principales :**
  - `getSetting($key, $default_value)` : Récupère un paramètre
  - `updateSetting($key, $value, $type)` : Met à jour un paramètre
  - `updateSettings($settings)` : Met à jour plusieurs paramètres
  - `getSettingsByCategory($category)` : Récupère les paramètres d'une catégorie
  - `getAllSettingsGrouped()` : Récupère tous les paramètres groupés
  - `deleteSetting($key)` : Supprime un paramètre
  - `clearCache()` : Vide le cache

#### Fonctions Globales
- `getSetting($key, $default_value)` : Fonction globale pour récupérer un paramètre
- `updateSetting($key, $value)` : Fonction globale pour mettre à jour un paramètre
- `getSettingsByCategory($category)` : Fonction globale pour récupérer par catégorie
- `getAllSettingsGrouped()` : Fonction globale pour récupérer tous les paramètres

### 3. Interface Utilisateur Moderne

#### Organisation en Onglets
1. **Général** : Paramètres de base (nom app, année scolaire, langue, devise, fuseau horaire)
2. **Établissement** : Informations de l'école (nom, adresse, contact, etc.)
3. **Apparence** : Logo, favicon, couleurs, thème
4. **Communication** : Email, SMS, WhatsApp, notifications
5. **Sécurité** : Mode maintenance, backup, droits, mots de passe

#### Fonctionnalités AJAX
- Sauvegarde sans rechargement de page
- Upload de fichiers (logo, favicon) avec prévisualisation
- Notifications en temps réel
- Validation côté client et serveur

#### Gestion des Fichiers
- Upload de logo (JPG, PNG, GIF, SVG - max 2MB)
- Upload de favicon (ICO, PNG, GIF - max 512KB)
- Prévisualisation immédiate
- Validation des types et tailles de fichiers

### 4. Système de Cache

#### Cache en Mémoire
- Cache des paramètres fréquemment utilisés
- Invalidation automatique lors des mises à jour

#### Cache en Base de Données
- Stockage persistant des paramètres mis en cache
- Expiration automatique des données obsolètes
- Nettoyage périodique du cache

## Installation

### 1. Exécuter les Migrations
```bash
# Accéder au script de migration
http://localhost/educ-sinfinity/database/run_settings_migration.php
```

### 2. Mettre à Jour la Page
```bash
# Exécuter le script de mise à jour
http://localhost/educ-sinfinity/admin/update_settings_page.php
```

### 3. Vérifier l'Installation
- Accéder à `admin/settings.php`
- Vérifier que tous les onglets s'affichent correctement
- Tester la sauvegarde des paramètres
- Tester l'upload de fichiers

## Utilisation

### Récupérer un Paramètre
```php
// Récupérer un paramètre avec valeur par défaut
$app_name = getSetting('app_name', 'Mon Application');

// Récupérer un paramètre sans valeur par défaut
$logo = getSetting('logo');
```

### Mettre à Jour un Paramètre
```php
// Mettre à jour un paramètre simple
updateSetting('app_name', 'Nouveau Nom');

// Mettre à jour plusieurs paramètres
$settings = [
    'app_name' => 'Nouveau Nom',
    'primary_color' => '#ff0000',
    'enable_email' => '1'
];
$settings_manager->updateSettings($settings);
```

### Récupérer par Catégorie
```php
// Récupérer tous les paramètres d'une catégorie
$school_settings = getSettingsByCategory('school');

// Récupérer tous les paramètres groupés
$all_settings = getAllSettingsGrouped();
```

## Paramètres par Défaut

### Général
- `app_name` : Nom de l'application
- `current_academic_year` : Année scolaire en cours
- `timezone` : Fuseau horaire
- `language` : Langue par défaut
- `currency` : Devise

### Établissement
- `school_name` : Nom de l'établissement
- `school_slogan` : Slogan
- `school_address` : Adresse complète
- `school_phone` : Téléphone
- `school_email` : Email de contact
- `school_website` : Site web

### Apparence
- `logo` : Logo de l'établissement
- `favicon` : Favicon
- `primary_color` : Couleur principale
- `secondary_color` : Couleur secondaire
- `theme` : Thème de l'interface

### Communication
- `admin_email` : Email administrateur
- `enable_email` : Activer les emails
- `enable_sms` : Activer les SMS
- `enable_notifications` : Activer les notifications
- `whatsapp_number` : Numéro WhatsApp

### Sécurité
- `maintenance_mode` : Mode maintenance
- `backup_retention_days` : Rétention des sauvegardes
- `session_lifetime` : Durée de session
- `max_login_attempts` : Tentatives de connexion max
- `password_min_length` : Longueur minimale du mot de passe

## Avantages

### 1. Flexibilité
- Ajout dynamique de nouveaux paramètres sans modification de la structure SQL
- Types de paramètres extensibles
- Validation personnalisable

### 2. Performance
- Système de cache intégré
- Requêtes optimisées
- Chargement asynchrone des données

### 3. Utilisabilité
- Interface intuitive en onglets
- Sauvegarde AJAX sans rechargement
- Prévisualisation des fichiers
- Notifications en temps réel

### 4. Sécurité
- Validation côté client et serveur
- Protection contre les injections SQL
- Gestion sécurisée des uploads de fichiers

### 5. Maintenabilité
- Code modulaire et réutilisable
- Documentation complète
- Gestion centralisée des paramètres

## Fichiers Créés/Modifiés

### Nouveaux Fichiers
- `includes/settings-manager.php` : Gestionnaire de paramètres
- `admin/settings_new.php` : Nouvelle interface de paramètres
- `assets/css/settings.css` : Styles pour la page de paramètres
- `database/migrations/improve_settings_tables.sql` : Script de migration
- `database/run_settings_migration.php` : Exécuteur de migration
- `admin/update_settings_page.php` : Script de mise à jour

### Fichiers Modifiés
- `admin/settings.php` : Remplacé par la nouvelle version
- `config/config.php` : Ajout de l'inclusion du gestionnaire de paramètres
- `includes/header.php` : Ajout du CSS des paramètres

## Support et Maintenance

### Logs
- Les erreurs sont loggées dans les logs PHP
- Messages d'erreur détaillés pour le débogage

### Sauvegarde
- L'ancienne page est sauvegardée avant remplacement
- Scripts de migration réversibles

### Tests
- Tester tous les onglets de paramètres
- Vérifier la sauvegarde des données
- Tester l'upload de fichiers
- Valider le système de cache

## Conclusion

Cette amélioration transforme complètement la gestion des paramètres en offrant une interface moderne, performante et extensible. Le système est maintenant prêt pour gérer efficacement tous les aspects de configuration de l'application scolaire.
