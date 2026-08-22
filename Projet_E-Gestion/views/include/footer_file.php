<script>
    document.addEventListener("DOMContentLoaded", function () {
        // Function to check if all required fields are filled
        function areFieldsComplete(form) {
            let isComplete = true;
            form.querySelectorAll("input[required], textarea[required], select[required]").forEach(field => {
                if (!field.value.trim()) {
                    isComplete = false;
                }
            });
            return isComplete;
        }

        const preloader = document.getElementById("preloader");

        // Masquer le preloader après le chargement de la page
        setTimeout(() => {
            if (preloader) {
                preloader.style.opacity = "0";
                setTimeout(() => {
                    preloader.style.display = "none";
                }, 500);
            }
        }, 500); // Petite attente pour éviter un clignotement

        // Sélectionne tous les formulaires de la page
        const forms = document.querySelectorAll("form");
        
        forms.forEach(form => {
            form.addEventListener("submit", function(event) {
                // Check if all required fields are complete
                if (!areFieldsComplete(form)) {
                    event.preventDefault(); // Prevent form submission
                    
                    if (typeof Swal !== 'undefined') {
                        Swal.fire({
                            icon: 'warning',
                            title: 'Champs incomplets',
                            text: 'Veuillez remplir tous les champs requis.',
                            confirmButtonText: 'OK'
                        });
                    } else {
                        alert('Veuillez remplir tous les champs requis.');
                    }
                    return;
                }

                
            });
        });
    });
</script>

<script>
    // Afficher le spinner
    window.onload = function() {
      const spinner = document.getElementById('spinner');
      if (spinner) {
          spinner.style.display = 'none';
      }
    };
</script>

<!-- ======= Footer ======= -->
<footer id="footer" class="footer bg-light border-top">
    <div class="container-fluid py-3">
        <div class="row align-items-center">
            <div class="col-12">
                <div class="text-center footer-info">
                    <?php if ($config): ?>
                        <h6 class="mb-2" style="font-weight: 600;">
                            <?= htmlspecialchars($config['nom'] ?? 'Université') ?>
                        </h6>
                        <div style="font-size: 0.85rem; color: #666;">
                            <?php if (!empty($config['adresse'])): ?>
                                <div class="mb-1">
                                    <i class="bi bi-geo-alt"></i> 
                                    <?= htmlspecialchars($config['adresse']) ?>
                                    <?php if (!empty($config['ville'])): ?>
                                        , <?= htmlspecialchars($config['ville']) ?>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                            
                            <div class="mb-1">
                                <?php if (!empty($config['telephone'])): ?>
                                    <i class="bi bi-telephone"></i> 
                                    <?= htmlspecialchars($config['telephone']) ?>
                                    <?php if (!empty($config['email'])): ?>
                                        | 
                                    <?php endif; ?>
                                <?php endif; ?>
                                
                                <?php if (!empty($config['email'])): ?>
                                    <i class="bi bi-envelope"></i> 
                                    <?= htmlspecialchars($config['email']) ?>
                                <?php endif; ?>
                            </div>
                            
                            <?php if (!empty($config['site_web'])): ?>
                                <div>
                                    <i class="bi bi-globe"></i> 
                                    <?= htmlspecialchars($config['site_web']) ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                    <div class="mt-3 pt-2 border-top">
                        <small class="text-muted">
                            Copyright &copy; <strong><?php echo htmlspecialchars($config['nom_application'] ?? 'E-GESTION'); ?></strong>. Tous les droits sont réservés
                        </small>
                    </div>
                </div>
            </div>
        </div>
    </div>
</footer>
<!-- End Footer -->

<a id="back-to-top" class="back-to-top d-flex align-items-center justify-content-center"><i class="bi bi-arrow-up-short"></i></a>

<script>
    // Fonction pour faire défiler vers le haut
    function scrollToTop() {
        window.scrollTo({
            top: 0,
            behavior: 'smooth' // Défilement en douceur
        });
    }

    // Ajouter un écouteur d'événement au bouton "back-to-top"
    const backToTopButton = document.getElementById('back-to-top');
    if (backToTopButton) {
        backToTopButton.addEventListener('click', scrollToTop);
    }
</script>

<!-- Vendor JS Files - Ne pas recharger jQuery ici -->
<script src="assets/vendor/apexcharts/apexcharts.min.js"></script>
<script src="assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="assets/vendor/chart.js/chart.umd.js"></script>
<script src="assets/vendor/quill/quill.min.js"></script>
<script src="assets/vendor/php-email-form/validate.js"></script>
<script src="assets/vendor/simple-datatables/simple-datatables.js"></script>

<!-- Template Main JS File -->
<script src="assets/js/main.js"></script>
<script src="assets/js/dselect.js"></script>
<script src="assets/js/dselect.min.js"></script>

<!-- Chargement unique de Select2 -->
<script src="assets/vendor/select2/select2.min.js"></script>

<script src="assets/vendor/spinners/spin.min.js"></script>
<script src="assets/vendor/spinners/ladda.min.js"></script>

