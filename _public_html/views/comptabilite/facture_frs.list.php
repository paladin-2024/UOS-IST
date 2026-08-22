<?php
include "./views/include/header.php";

$structureModel = new Structure();
$search = isset($_GET['search']) ? $_GET['search'] : '';
$structures = $structureModel->getStructures();

?>

<main id="main" class="main">

    <div class="pagetitle">
        <h1>Liste des Factures Fournisseur</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="#">Accueil</a></li>
                <li class="breadcrumb-item active">Factures Fournisseur</li>
            </ol>
        </nav>
    </div><!-- End Page Title -->

    <section class="section dashboard">
        <div class="row">
            <div class="col-lg-12">
                <div class="card overflow-auto">
                    <div class="card-body">
                        <h5 class="card-title">Gestion des Factures Fournisseur</h5>

                        <!-- Search Form -->
                        <form method="GET" action="" class="mb-3">
                            <div class="input-group">
                                <input type="hidden" name="view" value="comptabilite/facture_frs.edit">
                                <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" class="form-control" placeholder="Rechercher une facture...">
                                <button type="submit" class="btn btn-primary">Rechercher</button>
                            </div>
                        </form>

                        <table class="table table-striped table-bordered" id="invoiceTable">
                            <thead>
                                <tr>
                                    <th>Numéro de Facture</th>
                                    <th>Date</th>
                                    <th>Montant</th>
                                    <th>Fournisseur</th>
                                    <th>Structure</th>
                                    <th>État</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $userId = $_SESSION['id'];
                                $hasResults = false;

                                foreach ($structures as $structure) {
                                    $ver1 = $structureModel->getUserPermissionStructure($userId, $structure['idStructure']);
                                    if ($ver1->fetch()) {
                                        $invoices = $structureModel->getSupplierInvoicesByUserAccess($userId, $structure['idStructure'], $search);
                                        foreach ($invoices as $invoice) {
                                            $dateF = date('d/m/Y', strtotime($invoice['dateFacture']));
                                            $hasResults = true;
                                            echo "
                                                <tr>
                                                    <td>{$invoice['numeroFacture']}</td>
                                                    <td>{$dateF}</td>
                                                    <td>{$invoice['montant']}</td>
                                                    <td>{$invoice['fournisseurName']}</td>
                                                    <td>{$invoice['structureName']}</td>
                                                    <td>{$invoice['statut']}</td>
                                                    
                                                </tr>
                                            ";
                                        }
                                    }
                                }

                                if (!$hasResults) {
                                    echo "<tr><td colspan='7' class='text-center'>Aucun résultat trouvé</td></tr>";
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