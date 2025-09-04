<?php
/**
 * Module de Gestion des Décisions d'Admission
 * Application de gestion scolaire - République Démocratique du Congo
 */

require_once '../../../../config/config.php';
require_once '../../../../config/database.php';
require_once '../../../../includes/functions.php';

// Vérifier l'authentification et les permissions
requireLogin();
if (!checkPermission('students') && !checkPermission('students_view')) {
    showMessage('error', 'Accès refusé à ce module.');
    redirectTo('../index.php');
}

$page_title = 'Gestion des Décisions';

// Traitement des actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && checkPermission('students')) {
    try {
        $action = $_POST['action'] ?? '';
        
        switch ($action) {
            case 'take_decision':
                $demande_id = intval($_POST['demande_id']);
                $decision = $_POST['decision'];
                $motif_decision = sanitizeInput($_POST['motif_decision']);
                $conditions_speciales = sanitizeInput($_POST['conditions_speciales']);
                $date_limite_reponse = $_POST['date_limite_reponse'];
                $frais_inscription_final = floatval($_POST['frais_inscription_final']);
                $frais_scolarite_final = floatval($_POST['frais_scolarite_final']);
                $reduction_finale = floatval($_POST['reduction_finale']);
                $commentaire = sanitizeInput($_POST['commentaire']);
                
                // Validation des données
                if (empty($demande_id) || empty($decision) || empty($motif_decision)) {
                    showMessage('error', 'Les champs obligatoires doivent être remplis.');
                } else {
                    // Insérer la décision
                    $database->execute(
                        "INSERT INTO decisions_admission (demande_admission_id, decision, date_decision, 
                         decideur_id, motif_decision, conditions_speciales, date_limite_reponse,
                         frais_inscription_final, frais_scolarite_final, reduction_finale, commentaire) 
                         VALUES (?, ?, CURDATE(), ?, ?, ?, ?, ?, ?, ?, ?)",
                        [$demande_id, $decision, $_SESSION['user_id'], $motif_decision, $conditions_speciales,
                         $date_limite_reponse, $frais_inscription_final, $frais_scolarite_final, 
                         $reduction_finale, $commentaire]
                    );
                    
                    // Mettre à jour le statut de la demande
                    $new_status = '';
                    switch ($decision) {
                        case 'acceptee':
                            $new_status = 'acceptee';
                            break;
                        case 'refusee':
                            $new_status = 'refusee';
                            break;
                        case 'acceptee_conditionnelle':
                            $new_status = 'acceptee';
                            break;
                        case 'mise_en_liste_attente':
                            $new_status = 'en_attente';
                            break;
                    }
                    
                    if ($new_status) {
                        $database->execute(
                            "UPDATE demandes_admission SET status = ?, updated_at = NOW() WHERE id = ?",
                            [$new_status, $demande_id]
                        );
                    }
                    
                    // Logger l'action
                    logUserAction(
                        'decision_admission', 
                        'students', 
                        "Décision d'admission prise pour la demande ID: $demande_id - Statut: $decision"
                    );
                    
                    showMessage('success', 'Décision enregistrée avec succès.');
                }
                break;
                
            case 'bulk_decision':
                $demandes = $_POST['demandes'] ?? [];
                $bulk_decision = $_POST['bulk_decision'] ?? '';
                $bulk_motif = sanitizeInput($_POST['bulk_motif'] ?? '');
                
                if (!empty($demandes) && !empty($bulk_decision) && !empty($bulk_motif)) {
                    foreach ($demandes as $demande_id) {
                        $database->execute(
                            "INSERT INTO decisions_admission (demande_admission_id, decision, date_decision, 
                             decideur_id, motif_decision) VALUES (?, ?, CURDATE(), ?, ?)",
                            [intval($demande_id), $bulk_decision, $_SESSION['user_id'], $bulk_motif]
                        );
                        
                        // Mettre à jour le statut
                        $new_status = '';
                        switch ($bulk_decision) {
                            case 'acceptee':
                                $new_status = 'acceptee';
                                break;
                            case 'refusee':
                                $new_status = 'refusee';
                                break;
                            case 'acceptee_conditionnelle':
                                $new_status = 'acceptee';
                                break;
                            case 'mise_en_liste_attente':
                                $new_status = 'en_attente';
                                break;
                        }
                        
                        if ($new_status) {
                            $database->execute(
                                "UPDATE demandes_admission SET status = ?, updated_at = NOW() WHERE id = ?",
                                [$new_status, intval($demande_id)]
                            );
                        }
                    }
                    
                    showMessage('success', count($demandes) . ' décisions prises en lot.');
                } else {
                    showMessage('error', 'Tous les champs sont obligatoires pour la décision en lot.');
                }
                break;
        }
    } catch (Exception $e) {
        showMessage('error', 'Erreur lors de l\'opération : ' . $e->getMessage());
    }
}

