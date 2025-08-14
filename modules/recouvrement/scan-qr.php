<?php
/**
 * Module Recouvrement - Scanner QR Code
 * Application de gestion scolaire - République Démocratique du Congo
 */

require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../includes/functions.php';

// Vérifier l'authentification et les permissions
requireLogin();
if (!checkPermission('recouvrement') && !checkPermission('admin')) {
    showMessage('error', 'Accès refusé à cette page.');
    redirectTo('../../index.php');
}

$errors = [];
$success_message = '';
$scan_result = null;

// Traitement du scan QR
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    try {
        if ($action === 'scan_qr') {
            $qr_data = trim($_POST['qr_data'] ?? '');
            $type_scan = $_POST['type_scan'] ?? 'entree';
            $lieu_scan = trim($_POST['lieu_scan'] ?? 'Entrée principale');
            
            if (empty($qr_data)) {
                throw new Exception('Données QR code manquantes.');
            }
            
            // Décoder les données QR (format JSON attendu)
            $qr_info = json_decode($qr_data, true);
            if (!$qr_info || !isset($qr_info['eleve_id']) || !isset($qr_info['carte_id'])) {
                throw new Exception('Format QR code invalide.');
            }
            
            $eleve_id = intval($qr_info['eleve_id']);
            $carte_id = intval($qr_info['carte_id']);
            
            // Vérifier que la carte existe et est active
            $carte = $database->query(
                "SELECT c.*, e.nom, e.prenom, e.numero_matricule, cl.nom as classe_nom
                 FROM cartes_eleves c
                 JOIN eleves e ON c.eleve_id = e.id
                 LEFT JOIN inscriptions i ON e.id = i.eleve_id AND i.status = 'inscrit'
                 LEFT JOIN classes cl ON i.classe_id = cl.id
                 WHERE c.id = ? AND c.eleve_id = ?",
                [$carte_id, $eleve_id]
            )->fetch();
            
            if (!$carte) {
                throw new Exception('Carte non trouvée ou invalide.');
            }
            
            if ($carte['status'] !== 'active') {
                throw new Exception('Carte inactive. Statut: ' . ucfirst($carte['status']));
            }
            
            // Vérifier la solvabilité de l'élève
            $solvabilite = $database->query(
                "SELECT s.*, a.nom as annee_nom
                 FROM solvabilite_eleves s
                 JOIN annees_scolaires a ON s.annee_scolaire_id = a.id
                 WHERE s.eleve_id = ? AND a.status = 'active'",
                [$eleve_id]
            )->fetch();
            
            // Enregistrer la présence
            $database->execute(
                "INSERT INTO presences_qr (
                    eleve_id, carte_id, date_presence, type_scan, lieu_scan, scanne_par,
                    heure_entree, heure_sortie, created_at
                ) VALUES (?, ?, CURDATE(), ?, ?, ?, ?, ?, NOW())",
                [
                    $eleve_id, $carte_id, $type_scan, $lieu_scan, $_SESSION['user_id'],
                    ($type_scan === 'entree') ? date('H:i:s') : null,
                    ($type_scan === 'sortie') ? date('H:i:s') : null
                ]
            );
            
            // Préparer les résultats du scan
            $scan_result = [
                'success' => true,
                'eleve' => $carte,
                'solvabilite' => $solvabilite,
                'type_scan' => $type_scan,
                'heure_scan' => date('H:i:s'),
                'lieu_scan' => $lieu_scan
            ];
            
            $success_message = 'Scan effectué avec succès pour ' . $carte['nom'] . ' ' . $carte['prenom'];
            
        } elseif ($action === 'manual_search') {
            $search_term = trim($_POST['search_term'] ?? '');
            
            if (empty($search_term)) {
                throw new Exception('Terme de recherche manquant.');
            }
            
            // Rechercher l'élève par matricule ou nom
            $eleve = $database->query(
                "SELECT e.*, c.id as carte_id, c.numero_carte, c.status as carte_status,
                        cl.nom as classe_nom
                 FROM eleves e
                 LEFT JOIN cartes_eleves c ON e.id = c.eleve_id AND c.status = 'active'
                 LEFT JOIN inscriptions i ON e.id = i.eleve_id AND i.status = 'inscrit'
                 LEFT JOIN classes cl ON i.classe_id = cl.id
                 WHERE e.numero_matricule = ? OR CONCAT(e.nom, ' ', e.prenom) LIKE ?
                 LIMIT 1",
                [$search_term, "%$search_term%"]
            )->fetch();
            
            if (!$eleve) {
                throw new Exception('Élève non trouvé.');
            }
            
            if (!$eleve['carte_id']) {
                throw new Exception('Aucune carte active trouvée pour cet élève.');
            }
            
            // Vérifier la solvabilité
            $solvabilite = $database->query(
                "SELECT s.*, a.nom as annee_nom
                 FROM solvabilite_eleves s
                 JOIN annees_scolaires a ON s.annee_scolaire_id = a.id
                 WHERE s.eleve_id = ? AND a.status = 'active'",
                [$eleve['id']]
            )->fetch();
            
            $scan_result = [
                'success' => true,
                'eleve' => $eleve,
                'solvabilite' => $solvabilite,
                'type_scan' => 'manual',
                'manual_search' => true
            ];
        }
        
    } catch (Exception $e) {
        $errors[] = $e->getMessage();
        $scan_result = ['success' => false, 'error' => $e->getMessage()];
    }
}

