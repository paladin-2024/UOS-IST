<?php
include "./views/include/header.php";

$agentModel = new Agent();
$agents = $agentModel->getAgents(); // Retrieve all agents

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
                        <h5 class="card-title">Gestion des Agents</h5>

                        <table class="table table-striped table-bordered">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Nom</th>
                                    <th>Lieu de Naissance</th>
                                    <th>Date de Naissance</th>
                                    <th>Sexe</th>
                                    <th>État Civil</th>
                                    <th>Niveau d'Étude</th>
                                    <th>Structure</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php 
                                foreach ($agents as $agent) {
                                    echo "
                                        <tr>
                                            <td>{$agent['idAgent']}</td>
                                            <td>{$agent['noms']}</td>
                                            <td>{$agent['lieuNaissance']}</td>
                                            <td>{$agent['dateNaissance']}</td>
                                            <td>{$agent['sexe']}</td>
                                            <td>{$agent['etatCivil']}</td>
                                            <td>{$agent['niveauEtude']}</td>
                                            <td>{$agent['designationStructure']}</td>
                                            <td>
                                                <a href='agent.famille.add.php?agent_id={$agent['idAgent']}' class='btn btn-primary btn-sm'>
                                                    <i class='bi bi-plus-circle-fill'></i> Ajouter un membre de la famille
                                                </a>
                                            </td>
                                        </tr>
                                    ";
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