// Paramètres de pagination et filtres
$page = intval($_GET['page'] ?? 1);
$per_page = 20;
$offset = ($page - 1) * $per_page;

$status_filter = $_GET['status'] ?? '';
$decision_filter = $_GET['decision'] ?? '';
$search = trim($_GET['search'] ?? '');

// Construction de la requête
$where_conditions = ["1=1"];
$params = [];

if ($status_filter) {
    $where_conditions[] = "da.status = ?";
    $params[] = $status_filter;
}

if ($decision_filter) {
    $where_conditions[] = "d.decision = ?";
    $params[] = $decision_filter;
}

if ($search) {
    $where_conditions[] = "(da.nom_eleve LIKE ? OR da.prenom_eleve LIKE ? OR da.numero_demande LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

$where_clause = implode(' AND ', $where_conditions);

// Récupérer les demandes avec leurs décisions
try {
    $demandes = $database->query(
        "SELECT da.*, c.nom as classe_demandee, c.niveau,
                d.decision, d.date_decision, d.motif_decision, d.conditions_speciales,
                u.username as decideur_nom,
                CASE 
                    WHEN da.status = 'en_attente' THEN 'Évaluation en attente'
                    WHEN da.status = 'en_cours_traitement' THEN 'En cours de traitement'
                    WHEN da.status = 'acceptee' THEN 'Acceptée'
                    WHEN da.status = 'refusee' THEN 'Refusée'
                    WHEN da.status = 'inscrit' THEN 'Inscrit'
                    ELSE da.status
                END as status_lisible
         FROM demandes_admission da
         LEFT JOIN classes c ON da.classe_demandee_id = c.id
         LEFT JOIN decisions_admission d ON da.id = d.demande_admission_id
         LEFT JOIN users u ON d.decideur_id = u.id
         WHERE $where_clause
         ORDER BY da.created_at DESC
         LIMIT $per_page OFFSET $offset",
        $params
    )->fetchAll();
} catch (Exception $e) {
    $demandes = [];
}

// Récupérer les demandes évaluées en attente de décision
try {
    $demandes_attente_decision = $database->query(
        "SELECT da.*, c.nom as classe_demandee, c.niveau,
                ea.note_evaluation, ea.commentaire_evaluation, ea.recommandation,
                u.username as evaluateur_nom
         FROM demandes_admission da
         LEFT JOIN classes c ON da.classe_demandee_id = c.id
         LEFT JOIN evaluations_admission ea ON da.id = ea.demande_admission_id
         LEFT JOIN users u ON ea.evaluateur_id = u.id
         WHERE da.status = 'en_cours_traitement' 
         AND da.annee_scolaire_id = ?
         AND NOT EXISTS (SELECT 1 FROM decisions_admission d WHERE d.demande_admission_id = da.id)
         AND EXISTS (SELECT 1 FROM evaluations_admission ea2 WHERE ea2.demande_admission_id = da.id)
         ORDER BY da.created_at ASC
         LIMIT 20",
        [getCurrentAcademicYear()['id'] ?? 0]
    )->fetchAll();
} catch (Exception $e) {
    $demandes_attente_decision = [];
}

include '../../../../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="page-title-box">
                <div class="page-title-right">
                    <ol class="breadcrumb m-0">
                        <li class="breadcrumb-item"><a href="../../../../dashboard.php">Tableau de bord</a></li>
                        <li class="breadcrumb-item"><a href="../../index.php">Suivi des Élèves</a></li>
                        <li class="breadcrumb-item active">Gestion des Décisions</li>
                    </ol>
                </div>
                <h4 class="page-title">
                    <i class="mdi mdi-gavel me-2"></i>
                    Gestion des Décisions d'Admission
                </h4>
            </div>
        </div>
    </div>

    <?php displayMessage(); ?>

    <!-- Filtres et recherche -->
    <div class="row mb-3">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <form method="GET" class="row g-3">
                        <div class="col-md-4">
                            <label for="search" class="form-label">Recherche</label>
                            <input type="text" class="form-control" id="search" name="search" 
                                   value="<?php echo htmlspecialchars($search); ?>" 
                                   placeholder="Nom, prénom ou numéro...">
                        </div>
                        <div class="col-md-3">
                            <label for="status_filter" class="form-label">Statut</label>
                            <select class="form-select" id="status_filter" name="status_filter">
                                <option value="">Tous</option>
                                <option value="en_attente" <?php echo $status_filter === 'en_attente' ? 'selected' : ''; ?>>En attente</option>
                                <option value="en_cours_traitement" <?php echo $status_filter === 'en_cours_traitement' ? 'selected' : ''; ?>>En cours</option>
                                <option value="acceptee" <?php echo $status_filter === 'acceptee' ? 'selected' : ''; ?>>Acceptée</option>
                                <option value="refusee" <?php echo $status_filter === 'refusee' ? 'selected' : ''; ?>>Refusée</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label for="decision_filter" class="form-label">Décision</label>
                            <select class="form-select" id="decision_filter" name="decision_filter">
                                <option value="">Toutes</option>
                                <option value="acceptee" <?php echo $decision_filter === 'acceptee' ? 'selected' : ''; ?>>Acceptée</option>
                                <option value="refusee" <?php echo $decision_filter === 'refusee' ? 'selected' : ''; ?>>Refusée</option>
                                <option value="acceptee_conditionnelle" <?php echo $decision_filter === 'acceptee_conditionnelle' ? 'selected' : ''; ?>>Acceptation conditionnelle</option>
                                <option value="mise_en_liste_attente" <?php echo $decision_filter === 'mise_en_liste_attente' ? 'selected' : ''; ?>>Liste d'attente</option>
                            </select>
                        </div>
                        <div class="col-md-2 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary me-2">
                                <i class="mdi mdi-magnify me-1"></i>
                                Filtrer
                            </button>
                            <a href="?" class="btn btn-secondary">
                                <i class="mdi mdi-refresh me-1"></i>
                                Reset
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Demandes en attente de décision -->
        <div class="col-lg-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="header-title">
                        <i class="mdi mdi-clock-outline me-2"></i>
                        Demandes en Attente de Décision
                    </h5>
                </div>
                <div class="card-body">
                    <?php if (!empty($demandes_attente_decision)): ?>
                        <div class="list-group list-group-flush">
                            <?php foreach ($demandes_attente_decision as $demande): ?>
                                <div class="list-group-item">
                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                        <div class="fw-bold"><?php echo $demande['prenom_eleve'] . ' ' . $demande['nom_eleve']; ?></div>
                                        <a href="take-decision.php?demande_id=<?php echo $demande['id']; ?>" 
                                           class="btn btn-sm btn-outline-primary">
                                            <i class="mdi mdi-gavel"></i>
                                        </a>
                                    </div>
                                    <small class="text-muted">
                                        <?php echo $demande['numero_demande']; ?> - 
                                        <?php echo $demande['classe_demandee']; ?>
                                    </small>
                                    <?php if ($demande['note_evaluation']): ?>
                                        <div class="mt-2">
                                            <span class="badge bg-info">
                                                Note: <?php echo $demande['note_evaluation']; ?>/20
                                            </span>
                                            <?php if ($demande['recommandation']): ?>
                                                <span class="badge bg-warning ms-1">
                                                    <?php echo ucfirst($demande['recommandation']); ?>
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-3">
                            <i class="mdi mdi-check-circle text-success" style="font-size: 32px;"></i>
                            <p class="text-success mt-2">Toutes les demandes ont une décision !</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Liste des demandes avec décisions -->
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header">
                    <h4 class="header-title">
                        <i class="mdi mdi-format-list-bulleted me-2"></i>
                        Demandes et Décisions
                    </h4>
                </div>
                <div class="card-body">
                    <?php if (!empty($demandes)): ?>
                        <div class="table-responsive">
                            <table class="table table-centered table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>Élève</th>
                                        <th>Classe</th>
                                        <th>Statut</th>
                                        <th>Décision</th>
                                        <th>Date Décision</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($demandes as $demande): ?>
                                        <tr>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <div class="avatar-sm me-3">
                                                        <span class="avatar-title bg-soft-primary rounded-circle">
                                                            <?php echo strtoupper(substr($demande['prenom_eleve'], 0, 1)); ?>
                                                        </span>
                                                    </div>
                                                    <div>
                                                        <h6 class="mb-0"><?php echo $demande['prenom_eleve'] . ' ' . $demande['nom_eleve']; ?></h6>
                                                        <small class="text-muted"><?php echo $demande['numero_demande']; ?></small>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="badge bg-light text-dark">
                                                    <?php echo $demande['classe_demandee']; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php
                                                $status_class = '';
                                                switch ($demande['status']) {
                                                    case 'en_attente':
                                                        $status_class = 'bg-warning';
                                                        break;
                                                    case 'en_cours_traitement':
                                                        $status_class = 'bg-info';
                                                        break;
                                                    case 'acceptee':
                                                        $status_class = 'bg-success';
                                                        break;
                                                    case 'refusee':
                                                        $status_class = 'bg-danger';
                                                        break;
                                                    case 'inscrit':
                                                        $status_class = 'bg-primary';
                                                        break;
                                                    default:
                                                        $status_class = 'bg-secondary';
                                                }
                                                ?>
                                                <span class="badge <?php echo $status_class; ?>">
                                                    <?php echo $demande['status_lisible']; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php if ($demande['decision']): ?>
                                                    <?php
                                                    $decision_class = '';
                                                    switch ($demande['decision']) {
                                                        case 'acceptee':
                                                            $decision_class = 'bg-success';
                                                            break;
                                                        case 'refusee':
                                                            $decision_class = 'bg-danger';
                                                            break;
                                                        case 'acceptee_conditionnelle':
                                                            $decision_class = 'bg-warning';
                                                            break;
                                                        case 'mise_en_liste_attente':
                                                            $decision_class = 'bg-info';
                                                            break;
                                                        default:
                                                            $decision_class = 'bg-secondary';
                                                    }
                                                    ?>
                                                    <span class="badge <?php echo $decision_class; ?>">
                                                        <?php echo ucfirst(str_replace('_', ' ', $demande['decision'])); ?>
                                                    </span>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary">Aucune</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($demande['date_decision']): ?>
                                                    <small class="text-muted">
                                                        <?php echo date('d/m/Y', strtotime($demande['date_decision'])); ?>
                                                    </small>
                                                <?php else: ?>
                                                    <span class="text-muted">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <div class="btn-group" role="group">
                                                    <a href="view.php?id=<?php echo $demande['id']; ?>" 
                                                       class="btn btn-sm btn-outline-primary">
                                                        <i class="mdi mdi-eye"></i>
                                                    </a>
                                                    <?php if (!$demande['decision']): ?>
                                                        <a href="take-decision.php?demande_id=<?php echo $demande['id']; ?>" 
                                                           class="btn btn-sm btn-outline-warning">
                                                            <i class="mdi mdi-gavel"></i>
                                                        </a>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-4">
                            <i class="mdi mdi-information-outline text-muted" style="font-size: 48px;"></i>
                            <p class="text-muted mt-2">Aucune demande trouvée</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../../../../includes/footer.php'; ?>
