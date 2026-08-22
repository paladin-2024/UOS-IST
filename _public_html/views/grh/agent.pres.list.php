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
        <h1>Liste des Présences Mensuelles</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="#">Accueil</a></li>
                <li class="breadcrumb-item active">Présences</li>
            </ol>
        </nav>
    </div><!-- End Page Title -->

    <section class="section dashboard">
        <div class="row">
            <div class="col-lg-12">
                <div class="card overflow-auto">
                    <div class="card-body">
                        <h5 class="card-title">Gestion des présences</h5>
                        <!-- Print Button -->
                        <button id="printButton" class="btn btn-success mb-3" data-bs-toggle="modal" data-bs-target="#monthYearModal"><span class="bi bi-printer"></span> Imprimer</button>
                        <!-- Search Form -->
                        <form method="GET" action="" class="mb-3">
                            <div class="input-group">
                                
                                <input type="hidden" name="view" value="grh/presence.list">
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
                                $structureData = $structure->getStructureById($agent['idStructure']);
                                $joursOuvrables = $structureData['joursOuvrables'];

                                if ($ver1->fetch()) {
                                    $hasResults = true;
                                    echo "
                                        <tr>
                                            <td>{$i}</td>
                                            <td>{$agent['noms']}</td>
                                            <td>{$agent['lieuNaissance']}</td>
                                            <td>{$agent['dateNaissance']}</td>
                                            <td>{$agent['sexe']}</td>
                                            <td>{$agent['designationStructure']}</td>
                                            <td>
                                                <button type='button' class='btn btn-secondary btn-sm' data-bs-toggle='collapse' data-bs-target='#presences{$agent['idAgent']}'>
                                                    <i class='bi bi-eye-fill'></i>
                                                </button>
                                            </td>
                                        </tr>
                                        <tr class='collapse' id='presences{$agent['idAgent']}'>
                                            <td colspan='7'>
                                                <table class='table table-sm'>
                                                    <thead>
                                                        <tr>
                                                            <th>Année</th>
                                                            <th>Mois</th>
                                                            <th>Jours de Présence</th>
                                                            <th>Jours d'Absence</th>
                                                            <th>Retards</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                    ";

                                    $presences = $agentModel->getPresenceByAgent($agent['idAgent']);
                                    foreach ($presences as $presence) {
                                        echo "
                                            <tr>
                                                <td>{$presence['annee']}</td>
                                                <td>{$presence['mois']}</td>
                                                <td>{$presence['joursPresence']}</td>
                                                <td>{$presence['joursAbsence']}</td>
                                                <td>{$presence['joursRetard']}</td>
                                                
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
                                $i++;
                            }
                            if (!$hasResults) {
                                echo "<tr><td colspan='7' class='text-center'>Aucun résultat trouvé</td></tr>";
                            }
                        ?>
                        </tbody>
                    </table>

                    <!-- Modal -->
                    <div class="modal fade" id="monthYearModal" tabindex="-1" aria-labelledby="monthYearModalLabel" aria-hidden="true" data-bs-backdrop="static">
                        <div class="modal-dialog">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title" id="monthYearModalLabel">Sélectionnez le mois et l'année</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>
                                <div class="modal-body">
                                    <div class="row mb-3">
                                        <div class="col-md-6">
                                            <label for="annee" class="form-label">Année <span class="text-danger">*</span></label>
                                            <select class="form-select" id="annee" name="annee" required>
                                                <?php
                                                $currentYear = date('Y');
                                                for ($year = $currentYear; $year <= $currentYear + 5; $year++) {
                                                    echo "<option value='$year'>$year</option>";
                                                }
                                                ?>
                                            </select>
                                        </div>
                                        <div class="col-md-6">
                                            <label for="mois" class="form-label">Mois <span class="text-danger">*</span></label>
                                            <select class="form-select" id="mois" name="mois" required>
                                                <?php
                                                $months = [
                                                    '01' => 'Janvier', '02' => 'Fevrier', '03' => 'Mars', '04' => 'Avril',
                                                    '05' => 'Mai', '06' => 'Juin', '07' => 'Juillet', '08' => 'Aout',
                                                    '09' => 'Septembre', '10' => 'Octobre', '11' => 'Novembre', '12' => 'Decembre'
                                                ];
                                                foreach ($months as $num => $name) {
                                                    echo "<option value='$name'>$name</option>";
                                                }
                                                ?>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                                    <button type="button" class="btn btn-primary" id="confirmSelection">Confirmer</button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <script>
                    document.getElementById('confirmSelection').addEventListener('click', function() {
                        const month = document.getElementById('mois').value;
                        const year = document.getElementById('annee').value;
                        const monthYear = month + '/' + year;
                        // Afficher le preloader
                        preloader.style.display = "flex";
                        preloader.style.opacity = "1";
                        window.location.href = `grh/presence.list?mois=${month}&annee=${year}`;
                    });
                    </script>

                </div>
            </div>
        </div>
    </section>

</main><!-- End #main -->

<?php include "./views/include/footer.php"; ?>