<?php
/**
 * Module Bibliothèque - Visualisation d'un livre
 * Application de gestion scolaire - République Démocratique du Congo
 */

require_once '../../../config/config.php';
require_once '../../../config/database.php';
require_once '../../../includes/functions.php';

// Vérifier l'authentification et les permissions
requireLogin();
if (!checkPermission('library') && !checkPermission('library_view')) {
    showMessage('error', 'Accès refusé à ce module.');
    redirectTo('../../../index.php');
}

// Récupérer l'ID du livre
$livre_id = intval($_GET['id'] ?? 0);
if (!$livre_id) {
    showMessage('error', 'ID du livre manquant.');
    redirectTo('index.php');
}

// Traitement des actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && checkPermission('library')) {
    try {
        $action = $_POST['action'] ?? '';
        
        switch ($action) {
            case 'update_status':
                $new_status = $_POST['status'] ?? '';
                $valid_statuses = ['disponible', 'emprunte', 'reserve', 'perdu', 'retire'];
                
                if (!in_array($new_status, $valid_statuses)) {
                    throw new Exception('Statut invalide.');
                }
                
                $database->execute(
                    "UPDATE livres SET status = ?, updated_at = NOW() WHERE id = ?",
                    [$new_status, $livre_id]
                );
                
                showMessage('success', 'Statut du livre mis à jour avec succès.');
                break;
        }
    } catch (Exception $e) {
        showMessage('error', 'Erreur : ' . $e->getMessage());
    }
}

// Récupérer les informations du livre
try {
    $livre = $database->query(
        "SELECT l.*, cl.nom as categorie_nom, cl.couleur as categorie_couleur
         FROM livres l
         LEFT JOIN categories_livres cl ON l.categorie_id = cl.id
         WHERE l.id = ?",
        [$livre_id]
    )->fetch();
    
    if (!$livre) {
        showMessage('error', 'Livre non trouvé.');
        redirectTo('index.php');
    }
    
} catch (Exception $e) {
    showMessage('error', 'Erreur lors du chargement : ' . $e->getMessage());
    redirectTo('index.php');
}

// Récupérer l'historique des emprunts
try {
    $emprunts = $database->query(
        "SELECT el.*, 
                CASE 
                    WHEN el.emprunteur_type = 'eleve' THEN e.nom
                    WHEN el.emprunteur_type = 'personnel' THEN p.nom
                    ELSE 'Inconnu'
                END as emprunteur_nom,
                CASE 
                    WHEN el.emprunteur_type = 'eleve' THEN e.prenom
                    WHEN el.emprunteur_type = 'personnel' THEN p.prenom
                    ELSE ''
                END as emprunteur_prenom,
                CASE 
                    WHEN el.emprunteur_type = 'eleve' THEN e.numero_matricule
                    WHEN el.emprunteur_type = 'personnel' THEN p.matricule
                    ELSE ''
                END as emprunteur_matricule,
                u.nom as user_nom, u.prenom as user_prenom
         FROM emprunts_livres el
         LEFT JOIN eleves e ON el.emprunteur_type = 'eleve' AND el.emprunteur_id = e.id
         LEFT JOIN personnel p ON el.emprunteur_type = 'personnel' AND el.emprunteur_id = p.id
         LEFT JOIN users u ON el.traite_par = u.id
         WHERE el.livre_id = ?
         ORDER BY el.date_emprunt DESC
         LIMIT 20",
        [$livre_id]
    )->fetchAll();
} catch (Exception $e) {
    $emprunts = [];
}

$page_title = "Livre : " . htmlspecialchars($livre['titre']);
include '../../../includes/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">
        <i class="fas fa-book me-2"></i>
        <?php echo htmlspecialchars($livre['titre']); ?>
    </h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <div class="btn-group me-2">
            <a href="index.php" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-1"></i>
                Retour au catalogue
            </a>
        </div>
        <?php if (checkPermission('library')): ?>
            <div class="btn-group me-2">
                <a href="edit.php?id=<?php echo $livre_id; ?>" class="btn btn-primary">
                    <i class="fas fa-edit me-1"></i>
                    Modifier
                </a>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Alertes -->
