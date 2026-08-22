class UEManager {
    constructor() {
        this.init();
    }

    init() {
        // Initialiser les accordéons pour les sections
        this.setupSectionAccordions();
        
        // Ajouter la recherche pour les semestres
        this.setupSemestresSearch();
        
        // Ajouter les boutons de sélection rapide
        this.setupQuickSelectionButtons();
    }

    setupSectionAccordions() {
        // Créer des accordéons pour chaque section
        const sections = document.querySelectorAll('#semestresContainer > h6');
        
        sections.forEach((section, index) => {
            const sectionId = `section-${index}`;
            const nextElement = section.nextElementSibling;
            
            // Transformer le titre en bouton d'accordéon
            section.innerHTML = `
                <button class="btn btn-link text-decoration-none p-0 w-100 text-start" 
                        type="button" 
                        data-bs-toggle="collapse" 
                        data-bs-target="#${sectionId}" 
                        aria-expanded="${index === 0 ? 'true' : 'false'}" 
                        aria-controls="${sectionId}">
                    <i class="bi bi-chevron-down me-2"></i> ${section.textContent}
                </button>
            `;
            
            // Envelopper le contenu dans un div collapsible
            if (nextElement && nextElement.classList.contains('ms-3')) {
                const wrapper = document.createElement('div');
                wrapper.id = sectionId;
                wrapper.className = `collapse ${index === 0 ? 'show' : ''}`;
                
                // Déplacer le contenu dans le wrapper
                section.parentNode.insertBefore(wrapper, nextElement);
                wrapper.appendChild(nextElement);
            }
        });
    }

    setupSemestresSearch() {
        // Ajouter un champ de recherche au-dessus de la liste des semestres
        const container = document.getElementById('semestresContainer');
        const searchBox = document.createElement('div');
        searchBox.className = 'mb-3';
        searchBox.innerHTML = `
            <div class="input-group">
                <span class="input-group-text"><i class="bi bi-search"></i></span>
                <input type="text" class="form-control" id="semestresSearch" 
                       placeholder="Rechercher une promotion ou un semestre...">
            </div>
        `;
        
        container.parentNode.insertBefore(searchBox, container);
        
        // Ajouter l'événement de recherche
        document.getElementById('semestresSearch').addEventListener('input', (e) => {
            const searchTerm = e.target.value.toLowerCase();
            
            // Rechercher dans les promotions et semestres
            document.querySelectorAll('#semestresContainer .mb-2').forEach(promotion => {
                const promotionText = promotion.textContent.toLowerCase();
                const promotionContainer = promotion.closest('.ms-3');
                const section = promotionContainer ? promotionContainer.closest('.collapse') : null;
                
                let visible = promotionText.includes(searchTerm);
                
                // Vérifier aussi les semestres de cette promotion
                const semestres = promotion.nextElementSibling.querySelectorAll('.form-check-label');
                semestres.forEach(semestre => {
                    const semestreVisible = semestre.textContent.toLowerCase().includes(searchTerm);
                    visible = visible || semestreVisible;
                    
                    // Afficher/masquer les semestres individuels
                    semestre.closest('.form-check').style.display = 
                        (searchTerm && !semestreVisible && !promotionText.includes(searchTerm)) ? 'none' : 'block';
                });
                
                // Afficher/masquer la promotion
                promotion.style.display = visible ? 'block' : 'none';
                if (promotionContainer) {
                    promotionContainer.style.display = visible ? 'block' : 'none';
                }
                
                // Ouvrir la section si des résultats sont trouvés
                if (section && visible && searchTerm) {
                    new bootstrap.Collapse(section).show();
                }
            });
        });
    }

    setupQuickSelectionButtons() {
        // Ajouter des boutons de sélection rapide
        const container = document.getElementById('semestresContainer');
        const buttonsGroup = document.createElement('div');
        buttonsGroup.className = 'btn-group mb-3';
        buttonsGroup.innerHTML = `
            <button type="button" class="btn btn-outline-primary btn-sm" id="selectAllSemestres">
                Tout sélectionner
            </button>
            <button type="button" class="btn btn-outline-secondary btn-sm" id="deselectAllSemestres">
                Tout désélectionner
            </button>
        `;
        
        container.parentNode.insertBefore(buttonsGroup, container);
        
        // Événements pour les boutons
        document.getElementById('selectAllSemestres').addEventListener('click', () => {
            document.querySelectorAll('input[name="semestres[]"]').forEach(checkbox => {
                checkbox.checked = true;
            });
        });
        
        document.getElementById('deselectAllSemestres').addEventListener('click', () => {
            document.querySelectorAll('input[name="semestres[]"]').forEach(checkbox => {
                checkbox.checked = false;
            });
        });
    }
}

// Initialiser le gestionnaire d'UE lorsque le DOM est chargé
document.addEventListener('DOMContentLoaded', () => {
    const ueManager = new UEManager();
});
