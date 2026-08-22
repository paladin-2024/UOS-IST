<?php
include "./views/include/header.php";

$structureModel = new Structure();
$search = isset($_GET['search']) ? $_GET['search'] : '';
$structures = $structureModel->getStructures();

?>

<main id="main" class="main">

    <div class="pagetitle">
        <h1>Liste des Factures</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="#">Accueil</a></li>
                <li class="breadcrumb-item active">Factures</li>
            </ol>
        </nav>
    </div><!-- End Page Title -->

    <section class="section dashboard">
        <div class="row">
            <div class="col-lg-12">
                <div class="card overflow-auto">
                    <div class="card-body">
                        <h5 class="card-title">Gestion des Factures</h5>

                        <!-- Search Form -->
                        <form method="GET" action="" class="mb-3">
                            <div class="input-group">
                                <input type="hidden" name="view" value="comptabilite/facture_cl.edit">
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
                                    <th>Client</th>
                                    <th>Structure</th>
                                    <th>État</th> <!-- New column for status -->
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $userId = $_SESSION['id'];
                                $hasResults = false;

                                foreach ($structures as $structure) {
                                    $ver1 = $structureModel->getUserPermissionStructure($userId, $structure['idStructure']);
                                    if ($ver1->fetch()) {
                                        $invoices = $structureModel->getInvoicesByUserAccess($userId, $structure['idStructure'], $search);
                                        foreach ($invoices as $invoice) {
                                            $dateF=date('d/m/Y',strtotime($invoice['dateFacture']));
                                            $hasResults = true;
                                            echo "
                                                <tr>
                                                    <td>{$invoice['numeroFacture']}</td>
                                                    <td>{$dateF}</td>
                                                    <td>{$invoice['montant']}</td>
                                                    <td>{$invoice['clientName']}</td>
                                                    <td>{$invoice['structureName']}</td>
                                                    <td>{$invoice['statut']}</td> <!-- Display the status -->
                                                    <td>
                                                        <button type='button' class='btn btn-warning btn-sm' data-bs-toggle='modal' data-bs-target='#editInvoiceModal'
                                                            data-invoice-id='{$invoice['idInvoice']}'
                                                            data-numero-facture='{$invoice['numeroFacture']}'
                                                            data-date-facture='{$invoice['dateFacture']}'
                                                            data-montant='{$invoice['montant']}'
                                                            data-client-id='{$invoice['Client_idClient']}'
                                                            data-structure-id='{$invoice['Structure_idStructure']}'
                                                            data-statut='{$invoice['statut']}' 
                                                            data-motif='{$invoice['motif']}'
                                                            >
                                                            Modifier
                                                        </button>
                                                        <form action='controller/delete_invoice.php' method='POST' class='delete-invoice-form' style='display:inline;'>
                                                            <input type='hidden' name='idInvoice' value='{$invoice['idInvoice']}'>
                                                            <button type='button' class='btn btn-danger btn-sm delete-invoice-btn'>Supprimer</button>
                                                        </form>
                                                    </td>
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

                        <!-- Modal for editing an invoice -->
    <div class="modal fade" id="editInvoiceModal" tabindex="-1" aria-labelledby="editInvoiceModalLabel" aria-hidden="true" data-bs-backdrop="static">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editInvoiceModalLabel">Modifier une facture</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="editInvoiceForm" action="controller/update_invoice.php" method="POST">
                        <input type="hidden" name="idInvoice" id="editIdInvoice">
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="editNumeroFacture" class="form-label">Numéro de Facture <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="editNumeroFacture" name="numeroFacture" required>
                            </div>
                            <div class="col-md-6">
                                <label for="editDateFacture" class="form-label">Date <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" id="editDateFacture" name="dateFacture" required>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="editMontant" class="form-label">Montant <span class="text-danger">*</span></label>
                                <input type="number" step="0.01" class="form-control" id="editMontant" name="montant" required>
                            </div>
                            <div class="col-md-6">
                                <label for="editMotif" class="form-label">Motif</label>
                                <input type="text" class="form-control" id="editMotif" name="motif">
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-save"></i> Enregistrer
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

                        <script>
                            document.addEventListener('DOMContentLoaded', function () {
                                document.querySelectorAll('.delete-invoice-btn').forEach(button => {
                                    button.addEventListener('click', function () {
                                        const form = this.closest('.delete-invoice-form');
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

                                document.querySelectorAll('[data-bs-target="#editInvoiceModal"]').forEach(button => {
                                    button.addEventListener('click', function () {
                                        const invoiceId = this.getAttribute('data-invoice-id');
                                        const numeroFacture = this.getAttribute('data-numero-facture');
                                        const dateFacture = this.getAttribute('data-date-facture');
                                        const montant = this.getAttribute('data-montant');
                                        const clientId = this.getAttribute('data-client-id');
                                        const structureId = this.getAttribute('data-structure-id');
                                        const motif = this.getAttribute('data-motif');

                                        document.getElementById('editIdInvoice').value = invoiceId;
                                        document.getElementById('editNumeroFacture').value = numeroFacture;
                                        document.getElementById('editDateFacture').value = dateFacture;
                                        document.getElementById('editMontant').value = montant;
                                        document.getElementById('editClientId').value = clientId;
                                        document.getElementById('editStructureId').value = structureId;
                                        document.getElementById('editMotif').value = motif;
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