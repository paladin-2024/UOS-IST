<?php include "./views/include/header.php"; 
// Récupérer le type d'agent s'il est présent dans l'URL
$selectedType = isset($_GET['type_agent']) ? $_GET['type_agent'] : '';

// Charger les grades si un type est sélectionné
$grades = [];
if (!empty($selectedType)) {
    $gradeModel = new Grade();
    $grades = $gradeModel->getGradesByType($selectedType);
}

// Charger tous les services
$serviceModel = new Service();
$services = $serviceModel->getService();
?>

<main id="main" class="main">
<div class="container-fluid">
    <div class="card shadow mb-4">
        <div class="card-header py-3 d-flex justify-content-between align-items-center">
            <h6 class="m-0 font-weight-bold text-primary">Enregistrement d'un nouvel agent</h6>
            <a href="index" class="btn btn-sm btn-secondary">
                <i class="bi bi-arrow-left"></i> Retour
            </a>
        </div>
        <div class="card-body">
            <!-- Barre de progression -->
            <div class="mb-4">
                <div class="progress-container">
                    <div class="progress" style="height: 15px;margin-top:5px;">
                        <div class="progress-bar bg-primary" role="progressbar" style="width: 25%;" id="progressBar"></div>
                    </div>
                    <div class="d-flex justify-content-between mt-2">
                        <div class="step active" data-step="1">
                            <div class="step-icon"><i class="bi bi-person"></i></div>
                            <div class="step-text">Informations générales</div>
                        </div>
                        <div class="step" data-step="2">
                            <div class="step-icon"><i class="bi bi-card-list"></i></div>
                            <div class="step-text">Informations personnelles</div>
                        </div>
                        <div class="step" data-step="3">
                            <div class="step-icon"><i class="bi bi-mortarboard"></i></div>
                            <div class="step-text">Formation académique</div>
                        </div>
                        <div class="step" data-step="4">
                            <div class="step-icon"><i class="bi bi-briefcase"></i></div>
                            <div class="step-text">Informations professionnelles</div>
                        </div>
                    </div>
                </div>
            </div>

            <form action="controller/create_agent.php" method="post" enctype="multipart/form-data" class="needs-validation" novalidate id="agentForm">
                <div class="alert alert-info mb-4">
                    <i class="bi bi-info-circle-fill me-2"></i>
                    Veuillez remplir tous les champs obligatoires marqués par <span class="text-danger">*</span>
                </div>

                <!-- Étape 1: Informations générales -->
                <div class="form-step" id="step1">
                    <div class="card mb-4">
                        <div class="card-header bg-light">
                            <h6 class="mb-0">Informations générales</h6>
                        </div>
                        <div class="card-body">
                            <div class="row mb-3">
                                <div class="col-md-4">
                                    <label for="type_agent" class="form-label">Type d'agent <span class="text-danger">*</span></label>
                                    <select class="form-select" id="type_agent" name="type_agent" required>
                                        <option value="" selected disabled>Sélectionner...</option>
                                        <option value="Administratif">Administratif</option>
                                        <option value="Enseignant">Enseignant</option>
                                        <option value="Recherche">Agent de recherche</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label for="matricule" class="form-label">Matricule <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="matricule" name="matricule" required>
                                </div>
                                <div class="col-md-4">
                                    <label for=codeAgent class="form-label">Code agent</label>
                                    <input type="text" class="form-control" id=codeAgent name=codeAgent readonly>
                                    <small class="text-muted">Code unique généré automatiquement</small>
                                </div>

                            </div>
                            <div class="row mb-3">
                                <div class="col-md-8">
                                    <label for="noms" class="form-label">Noms, Postnoms & Prénoms <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="noms" name="noms" required>
                                </div>
                                <div class="col-md-4">
                                    <label for="photo" class="form-label">Photo</label>
                                    <div class="custom-file-upload">
                                        <input type="file" class="form-control" id="photo" name="photo" accept="image/*">
                                        <div id="photoPreview" class="mt-2"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="d-flex justify-content-end mt-3">
                        <button type="button" class="btn btn-primary next-step">
                            Suivant <i class="bi bi-arrow-right"></i>
                        </button>
                    </div>
                </div>

                <!-- Étape 2: Informations personnelles -->
                <div class="form-step" id="step2" style="display: none;">
                    <div class="card mb-4">
                        <div class="card-header bg-light">
                            <h6 class="mb-0">Informations personnelles</h6>
                        </div>
                        <div class="card-body">
                            <div class="row mb-3">
                                <div class="col-md-3">
                                    <label for="sexe" class="form-label">Sexe <span class="text-danger">*</span></label>
                                    <select class="form-select" id="sexe" name="sexe" required>
                                        <option value="" selected disabled>Sélectionner...</option>
                                        <option value="M">Masculin</option>
                                        <option value="F">Féminin</option>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label for=dateNaissance class="form-label">Date de naissance <span class="text-danger">*</span></label>
                                    <input type="date" class="form-control" id=dateNaissance name=dateNaissance required>
                                </div>
                                <div class="col-md-6">
                                    <label for=lieuNaissance class="form-label">Lieu de naissance <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id=lieuNaissance name=lieuNaissance required>
                                </div>
                            </div>
                            <div class="row mb-3">
                                <div class="col-md-4">
                                    <label for=etatCivil class="form-label">État civil <span class="text-danger">*</span></label>
                                    <select class="form-select" id=etatCivil name=etatCivil required>
                                        <option value="" selected disabled>Sélectionner...</option>
                                        <option value="Célibataire">Célibataire</option>
                                        <option value="Marié(e)">Marié(e)</option>
                                        <option value="Divorcé(e)">Divorcé(e)</option>
                                        <option value="Veuf(ve)">Veuf(ve)</option>
                                    </select>
                                </div>
                                <div class="col-md-8">
                                    <label for="conjoint" class="form-label">Nom du conjoint(e)</label>
                                    <input type="text" class="form-control" id="conjoint" name="conjoint">
                                </div>
                            </div>
                            <div class="row mb-3">
                                <div class="col-md-12">
                                    <label class="form-label">Adresse</label>
                                    <div class="row g-2">
                                        <div class="col-md-4">
                                            <input type="text" class="form-control" name="adresse_avenue" placeholder="Avenue">
                                        </div>
                                        <div class="col-md-4">
                                            <input type="text" class="form-control" name="adresse_quartier" placeholder="Quartier">
                                        </div>
                                        <div class="col-md-4">
                                            <input type="text" class="form-control" name="adresse_commune" placeholder="Commune">
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="telephone" class="form-label">Téléphone <span class="text-danger">*</span></label>
                                    <input type="tel" class="form-control" id="telephone" name="telephone" required>
                                </div>
                                <div class="col-md-6">
                                    <label for="email" class="form-label">Email</label>
                                    <input type="email" class="form-control" id="email" name="email">
                                </div>
                            </div>
                            <div class="row mb-3">
                                <div class="col-md-4">
                                    <label for="contact_urgence" class="form-label">Personne à contacter en cas d'urgence</label>
                                    <input type="text" class="form-control" id="contact_urgence" name="contact_urgence">
                                </div>
                                <div class="col-md-4">
                                    <label for="degre_parente_urgence" class="form-label">Degré de parenté</label>
                                    <input type="text" class="form-control" id="degre_parente_urgence" name="degre_parente_urgence">
                                </div>
                                <div class="col-md-4">
                                    <label for="telephone_urgence" class="form-label">Téléphone d'urgence</label>
                                    <input type="tel" class="form-control" id="telephone_urgence" name="telephone_urgence">
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="d-flex justify-content-between mt-3">
                        <button type="button" class="btn btn-outline-secondary prev-step">
                            <i class="bi bi-arrow-left"></i> Précédent
                        </button>
                        <button type="button" class="btn btn-primary next-step">
                            Suivant <i class="bi bi-arrow-right"></i>
                        </button>
                    </div>
                </div>

                <!-- Étape 3: Formation académique -->
                <div class="form-step" id="step3" style="display: none;">
                    <div class="card mb-4">
                        <div class="card-header bg-light d-flex justify-content-between align-items-center">
                            <h6 class="mb-0">Formation académique</h6>
                            <button type="button" class="btn btn-sm btn-outline-primary" id="addFormationBtn">
                                <i class="bi bi-plus-circle"></i> Ajouter une formation
                            </button>
                        </div>
                        <div class="card-body">
                            <div class="row mb-3">
                                <div class="col-md-4">
                                    <label for=niveauEtude class="form-label">Niveau d'étude le plus élevé <span class="text-danger">*</span></label>
                                    <select class="form-select" id=niveauEtude name=niveauEtude required>
                                        <option value="" selected disabled>Sélectionner...</option>
                                        <option value="Certificat primaire">Certificat primaire</option>
                                        <option value="Diplôme d'état">Diplôme d'état</option>
                                        <option value="Graduat">Graduat</option>
                                        <option value="Licence">Licence</option>
                                        <option value="Master">Master</option>
                                        <option value="Doctorat">Doctorat</option>
                                    </select>
                                </div>
                            </div>
                            
                            <!-- Conteneur pour les formations -->
                            <div id="formationsContainer">
                                <div class="formation-item border rounded p-3 mb-3">
                                    <div class="row mb-2">
                                        <div class="col-md-4">
                                            <label class="form-label">Niveau</label>
                                            <select class="form-select" name="formations[0][niveau]">
                                                <option value="Certificat primaire">Certificat primaire</option>
                                                <option value="Diplôme d'état">Diplôme d'état</option>
                                                <option value="Graduat">Graduat</option>
                                                <option value="Licence">Licence</option>
                                                <option value="Master">Master</option>
                                                <option value="Doctorat">Doctorat</option>
                                            </select>
                                        </div>
                                        <div class="col-md-8">
                                            <label class="form-label">Établissement</label>
                                            <input type="text" class="form-control" name="formations[0][etablissement]">
                                        </div>
                                    </div>
                                    <div class="row mb-2">
                                        <div class="col-md-6">
                                            <label class="form-label">Filière/Faculté</label>
                                            <input type="text" class="form-control" name="formations[0][filiere]">
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label">Année d'obtention</label>
                                            <input type="number" class="form-control" name="formations[0][annee_obtention]" min="1950" max="<?= date('Y') ?>">
                                        </div>
                                        <div class="col-md-2">
                                            <label class="form-label">Diplôme</label>
                                            <input type="file" class="form-control" name="formations[0][diplome_fichier]">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="d-flex justify-content-between mt-3">
                    <button type="button" class="btn btn-outline-secondary prev-step">
                            <i class="bi bi-arrow-left"></i> Précédent
                        </button>
                        <button type="button" class="btn btn-primary next-step">
                            Suivant <i class="bi bi-arrow-right"></i>
                        </button>
                    </div>
                </div>

                <!-- Étape 4: Informations professionnelles -->
                <div class="form-step" id="step4" style="display: none;">
                    <div class="card mb-4">
                        <div class="card-header bg-light">
                            <h6 class="mb-0">Informations professionnelles</h6>
                        </div>
                        <div class="card-body">
                            <div class="row mb-3">
                                <div class="col-md-4">
                                    <label for="annee_engagement" class="form-label">Année d'engagement</label>
                                    <input type="number" class="form-control" id="annee_engagement" name="annee_engagement" min="1950" max="<?= date('Y') ?>">
                                </div>
                                <div class="col-md-4">
                                    <label for="reference_acte_engagement" class="form-label">Référence de l'acte d'engagement</label>
                                    <input type="text" class="form-control" id="reference_acte_engagement" name="reference_acte_engagement">
                                </div>
                                <div class="col-md-4">
                                    <label for="grade_id" class="form-label">Grade actuel <span class="text-danger">*</span></label>
                                    <select class="form-select" id="grade_id" name="grade_id" required>
                                        <option value="" selected disabled>Sélectionner...</option>
                                        <?php foreach ($grades as $grade): ?>
                                            <option value="<?= $grade['idgrade'] ?>"><?= $grade['designation'] ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            
                            <!-- Champs spécifiques pour les administratifs -->
                            <div id="adminFields" class="d-none">
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label for="direction" class="form-label">Direction</label>
                                        <input type="text" class="form-control" id="direction" name="direction">
                                    </div>
                                    <div class="col-md-6">
                                        <label for="division" class="form-label">Division</label>
                                        <input type="text" class="form-control" id="division" name="division">
                                    </div>
                                </div>
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label for="decision_grade" class="form-label">Décision grade</label>
                                        <input type="text" class="form-control" id="decision_grade" name="decision_grade">
                                    </div>
                                    <div class="col-md-6">
                                        <label for="notification_grade" class="form-label">Notification grade</label>
                                        <input type="text" class="form-control" id="notification_grade" name="notification_grade">
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Champs spécifiques pour les enseignants -->
                            <div id="teacherFields" class="d-none">
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label for="specialisation" class="form-label">Spécialisation</label>
                                        <input type="text" class="form-control" id="specialisation" name="specialisation">
                                    </div>
                                    <div class="col-md-6">
                                        <label for="domaine_recherche" class="form-label">Domaine de recherche</label>
                                        <input type="text" class="form-control" id="domaine_recherche" name="domaine_recherche">
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Champs spécifiques pour les agents de recherche -->
                            <div id="researchFields" class="d-none">
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label for="unite_recherche" class="form-label">Unité de recherche</label>
                                        <input type="text" class="form-control" id="unite_recherche" name="unite_recherche">
                                    </div>
                                    <div class="col-md-6">
                                        <label for="projet_recherche" class="form-label">Projet de recherche</label>
                                        <input type="text" class="form-control" id="projet_recherche" name="projet_recherche">
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for=idStructure class="form-label">Structure <span class="text-danger">*</span></label>
                                    <select class="form-select" id=idStructure name=idStructure required>
                                        <option value="" selected disabled>Sélectionner...</option>
                                        <?php
                                        $structureModel = new Structure();
                                        $structures = $structureModel->getStructures();
                                        foreach ($structures as $structure) {
                                            echo "<option value='{$structure['idStructure']}'>{$structure['designation']}</option>";
                                        }
                                        ?>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label for=idService class="form-label">Service <span class="text-danger">*</span></label>
                                    <select class="form-select" id=idService name=idService required>
                                        <option value="" selected disabled>Sélectionner un service</option>
                                        <?php foreach ($services as $service): ?>
                                            <option value="<?= $service['idService'] ?>"><?= $service['designationService'] ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            
                            <!-- Historique des grades -->
                            <div class="mt-4">
                                <h6 class="mb-3">Historique des grades</h6>
                                <div id="gradesHistoryContainer">
                                    <div class="grade-history-item border rounded p-3 mb-3">
                                        <div class="row mb-2">
                                            <div class="col-md-4">
                                                <label class="form-label">Grade</label>
                                                <select class="form-select" id="grade_id_0" name="grades_history[0][idgrade]">
                                                    <option value="" selected disabled>Sélectionner...</option>
                                                    <?php foreach ($grades as $grade): ?>
                                                        <option value="<?= $grade['idgrade'] ?>"><?= $grade['designation'] ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            <div class="col-md-4">
                                                <label class="form-label">Date de promotion</label>
                                                <input type="date" class="form-control" name="grades_history[0][date_promotion]">
                                            </div>
                                            <div class="col-md-4">
                                                <label class="form-label">Référence décision</label>
                                                <input type="text" class="form-control" name="grades_history[0][reference_decision]">
                                            </div>
                                        </div>
                                        <div class="row">
                                            <div class="col-md-12">
                                                <label class="form-label">Référence notification</label>
                                                <input type="text" class="form-control" name="grades_history[0][reference_notification]">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <button type="button" class="btn btn-sm btn-outline-primary" id="addGradeHistoryBtn">
                                    <i class="bi bi-plus-circle"></i> Ajouter un grade
                                </button>
                            </div>
                            
                            <div class="row mt-4">
                                <div class="col-md-4">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="prime_locale" name="prime_locale" value="1">
                                        <label class="form-check-label" for="prime_locale">
                                            Paiement en prime locale
                                        </label>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="salaire_etat" name="salaire_etat" value="1">
                                        <label class="form-check-label" for="salaire_etat">
                                            Paiement salaire de base de l'état congolais
                                        </label>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="prime_institutionnelle" name="prime_institutionnelle" value="1">
                                        <label class="form-check-label" for="prime_institutionnelle">
                                            Paiement de la prime institutionnelle de la fonction publique
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="d-flex justify-content-between mt-3">
                        <button type="button" class="btn btn-outline-secondary prev-step">
                            <i class="bi bi-arrow-left"></i> Précédent
                        </button>
                        <button type="submit" class="btn btn-success">
                            <i class="bi bi-save"></i> Enregistrer
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
</main>

