<?php
/**
 * Module Bibliothèque - Ajouter une réservation
 * Application de gestion scolaire - République Démocratique du Congo
 */

require_once '../../../config/config.php';
require_once '../../../config/database.php';
require_once '../../../includes/functions.php';

// Vérifier l'authentification et les permissions
requireLogin();
if (!checkPermission('library')) {
    showMessage('error', 'Accès refusé à cette page.');
    redirectTo('index.php');
}

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $livre_id = intval($_POST['livre_id'] ?? 0);
        $reserver_type = $_POST['reserver_type'] ?? '';
        $reserver_id = intval($_POST['reserver_id'] ?? 0);
        $notes = trim($_POST['notes'] ?? '');
        
        // Validation
        if (!$livre_id || !$reserver_type || !$reserver_id) {
            throw new Exception('Tous les champs obligatoires doivent être remplis.');
        }
        
        if (!in_array($reserver_type, ['eleve', 'personnel'])) {
            throw new Exception('Type de réservation invalide.');
        }
        
        // Vérifier que le livre existe et n'est pas disponible
        $livre = $database->query(
            "SELECT * FROM livres WHERE id = ?",
            [$livre_id]
        )->fetch();
        
        if (!$livre) {
            throw new Exception('Livre introuvable.');
        }
        
        if ($livre['status'] === 'disponible') {
            throw new Exception('Ce livre est disponible, vous pouvez l\'emprunter directement.');
        }
        
        // Vérifier qu'il n'y a pas déjà une réservation active pour ce livre par cette personne
        $existing = $database->query(
            "SELECT id FROM reservations_livres 
             WHERE livre_id = ? AND reserver_type = ? AND reserver_id = ? AND status = 'active'",
            [$livre_id, $reserver_type, $reserver_id]
        )->fetch();
        
        if ($existing) {
            throw new Exception('Une réservation active existe déjà pour ce livre par cette personne.');
        }
        
        // Récupérer la durée de validité des réservations
        $duree_reservation = $database->query(
            "SELECT valeur FROM parametres_bibliotheque WHERE cle = 'duree_reservation'"
        )->fetch()['valeur'] ?? 7;
        
        $date_expiration = date('Y-m-d', strtotime("+{$duree_reservation} days"));
        
        // Créer la réservation
        $database->execute(
            "INSERT INTO reservations_livres (
                livre_id, reserver_type, reserver_id, date_reservation, 
                date_expiration, status, notes, traite_par, created_at
            ) VALUES (?, ?, ?, CURDATE(), ?, 'active', ?, ?, NOW())",
            [$livre_id, $reserver_type, $reserver_id, $date_expiration, $notes, $_SESSION['user_id']]
        );
        
        showMessage('success', 'Réservation créée avec succès. Validité jusqu\'au ' . formatDate($date_expiration));
        redirectTo('index.php');
        
    } catch (Exception $e) {
        showMessage('error', 'Erreur : ' . $e->getMessage());
    }
}

// Récupérer les livres non disponibles
try {
    $livres_non_disponibles = $database->query(
        "SELECT l.*, cl.nom as categorie_nom, cl.couleur as categorie_couleur
         FROM livres l
         LEFT JOIN categories_livres cl ON l.categorie_id = cl.id
         WHERE l.status != 'disponible' AND l.status != 'retire'
         ORDER BY l.titre"
    )->fetchAll();
} catch (Exception $e) {
    $livres_non_disponibles = [];
}

$page_title = "Nouvelle Réservation";
include '../../../includes/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">
        <i class="fas fa-bookmark me-2"></i>
        Nouvelle Réservation
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

