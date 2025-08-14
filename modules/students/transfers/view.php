<?php
/**
 * Visualisation détaillée d'un transfert d'élève
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

$page_title = "Détails du transfert";

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
        
        switch ($action) {
            case 'approve':
                $database->query(
                    "UPDATE transfers SET statut = 'approuve', approuve_par = ?, date_approbation = NOW() WHERE id = ? AND statut = 'en_attente'",
                    [$_SESSION['user_id'], $transfer_id]
                );
                
                // Historique
                $database->query(
                    "INSERT INTO transfer_history (transfer_id, action, ancien_statut, nouveau_statut, commentaire, user_id) VALUES (?, 'approbation', 'en_attente', 'approuve', ?, ?)",
                    [$transfer_id, $_POST['commentaire'] ?? 'Transfert approuvé', $_SESSION['user_id']]
                );
                
                showMessage('success', 'Transfert approuvé avec succès !');
                break;
                
            case 'reject':
                $database->query(
                    "UPDATE transfers SET statut = 'rejete', approuve_par = ?, date_approbation = NOW() WHERE id = ? AND statut = 'en_attente'",
                    [$_SESSION['user_id'], $transfer_id]
                );
                
                // Historique
                $database->query(
                    "INSERT INTO transfer_history (transfer_id, action, ancien_statut, nouveau_statut, commentaire, user_id) VALUES (?, 'rejet', 'en_attente', 'rejete', ?, ?)",
                    [$transfer_id, $_POST['commentaire'] ?? 'Transfert rejeté', $_SESSION['user_id']]
                );
                
                showMessage('success', 'Transfert rejeté');
                break;
                
            case 'complete':
                $database->query(
                    "UPDATE transfers SET statut = 'complete', date_effective = COALESCE(date_effective, CURDATE()) WHERE id = ? AND statut = 'approuve'",
                    [$transfer_id]
                );
                
                // Mettre à jour le statut de l'élève si c'est une sortie
                $transfer_info = $database->query("SELECT * FROM transfers WHERE id = ?", [$transfer_id])->fetch();
                if (in_array($transfer_info['type_mouvement'], ['transfert_sortant', 'sortie_definitive'])) {
                    $database->query(
                        "UPDATE inscriptions SET statut = 'inactive', date_fin = CURDATE() WHERE eleve_id = ? AND statut = 'active'",
                        [$transfer_info['eleve_id']]
                    );
                }
                
                // Historique
                $database->query(
                    "INSERT INTO transfer_history (transfer_id, action, ancien_statut, nouveau_statut, commentaire, user_id) VALUES (?, 'completion', 'approuve', 'complete', ?, ?)",
                    [$transfer_id, $_POST['commentaire'] ?? 'Transfert complété', $_SESSION['user_id']]
                );
                
                showMessage('success', 'Transfert complété avec succès !');
                break;
                
            case 'add_comment':
                $database->query(
                    "INSERT INTO transfer_history (transfer_id, action, ancien_statut, nouveau_statut, commentaire, user_id) VALUES (?, 'modification', ?, ?, ?, ?)",
                    [$transfer_id, $_POST['current_status'], $_POST['current_status'], $_POST['commentaire'], $_SESSION['user_id']]
                );
                
                showMessage('success', 'Commentaire ajouté avec succès !');
                break;
        }
        
        // Logger l'action
        logUserAction('transfer_action', 'transfers', "Action '$action' sur le transfert ID: $transfer_id", $transfer_id);
        
    } catch (Exception $e) {
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

// Récupérer l'historique
$history = $database->query(
    "SELECT th.*, u.nom as user_nom, u.prenom as user_prenom
     FROM transfer_history th
     LEFT JOIN users u ON th.user_id = u.id
     WHERE th.transfer_id = ?
     ORDER BY th.created_at DESC",
    [$transfer_id]
)->fetchAll();

include '../../../includes/header.php';
?>

<!-- Styles CSS modernes -->
<style>
.view-header {
    background: linear-gradient(135deg, #495057 0%, #6c757d 100%);
    color: white;
    padding: 2rem 0;
    margin: -20px -15px 30px -15px;
    border-radius: 0 0 20px 20px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.1);
}

.view-header h1 {
    font-weight: 300;
    font-size: 2.5rem;
    margin-bottom: 0.5rem;
}

.view-header .subtitle {
    opacity: 0.9;
    font-size: 1.1rem;
}

.info-card {
    background: white;
    border-radius: 15px;
    padding: 2rem;
    box-shadow: 0 5px 15px rgba(0,0,0,0.08);
    border: none;
    margin-bottom: 2rem;
    transition: all 0.3s ease;
}

.info-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(0,0,0,0.12);
}

.student-summary {
    background: linear-gradient(135deg, #e3f2fd 0%, #f3e5f5 100%);
    border-radius: 15px;
    padding: 2rem;
    margin-bottom: 2rem;
    border-left: 5px solid #495057;
    position: relative;
    overflow: hidden;
}

.student-summary::before {
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

.detail-section {
    background: #f8f9fa;
    border-radius: 10px;
    padding: 1.5rem;
    margin-bottom: 1.5rem;
    border-left: 4px solid #495057;
}

.detail-section h6 {
    color: #495057;
    font-weight: 600;
    margin-bottom: 1rem;
    display: flex;
    align-items: center;
}

.detail-section h6 i {
    margin-right: 0.5rem;
    font-size: 1.2rem;
}

.detail-row {
    display: flex;
    margin-bottom: 1rem;
    align-items: flex-start;
}

.detail-label {
    font-weight: 600;
    color: #495057;
    width: 200px;
    flex-shrink: 0;
}

.detail-value {
    flex: 1;
    color: #6c757d;
}

.action-buttons {
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
    border-radius: 15px;
    padding: 2rem;
    margin-bottom: 2rem;
    text-align: center;
}

.btn-modern {
    border-radius: 25px;
    padding: 0.75rem 2rem;
    font-weight: 600;
    transition: all 0.3s ease;
    border: none;
    box-shadow: 0 3px 15px rgba(0,0,0,0.1);
    margin: 0.25rem;
}

.btn-modern:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 25px rgba(0,0,0,0.2);
}

.btn-success.btn-modern {
    background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
}

.btn-danger.btn-modern {
    background: linear-gradient(135deg, #dc3545 0%, #fd7e14 100%);
}

.btn-primary.btn-modern {
    background: linear-gradient(135deg, #007bff 0%, #6610f2 100%);
}

.btn-info.btn-modern {
    background: linear-gradient(135deg, #17a2b8 0%, #6f42c1 100%);
}

.btn-secondary.btn-modern {
    background: linear-gradient(135deg, #6c757d 0%, #495057 100%);
}

.timeline {
    position: relative;
    padding-left: 2rem;
}

.timeline::before {
    content: '';
    position: absolute;
    left: 1rem;
    top: 0;
    bottom: 0;
    width: 2px;
    background: linear-gradient(to bottom, #495057, #6c757d);
}

.timeline-item {
    position: relative;
    margin-bottom: 2rem;
    background: white;
    border-radius: 10px;
    padding: 1.5rem;
    box-shadow: 0 2px 10px rgba(0,0,0,0.05);
    margin-left: 1rem;
}

.timeline-item::before {
    content: '';
    position: absolute;
    left: -1.5rem;
    top: 1.5rem;
    width: 12px;
    height: 12px;
    background: #495057;
    border-radius: 50%;
    border: 3px solid white;
    box-shadow: 0 0 0 3px #495057;
}

.document-item, .fee-item {
    background: white;
    border-radius: 8px;
    padding: 1rem;
    margin-bottom: 0.5rem;
    border-left: 4px solid #e9ecef;
    transition: all 0.3s ease;
}

.document-item:hover, .fee-item:hover {
    border-left-color: #495057;
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
    .view-header {
        margin: -20px -15px 20px -15px;
        padding: 1.5rem 0;
    }
    
    .view-header h1 {
        font-size: 2rem;
    }
    
    .info-card, .student-summary {
        padding: 1rem;
    }
    
    .detail-row {
        flex-direction: column;
    }
    
    .detail-label {
        width: 100%;
        margin-bottom: 0.5rem;
    }
    
    .action-buttons {
        padding: 1rem;
    }
    
    .btn-modern {
        display: block;
        width: 100%;
        margin-bottom: 0.5rem;
    }
}
</style>

<!-- En-tête moderne -->
<div class="view-header">
    <div class="container-fluid">
        <div class="row align-items-center">
            <div class="col-md-8">
                <h1 class="animate-fade-in">
                    <i class="fas fa-eye me-3"></i>
                    Détails du transfert
                </h1>
                <p class="subtitle animate-fade-in animate-delay-1">
                    Transfert N° <?php echo str_pad($transfer_id, 6, '0', STR_PAD_LEFT); ?> - 
                    <?php echo htmlspecialchars($transfer['nom'] . ' ' . $transfer['prenom']); ?>
                </p>
            </div>
            <div class="col-md-4 text-end">
                <div class="animate-fade-in animate-delay-2">
                    <a href="bulk-process.php" class="btn btn-light btn-modern">
                        <i class="fas fa-arrow-left me-2"></i>
                        Retour à la liste
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Résumé de l'élève et du transfert -->
<div class="student-summary animate-fade-in animate-delay-1">
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
                    <p class="mb-1"><strong>Année scolaire:</strong> <?php echo htmlspecialchars($transfer['annee_nom'] ?? 'Non spécifiée'); ?></p>
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

<!-- Actions disponibles -->
<?php if ($transfer['statut'] !== 'complete'): ?>
<div class="action-buttons animate-fade-in animate-delay-2">
    <h5 class="mb-3">
        <i class="fas fa-cogs me-2"></i>
        Actions disponibles
    </h5>

    <div class="d-flex justify-content-center flex-wrap gap-2">
        <?php if ($transfer['statut'] === 'en_attente'): ?>
            <button type="button" class="btn btn-success btn-modern" data-bs-toggle="modal" data-bs-target="#approveModal">
                <i class="fas fa-check me-2"></i>
                Approuver
            </button>
            <button type="button" class="btn btn-danger btn-modern" data-bs-toggle="modal" data-bs-target="#rejectModal">
                <i class="fas fa-times me-2"></i>
                Rejeter
            </button>
        <?php elseif ($transfer['statut'] === 'approuve'): ?>
            <button type="button" class="btn btn-primary btn-modern" data-bs-toggle="modal" data-bs-target="#completeModal">
                <i class="fas fa-check-circle me-2"></i>
                Marquer comme complété
            </button>
        <?php endif; ?>

        <button type="button" class="btn btn-info btn-modern" data-bs-toggle="modal" data-bs-target="#commentModal">
            <i class="fas fa-comment me-2"></i>
            Ajouter un commentaire
        </button>

        <?php if ($transfer['statut'] === 'complete'): ?>
            <a href="certificates/generate.php?id=<?php echo $transfer_id; ?>" class="btn btn-secondary btn-modern">
                <i class="fas fa-certificate me-2"></i>
                Voir le certificat
            </a>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<!-- Détails du transfert -->
<div class="row">
    <div class="col-lg-8">
        <!-- Informations du transfert -->
        <div class="info-card animate-fade-in animate-delay-3">
            <h5 class="mb-3">
                <i class="fas fa-exchange-alt me-2"></i>
                Informations du transfert
            </h5>

            <div class="detail-section">
                <h6><i class="fas fa-info-circle"></i>Détails généraux</h6>
                <div class="detail-row">
                    <div class="detail-label">Motif:</div>
                    <div class="detail-value"><?php echo htmlspecialchars($transfer['motif']); ?></div>
                </div>

                <?php if ($transfer['type_mouvement'] === 'transfert_entrant'): ?>
                    <div class="detail-row">
                        <div class="detail-label">École d'origine:</div>
                        <div class="detail-value"><?php echo htmlspecialchars($transfer['ecole_origine']); ?></div>
                    </div>
                    <div class="detail-row">
                        <div class="detail-label">Classe d'affectation:</div>
                        <div class="detail-value"><?php echo htmlspecialchars(($transfer['classe_destination_niveau'] ?? '') . ' - ' . ($transfer['classe_destination_nom'] ?? '')); ?></div>
                    </div>
                <?php elseif ($transfer['type_mouvement'] === 'transfert_sortant'): ?>
                    <div class="detail-row">
                        <div class="detail-label">Classe fréquentée:</div>
                        <div class="detail-value"><?php echo htmlspecialchars(($transfer['classe_origine_niveau'] ?? '') . ' - ' . ($transfer['classe_origine_nom'] ?? '')); ?></div>
                    </div>
                    <div class="detail-row">
                        <div class="detail-label">École de destination:</div>
                        <div class="detail-value"><?php echo htmlspecialchars($transfer['ecole_destination']); ?></div>
                    </div>
                <?php else: ?>
                    <div class="detail-row">
                        <div class="detail-label">Dernière classe:</div>
                        <div class="detail-value"><?php echo htmlspecialchars(($transfer['classe_origine_niveau'] ?? '') . ' - ' . ($transfer['classe_origine_nom'] ?? '')); ?></div>
                    </div>
                <?php endif; ?>

                <?php if ($transfer['observations']): ?>
                    <div class="detail-row">
                        <div class="detail-label">Observations:</div>
                        <div class="detail-value"><?php echo nl2br(htmlspecialchars($transfer['observations'])); ?></div>
                    </div>
                <?php endif; ?>
            </div>

            <div class="detail-section">
                <h6><i class="fas fa-calendar-alt"></i>Dates importantes</h6>
                <div class="detail-row">
                    <div class="detail-label">Date de demande:</div>
                    <div class="detail-value"><?php echo date('d/m/Y', strtotime($transfer['date_demande'])); ?></div>
                </div>
                <?php if ($transfer['date_approbation']): ?>
                    <div class="detail-row">
                        <div class="detail-label">Date d'approbation:</div>
                        <div class="detail-value"><?php echo date('d/m/Y à H:i', strtotime($transfer['date_approbation'])); ?></div>
                    </div>
                <?php endif; ?>
                <?php if ($transfer['date_effective']): ?>
                    <div class="detail-row">
                        <div class="detail-label">Date effective:</div>
                        <div class="detail-value"><?php echo date('d/m/Y', strtotime($transfer['date_effective'])); ?></div>
                    </div>
                <?php endif; ?>
                <div class="detail-row">
                    <div class="detail-label">Créé le:</div>
                    <div class="detail-value"><?php echo date('d/m/Y à H:i', strtotime($transfer['created_at'])); ?></div>
                </div>
            </div>

            <div class="detail-section">
                <h6><i class="fas fa-users"></i>Personnes impliquées</h6>
                <div class="detail-row">
                    <div class="detail-label">Traité par:</div>
                    <div class="detail-value">
                        <?php if ($transfer['traite_par_nom']): ?>
                            <?php echo htmlspecialchars($transfer['traite_par_nom'] . ' ' . $transfer['traite_par_prenom']); ?>
                        <?php else: ?>
                            <span class="text-muted">Non spécifié</span>
                        <?php endif; ?>
                    </div>
                </div>
                <?php if ($transfer['approuve_par_nom']): ?>
                    <div class="detail-row">
                        <div class="detail-label">Approuvé par:</div>
                        <div class="detail-value">
                            <?php echo htmlspecialchars($transfer['approuve_par_nom'] . ' ' . $transfer['approuve_par_prenom']); ?>
                            <small class="text-muted">(<?php echo date('d/m/Y', strtotime($transfer['date_approbation'])); ?>)</small>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Informations personnelles de l'élève -->
        <div class="info-card animate-fade-in animate-delay-4">
            <h5 class="mb-3">
                <i class="fas fa-user me-2"></i>
                Informations personnelles
            </h5>

            <div class="row">
                <div class="col-md-6">
                    <div class="detail-section">
                        <h6><i class="fas fa-id-card"></i>Identité</h6>
                        <div class="detail-row">
                            <div class="detail-label">Lieu de naissance:</div>
                            <div class="detail-value"><?php echo htmlspecialchars($transfer['lieu_naissance'] ?: 'Non spécifié'); ?></div>
                        </div>
                        <div class="detail-row">
                            <div class="detail-label">Sexe:</div>
                            <div class="detail-value"><?php echo $transfer['sexe'] === 'M' ? 'Masculin' : 'Féminin'; ?></div>
                        </div>
                        <div class="detail-row">
                            <div class="detail-label">Adresse:</div>
                            <div class="detail-value"><?php echo htmlspecialchars($transfer['adresse'] ?: 'Non spécifiée'); ?></div>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="detail-section">
                        <h6><i class="fas fa-users"></i>Famille</h6>
                        <div class="detail-row">
                            <div class="detail-label">Nom du père:</div>
                            <div class="detail-value"><?php echo htmlspecialchars($transfer['nom_pere'] ?: 'Non spécifié'); ?></div>
                        </div>
                        <div class="detail-row">
                            <div class="detail-label">Nom de la mère:</div>
                            <div class="detail-value"><?php echo htmlspecialchars($transfer['nom_mere'] ?: 'Non spécifié'); ?></div>
                        </div>
                        <div class="detail-row">
                            <div class="detail-label">Profession du père:</div>
                            <div class="detail-value"><?php echo htmlspecialchars($transfer['profession_pere'] ?: 'Non spécifiée'); ?></div>
                        </div>
                        <div class="detail-row">
                            <div class="detail-label">Contact:</div>
                            <div class="detail-value">
                                <?php if ($transfer['telephone_parent']): ?>
                                    <i class="fas fa-phone me-1"></i><?php echo htmlspecialchars($transfer['telephone_parent']); ?>
                                <?php endif; ?>
                                <?php if ($transfer['email_parent']): ?>
                                    <br><i class="fas fa-envelope me-1"></i><?php echo htmlspecialchars($transfer['email_parent']); ?>
                                <?php endif; ?>
                                <?php if (!$transfer['telephone_parent'] && !$transfer['email_parent']): ?>
                                    <span class="text-muted">Non spécifié</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <!-- Documents -->
        <div class="info-card animate-fade-in animate-delay-3">
            <h5 class="mb-3">
                <i class="fas fa-file-alt me-2"></i>
                Documents
                <span class="badge bg-secondary ms-2"><?php echo count($documents); ?></span>
            </h5>

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
                                <?php else: ?>
                                    <i class="fas fa-times-circle text-danger fa-lg"></i>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p class="text-muted text-center">Aucun document requis</p>
            <?php endif; ?>
        </div>

        <!-- Frais -->
        <div class="info-card animate-fade-in animate-delay-4">
            <h5 class="mb-3">
                <i class="fas fa-money-bill me-2"></i>
                Frais
                <span class="badge bg-secondary ms-2"><?php echo count($fees); ?></span>
            </h5>

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

<!-- Historique -->
<div class="info-card animate-fade-in animate-delay-4">
    <h5 class="mb-3">
        <i class="fas fa-history me-2"></i>
        Historique des actions
        <span class="badge bg-secondary ms-2"><?php echo count($history); ?></span>
    </h5>

    <?php if (!empty($history)): ?>
        <div class="timeline">
            <?php foreach ($history as $item): ?>
                <div class="timeline-item">
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
                    <p>Êtes-vous sûr de vouloir approuver ce transfert ?</p>
                    <div class="mb-3">
                        <label for="approve_comment" class="form-label">Commentaire (optionnel)</label>
                        <textarea class="form-control" id="approve_comment" name="commentaire" rows="3"
                                  placeholder="Ajouter un commentaire..."></textarea>
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
                    <p>Êtes-vous sûr de vouloir rejeter ce transfert ?</p>
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
                    <p>Marquer ce transfert comme complété ?</p>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        Cette action mettra à jour le statut de l'élève et permettra la génération du certificat.
                    </div>
                    <div class="mb-3">
                        <label for="complete_comment" class="form-label">Commentaire (optionnel)</label>
                        <textarea class="form-control" id="complete_comment" name="commentaire" rows="3"
                                  placeholder="Ajouter un commentaire..."></textarea>
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

<!-- Modal de commentaire -->
<div class="modal fade" id="commentModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="add_comment">
                <input type="hidden" name="current_status" value="<?php echo $transfer['statut']; ?>">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-comment text-info me-2"></i>
                        Ajouter un commentaire
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="new_comment" class="form-label">Commentaire <span class="text-danger">*</span></label>
                        <textarea class="form-control" id="new_comment" name="commentaire" rows="4"
                                  placeholder="Saisir votre commentaire..." required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-info">
                        <i class="fas fa-comment me-2"></i>
                        Ajouter
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Confirmation pour les actions critiques
document.addEventListener('DOMContentLoaded', function() {
    // Confirmation pour l'approbation
    document.getElementById('approveModal').addEventListener('submit', function(e) {
        if (!confirm('Confirmer l\'approbation de ce transfert ?')) {
            e.preventDefault();
        }
    });

    // Confirmation pour le rejet
    document.getElementById('rejectModal').addEventListener('submit', function(e) {
        const comment = document.getElementById('reject_comment').value.trim();
        if (!comment) {
            e.preventDefault();
            alert('Le motif du rejet est obligatoire');
            return;
        }
        if (!confirm('Confirmer le rejet de ce transfert ?')) {
            e.preventDefault();
        }
    });

    // Confirmation pour la finalisation
    document.getElementById('completeModal').addEventListener('submit', function(e) {
        if (!confirm('Confirmer la finalisation de ce transfert ?')) {
            e.preventDefault();
        }
    });
});
</script>

<?php include '../../../includes/footer.php'; ?>
