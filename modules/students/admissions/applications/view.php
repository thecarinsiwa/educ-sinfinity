<?php
/**
 * Voir les détails d'une candidature
 * Application de gestion scolaire - République Démocratique du Congo
 */

require_once '../../../../config/config.php';
require_once '../../../../config/database.php';
require_once '../../../../includes/functions.php';

// Vérifier l'authentification et les permissions
requireLogin();
if (!checkPermission('students') && !checkPermission('students_view')) {
    showMessage('error', 'Accès refusé à ce module.');
    redirectTo('../../index.php');
}

$candidature_id = intval($_GET['id'] ?? 0);

if (!$candidature_id) {
    showMessage('error', 'ID de candidature invalide.');
    redirectTo('index.php');
}

// Récupérer les détails de la candidature
try {
    $candidature = $database->query(
        "SELECT da.*, c.nom as classe_demandee, c.niveau, c.section,
                u.username as traite_par_nom, u.nom as traite_par_nom_complet,
                ans.annee as annee_scolaire_nom,
                DATEDIFF(NOW(), da.created_at) as jours_depuis_demande
         FROM demandes_admission da
         LEFT JOIN classes c ON da.classe_demandee_id = c.id
         LEFT JOIN users u ON da.traite_par = u.id
         LEFT JOIN annees_scolaires ans ON da.annee_scolaire_id = ans.id
         WHERE da.id = ?",
        [$candidature_id]
    )->fetch();

    if (!$candidature) {
        showMessage('error', 'Candidature non trouvée.');
        redirectTo('index.php');
    }
} catch (Exception $e) {
    showMessage('error', 'Erreur lors du chargement de la candidature : ' . $e->getMessage());
    redirectTo('index.php');
}

$page_title = 'Candidature - ' . $candidature['nom_eleve'] . ' ' . $candidature['prenom_eleve'];

include '../../../../includes/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">
        <i class="fas fa-file-alt me-2"></i>
        Détails de la Candidature
    </h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <div class="btn-group me-2">
            <a href="index.php" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-1"></i>
                Retour à la liste
            </a>
        </div>
        <?php if (checkPermission('students')): ?>
            <div class="btn-group me-2">
                <a href="edit.php?id=<?php echo $candidature['id']; ?>" class="btn btn-warning">
                    <i class="fas fa-edit me-1"></i>
                    Modifier
                </a>
            </div>
            <?php if ($candidature['status'] === 'en_attente'): ?>
                <div class="btn-group">
                    <button type="button" class="btn btn-success" onclick="updateStatus('acceptee')">
                        <i class="fas fa-check me-1"></i>
                        Accepter
                    </button>
                    <button type="button" class="btn btn-danger" onclick="updateStatus('refusee')">
                        <i class="fas fa-times me-1"></i>
                        Refuser
                    </button>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<!-- Informations générales -->
