<?php
/**
 * Module Gestion des Élèves - Recherche avancée
 * Application de gestion scolaire - République Démocratique du Congo
 */

require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../includes/functions.php';

// Vérifier l'authentification et les permissions
requireLogin();
if (!checkPermission('students') && !checkPermission('students_view')) {
    showMessage('error', 'Accès refusé à ce module.');
    redirectTo('../../dashboard.php');
}

$page_title = 'Recherche Avancée d\'Élèves';

// Obtenir l'année scolaire actuelle
$current_year = getCurrentAcademicYear();

// Paramètres de recherche avancée
$search_performed = false;
$results = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' || !empty($_GET['search'])) {
    $search_performed = true;
    
    // Récupérer les critères de recherche
    $nom = sanitizeInput($_POST['nom'] ?? $_GET['nom'] ?? '');
    $prenom = sanitizeInput($_POST['prenom'] ?? $_GET['prenom'] ?? '');
    $matricule = sanitizeInput($_POST['matricule'] ?? $_GET['matricule'] ?? '');
    $classe_id = sanitizeInput($_POST['classe_id'] ?? $_GET['classe_id'] ?? '');
    $niveau = sanitizeInput($_POST['niveau'] ?? $_GET['niveau'] ?? '');
    $sexe = sanitizeInput($_POST['sexe'] ?? $_GET['sexe'] ?? '');
    $age_min = intval($_POST['age_min'] ?? $_GET['age_min'] ?? 0);
    $age_max = intval($_POST['age_max'] ?? $_GET['age_max'] ?? 0);
    $date_naissance_debut = sanitizeInput($_POST['date_naissance_debut'] ?? $_GET['date_naissance_debut'] ?? '');
    $date_naissance_fin = sanitizeInput($_POST['date_naissance_fin'] ?? $_GET['date_naissance_fin'] ?? '');
    $lieu_naissance = sanitizeInput($_POST['lieu_naissance'] ?? $_GET['lieu_naissance'] ?? '');
    $nom_pere = sanitizeInput($_POST['nom_pere'] ?? $_GET['nom_pere'] ?? '');
    $nom_mere = sanitizeInput($_POST['nom_mere'] ?? $_GET['nom_mere'] ?? '');
    $adresse = sanitizeInput($_POST['adresse'] ?? $_GET['adresse'] ?? '');
    $telephone = sanitizeInput($_POST['telephone'] ?? $_GET['telephone'] ?? '');
    $status = sanitizeInput($_POST['status'] ?? $_GET['status'] ?? '');
    $date_inscription_debut = sanitizeInput($_POST['date_inscription_debut'] ?? $_GET['date_inscription_debut'] ?? '');
    $date_inscription_fin = sanitizeInput($_POST['date_inscription_fin'] ?? $_GET['date_inscription_fin'] ?? '');
    
    // Construction de la requête
    $where_conditions = ["i.annee_scolaire_id = ?"];
    $params = [$current_year['id'] ?? 0];
    
    if ($nom) {
        $where_conditions[] = "e.nom LIKE ?";
        $params[] = "%$nom%";
    }
    
    if ($prenom) {
        $where_conditions[] = "e.prenom LIKE ?";
        $params[] = "%$prenom%";
    }
    
    if ($matricule) {
        $where_conditions[] = "e.numero_matricule LIKE ?";
        $params[] = "%$matricule%";
    }
    
    if ($classe_id) {
        $where_conditions[] = "c.id = ?";
        $params[] = $classe_id;
    }
    
    if ($niveau) {
        $where_conditions[] = "c.niveau = ?";
        $params[] = $niveau;
    }
    
    if ($sexe) {
        $where_conditions[] = "e.sexe = ?";
        $params[] = $sexe;
    }
    
    if ($age_min > 0) {
        $where_conditions[] = "TIMESTAMPDIFF(YEAR, e.date_naissance, CURDATE()) >= ?";
        $params[] = $age_min;
    }
    
    if ($age_max > 0) {
        $where_conditions[] = "TIMESTAMPDIFF(YEAR, e.date_naissance, CURDATE()) <= ?";
        $params[] = $age_max;
    }
    
    if ($date_naissance_debut) {
        $where_conditions[] = "e.date_naissance >= ?";
        $params[] = $date_naissance_debut;
    }
    
    if ($date_naissance_fin) {
        $where_conditions[] = "e.date_naissance <= ?";
        $params[] = $date_naissance_fin;
    }
    
    if ($lieu_naissance) {
        $where_conditions[] = "e.lieu_naissance LIKE ?";
        $params[] = "%$lieu_naissance%";
    }
    
    if ($nom_pere) {
        $where_conditions[] = "e.nom_pere LIKE ?";
        $params[] = "%$nom_pere%";
    }
    
    if ($nom_mere) {
        $where_conditions[] = "e.nom_mere LIKE ?";
        $params[] = "%$nom_mere%";
    }
    
    if ($adresse) {
        $where_conditions[] = "e.adresse LIKE ?";
        $params[] = "%$adresse%";
    }
    
    if ($telephone) {
        $where_conditions[] = "(e.telephone LIKE ? OR e.telephone_urgence LIKE ?)";
        $params[] = "%$telephone%";
        $params[] = "%$telephone%";
    }
    
    if ($status) {
        $where_conditions[] = "i.status = ?";
        $params[] = $status;
    }
    
    if ($date_inscription_debut) {
        $where_conditions[] = "i.date_inscription >= ?";
        $params[] = $date_inscription_debut;
    }
    
    if ($date_inscription_fin) {
        $where_conditions[] = "i.date_inscription <= ?";
        $params[] = $date_inscription_fin;
    }
    
    $where_clause = implode(' AND ', $where_conditions);
    
    // Exécuter la recherche
    try {
        $results = $database->query(
            "SELECT e.*, i.status as inscription_status, i.date_inscription,
                    c.nom as classe_nom, c.niveau, c.section,
                    TIMESTAMPDIFF(YEAR, e.date_naissance, CURDATE()) as age
             FROM eleves e
             JOIN inscriptions i ON e.id = i.eleve_id
             JOIN classes c ON i.classe_id = c.id
             WHERE $where_clause
             ORDER BY e.nom, e.prenom
             LIMIT 100",
            $params
        )->fetchAll();
    } catch (Exception $e) {
        $results = [];
        showMessage('error', 'Erreur lors de la recherche: ' . $e->getMessage());
    }
}

