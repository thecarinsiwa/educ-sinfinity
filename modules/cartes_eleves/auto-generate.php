<?php
/**
 * Génération automatique de cartes d'élèves
 * Application de gestion scolaire - République Démocratique du Congo
 */

require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/includes/functions.php';
require_once __DIR__ . '/qr-generator.php';

/**
 * Générer automatiquement une carte d'élève lors de l'inscription
 */
function autoGenerateStudentCard($eleve_id, $annee_scolaire_id) {
    global $database;
    
    try {
        // Vérifier si une carte existe déjà pour cette année
        $existing_card = $database->query(
            "SELECT id FROM carte_eleve WHERE eleve_id = ? AND annee_scolaire_id = ?",
            [$eleve_id, $annee_scolaire_id]
        )->fetch();
        
        if ($existing_card) {
            return $existing_card['id']; // Carte déjà existante
        }
        
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
        
        // Récupérer l'année scolaire pour le QR code
        $annee_scolaire = $student['annee'];
        
        // Générer le QR code PNG
        $qrGenerator = new QRCodeGenerator($database);
        $qrResult = $qrGenerator->generateQRCode(
            $eleve_id, 
            $student['numero_matricule'], 
            $annee_scolaire
        );
        
        if (!$qrResult['success']) {
            throw new Exception('Erreur lors de la génération du QR code: ' . $qrResult['error']);
        }
        
        // Générer les données du QR code (format JSON pour compatibilité)
        $qr_data = [
            'type' => 'student_card',
            'student_id' => $eleve_id,
            'matricule' => $student['numero_matricule'],
            'card_number' => $numero_carte,
            'year' => $annee_scolaire_id,
            'timestamp' => time()
        ];
        
        $qr_code_data = json_encode($qr_data);
        $qr_code = $qrResult['qr_data'] ?? 'SINF|' . $annee_scolaire . '|' . $student['numero_matricule']; // Données au format ECOLE_ID|ANNEE|MATRICULE
        
        // Calculer la date d'expiration (fin de l'année scolaire)
        $date_expiration = $student['date_fin'] . ' 23:59:59';
        
        // Insérer la carte avec le chemin du QR code PNG
        $sql = "INSERT INTO carte_eleve 
                (eleve_id, annee_scolaire_id, numero_carte, qr_code, qr_data, qr_code_path, annee_scolaire, statut, date_generation, date_expiration)
                VALUES (?, ?, ?, ?, ?, ?, ?, 'active', NOW(), ?)";
        
        $database->execute($sql, [
            $eleve_id,
            $annee_scolaire_id,
            $numero_carte,
            $qr_code,
            $qr_code_data,
            $qrGenerator->getRelativePath($qrResult['filepath']),
            $annee_scolaire,
            $date_expiration
        ]);
        
        $carte_id = $database->lastInsertId();
        
        // Log de l'action
        logAction('cartes_eleves', "Génération automatique de carte pour l'élève ID: $eleve_id", $carte_id);
        
        return $carte_id;
        
    } catch (Exception $e) {
        error_log("Erreur génération carte élève: " . $e->getMessage());
        return false;
    }
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

/**
 * Archiver les cartes de l'année précédente
 */
function archiveOldCards($current_year_id) {
    global $database;
    
    try {
        $database->beginTransaction();
        
        // Récupérer les cartes de l'année précédente
        $old_cards = $database->query(
            "SELECT * FROM carte_eleve WHERE annee_scolaire_id != ? AND statut = 'active'",
            [$current_year_id]
        )->fetchAll();
        
        foreach ($old_cards as $card) {
            // Archiver la carte
            $database->execute(
                "INSERT INTO carte_eleve_historique 
                 (carte_id, eleve_id, annee_scolaire_id, numero_carte, qr_code, statut, date_generation, date_expiration, date_archivage)
                 VALUES (?, ?, ?, ?, ?, 'archivée', ?, ?, NOW())",
                [
                    $card['id'], $card['eleve_id'], $card['annee_scolaire_id'],
                    $card['numero_carte'], $card['qr_code'], $card['date_generation'],
                    $card['date_expiration']
                ]
            );
            
            // Supprimer la carte active
            $database->execute("DELETE FROM carte_eleve WHERE id = ?", [$card['id']]);
        }
        
        $database->commit();
        
        logAction('cartes_eleves', "Archivage de " . count($old_cards) . " carte(s) de l'année précédente");
        
        return count($old_cards);
        
    } catch (Exception $e) {
        $database->rollback();
        error_log("Erreur archivage cartes: " . $e->getMessage());
        return false;
    }
}

/**
 * Générer les cartes pour tous les élèves d'une classe
 */
function generateCardsForClass($classe_id, $annee_scolaire_id) {
    global $database;
    
    try {
        // Récupérer tous les élèves actifs de la classe
        $students = $database->query(
            "SELECT e.id FROM eleves e 
             JOIN inscriptions i ON e.id = i.eleve_id 
             WHERE e.classe_id = ? AND i.status = 'inscrit' AND i.annee_scolaire_id = ?",
            [$classe_id, $annee_scolaire_id]
        )->fetchAll();
        
        $generated_count = 0;
        
        foreach ($students as $student) {
            $carte_id = autoGenerateStudentCard($student['id'], $annee_scolaire_id);
            if ($carte_id) {
                $generated_count++;
            }
        }
        
        return $generated_count;
        
    } catch (Exception $e) {
        error_log("Erreur génération cartes classe: " . $e->getMessage());
        return false;
    }
}

/**
 * Vérifier et mettre à jour le statut des cartes expirées
 */
function updateExpiredCards() {
    global $database;
    
    try {
        $database->execute(
            "UPDATE carte_eleve SET statut = 'expiree' 
             WHERE statut = 'active' AND date_expiration < NOW()"
        );
        
        $updated = $database->query("SELECT ROW_COUNT() as count")->fetch();
        
        if ($updated['count'] > 0) {
            logAction('cartes_eleves', "Mise à jour de {$updated['count']} carte(s) expirée(s)");
        }
        
        return $updated['count'];
        
    } catch (Exception $e) {
        error_log("Erreur mise à jour cartes expirées: " . $e->getMessage());
        return false;
    }
}
?>
