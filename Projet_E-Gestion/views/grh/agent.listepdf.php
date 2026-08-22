<?php
require_once './assets/html2pdf/vendor/autoload.php';

use Spipu\Html2Pdf\Html2Pdf;

$agentModel = new Agent();
$structureModel = new Structure();
$userId = $_SESSION['id']; 

$agents = $agentModel->getAgentsByUserAccess($userId);
$agentsByStructure = [];
foreach ($agents as $agent) {
    $agentsByStructure[$agent['designation']][] = $agent;
}

$html = '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Liste des Agents</title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 12px; margin: 10px; }
        h1 { font-size: 16px; text-align: center; margin-bottom: 10px; }
        h2 { font-size: 14px; color: #333; margin-top: 15px; }
        table { width: 100%; table-layout: auto; border-collapse: collapse; font-size: 12px; page-break-inside: avoid; }
        th, td { padding: 6px; border: 1px solid #ddd; text-align: left; }
        th { background-color: #4CAF50; color: white; font-size: 13px; }
        td { font-size: 12px; }
        .logo-container { text-align: center; margin-bottom: 5px; }
        .footer { font-size: 11px; text-align: right; margin-top: 15px; }
    </style>
</head>
<body>
    <div class="logo-container">
        <img src="uploads/enteteBDOM.jpg" alt="Logo" />
    </div>
    <hr/>
    <h1>LISTE DES AGENTS ENREGISTRES DANS LE SYSTEME</h1>';

foreach ($agentsByStructure as $structureName => $agents) {
    $html .= '<h2>' . htmlspecialchars($structureName) . '</h2>
    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>Noms</th>
                <th>Lieu de Naissance</th>
                <th>Date de Naissance</th>
                <th>Sexe</th>
                <th>État Civil</th>
                <th>Niveau d\'Étude</th>
                <th>Téléphone</th>
                <th>Service</th>
            </tr>
        </thead>
        <tbody>';

    $i = 1;
    foreach ($agents as $agent) {
        $idAgent = $agent['idAgent'] ?? null;
        $service = $agentModel->getServiceByAgent($idAgent);
        $serviceDesignation = isset($service['designation']) ? htmlspecialchars($service['designation']) : 'N/A';
        $dateNaissance = isset($agent['dateNaissance']) ? date('d/m/Y', strtotime($agent['dateNaissance'])) : 'N/A';

        $html .= '<tr>
            <td>' . $i++ . '</td>
            <td>' . htmlspecialchars($agent['noms']) . '</td>
            <td>' . htmlspecialchars($agent['lieuNaissance']) . '</td>
            <td>' . $dateNaissance . '</td>
            <td>' . htmlspecialchars($agent['sexe']) . '</td>
            <td>' . htmlspecialchars($agent['etatCivil']) . '</td>
            <td>' . htmlspecialchars($agent['niveauEtude']) . '</td>
            <td>' . htmlspecialchars($agent['telephone']) . '</td>
            <td>' . $serviceDesignation . '</td>
        </tr>';
    }

    $html .= '</tbody>
    </table>';
}

$html .= '<div class="footer">
        <p>Imprimé par ' . htmlspecialchars($_SESSION['nom']) . ', le ' . date('d/m/Y') . '</p>
    </div>
</body>
</html>';

try {
    $html2pdf = new Html2Pdf('L', 'A4', 'fr', true, 'UTF-8', array(5, 5, 5, 5));
    $html2pdf->pdf->SetAutoPageBreak(true, 5);
    $html2pdf->writeHTML($html);
    $html2pdf->output('liste_agents.pdf', 'I');
} catch (Exception $e) {
    echo 'Error: ' . $e->getMessage();
}
?>
