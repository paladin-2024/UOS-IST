
<script>
    // Initialisation des toasts avec animation
    document.addEventListener('DOMContentLoaded', function() {
        var toastElList = [].slice.call(document.querySelectorAll('.toast'));
        var toastList = toastElList.map(function(toastEl) {
            // Créer l'objet toast
            var toast = new bootstrap.Toast(toastEl, {
                delay: 5000,
                autohide: true
            });

            // Ajouter un écouteur pour l'animation de sortie
            toastEl.addEventListener('hide.bs.toast', function() {
                this.classList.add('toast-hide');
                // Retarder la disparition réelle pour laisser l'animation se terminer
                setTimeout(function() {
                    toast.dispose();
                }, 300);
            });

            // Afficher le toast
            toast.show();
            return toast;
        });
    });

    // Fallback: masquer le preloader après 8 secondes max (si DOMContentLoaded ne se déclenche pas à cause de scripts CDN bloqués)
    (function() {
        function hidePreloader() {
            const preloader = document.getElementById("preloader");
            if (preloader && preloader.style.display !== "none") {
                preloader.style.opacity = "0";
                setTimeout(() => { preloader.style.display = "none"; }, 500);
            }
        }
        setTimeout(hidePreloader, 8000);
    })();

    document.addEventListener("DOMContentLoaded", function() {
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
            form.addEventListener("submit", function() {
                // Check if all required fields are complete
                if (!areFieldsComplete(form)) {
                    event.preventDefault(); // Prevent form submission
                    Swal.fire({
                        icon: 'warning',
                        title: 'Champs incomplets',
                        text: 'Veuillez remplir tous les champs requis.',
                        confirmButtonText: 'OK'
                    });
                    return;
                }

                // Afficher le preloader
                preloader.style.display = "flex";
                preloader.style.opacity = "1";
            });
        });
    });
</script>

<script>
    // Afficher le spinner
    window.onload = function() {
        document.getElementById('spinner').style.display = 'none';
    };
</script>

<!-- ======= Footer ======= -->
<footer id="footer" class="footer">
    <div class="copyright">
    &copy; Copyright <strong><span><?php echo htmlspecialchars($config['nom_application'] ?? 'E-GESTION'); ?></span></strong>. All Rights Reserved
    </div>
    <div class="credits">
    Designed by <a href=""><?php echo htmlspecialchars($config['nom_application'] ?? 'E-GESTION'); ?> - Business Domain Software</a>
    </div>
</footer><!-- End Footer -->

<a class="back-to-top d-flex align-items-center justify-content-center" id="btn-back-to-top"><i class="bi bi-arrow-up-short"></i></a>

<!-- ======= Scripts principaux ======= -->
<!-- Vendor JS Files -->
<!-- NE PAS charger jQuery à nouveau ici -->
<script src="assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="assets/vendor/chart.js/chart.umd.js"></script>
<script src="assets/vendor/simple-datatables/simple-datatables.js"></script>
<script src="assets/vendor/php-email-form/validate.js"></script>

<!-- Template Main JS File -->
<script src="assets/js/main.js"></script>

<!-- Chargement de Select2 (UNE SEULE FOIS) -->
<script src="assets/vendor/select2/select2.min.js"></script>

<!-- Script spécialisé pour la gestion de Select2 dans les compositions -->
<script src="assets/js/composition-select2.js"></script>

<!-- Initialisation de Select2 -->
<script>
    //Sroller la page
    $(document).ready(function() {
        //donner la forme de la main lors que la souris y pointe. seulement à ce niveau

        $('#btn-back-to-top').click(function() {
            $('html, body').animate({
                scrollTop: 0
            }, 1000);
        });
    });

</script>

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




<!-- DataTables Language Configuration -->
<script>
    $(document).ready(function() {
        // Configuration globale pour DataTables en français
        $.extend(true, $.fn.dataTable.defaults, {
            language: {
                "emptyTable": "Aucune donnée disponible dans le tableau",
                "info": "Affichage de _START_ à _END_ sur _TOTAL_ entrées",
                "infoEmpty": "Affichage de 0 à 0 sur 0 entrée",
                "infoFiltered": "(filtré à partir de _MAX_ entrées au total)",
                "lengthMenu": "Afficher _MENU_ entrées",
                "loadingRecords": "Chargement...",
                "processing": "Traitement...",
                "search": "Rechercher :",
                "zeroRecords": "Aucun résultat trouvé",
                "paginate": {
                    "first": "Premier",
                    "last": "Dernier",
                    "next": "Suivant",
                    "previous": "Précédent"
                },
                "aria": {
                    "sortAscending": ": activer pour trier la colonne par ordre croissant",
                    "sortDescending": ": activer pour trier la colonne par ordre décroissant"
                }
            }
        });
    });
</script>




<!-- Ajouter DataTables JS -->
<script src="assets/DataTables/datatables.min.js"></script>

</body>

</html>
