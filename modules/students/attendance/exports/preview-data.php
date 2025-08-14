<?php
/**
 * API pour l'aperçu des données d'export
 * Application de gestion scolaire - République Démocratique du Congo
 */

require_once '../../../../config/config.php';
require_once '../../../../config/database.php';
require_once '../../../../includes/functions.php';

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

// Vérifier que c'est une requête POST avec action preview
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['preview_action'])) {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
    exit;
}

try {
    // Récupérer l'année scolaire active
    $current_year = $database->query("SELECT * FROM annees_scolaires WHERE status = 'active' LIMIT 1")->fetch();
    
    // Récupérer les paramètres
    $date_start = $_POST['date_start'] ?? '';
    $date_end = $_POST['date_end'] ?? '';
    $selected_classes = $_POST['selected_classes'] ?? [];
    $export_type = $_POST['export_type'] ?? 'summary';
    $include_justified = isset($_POST['include_justified']);
    
    // Validation
    if (!$date_start || !$date_end) {
        throw new Exception('Période de dates requise');
    }
    
    if (strtotime($date_start) > strtotime($date_end)) {
        throw new Exception('La date de début doit être antérieure à la date de fin');
    }
    
    // Construire la requête
    $where_conditions = [
        "a.date_absence >= ?",
        "a.date_absence <= ?",
        "i.annee_scolaire_id = ?"
    ];
    $params = [$date_start, $date_end . ' 23:59:59', $current_year['id'] ?? 0];
    
    if (!empty($selected_classes)) {
        $placeholders = str_repeat('?,', count($selected_classes) - 1) . '?';
        $where_conditions[] = "c.id IN ($placeholders)";
        $params = array_merge($params, $selected_classes);
    }
    
    if (!$include_justified) {
        $where_conditions[] = "a.type_absence NOT IN ('absence_justifiee', 'retard_justifie')";
    }
    
    $where_clause = implode(' AND ', $where_conditions);
    
    // Requête selon le type d'export
    if ($export_type === 'detailed') {
        $query = "
            SELECT 
                a.id,
                e.numero_matricule,
                e.nom as eleve_nom,
                e.prenom as eleve_prenom,
                e.date_naissance,
                c.nom as classe_nom,
                c.niveau,
                a.type_absence,
                a.date_absence,
                a.motif,
                a.duree_retard,
                a.justification,
                a.created_at,
                u.nom as valide_par_nom,
                u.prenom as valide_par_prenom
            FROM absences a
            JOIN eleves e ON a.eleve_id = e.id
            JOIN inscriptions i ON e.id = i.eleve_id
            JOIN classes c ON i.classe_id = c.id
            LEFT JOIN users u ON a.valide_par = u.id
            WHERE $where_clause
            ORDER BY a.date_absence DESC, c.niveau, c.nom, e.nom, e.prenom
            LIMIT 50
        ";
    } else {
        $query = "
            SELECT 
                c.niveau,
                c.nom as classe_nom,
                COUNT(*) as total_incidents,
                COUNT(CASE WHEN a.type_absence IN ('absence', 'absence_justifiee') THEN 1 END) as total_absences,
                COUNT(CASE WHEN a.type_absence IN ('retard', 'retard_justifie') THEN 1 END) as total_retards,
                COUNT(CASE WHEN a.type_absence IN ('absence_justifiee', 'retard_justifie') THEN 1 END) as total_justifies,
                COUNT(DISTINCT a.eleve_id) as eleves_concernes,
                AVG(CASE WHEN a.duree_retard > 0 THEN a.duree_retard END) as duree_moyenne_retard
            FROM absences a
            JOIN eleves e ON a.eleve_id = e.id
            JOIN inscriptions i ON e.id = i.eleve_id
            JOIN classes c ON i.classe_id = c.id
            WHERE $where_clause
            GROUP BY c.id, c.niveau, c.nom
            ORDER BY c.niveau, c.nom
        ";
    }
    
    $data = $database->query($query, $params)->fetchAll();
    
    // Enregistrer l'action
    logUserAction(
        'preview_export_data',
        'attendance',
        "Aperçu export - Type: $export_type, Période: $date_start à $date_end, Résultats: " . count($data),
        null
    );
    
    // Retourner les données
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'data' => $data,
        'type' => $export_type,
        'count' => count($data),
        'period' => [
            'start' => $date_start,
            'end' => $date_end
        ],
        'filters' => [
            'classes' => $selected_classes,
            'include_justified' => $include_justified
        ]
    ]);
    
} catch (Exception $e) {
    error_log("Erreur lors de l'aperçu des données d'export: " . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
