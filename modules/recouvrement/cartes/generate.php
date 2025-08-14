<?php
/**
 * Module Recouvrement - Génération des Cartes
 * Application de gestion scolaire - République Démocratique du Congo
 */

require_once '../../../config/config.php';
require_once '../../../config/database.php';
require_once '../../../includes/functions.php';

// Vérifier l'authentification et les permissions
requireLogin();
if (!checkPermission('recouvrement') && !checkPermission('admin')) {
    showMessage('error', 'Accès refusé à cette page.');
    redirectTo('../../../index.php');
}

$errors = [];
$success_messages = [];

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $database->beginTransaction();
        
        $action = $_POST['action'] ?? '';
        
        if ($action === 'generate_selected') {
            $eleve_ids = $_POST['eleve_ids'] ?? [];
            
            if (empty($eleve_ids)) {
                throw new Exception('Veuillez sélectionner au moins un élève.');
            }
            
            $generated_count = 0;
            
            foreach ($eleve_ids as $eleve_id) {
                // Vérifier si l'élève a déjà une carte active
                $existing_card = $database->query(
                    "SELECT id FROM cartes_eleves WHERE eleve_id = ? AND status = 'active'",
                    [$eleve_id]
                )->fetch();
                
                if ($existing_card) {
                    continue; // Passer cet élève s'il a déjà une carte active
                }
                
                // Générer un numéro de carte unique
                $numero_carte = generateCardNumber();
                
                // Générer le contenu du QR code (JSON avec les infos de l'élève)
                $eleve_info = $database->query(
                    "SELECT e.*, c.nom as classe_nom 
                     FROM eleves e 
                     LEFT JOIN inscriptions i ON e.id = i.eleve_id AND i.status = 'inscrit'
                     LEFT JOIN classes c ON i.classe_id = c.id
                     WHERE e.id = ?",
                    [$eleve_id]
                )->fetch();
                
                if (!$eleve_info) {
                    continue;
                }
                
                $qr_data = json_encode([
                    'eleve_id' => $eleve_info['id'],
                    'numero_matricule' => $eleve_info['numero_matricule'],
                    'nom' => $eleve_info['nom'],
                    'prenom' => $eleve_info['prenom'],
                    'classe' => $eleve_info['classe_nom'],
                    'numero_carte' => $numero_carte,
                    'date_emission' => date('Y-m-d'),
                    'type' => 'carte_eleve'
                ]);
                
                // Calculer la date d'expiration (12 mois par défaut)
                $validite_mois = getParameterValue('carte_validite_mois', 12);
                $date_expiration = date('Y-m-d', strtotime("+{$validite_mois} months"));
                
                // Insérer la carte dans la base de données
                $database->execute(
                    "INSERT INTO cartes_eleves (eleve_id, numero_carte, qr_code, date_emission, date_expiration, status, created_at) 
                     VALUES (?, ?, ?, ?, ?, 'active', NOW())",
                    [$eleve_id, $numero_carte, $qr_data, date('Y-m-d'), $date_expiration]
                );
                
                $generated_count++;
            }
            
            $database->commit();
            $success_messages[] = "$generated_count carte(s) générée(s) avec succès.";
            
        } elseif ($action === 'generate_class') {
            $classe_id = intval($_POST['classe_id'] ?? 0);
            
            if (!$classe_id) {
                throw new Exception('Veuillez sélectionner une classe.');
            }
            
            // Récupérer tous les élèves de la classe qui n'ont pas de carte active
            $eleves = $database->query(
                "SELECT e.id, e.nom, e.prenom, e.numero_matricule
                 FROM eleves e
                 JOIN inscriptions i ON e.id = i.eleve_id
                 WHERE i.classe_id = ? AND i.status = 'inscrit'
                 AND NOT EXISTS (
                     SELECT 1 FROM cartes_eleves c 
                     WHERE c.eleve_id = e.id AND c.status = 'active'
                 )
                 ORDER BY e.nom, e.prenom",
                [$classe_id]
            )->fetchAll();
            
            $generated_count = 0;
            
            foreach ($eleves as $eleve) {
                // Générer un numéro de carte unique
                $numero_carte = generateCardNumber();
                
                // Récupérer les infos complètes de l'élève
                $eleve_info = $database->query(
                    "SELECT e.*, c.nom as classe_nom 
                     FROM eleves e 
                     LEFT JOIN inscriptions i ON e.id = i.eleve_id AND i.status = 'inscrit'
                     LEFT JOIN classes c ON i.classe_id = c.id
                     WHERE e.id = ?",
                    [$eleve['id']]
                )->fetch();
                
                $qr_data = json_encode([
                    'eleve_id' => $eleve_info['id'],
                    'numero_matricule' => $eleve_info['numero_matricule'],
                    'nom' => $eleve_info['nom'],
                    'prenom' => $eleve_info['prenom'],
                    'classe' => $eleve_info['classe_nom'],
                    'numero_carte' => $numero_carte,
                    'date_emission' => date('Y-m-d'),
                    'type' => 'carte_eleve'
                ]);
                
                // Calculer la date d'expiration
                $validite_mois = getParameterValue('carte_validite_mois', 12);
                $date_expiration = date('Y-m-d', strtotime("+{$validite_mois} months"));
                
                // Insérer la carte
                $database->execute(
                    "INSERT INTO cartes_eleves (eleve_id, numero_carte, qr_code, date_emission, date_expiration, status, created_at) 
                     VALUES (?, ?, ?, ?, ?, 'active', NOW())",
                    [$eleve['id'], $numero_carte, $qr_data, date('Y-m-d'), $date_expiration]
                );
                
                $generated_count++;
            }
            
            $database->commit();
            $success_messages[] = "$generated_count carte(s) générée(s) pour la classe.";
        }
        
    } catch (Exception $e) {
        $database->rollback();
        $errors[] = 'Erreur lors de la génération : ' . $e->getMessage();
    }
}

