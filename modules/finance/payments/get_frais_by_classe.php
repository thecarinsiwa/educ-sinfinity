<?php
/**
 * Endpoint AJAX pour récupérer les frais scolaires selon la classe et le type
 * Application de gestion scolaire - République Démocratique du Congo
 */

// Démarrer la session
session_start();

// Nettoyer toute sortie précédente
if (ob_get_level()) {
    ob_clean();
}

// Inclure les fichiers nécessaires
require_once '../../../config/config.php';
require_once '../../../config/database.php';
require_once '../../../includes/functions.php';
require_once '../../../includes/permissions-pages.php';

// Vérifier l'authentification et les permissions
requireLogin();
requirePagePermissionFromDB('finance', 'payments/get_frais_by_classe', 'read', '../../dashboard.php');

// Vérifier les paramètres
$classe_id = (int)($_GET['classe_id'] ?? 0);
$type_frais = sanitizeInput($_GET['type_frais'] ?? '');

if (!$classe_id || empty($type_frais)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Paramètres manquants']);
    exit;
}

try {
    // Récupérer l'année scolaire actuelle
    $current_year = getCurrentAcademicYear();
    if (!$current_year) {
        throw new Exception('Aucune année scolaire active');
    }
    
    // Récupérer les frais pour cette classe et ce type
    $sql = "SELECT f.id, f.libelle, f.montant, f.description, f.obligatoire,
                   d.code as devise_code, d.symbole as devise_symbole, d.nom as devise_nom
            FROM frais_scolaires f
            LEFT JOIN devises d ON f.devise_id = d.id
            WHERE f.classe_id = ? 
            AND f.type_frais = ? 
            AND f.annee_scolaire_id = ?
            ORDER BY f.libelle";
    
    $frais = $database->query($sql, [$classe_id, $type_frais, $current_year['id']])->fetchAll();
    
    // Formater la réponse
    $response = [
        'success' => true,
        'frais' => array_map(function($frais) {
            return [
                'id' => $frais['id'],
                'libelle' => $frais['libelle'],
                'montant' => $frais['montant'],
                'description' => $frais['description'],
                'obligatoire' => (bool)$frais['obligatoire'],
                'devise_code' => $frais['devise_code'],
                'devise_symbole' => $frais['devise_symbole'],
                'devise_nom' => $frais['devise_nom']
            ];
        }, $frais)
    ];
    
    // Envoyer la réponse
    header('Content-Type: application/json');
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => 'Erreur lors de la récupération des frais: ' . $e->getMessage()
    ]);
}
?>
