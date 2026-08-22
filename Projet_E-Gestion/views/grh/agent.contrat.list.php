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

                            foreach ($agents as $agent) {
                                $ver1 = $structure->getUserPermissionStructure($userId, $agent['idStructure']);
                                if ($ver1->fetch()) {
                                    $hasResults = true;
                                    $services = $serviceModel->getService($agent['idStructure']); // Get services for the agent's structure
                                    echo "
                                        <tr>
                                            <td>{$agent['idAgent']}</td>
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
                                                
                                            </tr>
                                        ";
                                    }

                                    echo "
                                                    </tbody>
                                                </table>
                                            </td>
                                        </tr>
                                    ";
                                }
                            }

                            if (!$hasResults) {
                                echo "<tr><td colspan='10' class='text-center'>Aucun résultat trouvé</td></tr>";
                            }
                        ?>
                        </tbody>
                    </table>


                    </div>
                </div>
            </div>
        </div>
    </section>

</main><!-- End #main -->

<?php include "./views/include/footer.php"; ?>