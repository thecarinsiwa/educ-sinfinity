<?php
/**
 * Module de gestion financière - Intégration des paiements avec les caisses
 * Application de gestion scolaire - République Démocratique du Congo
 */

require_once '../../../config/config.php';
require_once '../../../config/database.php';
require_once '../../../includes/functions.php';
require_once '../../../includes/permissions-pages.php';
require_once '../../../includes/ui-permissions.php';

// Vérifier l'authentification et les permissions
requireLogin();

// Vérifier l'accès à cette page
requirePagePermissionFromDB('finance', 'expenses/integration_paiements', 'read', '../../dashboard.php');

$page_title = 'Intégration Paiements - Caisses';

// Obtenir l'année scolaire actuelle
$current_year = getCurrentAcademicYear();

// Obtenir la devise par défaut
$devise_par_defaut = getDefaultCurrency();

// Vérifier si la table depenses existe et a les bonnes colonnes
try {
    $table_exists = $database->query("SHOW TABLES LIKE 'depenses'")->fetch();
    if ($table_exists) {
        // Vérifier si les colonnes devise_id et montant_devise_par_defaut existent
        $columns = $database->query("SHOW COLUMNS FROM depenses LIKE 'devise_id'")->fetch();
        if (!$columns) {
            $database->execute("ALTER TABLE depenses ADD COLUMN devise_id INT NOT NULL DEFAULT 1 AFTER montant");
            $database->execute("ALTER TABLE depenses ADD COLUMN montant_devise_par_defaut DECIMAL(10,2) NOT NULL DEFAULT 0 AFTER devise_id");
            $database->execute("ALTER TABLE depenses ADD FOREIGN KEY (devise_id) REFERENCES devises(id)");
        }
    }
} catch (Exception $e) {
    // Table update failed, continue anyway
}

