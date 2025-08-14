<?php
/**
 * Configuration des exports de rapports
 * Application de gestion scolaire - République Démocratique du Congo
 */

// Configuration des formats d'export
$export_formats = [
    'pdf' => [
        'name' => 'PDF',
        'mime_type' => 'application/pdf',
        'extension' => 'pdf',
        'icon' => 'fas fa-file-pdf',
        'color' => 'danger',
        'description' => 'Format PDF pour impression et archivage'
    ],
    'excel' => [
        'name' => 'Excel',
        'mime_type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'extension' => 'xlsx',
        'icon' => 'fas fa-file-excel',
        'color' => 'success',
        'description' => 'Format Excel pour analyse de données'
    ],
    'csv' => [
        'name' => 'CSV',
        'mime_type' => 'text/csv',
        'extension' => 'csv',
        'icon' => 'fas fa-file-csv',
        'color' => 'info',
        'description' => 'Format CSV pour import/export de données'
    ],
    'json' => [
        'name' => 'JSON',
        'mime_type' => 'application/json',
        'extension' => 'json',
        'icon' => 'fas fa-code',
        'color' => 'warning',
        'description' => 'Format JSON pour intégrations API'
    ]
];

// Configuration des modèles de rapports
$report_templates = [
    'academic' => [
        'name' => 'Rapports Académiques',
        'icon' => 'fas fa-graduation-cap',
        'color' => 'primary',
        'reports' => [
            'class_performance' => [
                'name' => 'Performance des classes',
                'description' => 'Analyse des résultats par classe',
                'query' => "
                    SELECT c.nom as classe, c.niveau,
                           COUNT(i.id) as nb_eleves,
                           AVG(n.note) as moyenne,
                           MIN(n.note) as note_min,
                           MAX(n.note) as note_max
                    FROM classes c
                    LEFT JOIN inscriptions i ON c.id = i.classe_id
                    LEFT JOIN notes n ON i.eleve_id = n.eleve_id
                    WHERE c.annee_scolaire_id = ?
                    GROUP BY c.id
                    ORDER BY moyenne DESC
                ",
                'params' => ['current_year_id'],
                'charts' => ['bar', 'pie']
            ],
            'student_grades' => [
                'name' => 'Notes des élèves',
                'description' => 'Détail des notes par élève',
                'query' => "
                    SELECT e.nom, e.prenom, e.numero_matricule,
                           c.nom as classe, m.nom as matiere,
                           n.note, ev.date_evaluation
                    FROM eleves e
                    JOIN inscriptions i ON e.id = i.eleve_id
                    JOIN classes c ON i.classe_id = c.id
                    JOIN notes n ON e.id = n.eleve_id
                    JOIN evaluations ev ON n.evaluation_id = ev.id
                    JOIN matieres m ON ev.matiere_id = m.id
                    WHERE i.annee_scolaire_id = ?
                    ORDER BY e.nom, e.prenom, ev.date_evaluation
                ",
                'params' => ['current_year_id'],
                'charts' => ['line', 'scatter']
            ]
        ]
    ],
    'financial' => [
        'name' => 'Rapports Financiers',
        'icon' => 'fas fa-chart-line',
        'color' => 'success',
        'reports' => [
            'payment_summary' => [
                'name' => 'Résumé des paiements',
                'description' => 'Synthèse des recettes par période',
                'query' => "
                    SELECT DATE_FORMAT(date_paiement, '%Y-%m') as mois,
                           type_frais,
                           SUM(montant) as total,
                           COUNT(*) as nb_paiements
                    FROM paiements
                    WHERE status = 'valide' AND annee_scolaire_id = ?
                    GROUP BY DATE_FORMAT(date_paiement, '%Y-%m'), type_frais
                    ORDER BY mois DESC, type_frais
                ",
                'params' => ['current_year_id'],
                'charts' => ['line', 'bar', 'pie']
            ],
            'outstanding_fees' => [
                'name' => 'Frais impayés',
                'description' => 'Liste des créances en cours',
                'query' => "
                    SELECT e.nom, e.prenom, e.numero_matricule,
                           c.nom as classe,
                           f.type_frais,
                           f.montant_total,
                           COALESCE(p.montant_paye, 0) as montant_paye,
                           (f.montant_total - COALESCE(p.montant_paye, 0)) as reste_a_payer
                    FROM eleves e
                    JOIN inscriptions i ON e.id = i.eleve_id
                    JOIN classes c ON i.classe_id = c.id
                    JOIN frais_scolaires f ON e.id = f.eleve_id
                    LEFT JOIN (
                        SELECT eleve_id, type_frais, SUM(montant) as montant_paye
                        FROM paiements
                        WHERE status = 'valide'
                        GROUP BY eleve_id, type_frais
                    ) p ON f.eleve_id = p.eleve_id AND f.type_frais = p.type_frais
                    WHERE f.annee_scolaire_id = ?
                    AND (f.montant_total - COALESCE(p.montant_paye, 0)) > 0
                    ORDER BY reste_a_payer DESC
                ",
                'params' => ['current_year_id'],
                'charts' => ['bar', 'pie']
            ]
        ]
    ],
    'administrative' => [
        'name' => 'Rapports Administratifs',
        'icon' => 'fas fa-users',
        'color' => 'info',
        'reports' => [
            'enrollment_stats' => [
                'name' => 'Statistiques d\'inscription',
                'description' => 'Effectifs par classe et niveau',
                'query' => "
                    SELECT c.nom as classe, c.niveau,
                           c.capacite_max,
                           COUNT(i.id) as effectif_actuel,
                           ROUND(COUNT(i.id) * 100.0 / c.capacite_max, 1) as taux_occupation
                    FROM classes c
                    LEFT JOIN inscriptions i ON c.id = i.classe_id AND i.status = 'inscrit'
                    WHERE c.annee_scolaire_id = ?
                    GROUP BY c.id
                    ORDER BY c.niveau, c.nom
                ",
                'params' => ['current_year_id'],
                'charts' => ['bar', 'pie', 'gauge']
            ],
            'student_demographics' => [
                'name' => 'Démographie des élèves',
                'description' => 'Répartition par âge et genre',
                'query' => "
                    SELECT c.niveau,
                           e.sexe,
                           YEAR(CURDATE()) - YEAR(e.date_naissance) as age,
                           COUNT(*) as nombre
                    FROM eleves e
                    JOIN inscriptions i ON e.id = i.eleve_id
                    JOIN classes c ON i.classe_id = c.id
                    WHERE i.status = 'inscrit' AND i.annee_scolaire_id = ?
                    AND e.date_naissance IS NOT NULL
                    GROUP BY c.niveau, e.sexe, age
                    ORDER BY c.niveau, age
                ",
                'params' => ['current_year_id'],
                'charts' => ['pie', 'bar', 'pyramid']
            ]
        ]
    ]
];

