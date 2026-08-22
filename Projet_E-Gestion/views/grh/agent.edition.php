<?php include "./views/include/header.php"; 

// Initialiser les modèles nécessaires
$agentModel = new Agent();
$gradeModel = new Grade();
$serviceModel = new Service();
$structureModel = new Structure();

// Variables pour stocker les données de l'agent
$agent = null;
$formations = [];
$gradesHistory = [];
$services = $serviceModel->getService();

// Traiter la recherche si soumise
$searchSubmitted = false;
$searchError = null;

if (isset($_GET['search']) && !empty($_GET['search']) && isset($_GET['searchType'])) {
    $searchSubmitted = true;
    $searchValue = trim($_GET['search']);
    $searchType = $_GET['searchType'];
    
    switch ($searchType) {
        case 'code':
            $agent = $agentModel->getAgentByCode($searchValue);
            break;
        case 'matricule':
            $agent = $agentModel->getAgentByMatricule($searchValue);
            break;
        case 'nom':
            $agents = $agentModel->searchAgentsByName($searchValue);
            if (count($agents) > 1) {
                $agent = null;
            } elseif (count($agents) == 1) {
                $agent = $agents[0];
            }
            break;
    }
    
    if ($agent) {
        // Récupérer les formations et l'historique des grades
        $formations = $agentModel->getFormationsForAgent($agent['idAgent']);
        $gradesHistory = $agentModel->getGradeHistoryForAgent($agent['idAgent']);
        
        // Récupérer les grades correspondant au type d'agent
        $grades = $gradeModel->getGradesByType($agent['type_agent']);
    } else {
        if (isset($agents) && count($agents) > 1) {
            // On a plusieurs résultats, pas d'erreur
        } else {
            $searchError = "Aucun agent trouvé avec ces critères de recherche.";
        }
    }
}

