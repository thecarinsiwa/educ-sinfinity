<?php
/**
 * Module de gestion financière - Historique des caisses
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

$page_title = 'Historique des Caisses';

// Obtenir l'année scolaire actuelle
$current_year = getCurrentAcademicYear();

// Obtenir la devise par défaut
$devise_par_defaut = getDefaultCurrency();

// Paramètres de filtrage
$caisse_id = (int)($_GET['caisse_id'] ?? 0);
$date_debut = sanitizeInput($_GET['date_debut'] ?? '');
$date_fin = sanitizeInput($_GET['date_fin'] ?? '');
$statut_filter = sanitizeInput($_GET['statut'] ?? '');

// Récupérer les caisses pour le filtre
$caisses = $database->query(
    "SELECT c.*, d.code as devise_code, d.symbole as devise_symbole
     FROM caisses c
     JOIN devises d ON c.devise_id = d.id
     WHERE c.annee_scolaire_id = ? AND c.statut = 'active'
     ORDER BY c.nom",
    [$current_year['id']]
)->fetchAll();

// Construction de la requête pour les sessions
$sql_sessions = "SELECT sc.*, c.nom as caisse_nom, u.username as caissier, u.nom as user_nom, u.prenom as user_prenom,
                        d.code as devise_code, d.symbole as devise_symbole,
                        (SELECT COUNT(*) FROM mouvements_caisse mc WHERE mc.session_caisse_id = sc.id) as nb_mouvements,
                        (SELECT SUM(CASE WHEN mc.type_mouvement = 'entree' THEN mc.montant ELSE 0 END) FROM mouvements_caisse mc WHERE mc.session_caisse_id = sc.id) as total_entrees,
                        (SELECT SUM(CASE WHEN mc.type_mouvement = 'sortie' THEN mc.montant ELSE 0 END) FROM mouvements_caisse mc WHERE mc.session_caisse_id = sc.id) as total_sorties
                 FROM sessions_caisse sc
                 JOIN caisses c ON sc.caisse_id = c.id
                 JOIN users u ON sc.user_id = u.id
                 JOIN devises d ON c.devise_id = d.id
                 WHERE c.annee_scolaire_id = ?";

$params = [$current_year['id']];

if ($caisse_id) {
    $sql_sessions .= " AND sc.caisse_id = ?";
    $params[] = $caisse_id;
}

if (!empty($date_debut)) {
    $sql_sessions .= " AND DATE(sc.date_ouverture) >= ?";
    $params[] = $date_debut;
}

if (!empty($date_fin)) {
    $sql_sessions .= " AND DATE(sc.date_ouverture) <= ?";
    $params[] = $date_fin;
}

if (!empty($statut_filter)) {
    $sql_sessions .= " AND sc.statut = ?";
    $params[] = $statut_filter;
}

$sql_sessions .= " ORDER BY sc.date_ouverture DESC";

$sessions = $database->query($sql_sessions, $params)->fetchAll();

include '../../../includes/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">
        <i class="fas fa-history me-2"></i>
        Historique des Caisses
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
            <div class="col-md-3">
                <label for="caisse_id" class="form-label">Caisse</label>
                <select class="form-select" id="caisse_id" name="caisse_id">
                    <option value="">Toutes les caisses</option>
                    <?php foreach ($caisses as $caisse): ?>
                        <option value="<?php echo $caisse['id']; ?>" <?php echo $caisse_id == $caisse['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($caisse['nom']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label for="date_debut" class="form-label">Date début</label>
                <input type="date" class="form-control" id="date_debut" name="date_debut" value="<?php echo $date_debut; ?>">
            </div>
            <div class="col-md-2">
                <label for="date_fin" class="form-label">Date fin</label>
                <input type="date" class="form-control" id="date_fin" name="date_fin" value="<?php echo $date_fin; ?>">
            </div>
            <div class="col-md-2">
                <label for="statut" class="form-label">Statut</label>
                <select class="form-select" id="statut" name="statut">
                    <option value="">Tous</option>
                    <option value="ouverte" <?php echo $statut_filter === 'ouverte' ? 'selected' : ''; ?>>Ouverte</option>
                    <option value="fermee" <?php echo $statut_filter === 'fermee' ? 'selected' : ''; ?>>Fermée</option>
                </select>
            </div>
            <div class="col-md-3">
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

<!-- Statistiques -->
<?php if (!empty($sessions)): ?>
<div class="row mb-4">
    <div class="col-md-3">
        <div class="card text-center">
            <div class="card-body">
                <h5 class="card-title text-primary"><?php echo count($sessions); ?></h5>
                <p class="card-text">Sessions totales</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-center">
            <div class="card-body">
                <h5 class="card-title text-success">
                    <?php echo count(array_filter($sessions, fn($s) => $s['statut'] === 'fermee')); ?>
                </h5>
                <p class="card-text">Sessions fermées</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-center">
            <div class="card-body">
                <h5 class="card-title text-warning">
                    <?php echo count(array_filter($sessions, fn($s) => $s['statut'] === 'ouverte')); ?>
                </h5>
                <p class="card-text">Sessions ouvertes</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-center">
            <div class="card-body">
                <h5 class="card-title text-info">
                    <?php echo array_sum(array_column($sessions, 'nb_mouvements')); ?>
                </h5>
                <p class="card-text">Mouvements total</p>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Liste des sessions -->
<div class="card">
    <div class="card-header">
        <h5 class="mb-0">
            <i class="fas fa-list me-2"></i>
            Sessions de Caisse
        </h5>
    </div>
    <div class="card-body">
        <?php if (empty($sessions)): ?>
            <div class="text-center py-4">
                <i class="fas fa-history fa-3x text-muted mb-3"></i>
                <p class="text-muted">Aucune session trouvée avec ces critères.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead>
                        <tr>
                            <th>Caisse</th>
                            <th>Caissier</th>
                            <th>Ouverture</th>
                            <th>Fermeture</th>
                            <th>Durée</th>
                            <th>Solde Ouverture</th>
                            <th>Solde Fermeture</th>
                            <th>Entrées</th>
                            <th>Sorties</th>
                            <th>Mouvements</th>
                            <th>Statut</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($sessions as $session): ?>
                        <tr>
                            <td>
                                <strong><?php echo htmlspecialchars($session['caisse_nom']); ?></strong>
                            </td>
                            <td>
                                <?php echo htmlspecialchars($session['user_prenom'] . ' ' . $session['user_nom']); ?>
                                <br><small class="text-muted">(<?php echo htmlspecialchars($session['caissier']); ?>)</small>
                            </td>
                            <td>
                                <small>
                                    <?php echo date('d/m/Y', strtotime($session['date_ouverture'])); ?><br>
                                    <?php echo date('H:i', strtotime($session['date_ouverture'])); ?>
                                </small>
                            </td>
                            <td>
                                <?php if ($session['date_fermeture']): ?>
                                    <small>
                                        <?php echo date('d/m/Y', strtotime($session['date_fermeture'])); ?><br>
                                        <?php echo date('H:i', strtotime($session['date_fermeture'])); ?>
                                    </small>
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($session['date_fermeture']): ?>
                                    <?php
                                    $debut = new DateTime($session['date_ouverture']);
                                    $fin = new DateTime($session['date_fermeture']);
                                    $duree = $debut->diff($fin);
                                    echo $duree->format('%h h %i min');
                                    ?>
                                <?php else: ?>
                                    <span class="text-muted">En cours</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge bg-info">
                                    <?php echo number_format($session['solde_ouverture'], 0, ',', ' '); ?> 
                                    <?php echo htmlspecialchars($session['devise_symbole']); ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($session['solde_fermeture'] !== null): ?>
                                    <span class="badge bg-secondary">
                                        <?php echo number_format($session['solde_fermeture'], 0, ',', ' '); ?> 
                                        <?php echo htmlspecialchars($session['devise_symbole']); ?>
                                    </span>
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge bg-success">
                                    <?php echo number_format($session['total_entrees'] ?? 0, 0, ',', ' '); ?> 
                                    <?php echo htmlspecialchars($session['devise_symbole']); ?>
                                </span>
                            </td>
                            <td>
                                <span class="badge bg-danger">
                                    <?php echo number_format($session['total_sorties'] ?? 0, 0, ',', ' '); ?> 
                                    <?php echo htmlspecialchars($session['devise_symbole']); ?>
                                </span>
                            </td>
                            <td>
                                <span class="badge bg-primary">
                                    <?php echo $session['nb_mouvements']; ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($session['statut'] === 'ouverte'): ?>
                                    <span class="badge bg-warning">Ouverte</span>
                                <?php else: ?>
                                    <span class="badge bg-success">Fermée</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <a href="journal_caisse.php?session_id=<?php echo $session['id']; ?>" 
                                   class="btn btn-sm btn-outline-primary">
                                    <i class="fas fa-book me-1"></i>Journal
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include '../../../includes/footer.php'; ?>

