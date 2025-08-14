<?php
/**
 * Module des étudiants - Voir les détails d'un étudiant
 * Application de gestion scolaire - République Démocratique du Congo
 */

require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../includes/functions.php';

// Vérifier l'authentification et les permissions
requireLogin();
if (!checkPermission('students') && !checkPermission('students_view')) {
    showMessage('error', 'Accès refusé à ce module.');
    redirectTo('../../dashboard.php');
}

// Récupérer l'ID de l'étudiant
$eleve_id = (int)($_GET['id'] ?? 0);
if (!$eleve_id) {
    showMessage('error', 'ID d\'étudiant manquant.');
    redirectTo('index.php');
}

// Récupérer l'année scolaire active
$current_year = getCurrentAcademicYear();

// Récupérer les informations de l'étudiant
$eleve = $database->query(
    "SELECT e.*, 
            i.date_inscription, i.status as statut_inscription,
            c.nom as classe_nom, c.niveau, c.section,
            (SELECT COUNT(*) FROM notes n 
             JOIN evaluations ev ON n.evaluation_id = ev.id 
             WHERE n.eleve_id = e.id AND ev.annee_scolaire_id = ?) as nb_evaluations,
            (SELECT AVG(n.note) FROM notes n 
             JOIN evaluations ev ON n.evaluation_id = ev.id 
             WHERE n.eleve_id = e.id AND ev.annee_scolaire_id = ?) as moyenne_generale
     FROM eleves e
     LEFT JOIN inscriptions i ON e.id = i.eleve_id AND i.annee_scolaire_id = ?
     LEFT JOIN classes c ON i.classe_id = c.id
     WHERE e.id = ?",
    [$current_year['id'] ?? 0, $current_year['id'] ?? 0, $current_year['id'] ?? 0, $eleve_id]
)->fetch();

if (!$eleve) {
    showMessage('error', 'Étudiant non trouvé.');
    redirectTo('index.php');
}

$page_title = 'Étudiant : ' . $eleve['nom'] . ' ' . $eleve['prenom'];

// Récupérer les notes récentes
$notes_recentes = $database->query(
    "SELECT n.note, n.observation, n.created_at,
            e.nom as evaluation_nom, e.type as evaluation_type, e.date_evaluation,
            m.nom as matiere_nom, m.coefficient
     FROM notes n
     JOIN evaluations e ON n.evaluation_id = e.id
     JOIN matieres m ON e.matiere_id = m.id
     WHERE n.eleve_id = ? AND e.annee_scolaire_id = ?
     ORDER BY e.date_evaluation DESC, n.created_at DESC
     LIMIT 10",
    [$eleve_id, $current_year['id'] ?? 0]
)->fetchAll();

// Récupérer les absences récentes
$absences_recentes = $database->query(
    "SELECT a.date_absence, a.type_absence, a.motif,
            a.justification, a.created_at
     FROM absences a
     WHERE a.eleve_id = ?
     ORDER BY a.date_absence DESC
     LIMIT 10",
    [$eleve_id]
)->fetchAll();

// Récupérer les paiements récents
$paiements_recents = $database->query(
    "SELECT p.montant, p.type_paiement, p.date_paiement, p.mode_paiement,
            p.recu_numero, p.observation
     FROM paiements p
     WHERE p.eleve_id = ? AND p.annee_scolaire_id = ?
     ORDER BY p.date_paiement DESC
     LIMIT 10",
    [$eleve_id, $current_year['id'] ?? 0]
)->fetchAll();

// Statistiques
$stats = [
    'age' => $eleve['date_naissance'] ? calculateAge($eleve['date_naissance']) : null,
    'jours_inscription' => $eleve['date_inscription'] ? floor((time() - strtotime($eleve['date_inscription'])) / (24 * 3600)) : null,
    'moyenne_generale' => $eleve['moyenne_generale'] ? round($eleve['moyenne_generale'], 2) : null,
    'nb_evaluations' => $eleve['nb_evaluations'] ?? 0
];

