<?php
/**
 * Actions pour le scanner QR Code
 * Application de gestion scolaire - République Démocratique du Congo
 */

require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/config/database.php';
require_once dirname(__DIR__, 2) . '/includes/functions.php';
require_once dirname(__DIR__, 2) . '/includes/permissions-pages.php';
requireLogin();

requirePagePermissionFromDB('cartes_eleves', 'cartes_eleves/qr-actions', 'read', '../dashboard.php');

$action = $_POST['action'] ?? $_GET['action'] ?? '';

switch ($action) {
    case 'mark_attendance':
        markAttendance();
        break;
    case 'check_balance':
        checkBalance();
        break;
    case 'get_student_info':
        getStudentInfo();
        break;
    case 'log_scan':
        logScan();
        break;
    case 'get_scan_history':
        getScanHistory();
        break;
    case 'get_statistics':
        getStatistics();
        break;
    default:
        echo json_encode(['success' => false, 'message' => 'Action non reconnue']);
}

function markAttendance() {
    global $database;
    
    $student_id = $_POST['student_id'] ?? 0;
    $matricule = $_POST['matricule'] ?? '';
    
    try {
        // Vérifier que l'élève existe
        $student = $database->query(
            "SELECT e.*, c.nom as classe_nom FROM eleves e 
             LEFT JOIN classes c ON e.classe_id = c.id 
             WHERE e.id = ? AND e.numero_matricule = ?",
            [$student_id, $matricule]
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
            throw new Exception('Présence déjà marquée aujourd\'hui');
        }
        
        // Marquer la présence
        $database->execute(
            "INSERT INTO presences (eleve_id, date_presence, statut, heure_arrivee, created_at) 
             VALUES (?, NOW(), 'present', NOW(), NOW())",
            [$student_id]
        );
        
        // Log de l'action
        logAction('presences', "Présence marquée via QR Code pour l'élève: {$student['nom']} {$student['prenom']}", $student_id);
        
        echo json_encode([
            'success' => true,
            'message' => 'Présence marquée avec succès',
            'student' => $student
        ]);
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function checkBalance() {
    global $database;
    
    $student_id = $_POST['student_id'] ?? 0;
    $matricule = $_POST['matricule'] ?? '';
    
    try {
        // Vérifier que l'élève existe
        $student = $database->query(
            "SELECT e.*, c.nom as classe_nom FROM eleves e 
             LEFT JOIN classes c ON e.classe_id = c.id 
             WHERE e.id = ? AND e.numero_matricule = ?",
            [$student_id, $matricule]
        )->fetch();
        
        if (!$student) {
            throw new Exception('Élève non trouvé');
        }
        
        // Calculer le solde de l'élève
        $frais_totaux = $database->query(
            "SELECT COALESCE(SUM(montant), 0) as total FROM frais_scolarite 
             WHERE eleve_id = ? AND statut = 'actif'",
            [$student_id]
        )->fetch()['total'];
        
        $paiements_totaux = $database->query(
            "SELECT COALESCE(SUM(montant), 0) as total FROM paiements 
             WHERE eleve_id = ? AND statut = 'valide'",
            [$student_id]
        )->fetch()['total'];
        
        $solde = $frais_totaux - $paiements_totaux;
        
        // Récupérer la devise par défaut
        $devise = $database->query("SELECT devise FROM parametres_generaux LIMIT 1")->fetch()['devise'] ?? 'CDF';
        
        echo json_encode([
            'success' => true,
            'balance' => [
                'solde' => $solde,
                'devise' => $devise,
                'frais_totaux' => $frais_totaux,
                'paiements_totaux' => $paiements_totaux,
                'last_update' => date('d/m/Y H:i')
            ],
            'student' => $student
        ]);
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function getStudentInfo() {
    global $database;
    
    $student_id = $_POST['student_id'] ?? 0;
    $matricule = $_POST['matricule'] ?? '';
    
    try {
        // Récupérer les informations complètes de l'élève
        $student = $database->query(
            "SELECT e.*, c.nom as classe_nom, c.niveau,
                    a.annee, a.date_debut, a.date_fin
             FROM eleves e 
             LEFT JOIN classes c ON e.classe_id = c.id 
             LEFT JOIN annees_scolaires a ON e.annee_scolaire_id = a.id
             WHERE e.id = ? AND e.numero_matricule = ?",
            [$student_id, $matricule]
        )->fetch();
        
        if (!$student) {
            throw new Exception('Élève non trouvé');
        }
        
        echo json_encode([
            'success' => true,
            'student' => $student
        ]);
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function logScan() {
    global $database;
    
    $type_scan = $_POST['type'] ?? 'autre';
    $data = $_POST['data'] ?? '';
    
    try {
        // Récupérer l'ID de la carte si possible
        $carte_id = null;
        $eleve_id = null;
        
        if ($data) {
            $qr_data = json_decode($data, true);
            if ($qr_data && isset($qr_data['student_id'])) {
                $eleve_id = $qr_data['student_id'];
                
                // Récupérer l'ID de la carte
                $carte = $database->query(
                    "SELECT id FROM carte_eleve WHERE eleve_id = ? AND statut = 'active'",
                    [$eleve_id]
                )->fetch();
                
                if ($carte) {
                    $carte_id = $carte['id'];
                }
            }
        }
        
        // Enregistrer le log
        $database->execute(
            "INSERT INTO logs_scan_carte 
             (carte_id, eleve_id, type_scan, ip_address, user_agent, donnees_scan, created_at)
             VALUES (?, ?, ?, ?, ?, ?, NOW())",
            [
                $carte_id,
                $eleve_id,
                $type_scan,
                $_SERVER['REMOTE_ADDR'] ?? null,
                $_SERVER['HTTP_USER_AGENT'] ?? null,
                $data
            ]
        );
        
        echo json_encode(['success' => true]);
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function getScanHistory() {
    global $database;
    
    try {
        $scans = $database->query(
            "SELECT lsc.*, e.nom, e.prenom, e.numero_matricule
             FROM logs_scan_carte lsc
             LEFT JOIN eleves e ON lsc.eleve_id = e.id
             ORDER BY lsc.created_at DESC
             LIMIT 20"
        )->fetchAll();
        
        echo json_encode([
            'success' => true,
            'history' => $scans
        ]);
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function getStatistics() {
    global $database;
    
    try {
        // Scans d'aujourd'hui
        $today_scans = $database->query(
            "SELECT COUNT(*) as count FROM logs_scan_carte 
             WHERE DATE(created_at) = CURDATE()"
        )->fetch()['count'];
        
        // Total des scans
        $total_scans = $database->query(
            "SELECT COUNT(*) as count FROM logs_scan_carte"
        )->fetch()['count'];
        
        echo json_encode([
            'success' => true,
            'today_scans' => $today_scans,
            'total_scans' => $total_scans
        ]);
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}
?>
