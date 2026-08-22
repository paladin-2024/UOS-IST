<?php
require_once './assets/html2pdf/vendor/autoload.php';

use Spipu\Html2Pdf\Html2Pdf;

$structureModel = new Structure();
$userId = $_SESSION['id']; // Assuming user ID is stored in session

// Vérifier si l'année est définie via un formulaire
$annee = isset($_POST['annee']) ? $_POST['annee'] : "";
$limit = 20;

if (!$annee) {
    echo '<!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>Sélection de l\'année</title>
        <style>
            body {
                font-family: Arial, sans-serif;
                display: flex;
                justify-content: center;
                align-items: center;
                height: 100vh;
                background-color: #f4f4f4;
            }
            .form-container {
                background: white;
                padding: 20px;
                border-radius: 10px;
                box-shadow: 0px 0px 10px rgba(0, 0, 0, 0.1);
                text-align: center;
            }
            input {
                padding: 10px;
                width: 100%;
                margin: 10px 0;
                border: 1px solid #ddd;
                border-radius: 5px;
            }
            button {
                background: #4CAF50;
                color: white;
                padding: 10px 15px;
                border: none;
                border-radius: 5px;
                cursor: pointer;
            }
            button:hover {
                background: #45a049;
            }
        </style>
    </head>
    <body>
        <div class="form-container">
            <h2>Veuillez saisir l\'année à consulter</h2>
            <form method="POST">
                <input type="text" name="annee" id="annee" placeholder="Entrer l\'année" required>
                <button type="submit">Valider</button>
            </form>
        </div>
    </body>
    </html>';
    exit;
}

// Fetch budgets accessible by the user
$budgets = $structureModel->getBudgetsByUser($userId);

$html = '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Budgets de Dépenses</title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 10px; margin-right: 20px; }
        h1 { font-size: 18px; font-weight: bold; text-align: center; margin-bottom: 20px; }
        h2 { font-size: 14px; font-weight: bold; text-align: left; color: #333; margin-top: 15px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 15px; table-layout: fixed; }
        th, td { padding: 8px; text-align: left; border: 1px solid #ddd; }
        th { background-color: #4CAF50; color: white; text-align: left; }
        tr:nth-child(even) { background-color: #f2f2f2; }
        tr:hover { background-color: #ddd; }
        .footer { font-size: 12px; margin-top: 20px; text-align: right; color: #555; }
        .container { margin: 20px; }
        .logo-container { text-align: center; }
        .logo-container img { width: 80px; height: 80px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="logo-container">
            <img src="uploads/bdom_bukavu_logo.jpg" alt="Logo" />
            <p><strong>BDOM SOFTWARE MANAGEMENT SYSTEM</strong></p>
        </div>
        <hr/>
        <h1>Budgets de Dépenses</h1>
        <table>
            <tr>
                <th style="width: 30%;">Nom du Groupe</th>
                <th style="width: 30%;">Code Ligne</th>
                <th style="width: 30%;">Désignation</th>
                <th style="width: 10%; text-align: right;">Montant (USD)</th>
            </tr>';
    
foreach ($budgets as $budget) {
    $totalBudget = 0;
    $html .= '<tr><td colspan="4" style="text-align: left; font-weight: bold; background-color: #ddd;">' . htmlspecialchars($budget['designation']) . ' (' . htmlspecialchars($budget['annee']) . ')</td></tr>';

    $groupes = $structureModel->getGroupesDepenseByUserAccess2($userId, $annee, $limit);
    foreach ($groupes as $groupe) {
        if ($groupe['Budget_depense_structure_idBudget_depense_structure'] == $budget['idBudget_depense_structure']) {
            $totalBudget += $groupe['soldeGD'];
            $html .= '<tr>
                <td><strong>' . htmlspecialchars($groupe['designationGD']) . '</strong></td>
                <td colspan="2"></td>
                <td style="text-align: right;"><strong>' . number_format($groupe['soldeGD'], 2, ',', ' ') . ' USD</strong></td>
            </tr>';

            $lignes = $structureModel->getLignesByGroupe($groupe['idGroupe_depense_structure']);
            foreach ($lignes as $ligne) {
                $html .= '<tr>
                    <td></td>
                    <td>' . htmlspecialchars($ligne['codeLigne']) . '</td>
                    <td>' . htmlspecialchars($ligne['designation']) . '</td>
                    <td style="text-align: right;">' . number_format($ligne['montant'], 2, ',', ' ') . ' USD</td>
                </tr>';
            }
        }
    }
    $html .= '<tr>
        <td colspan="3" style="text-align: right; font-weight: bold;">Total Budget :</td>
        <td style="text-align: right; font-weight: bold;">' . number_format($totalBudget, 2, ',', ' ') . ' USD</td>
    </tr>';
}

$html .= '</table>
<div class="footer">
    <p>Imprimé par ' . htmlspecialchars($_SESSION['nom']) . ', le ' . date('d-m-Y') . '</p>
</div>
</div>
</body>
</html>';

try {
    $html2pdf = new Html2Pdf('L', 'A4', 'fr', true, 'UTF-8', array(10, 15, 20, 15));
    $html2pdf->writeHTML($html);
    $html2pdf->output('budgets_depenses.pdf', 'I');
} catch (Exception $e) {
    echo 'Error: ' . $e->getMessage();
}
?>
