<?php
/**
 * Traitement en masse des transferts
 * Application de gestion scolaire - République Démocratique du Congo
 */

require_once '../../../config/config.php';
require_once '../../../config/database.php';
require_once '../../../includes/functions.php';

// Vérifier l'authentification et les permissions
requireLogin();
if (!checkPermission('students')) {
    redirectTo('../../../login.php');
}

$page_title = "Traitement en masse des transferts";

// Traitement des actions en masse
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_action'])) {
    try {
        $selected_transfers = $_POST['selected_transfers'] ?? [];
        $action = $_POST['bulk_action'];
        
        if (empty($selected_transfers)) {
            throw new Exception("Aucun transfert sélectionné");
        }
        
        $database->beginTransaction();
        $processed_count = 0;
        
        foreach ($selected_transfers as $transfer_id) {
            // Vérifier que le transfert existe
            $transfer = $database->query("SELECT * FROM transfers WHERE id = ?", [$transfer_id])->fetch();
            if (!$transfer) continue;
            
            switch ($action) {
                case 'approve':
                    if ($transfer['statut'] === 'en_attente') {
                        $database->query(
                            "UPDATE transfers SET statut = 'approuve', approuve_par = ?, date_approbation = NOW() WHERE id = ?",
                            [$_SESSION['user_id'], $transfer_id]
                        );
                        
                        // Historique
                        $database->query(
                            "INSERT INTO transfer_history (transfer_id, action, ancien_statut, nouveau_statut, commentaire, user_id) VALUES (?, 'approbation', 'en_attente', 'approuve', 'Approuvé en masse', ?)",
                            [$transfer_id, $_SESSION['user_id']]
                        );
                        $processed_count++;
                    }
                    break;
                    
                case 'reject':
                    if ($transfer['statut'] === 'en_attente') {
                        $database->query(
                            "UPDATE transfers SET statut = 'rejete', approuve_par = ?, date_approbation = NOW() WHERE id = ?",
                            [$_SESSION['user_id'], $transfer_id]
                        );
                        
                        // Historique
                        $database->query(
                            "INSERT INTO transfer_history (transfer_id, action, ancien_statut, nouveau_statut, commentaire, user_id) VALUES (?, 'rejet', 'en_attente', 'rejete', 'Rejeté en masse', ?)",
                            [$transfer_id, $_SESSION['user_id']]
                        );
                        $processed_count++;
                    }
                    break;
                    
                case 'complete':
                    if ($transfer['statut'] === 'approuve') {
                        $database->query(
                            "UPDATE transfers SET statut = 'complete', date_effective = COALESCE(date_effective, CURDATE()) WHERE id = ?",
                            [$transfer_id]
                        );
                        
                        // Mettre à jour le statut de l'élève si c'est une sortie
                        if (in_array($transfer['type_mouvement'], ['transfert_sortant', 'sortie_definitive'])) {
                            $database->query(
                                "UPDATE inscriptions SET statut = 'inactive', date_fin = CURDATE() WHERE eleve_id = ? AND statut = 'active'",
                                [$transfer['eleve_id']]
                            );
                        }
                        
                        // Historique
                        $database->query(
                            "INSERT INTO transfer_history (transfer_id, action, ancien_statut, nouveau_statut, commentaire, user_id) VALUES (?, 'completion', 'approuve', 'complete', 'Complété en masse', ?)",
                            [$transfer_id, $_SESSION['user_id']]
                        );
                        $processed_count++;
                    }
                    break;
                    
                case 'generate_certificates':
                    if ($transfer['statut'] === 'complete' && !$transfer['certificat_genere']) {
                        // Générer un numéro de certificat
                        $numero_certificat = 'CERT' . date('Y') . str_pad($transfer_id, 6, '0', STR_PAD_LEFT);
                        
                        $database->query(
                            "UPDATE transfers SET certificat_genere = 1, numero_certificat = ? WHERE id = ?",
                            [$numero_certificat, $transfer_id]
                        );
                        
                        // Historique
                        $database->query(
                            "INSERT INTO transfer_history (transfer_id, action, ancien_statut, nouveau_statut, commentaire, user_id) VALUES (?, 'modification', ?, ?, 'Certificat généré en masse', ?)",
                            [$transfer_id, $transfer['statut'], $transfer['statut'], $_SESSION['user_id']]
                        );
                        $processed_count++;
                    }
                    break;
            }
        }
        
        $database->commit();
        
        $action_labels = [
            'approve' => 'approuvés',
            'reject' => 'rejetés',
            'complete' => 'complétés',
            'generate_certificates' => 'certificats générés'
        ];
        
        showMessage('success', "$processed_count transfert(s) " . $action_labels[$action] . " avec succès !");
        
        // Logger l'action
        logUserAction('bulk_process_transfers', 'transfers', "Action en masse: $action sur $processed_count transferts", null);
        
    } catch (Exception $e) {
        $database->rollBack();
        showMessage('error', $e->getMessage());
    }
}

