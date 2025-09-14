<?php
/**
 * Module d'évaluations - Gestion des évaluations par enseignant
 * Application de gestion scolaire - République Démocratique du Congo
 */

require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../includes/functions.php';
require_once '../../includes/permissions-pages.php';

// Vérifier l'authentification et les permissions
requireLogin();
requirePagePermissionFromDB('evaluations', 'teacher/index', 'read', '../../dashboard.php');

// Récupérer l'ID de l'enseignant
$teacher_id = (int)($_GET['id'] ?? 0);
if (!$teacher_id) {
    showMessage('error', 'ID enseignant manquant.');
    redirectTo('../index.php');
}

// Récupérer les informations de l'enseignant
$teacher = $database->query(
    "SELECT p.*, u.username, u.email, u.role 
     FROM personnel p 
     LEFT JOIN users u ON p.user_id = u.id 
     WHERE p.id = ? AND p.fonction IN ('enseignant', 'directeur', 'sous_directeur')",
    [$teacher_id]
)->fetch();

if (!$teacher) {
    showMessage('error', 'Enseignant non trouvé.');
    redirectTo('../index.php');
}

$page_title = 'Évaluations de ' . $teacher['nom'] . ' ' . $teacher['prenom'];

// Obtenir l'année scolaire actuelle
$current_year = getCurrentAcademicYear();

// Paramètres de recherche et filtrage
$search = sanitizeInput($_GET['search'] ?? '');
$classe_filter = (int)($_GET['classe'] ?? 0);
$matiere_filter = (int)($_GET['matiere'] ?? 0);
$type_filter = sanitizeInput($_GET['type'] ?? '');
$periode_filter = sanitizeInput($_GET['periode'] ?? '');
$status_filter = sanitizeInput($_GET['status'] ?? '');

// Construction de la requête pour les évaluations de l'enseignant
$sql = "SELECT e.*, 
               m.nom as matiere_nom, m.coefficient as matiere_coefficient,
               c.nom as classe_nom, c.niveau, c.section,
               COUNT(n.id) as nb_notes,
               AVG(n.note) as moyenne_evaluation,
               COUNT(el.id) as nb_eleves_classe
        FROM evaluations e
        JOIN matieres m ON e.matiere_id = m.id
        JOIN classes c ON e.classe_id = c.id
        LEFT JOIN notes n ON e.id = n.evaluation_id
        LEFT JOIN eleves el ON c.id = el.classe_id AND el.status = 'inscrit'
        WHERE e.enseignant_id = ? AND e.annee_scolaire_id = ?";

$params = [$teacher_id, $current_year['id']];

