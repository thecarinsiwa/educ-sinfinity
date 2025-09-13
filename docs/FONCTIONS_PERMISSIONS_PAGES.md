# Fonctions de Gestion des Permissions de Pages

Ce document explique comment utiliser les nouvelles fonctions créées dans `includes/functions.php` pour gérer les permissions d'accès aux pages en utilisant les données de la base de données de la table `roles`.

## Fonctions Disponibles

### 1. `grantPagePermissions($role_nom, $module, $pages)`

**Description :** Accorde des permissions d'accès à des pages pour un rôle spécifique.

**Paramètres :**
- `$role_nom` (string) : Nom du rôle dans la base de données
- `$module` (string) : Nom du module (ex: 'academic', 'students', 'finance')
- `$pages` (array) : Liste des pages avec leurs permissions

**Retour :** `bool` - True si succès, False si erreur

**Exemple d'utilisation :**
```php
$academic_pages = [
    'index' => ['name' => 'Tableau de bord', 'permissions' => ['read']],
    'classes' => [
        'name' => 'Classes',
        'pages' => [
            'index' => ['name' => 'Liste classes', 'permissions' => ['read']],
            'add' => ['name' => 'Ajouter classe', 'permissions' => ['create']],
            'edit' => ['name' => 'Modifier classe', 'permissions' => ['edit']]
        ]
    ]
];

$result = grantPagePermissions('enseignant', 'academic', $academic_pages);
```

### 2. `revokePagePermissions($role_nom, $module, $pages = [])`

**Description :** Révoque des permissions d'accès à des pages pour un rôle spécifique.

**Paramètres :**
- `$role_nom` (string) : Nom du rôle dans la base de données
- `$module` (string) : Nom du module
- `$pages` (array) : Liste des pages à révoquer (optionnel, si vide révoque tout le module)

**Retour :** `bool` - True si succès, False si erreur

**Exemples d'utilisation :**
```php
// Révoquer des pages spécifiques
revokePagePermissions('enseignant', 'academic', ['classes', 'subjects']);

// Révoquer tout le module
revokePagePermissions('enseignant', 'academic');
```

### 3. `getRolePermissions($role_nom)`

**Description :** Obtient toutes les permissions d'un rôle spécifique.

**Paramètres :**
- `$role_nom` (string) : Nom du rôle dans la base de données

**Retour :** `array|false` - Permissions du rôle ou False si erreur

**Exemple d'utilisation :**
```php
$permissions = getRolePermissions('enseignant');
if ($permissions && isset($permissions['academic'])) {
    echo "Le rôle a des permissions pour le module academic";
}
```

### 4. `roleHasPagePermission($role_nom, $module, $page, $action, $subpage = null)`

**Description :** Vérifie si un rôle a des permissions pour un module/page/action spécifique.

**Paramètres :**
- `$role_nom` (string) : Nom du rôle dans la base de données
- `$module` (string) : Nom du module
- `$page` (string) : Nom de la page
- `$action` (string) : Action à vérifier
- `$subpage` (string) : Nom de la sous-page (optionnel)

**Retour :** `bool` - True si le rôle a la permission, False sinon

**Exemples d'utilisation :**
```php
// Vérifier une permission directe
$has_permission = roleHasPagePermission('enseignant', 'academic', 'classes', 'read');

// Vérifier une permission de sous-page
$has_permission = roleHasPagePermission('enseignant', 'academic', 'classes', 'create', 'add');
```

### 5. `syncModulePermissions($module, $module_pages)`

**Description :** Synchronise les permissions d'un module pour tous les rôles actifs.

**Paramètres :**
- `$module` (string) : Nom du module
- `$module_pages` (array) : Structure des pages du module (format detailed-permissions)

**Retour :** `array` - Résultat de la synchronisation par rôle

**Exemple d'utilisation :**
```php
$finance_module = [
    'name' => 'Gestion Financière',
    'pages' => [
        'index' => ['name' => 'Tableau de bord', 'permissions' => ['read']],
        'payments' => [
            'name' => 'Paiements',
            'pages' => [
                'index' => ['name' => 'Liste paiements', 'permissions' => ['read']],
                'add' => ['name' => 'Nouveau paiement', 'permissions' => ['create']]
            ]
        ]
    ]
];

$results = syncModulePermissions('finance', $finance_module);
foreach ($results as $role_name => $result) {
    echo "Rôle $role_name : " . ($result['success'] ? 'Succès' : 'Erreur') . "\n";
}
```

## Structure des Permissions

Les permissions sont stockées dans la base de données au format JSON dans la colonne `permissions` de la table `roles` :

```json
{
    "academic": {
        "name": "Gestion Académique",
        "pages": {
            "index": {
                "name": "Tableau de bord",
                "permissions": ["read"]
            },
            "classes": {
                "name": "Classes",
                "pages": {
                    "index": {
                        "name": "Liste classes",
                        "permissions": ["read"]
                    },
                    "add": {
                        "name": "Ajouter classe",
                        "permissions": ["create"]
                    }
                }
            }
        }
    }
}
```

## Actions Disponibles

Les actions standard disponibles sont :
- `read` : Lecture/consultation
- `create` : Création/ajout
- `edit` : Modification
- `delete` : Suppression
- `export` : Exportation

## Gestion des Erreurs

Toutes les fonctions incluent une gestion d'erreur robuste :
- Vérification de l'existence du rôle
- Validation des données JSON
- Logs d'erreur dans les logs PHP
- Retour de valeurs booléennes pour indiquer le succès/échec

## Exemples d'Utilisation Pratique

### Créer un nouveau module avec permissions
```php
$new_module_pages = [
    'index' => ['name' => 'Tableau de bord', 'permissions' => ['read']],
    'manage' => [
        'name' => 'Gestion',
        'pages' => [
            'add' => ['name' => 'Ajouter', 'permissions' => ['create']],
            'edit' => ['name' => 'Modifier', 'permissions' => ['edit']],
            'delete' => ['name' => 'Supprimer', 'permissions' => ['delete']]
        ]
    ]
];

// Accorder à tous les rôles
$results = syncModulePermissions('nouveau_module', $new_module_pages);
```

### Vérifier les permissions avant d'afficher une interface
```php
// Vérifier si l'utilisateur peut créer des classes
if (roleHasPagePermission($_SESSION['user_role'], 'academic', 'classes', 'create', 'add')) {
    echo '<a href="classes/add.php" class="btn btn-primary">Nouvelle classe</a>';
}
```

### Révoquer des permissions temporairement
```php
// Désactiver temporairement la suppression pour les enseignants
revokePagePermissions('enseignant', 'academic', ['classes', 'subjects']);
```

## Notes Importantes

1. **Sécurité** : Ces fonctions modifient directement la base de données. Assurez-vous de valider les entrées avant utilisation.

2. **Performance** : Les fonctions utilisent des requêtes SQL optimisées et mettent en cache les résultats quand c'est possible.

3. **Compatibilité** : Ces fonctions sont compatibles avec le système de permissions existant et peuvent être utilisées conjointement.

4. **Logs** : Toutes les opérations sont loggées pour faciliter le débogage et l'audit.

5. **Transactions** : Les opérations de mise à jour sont atomiques et sécurisées.
