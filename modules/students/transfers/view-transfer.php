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

$page_title = "Détail du transfert";

// Récupérer l'ID du transfert
$transfer_id = $_GET['id'] ?? null;

if (!$transfer_id) {
    showMessage('error', 'ID de transfert manquant');
    redirectTo('index.php');
}

// Récupérer les informations complètes du transfert
$transfer = $database->query(
    "SELECT t.*,
            e.numero_matricule, e.nom, e.prenom, e.date_naissance, e.lieu_naissance, e.sexe,
            e.adresse, e.telephone_parent, e.email_parent, e.nom_pere, e.nom_mere,
            e.profession_pere, e.profession_mere, e.photo,
            c_orig.nom as classe_origine_nom, c_orig.niveau as classe_origine_niveau,
            c_dest.nom as classe_destination_nom, c_dest.niveau as classe_destination_niveau,
            u_traite.nom as traite_par_nom, u_traite.prenom as traite_par_prenom,
            u_approuve.nom as approuve_par_nom, u_approuve.prenom as approuve_par_prenom,
            a.annee as annee_scolaire
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
    redirectTo('index.php');
}

// Récupérer les documents associés
$documents = $database->query(
    "SELECT * FROM transfer_documents WHERE transfer_id = ? ORDER BY obligatoire DESC, nom_document",
    [$transfer_id]
)->fetchAll();

// Récupérer les frais
$frais = $database->query(
    "SELECT * FROM transfer_fees WHERE transfer_id = ? ORDER BY type_frais",
    [$transfer_id]
)->fetchAll();

// Récupérer l'historique
$historique = $database->query(
    "SELECT h.*, u.nom as user_nom, u.prenom as user_prenom
     FROM transfer_history h
     LEFT JOIN users u ON h.user_id = u.id
     WHERE h.transfer_id = ?
     ORDER BY h.created_at DESC",
    [$transfer_id]
)->fetchAll();

// Définir les labels pour les types de mouvement
$type_labels = [
    'transfert_entrant' => 'Transfert entrant',
    'transfert_sortant' => 'Transfert sortant',
    'sortie_definitive' => 'Sortie définitive'
];

// Définir les couleurs pour les statuts
$status_colors = [
    'en_attente' => 'warning',
    'approuve' => 'success',
    'rejete' => 'danger',
    'complete' => 'primary'
];

include '../../../includes/header.php';
?>

<!-- Styles CSS personnalisés -->
<style>
.transfer-header {
    background: linear-gradient(135deg, #007bff 0%, #6610f2 100%);
    color: white;
    padding: 2rem 0;
    margin: -20px -15px 30px -15px;
    border-radius: 0 0 20px 20px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.1);
}

.transfer-header h1 {
    font-weight: 300;
    font-size: 2.5rem;
    margin-bottom: 0.5rem;
}

.info-card {
    background: white;
    border-radius: 15px;
    padding: 1.5rem;
    box-shadow: 0 5px 15px rgba(0,0,0,0.08);
    border: none;
    margin-bottom: 1.5rem;
    transition: transform 0.2s ease;
}

.info-card:hover {
    transform: translateY(-2px);
}

.info-card h5 {
    color: #007bff;
    font-weight: 600;
    margin-bottom: 1rem;
    display: flex;
    align-items: center;
}

.info-card h5 i {
    margin-right: 0.5rem;
}

.student-photo {
    width: 120px;
    height: 120px;
    object-fit: cover;
    border-radius: 15px;
    border: 4px solid #fff;
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
}

.status-badge {
    font-size: 0.9rem;
    padding: 0.5rem 1rem;
    border-radius: 25px;
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
    background: #dee2e6;
}

.timeline-item {
    position: relative;
    margin-bottom: 1.5rem;
}

.timeline-item::before {
    content: '';
    position: absolute;
    left: -1.5rem;
    top: 0.5rem;
    width: 12px;
    height: 12px;
    border-radius: 50%;
    background: #007bff;
    border: 3px solid #fff;
    box-shadow: 0 0 0 3px #dee2e6;
}

