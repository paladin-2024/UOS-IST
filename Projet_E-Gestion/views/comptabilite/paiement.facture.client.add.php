<?php
include "./views/include/header.php";

$structureModel = new Structure();
$search = isset($_GET['search']) ? $_GET['search'] : '';
$structures = $structureModel->getStructures();

$userId = $_SESSION['id']; // Assuming the user ID is stored in the session
$banqueModel = new Banque();
$banks = $banqueModel->getBanksByUserAccess($userId);

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
                        <h5 class="card-title">Paiements des Factures Clients</h5>

                        <!-- Search Form -->
                        <form method="GET" action="" class="mb-3">
                            <div class="input-group">
                                <input type="hidden" name="view" value="comptabilite/paiement.facture.client.add">
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
                                    <th>État</th>
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
                                            $dateF = date('d/m/Y', strtotime($invoice['dateFacture']));
                                            $hasResults = true;
                                            echo "
                                                <tr>
                                                    <td>{$invoice['numeroFacture']}</td>
                                                    <td>{$dateF}</td>
                                                    <td>{$invoice['montant']}</td>
                                                    <td>{$invoice['clientName']}</td>
                                                    <td>{$invoice['structureName']}</td>
                                                    <td>{$invoice['statut']}</td>
                                                    <td>
                                                        <button type='button' class='btn btn-info btn-sm toggle-payments' data-invoice-id='{$invoice['idInvoice']}'>
                                                            Voir
                                                        </button>
                                                        <button type='button' class='btn btn-success btn-sm' data-bs-toggle='modal' data-bs-target='#addPaymentModal'
                                                            data-invoice-id='{$invoice['idInvoice']}'>
                                                            Ajouter Paiement
                                                        </button>
                                                    </td>
                                                </tr>
                                                <tr class='payment-details' id='payments-{$invoice['idInvoice']}' style='display:none;'>
                                                    <td colspan='7'>
                                                        <table class='table'>
                                                            <thead>
                                                                <tr>
                                                                    <th>Date</th>
                                                                    <th>Montant</th>
                                                                    <th>Libellé</th>
                                                                    <th>Dépositaire</th>
                                                                    <th>Actions</th>
                                                                </tr>
                                                            </thead>
                                                            <tbody>";
                                            
                                            // Fetch payments for the current invoice
                                            $payments = $structureModel->getPaymentsByInvoiceId($invoice['idInvoice']);
                                            foreach ($payments as $payment) {
                                                echo "
                                                    <tr>
                                                        <td>{$payment['datePaiement']}</td>
                                                        <td>{$payment['montant']}</td>
                                                        <td>{$payment['libelle']}</td>
                                                        <td>{$payment['depositaire']}</td>
                                                        <td>
                                                            <button type='button' class='btn btn-secondary btn-sm print-receipt-btn' data-payment-id='{$payment['idPaiement_client']}'><i class='bi bi-printer'></i> Imprimer</button>
                                                            <button type='button' class='btn btn-secondary btn-sm print-receipt-btn-pos' data-payment-id='{$payment['idPaiement_client']}'><i class='bi bi-printer'></i> POS</button>
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
                                        }
                                    }
                                }

                                if (!$hasResults) {
                                    echo "<tr><td colspan='7' class='text-center'>Aucun résultat trouvé</td></tr>";
                                }
                                ?>
                            </tbody>
                        </table>

                        <!-- Modal for adding a payment -->
    <div class="modal fade" id="addPaymentModal" tabindex="-1" aria-labelledby="addPaymentModalLabel" aria-hidden="true" data-bs-backdrop="static">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="addPaymentModalLabel">Ajouter un paiement</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="addPaymentForm" action="controller/add_payment.php" method="POST">
                        <input type="hidden" name="idInvoice" id="addPaymentInvoiceId">
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="paymentBank" class="form-label">Sélectionner la Banque <span class="text-danger">*</span></label>
                                <select class="form-select" id="paymentBank" name="bankId" required>
                                    <?php foreach ($banks as $bank): ?>
                                        <option value="<?= htmlspecialchars($bank['idBanque']) ?>"><?= htmlspecialchars($bank['designation']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="paymentDate" class="form-label">Date de Paiement <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" id="paymentDate" name="datePaiement" required>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="paymentAmount" class="form-label">Montant (USD) <span class="text-danger">*</span></label>
                                <input type="number" step="0.01" class="form-control" id="paymentAmount" name="montant" required>
                            </div>
                            <div class="col-md-6">
                                <label for="paymentLibelle" class="form-label">Libellé</label>
                                <input type="text" class="form-control" id="paymentLibelle" name="libelle">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="paymentDepositaire" class="form-label">Dépositaire</label>
                            <input type="text" class="form-control" id="paymentDepositaire" name="depositaire">
                        </div>
                        <div class="modal-footer">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-save"></i> Ajouter
                        </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

                        <script>
                            document.addEventListener('DOMContentLoaded', function () {
                                document.querySelectorAll('.toggle-payments').forEach(button => {
                                    button.addEventListener('click', function () {
                                        const invoiceId = this.getAttribute('data-invoice-id');
                                        const paymentRow = document.getElementById('payments-' + invoiceId);
                                        paymentRow.style.display = paymentRow.style.display === 'none' ? '' : 'none';
                                    });
                                });

                                document.querySelectorAll('[data-bs-target="#addPaymentModal"]').forEach(button => {
                                    button.addEventListener('click', function () {
                                        const invoiceId = this.getAttribute('data-invoice-id');
                                        document.getElementById('addPaymentInvoiceId').value = invoiceId;
                                    });
                                });

                                document.querySelectorAll('.print-receipt-btn').forEach(button => {
                                    button.addEventListener('click', function () {
                                        const paymentId = this.getAttribute('data-payment-id');
                                        printReceipt(paymentId);
                                    });
                                });

                                document.querySelectorAll('.print-receipt-btn-pos').forEach(button => {
                                    button.addEventListener('click', function () {
                                        const paymentId = this.getAttribute('data-payment-id');
                                        printReceipt_pos(paymentId);
                                    });
                                });

                                function printReceipt(paymentId) {
                                    // Open the PDF receipt in a new window
                                    window.open('comptabilite/generate_receipt&paymentId=' + paymentId, '_blank');
                                }

                                function printReceipt_pos(paymentId) {
                                    // Open the PDF receipt in a new window
                                    window.open('comptabilite/generate_receipt_pos&paymentId=' + paymentId, '_blank');
                                }

                            });
                        </script>

                    </div>
                </div>
            </div>
        </div>
    </section>

</main><!-- End #main -->

<?php include "./views/include/footer.php"; ?>