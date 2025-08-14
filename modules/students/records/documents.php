<?php
/**
 * Gestion des documents d'un élève
 * Application de gestion scolaire - République Démocratique du Congo
 */

require_once '../../../config/config.php';
require_once '../../../config/database.php';
require_once '../../../includes/functions.php';

// Vérifier l'authentification et les permissions
requireLogin();
if (!checkPermission('students') && !checkPermission('students_view')) {
    showMessage('error', 'Accès refusé à ce module.');
    redirectTo('../../dashboard.php');
}

$eleve_id = intval($_GET['id'] ?? 0);

if (!$eleve_id) {
    showMessage('error', 'ID d\'élève invalide.');
    redirectTo('index.php');
}

// Obtenir l'année scolaire actuelle
$current_year = getCurrentAcademicYear();

// Récupérer les informations de l'élève
try {
    $eleve = $database->query(
        "SELECT e.*, CONCAT('INS', YEAR(i.date_inscription), LPAD(i.id, 4, '0')) as numero_inscription,
                c.nom as classe_nom, c.niveau
         FROM eleves e
         JOIN inscriptions i ON e.id = i.eleve_id
         JOIN classes c ON i.classe_id = c.id
         WHERE e.id = ? AND i.annee_scolaire_id = ?",
        [$eleve_id, $current_year['id'] ?? 0]
    )->fetch();

    if (!$eleve) {
        showMessage('error', 'Élève non trouvé ou non inscrit pour l\'année scolaire actuelle.');
        redirectTo('index.php');
    }
} catch (Exception $e) {
    showMessage('error', 'Erreur lors du chargement de l\'élève : ' . $e->getMessage());
    redirectTo('index.php');
}

// Traitement des actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add_document' && checkPermission('students')) {
        $type_document = $_POST['type_document'] ?? '';
        $nom_document = sanitizeInput($_POST['nom_document'] ?? '');
        $description = sanitizeInput($_POST['description'] ?? '');
        $obligatoire = isset($_POST['obligatoire']) ? 1 : 0;
        
        if ($type_document && $nom_document) {
            try {
                $database->execute(
                    "INSERT INTO documents_eleves (eleve_id, type_document, nom_document, description, obligatoire, ajoute_par, date_ajout) 
                     VALUES (?, ?, ?, ?, ?, ?, NOW())",
                    [$eleve_id, $type_document, $nom_document, $description, $obligatoire, $_SESSION['user_id']]
                );
                showMessage('success', 'Document ajouté avec succès.');
            } catch (Exception $e) {
                showMessage('error', 'Erreur lors de l\'ajout du document : ' . $e->getMessage());
            }
        } else {
            showMessage('error', 'Type de document et nom sont obligatoires.');
        }
    }
    
    if ($action === 'update_status' && checkPermission('students')) {
        $document_id = intval($_POST['document_id'] ?? 0);
        $statut = $_POST['statut'] ?? '';
        $commentaire = sanitizeInput($_POST['commentaire'] ?? '');
        
        if ($document_id && in_array($statut, ['en_attente', 'verifie', 'rejete'])) {
            try {
                $database->execute(
                    "UPDATE documents_eleves SET 
                     statut_verification = ?, commentaire_verification = ?, 
                     verifie_par = ?, date_verification = NOW()
                     WHERE id = ? AND eleve_id = ?",
                    [$statut, $commentaire, $_SESSION['user_id'], $document_id, $eleve_id]
                );
                showMessage('success', 'Statut du document mis à jour.');
            } catch (Exception $e) {
                showMessage('error', 'Erreur lors de la mise à jour : ' . $e->getMessage());
            }
        }
    }
    
    if ($action === 'delete_document' && checkPermission('students')) {
        $document_id = intval($_POST['document_id'] ?? 0);
        
        if ($document_id) {
            try {
                $database->execute(
                    "DELETE FROM documents_eleves WHERE id = ? AND eleve_id = ?",
                    [$document_id, $eleve_id]
                );
                showMessage('success', 'Document supprimé avec succès.');
            } catch (Exception $e) {
                showMessage('error', 'Erreur lors de la suppression : ' . $e->getMessage());
            }
        }
    }
}

// Récupérer les documents de l'élève
try {
    $documents = $database->query(
        "SELECT de.*, u1.username as ajoute_par_nom, u2.username as verifie_par_nom
         FROM documents_eleves de
         LEFT JOIN users u1 ON de.ajoute_par = u1.id
         LEFT JOIN users u2 ON de.verifie_par = u2.id
         WHERE de.eleve_id = ?
         ORDER BY de.date_ajout DESC",
        [$eleve_id]
    )->fetchAll();
} catch (Exception $e) {
    $documents = [];
}

