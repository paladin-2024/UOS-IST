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

        // POS receipt HTML
        $html = '
        <html>
        <head>
            <meta charset="UTF-8">
            <style>
                body { font-family: monospace; font-size: 10px; }
                h1, h2 { text-align: center; font-size: 12px; }
                table { width: 100%; border-collapse: collapse; }
                th, td { padding: 2px; text-align: left; }
                .footer { text-align: center; margin-top: 10px; }
                .logo { text-align: center; margin-bottom: 10px; }
                .signature { margin-top: 20px; }
                .signature th, .signature td { padding: 10px 0; }
            </style>
        </head>
        <body>
            <div class="logo">
                <img src="uploads/' . htmlspecialchars($structureDetails['logo']) . '" alt="Logo" width="50" height="50"/>
            </div>
            <h2>' . htmlspecialchars($structureDetails['designation']) . '</h2>
            <hr/>
            <h1>' . htmlspecialchars($receiptNumber) . '</h1>
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
                        <th>Bénéficiaire:</th>
                        <td>__________________________</td>
                    </tr>
                    <tr>
                        <th>Caissier:</th>
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
            $html2pdf = new Html2Pdf('P', array(80, 297), 'fr', true, 'UTF-8', array(5, 5, 5, 5));
            $html2pdf->writeHTML($html);
            $html2pdf->output('recu_depense_pos.pdf', 'I');
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