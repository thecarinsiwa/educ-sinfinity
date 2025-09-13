<?php
/**
 * Module de gestion financière - Journal de caisse détaillé
 * Application de gestion scolaire - République Démocratique du Congo
 */

require_once '../../../config/config.php';
require_once '../../../config/database.php';
require_once '../../../includes/functions.php';
require_once '../../../includes/permissions-pages.php';

// Vérifier l'authentification et les permissions
requireLogin();

// Vérifier l'accès à cette page
requireCurrentPageAccess('read');
if (!checkPagePermission('finance') && !checkPagePermission('finance_view')) {
    showMessage('error', 'Accès refusé à cette fonctionnalité.');
    redirectTo('caisses.php');
}

$page_title = 'Journal de Caisse';

// Obtenir la devise par défaut
$devise_par_defaut = getDefaultCurrency();

// Récupérer l'ID de la session
$session_id = (int)($_GET['session_id'] ?? 0);
if (!$session_id) {
    showMessage('error', 'Session de caisse non spécifiée.');
    redirectTo('caisses.php');
}

// Récupérer les informations de la session
$session = $database->query(
    "SELECT sc.*, c.nom as caisse_nom, c.description as caisse_description,
            u.username as caissier, u.nom as user_nom, u.prenom as user_prenom,
            d.code as devise_code, d.symbole as devise_symbole, d.nom as devise_nom
     FROM sessions_caisse sc
     JOIN caisses c ON sc.caisse_id = c.id
     JOIN users u ON sc.user_id = u.id
     JOIN devises d ON c.devise_id = d.id
     WHERE sc.id = ?",
    [$session_id]
)->fetch();

if (!$session) {
    showMessage('error', 'Session de caisse non trouvée.');
    redirectTo('caisses.php');
}

// Traitement des actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add_mouvement':
                $type_mouvement = sanitizeInput($_POST['type_mouvement']);
                $categorie = sanitizeInput($_POST['categorie']);
                $libelle = sanitizeInput($_POST['libelle']);
                $description = sanitizeInput($_POST['description']);
                $montant = (float)($_POST['montant']);
                $devise_id = (int)($_POST['devise_id']);
                $reference = sanitizeInput($_POST['reference']);
                $date_mouvement = sanitizeInput($_POST['date_mouvement']);
                
                if (empty($libelle) || $montant <= 0 || !$devise_id) {
                    showMessage('error', 'Tous les champs obligatoires doivent être remplis.');
                } else {
                    try {
                        $database->execute(
                            "INSERT INTO mouvements_caisse (session_caisse_id, type_mouvement, categorie, libelle, description, montant, devise_id, reference, date_mouvement, user_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
                            [$session_id, $type_mouvement, $categorie, $libelle, $description, $montant, $devise_id, $reference, $date_mouvement, $_SESSION['user_id']]
                        );
                        showMessage('success', 'Mouvement enregistré avec succès.');
                    } catch (Exception $e) {
                        showMessage('error', 'Erreur lors de l\'enregistrement : ' . $e->getMessage());
                    }
                }
                break;

            case 'delete_mouvement':
                $mouvement_id = (int)($_POST['mouvement_id']);
                try {
                    $database->execute("DELETE FROM mouvements_caisse WHERE id = ? AND session_caisse_id = ?", [$mouvement_id, $session_id]);
                    showMessage('success', 'Mouvement supprimé avec succès.');
                } catch (Exception $e) {
                    showMessage('error', 'Erreur lors de la suppression : ' . $e->getMessage());
                }
                break;
        }
        
        redirectTo('journal_caisse.php?session_id=' . $session_id);
    }
}

// Paramètres de filtrage
$date_debut = sanitizeInput($_GET['date_debut'] ?? '');
$date_fin = sanitizeInput($_GET['date_fin'] ?? '');
$type_filter = sanitizeInput($_GET['type'] ?? '');
$categorie_filter = sanitizeInput($_GET['categorie'] ?? '');

// Construction de la requête pour les mouvements
$sql_mouvements = "SELECT m.*, d.code as devise_code, d.symbole as devise_symbole, u.username as user_name
                   FROM mouvements_caisse m
                   JOIN devises d ON m.devise_id = d.id
                   JOIN users u ON m.user_id = u.id
                   WHERE m.session_caisse_id = ?";

