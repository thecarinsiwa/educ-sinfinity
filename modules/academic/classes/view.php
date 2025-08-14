<?php
/**
 * Module de gestion académique - Voir les détails d'une classe
 * Application de gestion scolaire - République Démocratique du Congo
 */

require_once '../../../config/config.php';
require_once '../../../config/database.php';
require_once '../../../includes/functions.php';

// Vérifier l'authentification et les permissions
requireLogin();
if (!checkPermission('academic') && !checkPermission('academic_view')) {
    showMessage('error', 'Accès refusé à ce module.');
    redirectTo('index.php');
}

// Récupérer l'ID de la classe
$id = (int)($_GET['id'] ?? 0);
if (!$id) {
    showMessage('error', 'Classe non spécifiée.');
    redirectTo('index.php');
}

// Récupérer les informations de la classe
$sql = "SELECT c.*, 
               a.annee as annee_scolaire,
               p.nom as titulaire_nom, p.prenom as titulaire_prenom, p.telephone as titulaire_tel
        FROM classes c 
        LEFT JOIN annees_scolaires a ON c.annee_scolaire_id = a.id
        LEFT JOIN personnel p ON c.titulaire_id = p.id
        WHERE c.id = ?";

$classe = $database->query($sql, [$id])->fetch();

if (!$classe) {
    showMessage('error', 'Classe non trouvée.');
    redirectTo('index.php');
}

$page_title = 'Classe - ' . $classe['nom'];

// Récupérer les élèves de la classe
$eleves = $database->query(
    "SELECT e.*, i.date_inscription, i.status as inscription_status
     FROM inscriptions i 
     JOIN eleves e ON i.eleve_id = e.id 
     WHERE i.classe_id = ? AND i.status = 'inscrit'
     ORDER BY e.nom, e.prenom",
    [$id]
)->fetchAll();

// Récupérer l'emploi du temps de la classe
$emploi_temps = $database->query(
    "SELECT et.*, 
            m.nom as matiere_nom, m.coefficient,
            p.nom as enseignant_nom, p.prenom as enseignant_prenom
     FROM emplois_temps et
     JOIN matieres m ON et.matiere_id = m.id
     LEFT JOIN personnel p ON et.enseignant_id = p.id
     WHERE et.classe_id = ?
     ORDER BY 
        CASE et.jour_semaine 
            WHEN 'lundi' THEN 1 
            WHEN 'mardi' THEN 2 
            WHEN 'mercredi' THEN 3 
            WHEN 'jeudi' THEN 4 
            WHEN 'vendredi' THEN 5 
            WHEN 'samedi' THEN 6 
            ELSE 7 
        END, et.heure_debut",
    [$id]
)->fetchAll();

// Statistiques de la classe
$stats = [
    'nb_eleves' => count($eleves),
    'nb_filles' => count(array_filter($eleves, fn($e) => $e['sexe'] === 'F')),
    'nb_garcons' => count(array_filter($eleves, fn($e) => $e['sexe'] === 'M')),
    'nb_matieres' => count(array_unique(array_column($emploi_temps, 'matiere_id'))),
    'nb_enseignants' => count(array_unique(array_filter(array_column($emploi_temps, 'enseignant_id')))),
    'age_moyen' => 0
];

// Calcul de l'âge moyen
if (!empty($eleves)) {
    $ages = array_map(function($eleve) {
        return $eleve['date_naissance'] ? calculateAge($eleve['date_naissance']) : 0;
    }, $eleves);
    $stats['age_moyen'] = round(array_sum($ages) / count($ages), 1);
}

