<?php
/**
 * Traitement avancé d'une candidature d'admission
 * Application de gestion scolaire - République Démocratique du Congo
 */

require_once '../../../../config/config.php';
require_once '../../../../config/database.php';
require_once '../../../../includes/functions.php';

// Vérifier l'authentification et les permissions
requireLogin();
if (!checkPermission('students')) {
    showMessage('error', 'Accès refusé à ce module.');
    redirectTo('index.php');
}

$candidature_id = intval($_GET['id'] ?? 0);

if (!$candidature_id) {
    showMessage('error', 'ID de candidature invalide.');
    redirectTo('index.php');
}

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $action = $_POST['action'] ?? '';
        
        switch ($action) {
            case 'update_status':
                $new_status = $_POST['status'] ?? '';
                $commentaire = trim($_POST['commentaire'] ?? '');
                $date_entretien = $_POST['date_entretien'] ?? null;
                $heure_entretien = $_POST['heure_entretien'] ?? null;
                $frais_scolarite = floatval($_POST['frais_scolarite'] ?? 0);
                $reduction_accordee = floatval($_POST['reduction_accordee'] ?? 0);
                
                // Vérifier que le statut est valide
                $valid_statuses = ['en_attente', 'acceptee', 'refusee', 'en_cours_traitement', 'inscrit'];
                if (!in_array($new_status, $valid_statuses)) {
                    throw new Exception('Statut invalide.');
                }
                
                // Préparer la date/heure d'entretien
                $datetime_entretien = null;
                if ($date_entretien && $heure_entretien) {
                    $datetime_entretien = $date_entretien . ' ' . $heure_entretien;
                }
                
                // Mettre à jour la candidature
                $database->execute(
                    "UPDATE demandes_admission 
                     SET status = ?, traite_par = ?, date_traitement = NOW(), updated_at = NOW(),
                         commentaire_traitement = ?, date_entretien = ?, 
                         frais_scolarite = ?, reduction_accordee = ?
                     WHERE id = ?",
                    [$new_status, $_SESSION['user_id'], $commentaire, $datetime_entretien, 
                     $frais_scolarite, $reduction_accordee, $candidature_id]
                );
                
                // Messages de confirmation selon le statut
                $status_messages = [
                    'acceptee' => 'Candidature acceptée avec succès.',
                    'refusee' => 'Candidature refusée.',
                    'en_cours_traitement' => 'Candidature mise en cours de traitement.',
                    'inscrit' => 'Candidat marqué comme inscrit.',
                    'en_attente' => 'Candidature remise en attente.'
                ];
                
                $message = $status_messages[$new_status] ?? 'Statut mis à jour avec succès.';
                showMessage('success', $message);
                
                // Si acceptée, proposer de créer l'élève
                if ($new_status === 'acceptee') {
                    $_SESSION['create_student_from_application'] = $candidature_id;
                    showMessage('info', 'Vous pouvez maintenant créer le dossier élève à partir de cette candidature.');
                }
                
                break;
                
            case 'create_student':
                // Récupérer les données de la candidature
                $candidature = $database->query(
                    "SELECT * FROM demandes_admission WHERE id = ? AND status = 'acceptee'",
                    [$candidature_id]
                )->fetch();
                
                if (!$candidature) {
                    throw new Exception('Candidature non trouvée ou non acceptée.');
                }
                
                // Vérifier si l'élève n'existe pas déjà
                $existing_student = $database->query(
                    "SELECT id FROM eleves WHERE nom = ? AND prenom = ? AND date_naissance = ?",
                    [$candidature['nom_eleve'], $candidature['prenom_eleve'], $candidature['date_naissance']]
                )->fetch();
                
                if ($existing_student) {
                    throw new Exception('Un élève avec ces informations existe déjà.');
                }
                
                // Générer un numéro d'élève unique
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
                
                // Créer l'élève
                $database->execute(
                    "INSERT INTO eleves (
                        numero_eleve, nom, prenom, date_naissance, lieu_naissance, sexe,
                        adresse, telephone, email, nom_pere, nom_mere, profession_pere, profession_mere,
                        telephone_parent, personne_contact, telephone_contact, relation_contact,
                        classe_id, annee_scolaire_id, status, created_at
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'actif', NOW())",
                    [
                        $numero_eleve, $candidature['nom_eleve'], $candidature['prenom_eleve'],
                        $candidature['date_naissance'], $candidature['lieu_naissance'], $candidature['sexe'],
                        $candidature['adresse'], $candidature['telephone'], $candidature['email'],
                        $candidature['nom_pere'], $candidature['nom_mere'], 
                        $candidature['profession_pere'], $candidature['profession_mere'],
                        $candidature['telephone_parent'], $candidature['personne_contact'],
                        $candidature['telephone_contact'], $candidature['relation_contact'],
                        $candidature['classe_demandee_id'], $candidature['annee_scolaire_id']
                    ]
                );
                
                $student_id = $database->lastInsertId();
                
                // Mettre à jour le statut de la candidature
                $database->execute(
                    "UPDATE demandes_admission SET status = 'inscrit', eleve_cree_id = ? WHERE id = ?",
                    [$student_id, $candidature_id]
                );
                
                showMessage('success', "Élève créé avec succès. Numéro d'élève : $numero_eleve");
                unset($_SESSION['create_student_from_application']);
                
                break;
                
            default:
                throw new Exception('Action non reconnue.');
        }
        
    } catch (Exception $e) {
        showMessage('error', 'Erreur lors du traitement : ' . $e->getMessage());
    }
}

