<?php
/**
 * Évaluation d'une demande d'admission
 * Application de gestion scolaire - République Démocratique du Congo
 */

require_once '../../../config/config.php';
require_once '../../../config/database.php';
require_once '../../../includes/functions.php';
require_once '../../../includes/permissions-pages.php';

// Vérifier l'authentification et les permissions
requireLogin();
requirePagePermissionFromDB('admissions', 'applications', 'update', '../../../dashboard.php');

$page_title = 'Évaluation de Demande d\'Admission';

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
            e.status as eleve_status, e.id as eleve_id
     FROM demandes_admission da 
     LEFT JOIN classes c ON da.classe_demandee_id = c.id 
     LEFT JOIN eleves e ON da.eleve_cree_id = e.id
     WHERE da.id = ?",
    [$demande_id]
)->fetch();

if (!$demande) {
    showMessage('error', 'Demande d\'admission non trouvée.');
    redirectTo('../index.php');
}

if ($demande['status'] !== 'en_cours_traitement') {
    showMessage('error', 'Cette demande ne peut plus être évaluée.');
    redirectTo('view.php?id=' . $demande_id);
}

$errors = [];
$success = false;

// Traitement du formulaire d'évaluation
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $note_evaluation = floatval($_POST['note_evaluation'] ?? 0);
    $status = sanitizeInput($_POST['status'] ?? '');
    $commentaire_evaluation = sanitizeInput($_POST['commentaire_evaluation'] ?? '');
    $recommandation = sanitizeInput($_POST['recommandation'] ?? '');
    $date_entretien = sanitizeInput($_POST['date_entretien'] ?? '');
    
    // Validation
    if (empty($status)) {
        $errors[] = 'Le statut est obligatoire.';
    }
    
    if (empty($commentaire_evaluation)) {
        $errors[] = 'Le commentaire d\'évaluation est obligatoire.';
    }
    
    if ($note_evaluation < 0 || $note_evaluation > 20) {
        $errors[] = 'La note doit être comprise entre 0 et 20.';
    }
    
    if (empty($errors)) {
        try {
            $database->beginTransaction();
            
            // Mettre à jour la demande d'admission
            $database->execute(
                "UPDATE demandes_admission SET 
                    status = ?, 
                    note_evaluation = ?, 
                    commentaire_evaluation = ?, 
                    recommandation = ?, 
                    date_entretien = ?, 
                    evalue_par = ?, 
                    date_evaluation = NOW(),
                    updated_at = NOW()
                 WHERE id = ?",
                [
                    $status, 
                    $note_evaluation, 
                    $commentaire_evaluation, 
                    $recommandation, 
                    $date_entretien ?: null, 
                    $_SESSION['user_id'], 
                    $demande_id
                ]
            );
            
            // Si la demande est acceptée et qu'un élève existe, changer son statut
            if ($status === 'acceptee' && $demande['eleve_id']) {
                $database->execute(
                    "UPDATE eleves SET status = 'actif', updated_at = NOW() WHERE id = ?",
                    [$demande['eleve_id']]
                );
                
                // Logger l'action
                logAction('demandes_admission', $demande_id, 'evaluation_acceptee', 
                         "Demande acceptée - Note: $note_evaluation/20 - Élève activé");
            } else {
                // Logger l'action
                logAction('demandes_admission', $demande_id, 'evaluation_' . $status, 
                         "Demande évaluée - Note: $note_evaluation/20 - Statut: $status");
            }
            
            $database->commit();
            
            showMessage('success', 'Évaluation enregistrée avec succès !');
            redirectTo('view.php?id=' . $demande_id);
            
        } catch (Exception $e) {
            $database->rollback();
            $errors[] = 'Erreur lors de l\'enregistrement : ' . $e->getMessage();
        }
    }
}

include '../../../includes/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">
        <i class="fas fa-clipboard-check me-2"></i>
        Évaluation de Demande d'Admission
        <span class="badge bg-primary ms-2"><?php echo htmlspecialchars($demande['numero_demande']); ?></span>
    </h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <a href="view.php?id=<?php echo $demande_id; ?>" class="btn btn-outline-secondary me-2">
            <i class="fas fa-arrow-left me-1"></i>
            Retour
        </a>
        <a href="../index.php" class="btn btn-outline-primary">
            <i class="fas fa-home me-1"></i>
            Accueil
        </a>
    </div>
</div>

