# 📄 Documentation - Impression et Export

## 🎯 Vue d'ensemble

Le système d'impression et d'export d'Educ-Sinfinity permet de générer des documents professionnels dans plusieurs formats :
- **Impression** directe avec mise en page optimisée
- **Export PDF** avec jsPDF et html2canvas
- **Export Excel** (.xls) compatible
- **Export CSV** avec encodage UTF-8
- **Aperçu avant impression** dans une modal

## 📁 Structure des fichiers

```
assets/
├── js/
│   └── print-export.js          # Fonctions JavaScript principales
└── css/
    └── print.css                # Styles d'impression

includes/
├── export-buttons.php           # Composant PHP réutilisable
├── header.php                   # Inclut les bibliothèques
└── footer.php                   # Charge les scripts

docs/
└── PRINT-EXPORT.md             # Cette documentation

examples/
├── example-print-export.php     # Exemple complet
└── example-export-component.php # Exemple du composant
```

## 🚀 Installation et Configuration

### 1. Bibliothèques incluses automatiquement

Les bibliothèques suivantes sont chargées dans `includes/footer.php` :
- **jsPDF** 2.5.1 - Génération PDF
- **html2canvas** 1.4.1 - Capture d'écran HTML
- **Bootstrap** 5.3.0 - Interface utilisateur
- **Font Awesome** 6.4.0 - Icônes

### 2. Fichiers CSS

Le fichier `assets/css/print.css` est automatiquement inclus avec `media="print"`.

## 📝 Utilisation

### 1. Méthode simple avec le composant PHP

```php
<?php
require_once 'includes/export-buttons.php';

// Utilisation basique
showExportButtons('mon-element', 'Mon Document', 'mon-fichier', 'mon-tableau');

// Impression uniquement
showPrintButtons('mon-element', 'Mon Document');
?>
```

### 2. Configuration avancée

```php
<?php
renderExportButtons([
    'element_id' => 'rapport-content',
    'table_id' => 'rapport-table',
    'title' => 'Rapport Mensuel',
    'filename' => 'rapport-mensuel',
    'show_print' => true,
    'show_preview' => true,
    'show_pdf' => true,
    'show_excel' => true,
    'show_csv' => true,
    'style' => 'dropdown',  // 'dropdown', 'buttons', 'toolbar'
    'size' => 'md',         // 'sm', 'md', 'lg'
    'color' => 'secondary'  // 'primary', 'secondary', 'success', etc.
]);
?>
```

### 3. Utilisation avec attributs data

```html
<!-- Bouton d'impression -->
<button type="button" class="btn btn-primary" 
        data-action="print" 
        data-element-id="printable-content" 
        data-title="Mon Document">
    <i class="fas fa-print"></i> Imprimer
</button>

<!-- Bouton d'export PDF -->
<button type="button" class="btn btn-danger" 
        data-action="export-pdf" 
        data-element-id="printable-content" 
        data-filename="document" 
        data-title="Mon Document">
    <i class="fas fa-file-pdf"></i> Export PDF
</button>

<!-- Bouton d'export Excel -->
<button type="button" class="btn btn-success" 
        data-action="export-excel" 
        data-element-id="mon-tableau" 
        data-filename="donnees">
    <i class="fas fa-file-excel"></i> Export Excel
</button>
```

### 4. Appels JavaScript directs

```javascript
// Impression
printElement('printable-content', 'Mon Document');

// Aperçu avant impression
showPrintPreview('printable-content', 'Mon Document');

// Export PDF
exportToPDF('printable-content', 'document.pdf', 'Mon Document');

// Export Excel
exportToExcel('mon-tableau', 'donnees.xls');

// Export CSV
exportToCSV('mon-tableau', 'donnees.csv');
```

## 🎨 Styles et Classes CSS

### Classes d'impression

```css
.no-print          /* Masquer à l'impression */
.print-only        /* Afficher uniquement à l'impression */
.page-break        /* Saut de page avant */
.page-break-after  /* Saut de page après */
.page-break-inside-avoid /* Éviter les coupures */
```

### Exemple d'utilisation

```html
<div id="printable-content">
    <h1>Mon Rapport</h1>
    
    <div class="no-print">
        <button class="btn btn-primary">Ce bouton ne s'imprime pas</button>
    </div>
    
    <table class="table">
        <thead>
            <tr>
                <th>Nom</th>
                <th>Prénom</th>
                <th class="no-print">Actions</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>Doe</td>
                <td>John</td>
                <td class="no-print">
                    <button class="btn btn-sm btn-outline-primary">Éditer</button>
                </td>
            </tr>
        </tbody>
    </table>
    
    <div class="page-break">
        <h2>Section suivante (nouvelle page)</h2>
    </div>
</div>
```

## ⚙️ Configuration des options

### Options du composant `renderExportButtons()`

