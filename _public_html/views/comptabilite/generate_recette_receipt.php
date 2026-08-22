<?php
require_once './assets/html2pdf/vendor/autoload.php';

use Spipu\Html2Pdf\Html2Pdf;

if (isset($_GET['recetteId'])) {
    $recetteId = intval($_GET['recetteId']);
    $structureModel = new Structure();
    $recetteDetails = $structureModel->getRecetteById($recetteId);

    if ($recetteDetails) {
        // Fetch structure details
        $structureId = $recetteDetails['idStructure'];
        $structureDetails = $structureModel->getStructureById($structureId);

        // Fetch the logo
        $logo = $structureDetails['logo'];

        // Fetch budget line details
        $budgetLineDetails = $structureModel->getLignesRecetteById($recetteDetails['ligne_recette_structure_idligne_recette_structure']);
        if (!$budgetLineDetails) {
            echo 'Budget line details not found';
            exit;
        }

        $budgetLineDesignation = $budgetLineDetails[0]['designation'];

        // Generate receipt number
        $year = date('Y', strtotime($recetteDetails['dateOperation']));
        $receiptNumber = sprintf("Reçu de recette N°%s-%05d", $year, $recetteId);

        // POS receipt HTML
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
                <tr><th>Date:</th><td>' . htmlspecialchars(date('d/m/Y', strtotime($recetteDetails['dateOperation']))) . '</td></tr>
                <tr><th>Montant:</th><td>' . htmlspecialchars($recetteDetails['montantR']) . ' USD</td></tr>
                <tr><th>Motif:</th><td>' . htmlspecialchars($recetteDetails['motif']) . '</td></tr>
                <tr><th>Dépositaire:</th><td>' . htmlspecialchars($recetteDetails['depositaire']) . '</td></tr>
                <tr><th>Ligne Budgétaire:</th><td>' . htmlspecialchars($budgetLineDesignation) . '</td></tr>
                <tr><th>Utilisateur:</th><td>' . htmlspecialchars($recetteDetails['nomUser']) . '</td></tr>
            </table>
            <div class="footer">
                <p>Imprimé par ' . $_SESSION['nom'] . ', le ' . date('d-m-Y') . '</p>
            </div>
        </body>
        </html>';

        try {
            $html2pdf = new Html2Pdf('P', array(80, 297), 'fr', true, 'UTF-8', array(5, 5, 5, 5));
            $html2pdf->writeHTML($html);
            $html2pdf->output('recu_recette.pdf', 'I');
        } catch (Exception $e) {
            echo 'Error: ' . $e->getMessage();
        }
    } else {
        echo 'Recette not found';
    }
} else {
    echo 'Invalid request';
}
?>