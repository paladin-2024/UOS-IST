// Variables globales
let currentAcademicYear = null;

// Fonction pour charger l'année académique en cours
function loadCurrentAcademicYear() {
    return fetch('controller/get_current_academic_year.php')
        .then(response => {
            if (!response.ok) {
                throw new Error('Erreur réseau: ' + response.status);
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                currentAcademicYear = data.anneeAcad;
                console.log("Année académique chargée:", currentAcademicYear);
                return currentAcademicYear;
            } else {
                throw new Error(data.message || "Impossible de charger l'année académique");
            }
        });
}

// Fonction pour charger les frais disponibles en fonction de l'étudiant sélectionné
function loadFraisForEtudiant(etudiantId, anneeAcadId) {
    if (!etudiantId) {
        return Promise.reject(new Error("ID étudiant non spécifié"));
    }
    
    if (!anneeAcadId) {
        return Promise.reject(new Error("ID année académique non spécifié"));
    }
    
    const fraisSelect = document.querySelector('select[name="fraisId"]');
    fraisSelect.disabled = true;
    fraisSelect.innerHTML = '<option value="">Chargement...</option>';
    
    const url = `controller/get_frais_etudiant.php?etudiantId=${etudiantId}&anneeAcadId=${anneeAcadId}`;
    console.log("URL appelée:", url);
    
    return fetch(url)
        .then(response => {
            console.log("Statut de la réponse:", response.status);
            if (!response.ok) {
                throw new Error('Erreur réseau: ' + response.status);
            }
            return response.json();
        })
        .then(data => {
            console.log("Données reçues:", data);
            
            fraisSelect.innerHTML = '<option value="">Sélectionner un frais...</option>';
            
            if (Array.isArray(data) && data.length > 0) {
                console.log("Nombre de frais trouvés:", data.length);
                data.forEach(frais => {
                    const montantRestant = frais.montantRestant || frais.montant;
                    fraisSelect.innerHTML += `
                        <option value="${frais.idfrais}" 
                                data-montant="${montantRestant}" 
                                data-devise="${frais.devise}">
                            ${frais.designation} - Reste: ${montantRestant} ${frais.devise}
                        </option>
                    `;
                });
                return data;
            } else {
                console.log("Aucun frais trouvé ou format incorrect");
                fraisSelect.innerHTML = '<option value="">Aucun frais disponible</option>';
                return [];
            }
        })
        .catch(error => {
            console.error('Erreur:', error);
            fraisSelect.innerHTML = '<option value="">Erreur de chargement</option>';
            throw error;
        })
        .finally(() => {
            fraisSelect.disabled = false;
        });
}

// Fonction pour éditer un paiement
function editPaiement(id) {
    fetch(`controller/get_paiement_details.php?id=${id}`)
        .then(response => response.json())
        .then(data => {
            document.getElementById('edit_id').value = data.idpaiement;
            document.getElementById('edit_etudiant').value = data.nomEtudiant;
            document.getElementById('edit_frais').value = `${data.fraisDesignation} - ${data.montantTotal} ${data.devise}`;
            document.getElementById('edit_montantPaye').value = data.montantPaye;
            document.getElementById('edit_referencePaiement').value = data.referencePaiement;
            document.getElementById('edit_modePaiement').value = data.modePaiement;
            document.getElementById('edit_commentaire').value = data.commentaire;

            new bootstrap.Modal(document.getElementById('editPaiementModal')).show();
        })
        .catch(error => {
            console.error('Erreur:', error);
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Impossible de charger les détails du paiement'
            });
        });
}

// Fonction pour voir les détails d'un paiement
function viewPaiement(id) {
    fetch(`controller/get_paiement_details.php?id=${id}`)
        .then(response => response.json())
        .then(data => {
            document.getElementById('view_etudiant').textContent = data.nomEtudiant;
            document.getElementById('view_matricule').textContent = data.matricule;
            document.getElementById('view_frais').textContent = data.fraisDesignation;
            document.getElementById('view_montantTotal').textContent = `${data.montantTotal} ${data.devise}`;
            document.getElementById('view_montantPaye').textContent = `${data.montantPaye} ${data.devise}`;
            document.getElementById('view_reference').textContent = data.referencePaiement;
            document.getElementById('view_modePaiement').textContent = data.modePaiement;
            document.getElementById('view_datePaiement').textContent = new Date(data.datePaiement).toLocaleString();
            document.getElementById('view_etat').textContent = data.estComplet ? 'Complet' : 'Partiel';
            document.getElementById('view_commentaire').textContent = data.commentaire || 'Aucun commentaire';

            new bootstrap.Modal(document.getElementById('viewPaiementModal')).show();
        })
        .catch(error => {
            console.error('Erreur:', error);
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: 'Impossible de charger les détails du paiement'
            });
        });
}

// Fonction pour supprimer un paiement
function deletePaiement(id) {
    Swal.fire({
        title: 'Êtes-vous sûr ?',
        text: "Cette action est irréversible !",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#3085d6',
        cancelButtonColor: '#d33',
        confirmButtonText: 'Oui, supprimer',
        cancelButtonText: 'Annuler'
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = 'controller/delete_paiement.php?id=' + id;
        }
    });
}

// Fonction pour imprimer un reçu
function printRecu() {
    // Implémenter la fonction d'impression du reçu
    window.print();
}

// Initialisation des événements
document.addEventListener('DOMContentLoaded', function() {
    // Charger l'année académique en cours au chargement de la page
    loadCurrentAcademicYear()
        .catch(error => {
            console.error("Erreur lors du chargement de l'année académique:", error);
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: "Impossible de charger l'année académique en cours: " + error.message
            });
        });

    console.log("Test OKOK");
    
    // Écouteur pour le changement d'étudiant
    const etudiantSelect = document.getElementById('etudiantId');
    if (etudiantSelect) {
        etudiantSelect.addEventListener('change', function() {
            const etudiantId = this.value;
            console.log("Étudiant sélectionné:", etudiantId);
            
            if (etudiantId) {
                if (currentAcademicYear && currentAcademicYear.idannee_acad) {
                    loadFraisForEtudiant(etudiantId, currentAcademicYear.idannee_acad)
                        .catch(error => {
                            console.error("Erreur lors du chargement des frais:", error);
                            Swal.fire({
                                icon: 'error',
                                title: 'Erreur',
                                text: 'Impossible de charger les frais: ' + error.message
                            });
                        });
                } else {
                    console.error("Année académique non disponible");
                    Swal.fire({
                        icon: 'error',
                        title: 'Erreur',
                        text: "L'année académique en cours n'est pas disponible"
                    });
                }
            } else {
                // Réinitialiser le select des frais si aucun étudiant n'est sélectionné
                document.querySelector('select[name="fraisId"]').innerHTML = 
                    '<option value="">Sélectionner un frais...</option>';
            }
        });
    }else{
        console.log("Erreur sur écouteur");
    }
    
    // Écouteur pour le changement de frais
    const fraisSelect = document.querySelector('select[name="fraisId"]');
    if (fraisSelect) {
        fraisSelect.addEventListener('change', function() {
            const selectedOption = this.options[this.selectedIndex];
            const montantInput = document.querySelector('input[name="montantPaye"]');
            
            if (selectedOption && selectedOption.dataset.montant) {
                montantInput.max = selectedOption.dataset.montant;
                montantInput.placeholder = `Maximum: ${selectedOption.dataset.montant} ${selectedOption.dataset.devise}`;
            } else {
                montantInput.max = '';
                montantInput.placeholder = '';
            }
        });
    }
});