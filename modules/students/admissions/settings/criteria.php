<?php
/**
 * Module Admissions - Critères d'admission
 * Application de gestion scolaire - République Démocratique du Congo
 */

require_once '../../../../config/config.php';
require_once '../../../../config/database.php';
require_once '../../../../includes/functions.php';

// Vérifier l'authentification et les permissions
requireLogin();
if (!checkPermission('students') && !checkPermission('admin')) {
    showMessage('error', 'Accès refusé à cette fonctionnalité.');
    redirectTo('../../index.php');
}

$page_title = 'Critères d\'admission';

// Récupérer l'année scolaire active
$current_year = getCurrentAcademicYear();

if (!$current_year) {
    showMessage('error', 'Aucune année scolaire active.');
    redirectTo('../../index.php');
}

// Traitement des actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = sanitizeInput($_POST['action'] ?? '');
    
    if ($action === 'save_criteria') {
        try {
            $database->beginTransaction();
            
            // Supprimer les anciens critères
            $database->query(
                "DELETE FROM criteres_admission WHERE annee_scolaire_id = ?",
                [$current_year['id']]
            );
            
            // Sauvegarder les nouveaux critères
            $niveaux = ['maternelle', 'primaire', 'secondaire', 'superieur'];
            
            foreach ($niveaux as $niveau) {
                $age_min = (int)($_POST["age_min_{$niveau}"] ?? 0);
                $age_max = (int)($_POST["age_max_{$niveau}"] ?? 0);
                $capacite_max = (int)($_POST["capacite_max_{$niveau}"] ?? 0);
                $note_min = (float)($_POST["note_min_{$niveau}"] ?? 0);
                $documents_requis = sanitizeInput($_POST["documents_requis_{$niveau}"] ?? '');
                $conditions_speciales = sanitizeInput($_POST["conditions_speciales_{$niveau}"] ?? '');
                $actif = isset($_POST["actif_{$niveau}"]) ? 1 : 0;
                
                $database->query(
                    "INSERT INTO criteres_admission (
                        annee_scolaire_id, niveau, age_min, age_max, capacite_max, 
                        note_min, documents_requis, conditions_speciales, actif, created_at
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())",
                    [
                        $current_year['id'], $niveau, $age_min, $age_max, $capacite_max,
                        $note_min, $documents_requis, $conditions_speciales, $actif
                    ]
                );
            }
            
            // Sauvegarder les critères par classe
            $classes = $database->query(
                "SELECT id, nom, niveau FROM classes WHERE annee_scolaire_id = ?",
                [$current_year['id']]
            )->fetchAll();
            
            foreach ($classes as $classe) {
                $capacite_classe = (int)($_POST["capacite_classe_{$classe['id']}"] ?? 0);
                $note_min_classe = (float)($_POST["note_min_classe_{$classe['id']}"] ?? 0);
                $actif_classe = isset($_POST["actif_classe_{$classe['id']}"]) ? 1 : 0;
                
                $database->query(
                    "INSERT INTO criteres_admission_classes (
                        annee_scolaire_id, classe_id, capacite_max, note_min, actif, created_at
                    ) VALUES (?, ?, ?, ?, ?, NOW())",
                    [$current_year['id'], $classe['id'], $capacite_classe, $note_min_classe, $actif_classe]
                );
            }
            
            $database->commit();
            showMessage('success', 'Critères d\'admission sauvegardés avec succès.');
            redirectTo('criteria.php');
            
        } catch (Exception $e) {
            $database->rollback();
            showMessage('error', 'Erreur lors de la sauvegarde des critères : ' . $e->getMessage());
        }
    }
}

// Récupérer les critères existants
$criteres_niveaux = $database->query(
    "SELECT * FROM criteres_admission WHERE annee_scolaire_id = ? ORDER BY niveau",
    [$current_year['id']]
)->fetchAll();

$criteres_classes = $database->query(
    "SELECT cac.*, c.nom as classe_nom, c.niveau 
     FROM criteres_admission_classes cac
     JOIN classes c ON cac.classe_id = c.id
     WHERE cac.annee_scolaire_id = ?
     ORDER BY c.niveau, c.nom",
    [$current_year['id']]
)->fetchAll();

// Organiser les critères par niveau
$criteres_par_niveau = [];
foreach ($criteres_niveaux as $critere) {
    $criteres_par_niveau[$critere['niveau']] = $critere;
}

