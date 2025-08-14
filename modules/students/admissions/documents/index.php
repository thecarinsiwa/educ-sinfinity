<?php
/**
 * Module de gestion des documents d'admission
 * Application de gestion scolaire - République Démocratique du Congo
 */

require_once '../../../../config/config.php';
require_once '../../../../config/database.php';
require_once '../../../../includes/functions.php';

// Vérifier l'authentification et les permissions
requireLogin();
if (!checkPermission('students') && !checkPermission('students_view')) {
    showMessage('error', 'Accès refusé à ce module.');
    redirectTo('../index.php');
}

// Traitement des actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && checkPermission('students')) {
    try {
        $action = $_POST['action'] ?? '';
        
        switch ($action) {
            case 'update_document_status':
                $candidature_id = intval($_POST['candidature_id']);
                $document_type = $_POST['document_type'] ?? '';
                $status = $_POST['status'] ?? '';
                $commentaire = trim($_POST['commentaire'] ?? '');
                
                // Types de documents valides
                $valid_documents = [
                    'certificat_naissance', 'bulletin_precedent', 'certificat_medical', 
                    'photo_identite', 'autres_documents'
                ];
                
                if (!in_array($document_type, $valid_documents)) {
                    throw new Exception('Type de document invalide.');
                }
                
                $valid_statuses = ['non_fourni', 'fourni', 'verifie', 'rejete'];
                if (!in_array($status, $valid_statuses)) {
                    throw new Exception('Statut invalide.');
                }
                
                // Mettre à jour le statut du document
                $database->execute(
                    "UPDATE demandes_admission 
                     SET {$document_type} = ?, commentaire_documents = ?, 
                         verifie_par = ?, date_verification = NOW(), updated_at = NOW()
                     WHERE id = ?",
                    [$status, $commentaire, $_SESSION['user_id'], $candidature_id]
                );
                
                showMessage('success', 'Statut du document mis à jour avec succès.');
                break;
                
            case 'bulk_update_documents':
                $candidatures = $_POST['candidatures'] ?? [];
                $bulk_document_type = $_POST['bulk_document_type'] ?? '';
                $bulk_status = $_POST['bulk_status'] ?? '';
                
                if (!in_array($bulk_document_type, $valid_documents)) {
                    throw new Exception('Type de document invalide.');
                }
                
                if (!in_array($bulk_status, $valid_statuses)) {
                    throw new Exception('Statut invalide.');
                }
                
                foreach ($candidatures as $candidature_id) {
                    $database->execute(
                        "UPDATE demandes_admission 
                         SET {$bulk_document_type} = ?, verifie_par = ?, date_verification = NOW(), updated_at = NOW()
                         WHERE id = ?",
                        [$bulk_status, $_SESSION['user_id'], intval($candidature_id)]
                    );
                }
                
                showMessage('success', count($candidatures) . ' candidatures mises à jour.');
                break;
        }
    } catch (Exception $e) {
        showMessage('error', 'Erreur lors de la mise à jour : ' . $e->getMessage());
    }
}

// Paramètres de filtrage
$status_filter = $_GET['status'] ?? '';
$document_filter = $_GET['document'] ?? '';
$search = trim($_GET['search'] ?? '');

// Construction de la requête
$where_conditions = ["1=1"];
$params = [];

if ($status_filter) {
    $where_conditions[] = "da.status = ?";
    $params[] = $status_filter;
}