// Traitement des actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'sync_paiements':
                $session_id = (int)($_POST['session_id']);
                $date_debut = sanitizeInput($_POST['date_debut']);
                $date_fin = sanitizeInput($_POST['date_fin']);
                
                try {
                    $database->beginTransaction();
                    
                    // Récupérer les informations de la session
                    $session = $database->query(
                        "SELECT sc.*, c.devise_id as caisse_devise_id
                         FROM sessions_caisse sc
                         JOIN caisses c ON sc.caisse_id = c.id
                         WHERE sc.id = ? AND sc.statut = 'ouverte'",
                        [$session_id]
                    )->fetch();
                    
                    if (!$session) {
                        throw new Exception('Session de caisse non trouvée ou fermée.');
                    }
                    
                    // Récupérer les paiements non synchronisés
                    $paiements = $database->query(
                        "SELECT p.*, e.nom as eleve_nom, e.prenom as eleve_prenom
                         FROM paiements p
                         JOIN eleves e ON p.eleve_id = e.id
                         WHERE p.annee_scolaire_id = ? 
                         AND DATE(p.date_paiement) BETWEEN ? AND ?
                         AND p.id NOT IN (
                             SELECT DISTINCT CAST(SUBSTRING_INDEX(mc.reference, '-', -1) AS UNSIGNED)
                             FROM mouvements_caisse mc
                             WHERE mc.reference LIKE 'PAIEMENT-%'
                         )",
                        [$current_year['id'], $date_debut, $date_fin]
                    )->fetchAll();
                    
                    $compteur = 0;
                    foreach ($paiements as $paiement) {
                        // Déterminer la catégorie selon le type de paiement
                        $categorie = 'autre';
                        switch ($paiement['type_paiement']) {
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
                        
                        // Insérer le mouvement
                        $database->execute(
                            "INSERT INTO mouvements_caisse (session_caisse_id, type_mouvement, categorie, libelle, description, montant, devise_id, reference, date_mouvement, user_id) VALUES (?, 'entree', ?, ?, ?, ?, ?, ?, ?, ?)",
                            [
                                $session_id,
                                $categorie,
                                'Paiement - ' . ucfirst($paiement['type_paiement']),
                                'Paiement de ' . $paiement['eleve_prenom'] . ' ' . $paiement['eleve_nom'] . ' - Reçu: ' . $paiement['recu_numero'],
                                $paiement['montant'],
                                $paiement['devise_id'],
                                'PAIEMENT-' . $paiement['id'],
                                $paiement['date_paiement'],
                                $_SESSION['user_id']
                            ]
                        );
                        $compteur++;
                    }
                    
                    $database->commit();
                    showMessage('success', "$compteur paiement(s) synchronisé(s) avec succès.");
                    
                } catch (Exception $e) {
                    $database->rollback();
                    showMessage('error', 'Erreur lors de la synchronisation : ' . $e->getMessage());
                }
                break;

            case 'sync_depenses':
                $session_id = (int)($_POST['session_id']);
                $date_debut = sanitizeInput($_POST['date_debut']);
                $date_fin = sanitizeInput($_POST['date_fin']);
                
                try {
                    $database->beginTransaction();
                    
                    // Récupérer les informations de la session
                    $session = $database->query(
                        "SELECT sc.*, c.devise_id as caisse_devise_id
                         FROM sessions_caisse sc
                         JOIN caisses c ON sc.caisse_id = c.id
                         WHERE sc.id = ? AND sc.statut = 'ouverte'",
                        [$session_id]
                    )->fetch();
                    
                    if (!$session) {
                        throw new Exception('Session de caisse non trouvée ou fermée.');
                    }
                    
                    // Récupérer les dépenses non synchronisées
                    $depenses = $database->query(
                        "SELECT d.*
                         FROM depenses d
                         WHERE d.annee_scolaire_id = ? 
                         AND DATE(d.date_depense) BETWEEN ? AND ?
                         AND d.id NOT IN (
                             SELECT DISTINCT CAST(SUBSTRING_INDEX(mc.reference, '-', -1) AS UNSIGNED)
                             FROM mouvements_caisse mc
                             WHERE mc.reference LIKE 'DEPENSE-%'
                         )",
                        [$current_year['id'], $date_debut, $date_fin]
                    )->fetchAll();
                    
                    $compteur = 0;
                    foreach ($depenses as $depense) {
                        // Insérer le mouvement
                        $database->execute(
                            "INSERT INTO mouvements_caisse (session_caisse_id, type_mouvement, categorie, libelle, description, montant, devise_id, reference, date_mouvement, user_id) VALUES (?, 'sortie', 'depense_ecole', ?, ?, ?, ?, ?, ?, ?)",
                            [
                                $session_id,
                                'Dépense - ' . $depense['libelle'],
                                $depense['description'] . ' - Fournisseur: ' . $depense['fournisseur'],
                                $depense['montant'],
                                $depense['devise_id'],
                                'DEPENSE-' . $depense['id'],
                                $depense['date_depense'],
                                $_SESSION['user_id']
                            ]
                        );
                        $compteur++;
                    }
                    
                    $database->commit();
                    showMessage('success', "$compteur dépense(s) synchronisée(s) avec succès.");
                    
                } catch (Exception $e) {
                    $database->rollback();
                    showMessage('error', 'Erreur lors de la synchronisation : ' . $e->getMessage());
                }
                break;
        }
        
        redirectTo('integration_paiements.php');
    }
}

// Récupérer les sessions ouvertes
$sessions_ouvertes = $database->query(
    "SELECT sc.*, c.nom as caisse_nom, u.username as caissier,
            d.code as devise_code, d.symbole as devise_symbole
     FROM sessions_caisse sc
     JOIN caisses c ON sc.caisse_id = c.id
     JOIN users u ON sc.user_id = u.id
     JOIN devises d ON c.devise_id = d.id
     WHERE sc.statut = 'ouverte'
     ORDER BY sc.date_ouverture DESC"
)->fetchAll();

