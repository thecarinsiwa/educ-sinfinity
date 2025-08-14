<?php
/**
 * Notifications aux parents - Absences et retards
 * Application de gestion scolaire - République Démocratique du Congo
 */

require_once '../../../../config/config.php';
require_once '../../../../config/database.php';
require_once '../../../../includes/functions.php';

// Vérifier l'authentification et les permissions
requireLogin();
if (!checkPermission('students')) {
    redirectTo('../../../../login.php');
}

$page_title = "Notifications aux parents";

// Récupérer l'année scolaire active
$current_year = $database->query("SELECT * FROM annees_scolaires WHERE status = 'active' LIMIT 1")->fetch();

// Traitement des actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $action = $_POST['action'] ?? '';
        
        if ($action === 'send_notifications') {
            $selected_absences = $_POST['selected_absences'] ?? [];
            $notification_type = $_POST['notification_type'] ?? 'sms';
            $custom_message = sanitizeInput($_POST['custom_message'] ?? '');
            
            if (empty($selected_absences)) {
                throw new Exception('Aucune absence sélectionnée');
            }
            
            $sent_count = 0;
            $errors = [];
            
            foreach ($selected_absences as $absence_id) {
                try {
                    // Récupérer les informations de l'absence et du parent
                    $absence_info = $database->query(
                        "SELECT a.*, e.nom as eleve_nom, e.prenom as eleve_prenom,
                                c.nom as classe_nom, p.nom as parent_nom, p.prenom as parent_prenom,
                                p.telephone, p.email
                         FROM absences a
                         JOIN eleves e ON a.eleve_id = e.id
                         JOIN inscriptions i ON e.id = i.eleve_id
                         JOIN classes c ON i.classe_id = c.id
                         LEFT JOIN parents p ON e.parent_id = p.id
                         WHERE a.id = ?",
                        [$absence_id]
                    )->fetch();
                    
                    if (!$absence_info) {
                        $errors[] = "Absence ID $absence_id non trouvée";
                        continue;
                    }
                    
                    // Préparer le message
                    $message = $custom_message ?: generateDefaultMessage($absence_info);
                    
                    // Envoyer la notification
                    $result = sendNotification($absence_info, $message, $notification_type);
                    
                    if ($result['success']) {
                        // Enregistrer la notification dans la base
                        $database->execute(
                            "INSERT INTO notifications_parents (absence_id, parent_id, type_notification, message, status, sent_at, created_by) 
                             VALUES (?, ?, ?, ?, 'sent', NOW(), ?)",
                            [$absence_id, $absence_info['parent_id'] ?? null, $notification_type, $message, $_SESSION['user_id']]
                        );
                        $sent_count++;
                    } else {
                        $errors[] = "Erreur pour {$absence_info['eleve_nom']} {$absence_info['eleve_prenom']}: {$result['message']}";
                    }
                    
                } catch (Exception $e) {
                    $errors[] = "Erreur absence ID $absence_id: " . $e->getMessage();
                }
            }
            
            // Enregistrer l'action
            logUserAction(
                'send_parent_notifications',
                'attendance',
                "Notifications envoyées - Type: $notification_type, Envoyées: $sent_count, Erreurs: " . count($errors),
                null
            );
            
            $message = "$sent_count notification(s) envoyée(s) avec succès";
            if (!empty($errors)) {
                $message .= ". " . count($errors) . " erreur(s)";
            }
            
            showMessage('success', $message);
            
            // Afficher les erreurs
            foreach ($errors as $error) {
                showMessage('warning', $error);
            }
            
        } elseif ($action === 'configure_templates') {
            $sms_template = sanitizeInput($_POST['sms_template'] ?? '');
            $email_template = sanitizeInput($_POST['email_template'] ?? '');
            
            // Sauvegarder les templates (ici on pourrait les stocker en base ou dans un fichier de config)
            $_SESSION['notification_templates'] = [
                'sms' => $sms_template,
                'email' => $email_template
            ];
            
            showMessage('success', 'Templates de notification sauvegardés');
        }
        
    } catch (Exception $e) {
        showMessage('error', $e->getMessage());
    }
}

