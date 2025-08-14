<?php
/**
 * Module Bibliothèque - Gestion des emprunts
 * Application de gestion scolaire - République Démocratique du Congo
 */

require_once '../../../config/config.php';
require_once '../../../config/database.php';
require_once '../../../includes/functions.php';

// Vérifier l'authentification et les permissions
requireLogin();
if (!checkPermission('library') && !checkPermission('library_view')) {
    showMessage('error', 'Accès refusé à ce module.');
    redirectTo('../index.php');
}

// Traitement des actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && checkPermission('library')) {
    try {
        $action = $_POST['action'] ?? '';
        
        switch ($action) {
            case 'return_book':
                $emprunt_id = intval($_POST['emprunt_id']);
                $notes_retour = trim($_POST['notes_retour'] ?? '');
                $etat_retour = $_POST['etat_retour'] ?? 'bon';
                
                // Récupérer l'emprunt
                $emprunt = $database->query(
                    "SELECT * FROM emprunts_livres WHERE id = ? AND status = 'en_cours'",
                    [$emprunt_id]
                )->fetch();
                
                if (!$emprunt) {
                    throw new Exception('Emprunt non trouvé ou déjà retourné.');
                }
                
                // Calculer les pénalités si en retard
                $penalite = 0;
                if (date('Y-m-d') > $emprunt['date_retour_prevue']) {
                    $jours_retard = (strtotime(date('Y-m-d')) - strtotime($emprunt['date_retour_prevue'])) / (60 * 60 * 24);
                    $penalite_par_jour = 100; // À récupérer des paramètres
                    $penalite = $jours_retard * $penalite_par_jour;
                }
                
                // Commencer une transaction
                $database->beginTransaction();
                
                try {
                    // Mettre à jour l'emprunt
                    $database->execute(
                        "UPDATE emprunts_livres 
                         SET status = 'rendu', date_retour_effective = CURDATE(), 
                             notes_retour = ?, penalite = ?, rendu_par = ?, updated_at = NOW()
                         WHERE id = ?",
                        [$notes_retour, $penalite, $_SESSION['user_id'], $emprunt_id]
                    );
                    
                    // Mettre à jour le statut du livre
                    $database->execute(
                        "UPDATE livres SET status = 'disponible', etat = ?, updated_at = NOW() WHERE id = ?",
                        [$etat_retour, $emprunt['livre_id']]
                    );
                    
                    // Créer une pénalité si nécessaire
                    if ($penalite > 0) {
                        $database->execute(
                            "INSERT INTO penalites_bibliotheque 
                             (emprunt_id, type_penalite, montant, description, status, date_penalite, traite_par, created_at)
                             VALUES (?, 'retard', ?, ?, 'impayee', CURDATE(), ?, NOW())",
                            [
                                $emprunt_id, 
                                $penalite, 
                                "Retard de retour de livre", 
                                $_SESSION['user_id']
                            ]
                        );
                    }
                    
                    $database->commit();
                    
                    $message = 'Livre retourné avec succès.';
                    if ($penalite > 0) {
                        $message .= " Pénalité de retard : " . number_format($penalite) . " FC.";
                    }
                    showMessage('success', $message);
                    
                } catch (Exception $e) {
                    $database->rollback();
                    throw $e;
                }
                
                break;
                
            case 'extend_loan':
                $emprunt_id = intval($_POST['emprunt_id']);
                $nouvelle_date = $_POST['nouvelle_date_retour'] ?? '';
                
                if (!$nouvelle_date) {
                    throw new Exception('Nouvelle date de retour requise.');
                }
                
                $database->execute(
                    "UPDATE emprunts_livres 
                     SET date_retour_prevue = ?, updated_at = NOW()
                     WHERE id = ? AND status = 'en_cours'",
                    [$nouvelle_date, $emprunt_id]
                );
                
                showMessage('success', 'Durée d\'emprunt prolongée avec succès.');
                break;
        }
    } catch (Exception $e) {
        showMessage('error', 'Erreur : ' . $e->getMessage());
    }
}

// Paramètres de pagination et filtres
$page = intval($_GET['page'] ?? 1);
$per_page = 20;
$offset = ($page - 1) * $per_page;

$search = trim($_GET['search'] ?? '');
$status_filter = $_GET['status'] ?? '';
$type_filter = $_GET['type'] ?? '';
$retard_filter = $_GET['retard'] ?? '';

// Construction de la requête
$where_conditions = ["1=1"];
$params = [];

