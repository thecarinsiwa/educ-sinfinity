<?php
/**
 * Module Communication - Gestion des templates
 * Application de gestion scolaire - République Démocratique du Congo
 */

require_once '../../../config/config.php';
require_once '../../../config/database.php';
require_once '../../../includes/functions.php';

// Vérifier l'authentification et les permissions
requireLogin();
if (!checkPermission('communication')) {
    showMessage('error', 'Accès refusé à cette page.');
    redirectTo('../index.php');
}

// Traitement des actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $action = $_POST['action'] ?? '';
        
        if ($action === 'add_template') {
            $nom = trim($_POST['nom'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $sujet = trim($_POST['sujet'] ?? '');
            $contenu = trim($_POST['contenu'] ?? '');
            $type = $_POST['type'] ?? 'email';
            $categorie = $_POST['categorie'] ?? '';
            $variables = $_POST['variables'] ?? '';
            
            // Validation
            if (empty($nom) || empty($contenu)) {
                throw new Exception('Le nom et le contenu sont obligatoires.');
            }
            
            if (!in_array($type, ['email', 'sms', 'notification', 'annonce'])) {
                throw new Exception('Type de template invalide.');
            }
            
            // Traiter les variables
            $variables_array = [];
            if ($variables) {
                $vars = explode(',', $variables);
                foreach ($vars as $var) {
                    $var = trim($var);
                    if ($var) {
                        $variables_array[] = $var;
                    }
                }
            }
            
            $database->execute(
                "INSERT INTO templates_messages (nom, description, sujet, contenu, type, categorie, variables, actif, created_by, created_at) 
                 VALUES (?, ?, ?, ?, ?, ?, ?, 1, ?, NOW())",
                [$nom, $description, $sujet, $contenu, $type, $categorie, json_encode($variables_array), $_SESSION['user_id']]
            );
            
            showMessage('success', 'Template créé avec succès.');
        }
        
        elseif ($action === 'toggle_status') {
            $id = intval($_POST['id'] ?? 0);
            $status = intval($_POST['status'] ?? 0);
            
            $database->execute(
                "UPDATE templates_messages SET actif = ?, updated_at = NOW() WHERE id = ?",
                [$status, $id]
            );
            
            showMessage('success', 'Statut du template mis à jour.');
        }
        
        elseif ($action === 'delete_template') {
            $id = intval($_POST['id'] ?? 0);
            
            $database->execute("DELETE FROM templates_messages WHERE id = ?", [$id]);
            
            showMessage('success', 'Template supprimé avec succès.');
        }
        
    } catch (Exception $e) {
        showMessage('error', 'Erreur : ' . $e->getMessage());
    }
}

// Filtres
$type_filter = $_GET['type'] ?? '';
$categorie_filter = $_GET['categorie'] ?? '';

// Construction de la requête WHERE
$where_conditions = ['1=1'];
$params = [];

if ($type_filter) {
    $where_conditions[] = "type = ?";
    $params[] = $type_filter;
}

if ($categorie_filter) {
    $where_conditions[] = "categorie = ?";
    $params[] = $categorie_filter;
}

$where_clause = implode(' AND ', $where_conditions);

// Récupérer les templates
try {
    $templates = $database->query(
        "SELECT t.*, u.nom as created_by_nom, u.prenom as created_by_prenom
         FROM templates_messages t
         LEFT JOIN users u ON t.created_by = u.id
         WHERE $where_clause
         ORDER BY t.type, t.nom",
        $params
    )->fetchAll();
} catch (Exception $e) {
    $templates = [];
    showMessage('error', 'Erreur lors du chargement : ' . $e->getMessage());
}

// Statistiques
try {
    $stats = $database->query(
        "SELECT 
            COUNT(*) as total,
            COUNT(CASE WHEN actif = 1 THEN 1 END) as actifs,
            COUNT(CASE WHEN type = 'email' THEN 1 END) as emails,
            COUNT(CASE WHEN type = 'sms' THEN 1 END) as sms,
            COUNT(CASE WHEN type = 'notification' THEN 1 END) as notifications,
            COUNT(CASE WHEN type = 'annonce' THEN 1 END) as annonces
         FROM templates_messages"
    )->fetch();
} catch (Exception $e) {
    $stats = ['total' => 0, 'actifs' => 0, 'emails' => 0, 'sms' => 0, 'notifications' => 0, 'annonces' => 0];
}

