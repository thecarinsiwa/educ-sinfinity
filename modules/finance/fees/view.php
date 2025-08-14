<?php
/**
 * Module de gestion financière - Voir un frais scolaire
 * Application de gestion scolaire - République Démocratique du Congo
 */

require_once '../../../config/config.php';
require_once '../../../config/database.php';
require_once '../../../includes/functions.php';

// Vérifier l'authentification et les permissions
requireLogin();
if (!checkPermission('finance') && !checkPermission('finance_view')) {
    showMessage('error', 'Accès refusé à cette fonctionnalité.');
    redirectTo('index.php');
}

// Récupérer l'ID du frais
$id = (int)($_GET['id'] ?? 0);
if (!$id) {
    showMessage('error', 'Frais non spécifié.');
    redirectTo('index.php');
}

// Récupérer les informations du frais
$sql = "SELECT f.*, c.nom as classe_nom, c.niveau, c.section
        FROM frais_scolaires f
        JOIN classes c ON f.classe_id = c.id
        WHERE f.id = ?";

$frais = $database->query($sql, [$id])->fetch();

if (!$frais) {
    showMessage('error', 'Frais non trouvé.');
    redirectTo('index.php');
}

$page_title = 'Détails du frais - ' . $frais['libelle'];

include '../../../includes/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">
        <i class="fas fa-eye me-2"></i>
        Détails du frais scolaire
    </h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <div class="btn-group me-2">
            <a href="index.php" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-1"></i>
                Retour à la liste
            </a>
        </div>
        <?php if (checkPermission('finance')): ?>
            <div class="btn-group">
                <a href="edit.php?id=<?php echo $frais['id']; ?>" class="btn btn-outline-primary">
                    <i class="fas fa-edit me-1"></i>
                    Modifier
                </a>
                <a href="duplicate.php?id=<?php echo $frais['id']; ?>" class="btn btn-outline-secondary">
                    <i class="fas fa-copy me-1"></i>
                    Dupliquer
                </a>
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="row">
    <div class="col-lg-8">
        <!-- Informations du frais -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-info-circle me-2"></i>
                    Informations du frais
                </h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <table class="table table-borderless">
                            <tr>
                                <td class="fw-bold" style="width: 150px;">Libellé :</td>
                                <td><?php echo htmlspecialchars($frais['libelle']); ?></td>
                            </tr>
                            <tr>
                                <td class="fw-bold">Type de frais :</td>
                                <td>
                                    <?php
                                    $types = [
                                        'inscription' => 'Frais d\'inscription',
                                        'mensualite' => 'Mensualité',
                                        'examen' => 'Frais d\'examen',
                                        'uniforme' => 'Uniforme scolaire',
                                        'transport' => 'Transport scolaire',
                                        'cantine' => 'Cantine',
                                        'autre' => 'Autre'
                                    ];
                                    $type_colors = [
                                        'inscription' => 'primary',
                                        'mensualite' => 'success',
                                        'examen' => 'warning',
                                        'uniforme' => 'info',
                                        'transport' => 'secondary',
                                        'cantine' => 'dark',
                                        'autre' => 'light'
                                    ];
                                    $color = $type_colors[$frais['type_frais']] ?? 'secondary';
                                    ?>
                                    <span class="badge bg-<?php echo $color; ?> fs-6">
                                        <?php echo $types[$frais['type_frais']] ?? ucfirst($frais['type_frais']); ?>
                                    </span>
                                </td>
                            </tr>
                            <tr>
                                <td class="fw-bold">Montant :</td>
                                <td>
                                    <span class="fs-4 text-success fw-bold">
                                        <?php echo formatMoney($frais['montant']); ?>
                                    </span>
                                </td>
                            </tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <table class="table table-borderless">
                            <tr>
                                <td class="fw-bold" style="width: 150px;">Classe :</td>
                                <td><?php echo htmlspecialchars($frais['classe_nom']); ?></td>
                            </tr>
                            <tr>
                                <td class="fw-bold">Niveau :</td>
                                <td>
                                    <?php
                                    $niveau_colors = [
                                        'maternelle' => 'warning',
                                        'primaire' => 'success',
                                        'secondaire' => 'primary'
                                    ];
                                    $color = $niveau_colors[$frais['niveau']] ?? 'secondary';
                                    ?>
                                    <span class="badge bg-<?php echo $color; ?>">
                                        <?php echo ucfirst($frais['niveau']); ?>
                                    </span>
                                </td>
                            </tr>
                            <tr>
                                <td class="fw-bold">Obligatoire :</td>
                                <td>
                                    <?php if ($frais['obligatoire']): ?>
                                        <span class="badge bg-danger">
                                            <i class="fas fa-star me-1"></i>Obligatoire
                                        </span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Optionnel</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>
                
                <?php if ($frais['date_echeance']): ?>
                <div class="row mt-3">
                    <div class="col-12">
                        <h6 class="fw-bold">Date d'échéance :</h6>
                        <p>
                            <?php echo formatDate($frais['date_echeance']); ?>
                            <?php
                            $jours_restants = (strtotime($frais['date_echeance']) - time()) / (24 * 3600);
                            if ($jours_restants < 0): ?>
                                <span class="badge bg-danger ms-2">Échue</span>
                            <?php elseif ($jours_restants < 30): ?>
                                <span class="badge bg-warning ms-2"><?php echo round($jours_restants); ?> jours restants</span>
                            <?php else: ?>
                                <span class="badge bg-success ms-2"><?php echo round($jours_restants); ?> jours restants</span>
                            <?php endif; ?>
                        </p>
                    </div>
                </div>
                <?php endif; ?>
                
                <?php if ($frais['description']): ?>
                <div class="row mt-3">
                    <div class="col-12">
                        <h6 class="fw-bold">Description :</h6>
                        <p class="text-muted"><?php echo nl2br(htmlspecialchars($frais['description'])); ?></p>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Statistiques des paiements -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-chart-bar me-2"></i>
                    Statistiques des paiements
                </h5>
            </div>
            <div class="card-body">
                <?php
                // Calculer les statistiques des paiements pour ce frais
                try {
                    $stats_sql = "SELECT 
                                    COUNT(DISTINCT i.eleve_id) as total_eleves,
                                    COUNT(DISTINCT p.eleve_id) as eleves_payes,
                                    COALESCE(SUM(p.montant), 0) as total_collecte
                                  FROM inscriptions i
                                  JOIN classes c ON i.classe_id = c.id
                                  LEFT JOIN paiements p ON i.eleve_id = p.eleve_id 
                                    AND p.type_paiement = ? 
                                    AND p.annee_scolaire_id = ?
                                  WHERE c.id = ? AND i.annee_scolaire_id = ? AND i.status = 'inscrit'";
                    
                    $stats = $database->query($stats_sql, [
                        $frais['type_frais'], 
                        $frais['annee_scolaire_id'],
                        $frais['classe_id'], 
                        $frais['annee_scolaire_id']
                    ])->fetch();
                    
                    $total_eleves = $stats['total_eleves'] ?? 0;
                    $eleves_payes = $stats['eleves_payes'] ?? 0;
                    $total_collecte = $stats['total_collecte'] ?? 0;
                    $total_attendu = $total_eleves * $frais['montant'];
                    $taux_paiement = $total_eleves > 0 ? round(($eleves_payes / $total_eleves) * 100, 1) : 0;
                    
                } catch (Exception $e) {
                    $total_eleves = 0;
                    $eleves_payes = 0;
                    $total_collecte = 0;
                    $total_attendu = 0;
                    $taux_paiement = 0;
                }
                ?>
                
                <div class="row text-center">
                    <div class="col-md-3">
                        <div class="card border-0 bg-light">
                            <div class="card-body">
                                <h4 class="text-primary"><?php echo $total_eleves; ?></h4>
                                <small class="text-muted">Élèves concernés</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card border-0 bg-light">
                            <div class="card-body">
                                <h4 class="text-success"><?php echo $eleves_payes; ?></h4>
                                <small class="text-muted">Ont payé</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card border-0 bg-light">
                            <div class="card-body">
                                <h4 class="text-info"><?php echo $taux_paiement; ?>%</h4>
                                <small class="text-muted">Taux de paiement</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card border-0 bg-light">
                            <div class="card-body">
                                <h4 class="text-success"><?php echo formatMoney($total_collecte); ?></h4>
                                <small class="text-muted">Collecté</small>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="mt-3">
                    <div class="progress">
                        <div class="progress-bar bg-success" 
                             role="progressbar" 
                             style="width: <?php echo $taux_paiement; ?>%"
                             aria-valuenow="<?php echo $taux_paiement; ?>" 
                             aria-valuemin="0" 
                             aria-valuemax="100">
                            <?php echo $taux_paiement; ?>%
                        </div>
                    </div>
                    <small class="text-muted">
                        Collecté : <?php echo formatMoney($total_collecte); ?> / 
                        Attendu : <?php echo formatMoney($total_attendu); ?>
                    </small>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <!-- Actions rapides -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-bolt me-2"></i>
                    Actions rapides
                </h5>
            </div>
            <div class="card-body">
                <div class="d-grid gap-2">
                    <?php if (checkPermission('finance')): ?>
                        <a href="edit.php?id=<?php echo $frais['id']; ?>" class="btn btn-outline-primary">
                            <i class="fas fa-edit me-2"></i>
                            Modifier ce frais
                        </a>
                        <a href="duplicate.php?id=<?php echo $frais['id']; ?>" class="btn btn-outline-secondary">
                            <i class="fas fa-copy me-2"></i>
                            Dupliquer pour autre classe
                        </a>
                        <a href="../payments/add.php?type=<?php echo $frais['type_frais']; ?>" class="btn btn-outline-success">
                            <i class="fas fa-plus me-2"></i>
                            Enregistrer un paiement
                        </a>
                        <a href="delete.php?id=<?php echo $frais['id']; ?>" class="btn btn-outline-danger" 
                           onclick="return confirm('Êtes-vous sûr de vouloir supprimer ce frais ?')">
                            <i class="fas fa-trash me-2"></i>
                            Supprimer
                        </a>
                    <?php endif; ?>
                    <a href="../reports/fees-analysis.php?frais_id=<?php echo $frais['id']; ?>" class="btn btn-outline-info">
                        <i class="fas fa-chart-bar me-2"></i>
                        Rapport détaillé
                    </a>
                </div>
            </div>
        </div>

        <!-- Informations système -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-info-circle me-2"></i>
                    Informations système
                </h5>
            </div>
            <div class="card-body">
                <table class="table table-borderless table-sm">
                    <tr>
                        <td class="fw-bold">ID :</td>
                        <td><?php echo $frais['id']; ?></td>
                    </tr>
                    <tr>
                        <td class="fw-bold">Créé le :</td>
                        <td><?php echo formatDate($frais['created_at']); ?></td>
                    </tr>
                    <?php if ($frais['updated_at']): ?>
                    <tr>
                        <td class="fw-bold">Modifié le :</td>
                        <td><?php echo formatDate($frais['updated_at']); ?></td>
                    </tr>
                    <?php endif; ?>
                </table>
            </div>
        </div>
    </div>
</div>

<?php include '../../../includes/footer.php'; ?>
