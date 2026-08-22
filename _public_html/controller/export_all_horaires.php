<?php
session_start();
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/models/Horaire.php';
require_once dirname(__DIR__) . '/models/Universite.php';
require_once dirname(__DIR__) . '/assets/html2pdf/vendor/autoload.php';

use Spipu\Html2Pdf\Html2Pdf;
use Spipu\Html2Pdf\Exception\Html2PdfException;
use Spipu\Html2Pdf\Exception\ExceptionFormatter;

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['id'])) {
    header("Location: ../index.php");
    exit;
}

// Vérifier si la requête est de type POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ../index.php");
    exit;
}

// Récupérer les paramètres
$section_id = isset($_POST['section_id']) ? intval($_POST['section_id']) : 0;
$idAnneeAcad = isset($_POST['annee_acad']) ? intval($_POST['annee_acad']) : 0;
$dateDebut = isset($_POST['date_debut']) ? trim($_POST['date_debut']) : '';
$dateFin = isset($_POST['date_fin']) ? trim($_POST['date_fin']) : '';
$format = isset($_POST['format']) ? trim($_POST['format']) : 'pdf';
$titre = isset($_POST['titre']) ? trim($_POST['titre']) : 'Horaire Hebdomadaire';

// Validation des données de base
if ($idAnneeAcad <= 0 || empty($dateDebut) || empty($dateFin)) {
    header("Location: ../index.php?view=enseignement/horaires&error=parametres_invalides");
    exit;
}

// Créer des instances des modèles
$horaire = new Horaire();
$universite = new Universite();

// Récupérer la configuration de l'université
$configUniversite = $universite->getConfigurationUniversite();

// Récupérer les promotions de l'utilisateur
$userId = $_SESSION['id'];
$isResponsable = $universite->isUserSectionResponsable($userId, $idAnneeAcad);

if ($isResponsable) {
    // L'utilisateur est responsable de section
    $promotions = $universite->getPromotionsByResponsable($idAnneeAcad, $userId);
} elseif (isset($_SESSION['idRole']) && $_SESSION['idRole'] === 1) {
    // Pour les administrateurs, toutes les promotions ou celles de la section spécifiée
    if ($section_id > 0) {
        $promotions = $universite->getPromotionsBySection($section_id, $idAnneeAcad);
    } else {
        $promotions = $universite->getPromotions($idAnneeAcad);
    }
} else {
    // Utilisateur sans droits spécifiques
    header("Location: ../index.php?view=enseignement/horaires&error=droits_insuffisants");
    exit;
}

// Si aucune promotion n'est accessible
if (empty($promotions)) {
    header("Location: ../index.php?view=enseignement/horaires&error=aucune_promotion");
    exit;
}

// Récupérer l'année académique et les infos de section
$anneeAcad = $universite->getAcademicYearById($idAnneeAcad);
$sectionInfo = null;
if ($section_id > 0) {
    $sectionInfo = $universite->getSectionById($section_id);
}