// Calculer le statut académique
$statut_academique = 'Non évalué';
$statut_color = 'secondary';
if ($stats['moyenne_generale']) {
    if ($stats['moyenne_generale'] >= 16) {
        $statut_academique = 'Excellent';
        $statut_color = 'success';
    } elseif ($stats['moyenne_generale'] >= 14) {
        $statut_academique = 'Très bien';
        $statut_color = 'info';
    } elseif ($stats['moyenne_generale'] >= 12) {
        $statut_academique = 'Bien';
        $statut_color = 'primary';
    } elseif ($stats['moyenne_generale'] >= 10) {
        $statut_academique = 'Satisfaisant';
        $statut_color = 'warning';
    } else {
        $statut_academique = 'Insuffisant';
        $statut_color = 'danger';
    }
}

include '../../includes/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">
        <i class="fas fa-user-graduate me-2"></i>
        Étudiant : <?php echo htmlspecialchars($eleve['nom'] . ' ' . $eleve['prenom']); ?>
    </h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <div class="btn-group me-2">
            <a href="index.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left me-1"></i>
                Retour à la liste
            </a>
        </div>
        <?php if (checkPermission('students')): ?>
            <div class="btn-group me-2">
                <a href="records/edit.php?id=<?php echo $eleve_id; ?>" class="btn btn-primary">
                    <i class="fas fa-edit me-1"></i>
                    Modifier
                </a>
            </div>
        <?php endif; ?>
        <div class="btn-group">
            <button type="button" class="btn btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">
                <i class="fas fa-ellipsis-v me-1"></i>
                Actions
            </button>
            <ul class="dropdown-menu">
                <li><a class="dropdown-item" href="records/view.php?id=<?php echo $eleve_id; ?>"><i class="fas fa-folder me-2"></i>Dossier complet</a></li>
                <li><a class="dropdown-item" href="../evaluations/notes/student.php?eleve_id=<?php echo $eleve_id; ?>"><i class="fas fa-chart-line me-2"></i>Notes détaillées</a></li>
                <li><a class="dropdown-item" href="attendance/index.php?eleve_id=<?php echo $eleve_id; ?>"><i class="fas fa-calendar-check me-2"></i>Présences</a></li>
                <li><a class="dropdown-item" href="../finance/payments/index.php?eleve_id=<?php echo $eleve_id; ?>"><i class="fas fa-money-bill me-2"></i>Paiements</a></li>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item" href="transfers/new-transfer.php?eleve_id=<?php echo $eleve_id; ?>"><i class="fas fa-exchange-alt me-2"></i>Transfert</a></li>
                <li><a class="dropdown-item" href="transfers/new-exit.php?eleve_id=<?php echo $eleve_id; ?>"><i class="fas fa-sign-out-alt me-2"></i>Sortie</a></li>
            </ul>
        </div>
    </div>
</div>

