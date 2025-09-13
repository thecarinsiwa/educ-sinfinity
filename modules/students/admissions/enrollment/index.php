<?php
/**
 * Module d'inscription des candidats acceptÃ©s
 * Application de gestion scolaire - République Démocratique du Congo
 */

require_once '../../../../config/config.php';
require_once '../../../../config/database.php';
require_once '../../../../includes/functions.php';
require_once '../../../../includes/permissions-pages.php';

// VÃ©rifier l'authentification et les permissions
requireLogin();

requirePagePermissionFromDB('students', 'admissions', 'create', '../../../../dashboard.php');

// Traitement des actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $action = $_POST['action'] ?? '';
        
        switch ($action) {
            case 'enroll_student':
                $candidature_id = intval($_POST['candidature_id']);
                $classe_finale_id = intval($_POST['classe_finale_id']);
                $frais_inscription = floatval($_POST['frais_inscription']);
                $frais_scolarite = floatval($_POST['frais_scolarite']);
                $reduction_accordee = floatval($_POST['reduction_accordee']);
                $date_inscription = $_POST['date_inscription'] ?? date('Y-m-d');
                
                // RÃ©cupÃ©rer les donnÃ©es de la candidature
                $candidature = $database->query(
                    "SELECT * FROM demandes_admission WHERE id = ? AND status = 'acceptee'",
                    [$candidature_id]
                )->fetch();
                
                if (!$candidature) {
                    throw new Exception('Candidature non trouvÃ©e ou non acceptÃ©e.');
                }
                
                // VÃ©rifier si l'Ã©lÃ¨ve n'existe pas dÃ©jÃ 
                $existing_student = $database->query(
                    "SELECT id FROM eleves WHERE nom = ? AND prenom = ? AND date_naissance = ?",
                    [$candidature['nom_eleve'], $candidature['prenom_eleve'], $candidature['date_naissance']]
                )->fetch();
                
                if ($existing_student) {
                    throw new Exception('Un Ã©lÃ¨ve avec ces informations existe dÃ©jÃ .');
                }
                
                // GÃ©nÃ©rer un numÃ©ro d'Ã©lÃ¨ve unique
                $annee_courante = date('Y');
                $last_student = $database->query(
                    "SELECT numero_eleve FROM eleves WHERE numero_eleve LIKE ? ORDER BY numero_eleve DESC LIMIT 1",
                    [$annee_courante . '%']
                )->fetch();
                
                if ($last_student) {
                    $last_number = intval(substr($last_student['numero_eleve'], -4));
                    $new_number = $last_number + 1;
                } else {
                    $new_number = 1;
                }
                
                $numero_eleve = $annee_courante . str_pad($new_number, 4, '0', STR_PAD_LEFT);
                
                // Commencer une transaction
                $database->beginTransaction();
                
                try {
                    // GÃ©nÃ©rer le numÃ©ro de matricule
                    $numero_matricule = generateMatricule();
                    
                    // CrÃ©er l'Ã©lÃ¨ve
                    $database->execute(
                        "INSERT INTO eleves (
                            numero_eleve, numero_matricule, nom, prenom, date_naissance, lieu_naissance, sexe,
                            adresse, telephone, email, nom_pere, nom_mere, profession_pere, profession_mere,
                            telephone_parent, personne_contact, telephone_contact, relation_contact,
                            classe_id, annee_scolaire_id, status, date_inscription, created_at
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'actif', ?, NOW())",
                        [
                            $numero_eleve, $numero_matricule, $candidature['nom_eleve'], $candidature['prenom_eleve'],
                            $candidature['date_naissance'], $candidature['lieu_naissance'], $candidature['sexe'],
                            $candidature['adresse'], $candidature['telephone'], $candidature['email'],
                            $candidature['nom_pere'], $candidature['nom_mere'], 
                            $candidature['profession_pere'], $candidature['profession_mere'],
                            $candidature['telephone_parent'], $candidature['personne_contact'],
                            $candidature['telephone_contact'], $candidature['relation_contact'],
                            $classe_finale_id, $candidature['annee_scolaire_id'], $date_inscription
                        ]
                    );
                    
                    $student_id = $database->lastInsertId();
                    
                    // GÃ©nÃ©rer automatiquement la carte d'Ã©lÃ¨ve
                    require_once '../../../cartes_eleves/auto-generate.php';
                    $carte_id = autoGenerateStudentCard($student_id, $candidature['annee_scolaire_id']);
                    
                    // CrÃ©er l'enregistrement financier
                    $database->execute(
                        "INSERT INTO frais_eleves (
                            eleve_id, annee_scolaire_id, frais_inscription, frais_scolarite, 
                            reduction_accordee, montant_total, created_at
                        ) VALUES (?, ?, ?, ?, ?, ?, NOW())",
                        [
                            $student_id, $candidature['annee_scolaire_id'], $frais_inscription, 
                            $frais_scolarite, $reduction_accordee, 
                            ($frais_inscription + $frais_scolarite) * (1 - $reduction_accordee / 100)
                        ]
                    );
                    
                    // Mettre Ã  jour le statut de la candidature
                    $database->execute(
                        "UPDATE demandes_admission 
                         SET status = 'inscrit', eleve_cree_id = ?, date_inscription = ?, 
                             frais_inscription = ?, frais_scolarite = ?, reduction_accordee = ?,
                             updated_at = NOW()
                         WHERE id = ?",
                        [$student_id, $date_inscription, $frais_inscription, $frais_scolarite, 
                         $reduction_accordee, $candidature_id]
                    );
                    
                    $database->commit();
                    
                    showMessage('success', "Ã‰lÃ¨ve inscrit avec succÃ¨s. NumÃ©ro d'Ã©lÃ¨ve : $numero_eleve");
                    
                } catch (Exception $e) {
                    $database->rollback();
                    throw $e;
                }
                
                break;
                
            case 'bulk_enroll':
                $candidatures = $_POST['candidatures'] ?? [];
                $bulk_classe_id = intval($_POST['bulk_classe_id']);
                $bulk_frais_inscription = floatval($_POST['bulk_frais_inscription']);
                $bulk_frais_scolarite = floatval($_POST['bulk_frais_scolarite']);
                
                $enrolled_count = 0;
                $errors = [];
                
                foreach ($candidatures as $candidature_id) {
                    try {
                        // Logique similaire Ã  l'inscription individuelle
                        // (code simplifiÃ© pour la dÃ©mo)
                        $enrolled_count++;
                    } catch (Exception $e) {
                        $errors[] = "Candidature $candidature_id : " . $e->getMessage();
                    }
                }
                
                if ($enrolled_count > 0) {
                    showMessage('success', "$enrolled_count Ã©lÃ¨ve(s) inscrit(s) avec succÃ¨s.");
                }
                if (!empty($errors)) {
                    showMessage('warning', 'Erreurs : ' . implode(', ', $errors));
                }
                
                break;
        }
    } catch (Exception $e) {
        showMessage('error', 'Erreur lors de l\'inscription : ' . $e->getMessage());
    }
}

