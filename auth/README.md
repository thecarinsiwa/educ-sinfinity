# Module d'Authentification - Educ-Sinfinity

## 📋 Fonctionnalités

### 🔐 Connexion (`login.php`)
- Authentification sécurisée avec SHA1
- Gestion des tentatives de connexion
- Verrouillage automatique des comptes
- Interface moderne et responsive

### ✍️ Inscription (`signup.php`)
- Création de comptes utilisateur
- **Statut par défaut : INACTIF**
- Validation des données en temps réel
- Chiffrement SHA1 des mots de passe
- Rôles disponibles : enseignant, secrétaire, comptable, surveillant

## 🔄 Processus d'Inscription

### 1. Utilisateur s'inscrit
- Remplit le formulaire d'inscription
- Choisit son rôle souhaité
- Compte créé avec statut **"inactif"**

### 2. Notification Admin
- Alerte dans le tableau de bord
- Badge avec nombre de comptes en attente
- Liste des dernières inscriptions

### 3. Validation Admin
- Page dédiée : `modules/admin/pending-users.php`
- Actions possibles :
  - ✅ **Activer** le compte
  - ❌ **Rejeter** l'inscription (suppression)
  - 📦 **Actions en masse**

### 4. Utilisateur peut se connecter
- Une fois activé par l'admin
- Accès selon les permissions de son rôle

## 🛡️ Sécurité

### Chiffrement
- Mots de passe chiffrés en **SHA1**
- Validation côté serveur et client
- Protection contre les injections SQL

### Contrôle d'Accès
- Comptes inactifs ne peuvent pas se connecter
- Seuls les admins peuvent activer les comptes
- Historique complet des actions

### Validation des Données
- Nom d'utilisateur unique (minimum 3 caractères)
- Mot de passe sécurisé (minimum 6 caractères)
- Format email valide
- Vérification de la correspondance des mots de passe

## 📊 Gestion Administrative

### Page de Gestion (`modules/admin/pending-users.php`)
- **Statistiques** : Total en attente, inscriptions du jour
- **Liste complète** des comptes inactifs
- **Actions individuelles** : Activer/Rejeter
- **Actions en masse** : Sélection multiple
- **Auto-refresh** : Mise à jour automatique toutes les 2 minutes

### Notifications Dashboard
- **Alerte visuelle** pour les admins
- **Compteur** de comptes en attente
- **Aperçu** des dernières inscriptions
- **Lien direct** vers la gestion

## 🔧 Fonctions Utilitaires

### `activateUser($user_id, $activated_by)`
- Active un compte utilisateur
- Enregistre l'action dans l'historique
- Retourne true/false selon le succès

### `deactivateUser($user_id, $deactivated_by)`
- Désactive un compte utilisateur
- Supprime les sessions actives
- Enregistre l'action dans l'historique

### `timeAgo($datetime)`
- Calcule le temps écoulé depuis une date
- Format français (minutes, heures, jours, etc.)
- Utilisé pour l'affichage des inscriptions

## 🎨 Interface Utilisateur

### Design Moderne
- **Gradient coloré** pour l'arrière-plan
- **Cartes transparentes** avec effet blur
- **Animations** au survol des boutons
- **Icônes FontAwesome** contextuelles

### Responsive
- **Mobile-first** design
- **Bootstrap 5** pour la grille
- **Formulaires adaptatifs** sur tous écrans

### UX Améliorée
- **Validation en temps réel** des champs
- **Génération automatique** de suggestions username
- **Affichage/masquage** des mots de passe
- **Messages d'erreur** explicites

## 📁 Structure des Fichiers

```
auth/
├── login.php              # Page de connexion
├── signup.php             # Page d'inscription ⭐ NOUVEAU
├── logout.php             # Déconnexion
└── README.md              # Documentation

modules/admin/
└── pending-users.php      # Gestion des comptes en attente ⭐ NOUVEAU

includes/
└── functions.php          # Fonctions utilitaires mises à jour
```

## 🚀 Utilisation

### Pour les Utilisateurs
1. Accéder à `/auth/signup.php`
2. Remplir le formulaire d'inscription
3. Attendre l'activation par un administrateur
4. Se connecter via `/auth/login.php`

### Pour les Administrateurs
1. Voir les notifications dans le dashboard
2. Accéder à "Comptes en attente" via le menu
3. Activer ou rejeter les inscriptions
4. Gérer les comptes en masse si nécessaire

## ⚙️ Configuration

### Base de Données
- Table `users` avec colonne `status` (actif/inactif)
- Table `user_actions_log` pour l'historique
- Index sur les colonnes critiques

### Permissions
- Seuls les **admins** peuvent gérer les comptes en attente
- Les autres rôles voient seulement leurs propres informations

## 🔍 Dépannage

### Problèmes Courants
1. **Compte non activé** : Contacter un administrateur
2. **Email déjà utilisé** : Utiliser une autre adresse
3. **Username déjà pris** : Choisir un autre nom d'utilisateur

### Logs
- Actions enregistrées dans `user_actions_log`
- Erreurs PHP dans les logs serveur
- Historique complet des activations/rejets

---

**Développé pour Educ-Sinfinity - Gestion Scolaire RDC** 🇨🇩
