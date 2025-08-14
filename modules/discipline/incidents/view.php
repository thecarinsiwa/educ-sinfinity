<?php
/**
 * Module Discipline - Visualiser un incident
 * Application de gestion scolaire - République Démocratique du Congo
 */

require_once '../../../config/config.php';
require_once '../../../config/database.php';
require_once '../../../includes/functions.php';

// Vérifier l'authentification et les permissions
requireLogin();
if (!checkPermission('discipline')) {
    showMessage('error', 'Accès refusé à cette page.');
    redirectTo('../../../index.php');
}

$incident_id = intval($_GET['id'] ?? 0);

if (!$incident_id) {
    showMessage('error', 'ID d\'incident invalide.');
    redirectTo('../index.php');
}

// Traitement des actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'update_status') {
        $new_status = $_POST['status'] ?? '';
        $notes = trim($_POST['notes'] ?? '');
        
        if (in_array($new_status, ['nouveau', 'en_cours', 'resolu', 'archive'])) {
            try {
                $database->beginTransaction();
                
                // Vérifier si la colonne notes_internes existe
                $columns = $database->query("DESCRIBE incidents")->fetchAll();
                $column_names = array_column($columns, 'Field');
                
                if (in_array('notes_internes', $column_names)) {
                    $sql = "UPDATE incidents SET status = ?, notes_internes = ?, updated_at = NOW() WHERE id = ?";
                    $params = [$new_status, $notes, $incident_id];
                } else {
                    $sql = "UPDATE incidents SET status = ? WHERE id = ?";
                    $params = [$new_status, $incident_id];
                }
                
                $database->execute($sql, $params);
                $database->commit();
                
                showMessage('success', 'Statut de l\'incident mis à jour avec succès !');
                
            } catch (Exception $e) {
                $database->rollback();
                showMessage('error', 'Erreur lors de la mise à jour : ' . $e->getMessage());
            }
        }
    }
}

// Récupérer les détails de l'incident
try {
    // Vérifier d'abord la structure de la table
    $columns = $database->query("DESCRIBE incidents")->fetchAll();
    $column_names = array_column($columns, 'Field');
    
    $has_rapporte_par = in_array('rapporte_par', $column_names);
    $has_notes_internes = in_array('notes_internes', $column_names);
    
    if ($has_rapporte_par) {
        // Nouvelle structure
        $sql = "SELECT i.*, 
                       e.numero_matricule, e.nom as eleve_nom, e.prenom as eleve_prenom,
                       e.date_naissance, e.telephone as eleve_telephone,
                       c.nom as classe_nom, c.niveau, c.section,
                       p.nom as rapporte_par_nom, p.prenom as rapporte_par_prenom, p.fonction,
                       TIMESTAMPDIFF(YEAR, e.date_naissance, CURDATE()) as eleve_age,
                       DATEDIFF(NOW(), i.date_incident) as jours_depuis
                FROM incidents i
                JOIN eleves e ON i.eleve_id = e.id
                LEFT JOIN classes c ON i.classe_id = c.id
                LEFT JOIN personnel p ON i.rapporte_par = p.id
                WHERE i.id = ?";
    } else {
        // Ancienne structure
        $sql = "SELECT i.*, 
                       e.numero_matricule, e.nom as eleve_nom, e.prenom as eleve_prenom,
                       e.date_naissance, e.telephone as eleve_telephone,
                       c.nom as classe_nom, c.niveau, c.section,
                       TIMESTAMPDIFF(YEAR, e.date_naissance, CURDATE()) as eleve_age,
                       DATEDIFF(NOW(), i.date_incident) as jours_depuis,
                       'Personnel' as rapporte_par_nom, '' as rapporte_par_prenom, '' as fonction
                FROM incidents i
                JOIN eleves e ON i.eleve_id = e.id
                LEFT JOIN classes c ON i.classe_id = c.id
                WHERE i.id = ?";
    }
    
    $incident = $database->query($sql, [$incident_id])->fetch();
    
    if (!$incident) {
        showMessage('error', 'Incident non trouvé.');
        redirectTo('../index.php');
    }
    
} catch (Exception $e) {
    showMessage('error', 'Erreur lors du chargement de l\'incident : ' . $e->getMessage());
    redirectTo('../index.php');
}

