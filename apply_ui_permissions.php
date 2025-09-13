<?php
/**
 * Script pour appliquer automatiquement les permissions UI aux pages existantes
 */

require_once 'config/config.php';
require_once 'config/database.php';
require_once 'includes/functions.php';

// Démarrer la session
session_start();

// Vérifier si l'utilisateur est admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'Administrateur') {
    echo "<h2>❌ Accès refusé</h2>";
    echo "<p>Seuls les administrateurs peuvent utiliser ce script.</p>";
    exit;
}

echo "<h2>🔧 Application des Permissions UI</h2>";

// Liste des fichiers à modifier
$files_to_modify = [
    'modules/finance/index.php',
    'modules/academic/index.php',
    'modules/evaluations/index.php',
    'modules/personnel/index.php',
    'admin/users.php'
];

echo "<h3>📋 Fichiers à modifier</h3>";
echo "<ul>";
foreach ($files_to_modify as $file) {
    if (file_exists($file)) {
        echo "<li>✅ " . htmlspecialchars($file) . "</li>";
    } else {
        echo "<li>❌ " . htmlspecialchars($file) . " (non trouvé)</li>";
    }
}
echo "</ul>";

echo "<h3>🔧 Actions disponibles</h3>";

foreach ($files_to_modify as $file) {
    if (file_exists($file)) {
        echo "<h4>📄 " . htmlspecialchars($file) . "</h4>";
        
        // Vérifier si le fichier inclut déjà ui-permissions.php
        $content = file_get_contents($file);
        
        if (strpos($content, 'ui-permissions.php') !== false) {
            echo "<p>✅ Déjà configuré</p>";
        } else {
            echo "<p>⚠️ Nécessite une configuration</p>";
            
            // Ajouter l'inclusion de ui-permissions.php
            $new_content = str_replace(
                "require_once '../../includes/permissions-pages.php';",
                "require_once '../../includes/permissions-pages.php';\nrequire_once '../../includes/ui-permissions.php';",
                $content
            );
            
            if ($new_content !== $content) {
                // Créer une sauvegarde
                $backup_file = $file . '.backup.' . date('Y-m-d-H-i-s');
                file_put_contents($backup_file, $content);
                
                // Écrire le nouveau contenu
                if (file_put_contents($file, $new_content)) {
                    echo "<p>✅ Fichier mis à jour (sauvegarde: " . htmlspecialchars(basename($backup_file)) . ")</p>";
                } else {
                    echo "<p>❌ Erreur lors de la mise à jour</p>";
                }
            } else {
                echo "<p>⚠️ Structure de fichier différente - modification manuelle requise</p>";
            }
        }
        
        echo "<hr>";
    }
}

echo "<h3>📝 Instructions pour l'utilisation</h3>";
echo "<div class='alert alert-info'>";
echo "<h5>Comment utiliser les nouvelles fonctions :</h5>";
echo "<ol>";
echo "<li><strong>generatePermissionLink()</strong> - Remplace les liens &lt;a&gt; normaux</li>";
echo "<li><strong>generatePermissionButton()</strong> - Remplace les boutons normaux</li>";
echo "<li><strong>generateActionButtons()</strong> - Crée des groupes de boutons d'actions</li>";
echo "<li><strong>generatePermissionDropdown()</strong> - Crée des menus déroulants avec permissions</li>";
echo "<li><strong>getPermissionClasses()</strong> - Génère des classes CSS conditionnelles</li>";
echo "<li><strong>canShowElement()</strong> - Vérifie si un élément doit être affiché</li>";
echo "</ol>";
echo "</div>";

echo "<h3>📚 Exemples d'utilisation</h3>";
echo "<pre class='bg-light p-3'>";
echo htmlspecialchars('
// Au lieu de :
<a href="add.php" class="btn btn-primary">Ajouter</a>

// Utilisez :
<?php echo generatePermissionLink(
    "add.php",
    "Ajouter",
    "students",
    "add", 
    "create",
    ["class" => "btn btn-primary", "icon" => "fas fa-plus"]
); ?>

// Au lieu de :
<button class="btn btn-success">Sauvegarder</button>

// Utilisez :
<?php echo generatePermissionButton(
    "Sauvegarder",
    "submit",
    "students",
    "edit",
    "edit",
    ["class" => "btn btn-success"]
); ?>
');
echo "</pre>";

echo "<hr>";
echo "<h3>🔗 Liens utiles</h3>";
echo "<p><a href='demo_ui_permissions.php'>→ Voir la démonstration</a></p>";
echo "<p><a href='modules/students/index.php'>→ Voir l'exemple appliqué</a></p>";
echo "<p><a href='admin/roles.php'>→ Gérer les rôles</a></p>";
echo "<p><a href='dashboard.php'>→ Tableau de bord</a></p>";
?>
