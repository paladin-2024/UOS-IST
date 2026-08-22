<?php
require_once './assets/html2pdf/vendor/autoload.php';

use Spipu\Html2Pdf\Html2Pdf;

if (isset($_GET['mois'])) {
    $month = $_GET['mois'];
    $year = $_GET['annee'];
} else {
    header("Location:../grh/agent.pres.list");
    exit;
}

$agentModel = new Agent();
$userId = $_SESSION['id'];
$agents = $agentModel->getAgentsByUserAccess($userId);

$agentsByStructureAndService = [];
foreach ($agents as $agent) {
    $structureName = $agent['designation'];
    $service = $agentModel->getServiceByAgent($agent['idAgent']);
    $serviceName = $service['designation'] ?? 'Service Inconnu';
    $agentsByStructureAndService[$structureName][$serviceName][] = $agent;
}

// Début du HTML
$html = '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Liste des Présences des Agents</title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 10px; margin: 0; padding: 0; }
        h1 { font-size: 18px; font-weight: bold; text-align: center; margin-bottom: 20px; }
        h2 { font-size: 14px; font-weight: bold; margin-top: 15px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 15px; table-layout: fixed; }
        th, td { padding: 5px; text-align: left; border: 1px solid #ddd; word-wrap: break-word; }
        th { background-color: #4CAF50; color: white; }
        tr:nth-child(even) { background-color: #f2f2f2; }
        .footer { font-size: 12px; text-align: right; color: #555; }
        .page-break { page-break-before: always; }
        .header-container { display: flex; align-items: center; justify-content: space-between; text-align: center; margin-bottom: 5px; }
        .header-text { flex: 1; text-align: center; font-weight: bold; font-size: 12px; }
    </style>
</head>
<body>
    <div class="header-container">
        <img class="logo-left" src="uploads/enteteBDOM.jpg" alt="Entete BDOM"/>
    </div>
    <hr/>
    <h2>LISTE DES PRÉSENCES DES AGENTS - ' . htmlspecialchars($month) . '/' . htmlspecialchars($year) . '</h2>';

// Boucle pour chaque structure
foreach ($agentsByStructureAndService as $structureName => $services) {
    $html .= '<h2>' . htmlspecialchars($structureName) . '</h2>';
    $html .= '<table>
        <thead>
            <tr>
                <th style="width: 5%;">#</th>
                <th style="width: 35%;">Nom de l\'Agent</th>
                <th style="width: 20%;">Jours de Présence</th>
                <th style="width: 20%;">Jours de Retard</th>
                <th style="width: 15%;">Jours d\'Absence</th>
            </tr>
        </thead>
        <tbody>';

    $i = 1;
    foreach ($services as $serviceName => $agents) {
        $html .= '<tr style="background-color: #ddd; font-weight: bold;">
            <td colspan="5" style="text-align: left;">' . htmlspecialchars($serviceName) . '</td>
        </tr>';

        foreach ($agents as $agent) {
            $presenceData = $agentModel->getPresenceDataForAgent($agent['idAgent'], $month, $year)->fetch();
            $joursPresence = $presenceData['joursPresence'] ?? 0;
            $joursAbsence = $presenceData['joursAbsence'] ?? 0;
            $joursRetard = $presenceData['joursRetard'] ?? 0;

            $html .= '<tr>
                <td>' . $i++ . '</td>
                <td>' . htmlspecialchars($agent['noms']) . '</td>
                <td>' . $joursPresence . '</td>
                <td>' . $joursRetard . '</td>
                <td>' . $joursAbsence. '</td>
            </tr>';
        }
    }
    $html .= '</tbody></table>';
    if (end($agentsByStructureAndService) !== $services) {
        $html .= '<div class="page-break"></div>';
    }
}
$html .= '<div class="footer">
        <p>Imprimé par ' . htmlspecialchars($_SESSION['nom']) . ', le ' . date('d/m/Y') . '</p>
    </div>
</body>
</html>';

// Génération du PDF
try {
    $html2pdf = new Html2Pdf('P', 'A4', 'fr', true, 'UTF-8', array(5, 5, 5, 5));
    $html2pdf->writeHTML($html);
    $html2pdf->output('liste_presences.pdf', 'I');
} catch (Exception $e) {
    echo 'Erreur: ' . $e->getMessage();
}
?>
