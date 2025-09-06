<?php
/**
 * Visualisation d'une carte d'élève
 * Application de gestion scolaire - République Démocratique du Congo
 */

require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/config/database.php';
require_once dirname(__DIR__, 2) . '/includes/functions.php';
requireLogin();

$carte_id = $_GET['id'] ?? 0;

if (!$carte_id) {
    showMessage('error', 'Carte non trouvée');
    redirectTo('index.php');
}

// Récupérer les informations de la carte
$sql = "SELECT ce.*, e.nom, e.prenom, e.numero_matricule, e.photo, e.date_naissance, e.sexe,
               c.nom as classe_nom, c.niveau,
               a.annee, a.date_debut, a.date_fin
        FROM carte_eleve ce
        LEFT JOIN eleves e ON ce.eleve_id = e.id
        LEFT JOIN classes c ON e.classe_id = c.id
        LEFT JOIN annees_scolaires a ON ce.annee_scolaire_id = a.id
        WHERE ce.id = ?";

$carte = $database->query($sql, [$carte_id])->fetch();

if (!$carte) {
    showMessage('error', 'Carte non trouvée');
    redirectTo('index.php');
}

// Récupérer les paramètres de design
$parametres = $database->query("SELECT * FROM parametres_cartes LIMIT 1")->fetch();
if (!$parametres) {
    // Paramètres par défaut
    $parametres = [
        'nom_ecole' => 'École Sinfinity',
        'couleur_principale' => '#1e40af',
        'couleur_secondaire' => '#3b82f6',
        'couleur_texte' => '#1f2937',
        'format_carte' => 'pdf',
        'dimensions' => '85.6x54mm',
        'qr_code_size' => 100,
        'include_photo' => 1,
        'include_qr_code' => 1,
        'include_barcode' => 0
    ];
}

