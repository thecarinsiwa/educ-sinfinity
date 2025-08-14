<?php
/**
 * Module Bibliothèque - Paramètres
 * Application de gestion scolaire - République Démocratique du Congo
 */

require_once '../../../config/config.php';
require_once '../../../config/database.php';
require_once '../../../includes/functions.php';

// Vérifier l'authentification et les permissions
requireLogin();
if (!checkPermission('admin')) {
    showMessage('error', 'Accès refusé. Seuls les administrateurs peuvent modifier les paramètres.');
    redirectTo('../index.php');
}

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $parametres = $_POST['parametres'] ?? [];
        
        if (empty($parametres)) {
            throw new Exception('Aucun paramètre à mettre à jour.');
        }
        
        foreach ($parametres as $cle => $valeur) {
            // Validation selon le type de paramètre
            switch ($cle) {
                case 'duree_emprunt_eleve':
                case 'duree_emprunt_personnel':
                case 'duree_reservation':
                case 'rappel_avant_echeance':
                    $valeur = intval($valeur);
                    if ($valeur < 1 || $valeur > 365) {
                        throw new Exception("La valeur pour '$cle' doit être entre 1 et 365 jours.");
                    }
                    break;
                    
                case 'max_emprunts_eleve':
                case 'max_emprunts_personnel':
                    $valeur = intval($valeur);
                    if ($valeur < 1 || $valeur > 20) {
                        throw new Exception("La valeur pour '$cle' doit être entre 1 et 20.");
                    }
                    break;
                    
                case 'penalite_retard_jour':
                case 'penalite_perte':
                    $valeur = floatval($valeur);
                    if ($valeur < 0 || $valeur > 100000) {
                        throw new Exception("La valeur pour '$cle' doit être entre 0 et 100000 FC.");
                    }
                    break;
                    
                case 'notifications_actives':
                    $valeur = $valeur ? '1' : '0';
                    break;
                    
                case 'bibliothecaire_principal':
                    $valeur = intval($valeur);
                    if ($valeur < 1) {
                        throw new Exception("L'ID du bibliothécaire principal doit être valide.");
                    }
                    break;
            }
            
            // Mettre à jour le paramètre
            $database->execute(
                "UPDATE parametres_bibliotheque SET valeur = ?, updated_at = NOW() WHERE cle = ?",
                [$valeur, $cle]
            );
        }
        
        showMessage('success', 'Paramètres mis à jour avec succès.');
        
    } catch (Exception $e) {
        showMessage('error', 'Erreur : ' . $e->getMessage());
    }
}

// Récupérer tous les paramètres
try {
    $parametres_raw = $database->query(
        "SELECT * FROM parametres_bibliotheque ORDER BY cle"
    )->fetchAll();
    
    $parametres = [];
    foreach ($parametres_raw as $param) {
        $parametres[$param['cle']] = $param;
    }
} catch (Exception $e) {
    $parametres = [];
    showMessage('error', 'Erreur lors du chargement des paramètres : ' . $e->getMessage());
}

// Récupérer les utilisateurs pour le bibliothécaire principal
try {
    $users = $database->query(
        "SELECT id, username, nom, prenom FROM users WHERE role IN ('admin', 'teacher') ORDER BY nom, prenom"
    )->fetchAll();
} catch (Exception $e) {
    $users = [];
}

$page_title = "Paramètres de la Bibliothèque";
include '../../../includes/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">
        <i class="fas fa-cog me-2"></i>
        Paramètres de la Bibliothèque
    </h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <div class="btn-group me-2">
            <a href="../" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-1"></i>
                Retour à la bibliothèque
            </a>
        </div>
    </div>
</div>