// Récupérer les absences récentes non notifiées
$recent_absences = $database->query(
    "SELECT a.*, e.nom as eleve_nom, e.prenom as eleve_prenom, e.numero_matricule,
            c.nom as classe_nom, c.niveau, i.classe_id,
            p.nom as parent_nom, p.prenom as parent_prenom, p.telephone, p.email,
            np.id as notification_sent
     FROM absences a
     JOIN eleves e ON a.eleve_id = e.id
     JOIN inscriptions i ON e.id = i.eleve_id
     JOIN classes c ON i.classe_id = c.id
     LEFT JOIN parents p ON e.parent_id = p.id
     LEFT JOIN notifications_parents np ON a.id = np.absence_id
     WHERE a.date_absence >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
     AND i.annee_scolaire_id = ?
     ORDER BY a.date_absence DESC, a.created_at DESC
     LIMIT 50",
    [$current_year['id'] ?? 0]
)->fetchAll();

// Statistiques des notifications
$notification_stats = $database->query(
    "SELECT
        COUNT(*) as total_notifications,
        COUNT(CASE WHEN status = 'sent' THEN 1 END) as sent_notifications,
        COUNT(CASE WHEN status = 'failed' THEN 1 END) as failed_notifications,
        COUNT(CASE WHEN type_notification = 'sms' THEN 1 END) as sms_notifications,
        COUNT(CASE WHEN type_notification = 'email' THEN 1 END) as email_notifications
     FROM notifications_parents
     WHERE DATE(created_at) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)"
)->fetch();

// Récupérer les classes pour le filtre
$classes = $database->query(
    "SELECT * FROM classes WHERE annee_scolaire_id = ? ORDER BY niveau, nom",
    [$current_year['id'] ?? 0]
)->fetchAll();

// Templates par défaut
$default_templates = [
    'sms' => "Cher parent, votre enfant {eleve_nom} de la classe {classe_nom} a été {type_absence} le {date_absence}. École Sinfinity.",
    'email' => "Cher(e) {parent_nom},\n\nNous vous informons que votre enfant {eleve_nom} {eleve_prenom} de la classe {classe_nom} a été marqué(e) comme {type_absence} le {date_absence}.\n\nMotif: {motif}\n\nCordialement,\nÉcole Sinfinity"
];

// Fonctions utilitaires
function generateDefaultMessage($absence_info) {
    $type_labels = [
        'absence' => 'absent(e)',
        'retard' => 'en retard',
        'absence_justifiee' => 'absent(e) (justifié)',
        'retard_justifie' => 'en retard (justifié)'
    ];
    
    $type_label = $type_labels[$absence_info['type_absence']] ?? $absence_info['type_absence'];
    
    return "Cher parent, votre enfant {$absence_info['eleve_nom']} {$absence_info['eleve_prenom']} " .
           "de la classe {$absence_info['classe_nom']} a été $type_label le " .
           date('d/m/Y à H:i', strtotime($absence_info['date_absence'])) . ". École Sinfinity.";
}

function sendNotification($absence_info, $message, $type) {
    // Simulation d'envoi de notification
    // Dans un vrai système, ici on intégrerait avec un service SMS/Email
    
    if ($type === 'sms') {
        if (empty($absence_info['telephone'])) {
            return ['success' => false, 'message' => 'Numéro de téléphone manquant'];
        }
        
        // Simulation envoi SMS
        // $result = sendSMS($absence_info['telephone'], $message);
        return ['success' => true, 'message' => 'SMS envoyé'];
        
    } elseif ($type === 'email') {
        if (empty($absence_info['email'])) {
            return ['success' => false, 'message' => 'Adresse email manquante'];
        }
        
        // Simulation envoi Email
        // $result = sendEmail($absence_info['email'], 'Notification absence', $message);
        return ['success' => true, 'message' => 'Email envoyé'];
    }
    
    return ['success' => false, 'message' => 'Type de notification non supporté'];
}

