<?php
/**
 * Module de gestion académique - Emplois du temps
 * Application de gestion scolaire - République Démocratique du Congo
 */

require_once '../../../config/config.php';
require_once '../../../config/database.php';
require_once '../../../includes/functions.php';

// Vérifier l'authentification et les permissions
requireLogin();
if (!checkPermission('academic') && !checkPermission('academic_view')) {
    showMessage('error', 'Accès refusé à ce module.');
    redirectTo('../index.php');
}

$page_title = 'Emplois du temps';

// Obtenir l'année scolaire actuelle
$current_year = getCurrentAcademicYear();

// Paramètres de filtrage
$classe_filter = (int)($_GET['classe'] ?? 0);
$enseignant_filter = (int)($_GET['enseignant'] ?? 0);

// Récupérer les classes pour le filtre
$classes = $database->query(
    "SELECT id, nom, niveau FROM classes WHERE annee_scolaire_id = ? ORDER BY niveau, nom",
    [$current_year['id'] ?? 0]
)->fetchAll();

// Récupérer les enseignants pour le filtre
$enseignants = $database->query(
    "SELECT id, nom, prenom FROM personnel WHERE fonction = 'enseignant' AND status = 'actif' ORDER BY nom, prenom"
)->fetchAll();

// Construction de la requête pour les emplois du temps
$sql = "SELECT et.*, 
               c.nom as classe_nom, c.niveau,
               m.nom as matiere_nom, m.coefficient,
               p.nom as enseignant_nom, p.prenom as enseignant_prenom
        FROM emplois_temps et
        JOIN classes c ON et.classe_id = c.id
        JOIN matieres m ON et.matiere_id = m.id
        LEFT JOIN personnel p ON et.enseignant_id = p.id
        WHERE et.annee_scolaire_id = ?";

$params = [$current_year['id'] ?? 0];

if ($classe_filter) {
    $sql .= " AND et.classe_id = ?";
    $params[] = $classe_filter;
}

if ($enseignant_filter) {
    $sql .= " AND et.enseignant_id = ?";
    $params[] = $enseignant_filter;
}

$sql .= " ORDER BY c.nom, 
          CASE et.jour_semaine 
              WHEN 'lundi' THEN 1 
              WHEN 'mardi' THEN 2 
              WHEN 'mercredi' THEN 3 
              WHEN 'jeudi' THEN 4 
              WHEN 'vendredi' THEN 5 
              WHEN 'samedi' THEN 6 
              ELSE 7 
          END, et.heure_debut";

$emplois_temps = $database->query($sql, $params)->fetchAll();

// Statistiques
$stats = [];
$stats['total_cours'] = count($emplois_temps);
$stats['classes_avec_emploi'] = count(array_unique(array_column($emplois_temps, 'classe_id')));
$stats['enseignants_assignes'] = count(array_unique(array_filter(array_column($emplois_temps, 'enseignant_id'))));
$stats['cours_sans_enseignant'] = count(array_filter($emplois_temps, fn($e) => !$e['enseignant_id']));

// Organiser les emplois du temps par classe
$emplois_par_classe = [];
foreach ($emplois_temps as $emploi) {
    $classe_id = $emploi['classe_id'];
    if (!isset($emplois_par_classe[$classe_id])) {
        $emplois_par_classe[$classe_id] = [
            'classe' => $emploi,
            'cours' => []
        ];
    }
    $emplois_par_classe[$classe_id]['cours'][] = $emploi;
}

include '../../../includes/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">
        <i class="fas fa-calendar-alt me-2"></i>
        Emplois du temps
    </h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <div class="btn-group me-2">
            <a href="../index.php" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-1"></i>
                Retour
            </a>
        </div>
        <?php if (checkPermission('academic')): ?>
            <div class="btn-group me-2">
                <button type="button" class="btn btn-primary dropdown-toggle" data-bs-toggle="dropdown">
                    <i class="fas fa-plus me-1"></i>
                    Nouveau
                </button>
                <ul class="dropdown-menu">
                    <li><a class="dropdown-item" href="add.php">
                        <i class="fas fa-calendar-plus me-2"></i>Créer un cours
                    </a></li>
                    <li><a class="dropdown-item" href="generate.php">
                        <i class="fas fa-magic me-2"></i>Générer emploi du temps
                    </a></li>
                </ul>
            </div>
        <?php endif; ?>
        <div class="btn-group">
            <button type="button" class="btn btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">
                <i class="fas fa-download me-1"></i>
                Exporter
            </button>
            <ul class="dropdown-menu">
                <li><a class="dropdown-item" href="export.php?format=pdf">
                    <i class="fas fa-file-pdf me-2"></i>PDF
                </a></li>
                <li><a class="dropdown-item" href="export.php?format=excel">
                    <i class="fas fa-file-excel me-2"></i>Excel
                </a></li>
            </ul>
        </div>
    </div>
