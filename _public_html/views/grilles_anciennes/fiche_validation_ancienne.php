<?php
session_start();
if (!isset($_SESSION['id'])) {
    header('Location: ../../connexion');
    exit();
}

require_once dirname(dirname(__DIR__)) . '/config/Connexion.php';
require_once dirname(dirname(__DIR__)) . '/models/GrilleAncienne.php';
require_once dirname(dirname(__DIR__)) . '/models/Universite.php';
require_once dirname(dirname(__DIR__)) . '/vendor/autoload.php';
require_once dirname(dirname(__DIR__)) . '/assets/html2pdf/vendor/autoload.php';

use Spipu\Html2Pdf\Html2Pdf;
use Spipu\Html2Pdf\Exception\Html2PdfException;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;

// Récupérer les paramètres
$importId = isset($_GET['import_id']) ? intval($_GET['import_id']) : 0;
$matricule = isset($_GET['matricule']) ? $_GET['matricule'] : '';

if (!$importId || !$matricule) {
    die('Paramètres manquants');
}

// Initialiser les objets
$grilleAncienne = new GrilleAncienne();
$universite = new Universite();

// Récupérer les informations
$importDetails = $grilleAncienne->getImportDetails($importId);
$etudiantData = $grilleAncienne->getEtudiantData($importId, $matricule);

if (!$importDetails || !$etudiantData) {
    die('Données non trouvées');
}

$etudiant = $etudiantData['etudiant'];
$ues = $etudiantData['ues'];
$resultats = $etudiantData['resultats'];

// Récupérer la configuration de l'université
$configUniversite = $universite->getConfigurationUniversite();

// Générer l'URL pour le QR code
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
$host = $_SERVER['HTTP_HOST'];
$baseUrl = $protocol . "://" . $host;
$bulletinUrl = $baseUrl . "/views/grilles_anciennes/fiche_validation_ancienne.php?import_id=" . $importId . "&matricule=" . $matricule;

// Configurer les options du QR code
$options = new QROptions([
    'outputType' => QRCode::OUTPUT_IMAGE_PNG,
    'eccLevel' => QRCode::ECC_H,
    'scale' => 5,
    'imageBase64' => true,
]);

// Générer le QR code
$qrCodeBase64 = (new QRCode($options))->render($bulletinUrl);

// Calculer les statistiques globales
$totalCredits = 0;
$totalCreditsValides = 0;
$moyenneGenerale = 0;
$totalCoefficients = 0;
$notesCount = 0;

foreach ($ues as $ue) {
    $ueCredits = floatval($ue['credits']);
    $totalCredits += $ueCredits;
    
    $ueMoyenne = 0;
    $ueCoefficients = 0;
    $ueNotesCompletes = true;
    
    foreach ($ue['ecues'] as $ecue) {
        if ($ecue['note_finale'] !== null) {
            $coefficient = floatval($ecue['coefficient']);
            $ueMoyenne += floatval($ecue['note_finale']) * $coefficient;
            $ueCoefficients += $coefficient;
        } else {
            $ueNotesCompletes = false;
        }
    }
    
    if ($ueCoefficients > 0 && $ueNotesCompletes) {
        $ueMoyenne = $ueMoyenne / $ueCoefficients;
        
        if ($ueMoyenne >= 10) {
            $totalCreditsValides += $ueCredits;
        }
        
        $moyenneGenerale += $ueMoyenne * $ueCredits;
        $totalCoefficients += $ueCredits;
        $notesCount++;
    }
}

if ($totalCoefficients > 0) {
    $moyenneGenerale = $moyenneGenerale / $totalCoefficients;
}

$pourcentageCredits = $totalCredits > 0 ? ($totalCreditsValides / $totalCredits) * 100 : 0;
$pourcentageMoyenne = ($moyenneGenerale / 20) * 100;

// Déterminer la mention
$mention = '';
if ($moyenneGenerale >= 16) {
    $mention = 'Très Bien';
} elseif ($moyenneGenerale >= 14) {
    $mention = 'Bien';
} elseif ($moyenneGenerale >= 12) {
    $mention = 'Assez Bien';
} elseif ($moyenneGenerale >= 10) {
    $mention = 'Satisfaction';
}

// Déterminer la décision
$decision = '';
if ($totalCreditsValides == $totalCredits && $moyenneGenerale >= 10) {
    $decision = 'ADMIS';
} elseif ($pourcentageCredits >= 75 && $moyenneGenerale >= 10) {
    $decision = 'ADMIS AVEC RACHAT';
} else {
    $decision = 'AJOURNÉ';
}

