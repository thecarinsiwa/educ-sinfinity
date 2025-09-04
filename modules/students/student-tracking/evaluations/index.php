<?php
/**
 * Module de Gestion des Évaluations d'Admission
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

$page_title = 'Gestion des Évaluations';

// Traitement des actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && checkPermission('students')) {
    try {
        $action = $_POST['action'] ?? '';
        
        switch ($action) {
            case 'add_evaluation':
                $demande_id = intval($_POST['demande_id']);
                $type_evaluation = $_POST['type_evaluation'];
                $date_evaluation = $_POST['date_evaluation'];
                $heure_debut = $_POST['heure_debut'];
                $heure_fin = $_POST['heure_fin'];
                $lieu = sanitizeInput($_POST['lieu']);
                $evaluateur_id = intval($_POST['evaluateur_id']);
                
                // Insérer l'évaluation
                $database->execute(
                    "INSERT INTO evaluations_admission (demande_admission_id, type_evaluation, date_evaluation, 
                     heure_debut, heure_fin, lieu, evaluateur_id, created_at) 
                     VALUES (?, ?, ?, ?, ?, ?, ?, NOW())",
                    [$demande_id, $type_evaluation, $date_evaluation, $heure_debut, $heure_fin, $lieu, $evaluateur_id]
                );
                
                // Mettre à jour le statut de la demande
                $database->execute(
                    "UPDATE demandes_admission SET status = 'en_cours_traitement', updated_at = NOW() WHERE id = ?",
                    [$demande_id]
                );
                
                showMessage('success', 'Évaluation programmée avec succès.');
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
$type_evaluation_filter = $_GET['type_evaluation'] ?? '';
$search = trim($_GET['search'] ?? '');

// Construction de la requête
$where_conditions = ["1=1"];
$params = [];

if ($status_filter) {
    $where_conditions[] = "da.status = ?";
    $params[] = $status_filter;
}

if ($type_evaluation_filter) {
    $where_conditions[] = "ea.type_evaluation = ?";
    $params[] = $type_evaluation_filter;
}

if ($search) {
    $where_conditions[] = "(da.nom_eleve LIKE ? OR da.prenom_eleve LIKE ? OR da.numero_demande LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

$where_clause = implode(' AND ', $where_conditions);

// Récupérer les évaluations
try {
    $evaluations = $database->query(
        "SELECT ea.*, da.numero_demande, da.nom_eleve, da.prenom_eleve, da.status as status_demande,
                c.nom as classe_demandee, c.niveau,
                u.username as evaluateur_nom
         FROM evaluations_admission ea
         JOIN demandes_admission da ON ea.demande_admission_id = da.id
         LEFT JOIN classes c ON da.classe_demandee_id = c.id
         LEFT JOIN users u ON ea.evaluateur_id = u.id
         WHERE $where_clause
         ORDER BY ea.date_evaluation DESC, ea.heure_debut ASC
         LIMIT $per_page OFFSET $offset",
        $params
    )->fetchAll();
} catch (Exception $e) {
    $evaluations = [];
}

// Récupérer les demandes sans évaluation
try {
    $demandes_sans_evaluation = $database->query(
        "SELECT da.*, c.nom as classe_demandee, c.niveau
         FROM demandes_admission da
         LEFT JOIN classes c ON da.classe_demandee_id = c.id
         WHERE da.status = 'en_attente' 
         AND da.annee_scolaire_id = ?
         AND NOT EXISTS (SELECT 1 FROM evaluations_admission ea WHERE ea.demande_admission_id = da.id)
         ORDER BY da.created_at ASC
         LIMIT 20",
        [$current_year['id'] ?? 0]
    )->fetchAll();
} catch (Exception $e) {
    $demandes_sans_evaluation = [];
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
                        <li class="breadcrumb-item active">Gestion des Évaluations</li>
                    </ol>
                </div>
                <h4 class="page-title">
                    <i class="mdi mdi-clipboard-check me-2"></i>
                    Gestion des Évaluations d'Admission
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
                            <label for="type_evaluation_filter" class="form-label">Type</label>
                            <select class="form-select" id="type_evaluation_filter" name="type_evaluation_filter">
                                <option value="">Tous</option>
                                <option value="test_ecrit" <?php echo $type_evaluation_filter === 'test_ecrit' ? 'selected' : ''; ?>>Test écrit</option>
                                <option value="entretien" <?php echo $type_evaluation_filter === 'entretien' ? 'selected' : ''; ?>>Entretien</option>
                                <option value="examen_medical" <?php echo $type_evaluation_filter === 'examen_medical' ? 'selected' : ''; ?>>Examen médical</option>
                                <option value="evaluation_psychologique" <?php echo $type_evaluation_filter === 'evaluation_psychologique' ? 'selected' : ''; ?>>Évaluation psychologique</option>
                                <option value="test_niveau" <?php echo $type_evaluation_filter === 'test_niveau' ? 'selected' : ''; ?>>Test de niveau</option>
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
        <!-- Liste des évaluations -->
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header">
                    <h4 class="header-title">
                        <i class="mdi mdi-format-list-bulleted me-2"></i>
                        Évaluations Programmes
                    </h4>
                </div>
                <div class="card-body">
                    <?php if (!empty($evaluations)): ?>
                        <div class="table-responsive">
                            <table class="table table-centered table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>Élève</th>
                                        <th>Type</th>
                                        <th>Date & Heure</th>
                                        <th>Lieu</th>
                                        <th>Évaluateur</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($evaluations as $evaluation): ?>
                                        <tr>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <div class="avatar-sm me-3">
                                                        <span class="avatar-title bg-soft-primary rounded-circle">
                                                            <?php echo strtoupper(substr($evaluation['prenom_eleve'], 0, 1)); ?>
                                                        </span>
                                                    </div>
                                                    <div>
                                                        <h6 class="mb-0"><?php echo $evaluation['prenom_eleve'] . ' ' . $evaluation['nom_eleve']; ?></h6>
                                                        <small class="text-muted"><?php echo $evaluation['numero_demande']; ?></small>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="badge bg-info">
                                                    <?php echo ucfirst(str_replace('_', ' ', $evaluation['type_evaluation'])); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div>
                                                    <strong><?php echo date('d/m/Y', strtotime($evaluation['date_evaluation'])); ?></strong>
                                                    <br>
                                                    <small class="text-muted">
                                                        <?php echo $evaluation['heure_debut'] . ' - ' . $evaluation['heure_fin']; ?>
                                                    </small>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="text-muted"><?php echo $evaluation['lieu'] ?: 'Non défini'; ?></span>
                                            </td>
                                            <td>
                                                <span class="text-muted"><?php echo $evaluation['evaluateur_nom'] ?: 'Non assigné'; ?></span>
                                            </td>
                                            <td>
                                                <div class="btn-group" role="group">
                                                    <a href="view.php?id=<?php echo $evaluation['id']; ?>" 
                                                       class="btn btn-sm btn-outline-primary">
                                                        <i class="mdi mdi-eye"></i>
                                                    </a>
                                                    <a href="edit.php?id=<?php echo $evaluation['id']; ?>" 
                                                       class="btn btn-sm btn-outline-warning">
                                                        <i class="mdi mdi-pencil"></i>
                                                    </a>
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
                            <p class="text-muted mt-2">Aucune évaluation trouvée</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Demandes sans évaluation -->
        <div class="col-lg-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="header-title">
                        <i class="mdi mdi-clock-outline me-2"></i>
                        Demandes sans Évaluation
                    </h5>
                </div>
                <div class="card-body">
                    <?php if (!empty($demandes_sans_evaluation)): ?>
                        <div class="list-group list-group-flush">
                            <?php foreach ($demandes_sans_evaluation as $demande): ?>
                                <div class="list-group-item d-flex justify-content-between align-items-start">
                                    <div class="ms-2 me-auto">
                                        <div class="fw-bold"><?php echo $demande['prenom_eleve'] . ' ' . $demande['nom_eleve']; ?></div>
                                        <small class="text-muted">
                                            <?php echo $demande['classe_demandee']; ?> - <?php echo $demande['numero_demande']; ?>
                                        </small>
                                    </div>
                                    <a href="add.php?demande_id=<?php echo $demande['id']; ?>" 
                                       class="btn btn-sm btn-outline-primary">
                                        <i class="mdi mdi-plus"></i>
                                    </a>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-3">
                            <i class="mdi mdi-check-circle text-success" style="font-size: 32px;"></i>
                            <p class="text-success mt-2">Toutes les demandes ont une évaluation !</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../../../../includes/footer.php'; ?>
