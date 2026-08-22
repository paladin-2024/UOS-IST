/**
 * Gestion avancee de Select2 pour les compositions
 * Ce fichier gere les interactions specifiques a Select2 dans les formulaires de composition
 */

$(document).ready(function() {
    console.log("Initialisation de composition-select2.js");
    
    // Configuration speciale pour les select de compositions
    function initializeCompositionSelects() {
        // Select pour les promotions dans les compositions
        $('.promotion-select').select2({
            width: '100%',
            placeholder: 'Selectionner une promotion',
            allowClear: true
        });
        
        // Select pour les ECUE dans les compositions
        $('.ecue-select').select2({
            width: '100%',
            placeholder: 'Selectionner un ECUE',
            allowClear: true
        });
        
        // Select pour les agents dans les compositions
        $('.agent-select').select2({
            width: '100%',
            placeholder: 'Selectionner un agent',
            allowClear: true
        });
    }
    
    // Initialiser les selects de composition
    initializeCompositionSelects();
    
    // Utiliser MutationObserver pour detecter les nouveaux elements (remplace DOMNodeInserted deprecie)
    if (window.MutationObserver) {
        const observer = new MutationObserver(function(mutations) {
            mutations.forEach(function(mutation) {
                if (mutation.type === 'childList') {
                    mutation.addedNodes.forEach(function(node) {
                        if (node.nodeType === Node.ELEMENT_NODE) {
                            const $node = $(node);
                            if ($node.is('select') || $node.find('select').length) {
                                initializeCompositionSelects();
                            }
                        }
                    });
                }
            });
        });
        
        observer.observe(document.body, {
            childList: true,
            subtree: true
        });
    }
});