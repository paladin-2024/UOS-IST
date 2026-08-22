<?php
require_once dirname(__DIR__).'/config/Connexion.php';
require_once dirname(__DIR__).'/vendor/autoload.php';
require_once dirname(__DIR__).'/models/Universite.php';
require_once dirname(__DIR__).'/models/GrilleAncienne.php';
require_once dirname(__DIR__).'/assets/html2pdf/vendor/autoload.php';

use Spipu\Html2Pdf\Html2Pdf;
use Spipu\Html2Pdf\Exception\Html2PdfException;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;

session_start();
if (!isset($_SESSION['id'])) {
    header('Location: ../connexion');
    exit();
}

// Activer le débogage
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Fonction de débogage
function debug_to_file($data, $label = '') {
    $output = date('Y-m-d H:i:s') . " - " . $label . ":\n";
    $output .= print_r($data, true) . "\n\n";
    file_put_contents('../debug_releve_ancien.log', $output, FILE_APPEND);
}

// Récupérer les paramètres
$matricule = isset($_GET['matricule']) ? $_GET['matricule'] : null;
$importId = isset($_GET['import_id']) ? intval($_GET['import_id']) : 0;

// Vérifier que tous les paramètres nécessaires sont fournis
if (!$matricule || !$importId) {
    die('Paramètres incomplets');
}

// Générer l'URL pour le QR code
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
$host = $_SERVER['HTTP_HOST'];
$baseUrl = $protocol . "://" . $host;
$releveUrl = $baseUrl . "/controller/export_releve_notes_ancienne.php?matricule=" . $matricule .
             "&import_id=" . $importId;

// Configurer les options du QR code
$options = new QROptions([
    'outputType' => QRCode::OUTPUT_IMAGE_PNG,
    'eccLevel' => QRCode::ECC_H,
    'scale' => 5,
    'imageBase64' => true,
]);

// Générer le QR code directement en base64
$qrCodeBase64 = (new QRCode($options))->render($releveUrl);

// Initialiser les objets nécessaires
$universite = new Universite();
$grilleAncienne = new GrilleAncienne();

// Récupérer les informations de configuration de l'université
$configUniversite = $universite->getConfigurationUniversite();