// Générer le HTML du bulletin
$html = '
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Fiche de Validation - ' . htmlspecialchars($etudiant['noms']) . '</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 10pt;
            line-height: 1.4;
        }
        
        .header {
            text-align: center;
            margin-bottom: 20px;
        }
        
        .institution-header {
            margin-bottom: 15px;
        }
        
        .logo {
            float: left;
            max-width: 80px;
            max-height: 80px;
            margin-right: 15px;
        }
        
        h1 {
            font-size: 16pt;
            margin: 5px 0;
            color: #2c3e50;
        }
        
        h2 {
            font-size: 14pt;
            margin: 5px 0;
            color: #34495e;
        }
        
        h3 {
            font-size: 12pt;
            margin: 5px 0;
            color: #7f8c8d;
        }
        
        .info-box {
            background: #ecf0f1;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 15px;
        }
        
        .student-info {
            margin-bottom: 15px;
        }
        
        .student-info p {
            margin: 5px 0;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
        }
        
        th, td {
            border: 1px solid #bdc3c7;
            padding: 5px;
            text-align: center;
        }
        
        th {
            background-color: #34495e;
            color: white;
            font-weight: bold;
        }
        
        .ue-header {
            background-color: #95a5a6;
            color: white;
            font-weight: bold;
        }
        
        .text-left {
            text-align: left;
        }
        
        .text-danger {
            color: #e74c3c;
        }
        
        .text-success {
            color: #27ae60;
        }
        
        .results {
            margin-top: 20px;
        }
        
        .results-table th {
            background-color: #2c3e50;
        }
        
        .footer {
            margin-top: 30px;
            text-align: center;
            font-size: 9pt;
            color: #7f8c8d;
        }
        
        .signature-section {
            margin-top: 40px;
            display: table;
            width: 100%;
        }
        
        .signature-left {
            display: table-cell;
            width: 40%;
            text-align: center;
        }
        
        .signature-right {
            display: table-cell;
            width: 40%;
            text-align: center;
        }
        
        .qr-code {
            text-align: center;
            margin-top: 20px;
        }
        
        .qr-code img {
            width: 100px;
            height: 100px;
        }
        
        .watermark {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%) rotate(-45deg);
            font-size: 100pt;
            color: rgba(0, 0, 0, 0.05);
            z-index: -1;
        }
        
        .badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 3px;
            font-size: 9pt;
            font-weight: bold;
        }
        
        .badge-success {
            background-color: #27ae60;
            color: white;
        }
        
        .badge-danger {
            background-color: #e74c3c;
            color: white;
        }
        
        .badge-warning {
            background-color: #f39c12;
            color: white;
        }
    </style>
</head>
<body>
    <div class="watermark">ARCHIVE</div>
    
    <div class="institution-header">';

if (!empty($configUniversite['logo'])) {
    $logoPath = dirname(dirname(__DIR__)) . '/' . $configUniversite['logo'];
    if (file_exists($logoPath)) {
        $logoType = pathinfo($logoPath, PATHINFO_EXTENSION);
        $logoData = file_get_contents($logoPath);
        $logoBase64 = 'data:image/' . $logoType . ';base64,' . base64_encode($logoData);
        $html .= '<img src="' . $logoBase64 . '" class="logo" alt="Logo">';
    }
}

$html .= '
        <div style="margin-left: 100px;">
            <div>' . htmlspecialchars($configUniversite['ministere_tutelle'] ?? 'MINISTÈRE DE L\'ENSEIGNEMENT SUPÉRIEUR') . '</div>
            <div><strong>' . htmlspecialchars($configUniversite['nom'] ?? 'UNIVERSITÉ') . '</strong></div>
            <div style="font-size: 9pt;">
                Tél: ' . htmlspecialchars($configUniversite['telephone'] ?? '') . ' | 
                Email: ' . htmlspecialchars($configUniversite['email'] ?? '') . '
            </div>
        </div>
    </div>
    
    <hr style="border: 1px solid #34495e; margin: 20px 0;">
    
    <div class="header">
        <h1>FICHE DE VALIDATION DE CRÉDITS</h1>
        <h2>ARCHIVES - ' . htmlspecialchars($importDetails['promotion']) . '</h2>
        <h3>' . htmlspecialchars($importDetails['session']) . ' - ' . htmlspecialchars($importDetails['annee_academique']) . '</h3>
    </div>
    
    <div class="info-box">
        <div class="student-info">
            <p><strong>Matricule:</strong> ' . htmlspecialchars($etudiant['matricule']) . '</p>
            <p><strong>Nom et Prénom:</strong> ' . htmlspecialchars($etudiant['noms']) . '</p>
            <p><strong>Promotion:</strong> ' . htmlspecialchars($importDetails['promotion']) . '</p>
            <p><strong>Année Académique:</strong> ' . htmlspecialchars($importDetails['annee_academique']) . '</p>
        </div>
    </div>
    
    <h3>Détail des notes</h3>
    <table>
        <thead>
            <tr>
                <th class="text-left" style="width: 40%;">Unité d\'Enseignement / ECUE</th>
                <th style="width: 10%;">Coeff.</th>
                <th style="width: 10%;">Note CC</th>
                <th style="width: 10%;">Note Ex.</th>
                <th style="width: 10%;">Note Finale</th>
                <th style="width: 10%;">Mention</th>
                <th style="width: 10%;">Validation</th>
            </tr>
        </thead>
        <tbody>';