<div class="row mb-4">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-user me-2"></i>
                    Informations du Candidat
                </h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <table class="table table-borderless">
                            <tr>
                                <td><strong>Numéro de demande :</strong></td>
                                <td><?php echo htmlspecialchars($candidature['numero_demande']); ?></td>
                            </tr>
                            <tr>
                                <td><strong>Nom complet :</strong></td>
                                <td><?php echo htmlspecialchars($candidature['nom_eleve'] . ' ' . $candidature['prenom_eleve']); ?></td>
                            </tr>
                            <tr>
                                <td><strong>Date de naissance :</strong></td>
                                <td><?php echo formatDate($candidature['date_naissance']); ?></td>
                            </tr>
                            <tr>
                                <td><strong>Lieu de naissance :</strong></td>
                                <td><?php echo htmlspecialchars($candidature['lieu_naissance'] ?? 'Non spécifié'); ?></td>
                            </tr>
                            <tr>
                                <td><strong>Sexe :</strong></td>
                                <td><?php echo $candidature['sexe'] === 'M' ? 'Masculin' : 'Féminin'; ?></td>
                            </tr>
                            <tr>
                                <td><strong>Adresse :</strong></td>
                                <td><?php echo htmlspecialchars($candidature['adresse'] ?? 'Non spécifiée'); ?></td>
                            </tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <table class="table table-borderless">
                            <tr>
                                <td><strong>Téléphone :</strong></td>
                                <td><?php echo htmlspecialchars($candidature['telephone'] ?? 'Non spécifié'); ?></td>
                            </tr>
                            <tr>
                                <td><strong>Email :</strong></td>
                                <td><?php echo htmlspecialchars($candidature['email'] ?? 'Non spécifié'); ?></td>
                            </tr>
                            <tr>
                                <td><strong>École précédente :</strong></td>
                                <td><?php echo htmlspecialchars($candidature['ecole_precedente'] ?? 'Non spécifiée'); ?></td>
                            </tr>
                            <tr>
                                <td><strong>Classe précédente :</strong></td>
                                <td><?php echo htmlspecialchars($candidature['classe_precedente'] ?? 'Non spécifiée'); ?></td>
                            </tr>
                            <tr>
                                <td><strong>Année précédente :</strong></td>
                                <td><?php echo htmlspecialchars($candidature['annee_precedente'] ?? 'Non spécifiée'); ?></td>
                            </tr>
                            <tr>
                                <td><strong>Moyenne précédente :</strong></td>
                                <td><?php echo $candidature['moyenne_precedente'] ? $candidature['moyenne_precedente'] . '/20' : 'Non spécifiée'; ?></td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-4">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-info-circle me-2"></i>
                    Statut de la Candidature
                </h5>
            </div>
            <div class="card-body text-center">
                <?php
                $status_colors = [
                    'en_attente' => 'warning',
                    'acceptee' => 'success',
                    'refusee' => 'danger',
                    'en_cours_traitement' => 'info',
                    'inscrit' => 'primary'
                ];
                $color = $status_colors[$candidature['status']] ?? 'secondary';
                ?>
                <div class="mb-3">
                    <span class="badge bg-<?php echo $color; ?> fs-6 p-3">
                        <?php echo ucfirst(str_replace('_', ' ', $candidature['status'])); ?>
                    </span>
                </div>
                
                <?php
                $priorite_colors = [
                    'normale' => 'secondary',
                    'urgente' => 'warning',
                    'tres_urgente' => 'danger'
                ];
                $priorite_color = $priorite_colors[$candidature['priorite']] ?? 'secondary';
                ?>
                <div class="mb-3">
                    <small class="text-muted">Priorité :</small><br>
                    <span class="badge bg-<?php echo $priorite_color; ?>">
                        <?php echo ucfirst(str_replace('_', ' ', $candidature['priorite'])); ?>
                    </span>
                </div>
                
                <div class="mb-3">
                    <small class="text-muted">Classe demandée :</small><br>
                    <strong><?php echo htmlspecialchars($candidature['classe_demandee'] ?? 'Non spécifiée'); ?></strong>
                    <?php if ($candidature['niveau']): ?>
                        <br><small class="text-muted"><?php echo htmlspecialchars($candidature['niveau']); ?></small>
                    <?php endif; ?>
                </div>
                
                <div class="mb-3">
                    <small class="text-muted">Demande soumise :</small><br>
                    <strong><?php echo formatDate($candidature['created_at']); ?></strong>
                    <br><small class="text-muted">Il y a <?php echo $candidature['jours_depuis_demande']; ?> jour(s)</small>
                </div>
                
                <?php if ($candidature['traite_par']): ?>
                    <div class="mb-3">
                        <small class="text-muted">Traitée par :</small><br>
                        <strong><?php echo htmlspecialchars($candidature['traite_par_nom_complet'] ?? $candidature['traite_par_nom']); ?></strong>
                        <?php if ($candidature['date_traitement']): ?>
                            <br><small class="text-muted"><?php echo formatDate($candidature['date_traitement']); ?></small>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Informations des parents -->
<div class="row mb-4">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-users me-2"></i>
                    Informations des Parents
                </h5>
            </div>
            <div class="card-body">
                <table class="table table-borderless">
                    <tr>
                        <td><strong>Nom du père :</strong></td>
                        <td><?php echo htmlspecialchars($candidature['nom_pere'] ?? 'Non spécifié'); ?></td>
                    </tr>
                    <tr>
                        <td><strong>Profession du père :</strong></td>
                        <td><?php echo htmlspecialchars($candidature['profession_pere'] ?? 'Non spécifiée'); ?></td>
                    </tr>
                    <tr>
                        <td><strong>Nom de la mère :</strong></td>
                        <td><?php echo htmlspecialchars($candidature['nom_mere'] ?? 'Non spécifié'); ?></td>
                    </tr>
                    <tr>
                        <td><strong>Profession de la mère :</strong></td>
                        <td><?php echo htmlspecialchars($candidature['profession_mere'] ?? 'Non spécifiée'); ?></td>
                    </tr>
                    <tr>
                        <td><strong>Téléphone des parents :</strong></td>
                        <td><?php echo htmlspecialchars($candidature['telephone_parent'] ?? 'Non spécifié'); ?></td>
                    </tr>
                </table>
            </div>
        </div>
    </div>
    
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-address-book me-2"></i>
                    Personne de Contact
                </h5>
            </div>
            <div class="card-body">
                <table class="table table-borderless">
                    <tr>
                        <td><strong>Nom du contact :</strong></td>
                        <td><?php echo htmlspecialchars($candidature['personne_contact'] ?? 'Non spécifié'); ?></td>
                    </tr>
                    <tr>
                        <td><strong>Téléphone du contact :</strong></td>
                        <td><?php echo htmlspecialchars($candidature['telephone_contact'] ?? 'Non spécifié'); ?></td>
                    </tr>
                    <tr>
                        <td><strong>Relation :</strong></td>
                        <td><?php echo htmlspecialchars($candidature['relation_contact'] ?? 'Non spécifiée'); ?></td>
                    </tr>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Documents et informations supplémentaires -->
