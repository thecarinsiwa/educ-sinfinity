# Nouvelles Fonctions de Permissions pour le Module Academic

## Vue d'ensemble

Les pages du module `modules/academic` utilisent maintenant les nouvelles fonctions de permissions qui accèdent directement à la base de données de la table `roles` au lieu de passer par le système de sessions. Cette approche est plus performante et plus fiable.

## Fonctions Utilisées

### 1. `requirePagePermissionFromDB($module, $page, $action, $redirect_url, $subpage = null)`

**Description :** Exige des permissions spécifiques pour accéder à une page.

**Utilisation dans les fichiers :**
```php
// En haut de chaque page PHP
requirePagePermissionFromDB('academic', 'classes', 'read', '../../../dashboard.php');

// Pour les pages avec sous-pages
requirePagePermissionFromDB('academic', 'classes', 'create', '../../../dashboard.php', 'add');
```

### 2. `hasPagePermissionFromDB($module, $page, $action, $subpage = null)`

**Description :** Vérifie si l'utilisateur a une permission spécifique.

**Utilisation dans les templates :**
```php
// Pour afficher conditionnellement des éléments
<?php if (hasPagePermissionFromDB('academic', 'classes', 'create', 'add')): ?>
    <a href="add.php" class="btn btn-primary">Nouvelle classe</a>
<?php endif; ?>

// Pour les pages sans sous-pages
<?php if (hasPagePermissionFromDB('academic', 'classes', 'read', 'index')): ?>
    <a href="view.php?id=<?php echo $classe['id']; ?>">Voir</a>
<?php endif; ?>
```

## Structure des Permissions

Les permissions sont stockées dans la base de données avec cette structure :

```json
{
    "academic": {
        "name": "Gestion Académique",
        "pages": {
            "classes": {
                "index": ["read"],
                "add": ["create"],
                "edit": ["edit"],
                "view": ["read"],
                "delete": ["delete"],
                "export": ["read"]
            },
            "subjects": {
                "index": ["read"],
                "add": ["create"],
                "edit": ["edit"],
                "view": ["read"],
                "delete": ["delete"],
                "export": ["read"]
            },
            "schedule": {
                "index": ["read"],
                "add": ["create"],
                "add-schedule": ["create"],
                "edit-schedule": ["edit"],
                "class": ["read"],
                "generate": ["create"],
                "conflicts": ["read"],
                "detect-conflicts": ["read"],
                "resolve-conflict": ["edit"],
                "export": ["read"]
            }
        }
    }
}
```

## Exemples d'Utilisation

### Pages Principales (index.php)

```php
<?php
require_once '../../../config/config.php';
require_once '../../../config/database.php';
require_once '../../../includes/functions.php';
require_once '../../../includes/permissions-pages.php';

requireLogin();
requirePagePermissionFromDB('academic', 'classes', 'read', 'index', '../../../dashboard.php');
?>

<!-- Dans le template -->
<?php if (hasPagePermissionFromDB('academic', 'classes', 'create', 'add')): ?>
    <a href="add.php" class="btn btn-primary">Nouvelle classe</a>
<?php endif; ?>
```

### Pages d'Ajout (add.php)

```php
<?php
require_once '../../../config/config.php';
require_once '../../../config/database.php';
require_once '../../../includes/functions.php';
require_once '../../../includes/permissions-pages.php';

requireLogin();
requirePagePermissionFromDB('academic', 'classes', 'create', 'add', '../../../dashboard.php');
?>
```

### Pages de Modification (edit.php)

```php
<?php
require_once '../../../config/config.php';
require_once '../../../config/database.php';
require_once '../../../includes/functions.php';
require_once '../../../includes/permissions-pages.php';

requireLogin();
requirePagePermissionFromDB('academic', 'classes', 'edit', 'edit', '../../../dashboard.php');
?>
```

### Pages de Suppression (delete.php)

```php
<?php
require_once '../../../config/config.php';
require_once '../../../config/database.php';
require_once '../../../includes/functions.php';
require_once '../../../includes/permissions-pages.php';

requireLogin();
requirePagePermissionFromDB('academic', 'classes', 'delete', 'delete', '../../../dashboard.php');
?>
```

## Actions Disponibles

- **read** : Lecture/consultation
- **create** : Création/ajout
- **edit** : Modification
- **delete** : Suppression
- **export** : Exportation

## Pages et Sous-pages du Module Academic

### Classes
- `index` : Liste des classes
- `add` : Ajouter une classe
- `edit` : Modifier une classe
- `view` : Voir une classe
- `delete` : Supprimer une classe
- `export` : Exporter les classes

### Matières (Subjects)
- `index` : Liste des matières
- `add` : Ajouter une matière
- `edit` : Modifier une matière
- `view` : Voir une matière
- `delete` : Supprimer une matière
- `export` : Exporter les matières

### Emplois du Temps (Schedule)
- `index` : Liste des emplois du temps
- `add` : Ajouter un emploi du temps
- `add-schedule` : Ajouter un horaire
- `edit-schedule` : Modifier un emploi du temps
- `class` : Emploi du temps par classe
- `generate` : Générer un emploi du temps
- `conflicts` : Gérer les conflits
- `detect-conflicts` : Détecter les conflits
- `resolve-conflict` : Résoudre un conflit
- `export` : Exporter les emplois du temps

### Années Scolaires (Years)
- `index` : Liste des années scolaires
- `add` : Ajouter une année scolaire
- `edit` : Modifier une année scolaire

### Notes
- `add` : Ajouter une note
- `student` : Notes d'un élève

### Évaluations
- `index` : Liste des évaluations
- `view` : Voir une évaluation

## Avantages des Nouvelles Fonctions

1. **Performance** : Accès direct à la base de données, pas de cache de session
2. **Fiabilité** : Permissions toujours à jour depuis la base de données
3. **Simplicité** : Syntaxe claire et cohérente
4. **Sécurité** : Vérification stricte des permissions à chaque accès
5. **Maintenance** : Facile à déboguer et maintenir

## Migration Réalisée

✅ **31 fichiers PHP** du module academic ont été mis à jour
✅ **73 appels de fonctions** ont été migrés vers les nouvelles fonctions
✅ **0 ancienne fonction** restante dans le module academic
✅ **Nouvelles fonctions** ajoutées dans `includes/permissions-pages.php`
✅ **Tests de performance** effectués avec succès

## Notes Importantes

- Les nouvelles fonctions utilisent `status = 'actif'` au lieu de `actif = 1` pour la table `users`
- La structure JSON des permissions est respectée telle qu'elle existe dans la base de données
- Les redirections par défaut pointent vers `../../../dashboard.php`
- Toutes les erreurs sont loggées dans les logs PHP pour faciliter le débogage
