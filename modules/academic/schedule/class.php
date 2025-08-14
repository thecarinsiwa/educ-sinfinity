<?php
/**
 * Emploi du temps d'une classe
 * URL: class.php?id=1
 */

require_once '../../../config/config.php';
require_once '../../../config/database.php';
require_once '../../../includes/functions.php';

requireLogin();
if (!checkPermission('academic') && !checkPermission('academic_view')) {
    redirectTo('../../login.php');
}

$page_title = "Emploi du temps de la classe";

// Récupérer l'ID de la classe
$class_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($class_id <= 0) {
    die('ID de classe invalide.');
}

// Récupérer les infos de la classe
$classe = $database->query(
    "SELECT c.*, a.annee as annee_scolaire, p.nom as titulaire_nom, p.prenom as titulaire_prenom
     FROM classes c
     LEFT JOIN annees_scolaires a ON c.annee_scolaire_id = a.id
     LEFT JOIN personnel p ON c.titulaire_id = p.id
     WHERE c.id = ?",
    [$class_id]
)->fetch();

if (!$classe) {
    die("Classe non trouvée.");
}

// Récupérer l'emploi du temps
$schedule = $database->query(
    "SELECT e.*, m.nom as matiere_nom, pe.nom as enseignant_nom, pe.prenom as enseignant_prenom
     FROM emplois_temps e
     JOIN matieres m ON e.matiere_id = m.id
     JOIN personnel pe ON e.enseignant_id = pe.id
     WHERE e.classe_id = ? AND e.annee_scolaire_id = ?
     ORDER BY FIELD(LOWER(jour_semaine), 'lundi','mardi','mercredi','jeudi','vendredi','samedi'), heure_debut",
    [$class_id, $classe['annee_scolaire_id']]
)->fetchAll();

