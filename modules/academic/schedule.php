<?php
/**
 * Module académique - Emploi du temps par enseignant
 * Application de gestion scolaire - République Démocratique du Congo
 */

require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../includes/functions.php';

// Vérifier l'authentification et les permissions
requireLogin();
if (!checkPermission('academic') && !checkPermission('academic_view')) {
    showMessage('error', 'Accès refusé à ce module.');
    redirectTo('../index.php');
}

// Récupérer l'ID de l'enseignant
$teacher_id = (int)($_GET['teacher'] ?? 0);
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

$page_title = 'Emploi du temps de ' . $teacher['nom'] . ' ' . $teacher['prenom'];

// Obtenir l'année scolaire actuelle
$current_year = getCurrentAcademicYear();

// Récupérer l'emploi du temps de l'enseignant
$schedule_data = $database->query(
    "SELECT et.*, 
            c.nom as classe_nom, c.niveau, c.section,
            m.nom as matiere_nom, m.code as matiere_code, m.coefficient
     FROM emploi_temps et
     JOIN classes c ON et.classe_id = c.id
     JOIN matieres m ON et.matiere_id = m.id
     WHERE et.enseignant_id = ? AND et.annee_scolaire_id = ? AND et.status = 'actif'
     ORDER BY 
        CASE et.jour_semaine 
            WHEN 'Lundi' THEN 1
            WHEN 'Mardi' THEN 2
            WHEN 'Mercredi' THEN 3
            WHEN 'Jeudi' THEN 4
            WHEN 'Vendredi' THEN 5
            WHEN 'Samedi' THEN 6
            WHEN 'Dimanche' THEN 7
        END,
        et.heure_debut",
    [$teacher_id, $current_year['id']]
)->fetchAll();

// Organiser les données par jour de la semaine
$schedule_by_day = [];
$jours_semaine = ['Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi', 'Dimanche'];

foreach ($jours_semaine as $jour) {
    $schedule_by_day[$jour] = [];
}

foreach ($schedule_data as $cours) {
    $schedule_by_day[$cours['jour_semaine']][] = $cours;
}

// Statistiques de l'enseignant
$stats = $database->query(
    "SELECT 
        COUNT(*) as total_cours,
        COUNT(DISTINCT classe_id) as nb_classes,
        COUNT(DISTINCT matiere_id) as nb_matieres,
        COUNT(DISTINCT jour_semaine) as nb_jours_travailles,
        SUM(TIME_TO_SEC(TIMEDIFF(heure_fin, heure_debut))) / 3600 as heures_totales
     FROM emploi_temps 
     WHERE enseignant_id = ? AND annee_scolaire_id = ? AND status = 'actif'",
    [$teacher_id, $current_year['id']]
)->fetch();

// Récupérer les classes enseignées
$classes_enseignees = $database->query(
    "SELECT DISTINCT c.* 
     FROM classes c
     JOIN emploi_temps et ON c.id = et.classe_id
     WHERE et.enseignant_id = ? AND et.annee_scolaire_id = ? AND et.status = 'actif'
     ORDER BY c.niveau, c.nom",
    [$teacher_id, $current_year['id']]
)->fetchAll();

// Récupérer les matières enseignées
$matieres_enseignees = $database->query(
    "SELECT DISTINCT m.* 
     FROM matieres m
     JOIN emploi_temps et ON m.id = et.matiere_id
     WHERE et.enseignant_id = ? AND et.annee_scolaire_id = ? AND et.status = 'actif'
     ORDER BY m.nom",
    [$teacher_id, $current_year['id']]
)->fetchAll();

include '../../includes/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">
        <i class="fas fa-calendar-alt me-2"></i>
        Emploi du temps de <?php echo htmlspecialchars($teacher['nom'] . ' ' . $teacher['prenom']); ?>
    </h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <a href="../index.php" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-left me-1"></i>
            Retour au module académique
        </a>
        <?php if (checkPermission('academic')): ?>
            <a href="schedule/add.php?teacher_id=<?php echo $teacher_id; ?>" class="btn btn-primary ms-2">
                <i class="fas fa-plus me-1"></i>
                Ajouter un cours
            </a>
        <?php endif; ?>
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
                            <i class="fas fa-chalkboard-teacher me-2"></i>
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
                                    <h4 class="text-primary mb-0"><?php echo $stats['total_cours']; ?></h4>
                                    <small class="text-muted">Cours</small>
                                </div>
                            </div>
                            <div class="col-3">
                                <div class="border-end">
                                    <h4 class="text-success mb-0"><?php echo $stats['nb_classes']; ?></h4>
                                    <small class="text-muted">Classes</small>
                                </div>
                            </div>
                            <div class="col-3">
                                <div class="border-end">
                                    <h4 class="text-info mb-0"><?php echo $stats['nb_matieres']; ?></h4>
                                    <small class="text-muted">Matières</small>
                                </div>
                            </div>
                            <div class="col-3">
                                <h4 class="text-warning mb-0"><?php echo number_format($stats['heures_totales'], 1); ?>h</h4>
                                <small class="text-muted">Total</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Résumé des classes et matières -->
