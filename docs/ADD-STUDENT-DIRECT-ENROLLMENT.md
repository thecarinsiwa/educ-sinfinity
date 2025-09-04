# Ajout Direct d'Élève - Demande d'Admission

## Vue d'ensemble

Le module d'ajout direct d'élève permet de créer une demande d'admission et d'ajouter un élève en une seule opération, sans passer par le processus d'évaluation préalable.

## Fonctionnement

### 1. Création de la demande d'admission
- **Statut** : `en_cours_traitement`
- **Priorité** : `normale`
- **Motif** : "Ajout direct - Élève non évalué"
- **Observations** : "Ajout direct sans évaluation préalable"

### 2. Création de l'élève
- **Statut** : `non-evalué`
- **Numéro d'élève** : Généré automatiquement (format: AAAA0001)
- **Numéro de demande** : Généré automatiquement (format: ADMAAAA0001)

### 3. Processus post-création
L'élève avec le statut "non-évalué" devra :
1. Passer par le processus d'évaluation
2. Fournir les documents requis
3. Être approuvé par l'administration
4. Avoir son statut changé vers "actif"

## Migration requise

Avant d'utiliser cette fonctionnalité, exécutez la migration pour ajouter le statut "non-évalué" :

```bash
# Accédez à l'URL de migration
http://localhost/educ-sinfinity/migrate-add-non-evalue-status.php
```

## Structure de la base de données

### Table `eleves`
- Nouveau statut : `non-evalué`
- Valeurs possibles : `actif`, `transfere`, `abandonne`, `diplome`, `non-evalué`

### Table `demandes_admission`
- Statut : `en_cours_traitement`
- Lien vers l'élève créé via `eleve_cree_id`

## Utilisation

1. Accédez à `modules/students/add.php`
2. Remplissez le formulaire avec les informations de l'élève
3. Soumettez le formulaire
4. Le système créera automatiquement :
   - Une demande d'admission
   - Un élève avec le statut "non-évalué"
   - Les numéros d'identification uniques

## Avantages

- **Rapidité** : Ajout d'élève en une seule étape
- **Traçabilité** : Toutes les informations sont enregistrées dans la demande d'admission
- **Flexibilité** : L'évaluation peut être effectuée ultérieurement
- **Conformité** : Respect du processus d'admission standard

## Notes importantes

- L'élève avec le statut "non-évalué" ne peut pas encore participer aux activités académiques
- Une évaluation et une validation sont obligatoires avant l'activation
- Le statut "non-évalué" est temporaire et doit être modifié après évaluation
