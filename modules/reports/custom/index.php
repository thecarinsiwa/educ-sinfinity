<?php
/**
 * Module Rapports Personnalisés - Page principale
 * Application de gestion scolaire - République Démocratique du Congo
 */

require_once '../../../config/config.php';
require_once '../../../config/database.php';
require_once '../../../includes/functions.php';

// Vérifier l'authentification
requireLogin();

$page_title = 'Rapports Personnalisés';

// Obtenir l'année scolaire actuelle
$current_year = getCurrentAcademicYear();

// Récupérer les rapports sauvegardés de l'utilisateur
$rapports_sauvegardes = $database->query(
    "SELECT * FROM rapports_personnalises 
     WHERE user_id = ? OR partage = 'public'
     ORDER BY created_at DESC",
    [$_SESSION['user_id']]
)->fetchAll();

// Modèles de rapports prédéfinis
$modeles_rapports = [
    [
        'id' => 'performance_classe',
        'nom' => 'Performance par classe',
        'description' => 'Analyse des résultats scolaires par classe',
        'icone' => 'fas fa-chart-bar',
        'couleur' => 'primary',
        'tables' => ['classes', 'inscriptions', 'evaluations', 'notes'],
        'champs_disponibles' => [
            'classes.nom' => 'Nom de la classe',
            'classes.niveau' => 'Niveau',
            'AVG(notes.note)' => 'Moyenne des notes',
            'COUNT(inscriptions.id)' => 'Nombre d\'élèves',
            'MIN(notes.note)' => 'Note minimale',
            'MAX(notes.note)' => 'Note maximale'
        ]
    ],
    [
        'id' => 'effectifs_detailles',
        'nom' => 'Effectifs détaillés',
        'description' => 'Répartition détaillée des effectifs par critères',
        'icone' => 'fas fa-users',
        'couleur' => 'success',
        'tables' => ['eleves', 'inscriptions', 'classes'],
        'champs_disponibles' => [
            'eleves.nom' => 'Nom de l\'élève',
            'eleves.prenom' => 'Prénom',
            'eleves.sexe' => 'Genre',
            'eleves.date_naissance' => 'Date de naissance',
            'classes.nom' => 'Classe',
            'classes.niveau' => 'Niveau',
            'inscriptions.date_inscription' => 'Date d\'inscription'
        ]
    ],
    [
        'id' => 'finances_detaillees',
        'nom' => 'Finances détaillées',
        'description' => 'Analyse financière approfondie',
        'icone' => 'fas fa-dollar-sign',
        'couleur' => 'warning',
        'tables' => ['paiements', 'frais_scolaires', 'eleves', 'classes'],
        'champs_disponibles' => [
            'paiements.montant' => 'Montant payé',
            'paiements.date_paiement' => 'Date de paiement',
            'paiements.type_frais' => 'Type de frais',
            'frais_scolaires.montant_total' => 'Montant total dû',
            'eleves.nom' => 'Nom de l\'élève',
            'classes.nom' => 'Classe'
        ]
    ],
    [
        'id' => 'personnel_complet',
        'nom' => 'Personnel complet',
        'description' => 'Rapport détaillé sur le personnel',
        'icone' => 'fas fa-chalkboard-teacher',
        'couleur' => 'info',
        'tables' => ['personnel', 'salaires'],
        'champs_disponibles' => [
            'personnel.nom' => 'Nom',
            'personnel.prenom' => 'Prénom',
            'personnel.fonction' => 'Fonction',
            'personnel.date_embauche' => 'Date d\'embauche',
            'personnel.telephone' => 'Téléphone',
            'salaires.montant_base' => 'Salaire de base',
            'salaires.total_net' => 'Salaire net'
        ]
    ]
];

// Statistiques des rapports
$stats_rapports = [];
$stats_rapports['total_rapports'] = count($rapports_sauvegardes);
$stats_rapports['rapports_publics'] = count(array_filter($rapports_sauvegardes, function($r) { return $r['partage'] === 'public'; }));
$stats_rapports['rapports_prives'] = $stats_rapports['total_rapports'] - $stats_rapports['rapports_publics'];

// Rapports les plus utilisés
$rapports_populaires = $database->query(
    "SELECT nom, description, nb_executions, derniere_execution
     FROM rapports_personnalises 
     WHERE (user_id = ? OR partage = 'public') AND nb_executions > 0
     ORDER BY nb_executions DESC
     LIMIT 5",
    [$_SESSION['user_id']]
)->fetchAll();

