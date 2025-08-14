<?php
/**
 * Module Rapports Académiques - Export des données
 * Application de gestion scolaire - République Démocratique du Congo
 */

require_once '../../../config/config.php';
require_once '../../../config/database.php';
require_once '../../../includes/functions.php';

// Vérifier l'authentification et les permissions
requireLogin();
if (!checkPermission('reports') && !checkPermission('academic')) {
    showMessage('error', 'Accès refusé à cette fonctionnalité.');
    redirectTo('index.php');
}

$page_title = 'Export des rapports académiques';
$current_year = getCurrentAcademicYear();

if (!$current_year) {
    showMessage('error', 'Aucune année scolaire active.');
    redirectTo('../../index.php');
}

// Paramètres d'export
$type = sanitizeInput($_GET['type'] ?? 'performance');
$format = sanitizeInput($_GET['format'] ?? 'pdf');
$periode_filter = sanitizeInput($_GET['periode'] ?? '');
$classe_filter = (int)($_GET['classe'] ?? 0);
$matiere_filter = (int)($_GET['matiere'] ?? 0);

// Validation des paramètres
$types_autorises = ['performance', 'notes', 'statistiques', 'comparaison', 'evolution'];
$formats_autorises = ['pdf', 'excel', 'csv'];

if (!in_array($type, $types_autorises)) {
    $type = 'performance';
}

if (!in_array($format, $formats_autorises)) {
    $format = 'pdf';
}

// Récupérer les données selon le type
$data = [];
$filename = '';