include '../../../../includes/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">
        <i class="fas fa-bell me-2"></i>
        Notifications aux parents
    </h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <div class="btn-group me-2">
            <a href="../index.php" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-1"></i>
                Retour
            </a>
        </div>
        <div class="btn-group">
            <button type="button" class="btn btn-info" data-bs-toggle="modal" data-bs-target="#templatesModal">
                <i class="fas fa-cog me-1"></i>
                Templates
            </button>
            <button type="button" class="btn btn-success" onclick="sendSelectedNotifications()">
                <i class="fas fa-paper-plane me-1"></i>
                Envoyer sélectionnées
            </button>
        </div>
    </div>
</div>

<!-- Statistiques -->
<div class="row mb-4">
    <div class="col-lg-3 col-md-6 mb-3">
        <div class="card bg-primary text-white">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h4 class="mb-0"><?php echo number_format($notification_stats['total_notifications'] ?? 0); ?></h4>
                        <p class="mb-0">Total notifications</p>
                    </div>
                    <div class="align-self-center">
                        <i class="fas fa-bell fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-lg-3 col-md-6 mb-3">
        <div class="card bg-success text-white">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h4 class="mb-0"><?php echo number_format($notification_stats['sent_notifications'] ?? 0); ?></h4>
                        <p class="mb-0">Envoyées</p>
                    </div>
                    <div class="align-self-center">
                        <i class="fas fa-check-circle fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-lg-3 col-md-6 mb-3">
        <div class="card bg-warning text-white">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h4 class="mb-0"><?php echo number_format($notification_stats['sms_notifications'] ?? 0); ?></h4>
                        <p class="mb-0">SMS</p>
                    </div>
                    <div class="align-self-center">
                        <i class="fas fa-sms fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-lg-3 col-md-6 mb-3">
        <div class="card bg-info text-white">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h4 class="mb-0"><?php echo number_format($notification_stats['email_notifications'] ?? 0); ?></h4>
                        <p class="mb-0">Emails</p>
                    </div>
                    <div class="align-self-center">
                        <i class="fas fa-envelope fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Liste des absences -->
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">
            <i class="fas fa-list me-2"></i>
            Absences récentes (7 derniers jours)
        </h5>
        <div class="form-check">
            <input class="form-check-input" type="checkbox" id="selectAll" onchange="toggleSelectAll()">
            <label class="form-check-label" for="selectAll">
                Tout sélectionner
            </label>
        </div>
    </div>
    <div class="card-body">
        <?php if (!empty($recent_absences)): ?>
            <form method="POST" id="notificationForm">
                <input type="hidden" name="action" value="send_notifications">

                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead class="table-dark">
                            <tr>
                                <th width="50">
                                    <input type="checkbox" id="selectAllHeader" onchange="toggleSelectAll()">
                                </th>
                                <th>Élève</th>
                                <th>Classe</th>
                                <th>Type</th>
                                <th>Date/Heure</th>
                                <th>Parent</th>
                                <th>Contact</th>
                                <th>Notification</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="absencesTableBody">
                            <?php foreach ($recent_absences as $absence): ?>
                                <?php
                                $type_badges = [
                                    'absence' => 'danger',
                                    'absence_justifiee' => 'success',
                                    'retard' => 'warning',
                                    'retard_justifie' => 'info'
                                ];
                                $badge_color = $type_badges[$absence['type_absence']] ?? 'secondary';

                                $has_phone = !empty($absence['telephone']);
                                $has_email = !empty($absence['email']);
                                $notification_sent = !empty($absence['notification_sent']);
                                ?>
                                <tr class="absence-row"
                                    data-class="<?php echo $absence['classe_id'] ?? ''; ?>"
                                    data-type="<?php echo $absence['type_absence']; ?>"
                                    data-notification="<?php echo $notification_sent ? 'sent' : 'not_sent'; ?>"
                                    data-contact="<?php echo $has_phone ? 'has_phone' : ($has_email ? 'has_email' : 'no_contact'); ?>">

                                    <td>
                                        <?php if (!$notification_sent && ($has_phone || $has_email)): ?>
                                            <input type="checkbox" name="selected_absences[]"
                                                   value="<?php echo $absence['id']; ?>"
                                                   class="absence-checkbox">
                                        <?php endif; ?>
                                    </td>

                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="me-3">
                                                <i class="fas fa-user-circle fa-2x text-muted"></i>
                                            </div>
                                            <div>
                                                <h6 class="mb-0"><?php echo htmlspecialchars($absence['eleve_nom'] . ' ' . $absence['eleve_prenom']); ?></h6>
                                                <small class="text-muted"><?php echo htmlspecialchars($absence['numero_matricule']); ?></small>
                                            </div>
                                        </div>
                                    </td>

                                    <td>
                                        <span class="badge bg-secondary">
                                            <?php echo htmlspecialchars($absence['niveau'] . ' - ' . $absence['classe_nom']); ?>
                                        </span>
                                    </td>

                                    <td>
                                        <span class="badge bg-<?php echo $badge_color; ?>">
                                            <?php
                                            $type_labels = [
                                                'absence' => 'Absence',
                                                'absence_justifiee' => 'Absence justifiée',
                                                'retard' => 'Retard',
                                                'retard_justifie' => 'Retard justifié'
                                            ];
                                            echo $type_labels[$absence['type_absence']] ?? $absence['type_absence'];
                                            ?>
                                        </span>
                                    </td>

                                    <td>
                                        <div>
                                            <strong><?php echo date('d/m/Y', strtotime($absence['date_absence'])); ?></strong><br>
                                            <small class="text-muted"><?php echo date('H:i', strtotime($absence['date_absence'])); ?></small>
                                        </div>
                                    </td>

                                    <td>
                                        <?php if ($absence['parent_nom']): ?>
                                            <div>
                                                <strong><?php echo htmlspecialchars($absence['parent_nom'] . ' ' . $absence['parent_prenom']); ?></strong>
                                            </div>
                                        <?php else: ?>
                                            <span class="text-muted">Non renseigné</span>
                                        <?php endif; ?>
                                    </td>

                                    <td>
                                        <div>
                                            <?php if ($has_phone): ?>
                                                <div class="mb-1">
                                                    <i class="fas fa-phone text-success me-1"></i>
                                                    <small><?php echo htmlspecialchars($absence['telephone']); ?></small>
                                                </div>
                                            <?php endif; ?>

                                            <?php if ($has_email): ?>
                                                <div>
                                                    <i class="fas fa-envelope text-info me-1"></i>
                                                    <small><?php echo htmlspecialchars($absence['email']); ?></small>
                                                </div>
                                            <?php endif; ?>

                                            <?php if (!$has_phone && !$has_email): ?>
                                                <span class="text-danger">
                                                    <i class="fas fa-exclamation-triangle me-1"></i>
                                                    Aucun contact
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </td>

                                    <td>
                                        <?php if ($notification_sent): ?>
                                            <span class="badge bg-success">
                                                <i class="fas fa-check me-1"></i>
                                                Envoyée
                                            </span>
                                        <?php elseif (!$has_phone && !$has_email): ?>
                                            <span class="badge bg-danger">
                                                <i class="fas fa-times me-1"></i>
                                                Impossible
                                            </span>
                                        <?php else: ?>
                                            <span class="badge bg-warning">
                                                <i class="fas fa-clock me-1"></i>
                                                En attente
                                            </span>
                                        <?php endif; ?>
                                    </td>

                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <?php if (!$notification_sent && ($has_phone || $has_email)): ?>
                                                <button type="button" class="btn btn-outline-primary"
                                                        onclick="sendSingleNotification(<?php echo $absence['id']; ?>)">
                                                    <i class="fas fa-paper-plane"></i>
                                                </button>
                                            <?php endif; ?>

                                            <a href="../edit.php?id=<?php echo $absence['id']; ?>"
                                               class="btn btn-outline-secondary">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Actions groupées -->
                <div class="row mt-3">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="notification_type" class="form-label">Type de notification</label>
                            <select class="form-select" id="notification_type" name="notification_type">
                                <option value="sms">SMS</option>
                                <option value="email">Email</option>
                            </select>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="custom_message" class="form-label">Message personnalisé (optionnel)</label>
                            <textarea class="form-control" id="custom_message" name="custom_message"
                                      rows="2" placeholder="Laisser vide pour utiliser le message par défaut"></textarea>
                        </div>
                    </div>
                </div>

                <div class="d-flex justify-content-between align-items-center mt-3">
                    <div>
                        <span id="selectedCount" class="text-muted">0 absence(s) sélectionnée(s)</span>
                    </div>
                    <div>
                        <button type="button" class="btn btn-secondary me-2" onclick="resetSelection()">
                            <i class="fas fa-undo me-1"></i>
                            Réinitialiser
                        </button>
                        <button type="submit" class="btn btn-success" id="sendButton" disabled>
                            <i class="fas fa-paper-plane me-1"></i>
                            Envoyer les notifications
                        </button>
                    </div>
                </div>
            </form>
        <?php else: ?>
            <div class="text-center py-5">
                <i class="fas fa-bell-slash fa-3x text-muted mb-3"></i>
                <p class="text-muted">Aucune absence récente trouvée.</p>
                <a href="../index.php" class="btn btn-primary">
                    <i class="fas fa-plus me-1"></i>
                    Ajouter une absence
                </a>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Modal Templates -->