// Statistiques des paiements non synchronisés (convertis en devise par défaut)
$stats_paiements = $database->query(
    "SELECT COUNT(*) as total, SUM(p.montant / d.taux_conversion) as montant_total
     FROM paiements p
     JOIN devises d ON p.devise_id = d.id
     WHERE p.annee_scolaire_id = ? 
     AND p.id NOT IN (
         SELECT DISTINCT CAST(SUBSTRING_INDEX(mc.reference, '-', -1) AS UNSIGNED)
         FROM mouvements_caisse mc
         WHERE mc.reference LIKE 'PAIEMENT-%'
     )",
    [$current_year['id']]
)->fetch();

// Statistiques des dépenses non synchronisées (convertis en devise par défaut)
$stats_depenses = $database->query(
    "SELECT COUNT(*) as total, SUM(d.montant / dev.taux_conversion) as montant_total
     FROM depenses d
     JOIN devises dev ON d.devise_id = dev.id
     WHERE d.annee_scolaire_id = ? 
     AND d.id NOT IN (
         SELECT DISTINCT CAST(SUBSTRING_INDEX(mc.reference, '-', -1) AS UNSIGNED)
         FROM mouvements_caisse mc
         WHERE mc.reference LIKE 'DEPENSE-%'
     )",
    [$current_year['id']]
)->fetch();

include '../../../includes/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">
        <i class="fas fa-sync me-2"></i>
        Intégration Paiements - Caisses
    </h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <div class="btn-group me-2">
            <a href="caisses.php" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-1"></i>
                Retour aux caisses
            </a>
        </div>
    </div>
</div>

<!-- Statistiques -->
<div class="row mb-4">
    <div class="col-md-6">
        <div class="card border-warning">
            <div class="card-header bg-warning text-dark">
                <h6 class="mb-0">
                    <i class="fas fa-money-bill me-2"></i>
                    Paiements Non Synchronisés
                </h6>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-6">
                        <h4 class="text-warning"><?php echo $stats_paiements['total']; ?></h4>
                        <small>Paiements</small>
                    </div>
                    <div class="col-6">
                        <h4 class="text-warning">
                            <?php echo number_format($stats_paiements['montant_total'] ?? 0, 0, ',', ' '); ?> <?php echo htmlspecialchars($devise_par_defaut['symbole'] ?? 'FC'); ?>
                        </h4>
                        <small>Montant total</small>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card border-danger">
            <div class="card-header bg-danger text-white">
                <h6 class="mb-0">
                    <i class="fas fa-receipt me-2"></i>
                    Dépenses Non Synchronisées
                </h6>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-6">
                        <h4 class="text-danger"><?php echo $stats_depenses['total']; ?></h4>
                        <small>Dépenses</small>
                    </div>
                    <div class="col-6">
                        <h4 class="text-danger">
                            <?php echo number_format($stats_depenses['montant_total'] ?? 0, 0, ',', ' '); ?> <?php echo htmlspecialchars($devise_par_defaut['symbole'] ?? 'FC'); ?>
                        </h4>
                        <small>Montant total</small>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Sessions ouvertes -->
<?php if (empty($sessions_ouvertes)): ?>
<div class="alert alert-warning">
    <i class="fas fa-exclamation-triangle me-2"></i>
    Aucune session de caisse ouverte. Vous devez d'abord ouvrir une session de caisse pour pouvoir synchroniser les paiements.
    <a href="caisses.php" class="alert-link">Ouvrir une session</a>