// Fonction pour générer un numéro de carte unique
function generateCardNumber() {
    global $database;
    
    $prefix = getParameterValue('card_number_prefix', 'CARD');
    $auto_generate = getParameterValue('auto_generate_card_number', 1);
    
    if ($auto_generate) {
        do {
            $numero = $prefix . '-' . date('Y') . '-' . str_pad(rand(1, 99999), 5, '0', STR_PAD_LEFT);
            $exists = $database->query(
                "SELECT id FROM cartes_eleves WHERE numero_carte = ?",
                [$numero]
            )->fetch();
        } while ($exists);
        
        return $numero;
    } else {
        return $prefix . '-' . date('Y') . '-' . uniqid();
    }
}

// Fonction pour récupérer une valeur de paramètre
function getParameterValue($key, $default = null) {
    global $database;
    
    try {
        $param = $database->query(
            "SELECT valeur FROM parametres_recouvrement WHERE cle = ?",
            [$key]
        )->fetch();
        
        return $param ? $param['valeur'] : $default;
    } catch (Exception $e) {
        return $default;
    }
}

// Paramètres de recherche et filtrage
$search = trim($_GET['search'] ?? '');
$classe_filter = $_GET['classe'] ?? '';
$page = max(1, intval($_GET['page'] ?? 1));
$per_page = 20;
$offset = ($page - 1) * $per_page;

// Construction de la requête pour les élèves sans carte
$where_conditions = ["1=1"];
$params = [];

if ($search) {
    $where_conditions[] = "(e.nom LIKE ? OR e.prenom LIKE ? OR e.numero_matricule LIKE ?)";
    $search_param = "%$search%";
    $params = array_merge($params, [$search_param, $search_param, $search_param]);
}

if ($classe_filter) {
    $where_conditions[] = "i.classe_id = ?";
    $params[] = $classe_filter;
}

