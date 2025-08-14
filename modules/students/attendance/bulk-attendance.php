<?php
/**
 * Saisie en masse des absences et retards
 * Application de gestion scolaire - République Démocratique du Congo
 */

require_once '../../../config/config.php';
require_once '../../../config/database.php';
require_once '../../../includes/functions.php';

// Vérifier l'authentification et les permissions
requireLogin();
if (!checkPermission('students')) {
    redirectTo('../../../login.php');
}

$page_title = "Saisie en masse des absences";

// Récupérer l'année scolaire active
$current_year = $database->query("SELECT * FROM annees_scolaires WHERE status = 'active' LIMIT 1")->fetch();

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $classe_id = (int)($_POST['classe_id'] ?? 0);
        $date_saisie = sanitizeInput($_POST['date_saisie'] ?? '');
        $heure_saisie = sanitizeInput($_POST['heure_saisie'] ?? '');
        $attendance_data = $_POST['attendance'] ?? [];
        
        // Validation
        if (!$classe_id) {
            throw new Exception('Classe requise');
        }
        
        if (!$date_saisie || !$heure_saisie) {
            throw new Exception('Date et heure requises');
        }
        
        if (empty($attendance_data)) {
            throw new Exception('Aucune donnée de présence saisie');
        }
        
        // Vérifier que la classe existe
        $classe = $database->query(
            "SELECT * FROM classes WHERE id = ? AND annee_scolaire_id = ?",
            [$classe_id, $current_year['id'] ?? 0]
        )->fetch();
        
        if (!$classe) {
            throw new Exception('Classe non trouvée');
        }
        
        // Combiner date et heure
        $datetime_saisie = $date_saisie . ' ' . $heure_saisie;
        
        // Commencer une transaction
        $database->beginTransaction();
        
        $created_count = 0;
        $errors = [];
        
        try {
            foreach ($attendance_data as $eleve_id => $data) {
                $eleve_id = (int)$eleve_id;
                $status = $data['status'] ?? 'present';
                $motif = sanitizeInput($data['motif'] ?? '');
                $duree_retard = (int)($data['duree_retard'] ?? 0);
                
                // Ignorer les élèves présents
                if ($status === 'present') {
                    continue;
                }
                
                // Vérifier que l'élève existe et est inscrit dans cette classe
                $eleve = $database->query(
                    "SELECT e.*, i.classe_id
                     FROM eleves e
                     JOIN inscriptions i ON e.id = i.eleve_id
                     WHERE e.id = ? AND i.classe_id = ? AND i.status = 'inscrit' AND i.annee_scolaire_id = ?",
                    [$eleve_id, $classe_id, $current_year['id'] ?? 0]
                )->fetch();
                
                if (!$eleve) {
                    $errors[] = "Élève ID $eleve_id non trouvé dans cette classe";
                    continue;
                }
                
                // Vérifier qu'il n'y a pas déjà un enregistrement pour cette date
                $existing = $database->query(
                    "SELECT id FROM absences WHERE eleve_id = ? AND DATE(date_absence) = ?",
                    [$eleve_id, $date_saisie]
                )->fetch();
                
                if ($existing) {
                    $errors[] = "Enregistrement déjà existant pour {$eleve['nom']} {$eleve['prenom']} à cette date";
                    continue;
                }
                
                // Déterminer le type d'absence
                $type_absence = $status;
                if ($status === 'retard' && $duree_retard <= 0) {
                    $duree_retard = 15; // Durée par défaut
                }
                
                // Insérer l'enregistrement
                $database->execute(
                    "INSERT INTO absences (eleve_id, classe_id, type_absence, date_absence, motif, duree_retard, created_at) 
                     VALUES (?, ?, ?, ?, ?, ?, NOW())",
                    [$eleve_id, $classe_id, $type_absence, $datetime_saisie, $motif, $duree_retard]
                );
                
                $created_count++;
            }
            
            // Enregistrer l'action dans l'historique
            logUserAction(
                'bulk_attendance',
                'attendance',
                "Saisie en masse - Classe: {$classe['nom']}, Date: $date_saisie, Enregistrements: $created_count",
                null
            );
            
            $database->commit();
            
            $message = "Saisie terminée : $created_count enregistrement(s) créé(s)";
            if (!empty($errors)) {
                $message .= ". Erreurs : " . count($errors);
            }
            
            showMessage('success', $message);
            
            // Afficher les erreurs s'il y en a
            if (!empty($errors)) {
                foreach ($errors as $error) {
                    showMessage('warning', $error);
                }
            }
            
        } catch (Exception $e) {
            $database->rollback();
            throw $e;
        }
        
    } catch (Exception $e) {
        showMessage('error', $e->getMessage());
    }
}