// Définir l'onglet actif
$activeTab = isset($_GET['tab']) ? $_GET['tab'] : 'general';
?>
<main id="main" class="main">
<div class="container-fluid">
    <div class="card shadow mb-4">
        <div class="card-header py-3 d-flex justify-content-between align-items-center">
            <h6 class="m-0 font-weight-bold text-primary">Modification des informations d'un agent</h6>
            <div>
                <?php if ($agent): ?>
                <a href="controller/generate_agent_pdf.php?id=<?= $agent['idAgent'] ?>" class="btn btn-sm btn-info me-2" target="_blank">
                    <i class="bi bi-file-earmark-pdf"></i> Générer fiche PDF
                </a>
                <?php endif; ?>
                <a href="index" class="btn btn-sm btn-secondary">
                    <i class="bi bi-arrow-left"></i> Retour
                </a>
            </div>
        </div>

        <div class="card-body">
            <!-- Formulaire de recherche -->
            <div class="card mb-4">
                <div class="card-header bg-light">
                    <h6 class="mb-0">Rechercher un agent</h6>
                </div>
                <div class="card-body">
                    <form method="GET" action="" class="row g-3">
                        <input type="hidden" name="page" value="grh/agent.edition">
                        <div class="col-md-4">
                            <label for="searchType" class="form-label">Rechercher par</label>
                            <select class="form-select" id="searchType" name="searchType">
                                <option value="code" <?= isset($_GET['searchType']) && $_GET['searchType'] == 'code' ? 'selected' : '' ?>>Code Agent</option>
                                <option value="matricule" <?= isset($_GET['searchType']) && $_GET['searchType'] == 'matricule' ? 'selected' : '' ?>>Matricule</option>
                                <option value="nom" <?= isset($_GET['searchType']) && $_GET['searchType'] == 'nom' ? 'selected' : '' ?>>Nom</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="search" class="form-label">Valeur de recherche</label>
                            <input type="text" class="form-control" id="search" name="search" value="<?= isset($_GET['search']) ? htmlspecialchars($_GET['search']) : '' ?>" >
                        </div>
                        <div class="col-md-2 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="bi bi-search"></i> Rechercher
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Affichage des résultats multiples si recherche par nom -->
            <?php if ($searchSubmitted && isset($agents) && count($agents) > 1): ?>
                <div class="card mb-4">
                    <div class="card-header bg-light">
                        <h6 class="mb-0">Résultats de la recherche</h6>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-bordered" width="100%" cellspacing="0">
                                <thead>
                                    <tr>
                                        <th>Code Agent</th>
                                        <th>Matricule</th>
                                        <th>Nom</th>
                                        <th>Type</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($agents as $a): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($a['codeAgent']) ?></td>
                                            <td><?= htmlspecialchars($a['matricule']) ?></td>
                                            <td><?= htmlspecialchars($a['noms']) ?></td>
                                            <td><?= htmlspecialchars($a['type_agent']) ?></td>
                                            <td>
                                                <a href="grh/agent.edition&searchType=code&search=<?= urlencode($a['codeAgent']) ?>" class="btn btn-sm btn-primary">
                                                    <i class="bi bi-pencil"></i> Éditer
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Message d'erreur si aucun agent trouvé -->
            <?php if ($searchError): ?>
                <div class="alert alert-danger">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i>
                    <?= $searchError ?>
                </div>
            <?php endif; ?>

            <!-- Formulaire d'édition si un agent est trouvé -->
            <?php if ($agent): ?>
                <div class="alert alert-info mb-4">
                    <i class="bi bi-info-circle-fill me-2"></i>
                    Veuillez remplir tous les champs obligatoires marqués par <span class="text-danger">*</span>
                </div>

                <!-- Onglets de navigation -->
                <ul class="nav nav-tabs mb-4" id="agentTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <a class="nav-link <?= $activeTab == 'general' ? 'active' : '' ?>" 
                           href="grh/agent.edition&searchType=<?= $_GET['searchType'] ?>&search=<?= urlencode($_GET['search']) ?>&tab=general" 
                           role="tab">
                            <i class="bi bi-person-vcard"></i> Infos générales
                        </a>
                    </li>
                    <li class="nav-item" role="presentation">
                        <a class="nav-link <?= $activeTab == 'personal' ? 'active' : '' ?>" 
                           href="grh/agent.edition&searchType=<?= $_GET['searchType'] ?>&search=<?= urlencode($_GET['search']) ?>&tab=personal" 
                           role="tab">
                            <i class="bi bi-person"></i> Infos personnelles
                        </a>
                    </li>
                    <li class="nav-item" role="presentation">
                        <a class="nav-link <?= $activeTab == 'formations' ? 'active' : '' ?>" 
                           href="grh/agent.edition&searchType=<?= $_GET['searchType'] ?>&search=<?= urlencode($_GET['search']) ?>&tab=formations" 
                           role="tab">
                            <i class="bi bi-mortarboard"></i> Formations
                        </a>
                    </li>
                    <li class="nav-item" role="presentation">
                        <a class="nav-link <?= $activeTab == 'professional' ? 'active' : '' ?>" 
                           href="grh/agent.edition&searchType=<?= $_GET['searchType'] ?>&search=<?= urlencode($_GET['search']) ?>&tab=professional" 
                           role="tab">
                            <i class="bi bi-briefcase"></i> Infos professionnelles
                        </a>
                    </li>
                    <li class="nav-item" role="presentation">
                        <a class="nav-link <?= $activeTab == 'grades' ? 'active' : '' ?>" 
                           href="grh/agent.edition&searchType=<?= $_GET['searchType'] ?>&search=<?= urlencode($_GET['search']) ?>&tab=grades" 
                           role="tab">
                            <i class="bi bi-award"></i> Grades
                        </a>
                    </li>
                </ul>

                <!-- Contenu des onglets -->
                <div class="tab-content" id="agentTabsContent">
                    <!-- Onglet Informations générales -->
                    <?php if ($activeTab == 'general'): ?>
                        <form action="controller/update_agent_general.php" method="post" enctype="multipart/form-data" class="needs-validation" novalidate>
                            <input type="hidden" name="idAgent" value="<?= $agent['idAgent'] ?>">
                            <input type="hidden" name="returnTab" value="general">
                            
                            <div class="card">
                                <div class="card-header bg-light">
                                    <h6 class="mb-0">Informations générales</h6>
                                </div>
                                <div class="card-body">
                                    <div class="row mb-3">
                                        <div class="col-md-4">
                                            <label for="type_agent" class="form-label">Type d'agent <span class="text-danger">*</span></label>
                                            <select class="form-select" id="type_agent" name="type_agent" required>
                                                <option value="" disabled>Sélectionner...</option>
                                                <option value="Administratif" <?= $agent['type_agent'] == 'Administratif' ? 'selected' : '' ?>>Administratif</option>
                                                <option value="Enseignant" <?= $agent['type_agent'] == 'Enseignant' ? 'selected' : '' ?>>Enseignant</option>
                                                <option value="Recherche" <?= $agent['type_agent'] == 'Recherche' ? 'selected' : '' ?>>Agent de recherche</option>
                                            </select>
                                        </div>
                                        <div class="col-md-4">
                                            <label for="matricule" class="form-label">Matricule <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control" id="matricule" name="matricule" value="<?= htmlspecialchars($agent['matricule']) ?>" required>
                                        </div>
                                        <div class="col-md-4">
                                            <label for="codeAgent" class="form-label">Code agent</label>
                                            <?php if (empty($agent['codeAgent'])): ?>
                                                <div class="input-group">
                                                    <input type="text" class="form-control" id="codeAgent" name="codeAgent" value="<?= htmlspecialchars($agent['codeAgent']) ?>">
                                                    <button class="btn btn-outline-secondary" type="button" id="generateCode">
                                                        <i class="bi bi-magic"></i> Générer
                                                    </button>
                                                </div>
                                                <small class="text-muted">Attribuez un code ou utilisez le générateur automatique</small>
                                            <?php else: ?>
                                                <input type="text" class="form-control" id="codeAgent" name="codeAgent" value="<?= htmlspecialchars($agent['codeAgent']) ?>" readonly>
                                                <small class="text-muted">Code unique généré automatiquement</small>
                                            <?php endif; ?>
                                        </div>

                                    </div>
                                    <div class="row mb-3">
                                        <div class="col-md-8">
                                            <label for="noms" class="form-label">Noms, Postnoms & Prénoms <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control" id="noms" name="noms" value="<?= htmlspecialchars($agent['noms']) ?>" required>
                                        </div>
                                        <div class="col-md-4">
                                            <label for="photo" class="form-label">Photo</label>
                                            <div class="custom-file-upload">
                                                <input type="file" class="form-control" id="photo" name="photo" accept="image/*">
                                                <div id="photoPreview" class="mt-2">
                                                    <?php if (!empty($agent['photo'])): ?>
                                                        <div class="card" style="max-width: 100px;">
                                                            <img src="uploads/agents/<?= htmlspecialchars($agent['photo']) ?>" class="card-img-top" alt="Photo de l'agent">
                                                            <div class="card-body p-2">
                                                                <button type="button" class="btn btn-sm btn-outline-danger w-100" id="removePhoto">
                                                                    <i class="bi bi-trash"></i> Supprimer
                                                                </button>
                                                            </div>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                                <input type="hidden" name="photo_actuelle" value="<?= htmlspecialchars($agent['photo'] ?? '') ?>">
                                                </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="card-footer">
                                    <button type="submit" class="btn btn-success">
                                        <i class="bi bi-save"></i> Enregistrer les informations générales
                                    </button>
                                </div>
                            </div>
                        </form>
                    <?php endif; ?>
                    
                    <!-- Onglet Informations personnelles -->
                    <?php if ($activeTab == 'personal'): ?>
                        <form action="controller/update_agent_personal.php" method="post" class="needs-validation" novalidate>
                            <input type="hidden" name="idAgent" value="<?= $agent['idAgent'] ?>">
                            <input type="hidden" name="returnTab" value="personal">
                            
                            <div class="card">
                                <div class="card-header bg-light">
                                    <h6 class="mb-0">Informations personnelles</h6>
                                </div>
                                <div class="card-body">
                                    <div class="row mb-3">
                                        <div class="col-md-3">
                                            <label for="sexe" class="form-label">Sexe <span class="text-danger">*</span></label>
                                            <select class="form-select" id="sexe" name="sexe" required>
                                                <option value="" disabled>Sélectionner...</option>
                                                <option value="M" <?= $agent['sexe'] == 'M' ? 'selected' : '' ?>>Masculin</option>
                                                <option value="F" <?= $agent['sexe'] == 'F' ? 'selected' : '' ?>>Féminin</option>
                                            </select>
                                        </div>
                                        <div class="col-md-3">
                                            <label for="dateNaissance" class="form-label">Date de naissance <span class="text-danger">*</span></label>
                                            <input type="date" class="form-control" id="dateNaissance" name="dateNaissance" value="<?= htmlspecialchars($agent['dateNaissance']) ?>" required>
                                        </div>
                                        <div class="col-md-6">
                                            <label for="lieuNaissance" class="form-label">Lieu de naissance <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control" id="lieuNaissance" name="lieuNaissance" value="<?= htmlspecialchars($agent['lieuNaissance']) ?>" required>
                                        </div>
                                    </div>
                                    <div class="row mb-3">
                                        <div class="col-md-4">
                                            <label for="etatCivil" class="form-label">État civil <span class="text-danger">*</span></label>
                                            <select class="form-select" id="etatCivil" name="etatCivil" required>
                                                <option value="" disabled>Sélectionner...</option>
                                                <option value="Célibataire" <?= $agent['etatCivil'] == 'Célibataire' ? 'selected' : '' ?>>Célibataire</option>
                                                <option value="Marié(e)" <?= $agent['etatCivil'] == 'Marié(e)' ? 'selected' : '' ?>>Marié(e)</option>
                                                <option value="Divorcé(e)" <?= $agent['etatCivil'] == 'Divorcé(e)' ? 'selected' : '' ?>>Divorcé(e)</option>
                                                <option value="Veuf(ve)" <?= $agent['etatCivil'] == 'Veuf(ve)' ? 'selected' : '' ?>>Veuf(ve)</option>
                                            </select>
                                        </div>
                                        <div class="col-md-8">
                                            <label for="conjoint" class="form-label">Nom du conjoint(e)</label>
                                            <input type="text" class="form-control" id="conjoint" name="conjoint" value="<?= htmlspecialchars($agent['conjoint'] ?? '') ?>">
                                        </div>
                                    </div>
                                    <div class="row mb-3">
                                        <div class="col-md-12">
                                            <label class="form-label">Adresse</label>
                                            <div class="row g-2">
                                                <div class="col-md-4">
                                                    <input type="text" class="form-control" name="adresse_avenue" placeholder="Avenue" value="<?= htmlspecialchars($agent['adresse_avenue'] ?? '') ?>">
                                                </div>
                                                <div class="col-md-4">
                                                    <input type="text" class="form-control" name="adresse_quartier" placeholder="Quartier" value="<?= htmlspecialchars($agent['adresse_quartier'] ?? '') ?>">
                                                </div>
                                                <div class="col-md-4">
                                                    <input type="text" class="form-control" name="adresse_commune" placeholder="Commune" value="<?= htmlspecialchars($agent['adresse_commune'] ?? '') ?>">
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="row mb-3">
                                        <div class="col-md-6">
                                            <label for="telephone" class="form-label">Téléphone <span class="text-danger">*</span></label>
                                            <input type="tel" class="form-control" id="telephone" name="telephone" value="<?= htmlspecialchars($agent['telephone']) ?>" required>
                                        </div>
                                        <div class="col-md-6">
                                            <label for="email" class="form-label">Email</label>
                                            <input type="email" class="form-control" id="email" name="email" value="<?= htmlspecialchars($agent['email'] ?? '') ?>">
                                        </div>
                                    </div>
                                    <div class="row mb-3">
                                        <div class="col-md-4">
                                            <label for="contact_urgence" class="form-label">Personne à contacter en cas d'urgence</label>
                                            <input type="text" class="form-control" id="contact_urgence" name="contact_urgence" value="<?= htmlspecialchars($agent['contact_urgence'] ?? '') ?>">
                                        </div>
                                        <div class="col-md-4">
                                            <label for="degre_parente_urgence" class="form-label">Degré de parenté</label>
                                            <input type="text" class="form-control" id="degre_parente_urgence" name="degre_parente_urgence" value="<?= htmlspecialchars($agent['degre_parente_urgence'] ?? '') ?>">
                                        </div>
                                        <div class="col-md-4">
                                            <label for="telephone_urgence" class="form-label">Téléphone d'urgence</label>
                                            <input type="tel" class="form-control" id="telephone_urgence" name="telephone_urgence" value="<?= htmlspecialchars($agent['telephone_urgence'] ?? '') ?>">
                                        </div>
                                    </div>
                                </div>
                                <div class="card-footer">
                                    <button type="submit" class="btn btn-success">
                                        <i class="bi bi-save"></i> Enregistrer les informations personnelles
                                    </button>
                                </div>
                            </div>
                        </form>
                    <?php endif; ?>
                    
                    <!-- Onglet Formations -->
                    <?php if ($activeTab == 'formations'): ?>
                        <form action="controller/update_agent_formations.php" method="post" enctype="multipart/form-data" class="needs-validation" novalidate>
                            <input type="hidden" name="idAgent" value="<?= $agent['idAgent'] ?>">
                            <input type="hidden" name="returnTab" value="formations">
                            
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
                                            <label for="niveauEtude" class="form-label">Niveau d'étude le plus élevé <span class="text-danger">*</span></label>
                                            <select class="form-select" id="niveauEtude" name="niveauEtude" required>
                                                <option value="" disabled>Sélectionner...</option>
                                                <option value="Certificat primaire" <?= $agent['niveauEtude'] == 'Certificat primaire' ? 'selected' : '' ?>>Certificat primaire</option>
                                                <option value="Diplôme d'état" <?= $agent['niveauEtude'] == 'Diplôme d\'état' ? 'selected' : '' ?>>Diplôme d'état</option>
                                                <option value="Graduat" <?= $agent['niveauEtude'] == 'Graduat' ? 'selected' : '' ?>>Graduat</option>
                                                <option value="Licence" <?= $agent['niveauEtude'] == 'Licence' ? 'selected' : '' ?>>Licence</option>
                                                <option value="Master" <?= $agent['niveauEtude'] == 'Master' ? 'selected' : '' ?>>Master</option>
                                                <option value="Doctorat" <?= $agent['niveauEtude'] == 'Doctorat' ? 'selected' : '' ?>>Doctorat</option>
                                            </select>
                                        </div>
                                    </div>
                                    
                                    <!-- Liste des formations existantes -->
                                    <div class="table-responsive mb-3">
                                        <table class="table table-bordered table-hover">
                                            <thead class="table-light">
                                                <tr>
                                                    <th>Niveau</th>
                                                    <th>Établissement</th>
                                                    <th>Filière</th>
                                                    <th>Année d'obtention</th>
                                                    <th>Diplôme</th>
                                                    <th>Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php if (empty($formations)): ?>
                                                    <tr>
                                                        <td colspan="6" class="text-center">Aucune formation enregistrée</td>
                                                    </tr>
                                                <?php else: ?>
                                                    <?php foreach ($formations as $index => $formation): ?>
                                                        <tr>
                                                            <td><?= htmlspecialchars($formation['niveau']) ?></td>
                                                            <td><?= htmlspecialchars($formation['etablissement']) ?></td>
                                                            <td><?= htmlspecialchars($formation['filiere']) ?></td>
                                                            <td><?= htmlspecialchars($formation['annee_obtention']) ?></td>
                                                            <td>
                                                                <?php if (!empty($formation['diplome_fichier'])): ?>
                                                                    <a href="<?= htmlspecialchars($formation['diplome_fichier']) ?>" target="_blank" class="btn btn-sm btn-outline-info">
                                                                        <i class="bi bi-file-earmark-pdf"></i> Voir
                                                                    </a>
                                                                <?php else: ?>
                                                                    <span class="badge bg-light text-dark">Non disponible</span>
                                                                <?php endif; ?>
                                                            </td>
                                                            <td>
                                                                <button type="button" class="btn btn-sm btn-primary edit-formation" data-id="<?= $formation['idformation'] ?>">
                                                                    <i class="bi bi-pencil"></i>
                                                                </button>
                                                                <button type="button" class="btn btn-sm btn-danger delete-formation" data-id="<?= $formation['idformation'] ?>">
                                                                    <i class="bi bi-trash"></i>
                                                                </button>
                                                            </td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                <?php endif; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                    
                                    <!-- Formulaire pour ajouter/modifier une formation -->
                                    <div id="formationForm" class="border rounded p-3 mb-3 bg-light d-none">
                                        <h6 class="mb-3">Ajouter une nouvelle formation</h6>
                                        <input type="hidden" name="formation_id" id="formation_id" value="">
                                        
                                        <div class="row mb-2">
                                            <div class="col-md-4">
                                                <label class="form-label">Niveau</label>
                                                <select class="form-select" name="formation_niveau" id="formation_niveau">
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
                                                <input type="text" class="form-control" name="formation_etablissement" id="formation_etablissement">
                                            </div>
                                        </div>
                                        <div class="row mb-2">
                                            <div class="col-md-6">
                                                <label class="form-label">Filière/Faculté</label>
                                                <input type="text" class="form-control" name="formation_filiere" id="formation_filiere">
                                                </div>
                                            <div class="col-md-4">
                                                <label class="form-label">Année d'obtention</label>
                                                <input type="number" class="form-control" name="formation_annee" id="formation_annee" min="1950" max="<?= date('Y') ?>">
                                            </div>
                                            <div class="col-md-2">
                                                <label class="form-label">Diplôme</label>
                                                <input type="file" class="form-control" name="formation_diplome" id="formation_diplome">
                                                <div id="diplome_actuel" class="mt-1"></div>
                                            </div>
                                        </div>
                                        <div class="mt-3">
                                            <button type="button" class="btn btn-primary" id="saveFormation">
                                                <i class="bi bi-check-circle"></i> Enregistrer
                                            </button>
                                            <button type="button" class="btn btn-secondary" id="cancelFormation">
                                                <i class="bi bi-x-circle"></i> Annuler
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                <div class="card-footer">
                                    <button type="submit" class="btn btn-success">
                                        <i class="bi bi-save"></i> Enregistrer les formations
                                    </button>
                                </div>
                            </div>
                        </form>
                    <?php endif; ?>
                    
                    <!-- Onglet Informations professionnelles -->
                    <?php if ($activeTab == 'professional'): ?>
                        <form action="controller/update_agent_professional.php" method="post" class="needs-validation" novalidate>
                            <input type="hidden" name="idAgent" value="<?= $agent['idAgent'] ?>">
                            <input type="hidden" name="returnTab" value="professional">
                            
                            <div class="card">
                                <div class="card-header bg-light">
                                    <h6 class="mb-0">Informations professionnelles</h6>
                                </div>
                                <div class="card-body">
                                    <div class="row mb-3">
                                        <div class="col-md-4">
                                            <label for="annee_engagement" class="form-label">Année d'engagement</label>
                                            <input type="number" class="form-control" id="annee_engagement" name="annee_engagement" min="1950" max="<?= date('Y') ?>" value="<?= htmlspecialchars($agent['annee_engagement'] ?? '') ?>">
                                        </div>
                                        <div class="col-md-4">
                                            <label for="reference_acte_engagement" class="form-label">Référence de l'acte d'engagement</label>
                                            <input type="text" class="form-control" id="reference_acte_engagement" name="reference_acte_engagement" value="<?= htmlspecialchars($agent['reference_acte_engagement'] ?? '') ?>">
                                        </div>
                                        <div class="col-md-4">
                                            <label for="grade_id" class="form-label">Grade actuel <span class="text-danger">*</span></label>
                                            <select class="form-select" id="grade_id" name="grade_id" required>
                                                <option value="" disabled>Sélectionner...</option>
                                                <?php foreach ($grades as $grade): ?>
                                                    <option value="<?= $grade['idgrade'] ?>" <?= $agent['grade_id'] == $grade['idgrade'] ? 'selected' : '' ?>>
                                                        <?= htmlspecialchars($grade['designation']) ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                    
                                    <!-- Champs spécifiques selon le type d'agent -->
                                    <div id="adminFields" class="<?= $agent['type_agent'] == 'Administratif' ? '' : 'd-none' ?>">
                                        <div class="row mb-3">
                                            <div class="col-md-6">
                                                <label for="direction" class="form-label">Direction</label>
                                                <input type="text" class="form-control" id="direction" name="direction" value="<?= htmlspecialchars($agent['direction'] ?? '') ?>">
                                            </div>
                                            <div class="col-md-6">
                                                <label for="division" class="form-label">Division</label>
                                                <input type="text" class="form-control" id="division" name="division" value="<?= htmlspecialchars($agent['division'] ?? '') ?>">
                                            </div>
                                        </div>
                                        <div class="row mb-3">
                                            <div class="col-md-6">
                                                <label for="decision_grade" class="form-label">Décision grade</label>
                                                <input type="text" class="form-control" id="decision_grade" name="decision_grade" value="<?= htmlspecialchars($agent['decision_grade'] ?? '') ?>">
                                            </div>
                                            <div class="col-md-6">
                                                <label for="notification_grade" class="form-label">Notification grade</label>
                                                <input type="text" class="form-control" id="notification_grade" name="notification_grade" value="<?= htmlspecialchars($agent['notification_grade'] ?? '') ?>">
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div id="teacherFields" class="<?= $agent['type_agent'] == 'Enseignant' ? '' : 'd-none' ?>">
                                        <div class="row mb-3">
                                            <div class="col-md-6">
                                                <label for="specialisation" class="form-label">Spécialisation</label>
                                                <input type="text" class="form-control" id="specialisation" name="specialisation" value="<?= htmlspecialchars($agent['specialisation'] ?? '') ?>">
                                            </div>
                                            <div class="col-md-6">
                                                <label for="domaine_recherche" class="form-label">Domaine de recherche</label>
                                                <input type="text" class="form-control" id="domaine_recherche" name="domaine_recherche" value="<?= htmlspecialchars($agent['domaine_recherche'] ?? '') ?>">
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div id="researchFields" class="<?= $agent['type_agent'] == 'Recherche' ? '' : 'd-none' ?>">
                                        <div class="row mb-3">
                                            <div class="col-md-6">
                                                <label for="unite_recherche" class="form-label">Unité de recherche</label>
                                                <input type="text" class="form-control" id="unite_recherche" name="unite_recherche" value="<?= htmlspecialchars($agent['unite_recherche'] ?? '') ?>">
                                            </div>
                                            <div class="col-md-6">
                                                <label for="projet_recherche" class="form-label">Projet de recherche</label>
                                                <input type="text" class="form-control" id="projet_recherche" name="projet_recherche" value="<?= htmlspecialchars($agent['projet_recherche'] ?? '') ?>">
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="row mb-3">
                                        <div class="col-md-6">
                                            <label for="idStructure" class="form-label">Campus <span class="text-danger">*</span></label>
                                            <select class="form-select" id="idStructure" name="idStructure" required>
                                                <option value="" disabled>Sélectionner...</option>
                                                <?php
                                                $structures = $structureModel->getStructures();
                                                foreach ($structures as $structure) {
                                                    $selected = ($agent['idStructure'] == $structure['idStructure']) ? 'selected' : '';
                                                    echo "<option value='{$structure['idStructure']}' {$selected}>{$structure['designation']}</option>";
                                                }
                                                ?>
                                            </select>
                                        </div>
                                        <div class="col-md-6">
                                            <label for="idService" class="form-label">Service <span class="text-danger">*</span></label>
                                            <select class="form-select" id="idService" name="idService" required>
                                                <option value="" disabled>Sélectionner un service</option>
                                                <?php foreach ($services as $service): ?>
                                                    <option value="<?= $service['idService'] ?>" <?= $agent['idService'] == $service['idService'] ? 'selected' : '' ?>>
                                                        <?= htmlspecialchars($service['designationService']) ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                    
                                    <div class="row mt-4">
                                        <div class="col-md-4">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" id="prime_locale" name="prime_locale" value="1" <?= isset($agent['prime_locale']) && $agent['prime_locale'] == 1 ? 'checked' : '' ?>>
                                                <label class="form-check-label" for="prime_locale">
                                                    Paiement en prime locale
                                                </label>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" id="salaire_etat" name="salaire_etat" value="1" <?= isset($agent['salaire_etat']) && $agent['salaire_etat'] == 1 ? 'checked' : '' ?>>
                                                <label class="form-check-label" for="salaire_etat">
                                                    Paiement salaire de base de l'état congolais
                                                </label>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" id="prime_institutionnelle" name="prime_institutionnelle" value="1" <?= isset($agent['prime_institutionnelle']) && $agent['prime_institutionnelle'] == 1 ? 'checked' : '' ?>>
                                                <label class="form-check-label" for="prime_institutionnelle">
                                                    Paiement de la prime institutionnelle
                                                </label>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="card-footer">
                                    <button type="submit" class="btn btn-success">
                                        <i class="bi bi-save"></i> Enregistrer les informations professionnelles
                                    </button>
                                </div>
                            </div>
                        </form>
                    <?php endif; ?>
                    
                    <!-- Onglet Grades -->
                    <?php if ($activeTab == 'grades'): ?>
                        <form action="controller/update_agent_grades.php" method="post" class="needs-validation" novalidate>
                            <input type="hidden" name="idAgent" value="<?= $agent['idAgent'] ?>">
                            <input type="hidden" name="returnTab" value="grades">
                            
                            <div class="card">
                                <div class="card-header bg-light d-flex justify-content-between align-items-center">
                                    <h6 class="mb-0">Historique des grades</h6>
                                    <button type="button" class="btn btn-sm btn-outline-primary" id="addGradeBtn">
                                        <i class="bi bi-plus-circle"></i> Ajouter un grade
                                    </button>
                                </div>
                                <div class="card-body">
                                    <!-- Liste des grades existants -->
                                    <div class="table-responsive mb-3">
                                        <table class="table table-bordered table-hover">
                                            <thead class="table-light">
                                                <tr>
                                                    <th>Grade</th>
                                                    <th>Date de promotion</th>
                                                    <th>Référence décision</th>
                                                    <th>Référence notification</th>
                                                    <th>Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php if (empty($gradesHistory)): ?>
                                                    <tr>
                                                        <td colspan="5" class="text-center">Aucun historique de grade enregistré</td>
                                                    </tr>
                                                <?php else: ?>
                                                    <?php foreach ($gradesHistory as $gradeHistory): ?>
                                                        <tr>
                                                            <td>
                                                                <?php
                                                                foreach ($grades as $grade) {
                                                                    if ($grade['idgrade'] == $gradeHistory['idgrade']) {
                                                                        echo htmlspecialchars($grade['designation']);
                                                                        break;
                                                                    }
                                                                }
                                                                ?>
                                                            </td>
                                                            <td><?= htmlspecialchars($gradeHistory['date_promotion']) ?></td>
                                                            <td><?= htmlspecialchars($gradeHistory['reference_decision'] ?? '') ?></td>
                                                            <td><?= htmlspecialchars($gradeHistory['reference_notification'] ?? '') ?></td>
                                                            <td>
                                                                <button type="button" class="btn btn-sm btn-primary edit-grade" data-id="<?= $gradeHistory['idhistorique_grade'] ?>">
                                                                    <i class="bi bi-pencil"></i>
                                                                </button>
                                                                <button type="button" class="btn btn-sm btn-danger delete-grade" data-id="<?= $gradeHistory['idhistorique_grade'] ?>">
                                                                    <i class="bi bi-trash"></i>
                                                                </button>
                                                            </td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                <?php endif; ?>
                                            </tbody>
                                        </table>
                                        </div>
                                    
                                    <!-- Formulaire pour ajouter/modifier un grade -->
                                    <div id="gradeForm" class="border rounded p-3 mb-3 bg-light d-none">
                                        <h6 class="mb-3" id="gradeFormTitle">Ajouter un nouveau grade</h6>
                                        <input type="hidden" name="grade_history_id" id="grade_history_id" value="">
                                        
                                        <div class="row mb-2">
                                            <div class="col-md-4">
                                                <label class="form-label">Grade</label>
                                                <select class="form-select" name="grade_idgrade" id="grade_idgrade">
                                                    <option value="" disabled selected>Sélectionner...</option>
                                                    <?php foreach ($grades as $grade): ?>
                                                        <option value="<?= $grade['idgrade'] ?>"><?= htmlspecialchars($grade['designation']) ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            <div class="col-md-4">
                                                <label class="form-label">Date de promotion</label>
                                                <input type="date" class="form-control" name="grade_date_promotion" id="grade_date_promotion">
                                            </div>
                                            <div class="col-md-4">
                                                <label class="form-label">Référence décision</label>
                                                <input type="text" class="form-control" name="grade_reference_decision" id="grade_reference_decision">
                                            </div>
                                        </div>
                                        <div class="row mb-2">
                                            <div class="col-md-12">
                                                <label class="form-label">Référence notification</label>
                                                <input type="text" class="form-control" name="grade_reference_notification" id="grade_reference_notification">
                                            </div>
                                        </div>
                                        <div class="mt-3">
                                            <button type="button" class="btn btn-primary" id="saveGrade">
                                                <i class="bi bi-check-circle"></i> Enregistrer
                                            </button>
                                            <button type="button" class="btn btn-secondary" id="cancelGrade">
                                                <i class="bi bi-x-circle"></i> Annuler
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                <div class="card-footer">
                                    <button type="submit" class="btn btn-success">
                                        <i class="bi bi-save"></i> Enregistrer les grades
                                    </button>
                                </div>
                            </div>
                        </form>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
