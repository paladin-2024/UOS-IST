<?php
include "./views/include/header.php";
$service = new Service();
$structure = new Structure();
?>

<!-- Espace de travail -->
<main id="main" class="main">

    <div class="pagetitle">
        <h1>SERVICES</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="#">Accueil</a></li>
                <li class="breadcrumb-item active">Services</li>
            </ol>
        </nav>
    </div><!-- End Page Title -->

    <!-- Section Dashboard -->
    <section class="section dashboard">
        <div class="row">
            <!-- Tableau de données -->
            <div class="col-lg-12">
                <div class="row">
                    <!-- Table services -->
                    <div class="col-12">
                        <div class="card overflow-auto">
                            <div class="card-body">
                                <h5 class="card-title">
                                    Gestion des services
                                </h5>

                                <div class="row mb-3">
                                    <div class="col-md-12">
                                        <input type="text" id="searchInput" class="form-control" placeholder="Rechercher...">
                                    </div>
                                </div>

                                <table class="table table-striped table-bordered">
                                    <thead>
                                        <tr>
                                            <th scope="col">#</th>
                                            <th scope="col">Désignation</th>
                                            <th scope="col">Responsable</th>
                                            <th scope="col">Structure</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                        $listeService = $service->getService();
                                        $i = 1;

                                        foreach ($listeService as $l) {
                                            $ver1 = $structure->getUserPermissionStructure($_SESSION['id'], $l['idStructure']);
                                            if ($ver1->fetch()) {
                                                    echo "
                                                    <tr>
                                                        <td>{$i}</td>
                                                        <td>{$l['designationService']}</td>
                                                        <td>{$l['responsable']}</td>
                                                        <td>{$l['designationStructure']}</td>
                                                        
                                                    </tr>";
                                                    $i++;
                                            }
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





<?php include "./views/include/footer.php"; ?>