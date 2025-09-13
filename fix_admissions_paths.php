<?php
/**
 * Script pour corriger les chemins dans les fichiers du module admissions
 */

$files_to_fix = [
    'modules/students/admissions/reports/admission-stats.php',
    'modules/students/admissions/settings/criteria.php',
    'modules/students/admissions/exports/applications.php',
    'modules/students/admissions/evaluation/index.php',
    'modules/students/admissions/evaluation/get-evaluation.php',
    'modules/students/admissions/enrollment/index.php',
    'modules/students/admissions/enrollment/get-candidature.php',
    'modules/students/admissions/documents/index.php',
    'modules/students/admissions/applications/view.php',
    'modules/students/admissions/applications/update_status.php',
    'modules/students/admissions/applications/process.php',
    'modules/students/admissions/applications/index.php',
    'modules/students/admissions/applications/edit.php',
    'modules/students/admissions/applications/add.php',
    'modules/students/admissions/index.php',
    'modules/students/admissions/new-application.php',
    'modules/students/admissions/direct-enrollment.php',
    'modules/students/admissions/bulk-import.php'
];

echo "=== CORRECTION DES CHEMINS DANS LE MODULE ADMISSIONS ===\n\n";

$fixed_count = 0;
$error_count = 0;

foreach ($files_to_fix as $file) {
    if (file_exists($file)) {
        echo "Traitement de : $file\n";
        
        // Lire le contenu du fichier
        $content = file_get_contents($file);
        
        if ($content === false) {
            echo "  ❌ Erreur de lecture\n";
            $error_count++;
            continue;
        }
        
        // Compter les remplacements
        $original_content = $content;
        
        // Corriger les chemins incorrects
        $content = str_replace(
            "require_once '../../../includes/permissions-pages.php';",
            "require_once '../../../../includes/permissions-pages.php';",
            $content
        );
        
        $content = str_replace(
            "require_once '../../../includes/permissions.php';",
            "require_once '../../../../includes/permissions.php';",
            $content
        );
        
        $content = str_replace(
            "require_once '../../../includes/functions.php';",
            "require_once '../../../../includes/functions.php';",
            $content
        );
        
        $content = str_replace(
            "require_once '../../../config/config.php';",
            "require_once '../../../../config/config.php';",
            $content
        );
        
        $content = str_replace(
            "require_once '../../../config/database.php';",
            "require_once '../../../../config/database.php';",
            $content
        );
        
        // Corriger les caractères d'encodage
        $content = str_replace('RÃ©publique', 'République', $content);
        $content = str_replace('DÃ©mocratique', 'Démocratique', $content);
        $content = str_replace('VÃ©rifier', 'Vérifier', $content);
        $content = str_replace('accÃ¨s', 'accès', $content);
        $content = str_replace('refusÃ©', 'refusé', $content);
        $content = str_replace('fonctionnalitÃ©', 'fonctionnalité', $content);
        $content = str_replace('RÃ©cupÃ©rer', 'Récupérer', $content);
        $content = str_replace('annÃ©e', 'année', $content);
        $content = str_replace('ParamÃ¨tres', 'Paramètres', $content);
        $content = str_replace('Ã ', 'à ', $content);
        $content = str_replace('Ã©', 'é', $content);
        $content = str_replace('Ã¨', 'è', $content);
        $content = str_replace('Ã§', 'ç', $content);
        $content = str_replace('Ã¢', 'â', $content);
        $content = str_replace('Ã´', 'ô', $content);
        $content = str_replace('Ã®', 'î', $content);
        $content = str_replace('Ã¯', 'ï', $content);
        $content = str_replace('Ã¹', 'ù', $content);
        $content = str_replace('Ã»', 'û', $content);
        
        // Vérifier si des changements ont été faits
        if ($content !== $original_content) {
            // Sauvegarder le fichier
            if (file_put_contents($file, $content) !== false) {
                echo "  ✅ Corrigé\n";
                $fixed_count++;
            } else {
                echo "  ❌ Erreur de sauvegarde\n";
                $error_count++;
            }
        } else {
            echo "  ⚪ Aucun changement nécessaire\n";
        }
    } else {
        echo "  ❌ Fichier non trouvé : $file\n";
        $error_count++;
    }
}

echo "\n=== RÉSUMÉ ===\n";
echo "Fichiers corrigés : $fixed_count\n";
echo "Erreurs : $error_count\n";
echo "Total traités : " . count($files_to_fix) . "\n";

echo "\n=== FIN ===\n";
?>
