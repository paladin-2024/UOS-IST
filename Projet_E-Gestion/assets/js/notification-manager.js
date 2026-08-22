// Gestionnaire des notifications
class NotificationManager {
    constructor() {
        this.icon = document.getElementById('notificationIcon');
        this.dropdown = document.getElementById('notificationDropdown');
        this.isOpen = false;
        this.init();
    }

    init() {
        if (!this.icon || !this.dropdown) return;

        // Gestionnaire de clic sur l'icône
        this.icon.addEventListener('click', (e) => {
            e.stopPropagation();
            this.toggleDropdown();
        });

        // Fermer lors du clic en dehors
        document.addEventListener('click', (e) => {
            if (!e.target.closest('.notification-icon-wrapper')) {
                this.closeDropdown();
            }
        });

        // Empêcher la fermeture lors du clic dans le dropdown
        this.dropdown.addEventListener('click', (e) => {
            e.stopPropagation();
        });

        // Gestionnaire pour la touche Escape
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                this.closeDropdown();
            }
        });
    }

    toggleDropdown() {
        if (this.isOpen) {
            this.closeDropdown();
        } else {
            this.openDropdown();
        }
    }

    openDropdown() {
        this.dropdown.classList.add('show');
        this.isOpen = true;
    }

    closeDropdown() {
        this.dropdown.classList.remove('show');
        this.isOpen = false;
    }
}

// Initialisation
document.addEventListener('DOMContentLoaded', () => {
    new NotificationManager();
});