<?php
/**
 * Page de génération de cartes d'élèves
 * Application de gestion scolaire - République Démocratique du Congo
 */

require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/config/database.php';
require_once dirname(__DIR__, 2) . '/includes/functions.php';
require_once dirname(__DIR__, 2) . '/includes/permissions-pages.php';
require_once __DIR__ . '/qr-generator.php';
require_once __DIR__ . '/auto-generate.php';

// Vérifier l'authentification
if (!isLoggedIn()) {
    redirectTo('auth/login.php');
}

requirePagePermissionFromDB('cartes_eleves', 'cartes_eleves/generate', 'create', '../dashboard.php');

$page_title = 'Génération de Cartes d\'Élèves';
$success_message = '';
$error_message = '';

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $action = $_POST['action'] ?? '';
        
        if ($action === 'generate_single') {
            // Génération d'une carte individuelle
            $eleve_id = intval($_POST['eleve_id']);
            $annee_scolaire_id = intval($_POST['annee_scolaire_id']);
            
            if ($eleve_id && $annee_scolaire_id) {
                $result = autoGenerateStudentCard($eleve_id, $annee_scolaire_id);
                if ($result) {
                    $success_message = "Carte générée avec succès (ID: $result)";
                } else {
                    $error_message = "Erreur lors de la génération de la carte";
                }
            } else {
                $error_message = "Veuillez sélectionner un élève et une année scolaire";
            }
            
        } elseif ($action === 'generate_class') {
            // Génération pour une classe entière
            $classe_id = intval($_POST['classe_id']);
            $annee_scolaire_id = intval($_POST['annee_scolaire_id']);
            
            if ($classe_id && $annee_scolaire_id) {
                $students = $database->query("
                    SELECT e.id, e.nom, e.prenom, e.numero_matricule
                    FROM eleves e
                    JOIN inscriptions i ON e.id = i.eleve_id
                    WHERE e.classe_id = ? AND i.annee_scolaire_id = ? AND i.status = 'inscrit'
                    ORDER BY e.nom, e.prenom
                ", [$classe_id, $annee_scolaire_id])->fetchAll();
                
                $generated = 0;
                $errors = 0;
                
                foreach ($students as $student) {
                    $result = autoGenerateStudentCard($student['id'], $annee_scolaire_id);
                    if ($result) {
                        $generated++;
                    } else {
                        $errors++;
                    }
                }
                
                if ($generated > 0) {
                    $success_message = "$generated carte(s) générée(s) avec succès";
                    if ($errors > 0) {
                        $success_message .= " ($errors erreur(s))";
                    }
                } else {
                    $error_message = "Aucune carte générée";
                }
            } else {
                $error_message = "Veuillez sélectionner une classe et une année scolaire";
            }
            
        } elseif ($action === 'regenerate_qr') {
            // Régénération des QR codes
            $carte_id = intval($_POST['carte_id']);
            
            if ($carte_id) {
                $carte = $database->query("
                    SELECT ce.*, e.numero_matricule 
                    FROM carte_eleve ce
                    LEFT JOIN eleves e ON ce.eleve_id = e.id
                    WHERE ce.id = ?
                ", [$carte_id])->fetch();
                
                if ($carte) {
                    $qrGenerator = new QRCodeGenerator($database);
                    
                    // Supprimer l'ancien fichier QR s'il existe
                    if (!empty($carte['qr_code_path']) && file_exists($carte['qr_code_path'])) {
                        $qrGenerator->deleteQRCode($carte['qr_code_path']);
                    }
                    
                    // Régénérer le QR code
                    $result = $qrGenerator->generateQRCode(
                        $carte['eleve_id'],
                        $carte['numero_matricule'],
                        $carte['annee_scolaire']
                    );
                    
                    if ($result['success']) {
                        // Mettre à jour la base de données
                        $database->execute(
                            "UPDATE carte_eleve SET qr_code_path = ? WHERE id = ?",
                            [$qrGenerator->getRelativePath($result['filepath']), $carte_id]
                        );
                        
                        $success_message = "QR code régénéré avec succès";
                    } else {
                        $error_message = "Erreur lors de la régénération du QR code: " . $result['error'];
                    }
                } else {
                    $error_message = "Carte non trouvée";
                }
            } else {
                $error_message = "ID de carte invalide";
            }
        }
        
    } catch (Exception $e) {
        $error_message = "Erreur: " . $e->getMessage();
    }
}