// Récupérer les filtres
$status_filter = $_GET['status'] ?? '';
$type_filter = $_GET['type'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';

// Construire la requête avec filtres
$where_conditions = ['1=1'];
$params = [];

if ($status_filter) {
    $where_conditions[] = "t.statut = ?";
    $params[] = $status_filter;
}

if ($type_filter) {
    $where_conditions[] = "t.type_mouvement = ?";
    $params[] = $type_filter;
}

if ($date_from) {
    $where_conditions[] = "t.date_demande >= ?";
    $params[] = $date_from;
}

if ($date_to) {
    $where_conditions[] = "t.date_demande <= ?";
    $params[] = $date_to;
}

$where_clause = implode(' AND ', $where_conditions);

// Récupérer les transferts
$transfers = $database->query(
    "SELECT t.*, e.numero_matricule, e.nom, e.prenom, 
            c_orig.nom as classe_origine_nom, c_orig.niveau as classe_origine_niveau,
            c_dest.nom as classe_destination_nom, c_dest.niveau as classe_destination_niveau,
            u_traite.nom as traite_par_nom, u_traite.prenom as traite_par_prenom,
            u_approuve.nom as approuve_par_nom, u_approuve.prenom as approuve_par_prenom
     FROM transfers t
     JOIN eleves e ON t.eleve_id = e.id
     LEFT JOIN classes c_orig ON t.classe_origine_id = c_orig.id
     LEFT JOIN classes c_dest ON t.classe_destination_id = c_dest.id
     LEFT JOIN users u_traite ON t.traite_par = u_traite.id
     LEFT JOIN users u_approuve ON t.approuve_par = u_approuve.id
     WHERE $where_clause
     ORDER BY t.date_demande DESC, t.id DESC",
    $params
)->fetchAll();

// Statistiques
$stats = $database->query(
    "SELECT 
        COUNT(*) as total,
        COUNT(CASE WHEN statut = 'en_attente' THEN 1 END) as en_attente,
        COUNT(CASE WHEN statut = 'approuve' THEN 1 END) as approuve,
        COUNT(CASE WHEN statut = 'rejete' THEN 1 END) as rejete,
        COUNT(CASE WHEN statut = 'complete' THEN 1 END) as complete
     FROM transfers"
)->fetch();

include '../../../includes/header.php';
?>

<!-- Styles CSS modernes -->
<style>
.bulk-header {
    background: linear-gradient(135deg, #6f42c1 0%, #e83e8c 100%);
    color: white;
    padding: 2rem 0;
    margin: -20px -15px 30px -15px;
    border-radius: 0 0 20px 20px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.1);
}

.bulk-header h1 {
    font-weight: 300;
    font-size: 2.5rem;
    margin-bottom: 0.5rem;
}

.bulk-header .subtitle {
    opacity: 0.9;
    font-size: 1.1rem;
}

.stats-card {
    background: white;
    border-radius: 15px;
    padding: 1.5rem;
    box-shadow: 0 5px 15px rgba(0,0,0,0.08);
    border: none;
    transition: all 0.3s ease;
    margin-bottom: 2rem;
}

.stats-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 10px 30px rgba(0,0,0,0.15);
}

.stat-item {
    text-align: center;
    padding: 1rem;
}

.stat-number {
    font-size: 2rem;
    font-weight: 700;
    margin-bottom: 0.5rem;
    display: block;
}