<div class="row mb-4">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h6 class="mb-0">
                    <i class="fas fa-users me-2"></i>
                    Classes enseignées (<?php echo count($classes_enseignees); ?>)
                </h6>
            </div>
            <div class="card-body">
                <?php if (empty($classes_enseignees)): ?>
                    <p class="text-muted mb-0">Aucune classe assignée</p>
                <?php else: ?>
                    <div class="row">
                        <?php foreach ($classes_enseignees as $classe): ?>
                            <div class="col-md-6 mb-2">
                                <span class="badge bg-primary me-1">
                                    <?php echo htmlspecialchars($classe['nom']); ?>
                                </span>
                                <small class="text-muted"><?php echo htmlspecialchars($classe['niveau']); ?></small>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h6 class="mb-0">
                    <i class="fas fa-book me-2"></i>
                    Matières enseignées (<?php echo count($matieres_enseignees); ?>)
                </h6>
            </div>
            <div class="card-body">
                <?php if (empty($matieres_enseignees)): ?>
                    <p class="text-muted mb-0">Aucune matière assignée</p>
                <?php else: ?>
                    <div class="row">
                        <?php foreach ($matieres_enseignees as $matiere): ?>
                            <div class="col-md-6 mb-2">
                                <span class="badge bg-success me-1">
                                    <?php echo htmlspecialchars($matiere['nom']); ?>
                                </span>
                                <small class="text-muted">Coef. <?php echo $matiere['coefficient']; ?></small>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Emploi du temps -->
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h6 class="mb-0">
                    <i class="fas fa-calendar-week me-2"></i>
                    Emploi du temps hebdomadaire
                </h6>
                <div class="btn-group btn-group-sm" role="group">
                    <button type="button" class="btn btn-outline-primary" onclick="window.print()">
                        <i class="fas fa-print me-1"></i>
                        Imprimer
                    </button>
                    <button type="button" class="btn btn-outline-secondary" onclick="exportToPDF('schedule-content', 'emploi_temps_<?php echo $teacher['matricule']; ?>.pdf', 'Emploi du temps - <?php echo $teacher['nom']; ?>')">
                        <i class="fas fa-file-pdf me-1"></i>
                        PDF
                    </button>
                </div>
            </div>
            <div class="card-body p-0" id="schedule-content">
                <?php if (empty($schedule_data)): ?>
                    <div class="text-center py-5">
                        <i class="fas fa-calendar-times fa-3x text-muted mb-3"></i>
                        <h5 class="text-muted">Aucun cours programmé</h5>
                        <p class="text-muted">Cet enseignant n'a pas encore d'emploi du temps pour cette année scolaire.</p>
                        <?php if (checkPermission('academic')): ?>
                            <a href="schedule/add.php?teacher_id=<?php echo $teacher_id; ?>" class="btn btn-primary">
                                <i class="fas fa-plus me-1"></i>
                                Créer le premier cours
                            </a>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-bordered mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th class="text-center" style="width: 12%;">Heure</th>
                                    <?php foreach ($jours_semaine as $jour): ?>
                                        <th class="text-center" style="width: 12.5%;">
                                            <?php echo $jour; ?>
                                        </th>
                                    <?php endforeach; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                // Créer des créneaux horaires (7h à 18h)
                                $heures = [];
                                for ($h = 7; $h <= 18; $h++) {
                                    for ($m = 0; $m < 60; $m += 30) {
                                        $heures[] = sprintf('%02d:%02d', $h, $m);
                                    }
                                }
                                
                                // Trouver les créneaux utilisés
                                $creneaux_utilises = [];
                                foreach ($schedule_data as $cours) {
                                    $debut = $cours['heure_debut'];
                                    $fin = $cours['heure_fin'];
                                    
                                    // Ajouter le créneau de début
                                    if (!in_array($debut, $creneaux_utilises)) {
                                        $creneaux_utilises[] = $debut;
                                    }
                                    
                                    // Ajouter le créneau de fin
                                    if (!in_array($fin, $creneaux_utilises)) {
                                        $creneaux_utilises[] = $fin;
                                    }
                                }
                                
                                // Filtrer les heures pour ne garder que celles utilisées
                                $heures_affichees = array_intersect($heures, $creneaux_utilises);
                                sort($heures_affichees);
                                
                                // Si pas de créneaux, afficher un exemple
                                if (empty($heures_affichees)) {
                                    $heures_affichees = ['08:00', '10:00', '14:00', '16:00'];
                                }
                                
                                foreach ($heures_affichees as $heure): ?>
                                    <tr>
                                        <td class="text-center fw-bold bg-light">
                                            <?php echo $heure; ?>
                                        </td>
                                        <?php foreach ($jours_semaine as $jour): ?>
                                            <td class="text-center" style="height: 60px; vertical-align: middle;">
                                                <?php
                                                $cours_ce_creneau = array_filter($schedule_data, function($cours) use ($jour, $heure) {
                                                    return $cours['jour_semaine'] === $jour && $cours['heure_debut'] === $heure;
                                                });
                                                
                                                if (!empty($cours_ce_creneau)) {
                                                    $cours = reset($cours_ce_creneau);
                                                    $duree = (strtotime($cours['heure_fin']) - strtotime($cours['heure_debut'])) / 3600;
                                                    $lignes = max(1, round($duree * 2)); // 2 lignes par heure
                                                    ?>
                                                    <div class="cours-item" style="background-color: <?php echo getCoursColor($cours['matiere_id']); ?>; color: white; padding: 5px; border-radius: 4px; font-size: 0.85em; line-height: 1.2;">
                                                        <div class="fw-bold"><?php echo htmlspecialchars($cours['matiere_nom']); ?></div>
                                                        <div class="small"><?php echo htmlspecialchars($cours['classe_nom']); ?></div>
                                                        <?php if ($cours['salle']): ?>
                                                            <div class="small">
                                                                <i class="fas fa-door-open"></i> <?php echo htmlspecialchars($cours['salle']); ?>
                                                            </div>
                                                        <?php endif; ?>
                                                        <div class="small">
                                                            <?php echo $cours['heure_debut']; ?> - <?php echo $cours['heure_fin']; ?>
                                                        </div>
                                                    </div>
                                                    <?php
                                                }
                                                ?>
                                            </td>
                                        <?php endforeach; ?>
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