$page_title = "Scanner QR Code";
include '../../includes/header.php';
?>

<!-- Inclure la bibliothèque QR Scanner -->
<script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">
        <i class="fas fa-qrcode me-2 text-primary"></i>
        Scanner QR Code
    </h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <div class="btn-group me-2">
            <a href="index.php" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-1"></i>
                Retour
            </a>
        </div>
        <div class="btn-group">
            <a href="presences/" class="btn btn-info">
                <i class="fas fa-history me-1"></i>
                Historique
            </a>
            <a href="rapports/" class="btn btn-warning">
                <i class="fas fa-chart-line me-1"></i>
                Rapports
            </a>
        </div>
    </div>
</div>

<?php if (!empty($errors)): ?>
    <div class="alert alert-danger">
        <h6><i class="fas fa-exclamation-circle me-1"></i> Erreurs :</h6>
        <ul class="mb-0">
            <?php foreach ($errors as $error): ?>
                <li><?php echo htmlspecialchars($error); ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<?php if (!empty($success_message)): ?>
    <div class="alert alert-success">
        <i class="fas fa-check-circle me-2"></i>
        <?php echo htmlspecialchars($success_message); ?>
    </div>
<?php endif; ?>

<div class="row">
    <!-- Scanner QR -->
    <div class="col-lg-8">
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0">
                    <i class="fas fa-camera me-2"></i>
                    Scanner de Carte QR
                </h5>
            </div>
            <div class="card-body">
                <!-- Contrôles du scanner -->
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label for="type_scan" class="form-label">Type de scan</label>
                        <select class="form-select" id="type_scan" name="type_scan">
                            <option value="entree">Entrée</option>
                            <option value="sortie">Sortie</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label for="lieu_scan" class="form-label">Lieu</label>
                        <input type="text" class="form-control" id="lieu_scan" name="lieu_scan" 
                               value="Entrée principale" placeholder="Lieu du scan">
                    </div>
                </div>
                
                <!-- Zone de scan -->
                <div class="text-center mb-3">
                    <div id="qr-reader" style="width: 100%; max-width: 500px; margin: 0 auto;"></div>
                    <div id="qr-reader-results" class="mt-3"></div>
                </div>
                
                <!-- Contrôles du scanner -->
                <div class="text-center">
                    <button id="start-scan" class="btn btn-success me-2">
                        <i class="fas fa-play me-1"></i>
                        Démarrer Scanner
                    </button>
                    <button id="stop-scan" class="btn btn-danger me-2" style="display: none;">
                        <i class="fas fa-stop me-1"></i>
                        Arrêter Scanner
                    </button>
                    <button type="button" class="btn btn-info" data-bs-toggle="modal" data-bs-target="#manualSearchModal">
                        <i class="fas fa-search me-1"></i>
                        Recherche Manuelle
                    </button>
                </div>
                
                <!-- Formulaire caché pour soumettre les données -->
                <form id="scanForm" method="POST" style="display: none;">
                    <input type="hidden" name="action" value="scan_qr">
                    <input type="hidden" name="qr_data" id="qr_data">
                    <input type="hidden" name="type_scan" id="form_type_scan">
                    <input type="hidden" name="lieu_scan" id="form_lieu_scan">
                </form>
            </div>
        </div>
        
        <!-- Instructions -->
        <div class="card shadow-sm">
            <div class="card-header bg-light">
                <h6 class="mb-0">
                    <i class="fas fa-info-circle me-2"></i>
                    Instructions d'utilisation
                </h6>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <h6>Comment scanner :</h6>
                        <ol class="small">
                            <li>Cliquez sur "Démarrer Scanner"</li>
                            <li>Autorisez l'accès à la caméra</li>
                            <li>Placez la carte QR devant la caméra</li>
                            <li>Le scan se fait automatiquement</li>
                        </ol>
                    </div>
                    <div class="col-md-6">
                        <h6>Informations affichées :</h6>
                        <ul class="small">
                            <li><strong>Identité :</strong> Nom, prénom, matricule</li>
                            <li><strong>Classe :</strong> Classe actuelle</li>
                            <li><strong>Solvabilité :</strong> État des paiements</li>
                            <li><strong>Présence :</strong> Entrée/sortie enregistrée</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Résultats du scan -->
    <div class="col-lg-4">
        <?php if ($scan_result && $scan_result['success']): ?>
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-success text-white">
                    <h6 class="mb-0">
                        <i class="fas fa-check-circle me-2"></i>
                        Résultat du Scan
                    </h6>
                </div>
                <div class="card-body">
                    <!-- Informations de l'élève -->
                    <div class="text-center mb-3">
                        <div class="bg-light rounded-circle d-inline-flex align-items-center justify-content-center" 
                             style="width: 80px; height: 80px;">
                            <i class="fas fa-user fa-2x text-muted"></i>
                        </div>
                        <h5 class="mt-2 mb-1">
                            <?php echo htmlspecialchars($scan_result['eleve']['nom'] . ' ' . $scan_result['eleve']['prenom']); ?>
                        </h5>
                        <p class="text-muted mb-0">
                            <i class="fas fa-id-card me-1"></i>
                            <?php echo htmlspecialchars($scan_result['eleve']['numero_matricule']); ?>
                        </p>
                        <?php if ($scan_result['eleve']['classe_nom']): ?>
                            <p class="text-muted">
                                <i class="fas fa-graduation-cap me-1"></i>
                                <?php echo htmlspecialchars($scan_result['eleve']['classe_nom']); ?>
                            </p>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Type de scan -->
                    <?php if (!isset($scan_result['manual_search'])): ?>
                        <div class="alert alert-<?php echo ($scan_result['type_scan'] === 'entree') ? 'success' : 'warning'; ?> text-center">
                            <i class="fas fa-<?php echo ($scan_result['type_scan'] === 'entree') ? 'sign-in-alt' : 'sign-out-alt'; ?> me-2"></i>
                            <strong><?php echo ucfirst($scan_result['type_scan']); ?></strong>
                            <br><small><?php echo $scan_result['heure_scan'] ?? ''; ?> - <?php echo htmlspecialchars($scan_result['lieu_scan'] ?? ''); ?></small>
                        </div>
                    <?php endif; ?>
                    
                    <!-- État de solvabilité -->
                    <?php if ($scan_result['solvabilite']): ?>
                        <div class="mb-3">
                            <h6>État de Solvabilité</h6>
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span>Statut :</span>
                                <span class="badge bg-<?php 
                                    echo match($scan_result['solvabilite']['status_solvabilite']) {
                                        'solvable' => 'success',
                                        'partiellement_solvable' => 'warning',
                                        'non_solvable' => 'danger',
                                        default => 'secondary'
                                    };
                                ?>">
                                    <?php 
                                    echo match($scan_result['solvabilite']['status_solvabilite']) {
                                        'solvable' => 'Solvable',
                                        'partiellement_solvable' => 'Partiellement solvable',
                                        'non_solvable' => 'Non solvable',
                                        default => 'Inconnu'
                                    };
                                    ?>
                                </span>
                            </div>
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span>Total payé :</span>
                                <span class="text-success">
                                    <?php echo number_format($scan_result['solvabilite']['total_paye'], 0, ',', ' '); ?> FC
                                </span>
                            </div>
                            <div class="d-flex justify-content-between align-items-center">
                                <span>Solde restant :</span>
                                <span class="text-danger">
                                    <?php echo number_format($scan_result['solvabilite']['solde_restant'], 0, ',', ' '); ?> FC
                                </span>
                            </div>
                            
                            <!-- Barre de progression -->
                            <div class="mt-2">
                                <div class="progress" style="height: 8px;">
                                    <div class="progress-bar bg-success" role="progressbar" 
                                         style="width: <?php echo $scan_result['solvabilite']['pourcentage_paye']; ?>%">
                                    </div>
                                </div>
                                <small class="text-muted">
                                    <?php echo number_format($scan_result['solvabilite']['pourcentage_paye'], 1); ?>% payé
                                </small>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            Aucune information de solvabilité disponible.
                        </div>
                    <?php endif; ?>
                    
                    <!-- Actions -->
                    <?php if (isset($scan_result['manual_search'])): ?>
                        <div class="d-grid gap-2">
                            <button type="button" class="btn btn-success" onclick="recordManualPresence(<?php echo $scan_result['eleve']['id']; ?>, <?php echo $scan_result['eleve']['carte_id']; ?>)">
                                <i class="fas fa-user-check me-1"></i>
                                Marquer Présent
                            </button>
                            <a href="solvabilite/view.php?id=<?php echo $scan_result['eleve']['id']; ?>" class="btn btn-outline-info">
                                <i class="fas fa-chart-pie me-1"></i>
                                Voir Solvabilité
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php else: ?>
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-light">
                    <h6 class="mb-0">
                        <i class="fas fa-qrcode me-2"></i>
                        En attente de scan
                    </h6>
                </div>
                <div class="card-body text-center">
                    <i class="fas fa-qrcode fa-3x text-muted mb-3"></i>
                    <p class="text-muted">Scannez une carte QR pour voir les informations de l'élève</p>
                </div>
            </div>
        <?php endif; ?>
        
        <!-- Statistiques du jour -->
        <div class="card shadow-sm">
            <div class="card-header bg-light">
                <h6 class="mb-0">
                    <i class="fas fa-chart-bar me-2"></i>
                    Statistiques du Jour
                </h6>
            </div>
            <div class="card-body">
                <?php
                try {
                    $stats_jour = $database->query("
                        SELECT 
                            COUNT(DISTINCT eleve_id) as presents_total,
                            COUNT(CASE WHEN type_scan = 'entree' THEN 1 END) as entrees,
                            COUNT(CASE WHEN type_scan = 'sortie' THEN 1 END) as sorties
                        FROM presences_qr 
                        WHERE DATE(created_at) = CURDATE()
                    ")->fetch();
                ?>
                    <div class="row text-center">
                        <div class="col-4">
                            <h4 class="text-primary mb-1"><?php echo number_format($stats_jour['presents_total'] ?? 0); ?></h4>
                            <small class="text-muted">Présents</small>
                        </div>
                        <div class="col-4">
                            <h4 class="text-success mb-1"><?php echo number_format($stats_jour['entrees'] ?? 0); ?></h4>
                            <small class="text-muted">Entrées</small>
                        </div>
                        <div class="col-4">
                            <h4 class="text-warning mb-1"><?php echo number_format($stats_jour['sorties'] ?? 0); ?></h4>
                            <small class="text-muted">Sorties</small>
                        </div>
                    </div>
                <?php
                } catch (Exception $e) {
                    echo '<div class="alert alert-warning small">Erreur lors du chargement des statistiques.</div>';
                }
                ?>
            </div>
        </div>
    </div>
</div>

<!-- Modal de recherche manuelle -->
<div class="modal fade" id="manualSearchModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-search me-2"></i>
                    Recherche Manuelle
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="manual_search">
                    <div class="mb-3">
                        <label for="search_term" class="form-label">Rechercher un élève</label>
                        <input type="text" class="form-control" id="search_term" name="search_term"
                               placeholder="Numéro matricule ou nom complet" required>
                        <div class="form-text">
                            Tapez le numéro matricule ou le nom complet de l'élève
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search me-1"></i>
                        Rechercher
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
#qr-reader {
    border: 2px dashed #dee2e6;
    border-radius: 8px;
    padding: 20px;
    background-color: #f8f9fa;
}