</div>
<?php else: ?>
<div class="row">
    <!-- Synchronisation des paiements -->
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-money-bill me-2"></i>
                    Synchroniser les Paiements
                </h5>
            </div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="action" value="sync_paiements">
                    <div class="mb-3">
                        <label for="session_paiements" class="form-label">Session de caisse <span class="text-danger">*</span></label>
                        <select class="form-select" id="session_paiements" name="session_id" required>
                            <option value="">Sélectionner une session</option>
                            <?php foreach ($sessions_ouvertes as $session): ?>
                                <option value="<?php echo $session['id']; ?>">
                                    <?php echo htmlspecialchars($session['caisse_nom']); ?> - 
                                    <?php echo htmlspecialchars($session['caissier']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="date_debut_paiements" class="form-label">Date début <span class="text-danger">*</span></label>
                        <input type="date" class="form-control" id="date_debut_paiements" name="date_debut" required>
                    </div>
                    <div class="mb-3">
                        <label for="date_fin_paiements" class="form-label">Date fin <span class="text-danger">*</span></label>
                        <input type="date" class="form-control" id="date_fin_paiements" name="date_fin" required>
                    </div>
                    <div class="d-grid">
                        <button type="submit" class="btn btn-warning">
                            <i class="fas fa-sync me-1"></i>
                            Synchroniser les Paiements
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Synchronisation des dépenses -->
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-receipt me-2"></i>
                    Synchroniser les Dépenses
                </h5>
            </div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="action" value="sync_depenses">
                    <div class="mb-3">
                        <label for="session_depenses" class="form-label">Session de caisse <span class="text-danger">*</span></label>
                        <select class="form-select" id="session_depenses" name="session_id" required>
                            <option value="">Sélectionner une session</option>
                            <?php foreach ($sessions_ouvertes as $session): ?>
                                <option value="<?php echo $session['id']; ?>">
                                    <?php echo htmlspecialchars($session['caisse_nom']); ?> - 
                                    <?php echo htmlspecialchars($session['caissier']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="date_debut_depenses" class="form-label">Date début <span class="text-danger">*</span></label>
                        <input type="date" class="form-control" id="date_debut_depenses" name="date_debut" required>
                    </div>
                    <div class="mb-3">
                        <label for="date_fin_depenses" class="form-label">Date fin <span class="text-danger">*</span></label>
                        <input type="date" class="form-control" id="date_fin_depenses" name="date_fin" required>
                    </div>
                    <div class="d-grid">
                        <button type="submit" class="btn btn-danger">
                            <i class="fas fa-sync me-1"></i>
                            Synchroniser les Dépenses
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Instructions -->
<div class="card mt-4">
    <div class="card-header">
        <h6 class="mb-0">
            <i class="fas fa-info-circle me-2"></i>
            Instructions
        </h6>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-6">
                <h6>Synchronisation des Paiements :</h6>
                <ul class="small">
                    <li>Les paiements des élèves seront automatiquement ajoutés comme entrées dans la caisse</li>
                    <li>Chaque paiement sera enregistré avec la référence "PAIEMENT-{ID}"</li>
                    <li>La catégorie sera automatiquement définie selon le type de paiement</li>
                    <li>Les paiements déjà synchronisés ne seront pas dupliqués</li>
                </ul>
            </div>
            <div class="col-md-6">
                <h6>Synchronisation des Dépenses :</h6>
                <ul class="small">
                    <li>Les dépenses de l'école seront automatiquement ajoutées comme sorties dans la caisse</li>
                    <li>Chaque dépense sera enregistrée avec la référence "DEPENSE-{ID}"</li>
                    <li>La catégorie sera automatiquement définie comme "Dépense école"</li>
                    <li>Les dépenses déjà synchronisées ne seront pas dupliquées</li>
                </ul>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
// Définir les dates par défaut (aujourd'hui)
document.addEventListener('DOMContentLoaded', function() {
    const today = new Date().toISOString().split('T')[0];
    
    document.getElementById('date_debut_paiements').value = today;
    document.getElementById('date_fin_paiements').value = today;
    document.getElementById('date_debut_depenses').value = today;
    document.getElementById('date_fin_depenses').value = today;
});
</script>

<?php include '../../../includes/footer.php'; ?>