include '../../../includes/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">
        <i class="fas fa-school me-2"></i>
        <?php echo htmlspecialchars($classe['nom']); ?>
        <span class="badge bg-<?php 
            echo $classe['niveau'] === 'maternelle' ? 'warning' : 
                ($classe['niveau'] === 'primaire' ? 'success' : 'primary'); 
        ?> ms-2">
            <?php echo ucfirst($classe['niveau']); ?>
        </span>
    </h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <div class="btn-group me-2">
            <a href="index.php" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-1"></i>
                Retour
            </a>
            <?php if (checkPermission('academic')): ?>
                <a href="edit.php?id=<?php echo $classe['id']; ?>" class="btn btn-primary">
                    <i class="fas fa-edit me-1"></i>
                    Modifier
                </a>
            <?php endif; ?>
        </div>
        <div class="btn-group">
            <button type="button" class="btn btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">
                <i class="fas fa-ellipsis-v me-1"></i>
                Actions
            </button>
            <ul class="dropdown-menu">
                <li><a class="dropdown-item" href="../schedule/class.php?id=<?php echo $classe['id']; ?>">
                    <i class="fas fa-calendar me-2"></i>Emploi du temps
                </a></li>
                <li><a class="dropdown-item" href="../../students/index.php?classe=<?php echo $classe['id']; ?>">
                    <i class="fas fa-users me-2"></i>Gérer les élèves
                </a></li>
                <li><a class="dropdown-item" href="../../evaluations/class.php?id=<?php echo $classe['id']; ?>">
                    <i class="fas fa-chart-line me-2"></i>Évaluations
                </a></li>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item" href="#" onclick="printElement('class-details')">
                    <i class="fas fa-print me-2"></i>Imprimer
                </a></li>
            </ul>
        </div>
    </div>
</div>