<?php if ($livre['status'] === 'perdu'): ?>
    <div class="alert alert-danger">
        <i class="fas fa-exclamation-triangle me-2"></i>
        <strong>Attention :</strong> Ce livre est marqué comme perdu.
    </div>
<?php elseif ($livre['status'] === 'retire'): ?>
    <div class="alert alert-warning">
        <i class="fas fa-archive me-2"></i>
        <strong>Information :</strong> Ce livre est retiré du service.
    </div>
<?php endif; ?>

<div class="row">
    <!-- Informations principales -->
    <div class="col-md-8">
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-info-circle me-2"></i>
                    Informations du livre
                </h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <table class="table table-borderless">
                            <tr>
                                <td class="fw-bold" style="width: 140px;">Titre :</td>
                                <td><?php echo htmlspecialchars($livre['titre']); ?></td>
                            </tr>
                            <tr>
                                <td class="fw-bold">Auteur :</td>
                                <td><?php echo htmlspecialchars($livre['auteur']); ?></td>
                            </tr>
                            <tr>
                                <td class="fw-bold">Éditeur :</td>
                                <td><?php echo htmlspecialchars($livre['editeur']); ?></td>
                            </tr>
                            <tr>
                                <td class="fw-bold">ISBN :</td>
                                <td>
                                    <?php if ($livre['isbn']): ?>
                                        <code><?php echo htmlspecialchars($livre['isbn']); ?></code>
                                    <?php else: ?>
                                        <span class="text-muted">Non renseigné</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <tr>
                                <td class="fw-bold">Année :</td>
                                <td>
                                    <?php echo $livre['annee_publication'] ? htmlspecialchars($livre['annee_publication']) : '<span class="text-muted">Non renseigné</span>'; ?>
                                </td>
                            </tr>
                            <tr>
                                <td class="fw-bold">Catégorie :</td>
                                <td>
                                    <?php if ($livre['categorie_nom']): ?>
                                        <span class="badge" style="background-color: <?php echo htmlspecialchars($livre['categorie_couleur']); ?>">
                                            <?php echo htmlspecialchars($livre['categorie_nom']); ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-muted">Non catégorisé</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <table class="table table-borderless">
                            <tr>
                                <td class="fw-bold" style="width: 140px;">Pages :</td>
                                <td>
                                    <?php echo (isset($livre['nombre_pages']) && $livre['nombre_pages']) ? htmlspecialchars($livre['nombre_pages']) . ' pages' : '<span class="text-muted">Non renseigné</span>'; ?>
                                </td>
                            </tr>
                            <tr>
                                <td class="fw-bold">Langue :</td>
                                <td><?php echo isset($livre['langue']) ? htmlspecialchars($livre['langue']) : 'Français'; ?></td>
                            </tr>
                            <tr>
                                <td class="fw-bold">Cote :</td>
                                <td>
                                    <?php if (isset($livre['cote']) && $livre['cote']): ?>
                                        <code><?php echo htmlspecialchars($livre['cote']); ?></code>
                                    <?php else: ?>
                                        <span class="text-muted">Non renseigné</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <tr>
                                <td class="fw-bold">Prix d'achat :</td>
                                <td>
                                    <?php if (isset($livre['prix_achat']) && $livre['prix_achat']): ?>
                                        <?php echo number_format($livre['prix_achat'], 0, ',', ' ') . ' FC'; ?>
                                    <?php else: ?>
                                        <span class="text-muted">Non renseigné</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <tr>
                                <td class="fw-bold">Date d'acquisition :</td>
                                <td>
                                    <?php if (isset($livre['date_acquisition']) && $livre['date_acquisition']): ?>
                                        <?php echo date('d/m/Y', strtotime($livre['date_acquisition'])); ?>
                                    <?php else: ?>
                                        <span class="text-muted">Non renseigné</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <tr>
                                <td class="fw-bold">État :</td>
                                <td>
                                    <?php
                                    $etat_colors = [
                                        'excellent' => 'success',
                                        'bon' => 'info',
                                        'moyen' => 'warning',
                                        'mauvais' => 'danger'
                                    ];
                                    $etat_labels = [
                                        'excellent' => 'Excellent',
                                        'bon' => 'Bon',
                                        'moyen' => 'Moyen',
                                        'mauvais' => 'Mauvais'
                                    ];
                                    $etat = isset($livre['etat']) ? $livre['etat'] : 'bon';
                                    ?>
                                    <span class="badge bg-<?php echo $etat_colors[$etat] ?? 'secondary'; ?>">
                                        <?php echo $etat_labels[$etat] ?? ucfirst($etat); ?>
                                    </span>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>
                
                <?php if (isset($livre['resume']) && $livre['resume']): ?>
                    <hr>
                    <h6>Résumé :</h6>
                    <p class="text-muted"><?php echo nl2br(htmlspecialchars($livre['resume'])); ?></p>
                <?php endif; ?>
                
                <?php if (isset($livre['notes']) && $livre['notes']): ?>
                    <hr>
                    <h6>Notes :</h6>
                    <p class="text-muted"><?php echo nl2br(htmlspecialchars($livre['notes'])); ?></p>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Statistiques et actions -->
    <div class="col-md-4">
        <!-- Statut et disponibilité -->
        <div class="card mb-4">
            <div class="card-header">
                <h6 class="mb-0">
                    <i class="fas fa-chart-bar me-2"></i>
                    Statut et disponibilité
                </h6>
            </div>
            <div class="card-body">
                <div class="row text-center">
                    <div class="col-6">
                        <div class="border-end">
                            <h4 class="text-primary"><?php echo isset($livre['nombre_disponibles']) ? $livre['nombre_disponibles'] : 1; ?></h4>
                            <small class="text-muted">Exemplaires totaux</small>
                        </div>
                    </div>
                    <div class="col-6">
                        <h4 class="text-success"><?php echo isset($livre['exemplaires_disponibles']) ? $livre['exemplaires_disponibles'] : 1; ?></h4>
                        <small class="text-muted">Disponibles</small>
                    </div>
                </div>
                
                <hr>
                
                <div class="mb-3">
                    <label class="form-label fw-bold">Statut actuel :</label>
                    <div class="d-flex align-items-center">
                        <?php
                        $status_colors = [
                            'disponible' => 'success',
                            'emprunte' => 'info',
                            'reserve' => 'warning',
                            'perdu' => 'danger',
                            'retire' => 'secondary'
                        ];
                        $status_labels = [
                            'disponible' => 'Disponible',
                            'emprunte' => 'Emprunté',
                            'reserve' => 'Réservé',
                            'perdu' => 'Perdu',
                            'retire' => 'Retiré'
                        ];
                        ?>
                        <span class="badge bg-<?php echo $status_colors[$livre['status']] ?? 'secondary'; ?> me-2">
                            <?php echo $status_labels[$livre['status']] ?? ucfirst($livre['status']); ?>
                        </span>
                        
                        <?php if (checkPermission('library')): ?>
                            <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#statusModal">
                                <i class="fas fa-edit"></i>
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
                
                <?php if (checkPermission('library')): ?>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Actions rapides :</label>
                        <div class="d-grid gap-2">
                            <a href="../loans/add.php?livre_id=<?php echo $livre_id; ?>" class="btn btn-success btn-sm">
                                <i class="fas fa-hand-holding me-1"></i>
                                Nouvel emprunt
                            </a>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Informations système -->
        <div class="card">
            <div class="card-header">
                <h6 class="mb-0">
                    <i class="fas fa-cog me-2"></i>
                    Informations système
                </h6>
            </div>
            <div class="card-body">
                <small class="text-muted">
                    <div>ID : <?php echo $livre_id; ?></div>
                    <div>Créé le : <?php echo date('d/m/Y H:i', strtotime($livre['created_at'])); ?></div>
                    <?php if (isset($livre['updated_at']) && $livre['updated_at']): ?>
                        <div>Modifié le : <?php echo date('d/m/Y H:i', strtotime($livre['updated_at'])); ?></div>
                    <?php endif; ?>
                </small>
            </div>
        </div>
    </div>
