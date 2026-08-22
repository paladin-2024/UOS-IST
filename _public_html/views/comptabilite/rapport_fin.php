<?php
require_once './assets/html2pdf/vendor/autoload.php';

use Spipu\Html2Pdf\Html2Pdf;

// Vérification des paramètres envoyés
if (!isset($_POST['startDate'], $_POST['endDate'], $_POST['structureId'])) {
    die('Paramètres manquants');
}

$dateDebut = $_POST['startDate'];
$dateFin = $_POST['endDate'];
$structureId = $_POST['structureId'];

$userId=$_SESSION['id'];

$structureModel = new Structure();

$structureDetails = $structureModel->getStructureById($structureId);

$compta = new Comptabilite();

// Récupération des données comptables groupées
$groupedRecettes = $compta->getDetailsRecettesParGroupe($userId,$structureId, $dateDebut, $dateFin,$userId);
$groupedDepenses = $compta->getDetailsDepensesParGroupe($userId,$structureId, $dateDebut, $dateFin);

$soldeReport = $compta->getSoldeReport($userId,$structureId, $dateDebut);
$clientPayments = $compta->getPaiementsClients($structureId, $dateDebut, $dateFin);
$supplierPayments = $compta->getPaiementsFournisseurs($structureId, $dateDebut, $dateFin);


$totalRecettes = array_sum(array_column($groupedRecettes, 'total_groupe')) + $clientPayments;
$totalDepenses = array_sum(array_column($groupedDepenses, 'total_groupe')) + $supplierPayments;
$soldeFinal = $soldeReport + $totalRecettes - $totalDepenses;

$html = '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Rapport Financier</title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 10px; margin: 10px; }
        h2 { font-size: 14px; font-weight: bold; text-align: center; }
        h1 { font-size: 16px; font-weight: bold; text-align: center; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; font-size: 11px; }
        th, td { border: 1px solid #ddd; padding: 6px; }
        th { background-color: #4CAF50; color: white; }
        th { width: 10%; } /* Ajustez la largeur des colonnes selon vos besoins */
        tr:nth-child(even) { background-color: #f2f2f2; }
        th, td, strong {text-align: right;}
        .footer { text-align: right; font-size: 9px; margin-top: 20px; }
        .summary { margin-top: 10px; }
        .summary p { font-size: 12px; }
    </style>
</head>
<body>
    <img src="uploads/' . htmlspecialchars($structureDetails['logo']) . '" alt="Logo" width="60" height="70"/>
    <p>' . htmlspecialchars($structureDetails['designation']) . '<br>' . htmlspecialchars($structureDetails['adresse']) . '<br>' . htmlspecialchars($structureDetails['siteweb']) . '</p>
    <hr/>
    <h1>Rapport Financier Du ' . htmlspecialchars(date('d/m/Y',strtotime($dateDebut))) . ' au ' . htmlspecialchars(date('d/m/Y',strtotime($dateFin))) . '</h1>
    
    <table>
        <tr>
            <th>Type</th>
            <th>Groupe</th>
            <th>Ligne</th>
            <th>Montant</th>
        </tr>
        <tr>
            <td colspan="3"><strong>Solde de Report</strong></td>
            <td>' . number_format($soldeReport, 2) . ' $</td>
        </tr>';

// Add client payments to entries
$clientPayments = $compta->getPaiementsClients($structureId, $dateDebut, $dateFin);
$html .= '<tr>
    <td>Entrée</td>
    <td>Clients</td>
    <td>Paiements Clients</td>
    <td>' . number_format($clientPayments, 2) . ' $</td>
</tr>
<tr>
    <td colspan="3"><strong>Sous-total Clients</strong></td>
    <td>' . number_format($clientPayments, 2) . ' $</td>
</tr>';

foreach ($groupedRecettes as $groupe) {
    $subtotal = 0;
    foreach ($groupe['lignes'] as $ligne) {
        $html .= '<tr>
            <td>Entrée</td>
            <td>' . htmlspecialchars($groupe['nom_groupe']) . '</td>
            <td>' . htmlspecialchars($ligne['nom_ligne']) . '</td>
            <td>' . number_format($ligne['total_ligne'], 2) . ' $</td>
        </tr>';
        $subtotal += $ligne['total_ligne'];
    }
    $html .= '<tr>
        <td colspan="3"><strong>Sous-total ' . htmlspecialchars($groupe['nom_groupe']) . '</strong></td>
        <td>' . number_format($subtotal, 2) . ' $</td>
    </tr>';
}

$html .= '<tr>
    <td colspan="3"><strong>Total Entrées</strong></td>
    <td>' . number_format($totalRecettes, 2) . ' $</td>
</tr>
<tr>
    <td colspan="4" style="background-color: #ddd;"></td>
</tr>';

// Add supplier payments to exits
$supplierPayments = $compta->getPaiementsFournisseurs($structureId, $dateDebut, $dateFin);
$html .= '<tr>
    <td>Sortie</td>
    <td>Fournisseurs</td>
    <td>Paiements Fournisseurs</td>
    <td>' . number_format($supplierPayments, 2) . ' $</td>
</tr>
<tr>
    <td colspan="3"><strong>Sous-total Fournisseurs</strong></td>
    <td>' . number_format($supplierPayments, 2) . ' $</td>
</tr>';

foreach ($groupedDepenses as $groupe) {
    $subtotal = 0;
    foreach ($groupe['lignes'] as $ligne) {
        $html .= '<tr>
            <td>Sortie</td>
            <td>' . htmlspecialchars($groupe['nom_groupe']) . '</td>
            <td>' . htmlspecialchars($ligne['nom_ligne']) . '</td>
            <td>' . number_format($ligne['total_ligne'], 2) . ' $</td>
        </tr>';
        $subtotal += $ligne['total_ligne'];
    }
    $html .= '<tr>
        <td colspan="3"><strong>Sous-total ' . htmlspecialchars($groupe['nom_groupe']) . '</strong></td>
        <td>' . number_format($subtotal, 2) . ' $</td>
    </tr>';
}

$html .= '<tr>
    <td colspan="3"><strong>Total Sorties</strong></td>
    <td>' . number_format($totalDepenses, 2) . ' $</td>
</tr>
<tr>
    <td colspan="3"><strong>Solde Final</strong></td>
    <td>' . number_format($soldeFinal, 2) . ' $</td>
</tr>
</table>

<div class="footer">
    <p>Imprimé par ' . htmlspecialchars($_SESSION['nom']) . ', le ' . date('d/m/Y') . '</p>
</div>
</body>
</html>';

try {
    $html2pdf = new Html2Pdf('P', 'A4', 'fr', true, 'UTF-8', array(10, 10, 10, 10));
    $html2pdf->writeHTML($html);
    $html2pdf->output('rapport_financier.pdf', 'I');
} catch (Exception $e) {
    echo 'Erreur lors de la génération du PDF: ' . $e->getMessage();
}
?>