<div id="class-details">
    <div class="row">
        <!-- Informations principales -->
        <div class="col-lg-8">
            <!-- Détails de la classe -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-info-circle me-2"></i>
                        Informations de la classe
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <table class="table table-borderless">
                                <tr>
                                    <td class="fw-bold">Nom :</td>
                                    <td><?php echo htmlspecialchars($classe['nom']); ?></td>
                                </tr>
                                <tr>
                                    <td class="fw-bold">Niveau :</td>
                                    <td>
                                        <span class="badge bg-<?php 
                                            echo $classe['niveau'] === 'maternelle' ? 'warning' : 
                                                ($classe['niveau'] === 'primaire' ? 'success' : 'primary'); 
                                        ?>">
                                            <?php echo ucfirst($classe['niveau']); ?>
                                        </span>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="fw-bold">Année scolaire :</td>
                                    <td><?php echo htmlspecialchars($classe['annee_scolaire']); ?></td>
                                </tr>
                                <tr>
                                    <td class="fw-bold">Salle :</td>
                                    <td>
                                        <?php if ($classe['salle']): ?>
                                            <i class="fas fa-map-marker-alt me-1"></i>
                                            <?php echo htmlspecialchars($classe['salle']); ?>
                                        <?php else: ?>
                                            <span class="text-muted">Non définie</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            </table>
                        </div>
                        <div class="col-md-6">
                            <table class="table table-borderless">
                                <tr>
                                    <td class="fw-bold">Capacité maximale :</td>
                                    <td>
                                        <?php if ($classe['capacite_max']): ?>
                                            <?php echo $classe['capacite_max']; ?> élèves
                                            <?php 
                                            $occupation = ($stats['nb_eleves'] / $classe['capacite_max']) * 100;
                                            $color = $occupation > 90 ? 'danger' : ($occupation > 75 ? 'warning' : 'success');
                                            ?>
                                            <div class="progress mt-1" style="height: 6px;">
                                                <div class="progress-bar bg-<?php echo $color; ?>" 
                                                     style="width: <?php echo min(100, $occupation); ?>%"></div>
                                            </div>
                                            <small class="text-muted"><?php echo round($occupation); ?>% d'occupation</small>
                                        <?php else: ?>
                                            <span class="text-muted">Non définie</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="fw-bold">Titulaire :</td>
                                    <td>
                                        <?php if ($classe['titulaire_nom']): ?>
                                            <strong><?php echo htmlspecialchars($classe['titulaire_nom'] . ' ' . $classe['titulaire_prenom']); ?></strong>
                                            <?php if ($classe['titulaire_tel']): ?>
                                                <br><small class="text-muted">
                                                    <i class="fas fa-phone fa-xs"></i>
                                                    <?php echo htmlspecialchars($classe['titulaire_tel']); ?>
                                                </small>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="text-muted">Aucun titulaire assigné</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            </table>
                        </div>
                    </div>
                    
                    <?php if ($classe['description']): ?>
                        <div class="mt-3">
                            <h6>Description :</h6>
                            <p class="text-muted"><?php echo nl2br(htmlspecialchars($classe['description'])); ?></p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Liste des élèves -->
            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        <i class="fas fa-users me-2"></i>
                        Élèves inscrits (<?php echo $stats['nb_eleves']; ?>)
                    </h5>
                    <?php if (checkPermission('students')): ?>
                        <a href="../../students/add.php?classe_id=<?php echo $classe['id']; ?>" 
                           class="btn btn-sm btn-primary">
                            <i class="fas fa-plus me-1"></i>
                            Inscrire un élève
                        </a>
                    <?php endif; ?>
                </div>
                <div class="card-body">
                    <?php if (!empty($eleves)): ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Matricule</th>
                                        <th>Nom complet</th>
                                        <th>Sexe</th>
                                        <th>Âge</th>
                                        <th>Date inscription</th>
                                        <th class="no-print">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($eleves as $eleve): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($eleve['numero_matricule']); ?></td>
                                            <td>
                                                <strong><?php echo htmlspecialchars($eleve['nom'] . ' ' . $eleve['prenom']); ?></strong>
                                            </td>
                                            <td>
                                                <i class="fas fa-<?php echo $eleve['sexe'] === 'M' ? 'mars text-primary' : 'venus text-pink'; ?>"></i>
                                                <?php echo $eleve['sexe'] === 'M' ? 'M' : 'F'; ?>
                                            </td>
                                            <td>
                                                <?php echo $eleve['date_naissance'] ? calculateAge($eleve['date_naissance']) : '-'; ?> ans
                                            </td>
                                            <td><?php echo formatDate($eleve['date_inscription']); ?></td>
                                            <td class="no-print">
                                                <div class="btn-group btn-group-sm">
                                                    <a href="../../students/view.php?id=<?php echo $eleve['id']; ?>" 
                                                       class="btn btn-outline-info" 
                                                       title="Voir détails">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                    <?php if (checkPermission('evaluations')): ?>
                                                        <a href="../../evaluations/student.php?id=<?php echo $eleve['id']; ?>" 
                                                           class="btn btn-outline-success" 
                                                           title="Notes">
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
                    <?php else: ?>
                        <div class="text-center py-4">
                            <i class="fas fa-user-plus fa-3x text-muted mb-3"></i>
                            <h6 class="text-muted">Aucun élève inscrit</h6>
                            <p class="text-muted">Cette classe n'a encore aucun élève inscrit.</p>
                            <?php if (checkPermission('students')): ?>
                                <a href="../../students/add.php?classe_id=<?php echo $classe['id']; ?>" 
                                   class="btn btn-primary">
                                    <i class="fas fa-plus me-1"></i>
                                    Inscrire le premier élève
                                </a>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Emploi du temps -->
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        <i class="fas fa-calendar-alt me-2"></i>
                        Emploi du temps
                    </h5>
                    <?php if (checkPermission('academic')): ?>
                        <a href="../schedule/class.php?id=<?php echo $classe['id']; ?>" 
                           class="btn btn-sm btn-warning">
                            <i class="fas fa-edit me-1"></i>
                            Gérer l'emploi du temps
                        </a>
                    <?php endif; ?>
                </div>
                <div class="card-body">
                    <?php if (!empty($emploi_temps)): ?>
                        <div class="table-responsive">
                            <table class="table table-sm table-bordered">
                                <thead class="table-dark">
                                    <tr>
                                        <th>Jour</th>
                                        <th>Heure</th>
                                        <th>Matière</th>
                                        <th>Enseignant</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($emploi_temps as $cours): ?>
                                        <tr>
                                            <td><?php echo ucfirst($cours['jour_semaine']); ?></td>
                                            <td>
                                                <?php echo substr($cours['heure_debut'], 0, 5); ?> - 
                                                <?php echo substr($cours['heure_fin'], 0, 5); ?>
                                            </td>
                                            <td>
                                                <strong><?php echo htmlspecialchars($cours['matiere_nom']); ?></strong>
                                                <?php if ($cours['coefficient']): ?>
                                                    <small class="text-muted">(Coef. <?php echo $cours['coefficient']; ?>)</small>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($cours['enseignant_nom']): ?>
                                                    <?php echo htmlspecialchars($cours['enseignant_nom'] . ' ' . $cours['enseignant_prenom']); ?>
                                                <?php else: ?>
                                                    <span class="text-muted">Non assigné</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-4">
                            <i class="fas fa-calendar-times fa-3x text-muted mb-3"></i>
                            <h6 class="text-muted">Aucun emploi du temps configuré</h6>
                            <p class="text-muted">L'emploi du temps de cette classe n'a pas encore été défini.</p>
                            <?php if (checkPermission('academic')): ?>
                                <a href="../schedule/class.php?id=<?php echo $classe['id']; ?>" 
                                   class="btn btn-warning">
                                    <i class="fas fa-plus me-1"></i>
                                    Créer l'emploi du temps
                                </a>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Sidebar avec statistiques -->
        <div class="col-lg-4">
            <!-- Statistiques de la classe -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-chart-pie me-2"></i>
                        Statistiques
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row text-center">
                        <div class="col-6 mb-3">
                            <div class="border-end">
                                <h3 class="text-primary"><?php echo $stats['nb_eleves']; ?></h3>
                                <small class="text-muted">Élèves</small>
                            </div>
                        </div>
                        <div class="col-6 mb-3">
                            <h3 class="text-success"><?php echo $stats['nb_matieres']; ?></h3>
                            <small class="text-muted">Matières</small>
                        </div>
                        <div class="col-6">
                            <div class="border-end">
                                <h3 class="text-warning"><?php echo $stats['nb_enseignants']; ?></h3>
                                <small class="text-muted">Enseignants</small>
                            </div>
                        </div>
                        <div class="col-6">
                            <h3 class="text-info"><?php echo $stats['age_moyen']; ?></h3>
                            <small class="text-muted">Âge moyen</small>
                        </div>
                    </div>
                    
                    <?php if ($stats['nb_eleves'] > 0): ?>
                        <hr>
                        <div class="row text-center">
                            <div class="col-6">
                                <h5 class="text-primary"><?php echo $stats['nb_garcons']; ?></h5>
                                <small class="text-muted">
                                    <i class="fas fa-mars"></i> Garçons
                                </small>
                            </div>
                            <div class="col-6">
                                <h5 class="text-pink"><?php echo $stats['nb_filles']; ?></h5>
                                <small class="text-muted">
                                    <i class="fas fa-venus"></i> Filles
                                </small>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Actions rapides -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-bolt me-2"></i>
                        Actions rapides
                    </h5>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <?php if (checkPermission('students')): ?>
                            <a href="../../students/add.php?classe_id=<?php echo $classe['id']; ?>" 
                               class="btn btn-outline-primary">
                                <i class="fas fa-user-plus me-2"></i>
                                Inscrire un élève
                            </a>
                        <?php endif; ?>
                        
                        <?php if (checkPermission('academic')): ?>
                            <a href="../schedule/class.php?id=<?php echo $classe['id']; ?>" 
                               class="btn btn-outline-warning">
                                <i class="fas fa-calendar me-2"></i>
                                Emploi du temps
                            </a>
                        <?php endif; ?>
                        
                        <?php if (checkPermission('evaluations')): ?>
                            <a href="../../evaluations/class.php?id=<?php echo $classe['id']; ?>" 
                               class="btn btn-outline-success">
                                <i class="fas fa-chart-line me-2"></i>
                                Évaluations
                            </a>
                        <?php endif; ?>
                        
                        <button onclick="printElement('class-details')" 
                                class="btn btn-outline-secondary">
                            <i class="fas fa-print me-2"></i>
                            Imprimer la fiche
                        </button>
                    </div>
                </div>
            </div>
            
            <!-- Informations système -->
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-info-circle me-2"></i>
                        Informations système
                    </h5>
                </div>
                <div class="card-body">
                    <small class="text-muted">
                        <strong>Créée le :</strong> <?php echo formatDate($classe['created_at']); ?><br>
                        <strong>Dernière modification :</strong> <?php echo formatDate($classe['updated_at']); ?><br>
                        <strong>ID système :</strong> #<?php echo $classe['id']; ?>
                    </small>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../../../includes/footer.php'; ?>
