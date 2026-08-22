<?php
include "./views/include/header.php";

$structureModel = new Structure();
$searchName = isset($_GET['searchName']) ? $_GET['searchName'] : '';
$startDate = isset($_GET['startDate']) ? $_GET['startDate'] : '';
$endDate = isset($_GET['endDate']) ? $_GET['endDate'] : '';

$userId = $_SESSION['id']; // Assuming the user ID is stored in the session
$structures = $structureModel->getStructures();

?>

<main id="main" class="main">

    <div class="pagetitle">
        <h1>Historique des Entrées au Dépôt</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="#">Accueil</a></li>
                <li class="breadcrumb-item active">Entrées au Dépôt</li>
            </ol>
        </nav>
    </div><!-- End Page Title -->

    <section class="section dashboard">
        <div class="row">
            <div class="col-lg-12">
                <div class="card overflow-auto">
                    <div class="card-body">
                        <h5 class="card-title">Recherche des Entrées au Dépôt</h5>

                        <!-- Search Form -->
                        <form method="GET" action="" class="mb-3">
                            <div class="input-group">
                                <input type="text" name="searchName" value="<?= htmlspecialchars($searchName) ?>" class="form-control" placeholder="Nom du fournisseur">
                                <input type="date" name="startDate" value="<?= htmlspecialchars($startDate) ?>" class="form-control" placeholder="Date de début">
                                <input type="date" name="endDate" value="<?= htmlspecialchars($endDate) ?>" class="form-control" placeholder="Date de fin">
                                <button type="submit" class="btn btn-primary">Rechercher</button>
                            </div>
                        </form>

                        <button onclick="printEntries()" class="btn btn-secondary mb-3"><i class="bi bi-printer"></i> Imprimer</button>

                        <div id="printableArea">
                            <table class="table table-striped table-bordered" id="entreeTable">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Date</th>
                                        <th>Fournisseur</th>
                                        <th>Référence</th>
                                        <th>Transporteur</th>
                                        <th>Dépôt</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $hasResults = false;$i=1;
                                    if ($searchName || ($startDate && $endDate)) {
                                        $entrees = $structureModel->getEntreesByUserAccess2($userId, $searchName, $startDate, $endDate);
                                        foreach ($entrees as $entree) {
                                            $dateF = date('d/m/Y', strtotime($entree['dateOperation']));
                                            $hasResults = true;
                                            echo "
                                                <tr>
                                                    <td>{$i}</td>
                                                    <td>{$dateF}</td>
                                                    <td>{$entree['nom']}</td>
                                                    <td>{$entree['reference_document']}</td>
                                                    <td>{$entree['transporteur']}</td>
                                                    <td>{$entree['designation']}</td>
                                                </tr>
                                                <tr>
                                                    <td colspan='6'>
                                                        <table class='table'>
                                                            <thead>
                                                                <tr>
                                                                    <th>Désignation</th>
                                                                    <th>Unité</th>
                                                                    <th>Quantité</th>
                                                                </tr>
                                                            </thead>
                                                            <tbody>";
                                            
                                            // Fetch details for the current entry
                                            $details = $structureModel->getDetailsEntreeByManifest($entree['idManifeste_entree']);
                                            foreach ($details as $detail) {
                                                echo "
                                                    <tr>
                                                        <td>{$detail['designation']}</td>
                                                        <td>{$detail['unite']}</td>
                                                        <td>{$detail['quantite']}</td>
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
                                        echo "<tr><td colspan='5' class='text-center'>Aucun résultat trouvé</td></tr>";
                                    }
                                    ?>
                                </tbody>
                            </table>
                        </div>

                        <script>
                            function printEntries() {
                                const printContents = document.getElementById('printableArea').innerHTML;
                                const newWindow = window.open('', '', 'width=800,height=600');
                                newWindow.document.write('<html><head><title>Historique des Entrées au Dépôt</title><style>');
                                newWindow.document.write('body { font-family: Arial, sans-serif; margin: 20px; font-size: 12px; }');
                                newWindow.document.write('table { width: 100%; border-collapse: collapse; margin-bottom: 20px; font-size: 12px; }');
                                newWindow.document.write('th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }');
                                newWindow.document.write('th { background-color: #f4f4f4; }');
                                newWindow.document.write('</style></head><body>');
                                newWindow.document.write('<div class="header">');
                                newWindow.document.write('<img src="../uploads/bdom_bukavu_logo.jpg" alt="Logo" style="width: 100px; height: auto; float: left; margin-right: 20px;">');
                                newWindow.document.write('<h2 style="margin: 0;">Historique des Entrées au Dépôt</h2>');
                                newWindow.document.write('<p style="clear: both;"></p>');
                                newWindow.document.write('</div>');
                                newWindow.document.write('<h3>Critères de recherche:</h3>');
                                newWindow.document.write('<p>Fournisseur: <?= htmlspecialchars($searchName) ?>, Date de début: <?= htmlspecialchars(date("d/m/Y", strtotime($startDate))) ?>, Date de fin: <?= htmlspecialchars(date("d/m/Y", strtotime($endDate))) ?></p>');
                                newWindow.document.write(printContents);
                                newWindow.document.write('<p>Imprimé par: <?php echo $_SESSION["nom"]; ?> le <?php echo date("d/m/Y"); ?></p>');
                                newWindow.document.write('<br><button onclick="window.print()">Imprimer</button>');
                                newWindow.document.write('</body></html>');
                                newWindow.document.close();
                            }
                        </script>

                    </div>
                </div>
            </div>
        </div>
    </section>

</main><!-- End #main -->

<?php include "./views/include/footer.php"; ?>