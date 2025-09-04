<?php
/**
 * Module de Suivi Scolaire
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

$page_title = 'Suivi Scolaire';

// Obtenir l'année scolaire actuelle
$current_year = getCurrentAcademicYear();

// Paramètres de pagination et filtres
$page = intval($_GET['page'] ?? 1);
$per_page = 20;
$offset = ($page - 1) * $per_page;

$classe_filter = $_GET['classe_filter'] ?? '';
$trimestre_filter = $_GET['trimestre_filter'] ?? '';
$search = trim($_GET['search'] ?? '');

// Construction de la requête
$where_conditions = ["e.status = 'actif'"];
$params = [];

if ($classe_filter) {
    $where_conditions[] = "c.id = ?";
    $params[] = $classe_filter;
}

if ($trimestre_filter) {
    $where_conditions[] = "ss.trimestre = ?";
    $params[] = $trimestre_filter;
}

if ($search) {
    $where_conditions[] = "(e.nom LIKE ? OR e.prenom LIKE ? OR e.numero_matricule LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

$where_clause = implode(' AND ', $where_conditions);

// Récupérer les élèves avec leur suivi scolaire
try {
    $eleves = $database->query(
        "SELECT e.*, c.nom as classe_nom, c.niveau,
                ss.trimestre, ss.moyenne_generale, ss.rang_classe, ss.effectif_classe,
                ss.appreciation, ss.decision_conseil, ss.date_conseil,
                (SELECT COUNT(*) FROM paiements p WHERE p.eleve_id = e.id AND p.type_paiement = 'mensualite' AND p.status = 'en_attente') as paiements_en_retard,
                (SELECT COUNT(*) FROM sanctions s WHERE s.eleve_id = e.id AND s.status = 'active') as sanctions_actives
         FROM eleves e
         LEFT JOIN inscriptions i ON e.id = i.eleve_id
         LEFT JOIN classes c ON i.classe_id = c.id
         LEFT JOIN suivi_scolaire ss ON e.id = ss.eleve_id AND ss.annee_scolaire_id = ?
         WHERE $where_clause
         ORDER BY e.nom, e.prenom
         LIMIT $per_page OFFSET $offset",
        array_merge([$current_year['id'] ?? 0], $params)
    )->fetchAll();
} catch (Exception $e) {
    $eleves = [];
}

// Récupérer les classes pour le filtre
try {
    $classes = $database->query(
        "SELECT DISTINCT c.id, c.nom, c.niveau 
         FROM classes c
         JOIN inscriptions i ON c.id = i.classe_id
         WHERE i.annee_scolaire_id = ?
         ORDER BY c.niveau, c.nom",
        [$current_year['id'] ?? 0]
    )->fetchAll();
} catch (Exception $e) {
    $classes = [];
}

// Statistiques du suivi
$stats = [];

// Moyenne générale par classe
try {
    $moyennes_classes = $database->query(
        "SELECT c.nom as classe_nom, 
                AVG(ss.moyenne_generale) as moyenne_classe,
                COUNT(ss.id) as nombre_eleves
         FROM classes c
         LEFT JOIN inscriptions i ON c.id = i.classe_id
         LEFT JOIN suivi_scolaire ss ON i.eleve_id = ss.eleve_id AND ss.annee_scolaire_id = ?
         WHERE i.annee_scolaire_id = ? AND ss.moyenne_generale IS NOT NULL
         GROUP BY c.id, c.nom
         ORDER BY c.niveau, c.nom",
        [$current_year['id'] ?? 0, $current_year['id'] ?? 0]
    )->fetchAll();
} catch (Exception $e) {
    $moyennes_classes = [];
}

// Élèves en difficulté
try {
    $eleves_difficulte = $database->query(
        "SELECT e.*, c.nom as classe_nom, ss.moyenne_generale, ss.decision_conseil
         FROM eleves e
         LEFT JOIN inscriptions i ON e.id = i.eleve_id
         LEFT JOIN classes c ON i.classe_id = c.id
         LEFT JOIN suivi_scolaire ss ON e.id = ss.eleve_id AND ss.annee_scolaire_id = ?
         WHERE e.status = 'actif' AND i.annee_scolaire_id = ?
         AND (ss.moyenne_generale < 10 OR ss.decision_conseil IN ('redouble', 'exclu'))
         ORDER BY ss.moyenne_generale ASC
         LIMIT 10",
        [$current_year['id'] ?? 0, $current_year['id'] ?? 0]
    )->fetchAll();
} catch (Exception $e) {
    $eleves_difficulte = [];
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
                        <li class="breadcrumb-item active">Suivi Scolaire</li>
                    </ol>
                </div>
                <h4 class="page-title">
                    <i class="mdi mdi-account-multiple-check me-2"></i>
                    Suivi Scolaire des Élèves
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
                                   placeholder="Nom, prénom ou matricule...">
                        </div>
                        <div class="col-md-3">
                            <label for="classe_filter" class="form-label">Classe</label>
                            <select class="form-select" id="classe_filter" name="classe_filter">
                                <option value="">Toutes les classes</option>
                                <?php foreach ($classes as $classe): ?>
                                    <option value="<?php echo $classe['id']; ?>" <?php echo $classe_filter == $classe['id'] ? 'selected' : ''; ?>>
                                        <?php echo $classe['nom']; ?> (<?php echo $classe['niveau']; ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label for="trimestre_filter" class="form-label">Trimestre</label>
                            <select class="form-select" id="trimestre_filter" name="trimestre_filter">
                                <option value="">Tous les trimestres</option>
                                <option value="1er_trimestre" <?php echo $trimestre_filter === '1er_trimestre' ? 'selected' : ''; ?>>1er Trimestre</option>
                                <option value="2eme_trimestre" <?php echo $trimestre_filter === '2eme_trimestre' ? 'selected' : ''; ?>>2ème Trimestre</option>
                                <option value="3eme_trimestre" <?php echo $trimestre_filter === '3eme_trimestre' ? 'selected' : ''; ?>>3ème Trimestre</option>
                                <option value="annuel" <?php echo $trimestre_filter === 'annuel' ? 'selected' : ''; ?>>Annuel</option>
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
        <!-- Statistiques par classe -->
        <div class="col-lg-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="header-title">
                        <i class="mdi mdi-chart-line me-2"></i>
                        Moyennes par Classe
                    </h5>
                </div>
                <div class="card-body">
                    <?php if (!empty($moyennes_classes)): ?>
                        <div class="list-group list-group-flush">
                            <?php foreach ($moyennes_classes as $classe): ?>
                                <div class="list-group-item d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="mb-0"><?php echo $classe['classe_nom']; ?></h6>
                                        <small class="text-muted"><?php echo $classe['nombre_eleves']; ?> élève(s)</small>
                                    </div>
                                    <div class="text-end">
                                        <h5 class="mb-0 text-primary">
                                            <?php echo number_format($classe['moyenne_classe'], 2); ?>
                                        </h5>
                                        <small class="text-muted">/20</small>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-3">
                            <i class="mdi mdi-information-outline text-muted" style="font-size: 32px;"></i>
                            <p class="text-muted mt-2">Aucune donnée disponible</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Élèves en difficulté -->
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header">
                    <h5 class="header-title">
                        <i class="mdi mdi-alert-circle me-2"></i>
                        Élèves en Difficulté
                    </h5>
                </div>
                <div class="card-body">
                    <?php if (!empty($eleves_difficulte)): ?>
                        <div class="table-responsive">
                            <table class="table table-centered table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>Élève</th>
                                        <th>Classe</th>
                                        <th>Moyenne</th>
                                        <th>Décision</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($eleves_difficulte as $eleve): ?>
                                        <tr>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <div class="avatar-sm me-3">
                                                        <?php if ($eleve['photo']): ?>
                                                            <img src="../../../../uploads/photos/<?php echo $eleve['photo']; ?>" 
                                                                 class="rounded-circle" width="40" height="40" alt="Photo">
                                                        <?php else: ?>
                                                            <span class="avatar-title bg-soft-primary rounded-circle">
                                                                <?php echo strtoupper(substr($eleve['prenom'], 0, 1)); ?>
                                                            </span>
                                                        <?php endif; ?>
                                                    </div>
                                                    <div>
                                                        <h6 class="mb-0"><?php echo $eleve['prenom'] . ' ' . $eleve['nom']; ?></h6>
                                                        <small class="text-muted"><?php echo $eleve['numero_matricule']; ?></small>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="badge bg-light text-dark">
                                                    <?php echo $eleve['classe_nom']; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php if ($eleve['moyenne_generale']): ?>
                                                    <span class="badge <?php echo $eleve['moyenne_generale'] >= 10 ? 'bg-success' : 'bg-danger'; ?>">
                                                        <?php echo $eleve['moyenne_generale']; ?>/20
                                                    </span>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary">Non évalué</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($eleve['decision_conseil']): ?>
                                                    <?php
                                                    $decision_class = '';
                                                    switch ($eleve['decision_conseil']) {
                                                        case 'admis':
                                                            $decision_class = 'bg-success';
                                                            break;
                                                        case 'admis_avec_reserves':
                                                            $decision_class = 'bg-warning';
                                                            break;
                                                        case 'redouble':
                                                            $decision_class = 'bg-danger';
                                                            break;
                                                        case 'exclu':
                                                            $decision_class = 'bg-dark';
                                                            break;
                                                        default:
                                                            $decision_class = 'bg-secondary';
                                                    }
                                                    ?>
                                                    <span class="badge <?php echo $decision_class; ?>">
                                                        <?php echo ucfirst(str_replace('_', ' ', $eleve['decision_conseil'])); ?>
                                                    </span>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary">Non décidé</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <a href="view.php?id=<?php echo $eleve['id']; ?>" 
                                                   class="btn btn-sm btn-outline-primary">
                                                    <i class="mdi mdi-eye"></i>
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-3">
                            <i class="mdi mdi-check-circle text-success" style="font-size: 32px;"></i>
                            <p class="text-success mt-2">Aucun élève en difficulté !</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Liste des élèves avec suivi -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h4 class="header-title">
                        <i class="mdi mdi-format-list-bulleted me-2"></i>
                        Suivi Scolaire des Élèves
                    </h4>
                </div>
                <div class="card-body">
                    <?php if (!empty($eleves)): ?>
                        <div class="table-responsive">
                            <table class="table table-centered table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>Élève</th>
                                        <th>Classe</th>
                                        <th>Trimestre</th>
                                        <th>Moyenne</th>
                                        <th>Rang</th>
                                        <th>Décision</th>
                                        <th>Paiements</th>
                                        <th>Sanctions</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($eleves as $eleve): ?>
                                        <tr>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <div class="avatar-sm me-3">
                                                        <?php if ($eleve['photo']): ?>
                                                            <img src="../../../../uploads/photos/<?php echo $eleve['photo']; ?>" 
                                                                 class="rounded-circle" width="40" height="40" alt="Photo">
                                                        <?php else: ?>
                                                            <span class="avatar-title bg-soft-primary rounded-circle">
                                                                <?php echo strtoupper(substr($eleve['prenom'], 0, 1)); ?>
                                                            </span>
                                                        <?php endif; ?>
                                                    </div>
                                                    <div>
                                                        <h6 class="mb-0"><?php echo $eleve['prenom'] . ' ' . $eleve['nom']; ?></h6>
                                                        <small class="text-muted"><?php echo $eleve['numero_matricule']; ?></small>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="badge bg-light text-dark">
                                                    <?php echo $eleve['classe_nom'] ?? 'Non assigné'; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php if ($eleve['trimestre']): ?>
                                                    <span class="badge bg-info">
                                                        <?php echo ucfirst(str_replace('_', ' ', $eleve['trimestre'])); ?>
                                                    </span>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary">Non défini</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($eleve['moyenne_generale']): ?>
                                                    <span class="badge <?php echo $eleve['moyenne_generale'] >= 10 ? 'bg-success' : 'bg-danger'; ?>">
                                                        <?php echo $eleve['moyenne_generale']; ?>/20
                                                    </span>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary">Non évalué</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($eleve['rang_classe'] && $eleve['effectif_classe']): ?>
                                                    <span class="text-muted">
                                                        <?php echo $eleve['rang_classe']; ?>/<?php echo $eleve['effectif_classe']; ?>
                                                    </span>
                                                <?php else: ?>
                                                    <span class="text-muted">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($eleve['decision_conseil']): ?>
                                                    <?php
                                                    $decision_class = '';
                                                    switch ($eleve['decision_conseil']) {
                                                        case 'admis':
                                                            $decision_class = 'bg-success';
                                                            break;
                                                        case 'admis_avec_reserves':
                                                            $decision_class = 'bg-warning';
                                                            break;
                                                        case 'redouble':
                                                            $decision_class = 'bg-danger';
                                                            break;
                                                        case 'exclu':
                                                            $decision_class = 'bg-dark';
                                                            break;
                                                        default:
                                                            $decision_class = 'bg-secondary';
                                                    }
                                                    ?>
                                                    <span class="badge <?php echo $decision_class; ?>">
                                                        <?php echo ucfirst(str_replace('_', ' ', $eleve['decision_conseil'])); ?>
                                                    </span>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary">Non décidé</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($eleve['paiements_en_retard'] > 0): ?>
                                                    <span class="badge bg-danger">
                                                        <?php echo $eleve['paiements_en_retard']; ?> en retard
                                                    </span>
                                                <?php else: ?>
                                                    <span class="badge bg-success">À jour</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($eleve['sanctions_actives'] > 0): ?>
                                                    <span class="badge bg-warning">
                                                        <?php echo $eleve['sanctions_actives']; ?> active(s)
                                                    </span>
                                                <?php else: ?>
                                                    <span class="badge bg-success">Aucune</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <div class="btn-group" role="group">
                                                    <a href="view.php?id=<?php echo $eleve['id']; ?>" 
                                                       class="btn btn-sm btn-outline-primary">
                                                        <i class="mdi mdi-eye"></i>
                                                    </a>
                                                    <a href="edit.php?id=<?php echo $eleve['id']; ?>" 
                                                       class="btn btn-sm btn-outline-warning">
                                                        <i class="mdi mdi-pencil"></i>
                                                    </a>
                                                    <a href="report.php?id=<?php echo $eleve['id']; ?>" 
                                                       class="btn btn-sm btn-outline-info">
                                                        <i class="mdi mdi-file-document"></i>
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
                            <p class="text-muted mt-2">Aucun élève trouvé</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../../../../includes/footer.php'; ?>