<form method="POST">
    <div class="row">
        <!-- Paramètres d'emprunt -->
        <div class="col-md-6 mb-4">
            <div class="card shadow-sm">
                <div class="card-header bg-light">
                    <h5 class="mb-0">
                        <i class="fas fa-hand-holding me-2"></i>
                        Paramètres d'emprunt
                    </h5>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label for="duree_emprunt_eleve" class="form-label">
                            Durée d'emprunt - Élèves (jours)
                        </label>
                        <input type="number" class="form-control" id="duree_emprunt_eleve" 
                               name="parametres[duree_emprunt_eleve]" 
                               value="<?php echo htmlspecialchars($parametres['duree_emprunt_eleve']['valeur'] ?? '14'); ?>" 
                               min="1" max="365" required>
                        <div class="form-text">
                            <?php echo htmlspecialchars($parametres['duree_emprunt_eleve']['description'] ?? ''); ?>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="duree_emprunt_personnel" class="form-label">
                            Durée d'emprunt - Personnel (jours)
                        </label>
                        <input type="number" class="form-control" id="duree_emprunt_personnel" 
                               name="parametres[duree_emprunt_personnel]" 
                               value="<?php echo htmlspecialchars($parametres['duree_emprunt_personnel']['valeur'] ?? '21'); ?>" 
                               min="1" max="365" required>
                        <div class="form-text">
                            <?php echo htmlspecialchars($parametres['duree_emprunt_personnel']['description'] ?? ''); ?>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="max_emprunts_eleve" class="form-label">
                            Maximum d'emprunts - Élèves
                        </label>
                        <input type="number" class="form-control" id="max_emprunts_eleve" 
                               name="parametres[max_emprunts_eleve]" 
                               value="<?php echo htmlspecialchars($parametres['max_emprunts_eleve']['valeur'] ?? '3'); ?>" 
                               min="1" max="20" required>
                        <div class="form-text">
                            <?php echo htmlspecialchars($parametres['max_emprunts_eleve']['description'] ?? ''); ?>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="max_emprunts_personnel" class="form-label">
                            Maximum d'emprunts - Personnel
                        </label>
                        <input type="number" class="form-control" id="max_emprunts_personnel" 
                               name="parametres[max_emprunts_personnel]" 
                               value="<?php echo htmlspecialchars($parametres['max_emprunts_personnel']['valeur'] ?? '5'); ?>" 
                               min="1" max="20" required>
                        <div class="form-text">
                            <?php echo htmlspecialchars($parametres['max_emprunts_personnel']['description'] ?? ''); ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Paramètres de pénalités -->
        <div class="col-md-6 mb-4">
            <div class="card shadow-sm">
                <div class="card-header bg-light">
                    <h5 class="mb-0">
                        <i class="fas fa-coins me-2"></i>
                        Pénalités et amendes
                    </h5>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label for="penalite_retard_jour" class="form-label">
                            Pénalité par jour de retard (FC)
                        </label>
                        <input type="number" class="form-control" id="penalite_retard_jour" 
                               name="parametres[penalite_retard_jour]" 
                               value="<?php echo htmlspecialchars($parametres['penalite_retard_jour']['valeur'] ?? '100'); ?>" 
                               min="0" max="100000" step="50" required>
                        <div class="form-text">
                            <?php echo htmlspecialchars($parametres['penalite_retard_jour']['description'] ?? ''); ?>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="penalite_perte" class="form-label">
                            Pénalité pour perte d'un livre (FC)
                        </label>
                        <input type="number" class="form-control" id="penalite_perte" 
                               name="parametres[penalite_perte]" 
                               value="<?php echo htmlspecialchars($parametres['penalite_perte']['valeur'] ?? '5000'); ?>" 
                               min="0" max="100000" step="500" required>
                        <div class="form-text">
                            <?php echo htmlspecialchars($parametres['penalite_perte']['description'] ?? ''); ?>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="duree_reservation" class="form-label">
                            Durée de validité des réservations (jours)
                        </label>
                        <input type="number" class="form-control" id="duree_reservation" 
                               name="parametres[duree_reservation]" 
                               value="<?php echo htmlspecialchars($parametres['duree_reservation']['valeur'] ?? '7'); ?>" 
                               min="1" max="30" required>
                        <div class="form-text">
                            <?php echo htmlspecialchars($parametres['duree_reservation']['description'] ?? ''); ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Paramètres de notifications -->
        <div class="col-md-6 mb-4">
            <div class="card shadow-sm">
                <div class="card-header bg-light">
                    <h5 class="mb-0">
                        <i class="fas fa-bell me-2"></i>
                        Notifications et rappels
                    </h5>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="notifications_actives" 
                                   name="parametres[notifications_actives]" value="1"
                                   <?php echo ($parametres['notifications_actives']['valeur'] ?? '1') === '1' ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="notifications_actives">
                                Activer les notifications de rappel
                            </label>
                        </div>
                        <div class="form-text">
                            <?php echo htmlspecialchars($parametres['notifications_actives']['description'] ?? ''); ?>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="rappel_avant_echeance" class="form-label">
                            Rappel avant échéance (jours)
                        </label>
                        <input type="number" class="form-control" id="rappel_avant_echeance" 
                               name="parametres[rappel_avant_echeance]" 
                               value="<?php echo htmlspecialchars($parametres['rappel_avant_echeance']['valeur'] ?? '3'); ?>" 
                               min="1" max="30" required>
                        <div class="form-text">
                            <?php echo htmlspecialchars($parametres['rappel_avant_echeance']['description'] ?? ''); ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Paramètres généraux -->
        <div class="col-md-6 mb-4">
            <div class="card shadow-sm">
                <div class="card-header bg-light">
                    <h5 class="mb-0">
                        <i class="fas fa-user-cog me-2"></i>
                        Paramètres généraux
                    </h5>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label for="bibliothecaire_principal" class="form-label">
                            Bibliothécaire principal
                        </label>
                        <select class="form-select" id="bibliothecaire_principal" 
                                name="parametres[bibliothecaire_principal]" required>
                            <option value="">-- Sélectionner --</option>
                            <?php foreach ($users as $user): ?>
                                <option value="<?php echo $user['id']; ?>" 
                                        <?php echo ($parametres['bibliothecaire_principal']['valeur'] ?? '') == $user['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($user['nom'] . ' ' . $user['prenom'] . ' (' . $user['username'] . ')'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text">
                            <?php echo htmlspecialchars($parametres['bibliothecaire_principal']['description'] ?? ''); ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Boutons d'action -->
    <div class="card shadow-sm">
        <div class="card-body">
            <div class="d-flex justify-content-between">
                <a href="../" class="btn btn-secondary">
                    <i class="fas fa-times me-1"></i>
                    Annuler
                </a>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save me-1"></i>
                    Enregistrer les paramètres
                </button>
            </div>
        </div>
    </div>
</form>

<!-- Aide -->
<div class="card shadow-sm mt-4">
    <div class="card-header bg-light">
        <h5 class="mb-0">
            <i class="fas fa-info-circle me-2"></i>
            Aide et informations
        </h5>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-6">
                <h6><i class="fas fa-lightbulb me-2"></i>Conseils de configuration</h6>
                <ul class="small">
                    <li>Durées d'emprunt recommandées : 14 jours (élèves), 21 jours (personnel)</li>
                    <li>Limites d'emprunts : 3 (élèves), 5 (personnel)</li>
                    <li>Pénalité de retard : 100-500 FC par jour selon votre politique</li>
                    <li>Réservations : 7 jours de validité recommandés</li>
                </ul>
            </div>
            <div class="col-md-6">
                <h6><i class="fas fa-exclamation-triangle me-2"></i>Important</h6>
                <ul class="small">
                    <li>Les modifications prennent effet immédiatement</li>
                    <li>Les emprunts en cours ne sont pas affectés</li>
                    <li>Seuls les administrateurs peuvent modifier ces paramètres</li>
                    <li>Sauvegardez vos paramètres avant modification</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<?php include '../../../includes/footer.php'; ?>
