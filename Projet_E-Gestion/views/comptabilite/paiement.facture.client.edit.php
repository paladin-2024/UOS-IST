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
                                                            <button type='button' class='btn btn-danger btn-sm cancel-payment-btn' data-payment-id='{$payment['idPaiement_client']}'>Annuler</button>
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
                                document.querySelectorAll('.cancel-payment-btn').forEach(button => {
                                    button.addEventListener('click', function () {
                                        const paymentId = this.getAttribute('data-payment-id');
                                        
                                        Swal.fire({
                                            title: 'Êtes-vous sûr ?',
                                            text: "Voulez-vous vraiment annuler ce paiement ?",
                                            icon: 'warning',
                                            showCancelButton: true,
                                            confirmButtonColor: '#3085d6',
                                            cancelButtonColor: '#d33',
                                            confirmButtonText: 'Oui, annuler!',
                                            cancelButtonText: 'Non, annuler'
                                        }).then((result) => {
                                            if (result.isConfirmed) {
                                                window.location.href = 'controller/cancel_payment.php?idPaiement=' + paymentId;
                                            }
                                        });
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