<!-- Ajout du CSS pour le formulaire multi-étapes -->
<style>
.progress-container {
    margin-bottom: 30px;
}

.step {
    text-align: center;
    position: relative;
    width: 25%;
}

.step-icon {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background-color: #e9ecef;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 5px;
    color: #6c757d;
    font-size: 1.2rem;
    position: relative;
    z-index: 2;
    transition: all 0.3s ease;
}

.step.active .step-icon {
    background-color: #4e73df;
    color: white;
    box-shadow: 0 0 0 5px rgba(78, 115, 223, 0.2);
}

.step.completed .step-icon {
    background-color: #1cc88a;
    color: white;
}

.step-text {
    font-size: 0.8rem;
    color: #6c757d;
    transition: all 0.3s ease;
}

.step.active .step-text {
    color: #4e73df;
    font-weight: 600;
}

.step.completed .step-text {
    color: #1cc88a;
}

.custom-file-upload {
    position: relative;
}

#photoPreview {
    margin-top: 10px;
}

.formation-item, .grade-history-item {
    background-color: #f8f9fc;
    border-radius: 5px;
    transition: all 0.3s ease;
}

.formation-item:hover, .grade-history-item:hover {
    box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
}

.form-step {
    transition: all 0.3s ease;
}

.next-step, .prev-step {
    transition: all 0.2s ease;
}

