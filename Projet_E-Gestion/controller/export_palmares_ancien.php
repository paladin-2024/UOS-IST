<?php
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/vendor/autoload.php';
require_once dirname(__DIR__) . '/models/Universite.php';
require_once dirname(__DIR__) . '/models/GrilleAncienne.php';
require_once dirname(__DIR__) . '/assets/html2pdf/vendor/autoload.php';

use Spipu\Html2Pdf\Html2Pdf;
use Spipu\Html2Pdf\Exception\Html2PdfException;

session_start();
if (!isset($_SESSION['id'])) {
    header('Location: ../connexion');
    exit();
}

// Fonction pour déterminer la mention (code lettre)
function mentions($point) {
    $mention = "";
    if ($point < 8)
        $mention = "G";
    else if ($point >= 8 && $point < 10)
        $mention = "F";
    else if ($point >= 10 && $point < 12)
        $mention = "E";
    else if ($point >= 12 && $point < 14)
        $mention = "D";
    else if ($point >= 14 && $point < 16)
        $mention = "C";
    else if ($point >= 16 && $point < 18)
        $mention = "B";
    else if ($point >= 18)
        $mention = "A";
    return $mention;
}

// Fonction pour déterminer la mention (texte complet)
function mentionsReleve($point) {
    $mention = "";
    if ($point < 8)
        $mention = "Insatisfaisant";
    else if ($point >= 8 && $point < 10)
        $mention = "Insuffisant";
    else if ($point >= 10 && $point < 12)
        $mention = "Passable";
    else if ($point >= 12 && $point < 14)
        $mention = "Assez bien";
    else if ($point >= 14 && $point < 16)
        $mention = "Bien";
    else if ($point >= 16 && $point < 18)
        $mention = "Très bien";
    else if ($point >= 18)
        $mention = "Excellent";
    return $mention;
}

// Récupérer les paramètres
$importId = isset($_GET['import_id']) ? intval($_GET['import_id']) : 0;

// Vérifier que l'import ID est fourni
if (!$importId) {
    die('Import ID manquant');
}

// Initialiser les objets
$universite = new Universite();
$grilleAncienne = new GrilleAncienne();

