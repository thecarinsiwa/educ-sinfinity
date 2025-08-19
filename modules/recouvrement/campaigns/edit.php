<?php
/**
 * Module Recouvrement - Édition d'une campagne
 * Application de gestion scolaire - République Démocratique du Congo
 */

require_once '../../../config/config.php';
require_once '../../../config/database.php';
require_once '../../../includes/functions.php';

// Vérifier l'authentification et les permissions
requireLogin();
if (!checkPermission('recouvrement') && !checkPermission('recouvrement_view')) {
    showMessage('error', 'Accès refusé à ce module.');
    redirectTo('../../dashboard.php');
}

$page_title = 'Édition de campagne';

// Obtenir l'ID de la campagne
$campaign_id = (int)($_GET['id'] ?? 0);
if (!$campaign_id) {
    showMessage('error', 'ID de campagne manquant.');
    redirectTo('index.php');
}

// Obtenir l'année scolaire actuelle
$current_year = getCurrentAcademicYear();

// Récupérer les détails de la campagne
$campaign = $database->query(
    "SELECT 
        cr.*,
        u.nom as created_by_name,
        u.prenom as created_by_firstname
     FROM campagnes_recouvrement cr
     LEFT JOIN users u ON cr.created_by = u.id
     WHERE cr.id = ? AND cr.annee_scolaire_id = ?",
    [$campaign_id, $current_year['id']]
)->fetch();

if (!$campaign) {
    showMessage('error', 'Campagne non trouvée.');
    redirectTo('index.php');
}

$errors = [];
$success_message = '';

// Traitement du formulaire de mise à jour
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = sanitizeInput($_POST['action'] ?? '');
    
    if ($action === 'update_campaign') {
        try {
            $nom = sanitizeInput($_POST['nom']);
            $description = sanitizeInput($_POST['description']);
            $type_cible = sanitizeInput($_POST['type_cible']);
            $montant_min = (float)($_POST['montant_min'] ?? 0);
            $montant_max = (float)($_POST['montant_max'] ?? 0);
            $date_debut = sanitizeInput($_POST['date_debut']);
            $date_fin = sanitizeInput($_POST['date_fin']);
            $strategie = sanitizeInput($_POST['strategie']);
            $budget = (float)($_POST['budget'] ?? 0);
            $status = sanitizeInput($_POST['status']);
            
            // Validation des données
            if (empty($nom)) {
                $errors[] = 'Le nom de la campagne est requis.';
            }
            
            if (empty($date_debut) || empty($date_fin)) {
                $errors[] = 'Les dates de début et de fin sont requises.';
            }
            
            if ($date_debut > $date_fin) {
                $errors[] = 'La date de début ne peut pas être postérieure à la date de fin.';
            }
            
            if ($montant_min > $montant_max && $montant_max > 0) {
                $errors[] = 'Le montant minimum ne peut pas être supérieur au montant maximum.';
            }
            
            if (empty($errors)) {
                $database->query(
                    "UPDATE campagnes_recouvrement SET 
                        nom = ?, 
                        description = ?, 
                        type_cible = ?, 
                        montant_min = ?, 
                        montant_max = ?,
                        date_debut = ?, 
                        date_fin = ?, 
                        strategie = ?, 
                        budget = ?,
                        status = ?,
                        updated_at = NOW()
                     WHERE id = ?",
                    [
                        $nom, $description, $type_cible, $montant_min, $montant_max,
                        $date_debut, $date_fin, $strategie, $budget, $status, $campaign_id
                    ]
                );
                
                // Logger l'action
                logUserAction('update_campaign', 'campagnes_recouvrement', "Campagne mise à jour: $nom", $campaign_id);
                
                $success_message = 'Campagne mise à jour avec succès.';
                
                // Recharger les données de la campagne
                $campaign = $database->query(
                    "SELECT 
                        cr.*,
                        u.nom as created_by_name,
                        u.prenom as created_by_firstname
                     FROM campagnes_recouvrement cr
                     LEFT JOIN users u ON cr.created_by = u.id
                     WHERE cr.id = ? AND cr.annee_scolaire_id = ?",
                    [$campaign_id, $current_year['id']]
                )->fetch();
            }
            
        } catch (Exception $e) {
            $errors[] = 'Erreur lors de la mise à jour : ' . $e->getMessage();
        }
    }
    
    if ($action === 'delete_campaign') {
        try {
            // Vérifier s'il y a des cibles associées
            $targets_count = $database->query(
                "SELECT COUNT(*) as count FROM campagnes_cibles_dettes WHERE campagne_id = ?",
                [$campaign_id]
            )->fetch()['count'];
            
            if ($targets_count > 0) {
                $errors[] = "Impossible de supprimer cette campagne car elle contient $targets_count cible(s) associée(s).";
            } else {
                $database->query(
                    "DELETE FROM campagnes_recouvrement WHERE id = ?",
                    [$campaign_id]
                );
                
                // Logger l'action
                logUserAction('delete_campaign', 'campagnes_recouvrement', "Campagne supprimée: " . $campaign['nom'], $campaign_id);
                
                showMessage('success', 'Campagne supprimée avec succès.');
                redirectTo('index.php');
            }
            
        } catch (Exception $e) {
            $errors[] = 'Erreur lors de la suppression : ' . $e->getMessage();
        }
    }
}