$where_clause = implode(' AND ', $where_conditions);

// Récupérer les élèves sans carte active
try {
    $sql = "SELECT e.id, e.nom, e.prenom, e.numero_matricule, e.date_naissance,
                   c.nom as classe_nom, c.niveau,
                   CASE WHEN ce.id IS NOT NULL THEN 1 ELSE 0 END as has_card
            FROM eleves e
            JOIN inscriptions i ON e.id = i.eleve_id AND i.status = 'inscrit'
            LEFT JOIN classes c ON i.classe_id = c.id
            LEFT JOIN cartes_eleves ce ON e.id = ce.eleve_id AND ce.status = 'active'
            WHERE $where_clause AND ce.id IS NULL
            ORDER BY c.nom, e.nom, e.prenom
            LIMIT $per_page OFFSET $offset";
    
    $eleves_sans_carte = $database->query($sql, $params)->fetchAll();
    
    // Compter le total
    $total_sql = "SELECT COUNT(*) as total 
                  FROM eleves e
                  JOIN inscriptions i ON e.id = i.eleve_id AND i.status = 'inscrit'
                  LEFT JOIN classes c ON i.classe_id = c.id
                  LEFT JOIN cartes_eleves ce ON e.id = ce.eleve_id AND ce.status = 'active'
                  WHERE $where_clause AND ce.id IS NULL";
    
    $total = $database->query($total_sql, $params)->fetch()['total'];
    $total_pages = ceil($total / $per_page);
    
} catch (Exception $e) {
    $eleves_sans_carte = [];
    $total = 0;
    $total_pages = 0;
    $errors[] = 'Erreur lors du chargement des élèves : ' . $e->getMessage();
}

// Récupérer les classes pour le filtre
try {
    $classes = $database->query(
        "SELECT id, nom FROM classes ORDER BY nom"
    )->fetchAll();
} catch (Exception $e) {
    $classes = [];
}

// Statistiques
try {
    $stats = $database->query(
        "SELECT 
            (SELECT COUNT(*) FROM eleves e JOIN inscriptions i ON e.id = i.eleve_id WHERE i.status = 'inscrit') as total_eleves,
            (SELECT COUNT(*) FROM cartes_eleves WHERE status = 'active') as cartes_actives,
            (SELECT COUNT(*) FROM eleves e 
             JOIN inscriptions i ON e.id = i.eleve_id 
             WHERE i.status = 'inscrit' 
             AND NOT EXISTS (SELECT 1 FROM cartes_eleves c WHERE c.eleve_id = e.id AND c.status = 'active')
            ) as eleves_sans_carte"
    )->fetch();
} catch (Exception $e) {
    $stats = ['total_eleves' => 0, 'cartes_actives' => 0, 'eleves_sans_carte' => 0];
}

$page_title = "Génération des Cartes";
include '../../../includes/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">
        <i class="fas fa-id-card me-2 text-success"></i>
        Génération des Cartes
        <span class="badge bg-secondary ms-2"><?php echo number_format($total ?? 0); ?></span>
    </h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <div class="btn-group me-2">
            <a href="index.php" class="btn btn-outline-primary">
                <i class="fas fa-list me-1"></i>
                Voir les cartes
            </a>
        </div>
        <div class="btn-group me-2">
            <a href="print.php" class="btn btn-info">
                <i class="fas fa-print me-1"></i>
                Imprimer cartes
            </a>
        </div>
        <div class="btn-group">
            <a href="../index.php" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-1"></i>
                Retour
            </a>
        </div>
    </div>
</div>

<!-- Messages d'erreur et de succès -->
<?php if (!empty($errors)): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="fas fa-exclamation-triangle me-2"></i>
        <ul class="mb-0">
            <?php foreach ($errors as $error): ?>
                <li><?php echo htmlspecialchars($error); ?></li>
            <?php endforeach; ?>
        </ul>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if (!empty($success_messages)): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="fas fa-check-circle me-2"></i>
        <ul class="mb-0">
            <?php foreach ($success_messages as $message): ?>
                <li><?php echo htmlspecialchars($message); ?></li>
            <?php endforeach; ?>
        </ul>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<!-- Statistiques -->
