<?php
/**
 * Script de maintenance pour les caisses
 * Application de gestion scolaire - République Démocratique du Congo
 */

require_once '../../../config/config.php';
require_once '../../../config/database.php';
require_once '../../../includes/functions.php';
require_once '../../../includes/permissions-pages.php';

// Vérifier l'authentification et les permissions
requireLogin();

// Vérifier l'accès à cette page
requirePagePermissionFromDB('finance', 'expenses/maintenance_caisses', 'read', '../../dashboard.php');

$page_title = 'Maintenance des Caisses';

// Obtenir la devise par défaut
$devise_par_defaut = getDefaultCurrency();

require_once 'caisse_functions.php';
require_once '../../includes/permissions-pages.php';

// Traitement des actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'fermer_sessions_expirees':
                $nb_fermees = fermerSessionsExpirees();
                if ($nb_fermees > 0) {
                    showMessage('success', "$nb_fermees session(s) fermée(s) automatiquement.");
                } else {
                    showMessage('info', 'Aucune session expirée trouvée.');
                }
                break;

            case 'nettoyer_mouvements_orphelins':
                try {
                    $database->beginTransaction();
                    
                    // Supprimer les mouvements de paiements qui n'existent plus
                    $result1 = $database->execute(
                        "DELETE mc FROM mouvements_caisse mc 
                         WHERE mc.reference LIKE 'PAIEMENT-%' 
                         AND mc.reference NOT IN (
                             SELECT CONCAT('PAIEMENT-', p.id) 
                             FROM paiements p
                         )"
                    );
                    
                    // Supprimer les mouvements de dépenses qui n'existent plus
                    $result2 = $database->execute(
                        "DELETE mc FROM mouvements_caisse mc 
                         WHERE mc.reference LIKE 'DEPENSE-%' 
                         AND mc.reference NOT IN (
                             SELECT CONCAT('DEPENSE-', d.id) 
                             FROM depenses d
                         )"
                    );
                    
                    $database->commit();
                    showMessage('success', 'Nettoyage des mouvements orphelins terminé.');
                    
                } catch (Exception $e) {
                    $database->rollback();
                    showMessage('error', 'Erreur lors du nettoyage : ' . $e->getMessage());
                }
                break;

            case 'recalculer_soldes':
                try {
                    $database->beginTransaction();
                    
                    // Recalculer les soldes de fermeture pour toutes les sessions fermées
                    $sessions = $database->query(
                        "SELECT id, solde_ouverture FROM sessions_caisse WHERE statut = 'fermee'"
                    )->fetchAll();
                    
                    $nb_recalculees = 0;
                    foreach ($sessions as $session) {
                        $solde_courant = getSoldeCaisseCourant($session['id']);
                        
                        $database->execute(
                            "UPDATE sessions_caisse SET solde_fermeture = ? WHERE id = ?",
                            [$solde_courant, $session['id']]
                        );
                        $nb_recalculees++;
                    }
                    
                    $database->commit();
                    showMessage('success', "$nb_recalculees session(s) recalculée(s).");
                    
                } catch (Exception $e) {
                    $database->rollback();
                    showMessage('error', 'Erreur lors du recalcul : ' . $e->getMessage());
                }
                break;
        }
        
        redirectTo('maintenance_caisses.php');
    }
}

// Statistiques de maintenance
$stats = [];

// Sessions ouvertes depuis plus de 24h
$stats['sessions_expirees'] = $database->query(
    "SELECT COUNT(*) as total FROM sessions_caisse 
     WHERE statut = 'ouverte' 
     AND date_ouverture < DATE_SUB(NOW(), INTERVAL 24 HOUR)"
)->fetch()['total'];

// Mouvements orphelins
$stats['mouvements_orphelins_paiements'] = $database->query(
    "SELECT COUNT(*) as total FROM mouvements_caisse mc 
     WHERE mc.reference LIKE 'PAIEMENT-%' 
     AND mc.reference NOT IN (
         SELECT CONCAT('PAIEMENT-', p.id) FROM paiements p
     )"
)->fetch()['total'];