<!-- Informations principales -->
<div class="row mb-4">
    <!-- Photo et informations de base -->
    <div class="col-md-4">
        <div class="card">
            <div class="card-body text-center">
                <?php if ($eleve['photo']): ?>
                    <img src="../../<?php echo htmlspecialchars($eleve['photo']); ?>" 
                         alt="Photo de l'étudiant" 
                         class="rounded-circle mb-3" 
                         style="width: 150px; height: 150px; object-fit: cover;">
                <?php else: ?>
                    <div class="bg-secondary rounded-circle d-flex align-items-center justify-content-center mx-auto mb-3" 
                         style="width: 150px; height: 150px;">
                        <i class="fas fa-user fa-4x text-white"></i>
                    </div>
                <?php endif; ?>
                
                <h4><?php echo htmlspecialchars($eleve['nom'] . ' ' . $eleve['prenom']); ?></h4>
                <p class="text-muted"><?php echo htmlspecialchars($eleve['numero_matricule']); ?></p>
                
                <div class="row text-center mt-3">
                    <div class="col-6">
                        <div class="h5 text-primary"><?php echo $stats['age'] ?? '-'; ?></div>
                        <small class="text-muted">Âge</small>
                    </div>
                    <div class="col-6">
                        <div class="h5 text-success"><?php echo $eleve['sexe'] === 'M' ? 'Garçon' : 'Fille'; ?></div>
                        <small class="text-muted">Sexe</small>
                    </div>
                </div>
                
                <div class="mt-3">
                    <span class="badge bg-<?php echo $statut_color; ?> fs-6">
                        <?php echo $statut_academique; ?>
                    </span>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Informations détaillées -->
    <div class="col-md-8">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-info-circle me-2"></i>
                    Informations détaillées
                </h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <table class="table table-borderless">
                            <tr>
                                <td class="fw-bold">Date de naissance :</td>
                                <td>
                                    <?php if ($eleve['date_naissance']): ?>
                                        <?php echo formatDate($eleve['date_naissance']); ?>
                                        <small class="text-muted">(<?php echo $stats['age']; ?> ans)</small>
                                    <?php else: ?>
                                        <span class="text-muted">Non renseignée</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <tr>
                                <td class="fw-bold">Lieu de naissance :</td>
                                <td><?php echo htmlspecialchars($eleve['lieu_naissance'] ?: 'Non renseigné'); ?></td>
                            </tr>
                            <tr>
                                <td class="fw-bold">Adresse :</td>
                                <td><?php echo htmlspecialchars($eleve['adresse'] ?: 'Non renseignée'); ?></td>
                            </tr>
                            <tr>
                                <td class="fw-bold">Téléphone :</td>
                                <td><?php echo htmlspecialchars($eleve['telephone'] ?: 'Non renseigné'); ?></td>
                            </tr>
                            <tr>
                                <td class="fw-bold">Email :</td>
                                <td><?php echo htmlspecialchars($eleve['email'] ?: 'Non renseigné'); ?></td>
                            </tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <table class="table table-borderless">
                            <tr>
                                <td class="fw-bold">Classe actuelle :</td>
                                <td>
                                    <?php if ($eleve['classe_nom']): ?>
                                        <span class="badge bg-primary"><?php echo htmlspecialchars($eleve['classe_nom']); ?></span>
                                        <br><small class="text-muted"><?php echo ucfirst($eleve['niveau']); ?> - <?php echo htmlspecialchars($eleve['section'] ?? ''); ?></small>
                                    <?php else: ?>
                                        <span class="text-muted">Non inscrit</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <tr>
                                <td class="fw-bold">Date d'inscription :</td>
                                <td>
                                    <?php if ($eleve['date_inscription']): ?>
                                        <?php echo formatDate($eleve['date_inscription']); ?>
                                        <br><small class="text-muted">Il y a <?php echo $stats['jours_inscription']; ?> jours</small>
                                    <?php else: ?>
                                        <span class="text-muted">Non renseignée</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <tr>
                                <td class="fw-bold">Statut :</td>
                                <td>
                                    <?php
                                    $status_colors = [
                                        'actif' => 'success',
                                        'transfere' => 'info',
                                        'abandonne' => 'warning',
                                        'diplome' => 'primary'
                                    ];
                                    $color = $status_colors[$eleve['status']] ?? 'secondary';
                                    ?>
                                    <span class="badge bg-<?php echo $color; ?>">
                                        <?php echo ucfirst($eleve['status']); ?>
                                    </span>
                                </td>
                            </tr>
                            <tr>
                                <td class="fw-bold">Moyenne générale :</td>
                                <td>
                                    <?php if ($stats['moyenne_generale']): ?>
                                        <span class="badge bg-<?php echo $stats['moyenne_generale'] >= 10 ? 'success' : 'danger'; ?> fs-6">
                                            <?php echo $stats['moyenne_generale']; ?>/20
                                        </span>
                                    <?php else: ?>
                                        <span class="text-muted">Non évalué</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <tr>
                                <td class="fw-bold">Évaluations passées :</td>
                                <td><?php echo $stats['nb_evaluations']; ?></td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Informations familiales -->