<!-- Informations de la demande -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-info-circle me-2"></i>
                    Informations de la Demande
                </h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <strong>Élève :</strong> 
                        <?php echo htmlspecialchars($demande['nom_eleve'] . ' ' . $demande['prenom_eleve']); ?>
                    </div>
                    <div class="col-md-6">
                        <strong>Classe demandée :</strong> 
                        <?php if ($demande['classe_nom']): ?>
                            <?php echo htmlspecialchars($demande['classe_nom'] . ' (' . $demande['classe_niveau'] . ')'); ?>
                        <?php else: ?>
                            <span class="text-muted">Non spécifiée</span>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="row mt-2">
                    <div class="col-md-6">
                        <strong>Date de naissance :</strong> 
                        <?php echo date('d/m/Y', strtotime($demande['date_naissance'])); ?>
                        (<?php echo date('Y') - date('Y', strtotime($demande['date_naissance'])); ?> ans)
                    </div>
                    <div class="col-md-6">
                        <strong>Statut actuel :</strong> 
                        <span class="badge bg-warning">En cours de traitement</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Formulaire d'évaluation -->
<div class="row">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-edit me-2"></i>
                    Formulaire d'Évaluation
                </h5>
            </div>
            <div class="card-body">
                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger">
                        <h6><i class="fas fa-exclamation-triangle me-2"></i>Erreurs détectées :</h6>
                        <ul class="mb-0">
                            <?php foreach ($errors as $error): ?>
                                <li><?php echo htmlspecialchars($error); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <form method="POST" class="needs-validation" novalidate>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="note_evaluation" class="form-label">
                                Note d'évaluation <span class="text-danger">*</span>
                            </label>
                            <div class="input-group">
                                <input type="number" 
                                       class="form-control" 
                                       id="note_evaluation" 
                                       name="note_evaluation" 
                                       min="0" 
                                       max="20" 
                                       step="0.5" 
                                       value="<?php echo htmlspecialchars($_POST['note_evaluation'] ?? ''); ?>"
                                       required>
                                <span class="input-group-text">/ 20</span>
                            </div>
                            <div class="form-text">
                                Note sur 20 points (0.5 par 0.5)
                            </div>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="status" class="form-label">
                                Décision <span class="text-danger">*</span>
                            </label>
                            <select class="form-select" id="status" name="status" required>
                                <option value="">Sélectionner une décision...</option>
                                <option value="acceptee" <?php echo ($_POST['status'] ?? '') === 'acceptee' ? 'selected' : ''; ?>>
                                    ✅ Acceptée
                                </option>
                                <option value="refusee" <?php echo ($_POST['status'] ?? '') === 'refusee' ? 'selected' : ''; ?>>
                                    ❌ Refusée
                                </option>
                                <option value="en_attente" <?php echo ($_POST['status'] ?? '') === 'en_attente' ? 'selected' : ''; ?>>
                                    ⏳ En attente
                                </option>
                            </select>
                            <div class="form-text">
                                Cette décision déterminera le statut final de la demande
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="commentaire_evaluation" class="form-label">
                            Commentaire d'évaluation <span class="text-danger">*</span>
                        </label>
                        <textarea class="form-control" 
                                  id="commentaire_evaluation" 
                                  name="commentaire_evaluation" 
                                  rows="4" 
                                  placeholder="Détaillez votre évaluation, points forts, points d'amélioration..."
                                  required><?php echo htmlspecialchars($_POST['commentaire_evaluation'] ?? ''); ?></textarea>
                        <div class="form-text">
                            Commentaire détaillé justifiant la décision
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="recommandation" class="form-label">Recommandation</label>
                        <textarea class="form-control" 
                                  id="recommandation" 
                                  name="recommandation" 
                                  rows="3" 
                                  placeholder="Recommandations pour l'élève, conditions d'admission..."><?php echo htmlspecialchars($_POST['recommandation'] ?? ''); ?></textarea>
                        <div class="form-text">
                            Recommandations optionnelles pour l'élève ou l'administration
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="date_entretien" class="form-label">Date d'entretien (si applicable)</label>
                        <input type="datetime-local" 
                               class="form-control" 
                               id="date_entretien" 
                               name="date_entretien"
                               value="<?php echo htmlspecialchars($_POST['date_entretien'] ?? ''); ?>">
                        <div class="form-text">
                            Date et heure de l'entretien si un entretien est prévu
                        </div>
                    </div>
                    
                    <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                        <a href="view.php?id=<?php echo $demande_id; ?>" class="btn btn-secondary me-md-2">
                            <i class="fas fa-times me-1"></i>
                            Annuler
                        </a>
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-save me-1"></i>
                            Enregistrer l'évaluation
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Sidebar avec informations et conseils -->
    <div class="col-lg-4">
        <!-- Informations sur l'évaluation -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-lightbulb me-2"></i>
                    Conseils d'Évaluation
                </h5>
            </div>
            <div class="card-body">
                <div class="alert alert-info">
                    <h6><i class="fas fa-info-circle me-2"></i>Critères d'évaluation :</h6>
                    <ul class="mb-0 small">
                        <li><strong>16-20 :</strong> Excellent - Acceptation recommandée</li>
                        <li><strong>12-15.5 :</strong> Bon - Acceptation possible</li>
                        <li><strong>8-11.5 :</strong> Moyen - Évaluation approfondie</li>
                        <li><strong>0-7.5 :</strong> Insuffisant - Refus probable</li>
                    </ul>
                </div>
                
                <div class="alert alert-warning">
                    <h6><i class="fas fa-exclamation-triangle me-2"></i>Points à vérifier :</h6>
                    <ul class="mb-0 small">
                        <li>Niveau scolaire antérieur</li>
                        <li>Motivation et comportement</li>
                        <li>Disponibilité des parents</li>
                        <li>Capacité financière</li>
                    </ul>
                </div>
            </div>
        </div>
        
        <!-- Impact de la décision -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-arrow-right me-2"></i>
                    Impact de la Décision
                </h5>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <h6 class="text-success">✅ Acceptée</h6>
                    <ul class="small text-muted">
                        <li>L'élève sera définitivement inscrit</li>
                        <li>Statut changé vers "actif"</li>
                        <li>Accès aux cours et activités</li>
                    </ul>
                </div>
                
                <div class="mb-3">
                    <h6 class="text-danger">❌ Refusée</h6>
                    <ul class="small text-muted">
                        <li>Demande fermée définitivement</li>
                        <li>L'élève ne sera pas inscrit</li>
                        <li>Possibilité de réapplique ultérieurement</li>
                    </ul>
                </div>
                
                <div class="mb-3">
                    <h6 class="text-info">⏳ En attente</h6>
                    <ul class="small text-muted">
                        <li>Demande suspendue temporairement</li>
                        <li>Évaluation complémentaire requise</li>
                        <li>Décision ultérieure nécessaire</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Validation du formulaire