$page_title = "Carte d'Élève - " . $carte['nom'] . ' ' . $carte['prenom'];
include dirname(__DIR__, 2) . '/includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="page-title-box">
                <div class="page-title-right">
                    <ol class="breadcrumb m-0">
                        <li class="breadcrumb-item"><a href="../dashboard.php">Tableau de bord</a></li>
                        <li class="breadcrumb-item"><a href="index.php">Cartes d'Élèves</a></li>
                        <li class="breadcrumb-item active">Visualisation</li>
                    </ol>
                </div>
                <h4 class="page-title">Carte d'Élève</h4>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Carte d'élève -->
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title">Aperçu de la Carte</h5>
                    <div class="card-tools">
                        <button type="button" class="btn btn-sm btn-primary" onclick="printCard()">
                            <i class="mdi mdi-printer me-1"></i> Imprimer
                        </button>
                        <button type="button" class="btn btn-sm btn-success" onclick="downloadCard()">
                            <i class="mdi mdi-download me-1"></i> Télécharger
                        </button>
                    </div>
                </div>
                <div class="card-body">
                    <div id="cardPreview" class="text-center">
                        <!-- La carte sera générée ici -->
                    </div>
                </div>
            </div>
        </div>

        <!-- Informations détaillées -->
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title">Informations de la Carte</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-sm-4">
                            <h6 class="text-muted">Numéro de carte</h6>
                            <p class="fw-medium"><?= htmlspecialchars($carte['numero_carte']) ?></p>
                        </div>
                        <div class="col-sm-4">
                            <h6 class="text-muted">Statut</h6>
                            <?php
                            $status_class = match($carte['statut']) {
                                'active' => 'success',
                                'expiree' => 'warning',
                                'suspendue' => 'danger',
                                'archivée' => 'secondary',
                                default => 'secondary'
                            };
                            ?>
                            <span class="badge bg-<?= $status_class ?>"><?= ucfirst($carte['statut']) ?></span>
                        </div>
                        <div class="col-sm-4">
                            <h6 class="text-muted">Date de génération</h6>
                            <p><?= date('d/m/Y H:i', strtotime($carte['date_generation'])) ?></p>
                        </div>
                    </div>
                    
                    <hr>
                    
                    <div class="row">
                        <div class="col-sm-6">
                            <h6 class="text-muted">Élève</h6>
                            <p class="fw-medium"><?= htmlspecialchars($carte['nom'] . ' ' . $carte['prenom']) ?></p>
                        </div>
                        <div class="col-sm-6">
                            <h6 class="text-muted">Matricule</h6>
                            <p><?= htmlspecialchars($carte['numero_matricule']) ?></p>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-sm-6">
                            <h6 class="text-muted">Classe</h6>
                            <p><?= htmlspecialchars($carte['classe_nom']) ?></p>
                        </div>
                        <div class="col-sm-6">
                            <h6 class="text-muted">Année scolaire</h6>
                            <p><?= $carte['annee'] ?></p>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-sm-6">
                            <h6 class="text-muted">Date d'expiration</h6>
                            <p><?= date('d/m/Y', strtotime($carte['date_expiration'])) ?></p>
                        </div>
                        <div class="col-sm-6">
                            <h6 class="text-muted">QR Code</h6>
                            <p><small class="text-muted"><?= substr($carte['qr_code'], 0, 50) ?>...</small></p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Actions -->
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title">Actions</h5>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <button type="button" class="btn btn-primary" onclick="printCard()">
                            <i class="mdi mdi-printer me-1"></i> Imprimer la carte
                        </button>
                        <button type="button" class="btn btn-success" onclick="downloadQR()">
                            <i class="mdi mdi-qrcode me-1"></i> Télécharger QR Code
                        </button>
                        <button type="button" class="btn btn-info" onclick="testQR()">
                            <i class="mdi mdi-test-tube me-1"></i> Tester le QR Code
                        </button>
                        <button type="button" class="btn btn-warning" onclick="regenerateCard()">
                            <i class="mdi mdi-refresh me-1"></i> Régénérer la carte
                        </button>
                        <?php if ($carte['statut'] === 'active'): ?>
                        <button type="button" class="btn btn-danger" onclick="suspendCard()">
                            <i class="mdi mdi-pause me-1"></i> Suspendre la carte
                        </button>
                        <?php elseif ($carte['statut'] === 'suspendue'): ?>
                        <button type="button" class="btn btn-success" onclick="activateCard()">
                            <i class="mdi mdi-play me-1"></i> Activer la carte
                        </button>
                        <?php endif; ?>
                        <button type="button" class="btn btn-outline-secondary" onclick="archiveCard()">
                            <i class="mdi mdi-archive me-1"></i> Archiver la carte
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
/* Styles pour la carte d'élève */
.student-card {
    width: 340px;
    height: 214px;
    background: linear-gradient(135deg, <?= $parametres['couleur_principale'] ?>, <?= $parametres['couleur_secondaire'] ?>);
    border-radius: 15px;
    padding: 20px;
    color: white;
    font-family: 'Arial', sans-serif;
    position: relative;
    margin: 0 auto;
    box-shadow: 0 8px 25px rgba(0,0,0,0.15);
}

.student-card .card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 15px;
}

.student-card .school-name {
    font-size: 14px;
    font-weight: bold;
    opacity: 0.9;
}

.student-card .card-type {
    font-size: 12px;
    background: rgba(255,255,255,0.2);
    padding: 4px 8px;
    border-radius: 10px;
}

.student-card .student-info {
    display: flex;
    gap: 15px;
    margin-bottom: 15px;
}

.student-card .student-photo {
    width: 60px;
    height: 60px;
    border-radius: 50%;
    border: 3px solid rgba(255,255,255,0.3);
    object-fit: cover;
}

.student-card .student-details h3 {
    margin: 0;
    font-size: 18px;
    font-weight: bold;
    margin-bottom: 5px;
}

.student-card .student-details p {
    margin: 0;
    font-size: 12px;
    opacity: 0.9;
}

.student-card .card-footer {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-top: 15px;
}

