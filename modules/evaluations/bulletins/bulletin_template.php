<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bulletin de <?php echo htmlspecialchars($eleve['nom'] . ' ' . $eleve['prenom']); ?></title>
    <style>
        body {
            font-family: 'Arial', sans-serif;
            font-size: 12px;
            line-height: 1.4;
            margin: 0;
            padding: 20px;
            color: #333;
        }
        
        .bulletin {
            max-width: 800px;
            margin: 0 auto;
            border: 2px solid #333;
            padding: 20px;
        }
        
        .header {
            text-align: center;
            border-bottom: 2px solid #333;
            padding-bottom: 15px;
            margin-bottom: 20px;
        }
        
        .header h1 {
            margin: 0;
            font-size: 24px;
            color: #2c3e50;
        }
        
        .header h2 {
            margin: 5px 0;
            font-size: 18px;
            color: #34495e;
        }
        
        .header p {
            margin: 5px 0;
            font-size: 14px;
        }
        
        .info-section {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .info-box {
            border: 1px solid #ddd;
            padding: 10px;
            background-color: #f8f9fa;
        }
        
        .info-box h3 {
            margin: 0 0 10px 0;
            font-size: 14px;
            color: #2c3e50;
            border-bottom: 1px solid #ddd;
            padding-bottom: 5px;
        }
        
        .info-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 5px;
        }
        
        .notes-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        
        .notes-table th,
        .notes-table td {
            border: 1px solid #333;
            padding: 8px;
            text-align: center;
        }
        
        .notes-table th {
            background-color: #34495e;
            color: white;
            font-weight: bold;
        }
        
        .notes-table .matiere {
            text-align: left;
            font-weight: bold;
        }
        
        .moyenne-generale {
            background-color: #e8f5e8;
            font-weight: bold;
        }
        
        .appreciation {
            text-align: center;
            margin: 20px 0;
            padding: 15px;
            border: 2px solid #2c3e50;
            background-color: #ecf0f1;
        }
        
        .appreciation h3 {
            margin: 0 0 10px 0;
            color: #2c3e50;
        }
        
        .signatures {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 20px;
            margin-top: 30px;
        }
        
        .signature-box {
            text-align: center;
            border-top: 1px solid #333;
            padding-top: 10px;
        }
        
        .excellent { background-color: #d4edda; }
        .tres-bien { background-color: #cce5ff; }
        .bien { background-color: #e6f3ff; }
        .assez-bien { background-color: #fff3cd; }
        .passable { background-color: #ffeaa7; }
        .insuffisant { background-color: #f8d7da; }
        
        @media print {
            body { margin: 0; padding: 10px; }
            .bulletin { border: 2px solid #000; }
            .no-print { display: none; }
        }
    </style>
</head>
<body>
    <div class="bulletin">
        <!-- En-tête -->
        <div class="header">
            <h1>BULLETIN SCOLAIRE</h1>
            <h2>République Démocratique du Congo</h2>
            <p><strong>Année scolaire <?php echo $current_year['annee']; ?></strong></p>
            <p><strong>Période : <?php echo str_replace('_', ' ', ucfirst($periode)); ?></strong></p>
        </div>
        
        <!-- Informations élève et classe -->
        <div class="info-section">
            <div class="info-box">
                <h3>INFORMATIONS ÉLÈVE</h3>
                <div class="info-row">
                    <span><strong>Nom :</strong></span>
                    <span><?php echo htmlspecialchars($eleve['nom']); ?></span>
                </div>
                <div class="info-row">
                    <span><strong>Prénom :</strong></span>
                    <span><?php echo htmlspecialchars($eleve['prenom']); ?></span>
                </div>
                <div class="info-row">
                    <span><strong>Matricule :</strong></span>
                    <span><?php echo htmlspecialchars($eleve['numero_matricule']); ?></span>
                </div>
                <div class="info-row">
                    <span><strong>Date de naissance :</strong></span>
                    <span><?php echo $eleve['date_naissance'] ? date('d/m/Y', strtotime($eleve['date_naissance'])) : '-'; ?></span>
                </div>
                <div class="info-row">
                    <span><strong>Sexe :</strong></span>
                    <span><?php echo ucfirst($eleve['sexe']); ?></span>
                </div>
            </div>
            
            <div class="info-box">
                <h3>INFORMATIONS CLASSE</h3>
                <div class="info-row">
                    <span><strong>Classe :</strong></span>
                    <span><?php echo htmlspecialchars($eleve['classe_nom']); ?></span>
                </div>
                <div class="info-row">
                    <span><strong>Niveau :</strong></span>
                    <span><?php echo ucfirst($eleve['niveau']); ?></span>
                </div>
                <div class="info-row">
                    <span><strong>Effectif :</strong></span>
                    <span><?php echo $stats_classe['effectif'] ?? '-'; ?> élèves</span>
                </div>
                <div class="info-row">
                    <span><strong>Moyenne classe :</strong></span>
                    <span><?php echo round($stats_classe['moyenne_classe'] ?? 0, 2); ?>/20</span>
                </div>
            </div>
        </div>
        
        <!-- Tableau des notes -->
        <table class="notes-table">
            <thead>
                <tr>
                    <th rowspan="2">MATIÈRES</th>
                    <th rowspan="2">COEF.</th>
                    <th colspan="3">ÉVALUATIONS</th>
                    <th rowspan="2">MOYENNE</th>
                    <th rowspan="2">APPRÉCIATION</th>
                </tr>
                <tr>
                    <th>Contrôle</th>
                    <th>Devoir</th>
                    <th>Examen</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $total_points = 0;
                $total_coefficients = 0;
                
                foreach ($moyennes_matieres as $matiere => $data): 
                    $moyenne = $data['moyenne'];
                    $coefficient = $data['coefficient'];
                    
                    // Déterminer la classe CSS pour la couleur
                    $classe_couleur = '';
                    if ($moyenne >= 16) $classe_couleur = 'excellent';
                    elseif ($moyenne >= 14) $classe_couleur = 'tres-bien';
                    elseif ($moyenne >= 12) $classe_couleur = 'bien';
                    elseif ($moyenne >= 10) $classe_couleur = 'assez-bien';
                    elseif ($moyenne >= 8) $classe_couleur = 'passable';
                    else $classe_couleur = 'insuffisant';
                    
                    // Appréciation
                    $appreciation = '';
                    if ($moyenne >= 16) $appreciation = 'Excellent';
                    elseif ($moyenne >= 14) $appreciation = 'Très bien';
                    elseif ($moyenne >= 12) $appreciation = 'Bien';
                    elseif ($moyenne >= 10) $appreciation = 'Assez bien';
                    elseif ($moyenne >= 8) $appreciation = 'Passable';
                    else $appreciation = 'Insuffisant';
                    
                    // Récupérer les notes par type d'évaluation
                    $notes_par_type = ['controle' => '-', 'devoir' => '-', 'examen' => '-'];
                    foreach ($data['notes_detail'] as $note_detail) {
                        $type = strtolower($note_detail['type']);
                        if (isset($notes_par_type[$type])) {
                            $notes_par_type[$type] = round($note_detail['note'], 2);
                        }
                    }
                    
                    $total_points += $moyenne * $coefficient;
                    $total_coefficients += $coefficient;
                ?>
                    <tr class="<?php echo $classe_couleur; ?>">
                        <td class="matiere"><?php echo htmlspecialchars($matiere); ?></td>
                        <td><?php echo $coefficient; ?></td>
                        <td><?php echo $notes_par_type['controle']; ?></td>
                        <td><?php echo $notes_par_type['devoir']; ?></td>
                        <td><?php echo $notes_par_type['examen']; ?></td>
                        <td><strong><?php echo round($moyenne, 2); ?></strong></td>
                        <td><?php echo $appreciation; ?></td>
                    </tr>
                <?php endforeach; ?>
                
                <!-- Ligne de moyenne générale -->
                <tr class="moyenne-generale">
                    <td class="matiere"><strong>MOYENNE GÉNÉRALE</strong></td>
                    <td><strong><?php echo $total_coefficients; ?></strong></td>
                    <td colspan="3">-</td>
                    <td><strong><?php echo round($moyenne_generale, 2); ?>/20</strong></td>
                    <td><strong><?php echo $appreciation_generale; ?></strong></td>
                </tr>
            </tbody>
        </table>
        
        <!-- Appréciation générale -->
        <div class="appreciation">
            <h3>APPRÉCIATION GÉNÉRALE</h3>
            <p><strong>Moyenne générale : <?php echo round($moyenne_generale, 2); ?>/20</strong></p>
            <p><strong>Mention : <?php echo $appreciation_generale; ?></strong></p>
            <?php if ($moyenne_generale >= 10): ?>
                <p>L'élève a obtenu des résultats satisfaisants pour cette période. Continuez vos efforts.</p>
            <?php else: ?>
                <p>L'élève doit redoubler d'efforts pour améliorer ses résultats. Un accompagnement est recommandé.</p>
            <?php endif; ?>
        </div>
        
        <!-- Statistiques comparatives -->
        <div class="info-section">
            <div class="info-box">
                <h3>STATISTIQUES</h3>
                <div class="info-row">
                    <span><strong>Rang dans la classe :</strong></span>
                    <span>À calculer</span>
                </div>
                <div class="info-row">
                    <span><strong>Moyenne de la classe :</strong></span>
                    <span><?php echo round($stats_classe['moyenne_classe'] ?? 0, 2); ?>/20</span>
                </div>
                <div class="info-row">
                    <span><strong>Écart à la moyenne :</strong></span>
                    <span>
                        <?php 
                        $ecart = $moyenne_generale - ($stats_classe['moyenne_classe'] ?? 0);
                        echo ($ecart >= 0 ? '+' : '') . round($ecart, 2); 
                        ?>
                    </span>
                </div>
            </div>
            
            <div class="info-box">
                <h3>OBSERVATIONS</h3>
                <div style="min-height: 80px; padding: 10px; border: 1px solid #ddd; background: white;">
                    <?php if ($moyenne_generale >= 16): ?>
                        Excellent travail ! L'élève fait preuve d'une grande maîtrise des compétences.
                    <?php elseif ($moyenne_generale >= 14): ?>
                        Très bon travail. L'élève maîtrise bien les compétences attendues.
                    <?php elseif ($moyenne_generale >= 12): ?>
                        Bon travail. L'élève progresse bien dans l'acquisition des compétences.
                    <?php elseif ($moyenne_generale >= 10): ?>
                        Travail satisfaisant. L'élève doit consolider certaines compétences.
                    <?php else: ?>
                        L'élève rencontre des difficultés. Un accompagnement personnalisé est nécessaire.
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Signatures -->
        <div class="signatures">
            <div class="signature-box">
                <strong>Le Directeur</strong><br><br><br>
                _________________
            </div>
            <div class="signature-box">
                <strong>Le Titulaire</strong><br><br><br>
                _________________
            </div>
            <div class="signature-box">
                <strong>Le Parent/Tuteur</strong><br><br><br>
                _________________
            </div>
        </div>
        
        <!-- Pied de page -->
        <div style="text-align: center; margin-top: 20px; font-size: 10px; color: #666;">
            <p>Bulletin généré le <?php echo date('d/m/Y à H:i'); ?></p>
            <p>Système de gestion scolaire - République Démocratique du Congo</p>
        </div>
    </div>
    
    <?php if ($format === 'print'): ?>
        <script>
            window.onload = function() {
                window.print();
            };
        </script>
    <?php endif; ?>
</body>
</html>