try {
    // Récupérer les informations de l'import
    $importInfo = $grilleAncienne->getImportById($importId);
    if (!$importInfo) {
        die('Import non trouvé');
    }

    // Récupérer tous les étudiants avec leurs moyennes générales
    $etudiants = $grilleAncienne->getEtudiantsAvecMoyennes($importId);
    
    // Trier les étudiants par moyenne décroissante
    usort($etudiants, function($a, $b) {
        $moyA = isset($a['moyenne_generale']) ? floatval($a['moyenne_generale']) : 0;
        $moyB = isset($b['moyenne_generale']) ? floatval($b['moyenne_generale']) : 0;
        return $moyB <=> $moyA; // Tri décroissant
    });

    // Récupérer la configuration de l'université
    $config = $universite->getConfigurationUniversite();

    // Calculer les statistiques
    $totalEtudiants = count($etudiants);
    $etudiantsAdmis = 0;
    $sommeMoyennes = 0;
    $moyennesValides = 0;
    $meilleursEtudiants = array_slice($etudiants, 0, 10); // Top 10

    foreach ($etudiants as $etudiant) {
        if (isset($etudiant['moyenne_generale']) && $etudiant['moyenne_generale'] !== null) {
            $moyenne = floatval($etudiant['moyenne_generale']);
            $sommeMoyennes += $moyenne;
            $moyennesValides++;
            
            if ($moyenne >= 10) {
                $etudiantsAdmis++;
            }
        }
    }

    $moyenneClasse = $moyennesValides > 0 ? $sommeMoyennes / $moyennesValides : 0;
    $tauxReussite = $totalEtudiants > 0 ? ($etudiantsAdmis / $totalEtudiants) * 100 : 0;

    // Construire le HTML
    $html = '
    <style>
        @page {
            margin: 15mm 10mm 15mm 10mm;
        }
        body {
            font-family: "Times New Roman", serif;
            font-size: 12px;
            line-height: 1.4;
            color: #000;
        }
        .header {
            text-align: center;
            margin-bottom: 25px;
            border-bottom: 3px solid #000;
            padding-bottom: 15px;
        }
        .logo {
            max-height: 70px;
            margin-bottom: 10px;
        }
        .title {
            font-size: 20px;
            font-weight: bold;
            margin: 15px 0;
            text-transform: uppercase;
            color: #2c3e50;
        }
        .subtitle {
            font-size: 16px;
            margin: 10px 0;
            font-weight: bold;
        }
        .info-section {
            margin: 20px 0;
            padding: 15px;
            background-color: #f8f9fa;
            border: 2px solid #dee2e6;
            border-radius: 5px;
        }
        .info-row {
            margin: 8px 0;
            display: flex;
            justify-content: space-between;
        }
        .label {
            font-weight: bold;
        }
        .stats-grid {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 15px;
            margin: 20px 0;
        }
        .stat-box {
            text-align: center;
            padding: 15px;
            border: 2px solid #3498db;
            border-radius: 5px;
            background-color: #ecf0f1;
        }
        .stat-number {
            font-size: 24px;
            font-weight: bold;
            color: #2980b9;
        }
        .stat-label {
            font-size: 12px;
            margin-top: 5px;
            color: #7f8c8d;
        }
        .table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        .table th, .table td {
            border: 1px solid #000;
            padding: 8px;
            text-align: center;
            font-size: 11px;
        }
        .table th {
            background-color: #3498db;
            color: white;
            font-weight: bold;
        }
        .table td.left {
            text-align: left;
        }
        .rank-1 { background-color: #f1c40f; color: #000; font-weight: bold; }
        .rank-2 { background-color: #e8e8e8; color: #000; font-weight: bold; }
        .rank-3 { background-color: #cd7f32; color: white; font-weight: bold; }
        .admis { color: #27ae60; font-weight: bold; }
        .echec { color: #e74c3c; font-weight: bold; }
        .footer {
            margin-top: 40px;
            text-align: center;
            font-size: 11px;
            color: #7f8c8d;
            border-top: 1px solid #bdc3c7;
            padding-top: 15px;
        }
        .signature-section {
            margin-top: 40px;
            text-align: right;
        }
        .mention-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 10px;
            margin: 15px 0;
            font-size: 10px;
        }
        .mention-box {
            text-align: center;
            padding: 8px;
            border: 1px solid #bdc3c7;
            background-color: #ecf0f1;
        }
    </style>

    <div class="header">
        ';
        
    if (!empty($config['logo'])) {
        $logoPath = dirname(__DIR__) . '/' . $config['logo'];
        if (file_exists($logoPath)) {
            $html .= '<img src="' . $logoPath . '" alt="Logo" class="logo"><br>';
        }
    }
    
    $html .= '
        <div style="font-size: 18px; font-weight: bold; margin: 10px 0;">' . 
        (!empty($config['nom']) ? htmlspecialchars($config['nom']) : 'UNIVERSITÉ') . '</div>
        
        <div class="title">PALMARÈS</div>
        <div class="subtitle">Grille Ancienne - ' . htmlspecialchars($importInfo['session']) . '</div>
    </div>

    <div class="info-section">
        <div class="info-row">
            <span class="label">Année Académique :</span>
            <span>' . htmlspecialchars($importInfo['annee_academique']) . '</span>
        </div>
        <div class="info-row">
            <span class="label">Promotion :</span>
            <span>' . htmlspecialchars($importInfo['promotion']) . '</span>
        </div>
        <div class="info-row">
            <span class="label">Semestre :</span>
            <span>' . htmlspecialchars($importInfo['semestre']) . '</span>
        </div>
        <div class="info-row">
            <span class="label">Session :</span>
            <span>' . ucfirst(htmlspecialchars($importInfo['session'])) . '</span>
        </div>
        <div class="info-row">
            <span class="label">Date de génération :</span>
            <span>' . date('d/m/Y à H:i') . '</span>
        </div>
    </div>

    <div class="stats-grid">
        <div class="stat-box">
            <div class="stat-number">' . $totalEtudiants . '</div>
            <div class="stat-label">Étudiants inscrits</div>
        </div>
        <div class="stat-box">
            <div class="stat-number">' . $etudiantsAdmis . '</div>
            <div class="stat-label">Étudiants admis</div>
        </div>
        <div class="stat-box">
            <div class="stat-number">' . number_format($tauxReussite, 1) . '%</div>
            <div class="stat-label">Taux de réussite</div>
        </div>
    </div>

    <div style="text-align: center; margin: 20px 0;">
        <strong>Moyenne générale de la promotion : ' . number_format($moyenneClasse, 2) . '/20</strong>
    </div>

    <div class="mention-grid">
        <div class="mention-box"><strong>A:</strong> 18-20 (Excellent)</div>
        <div class="mention-box"><strong>B:</strong> 16-18 (Très bien)</div>
        <div class="mention-box"><strong>C:</strong> 14-16 (Bien)</div>
        <div class="mention-box"><strong>D:</strong> 12-14 (Assez bien)</div>
        <div class="mention-box"><strong>E:</strong> 10-12 (Passable)</div>
        <div class="mention-box"><strong>F:</strong> 8-10 (Insuffisant)</div>
        <div class="mention-box"><strong>G:</strong> 0-8 (Insatisfaisant)</div>
        <div class="mention-box"></div>
    </div>';

    if (!empty($meilleursEtudiants)) {
        $html .= '
        <h3 style="text-align: center; margin: 30px 0 15px 0; color: #2c3e50;">🏆 TOP 10 DES MEILLEURS ÉTUDIANTS 🏆</h3>
        
        <table class="table">
            <thead>
                <tr>
                    <th>Rang</th>
                    <th>Matricule</th>
                    <th>Nom et Prénom</th>
                    <th>Moyenne</th>
                    <th>Mention</th>
                    <th>Résultat</th>
                </tr>
            </thead>
            <tbody>';

        foreach ($meilleursEtudiants as $index => $etudiant) {
            $rang = $index + 1;
            $moyenne = isset($etudiant['moyenne_generale']) ? floatval($etudiant['moyenne_generale']) : 0;
            $mention = mentions($moyenne);
            $mentionTexte = mentionsReleve($moyenne);
            $resultat = $moyenne >= 10 ? 'ADMIS' : 'AJOURNÉ';
            
            $rankClass = '';
            if ($rang == 1) $rankClass = 'rank-1';
            elseif ($rang == 2) $rankClass = 'rank-2';
            elseif ($rang == 3) $rankClass = 'rank-3';
            
            $resultClass = $moyenne >= 10 ? 'admis' : 'echec';
            
            $html .= '
                <tr class="' . $rankClass . '">
                    <td><strong>' . $rang . '</strong></td>
                    <td>' . htmlspecialchars($etudiant['matricule']) . '</td>
                    <td class="left">' . htmlspecialchars($etudiant['nom']) . '</td>
                    <td><strong>' . number_format($moyenne, 2) . '</strong></td>
                    <td><strong>' . $mention . '</strong></td>
                    <td class="' . $resultClass . '">' . $resultat . '</td>
                </tr>';
        }

        $html .= '
            </tbody>
        </table>';
    }

    // Tableau complet des étudiants
    $html .= '
    <h3 style="text-align: center; margin: 30px 0 15px 0; color: #2c3e50;">CLASSEMENT GÉNÉRAL</h3>
    
    <table class="table">
        <thead>
            <tr>
                <th>Rang</th>
                <th>Matricule</th>
                <th>Nom et Prénom</th>
                <th>Moyenne</th>
                <th>Mention</th>
                <th>Résultat</th>
            </tr>
        </thead>
        <tbody>';

    foreach ($etudiants as $index => $etudiant) {
        $rang = $index + 1;
        $moyenne = isset($etudiant['moyenne_generale']) ? floatval($etudiant['moyenne_generale']) : 0;
        $mention = mentions($moyenne);
        $mentionTexte = mentionsReleve($moyenne);
        $resultat = $moyenne >= 10 ? 'ADMIS' : 'AJOURNÉ';
        
        $rankClass = '';
        if ($rang == 1) $rankClass = 'rank-1';
        elseif ($rang == 2) $rankClass = 'rank-2';
        elseif ($rang == 3) $rankClass = 'rank-3';
        
        $resultClass = $moyenne >= 10 ? 'admis' : 'echec';
        
        $html .= '
            <tr class="' . $rankClass . '">
                <td>' . $rang . '</td>
                <td>' . htmlspecialchars($etudiant['matricule']) . '</td>
                <td class="left">' . htmlspecialchars($etudiant['nom']) . '</td>
                <td>' . number_format($moyenne, 2) . '</td>
                <td>' . $mention . '</td>
                <td class="' . $resultClass . '">' . $resultat . '</td>
            </tr>';
    }

    $html .= '
        </tbody>
    </table>

    <div class="signature-section">
        <div style="margin-top: 40px;">
            <div>Le Responsable de l\'établissement</div>
            <div style="margin-top: 60px; border-top: 1px solid #000; width: 200px; margin-left: auto;">
                Signature et Cachet
            </div>
        </div>
    </div>

    <div class="footer">
        <p><strong>Document officiel généré automatiquement</strong></p>
        <p>Ce palmarès classe les étudiants selon leur moyenne générale</p>
        <p>Généré le ' . date('d/m/Y à H:i:s') . '</p>
    </div>';

    // Générer le PDF
    $html2pdf = new Html2Pdf('P', 'A4', 'fr', true, 'UTF-8', [15, 10, 15, 10]);
    $html2pdf->pdf->SetDisplayMode('fullpage');
    $html2pdf->writeHTML($html);
    
    $filename = 'palmares_' . str_replace(' ', '_', $importInfo['promotion']) . '_' . date('Y-m-d') . '.pdf';
    $html2pdf->output($filename, 'D');

} catch (Html2PdfException $e) {
    error_log("Erreur HTML2PDF: " . $e->getMessage());
    die('Erreur lors de la génération du PDF: ' . $e->getMessage());
} catch (Exception $e) {
    error_log("Erreur générale: " . $e->getMessage());
    die('Erreur: ' . $e->getMessage());
}
?>