// Catégories disponibles
try {
    $categories = $database->query(
        "SELECT DISTINCT categorie FROM templates_messages WHERE categorie IS NOT NULL AND categorie != '' ORDER BY categorie"
    )->fetchAll();
} catch (Exception $e) {
    $categories = [];
}

$page_title = "Gestion des Templates";
include '../../../includes/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">
        <i class="fas fa-file-alt me-2"></i>
        Gestion des Templates
    </h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <div class="btn-group me-2">
            <a href="../" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-1"></i>
                Retour à la communication
            </a>
        </div>
        <div class="btn-group me-2">
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addTemplateModal">
                <i class="fas fa-plus me-1"></i>
                Nouveau template
            </button>
        </div>
    </div>
</div>

<!-- Statistiques rapides -->
<div class="row mb-4">
    <div class="col-md-2">
        <div class="card text-center shadow-sm stats-card">
            <div class="card-body">
                <i class="fas fa-file-alt fa-2x text-primary mb-2"></i>
                <h4><?php echo number_format($stats['total'] ?? 0); ?></h4>
                <p class="text-muted mb-0">Total templates</p>
            </div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card text-center shadow-sm stats-card">
            <div class="card-body">
                <i class="fas fa-check-circle fa-2x text-success mb-2"></i>
                <h4><?php echo number_format($stats['actifs'] ?? 0); ?></h4>
                <p class="text-muted mb-0">Actifs</p>
            </div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card text-center shadow-sm stats-card">
            <div class="card-body">
                <i class="fas fa-envelope fa-2x text-info mb-2"></i>
                <h4><?php echo number_format($stats['emails'] ?? 0); ?></h4>
                <p class="text-muted mb-0">Emails</p>
            </div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card text-center shadow-sm stats-card">
            <div class="card-body">
                <i class="fas fa-mobile-alt fa-2x text-warning mb-2"></i>
                <h4><?php echo number_format($stats['sms'] ?? 0); ?></h4>
                <p class="text-muted mb-0">SMS</p>
            </div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card text-center shadow-sm stats-card">
            <div class="card-body">
                <i class="fas fa-bell fa-2x text-secondary mb-2"></i>
                <h4><?php echo number_format($stats['notifications'] ?? 0); ?></h4>
                <p class="text-muted mb-0">Notifications</p>
            </div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card text-center shadow-sm stats-card">
            <div class="card-body">
                <i class="fas fa-bullhorn fa-2x text-danger mb-2"></i>
                <h4><?php echo number_format($stats['annonces'] ?? 0); ?></h4>
                <p class="text-muted mb-0">Annonces</p>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-12">
        <!-- Filtres -->
        <div class="card mb-4 shadow-sm">
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-4">
                        <label for="type" class="form-label">Type</label>
                        <select class="form-select" id="type" name="type">
                            <option value="">Tous les types</option>
                            <option value="email" <?php echo $type_filter === 'email' ? 'selected' : ''; ?>>Email</option>
                            <option value="sms" <?php echo $type_filter === 'sms' ? 'selected' : ''; ?>>SMS</option>
                            <option value="notification" <?php echo $type_filter === 'notification' ? 'selected' : ''; ?>>Notification</option>
                            <option value="annonce" <?php echo $type_filter === 'annonce' ? 'selected' : ''; ?>>Annonce</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label for="categorie" class="form-label">Catégorie</label>
                        <select class="form-select" id="categorie" name="categorie">
                            <option value="">Toutes les catégories</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?php echo htmlspecialchars($cat['categorie']); ?>" 
                                        <?php echo $categorie_filter === $cat['categorie'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars(ucfirst($cat['categorie'])); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">&nbsp;</label>
                        <button type="submit" class="btn btn-primary d-block w-100">
                            <i class="fas fa-filter me-1"></i>
                            Filtrer
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Liste des templates -->
        <div class="card shadow-sm">
            <div class="card-header bg-light">
                <h5 class="mb-0">
                    <i class="fas fa-list me-2"></i>
                    Templates (<?php echo count($templates); ?>)
                </h5>
            </div>
            <div class="card-body">
                <?php if (empty($templates)): ?>
                    <div class="text-center py-5">
                        <i class="fas fa-file-alt fa-3x text-muted mb-3"></i>
                        <h5 class="text-muted">Aucun template trouvé</h5>
                        <p class="text-muted">Créez votre premier template pour gagner du temps.</p>
                        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addTemplateModal">
                            <i class="fas fa-plus me-1"></i>
                            Créer un template
                        </button>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead class="table-light">
                                <tr>
                                    <th>Nom</th>
                                    <th>Type</th>
                                    <th>Catégorie</th>
                                    <th>Sujet</th>
                                    <th>Variables</th>
                                    <th>Statut</th>
                                    <th>Créé par</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($templates as $template): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo htmlspecialchars($template['nom']); ?></strong>
                                            <?php if ($template['description']): ?>
                                                <br><small class="text-muted">
                                                    <?php echo htmlspecialchars(substr($template['description'], 0, 50)); ?>
                                                    <?php if (strlen($template['description']) > 50): ?>...<?php endif; ?>
                                                </small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php
                                            $type_colors = [
                                                'email' => 'primary',
                                                'sms' => 'warning',
                                                'notification' => 'info',
                                                'annonce' => 'success'
                                            ];
                                            $type_icons = [
                                                'email' => 'fas fa-envelope',
                                                'sms' => 'fas fa-mobile-alt',
                                                'notification' => 'fas fa-bell',
                                                'annonce' => 'fas fa-bullhorn'
                                            ];
                                            $color = $type_colors[$template['type']] ?? 'secondary';
                                            $icon = $type_icons[$template['type']] ?? 'fas fa-file';
                                            ?>
                                            <span class="badge bg-<?php echo $color; ?>">
                                                <i class="<?php echo $icon; ?> me-1"></i>
                                                <?php echo ucfirst($template['type']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($template['categorie']): ?>
                                                <span class="badge bg-light text-dark">
                                                    <?php echo htmlspecialchars(ucfirst($template['categorie'])); ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($template['sujet']): ?>
                                                <span class="text-truncate d-inline-block" style="max-width: 150px;" 
                                                      title="<?php echo htmlspecialchars($template['sujet']); ?>">
                                                    <?php echo htmlspecialchars($template['sujet']); ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php 
                                            $variables = json_decode($template['variables'], true);
                                            if ($variables && is_array($variables)):
                                            ?>
                                                <small class="text-muted">
                                                    <?php echo count($variables); ?> variable(s)
                                                    <br><?php echo implode(', ', array_slice($variables, 0, 2)); ?>
                                                    <?php if (count($variables) > 2): ?>...<?php endif; ?>
                                                </small>
                                            <?php else: ?>
                                                <span class="text-muted">Aucune</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <form method="POST" class="d-inline">
                                                <input type="hidden" name="action" value="toggle_status">
                                                <input type="hidden" name="id" value="<?php echo $template['id']; ?>">
                                                <input type="hidden" name="status" value="<?php echo $template['actif'] ? 0 : 1; ?>">
                                                <button type="submit" class="btn btn-sm <?php echo $template['actif'] ? 'btn-success' : 'btn-secondary'; ?>">
                                                    <i class="fas fa-<?php echo $template['actif'] ? 'check' : 'times'; ?>"></i>
                                                    <?php echo $template['actif'] ? 'Actif' : 'Inactif'; ?>
                                                </button>
                                            </form>
                                        </td>
                                        <td>
                                            <small class="text-muted">
                                                <?php echo htmlspecialchars($template['created_by_nom'] . ' ' . $template['created_by_prenom']); ?>
                                                <br><?php echo formatDate($template['created_at']); ?>
                                            </small>
                                        </td>
                                        <td>
                                            <div class="btn-group" role="group">
                                                <button type="button" class="btn btn-sm btn-outline-primary" 
                                                        onclick="viewTemplate(<?php echo htmlspecialchars(json_encode($template)); ?>)" 
                                                        title="Voir détails">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                                <button type="button" class="btn btn-sm btn-outline-warning" 
                                                        onclick="editTemplate(<?php echo htmlspecialchars(json_encode($template)); ?>)" 
                                                        title="Modifier">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <form method="POST" class="d-inline" onsubmit="return confirm('Êtes-vous sûr de vouloir supprimer ce template ?')">
                                                    <input type="hidden" name="action" value="delete_template">
                                                    <input type="hidden" name="id" value="<?php echo $template['id']; ?>">
                                                    <button type="submit" class="btn btn-sm btn-outline-danger" title="Supprimer">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
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

<!-- Modal d'ajout de template -->
<div class="modal fade" id="addTemplateModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="add_template">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-plus me-2"></i>
                        Nouveau Template
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="nom" class="form-label">Nom du template <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="nom" name="nom" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="type" class="form-label">Type <span class="text-danger">*</span></label>
                            <select class="form-select" id="type" name="type" required>
                                <option value="email">Email</option>
                                <option value="sms">SMS</option>
                                <option value="notification">Notification</option>
                                <option value="annonce">Annonce</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="categorie" class="form-label">Catégorie</label>
                            <input type="text" class="form-control" id="categorie" name="categorie" 
                                   placeholder="ex: discipline, pedagogique, administrative">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="variables" class="form-label">Variables</label>
                            <input type="text" class="form-control" id="variables" name="variables" 
                                   placeholder="eleve_nom, date, classe (séparées par des virgules)">
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="description" class="form-label">Description</label>
                        <textarea class="form-control" id="description" name="description" rows="2" 
                                  placeholder="Description du template"></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label for="sujet" class="form-label">Sujet (pour emails)</label>
                        <input type="text" class="form-control" id="sujet" name="sujet" 
                               placeholder="Sujet du message">
                    </div>
                    
                    <div class="mb-3">
                        <label for="contenu" class="form-label">Contenu <span class="text-danger">*</span></label>
                        <textarea class="form-control" id="contenu" name="contenu" rows="6" required
                                  placeholder="Contenu du template avec variables {variable_nom}"></textarea>
                        <div class="form-text">
                            Utilisez {nom_variable} pour insérer des variables dynamiques
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times me-1"></i>
                        Annuler
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-1"></i>
                        Créer le template
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal de visualisation -->
<div class="modal fade" id="viewTemplateModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-eye me-2"></i>
                    Détails du Template
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="templateViewContent">
                <!-- Contenu dynamique -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="fas fa-times me-1"></i>
                    Fermer
                </button>
            </div>
        </div>
    </div>
</div>

<script>
function viewTemplate(template) {
    const variables = JSON.parse(template.variables || '[]');
    
    const content = `
        <div class="row">
            <div class="col-md-6">
                <h6>Informations générales</h6>
                <table class="table table-sm">
                    <tr><td><strong>Nom:</strong></td><td>${template.nom}</td></tr>
                    <tr><td><strong>Type:</strong></td><td><span class="badge bg-primary">${template.type}</span></td></tr>
                    <tr><td><strong>Catégorie:</strong></td><td>${template.categorie || 'Aucune'}</td></tr>
                    <tr><td><strong>Statut:</strong></td><td><span class="badge bg-${template.actif ? 'success' : 'secondary'}">${template.actif ? 'Actif' : 'Inactif'}</span></td></tr>
                </table>
            </div>
            <div class="col-md-6">
                <h6>Variables disponibles</h6>
                ${variables.length > 0 ? 
                    '<div class="d-flex flex-wrap gap-1">' + 
                    variables.map(v => `<span class="badge bg-light text-dark">{${v}}</span>`).join(' ') + 
                    '</div>' : 
                    '<p class="text-muted">Aucune variable définie</p>'
                }
            </div>
        </div>
        ${template.description ? `
        <div class="row mt-3">
            <div class="col-12">
                <h6>Description</h6>
                <p class="text-muted">${template.description}</p>
            </div>
        </div>
        ` : ''}
        ${template.sujet ? `
        <div class="row mt-3">
            <div class="col-12">
                <h6>Sujet</h6>
                <div class="border p-2 bg-light rounded">
                    ${template.sujet}
                </div>
            </div>
        </div>
        ` : ''}
        <div class="row mt-3">
            <div class="col-12">
                <h6>Contenu</h6>
                <div class="border p-3 bg-light rounded">
                    ${template.contenu.replace(/\n/g, '<br>')}
                </div>
            </div>
        </div>
    `;
    
    document.getElementById('templateViewContent').innerHTML = content;
    const modal = new bootstrap.Modal(document.getElementById('viewTemplateModal'));
    modal.show();
}

function editTemplate(template) {
    // Implémenter l'édition de template
    alert('Fonctionnalité d\'édition à implémenter');
}

// Styles pour les cartes statistiques
const style = document.createElement('style');
style.textContent = `
    .stats-card {
        transition: all 0.2s ease-in-out;
    }
    .stats-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15) !important;
    }
`;
document.head.appendChild(style);
</script>

<?php include '../../../includes/footer.php'; ?>
