<?php
/**
 * Module d'évaluations - Aperçu du bulletin
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

// Récupérer l'année scolaire active
$current_year = $database->query("SELECT * FROM annees_scolaires WHERE status = 'active' LIMIT 1")->fetch();
if (!$current_year) {
    showMessage('error', 'Aucune année scolaire active trouvée.');
    redirectTo('../../../index.php');
}

// Récupérer les paramètres
$eleve_id = (int)($_GET['eleve'] ?? 0);
$periode = sanitizeInput($_GET['periode'] ?? '');

if (!$eleve_id || !$periode) {
    showMessage('error', 'Paramètres manquants pour l\'aperçu du bulletin.');
    redirectTo('index.php');
}

// Récupérer les informations de l'élève
$eleve = $database->query(
    "SELECT e.*, c.nom as classe_nom, c.niveau, c.section, c.id as classe_id
     FROM eleves e
     JOIN inscriptions i ON e.id = i.eleve_id
     JOIN classes c ON i.classe_id = c.id
     WHERE e.id = ? AND i.annee_scolaire_id = ? AND i.status = 'inscrit'",
    [$eleve_id, $current_year['id']]
)->fetch();

if (!$eleve) {
    showMessage('error', 'Élève non trouvé ou non inscrit pour cette année.');
    redirectTo('index.php');
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
    [$eleve['classe_id'], $current_year['id'], $periode]
)->fetch();

// Utiliser le template de bulletin existant
$format = 'view'; // Mode aperçu
include 'bulletin_template.php';
?>