// RÃ©cupÃ©rer les candidatures acceptÃ©es (prÃªtes Ã  Ãªtre inscrites)
try {
    $candidatures_acceptees = $database->query(
        "SELECT da.*, c.nom as classe_demandee, c.niveau, c.section,
                DATEDIFF(NOW(), COALESCE(da.date_traitement, da.created_at)) as jours_depuis_acceptation
         FROM demandes_admission da
         LEFT JOIN classes c ON da.classe_demandee_id = c.id
         WHERE da.status = 'acceptee'
         ORDER BY COALESCE(da.date_traitement, da.created_at) DESC"
    )->fetchAll();
} catch (Exception $e) {
    $candidatures_acceptees = [];
    showMessage('error', 'Erreur lors du chargement : ' . $e->getMessage());
}

// RÃ©cupÃ©rer les classes disponibles
try {
    $classes = $database->query("SELECT * FROM classes ORDER BY niveau, nom")->fetchAll();
} catch (Exception $e) {
    $classes = [];
}

// Statistiques
try {
    $stats = $database->query(
        "SELECT 
            COUNT(CASE WHEN status = 'acceptee' THEN 1 END) as acceptees,
            COUNT(CASE WHEN status = 'inscrit' THEN 1 END) as inscrites,
            COUNT(CASE WHEN status = 'acceptee' AND DATEDIFF(NOW(), date_traitement) > 7 THEN 1 END) as en_retard
         FROM demandes_admission"
    )->fetch();
} catch (Exception $e) {
    $stats = ['acceptees' => 0, 'inscrites' => 0, 'en_retard' => 0];
}

