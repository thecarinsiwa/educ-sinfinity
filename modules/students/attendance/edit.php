<?php
/**
 * Modifier une absence/retard
 * Application de gestion scolaire - République Démocratique du Congo
 */

require_once '../../../config/config.php';
require_once '../../../config/database.php';
require_once '../../../includes/functions.php';

// Vérifier l'authentification et les permissions
requireLogin();
if (!checkPermission('students')) {
    redirectTo('../../../login.php');
}

$page_title = "Modifier une absence/retard";
$absence_id = (int)($_GET['id'] ?? 0);

if (!$absence_id) {
    showMessage('error', 'ID d\'absence manquant');
    redirectTo('index.php');
}

// Récupérer l'année scolaire active
$current_year = $database->query("SELECT * FROM annees_scolaires WHERE status = 'active' LIMIT 1")->fetch();

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $action = $_POST['action'] ?? '';
        
        if ($action === 'update') {
            // Mise à jour des informations de base
            $type_absence = sanitizeInput($_POST['type_absence'] ?? '');
            $date_absence = sanitizeInput($_POST['date_absence'] ?? '');
            $heure_absence = sanitizeInput($_POST['heure_absence'] ?? '');
            $motif = sanitizeInput($_POST['motif'] ?? '');
            $justifiee = isset($_POST['justifiee']) ? 1 : 0;
            
            // Adapter le type d'absence selon la justification
            if ($justifiee && $type_absence === 'absence') {
                $type_absence = 'absence_justifiee';
            } elseif ($justifiee && $type_absence === 'retard') {
                $type_absence = 'retard_justifie';
            }
            
            // Validation
            if (!in_array($type_absence, ['absence', 'retard', 'absence_justifiee', 'retard_justifie'])) {
                throw new Exception('Type d\'absence invalide');
            }
            
            if (!$date_absence || !$heure_absence) {
                throw new Exception('Date et heure requises');
            }
            
            // Combiner date et heure
            $datetime_absence = $date_absence . ' ' . $heure_absence;
            
            // Commencer une transaction
            $database->beginTransaction();
            
            try {
                // Mettre à jour l'absence
                $database->execute(
                    "UPDATE absences SET 
                     type_absence = ?, date_absence = ?, motif = ?, updated_at = NOW()
                     WHERE id = ?",
                    [$type_absence, $datetime_absence, $motif, $absence_id]
                );
                
                // Enregistrer l'action dans l'historique
                logUserAction(
                    'update_absence',
                    'attendance',
                    'Absence modifiée - Type: ' . $type_absence . ', Date: ' . formatDateTime($datetime_absence) .
                    ($motif ? ', Motif: ' . $motif : ''),
                    $absence_id
                );
                
                $database->commit();
                showMessage('success', 'Absence modifiée avec succès');
                
            } catch (Exception $e) {
                $database->rollback();
                throw $e;
            }
            
        } elseif ($action === 'justify') {
            // Justification d'absence
            $justification = sanitizeInput($_POST['justification'] ?? '');
            $document_justificatif = '';
            
            // Gestion de l'upload de document justificatif
            if (isset($_FILES['document_justificatif']) && $_FILES['document_justificatif']['error'] === UPLOAD_ERR_OK) {
                $upload_dir = '../../../uploads/justificatifs/';
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                }
                
                $file_extension = pathinfo($_FILES['document_justificatif']['name'], PATHINFO_EXTENSION);
                $filename = 'justificatif_' . $absence_id . '_' . time() . '.' . $file_extension;
                $upload_path = $upload_dir . $filename;
                
                if (move_uploaded_file($_FILES['document_justificatif']['tmp_name'], $upload_path)) {
                    $document_justificatif = $filename;
                }
            }
            
            // Commencer une transaction
            $database->beginTransaction();
            
            try {
                // Déterminer le nouveau type d'absence justifiée
                $absence_info = $database->query("SELECT type_absence FROM absences WHERE id = ?", [$absence_id])->fetch();
                $new_type = $absence_info['type_absence'];
                
                if ($absence_info['type_absence'] === 'absence') {
                    $new_type = 'absence_justifiee';
                } elseif ($absence_info['type_absence'] === 'retard') {
                    $new_type = 'retard_justifie';
                }
                
                // Mettre à jour l'absence avec justification
                $database->execute(
                    "UPDATE absences SET 
                     type_absence = ?, justification = ?, document_justificatif = ?, 
                     valide_par = ?, date_validation = NOW(), updated_at = NOW()
                     WHERE id = ?",
                    [$new_type, $justification, $document_justificatif, $_SESSION['user_id'], $absence_id]
                );
                
                // Enregistrer l'action dans l'historique
                logUserAction(
                    'justify_absence',
                    'attendance',
                    'Absence justifiée' . ($justification ? ' - Justification: ' . substr($justification, 0, 100) : '') .
                    ($document_justificatif ? ' - Document: ' . $document_justificatif : ''),
                    $absence_id
                );
                
                $database->commit();
                showMessage('success', 'Absence justifiée avec succès');
                
            } catch (Exception $e) {
                $database->rollback();
                throw $e;
            }
        }
        
    } catch (Exception $e) {
        showMessage('error', $e->getMessage());
    }
}

