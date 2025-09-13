<?php
/**
 * Visualisation d'une demande d'admission
 * Application de gestion scolaire - République Démocratique du Congo
 */

require_once '../../../config/config.php';
require_once '../../../config/database.php';
require_once '../../../includes/functions.php';
require_once '../../../includes/permissions-pages.php';

// Vérifier l'authentification et les permissions
requireLogin();
requirePagePermissionFromDB('admissions', 'applications', 'read', '../../../dashboard.php');

// Récupérer l'ID de la demande
$demande_id = intval($_GET['id'] ?? 0);

if (!$demande_id) {
    showMessage('error', 'ID de demande non spécifié.');
    redirectTo('../index.php');
}

// Récupérer les détails de la demande
$demande = $database->query(
    "SELECT da.*, 
            c.nom as classe_nom, c.niveau as classe_niveau,
            as2.annee as annee_scolaire_nom,
            e.nom as eleve_nom, e.prenom as eleve_prenom, e.status as eleve_status,
            e.numero_eleve, e.numero_matricule
     FROM demandes_admission da 
     LEFT JOIN classes c ON da.classe_demandee_id = c.id 
     LEFT JOIN annees_scolaires as2 ON da.annee_scolaire_id = as2.id
     LEFT JOIN eleves e ON da.eleve_cree_id = e.id
     WHERE da.id = ?",
    [$demande_id]
)->fetch();

if (!$demande) {
    showMessage('error', 'Demande d\'admission non trouvée.');
    redirectTo('../index.php');
}

$page_title = 'Demande d\'Admission - ' . $demande['numero_demande'];

// Récupérer l'historique des actions
$historique = $database->query(
    "SELECT ual.*, u.nom as user_name 
     FROM user_actions_log ual
     LEFT JOIN users u ON ual.user_id = u.id
     WHERE ual.module = 'admissions' AND ual.target_id = ? 
     ORDER BY ual.created_at DESC",
    [$demande_id]
)->fetchAll();

include '../../../includes/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">
        <i class="fas fa-file-alt me-2"></i>
        Demande d'Admission
        <span class="badge bg-primary ms-2"><?php echo htmlspecialchars($demande['numero_demande']); ?></span>
    </h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <a href="../index.php" class="btn btn-outline-secondary me-2">
            <i class="fas fa-arrow-left me-1"></i>
            Retour
        </a>
        <a href="edit.php?id=<?php echo $demande_id; ?>" class="btn btn-warning me-2">
            <i class="fas fa-edit me-1"></i>
            Modifier
        </a>
        <a href="print.php?id=<?php echo $demande_id; ?>" class="btn btn-info me-2" target="_blank">
            <i class="fas fa-print me-1"></i>
            Imprimer
        </a>
        <?php if ($demande['status'] === 'en_cours_traitement'): ?>
            <button type="button" class="btn btn-success me-2" data-bs-toggle="modal" data-bs-target="#evaluationModal">
                <i class="fas fa-clipboard-check me-1"></i>
                Évaluer
            </button>
        <?php endif; ?>
    </div>
</div>

