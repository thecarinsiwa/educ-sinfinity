<?php
/**
 * Module de gestion académique - Voir les détails d'une matière
 * Application de gestion scolaire - République Démocratique du Congo
 */

require_once '../../../config/config.php';
require_once '../../../config/database.php';
require_once '../../../includes/functions.php';

// Vérifier l'authentification et les permissions
requireLogin();
if (!checkPermission('academic') && !checkPermission('academic_view')) {
    showMessage('error', 'Accès refusé à cette fonctionnalité.');
    redirectTo('index.php');
}

// Récupérer l'ID de la matière
$matiere_id = (int)($_GET['id'] ?? 0);
if (!$matiere_id) {
    showMessage('error', 'ID de matière manquant.');
    redirectTo('index.php');
}

// Récupérer les données de la matière
$matiere = $database->query(
    "SELECT * FROM matieres WHERE id = ?",
    [$matiere_id]
)->fetch();

if (!$matiere) {
    showMessage('error', 'Matière non trouvée.');
    redirectTo('index.php');
}

$page_title = 'Détails de la matière : ' . $matiere['nom'];

// Récupérer les classes qui utilisent cette matière
$classes = $database->query(
    "SELECT DISTINCT c.id, c.nom, c.niveau, c.section,
            COUNT(et.id) as nb_cours,
            GROUP_CONCAT(DISTINCT CONCAT(p.nom, ' ', p.prenom) SEPARATOR ', ') as enseignants
     FROM classes c
     JOIN emplois_temps et ON c.id = et.classe_id
     LEFT JOIN personnel p ON et.enseignant_id = p.id
     WHERE et.matiere_id = ?
     GROUP BY c.id, c.nom, c.niveau, c.section
     ORDER BY c.niveau, c.nom",
    [$matiere_id]
)->fetchAll();

// Récupérer les enseignants qui enseignent cette matière
$enseignants = $database->query(
    "SELECT DISTINCT p.id, p.nom, p.prenom, p.specialite,
            COUNT(DISTINCT et.classe_id) as nb_classes,
            COUNT(et.id) as nb_cours
     FROM personnel p
     JOIN emplois_temps et ON p.id = et.enseignant_id
     WHERE et.matiere_id = ?
     GROUP BY p.id, p.nom, p.prenom, p.specialite
     ORDER BY p.nom, p.prenom",
    [$matiere_id]
)->fetchAll();

// Récupérer les évaluations de cette matière
$evaluations = $database->query(
    "SELECT e.*, c.nom as classe_nom, 
            CONCAT(p.nom, ' ', p.prenom) as enseignant_nom,
            COUNT(n.id) as nb_notes
     FROM evaluations e
     JOIN classes c ON e.classe_id = c.id
     JOIN personnel p ON e.enseignant_id = p.id
     LEFT JOIN notes n ON e.id = n.evaluation_id
     WHERE e.matiere_id = ?
     GROUP BY e.id
     ORDER BY e.date_evaluation DESC
     LIMIT 10",
    [$matiere_id]
)->fetchAll();

// Statistiques générales
$stats = [
    'nb_classes' => count($classes),
    'nb_enseignants' => count($enseignants),
    'nb_evaluations' => count($evaluations),
    'total_cours' => array_sum(array_column($classes, 'nb_cours'))
];

