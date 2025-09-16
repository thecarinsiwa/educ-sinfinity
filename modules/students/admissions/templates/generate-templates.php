<?php
/**
 * Script pour générer les modèles CSV avec des données d'exemple
 * Application de gestion scolaire - République Démocratique du Congo
 */

require_once '../../../../config/config.php';
require_once '../../../../config/database.php';

// Récupérer les classes disponibles
$classes = $database->query("SELECT nom FROM classes ORDER BY nom")->fetchAll();

// Générer le modèle candidatures
$candidatures_data = [
    ['Nom', 'Prenom', 'Date_naissance', 'Sexe', 'Classe_demandee', 'Telephone_parent', 'Nom_pere', 'Nom_mere', 'Profession_pere', 'Profession_mere', 'Adresse', 'Email', 'Personne_contact', 'Telephone_contact', 'Relation_contact', 'Ecole_precedente', 'Classe_precedente', 'Annee_precedente', 'Moyenne_precedente', 'Motif_demande'],
    ['KABONGO', 'Jean', '2010-05-15', 'M', '6ème Primaire A', '+243 123 456 789', 'KABONGO Pierre', 'KABONGO Marie', 'Enseignant', 'Commerçante', 'Commune de Limete', 'jean.kabongo@email.com', 'KABONGO Pierre', '+243 123 456 789', 'Père', 'École Saint Paul', '5ème Primaire A', '2023-2024', '14.5', 'Changement d\'établissement'],
    ['MUKENDI', 'Marie', '2009-08-22', 'F', '5ème Primaire A', '+243 987 654 321', 'MUKENDI Joseph', 'MUKENDI Anne', 'Ingénieur', 'Infirmière', 'Commune de Gombe', 'marie.mukendi@email.com', 'MUKENDI Joseph', '+243 987 654 321', 'Père', 'École Notre Dame', '4ème Primaire A', '2023-2024', '16.2', 'Meilleure qualité d\'enseignement'],
    ['TSHILONGO', 'Paul', '2011-03-10', 'M', '4ème Primaire A', '+243 555 123 456', 'TSHILONGO David', 'TSHILONGO Grace', 'Chauffeur', 'Secrétaire', 'Commune de Matete', 'paul.tshilongo@email.com', 'TSHILONGO David', '+243 555 123 456', 'Père', 'École Publique', '3ème Primaire A', '2023-2024', '12.8', 'Proximité géographique']
];

// Générer le modèle élèves
$eleves_data = [
    ['Nom', 'Prenom', 'Date_naissance', 'Sexe', 'Classe', 'Telephone_parent', 'Nom_pere', 'Nom_mere', 'Profession_pere', 'Profession_mere', 'Adresse', 'Email', 'Personne_contact', 'Telephone_contact', 'Relation_contact', 'Lieu_naissance'],
    ['KABONGO', 'Jean', '2010-05-15', 'M', '6ème Primaire A', '+243 123 456 789', 'KABONGO Pierre', 'KABONGO Marie', 'Enseignant', 'Commerçante', 'Commune de Limete', 'jean.kabongo@email.com', 'KABONGO Pierre', '+243 123 456 789', 'Père', 'Kinshasa'],
    ['MUKENDI', 'Marie', '2009-08-22', 'F', '5ème Primaire A', '+243 987 654 321', 'MUKENDI Joseph', 'MUKENDI Anne', 'Ingénieur', 'Infirmière', 'Commune de Gombe', 'marie.mukendi@email.com', 'MUKENDI Joseph', '+243 987 654 321', 'Père', 'Kinshasa'],
    ['TSHILONGO', 'Paul', '2011-03-10', 'M', '4ème Primaire A', '+243 555 123 456', 'TSHILONGO David', 'TSHILONGO Grace', 'Chauffeur', 'Secrétaire', 'Commune de Matete', 'paul.tshilongo@email.com', 'TSHILONGO David', '+243 555 123 456', 'Père', 'Kinshasa']
];

// Fonction pour écrire un fichier CSV
function writeCSV($filename, $data) {
    $file = fopen($filename, 'w');
    if ($file) {
        foreach ($data as $row) {
            fputcsv($file, $row);
        }
        fclose($file);
        echo "Fichier généré : $filename\n";
    } else {
        echo "Erreur lors de la création du fichier : $filename\n";
    }
}

// Générer les fichiers
writeCSV('modele-candidatures.csv', $candidatures_data);
writeCSV('modele-eleves.csv', $eleves_data);

echo "\nModèles CSV générés avec succès !\n";
echo "Classes disponibles dans le système :\n";
foreach ($classes as $classe) {
    echo "- " . $classe['nom'] . "\n";
}
?>
