<?php
/**
 * Intégration avec le module Paiements
 * Application de gestion scolaire - République Démocratique du Congo
 */

require_once '../../config/config.php';
require_once '../../includes/functions.php';

/**
 * Obtenir le solde d'un élève via QR Code
 */
function getStudentBalanceViaQR($student_id, $qr_data) {
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
        
        // Récupérer les détails des frais
        $frais_details = $database->query(
            "SELECT fs.*, f.nom as frais_nom, f.type as frais_type
             FROM frais_scolarite fs
             LEFT JOIN frais f ON fs.frais_id = f.id
             WHERE fs.eleve_id = ? AND fs.statut = 'actif'
             ORDER BY fs.created_at DESC",
            [$student_id]
        )->fetchAll();
        
        // Récupérer les détails des paiements
        $paiements_details = $database->query(
            "SELECT p.*, m.nom as mode_paiement
             FROM paiements p
             LEFT JOIN modes_paiement m ON p.mode_paiement_id = m.id
             WHERE p.eleve_id = ? AND p.statut = 'valide'
             ORDER BY p.date_paiement DESC",
            [$student_id]
        )->fetchAll();
        
        return [
            'success' => true,
            'balance' => [
                'solde' => $solde,
                'devise' => $devise,
                'frais_totaux' => $frais_totaux,
                'paiements_totaux' => $paiements_totaux,
                'frais_details' => $frais_details,
                'paiements_details' => $paiements_details,
                'last_update' => date('d/m/Y H:i')
            ],
            'student' => $student
        ];
        
    } catch (Exception $e) {
        return [
            'success' => false,
            'message' => $e->getMessage()
        ];
    }
}

/**
 * Enregistrer un paiement via QR Code
 */
function recordPaymentViaQR($student_id, $montant, $mode_paiement_id, $observations = '') {
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
        
        // Générer le numéro de paiement
        $numero_paiement = generatePaymentNumber();
        
        // Enregistrer le paiement
        $database->execute(
            "INSERT INTO paiements (
                eleve_id, numero_paiement, montant, mode_paiement_id, 
                date_paiement, statut, observations, created_at
            ) VALUES (?, ?, ?, ?, NOW(), 'valide', ?, NOW())",
            [$student_id, $numero_paiement, $montant, $mode_paiement_id, $observations]
        );
        
        $paiement_id = $database->lastInsertId();
        
        // Log de l'action
        logAction('paiements', "Paiement enregistré via QR Code pour l'élève: {$student['nom']} {$student['prenom']} - Montant: {$montant}", $paiement_id);
        
        return [
            'success' => true,
            'message' => 'Paiement enregistré avec succès',
            'paiement_id' => $paiement_id,
            'numero_paiement' => $numero_paiement
        ];
        
    } catch (Exception $e) {
        return [
            'success' => false,
            'message' => $e->getMessage()
        ];
    }
}

/**
 * Générer un numéro de paiement unique
 */
function generatePaymentNumber() {
    global $database;
    
    $year = date('Y');
    $pattern = 'PAY' . $year . '%';
    
    $last_payment = $database->query(
        "SELECT numero_paiement FROM paiements WHERE numero_paiement LIKE ? ORDER BY numero_paiement DESC LIMIT 1",
        [$pattern]
    )->fetch();
    
    if ($last_payment) {
        $last_number = intval(substr($last_payment['numero_paiement'], -4));
        $new_number = $last_number + 1;
    } else {
        $new_number = 1;
    }
    
    return 'PAY' . $year . str_pad($new_number, 4, '0', STR_PAD_LEFT);
}

/**
 * Obtenir l'historique des paiements d'un élève
 */
function getStudentPaymentHistory($student_id, $limit = 10) {
    global $database;
    
    try {
        $paiements = $database->query(
            "SELECT p.*, m.nom as mode_paiement
             FROM paiements p
             LEFT JOIN modes_paiement m ON p.mode_paiement_id = m.id
             WHERE p.eleve_id = ? AND p.statut = 'valide'
             ORDER BY p.date_paiement DESC
             LIMIT ?",
            [$student_id, $limit]
        )->fetchAll();
        
        return [
            'success' => true,
            'paiements' => $paiements
        ];
        
    } catch (Exception $e) {
        return [
            'success' => false,
            'message' => $e->getMessage()
        ];
    }
}

/**
 * Générer un reçu de paiement
 */
function generatePaymentReceipt($paiement_id) {
    global $database;
    
    try {
        $paiement = $database->query(
            "SELECT p.*, e.nom, e.prenom, e.numero_matricule, c.nom as classe_nom,
                    m.nom as mode_paiement
             FROM paiements p
             LEFT JOIN eleves e ON p.eleve_id = e.id
             LEFT JOIN classes c ON e.classe_id = c.id
             LEFT JOIN modes_paiement m ON p.mode_paiement_id = m.id
             WHERE p.id = ?",
            [$paiement_id]
        )->fetch();
        
        if (!$paiement) {
            throw new Exception('Paiement non trouvé');
        }
        
        return [
            'success' => true,
            'receipt' => $paiement
        ];
        
    } catch (Exception $e) {
        return [
            'success' => false,
            'message' => $e->getMessage()
        ];
    }
}
?>