<script>
    // Affichage mot de passe
    let checkbox = document.querySelector("#rememberMe");

    function changer() {
      if (checkbox && checkbox.checked) {
        const renewPassword = document.getElementById("renewPassword");
        const newPassword = document.getElementById("newPassword");
        const currentPassword = document.getElementById("currentPassword");
        
        if (renewPassword) renewPassword.setAttribute("type", "text");
        if (newPassword) newPassword.setAttribute("type", "text");
        if (currentPassword) currentPassword.setAttribute("type", "text");
      } else {
        const renewPassword = document.getElementById("renewPassword");
        const newPassword = document.getElementById("newPassword");
        const currentPassword = document.getElementById("currentPassword");
        
        if (renewPassword) renewPassword.setAttribute("type", "password");
        if (newPassword) newPassword.setAttribute("type", "password");
        if (currentPassword) currentPassword.setAttribute("type", "password");
      }
    }
    
    // Ajouter l'écouteur d'événement si l'élément existe
    if (checkbox) {
        checkbox.addEventListener('change', changer);
    }
</script>

<!-- Initialisation centralisée de Select2 -->
<script>
$(document).ready(function() {
    console.log("Document ready - Initialisation de Select2");
    
    try {
        // Initialisation de base de Select2 pour tous les selects
        $('select').each(function() {
            // Vérifier si Select2 est déjà initialisé sur cet élément
            if (!$(this).data('select2')) {
                $(this).select2({
                    width: '100%',
                    placeholder: 'Sélectionner une option',
                    allowClear: true
                });
            }
        });
        
        // Configuration spéciale pour les select dans les modals
        $('.modal').on('shown.bs.modal', function() {
            $(this).find('select').each(function() {
                // Détruire l'instance existante si nécessaire
                if ($(this).data('select2')) {
                    $(this).select2('destroy');
                }
                
                // Réinitialiser avec le parent modal
                $(this).select2({
                    width: '100%',
                    placeholder: 'Sélectionner une option',
                    allowClear: true,
                    dropdownParent: $(this).closest('.modal-body') // Changer ici : utiliser modal-body au lieu de modal
                });
            });
        });
        
        // Solution définitive pour forcer l'ouverture vers le bas
        $('.modal').on('shown.bs.modal', function() {
            // Ajouter CSS pour forcer l'ouverture vers le bas dans cette modal
            const modalId = $(this).attr('id') || 'modal-' + Date.now();
            if (!$(this).attr('id')) {
                $(this).attr('id', modalId);
            }
            
            // Injecter du CSS spécifique pour cette modal
            const style = `
                <style id="select2-modal-fix-${modalId}">
                    #${modalId} .select2-container--open .select2-dropdown {
                        position: absolute !important;
                        top: 100% !important;
                        left: 0 !important;
                        right: 0 !important;
                        z-index: 1070 !important;
                    }
                    #${modalId} .select2-container--open .select2-dropdown--above {
                        top: 100% !important;
                        bottom: auto !important;
                    }
                    #${modalId} .select2-dropdown {
                        position: absolute !important;
                        z-index: 1070 !important;
                    }
                </style>
            `;
            
            // Supprimer le style précédent s'il existe et ajouter le nouveau
            $(`#select2-modal-fix-${modalId}`).remove();
            $('head').append(style);
        });
        
        // Nettoyer les styles à la fermeture
        $('.modal').on('hidden.bs.modal', function() {
            const modalId = $(this).attr('id');
            if (modalId) {
                $(`#select2-modal-fix-${modalId}`).remove();
            }
            $('.select2-container').css('z-index', '');
        });
        
        // Gestion du z-index pour les modals
        $('.modal').on('show.bs.modal', function() {
            // Réinitialiser le z-index des select2 en dehors du modal
            $('.select2-container').not($(this).find('.select2-container')).css('z-index', 'auto');
        });
        
        // Déclenchement des événements sur des éléments spécifiques
        setTimeout(function() {
            if ($('#type_operation').length) {
                console.log("Déclenchement de l'événement change sur #type_operation");
                $('#type_operation').trigger('change');
            }
            
            // Déclencher d'autres éléments spécifiques si nécessaire
            $('.trigger-change').trigger('change');
        }, 1000);
        
        console.log("Initialisation de Select2 terminée");
    } catch (e) {
        console.error("Erreur lors de l'initialisation de Select2:", e);
    }
});
</script>

<style>
    /* Style de base pour Select2 - z-index normal */
    .select2-container {
        z-index: auto;
    }

    .select2-dropdown {
        z-index: 1000;
    }

    /* Styles spéciaux pour Select2 dans les modals */
    .modal .select2-container {
        z-index: 2050;
    }

    .modal .select2-dropdown {
        z-index: 2051;
    }

    .select2-container .select2-selection--single {
        height: calc(1.5em + 0.75rem + 2px);
        padding: 0.375rem 0.75rem;
        border-radius: 0.25rem;
    }

    .select2-selection__rendered {
        line-height: calc(1.5em + 0.75rem);
    }

    .select2-selection__arrow {
        height: calc(1.5em + 0.75rem + 2px);
    }
</style>

</body>
</html>