.student-card .matricule {
    font-size: 14px;
    font-weight: bold;
    background: rgba(255,255,255,0.2);
    padding: 5px 10px;
    border-radius: 15px;
}

.student-card .qr-code {
    width: 50px;
    height: 50px;
    background: white;
    border-radius: 5px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #333;
    font-size: 10px;
    text-align: center;
}
</style>

<script>
// Générer l'aperçu de la carte
function generateCardPreview() {
    const cardHtml = `
        <div class="student-card">
            <div class="card-header">
                <div class="school-name"><?= htmlspecialchars($parametres['nom_ecole']) ?></div>
                <div class="card-type">Carte d'Élève</div>
            </div>
            
            <div class="student-info">
                <div>
                    <?php if ($carte['photo'] && $parametres['include_photo']): ?>
                    <img src="../../uploads/photos/<?= htmlspecialchars($carte['photo']) ?>" 
                         class="student-photo" alt="Photo">
                    <?php else: ?>
                    <div class="student-photo bg-light d-flex align-items-center justify-content-center">
                        <i class="mdi mdi-account text-muted"></i>
                    </div>
                    <?php endif; ?>
                </div>
                <div class="student-details">
                    <h3><?= htmlspecialchars($carte['nom'] . ' ' . $carte['prenom']) ?></h3>
                    <p>Classe: <?= htmlspecialchars($carte['classe_nom']) ?></p>
                    <p>Année: <?= $carte['annee'] ?></p>
                </div>
            </div>
            
            <div class="card-footer">
                <div class="matricule"><?= htmlspecialchars($carte['numero_matricule']) ?></div>
                <?php if ($parametres['include_qr_code']): ?>
                <div class="qr-code">
                    <div>
                        <i class="mdi mdi-qrcode"></i><br>
                        <small>QR</small>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    `;
    
    document.getElementById('cardPreview').innerHTML = cardHtml;
}

// Actions
function printCard() {
    window.open(`print.php?id=<?= $carte_id ?>`, '_blank');
}

function downloadCard() {
    window.open(`download.php?id=<?= $carte_id ?>`, '_blank');
}

function downloadQR() {
    window.open(`download-qr.php?id=<?= $carte_id ?>`, '_blank');
}

function testQR() {
    // Simuler le test du QR code
    const qrData = <?= json_encode($carte['qr_data']) ?>;
    alert('Données du QR Code:\n' + JSON.stringify(JSON.parse(qrData), null, 2));
}

function regenerateCard() {
    if (confirm('Êtes-vous sûr de vouloir régénérer cette carte ?')) {
        fetch('actions.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `action=regenerate&carte_id=<?= $carte_id ?>`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Carte régénérée avec succès');
                location.reload();
            } else {
                alert('Erreur: ' + data.message);
            }
        });
    }
}

function suspendCard() {
    if (confirm('Êtes-vous sûr de vouloir suspendre cette carte ?')) {
        fetch('actions.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `action=suspend&carte_id=<?= $carte_id ?>`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Carte suspendue avec succès');
                location.reload();
            } else {
                alert('Erreur: ' + data.message);
            }
        });
    }
}

function activateCard() {
    if (confirm('Êtes-vous sûr de vouloir activer cette carte ?')) {
        fetch('actions.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `action=activate&carte_id=<?= $carte_id ?>`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Carte activée avec succès');
                location.reload();
            } else {
                alert('Erreur: ' + data.message);
            }
        });
    }
}

function archiveCard() {
    if (confirm('Êtes-vous sûr de vouloir archiver cette carte ?')) {
        fetch('actions.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `action=archive&carte_id=<?= $carte_id ?>`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Carte archivée avec succès');
                location.href = 'index.php';
            } else {
                alert('Erreur: ' + data.message);
            }
        });
    }
}

// Initialiser l'aperçu de la carte
document.addEventListener('DOMContentLoaded', function() {
    generateCardPreview();
});
</script>

<?php include dirname(__DIR__, 2) . '/includes/footer.php'; ?>
