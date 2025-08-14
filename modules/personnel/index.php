<?php
/**
 * Module de gestion du personnel - Page principale
 * Application de gestion scolaire - République Démocratique du Congo
 */

require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../includes/functions.php';

// Vérifier l'authentification et les permissions
requireLogin();
if (!checkPermission('personnel') && !checkPermission('personnel_view')) {
    showMessage('error', 'Accès refusé à ce module.');
    redirectTo('../../dashboard.php');
}

$page_title = 'Gestion du Personnel';

// Paramètres de recherche et filtrage
$search = sanitizeInput($_GET['search'] ?? '');
$fonction_filter = sanitizeInput($_GET['fonction'] ?? '');
$status_filter = sanitizeInput($_GET['status'] ?? '');

// Construction de la requête
$sql = "SELECT p.*, u.username, u.email as user_email, u.role 
        FROM personnel p 
        LEFT JOIN users u ON p.user_id = u.id
        WHERE 1=1";

$params = [];

if (!empty($search)) {
    $sql .= " AND (p.nom LIKE ? OR p.prenom LIKE ? OR p.matricule LIKE ? OR p.email LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

if (!empty($fonction_filter)) {
    $sql .= " AND p.fonction = ?";
    $params[] = $fonction_filter;
}

if (!empty($status_filter)) {
    $sql .= " AND p.status = ?";
    $params[] = $status_filter;
}

$sql .= " ORDER BY p.nom, p.prenom";

$personnel = $database->query($sql, $params)->fetchAll();

// Statistiques
$stats = [
    'total' => count($personnel),
    'enseignants' => count(array_filter($personnel, fn($p) => $p['fonction'] === 'enseignant')),
    'administratifs' => count(array_filter($personnel, fn($p) => in_array($p['fonction'], ['directeur', 'sous_directeur', 'secretaire', 'comptable']))),
    'actifs' => count(array_filter($personnel, fn($p) => $p['status'] === 'actif'))
];

include '../../includes/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">
        <i class="fas fa-users me-2"></i>
        Gestion du Personnel
    </h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <?php if (checkPermission('personnel')): ?>
            <div class="btn-group me-2">
                <a href="add.php" class="btn btn-primary">
                    <i class="fas fa-plus me-1"></i>
                    Nouveau membre
                </a>
                <a href="import.php" class="btn btn-outline-primary">
                    <i class="fas fa-upload me-1"></i>
                    Importer
                </a>
            </div>
        <?php endif; ?>
        <div class="btn-group">
            <button type="button" class="btn btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">
                <i class="fas fa-download me-1"></i>
                Exporter
            </button>
            <ul class="dropdown-menu">
                <li><a class="dropdown-item" href="export.php?format=excel"><i class="fas fa-file-excel me-2"></i>Excel</a></li>
                <li><a class="dropdown-item" href="export.php?format=pdf"><i class="fas fa-file-pdf me-2"></i>PDF</a></li>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item" href="payroll.php"><i class="fas fa-money-bill-wave me-2"></i>Fiche de paie</a></li>
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
                        <h4><?php echo $stats['total']; ?></h4>
                        <p class="mb-0">Total personnel</p>
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
                        <h4><?php echo $stats['enseignants']; ?></h4>
                        <p class="mb-0">Enseignants</p>
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
                        <h4><?php echo $stats['administratifs']; ?></h4>
                        <p class="mb-0">Administratifs</p>
                    </div>
                    <div class="align-self-center">
                        <i class="fas fa-user-tie fa-2x"></i>
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
                        <h4><?php echo $stats['actifs']; ?></h4>
                        <p class="mb-0">Actifs</p>
                    </div>
                    <div class="align-self-center">
                        <i class="fas fa-check-circle fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Filtres de recherche -->
<div class="card mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-md-4">
                <label for="search" class="form-label">Rechercher</label>
                <input type="text" 
                       class="form-control" 
                       id="search" 
                       name="search" 
                       placeholder="Nom, prénom, matricule ou email..."
                       value="<?php echo htmlspecialchars($search); ?>">
            </div>
            <div class="col-md-3">
                <label for="fonction" class="form-label">Fonction</label>
                <select class="form-select" id="fonction" name="fonction">
                    <option value="">Toutes les fonctions</option>
                    <option value="enseignant" <?php echo $fonction_filter === 'enseignant' ? 'selected' : ''; ?>>Enseignant</option>
                    <option value="directeur" <?php echo $fonction_filter === 'directeur' ? 'selected' : ''; ?>>Directeur</option>
                    <option value="sous_directeur" <?php echo $fonction_filter === 'sous_directeur' ? 'selected' : ''; ?>>Sous-directeur</option>
                    <option value="secretaire" <?php echo $fonction_filter === 'secretaire' ? 'selected' : ''; ?>>Secrétaire</option>
                    <option value="comptable" <?php echo $fonction_filter === 'comptable' ? 'selected' : ''; ?>>Comptable</option>
                    <option value="surveillant" <?php echo $fonction_filter === 'surveillant' ? 'selected' : ''; ?>>Surveillant</option>
                    <option value="gardien" <?php echo $fonction_filter === 'gardien' ? 'selected' : ''; ?>>Gardien</option>
                    <option value="autre" <?php echo $fonction_filter === 'autre' ? 'selected' : ''; ?>>Autre</option>
                </select>
            </div>
            <div class="col-md-3">
                <label for="status" class="form-label">Statut</label>
                <select class="form-select" id="status" name="status">
                    <option value="">Tous les statuts</option>
                    <option value="actif" <?php echo $status_filter === 'actif' ? 'selected' : ''; ?>>Actif</option>
                    <option value="suspendu" <?php echo $status_filter === 'suspendu' ? 'selected' : ''; ?>>Suspendu</option>
                    <option value="demissionne" <?php echo $status_filter === 'demissionne' ? 'selected' : ''; ?>>Démissionné</option>
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

<!-- Liste du personnel -->
<div class="card">
    <div class="card-header">
        <h5 class="mb-0">
            <i class="fas fa-list me-2"></i>
            Liste du personnel (<?php echo count($personnel); ?>)
        </h5>
    </div>
    <div class="card-body">
        <?php if (!empty($personnel)): ?>
            <div class="table-responsive">
                <table class="table table-striped table-hover datatable">
                    <thead>
                        <tr>
                            <th>Matricule</th>
                            <th>Nom complet</th>
                            <th>Fonction</th>
                            <th>Spécialité</th>
                            <th>Contact</th>
                            <th>Embauche</th>
                            <th>Salaire</th>
                            <th>Statut</th>
                            <th>Compte</th>
                            <th class="no-sort">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($personnel as $membre): ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($membre['matricule']); ?></strong>
                                </td>
                                <td>
                                    <div>
                                        <strong><?php echo htmlspecialchars($membre['nom'] . ' ' . $membre['prenom']); ?></strong>
                                        <br><small class="text-muted">
                                            <i class="fas fa-<?php echo $membre['sexe'] === 'M' ? 'mars' : 'venus'; ?>"></i>
                                            <?php echo $membre['sexe'] === 'M' ? 'Masculin' : 'Féminin'; ?>
                                            <?php if ($membre['date_naissance']): ?>
                                                - <?php echo calculateAge($membre['date_naissance']); ?> ans
                                            <?php endif; ?>
                                        </small>
                                    </div>
                                </td>
                                <td>
                                    <?php
                                    $fonction_colors = [
                                        'enseignant' => 'primary',
                                        'directeur' => 'danger',
                                        'sous_directeur' => 'warning',
                                        'secretaire' => 'info',
                                        'comptable' => 'success',
                                        'surveillant' => 'secondary',
                                        'gardien' => 'dark',
                                        'autre' => 'light'
                                    ];
                                    $color = $fonction_colors[$membre['fonction']] ?? 'secondary';
                                    ?>
                                    <span class="badge bg-<?php echo $color; ?>">
                                        <?php echo ucfirst(str_replace('_', ' ', $membre['fonction'])); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php echo htmlspecialchars($membre['specialite'] ?? '-'); ?>
                                </td>
                                <td>
                                    <div>
                                        <?php if (!empty($membre['telephone'])): ?>
                                            <small><i class="fas fa-phone fa-xs"></i> <?php echo htmlspecialchars($membre['telephone']); ?></small><br>
                                        <?php endif; ?>
                                        <?php if (!empty($membre['email'])): ?>
                                            <small><i class="fas fa-envelope fa-xs"></i> <?php echo htmlspecialchars($membre['email']); ?></small>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <?php echo $membre['date_embauche'] ? formatDate($membre['date_embauche']) : '-'; ?>
                                </td>
                                <td>
                                    <?php if ($membre['salaire_base']): ?>
                                        <strong><?php echo formatMoney($membre['salaire_base']); ?></strong>
                                    <?php else: ?>
                                        <span class="text-muted">Non défini</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php
                                    $status_colors = [
                                        'actif' => 'success',
                                        'suspendu' => 'warning',
                                        'demissionne' => 'danger'
                                    ];
                                    $color = $status_colors[$membre['status']] ?? 'secondary';
                                    ?>
                                    <span class="badge bg-<?php echo $color; ?>">
                                        <?php echo ucfirst($membre['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($membre['user_id']): ?>
                                        <span class="badge bg-info" title="<?php echo htmlspecialchars($membre['username']); ?>">
                                            <i class="fas fa-user-check"></i>
                                            <?php echo ucfirst($membre['role']); ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-muted">Aucun</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <a href="view.php?id=<?php echo $membre['id']; ?>" 
                                           class="btn btn-outline-info" 
                                           title="Voir détails">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <?php if (checkPermission('personnel')): ?>
                                            <a href="edit.php?id=<?php echo $membre['id']; ?>" 
                                               class="btn btn-outline-primary" 
                                               title="Modifier">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <a href="payslip.php?id=<?php echo $membre['id']; ?>" 
                                               class="btn btn-outline-success" 
                                               title="Fiche de paie">
                                                <i class="fas fa-money-bill"></i>
                                            </a>
                                            <a href="delete.php?id=<?php echo $membre['id']; ?>" 
                                               class="btn btn-outline-danger btn-delete" 
                                               title="Supprimer"
                                               data-name="<?php echo htmlspecialchars($membre['nom'] . ' ' . $membre['prenom']); ?>">
                                                <i class="fas fa-trash"></i>
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
            <div class="text-center py-5">
                <i class="fas fa-users fa-3x text-muted mb-3"></i>
                <h5 class="text-muted">Aucun membre du personnel trouvé</h5>
                <p class="text-muted">
                    <?php if (checkPermission('personnel')): ?>
                        <a href="add.php" class="btn btn-primary">
                            <i class="fas fa-plus me-1"></i>
                            Ajouter le premier membre
                        </a>
                    <?php else: ?>
                        Aucun membre du personnel n'est encore enregistré dans le système.
                    <?php endif; ?>
                </p>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>