$params = [$session_id];

if (!empty($date_debut)) {
    $sql_mouvements .= " AND DATE(m.date_mouvement) >= ?";
    $params[] = $date_debut;
}

if (!empty($date_fin)) {
    $sql_mouvements .= " AND DATE(m.date_mouvement) <= ?";
    $params[] = $date_fin;
}

if (!empty($type_filter)) {
    $sql_mouvements .= " AND m.type_mouvement = ?";
    $params[] = $type_filter;
}

if (!empty($categorie_filter)) {
    $sql_mouvements .= " AND m.categorie = ?";
    $params[] = $categorie_filter;
}

$sql_mouvements .= " ORDER BY m.date_mouvement DESC, m.id DESC";

$mouvements = $database->query($sql_mouvements, $params)->fetchAll();

// Calculer les totaux
$total_entrees = 0;
$total_sorties = 0;
$solde_courant = $session['solde_ouverture'];

foreach ($mouvements as $mouvement) {
    if ($mouvement['type_mouvement'] === 'entree') {
        $total_entrees += $mouvement['montant'];
        $solde_courant += $mouvement['montant'];
    } else {
        $total_sorties += $mouvement['montant'];
        $solde_courant -= $mouvement['montant'];
    }
}

// Récupérer les devises actives
$devises = getActiveCurrencies();

include '../../../includes/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">
        <i class="fas fa-book me-2"></i>
        Journal de Caisse
    </h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <div class="btn-group me-2">
            <a href="caisses.php" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-1"></i>
                Retour aux caisses
            </a>
        </div>
        <?php if ($session['statut'] === 'ouverte' && checkPagePermission('finance')): ?>
        <div class="btn-group">
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addMouvementModal">
                <i class="fas fa-plus me-1"></i>
                Nouveau Mouvement
            </button>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Informations de la session -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-info-circle me-2"></i>
                    Informations de la Session
                </h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-3">
                        <strong>Caisse :</strong><br>
                        <?php echo htmlspecialchars($session['caisse_nom']); ?>
                    </div>
                    <div class="col-md-3">
                        <strong>Caissier :</strong><br>
                        <?php echo htmlspecialchars($session['user_prenom'] . ' ' . $session['user_nom']); ?>
                        <small class="text-muted">(<?php echo htmlspecialchars($session['caissier']); ?>)</small>
                    </div>
                    <div class="col-md-3">
                        <strong>Ouverte le :</strong><br>
                        <?php echo date('d/m/Y H:i', strtotime($session['date_ouverture'])); ?>
                    </div>
                    <div class="col-md-3">
                        <strong>Statut :</strong><br>
                        <?php if ($session['statut'] === 'ouverte'): ?>
                            <span class="badge bg-success">Ouverte</span>
                        <?php else: ?>
                            <span class="badge bg-danger">Fermée</span>
                            <br><small class="text-muted">Fermée le : <?php echo date('d/m/Y H:i', strtotime($session['date_fermeture'])); ?></small>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Résumé financier -->
<div class="row mb-4">
    <div class="col-md-3">
        <div class="card text-center">
            <div class="card-body">
                <h5 class="card-title text-primary">
                    <?php echo number_format($session['solde_ouverture'], 0, ',', ' '); ?> 
                    <?php echo htmlspecialchars($session['devise_symbole']); ?>
                </h5>
                <p class="card-text">Solde d'ouverture</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-center">
            <div class="card-body">
                <h5 class="card-title text-success">
                    <?php echo number_format($total_entrees, 0, ',', ' '); ?> 
                    <?php echo htmlspecialchars($session['devise_symbole']); ?>
                </h5>
                <p class="card-text">Total Entrées</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-center">
            <div class="card-body">
                <h5 class="card-title text-danger">
                    <?php echo number_format($total_sorties, 0, ',', ' '); ?> 
                    <?php echo htmlspecialchars($session['devise_symbole']); ?>
                </h5>
                <p class="card-text">Total Sorties</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-center">
            <div class="card-body">
                <h5 class="card-title <?php echo $solde_courant >= 0 ? 'text-success' : 'text-danger'; ?>">
                    <?php echo number_format($solde_courant, 0, ',', ' '); ?> 
                    <?php echo htmlspecialchars($session['devise_symbole']); ?>
                </h5>
                <p class="card-text">Solde Courant</p>
            </div>
        </div>
    </div>