<style>
@media print {
    .btn-toolbar, .card-header .btn-group {
        display: none !important;
    }
    
    .cours-item {
        background-color: #f8f9fa !important;
        color: #000 !important;
        border: 1px solid #dee2e6 !important;
    }
    
    .table {
        font-size: 0.8em;
    }
}

.cours-item {
    transition: all 0.3s ease;
}

.cours-item:hover {
    transform: scale(1.02);
    box-shadow: 0 2px 8px rgba(0,0,0,0.15);
}
</style>

<script>
// Fonction pour générer des couleurs pour les matières
function getCoursColor(matiereId) {
    const colors = [
        '#007bff', '#28a745', '#dc3545', '#ffc107', '#17a2b8',
        '#6f42c1', '#e83e8c', '#fd7e14', '#20c997', '#6c757d'
    ];
    return colors[matiereId % colors.length];
}

// Fonction d'export PDF (si disponible)
function exportToPDF(elementId, filename, title) {
    if (typeof window.jsPDF !== 'undefined' && typeof html2canvas !== 'undefined') {
        try {
            exportToPDF(elementId, filename, title);
        } catch (error) {
            console.error('Erreur lors de l\'export PDF:', error);
            alert('Erreur lors de l\'export PDF. Utilisez la fonction d\'impression du navigateur.');
        }
    } else {
        alert('Fonction d\'export PDF non disponible. Utilisez la fonction d\'impression du navigateur.');
    }
}
</script>

<?php
// Fonction pour générer des couleurs pour les matières
function getCoursColor($matiere_id) {
    $colors = [
        '#007bff', '#28a745', '#dc3545', '#ffc107', '#17a2b8',
        '#6f42c1', '#e83e8c', '#fd7e14', '#20c997', '#6c757d'
    ];
    return $colors[$matiere_id % count($colors)];
}

include '../../includes/footer.php';
?>
