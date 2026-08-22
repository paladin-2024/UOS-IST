<?php
session_start();
require_once dirname(dirname(__DIR__)) . '/config/Connexion.php';
require_once dirname(dirname(__DIR__)) . '/models/DepotSoutenance.php';
require_once dirname(dirname(__DIR__)) . '/models/Universite.php';
require_once dirname(dirname(__DIR__)) . '/models/Agent.php';
require_once dirname(dirname(__DIR__)) . '/assets/html2pdf/vendor/autoload.php';

use Spipu\Html2Pdf\Html2Pdf;
use Spipu\Html2Pdf\Exception\Html2PdfException;
use Spipu\Html2Pdf\Exception\ExceptionFormatter;

// Vérifier l'authentification
if (!isset($_SESSION['id'])) {
    header('Location: ../../index.php');
    exit;
}

$idSection = isset($_GET['id_section']) ? intval($_GET['id_section']) : 0;
$anneeAcadId = isset($_GET['annee_acad']) ? intval($_GET['annee_acad']) : 0;

if ($idSection <= 0 || $anneeAcadId <= 0) {
    echo "Paramètres invalides";
    exit;
}

$depotSoutenanceModel = new DepotSoutenance();
$universite = new Universite();

// Récupérer les données - uniquement les soutenances programmées
$soutenances = $depotSoutenanceModel->getSoutenancesProgrammeesParSection($idSection, $anneeAcadId);
$configUni = $universite->getConfigurationUniversite();
$sectionInfo = $universite->getSectionById($idSection);
$anneeAcad = $universite->getAcademicYearById($anneeAcadId);

// Regrouper les soutenances par jury
$soutenancesParJury = [];
foreach ($soutenances as $soutenance) {
    $juryKey = !empty($soutenance['jury_designation']) ? $soutenance['jury_designation'] : 'Sans jury assigné';
    if (!isset($soutenancesParJury[$juryKey])) {
        $soutenancesParJury[$juryKey] = [];
    }
    $soutenancesParJury[$juryKey][] = $soutenance;
}

// Créer le contenu HTML avec un nouveau style basé sur des cartes
$htmlOutput = '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Programme des Soutenances par Jury</title>
    <style>
        body { 
            font-family: Arial, sans-serif; 
            font-size: 9pt; 
            color: #333; 
            margin: 0;
            padding: 0;
        }
        h1 { 
            font-size: 14pt; 
            color: #000; 
            text-align: center; 
            margin: 5px 0; 
        }
        h2 { 
            font-size: 12pt; 
            color: #000; 
            margin: 15px 0 8px 0; 
            text-align: left; 
            padding-bottom: 3px; 
            border-bottom: 1px solid #ccc; 
            background-color: #f5f5f5;
            padding: 5px;
        }
        .header {
            text-align: center;
            margin-bottom: 10px;
        }
        .institution-info {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
        }
        .institution-logo {
            width: 20%;
            text-align: left;
        }
        .institution-logo img {
            max-height: 50px;
        }
        .institution-details {
            width: 60%;
            text-align: center;
            font-weight: bold;
        }
        .institution-contact {
            width: 20%;
            text-align: right;
            font-size: 8pt;
        }
        .header-separator {
            border-bottom: 1px solid #000;
            margin: 5px 0 10px 0;
        }
        .footer {
            text-align: right;
            font-size: 8pt;
            margin-top: 5px;
            font-style: italic;
        }
        .section-info {
            text-align: center;
            margin: 5px 0 15px 0;
            font-weight: bold;
            font-size: 11pt;
        }
        /* Style de carte pour chaque soutenance */
        .soutenance-card {
            border: 1px solid #ccc;
            margin-bottom: 10px;
            padding: 5px;
            background-color: #f9f9f9;
            page-break-inside: avoid;
        }
        .soutenance-header {
            background-color: #eaeaea;
            padding: 5px;
            margin-bottom: 5px;
            font-weight: bold;
            border-bottom: 1px solid #ddd;
        }
        .soutenance-body {
            display: flex;
            flex-wrap: wrap;
        }
        .soutenance-info {
            width: 35%;
            padding-right: 10px;
        }
        .soutenance-sujet {
            width: 65%;
            margin-bottom: 5px;
        }
        .soutenance-infos-complementaires {
            width: 100%;
            margin-top: 5px;
            border-top: 1px dotted #ddd;
            padding-top: 5px;
            font-size: 8pt;
        }
        .label {
            font-weight: bold;
            display: inline-block;
            margin-right: 5px;
        }
        .value {
            display: inline-block;
        }
        .pagebreak {
            page-break-before: always;
        }
    </style>
</head>
<body>';

// Ajouter l'en-tête avec les informations de l'institution
$htmlOutput .= '<div class="institution-info">
    <div class="institution-logo">';

// Ajouter le logo s'il existe
if (!empty($configUni['logo'])) {
    $logoPath = dirname(dirname(__DIR__)) . '/' . $configUni['logo'];
    if (file_exists($logoPath)) {
        $logoData = base64_encode(file_get_contents($logoPath));
        $logoMime = mime_content_type($logoPath);
        $htmlOutput .= '<img src="data:' . $logoMime . ';base64,' . $logoData . '" alt="Logo Institution" />';
    }
}

