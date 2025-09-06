<?php
/**
 * Intégration avec le module Présences
 * Application de gestion scolaire - République Démocratique du Congo
 */

require_once '../../config/config.php';
require_once '../../includes/functions.php';

/**
 * Marquer la présence via QR Code
 */
function markAttendanceViaQR($student_id, $qr_data) {
    global $database;
    
    try {
        // Vérifier que l'élève existe
        $student = $database->query(
            "SELECT e.*, c.nom as classe_nom FROM eleves e 
             LEFT JOIN classes c ON e.classe_id = c.id 
             WHERE e.id = ?",
            [$student_id]
        )->fetch();
        
        if (!$student) {
            throw new Exception('Élève non trouvé');
        }
        
        // Vérifier si la présence n'est pas déjà marquée aujourd'hui
        $today = date('Y-m-d');
        $existing_attendance = $database->query(
            "SELECT id FROM presences WHERE eleve_id = ? AND DATE(date_presence) = ?",
            [$student_id, $today]
        )->fetch();
        
        if ($existing_attendance) {
            return [
                'success' => false,
                'message' => 'Présence déjà marquée aujourd\'hui',
                'type' => 'warning'
            ];
        }
        
        // Marquer la présence
        $database->execute(
            "INSERT INTO presences (eleve_id, date_presence, statut, heure_arrivee, created_at) 
             VALUES (?, NOW(), 'present', NOW(), NOW())",
            [$student_id]
        );
        
        // Log de l'action
        logAction('presences', "Présence marquée via QR Code pour l'élève: {$student['nom']} {$student['prenom']}", $student_id);
        
        return [
            'success' => true,
            'message' => 'Présence marquée avec succès',
            'student' => $student,
            'type' => 'success'
        ];
        
    } catch (Exception $e) {
        return [
            'success' => false,
            'message' => $e->getMessage(),
            'type' => 'error'
        ];
    }
}

/**
 * Obtenir les statistiques de présence d'un élève
 */
function getStudentAttendanceStats($student_id, $month = null, $year = null) {
    global $database;
    
    if (!$month) $month = date('n');
    if (!$year) $year = date('Y');
    
    try {
        // Nombre de jours de présence ce mois
        $present_days = $database->query(
            "SELECT COUNT(*) as count FROM presences 
             WHERE eleve_id = ? AND MONTH(date_presence) = ? AND YEAR(date_presence) = ? AND statut = 'present'",
            [$student_id, $month, $year]
        )->fetch()['count'];
        
        // Nombre total de jours d'école ce mois (estimation)
        $total_school_days = $database->query(
            "SELECT COUNT(DISTINCT DATE(date_presence)) as count FROM presences 
             WHERE MONTH(date_presence) = ? AND YEAR(date_presence) = ?",
            [$month, $year]
        )->fetch()['count'];
        
        // Pourcentage de présence
        $attendance_rate = $total_school_days > 0 ? ($present_days / $total_school_days) * 100 : 0;
        
        // Dernière présence
        $last_attendance = $database->query(
            "SELECT date_presence, statut FROM presences 
             WHERE eleve_id = ? 
             ORDER BY date_presence DESC LIMIT 1",
            [$student_id]
        )->fetch();
        
        return [
            'success' => true,
            'stats' => [
                'present_days' => $present_days,
                'total_school_days' => $total_school_days,
                'attendance_rate' => round($attendance_rate, 2),
                'last_attendance' => $last_attendance
            ]
        ];
        
    } catch (Exception $e) {
        return [
            'success' => false,
            'message' => $e->getMessage()
        ];
    }
}

/**
 * Exporter les présences d'un élève
 */
function exportStudentAttendance($student_id, $start_date, $end_date) {
    global $database;
    
    try {
        $attendances = $database->query(
            "SELECT date_presence, statut, heure_arrivee, heure_depart, observations 
             FROM presences 
             WHERE eleve_id = ? AND DATE(date_presence) BETWEEN ? AND ?
             ORDER BY date_presence DESC",
            [$student_id, $start_date, $end_date]
        )->fetchAll();
        
        return [
            'success' => true,
            'attendances' => $attendances
        ];
        
    } catch (Exception $e) {
        return [
            'success' => false,
            'message' => $e->getMessage()
        ];
    }
}
?>
