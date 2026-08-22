<?php
include "./views/include/header.php";

$agentModel = new Agent();
$structure = new Structure();
$serviceModel = new Service();
$search = isset($_GET['search']) ? $_GET['search'] : '';
$agents = $agentModel->getAgents($search);

?>

<main id="main" class="main">

    <div class="pagetitle">
        <h1>Liste des Agents</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="#">Accueil</a></li>
                <li class="breadcrumb-item active">Agents</li>
            </ol>
        </nav>
    </div><!-- End Page Title -->

    <section class="section dashboard">
        <div class="row">
            <div class="col-lg-12">
                <div class="card overflow-auto">
                    <div class="card-body">
                        <h5 class="card-title">Gestion des contrats</h5>

                        <!-- Search Form -->
                        <form method="GET" action="" class="mb-3">
                            <div class="input-group">
                                <input type="hidden" name="view" value="grh/agent.contrat.add">
                                <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" class="form-control" placeholder="Rechercher par nom...">
                                <button type="submit" class="btn btn-primary">Rechercher</button>
                            </div>
                        </form>

                        <table class="table table-striped table-bordered">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Nom</th>
                                <th>Lieu de Naissance</th>
                                <th>Date de Naissance</th>
                                <th>Sexe</th>
                                <th>Structure</th>
                                <th>Total</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php 
                            $userId = $_SESSION['id'];
                            $hasResults = false;
                            $i=1;

                            foreach ($agents as $agent) {
                                $ver1 = $structure->getUserPermissionStructure($userId, $agent['idStructure']);
                                if ($ver1->fetch()) {
                                    $hasResults = true;
                                    $services = $serviceModel->getService($agent['idStructure']); // Get services for the agent's structure
                                    echo "
                                        <tr>
                                            <td>{$i}</td>
                                            <td>{$agent['noms']}</td>
                                            <td>{$agent['lieuNaissance']}</td>
                                            <td>{$agent['dateNaissance']}</td>
                                            <td>{$agent['sexe']}</td>
                                            <td>{$agent['designationStructure']}</td>
                                            <td>{$agent['totalContracts']}</td>
                                            <td>
                                                
                                                <button type='button' class='btn btn-secondary btn-sm' data-bs-toggle='collapse' data-bs-target='#contracts{$agent['idAgent']}'>
                                                    <i class='bi bi-eye-fill'></i>
                                                </button>
                                            </td>
                                        </tr>
                                        <tr class='collapse' id='contracts{$agent['idAgent']}'>
                                            <td colspan='10'>
                                                <table class='table table-sm'>
                                                    <thead>
                                                        <tr>
                                                            <th>Désignation</th>
                                                            <th>Type de Contrat</th>
                                                            <th>Date Début</th>
                                                            <th>Date Fin</th>
                                                            <th>Fonction</th>
                                                            <th>Salaire de Base</th>
                                                            <th>Actions</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                    ";

                                    $contracts = $agentModel->getContractsByAgent($agent['idAgent']);
                                    foreach ($contracts as $contract) {
                                        $currentDate = date('Y-m-d');
                                        $contractStatusClass = '';
                                    
                                        if ($contract['dateFin'] && $contract['dateFin'] < $currentDate) {
                                            $contractStatusClass = 'table-danger'; // Red for completed contracts
                                        } else {
                                            $contractStatusClass = 'table-success'; // Green for ongoing contracts
                                        }
                                    
                                        echo "
                                            <tr class='{$contractStatusClass}'>
                                                <td>{$contract['designation']}</td>
                                                <td>{$contract['typeContrat']}</td>
                                                <td>{$contract['dateDebut']}</td>
                                                <td>{$contract['dateFin']}</td>
                                                <td>{$contract['fonction']}</td>
                                                <td>{$contract['salaireDeBase']}</td>
                                                <td>
                                                    <button type='button' class='btn btn-warning btn-sm' 
                                                            data-bs-toggle='modal' 
                                                            data-bs-target='#editContractModal' 
                                                            data-contract-id='{$contract['idContrat_agent']}'
                                                            data-designation='{$contract['designation']}'
                                                            data-type-contrat='{$contract['typeContrat']}'
                                                            data-date-debut='{$contract['dateDebut']}'
                                                            data-date-fin='{$contract['dateFin']}'
                                                            data-fonction='{$contract['fonction']}'
                                                            data-salaire-de-base='{$contract['salaireDeBase']}'
                                                            data-service-id='{$contract['Service_idService']}'
                                                            data-structure-id='{$agent['idStructure']}'>
                                                        Modifier
                                                    </button>
                                                    
                                                </td>
                                            </tr>
                                        ";
                                    }

                                    echo "
                                                    </tbody>
                                                </table>
                                            </td>
                                        </tr>
                                    ";
                                    $i++;
                                }
                            }

                            if (!$hasResults) {
                                echo "<tr><td colspan='10' class='text-center'>Aucun résultat trouvé</td></tr>";
                            }
                        ?>
                        </tbody>
                    </table>

                   
                    <div class="modal fade" id="editContractModal" tabindex="-1" aria-labelledby="editContractModalLabel" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered modal-lg"> <!-- Increased size of the modal -->
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editContractModalLabel">Modifier un contrat</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="editContractForm" action="controller/update_contrat.php" method="POST">
                    <input type="hidden" name="idContratAgent" id="editIdContratAgent">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="editDesignation" class="form-label">Désignation</label>
                            <input type="text" class="form-control" id="editDesignation" name="designation" required>
                        </div>
                        <div class="col-md-6">
                            <label for="editTypeContrat" class="form-label">Type de Contrat</label>
                            <select class="form-select" id="editTypeContrat" name="typeContrat" required>
                                <option value="">Sélectionner un type</option>
                                <option value="CDI">CDI</option>
                                <option value="CDD">CDD</option>
                            </select>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="editDateDebut" class="form-label">Date Début</label>
                            <input type="date" class="form-control" id="editDateDebut" name="dateDebut" required>
                        </div>
                        <div class="col-md-6">
                            <label for="editDateFin" class="form-label">Date Fin</label>
                            <input type="date" class="form-control" id="editDateFin" name="dateFin">
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="editFonction" class="form-label">Fonction</label>
                            <input type="text" class="form-control" id="editFonction" name="fonction" required>
                        </div>
                        <div class="col-md-6">
                            <label for="editSalaireDeBase" class="form-label">Salaire de Base</label>
                            <input type="number" step="0.01" class="form-control" id="editSalaireDeBase" name="salaireDeBase" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="editServiceId" class="form-label">Service</label>
                        <select class="form-select" id="editServiceId" name="serviceId" required>
                            <option value="">Sélectionner un service</option>
                            <?php foreach ($services as $service): ?>
                                <option value="<?= $service['idService'] ?>"><?= $service['designationService'] ?> (<?= $service['designationStructure'] ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="submit" name="action" value="edit" class="btn btn-primary">Enregistrer</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.add-contract-btn').forEach(button => {
        button.addEventListener('click', function () {
            const agentId = this.getAttribute('data-agent-id');
            const structureId = this.getAttribute('data-structure-id');
            document.getElementById('agentId').value = agentId;

            // Get services for the selected structure
            const services = <?= json_encode($serviceModel->getService()) ?>;
            const serviceSelect = document.getElementById('serviceId');
            serviceSelect.innerHTML = '<option value="">Sélectionner un service</option>';

            services.forEach(service => {
                if (service.idStructure == structureId) {
                    const option = document.createElement('option');
                    option.value = service.idService;
                    option.textContent = `${service.designationService} (${service.designationStructure})`;
                    serviceSelect.appendChild(option);
                }
            });
        });
    });

    document.querySelectorAll('.delete-contract-btn').forEach(button => {
        button.addEventListener('click', function () {
            const form = this.closest('.delete-contract-form');
            Swal.fire({
                title: 'Êtes-vous sûr?',
                text: "Cette action est irréversible!",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#d33',
                confirmButtonText: 'Oui, supprimer!',
                cancelButtonText: 'Annuler'
            }).then((result) => {
                if (result.isConfirmed) {
                    form.submit();
                }
            });
        });
    });

    document.querySelectorAll('[data-bs-target="#editContractModal"]').forEach(button => {
            button.addEventListener('click', function () {
                const contractId = this.getAttribute('data-contract-id');
                const designation = this.getAttribute('data-designation');
                const typeContrat = this.getAttribute('data-type-contrat');
                const dateDebut = this.getAttribute('data-date-debut');
                const dateFin = this.getAttribute('data-date-fin');
                const fonction = this.getAttribute('data-fonction');
                const salaireDeBase = this.getAttribute('data-salaire-de-base');
                const serviceId = this.getAttribute('data-service-id');
                const structureId = this.getAttribute('data-structure-id');

                document.getElementById('editIdContratAgent').value = contractId;
                document.getElementById('editDesignation').value = designation;
                document.getElementById('editTypeContrat').value = typeContrat;
                document.getElementById('editDateDebut').value = dateDebut;
                document.getElementById('editDateFin').value = dateFin;
                document.getElementById('editFonction').value = fonction;
                document.getElementById('editSalaireDeBase').value = salaireDeBase;

                // Filter and set the services for the selected structure
                const services = <?= json_encode($serviceModel->getService()) ?>;
                const serviceSelect = document.getElementById('editServiceId');
                serviceSelect.innerHTML = '<option value="">Sélectionner un service</option>';

                services.forEach(service => {
                    if (service.idStructure == structureId) {
                        const option = document.createElement('option');
                        option.value = service.idService;
                        option.textContent = `${service.designationService} (${service.designationStructure})`;
                        serviceSelect.appendChild(option);
                    }
                });

                // Set the selected service
                serviceSelect.value = serviceId;
            });
        });
});
</script>
                    </div>
                </div>
            </div>
        </div>
    </section>

</main><!-- End #main -->

<?php include "./views/include/footer.php"; ?>