<?php
include "./views/include/header.php";
$universite = new Universite();
$agentModel = new Agent();

// Récupérer l'année académique actuelle (active)
$currentYear = $universite->getCurrentAcademicYear();

$search = isset($_GET['search']) ? $_GET['search'] : '';

// Fetch sections for dropdowns (filtrées par année courante)
$sections = $universite->getSections();
if ($currentYear) {
    $sections = array_filter($sections, function($section) use ($universite, $currentYear) {
        // Vérifier si la section a des promotions dans l'année courante
        $promotions = $universite->getPromotionsBySection($section['idsection']);
        foreach ($promotions as $promotion) {
            if ($promotion['annee_acad_idannee_acad'] == $currentYear['idannee_acad']) {
                return true;
            }
        }
        return false;
    });
}
?>
<!-- Espace de travail -->
<main id="main" class="main">
    <div class="pagetitle">
        <h1>ENSEIGNANTS</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="#">Accueil</a></li>
                <li class="breadcrumb-item active">Enseignants</li>
            </ol>
        </nav>
    </div><!-- End Page Title -->

    <!-- Section Dashboard -->
    <section class="section dashboard">
        <div class="row">
            <!-- Tableau de données -->
            <div class="col-lg-12">
                <div class="row">
                    <!-- Table teachers -->
                    <div class="col-12">
                        <div class="card overflow-auto">
                            <div class="card-body">
                                <h5 class="card-title">
                                    Gestion des enseignants
                                    <span>
                                        | <a href="grh/agent.add" class="btnPage">
                                            <i class="bi bi-plus-circle-fill"></i> Ajouter un agent
                                        </a>
                                    </span>
                                </h5>

                                <form method="GET" action="" class="mb-3">
                                    <div class="input-group">
                                        <input type="hidden" name="view" value="cours/enseignant">
                                        <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" class="form-control" placeholder="Rechercher par nom...">
                                        <button type="submit" class="btn btn-primary">Rechercher</button>
                                    </div>
                                </form>

                                <table class="table table-striped table-bordered">
                                    <thead>
                                        <tr>
                                            <th scope="col">#</th>
                                            <th scope="col">Nom</th>
                                            <th scope="col">Grade</th>
                                            <th scope="col">Service</th>
                                            <th scope="col">Sections</th>
                                            <th scope="col">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                        // Récupérer tous les agents de type "Enseignant"
                                        $listeEnseignants = $agentModel->getAgentsByType('Enseignant', $search);
                                        $i = 1;
                                        foreach ($listeEnseignants as $enseignant){
                                            // Récupérer les sections de l'enseignant (déjà filtrées par année courante via getAgentSections)
                                            $sectionsEnseignant = $universite->getAgentSections($enseignant['idAgent']);
                                            $sectionsList = '';

                                            foreach ($sectionsEnseignant as $section) {
                                                $principalBadge = $section['estPrincipal'] ? '<span class="badge bg-success">Principal</span>' : '';
                                                $sectionsList .= '<span class="badge bg-info me-1">' . htmlspecialchars($section['designationSection']) . ' ' . $principalBadge . '</span>';
                                            }
                                            
                                            echo "
                                            <tr>
                                                <td>{$i}</td>
                                                <td>{$enseignant['noms']}</td>
                                                <td>" . ($enseignant['gradeDesignation'] ?? 'Non défini') . "</td>
                                                <td>" . ($enseignant['serviceDesignation'] ?? $enseignant['designationStructure']) . "</td>
                                                <td>{$sectionsList}</td>
                                                <td>
                                                    <button class='btn btn-sm btn-info' onclick='manageAgentSections({$enseignant['idAgent']}, \"{$enseignant['noms']}\")'>
                                                        <i class='bi bi-diagram-3'></i> Sections
                                                    </button>
                                                </td>
                                            </tr>";
                                            $i++;
                                        }
                                        ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div><!-- End Table -->
                </div>
            </div>
        </div>
    </section>

</main><!-- End #main -->

<!-- Modal pour gérer les sections d'un enseignant -->
<div class="modal fade" id="manageSectionsModal" tabindex="-1" role="dialog" aria-labelledby="manageSectionsModalLabel" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Gérer les sections de l'enseignant: <span id="agentNameDisplay"></span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="addSectionForm" method="POST" action="controller/add_agent_section.php" class="needs-validation mb-4" novalidate>
                    <input type="hidden" id="agentIdForSection" name="idAgent">
                    <div class="row mb-3">
                        <div class="col-md-8">
                            <label for="idsection" class="form-label">Ajouter une section</label>
                            <select name="idsection" id="idsection" class="form-control" required>
                                <option value="">Sélectionner une section</option>
                                <?php foreach ($sections as $section): ?>
                                    <option value="<?= $section['idsection'] ?>"><?= $section['designationSection'] ?></option>
                                <?php endforeach; ?>
                            </select>
                            <div class="invalid-feedback">Veuillez sélectionner une section.</div>
                        </div>
                        <div class="col-md-4">
                            <label for="estPrincipal" class="form-label">Section principale</label>
                            <div class="form-check form-switch mt-2">
                                <input class="form-check-input" type="checkbox" id="estPrincipal" name="estPrincipal" value="1">
                                <label class="form-check-label" for="estPrincipal">Définir comme principale</label>
                            </div>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-plus-circle"></i> Ajouter la section
                    </button>
                </form>

                <h6 class="mt-4 mb-3">Sections actuelles</h6>
                <div id="agentSectionsList" class="table-responsive">
                    <table class="table table-striped table-bordered">
                        <thead>
                            <tr>
                                <th>Section</th>
                                <th>Date d'affectation</th>
                                <th>Statut</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="sectionTableBody">
                            <!-- Les sections seront chargées dynamiquement ici -->
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fermer</button>
            </div>
        </div>
    </div>