$stats['mouvements_orphelins_depenses'] = $database->query(
    "SELECT COUNT(*) as total FROM mouvements_caisse mc 
     WHERE mc.reference LIKE 'DEPENSE-%' 
     AND mc.reference NOT IN (
         SELECT CONCAT('DEPENSE-', d.id) FROM depenses d
     )"
)->fetch()['total'];

// Sessions avec soldes incohérents
$stats['sessions_incoherentes'] = $database->query(
    "SELECT COUNT(*) as total FROM sessions_caisse sc
     WHERE sc.statut = 'fermee'
     AND ABS(sc.solde_fermeture - (
         sc.solde_ouverture + 
         COALESCE((SELECT SUM(montant) FROM mouvements_caisse mc WHERE mc.session_caisse_id = sc.id AND mc.type_mouvement = 'entree'), 0) -
         COALESCE((SELECT SUM(montant) FROM mouvements_caisse mc WHERE mc.session_caisse_id = sc.id AND mc.type_mouvement = 'sortie'), 0)
     )) > 0.01"
)->fetch()['total'];

include '../../../includes/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">
        <i class="fas fa-tools me-2"></i>
        Maintenance des Caisses
    </h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <div class="btn-group me-2">
            <a href="caisses.php" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-1"></i>
                Retour aux caisses
            </a>
        </div>
    </div>
</div>

<!-- Alertes de maintenance -->
<div class="row mb-4">
    <?php if ($stats['sessions_expirees'] > 0): ?>
    <div class="col-md-6">
        <div class="alert alert-warning">
            <h6><i class="fas fa-exclamation-triangle me-2"></i>Sessions Expirées</h6>
            <p class="mb-2"><?php echo $stats['sessions_expirees']; ?> session(s) ouverte(s) depuis plus de 24h.</p>
            <form method="POST" class="d-inline">
                <input type="hidden" name="action" value="fermer_sessions_expirees">
                <button type="submit" class="btn btn-warning btn-sm">
                    <i class="fas fa-lock me-1"></i>Fermer automatiquement
                </button>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($stats['mouvements_orphelins_paiements'] > 0 || $stats['mouvements_orphelins_depenses'] > 0): ?>
    <div class="col-md-6">
        <div class="alert alert-danger">
            <h6><i class="fas fa-exclamation-circle me-2"></i>Mouvements Orphelins</h6>
            <p class="mb-2">
                <?php echo $stats['mouvements_orphelins_paiements']; ?> mouvement(s) de paiement orphelin(s)<br>
                <?php echo $stats['mouvements_orphelins_depenses']; ?> mouvement(s) de dépense orphelin(s)
            </p>
            <form method="POST" class="d-inline">
                <input type="hidden" name="action" value="nettoyer_mouvements_orphelins">
                <button type="submit" class="btn btn-danger btn-sm">
                    <i class="fas fa-trash me-1"></i>Nettoyer
                </button>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($stats['sessions_incoherentes'] > 0): ?>
    <div class="col-md-6">
        <div class="alert alert-info">
            <h6><i class="fas fa-calculator me-2"></i>Soldes Incohérents</h6>
            <p class="mb-2"><?php echo $stats['sessions_incoherentes']; ?> session(s) avec des soldes incohérents.</p>
            <form method="POST" class="d-inline">
                <input type="hidden" name="action" value="recalculer_soldes">
                <button type="submit" class="btn btn-info btn-sm">
                    <i class="fas fa-sync me-1"></i>Recalculer
                </button>
            </form>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- Outils de maintenance -->