<div class="row mb-4">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-file-alt me-2"></i>
                    Documents Requis
                </h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-6">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" <?php echo $candidature['certificat_naissance'] ? 'checked' : ''; ?> disabled>
                            <label class="form-check-label">Certificat de naissance</label>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" <?php echo $candidature['bulletin_precedent'] ? 'checked' : ''; ?> disabled>
                            <label class="form-check-label">Bulletin précédent</label>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" <?php echo $candidature['certificat_medical'] ? 'checked' : ''; ?> disabled>
                            <label class="form-check-label">Certificat médical</label>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" <?php echo $candidature['photo_identite'] ? 'checked' : ''; ?> disabled>
                            <label class="form-check-label">Photo d'identité</label>
                        </div>
                    </div>
                </div>
                
                <?php if ($candidature['autres_documents']): ?>
                    <div class="mt-3">
                        <strong>Autres documents :</strong>
                        <p class="text-muted"><?php echo nl2br(htmlspecialchars($candidature['autres_documents'])); ?></p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-notes-medical me-2"></i>
                    Informations Médicales
                </h5>
            </div>
            <div class="card-body">
                <?php if ($candidature['besoins_speciaux']): ?>
                    <div class="mb-3">
                        <strong>Besoins spéciaux :</strong>
                        <p class="text-muted"><?php echo nl2br(htmlspecialchars($candidature['besoins_speciaux'])); ?></p>
                    </div>
                <?php endif; ?>
                
                <?php if ($candidature['allergies_medicales']): ?>
                    <div class="mb-3">
                        <strong>Allergies médicales :</strong>
                        <p class="text-muted"><?php echo nl2br(htmlspecialchars($candidature['allergies_medicales'])); ?></p>
                    </div>
                <?php endif; ?>
                
                <?php if (!$candidature['besoins_speciaux'] && !$candidature['allergies_medicales']): ?>
                    <p class="text-muted">Aucune information médicale spéciale renseignée.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Motif et observations -->
<?php if ($candidature['motif_demande'] || $candidature['observations'] || $candidature['decision_motif']): ?>
<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-comment me-2"></i>
                    Motifs et Observations
                </h5>
            </div>
            <div class="card-body">
                <?php if ($candidature['motif_demande']): ?>
                    <div class="mb-3">
                        <strong>Motif de la demande :</strong>
                        <p class="text-muted"><?php echo nl2br(htmlspecialchars($candidature['motif_demande'])); ?></p>
                    </div>
                <?php endif; ?>
                
                <?php if ($candidature['observations']): ?>
                    <div class="mb-3">
                        <strong>Observations :</strong>
                        <p class="text-muted"><?php echo nl2br(htmlspecialchars($candidature['observations'])); ?></p>
                    </div>
                <?php endif; ?>
                
                <?php if ($candidature['decision_motif']): ?>
                    <div class="mb-3">
                        <strong>Motif de la décision :</strong>
                        <p class="text-muted"><?php echo nl2br(htmlspecialchars($candidature['decision_motif'])); ?></p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Informations financières -->
<?php if ($candidature['frais_inscription'] || $candidature['frais_scolarite'] || $candidature['reduction_accordee']): ?>
<div class="row mb-4">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-money-bill me-2"></i>
                    Informations Financières
                </h5>
            </div>
            <div class="card-body">
                <table class="table table-borderless">
                    <?php if ($candidature['frais_inscription']): ?>
                        <tr>
                            <td><strong>Frais d'inscription :</strong></td>
                            <td><?php echo number_format($candidature['frais_inscription'], 0, ',', ' ') . ' FC'; ?></td>
                        </tr>
                    <?php endif; ?>
                    <?php if ($candidature['frais_scolarite']): ?>
                        <tr>
                            <td><strong>Frais de scolarité :</strong></td>
                            <td><?php echo number_format($candidature['frais_scolarite'], 0, ',', ' ') . ' FC'; ?></td>
                        </tr>
                    <?php endif; ?>
                    <?php if ($candidature['reduction_accordee']): ?>
                        <tr>
                            <td><strong>Réduction accordée :</strong></td>
                            <td><?php echo $candidature['reduction_accordee'] . '%'; ?></td>
                        </tr>
                    <?php endif; ?>
                </table>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
function updateStatus(newStatus) {
    const statusNames = {
        'acceptee': 'accepter',
        'refusee': 'refuser'
    };
    
    if (confirm(`Êtes-vous sûr de vouloir ${statusNames[newStatus]} cette candidature ?`)) {
        window.location.href = `update_status.php?id=<?php echo $candidature['id']; ?>&status=${newStatus}`;
    }
}
</script>

<?php include '../../../../includes/footer.php'; ?>