<div class="modal fade" id="templatesModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-cog me-2"></i>
                    Configuration des templates
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="configure_templates">
                <div class="modal-body">
                    <div class="mb-4">
                        <h6>Variables disponibles :</h6>
                        <div class="row">
                            <div class="col-md-6">
                                <ul class="list-unstyled small">
                                    <li><code>{eleve_nom}</code> - Nom de l'élève</li>
                                    <li><code>{eleve_prenom}</code> - Prénom de l'élève</li>
                                    <li><code>{classe_nom}</code> - Nom de la classe</li>
                                    <li><code>{parent_nom}</code> - Nom du parent</li>
                                </ul>
                            </div>
                            <div class="col-md-6">
                                <ul class="list-unstyled small">
                                    <li><code>{type_absence}</code> - Type d'absence</li>
                                    <li><code>{date_absence}</code> - Date de l'absence</li>
                                    <li><code>{motif}</code> - Motif de l'absence</li>
                                </ul>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="sms_template" class="form-label">Template SMS</label>
                        <textarea class="form-control" id="sms_template" name="sms_template" rows="3"
                                  placeholder="Template pour les SMS..."><?php echo $_SESSION['notification_templates']['sms'] ?? $default_templates['sms']; ?></textarea>
                        <div class="form-text">Maximum 160 caractères recommandé pour les SMS</div>
                    </div>

                    <div class="mb-3">
                        <label for="email_template" class="form-label">Template Email</label>
                        <textarea class="form-control" id="email_template" name="email_template" rows="6"
                                  placeholder="Template pour les emails..."><?php echo $_SESSION['notification_templates']['email'] ?? $default_templates['email']; ?></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-primary">Sauvegarder</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Notification individuelle -->