try {
    // Récupérer les informations de l'import
    $importInfo = $grilleAncienne->getImportById($importId);
    if (!$importInfo) {
        die('Import non trouvé');
    }

    // Récupérer les informations de l'étudiant
    $etudiant = $grilleAncienne->getEtudiantByMatricule($importId, $matricule);
    if (!$etudiant) {
        die('Étudiant non trouvé');
    }

    // Récupérer l'ID interne de l'étudiant
    $etudiantId = $etudiant['id'];
    
    // Récupérer les résultats pré-calculés
    $resultats = $grilleAncienne->getResultatsByEtudiant($etudiantId, 'annuel');
    $moyenneGenerale = !empty($resultats) ? $resultats[0]['moyenne'] : null;
    
    // Récupérer les UEs avec leurs ECUEs pour affichage détaillé
    $uesAvecEcues = $grilleAncienne->getUEsAvecECUEs($importId, $matricule);
    
    // Calculer les statistiques EXACTEMENT comme dans le fichier original
    $totalCredits = 0;
    $totalCreditsValides = 0;
    $totalMoyennePonderee = 0;
    $totalCoefficients = 0;
    $notesManquantes = false;
    
    // Simuler la structure des semestres pour correspondre au format original
    $notesEtudiant = [
        0 => [
            'info' => [
                'numeroSemestre' => $importInfo['semestre']
            ],
            'ues' => []
        ]
    ];
    
    foreach ($uesAvecEcues as $ue) {
        $credits = isset($ue['credits_ue']) ? intval($ue['credits_ue']) : 0;
        $totalCredits += $credits;
        
        // Calculer la moyenne de l'UE à partir des ECUEs (logique identique à export_grille_ancienne.php)
        $totalPointsUE = 0;
        $totalCoeffUE = 0;
        $hasAllNotes = true;
        
        $ecuesData = [];
        
        // Vérifier d'abord si TOUTES les ECUEs ont des notes
        foreach ($ue['ecues'] as $ecue) {
            if (!isset($ecue['note_finale']) || $ecue['note_finale'] === null) {
                $hasAllNotes = false;
                break;
            }
        }
        
        foreach ($ue['ecues'] as $ecue) {
            $ecuesData[] = [
                'designationECUE' => $ecue['nom_ecue'],
                'coefficient' => isset($ecue['credits']) ? floatval($ecue['credits']) : 1,
                'note' => isset($ecue['note_finale']) ? $ecue['note_finale'] : null
            ];
        }
        
        // Si toutes les ECUEs ont des notes, calculer la moyenne
        if ($hasAllNotes) {
            foreach ($ue['ecues'] as $ecue) {
                $coeff = isset($ecue['credits']) ? floatval($ecue['credits']) : 1;
                $totalPointsUE += floatval($ecue['note_finale']) * $coeff;
                $totalCoeffUE += $coeff;
            }
            
            if ($totalCoeffUE > 0) {
                $moyenneUE = $totalPointsUE / $totalCoeffUE;
                $estValidee = $moyenneUE >= 10 ? 1 : 0;
                
                // Capitalisation au niveau UE entière
                if ($moyenneUE >= 10) {
                    $totalCreditsValides += $credits;
                }
                
                $totalMoyennePonderee += ($moyenneUE * $credits);
                $totalCoefficients += $credits;
            } else {
                $moyenneUE = null;
                $estValidee = 0;
            }
        } else {
            // Pas toutes les notes - UE non validée - 0 crédit validé
            $moyenneUE = null;
            $estValidee = 0;
        }
        
        // Ajouter l'UE à la structure
        $notesEtudiant[0]['ues'][] = [
            'info' => [
                'codeUE' => $ue['code_ue'] ?? 'UE',
                'designationUE' => $ue['nom_ue'],
                'nombre_credits' => $ue['credits_ue'],
                'moyenne' => $moyenneUE,
                'est_validee' => $estValidee
            ],
            'ecues' => $ecuesData
        ];
    }
    
    // Simuler les stats du semestre
    $semestresStats = [
        0 => [
            'credits_total' => $totalCredits,
            'credits_valides' => $totalCreditsValides,
            'moyenne' => $moyenneGenerale,
            'notes_manquantes' => $notesManquantes
        ]
    ];
    
    // Calculer la moyenne générale et le pourcentage (logique identique à export_grille_ancienne.php)
    $pourcentageCredits = null;
    $pourcentageM = null;
    
    // Toujours calculer les pourcentages de crédits
    $pourcentageCredits = ($totalCredits > 0) ? (($totalCreditsValides / $totalCredits) * 100) : 0;
    
    // Calculer la moyenne seulement si on a des coefficients (toutes les UEs ont des moyennes)
    if ($totalCoefficients > 0) {
        if ($moyenneGenerale === null) { // Si pas de résultat pré-calculé, calculer
            $moyenneGenerale = $totalMoyennePonderee / $totalCoefficients;
        }
        $pourcentageM = ($moyenneGenerale > 0) ? (($moyenneGenerale/20)*100) : 0;
    } else {
        // Pas de moyenne calculable mais afficher les crédits
        $notesManquantes = true;
    }
    
    // Déterminer l'état global et la mention
    $estValideGlobal = (!$notesManquantes && $totalCreditsValides == $totalCredits && $moyenneGenerale >= 10);
    
    // Déterminer la mention seulement si pas de notes manquantes
    $mention = '';
    if (!$notesManquantes && $moyenneGenerale !== null) {
        if ($moyenneGenerale >= 16) {
            $mention = 'Très Bien';
        } elseif ($moyenneGenerale >= 14) {
            $mention = 'Bien';
        } elseif ($moyenneGenerale >= 12) {
            $mention = 'Assez Bien';
        } elseif ($moyenneGenerale >= 10) {
            $mention = 'Satisfaction';
        }
    }
    
    // Variables pour correspondre au format original
    $afficherDeuxSemestres = true; // Pour les grilles anciennes, on affiche comme annuel
    $isDeuxiemeSession = stripos($importInfo['session'], 'rattrapage') !== false;

    // Générer le contenu HTML du relevé (EXACTEMENT comme l'original)
    $html = '
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Relevé - ' . htmlspecialchars($etudiant['noms']) . '</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 9pt;
            line-height: 1.3;
        }
            
        .header {
            text-align: center;
            margin-bottom: 5px;
        }
        
        
        
        .institution-header {
            margin-bottom: 10px;
            width: 100%;
            box-sizing: border-box; /* Inclure les bordures dans la largeur */
        }
        
        .logo {
            float: left;
            max-width: 70px;
            max-height: 70px;
            margin-right: 15px;
        }
        
        .institution-info {
            margin-left: 10px; /* Espace pour le logo */
            margin-top: -15px;
        }
        
        .institution-info {
            flex-grow: 1;
        }
        
        
        h1 {
            font-size: 14pt;
            margin: 3px 0;
        }
        h2 {
            font-size: 12pt;
            margin: 3px 0;
        }
        h3 {
            font-size: 10pt;
            margin: 3px 0;
        }
        .student-info {
            margin-bottom: 5px;
        }
        .student-info p {
            margin: 3px 0;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;
        }
        th, td {
            border: 1px solid #000;
            padding: 3px;
            text-align: center;
            font-size: 10pt;
        }
        th {
            background-color: #f0f0f0;
        }
        .text-center {
            text-align: center;
        }
        .text-left {
            text-align: left;
        }
        .text-danger {
            color: #dc3545;
        }
        .text-success {
            color: #28a745;
        }
        .bg-light {
            background-color: #f8f9fa;
        }
        .results {
            margin-top: 5px;
        }
        .footer {
            text-align: center;
            margin-top: 5px;
            font-size: 8pt;
        }
        .signature {
            margin-top: 10px;
            text-align: right;
        }
            table {
            width: 100% !important; /* Forcer la largeur à 100% */
            table-layout: fixed; /* Utiliser une mise en page fixe */
            border-collapse: collapse;
            margin-bottom: 10px;
        }
        th, td {
            border: 1px solid #000;
            padding: 2px; /* Réduire le padding */
            text-align: center;
            font-size: 7pt; /* Réduire la taille de police */
            overflow: hidden; /* Éviter les débordements */
            word-wrap: break-word; /* Permettre le retour à la ligne des mots longs */
        }
        /* Définir des largeurs spécifiques pour les colonnes */
        .col-ue { width: 70%; }
        .col-credit { width: 10%; }
        .col-note { width: 10%; }
        .col-valid { width: 10%; }
        /* ... autres styles ... */
        .page-break {
            page-break-after: always;
        }
        table {
            page-break-inside: auto; /* Permettre la coupure des tableaux entre les pages */
        }
        tr {
            page-break-inside: avoid; /* Éviter de couper une ligne entre deux pages */
            page-break-after: auto;
        }

         /* Styles pour le QR code et la signature */
        .qrcode-container {
            position: relative;
            margin-top: 20px;
            height: 100px;
        }
        .qrcode-left {
            position: absolute;
            left: 0;
            bottom: 0;
            width: 30%;
        }
        .signature-right {
            position: absolute;
            right: 0;
            bottom: 0;
            width: 60%;
            text-align: right;
        }

        .institution-header p{
            margin-left : -60px;
        }
            

        
            
    </style>