include '../../../includes/header.php';
?>
<div class="container mt-4">
    <div class="row justify-content-center">
        <div class="col-lg-10">
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-body">
                    <div class="d-flex align-items-center mb-3">
                        <div class="me-3" style="font-size:2rem;color:#0d6efd;">
                            <i class="bi bi-calendar-week"></i>
                        </div>
                        <div>
                            <h2 class="card-title mb-1">Emploi du temps : <span class="text-primary"><?php echo htmlspecialchars($classe['nom']); ?></span></h2>
                            <div class="mb-1"><span class="badge bg-info text-dark"><i class="bi bi-calendar"></i> Année scolaire : <?php echo htmlspecialchars($classe['annee_scolaire']); ?></span></div>
                            <div><span class="badge bg-secondary"><i class="bi bi-person"></i> Titulaire : <?php echo htmlspecialchars(($classe['titulaire_nom'] ?? '') . ' ' . ($classe['titulaire_prenom'] ?? '')); ?></span></div>
                        </div>
                    </div>
                    <div class="d-flex justify-content-between align-items-center mt-4 mb-2">
                        <h5 class="mb-0"><i class="bi bi-clock-history"></i> Horaires</h5>
                        <div class="btn-group">
                            <a href="add-schedule.php?class_id=<?php echo $class_id; ?>" class="btn btn-success">
                                <i class="bi bi-plus-circle"></i> Ajouter un horaire
                            </a>
                            <a href="generate.php?classe_id=<?php echo $class_id; ?>" class="btn btn-primary">
                                <i class="bi bi-magic"></i> Générer automatiquement
                            </a>
                            <?php if (!empty($schedule)): ?>
                            <div class="btn-group">
                                <button type="button" class="btn btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">
                                    <i class="bi bi-download"></i> Exporter
                                </button>
                                <ul class="dropdown-menu">
                                    <li><a class="dropdown-item" href="export.php?format=pdf&type=classe&classe_id=<?php echo $class_id; ?>">
                                        <i class="bi bi-file-pdf"></i> PDF
                                    </a></li>
                                    <li><a class="dropdown-item" href="export.php?format=excel&type=classe&classe_id=<?php echo $class_id; ?>">
                                        <i class="bi bi-file-excel"></i> Excel
                                    </a></li>
                                </ul>
                            </div>
                            <button type="button" class="btn btn-outline-info" onclick="printSchedule()">
                                <i class="bi bi-printer"></i> Imprimer
                            </button>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Onglets pour changer de vue -->
                    <ul class="nav nav-tabs" id="scheduleTab" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="grid-tab" data-bs-toggle="tab" data-bs-target="#grid-view" type="button" role="tab">
                                <i class="bi bi-grid-3x3"></i> Vue Grille
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="list-tab" data-bs-toggle="tab" data-bs-target="#list-view" type="button" role="tab">
                                <i class="bi bi-list"></i> Vue Liste
                            </button>
                        </li>
                    </ul>

                    <div class="tab-content" id="scheduleTabContent">
                        <!-- Vue Grille -->
                        <div class="tab-pane fade show active" id="grid-view" role="tabpanel">
                            <?php if (!empty($schedule)): ?>
                                <?php
                                // Organiser les données par jour et heure
                                $schedule_grid = [];
                                $all_hours = [];
                                foreach ($schedule as $row) {
                                    $jour = strtolower($row['jour_semaine']);
                                    $heure = substr($row['heure_debut'], 0, 5);
                                    $schedule_grid[$jour][$heure] = $row;
                                    $all_hours[] = $heure;
                                }
                                $all_hours = array_unique($all_hours);
                                sort($all_hours);

                                $jours = ['lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi'];
                                $jours_fr = ['Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi'];
                                ?>
                                <div class="table-responsive mt-3" id="schedule-grid">
                                    <table class="table table-bordered">
                                        <thead class="table-primary">
                                            <tr>
                                                <th class="text-center" style="width: 100px;">Heure</th>
                                                <?php foreach ($jours_fr as $jour_fr): ?>
                                                    <th class="text-center"><?php echo $jour_fr; ?></th>
                                                <?php endforeach; ?>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($all_hours as $heure): ?>
                                                <tr>
                                                    <td class="text-center fw-bold bg-light"><?php echo $heure; ?></td>
                                                    <?php foreach ($jours as $jour): ?>
                                                        <td class="p-2" style="min-height: 80px; vertical-align: top;">
                                                            <?php if (isset($schedule_grid[$jour][$heure])): ?>
                                                                <?php $cours = $schedule_grid[$jour][$heure]; ?>
                                                                <div class="card border-0 bg-primary bg-opacity-10 h-100">
                                                                    <div class="card-body p-2">
                                                                        <h6 class="card-title mb-1 text-primary">
                                                                            <i class="bi bi-book"></i> <?php echo htmlspecialchars($cours['matiere_nom']); ?>
                                                                        </h6>
                                                                        <p class="card-text small mb-1">
                                                                            <i class="bi bi-person"></i> <?php echo htmlspecialchars($cours['enseignant_nom'] . ' ' . $cours['enseignant_prenom']); ?>
                                                                        </p>
                                                                        <p class="card-text small mb-1">
                                                                            <i class="bi bi-clock"></i> <?php echo substr($cours['heure_debut'], 0, 5) . '-' . substr($cours['heure_fin'], 0, 5); ?>
                                                                        </p>
                                                                        <?php if ($cours['salle']): ?>
                                                                            <p class="card-text small mb-1">
                                                                                <i class="bi bi-door-closed"></i> <?php echo htmlspecialchars($cours['salle']); ?>
                                                                            </p>
                                                                        <?php endif; ?>
                                                                        <div class="mt-2">
                                                                            <a href="edit-schedule.php?id=<?php echo $cours['id']; ?>" class="btn btn-sm btn-outline-primary">
                                                                                <i class="bi bi-pencil"></i>
                                                                            </a>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            <?php endif; ?>
                                                        </td>
                                                    <?php endforeach; ?>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <div class="text-center py-5">
                                    <i class="bi bi-calendar-x display-1 text-muted"></i>
                                    <h4 class="text-muted mt-3">Aucun horaire défini</h4>
                                    <p class="text-muted">Cette classe n'a pas encore d'emploi du temps.</p>
                                    <div class="mt-4">
                                        <a href="add-schedule.php?class_id=<?php echo $class_id; ?>" class="btn btn-success me-2">
                                            <i class="bi bi-plus-circle"></i> Ajouter un horaire
                                        </a>
                                        <a href="generate.php?classe_id=<?php echo $class_id; ?>" class="btn btn-primary">
                                            <i class="bi bi-magic"></i> Générer automatiquement
                                        </a>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Vue Liste -->
                        <div class="tab-pane fade" id="list-view" role="tabpanel">
                            <div class="table-responsive mt-3">
                                <table class="table table-hover table-bordered align-middle mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th><i class="bi bi-calendar-day"></i> Jour</th>
                                            <th><i class="bi bi-clock"></i> Début</th>
                                            <th><i class="bi bi-clock"></i> Fin</th>
                                            <th><i class="bi bi-book"></i> Matière</th>
                                            <th><i class="bi bi-person"></i> Enseignant</th>
                                            <th><i class="bi bi-door-closed"></i> Salle</th>
                                            <th><i class="bi bi-gear"></i> Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                    <?php if (!empty($schedule)): ?>
                                        <?php foreach ($schedule as $row): ?>
                                            <tr>
                                                <td><span class="badge bg-light text-dark border"><i class="bi bi-calendar-day"></i> <?php echo ucfirst($row['jour_semaine']); ?></span></td>
                                                <td><span class="badge bg-primary-subtle text-primary"><i class="bi bi-clock"></i> <?php echo htmlspecialchars(substr($row['heure_debut'], 0, 5)); ?></span></td>
                                                <td><span class="badge bg-primary-subtle text-primary"><i class="bi bi-clock"></i> <?php echo htmlspecialchars(substr($row['heure_fin'], 0, 5)); ?></span></td>
                                                <td><span class="badge bg-success-subtle text-success"><i class="bi bi-book"></i> <?php echo htmlspecialchars($row['matiere_nom']); ?></span></td>
                                                <td><span class="badge bg-secondary-subtle text-secondary"><i class="bi bi-person"></i> <?php echo htmlspecialchars($row['enseignant_nom'] . ' ' . $row['enseignant_prenom']); ?></span></td>
                                                <td><span class="badge bg-warning-subtle text-warning"><i class="bi bi-door-closed"></i> <?php echo htmlspecialchars($row['salle'] ?? ''); ?></span></td>
                                                <td>
                                                    <a href="edit-schedule.php?id=<?php echo $row['id']; ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i> Modifier</a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr><td colspan="7" class="text-center text-muted">Aucun horaire défini pour cette classe.</td></tr>
                                    <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    <div class="d-flex justify-content-between align-items-center mt-4">
                        <a href="../classes/view.php?id=<?php echo $class_id; ?>" class="btn btn-secondary">
                            <i class="bi bi-arrow-left"></i> Retour à la classe
                        </a>
                        <?php if (!empty($schedule)): ?>
                        <div class="text-muted small">
                            <i class="bi bi-info-circle"></i>
                            <?php echo count($schedule); ?> cours programmé(s) cette semaine
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Styles d'impression -->
<style>
@media print {
    .btn, .nav-tabs, .dropdown, .no-print {
        display: none !important;
    }

    .card {
        border: none !important;
        box-shadow: none !important;
    }

    .table {
        font-size: 12px;
    }

    .card-title {
        color: #000 !important;
        font-size: 18px;
        margin-bottom: 20px;
    }

    #schedule-grid .card {
        border: 1px solid #ddd !important;
        background: #f8f9fa !important;
    }

    .badge {
        color: #000 !important;
        background: #f8f9fa !important;
        border: 1px solid #ddd !important;
    }
}
</style>

