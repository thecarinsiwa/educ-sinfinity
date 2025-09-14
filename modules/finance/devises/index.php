<?php
/**
 * Module de gestion des devises
 * Application de gestion scolaire - République Démocratique du Congo
 */

require_once '../../../config/config.php';
require_once '../../../config/database.php';
require_once '../../../includes/functions.php';
require_once '../../../includes/permissions-pages.php';
require_once '../../../includes/ui-permissions.php';

// Vérifier l'authentification et les permissions
requireLogin();
requirePagePermissionFromDB('finance', 'devises/index', 'read', '../../dashboard.php');

$page_title = 'Gestion des Devises';

// Traitement des actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add':
                $code = sanitizeInput($_POST['code']);
                $nom = sanitizeInput($_POST['nom']);
                $symbole = sanitizeInput($_POST['symbole']);
                $taux_conversion = floatval($_POST['taux_conversion']);
                $devise_par_defaut = isset($_POST['devise_par_defaut']) && $_POST['devise_par_defaut'] ? 1 : 0;
                
                // Vérifier si c'est la première devise
                $count = $database->query("SELECT COUNT(*) as total FROM devises")->fetch()['total'];
                if ($count == 0) {
                    $devise_par_defaut = 1;
                }
                
                // Si c'est une nouvelle devise par défaut, désactiver les autres
                if ($devise_par_defaut) {
                    $database->execute("UPDATE devises SET devise_par_defaut = FALSE");
                }
                
                try {
                    $database->execute(
                        "INSERT INTO devises (code, nom, symbole, taux_conversion, devise_par_defaut) VALUES (?, ?, ?, ?, ?)",
                        [$code, $nom, $symbole, $taux_conversion, $devise_par_defaut]
                    );
                    showMessage('success', 'Devise ajoutée avec succès.');
                } catch (Exception $e) {
                    showMessage('error', 'Erreur lors de l\'ajout de la devise: ' . $e->getMessage());
                }
                break;
                
            case 'edit':
                $id = intval($_POST['id']);
                $code = sanitizeInput($_POST['code']);
                $nom = sanitizeInput($_POST['nom']);
                $symbole = sanitizeInput($_POST['symbole']);
                $taux_conversion = floatval($_POST['taux_conversion']);
                $devise_par_defaut = isset($_POST['devise_par_defaut']) && $_POST['devise_par_defaut'] ? 1 : 0;
                $active = isset($_POST['active']) && $_POST['active'] ? 1 : 0;
                
                // Si c'est une nouvelle devise par défaut, désactiver les autres
                if ($devise_par_defaut) {
                    $database->execute("UPDATE devises SET devise_par_defaut = FALSE WHERE id != ?", [$id]);
                }
                
                try {
                    $database->execute(
                        "UPDATE devises SET code = ?, nom = ?, symbole = ?, taux_conversion = ?, devise_par_defaut = ?, active = ? WHERE id = ?",
                        [$code, $nom, $symbole, $taux_conversion, $devise_par_defaut, $active, $id]
                    );
                    showMessage('success', 'Devise mise à jour avec succès.');
                } catch (Exception $e) {
                    showMessage('error', 'Erreur lors de la mise à jour: ' . $e->getMessage());
                }
                break;
                
            case 'delete':
                $id = intval($_POST['id']);
                
                // Vérifier si la devise est utilisée
                $usage = $database->query(
                    "SELECT 
                        (SELECT COUNT(*) FROM paiements WHERE devise_id = ?) +
                        (SELECT COUNT(*) FROM frais_scolaires WHERE devise_id = ?) +
                        (SELECT COUNT(*) FROM paiements_cartes WHERE devise_id = ?) as total",
                    [$id, $id, $id]
                )->fetch()['total'];
                
                if ($usage > 0) {
                    showMessage('error', 'Cette devise ne peut pas être supprimée car elle est utilisée dans des opérations financières.');
                } else {
                    try {
                        $database->execute("DELETE FROM devises WHERE id = ?", [$id]);
                        showMessage('success', 'Devise supprimée avec succès.');
                    } catch (Exception $e) {
                        showMessage('error', 'Erreur lors de la suppression: ' . $e->getMessage());
                    }
                }
                break;
        }
        
        // Redirection pour éviter la resoumission du formulaire
        redirectTo('index.php');
    }
}