</div>

<!-- Filtres -->
<div class="card mb-4">
    <div class="card-header">
        <h6 class="mb-0">
            <i class="fas fa-filter me-2"></i>
            Filtres
        </h6>
    </div>
    <div class="card-body">
        <form method="GET" class="row g-3">
            <input type="hidden" name="session_id" value="<?php echo $session_id; ?>">
            <div class="col-md-3">
                <label for="date_debut" class="form-label">Date début</label>
                <input type="date" class="form-control" id="date_debut" name="date_debut" value="<?php echo $date_debut; ?>">
            </div>
            <div class="col-md-3">
                <label for="date_fin" class="form-label">Date fin</label>
                <input type="date" class="form-control" id="date_fin" name="date_fin" value="<?php echo $date_fin; ?>">
            </div>
            <div class="col-md-2">
                <label for="type" class="form-label">Type</label>
                <select class="form-select" id="type" name="type">
                    <option value="">Tous</option>
                    <option value="entree" <?php echo $type_filter === 'entree' ? 'selected' : ''; ?>>Entrées</option>
                    <option value="sortie" <?php echo $type_filter === 'sortie' ? 'selected' : ''; ?>>Sorties</option>
                </select>
            </div>
            <div class="col-md-2">
                <label for="categorie" class="form-label">Catégorie</label>
                <select class="form-select" id="categorie" name="categorie">
                    <option value="">Toutes</option>
                    <option value="paiement_eleve" <?php echo $categorie_filter === 'paiement_eleve' ? 'selected' : ''; ?>>Paiement élève</option>
                    <option value="don" <?php echo $categorie_filter === 'don' ? 'selected' : ''; ?>>Don</option>
                    <option value="subvention" <?php echo $categorie_filter === 'subvention' ? 'selected' : ''; ?>>Subvention</option>
                    <option value="depense_ecole" <?php echo $categorie_filter === 'depense_ecole' ? 'selected' : ''; ?>>Dépense école</option>
                    <option value="retrait" <?php echo $categorie_filter === 'retrait' ? 'selected' : ''; ?>>Retrait</option>
                    <option value="versement" <?php echo $categorie_filter === 'versement' ? 'selected' : ''; ?>>Versement</option>
                    <option value="autre" <?php echo $categorie_filter === 'autre' ? 'selected' : ''; ?>>Autre</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">&nbsp;</label>
                <div class="d-grid">
                    <button type="submit" class="btn btn-outline-primary">
                        <i class="fas fa-search me-1"></i>Filtrer
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Journal des mouvements -->
<div class="card">
    <div class="card-header">
        <h5 class="mb-0">
            <i class="fas fa-list me-2"></i>
            Journal des Mouvements
        </h5>
    </div>
    <div class="card-body">
        <?php if (empty($mouvements)): ?>
            <div class="text-center py-4">
                <i class="fas fa-receipt fa-3x text-muted mb-3"></i>
                <p class="text-muted">Aucun mouvement enregistré pour cette session.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead>
                        <tr>
                            <th>Date/Heure</th>
                            <th>Type</th>
                            <th>Catégorie</th>
                            <th>Libellé</th>
                            <th>Description</th>
                            <th>Montant</th>
                            <th>Référence</th>
                            <th>Enregistré par</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($mouvements as $mouvement): ?>
                        <tr>
                            <td>
                                <small>
                                    <?php echo date('d/m/Y', strtotime($mouvement['date_mouvement'])); ?><br>
                                    <?php echo date('H:i', strtotime($mouvement['date_mouvement'])); ?>
                                </small>
                            </td>
                            <td>
                                <?php if ($mouvement['type_mouvement'] === 'entree'): ?>
                                    <span class="badge bg-success">Entrée</span>
                                <?php else: ?>
                                    <span class="badge bg-danger">Sortie</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php
                                $categories = [
                                    'paiement_eleve' => 'Paiement élève',
                                    'don' => 'Don',
                                    'subvention' => 'Subvention',
                                    'depense_ecole' => 'Dépense école',
                                    'retrait' => 'Retrait',
                                    'versement' => 'Versement',
                                    'autre' => 'Autre'
                                ];
                                echo $categories[$mouvement['categorie']] ?? $mouvement['categorie'];
                                ?>
                            </td>
                            <td>
                                <strong><?php echo htmlspecialchars($mouvement['libelle']); ?></strong>
                            </td>
                            <td>
                                <small><?php echo htmlspecialchars($mouvement['description']); ?></small>
                            </td>
                            <td>
                                <span class="badge <?php echo $mouvement['type_mouvement'] === 'entree' ? 'bg-success' : 'bg-danger'; ?>">
                                    <?php echo number_format($mouvement['montant'], 0, ',', ' '); ?> 
                                    <?php echo htmlspecialchars($mouvement['devise_symbole']); ?>
                                </span>
                            </td>
                            <td>
                                <small><?php echo htmlspecialchars($mouvement['reference']); ?></small>
                            </td>
                            <td>
                                <small><?php echo htmlspecialchars($mouvement['user_name']); ?></small>
                            </td>
                            <td>
                                <?php if ($session['statut'] === 'ouverte' && checkPagePermission('finance')): ?>
                                <button type="button" class="btn btn-sm btn-outline-danger" 
                                        onclick="deleteMouvement(<?php echo $mouvement['id']; ?>, '<?php echo htmlspecialchars($mouvement['libelle']); ?>')">
                                    <i class="fas fa-trash"></i>
                                </button>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Modal Ajout Mouvement -->