// Statistiques de la campagne
$campaign_stats = $database->query(
    "SELECT 
        COUNT(DISTINCT ccd.eleve_id) as total_cibles,
        COUNT(DISTINCT CASE WHEN ccd.status = 'contacte' THEN ccd.eleve_id END) as contactes,
        COUNT(DISTINCT CASE WHEN ccd.status = 'paye' THEN ccd.eleve_id END) as payes,
        COUNT(DISTINCT CASE WHEN ccd.status = 'refuse' THEN ccd.eleve_id END) as refuses,
        COUNT(DISTINCT CASE WHEN ccd.status = 'injoignable' THEN ccd.eleve_id END) as injoignables,
        SUM(ccd.montant_dette) as total_dettes,
        SUM(ccd.montant_recouvre) as total_recouvre,
        ROUND((SUM(ccd.montant_recouvre) * 100.0 / SUM(ccd.montant_dette)), 1) as taux_recouvrement
     FROM campagnes_cibles_dettes ccd
     WHERE ccd.campagne_id = ?",
    [$campaign_id]
)->fetch();

include '../../../includes/header.php';
?>

<!-- Styles CSS modernes -->
<style>
.edit-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 2rem 0;
    margin: -20px -15px 30px -15px;
    border-radius: 0 0 20px 20px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.1);
}

.edit-header h1 {
    font-weight: 300;
    font-size: 2.5rem;
    margin-bottom: 0.5rem;
}

.edit-header .subtitle {
    opacity: 0.9;
    font-size: 1.1rem;
}

.campaign-stats {
    background: white;
    border-radius: 15px;
    padding: 2rem;
    box-shadow: 0 5px 15px rgba(0,0,0,0.08);
    border: none;
    margin-bottom: 2rem;
}

.stat-card {
    background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
    color: white;
    border-radius: 15px;
    padding: 1.5rem;
    text-align: center;
    margin-bottom: 1rem;
}

.stat-card.success {
    background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
}

