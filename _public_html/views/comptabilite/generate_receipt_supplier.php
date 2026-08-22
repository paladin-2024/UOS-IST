<?php

require_once './assets/html2pdf/vendor/autoload.php';

use Spipu\Html2Pdf\Html2Pdf;

if (isset($_GET['paymentId'])) {
    $paymentId = intval($_GET['paymentId']);
    $structureModel = new Structure();
    $paymentDetails = $structureModel->getSupplierPaymentById($paymentId);

    if ($paymentDetails) {
        // Fetch structure details
        $structureId = $paymentDetails['Structure_idStructure'];
        $structureDetails = $structureModel->getStructureById($structureId);

        // Fetch invoice details
        $invoiceDetails = $structureModel->getSupplierInvoiceById($paymentDetails['Facture_fournisseur_idFacture_fournisseur']);
        if (!$invoiceDetails) {
            echo 'Invoice details not found';
            exit;
        }

        $supplierName = $structureModel->getSupplierById($invoiceDetails['Fournisseur_idFournisseur'])['nom'];
        $invoiceAmount = $invoiceDetails['montant'];
        $invoiceNumber = $invoiceDetails['numeroFacture'];

        // Fetch payment history
        $paymentHistory = $structureModel->getSupplierPaymentsByInvoiceId($paymentDetails['Facture_fournisseur_idFacture_fournisseur']);
        $totalPaid = 0;
        $paymentHistoryHtml = '';
        foreach ($paymentHistory as $payment) {
            $totalPaid += $payment['montant'];
            $paymentHistoryHtml .= '<tr>
                <td>' . htmlspecialchars(date('d/m/Y', strtotime($payment['datePaiement']))) . '</td>
                <td>' . htmlspecialchars($payment['montant']) . '</td>
                <td>' . htmlspecialchars($payment['beneficiaire']) . '</td>
                <td>' . htmlspecialchars($payment['userName']) . '</td>
            </tr>';
        }

        // Calculate remaining balance
        $remainingBalance = $invoiceAmount - $totalPaid;

        // Generate receipt number
        $year = date('Y', strtotime($paymentDetails['datePaiement']));
        $receiptNumber = sprintf("Reçu de paiement fournisseur N°FF%s-%05d", $year, $paymentId);

        // Generate HTML content
        $html = '
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title>Reçu de Paiement</title>
            <style>
                body { font-family: Arial, sans-serif; font-size: 10px; }
                h1 { font-size: 14px; font-weight: bold; text-align: center; }
                h2 { font-size: 16px; font-weight: bold; text-align: center; }
                table { width: 100%; border-collapse: collapse; margin-bottom: 10px; table-layout: fixed; }
                table, th, td { border: 1px solid black; }
                th, td { padding: 5px; font-size: 10px; text-align: left; }
                th { width: 25%; }
                .footer { font-size: 12px; margin-top: 20px; text-align: right; }
                .signature { margin-top: 30px; display: flex; justify-content: space-between; }
                .signature-line { border-top: 1px solid black; width: 200px; margin-top: 5px; }
            </style>
        </head>
        <body>
            <div class="container">
                <img src="uploads/' . htmlspecialchars($structureDetails['logo']) . '" alt="Logo" width="60" height="70"/>
                <p>' . htmlspecialchars($structureDetails['designation']) . '<br>' . htmlspecialchars($structureDetails['adresse']) . '<br>' . htmlspecialchars($structureDetails['siteweb']) . '</p>
                <hr/>
                <h2>' . htmlspecialchars($receiptNumber) . '</h2>
                <table>
                    <tr><th>Numéro de Facture</th><td>' . htmlspecialchars($invoiceNumber) . '</td></tr>
                    <tr><th>Nom du Fournisseur</th><td>' . htmlspecialchars($supplierName) . '</td></tr>
                    <tr><th>Montant de la Facture</th><td>' . htmlspecialchars($invoiceAmount) . '</td></tr>
                    <tr><th>Date de Paiement</th><td>' . htmlspecialchars(date('d/m/Y', strtotime($paymentDetails['datePaiement']))) . '</td></tr>
                    <tr><th>Montant du Paiement</th><td>' . htmlspecialchars($paymentDetails['montant']) . '</td></tr>
                    <tr><th>Libellé</th><td>' . htmlspecialchars($paymentDetails['libelle']) . '</td></tr>
                    <tr><th>Bénéficiaire</th><td>' . htmlspecialchars($paymentDetails['beneficiaire']) . '</td></tr>
                </table>
                <h1>Historique des Paiements</h1>
                <table>
                    <tr><th>Date</th><th>Montant</th><th>Bénéficiaire</th><th>Enregistré Par</th></tr>
                    ' . $paymentHistoryHtml . '
                </table>
                <p>Solde Restant: ' . htmlspecialchars($remainingBalance) . ' USD</p>
                <div class="signature">
                    <div>
                        <p>Signature de l\'utilisateur:</p>
                        <div class="signature-line"></div>
                    </div>
                    <div>
                        <p>Signature du bénéficiaire:</p>
                        <div class="signature-line"></div>
                    </div>
                </div>
                <div class="footer">
                    <p>Imprimé par ' . $_SESSION['nom'] . ', le ' . date('d-m-Y') . '</p>
                </div>
            </div>
        </body>
        </html>';

        try {
            $html2pdf = new Html2Pdf('P', 'A4', 'fr', true, 'UTF-8', array(10, 15, 10, 15));
            $html2pdf->writeHTML($html);
            $html2pdf->output('recu_paiement_fournisseur.pdf', 'I');
        } catch (Exception $e) {
            echo 'Error: ' . $e->getMessage();
        }
    } else {
        echo 'Payment not found';
    }
} else {
    echo 'Invalid request';
}
?>