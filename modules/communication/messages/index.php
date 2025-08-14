<?php
/**
 * Module Communication - Liste des messages
 * Application de gestion scolaire - République Démocratique du Congo
 */

require_once '../../../config/config.php';
require_once '../../../config/database.php';
require_once '../../../includes/functions.php';

// Vérifier l'authentification et les permissions
requireLogin();
if (!checkPermission('communication')) {
    showMessage('error', 'Accès refusé à cette page.');
    redirectTo('../../../index.php');
}

// Vérifier et créer la table messages si nécessaire
try {
    $tables = $database->query("SHOW TABLES LIKE 'messages'")->fetch();
    if (!$tables) {
        $database->execute("
            CREATE TABLE IF NOT EXISTS messages (
                id INT PRIMARY KEY AUTO_INCREMENT,
                expediteur_id INT NOT NULL,
                destinataire_id INT NULL,
                destinataire_type ENUM('personnel', 'eleve', 'parent', 'classe', 'niveau', 'tous', 'custom') NOT NULL,
                destinataires_custom TEXT NULL,
                sujet VARCHAR(255) NOT NULL,
                contenu TEXT NOT NULL,
                type_message ENUM('info', 'urgent', 'rappel', 'felicitation') DEFAULT 'info',
                priorite ENUM('basse', 'normale', 'haute', 'urgente') DEFAULT 'normale',
                date_envoi DATETIME NULL,
                programme TINYINT(1) DEFAULT 0,
                date_programmee DATETIME NULL,
                status ENUM('brouillon', 'programme', 'envoye', 'lu', 'archive') DEFAULT 'brouillon',
                accuse_reception TINYINT(1) DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            )
        ");
    }
} catch (Exception $e) {
    // Ignorer les erreurs de création de table
}

$errors = [];

// Traitement des actions en lot
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_action'])) {
    $bulk_action = $_POST['bulk_action'] ?? '';
    $message_ids = $_POST['message_ids'] ?? [];
    
    if (!empty($message_ids) && is_array($message_ids)) {
        $message_ids = array_map('intval', $message_ids);
        
        try {
            $database->beginTransaction();
            
            switch ($bulk_action) {
                case 'mark_read':
                    $placeholders = str_repeat('?,', count($message_ids) - 1) . '?';
                    $database->execute(
                        "UPDATE messages SET status = 'lu' WHERE id IN ($placeholders)",
                        $message_ids
                    );
                    showMessage('success', count($message_ids) . ' message(s) marqué(s) comme lu(s).');
                    break;
                    
                case 'archive':
                    $placeholders = str_repeat('?,', count($message_ids) - 1) . '?';
                    $database->execute(
                        "UPDATE messages SET status = 'archive' WHERE id IN ($placeholders)",
                        $message_ids
                    );
                    showMessage('success', count($message_ids) . ' message(s) archivé(s).');
                    break;
                    
                case 'delete':
                    $placeholders = str_repeat('?,', count($message_ids) - 1) . '?';
                    $database->execute(
                        "DELETE FROM messages WHERE id IN ($placeholders)",
                        $message_ids
                    );
                    showMessage('success', count($message_ids) . ' message(s) supprimé(s).');
                    break;
            }
            
            $database->commit();
            
        } catch (Exception $e) {
            $database->rollback();
            $errors[] = 'Erreur lors de l\'action en lot : ' . $e->getMessage();
        }
    }
}

// Paramètres de filtrage et pagination
$view = $_GET['view'] ?? 'received'; // received, sent, programmed, archived
$search = trim($_GET['search'] ?? '');
$status_filter = $_GET['status'] ?? '';
$type_filter = $_GET['type'] ?? '';
$priorite_filter = $_GET['priorite'] ?? '';
$date_debut = $_GET['date_debut'] ?? '';
$date_fin = $_GET['date_fin'] ?? '';
$page = max(1, intval($_GET['page'] ?? 1));
$per_page = 25;
$offset = ($page - 1) * $per_page;

// Construction de la requête avec filtres
$where_conditions = ["1=1"];
$params = [];

