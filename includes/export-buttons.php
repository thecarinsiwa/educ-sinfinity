<?php
/**
 * Composant réutilisable pour les boutons d'export et d'impression
 * Application de gestion scolaire - République Démocratique du Congo
 */

/**
 * Afficher les boutons d'export et d'impression
 * 
 * @param array $options Configuration des boutons
 *   - element_id: ID de l'élément à traiter
 *   - table_id: ID du tableau (pour CSV/Excel)
 *   - title: Titre du document
 *   - filename: Nom de base du fichier
 *   - show_print: Afficher le bouton d'impression (défaut: true)
 *   - show_preview: Afficher l'aperçu avant impression (défaut: true)
 *   - show_pdf: Afficher l'export PDF (défaut: true)
 *   - show_excel: Afficher l'export Excel (défaut: true)
 *   - show_csv: Afficher l'export CSV (défaut: true)
 *   - style: Style des boutons ('dropdown', 'buttons', 'toolbar')
 *   - size: Taille des boutons ('sm', 'md', 'lg')
 *   - color: Couleur des boutons ('primary', 'secondary', 'success', etc.)
 */
function renderExportButtons($options = []) {
    // Valeurs par défaut
    $defaults = [
        'element_id' => 'printable-content',
        'table_id' => null,
        'title' => 'Document',
        'filename' => 'export',
        'show_print' => true,
        'show_preview' => true,
        'show_pdf' => true,
        'show_excel' => true,
        'show_csv' => true,
        'style' => 'dropdown',
        'size' => 'md',
        'color' => 'secondary'
    ];
    
    $config = array_merge($defaults, $options);
    
    // Déterminer l'ID du tableau
    $table_id = $config['table_id'] ?: $config['element_id'];
    
    // Classes CSS
    $btn_class = 'btn';
    if ($config['size'] !== 'md') {
        $btn_class .= ' btn-' . $config['size'];
    }
    
    switch ($config['style']) {
        case 'dropdown':
            renderDropdownButtons($config, $btn_class, $table_id);
            break;
            
        case 'buttons':
            renderIndividualButtons($config, $btn_class, $table_id);
            break;
            
        case 'toolbar':
            renderToolbarButtons($config, $btn_class, $table_id);
            break;
            
        default:
            renderDropdownButtons($config, $btn_class, $table_id);
    }
}

/**
 * Rendu en style dropdown
 */
function renderDropdownButtons($config, $btn_class, $table_id) {
    ?>
    <div class="btn-group">
        <button type="button" class="<?php echo $btn_class; ?> btn-outline-<?php echo $config['color']; ?> dropdown-toggle" 
                data-bs-toggle="dropdown" aria-expanded="false">
            <i class="fas fa-download me-1"></i>
            Exporter
        </button>
        <ul class="dropdown-menu">
            <?php if ($config['show_preview']): ?>
                <li>
                    <a class="dropdown-item" href="#" 
                       data-action="print-preview" 
                       data-element-id="<?php echo $config['element_id']; ?>" 
                       data-title="<?php echo htmlspecialchars($config['title']); ?>">
                        <i class="fas fa-eye me-2 text-info"></i>Aperçu avant impression
                    </a>
                </li>
            <?php endif; ?>
            
            <?php if ($config['show_print']): ?>
                <li>
                    <a class="dropdown-item" href="#" 
                       data-action="print" 
                       data-element-id="<?php echo $config['element_id']; ?>" 
                       data-title="<?php echo htmlspecialchars($config['title']); ?>">
                        <i class="fas fa-print me-2 text-primary"></i>Imprimer
                    </a>
                </li>
            <?php endif; ?>
            
            <?php if ($config['show_print'] || $config['show_preview']): ?>
                <li><hr class="dropdown-divider"></li>
            <?php endif; ?>
            
            <?php if ($config['show_pdf']): ?>
                <li>
                    <a class="dropdown-item" href="#" 
                       data-action="export-pdf" 
                       data-element-id="<?php echo $config['element_id']; ?>" 
                       data-filename="<?php echo $config['filename']; ?>" 
                       data-title="<?php echo htmlspecialchars($config['title']); ?>">
                        <i class="fas fa-file-pdf me-2 text-danger"></i>Export PDF
                    </a>
                </li>
            <?php endif; ?>
            
            <?php if ($config['show_excel']): ?>
                <li>
                    <a class="dropdown-item" href="#" 
                       data-action="export-excel" 
                       data-element-id="<?php echo $table_id; ?>" 
                       data-filename="<?php echo $config['filename']; ?>">
                        <i class="fas fa-file-excel me-2 text-success"></i>Export Excel
                    </a>
                </li>
            <?php endif; ?>
            
            <?php if ($config['show_csv']): ?>
                <li>
                    <a class="dropdown-item" href="#" 
                       data-action="export-csv" 
                       data-element-id="<?php echo $table_id; ?>" 
                       data-filename="<?php echo $config['filename']; ?>">
                        <i class="fas fa-file-csv me-2 text-warning"></i>Export CSV
                    </a>
                </li>
            <?php endif; ?>
        </ul>
    </div>
    <?php
}

/**
 * Rendu en boutons individuels
 */
