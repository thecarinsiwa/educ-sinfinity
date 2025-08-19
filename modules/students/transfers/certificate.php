<?php
/**
 * Affichage direct du certificat de transfert
 * Application de gestion scolaire - République Démocratique du Congo
 */

require_once '../../../config/config.php';
require_once '../../../config/database.php';
require_once '../../../includes/functions.php';

// Vérifier l'authentification et les permissions
requireLogin();
if (!checkPermission('students')) {
    redirectTo('../../../login.php');
}

// Récupérer l'ID du transfert
$transfer_id = $_GET['id'] ?? null;

if (!$transfer_id) {
    showMessage('error', 'ID de transfert manquant');
    redirectTo('index.php');
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
    redirectTo('index.php');
}

// Vérifier que le transfert est complété
if ($transfer['statut'] !== 'complete') {
    showMessage('error', 'Le transfert doit être complété pour afficher le certificat');
    redirectTo("view-transfer.php?id=$transfer_id");
}

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
        "INSERT INTO transfer_history (transfer_id, action, ancien_statut, nouveau_statut, commentaire, user_id) VALUES (?, 'modification', ?, ?, 'Certificat généré automatiquement', ?)",
        [$transfer_id, $transfer['statut'], $transfer['statut'], $_SESSION['user_id']]
    );
    
    // Logger l'action
    logUserAction('generate_certificate', 'transfers', "Certificat généré automatiquement pour le transfert ID: $transfer_id", $transfer_id);
}

// Fonction de génération du certificat HTML
function generateCertificateHTML($transfer) {
    $type_labels = [
        'transfert_entrant' => 'CERTIFICAT DE TRANSFERT ENTRANT',
        'transfert_sortant' => 'CERTIFICAT DE TRANSFERT SORTANT',
        'sortie_definitive' => 'CERTIFICAT DE FIN DE SCOLARITÉ'
    ];
    
    $certificate_title = $type_labels[$transfer['type_mouvement']] ?? 'CERTIFICAT';
    
    return "<!DOCTYPE html>
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
            
            .print-controls {
                text-align: center;
                margin: 2cm 0;
                padding: 1cm;
                background: #f8f9fa;
                border-radius: 10px;
            }

            .print-btn {
                background: #2c5aa0;
                color: white;
                border: none;
                padding: 15px 30px;
                border-radius: 8px;
                cursor: pointer;
                font-size: 1.1rem;
                margin: 0 10px;
                box-shadow: 0 4px 8px rgba(0,0,0,0.2);
                transition: all 0.3s ease;
            }

            .print-btn:hover {
                background: #1e3a5f;
                transform: translateY(-2px);
            }

            .close-btn {
                background: #6c757d;
                color: white;
                border: none;
                padding: 15px 30px;
                border-radius: 8px;
                cursor: pointer;
                font-size: 1.1rem;
                margin: 0 10px;
                box-shadow: 0 4px 8px rgba(0,0,0,0.2);
                transition: all 0.3s ease;
            }

            .close-btn:hover {
                background: #495057;
                transform: translateY(-2px);
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

                .print-controls {
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
        $html .= "<div class='info-row'>
                <div class='info-label'>École d'origine :</div>
                <div class='info-value'>" . htmlspecialchars($transfer['ecole_origine']) . "</div>
              </div>
              <div class='info-row'>
                <div class='info-label'>Classe d'affectation :</div>
                <div class='info-value'>" . htmlspecialchars(($transfer['classe_destination_niveau'] ?? '') . ' - ' . ($transfer['classe_destination_nom'] ?? '')) . "</div>
              </div>";
        
        $html .= "</div>
              <p>A été admis(e) par transfert dans notre établissement en date du " . date('d/m/Y', strtotime($transfer['date_effective'])) . " 
              pour poursuivre ses études en " . htmlspecialchars(($transfer['classe_destination_niveau'] ?? '') . ' - ' . ($transfer['classe_destination_nom'] ?? '')) . ".</p>";
    
    } elseif ($transfer['type_mouvement'] === 'transfert_sortant') {
        $html .= "<div class='info-row'>
                <div class='info-label'>Classe fréquentée :</div>
                <div class='info-value'>" . htmlspecialchars(($transfer['classe_origine_niveau'] ?? '') . ' - ' . ($transfer['classe_origine_nom'] ?? '')) . "</div>
              </div>
              <div class='info-row'>
                <div class='info-label'>École de destination :</div>
                <div class='info-value'>" . htmlspecialchars($transfer['ecole_destination']) . "</div>
              </div>";
        
        $html .= "</div>
              <p>A fréquenté notre établissement en " . htmlspecialchars(($transfer['classe_origine_niveau'] ?? '') . ' - ' . ($transfer['classe_origine_nom'] ?? '')) . " 
              et a quitté l'école en date du " . date('d/m/Y', strtotime($transfer['date_effective'])) . " 
              pour poursuivre ses études à " . htmlspecialchars($transfer['ecole_destination']) . ".</p>";
    
    } else { // sortie_definitive
        $html .= "<div class='info-row'>
                <div class='info-label'>Dernière classe :</div>
                <div class='info-value'>" . htmlspecialchars(($transfer['classe_origine_niveau'] ?? '') . ' - ' . ($transfer['classe_origine_nom'] ?? '')) . "</div>
              </div>";
        
        $html .= "</div>
              <p>A terminé sa scolarité dans notre établissement en " . htmlspecialchars(($transfer['classe_origine_niveau'] ?? '') . ' - ' . ($transfer['classe_origine_nom'] ?? '')) . " 
              en date du " . date('d/m/Y', strtotime($transfer['date_effective'])) . ".</p>";
    }
    
    $html .= "<p style='font-size: 1.2rem; margin-top: 1.5cm; text-align: center; font-style: italic;'>
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

        <div class='print-controls'>
            <button onclick='window.print()' class='print-btn'>
                <i class='fas fa-print' style='margin-right: 8px;'></i>
                Imprimer le certificat
            </button>
            <button onclick='window.close()' class='close-btn'>
                <i class='fas fa-times' style='margin-right: 8px;'></i>
                Fermer
            </button>
            <a href='view-transfer.php?id=" . $transfer['id'] . "' class='close-btn' style='text-decoration: none; display: inline-block;'>
                <i class='fas fa-arrow-left' style='margin-right: 8px;'></i>
                Retour au transfert
            </a>
        </div>

        <script>
            // Auto-focus sur la page pour une meilleure expérience d'impression
            window.onload = function() {
                // Ajouter un délai pour permettre le chargement complet
                setTimeout(function() {
                    window.focus();
                }, 500);
            };
        </script>
    </body>
    </html>";
}

// Afficher le certificat
echo generateCertificateHTML($transfer);
?>