<div class="row mb-4">
    <div class="col-md-4">
        <div class="card bg-primary text-white">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h4 class="mb-0"><?php echo number_format($stats['total_eleves']); ?></h4>
                        <p class="mb-0">Total Élèves</p>
                    </div>
                    <div class="align-self-center">
                        <i class="fas fa-users fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card bg-success text-white">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h4 class="mb-0"><?php echo number_format($stats['cartes_actives']); ?></h4>
                        <p class="mb-0">Cartes Actives</p>
                    </div>
                    <div class="align-self-center">
                        <i class="fas fa-id-card fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card bg-warning text-white">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h4 class="mb-0"><?php echo number_format($stats['eleves_sans_carte']); ?></h4>
                        <p class="mb-0">Sans Carte</p>
                    </div>
                    <div class="align-self-center">
                        <i class="fas fa-exclamation-triangle fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Actions rapides -->
<div class="row mb-4">
    <div class="col-md-6">
        <div class="card shadow-sm">
            <div class="card-header bg-success text-white">
                <h6 class="mb-0">
                    <i class="fas fa-graduation-cap me-2"></i>
                    Générer par Classe
                </h6>
            </div>
            <div class="card-body">
                <form method="POST" onsubmit="return confirm('Générer les cartes pour tous les élèves de cette classe ?')">
                    <input type="hidden" name="action" value="generate_class">
                    <div class="mb-3">
                        <label for="classe_id" class="form-label">Sélectionner une classe</label>
                        <select class="form-select" id="classe_id" name="classe_id" required>
                            <option value="">Choisir une classe...</option>
                            <?php foreach ($classes as $classe): ?>
                                <option value="<?php echo $classe['id']; ?>">
                                    <?php echo htmlspecialchars($classe['nom']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-magic me-1"></i>
                        Générer pour la classe
                    </button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-md-6">
        <div class="card shadow-sm">
            <div class="card-header bg-info text-white">
                <h6 class="mb-0">
                    <i class="fas fa-info-circle me-2"></i>
                    Informations
                </h6>
            </div>
            <div class="card-body">
                <ul class="list-unstyled mb-0">
                    <li><i class="fas fa-check text-success me-2"></i>Les cartes contiennent un QR code unique</li>
                    <li><i class="fas fa-check text-success me-2"></i>Validité de <?php echo getParameterValue('carte_validite_mois', 12); ?> mois</li>
                    <li><i class="fas fa-check text-success me-2"></i>Numérotation automatique</li>
                    <li><i class="fas fa-check text-success me-2"></i>Données sécurisées dans le QR code</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<!-- Filtres de recherche -->
<div class="card shadow-sm mb-4">
    <div class="card-header bg-light">
        <h6 class="mb-0">
            <i class="fas fa-filter me-2"></i>
            Filtres de recherche
        </h6>
    </div>
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-md-4">
                <label for="search" class="form-label">Rechercher</label>
                <input type="text" class="form-control" id="search" name="search"
                       value="<?php echo htmlspecialchars($search); ?>"
                       placeholder="Nom, prénom ou matricule...">
            </div>

            <div class="col-md-4">
                <label for="classe" class="form-label">Classe</label>
                <select class="form-select" id="classe" name="classe">
                    <option value="">Toutes les classes</option>
                    <?php foreach ($classes as $classe): ?>
                        <option value="<?php echo $classe['id']; ?>"
                                <?php echo ($classe_filter == $classe['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($classe['nom']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-4 d-flex align-items-end">
                <button type="submit" class="btn btn-primary me-2">
                    <i class="fas fa-search me-1"></i>
                    Rechercher
                </button>
                <a href="generate.php" class="btn btn-outline-secondary">
                    <i class="fas fa-times me-1"></i>
                    Effacer
                </a>
            </div>
        </form>
    </div>
</div>

<!-- Actions en lot -->
<?php if (!empty($eleves_sans_carte)): ?>
<div class="card shadow-sm mb-4">
    <div class="card-body">
        <form method="POST" id="bulkForm">
            <input type="hidden" name="action" value="generate_selected">
            <div class="row g-3 align-items-end">
                <div class="col-md-6">
                    <label class="form-label">Actions en lot</label>
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-outline-primary btn-sm" onclick="selectAll()">
                            <i class="fas fa-check-square me-1"></i>
                            Tout sélectionner
                        </button>
                        <button type="button" class="btn btn-outline-secondary btn-sm" onclick="selectNone()">
                            <i class="fas fa-square me-1"></i>
                            Tout désélectionner
                        </button>
                    </div>
                </div>

                <div class="col-md-6 text-end">
                    <button type="submit" class="btn btn-success" onclick="return confirmBulkGeneration()">
                        <i class="fas fa-magic me-1"></i>
                        Générer les cartes sélectionnées
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<!-- Liste des élèves sans carte -->
<div class="card shadow-sm">
    <div class="card-header bg-light d-flex justify-content-between align-items-center">
        <h6 class="mb-0">
            <i class="fas fa-users me-2"></i>
            Élèves sans carte active
        </h6>
        <span class="badge bg-warning"><?php echo number_format($total); ?> élève(s)</span>
    </div>
    <div class="card-body">
        <?php if (empty($eleves_sans_carte)): ?>
            <div class="text-center py-5">
                <i class="fas fa-check-circle fa-3x text-success mb-3"></i>
                <h5 class="text-success">Excellent !</h5>
                <p class="text-muted">
                    <?php if ($search || $classe_filter): ?>
                        Aucun élève trouvé avec ces critères de recherche.
                    <?php else: ?>
                        Tous les élèves ont déjà une carte active.
                    <?php endif; ?>
                </p>
                <a href="index.php" class="btn btn-primary">
                    <i class="fas fa-list me-1"></i>
                    Voir toutes les cartes
                </a>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover" id="elevesTable">
                    <thead class="table-light">
                        <tr>
                            <th width="30">
                                <input type="checkbox" class="form-check-input" id="selectAllCheckbox" onchange="toggleAll()">
                            </th>
                            <th>Matricule</th>
                            <th>Nom & Prénom</th>
                            <th>Classe</th>
                            <th>Date Naissance</th>
                            <th width="100">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($eleves_sans_carte as $eleve): ?>
                            <tr>
                                <td>
                                    <input type="checkbox" class="form-check-input eleve-checkbox"
                                           name="eleve_ids[]" value="<?php echo $eleve['id']; ?>"
                                           form="bulkForm">
                                </td>
                                <td>
                                    <span class="badge bg-secondary">
                                        <?php echo htmlspecialchars($eleve['numero_matricule']); ?>
                                    </span>
                                </td>
                                <td>
                                    <div>
                                        <strong><?php echo htmlspecialchars($eleve['nom'] . ' ' . $eleve['prenom']); ?></strong>
                                    </div>
                                </td>
                                <td>
                                    <?php if ($eleve['classe_nom']): ?>
                                        <span class="badge bg-info">
                                            <?php echo htmlspecialchars($eleve['classe_nom']); ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-muted">Non assigné</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <small class="text-muted">
                                        <?php echo date('d/m/Y', strtotime($eleve['date_naissance'])); ?>
                                    </small>
                                </td>
                                <td>
                                    <form method="POST" style="display: inline;"
                                          onsubmit="return confirm('Générer une carte pour cet élève ?')">
                                        <input type="hidden" name="action" value="generate_selected">
                                        <input type="hidden" name="eleve_ids[]" value="<?php echo $eleve['id']; ?>">
                                        <button type="submit" class="btn btn-sm btn-success" title="Générer carte">
                                            <i class="fas fa-plus"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Pagination -->
<?php if ($total_pages > 1): ?>
<nav aria-label="Navigation des pages" class="mt-4">
    <ul class="pagination justify-content-center">
        <?php if ($page > 1): ?>
            <li class="page-item">
                <a class="page-link" href="?page=<?php echo ($page - 1); ?>&search=<?php echo urlencode($search); ?>&classe=<?php echo urlencode($classe_filter); ?>">
                    <i class="fas fa-chevron-left"></i> Précédent
                </a>
            </li>
        <?php endif; ?>

        <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
            <li class="page-item <?php echo ($i == $page) ? 'active' : ''; ?>">
                <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&classe=<?php echo urlencode($classe_filter); ?>">
                    <?php echo $i; ?>
                </a>
            </li>
        <?php endfor; ?>

        <?php if ($page < $total_pages): ?>
            <li class="page-item">
                <a class="page-link" href="?page=<?php echo ($page + 1); ?>&search=<?php echo urlencode($search); ?>&classe=<?php echo urlencode($classe_filter); ?>">
                    Suivant <i class="fas fa-chevron-right"></i>
                </a>
            </li>
        <?php endif; ?>
    </ul>
</nav>
<?php endif; ?>

<script>
// Fonctions pour la sélection en lot
function selectAll() {
    const checkboxes = document.querySelectorAll('.eleve-checkbox');
    checkboxes.forEach(checkbox => {
        checkbox.checked = true;
    });
    updateSelectAllCheckbox();
}

function selectNone() {
    const checkboxes = document.querySelectorAll('.eleve-checkbox');
    checkboxes.forEach(checkbox => {
        checkbox.checked = false;
    });
    updateSelectAllCheckbox();
}

function toggleAll() {
    const selectAllCheckbox = document.getElementById('selectAllCheckbox');
    const checkboxes = document.querySelectorAll('.eleve-checkbox');

    checkboxes.forEach(checkbox => {
        checkbox.checked = selectAllCheckbox.checked;
    });
}

function updateSelectAllCheckbox() {
    const checkboxes = document.querySelectorAll('.eleve-checkbox');
    const selectAllCheckbox = document.getElementById('selectAllCheckbox');

    if (checkboxes.length === 0) return;

    const checkedCount = document.querySelectorAll('.eleve-checkbox:checked').length;

    if (checkedCount === 0) {
        selectAllCheckbox.checked = false;
        selectAllCheckbox.indeterminate = false;
    } else if (checkedCount === checkboxes.length) {
        selectAllCheckbox.checked = true;
        selectAllCheckbox.indeterminate = false;
    } else {
        selectAllCheckbox.checked = false;
        selectAllCheckbox.indeterminate = true;
    }
}

function confirmBulkGeneration() {
    const checkedBoxes = document.querySelectorAll('.eleve-checkbox:checked');

    if (checkedBoxes.length === 0) {
        alert('Veuillez sélectionner au moins un élève.');
        return false;
    }

    return confirm(`Générer ${checkedBoxes.length} carte(s) pour les élèves sélectionnés ?`);
}

// Écouter les changements sur les checkboxes individuelles
document.addEventListener('DOMContentLoaded', function() {
    const checkboxes = document.querySelectorAll('.eleve-checkbox');
    checkboxes.forEach(checkbox => {
        checkbox.addEventListener('change', updateSelectAllCheckbox);
    });

    // Initialiser l'état du checkbox "Tout sélectionner"
    updateSelectAllCheckbox();
});

// Auto-submit du formulaire de recherche avec un délai
let searchTimeout;
document.getElementById('search')?.addEventListener('input', function() {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        this.form.submit();
    }, 500);
});
</script>

<?php include '../../../includes/footer.php'; ?>