if ($search) {
    $where_conditions[] = "(da.nom_eleve LIKE ? OR da.prenom_eleve LIKE ? OR da.numero_demande LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

$where_clause = implode(' AND ', $where_conditions);

// Récupérer les candidatures avec leurs documents
try {
    $candidatures = $database->query(
        "SELECT da.*, c.nom as classe_demandee, c.niveau, c.section,
                u.username as verifie_par_nom,
                CASE 
                    WHEN da.certificat_naissance = 'verifie' AND da.bulletin_precedent = 'verifie' 
                         AND da.certificat_medical = 'verifie' AND da.photo_identite = 'verifie' 
                    THEN 'Complet'
                    WHEN da.certificat_naissance = 'rejete' OR da.bulletin_precedent = 'rejete' 
                         OR da.certificat_medical = 'rejete' OR da.photo_identite = 'rejete'
                    THEN 'Rejeté'
                    ELSE 'Incomplet'
                END as statut_documents
         FROM demandes_admission da
         LEFT JOIN classes c ON da.classe_demandee_id = c.id
         LEFT JOIN users u ON da.verifie_par = u.id
         WHERE $where_clause
         ORDER BY da.created_at DESC",
        $params
    )->fetchAll();
} catch (Exception $e) {
    $candidatures = [];
    showMessage('error', 'Erreur lors du chargement : ' . $e->getMessage());
}

// Statistiques des documents
try {
    $stats = $database->query(
        "SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN certificat_naissance = 'verifie' THEN 1 ELSE 0 END) as cert_naissance_ok,
            SUM(CASE WHEN bulletin_precedent = 'verifie' THEN 1 ELSE 0 END) as bulletin_ok,
            SUM(CASE WHEN certificat_medical = 'verifie' THEN 1 ELSE 0 END) as cert_medical_ok,
            SUM(CASE WHEN photo_identite = 'verifie' THEN 1 ELSE 0 END) as photo_ok,
            SUM(CASE WHEN certificat_naissance = 'verifie' AND bulletin_precedent = 'verifie' 
                          AND certificat_medical = 'verifie' AND photo_identite = 'verifie' 
                     THEN 1 ELSE 0 END) as dossiers_complets
         FROM demandes_admission"
    )->fetch();
} catch (Exception $e) {
    $stats = [
        'total' => 0, 'cert_naissance_ok' => 0, 'bulletin_ok' => 0, 
        'cert_medical_ok' => 0, 'photo_ok' => 0, 'dossiers_complets' => 0
    ];
}

$page_title = "Gestion des Documents";
include '../../../../includes/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">
        <i class="fas fa-folder-open me-2"></i>
        Gestion des Documents
    </h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <div class="btn-group me-2">
            <a href="../index.php" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-1"></i>
                Retour aux Admissions
            </a>
        </div>
        <?php if (checkPermission('students')): ?>
            <div class="btn-group me-2">
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#bulkDocumentModal">
                    <i class="fas fa-tasks me-1"></i>
                    Mise à jour en lot
                </button>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Statistiques des documents -->
<div class="row mb-4">
    <div class="col-md-2">
        <div class="card text-center">
            <div class="card-body">
                <i class="fas fa-file-alt fa-2x text-primary mb-2"></i>
                <h6 class="card-title"><?php echo number_format($stats['total']); ?></h6>
                <p class="card-text small text-muted">Total dossiers</p>
            </div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card text-center">
            <div class="card-body">
                <i class="fas fa-certificate fa-2x text-success mb-2"></i>
                <h6 class="card-title"><?php echo number_format($stats['cert_naissance_ok']); ?></h6>
                <p class="card-text small text-muted">Cert. naissance</p>
            </div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card text-center">
            <div class="card-body">
                <i class="fas fa-graduation-cap fa-2x text-info mb-2"></i>
                <h6 class="card-title"><?php echo number_format($stats['bulletin_ok']); ?></h6>
                <p class="card-text small text-muted">Bulletins</p>
            </div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card text-center">
            <div class="card-body">
                <i class="fas fa-heartbeat fa-2x text-warning mb-2"></i>
                <h6 class="card-title"><?php echo number_format($stats['cert_medical_ok']); ?></h6>
                <p class="card-text small text-muted">Cert. médical</p>
            </div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card text-center">
            <div class="card-body">
                <i class="fas fa-camera fa-2x text-secondary mb-2"></i>
                <h6 class="card-title"><?php echo number_format($stats['photo_ok']); ?></h6>
                <p class="card-text small text-muted">Photos</p>
            </div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card text-center">
            <div class="card-body">
                <i class="fas fa-check-circle fa-2x text-success mb-2"></i>
                <h6 class="card-title"><?php echo number_format($stats['dossiers_complets']); ?></h6>
                <p class="card-text small text-muted">Complets</p>
            </div>
        </div>
    </div>
</div>

<!-- Filtres -->
<div class="card mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-md-3">
                <label for="status" class="form-label">Statut candidature</label>
                <select class="form-select" id="status" name="status">
                    <option value="">Tous les statuts</option>
                    <option value="en_attente" <?php echo $status_filter === 'en_attente' ? 'selected' : ''; ?>>En attente</option>
                    <option value="en_cours_traitement" <?php echo $status_filter === 'en_cours_traitement' ? 'selected' : ''; ?>>En cours</option>
                    <option value="acceptee" <?php echo $status_filter === 'acceptee' ? 'selected' : ''; ?>>Acceptée</option>
                </select>
            </div>
            <div class="col-md-3">
                <label for="document" class="form-label">Document</label>
                <select class="form-select" id="document" name="document">
                    <option value="">Tous les documents</option>
                    <option value="certificat_naissance" <?php echo $document_filter === 'certificat_naissance' ? 'selected' : ''; ?>>Certificat de naissance</option>
                    <option value="bulletin_precedent" <?php echo $document_filter === 'bulletin_precedent' ? 'selected' : ''; ?>>Bulletin précédent</option>
                    <option value="certificat_medical" <?php echo $document_filter === 'certificat_medical' ? 'selected' : ''; ?>>Certificat médical</option>
                    <option value="photo_identite" <?php echo $document_filter === 'photo_identite' ? 'selected' : ''; ?>>Photo d'identité</option>
                </select>
            </div>
            <div class="col-md-4">
                <label for="search" class="form-label">Recherche</label>
                <input type="text" class="form-control" id="search" name="search" 
                       value="<?php echo htmlspecialchars($search); ?>" 
                       placeholder="Nom, prénom ou numéro...">
            </div>
            <div class="col-md-2">
                <label class="form-label">&nbsp;</label>
                <button type="submit" class="btn btn-primary d-block w-100">
                    <i class="fas fa-search me-1"></i>
                    Filtrer
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Liste des candidatures et leurs documents -->
<div class="card">
    <div class="card-header">
        <h5 class="mb-0">
            <i class="fas fa-list me-2"></i>
            Dossiers de candidatures (<?php echo count($candidatures); ?>)
        </h5>
    </div>
    <div class="card-body">
        <?php if (empty($candidatures)): ?>
            <div class="text-center py-4">
                <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                <h5 class="text-muted">Aucune candidature trouvée</h5>
                <p class="text-muted">Aucune candidature ne correspond aux critères sélectionnés.</p>
            </div>
        <?php else: ?>
            <form method="POST" id="bulkForm">
                <input type="hidden" name="action" value="bulk_update_documents">

                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead class="table-light">
                            <tr>
                                <?php if (checkPermission('students')): ?>
                                    <th width="40">
                                        <input type="checkbox" id="selectAll" class="form-check-input">
                                    </th>
                                <?php endif; ?>
                                <th>Candidat</th>
                                <th>Statut</th>
                                <th>Cert. Naissance</th>
                                <th>Bulletin</th>
                                <th>Cert. Médical</th>
                                <th>Photo</th>
                                <th>Autres</th>
                                <th>Statut Global</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($candidatures as $candidature): ?>
                                <tr>
                                    <?php if (checkPermission('students')): ?>
                                        <td>
                                            <input type="checkbox" name="candidatures[]"
                                                   value="<?php echo $candidature['id']; ?>"
                                                   class="form-check-input candidature-checkbox">
                                        </td>
                                    <?php endif; ?>
                                    <td>
                                        <div>
                                            <strong><?php echo htmlspecialchars($candidature['nom_eleve'] . ' ' . $candidature['prenom_eleve']); ?></strong>
                                            <br>
                                            <small class="text-muted"><?php echo htmlspecialchars($candidature['numero_demande']); ?></small>
                                        </div>
                                    </td>
                                    <td>
                                        <?php
                                        $status_classes = [
                                            'en_attente' => 'warning',
                                            'acceptee' => 'success',
                                            'refusee' => 'danger',
                                            'en_cours_traitement' => 'info',
                                            'inscrit' => 'primary'
                                        ];
                                        $status_names = [
                                            'en_attente' => 'En attente',
                                            'acceptee' => 'Acceptée',
                                            'refusee' => 'Refusée',
                                            'en_cours_traitement' => 'En cours',
                                            'inscrit' => 'Inscrit'
                                        ];
                                        $status_class = $status_classes[$candidature['status']] ?? 'secondary';
                                        $status_name = $status_names[$candidature['status']] ?? $candidature['status'];
                                        ?>
                                        <span class="badge bg-<?php echo $status_class; ?>">
                                            <?php echo $status_name; ?>
                                        </span>
                                    </td>

                                    <!-- Statuts des documents -->
                                    <?php
                                    $documents = [
                                        'certificat_naissance' => $candidature['certificat_naissance'],
                                        'bulletin_precedent' => $candidature['bulletin_precedent'],
                                        'certificat_medical' => $candidature['certificat_medical'],
                                        'photo_identite' => $candidature['photo_identite'],
                                        'autres_documents' => $candidature['autres_documents']
                                    ];

                                    $doc_classes = [
                                        'non_fourni' => 'secondary',
                                        'fourni' => 'warning',
                                        'verifie' => 'success',
                                        'rejete' => 'danger'
                                    ];

                                    $doc_icons = [
                                        'non_fourni' => 'fas fa-times',
                                        'fourni' => 'fas fa-clock',
                                        'verifie' => 'fas fa-check',
                                        'rejete' => 'fas fa-exclamation-triangle'
                                    ];

                                    foreach (array_slice($documents, 0, 4) as $doc_type => $doc_status):
                                        $doc_class = $doc_classes[$doc_status] ?? 'secondary';
                                        $doc_icon = $doc_icons[$doc_status] ?? 'fas fa-question';
                                    ?>
                                        <td class="text-center">
                                            <i class="<?php echo $doc_icon; ?> text-<?php echo $doc_class; ?>"
                                               title="<?php echo ucfirst(str_replace('_', ' ', $doc_status)); ?>"></i>
                                        </td>
                                    <?php endforeach; ?>

                                    <!-- Autres documents -->
                                    <td class="text-center">
                                        <?php
                                        $autres_status = $documents['autres_documents'];
                                        $autres_class = $doc_classes[$autres_status] ?? 'secondary';
                                        $autres_icon = $doc_icons[$autres_status] ?? 'fas fa-question';
                                        ?>
                                        <i class="<?php echo $autres_icon; ?> text-<?php echo $autres_class; ?>"
                                           title="<?php echo ucfirst(str_replace('_', ' ', $autres_status)); ?>"></i>
                                    </td>

                                    <!-- Statut global -->
                                    <td>
                                        <?php
                                        $global_classes = [
                                            'Complet' => 'success',
                                            'Incomplet' => 'warning',
                                            'Rejeté' => 'danger'
                                        ];
                                        $global_class = $global_classes[$candidature['statut_documents']] ?? 'secondary';
                                        ?>
                                        <span class="badge bg-<?php echo $global_class; ?>">
                                            <?php echo $candidature['statut_documents']; ?>
                                        </span>
                                    </td>

                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <a href="../applications/view.php?id=<?php echo $candidature['id']; ?>"
                                               class="btn btn-outline-info" title="Voir les détails">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <?php if (checkPermission('students')): ?>
                                                <button type="button" class="btn btn-outline-primary"
                                                        onclick="openDocumentModal(<?php echo $candidature['id']; ?>)"
                                                        title="Gérer les documents">
                                                    <i class="fas fa-folder-open"></i>
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </form>
        <?php endif; ?>
    </div>
</div>

<!-- Modal de gestion des documents -->
<div class="modal fade" id="documentModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-folder-open me-2"></i>
                    Gestion des documents
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" id="documentForm">
                <input type="hidden" name="action" value="update_document_status">
                <input type="hidden" name="candidature_id" id="modal_candidature_id">

                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="document_type" class="form-label">
                                    <i class="fas fa-file me-1"></i>
                                    Type de document *
                                </label>
                                <select class="form-select" id="document_type" name="document_type" required>
                                    <option value="">-- Sélectionner --</option>
                                    <option value="certificat_naissance">Certificat de naissance</option>
                                    <option value="bulletin_precedent">Bulletin précédent</option>
                                    <option value="certificat_medical">Certificat médical</option>
                                    <option value="photo_identite">Photo d'identité</option>
                                    <option value="autres_documents">Autres documents</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="status" class="form-label">
                                    <i class="fas fa-flag me-1"></i>
                                    Nouveau statut *
                                </label>
                                <select class="form-select" id="status" name="status" required>
                                    <option value="">-- Sélectionner --</option>
                                    <option value="non_fourni">Non fourni</option>
                                    <option value="fourni">Fourni</option>
                                    <option value="verifie">Vérifié</option>
                                    <option value="rejete">Rejeté</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="commentaire" class="form-label">
                            <i class="fas fa-comment me-1"></i>
                            Commentaire
                        </label>
                        <textarea class="form-control" id="commentaire" name="commentaire" rows="3"
                                  placeholder="Observations sur le document..."></textarea>
                    </div>

                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        <strong>Statuts des documents :</strong>
                        <ul class="mb-0 mt-2">
                            <li><strong>Non fourni :</strong> Document manquant</li>
                            <li><strong>Fourni :</strong> Document reçu, en attente de vérification</li>
                            <li><strong>Vérifié :</strong> Document conforme et validé</li>
                            <li><strong>Rejeté :</strong> Document non conforme ou illisible</li>
                        </ul>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times me-1"></i>
                        Annuler
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-1"></i>
                        Mettre à jour
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal de mise à jour en lot -->
<div class="modal fade" id="bulkDocumentModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-tasks me-2"></i>
                    Mise à jour en lot
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    Sélectionnez d'abord les candidatures dans la liste, puis choisissez le document et le statut à appliquer.
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="bulk_document_type" class="form-label">
                                <i class="fas fa-file me-1"></i>
                                Type de document *
                            </label>
                            <select class="form-select" id="bulk_document_type" name="bulk_document_type" required>
                                <option value="">-- Sélectionner --</option>
                                <option value="certificat_naissance">Certificat de naissance</option>
                                <option value="bulletin_precedent">Bulletin précédent</option>
                                <option value="certificat_medical">Certificat médical</option>
                                <option value="photo_identite">Photo d'identité</option>
                                <option value="autres_documents">Autres documents</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="bulk_status" class="form-label">
                                <i class="fas fa-flag me-1"></i>
                                Statut à appliquer *
                            </label>
                            <select class="form-select" id="bulk_status" name="bulk_status" required>
                                <option value="">-- Sélectionner --</option>
                                <option value="non_fourni">Non fourni</option>
                                <option value="fourni">Fourni</option>
                                <option value="verifie">Vérifié</option>
                                <option value="rejete">Rejeté</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div id="selectedDocumentsCount" class="text-muted">
                    Aucune candidature sélectionnée
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="fas fa-times me-1"></i>
                    Annuler
                </button>
                <button type="button" class="btn btn-primary" onclick="submitBulkDocuments()">
                    <i class="fas fa-check me-1"></i>
                    Appliquer les modifications
                </button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Gestion de la sélection multiple
    const selectAllCheckbox = document.getElementById('selectAll');
    const candidatureCheckboxes = document.querySelectorAll('.candidature-checkbox');

    if (selectAllCheckbox) {
        selectAllCheckbox.addEventListener('change', function() {
            candidatureCheckboxes.forEach(checkbox => {
                checkbox.checked = this.checked;
            });
            updateSelectedCount();
        });
    }

    candidatureCheckboxes.forEach(checkbox => {
        checkbox.addEventListener('change', updateSelectedCount);
    });

    function updateSelectedCount() {
        const selectedCount = document.querySelectorAll('.candidature-checkbox:checked').length;
        const countElement = document.getElementById('selectedDocumentsCount');
        if (countElement) {
            countElement.textContent = selectedCount > 0
                ? `${selectedCount} candidature(s) sélectionnée(s)`
                : 'Aucune candidature sélectionnée';
        }
    }

    // Auto-submit du formulaire de recherche
    let searchTimeout;
    const searchInput = document.getElementById('search');
    if (searchInput) {
        searchInput.addEventListener('input', function() {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                this.form.submit();
            }, 500);
        });
    }
});

