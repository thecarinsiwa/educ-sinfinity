            </main>
        </div>
    </div>
    
    <!-- Footer -->
    <footer class="bg-light text-center text-muted py-3 mt-5">
        <div class="container">
            <p class="mb-1">&copy; <?php echo date('Y'); ?> <?php echo APP_NAME; ?> - Système de gestion scolaire</p>
            <p class="mb-0">
                <small>Version <?php echo APP_VERSION; ?> | République Démocratique du Congo</small>
            </p>
        </div>
    </footer>
    
    <!-- Scripts JavaScript -->
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- DataTables JS -->
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
    
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <!-- Bibliothèques pour l'export PDF -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>

    <!-- Custom JavaScript -->
    <script src="<?php echo APP_URL; ?>/assets/js/app.js"></script>

    <!-- Script d'impression et export -->
    <script src="<?php echo APP_URL; ?>/assets/js/print-export.js"></script>
    <script>
        // Configuration globale
        $(document).ready(function() {
            // Initialiser DataTables avec configuration française
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
                order: [[0, 'desc']]
            });
            
            // Initialiser toutes les tables avec la classe 'datatable'
            $('.datatable').DataTable();
            
            // Toggle sidebar sur mobile
            $('#sidebarToggle').click(function() {
                $('#sidebar').toggleClass('show');
            });
            
            // Fermer sidebar en cliquant à l'extérieur sur mobile
            $(document).click(function(e) {
                if (!$(e.target).closest('#sidebar, #sidebarToggle').length) {
                    $('#sidebar').removeClass('show');
                }
            });
            
            // Confirmation de suppression
            $('.btn-delete').click(function(e) {
                e.preventDefault();
                const url = $(this).attr('href');
                const itemName = $(this).data('name') || 'cet élément';
                
                Swal.fire({
                    title: 'Êtes-vous sûr ?',
                    text: `Voulez-vous vraiment supprimer ${itemName} ?`,
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#e74c3c',
                    cancelButtonColor: '#95a5a6',
                    confirmButtonText: 'Oui, supprimer',
                    cancelButtonText: 'Annuler'
                }).then((result) => {
                    if (result.isConfirmed) {
                        window.location.href = url;
                    }
                });
            });
            
            // Auto-hide alerts après 5 secondes
            $('.alert').delay(5000).fadeOut();
            
            // Validation des formulaires
            $('form').submit(function() {
                const requiredFields = $(this).find('[required]');
                let isValid = true;
                
                requiredFields.each(function() {
                    if (!$(this).val().trim()) {
                        $(this).addClass('is-invalid');
                        isValid = false;
                    } else {
                        $(this).removeClass('is-invalid');
                    }
                });
                
                if (!isValid) {
                    Swal.fire({
                        title: 'Erreur',
                        text: 'Veuillez remplir tous les champs obligatoires',
                        icon: 'error'
                    });
                    return false;
                }
            });
            
            // Formatage automatique des numéros de téléphone
            $('input[type="tel"]').on('input', function() {
                let value = $(this).val().replace(/\D/g, '');
                if (value.startsWith('243')) {
                    value = '+' + value;
                } else if (value.startsWith('0')) {
                    // Format local
                }
                $(this).val(value);
            });
            
            // Preview des images avant upload
            $('input[type="file"][accept*="image"]').change(function() {
                const file = this.files[0];
                if (file) {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        const preview = $(this).siblings('.image-preview');
                        if (preview.length) {
                            preview.attr('src', e.target.result).show();
                        } else {
                            $(this).after(`<img src="${e.target.result}" class="image-preview img-thumbnail mt-2" style="max-width: 200px;">`);
                        }
                    }.bind(this);
                    reader.readAsDataURL(file);
                }
            });
            
            // Calculateur d'âge automatique
            $('input[name="date_naissance"]').change(function() {
                const birthDate = new Date($(this).val());
                const today = new Date();
                const age = today.getFullYear() - birthDate.getFullYear();
                const monthDiff = today.getMonth() - birthDate.getMonth();
                
                if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < birthDate.getDate())) {
                    age--;
                }
                
                const ageField = $('input[name="age"], .age-display');
                if (ageField.length) {
                    if (ageField.is('input')) {
                        ageField.val(age);
                    } else {
                        ageField.text(age + ' ans');
                    }
                }
            });
            
            // Génération automatique de matricule
            $('.generate-matricule').click(function() {
                const prefix = $(this).data('prefix') || 'STU';
                const year = new Date().getFullYear();
                const random = Math.floor(Math.random() * 9999).toString().padStart(4, '0');
                const matricule = prefix + year + random;
                
                $(this).siblings('input[name*="matricule"]').val(matricule);
            });
        });
        
        // Fonctions utilitaires
        function showSuccess(message) {
            Swal.fire({
                title: 'Succès',
                text: message,
                icon: 'success',
                timer: 3000,
                showConfirmButton: false
            });
        }
        
        function showError(message) {
            Swal.fire({
                title: 'Erreur',
                text: message,
                icon: 'error'
            });
        }
        
        function showWarning(message) {
            Swal.fire({
                title: 'Attention',
                text: message,
                icon: 'warning'
            });
        }
        
        function formatMoney(amount) {
            return new Intl.NumberFormat('fr-CD', {
                style: 'currency',
                currency: 'CDF',
                minimumFractionDigits: 0
            }).format(amount);
        }
        
        function formatDate(date) {
            return new Date(date).toLocaleDateString('fr-FR');
        }
        
        // Les fonctions d'impression et d'export sont maintenant dans print-export.js
        // Fonctions disponibles :
        // - printElement(elementId, title)
        // - exportToPDF(elementId, filename, title)
        // - exportToCSV(tableId, filename)
        // - exportToExcel(tableId, filename)
        // - showPrintPreview(elementId, title)
    </script>
    
    <!-- Scripts personnalisés de la page -->
    <?php if (isset($custom_js)): ?>
        <script><?php echo $custom_js; ?></script>
    <?php endif; ?>
    
    <?php if (isset($js_files)): ?>
        <?php foreach ($js_files as $js_file): ?>
            <script src="<?php echo $js_file; ?>"></script>
        <?php endforeach; ?>
    <?php endif; ?>

    <!-- Modal de déconnexion -->
    <div class="modal fade" id="logoutModal" tabindex="-1" aria-labelledby="logoutModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title" id="logoutModalLabel">
                        <i class="fas fa-sign-out-alt me-2"></i>
                        Confirmation de déconnexion
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Êtes-vous sûr de vouloir vous déconnecter ?</p>
                    <p class="text-muted small">
                        <i class="fas fa-info-circle me-1"></i>
                        Votre session sera fermée et vous devrez vous reconnecter pour accéder à l'application.
                    </p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times me-1"></i>
                        Annuler
                    </button>
                    <a href="<?php echo APP_URL; ?>/auth/logout.php" class="btn btn-danger">
                        <i class="fas fa-sign-out-alt me-1"></i>
                        Se déconnecter
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Script pour initialiser le modal de déconnexion -->
    <script>
        $(document).ready(function() {
            // Initialiser le modal de déconnexion
            const logoutModal = new bootstrap.Modal(document.getElementById('logoutModal'));
            
            // Test du modal (pour debug)
            console.log('✅ Modal de déconnexion initialisé');
            
            // Vérifier que Bootstrap est chargé
            if (typeof bootstrap !== 'undefined') {
                console.log('✅ Bootstrap est disponible');
            } else {
                console.error('❌ Bootstrap n\'est pas disponible');
            }
        });
    </script>
</body>
</html>
