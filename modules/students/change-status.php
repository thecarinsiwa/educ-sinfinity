<?php
/**
 * Module Changement de Statut des Inscriptions
 * Application de gestion scolaire - République Démocratique du Congo
 */

require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../includes/functions.php';
require_once '../../includes/permissions-pages.php';

// Vérifier l'authentification et les permissions
requireLogin();
requirePagePermissionFromDB('students', 'change-status', 'edit', '../../dashboard.php');

$page_title = 'Changement de Statut';

// Récupérer les paramètres
$inscription_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$nouveau_statut = isset($_GET['status']) ? $_GET['status'] : '';

// Valider les paramètres
if ($inscription_id <= 0 || empty($nouveau_statut)) {
    showMessage('error', 'Paramètres invalides.');
    redirectTo('enrollment-history.php');
}

// Valider le statut
$statuts_valides = ['transfere', 'abandonne'];
if (!in_array($nouveau_statut, $statuts_valides)) {
    showMessage('error', 'Statut invalide.');
    redirectTo('enrollment-history.php');
}

// Récupérer les informations de l'inscription
$inscription = null;
try {
    $stmt = $database->query(
        "SELECT i.*, e.nom, e.prenom, e.numero_matricule, c.nom as classe_nom, a.annee
         FROM inscriptions i 
         JOIN eleves e ON i.eleve_id = e.id 
         JOIN classes c ON i.classe_id = c.id 
         JOIN annees_scolaires a ON i.annee_scolaire_id = a.id 
         WHERE i.id = ? AND i.status = 'inscrit'",
        [$inscription_id]
    );
    $inscription = $stmt->fetch();
} catch (Exception $e) {
    showMessage('error', 'Erreur lors de la récupération de l\'inscription: ' . $e->getMessage());
    redirectTo('enrollment-history.php');
}

if (!$inscription) {
    showMessage('error', 'Inscription non trouvée ou déjà modifiée.');
    redirectTo('enrollment-history.php');
}

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $motif = sanitizeInput($_POST['motif']);
    $date_effet = $_POST['date_effet'];
    $commentaire = sanitizeInput($_POST['commentaire']);
    
    if (empty($motif) || empty($date_effet)) {
        showMessage('error', 'Le motif et la date d\'effet sont obligatoires.');
    } else {
        try {
            // Démarrer une transaction
            $database->beginTransaction();
            
            // Mettre à jour le statut de l'inscription
            $database->query(
                "UPDATE inscriptions SET status = ?, updated_at = NOW() WHERE id = ?",
                [$nouveau_statut, $inscription_id]
            );
            
            // Mettre à jour le statut de l'élève si nécessaire
            if ($nouveau_statut === 'abandonne') {
                $database->query(
                    "UPDATE eleves SET status = 'abandonne', updated_at = NOW() WHERE id = ?",
                    [$inscription['eleve_id']]
                );
            } elseif ($nouveau_statut === 'transfere') {
                $database->query(
                    "UPDATE eleves SET status = 'transfere', updated_at = NOW() WHERE id = ?",
                    [$inscription['eleve_id']]
                );
            }
            
            // Enregistrer l'historique du changement de statut
            $database->query(
                "INSERT INTO historique_changements_statut (inscription_id, eleve_id, ancien_statut, nouveau_statut, motif, date_effet, commentaire, user_id, created_at) 
                 VALUES (?, ?, 'inscrit', ?, ?, ?, ?, ?, NOW())",
                [$inscription_id, $inscription['eleve_id'], $nouveau_statut, $motif, $date_effet, $commentaire, $_SESSION['user_id']]
            );
            
            // Valider la transaction
            $database->commit();
            
            // Logger l'action
            $statut_lisible = $nouveau_statut === 'transfere' ? 'transféré' : 'abandonné';
            logUserAction(
                'changement_statut_inscription', 
                'students', 
                "Changement de statut de l'élève {$inscription['prenom']} {$inscription['nom']} (ID: {$inscription['eleve_id']}) : $statut_lisible. Motif: $motif"
            );
            
            showMessage('success', "Le statut de l'élève a été modifié avec succès.");
            redirectTo('enrollment-history.php');
            
        } catch (Exception $e) {
            // Annuler la transaction en cas d'erreur
            $database->rollback();
            showMessage('error', 'Erreur lors de la modification du statut: ' . $e->getMessage());
        }
    }
}

