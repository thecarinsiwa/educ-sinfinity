<?php
/**
 * Module Académique - Détection de conflits d'emploi du temps
 * Application de gestion scolaire - République Démocratique du Congo
 */

require_once '../../../config/config.php';
require_once '../../../config/database.php';
require_once '../../../includes/functions.php';

// Vérifier l'authentification et les permissions
requireLogin();
if (!checkPermission('admin') && !checkPermission('academic')) {
    showMessage('error', 'Accès refusé à cette fonctionnalité.');
    redirectTo('../../../index.php');
}

$page_title = "Détection de conflits d'emploi du temps";

// Récupérer l'année scolaire active
$current_year = $database->query("SELECT * FROM annees_scolaires WHERE status = 'active' LIMIT 1")->fetch();
if (!$current_year) {
    showMessage('error', 'Aucune année scolaire active trouvée.');
    redirectTo('../../../index.php');
}

// Fonction pour détecter les conflits
function detectConflicts($database, $annee_id) {
    $conflicts = [];
    
    // Requête pour détecter les conflits d'enseignants
    $teacher_conflicts = $database->query(
        "SELECT e1.id as cours1_id, e2.id as cours2_id, 
                e1.jour_semaine, e1.heure_debut, e1.heure_fin,
                e1.enseignant_id,
                CONCAT(p.nom, ' ', p.prenom) as enseignant_nom,
                m1.nom as matiere1, m2.nom as matiere2,
                c1.nom as classe1, c2.nom as classe2,
                'enseignant' as conflict_type
         FROM emplois_temps e1
         JOIN emplois_temps e2 ON e1.enseignant_id = e2.enseignant_id 
                                AND e1.jour_semaine = e2.jour_semaine
                                AND e1.id < e2.id
                                AND e1.annee_scolaire_id = e2.annee_scolaire_id
         JOIN personnel p ON e1.enseignant_id = p.id
         JOIN matieres m1 ON e1.matiere_id = m1.id
         JOIN matieres m2 ON e2.matiere_id = m2.id
         JOIN classes c1 ON e1.classe_id = c1.id
         JOIN classes c2 ON e2.classe_id = c2.id
         WHERE e1.annee_scolaire_id = ?
         AND ((e1.heure_debut < e2.heure_fin AND e1.heure_fin > e2.heure_debut))",
        [$annee_id]
    )->fetchAll();
    
    // Requête pour détecter les conflits de salles
    $room_conflicts = $database->query(
        "SELECT e1.id as cours1_id, e2.id as cours2_id, 
                e1.jour_semaine, e1.heure_debut, e1.heure_fin,
                e1.salle,
                m1.nom as matiere1, m2.nom as matiere2,
                c1.nom as classe1, c2.nom as classe2,
                CONCAT(p1.nom, ' ', p1.prenom) as enseignant1,
                CONCAT(p2.nom, ' ', p2.prenom) as enseignant2,
                'salle' as conflict_type
         FROM emplois_temps e1
         JOIN emplois_temps e2 ON e1.salle = e2.salle 
                                AND e1.jour_semaine = e2.jour_semaine
                                AND e1.id < e2.id
                                AND e1.annee_scolaire_id = e2.annee_scolaire_id
         JOIN matieres m1 ON e1.matiere_id = m1.id
         JOIN matieres m2 ON e2.matiere_id = m2.id
         JOIN classes c1 ON e1.classe_id = c1.id
         JOIN classes c2 ON e2.classe_id = c2.id
         JOIN personnel p1 ON e1.enseignant_id = p1.id
         JOIN personnel p2 ON e2.enseignant_id = p2.id
         WHERE e1.annee_scolaire_id = ? 
         AND e1.salle IS NOT NULL AND e1.salle != ''
         AND ((e1.heure_debut < e2.heure_fin AND e1.heure_fin > e2.heure_debut))",
        [$annee_id]
    )->fetchAll();
    
    // Requête pour détecter les conflits de classes (même classe, deux cours simultanés)
    $class_conflicts = $database->query(
        "SELECT e1.id as cours1_id, e2.id as cours2_id, 
                e1.jour_semaine, e1.heure_debut, e1.heure_fin,
                e1.classe_id,
                c1.nom as classe_nom,
                m1.nom as matiere1, m2.nom as matiere2,
                CONCAT(p1.nom, ' ', p1.prenom) as enseignant1,
                CONCAT(p2.nom, ' ', p2.prenom) as enseignant2,
                'classe' as conflict_type
         FROM emplois_temps e1
         JOIN emplois_temps e2 ON e1.classe_id = e2.classe_id 
                                AND e1.jour_semaine = e2.jour_semaine
                                AND e1.id < e2.id
                                AND e1.annee_scolaire_id = e2.annee_scolaire_id
         JOIN matieres m1 ON e1.matiere_id = m1.id
         JOIN matieres m2 ON e2.matiere_id = m2.id
         JOIN classes c1 ON e1.classe_id = c1.id
         JOIN personnel p1 ON e1.enseignant_id = p1.id
         JOIN personnel p2 ON e2.enseignant_id = p2.id
         WHERE e1.annee_scolaire_id = ?
         AND ((e1.heure_debut < e2.heure_fin AND e1.heure_fin > e2.heure_debut))",
        [$annee_id]
    )->fetchAll();
    
    return array_merge($teacher_conflicts, $room_conflicts, $class_conflicts);
}