// Récupérer les informations de l'absence
$absence = $database->query(
    "SELECT a.*, e.nom as eleve_nom, e.prenom as eleve_prenom, e.numero_matricule,
            c.nom as classe_nom, c.niveau, i.classe_id,
            u_valide.nom as valide_par_nom, u_valide.prenom as valide_par_prenom
     FROM absences a
     JOIN eleves e ON a.eleve_id = e.id
     JOIN inscriptions i ON e.id = i.eleve_id
     JOIN classes c ON i.classe_id = c.id
     LEFT JOIN users u_valide ON a.valide_par = u_valide.id
     WHERE a.id = ? AND i.annee_scolaire_id = ?",
    [$absence_id, $current_year['id'] ?? 0]
)->fetch();

if (!$absence) {
    showMessage('error', 'Absence non trouvée');
    redirectTo('index.php');
}

// Récupérer l'historique des actions sur cette absence
$history = $database->query(
    "SELECT ual.*, u.nom as user_nom, u.prenom as user_prenom, u.username
     FROM user_actions_log ual
     JOIN users u ON ual.user_id = u.id
     WHERE ual.module = 'attendance' AND ual.target_id = ?
     ORDER BY ual.created_at DESC",
    [$absence_id]
)->fetchAll();

// Déterminer si l'absence est justifiée
$is_justified = in_array($absence['type_absence'], ['absence_justifiee', 'retard_justifie']);

include '../../../includes/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">
        <i class="fas fa-edit me-2"></i>
        Modifier une absence/retard
    </h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <div class="btn-group me-2">
            <a href="index.php" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-1"></i>
                Retour à la liste
            </a>
        </div>
        <div class="btn-group">
            <button type="button" class="btn btn-outline-info" onclick="showHistory()">
                <i class="fas fa-history me-1"></i>
                Historique
            </button>
        </div>
    </div>
</div>

