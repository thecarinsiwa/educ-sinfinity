<?php
/**
 * API pour récupérer les élèves d'une classe
 * Application de gestion scolaire - République Démocratique du Congo
 */

require_once '../../../config/config.php';
require_once '../../../config/database.php';
require_once '../../../includes/functions.php';

// Vérifier l'authentification
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Non authentifié']);
    exit;
}

// Vérifier les permissions
if (!checkPermission('students')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Permissions insuffisantes']);
    exit;
}

$classe_id = (int)($_GET['classe_id'] ?? 0);
$include_attendance = isset($_GET['include_attendance']);
$date = $_GET['date'] ?? date('Y-m-d');

if (!$classe_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'ID de classe manquant']);
    exit;
}

try {
    // Récupérer l'année scolaire active
    $current_year = $database->query("SELECT * FROM annees_scolaires WHERE status = 'active' LIMIT 1")->fetch();
    
    // Requête de base pour récupérer les élèves
    $base_query = "
        SELECT e.id, e.nom, e.prenom, e.numero_matricule, e.date_naissance,
               c.nom as classe_nom, c.niveau
        FROM eleves e
        JOIN inscriptions i ON e.id = i.eleve_id
        JOIN classes c ON i.classe_id = c.id
        WHERE i.classe_id = ? 
        AND i.status = 'inscrit' 
        AND i.annee_scolaire_id = ?
        ORDER BY e.nom, e.prenom
    ";
    
    $students = $database->query($base_query, [$classe_id, $current_year['id'] ?? 0])->fetchAll();
    
    if (empty($students)) {
        echo json_encode([
            'success' => true,
            'students' => [],
            'message' => 'Aucun élève trouvé dans cette classe'
        ]);
        exit;
    }
    
    // Si demandé, inclure les informations d'attendance pour la date spécifiée
    if ($include_attendance) {
        foreach ($students as &$student) {
            // Vérifier s'il y a déjà un enregistrement d'attendance pour cette date
            $existing = $database->query(
                "SELECT id, type_absence, motif, duree_retard 
                 FROM absences 
                 WHERE eleve_id = ? AND DATE(date_absence) = ?",
                [$student['id'], $date]
            )->fetch();
            
            $student['existing_attendance'] = $existing ? true : false;
            $student['attendance_details'] = $existing ?: null;
            $student['status'] = 'present'; // Statut par défaut
        }
    }
    
    // Enregistrer l'action
    logUserAction(
        'get_students',
        'attendance',
        'Récupération des élèves - Classe ID: ' . $classe_id . ', Date: ' . $date,
        null
    );
    
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'students' => $students,
        'count' => count($students),
        'classe_id' => $classe_id,
        'date' => $date
    ]);
    
} catch (Exception $e) {
    error_log("Erreur lors de la récupération des élèves: " . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erreur lors de la récupération des élèves: ' . $e->getMessage()
    ]);
}
?>