<?php if ($session['statut'] === 'ouverte' && checkPagePermission('finance')): ?>
<div class="modal fade" id="addMouvementModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="add_mouvement">
                <div class="modal-header">
                    <h5 class="modal-title">Nouveau Mouvement</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="type_mouvement" class="form-label">Type <span class="text-danger">*</span></label>
                            <select class="form-select" id="type_mouvement" name="type_mouvement" required>
                                <option value="">Sélectionner</option>
                                <option value="entree">Entrée</option>
                                <option value="sortie">Sortie</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="categorie" class="form-label">Catégorie <span class="text-danger">*</span></label>
                            <select class="form-select" id="categorie" name="categorie" required>
                                <option value="">Sélectionner</option>
                                <option value="paiement_eleve">Paiement élève</option>
                                <option value="don">Don</option>
                                <option value="subvention">Subvention</option>
                                <option value="depense_ecole">Dépense école</option>
                                <option value="retrait">Retrait</option>
                                <option value="versement">Versement</option>
                                <option value="autre">Autre</option>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="libelle" class="form-label">Libellé <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="libelle" name="libelle" required>
                    </div>
                    <div class="mb-3">
                        <label for="description" class="form-label">Description</label>
                        <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="montant" class="form-label">Montant <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" id="montant" name="montant" 
                                   step="0.01" min="0.01" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="devise_id" class="form-label">Devise <span class="text-danger">*</span></label>
                            <select class="form-select" id="devise_id" name="devise_id" required>
                                <option value="">Sélectionner</option>
                                <?php foreach ($devises as $devise): ?>
                                    <option value="<?php echo $devise['id']; ?>">
                                        <?php echo htmlspecialchars($devise['code']); ?> - <?php echo htmlspecialchars($devise['nom']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="reference" class="form-label">Référence</label>
                            <input type="text" class="form-control" id="reference" name="reference">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="date_mouvement" class="form-label">Date du mouvement</label>
                            <input type="datetime-local" class="form-control" id="date_mouvement" name="date_mouvement" 
                                   value="<?php echo date('Y-m-d\TH:i'); ?>">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-primary">Enregistrer</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Formulaire de suppression -->
<form id="deleteForm" method="POST" style="display: none;">
    <input type="hidden" name="action" value="delete_mouvement">
    <input type="hidden" name="mouvement_id" id="delete_mouvement_id">
</form>

<script>
function deleteMouvement(mouvementId, libelle) {
    if (confirm(`Êtes-vous sûr de vouloir supprimer le mouvement "${libelle}" ?`)) {
        document.getElementById('delete_mouvement_id').value = mouvementId;
        document.getElementById('deleteForm').submit();
    }
}
</script>

<?php include '../../../includes/footer.php'; ?>

