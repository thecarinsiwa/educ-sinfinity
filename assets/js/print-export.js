/**
 * Fonctions d'impression et d'export pour Educ-Sinfinity
 * Application de gestion scolaire - République Démocratique du Congo
 */

/**
 * Imprimer un élément spécifique de la page
 * @param {string} elementId - ID de l'élément à imprimer
 * @param {string} title - Titre du document à imprimer
 */
function printElement(elementId, title = 'Document') {
    const element = document.getElementById(elementId);
    if (!element) {
        console.error('Élément non trouvé:', elementId);
        alert('Erreur: Élément à imprimer non trouvé');
        return;
    }

    // Créer une nouvelle fenêtre pour l'impression
    const printWindow = window.open('', '_blank', 'width=800,height=600');
    
    if (!printWindow) {
        alert('Erreur: Impossible d\'ouvrir la fenêtre d\'impression. Vérifiez que les popups ne sont pas bloqués.');
        return;
    }

    // Contenu HTML pour l'impression
    const printContent = `
        <!DOCTYPE html>
        <html lang="fr">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>${title} - Educ-Sinfinity</title>
            <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
            <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
            <style>
                @media print {
                    body { 
                        font-size: 12px; 
                        line-height: 1.4;
                        color: #000 !important;
                    }
                    .no-print { display: none !important; }
                    .page-break { page-break-before: always; }
                    .table { border-collapse: collapse !important; }
                    .table th, .table td { 
                        border: 1px solid #000 !important; 
                        padding: 8px !important;
                    }
                    .btn, .dropdown, .pagination { display: none !important; }
                    .card { border: 1px solid #000 !important; box-shadow: none !important; }
                    .alert { border: 1px solid #000 !important; }
                }
                @page {
                    margin: 2cm;
                    size: A4;
                }
                .print-header {
                    text-align: center;
                    margin-bottom: 30px;
                    border-bottom: 2px solid #000;
                    padding-bottom: 20px;
                }
                .print-footer {
                    text-align: center;
                    margin-top: 30px;
                    border-top: 1px solid #000;
                    padding-top: 10px;
                    font-size: 10px;
                    color: #666;
                }
                .logo-print {
                    font-size: 24px;
                    font-weight: bold;
                    color: #000;
                }
            </style>
        </head>
        <body>
            <div class="print-header">
                <div class="logo-print">
                    <i class="fas fa-graduation-cap"></i>
                    EDUC-SINFINITY
                </div>
                <p>Système de Gestion Scolaire - République Démocratique du Congo</p>
                <h3>${title}</h3>
                <p><small>Généré le ${new Date().toLocaleDateString('fr-FR')} à ${new Date().toLocaleTimeString('fr-FR')}</small></p>
            </div>
            
            <div class="print-content">
                ${element.innerHTML}
            </div>
            
            <div class="print-footer">
                <p>Document généré par Educ-Sinfinity - ${new Date().toLocaleDateString('fr-FR')}</p>
            </div>
            
            <script>
                window.onload = function() {
                    // Supprimer les éléments non imprimables
                    const noPrintElements = document.querySelectorAll('.no-print, .btn, .dropdown, .pagination');
                    noPrintElements.forEach(el => el.remove());
                    
                    // Lancer l'impression automatiquement
                    setTimeout(() => {
                        window.print();
                        window.close();
                    }, 500);
                };
            </script>
        </body>
        </html>
    `;

    printWindow.document.write(printContent);
    printWindow.document.close();
}

/**
 * Exporter un tableau en CSV
 * @param {string} tableId - ID du tableau à exporter
 * @param {string} filename - Nom du fichier CSV
 */
