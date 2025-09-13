<?php
/**
 * Module Communication - Visualiser un message
 * Application de gestion scolaire - République Démocratique du Congo
 */

require_once '../../../config/config.php';
require_once '../../../config/database.php';
require_once '../../../includes/functions.php';
require_once '../../../includes/permissions-pages.php';

// Vérifier l'authentification et les permissions
requireLogin();
requirePagePermissionFromDB('communication', 'messages', 'read', '../../../dashboard.php');

$message_id = intval($_GET['id'] ?? 0);

if (!$message_id) {
    showMessage('error', 'ID de message invalide.');
    redirectTo('index.php');
}

// Traitement des actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    try {
        switch ($action) {
            case 'mark_read':
                $database->execute(
                    "UPDATE messages SET status = 'lu', updated_at = NOW() WHERE id = ?",
                    [$message_id]
                );
                showMessage('success', 'Message marqué comme lu.');
                break;
                
            case 'archive':
                $database->execute(
                    "UPDATE messages SET status = 'archive', updated_at = NOW() WHERE id = ?",
                    [$message_id]
                );
                showMessage('success', 'Message archivé.');
                break;
                
            case 'delete':
                $database->execute(
                    "DELETE FROM messages WHERE id = ?",
                    [$message_id]
                );
                showMessage('success', 'Message supprimé.');
                redirectTo('index.php');
                break;
        }
    } catch (Exception $e) {
        showMessage('error', 'Erreur lors de l\'action : ' . $e->getMessage());
    }
}

// Récupérer les détails du message
try {
    $sql = "SELECT m.*, 
                   u_exp.nom as expediteur_nom, u_exp.prenom as expediteur_prenom, u_exp.email as expediteur_email,
                   u_dest.nom as destinataire_nom, u_dest.prenom as destinataire_prenom, u_dest.email as destinataire_email,
                   DATEDIFF(NOW(), COALESCE(m.date_envoi, m.created_at)) as jours_depuis
            FROM messages m
            LEFT JOIN users u_exp ON m.expediteur_id = u_exp.id
            LEFT JOIN users u_dest ON m.destinataire_id = u_dest.id
            WHERE m.id = ?";
    
    $message = $database->query($sql, [$message_id])->fetch();
    
    if (!$message) {
        showMessage('error', 'Message non trouvé.');
        redirectTo('index.php');
    }
    
    // Vérifier les permissions d'accès au message
    $can_view = false;
    if ($message['expediteur_id'] == $_SESSION['user_id']) {
        $can_view = true; // Expéditeur
    } elseif ($message['destinataire_id'] == $_SESSION['user_id']) {
        $can_view = true; // Destinataire direct
    } elseif (in_array($message['destinataire_type'], ['tous', 'personnel'])) {
        $can_view = true; // Message public ou pour le personnel
    }
    
    if (!$can_view && !hasPagePermissionFromDB('admin', 'users', 'read')) {
        showMessage('error', 'Vous n\'avez pas l\'autorisation de voir ce message.');
        redirectTo('index.php');
    }
    
    // Marquer automatiquement comme lu si c'est le destinataire et que le message est "envoyé"
    if ($message['destinataire_id'] == $_SESSION['user_id'] && $message['status'] === 'envoye') {
        $database->execute(
            "UPDATE messages SET status = 'lu', updated_at = NOW() WHERE id = ?",
            [$message_id]
        );
        $message['status'] = 'lu'; // Mettre à jour localement
    }
    
} catch (Exception $e) {
    showMessage('error', 'Erreur lors du chargement du message : ' . $e->getMessage());
    redirectTo('index.php');
}

// Récupérer les destinataires personnalisés si applicable
$destinataires_custom = [];
if ($message['destinataire_type'] === 'custom' && !empty($message['destinataires_custom'])) {
    try {
        $destinataires_ids = json_decode($message['destinataires_custom'], true);
        if (is_array($destinataires_ids) && !empty($destinataires_ids)) {
            $placeholders = str_repeat('?,', count($destinataires_ids) - 1) . '?';
            $destinataires_custom = $database->query(
                "SELECT id, nom, prenom, email FROM users WHERE id IN ($placeholders)",
                $destinataires_ids
            )->fetchAll();
        }
    } catch (Exception $e) {
        // Ignorer les erreurs de parsing JSON
    }
}

