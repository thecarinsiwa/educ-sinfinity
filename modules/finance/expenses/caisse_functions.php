<?php
/**
 * Fonctions utilitaires pour la gestion des caisses
 * Application de gestion scolaire - République Démocratique du Congo
 */

/**
 * Enregistre automatiquement un paiement dans la caisse active
 */
function enregistrerPaiementDansCaisse($paiement_id, $eleve_id, $montant, $devise_id, $type_paiement, $date_paiement, $recu_numero) {
    global $database;
    
    try {
        // Trouver une session de caisse ouverte
        $session_ouverte = $database->query(
            "SELECT sc.id, c.devise_id as caisse_devise_id
             FROM sessions_caisse sc
             JOIN caisses c ON sc.caisse_id = c.id
             WHERE sc.statut = 'ouverte'
             ORDER BY sc.date_ouverture DESC
             LIMIT 1"
        )->fetch();
        
        if (!$session_ouverte) {
            // Aucune session ouverte, on ne peut pas enregistrer automatiquement
            return false;
        }
        
        // Déterminer la catégorie selon le type de paiement
        $categorie = 'autre';
        switch ($type_paiement) {
            case 'inscription':
            case 'mensualite':
            case 'examen':
            case 'uniforme':
            case 'transport':
            case 'cantine':
                $categorie = 'paiement_eleve';
                break;
            default:
                $categorie = 'autre';
        }
        
        // Récupérer les informations de l'élève
        $eleve = $database->query(
            "SELECT nom, prenom FROM eleves WHERE id = ?",
            [$eleve_id]
        )->fetch();
        
        if (!$eleve) {
            return false;
        }
        
        // Insérer le mouvement dans la caisse
        $database->execute(
            "INSERT INTO mouvements_caisse (session_caisse_id, type_mouvement, categorie, libelle, description, montant, devise_id, reference, date_mouvement, user_id) VALUES (?, 'entree', ?, ?, ?, ?, ?, ?, ?, ?)",
            [
                $session_ouverte['id'],
                $categorie,
                'Paiement - ' . ucfirst($type_paiement),
                'Paiement de ' . $eleve['prenom'] . ' ' . $eleve['nom'] . ' - Reçu: ' . $recu_numero,
                $montant,
                $devise_id,
                'PAIEMENT-' . $paiement_id,
                $date_paiement,
                $_SESSION['user_id'] ?? 1
            ]
        );
        
        return true;
        
    } catch (Exception $e) {
        error_log("Erreur lors de l'enregistrement du paiement dans la caisse: " . $e->getMessage());
        return false;
    }
}

/**
 * Enregistre automatiquement une dépense dans la caisse active
 */
function enregistrerDepenseDansCaisse($depense_id, $libelle, $montant, $devise_id, $date_depense, $fournisseur = '') {
    global $database;
    
    try {
        // Trouver une session de caisse ouverte
        $session_ouverte = $database->query(
            "SELECT sc.id, c.devise_id as caisse_devise_id
             FROM sessions_caisse sc
             JOIN caisses c ON sc.caisse_id = c.id
             WHERE sc.statut = 'ouverte'
             ORDER BY sc.date_ouverture DESC
             LIMIT 1"
        )->fetch();
        
        if (!$session_ouverte) {
            // Aucune session ouverte, on ne peut pas enregistrer automatiquement
            return false;
        }
        
        // Insérer le mouvement dans la caisse
        $database->execute(
            "INSERT INTO mouvements_caisse (session_caisse_id, type_mouvement, categorie, libelle, description, montant, devise_id, reference, date_mouvement, user_id) VALUES (?, 'sortie', 'depense_ecole', ?, ?, ?, ?, ?, ?, ?)",
            [
                $session_ouverte['id'],
                'Dépense - ' . $libelle,
                'Fournisseur: ' . $fournisseur,
                $montant,
                $devise_id,
                'DEPENSE-' . $depense_id,
                $date_depense,
                $_SESSION['user_id'] ?? 1
            ]
        );
        
        return true;
        
    } catch (Exception $e) {
        error_log("Erreur lors de l'enregistrement de la dépense dans la caisse: " . $e->getMessage());
        return false;
    }
}

/**
 * Vérifie si un paiement est déjà enregistré dans une caisse
 */
