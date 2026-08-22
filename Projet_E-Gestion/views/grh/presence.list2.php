<?php
$month = $_GET['mois'];
$year = $_GET['annee'];

$agentModel = new Agent();
$userId = $_SESSION['id'];
$agents = $agentModel->getAgentsByUserAccess($userId);

$i=1;
echo "<table>";
$html="";
foreach ($agents as $agent) {
    //echo $agent['idAgent']." ".$agent['noms']." <br>";
    
    $presenceData = $agentModel->getPresenceDataForAgent($agent['idAgent'], $month, $year)->fetch();
    $joursPresence = $presenceData['joursPresence'] ?? 0;
    $joursAbsence = $presenceData['joursAbsence'] ?? 0;
    $joursRetard = $presenceData['joursRetard'] ?? 0;

    echo $html .= '<tr>
        <td>' . $i++ . '</td>
        <td>' . htmlspecialchars($agent['noms']) . '</td>
        <td>' . $joursPresence . '</td>
        <td>' . $joursRetard . '</td>
        <td>' . $joursAbsence. '</td>
    </tr>';
    
}
echo "</table>";