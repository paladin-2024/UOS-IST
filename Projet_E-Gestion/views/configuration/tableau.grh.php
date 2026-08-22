<?php
include "./views/include/header.php";
$agent = new Agent();
$structure = new Structure();
$service = new Service();
$grade = new Grade();
$universite = new Universite();

// Récupérer les paramètres de filtrage
$search = isset($_GET['search']) ? $_GET['search'] : '';
$typeAgent = isset($_GET['typeAgent']) ? $_GET['typeAgent'] : '';
$gradeId = isset($_GET['gradeId']) ? (int)$_GET['gradeId'] : 0;
$structureId = isset($_GET['structureId']) ? (int)$_GET['structureId'] : 0;
$serviceId = isset($_GET['serviceId']) ? (int)$_GET['serviceId'] : 0;
?>

<!-- Espace de travail -->
<main id="main" class="main">

    <div class="pagetitle">
        <h1>TABLEAU DE BORD DES AGENTS</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="#">Accueil</a></li>
                <li class="breadcrumb-item active">Tableau de bord</li>
            </ol>
        </nav>
    </div><!-- End Page Title -->

    <!-- Section Dashboard -->
    <section class="section dashboard">
        <!-- Cartes résumé -->
        <div class="row">
            <!-- Modify the Total Agents card -->
            <div class="col-xxl-3 col-md-6">
                <div class="card info-card sales-card">
                    <div class="card-body">
                        <h5 class="card-title">Total Agents</h5>
                        <div class="d-flex align-items-center">
                            <div class="card-icon rounded-circle d-flex align-items-center justify-content-center">
                                <i class="bi bi-people"></i>
                            </div>
                            <div class="ps-3">
                                <h6><?php echo $agent->getAgentCount(); ?></h6>
                                <a href="?view=grh/agent.list" class="small text-muted">Voir tous les agents</a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Modify the Enseignants card -->
            <div class="col-xxl-3 col-md-6">
                <div class="card info-card revenue-card">
                    <div class="card-body">
                        <h5 class="card-title">Enseignants</h5>
                        <div class="d-flex align-items-center">
                            <div class="card-icon rounded-circle d-flex align-items-center justify-content-center">
                                <i class="bi bi-mortarboard"></i>
                            </div>
                            <div class="ps-3">
                                <h6><?php echo $agent->getAgentCountByType('Enseignant'); ?></h6>
                                <a href="?view=grh/agent.list&typeAgent=Enseignant" class="small text-muted">Voir les enseignants</a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Modify the Administratifs card -->
            <div class="col-xxl-3 col-md-6">
                <div class="card info-card customers-card">
                    <div class="card-body">
                        <h5 class="card-title">Administratifs</h5>
                        <div class="d-flex align-items-center">
                            <div class="card-icon rounded-circle d-flex align-items-center justify-content-center">
                                <i class="bi bi-briefcase"></i>
                            </div>
                            <div class="ps-3">
                                <h6><?php echo $agent->getAgentCountByType('Administratif'); ?></h6>
                                <a href="?view=grh/agent.list&typeAgent=Administratif" class="small text-muted">Voir les administratifs</a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Modify the Recherche card -->
            <div class="col-xxl-3 col-md-6">
                <div class="card info-card revenue-card">
                    <div class="card-body">
                        <h5 class="card-title">Recherche</h5>
                        <div class="d-flex align-items-center">
                            <div class="card-icon rounded-circle d-flex align-items-center justify-content-center">
                                <i class="bi bi-search"></i>
                            </div>
                            <div class="ps-3">
                                <h6><?php echo $agent->getAgentCountByType('Recherche'); ?></h6>
                                <a href="?view=grh/agent.list&typeAgent=Recherche" class="small text-muted">Voir le personnel de recherche</a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <!-- Filtres avancés -->
            <div class="row">
                <div class="col-12">
                    <div class="card">
                        <div class="card-body">
                            <h5 class="card-title">Filtres avancés</h5>

                            <form method="GET" action="" id="filterForm">
                                <input type="hidden" name="view" value="configuration/tableau.grh">

                                <div class="row mb-3">
                                    <div class="col-md-3">
                                        <label for="search" class="form-label">Recherche par nom/matricule</label>
                                        <input type="text" name="search" id="search" class="form-control" value="<?= htmlspecialchars($search) ?>" placeholder="Nom ou matricule...">
                                    </div>

                                    <div class="col-md-3">
                                        <label for="typeAgent" class="form-label">Type d'agent</label>
                                        <select name="typeAgent" id="typeAgent" class="form-select">
                                            <option value="">Tous</option>
                                            <option value="Enseignant" <?= $typeAgent == 'Enseignant' ? 'selected' : '' ?>>Enseignant</option>
                                            <option value="Administratif" <?= $typeAgent == 'Administratif' ? 'selected' : '' ?>>Administratif</option>
                                            <option value="Recherche" <?= $typeAgent == 'Recherche' ? 'selected' : '' ?>>Recherche</option>
                                        </select>
                                    </div>

                                    <div class="col-md-3">
                                        <label for="gradeId" class="form-label">Grade</label>
                                        <select name="gradeId" id="gradeId" class="form-select">
                                            <option value="0">Tous</option>
                                            <?php
                                            $grades = $grade->getGrades();
                                            foreach ($grades as $g) {
                                                $selected = ($gradeId == $g['idgrade']) ? 'selected' : '';
                                                echo "<option value='{$g['idgrade']}' {$selected}>{$g['designation']} ({$g['type_agent']})</option>";
                                            }
                                            ?>
                                        </select>
                                    </div>

                                    <div class="col-md-3">
                                        <label for="structureId" class="form-label">Campus</label>
                                        <select name="structureId" id="structureId" class="form-select" onchange="updateServiceFilterOptions()">
                                            <option value="0">Tous</option>
                                            <?php
                                            $structures = $structure->getStructures();
                                            foreach ($structures as $s) {
                                                $selected = ($structureId == $s['idStructure']) ? 'selected' : '';
                                                echo "<option value='{$s['idStructure']}' {$selected}>{$s['designation']}</option>";
                                            }
                                            ?>
                                        </select>
                                    </div>
                                </div>

                                <div class="row mb-3">
                                    <div class="col-md-3">
                                        <label for="serviceId" class="form-label">Service</label>
                                        <select name="serviceId" id="serviceId" class="form-select">
                                            <option value="0">Tous</option>
                                            <!-- Options chargées dynamiquement -->
                                        </select>
                                    </div>

                                    <div class="col-md-3 d-flex align-items-end">
                                        <button type="submit" class="btn btn-primary me-2">
                                            <i class="bi bi-filter"></i> Filtrer
                                        </button>
                                        <button type="button" class="btn btn-secondary" onclick="resetFilters()">
                                            <i class="bi bi-x-circle"></i> Réinitialiser
                                        </button>
                                    </div>

                                    <div class="col-md-6 d-flex align-items-end justify-content-end">
                                        <button type="button" class="btn btn-success me-2" onclick="exportToExcel()">
                                            <i class="bi bi-file-earmark-excel"></i> Exporter Excel
                                        </button>
                                        <button type="button" class="btn btn-danger" onclick="exportToPDF()">
                                            <i class="bi bi-file-earmark-pdf"></i> Exporter PDF
                                        </button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Statistiques -->
            <div class="row">
                <!-- Répartition par type -->
                <div class="col-lg-6">
                    <div class="card">
                        <div class="card-body">
                            <h5 class="card-title">Répartition par type d'agent</h5>
                            <canvas id="typeAgentChart" style="width:100%;max-height:400px;"></canvas>
                        </div>
                    </div>
                </div>

                <!-- Répartition par structure -->
                <div class="col-lg-6">
                    <div class="card">
                        <div class="card-body">
                            <h5 class="card-title">Répartition par campus</h5>
                            <canvas id="structureChart" style="width:100%;max-height:400px;"></canvas>
                        </div>
                    </div>
                </div>
            </div>


    </section>