// Récupérer les détails de la candidature
try {
    $candidature = $database->query(
        "SELECT da.*, c.nom as classe_demandee, c.niveau, c.section,
                u.username as traite_par_nom, u.nom as traite_par_nom_complet,
                ans.annee as annee_scolaire_nom,
                DATEDIFF(NOW(), da.created_at) as jours_depuis_demande,
                e.numero_eleve, e.id as eleve_id
         FROM demandes_admission da
         LEFT JOIN classes c ON da.classe_demandee_id = c.id
         LEFT JOIN users u ON da.traite_par = u.id
         LEFT JOIN annees_scolaires ans ON da.annee_scolaire_id = ans.id
         LEFT JOIN eleves e ON da.eleve_cree_id = e.id
         WHERE da.id = ?",
        [$candidature_id]
    )->fetch();

    if (!$candidature) {
        showMessage('error', 'Candidature non trouvée.');
        redirectTo('index.php');
    }
} catch (Exception $e) {
    showMessage('error', 'Erreur lors du chargement : ' . $e->getMessage());
    redirectTo('index.php');
}

// Récupérer les classes disponibles
try {
    $classes = $database->query("SELECT * FROM classes ORDER BY niveau, nom")->fetchAll();
} catch (Exception $e) {
    $classes = [];
}

$page_title = "Traitement de la Candidature - " . $candidature['nom_eleve'] . " " . $candidature['prenom_eleve'];
include '../../../../includes/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">
        <i class="fas fa-cogs me-2"></i>
        Traitement de la Candidature
    </h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <div class="btn-group me-2">
            <a href="view.php?id=<?php echo $candidature_id; ?>" class="btn btn-outline-secondary">
                <i class="fas fa-eye me-1"></i>
                Voir les détails
            </a>
        </div>
        <div class="btn-group me-2">
            <a href="index.php" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-1"></i>
                Retour à la liste
            </a>
        </div>
    </div>
</div>

