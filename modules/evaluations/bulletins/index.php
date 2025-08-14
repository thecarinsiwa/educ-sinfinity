<?php
/**
 * Module d'évaluations et notes - Gestion des bulletins
 * Application de gestion scolaire - République Démocratique du Congo
 */

require_once '../../../config/config.php';
require_once '../../../config/database.php';
require_once '../../../includes/functions.php';

// Vérifier l'authentification et les permissions
requireLogin();
if (!checkPermission('evaluations') && !checkPermission('evaluations_view')) {
    showMessage('error', 'Accès refusé à ce module.');
    redirectTo('../index.php');
}

$page_title = 'Gestion des Bulletins';

// Obtenir l'année scolaire actuelle
$current_year = getCurrentAcademicYear();

// Paramètres de filtrage
$classe_filter = (int)($_GET['classe'] ?? 0);
$periode_filter = sanitizeInput($_GET['periode'] ?? '');

// Récupérer les classes
$classes = $database->query(
    "SELECT id, nom, niveau FROM classes WHERE annee_scolaire_id = ? ORDER BY niveau, nom",
    [$current_year['id'] ?? 0]
)->fetchAll();

// Statistiques générales
$stats = [];

// Nombre total d'élèves
$stmt = $database->query(
    "SELECT COUNT(*) as total FROM inscriptions WHERE status = 'inscrit' AND annee_scolaire_id = ?",
    [$current_year['id'] ?? 0]
);
$stats['total_eleves'] = $stmt->fetch()['total'];

// Nombre d'évaluations par période
$evaluations_par_periode = $database->query(
    "SELECT periode, COUNT(*) as nombre 
     FROM evaluations 
     WHERE annee_scolaire_id = ? 
     GROUP BY periode 
     ORDER BY periode",
    [$current_year['id'] ?? 0]
)->fetchAll();

// Moyennes par classe et période
$moyennes_classes = [];
if ($classe_filter && $periode_filter) {
    $sql = "SELECT e.id, e.nom, e.prenom, e.numero_matricule,
                   AVG(n.note * ev.coefficient * m.coefficient) / AVG(ev.coefficient * m.coefficient) as moyenne_generale,
                   COUNT(DISTINCT ev.id) as nb_evaluations
            FROM eleves e
            JOIN inscriptions i ON e.id = i.eleve_id
            LEFT JOIN notes n ON e.id = n.eleve_id
            LEFT JOIN evaluations ev ON n.evaluation_id = ev.id AND ev.periode = ?
            LEFT JOIN matieres m ON ev.matiere_id = m.id
            WHERE i.classe_id = ? AND i.status = 'inscrit' AND i.annee_scolaire_id = ?
            GROUP BY e.id
            ORDER BY moyenne_generale DESC, e.nom, e.prenom";
    
    $moyennes_classes = $database->query($sql, [$periode_filter, $classe_filter, $current_year['id'] ?? 0])->fetchAll();
}

include '../../../includes/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">
        <i class="fas fa-file-alt me-2"></i>
        Gestion des Bulletins
    </h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <div class="btn-group me-2">
            <a href="../index.php" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-1"></i>
                Retour
            </a>
        </div>
        <?php if (checkPermission('evaluations')): ?>
            <div class="btn-group">
                <button type="button" class="btn btn-primary dropdown-toggle" data-bs-toggle="dropdown">
                    <i class="fas fa-file-alt me-1"></i>
                    Générer bulletins
                </button>
                <ul class="dropdown-menu">
                    <li><a class="dropdown-item" href="generate.php">
                        <i class="fas fa-magic me-2"></i>Assistant de génération
                    </a></li>
                    <li><a class="dropdown-item" href="batch-generate.php">
                        <i class="fas fa-layer-group me-2"></i>Génération en lot
                    </a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item" href="templates.php">
                        <i class="fas fa-file-code me-2"></i>Modèles de bulletins
                    </a></li>
                </ul>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Statistiques rapides -->
<div class="row mb-4">
    <div class="col-md-3">
        <div class="card text-white bg-primary">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h4><?php echo $stats['total_eleves']; ?></h4>
                        <p class="mb-0">Élèves inscrits</p>
                    </div>
                    <div class="align-self-center">
                        <i class="fas fa-users fa-2x"></i>
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
                        <h4><?php echo count($evaluations_par_periode); ?></h4>
                        <p class="mb-0">Périodes actives</p>
                    </div>
                    <div class="align-self-center">
                        <i class="fas fa-calendar fa-2x"></i>
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
                        <h4><?php echo count($classes); ?></h4>
                        <p class="mb-0">Classes</p>
                    </div>
                    <div class="align-self-center">
                        <i class="fas fa-school fa-2x"></i>
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
                        <h4><?php echo array_sum(array_column($evaluations_par_periode, 'nombre')); ?></h4>
                        <p class="mb-0">Évaluations</p>
                    </div>
                    <div class="align-self-center">
                        <i class="fas fa-clipboard-list fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Sélection de classe et période -->