<div class="row">
    <!-- Informations de l'élève -->
    <div class="col-lg-4">
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-user me-2"></i>
                    Informations de l'élève
                </h5>
            </div>
            <div class="card-body">
                <div class="text-center mb-3">
                    <div class="avatar-lg mx-auto mb-2">
                        <i class="fas fa-user-circle fa-4x text-muted"></i>
                    </div>
                    <h5 class="mb-1"><?php echo htmlspecialchars($absence['eleve_nom'] . ' ' . $absence['eleve_prenom']); ?></h5>
                    <p class="text-muted mb-0"><?php echo htmlspecialchars($absence['numero_matricule']); ?></p>
                </div>
                
                <div class="row text-center">
                    <div class="col-6">
                        <div class="border-end">
                            <h6 class="text-muted mb-1">Classe</h6>
                            <p class="mb-0"><?php echo htmlspecialchars($absence['classe_nom']); ?></p>
                        </div>
                    </div>
                    <div class="col-6">
                        <h6 class="text-muted mb-1">Niveau</h6>
                        <p class="mb-0"><?php echo htmlspecialchars($absence['niveau']); ?></p>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Statut actuel -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-info-circle me-2"></i>
                    Statut actuel
                </h5>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <label class="form-label">Type d'absence</label>
                    <div>
                        <?php
                        $type_badges = [
                            'absence' => 'danger',
                            'absence_justifiee' => 'success',
                            'retard' => 'warning',
                            'retard_justifie' => 'info'
                        ];
                        $type_labels = [
                            'absence' => 'Absence',
                            'absence_justifiee' => 'Absence justifiée',
                            'retard' => 'Retard',
                            'retard_justifie' => 'Retard justifié'
                        ];
                        $badge_color = $type_badges[$absence['type_absence']] ?? 'secondary';
                        $type_label = $type_labels[$absence['type_absence']] ?? $absence['type_absence'];
                        ?>
                        <span class="badge bg-<?php echo $badge_color; ?> fs-6">
                            <?php echo $type_label; ?>
                        </span>
                    </div>
                </div>
                
                <div class="mb-3">
                    <label class="form-label">Date et heure</label>
                    <p class="mb-0"><?php echo formatDateTime($absence['date_absence']); ?></p>
                </div>
                
                <?php if ($absence['motif']): ?>
                <div class="mb-3">
                    <label class="form-label">Motif</label>
                    <p class="mb-0"><?php echo htmlspecialchars($absence['motif']); ?></p>
                </div>
                <?php endif; ?>
                
                <?php if ($is_justified): ?>
                <div class="mb-3">
                    <label class="form-label">Validé par</label>
                    <p class="mb-0">
                        <?php 
                        if ($absence['valide_par_nom']) {
                            echo htmlspecialchars($absence['valide_par_nom'] . ' ' . $absence['valide_par_prenom']);
                            if ($absence['date_validation']) {
                                echo '<br><small class="text-muted">Le ' . formatDateTime($absence['date_validation']) . '</small>';
                            }
                        } else {
                            echo '<span class="text-muted">Non spécifié</span>';
                        }
                        ?>
                    </p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Formulaires de modification -->
    <div class="col-lg-8">
        <!-- Onglets -->
        <ul class="nav nav-tabs mb-4" id="editTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="edit-tab" data-bs-toggle="tab" data-bs-target="#edit-pane" type="button" role="tab">
                    <i class="fas fa-edit me-2"></i>Modifier
                </button>
            </li>
            <?php if (!$is_justified): ?>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="justify-tab" data-bs-toggle="tab" data-bs-target="#justify-pane" type="button" role="tab">
                    <i class="fas fa-check-circle me-2"></i>Justifier
                </button>
            </li>
            <?php endif; ?>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="history-tab" data-bs-toggle="tab" data-bs-target="#history-pane" type="button" role="tab">
                    <i class="fas fa-history me-2"></i>Historique
                </button>
            </li>
        </ul>
        
        <div class="tab-content" id="editTabsContent">
            <!-- Onglet Modifier -->
            <div class="tab-pane fade show active" id="edit-pane" role="tabpanel">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Modifier les informations</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" id="editForm">
                            <input type="hidden" name="action" value="update">
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="type_absence" class="form-label">Type d'absence <span class="text-danger">*</span></label>
                                    <select class="form-select" id="type_absence" name="type_absence" required>
                                        <?php
                                        $base_type = str_replace(['_justifiee', '_justifie'], '', $absence['type_absence']);
                                        ?>
                                        <option value="absence" <?php echo $base_type === 'absence' ? 'selected' : ''; ?>>Absence</option>
                                        <option value="retard" <?php echo $base_type === 'retard' ? 'selected' : ''; ?>>Retard</option>
                                    </select>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <div class="form-check mt-4">
                                        <input class="form-check-input" type="checkbox" id="justifiee" name="justifiee" <?php echo $is_justified ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="justifiee">
                                            Justifiée
                                        </label>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="date_absence" class="form-label">Date <span class="text-danger">*</span></label>
                                    <input type="date" class="form-control" id="date_absence" name="date_absence" 
                                           value="<?php echo date('Y-m-d', strtotime($absence['date_absence'])); ?>" required>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="heure_absence" class="form-label">Heure <span class="text-danger">*</span></label>
                                    <input type="time" class="form-control" id="heure_absence" name="heure_absence" 
                                           value="<?php echo date('H:i', strtotime($absence['date_absence'])); ?>" required>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="motif" class="form-label">Motif</label>
                                <textarea class="form-control" id="motif" name="motif" rows="3" 
                                          placeholder="Motif de l'absence ou du retard"><?php echo htmlspecialchars($absence['motif'] ?? ''); ?></textarea>
                            </div>
                            
                            <div class="d-flex justify-content-end">
                                <button type="button" class="btn btn-secondary me-2" onclick="window.location.href='index.php'">
                                    Annuler
                                </button>
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save me-1"></i>
                                    Enregistrer les modifications
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Onglet Justifier -->
            <?php if (!$is_justified): ?>
            <div class="tab-pane fade" id="justify-pane" role="tabpanel">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Justifier l'absence/retard</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" enctype="multipart/form-data" id="justifyForm">
                            <input type="hidden" name="action" value="justify">

                            <div class="alert alert-info">
                                <i class="fas fa-info-circle me-2"></i>
                                <strong>Information :</strong> Justifier cette absence/retard changera automatiquement son statut.
                            </div>

                            <div class="mb-3">
                                <label for="justification" class="form-label">Justification <span class="text-danger">*</span></label>
                                <textarea class="form-control" id="justification" name="justification" rows="4"
                                          placeholder="Expliquez les raisons de cette absence/retard..." required></textarea>
                                <div class="form-text">Décrivez les circonstances qui justifient cette absence ou ce retard.</div>
                            </div>

                            <div class="mb-3">
                                <label for="document_justificatif" class="form-label">Document justificatif (optionnel)</label>
                                <input type="file" class="form-control" id="document_justificatif" name="document_justificatif"
                                       accept=".pdf,.jpg,.jpeg,.png,.doc,.docx">
                                <div class="form-text">
                                    Formats acceptés : PDF, JPG, PNG, DOC, DOCX (max 5MB)
                                </div>
                            </div>

                            <div class="d-flex justify-content-end">
                                <button type="button" class="btn btn-secondary me-2" onclick="$('#edit-tab').tab('show')">
                                    Retour
                                </button>
                                <button type="submit" class="btn btn-success">
                                    <i class="fas fa-check-circle me-1"></i>
                                    Justifier l'absence
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Onglet Historique -->
            <div class="tab-pane fade" id="history-pane" role="tabpanel">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Historique des modifications</h5>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($history)): ?>
                            <div class="timeline">
                                <?php foreach ($history as $entry): ?>
                                    <div class="timeline-item">
                                        <div class="timeline-marker">
                                            <?php
                                            $action_icons = [
                                                'create_absence' => 'fas fa-plus text-primary',
                                                'update_absence' => 'fas fa-edit text-warning',
                                                'justify_absence' => 'fas fa-check-circle text-success',
                                                'view_absence_history' => 'fas fa-eye text-info'
                                            ];
                                            $icon = $action_icons[$entry['action']] ?? 'fas fa-circle text-secondary';
                                            ?>
                                            <i class="<?php echo $icon; ?>"></i>
                                        </div>
                                        <div class="timeline-content">
                                            <div class="d-flex justify-content-between align-items-start">
                                                <div>
                                                    <h6 class="mb-1">
                                                        <?php
                                                        $action_labels = [
                                                            'create_absence' => 'Création',
                                                            'update_absence' => 'Modification',
                                                            'justify_absence' => 'Justification',
                                                            'view_absence_history' => 'Consultation historique'
                                                        ];
                                                        echo $action_labels[$entry['action']] ?? ucfirst($entry['action']);
                                                        ?>
                                                    </h6>
                                                    <p class="mb-1 text-muted"><?php echo htmlspecialchars($entry['details']); ?></p>
                                                    <small class="text-muted">
                                                        Par <?php echo htmlspecialchars($entry['user_nom'] . ' ' . $entry['user_prenom']); ?>
                                                        (<?php echo htmlspecialchars($entry['username']); ?>)
                                                    </small>
                                                </div>
                                                <small class="text-muted">
                                                    <?php echo formatDateTime($entry['created_at']); ?>
                                                </small>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-4">
                                <i class="fas fa-history fa-3x text-muted mb-3"></i>
                                <p class="text-muted">Aucun historique disponible pour cette absence.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal d'historique détaillé -->