if ($search) {
    $where_conditions[] = "(l.titre LIKE ? OR l.auteur LIKE ? OR 
                           CONCAT(COALESCE(e.nom, ''), ' ', COALESCE(e.prenom, '')) LIKE ? OR
                           CONCAT(COALESCE(p.nom, ''), ' ', COALESCE(p.prenom, '')) LIKE ?)";
    $search_param = "%$search%";
    $params = array_merge($params, [$search_param, $search_param, $search_param, $search_param]);
}

if ($status_filter) {
    $where_conditions[] = "el.status = ?";
    $params[] = $status_filter;
}

if ($type_filter) {
    $where_conditions[] = "el.emprunteur_type = ?";
    $params[] = $type_filter;
}

if ($retard_filter === 'oui') {
    $where_conditions[] = "el.status = 'en_cours' AND el.date_retour_prevue < CURDATE()";
} elseif ($retard_filter === 'non') {
    $where_conditions[] = "el.status = 'en_cours' AND el.date_retour_prevue >= CURDATE()";
}

$where_clause = implode(' AND ', $where_conditions);

// Récupérer les emprunts
try {
    $emprunts = $database->query(
        "SELECT el.*, l.titre, l.auteur, l.isbn,
                CONCAT('Emprunteur ', el.emprunteur_id) as emprunteur_nom,
                CASE
                    WHEN el.emprunteur_type = 'eleve' THEN 'Élève'
                    WHEN el.emprunteur_type = 'personnel' THEN 'Personnel'
                    ELSE 'Inconnu'
                END as info_supplementaire,
                u1.username as traite_par_nom,
                u2.username as rendu_par_nom,
                DATEDIFF(CURDATE(), el.date_retour_prevue) as jours_retard
         FROM emprunts_livres el
         JOIN livres l ON el.livre_id = l.id
         LEFT JOIN users u1 ON el.traite_par = u1.id
         LEFT JOIN users u2 ON el.rendu_par = u2.id
         WHERE $where_clause
         ORDER BY
            CASE el.status
                WHEN 'en_cours' THEN 1
                WHEN 'en_retard' THEN 2
                ELSE 3
            END,
            el.date_retour_prevue ASC,
            el.date_emprunt DESC
         LIMIT $per_page OFFSET $offset",
        $params
    )->fetchAll();

    // Compter le total pour la pagination
    $total_emprunts = $database->query(
        "SELECT COUNT(*) as total
         FROM emprunts_livres el
         JOIN livres l ON el.livre_id = l.id
         WHERE $where_clause",
        $params
    )->fetch()['total'];

} catch (Exception $e) {
    $emprunts = [];
    $total_emprunts = 0;
    showMessage('error', 'Erreur lors du chargement : ' . $e->getMessage());
}

// Statistiques
try {
    $stats = $database->query(
        "SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN status = 'en_cours' THEN 1 ELSE 0 END) as en_cours,
            SUM(CASE WHEN status = 'rendu' THEN 1 ELSE 0 END) as rendus,
            SUM(CASE WHEN status = 'en_cours' AND date_retour_prevue < CURDATE() THEN 1 ELSE 0 END) as en_retard,
            SUM(CASE WHEN status = 'perdu' THEN 1 ELSE 0 END) as perdus
         FROM emprunts_livres"
    )->fetch();
} catch (Exception $e) {
    $stats = [
        'total' => 0, 'en_cours' => 0, 'rendus' => 0, 'en_retard' => 0, 'perdus' => 0
    ];
}

$total_pages = ceil($total_emprunts / $per_page);

$page_title = "Gestion des Emprunts";
include '../../../includes/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">
        <i class="fas fa-exchange-alt me-2"></i>
        Gestion des Emprunts
    </h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <div class="btn-group me-2">
            <a href="../" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-1"></i>
                Retour à la bibliothèque
            </a>
        </div>
        <?php if (checkPermission('library')): ?>
            <div class="btn-group me-2">
                <a href="add.php" class="btn btn-primary">
                    <i class="fas fa-plus me-1"></i>
                    Nouvel emprunt
                </a>
            </div>
            <div class="btn-group me-2">
                <a href="returns.php" class="btn btn-success">
                    <i class="fas fa-undo me-1"></i>
                    Retours rapides
                </a>
            </div>
        <?php endif; ?>
        <div class="btn-group">
            <button type="button" class="btn btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">
                <i class="fas fa-download me-1"></i>
                Export
            </button>
            <ul class="dropdown-menu">
                <li><a class="dropdown-item" href="export.php?format=csv">
                    <i class="fas fa-file-csv me-2"></i>Export CSV
                </a></li>
                <li><a class="dropdown-item" href="export.php?format=excel">
                    <i class="fas fa-file-excel me-2"></i>Export Excel
                </a></li>
            </ul>
        </div>
    </div>
</div>

