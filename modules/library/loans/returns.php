<?php
/**
 * Module Bibliothèque - Gestion des retours
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

// Traitement des retours
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $action = $_POST['action'] ?? '';
        
        if ($action === 'return_book') {
            $emprunt_id = intval($_POST['emprunt_id'] ?? 0);
            $notes_retour = trim($_POST['notes_retour'] ?? '');
            $penalite = floatval($_POST['penalite'] ?? 0);
            
            if (!$emprunt_id) {
                throw new Exception('ID d\'emprunt invalide.');
            }
            
            // Récupérer l'emprunt
            $emprunt = $database->query(
                "SELECT * FROM emprunts_livres WHERE id = ? AND status = 'en_cours'",
                [$emprunt_id]
            )->fetch();
            
            if (!$emprunt) {
                throw new Exception('Emprunt introuvable ou déjà retourné.');
            }
            
            // Mettre à jour l'emprunt
            $database->execute(
                "UPDATE emprunts_livres SET 
                    status = 'rendu', 
                    date_retour_effective = CURDATE(), 
                    notes_retour = ?, 
                    penalite = ?, 
                    rendu_par = ?,
                    updated_at = NOW()
                 WHERE id = ?",
                [$notes_retour, $penalite, $_SESSION['user_id'], $emprunt_id]
            );
            
            // Mettre à jour le statut du livre
            $database->execute(
                "UPDATE livres SET 
                    status = 'disponible', 
                    exemplaires_disponibles = exemplaires_disponibles + 1 
                 WHERE id = ?",
                [$emprunt['livre_id']]
            );
            
            // Créer une pénalité si nécessaire
            if ($penalite > 0) {
                $database->execute(
                    "INSERT INTO penalites_bibliotheque (
                        emprunt_id, type_penalite, montant, description, 
                        status, date_penalite, traite_par, created_at
                    ) VALUES (?, 'retard', ?, ?, 'impayee', CURDATE(), ?, NOW())",
                    [$emprunt_id, $penalite, $notes_retour, $_SESSION['user_id']]
                );
            }
            
            showMessage('success', 'Livre retourné avec succès.' . ($penalite > 0 ? " Pénalité : {$penalite} FC" : ''));
        }
        
    } catch (Exception $e) {
        showMessage('error', 'Erreur : ' . $e->getMessage());
    }
}

// Récupérer les emprunts en cours
try {
    $emprunts_en_cours = $database->query(
        "SELECT el.*, l.titre, l.auteur, l.isbn,
                CONCAT('Emprunteur ', el.emprunteur_id) as emprunteur_nom,
                CASE 
                    WHEN el.emprunteur_type = 'eleve' THEN 'Élève'
                    WHEN el.emprunteur_type = 'personnel' THEN 'Personnel'
                    ELSE 'Inconnu'
                END as info_supplementaire,
                DATEDIFF(CURDATE(), el.date_retour_prevue) as jours_retard
         FROM emprunts_livres el
         JOIN livres l ON el.livre_id = l.id
         WHERE el.status = 'en_cours'
         ORDER BY 
            CASE WHEN el.date_retour_prevue < CURDATE() THEN 1 ELSE 2 END,
            el.date_retour_prevue ASC"
    )->fetchAll();
} catch (Exception $e) {
    $emprunts_en_cours = [];
    showMessage('error', 'Erreur lors du chargement : ' . $e->getMessage());
}

// Récupérer les paramètres de pénalité
try {
    $penalite_par_jour = $database->query(
        "SELECT valeur FROM parametres_bibliotheque WHERE cle = 'penalite_retard_jour'"
    )->fetch()['valeur'] ?? 100;
} catch (Exception $e) {
    $penalite_par_jour = 100;
}

$page_title = "Gestion des Retours";
include '../../../includes/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">
        <i class="fas fa-undo me-2"></i>
        Gestion des Retours
    </h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <div class="btn-group me-2">
            <a href="../" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-1"></i>
                Retour à la bibliothèque
            </a>
        </div>
        <div class="btn-group me-2">
            <a href="add.php" class="btn btn-primary">
                <i class="fas fa-plus me-1"></i>
                Nouvel emprunt
            </a>
        </div>
    </div>
</div>

<!-- Statistiques rapides -->
<div class="row mb-4">
    <div class="col-md-3">
        <div class="card text-center shadow-sm">
            <div class="card-body">
                <i class="fas fa-clock fa-2x text-info mb-2"></i>
                <h5 class="card-title"><?php echo count($emprunts_en_cours); ?></h5>
                <p class="card-text text-muted">Emprunts en cours</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-center shadow-sm">
            <div class="card-body">
                <i class="fas fa-exclamation-triangle fa-2x text-warning mb-2"></i>
                <h5 class="card-title">
                    <?php echo count(array_filter($emprunts_en_cours, function($e) { return $e['jours_retard'] > 0; })); ?>
                </h5>
                <p class="card-text text-muted">En retard</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-center shadow-sm">
            <div class="card-body">
                <i class="fas fa-calendar-day fa-2x text-success mb-2"></i>
                <h5 class="card-title">
                    <?php echo count(array_filter($emprunts_en_cours, function($e) { 
                        return $e['jours_retard'] <= 0 && strtotime($e['date_retour_prevue']) <= strtotime('+3 days'); 
                    })); ?>
                </h5>
                <p class="card-text text-muted">À rendre bientôt</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-center shadow-sm">
            <div class="card-body">
                <i class="fas fa-coins fa-2x text-danger mb-2"></i>
                <h5 class="card-title"><?php echo $penalite_par_jour; ?> FC</h5>
                <p class="card-text text-muted">Pénalité/jour</p>
            </div>
        </div>
    </div>
</div>

<!-- Liste des emprunts en cours -->
<div class="card shadow-sm">
    <div class="card-header bg-light">
        <h5 class="mb-0">
            <i class="fas fa-list me-2"></i>
            Emprunts en cours
        </h5>
    </div>
    <div class="card-body">
        <?php if (empty($emprunts_en_cours)): ?>
            <div class="text-center py-5">
                <i class="fas fa-check-circle fa-3x text-success mb-3"></i>
                <h5 class="text-muted">Aucun emprunt en cours</h5>
                <p class="text-muted">Tous les livres ont été retournés.</p>
                <a href="add.php" class="btn btn-primary">
                    <i class="fas fa-plus me-1"></i>
                    Créer un nouvel emprunt
                </a>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead class="table-light">
                        <tr>
                            <th>Livre</th>
                            <th>Emprunteur</th>
                            <th>Date emprunt</th>
                            <th>Retour prévu</th>
                            <th>Retard</th>
                            <th>Pénalité</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($emprunts_en_cours as $emprunt): ?>
                            <tr class="<?php echo ($emprunt['jours_retard'] > 0) ? 'table-warning' : ''; ?>">
                                <td>
                                    <strong><?php echo htmlspecialchars($emprunt['titre']); ?></strong>
                                    <br><small class="text-muted">
                                        <?php echo htmlspecialchars($emprunt['auteur']); ?>
                                    </small>
                                </td>
                                <td>
                                    <?php echo htmlspecialchars($emprunt['emprunteur_nom']); ?>
                                    <br><small class="text-muted">
                                        <?php echo htmlspecialchars($emprunt['info_supplementaire']); ?>
                                    </small>
                                </td>
                                <td><?php echo formatDate($emprunt['date_emprunt']); ?></td>
                                <td>
                                    <?php echo formatDate($emprunt['date_retour_prevue']); ?>
                                    <?php if ($emprunt['jours_retard'] <= 0): ?>
                                        <?php
                                        $jours_restants = -$emprunt['jours_retard'];
                                        if ($jours_restants <= 3): ?>
                                            <br><small class="text-warning">
                                                <i class="fas fa-clock me-1"></i>
                                                <?php echo $jours_restants; ?> jour(s) restant(s)
                                            </small>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($emprunt['jours_retard'] > 0): ?>
                                        <span class="badge bg-danger">
                                            <?php echo $emprunt['jours_retard']; ?> jour(s)
                                        </span>
                                    <?php else: ?>
                                        <span class="badge bg-success">À jour</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php 
                                    $penalite_calculee = max(0, $emprunt['jours_retard'] * $penalite_par_jour);
                                    if ($penalite_calculee > 0): ?>
                                        <span class="text-danger">
                                            <?php echo number_format($penalite_calculee); ?> FC
                                        </span>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <button type="button" class="btn btn-success btn-sm" 
                                            onclick="returnBook(<?php echo $emprunt['id']; ?>, '<?php echo htmlspecialchars($emprunt['titre']); ?>', <?php echo $penalite_calculee; ?>)"
                                            title="Retourner le livre">
                                        <i class="fas fa-undo me-1"></i>
                                        Retourner
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Modal de retour -->
<div class="modal fade" id="returnModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-undo me-2"></i>
                    Retourner un livre
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" id="returnForm">
                <input type="hidden" name="action" value="return_book">
                <input type="hidden" name="emprunt_id" id="return_emprunt_id">
                
                <div class="modal-body">
                    <div class="alert alert-info">
                        <strong>Livre :</strong> <span id="return_livre_titre"></span>
                    </div>
                    
                    <div class="mb-3">
                        <label for="return_penalite" class="form-label">Pénalité (FC)</label>
                        <input type="number" class="form-control" id="return_penalite" name="penalite" 
                               min="0" step="100" readonly>
                        <div class="form-text">Pénalité calculée automatiquement selon le retard.</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="return_notes" class="form-label">Notes de retour</label>
                        <textarea class="form-control" id="return_notes" name="notes_retour" rows="3" 
                                  placeholder="État du livre, observations..."></textarea>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times me-1"></i>
                        Annuler
                    </button>
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-undo me-1"></i>
                        Confirmer le retour
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function returnBook(empruntId, titre, penalite) {
    document.getElementById('return_emprunt_id').value = empruntId;
    document.getElementById('return_livre_titre').textContent = titre;
    document.getElementById('return_penalite').value = penalite;
    
    const modal = new bootstrap.Modal(document.getElementById('returnModal'));
    modal.show();
}
</script>

<?php include '../../../includes/footer.php'; ?>