<div class="card mb-4">
    <div class="card-header">
        <h5 class="mb-0">
            <i class="fas fa-filter me-2"></i>
            Sélection pour génération de bulletins
        </h5>
    </div>
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-md-5">
                <label for="classe" class="form-label">Classe <span class="text-danger">*</span></label>
                <select class="form-select" id="classe" name="classe" required>
                    <option value="">Sélectionner une classe...</option>
                    <?php foreach ($classes as $classe): ?>
                        <option value="<?php echo $classe['id']; ?>" 
                                <?php echo $classe_filter == $classe['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($classe['nom']); ?> 
                            (<?php echo ucfirst($classe['niveau']); ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label for="periode" class="form-label">Période <span class="text-danger">*</span></label>
                <select class="form-select" id="periode" name="periode" required>
                    <option value="">Sélectionner une période...</option>
                    <option value="1er_trimestre" <?php echo $periode_filter === '1er_trimestre' ? 'selected' : ''; ?>>1er Trimestre</option>
                    <option value="2eme_trimestre" <?php echo $periode_filter === '2eme_trimestre' ? 'selected' : ''; ?>>2ème Trimestre</option>
                    <option value="3eme_trimestre" <?php echo $periode_filter === '3eme_trimestre' ? 'selected' : ''; ?>>3ème Trimestre</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">&nbsp;</label>
                <div class="d-grid">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search me-1"></i>
                        Afficher
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Résultats et génération -->
<?php if ($classe_filter && $periode_filter): ?>
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0">
                <i class="fas fa-list me-2"></i>
                Élèves et moyennes - 
                <?php 
                $classe_info = array_filter($classes, fn($c) => $c['id'] == $classe_filter)[0] ?? null;
                echo $classe_info ? htmlspecialchars($classe_info['nom']) : 'Classe inconnue';
                ?>
                (<?php echo str_replace('_', ' ', ucfirst($periode_filter)); ?>)
            </h5>
            <?php if (!empty($moyennes_classes)): ?>
                <div class="btn-group">
                    <a href="generate.php?classe=<?php echo $classe_filter; ?>&periode=<?php echo $periode_filter; ?>" 
                       class="btn btn-success">
                        <i class="fas fa-file-alt me-1"></i>
                        Générer tous les bulletins
                    </a>
                    <button type="button" class="btn btn-outline-success dropdown-toggle dropdown-toggle-split" data-bs-toggle="dropdown">
                        <span class="visually-hidden">Toggle Dropdown</span>
                    </button>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="preview.php?classe=<?php echo $classe_filter; ?>&periode=<?php echo $periode_filter; ?>">
                            <i class="fas fa-eye me-2"></i>Aperçu
                        </a></li>
                        <li><a class="dropdown-item" href="export.php?classe=<?php echo $classe_filter; ?>&periode=<?php echo $periode_filter; ?>&format=pdf">
                            <i class="fas fa-file-pdf me-2"></i>Export PDF
                        </a></li>
                        <li><a class="dropdown-item" href="export.php?classe=<?php echo $classe_filter; ?>&periode=<?php echo $periode_filter; ?>&format=excel">
                            <i class="fas fa-file-excel me-2"></i>Export Excel
                        </a></li>
                    </ul>
                </div>
            <?php endif; ?>
        </div>
        <div class="card-body">
            <?php if (!empty($moyennes_classes)): ?>
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th>Rang</th>
                                <th>Élève</th>
                                <th>Matricule</th>
                                <th>Évaluations</th>
                                <th>Moyenne générale</th>
                                <th>Mention</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($moyennes_classes as $index => $eleve): ?>
                                <tr>
                                    <td>
                                        <span class="badge bg-<?php 
                                            echo $index < 3 ? 'warning' : 'secondary'; 
                                        ?>">
                                            <?php echo $index + 1; ?>
                                            <?php if ($index === 0): ?>
                                                <i class="fas fa-crown ms-1"></i>
                                            <?php endif; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($eleve['nom'] . ' ' . $eleve['prenom']); ?></strong>
                                    </td>
                                    <td>
                                        <small class="text-muted"><?php echo htmlspecialchars($eleve['numero_matricule']); ?></small>
                                    </td>
                                    <td class="text-center">
                                        <?php if ($eleve['nb_evaluations'] > 0): ?>
                                            <span class="badge bg-info"><?php echo $eleve['nb_evaluations']; ?></span>
                                        <?php else: ?>
                                            <span class="badge bg-warning">0</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <?php if ($eleve['moyenne_generale']): ?>
                                            <span class="badge bg-<?php 
                                                echo $eleve['moyenne_generale'] >= 14 ? 'success' : 
                                                    ($eleve['moyenne_generale'] >= 10 ? 'warning' : 'danger'); 
                                            ?> fs-6">
                                                <?php echo round($eleve['moyenne_generale'], 2); ?>/20
                                            </span>
                                        <?php else: ?>
                                            <span class="text-muted">Non évalué</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($eleve['moyenne_generale']): ?>
                                            <?php
                                            $moyenne = $eleve['moyenne_generale'];
                                            if ($moyenne >= 16) {
                                                echo '<span class="badge bg-success">Excellent</span>';
                                            } elseif ($moyenne >= 14) {
                                                echo '<span class="badge bg-info">Très bien</span>';
                                            } elseif ($moyenne >= 12) {
                                                echo '<span class="badge bg-primary">Bien</span>';
                                            } elseif ($moyenne >= 10) {
                                                echo '<span class="badge bg-warning">Satisfaisant</span>';
                                            } elseif ($moyenne >= 8) {
                                                echo '<span class="badge bg-secondary">Passable</span>';
                                            } else {
                                                echo '<span class="badge bg-danger">Insuffisant</span>';
                                            }
                                            ?>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <a href="individual.php?eleve=<?php echo $eleve['id']; ?>&periode=<?php echo $periode_filter; ?>" 
                                               class="btn btn-outline-primary" 
                                               title="Bulletin individuel">
                                                <i class="fas fa-file-alt"></i>
                                            </a>
                                            <a href="preview.php?eleve=<?php echo $eleve['id']; ?>&periode=<?php echo $periode_filter; ?>" 
                                               class="btn btn-outline-info" 
                                               title="Aperçu"
                                               target="_blank">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <a href="download.php?eleve=<?php echo $eleve['id']; ?>&periode=<?php echo $periode_filter; ?>&format=pdf" 
                                               class="btn btn-outline-success" 
                                               title="Télécharger PDF">
                                                <i class="fas fa-download"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Statistiques de la classe -->
                <div class="row mt-4">
                    <div class="col-md-12">
                        <div class="alert alert-info">
                            <h6><i class="fas fa-chart-bar me-2"></i>Statistiques de la classe</h6>
                            <div class="row">
                                <div class="col-md-3">
                                    <strong>Effectif :</strong> <?php echo count($moyennes_classes); ?> élèves
                                </div>
                                <div class="col-md-3">
                                    <?php 
                                    $moyennes_valides = array_filter(array_column($moyennes_classes, 'moyenne_generale'));
                                    $moyenne_classe = !empty($moyennes_valides) ? array_sum($moyennes_valides) / count($moyennes_valides) : 0;
                                    ?>
                                    <strong>Moyenne de classe :</strong> <?php echo round($moyenne_classe, 2); ?>/20
                                </div>
                                <div class="col-md-3">
                                    <?php $admis = count(array_filter($moyennes_valides, fn($m) => $m >= 10)); ?>
                                    <strong>Taux de réussite :</strong> 
                                    <?php echo !empty($moyennes_valides) ? round(($admis / count($moyennes_valides)) * 100, 1) : 0; ?>%
                                </div>
                                <div class="col-md-3">
                                    <strong>Meilleure note :</strong> 
                                    <?php echo !empty($moyennes_valides) ? round(max($moyennes_valides), 2) : 0; ?>/20
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <div class="text-center py-5">
                    <i class="fas fa-exclamation-triangle fa-3x text-muted mb-3"></i>
                    <h5 class="text-muted">Aucune donnée disponible</h5>
                    <p class="text-muted">
                        Aucune évaluation trouvée pour cette classe et cette période.<br>
                        Assurez-vous que des évaluations ont été créées et que les notes ont été saisies.
                    </p>
                    <div class="mt-3">
                        <a href="../evaluations/add.php" class="btn btn-primary me-2">
                            <i class="fas fa-plus me-1"></i>
                            Créer une évaluation
                        </a>
                        <a href="../notes/batch-entry.php" class="btn btn-outline-primary">
                            <i class="fas fa-edit me-1"></i>
                            Saisir des notes
                        </a>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
<?php else: ?>
    <!-- Guide d'utilisation -->
    <div class="row">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-info-circle me-2"></i>
                        Comment générer des bulletins
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <h6><i class="fas fa-step-forward text-primary me-2"></i>Étapes</h6>
                            <ol>
                                <li>Sélectionnez une classe</li>
                                <li>Choisissez la période (trimestre)</li>
                                <li>Cliquez sur "Afficher" pour voir les résultats</li>
                                <li>Générez les bulletins individuels ou en lot</li>
                            </ol>
                        </div>
                        <div class="col-md-6">
                            <h6><i class="fas fa-check-circle text-success me-2"></i>Prérequis</h6>
                            <ul class="list-unstyled">
                                <li><i class="fas fa-check text-success me-2"></i>Évaluations créées</li>
                                <li><i class="fas fa-check text-success me-2"></i>Notes saisies</li>
                                <li><i class="fas fa-check text-success me-2"></i>Élèves inscrits</li>
                                <li><i class="fas fa-check text-success me-2"></i>Matières configurées</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-lg-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-chart-pie me-2"></i>
                        Évaluations par période
                    </h5>
                </div>
                <div class="card-body">
                    <?php if (!empty($evaluations_par_periode)): ?>
                        <?php foreach ($evaluations_par_periode as $periode): ?>
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span><?php echo str_replace('_', ' ', ucfirst($periode['periode'])); ?></span>
                                <span class="badge bg-primary"><?php echo $periode['nombre']; ?></span>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p class="text-muted text-center">Aucune évaluation créée</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php include '../../../includes/footer.php'; ?>
