# Guide d'Utilisation des Modèles CSV

## Vue d'ensemble

Les modèles CSV permettent d'importer en masse des candidatures d'admission ou des inscriptions d'élèves dans le système de gestion scolaire.

## Types de Modèles

### 1. Modèle Candidatures (modele-candidatures.csv)
- **Usage** : Import de demandes d'admission
- **Résultat** : Création de candidatures en attente de validation
- **Avantage** : Permet de traiter les demandes avant inscription définitive

### 2. Modèle Élèves (modele-eleves.csv)
- **Usage** : Inscription directe d'élèves
- **Résultat** : Création immédiate d'élèves actifs
- **Avantage** : Inscription rapide sans processus de validation

## Étapes d'Import

### Étape 1 : Préparation des Données
1. Téléchargez le modèle approprié
2. Remplissez les colonnes avec les données des élèves
3. Vérifiez la cohérence des données

### Étape 2 : Validation des Données
- **Classes** : Vérifiez que les noms de classes existent dans le système
- **Dates** : Format YYYY-MM-DD obligatoire
- **Sexe** : M ou F uniquement
- **Téléphones** : Format international recommandé (+243)

### Étape 3 : Import
1. Accédez à la page d'import en lot
2. Sélectionnez le type d'import (Candidatures ou Élèves)
3. Uploadez votre fichier CSV
4. Cliquez sur "Importer les données"

## Gestion des Erreurs

### Erreurs Communes
- **Classe non trouvée** : Vérifiez le nom exact de la classe
- **Format de date invalide** : Utilisez YYYY-MM-DD
- **Données obligatoires manquantes** : Remplissez tous les champs requis
- **Élève déjà existant** : Vérifiez les doublons

### Solutions
1. Consultez le rapport d'erreurs après import
2. Corrigez les données dans le fichier CSV
3. Relancez l'import

## Bonnes Pratiques

### Avant l'Import
- Sauvegardez vos données
- Testez avec un petit échantillon
- Vérifiez les noms de classes disponibles

### Pendant l'Import
- Surveillez les messages d'erreur
- Notez les lignes problématiques
- Interrompez si trop d'erreurs

### Après l'Import
- Vérifiez les données importées
- Traitez les candidatures si nécessaire
- Archivez les fichiers d'import

## Support Technique

En cas de problème :
1. Consultez ce guide
2. Vérifiez les logs d'erreur
3. Contactez l'administrateur système

## Exemples de Données

### Format de Date
- ✅ Correct : 2010-05-15
- ❌ Incorrect : 15/05/2010, 15-05-2010

### Format de Sexe
- ✅ Correct : M, F
- ❌ Incorrect : Masculin, Féminin, m, f

### Format de Téléphone
- ✅ Correct : +243 123 456 789
- ✅ Correct : 0123456789
- ❌ Incorrect : (012) 345-6789

### Noms de Classes
- Vérifiez la liste exacte dans le système
- Respectez la casse et les accents
- Exemple : "6ème Primaire" et non "6eme Primaire"