</div>

<script>
    function manageAgentSections(agentId, agentName) {
        document.getElementById('agentIdForSection').value = agentId;
        document.getElementById('agentNameDisplay').textContent = agentName;
        
        // Charger les sections actuelles de l'agent
        loadAgentSections(agentId);
        
        new bootstrap.Modal(document.getElementById('manageSectionsModal')).show();
    }
    
    function loadAgentSections(agentId) {
        fetch(`controller/get_agent_sections.php?idAgent=${agentId}`)
            .then(response => response.json())
            .then(data => {
                const sectionTableBody = document.getElementById('sectionTableBody');
                sectionTableBody.innerHTML = '';
                
                if (data.length === 0) {
                    sectionTableBody.innerHTML = '<tr><td colspan="4" class="text-center">Aucune section assignée</td></tr>';
                    return;
                }
                
                data.forEach(section => {
                    const dateAffectation = new Date(section.dateAffectation).toLocaleDateString('fr-FR');
                    const principalBadge = section.estPrincipal == 1 
                        ? '<span class="badge bg-success">Principal</span>' 
                        : '<span class="badge bg-secondary">Secondaire</span>';
                    
                    const setPrincipalBtn = section.estPrincipal != 1 
                        ? `<button class="btn btn-sm btn-success" onclick="setAsPrincipal(${section.idagent_section}, ${agentId})">
                             <i class="bi bi-star-fill"></i>
                           </button>` 
                        : '';
                    
                    sectionTableBody.innerHTML += `
                        <tr>
                            <td>${section.designationSection}</td>
                            <td>${dateAffectation}</td>
                            <td>${principalBadge}</td>
                            <td>
                                ${setPrincipalBtn}
                                <button class="btn btn-sm btn-danger" onclick="removeSection(${section.idagent_section}, ${agentId})">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </td>
                        </tr>
                    `;
                });
            })
            .catch(error => {
                console.error('Erreur lors du chargement des sections:', error);
                Swal.fire({
                    icon: 'error',
                    title: 'Erreur',
                    text: 'Impossible de charger les sections de l\'enseignant'
                });
            });
    }
    
    function setAsPrincipal(sectionId, agentId) {
        Swal.fire({
            title: 'Confirmation',
            text: "Définir cette section comme principale ? Cela remplacera la section principale actuelle.",
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#3085d6',
            cancelButtonColor: '#d33',
            confirmButtonText: 'Oui, définir comme principale',
            cancelButtonText: 'Annuler'
        }).then((result) => {
            if (result.isConfirmed) {
                fetch('controller/set_principal_section.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `idagent_section=${sectionId}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        Swal.fire({
                            icon: 'success',
                            title: 'Succès',
                            text: 'Section définie comme principale avec succès'
                        });
                        loadAgentSections(agentId);
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Erreur',
                            text: data.message || 'Une erreur est survenue'
                        });
                    }
                })
                .catch(error => {
                    console.error('Erreur:', error);
                    Swal.fire({
                        icon: 'error',
                        title: 'Erreur',
                        text: 'Une erreur est survenue lors de la mise à jour'
                    });
                });
            }
        });
    }
    
    function removeSection(sectionId, agentId) {
        Swal.fire({
            title: 'Êtes-vous sûr ?',
            text: "Voulez-vous vraiment supprimer cette affectation ?",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#3085d6',
            cancelButtonColor: '#d33',
            confirmButtonText: 'Oui, supprimer',
            cancelButtonText: 'Annuler'
        }).then((result) => {
            if (result.isConfirmed) {
                fetch('controller/delete_agent_section.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `idagent_section=${sectionId}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        Swal.fire({
                            icon: 'success',
                            title: 'Succès',
                            text: 'Affectation supprimée avec succès'
                        });
                        loadAgentSections(agentId);
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Erreur',
                            text: data.message || 'Une erreur est survenue'
                        });
                    }
                })
                .catch(error => {
                    console.error('Erreur:', error);
                    Swal.fire({
                        icon: 'error',
                        title: 'Erreur',
                        text: 'Une erreur est survenue lors de la suppression'
                    });
                });
            }
        });
    }
</script>

<?php include "./views/include/footer.php"; ?>