include '../../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="page-title-box">
                <div class="page-title-right">
                    <ol class="breadcrumb m-0">
                        <li class="breadcrumb-item"><a href="../../dashboard.php">Tableau de bord</a></li>
                        <li class="breadcrumb-item"><a href="../students/">Gestion des Élèves</a></li>
                        <li class="breadcrumb-item"><a href="enrollment-history.php">Historique des Inscriptions</a></li>
                        <li class="breadcrumb-item active">Changement de Statut</li>
                    </ol>
                </div>
                <h4 class="page-title">Changement de Statut - Inscription</h4>
            </div>
        </div>
    </div>

    <?php displayMessage(); ?>

    <div class="row">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header">
                    <h4 class="header-title">
                        <i class="mdi mdi-account-convert me-2"></i>
                        Confirmation du Changement de Statut
                    </h4>
                    <p class="text-muted mb-0">
                        Vous êtes sur le point de modifier le statut de l'inscription de cet élève
                    </p>
                </div>
                <div class="card-body">
                    <!-- Informations de l'élève -->
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <h6 class="text-muted">Informations de l'Élève</h6>
                            <table class="table table-borderless">
                                <tr>
                                    <td><strong>Nom :</strong></td>
                                    <td><?php echo $inscription['prenom'] . ' ' . $inscription['nom']; ?></td>
                                </tr>
                                <tr>
                                    <td><strong>Matricule :</strong></td>
                                    <td><span class="badge bg-light text-dark"><?php echo $inscription['numero_matricule']; ?></span></td>
                                </tr>
                                <tr>
                                    <td><strong>Classe :</strong></td>
                                    <td><?php echo $inscription['classe_nom']; ?></td>
                                </tr>
                                <tr>
                                    <td><strong>Année Scolaire :</strong></td>
                                    <td><?php echo $inscription['annee']; ?></td>
                                </tr>
                            </table>
                        </div>
                        <div class="col-md-6">
                            <h6 class="text-muted">Nouveau Statut</h6>
                            <div class="alert alert-<?php echo $nouveau_statut === 'transfere' ? 'warning' : 'danger'; ?>">
                                <i class="mdi mdi-<?php echo $nouveau_statut === 'transfere' ? 'account-arrow-right' : 'account-remove'; ?> me-2"></i>
                                <strong>
                                    <?php echo $nouveau_statut === 'transfere' ? 'Transféré' : 'Abandonné'; ?>
                                </strong>
                            </div>
                            <p class="text-muted small">
                                <?php if ($nouveau_statut === 'transfere'): ?>
                                    L'élève sera marqué comme transféré vers un autre établissement.
                                <?php else: ?>
                                    L'élève sera marqué comme ayant abandonné ses études.
                                <?php endif; ?>
                            </p>
                        </div>
                    </div>

                    <!-- Formulaire de confirmation -->
                    <form method="POST">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="motif" class="form-label">Motif du changement *</label>
                                    <select class="form-select" id="motif" name="motif" required>
                                        <option value="">Sélectionner un motif</option>
                                        <?php if ($nouveau_statut === 'transfere'): ?>
                                            <option value="Transfert vers autre établissement">Transfert vers autre établissement</option>
                                            <option value="Déménagement de la famille">Déménagement de la famille</option>
                                            <option value="Changement de programme">Changement de programme</option>
                                            <option value="Raison personnelle">Raison personnelle</option>
                                            <option value="Autre">Autre</option>
                                        <?php else: ?>
                                            <option value="Abandon des études">Abandon des études</option>
                                            <option value="Problèmes financiers">Problèmes financiers</option>
                                            <option value="Problèmes de santé">Problèmes de santé</option>
                                            <option value="Problèmes familiaux">Problèmes familiaux</option>
                                            <option value="Autre">Autre</option>
                                        <?php endif; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="date_effet" class="form-label">Date d'effet *</label>
                                    <input type="date" class="form-control" id="date_effet" name="date_effet" 
                                           value="<?php echo date('Y-m-d'); ?>" required>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="commentaire" class="form-label">Commentaire additionnel</label>
                            <textarea class="form-control" id="commentaire" name="commentaire" rows="3" 
                                      placeholder="Précisions supplémentaires sur le changement de statut..."></textarea>
                        </div>
                        
                        <div class="alert alert-warning">
                            <i class="mdi mdi-alert-outline me-2"></i>
                            <strong>Attention :</strong> Cette action est irréversible. Le changement de statut sera enregistré dans l'historique et pourra affecter les rapports et statistiques.
                        </div>
                        
                        <div class="d-flex justify-content-between">
                            <a href="enrollment-history.php" class="btn btn-secondary">
                                <i class="mdi mdi-arrow-left me-1"></i>
                                Annuler
                            </a>
                            <button type="submit" class="btn btn-<?php echo $nouveau_statut === 'transfere' ? 'warning' : 'danger'; ?>">
                                <i class="mdi mdi-check me-1"></i>
                                Confirmer le Changement
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
        <div class="col-lg-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="header-title">
                        <i class="mdi mdi-information-outline me-2"></i>
                        Informations Importantes
                    </h5>
                </div>
                <div class="card-body">
                    <h6>Conséquences du changement :</h6>
                    <ul class="list-unstyled">
                        <?php if ($nouveau_statut === 'transfere'): ?>
                            <li class="mb-2">
                                <i class="mdi mdi-check-circle text-success me-2"></i>
                                L'élève ne sera plus compté dans les effectifs actifs
                            </li>
                            <li class="mb-2">
                                <i class="mdi mdi-check-circle text-success me-2"></i>
                                Son dossier reste accessible pour consultation
                            </li>
                            <li class="mb-2">
                                <i class="mdi mdi-check-circle text-success me-2"></i>
                                Possibilité de réinscription ultérieure
                            </li>
                        <?php else: ?>
                            <li class="mb-2">
                                <i class="mdi mdi-check-circle text-success me-2"></i>
                                L'élève ne sera plus compté dans les effectifs actifs
                            </li>
                            <li class="mb-2">
                                <i class="mdi mdi-check-circle text-success me-2"></i>
                                Son dossier reste accessible pour consultation
                            </li>
                            <li class="mb-2">
                                <i class="mdi mdi-alert-circle text-warning me-2"></i>
                                Réinscription possible après justification
                            </li>
                        <?php endif; ?>
                    </ul>
                    
                    <hr>
                    
                    <h6>Actions possibles après :</h6>
                    <ul class="list-unstyled">
                        <li class="mb-2">
                            <i class="mdi mdi-account-plus text-info me-2"></i>
                            Réinscription pour l'année suivante
                        </li>
                        <li class="mb-2">
                            <i class="mdi mdi-file-document text-info me-2"></i>
                            Génération de certificat de sortie
                        </li>
                        <li class="mb-2">
                            <i class="mdi mdi-chart-line text-info me-2"></i>
                            Mise à jour des statistiques
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Validation du formulaire
    const form = document.querySelector('form');
    const motifSelect = document.getElementById('motif');
    const dateEffetInput = document.getElementById('date_effet');
    
    form.addEventListener('submit', function(e) {
        if (!motifSelect.value) {
            e.preventDefault();
            alert('Veuillez sélectionner un motif pour le changement de statut.');
            motifSelect.focus();
            return;
        }
        
        if (!dateEffetInput.value) {
            e.preventDefault();
            alert('Veuillez sélectionner une date d\'effet.');
            dateEffetInput.focus();
            return;
        }
        
        // Confirmation finale
        const statut = '<?php echo $nouveau_statut === 'transfere' ? 'transféré' : 'abandonné'; ?>';
        const nomEleve = '<?php echo addslashes($inscription['prenom'] . ' ' . $inscription['nom']); ?>';
        
        if (!confirm(`Êtes-vous absolument sûr de vouloir marquer l'élève ${nomEleve} comme ${statut} ?\n\nCette action est irréversible.`)) {
            e.preventDefault();
        }
    });
    
    // Validation de la date d'effet
    dateEffetInput.addEventListener('change', function() {
        const selectedDate = new Date(this.value);
        const today = new Date();
        
        if (selectedDate > today) {
            alert('La date d\'effet ne peut pas être dans le futur.');
            this.value = today.toISOString().split('T')[0];
        }
    });
});
</script>

<?php include '../../includes/footer.php'; ?>