</div>

<!-- Historique des emprunts -->
<?php if (!empty($emprunts)): ?>
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">
                <i class="fas fa-history me-2"></i>
                Historique des emprunts
            </h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Emprunteur</th>
                            <th>Date d'emprunt</th>
                            <th>Date de retour prévue</th>
                            <th>Date de retour effective</th>
                            <th>Statut</th>
                            <th>Traité par</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($emprunts as $emprunt): ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($emprunt['emprunteur_prenom'] . ' ' . $emprunt['emprunteur_nom']); ?></strong>
                                    <br>
                                    <small class="text-muted">
                                        <?php echo htmlspecialchars($emprunt['emprunteur_matricule']); ?>
                                        <?php if ($emprunt['emprunteur_type']): ?>
                                            <span class="badge bg-secondary ms-1"><?php echo ucfirst($emprunt['emprunteur_type']); ?></span>
                                        <?php endif; ?>
                                    </small>
                                </td>
                                <td><?php echo date('d/m/Y', strtotime($emprunt['date_emprunt'])); ?></td>
                                <td><?php echo date('d/m/Y', strtotime($emprunt['date_retour_prevue'])); ?></td>
                                <td>
                                    <?php if ($emprunt['date_retour_effective']): ?>
                                        <?php echo date('d/m/Y', strtotime($emprunt['date_retour_effective'])); ?>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php
                                    $status_colors = [
                                        'en_cours' => 'info',
                                        'termine' => 'success',
                                        'en_retard' => 'danger'
                                    ];
                                    $status_labels = [
                                        'en_cours' => 'En cours',
                                        'termine' => 'Terminé',
                                        'en_retard' => 'En retard'
                                    ];
                                    ?>
                                    <span class="badge bg-<?php echo $status_colors[$emprunt['status']] ?? 'secondary'; ?>">
                                        <?php echo $status_labels[$emprunt['status']] ?? ucfirst($emprunt['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($emprunt['user_nom']): ?>
                                        <?php echo htmlspecialchars($emprunt['user_prenom'] . ' ' . $emprunt['user_nom']); ?>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
<?php endif; ?>

<!-- Modal pour changer le statut -->
<?php if (checkPermission('library')): ?>
    <div class="modal fade" id="statusModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <input type="hidden" name="action" value="update_status">
                    <div class="modal-header">
                        <h5 class="modal-title">Changer le statut du livre</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="status" class="form-label">Nouveau statut :</label>
                            <select class="form-select" name="status" id="status" required>
                                <option value="disponible" <?php echo $livre['status'] === 'disponible' ? 'selected' : ''; ?>>Disponible</option>
                                <option value="emprunte" <?php echo $livre['status'] === 'emprunte' ? 'selected' : ''; ?>>Emprunté</option>
                                <option value="reserve" <?php echo $livre['status'] === 'reserve' ? 'selected' : ''; ?>>Réservé</option>
                                <option value="perdu" <?php echo $livre['status'] === 'perdu' ? 'selected' : ''; ?>>Perdu</option>
                                <option value="retire" <?php echo $livre['status'] === 'retire' ? 'selected' : ''; ?>>Retiré</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" class="btn btn-primary">Enregistrer</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php include '../../../includes/footer.php'; ?>