// Début du HTML global
$htmlOutput = '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Emploi du temps</title>
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
            margin: 0 0 8px 0; 
            text-align: center; 
            padding-bottom: 3px; 
            border-bottom: 1px solid #ccc; 
        }
        h3 {
            font-size: 11pt;
            color: #444;
            margin: 10px 0 5px 0;
            background-color: #f5f5f5;
            padding: 3px;
            text-align: center;
            border-radius: 3px;
        }
        table { 
            width: 100%; 
            border-collapse: collapse; 
            margin-bottom: 10px;
        }
        table, th, td { 
            border: 1px solid #ccc; 
        }
        th { 
            background-color: #f0f0f0; 
            font-weight: bold; 
            padding: 3px; 
            text-align: center; 
            font-size: 9pt;
        }
        td { 
            padding: 4px; 
            vertical-align: middle; 
            font-size: 8pt; 
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
        .jour-cell {
            font-weight: bold;
            width: 12%;
        }
        .cours-cell {
            width: 45%;
        }
        .horaire-cell {
            width: 23%;
        }
        .enseignant-cell {
            width: 20%;
        }
        .cm { background-color: #cfe2ff; }
        .td { background-color: #d1e7dd; }
        .tp { background-color: #fff3cd; }
        .eval { background-color: #f8d7da; }
        .page-break {
            page-break-after: always;
        }
        .semaine-info {
            text-align: center;
            margin: 5px 0;
            font-size: 10pt;
            font-weight: bold;
        }
        .footer {
            text-align: right;
            font-size: 8pt;
            margin-top: 5px;
            font-style: italic;
        }
        .legende {
            display: flex;
            justify-content: center;
            margin: 10px 0;
            font-size: 8pt;
        }
        .legende-item {
            margin: 0 5px;
            padding: 2px 5px;
            border-radius: 2px;
        }
    </style>
</head>
<body>';

// Ajouter l'en-tête avec les informations de l'institution
$htmlOutput .= '<div class="institution-info">
    <div class="institution-logo">';

// Ajouter le logo s'il existe
if (!empty($configUniversite['logo'])) {
    $logoPath = dirname(__DIR__) . '/' . $configUniversite['logo'];
    if (file_exists($logoPath)) {
        $logoData = base64_encode(file_get_contents($logoPath));
        $logoMime = mime_content_type($logoPath);
        $htmlOutput .= '<img src="data:' . $logoMime . ';base64,' . $logoData . '" alt="Logo Institution" />';
    }
}

$htmlOutput .= '</div>
    <div class="institution-details">
        <div>' . htmlspecialchars($configUniversite['ministere_tutelle'] ?? '') . '</div>
        <div>' . htmlspecialchars($configUniversite['nom'] ?? 'Université') . '</div>
        
    </div>
    <div class="institution-contact">
        <div>' . htmlspecialchars($configUniversite['adresse'] ?? '') . '</div>
        <div>' . htmlspecialchars($configUniversite['ville'] ?? '') . '</div>
        <div>' . htmlspecialchars($configUniversite['telephone'] ?? '') . '</div>
        <div>' . htmlspecialchars($configUniversite['email'] ?? '') . '</div>
    </div>
</div>
<div class="header-separator"></div>

<div class="header">
    <h1>' . htmlspecialchars($titre) . '</h1>
    <h1>Semaine du ' . date('d/m/Y', strtotime($dateDebut)) . ' au ' . date('d/m/Y', strtotime($dateFin)) . '</h1>
    
</div>

<div class="legende">
    <span class="legende-item cm">CM</span>
    <span class="legende-item td">TD</span>
    <span class="legende-item tp">TP</span>
    <span class="legende-item eval">Évaluation</span>
</div>';

// Pour chaque promotion
foreach ($promotions as $index => $promotion) {
    // Ajouter un saut de page sauf pour la première promotion
    if ($index > 0) {
        $htmlOutput .= '<div class="page-break"></div>';
    }
    
    // Titre de la promotion
    $htmlOutput .= '<h3>Promotion: ' . htmlspecialchars($promotion['designationPromotion']) . '</h3>';
    
    // Récupérer les horaires pour cette promotion
    $horaires = $horaire->getHorairesByPromotionAndDates(
        $promotion['idpromotion'], 
        $idAnneeAcad,
        $dateDebut,
        $dateFin
    );
    
    // Organiser les horaires par jour
    $horairesByDay = [];
    $weekDays = ['Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi'];
    
    foreach ($horaires as $h) {
        // Déterminer le jour
        if (!empty($h['date_cours'])) {
            $jourMapping = [
                'Monday' => 'Lundi',
                'Tuesday' => 'Mardi',
                'Wednesday' => 'Mercredi',
                'Thursday' => 'Jeudi',
                'Friday' => 'Vendredi',
                'Saturday' => 'Samedi',
                'Sunday' => 'Dimanche'
            ];
            $jourSemaine = date('l', strtotime($h['date_cours']));
            $jour = $jourMapping[$jourSemaine];
        } else {
            $jour = $h['jour'];
        }
        
        if (!isset($horairesByDay[$jour])) {
            $horairesByDay[$jour] = [];
        }
        
        $horairesByDay[$jour][] = $h;
    }
    
    // Trier les horaires par jour et heure de début
    foreach ($horairesByDay as &$dayHoraires) {
        usort($dayHoraires, function($a, $b) {
            return strcmp($a['heure_debut'], $b['heure_debut']);
        });
    }
    
    // Générer le tableau avec 4 colonnes
    $htmlOutput .= '<table cellspacing="0" cellpadding="3">
        <thead>
            <tr>
                <th class="jour-cell">Jour</th>
                <th class="cours-cell">Cours</th>
                <th class="horaire-cell">Horaire</th>
                <th class="enseignant-cell">Enseignant</th>
            </tr>
        </thead>
        <tbody>';
    
        // Pour chaque jour de la semaine
        foreach ($weekDays as $jour) {
            if (isset($horairesByDay[$jour]) && !empty($horairesByDay[$jour])) {
                $rowspan = count($horairesByDay[$jour]);
                $firstRow = true;
                
                foreach ($horairesByDay[$jour] as $index => $h) {
                    $htmlOutput .= '<tr>';
                    
                    // Colonne Jour (seulement sur la première ligne de chaque jour)
                    if ($firstRow) {
                        $htmlOutput .= '<td class="jour-cell" rowspan="' . $rowspan . '">' . $jour . '</td>';
                        $firstRow = false;
                    }
                    
                    // Déterminer le type de cours pour la classe CSS
                    $typeClass = 'cm'; // CM par défaut
                    if (isset($h['type_cours'])) {
                        if (strpos(strtolower($h['type_cours']), 'td') !== false) {
                            $typeClass = 'td';
                        } elseif (strpos(strtolower($h['type_cours']), 'tp') !== false) {
                            $typeClass = 'tp';
                        } elseif (strpos(strtolower($h['type_cours']), 'eval') !== false) {
                            $typeClass = 'eval';
                        }
                    }
                    
                    // Colonne Cours
                    $htmlOutput .= '<td class="cours-cell ' . $typeClass . '">' . 
                        htmlspecialchars($h['designationECUE']) . 
                        '<br><small>Salle: ' . htmlspecialchars($h['salle']) . '</small></td>';
                    
                    // Colonne Horaire
                    $htmlOutput .= '<td class="horaire-cell">' . 
                        substr($h['heure_debut'], 0, 5) . ' - ' . substr($h['heure_fin'], 0, 5) . 
                        '<br>Type: ' . htmlspecialchars($h['type_cours'] ?? 'CM') . '</td>';
                    
                    // Colonne Enseignant
                    $htmlOutput .= '<td class="enseignant-cell">' . htmlspecialchars($h['enseignant_nom']) . '</td>';
                    
                    $htmlOutput .= '</tr>';
                }
            } else {
                // Si aucun cours ce jour-là
                $htmlOutput .= '<tr>
                    <td class="jour-cell">' . $jour . '</td>
                    <td class="cours-cell" colspan="3">Aucun cours programmé</td>
                </tr>';
            }
        }
        
        $htmlOutput .= '</tbody></table>';
    }
    
    // Pied de page
    $htmlOutput .= '<div class="footer">
        Imprimé par ' . htmlspecialchars($_SESSION['nom']) . ' le ' . date('d/m/Y à H:i') . '
    </div>';
    
    // Fermer le HTML
    $htmlOutput .= '</body></html>';
    
    // Générer le PDF
    try {
        // Utiliser des marges plus petites pour éviter les débordements
        $html2pdf = new Html2Pdf('L', 'A4', 'fr', true, 'UTF-8', [5, 5, 5, 5]);
        $html2pdf->setDefaultFont('Arial');
        
        // Ajuster la taille du document si nécessaire pour éviter les débordements
        $html2pdf->pdf->SetDisplayMode('fullpage');
        
        $html2pdf->writeHTML($htmlOutput);
        $html2pdf->output('emploi_du_temps_toutes_promotions.pdf', 'I');
        exit;
    } catch (Html2PdfException $e) {
        $html2pdf->clean();
        $formatter = new ExceptionFormatter($e);
        echo $formatter->getHtmlMessage();
        exit;
    }
    