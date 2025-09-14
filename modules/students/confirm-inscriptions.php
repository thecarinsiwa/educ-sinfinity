<?php
/**
 * Module Gestion des Élèves - Confirmation des inscriptions
 * Application de gestion scolaire - République Démocratique du Congo
 */

require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../includes/functions.php';
require_once '../../includes/permissions-pages.php';

// Vérifier l'authentification et les permissions
requireLogin();

requirePagePermissionFromDB('students', 'confirm-inscriptions', 'edit', '../../dashboard.php');

$page_title = 'Confirmation des inscriptions';

// Obtenir l'année scolaire actuelle
$current_year = getCurrentAcademicYear();

if (!$current_year) {
    showMessage('error', 'Aucune année scolaire active.');
    redirectTo('index.php');
}

// Obtenir la devise par défaut
$devise_par_defaut = getDefaultCurrency();

// Traitement des actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $eleve_id = (int)($_POST['eleve_id'] ?? 0);
    $inscription_id = (int)($_POST['inscription_id'] ?? 0);
    
    if ($action === 'confirm' && $eleve_id && $inscription_id) {
        try {
            $database->beginTransaction();
            
            // Vérifier que l'inscription existe et a le statut "en attente"
            $inscription = $database->query(
                "SELECT i.*, e.nom, e.prenom, c.nom as classe_nom, c.frais_inscription
                 FROM inscriptions i
                 JOIN eleves e ON i.eleve_id = e.id
                 JOIN classes c ON i.classe_id = c.id
                 WHERE i.id = ? AND i.eleve_id = ? AND i.status = 'en_attente'",
                [$inscription_id, $eleve_id]
            )->fetch();
            
            if (!$inscription) {
                throw new Exception('Inscription non trouvée ou déjà confirmée.');
            }
            
            // Vérifier si les frais d'inscription sont complets
            $frais_inscription_classe = $inscription['frais_inscription'] ?? 0;
            $frais_payes = $inscription['frais_inscription_paye'] ?? 0;
            
            if ($frais_payes < $frais_inscription_classe) {
                throw new Exception("Les frais d'inscription ne sont pas complets. Payé: {$frais_payes}, Requis: {$frais_inscription_classe}");
            }
            
            // Confirmer l'inscription
            $database->execute(
                "UPDATE inscriptions SET status = 'inscrit', updated_at = NOW() WHERE id = ?",
                [$inscription_id]
            );
            
            // Mettre à jour le statut de l'élève
            $database->execute(
                "UPDATE eleves SET status = 'inscrit', updated_at = NOW() WHERE id = ?",
                [$eleve_id]
            );
            
            // Mettre à jour le statut dans la table demandes_admission
            $database->execute(
                "UPDATE demandes_admission SET 
                    status = 'inscrit', 
                    date_traitement = NOW(),
                    updated_at = NOW(),
                    commentaire_traitement = 'Inscription confirmée - Frais d\'inscription complets'
                 WHERE eleve_cree_id = ? AND annee_scolaire_id = ?",
                [$eleve_id, $current_year['id']]
            );
            
            // Log de l'action
            logAction('inscription_confirmee', [
                'eleve_id' => $eleve_id,
                'inscription_id' => $inscription_id,
                'classe_id' => $inscription['classe_id'],
                'annee_scolaire_id' => $current_year['id'],
                'frais_payes' => $frais_payes,
                'frais_requis' => $frais_inscription_classe
            ]);
            
            $database->commit();
            showMessage('success', "L'inscription de {$inscription['nom']} {$inscription['prenom']} a été confirmée avec succès !");
            
        } catch (Exception $e) {
            $database->rollback();
            showMessage('error', 'Erreur lors de la confirmation : ' . $e->getMessage());
        }
    } elseif ($action === 'reject' && $eleve_id && $inscription_id) {
        try {
            $database->beginTransaction();
            
            // Rejeter l'inscription
            $database->execute(
                "UPDATE inscriptions SET status = 'rejetee', updated_at = NOW() WHERE id = ?",
                [$inscription_id]
            );
            
            // Mettre à jour le statut dans la table demandes_admission
            $database->execute(
                "UPDATE demandes_admission SET 
                    status = 'refusee', 
                    date_traitement = NOW(),
                    updated_at = NOW(),
                    commentaire_traitement = 'Inscription rejetée - Frais d\'inscription insuffisants'
                 WHERE eleve_cree_id = ? AND annee_scolaire_id = ?",
                [$eleve_id, $current_year['id']]
            );
            
            // Log de l'action
            logAction('inscription_rejetee', [
                'eleve_id' => $eleve_id,
                'inscription_id' => $inscription_id,
                'annee_scolaire_id' => $current_year['id']
            ]);
            
            $database->commit();
            showMessage('success', 'Inscription rejetée avec succès.');
            
        } catch (Exception $e) {
            $database->rollback();
            showMessage('error', 'Erreur lors du rejet : ' . $e->getMessage());
        }
    }
    
    // Rediriger pour éviter la soumission multiple
    redirectTo('confirm-inscriptions.php');
}