.next-step:hover, .prev-step:hover {
    transform: translateY(-2px);
}
</style>


<script>
document.addEventListener('DOMContentLoaded', function() {
    // Sélectionner l'élément de type d'agent
    const typeAgentSelect = document.getElementById('type_agent');
    
    if (typeAgentSelect) {
        console.log('Type agent select trouvé');
        
        // Utiliser un gestionnaire d'événements plus simple et direct
        typeAgentSelect.onchange = function() {
            const selectedType = this.value;
            console.log('Type agent changé:', selectedType);
            
            // Redirection avec le type sélectionné
            window.location.href = 'grh/agent.add&type_agent=' + selectedType;
        };
        
        // Vérifier si un type est déjà sélectionné
        const currentAgentType = '<?= $selectedType ?>';
        console.log('Type actuel:', currentAgentType);
        
        if (currentAgentType) {
            // Définir la valeur sélectionnée
            typeAgentSelect.value = currentAgentType;
            
            // Afficher les champs spécifiques selon le type
            const adminFields = document.getElementById('adminFields');
            const teacherFields = document.getElementById('teacherFields');
            const researchFields = document.getElementById('researchFields');
            
            adminFields.classList.add('d-none');
            teacherFields.classList.add('d-none');
            researchFields.classList.add('d-none');
            
            if (currentAgentType === 'Administratif') {
                adminFields.classList.remove('d-none');
            } else if (currentAgentType === 'Enseignant') {
                teacherFields.classList.remove('d-none');
            } else if (currentAgentType === 'Recherche') {
                researchFields.classList.remove('d-none');
            }
        }
    } else {
        console.error('Élément type_agent non trouvé!');
    }
});
</script>