$page_title = "Message - " . htmlspecialchars($message['sujet']);
include '../../../includes/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">
        <i class="fas fa-envelope-open me-2 text-primary"></i>
        Message
    </h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <div class="btn-group me-2">
            <a href="index.php" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-1"></i>
                Retour à la liste
            </a>
        </div>
        <div class="btn-group me-2">
            <a href="compose.php?reply=<?php echo $message_id; ?>" class="btn btn-success">
                <i class="fas fa-reply me-1"></i>
                Répondre
            </a>
        </div>
        <div class="btn-group">
            <button type="button" class="btn btn-outline-primary" onclick="window.print()">
                <i class="fas fa-print me-1"></i>
                Imprimer
            </button>
        </div>
    </div>
</div>

<div class="row">
    <!-- Contenu principal du message -->
    <div class="col-lg-8">
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-light">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <h5 class="mb-1"><?php echo htmlspecialchars($message['sujet']); ?></h5>
                        <div class="d-flex align-items-center gap-2 mt-2">
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
                                    'info' => 'ℹ️ Information',
                                    'urgent' => '🚨 Urgent',
                                    'rappel' => '🔔 Rappel',
                                    'felicitation' => '🎉 Félicitation',
                                    default => ucfirst($message['type_message'])
                                };
                                ?>
                            </span>
                            
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
                                    'basse' => '🟢 Priorité basse',
                                    'normale' => '🟡 Priorité normale',
                                    'haute' => '🟠 Priorité haute',
                                    'urgente' => '🔴 Priorité urgente',
                                    default => ucfirst($message['priorite'])
                                };
                                ?>
                            </span>
                        </div>
                    </div>
                    
                    <span class="badge bg-<?php 
                        echo match($message['status']) {
                            'brouillon' => 'secondary',
                            'programme' => 'warning',
                            'envoye' => 'success',
                            'lu' => 'info',
                            'archive' => 'dark',
                            default => 'secondary'
                        };
                    ?> fs-6">
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
                </div>
            </div>
            <div class="card-body">
                <!-- Informations d'en-tête -->
                <div class="row mb-4">
                    <div class="col-md-6">
                        <h6 class="text-primary">Expéditeur</h6>
                        <div class="d-flex align-items-center">
                            <i class="fas fa-user-circle fa-2x text-muted me-2"></i>
                            <div>
                                <strong><?php echo htmlspecialchars($message['expediteur_nom'] . ' ' . $message['expediteur_prenom']); ?></strong>
                                <?php if ($message['expediteur_email']): ?>
                                    <br><small class="text-muted"><?php echo htmlspecialchars($message['expediteur_email']); ?></small>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <h6 class="text-primary">Destinataire(s)</h6>
                        <div class="d-flex align-items-start">
                            <i class="fas fa-users fa-2x text-muted me-2"></i>
                            <div>
                                <?php if ($message['destinataire_type'] === 'custom' && !empty($destinataires_custom)): ?>
                                    <strong>Destinataires multiples (<?php echo count($destinataires_custom); ?>)</strong>
                                    <br><small class="text-muted">
                                        <?php 
                                        $noms = array_map(function($dest) {
                                            return $dest['nom'] . ' ' . $dest['prenom'];
                                        }, array_slice($destinataires_custom, 0, 3));
                                        echo htmlspecialchars(implode(', ', $noms));
                                        if (count($destinataires_custom) > 3) {
                                            echo ' et ' . (count($destinataires_custom) - 3) . ' autre(s)';
                                        }
                                        ?>
                                    </small>
                                <?php elseif ($message['destinataire_type'] === 'tous'): ?>
                                    <strong>Tous les utilisateurs</strong>
                                    <br><small class="text-muted">Diffusion générale</small>
                                <?php elseif ($message['destinataire_type'] === 'personnel'): ?>
                                    <strong>Tout le personnel</strong>
                                    <br><small class="text-muted">Diffusion au personnel</small>
                                <?php elseif ($message['destinataire_nom']): ?>
                                    <strong><?php echo htmlspecialchars($message['destinataire_nom'] . ' ' . $message['destinataire_prenom']); ?></strong>
                                    <?php if ($message['destinataire_email']): ?>
                                        <br><small class="text-muted"><?php echo htmlspecialchars($message['destinataire_email']); ?></small>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <strong><?php echo ucfirst($message['destinataire_type']); ?></strong>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Dates importantes -->
                <div class="row mb-4">
                    <div class="col-md-4">
                        <h6 class="text-primary">Date de création</h6>
                        <div>
                            <i class="fas fa-calendar-plus me-1"></i>
                            <?php echo formatDateTime($message['created_at'], 'd/m/Y à H:i'); ?>
                        </div>
                    </div>
                    
                    <?php if ($message['programme'] && $message['date_programmee']): ?>
                    <div class="col-md-4">
                        <h6 class="text-primary">Programmé pour</h6>
                        <div>
                            <i class="fas fa-clock me-1"></i>
                            <?php echo formatDateTime($message['date_programmee'], 'd/m/Y à H:i'); ?>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($message['date_envoi']): ?>
                    <div class="col-md-4">
                        <h6 class="text-primary">Date d'envoi</h6>
                        <div>
                            <i class="fas fa-paper-plane me-1"></i>
                            <?php echo formatDateTime($message['date_envoi'], 'd/m/Y à H:i'); ?>
                            <br><small class="text-muted">
                                <?php if ($message['jours_depuis'] == 0): ?>
                                    Aujourd'hui
                                <?php elseif ($message['jours_depuis'] == 1): ?>
                                    Hier
                                <?php else: ?>
                                    Il y a <?php echo $message['jours_depuis']; ?> jour(s)
                                <?php endif; ?>
                            </small>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
                
                <!-- Contenu du message -->
                <div class="mb-4">
                    <h6 class="text-primary">Contenu du message</h6>
                    <div class="p-4 bg-light rounded border-start border-primary border-4">
                        <?php echo nl2br(htmlspecialchars($message['contenu'])); ?>
                    </div>
                </div>
                
                <!-- Options supplémentaires -->
                <?php if ($message['accuse_reception']): ?>
                <div class="alert alert-info">
                    <i class="fas fa-receipt me-2"></i>
                    <strong>Accusé de réception demandé</strong> - L'expéditeur sera notifié de la lecture de ce message.
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Actions et informations complémentaires -->
    <div class="col-lg-4">
        <!-- Actions rapides -->
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-primary text-white">
                <h6 class="mb-0">
                    <i class="fas fa-bolt me-2"></i>
                    Actions rapides
                </h6>
            </div>
            <div class="card-body">
                <div class="d-grid gap-2">
                    <a href="compose.php?reply=<?php echo $message_id; ?>" class="btn btn-success">
                        <i class="fas fa-reply me-1"></i>
                        Répondre
                    </a>
                    
                    <a href="compose.php?forward=<?php echo $message_id; ?>" class="btn btn-info">
                        <i class="fas fa-share me-1"></i>
                        Transférer
                    </a>
                    
                    <?php if ($message['expediteur_id'] == $_SESSION['user_id'] && $message['status'] === 'brouillon'): ?>
                        <a href="compose.php?edit=<?php echo $message_id; ?>" class="btn btn-warning">
                            <i class="fas fa-edit me-1"></i>
                            Modifier
                        </a>
                    <?php endif; ?>
                    
                    <?php if ($message['status'] !== 'lu' && $message['destinataire_id'] == $_SESSION['user_id']): ?>
                        <form method="POST" class="d-inline">
                            <input type="hidden" name="action" value="mark_read">
                            <button type="submit" class="btn btn-outline-primary w-100">
                                <i class="fas fa-eye me-1"></i>
                                Marquer comme lu
                            </button>
                        </form>
                    <?php endif; ?>
                    
                    <?php if ($message['status'] !== 'archive'): ?>
                        <form method="POST" class="d-inline">
                            <input type="hidden" name="action" value="archive">
                            <button type="submit" class="btn btn-outline-secondary w-100" 
                                    onclick="return confirm('Archiver ce message ?')">
                                <i class="fas fa-archive me-1"></i>
                                Archiver
                            </button>
                        </form>
                    <?php endif; ?>
                    
                    <form method="POST" class="d-inline">
                        <input type="hidden" name="action" value="delete">
                        <button type="submit" class="btn btn-outline-danger w-100" 
                                onclick="return confirm('Supprimer définitivement ce message ?')">
                            <i class="fas fa-trash me-1"></i>
                            Supprimer
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Informations détaillées -->
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-light">
                <h6 class="mb-0">
                    <i class="fas fa-info-circle me-2"></i>
                    Informations détaillées
                </h6>
            </div>
            <div class="card-body">
                <div class="small">
                    <div class="row mb-2">
                        <div class="col-5"><strong>ID du message :</strong></div>
                        <div class="col-7">#<?php echo $message['id']; ?></div>
                    </div>

                    <div class="row mb-2">
                        <div class="col-5"><strong>Type :</strong></div>
                        <div class="col-7"><?php echo ucfirst($message['type_message']); ?></div>
                    </div>

                    <div class="row mb-2">
                        <div class="col-5"><strong>Priorité :</strong></div>
                        <div class="col-7"><?php echo ucfirst($message['priorite']); ?></div>
                    </div>

                    <div class="row mb-2">
                        <div class="col-5"><strong>Statut :</strong></div>
                        <div class="col-7"><?php echo ucfirst($message['status']); ?></div>
                    </div>

                    <?php if ($message['programme']): ?>
                    <div class="row mb-2">
                        <div class="col-5"><strong>Programmé :</strong></div>
                        <div class="col-7">
                            <i class="fas fa-check text-success"></i> Oui
                        </div>
                    </div>
                    <?php endif; ?>

                    <?php if ($message['accuse_reception']): ?>
                    <div class="row mb-2">
                        <div class="col-5"><strong>Accusé réception :</strong></div>
                        <div class="col-7">
                            <i class="fas fa-check text-success"></i> Demandé
                        </div>
                    </div>
                    <?php endif; ?>

                    <div class="row mb-2">
                        <div class="col-5"><strong>Créé le :</strong></div>
                        <div class="col-7"><?php echo formatDateTime($message['created_at']); ?></div>
                    </div>

                    <?php if ($message['updated_at'] && $message['updated_at'] !== $message['created_at']): ?>
                    <div class="row mb-2">
                        <div class="col-5"><strong>Modifié le :</strong></div>
                        <div class="col-7"><?php echo formatDateTime($message['updated_at']); ?></div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Liste des destinataires personnalisés -->
        <?php if ($message['destinataire_type'] === 'custom' && !empty($destinataires_custom)): ?>
        <div class="card shadow-sm">
            <div class="card-header bg-light">
                <h6 class="mb-0">
                    <i class="fas fa-users me-2"></i>
                    Destinataires (<?php echo count($destinataires_custom); ?>)
                </h6>
            </div>
            <div class="card-body">
                <div class="list-group list-group-flush">
                    <?php foreach ($destinataires_custom as $destinataire): ?>
                        <div class="list-group-item px-0 py-2">
                            <div class="d-flex align-items-center">
                                <i class="fas fa-user-circle fa-lg text-muted me-2"></i>
                                <div>
                                    <strong><?php echo htmlspecialchars($destinataire['nom'] . ' ' . $destinataire['prenom']); ?></strong>
                                    <?php if ($destinataire['email']): ?>
                                        <br><small class="text-muted"><?php echo htmlspecialchars($destinataire['email']); ?></small>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<style>
@media print {
    .btn-toolbar, .card:last-child, .no-print {
        display: none !important;
    }

    .card {
        border: 1px solid #000 !important;
        box-shadow: none !important;
    }

    .badge {
        border: 1px solid #000 !important;
    }
}

.border-start {
    border-left-width: 4px !important;
}
</style>

<script>
// Confirmation pour les actions destructives
document.querySelectorAll('form[method="POST"]').forEach(form => {
    const action = form.querySelector('input[name="action"]')?.value;
    if (action === 'delete') {
        form.addEventListener('submit', function(e) {
            if (!confirm('Êtes-vous sûr de vouloir supprimer définitivement ce message ?')) {
                e.preventDefault();
            }
        });
    } else if (action === 'archive') {
        form.addEventListener('submit', function(e) {
            if (!confirm('Êtes-vous sûr de vouloir archiver ce message ?')) {
                e.preventDefault();
            }
        });
    }
});

// Auto-scroll vers le contenu du message si l'URL contient un hash
if (window.location.hash) {
    setTimeout(() => {
        const element = document.querySelector(window.location.hash);
        if (element) {
            element.scrollIntoView({ behavior: 'smooth' });
        }
    }, 100);
}
</script>

<?php include '../../../includes/footer.php'; ?>