// Filtres selon la vue
switch ($view) {
    case 'sent':
        $where_conditions[] = "m.expediteur_id = ?";
        $params[] = $_SESSION['user_id'];
        break;
    case 'programmed':
        $where_conditions[] = "m.expediteur_id = ? AND m.status = 'programme'";
        $params[] = $_SESSION['user_id'];
        break;
    case 'archived':
        $where_conditions[] = "(m.expediteur_id = ? OR m.destinataire_id = ?) AND m.status = 'archive'";
        $params[] = $_SESSION['user_id'];
        $params[] = $_SESSION['user_id'];
        break;
    default: // received
        $where_conditions[] = "m.destinataire_id = ? OR m.destinataire_type IN ('tous', 'personnel')";
        $params[] = $_SESSION['user_id'];
        break;
}

if ($search) {
    $where_conditions[] = "(m.sujet LIKE ? OR m.contenu LIKE ? OR u_exp.nom LIKE ? OR u_exp.prenom LIKE ?)";
    $search_param = "%$search%";
    $params = array_merge($params, [$search_param, $search_param, $search_param, $search_param]);
}

if ($status_filter) {
    $where_conditions[] = "m.status = ?";
    $params[] = $status_filter;
}

if ($type_filter) {
    $where_conditions[] = "m.type_message = ?";
    $params[] = $type_filter;
}

if ($priorite_filter) {
    $where_conditions[] = "m.priorite = ?";
    $params[] = $priorite_filter;
}

if ($date_debut) {
    $where_conditions[] = "DATE(COALESCE(m.date_envoi, m.created_at)) >= ?";
    $params[] = $date_debut;
}

if ($date_fin) {
    $where_conditions[] = "DATE(COALESCE(m.date_envoi, m.created_at)) <= ?";
    $params[] = $date_fin;
}

$where_clause = implode(' AND ', $where_conditions);

// Récupérer les messages avec pagination
try {
    $sql = "SELECT m.*, 
                   u_exp.nom as expediteur_nom, u_exp.prenom as expediteur_prenom,
                   u_dest.nom as destinataire_nom, u_dest.prenom as destinataire_prenom,
                   DATEDIFF(NOW(), COALESCE(m.date_envoi, m.created_at)) as jours_depuis
            FROM messages m
            LEFT JOIN users u_exp ON m.expediteur_id = u_exp.id
            LEFT JOIN users u_dest ON m.destinataire_id = u_dest.id
            WHERE $where_clause
            ORDER BY COALESCE(m.date_envoi, m.created_at) DESC, m.id DESC
            LIMIT $per_page OFFSET $offset";
    
    $messages = $database->query($sql, $params)->fetchAll();
    
    // Compter le total pour la pagination
    $total_sql = "SELECT COUNT(*) as total 
                  FROM messages m
                  LEFT JOIN users u_exp ON m.expediteur_id = u_exp.id
                  LEFT JOIN users u_dest ON m.destinataire_id = u_dest.id
                  WHERE $where_clause";
    
    $total = $database->query($total_sql, $params)->fetch()['total'];
    $total_pages = ceil($total / $per_page);
    
} catch (Exception $e) {
    $messages = [];
    $total = 0;
    $total_pages = 0;
    $errors[] = 'Erreur lors du chargement des messages : ' . $e->getMessage();
}

// Statistiques rapides
try {
    $stats = $database->query(
        "SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN m.status = 'envoye' AND m.destinataire_id = ? THEN 1 ELSE 0 END) as recus,
            SUM(CASE WHEN m.status = 'envoye' AND m.expediteur_id = ? THEN 1 ELSE 0 END) as envoyes,
            SUM(CASE WHEN m.status = 'programme' AND m.expediteur_id = ? THEN 1 ELSE 0 END) as programmes,
            SUM(CASE WHEN m.status = 'archive' AND (m.expediteur_id = ? OR m.destinataire_id = ?) THEN 1 ELSE 0 END) as archives
         FROM messages m
         WHERE m.expediteur_id = ? OR m.destinataire_id = ? OR m.destinataire_type IN ('tous', 'personnel')",
        [$_SESSION['user_id'], $_SESSION['user_id'], $_SESSION['user_id'], $_SESSION['user_id'], $_SESSION['user_id'], $_SESSION['user_id'], $_SESSION['user_id']]
    )->fetch();
} catch (Exception $e) {
    $stats = ['total' => 0, 'recus' => 0, 'envoyes' => 0, 'programmes' => 0, 'archives' => 0];
}