// Configuration des graphiques
$chart_types = [
    'bar' => [
        'name' => 'Graphique en barres',
        'icon' => 'fas fa-chart-bar',
        'description' => 'Idéal pour comparer des valeurs',
        'js_type' => 'bar'
    ],
    'line' => [
        'name' => 'Graphique linéaire',
        'icon' => 'fas fa-chart-line',
        'description' => 'Parfait pour montrer l\'évolution',
        'js_type' => 'line'
    ],
    'pie' => [
        'name' => 'Graphique circulaire',
        'icon' => 'fas fa-chart-pie',
        'description' => 'Excellent pour les proportions',
        'js_type' => 'pie'
    ],
    'doughnut' => [
        'name' => 'Graphique en anneau',
        'icon' => 'fas fa-circle-notch',
        'description' => 'Variante du graphique circulaire',
        'js_type' => 'doughnut'
    ],
    'scatter' => [
        'name' => 'Nuage de points',
        'icon' => 'fas fa-braille',
        'description' => 'Pour analyser les corrélations',
        'js_type' => 'scatter'
    ]
];

// Configuration des couleurs pour les graphiques
$chart_colors = [
    'primary' => '#007bff',
    'secondary' => '#6c757d',
    'success' => '#28a745',
    'danger' => '#dc3545',
    'warning' => '#ffc107',
    'info' => '#17a2b8',
    'light' => '#f8f9fa',
    'dark' => '#343a40'
];

