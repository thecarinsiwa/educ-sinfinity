<?php
/**
 * Module d'évaluations - Rapports des notes
 * Application de gestion scolaire - République Démocratique du Congo
 */

require_once '../../../config/config.php';
require_once '../../../config/database.php';
require_once '../../../includes/functions.php';

// Vérifier l'authentification et les permissions
requireLogin();
if (!checkPermission('evaluations') && !checkPermission('evaluations_view')) {
    showMessage('error', 'Accès refusé à cette fonctionnalité.');
    redirectTo('index.php');
}

$page_title = 'Rapports des notes';

// Récupérer l'année scolaire active
$current_year = $database->query("SELECT * FROM annees_scolaires WHERE status = 'active' LIMIT 1")->fetch();
if (!$current_year) {
    showMessage('error', 'Aucune année scolaire active trouvée.');
    redirectTo('../../../index.php');
}

// Récupérer les listes pour les sélecteurs
$classes = $database->query(
    "SELECT * FROM classes WHERE annee_scolaire_id = ? ORDER BY niveau, nom",
    [$current_year['id']]
)->fetchAll();

$matieres = $database->query(
    "SELECT * FROM matieres ORDER BY niveau, nom"
)->fetchAll();

$evaluations = $database->query(
    "SELECT e.id, e.nom, m.nom as matiere_nom, c.nom as classe_nom, e.date_evaluation
     FROM evaluations e
     JOIN matieres m ON e.matiere_id = m.id
     JOIN classes c ON e.classe_id = c.id
     WHERE e.annee_scolaire_id = ?
     ORDER BY e.date_evaluation DESC",
    [$current_year['id']]
)->fetchAll();

include '../../../includes/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">
        <i class="fas fa-file-alt me-2"></i>
        Rapports des notes
    </h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <div class="btn-group me-2">
            <a href="index.php" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-1"></i>
                Retour aux notes
            </a>
            <a href="statistics.php" class="btn btn-outline-info">
                <i class="fas fa-chart-bar me-1"></i>
                Statistiques
            </a>
        </div>
    </div>
</div>

