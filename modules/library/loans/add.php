<?php
/**
 * Module Bibliothèque - Ajouter un emprunt
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
        $emprunteur_type = $_POST['emprunteur_type'] ?? '';
        $emprunteur_id = intval($_POST['emprunteur_id'] ?? 0);
        $duree_jours = intval($_POST['duree_jours'] ?? 14);
        $notes_emprunt = trim($_POST['notes_emprunt'] ?? '');
        
        // Validation
        if (!$livre_id || !$emprunteur_type || !$emprunteur_id) {
            throw new Exception('Tous les champs obligatoires doivent être remplis.');
        }
        
        if (!in_array($emprunteur_type, ['eleve', 'personnel'])) {
            throw new Exception('Type d\'emprunteur invalide.');
        }
        
        if ($duree_jours < 1 || $duree_jours > 90) {
            throw new Exception('La durée doit être entre 1 et 90 jours.');
        }
        
        // Vérifier que le livre existe et est disponible
        $livre = $database->query(
            "SELECT * FROM livres WHERE id = ? AND status = 'disponible'",
            [$livre_id]
        )->fetch();
        
        if (!$livre) {
            throw new Exception('Livre introuvable ou non disponible.');
        }
        
        // Vérifier les limites d'emprunt
        $max_emprunts_key = $emprunteur_type === 'eleve' ? 'max_emprunts_eleve' : 'max_emprunts_personnel';
        $max_emprunts = $database->query(
            "SELECT valeur FROM parametres_bibliotheque WHERE cle = ?",
            [$max_emprunts_key]
        )->fetch()['valeur'] ?? 3;
        
        $emprunts_actifs = $database->query(
            "SELECT COUNT(*) as count FROM emprunts_livres 
             WHERE emprunteur_type = ? AND emprunteur_id = ? AND status = 'en_cours'",
            [$emprunteur_type, $emprunteur_id]
        )->fetch()['count'];
        
        if ($emprunts_actifs >= $max_emprunts) {
            throw new Exception("Cette personne a atteint la limite de $max_emprunts emprunt(s) simultané(s).");
        }
        
        // Calculer les dates
        $date_emprunt = date('Y-m-d');
        $date_retour_prevue = date('Y-m-d', strtotime("+{$duree_jours} days"));
        
        // Créer l'emprunt
        $database->execute(
            "INSERT INTO emprunts_livres (
                livre_id, emprunteur_type, emprunteur_id, date_emprunt,
                date_retour_prevue, duree_jours, status, notes_emprunt,
                traite_par, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, 'en_cours', ?, ?, NOW())",
            [$livre_id, $emprunteur_type, $emprunteur_id, $date_emprunt, 
             $date_retour_prevue, $duree_jours, $notes_emprunt, $_SESSION['user_id']]
        );
        
        // Mettre à jour le statut du livre
        $database->execute(
            "UPDATE livres SET status = 'emprunte', exemplaires_disponibles = exemplaires_disponibles - 1 
             WHERE id = ?",
            [$livre_id]
        );
        
        // Vérifier s'il y a une réservation pour ce livre et la convertir
        $reservation = $database->query(
            "SELECT * FROM reservations_livres 
             WHERE livre_id = ? AND reserver_type = ? AND reserver_id = ? AND status = 'active'
             ORDER BY date_reservation ASC LIMIT 1",
            [$livre_id, $emprunteur_type, $emprunteur_id]
        )->fetch();
        
        if ($reservation) {
            $database->execute(
                "UPDATE reservations_livres SET status = 'convertie' WHERE id = ?",
                [$reservation['id']]
            );
        }
        
        showMessage('success', 'Emprunt créé avec succès. Retour prévu le ' . formatDate($date_retour_prevue));
        redirectTo('index.php');
        
    } catch (Exception $e) {
        showMessage('error', 'Erreur : ' . $e->getMessage());
    }
}

// Récupérer les livres disponibles
try {
    $livres_disponibles = $database->query(
        "SELECT l.*, cl.nom as categorie_nom, cl.couleur as categorie_couleur
         FROM livres l
         LEFT JOIN categories_livres cl ON l.categorie_id = cl.id
         WHERE l.status = 'disponible' AND l.exemplaires_disponibles > 0
         ORDER BY l.titre"
    )->fetchAll();
} catch (Exception $e) {
    $livres_disponibles = [];
}

// Récupérer les paramètres par défaut
try {
    $duree_eleve = $database->query(
        "SELECT valeur FROM parametres_bibliotheque WHERE cle = 'duree_emprunt_eleve'"
    )->fetch()['valeur'] ?? 14;
    
    $duree_personnel = $database->query(
        "SELECT valeur FROM parametres_bibliotheque WHERE cle = 'duree_emprunt_personnel'"
    )->fetch()['valeur'] ?? 21;
} catch (Exception $e) {
    $duree_eleve = 14;
    $duree_personnel = 21;
}

$page_title = "Nouvel Emprunt";
include '../../../includes/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">
        <i class="fas fa-hand-holding me-2"></i>
        Nouvel Emprunt
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
                        <i class="fas fa-hand-holding me-2"></i>
                        Informations d'emprunt
                    </h5>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label for="livre_id" class="form-label">
                            Livre à emprunter <span class="text-danger">*</span>
                        </label>
                        <select class="form-select" id="livre_id" name="livre_id" required>
                            <option value="">-- Sélectionner un livre --</option>
                            <?php foreach ($livres_disponibles as $livre): ?>
                                <option value="<?php echo $livre['id']; ?>" 
                                        <?php echo (intval($_POST['livre_id'] ?? 0) === $livre['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($livre['titre']); ?> - <?php echo htmlspecialchars($livre['auteur']); ?>
                                    <?php if ($livre['categorie_nom']): ?>
                                        (<?php echo htmlspecialchars($livre['categorie_nom']); ?>)
                                    <?php endif; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="emprunteur_type" class="form-label">
                                Type d'emprunteur <span class="text-danger">*</span>
                            </label>
                            <select class="form-select" id="emprunteur_type" name="emprunteur_type" required>
                                <option value="">-- Sélectionner --</option>
                                <option value="eleve" <?php echo ($_POST['emprunteur_type'] ?? '') === 'eleve' ? 'selected' : ''; ?>>
                                    Élève
                                </option>
                                <option value="personnel" <?php echo ($_POST['emprunteur_type'] ?? '') === 'personnel' ? 'selected' : ''; ?>>
                                    Personnel
                                </option>
                            </select>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="emprunteur_id" class="form-label">
                                ID de l'emprunteur <span class="text-danger">*</span>
                            </label>
                            <input type="number" class="form-control" id="emprunteur_id" name="emprunteur_id" 
                                   value="<?php echo htmlspecialchars($_POST['emprunteur_id'] ?? ''); ?>" 
                                   min="1" required>
                            <div class="form-text">ID de l'élève ou du personnel dans le système.</div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="duree_jours" class="form-label">
                            Durée d'emprunt (jours) <span class="text-danger">*</span>
                        </label>
                        <input type="number" class="form-control" id="duree_jours" name="duree_jours" 
                               value="<?php echo htmlspecialchars($_POST['duree_jours'] ?? '14'); ?>" 
                               min="1" max="90" required>
                        <div class="form-text">
                            Durée par défaut : <?php echo $duree_eleve; ?> jours (élèves), <?php echo $duree_personnel; ?> jours (personnel)
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="notes_emprunt" class="form-label">Notes</label>
                        <textarea class="form-control" id="notes_emprunt" name="notes_emprunt" rows="3" 
                                  placeholder="Notes sur l'emprunt..."><?php echo htmlspecialchars($_POST['notes_emprunt'] ?? ''); ?></textarea>
                    </div>
                </div>
                
                <div class="card-footer bg-light">
                    <div class="d-flex justify-content-between">
                        <a href="../" class="btn btn-secondary">
                            <i class="fas fa-times me-1"></i>
                            Annuler
                        </a>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-hand-holding me-1"></i>
                            Créer l'emprunt
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
                    <h6><i class="fas fa-lightbulb me-2"></i>Règles d'emprunt</h6>
                    <ul class="mb-0 small">
                        <li>Durée par défaut : <?php echo $duree_eleve; ?> jours (élèves)</li>
                        <li>Durée par défaut : <?php echo $duree_personnel; ?> jours (personnel)</li>
                        <li>Maximum 3 emprunts simultanés (élèves)</li>
                        <li>Maximum 5 emprunts simultanés (personnel)</li>
                        <li>Pénalité de retard : 100 FC/jour</li>
                    </ul>
                </div>
                
                <?php if (!empty($livres_disponibles)): ?>
                    <div class="alert alert-success">
                        <h6><i class="fas fa-check-circle me-2"></i>Livres disponibles</h6>
                        <p class="mb-0 small">
                            <?php echo count($livres_disponibles); ?> livre(s) disponible(s) pour l'emprunt.
                        </p>
                    </div>
                <?php else: ?>
                    <div class="alert alert-warning">
                        <h6><i class="fas fa-exclamation-triangle me-2"></i>Aucun livre disponible</h6>
                        <p class="mb-0 small">
                            Tous les livres sont actuellement empruntés.
                        </p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
// Mise à jour automatique de la durée selon le type d'emprunteur
document.getElementById('emprunteur_type').addEventListener('change', function() {
    const dureeInput = document.getElementById('duree_jours');
    if (this.value === 'eleve') {
        dureeInput.value = <?php echo $duree_eleve; ?>;
    } else if (this.value === 'personnel') {
        dureeInput.value = <?php echo $duree_personnel; ?>;
    }
});

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
