<?php
require_once './assets/html2pdf/vendor/autoload.php';
use Spipu\Html2Pdf\Html2Pdf;

if (isset($_GET['id'])) {
    $entryId = intval($_GET['id']);
    $structureModel = new Structure();
    $entryDetails = $structureModel->getEntreeById($entryId);

    if ($entryDetails) {
        $structureId = $entryDetails['Structure_idStructure'];
        $structureDetails = $structureModel->getStructureById($structureId);

        $details = $structureModel->getDetailsEntreeByManifest($entryId);
        $detailsHtml = '';
        foreach ($details as $detail) {
            $detailsHtml .= '<tr>
                <td>' . htmlspecialchars($detail['designation'], ENT_QUOTES, 'UTF-8') . '</td>
                <td>' . htmlspecialchars($detail['unite'], ENT_QUOTES, 'UTF-8') . '</td>
                <td>' . htmlspecialchars($detail['quantite'], ENT_QUOTES, 'UTF-8') . '</td>
            </tr>';
        }

        $uniqueNumber = sprintf("ENTREE-%05d", $entryId);

        $html = '
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title>Entrée de Dépôt</title>
            <style>
                body { font-family: Arial, sans-serif; font-size: 12px; color: #333; margin: 0; padding: 0; }
                .container { width: 95%; max-width: 750px; margin: auto; padding: 20px; border: 2px solid #007BFF; border-radius: 10px; background:rgb(255, 255, 255); box-sizing: border-box; }
                h1 { font-size: 18px; text-align: center; color: #007BFF; margin-bottom: 10px; }
                .header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 10px; }
                .header img { width: 70px; height: 70px; }
                .header p { font-size: 14px; text-align: left; }
                hr { border: 1px solid #007BFF; margin-bottom: 10px; }
                table { width: 100%; border-collapse: collapse; margin-bottom: 10px; table-layout: fixed; }
                table, th, td { border: 1px solid black; }
                th, td { padding: 5px; font-size: 10px; text-align: left; }
                th { width: 25%; }
                .footer { font-size: 12px; margin-top: 20px; text-align: right; color: #555; }
                .signature-container { display: flex; justify-content: space-between; text-align: center; margin-top: 30px; }
                .signature { margin-top: 20px; width: 30%; }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <img src="uploads/' . htmlspecialchars($structureDetails['logo']) . '" alt="Logo"/>
                    <p>' . htmlspecialchars($structureDetails['designation']) . '<br>' . htmlspecialchars($structureDetails['adresse']) . '<br>' . htmlspecialchars($structureDetails['siteweb']) . '</p>
                </div>
                <hr/>
                <h1>Entrée au Dépôt N°'.$uniqueNumber.'</h1>
                <table>
                    <tr><th>Date</th><td>' . htmlspecialchars(date('d/m/Y', strtotime($entryDetails['dateOperation'])), ENT_QUOTES, 'UTF-8') . '</td>
                    <th>Transporteur</th><td>' . htmlspecialchars($entryDetails['transporteur'], ENT_QUOTES, 'UTF-8') . '</td></tr>
                    <tr><th>Référence Document</th><td>' . htmlspecialchars($entryDetails['reference_document'], ENT_QUOTES, 'UTF-8') . '</td>
                    <th>Dépôt</th><td>' . htmlspecialchars($structureDetails['designation'], ENT_QUOTES, 'UTF-8') . '</td></tr>
                    <tr><th>Enregistré Par</th><td>' . htmlspecialchars($entryDetails['nomUser'], ENT_QUOTES, 'UTF-8') . '</td></tr>
                </table>
                <table>
                    <tr><th>Désignation</th><th>Unité</th><th>Quantité</th></tr>
                    ' . $detailsHtml . '
                </table>
                <div class="signature-container">
                    <div class="signature">
                        <p>Signature du Responsable du Dépôt</p>
                        
                        <hr/>
                    </div>
                    <div class="signature">
                        <p>Signature du Transporteur</p>
                        
                        <hr/>
                    </div>
                </div>
                <div class="footer">
                    <p>Imprimé par ' . htmlspecialchars($_SESSION['nom'], ENT_QUOTES, 'UTF-8') . ', le ' . date('d-m-Y') . '</p>
                </div>
            </div>
        </body>
        </html>';

        try {
            $html2pdf = new Html2Pdf('P', 'A4', 'fr', true, 'UTF-8', array(10, 10, 10, 10));
            $html2pdf->writeHTML($html);
            $html2pdf->output('entree_depot.pdf', 'I');
        } catch (Exception $e) {
            echo 'Error: ' . $e->getMessage();
        }
    } else {
        echo 'Entrée de dépôt not found';
    }
} else {
    echo 'Invalid request';
}
?>