<?php
require_once './assets/html2pdf/vendor/autoload.php';

use Spipu\Html2Pdf\Html2Pdf;

if (isset($_GET['paymentId'])) {
    $paymentId = intval($_GET['paymentId']);
    $structureModel = new Structure();
    $paymentDetails = $structureModel->getSupplierPaymentById($paymentId);

    if ($paymentDetails) {
        // Récupérer les détails de la structure
        $structureId = $paymentDetails['Structure_idStructure'];
        $structureDetails = $structureModel->getStructureById($structureId);

        // Récupérer les détails de la facture fournisseur
        $invoiceDetails = $structureModel->getSupplierInvoiceById($paymentDetails['Facture_fournisseur_idFacture_fournisseur']);
        if (!$invoiceDetails) {
            echo 'Invoice details not found';
            exit;
        }

        $supplierName = $structureModel->getSupplierById($invoiceDetails['Fournisseur_idFournisseur'])['nom'];
        $invoiceAmount = $invoiceDetails['montant'];
        $invoiceNumber = $invoiceDetails['numeroFacture'];

        // Récupérer l'historique des paiements
        $paymentHistory = $structureModel->getSupplierPaymentsByInvoiceId($paymentDetails['Facture_fournisseur_idFacture_fournisseur']);
        $totalPaid = 0;
        $paymentHistoryHtml = '';
        foreach ($paymentHistory as $payment) {
            $totalPaid += $payment['montant'];
            $paymentHistoryHtml .= '<tr>
                <td>' . htmlspecialchars(date('d/m/Y', strtotime($payment['datePaiement']))) . '</td>
                <td>' . htmlspecialchars($payment['montant']) . ' USD</td>
                <td>' . htmlspecialchars($payment['beneficiaire']) . '</td>
            </tr>';
        }

        // Calculer le solde restant
        $remainingBalance = $invoiceAmount - $totalPaid;

        // Générer le numéro de reçu
        $year = date('Y', strtotime($paymentDetails['datePaiement']));
        $receiptNumber = sprintf("Reçu de paiement fournisseur N°FF-%s-%05d", $year, $paymentId);

        // Générer le contenu HTML pour le reçu POS
        $html = '
        <html>
        <head>
            <meta charset="UTF-8">
            <style>
                body { font-family: monospace; font-size: 10px; }
                h1, h2 { text-align: center; font-size: 14px; }
                table { width: 100%; border-collapse: collapse; }
                th, td { padding: 2px; text-align: left; }
                .footer { text-align: center; margin-top: 10px; }
                .logo { text-align: center; margin-bottom: 10px; }
            </style>
        </head>
        <body>
            <div class="logo">
                <img src="uploads/' . htmlspecialchars($structureDetails['logo']) . '" alt="Logo" width="60" height="70"/>
            </div>
            <h2>' . htmlspecialchars($structureDetails['designation']) . '</h2>
            <hr/>
            <h1>' . htmlspecialchars($receiptNumber) . '</h1>
            <table>
                <tr><th>Numéro Facture:</th><td>' . htmlspecialchars($invoiceNumber) . '</td></tr>
                <tr><th>Fournisseur:</th><td>' . htmlspecialchars($supplierName) . '</td></tr>
                <tr><th>Montant Facture:</th><td>' . htmlspecialchars($invoiceAmount) . ' USD</td></tr>
                <tr><th>Date Paiement:</th><td>' . htmlspecialchars(date('d/m/Y', strtotime($paymentDetails['datePaiement']))) . '</td></tr>
                <tr><th>Montant Paiement:</th><td>' . htmlspecialchars($paymentDetails['montant']) . ' USD</td></tr>
                <tr><th>Libellé:</th><td>' . htmlspecialchars($paymentDetails['libelle']) . '</td></tr>
                <tr><th>Bénéficiaire:</th><td>' . htmlspecialchars($paymentDetails['beneficiaire']) . '</td></tr>
                <tr><th>Utilisateur:</th><td>' . htmlspecialchars($paymentDetails['userName']) . '</td></tr>
            </table>
            <h1>Historique des Paiements</h1>
            <table>
                <tr><th>Date</th><th>Montant</th><th>Bénéficiaire</th></tr>
                ' . $paymentHistoryHtml . '
            </table>
            <p>Solde Restant: ' . htmlspecialchars($remainingBalance) . ' USD</p>
            <div class="footer">
                <p>Imprimé par ' . $_SESSION['nom'] . ', le ' . date('d-m-Y') . '</p>
            </div>
        </body>
        </html>';

        try {
            // Création du fichier PDF en format POS
            $html2pdf = new Html2Pdf('P', array(80, 297), 'fr', true, 'UTF-8', array(5, 5, 5, 5));
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