// Récupérer toutes les devises
$devises = $database->query("SELECT * FROM devises ORDER BY devise_par_defaut DESC, code")->fetchAll();

// Statistiques
$stats = [];
$stats['total_devises'] = count($devises);
$stats['devises_actives'] = count(array_filter($devises, fn($d) => $d['active']));
$stats['devise_par_defaut'] = array_filter($devises, fn($d) => $d['devise_par_defaut'])[0] ?? null;

include '../../../includes/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">Gestion des Devises</h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <?php if (hasPagePermissionFromDB('finance', 'devises/add', 'create')): ?>
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addDeviseModal">
            <i class="fas fa-plus"></i> Nouvelle Devise
        </button>
        <?php endif; ?>
    </div>
</div>

<!-- Statistiques -->
<div class="row mb-4">
    <div class="col-md-3">
        <div class="card text-center">
            <div class="card-body">
                <h5 class="card-title"><?= $stats['total_devises'] ?></h5>
                <p class="card-text">Total Devises</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-center">
            <div class="card-body">
                <h5 class="card-title"><?= $stats['devises_actives'] ?></h5>
                <p class="card-text">Devises Actives</p>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card">
            <div class="card-body">
                <h6 class="card-title">Devise par défaut</h6>
                <p class="card-text">
                    <?php if ($stats['devise_par_defaut']): ?>
                        <strong><?= htmlspecialchars($stats['devise_par_defaut']['nom']) ?></strong> 
                        (<?= htmlspecialchars($stats['devise_par_defaut']['code']) ?>)
                        - Taux: <?= number_format($stats['devise_par_defaut']['taux_conversion'], 6) ?>
                    <?php else: ?>
                        <span class="text-warning">Aucune devise par défaut définie</span>
                    <?php endif; ?>
                </p>
            </div>
        </div>
    </div>
</div>

<!-- Liste des devises -->
<div class="card">
    <div class="card-header">
        <h5 class="mb-0">Liste des Devises</h5>
    </div>
    <div class="card-body">
        <?php if (empty($devises)): ?>
            <p class="text-muted text-center">Aucune devise configurée.</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead>
                        <tr>
                            <th>Code</th>
                            <th>Nom</th>
                            <th>Symbole</th>
                            <th>Taux de Conversion</th>
                            <th>Par défaut</th>
                            <th>Statut</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($devises as $devise): ?>
                            <tr>
                                <td>
                                    <span class="badge bg-primary"><?= htmlspecialchars($devise['code']) ?></span>
                                </td>
                                <td><?= htmlspecialchars($devise['nom']) ?></td>
                                <td><?= htmlspecialchars($devise['symbole']) ?></td>
                                <td><?= number_format($devise['taux_conversion'], 6) ?></td>
                                <td>
                                    <?php if ($devise['devise_par_defaut']): ?>
                                        <span class="badge bg-success">Oui</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Non</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($devise['active']): ?>
                                        <span class="badge bg-success">Active</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger">Inactive</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (hasPagePermissionFromDB('finance', 'devises/edit', 'update')): ?>
                                    <button type="button" class="btn btn-sm btn-outline-primary" 
                                            onclick="editDevise(<?= htmlspecialchars(json_encode($devise)) ?>)">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <?php endif; ?>
                                    <?php if (!$devise['devise_par_defaut'] && hasPagePermissionFromDB('finance', 'devises/delete', 'delete')): ?>
                                        <button type="button" class="btn btn-sm btn-outline-danger" 
                                                onclick="deleteDevise(<?= $devise['id'] ?>, '<?= htmlspecialchars($devise['code']) ?>')">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Modal Ajout Devise -->
