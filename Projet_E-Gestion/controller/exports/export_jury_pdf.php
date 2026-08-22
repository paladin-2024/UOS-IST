<?php
session_start();
require_once dirname(dirname(__DIR__)) . '/models/DepotSoutenance.php';
require_once dirname(dirname(__DIR__)) . '/models/Universite.php';
require_once dirname(dirname(__DIR__)) . '/models/Agent.php';
require_once 'export_utils.php';

// Vérifier l'authentification
if (!isset($_SESSION['id'])) {
    header('Location: ../../index.php');
    exit;
}

$idJury = isset($_GET['id_jury']) ? intval($_GET['id_jury']) : 0;
$anneeAcadId = isset($_GET['annee_acad']) ? intval($_GET['annee_acad']) : 0;

if ($idJury <= 0 || $anneeAcadId <= 0) {
    echo "Paramètres invalides";
    exit;
}

$depotSoutenanceModel = new DepotSoutenance();
$universite = new Universite();
$agentModel = new Agent();

// Récupérer les données
$soutenances = $depotSoutenanceModel->getSoutenancesParJury($idJury, $anneeAcadId);
$configUni = $universite->getConfigurationUniversite();
$juryInfo = $agentModel->getAgentById($idJury);
$anneeAcad = $universite->getAcademicYearById($anneeAcadId);

// Générer le contenu HTML
$title = 'Liste des Soutenances - Jury';
$subtitle = 'Jury: ' . htmlspecialchars($juryInfo['noms']) . ' - Année Académique: ' . htmlspecialchars($anneeAcad['designation']);

$html = getPdfHeader($configUni, $title, $subtitle);

$html .= '
<table>
    <tr>
        <th style="width: 5%;">#</th>
        <th style="width: 15%;">Date et Heure</th>
        <th style="width: 15%;">Lieu</th>
        <th style="width: 20%;">Étudiant</th>
        <th style="width: 15%;">Matricule</th>
        <th style="width: 30%;">Sujet</th>
    </tr>';

$count = 1;
foreach ($soutenances as $soutenance) {
    $html .= '
    <tr>
        <td>' . $count . '</td>
        <td>' . date('d/m/Y H:i', strtotime($soutenance['date_soutenance'])) . '</td>
        <td>' . htmlspecialchars($soutenance['lieu']) . '</td>
        <td>' . htmlspecialchars($soutenance['nom_etudiant']) . '</td>
        <td>' . htmlspecialchars($soutenance['matricule']) . '</td>
        <td>' . htmlspecialchars($soutenance['intitule']) . '</td>
    </tr>';
    $count++;
}

$html .= '
</table>

<div class="footer">
    <p>Document généré par ' . htmlspecialchars($_SESSION['nom'] ?? 'Système') . ', le ' . date('d-m-Y à H:i:s') . '</p>
</div>
</div>
</body>
</html>';

// Générer le PDF
generatePdf($html, 'Liste_Soutenances_Jury_' . date('Y-m-d') . '.pdf');
