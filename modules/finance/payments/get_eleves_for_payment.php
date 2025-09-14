<?php
/**
 * Endpoint AJAX pour récupérer la liste des élèves pour le DataTable
 * Module de gestion financière - Paiements
 */

// Désactiver l'affichage des erreurs pour éviter la pollution du JSON
error_reporting(0);
ini_set('display_errors', 0);

// Démarrer la session si pas déjà démarrée
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../../../config/config.php';
require_once '../../../config/database.php';
require_once '../../../includes/functions.php';
require_once '../../../includes/permissions-pages.php';

// Vérifier l'authentification et les permissions
requireLogin();
requirePagePermissionFromDB('finance', 'payments/get_eleves_for_payment', 'read', '../../dashboard.php');

// Obtenir l'année scolaire actuelle
$current_year = null;
if (function_exists('getCurrentAcademicYear')) {
    $current_year = getCurrentAcademicYear();
}

if (!$current_year) {
    http_response_code(400);
    echo json_encode(['error' => 'Aucune année scolaire active']);
    exit;
}

try {
    // Récupérer les statuts demandés
    $statuses = ['actif', 'transfere', 'abandonne', 'diplome', 'non-evalue', 'admis', 'evalue'];
    
    // Construire la requête SQL pour calculer la situation financière de chaque élève
    // CORRECTION: Utilisation de sous-requêtes pour éviter la multiplication par 2
    // 1. Récupère tous les frais programmés pour la classe de l'élève
    // 2. Récupère tous les paiements effectués par l'élève pour l'année scolaire active
    // 3. Calcule le solde restant = (Total frais programmés - Total payé)
    $sql = "SELECT 
                e.id,
                e.nom,
                e.prenom,
                e.numero_matricule,
                COALESCE(c.nom, 'Non assigné') as classe_nom,
                COALESCE(c.niveau, '-') as niveau,
                COALESCE(i.status, 'non-defini') as status,
                COALESCE(c.id, 0) as classe_id,
                -- 1. Total des frais programmés pour la classe de l'élève (sous-requête)
                COALESCE((
                    SELECT SUM(fs.montant) 
                    FROM frais_scolaires fs 
                    WHERE fs.classe_id = c.id AND fs.annee_scolaire_id = ?
                ), 0) as total_frais_programmes,
                -- 2. Total des paiements effectués par l'élève (sous-requête)
                COALESCE((
                    SELECT SUM(p.montant_devise_par_defaut) 
                    FROM paiements p 
                    WHERE p.eleve_id = e.id AND p.annee_scolaire_id = ?
                ), 0) as total_paye
            FROM eleves e
            JOIN inscriptions i ON e.id = i.eleve_id
            LEFT JOIN classes c ON i.classe_id = c.id
            WHERE i.annee_scolaire_id = ? AND i.status = 'inscrit'
            ORDER BY e.nom, e.prenom";
    
    // Préparer les paramètres (année scolaire pour frais, paiements et inscriptions)
    $params = [$current_year['id'], $current_year['id'], $current_year['id']];
    
    // Exécuter la requête en utilisant la méthode query de la classe Database
    $stmt = $database->query($sql, $params);
    $eleves = $stmt->fetchAll();
    
    // Obtenir la devise par défaut pour l'affichage
    $devise_par_defaut = getDefaultCurrency();
    $symbole_devise = $devise_par_defaut['symbole'] ?? 'FC';
    
    // Formater les données pour DataTables avec les informations financières
    $data = [];
    foreach ($eleves as $eleve) {
        // Nettoyer et valider les données financières
        $total_frais = (float)($eleve['total_frais_programmes'] ?? 0);      // Total des frais programmés pour la classe
        $total_paye = (float)($eleve['total_paye'] ?? 0);                  // Total des paiements effectués par l'élève
        $solde_restant = $total_frais - $total_paye;                       // Solde restant = Total frais - Total payé
        
        $data[] = [
            'id' => (int)$eleve['id'],
            'numero_matricule' => htmlspecialchars($eleve['numero_matricule'] ?? ''),
            'nom' => htmlspecialchars($eleve['nom'] ?? ''),
            'prenom' => htmlspecialchars($eleve['prenom'] ?? ''),
            'classe_nom' => htmlspecialchars($eleve['classe_nom'] ?? 'Non assigné'),
            'niveau' => htmlspecialchars($eleve['niveau'] ?? '-'),
            'status' => htmlspecialchars($eleve['status'] ?? 'non-defini'),
            'classe_id' => (int)$eleve['classe_id'],
            'total_frais' => $total_frais,
            'total_paye' => $total_paye,
            'solde_restant' => $solde_restant,
            'total_frais_formatted' => number_format($total_frais, 0, ',', ' ') . ' ' . $symbole_devise,
            'total_paye_formatted' => number_format($total_paye, 0, ',', ' ') . ' ' . $symbole_devise,
            'solde_restant_formatted' => number_format($solde_restant, 0, ',', ' ') . ' ' . $symbole_devise,
            'actions' => '' // Sera rempli par DataTables
        ];
    }
    
    // Réponse pour DataTables
    $response = [
        'draw' => isset($_POST['draw']) ? (int)$_POST['draw'] : 1,
        'recordsTotal' => count($data),
        'recordsFiltered' => count($data),
        'data' => $data
    ];
    
    // Envoyer la réponse JSON
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-cache, must-revalidate');
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    // Log de l'erreur (optionnel)
    error_log('Erreur get_eleves_for_payment: ' . $e->getMessage());
    
    // Réponse d'erreur pour DataTables
    $error_response = [
        'draw' => isset($_POST['draw']) ? (int)$_POST['draw'] : 1,
        'recordsTotal' => 0,
        'recordsFiltered' => 0,
        'data' => [],
        'error' => 'Erreur lors de la récupération des données'
    ];
    
    // Envoyer la réponse d'erreur
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($error_response, JSON_UNESCAPED_UNICODE);
}
?>
