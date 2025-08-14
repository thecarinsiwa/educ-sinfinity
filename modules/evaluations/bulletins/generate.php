<?php
/**
 * Module d'évaluations - Génération de bulletins
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

$page_title = 'Génération de bulletins';

// Récupérer l'année scolaire active
$current_year = $database->query("SELECT * FROM annees_scolaires WHERE status = 'active' LIMIT 1")->fetch();
if (!$current_year) {
    showMessage('error', 'Aucune année scolaire active trouvée.');
    redirectTo('../../../index.php');
}

// Récupérer les paramètres
$eleve_id = (int)($_GET['eleve_id'] ?? 0);
$classe_id = (int)($_GET['classe_id'] ?? 0);
$periode = sanitizeInput($_GET['periode'] ?? '');
$format = sanitizeInput($_GET['format'] ?? 'view');
$action = sanitizeInput($_GET['action'] ?? '');

// Récupérer les listes pour les filtres
$classes = $database->query(
    "SELECT * FROM classes WHERE annee_scolaire_id = ? ORDER BY niveau, nom",
    [$current_year['id']]
)->fetchAll();

// Si une classe est sélectionnée, récupérer ses élèves
$eleves = [];
if ($classe_id) {
    $eleves = $database->query(
        "SELECT e.* FROM eleves e
         JOIN inscriptions i ON e.id = i.eleve_id
         WHERE i.classe_id = ? AND i.annee_scolaire_id = ? AND i.status = 'inscrit'
         ORDER BY e.nom, e.prenom",
        [$classe_id, $current_year['id']]
    )->fetchAll();
}

// Génération du bulletin individuel
if ($eleve_id && $periode && $action === 'generate') {
    // Récupérer les informations de l'élève
    $eleve = $database->query(
        "SELECT e.*, c.nom as classe_nom, c.niveau
         FROM eleves e
         JOIN inscriptions i ON e.id = i.eleve_id
         JOIN classes c ON i.classe_id = c.id
         WHERE e.id = ? AND i.annee_scolaire_id = ? AND i.status = 'inscrit'",
        [$eleve_id, $current_year['id']]
    )->fetch();
    
    if (!$eleve) {
        showMessage('error', 'Élève non trouvé.');
        redirectTo('generate.php');
    }
    
    // Récupérer les notes de l'élève pour la période
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
    
    // Calculer les moyennes par matière
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
        
        // Convertir la note sur 20
        $note_sur_20 = ($note['note'] / $note['note_max']) * 20;
        $notes_par_matiere[$matiere]['notes'][] = [
            'note' => $note_sur_20,
            'coefficient' => $note['coefficient'],
            'evaluation' => $note['evaluation_nom'],
            'type' => $note['type_evaluation'],
            'date' => $note['date_evaluation']
        ];
    }
    
    // Calculer les moyennes
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
    
    // Déterminer l'appréciation générale
    $appreciation_generale = '';
    if ($moyenne_generale >= 16) $appreciation_generale = 'Excellent';
    elseif ($moyenne_generale >= 14) $appreciation_generale = 'Très bien';
    elseif ($moyenne_generale >= 12) $appreciation_generale = 'Bien';
    elseif ($moyenne_generale >= 10) $appreciation_generale = 'Assez bien';
    elseif ($moyenne_generale >= 8) $appreciation_generale = 'Passable';
    else $appreciation_generale = 'Insuffisant';
    
    // Statistiques de la classe pour comparaison
    $stats_classe = $database->query(
        "SELECT AVG(moyenne_eleve) as moyenne_classe, COUNT(*) as effectif
         FROM (
             SELECT AVG(n.note / e.note_max * 20) as moyenne_eleve
             FROM notes n
             JOIN evaluations e ON n.evaluation_id = e.id
             JOIN inscriptions i ON n.eleve_id = i.eleve_id
             WHERE i.classe_id = ? AND e.annee_scolaire_id = ? AND e.periode = ?
             GROUP BY n.eleve_id
         ) as moyennes",
        [$classe_id, $current_year['id'], $periode]
    )->fetch();
    
    // Mode impression ou PDF
    if ($format === 'print' || $format === 'pdf') {
        include 'bulletin_template.php';
        exit;
    }
}

include '../../../includes/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">
        <i class="fas fa-file-alt me-2"></i>
        Génération de bulletins
    </h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <div class="btn-group me-2">
            <a href="../notes/" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-1"></i>
                Retour aux notes
            </a>
            <a href="../evaluations/" class="btn btn-outline-info">
                <i class="fas fa-clipboard-check me-1"></i>
                Évaluations
            </a>
        </div>
    </div>
</div>

<!-- Formulaire de sélection -->
<div class="card mb-4">
    <div class="card-header">
        <h5 class="mb-0">
            <i class="fas fa-filter me-2"></i>
            Paramètres de génération
        </h5>
    </div>
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-md-4">
                <label for="classe_id" class="form-label">Classe</label>
                <select class="form-select" id="classe_id" name="classe_id" required onchange="this.form.submit()">
                    <option value="">Sélectionner une classe...</option>
                    <?php foreach ($classes as $classe): ?>
                        <option value="<?php echo $classe['id']; ?>" 
                                <?php echo $classe_id == $classe['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($classe['nom']); ?> 
                            (<?php echo ucfirst($classe['niveau']); ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <?php if (!empty($eleves)): ?>
                <div class="col-md-4">
                    <label for="eleve_id" class="form-label">Élève</label>
                    <select class="form-select" id="eleve_id" name="eleve_id">
                        <option value="">Tous les élèves</option>
                        <?php foreach ($eleves as $eleve_option): ?>
                            <option value="<?php echo $eleve_option['id']; ?>" 
                                    <?php echo $eleve_id == $eleve_option['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($eleve_option['nom'] . ' ' . $eleve_option['prenom']); ?>
                                (<?php echo htmlspecialchars($eleve_option['numero_matricule']); ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php endif; ?>
            
            <div class="col-md-4">
                <label for="periode" class="form-label">Période</label>
                <select class="form-select" id="periode" name="periode" required>
                    <option value="">Sélectionner une période...</option>
                    <option value="1er_trimestre" <?php echo $periode === '1er_trimestre' ? 'selected' : ''; ?>>
                        1er Trimestre
                    </option>
                    <option value="2eme_trimestre" <?php echo $periode === '2eme_trimestre' ? 'selected' : ''; ?>>
                        2ème Trimestre
                    </option>
                    <option value="3eme_trimestre" <?php echo $periode === '3eme_trimestre' ? 'selected' : ''; ?>>
                        3ème Trimestre
                    </option>
                    <option value="annuelle" <?php echo $periode === 'annuelle' ? 'selected' : ''; ?>>
                        Année complète
                    </option>
                </select>
            </div>
        </form>
    </div>
</div>

<?php if ($classe_id && $periode): ?>
    <!-- Options de génération -->
    <div class="row">
        <?php if ($eleve_id): ?>
            <!-- Bulletin individuel -->
            <div class="col-lg-6 mb-4">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">
                            <i class="fas fa-user me-2"></i>
                            Bulletin individuel
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php
                        $eleve_selected = null;
                        foreach ($eleves as $e) {
                            if ($e['id'] == $eleve_id) {
                                $eleve_selected = $e;
                                break;
                            }
                        }
                        ?>
                        <?php if ($eleve_selected): ?>
                            <p><strong>Élève :</strong> <?php echo htmlspecialchars($eleve_selected['nom'] . ' ' . $eleve_selected['prenom']); ?></p>
                            <p><strong>Matricule :</strong> <?php echo htmlspecialchars($eleve_selected['numero_matricule']); ?></p>
                            <p><strong>Période :</strong> <?php echo str_replace('_', ' ', ucfirst($periode)); ?></p>
                            
                            <div class="d-grid gap-2">
                                <a href="?classe_id=<?php echo $classe_id; ?>&eleve_id=<?php echo $eleve_id; ?>&periode=<?php echo $periode; ?>&action=generate&format=view" 
                                   class="btn btn-primary">
                                    <i class="fas fa-eye me-1"></i>
                                    Aperçu du bulletin
                                </a>
                                <a href="?classe_id=<?php echo $classe_id; ?>&eleve_id=<?php echo $eleve_id; ?>&periode=<?php echo $periode; ?>&action=generate&format=print" 
                                   class="btn btn-success" target="_blank">
                                    <i class="fas fa-print me-1"></i>
                                    Imprimer le bulletin
                                </a>
                                <a href="?classe_id=<?php echo $classe_id; ?>&eleve_id=<?php echo $eleve_id; ?>&periode=<?php echo $periode; ?>&action=generate&format=pdf" 
                                   class="btn btn-danger">
                                    <i class="fas fa-file-pdf me-1"></i>
                                    Télécharger PDF
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>
        
        <!-- Bulletins de classe -->
        <div class="col-lg-6 mb-4">
            <div class="card">
                <div class="card-header bg-success text-white">
                    <h5 class="mb-0">
                        <i class="fas fa-users me-2"></i>
                        Bulletins de classe
                    </h5>
                </div>
                <div class="card-body">
                    <?php
                    $classe_selected = null;
                    foreach ($classes as $c) {
                        if ($c['id'] == $classe_id) {
                            $classe_selected = $c;
                            break;
                        }
                    }
                    ?>
                    <?php if ($classe_selected): ?>
                        <p><strong>Classe :</strong> <?php echo htmlspecialchars($classe_selected['nom']); ?></p>
                        <p><strong>Élèves :</strong> <?php echo count($eleves); ?> inscrits</p>
                        <p><strong>Période :</strong> <?php echo str_replace('_', ' ', ucfirst($periode)); ?></p>
                        
                        <div class="d-grid gap-2">
                            <button class="btn btn-primary" onclick="generateClassBulletins('view')">
                                <i class="fas fa-eye me-1"></i>
                                Aperçu tous les bulletins
                            </button>
                            <button class="btn btn-success" onclick="generateClassBulletins('print')">
                                <i class="fas fa-print me-1"></i>
                                Imprimer tous les bulletins
                            </button>
                            <button class="btn btn-danger" onclick="generateClassBulletins('pdf')">
                                <i class="fas fa-file-pdf me-1"></i>
                                Télécharger ZIP des PDF
                            </button>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Liste des élèves -->
    <?php if (!empty($eleves)): ?>
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-list me-2"></i>
                    Élèves de la classe (<?php echo count($eleves); ?>)
                </h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Matricule</th>
                                <th>Nom et Prénom</th>
                                <th>Date de naissance</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($eleves as $eleve_item): ?>
                                <tr>
                                    <td><code><?php echo htmlspecialchars($eleve_item['numero_matricule']); ?></code></td>
                                    <td><strong><?php echo htmlspecialchars($eleve_item['nom'] . ' ' . $eleve_item['prenom']); ?></strong></td>
                                    <td><?php echo $eleve_item['date_naissance'] ? date('d/m/Y', strtotime($eleve_item['date_naissance'])) : '-'; ?></td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <a href="?classe_id=<?php echo $classe_id; ?>&eleve_id=<?php echo $eleve_item['id']; ?>&periode=<?php echo $periode; ?>&action=generate&format=view" 
                                               class="btn btn-outline-primary" title="Voir bulletin">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <a href="?classe_id=<?php echo $classe_id; ?>&eleve_id=<?php echo $eleve_item['id']; ?>&periode=<?php echo $periode; ?>&action=generate&format=print" 
                                               class="btn btn-outline-success" target="_blank" title="Imprimer">
                                                <i class="fas fa-print"></i>
                                            </a>
                                            <a href="?classe_id=<?php echo $classe_id; ?>&eleve_id=<?php echo $eleve_item['id']; ?>&periode=<?php echo $periode; ?>&action=generate&format=pdf" 
                                               class="btn btn-outline-danger" title="PDF">
                                                <i class="fas fa-file-pdf"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    <?php endif; ?>

<?php else: ?>
    <!-- Instructions -->
    <div class="card">
        <div class="card-body text-center py-5">
            <i class="fas fa-graduation-cap fa-3x text-primary mb-3"></i>
            <h5>Génération de bulletins scolaires</h5>
            <p class="text-muted">
                Sélectionnez une classe et une période pour commencer la génération des bulletins.<br>
                Vous pourrez ensuite choisir de générer des bulletins individuels ou pour toute la classe.
            </p>
            <div class="mt-4">
                <h6>Fonctionnalités disponibles :</h6>
                <div class="row mt-3">
                    <div class="col-md-4">
                        <i class="fas fa-user fa-2x text-info mb-2"></i>
                        <h6>Bulletins individuels</h6>
                        <small class="text-muted">Génération pour un élève spécifique</small>
                    </div>
                    <div class="col-md-4">
                        <i class="fas fa-users fa-2x text-success mb-2"></i>
                        <h6>Bulletins de classe</h6>
                        <small class="text-muted">Génération pour tous les élèves</small>
                    </div>
                    <div class="col-md-4">
                        <i class="fas fa-file-pdf fa-2x text-danger mb-2"></i>
                        <h6>Formats multiples</h6>
                        <small class="text-muted">Aperçu, impression, PDF</small>
                    </div>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<script>
function generateClassBulletins(format) {
    const classeId = <?php echo $classe_id; ?>;
    const periode = '<?php echo $periode; ?>';
    
    if (confirm('Générer les bulletins pour tous les élèves de la classe ?')) {
        // Ouvrir une nouvelle fenêtre pour la génération en lot
        const url = `batch_bulletins.php?classe_id=${classeId}&periode=${periode}&format=${format}`;
        
        if (format === 'view' || format === 'print') {
            window.open(url, '_blank');
        } else {
            window.location.href = url;
        }
    }
}

// Auto-submit du formulaire quand les paramètres changent
document.addEventListener('DOMContentLoaded', function() {
    const eleveSelect = document.getElementById('eleve_id');
    const periodeSelect = document.getElementById('periode');
    
    if (eleveSelect) {
        eleveSelect.addEventListener('change', function() {
            this.form.submit();
        });
    }
    
    if (periodeSelect) {
        periodeSelect.addEventListener('change', function() {
            this.form.submit();
        });
    }
});
</script>

<?php include '../../../includes/footer.php'; ?>
