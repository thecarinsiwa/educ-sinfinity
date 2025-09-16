# Modèles CSV pour l'Import en Lot

## Modèle Candidatures (modele-candidatures.csv)

Ce modèle est utilisé pour importer des demandes d'admission. Il contient les colonnes suivantes :

### Colonnes obligatoires :
- **Nom** : Nom de famille de l'élève
- **Prenom** : Prénom de l'élève
- **Date_naissance** : Date de naissance au format YYYY-MM-DD
- **Sexe** : M (Masculin) ou F (Féminin)
- **Classe_demandee** : Nom exact de la classe demandée (ex: "6ème Primaire A", "5ème Primaire A", etc.)
- **Telephone_parent** : Numéro de téléphone du parent/tuteur

### Colonnes optionnelles :
- **Nom_pere** : Nom du père
- **Nom_mere** : Nom de la mère
- **Profession_pere** : Profession du père
- **Profession_mere** : Profession de la mère
- **Adresse** : Adresse de résidence
- **Email** : Adresse email de l'élève ou du parent
- **Personne_contact** : Nom de la personne de contact
- **Telephone_contact** : Téléphone de la personne de contact
- **Relation_contact** : Relation avec l'élève (Père, Mère, Tuteur, etc.)
- **Ecole_precedente** : Nom de l'école précédente
- **Classe_precedente** : Classe fréquentée précédemment
- **Annee_precedente** : Année scolaire précédente
- **Moyenne_precedente** : Moyenne obtenue l'année précédente
- **Motif_demande** : Raison de la demande d'admission

## Modèle Élèves (modele-eleves.csv)

Ce modèle est utilisé pour l'inscription directe d'élèves. Il contient les colonnes suivantes :

### Colonnes obligatoires :
- **Nom** : Nom de famille de l'élève
- **Prenom** : Prénom de l'élève
- **Date_naissance** : Date de naissance au format YYYY-MM-DD
- **Sexe** : M (Masculin) ou F (Féminin)
- **Classe** : Nom exact de la classe (ex: "6ème Primaire A", "5ème Primaire A", etc.)
- **Telephone_parent** : Numéro de téléphone du parent/tuteur

### Colonnes optionnelles :
- **Nom_pere** : Nom du père
- **Nom_mere** : Nom de la mère
- **Profession_pere** : Profession du père
- **Profession_mere** : Profession de la mère
- **Adresse** : Adresse de résidence
- **Email** : Adresse email de l'élève ou du parent
- **Personne_contact** : Nom de la personne de contact
- **Telephone_contact** : Téléphone de la personne de contact
- **Relation_contact** : Relation avec l'élève (Père, Mère, Tuteur, etc.)
- **Lieu_naissance** : Lieu de naissance de l'élève

## Instructions d'utilisation

1. **Téléchargez** le modèle approprié selon votre besoin
2. **Remplissez** les colonnes avec les données des élèves
3. **Vérifiez** que :
   - Les noms de classes existent dans le système
   - Les dates sont au format YYYY-MM-DD
   - Les valeurs de sexe sont M ou F
   - Les données obligatoires sont renseignées
4. **Sauvegardez** le fichier au format CSV
5. **Importez** le fichier via l'interface d'import en lot

## Notes importantes

- La première ligne doit contenir les en-têtes (ne pas la modifier)
- Les noms de classes doivent correspondre exactement à ceux du système
- Les dates doivent être au format YYYY-MM-DD
- Les valeurs de sexe doivent être M ou F (majuscules)
- Les numéros de téléphone peuvent inclure le code pays (+243)
- Les emails doivent être valides si renseignés