// Récupérer les classes pour les filtres
try {
    $classes = $database->query(
        "SELECT DISTINCT c.id, c.nom, c.niveau, c.section
         FROM classes c
         JOIN inscriptions i ON c.id = i.classe_id
         WHERE i.annee_scolaire_id = ?
         ORDER BY c.niveau, c.nom",
        [$current_year['id'] ?? 0]
    )->fetchAll();
} catch (Exception $e) {
    $classes = [];
}

include '../../includes/header.php';
?>

<!-- Styles CSS modernes -->
<style>
.search-header {
    background: linear-gradient(135deg, #17a2b8 0%, #6f42c1 100%);
    color: white;
    padding: 2rem 0;
    margin: -20px -15px 30px -15px;
    border-radius: 0 0 20px 20px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.1);
}

.search-header h1 {
    font-weight: 300;
    font-size: 2.5rem;
    margin-bottom: 0.5rem;
}

.search-header .subtitle {
    opacity: 0.9;
    font-size: 1.1rem;
}

.search-card {
    background: white;
    border-radius: 15px;
    padding: 2rem;
    box-shadow: 0 5px 15px rgba(0,0,0,0.08);
    margin-bottom: 2rem;
}

.form-section {
    background: #f8f9fa;
    border-radius: 10px;
    padding: 1.5rem;
    margin-bottom: 1.5rem;
    border-left: 4px solid #17a2b8;
}