// Types de documents disponibles
$types_documents = [
    'certificat_naissance' => 'Certificat de naissance',
    'bulletin_precedent' => 'Bulletin de l\'année précédente',
    'certificat_medical' => 'Certificat médical',
    'photo_identite' => 'Photo d\'identité',
    'fiche_inscription' => 'Fiche d\'inscription',
    'attestation_scolarite' => 'Attestation de scolarité',
    'releve_notes' => 'Relevé de notes',
    'certificat_conduite' => 'Certificat de bonne conduite',
    'autre' => 'Autre document'
];

$page_title = 'Documents de ' . $eleve['nom'] . ' ' . $eleve['prenom'];

include '../../../includes/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">
        <i class="fas fa-file-alt me-2"></i>
        Gestion des Documents
    </h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <div class="btn-group me-2">
            <a href="view.php?id=<?php echo $eleve_id; ?>" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-1"></i>
                Retour au dossier
            </a>
        </div>
        <div class="btn-group me-2">
            <a href="index.php" class="btn btn-outline-secondary">
                <i class="fas fa-list me-1"></i>
                Liste des dossiers
            </a>
        </div>
        <?php if (checkPermission('students')): ?>
        <div class="btn-group">
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addDocumentModal">
                <i class="fas fa-plus me-1"></i>
                Ajouter un document
            </button>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Informations de l'élève -->
<div class="alert alert-info mb-4">
    <div class="row">
        <div class="col-md-3">
            <strong>Élève :</strong> <?php echo htmlspecialchars($eleve['nom'] . ' ' . $eleve['prenom']); ?>
        </div>
        <div class="col-md-3">
            <strong>N° Inscription :</strong> <code><?php echo htmlspecialchars($eleve['numero_inscription']); ?></code>
        </div>
        <div class="col-md-3">
            <strong>Classe :</strong> <?php echo htmlspecialchars($eleve['classe_nom']); ?>
        </div>
        <div class="col-md-3">
            <strong>Total documents :</strong> <?php echo count($documents); ?>
        </div>
    </div>
</div>

<!-- Statistiques des documents -->
<div class="row mb-4">
    <?php
    $stats = [
        'total' => count($documents),
        'verifie' => count(array_filter($documents, fn($d) => $d['statut_verification'] === 'verifie')),
        'en_attente' => count(array_filter($documents, fn($d) => $d['statut_verification'] === 'en_attente')),
        'rejete' => count(array_filter($documents, fn($d) => $d['statut_verification'] === 'rejete')),
        'obligatoire' => count(array_filter($documents, fn($d) => $d['obligatoire'] == 1))
    ];
    ?>
    <div class="col-md-2">
        <div class="card text-center">
            <div class="card-body">
                <h4 class="text-primary"><?php echo $stats['total']; ?></h4>
                <small class="text-muted">Total</small>
            </div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card text-center">
            <div class="card-body">
                <h4 class="text-success"><?php echo $stats['verifie']; ?></h4>
                <small class="text-muted">Vérifiés</small>
            </div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card text-center">
            <div class="card-body">
                <h4 class="text-warning"><?php echo $stats['en_attente']; ?></h4>
                <small class="text-muted">En attente</small>
            </div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card text-center">
            <div class="card-body">
                <h4 class="text-danger"><?php echo $stats['rejete']; ?></h4>
                <small class="text-muted">Rejetés</small>
            </div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card text-center">
            <div class="card-body">
                <h4 class="text-info"><?php echo $stats['obligatoire']; ?></h4>
                <small class="text-muted">Obligatoires</small>
            </div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card text-center">
            <div class="card-body">
                <?php $pourcentage = $stats['total'] > 0 ? round(($stats['verifie'] / $stats['total']) * 100) : 0; ?>
                <h4 class="text-secondary"><?php echo $pourcentage; ?>%</h4>
                <small class="text-muted">Complétude</small>
            </div>
        </div>
    </div>
</div>