<div class="modal fade" id="historyModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-history me-2"></i>
                    Historique détaillé
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="historyModalBody">
                <div class="text-center">
                    <div class="spinner-border" role="status">
                        <span class="visually-hidden">Chargement...</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.timeline {
    position: relative;
    padding-left: 30px;
}

.timeline::before {
    content: '';
    position: absolute;
    left: 15px;
    top: 0;
    bottom: 0;
    width: 2px;
    background: #dee2e6;
}

.timeline-item {
    position: relative;
    margin-bottom: 20px;
}

.timeline-marker {
    position: absolute;
    left: -22px;
    top: 0;
    width: 30px;
    height: 30px;
    border-radius: 50%;
    background: white;
    border: 2px solid #dee2e6;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 12px;
}

.timeline-content {
    background: #f8f9fa;
    padding: 15px;
    border-radius: 8px;
    border-left: 3px solid #007bff;
}

.avatar-lg {
    width: 80px;
    height: 80px;
    display: flex;
    align-items: center;
    justify-content: center;
}
</style>

<script>
function showHistory() {
    $('#historyModal').modal('show');

    // Charger l'historique détaillé via AJAX
    fetch('get-absence-history.php?id=<?php echo $absence_id; ?>')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                displayDetailedHistory(data);
            } else {
                $('#historyModalBody').html('<div class="alert alert-danger">Erreur: ' + (data.message || 'Impossible de charger l\'historique') + '</div>');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            $('#historyModalBody').html('<div class="alert alert-danger">Erreur lors du chargement de l\'historique</div>');
        });
}