#qr-reader video {
    border-radius: 8px;
}

.progress {
    border-radius: 10px;
}
</style>

<script>
let html5QrcodeScanner = null;
let isScanning = false;

// Initialiser le scanner QR
function initQRScanner() {
    const qrCodeSuccessCallback = (decodedText, decodedResult) => {
        // Arrêter le scanner
        stopQRScanner();

        // Remplir le formulaire avec les données scannées
        document.getElementById('qr_data').value = decodedText;
        document.getElementById('form_type_scan').value = document.getElementById('type_scan').value;
        document.getElementById('form_lieu_scan').value = document.getElementById('lieu_scan').value;

        // Soumettre le formulaire
        document.getElementById('scanForm').submit();
    };

    const qrCodeErrorCallback = (errorMessage) => {
        // Ignorer les erreurs de scan (trop fréquentes)
    };

    const config = {
        fps: 10,
        qrbox: { width: 250, height: 250 },
        aspectRatio: 1.0
    };

    html5QrcodeScanner = new Html5Qrcode("qr-reader");

    Html5Qrcode.getCameras().then(devices => {
        if (devices && devices.length) {
            const cameraId = devices[0].id;
            html5QrcodeScanner.start(
                cameraId,
                config,
                qrCodeSuccessCallback,
                qrCodeErrorCallback
            ).then(() => {
                isScanning = true;
                document.getElementById('start-scan').style.display = 'none';
                document.getElementById('stop-scan').style.display = 'inline-block';
            }).catch(err => {
                console.error('Erreur lors du démarrage du scanner:', err);
                alert('Erreur lors du démarrage du scanner. Vérifiez les permissions de la caméra.');
            });
        } else {
            alert('Aucune caméra trouvée sur cet appareil.');
        }
    }).catch(err => {
        console.error('Erreur lors de la détection des caméras:', err);
        alert('Erreur lors de la détection des caméras.');
    });
}