</head>
<body>
   <div class="institution-header">
        ' . (isset($configUniversite['logo']) && !empty($configUniversite['logo']) ? 
        '<img src="../' . htmlspecialchars($configUniversite['logo']) . '" class="logo" alt="Logo">' : '') . '
        <div class="institution-info">
            <div>' . htmlspecialchars($configUniversite['ministere_tutelle'] ?? 'ENSEIGNEMENT SUPERIEUR') . '</div>
            <div><strong>' . htmlspecialchars($configUniversite['nom'] ?? 'UNIVERSITÉ') . '</strong></div>
            <div>Tél: ' . htmlspecialchars($configUniversite['telephone'] ?? '') . ' | Email: ' . htmlspecialchars($configUniversite['email'] ?? '') . '</div>
            ' . (isset($configUniversite['site_web']) && !empty($configUniversite['site_web']) ? 
                '<div>Site web: ' . htmlspecialchars($configUniversite['site_web']) . '</div>' : '') . '
        
        </div>
        <p>______________________________________________________________________________________________</p>
    </div>
    <div class="header">
        <h1>RELEVE DE NOTES N°.................</h1>
        <h2>' . htmlspecialchars($importInfo['promotion']) . '</h2>
        <h3>' . ucfirst(htmlspecialchars($importInfo['session'])) . ' - ' . htmlspecialchars($importInfo['annee_academique']) . '</h3>
    </div>
    
    <div class="student-info">
        <p><strong>Matricule:</strong> ' . htmlspecialchars($etudiant['matricule']) . '</p>
        <p><strong>Nom et Prénom:</strong> ' . htmlspecialchars($etudiant['noms']) . '</p>
    </div>';

    // Pour chaque semestre dans les notes (structure EXACTE de l'original)
    foreach ($notesEtudiant as $idxSemestre => $semestreData) {
        $semestre = $semestreData['info'];
        $stats = $semestresStats[$idxSemestre];
        
        $html .= '<h3>' . htmlspecialchars($semestre['numeroSemestre']) . '</h3>';
        
        // Tableau des notes par UE/ECUE (structure EXACTE de l'original)
        $html .= '<table>
    <thead>
        <tr>
            <th class="col-ue text-left">UE/ECUE</th>
            <th class="col-credit">Crédit</th>
            <th class="col-note">Moy.</th>
            <th class="col-valid">Valid.</th>
        </tr>
    </thead>
    <tbody>';

        // Pour chaque UE du semestre (structure EXACTE de l'original)
        foreach ($semestreData['ues'] as $ueData) {
            $ue = $ueData['info'];
            $estValidee = isset($ue['est_validee']) && $ue['est_validee'] == 1;
            $moyenneUE = isset($ue['moyenne']) ? $ue['moyenne'] : null;
            $moyenneClass = ($moyenneUE !== null && floatval($moyenneUE) < 10) ? 'text-danger' : '';
            $validationClass = $estValidee ? 'text-success' : 'text-danger';
            
            $html .= '<tr class="bg-light">
                <td class="text-left"><strong>' . htmlspecialchars($ue['codeUE']) . ' - ' . htmlspecialchars($ue['designationUE']) . '</strong></td>
                <td>' . (isset($ue['nombre_credits']) ? number_format(floatval($ue['nombre_credits']), 1) : '-') . '</td>
                <td class="' . $moyenneClass . '"><strong>' . ($moyenneUE !== null ? number_format(floatval($moyenneUE), 2) : '-') . '</strong></td>
                <td class="' . $validationClass . '"><strong>' . ($estValidee ? 'V' : 'NV') . '</strong></td>
            </tr>';
            
            // Pour chaque ECUE de l'UE (structure EXACTE de l'original)
            foreach ($ueData['ecues'] as $ecue) {
                $note = isset($ecue['note']) ? $ecue['note'] : null;
                $noteClass = ($note !== null && floatval($note) < 10) ? 'text-danger' : '';
                
                $html .= '<tr>
                    <td class="text-left">&nbsp;&nbsp;&nbsp;&nbsp;' . htmlspecialchars($ecue['designationECUE']) . '</td>
                    <td>' . (isset($ecue['coefficient']) ? number_format(floatval($ecue['coefficient']), 1) : '-') . '</td>
                    <td class="' . $noteClass . '">' . ($note !== null ? number_format(floatval($note), 2) : '-') . '</td>
                    <td></td>
                </tr>';
            }
        }
        
        $html .= '</tbody>
        </table>';

        // Ajouter un résumé pour le semestre (structure EXACTE de l'original)
        $html .= '<div style="text-align: right; font-size: 9pt; margin-bottom: 10px;">';
        
        if ($stats['notes_manquantes']) {
            $html .= '<strong>Notes incomplètes | Crédits validés: ' . 
                $stats['credits_valides'] . '/' . $stats['credits_total'] . '</strong>';
        } else {
            $html .= '<strong>Moyenne: ' . number_format($stats['moyenne'], 2) . ' | Crédits validés: ' . 
                $stats['credits_valides'] . '/' . $stats['credits_total'] . ' (' . 
                number_format(($stats['moyenne'] / 20) * 100, 2) . '%)</strong>';
        }
        
        $html .= '</div>';
    }

    // Afficher les résultats globaux (structure EXACTE de l'original)
    $html .= '<div class="results">
        <h3>Résultats globaux</h3>
        <table>
            <tr>
                <th>Moyenne</th>
                <th>Crédits validés</th>
                <th>Pourcentage</th>
                <th>Décision</th>';

    if (!$afficherDeuxSemestres) {
        $html .= '<th>État</th>';
    } else {
        $html .= '<th>Mention</th>';
    }

    $html .= '</tr>
            <tr>
                <td><strong>' . ($moyenneGenerale !== null ? number_format($moyenneGenerale, 2) : 'N/A') . '</strong></td>
                <td><strong>' . $totalCreditsValides . '/' . $totalCredits . '</strong></td>
                <td><strong>' . ($pourcentageM !== null ? number_format($pourcentageM, 2) . '%' : 'N/A') . '</strong></td>';

    // Afficher la décision en fonction du type de résultat (logique EXACTE de l'original)
    if ($afficherDeuxSemestres) {
        // Pour les résultats annuels
        if ($notesManquantes) {
            $decision = '<strong class="text-danger">INCOMPLET</strong>';
            $html .= '<td>' . $decision . '</td><td>-</td>';
        } 
        // Logique différente selon la session
        else if ($isDeuxiemeSession) {
            // En deuxième session
            if ($estValideGlobal) {
                $decision = '<strong class="text-success">ADMIS SANS RACHAT</strong>';
                $html .= '<td>' . $decision . '</td>';
                $html .= '<td><strong>' . htmlspecialchars($mention) . '</strong></td>';
            } else if ($pourcentageCredits !== null && $pourcentageCredits >= 75 && $moyenneGenerale >= 10) {
                $decision = '<strong class="text-success">ADMIS AVEC RACHAT</strong>';
                $html .= '<td>' . $decision . '</td>';
                $html .= '<td><strong>' . htmlspecialchars($mention) . '</strong></td>';
            } else {
                $decision = '<strong class="text-danger">AJOURNÉ</strong>';
                $html .= '<td>' . $decision . '</td><td>-</td>';
            }
        } 
        // En première session
        else {
            if ($estValideGlobal) {
                $decision = '<strong class="text-success">ADMIS SANS RACHAT</strong>';
                $html .= '<td>' . $decision . '</td>';
                $html .= '<td><strong>' . htmlspecialchars($mention) . '</strong></td>';
            } else {
                $decision = '<strong class="text-warning">ADMIS AU RATTRAPAGE</strong>';
                $html .= '<td>' . $decision . '</td><td>-</td>';
            }
        }
    } else {
        // Pour un semestre
        if ($notesManquantes) {
            $decision = '<strong class="text-danger">INCOMPLET</strong>';
            $etat = '<strong class="text-danger">INCOMPLET</strong>';
        } else if ($totalCreditsValides == $totalCredits) {
            $decision = '<strong class="text-success">VALIDÉ TOTALEMENT</strong>';
            $etat = '<strong class="text-success">COMPLET</strong>';
        } else if ($totalCreditsValides > 0) {
            $decision = '<strong>VALIDÉ PARTIELLEMENT</strong>';
            $etat = '<strong class="text-danger">INCOMPLET</strong>';
        } else {
            $decision = '<strong class="text-danger">NON VALIDÉ</strong>';
            $etat = '<strong class="text-danger">INCOMPLET</strong>';
        }
        
        $html .= '<td>' . $decision . '</td>';
        $html .= '<td>' . $etat . '</td>';
    }

    $html .= '</tr>
        </table>
    </div>';

    // Ajouter une div pour contenir le QR code et le footer (structure EXACTE de l'original)
    $html .= '<div style="position: relative; margin-top: 20px; height: 100px;">
        <!-- QR code en bas à gauche -->
        <div style="position: absolute; left: 0; bottom: 0; width: 30%;">
            <img src="' . $qrCodeBase64 . '" alt="QR Code" style="width: 80px; height: 80px;">
            <p style="font-size: 7pt; margin-top: 2px;">Scannez pour vérifier</p>
        </div>
        
        <!-- Signature au milieu ou à droite -->
        <div style="position: absolute; right: 0; bottom: 0; width: 60%; text-align: right;">
            <p>Le Chef de Section</p>
            <p style="margin-top: 20px;">___________________</p>
        </div>
    </div>';

    // Footer avec la date d'impression (structure EXACTE de l'original)
    $html .= '<div class="footer" style="text-align: center; margin-top: 5px; font-size: 8pt;">
        <p>Imprimé par ' . htmlspecialchars($_SESSION['nom']) . ', le ' . date('d/m/Y') . '</p>
    </div>
    </body>
    </html>';

    // Générer le PDF (EXACTEMENT comme l'original)
    $html2pdf = new Html2Pdf('P', 'A4', 'fr', true, 'UTF-8', array(10, 10, 10, 10));
    $html2pdf->writeHTML($html);
    
    $filename = 'Releve_Notes_' . $matricule . '_' . date('Y-m-d_H-i-s') . '.pdf';
    $html2pdf->output($filename, 'D');

} catch (Html2PdfException $e) {
    debug_to_file($e->getMessage(), 'Erreur HTML2PDF');
    die('Erreur lors de la génération du PDF: ' . $e->getMessage());
} catch (Exception $e) {
    debug_to_file($e->getMessage(), 'Erreur générale');
    die('Erreur: ' . $e->getMessage());
}
?>