document.querySelector('form').addEventListener('submit', function(e) {
    const noteField = document.getElementById('note_evaluation');
    const statusField = document.getElementById('status');
    const commentField = document.getElementById('commentaire_evaluation');
    
    let isValid = true;
    
    // Vérifier la note
    if (noteField.value < 0 || noteField.value > 20) {
        noteField.classList.add('is-invalid');
        isValid = false;
    } else {
        noteField.classList.remove('is-invalid');
    }
    
    // Vérifier le statut
    if (!statusField.value) {
        statusField.classList.add('is-invalid');
        isValid = false;
    } else {
        statusField.classList.remove('is-invalid');
    }
    
    // Vérifier le commentaire
    if (!commentField.value.trim()) {
        commentField.classList.add('is-invalid');
        isValid = false;
    } else {
        commentField.classList.remove('is-invalid');
    }
    
    if (!isValid) {
        e.preventDefault();
        showError('Veuillez corriger les erreurs dans le formulaire.');
    }
});

// Mise à jour dynamique des conseils selon la note
document.getElementById('note_evaluation').addEventListener('input', function() {
    const note = parseFloat(this.value);
    const statusField = document.getElementById('status');
    
    // Suggestions automatiques selon la note
    if (note >= 16) {
        statusField.value = 'acceptee';
    } else if (note >= 12) {
        statusField.value = 'acceptee';
    } else if (note >= 8) {
        statusField.value = 'en_attente';
    } else {
        statusField.value = 'refusee';
    }
});

// Confirmation avant soumission
document.querySelector('form').addEventListener('submit', function(e) {
    const status = document.getElementById('status').value;
    const note = document.getElementById('note_evaluation').value;
    
    if (status === 'refusee' && note >= 12) {
        if (!confirm('Attention : Vous allez refuser un élève avec une note de ' + note + '/20. Êtes-vous sûr de votre décision ?')) {
            e.preventDefault();
        }
    }
});
</script>

<?php include '../../../includes/footer.php'; ?>