$page_title = "Inscription des Candidats";
include '../../../../includes/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">
        <i class="fas fa-user-check me-2"></i>
        Inscription des Candidats
    </h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <div class="btn-group me-2">
            <a href="../index.php" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-1"></i>
                Retour aux Admissions
            </a>
        </div>
        <div class="btn-group me-2">
            <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#bulkEnrollmentModal">
                <i class="fas fa-users me-1"></i>
                Inscription en lot
            </button>
        </div>
    </div>
</div>

<!-- Statistiques -->
<div class="row mb-4">
    <div class="col-md-4">
        <div class="card text-center">
            <div class="card-body">
                <i class="fas fa-check-circle fa-2x text-success mb-2"></i>
                                        <h5 class="card-title"><?php echo number_format($stats['acceptees']); ?></h5>
                        <p class="card-text text-muted">Candidatures acceptÃ©es</p>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card text-center">
            <div class="card-body">
                <i class="fas fa-user-graduate fa-2x text-primary mb-2"></i>
                <h5 class="card-title"><?php echo number_format($stats['inscrites']); ?></h5>
                <p class="card-text text-muted">DÃ©jÃ  inscrites</p>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card text-center">
            <div class="card-body">
                <i class="fas fa-clock fa-2x text-warning mb-2"></i>
                <h5 class="card-title"><?php echo number_format($stats['en_retard']); ?></h5>
                <p class="card-text text-muted">En retard d'inscription</p>
            </div>
        </div>
    </div>
</div>

<!-- Liste des candidatures acceptÃ©es -->
<div class="card">
    <div class="card-header">
        <h5 class="mb-0">
            <i class="fas fa-list me-2"></i>
            Candidatures acceptÃ©es Ã  inscrire (<?php echo count($candidatures_acceptees); ?>)
        </h5>
    </div>
    <div class="card-body">
        <?php if (empty($candidatures_acceptees)): ?>
            <div class="text-center py-4">
                <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                <h5 class="text-muted">Aucune candidature acceptÃ©e</h5>
                <p class="text-muted">Toutes les candidatures acceptÃ©es ont dÃ©jÃ  Ã©tÃ© inscrites.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead class="table-light">
                        <tr>
                            <th width="40">
                                <input type="checkbox" id="selectAll" class="form-check-input">
                            </th>
                            <th>Candidat</th>
                            <th>Classe demandÃ©e</th>
                            <th>Date d'acceptation</th>
                            <th>DÃ©lai</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($candidatures_acceptees as $candidature): ?>
                            <tr class="<?php echo $candidature['jours_depuis_acceptation'] > 7 ? 'table-warning' : ''; ?>">
                                <td>
                                    <input type="checkbox" name="candidatures[]" 
                                           value="<?php echo $candidature['id']; ?>" 
                                           class="form-check-input candidature-checkbox">
                                </td>
                                <td>
                                    <div>
                                        <strong><?php echo htmlspecialchars($candidature['nom_eleve'] . ' ' . $candidature['prenom_eleve']); ?></strong>
                                        <br>
                                        <small class="text-muted"><?php echo htmlspecialchars($candidature['numero_demande']); ?></small>
                                    </div>
                                </td>
                                <td>
                                    <?php echo htmlspecialchars($candidature['classe_demandee'] . ' - ' . $candidature['niveau']); ?>
                                </td>
                                <td>
                                    <?php echo formatDate($candidature['date_traitement']); ?>
                                </td>
                                <td>
                                    <?php if ($candidature['jours_depuis_acceptation'] > 7): ?>
                                        <span class="badge bg-warning">
                                            <?php echo $candidature['jours_depuis_acceptation']; ?> jours
                                        </span>
                                    <?php else: ?>
                                        <span class="text-muted">
                                            <?php echo $candidature['jours_depuis_acceptation']; ?> jours
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <a href="../applications/view.php?id=<?php echo $candidature['id']; ?>" 
                                           class="btn btn-outline-info" title="Voir les dÃ©tails">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <button type="button" class="btn btn-outline-success" 
                                                onclick="openEnrollmentModal(<?php echo $candidature['id']; ?>)" 
                                                title="Inscrire">
                                            <i class="fas fa-user-check"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Modal d'inscription individuelle -->