function exportToCSV(tableId, filename = 'export.csv') {
    const table = document.getElementById(tableId);
    if (!table) {
        console.error('Tableau non trouvé:', tableId);
        alert('Erreur: Tableau à exporter non trouvé');
        return;
    }

    let csv = [];
    const rows = table.querySelectorAll('tr');

    for (let i = 0; i < rows.length; i++) {
        const row = [];
        const cols = rows[i].querySelectorAll('td, th');

        for (let j = 0; j < cols.length; j++) {
            // Nettoyer le texte et échapper les guillemets
            let cellText = cols[j].innerText.replace(/"/g, '""');
            row.push('"' + cellText + '"');
        }

        csv.push(row.join(','));
    }

    // Créer et télécharger le fichier CSV
    const csvContent = csv.join('\n');
    const blob = new Blob(['\ufeff' + csvContent], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');
    
    if (link.download !== undefined) {
        const url = URL.createObjectURL(blob);
        link.setAttribute('href', url);
        link.setAttribute('download', filename);
        link.style.visibility = 'hidden';
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    } else {
        alert('Export CSV non supporté par ce navigateur');
    }
}

/**
 * Exporter en PDF avec jsPDF
 * @param {string} elementId - ID de l'élément à exporter
 * @param {string} filename - Nom du fichier PDF
 * @param {string} title - Titre du document
 */
function exportToPDF(elementId, filename = 'document.pdf', title = 'Document') {
    // Vérifier si jsPDF est disponible
    if (typeof window.jsPDF === 'undefined') {
        console.error('jsPDF non disponible');
        alert('Erreur: Bibliothèque PDF non chargée. Veuillez recharger la page.');
        return;
    }

    const element = document.getElementById(elementId);
    if (!element) {
        console.error('Élément non trouvé:', elementId);
        alert('Erreur: Élément à exporter non trouvé');
        return;
    }

    // Créer une nouvelle instance jsPDF
    const { jsPDF } = window.jsPDF;
    const pdf = new jsPDF('p', 'mm', 'a4');

    // Ajouter l'en-tête
    pdf.setFontSize(20);
    pdf.text('EDUC-SINFINITY', 105, 20, { align: 'center' });
    
    pdf.setFontSize(12);
    pdf.text('Système de Gestion Scolaire - RDC', 105, 30, { align: 'center' });
    
    pdf.setFontSize(16);
    pdf.text(title, 105, 45, { align: 'center' });
    
    pdf.setFontSize(10);
    const date = new Date().toLocaleDateString('fr-FR');
    pdf.text(`Généré le ${date}`, 105, 55, { align: 'center' });

    // Ligne de séparation
    pdf.line(20, 60, 190, 60);

    // Utiliser html2canvas pour convertir l'élément en image
    if (typeof html2canvas !== 'undefined') {
        html2canvas(element, {
            scale: 2,
            useCORS: true,
            allowTaint: true
        }).then(canvas => {
            const imgData = canvas.toDataURL('image/png');
            const imgWidth = 170; // Largeur en mm
            const pageHeight = 295; // Hauteur de page A4 en mm
            const imgHeight = (canvas.height * imgWidth) / canvas.width;
            let heightLeft = imgHeight;
            let position = 70; // Position après l'en-tête

            // Ajouter l'image au PDF
            pdf.addImage(imgData, 'PNG', 20, position, imgWidth, imgHeight);
            heightLeft -= pageHeight;

            // Ajouter des pages supplémentaires si nécessaire
            while (heightLeft >= 0) {
                position = heightLeft - imgHeight;
                pdf.addPage();
                pdf.addImage(imgData, 'PNG', 20, position, imgWidth, imgHeight);
                heightLeft -= pageHeight;
            }

            // Ajouter le pied de page
            const pageCount = pdf.internal.getNumberOfPages();
            for (let i = 1; i <= pageCount; i++) {
                pdf.setPage(i);
                pdf.setFontSize(8);
                pdf.text(`Page ${i} sur ${pageCount}`, 105, 285, { align: 'center' });
                pdf.text(`Educ-Sinfinity - ${date}`, 105, 290, { align: 'center' });
            }

            // Sauvegarder le PDF
            pdf.save(filename);
        }).catch(error => {
            console.error('Erreur lors de la génération du PDF:', error);
            alert('Erreur lors de la génération du PDF');
        });
    } else {
        // Fallback sans html2canvas - texte simple
        pdf.setFontSize(12);
        const text = element.innerText;
        const lines = pdf.splitTextToSize(text, 170);
        pdf.text(lines, 20, 70);
        pdf.save(filename);
    }
}

/**
 * Exporter un tableau en Excel (format CSV avec extension .xls)
 * @param {string} tableId - ID du tableau à exporter
 * @param {string} filename - Nom du fichier Excel
 */
function exportToExcel(tableId, filename = 'export.xls') {
    const table = document.getElementById(tableId);
    if (!table) {
        console.error('Tableau non trouvé:', tableId);
        alert('Erreur: Tableau à exporter non trouvé');
        return;
    }

    // Créer le contenu HTML du tableau
    let html = '<table>';
    const rows = table.querySelectorAll('tr');

    for (let i = 0; i < rows.length; i++) {
        html += '<tr>';
        const cols = rows[i].querySelectorAll('td, th');

        for (let j = 0; j < cols.length; j++) {
            const cellText = cols[j].innerText;
            const tag = cols[j].tagName.toLowerCase();
            html += `<${tag}>${cellText}</${tag}>`;
        }

        html += '</tr>';
    }
    html += '</table>';

    // Créer le blob avec le type Excel
    const blob = new Blob([html], {
        type: 'application/vnd.ms-excel'
    });

    // Télécharger le fichier
    const link = document.createElement('a');
    if (link.download !== undefined) {
        const url = URL.createObjectURL(blob);
        link.setAttribute('href', url);
        link.setAttribute('download', filename);
        link.style.visibility = 'hidden';
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    } else {
        alert('Export Excel non supporté par ce navigateur');
    }
}

/**
 * Afficher un aperçu avant impression
 * @param {string} elementId - ID de l'élément à prévisualiser
 * @param {string} title - Titre du document
 */
function showPrintPreview(elementId, title = 'Aperçu') {
    const element = document.getElementById(elementId);
    if (!element) {
        console.error('Élément non trouvé:', elementId);
        return;
    }

    // Créer une modal pour l'aperçu
    const modal = document.createElement('div');
    modal.className = 'modal fade';
    modal.id = 'printPreviewModal';
    modal.innerHTML = `
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-print me-2"></i>
                        Aperçu avant impression - ${title}
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" style="max-height: 70vh; overflow-y: auto;">
                    <div class="print-preview-content">
                        ${element.innerHTML}
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times me-1"></i>
                        Annuler
                    </button>
                    <button type="button" class="btn btn-primary" onclick="printElement('${elementId}', '${title}')">
                        <i class="fas fa-print me-1"></i>
                        Imprimer
                    </button>
                </div>
            </div>
        </div>
    `;

    document.body.appendChild(modal);

    // Afficher la modal
    const bootstrapModal = new bootstrap.Modal(modal);
    bootstrapModal.show();

    // Supprimer la modal après fermeture
    modal.addEventListener('hidden.bs.modal', () => {
        document.body.removeChild(modal);
    });
}

/**
 * Initialiser les boutons d'export/impression
 */
function initializePrintExportButtons() {
    // Ajouter les événements aux boutons existants
    document.addEventListener('click', function(e) {
        const target = e.target.closest('[data-action]');
        if (!target) return;

        const action = target.getAttribute('data-action');
        const elementId = target.getAttribute('data-element-id');
        const filename = target.getAttribute('data-filename') || 'export';
        const title = target.getAttribute('data-title') || 'Document';

        switch (action) {
            case 'print':
                printElement(elementId, title);
                break;
            case 'print-preview':
                showPrintPreview(elementId, title);
                break;
            case 'export-csv':
                exportToCSV(elementId, filename + '.csv');
                break;
            case 'export-excel':
                exportToExcel(elementId, filename + '.xls');
                break;
            case 'export-pdf':
                exportToPDF(elementId, filename + '.pdf', title);
                break;
        }
    });
}

// Initialiser au chargement de la page
document.addEventListener('DOMContentLoaded', initializePrintExportButtons);