</main><!-- End #main -->

<!-- Modal pour visualiser les détails d'un agent -->
<div class="modal fade" id="viewAgentModal" tabindex="-1" role="dialog" aria-labelledby="viewAgentModalLabel" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Détails de l'Agent</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="agentDetailsContainer">
                <div class="text-center">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Chargement...</span>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fermer</button>
            </div>
        </div>
    </div>
</div>

<script>
    // Charger les graphiques lors du chargement de la page
    document.addEventListener('DOMContentLoaded', function() {
        // Mise à jour initiale des services
        if (document.getElementById('structureId').value != '0') {
            updateServiceFilterOptions();
        }

        // Charger les graphiques
        loadAgentTypeChart();
        loadStructureChart();
    });

    // Fonction pour mettre à jour les options de service en fonction de la structure sélectionnée
    function updateServiceFilterOptions() {
        const structureSelect = document.getElementById('structureId');
        const serviceSelect = document.getElementById('serviceId');
        const structureId = structureSelect.value;

        // Conserver la valeur actuelle du service
        const currentServiceId = serviceSelect.value;

        // Vider le select des services sauf la première option
        serviceSelect.innerHTML = '<option value="0">Tous</option>';

        if (structureId && structureId != '0') {
            // Appel AJAX pour récupérer les services de la structure
            fetch('controller/get_services.php?idStructure=' + structureId)
                .then(response => response.json())
                .then(data => {
                    data.forEach(service => {
                        const option = document.createElement('option');
                        option.value = service.idService;
                        option.textContent = service.designationService;
                        if (service.idService == currentServiceId) {
                            option.selected = true;
                        }
                        serviceSelect.appendChild(option);
                    });
                })
                .catch(error => console.error('Erreur:', error));
        }
    }

    // Fonction pour réinitialiser les filtres
    function resetFilters() {
        document.getElementById('search').value = '';
        document.getElementById('typeAgent').value = '';
        document.getElementById('gradeId').value = '0';
        document.getElementById('structureId').value = '0';
        document.getElementById('serviceId').innerHTML = '<option value="0">Tous</option>';
        document.getElementById('filterForm').submit();
    }

    // Fonction pour voir les détails d'un agent
    function viewAgentDetails(agentId) {
        const detailsContainer = document.getElementById('agentDetailsContainer');
        detailsContainer.innerHTML = `
            <div class="text-center">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Chargement...</span>
                </div>
            </div>
        `;

        // Ouvrir la modal
        const modal = new bootstrap.Modal(document.getElementById('viewAgentModal'));
        modal.show();

        // Charger les détails via AJAX
        fetch('controller/get_agent_details.php?idAgent=' + agentId)
            .then(response => response.json())
            .then(data => {
                if (data.error) {
                    detailsContainer.innerHTML = `
                        <div class="alert alert-danger">
                            <i class="bi bi-exclamation-triangle"></i> ${data.error}
                        </div>
                    `;
                    return;
                }

                // Afficher les détails
                detailsContainer.innerHTML = `
                    <div class="row">
                        <div class="col-md-4 text-center mb-3">
                            <img src="${data.photo ? 'assets/img/profile/' + data.photo : 'assets/img/profile/no-image.jpg'}" 
                                class="rounded-circle img-fluid" style="max-width: 150px;">
                        </div>
                        <div class="col-md-8">
                            <h4>${data.noms}</h4>
                            <p class="text-muted mb-2">${data.type_agent ? data.type_agent : '-'} | ${data.grade ? data.grade : '-'}</p>
                            <p class="mb-1"><strong>Matricule:</strong> ${data.matricule ? data.matricule : '-'}</p>
                            <p class="mb-1"><strong>Structure:</strong> ${data.structure}</p>
                            <p class="mb-1"><strong>Service:</strong> ${data.service ? data.service : '-'}</p>
                        </div>
                    </div>
                    <div class="row mt-3">
                        <div class="col-md-6">
                            <p class="mb-1"><strong>Téléphone:</strong> ${data.telephone}</p>
                            <p class="mb-1"><strong>Email:</strong> ${data.email}</p>
                            <p class="mb-1"><strong>Sexe:</strong> ${data.sexe === 'M' ? 'Masculin' : 'Féminin'}</p>
                            <p class="mb-1"><strong>État civil:</strong> ${data.etatCivil}</p>
                        </div>
                        <div class="col-md-6">
                            <p class="mb-1"><strong>Date de naissance:</strong> ${data.dateNaissance}</p>
                            <p class="mb-1"><strong>Lieu de naissance:</strong> ${data.lieuNaissance}</p>
                            <p class="mb-1"><strong>Niveau d'études:</strong> ${data.niveauEtude}</p>
                            <p class="mb-1"><strong>Code agent:</strong> ${data.codeAgent ? data.codeAgent : '-'}</p>
                        </div>
                    </div>
                `;
            })
            .catch(error => {
                console.error('Erreur:', error);
                detailsContainer.innerHTML = `
                    <div class="alert alert-danger">
                        <i class="bi bi-exclamation-triangle"></i> Une erreur s'est produite lors du chargement des détails de l'agent.
                    </div>
                `;
            });
    }

    // Fonction pour exporter vers Excel
    function exportToExcel() {
        // Récupérer les paramètres de filtrage actuels
        const search = document.getElementById('search').value;
        const typeAgent = document.getElementById('typeAgent').value;
        const gradeId = document.getElementById('gradeId').value;
        const structureId = document.getElementById('structureId').value;
        const serviceId = document.getElementById('serviceId').value;

        // Construire l'URL avec paramètres
        let url = 'controller/export_agents_excel.php?';
        if (search) url += `search=${encodeURIComponent(search)}&`;
        if (typeAgent) url += `typeAgent=${encodeURIComponent(typeAgent)}&`;
        if (gradeId != '0') url += `gradeId=${encodeURIComponent(gradeId)}&`;
        if (structureId != '0') url += `structureId=${encodeURIComponent(structureId)}&`;
        if (serviceId != '0') url += `serviceId=${encodeURIComponent(serviceId)}`;

        // Rediriger vers le script d'exportation
        window.location.href = url;
    }

    // Fonction pour exporter vers PDF
    function exportToPDF() {
        // Récupérer les paramètres de filtrage actuels
        const search = document.getElementById('search').value;
        const typeAgent = document.getElementById('typeAgent').value;
        const gradeId = document.getElementById('gradeId').value;
        const structureId = document.getElementById('structureId').value;
        const serviceId = document.getElementById('serviceId').value;

        // Construire l'URL avec paramètres
        let url = 'controller/export_agents_pdf.php?';
        if (search) url += `search=${encodeURIComponent(search)}&`;
        if (typeAgent) url += `typeAgent=${encodeURIComponent(typeAgent)}&`;
        if (gradeId != '0') url += `gradeId=${encodeURIComponent(gradeId)}&`;
        if (structureId != '0') url += `structureId=${encodeURIComponent(structureId)}&`;
        if (serviceId != '0') url += `serviceId=${encodeURIComponent(serviceId)}`;

        // Rediriger vers le script d'exportation
        window.location.href = url;
    }

    // Fonction pour charger le graphique de répartition par type d'agent
    function loadAgentTypeChart() {
        fetch('controller/get_agent_stats.php?stat=type')
            .then(response => response.json())
            .then(data => {
                const ctx = document.getElementById('typeAgentChart').getContext('2d');
                new Chart(ctx, {
                    type: 'pie',
                    data: {
                        labels: data.map(item => item.type),
                        datasets: [{
                            data: data.map(item => item.count),
                            backgroundColor: [
                                'rgba(54, 162, 235, 0.7)',
                                'rgba(255, 99, 132, 0.7)',
                                'rgba(75, 192, 192, 0.7)',
                                'rgba(255, 159, 64, 0.7)'
                            ],
                            borderWidth: 1
                        }]
                    },
                    options: {
                        responsive: true,
                        plugins: {
                            legend: {
                                position: 'bottom'
                            },
                            tooltip: {
                                callbacks: {
                                    label: function(context) {
                                        let label = context.label || '';
                                        if (label) {
                                            label += ': ';
                                        }
                                        label += context.raw;
                                        return label;
                                    }
                                }
                            }
                        }
                    }
                });
            })
            .catch(error => console.error('Erreur:', error));
    }

    // Fonction pour charger le graphique de répartition par structure
    function loadStructureChart() {
        fetch('controller/get_agent_stats.php?stat=structure')
            .then(response => response.json())
            .then(data => {
                const ctx = document.getElementById('structureChart').getContext('2d');
                new Chart(ctx, {
                    type: 'bar',
                    data: {
                        labels: data.map(item => item.structure),
                        datasets: [{
                            label: 'Nombre d\'agents',
                            data: data.map(item => item.count),
                            backgroundColor: 'rgba(75, 192, 192, 0.7)',
                            borderColor: 'rgba(75, 192, 192, 1)',
                            borderWidth: 1
                        }]
                    },
                    options: {
                        responsive: true,
                        scales: {
                            y: {
                                beginAtZero: true,
                                ticks: {
                                    precision: 0
                                }
                            }
                        },
                        plugins: {
                            legend: {
                                position: 'bottom'
                            }
                        }
                    }
                });
            })
            .catch(error => console.error('Erreur:', error));
    }
</script>

<?php include "./views/include/footer.php"; ?>