// Récupérer les classes
$classes = $database->query(
    "SELECT * FROM classes WHERE annee_scolaire_id = ? ORDER BY niveau, nom",
    [$current_year['id'] ?? 0]
)->fetchAll();

include '../../../includes/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">
        <i class="fas fa-users-cog me-2"></i>
        Saisie en masse des absences
    </h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <div class="btn-group me-2">
            <a href="index.php" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-1"></i>
                Retour à la liste
            </a>
        </div>
        <div class="btn-group">
            <a href="add-absence.php" class="btn btn-outline-danger">
                <i class="fas fa-user-times me-1"></i>
                Absence individuelle
            </a>
            <a href="add-delay.php" class="btn btn-outline-warning">
                <i class="fas fa-clock me-1"></i>
                Retard individuel
            </a>
        </div>
    </div>
</div>

<div class="row">
    <!-- Configuration de la saisie -->
    <div class="col-lg-4 mb-4">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-cog me-2"></i>
                    Configuration
                </h5>
            </div>
            <div class="card-body">
                <form id="configForm">
                    <div class="mb-3">
                        <label for="classe_select" class="form-label">Classe <span class="text-danger">*</span></label>
                        <select class="form-select" id="classe_select" required>
                            <option value="">Sélectionner une classe</option>
                            <?php foreach ($classes as $class): ?>
                                <option value="<?php echo $class['id']; ?>">
                                    <?php echo htmlspecialchars($class['niveau'] . ' - ' . $class['nom']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="date_saisie" class="form-label">Date <span class="text-danger">*</span></label>
                        <input type="date" class="form-control" id="date_saisie" 
                               value="<?php echo date('Y-m-d'); ?>" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="heure_saisie" class="form-label">Heure <span class="text-danger">*</span></label>
                        <input type="time" class="form-control" id="heure_saisie" 
                               value="<?php echo date('H:i'); ?>" required>
                    </div>
                    
                    <div class="d-grid">
                        <button type="button" class="btn btn-primary" onclick="loadStudents()">
                            <i class="fas fa-users me-1"></i>
                            Charger les élèves
                        </button>
                    </div>
                </form>
            </div>
        </div>
        
        <div class="card mt-3">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-info-circle me-2"></i>
                    Instructions
                </h5>
            </div>
            <div class="card-body">
                <ol class="small">
                    <li>Sélectionnez la classe et la date/heure</li>
                    <li>Cliquez sur "Charger les élèves"</li>
                    <li>Marquez les absents et retardataires</li>
                    <li>Ajoutez les motifs si nécessaire</li>
                    <li>Cliquez sur "Enregistrer la saisie"</li>
                </ol>
                
                <div class="mt-3">
                    <h6>Légende :</h6>
                    <div class="d-flex flex-wrap gap-2">
                        <span class="badge bg-success">Présent</span>
                        <span class="badge bg-danger">Absent</span>
                        <span class="badge bg-warning">Retard</span>
                        <span class="badge bg-info">Justifié</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Liste des élèves -->
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">
                    <i class="fas fa-list me-2"></i>
                    Liste des élèves
                </h5>
                <div class="btn-group btn-group-sm" role="group">
                    <button type="button" class="btn btn-outline-success" onclick="markAll('present')">
                        Tous présents
                    </button>
                    <button type="button" class="btn btn-outline-danger" onclick="markAll('absence')">
                        Tous absents
                    </button>
                </div>
            </div>
            <div class="card-body">
                <div id="studentsContainer">
                    <div class="text-center py-5">
                        <i class="fas fa-users fa-3x text-muted mb-3"></i>
                        <p class="text-muted">Sélectionnez une classe et cliquez sur "Charger les élèves" pour commencer.</p>
                    </div>
                </div>
                
                <div id="bulkActions" class="mt-3" style="display: none;">
                    <form method="POST" id="bulkForm">
                        <input type="hidden" id="bulk_classe_id" name="classe_id">
                        <input type="hidden" id="bulk_date_saisie" name="date_saisie">
                        <input type="hidden" id="bulk_heure_saisie" name="heure_saisie">
                        
                        <div id="attendanceData"></div>
                        
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <span id="summaryText" class="text-muted"></span>
                            </div>
                            <div>
                                <button type="button" class="btn btn-secondary me-2" onclick="resetForm()">
                                    <i class="fas fa-undo me-1"></i>
                                    Réinitialiser
                                </button>
                                <button type="submit" class="btn btn-success">
                                    <i class="fas fa-save me-1"></i>
                                    Enregistrer la saisie
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.student-row {
    border: 1px solid #dee2e6;
    border-radius: 8px;
    padding: 15px;
    margin-bottom: 10px;
    transition: all 0.3s ease;
}

.student-row:hover {
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.student-row.absent {
    border-color: #dc3545;
    background-color: #f8d7da;
}

.student-row.retard {
    border-color: #ffc107;
    background-color: #fff3cd;
}

.student-row.present {
    border-color: #28a745;
    background-color: #d4edda;
}

.status-buttons .btn {
    margin-right: 5px;
    margin-bottom: 5px;
}

.motif-input {
    display: none;
    margin-top: 10px;
}

.motif-input.show {
    display: block;
}
</style>

<script>
let studentsData = [];

// Charger les élèves de la classe sélectionnée
function loadStudents() {
    const classeId = document.getElementById('classe_select').value;
    const dateSaisie = document.getElementById('date_saisie').value;
    const heureSaisie = document.getElementById('heure_saisie').value;
    
    if (!classeId || !dateSaisie || !heureSaisie) {
        alert('Veuillez remplir tous les champs de configuration');
        return;
    }
    
    const container = document.getElementById('studentsContainer');
    container.innerHTML = '<div class="text-center py-3"><div class="spinner-border" role="status"></div><p class="mt-2">Chargement des élèves...</p></div>';
    
    // Charger les élèves via AJAX
    fetch(`get-students.php?classe_id=${classeId}&include_attendance=1&date=${dateSaisie}`)
        .then(response => response.json())
        .then(data => {
            if (data.success && data.students) {
                studentsData = data.students;
                displayStudents();
                
                // Mettre à jour les champs cachés
                document.getElementById('bulk_classe_id').value = classeId;
                document.getElementById('bulk_date_saisie').value = dateSaisie;
                document.getElementById('bulk_heure_saisie').value = heureSaisie;
                
                document.getElementById('bulkActions').style.display = 'block';
            } else {
                container.innerHTML = '<div class="alert alert-warning">Aucun élève trouvé dans cette classe.</div>';
            }
        })
        .catch(error => {
            console.error('Erreur:', error);
            container.innerHTML = '<div class="alert alert-danger">Erreur lors du chargement des élèves.</div>';
        });
}

// Afficher la liste des élèves
function displayStudents() {
    const container = document.getElementById('studentsContainer');
    let html = '';
    
    studentsData.forEach((student, index) => {
        const hasExisting = student.existing_attendance;
        const statusClass = hasExisting ? 'border-warning' : '';
        
        html += `
            <div class="student-row ${statusClass}" id="student_${student.id}">
                <div class="row align-items-center">
                    <div class="col-md-4">
                        <div class="d-flex align-items-center">
                            <div class="me-3">
                                <img src="../../../assets/images/avatar-placeholder.png" 
                                     class="rounded-circle" width="40" height="40" 
                                     onerror="this.src='data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iNDAiIGhlaWdodD0iNDAiIHZpZXdCb3g9IjAgMCA0MCA0MCIgZmlsbD0ibm9uZSIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj4KPGNpcmNsZSBjeD0iMjAiIGN5PSIyMCIgcj0iMjAiIGZpbGw9IiNkZWUyZTYiLz4KPHN2ZyB3aWR0aD0iMjQiIGhlaWdodD0iMjQiIHZpZXdCb3g9IjAgMCAyNCAyNCIgZmlsbD0ibm9uZSIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj4KPHBhdGggZD0iTTEyIDEyQzE0LjIwOTEgMTIgMTYgMTAuMjA5MSAxNiA4QzE2IDUuNzkwODYgMTQuMjA5MSA0IDEyIDRDOS43OTA4NiA0IDggNS43OTA4NiA4IDhDOCAxMC4yMDkxIDkuNzkwODYgMTIgMTIgMTJaIiBmaWxsPSIjNmM3NTdkIi8+CjxwYXRoIGQ9Ik0xMiAxNEM5LjMzIDEzLjk5IDcuMDEgMTUuNzggNyAxOC42N1YyMEgxN1YxOC42N0MxNi45OSAxNS43OCA0LjY3IDEzLjk5IDEyIDE0WiIgZmlsbD0iIzZjNzU3ZCIvPgo8L3N2Zz4KPC9zdmc+'">
                            </div>
                            <div>
                                <h6 class="mb-0">${student.nom} ${student.prenom}</h6>
                                <small class="text-muted">${student.numero_matricule}</small>
                                ${hasExisting ? '<br><small class="text-warning"><i class="fas fa-exclamation-triangle"></i> Déjà enregistré</small>' : ''}
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-5">
                        <div class="status-buttons">
                            <button type="button" class="btn btn-sm btn-outline-success" 
                                    onclick="setStatus(${student.id}, 'present')" 
                                    id="btn_present_${student.id}">
                                <i class="fas fa-check"></i> Présent
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-danger" 
                                    onclick="setStatus(${student.id}, 'absence')" 
                                    id="btn_absence_${student.id}">
                                <i class="fas fa-times"></i> Absent
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-warning" 
                                    onclick="setStatus(${student.id}, 'retard')" 
                                    id="btn_retard_${student.id}">
                                <i class="fas fa-clock"></i> Retard
                            </button>
                        </div>
                    </div>
                    
                    <div class="col-md-3">
                        <div class="motif-input" id="motif_${student.id}">
                            <input type="text" class="form-control form-control-sm" 
                                   placeholder="Motif (optionnel)" 
                                   id="motif_input_${student.id}">
                            <div class="retard-duration mt-2" id="duration_${student.id}" style="display: none;">
                                <input type="number" class="form-control form-control-sm" 
                                       placeholder="Durée (min)" min="1" max="480" 
                                       id="duration_input_${student.id}" value="15">
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        `;
    });
    
    container.innerHTML = html;
    
    // Marquer tous comme présents par défaut
    studentsData.forEach(student => {
        setStatus(student.id, 'present', false);
    });
    
    updateSummary();
}

// Définir le statut d'un élève
function setStatus(studentId, status, updateSummary = true) {
    const studentRow = document.getElementById(`student_${studentId}`);
    const motifDiv = document.getElementById(`motif_${studentId}`);
    const durationDiv = document.getElementById(`duration_${studentId}`);
    
    // Réinitialiser les classes
    studentRow.className = 'student-row';
    
    // Réinitialiser les boutons
    ['present', 'absence', 'retard'].forEach(s => {
        const btn = document.getElementById(`btn_${s}_${studentId}`);
        btn.className = `btn btn-sm btn-outline-${s === 'present' ? 'success' : s === 'absence' ? 'danger' : 'warning'}`;
    });
    
    // Activer le bouton sélectionné
    const activeBtn = document.getElementById(`btn_${status}_${studentId}`);
    activeBtn.className = `btn btn-sm btn-${status === 'present' ? 'success' : status === 'absence' ? 'danger' : 'warning'}`;
    
    // Appliquer la classe au conteneur
    studentRow.classList.add(status);
    
    // Afficher/masquer les champs selon le statut
    if (status === 'present') {
        motifDiv.classList.remove('show');
        durationDiv.style.display = 'none';
    } else {
        motifDiv.classList.add('show');
        if (status === 'retard') {
            durationDiv.style.display = 'block';
        } else {
            durationDiv.style.display = 'none';
        }
    }
    
    // Mettre à jour les données
    const studentIndex = studentsData.findIndex(s => s.id == studentId);
    if (studentIndex !== -1) {
        studentsData[studentIndex].status = status;
    }
    
    if (updateSummary) {
        updateSummary();
        generateAttendanceData();
    }
}

// Marquer tous les élèves avec le même statut
function markAll(status) {
    studentsData.forEach(student => {
        setStatus(student.id, status, false);
    });
    updateSummary();
    generateAttendanceData();
}

// Mettre à jour le résumé
function updateSummary() {
    const present = studentsData.filter(s => s.status === 'present').length;
    const absent = studentsData.filter(s => s.status === 'absence').length;
    const retard = studentsData.filter(s => s.status === 'retard').length;
    const total = studentsData.length;
    
    const summaryText = `Total: ${total} | Présents: ${present} | Absents: ${absent} | Retards: ${retard}`;
    document.getElementById('summaryText').textContent = summaryText;
}

// Générer les données d'attendance pour le formulaire
function generateAttendanceData() {
    const container = document.getElementById('attendanceData');
    let html = '';
    
    studentsData.forEach(student => {
        if (student.status !== 'present') {
            const motif = document.getElementById(`motif_input_${student.id}`)?.value || '';
            const duration = document.getElementById(`duration_input_${student.id}`)?.value || '';
            
            html += `
                <input type="hidden" name="attendance[${student.id}][status]" value="${student.status}">
                <input type="hidden" name="attendance[${student.id}][motif]" value="${motif}">
                <input type="hidden" name="attendance[${student.id}][duree_retard]" value="${duration}">
            `;
        }
    });
    
    container.innerHTML = html;
}

// Réinitialiser le formulaire
function resetForm() {
    document.getElementById('studentsContainer').innerHTML = `
        <div class="text-center py-5">
            <i class="fas fa-users fa-3x text-muted mb-3"></i>
            <p class="text-muted">Sélectionnez une classe et cliquez sur "Charger les élèves" pour commencer.</p>
        </div>
    `;
    document.getElementById('bulkActions').style.display = 'none';
    document.getElementById('configForm').reset();
    document.getElementById('date_saisie').value = '<?php echo date('Y-m-d'); ?>';
    document.getElementById('heure_saisie').value = '<?php echo date('H:i'); ?>';
    studentsData = [];
}

// Validation avant soumission
document.getElementById('bulkForm').addEventListener('submit', function(e) {
    generateAttendanceData();
    
    const incidents = studentsData.filter(s => s.status !== 'present').length;
    
    if (incidents === 0) {
        if (!confirm('Aucun incident à enregistrer (tous les élèves sont présents). Continuer ?')) {
            e.preventDefault();
            return false;
        }
    } else {
        if (!confirm(`Enregistrer ${incidents} incident(s) d'attendance ?`)) {
            e.preventDefault();
            return false;
        }
    }
});

// Mise à jour automatique des données lors de la saisie des motifs
document.addEventListener('input', function(e) {
    if (e.target.id.startsWith('motif_input_') || e.target.id.startsWith('duration_input_')) {
        generateAttendanceData();
    }
});
</script>

<?php include '../../../includes/footer.php'; ?>
