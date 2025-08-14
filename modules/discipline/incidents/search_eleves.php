<?php
/**
 * Endpoint AJAX pour la recherche d'élèves - Module Discipline
 * Application de gestion scolaire - République Démocratique du Congo
 */

require_once '../../../config/config.php';
require_once '../../../config/database.php';
require_once '../../../includes/functions.php';

// Vérifier l'authentification et les permissions
requireLogin();
if (!checkPermission('discipline')) {
    http_response_code(403);
    echo json_encode(['error' => 'Accès refusé']);
    exit;
}

// Récupérer le terme de recherche
$query = sanitizeInput($_GET['q'] ?? '');

if (strlen($query) < 2) {
    echo json_encode([]);
    exit;
}

try {
    // Recherche d'élèves actifs
    $sql = "SELECT e.id, e.nom, e.prenom, e.numero_matricule, 
                   c.nom as classe_nom, c.id as classe_id, c.niveau
            FROM eleves e
            LEFT JOIN inscriptions i ON e.id = i.eleve_id AND i.status = 'inscrit'
            LEFT JOIN classes c ON i.classe_id = c.id
            WHERE e.status = 'actif'
              AND (e.nom LIKE ? OR e.prenom LIKE ? OR e.numero_matricule LIKE ?)
            ORDER BY e.nom, e.prenom
            LIMIT 10";
    
    $search_param = "%$query%";
    $params = [$search_param, $search_param, $search_param];
    
    $eleves = $database->query($sql, $params)->fetchAll();
    
    // Formater les résultats
    $results = [];
    foreach ($eleves as $eleve) {
        $results[] = [
            'id' => $eleve['id'],
            'nom' => $eleve['nom'],
            'prenom' => $eleve['prenom'],
            'numero_matricule' => $eleve['numero_matricule'],
            'classe_nom' => $eleve['classe_nom'] ?? 'Non inscrit',
            'classe_id' => $eleve['classe_id'],
            'niveau' => $eleve['niveau'] ?? 'N/A'
        ];
    }
    
    // Retourner les résultats en JSON
    header('Content-Type: application/json');
    echo json_encode($results);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Erreur lors de la recherche']);
}
?>
