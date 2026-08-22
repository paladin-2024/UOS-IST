<?php
require_once './assets/html2pdf/vendor/autoload.php';

use Spipu\Html2Pdf\Html2Pdf;

if (isset($_GET['depenseId'])) {
    $depenseId = intval($_GET['depenseId']);
    $structureModel = new Structure();
    $depenseDetails = $structureModel->getDepenseById($depenseId);

    if ($depenseDetails) {
        // Fetch structure details
        $structureId = $depenseDetails['idStructure'];
        $structureDetails = $structureModel->getStructureById($structureId);

        // Fetch the logo
        $logo = $structureDetails['logo'];

        // Fetch budget line details
        $budgetLineDetails = $structureModel->getLignesDepenseById($depenseDetails['ligne_depense_structure_idligne_depense_structure']);
        if (!$budgetLineDetails) {
            echo 'Budget line details not found';
            exit;
        }

        $budgetLineDesignation = $budgetLineDetails[0]['designation'];

        // Generate receipt number
        $year = date('Y', strtotime($depenseDetails['dateoperation']));
        $receiptNumber = sprintf("Reçu de dépense N°%s-%05d", $year, $depenseId);

        // A4 receipt HTML
        $html = '
        <html>
        <head>
            <meta charset="UTF-8">
            <style>
                body { font-family: Arial, sans-serif; font-size: 12px; }
                h1, h2 { text-align: left; font-size: 18px; }
                h3{ text-align: center; font-size: 18px; }
                table { width: 100%; border-collapse: collapse; margin-top: 20px; }
                th, td { padding: 8px; text-align: left; border-bottom: 1px solid #ddd; }
                .footer { text-align: center; margin-top: 30px; }
                .logo { text-align: left; margin-bottom: 10px; }
                .header { text-align: left; margin-bottom: 10px; line-height: 1.2; }
                .signature { margin-top: 40px; }
                .signature th, .signature td { padding: 20px 0; }
            </style>
        </head>
        <body>
            <div class="logo">
                <img src="uploads/' . htmlspecialchars($structureDetails['logo']) . '" alt="Logo" width="70" height="70"/>
            </div>
            <div class="header">
                <h2>' . htmlspecialchars($structureDetails['designation']) . '</h2>
                <p>' . htmlspecialchars($structureDetails['adresse']) . '
                Téléphone: ' . htmlspecialchars($structureDetails['phone1']) . ' / ' . htmlspecialchars($structureDetails['phone2']) . '</p>
                <p>Site Web: ' . htmlspecialchars($structureDetails['siteweb']) . '</p>
            </div>
            <hr/>
            <h3>' . htmlspecialchars($receiptNumber) . '</h3>
            <table>
                <tr><th>Date:</th><td>' . htmlspecialchars(date('d/m/Y', strtotime($depenseDetails['dateoperation']))) . '</td></tr>
                <tr><th>Montant:</th><td>' . htmlspecialchars($depenseDetails['montantD']) . ' USD</td></tr>
                <tr><th>Motif:</th><td>' . htmlspecialchars($depenseDetails['motifD']) . '</td></tr>
                <tr><th>Bénéficiaire:</th><td>' . htmlspecialchars($depenseDetails['beneficiaire']) . '</td></tr>
                <tr><th>Ligne Budgétaire:</th><td>' . htmlspecialchars($budgetLineDesignation) . '</td></tr>
                <tr><th>Utilisateur:</th><td>' . htmlspecialchars($depenseDetails['nomUser']) . '</td></tr>
            </table>
            <div class="signature">
                <table>
                    <tr>
                        <th>Signature du Bénéficiaire:</th>
                        <td>__________________________</td>
                        <th>Signature du Caissier:</th>
                        <td>__________________________</td>
                    </tr>
                </table>
            </div>
            <div class="footer">
                <p>Imprimé par ' . $_SESSION['nom'] . ', le ' . date('d-m-Y') . '</p>
            </div>
        </body>
        </html>';

        try {
            $html2pdf = new Html2Pdf('P', 'A4', 'fr', true, 'UTF-8', array(10, 10, 10, 10));
            $html2pdf->writeHTML($html);
            $html2pdf->output('recu_depense.pdf', 'I');
        } catch (Exception $e) {
            echo 'Error: ' . $e->getMessage();
        }
    } else {
        echo 'Depense not found';
    }
} else {
    echo 'Invalid request';
}
?>