<!-- Informations de base de la candidature -->
<div class="row mb-4">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="fas fa-user me-2"></i>
                    Informations du Candidat
                </h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <p><strong>Nom complet :</strong> <?php echo htmlspecialchars($candidature['nom_eleve'] . ' ' . $candidature['prenom_eleve']); ?></p>
                        <p><strong>Date de naissance :</strong> <?php echo formatDate($candidature['date_naissance']); ?></p>
                        <p><strong>Sexe :</strong> <?php echo $candidature['sexe'] === 'M' ? 'Masculin' : 'Féminin'; ?></p>
                        <p><strong>Classe demandée :</strong> <?php echo htmlspecialchars($candidature['classe_demandee'] . ' - ' . $candidature['niveau']); ?></p>
                    </div>
                    <div class="col-md-6">
                        <p><strong>Numéro de demande :</strong> <?php echo htmlspecialchars($candidature['numero_demande']); ?></p>
                        <p><strong>Date de demande :</strong> <?php echo formatDateTime($candidature['created_at']); ?></p>
                        <p><strong>Jours depuis demande :</strong> <?php echo $candidature['jours_depuis_demande']; ?> jours</p>
                        <p><strong>Téléphone parent :</strong> <?php echo htmlspecialchars($candidature['telephone_parent']); ?></p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-4">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="fas fa-info-circle me-2"></i>
                    Statut Actuel
                </h5>
            </div>
            <div class="card-body text-center">
                <?php
                $status_classes = [
                    'en_attente' => 'warning',
                    'acceptee' => 'success',
                    'refusee' => 'danger',
                    'en_cours_traitement' => 'info',
                    'inscrit' => 'primary'
                ];

                $status_names = [
                    'en_attente' => 'En attente',
                    'acceptee' => 'Acceptée',
                    'refusee' => 'Refusée',
                    'en_cours_traitement' => 'En cours de traitement',
                    'inscrit' => 'Inscrit'
                ];

                $status_class = $status_classes[$candidature['status']] ?? 'secondary';
                $status_name = $status_names[$candidature['status']] ?? $candidature['status'];
                ?>
                <span class="badge bg-<?php echo $status_class; ?> fs-6 p-3">
                    <?php echo $status_name; ?>
                </span>

                <?php if ($candidature['date_traitement']): ?>
                    <p class="mt-3 mb-0 small text-muted">
                        Traité le <?php echo formatDateTime($candidature['date_traitement']); ?>
                        <?php if ($candidature['traite_par_nom']): ?>
                            <br>par <?php echo htmlspecialchars($candidature['traite_par_nom']); ?>
                        <?php endif; ?>
                    </p>
                <?php endif; ?>

                <?php if ($candidature['eleve_id']): ?>
                    <div class="mt-3">
                        <a href="../../records/view.php?id=<?php echo $candidature['eleve_id']; ?>" class="btn btn-sm btn-primary">
                            <i class="fas fa-user-graduate me-1"></i>
                            Voir le dossier élève
                        </a>
                        <p class="small text-muted mt-1">N° élève: <?php echo htmlspecialchars($candidature['numero_eleve']); ?></p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Formulaire de traitement -->
