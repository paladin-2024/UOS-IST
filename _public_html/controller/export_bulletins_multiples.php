<?php
session_start();
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/models/Dette.php';
require_once dirname(__DIR__) . '/models/Universite.php';
require_once dirname(__DIR__) . '/models/Etudiant.php';
require_once dirname(__DIR__) . '/vendor/autoload.php';

use Spipu\Html2Pdf\Html2Pdf;
use Spipu\Html2Pdf\Exception\Html2PdfException;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;

// Vérifier l'authentification
if (!isset($_SESSION['id']) || ($_SESSION['idRole'] != 1 && $_SESSION['idRole'] != 2)) {
    die('Accès non autorisé');
}

// Récupérer les paramètres
$matricules = isset($_POST['matricules']) ? explode(',', $_POST['matricules']) : [];
$anneeId = isset($_POST['annee']) ? intval($_POST['annee']) : 0;
$promotionId = isset($_POST['promotion']) ? intval($_POST['promotion']) : 0;

if (empty($matricules) || !$anneeId || !$promotionId) {
    die('Paramètres manquants');
}

// Initialiser les modèles
$dette = new Dette();
$universite = new Universite();
$etudiant = new Etudiant();

// Récupérer la configuration de l'université
$configUniversite = $universite->getConfigurationUniversite();

// Créer un nouveau PDF qui contiendra tous les bulletins
$html2pdf = new Html2Pdf('P', 'A4', 'fr', true, 'UTF-8', array(10, 10, 10, 10));
$html2pdf->pdf->SetDisplayMode('fullpage');

// Générer le contenu HTML pour tous les bulletins
$htmlContent = '';
$first = true;

