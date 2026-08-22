/**
 * Initialisation sécurisée de DataTables avec traduction française
 */

// Configuration de la traduction française
const frenchTranslation = {
    "sProcessing":     "Traitement en cours...",
    "sSearch":         "Rechercher&nbsp;:",
    "sLengthMenu":     "Afficher _MENU_ &eacute;l&eacute;ments",
    "sInfo":           "Affichage de l'&eacute;l&eacute;ment _START_ &agrave; _END_ sur _TOTAL_ &eacute;l&eacute;ments",
    "sInfoEmpty":      "Affichage de l'&eacute;l&eacute;ment 0 &agrave; 0 sur 0 &eacute;l&eacute;ment",
    "sInfoFiltered":   "(filtr&eacute; de _MAX_ &eacute;l&eacute;ments au total)",
    "sInfoPostFix":    "",
    "sLoadingRecords": "Chargement en cours...",
    "sZeroRecords":    "Aucun &eacute;l&eacute;ment &agrave; afficher",
    "sEmptyTable":     "Aucune donn&eacute;e disponible dans le tableau",
    "oPaginate": {
        "sFirst":      "Premier",
        "sPrevious":   "Pr&eacute;c&eacute;dent",
        "sNext":       "Suivant",
        "sLast":       "Dernier"
    },
    "oAria": {
        "sSortAscending":  ": activer pour trier la colonne par ordre croissant",
        "sSortDescending": ": activer pour trier la colonne par ordre d&eacute;croissant"
    },
    "select": {
        "rows": {
            "_": "%d lignes s&eacute;lectionn&eacute;es",
            "0": "Aucune ligne s&eacute;lectionn&eacute;e",
            "1": "1 ligne s&eacute;lectionn&eacute;e"
        }
    }
};

// Fonction pour initialiser DataTables de manière sécurisée
function safeInitDataTable(selector, options = {}) {
    // Vérifier que jQuery est chargé
    if (typeof jQuery === 'undefined') {
        console.error('jQuery n\'est pas chargé');
        return null;
    }
    
    // Vérifier que DataTables est chargé
    if (typeof $.fn.DataTable === 'undefined') {
        console.error('DataTables n\'est pas chargé');
        return null;
    }
    
    try {
        // Détruire l'instance existante si elle existe
        if ($.fn.DataTable.isDataTable(selector)) {
            $(selector).DataTable().destroy();
        }
        
        // Configuration par défaut
        const defaultOptions = {
            language: frenchTranslation,
            pageLength: 25,
            lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, "Tout"]],
            responsive: true,
            autoWidth: false,
            processing: true,
            stateSave: true,
            stateDuration: 60 * 60 * 24 // 24 heures
        };
        
        // Fusionner les options
        const finalOptions = $.extend(true, {}, defaultOptions, options);
        
        // Initialiser DataTable
        return $(selector).DataTable(finalOptions);
        
    } catch (error) {
        console.error('Erreur lors de l\'initialisation de DataTable:', error);
        return null;
    }
}

// Fonction alternative pour charger la traduction depuis un CDN
function loadDataTablesFrenchFromCDN(callback) {
    const urls = [
        'https://cdn.datatables.net/plug-ins/1.13.7/i18n/fr-FR.json',
        'https://cdn.datatables.net/plug-ins/1.13.6/i18n/fr-FR.json',
        'https://cdn.datatables.net/plug-ins/1.13.4/i18n/fr-FR.json'
    ];
    
    let currentIndex = 0;
    
    function tryNextUrl() {
        if (currentIndex >= urls.length) {
            console.warn('Impossible de charger la traduction depuis le CDN, utilisation de la traduction locale');
            if (callback) callback(frenchTranslation);
            return;
        }
        
        $.ajax({
            url: urls[currentIndex],
            dataType: 'json',
            success: function(data) {
                console.log('Traduction fran��aise chargée depuis:', urls[currentIndex]);
                if (callback) callback(data);
            },
            error: function() {
                currentIndex++;
                tryNextUrl();
            }
        });
    }
    
    tryNextUrl();
}