<div class="row">
    <div class="col-md-8">
        <form method="POST" class="needs-validation" novalidate>
            <div class="card shadow-sm">
                <div class="card-header bg-light">
                    <h5 class="mb-0">
                        <i class="fas fa-bookmark me-2"></i>
                        Informations de réservation
                    </h5>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label for="livre_id" class="form-label">
                            Livre à réserver <span class="text-danger">*</span>
                        </label>
                        <select class="form-select" id="livre_id" name="livre_id" required>
                            <option value="">-- Sélectionner un livre --</option>
                            <?php foreach ($livres_non_disponibles as $livre): ?>
                                <option value="<?php echo $livre['id']; ?>" 
                                        data-status="<?php echo $livre['status']; ?>"
                                        <?php echo (intval($_POST['livre_id'] ?? 0) === $livre['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($livre['titre']); ?> - <?php echo htmlspecialchars($livre['auteur']); ?>
                                    (<?php echo ucfirst($livre['status']); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text">Seuls les livres non disponibles peuvent être réservés.</div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="reserver_type" class="form-label">
                                Type de réservation <span class="text-danger">*</span>
                            </label>
                            <select class="form-select" id="reserver_type" name="reserver_type" required>
                                <option value="">-- Sélectionner --</option>
                                <option value="eleve" <?php echo ($_POST['reserver_type'] ?? '') === 'eleve' ? 'selected' : ''; ?>>
                                    Élève
                                </option>
                                <option value="personnel" <?php echo ($_POST['reserver_type'] ?? '') === 'personnel' ? 'selected' : ''; ?>>
                                    Personnel
                                </option>
                            </select>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="reserver_id" class="form-label">
                                ID de la personne <span class="text-danger">*</span>
                            </label>
                            <input type="number" class="form-control" id="reserver_id" name="reserver_id" 
                                   value="<?php echo htmlspecialchars($_POST['reserver_id'] ?? ''); ?>" 
                                   min="1" required>
                            <div class="form-text">ID de l'élève ou du personnel dans le système.</div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="notes" class="form-label">Notes</label>
                        <textarea class="form-control" id="notes" name="notes" rows="3" 
                                  placeholder="Notes sur la réservation..."><?php echo htmlspecialchars($_POST['notes'] ?? ''); ?></textarea>
                    </div>
                </div>
                
                <div class="card-footer bg-light">
                    <div class="d-flex justify-content-between">
                        <a href="../" class="btn btn-secondary">
                            <i class="fas fa-times me-1"></i>
                            Annuler
                        </a>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-bookmark me-1"></i>
                            Créer la réservation
                        </button>
                    </div>
                </div>
            </div>
        </form>
    </div>
    
    <div class="col-md-4">
        <!-- Aide -->
        <div class="card shadow-sm">
            <div class="card-header bg-light">
                <h5 class="mb-0">
                    <i class="fas fa-info-circle me-2"></i>
                    Aide
                </h5>
            </div>
            <div class="card-body">
                <div class="alert alert-info">
                    <h6><i class="fas fa-lightbulb me-2"></i>À propos des réservations</h6>
                    <ul class="mb-0 small">
                        <li>Les réservations sont valides 7 jours par défaut</li>
                        <li>Seuls les livres non disponibles peuvent être réservés</li>
                        <li>Une personne ne peut réserver le même livre qu'une fois</li>
                        <li>La réservation sera automatiquement convertie en emprunt quand le livre sera disponible</li>
                    </ul>
                </div>
                
                <?php if (!empty($livres_non_disponibles)): ?>
                    <div class="alert alert-warning">
                        <h6><i class="fas fa-exclamation-triangle me-2"></i>Livres non disponibles</h6>
                        <p class="mb-0 small">
                            <?php echo count($livres_non_disponibles); ?> livre(s) peuvent être réservé(s).
                        </p>
                    </div>
                <?php else: ?>
                    <div class="alert alert-success">
                        <h6><i class="fas fa-check-circle me-2"></i>Tous les livres sont disponibles</h6>
                        <p class="mb-0 small">
                            Aucune réservation n'est nécessaire actuellement.
                        </p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
// Validation Bootstrap
(function() {
    'use strict';
    window.addEventListener('load', function() {
        var forms = document.getElementsByClassName('needs-validation');
        var validation = Array.prototype.filter.call(forms, function(form) {
            form.addEventListener('submit', function(event) {
                if (form.checkValidity() === false) {
                    event.preventDefault();
                    event.stopPropagation();
                }
                form.classList.add('was-validated');
            }, false);
        });
    }, false);
})();
</script>

<?php include '../../../includes/footer.php'; ?>
