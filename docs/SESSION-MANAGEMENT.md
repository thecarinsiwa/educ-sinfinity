# Gestion des Sessions - Documentation

## Problème résolu

L'erreur `Warning: Cannot modify header information - headers already sent` se produisait lorsque la session utilisateur expirait et qu'une tentative de redirection était faite après que du contenu HTML avait déjà été envoyé au navigateur.

## Solution implémentée

### 1. Fonction `redirectTo()` améliorée

La fonction `redirectTo()` dans `config/config.php` a été améliorée pour détecter si les headers ont déjà été envoyés :

```php
function redirectTo($url) {
    // Vérifier si les headers ont déjà été envoyés
    if (headers_sent()) {
        // Utiliser JavaScript pour la redirection
        echo "<script>window.location.href = '" . htmlspecialchars($url, ENT_QUOTES) . "';</script>";
        echo "<noscript><meta http-equiv='refresh' content='0;url=" . htmlspecialchars($url, ENT_QUOTES) . "'></noscript>";
        exit;
    } else {
        // Utiliser la redirection HTTP normale
        header("Location: " . $url);
        exit;
    }
}
```

### 2. Fichiers de vérification de session robustes

#### Pour les pages du dossier `admin/` :
- **Fichier** : `session_check.php`
- **Usage** : `require_once '../session_check.php';`

#### Pour les pages du dossier racine :
- **Fichier** : `session_check_root.php`
- **Usage** : `require_once 'session_check_root.php';`

### 3. Fonction `checkSessionAndRedirect()` dans `functions.php`

Une nouvelle fonction robuste a été ajoutée pour la vérification de session :

```php
function checkSessionAndRedirect() {
    // Vérifier si la session est démarrée
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    // Vérifier si l'utilisateur est connecté
    if (!isUserLoggedIn()) {
        // Déterminer le chemin de redirection
        $current_dir = dirname($_SERVER['PHP_SELF']);
        $login_url = (strpos($current_dir, '/admin') !== false) ? '../auth/login.php' : 'auth/login.php';
        
        // Si les headers n'ont pas encore été envoyés, utiliser la redirection HTTP
        if (!headers_sent()) {
            header("Location: " . $login_url);
            exit;
        } else {
            // Sinon, utiliser JavaScript pour la redirection
            echo "<!DOCTYPE html><html><head><title>Redirection...</title></head><body>";
            echo "<script>window.location.href = '" . htmlspecialchars($login_url, ENT_QUOTES) . "';</script>";
            echo "<noscript><meta http-equiv='refresh' content='0;url=" . htmlspecialchars($login_url, ENT_QUOTES) . "'></noscript>";
            echo "<p>Redirection en cours... <a href='" . htmlspecialchars($login_url, ENT_QUOTES) . "'>Cliquez ici si la redirection ne fonctionne pas</a></p>";
            echo "</body></html>";
            exit;
        }
    }
}
```

## Comment utiliser

### Pour les nouvelles pages

1. **Pages dans le dossier `admin/`** :
```php
<?php
require_once '../config/config.php';

// Vérification de session robuste
require_once '../session_check.php';
// ... autres includes
?>
```

2. **Pages dans le dossier racine** :
```php
<?php
require_once 'config/config.php';

// Vérification de session robuste
require_once 'session_check_root.php';
// ... autres includes
?>
```

### Pour les pages existantes

Remplacer :
```php
requireLogin();
```

Par :
```php
// Vérification de session robuste
require_once '../session_check.php'; // ou session_check_root.php selon l'emplacement
```

## Avantages

✅ **Plus d'erreurs de headers** : La redirection fonctionne même après l'envoi de contenu HTML
✅ **Redirection JavaScript** : Fallback automatique si les headers HTTP ne peuvent pas être modifiés
✅ **Support NoScript** : Redirection via meta refresh pour les navigateurs sans JavaScript
✅ **Messages utilisateur** : Lien de fallback si la redirection automatique échoue
✅ **Nettoyage du buffer** : Évite l'affichage de contenu indésirable

## Pages mises à jour

Les pages suivantes ont été mises à jour avec le nouveau système :

- `admin/roles.php`
- `admin/roles_add.php`
- `admin/roles_edit.php`
- `admin/roles_view.php`
- `admin/roles_delete.php`
- `admin/roles_bulk.php`

## Test

Pour tester le système :

1. Connectez-vous à l'application
2. Attendez que votre session expire (ou supprimez manuellement les cookies)
3. Essayez d'accéder à une page protégée
4. Vous devriez être redirigé vers la page de connexion sans erreur