// Récupérer les données pour les formulaires
$current_year = $database->query("SELECT * FROM annees_scolaires WHERE status = 'active' ORDER BY id DESC LIMIT 1")->fetch();

$classes = $database->query("
    SELECT DISTINCT c.id, c.nom, c.niveau 
    FROM classes c
    JOIN eleves e ON c.id = e.classe_id
    JOIN inscriptions i ON e.id = i.eleve_id
    WHERE i.status = 'inscrit' AND i.annee_scolaire_id = ?
    ORDER BY c.niveau, c.nom
", [$current_year['id']])->fetchAll();

$eleves = $database->query("
    SELECT e.id, e.nom, e.prenom, e.numero_matricule, c.nom as classe_nom
    FROM eleves e
    LEFT JOIN classes c ON e.classe_id = c.id
    JOIN inscriptions i ON e.id = i.eleve_id
    WHERE i.status = 'inscrit' AND i.annee_scolaire_id = ?
    ORDER BY e.nom, e.prenom
    LIMIT 50
", [$current_year['id']])->fetchAll();

$cartes_recentes = $database->query("
    SELECT ce.*, e.nom, e.prenom, e.numero_matricule, c.nom as classe_nom
    FROM carte_eleve ce
    LEFT JOIN eleves e ON ce.eleve_id = e.id
    LEFT JOIN classes c ON e.classe_id = c.id
    ORDER BY ce.date_generation DESC
    LIMIT 10
")->fetchAll();

include dirname(__DIR__, 2) . '/includes/header.php';
?>

<div class="container-fluid">
    <!-- En-tête de page -->
    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
        <h1 class="h2">
            <i class="fas fa-id-card text-primary"></i>
            Génération de Cartes d'Élèves
        </h1>
        <div class="btn-toolbar mb-2 mb-md-0">
            <a href="index.php" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left"></i> Retour
            </a>
        </div>
    </div>

    <!-- Messages -->
    <?php if ($success_message): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle"></i>
            <?php echo htmlspecialchars($success_message); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if ($error_message): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-circle"></i>
            <?php echo htmlspecialchars($error_message); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="row">
        <!-- Génération individuelle -->
        <div class="col-md-6 mb-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-user-plus text-primary"></i>
                        Génération Individuelle
                    </h5>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="action" value="generate_single">
                        
                        <div class="mb-3">
                            <label for="eleve_id" class="form-label">Élève</label>
                            <select class="form-select" id="eleve_id" name="eleve_id" required>
                                <option value="">Sélectionner un élève</option>
                                <?php foreach ($eleves as $eleve): ?>
                                    <option value="<?php echo $eleve['id']; ?>">
                                        <?php echo htmlspecialchars($eleve['nom'] . ' ' . $eleve['prenom'] . ' (' . $eleve['numero_matricule'] . ') - ' . $eleve['classe_nom']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="annee_scolaire_id" class="form-label">Année Scolaire</label>
                            <select class="form-select" id="annee_scolaire_id" name="annee_scolaire_id" required>
                                <option value="<?php echo $current_year['id']; ?>">
                                    <?php echo htmlspecialchars($current_year['annee']); ?>
                                </option>
                            </select>
                        </div>
                        
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-id-card"></i>
                            Générer la Carte
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Génération par classe -->
        <div class="col-md-6 mb-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-users text-success"></i>
                        Génération par Classe
                    </h5>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="action" value="generate_class">
                        
                        <div class="mb-3">
                            <label for="classe_id" class="form-label">Classe</label>
                            <select class="form-select" id="classe_id" name="classe_id" required>
                                <option value="">Sélectionner une classe</option>
                                <?php foreach ($classes as $classe): ?>
                                    <option value="<?php echo $classe['id']; ?>">
                                        <?php echo htmlspecialchars($classe['niveau'] . ' - ' . $classe['nom']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="annee_scolaire_id_class" class="form-label">Année Scolaire</label>
                            <select class="form-select" id="annee_scolaire_id_class" name="annee_scolaire_id" required>
                                <option value="<?php echo $current_year['id']; ?>">
                                    <?php echo htmlspecialchars($current_year['annee']); ?>
                                </option>
                            </select>
                        </div>
                        
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-users"></i>
                            Générer pour la Classe
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Cartes récentes -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-history text-info"></i>
                        Cartes Récentes
                    </h5>
                </div>
                <div class="card-body">
                    <?php if (empty($cartes_recentes)): ?>
                        <div class="text-center text-muted py-4">
                            <i class="fas fa-inbox fa-3x mb-3"></i>
                            <p>Aucune carte générée récemment</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Élève</th>
                                        <th>Matricule</th>
                                        <th>Classe</th>
                                        <th>N° Carte</th>
                                        <th>Année</th>
                                        <th>QR Code</th>
                                        <th>Date</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($cartes_recentes as $carte): ?>
                                        <tr>
                                            <td>
                                                <strong><?php echo htmlspecialchars($carte['nom'] . ' ' . $carte['prenom']); ?></strong>
                                            </td>
                                            <td>
                                                <span class="badge bg-secondary"><?php echo htmlspecialchars($carte['numero_matricule']); ?></span>
                                            </td>
                                            <td><?php echo htmlspecialchars($carte['classe_nom']); ?></td>
                                            <td>
                                                <span class="badge bg-primary"><?php echo htmlspecialchars($carte['numero_carte']); ?></span>
                                            </td>
                                            <td><?php echo htmlspecialchars($carte['annee_scolaire']); ?></td>
                                            <td>
                                                <?php if (!empty($carte['qr_code_path']) && file_exists($carte['qr_code_path'])): ?>
                                                    <span class="badge bg-success">
                                                        <i class="fas fa-check"></i> PNG
                                                    </span>
                                                <?php else: ?>
                                                    <span class="badge bg-warning">
                                                        <i class="fas fa-exclamation-triangle"></i> Manquant
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <small class="text-muted">
                                                    <?php echo date('d/m/Y H:i', strtotime($carte['date_generation'])); ?>
                                                </small>
                                            </td>
                                            <td>
                                                <div class="btn-group btn-group-sm">
                                                    <a href="view.php?id=<?php echo $carte['id']; ?>" class="btn btn-outline-info" title="Voir">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                    <a href="print.php?id=<?php echo $carte['id']; ?>" class="btn btn-outline-primary" title="Imprimer" target="_blank">
                                                        <i class="fas fa-print"></i>
                                                    </a>
                                                    <form method="POST" class="d-inline" onsubmit="return confirm('Régénérer le QR code de cette carte ?')">
                                                        <input type="hidden" name="action" value="regenerate_qr">
                                                        <input type="hidden" name="carte_id" value="<?php echo $carte['id']; ?>">
                                                        <button type="submit" class="btn btn-outline-warning" title="Régénérer QR">
                                                            <i class="fas fa-sync"></i>
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
        </div>
    </div>
</div>

<script>
// Auto-submit pour la génération par classe
document.getElementById('classe_id').addEventListener('change', function() {
    if (this.value) {
        // Afficher un message de confirmation
        if (confirm('Générer les cartes pour tous les élèves de cette classe ?')) {
            this.form.submit();
        }
    }
});
</script>

<?php include dirname(__DIR__, 2) . '/includes/footer.php'; ?>
