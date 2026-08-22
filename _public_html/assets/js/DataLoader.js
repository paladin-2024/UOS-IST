// L'affichage des données avec du json
class DataLoader {
    constructor(config) {
        // Configuration par défaut avec validation
        this.validateConfig(config);
        
        this.config = {
            tableBodyId: '',
            loadingIndicatorId: '',
            searchInputId: '',
            loadMoreButtonId: '',
            endpoint: '',
            limit: APP_CONFIG.defaultLimit,
            columns: [],
            actions: [],
            dataKey: '',
            ...config
        };

        // Initialisation des états
        this.offset = 0;
        this.loading = false;
        this.hasMore = true;
        this.currentSearch = '';

        // Initialisation des éléments DOM
        this.initializeDOMElements();

        // Démarrage des écouteurs d'événements
        this.initializeEventListeners();
    }

    validateConfig(config) {
        const requiredFields = ['tableBodyId', 'loadingIndicatorId', 'searchInputId', 
                              'loadMoreButtonId', 'endpoint', 'dataKey'];
        
        const missingFields = requiredFields.filter(field => !config[field]);
        if (missingFields.length > 0) {
            throw new Error(`Champs requis manquants: ${missingFields.join(', ')}`);
        }

        if (!Array.isArray(config.columns) || config.columns.length === 0) {
            throw new Error('La configuration doit inclure au moins une colonne');
        }
    }

    initializeDOMElements() {
        this.tableBody = document.getElementById(this.config.tableBodyId);
        this.loadingIndicator = document.getElementById(this.config.loadingIndicatorId);
        this.searchInput = document.getElementById(this.config.searchInputId);
        this.buttonLoad = document.getElementById(this.config.loadMoreButtonId);

        const missingElements = [];
        if (!this.tableBody) missingElements.push(this.config.tableBodyId);
        if (!this.loadingIndicator) missingElements.push(this.config.loadingIndicatorId);
        if (!this.searchInput) missingElements.push(this.config.searchInputId);
        if (!this.buttonLoad) missingElements.push(this.config.loadMoreButtonId);

        if (missingElements.length > 0) {
            throw new Error(`Éléments DOM introuvables: ${missingElements.join(', ')}`);
        }
    }

    initializeEventListeners() {
        // Gestionnaire de défilement avec throttle
        window.addEventListener('scroll', this.throttle(() => {
            if ((window.innerHeight + window.scrollY) >= document.body.offsetHeight - 100) {
                this.loadData();
            }
        }, 250));

        // Gestionnaire du bouton "Charger plus"
        this.buttonLoad.addEventListener('click', () => this.loadData());

        // Gestionnaire de recherche avec debounce
        let searchTimeout;
        this.searchInput.addEventListener('input', (e) => {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                const newSearch = e.target.value.trim();
                if (this.currentSearch !== newSearch) {
                    this.currentSearch = newSearch;
                    this.hasMore = true;
                    this.loadData(true);
                }
            }, 300);
        });

        // Chargement initial
        this.loadData();
    }

    async loadData(resetTable = false) {
        if (this.loading || (!this.hasMore && !resetTable)) return;

        try {
            this.loading = true;
            this.loadingIndicator.classList.remove('d-none');
            this.buttonLoad.disabled = true;

            if (resetTable) {
                this.offset = 0;
                this.tableBody.innerHTML = '';
                // Supprimer les messages d'erreur précédents
                const existingError = this.tableBody.parentNode.querySelector('.alert-danger');
                if (existingError) {
                    existingError.remove();
                }
            }

            const url = new URL(this.config.endpoint, window.location.origin);
            url.searchParams.append('offset', this.offset);
            url.searchParams.append('limit', this.config.limit);
            url.searchParams.append('search', this.currentSearch);

            console.log('Chargement des données depuis:', url.toString());

            const response = await fetch(url);
            if (!response.ok) {
                throw new Error(`Erreur HTTP: ${response.status} ${response.statusText}`);
            }
            
            const data = await response.json();
            console.log('Données reçues:', data);
            
            if (!data || !data[this.config.dataKey]) {
                console.error('Format attendu:', this.config.dataKey);
                throw new Error('Format de données invalide');
            }

            this.renderData(data);

        } catch (error) {
            console.error('Erreur lors du chargement des données:', error);
            this.handleError(error.message);
        } finally {
            this.loading = false;
            this.loadingIndicator.classList.add('d-none');
            this.buttonLoad.disabled = false;
        }
    }

    renderData(data) {
        const items = data[this.config.dataKey];
        if (Array.isArray(items)) {
            items.forEach((item, index) => {
                const row = document.createElement('tr');
                let html = '';

                // Génération dynamique des colonnes
                this.config.columns.forEach(column => {
                    let value;
                    if (column.field === 'index') {
                        value = this.offset + index + 1;
                    } else {
                        value = this.getNestedValue(item, column.field);
                    }

                    html += `<td>${column.render ? 
                        column.render(value, item, index) : 
                        this.escapeHtml(value)}</td>`;
                });

                // Ajout des actions si définies
                if (this.config.actions && this.config.actions.length > 0) {
                    html += '<td class="text-end">';
                    this.config.actions.forEach(action => {
                        html += action.render(item);
                    });
                    html += '</td>';
                }

                row.innerHTML = html;
                this.tableBody.appendChild(row);
            });

            this.hasMore = data.hasMore;
            this.offset += items.length;
            this.buttonLoad.style.display = this.hasMore ? '' : 'none';
        }
    }

    getNestedValue(obj, path) {
        if (!path) return '';
        return path.split('.').reduce((current, key) => 
            (current && current[key] !== undefined) ? current[key] : '', obj);
    }

    escapeHtml(unsafe) {
        if (unsafe === undefined || unsafe === null) return '';
        return unsafe
            .toString()
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#039;");
    }

    handleError(message = 'Une erreur est survenue lors du chargement des données.') {
        // Supprime les messages d'erreur précédents
        const existingError = this.tableBody.parentNode.querySelector('.alert-danger');
        if (existingError) {
            existingError.remove();
        }

        const errorDiv = document.createElement('div');
        errorDiv.className = 'alert alert-danger';
        errorDiv.textContent = message;
        this.tableBody.parentNode.insertBefore(errorDiv, this.tableBody);
    }

    throttle(func, limit) {
        let inThrottle;
        return (...args) => {
            if (!inThrottle) {
                func.apply(this, args);
                inThrottle = true;
                setTimeout(() => inThrottle = false, limit);
            }
        }
    }
}