<!-- JavaScript pour l'impression -->
<script>
function printSchedule() {
    // Masquer les éléments non nécessaires pour l'impression
    const elementsToHide = document.querySelectorAll('.btn, .nav-tabs, .dropdown');
    elementsToHide.forEach(el => el.style.display = 'none');

    // Imprimer
    window.print();

    // Restaurer les éléments après impression
    setTimeout(() => {
        elementsToHide.forEach(el => el.style.display = '');
    }, 1000);
}

// Améliorer l'affichage des onglets
document.addEventListener('DOMContentLoaded', function() {
    // Sauvegarder l'onglet actif dans localStorage
    const tabButtons = document.querySelectorAll('#scheduleTab button[data-bs-toggle="tab"]');
    tabButtons.forEach(button => {
        button.addEventListener('shown.bs.tab', function(e) {
            localStorage.setItem('activeScheduleTab', e.target.id);
        });
    });

    // Restaurer l'onglet actif
    const activeTab = localStorage.getItem('activeScheduleTab');
    if (activeTab) {
        const tabButton = document.getElementById(activeTab);
        if (tabButton) {
            const tab = new bootstrap.Tab(tabButton);
            tab.show();
        }
    }
});
</script>

<!-- Optionally include Bootstrap Icons if not already present -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
<?php include '../../../includes/footer.php'; ?>