<div class="modal fade" id="singleNotificationModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-paper-plane me-2"></i>
                    Envoyer notification
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="singleNotificationForm">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="single_notification_type" class="form-label">Type</label>
                        <select class="form-select" id="single_notification_type">
                            <option value="sms">SMS</option>
                            <option value="email">Email</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="single_message" class="form-label">Message</label>
                        <textarea class="form-control" id="single_message" rows="4"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-success">Envoyer</button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
.absence-row.filtered-out {
    display: none;
}

.contact-info {
    font-size: 0.875rem;
}

.notification-status {
    font-size: 0.75rem;
}

@media (max-width: 768px) {
    .table-responsive {
        font-size: 0.875rem;
    }

    .btn-group-sm .btn {
        padding: 0.25rem 0.5rem;
    }
}
</style>

<script>
let selectedAbsences = [];
let currentSingleAbsenceId = null;

// Gestion de la sélection
function toggleSelectAll() {
    const selectAll = document.getElementById('selectAll');
    const checkboxes = document.querySelectorAll('.absence-checkbox:not([disabled])');

    checkboxes.forEach(checkbox => {
        if (!checkbox.closest('tr').classList.contains('filtered-out')) {
            checkbox.checked = selectAll.checked;
        }
    });

    updateSelectedCount();
}