function displayDetailedHistory(data) {
    let html = '<div class="row mb-4">';
    html += '<div class="col-12">';
    html += '<h6>Informations de l\'absence</h6>';
    html += '<div class="card">';
    html += '<div class="card-body">';
    html += '<div class="row">';
    html += '<div class="col-md-6"><strong>Élève:</strong> ' + data.absence_info.student_name + '</div>';
    html += '<div class="col-md-6"><strong>Classe:</strong> ' + data.absence_info.class_name + '</div>';
    html += '<div class="col-md-6"><strong>Type:</strong> <span class="badge bg-' + getTypeBadgeColor(data.absence_info.type) + '">' + data.absence_info.type + '</span></div>';
    html += '<div class="col-md-6"><strong>Date:</strong> ' + data.absence_info.date + '</div>';
    if (data.absence_info.motif) {
        html += '<div class="col-12 mt-2"><strong>Motif:</strong> ' + data.absence_info.motif + '</div>';
    }
    html += '</div>';
    html += '</div>';
    html += '</div>';
    html += '</div>';
    html += '</div>';

    if (data.history && data.history.length > 0) {
        html += '<h6>Historique des actions</h6>';
        html += '<div class="timeline">';

        data.history.forEach(function(entry) {
            html += '<div class="timeline-item">';
            html += '<div class="timeline-marker">';
            html += '<i class="' + getActionIcon(entry.action) + '"></i>';
            html += '</div>';
            html += '<div class="timeline-content">';
            html += '<div class="d-flex justify-content-between align-items-start">';
            html += '<div>';
            html += '<h6 class="mb-1">' + getActionLabel(entry.action) + '</h6>';
            html += '<p class="mb-1 text-muted">' + entry.details + '</p>';
            html += '<small class="text-muted">Par ' + entry.user_name + ' (' + entry.username + ')</small>';
            html += '</div>';
            html += '<small class="text-muted">' + entry.date + '</small>';
            html += '</div>';
            html += '</div>';
            html += '</div>';
        });

        html += '</div>';
    } else {
        html += '<div class="alert alert-info">Aucun historique disponible.</div>';
    }

    $('#historyModalBody').html(html);
}

function getActionIcon(action) {
    const icons = {
        'create_absence': 'fas fa-plus text-primary',
        'update_absence': 'fas fa-edit text-warning',
        'justify_absence': 'fas fa-check-circle text-success',
        'view_absence_history': 'fas fa-eye text-info'
    };
    return icons[action] || 'fas fa-circle text-secondary';
}

function getActionLabel(action) {
    const labels = {
        'create_absence': 'Création',
        'update_absence': 'Modification',
        'justify_absence': 'Justification',
        'view_absence_history': 'Consultation'
    };
    return labels[action] || action;
}

function getTypeBadgeColor(type) {
    const colors = {
        'absence': 'danger',
        'absence_justifiee': 'success',
        'retard': 'warning',
        'retard_justifie': 'info'
    };
    return colors[type] || 'secondary';
}

// Validation du formulaire de justification
document.getElementById('justifyForm')?.addEventListener('submit', function(e) {
    const justification = document.getElementById('justification').value.trim();
    if (justification.length < 10) {
        e.preventDefault();
        alert('La justification doit contenir au moins 10 caractères.');
        return false;
    }

    if (!confirm('Êtes-vous sûr de vouloir justifier cette absence ? Cette action ne peut pas être annulée.')) {
        e.preventDefault();
        return false;
    }
});

// Validation du fichier uploadé
document.getElementById('document_justificatif')?.addEventListener('change', function(e) {
    const file = e.target.files[0];
    if (file) {
        const maxSize = 5 * 1024 * 1024; // 5MB
        const allowedTypes = ['application/pdf', 'image/jpeg', 'image/png', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];

        if (file.size > maxSize) {
            alert('Le fichier est trop volumineux. Taille maximale : 5MB');
            e.target.value = '';
            return;
        }

        if (!allowedTypes.includes(file.type)) {
            alert('Type de fichier non autorisé. Formats acceptés : PDF, JPG, PNG, DOC, DOCX');
            e.target.value = '';
            return;
        }
    }
});

// Enregistrer l'action de consultation de l'historique
document.addEventListener('DOMContentLoaded', function() {
    // Log de la consultation de la page d'édition
    fetch('log-action.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            action: 'view_absence_edit',
            module: 'attendance',
            target_id: <?php echo $absence_id; ?>,
            details: 'Consultation de la page d\'édition de l\'absence ID <?php echo $absence_id; ?>'
        })
    }).catch(error => console.log('Log error:', error));
});
</script>

<?php include '../../../includes/footer.php'; ?>
