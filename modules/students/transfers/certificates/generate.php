<?php
/**
 * Génération de certificats de transfert
 * Application de gestion scolaire - République Démocratique du Congo
 */

require_once '../../../../config/config.php';
require_once '../../../../config/database.php';
require_once '../../../../includes/functions.php';

// Vérifier l'authentification et les permissions
requireLogin();
if (!checkPermission('students')) {
    redirectTo('../../../../login.php');
}

$page_title = "Génération de certificat";

// Récupérer l'ID du transfert
$transfer_id = $_GET['id'] ?? null;

if (!$transfer_id) {
    showMessage('error', 'ID de transfert manquant');
    redirectTo('../index.php');
}

// Récupérer les informations du transfert
$transfer = $database->query(
    "SELECT t.*, e.numero_matricule, e.nom, e.prenom, e.date_naissance, e.lieu_naissance, e.sexe, e.adresse,
            e.nom_pere, e.nom_mere, e.profession_pere, e.profession_mere,
            c_orig.nom as classe_origine_nom, c_orig.niveau as classe_origine_niveau,
            c_dest.nom as classe_destination_nom, c_dest.niveau as classe_destination_niveau,
            u_traite.nom as traite_par_nom, u_traite.prenom as traite_par_prenom,
            u_approuve.nom as approuve_par_nom, u_approuve.prenom as approuve_par_prenom,
            a.annee as annee_nom
     FROM transfers t
     JOIN eleves e ON t.eleve_id = e.id
     LEFT JOIN classes c_orig ON t.classe_origine_id = c_orig.id
     LEFT JOIN classes c_dest ON t.classe_destination_id = c_dest.id
     LEFT JOIN users u_traite ON t.traite_par = u_traite.id
     LEFT JOIN users u_approuve ON t.approuve_par = u_approuve.id
     LEFT JOIN inscriptions i ON e.id = i.eleve_id AND i.status = 'inscrit'
     LEFT JOIN annees_scolaires a ON i.annee_scolaire_id = a.id
     WHERE t.id = ?",
    [$transfer_id]
)->fetch();

if (!$transfer) {
    showMessage('error', 'Transfert non trouvé');
    redirectTo('../index.php');
}

// Vérifier que le transfert est complété
if ($transfer['statut'] !== 'complete') {
    showMessage('error', 'Le transfert doit être complété pour générer un certificat');
    redirectTo("../view-transfer.php?id=$transfer_id");
}

// Traitement de la génération/régénération du certificat
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generate_certificate'])) {
    try {
        // Générer un numéro de certificat s'il n'existe pas
        if (!$transfer['numero_certificat']) {
            $numero_certificat = 'CERT' . date('Y') . str_pad($transfer_id, 6, '0', STR_PAD_LEFT);
            
            $database->query(
                "UPDATE transfers SET certificat_genere = 1, numero_certificat = ? WHERE id = ?",
                [$numero_certificat, $transfer_id]
            );
            
            $transfer['numero_certificat'] = $numero_certificat;
            $transfer['certificat_genere'] = 1;
            
            // Enregistrer l'historique
            $database->query(
                "INSERT INTO transfer_history (transfer_id, action, ancien_statut, nouveau_statut, commentaire, user_id) VALUES (?, 'modification', ?, ?, 'Certificat généré', ?)",
                [$transfer_id, $transfer['statut'], $transfer['statut'], $_SESSION['user_id']]
            );
        }
        
        // Logger l'action
        logUserAction('generate_certificate', 'transfers', "Certificat généré pour le transfert ID: $transfer_id", $transfer_id);
        
        showMessage('success', 'Certificat généré avec succès !');
        
    } catch (Exception $e) {
        showMessage('error', $e->getMessage());
    }
}

// Traitement de l'impression/téléchargement
if (isset($_GET['action']) && $_GET['action'] === 'print') {
    generateCertificatePDF($transfer);
    exit;
}

