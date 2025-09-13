<?php
/**
 * Actions pour le module Carte d'Élève
 * Application de gestion scolaire - République Démocratique du Congo
 */

require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/config/database.php';
require_once dirname(__DIR__, 2) . '/includes/functions.php';
require_once dirname(__DIR__, 2) . '/includes/permissions-pages.php';
requireLogin();

requirePagePermissionFromDB('cartes_eleves', 'actions', 'edit', '../dashboard.php');

$action = $_POST['action'] ?? '';

switch ($action) {
    case 'generate':
        generateCards();
        break;
    case 'regenerate':
        regenerateCard();
        break;
    case 'suspend':
        suspendCard();
        break;
    case 'archive':
        archiveCard();
        break;
    case 'activate':
        activateCard();
        break;
    default:
        echo json_encode(['success' => false, 'message' => 'Action non reconnue']);
}

function generateCards() {
    global $database;
    
    try {
        $database->beginTransaction();
        
        $student_ids = json_decode($_POST['student_ids'] ?? '[]', true);
        $generation_type = $_POST['generation_type'] ?? 'all';
        $classe_id = $_POST['classe_id'] ?? '';
        
        if (empty($student_ids) && $generation_type === 'all' && $classe_id) {
            // Récupérer tous les élèves de la classe
            $sql = "SELECT e.id FROM eleves e 
                    JOIN inscriptions i ON e.id = i.eleve_id 
                    WHERE e.classe_id = ? AND i.status = 'inscrit'";
            $students = $database->query($sql, [$classe_id])->fetchAll();
            $student_ids = array_column($students, 'id');
        }
        
        $current_year = $database->query("SELECT * FROM annees_scolaires WHERE status = 'active'")->fetch();
        $generated_count = 0;
        
        foreach ($student_ids as $student_id) {
            // Vérifier si l'élève existe
            $student = $database->query("SELECT * FROM eleves WHERE id = ?", [$student_id])->fetch();
            if (!$student) continue;
            
            // Vérifier si une carte existe déjà pour cette année
            $existing_card = $database->query(
                "SELECT id FROM carte_eleve WHERE eleve_id = ? AND annee_scolaire_id = ?",
                [$student_id, $current_year['id']]
            )->fetch();
            
            if ($existing_card && $generation_type !== 'regenerate') {
                continue; // Passer si la carte existe déjà
            }
            
            // Supprimer l'ancienne carte si on régénère
            if ($existing_card && $generation_type === 'regenerate') {
                $database->execute("DELETE FROM carte_eleve WHERE id = ?", [$existing_card['id']]);
            }
            
            // Générer la nouvelle carte
            $carte_id = createStudentCard($student_id, $current_year['id']);
            if ($carte_id) {
                $generated_count++;
            }
        }
        
        $database->commit();
        
        // Log de l'action
        logAction('cartes_eleves', "Génération de $generated_count carte(s) d'élève", null);
        
        echo json_encode([
            'success' => true, 
            'message' => "Cartes générées avec succès",
            'count' => $generated_count
        ]);
        
    } catch (Exception $e) {
        $database->rollback();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function regenerateCard() {
    global $database;
    
    $carte_id = $_POST['carte_id'] ?? 0;
    
    try {
        $carte = $database->query("SELECT * FROM carte_eleve WHERE id = ?", [$carte_id])->fetch();
        if (!$carte) {
            throw new Exception('Carte non trouvée');
        }
        
        $database->beginTransaction();
        
        // Archiver l'ancienne carte
        $database->execute(
            "INSERT INTO carte_eleve_historique 
             (carte_id, eleve_id, annee_scolaire_id, numero_carte, qr_code, statut, date_generation, date_expiration, date_archivage)
             SELECT id, eleve_id, annee_scolaire_id, numero_carte, qr_code, 'archivée', date_generation, date_expiration, NOW()
             FROM carte_eleve WHERE id = ?",
            [$carte_id]
        );
        
        // Supprimer l'ancienne carte
        $database->execute("DELETE FROM carte_eleve WHERE id = ?", [$carte_id]);
        
        // Créer une nouvelle carte
        $new_carte_id = createStudentCard($carte['eleve_id'], $carte['annee_scolaire_id']);
        
        $database->commit();
        
        logAction('cartes_eleves', "Régénération de la carte d'élève ID: $carte_id", $new_carte_id);
        
        echo json_encode(['success' => true, 'message' => 'Carte régénérée avec succès']);
        
    } catch (Exception $e) {
        $database->rollback();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function suspendCard() {
    global $database;
    
    $carte_id = $_POST['carte_id'] ?? 0;
    
    try {
        $database->execute(
            "UPDATE carte_eleve SET statut = 'suspendue' WHERE id = ?",
            [$carte_id]
        );
        
        logAction('cartes_eleves', "Suspension de la carte d'élève ID: $carte_id", $carte_id);
        
        echo json_encode(['success' => true, 'message' => 'Carte suspendue avec succès']);
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function archiveCard() {
    global $database;
    
    $carte_id = $_POST['carte_id'] ?? 0;
    
    try {
        $database->beginTransaction();
        
        // Archiver la carte
        $database->execute(
            "INSERT INTO carte_eleve_historique 
             (carte_id, eleve_id, annee_scolaire_id, numero_carte, qr_code, statut, date_generation, date_expiration, date_archivage)
             SELECT id, eleve_id, annee_scolaire_id, numero_carte, qr_code, 'archivée', date_generation, date_expiration, NOW()
             FROM carte_eleve WHERE id = ?",
            [$carte_id]
        );
        
        $database->execute("DELETE FROM carte_eleve WHERE id = ?", [$carte_id]);
        
        $database->commit();
        
        logAction('cartes_eleves', "Archivage de la carte d'élève ID: $carte_id", $carte_id);
        
        echo json_encode(['success' => true, 'message' => 'Carte archivée avec succès']);
        
    } catch (Exception $e) {
        $database->rollback();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function activateCard() {
    global $database;
    
    $carte_id = $_POST['carte_id'] ?? 0;
    
    try {
        $database->execute(
            "UPDATE carte_eleve SET statut = 'active' WHERE id = ?",
            [$carte_id]
        );
        
        logAction('cartes_eleves', "Activation de la carte d'élève ID: $carte_id", $carte_id);
        
        echo json_encode(['success' => true, 'message' => 'Carte activée avec succès']);
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

/**
 * Créer une carte d'élève
 */
function createStudentCard($eleve_id, $annee_scolaire_id) {
    global $database;
    
    // Récupérer les informations de l'élève
    $student = $database->query(
        "SELECT e.*, c.nom as classe_nom, c.niveau, a.annee, a.date_debut, a.date_fin
         FROM eleves e
         LEFT JOIN classes c ON e.classe_id = c.id
         LEFT JOIN annees_scolaires a ON e.annee_scolaire_id = a.id
         WHERE e.id = ?",
        [$eleve_id]
    )->fetch();
    
    if (!$student) {
        return false;
    }
    
    // Générer le numéro de carte unique
    $numero_carte = generateCardNumber($annee_scolaire_id);
    
    // Générer les données du QR code
    $qr_data = [
        'type' => 'student_card',
        'student_id' => $eleve_id,
        'matricule' => $student['numero_matricule'],
        'card_number' => $numero_carte,
        'year' => $annee_scolaire_id,
        'timestamp' => time()
    ];
    
    $qr_code_data = json_encode($qr_data);
    $qr_code = generateQRCode($qr_code_data);
    
    // Calculer la date d'expiration (fin de l'année scolaire)
    $date_expiration = $student['date_fin'] . ' 23:59:59';
    
        // Insérer la carte
        $sql = "INSERT INTO carte_eleve 
                (eleve_id, annee_scolaire_id, numero_carte, qr_code, qr_data, statut, date_generation, date_expiration)
                VALUES (?, ?, ?, ?, ?, 'active', NOW(), ?)";
    
    $database->execute($sql, [
        $eleve_id,
        $annee_scolaire_id,
        $numero_carte,
        $qr_code,
        $qr_code_data,
        $date_expiration
    ]);
    
    return $database->lastInsertId();
}

/**
 * Générer un numéro de carte unique
 */
function generateCardNumber($annee_scolaire_id) {
    global $database;
    
    $year = date('Y');
    $pattern = 'CARD' . $year . '%';
    
    $last_card = $database->query(
        "SELECT numero_carte FROM carte_eleve WHERE numero_carte LIKE ? ORDER BY numero_carte DESC LIMIT 1",
        [$pattern]
    )->fetch();
    
    if ($last_card) {
        $last_number = intval(substr($last_card['numero_carte'], -4));
        $new_number = $last_number + 1;
    } else {
        $new_number = 1;
    }
    
    return 'CARD' . $year . str_pad($new_number, 4, '0', STR_PAD_LEFT);
}

/**
 * Générer un QR code
 */
function generateQRCode($data) {
    // Utiliser une bibliothèque QR code (ex: phpqrcode)
    // Pour l'instant, on retourne les données encodées en base64
    return base64_encode($data);
}
?>