$page_title = "Messagerie";
include '../../../includes/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">
        <i class="fas fa-envelope me-2 text-primary"></i>
        Messagerie
        <span class="badge bg-secondary ms-2"><?php echo number_format($total ?? 0); ?></span>
    </h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <div class="btn-group me-2">
            <a href="compose.php" class="btn btn-primary">
                <i class="fas fa-pen me-1"></i>
                Nouveau message
            </a>
        </div>
        <div class="btn-group me-2">
            <button type="button" class="btn btn-outline-success" onclick="exportData()">
                <i class="fas fa-file-excel me-1"></i>
                Exporter
            </button>
        </div>
        <div class="btn-group">
            <a href="../index.php" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-1"></i>
                Retour
            </a>
        </div>
    </div>
</div>

<?php if (!empty($errors)): ?>
    <div class="alert alert-danger">
        <h6><i class="fas fa-exclamation-circle me-1"></i> Erreurs :</h6>
        <ul class="mb-0">
            <?php foreach ($errors as $error): ?>
                <li><?php echo htmlspecialchars($error); ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<!-- Navigation par onglets -->
<div class="row mb-4">
    <div class="col-12">
        <ul class="nav nav-tabs">
            <li class="nav-item">
                <a class="nav-link <?php echo ($view === 'received') ? 'active' : ''; ?>" 
                   href="?view=received">
                    <i class="fas fa-inbox me-1"></i>
                    Reçus
                    <span class="badge bg-primary ms-1"><?php echo number_format($stats['recus'] ?? 0); ?></span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo ($view === 'sent') ? 'active' : ''; ?>" 
                   href="?view=sent">
                    <i class="fas fa-paper-plane me-1"></i>
                    Envoyés
                    <span class="badge bg-success ms-1"><?php echo number_format($stats['envoyes'] ?? 0); ?></span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo ($view === 'programmed') ? 'active' : ''; ?>" 
                   href="?view=programmed">
                    <i class="fas fa-clock me-1"></i>
                    Programmés
                    <span class="badge bg-warning ms-1"><?php echo number_format($stats['programmes'] ?? 0); ?></span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo ($view === 'archived') ? 'active' : ''; ?>" 
                   href="?view=archived">
                    <i class="fas fa-archive me-1"></i>
                    Archivés
                    <span class="badge bg-secondary ms-1"><?php echo number_format($stats['archives'] ?? 0); ?></span>
                </a>
            </li>
        </ul>
    </div>
</div>

<!-- Filtres -->
<div class="card shadow-sm mb-4">
    <div class="card-header bg-light">
        <h6 class="mb-0">
            <i class="fas fa-filter me-2"></i>
            Filtres de recherche
        </h6>
    </div>
    <div class="card-body">
        <form method="GET" class="row g-3">
            <input type="hidden" name="view" value="<?php echo htmlspecialchars($view); ?>">
            
            <div class="col-md-3">
                <label for="search" class="form-label">Recherche</label>
                <input type="text" class="form-control" id="search" name="search" 
                       value="<?php echo htmlspecialchars($search); ?>" 
                       placeholder="Sujet, contenu, expéditeur...">
            </div>
            
            <div class="col-md-2">
                <label for="status" class="form-label">Statut</label>
                <select class="form-select" id="status" name="status">
                    <option value="">Tous les statuts</option>
                    <option value="brouillon" <?php echo ($status_filter === 'brouillon') ? 'selected' : ''; ?>>
                        📝 Brouillon
                    </option>
                    <option value="programme" <?php echo ($status_filter === 'programme') ? 'selected' : ''; ?>>
                        ⏰ Programmé
                    </option>
                    <option value="envoye" <?php echo ($status_filter === 'envoye') ? 'selected' : ''; ?>>
                        ✅ Envoyé
                    </option>
                    <option value="lu" <?php echo ($status_filter === 'lu') ? 'selected' : ''; ?>>
                        👁️ Lu
                    </option>
                    <option value="archive" <?php echo ($status_filter === 'archive') ? 'selected' : ''; ?>>
                        📁 Archivé
                    </option>
                </select>
            </div>
            
            <div class="col-md-2">
                <label for="type" class="form-label">Type</label>
                <select class="form-select" id="type" name="type">
                    <option value="">Tous les types</option>
                    <option value="info" <?php echo ($type_filter === 'info') ? 'selected' : ''; ?>>
                        ℹ️ Information
                    </option>
                    <option value="urgent" <?php echo ($type_filter === 'urgent') ? 'selected' : ''; ?>>
                        🚨 Urgent
                    </option>
                    <option value="rappel" <?php echo ($type_filter === 'rappel') ? 'selected' : ''; ?>>
                        🔔 Rappel
                    </option>
                    <option value="felicitation" <?php echo ($type_filter === 'felicitation') ? 'selected' : ''; ?>>
                        🎉 Félicitation
                    </option>
                </select>
            </div>
            
            <div class="col-md-2">
                <label for="priorite" class="form-label">Priorité</label>
                <select class="form-select" id="priorite" name="priorite">
                    <option value="">Toutes les priorités</option>
                    <option value="basse" <?php echo ($priorite_filter === 'basse') ? 'selected' : ''; ?>>
                        🟢 Basse
                    </option>
                    <option value="normale" <?php echo ($priorite_filter === 'normale') ? 'selected' : ''; ?>>
                        🟡 Normale
                    </option>
                    <option value="haute" <?php echo ($priorite_filter === 'haute') ? 'selected' : ''; ?>>
                        🟠 Haute
                    </option>
                    <option value="urgente" <?php echo ($priorite_filter === 'urgente') ? 'selected' : ''; ?>>
                        🔴 Urgente
                    </option>
                </select>
            </div>
            
            <div class="col-md-3">
                <label class="form-label">Période</label>
                <div class="row g-1">
                    <div class="col-6">
                        <input type="date" class="form-control form-control-sm" name="date_debut" 
                               value="<?php echo htmlspecialchars($date_debut); ?>" placeholder="Du">
                    </div>
                    <div class="col-6">
                        <input type="date" class="form-control form-control-sm" name="date_fin" 
                               value="<?php echo htmlspecialchars($date_fin); ?>" placeholder="Au">
                    </div>
                </div>
            </div>
            
            <div class="col-12">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-search me-1"></i>
                    Filtrer
                </button>
                <a href="?view=<?php echo htmlspecialchars($view); ?>" class="btn btn-outline-secondary">
                    <i class="fas fa-undo me-1"></i>
                    Reset
                </a>
            </div>
        </form>
    </div>