// Fonction de génération du certificat PDF
function generateCertificatePDF($transfer) {
    $filename = "certificat_" . $transfer['numero_certificat'] . ".html";
    
    header('Content-Type: text/html; charset=UTF-8');
    header('Content-Disposition: inline; filename="' . $filename . '"');
    
    $type_labels = [
        'transfert_entrant' => 'CERTIFICAT DE TRANSFERT ENTRANT',
        'transfert_sortant' => 'CERTIFICAT DE TRANSFERT SORTANT',
        'sortie_definitive' => 'CERTIFICAT DE FIN DE SCOLARITÉ'
    ];
    
    $certificate_title = $type_labels[$transfer['type_mouvement']] ?? 'CERTIFICAT';
    
    echo "<!DOCTYPE html>
    <html lang='fr'>
    <head>
        <meta charset='UTF-8'>
        <meta name='viewport' content='width=device-width, initial-scale=1.0'>
        <title>$certificate_title</title>
        <style>
            @page {
                size: A4 portrait;
                margin: 1.5cm;
                padding: 0;
            }

            * {
                box-sizing: border-box;
            }

            body {
                font-family: 'Times New Roman', serif;
                line-height: 1.5;
                color: #333;
                margin: 0;
                padding: 0;
                background: white;
                font-size: 14px;
            }

            .certificate {
                width: 100%;
                max-width: 21cm;
                min-height: 29.7cm;
                margin: 0 auto;
                padding: 2cm;
                border: 4px solid #2c5aa0;
                border-radius: 10px;
                background: white;
                position: relative;
                page-break-inside: avoid;
            }
            
            .header {
                text-align: center;
                margin-bottom: 3cm;
                border-bottom: 3px solid #2c5aa0;
                padding-bottom: 1cm;
            }

            .logo {
                width: 100px;
                height: 100px;
                margin: 0 auto 1cm;
                background: #2c5aa0;
                border-radius: 50%;
                display: flex;
                align-items: center;
                justify-content: center;
                color: white;
                font-size: 2.5rem;
                font-weight: bold;
            }

            .school-name {
                font-size: 2.2rem;
                font-weight: bold;
                color: #2c5aa0;
                margin-bottom: 0.5cm;
                text-transform: uppercase;
                letter-spacing: 1px;
            }

            .school-address {
                font-size: 1.1rem;
                color: #666;
                margin-bottom: 1cm;
                line-height: 1.4;
            }

            .certificate-title {
                font-size: 1.8rem;
                font-weight: bold;
                color: #2c5aa0;
                text-transform: uppercase;
                letter-spacing: 3px;
                border: 3px solid #2c5aa0;
                padding: 1cm;
                border-radius: 10px;
                background: #f8f9fa;
                margin: 1cm 0;
            }
            
            .certificate-number {
                text-align: right;
                margin-bottom: 1cm;
                font-weight: bold;
                color: #666;
                font-size: 1.1rem;
            }

            .content {
                margin: 1.5cm 0;
                text-align: justify;
                font-size: 1.2rem;
                line-height: 1.8;
            }

            .student-info {
                background: #f8f9fa;
                padding: 1cm;
                border-radius: 10px;
                margin: 1cm 0;
                border-left: 8px solid #2c5aa0;
                border: 2px solid #e9ecef;
            }

            .info-row {
                display: flex;
                margin-bottom: 0.5cm;
                align-items: baseline;
            }

            .info-label {
                font-weight: bold;
                width: 6cm;
                color: #2c5aa0;
                font-size: 1.1rem;
            }

            .info-value {
                flex: 1;
                font-size: 1.1rem;
                color: #333;
            }
            
            .signatures {
                margin-top: 3cm;
                display: flex;
                justify-content: space-between;
                align-items: flex-end;
            }

            .signature-block {
                text-align: center;
                width: 6cm;
            }

            .signature-line {
                border-bottom: 2px solid #333;
                margin-bottom: 0.5cm;
                height: 2cm;
            }

            .signature-label {
                font-weight: bold;
                color: #2c5aa0;
                font-size: 1.1rem;
            }

            .footer {
                text-align: center;
                margin-top: 2cm;
                padding-top: 1cm;
                border-top: 2px solid #ddd;
                font-size: 1rem;
                color: #666;
            }

            .seal {
                position: absolute;
                right: 2cm;
                top: 8cm;
                width: 3cm;
                height: 3cm;
                border: 4px solid #2c5aa0;
                border-radius: 50%;
                display: flex;
                align-items: center;
                justify-content: center;
                background: rgba(44, 90, 160, 0.1);
                font-weight: bold;
                color: #2c5aa0;
                font-size: 0.9rem;
                text-align: center;
                line-height: 1.2;
            }

            .watermark {
                position: absolute;
                top: 50%;
                left: 50%;
                transform: translate(-50%, -50%) rotate(-45deg);
                font-size: 4rem;
                color: rgba(44, 90, 160, 0.05);
                font-weight: bold;
                z-index: 0;
                pointer-events: none;
            }

            .certificate-content {
                position: relative;
                z-index: 1;
            }
            
            @media print {
                @page {
                    size: A4 portrait;
                    margin: 1cm;
                }

                body {
                    font-size: 12px;
                }

                .certificate {
                    border: 4px solid #2c5aa0;
                    box-shadow: none;
                    margin: 0;
                    padding: 1.5cm;
                    min-height: auto;
                    page-break-inside: avoid;
                }

                .no-print {
                    display: none !important;
                }

                .watermark {
                    display: none;
                }
            }

            @media screen {
                body {
                    background: #f5f5f5;
                    padding: 1cm;
                }

                .certificate {
                    box-shadow: 0 0 20px rgba(0,0,0,0.1);
                }
            }
        </style>
    </head>
    <body>
        <div class='certificate'>
            <div class='watermark'>ÉCOLE SINFINITY</div>
            <div class='certificate-content'>
                <div class='header'>
                    <div class='logo'>ES</div>
                    <div class='school-name'>ÉCOLE SINFINITY</div>
                    <div class='school-address'>
                        Kinshasa, République Démocratique du Congo<br>
                        Tél: +243 XXX XXX XXX | Email: contact@ecolesinfinity.cd
                    </div>
                    <div class='certificate-title'>$certificate_title</div>
                </div>

                <div class='certificate-number'>
                    N° " . htmlspecialchars($transfer['numero_certificat']) . "
                </div>

                <div class='content'>
                    <p style='font-size: 1.3rem; margin-bottom: 1.5cm; text-align: center; font-weight: bold;'>
                        Le Directeur de l'École Sinfinity certifie par la présente que :
                    </p>

                    <div class='student-info'>
                    <div class='info-row'>
                        <div class='info-label'>Nom et Prénom :</div>
                        <div class='info-value'>" . htmlspecialchars($transfer['nom'] . ' ' . $transfer['prenom']) . "</div>
                    </div>
                    <div class='info-row'>
                        <div class='info-label'>Matricule :</div>
                        <div class='info-value'>" . htmlspecialchars($transfer['numero_matricule']) . "</div>
                    </div>
                    <div class='info-row'>
                        <div class='info-label'>Date de naissance :</div>
                        <div class='info-value'>" . date('d/m/Y', strtotime($transfer['date_naissance'])) . "</div>
                    </div>
                    <div class='info-row'>
                        <div class='info-label'>Lieu de naissance :</div>
                        <div class='info-value'>" . htmlspecialchars($transfer['lieu_naissance'] ?: 'Non spécifié') . "</div>
                    </div>
                    <div class='info-row'>
                        <div class='info-label'>Sexe :</div>
                        <div class='info-value'>" . ($transfer['sexe'] === 'M' ? 'Masculin' : 'Féminin') . "</div>
                    </div>
                    <div class='info-row'>
                        <div class='info-label'>Nom du père :</div>
                        <div class='info-value'>" . htmlspecialchars($transfer['nom_pere'] ?: 'Non spécifié') . "</div>
                    </div>
                    <div class='info-row'>
                        <div class='info-label'>Nom de la mère :</div>
                        <div class='info-value'>" . htmlspecialchars($transfer['nom_mere'] ?: 'Non spécifié') . "</div>
                    </div>";
    
    if ($transfer['type_mouvement'] === 'transfert_entrant') {
        echo "<div class='info-row'>
                <div class='info-label'>École d'origine :</div>
                <div class='info-value'>" . htmlspecialchars($transfer['ecole_origine']) . "</div>
              </div>
              <div class='info-row'>
                <div class='info-label'>Classe d'affectation :</div>
                <div class='info-value'>" . htmlspecialchars(($transfer['classe_destination_niveau'] ?? '') . ' - ' . ($transfer['classe_destination_nom'] ?? '')) . "</div>
              </div>";
        
        echo "</div>
              <p>A été admis(e) par transfert dans notre établissement en date du " . date('d/m/Y', strtotime($transfer['date_effective'])) . " 
              pour poursuivre ses études en " . htmlspecialchars(($transfer['classe_destination_niveau'] ?? '') . ' - ' . ($transfer['classe_destination_nom'] ?? '')) . ".</p>";
    
    } elseif ($transfer['type_mouvement'] === 'transfert_sortant') {
        echo "<div class='info-row'>
                <div class='info-label'>Classe fréquentée :</div>
                <div class='info-value'>" . htmlspecialchars(($transfer['classe_origine_niveau'] ?? '') . ' - ' . ($transfer['classe_origine_nom'] ?? '')) . "</div>
              </div>
              <div class='info-row'>
                <div class='info-label'>École de destination :</div>
                <div class='info-value'>" . htmlspecialchars($transfer['ecole_destination']) . "</div>
              </div>";
        
        echo "</div>
              <p>A fréquenté notre établissement en " . htmlspecialchars(($transfer['classe_origine_niveau'] ?? '') . ' - ' . ($transfer['classe_origine_nom'] ?? '')) . " 
              et a quitté l'école en date du " . date('d/m/Y', strtotime($transfer['date_effective'])) . " 
              pour poursuivre ses études à " . htmlspecialchars($transfer['ecole_destination']) . ".</p>";
    
    } else { // sortie_definitive
        echo "<div class='info-row'>
                <div class='info-label'>Dernière classe :</div>
                <div class='info-value'>" . htmlspecialchars(($transfer['classe_origine_niveau'] ?? '') . ' - ' . ($transfer['classe_origine_nom'] ?? '')) . "</div>
              </div>";
        
        echo "</div>
              <p>A terminé sa scolarité dans notre établissement en " . htmlspecialchars(($transfer['classe_origine_niveau'] ?? '') . ' - ' . ($transfer['classe_origine_nom'] ?? '')) . " 
              en date du " . date('d/m/Y', strtotime($transfer['date_effective'])) . ".</p>";
    }
    
    echo "<p style='font-size: 1.2rem; margin-top: 1.5cm; text-align: center; font-style: italic;'>
                        Ce certificat est délivré pour servir et valoir ce que de droit.
                    </p>
                </div>

                <div class='signatures'>
                    <div class='signature-block'>
                        <div class='signature-line'></div>
                        <div class='signature-label'>Le Secrétaire</div>
                    </div>
                    <div class='signature-block'>
                        <div class='signature-line'></div>
                        <div class='signature-label'>Le Directeur</div>
                    </div>
                </div>

                <div class='seal'>
                    SCEAU<br>DE<br>L'ÉCOLE
                </div>

                <div class='footer'>
                    Fait à Kinshasa, le " . date('d/m/Y') . "<br>
                    <strong>École Sinfinity - Certificat N° " . htmlspecialchars($transfer['numero_certificat']) . "</strong>
                </div>
            </div>
        </div>

        <div class='no-print' style='text-align: center; margin-top: 1cm; padding: 1cm;'>
            <button onclick='window.print()' style='background: #2c5aa0; color: white; border: none; padding: 15px 30px; border-radius: 8px; cursor: pointer; font-size: 1.1rem; box-shadow: 0 4px 8px rgba(0,0,0,0.2);'>
                <i class='fas fa-print' style='margin-right: 8px;'></i>
                Imprimer le certificat
            </button>
            <button onclick='window.close()' style='background: #6c757d; color: white; border: none; padding: 15px 30px; border-radius: 8px; cursor: pointer; font-size: 1.1rem; margin-left: 10px; box-shadow: 0 4px 8px rgba(0,0,0,0.2);'>
                <i class='fas fa-times' style='margin-right: 8px;'></i>
                Fermer
            </button>
        </div>
    </body>
    </html>";
}