<!-- Statistiques -->
<div class="row mb-4">
    <div class="col-md-2">
        <div class="card text-center">
            <div class="card-body">
                <i class="fas fa-list fa-2x text-primary mb-2"></i>
                <h5 class="card-title"><?php echo number_format($stats['total'] ?? 0); ?></h5>
                <p class="card-text text-muted">Total</p>
            </div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card text-center">
            <div class="card-body">
                <i class="fas fa-clock fa-2x text-info mb-2"></i>
                <h5 class="card-title"><?php echo number_format($stats['en_cours'] ?? 0); ?></h5>
                <p class="card-text text-muted">En cours</p>
            </div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card text-center">
            <div class="card-body">
                <i class="fas fa-check-circle fa-2x text-success mb-2"></i>
                <h5 class="card-title"><?php echo number_format($stats['rendus'] ?? 0); ?></h5>
                <p class="card-text text-muted">Rendus</p>
            </div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card text-center">
            <div class="card-body">
                <i class="fas fa-exclamation-triangle fa-2x text-warning mb-2"></i>
                <h5 class="card-title"><?php echo number_format($stats['en_retard'] ?? 0); ?></h5>
                <p class="card-text text-muted">En retard</p>
            </div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card text-center">
            <div class="card-body">
                <i class="fas fa-times-circle fa-2x text-danger mb-2"></i>
                <h5 class="card-title"><?php echo number_format($stats['perdus'] ?? 0); ?></h5>
                <p class="card-text text-muted">Perdus</p>
            </div>
        </div>
    </div>
</div>

<!-- Filtres -->
<div class="card mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-md-3">
                <label for="search" class="form-label">Recherche</label>
                <input type="text" class="form-control" id="search" name="search"
                       value="<?php echo htmlspecialchars($search); ?>"
                       placeholder="Livre, emprunteur...">
            </div>
            <div class="col-md-2">
                <label for="status" class="form-label">Statut</label>
                <select class="form-select" id="status" name="status">
                    <option value="">Tous</option>
                    <option value="en_cours" <?php echo $status_filter === 'en_cours' ? 'selected' : ''; ?>>En cours</option>
                    <option value="rendu" <?php echo $status_filter === 'rendu' ? 'selected' : ''; ?>>Rendu</option>
                    <option value="en_retard" <?php echo $status_filter === 'en_retard' ? 'selected' : ''; ?>>En retard</option>
                    <option value="perdu" <?php echo $status_filter === 'perdu' ? 'selected' : ''; ?>>Perdu</option>
                </select>
            </div>
            <div class="col-md-2">
                <label for="type" class="form-label">Type</label>
                <select class="form-select" id="type" name="type">
                    <option value="">Tous</option>
                    <option value="eleve" <?php echo $type_filter === 'eleve' ? 'selected' : ''; ?>>Élèves</option>
                    <option value="personnel" <?php echo $type_filter === 'personnel' ? 'selected' : ''; ?>>Personnel</option>
                </select>
            </div>
            <div class="col-md-2">
                <label for="retard" class="form-label">Retard</label>
                <select class="form-select" id="retard" name="retard">
                    <option value="">Tous</option>
                    <option value="oui" <?php echo $retard_filter === 'oui' ? 'selected' : ''; ?>>En retard</option>
                    <option value="non" <?php echo $retard_filter === 'non' ? 'selected' : ''; ?>>À jour</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">&nbsp;</label>
                <button type="submit" class="btn btn-primary d-block w-100">
                    <i class="fas fa-search me-1"></i>
                    Filtrer
                </button>
            </div>
            <div class="col-md-1">
                <label class="form-label">&nbsp;</label>
                <a href="?" class="btn btn-outline-secondary d-block w-100">
                    <i class="fas fa-times"></i>
                </a>
            </div>
        </form>
    </div>
</div>

