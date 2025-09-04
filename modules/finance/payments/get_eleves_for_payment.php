<?php
/**
 * Endpoint AJAX pour récupérer la liste des élèves pour le DataTable
 * Module de gestion financière - Paiements
 */

// Désactiver l'affichage des erreurs pour éviter la pollution du JSON
error_reporting(0);
ini_set('display_errors', 0);

// Démarrer la session si pas déjà démarrée
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../../../config/config.php';
require_once '../../../config/database.php';
require_once '../../../includes/functions.php';

// Vérifier l'authentification
if (!function_exists('requireLogin') || !isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Non authentifié']);
    exit;
}

// Vérifier les permissions
if (!function_exists('checkPermission') || !checkPermission('finance')) {
    http_response_code(403);
    echo json_encode(['error' => 'Accès refusé']);
    exit;
}

// Obtenir l'année scolaire actuelle
$current_year = null;
if (function_exists('getCurrentAcademicYear')) {
    $current_year = getCurrentAcademicYear();
}

if (!$current_year) {
    http_response_code(400);
    echo json_encode(['error' => 'Aucune année scolaire active']);
    exit;
}

try {
    // Récupérer les statuts demandés
    $statuses = ['actif', 'transfere', 'abandonne', 'diplome', 'non-evalue', 'admis', 'evalue'];
    
    // Construire la requête SQL - simplifiée pour éviter les erreurs
    // Utiliser une approche plus simple qui ne dépend pas des inscriptions
    $sql = "SELECT DISTINCT 
                e.id,
                e.nom,
                e.prenom,
                e.numero_matricule,
                COALESCE(c.nom, 'Non assigné') as classe_nom,
                COALESCE(c.niveau, '-') as niveau,
                COALESCE(e.status, 'non-defini') as status,
                COALESCE(c.id, 0) as classe_id
            FROM eleves e
            LEFT JOIN classes c ON e.classe_id = c.id
            WHERE e.status IN (" . str_repeat('?,', count($statuses) - 1) . "?)
            ORDER BY e.nom, e.prenom";
    
    // Préparer les paramètres (juste les statuts)
    $params = $statuses;
    
    // Exécuter la requête en utilisant la méthode query de la classe Database
    $stmt = $database->query($sql, $params);
    $eleves = $stmt->fetchAll();
    
    // Formater les données pour DataTables
    $data = [];
    foreach ($eleves as $eleve) {
        // Nettoyer et valider les données
        $data[] = [
            'id' => (int)$eleve['id'],
            'numero_matricule' => htmlspecialchars($eleve['numero_matricule'] ?? ''),
            'nom' => htmlspecialchars($eleve['nom'] ?? ''),
            'prenom' => htmlspecialchars($eleve['prenom'] ?? ''),
            'classe_nom' => htmlspecialchars($eleve['classe_nom'] ?? 'Non assigné'),
            'niveau' => htmlspecialchars($eleve['niveau'] ?? '-'),
            'status' => htmlspecialchars($eleve['status'] ?? 'non-defini'),
            'classe_id' => (int)$eleve['classe_id'],
            'actions' => '' // Sera rempli par DataTables
        ];
    }
    
    // Réponse pour DataTables
    $response = [
        'draw' => isset($_POST['draw']) ? (int)$_POST['draw'] : 1,
        'recordsTotal' => count($data),
        'recordsFiltered' => count($data),
        'data' => $data
    ];
    
    // Envoyer la réponse JSON
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-cache, must-revalidate');
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    // Log de l'erreur (optionnel)
    error_log('Erreur get_eleves_for_payment: ' . $e->getMessage());
    
    // Réponse d'erreur pour DataTables
    $error_response = [
        'draw' => isset($_POST['draw']) ? (int)$_POST['draw'] : 1,
        'recordsTotal' => 0,
        'recordsFiltered' => 0,
        'data' => [],
        'error' => 'Erreur lors de la récupération des données'
    ];
    
    // Envoyer la réponse d'erreur
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($error_response, JSON_UNESCAPED_UNICODE);
}
?>
