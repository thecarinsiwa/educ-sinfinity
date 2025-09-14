<?php
/**
 * Scanner QR Code pour les cartes d'élèves
 * Application de gestion scolaire - République Démocratique du Congo
 */

require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/config/database.php';
require_once dirname(__DIR__, 2) . '/includes/functions.php';
require_once dirname(__DIR__, 2) . '/includes/permissions-pages.php';
requireLogin();

requirePagePermissionFromDB('cartes_eleves', 'cartes_eleves/qr-scanner', 'read', '../dashboard.php');

$page_title = "Scanner QR Code";
$current_module = 'cartes_eleves';

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
                        <li class="breadcrumb-item active">Scanner QR</li>
                    </ol>
                </div>
                <h4 class="page-title">Scanner QR Code</h4>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Scanner -->
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title">Scanner de QR Code</h5>
                    <div class="card-tools">
                        <button type="button" class="btn btn-sm btn-primary" onclick="startScanner()">
                            <i class="mdi mdi-camera me-1"></i> Démarrer le scanner
                        </button>
                        <button type="button" class="btn btn-sm btn-secondary" onclick="stopScanner()">
                            <i class="mdi mdi-camera-off me-1"></i> Arrêter
                        </button>
                    </div>
                </div>
                <div class="card-body">
                    <div id="scanner-container" class="text-center">
                        <div id="scanner" style="width: 100%; max-width: 500px; margin: 0 auto;">
                            <video id="video" width="100%" height="300" style="display: none;"></video>
                            <canvas id="canvas" width="500" height="300" style="display: none;"></canvas>
                        </div>
                        <div id="scanner-placeholder" class="border rounded p-5">
                            <i class="mdi mdi-qrcode-scan text-muted" style="font-size: 48px;"></i>
                            <p class="text-muted mt-2">Cliquez sur "Démarrer le scanner" pour commencer</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Résultat du scan -->
            <div class="card" id="scan-result" style="display: none;">
                <div class="card-header">
                    <h5 class="card-title">Résultat du scan</h5>
                </div>
                <div class="card-body" id="scan-result-content">
                    <!-- Le contenu sera rempli dynamiquement -->
                </div>
            </div>
        </div>

        <!-- Actions rapides -->
        <div class="col-lg-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title">Actions rapides</h5>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <button type="button" class="btn btn-success" onclick="markAttendance()">
                            <i class="mdi mdi-check-circle me-1"></i> Marquer la présence
                        </button>
                        <button type="button" class="btn btn-info" onclick="checkBalance()">
                            <i class="mdi mdi-currency-usd me-1"></i> Vérifier le solde
                        </button>
                        <button type="button" class="btn btn-warning" onclick="viewStudentInfo()">
                            <i class="mdi mdi-account me-1"></i> Informations élève
                        </button>
                        <button type="button" class="btn btn-primary" onclick="printCard()">
                            <i class="mdi mdi-printer me-1"></i> Imprimer la carte
                        </button>
                    </div>
                </div>
            </div>

            <!-- Historique des scans -->
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title">Historique des scans</h5>
                </div>
                <div class="card-body">
                    <div id="scan-history" style="max-height: 300px; overflow-y: auto;">
                        <!-- L'historique sera chargé ici -->
                    </div>
                </div>
            </div>

            <!-- Statistiques -->
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title">Statistiques</h5>
                </div>
                <div class="card-body">
                    <div class="row text-center">
                        <div class="col-6">
                            <h4 class="text-success" id="today-scans">0</h4>
                            <small class="text-muted">Scans aujourd'hui</small>
                        </div>
                        <div class="col-6">
                            <h4 class="text-info" id="total-scans">0</h4>
                            <small class="text-muted">Total scans</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal pour les détails de l'élève -->
<div class="modal fade" id="studentModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Informations de l'élève</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="studentModalContent">
                <!-- Le contenu sera rempli dynamiquement -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fermer</button>
                <button type="button" class="btn btn-primary" onclick="performAction()">Effectuer l'action</button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/jsqr@1.4.0/dist/jsQR.js"></script>
<script>
let scanner = null;
let currentStudent = null;
let currentAction = null;