switch ($type) {
    case 'performance':
        $filename = "rapport_performance_{$current_year['annee']}";
        
        // Performance par classe
        $data['classes'] = $database->query(
            "SELECT c.id, c.nom as classe_nom, c.niveau, c.section,
                    COUNT(DISTINCT n.eleve_id) as nb_eleves_evalues,
                    AVG(n.note) as moyenne_classe,
                    MIN(n.note) as note_min,
                    MAX(n.note) as note_max,
                    COUNT(CASE WHEN n.note >= 16 THEN 1 END) as excellents,
                    COUNT(CASE WHEN n.note >= 10 AND n.note < 16 THEN 1 END) as admis,
                    COUNT(CASE WHEN n.note < 10 THEN 1 END) as echecs
             FROM classes c
             JOIN inscriptions i ON c.id = i.classe_id
             JOIN notes n ON i.eleve_id = n.eleve_id
             JOIN evaluations e ON n.evaluation_id = e.id
             WHERE c.annee_scolaire_id = ? AND i.status = 'inscrit'
             " . ($periode_filter ? "AND e.periode = ?" : "") . "
             GROUP BY c.id, c.nom, c.niveau, c.section
             ORDER BY moyenne_classe DESC",
            array_filter([$current_year['id'], $periode_filter])
        )->fetchAll();
        
        // Performance par matière
        $data['matieres'] = $database->query(
            "SELECT m.id, m.nom as matiere_nom, m.coefficient,
                    COUNT(n.id) as nb_notes,
                    AVG(n.note) as moyenne_matiere,
                    MIN(n.note) as note_min,
                    MAX(n.note) as note_max
             FROM matieres m
             JOIN evaluations e ON m.id = e.matiere_id
             JOIN notes n ON e.id = n.evaluation_id
             WHERE e.annee_scolaire_id = ?
             " . ($periode_filter ? "AND e.periode = ?" : "") . "
             GROUP BY m.id, m.nom, m.coefficient
             ORDER BY moyenne_matiere DESC",
            array_filter([$current_year['id'], $periode_filter])
        )->fetchAll();
        
        // Top 10 des meilleurs élèves
        $data['meilleurs_eleves'] = $database->query(
            "SELECT e.id, e.nom, e.prenom, e.numero_matricule, c.nom as classe_nom,
                    AVG(n.note) as moyenne_generale,
                    COUNT(n.id) as nb_notes
             FROM eleves e
             JOIN inscriptions i ON e.id = i.eleve_id
             JOIN classes c ON i.classe_id = c.id
             JOIN notes n ON e.id = n.eleve_id
             JOIN evaluations ev ON n.evaluation_id = ev.id
             WHERE i.status = 'inscrit' AND i.annee_scolaire_id = ?
             " . ($periode_filter ? "AND ev.periode = ?" : "") . "
             GROUP BY e.id, e.nom, e.prenom, e.numero_matricule, c.nom
             HAVING nb_notes >= 3
             ORDER BY moyenne_generale DESC
             LIMIT 10",
            array_filter([$current_year['id'], $periode_filter])
        )->fetchAll();
        break;
        
    case 'notes':
        $filename = "rapport_notes_{$current_year['annee']}";
        
        // Notes détaillées par élève
        $where_conditions = ["e.annee_scolaire_id = ?"];
        $params = [$current_year['id']];
        
        if ($classe_filter) {
            $where_conditions[] = "i.classe_id = ?";
            $params[] = $classe_filter;
        }
        
        if ($periode_filter) {
            $where_conditions[] = "ev.periode = ?";
            $params[] = $periode_filter;
        }
        
        $where_clause = implode(' AND ', $where_conditions);
        
        $data['notes'] = $database->query(
            "SELECT e.nom, e.prenom, e.numero_matricule, c.nom as classe_nom,
                    m.nom as matiere_nom, ev.nom as evaluation_nom,
                    ev.type_evaluation, ev.periode, n.note, n.observation,
                    CONCAT(p.nom, ' ', p.prenom) as enseignant_nom
             FROM eleves e
             JOIN inscriptions i ON e.id = i.eleve_id
             JOIN classes c ON i.classe_id = c.id
             JOIN notes n ON e.id = n.eleve_id
             JOIN evaluations ev ON n.evaluation_id = ev.id
             JOIN matieres m ON ev.matiere_id = m.id
             LEFT JOIN personnel p ON ev.enseignant_id = p.id
             WHERE $where_clause AND i.status = 'inscrit'
             ORDER BY e.nom, e.prenom, m.nom, ev.date_evaluation",
            $params
        )->fetchAll();
        break;
        
    case 'statistiques':
        $filename = "statistiques_academiques_{$current_year['annee']}";
        
        // Statistiques générales
        $data['general'] = [
            'total_evaluations' => $database->query(
                "SELECT COUNT(*) as total FROM evaluations WHERE annee_scolaire_id = ?",
                [$current_year['id']]
            )->fetch()['total'],
            'moyenne_ecole' => round($database->query(
                "SELECT AVG(n.note) as moyenne FROM notes n 
                 JOIN evaluations e ON n.evaluation_id = e.id 
                 WHERE e.annee_scolaire_id = ?",
                [$current_year['id']]
            )->fetch()['moyenne'] ?? 0, 2),
            'taux_reussite' => round($database->query(
                "SELECT 
                    COUNT(CASE WHEN moyenne >= 10 THEN 1 END) * 100.0 / COUNT(*) as taux_reussite
                 FROM (
                    SELECT AVG(n.note) as moyenne
                    FROM notes n 
                    JOIN evaluations e ON n.evaluation_id = e.id 
                    WHERE e.annee_scolaire_id = ?
                    GROUP BY n.eleve_id
                 ) as moyennes_eleves",
                [$current_year['id']]
            )->fetch()['taux_reussite'] ?? 0, 1)
        ];
        
        // Répartition des mentions
        $data['mentions'] = $database->query(
            "SELECT 
                CASE 
                    WHEN moyenne >= 16 THEN 'Excellent'
                    WHEN moyenne >= 14 THEN 'Très bien'
                    WHEN moyenne >= 12 THEN 'Bien'
                    WHEN moyenne >= 10 THEN 'Satisfaisant'
                    WHEN moyenne >= 8 THEN 'Passable'
                    ELSE 'Insuffisant'
                END as mention,
                COUNT(*) as nombre
             FROM (
                SELECT AVG(n.note) as moyenne
                FROM notes n 
                JOIN evaluations e ON n.evaluation_id = e.id 
                WHERE e.annee_scolaire_id = ?
                " . ($periode_filter ? "AND e.periode = ?" : "") . "
                GROUP BY n.eleve_id
             ) as moyennes_eleves",
            array_filter([$current_year['id'], $periode_filter])
        )->fetchAll();
        break;
}