<!-- Script pour la gestion dynamique du formulaire multi-étapes -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Éléments du DOM pour la navigation entre étapes
    const steps = document.querySelectorAll('.step');
    const formSteps = document.querySelectorAll('.form-step');
    const nextButtons = document.querySelectorAll('.next-step');
    const prevButtons = document.querySelectorAll('.prev-step');
    const progressBar = document.getElementById('progressBar');
    
    // Variables pour le formulaire
    let currentStep = 1;
    const totalSteps = formSteps.length;
    
    // Éléments du DOM pour les fonctionnalités du formulaire
    const typeAgentSelect = document.getElementById('type_agent');
    const gradeSelect = document.getElementById('grade_id');
    const structureSelect = document.getElementById('idStructure');
    const serviceSelect = document.getElementById('idService');
    const adminFields = document.getElementById('adminFields');
    const teacherFields = document.getElementById('teacherFields');
    const researchFields = document.getElementById('researchFields');
    const addFormationBtn = document.getElementById('addFormationBtn');
    const formationsContainer = document.getElementById('formationsContainer');
    const addGradeHistoryBtn = document.getElementById('addGradeHistoryBtn');
    const gradesHistoryContainer = document.getElementById('gradesHistoryContainer');
    
    // Compteurs pour les formations et grades
    let formationCount = 1;
    let gradeHistoryCount = 1;

    // Charger les grades selon le type d'agent
    function loadGrades(agentType) {
        if (!gradeSelect) return;
        gradeSelect.innerHTML = '<option value="" selected disabled>Sélectionner...</option>';
        if (!agentType) return;
        fetch(`controller/get_grades_by_type.php?type=${encodeURIComponent(agentType)}`)
            .then(r => r.json())
            .then(items => {
                (items || []).forEach(g => {
                    const opt = document.createElement('option');
                    opt.value = g.idgrade;
                    opt.textContent = g.designation;
                    gradeSelect.appendChild(opt);
                });
            })
            .catch(() => {});
    }

    // Charger les services selon la structure
    function loadServices(structureId) {
        if (!serviceSelect) return;
        serviceSelect.innerHTML = '<option value="" selected disabled>Sélectionner un service</option>';
        if (!structureId) return;
        fetch(`controller/get_services_by_structure.php?structure=${encodeURIComponent(structureId)}`)
            .then(r => r.json())
            .then(items => {
                (items || []).forEach(s => {
                    const opt = document.createElement('option');
                    opt.value = s.idService;
                    opt.textContent = s.designationService;
                    serviceSelect.appendChild(opt);
                });
            })
            .catch(() => {});
    }
    
    // Fonction pour naviguer vers l'étape suivante
    function goToNextStep() {
        // Vérifier si les champs requis de l'étape actuelle sont remplis
        const currentFormStep = document.getElementById(`step${currentStep}`);
        const requiredFields = currentFormStep.querySelectorAll('[required]');
        let isValid = true;
        
        requiredFields.forEach(field => {
            if (!field.value) {
                isValid = false;
                field.classList.add('is-invalid');
            } else {
                field.classList.remove('is-invalid');
            }
        });
        
        if (!isValid) {
            Swal.fire({
                icon: 'error',
                title: 'Champs obligatoires',
                text: 'Veuillez remplir tous les champs obligatoires avant de continuer.',
                confirmButtonColor: '#4e73df'
            });
            return;
        }
        
        // Si tout est valide, passer à l'étape suivante
        if (currentStep < totalSteps) {
                        // Masquer l'étape actuelle
                        document.getElementById(`step${currentStep}`).style.display = 'none';
            // Marquer l'étape actuelle comme complétée
            steps[currentStep - 1].classList.add('completed');
            // Incrémenter l'étape courante
            currentStep++;
            // Afficher la nouvelle étape
            document.getElementById(`step${currentStep}`).style.display = 'block';
            // Marquer la nouvelle étape comme active
            steps[currentStep - 1].classList.add('active');
            // Mettre à jour la barre de progression
            updateProgressBar();
        }
    }
    
    // Fonction pour naviguer vers l'étape précédente
    function goToPrevStep() {
        if (currentStep > 1) {
            // Masquer l'étape actuelle
            document.getElementById(`step${currentStep}`).style.display = 'none';
            // Supprimer la classe active de l'étape actuelle
            steps[currentStep - 1].classList.remove('active');
            // Décrémenter l'étape courante
            currentStep--;
            // Afficher la nouvelle étape
            document.getElementById(`step${currentStep}`).style.display = 'block';
            // Supprimer la classe completed de la nouvelle étape active
            steps[currentStep - 1].classList.remove('completed');
            steps[currentStep - 1].classList.add('active');
            // Mettre à jour la barre de progression
            updateProgressBar();
        }
    }
    
    // Fonction pour mettre à jour la barre de progression
    function updateProgressBar() {
        const progressPercentage = ((currentStep - 1) / (totalSteps - 1)) * 100;
        progressBar.style.width = `${progressPercentage}%`;
    }
    
    // Ajouter les écouteurs d'événements pour les boutons suivant/précédent
    nextButtons.forEach(button => {
        button.addEventListener('click', goToNextStep);
    });
    
    prevButtons.forEach(button => {
        button.addEventListener('click', goToPrevStep);
    });
    



    
    // Fonction pour ajouter un champ de formation
    function addFormationField() {
        const formationItem = document.createElement('div');
        formationItem.className = 'formation-item border rounded p-3 mb-3';
        formationItem.innerHTML = `
            <div class="d-flex justify-content-between align-items-center mb-2">
                <h6 class="mb-0">Formation ${formationCount + 1}</h6>
                <button type="button" class="btn btn-sm btn-outline-danger remove-formation">
                    <i class="bi bi-trash"></i>
                </button>
            </div>
            <div class="row mb-2">
                <div class="col-md-4">
                    <label class="form-label">Niveau</label>
                    <select class="form-select" name="formations[${formationCount}][niveau]">
                        <option value="Certificat primaire">Certificat primaire</option>
                        <option value="Diplôme d'état">Diplôme d'état</option>
                        <option value="Graduat">Graduat</option>
                        <option value="Licence">Licence</option>
                        <option value="Master">Master</option>
                        <option value="Doctorat">Doctorat</option>
                    </select>
                </div>
                <div class="col-md-8">
                    <label class="form-label">Établissement</label>
                    <input type="text" class="form-control" name="formations[${formationCount}][etablissement]">
                </div>
            </div>
            <div class="row mb-2">
                <div class="col-md-6">
                    <label class="form-label">Filière/Faculté</label>
                    <input type="text" class="form-control" name="formations[${formationCount}][filiere]">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Année d'obtention</label>
                    <input type="number" class="form-control" name="formations[${formationCount}][annee_obtention]" min="1950" max="${new Date().getFullYear()}">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Diplôme</label>
                    <input type="file" class="form-control" name="formations[${formationCount}][diplome_fichier]">
                </div>
            </div>
        `;
        
        formationsContainer.appendChild(formationItem);
        formationCount++;
        
        // Ajouter l'événement pour supprimer la formation
        const removeBtn = formationItem.querySelector('.remove-formation');
        removeBtn.addEventListener('click', function() {
            formationItem.remove();
        });
    }
    
    // Fonction pour ajouter un champ d'historique de grade
    function addGradeHistoryField() {
        const gradeItem = document.createElement('div');
        gradeItem.className = 'grade-history-item border rounded p-3 mb-3';
        gradeItem.innerHTML = `
            <div class="d-flex justify-content-between align-items-center mb-2">
                <h6 class="mb-0">Grade ${gradeHistoryCount + 1}</h6>
                <button type="button" class="btn btn-sm btn-outline-danger remove-grade">
                    <i class="bi bi-trash"></i>
                </button>
            </div>
            <div class="row mb-2">
                <div class="col-md-4">
                    <label class="form-label">Grade</label>
                    <select class="form-select grade-select" name="grades_history[${gradeHistoryCount}][idgrade]">
                        <option value="" selected disabled>Sélectionner...</option>
                            <?php foreach ($grades as $grade): ?>
                                <option value="<?= $grade['idgrade'] ?>"><?= $grade['designation'] ?></option>
                            <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Date de promotion</label>
                    <input type="date" class="form-control" name="grades_history[${gradeHistoryCount}][date_promotion]">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Référence décision</label>
                    <input type="text" class="form-control" name="grades_history[${gradeHistoryCount}][reference_decision]">
                </div>
            </div>
            <div class="row">
                <div class="col-md-12">
                    <label class="form-label">Référence notification</label>
                    <input type="text" class="form-control" name="grades_history[${gradeHistoryCount}][reference_notification]">
                </div>
            </div>
        `;
        
        gradesHistoryContainer.appendChild(gradeItem);
        gradeHistoryCount++;
        
        // Ajouter l'événement pour supprimer l'historique de grade
        const removeBtn = gradeItem.querySelector('.remove-grade');
        removeBtn.addEventListener('click', function() {
            gradeItem.remove();
        });
        
        
    }
    
    // Événement de changement du type d'agent
    typeAgentSelect.addEventListener('change', function() {
        const agentType = this.value;
        
        // Afficher/masquer les champs spécifiques
        adminFields.classList.add('d-none');
        teacherFields.classList.add('d-none');
        researchFields.classList.add('d-none');
        
        if (agentType === 'Administratif') {
            adminFields.classList.remove('d-none');
        } else if (agentType === 'Enseignant') {
            teacherFields.classList.remove('d-none');
        } else if (agentType === 'Recherche') {
            researchFields.classList.remove('d-none');
        }
        
        // Charger les grades correspondants
        if (agentType) {
            loadGrades(agentType);
        }
    });
    
    // Événement de changement de la structure
    structureSelect.addEventListener('change', function() {
        const structureId = this.value;
        if (structureId) {
            loadServices(structureId);
        } else {
            serviceSelect.innerHTML = '<option value="" selected disabled>Sélectionner d\'abord une structure</option>';
        }
    });
    
    // Événement pour ajouter une formation
    addFormationBtn.addEventListener('click', addFormationField);
    
    // Événement pour ajouter un historique de grade
    addGradeHistoryBtn.addEventListener('click', addGradeHistoryField);
    
    // Prévisualisation de la photo
    const photoInput = document.getElementById('photo');
    photoInput.addEventListener('change', function() {
        if (this.files && this.files[0]) {
            const reader = new FileReader();
            reader.onload = function(e) {
                const photoPreview = document.getElementById('photoPreview');
                photoPreview.innerHTML = `
                    <div class="card" style="max-width: 200px;">
                        <img src="${e.target.result}" class="card-img-top" alt="Aperçu de la photo">
                        <div class="card-body p-2">
                            <button type="button" class="btn btn-sm btn-outline-danger w-100" id="removePhoto">
                                <i class="bi bi-trash"></i> Supprimer
                            </button>
                        </div>
                    </div>
                `;
                
                // Ajouter un gestionnaire d'événements pour supprimer la photo
                document.getElementById('removePhoto').addEventListener('click', function() {
                    photoInput.value = '';
                    photoPreview.innerHTML = '';
                });
            };
            reader.readAsDataURL(this.files[0]);
        }
    });
    
    // Validation du formulaire
    const form = document.getElementById('agentForm');
    form.addEventListener('submit', function(event) {
        if (!form.checkValidity()) {
            event.preventDefault();
            event.stopPropagation();
            
            // Afficher un message d'erreur avec SweetAlert2
            Swal.fire({
                icon: 'error',
                title: 'Formulaire incomplet',
                text: 'Veuillez remplir tous les champs obligatoires avant de soumettre le formulaire.',
                confirmButtonColor: '#4e73df'
            });
        } else {
            // Afficher un message de chargement
            Swal.fire({
                title: 'Enregistrement en cours...',
                html: 'Veuillez patienter pendant l\'enregistrement des données.',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });
        }
        
        form.classList.add('was-validated');
    });
    
        // Animation des étapes lors du chargement initial
        setTimeout(() => {
        steps[0].classList.add('active');
        updateProgressBar();
    }, 100);
    
    // Ajouter des animations pour les transitions entre étapes
    document.querySelectorAll('.form-step').forEach(step => {
        step.addEventListener('transitionend', function() {
            if (this.style.display === 'none') {
                this.style.opacity = 0;
            } else {
                setTimeout(() => {
                    this.style.opacity = 1;
                }, 50);
            }
        });
    });
    
    // Fonction pour valider les champs d'une étape
    function validateStep(stepNumber) {
        const step = document.getElementById(`step${stepNumber}`);
        const requiredFields = step.querySelectorAll('[required]');
        let isValid = true;
        
        requiredFields.forEach(field => {
            if (!field.value) {
                isValid = false;
                field.classList.add('is-invalid');
                
                // Ajouter un événement pour enlever la classe is-invalid lorsque l'utilisateur modifie le champ
                field.addEventListener('input', function() {
                    if (this.value) {
                        this.classList.remove('is-invalid');
                    }
                }, { once: true });
            } else {
                field.classList.remove('is-invalid');
            }
        });
        
        return isValid;
    }
    
    // Amélioration de la navigation entre les étapes avec validation
    nextButtons.forEach(button => {
        button.addEventListener('click', function() {
            if (validateStep(currentStep)) {
                goToNextStep();
            } else {
                // Afficher un message d'erreur avec animation
                button.classList.add('shake');
                setTimeout(() => {
                    button.classList.remove('shake');
                }, 500);
                
                // Faire défiler jusqu'au premier champ invalide
                const firstInvalid = document.querySelector(`#step${currentStep} .is-invalid`);
                if (firstInvalid) {
                    firstInvalid.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }
            }
        });
    });
    
    // Ajouter des effets visuels pour améliorer l'expérience utilisateur
    document.querySelectorAll('.form-control, .form-select').forEach(input => {
        input.addEventListener('focus', function() {
            this.closest('.mb-3')?.classList.add('focused');
        });
        
        input.addEventListener('blur', function() {
            this.closest('.mb-3')?.classList.remove('focused');
        });
    });
    
    // Initialiser les tooltips Bootstrap pour les champs avec des informations supplémentaires
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
});