// Démarrer le scanner
function startScanner() {
    const video = document.getElementById('video');
    const canvas = document.getElementById('canvas');
    const ctx = canvas.getContext('2d');
    const placeholder = document.getElementById('scanner-placeholder');
    
    placeholder.style.display = 'none';
    video.style.display = 'block';
    
    navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' } })
        .then(stream => {
            video.srcObject = stream;
            video.play();
            
            scanner = setInterval(() => {
                if (video.readyState === video.HAVE_ENOUGH_DATA) {
                    canvas.width = video.videoWidth;
                    canvas.height = video.videoHeight;
                    ctx.drawImage(video, 0, 0, canvas.width, canvas.height);
                    
                    const imageData = ctx.getImageData(0, 0, canvas.width, canvas.height);
                    const code = jsQR(imageData.data, imageData.width, imageData.height);
                    
                    if (code) {
                        handleQRCode(code.data);
                    }
                }
            }, 100);
        })
        .catch(err => {
            console.error('Erreur accès caméra:', err);
            alert('Impossible d\'accéder à la caméra');
            placeholder.style.display = 'block';
            video.style.display = 'none';
        });
}

// Arrêter le scanner
function stopScanner() {
    if (scanner) {
        clearInterval(scanner);
        scanner = null;
    }
    
    const video = document.getElementById('video');
    const stream = video.srcObject;
    if (stream) {
        stream.getTracks().forEach(track => track.stop());
    }
    
    video.style.display = 'none';
    document.getElementById('scanner-placeholder').style.display = 'block';
}

// Traiter le QR code scanné
function handleQRCode(qrData) {
    try {
        const data = JSON.parse(qrData);
        
        if (data.type === 'student_card') {
            currentStudent = data;
            showScanResult(data);
            logScan('success', data);
        } else {
            showError('QR Code non reconnu');
            logScan('error', { message: 'QR Code non reconnu', data: qrData });
        }
    } catch (e) {
        showError('QR Code invalide');
        logScan('error', { message: 'QR Code invalide', data: qrData });
    }
}

// Afficher le résultat du scan
function showScanResult(data) {
    const resultDiv = document.getElementById('scan-result');
    const contentDiv = document.getElementById('scan-result-content');
    
    contentDiv.innerHTML = `
        <div class="row">
            <div class="col-md-6">
                <h6>Informations de la carte</h6>
                <p><strong>Matricule:</strong> ${data.matricule}</p>
                <p><strong>N° Carte:</strong> ${data.card_number}</p>
                <p><strong>Année:</strong> ${data.year}</p>
            </div>
            <div class="col-md-6">
                <h6>Actions disponibles</h6>
                <div class="d-grid gap-2">
                    <button class="btn btn-success btn-sm" onclick="markAttendance()">
                        <i class="mdi mdi-check-circle me-1"></i> Marquer présence
                    </button>
                    <button class="btn btn-info btn-sm" onclick="checkBalance()">
                        <i class="mdi mdi-currency-usd me-1"></i> Vérifier solde
                    </button>
                    <button class="btn btn-warning btn-sm" onclick="viewStudentInfo()">
                        <i class="mdi mdi-account me-1"></i> Infos élève
                    </button>
                </div>
            </div>
        </div>
    `;
    
    resultDiv.style.display = 'block';
}

// Afficher une erreur
function showError(message) {
    const resultDiv = document.getElementById('scan-result');
    const contentDiv = document.getElementById('scan-result-content');
    
    contentDiv.innerHTML = `
        <div class="alert alert-danger">
            <i class="mdi mdi-alert-circle me-2"></i>
            ${message}
        </div>
    `;
    
    resultDiv.style.display = 'block';
}

// Marquer la présence
function markAttendance() {
    if (!currentStudent) {
        alert('Aucun élève sélectionné');
        return;
    }
    
    currentAction = 'attendance';
    fetch('qr-actions.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `action=mark_attendance&student_id=${currentStudent.student_id}&matricule=${currentStudent.matricule}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Présence marquée avec succès');
            logScan('attendance', currentStudent);
        } else {
            alert('Erreur: ' + data.message);
        }
    });
}