// Palette de couleurs étendue pour les graphiques
$extended_colors = [
    '#FF6384', '#36A2EB', '#FFCE56', '#4BC0C0',
    '#9966FF', '#FF9F40', '#FF6384', '#C9CBCF',
    '#4BC0C0', '#FF6384', '#36A2EB', '#FFCE56'
];

// Configuration des permissions d'export
$export_permissions = [
    'academic' => ['admin', 'directeur', 'enseignant'],
    'financial' => ['admin', 'directeur', 'comptable'],
    'administrative' => ['admin', 'directeur', 'secretaire'],
    'custom' => ['admin', 'directeur']
];

// Configuration des limites d'export
$export_limits = [
    'max_rows' => 10000,
    'max_file_size' => 50 * 1024 * 1024, // 50MB
    'timeout' => 300, // 5 minutes
    'concurrent_exports' => 3
];

// Configuration des en-têtes d'export
$export_headers = [
    'school_name' => 'École Primaire et Secondaire',
    'school_address' => 'Kinshasa, République Démocratique du Congo',
    'school_phone' => '+243 XX XXX XXXX',
    'school_email' => 'contact@ecole.cd',
    'logo_path' => '/assets/images/logo.png'
];

// Fonction pour obtenir la configuration d'un format d'export
function getExportFormat($format) {
    global $export_formats;
    return $export_formats[$format] ?? null;
}

// Fonction pour obtenir les modèles de rapports
function getReportTemplates($category = null) {
    global $report_templates;
    
    if ($category) {
        return $report_templates[$category] ?? null;
    }
    
    return $report_templates;
}

// Fonction pour vérifier les permissions d'export
function canExport($category, $user_role) {
    global $export_permissions;
    
    if (!isset($export_permissions[$category])) {
        return false;
    }
    
    return in_array($user_role, $export_permissions[$category]);
}

// Fonction pour obtenir les couleurs de graphique
function getChartColors($count = 1) {
    global $extended_colors;
    
    if ($count <= count($extended_colors)) {
        return array_slice($extended_colors, 0, $count);
    }
    
    // Générer des couleurs supplémentaires si nécessaire
    $colors = $extended_colors;
    while (count($colors) < $count) {
        $colors = array_merge($colors, $extended_colors);
    }
    
    return array_slice($colors, 0, $count);
}

// Fonction pour formater les données pour l'export
function formatDataForExport($data, $format) {
    switch ($format) {
        case 'json':
            return json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            
        case 'csv':
            if (empty($data)) return '';
            
            $output = fopen('php://temp', 'r+');
            
            // En-têtes
            fputcsv($output, array_keys($data[0]));
            
            // Données
            foreach ($data as $row) {
                fputcsv($output, $row);
            }
            
            rewind($output);
            $csv = stream_get_contents($output);
            fclose($output);
            
            return $csv;
            
        default:
            return $data;
    }
}

// Fonction pour générer un nom de fichier d'export
function generateExportFilename($report_name, $format, $timestamp = null) {
    if (!$timestamp) {
        $timestamp = time();
    }
    
    $safe_name = preg_replace('/[^a-zA-Z0-9_-]/', '_', $report_name);
    $date_str = date('Y-m-d_H-i-s', $timestamp);
    
    $extension = getExportFormat($format)['extension'] ?? $format;
    
    return "{$safe_name}_{$date_str}.{$extension}";
}

// Fonction pour valider les données d'export
function validateExportData($data) {
    global $export_limits;
    
    if (empty($data)) {
        return ['valid' => false, 'error' => 'Aucune donnée à exporter'];
    }
    
    if (count($data) > $export_limits['max_rows']) {
        return [
            'valid' => false, 
            'error' => "Trop de lignes à exporter (max: {$export_limits['max_rows']})"
        ];
    }
    
    return ['valid' => true];
}
?>