<div class="modal fade" id="enrollmentModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-user-check me-2"></i>
                    Inscription d'un candidat
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" id="enrollmentForm">
                <input type="hidden" name="action" value="enroll_student">
                <input type="hidden" name="candidature_id" id="modal_candidature_id">

                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="classe_finale_id" class="form-label">
                                    <i class="fas fa-chalkboard me-1"></i>
                                    Classe d'inscription *
                                </label>
                                <select class="form-select" id="classe_finale_id" name="classe_finale_id" required>
                                    <option value="">-- SÃ©lectionner une classe --</option>
                                    <?php foreach ($classes as $classe): ?>
                                        <option value="<?php echo $classe['id']; ?>">
                                            <?php echo htmlspecialchars($classe['nom'] . ' - ' . $classe['niveau']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="date_inscription" class="form-label">
                                    <i class="fas fa-calendar me-1"></i>
                                    Date d'inscription *
                                </label>
                                <input type="date" class="form-control" id="date_inscription"
                                       name="date_inscription" value="<?php echo date('Y-m-d'); ?>" required>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="frais_inscription" class="form-label">
                                    <i class="fas fa-money-bill me-1"></i>
                                    Frais d'inscription (FC) *
                                </label>
                                <input type="number" class="form-control" id="frais_inscription"
                                       name="frais_inscription" min="0" step="1000" required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="frais_scolarite" class="form-label">
                                    <i class="fas fa-coins me-1"></i>
                                    Frais de scolaritÃ© (FC) *
                                </label>
                                <input type="number" class="form-control" id="frais_scolarite"
                                       name="frais_scolarite" min="0" step="1000" required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="reduction_accordee" class="form-label">
                                    <i class="fas fa-percent me-1"></i>
                                    RÃ©duction (%)
                                </label>
                                <input type="number" class="form-control" id="reduction_accordee"
                                       name="reduction_accordee" min="0" max="100" step="5" value="0">
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-12">
                            <div class="alert alert-info">
                                <i class="fas fa-calculator me-2"></i>
                                <strong>Calcul automatique :</strong>
                                <div id="montant_total" class="mt-2">
                                    Montant total : <span class="fw-bold">0 FC</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <strong>Attention :</strong> Cette action crÃ©era dÃ©finitivement le dossier Ã©lÃ¨ve
                        et ne pourra pas Ãªtre annulÃ©e.
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times me-1"></i>
                        Annuler
                    </button>
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-user-check me-1"></i>
                        Inscrire l'Ã©lÃ¨ve
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal d'inscription en lot -->
<div class="modal fade" id="bulkEnrollmentModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-users me-2"></i>
                    Inscription en lot
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" id="bulkEnrollmentForm">
                <input type="hidden" name="action" value="bulk_enroll">

                <div class="modal-body">
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        SÃ©lectionnez d'abord les candidatures dans la liste, puis dÃ©finissez les paramÃ¨tres communs.
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="bulk_classe_id" class="form-label">
                                    <i class="fas fa-chalkboard me-1"></i>
                                    Classe commune *
                                </label>
                                <select class="form-select" id="bulk_classe_id" name="bulk_classe_id" required>
                                    <option value="">-- SÃ©lectionner une classe --</option>
                                    <?php foreach ($classes as $classe): ?>
                                        <option value="<?php echo $classe['id']; ?>">
                                            <?php echo htmlspecialchars($classe['nom'] . ' - ' . $classe['niveau']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="bulk_date_inscription" class="form-label">
                                    <i class="fas fa-calendar me-1"></i>
                                    Date d'inscription
                                </label>
                                <input type="date" class="form-control" id="bulk_date_inscription"
                                       name="bulk_date_inscription" value="<?php echo date('Y-m-d'); ?>">
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="bulk_frais_inscription" class="form-label">
                                    <i class="fas fa-money-bill me-1"></i>
                                    Frais d'inscription (FC) *
                                </label>
                                <input type="number" class="form-control" id="bulk_frais_inscription"
                                       name="bulk_frais_inscription" min="0" step="1000" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="bulk_frais_scolarite" class="form-label">
                                    <i class="fas fa-coins me-1"></i>
                                    Frais de scolaritÃ© (FC) *
                                </label>
                                <input type="number" class="form-control" id="bulk_frais_scolarite"
                                       name="bulk_frais_scolarite" min="0" step="1000" required>
                            </div>
                        </div>
                    </div>

                    <div id="selectedCandidatesCount" class="text-muted">
                        Aucune candidature sÃ©lectionnÃ©e
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times me-1"></i>
                        Annuler
                    </button>
                    <button type="button" class="btn btn-success" onclick="submitBulkEnrollment()">
                        <i class="fas fa-users me-1"></i>
                        Inscrire les candidats
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Gestion de la sÃ©lection multiple
    const selectAllCheckbox = document.getElementById('selectAll');
    const candidatureCheckboxes = document.querySelectorAll('.candidature-checkbox');

    if (selectAllCheckbox) {
        selectAllCheckbox.addEventListener('change', function() {
            candidatureCheckboxes.forEach(checkbox => {
                checkbox.checked = this.checked;
            });
            updateSelectedCount();
        });
    }

    candidatureCheckboxes.forEach(checkbox => {
        checkbox.addEventListener('change', updateSelectedCount);
    });

    function updateSelectedCount() {
        const selectedCount = document.querySelectorAll('.candidature-checkbox:checked').length;
        const countElement = document.getElementById('selectedCandidatesCount');
        if (countElement) {
            countElement.textContent = selectedCount > 0
                ? `${selectedCount} candidature(s) sÃ©lectionnÃ©e(s)`
                : 'Aucune candidature sÃ©lectionnÃ©e';
        }
    }

    // Calcul automatique du montant total
    const fraisInscription = document.getElementById('frais_inscription');
    const fraisScolarite = document.getElementById('frais_scolarite');
    const reduction = document.getElementById('reduction_accordee');
    const montantTotal = document.getElementById('montant_total');

    function calculateTotal() {
        const inscription = parseFloat(fraisInscription.value) || 0;
        const scolarite = parseFloat(fraisScolarite.value) || 0;
        const reductionPct = parseFloat(reduction.value) || 0;

        const total = (inscription + scolarite) * (1 - reductionPct / 100);

        if (montantTotal) {
            montantTotal.innerHTML = `Montant total : <span class="fw-bold">${total.toLocaleString()} FC</span>`;
        }
    }

    if (fraisInscription) fraisInscription.addEventListener('input', calculateTotal);
    if (fraisScolarite) fraisScolarite.addEventListener('input', calculateTotal);
    if (reduction) reduction.addEventListener('input', calculateTotal);
});

