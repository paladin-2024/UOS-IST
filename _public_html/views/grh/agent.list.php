<?php
include "./views/include/header.php";
$agent = new Agent();
$structure = new Structure();
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

    <section class="section">
        <div class="row">
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Gestion des agents

                            <!-- Ajouter ce bouton dans la barre d'outils en haut de la liste des agents -->
                        <a href="grh/export_agents" class="btn btn-sm btn-outline-primary">
                            <i class="bi bi-file-earmark-pdf"></i> Exporter PDF
                        </a>
                        </h5>
                        

                        <div class="row mb-3">
                            <div class="col-md-12">
                                <input type="text" id="searchInput" class="form-control" placeholder="Rechercher...">
                            </div>
                        </div>

                        <table id="agentsTable" class="table table-striped table-bordered">
                        <thead>
                            <tr>
                                <th scope="col">#</th>
                                <th scope="col">Noms</th>
                                <th scope="col">Lieu de Naissance</th>
                                <th scope="col">Date de Naissance</th>
                                <th scope="col">Sexe</th>
                                <th scope="col">État Civil</th>
                                <th scope="col">Niveau d'Étude</th>
                                <th scope="col">Campus</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $listeAgent = $agent->getAgents();
                            $i = 1;

                            foreach ($listeAgent as $l) {
                                $ver1 = $structure->getUserPermissionStructure($_SESSION['id'], $l['idStructure']);
                                if ($ver1->fetch()) {
                                    echo "
                                    <tr>
                                        <td>{$i}</td>
                                        <td>{$l['noms']}</td>
                                        <td>{$l['lieuNaissance']}</td>
                                        <td>{$l['dateNaissance']}</td>
                                        <td>{$l['sexe']}</td>
                                        <td>{$l['etatCivil']}</td>
                                        <td>{$l['niveauEtude']}</td>
                                        <td>{$l['designationStructure']}</td>
                                    </tr>";
                                    $i++;
                                }
                            }
                            ?>
                        </tbody>
                    </table>

                    <script>
                    $(document).ready(function() {
                        $('#agentsTable').DataTable({
                            dom: 'Bfrtip',
                            buttons: [
                                {
                                    extend: 'excelHtml5',
                                    title: 'Liste des Agents'
                                },
                                {
                                    extend: 'pdfHtml5',
                                    title: 'Liste des Agents'
                                }
                            ]
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