.stat-card.warning {
    background: linear-gradient(135deg, #fa709a 0%, #fee140 100%);
}

.stat-card.info {
    background: linear-gradient(135deg, #a8edea 0%, #fed6e3 100%);
    color: #333;
}

.stat-number {
    font-size: 2.5rem;
    font-weight: bold;
    margin-bottom: 0.5rem;
}

.stat-label {
    font-size: 0.9rem;
    opacity: 0.9;
}

.edit-form {
    background: white;
    border-radius: 15px;
    padding: 2rem;
    box-shadow: 0 5px 15px rgba(0,0,0,0.08);
    border: none;
    margin-bottom: 2rem;
}

.form-section {
    border-bottom: 2px solid #f8f9fa;
    padding-bottom: 2rem;
    margin-bottom: 2rem;
}

.form-section:last-child {
    border-bottom: none;
    padding-bottom: 0;
    margin-bottom: 0;
}

.section-title {
    color: #667eea;
    font-size: 1.3rem;
    font-weight: 600;
    margin-bottom: 1.5rem;
    display: flex;
    align-items: center;
}

.section-title i {
    margin-right: 0.5rem;
    font-size: 1.1rem;
}

.form-group {
    margin-bottom: 1.5rem;
}

.form-label {
    font-weight: 600;
    color: #495057;
    margin-bottom: 0.5rem;
    display: block;
}

.form-control {
    border: 2px solid #e9ecef;
    border-radius: 10px;
    padding: 0.75rem 1rem;
    font-size: 1rem;
    transition: all 0.3s ease;
}

.form-control:focus {
    border-color: #667eea;
    box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
}

.btn-modern {
    border-radius: 25px;
    padding: 0.75rem 2rem;
    font-weight: 600;
    transition: all 0.3s ease;
    border: none;
    box-shadow: 0 3px 15px rgba(0,0,0,0.1);
}

.btn-modern:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 25px rgba(0,0,0,0.2);
}

.btn-primary.btn-modern {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
}

.btn-success.btn-modern {
    background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
}

.btn-danger.btn-modern {
    background: linear-gradient(135deg, #fa709a 0%, #fee140 100%);
}

.btn-secondary.btn-modern {
    background: linear-gradient(135deg, #6c757d 0%, #495057 100%);
}

.alert-modern {
    border-radius: 15px;
    border: none;
    padding: 1rem 1.5rem;
    margin-bottom: 1.5rem;
}

.alert-success {
    background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%);
    color: #155724;
}

.alert-danger {
    background: linear-gradient(135deg, #f8d7da 0%, #f5c6cb 100%);
    color: #721c24;
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

@media (max-width: 768px) {
    .edit-header {
        margin: -20px -15px 20px -15px;
        padding: 1.5rem 0;
    }

    .edit-header h1 {
        font-size: 2rem;
    }

    .campaign-stats, .edit-form {
        padding: 1rem;
    }

    .stat-number {
        font-size: 2rem;
    }
}
</style>

<!-- En-tête moderne -->
<div class="edit-header">
    <div class="container-fluid">
        <div class="row align-items-center">
            <div class="col-md-8">
                <h1 class="animate-fade-in">
                    <i class="fas fa-edit me-3"></i>
                    Édition de campagne
                </h1>
                <p class="subtitle animate-fade-in animate-delay-1">
                    <?php echo htmlspecialchars($campaign['nom']); ?>
                </p>
            </div>
            <div class="col-md-4 text-end">
                <div class="animate-fade-in animate-delay-2">
                    <a href="index.php" class="btn btn-light btn-modern">
                        <i class="fas fa-arrow-left me-2"></i>
                        Retour
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Messages d'erreur et de succès -->
<?php if (!empty($errors)): ?>
    <div class="alert alert-danger alert-modern animate-fade-in">
        <h5><i class="fas fa-exclamation-triangle me-2"></i>Erreurs</h5>
        <ul class="mb-0">
            <?php foreach ($errors as $error): ?>
                <li><?php echo htmlspecialchars($error); ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<?php if ($success_message): ?>
    <div class="alert alert-success alert-modern animate-fade-in">
        <i class="fas fa-check-circle me-2"></i>
        <?php echo htmlspecialchars($success_message); ?>
    </div>
<?php endif; ?>

<!-- Statistiques de la campagne -->
<div class="campaign-stats animate-fade-in animate-delay-1">
    <h5 class="mb-3">
        <i class="fas fa-chart-bar me-2"></i>
        Statistiques de la campagne
    </h5>
    
    <div class="row">
        <div class="col-md-3 col-sm-6">
            <div class="stat-card">
                <div class="stat-number"><?php echo $campaign_stats['total_cibles'] ?? 0; ?></div>
                <div class="stat-label">Total des cibles</div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6">
            <div class="stat-card success">
                <div class="stat-number"><?php echo $campaign_stats['payes'] ?? 0; ?></div>
                <div class="stat-label">Paiements effectués</div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6">
            <div class="stat-card warning">
                <div class="stat-number"><?php echo number_format($campaign_stats['total_recouvre'] ?? 0, 0, ',', ' '); ?> FC</div>
                <div class="stat-label">Montant recouvré</div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6">
            <div class="stat-card info">
                <div class="stat-number"><?php echo $campaign_stats['taux_recouvrement'] ?? 0; ?>%</div>
                <div class="stat-label">Taux de recouvrement</div>
            </div>
        </div>
    </div>
    
    <div class="row mt-3">
        <div class="col-md-4">
            <small class="text-muted">
                <i class="fas fa-phone me-1"></i>
                Contactés: <?php echo $campaign_stats['contactes'] ?? 0; ?>
            </small>
        </div>
        <div class="col-md-4">
            <small class="text-muted">
                <i class="fas fa-times me-1"></i>
                Refusés: <?php echo $campaign_stats['refuses'] ?? 0; ?>
            </small>
        </div>
        <div class="col-md-4">
            <small class="text-muted">
                <i class="fas fa-question me-1"></i>
                Injoignables: <?php echo $campaign_stats['injoignables'] ?? 0; ?>
            </small>
        </div>
    </div>
</div>

<!-- Formulaire d'édition -->
<div class="edit-form animate-fade-in animate-delay-2">
    <form method="POST" action="">
        <input type="hidden" name="action" value="update_campaign">
        
        <!-- Informations générales -->
        <div class="form-section">
            <h5 class="section-title">
                <i class="fas fa-info-circle"></i>
                Informations générales
            </h5>
            
            <div class="row">
                <div class="col-md-6">
                    <div class="form-group">
                        <label class="form-label">Nom de la campagne *</label>
                        <input type="text" name="nom" class="form-control" 
                               value="<?php echo htmlspecialchars($campaign['nom']); ?>" required>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label class="form-label">Statut</label>
                        <select name="status" class="form-control">
                            <option value="active" <?php echo $campaign['status'] === 'active' ? 'selected' : ''; ?>>Active</option>
                            <option value="paused" <?php echo $campaign['status'] === 'paused' ? 'selected' : ''; ?>>En pause</option>
                            <option value="completed" <?php echo $campaign['status'] === 'completed' ? 'selected' : ''; ?>>Terminée</option>
                            <option value="cancelled" <?php echo $campaign['status'] === 'cancelled' ? 'selected' : ''; ?>>Annulée</option>
                        </select>
                    </div>
                </div>
            </div>
            
            <div class="form-group">
                <label class="form-label">Description</label>
                <textarea name="description" class="form-control" rows="3"><?php echo htmlspecialchars($campaign['description'] ?? ''); ?></textarea>
            </div>
        </div>
        
        <!-- Critères de ciblage -->
        <div class="form-section">
            <h5 class="section-title">
                <i class="fas fa-bullseye"></i>
                Critères de ciblage
            </h5>
            
            <div class="row">
                <div class="col-md-6">
                    <div class="form-group">
                        <label class="form-label">Type de cible *</label>
                        <select name="type_cible" class="form-control" required>
                            <option value="tous" <?php echo $campaign['type_cible'] === 'tous' ? 'selected' : ''; ?>>Tous les débiteurs</option>
                            <option value="retard" <?php echo $campaign['type_cible'] === 'retard' ? 'selected' : ''; ?>>Retard de paiement</option>
                            <option value="montant" <?php echo $campaign['type_cible'] === 'montant' ? 'selected' : ''; ?>>Montant spécifique</option>
                            <option value="niveau" <?php echo $campaign['type_cible'] === 'niveau' ? 'selected' : ''; ?>>Par niveau scolaire</option>
                        </select>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label class="form-label">Stratégie de recouvrement</label>
                        <select name="strategie" class="form-control">
                            <option value="mixte" <?php echo $campaign['strategie'] === 'mixte' ? 'selected' : ''; ?>>Mixte</option>
                            <option value="appel_telephonique" <?php echo $campaign['strategie'] === 'appel_telephonique' ? 'selected' : ''; ?>>Appel téléphonique</option>
                            <option value="sms" <?php echo $campaign['strategie'] === 'sms' ? 'selected' : ''; ?>>SMS</option>
                            <option value="email" <?php echo $campaign['strategie'] === 'email' ? 'selected' : ''; ?>>Email</option>
                            <option value="visite_domicile" <?php echo $campaign['strategie'] === 'visite_domicile' ? 'selected' : ''; ?>>Visite à domicile</option>
                            <option value="lettre" <?php echo $campaign['strategie'] === 'lettre' ? 'selected' : ''; ?>>Lettre</option>
                        </select>
                    </div>
                </div>
            </div>
            
            <div class="row">
                <div class="col-md-6">
                    <div class="form-group">
                        <label class="form-label">Montant minimum (FC)</label>
                        <input type="number" name="montant_min" class="form-control" 
                               value="<?php echo $campaign['montant_min'] ?? ''; ?>" step="0.01" min="0">
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label class="form-label">Montant maximum (FC)</label>
                        <input type="number" name="montant_max" class="form-control" 
                               value="<?php echo $campaign['montant_max'] ?? ''; ?>" step="0.01" min="0">
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Période et budget -->
        <div class="form-section">
            <h5 class="section-title">
                <i class="fas fa-calendar-alt"></i>
                Période et budget
            </h5>
            
            <div class="row">
                <div class="col-md-6">
                    <div class="form-group">
                        <label class="form-label">Date de début *</label>
                        <input type="date" name="date_debut" class="form-control" 
                               value="<?php echo $campaign['date_debut']; ?>" required>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label class="form-label">Date de fin *</label>
                        <input type="date" name="date_fin" class="form-control" 
                               value="<?php echo $campaign['date_fin']; ?>" required>
                    </div>
                </div>
            </div>
            
            <div class="form-group">
                <label class="form-label">Budget alloué (FC)</label>
                <input type="number" name="budget" class="form-control" 
                       value="<?php echo $campaign['budget'] ?? ''; ?>" step="0.01" min="0">
            </div>
        </div>
        
        <!-- Informations de création -->
        <div class="form-section">
            <h5 class="section-title">
                <i class="fas fa-user"></i>
                Informations de création
            </h5>
            
            <div class="row">
                <div class="col-md-6">
                    <p><strong>Créée par:</strong> <?php echo htmlspecialchars($campaign['created_by_name'] . ' ' . $campaign['created_by_firstname']); ?></p>
                </div>
                <div class="col-md-6">
                    <p><strong>Date de création:</strong> <?php echo date('d/m/Y H:i', strtotime($campaign['created_at'])); ?></p>
                </div>
            </div>
            
            <?php if ($campaign['updated_at']): ?>
                <div class="row">
                    <div class="col-md-6">
                        <p><strong>Dernière modification:</strong> <?php echo date('d/m/Y H:i', strtotime($campaign['updated_at'])); ?></p>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Boutons d'action -->
        <div class="text-center mt-4">
            <button type="submit" class="btn btn-primary btn-modern me-3">
                <i class="fas fa-save me-2"></i>
                Enregistrer les modifications
            </button>
            
            <a href="details.php?id=<?php echo $campaign_id; ?>" class="btn btn-info btn-modern me-3">
                <i class="fas fa-eye me-2"></i>
                Voir les détails
            </a>
            
            <button type="button" class="btn btn-danger btn-modern" 
                    onclick="confirmDelete()">
                <i class="fas fa-trash me-2"></i>
                Supprimer la campagne
            </button>
        </div>
    </form>
    
    <!-- Formulaire de suppression caché -->
    <form method="POST" action="" id="deleteForm" style="display: none;">
        <input type="hidden" name="action" value="delete_campaign">
    </form>
</div>

<script>
function confirmDelete() {
    if (confirm('Êtes-vous sûr de vouloir supprimer cette campagne ? Cette action est irréversible.')) {
        document.getElementById('deleteForm').submit();
    }
}

// Validation en temps réel
document.addEventListener('DOMContentLoaded', function() {
    const dateDebut = document.querySelector('input[name="date_debut"]');
    const dateFin = document.querySelector('input[name="date_fin"]');
    const montantMin = document.querySelector('input[name="montant_min"]');
    const montantMax = document.querySelector('input[name="montant_max"]');
    
    function validateDates() {
        if (dateDebut.value && dateFin.value && dateDebut.value > dateFin.value) {
            dateFin.setCustomValidity('La date de fin doit être postérieure à la date de début');
        } else {
            dateFin.setCustomValidity('');
        }
    }
    
    function validateAmounts() {
        const min = parseFloat(montantMin.value) || 0;
        const max = parseFloat(montantMax.value) || 0;
        
        if (max > 0 && min > max) {
            montantMax.setCustomValidity('Le montant maximum doit être supérieur au montant minimum');
        } else {
            montantMax.setCustomValidity('');
        }
    }
    
    dateDebut.addEventListener('change', validateDates);
    dateFin.addEventListener('change', validateDates);
    montantMin.addEventListener('input', validateAmounts);
    montantMax.addEventListener('input', validateAmounts);
});
</script>

<?php include '../../../includes/footer.php'; ?>