function openDocumentModal(candidatureId) {
    document.getElementById('modal_candidature_id').value = candidatureId;

    // Réinitialiser le formulaire
    document.getElementById('document_type').value = '';
    document.getElementById('status').value = '';
    document.getElementById('commentaire').value = '';

    new bootstrap.Modal(document.getElementById('documentModal')).show();
}

function submitBulkDocuments() {
    const selectedCheckboxes = document.querySelectorAll('.candidature-checkbox:checked');
    const documentType = document.getElementById('bulk_document_type').value;
    const status = document.getElementById('bulk_status').value;

    if (selectedCheckboxes.length === 0) {
        alert('Veuillez sélectionner au moins une candidature.');
        return;
    }

    if (!documentType || !status) {
        alert('Veuillez sélectionner le type de document et le statut.');
        return;
    }

    const documentNames = {
        'certificat_naissance': 'Certificat de naissance',
        'bulletin_precedent': 'Bulletin précédent',
        'certificat_medical': 'Certificat médical',
        'photo_identite': 'Photo d\'identité',
        'autres_documents': 'Autres documents'
    };

    const statusNames = {
        'non_fourni': 'Non fourni',
        'fourni': 'Fourni',
        'verifie': 'Vérifié',
        'rejete': 'Rejeté'
    };

    const documentName = documentNames[documentType] || documentType;
    const statusName = statusNames[status] || status;

    if (confirm(`Êtes-vous sûr de vouloir marquer "${documentName}" comme "${statusName}" pour ${selectedCheckboxes.length} candidature(s) ?`)) {
        // Créer un formulaire dynamique
        const form = document.createElement('form');
        form.method = 'POST';
        form.style.display = 'none';

        // Action
        const actionInput = document.createElement('input');
        actionInput.type = 'hidden';
        actionInput.name = 'action';
        actionInput.value = 'bulk_update_documents';
        form.appendChild(actionInput);

        // Type de document
        const docTypeInput = document.createElement('input');
        docTypeInput.type = 'hidden';
        docTypeInput.name = 'bulk_document_type';
        docTypeInput.value = documentType;
        form.appendChild(docTypeInput);

        // Statut
        const statusInput = document.createElement('input');
        statusInput.type = 'hidden';
        statusInput.name = 'bulk_status';
        statusInput.value = status;
        form.appendChild(statusInput);

        // Candidatures sélectionnées
        selectedCheckboxes.forEach(checkbox => {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'candidatures[]';
            input.value = checkbox.value;
            form.appendChild(input);
        });

        document.body.appendChild(form);
        form.submit();
    }
}
</script>

<?php include '../../../../includes/footer.php'; ?>
