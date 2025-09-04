# Gestion des Devises - Module Finance

## Vue d'ensemble

Le module de gestion des devises permet de gérer différentes monnaies utilisées dans l'application et d'effectuer des conversions automatiques lors des opérations financières.

## Fonctionnalités

### 1. Gestion des Devises
- **Création** : Ajouter de nouvelles devises avec code ISO, nom, symbole et taux de conversion
- **Édition** : Modifier les informations des devises existantes
- **Suppression** : Supprimer les devises non utilisées
- **Devise par défaut** : Définir une devise de référence pour l'application

### 2. Conversion Automatique
- **Taux de conversion** : Chaque devise a un taux par rapport à la devise par défaut
- **Calcul automatique** : Le système convertit automatiquement les montants
- **Affichage double** : Montant dans la devise choisie + équivalent en devise par défaut

### 3. Intégration Financière
- **Paiements** : Choisir la devise lors de l'enregistrement
- **Frais scolaires** : Configurer les frais dans différentes devises
- **Rapports** : Affichage des montants convertis automatiquement

## Structure de la Base de Données

### Table `devises`
```sql
CREATE TABLE devises (
    id INT PRIMARY KEY AUTO_INCREMENT,
    code VARCHAR(3) NOT NULL UNIQUE,           -- Code ISO (USD, EUR, CDF)
    nom VARCHAR(100) NOT NULL,                 -- Nom complet de la devise
    symbole VARCHAR(10) NOT NULL,              -- Symbole ($, €, FC)
    taux_conversion DECIMAL(15,6) NOT NULL,    -- Taux par rapport à la devise par défaut
    devise_par_defaut BOOLEAN DEFAULT FALSE,   -- Si c'est la devise de référence
    active BOOLEAN DEFAULT TRUE,               -- Si la devise est utilisable
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP
);
```

### Tables Modifiées
Les tables suivantes ont été étendues avec la gestion des devises :

- **`paiements`** : `devise_id`, `montant_devise_par_defaut`
- **`frais_scolaires`** : `devise_id`, `montant_devise_par_defaut`
- **`paiements_cartes`** : `devise_id`, `montant_devise_par_defaut`

## Utilisation

### 1. Configuration des Devises

#### Accès
1. Aller dans **Finance** → **Devises**
2. Cliquer sur **"Nouvelle Devise"**

#### Paramètres
- **Code ISO** : Code à 3 lettres (ex: USD, EUR, CDF)
- **Nom** : Nom complet de la devise
- **Symbole** : Symbole monétaire ($, €, FC)
- **Taux de conversion** : Taux par rapport à la devise par défaut
- **Devise par défaut** : Cocher si c'est la devise de référence

#### Exemple de Configuration
```
Code: USD
Nom: Dollar Américain
Symbole: $
Taux: 0.000400 (1 USD = 0.0004 CDF)
```

### 2. Enregistrement des Paiements

#### Formulaire de Paiement
1. **Montant** : Saisir le montant dans la devise choisie
2. **Devise** : Sélectionner la devise de paiement
3. **Conversion automatique** : Le montant équivalent en devise par défaut est calculé

#### Affichage
- Montant principal dans la devise choisie
- Montant équivalent en devise par défaut (entre parenthèses)

### 3. Configuration des Frais Scolaires

#### Création des Frais
1. Choisir la classe et le type de frais
2. Saisir le montant dans la devise souhaitée
3. Le système calcule automatiquement l'équivalent en devise par défaut

#### Affichage des Frais
- Montant dans la devise configurée
- Montant équivalent en devise par défaut

## Fonctions PHP Disponibles

### Récupération des Devises
```php
// Obtenir la devise par défaut
$devise_defaut = getDefaultCurrency();

// Obtenir toutes les devises actives
$devises = getActiveCurrencies();

// Obtenir une devise par ID
$devise = getCurrencyById($id);
```

### Conversion de Devises
```php
// Convertir vers la devise par défaut
$montant_defaut = convertToDefaultCurrency($montant, $devise_id);

// Convertir depuis la devise par défaut
$montant_devise = convertFromDefaultCurrency($montant, $devise_id);

// Obtenir le taux de change entre deux devises
$taux = getExchangeRate($from_currency_id, $to_currency_id);
```

### Formatage
```php
// Formater un montant avec sa devise
$formatted = formatCurrency($montant, $devise_id);

// Formater avec symbole ou code
$formatted_symbol = formatCurrency($montant, $devise_id, true);   // Avec symbole
$formatted_code = formatCurrency($montant, $devise_id, false);    // Avec code
```

## Migration et Installation

### Script de Migration
Exécuter le script `migrate-devises.php` à la racine de l'application :

```bash
php migrate-devises.php
```

### Vérification
1. Vérifier que la table `devises` a été créée
2. Vérifier que les colonnes ont été ajoutées aux tables financières
3. Vérifier que les devises de base ont été insérées

## Bonnes Pratiques

### 1. Gestion des Taux de Change
- **Mise à jour régulière** : Actualiser les taux selon les fluctuations du marché
- **Validation** : Vérifier la cohérence des taux de conversion
- **Historique** : Conserver un historique des taux pour audit

### 2. Sécurité
- **Validation des entrées** : Vérifier les montants et taux saisis
- **Contraintes** : Utiliser les contraintes de base de données
- **Permissions** : Restreindre l'accès à la gestion des devises

### 3. Performance
- **Index** : Les colonnes `devise_id` et `code` sont indexées
- **Requêtes optimisées** : Utiliser les jointures appropriées
- **Cache** : Mettre en cache les taux de conversion fréquemment utilisés

## Dépannage

### Problèmes Courants

#### 1. Erreur de Conversion
- Vérifier que la devise existe et est active
- Vérifier que le taux de conversion n'est pas nul
- Vérifier les permissions d'accès à la base de données

#### 2. Devise Non Affichée
- Vérifier que la devise est marquée comme active
- Vérifier que la devise est associée aux opérations
- Vérifier les jointures dans les requêtes

#### 3. Erreurs de Migration
- Vérifier les permissions de base de données
- Vérifier que les tables existent
- Vérifier les contraintes existantes

### Logs et Debug
- Les erreurs sont loggées dans les logs PHP
- Utiliser `error_log()` pour le débogage
- Vérifier les contraintes de clés étrangères

## Évolutions Futures

### Fonctionnalités Prévues
1. **API de taux de change** : Intégration avec des services externes
2. **Historique des taux** : Suivi des variations dans le temps
3. **Devises multiples** : Support de plusieurs devises par opération
4. **Rapports multidevises** : Analyses financières par devise

### Améliorations Techniques
1. **Cache Redis** : Mise en cache des taux de conversion
2. **Validation avancée** : Règles métier plus sophistiquées
3. **Audit trail** : Suivi des modifications de devises
4. **API REST** : Interface programmatique pour la gestion des devises

## Support

Pour toute question ou problème :
1. Consulter les logs d'erreur
2. Vérifier la documentation technique
3. Contacter l'équipe de développement
4. Consulter les forums de support

---

**Version** : 1.0  
**Date** : <?= date('Y-m-d') ?>  
**Auteur** : Équipe de développement Educ-Sinfinity