function updateSelectedCount() {
    const checkboxes = document.querySelectorAll('.absence-checkbox:checked');
    const count = checkboxes.length;

    document.getElementById('selectedCount').textContent = `${count} absence(s) sélectionnée(s)`;
    document.getElementById('sendButton').disabled = count === 0;

    selectedAbsences = Array.from(checkboxes).map(cb => cb.value);
}

// Filtrage des absences
function filterAbsences() {
    const classFilter = document.getElementById('filter_class').value;
    const typeFilter = document.getElementById('filter_type').value;
    const notificationFilter = document.getElementById('filter_notification').value;
    const contactFilter = document.getElementById('filter_contact').value;

    const rows = document.querySelectorAll('.absence-row');

    rows.forEach(row => {
        let show = true;

        if (classFilter && row.dataset.class !== classFilter) {
            show = false;
        }

        if (typeFilter && row.dataset.type !== typeFilter) {
            show = false;
        }

        if (notificationFilter && row.dataset.notification !== notificationFilter) {
            show = false;
        }

        if (contactFilter) {
            if (contactFilter === 'has_phone' && row.dataset.contact !== 'has_phone') {
                show = false;
            } else if (contactFilter === 'has_email' && row.dataset.contact !== 'has_email') {
                show = false;
            } else if (contactFilter === 'no_contact' && row.dataset.contact !== 'no_contact') {
                show = false;
            }
        }

        if (show) {
            row.classList.remove('filtered-out');
        } else {
            row.classList.add('filtered-out');
            // Décocher si filtré
            const checkbox = row.querySelector('.absence-checkbox');
            if (checkbox) {
                checkbox.checked = false;
            }
        }
    });

    updateSelectedCount();
}

// Notification individuelle
function sendSingleNotification(absenceId) {
    currentSingleAbsenceId = absenceId;

    // Préparer le message par défaut
    const row = document.querySelector(`input[value="${absenceId}"]`).closest('tr');
    const eleveName = row.querySelector('h6').textContent;
    const type = row.dataset.type;

    document.getElementById('single_message').value =
        `Notification pour ${eleveName} - ${type}`;

    const modal = new bootstrap.Modal(document.getElementById('singleNotificationModal'));
    modal.show();
}

// Envoyer les notifications sélectionnées
function sendSelectedNotifications() {
    const selected = document.querySelectorAll('.absence-checkbox:checked');

    if (selected.length === 0) {
        alert('Veuillez sélectionner au moins une absence');
        return;
    }

    if (!confirm(`Envoyer ${selected.length} notification(s) ?`)) {
        return;
    }

    document.getElementById('notificationForm').submit();
}

