<?php
/**
 * Traitement individuel d'un transfert d'élève
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

$page_title = "Traitement du transfert";

// Récupérer l'ID du transfert
$transfer_id = $_GET['id'] ?? null;

if (!$transfer_id) {
    showMessage('error', 'ID de transfert manquant');
    redirectTo('bulk-process.php');
}

// Traitement des actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $action = $_POST['action'] ?? '';
        $commentaire = $_POST['commentaire'] ?? '';
        
        $database->beginTransaction();
        
        switch ($action) {
            case 'approve':
                // Approuver le transfert
                $database->query(
                    "UPDATE transfers SET statut = 'approuve', approuve_par = ?, date_approbation = NOW() WHERE id = ? AND statut = 'en_attente'",
                    [$_SESSION['user_id'], $transfer_id]
                );
                
                // Historique
                $database->query(
                    "INSERT INTO transfer_history (transfer_id, action, ancien_statut, nouveau_statut, commentaire, user_id) VALUES (?, 'approbation', 'en_attente', 'approuve', ?, ?)",
                    [$transfer_id, $commentaire ?: 'Transfert approuvé', $_SESSION['user_id']]
                );
                
                $message = 'Transfert approuvé avec succès !';
                $message_type = 'success';
                break;
                
            case 'reject':
                // Rejeter le transfert
                $database->query(
                    "UPDATE transfers SET statut = 'rejete', approuve_par = ?, date_approbation = NOW() WHERE id = ? AND statut = 'en_attente'",
                    [$_SESSION['user_id'], $transfer_id]
                );
                
                // Historique
                $database->query(
                    "INSERT INTO transfer_history (transfer_id, action, ancien_statut, nouveau_statut, commentaire, user_id) VALUES (?, 'rejet', 'en_attente', 'rejete', ?, ?)",
                    [$transfer_id, $commentaire ?: 'Transfert rejeté', $_SESSION['user_id']]
                );
                
                $message = 'Transfert rejeté';
                $message_type = 'warning';
                break;
                
            case 'complete':
                // Compléter le transfert
                $transfer_info = $database->query("SELECT * FROM transfers WHERE id = ?", [$transfer_id])->fetch();
                
                if ($transfer_info['statut'] !== 'approuve') {
                    throw new Exception('Le transfert doit être approuvé avant d\'être complété');
                }
                
                $database->query(
                    "UPDATE transfers SET statut = 'complete', date_effective = COALESCE(date_effective, CURDATE()) WHERE id = ?",
                    [$transfer_id]
                );
                
                // Mettre à jour le statut de l'élève si c'est une sortie
                if (in_array($transfer_info['type_mouvement'], ['transfert_sortant', 'sortie_definitive'])) {
                    $database->query(
                        "UPDATE inscriptions SET status = 'transfere' WHERE eleve_id = ? AND status = 'inscrit'",
                        [$transfer_info['eleve_id']]
                    );
                    
                    // Mettre à jour le statut de l'élève
                    $new_status = $transfer_info['type_mouvement'] === 'sortie_definitive' ? 'diplome' : 'transfere';
                    $database->query(
                        "UPDATE eleves SET status = ? WHERE id = ?",
                        [$new_status, $transfer_info['eleve_id']]
                    );
                }
                
                // Générer automatiquement un certificat si pas encore fait
                if (!$transfer_info['certificat_genere']) {
                    $numero_certificat = 'CERT' . date('Y') . str_pad($transfer_id, 6, '0', STR_PAD_LEFT);
                    $database->query(
                        "UPDATE transfers SET certificat_genere = 1, numero_certificat = ? WHERE id = ?",
                        [$numero_certificat, $transfer_id]
                    );
                }
                
                // Historique
                $database->query(
                    "INSERT INTO transfer_history (transfer_id, action, ancien_statut, nouveau_statut, commentaire, user_id) VALUES (?, 'completion', 'approuve', 'complete', ?, ?)",
                    [$transfer_id, $commentaire ?: 'Transfert complété', $_SESSION['user_id']]
                );
                
                $message = 'Transfert complété avec succès ! Certificat généré automatiquement.';
                $message_type = 'success';
                break;
                
            case 'reopen':
                // Rouvrir le transfert (remettre en attente)
                $database->query(
                    "UPDATE transfers SET statut = 'en_attente', approuve_par = NULL, date_approbation = NULL WHERE id = ?",
                    [$transfer_id]
                );
                
                // Historique
                $database->query(
                    "INSERT INTO transfer_history (transfer_id, action, ancien_statut, nouveau_statut, commentaire, user_id) VALUES (?, 'modification', ?, 'en_attente', ?, ?)",
                    [$transfer_id, $_POST['current_status'], $commentaire ?: 'Transfert rouvert', $_SESSION['user_id']]
                );
                
                $message = 'Transfert remis en attente';
                $message_type = 'info';
                break;
                
            case 'update_documents':
                // Mettre à jour les documents
                $documents = $_POST['documents'] ?? [];
                
                foreach ($documents as $doc_id => $status) {
                    $database->query(
                        "UPDATE transfer_documents SET fourni = ? WHERE id = ? AND transfer_id = ?",
                        [$status === 'provided' ? 1 : 0, $doc_id, $transfer_id]
                    );
                }
                
                // Historique
                $database->query(
                    "INSERT INTO transfer_history (transfer_id, action, ancien_statut, nouveau_statut, commentaire, user_id) VALUES (?, 'modification', ?, ?, 'Documents mis à jour', ?)",
                    [$transfer_id, $_POST['current_status'], $_POST['current_status'], $_SESSION['user_id']]
                );
                
                $message = 'Documents mis à jour avec succès';
                $message_type = 'success';
                break;
                
            case 'update_fees':
                // Mettre à jour les frais
                $fees = $_POST['fees'] ?? [];
                
                foreach ($fees as $fee_id => $data) {
                    $paye = isset($data['paid']) ? 1 : 0;
                    $date_paiement = $paye ? ($data['date_paiement'] ?: date('Y-m-d')) : null;
                    $mode_paiement = $data['mode_paiement'] ?? 'especes';
                    
                    $database->query(
                        "UPDATE transfer_fees SET paye = ?, date_paiement = ?, mode_paiement = ? WHERE id = ? AND transfer_id = ?",
                        [$paye, $date_paiement, $mode_paiement, $fee_id, $transfer_id]
                    );
                }
                
                // Historique
                $database->query(
                    "INSERT INTO transfer_history (transfer_id, action, ancien_statut, nouveau_statut, commentaire, user_id) VALUES (?, 'modification', ?, ?, 'Frais mis à jour', ?)",
                    [$transfer_id, $_POST['current_status'], $_POST['current_status'], $_SESSION['user_id']]
                );
                
                $message = 'Frais mis à jour avec succès';
                $message_type = 'success';
                break;
                
            default:
                throw new Exception('Action non reconnue');
        }
        
        $database->commit();
        
        // Logger l'action
        logUserAction('process_transfer', 'transfers', "Action '$action' sur le transfert ID: $transfer_id", $transfer_id);
        
        showMessage($message_type, $message);
        
        // Rediriger vers la page de visualisation après traitement
        if (in_array($action, ['approve', 'reject', 'complete', 'reopen'])) {
            redirectTo("view.php?id=$transfer_id");
        }
        
    } catch (Exception $e) {
        $database->rollBack();
        showMessage('error', $e->getMessage());
    }
}

// Récupérer les informations complètes du transfert
$transfer = $database->query(
    "SELECT t.*, e.numero_matricule, e.nom, e.prenom, e.date_naissance, e.lieu_naissance, e.sexe,
            e.adresse, e.telephone_parent, e.email_parent, e.nom_pere, e.nom_mere, e.profession_pere, e.profession_mere,
            c_orig.nom as classe_origine_nom, c_orig.niveau as classe_origine_niveau,
            c_dest.nom as classe_destination_nom, c_dest.niveau as classe_destination_niveau,
            u_traite.nom as traite_par_nom, u_traite.prenom as traite_par_prenom,
            u_approuve.nom as approuve_par_nom, u_approuve.prenom as approuve_par_prenom,
            a.annee as annee_nom
     FROM transfers t
     JOIN eleves e ON t.eleve_id = e.id
     LEFT JOIN classes c_orig ON t.classe_origine_id = c_orig.id
     LEFT JOIN classes c_dest ON t.classe_destination_id = c_dest.id
     LEFT JOIN users u_traite ON t.traite_par = u_traite.id
     LEFT JOIN users u_approuve ON t.approuve_par = u_approuve.id
     LEFT JOIN inscriptions i ON e.id = i.eleve_id AND i.status = 'inscrit'
     LEFT JOIN annees_scolaires a ON i.annee_scolaire_id = a.id
     WHERE t.id = ?",
    [$transfer_id]
)->fetch();

if (!$transfer) {
    showMessage('error', 'Transfert non trouvé');
    redirectTo('bulk-process.php');
}

// Récupérer les documents
$documents = $database->query(
    "SELECT * FROM transfer_documents WHERE transfer_id = ? ORDER BY obligatoire DESC, nom_document",
    [$transfer_id]
)->fetchAll();

// Récupérer les frais
$fees = $database->query(
    "SELECT * FROM transfer_fees WHERE transfer_id = ? ORDER BY type_frais",
    [$transfer_id]
)->fetchAll();

// Récupérer l'historique récent
$recent_history = $database->query(
    "SELECT th.*, u.nom as user_nom, u.prenom as user_prenom
     FROM transfer_history th
     LEFT JOIN users u ON th.user_id = u.id
     WHERE th.transfer_id = ?
     ORDER BY th.created_at DESC
     LIMIT 5",
    [$transfer_id]
)->fetchAll();

include '../../../includes/header.php';
?>

<!-- Styles CSS modernes -->
<style>
.process-header {
    background: linear-gradient(135deg, #6610f2 0%, #e83e8c 100%);
    color: white;
    padding: 2rem 0;
    margin: -20px -15px 30px -15px;
    border-radius: 0 0 20px 20px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.1);
}

.process-header h1 {
    font-weight: 300;
    font-size: 2.5rem;
    margin-bottom: 0.5rem;
}

.process-header .subtitle {
    opacity: 0.9;
    font-size: 1.1rem;
}

.process-card {
    background: white;
    border-radius: 15px;
    padding: 2rem;
    box-shadow: 0 5px 15px rgba(0,0,0,0.08);
    border: none;
    margin-bottom: 2rem;
    transition: all 0.3s ease;
}

.process-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(0,0,0,0.12);
}

.student-header {
    background: linear-gradient(135deg, #e3f2fd 0%, #f3e5f5 100%);
    border-radius: 15px;
    padding: 2rem;
    margin-bottom: 2rem;
    border-left: 5px solid #6610f2;
    position: relative;
    overflow: hidden;
}

.student-header::before {
    content: '';
    position: absolute;
    top: -50%;
    right: -50%;
    width: 100%;
    height: 200%;
    background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
    transform: rotate(45deg);
}

.status-badge {
    padding: 0.75rem 1.5rem;
    border-radius: 25px;
    font-weight: 600;
    font-size: 0.9rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.status-en_attente { background: #fff3cd; color: #856404; border: 2px solid #ffeaa7; }
.status-approuve { background: #d1ecf1; color: #0c5460; border: 2px solid #74b9ff; }
.status-rejete { background: #f8d7da; color: #721c24; border: 2px solid #fd79a8; }
.status-complete { background: #d4edda; color: #155724; border: 2px solid #00b894; }

.action-section {
    background: #f8f9fa;
    border-radius: 15px;
    padding: 2rem;
    margin-bottom: 2rem;
    border-left: 4px solid #6610f2;
}

.action-section h5 {
    color: #6610f2;
    font-weight: 600;
    margin-bottom: 1.5rem;
    display: flex;
    align-items: center;
}

.action-section h5 i {
    margin-right: 0.5rem;
    font-size: 1.2rem;
}

.btn-process {
    border-radius: 25px;
    padding: 0.75rem 2rem;
    font-weight: 600;
    transition: all 0.3s ease;
    border: none;
    box-shadow: 0 3px 15px rgba(0,0,0,0.1);
    margin: 0.25rem;
}

.btn-process:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 25px rgba(0,0,0,0.2);
}

.btn-success.btn-process {
    background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
}

.btn-danger.btn-process {
    background: linear-gradient(135deg, #dc3545 0%, #fd7e14 100%);
}

.btn-primary.btn-process {
    background: linear-gradient(135deg, #007bff 0%, #6610f2 100%);
}

.btn-warning.btn-process {
    background: linear-gradient(135deg, #ffc107 0%, #fd7e14 100%);
}

.btn-info.btn-process {
    background: linear-gradient(135deg, #17a2b8 0%, #6f42c1 100%);
}

.btn-secondary.btn-process {
    background: linear-gradient(135deg, #6c757d 0%, #495057 100%);
}

.quick-actions {
    display: flex;
    gap: 1rem;
    flex-wrap: wrap;
    justify-content: center;
    margin-bottom: 2rem;
}

.document-list, .fee-list {
    background: white;
    border-radius: 10px;
    padding: 1rem;
    margin-bottom: 1rem;
}

.document-item, .fee-item {
    background: #f8f9fa;
    border-radius: 8px;
    padding: 1rem;
    margin-bottom: 0.5rem;
    border-left: 4px solid #e9ecef;
    transition: all 0.3s ease;
}

.document-item:hover, .fee-item:hover {
    border-left-color: #6610f2;
    box-shadow: 0 2px 10px rgba(0,0,0,0.05);
}

.document-item.provided {
    border-left-color: #28a745;
    background: #f8fff9;
}

.document-item.required {
    border-left-color: #dc3545;
    background: #fff8f8;
}

.fee-item.paid {
    border-left-color: #28a745;
    background: #f8fff9;
}

.fee-item.unpaid {
    border-left-color: #ffc107;
    background: #fffbf0;
}

.history-item {
    background: white;
    border-radius: 10px;
    padding: 1rem;
    margin-bottom: 0.5rem;
    border-left: 4px solid #6610f2;
    box-shadow: 0 2px 5px rgba(0,0,0,0.05);
}

.progress-indicator {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 2rem;
    position: relative;
}

.progress-indicator::before {
    content: '';
    position: absolute;
    top: 50%;
    left: 0;
    right: 0;
    height: 2px;
    background: #e9ecef;
    z-index: 1;
}

.progress-step {
    background: white;
    border: 3px solid #e9ecef;
    border-radius: 50%;
    width: 50px;
    height: 50px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
    z-index: 2;
    position: relative;
}

.progress-step.active {
    border-color: #6610f2;
    background: #6610f2;
    color: white;
}

.progress-step.completed {
    border-color: #28a745;
    background: #28a745;
    color: white;
}

@keyframes fadeInUp {
    from {
        opacity: 0;
        transform: translateY(30px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.animate-fade-in {
    animation: fadeInUp 0.6s ease-out;
}

.animate-delay-1 { animation-delay: 0.1s; }
.animate-delay-2 { animation-delay: 0.2s; }
.animate-delay-3 { animation-delay: 0.3s; }
.animate-delay-4 { animation-delay: 0.4s; }

@media (max-width: 768px) {
    .process-header {
        margin: -20px -15px 20px -15px;
        padding: 1.5rem 0;
    }

    .process-header h1 {
        font-size: 2rem;
    }

    .process-card, .student-header {
        padding: 1rem;
    }

    .quick-actions {
        flex-direction: column;
    }

    .btn-process {
        width: 100%;
        margin-bottom: 0.5rem;
    }

    .progress-indicator {
        flex-direction: column;
        gap: 1rem;
    }

    .progress-indicator::before {
        display: none;
    }
}
</style>

<!-- En-tête moderne -->
<div class="process-header">
    <div class="container-fluid">
        <div class="row align-items-center">
            <div class="col-md-8">
                <h1 class="animate-fade-in">
                    <i class="fas fa-cogs me-3"></i>
                    Traitement du transfert
                </h1>
                <p class="subtitle animate-fade-in animate-delay-1">
                    Transfert N° <?php echo str_pad($transfer_id, 6, '0', STR_PAD_LEFT); ?> -
                    <?php echo htmlspecialchars($transfer['nom'] . ' ' . $transfer['prenom']); ?>
                </p>
            </div>
            <div class="col-md-4 text-end">
                <div class="animate-fade-in animate-delay-2">
                    <a href="view.php?id=<?php echo $transfer_id; ?>" class="btn btn-light btn-process">
                        <i class="fas fa-eye me-2"></i>
                        Voir détails
                    </a>
                    <a href="bulk-process.php" class="btn btn-secondary btn-process">
                        <i class="fas fa-arrow-left me-2"></i>
                        Retour
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Résumé de l'élève et du transfert -->
<div class="student-header animate-fade-in animate-delay-1">
    <div class="row align-items-center">
        <div class="col-md-2 text-center">
            <div class="student-avatar">
                <i class="fas fa-user-graduate fa-4x text-primary"></i>
            </div>
        </div>
        <div class="col-md-7">
            <h4 class="mb-2">
                <?php echo htmlspecialchars($transfer['nom'] . ' ' . $transfer['prenom']); ?>
            </h4>
            <div class="row">
                <div class="col-md-6">
                    <p class="mb-1"><strong>Matricule:</strong> <?php echo htmlspecialchars($transfer['numero_matricule']); ?></p>
                    <p class="mb-1"><strong>Date de naissance:</strong> <?php echo date('d/m/Y', strtotime($transfer['date_naissance'])); ?></p>
                    <p class="mb-1"><strong>Type:</strong>
                        <?php
                        $type_labels = [
                            'transfert_entrant' => '<i class="fas fa-arrow-right text-success"></i> Transfert entrant',
                            'transfert_sortant' => '<i class="fas fa-arrow-left text-warning"></i> Transfert sortant',
                            'sortie_definitive' => '<i class="fas fa-graduation-cap text-info"></i> Sortie définitive'
                        ];
                        echo $type_labels[$transfer['type_mouvement']] ?? $transfer['type_mouvement'];
                        ?>
                    </p>
                </div>
                <div class="col-md-6">
                    <p class="mb-1"><strong>Date de demande:</strong> <?php echo date('d/m/Y', strtotime($transfer['date_demande'])); ?></p>
                    <?php if ($transfer['date_effective']): ?>
                        <p class="mb-1"><strong>Date effective:</strong> <?php echo date('d/m/Y', strtotime($transfer['date_effective'])); ?></p>
                    <?php endif; ?>
                    <p class="mb-1"><strong>Motif:</strong> <?php echo htmlspecialchars(substr($transfer['motif'], 0, 50) . (strlen($transfer['motif']) > 50 ? '...' : '')); ?></p>
                </div>
            </div>
        </div>
        <div class="col-md-3 text-center">
            <div class="status-badge status-<?php echo $transfer['statut']; ?>">
                <?php
                $status_icons = [
                    'en_attente' => 'fas fa-clock',
                    'approuve' => 'fas fa-check',
                    'rejete' => 'fas fa-times',
                    'complete' => 'fas fa-check-circle'
                ];
                $status_labels = [
                    'en_attente' => 'En attente',
                    'approuve' => 'Approuvé',
                    'rejete' => 'Rejeté',
                    'complete' => 'Complété'
                ];
                ?>
                <i class="<?php echo $status_icons[$transfer['statut']] ?? 'fas fa-question'; ?> me-2"></i>
                <?php echo $status_labels[$transfer['statut']] ?? ucfirst($transfer['statut']); ?>
            </div>
        </div>
    </div>
</div>

<!-- Indicateur de progression -->
<div class="process-card animate-fade-in animate-delay-2">
    <h5 class="mb-3">
        <i class="fas fa-tasks me-2"></i>
        Progression du transfert
    </h5>

    <div class="progress-indicator">
        <div class="progress-step <?php echo in_array($transfer['statut'], ['en_attente', 'approuve', 'rejete', 'complete']) ? 'completed' : ''; ?>">
            <i class="fas fa-file-alt"></i>
        </div>
        <div class="progress-step <?php echo in_array($transfer['statut'], ['approuve', 'complete']) ? 'completed' : ($transfer['statut'] === 'en_attente' ? 'active' : ''); ?>">
            <i class="fas fa-check"></i>
        </div>
        <div class="progress-step <?php echo $transfer['statut'] === 'complete' ? 'completed' : ($transfer['statut'] === 'approuve' ? 'active' : ''); ?>">
            <i class="fas fa-check-circle"></i>
        </div>
        <div class="progress-step <?php echo $transfer['certificat_genere'] ? 'completed' : ''; ?>">
            <i class="fas fa-certificate"></i>
        </div>
    </div>

    <div class="row text-center">
        <div class="col-3">
            <small class="text-muted">Demande</small>
        </div>
        <div class="col-3">
            <small class="text-muted">Approbation</small>
        </div>
        <div class="col-3">
            <small class="text-muted">Finalisation</small>
        </div>
        <div class="col-3">
            <small class="text-muted">Certificat</small>
        </div>
    </div>
</div>

<!-- Actions rapides -->
<?php if ($transfer['statut'] !== 'complete'): ?>
<div class="process-card animate-fade-in animate-delay-3">
    <div class="action-section">
        <h5><i class="fas fa-bolt"></i>Actions rapides</h5>

        <div class="quick-actions">
            <?php if ($transfer['statut'] === 'en_attente'): ?>
                <button type="button" class="btn btn-success btn-process" data-bs-toggle="modal" data-bs-target="#approveModal">
                    <i class="fas fa-check me-2"></i>
                    Approuver
                </button>
                <button type="button" class="btn btn-danger btn-process" data-bs-toggle="modal" data-bs-target="#rejectModal">
                    <i class="fas fa-times me-2"></i>
                    Rejeter
                </button>
            <?php elseif ($transfer['statut'] === 'approuve'): ?>
                <button type="button" class="btn btn-primary btn-process" data-bs-toggle="modal" data-bs-target="#completeModal">
                    <i class="fas fa-check-circle me-2"></i>
                    Finaliser
                </button>
            <?php endif; ?>

            <?php if (in_array($transfer['statut'], ['approuve', 'rejete'])): ?>
                <button type="button" class="btn btn-warning btn-process" data-bs-toggle="modal" data-bs-target="#reopenModal">
                    <i class="fas fa-undo me-2"></i>
                    Rouvrir
                </button>
            <?php endif; ?>

            <button type="button" class="btn btn-info btn-process" data-bs-toggle="modal" data-bs-target="#documentsModal">
                <i class="fas fa-file-alt me-2"></i>
                Gérer documents
            </button>

            <?php if (!empty($fees)): ?>
                <button type="button" class="btn btn-secondary btn-process" data-bs-toggle="modal" data-bs-target="#feesModal">
                    <i class="fas fa-money-bill me-2"></i>
                    Gérer frais
                </button>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Gestion des documents -->
<div class="row">
    <div class="col-lg-6">
        <div class="process-card animate-fade-in animate-delay-3">
            <h5 class="mb-3">
                <i class="fas fa-file-alt me-2"></i>
                Documents requis
                <span class="badge bg-secondary ms-2"><?php echo count($documents); ?></span>
            </h5>

            <div class="document-list">
                <?php if (!empty($documents)): ?>
                    <?php foreach ($documents as $doc): ?>
                        <div class="document-item <?php echo $doc['fourni'] ? 'provided' : ($doc['obligatoire'] ? 'required' : ''); ?>">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <strong><?php echo htmlspecialchars($doc['nom_document']); ?></strong>
                                    <?php if ($doc['obligatoire']): ?>
                                        <span class="badge bg-danger ms-1">Obligatoire</span>
                                    <?php endif; ?>
                                    <br>
                                    <small class="text-muted"><?php echo ucfirst(str_replace('_', ' ', $doc['type_document'])); ?></small>
                                </div>
                                <div>
                                    <?php if ($doc['fourni']): ?>
                                        <i class="fas fa-check-circle text-success fa-lg"></i>
                                        <br><small class="text-success">Fourni</small>
                                    <?php else: ?>
                                        <i class="fas fa-times-circle text-danger fa-lg"></i>
                                        <br><small class="text-danger">Manquant</small>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p class="text-muted text-center">Aucun document requis</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="process-card animate-fade-in animate-delay-4">
            <h5 class="mb-3">
                <i class="fas fa-money-bill me-2"></i>
                Frais associés
                <span class="badge bg-secondary ms-2"><?php echo count($fees); ?></span>
            </h5>

            <div class="fee-list">
                <?php if (!empty($fees)): ?>
                    <?php
                    $total_frais = 0;
                    $total_payes = 0;
                    foreach ($fees as $fee):
                        $total_frais += $fee['montant'];
                        if ($fee['paye']) $total_payes += $fee['montant'];
                    ?>
                        <div class="fee-item <?php echo $fee['paye'] ? 'paid' : 'unpaid'; ?>">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <strong><?php echo htmlspecialchars($fee['libelle']); ?></strong>
                                    <br>
                                    <small class="text-muted"><?php echo ucfirst(str_replace('_', ' ', $fee['type_frais'])); ?></small>
                                </div>
                                <div class="text-end">
                                    <div class="fw-bold"><?php echo number_format($fee['montant'], 0, ',', ' '); ?> FC</div>
                                    <?php if ($fee['paye']): ?>
                                        <small class="text-success">
                                            <i class="fas fa-check me-1"></i>Payé
                                            <?php if ($fee['date_paiement']): ?>
                                                <br><?php echo date('d/m/Y', strtotime($fee['date_paiement'])); ?>
                                            <?php endif; ?>
                                        </small>
                                    <?php else: ?>
                                        <small class="text-warning">
                                            <i class="fas fa-clock me-1"></i>En attente
                                        </small>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>

                    <!-- Résumé des frais -->
                    <div class="mt-3 p-3 bg-light rounded">
                        <div class="d-flex justify-content-between">
                            <strong>Total frais:</strong>
                            <strong><?php echo number_format($total_frais, 0, ',', ' '); ?> FC</strong>
                        </div>
                        <div class="d-flex justify-content-between text-success">
                            <span>Total payé:</span>
                            <span><?php echo number_format($total_payes, 0, ',', ' '); ?> FC</span>
                        </div>
                        <?php if ($total_frais > $total_payes): ?>
                            <div class="d-flex justify-content-between text-danger">
                                <span>Reste à payer:</span>
                                <span><?php echo number_format($total_frais - $total_payes, 0, ',', ' '); ?> FC</span>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <p class="text-muted text-center">Aucun frais associé</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Historique récent -->
<div class="process-card animate-fade-in animate-delay-4">
    <h5 class="mb-3">
        <i class="fas fa-history me-2"></i>
        Historique récent
        <span class="badge bg-secondary ms-2"><?php echo count($recent_history); ?></span>
    </h5>

    <?php if (!empty($recent_history)): ?>
        <?php foreach ($recent_history as $item): ?>
            <div class="history-item">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <h6 class="mb-1">
                            <?php
                            $action_labels = [
                                'creation' => 'Création du transfert',
                                'modification' => 'Modification',
                                'approbation' => 'Approbation',
                                'rejet' => 'Rejet',
                                'completion' => 'Finalisation'
                            ];
                            echo $action_labels[$item['action']] ?? ucfirst($item['action']);
                            ?>
                        </h6>
                        <?php if ($item['commentaire']): ?>
                            <p class="mb-2 text-muted"><?php echo htmlspecialchars($item['commentaire']); ?></p>
                        <?php endif; ?>
                        <?php if ($item['ancien_statut'] && $item['nouveau_statut'] && $item['ancien_statut'] !== $item['nouveau_statut']): ?>
                            <small class="text-info">
                                <i class="fas fa-arrow-right me-1"></i>
                                <?php echo ucfirst(str_replace('_', ' ', $item['ancien_statut'])); ?> →
                                <?php echo ucfirst(str_replace('_', ' ', $item['nouveau_statut'])); ?>
                            </small>
                        <?php endif; ?>
                    </div>
                    <div class="text-end">
                        <small class="text-muted">
                            <?php echo date('d/m/Y à H:i', strtotime($item['created_at'])); ?>
                            <?php if ($item['user_nom']): ?>
                                <br>par <?php echo htmlspecialchars($item['user_nom'] . ' ' . $item['user_prenom']); ?>
                            <?php endif; ?>
                        </small>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>

        <div class="text-center mt-3">
            <a href="view.php?id=<?php echo $transfer_id; ?>#historique" class="btn btn-outline-primary btn-sm">
                <i class="fas fa-history me-2"></i>
                Voir l'historique complet
            </a>
        </div>
    <?php else: ?>
        <p class="text-muted text-center">Aucun historique disponible</p>
    <?php endif; ?>
</div>

<!-- Modales pour les actions -->

<!-- Modal d'approbation -->
<div class="modal fade" id="approveModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="approve">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-check text-success me-2"></i>
                        Approuver le transfert
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        Cette action approuvera le transfert et permettra sa finalisation.
                    </div>
                    <div class="mb-3">
                        <label for="approve_comment" class="form-label">Commentaire (optionnel)</label>
                        <textarea class="form-control" id="approve_comment" name="commentaire" rows="3"
                                  placeholder="Ajouter un commentaire d'approbation..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-check me-2"></i>
                        Approuver
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal de rejet -->
<div class="modal fade" id="rejectModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="reject">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-times text-danger me-2"></i>
                        Rejeter le transfert
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        Cette action rejettera définitivement le transfert.
                    </div>
                    <div class="mb-3">
                        <label for="reject_comment" class="form-label">Motif du rejet <span class="text-danger">*</span></label>
                        <textarea class="form-control" id="reject_comment" name="commentaire" rows="3"
                                  placeholder="Expliquer le motif du rejet..." required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-danger">
                        <i class="fas fa-times me-2"></i>
                        Rejeter
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal de finalisation -->
<div class="modal fade" id="completeModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="complete">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-check-circle text-primary me-2"></i>
                        Finaliser le transfert
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle me-2"></i>
                        Cette action finalisera le transfert et générera automatiquement le certificat.
                    </div>
                    <div class="mb-3">
                        <label for="complete_comment" class="form-label">Commentaire (optionnel)</label>
                        <textarea class="form-control" id="complete_comment" name="commentaire" rows="3"
                                  placeholder="Ajouter un commentaire de finalisation..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-check-circle me-2"></i>
                        Finaliser
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal de réouverture -->
<div class="modal fade" id="reopenModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="reopen">
                <input type="hidden" name="current_status" value="<?php echo $transfer['statut']; ?>">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-undo text-warning me-2"></i>
                        Rouvrir le transfert
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        Cette action remettra le transfert en attente d'approbation.
                    </div>
                    <div class="mb-3">
                        <label for="reopen_comment" class="form-label">Motif de la réouverture <span class="text-danger">*</span></label>
                        <textarea class="form-control" id="reopen_comment" name="commentaire" rows="3"
                                  placeholder="Expliquer pourquoi rouvrir ce transfert..." required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-warning">
                        <i class="fas fa-undo me-2"></i>
                        Rouvrir
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal de gestion des documents -->
<div class="modal fade" id="documentsModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="update_documents">
                <input type="hidden" name="current_status" value="<?php echo $transfer['statut']; ?>">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-file-alt text-info me-2"></i>
                        Gestion des documents
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <?php if (!empty($documents)): ?>
                        <div class="row">
                            <?php foreach ($documents as $doc): ?>
                                <div class="col-md-6 mb-3">
                                    <div class="card">
                                        <div class="card-body">
                                            <h6 class="card-title">
                                                <?php echo htmlspecialchars($doc['nom_document']); ?>
                                                <?php if ($doc['obligatoire']): ?>
                                                    <span class="badge bg-danger ms-1">Obligatoire</span>
                                                <?php endif; ?>
                                            </h6>
                                            <p class="card-text">
                                                <small class="text-muted"><?php echo ucfirst(str_replace('_', ' ', $doc['type_document'])); ?></small>
                                            </p>
                                            <div class="form-check form-switch">
                                                <input class="form-check-input" type="checkbox"
                                                       name="documents[<?php echo $doc['id']; ?>]"
                                                       value="provided"
                                                       id="doc_<?php echo $doc['id']; ?>"
                                                       <?php echo $doc['fourni'] ? 'checked' : ''; ?>>
                                                <label class="form-check-label" for="doc_<?php echo $doc['id']; ?>">
                                                    Document fourni
                                                </label>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p class="text-muted text-center">Aucun document à gérer</p>
                    <?php endif; ?>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-info">
                        <i class="fas fa-save me-2"></i>
                        Mettre à jour
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal de gestion des frais -->
<div class="modal fade" id="feesModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="update_fees">
                <input type="hidden" name="current_status" value="<?php echo $transfer['statut']; ?>">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-money-bill text-success me-2"></i>
                        Gestion des frais
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <?php if (!empty($fees)): ?>
                        <div class="row">
                            <?php foreach ($fees as $fee): ?>
                                <div class="col-md-6 mb-3">
                                    <div class="card">
                                        <div class="card-body">
                                            <h6 class="card-title">
                                                <?php echo htmlspecialchars($fee['libelle']); ?>
                                            </h6>
                                            <p class="card-text">
                                                <strong><?php echo number_format($fee['montant'], 0, ',', ' '); ?> FC</strong><br>
                                                <small class="text-muted"><?php echo ucfirst(str_replace('_', ' ', $fee['type_frais'])); ?></small>
                                            </p>

                                            <div class="form-check form-switch mb-2">
                                                <input class="form-check-input" type="checkbox"
                                                       name="fees[<?php echo $fee['id']; ?>][paid]"
                                                       value="1"
                                                       id="fee_<?php echo $fee['id']; ?>"
                                                       <?php echo $fee['paye'] ? 'checked' : ''; ?>
                                                       onchange="togglePaymentDetails(<?php echo $fee['id']; ?>)">
                                                <label class="form-check-label" for="fee_<?php echo $fee['id']; ?>">
                                                    Frais payé
                                                </label>
                                            </div>

                                            <div id="payment_details_<?php echo $fee['id']; ?>"
                                                 style="display: <?php echo $fee['paye'] ? 'block' : 'none'; ?>;">
                                                <div class="mb-2">
                                                    <label class="form-label">Date de paiement</label>
                                                    <input type="date" class="form-control form-control-sm"
                                                           name="fees[<?php echo $fee['id']; ?>][date_paiement]"
                                                           value="<?php echo $fee['date_paiement'] ?: date('Y-m-d'); ?>">
                                                </div>
                                                <div class="mb-2">
                                                    <label class="form-label">Mode de paiement</label>
                                                    <select class="form-select form-select-sm"
                                                            name="fees[<?php echo $fee['id']; ?>][mode_paiement]">
                                                        <option value="especes" <?php echo ($fee['mode_paiement'] ?? 'especes') === 'especes' ? 'selected' : ''; ?>>Espèces</option>
                                                        <option value="cheque" <?php echo ($fee['mode_paiement'] ?? '') === 'cheque' ? 'selected' : ''; ?>>Chèque</option>
                                                        <option value="virement" <?php echo ($fee['mode_paiement'] ?? '') === 'virement' ? 'selected' : ''; ?>>Virement</option>
                                                        <option value="mobile_money" <?php echo ($fee['mode_paiement'] ?? '') === 'mobile_money' ? 'selected' : ''; ?>>Mobile Money</option>
                                                    </select>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p class="text-muted text-center">Aucun frais à gérer</p>
                    <?php endif; ?>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-save me-2"></i>
                        Mettre à jour
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Variables globales
let processingAction = false;

// Initialisation
document.addEventListener('DOMContentLoaded', function() {
    // Initialiser les tooltips
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
});

// Fonction pour basculer les détails de paiement
function togglePaymentDetails(feeId) {
    const checkbox = document.getElementById('fee_' + feeId);
    const details = document.getElementById('payment_details_' + feeId);

    if (checkbox.checked) {
        details.style.display = 'block';
    } else {
        details.style.display = 'none';
    }
}

// Confirmation pour les actions critiques
document.addEventListener('DOMContentLoaded', function() {
    // Confirmation pour l'approbation
    const approveForm = document.querySelector('#approveModal form');
    if (approveForm) {
        approveForm.addEventListener('submit', function(e) {
            if (processingAction) {
                e.preventDefault();
                return false;
            }

            if (!confirm('Confirmer l\'approbation de ce transfert ?')) {
                e.preventDefault();
                return false;
            }

            processingAction = true;
            const submitBtn = this.querySelector('button[type="submit"]');
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Traitement...';
            submitBtn.disabled = true;
        });
    }

    // Confirmation pour le rejet
    const rejectForm = document.querySelector('#rejectModal form');
    if (rejectForm) {
        rejectForm.addEventListener('submit', function(e) {
            if (processingAction) {
                e.preventDefault();
                return false;
            }

            const comment = document.getElementById('reject_comment').value.trim();
            if (!comment) {
                e.preventDefault();
                alert('Le motif du rejet est obligatoire');
                return false;
            }

            if (!confirm('Confirmer le rejet de ce transfert ?')) {
                e.preventDefault();
                return false;
            }

            processingAction = true;
            const submitBtn = this.querySelector('button[type="submit"]');
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Traitement...';
            submitBtn.disabled = true;
        });
    }

    // Confirmation pour la finalisation
    const completeForm = document.querySelector('#completeModal form');
    if (completeForm) {
        completeForm.addEventListener('submit', function(e) {
            if (processingAction) {
                e.preventDefault();
                return false;
            }

            if (!confirm('Confirmer la finalisation de ce transfert ? Cette action générera automatiquement le certificat.')) {
                e.preventDefault();
                return false;
            }

            processingAction = true;
            const submitBtn = this.querySelector('button[type="submit"]');
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Finalisation...';
            submitBtn.disabled = true;
        });
    }

    // Confirmation pour la réouverture
    const reopenForm = document.querySelector('#reopenModal form');
    if (reopenForm) {
        reopenForm.addEventListener('submit', function(e) {
            if (processingAction) {
                e.preventDefault();
                return false;
            }

            const comment = document.getElementById('reopen_comment').value.trim();
            if (!comment) {
                e.preventDefault();
                alert('Le motif de la réouverture est obligatoire');
                return false;
            }

            if (!confirm('Confirmer la réouverture de ce transfert ?')) {
                e.preventDefault();
                return false;
            }

            processingAction = true;
            const submitBtn = this.querySelector('button[type="submit"]');
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Traitement...';
            submitBtn.disabled = true;
        });
    }

    // Gestion des documents
    const documentsForm = document.querySelector('#documentsModal form');
    if (documentsForm) {
        documentsForm.addEventListener('submit', function(e) {
            if (processingAction) {
                e.preventDefault();
                return false;
            }

            processingAction = true;
            const submitBtn = this.querySelector('button[type="submit"]');
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Mise à jour...';
            submitBtn.disabled = true;
        });
    }

    // Gestion des frais
    const feesForm = document.querySelector('#feesModal form');
    if (feesForm) {
        feesForm.addEventListener('submit', function(e) {
            if (processingAction) {
                e.preventDefault();
                return false;
            }

            processingAction = true;
            const submitBtn = this.querySelector('button[type="submit"]');
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Mise à jour...';
            submitBtn.disabled = true;
        });
    }
});

// Fonction pour actualiser la page après une action
function refreshAfterAction() {
    setTimeout(function() {
        window.location.reload();
    }, 2000);
}
</script>

<?php include '../../../includes/footer.php'; ?>