<div class="row mb-4">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-users me-2"></i>
                    Informations familiales
                </h5>
            </div>
            <div class="card-body">
                <table class="table table-borderless">
                    <tr>
                        <td class="fw-bold">Nom du père :</td>
                        <td><?php echo htmlspecialchars($eleve['nom_pere'] ?: 'Non renseigné'); ?></td>
                    </tr>
                    <tr>
                        <td class="fw-bold">Profession du père :</td>
                        <td><?php echo htmlspecialchars($eleve['profession_pere'] ?: 'Non renseignée'); ?></td>
                    </tr>
                    <tr>
                        <td class="fw-bold">Nom de la mère :</td>
                        <td><?php echo htmlspecialchars($eleve['nom_mere'] ?: 'Non renseigné'); ?></td>
                    </tr>
                    <tr>
                        <td class="fw-bold">Profession de la mère :</td>
                        <td><?php echo htmlspecialchars($eleve['profession_mere'] ?: 'Non renseignée'); ?></td>
                    </tr>
                    <tr>
                        <td class="fw-bold">Téléphone parent :</td>
                        <td><?php echo htmlspecialchars($eleve['telephone_parent'] ?: 'Non renseigné'); ?></td>
                    </tr>
                    <tr>
                        <td class="fw-bold">Personne de contact :</td>
                        <td><?php echo htmlspecialchars($eleve['personne_contact'] ?: 'Non renseignée'); ?></td>
                    </tr>
                    <tr>
                        <td class="fw-bold">Téléphone contact :</td>
                        <td><?php echo htmlspecialchars($eleve['telephone_contact'] ?: 'Non renseigné'); ?></td>
                    </tr>
                </table>
            </div>
        </div>
    </div>
    
    <!-- Notes récentes -->
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-chart-line me-2"></i>
                    Notes récentes
                </h5>
            </div>
            <div class="card-body">
                <?php if (!empty($notes_recentes)): ?>
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Matière</th>
                                    <th>Note</th>
                                    <th>Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($notes_recentes as $note): ?>
                                    <tr>
                                        <td>
                                            <?php echo htmlspecialchars($note['matiere_nom']); ?>
                                            <br><small class="text-muted"><?php echo ucfirst($note['evaluation_type']); ?></small>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?php echo $note['note'] >= 10 ? 'success' : 'danger'; ?>">
                                                <?php echo $note['note']; ?>/20
                                            </span>
                                        </td>
                                        <td>
                                            <small><?php echo formatDate($note['date_evaluation']); ?></small>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="text-center mt-2">
                        <a href="../evaluations/notes/student.php?eleve_id=<?php echo $eleve_id; ?>" class="btn btn-sm btn-outline-primary">
                            Voir toutes les notes
                        </a>
                    </div>
                <?php else: ?>
                    <p class="text-muted text-center">Aucune note enregistrée</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Activités récentes -->
<div class="row mb-4">
    <!-- Présences récentes -->
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-calendar-times me-2"></i>
                    Absences récentes
                </h5>
            </div>
            <div class="card-body">
                <?php if (!empty($absences_recentes)): ?>
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Type</th>
                                    <th>Motif</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($absences_recentes as $absence): ?>
                                    <tr>
                                        <td><?php echo formatDate($absence['date_absence']); ?></td>
                                        <td>
                                            <?php
                                            $absence_colors = [
                                                'absence' => 'danger',
                                                'retard' => 'warning',
                                                'absence_justifiee' => 'info',
                                                'retard_justifie' => 'secondary'
                                            ];
                                            $color = $absence_colors[$absence['type_absence']] ?? 'secondary';
                                            $type_labels = [
                                                'absence' => 'Absence',
                                                'retard' => 'Retard',
                                                'absence_justifiee' => 'Absence justifiée',
                                                'retard_justifie' => 'Retard justifié'
                                            ];
                                            $label = $type_labels[$absence['type_absence']] ?? ucfirst($absence['type_absence']);
                                            ?>
                                            <span class="badge bg-<?php echo $color; ?>">
                                                <?php echo $label; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <small><?php echo htmlspecialchars($absence['motif'] ?: '-'); ?></small>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="text-center mt-2">
                        <a href="attendance/index.php?eleve_id=<?php echo $eleve_id; ?>" class="btn btn-sm btn-outline-primary">
                            Voir toutes les absences
                        </a>
                    </div>
                <?php else: ?>
                    <p class="text-muted text-center">Aucune absence enregistrée</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Paiements récents -->
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-money-bill me-2"></i>
                    Paiements récents
                </h5>
            </div>
            <div class="card-body">
                <?php if (!empty($paiements_recents)): ?>
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Type</th>
                                    <th>Montant</th>
                                    <th>Mode</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($paiements_recents as $paiement): ?>
                                    <tr>
                                        <td><?php echo formatDate($paiement['date_paiement']); ?></td>
                                        <td>
                                            <span class="badge bg-info">
                                                <?php echo ucfirst($paiement['type_paiement']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <strong><?php echo formatMoney($paiement['montant']); ?></strong>
                                        </td>
                                        <td>
                                            <small><?php echo ucfirst($paiement['mode_paiement']); ?></small>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="text-center mt-2">
                        <a href="../finance/payments/index.php?eleve_id=<?php echo $eleve_id; ?>" class="btn btn-sm btn-outline-primary">
                            Voir tous les paiements
                        </a>
                    </div>
                <?php else: ?>
                    <p class="text-muted text-center">Aucun paiement enregistré</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>