.document-item {
    display: flex;
    align-items: center;
    padding: 0.75rem;
    background: #f8f9fa;
    border-radius: 8px;
    margin-bottom: 0.5rem;
}

.document-item.required {
    border-left: 4px solid #dc3545;
}

.document-item.provided {
    border-left: 4px solid #28a745;
}
</style>

<div class="transfer-header text-center">
    <div class="container">
        <h1>
            <i class="fas fa-exchange-alt me-2"></i>
            Détail du Transfert
        </h1>
        <p class="lead mb-0">
            <?php echo $type_labels[$transfer['type_mouvement']] ?? $transfer['type_mouvement']; ?>
            - Dossier #<?php echo str_pad($transfer['id'], 6, '0', STR_PAD_LEFT); ?>
        </p>
    </div>
</div>

<div class="container-fluid">
    <!-- Barre d'actions -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <a href="index.php" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left me-1"></i>
                        Retour à la liste
                    </a>
                </div>
                <div class="btn-group">
                    <?php if (checkPermission('students') && $transfer['statut'] === 'en_attente'): ?>
                        <a href="process.php?id=<?php echo $transfer['id']; ?>" class="btn btn-primary">
                            <i class="fas fa-cog me-1"></i>
                            Traiter
                        </a>
                    <?php endif; ?>

                    <?php if ($transfer['statut'] === 'approuve' || $transfer['certificat_genere']): ?>
                        <a href="certificates/generate.php?id=<?php echo $transfer['id']; ?>" class="btn btn-success">
                            <i class="fas fa-certificate me-1"></i>
                            Certificat
                        </a>
                    <?php endif; ?>

                    <button class="btn btn-outline-primary" onclick="window.print()">
                        <i class="fas fa-print me-1"></i>
                        Imprimer
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Contenu principal -->
    <div class="row">
        <!-- Informations de l'élève -->
        <div class="col-lg-4">
            <div class="info-card">
                <h5>
                    <i class="fas fa-user"></i>
                    Informations de l'élève
                </h5>

                <div class="text-center mb-3">
                    <?php if ($transfer['photo']): ?>
                        <img src="../../../uploads/photos/<?php echo htmlspecialchars($transfer['photo']); ?>"
                             alt="Photo de l'élève" class="student-photo">
                    <?php else: ?>
                        <div class="student-photo d-flex align-items-center justify-content-center bg-light">
                            <i class="fas fa-user fa-3x text-muted"></i>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="student-info">
                    <h6 class="text-center mb-3">
                        <?php echo htmlspecialchars($transfer['nom'] . ' ' . $transfer['prenom']); ?>
                    </h6>

                    <div class="row g-2">
                        <div class="col-12">
                            <small class="text-muted">Matricule</small>
                            <div class="fw-bold"><?php echo htmlspecialchars($transfer['numero_matricule']); ?></div>
                        </div>
                        <div class="col-6">
                            <small class="text-muted">Sexe</small>
                            <div><?php echo $transfer['sexe'] === 'M' ? 'Masculin' : 'Féminin'; ?></div>
                        </div>
                        <div class="col-6">
                            <small class="text-muted">Âge</small>
                            <div><?php echo calculateAge($transfer['date_naissance']); ?> ans</div>
                        </div>
                        <div class="col-12">
                            <small class="text-muted">Date de naissance</small>
                            <div><?php echo formatDate($transfer['date_naissance']); ?></div>
                        </div>
                        <div class="col-12">
                            <small class="text-muted">Lieu de naissance</small>
                            <div><?php echo htmlspecialchars($transfer['lieu_naissance'] ?? 'Non renseigné'); ?></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Informations familiales -->
            <div class="info-card">
                <h5>
                    <i class="fas fa-users"></i>
                    Informations familiales
                </h5>

                <div class="row g-2">
                    <div class="col-12">
                        <small class="text-muted">Nom du père</small>
                        <div><?php echo htmlspecialchars($transfer['nom_pere'] ?? 'Non renseigné'); ?></div>
                    </div>
                    <div class="col-12">
                        <small class="text-muted">Profession du père</small>
                        <div><?php echo htmlspecialchars($transfer['profession_pere'] ?? 'Non renseignée'); ?></div>
                    </div>
                    <div class="col-12">
                        <small class="text-muted">Nom de la mère</small>
                        <div><?php echo htmlspecialchars($transfer['nom_mere'] ?? 'Non renseigné'); ?></div>
                    </div>
                    <div class="col-12">
                        <small class="text-muted">Profession de la mère</small>
                        <div><?php echo htmlspecialchars($transfer['profession_mere'] ?? 'Non renseignée'); ?></div>
                    </div>
                    <div class="col-12">
                        <small class="text-muted">Téléphone parent</small>
                        <div><?php echo htmlspecialchars($transfer['telephone_parent'] ?? 'Non renseigné'); ?></div>
                    </div>
                    <?php if ($transfer['email_parent']): ?>
                    <div class="col-12">
                        <small class="text-muted">Email parent</small>
                        <div><?php echo htmlspecialchars($transfer['email_parent']); ?></div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Détails du transfert -->
        <div class="col-lg-8">
            <!-- Informations générales du transfert -->
            <div class="info-card">
                <h5>
                    <i class="fas fa-exchange-alt"></i>
                    Détails du transfert
                </h5>

                <div class="row g-3">
                    <div class="col-md-6">
                        <small class="text-muted">Type de mouvement</small>
                        <div class="fw-bold">
                            <?php echo $type_labels[$transfer['type_mouvement']] ?? $transfer['type_mouvement']; ?>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <small class="text-muted">Statut</small>
                        <div>
                            <span class="badge bg-<?php echo $status_colors[$transfer['statut']] ?? 'secondary'; ?> status-badge">
                                <?php echo ucfirst(str_replace('_', ' ', $transfer['statut'])); ?>
                            </span>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <small class="text-muted">Date de demande</small>
                        <div><?php echo formatDate($transfer['date_demande']); ?></div>
                    </div>
                    <div class="col-md-6">
                        <small class="text-muted">Date effective</small>
                        <div><?php echo $transfer['date_effective'] ? formatDate($transfer['date_effective']) : 'Non définie'; ?></div>
                    </div>
                </div>
            </div>

            <!-- Informations scolaires -->
            <div class="info-card">
                <h5>
                    <i class="fas fa-school"></i>
                    Informations scolaires
                </h5>

                <div class="row g-3">
                    <?php if ($transfer['ecole_origine']): ?>
                    <div class="col-md-6">
                        <small class="text-muted">École d'origine</small>
                        <div><?php echo htmlspecialchars($transfer['ecole_origine']); ?></div>
                    </div>
                    <?php endif; ?>

                    <?php if ($transfer['ecole_destination']): ?>
                    <div class="col-md-6">
                        <small class="text-muted">École de destination</small>
                        <div><?php echo htmlspecialchars($transfer['ecole_destination']); ?></div>
                    </div>
                    <?php endif; ?>

                    <?php if ($transfer['classe_origine_nom']): ?>
                    <div class="col-md-6">
                        <small class="text-muted">Classe d'origine</small>
                        <div><?php echo htmlspecialchars($transfer['classe_origine_niveau'] . ' - ' . $transfer['classe_origine_nom']); ?></div>
                    </div>
                    <?php endif; ?>

                    <?php if ($transfer['classe_destination_nom']): ?>
                    <div class="col-md-6">
                        <small class="text-muted">Classe de destination</small>
                        <div><?php echo htmlspecialchars($transfer['classe_destination_niveau'] . ' - ' . $transfer['classe_destination_nom']); ?></div>
                    </div>
                    <?php endif; ?>

                    <?php if ($transfer['annee_scolaire']): ?>
                    <div class="col-12">
                        <small class="text-muted">Année scolaire</small>
                        <div><?php echo htmlspecialchars($transfer['annee_scolaire']); ?></div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Motif et observations -->
            <div class="info-card">
                <h5>
                    <i class="fas fa-comment-alt"></i>
                    Motif et observations
                </h5>

                <div class="mb-3">
                    <small class="text-muted">Motif du transfert</small>
                    <div class="mt-1">
                        <?php echo nl2br(htmlspecialchars($transfer['motif'] ?? 'Aucun motif spécifié')); ?>
                    </div>
                </div>

                <?php if ($transfer['observations']): ?>
                <div>
                    <small class="text-muted">Observations</small>
                    <div class="mt-1">
                        <?php echo nl2br(htmlspecialchars($transfer['observations'])); ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <!-- Informations financières -->
            <?php if ($transfer['frais_transfert'] > 0 || !empty($frais)): ?>
            <div class="info-card">
                <h5>
                    <i class="fas fa-money-bill-wave"></i>
                    Informations financières
                </h5>

                <div class="row g-3">
                    <div class="col-md-6">
                        <small class="text-muted">Frais de transfert</small>
                        <div class="fw-bold"><?php echo formatMoney($transfer['frais_transfert']); ?></div>
                    </div>
                    <div class="col-md-6">
                        <small class="text-muted">Frais payés</small>
                        <div class="fw-bold text-success"><?php echo formatMoney($transfer['frais_payes'] ?? 0); ?></div>
                    </div>
                </div>

                <?php if (!empty($frais)): ?>
                <div class="mt-3">
                    <small class="text-muted">Détail des frais</small>
                    <div class="table-responsive mt-2">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Type</th>
                                    <th>Libellé</th>
                                    <th>Montant</th>
                                    <th>Statut</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($frais as $frais_item): ?>
                                <tr>
                                    <td><?php echo ucfirst(str_replace('_', ' ', $frais_item['type_frais'])); ?></td>
                                    <td><?php echo htmlspecialchars($frais_item['libelle']); ?></td>
                                    <td><?php echo formatMoney($frais_item['montant']); ?></td>
                                    <td>
                                        <span class="badge bg-<?php echo $frais_item['paye'] ? 'success' : 'warning'; ?>">
                                            <?php echo $frais_item['paye'] ? 'Payé' : 'En attente'; ?>
                                        </span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <!-- Documents -->
            <?php if (!empty($documents)): ?>
            <div class="info-card">
                <h5>
                    <i class="fas fa-file-alt"></i>
                    Documents
                </h5>

                <div class="documents-list">
                    <?php foreach ($documents as $document): ?>
                    <div class="document-item <?php echo $document['obligatoire'] ? 'required' : ''; ?> <?php echo $document['fourni'] ? 'provided' : ''; ?>">
                        <div class="flex-grow-1">
                            <div class="d-flex align-items-center">
                                <i class="fas fa-file me-2"></i>
                                <strong><?php echo htmlspecialchars($document['nom_document']); ?></strong>
                                <?php if ($document['obligatoire']): ?>
                                    <span class="badge bg-danger ms-2">Obligatoire</span>
                                <?php endif; ?>
                            </div>
                            <small class="text-muted">
                                Type: <?php echo ucfirst($document['type_document']); ?>
                                <?php if ($document['fourni'] && $document['date_upload']): ?>
                                    - Fourni le <?php echo formatDate($document['date_upload']); ?>
                                <?php endif; ?>
                            </small>
                        </div>
                        <div class="ms-auto">
                            <?php if ($document['fourni']): ?>
                                <span class="badge bg-success">Fourni</span>
                                <?php if ($document['chemin_fichier']): ?>
                                    <a href="../../../uploads/transfers/<?php echo htmlspecialchars($document['chemin_fichier']); ?>"
                                       target="_blank" class="btn btn-sm btn-outline-primary ms-1">
                                        <i class="fas fa-download"></i>
                                    </a>
                                <?php endif; ?>
                            <?php else: ?>
                                <span class="badge bg-warning">Manquant</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Informations de traitement -->
            <div class="info-card">
                <h5>
                    <i class="fas fa-cogs"></i>
                    Informations de traitement
                </h5>

                <div class="row g-3">
                    <div class="col-md-6">
                        <small class="text-muted">Traité par</small>
                        <div>
                            <?php if ($transfer['traite_par_nom']): ?>
                                <?php echo htmlspecialchars($transfer['traite_par_nom'] . ' ' . $transfer['traite_par_prenom']); ?>
                            <?php else: ?>
                                <em class="text-muted">Non traité</em>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <small class="text-muted">Date de traitement</small>
                        <div>
                            <?php echo $transfer['date_traitement'] ? formatDate($transfer['date_traitement']) : 'Non traité'; ?>
                        </div>
                    </div>

                    <?php if ($transfer['approuve_par_nom']): ?>
                    <div class="col-md-6">
                        <small class="text-muted">Approuvé par</small>
                        <div><?php echo htmlspecialchars($transfer['approuve_par_nom'] . ' ' . $transfer['approuve_par_prenom']); ?></div>
                    </div>
                    <div class="col-md-6">
                        <small class="text-muted">Date d'approbation</small>
                        <div><?php echo $transfer['date_approbation'] ? formatDate($transfer['date_approbation']) : 'Non approuvé'; ?></div>
                    </div>
                    <?php endif; ?>

                    <?php if ($transfer['certificat_genere']): ?>
                    <div class="col-md-6">
                        <small class="text-muted">Certificat</small>
                        <div>
                            <span class="badge bg-success">Généré</span>
                            <?php if ($transfer['numero_certificat']): ?>
                                <br><small>N° <?php echo htmlspecialchars($transfer['numero_certificat']); ?></small>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Historique -->
            <?php if (!empty($historique)): ?>
            <div class="info-card">
                <h5>
                    <i class="fas fa-history"></i>
                    Historique des actions
                </h5>

                <div class="timeline">
                    <?php foreach ($historique as $action): ?>
                    <div class="timeline-item">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <strong><?php echo ucfirst($action['action']); ?></strong>
                                <?php if ($action['ancien_statut'] && $action['nouveau_statut']): ?>
                                    <br><small class="text-muted">
                                        De "<?php echo $action['ancien_statut']; ?>" vers "<?php echo $action['nouveau_statut']; ?>"
                                    </small>
                                <?php endif; ?>
                                <?php if ($action['commentaire']): ?>
                                    <br><em><?php echo htmlspecialchars($action['commentaire']); ?></em>
                                <?php endif; ?>
                                <?php if ($action['user_nom']): ?>
                                    <br><small class="text-muted">
                                        Par <?php echo htmlspecialchars($action['user_nom'] . ' ' . $action['user_prenom']); ?>
                                    </small>
                                <?php endif; ?>
                            </div>
                            <small class="text-muted">
                                <?php echo formatDate($action['created_at'], 'd/m/Y H:i'); ?>
                            </small>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
// Améliorer l'impression
window.addEventListener('beforeprint', function() {
    // Masquer les boutons d'action lors de l'impression
    document.querySelectorAll('.btn-group, .btn-toolbar').forEach(function(element) {
        element.style.display = 'none';
    });
});

window.addEventListener('afterprint', function() {
    // Réafficher les boutons après l'impression
    document.querySelectorAll('.btn-group, .btn-toolbar').forEach(function(element) {
        element.style.display = '';
    });
});

// Animation des cartes au chargement
document.addEventListener('DOMContentLoaded', function() {
    const cards = document.querySelectorAll('.info-card');
    cards.forEach((card, index) => {
        card.style.opacity = '0';
        card.style.transform = 'translateY(20px)';

        setTimeout(() => {
            card.style.transition = 'all 0.5s ease';
            card.style.opacity = '1';
            card.style.transform = 'translateY(0)';
        }, index * 100);
    });
});

// Tooltip pour les badges de statut
document.addEventListener('DOMContentLoaded', function() {
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    const tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
});
</script>

<?php include '../../../includes/footer.php'; ?>