include '../../../includes/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">
        <i class="fas fa-book-open me-2"></i>
        <?php echo htmlspecialchars($matiere['nom']); ?>
    </h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <div class="btn-group me-2">
            <a href="index.php" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-1"></i>
                Retour à la liste
            </a>
        </div>
        <?php if (checkPermission('academic')): ?>
            <div class="btn-group">
                <a href="edit.php?id=<?php echo $matiere_id; ?>" class="btn btn-primary">
                    <i class="fas fa-edit me-1"></i>
                    Modifier
                </a>
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="row">
    <div class="col-lg-8">
        <!-- Informations générales -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-info-circle me-2"></i>
                    Informations générales
                </h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <table class="table table-borderless">
                            <tr>
                                <td><strong>Nom :</strong></td>
                                <td><?php echo htmlspecialchars($matiere['nom']); ?></td>
                            </tr>
                            <tr>
                                <td><strong>Code :</strong></td>
                                <td>
                                    <?php if ($matiere['code']): ?>
                                        <span class="badge bg-secondary"><?php echo htmlspecialchars($matiere['code']); ?></span>
                                    <?php else: ?>
                                        <span class="text-muted">Non défini</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <tr>
                                <td><strong>Niveau :</strong></td>
                                <td>
                                    <?php
                                    $niveau_colors = [
                                        'maternelle' => 'warning',
                                        'primaire' => 'success',
                                        'secondaire' => 'primary'
                                    ];
                                    $color = $niveau_colors[$matiere['niveau']] ?? 'secondary';
                                    ?>
                                    <span class="badge bg-<?php echo $color; ?>">
                                        <?php echo ucfirst($matiere['niveau']); ?>
                                    </span>
                                </td>
                            </tr>
                            <tr>
                                <td><strong>Type :</strong></td>
                                <td>
                                    <span class="badge bg-<?php echo $matiere['type'] === 'obligatoire' ? 'success' : 'warning'; ?>">
                                        <i class="fas fa-<?php echo $matiere['type'] === 'obligatoire' ? 'star' : 'star-half-alt'; ?> me-1"></i>
                                        <?php echo ucfirst($matiere['type']); ?>
                                    </span>
                                </td>
                            </tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <table class="table table-borderless">
                            <tr>
                                <td><strong>Coefficient :</strong></td>
                                <td>
                                    <?php if ($matiere['coefficient']): ?>
                                        <span class="badge bg-info"><?php echo $matiere['coefficient']; ?></span>
                                    <?php else: ?>
                                        <span class="text-muted">Non défini</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <tr>
                                <td><strong>Volume horaire :</strong></td>
                                <td>
                                    <?php if ($matiere['volume_horaire']): ?>
                                        <?php echo $matiere['volume_horaire']; ?>h/semaine
                                    <?php else: ?>
                                        <span class="text-muted">Non défini</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <tr>
                                <td><strong>Créée le :</strong></td>
                                <td><?php echo date('d/m/Y à H:i', strtotime($matiere['created_at'])); ?></td>
                            </tr>
                        </table>
                    </div>
                </div>
                
                <?php if ($matiere['description']): ?>
                    <hr>
                    <h6><i class="fas fa-align-left me-2"></i>Description</h6>
                    <p class="text-muted"><?php echo nl2br(htmlspecialchars($matiere['description'])); ?></p>
                <?php endif; ?>
                
                <?php if ($matiere['objectifs']): ?>
                    <hr>
                    <h6><i class="fas fa-bullseye me-2"></i>Objectifs pédagogiques</h6>
                    <p class="text-muted"><?php echo nl2br(htmlspecialchars($matiere['objectifs'])); ?></p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Classes utilisant cette matière -->
        <?php if (!empty($classes)): ?>
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-users me-2"></i>
                    Classes (<?php echo count($classes); ?>)
                </h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Classe</th>
                                <th>Niveau</th>
                                <th>Section</th>
                                <th>Nb cours</th>
                                <th>Enseignants</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($classes as $classe): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($classe['nom']); ?></strong></td>
                                    <td>
                                        <span class="badge bg-<?php echo $niveau_colors[$classe['niveau']] ?? 'secondary'; ?>">
                                            <?php echo ucfirst($classe['niveau']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo htmlspecialchars($classe['section'] ?? '-'); ?></td>
                                    <td>
                                        <span class="badge bg-primary"><?php echo $classe['nb_cours']; ?></span>
                                    </td>
                                    <td>
                                        <small class="text-muted">
                                            <?php echo htmlspecialchars($classe['enseignants'] ?? 'Aucun'); ?>
                                        </small>
                                    </td>
                                    <td>
                                        <a href="../schedule/class.php?id=<?php echo $classe['id']; ?>" 
                                           class="btn btn-sm btn-outline-primary">
                                            <i class="fas fa-calendar"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Enseignants -->
        <?php if (!empty($enseignants)): ?>
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-chalkboard-teacher me-2"></i>
                    Enseignants (<?php echo count($enseignants); ?>)
                </h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Enseignant</th>
                                <th>Spécialité</th>
                                <th>Classes</th>
                                <th>Cours</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($enseignants as $enseignant): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo htmlspecialchars($enseignant['nom'] . ' ' . $enseignant['prenom']); ?></strong>
                                    </td>
                                    <td>
                                        <?php if ($enseignant['specialite']): ?>
                                            <span class="badge bg-info"><?php echo htmlspecialchars($enseignant['specialite']); ?></span>
                                        <?php else: ?>
                                            <span class="text-muted">Non définie</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge bg-success"><?php echo $enseignant['nb_classes']; ?></span>
                                    </td>
                                    <td>
                                        <span class="badge bg-primary"><?php echo $enseignant['nb_cours']; ?></span>
                                    </td>
                                    <td>
                                        <a href="../../personnel/view.php?id=<?php echo $enseignant['id']; ?>" 
                                           class="btn btn-sm btn-outline-info">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Évaluations récentes -->
        <?php if (!empty($evaluations)): ?>
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-clipboard-check me-2"></i>
                    Évaluations récentes (<?php echo count($evaluations); ?>)
                </h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Évaluation</th>
                                <th>Type</th>
                                <th>Classe</th>
                                <th>Date</th>
                                <th>Notes</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($evaluations as $evaluation): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($evaluation['nom']); ?></strong></td>
                                    <td>
                                        <span class="badge bg-secondary">
                                            <?php echo ucfirst($evaluation['type']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo htmlspecialchars($evaluation['classe_nom']); ?></td>
                                    <td><?php echo date('d/m/Y', strtotime($evaluation['date_evaluation'])); ?></td>
                                    <td>
                                        <span class="badge bg-info"><?php echo $evaluation['nb_notes']; ?></span>
                                    </td>
                                    <td>
                                        <a href="../evaluations/view.php?id=<?php echo $evaluation['id']; ?>" 
                                           class="btn btn-sm btn-outline-primary">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
    
    <div class="col-lg-4">
        <!-- Statistiques -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-chart-bar me-2"></i>
                    Statistiques
                </h5>
            </div>
            <div class="card-body">
                <div class="row text-center">
                    <div class="col-6">
                        <h3 class="text-primary"><?php echo $stats['nb_classes']; ?></h3>
                        <p class="mb-0 small">Classes</p>
                    </div>
                    <div class="col-6">
                        <h3 class="text-success"><?php echo $stats['nb_enseignants']; ?></h3>
                        <p class="mb-0 small">Enseignants</p>
                    </div>
                </div>
                
                <hr>
                
                <div class="row text-center">
                    <div class="col-6">
                        <h3 class="text-info"><?php echo $stats['total_cours']; ?></h3>
                        <p class="mb-0 small">Cours programmés</p>
                    </div>
                    <div class="col-6">
                        <h3 class="text-warning"><?php echo $stats['nb_evaluations']; ?></h3>
                        <p class="mb-0 small">Évaluations</p>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Actions rapides -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-tools me-2"></i>
                    Actions rapides
                </h5>
            </div>
            <div class="card-body">
                <div class="d-grid gap-2">
                    <?php if (checkPermission('academic')): ?>
                        <a href="edit.php?id=<?php echo $matiere_id; ?>" class="btn btn-primary btn-sm">
                            <i class="fas fa-edit me-1"></i>
                            Modifier cette matière
                        </a>
                    <?php endif; ?>
                    
                    <?php if ($stats['nb_classes'] > 0): ?>
                        <a href="../schedule/index.php?matiere_id=<?php echo $matiere_id; ?>" class="btn btn-outline-primary btn-sm">
                            <i class="fas fa-calendar me-1"></i>
                            Voir dans l'emploi du temps
                        </a>
                    <?php endif; ?>
                    
                    <?php if ($stats['nb_evaluations'] > 0): ?>
                        <a href="../evaluations/index.php?matiere_id=<?php echo $matiere_id; ?>" class="btn btn-outline-success btn-sm">
                            <i class="fas fa-clipboard-check me-1"></i>
                            Voir les évaluations
                        </a>
                    <?php endif; ?>
                    
                    <a href="export.php?id=<?php echo $matiere_id; ?>&format=pdf" class="btn btn-outline-secondary btn-sm">
                        <i class="fas fa-file-pdf me-1"></i>
                        Exporter en PDF
                    </a>
                    
                    <?php if (checkPermission('academic')): ?>
                        <hr>
                        <a href="delete.php?id=<?php echo $matiere_id; ?>" 
                           class="btn btn-outline-danger btn-sm btn-delete"
                           data-name="<?php echo htmlspecialchars($matiere['nom']); ?>">
                            <i class="fas fa-trash me-1"></i>
                            Supprimer cette matière
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Confirmation de suppression
document.addEventListener('DOMContentLoaded', function() {
    const deleteButtons = document.querySelectorAll('.btn-delete');
    deleteButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            const name = this.getAttribute('data-name');
            if (confirm(`Êtes-vous sûr de vouloir supprimer la matière "${name}" ?\n\nCette action est irréversible et supprimera également tous les cours et évaluations associés.`)) {
                window.location.href = this.href;
            }
        });
    });
});
</script>

<?php include '../../../includes/footer.php'; ?>