<!-- Liste des documents -->
<div class="card">
    <div class="card-header">
        <h5 class="mb-0">
            <i class="fas fa-list me-2"></i>
            Liste des Documents (<?php echo count($documents); ?>)
        </h5>
    </div>
    <div class="card-body">
        <?php if (empty($documents)): ?>
            <div class="text-center py-4">
                <i class="fas fa-file-alt fa-3x text-muted mb-3"></i>
                <h5 class="text-muted">Aucun document</h5>
                <p class="text-muted">Cet élève n'a encore aucun document dans son dossier.</p>
                <?php if (checkPermission('students')): ?>
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addDocumentModal">
                        <i class="fas fa-plus me-1"></i>
                        Ajouter le premier document
                    </button>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Type</th>
                            <th>Nom du document</th>
                            <th>Statut</th>
                            <th>Obligatoire</th>
                            <th>Date d'ajout</th>
                            <th>Ajouté par</th>
                            <?php if (checkPermission('students')): ?>
                            <th>Actions</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($documents as $document): ?>
                            <tr>
                                <td>
                                    <span class="badge bg-secondary">
                                        <?php echo htmlspecialchars($types_documents[$document['type_document']] ?? $document['type_document']); ?>
                                    </span>
                                </td>
                                <td>
                                    <strong><?php echo htmlspecialchars($document['nom_document']); ?></strong>
                                    <?php if ($document['description']): ?>
                                        <br><small class="text-muted"><?php echo htmlspecialchars($document['description']); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php
                                    $status_colors = [
                                        'en_attente' => 'warning',
                                        'verifie' => 'success',
                                        'rejete' => 'danger'
                                    ];
                                    $color = $status_colors[$document['statut_verification']] ?? 'secondary';
                                    ?>
                                    <span class="badge bg-<?php echo $color; ?>">
                                        <?php echo ucfirst(str_replace('_', ' ', $document['statut_verification'])); ?>
                                    </span>
                                    <?php if ($document['commentaire_verification']): ?>
                                        <br><small class="text-muted"><?php echo htmlspecialchars($document['commentaire_verification']); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($document['obligatoire']): ?>
                                        <span class="badge bg-info">Obligatoire</span>
                                    <?php else: ?>
                                        <span class="text-muted">Facultatif</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php echo formatDate($document['date_ajout']); ?>
                                    <?php if ($document['date_verification']): ?>
                                        <br><small class="text-muted">Vérifié le <?php echo formatDate($document['date_verification']); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php echo htmlspecialchars($document['ajoute_par_nom'] ?? 'Inconnu'); ?>
                                    <?php if ($document['verifie_par_nom']): ?>
                                        <br><small class="text-muted">Vérifié par <?php echo htmlspecialchars($document['verifie_par_nom']); ?></small>
                                    <?php endif; ?>
                                </td>
                                <?php if (checkPermission('students')): ?>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <button type="button" class="btn btn-outline-primary" 
                                                data-bs-toggle="modal" data-bs-target="#statusModal<?php echo $document['id']; ?>">
                                            <i class="fas fa-check"></i>
                                        </button>
                                        <button type="button" class="btn btn-outline-danger" 
                                                onclick="deleteDocument(<?php echo $document['id']; ?>, '<?php echo htmlspecialchars($document['nom_document']); ?>')">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </td>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php if (checkPermission('students')): ?>
<!-- Modal d'ajout de document -->
<div class="modal fade" id="addDocumentModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="add_document">
                <div class="modal-header">
                    <h5 class="modal-title">Ajouter un Document</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="type_document" class="form-label">Type de document <span class="text-danger">*</span></label>
                        <select class="form-select" id="type_document" name="type_document" required>
                            <option value="">Sélectionner un type...</option>
                            <?php foreach ($types_documents as $value => $label): ?>
                                <option value="<?php echo $value; ?>"><?php echo htmlspecialchars($label); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="nom_document" class="form-label">Nom du document <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="nom_document" name="nom_document" required>
                    </div>
                    <div class="mb-3">
                        <label for="description" class="form-label">Description</label>
                        <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="obligatoire" name="obligatoire">
                        <label class="form-check-label" for="obligatoire">
                            Document obligatoire
                        </label>
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

<!-- Modals de mise à jour de statut -->
<?php foreach ($documents as $document): ?>
<div class="modal fade" id="statusModal<?php echo $document['id']; ?>" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="update_status">
                <input type="hidden" name="document_id" value="<?php echo $document['id']; ?>">
                <div class="modal-header">
                    <h5 class="modal-title">Mettre à jour le statut</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p><strong>Document :</strong> <?php echo htmlspecialchars($document['nom_document']); ?></p>
                    <div class="mb-3">
                        <label for="statut<?php echo $document['id']; ?>" class="form-label">Nouveau statut</label>
                        <select class="form-select" id="statut<?php echo $document['id']; ?>" name="statut" required>
                            <option value="en_attente" <?php echo $document['statut_verification'] === 'en_attente' ? 'selected' : ''; ?>>En attente</option>
                            <option value="verifie" <?php echo $document['statut_verification'] === 'verifie' ? 'selected' : ''; ?>>Vérifié</option>
                            <option value="rejete" <?php echo $document['statut_verification'] === 'rejete' ? 'selected' : ''; ?>>Rejeté</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="commentaire<?php echo $document['id']; ?>" class="form-label">Commentaire</label>
                        <textarea class="form-control" id="commentaire<?php echo $document['id']; ?>" name="commentaire" rows="3"><?php echo htmlspecialchars($document['commentaire_verification'] ?? ''); ?></textarea>
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
<?php endforeach; ?>

<!-- Form de suppression -->
<form id="deleteForm" method="POST" style="display: none;">
    <input type="hidden" name="action" value="delete_document">
    <input type="hidden" name="document_id" id="deleteDocumentId">
</form>

<script>
function deleteDocument(id, nom) {
    if (confirm('Êtes-vous sûr de vouloir supprimer le document "' + nom + '" ?')) {
        document.getElementById('deleteDocumentId').value = id;
        document.getElementById('deleteForm').submit();
    }
}
</script>
<?php endif; ?>

<?php include '../../../includes/footer.php'; ?>