// Vérifier le solde
function checkBalance() {
    if (!currentStudent) {
        alert('Aucun élève sélectionné');
        return;
    }
    
    currentAction = 'balance';
    fetch('qr-actions.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `action=check_balance&student_id=${currentStudent.student_id}&matricule=${currentStudent.matricule}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showBalanceModal(data.balance);
            logScan('balance', currentStudent);
        } else {
            alert('Erreur: ' + data.message);
        }
    });
}

// Afficher les informations de l'élève
function viewStudentInfo() {
    if (!currentStudent) {
        alert('Aucun élève sélectionné');
        return;
    }
    
    currentAction = 'info';
    fetch('qr-actions.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `action=get_student_info&student_id=${currentStudent.student_id}&matricule=${currentStudent.matricule}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showStudentModal(data.student);
            logScan('info', currentStudent);
        } else {
            alert('Erreur: ' + data.message);
        }
    });
}

// Afficher le modal de solde
function showBalanceModal(balance) {
    const modal = new bootstrap.Modal(document.getElementById('studentModal'));
    document.getElementById('studentModalContent').innerHTML = `
        <div class="text-center">
            <h4>Solde de l'élève</h4>
            <div class="display-4 text-${balance.solde >= 0 ? 'success' : 'danger'}">
                ${balance.solde.toLocaleString()} ${balance.devise}
            </div>
            <p class="text-muted">Dernière mise à jour: ${balance.last_update}</p>
        </div>
    `;
    modal.show();
}

// Afficher le modal d'informations
function showStudentModal(student) {
    const modal = new bootstrap.Modal(document.getElementById('studentModal'));
    document.getElementById('studentModalContent').innerHTML = `
        <div class="row">
            <div class="col-md-4 text-center">
                ${student.photo ? 
                    `<img src="../../uploads/photos/${student.photo}" class="img-thumbnail" style="max-width: 150px;">` :
                    `<div class="bg-light rounded-circle d-flex align-items-center justify-content-center mx-auto" style="width: 150px; height: 150px;">
                        <i class="mdi mdi-account text-muted" style="font-size: 48px;"></i>
                    </div>`
                }
            </div>
            <div class="col-md-8">
                <h4>${student.nom} ${student.prenom}</h4>
                <p><strong>Matricule:</strong> ${student.numero_matricule}</p>
                <p><strong>Classe:</strong> ${student.classe_nom}</p>
                <p><strong>Date de naissance:</strong> ${student.date_naissance}</p>
                <p><strong>Sexe:</strong> ${student.sexe}</p>
                <p><strong>Téléphone parent:</strong> ${student.telephone_parent || 'Non renseigné'}</p>
            </div>
        </div>
    `;
    modal.show();
}

// Imprimer la carte
function printCard() {
    if (!currentStudent) {
        alert('Aucun élève sélectionné');
        return;
    }
    
    window.open(`print.php?matricule=${currentStudent.matricule}`, '_blank');
}

// Enregistrer le scan
function logScan(type, data) {
    fetch('qr-actions.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `action=log_scan&type=${type}&data=${encodeURIComponent(JSON.stringify(data))}`
    });
    
    loadScanHistory();
    loadStatistics();
}

// Charger l'historique des scans
function loadScanHistory() {
    fetch('qr-actions.php?action=get_scan_history')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const historyDiv = document.getElementById('scan-history');
                historyDiv.innerHTML = data.history.map(scan => `
                    <div class="d-flex justify-content-between align-items-center border-bottom py-2">
                        <div>
                            <small class="fw-medium">${scan.type_scan}</small>
                            <br>
                            <small class="text-muted">${scan.created_at}</small>
                        </div>
                        <span class="badge bg-${scan.type_scan === 'presence' ? 'success' : scan.type_scan === 'solde' ? 'info' : 'secondary'}">
                            ${scan.type_scan}
                        </span>
                    </div>
                `).join('');
            }
        });
}

// Charger les statistiques
function loadStatistics() {
    fetch('qr-actions.php?action=get_statistics')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                document.getElementById('today-scans').textContent = data.today_scans;
                document.getElementById('total-scans').textContent = data.total_scans;
            }
        });
}

// Initialiser
document.addEventListener('DOMContentLoaded', function() {
    loadScanHistory();
    loadStatistics();
});
</script>

<?php include dirname(__DIR__, 2) . '/includes/footer.php'; ?>
