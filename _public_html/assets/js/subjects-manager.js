class SubjectsManager {
    constructor() {
        this.subjectsContainer = document.getElementById('subjectsContainer');
        if (!this.subjectsContainer) {
            console.error('Container des sujets non trouvé');
            return;
        }
    }

    initializeEventListeners() {
        // Rafraîchissement automatique des sujets
        document.addEventListener('subjectsUpdated', () => this.loadAvailableSubjects());
    }

    async loadAvailableSubjects() {
        try {
            this.showLoading();
            const response = await fetch('../controller/get_subjects.php', {
                method: 'GET',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                credentials: 'same-origin'
            });

            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            const data = await response.json();
            
            if (data.error) {
                this.showError(data.error);
                return;
            }
            
            this.displaySubjects(Array.isArray(data) ? data : []);
        } catch (error) {
            console.error('Erreur de chargement:', error);
            this.showError('Erreur lors du chargement des sujets');
        }
    }

    displaySubjects(subjects) {
        if (!Array.isArray(subjects) || subjects.length === 0) {
            this.subjectsContainer.innerHTML = `
                <div class="alert alert-info text-center">
                    <i class="fas fa-info-circle me-2"></i>
                    Aucun sujet disponible pour le moment.
                </div>
            `;
            return;
        }

        try {
            this.subjectsContainer.innerHTML = subjects.map(subject => this.createSubjectCard(subject)).join('');
        } catch (error) {
            console.error('Erreur d\'affichage:', error);
            this.showError('Erreur lors de l\'affichage des sujets');
        }
    }

    createSubjectCard(subject) {
        if (!subject || !subject.titre) {
            return ''; // Ignorer les sujets invalides
        }

        return `
            <div class="subject-card shadow-sm">
                <div class="d-flex justify-content-between align-items-start mb-2">
                    <h5 class="mb-0">${this.escapeHtml(subject.titre)}</h5>
                    <span class="status-badge badge bg-${this.getStatusColor(subject.etatSujet)}">
                        ${this.escapeHtml(subject.etatSujet || 'Non défini')}
                    </span>
                </div>
                <div class="subject-details">
                    <p class="small text-muted mb-2">
                        <i class="fas fa-graduation-cap me-1"></i> 
                        Spécialisation: ${this.escapeHtml(subject.specialisation || 'Non spécifiée')}
                    </p>
                    <p class="small text-muted mb-2">
                        <i class="fas fa-flask me-1"></i> 
                        Unité de recherche: ${this.escapeHtml(subject.unite_recherche || 'Non spécifiée')}
                    </p>
                    <p class="small text-muted mb-3">
                        <i class="fas fa-calendar-alt me-1"></i> 
                        Publié le: ${this.formatDate(subject.dateCreation)}
                    </p>
                </div>
                ${this.getActionButton(subject)}
            </div>
        `;
    }

    formatDate(dateString) {
        if (!dateString) return 'Date non spécifiée';
        try {
            return new Date(dateString).toLocaleDateString('fr-FR', {
                year: 'numeric',
                month: 'long',
                day: 'numeric'
            });
        } catch (error) {
            return 'Date invalide';
        }
    }

    getStatusColor(status) {
        const colors = {
            'Disponible': 'success',
            'En attente': 'warning',
            'Attribué': 'info',
            'Non disponible': 'danger'
        };
        return colors[status] || 'secondary';
    }

    getActionButton(subject) {
        if (!subject || subject.etatSujet !== 'Disponible') {
            return '';
        }

        return `
            <button class="btn btn-primary btn-sm w-100" 
                    onclick="subjectsManager.choisirSujet(${subject.idsujets})"
                    data-sujet-id="${subject.idsujets}">
                <i class="fas fa-check me-1"></i> Choisir ce sujet
            </button>
        `;
    }

    async choisirSujet(sujetId) {
        if (!sujetId) {
            this.showError('ID du sujet invalide');
            return;
        }

        try {
            const confirmation = await Swal.fire({
                title: 'Confirmation',
                text: 'Voulez-vous vraiment choisir ce sujet ?',
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Oui, choisir',
                cancelButtonText: 'Annuler',
                showLoaderOnConfirm: true,
                preConfirm: async () => {
                    try {
                        const response = await fetch('../controller/choose_subject.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-Requested-With': 'XMLHttpRequest'
                            },
                            body: JSON.stringify({ sujetId }),
                            credentials: 'same-origin'
                        });

                        if (!response.ok) {
                            throw new Error('Erreur réseau');
                        }

                        return await response.json();
                    } catch (error) {
                        Swal.showValidationMessage(`Erreur: ${error.message}`);
                    }
                },
                allowOutsideClick: () => !Swal.isLoading()
            });

            if (confirmation.isConfirmed && confirmation.value.success) {
                await Swal.fire({
                    icon: 'success',
                    title: 'Succès',
                    text: 'Le sujet a été choisi avec succès'
                });
                this.loadAvailableSubjects();
            } else if (confirmation.value && !confirmation.value.success) {
                throw new Error(confirmation.value.message || 'Erreur lors du choix du sujet');
            }
        } catch (error) {
            console.error('Erreur:', error);
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: error.message || 'Une erreur est survenue'
            });
        }
    }

    showLoading() {
        if (this.subjectsContainer) {
            this.subjectsContainer.innerHTML = `
                <div class="text-center py-5">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Chargement...</span>
                    </div>
                </div>
            `;
        }
    }

    showError(message) {
        if (this.subjectsContainer) {
            this.subjectsContainer.innerHTML = `
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle me-2"></i>
                    ${this.escapeHtml(message || 'Une erreur est survenue')}
                </div>
            `;
        }
    }

    escapeHtml(unsafe) {
        if (typeof unsafe !== 'string') {
            return '';
        }
        return unsafe
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#039;");
    }
}

// Mettez simplement :
const subjectsManager = new SubjectsManager();