</div>

<!-- Actions en lot -->
<?php if (!empty($messages)): ?>
<div class="card shadow-sm mb-4">
    <div class="card-body">
        <form method="POST" id="bulkForm">
            <div class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label for="bulk_action" class="form-label">Actions en lot</label>
                    <select class="form-select" id="bulk_action" name="bulk_action">
                        <option value="">Sélectionner une action...</option>
                        <option value="mark_read">Marquer comme lu</option>
                        <option value="archive">Archiver</option>
                        <option value="delete">Supprimer</option>
                    </select>
                </div>
                
                <div class="col-md-3">
                    <button type="submit" class="btn btn-warning" onclick="return confirmBulkAction()">
                        <i class="fas fa-cogs me-1"></i>
                        Appliquer
                    </button>
                </div>
                
                <div class="col-md-6 text-end">
                    <small class="text-muted">
                        <span id="selected-count">0</span> message(s) sélectionné(s)
                    </small>
                </div>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<!-- Liste des messages -->
<div class="card shadow-sm">
    <div class="card-body">
        <?php if (empty($messages)): ?>
            <div class="text-center py-5">
                <i class="fas fa-envelope fa-3x text-muted mb-3"></i>
                <h5 class="text-muted">Aucun message trouvé</h5>
                <p class="text-muted">
                    <?php if ($search || $status_filter || $type_filter || $priorite_filter || $date_debut || $date_fin): ?>
                        Essayez de modifier vos critères de recherche.
                    <?php else: ?>
                        <?php if ($view === 'sent'): ?>
                            Vous n'avez envoyé aucun message pour le moment.
                        <?php elseif ($view === 'programmed'): ?>
                            Aucun message programmé.
                        <?php elseif ($view === 'archived'): ?>
                            Aucun message archivé.
                        <?php else: ?>
                            Vous n'avez reçu aucun message pour le moment.
                        <?php endif; ?>
                    <?php endif; ?>
                </p>
                <?php if (!$search && !$status_filter && !$type_filter && !$priorite_filter && !$date_debut && !$date_fin): ?>
                    <a href="compose.php" class="btn btn-primary">
                        <i class="fas fa-pen me-1"></i>
                        Composer un message
                    </a>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover" id="messagesTable">
                    <thead class="table-light">
                        <tr>
                            <th width="30">
                                <input type="checkbox" id="select-all" class="form-check-input">
                            </th>
                            <th>Date</th>
                            <th><?php echo ($view === 'sent') ? 'Destinataire' : 'Expéditeur'; ?></th>
                            <th>Sujet</th>
                            <th>Type</th>
                            <th>Priorité</th>
                            <th>Statut</th>
                            <th width="100">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($messages as $message): ?>
                            <tr class="<?php echo ($view === 'received' && $message['status'] === 'envoye') ? 'table-warning' : ''; ?>">
                                <td>
                                    <input type="checkbox" name="message_ids[]" value="<?php echo $message['id']; ?>"
                                           class="form-check-input message-checkbox">
                                </td>
                                <td>
                                    <div>
                                        <?php if ($message['programme'] && $message['date_programmee']): ?>
                                            <strong><?php echo formatDateTime($message['date_programmee'], 'd/m/Y H:i'); ?></strong>
                                            <br><small class="text-muted">Programmé</small>
                                        <?php elseif ($message['date_envoi']): ?>
                                            <strong><?php echo formatDateTime($message['date_envoi'], 'd/m/Y H:i'); ?></strong>
                                            <br><small class="text-muted">
                                                <?php if ($message['jours_depuis'] == 0): ?>
                                                    Aujourd'hui
                                                <?php elseif ($message['jours_depuis'] == 1): ?>
                                                    Hier
                                                <?php else: ?>
                                                    Il y a <?php echo $message['jours_depuis']; ?> jour(s)
                                                <?php endif; ?>
                                            </small>
                                        <?php else: ?>
                                            <strong><?php echo formatDateTime($message['created_at'], 'd/m/Y H:i'); ?></strong>
                                            <br><small class="text-muted">Créé</small>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <div>
                                        <?php if ($view === 'sent'): ?>
                                            <?php if ($message['destinataire_type'] === 'custom'): ?>
                                                <strong>Destinataires multiples</strong>
                                                <br><small class="text-muted">Personnalisé</small>
                                            <?php elseif ($message['destinataire_type'] === 'tous'): ?>
                                                <strong>Tous les utilisateurs</strong>
                                                <br><small class="text-muted">Diffusion générale</small>
                                            <?php elseif ($message['destinataire_nom']): ?>
                                                <strong><?php echo htmlspecialchars($message['destinataire_nom'] . ' ' . $message['destinataire_prenom']); ?></strong>
                                                <br><small class="text-muted"><?php echo ucfirst($message['destinataire_type']); ?></small>
                                            <?php else: ?>
                                                <strong><?php echo ucfirst($message['destinataire_type']); ?></strong>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <strong><?php echo htmlspecialchars($message['expediteur_nom'] . ' ' . $message['expediteur_prenom']); ?></strong>
                                            <br><small class="text-muted">Expéditeur</small>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <div class="message-subject">
                                        <strong><?php echo htmlspecialchars($message['sujet']); ?></strong>
                                        <br><small class="text-muted">
                                            <?php echo htmlspecialchars(substr($message['contenu'], 0, 50)); ?>
                                            <?php if (strlen($message['contenu']) > 50): ?>...<?php endif; ?>
                                        </small>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge bg-<?php
                                        echo match($message['type_message']) {
                                            'info' => 'info',
                                            'urgent' => 'danger',
                                            'rappel' => 'warning',
                                            'felicitation' => 'success',
                                            default => 'secondary'
                                        };
                                    ?>">
                                        <?php
                                        echo match($message['type_message']) {
                                            'info' => 'ℹ️ Info',
                                            'urgent' => '🚨 Urgent',
                                            'rappel' => '🔔 Rappel',
                                            'felicitation' => '🎉 Félicitation',
                                            default => ucfirst($message['type_message'])
                                        };
                                        ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge bg-<?php
                                        echo match($message['priorite']) {
                                            'basse' => 'success',
                                            'normale' => 'secondary',
                                            'haute' => 'warning',
                                            'urgente' => 'danger',
                                            default => 'secondary'
                                        };
                                    ?>">
                                        <?php
                                        echo match($message['priorite']) {
                                            'basse' => '🟢 Basse',
                                            'normale' => '🟡 Normale',
                                            'haute' => '🟠 Haute',
                                            'urgente' => '🔴 Urgente',
                                            default => ucfirst($message['priorite'])
                                        };
                                        ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge bg-<?php
                                        echo match($message['status']) {
                                            'brouillon' => 'secondary',
                                            'programme' => 'warning',
                                            'envoye' => 'success',
                                            'lu' => 'info',
                                            'archive' => 'dark',
                                            default => 'secondary'
                                        };
                                    ?>">
                                        <?php
                                        echo match($message['status']) {
                                            'brouillon' => '📝 Brouillon',
                                            'programme' => '⏰ Programmé',
                                            'envoye' => '✅ Envoyé',
                                            'lu' => '👁️ Lu',
                                            'archive' => '📁 Archivé',
                                            default => ucfirst($message['status'])
                                        };
                                        ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <a href="view.php?id=<?php echo $message['id']; ?>"
                                           class="btn btn-outline-primary" title="Voir le message">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <?php if ($message['expediteur_id'] == $_SESSION['user_id'] && $message['status'] === 'brouillon'): ?>
                                            <a href="compose.php?edit=<?php echo $message['id']; ?>"
                                               class="btn btn-outline-warning" title="Modifier">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                        <?php endif; ?>
                                        <a href="compose.php?reply=<?php echo $message['id']; ?>"
                                           class="btn btn-outline-success" title="Répondre">
                                            <i class="fas fa-reply"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
                <nav aria-label="Navigation des messages" class="mt-4">
                    <ul class="pagination justify-content-center">
                        <?php if ($page > 1): ?>
                            <li class="page-item">
                                <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>">
                                    <i class="fas fa-chevron-left"></i>
                                </a>
                            </li>
                        <?php endif; ?>

                        <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                            <li class="page-item <?php echo ($i === $page) ? 'active' : ''; ?>">
                                <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>">
                                    <?php echo $i; ?>
                                </a>
                            </li>
                        <?php endfor; ?>

                        <?php if ($page < $total_pages): ?>
                            <li class="page-item">
                                <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>">
                                    <i class="fas fa-chevron-right"></i>
                                </a>
                            </li>
                        <?php endif; ?>
                    </ul>

                    <div class="text-center text-muted">
                        Page <?php echo $page; ?> sur <?php echo $total_pages; ?>
                        (<?php echo number_format($total ?? 0); ?> message<?php echo ($total ?? 0) > 1 ? 's' : ''; ?> au total)
                    </div>
                </nav>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<style>
