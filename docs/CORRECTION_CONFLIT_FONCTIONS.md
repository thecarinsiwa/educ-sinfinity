# Correction du Conflit de Fonctions

## Problème Identifié

**Erreur :** `Fatal error: Cannot redeclare getRolePermissions() (previously declared in C:\laragon\www\educ-sinfinity\includes\functions.php:1159) in C:\laragon\www\educ-sinfinity\includes\permissions.php on line 71`

## Cause du Problème

Il y avait deux fonctions avec le même nom `getRolePermissions()` mais des signatures différentes :

1. **Dans `includes/functions.php` :**
   ```php
   function getRolePermissions($role_nom) // Prend un nom de rôle
   ```

2. **Dans `includes/permissions.php` :**
   ```php
   function getRolePermissions($role_id) // Prend un ID de rôle
   ```

## Solution Appliquée

La fonction dans `includes/permissions.php` a été renommée pour éviter le conflit :

```php
// AVANT
function getRolePermissions($role_id) {
    // ...
}

// APRÈS
function getRolePermissionsById($role_id) {
    // ...
}
```

## Fonctions Disponibles

### 1. `getRolePermissions($role_nom)` (dans includes/functions.php)
- **Paramètre :** Nom du rôle (string)
- **Utilisation :** Pour récupérer les permissions d'un rôle par son nom
- **Exemple :** `getRolePermissions('admin')`

### 2. `getRolePermissionsById($role_id)` (dans includes/permissions.php)
- **Paramètre :** ID du rôle (int)
- **Utilisation :** Pour récupérer les permissions d'un rôle par son ID
- **Exemple :** `getRolePermissionsById(1)`

## Vérification

✅ **Conflit résolu** : Plus d'erreur de redéclaration
✅ **Fonctions disponibles** : Les deux fonctions sont accessibles avec des noms distincts
✅ **Compatibilité maintenue** : Aucun code existant n'est cassé
✅ **Dashboard fonctionnel** : Le dashboard peut maintenant être chargé sans erreur

## Impact

- **Aucun impact négatif** sur le code existant
- **Fonctionnalités préservées** : Toutes les fonctions continuent de fonctionner
- **Séparation claire** : Les deux fonctions ont maintenant des noms explicites
- **Maintenance améliorée** : Plus de confusion entre les deux fonctions

## Recommandations

1. **Utiliser `getRolePermissions($role_nom)`** quand vous connaissez le nom du rôle
2. **Utiliser `getRolePermissionsById($role_id)`** quand vous avez l'ID du rôle
3. **Documenter clairement** quelle fonction utiliser dans chaque contexte
4. **Éviter les conflits futurs** en utilisant des noms de fonctions explicites
