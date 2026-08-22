<?php
require_once './assets/html2pdf/vendor/autoload.php';

use Spipu\Html2Pdf\Html2Pdf;

if (isset($_GET['id'])) {
    $etatId = intval($_GET['id']);
    $structureModel = new Structure();
    $etatDetails = $structureModel->getEtatDeBesoinById($etatId);

    if ($etatDetails) {
        $structureId = $etatDetails['idStructure'];
        $structureDetails = $structureModel->getStructureById($structureId);

        $lines = $structureModel->getLignesEtatBesoinByEtat($etatId);
        $linesHtml = '';
        foreach ($lines as $line) {
            $totalPrice = $line['quantite'] * $line['prixUnitaire'];
            $linesHtml .= '<tr>
                <td>' . htmlspecialchars($line['designation'], ENT_QUOTES, 'UTF-8') . '</td>
                <td>' . htmlspecialchars($line['quantite'], ENT_QUOTES, 'UTF-8') . '</td>
                <td>USD ' . htmlspecialchars($line['prixUnitaire'], ENT_QUOTES, 'UTF-8') . '</td>
                <td>USD ' . htmlspecialchars($totalPrice, ENT_QUOTES, 'UTF-8') . '</td>
            </tr>';
        }

        $uniqueNumber = sprintf("EDB-%05d", $etatId);

        $html = '
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title>État de Besoin</title>
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
                th { width: 25%; } /* Ajustez la largeur des colonnes selon vos besoins */
                .footer { font-size: 12px; margin-top: 20px; text-align: right; color: #555; }
                .signature-container { display: flex; justify-content: space-between; text-align: center; margin-top: 30px; }
                .signature { margin-top: 20px; }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <img src="uploads/' . htmlspecialchars($structureDetails['logo']) . '" alt="Logo"/>
                    <p>' . htmlspecialchars($structureDetails['designation']) . '<br>' . htmlspecialchars($structureDetails['adresse']) . '<br>' . htmlspecialchars($structureDetails['siteweb']) . '</p>
                </div>
                <hr/>
                <h1>État de Besoin N°'.$uniqueNumber.'</h1>
                <table>
                    <tr><th>Libellé</th><td>' . htmlspecialchars($etatDetails['libelle'], ENT_QUOTES, 'UTF-8') . '</td>
                    <th>Montant Total</th><td><b>USD ' . htmlspecialchars($etatDetails['montant'], ENT_QUOTES, 'UTF-8') . '</b></td></tr>
                    <tr><th>Date d\'Élaboration</th><td>' . htmlspecialchars(date('d/m/Y', strtotime($etatDetails['dateElaboration'])), ENT_QUOTES, 'UTF-8') . '</td>
                    <th>Service Demandeur</th><td>' . htmlspecialchars($etatDetails['serviceDesignation'], ENT_QUOTES, 'UTF-8') . '</td></tr>
                    <tr><th>Enregistré Par</th><td>' . htmlspecialchars($etatDetails['userName'], ENT_QUOTES, 'UTF-8') . '</td></tr>
                </table>
                <table>
                    <tr><th>Désignation</th><th>Quantité</th><th>Prix Unitaire</th><th>Prix Total</th></tr>
                    ' . $linesHtml . '
                </table>
                <div class="signature">
                    <table>
                        <tr>
                            <th>Demandeur</th>
                            <th>Responsable de Service</th>
                            <th>Responsable Financier</th>
                        </tr>
                        <tr>
                            <td><br/><br/></td>
                            <td></td>
                            <td></td>
                        </tr>
                    </table>

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
            $html2pdf->output('etat_de_besoin.pdf', 'I');
        } catch (Exception $e) {
            echo 'Error: ' . $e->getMessage();
        }
    } else {
        echo 'État de besoin not found';
    }
} else {
    echo 'Invalid request';
}
?>
