<?php
include "./views/include/header.php";
$pdo = Connexion::getInstance()->getPDO();

// Récupérer toutes les années académiques disponibles dans les palmarès
$query = "SELECT DISTINCT annee_academique FROM palmares_archives ORDER BY annee_academique DESC";
$stmt = $pdo->prepare($query);
$stmt->execute();
$annees = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<main id="main" class="main">
    <div class="pagetitle">
        <h1>RECHERCHE DANS LES PALMARÈS</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="?view=dashboard">Accueil</a></li>
                <li class="breadcrumb-item"><a href="?view=academique/palmares_archives">Palmarès archivés</a></li>
                <li class="breadcrumb-item active">Recherche avancée</li>
            </ol>
        </nav>
    </div>

    <section class="section">
        <div class="row">
            <!-- Panneau de recherche -->
            <div class="col-lg-4">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Options de recherche</h5>
                        
                        <ul class="nav nav-tabs nav-tabs-bordered d-flex" role="tablist">
                            <li class="nav-item flex-fill" role="presentation">
                                <button class="nav-link active w-100" id="search-by-criteria-tab" data-bs-toggle="tab" data-bs-target="#search-by-criteria" type="button" role="tab" aria-selected="true">
                                    <i class="bi bi-filter me-1"></i> Par critères
                                </button>
                            </li>
                            <li class="nav-item flex-fill" role="presentation">
                                <button class="nav-link w-100" id="search-by-student-tab" data-bs-toggle="tab" data-bs-target="#search-by-student" type="button" role="tab" aria-selected="false">
                                    <i class="bi bi-person-search me-1"></i> Par étudiant
                                </button>
                            </li>
                        </ul>

                        <div class="tab-content pt-3">
                            <!-- Recherche par critères -->
                            <div class="tab-pane fade show active" id="search-by-criteria" role="tabpanel">
                                <form id="searchCriteriaForm">
                                    <div class="mb-3">
                                        <label for="annee_academique" class="form-label">Année académique</label>
                                        <select class="form-select" id="annee_academique" name="annee_academique" required>
                                            <option value="">Sélectionnez une année académique</option>
                                            <?php foreach ($annees as $annee): ?>
                                                <option value="<?= htmlspecialchars($annee['annee_academique']) ?>">
                                                    <?= htmlspecialchars($annee['annee_academique']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="section" class="form-label">Section</label>
                                        <select class="form-select" id="section" name="section" disabled>
                                            <option value="">Sélectionnez d'abord une année académique</option>
                                        </select>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="promotion" class="form-label">Promotion</label>
                                        <select class="form-select" id="promotion" name="promotion" disabled>
                                            <option value="">Sélectionnez d'abord une section</option>
                                        </select>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="session" class="form-label">Session</label>
                                        <select class="form-select" id="session" name="session" disabled>
                                            <option value="">Sélectionnez d'abord une promotion</option>
                                        </select>
                                    </div>

                                    <div class="d-grid gap-2">
                                        <button type="submit" class="btn btn-primary">
                                            <i class="bi bi-search me-1"></i> Rechercher
                                        </button>
                                        <button type="button" id="resetCriteriaBtn" class="btn btn-outline-secondary">
                                            <i class="bi bi-arrow-counterclockwise me-1"></i> Réinitialiser
                                        </button>
                                    </div>
                                </form>
                            </div>
                            
                            <!-- Recherche par étudiant -->
                            <div class="tab-pane fade" id="search-by-student" role="tabpanel">
                                <form id="searchStudentForm">
                                    <div class="mb-4">
                                        <div class="position-relative">
                                            <input type="text" class="form-control form-control-lg" id="student_search" name="student_search" placeholder="Nom ou matricule de l'étudiant" required>
                                            <div class="position-absolute top-50 end-0 translate-middle-y pe-3">
                                                <i class="bi bi-search text-muted"></i>
                                            </div>
                                        </div>
                                        <div class="form-text">Entrez au moins 3 caractères pour lancer la recherche</div>
                                    </div>

                                    <div class="form-check mb-3">
                                        <input class="form-check-input" type="checkbox" value="1" id="search_all_years" name="search_all_years" checked>
                                        <label class="form-check-label" for="search_all_years">
                                            Rechercher dans toutes les années académiques
                                        </label>
                                    </div>
                                    
                                    <div id="student_year_select" class="mb-3" style="display: none;">
                                        <label for="student_year" class="form-label">Année académique</label>
                                        <select class="form-select" id="student_year" name="student_year">
                                            <option value="">Toutes les années</option>
                                            <?php foreach ($annees as $annee): ?>
                                                <option value="<?= htmlspecialchars($annee['annee_academique']) ?>">
                                                    <?= htmlspecialchars($annee['annee_academique']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>

                                    <div class="d-grid gap-2">
                                        <button type="submit" class="btn btn-primary">
                                            <i class="bi bi-search me-1"></i> Rechercher
                                        </button>
                                        <button type="button" id="resetStudentBtn" class="btn btn-outline-secondary">
                                            <i class="bi bi-arrow-counterclockwise me-1"></i> Réinitialiser
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Aide et informations -->
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Aide</h5>
                        <div class="accordion" id="helpAccordion">
                            <div class="accordion-item">
                                <h2 class="accordion-header" id="headingOne">
                                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseOne" aria-expanded="false" aria-controls="collapseOne">
                                        Comment rechercher par critères?
                                    </button>
                                </h2>
                                <div id="collapseOne" class="accordion-collapse collapse" aria-labelledby="headingOne" data-bs-parent="#helpAccordion">
                                    <div class="accordion-body">
                                        <p>Pour rechercher par critères, suivez ces étapes:</p>
                                        <ol>
                                            <li>Sélectionnez une année académique</li>
                                            <li>Choisissez une section parmi celles disponibles</li>
                                            <li>Sélectionnez une promotion</li>
                                            <li>Choisissez une session (optionnel)</li>
                                            <li>Cliquez sur Rechercher</li>
                                        </ol>
                                    </div>
                                </div>
                            </div>
                            <div class="accordion-item">
                                <h2 class="accordion-header" id="headingTwo">
                                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseTwo" aria-expanded="false" aria-controls="collapseTwo">
                                        Comment rechercher un étudiant?
                                    </button>
                                </h2>
                                <div id="collapseTwo" class="accordion-collapse collapse" aria-labelledby="headingTwo" data-bs-parent="#helpAccordion">
                                    <div class="accordion-body">
                                        <p>Pour rechercher un étudiant spécifique:</p>
                                        <ol>
                                            <li>Cliquez sur l'onglet "Par étudiant"</li>
                                            <li>Entrez le nom ou le matricule de l'étudiant (minimum 3 caractères)</li>
                                            <li>Choisissez si vous voulez rechercher dans toutes les années académiques</li>
                                            <li>Cliquez sur Rechercher</li>
                                        </ol>
                                        <p>Le système affichera tous les palmarès contenant cet étudiant.</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Résultats de recherche -->
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <h5 class="card-title">Résultats de la recherche</h5>
                            <div id="exportButtons" style="display: none;">
                                <a href="#" id="exportCriteriaExcel" class="btn btn-sm btn-outline-success">
                                    <i class="bi bi-file-excel me-1"></i> Exporter Excel
                                </a>
                                <a href="#" id="exportCriteriaPdf" class="btn btn-sm btn-outline-danger">
                                    <i class="bi bi-file-pdf me-1"></i> Exporter PDF
                                </a>
                            </div>
                        </div>
                        
                        <!-- État initial -->
                        <div id="initial-state" class="text-center py-5">
                            <i class="bi bi-search" style="font-size: 5rem; opacity: 0.5; color: #6c757d; display: block;" aria-hidden="true"></i>
                            <h4 class="text-muted">Lancez une recherche pour voir les résultats</h4>
                            <p class="text-muted">Utilisez les options de recherche à gauche pour trouver des palmarès ou des étudiants</p>
                        </div>

                        
                        <!-- Chargement -->
                        <div id="loading-state" class="text-center py-5" style="display: none;">
                            <div class="spinner-border text-primary" role="status">
                                <span class="visually-hidden">Chargement...</span>
                            </div>
                            <p class="mt-2">Recherche en cours...</p>
                        </div>
                        
                        <!-- Résultats par critères -->
                        <div id="results-criteria" style="display: none;">
                            <!-- Palmarès trouvés -->
                            <div id="palmares-results">
                                <!-- Contenu chargé dynamiquement -->
                            </div>
                        </div>
                        
                        <!-- Résultats par étudiant -->
                        <div id="results-student" style="display: none;">
                            <!-- Étudiant trouvé -->
                            <div id="student-info" class="mb-4">
                                <!-- Informations sur l'étudiant trouvé -->
                            </div>
                            
                            <!-- Palmarès où l'étudiant apparaît -->
                            <div id="student-palmares-list">
                                <!-- Liste des palmarès où l'étudiant apparaît -->
                            </div>
                        </div>
                        
                        <!-- Aucun résultat -->
                        <div id="no-results" class="text-center py-5" style="display: none;">
                            <i class="bi bi-search text-muted" style="font-size: 3rem;"></i>
                            <h4 class="text-muted mt-3">Aucun résultat trouvé</h4>
                            <p class="text-muted">Essayez de modifier vos critères de recherche</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        </section>
</main>

<!-- Modals pour les détails -->
<div class="modal fade" id="palmares-details-modal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="palmares-modal-title">Détails du palmarès</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="palmares-details-content">
                <!-- Informations générales -->
                <div class="card mb-3">
                    <div class="card-body">
                        <h5 class="card-title">Informations générales</h5>
                        <div class="row">
                            <div class="col-md-6">
                                <p><strong>Année académique:</strong> <span id="modal-annee-academique"></span></p>
                                <p><strong>Section:</strong> <span id="modal-section"></span></p>
                                <p><strong>Promotion:</strong> <span id="modal-promotion"></span></p>
                            </div>
                            <div class="col-md-6">
                                <p><strong>Session:</strong> <span id="modal-session"></span></p>
                                <p><strong>Date de création:</strong> <span id="modal-date-creation"></span></p>
                                <p><strong>Nombre d'étudiants:</strong> <span id="modal-nb-etudiants"></span></p>
                            </div>
                        </div>
                        
                        <!-- Description (affichée seulement si disponible) -->
                        <div id="modal-description-container" style="display: none;">
                            <hr>
                            <h6>Description:</h6>
                            <p id="modal-description"></p>
                        </div>
                        
                        <!-- Lien vers le PDF scanné si disponible -->
                        <div id="modal-pdf-container" style="display: none;">
                            <hr>
                            <a href="#" id="modal-pdf-link" target="_blank" class="btn btn-sm btn-outline-danger">
                                <i class="bi bi-file-pdf"></i> Voir le fichier scanné
                            </a>
                        </div>
                    </div>
                </div>
                
                <!-- Liste des étudiants -->
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Liste des étudiants</h5>
                        <div class="table-responsive">
                            <table class="table table-bordered table-hover">
                                <thead class="table-light">
                                    <tr>
                                        <th width="10%">Rang</th>
                                        <th width="20%">Matricule</th>
                                        <th width="40%">Nom complet</th>
                                        <th width="15%">Pourcentage</th>
                                        <th width="15%">Décision</th>
                                    </tr>
                                </thead>
                                <tbody id="modal-etudiants-table-body">
                                    <!-- Contenu généré dynamiquement -->
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fermer</button>
                <a href="#" class="btn btn-primary" id="view-full-palmares">
                    <i class="bi bi-eye"></i> Voir le palmarès complet
                </a>
            </div>
        </div>
    </div>
</div>


<script>
document.addEventListener('DOMContentLoaded', function() {
    // Éléments DOM
    const anneeAcademiqueSelect = document.getElementById('annee_academique');
    const sectionSelect = document.getElementById('section');
    const promotionSelect = document.getElementById('promotion');
    const sessionSelect = document.getElementById('session');
    const searchCriteriaForm = document.getElementById('searchCriteriaForm');
    const searchStudentForm = document.getElementById('searchStudentForm');
    const resetCriteriaBtn = document.getElementById('resetCriteriaBtn');
    const resetStudentBtn = document.getElementById('resetStudentBtn');
    const searchAllYears = document.getElementById('search_all_years');
    const studentYearSelect = document.getElementById('student_year_select');
    const studentSearch = document.getElementById('student_search');
    
    // Conteneurs de résultats
    const initialState = document.getElementById('initial-state');
    const loadingState = document.getElementById('loading-state');
    const resultsCriteria = document.getElementById('results-criteria');
    const resultsStudent = document.getElementById('results-student');
    const noResults = document.getElementById('no-results');
    const palmaresResults = document.getElementById('palmares-results');
    const studentInfo = document.getElementById('student-info');
    const studentPalmaresList = document.getElementById('student-palmares-list');
    const exportButtons = document.getElementById('exportButtons');
    
    // Modals
    const palmaresDetailsModal = new bootstrap.Modal(document.getElementById('palmares-details-modal'));
    const viewFullPalmaresBtn = document.getElementById('view-full-palmares');
    
    // Initialisation de Select2 pour tous les selects
    $(anneeAcademiqueSelect).select2({
        placeholder: "Sélectionnez une année académique",
        allowClear: true,
        width: '100%'
    });
    
    $(sectionSelect).select2({
        placeholder: "Sélectionnez d'abord une année académique",
        allowClear: true,
        width: '100%'
    });
    
    $(promotionSelect).select2({
        placeholder: "Sélectionnez d'abord une section",
        allowClear: true,
        width: '100%'
    });
    
    $(sessionSelect).select2({
        placeholder: "Sélectionnez d'abord une promotion",
        allowClear: true,
        width: '100%'
    });
    
    $(document.getElementById('student_year')).select2({
        placeholder: "Toutes les années",
        allowClear: true,
        width: '100%'
    });
    
    // Chargement dynamique des sections en fonction de l'année académique
    // Important: utiliser l'événement 'select2:select' au lieu de 'change'
    $(anneeAcademiqueSelect).on('select2:select', function() {
        const anneeAcademique = this.value;
        
        // Réinitialiser les sélecteurs dépendants
        resetSelect(sectionSelect, "Sélectionnez d'abord une année académique");
        resetSelect(promotionSelect, "Sélectionnez d'abord une section");
        resetSelect(sessionSelect, "Sélectionnez d'abord une promotion");
        
        sectionSelect.disabled = !anneeAcademique;
        promotionSelect.disabled = true;
        sessionSelect.disabled = true;
        
        if (!anneeAcademique) return;
        
        // Charger les sections disponibles
        loadingSelectOptions(sectionSelect, "Chargement des sections...");
        
        fetch(`controller/get_palmares_sections.php?annee_academique=${encodeURIComponent(anneeAcademique)}`)
            .then(response => response.json())
            .then(data => {
                if (data.length === 0) {
                    resetSelect(sectionSelect, "Aucune section disponible");
                    return;
                }
                
                resetSelect(sectionSelect, "Sélectionnez une section");
                data.forEach(section => {
                    const option = new Option(section.section, section.section);
                    sectionSelect.add(option);
                });
                
                sectionSelect.disabled = false;
                $(sectionSelect).select2({
                    placeholder: "Sélectionnez une section",
                    allowClear: true,
                    width: '100%'
                });
            })
            .catch(error => {
                console.error('Erreur lors du chargement des sections:', error);
                resetSelect(sectionSelect, "Erreur de chargement");
            });
    });
    
    // Pour gérer également le cas où l'utilisateur efface la sélection
    $(anneeAcademiqueSelect).on('select2:clear', function() {
        resetSelect(sectionSelect, "Sélectionnez d'abord une année académique");
        resetSelect(promotionSelect, "Sélectionnez d'abord une section");
        resetSelect(sessionSelect, "Sélectionnez d'abord une promotion");
        
        sectionSelect.disabled = true;
        promotionSelect.disabled = true;
        sessionSelect.disabled = true;
        
        $(sectionSelect).select2({
            placeholder: "Sélectionnez d'abord une année académique",
            allowClear: true,
            width: '100%'
        });
        $(promotionSelect).select2({
            placeholder: "Sélectionnez d'abord une section",
            allowClear: true,
            width: '100%'
        });
        $(sessionSelect).select2({
            placeholder: "Sélectionnez d'abord une promotion",
            allowClear: true,
            width: '100%'
        });
    });
    
    // Chargement dynamique des promotions en fonction de la section
    $(sectionSelect).on('select2:select', function() {
        const section = this.value;
        const anneeAcademique = anneeAcademiqueSelect.value;
        
        // Réinitialiser les sélecteurs dépendants
        resetSelect(promotionSelect, "Sélectionnez d'abord une section");
        resetSelect(sessionSelect, "Sélectionnez d'abord une promotion");
        
        promotionSelect.disabled = !section;
        sessionSelect.disabled = true;
        
        if (!section) return;
        
        // Charger les promotions disponibles
        loadingSelectOptions(promotionSelect, "Chargement des promotions...");
        
        fetch(`controller/get_palmares_promotions.php?annee_academique=${encodeURIComponent(anneeAcademique)}&section=${encodeURIComponent(section)}`)
            .then(response => response.json())
            .then(data => {
                if (data.length === 0) {
                    resetSelect(promotionSelect, "Aucune promotion disponible");
                    return;
                }
                
                resetSelect(promotionSelect, "Sélectionnez une promotion");
                data.forEach(promotion => {
                    const option = new Option(promotion.promotion, promotion.promotion);
                    promotionSelect.add(option);
                });
                
                promotionSelect.disabled = false;
                $(promotionSelect).select2({
                    placeholder: "Sélectionnez une promotion",
                    allowClear: true,
                    width: '100%'
                });
            })
            .catch(error => {
                console.error('Erreur lors du chargement des promotions:', error);
                resetSelect(promotionSelect, "Erreur de chargement");
            });
    });
    
    $(sectionSelect).on('select2:clear', function() {
        resetSelect(promotionSelect, "Sélectionnez d'abord une section");
        resetSelect(sessionSelect, "Sélectionnez d'abord une promotion");
        
        promotionSelect.disabled = true;
        sessionSelect.disabled = true;
        
        $(promotionSelect).select2({
            placeholder: "Sélectionnez d'abord une section",
            allowClear: true,
            width: '100%'
        });
        $(sessionSelect).select2({
            placeholder: "Sélectionnez d'abord une promotion",
            allowClear: true,
            width: '100%'
        });
    });
    
    // Chargement dynamique des sessions en fonction de la promotion
    $(promotionSelect).on('select2:select', function() {
        const promotion = this.value;
        const section = sectionSelect.value;
        const anneeAcademique = anneeAcademiqueSelect.value;
        
        resetSelect(sessionSelect, "Sélectionnez d'abord une promotion");
        sessionSelect.disabled = !promotion;
        
        if (!promotion) return;
        
        // Charger les sessions disponibles
        loadingSelectOptions(sessionSelect, "Chargement des sessions...");
        
        fetch(`controller/get_palmares_sessions.php?annee_academique=${encodeURIComponent(anneeAcademique)}&section=${encodeURIComponent(section)}&promotion=${encodeURIComponent(promotion)}`)
            .then(response => response.json())
            .then(data => {
                if (data.length === 0) {
                    resetSelect(sessionSelect, "Aucune session disponible");
                    return;
                }
                
                resetSelect(sessionSelect, "Toutes les sessions");
                sessionSelect.options[0].value = ""; // Pour permettre la recherche sur toutes les sessions
                
                data.forEach(session => {
                    const option = new Option(session.session, session.session);
                    sessionSelect.add(option);
                });
                
                sessionSelect.disabled = false;
                $(sessionSelect).select2({
                    placeholder: "Sélectionnez une session",
                    allowClear: true,
                    width: '100%'
                });
            })
            .catch(error => {
                console.error('Erreur lors du chargement des sessions:', error);
                resetSelect(sessionSelect, "Erreur de chargement");
            });
    });
    
    $(promotionSelect).on('select2:clear', function() {
        resetSelect(sessionSelect, "Sélectionnez d'abord une promotion");
        sessionSelect.disabled = true;
        
        $(sessionSelect).select2({
            placeholder: "Sélectionnez d'abord une promotion",
            allowClear: true,
            width: '100%'
        });
    });
    
    // Gestion de la recherche par critères
searchCriteriaForm.addEventListener('submit', function(e) {
    e.preventDefault();
    
    const anneeAcademique = anneeAcademiqueSelect.value;
    const section = sectionSelect.value;
    const promotion = promotionSelect.value;
    const session = sessionSelect.value;
    
    if (!anneeAcademique || !section || !promotion) {
        Swal.fire({
            icon: 'warning',
            title: 'Critères incomplets',
            text: 'Veuillez au moins sélectionner une année académique, une section et une promotion.'
        });
        return;
    }
    
    // Afficher le chargement
    showState('loading');
    
    // Construire l'URL de recherche
    let searchUrl = `controller/search_palmares_criteria.php?annee_academique=${encodeURIComponent(anneeAcademique)}&section=${encodeURIComponent(section)}&promotion=${encodeURIComponent(promotion)}`;
    
    if (session) {
        searchUrl += `&session=${encodeURIComponent(session)}`;
    }
    
    console.log("URL de recherche:", searchUrl); // Ajout pour déboguer
    
    // Effectuer la recherche
    fetch(searchUrl)
        .then(response => {
            // Vérifier si la réponse est OK avant de la parser
            if (!response.ok) {
                throw new Error(`Erreur HTTP: ${response.status}`);
            }
            console.log("Réponse reçue avec statut:", response.status);
            return response.text(); // Obtenir d'abord le texte brut pour le déboguer
        })
        .then(text => {
            console.log("Réponse brute:", text);
            try {
                return JSON.parse(text); // Parser le texte en JSON
            } catch (e) {
                console.error("Erreur de parsing JSON:", e);
                throw new Error('Réponse invalide du serveur');
            }
        })
        .then(data => {
            console.log("Données parsées:", data);
            if (!data || data.length === 0) {
                showState('no-results');
                return;
            }
            
            // Vérifier si nous avons une erreur
            if (data.error) {
                throw new Error(data.error);
            }
            
            // Afficher les résultats
            renderPalmaresResults(data);
            showState('criteria');
            exportButtons.style.display = 'none';
        })
        .catch(error => {
            console.error('Erreur lors de la recherche:', error);
            Swal.fire({
                icon: 'error',
                title: 'Erreur de recherche',
                text: 'Une erreur est survenue lors de la recherche: ' + error.message
            });
            showState('initial');
        });
});

    // Gestion de la recherche par étudiant
searchStudentForm.addEventListener('submit', function(e) {
    e.preventDefault();
    
    const searchTerm = studentSearch.value.trim();
    const searchAllYearsChecked = searchAllYears.checked;
    const studentYear = document.getElementById('student_year').value;
    
    if (searchTerm.length < 3) {
        Swal.fire({
            icon: 'warning',
            title: 'Recherche trop courte',
            text: 'Veuillez entrer au moins 3 caractères pour la recherche.'
        });
        return;
    }
    
    // Afficher le chargement
    showState('loading');
    
    // Construire l'URL de recherche
    let searchUrl = `controller/search_palmares_student.php?search=${encodeURIComponent(searchTerm)}`;
    
    if (!searchAllYearsChecked && studentYear) {
        searchUrl += `&annee_academique=${encodeURIComponent(studentYear)}`;
    }
    
    console.log("URL de recherche étudiant:", searchUrl);
    
    // Effectuer la recherche
    fetch(searchUrl)
        .then(response => {
            // Vérifier si la réponse est OK avant de la parser
            if (!response.ok) {
                throw new Error(`Erreur HTTP: ${response.status}`);
            }
            return response.text();
        })
        .then(text => {
            console.log("Réponse brute de la recherche étudiant:", text);
            try {
                return JSON.parse(text);
            } catch (e) {
                console.error("Erreur de parsing JSON:", e);
                throw new Error('Réponse invalide du serveur');
            }
        })
        .then(data => {
            console.log("Données de recherche étudiant:", data);
            
            // Vérifier d'abord si nous avons une erreur explicite
            if (data.error) {
                throw new Error(data.error);
            }
            
            // Vérifier que nous avons des données valides
            if (!data.student || !data.palmares || data.palmares.length === 0) {
                showState('no-results');
                return;
            }
            
            // Afficher les informations de l'étudiant
            renderStudentInfo(data.student);
            
            // Afficher les palmarès où l'étudiant apparaît
            renderStudentPalmares(data.palmares);
            
            showState('student');
            
            // Mettre à jour les boutons d'exportation (si vous les implémentez)
            // updateExportButtons(searchTerm, studentYear, searchAllYearsChecked);
        })
        .catch(error => {
            console.error('Erreur lors de la recherche étudiant:', error);
            Swal.fire({
                icon: 'error',
                title: 'Erreur de recherche',
                text: 'Une erreur est survenue lors de la recherche: ' + error.message
            });
            showState('initial');
        });
});

// Fonction auxiliaire pour mettre à jour les boutons d'exportation (optionnel)
function updateExportButtons(searchTerm, studentYear, searchAllYears) {
    const exportExcelBtn = document.getElementById('exportExcelBtn');
    const exportPdfBtn = document.getElementById('exportPdfBtn');
    
    if (exportExcelBtn && exportPdfBtn) {
        const baseUrl = 'controller/export_palmares_search.php?type=student';
        const searchParam = `&search=${encodeURIComponent(searchTerm)}`;
        const yearParam = !searchAllYears && studentYear ? `&annee_academique=${encodeURIComponent(studentYear)}` : '';
        
        exportExcelBtn.onclick = function() {
            window.location.href = baseUrl + '&format=excel' + searchParam + yearParam;
        };
        
        exportPdfBtn.onclick = function() {
            window.location.href = baseUrl + '&format=pdf' + searchParam + yearParam;
        };
    }
}

// Mises à jour des fonctions d'affichage
function renderStudentInfo(student) {
    studentInfo.innerHTML = `
        <div class="card">
            <div class="card-body">
                <h5 class="card-title">${student.nom_complet}</h5>
                ${student.matricule ? `<p class="card-text"><strong>Matricule:</strong> ${student.matricule}</p>` : ''}
                <p class="card-text"><strong>Nombre de palmarès:</strong> ${student.count}</p>
            </div>
        </div>
    `;
}

    
    
    // Gestion de l'affichage des détails d'un palmarès
    document.body.addEventListener('click', function(e) {
        const viewDetailsBtn = e.target.closest('.view-palmares-details');
        if (!viewDetailsBtn) return;
        
        e.preventDefault();
        
        const palmaresId = viewDetailsBtn.dataset.id;
        const highlightedStudent = viewDetailsBtn.dataset.studentId;
        
        // Charger les détails du palmarès
        fetch(`controller/get_palmares_details.php?id=${palmaresId}${highlightedStudent ? '&student_id=' + highlightedStudent : ''}`)
            .then(response => response.json())
            .then(data => {
                if (data.error) {
                    throw new Error(data.error);
                }
                
                // Mettre à jour le modal avec les détails
                updatePalmaresDetailsModal(data);
                
                // Afficher le modal
                palmaresDetailsModal.show();
                
                // Mettre à jour le bouton pour voir le palmarès complet
                viewFullPalmaresBtn.href = `?view=academique/voir_palmares_archive&id=${palmaresId}`;
            })
            .catch(error => {
                console.error('Erreur lors du chargement des détails:', error);
                Swal.fire({
                    icon: 'error',
                    title: 'Erreur',
                    text: error.message || 'Une erreur est survenue lors du chargement des détails.'
                });
            });
    });
    
    // Gestion du cochage de la case "Toutes les années"
    searchAllYears.addEventListener('change', function() {
        studentYearSelect.style.display = this.checked ? 'none' : 'block';
    });
    
    // Réinitialisation des formulaires
    resetCriteriaBtn.addEventListener('click', function(e) {
        e.preventDefault();
        searchCriteriaForm.reset();
        
        // Réinitialiser tous les select2
        $(anneeAcademiqueSelect).val(null).trigger('change');
        $(sectionSelect).val(null).trigger('change');
        $(promotionSelect).val(null).trigger('change');
        $(sessionSelect).val(null).trigger('change');
        
        sectionSelect.disabled = true;
        promotionSelect.disabled = true;
        sessionSelect.disabled = true;
        
        // Réinitialiser l'affichage
        showState('initial');
    });
    
    resetStudentBtn.addEventListener('click', function(e) {
        e.preventDefault();
        searchStudentForm.reset();
        studentSearch.value = '';
        searchAllYears.checked = true;
        studentYearSelect.style.display = 'none';
        $(document.getElementById('student_year')).val(null).trigger('change');
        
        // Réinitialiser l'affichage
        showState('initial');
    });
    
    // Fonctions utilitaires
    function resetSelect(select, placeholder) {
        select.innerHTML = '';
        const option = new Option(placeholder, '', true, true);
        option.disabled = true;
        select.add(option);
        $(select).val(null).trigger('change');
    }
    
    function loadingSelectOptions(select, loadingText) {
        select.innerHTML = '';
        const option = new Option(loadingText, '', true, true);
        option.disabled = true;
        select.add(option);
    }
    
    function showState(state) {
        // Masquer tous les états
        initialState.style.display = 'none';
        loadingState.style.display = 'none';
        resultsCriteria.style.display = 'none';
        resultsStudent.style.display = 'none';
        noResults.style.display = 'none';
        exportButtons.style.display = 'none';
        
        // Afficher l'état demandé
        switch (state) {
            case 'initial':
                initialState.style.display = 'block';
                break;
            case 'loading':
                loadingState.style.display = 'block';
                break;
            case 'criteria':
                resultsCriteria.style.display = 'block';
                break;
            case 'student':
                resultsStudent.style.display = 'block';
                break;
            case 'no-results':
                noResults.style.display = 'block';
                break;
        }
    }
    
    function renderPalmaresResults(palmaresData) {
        palmaresResults.innerHTML = '';
        
        palmaresData.forEach(palmares => {
            const card = document.createElement('div');
            card.className = 'card mb-3';
            
            const cardHeader = document.createElement('div');
            cardHeader.className = 'card-header d-flex justify-content-between align-items-center';
            
            const cardTitle = document.createElement('h5');
            cardTitle.className = 'card-title mb-0';
            cardTitle.textContent = palmares.designation || `Palmarès ${palmares.promotion} - ${palmares.annee_academique}`;
            
            const actionsGroup = document.createElement('div');
            actionsGroup.className = 'btn-group';
            
            const viewBtn = document.createElement('button');
            viewBtn.className = 'btn btn-sm btn-primary view-palmares-details';
            viewBtn.dataset.id = palmares.idpalmares;
            viewBtn.innerHTML = '<i class="bi bi-eye"></i> Détails';
            
            const viewFullBtn = document.createElement('a');
            viewFullBtn.className = 'btn btn-sm btn-info';
            viewFullBtn.href = `?view=academique/voir_palmares_archive&id=${palmares.idpalmares}`;
            viewFullBtn.innerHTML = '<i class="bi bi-file-text"></i> Voir complet';
            
            actionsGroup.appendChild(viewBtn);
            actionsGroup.appendChild(viewFullBtn);
            
            cardHeader.appendChild(cardTitle);
            cardHeader.appendChild(actionsGroup);
            
            const cardBody = document.createElement('div');
            cardBody.className = 'card-body';
            
            const infoRow = document.createElement('div');
            infoRow.className = 'row';
            
            const leftCol = document.createElement('div');
            leftCol.className = 'col-md-6';
            
            const rightCol = document.createElement('div');
            rightCol.className = 'col-md-6 text-md-end';
            
            leftCol.innerHTML = `
                <p class="mb-1"><strong>Section:</strong> ${palmares.section}</p>
                <p class="mb-1"><strong>Promotion:</strong> ${palmares.promotion}</p>
                <p class="mb-0"><strong>Session:</strong> ${palmares.session}</p>
            `;
            
            rightCol.innerHTML = `
                <p class="mb-1"><strong>Année académique:</strong> ${palmares.annee_academique}</p>
                <p class="mb-1"><strong>Étudiants:</strong> ${palmares.nb_etudiants}</p>
                <p class="mb-0"><strong>Date:</strong> ${new Date(palmares.date_creation).toLocaleDateString()}</p>
            `;
            
            infoRow.appendChild(leftCol);
            infoRow.appendChild(rightCol);
            
            cardBody.appendChild(infoRow);
            
            card.appendChild(cardHeader);
            card.appendChild(cardBody);
            
            palmaresResults.appendChild(card);
        });
        
        // Mise à jour des liens d'exportation
        const anneeAcademique = anneeAcademiqueSelect.value;
        const section = sectionSelect.value;
        const promotion = promotionSelect.value;
        const session = sessionSelect.value;
        
        document.getElementById('exportCriteriaExcel').href = `controller/export_palmares_search.php?type=criteria&format=excel&annee_academique=${encodeURIComponent(anneeAcademique)}&section=${encodeURIComponent(section)}&promotion=${encodeURIComponent(promotion)}${session ? '&session=' + encodeURIComponent(session) : ''}`;
        
        document.getElementById('exportCriteriaPdf').href = `controller/export_palmares_search.php?type=criteria&format=pdf&annee_academique=${encodeURIComponent(anneeAcademique)}&section=${encodeURIComponent(section)}&promotion=${encodeURIComponent(promotion)}${session ? '&session=' + encodeURIComponent(session) : ''}`;
    }
    
    
    
    function renderStudentPalmares(palmares) {
        studentPalmaresList.innerHTML = '';
        
        palmares.forEach(result => {
            const card = document.createElement('div');
            card.className = 'card mb-3';
            
            const cardHeader = document.createElement('div');
            cardHeader.className = 'card-header d-flex justify-content-between align-items-center';
            
            const cardTitle = document.createElement('h5');
            cardTitle.className = 'card-title mb-0';
            cardTitle.textContent = `${result.promotion} - ${result.annee_academique}`;
            
            const actionsGroup = document.createElement('div');
            actionsGroup.className = 'btn-group';
            
            const viewBtn = document.createElement('button');
            viewBtn.className = 'btn btn-sm btn-primary view-palmares-details';
            viewBtn.dataset.id = result.idpalmares;
            viewBtn.dataset.studentId = result.id;
            viewBtn.innerHTML = '<i class="bi bi-eye"></i> Détails';
            
            const viewFullBtn = document.createElement('a');
            viewFullBtn.className = 'btn btn-sm btn-info';
            viewFullBtn.href = `?view=academique/voir_palmares_archive&id=${result.idpalmares}`;
            viewFullBtn.innerHTML = '<i class="bi bi-file-text"></i> Voir complet';
            
            actionsGroup.appendChild(viewBtn);
            actionsGroup.appendChild(viewFullBtn);
            
            cardHeader.appendChild(cardTitle);
            cardHeader.appendChild(actionsGroup);
            
            const cardBody = document.createElement('div');
            cardBody.className = 'card-body';
            
            const infoRow = document.createElement('div');
            infoRow.className = 'row';
            
            const leftCol = document.createElement('div');
            leftCol.className = 'col-md-6';
            
            const rightCol = document.createElement('div');
            rightCol.className = 'col-md-6';
            
            leftCol.innerHTML = `
                <p class="mb-1"><strong>Section:</strong> ${result.section}</p>
                <p class="mb-1"><strong>Promotion:</strong> ${result.promotion}</p>
                <p class="mb-0"><strong>Session:</strong> ${result.session}</p>
            `;
            
            const pourcentage = parseFloat(result.pourcentage);
            let badgeClass = 'bg-danger';
            
            if (pourcentage >= 80) badgeClass = 'bg-success';
            else if (pourcentage >= 70) badgeClass = 'bg-info';
            else if (pourcentage >= 60) badgeClass = 'bg-primary';
            else if (pourcentage >= 50) badgeClass = 'bg-warning';
            
            rightCol.innerHTML = `
                <p class="mb-1"><strong>Pourcentage:</strong> <span class="badge ${badgeClass}">${pourcentage.toFixed(2)}%</span></p>
                <p class="mb-1"><strong>Rang:</strong> ${result.rang || 'Non défini'}</p>
                <p class="mb-0"><strong>Décision:</strong> ${result.decision || 'Non définie'}</p>
            `;
            
            infoRow.appendChild(leftCol);
            infoRow.appendChild(rightCol);
            
            cardBody.appendChild(infoRow);
            
            card.appendChild(cardHeader);
            card.appendChild(cardBody);
            
            studentPalmaresList.appendChild(card);
        });
    }
    
    function updatePalmaresDetailsModal(data) {
    const palmares = data.palmares;
    const etudiants = data.etudiants;
    const highlightedStudent = data.highlighted_student;
    
    // Mettre à jour le titre du modal
    document.getElementById('palmares-modal-title').textContent = palmares.designation || `Palmarès ${palmares.promotion} - ${palmares.annee_academique}`;
    
    // Mettre à jour les informations générales
    document.getElementById('modal-annee-academique').textContent = palmares.annee_academique;
    document.getElementById('modal-section').textContent = palmares.section;
    document.getElementById('modal-promotion').textContent = palmares.promotion;
    document.getElementById('modal-session').textContent = palmares.session;
    document.getElementById('modal-date-creation').textContent = new Date(palmares.date_creation).toLocaleDateString();
    document.getElementById('modal-nb-etudiants').textContent = etudiants.length;
    
    // Mettre à jour la description si disponible
    const descriptionContainer = document.getElementById('modal-description-container');
    const description = document.getElementById('modal-description');
    
    if (palmares.description) {
        description.textContent = palmares.description;
        descriptionContainer.style.display = 'block';
    } else {
        descriptionContainer.style.display = 'none';
    }
    
    // Mettre à jour le tableau des étudiants
    const tableBody = document.getElementById('modal-etudiants-table-body');
    tableBody.innerHTML = '';
    
    etudiants.forEach((etudiant, index) => {
        const tr = document.createElement('tr');
        
        // Vérifier si c'est l'étudiant à mettre en évidence
        if (highlightedStudent && etudiant.id == highlightedStudent) {
            tr.className = 'table-primary';
        }
        
        const pourcentage = parseFloat(etudiant.pourcentage);
        let badgeClass = 'bg-danger';
        
        if (pourcentage >= 80) badgeClass = 'bg-success';
        else if (pourcentage >= 70) badgeClass = 'bg-info';
        else if (pourcentage >= 60) badgeClass = 'bg-primary';
        else if (pourcentage >= 50) badgeClass = 'bg-warning';
        
        // Utilisez index+1 comme rang si le champ rang n'existe pas
        const rang = etudiant.rang !== undefined ? etudiant.rang : index + 1;
        
        tr.innerHTML = `
            <td class="text-center">${rang || '-'}</td>
            <td>${etudiant.matricule || '-'}</td>
            <td>${etudiant.nom_complet}</td>
            <td class="text-center"><span class="badge ${badgeClass}">${pourcentage.toFixed(2)}%</span></td>
            <td>${etudiant.decision || '-'}</td>
        `;
        
        tableBody.appendChild(tr);
    });
    
    // Mettre à jour les liens d'exportation si nécessaire
    const modalPdfLink = document.getElementById('modal-pdf-link');
    const modalPdfContainer = document.getElementById('modal-pdf-container');
    
    if (modalPdfLink && modalPdfContainer) {
        modalPdfLink.href = palmares.fichier_scanne || '#';
        modalPdfContainer.style.display = palmares.fichier_scanne ? 'block' : 'none';
    }
}

    
    // Gestion de la pagination des résultats si nécessaire
    function setupPagination(totalItems, itemsPerPage) {
        const totalPages = Math.ceil(totalItems / itemsPerPage);
        if (totalPages <= 1) return;
        
        const pagination = document.getElementById('pagination');
        pagination.innerHTML = '';
        
        // Créer les boutons de pagination
        for (let i = 1; i <= totalPages; i++) {
            const li = document.createElement('li');
            li.className = 'page-item' + (i === 1 ? ' active' : '');
            
            const a = document.createElement('a');
            a.className = 'page-link';
            a.href = '#';
            a.textContent = i;
            a.dataset.page = i;
            
            li.appendChild(a);
            pagination.appendChild(li);
        }
        
        // Gestionnaire d'événement pour les boutons de pagination
        pagination.addEventListener('click', function(e) {
            if (e.target.tagName === 'A' && e.target.dataset.page) {
                e.preventDefault();
                
                const pageNumber = parseInt(e.target.dataset.page);
                // Code pour charger les résultats de la page demandée
                // Vous pouvez implémenter une fonction loadPage(pageNumber) 
                // qui charge les données correspondantes
                
                // Mettre à jour l'état actif du bouton de pagination
                document.querySelectorAll('#pagination .page-item').forEach(item => {
                    item.classList.remove('active');
                });
                e.target.parentNode.classList.add('active');
            }
        });
    }
    
    // Initialiser la page
    showState('initial');
    
    // Gestionnaire pour le changement du checkbox toutes les années
    searchAllYears.addEventListener('change', function() {
        document.getElementById('student_year_select').style.display = this.checked ? 'none' : 'block';
    });
    
    // Vérifier l'état initial du checkbox toutes les années
    document.getElementById('student_year_select').style.display = searchAllYears.checked ? 'none' : 'block';
});



</script>

<?php include "./views/include/footer_file.php"; ?>