// Organiser les critères par classe
$criteres_par_classe = [];
foreach ($criteres_classes as $critere) {
    $criteres_par_classe[$critere['classe_id']] = $critere;
}

// Récupérer les classes
$classes = $database->query(
    "SELECT id, nom, niveau FROM classes WHERE annee_scolaire_id = ? ORDER BY niveau, nom",
    [$current_year['id']]
)->fetchAll();

// Statistiques d'admission actuelles
$stats_admission = [];
$niveaux = ['maternelle', 'primaire', 'secondaire', 'superieur'];

foreach ($niveaux as $niveau) {
    $stats = $database->query(
        "SELECT 
            COUNT(*) as total_demandes,
            COUNT(CASE WHEN status = 'acceptee' THEN 1 END) as acceptees,
            COUNT(CASE WHEN status = 'refusee' THEN 1 END) as refusees,
            COUNT(CASE WHEN status = 'en_attente' THEN 1 END) as en_attente
         FROM demandes_admission da
         LEFT JOIN classes c ON da.classe_demandee_id = c.id
         WHERE da.annee_scolaire_id = ? AND c.niveau = ?",
        [$current_year['id'], $niveau]
    )->fetch();
    
    $stats_admission[$niveau] = $stats;
}

include '../../../../includes/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">
        <i class="fas fa-cogs me-2"></i>
        Critères d'admission
    </h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <div class="btn-group me-2">
            <a href="../index.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left me-1"></i>
                Retour aux admissions
            </a>
        </div>
        <div class="btn-group">
            <a href="../reports/admission-stats.php" class="btn btn-outline-info">
                <i class="fas fa-chart-bar me-1"></i>
                Voir les statistiques
            </a>
        </div>
    </div>
</div>

<!-- Statistiques actuelles -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-chart-pie me-2"></i>
                    Statistiques d'admission actuelles
                </h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <?php foreach ($niveaux as $niveau): ?>
                        <div class="col-md-3">
                            <div class="card text-center">
                                <div class="card-body">
                                    <h6 class="card-title text-uppercase"><?php echo ucfirst($niveau); ?></h6>
                                    <div class="row">
                                        <div class="col-6">
                                            <h4 class="text-primary"><?php echo $stats_admission[$niveau]['total_demandes']; ?></h4>
                                            <small>Total</small>
                                        </div>
                                        <div class="col-6">
                                            <h4 class="text-success"><?php echo $stats_admission[$niveau]['acceptees']; ?></h4>
                                            <small>Acceptées</small>
                                        </div>
                                    </div>
                                    <div class="mt-2">
                                        <span class="badge bg-warning"><?php echo $stats_admission[$niveau]['en_attente']; ?> en attente</span>
                                        <span class="badge bg-danger"><?php echo $stats_admission[$niveau]['refusees']; ?> refusées</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Formulaire des critères -->