// Récupérer les sanctions liées à cet incident
try {
    $sanctions = [];
    $tables = $database->query("SHOW TABLES LIKE 'sanctions'")->fetch();
    
    if ($tables) {
        $columns = $database->query("DESCRIBE sanctions")->fetchAll();
        $column_names = array_column($columns, 'Field');
        
        if (in_array('incident_id', $column_names) && in_array('type_sanction_id', $column_names)) {
            // Nouvelle structure avec types_sanctions
            $types_sanctions_exists = $database->query("SHOW TABLES LIKE 'types_sanctions'")->fetch();
            
            if ($types_sanctions_exists) {
                $sanctions = $database->query(
                    "SELECT s.*, ts.nom as type_nom, ts.couleur,
                            p.nom as prononcee_par_nom, p.prenom as prononcee_par_prenom
                     FROM sanctions s
                     JOIN types_sanctions ts ON s.type_sanction_id = ts.id
                     LEFT JOIN personnel p ON s.prononcee_par = p.id
                     WHERE s.incident_id = ?
                     ORDER BY s.date_sanction DESC",
                    [$incident_id]
                )->fetchAll();
            }
        } else {
            // Ancienne structure
            $sanctions = $database->query(
                "SELECT s.*, s.type_sanction as type_nom, '#ffc107' as couleur,
                        p.nom as prononcee_par_nom, p.prenom as prononcee_par_prenom
                 FROM sanctions s
                 LEFT JOIN personnel p ON s.enseignant_id = p.id
                 WHERE s.eleve_id = ? AND DATE(s.date_sanction) >= DATE(?)
                 ORDER BY s.date_sanction DESC",
                [$incident['eleve_id'], $incident['date_incident']]
            )->fetchAll();
        }
    }
} catch (Exception $e) {
    $sanctions = [];
}

