class TasksManager {
    constructor() {
        this.tasksContainer = document.getElementById('tasksContainer');
        if (!this.tasksContainer) {
            console.error('Container des tâches non trouvé');
            return;
        }
    }

    async loadTasks() {
        try {
            this.showLoading();
            const response = await fetch('../controller/get_tasks.php', {
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
            
            this.displayTasks(Array.isArray(data) ? data : []);
        } catch (error) {
            console.error('Erreur de chargement:', error);
            this.showError('Erreur lors du chargement des tâches');
        }
    }

    displayTasks(tasks) {
        if (!Array.isArray(tasks) || tasks.length === 0) {
            this.tasksContainer.innerHTML = `
                <div class="alert alert-info text-center">
                    <i class="fas fa-info-circle me-2"></i>
                    Aucune tâche disponible pour le moment.
                </div>
            `;
            return;
        }

        this.tasksContainer.innerHTML = tasks.map(task => this.createTaskCard(task)).join('');
    }

    createTaskCard(task) {
        return `
            <div class="task-card">
                <div class="d-flex justify-content-between align-items-start mb-2">
                    <h6 class="mb-0">${this.escapeHtml(task.description)}</h6>
                    <span class="status-badge badge bg-${this.getStatusColor(task.validation)}">
                        ${this.escapeHtml(task.validation)}
                    </span>
                </div>
                <p class="small text-muted mb-2">
                    <i class="fas fa-calendar-alt me-1"></i> 
                    Date: ${this.formatDate(task.dateTache)}
                </p>
                ${this.getComments(task)}
                ${this.getActionButtons(task)}
            </div>
        `;
    }

    getStatusColor(status) {
        const colors = {
            'En attente': 'warning',
            'Validé': 'success',
            'Rejeté': 'danger',
            'En cours': 'info'
        };
        return colors[status] || 'secondary';
    }

    getComments(task) {
        let comments = '';
        if (task.observationDirecteur) {
            comments += `
                <div class="small mb-2">
                    <strong>Directeur:</strong> ${this.escapeHtml(task.observationDirecteur)}
                </div>
            `;
        }
        if (task.observationEncadreur) {
            comments += `
                <div class="small mb-2">
                    <strong>Encadreur:</strong> ${this.escapeHtml(task.observationEncadreur)}
                </div>
            `;
        }
        return comments;
    }

    getActionButtons(task) {
        let buttons = `
            <div class="d-flex justify-content-between mt-3">
                <button class="btn btn-sm btn-outline-primary" onclick="tasksManager.viewTaskDetails(${task.idtaches})">
                    <i class="fas fa-eye me-1"></i> Détails
                </button>
        `;

        if (task.validation === 'En attente') {
            buttons += `
                <button class="btn btn-sm btn-primary" onclick="tasksManager.submitTask(${task.idtaches})">
                    <i class="fas fa-upload me-1"></i> Soumettre
                </button>
            `;
        }

        buttons += '</div>';
        return buttons;
    }

    showAddTaskForm() {
        Swal.fire({
            title: 'Nouvelle tâche',
            html: `
                <form id="newTaskForm">
                    <div class="mb-3">
                        <label for="taskDescription" class="form-label">Description</label>
                        <textarea class="form-control" id="taskDescription" rows="3" required></textarea>
                    </div>
                    <div class="mb-3">
                        <label for="taskFile" class="form-label">Fichier</label>
                        <input type="file" class="form-control" id="taskFile">
                    </div>
                </form>
            `,
            showCancelButton: true,
            confirmButtonText: 'Créer',
            cancelButtonText: 'Annuler',
            preConfirm: () => {
                const description = document.getElementById('taskDescription').value;
                const file = document.getElementById('taskFile').files[0];
                if (!description) {
                    Swal.showValidationMessage('La description est requise');
                }
                return { description, file };
            }
        }).then((result) => {
            if (result.isConfirmed) {
                this.createTask(result.value.description, result.value.file);
            }
        });
    }

    async createTask(description, file) {
        try {
            const formData = new FormData();
            formData.append('description', description);
            if (file) {
                formData.append('file', file);
            }

            const response = await fetch('../controller/create_task.php', {
                method: 'POST',
                body: formData,
                credentials: 'same-origin'
            });

            const result = await response.json();

            if (result.success) {
                Swal.fire('Succès', 'Tâche créée avec succès', 'success');
                this.loadTasks();
            } else {
                throw new Error(result.message || 'Erreur lors de la création de la tâche');
            }
        } catch (error) {
            console.error('Erreur:', error);
            Swal.fire('Erreur', error.message, 'error');
        }
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

    showLoading() {
        if (this.tasksContainer) {
            this.tasksContainer.innerHTML = `
                <div class="text-center py-5">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Chargement...</span>
                    </div>
                </div>
            `;
        }
    }

    showError(message) {
        if (this.tasksContainer) {
            this.tasksContainer.innerHTML = `
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

// Création de l'instance globale
window.tasksManager = new TasksManager();