$htmlOutput .= '</div>
    <div class="institution-details">
        <div>' . htmlspecialchars($configUni['ministere_tutelle'] ?? '') . '</div>
        <div>' . htmlspecialchars($configUni['nom'] ?? 'Université') . '</div>
    </div>
    <div class="institution-contact">
        <div>' . htmlspecialchars($configUni['adresse'] ?? '') . '</div>
        <div>' . htmlspecialchars($configUni['ville'] ?? '') . '</div>
        <div>' . htmlspecialchars($configUni['telephone'] ?? '') . '</div>
        <div>' . htmlspecialchars($configUni['email'] ?? '') . '</div>
    </div>
</div>
<div class="header-separator"></div>

<div class="header">
    <h1>PROGRAMME DES SOUTENANCES PAR JURY</h1>
</div>

<div class="section-info">
    Section: ' . htmlspecialchars($sectionInfo['designationSection']) . ' - Année Académique: ' . htmlspecialchars($anneeAcad['designation']) . '
</div>';

// Créer une section pour chaque jury
$juryCount = 0;
foreach ($soutenancesParJury as $juryName => $juryGroup) {
    // Ajouter un saut de page pour les jurys suivants (pas pour le premier)
    if ($juryCount > 0) {
        $htmlOutput .= '<div class="pagebreak"></div>';
    }
    
    $htmlOutput .= '<h2>Jury : ' . htmlspecialchars($juryName) . '</h2>';
    
    // Afficher les détails du jury si disponibles
    if (!empty($juryGroup[0]['president_nom']) || !empty($juryGroup[0]['secretaire_nom'])) {
        $htmlOutput .= '<p>';
        if (!empty($juryGroup[0]['president_nom'])) {
            $htmlOutput .= '<strong>Président:</strong> ' . htmlspecialchars($juryGroup[0]['president_nom']) . ' ';
        }
        if (!empty($juryGroup[0]['secretaire_nom'])) {
            $htmlOutput .= '<strong>Secrétaire:</strong> ' . htmlspecialchars($juryGroup[0]['secretaire_nom']);
        }
        $htmlOutput .= '</p>';
    }
    
    // Si aucune soutenance n'est trouvée
    if (count($juryGroup) === 0) {
        $htmlOutput .= '<div style="text-align: center; padding: 20px; font-style: italic;">
            Aucune soutenance programmée pour ce jury.
        </div>';
    } else {
        $count = 1;
        foreach ($juryGroup as $soutenance) {
            // Extraire les lecteurs si disponibles
            $lecteurs = '';
            if (!empty($soutenance['lecteurs'])) {
                $lecteursList = explode('|', $soutenance['lecteurs']);
                $lecteurs = '<div><span class="label">1<sup>er</sup> Lecteur:</span> <span class="value">' . htmlspecialchars($lecteursList[0] ?? 'Non défini') . '</span></div>';
                $lecteurs .= '<div><span class="label">2<sup>e</sup> Lecteur:</span> <span class="value">' . htmlspecialchars($lecteursList[1] ?? 'Non défini') . '</span></div>';
            } else {
                $lecteurs = '<div><span class="label">Lecteurs:</span> <span class="value">Non assignés</span></div>';
            }

            $htmlOutput .= '
            <div class="soutenance-card">
                <div class="soutenance-header">
                    Soutenance #' . $count . ' - ' . date('d/m/Y à H:i', strtotime($soutenance['date_soutenance'])) . ' - ' . htmlspecialchars($soutenance['lieu']) . '
                </div>
                <div class="soutenance-body">
                    <div class="soutenance-info">
                        <div><span class="label">Étudiant:</span> <span class="value">' . htmlspecialchars($soutenance['nom_etudiant']) . '</span></div>
                        <div><span class="label">Matricule:</span> <span class="value">' . htmlspecialchars($soutenance['matricule']) . '</span></div>
                    </div>
                    <div class="soutenance-sujet">
                        <div><span class="label">Sujet:</span></div>
                        <div style="margin-top: 3px;">' . htmlspecialchars($soutenance['intitule']) . '</div>
                    </div>
                    <div class="soutenance-infos-complementaires">
                        ' . $lecteurs . '
                    </div>
                </div>
            </div>';
            $count++;
        }
    }
    
    $juryCount++;
}

// Si aucun jury n'est défini
if (empty($soutenancesParJury)) {
    $htmlOutput .= '<div style="text-align: center; padding: 20px; margin: 20px 0; border: 1px solid #ccc; background-color: #f5f5f5;">
        <p style="font-weight: bold;">Aucune soutenance programmée pour cette section.</p>
    </div>';
}

$htmlOutput .= '
<div class="footer">
    <p>Document généré par ' . htmlspecialchars($_SESSION['nom'] ?? 'Système') . ', le ' . date('d-m-Y à H:i:s') . '</p>
</div>
</body>
</html>';

// Générer le PDF en orientation paysage (L)
try {
    $html2pdf = new Html2Pdf('L', 'A4', 'fr', true, 'UTF-8', [10, 10, 10, 10]);
    $html2pdf->setDefaultFont('Arial');
    $html2pdf->writeHTML($htmlOutput);
    $html2pdf->output('Programme_Soutenances_Par_Jury_' . date('Y-m-d') . '.pdf');
} catch (Html2PdfException $e) {
    $html2pdf->clean();
    $formatter = new ExceptionFormatter($e);
    echo $formatter->getHtmlMessage();
}