</script>

<script>
// Ajoutez cette fonction dans la section script existante
document.addEventListener('DOMContentLoaded', function() {
    // Fonction pour générer un code agent unique à 8 chiffres
    function generateUniqueCode() {
        // Préfixe AG pour Agent
        const prefix = "AG";
        // Génère 6 chiffres aléatoires pour avoir un code de 8 caractères au total
        const randomDigits = Math.floor(100000 + Math.random() * 900000);
        return prefix + randomDigits;
    }
    
    // Générer et définir le code agent lors du chargement de la page
    const codeAgentField = document.getElementById('codeAgent');
    if (codeAgentField) {
        codeAgentField.value = generateUniqueCode();
    }
    
    // Ajouter un événement avant la soumission du formulaire pour vérifier/régénérer le code si nécessaire
    const agentForm = document.getElementById('agentForm');
    if (agentForm) {
        agentForm.addEventListener('submit', function(event) {
            // S'assurer que le code agent est défini
            if (!codeAgentField.value) {
                codeAgentField.value = generateUniqueCode();
            }
        });
    }
});
</script>


<!-- Styles supplémentaires pour les animations -->
<style>
.shake {
    animation: shake 0.5s cubic-bezier(.36,.07,.19,.97) both;
}

@keyframes shake {
    10%, 90% {
        transform: translate3d(-1px, 0, 0);
    }
    20%, 80% {
        transform: translate3d(2px, 0, 0);
    }
    30%, 50%, 70% {
        transform: translate3d(-3px, 0, 0);
    }
    40%, 60% {
        transform: translate3d(3px, 0, 0);
    }
}