include '../../../../includes/header.php';
?>

<!-- Styles CSS modernes -->
<style>
.certificate-header {
    background: linear-gradient(135deg, #6f42c1 0%, #e83e8c 100%);
    color: white;
    padding: 2rem 0;
    margin: -20px -15px 30px -15px;
    border-radius: 0 0 20px 20px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.1);
}

.certificate-header h1 {
    font-weight: 300;
    font-size: 2.5rem;
    margin-bottom: 0.5rem;
}

.certificate-header .subtitle {
    opacity: 0.9;
    font-size: 1.1rem;
}

.info-card {
    background: white;
    border-radius: 15px;
    padding: 2rem;
    box-shadow: 0 5px 15px rgba(0,0,0,0.08);
    border: none;
    margin-bottom: 2rem;
}

.student-summary {
    background: linear-gradient(135deg, #e3f2fd 0%, #f3e5f5 100%);
    border-radius: 15px;
    padding: 2rem;
    margin-bottom: 2rem;
    border-left: 5px solid #6f42c1;
}

.transfer-details {
    background: #f8f9fa;
    border-radius: 10px;
    padding: 1.5rem;
    margin-bottom: 1.5rem;
}

.detail-row {
    display: flex;
    margin-bottom: 1rem;
    align-items: center;
}

.detail-label {
    font-weight: 600;
    color: #6f42c1;
    width: 200px;
    flex-shrink: 0;
}

.detail-value {
    flex: 1;
    color: #495057;
}

.status-badge {
    padding: 0.5rem 1rem;
    border-radius: 20px;
    font-weight: 500;
    font-size: 0.9rem;
}

.status-complete {
    background: #d4edda;
    color: #155724;
}

.certificate-preview {
    background: white;
    border: 2px dashed #6f42c1;
    border-radius: 15px;
    padding: 2rem;
    text-align: center;
    margin-bottom: 2rem;
}

.certificate-icon {
    font-size: 4rem;
    color: #6f42c1;
    margin-bottom: 1rem;
}

.btn-modern {
    border-radius: 25px;
    padding: 0.75rem 2rem;
    font-weight: 600;
    transition: all 0.3s ease;
    border: none;
    box-shadow: 0 3px 15px rgba(0,0,0,0.1);
}

.btn-modern:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 25px rgba(0,0,0,0.2);
}

