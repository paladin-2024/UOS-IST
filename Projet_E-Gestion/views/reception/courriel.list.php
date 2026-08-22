<?php
include "./views/include/header.php";

$structureModel = new Structure();
$userId = $_SESSION['id']; // Assuming the user ID is stored in the session

$searchName = isset($_GET['searchProvenance']) ? $_GET['searchProvenance'] : '';
$startDate = isset($_GET['startDate']) ? $_GET['startDate'] : '';
$endDate = isset($_GET['endDate']) ? $_GET['endDate'] : '';

// Fetch emails the user has access to
$emails = $structureModel->getEmailsByUserAccess($userId,$searchName,$startDate,$endDate);

?>

<main id="main" class="main">

    <div class="pagetitle">
        <h1>Gestion des Courriels Entrants</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="#">Accueil</a></li>
                <li class="breadcrumb-item active">Courriels Entrants</li>
            </ol>
        </nav>
    </div><!-- End Page Title -->

    <section class="section dashboard">
        <div class="row">
            <div class="col-lg-12">
                <div class="card overflow-auto">
                    <div class="card-body">
                        <h5 class="card-title">Liste des Courriels Entrants</h5>

                        <!-- Search Form -->
                        <form method="GET" action="" class="mb-3">
                            <div class="input-group">
                                <input type="text" name="searchProvenance" class="form-control" placeholder="Provenance">
                                <input type="date" name="startDate" class="form-control" placeholder="Date de début">
                                <input type="date" name="endDate" class="form-control" placeholder="Date de fin">
                                <button type="submit" class="btn btn-primary">Rechercher</button>
                            </div>
                        </form>

                        <table class="table table-striped table-bordered" id="emailTable">
                            <thead>
                                <tr>
                                    <th>Date d'Arrivée</th>
                                    <th>Provenance</th>
                                    <th>Dépositaire</th>
                                    <th>Objet</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $hasResults = false;
                                foreach ($emails as $email) {
                                    $dateArrive = date('d/m/Y', strtotime($email['dateArrive']));
                                    $dateEnregistrement = date('d/m/Y H:i', strtotime($email['dateEnregistrement']));
                                    $hasResults = true;
                                    echo "
                                        <tr>
                                            <td>{$dateEnregistrement}</td>
                                            <td>{$email['provenance']}</td>
                                            <td>{$email['depositaire']}</td>
                                            <td>{$email['objet']}</td>
                                            
                                        </tr>
                                    ";
                                }

                                if (!$hasResults) {
                                    echo "<tr><td colspan='5' class='text-center'>Aucun résultat trouvé</td></tr>";
                                }
                                ?>
                            </tbody>
                        </table>

                        <script>
                            function viewEmailDetails(emailId) {
                                // Implement the logic to view email details
                                alert('Viewing details for email ID: ' + emailId);
                            }
                        </script>

                    </div>
                </div>
            </div>
        </div>
    </section>

</main><!-- End #main -->

<?php include "./views/include/footer.php"; ?>