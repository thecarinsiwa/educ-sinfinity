<?php
/**
 * Récupérer l'historique d'une absence
 * Application de gestion scolaire - République Démocratique du Congo
 */

require_once '../../../config/config.php';
require_once '../../../config/database.php';
require_once '../../../includes/functions.php';

// Vérifier l'authentification
requireLogin();

header('Content-Type: application/json');

try {
    $absence_id = (int)($_GET['id'] ?? 0);
    
    if (!$absence_id) {
        throw new Exception('ID d\'absence manquant');
    }
    
    // Vérifier que l'absence existe et récupérer ses informations
    $absence_info = $database->query(
        "SELECT a.*, e.nom as eleve_nom, e.prenom as eleve_prenom,
                c.nom as classe_nom, c.niveau,
                u_created.nom as created_by_nom, u_created.prenom as created_by_prenom,
                u_updated.nom as updated_by_nom, u_updated.prenom as updated_by_prenom
         FROM absences a
         JOIN eleves e ON a.eleve_id = e.id
         JOIN inscriptions i ON e.id = i.eleve_id
         JOIN classes c ON i.classe_id = c.id
         LEFT JOIN users u_created ON a.created_by = u_created.id
         LEFT JOIN users u_updated ON a.updated_by = u_updated.id
         WHERE a.id = ?",
        [$absence_id]
    )->fetch();
    
    if (!$absence_info) {
        throw new Exception('Absence non trouvée');
    }
    
    // Récupérer l'historique des actions sur cette absence
    $history = $database->query(
        "SELECT ual.*, u.nom as user_nom, u.prenom as user_prenom, u.username
         FROM user_actions_log ual
         JOIN users u ON ual.user_id = u.id
         WHERE ual.module = 'attendance' 
         AND (ual.target_id = ? OR ual.details LIKE ?)
         ORDER BY ual.created_at DESC",
        [$absence_id, "%absence_id:$absence_id%"]
    )->fetchAll();
    
    // Formater les données pour l'affichage
    $formatted_history = [];
    foreach ($history as $entry) {
        $formatted_history[] = [
            'action' => $entry['action'],
            'details' => $entry['details'],
            'user_name' => $entry['user_nom'] . ' ' . $entry['user_prenom'] . ' (' . $entry['username'] . ')',
            'created_at' => formatDateTime($entry['created_at']),
            'ip_address' => $entry['ip_address']
        ];
    }
    
    // Formater les informations de l'absence
    $formatted_absence = [
        'eleve_nom' => $absence_info['eleve_nom'] . ' ' . $absence_info['eleve_prenom'],
        'classe_nom' => $absence_info['classe_nom'],
        'niveau' => $absence_info['niveau'],
        'date_absence' => formatDateTime($absence_info['date_absence']),
        'type_absence' => $absence_info['type_absence'],
        'motif' => $absence_info['motif'],
        'justifiee' => (bool)$absence_info['justifiee'],
        'created_at' => formatDateTime($absence_info['created_at']),
        'updated_at' => $absence_info['updated_at'] ? formatDateTime($absence_info['updated_at']) : null,
        'created_by' => $absence_info['created_by_nom'] ? 
            $absence_info['created_by_nom'] . ' ' . $absence_info['created_by_prenom'] : 'Non renseigné',
        'updated_by' => $absence_info['updated_by_nom'] ? 
            $absence_info['updated_by_nom'] . ' ' . $absence_info['updated_by_prenom'] : null
    ];
    
    // Enregistrer la consultation de l'historique
    logUserAction(
        'view_absence_history',
        'attendance',
        'Consultation de l\'historique de l\'absence ID: ' . $absence_id,
        $absence_id
    );
    
    echo json_encode([
        'success' => true,
        'absence_info' => $formatted_absence,
        'history' => $formatted_history
    ]);
    
} catch (Exception $e) {
    error_log("Erreur lors de la récupération de l'historique d'absence: " . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