<div class="card">
    <div class="card-header">
        <h5 class="card-title mb-0">
            <i class="fas fa-edit me-2"></i>
            Actions de Traitement
        </h5>
    </div>
    <div class="card-body">
        <form method="POST" id="processForm">
            <input type="hidden" name="action" value="update_status">

            <div class="row">
                <div class="col-md-6">
                    <div class="mb-3">
                        <label for="status" class="form-label">
                            <i class="fas fa-flag me-1"></i>
                            Nouveau Statut *
                        </label>
                        <select class="form-select" id="status" name="status" required>
                            <option value="">-- Sélectionner un statut --</option>
                            <option value="en_attente" <?php echo $candidature['status'] === 'en_attente' ? 'selected' : ''; ?>>En attente</option>
                            <option value="en_cours_traitement" <?php echo $candidature['status'] === 'en_cours_traitement' ? 'selected' : ''; ?>>En cours de traitement</option>
                            <option value="acceptee" <?php echo $candidature['status'] === 'acceptee' ? 'selected' : ''; ?>>Acceptée</option>
                            <option value="refusee" <?php echo $candidature['status'] === 'refusee' ? 'selected' : ''; ?>>Refusée</option>
                            <option value="inscrit" <?php echo $candidature['status'] === 'inscrit' ? 'selected' : ''; ?>>Inscrit</option>
                        </select>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="mb-3">
                        <label for="priorite" class="form-label">
                            <i class="fas fa-exclamation-triangle me-1"></i>
                            Priorité
                        </label>
                        <select class="form-select" id="priorite" name="priorite">
                            <option value="normale" <?php echo $candidature['priorite'] === 'normale' ? 'selected' : ''; ?>>Normale</option>
                            <option value="urgente" <?php echo $candidature['priorite'] === 'urgente' ? 'selected' : ''; ?>>Urgente</option>
                            <option value="tres_urgente" <?php echo $candidature['priorite'] === 'tres_urgente' ? 'selected' : ''; ?>>Très urgente</option>
                        </select>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-md-6">
                    <div class="mb-3">
                        <label for="date_entretien" class="form-label">
                            <i class="fas fa-calendar me-1"></i>
                            Date d'entretien
                        </label>
                        <input type="date" class="form-control" id="date_entretien" name="date_entretien"
                               value="<?php echo $candidature['date_entretien'] ? date('Y-m-d', strtotime($candidature['date_entretien'])) : ''; ?>">
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="mb-3">
                        <label for="heure_entretien" class="form-label">
                            <i class="fas fa-clock me-1"></i>
                            Heure d'entretien
                        </label>
                        <input type="time" class="form-control" id="heure_entretien" name="heure_entretien"
                               value="<?php echo $candidature['date_entretien'] ? date('H:i', strtotime($candidature['date_entretien'])) : ''; ?>">
                    </div>
                </div>
            </div>

            <div class="row" id="financial-section" style="display: none;">
                <div class="col-md-6">
                    <div class="mb-3">
                        <label for="frais_scolarite" class="form-label">
                            <i class="fas fa-money-bill me-1"></i>
                            Frais de scolarité (FC)
                        </label>
                        <input type="number" class="form-control" id="frais_scolarite" name="frais_scolarite"
                               value="<?php echo $candidature['frais_scolarite'] ?? ''; ?>" min="0" step="1000">
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="mb-3">
                        <label for="reduction_accordee" class="form-label">
                            <i class="fas fa-percent me-1"></i>
                            Réduction accordée (%)
                        </label>
                        <input type="number" class="form-control" id="reduction_accordee" name="reduction_accordee"
                               value="<?php echo $candidature['reduction_accordee'] ?? ''; ?>" min="0" max="100" step="5">
                    </div>
                </div>
            </div>

            <div class="mb-3">
                <label for="commentaire" class="form-label">
                    <i class="fas fa-comment me-1"></i>
                    Commentaire de traitement
                </label>
                <textarea class="form-control" id="commentaire" name="commentaire" rows="4"
                          placeholder="Ajoutez vos observations, remarques ou justifications..."><?php echo htmlspecialchars($candidature['commentaire_traitement'] ?? ''); ?></textarea>
            </div>

            <div class="d-flex justify-content-between">
                <div>
                    <a href="view.php?id=<?php echo $candidature_id; ?>" class="btn btn-secondary">
                        <i class="fas fa-times me-1"></i>
                        Annuler
                    </a>
                </div>
                <div>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-1"></i>
                        Enregistrer le traitement
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Section création d'élève (si candidature acceptée) -->
<?php if ($candidature['status'] === 'acceptee' && !$candidature['eleve_id']): ?>
<div class="card mt-4">
    <div class="card-header bg-success text-white">
        <h5 class="card-title mb-0">
            <i class="fas fa-user-plus me-2"></i>
            Créer le Dossier Élève
        </h5>
    </div>
    <div class="card-body">
        <div class="alert alert-info">
            <i class="fas fa-info-circle me-2"></i>
            Cette candidature a été acceptée. Vous pouvez maintenant créer automatiquement le dossier élève
            à partir des informations de la candidature.
        </div>

        <form method="POST" id="createStudentForm">
            <input type="hidden" name="action" value="create_student">

            <div class="row">
                <div class="col-md-8">
                    <h6>Informations qui seront transférées :</h6>
                    <ul class="list-unstyled">
                        <li><i class="fas fa-check text-success me-2"></i>Nom et prénom</li>
                        <li><i class="fas fa-check text-success me-2"></i>Date et lieu de naissance</li>
                        <li><i class="fas fa-check text-success me-2"></i>Informations des parents</li>
                        <li><i class="fas fa-check text-success me-2"></i>Contacts et adresse</li>
                        <li><i class="fas fa-check text-success me-2"></i>Classe demandée</li>
                    </ul>
                </div>
                <div class="col-md-4 text-end">
                    <button type="submit" class="btn btn-success btn-lg" onclick="return confirm('Êtes-vous sûr de vouloir créer le dossier élève ? Cette action ne peut pas être annulée.')">
                        <i class="fas fa-user-graduate me-2"></i>
                        Créer l'Élève
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<!-- Historique des actions -->
<div class="card mt-4">
    <div class="card-header">
        <h5 class="card-title mb-0">
            <i class="fas fa-history me-2"></i>
            Historique des Actions
        </h5>
    </div>
    <div class="card-body">
        <div class="timeline">
            <div class="timeline-item">
                <div class="timeline-marker bg-primary"></div>
                <div class="timeline-content">
                    <h6 class="timeline-title">Candidature créée</h6>
                    <p class="timeline-text">
                        Le <?php echo formatDateTime($candidature['created_at']); ?>
                    </p>
                </div>
            </div>

            <?php if ($candidature['date_traitement']): ?>
            <div class="timeline-item">
                <div class="timeline-marker bg-<?php echo $status_class; ?>"></div>
                <div class="timeline-content">
                    <h6 class="timeline-title">Statut mis à jour : <?php echo $status_name; ?></h6>
                    <p class="timeline-text">
                        Le <?php echo formatDateTime($candidature['date_traitement']); ?>
                        <?php if ($candidature['traite_par_nom']): ?>
                            par <?php echo htmlspecialchars($candidature['traite_par_nom']); ?>
                        <?php endif; ?>
                    </p>
                    <?php if ($candidature['commentaire_traitement']): ?>
                        <p class="timeline-comment">
                            <i class="fas fa-quote-left me-1"></i>
                            <?php echo nl2br(htmlspecialchars($candidature['commentaire_traitement'])); ?>
                        </p>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>

            <?php if ($candidature['date_entretien']): ?>
            <div class="timeline-item">
                <div class="timeline-marker bg-info"></div>
                <div class="timeline-content">
                    <h6 class="timeline-title">Entretien programmé</h6>
                    <p class="timeline-text">
                        Le <?php echo formatDateTime($candidature['date_entretien']); ?>
                    </p>
                </div>
            </div>
            <?php endif; ?>

            <?php if ($candidature['eleve_id']): ?>
            <div class="timeline-item">
                <div class="timeline-marker bg-success"></div>
                <div class="timeline-content">
                    <h6 class="timeline-title">Élève créé</h6>
                    <p class="timeline-text">
                        Numéro d'élève : <?php echo htmlspecialchars($candidature['numero_eleve']); ?>
                    </p>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
