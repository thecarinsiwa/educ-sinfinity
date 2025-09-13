<?php
/**
 * Récupérer la liste des élèves pour la génération de cartes
 * Application de gestion scolaire - République Démocratique du Congo
 */

require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/config/database.php';
require_once dirname(__DIR__, 2) . '/includes/functions.php';
require_once dirname(__DIR__, 2) . '/includes/permissions-pages.php';
requireLogin();

requirePagePermissionFromDB('cartes_eleves', 'get-students', 'read', '../dashboard.php');

$classe_id = $_POST['classe_id'] ?? 0;
$generation_type = $_POST['generation_type'] ?? 'all';

if (!$classe_id) {
    echo '<p class="text-muted">Sélectionnez une classe pour voir les élèves</p>';
    exit;
}

// Récupérer l'année scolaire courante
$current_year = $database->query("SELECT * FROM annees_scolaires WHERE status = 'active'")->fetch();

// Récupérer les élèves de la classe
$sql = "SELECT e.id, e.nom, e.prenom, e.numero_matricule, e.photo,
               ce.id as carte_id, ce.statut as carte_statut, ce.date_generation
        FROM eleves e
        LEFT JOIN inscriptions i ON e.id = i.eleve_id
        LEFT JOIN carte_eleve ce ON e.id = ce.eleve_id AND ce.annee_scolaire_id = ?
        WHERE e.classe_id = ? AND i.status = 'inscrit'
        ORDER BY e.nom, e.prenom";

$students = $database->query($sql, [$current_year['id'], $classe_id])->fetchAll();

if (empty($students)) {
    echo '<p class="text-muted">Aucun élève trouvé dans cette classe</p>';
    exit;
}

echo '<div class="row">';
foreach ($students as $student) {
    $has_card = !empty($student['carte_id']);
    $card_status = $student['carte_statut'] ?? '';
    
    // Déterminer si l'élève doit être inclus selon le type de génération
    $include_student = false;
    switch ($generation_type) {
        case 'all':
            $include_student = true;
            break;
        case 'without_card':
            $include_student = !$has_card;
            break;
        case 'regenerate':
            $include_student = true;
            break;
    }
    
    if (!$include_student) continue;
    
    $status_badge = '';
    if ($has_card) {
        $status_class = match($card_status) {
            'active' => 'success',
            'expiree' => 'warning',
            'suspendue' => 'danger',
            'archivée' => 'secondary',
            default => 'secondary'
        };
        $status_badge = "<span class='badge bg-$status_class ms-2'>" . ucfirst($card_status) . "</span>";
    } else {
        $status_badge = "<span class='badge bg-light text-dark ms-2'>Sans carte</span>";
    }
    
    echo '<div class="col-md-6 mb-2">';
    echo '<div class="form-check">';
    echo '<input class="form-check-input" type="checkbox" name="student_ids[]" value="' . $student['id'] . '" id="student_' . $student['id'] . '">';
    echo '<label class="form-check-label d-flex align-items-center" for="student_' . $student['id'] . '">';
    
    // Photo de l'élève
    if ($student['photo']) {
        echo '<img src="../../uploads/photos/' . htmlspecialchars($student['photo']) . '" class="rounded-circle me-2" width="30" height="30" alt="Photo">';
    } else {
        echo '<div class="bg-light rounded-circle d-flex align-items-center justify-content-center me-2" style="width: 30px; height: 30px;">';
        echo '<i class="mdi mdi-account text-muted"></i>';
        echo '</div>';
    }
    
    // Informations de l'élève
    echo '<div>';
    echo '<div class="fw-medium">' . htmlspecialchars($student['nom'] . ' ' . $student['prenom']) . '</div>';
    echo '<small class="text-muted">Matricule: ' . htmlspecialchars($student['numero_matricule']) . '</small>';
    echo $status_badge;
    echo '</div>';
    
    echo '</label>';
    echo '</div>';
    echo '</div>';
}

echo '</div>';

// Boutons d'action
echo '<div class="mt-3">';
echo '<button type="button" class="btn btn-sm btn-outline-primary" onclick="selectAllStudents()">Tout sélectionner</button>';
echo '<button type="button" class="btn btn-sm btn-outline-secondary ms-2" onclick="deselectAllStudents()">Tout désélectionner</button>';
echo '</div>';

// JavaScript pour la sélection
echo '<script>';
echo 'function selectAllStudents() {';
echo '    document.querySelectorAll("input[name=\"student_ids[]\"]").forEach(input => input.checked = true);';
echo '}';
echo 'function deselectAllStudents() {';
echo '    document.querySelectorAll("input[name=\"student_ids[]\"]").forEach(input => input.checked = false);';
echo '}';
echo '</script>';
?>
