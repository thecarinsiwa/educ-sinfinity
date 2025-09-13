<?php
/**
 * API pour récupérer les permissions d'un rôle
 * Application de gestion scolaire - République Démocratique du Congo
 */

header('Content-Type: application/json');

require_once dirname(__FILE__) . '/../../../config/config.php';
require_once dirname(__FILE__) . '/../../../config/database.php';
require_once dirname(__FILE__) . '/../../../includes/permissions.php';

try {
    // Vérifier que l'ID du rôle est fourni
    if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
        throw new Exception('ID du rôle invalide');
    }
    
    $role_id = (int)$_GET['id'];
    
    // Initialiser la connexion à la base de données
    $database = new Database();
    $database = $database->connect();
    
    // Récupérer le rôle et ses permissions
    $stmt = $database->prepare(
        "SELECT id, nom, description, permissions FROM roles WHERE id = ? AND actif = 1"
    );
    $stmt->execute([$role_id]);
    $role = $stmt->fetch();
    
    if (!$role) {
        throw new Exception('Rôle non trouvé ou inactif');
    }
    
    // Décoder les permissions JSON
    $permissions = json_decode($role['permissions'], true) ?: [];
    
    // Retourner les données en JSON
    echo json_encode([
        'success' => true,
        'role_id' => $role['id'],
        'role_name' => $role['nom'],
        'role_description' => $role['description'],
        'permissions' => $permissions
    ]);
    
} catch (Exception $e) {
    // Retourner une erreur en JSON
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