<div class="row">
    <div class="col-md-4">
        <div class="card">
            <div class="card-header">
                <h6 class="mb-0">
                    <i class="fas fa-clock me-2"></i>
                    Sessions Expirées
                </h6>
            </div>
            <div class="card-body">
                <p class="card-text">Fermer automatiquement les sessions ouvertes depuis plus de 24h.</p>
                <form method="POST">
                    <input type="hidden" name="action" value="fermer_sessions_expirees">
                    <button type="submit" class="btn btn-warning">
                        <i class="fas fa-lock me-1"></i>
                        Fermer les sessions expirées
                    </button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-md-4">
        <div class="card">
            <div class="card-header">
                <h6 class="mb-0">
                    <i class="fas fa-broom me-2"></i>
                    Nettoyage
                </h6>
            </div>
            <div class="card-body">
                <p class="card-text">Supprimer les mouvements liés à des paiements ou dépenses supprimés.</p>
                <form method="POST">
                    <input type="hidden" name="action" value="nettoyer_mouvements_orphelins">
                    <button type="submit" class="btn btn-danger">
                        <i class="fas fa-trash me-1"></i>
                        Nettoyer les mouvements orphelins
                    </button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-md-4">
        <div class="card">
            <div class="card-header">
                <h6 class="mb-0">
                    <i class="fas fa-calculator me-2"></i>
                    Recalcul des Soldes
                </h6>
            </div>
            <div class="card-body">
                <p class="card-text">Recalculer les soldes de fermeture basés sur les mouvements réels.</p>
                <form method="POST">
                    <input type="hidden" name="action" value="recalculer_soldes">
                    <button type="submit" class="btn btn-info">
                        <i class="fas fa-sync me-1"></i>
                        Recalculer les soldes
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Statistiques détaillées -->
<div class="card mt-4">
    <div class="card-header">
        <h6 class="mb-0">
            <i class="fas fa-chart-bar me-2"></i>
            Statistiques de Maintenance
        </h6>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-3">
                <div class="text-center">
                    <h4 class="text-warning"><?php echo $stats['sessions_expirees']; ?></h4>
                    <small>Sessions expirées</small>
                </div>
            </div>
            <div class="col-md-3">
                <div class="text-center">
                    <h4 class="text-danger"><?php echo $stats['mouvements_orphelins_paiements']; ?></h4>
                    <small>Mouvements paiements orphelins</small>
                </div>
            </div>
            <div class="col-md-3">
                <div class="text-center">
                    <h4 class="text-danger"><?php echo $stats['mouvements_orphelins_depenses']; ?></h4>
                    <small>Mouvements dépenses orphelins</small>
                </div>
            </div>
            <div class="col-md-3">
                <div class="text-center">
                    <h4 class="text-info"><?php echo $stats['sessions_incoherentes']; ?></h4>
                    <small>Sessions incohérentes</small>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Instructions -->
<div class="card mt-4">
    <div class="card-header">
        <h6 class="mb-0">
            <i class="fas fa-info-circle me-2"></i>
            Instructions de Maintenance
        </h6>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-4">
                <h6>Sessions Expirées :</h6>
                <ul class="small">
                    <li>Ferme automatiquement les sessions ouvertes depuis plus de 24h</li>
                    <li>Calcule le solde final basé sur les mouvements</li>
                    <li>Ajoute une observation de fermeture automatique</li>
                </ul>
            </div>
            <div class="col-md-4">
                <h6>Nettoyage :</h6>
                <ul class="small">
                    <li>Supprime les mouvements de paiements supprimés</li>
                    <li>Supprime les mouvements de dépenses supprimées</li>
                    <li>Maintient l'intégrité des données</li>
                </ul>
            </div>
            <div class="col-md-4">
                <h6>Recalcul :</h6>
                <ul class="small">
                    <li>Recalcule les soldes de fermeture</li>
                    <li>Basé sur les mouvements réels enregistrés</li>
                    <li>Corrige les incohérences</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<?php include '../../../includes/footer.php'; ?>