<form method="POST" action="">
    <input type="hidden" name="action" value="save_criteria">
    
    <!-- Critères par niveau -->
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="mb-0">
                <i class="fas fa-layer-group me-2"></i>
                Critères par niveau
            </h5>
        </div>
        <div class="card-body">
            <div class="row">
                <?php foreach ($niveaux as $niveau): ?>
                    <?php $critere = $criteres_par_niveau[$niveau] ?? []; ?>
                    <div class="col-md-6 mb-4">
                        <div class="card">
                            <div class="card-header">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="actif_<?php echo $niveau; ?>" 
                                           name="actif_<?php echo $niveau; ?>" 
                                           <?php echo ($critere['actif'] ?? 0) ? 'checked' : ''; ?>>
                                    <label class="form-check-label fw-bold" for="actif_<?php echo $niveau; ?>">
                                        <?php echo ucfirst($niveau); ?>
                                    </label>
                                </div>
                            </div>
                            <div class="card-body">
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label for="age_min_<?php echo $niveau; ?>" class="form-label">Âge minimum</label>
                                        <input type="number" class="form-control" id="age_min_<?php echo $niveau; ?>" 
                                               name="age_min_<?php echo $niveau; ?>" 
                                               value="<?php echo $critere['age_min'] ?? ''; ?>" min="0" max="25">
                                    </div>
                                    <div class="col-md-6">
                                        <label for="age_max_<?php echo $niveau; ?>" class="form-label">Âge maximum</label>
                                        <input type="number" class="form-control" id="age_max_<?php echo $niveau; ?>" 
                                               name="age_max_<?php echo $niveau; ?>" 
                                               value="<?php echo $critere['age_max'] ?? ''; ?>" min="0" max="25">
                                    </div>
                                    <div class="col-md-6">
                                        <label for="capacite_max_<?php echo $niveau; ?>" class="form-label">Capacité maximale</label>
                                        <input type="number" class="form-control" id="capacite_max_<?php echo $niveau; ?>" 
                                               name="capacite_max_<?php echo $niveau; ?>" 
                                               value="<?php echo $critere['capacite_max'] ?? ''; ?>" min="0">
                                    </div>
                                    <div class="col-md-6">
                                        <label for="note_min_<?php echo $niveau; ?>" class="form-label">Note minimale</label>
                                        <input type="number" class="form-control" id="note_min_<?php echo $niveau; ?>" 
                                               name="note_min_<?php echo $niveau; ?>" 
                                               value="<?php echo $critere['note_min'] ?? ''; ?>" min="0" max="20" step="0.1">
                                    </div>
                                    <div class="col-12">
                                        <label for="documents_requis_<?php echo $niveau; ?>" class="form-label">Documents requis</label>
                                        <textarea class="form-control" id="documents_requis_<?php echo $niveau; ?>" 
                                                  name="documents_requis_<?php echo $niveau; ?>" rows="2"
                                                  placeholder="Liste des documents requis (séparés par des virgules)"><?php echo htmlspecialchars($critere['documents_requis'] ?? ''); ?></textarea>
                                    </div>
                                    <div class="col-12">
                                        <label for="conditions_speciales_<?php echo $niveau; ?>" class="form-label">Conditions spéciales</label>
                                        <textarea class="form-control" id="conditions_speciales_<?php echo $niveau; ?>" 
                                                  name="conditions_speciales_<?php echo $niveau; ?>" rows="2"
                                                  placeholder="Conditions spéciales d'admission"><?php echo htmlspecialchars($critere['conditions_speciales'] ?? ''); ?></textarea>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Critères par classe -->
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="mb-0">
                <i class="fas fa-graduation-cap me-2"></i>
                Critères par classe
            </h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Classe</th>
                            <th>Niveau</th>
                            <th>Capacité maximale</th>
                            <th>Note minimale</th>
                            <th>Actif</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($classes as $classe): ?>
                            <?php $critere_classe = $criteres_par_classe[$classe['id']] ?? []; ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($classe['nom']); ?></strong>
                                </td>
                                <td>
                                    <span class="badge bg-info"><?php echo ucfirst($classe['niveau']); ?></span>
                                </td>
                                <td>
                                    <input type="number" class="form-control form-control-sm" 
                                           name="capacite_classe_<?php echo $classe['id']; ?>" 
                                           value="<?php echo $critere_classe['capacite_max'] ?? ''; ?>" 
                                           min="0" style="width: 100px;">
                                </td>
                                <td>
                                    <input type="number" class="form-control form-control-sm" 
                                           name="note_min_classe_<?php echo $classe['id']; ?>" 
                                           value="<?php echo $critere_classe['note_min'] ?? ''; ?>" 
                                           min="0" max="20" step="0.1" style="width: 100px;">
                                </td>
                                <td>
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" 
                                               name="actif_classe_<?php echo $classe['id']; ?>" 
                                               <?php echo ($critere_classe['actif'] ?? 0) ? 'checked' : ''; ?>>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Paramètres généraux -->
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="mb-0">
                <i class="fas fa-sliders-h me-2"></i>
                Paramètres généraux
            </h5>
        </div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-6">
                    <label for="delai_traitement" class="form-label">Délai de traitement (jours)</label>
                    <input type="number" class="form-control" id="delai_traitement" name="delai_traitement" 
                           value="<?php echo getSetting('delai_traitement_admission', 7); ?>" min="1" max="30">
                    <small class="form-text text-muted">Délai maximum pour traiter une demande d'admission</small>
                </div>
                <div class="col-md-6">
                    <label for="auto_refus" class="form-label">Refus automatique après (jours)</label>
                    <input type="number" class="form-control" id="auto_refus" name="auto_refus" 
                           value="<?php echo getSetting('auto_refus_admission', 30); ?>" min="1" max="90">
                    <small class="form-text text-muted">Refus automatique si pas de réponse dans ce délai</small>
                </div>
                <div class="col-md-6">
                    <label for="notifications_email" class="form-label">Notifications par email</label>
                    <select class="form-select" id="notifications_email" name="notifications_email">
                        <option value="1" <?php echo getSetting('notifications_email_admission', 1) ? 'selected' : ''; ?>>Activées</option>
                        <option value="0" <?php echo !getSetting('notifications_email_admission', 1) ? 'selected' : ''; ?>>Désactivées</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <label for="validation_auto" class="form-label">Validation automatique</label>
                    <select class="form-select" id="validation_auto" name="validation_auto">
                        <option value="0" <?php echo !getSetting('validation_auto_admission', 0) ? 'selected' : ''; ?>>Manuelle</option>
                        <option value="1" <?php echo getSetting('validation_auto_admission', 0) ? 'selected' : ''; ?>>Automatique</option>
                    </select>
                    <small class="form-text text-muted">Validation automatique si tous les critères sont remplis</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Boutons d'action -->
    <div class="card mb-4">
        <div class="card-body">
            <div class="d-flex justify-content-between">
                <div>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-1"></i>
                        Sauvegarder les critères
                    </button>
                    <a href="?" class="btn btn-outline-secondary">
                        <i class="fas fa-times me-1"></i>
                        Annuler
                    </a>
                </div>
                <div>
                    <a href="../reports/admission-stats.php" class="btn btn-outline-info">
                        <i class="fas fa-chart-bar me-1"></i>
                        Voir l'impact
                    </a>
                </div>
            </div>
        </div>
    </div>