if (!empty($search)) {
    $sql .= " AND (e.nom LIKE ? OR e.description LIKE ? OR m.nom LIKE ? OR c.nom LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

if ($classe_filter) {
    $sql .= " AND e.classe_id = ?";
    $params[] = $classe_filter;
}

if ($matiere_filter) {
    $sql .= " AND e.matiere_id = ?";
    $params[] = $matiere_filter;
}

if ($type_filter) {
    $sql .= " AND e.type = ?";
    $params[] = $type_filter;
}

if ($periode_filter) {
    $sql .= " AND e.periode = ?";
    $params[] = $periode_filter;
}

if ($status_filter) {
    $sql .= " AND e.status = ?";
    $params[] = $status_filter;
}

$sql .= " GROUP BY e.id ORDER BY e.date_evaluation DESC, e.created_at DESC";

$evaluations = $database->query($sql, $params)->fetchAll();

// Récupérer les classes enseignées par cet enseignant
$classes_enseignees = $database->query(
    "SELECT DISTINCT c.* 
     FROM classes c
     JOIN evaluations e ON c.id = e.classe_id
     WHERE e.enseignant_id = ? AND e.annee_scolaire_id = ?
     ORDER BY c.niveau, c.nom",
    [$teacher_id, $current_year['id']]
)->fetchAll();

// Récupérer les matières enseignées par cet enseignant
$matieres_enseignees = $database->query(
    "SELECT DISTINCT m.* 
     FROM matieres m
     JOIN evaluations e ON m.id = e.matiere_id
     WHERE e.enseignant_id = ? AND e.annee_scolaire_id = ?
     ORDER BY m.nom",
    [$teacher_id, $current_year['id']]
)->fetchAll();

// Statistiques de l'enseignant
$stats = $database->query(
    "SELECT 
        COUNT(*) as total_evaluations,
        COUNT(CASE WHEN status = 'programmee' THEN 1 END) as evaluations_programmees,
        COUNT(CASE WHEN status = 'en_cours' THEN 1 END) as evaluations_en_cours,
        COUNT(CASE WHEN status = 'terminee' THEN 1 END) as evaluations_terminees,
        COUNT(CASE WHEN status = 'annulee' THEN 1 END) as evaluations_annulees,
        COUNT(DISTINCT classe_id) as nb_classes,
        COUNT(DISTINCT matiere_id) as nb_matieres
     FROM evaluations 
     WHERE enseignant_id = ? AND annee_scolaire_id = ?",
    [$teacher_id, $current_year['id']]
)->fetch();

include '../../includes/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">
        <i class="fas fa-chalkboard-teacher me-2"></i>
        Évaluations de <?php echo htmlspecialchars($teacher['nom'] . ' ' . $teacher['prenom']); ?>
    </h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <a href="../index.php" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-left me-1"></i>
            Retour aux évaluations
        </a>
    </div>
</div>

<!-- Informations de l'enseignant -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <h5 class="card-title mb-1">
                            <i class="fas fa-user-tie me-2"></i>
                            <?php echo htmlspecialchars($teacher['nom'] . ' ' . $teacher['prenom']); ?>
                        </h5>
                        <p class="card-text text-muted mb-2">
                            <i class="fas fa-id-badge me-1"></i>
                            Matricule: <?php echo htmlspecialchars($teacher['matricule']); ?>
                            <?php if ($teacher['specialite']): ?>
                                | <i class="fas fa-graduation-cap me-1"></i>
                                Spécialité: <?php echo htmlspecialchars($teacher['specialite']); ?>
                            <?php endif; ?>
                        </p>
                        <?php if ($teacher['email']): ?>
                            <p class="card-text text-muted mb-0">
                                <i class="fas fa-envelope me-1"></i>
                                <?php echo htmlspecialchars($teacher['email']); ?>
                            </p>
                        <?php endif; ?>
                    </div>
                    <div class="col-md-4 text-end">
                        <div class="row text-center">
                            <div class="col-3">
                                <div class="border-end">
                                    <h4 class="text-primary mb-0"><?php echo $stats['total_evaluations']; ?></h4>
                                    <small class="text-muted">Total</small>
                                </div>
                            </div>
                            <div class="col-3">
                                <div class="border-end">
                                    <h4 class="text-success mb-0"><?php echo $stats['evaluations_terminees']; ?></h4>
                                    <small class="text-muted">Terminées</small>
                                </div>
                            </div>
                            <div class="col-3">
                                <div class="border-end">
                                    <h4 class="text-warning mb-0"><?php echo $stats['evaluations_programmees']; ?></h4>
                                    <small class="text-muted">Programmées</small>
                                </div>
                            </div>
                            <div class="col-3">
                                <h4 class="text-info mb-0"><?php echo $stats['nb_classes']; ?></h4>
                                <small class="text-muted">Classes</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Filtres de recherche -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h6 class="mb-0">
                    <i class="fas fa-filter me-2"></i>
                    Filtres de recherche
                </h6>
            </div>
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <input type="hidden" name="id" value="<?php echo $teacher_id; ?>">
                    
                    <div class="col-md-3">
                        <label for="search" class="form-label">Recherche</label>
                        <input type="text" 
                               class="form-control" 
                               id="search" 
                               name="search" 
                               value="<?php echo htmlspecialchars($search); ?>"
                               placeholder="Nom, description, matière...">
                    </div>
                    
                    <div class="col-md-2">
                        <label for="classe" class="form-label">Classe</label>
                        <select class="form-select" id="classe" name="classe">
                            <option value="">Toutes les classes</option>
                            <?php foreach ($classes_enseignees as $classe): ?>
                                <option value="<?php echo $classe['id']; ?>" 
                                        <?php echo $classe_filter == $classe['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($classe['nom']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="col-md-2">
                        <label for="matiere" class="form-label">Matière</label>
                        <select class="form-select" id="matiere" name="matiere">
                            <option value="">Toutes les matières</option>
                            <?php foreach ($matieres_enseignees as $matiere): ?>
                                <option value="<?php echo $matiere['id']; ?>" 
                                        <?php echo $matiere_filter == $matiere['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($matiere['nom']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="col-md-2">
                        <label for="type" class="form-label">Type</label>
                        <select class="form-select" id="type" name="type">
                            <option value="">Tous les types</option>
                            <option value="interrogation" <?php echo $type_filter === 'interrogation' ? 'selected' : ''; ?>>Interrogation</option>
                            <option value="devoir" <?php echo $type_filter === 'devoir' ? 'selected' : ''; ?>>Devoir</option>
                            <option value="examen" <?php echo $type_filter === 'examen' ? 'selected' : ''; ?>>Examen</option>
                            <option value="composition" <?php echo $type_filter === 'composition' ? 'selected' : ''; ?>>Composition</option>
                        </select>
                    </div>
                    
                    <div class="col-md-2">
                        <label for="periode" class="form-label">Période</label>
                        <select class="form-select" id="periode" name="periode">
                            <option value="">Toutes les périodes</option>
                            <option value="1er_trimestre" <?php echo $periode_filter === '1er_trimestre' ? 'selected' : ''; ?>>1er Trimestre</option>
                            <option value="2eme_trimestre" <?php echo $periode_filter === '2eme_trimestre' ? 'selected' : ''; ?>>2ème Trimestre</option>
                            <option value="3eme_trimestre" <?php echo $periode_filter === '3eme_trimestre' ? 'selected' : ''; ?>>3ème Trimestre</option>
                            <option value="annuelle" <?php echo $periode_filter === 'annuelle' ? 'selected' : ''; ?>>Annuelle</option>
                        </select>
                    </div>
                    
                    <div class="col-md-1">
                        <label for="status" class="form-label">Statut</label>
                        <select class="form-select" id="status" name="status">
                            <option value="">Tous</option>
                            <option value="programmee" <?php echo $status_filter === 'programmee' ? 'selected' : ''; ?>>Programmée</option>
                            <option value="en_cours" <?php echo $status_filter === 'en_cours' ? 'selected' : ''; ?>>En cours</option>
                            <option value="terminee" <?php echo $status_filter === 'terminee' ? 'selected' : ''; ?>>Terminée</option>
                            <option value="annulee" <?php echo $status_filter === 'annulee' ? 'selected' : ''; ?>>Annulée</option>
                        </select>
                    </div>
                    
                    <div class="col-12">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-search me-1"></i>
                            Filtrer
                        </button>
                        <a href="?id=<?php echo $teacher_id; ?>" class="btn btn-outline-secondary">
                            <i class="fas fa-times me-1"></i>
                            Effacer
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Liste des évaluations -->
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h6 class="mb-0">
                    <i class="fas fa-list me-2"></i>
                    Évaluations (<?php echo count($evaluations); ?>)
                </h6>
                <?php if (checkPagePermission('evaluations')): ?>
                    <a href="evaluations/add.php?enseignant_id=<?php echo $teacher_id; ?>" class="btn btn-sm btn-primary">
                        <i class="fas fa-plus me-1"></i>
                        Nouvelle évaluation
                    </a>
                <?php endif; ?>
            </div>
            <div class="card-body p-0">
                <?php if (empty($evaluations)): ?>
                    <div class="text-center py-5">
                        <i class="fas fa-clipboard-list fa-3x text-muted mb-3"></i>
                        <h5 class="text-muted">Aucune évaluation trouvée</h5>
                        <p class="text-muted">Cet enseignant n'a pas encore d'évaluations pour cette année scolaire.</p>
                        <?php if (checkPagePermission('evaluations')): ?>
                            <a href="evaluations/add.php?enseignant_id=<?php echo $teacher_id; ?>" class="btn btn-primary">
                                <i class="fas fa-plus me-1"></i>
                                Créer la première évaluation
                            </a>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Évaluation</th>
                                    <th>Classe</th>
                                    <th>Matière</th>
                                    <th>Type</th>
                                    <th>Date</th>
                                    <th>Période</th>
                                    <th>Notes</th>
                                    <th>Moyenne</th>
                                    <th>Statut</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($evaluations as $evaluation): ?>
                                    <tr>
                                        <td>
                                            <div>
                                                <strong><?php echo htmlspecialchars($evaluation['nom']); ?></strong>
                                                <?php if ($evaluation['description']): ?>
                                                    <br><small class="text-muted"><?php echo htmlspecialchars(substr($evaluation['description'], 0, 50)) . (strlen($evaluation['description']) > 50 ? '...' : ''); ?></small>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="badge bg-info">
                                                <?php echo htmlspecialchars($evaluation['classe_nom']); ?>
                                            </span>
                                            <br><small class="text-muted"><?php echo htmlspecialchars($evaluation['niveau']); ?></small>
                                        </td>
                                        <td>
                                            <?php echo htmlspecialchars($evaluation['matiere_nom']); ?>
                                            <br><small class="text-muted">Coef. <?php echo $evaluation['matiere_coefficient']; ?></small>
                                        </td>
                                        <td>
                                            <span class="badge bg-secondary">
                                                <?php echo ucfirst($evaluation['type']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php echo formatDate($evaluation['date_evaluation']); ?>
                                            <?php if ($evaluation['heure_debut']): ?>
                                                <br><small class="text-muted"><?php echo date('H:i', strtotime($evaluation['heure_debut'])); ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="badge bg-primary">
                                                <?php echo ucfirst(str_replace('_', ' ', $evaluation['periode'])); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge bg-success">
                                                <?php echo $evaluation['nb_notes']; ?>/<?php echo $evaluation['nb_eleves_classe']; ?>
                                            </span>
                                            <br><small class="text-muted">élèves notés</small>
                                        </td>
                                        <td>
                                            <?php if ($evaluation['moyenne_evaluation']): ?>
                                                <strong><?php echo number_format($evaluation['moyenne_evaluation'], 2); ?></strong>/20
                                                <br><small class="text-muted">Coef. <?php echo $evaluation['coefficient']; ?></small>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php
                                            $status_class = [
                                                'programmee' => 'warning',
                                                'en_cours' => 'info',
                                                'terminee' => 'success',
                                                'annulee' => 'danger'
                                            ];
                                            $status_text = [
                                                'programmee' => 'Programmée',
                                                'en_cours' => 'En cours',
                                                'terminee' => 'Terminée',
                                                'annulee' => 'Annulée'
                                            ];
                                            ?>
                                            <span class="badge bg-<?php echo $status_class[$evaluation['status']]; ?>">
                                                <?php echo $status_text[$evaluation['status']]; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm" role="group">
                                                <a href="evaluations/view.php?id=<?php echo $evaluation['id']; ?>" 
                                                   class="btn btn-outline-primary" 
                                                   title="Voir les détails">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                <?php if (checkPagePermission('evaluations')): ?>
                                                    <a href="evaluations/edit.php?id=<?php echo $evaluation['id']; ?>" 
                                                       class="btn btn-outline-secondary" 
                                                       title="Modifier">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                <?php endif; ?>
                                                <?php if ($evaluation['status'] === 'terminee'): ?>
                                                    <a href="notes/evaluation.php?id=<?php echo $evaluation['id']; ?>" 
                                                       class="btn btn-outline-success" 
                                                       title="Voir les notes">
                                                        <i class="fas fa-chart-line"></i>
                                                    </a>
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
    </div>
</div>

<?php include '../../includes/footer.php'; ?>