// Détecter les conflits
$conflicts = detectConflicts($database, $current_year['id']);

// Statistiques
$total_conflicts = count($conflicts);
$teacher_conflicts_count = count(array_filter($conflicts, function($c) { return $c['conflict_type'] === 'enseignant'; }));
$room_conflicts_count = count(array_filter($conflicts, function($c) { return $c['conflict_type'] === 'salle'; }));
$class_conflicts_count = count(array_filter($conflicts, function($c) { return $c['conflict_type'] === 'classe'; }));

include '../../../includes/header.php';
?>

<div class="container mt-4">
    <div class="row">
        <div class="col-12">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-primary text-white">
                    <h4 class="mb-0">
                        <i class="bi bi-search"></i>
                        Détection de conflits d'emploi du temps
                    </h4>
                </div>
                <div class="card-body">
                    <!-- Statistiques -->
                    <div class="row mb-4">
                        <div class="col-md-3">
                            <div class="card bg-<?php echo $total_conflicts > 0 ? 'danger' : 'success'; ?> text-white">
                                <div class="card-body text-center">
                                    <h2 class="mb-0"><?php echo $total_conflicts; ?></h2>
                                    <p class="mb-0">Conflits détectés</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card bg-warning text-dark">
                                <div class="card-body text-center">
                                    <h2 class="mb-0"><?php echo $teacher_conflicts_count; ?></h2>
                                    <p class="mb-0">Conflits d'enseignants</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card bg-info text-white">
                                <div class="card-body text-center">
                                    <h2 class="mb-0"><?php echo $room_conflicts_count; ?></h2>
                                    <p class="mb-0">Conflits de salles</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card bg-secondary text-white">
                                <div class="card-body text-center">
                                    <h2 class="mb-0"><?php echo $class_conflicts_count; ?></h2>
                                    <p class="mb-0">Conflits de classes</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <?php if ($total_conflicts > 0): ?>
                        <div class="alert alert-warning">
                            <h5><i class="bi bi-exclamation-triangle"></i> Conflits détectés</h5>
                            <p class="mb-0">
                                <?php echo $total_conflicts; ?> conflit(s) ont été détecté(s) dans l'emploi du temps. 
                                Veuillez les résoudre pour éviter les problèmes d'organisation.
                            </p>
                        </div>

                        <!-- Liste des conflits -->
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead class="table-light">
                                    <tr>
                                        <th>Type</th>
                                        <th>Jour/Heure</th>
                                        <th>Détails du conflit</th>
                                        <th>Cours #1</th>
                                        <th>Cours #2</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($conflicts as $conflict): ?>
                                        <tr>
                                            <td>
                                                <?php if ($conflict['conflict_type'] === 'enseignant'): ?>
                                                    <span class="badge bg-danger">
                                                        <i class="bi bi-person-x"></i> Enseignant
                                                    </span>
                                                <?php elseif ($conflict['conflict_type'] === 'salle'): ?>
                                                    <span class="badge bg-warning text-dark">
                                                        <i class="bi bi-door-closed"></i> Salle
                                                    </span>
                                                <?php else: ?>
                                                    <span class="badge bg-info">
                                                        <i class="bi bi-people"></i> Classe
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <strong><?php echo ucfirst($conflict['jour_semaine']); ?></strong><br>
                                                <small class="text-muted">
                                                    <?php echo substr($conflict['heure_debut'], 0, 5); ?> - 
                                                    <?php echo substr($conflict['heure_fin'], 0, 5); ?>
                                                </small>
                                            </td>
                                            <td>
                                                <?php if ($conflict['conflict_type'] === 'enseignant'): ?>
                                                    <strong><?php echo htmlspecialchars($conflict['enseignant_nom']); ?></strong>
                                                    <br><small class="text-muted">ne peut être à deux endroits</small>
                                                <?php elseif ($conflict['conflict_type'] === 'salle'): ?>
                                                    <strong><?php echo htmlspecialchars($conflict['salle']); ?></strong>
                                                    <br><small class="text-muted">occupée par deux cours</small>
                                                <?php else: ?>
                                                    <strong><?php echo htmlspecialchars($conflict['classe_nom']); ?></strong>
                                                    <br><small class="text-muted">a deux cours simultanés</small>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <strong><?php echo htmlspecialchars($conflict['matiere1']); ?></strong><br>
                                                <small class="text-muted">
                                                    <?php if ($conflict['conflict_type'] === 'enseignant'): ?>
                                                        <?php echo htmlspecialchars($conflict['classe1']); ?>
                                                    <?php elseif ($conflict['conflict_type'] === 'salle'): ?>
                                                        <?php echo htmlspecialchars($conflict['classe1']); ?> - <?php echo htmlspecialchars($conflict['enseignant1']); ?>
                                                    <?php else: ?>
                                                        <?php echo htmlspecialchars($conflict['enseignant1']); ?>
                                                    <?php endif; ?>
                                                </small>
                                            </td>
                                            <td>
                                                <strong><?php echo htmlspecialchars($conflict['matiere2']); ?></strong><br>
                                                <small class="text-muted">
                                                    <?php if ($conflict['conflict_type'] === 'enseignant'): ?>
                                                        <?php echo htmlspecialchars($conflict['classe2']); ?>
                                                    <?php elseif ($conflict['conflict_type'] === 'salle'): ?>
                                                        <?php echo htmlspecialchars($conflict['classe2']); ?> - <?php echo htmlspecialchars($conflict['enseignant2']); ?>
                                                    <?php else: ?>
                                                        <?php echo htmlspecialchars($conflict['enseignant2']); ?>
                                                    <?php endif; ?>
                                                </small>
                                            </td>
                                            <td>
                                                <a href="resolve-conflict.php?cours1=<?php echo $conflict['cours1_id']; ?>&cours2=<?php echo $conflict['cours2_id']; ?>" 
                                                   class="btn btn-sm btn-warning">
                                                    <i class="bi bi-tools"></i> Résoudre
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- Actions groupées -->
                        <div class="mt-4">
                            <div class="alert alert-info">
                                <h6><i class="bi bi-lightbulb"></i> Suggestions pour résoudre les conflits :</h6>
                                <ul class="mb-0">
                                    <li><strong>Conflits d'enseignants :</strong> Modifier l'horaire ou changer l'enseignant</li>
                                    <li><strong>Conflits de salles :</strong> Changer la salle ou modifier l'horaire</li>
                                    <li><strong>Conflits de classes :</strong> Décaler l'un des cours ou fusionner si possible</li>
                                </ul>
                            </div>
                        </div>

                    <?php else: ?>
                        <div class="alert alert-success">
                            <h5><i class="bi bi-check-circle"></i> Aucun conflit détecté</h5>
                            <p class="mb-0">
                                Félicitations ! L'emploi du temps ne présente aucun conflit. 
                                Tous les cours sont correctement planifiés.
                            </p>
                        </div>

                        <div class="text-center py-4">
                            <i class="bi bi-check-circle-fill text-success" style="font-size: 4rem;"></i>
                            <h4 class="text-success mt-3">Emploi du temps valide</h4>
                            <p class="text-muted">Aucune intervention nécessaire</p>
                        </div>
                    <?php endif; ?>

                    <!-- Actions -->
                    <div class="d-flex justify-content-between mt-4">
                        <a href="index.php" class="btn btn-secondary">
                            <i class="bi bi-arrow-left"></i> Retour à l'emploi du temps
                        </a>
                        <div>
                            <button onclick="location.reload()" class="btn btn-outline-primary">
                                <i class="bi bi-arrow-clockwise"></i> Actualiser
                            </button>
                            <?php if ($total_conflicts > 0): ?>
                                <a href="generate.php" class="btn btn-primary">
                                    <i class="bi bi-magic"></i> Régénérer automatiquement
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../../../includes/footer.php'; ?>