<!-- Statut de la demande -->
<div class="row mb-4">
    <div class="col-12">
        <?php
        $status_class = '';
        $status_text = '';
        $status_icon = '';
        
        switch ($demande['status']) {
            case 'en_cours_traitement':
                $status_class = 'bg-warning';
                $status_text = 'En cours de traitement';
                $status_icon = 'clock';
                break;
            case 'acceptee':
                $status_class = 'bg-success';
                $status_text = 'Acceptée';
                $status_icon = 'check-circle';
                break;
            case 'refusee':
                $status_class = 'bg-danger';
                $status_text = 'Refusée';
                $status_icon = 'times-circle';
                break;
            case 'en_attente':
                $status_class = 'bg-info';
                $status_text = 'En attente';
                $status_icon = 'hourglass-half';
                break;
            default:
                $status_class = 'bg-secondary';
                $status_text = ucfirst($demande['status']);
                $status_icon = 'question-circle';
        }
        ?>
        <div class="alert alert-<?php echo str_replace('bg-', 'alert-', $status_class); ?> d-flex align-items-center">
            <i class="fas fa-<?php echo $status_icon; ?> me-2"></i>
            <strong>Statut :</strong> <?php echo $status_text; ?>
            <?php if ($demande['status'] === 'en_cours_traitement'): ?>
                <span class="ms-2">- Cette demande nécessite une évaluation</span>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="row">
    <!-- Informations principales -->
    <div class="col-lg-8">
        <!-- Informations de l'élève -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-user me-2"></i>
                    Informations de l'Élève
                </h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold">Nom complet</label>
                        <div class="form-control-plaintext">
                            <?php echo htmlspecialchars($demande['nom_eleve'] . ' ' . $demande['prenom_eleve']); ?>
                        </div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold">Sexe</label>
                        <div class="form-control-plaintext">
                            <?php echo $demande['sexe'] === 'M' ? 'Masculin' : 'Féminin'; ?>
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold">Date de naissance</label>
                        <div class="form-control-plaintext">
                            <?php echo date('d/m/Y', strtotime($demande['date_naissance'])); ?>
                            (<?php echo date('Y') - date('Y', strtotime($demande['date_naissance'])); ?> ans)
                        </div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold">Lieu de naissance</label>
                        <div class="form-control-plaintext">
                            <?php echo htmlspecialchars($demande['lieu_naissance'] ?: 'Non spécifié'); ?>
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold">Téléphone</label>
                        <div class="form-control-plaintext">
                            <?php echo htmlspecialchars($demande['telephone'] ?: 'Non spécifié'); ?>
                        </div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold">Email</label>
                        <div class="form-control-plaintext">
                            <?php echo htmlspecialchars($demande['email'] ?: 'Non spécifié'); ?>
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-12 mb-3">
                        <label class="form-label fw-bold">Adresse</label>
                        <div class="form-control-plaintext">
                            <?php echo htmlspecialchars($demande['adresse'] ?: 'Non spécifiée'); ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Informations des parents -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-users me-2"></i>
                    Informations des Parents/Tuteurs
                </h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold">Nom du père</label>
                        <div class="form-control-plaintext">
                            <?php echo htmlspecialchars($demande['nom_pere'] ?: 'Non spécifié'); ?>
                        </div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold">Profession du père</label>
                        <div class="form-control-plaintext">
                            <?php echo htmlspecialchars($demande['profession_pere'] ?: 'Non spécifiée'); ?>
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold">Nom de la mère</label>
                        <div class="form-control-plaintext">
                            <?php echo htmlspecialchars($demande['nom_mere'] ?: 'Non spécifié'); ?>
                        </div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold">Profession de la mère</label>
                        <div class="form-control-plaintext">
                            <?php echo htmlspecialchars($demande['profession_mere'] ?: 'Non spécifiée'); ?>
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold">Téléphone des parents</label>
                        <div class="form-control-plaintext">
                            <?php echo htmlspecialchars($demande['telephone_parent'] ?: 'Non spécifié'); ?>
                        </div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold">Personne de contact</label>
                        <div class="form-control-plaintext">
                            <?php echo htmlspecialchars($demande['personne_contact'] ?: 'Non spécifiée'); ?>
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold">Téléphone de contact</label>
                        <div class="form-control-plaintext">
                            <?php echo htmlspecialchars($demande['telephone_contact'] ?: 'Non spécifié'); ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Informations scolaires -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-graduation-cap me-2"></i>
                    Informations Scolaires
                </h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold">Classe demandée</label>
                        <div class="form-control-plaintext">
                            <?php if ($demande['classe_nom']): ?>
                                <?php echo htmlspecialchars($demande['classe_nom'] . ' (' . $demande['classe_niveau'] . ')'); ?>
                            <?php else: ?>
                                <span class="text-muted">Non spécifiée</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold">Année scolaire</label>
                        <div class="form-control-plaintext">
                            <?php echo htmlspecialchars($demande['annee_scolaire_nom'] ?: 'Non spécifiée'); ?>
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold">École précédente</label>
                        <div class="form-control-plaintext">
                            <?php echo htmlspecialchars($demande['ecole_precedente'] ?: 'Non spécifiée'); ?>
                        </div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold">Classe précédente</label>
                        <div class="form-control-plaintext">
                            <?php echo htmlspecialchars($demande['classe_precedente'] ?: 'Non spécifiée'); ?>
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold">Année précédente</label>
                        <div class="form-control-plaintext">
                            <?php echo htmlspecialchars($demande['annee_precedente'] ?: 'Non spécifiée'); ?>
                        </div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold">Moyenne précédente</label>
                        <div class="form-control-plaintext">
                            <?php echo htmlspecialchars($demande['moyenne_precedente'] ?: 'Non spécifiée'); ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Motif et observations -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-comment me-2"></i>
                    Motif et Observations
                </h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-12 mb-3">
                        <label class="form-label fw-bold">Motif de la demande</label>
                        <div class="form-control-plaintext">
                            <?php echo htmlspecialchars($demande['motif_demande'] ?: 'Non spécifié'); ?>
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-12 mb-3">
                        <label class="form-label fw-bold">Observations</label>
                        <div class="form-control-plaintext">
                            <?php echo htmlspecialchars($demande['observations'] ?: 'Aucune observation'); ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Sidebar avec informations complémentaires -->
    <div class="col-lg-4">
        <!-- Statut et priorité -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-info-circle me-2"></i>
                    Statut et Priorité
                </h5>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <label class="form-label fw-bold">Statut</label>
                    <div class="d-grid">
                        <span class="badge <?php echo $status_class; ?> fs-6 py-2">
                            <i class="fas fa-<?php echo $status_icon; ?> me-1"></i>
                            <?php echo $status_text; ?>
                        </span>
                    </div>
                </div>
                
                <div class="mb-3">
                    <label class="form-label fw-bold">Priorité</label>
                    <div class="form-control-plaintext">
                        <?php
                        $priorite_class = $demande['priorite'] === 'haute' ? 'text-danger' : 
                                        ($demande['priorite'] === 'normale' ? 'text-primary' : 'text-success');
                        ?>
                        <span class="<?php echo $priorite_class; ?> fw-bold">
                            <?php echo ucfirst($demande['priorite']); ?>
                        </span>
                    </div>
                </div>
                
                <div class="mb-3">
                    <label class="form-label fw-bold">Date de création</label>
                    <div class="form-control-plaintext">
                        <?php echo date('d/m/Y H:i', strtotime($demande['created_at'])); ?>
                    </div>
                </div>
                
                <?php if ($demande['updated_at']): ?>
                <div class="mb-3">
                    <label class="form-label fw-bold">Dernière modification</label>
                    <div class="form-control-plaintext">
                        <?php echo date('d/m/Y H:i', strtotime($demande['updated_at'])); ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Informations financières -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-money-bill me-2"></i>
                    Informations Financières
                </h5>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <label class="form-label fw-bold">Frais d'inscription</label>
                    <div class="form-control-plaintext">
                        <?php echo number_format($demande['frais_inscription'], 2, ',', ' '); ?> FC
                    </div>
                </div>
                
                <div class="mb-3">
                    <label class="form-label fw-bold">Frais de scolarité</label>
                    <div class="form-control-plaintext">
                        <?php echo number_format($demande['frais_scolarite'], 2, ',', ' '); ?> FC
                    </div>
                </div>
                
                <div class="mb-3">
                    <label class="form-label fw-bold">Réduction accordée</label>
                    <div class="form-control-plaintext">
                        <?php echo number_format($demande['reduction_accordee'], 2, ',', ' '); ?> FC
                    </div>
                </div>
            </div>
        </div>

        <!-- Élève créé -->
        <?php if ($demande['eleve_cree_id']): ?>
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-user-check me-2"></i>
                    Élève Créé
                </h5>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <label class="form-label fw-bold">Numéro d'élève</label>
                    <div class="form-control-plaintext">
                        <span class="badge bg-success"><?php echo htmlspecialchars($demande['numero_eleve']); ?></span>
                    </div>
                </div>
                
                <div class="mb-3">
                    <label class="form-label fw-bold">Numéro de matricule</label>
                    <div class="form-control-plaintext">
                        <span class="badge bg-info"><?php echo htmlspecialchars($demande['numero_matricule']); ?></span>
                    </div>
                </div>
                
                <div class="mb-3">
                    <label class="form-label fw-bold">Statut de l'élève</label>
                    <div class="form-control-plaintext">
                        <?php
                        $eleve_status_class = '';
                        $eleve_status_text = '';
                        switch ($demande['eleve_status']) {
                            case 'non-evalué':
                                $eleve_status_class = 'bg-warning';
                                $eleve_status_text = 'Non évalué';
                                break;
                            case 'actif':
                                $eleve_status_class = 'bg-success';
                                $eleve_status_text = 'Actif';
                                break;
                            default:
                                $eleve_status_class = 'bg-secondary';
                                $eleve_status_text = ucfirst($demande['eleve_status']);
                        }
                        ?>
                        <span class="badge <?php echo $eleve_status_class; ?>">
                            <?php echo $eleve_status_text; ?>
                        </span>
                    </div>
                </div>
                
                <div class="d-grid">
                    <a href="../students/view.php?id=<?php echo $demande['eleve_cree_id']; ?>" 
                       class="btn btn-outline-primary">
                        <i class="fas fa-eye me-1"></i>
                        Voir l'élève
                    </a>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Actions rapides -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-bolt me-2"></i>
                    Actions Rapides
                </h5>
            </div>
            <div class="card-body">
                <div class="d-grid gap-2">
                    <a href="edit.php?id=<?php echo $demande_id; ?>" class="btn btn-warning">
                        <i class="fas fa-edit me-1"></i>
                        Modifier
                    </a>
                    
                    <a href="print.php?id=<?php echo $demande_id; ?>" class="btn btn-info" target="_blank">
                        <i class="fas fa-print me-1"></i>
                        Imprimer
                    </a>
                    
                    <?php if ($demande['status'] === 'en_cours_traitement'): ?>
                        <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#evaluationModal">
                            <i class="fas fa-clipboard-check me-1"></i>
                            Évaluer
                        </button>
                    <?php endif; ?>
                    
                    <a href="list.php" class="btn btn-outline-secondary">
                        <i class="fas fa-list me-1"></i>
                        Liste des demandes
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Historique des actions -->
<?php if (!empty($historique)): ?>
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-history me-2"></i>
                    Historique des Actions
                </h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Utilisateur</th>
                                <th>Action</th>
                                <th>Détails</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($historique as $action): ?>
                                <tr>
                                    <td><?php echo date('d/m/Y H:i', strtotime($action['created_at'])); ?></td>
                                    <td><?php echo htmlspecialchars($action['user_name']); ?></td>
                                    <td>
                                        <span class="badge bg-info"><?php echo htmlspecialchars($action['action']); ?></span>
                                    </td>
                                    <td><?php echo htmlspecialchars($action['details']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Modal d'évaluation -->
<?php if ($demande['status'] === 'en_cours_traitement'): ?>
<div class="modal fade" id="evaluationModal" tabindex="-1" aria-labelledby="evaluationModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="evaluationModalLabel">
                    <i class="fas fa-clipboard-check me-2"></i>
                    Évaluation de la Demande d'Admission
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="evaluate.php" method="POST">
                <div class="modal-body">
                    <input type="hidden" name="demande_id" value="<?php echo $demande_id; ?>">
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="note_evaluation" class="form-label">Note d'évaluation (sur 20)</label>
                            <input type="number" class="form-control" id="note_evaluation" name="note_evaluation" 
                                   min="0" max="20" step="0.5" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="status" class="form-label">Décision</label>
                            <select class="form-select" id="status" name="status" required>
                                <option value="">Sélectionner...</option>
                                <option value="acceptee">Acceptée</option>
                                <option value="refusee">Refusée</option>
                                <option value="en_attente">En attente</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="commentaire_evaluation" class="form-label">Commentaire d'évaluation</label>
                        <textarea class="form-control" id="commentaire_evaluation" name="commentaire_evaluation" 
                                  rows="3" required></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label for="recommandation" class="form-label">Recommandation</label>
                        <textarea class="form-control" id="recommandation" name="recommandation" 
                                  rows="2"></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label for="date_entretien" class="form-label">Date d'entretien (si applicable)</label>
                        <input type="datetime-local" class="form-control" id="date_entretien" name="date_entretien">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-save me-1"></i>
                        Enregistrer l'évaluation
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
// Initialisation des tooltips Bootstrap
var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
    return new bootstrap.Tooltip(tooltipTriggerEl);
});

// Validation du formulaire d'évaluation
document.getElementById('status').addEventListener('change', function() {
    const noteField = document.getElementById('note_evaluation');
    const commentField = document.getElementById('commentaire_evaluation');
    
    if (this.value === 'acceptee' || this.value === 'refusee') {
        noteField.required = true;
        commentField.required = true;
    } else {
        noteField.required = false;
        commentField.required = false;
    }
});
</script>

<?php include '../../../includes/footer.php'; ?>