</form>

<!-- Aide et informations -->
<div class="card">
    <div class="card-header">
        <h5 class="mb-0">
            <i class="fas fa-question-circle me-2"></i>
            Aide et informations
        </h5>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-6">
                <h6>Critères par niveau</h6>
                <ul class="list-unstyled">
                    <li><strong>Âge min/max :</strong> Limites d'âge pour l'admission</li>
                    <li><strong>Capacité max :</strong> Nombre maximum d'élèves par niveau</li>
                    <li><strong>Note min :</strong> Note minimale requise pour l'admission</li>
                    <li><strong>Documents :</strong> Documents obligatoires pour l'inscription</li>
                </ul>
            </div>
            <div class="col-md-6">
                <h6>Critères par classe</h6>
                <ul class="list-unstyled">
                    <li><strong>Capacité max :</strong> Nombre maximum d'élèves par classe</li>
                    <li><strong>Note min :</strong> Note minimale spécifique à la classe</li>
                    <li><strong>Actif :</strong> Active/désactive les critères pour cette classe</li>
                </ul>
            </div>
        </div>
        <hr>
        <div class="alert alert-info">
            <i class="fas fa-info-circle me-2"></i>
            <strong>Note :</strong> Les critères d'admission s'appliquent automatiquement lors du traitement des demandes. 
            Les demandes qui ne respectent pas ces critères seront automatiquement refusées si la validation automatique est activée.
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Validation des formulaires
    const form = document.querySelector('form');
    form.addEventListener('submit', function(e) {
        let isValid = true;
        
        // Vérifier que les âges min sont inférieurs aux âges max
        const niveaux = ['maternelle', 'primaire', 'secondaire', 'superieur'];
        niveaux.forEach(function(niveau) {
            const ageMin = parseInt(document.getElementById('age_min_' + niveau).value) || 0;
            const ageMax = parseInt(document.getElementById('age_max_' + niveau).value) || 0;
            
            if (ageMin > 0 && ageMax > 0 && ageMin >= ageMax) {
                alert('L\'âge minimum doit être inférieur à l\'âge maximum pour le niveau ' + niveau);
                isValid = false;
            }
        });
        
        if (!isValid) {
            e.preventDefault();
        }
    });
    
    // Activer/désactiver les champs selon l'état du switch
    const switches = document.querySelectorAll('.form-check-input');
    switches.forEach(function(switchEl) {
        switchEl.addEventListener('change', function() {
            const card = this.closest('.card');
            const inputs = card.querySelectorAll('input:not(.form-check-input), textarea, select');
            inputs.forEach(function(input) {
                input.disabled = !this.checked;
            }.bind(this));
        });
        
        // Déclencher l'événement au chargement
        switchEl.dispatchEvent(new Event('change'));
    });
});
</script>

<?php include '../../../../includes/footer.php'; ?>