foreach ($ues as $ue) {
    // Calculer la moyenne de l'UE
    $ueMoyenne = 0;
    $ueCoefficients = 0;
    $ueValidee = false;
    
    foreach ($ue['ecues'] as $ecue) {
        if ($ecue['note_finale'] !== null) {
            $coefficient = floatval($ecue['coefficient']);
            $ueMoyenne += floatval($ecue['note_finale']) * $coefficient;
            $ueCoefficients += $coefficient;
        }
    }
    
    if ($ueCoefficients > 0) {
        $ueMoyenne = $ueMoyenne / $ueCoefficients;
        $ueValidee = $ueMoyenne >= 10;
    }
    
    $html .= '
        <tr class="ue-header">
            <td class="text-left"><strong>' . htmlspecialchars($ue['designation']) . '</strong></td>
            <td>' . number_format($ue['credits'], 1) . '</td>
            <td colspan="3"><strong>' . ($ueCoefficients > 0 ? number_format($ueMoyenne, 2) : '-') . '</strong></td>
            <td>-</td>
            <td><strong class="' . ($ueValidee ? 'text-success' : 'text-danger') . '">' . 
                ($ueValidee ? 'V' : 'NV') . '</strong></td>
        </tr>';
    
    foreach ($ue['ecues'] as $ecue) {
        $noteFinale = $ecue['note_finale'] !== null ? floatval($ecue['note_finale']) : null;
        $noteClass = ($noteFinale !== null && $noteFinale < 10) ? 'text-danger' : '';
        
        $html .= '
        <tr>
            <td class="text-left" style="padding-left: 20px;">' . htmlspecialchars($ecue['designation']) . '</td>
            <td>' . number_format($ecue['coefficient'], 1) . '</td>
            <td>' . ($ecue['note_cc'] !== null ? number_format($ecue['note_cc'], 2) : '-') . '</td>
            <td>' . ($ecue['note_examen'] !== null ? number_format($ecue['note_examen'], 2) : '-') . '</td>
            <td class="' . $noteClass . '">' . 
                ($noteFinale !== null ? number_format($noteFinale, 2) : '-') . '</td>
            <td>' . htmlspecialchars($ecue['mention'] ?? '-') . '</td>
            <td>' . ($noteFinale !== null && $noteFinale >= 10 ? 
                '<span class="text-success">✓</span>' : '<span class="text-danger">✗</span>') . '</td>
        </tr>';
    }
}

$html .= '
        </tbody>
    </table>
    
    <div class="results">
        <h3>Résultats globaux</h3>
        <table class="results-table">
            <thead>
                <tr>
                    <th>Moyenne Générale</th>
                    <th>Crédits Validés</th>
                    <th>Pourcentage</th>
                    <th>Mention</th>
                    <th>Décision</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><strong>' . number_format($moyenneGenerale, 2) . '/20</strong></td>
                    <td><strong>' . $totalCreditsValides . '/' . $totalCredits . '</strong></td>
                    <td><strong>' . number_format($pourcentageMoyenne, 1) . '%</strong></td>
                    <td>';

if (!empty($mention)) {
    $badgeClass = 'badge-success';
    if ($mention == 'Assez Bien') $badgeClass = 'badge-warning';
    $html .= '<span class="badge ' . $badgeClass . '">' . $mention . '</span>';
} else {
    $html .= '-';
}

$html .= '</td>
                    <td>';

if ($decision == 'ADMIS') {
    $html .= '<span class="badge badge-success">' . $decision . '</span>';
} elseif ($decision == 'ADMIS AVEC RACHAT') {
    $html .= '<span class="badge badge-warning">' . $decision . '</span>';
} else {
    $html .= '<span class="badge badge-danger">' . $decision . '</span>';
}

$html .= '</td>
                </tr>
            </tbody>
        </table>
    </div>
    
    <div class="signature-section">
        <div class="signature-left">
            <p>Le Chef de Section</p>
            <br><br><br>
            <p>_______________________</p>
        </div>
        <div style="display: table-cell; width: 20%;"></div>
        <div class="signature-right">
            <p>Le Secrétaire Académique</p>
            <br><br><br>
            <p>_______________________</p>
        </div>
    </div>
    
    <div class="qr-code">
        <img src="' . $qrCodeBase64 . '" alt="QR Code">
        <p style="font-size: 8pt;">Document archivé - Scannez pour vérifier</p>
    </div>
    
    <div class="footer">
        <hr style="border: 0.5px solid #bdc3c7;">
        <p>Document généré le ' . date('d/m/Y à H:i') . ' par ' . htmlspecialchars($_SESSION['nom'] ?? 'Système') . '</p>
        <p style="font-style: italic;">Ce document est une reproduction des archives. Pour toute vérification, veuillez contacter le service académique.</p>
    </div>
</body>
</html>';

// Générer le PDF
try {
    $html2pdf = new Html2Pdf('P', 'A4', 'fr', true, 'UTF-8', array(10, 10, 10, 10));
    $html2pdf->writeHTML($html);
    $html2pdf->output('fiche_validation_' . $matricule . '.pdf', 'I');
} catch (Html2PdfException $e) {
    die('Erreur lors de la génération du PDF: ' . $e->getMessage());
}
?>