.timeline {
    position: relative;
    padding-left: 30px;
}

.timeline::before {
    content: '';
    position: absolute;
    left: 15px;
    top: 0;
    bottom: 0;
    width: 2px;
    background: #dee2e6;
}

.timeline-item {
    position: relative;
    margin-bottom: 30px;
}

.timeline-marker {
    position: absolute;
    left: -22px;
    top: 5px;
    width: 12px;
    height: 12px;
    border-radius: 50%;
    border: 2px solid #fff;
    box-shadow: 0 0 0 2px #dee2e6;
}

.timeline-content {
    background: #f8f9fa;
    padding: 15px;
    border-radius: 8px;
    border-left: 3px solid #dee2e6;
}

.timeline-title {
    margin-bottom: 5px;
    font-weight: 600;
    color: #495057;
}

.timeline-text {
    margin-bottom: 0;
    color: #6c757d;
    font-size: 0.9em;
}

.timeline-comment {
    margin-top: 10px;
    padding: 10px;
    background: #fff;
    border-radius: 5px;
    font-style: italic;
    color: #495057;
}

.card-header.bg-success {
    border-bottom: 1px solid rgba(255,255,255,0.2);
}

.badge.fs-6 {
    font-size: 1rem !important;
}

#financial-section {
    border-top: 1px solid #dee2e6;
    padding-top: 20px;
    margin-top: 20px;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const statusSelect = document.getElementById('status');
    const financialSection = document.getElementById('financial-section');

    // Afficher/masquer la section financière selon le statut
    function toggleFinancialSection() {
        if (statusSelect.value === 'acceptee') {
            financialSection.style.display = 'block';
        } else {
            financialSection.style.display = 'none';
        }
    }

    // Vérifier au chargement de la page
    toggleFinancialSection();

    // Écouter les changements de statut
    statusSelect.addEventListener('change', toggleFinancialSection);

    // Validation du formulaire
    document.getElementById('processForm').addEventListener('submit', function(e) {
        const status = statusSelect.value;
        const commentaire = document.getElementById('commentaire').value.trim();

        if (!status) {
            e.preventDefault();
            alert('Veuillez sélectionner un statut.');
            statusSelect.focus();
            return;
        }

        // Demander confirmation pour les actions importantes
        if (status === 'refusee') {
            if (!commentaire) {
                e.preventDefault();
                alert('Un commentaire est requis pour refuser une candidature.');
                document.getElementById('commentaire').focus();
                return;
            }

            if (!confirm('Êtes-vous sûr de vouloir refuser cette candidature ? Cette action peut être modifiée ultérieurement.')) {
                e.preventDefault();
                return;
            }
        }

        if (status === 'acceptee') {
            if (!confirm('Êtes-vous sûr de vouloir accepter cette candidature ? Vous pourrez ensuite créer le dossier élève.')) {
                e.preventDefault();
                return;
            }
        }
    });

    // Auto-resize du textarea
    const textarea = document.getElementById('commentaire');
    textarea.addEventListener('input', function() {
        this.style.height = 'auto';
        this.style.height = (this.scrollHeight) + 'px';
    });
});

// Fonction pour imprimer la page
function printPage() {
    window.print();
}

// Fonction pour exporter en PDF (nécessite une implémentation côté serveur)
function exportToPDF() {
    window.location.href = 'export-pdf.php?id=<?php echo $candidature_id; ?>';
}
</script>

<?php include '../../../../includes/footer.php'; ?>