foreach ($matricules as $matricule) {
    // Récupérer les informations de l'étudiant
    $infoEtudiant = $etudiant->getEtudiantByMatricule($matricule);
    if (!$infoEtudiant) {
        continue;
    }
    
    // Récupérer les dettes de l'étudiant
    $dettesEtudiant = $dette->getDettesEtudiant($matricule, $anneeId, $promotionId);
    if (empty($dettesEtudiant)) {
        continue;
    }
    
    // Ajouter un saut de page entre les bulletins (sauf pour le premier)
    if (!$first) {
        $htmlContent .= '<page_break />';
    }
    $first = false;
    
    // Générer l'URL pour le QR code
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
    $host = $_SERVER['HTTP_HOST'];
    $baseUrl = $protocol . "://" . $host;
    $bulletinUrl = $baseUrl . "/controller/export_bulletin_dettes.php?matricule=" . $matricule . 
                   "&annee=" . $anneeId . "&promotion=" . $promotionId;
    
    // Générer le QR code
    $options = new QROptions([
        'outputType' => QRCode::OUTPUT_IMAGE_PNG,
        'eccLevel' => QRCode::ECC_H,
        'scale' => 5,
        'imageBase64' => true,
    ]);
    $qrCodeBase64 = (new QRCode($options))->render($bulletinUrl);
    
    // Grouper les dettes par semestre
    $dettesBySemestre = [];
    foreach ($dettesEtudiant as $detteItem) {
        $semestre = $detteItem['semestre'];
        if (!isset($dettesBySemestre[$semestre])) {
            $dettesBySemestre[$semestre] = [];
        }
        $dettesBySemestre[$semestre][] = $detteItem;
    }
    
    // Calculer les totaux
    $totalCredits = 0;
    $totalCreditsValides = 0;
    foreach ($dettesEtudiant as $detteItem) {
        $totalCredits += $detteItem['credits'];
        if ($detteItem['statut'] == 'Validée') {
            $totalCreditsValides += $detteItem['credits'];
        }
    }
    
    // Générer le HTML pour ce bulletin
    $htmlContent .= '
    <style>
        body { font-family: Arial, sans-serif; font-size: 11pt; }
        .header { text-align: center; margin-bottom: 20px; }
        .logo { width: 80px; height: 80px; }
        .title { font-size: 16pt; font-weight: bold; margin: 10px 0; }
        .subtitle { font-size: 12pt; margin: 5px 0; }
        .student-info { margin: 20px 0; padding: 10px; background-color: #f5f5f5; }
        table { width: 100%; border-collapse: collapse; margin: 10px 0; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #4CAF50; color: white; }
        .semester-header { background-color: #e0e0e0; font-weight: bold; }
        .validated { color: green; font-weight: bold; }
        .pending { color: orange; font-weight: bold; }
        .footer { margin-top: 30px; text-align: center; font-size: 9pt; }
        .qr-code { width: 100px; height: 100px; margin: 20px auto; }
        .signature-section { margin-top: 40px; }
        .signature-box { display: inline-block; width: 45%; text-align: center; }
    </style>
    
    <div class="header">
        ' . (!empty($configUniversite['logo']) ? '<img src="../' . $configUniversite['logo'] . '" class="logo" alt="Logo">' : '') . '
        <div class="title">' . htmlspecialchars($configUniversite['nom'] ?? 'UNIVERSITÉ') . '</div>
        <div class="subtitle">BULLETIN DE DETTES ACADÉMIQUES</div>
        <div class="subtitle">Année académique : ' . htmlspecialchars($dettesEtudiant[0]['annee_academique'] ?? '') . '</div>
    </div>
    
    <div class="student-info">
        <strong>Matricule :</strong> ' . htmlspecialchars($infoEtudiant['matricule']) . '<br>
        <strong>Nom :</strong> ' . htmlspecialchars($infoEtudiant['noms']) . '<br>
        <strong>Promotion :</strong> ' . htmlspecialchars($dettesEtudiant[0]['promotion'] ?? '') . '<br>
        <strong>Date d\'édition :</strong> ' . date('d/m/Y') . '
    </div>
    
    <h3>Détail des dettes par semestre</h3>';
    
    // Afficher les dettes par semestre
    foreach ($dettesBySemestre as $semestre => $dettes) {
        $htmlContent .= '
        <table>
            <tr class="semester-header">
                <td colspan="5">SEMESTRE ' . htmlspecialchars($semestre) . '</td>
            </tr>
            <tr>
                <th>Code UE</th>
                <th>Désignation</th>
                <th>Crédits</th>
                <th>Note obtenue</th>
                <th>Statut</th>
            </tr>';
        
        foreach ($dettes as $detteItem) {
            $statutClass = $detteItem['statut'] == 'Validée' ? 'validated' : 'pending';
            $noteAffichee = $detteItem['note_rachat'] !== null ? 
                           number_format($detteItem['note_rachat'], 2) . '/20' : 
                           'En attente';
            
            $htmlContent .= '
            <tr>
                <td>' . htmlspecialchars($detteItem['code_ue']) . '</td>
                <td>' . htmlspecialchars($detteItem['ue_designation']) . '</td>
                <td style="text-align: center;">' . $detteItem['credits'] . '</td>
                <td style="text-align: center;">' . $noteAffichee . '</td>
                <td style="text-align: center;" class="' . $statutClass . '">' . 
                    htmlspecialchars($detteItem['statut']) . '</td>
            </tr>';
        }
        
        $htmlContent .= '</table>';
    }
    
    // Résumé
    $htmlContent .= '
    <div style="margin-top: 20px; padding: 10px; background-color: #f0f0f0;">
        <strong>RÉSUMÉ</strong><br>
        Total crédits en dette : ' . $totalCredits . '<br>
        Crédits validés : ' . $totalCreditsValides . '<br>
        Crédits restants : ' . ($totalCredits - $totalCreditsValides) . '
    </div>
    
    <div style="text-align: center;">
        <img src="' . $qrCodeBase64 . '" class="qr-code" alt="QR Code">
        <div style="font-size: 8pt;">Scannez pour vérifier l\'authenticité</div>
    </div>
    
    <div class="signature-section">
        <div class="signature-box">
            <p>Le Chef de Section LMD</p>
            <p style="margin-top: 50px;">_______________________</p>
        </div>
        <div class="signature-box">
            <p>Le Secrétaire Général Académique</p>
            <p style="margin-top: 50px;">_______________________</p>
        </div>
    </div>
    
    <div class="footer">
        <p>Document gén��ré le ' . date('d/m/Y à H:i:s') . ' par ' . htmlspecialchars($_SESSION['nom']) . '</p>
        <p>Ce document est certifié conforme aux données du système de gestion académique.</p>
    </div>';
}

// Si aucun bulletin n'a été généré
if (empty($htmlContent)) {
    die('Aucun bulletin à générer pour les matricules sélectionnés.');
}

try {
    // Écrire le contenu HTML dans le PDF
    $html2pdf->writeHTML($htmlContent);
    
    // Générer le nom du fichier
    $filename = 'Bulletins_Dettes_' . date('Ymd_His') . '.pdf';
    
    // Envoyer le PDF au navigateur
    $html2pdf->output($filename);
    
} catch (Html2PdfException $e) {
    die('Erreur lors de la génération du PDF : ' . $e->getMessage());
}
?>