| Option | Type | Défaut | Description |
|--------|------|--------|-------------|
| `element_id` | string | 'printable-content' | ID de l'élément à traiter |
| `table_id` | string | null | ID du tableau (pour CSV/Excel) |
| `title` | string | 'Document' | Titre du document |
| `filename` | string | 'export' | Nom de base du fichier |
| `show_print` | boolean | true | Afficher le bouton d'impression |
| `show_preview` | boolean | true | Afficher l'aperçu avant impression |
| `show_pdf` | boolean | true | Afficher l'export PDF |
| `show_excel` | boolean | true | Afficher l'export Excel |
| `show_csv` | boolean | true | Afficher l'export CSV |
| `style` | string | 'dropdown' | Style des boutons |
| `size` | string | 'md' | Taille des boutons |
| `color` | string | 'secondary' | Couleur des boutons |

### Styles disponibles

1. **dropdown** - Menu déroulant compact
2. **buttons** - Boutons individuels côte à côte
3. **toolbar** - Groupes de boutons organisés

## 🔧 Fonctions JavaScript disponibles

### `printElement(elementId, title)`
Imprime un élément spécifique avec en-tête et pied de page.

### `showPrintPreview(elementId, title)`
Affiche un aperçu avant impression dans une modal Bootstrap.

### `exportToPDF(elementId, filename, title)`
Exporte en PDF avec jsPDF et html2canvas.

### `exportToExcel(tableId, filename)`
Exporte un tableau en format Excel (.xls).

### `exportToCSV(tableId, filename)`
Exporte un tableau en format CSV avec encodage UTF-8.

## 📋 Exemples pratiques

### 1. Page de rapport

```php
<?php
$page_title = 'Rapport Mensuel';
include 'includes/header.php';
require_once 'includes/export-buttons.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h1>Rapport Mensuel</h1>
    <?php showExportButtons('rapport-content', 'Rapport Mensuel', 'rapport-mensuel', 'rapport-table'); ?>
</div>

<div id="rapport-content">
    <!-- Contenu du rapport -->
</div>
```

### 2. Liste d'élèves

```php
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5>Liste des Élèves</h5>
        <?php 
        renderExportButtons([
            'element_id' => 'eleves-list',
            'table_id' => 'eleves-table',
            'title' => 'Liste des Élèves - ' . $classe['nom'],
            'filename' => 'eleves-' . sanitizeFilename($classe['nom']),
            'style' => 'buttons',
            'size' => 'sm'
        ]);
        ?>
    </div>
    <div class="card-body" id="eleves-list">
        <table class="table" id="eleves-table">
            <!-- Tableau des élèves -->
        </table>
    </div>
</div>
```

### 3. Bulletin de notes

```php
<div id="bulletin-<?php echo $eleve['id']; ?>" class="bulletin">
    <div class="print-header">
        <div class="logo">EDUC-SINFINITY</div>
        <div class="subtitle">Bulletin de Notes</div>
        <div class="title"><?php echo $eleve['nom'] . ' ' . $eleve['prenom']; ?></div>
    </div>
    
    <!-- Contenu du bulletin -->
    
    <div class="signature-section no-print">
        <?php showPrintButtons('bulletin-' . $eleve['id'], 'Bulletin - ' . $eleve['nom']); ?>
    </div>
</div>
```

## 🐛 Dépannage

### Problèmes courants

1. **PDF vide ou incomplet**
   - Vérifiez que jsPDF et html2canvas sont chargés
   - Assurez-vous que l'élément existe et est visible

2. **Styles manquants à l'impression**
   - Vérifiez que `print.css` est inclus
   - Utilisez `!important` pour forcer les styles

3. **Export Excel ne fonctionne pas**
   - Vérifiez que l'élément est bien un tableau
   - Assurez-vous que le navigateur supporte les téléchargements

### Vérification des dépendances

```javascript
// Console du navigateur
console.log('jQuery:', typeof $ !== 'undefined');
console.log('Bootstrap:', typeof bootstrap !== 'undefined');
console.log('jsPDF:', typeof window.jsPDF !== 'undefined');
console.log('html2canvas:', typeof html2canvas !== 'undefined');
```

## 🔄 Mises à jour et maintenance

### Mise à jour des bibliothèques

Pour mettre à jour les bibliothèques, modifiez les URLs dans `includes/footer.php` :

```php
<!-- jsPDF -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>

<!-- html2canvas -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
```

### Personnalisation

1. **Modifier les styles d'impression** : Éditez `assets/css/print.css`
2. **Ajouter de nouveaux formats** : Étendez `assets/js/print-export.js`
3. **Personnaliser les boutons** : Modifiez `includes/export-buttons.php`

---

**Développé pour Educ-Sinfinity - Système de Gestion Scolaire RDC** 🇨🇩