.stat-label {
    color: #6c757d;
    font-size: 0.9rem;
    font-weight: 500;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.filters-card {
    background: white;
    border-radius: 15px;
    padding: 1.5rem;
    box-shadow: 0 2px 10px rgba(0,0,0,0.05);
    margin-bottom: 2rem;
}

.bulk-actions {
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
    border-radius: 10px;
    padding: 1rem;
    margin-bottom: 1rem;
    border-left: 4px solid #6f42c1;
}

.table-modern {
    background: white;
    border-radius: 15px;
    overflow: hidden;
    box-shadow: 0 5px 15px rgba(0,0,0,0.08);
}

.table-modern thead th {
    background: linear-gradient(135deg, #495057 0%, #6c757d 100%);
    color: white;
    border: none;
    padding: 1rem;
    font-weight: 600;
    text-transform: uppercase;
    font-size: 0.85rem;
    letter-spacing: 0.5px;
}

.table-modern tbody tr {
    transition: all 0.3s ease;
}

.table-modern tbody tr:hover {
    background-color: #f8f9fa;
    transform: scale(1.01);
}

.btn-modern {
    border-radius: 25px;
    padding: 0.5rem 1.5rem;
    font-weight: 500;
    transition: all 0.3s ease;
    border: none;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
}

.btn-modern:hover {
    transform: translateY(-2px);
    box-shadow: 0 5px 20px rgba(0,0,0,0.2);
}

.status-badge {
    padding: 0.5rem 1rem;
    border-radius: 20px;
    font-weight: 500;
    font-size: 0.8rem;
}

.status-en_attente { background: #fff3cd; color: #856404; }
.status-approuve { background: #d1ecf1; color: #0c5460; }
.status-rejete { background: #f8d7da; color: #721c24; }
.status-complete { background: #d4edda; color: #155724; }

@keyframes fadeInUp {
    from {
        opacity: 0;
        transform: translateY(30px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.animate-fade-in {
    animation: fadeInUp 0.6s ease-out;
}

.animate-delay-1 { animation-delay: 0.1s; }
.animate-delay-2 { animation-delay: 0.2s; }
.animate-delay-3 { animation-delay: 0.3s; }

@media (max-width: 768px) {
    .bulk-header {
        margin: -20px -15px 20px -15px;
        padding: 1.5rem 0;
    }
    
    .bulk-header h1 {
        font-size: 2rem;
    }
    
    .stats-card {
        padding: 1rem;
    }
    
    .table-responsive {
        border-radius: 10px;
    }
}
</style>

<!-- En-tête moderne -->
<div class="bulk-header">
    <div class="container-fluid">
        <div class="row align-items-center">
            <div class="col-md-8">
                <h1 class="animate-fade-in">
                    <i class="fas fa-tasks me-3"></i>
                    Traitement en masse des transferts
                </h1>
                <p class="subtitle animate-fade-in animate-delay-1">
                    Gérer plusieurs transferts simultanément
                </p>
            </div>
            <div class="col-md-4 text-end">
                <div class="animate-fade-in animate-delay-2">
                    <a href="../index.php" class="btn btn-light btn-modern">
                        <i class="fas fa-arrow-left me-2"></i>
                        Retour
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Statistiques -->
<div class="stats-card animate-fade-in animate-delay-1">
    <div class="row">
        <div class="col-lg-2 col-md-4 col-sm-6">
            <div class="stat-item">
                <span class="stat-number text-primary"><?php echo $stats['total']; ?></span>
                <span class="stat-label">Total</span>
            </div>
        </div>
        <div class="col-lg-2 col-md-4 col-sm-6">
            <div class="stat-item">
                <span class="stat-number text-warning"><?php echo $stats['en_attente']; ?></span>
                <span class="stat-label">En attente</span>
            </div>
        </div>
        <div class="col-lg-2 col-md-4 col-sm-6">
            <div class="stat-item">
                <span class="stat-number text-info"><?php echo $stats['approuve']; ?></span>
                <span class="stat-label">Approuvés</span>
            </div>
        </div>
        <div class="col-lg-2 col-md-4 col-sm-6">
            <div class="stat-item">
                <span class="stat-number text-danger"><?php echo $stats['rejete']; ?></span>
                <span class="stat-label">Rejetés</span>
            </div>
        </div>
        <div class="col-lg-2 col-md-4 col-sm-6">
            <div class="stat-item">
                <span class="stat-number text-success"><?php echo $stats['complete']; ?></span>
                <span class="stat-label">Complétés</span>
            </div>
        </div>
        <div class="col-lg-2 col-md-4 col-sm-6">
            <div class="stat-item">
                <span class="stat-number text-secondary"><?php echo count($transfers); ?></span>
                <span class="stat-label">Affichés</span>
            </div>
        </div>
    </div>
</div>

<!-- Filtres -->
<div class="filters-card animate-fade-in animate-delay-2">
    <form method="GET" class="row g-3">
        <div class="col-md-3">
            <label for="status" class="form-label">Statut</label>
            <select class="form-select" id="status" name="status">
                <option value="">Tous les statuts</option>
                <option value="en_attente" <?php echo $status_filter === 'en_attente' ? 'selected' : ''; ?>>En attente</option>
                <option value="approuve" <?php echo $status_filter === 'approuve' ? 'selected' : ''; ?>>Approuvé</option>
                <option value="rejete" <?php echo $status_filter === 'rejete' ? 'selected' : ''; ?>>Rejeté</option>
                <option value="complete" <?php echo $status_filter === 'complete' ? 'selected' : ''; ?>>Complété</option>
            </select>
        </div>
        
        <div class="col-md-3">
            <label for="type" class="form-label">Type</label>
            <select class="form-select" id="type" name="type">
                <option value="">Tous les types</option>
                <option value="transfert_entrant" <?php echo $type_filter === 'transfert_entrant' ? 'selected' : ''; ?>>Transfert entrant</option>
                <option value="transfert_sortant" <?php echo $type_filter === 'transfert_sortant' ? 'selected' : ''; ?>>Transfert sortant</option>
                <option value="sortie_definitive" <?php echo $type_filter === 'sortie_definitive' ? 'selected' : ''; ?>>Sortie définitive</option>
            </select>
        </div>
        
        <div class="col-md-2">
            <label for="date_from" class="form-label">Du</label>
            <input type="date" class="form-select" id="date_from" name="date_from" value="<?php echo htmlspecialchars($date_from); ?>">
        </div>
        
        <div class="col-md-2">
            <label for="date_to" class="form-label">Au</label>
            <input type="date" class="form-select" id="date_to" name="date_to" value="<?php echo htmlspecialchars($date_to); ?>">
        </div>
        
        <div class="col-md-2 d-flex align-items-end">
            <button type="submit" class="btn btn-primary btn-modern w-100">
                <i class="fas fa-filter me-1"></i>
                Filtrer
            </button>
        </div>
    </form>
</div>

<!-- Actions en masse -->
<?php if (!empty($transfers)): ?>
<form method="POST" id="bulkForm">
    <div class="bulk-actions animate-fade-in animate-delay-3">
        <div class="d-flex align-items-center justify-content-between flex-wrap">
            <div class="d-flex align-items-center gap-3">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="selectAll" onchange="toggleAllTransfers()">
                    <label class="form-check-label fw-bold" for="selectAll">
                        Tout sélectionner
                    </label>
                </div>
                <span id="selectedCount" class="text-muted">0 sélectionné(s)</span>
            </div>
            
            <div class="d-flex gap-2">
                <select class="form-select" name="bulk_action" id="bulkAction" required>
                    <option value="">Choisir une action</option>
                    <option value="approve">Approuver</option>
                    <option value="reject">Rejeter</option>
                    <option value="complete">Compléter</option>
                    <option value="generate_certificates">Générer certificats</option>
                </select>
                <button type="submit" class="btn btn-warning btn-modern" id="bulkSubmit" disabled>
                    <i class="fas fa-cogs me-1"></i>
                    Exécuter
                </button>
            </div>
        </div>
    </div>

    <!-- Tableau des transferts -->
    <div class="table-modern animate-fade-in animate-delay-3">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th width="50">
                            <input type="checkbox" id="selectAllHeader" onchange="toggleAllTransfers()">
                        </th>
                        <th>Élève</th>
                        <th>Type</th>
                        <th>École</th>
                        <th>Date demande</th>
                        <th>Statut</th>
                        <th>Traité par</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($transfers as $transfer): ?>
                        <tr>
                            <td>
                                <input type="checkbox" class="form-check-input transfer-checkbox" 
                                       name="selected_transfers[]" value="<?php echo $transfer['id']; ?>"
                                       onchange="updateSelectedCount()">
                            </td>
                            <td>
                                <div class="fw-bold"><?php echo htmlspecialchars($transfer['nom'] . ' ' . $transfer['prenom']); ?></div>
                                <small class="text-muted"><?php echo htmlspecialchars($transfer['numero_matricule']); ?></small>
                            </td>
                            <td>
                                <?php
                                $type_labels = [
                                    'transfert_entrant' => '<i class="fas fa-arrow-right text-success"></i> Entrant',
                                    'transfert_sortant' => '<i class="fas fa-arrow-left text-warning"></i> Sortant',
                                    'sortie_definitive' => '<i class="fas fa-graduation-cap text-info"></i> Sortie'
                                ];
                                echo $type_labels[$transfer['type_mouvement']] ?? $transfer['type_mouvement'];
                                ?>
                            </td>
                            <td>
                                <?php if ($transfer['type_mouvement'] === 'transfert_entrant'): ?>
                                    <div class="fw-bold">De: <?php echo htmlspecialchars($transfer['ecole_origine']); ?></div>
                                <?php else: ?>
                                    <div class="fw-bold">Vers: <?php echo htmlspecialchars($transfer['ecole_destination'] ?: 'Non spécifié'); ?></div>
                                <?php endif; ?>
                            </td>
                            <td><?php echo date('d/m/Y', strtotime($transfer['date_demande'])); ?></td>
                            <td>
                                <span class="status-badge status-<?php echo $transfer['statut']; ?>">
                                    <?php echo ucfirst(str_replace('_', ' ', $transfer['statut'])); ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($transfer['traite_par_nom']): ?>
                                    <small><?php echo htmlspecialchars($transfer['traite_par_nom'] . ' ' . $transfer['traite_par_prenom']); ?></small>
                                <?php else: ?>
                                    <small class="text-muted">Non assigné</small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="btn-group btn-group-sm">
                                    <a href="view-transfer.php?id=<?php echo $transfer['id']; ?>" 
                                       class="btn btn-outline-primary btn-sm" title="Voir">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <?php if ($transfer['statut'] === 'complete' && $transfer['certificat_genere']): ?>
                                        <a href="certificates/generate.php?id=<?php echo $transfer['id']; ?>" 
                                           class="btn btn-outline-success btn-sm" title="Certificat">
                                            <i class="fas fa-certificate"></i>
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</form>
<?php else: ?>
<div class="text-center py-5">
    <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
    <h5 class="text-muted">Aucun transfert trouvé</h5>
    <p class="text-muted">Aucun transfert ne correspond aux critères sélectionnés.</p>
</div>
<?php endif; ?>

<script>
// Sélectionner/désélectionner tous les transferts
function toggleAllTransfers() {
    const selectAll = document.getElementById('selectAll');
    const selectAllHeader = document.getElementById('selectAllHeader');
    const checkboxes = document.querySelectorAll('.transfer-checkbox');
    
    // Synchroniser les deux cases "tout sélectionner"
    selectAll.checked = selectAllHeader.checked = selectAll.checked || selectAllHeader.checked;
    
    checkboxes.forEach(checkbox => {
        checkbox.checked = selectAll.checked;
    });
    
    updateSelectedCount();
}

// Mettre à jour le compteur de sélection
function updateSelectedCount() {
    const checkboxes = document.querySelectorAll('.transfer-checkbox:checked');
    const count = checkboxes.length;
    
    document.getElementById('selectedCount').textContent = count + ' sélectionné(s)';
    document.getElementById('bulkSubmit').disabled = count === 0;
    
    // Mettre à jour l'état des cases "tout sélectionner"
    const allCheckboxes = document.querySelectorAll('.transfer-checkbox');
    const selectAll = document.getElementById('selectAll');
    const selectAllHeader = document.getElementById('selectAllHeader');
    
    if (count === 0) {
        selectAll.indeterminate = selectAllHeader.indeterminate = false;
        selectAll.checked = selectAllHeader.checked = false;
    } else if (count === allCheckboxes.length) {
        selectAll.indeterminate = selectAllHeader.indeterminate = false;
        selectAll.checked = selectAllHeader.checked = true;
    } else {
        selectAll.indeterminate = selectAllHeader.indeterminate = true;
        selectAll.checked = selectAllHeader.checked = false;
    }
}

// Validation du formulaire
document.getElementById('bulkForm').addEventListener('submit', function(e) {
    const selectedTransfers = document.querySelectorAll('.transfer-checkbox:checked');
    const action = document.getElementById('bulkAction').value;
    
    if (selectedTransfers.length === 0) {
        e.preventDefault();
        alert('Veuillez sélectionner au moins un transfert');
        return false;
    }
    
    if (!action) {
        e.preventDefault();
        alert('Veuillez choisir une action');
        return false;
    }
    
    const actionLabels = {
        'approve': 'approuver',
        'reject': 'rejeter',
        'complete': 'compléter',
        'generate_certificates': 'générer les certificats pour'
    };
    
    const confirmMessage = `Êtes-vous sûr de vouloir ${actionLabels[action]} ${selectedTransfers.length} transfert(s) ?`;
    
    if (!confirm(confirmMessage)) {
        e.preventDefault();
        return false;
    }
});

// Initialiser le compteur
updateSelectedCount();
</script>

<?php include '../../../includes/footer.php'; ?>