<div class="modal fade" id="addDeviseModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="add">
                <div class="modal-header">
                    <h5 class="modal-title">Nouvelle Devise</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="code" class="form-label">Code ISO (3 lettres)</label>
                        <input type="text" class="form-control" id="code" name="code" maxlength="3" required>
                        <div class="form-text">Ex: USD, EUR, CDF</div>
                    </div>
                    <div class="mb-3">
                        <label for="nom" class="form-label">Nom de la devise</label>
                        <input type="text" class="form-control" id="nom" name="nom" required>
                    </div>
                    <div class="mb-3">
                        <label for="symbole" class="form-label">Symbole</label>
                        <input type="text" class="form-control" id="symbole" name="symbole" maxlength="10" required>
                    </div>
                    <div class="mb-3">
                        <label for="taux_conversion" class="form-label">Taux de conversion</label>
                        <input type="number" class="form-control" id="taux_conversion" name="taux_conversion" 
                               step="0.000001" min="0" required>
                        <div class="form-text">Taux par rapport à la devise par défaut</div>
                    </div>
                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="devise_par_defaut" name="devise_par_defaut" value="1">
                            <label class="form-check-label" for="devise_par_defaut">
                                Définir comme devise par défaut
                            </label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-primary">Ajouter</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Édition Devise -->
<div class="modal fade" id="editDeviseModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="id" id="edit_id">
                <div class="modal-header">
                    <h5 class="modal-title">Modifier la Devise</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="edit_code" class="form-label">Code ISO</label>
                        <input type="text" class="form-control" id="edit_code" name="code" maxlength="3" required>
                    </div>
                    <div class="mb-3">
                        <label for="edit_nom" class="form-label">Nom de la devise</label>
                        <input type="text" class="form-control" id="edit_nom" name="nom" required>
                    </div>
                    <div class="mb-3">
                        <label for="edit_symbole" class="form-label">Symbole</label>
                        <input type="text" class="form-control" id="edit_symbole" name="symbole" maxlength="10" required>
                    </div>
                    <div class="mb-3">
                        <label for="edit_taux_conversion" class="form-label">Taux de conversion</label>
                        <input type="number" class="form-control" id="edit_taux_conversion" name="taux_conversion" 
                               step="0.000001" min="0" required>
                    </div>
                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="edit_devise_par_defaut" name="devise_par_defaut" value="1">
                            <label class="form-check-label" for="edit_devise_par_defaut">
                                Définir comme devise par défaut
                            </label>
                        </div>
                    </div>
                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="edit_active" name="active" value="1" checked>
                            <label class="form-check-label" for="edit_active">
                                Devise active
                            </label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-primary">Mettre à jour</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Formulaire de suppression -->
<form id="deleteForm" method="POST" style="display: none;">
    <input type="hidden" name="action" value="delete">
    <input type="hidden" name="id" id="delete_id">
</form>

<script>
function editDevise(devise) {
    document.getElementById('edit_id').value = devise.id;
    document.getElementById('edit_code').value = devise.code;
    document.getElementById('edit_nom').value = devise.nom;
    document.getElementById('edit_symbole').value = devise.symbole;
    document.getElementById('edit_taux_conversion').value = devise.taux_conversion;
    document.getElementById('edit_devise_par_defaut').checked = devise.devise_par_defaut == 1;
    document.getElementById('edit_active').checked = devise.active == 1;
    
    new bootstrap.Modal(document.getElementById('editDeviseModal')).show();
}

function deleteDevise(id, code) {
    if (confirm(`Êtes-vous sûr de vouloir supprimer la devise ${code} ?`)) {
        document.getElementById('delete_id').value = id;
        document.getElementById('deleteForm').submit();
    }
}

// Validation du code ISO
document.getElementById('code').addEventListener('input', function() {
    this.value = this.value.toUpperCase();
});

document.getElementById('edit_code').addEventListener('input', function() {
    this.value = this.value.toUpperCase();
});
</script>

<?php include '../../../includes/footer.php'; ?>