.form-step {
    opacity: 0;
    transition: opacity 0.3s ease;
}

#step1 {
    opacity: 1;
}

.focused {
    transition: all 0.3s ease;
}

.focused label {
    color: #4e73df;
    font-weight: 500;
}

.custom-file-upload:hover {
    cursor: pointer;
}

/* Amélioration de l'apparence des cartes */
.card {
    border: none;
    box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.1);
    transition: all 0.3s ease;
}

.card:hover {
    box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
}

.card-header {
    background-color: #f8f9fc;
    border-bottom: 1px solid #e3e6f0;
}

/* Style pour les boutons d'action */
.btn-primary, .btn-success {
    transition: all 0.2s ease;
}

.btn-primary:hover, .btn-success:hover {
    transform: translateY(-2px);
    box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
}

/* Animation pour les étapes complétées */
.step.completed .step-icon {
    animation: pulse 1s;
}

@keyframes pulse {
    0% {
        box-shadow: 0 0 0 0 rgba(28, 200, 138, 0.7);
    }
    70% {
        box-shadow: 0 0 0 10px rgba(28, 200, 138, 0);
    }
    100% {
        box-shadow: 0 0 0 0 rgba(28, 200, 138, 0);
    }
}
</style>

<?php include "./views/include/footer.php"; ?>

