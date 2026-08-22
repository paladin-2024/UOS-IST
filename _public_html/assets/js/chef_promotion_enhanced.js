// Variables globales pour la recherche
let searchTimeout;
let allPromotions = [];

// Fonction pour afficher l'historique des chefs de promotion
function showHistory(promotionId, promotionName, anneeId) {
    document.getElementById('history_promotion_name').textContent = promotionName;
    
    // Réinitialiser l'état du modal
    document.getElementById('historyLoading').classList.remove('d-none');
    document.getElementById('historyContent').classList.add('d-none');
    document.getElementById('noHistoryMessage').classList.add('d-none');
    document.getElementById('historyError').classList.add('d-none');
    
    // Afficher le modal
    const historyModal = new bootstrap.Modal(document.getElementById('historyModal'));
    historyModal.show();
    
    // Charger l'historique
    fetch(`controller/get_chef_promotion_history.php?promotion_id=${promotionId}&annee_id=${anneeId}`)
        .then(response => {
            if (!response.ok) {
                throw new Error(`Erreur HTTP: ${response.status}`);
            }
            return response.json();
        })
        .then(data => {
            if (data.error) {
                throw new Error(data.error);
            }
            
            document.getElementById('historyLoading').classList.add('d-none');
            
            if (data.historique && data.historique.length > 0) {
                displayHistory(data.historique);
                document.getElementById('historyContent').classList.remove('d-none');
            } else {
                document.getElementById('noHistoryMessage').classList.remove('d-none');
            }
        })
        .catch(error => {
            console.error('Erreur lors du chargement de l\'historique:', error);
            document.getElementById('historyLoading').classList.add('d-none');
            document.getElementById('historyError').classList.remove('d-none');
            document.getElementById('historyErrorMessage').textContent = error.message;
        });
}

// Fonction pour afficher l'historique dans le tableau
function displayHistory(historique) {
    const tbody = document.getElementById('historyTableBody');
    tbody.innerHTML = '';
    
    historique.forEach(entry => {
        const row = document.createElement('tr');
        row.className = entry.statut === 'Actif' ? 'table-success' : '';
        
        row.innerHTML = `
            <td>
                <div class="d-flex align-items-center">
                    <div class="avatar-sm ${entry.statut === 'Actif' ? 'bg-success' : 'bg-secondary'} rounded-circle d-flex align-items-center justify-content-center me-2">
                        <i class="bi bi-person-fill text-white"></i>
                    </div>
                    <div>
                        <div class="fw-bold">${entry.chef_nom || 'N/A'}</div>
                        ${entry.chef_matricule ? `<small class="text-muted">${entry.chef_matricule}</small>` : ''}
                    </div>
                </div>
            </td>
            <td>
                <div class="fw-bold">${entry.periode}</div>
                ${entry.annee ? `<small class="text-muted">${entry.annee}</small>` : ''}
            </td>
            <td>
                <span class="badge ${entry.statut === 'Actif' ? 'bg-success' : 'bg-secondary'}">
                    ${entry.statut}
                </span>
            </td>
            <td>${entry.assigneur || '-'}</td>
            <td>${entry.retireur || '-'}</td>
            <td>
                ${entry.commentaire ? `<small class="text-muted">${entry.commentaire}</small>` : '-'}
            </td>
        `;
        
        tbody.appendChild(row);
    });
}

// Fonction de recherche instantanée
function performInstantSearch(searchTerm) {
    const rows = document.querySelectorAll('.promotion-row');
    const noResultsDiv = document.getElementById('noSearchResults');
    const resultCount = document.getElementById('resultCount');
    let visibleCount = 0;
    
    searchTerm = searchTerm.toLowerCase().trim();
    
    rows.forEach(row => {
        const searchText = row.getAttribute('data-search-text');
        const isVisible = searchTerm === '' || searchText.includes(searchTerm);
        
        if (isVisible) {
            row.style.display = '';
            row.classList.remove('fade-out');
            visibleCount++;
        } else {
            row.style.display = 'none';
            row.classList.add('fade-out');
        }
    });
    
    // Mettre à jour le compteur
    resultCount.textContent = `${visibleCount} promotion(s) trouvée(s)`;
    
    // Afficher/masquer le message "aucun résultat"
    if (visibleCount === 0 && searchTerm !== '') {
        noResultsDiv.classList.remove('d-none');
        document.getElementById('promotionsTable').style.display = 'none';
    } else {
        noResultsDiv.classList.add('d-none');
        document.getElementById('promotionsTable').style.display = '';
    }
}

// Fonction pour effacer la recherche instantanée
function clearInstantSearch() {
    const searchInput = document.getElementById('instantSearchInput');
    searchInput.value = '';
    performInstantSearch('');
}

// Initialisation de la recherche instantanée
document.addEventListener('DOMContentLoaded', function() {
    // Attendre que le DOM soit complètement chargé
    setTimeout(() => {
        // Initialiser la liste des promotions pour la recherche
        const rows = document.querySelectorAll('.promotion-row');
        allPromotions = Array.from(rows);
        
        // Gestion de la recherche instantanée
        const instantSearchInput = document.getElementById('instantSearchInput');
        const clearSearchBtn = document.getElementById('clearSearch');
        
        if (instantSearchInput) {
            instantSearchInput.addEventListener('input', function(e) {
                clearTimeout(searchTimeout);
                searchTimeout = setTimeout(() => {
                    performInstantSearch(e.target.value);
                }, 300);
            });
            
            // Recherche en temps réel (plus rapide)
            instantSearchInput.addEventListener('keyup', function(e) {
                if (e.key === 'Escape') {
                    clearInstantSearch();
                }
            });
        }
        
        if (clearSearchBtn) {
            clearSearchBtn.addEventListener('click', clearInstantSearch);
        }
        
        // Animation d'entrée pour les lignes du tableau
        setTimeout(() => {
            rows.forEach((row, index) => {
                setTimeout(() => {
                    row.style.opacity = '0';
                    row.style.transform = 'translateY(20px)';
                    row.style.transition = 'all 0.3s ease';
                    
                    setTimeout(() => {
                        row.style.opacity = '1';
                        row.style.transform = 'translateY(0)';
                    }, 50);
                }, index * 50);
            });
        }, 100);
    }, 500);
});