// Arrêter le scanner QR
function stopQRScanner() {
    if (html5QrcodeScanner && isScanning) {
        html5QrcodeScanner.stop().then(() => {
            isScanning = false;
            document.getElementById('start-scan').style.display = 'inline-block';
            document.getElementById('stop-scan').style.display = 'none';
        }).catch(err => {
            console.error('Erreur lors de l\'arrêt du scanner:', err);
        });
    }
}

// Enregistrer une présence manuelle
function recordManualPresence(eleveId, carteId) {
    const typeScan = document.getElementById('type_scan').value;
    const lieuScan = document.getElementById('lieu_scan').value;

    // Créer les données QR simulées
    const qrData = JSON.stringify({
        eleve_id: eleveId,
        carte_id: carteId
    });

    // Remplir et soumettre le formulaire
    document.getElementById('qr_data').value = qrData;
    document.getElementById('form_type_scan').value = typeScan;
    document.getElementById('form_lieu_scan').value = lieuScan;
    document.getElementById('scanForm').submit();
}

// Event listeners
document.getElementById('start-scan').addEventListener('click', initQRScanner);
document.getElementById('stop-scan').addEventListener('click', stopQRScanner);

// Auto-focus sur le champ de recherche quand la modal s'ouvre
document.getElementById('manualSearchModal').addEventListener('shown.bs.modal', function () {
    document.getElementById('search_term').focus();
});

// Nettoyage lors de la fermeture de la page
window.addEventListener('beforeunload', function() {
    if (isScanning) {
        stopQRScanner();
    }
});
</script>

<?php include '../../includes/footer.php'; ?>
