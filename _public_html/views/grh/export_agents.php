<?php include "./views/include/header.php"; 

// Initialiser les modèles nécessaires
$structureModel = new Structure();
$gradeModel = new Grade();
$serviceModel = new Service();

// Récupérer les données pour les filtres
$structures = $structureModel->getStructures();
$grades = $gradeModel->getAllGrades();
$services = $serviceModel->getService();

?>
<main id="main" class="main">
<div class="container-fluid">
    <div class="card shadow mb-4">
        <div class="card-header py-3 d-flex justify-content-between align-items-center">
            <h6 class="m-0 font-weight-bold text-primary">Exportation de la liste des agents</h6>
            <a href="grh/agent.list" class="btn btn-sm btn-secondary">
                <i class="bi bi-arrow-left"></i> Retour
            </a>
        </div>
        <div class="card-body">
            <div class="alert alert-info">
                <i class="bi bi-info-circle-fill me-2"></i>
                Sélectionnez les critères de filtrage pour l'exportation PDF de la liste des agents.
            </div>
            
            <form action="controller/export_agents_filtered_pdf.php" method="post" target="_blank" class="needs-validation" novalidate>
                <div class="row mb-3">
                    <div class="col-md-4">
                        <label for="type_agent" class="form-label">Type d'agent</label>
                        <select class="form-select" id="type_agent" name="type_agent">
                            <option value="">Tous les types</option>
                            <option value="Administratif">Administratif</option>
                            <option value="Enseignant">Enseignant</option>
                            <option value="Recherche">Recherche</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label for="grade_id" class="form-label">Grade</label>
                        <select class="form-select" id="grade_id" name="grade_id">
                            <option value="">Tous les grades</option>
                            <?php foreach ($grades as $grade): ?>
                                <option value="<?= $grade['idgrade'] ?>"><?= htmlspecialchars($grade['designation']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label for="sexe" class="form-label">Sexe</label>
                        <select class="form-select" id="sexe" name="sexe">
                            <option value="">Tous</option>
                            <option value="M">Masculin</option>
                            <option value="F">Féminin</option>
                        </select>
                    </div>
                </div>
                
                <div class="row mb-3">
                    <div class="col-md-4">
                        <label for="idStructure" class="form-label">Structure</label>
                        <select class="form-select" id="idStructure" name="idStructure">
                            <option value="">Toutes les structures</option>
                            <?php foreach ($structures as $structure): ?>
                                <option value="<?= $structure['idStructure'] ?>"><?= htmlspecialchars($structure['designation']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label for="idService" class="form-label">Service</label>
                        <select class="form-select" id="idService" name="idService">
                            <option value="">Tous les services</option>
                            <?php foreach ($services as $service): ?>
                                <option value="<?= $service['idService'] ?>"><?= htmlspecialchars($service['designationService']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label for="annee_engagement" class="form-label">Année d'engagement</label>
                        <input type="number" class="form-control" id="annee_engagement" name="annee_engagement" min="1950" max="<?= date('Y') ?>" placeholder="Toutes les années">
                    </div>
                </div>
                
                <div class="row mb-3">
                    <div class="col-md-4">
                        <label for="prime_locale" class="form-label">Prime locale</label>
                        <select class="form-select" id="prime_locale" name="prime_locale">
                            <option value="">Tous</option>
                            <option value="1">Oui</option>
                            <option value="0">Non</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label for="salaire_etat" class="form-label">Salaire de l'état</label>
                        <select class="form-select" id="salaire_etat" name="salaire_etat">
                            <option value="">Tous</option>
                            <option value="1">Oui</option>
                            <option value="0">Non</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label for="prime_institutionnelle" class="form-label">Prime institutionnelle</label>
                        <select class="form-select" id="prime_institutionnelle" name="prime_institutionnelle">
                            <option value="">Tous</option>
                            <option value="1">Oui</option>
                            <option value="0">Non</option>
                        </select>
                    </div>
                </div>
                
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label for="niveauEtude" class="form-label">Niveau d'étude</label>
                        <select class="form-select" id="niveauEtude" name="niveauEtude">
                            <option value="">Tous les niveaux</option>
                            <option value="Certificat primaire">Certificat primaire</option>
                            <option value="Diplôme d'état">Diplôme d'état</option>
                            <option value="Graduat">Graduat</option>
                            <option value="Licence">Licence</option>
                            <option value="Master">Master</option>
                            <option value="Doctorat">Doctorat</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label for="etatCivil" class="form-label">État civil</label>
                        <select class="form-select" id="etatCivil" name="etatCivil">
                            <option value="">Tous</option>
                            <option value="Célibataire">Célibataire</option>
                            <option value="Marié(e)">Marié(e)</option>
                            <option value="Divorcé(e)">Divorcé(e)</option>
                            <option value="Veuf(ve)">Veuf(ve)</option>
                        </select>
                    </div>
                </div>
                
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label for="search" class="form-label">Recherche par nom</label>
                        <input type="text" class="form-control" id="search" name="search" placeholder="Rechercher un agent par son nom">
                    </div>
                    <div class="col-md-6">
                        <label for="export_type" class="form-label">Type d'exportation</label>
                        <select class="form-select" id="export_type" name="export_type" required>
                            <option value="liste_simple">Liste simple</option>
                            <option value="liste_detaillee">Liste détaillée</option>
                            <option value="fiches_individuelles">Fiches individuelles</option>
                        </select>
                    </div>
                </div>
                
                <div class="row mb-3">
                    <div class="col-md-12">
                        <label class="form-label">Colonnes à inclure</label>
                        <div class="row">
                            <div class="col-md-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="col_code" name="columns[]" value="code" checked>
                                    <label class="form-check-label" for="col_code">Code agent</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="col_matricule" name="columns[]" value="matricule" checked>
                                    <label class="form-check-label" for="col_matricule">Matricule</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="col_noms" name="columns[]" value="noms" checked>
                                    <label class="form-check-label" for="col_noms">Noms</label>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="col_sexe" name="columns[]" value="sexe" checked>
                                    <label class="form-check-label" for="col_sexe">Sexe</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="col_grade" name="columns[]" value="grade" checked>
                                    <label class="form-check-label" for="col_grade">Grade</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="col_type" name="columns[]" value="type" checked>
                                    <label class="form-check-label" for="col_type">Type d'agent</label>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="col_structure" name="columns[]" value="structure">
                                    <label class="form-check-label" for="col_structure">Campus</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="col_service" name="columns[]" value="service">
                                    <label class="form-check-label" for="col_service">Service</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="col_telephone" name="columns[]" value="telephone">
                                    <label class="form-check-label" for="col_telephone">Téléphone</label>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="col_email" name="columns[]" value="email">
                                    <label class="form-check-label" for="col_email">Email</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="col_niveau" name="columns[]" value="niveau">
                                    <label class="form-check-label" for="col_niveau">Niveau d'étude</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="col_engagement" name="columns[]" value="engagement">
                                    <label class="form-check-label" for="col_engagement">Année d'engagement</label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="d-flex justify-content-end">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-file-earmark-pdf"></i> Générer PDF
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
</main>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Filtrer les services en fonction de la structure sélectionnée
    const structureSelect = document.getElementById('idStructure');
    const serviceSelect = document.getElementById('idService');
    
    if (structureSelect && serviceSelect) {
        structureSelect.addEventListener('change', function() {
            const structureId = this.value;
            
            // Réinitialiser le sélecteur de service
            serviceSelect.innerHTML = '<option value="">Tous les services</option>';
            
            if (structureId) {
                // Charger les services de la structure sélectionnée
                fetch(`controller/get_services_by_structure.php?structure=${structureId}`)
                    .then(response => response.json())
                    .then(data => {
                        data.forEach(service => {
                            serviceSelect.innerHTML += `<option value="${service.idService}">${service.designationService}</option>`;
                        });
                    })
                    .catch(error => console.error('Erreur lors du chargement des services:', error));
            }
        });
    }
    
    // Filtrer les grades en fonction du type d'agent sélectionné
    const typeAgentSelect = document.getElementById('type_agent');
    const gradeSelect = document.getElementById('grade_id');
    
    if (typeAgentSelect && gradeSelect) {
        typeAgentSelect.addEventListener('change', function() {
            const agentType = this.value;
            
            // Réinitialiser le sélecteur de grade
            gradeSelect.innerHTML = '<option value="">Tous les grades</option>';
            
            if (agentType) {
                // Charger les grades correspondant au type d'agent
                fetch(`controller/get_grades_by_type.php?type=${agentType}`)
                    .then(response => response.json())
                    .then(data => {
                        data.forEach(grade => {
                            gradeSelect.innerHTML += `<option value="${grade.idgrade}">${grade.designation}</option>`;
                        });
                    })
                    .catch(error => console.error('Erreur lors du chargement des grades:', error));
            } else {
                                // Si aucun type n'est sélectionné, charger tous les grades
                                fetch(`controller/get_all_grades.php`)
                    .then(response => response.json())
                    .then(data => {
                        data.forEach(grade => {
                            gradeSelect.innerHTML += `<option value="${grade.idgrade}">${grade.designation}</option>`;
                        });
                    })
                    .catch(error => console.error('Erreur lors du chargement des grades:', error));
            }
        });
    }
    
    // Gestion du type d'exportation
    const exportTypeSelect = document.getElementById('export_type');
    const columnsSection = document.querySelector('.row.mb-3:nth-last-child(2)');
    
    if (exportTypeSelect && columnsSection) {
        exportTypeSelect.addEventListener('change', function() {
            const exportType = this.value;
            
            // Afficher/masquer la section des colonnes selon le type d'exportation
            if (exportType === 'fiches_individuelles') {
                columnsSection.classList.add('d-none');
            } else {
                columnsSection.classList.remove('d-none');
            }
        });
    }
    
    // Validation du formulaire
    const form = document.querySelector('form.needs-validation');
    if (form) {
        form.addEventListener('submit', function(event) {
            if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
                
                // Afficher un message d'erreur
                Swal.fire({
                    icon: 'error',
                    title: 'Formulaire incomplet',
                    text: 'Veuillez remplir tous les champs obligatoires avant de soumettre le formulaire.',
                    confirmButtonColor: '#4e73df'
                });
            } else {
                // Vérifier si au moins une colonne est sélectionnée pour les listes
                const exportType = exportTypeSelect.value;
                if (exportType !== 'fiches_individuelles') {
                    const selectedColumns = document.querySelectorAll('input[name="columns[]"]:checked');
                    if (selectedColumns.length === 0) {
                        event.preventDefault();
                        event.stopPropagation();
                        
                        Swal.fire({
                            icon: 'warning',
                            title: 'Aucune colonne sélectionnée',
                            text: 'Veuillez sélectionner au moins une colonne à inclure dans le rapport.',
                            confirmButtonColor: '#4e73df'
                        });
                    }
                }
            }
            
            form.classList.add('was-validated');
        });
    }
});
</script>

<?php include "./views/include/footer_file.php"; ?>