</main>
<style>
.nav-tabs .nav-link {
    color: #4e73df;
    font-weight: 500;
}

.nav-tabs .nav-link.active {
    color: #2e59d9;
    font-weight: 600;
    border-color: #4e73df #4e73df #fff;
}

.formation-item, .grade-history-item {
    background-color: #f8f9fc;
    border-radius: 5px;
    transition: all 0.3s ease;
}

.formation-item:hover, .grade-history-item:hover {
    box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
}

.custom-file-upload {
    position: relative;
}

#photoPreview {
    margin-top: 10px;
}

.tab-content {
    padding-top: 20px;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Gestion du type d'agent et chargement des grades correspondants
    const typeAgentSelect = document.getElementById('type_agent');
    const gradeSelect = document.getElementById('grade_id');
    const structureSelect = document.getElementById('idStructure');
    const serviceSelect = document.getElementById('idService');
    const adminFields = document.getElementById('adminFields');
    const teacherFields = document.getElementById('teacherFields');
    const researchFields = document.getElementById('researchFields');
    
    // Événement de changement du type d'agent
    if (typeAgentSelect) {
        typeAgentSelect.addEventListener('change', function() {
            const agentType = this.value;
            
            // Afficher/masquer les champs spécifiques
            adminFields?.classList.add('d-none');
            teacherFields?.classList.add('d-none');
            researchFields?.classList.add('d-none');
            
            if (agentType === 'Administratif') {
                adminFields?.classList.remove('d-none');
            } else if (agentType === 'Enseignant') {
                teacherFields?.classList.remove('d-none');
            } else if (agentType === 'Recherche') {
                researchFields?.classList.remove('d-none');
            }
            
            // Charger les grades correspondants
            if (agentType) {
                loadGrades(agentType);
            }
        });
    }
    
    // Fonction pour charger les grades en fonction du type d'agent
    function loadGrades(agentType) {
        fetch(`controller/get_grades_by_type.php?type=${agentType}`)
            .then(response => response.json())
            .then(data => {
                if (gradeSelect) {
                    gradeSelect.innerHTML = '<option value="" disabled selected>Sélectionner...</option>';
                    data.forEach(grade => {
                        gradeSelect.innerHTML += `<option value="${grade.idgrade}">${grade.designation}</option>`;
                    });
                }
                
                // Mettre à jour les sélecteurs de grade dans le formulaire des grades
                const gradeIdgradeSelect = document.getElementById('grade_idgrade');
                if (gradeIdgradeSelect) {
                    gradeIdgradeSelect.innerHTML = '<option value="" disabled selected>Sélectionner...</option>';
                    data.forEach(grade => {
                        gradeIdgradeSelect.innerHTML += `<option value="${grade.idgrade}">${grade.designation}</option>`;
                    });
                }
            })
            .catch(error => console.error('Erreur lors du chargement des grades:', error));
    }
    
    // Chargement des services en fonction de la structure
    if (structureSelect) {
        structureSelect.addEventListener('change', function() {
            const structureId = this.value;
            if (structureId && serviceSelect) {
                loadServices(structureId);
            }
        });
    }
    
    function loadServices(structureId) {
        fetch(`controller/get_services_by_structure.php?structure=${structureId}`)
            .then(response => response.json())
            .then(data => {
                if (serviceSelect) {
                    serviceSelect.innerHTML = '<option value="" disabled selected>Sélectionner...</option>';
                    data.forEach(service => {
                        serviceSelect.innerHTML += `<option value="${service.idService}">${service.designationService}</option>`;
                    });
                }
            })
            .catch(error => console.error('Erreur lors du chargement des services:', error));
    }
    
    // Gestion du formulaire de formation
    const addFormationBtn = document.getElementById('addFormationBtn');
    const formationForm = document.getElementById('formationForm');
    const saveFormationBtn = document.getElementById('saveFormation');
    const cancelFormationBtn = document.getElementById('cancelFormation');
    
    if (addFormationBtn) {
        addFormationBtn.addEventListener('click', function() {
            // Réinitialiser le formulaire
            document.getElementById('formation_id').value = '';
            document.getElementById('formation_niveau').value = 'Licence';
            document.getElementById('formation_etablissement').value = '';
            document.getElementById('formation_filiere').value = '';
            document.getElementById('formation_annee').value = '';
            document.getElementById('formation_diplome').value = '';
            document.getElementById('diplome_actuel').innerHTML = '';
            
            // Mettre à jour le titre et afficher le formulaire
            document.querySelector('#formationForm h6').textContent = 'Ajouter une nouvelle formation';
            formationForm.classList.remove('d-none');
        });
    }
    
    // Fonctions pour éditer les formations existantes
    document.querySelectorAll('.edit-formation').forEach(button => {
        button.addEventListener('click', function() {
            const formationId = this.getAttribute('data-id');
            // Charger les données de la formation par AJAX
            fetch(`controller/get_formation.php?id=${formationId}`)
                .then(response => response.json())
                .then(data => {
                    document.getElementById('formation_id').value = data.id;
                    document.getElementById('formation_niveau').value = data.niveau;
                    document.getElementById('formation_etablissement').value = data.etablissement;
                    document.getElementById('formation_filiere').value = data.filiere;
                    document.getElementById('formation_annee').value = data.annee_obtention;
                    
                    if (data.diplome_fichier) {
                        document.getElementById('diplome_actuel').innerHTML = `
                            <small class="text-muted">Fichier actuel: 
                                <a href="${data.diplome_fichier}" target="_blank">${data.diplome_fichier.split('/').pop()}</a>
                            </small>
                            <input type="hidden" name="formation_diplome_actuel" value="${data.diplome_fichier}">
                        `;
                    } else {
                        document.getElementById('diplome_actuel').innerHTML = '';
                    }
                    
                    // Mettre à jour le titre et afficher le formulaire
                    document.querySelector('#formationForm h6').textContent = 'Modifier la formation';
                    formationForm.classList.remove('d-none');
                })
                .catch(error => console.error('Erreur lors du chargement de la formation:', error));
        });
    });
    
    // Supprimer une formation
    document.querySelectorAll('.delete-formation').forEach(button => {
        button.addEventListener('click', function() {
            const formationId = this.getAttribute('data-id');
            
            Swal.fire({
                title: 'Êtes-vous sûr?',
                text: "Cette action supprimera définitivement cette formation!",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#d33',
                confirmButtonText: 'Oui, supprimer',
                cancelButtonText: 'Annuler'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = `controller/delete_formation.php?id=${formationId}&agent_id=<?= $agent['idAgent'] ?>&returnTab=formations`;
                }
            });
        });
    });
    
    // Annuler l'ajout/modification d'une formation
    if (cancelFormationBtn) {
        cancelFormationBtn.addEventListener('click', function() {
            formationForm.classList.add('d-none');
        });
    }
    
    // Gestion du formulaire de grade
    const addGradeBtn = document.getElementById('addGradeBtn');
    const gradeForm = document.getElementById('gradeForm');
    const saveGradeBtn = document.getElementById('saveGrade');
    const cancelGradeBtn = document.getElementById('cancelGrade');
    
    if (addGradeBtn) {
        addGradeBtn.addEventListener('click', function() {
            // Réinitialiser le formulaire
            document.getElementById('grade_history_id').value = '';
            document.getElementById('grade_idgrade').value = '';
            document.getElementById('grade_date_promotion').value = '';
            document.getElementById('grade_reference_decision').value = '';
            document.getElementById('grade_reference_notification').value = '';
            
            // Mettre à jour le titre et afficher le formulaire
            document.getElementById('gradeFormTitle').textContent = 'Ajouter un nouveau grade';
            gradeForm.classList.remove('d-none');
        });
    }
    
    // Fonctions pour éditer les grades existants
    document.querySelectorAll('.edit-grade').forEach(button => {
        button.addEventListener('click', function() {
            const gradeHistoryId = this.getAttribute('data-id');
            // Charger les données du grade par AJAX
            fetch(`controller/get_grade_history.php?id=${gradeHistoryId}`)
                .then(response => response.json())
                .then(data => {
                    document.getElementById('grade_history_id').value = data.id;
                    document.getElementById('grade_idgrade').value = data.idgrade;
                    document.getElementById('grade_date_promotion').value = data.date_promotion;
                    document.getElementById('grade_reference_decision').value = data.reference_decision || '';
                    document.getElementById('grade_reference_notification').value = data.reference_notification || '';
                    
                    // Mettre à jour le titre et afficher le formulaire
                    document.getElementById('gradeFormTitle').textContent = 'Modifier le grade';
                    gradeForm.classList.remove('d-none');
                })
                .catch(error => console.error('Erreur lors du chargement du grade:', error));
        });
    });
    
    // Supprimer un grade
    document.querySelectorAll('.delete-grade').forEach(button => {
        button.addEventListener('click', function() {
            const gradeHistoryId = this.getAttribute('data-id');
            
            Swal.fire({
                title: 'Êtes-vous sûr?',
                text: "Cette action supprimera définitivement ce grade de l'historique!",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#d33',
                confirmButtonText: 'Oui, supprimer',
                cancelButtonText: 'Annuler'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = `controller/delete_grade_history.php?id=${gradeHistoryId}&agent_id=<?= $agent['idAgent'] ?>&returnTab=grades`;
                }
            });
        });
    });
    
    // Annuler l'ajout/modification d'un grade
    if (cancelGradeBtn) {
        cancelGradeBtn.addEventListener('click', function() {
            gradeForm.classList.add('d-none');
        });
    }
    
    // Prévisualisation de la photo
    const photoInput = document.getElementById('photo');
    if (photoInput) {
        photoInput.addEventListener('change',
        function(e) {
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
                        
                        // Ajouter un champ caché pour indiquer que la photo doit être supprimée
                        const removePhotoField = document.createElement('input');
                        removePhotoField.type = 'hidden';
                        removePhotoField.name = 'remove_photo';
                        removePhotoField.value = '1';
                        photoPreview.appendChild(removePhotoField);
                    });
                };
                reader.readAsDataURL(this.files[0]);
            }
        });
    }
    
    // Gestion du bouton de suppression de photo existant
    const removePhotoButton = document.getElementById('removePhoto');
    if (removePhotoButton) {
        removePhotoButton.addEventListener('click', function() {
            const photoInput = document.getElementById('photo');
            const photoPreview = document.getElementById('photoPreview');
            
            photoInput.value = '';
            photoPreview.innerHTML = '';
            
            // Ajouter un champ caché pour indiquer que la photo doit être supprimée
            const removePhotoField = document.createElement('input');
            removePhotoField.type = 'hidden';
            removePhotoField.name = 'remove_photo';
            removePhotoField.value = '1';
            photoPreview.appendChild(removePhotoField);
        });
    }
    
    // Validation des formulaires
    document.querySelectorAll('form.needs-validation').forEach(form => {
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
                // Afficher un message de chargement
                Swal.fire({
                    title: 'Mise à jour en cours...',
                    html: 'Veuillez patienter pendant la sauvegarde des modifications.',
                    allowOutsideClick: false,
                    didOpen: () => {
                        Swal.showLoading();
                    }
                });
            }
            
            form.classList.add('was-validated');
        });
    });
    
    // Gestion des formulaires AJAX pour formations et grades
    if (saveFormationBtn) {
        saveFormationBtn.addEventListener('click', function() {
            const formData = new FormData();
            formData.append('idAgent', <?= $agent['idAgent'] ?>);
            formData.append('formation_id', document.getElementById('formation_id').value);
            formData.append('niveau', document.getElementById('formation_niveau').value);
            formData.append('etablissement', document.getElementById('formation_etablissement').value);
            formData.append('filiere', document.getElementById('formation_filiere').value);
            formData.append('annee_obtention', document.getElementById('formation_annee').value);
            
            const diplomeFile = document.getElementById('formation_diplome').files[0];
            if (diplomeFile) {
                formData.append('diplome_fichier', diplomeFile);
            }
            
            const diplomeActuel = document.querySelector('input[name="formation_diplome_actuel"]');
            if (diplomeActuel) {
                formData.append('diplome_fichier_actuel', diplomeActuel.value);
            }
            
            // Envoyer les données via AJAX
            fetch('controller/save_formation.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Succès',
                        text: data.message,
                        confirmButtonColor: '#4e73df'
                    }).then(() => {
                        // Recharger la page pour voir les modifications
                        window.location.href = `grh/agent.edition&searchType=<?= $_GET['searchType'] ?>&search=<?= urlencode($_GET['search']) ?>&tab=formations`;
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Erreur',
                        text: data.message || 'Une erreur est survenue',
                        confirmButtonColor: '#4e73df'
                    });
                }
            })
            .catch(error => {
                console.error('Erreur:', error);
                Swal.fire({
                    icon: 'error',
                    title: 'Erreur',
                    text: 'Une erreur est survenue lors de la communication avec le serveur',
                    confirmButtonColor: '#4e73df'
                });
            });
        });
    }
    
    if (saveGradeBtn) {
        saveGradeBtn.addEventListener('click', function() {
            const formData = new FormData();
            formData.append('idAgent', <?= $agent['idAgent'] ?>);
            formData.append('grade_history_id', document.getElementById('grade_history_id').value);
            formData.append('idgrade', document.getElementById('grade_idgrade').value);
            formData.append('date_promotion', document.getElementById('grade_date_promotion').value);
            formData.append('reference_decision', document.getElementById('grade_reference_decision').value);
            formData.append('reference_notification', document.getElementById('grade_reference_notification').value);
            
            // Envoyer les données via AJAX
            fetch('controller/save_grade_history.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Succès',
                        text: data.message,
                        confirmButtonColor: '#4e73df'
                    }).then(() => {
                        // Recharger la page pour voir les modifications
                        window.location.href = `grh/agent.edition&searchType=<?= $_GET['searchType'] ?>&search=<?= urlencode($_GET['search']) ?>&tab=grades`;
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Erreur',
                        text: data.message || 'Une erreur est survenue',
                        confirmButtonColor: '#4e73df'
                    });
                }
            })
            .catch(error => {
                console.error('Erreur:', error);
                Swal.fire({
                    icon: 'error',
                    title: 'Erreur',
                    text: 'Une erreur est survenue lors de la communication avec le serveur',
                    confirmButtonColor: '#4e73df'
                });
            });
        });
    }
});

