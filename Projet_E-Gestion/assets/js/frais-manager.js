/**
 * Gestionnaire pour les frais académiques
 */
class FraisManager {
    constructor() {
        this.initEventListeners();
    }

    initEventListeners() {
        // Gestion du type d'affectation
        const typeAffectation = document.getElementById('type_affectation');
        if (typeAffectation) {
            typeAffectation.addEventListener('change', () => this.toggleAffectationType());
            // Initialiser l'état au chargement
            this.toggleAffectationType();
        }

        // Gestion de l'échelonnement
        const estEchelonnable = document.getElementById('est_echelonnable');
        if (estEchelonnable) {
            estEchelonnable.addEventListener('change', () => this.toggleEchelonnement());
            // Initialiser l'état au chargement
            this.toggleEchelonnement();
        }

        // Vérification de l'étudiant
        const matriculeInput = document.getElementById('matricule_etudiant');
        if (matriculeInput) {
            matriculeInput.addEventListener('blur', () => this.verifierEtudiant());
        }

        // Gestion du montant spécifique
        const montantSpecifiqueCheck = document.getElementById('montant_specifique_check');
        if (montantSpecifiqueCheck) {
            montantSpecifiqueCheck.addEventListener('change', () => this.toggleMontantSpecifique());
            // Initialiser l'état au chargement
            this.toggleMontantSpecifique();
        }
    }

    toggleAffectationType() {
        const typeAffectation = document.getElementById('type_affectation');
        const promotionContainer = document.getElementById('promotion_container');
        const etudiantContainer = document.getElementById('etudiant_container');
        
        if (!typeAffectation || !promotionContainer || !etudiantContainer) return;
        
        if (typeAffectation.value === 'promotion') {
            promotionContainer.style.display = 'block';
            etudiantContainer.style.display = 'none';
            document.getElementById('promotion_id').setAttribute('required', 'required');
            document.getElementById('matricule_etudiant').removeAttribute('required');
        } else {
            promotionContainer.style.display = 'none';
            etudiantContainer.style.display = 'block';
            document.getElementById('promotion_id').removeAttribute('required');
            document.getElementById('matricule_etudiant').setAttribute('required', 'required');
        }
    }

    toggleEchelonnement() {
        const estEchelonnable = document.getElementById('est_echelonnable');
        const echelonnementOptions = document.getElementById('echelonnement_options');
        
        if (!estEchelonnable || !echelonnementOptions) return;
        
        echelonnementOptions.style.display = estEchelonnable.checked ? 'block' : 'none';
    }

    toggleMontantSpecifique() {
        const montantSpecifiqueCheck = document.getElementById('montant_specifique_check');
        const montantSpecifiqueContainer = document.getElementById('montant_specifique_container');
        
        if (!montantSpecifiqueCheck || !montantSpecifiqueContainer) return;
        
        montantSpecifiqueContainer.style.display = montantSpecifiqueCheck.checked ? 'block' : 'none';
        
        if (!montantSpecifiqueCheck.checked) {
            const montantInput = document.getElementById('montant_specifique');
            if (montantInput) montantInput.value = '';
        }
    }

    async verifierEtudiant() {
        const matricule = document.getElementById('matricule_etudiant').value.trim();
        const infoContainer = document.getElementById('etudiant_info');
        
        if (!matricule || !infoContainer) return;
        
        if (matricule.length < 4) {
            infoContainer.style.display = 'block';
            infoContainer.className = 'alert alert-warning';
            infoContainer.innerHTML = '<i class="bi bi-exclamation-triangle"></i> Veuillez saisir un matricule valide.';
            return;
        }
        
        infoContainer.style.display = 'block';
        infoContainer.className = 'alert alert-info';
        infoContainer.innerHTML = '<i class="bi bi-hourglass-split"></i> Vérification en cours...';
        
        try {
            const response = await fetch(`../controller/get_etudiant_info.php?matricule=${encodeURIComponent(matricule)}`);
            const data = await response.json();
            
            if (data.error) {
                infoContainer.className = 'alert alert-danger';
                infoContainer.innerHTML = `<i class="bi bi-exclamation-circle"></i> ${data.error}`;
                return;
            }
            
            infoContainer.className = 'alert alert-success';
            infoContainer.innerHTML = `
                <div class="d-flex align-items-center">
                    <div class="flex-shrink-0">
                        <i class="bi bi-person-check fs-1"></i>
                    </div>
                    <div class="flex-grow-1 ms-3">
                        <h6 class="mb-0">${data.nom}</h6>
                        <p class="mb-0">Promotion: ${data.promotion}</p>
                        <p class="mb-0">Faculté: ${data.faculte}</p>
                    </div>
                </div>
            `;
        } catch (error) {
            console.error('Erreur:', error);
            infoContainer.className = 'alert alert-danger';
            infoContainer.innerHTML = '<i class="bi bi-exclamation-circle"></i> Erreur de communication avec le serveur.';
        }
    }
}

// Initialiser le gestionnaire au chargement de la page
document.addEventListener('DOMContentLoaded', () => {
    new FraisManager();
});