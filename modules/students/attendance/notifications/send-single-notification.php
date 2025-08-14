<?php
/**
 * API pour envoyer une notification individuelle
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

// Vérifier que c'est une requête POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
    exit;
}

// Lire les données JSON
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Données JSON invalides']);
    exit;
}

try {
    $absence_id = (int)($input['absence_id'] ?? 0);
    $notification_type = $input['type'] ?? '';
    $message = trim($input['message'] ?? '');
    
    // Validation
    if (!$absence_id) {
        throw new Exception('ID d\'absence requis');
    }
    
    if (!in_array($notification_type, ['sms', 'email'])) {
        throw new Exception('Type de notification invalide');
    }
    
    if (!$message) {
        throw new Exception('Message requis');
    }
    
    // Récupérer les informations de l'absence et du parent
    $absence_info = $database->query(
        "SELECT a.*, e.nom as eleve_nom, e.prenom as eleve_prenom,
                c.nom as classe_nom, p.nom as parent_nom, p.prenom as parent_prenom,
                p.telephone, p.email, p.id as parent_id
         FROM absences a
         JOIN eleves e ON a.eleve_id = e.id
         JOIN inscriptions i ON e.id = i.eleve_id
         JOIN classes c ON i.classe_id = c.id
         LEFT JOIN parents p ON e.parent_id = p.id
         WHERE a.id = ?",
        [$absence_id]
    )->fetch();
    
    if (!$absence_info) {
        throw new Exception('Absence non trouvée');
    }
    
    // Vérifier qu'il n'y a pas déjà une notification envoyée
    $existing_notification = $database->query(
        "SELECT id FROM notifications_parents WHERE absence_id = ? AND status = 'sent'",
        [$absence_id]
    )->fetch();
    
    if ($existing_notification) {
        throw new Exception('Une notification a déjà été envoyée pour cette absence');
    }
    
    // Vérifier les informations de contact
    if ($notification_type === 'sms' && empty($absence_info['telephone'])) {
        throw new Exception('Numéro de téléphone du parent manquant');
    }
    
    if ($notification_type === 'email' && empty($absence_info['email'])) {
        throw new Exception('Adresse email du parent manquante');
    }
    
    // Commencer une transaction
    $database->beginTransaction();
    
    try {
        // Simuler l'envoi de la notification
        $send_result = simulateNotificationSending($absence_info, $message, $notification_type);
        
        // Enregistrer la notification dans la base
        $status = $send_result['success'] ? 'sent' : 'failed';
        $sent_at = $send_result['success'] ? 'NOW()' : 'NULL';
        $error_message = $send_result['success'] ? null : $send_result['message'];
        
        $database->execute(
            "INSERT INTO notifications_parents (absence_id, parent_id, type_notification, message, status, sent_at, error_message, created_by) 
             VALUES (?, ?, ?, ?, ?, " . $sent_at . ", ?, ?)",
            [
                $absence_id, 
                $absence_info['parent_id'], 
                $notification_type, 
                $message, 
                $status, 
                $error_message, 
                $_SESSION['user_id']
            ]
        );
        
        $notification_id = $database->lastInsertId();
        
        // Enregistrer l'action dans l'historique
        logUserAction(
            'send_single_notification',
            'attendance',
            "Notification individuelle - Type: $notification_type, Élève: {$absence_info['eleve_nom']} {$absence_info['eleve_prenom']}, Statut: $status",
            $absence_id
        );
        
        $database->commit();
        
        if ($send_result['success']) {
            echo json_encode([
                'success' => true,
                'message' => 'Notification envoyée avec succès',
                'notification_id' => $notification_id,
                'type' => $notification_type,
                'recipient' => $notification_type === 'sms' ? $absence_info['telephone'] : $absence_info['email']
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Erreur lors de l\'envoi: ' . $send_result['message'],
                'notification_id' => $notification_id
            ]);
        }
        
    } catch (Exception $e) {
        $database->rollback();
        throw $e;
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

/**
 * Simuler l'envoi d'une notification
 * Dans un vrai système, ici on intégrerait avec des services SMS/Email réels
 */
function simulateNotificationSending($absence_info, $message, $type) {
    // Simulation d'envoi avec quelques cas d'échec aléatoires pour les tests
    $success_rate = 0.9; // 90% de réussite
    
    if (rand(1, 100) <= ($success_rate * 100)) {
        // Simulation d'envoi réussi
        if ($type === 'sms') {
            // Ici on appellerait un service SMS comme Twilio, Nexmo, etc.
            // $result = sendSMS($absence_info['telephone'], $message);
            
            return [
                'success' => true,
                'message' => 'SMS envoyé avec succès',
                'provider' => 'SMS_PROVIDER',
                'cost' => 0.05 // Coût simulé
            ];
            
        } elseif ($type === 'email') {
            // Ici on appellerait un service Email comme SendGrid, Mailgun, etc.
            // $result = sendEmail($absence_info['email'], 'Notification absence', $message);
            
            return [
                'success' => true,
                'message' => 'Email envoyé avec succès',
                'provider' => 'EMAIL_PROVIDER'
            ];
        }
    } else {
        // Simulation d'échec
        $error_messages = [
            'Numéro de téléphone invalide',
            'Adresse email invalide',
            'Service temporairement indisponible',
            'Quota d\'envoi dépassé',
            'Destinataire non joignable'
        ];
        
        return [
            'success' => false,
            'message' => $error_messages[array_rand($error_messages)]
        ];
    }
    
    return [
        'success' => false,
        'message' => 'Type de notification non supporté'
    ];
}
?>
