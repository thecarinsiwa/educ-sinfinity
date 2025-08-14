# Module Applications - Gestion des Candidatures

## 📋 Description

Le module **Applications** permet la gestion détaillée des candidatures d'admission dans l'établissement scolaire. Il fait partie du système de gestion des admissions et offre une interface complète pour traiter les demandes d'inscription.

## 🎯 Fonctionnalités

### ✅ Gestion des Candidatures
- **Liste complète** des candidatures avec pagination
- **Filtres avancés** par statut, priorité, classe
- **Recherche** par nom, prénom ou numéro de demande
- **Tri automatique** par priorité et date
- **Modification** des candidatures existantes

### ✅ Affichage Détaillé
- **Informations complètes** du candidat
- **Données des parents** et personnes de contact
- **Historique scolaire** précédent
- **Documents requis** avec statut
- **Informations médicales** et besoins spéciaux

### ✅ Gestion des Statuts
- **En attente** - Demande soumise
- **Acceptée** - Candidature approuvée
- **Refusée** - Candidature rejetée
- **En cours de traitement** - En cours d'évaluation
- **Inscrit** - Élève inscrit définitivement

### ✅ Gestion des Priorités
- **Normale** - Traitement standard
- **Urgente** - Traitement prioritaire
- **Très urgente** - Traitement immédiat

## 📁 Structure des Fichiers

```
modules/students/admissions/applications/
├── index.php              # Page principale avec liste et filtres
├── view.php               # Affichage détaillé d'une candidature
├── add.php                # Formulaire d'ajout de candidature
├── edit.php               # Formulaire de modification de candidature
├── update_status.php      # Mise à jour du statut
└── README.md             # Cette documentation
```

## 🗄️ Base de Données

### Table : `demandes_admission`

**Colonnes principales :**
- `id` - Identifiant unique
- `numero_demande` - Numéro de demande (ex: ADM2025001)
- `nom_eleve`, `prenom_eleve` - Identité du candidat
- `status` - Statut de la demande
- `priorite` - Niveau de priorité
- `classe_demandee_id` - Classe souhaitée
- `telephone_parent` - Contact des parents
- `created_at` - Date de création

**Relations :**
- `classe_demandee_id` → `classes.id`
- `annee_scolaire_id` → `annees_scolaires.id`
- `traite_par` → `users.id`

## 🚀 Utilisation

### Accès au Module
```
http://localhost/educ-sinfinity/modules/students/admissions/applications/
```

### Navigation
1. **Liste des candidatures** - Vue d'ensemble avec filtres
2. **Détails** - Clic sur une candidature pour voir les détails
3. **Ajouter** - Bouton "Nouvelle Candidature"
4. **Actions rapides** - Accepter/Refuser depuis la liste

### Filtres Disponibles
- **Recherche textuelle** - Nom, prénom, numéro
- **Statut** - Tous, en attente, acceptée, refusée, etc.
- **Priorité** - Normale, urgente, très urgente
- **Classe** - Toutes les classes disponibles

## 📊 Statistiques

Le module affiche des statistiques en temps réel :
- **Total** des candidatures
- **En attente** de traitement
- **Acceptées** et **Refusées**
- **Urgentes** nécessitant attention

## 🔧 Configuration

### Permissions Requises
- `students` - Accès complet (ajout, modification, suppression)
- `students_view` - Accès en lecture seule

### Paramètres
- **Pagination** : 20 candidatures par page
- **Recherche** : Auto-submit après 500ms
- **Tri** : Par priorité puis date de création

## 🎨 Interface

### Codes Couleurs
- 🟡 **En attente** (warning)
- 🟢 **Acceptée** (success)
- 🔴 **Refusée** (danger)
- 🔵 **En cours** (info)
- 🟣 **Inscrit** (primary)

### Priorités
- 🔘 **Normale** (secondary)
- 🟡 **Urgente** (warning)
- 🔴 **Très urgente** (danger)

## 📋 Workflow Typique

1. **Soumission** - Nouvelle candidature créée
2. **Modification** - Mise à jour des informations si nécessaire
3. **Évaluation** - Examen du dossier
4. **Décision** - Acceptation ou refus
5. **Inscription** - Si acceptée, création du dossier élève

## 🔗 Intégrations

### Modules Liés
- **Admissions** - Module parent
- **Élèves** - Création automatique après acceptation
- **Classes** - Sélection de la classe demandée
- **Années scolaires** - Gestion par année

### Fonctionnalités Futures
- **Export Excel/PDF** des candidatures
- **Notifications** automatiques
- **Entretiens** programmés
- **Documents** uploadés

## 🛠️ Maintenance

### Scripts de Test
- `test-applications-module.php` - Test complet du module
- `fix-admissions-table.php` - Création/réparation de la table

### Logs et Debugging
- Gestion d'erreurs avec try/catch
- Messages informatifs pour l'utilisateur
- Validation côté serveur et client

## 📞 Support

En cas de problème :
1. Vérifier que la table `demandes_admission` existe
2. Exécuter les scripts de test
3. Vérifier les permissions utilisateur
4. Consulter les logs d'erreur

---

**Module créé pour le système de gestion scolaire - République Démocratique du Congo**