<!-- Liste des emprunts -->
<div class="card">
    <div class="card-header">
        <h5 class="mb-0">
            <i class="fas fa-list me-2"></i>
            Liste des emprunts (<?php echo number_format($total_emprunts ?? 0); ?>)
        </h5>
    </div>
    <div class="card-body">
        <?php if (empty($emprunts)): ?>
            <div class="text-center py-4">
                <i class="fas fa-exchange-alt fa-3x text-muted mb-3"></i>
                <h5 class="text-muted">Aucun emprunt trouvé</h5>
                <p class="text-muted">Aucun emprunt ne correspond aux critères sélectionnés.</p>
                <?php if (checkPermission('library')): ?>
                    <a href="add.php" class="btn btn-primary">
                        <i class="fas fa-plus me-1"></i>
                        Créer le premier emprunt
                    </a>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead class="table-light">
                        <tr>
                            <th>Date emprunt</th>
                            <th>Livre</th>
                            <th>Emprunteur</th>
                            <th>Retour prévu</th>
                            <th>Retour effectif</th>
                            <th>Statut</th>
                            <th>Pénalité</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($emprunts as $emprunt): ?>
                            <tr class="<?php echo ($emprunt['jours_retard'] > 0 && $emprunt['status'] === 'en_cours') ? 'table-warning' : ''; ?>">
                                <td><?php echo formatDate($emprunt['date_emprunt']); ?></td>
                                <td>
                                    <strong><?php echo htmlspecialchars($emprunt['titre']); ?></strong>
                                    <br>
                                    <small class="text-muted">
                                        <?php echo htmlspecialchars($emprunt['auteur']); ?>
                                        <?php if ($emprunt['isbn']): ?>
                                            • <?php echo htmlspecialchars($emprunt['isbn']); ?>
                                        <?php endif; ?>
                                    </small>
                                </td>
                                <td>
                                    <?php echo htmlspecialchars($emprunt['emprunteur_nom']); ?>
                                    <br>
                                    <small class="text-muted">
                                        <?php echo htmlspecialchars($emprunt['info_supplementaire']); ?>
                                        <span class="badge bg-<?php echo $emprunt['emprunteur_type'] === 'eleve' ? 'primary' : 'secondary'; ?> ms-1">
                                            <?php echo $emprunt['emprunteur_type'] === 'eleve' ? 'Élève' : 'Personnel'; ?>
                                        </span>
                                    </small>
                                </td>
                                <td>
                                    <?php echo formatDate($emprunt['date_retour_prevue']); ?>
                                    <?php if ($emprunt['jours_retard'] > 0 && $emprunt['status'] === 'en_cours'): ?>
                                        <br><small class="text-danger">
                                            <i class="fas fa-exclamation-triangle me-1"></i>
                                            <?php echo $emprunt['jours_retard']; ?> jour(s) de retard
                                        </small>
                                    <?php elseif ($emprunt['status'] === 'en_cours'): ?>
                                        <?php
                                        $jours_restants = (strtotime($emprunt['date_retour_prevue']) - strtotime(date('Y-m-d'))) / (60 * 60 * 24);
                                        if ($jours_restants <= 3 && $jours_restants >= 0): ?>
                                            <br><small class="text-warning">
                                                <i class="fas fa-clock me-1"></i>
                                                <?php echo $jours_restants; ?> jour(s) restant(s)
                                            </small>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($emprunt['date_retour_effective']): ?>
                                        <?php echo formatDate($emprunt['date_retour_effective']); ?>
                                        <?php if ($emprunt['rendu_par_nom']): ?>
                                            <br><small class="text-muted">par <?php echo htmlspecialchars($emprunt['rendu_par_nom']); ?></small>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php
                                    $status_colors = [
                                        'en_cours' => 'info',
                                        'rendu' => 'success',
                                        'en_retard' => 'warning',
                                        'perdu' => 'danger',
                                        'annule' => 'secondary'
                                    ];
                                    $status_names = [
                                        'en_cours' => 'En cours',
                                        'rendu' => 'Rendu',
                                        'en_retard' => 'En retard',
                                        'perdu' => 'Perdu',
                                        'annule' => 'Annulé'
                                    ];

                                    // Déterminer le statut réel
                                    $real_status = $emprunt['status'];
                                    if ($emprunt['status'] === 'en_cours' && $emprunt['jours_retard'] > 0) {
                                        $real_status = 'en_retard';
                                    }

                                    $color = $status_colors[$real_status] ?? 'secondary';
                                    $name = $status_names[$real_status] ?? $real_status;
                                    ?>
                                    <span class="badge bg-<?php echo $color; ?>">
                                        <?php echo $name; ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($emprunt['penalite'] > 0): ?>
                                        <span class="text-danger">
                                            <?php echo number_format($emprunt['penalite']); ?> FC
                                        </span>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <a href="view.php?id=<?php echo $emprunt['id']; ?>"
                                           class="btn btn-outline-info" title="Voir les détails">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <?php if (checkPermission('library') && $emprunt['status'] === 'en_cours'): ?>
                                            <button type="button" class="btn btn-outline-success"
                                                    onclick="returnBook(<?php echo $emprunt['id']; ?>)"
                                                    title="Retourner le livre">
                                                <i class="fas fa-undo"></i>
                                            </button>
                                            <button type="button" class="btn btn-outline-warning"
                                                    onclick="extendLoan(<?php echo $emprunt['id']; ?>)"
                                                    title="Prolonger l'emprunt">
                                                <i class="fas fa-clock"></i>
                                            </button>
                                        <?php endif; ?>
                                    </div>
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