include '../../../includes/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">
        <i class="fas fa-cogs me-2"></i>
        Rapports Personnalisés
    </h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <div class="btn-group me-2">
            <a href="../index.php" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-1"></i>
                Retour
            </a>
        </div>
        <div class="btn-group me-2">
            <a href="builder.php" class="btn btn-primary">
                <i class="fas fa-plus me-1"></i>
                Créer un rapport
            </a>
        </div>
        <div class="btn-group">
            <button type="button" class="btn btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">
                <i class="fas fa-tools me-1"></i>
                Outils
            </button>
            <ul class="dropdown-menu">
                <li><a class="dropdown-item" href="import.php">
                    <i class="fas fa-file-import me-2"></i>Importer rapport
                </a></li>
                <li><a class="dropdown-item" href="templates.php">
                    <i class="fas fa-file-alt me-2"></i>Gérer modèles
                </a></li>
                <li><a class="dropdown-item" href="scheduler.php">
                    <i class="fas fa-clock me-2"></i>Planifier rapports
                </a></li>
            </ul>
        </div>
    </div>
</div>

<!-- Statistiques rapides -->
<div class="row mb-4">
    <div class="col-lg-4 col-md-6 mb-3">
        <div class="card text-white bg-primary">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h4><?php echo $stats_rapports['total_rapports']; ?></h4>
                        <p class="mb-0">Rapports sauvegardés</p>
                    </div>
                    <div class="align-self-center">
                        <i class="fas fa-file-alt fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-4 col-md-6 mb-3">
        <div class="card text-white bg-success">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h4><?php echo $stats_rapports['rapports_publics']; ?></h4>
                        <p class="mb-0">Rapports partagés</p>
                    </div>
                    <div class="align-self-center">
                        <i class="fas fa-share-alt fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-4 col-md-6 mb-3">
        <div class="card text-white bg-info">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h4><?php echo count($modeles_rapports); ?></h4>
                        <p class="mb-0">Modèles disponibles</p>
                    </div>
                    <div class="align-self-center">
                        <i class="fas fa-layer-group fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modèles de rapports prédéfinis -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-layer-group me-2"></i>
                    Modèles de rapports prédéfinis
                </h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <?php foreach ($modeles_rapports as $modele): ?>
                        <div class="col-lg-3 col-md-6 mb-3">
                            <div class="card h-100 border-0 shadow-sm hover-card">
                                <div class="card-body text-center">
                                    <i class="<?php echo $modele['icone']; ?> fa-3x text-<?php echo $modele['couleur']; ?> mb-3"></i>
                                    <h5 class="card-title"><?php echo $modele['nom']; ?></h5>
                                    <p class="card-text text-muted">
                                        <?php echo $modele['description']; ?>
                                    </p>
                                    <div class="mt-3">
                                        <a href="builder.php?template=<?php echo $modele['id']; ?>" 
                                           class="btn btn-outline-<?php echo $modele['couleur']; ?> btn-sm">
                                            <i class="fas fa-magic me-1"></i>
                                            Utiliser ce modèle
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Contenu principal -->
<div class="row">
    <div class="col-lg-8">
        <!-- Rapports sauvegardés -->
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">
                    <i class="fas fa-save me-2"></i>
                    Mes rapports sauvegardés
                </h5>
                <div class="btn-group btn-group-sm">
                    <button type="button" class="btn btn-outline-secondary" onclick="toggleView('grid')">
                        <i class="fas fa-th"></i>
                    </button>
                    <button type="button" class="btn btn-outline-secondary active" onclick="toggleView('list')">
                        <i class="fas fa-list"></i>
                    </button>
                </div>
            </div>
            <div class="card-body">
                <?php if (!empty($rapports_sauvegardes)): ?>
                    <div id="rapports-list">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Nom du rapport</th>
                                        <th>Description</th>
                                        <th>Créé le</th>
                                        <th>Exécutions</th>
                                        <th>Partage</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($rapports_sauvegardes as $rapport): ?>
                                        <tr>
                                            <td>
                                                <strong><?php echo htmlspecialchars($rapport['nom']); ?></strong>
                                                <?php if ($rapport['user_id'] != $_SESSION['user_id']): ?>
                                                    <br><small class="text-muted">
                                                        <i class="fas fa-share-alt me-1"></i>Partagé
                                                    </small>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <small><?php echo htmlspecialchars($rapport['description']); ?></small>
                                            </td>
                                            <td>
                                                <small><?php echo formatDate($rapport['created_at']); ?></small>
                                            </td>
                                            <td class="text-center">
                                                <span class="badge bg-info"><?php echo $rapport['nb_executions']; ?></span>
                                            </td>
                                            <td>
                                                <span class="badge bg-<?php echo $rapport['partage'] === 'public' ? 'success' : 'secondary'; ?>">
                                                    <?php echo $rapport['partage'] === 'public' ? 'Public' : 'Privé'; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div class="btn-group btn-group-sm">
                                                    <a href="execute.php?id=<?php echo $rapport['id']; ?>" 
                                                       class="btn btn-outline-primary" title="Exécuter">
                                                        <i class="fas fa-play"></i>
                                                    </a>
                                                    <?php if ($rapport['user_id'] == $_SESSION['user_id']): ?>
                                                        <a href="builder.php?edit=<?php echo $rapport['id']; ?>" 
                                                           class="btn btn-outline-secondary" title="Modifier">
                                                            <i class="fas fa-edit"></i>
                                                        </a>
                                                        <a href="duplicate.php?id=<?php echo $rapport['id']; ?>" 
                                                           class="btn btn-outline-info" title="Dupliquer">
                                                            <i class="fas fa-copy"></i>
                                                        </a>
                                                        <button type="button" class="btn btn-outline-danger" 
                                                                onclick="deleteReport(<?php echo $rapport['id']; ?>)" title="Supprimer">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    <?php else: ?>
                                                        <a href="duplicate.php?id=<?php echo $rapport['id']; ?>" 
                                                           class="btn btn-outline-info" title="Copier">
                                                            <i class="fas fa-copy"></i>
                                                        </a>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    
                    <div id="rapports-grid" style="display: none;">
                        <div class="row">
                            <?php foreach ($rapports_sauvegardes as $rapport): ?>
                                <div class="col-md-6 col-lg-4 mb-3">
                                    <div class="card h-100 border-0 shadow-sm">
                                        <div class="card-body">
                                            <h6 class="card-title">
                                                <?php echo htmlspecialchars($rapport['nom']); ?>
                                                <?php if ($rapport['partage'] === 'public'): ?>
                                                    <i class="fas fa-share-alt text-success ms-1" title="Public"></i>
                                                <?php endif; ?>
                                            </h6>
                                            <p class="card-text">
                                                <small class="text-muted">
                                                    <?php echo htmlspecialchars($rapport['description']); ?>
                                                </small>
                                            </p>
                                            <div class="d-flex justify-content-between align-items-center">
                                                <small class="text-muted">
                                                    <?php echo $rapport['nb_executions']; ?> exécutions
                                                </small>
                                                <div class="btn-group btn-group-sm">
                                                    <a href="execute.php?id=<?php echo $rapport['id']; ?>" 
                                                       class="btn btn-primary btn-sm">
                                                        <i class="fas fa-play"></i>
                                                    </a>
                                                    <?php if ($rapport['user_id'] == $_SESSION['user_id']): ?>
                                                        <a href="builder.php?edit=<?php echo $rapport['id']; ?>" 
                                                           class="btn btn-outline-secondary btn-sm">
                                                            <i class="fas fa-edit"></i>
                                                        </a>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="text-center py-5">
                        <i class="fas fa-file-alt fa-4x text-muted mb-3"></i>
                        <h4 class="text-muted">Aucun rapport sauvegardé</h4>
                        <p class="text-muted">Créez votre premier rapport personnalisé pour commencer.</p>
                        <a href="builder.php" class="btn btn-primary">
                            <i class="fas fa-plus me-1"></i>
                            Créer mon premier rapport
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <div class="col-lg-4">
        <!-- Rapports populaires -->
        <?php if (!empty($rapports_populaires)): ?>
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-fire me-2"></i>
                    Rapports populaires
                </h5>
            </div>
            <div class="card-body">
                <?php foreach ($rapports_populaires as $rapport): ?>
                    <div class="d-flex justify-content-between align-items-start mb-3">
                        <div>
                            <strong><?php echo htmlspecialchars($rapport['nom']); ?></strong>
                            <br><small class="text-muted">
                                <?php echo htmlspecialchars(substr($rapport['description'], 0, 50)); ?>
                                <?php echo strlen($rapport['description']) > 50 ? '...' : ''; ?>
                            </small>
                            <br><small class="text-muted">
                                Dernière exécution: <?php echo formatDate($rapport['derniere_execution']); ?>
                            </small>
                        </div>
                        <span class="badge bg-warning"><?php echo $rapport['nb_executions']; ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Guide rapide -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-question-circle me-2"></i>
                    Guide rapide
                </h5>
            </div>
            <div class="card-body">
                <div class="list-group list-group-flush">
                    <div class="list-group-item border-0 px-0">
                        <div class="d-flex align-items-center">
                            <div class="flex-shrink-0">
                                <span class="badge bg-primary rounded-pill">1</span>
                            </div>
                            <div class="flex-grow-1 ms-3">
                                <strong>Choisir un modèle</strong>
                                <br><small class="text-muted">Sélectionnez un modèle prédéfini ou créez de zéro</small>
                            </div>
                        </div>
                    </div>
                    <div class="list-group-item border-0 px-0">
                        <div class="d-flex align-items-center">
                            <div class="flex-shrink-0">
                                <span class="badge bg-success rounded-pill">2</span>
                            </div>
                            <div class="flex-grow-1 ms-3">
                                <strong>Configurer les données</strong>
                                <br><small class="text-muted">Sélectionnez les champs et filtres souhaités</small>
                            </div>
                        </div>
                    </div>
                    <div class="list-group-item border-0 px-0">
                        <div class="d-flex align-items-center">
                            <div class="flex-shrink-0">
                                <span class="badge bg-info rounded-pill">3</span>
                            </div>
                            <div class="flex-grow-1 ms-3">
                                <strong>Personnaliser l'affichage</strong>
                                <br><small class="text-muted">Choisissez le format et la présentation</small>
                            </div>
                        </div>
                    </div>
                    <div class="list-group-item border-0 px-0">
                        <div class="d-flex align-items-center">
                            <div class="flex-shrink-0">
                                <span class="badge bg-warning rounded-pill">4</span>
                            </div>
                            <div class="flex-grow-1 ms-3">
                                <strong>Sauvegarder et partager</strong>
                                <br><small class="text-muted">Enregistrez votre rapport pour le réutiliser</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Actions rapides -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-bolt me-2"></i>
                    Actions rapides
                </h5>
            </div>
            <div class="card-body">
                <div class="d-grid gap-2">
                    <a href="builder.php" class="btn btn-primary">
                        <i class="fas fa-plus me-2"></i>
                        Nouveau rapport
                    </a>
                    <a href="builder.php?template=performance_classe" class="btn btn-outline-primary">
                        <i class="fas fa-chart-bar me-2"></i>
                        Performance des classes
                    </a>
                    <a href="builder.php?template=effectifs_detailles" class="btn btn-outline-success">
                        <i class="fas fa-users me-2"></i>
                        Effectifs détaillés
                    </a>
                    <a href="builder.php?template=finances_detaillees" class="btn btn-outline-warning">
                        <i class="fas fa-dollar-sign me-2"></i>
                        Rapport financier
                    </a>
                    <a href="scheduler.php" class="btn btn-outline-info">
                        <i class="fas fa-clock me-2"></i>
                        Planifier rapports
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function toggleView(viewType) {
    const listView = document.getElementById('rapports-list');
    const gridView = document.getElementById('rapports-grid');
    const buttons = document.querySelectorAll('.btn-group .btn');
    
    buttons.forEach(btn => btn.classList.remove('active'));
    
    if (viewType === 'grid') {
        listView.style.display = 'none';
        gridView.style.display = 'block';
        document.querySelector('button[onclick="toggleView(\'grid\')"]').classList.add('active');
    } else {
        listView.style.display = 'block';
        gridView.style.display = 'none';
        document.querySelector('button[onclick="toggleView(\'list\')"]').classList.add('active');
    }
}

function deleteReport(reportId) {
    if (confirm('Êtes-vous sûr de vouloir supprimer ce rapport ?')) {
        fetch('delete.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({id: reportId})
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                alert('Erreur lors de la suppression du rapport');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Erreur lors de la suppression du rapport');
        });
    }
}
</script>

<style>
.hover-card {
    transition: transform 0.2s ease-in-out;
}

.hover-card:hover {
    transform: translateY(-5px);
}

.list-group-item {
    border: none !important;
}
</style>

<?php include '../../../includes/footer.php'; ?>