// Récupérer les inscriptions en attente avec informations de devise et numéros de reçu
$inscriptions_en_attente = $database->query(
    "SELECT i.*, e.nom, e.prenom, e.numero_matricule, e.sexe, e.telephone,
            c.nom as classe_nom, c.niveau, c.frais_inscription,
            a.annee as annee_scolaire,
            COALESCE(p.montant_devise_par_defaut, i.frais_inscription_paye) as montant_devise_par_defaut,
            p.devise_id, d.code as devise_code, d.symbole as devise_symbole, d.nom as devise_nom,
            p2.numeros_recu
     FROM inscriptions i
     JOIN eleves e ON i.eleve_id = e.id
     JOIN classes c ON i.classe_id = c.id
     JOIN annees_scolaires a ON i.annee_scolaire_id = a.id
     LEFT JOIN (
         SELECT p.eleve_id, p.annee_scolaire_id, 
                SUM(p.montant_devise_par_defaut) as montant_devise_par_defaut, 
                MAX(p.devise_id) as devise_id
         FROM paiements p
         JOIN type_frais tf ON p.type_frais_id = tf.id
         WHERE tf.nom = 'Frais d\'inscription' OR tf.nom LIKE '%inscription%'
         GROUP BY p.eleve_id, p.annee_scolaire_id
     ) p ON i.eleve_id = p.eleve_id AND i.annee_scolaire_id = p.annee_scolaire_id
     LEFT JOIN (
         SELECT p.eleve_id, p.annee_scolaire_id, 
                GROUP_CONCAT(p.recu_numero ORDER BY p.created_at ASC SEPARATOR ', ') as numeros_recu
         FROM paiements p
         JOIN type_frais tf ON p.type_frais_id = tf.id
         WHERE tf.nom = 'Frais d\'inscription' OR tf.nom LIKE '%inscription%'
         GROUP BY p.eleve_id, p.annee_scolaire_id
     ) p2 ON i.eleve_id = p2.eleve_id AND i.annee_scolaire_id = p2.annee_scolaire_id
     LEFT JOIN devises d ON p.devise_id = d.id
     WHERE i.status = 'en_attente' AND i.annee_scolaire_id = ?
     ORDER BY i.created_at ASC",
    [$current_year['id']]
)->fetchAll();

// Statistiques
$stats = [
    'total_en_attente' => count($inscriptions_en_attente),
    'frais_complets' => 0,
    'frais_partiels' => 0
];

foreach ($inscriptions_en_attente as $inscription) {
    $frais_requis = $inscription['frais_inscription'] ?? 0;
    $frais_payes = $inscription['frais_inscription_paye'] ?? 0;
    
    if ($frais_payes >= $frais_requis) {
        $stats['frais_complets']++;
    } else {
        $stats['frais_partiels']++;
    }
}

include '../../includes/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">
        <i class="fas fa-check-circle me-2"></i>
        Confirmation des inscriptions
    </h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <div class="btn-group me-2">
            <a href="index.php" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-1"></i>
                Retour au tableau de bord
            </a>
        </div>
        <?php if ($devise_par_defaut): ?>
            <div class="btn-group me-2">
                <button type="button" class="btn btn-outline-info">
                    <i class="fas fa-exchange-alt me-1"></i>
                    Devise par défaut : <?php echo htmlspecialchars($devise_par_defaut['code']); ?> 
                    (<?php echo htmlspecialchars($devise_par_defaut['symbole']); ?>)
                </button>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Statistiques -->
<div class="row mb-4">
    <div class="col-md-4">
        <div class="card text-white bg-warning">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h4><?php echo $stats['total_en_attente']; ?></h4>
                        <p class="mb-0">Inscriptions en attente</p>
                    </div>
                    <div class="align-self-center">
                        <i class="fas fa-clock fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card text-white bg-success">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h4><?php echo $stats['frais_complets']; ?></h4>
                        <p class="mb-0">Frais complets</p>
                    </div>
                    <div class="align-self-center">
                        <i class="fas fa-check fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card text-white bg-info">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h4><?php echo $stats['frais_partiels']; ?></h4>
                        <p class="mb-0">Frais partiels</p>
                    </div>
                    <div class="align-self-center">
                        <i class="fas fa-exclamation-triangle fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Liste des inscriptions en attente -->