.form-section h6 {
    color: #17a2b8;
    font-weight: 600;
    margin-bottom: 1rem;
}

.results-card {
    background: white;
    border-radius: 15px;
    padding: 1.5rem;
    box-shadow: 0 5px 15px rgba(0,0,0,0.08);
    margin-bottom: 1.5rem;
}

.student-result {
    background: #f8f9fa;
    border-radius: 10px;
    padding: 1rem;
    margin-bottom: 1rem;
    border-left: 4px solid #28a745;
    transition: all 0.3s ease;
}

.student-result:hover {
    background: #e9ecef;
    transform: translateX(5px);
}

.btn-modern {
    border-radius: 25px;
    padding: 0.75rem 2rem;
    font-weight: 600;
    transition: all 0.3s ease;
    border: none;
    box-shadow: 0 3px 15px rgba(0,0,0,0.1);
}

.btn-modern:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 25px rgba(0,0,0,0.2);
}

.search-stats {
    background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
    color: white;
    border-radius: 10px;
    padding: 1rem;
    margin-bottom: 1.5rem;
}

@keyframes fadeInUp {
    from {
        opacity: 0;
        transform: translateY(30px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.animate-fade-in {
    animation: fadeInUp 0.6s ease-out;
}

.animate-delay-1 { animation-delay: 0.1s; }
.animate-delay-2 { animation-delay: 0.2s; }
.animate-delay-3 { animation-delay: 0.3s; }

@media (max-width: 768px) {
    .search-header {
        margin: -20px -15px 20px -15px;
        padding: 1.5rem 0;
    }

    .search-header h1 {
        font-size: 2rem;
    }

    .search-card, .results-card {
        padding: 1rem;
    }

    .form-section {
        padding: 1rem;
    }
}
</style>

<!-- En-tête moderne -->
<div class="search-header">
    <div class="container-fluid">
        <div class="row align-items-center">
            <div class="col-md-8">
                <h1 class="animate-fade-in">
                    <i class="fas fa-search-plus me-3"></i>
                    Recherche Avancée
                </h1>
                <p class="subtitle animate-fade-in animate-delay-1">
                    Recherche détaillée dans la base de données des élèves
                </p>
            </div>
            <div class="col-md-4 text-end">
                <div class="animate-fade-in animate-delay-2">
                    <a href="list.php" class="btn btn-light btn-modern me-2">
                        <i class="fas fa-list me-2"></i>
                        Liste simple
                    </a>
                    <a href="index.php" class="btn btn-outline-light btn-modern">
                        <i class="fas fa-arrow-left me-2"></i>
                        Retour
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Formulaire de recherche avancée -->
<div class="search-card animate-fade-in animate-delay-1">
    <form method="POST" id="searchForm">
        <div class="row">
            <div class="col-12">
                <h5 class="mb-4">
                    <i class="fas fa-filter me-2"></i>
                    Critères de recherche
                </h5>
            </div>
        </div>

        <!-- Informations personnelles -->
        <div class="form-section">
            <h6><i class="fas fa-user me-2"></i>Informations personnelles</h6>
            <div class="row">
                <div class="col-md-4 mb-3">
                    <label for="nom" class="form-label">Nom</label>
                    <input type="text" class="form-control" id="nom" name="nom"
                           value="<?php echo htmlspecialchars($nom ?? ''); ?>"
                           placeholder="Nom de famille">
                </div>
                <div class="col-md-4 mb-3">
                    <label for="prenom" class="form-label">Prénom</label>
                    <input type="text" class="form-control" id="prenom" name="prenom"
                           value="<?php echo htmlspecialchars($prenom ?? ''); ?>"
                           placeholder="Prénom">
                </div>
                <div class="col-md-4 mb-3">
                    <label for="matricule" class="form-label">Numéro de matricule</label>
                    <input type="text" class="form-control" id="matricule" name="matricule"
                           value="<?php echo htmlspecialchars($matricule ?? ''); ?>"
                           placeholder="Matricule">
                </div>
                <div class="col-md-3 mb-3">
                    <label for="sexe" class="form-label">Sexe</label>
                    <select class="form-select" id="sexe" name="sexe">
                        <option value="">Tous</option>
                        <option value="M" <?php echo ($sexe ?? '') === 'M' ? 'selected' : ''; ?>>Masculin</option>
                        <option value="F" <?php echo ($sexe ?? '') === 'F' ? 'selected' : ''; ?>>Féminin</option>
                    </select>
                </div>
                <div class="col-md-3 mb-3">
                    <label for="age_min" class="form-label">Âge minimum</label>
                    <input type="number" class="form-control" id="age_min" name="age_min"
                           value="<?php echo $age_min ?? ''; ?>" min="0" max="25">
                </div>
                <div class="col-md-3 mb-3">
                    <label for="age_max" class="form-label">Âge maximum</label>
                    <input type="number" class="form-control" id="age_max" name="age_max"
                           value="<?php echo $age_max ?? ''; ?>" min="0" max="25">
                </div>
                <div class="col-md-3 mb-3">
                    <label for="lieu_naissance" class="form-label">Lieu de naissance</label>
                    <input type="text" class="form-control" id="lieu_naissance" name="lieu_naissance"
                           value="<?php echo htmlspecialchars($lieu_naissance ?? ''); ?>"
                           placeholder="Ville/Province">
                </div>
            </div>
        </div>

        <!-- Dates de naissance -->
        <div class="form-section">
            <h6><i class="fas fa-calendar me-2"></i>Période de naissance</h6>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="date_naissance_debut" class="form-label">Date de naissance (début)</label>
                    <input type="date" class="form-control" id="date_naissance_debut" name="date_naissance_debut"
                           value="<?php echo $date_naissance_debut ?? ''; ?>">
                </div>
                <div class="col-md-6 mb-3">
                    <label for="date_naissance_fin" class="form-label">Date de naissance (fin)</label>
                    <input type="date" class="form-control" id="date_naissance_fin" name="date_naissance_fin"
                           value="<?php echo $date_naissance_fin ?? ''; ?>">
                </div>
            </div>
        </div>

        <!-- Informations scolaires -->
        <div class="form-section">
            <h6><i class="fas fa-school me-2"></i>Informations scolaires</h6>
            <div class="row">
                <div class="col-md-4 mb-3">
                    <label for="classe_id" class="form-label">Classe</label>
                    <select class="form-select" id="classe_id" name="classe_id">
                        <option value="">Toutes les classes</option>
                        <?php foreach ($classes as $classe): ?>
                            <option value="<?php echo $classe['id']; ?>"
                                    <?php echo ($classe_id ?? '') == $classe['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($classe['nom'] . ' (' . $classe['niveau'] . ')'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4 mb-3">
                    <label for="niveau" class="form-label">Niveau</label>
                    <select class="form-select" id="niveau" name="niveau">
                        <option value="">Tous les niveaux</option>
                        <option value="maternelle" <?php echo ($niveau ?? '') === 'maternelle' ? 'selected' : ''; ?>>Maternelle</option>
                        <option value="primaire" <?php echo ($niveau ?? '') === 'primaire' ? 'selected' : ''; ?>>Primaire</option>
                        <option value="secondaire" <?php echo ($niveau ?? '') === 'secondaire' ? 'selected' : ''; ?>>Secondaire</option>
                    </select>
                </div>
                <div class="col-md-4 mb-3">
                    <label for="status" class="form-label">Statut d'inscription</label>
                    <select class="form-select" id="status" name="status">
                        <option value="">Tous les statuts</option>
                        <option value="inscrit" <?php echo ($status ?? '') === 'inscrit' ? 'selected' : ''; ?>>Inscrit</option>
                        <option value="suspendu" <?php echo ($status ?? '') === 'suspendu' ? 'selected' : ''; ?>>Suspendu</option>
                        <option value="transfere" <?php echo ($status ?? '') === 'transfere' ? 'selected' : ''; ?>>Transféré</option>
                        <option value="abandonne" <?php echo ($status ?? '') === 'abandonne' ? 'selected' : ''; ?>>Abandonné</option>
                    </select>
                </div>
            </div>
        </div>

        <!-- Dates d'inscription -->
        <div class="form-section">
            <h6><i class="fas fa-calendar-plus me-2"></i>Période d'inscription</h6>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="date_inscription_debut" class="form-label">Date d'inscription (début)</label>
                    <input type="date" class="form-control" id="date_inscription_debut" name="date_inscription_debut"
                           value="<?php echo $date_inscription_debut ?? ''; ?>">
                </div>
                <div class="col-md-6 mb-3">
                    <label for="date_inscription_fin" class="form-label">Date d'inscription (fin)</label>
                    <input type="date" class="form-control" id="date_inscription_fin" name="date_inscription_fin"
                           value="<?php echo $date_inscription_fin ?? ''; ?>">
                </div>
            </div>
        </div>

        <!-- Informations familiales -->
        <div class="form-section">
            <h6><i class="fas fa-home me-2"></i>Informations familiales et contact</h6>
            <div class="row">
                <div class="col-md-4 mb-3">
                    <label for="nom_pere" class="form-label">Nom du père</label>
                    <input type="text" class="form-control" id="nom_pere" name="nom_pere"
                           value="<?php echo htmlspecialchars($nom_pere ?? ''); ?>"
                           placeholder="Nom du père">
                </div>
                <div class="col-md-4 mb-3">
                    <label for="nom_mere" class="form-label">Nom de la mère</label>
                    <input type="text" class="form-control" id="nom_mere" name="nom_mere"
                           value="<?php echo htmlspecialchars($nom_mere ?? ''); ?>"
                           placeholder="Nom de la mère">
                </div>
                <div class="col-md-4 mb-3">
                    <label for="telephone" class="form-label">Téléphone</label>
                    <input type="text" class="form-control" id="telephone" name="telephone"
                           value="<?php echo htmlspecialchars($telephone ?? ''); ?>"
                           placeholder="Numéro de téléphone">
                </div>
                <div class="col-md-12 mb-3">
                    <label for="adresse" class="form-label">Adresse</label>
                    <input type="text" class="form-control" id="adresse" name="adresse"
                           value="<?php echo htmlspecialchars($adresse ?? ''); ?>"
                           placeholder="Adresse complète">
                </div>
            </div>
        </div>

        <!-- Boutons d'action -->
        <div class="row">
            <div class="col-12 text-center">
                <button type="submit" class="btn btn-primary btn-modern me-3">
                    <i class="fas fa-search me-2"></i>
                    Rechercher
                </button>
                <button type="button" class="btn btn-secondary btn-modern me-3" onclick="clearForm()">
                    <i class="fas fa-eraser me-2"></i>
                    Effacer
                </button>
                <a href="list.php" class="btn btn-outline-primary btn-modern">
                    <i class="fas fa-list me-2"></i>
                    Liste simple
                </a>
            </div>
        </div>
    </form>
</div>

<!-- Résultats de recherche -->
<?php if ($search_performed): ?>
    <div class="results-card animate-fade-in animate-delay-3">
        <?php if (!empty($results)): ?>
            <div class="search-stats">
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <h5 class="mb-0">
                            <i class="fas fa-check-circle me-2"></i>
                            <?php echo count($results); ?> élève(s) trouvé(s)
                        </h5>
                        <small>Résultats de la recherche avancée</small>
                    </div>
                    <div class="col-md-4 text-end">
                        <button class="btn btn-light btn-sm" onclick="exportResults()">
                            <i class="fas fa-download me-1"></i>
                            Exporter
                        </button>
                        <button class="btn btn-light btn-sm ms-2" onclick="printResults()">
                            <i class="fas fa-print me-1"></i>
                            Imprimer
                        </button>
                    </div>
                </div>
            </div>

            <div class="row">
                <?php foreach ($results as $eleve): ?>
                    <div class="col-lg-6 mb-3">
                        <div class="student-result">
                            <div class="row align-items-center">
                                <div class="col-md-8">
                                    <h6 class="mb-1">
                                        <i class="fas fa-user me-2"></i>
                                        <?php echo htmlspecialchars($eleve['nom'] . ' ' . $eleve['prenom']); ?>
                                    </h6>
                                    <div class="small text-muted mb-2">
                                        <div class="row">
                                            <div class="col-6">
                                                <strong>Matricule:</strong> <?php echo htmlspecialchars($eleve['numero_matricule']); ?>
                                            </div>
                                            <div class="col-6">
                                                <strong>Âge:</strong> <?php echo $eleve['age']; ?> ans
                                            </div>
                                        </div>
                                        <div class="row">
                                            <div class="col-6">
                                                <strong>Classe:</strong> <?php echo htmlspecialchars($eleve['classe_nom']); ?>
                                            </div>
                                            <div class="col-6">
                                                <strong>Sexe:</strong> <?php echo $eleve['sexe'] === 'M' ? 'Masculin' : 'Féminin'; ?>
                                            </div>
                                        </div>
                                        <?php if ($eleve['date_naissance']): ?>
                                            <div class="row">
                                                <div class="col-6">
                                                    <strong>Né(e) le:</strong> <?php echo date('d/m/Y', strtotime($eleve['date_naissance'])); ?>
                                                </div>
                                                <div class="col-6">
                                                    <strong>Statut:</strong>
                                                    <span class="badge bg-<?php
                                                        echo $eleve['inscription_status'] === 'inscrit' ? 'success' :
                                                            ($eleve['inscription_status'] === 'suspendu' ? 'warning' : 'secondary');
                                                    ?>">
                                                        <?php echo ucfirst($eleve['inscription_status']); ?>
                                                    </span>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                        <?php if ($eleve['lieu_naissance']): ?>
                                            <div class="row">
                                                <div class="col-12">
                                                    <strong>Lieu de naissance:</strong> <?php echo htmlspecialchars($eleve['lieu_naissance']); ?>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                        <?php if ($eleve['nom_pere'] || $eleve['nom_mere']): ?>
                                            <div class="row">
                                                <?php if ($eleve['nom_pere']): ?>
                                                    <div class="col-6">
                                                        <strong>Père:</strong> <?php echo htmlspecialchars($eleve['nom_pere']); ?>
                                                    </div>
                                                <?php endif; ?>
                                                <?php if ($eleve['nom_mere']): ?>
                                                    <div class="col-6">
                                                        <strong>Mère:</strong> <?php echo htmlspecialchars($eleve['nom_mere']); ?>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        <?php endif; ?>
                                        <?php if ($eleve['adresse']): ?>
                                            <div class="row">
                                                <div class="col-12">
                                                    <strong>Adresse:</strong> <?php echo htmlspecialchars($eleve['adresse']); ?>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                        <?php if ($eleve['telephone']): ?>
                                            <div class="row">
                                                <div class="col-12">
                                                    <strong>Téléphone:</strong> <?php echo htmlspecialchars($eleve['telephone']); ?>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="col-md-4 text-end">
                                    <div class="btn-group-vertical btn-group-sm">
                                        <a href="records/view.php?id=<?php echo $eleve['id']; ?>"
                                           class="btn btn-outline-info btn-sm mb-1">
                                            <i class="fas fa-eye me-1"></i>
                                            Voir profil
                                        </a>
                                        <?php if (checkPermission('students')): ?>
                                            <a href="records/edit.php?id=<?php echo $eleve['id']; ?>"
                                               class="btn btn-outline-primary btn-sm mb-1">
                                                <i class="fas fa-edit me-1"></i>
                                                Modifier
                                            </a>
                                            <a href="attendance/index.php?eleve_id=<?php echo $eleve['id']; ?>"
                                               class="btn btn-outline-secondary btn-sm">
                                                <i class="fas fa-calendar-check me-1"></i>
                                                Présences
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Limitation des résultats -->
            <?php if (count($results) >= 100): ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i>
                    <strong>Limite atteinte:</strong> Seuls les 100 premiers résultats sont affichés.
                    Affinez vos critères de recherche pour obtenir des résultats plus précis.
                </div>
            <?php endif; ?>

        <?php else: ?>
            <div class="text-center py-5">
                <i class="fas fa-search fa-4x text-muted mb-3"></i>
                <h4 class="text-muted">Aucun résultat trouvé</h4>
                <p class="text-muted">
                    Aucun élève ne correspond aux critères de recherche spécifiés.
                    <br>Essayez de modifier ou d'élargir vos critères de recherche.
                </p>
                <button class="btn btn-outline-secondary btn-modern" onclick="clearForm()">
                    <i class="fas fa-eraser me-2"></i>
                    Effacer les critères
                </button>
            </div>
        <?php endif; ?>
    </div>
<?php else: ?>
    <!-- Message d'aide -->
    <div class="results-card animate-fade-in animate-delay-3">
        <div class="text-center py-4">
            <i class="fas fa-info-circle fa-3x text-info mb-3"></i>
            <h5 class="text-info">Recherche avancée d'élèves</h5>
            <p class="text-muted">
                Utilisez le formulaire ci-dessus pour effectuer une recherche détaillée dans la base de données des élèves.
                <br>Vous pouvez combiner plusieurs critères pour affiner votre recherche.
            </p>
            <div class="row mt-4">
                <div class="col-md-4">
                    <div class="card border-0 bg-light">
                        <div class="card-body text-center">
                            <i class="fas fa-user fa-2x text-primary mb-2"></i>
                            <h6>Informations personnelles</h6>
                            <small class="text-muted">Nom, prénom, âge, sexe, lieu de naissance</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card border-0 bg-light">
                        <div class="card-body text-center">
                            <i class="fas fa-school fa-2x text-success mb-2"></i>
                            <h6>Informations scolaires</h6>
                            <small class="text-muted">Classe, niveau, statut d'inscription</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card border-0 bg-light">
                        <div class="card-body text-center">
                            <i class="fas fa-home fa-2x text-warning mb-2"></i>
                            <h6>Informations familiales</h6>
                            <small class="text-muted">Parents, adresse, téléphone</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<script>
// Fonctions JavaScript
function clearForm() {
    document.getElementById('searchForm').reset();
    // Rediriger vers la page sans paramètres
    window.location.href = 'search.php';
}

function exportResults() {
    // Créer un formulaire pour l'export avec les critères actuels
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = 'search.php';

    // Ajouter un champ pour indiquer l'export
    const exportField = document.createElement('input');
    exportField.type = 'hidden';
    exportField.name = 'export';
    exportField.value = 'excel';
    form.appendChild(exportField);

    // Copier tous les champs du formulaire de recherche
    const searchForm = document.getElementById('searchForm');
    const formData = new FormData(searchForm);

    for (let [key, value] of formData.entries()) {
        const field = document.createElement('input');
        field.type = 'hidden';
        field.name = key;
        field.value = value;
        form.appendChild(field);
    }

    document.body.appendChild(form);
    form.submit();
    document.body.removeChild(form);
}

function printResults() {
    window.print();
}

// Animation des résultats au chargement
document.addEventListener('DOMContentLoaded', function() {
    const results = document.querySelectorAll('.student-result');
    results.forEach((result, index) => {
        result.style.opacity = '0';
        result.style.transform = 'translateY(20px)';
        result.style.transition = 'opacity 0.6s ease, transform 0.6s ease';

        setTimeout(() => {
            result.style.opacity = '1';
            result.style.transform = 'translateY(0)';
        }, index * 100);
    });
});
</script>

<?php include '../../includes/footer.php'; ?>