.message-subject {
    max-width: 300px;
}

.table-warning {
    background-color: rgba(255, 193, 7, 0.1) !important;
}

@media print {
    .btn-toolbar, .card:first-child, .card:nth-child(2), .no-print {
        display: none !important;
    }
}
</style>

<script>
// Gestion de la sélection multiple
document.getElementById('select-all').addEventListener('change', function() {
    const checkboxes = document.querySelectorAll('.message-checkbox');
    checkboxes.forEach(checkbox => {
        checkbox.checked = this.checked;
    });
    updateSelectedCount();
});

document.querySelectorAll('.message-checkbox').forEach(checkbox => {
    checkbox.addEventListener('change', updateSelectedCount);
});

function updateSelectedCount() {
    const selected = document.querySelectorAll('.message-checkbox:checked').length;
    document.getElementById('selected-count').textContent = selected;

    // Mettre à jour l'état du checkbox "Tout sélectionner"
    const selectAll = document.getElementById('select-all');
    const total = document.querySelectorAll('.message-checkbox').length;
    selectAll.indeterminate = selected > 0 && selected < total;
    selectAll.checked = selected === total && total > 0;
}

function confirmBulkAction() {
    const selected = document.querySelectorAll('.message-checkbox:checked').length;
    if (selected === 0) {
        alert('Veuillez sélectionner au moins un message.');
        return false;
    }

    const action = document.getElementById('bulk_action').value;
    if (!action) {
        alert('Veuillez sélectionner une action.');
        return false;
    }

    let message = `Êtes-vous sûr de vouloir appliquer cette action à ${selected} message(s) ?`;
    if (action === 'delete') {
        message = `Êtes-vous sûr de vouloir supprimer définitivement ${selected} message(s) ?`;
    }

    return confirm(message);
}

function exportData() {
    const params = new URLSearchParams(window.location.search);
    params.set('export', 'excel');
    window.location.href = 'index.php?' + params.toString();
}

// Initialiser le compteur
updateSelectedCount();
</script>

<?php include '../../../includes/footer.php'; ?>