$page_title = "Incident #" . $incident_id;
include '../../../includes/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">
        <i class="fas fa-exclamation-triangle me-2 text-warning"></i>
        Incident #<?php echo $incident_id; ?>
    </h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <div class="btn-group me-2">
            <a href="../index.php" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-1"></i>
                Retour au tableau de bord
            </a>
        </div>
        <div class="btn-group me-2">
            <a href="../sanctions/add.php?incident_id=<?php echo $incident_id; ?>&eleve_id=<?php echo $incident['eleve_id']; ?>" 
               class="btn btn-outline-danger">
                <i class="fas fa-gavel me-1"></i>
                Prononcer une sanction
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
    <!-- Détails de l'incident -->
    <div class="col-lg-8">
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-light d-flex justify-content-between align-items-center">
                <h5 class="mb-0">
                    <i class="fas fa-file-alt me-2"></i>
                    Détails de l'incident
                </h5>
                <span class="badge bg-<?php 
                    echo match($incident['status']) {
                        'nouveau' => 'danger',
                        'en_cours' => 'warning',
                        'resolu' => 'success',
                        'archive' => 'secondary',
                        default => 'secondary'
                    };
                ?> fs-6">
                    <?php echo ucfirst(str_replace('_', ' ', $incident['status'])); ?>
                </span>
            </div>
            <div class="card-body">
                <div class="row mb-3">
                    <div class="col-md-6">
                        <h6 class="text-primary">Informations générales</h6>
                        <table class="table table-sm table-borderless">
                            <tr>
                                <td><strong>Date :</strong></td>
                                <td><?php echo formatDateTime($incident['date_incident'], 'd/m/Y H:i'); ?></td>
                            </tr>
                            <tr>
                                <td><strong>Lieu :</strong></td>
                                <td><?php echo htmlspecialchars($incident['lieu'] ?: 'Non précisé'); ?></td>
                            </tr>
                            <tr>
                                <td><strong>Gravité :</strong></td>
                                <td>
                                    <span class="badge bg-<?php 
                                        echo match($incident['gravite']) {
                                            'legere' => 'success',
                                            'moyenne' => 'warning',
                                            'grave' => 'danger',
                                            'tres_grave' => 'dark',
                                            default => 'secondary'
                                        };
                                    ?>">
                                        <?php echo ucfirst(str_replace('_', ' ', $incident['gravite'])); ?>
                                    </span>
                                </td>
                            </tr>
                            <tr>
                                <td><strong>Signalé par :</strong></td>
                                <td>
                                    <?php if ($incident['rapporte_par_nom']): ?>
                                        <?php echo htmlspecialchars($incident['rapporte_par_nom'] . ' ' . $incident['rapporte_par_prenom']); ?>
                                        <?php if ($incident['fonction']): ?>
                                            <br><small class="text-muted"><?php echo htmlspecialchars($incident['fonction']); ?></small>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        Personnel de l'établissement
                                    <?php endif; ?>
                                </td>
                            </tr>
                        </table>
                    </div>
                    
                    <div class="col-md-6">
                        <h6 class="text-primary">Élève concerné</h6>
                        <table class="table table-sm table-borderless">
                            <tr>
                                <td><strong>Nom :</strong></td>
                                <td><?php echo htmlspecialchars($incident['eleve_nom'] . ' ' . $incident['eleve_prenom']); ?></td>
                            </tr>
                            <tr>
                                <td><strong>Matricule :</strong></td>
                                <td><?php echo htmlspecialchars($incident['numero_matricule']); ?></td>
                            </tr>
                            <tr>
                                <td><strong>Classe :</strong></td>
                                <td>
                                    <?php if ($incident['classe_nom']): ?>
                                        <?php echo htmlspecialchars($incident['classe_nom']); ?>
                                        <small class="text-muted">(<?php echo ucfirst($incident['niveau']); ?>)</small>
                                    <?php else: ?>
                                        Non définie
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <tr>
                                <td><strong>Âge :</strong></td>
                                <td><?php echo $incident['eleve_age']; ?> ans</td>
                            </tr>
                        </table>
                    </div>
                </div>
                
                <div class="mb-3">
                    <h6 class="text-primary">Description de l'incident</h6>
                    <div class="p-3 bg-light rounded">
                        <?php echo nl2br(htmlspecialchars($incident['description'])); ?>
                    </div>
                </div>
                
                <?php if (!empty($incident['temoins'])): ?>
                <div class="mb-3">
                    <h6 class="text-primary">Témoins</h6>
                    <div class="p-3 bg-light rounded">
                        <?php echo nl2br(htmlspecialchars($incident['temoins'])); ?>
                    </div>
                </div>
                <?php endif; ?>
                
                <?php if ($has_notes_internes && !empty($incident['notes_internes'])): ?>
                <div class="mb-3">
                    <h6 class="text-primary">Notes internes</h6>
                    <div class="p-3 bg-warning bg-opacity-10 rounded border-start border-warning border-4">
                        <?php echo nl2br(htmlspecialchars($incident['notes_internes'])); ?>
                    </div>
                </div>
                <?php endif; ?>
                
                <div class="row text-muted small">
                    <div class="col-md-6">
                        <i class="fas fa-calendar me-1"></i>
                        Signalé il y a <?php echo $incident['jours_depuis']; ?> jour(s)
                    </div>
                    <div class="col-md-6 text-end">
                        <i class="fas fa-clock me-1"></i>
                        Créé le <?php echo formatDateTime($incident['created_at'] ?? $incident['date_incident'], 'd/m/Y à H:i'); ?>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Sanctions liées -->
        <?php if (!empty($sanctions)): ?>
        <div class="card shadow-sm">
            <div class="card-header bg-light">
                <h5 class="mb-0">
                    <i class="fas fa-gavel me-2"></i>
                    Sanctions prononcées (<?php echo count($sanctions); ?>)
                </h5>
            </div>
            <div class="card-body">
                <?php foreach ($sanctions as $sanction): ?>
                    <div class="d-flex align-items-start mb-3 p-3 border rounded">
                        <div class="flex-shrink-0 me-3">
                            <span class="badge" style="background-color: <?php echo $sanction['couleur'] ?? '#6c757d'; ?>">
                                <?php echo htmlspecialchars($sanction['type_nom']); ?>
                            </span>
                        </div>
                        <div class="flex-grow-1">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <h6 class="mb-0"><?php echo htmlspecialchars($sanction['type_nom']); ?></h6>
                                <small class="text-muted">
                                    <?php echo formatDate($sanction['date_sanction']); ?>
                                </small>
                            </div>
                            
                            <?php if (!empty($sanction['description'])): ?>
                                <p class="mb-2"><?php echo nl2br(htmlspecialchars($sanction['description'])); ?></p>
                            <?php endif; ?>
                            
                            <div class="row small text-muted">
                                <div class="col-md-6">
                                    <?php if ($sanction['prononcee_par_nom']): ?>
                                        <i class="fas fa-user me-1"></i>
                                        Par <?php echo htmlspecialchars($sanction['prononcee_par_nom'] . ' ' . $sanction['prononcee_par_prenom']); ?>
                                    <?php endif; ?>
                                </div>
                                <div class="col-md-6 text-end">
                                    <span class="badge bg-<?php echo ($sanction['status'] === 'active') ? 'success' : 'secondary'; ?>">
                                        <?php echo ucfirst($sanction['status']); ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
    
    <!-- Actions et informations complémentaires -->
    <div class="col-lg-4">
        <!-- Changer le statut -->
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-primary text-white">
                <h6 class="mb-0">
                    <i class="fas fa-edit me-2"></i>
                    Modifier le statut
                </h6>
            </div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="action" value="update_status">
                    
                    <div class="mb-3">
                        <label for="status" class="form-label">Nouveau statut</label>
                        <select class="form-select" id="status" name="status" required>
                            <option value="nouveau" <?php echo ($incident['status'] === 'nouveau') ? 'selected' : ''; ?>>
                                🔴 Nouveau
                            </option>
                            <option value="en_cours" <?php echo ($incident['status'] === 'en_cours') ? 'selected' : ''; ?>>
                                🟡 En cours de traitement
                            </option>
                            <option value="resolu" <?php echo ($incident['status'] === 'resolu') ? 'selected' : ''; ?>>
                                🟢 Résolu
                            </option>
                            <option value="archive" <?php echo ($incident['status'] === 'archive') ? 'selected' : ''; ?>>
                                📁 Archivé
                            </option>
                        </select>
                    </div>
                    
                    <?php if ($has_notes_internes): ?>
                    <div class="mb-3">
                        <label for="notes" class="form-label">Notes internes</label>
                        <textarea class="form-control" id="notes" name="notes" rows="3"
                                  placeholder="Ajoutez des notes sur le traitement de cet incident..."><?php echo htmlspecialchars($incident['notes_internes'] ?? ''); ?></textarea>
                    </div>
                    <?php endif; ?>
                    
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-save me-1"></i>
                        Mettre à jour
                    </button>
                </form>
            </div>
        </div>
        
        <!-- Actions rapides -->
        <div class="card shadow-sm">
            <div class="card-header bg-light">
                <h6 class="mb-0">
                    <i class="fas fa-bolt me-2"></i>
                    Actions rapides
                </h6>
            </div>
            <div class="card-body">
                <div class="d-grid gap-2">
                    <a href="../sanctions/add.php?incident_id=<?php echo $incident_id; ?>&eleve_id=<?php echo $incident['eleve_id']; ?>" 
                       class="btn btn-outline-danger">
                        <i class="fas fa-gavel me-1"></i>
                        Prononcer une sanction
                    </a>
                    
                    <a href="../recompenses/add.php?eleve_id=<?php echo $incident['eleve_id']; ?>" 
                       class="btn btn-outline-success">
                        <i class="fas fa-award me-1"></i>
                        Attribuer une récompense
                    </a>
                    
                    <a href="../../students/view.php?id=<?php echo $incident['eleve_id']; ?>" 
                       class="btn btn-outline-info">
                        <i class="fas fa-user me-1"></i>
                        Voir le dossier élève
                    </a>
                </div>
                
                <hr>
                
                <div class="small text-muted">
                    <h6 class="text-primary">Informations</h6>
                    <ul class="list-unstyled">
                        <li><i class="fas fa-calendar me-1"></i> Créé le <?php echo formatDate($incident['created_at'] ?? $incident['date_incident']); ?></li>
                        <li><i class="fas fa-clock me-1"></i> Il y a <?php echo $incident['jours_depuis']; ?> jour(s)</li>
                        <?php if (!empty($sanctions)): ?>
                            <li><i class="fas fa-gavel me-1"></i> <?php echo count($sanctions); ?> sanction(s) liée(s)</li>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>
        </div>
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
}
</style>

<?php include '../../../includes/footer.php'; ?>