<div class="card">
    <div class="card-header">
        <h5 class="mb-0">
            <i class="fas fa-list me-2"></i>
            Inscriptions en attente de confirmation
        </h5>
    </div>
    <div class="card-body">
        <?php if (empty($inscriptions_en_attente)): ?>
            <div class="text-center py-5">
                <i class="fas fa-check-circle fa-3x text-success mb-3"></i>
                <h5 class="text-success">Aucune inscription en attente</h5>
                <p class="text-muted">Toutes les inscriptions ont été confirmées ou rejetées.</p>
                <a href="index.php" class="btn btn-primary">
                    <i class="fas fa-arrow-left me-1"></i>
                    Retour au tableau de bord
                </a>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead class="table-light">
                        <tr>
                            <th>Élève</th>
                            <th>Classe</th>
                            <th>Frais d'inscription</th>
                            <th>Devise</th>
                            <th>Numéro de reçu</th>
                            <th>Date d'inscription</th>
                            <th>Statut des frais</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($inscriptions_en_attente as $inscription): ?>
                            <?php
                            $frais_requis = $inscription['frais_inscription'] ?? 0;
                            $frais_payes = $inscription['frais_inscription_paye'] ?? 0;
                            $montant_devise_par_defaut = $inscription['montant_devise_par_defaut'] ?? $frais_payes;
                            $frais_complets = $frais_payes >= $frais_requis;
                            $pourcentage = $frais_requis > 0 ? ($frais_payes / $frais_requis) * 100 : 0;
                            ?>
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="avatar-sm me-3">
                                            <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                                                <i class="fas fa-user"></i>
                                            </div>
                                        </div>
                                        <div>
                                            <h6 class="mb-0"><?php echo htmlspecialchars($inscription['nom'] . ' ' . $inscription['prenom']); ?></h6>
                                            <small class="text-muted">
                                                <?php echo htmlspecialchars($inscription['numero_matricule']); ?> - 
                                                <?php echo $inscription['sexe'] === 'M' ? 'Garçon' : 'Fille'; ?>
                                            </small>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge bg-<?php 
                                        echo $inscription['niveau'] === 'maternelle' ? 'warning' : 
                                            ($inscription['niveau'] === 'primaire' ? 'success' : 'primary'); 
                                    ?>">
                                        <?php echo htmlspecialchars($inscription['classe_nom']); ?>
                                    </span>
                                    <br>
                                    <small class="text-muted"><?php echo ucfirst($inscription['niveau']); ?></small>
                                </td>
                                <td>
                                    <div class="mb-2">
                                        <strong><?php echo formatMoney($frais_payes); ?> / <?php echo formatMoney($frais_requis); ?></strong>
                                        <?php if ($inscription['devise_symbole']): ?>
                                            <span class="text-muted"><?php echo htmlspecialchars($inscription['devise_symbole']); ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <?php if ($devise_par_defaut && $inscription['devise_code'] && $inscription['devise_code'] !== $devise_par_defaut['code']): ?>
                                        <div class="mb-2">
                                            <small class="text-muted">
                                                <i class="fas fa-exchange-alt me-1"></i>
                                                Équivalent : <?php echo formatMoney($montant_devise_par_defaut); ?> 
                                                <?php echo htmlspecialchars($devise_par_defaut['symbole']); ?>
                                            </small>
                                        </div>
                                    <?php endif; ?>
                                    <div class="progress" style="height: 8px;">
                                        <div class="progress-bar <?php echo $frais_complets ? 'bg-success' : 'bg-warning'; ?>" 
                                             style="width: <?php echo min(100, $pourcentage); ?>%"></div>
                                    </div>
                                    <small class="text-muted"><?php echo number_format($pourcentage, 1); ?>% payé</small>
                                </td>
                                <td>
                                    <?php if ($inscription['devise_code']): ?>
                                        <span class="badge bg-info">
                                            <?php echo htmlspecialchars($inscription['devise_code']); ?>
                                        </span>
                                        <br>
                                        <small class="text-muted"><?php echo htmlspecialchars($inscription['devise_nom']); ?></small>
                                    <?php else: ?>
                                        <span class="text-muted">Non spécifiée</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($inscription['numeros_recu']): ?>
                                        <div class="text-center">
                                            <span class="badge bg-primary">
                                                <i class="fas fa-receipt me-1"></i>
                                                <?php echo htmlspecialchars($inscription['numeros_recu']); ?>
                                            </span>
                                        </div>
                                    <?php else: ?>
                                        <div class="text-center">
                                            <span class="text-muted">
                                                <i class="fas fa-times me-1"></i>
                                                Aucun
                                            </span>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="text-center">
                                        <div class="fw-bold"><?php echo date('d/m/Y', strtotime($inscription['created_at'])); ?></div>
                                        <small class="text-muted"><?php echo date('H:i', strtotime($inscription['created_at'])); ?></small>
                                    </div>
                                </td>
                                <td>
                                    <?php if ($frais_complets): ?>
                                        <span class="badge bg-success">
                                            <i class="fas fa-check me-1"></i>Complets
                                        </span>
                                    <?php else: ?>
                                        <span class="badge bg-warning">
                                            <i class="fas fa-exclamation-triangle me-1"></i>Partiels
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="btn-group" role="group">
                                        <?php if ($frais_complets): ?>
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="action" value="confirm">
                                                <input type="hidden" name="eleve_id" value="<?php echo $inscription['eleve_id']; ?>">
                                                <input type="hidden" name="inscription_id" value="<?php echo $inscription['id']; ?>">
                                                <button type="submit" class="btn btn-success btn-sm" 
                                                        onclick="return confirm('Confirmer l\'inscription de <?php echo htmlspecialchars($inscription['nom'] . ' ' . $inscription['prenom']); ?> ?')">
                                                    <i class="fas fa-check me-1"></i>Confirmer
                                                </button>
                                            </form>
                                        <?php else: ?>
                                            <button type="button" class="btn btn-secondary btn-sm" disabled>
                                                <i class="fas fa-clock me-1"></i>En attente
                                            </button>
                                        <?php endif; ?>
                                        
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="action" value="reject">
                                            <input type="hidden" name="eleve_id" value="<?php echo $inscription['eleve_id']; ?>">
                                            <input type="hidden" name="inscription_id" value="<?php echo $inscription['id']; ?>">
                                            <button type="submit" class="btn btn-danger btn-sm" 
                                                    onclick="return confirm('Rejeter l\'inscription de <?php echo htmlspecialchars($inscription['nom'] . ' ' . $inscription['prenom']); ?> ? Cette action est irréversible.')">
                                                <i class="fas fa-times me-1"></i>Rejeter
                                            </button>
                                        </form>
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

