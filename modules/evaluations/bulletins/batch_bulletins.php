<?php
/**
 * Module d'évaluations - Génération en lot des bulletins
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

// Récupérer les paramètres
$classe_id = (int)($_GET['classe_id'] ?? 0);
$periode = sanitizeInput($_GET['periode'] ?? '');
$format = sanitizeInput($_GET['format'] ?? 'view');

if (!$classe_id || !$periode) {
    showMessage('error', 'Paramètres manquants pour la génération en lot.');
    redirectTo('generate.php');
}

// Récupérer l'année scolaire active
$current_year = $database->query("SELECT * FROM annees_scolaires WHERE status = 'active' LIMIT 1")->fetch();
if (!$current_year) {
    showMessage('error', 'Aucune année scolaire active trouvée.');
    redirectTo('../../../index.php');
}

// Récupérer les informations de la classe
$classe = $database->query(
    "SELECT * FROM classes WHERE id = ? AND annee_scolaire_id = ?",
    [$classe_id, $current_year['id']]
)->fetch();

if (!$classe) {
    showMessage('error', 'Classe non trouvée.');
    redirectTo('generate.php');
}

// Récupérer tous les élèves de la classe
$eleves = $database->query(
    "SELECT e.* FROM eleves e
     JOIN inscriptions i ON e.id = i.eleve_id
     WHERE i.classe_id = ? AND i.annee_scolaire_id = ? AND i.status = 'inscrit'
     ORDER BY e.nom, e.prenom",
    [$classe_id, $current_year['id']]
)->fetchAll();

if (empty($eleves)) {
    showMessage('error', 'Aucun élève trouvé dans cette classe.');
    redirectTo('generate.php');
}

// Mode impression/PDF - générer tous les bulletins
if ($format === 'print' || $format === 'pdf') {
    echo "<!DOCTYPE html>
    <html lang='fr'>
    <head>
        <meta charset='UTF-8'>
        <title>Bulletins - Classe " . htmlspecialchars($classe['nom']) . "</title>
        <style>
            body { font-family: Arial, sans-serif; margin: 0; padding: 0; }
            .bulletin-page { page-break-after: always; margin: 20px; }
            .bulletin-page:last-child { page-break-after: avoid; }
            @media print {
                .bulletin-page { page-break-after: always; margin: 0; }
                .no-print { display: none; }
            }
        </style>
    </head>
    <body>";
    
    foreach ($eleves as $index => $eleve_data) {
        echo "<div class='bulletin-page'>";
        
        // Simuler les variables nécessaires pour le template
        $eleve_id = $eleve_data['id'];
        $eleve = array_merge($eleve_data, ['classe_nom' => $classe['nom'], 'niveau' => $classe['niveau']]);
        
        // Récupérer les notes de l'élève
        $notes_eleve = $database->query(
            "SELECT n.note, n.observation,
                    e.nom as evaluation_nom, e.type_evaluation, e.coefficient, e.note_max, e.date_evaluation,
                    m.nom as matiere_nom, m.coefficient as matiere_coefficient, m.code as matiere_code
             FROM notes n
             JOIN evaluations e ON n.evaluation_id = e.id
             JOIN matieres m ON e.matiere_id = m.id
             WHERE n.eleve_id = ? AND e.annee_scolaire_id = ? AND e.periode = ?
             ORDER BY m.nom, e.date_evaluation",
            [$eleve_id, $current_year['id'], $periode]
        )->fetchAll();
        
        // Calculer les moyennes (logique simplifiée)
        $moyennes_matieres = [];
        $notes_par_matiere = [];
        
        foreach ($notes_eleve as $note) {
            $matiere = $note['matiere_nom'];
            if (!isset($notes_par_matiere[$matiere])) {
                $notes_par_matiere[$matiere] = [
                    'notes' => [],
                    'coefficient' => $note['matiere_coefficient'],
                    'code' => $note['matiere_code']
                ];
            }
            
            $note_sur_20 = ($note['note'] / $note['note_max']) * 20;
            $notes_par_matiere[$matiere]['notes'][] = [
                'note' => $note_sur_20,
                'coefficient' => $note['coefficient'],
                'evaluation' => $note['evaluation_nom'],
                'type' => $note['type_evaluation'],
                'date' => $note['date_evaluation']
            ];
        }
        
        $moyenne_generale = 0;
        $total_coefficients = 0;
        
        foreach ($notes_par_matiere as $matiere => $data) {
            $somme_notes = 0;
            $somme_coef = 0;
            
            foreach ($data['notes'] as $note_info) {
                $somme_notes += $note_info['note'] * $note_info['coefficient'];
                $somme_coef += $note_info['coefficient'];
            }
            
            $moyenne_matiere = $somme_coef > 0 ? $somme_notes / $somme_coef : 0;
            $moyennes_matieres[$matiere] = [
                'moyenne' => $moyenne_matiere,
                'coefficient' => $data['coefficient'],
                'code' => $data['code'],
                'notes_detail' => $data['notes']
            ];
            
            $moyenne_generale += $moyenne_matiere * $data['coefficient'];
            $total_coefficients += $data['coefficient'];
        }
        
        $moyenne_generale = $total_coefficients > 0 ? $moyenne_generale / $total_coefficients : 0;
        
        // Appréciation
        if ($moyenne_generale >= 16) $appreciation_generale = 'Excellent';
        elseif ($moyenne_generale >= 14) $appreciation_generale = 'Très bien';
        elseif ($moyenne_generale >= 12) $appreciation_generale = 'Bien';
        elseif ($moyenne_generale >= 10) $appreciation_generale = 'Assez bien';
        elseif ($moyenne_generale >= 8) $appreciation_generale = 'Passable';
        else $appreciation_generale = 'Insuffisant';
        
        // Stats classe (simplifiées)
        $stats_classe = ['moyenne_classe' => 12, 'effectif' => count($eleves)];
        
        // Inclure le template de bulletin
        include 'bulletin_template.php';
        
        echo "</div>";
    }
    
    if ($format === 'print') {
        echo "<script>window.onload = function() { window.print(); };</script>";
    }
    
    echo "</body></html>";
    exit;
}

// Mode vue normale
$page_title = 'Bulletins en lot - Classe ' . $classe['nom'];
include '../../../includes/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">
        <i class="fas fa-files-o me-2"></i>
        Bulletins en lot
    </h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <div class="btn-group me-2">
            <a href="generate.php?classe_id=<?php echo $classe_id; ?>&periode=<?php echo $periode; ?>" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-1"></i>
                Retour
            </a>
        </div>
        <div class="btn-group">
            <a href="?classe_id=<?php echo $classe_id; ?>&periode=<?php echo $periode; ?>&format=print" 
               class="btn btn-success" target="_blank">
                <i class="fas fa-print me-1"></i>
                Imprimer tous
            </a>
        </div>
    </div>
</div>

<!-- Informations sur la génération -->
<div class="card mb-4">
    <div class="card-header bg-info text-white">
        <h5 class="mb-0">
            <i class="fas fa-info-circle me-2"></i>
            Informations sur la génération
        </h5>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-3">
                <strong>Classe :</strong><br>
                <?php echo htmlspecialchars($classe['nom']); ?>
            </div>
            <div class="col-md-3">
                <strong>Niveau :</strong><br>
                <?php echo ucfirst($classe['niveau']); ?>
            </div>
            <div class="col-md-3">
                <strong>Période :</strong><br>
                <?php echo str_replace('_', ' ', ucfirst($periode)); ?>
            </div>
            <div class="col-md-3">
                <strong>Élèves :</strong><br>
                <?php echo count($eleves); ?> bulletins à générer
            </div>
        </div>
    </div>
</div>

<!-- Aperçu des bulletins -->
<div class="card">
    <div class="card-header">
        <h5 class="mb-0">
            <i class="fas fa-eye me-2"></i>
            Aperçu des bulletins (<?php echo count($eleves); ?>)
        </h5>
    </div>
    <div class="card-body">
        <div class="row">
            <?php foreach ($eleves as $eleve): ?>
                <div class="col-lg-4 col-md-6 mb-3">
                    <div class="card border-primary">
                        <div class="card-body">
                            <h6 class="card-title">
                                <?php echo htmlspecialchars($eleve['nom'] . ' ' . $eleve['prenom']); ?>
                            </h6>
                            <p class="card-text">
                                <small class="text-muted">
                                    Matricule: <?php echo htmlspecialchars($eleve['numero_matricule']); ?><br>
                                    <?php if ($eleve['date_naissance']): ?>
                                        Né(e) le: <?php echo date('d/m/Y', strtotime($eleve['date_naissance'])); ?>
                                    <?php endif; ?>
                                </small>
                            </p>
                            <div class="d-grid gap-2">
                                <a href="generate.php?classe_id=<?php echo $classe_id; ?>&eleve_id=<?php echo $eleve['id']; ?>&periode=<?php echo $periode; ?>&action=generate&format=view" 
                                   class="btn btn-outline-primary btn-sm">
                                    <i class="fas fa-eye me-1"></i>
                                    Aperçu
                                </a>
                                <a href="generate.php?classe_id=<?php echo $classe_id; ?>&eleve_id=<?php echo $eleve['id']; ?>&periode=<?php echo $periode; ?>&action=generate&format=print" 
                                   class="btn btn-outline-success btn-sm" target="_blank">
                                    <i class="fas fa-print me-1"></i>
                                    Imprimer
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        
        <hr>
        
        <div class="text-center">
            <h6>Actions groupées :</h6>
            <div class="btn-group">
                <a href="?classe_id=<?php echo $classe_id; ?>&periode=<?php echo $periode; ?>&format=print" 
                   class="btn btn-success" target="_blank">
                    <i class="fas fa-print me-1"></i>
                    Imprimer tous les bulletins
                </a>
                <a href="?classe_id=<?php echo $classe_id; ?>&periode=<?php echo $periode; ?>&format=pdf" 
                   class="btn btn-danger">
                    <i class="fas fa-file-pdf me-1"></i>
                    Télécharger tous en PDF
                </a>
            </div>
        </div>
    </div>
</div>

<?php include '../../../includes/footer.php'; ?>
