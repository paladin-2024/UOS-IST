// Script pour le suivi des enseignements
(function($) {
    'use strict';
    
    // Fonction pour charger les ECUE
    function chargerECUE() {
        console.log('chargerECUE appelée');
        
        const promotionId = $('#promotionFilter').val();
        const $ecueSelect = $('#ecueFilter');
        
        if (!promotionId) {
            $ecueSelect.html('<option value="">Tous les cours</option>').prop('disabled', false);
            return;
        }
        
        // Afficher le chargement
        $ecueSelect.html('<option value="">Chargement...</option>').prop('disabled', true);
        
        // Requête AJAX
        $.ajax({
            url: 'controller/ajax_get_ecues.php',
            type: 'GET',
            data: { promotion_id: promotionId },
            dataType: 'json'
        })
        .done(function(response) {
            console.log('Réponse:', response);
            
            $ecueSelect.html('<option value="">Tous les cours</option>');
            
            if (response.success && response.ecues && response.ecues.length > 0) {
                $.each(response.ecues, function(index, ecue) {
                    $ecueSelect.append(
                        $('<option></option>')
                            .attr('value', ecue.idECUE)
                            .text(ecue.designationECUE)
                    );
                });
            } else {
                $ecueSelect.html('<option value="">Aucun cours disponible</option>');
            }
            
            $ecueSelect.prop('disabled', false);
        })
        .fail(function(xhr, status, error) {
            console.error('Erreur AJAX:', status, error);
            $ecueSelect.html('<option value="">Erreur de chargement</option>').prop('disabled', false);
            
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Impossible de charger les cours'
            });
        });
    }
    
    // Initialisation au chargement de la page
    $(function() {
        console.log('Initialisation suivi_enseignements.js');
        
        // Vérifier que les éléments existent
        const $promotionFilter = $('#promotionFilter');
        const $ecueFilter = $('#ecueFilter');
        
        if ($promotionFilter.length === 0 || $ecueFilter.length === 0) {
            console.error('Éléments de filtre non trouvés');
            return;
        }
        
        // Attacher l'événement
        $promotionFilter.on('change', chargerECUE);
        
        // Charger les ECUE si une promotion est déjà sélectionnée
        if ($promotionFilter.val()) {
            chargerECUE();
        }
        
        console.log('Événements attachés avec succès');
    });
    
    // Exposer la fonction globalement si nécessaire
    window.chargerECUE = chargerECUE;
    
})(jQuery);