// Réinitialiser la sélection
function resetSelection() {
    document.querySelectorAll('.absence-checkbox').forEach(cb => cb.checked = false);
    document.getElementById('selectAll').checked = false;
    updateSelectedCount();
}

// Gestion du formulaire de notification individuelle
document.getElementById('singleNotificationForm').addEventListener('submit', function(e) {
    e.preventDefault();

    const type = document.getElementById('single_notification_type').value;
    const message = document.getElementById('single_message').value;

    if (!message.trim()) {
        alert('Veuillez saisir un message');
        return;
    }

    // Envoyer via AJAX
    fetch('send-single-notification.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            absence_id: currentSingleAbsenceId,
            type: type,
            message: message
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Notification envoyée avec succès');
            location.reload();
        } else {
            alert('Erreur: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Erreur:', error);
        alert('Erreur lors de l\'envoi');
    });

    bootstrap.Modal.getInstance(document.getElementById('singleNotificationModal')).hide();
});

// Événements
document.addEventListener('DOMContentLoaded', function() {
    // Mettre à jour le compteur au chargement
    updateSelectedCount();

    // Écouter les changements de sélection
    document.querySelectorAll('.absence-checkbox').forEach(checkbox => {
        checkbox.addEventListener('change', updateSelectedCount);
    });

    // Enregistrer l'action de consultation
    fetch('../log-action.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            action: 'view_parent_notifications',
            module: 'attendance',
            details: 'Consultation de la page notifications parents'
        })
    }).catch(error => console.log('Log error:', error));
});

// Validation du formulaire principal
document.getElementById('notificationForm').addEventListener('submit', function(e) {
    const selected = document.querySelectorAll('.absence-checkbox:checked');

    if (selected.length === 0) {
        e.preventDefault();
        alert('Veuillez sélectionner au moins une absence');
        return false;
    }

    const type = document.getElementById('notification_type').value;

    if (!confirm(`Envoyer ${selected.length} notification(s) par ${type.toUpperCase()} ?`)) {
        e.preventDefault();
        return false;
    }
});
</script>

<?php include '../../../../includes/footer.php'; ?>

<!-- Filtres -->
<div class="card mb-4">
    <div class="card-header">
        <h5 class="mb-0">
            <i class="fas fa-filter me-2"></i>
            Filtres
        </h5>
    </div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-3">
                <label for="filter_class" class="form-label">Classe</label>
                <select class="form-select" id="filter_class" onchange="filterAbsences()">
                    <option value="">Toutes les classes</option>
                    <?php foreach ($classes as $class): ?>
                        <option value="<?php echo $class['id']; ?>">
                            <?php echo htmlspecialchars($class['niveau'] . ' - ' . $class['nom']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="col-md-3">
                <label for="filter_type" class="form-label">Type</label>
                <select class="form-select" id="filter_type" onchange="filterAbsences()">
                    <option value="">Tous les types</option>
                    <option value="absence">Absences</option>
                    <option value="retard">Retards</option>
                    <option value="absence_justifiee">Absences justifiées</option>
                    <option value="retard_justifie">Retards justifiés</option>
                </select>
            </div>
            
            <div class="col-md-3">
                <label for="filter_notification" class="form-label">Statut notification</label>
                <select class="form-select" id="filter_notification" onchange="filterAbsences()">
                    <option value="">Tous</option>
                    <option value="not_sent">Non envoyées</option>
                    <option value="sent">Envoyées</option>
                </select>
            </div>
            
            <div class="col-md-3">
                <label for="filter_contact" class="form-label">Contact parent</label>
                <select class="form-select" id="filter_contact" onchange="filterAbsences()">
                    <option value="">Tous</option>
                    <option value="has_phone">Avec téléphone</option>
                    <option value="has_email">Avec email</option>
                    <option value="no_contact">Sans contact</option>
                </select>
            </div>
        </div>
    </div>
</div>
