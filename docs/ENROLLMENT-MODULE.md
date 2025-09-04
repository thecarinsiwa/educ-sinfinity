# Module de Gestion des Inscriptions - Nouvelle Année Scolaire

## Vue d'ensemble

Ce module permet de gérer efficacement les inscriptions des élèves lors du lancement d'une nouvelle année scolaire. Il offre des fonctionnalités complètes pour la réinscription des élèves existants et l'inscription de nouveaux élèves.

## Fonctionnalités principales

### 1. Réinscription des Élèves de l'Année Précédente

- **Liste automatique** : Tous les élèves inscrits l'année précédente sont automatiquement listés
- **Sélection multiple** : Possibilité de sélectionner un ou plusieurs élèves pour la réinscription
- **Promotion automatique** : Le système détermine automatiquement la nouvelle classe selon le niveau
- **Vérification des doublons** : Prévention des inscriptions multiples pour la même année
- **Statuts visuels** : Indication claire des élèves déjà inscrits vs en attente

### 2. Inscription de Nouveaux Élèves

- **Formulaire complet** : Saisie des informations personnelles de l'élève
- **Génération automatique de matricule** : Création d'un numéro unique
- **Sélection de classe** : Choix parmi les classes disponibles de la nouvelle année
- **Frais d'inscription** : Récupération automatique des frais selon la classe

### 3. Historique des Inscriptions

- **Suivi par année** : Consultation des inscriptions pour chaque année scolaire
- **Filtres avancés** : Recherche par statut, classe, nom, etc.
- **Statistiques détaillées** : Vue d'ensemble par année avec compteurs
- **Gestion des statuts** : Modification des statuts (transféré, abandonné)
- **Traçabilité complète** : Historique des changements avec motifs et dates

### 4. Gestion des Statuts

- **Changement de statut** : Processus sécurisé pour modifier le statut d'un élève
- **Motifs prédéfinis** : Sélection parmi des motifs standardisés
- **Validation** : Confirmation obligatoire avant tout changement
- **Historique** : Enregistrement de tous les changements avec traçabilité

## Structure des fichiers

```
modules/students/
├── enrollment.php              # Page principale des inscriptions
├── enrollment-history.php      # Historique des inscriptions
├── change-status.php          # Gestion des changements de statut
└── list.php                   # Liste des élèves (modifié)

database/migrations/
└── add_historique_changements_statut.sql  # Migration SQL
```

## Tables de base de données

### Table `inscriptions`
- `id` : Identifiant unique
- `eleve_id` : Référence vers l'élève
- `classe_id` : Référence vers la classe
- `annee_scolaire_id` : Référence vers l'année scolaire
- `date_inscription` : Date d'inscription
- `frais_inscription_paye` : Montant des frais payés
- `status` : Statut (inscrit, transféré, abandonné)
- `created_at`, `updated_at` : Timestamps

### Table `historique_changements_statut`
- `id` : Identifiant unique
- `inscription_id` : Référence vers l'inscription
- `eleve_id` : Référence vers l'élève
- `ancien_statut` : Statut précédent
- `nouveau_statut` : Nouveau statut
- `motif` : Motif du changement
- `date_effet` : Date d'effet
- `commentaire` : Commentaire additionnel
- `user_id` : Utilisateur ayant effectué le changement
- `created_at` : Timestamp de création

## Logique de promotion automatique

### Maternelle
- 1ère Maternelle → 2ème Maternelle
- 2ème Maternelle → 3ème Maternelle
- 3ème Maternelle → 1ère Primaire A

### Primaire
- 1ère Primaire A → 2ème Primaire A
- 2ème Primaire A → 3ème Primaire A
- 3ème Primaire A → 4ème Primaire A
- 4ème Primaire A → 5ème Primaire A
- 5ème Primaire A → 6ème Primaire A
- 6ème Primaire A → 1ère Secondaire A

### Secondaire
- 1ère Secondaire A → 2ème Secondaire A
- 2ème Secondaire A → 3ème Secondaire A
- 3ème Secondaire A → 4ème Secondaire A
- 4ème Secondaire A → 5ème Secondaire A
- 5ème Secondaire A → 6ème Secondaire A
- 6ème Secondaire A → Fin des études

## Utilisation

### 1. Accès au module
- Navigation : Gestion des Élèves → Outils → Inscriptions Nouvelle Année
- URL directe : `/modules/students/enrollment.php`

### 2. Réinscription en lot
1. Sélectionner les élèves à réinscrire
2. Utiliser les boutons "Tout sélectionner" ou "Tout désélectionner"
3. Cliquer sur "Réinscrire les Élèves Sélectionnés"
4. Confirmer l'action

### 3. Inscription d'un nouvel élève
1. Remplir le formulaire avec les informations requises
2. Sélectionner la classe d'inscription
3. Les frais d'inscription sont automatiquement remplis
4. Cliquer sur "Inscrire l'Élève"

### 4. Consultation de l'historique
1. Accéder à "Historique des Inscriptions"
2. Utiliser les filtres pour affiner la recherche
3. Consulter les détails de chaque inscription
4. Modifier les statuts si nécessaire

## Sécurité et permissions

- **Authentification requise** : Connexion obligatoire
- **Permissions** : Nécessite les droits `students` ou `students_enrollment`
- **Validation des données** : Vérification de l'intégrité des informations
- **Transactions** : Utilisation de transactions SQL pour la cohérence
- **Logging** : Enregistrement de toutes les actions importantes

## Maintenance

### Vérification de l'intégrité des données
```sql
-- Vérifier la cohérence entre les statuts des élèves et des inscriptions
SELECT 
    e.id,
    e.nom,
    e.prenom,
    e.status as statut_eleve,
    i.status as statut_inscription,
    i.annee_scolaire_id
FROM eleves e
LEFT JOIN inscriptions i ON e.id = i.eleve_id 
    AND i.annee_scolaire_id = (SELECT id FROM annees_scolaires WHERE status = 'active' LIMIT 1)
WHERE e.status != 'actif' OR i.status != 'inscrit';
```

### Nettoyage des données
- Les élèves transférés ou abandonnés restent dans la base
- L'historique est conservé pour la traçabilité
- Les statistiques sont mises à jour automatiquement

## Support et assistance

Pour toute question ou problème avec ce module :
1. Vérifier les logs d'erreur
2. Contrôler les permissions utilisateur
3. Vérifier l'intégrité de la base de données
4. Consulter la documentation technique

## Évolutions futures

- Interface d'import en masse des inscriptions
- Génération automatique de certificats de sortie
- Intégration avec le module de facturation
- Rapports avancés sur les mouvements d'élèves
- Notifications automatiques aux parents