</div>

<!-- Statistiques rapides -->
<div class="row mb-4">
    <div class="col-md-3">
        <div class="card text-white bg-primary">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h4><?php echo $stats['total_cours']; ?></h4>
                        <p class="mb-0">Total cours</p>
                    </div>
                    <div class="align-self-center">
                        <i class="fas fa-clock fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-white bg-success">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h4><?php echo $stats['classes_avec_emploi']; ?></h4>
                        <p class="mb-0">Classes configurées</p>
                    </div>
                    <div class="align-self-center">
                        <i class="fas fa-school fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-white bg-info">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h4><?php echo $stats['enseignants_assignes']; ?></h4>
                        <p class="mb-0">Enseignants assignés</p>
                    </div>
                    <div class="align-self-center">
                        <i class="fas fa-chalkboard-teacher fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-white bg-warning">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h4><?php echo $stats['cours_sans_enseignant']; ?></h4>
                        <p class="mb-0">Cours sans enseignant</p>
                    </div>
                    <div class="align-self-center">
                        <i class="fas fa-exclamation-triangle fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Filtres -->
<div class="card mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-md-5">
                <label for="classe" class="form-label">Classe</label>
                <select class="form-select" id="classe" name="classe">
                    <option value="">Toutes les classes</option>
                    <?php foreach ($classes as $classe): ?>
                        <option value="<?php echo $classe['id']; ?>" 
                                <?php echo $classe_filter == $classe['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($classe['nom']); ?> 
                            (<?php echo ucfirst($classe['niveau']); ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-5">
                <label for="enseignant" class="form-label">Enseignant</label>
                <select class="form-select" id="enseignant" name="enseignant">
                    <option value="">Tous les enseignants</option>
                    <?php foreach ($enseignants as $enseignant): ?>
                        <option value="<?php echo $enseignant['id']; ?>" 
                                <?php echo $enseignant_filter == $enseignant['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($enseignant['nom'] . ' ' . $enseignant['prenom']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">&nbsp;</label>
                <div class="d-grid">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search me-1"></i>
                        Filtrer
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Emplois du temps -->
<div class="card">
    <div class="card-header">
        <h5 class="mb-0">
            <i class="fas fa-calendar-week me-2"></i>
            Emplois du temps
            <?php if ($classe_filter || $enseignant_filter): ?>
                <small class="text-muted">
                    (<?php echo count($emplois_temps); ?> cours)
                </small>
            <?php endif; ?>
        </h5>
    </div>
    <div class="card-body">
        <?php if (!empty($emplois_par_classe)): ?>
            <?php foreach ($emplois_par_classe as $classe_data): ?>
                <div class="mb-4">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h6 class="mb-0">
                            <span class="badge bg-<?php 
                                echo $classe_data['classe']['niveau'] === 'maternelle' ? 'warning' : 
                                    ($classe_data['classe']['niveau'] === 'primaire' ? 'success' : 'primary'); 
                            ?> me-2">
                                <?php echo ucfirst($classe_data['classe']['niveau']); ?>
                            </span>
                            <?php echo htmlspecialchars($classe_data['classe']['classe_nom']); ?>
                        </h6>
                        <div class="btn-group btn-group-sm">
                            <a href="class.php?id=<?php echo $classe_data['classe']['classe_id']; ?>" 
                               class="btn btn-outline-primary">
                                <i class="fas fa-eye me-1"></i>
                                Voir détail
                            </a>
                            <?php if (checkPermission('academic')): ?>
                                <a href="edit-class.php?id=<?php echo $classe_data['classe']['classe_id']; ?>" 
                                   class="btn btn-outline-warning">
                                    <i class="fas fa-edit me-1"></i>
                                    Modifier
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered">
                            <thead class="table-dark">
                                <tr>
                                    <th style="width: 100px;">Heure</th>
                                    <th>Lundi</th>
                                    <th>Mardi</th>
                                    <th>Mercredi</th>
                                    <th>Jeudi</th>
                                    <th>Vendredi</th>
                                    <th>Samedi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                // Organiser les cours par créneaux horaires
                                $creneaux = [];
                                foreach ($classe_data['cours'] as $cours) {
                                    $heure = substr($cours['heure_debut'], 0, 5) . '-' . substr($cours['heure_fin'], 0, 5);
                                    if (!isset($creneaux[$heure])) {
                                        $creneaux[$heure] = [];
                                    }
                                    $creneaux[$heure][$cours['jour_semaine']] = $cours;
                                }
                                
                                $jours = ['lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi'];
                                
                                foreach ($creneaux as $heure => $cours_jour):
                                ?>
                                    <tr>
                                        <td class="fw-bold text-center bg-light"><?php echo $heure; ?></td>
                                        <?php foreach ($jours as $jour): ?>
                                            <td>
                                                <?php if (isset($cours_jour[$jour])): ?>
                                                    <?php $cours = $cours_jour[$jour]; ?>
                                                    <div class="small">
                                                        <strong class="text-primary">
                                                            <?php echo htmlspecialchars($cours['matiere_nom']); ?>
                                                        </strong>
                                                        <?php if ($cours['enseignant_nom']): ?>
                                                            <br><span class="text-muted">
                                                                <?php echo htmlspecialchars($cours['enseignant_nom'] . ' ' . substr($cours['enseignant_prenom'], 0, 1) . '.'); ?>
                                                            </span>
                                                        <?php else: ?>
                                                            <br><span class="text-danger">Non assigné</span>
                                                        <?php endif; ?>
                                                        <?php if ($cours['salle']): ?>
                                                            <br><small class="text-info">
                                                                <i class="fas fa-map-marker-alt fa-xs"></i>
                                                                <?php echo htmlspecialchars($cours['salle']); ?>
                                                            </small>
                                                        <?php endif; ?>
                                                    </div>
                                                <?php endif; ?>
                                            </td>
                                        <?php endforeach; ?>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="text-center py-5">
                <i class="fas fa-calendar-times fa-3x text-muted mb-3"></i>
                <h5 class="text-muted">Aucun emploi du temps configuré</h5>
                <p class="text-muted">
                    <?php if ($classe_filter || $enseignant_filter): ?>
                        Aucun cours trouvé avec les filtres sélectionnés.
                    <?php else: ?>
                        Aucun emploi du temps n'a encore été configuré.
                    <?php endif; ?>
                </p>
                <?php if (checkPermission('academic')): ?>
                    <div class="mt-3">
                        <a href="add.php" class="btn btn-primary me-2">
                            <i class="fas fa-plus me-1"></i>
                            Créer un cours
                        </a>
                        <a href="generate.php" class="btn btn-outline-primary">
                            <i class="fas fa-magic me-1"></i>
                            Générer automatiquement
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Actions rapides -->
<?php if (checkPermission('academic') && !empty($classes)): ?>
<div class="row mt-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-bolt me-2"></i>
                    Actions rapides
                </h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-3 mb-2">
                        <div class="d-grid">
                            <a href="add.php" class="btn btn-outline-primary">
                                <i class="fas fa-plus me-2"></i>
                                Nouveau cours
                            </a>
                        </div>
                    </div>
                    <div class="col-md-3 mb-2">
                        <div class="d-grid">
                            <a href="generate.php" class="btn btn-outline-success">
                                <i class="fas fa-magic me-2"></i>
                                Génération automatique
                            </a>
                        </div>
                    </div>
                    <div class="col-md-3 mb-2">
                        <div class="d-grid">
                            <a href="conflicts.php" class="btn btn-outline-warning">
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                Vérifier conflits
                            </a>
                        </div>
                    </div>
                    <div class="col-md-3 mb-2">
                        <div class="d-grid">
                            <a href="export.php?format=pdf" class="btn btn-outline-secondary">
                                <i class="fas fa-download me-2"></i>
                                Exporter tout
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<?php include '../../../includes/footer.php'; ?>