document.addEventListener('DOMContentLoaded', function() {
    // Check for SweetAlert success message
    <?php if(isset($_SESSION['swal_success'])): ?>
        Swal.fire({
            title: "<?= $_SESSION['swal_success']['title'] ?>",
            text: "<?= $_SESSION['swal_success']['text'] ?>",
            icon: "<?= $_SESSION['swal_success']['icon'] ?>",
            confirmButtonColor: '#4e73df'
        });
        <?php unset($_SESSION['swal_success']); ?>
    <?php endif; ?>
    
    // Check for SweetAlert error message
    <?php if(isset($_SESSION['swal_error'])): ?>
        Swal.fire({
            title: "<?= $_SESSION['swal_error']['title'] ?>",
            text: "<?= $_SESSION['swal_error']['text'] ?>",
            icon: "<?= $_SESSION['swal_error']['icon'] ?>",
            confirmButtonColor: '#4e73df'
        });
        <?php unset($_SESSION['swal_error']); ?>
    <?php endif; ?>
});

document.addEventListener('DOMContentLoaded', function() {
    const generateCodeBtn = document.getElementById('generateCode');
    if (generateCodeBtn) {
        generateCodeBtn.addEventListener('click', function() {
            // Utiliser le même format que dans controller/create_agent.php
            const prefix = "AG";
            const randomNum = Math.floor(Math.random() * 999999) + 1;
            const paddedNum = randomNum.toString().padStart(6, '0');
            const generatedCode = prefix + paddedNum;
            
            document.getElementById('codeAgent').value = generatedCode;
        });
    }
});


</script>

<?php include "./views/include/footer.php"; ?>