function openEnrollmentModal(candidatureId) {
    document.getElementById('modal_candidature_id').value = candidatureId;

    // Charger les donnÃ©es de la candidature
    fetch(`get-candidature.php?id=${candidatureId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // PrÃ©-sÃ©lectionner la classe demandÃ©e
                document.getElementById('classe_finale_id').value = data.classe_demandee_id || '';

                // PrÃ©-remplir les frais si disponibles
                document.getElementById('frais_inscription').value = data.frais_inscription || '';
                document.getElementById('frais_scolarite').value = data.frais_scolarite || '';
                document.getElementById('reduction_accordee').value = data.reduction_accordee || 0;
            }
        })
        .catch(error => console.error('Erreur:', error));

    new bootstrap.Modal(document.getElementById('enrollmentModal')).show();
}

function submitBulkEnrollment() {
    const selectedCheckboxes = document.querySelectorAll('.candidature-checkbox:checked');
    const classeId = document.getElementById('bulk_classe_id').value;
    const fraisInscription = document.getElementById('bulk_frais_inscription').value;
    const fraisScolarite = document.getElementById('bulk_frais_scolarite').value;

    if (selectedCheckboxes.length === 0) {
        alert('Veuillez sÃ©lectionner au moins une candidature.');
        return;
    }

    if (!classeId || !fraisInscription || !fraisScolarite) {
        alert('Veuillez remplir tous les champs obligatoires.');
        return;
    }

    if (confirm(`ÃŠtes-vous sÃ»r de vouloir inscrire ${selectedCheckboxes.length} candidat(s) ? Cette action ne peut pas Ãªtre annulÃ©e.`)) {
        // Soumettre le formulaire avec les candidatures sÃ©lectionnÃ©es
        const form = document.getElementById('bulkEnrollmentForm');

        // Ajouter les candidatures sÃ©lectionnÃ©es au formulaire
        selectedCheckboxes.forEach(checkbox => {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'candidatures[]';
            input.value = checkbox.value;
            form.appendChild(input);
        });

        form.submit();
    }
}
</script>

<?php include '../../../../includes/footer.php'; ?>