function renderIndividualButtons($config, $btn_class, $table_id) {
    ?>
    <div class="btn-group" role="group">
        <?php if ($config['show_print']): ?>
            <button type="button" class="<?php echo $btn_class; ?> btn-outline-primary" 
                    data-action="print" 
                    data-element-id="<?php echo $config['element_id']; ?>" 
                    data-title="<?php echo htmlspecialchars($config['title']); ?>"
                    title="Imprimer">
                <i class="fas fa-print"></i>
                <span class="d-none d-md-inline ms-1">Imprimer</span>
            </button>
        <?php endif; ?>
        
        <?php if ($config['show_pdf']): ?>
            <button type="button" class="<?php echo $btn_class; ?> btn-outline-danger" 
                    data-action="export-pdf" 
                    data-element-id="<?php echo $config['element_id']; ?>" 
                    data-filename="<?php echo $config['filename']; ?>" 
                    data-title="<?php echo htmlspecialchars($config['title']); ?>"
                    title="Export PDF">
                <i class="fas fa-file-pdf"></i>
                <span class="d-none d-md-inline ms-1">PDF</span>
            </button>
        <?php endif; ?>
        
        <?php if ($config['show_excel']): ?>
            <button type="button" class="<?php echo $btn_class; ?> btn-outline-success" 
                    data-action="export-excel" 
                    data-element-id="<?php echo $table_id; ?>" 
                    data-filename="<?php echo $config['filename']; ?>"
                    title="Export Excel">
                <i class="fas fa-file-excel"></i>
                <span class="d-none d-md-inline ms-1">Excel</span>
            </button>
        <?php endif; ?>
        
        <?php if ($config['show_csv']): ?>
            <button type="button" class="<?php echo $btn_class; ?> btn-outline-warning" 
                    data-action="export-csv" 
                    data-element-id="<?php echo $table_id; ?>" 
                    data-filename="<?php echo $config['filename']; ?>"
                    title="Export CSV">
                <i class="fas fa-file-csv"></i>
                <span class="d-none d-md-inline ms-1">CSV</span>
            </button>
        <?php endif; ?>
    </div>
    <?php
}

/**
 * Rendu en style toolbar
 */
function renderToolbarButtons($config, $btn_class, $table_id) {
    ?>
    <div class="btn-toolbar" role="toolbar">
        <?php if ($config['show_print'] || $config['show_preview']): ?>
            <div class="btn-group me-2" role="group">
                <?php if ($config['show_preview']): ?>
                    <button type="button" class="<?php echo $btn_class; ?> btn-outline-info" 
                            data-action="print-preview" 
                            data-element-id="<?php echo $config['element_id']; ?>" 
                            data-title="<?php echo htmlspecialchars($config['title']); ?>"
                            title="Aperçu">
                        <i class="fas fa-eye"></i>
                    </button>
                <?php endif; ?>
                
                <?php if ($config['show_print']): ?>
                    <button type="button" class="<?php echo $btn_class; ?> btn-outline-primary" 
                            data-action="print" 
                            data-element-id="<?php echo $config['element_id']; ?>" 
                            data-title="<?php echo htmlspecialchars($config['title']); ?>"
                            title="Imprimer">
                        <i class="fas fa-print"></i>
                    </button>
                <?php endif; ?>
            </div>
        <?php endif; ?>
        
        <div class="btn-group" role="group">
            <?php if ($config['show_pdf']): ?>
                <button type="button" class="<?php echo $btn_class; ?> btn-outline-danger" 
                        data-action="export-pdf" 
                        data-element-id="<?php echo $config['element_id']; ?>" 
                        data-filename="<?php echo $config['filename']; ?>" 
                        data-title="<?php echo htmlspecialchars($config['title']); ?>"
                        title="Export PDF">
                    <i class="fas fa-file-pdf"></i>
                </button>
            <?php endif; ?>
            
            <?php if ($config['show_excel']): ?>
                <button type="button" class="<?php echo $btn_class; ?> btn-outline-success" 
                        data-action="export-excel" 
                        data-element-id="<?php echo $table_id; ?>" 
                        data-filename="<?php echo $config['filename']; ?>"
                        title="Export Excel">
                    <i class="fas fa-file-excel"></i>
                </button>
            <?php endif; ?>
            
            <?php if ($config['show_csv']): ?>
                <button type="button" class="<?php echo $btn_class; ?> btn-outline-warning" 
                        data-action="export-csv" 
                        data-element-id="<?php echo $table_id; ?>" 
                        data-filename="<?php echo $config['filename']; ?>"
                        title="Export CSV">
                    <i class="fas fa-file-csv"></i>
                </button>
            <?php endif; ?>
        </div>
    </div>
    <?php
}

/**
 * Fonction raccourci pour afficher les boutons d'export standard
 */
function showExportButtons($element_id, $title, $filename = null, $table_id = null) {
    renderExportButtons([
        'element_id' => $element_id,
        'table_id' => $table_id,
        'title' => $title,
        'filename' => $filename ?: sanitizeFilename($title),
        'style' => 'dropdown'
    ]);
}

/**
 * Fonction raccourci pour afficher les boutons d'impression uniquement
 */
function showPrintButtons($element_id, $title) {
    renderExportButtons([
        'element_id' => $element_id,
        'title' => $title,
        'show_pdf' => false,
        'show_excel' => false,
        'show_csv' => false,
        'style' => 'buttons',
        'color' => 'primary'
    ]);
}

/**
 * Nettoyer un nom de fichier
 */
function sanitizeFilename($filename) {
    // Supprimer les caractères spéciaux et remplacer les espaces par des tirets
    $filename = preg_replace('/[^a-zA-Z0-9\s\-_]/', '', $filename);
    $filename = preg_replace('/\s+/', '-', trim($filename));
    $filename = strtolower($filename);
    
    return $filename;
}
?>