.btn-primary.btn-modern {
    background: linear-gradient(135deg, #6f42c1 0%, #e83e8c 100%);
}

.btn-success.btn-modern {
    background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
}

.btn-info.btn-modern {
    background: linear-gradient(135deg, #17a2b8 0%, #6f42c1 100%);
}

.btn-secondary.btn-modern {
    background: linear-gradient(135deg, #6c757d 0%, #495057 100%);
}

@keyframes fadeInUp {
    from {
        opacity: 0;
        transform: translateY(30px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.animate-fade-in {
    animation: fadeInUp 0.6s ease-out;
}

.animate-delay-1 { animation-delay: 0.1s; }
.animate-delay-2 { animation-delay: 0.2s; }
.animate-delay-3 { animation-delay: 0.3s; }

@media (max-width: 768px) {
    .certificate-header {
        margin: -20px -15px 20px -15px;
        padding: 1.5rem 0;
    }

    .certificate-header h1 {
        font-size: 2rem;
    }

    .info-card, .student-summary {
        padding: 1rem;
    }

    .detail-row {
        flex-direction: column;
        align-items: flex-start;
    }

    .detail-label {
        width: 100%;
        margin-bottom: 0.5rem;
    }
}
</style>

<!-- En-tête moderne -->
<div class="certificate-header">
    <div class="container-fluid">
        <div class="row align-items-center">
            <div class="col-md-8">
                <h1 class="animate-fade-in">
                    <i class="fas fa-certificate me-3"></i>
                    Génération de certificat
                </h1>
                <p class="subtitle animate-fade-in animate-delay-1">
                    Certificat N° <?php echo htmlspecialchars($transfer['numero_certificat'] ?: 'À générer'); ?>
                </p>
            </div>
            <div class="col-md-4 text-end">
                <div class="animate-fade-in animate-delay-2">
                    <a href="../bulk-process.php" class="btn btn-light btn-modern">
                        <i class="fas fa-arrow-left me-2"></i>
                        Retour
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Résumé de l'élève -->
<div class="student-summary animate-fade-in animate-delay-1">
    <div class="row align-items-center">
        <div class="col-md-2 text-center">
            <div class="student-avatar">
                <i class="fas fa-user-graduate fa-4x text-primary"></i>
            </div>
        </div>
        <div class="col-md-10">
            <h4 class="mb-2">
                <?php echo htmlspecialchars($transfer['nom'] . ' ' . $transfer['prenom']); ?>
                <span class="status-badge status-complete ms-2">
                    <i class="fas fa-check-circle me-1"></i>
                    Transfert complété
                </span>
            </h4>
            <div class="row">
                <div class="col-md-6">
                    <p class="mb-1"><strong>Matricule:</strong> <?php echo htmlspecialchars($transfer['numero_matricule']); ?></p>
                    <p class="mb-1"><strong>Date de naissance:</strong> <?php echo date('d/m/Y', strtotime($transfer['date_naissance'])); ?></p>
                </div>
                <div class="col-md-6">
                    <p class="mb-1"><strong>Type:</strong>
                        <?php
                        $type_labels = [
                            'transfert_entrant' => '<i class="fas fa-arrow-right text-success"></i> Transfert entrant',
                            'transfert_sortant' => '<i class="fas fa-arrow-left text-warning"></i> Transfert sortant',
                            'sortie_definitive' => '<i class="fas fa-graduation-cap text-info"></i> Sortie définitive'
                        ];
                        echo $type_labels[$transfer['type_mouvement']] ?? $transfer['type_mouvement'];
                        ?>
                    </p>
                    <p class="mb-1"><strong>Date effective:</strong> <?php echo date('d/m/Y', strtotime($transfer['date_effective'])); ?></p>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Détails du transfert -->
<div class="info-card animate-fade-in animate-delay-2">
    <h5 class="mb-3">
        <i class="fas fa-info-circle me-2"></i>
        Détails du transfert
    </h5>

    <div class="transfer-details">
        <div class="detail-row">
            <div class="detail-label">Motif:</div>
            <div class="detail-value"><?php echo htmlspecialchars($transfer['motif']); ?></div>
        </div>

        <?php if ($transfer['type_mouvement'] === 'transfert_entrant'): ?>
            <div class="detail-row">
                <div class="detail-label">École d'origine:</div>
                <div class="detail-value"><?php echo htmlspecialchars($transfer['ecole_origine']); ?></div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Classe d'affectation:</div>
                <div class="detail-value"><?php echo htmlspecialchars(($transfer['classe_destination_niveau'] ?? '') . ' - ' . ($transfer['classe_destination_nom'] ?? '')); ?></div>
            </div>
        <?php elseif ($transfer['type_mouvement'] === 'transfert_sortant'): ?>
            <div class="detail-row">
                <div class="detail-label">Classe fréquentée:</div>
                <div class="detail-value"><?php echo htmlspecialchars(($transfer['classe_origine_niveau'] ?? '') . ' - ' . ($transfer['classe_origine_nom'] ?? '')); ?></div>
            </div>
            <div class="detail-row">
                <div class="detail-label">École de destination:</div>
                <div class="detail-value"><?php echo htmlspecialchars($transfer['ecole_destination']); ?></div>
            </div>
        <?php else: ?>
            <div class="detail-row">
                <div class="detail-label">Dernière classe:</div>
                <div class="detail-value"><?php echo htmlspecialchars(($transfer['classe_origine_niveau'] ?? '') . ' - ' . ($transfer['classe_origine_nom'] ?? '')); ?></div>
            </div>
        <?php endif; ?>

        <div class="detail-row">
            <div class="detail-label">Date de demande:</div>
            <div class="detail-value"><?php echo date('d/m/Y', strtotime($transfer['date_demande'])); ?></div>
        </div>

        <div class="detail-row">
            <div class="detail-label">Approuvé par:</div>
            <div class="detail-value">
                <?php if ($transfer['approuve_par_nom']): ?>
                    <?php echo htmlspecialchars($transfer['approuve_par_nom'] . ' ' . $transfer['approuve_par_prenom']); ?>
                    <small class="text-muted">(<?php echo date('d/m/Y', strtotime($transfer['date_approbation'])); ?>)</small>
                <?php else: ?>
                    <span class="text-muted">Non spécifié</span>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($transfer['observations']): ?>
            <div class="detail-row">
                <div class="detail-label">Observations:</div>
                <div class="detail-value"><?php echo htmlspecialchars($transfer['observations']); ?></div>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Aperçu du certificat -->
<div class="certificate-preview animate-fade-in animate-delay-3">
    <?php if ($transfer['certificat_genere']): ?>
        <div class="certificate-icon">
            <i class="fas fa-certificate"></i>
        </div>
        <h5 class="text-success mb-3">Certificat généré</h5>
        <p class="mb-3">
            Le certificat N° <strong><?php echo htmlspecialchars($transfer['numero_certificat']); ?></strong>
            a été généré avec succès.
        </p>
        <div class="d-flex justify-content-center gap-3 flex-wrap">
            <a href="?id=<?php echo $transfer_id; ?>&action=print" target="_blank" class="btn btn-success btn-modern">
                <i class="fas fa-print me-2"></i>
                Imprimer le certificat
            </a>
            <a href="?id=<?php echo $transfer_id; ?>&action=print" target="_blank" class="btn btn-info btn-modern">
                <i class="fas fa-download me-2"></i>
                Télécharger PDF
            </a>
            <form method="POST" class="d-inline">
                <input type="hidden" name="generate_certificate" value="1">
                <button type="submit" class="btn btn-secondary btn-modern">
                    <i class="fas fa-sync-alt me-2"></i>
                    Régénérer
                </button>
            </form>
        </div>
    <?php else: ?>
        <div class="certificate-icon">
            <i class="fas fa-file-alt"></i>
        </div>
        <h5 class="text-muted mb-3">Certificat non généré</h5>
        <p class="mb-3">
            Le certificat pour ce transfert n'a pas encore été généré.
        </p>
        <form method="POST">
            <input type="hidden" name="generate_certificate" value="1">
            <button type="submit" class="btn btn-primary btn-modern">
                <i class="fas fa-certificate me-2"></i>
                Générer le certificat
            </button>
        </form>
    <?php endif; ?>
</div>

<!-- Informations complémentaires -->
<div class="info-card animate-fade-in animate-delay-3">
    <h5 class="mb-3">
        <i class="fas fa-user me-2"></i>
        Informations personnelles
    </h5>

    <div class="row">
        <div class="col-md-6">
            <div class="transfer-details">
                <div class="detail-row">
                    <div class="detail-label">Lieu de naissance:</div>
                    <div class="detail-value"><?php echo htmlspecialchars($transfer['lieu_naissance'] ?: 'Non spécifié'); ?></div>
                </div>
                <div class="detail-row">
                    <div class="detail-label">Sexe:</div>
                    <div class="detail-value"><?php echo $transfer['sexe'] === 'M' ? 'Masculin' : 'Féminin'; ?></div>
                </div>
                <div class="detail-row">
                    <div class="detail-label">Adresse:</div>
                    <div class="detail-value"><?php echo htmlspecialchars($transfer['adresse'] ?: 'Non spécifiée'); ?></div>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="transfer-details">
                <div class="detail-row">
                    <div class="detail-label">Nom du père:</div>
                    <div class="detail-value"><?php echo htmlspecialchars($transfer['nom_pere'] ?: 'Non spécifié'); ?></div>
                </div>
                <div class="detail-row">
                    <div class="detail-label">Nom de la mère:</div>
                    <div class="detail-value"><?php echo htmlspecialchars($transfer['nom_mere'] ?: 'Non spécifié'); ?></div>
                </div>
                <div class="detail-row">
                    <div class="detail-label">Profession du père:</div>
                    <div class="detail-value"><?php echo htmlspecialchars($transfer['profession_pere'] ?: 'Non spécifiée'); ?></div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Confirmation pour la régénération
document.addEventListener('DOMContentLoaded', function() {
    const regenerateForm = document.querySelector('form[method="POST"]');
    if (regenerateForm) {
        regenerateForm.addEventListener('submit', function(e) {
            const isRegenerate = this.querySelector('button').textContent.includes('Régénérer');
            if (isRegenerate) {
                if (!confirm('Êtes-vous sûr de vouloir régénérer ce certificat ?')) {
                    e.preventDefault();
                    return false;
                }
            }
        });
    }
});
</script>

<?php include '../../../../includes/footer.php'; ?>