// Générer l'export selon le format
if ($format === 'pdf') {
    // Pour PDF, on affiche une page de prévisualisation
    include '../../../includes/header.php';
    ?>
    
    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
        <h1 class="h2">
            <i class="fas fa-file-pdf me-2"></i>
            Export PDF - <?php echo ucfirst($type); ?>
        </h1>
        <div class="btn-toolbar mb-2 mb-md-0">
            <div class="btn-group me-2">
                <a href="index.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left me-1"></i>
                    Retour aux rapports
                </a>
            </div>
            <div class="btn-group">
                <a href="?type=<?php echo $type; ?>&format=excel<?php echo $periode_filter ? '&periode=' . urlencode($periode_filter) : ''; ?><?php echo $classe_filter ? '&classe=' . $classe_filter : ''; ?>" class="btn btn-outline-success">
                    <i class="fas fa-file-excel me-1"></i>
                    Version Excel
                </a>
            </div>
        </div>
    </div>

    <div class="alert alert-info">
        <h5><i class="fas fa-info-circle me-2"></i>Prévisualisation du rapport</h5>
        <p class="mb-0">
            Ceci est une prévisualisation du rapport qui sera généré en PDF. 
            Cliquez sur "Télécharger PDF" pour obtenir le fichier final.
        </p>
    </div>

    <div class="card mb-4">
        <div class="card-header">
            <h5 class="mb-0">
                <i class="fas fa-chart-bar me-2"></i>
                Rapport de <?php echo ucfirst($type); ?> - <?php echo htmlspecialchars($current_year['annee']); ?>
            </h5>
        </div>
        <div class="card-body">
            <?php if ($type === 'performance'): ?>
                <!-- Performance par classe -->
                <h6>Performance par classe</h6>
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Classe</th>
                                <th>Niveau</th>
                                <th>Élèves évalués</th>
                                <th>Moyenne</th>
                                <th>Min</th>
                                <th>Max</th>
                                <th>Excellents</th>
                                <th>Admis</th>
                                <th>Échecs</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($data['classes'] as $classe): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($classe['classe_nom']); ?></td>
                                    <td><?php echo ucfirst($classe['niveau']); ?></td>
                                    <td><?php echo $classe['nb_eleves_evalues']; ?></td>
                                    <td><strong><?php echo round($classe['moyenne_classe'], 2); ?></strong></td>
                                    <td><?php echo $classe['note_min']; ?></td>
                                    <td><?php echo $classe['note_max']; ?></td>
                                    <td><span class="badge bg-success"><?php echo $classe['excellents']; ?></span></td>
                                    <td><span class="badge bg-primary"><?php echo $classe['admis']; ?></span></td>
                                    <td><span class="badge bg-danger"><?php echo $classe['echecs']; ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Performance par matière -->
                <h6 class="mt-4">Performance par matière</h6>
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Matière</th>
                                <th>Coefficient</th>
                                <th>Nombre de notes</th>
                                <th>Moyenne</th>
                                <th>Min</th>
                                <th>Max</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($data['matieres'] as $matiere): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($matiere['matiere_nom']); ?></td>
                                    <td><?php echo $matiere['coefficient']; ?></td>
                                    <td><?php echo $matiere['nb_notes']; ?></td>
                                    <td><strong><?php echo round($matiere['moyenne_matiere'], 2); ?></strong></td>
                                    <td><?php echo $matiere['note_min']; ?></td>
                                    <td><?php echo $matiere['note_max']; ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

            <?php elseif ($type === 'notes'): ?>
                <!-- Notes détaillées -->
                <h6>Notes détaillées par élève</h6>
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Élève</th>
                                <th>Matricule</th>
                                <th>Classe</th>
                                <th>Matière</th>
                                <th>Évaluation</th>
                                <th>Type</th>
                                <th>Période</th>
                                <th>Note</th>
                                <th>Enseignant</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($data['notes'] as $note): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($note['nom'] . ' ' . $note['prenom']); ?></td>
                                    <td><?php echo htmlspecialchars($note['numero_matricule']); ?></td>
                                    <td><?php echo htmlspecialchars($note['classe_nom']); ?></td>
                                    <td><?php echo htmlspecialchars($note['matiere_nom']); ?></td>
                                    <td><?php echo htmlspecialchars($note['evaluation_nom']); ?></td>
                                    <td><span class="badge bg-secondary"><?php echo ucfirst($note['type_evaluation']); ?></span></td>
                                    <td><?php echo htmlspecialchars($note['periode']); ?></td>
                                    <td><span class="badge bg-<?php echo $note['note'] >= 10 ? 'success' : 'danger'; ?>"><?php echo $note['note']; ?>/20</span></td>
                                    <td><?php echo htmlspecialchars($note['enseignant_nom'] ?: '-'); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

            <?php elseif ($type === 'statistiques'): ?>
                <!-- Statistiques générales -->
                <h6>Statistiques générales</h6>
                <div class="row text-center mb-4">
                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-body">
                                <h3 class="text-primary"><?php echo $data['general']['total_evaluations']; ?></h3>
                                <p class="mb-0">Total évaluations</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-body">
                                <h3 class="text-success"><?php echo $data['general']['moyenne_ecole']; ?></h3>
                                <p class="mb-0">Moyenne générale</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-body">
                                <h3 class="text-info"><?php echo $data['general']['taux_reussite']; ?>%</h3>
                                <p class="mb-0">Taux de réussite</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Répartition des mentions -->
                <h6>Répartition des mentions</h6>
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Mention</th>
                                <th>Nombre d'élèves</th>
                                <th>Pourcentage</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $total_eleves = array_sum(array_column($data['mentions'], 'nombre'));
                            foreach ($data['mentions'] as $mention): 
                                $pourcentage = $total_eleves > 0 ? round(($mention['nombre'] / $total_eleves) * 100, 1) : 0;
                            ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($mention['mention']); ?></td>
                                    <td><?php echo $mention['nombre']; ?></td>
                                    <td><?php echo $pourcentage; ?>%</td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="text-center">
        <a href="?type=<?php echo $type; ?>&format=pdf&download=1<?php echo $periode_filter ? '&periode=' . urlencode($periode_filter) : ''; ?><?php echo $classe_filter ? '&classe=' . $classe_filter : ''; ?>" class="btn btn-primary btn-lg">
            <i class="fas fa-download me-2"></i>
            Télécharger PDF
        </a>
    </div>

    <?php include '../../../includes/footer.php'; ?>

<?php
} elseif ($format === 'excel') {
    // Pour Excel, on génère un fichier CSV
    $filename .= '.csv';
    
    // Headers pour le téléchargement
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    
    // Créer le fichier CSV
    $output = fopen('php://output', 'w');
    
    // BOM pour UTF-8
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    if ($type === 'performance') {
        // En-têtes pour performance
        fputcsv($output, ['Rapport de Performance - ' . $current_year['annee']]);
        fputcsv($output, []);
        
        // Performance par classe
        fputcsv($output, ['PERFORMANCE PAR CLASSE']);
        fputcsv($output, ['Classe', 'Niveau', 'Élèves évalués', 'Moyenne', 'Min', 'Max', 'Excellents', 'Admis', 'Échecs']);
        
        foreach ($data['classes'] as $classe) {
            fputcsv($output, [
                $classe['classe_nom'],
                ucfirst($classe['niveau']),
                $classe['nb_eleves_evalues'],
                round($classe['moyenne_classe'], 2),
                $classe['note_min'],
                $classe['note_max'],
                $classe['excellents'],
                $classe['admis'],
                $classe['echecs']
            ]);
        }
        
        fputcsv($output, []);
        
        // Performance par matière
        fputcsv($output, ['PERFORMANCE PAR MATIÈRE']);
        fputcsv($output, ['Matière', 'Coefficient', 'Nombre de notes', 'Moyenne', 'Min', 'Max']);
        
        foreach ($data['matieres'] as $matiere) {
            fputcsv($output, [
                $matiere['matiere_nom'],
                $matiere['coefficient'],
                $matiere['nb_notes'],
                round($matiere['moyenne_matiere'], 2),
                $matiere['note_min'],
                $matiere['note_max']
            ]);
        }
        
    } elseif ($type === 'notes') {
        // En-têtes pour notes
        fputcsv($output, ['Rapport des Notes - ' . $current_year['annee']]);
        fputcsv($output, []);
        fputcsv($output, ['Élève', 'Matricule', 'Classe', 'Matière', 'Évaluation', 'Type', 'Période', 'Note', 'Enseignant']);
        
        foreach ($data['notes'] as $note) {
            fputcsv($output, [
                $note['nom'] . ' ' . $note['prenom'],
                $note['numero_matricule'],
                $note['classe_nom'],
                $note['matiere_nom'],
                $note['evaluation_nom'],
                ucfirst($note['type_evaluation']),
                $note['periode'],
                $note['note'],
                $note['enseignant_nom'] ?: '-'
            ]);
        }
        
    } elseif ($type === 'statistiques') {
        // En-têtes pour statistiques
        fputcsv($output, ['Statistiques Académiques - ' . $current_year['annee']]);
        fputcsv($output, []);
        fputcsv($output, ['Métrique', 'Valeur']);
        fputcsv($output, ['Total évaluations', $data['general']['total_evaluations']]);
        fputcsv($output, ['Moyenne générale', $data['general']['moyenne_ecole']]);
        fputcsv($output, ['Taux de réussite', $data['general']['taux_reussite'] . '%']);
        fputcsv($output, []);
        fputcsv($output, ['RÉPARTITION DES MENTIONS']);
        fputcsv($output, ['Mention', 'Nombre d\'élèves']);
        
        foreach ($data['mentions'] as $mention) {
            fputcsv($output, [$mention['mention'], $mention['nombre']]);
        }
    }
    
    fclose($output);
    exit;
}
?>
