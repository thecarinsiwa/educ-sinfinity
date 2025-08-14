/**
 * JavaScript principal pour Educ-Sinfinity
 * Application de gestion scolaire - République Démocratique du Congo
 */

// Configuration globale
const App = {
    config: {
        baseUrl: window.location.origin + '/educ-sinfinity',
        apiUrl: window.location.origin + '/educ-sinfinity/api',
        locale: 'fr-CD',
        currency: 'CDF',
        dateFormat: 'dd/mm/yyyy'
    },
    
    // Initialisation de l'application
    init: function() {
        this.initDataTables();
        this.initSidebar();
        this.initFormValidation();
        this.initFileUploads();
        this.initTooltips();
        this.initConfirmDialogs();
        this.initAutoSave();
        this.initSearchFilters();
    },
    
    // Configuration des DataTables
    initDataTables: function() {
        if (typeof $.fn.DataTable !== 'undefined') {
            $.extend(true, $.fn.dataTable.defaults, {
                language: {
                    url: 'https://cdn.datatables.net/plug-ins/1.13.6/i18n/fr-FR.json'
                },
                responsive: true,
                pageLength: 25,
                lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, "Tout"]],
                dom: '<"row"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6"f>>' +
                     '<"row"<"col-sm-12"tr>>' +
                     '<"row"<"col-sm-12 col-md-5"i><"col-sm-12 col-md-7"p>>',
                order: [[0, 'desc']],
                columnDefs: [
                    { targets: 'no-sort', orderable: false },
                    { targets: 'text-center', className: 'text-center' },
                    { targets: 'text-right', className: 'text-end' }
                ]
            });
            
            // Initialiser toutes les tables avec la classe 'datatable'
            $('.datatable').each(function() {
                if (!$.fn.DataTable.isDataTable(this)) {
                    $(this).DataTable();
                }
            });
        }
    },
    
    // Gestion du sidebar responsive
    initSidebar: function() {
        const sidebarToggle = document.getElementById('sidebarToggle');
        const sidebar = document.getElementById('sidebar');
        
        if (sidebarToggle && sidebar) {
            sidebarToggle.addEventListener('click', function() {
                sidebar.classList.toggle('show');
            });
            
            // Fermer le sidebar en cliquant à l'extérieur sur mobile
            document.addEventListener('click', function(e) {
                if (window.innerWidth <= 768) {
                    if (!sidebar.contains(e.target) && !sidebarToggle.contains(e.target)) {
                        sidebar.classList.remove('show');
                    }
                }
            });
        }
    },
    
    // Validation des formulaires
    initFormValidation: function() {
        const forms = document.querySelectorAll('.needs-validation');
        
        forms.forEach(function(form) {
            form.addEventListener('submit', function(event) {
                if (!form.checkValidity()) {
                    event.preventDefault();
                    event.stopPropagation();
                    
                    // Afficher un message d'erreur
                    App.showAlert('error', 'Veuillez remplir tous les champs obligatoires.');
                }
                
                form.classList.add('was-validated');
            });
        });
        
        // Validation en temps réel
        document.querySelectorAll('input[required], select[required], textarea[required]').forEach(function(field) {
            field.addEventListener('blur', function() {
                if (this.value.trim() === '') {
                    this.classList.add('is-invalid');
                } else {
                    this.classList.remove('is-invalid');
                    this.classList.add('is-valid');
                }
            });
        });
    },
    
    // Gestion des uploads de fichiers
    initFileUploads: function() {
        // Preview des images
        document.querySelectorAll('input[type="file"][accept*="image"]').forEach(function(input) {
            input.addEventListener('change', function(e) {
                const file = e.target.files[0];
                if (file) {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        let preview = input.parentNode.querySelector('.image-preview');
                        if (!preview) {
                            preview = document.createElement('img');
                            preview.className = 'image-preview img-thumbnail mt-2';
                            preview.style.maxWidth = '200px';
                            input.parentNode.appendChild(preview);
                        }
                        preview.src = e.target.result;
                    };
                    reader.readAsDataURL(file);
                }
            });
        });
        
        // Validation de la taille des fichiers
        document.querySelectorAll('input[type="file"]').forEach(function(input) {
            input.addEventListener('change', function() {
                const maxSize = 5 * 1024 * 1024; // 5MB
                const file = this.files[0];
                
                if (file && file.size > maxSize) {
                    App.showAlert('error', 'Le fichier est trop volumineux. Taille maximale : 5MB');
                    this.value = '';
                }
            });
        });
    },
    
    // Initialiser les tooltips Bootstrap
    initTooltips: function() {
        if (typeof bootstrap !== 'undefined') {
            const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            tooltipTriggerList.map(function(tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });
        }
    },
    
    // Dialogues de confirmation
    initConfirmDialogs: function() {
        document.querySelectorAll('.btn-delete, .confirm-action').forEach(function(btn) {
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                
                const url = this.getAttribute('href') || this.dataset.url;
                const itemName = this.dataset.name || 'cet élément';
                const action = this.dataset.action || 'supprimer';
                
                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        title: 'Êtes-vous sûr ?',
                        text: `Voulez-vous vraiment ${action} ${itemName} ?`,
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonColor: '#e74c3c',
                        cancelButtonColor: '#95a5a6',
                        confirmButtonText: `Oui, ${action}`,
                        cancelButtonText: 'Annuler'
                    }).then((result) => {
                        if (result.isConfirmed) {
                            if (url) {
                                window.location.href = url;
                            } else if (this.tagName === 'BUTTON' && this.form) {
                                this.form.submit();
                            }
                        }
                    });
                } else {
                    if (confirm(`Voulez-vous vraiment ${action} ${itemName} ?`)) {
                        if (url) {
                            window.location.href = url;
                        } else if (this.tagName === 'BUTTON' && this.form) {
                            this.form.submit();
                        }
                    }
                }
            });
        });
    },
    
    // Sauvegarde automatique des formulaires
    initAutoSave: function() {
        const autoSaveForms = document.querySelectorAll('.auto-save');
        
        autoSaveForms.forEach(function(form) {
            const inputs = form.querySelectorAll('input, select, textarea');
            
            inputs.forEach(function(input) {
                input.addEventListener('change', function() {
                    App.autoSaveForm(form);
                });
            });
        });
    },
    
    // Filtres de recherche en temps réel
    initSearchFilters: function() {
        const searchInputs = document.querySelectorAll('.live-search');
        
        searchInputs.forEach(function(input) {
            let timeout;
            input.addEventListener('input', function() {
                clearTimeout(timeout);
                timeout = setTimeout(() => {
                    App.performLiveSearch(this);
                }, 300);
            });
        });
    },
    
    // Fonctions utilitaires
    showAlert: function(type, message, duration = 5000) {
        if (typeof Swal !== 'undefined') {
            const config = {
                title: type === 'success' ? 'Succès' : type === 'error' ? 'Erreur' : 'Information',
                text: message,
                icon: type,
                timer: duration,
                showConfirmButton: false
            };
            
            if (type === 'error') {
                config.timer = null;
                config.showConfirmButton = true;
            }
            
            Swal.fire(config);
        } else {
            alert(message);
        }
    },
    
    // Formatage des montants
    formatMoney: function(amount) {
        return new Intl.NumberFormat('fr-CD', {
            style: 'currency',
            currency: 'CDF',
            minimumFractionDigits: 0
        }).format(amount);
    },
    
    // Formatage des dates
    formatDate: function(date, format = 'dd/mm/yyyy') {
        const d = new Date(date);
        const day = String(d.getDate()).padStart(2, '0');
        const month = String(d.getMonth() + 1).padStart(2, '0');
        const year = d.getFullYear();
        
        switch (format) {
            case 'dd/mm/yyyy':
                return `${day}/${month}/${year}`;
            case 'yyyy-mm-dd':
                return `${year}-${month}-${day}`;
            default:
                return d.toLocaleDateString('fr-FR');
        }
    },
    
    // Calcul de l'âge
    calculateAge: function(birthDate) {
        const today = new Date();
        const birth = new Date(birthDate);
        let age = today.getFullYear() - birth.getFullYear();
        const monthDiff = today.getMonth() - birth.getMonth();
        
        if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < birth.getDate())) {
            age--;
        }
        
        return age;
    },
    
    // Génération de matricule
    generateMatricule: function(prefix = 'STU') {
        const year = new Date().getFullYear();
        const random = Math.floor(Math.random() * 9999).toString().padStart(4, '0');
        return prefix + year + random;
    },
    
    // Sauvegarde automatique
    autoSaveForm: function(form) {
        const formData = new FormData(form);
        const url = form.dataset.autoSaveUrl || form.action;
        
        if (url) {
            fetch(url, {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Auto-Save': '1'
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Afficher un indicateur de sauvegarde
                    this.showSaveIndicator();
                }
            })
            .catch(error => {
                console.error('Erreur de sauvegarde automatique:', error);
            });
        }
    },
    
    // Indicateur de sauvegarde
    showSaveIndicator: function() {
        let indicator = document.getElementById('save-indicator');
        if (!indicator) {
            indicator = document.createElement('div');
            indicator.id = 'save-indicator';
            indicator.className = 'position-fixed top-0 end-0 m-3 alert alert-success alert-dismissible';
            indicator.innerHTML = '<i class="fas fa-check me-2"></i>Sauvegardé automatiquement';
            document.body.appendChild(indicator);
        }
        
        indicator.style.display = 'block';
        setTimeout(() => {
            indicator.style.display = 'none';
        }, 2000);
    },
    
    // Recherche en temps réel
    performLiveSearch: function(input) {
        const query = input.value;
        const targetTable = document.querySelector(input.dataset.target);
        
        if (targetTable && $.fn.DataTable.isDataTable(targetTable)) {
            $(targetTable).DataTable().search(query).draw();
        }
    },
    
    // Impression
    printElement: function(elementId) {
        const element = document.getElementById(elementId);
        if (!element) return;
        
        const printWindow = window.open('', '_blank');
        printWindow.document.write(`
            <html>
                <head>
                    <title>Impression</title>
                    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
                    <style>
                        @media print {
                            .no-print { display: none !important; }
                            body { font-size: 12px; }
                            .container { max-width: none; }
                        }
                    </style>
                </head>
                <body>
                    <div class="container">
                        ${element.innerHTML}
                    </div>
                    <script>
                        window.onload = function() {
                            window.print();
                            window.close();
                        }
                    </script>
                </body>
            </html>
        `);
        printWindow.document.close();
    }
};

// Initialisation au chargement de la page
document.addEventListener('DOMContentLoaded', function() {
    App.init();
});

// Fonctions globales pour compatibilité
function showSuccess(message) {
    App.showAlert('success', message);
}

function showError(message) {
    App.showAlert('error', message);
}

function showWarning(message) {
    App.showAlert('warning', message);
}

function formatMoney(amount) {
    return App.formatMoney(amount);
}

function formatDate(date) {
    return App.formatDate(date);
}

function printElement(elementId) {
    App.printElement(elementId);
}