function paiementDejaEnregistre($paiement_id) {
    global $database;
    
    $mouvement = $database->query(
        "SELECT id FROM mouvements_caisse WHERE reference = ?",
        ['PAIEMENT-' . $paiement_id]
    )->fetch();
    
    return $mouvement !== false;
}

/**
 * Vérifie si une dépense est déjà enregistrée dans une caisse
 */
function depenseDejaEnregistree($depense_id) {
    global $database;
    
    $mouvement = $database->query(
        "SELECT id FROM mouvements_caisse WHERE reference = ?",
        ['DEPENSE-' . $depense_id]
    )->fetch();
    
    return $mouvement !== false;
}

/**
 * Obtient le solde courant d'une session de caisse
 */
function getSoldeCaisseCourant($session_id) {
    global $database;
    
    $session = $database->query(
        "SELECT solde_ouverture FROM sessions_caisse WHERE id = ?",
        [$session_id]
    )->fetch();
    
    if (!$session) {
        return 0;
    }
    
    $solde = $session['solde_ouverture'];
    
    // Calculer les mouvements
    $mouvements = $database->query(
        "SELECT type_mouvement, SUM(montant) as total 
         FROM mouvements_caisse 
         WHERE session_caisse_id = ? 
         GROUP BY type_mouvement",
        [$session_id]
    )->fetchAll();
    
    foreach ($mouvements as $mouvement) {
        if ($mouvement['type_mouvement'] === 'entree') {
            $solde += $mouvement['total'];
        } else {
            $solde -= $mouvement['total'];
        }
    }
    
    return $solde;
}

/**
 * Obtient les statistiques d'une session de caisse
 */
function getStatistiquesSession($session_id) {
    global $database;
    
    $session = $database->query(
        "SELECT sc.*, c.nom as caisse_nom, u.username as caissier
         FROM sessions_caisse sc
         JOIN caisses c ON sc.caisse_id = c.id
         JOIN users u ON sc.user_id = u.id
         WHERE sc.id = ?",
        [$session_id]
    )->fetch();
    
    if (!$session) {
        return null;
    }
    
    $mouvements = $database->query(
        "SELECT 
            COUNT(*) as total_mouvements,
            SUM(CASE WHEN type_mouvement = 'entree' THEN montant ELSE 0 END) as total_entrees,
            SUM(CASE WHEN type_mouvement = 'sortie' THEN montant ELSE 0 END) as total_sorties,
            SUM(CASE WHEN type_mouvement = 'entree' THEN 1 ELSE 0 END) as nb_entrees,
            SUM(CASE WHEN type_mouvement = 'sortie' THEN 1 ELSE 0 END) as nb_sorties
         FROM mouvements_caisse 
         WHERE session_caisse_id = ?",
        [$session_id]
    )->fetch();
    
    $solde_courant = $session['solde_ouverture'] + ($mouvements['total_entrees'] ?? 0) - ($mouvements['total_sorties'] ?? 0);
    
    return [
        'session' => $session,
        'mouvements' => $mouvements,
        'solde_courant' => $solde_courant
    ];
}

/**
 * Ferme automatiquement les sessions de caisse ouvertes depuis plus de 24h
 */
function fermerSessionsExpirees() {
    global $database;
    
    try {
        $sessions_expirees = $database->query(
            "SELECT id, caisse_id, user_id, solde_ouverture 
             FROM sessions_caisse 
             WHERE statut = 'ouverte' 
             AND date_ouverture < DATE_SUB(NOW(), INTERVAL 24 HOUR)"
        )->fetchAll();
        
        foreach ($sessions_expirees as $session) {
            $solde_courant = getSoldeCaisseCourant($session['id']);
            
            $database->execute(
                "UPDATE sessions_caisse 
                 SET date_fermeture = NOW(), 
                     solde_fermeture = ?, 
                     observation_fermeture = 'Fermeture automatique après 24h', 
                     statut = 'fermee' 
                 WHERE id = ?",
                [$solde_courant, $session['id']]
            );
        }
        
        return count($sessions_expirees);
        
    } catch (Exception $e) {
        error_log("Erreur lors de la fermeture automatique des sessions: " . $e->getMessage());
        return 0;
    }
}
?>