<!-- Informations sur la devise -->
<?php if ($devise_par_defaut): ?>
<div class="row mt-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header bg-warning text-white">
                <h5 class="mb-0">
                    <i class="fas fa-exchange-alt me-2"></i>
                    Informations sur la devise par défaut
                </h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-4 text-center">
                        <h6 class="text-muted">Devise par défaut</h6>
                        <h4 class="text-warning">
                            <?php echo htmlspecialchars($devise_par_defaut['symbole']); ?>
                            <?php echo htmlspecialchars($devise_par_defaut['code']); ?>
                        </h4>
                        <small class="text-muted"><?php echo htmlspecialchars($devise_par_defaut['nom']); ?></small>
                    </div>
                    <div class="col-md-8">
                        <h6><i class="fas fa-info-circle me-2"></i>Conversion automatique</h6>
                        <p class="text-muted">
                            Tous les montants sont automatiquement convertis en devise par défaut pour faciliter 
                            la comparaison et la gestion financière. Les montants originaux sont conservés 
                            pour référence.
                        </p>
                        <div class="alert alert-info">
                            <small>
                                <i class="fas fa-lightbulb me-1"></i>
                                <strong>Conseil :</strong> Utilisez la devise par défaut comme référence principale 
                                pour évaluer les frais d'inscription et prendre les décisions de confirmation.
                            </small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Informations -->
<div class="row mt-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header bg-info text-white">
                <h5 class="mb-0">
                    <i class="fas fa-info-circle me-2"></i>
                    Informations sur la confirmation des inscriptions
                </h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <h6><i class="fas fa-check-circle text-success me-2"></i>Inscriptions confirmables</h6>
                        <p class="text-muted">
                            Les inscriptions avec des frais d'inscription complets peuvent être confirmées. 
                            L'élève passera alors au statut "inscrit" et pourra commencer les cours.
                        </p>
                    </div>
                    <div class="col-md-6">
                        <h6><i class="fas fa-exclamation-triangle text-warning me-2"></i>Inscriptions en attente</h6>
                        <p class="text-muted">
                            Les inscriptions avec des frais partiels restent en attente jusqu'à ce que 
                            le paiement soit complété. Vous pouvez les rejeter si nécessaire.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.avatar-sm {
    width: 40px;
    height: 40px;
}

.progress {
    border-radius: 10px;
}

.btn-group .btn {
    margin-right: 2px;
}

.btn-group .btn:last-child {
    margin-right: 0;
}
</style>

<?php include '../../includes/footer.php'; ?>