<div class="row">
    <!-- Rapports par évaluation -->
    <div class="col-lg-6 mb-4">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-clipboard-check me-2"></i>
                    Rapports par évaluation
                </h5>
            </div>
            <div class="card-body">
                <p class="text-muted">Générer des rapports détaillés pour une évaluation spécifique.</p>
                
                <form id="reportEvaluationForm" class="mb-3">
                    <div class="mb-3">
                        <label for="evaluation_id" class="form-label">Sélectionner une évaluation</label>
                        <select class="form-select" id="evaluation_id" name="evaluation_id" required>
                            <option value="">Choisir une évaluation...</option>
                            <?php foreach ($evaluations as $eval): ?>
                                <option value="<?php echo $eval['id']; ?>">
                                    <?php echo htmlspecialchars($eval['nom']); ?> - 
                                    <?php echo htmlspecialchars($eval['classe_nom']); ?> - 
                                    <?php echo htmlspecialchars($eval['matiere_nom']); ?>
                                    (<?php echo date('d/m/Y', strtotime($eval['date_evaluation'])); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="d-grid gap-2">
                        <button type="button" class="btn btn-primary" onclick="generateEvaluationReport('pdf')">
                            <i class="fas fa-file-pdf me-1"></i>
                            Rapport PDF
                        </button>
                        <button type="button" class="btn btn-success" onclick="generateEvaluationReport('excel')">
                            <i class="fas fa-file-excel me-1"></i>
                            Export Excel
                        </button>
                        <button type="button" class="btn btn-info" onclick="generateEvaluationReport('view')">
                            <i class="fas fa-eye me-1"></i>
                            Aperçu en ligne
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Rapports par classe -->
    <div class="col-lg-6 mb-4">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-school me-2"></i>
                    Rapports par classe
                </h5>
            </div>
            <div class="card-body">
                <p class="text-muted">Générer des rapports pour toutes les évaluations d'une classe.</p>
                
                <form id="reportClasseForm" class="mb-3">
                    <div class="mb-3">
                        <label for="classe_id" class="form-label">Sélectionner une classe</label>
                        <select class="form-select" id="classe_id" name="classe_id" required>
                            <option value="">Choisir une classe...</option>
                            <?php foreach ($classes as $classe): ?>
                                <option value="<?php echo $classe['id']; ?>">
                                    <?php echo htmlspecialchars($classe['nom']); ?> 
                                    (<?php echo ucfirst($classe['niveau']); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="periode_classe" class="form-label">Période (optionnel)</label>
                        <select class="form-select" id="periode_classe" name="periode">
                            <option value="">Toutes les périodes</option>
                            <option value="1er_trimestre">1er Trimestre</option>
                            <option value="2eme_trimestre">2ème Trimestre</option>
                            <option value="3eme_trimestre">3ème Trimestre</option>
                            <option value="annuelle">Annuelle</option>
                        </select>
                    </div>
                    
                    <div class="d-grid gap-2">
                        <button type="button" class="btn btn-primary" onclick="generateClasseReport('pdf')">
                            <i class="fas fa-file-pdf me-1"></i>
                            Bulletin de classe PDF
                        </button>
                        <button type="button" class="btn btn-success" onclick="generateClasseReport('excel')">
                            <i class="fas fa-file-excel me-1"></i>
                            Tableau Excel
                        </button>
                        <button type="button" class="btn btn-warning" onclick="generateClasseReport('summary')">
                            <i class="fas fa-chart-bar me-1"></i>
                            Résumé statistique
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <!-- Rapports par matière -->
    <div class="col-lg-6 mb-4">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-book me-2"></i>
                    Rapports par matière
                </h5>
            </div>
            <div class="card-body">
                <p class="text-muted">Analyser les performances dans une matière spécifique.</p>
                
                <form id="reportMatiereForm" class="mb-3">
                    <div class="mb-3">
                        <label for="matiere_id" class="form-label">Sélectionner une matière</label>
                        <select class="form-select" id="matiere_id" name="matiere_id" required>
                            <option value="">Choisir une matière...</option>
                            <?php foreach ($matieres as $matiere): ?>
                                <option value="<?php echo $matiere['id']; ?>">
                                    <?php echo htmlspecialchars($matiere['nom']); ?>
                                    (Coef. <?php echo $matiere['coefficient']; ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="niveau_matiere" class="form-label">Niveau (optionnel)</label>
                        <select class="form-select" id="niveau_matiere" name="niveau">
                            <option value="">Tous les niveaux</option>
                            <option value="maternelle">Maternelle</option>
                            <option value="primaire">Primaire</option>
                            <option value="secondaire">Secondaire</option>
                        </select>
                    </div>
                    
                    <div class="d-grid gap-2">
                        <button type="button" class="btn btn-primary" onclick="generateMatiereReport('analysis')">
                            <i class="fas fa-chart-line me-1"></i>
                            Analyse de performance
                        </button>
                        <button type="button" class="btn btn-info" onclick="generateMatiereReport('comparison')">
                            <i class="fas fa-balance-scale me-1"></i>
                            Comparaison classes
                        </button>
                        <button type="button" class="btn btn-success" onclick="generateMatiereReport('excel')">
                            <i class="fas fa-file-excel me-1"></i>
                            Export détaillé
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Rapports par période -->
    <div class="col-lg-6 mb-4">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-calendar me-2"></i>
                    Rapports par période
                </h5>
            </div>
            <div class="card-body">
                <p class="text-muted">Générer des rapports pour une période scolaire.</p>
                
                <form id="reportPeriodeForm" class="mb-3">
                    <div class="mb-3">
                        <label for="periode_rapport" class="form-label">Sélectionner une période</label>
                        <select class="form-select" id="periode_rapport" name="periode" required>
                            <option value="">Choisir une période...</option>
                            <option value="1er_trimestre">1er Trimestre</option>
                            <option value="2eme_trimestre">2ème Trimestre</option>
                            <option value="3eme_trimestre">3ème Trimestre</option>
                            <option value="annuelle">Année complète</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="type_rapport" class="form-label">Type de rapport</label>
                        <select class="form-select" id="type_rapport" name="type_rapport">
                            <option value="global">Rapport global</option>
                            <option value="par_classe">Par classe</option>
                            <option value="par_matiere">Par matière</option>
                            <option value="synthese">Synthèse générale</option>
                        </select>
                    </div>
                    
                    <div class="d-grid gap-2">
                        <button type="button" class="btn btn-primary" onclick="generatePeriodeReport('pdf')">
                            <i class="fas fa-file-pdf me-1"></i>
                            Rapport PDF
                        </button>
                        <button type="button" class="btn btn-success" onclick="generatePeriodeReport('excel')">
                            <i class="fas fa-file-excel me-1"></i>
                            Données Excel
                        </button>
                        <button type="button" class="btn btn-warning" onclick="generatePeriodeReport('dashboard')">
                            <i class="fas fa-tachometer-alt me-1"></i>
                            Tableau de bord
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Rapports prédéfinis -->
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-magic me-2"></i>
                    Rapports prédéfinis
                </h5>
            </div>
            <div class="card-body">
                <p class="text-muted">Rapports fréquemment utilisés, prêts à générer.</p>
                
                <div class="row">
                    <div class="col-lg-3 col-md-6 mb-3">
                        <div class="card border-primary">
                            <div class="card-body text-center">
                                <i class="fas fa-trophy fa-2x text-primary mb-2"></i>
                                <h6>Classement général</h6>
                                <p class="small text-muted">Top des élèves par moyenne</p>
                                <button class="btn btn-primary btn-sm" onclick="generatePredefinedReport('ranking')">
                                    Générer
                                </button>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-lg-3 col-md-6 mb-3">
                        <div class="card border-success">
                            <div class="card-body text-center">
                                <i class="fas fa-chart-pie fa-2x text-success mb-2"></i>
                                <h6>Analyse globale</h6>
                                <p class="small text-muted">Statistiques générales</p>
                                <button class="btn btn-success btn-sm" onclick="generatePredefinedReport('global_analysis')">
                                    Générer
                                </button>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-lg-3 col-md-6 mb-3">
                        <div class="card border-warning">
                            <div class="card-body text-center">
                                <i class="fas fa-exclamation-triangle fa-2x text-warning mb-2"></i>
                                <h6>Élèves en difficulté</h6>
                                <p class="small text-muted">Moyennes < 10/20</p>
                                <button class="btn btn-warning btn-sm" onclick="generatePredefinedReport('struggling_students')">
                                    Générer
                                </button>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-lg-3 col-md-6 mb-3">
                        <div class="card border-info">
                            <div class="card-body text-center">
                                <i class="fas fa-calendar-check fa-2x text-info mb-2"></i>
                                <h6>Suivi mensuel</h6>
                                <p class="small text-muted">Évolution des notes</p>
                                <button class="btn btn-info btn-sm" onclick="generatePredefinedReport('monthly_tracking')">
                                    Générer
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal de progression -->
<div class="modal fade" id="progressModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Génération du rapport</h5>
            </div>
            <div class="modal-body text-center">
                <div class="spinner-border text-primary mb-3" role="status">
                    <span class="visually-hidden">Chargement...</span>
                </div>
                <p id="progressText">Génération en cours...</p>
                <div class="progress">
                    <div class="progress-bar" id="progressBar" style="width: 0%"></div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Fonctions de génération de rapports
function generateEvaluationReport(format) {
    const evaluationId = document.getElementById('evaluation_id').value;
    if (!evaluationId) {
        alert('Veuillez sélectionner une évaluation');
        return;
    }
    
    showProgress('Génération du rapport d\'évaluation...');
    
    // Simuler la génération (à remplacer par l'appel réel)
    setTimeout(() => {
        hideProgress();
        const url = `evaluation_report.php?id=${evaluationId}&format=${format}`;
        if (format === 'view') {
            window.open(url, '_blank');
        } else {
            window.location.href = url;
        }
    }, 2000);
}

function generateClasseReport(format) {
    const classeId = document.getElementById('classe_id').value;
    const periode = document.getElementById('periode_classe').value;
    
    if (!classeId) {
        alert('Veuillez sélectionner une classe');
        return;
    }
    
    showProgress('Génération du rapport de classe...');
    
    setTimeout(() => {
        hideProgress();
        let url = `classe_report.php?classe_id=${classeId}&format=${format}`;
        if (periode) url += `&periode=${periode}`;
        
        if (format === 'summary') {
            window.open(url, '_blank');
        } else {
            window.location.href = url;
        }
    }, 2500);
}

function generateMatiereReport(type) {
    const matiereId = document.getElementById('matiere_id').value;
    const niveau = document.getElementById('niveau_matiere').value;
    
    if (!matiereId) {
        alert('Veuillez sélectionner une matière');
        return;
    }
    
    showProgress('Génération du rapport de matière...');
    
    setTimeout(() => {
        hideProgress();
        let url = `matiere_report.php?matiere_id=${matiereId}&type=${type}`;
        if (niveau) url += `&niveau=${niveau}`;
        
        if (type === 'analysis' || type === 'comparison') {
            window.open(url, '_blank');
        } else {
            window.location.href = url;
        }
    }, 3000);
}

function generatePeriodeReport(format) {
    const periode = document.getElementById('periode_rapport').value;
    const typeRapport = document.getElementById('type_rapport').value;
    
    if (!periode) {
        alert('Veuillez sélectionner une période');
        return;
    }
    
    showProgress('Génération du rapport de période...');
    
    setTimeout(() => {
        hideProgress();
        const url = `periode_report.php?periode=${periode}&type=${typeRapport}&format=${format}`;
        
        if (format === 'dashboard') {
            window.open(url, '_blank');
        } else {
            window.location.href = url;
        }
    }, 2800);
}

function generatePredefinedReport(type) {
    showProgress('Génération du rapport prédéfini...');
    
    setTimeout(() => {
        hideProgress();
        const url = `predefined_report.php?type=${type}`;
        window.open(url, '_blank');
    }, 2200);
}

function showProgress(text) {
    document.getElementById('progressText').textContent = text;
    document.getElementById('progressBar').style.width = '0%';
    
    const modal = new bootstrap.Modal(document.getElementById('progressModal'));
    modal.show();
    
    // Simuler la progression
    let progress = 0;
    const interval = setInterval(() => {
        progress += Math.random() * 20;
        if (progress > 90) progress = 90;
        document.getElementById('progressBar').style.width = progress + '%';
    }, 200);
    
    // Stocker l'interval pour pouvoir l'arrêter
    window.progressInterval = interval;
}

function hideProgress() {
    if (window.progressInterval) {
        clearInterval(window.progressInterval);
    }
    
    document.getElementById('progressBar').style.width = '100%';
    
    setTimeout(() => {
        const modal = bootstrap.Modal.getInstance(document.getElementById('progressModal'));
        if (modal) modal.hide();
    }, 500);
}

// Validation des formulaires
document.addEventListener('DOMContentLoaded', function() {
    // Ajouter des validations si nécessaire
});
</script>

<?php include '../../../includes/footer.php'; ?>
