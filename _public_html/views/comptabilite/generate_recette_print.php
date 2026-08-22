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
                <tr><th>Date Opération:</th><td>' . htmlspecialchars(date('d/m/Y', strtotime($recetteDetails['dateOperation']))) . '</td></tr>
                <tr><th>Montant:</th><td>' . htmlspecialchars($recetteDetails['montantR']) . ' USD</td></tr>
                <tr><th>Motif:</th><td>' . htmlspecialchars($recetteDetails['motif']) . '</td></tr>
                <tr><th>Dépositaire:</th><td>' . htmlspecialchars($recetteDetails['depositaire']) . '</td></tr>
                <tr><th>Ligne Budgétaire:</th><td>' . htmlspecialchars($budgetLineDesignation) . '</td></tr>
                <tr><th>Enregistré par :</th><td>' . htmlspecialchars($recetteDetails['nomUser']) . '</td></tr>
            </table>
            <div class="footer">
                <p>Imprimé par ' . $_SESSION['nom'] . ', le ' . date('d-m-Y') . '</p>
            </div>
        </body>
        </html>';

        try {
            $html2pdf = new Html2Pdf('P', 'A4', 'fr', true, 'UTF-8', array(10, 10, 10, 10));
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