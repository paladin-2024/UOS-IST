<?php
require_once dirname(__DIR__,2) . '/config/config.php';
require_once dirname(__DIR__,2) . '/config/Connexion.php';
require_once dirname(__DIR__,2) . '/vendor/autoload.php';
require_once dirname(__DIR__,2) . '/models/Universite.php';
require_once dirname(__DIR__,2) . '/assets/html2pdf/vendor/autoload.php';

use Spipu\Html2Pdf\Html2Pdf;
use Spipu\Html2Pdf\Exception\Html2PdfException;

// Vérification de l'authentification
if (!isset($_SESSION['id'])) {
    header('Location: connexion');
    exit();
}

// Récupération de l'ID du palmarès à imprimer
$idPalmares = isset($_GET['id']) ? intval($_GET['id']) : 0;
if (!$idPalmares) {
    echo "<script>
        alert('Identifiant de palmarès invalide');
        window.location.href = '?view=academique/palmares';
    </script>";
    exit;
}

// Initialisation de la connexion à la base de données
$pdo = Connexion::getInstance()->getPDO();

// Récupération des informations du palmarès
$query = "SELECT * FROM palmares_archive WHERE id_palmares = :id";
$stmt = $pdo->prepare($query);
$stmt->bindParam(':id', $idPalmares, PDO::PARAM_INT);
$stmt->execute();
$palmares = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$palmares) {
    echo "<script>
        alert('Palmarès non trouvé');
        window.location.href = '?view=academique/palmares';
    </script>";
    exit;
}

// Récupération des étudiants du palmarès
// Récupération des étudiants du palmarès
$queryEtudiants = "SELECT pe.*, 
                    CASE WHEN pe.pourcentage >= 50 AND pe.credit_obtenu = pe.credit_total 
                         THEN 1 ELSE 0 END as est_valide,
                    e.noms as nom_etudiant,
                    e.matricule as matricule_etudiant
                   FROM palmares_etudiant pe 
                   LEFT JOIN etudiant e ON pe.idetudiant = e.idetudiant
                   WHERE pe.id_palmares = :id_palmares
                   ORDER BY pe.pourcentage DESC, pe.nom_complet ASC";


$stmtEtudiants = $pdo->prepare($queryEtudiants);
$stmtEtudiants->bindParam(':id_palmares', $idPalmares, PDO::PARAM_INT);
$stmtEtudiants->execute();
$etudiants = $stmtEtudiants->fetchAll(PDO::FETCH_ASSOC);

// Récupération des informations de l'université
$universite = new Universite();
$configUniversite = $universite->getConfigurationUniversite();

// Fonction pour déterminer la mention (texte complet)
function mentionsReleve($pourcentage) {
    $mention = "";
    if ($pourcentage < 40)
        $mention = "Insatisfaisant";
    else if ($pourcentage >= 40 && $pourcentage < 50)
        $mention = "Insuffisant";
    else if ($pourcentage >= 50 && $pourcentage < 60)
        $mention = "Passable";
    else if ($pourcentage >= 60 && $pourcentage < 70)
        $mention = "Assez bien";
    else if ($pourcentage >= 70 && $pourcentage < 80)
        $mention = "Bien";
    else if ($pourcentage >= 80 && $pourcentage < 90)
        $mention = "Très bien";
    else if ($pourcentage >= 90)
        $mention = "Excellent";
    return $mention;
}


// Attribution des rangs aux étudiants
$rang = 1;
$moyennePrecedente = null;
$rangPrecedent = 1;

foreach ($etudiants as &$etudiant) {
    // Si la moyenne est identique à la précédente, garder le même rang
    if ($moyennePrecedente !== null && $etudiant['pourcentage'] == $moyennePrecedente) {
        $etudiant['rang'] = $rangPrecedent;
    } else {
        $etudiant['rang'] = $rang;
        $rangPrecedent = $rang;
    }
    
    $moyennePrecedente = $etudiant['pourcentage'];
    $rang++;
}

// Calcul des statistiques
$totalEtudiants = count($etudiants);
$etudiantsAdmis = count(array_filter($etudiants, function($e) { return $e['est_valide'] == 1; }));
$etudiantsAjournes = count(array_filter($etudiants, function($e) { return $e['est_valide'] == 0; }));
$tauxReussite = $totalEtudiants > 0 ? ($etudiantsAdmis / $totalEtudiants * 100) : 0;
$moyenneGenerale = $totalEtudiants > 0 ? (array_sum(array_column($etudiants, 'pourcentage')) / $totalEtudiants) : 0;

// Récupérer le chemin du logo
$logoPath = isset($configUniversite['logo']) && !empty($configUniversite['logo']) ? 
    $configUniversite['logo'] : '';

// Convertir le logo en base64 s'il existe
$logoBase64 = '';
if (!empty($logoPath) && file_exists($logoPath)) {
    $logoType = pathinfo($logoPath, PATHINFO_EXTENSION);
    $logoData = file_get_contents($logoPath);
    $logoBase64 = 'data:image/' . $logoType . ';base64,' . base64_encode($logoData);
}

// Générer le contenu HTML du Palmarès
$html = '
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Palmarès Académique - ' . htmlspecialchars($palmares['designation']) . '</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 11pt;
            line-height: 1.4;
        }
        
        .institution-header {
            margin-bottom: 1px;
            width: 100%;
            box-sizing: border-box;
        }
        
        .logo {
            float: left;
            max-width: 70px;
            max-height: 70px;
            margin-right: 15px;
        }
        
        .institution-info {
            margin-left: 10px;
            margin-top: -15px;
        }
        
        h1 {
            font-size: 16pt;
            margin: 5px 0;
            text-align: center;
        }
        
        h2 {
            font-size: 14pt;
            margin: 5px 0;
            text-align: center;
        }
        
        h3 {
            font-size: 12pt;
            margin: 10px 0;
        }
        
        p {
            margin: 5px 0;
            text-align: justify;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 15px 0;
        }
        
        th, td {
            border: 1px solid #000;
            padding: 5px;
            text-align: center;
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
        
        .signature {
            margin-top: 30px;
            text-align: center;
        }
        
        .footer {
            text-align: center;
            margin-top: 20px;
            font-size: 9pt;
        }
        
        .top-rank {
            background-color: #fffce6;
            font-weight: bold;
        }
        
        .medal-1 {
            background-color: #ffd700; /* Or */
        }
        
        .medal-2 {
            background-color: #c0c0c0; /* Argent */
        }
        
        .medal-3 {
            background-color: #cd7f32; /* Bronze */
        }
    </style>
</head>
<body>
    <div class="institution-header">
        ' . (isset($configUniversite['logo']) && !empty($configUniversite['logo']) ? 
        '<img src="' . $logoBase64 . '" class="logo" alt="Logo">' : '') . '
        <div class="institution-info">
            <div>' . htmlspecialchars($configUniversite['ministere_tutelle'] ?? 'ENSEIGNEMENT SUPERIEUR') . '</div>
            <div><strong>' . htmlspecialchars($configUniversite['nom'] ?? 'UNIVERSITÉ') . '</strong></div>
            <div>Tél: ' . htmlspecialchars($configUniversite['telephone'] ?? '') . ' | Email: ' . htmlspecialchars($configUniversite['email'] ?? '') . '</div>
            ' . (isset($configUniversite['site_web']) && !empty($configUniversite['site_web']) ? 
                '<div>Site web: ' . htmlspecialchars($configUniversite['site_web']) . '</div>' : '') . '
        </div>
    </div>
    <p>______________________________________________________________________________________________</p>

    <h1>PALMARÈS ACADÉMIQUE</h1>
    <h2>' . htmlspecialchars($palmares['designation']) . ' - ' . htmlspecialchars($palmares['session']) . '</h2>
    <h2>Année Académique: ' . htmlspecialchars($palmares['annee_academique']) . '</h2>
    
    <p>
        Le Jury de délibération, après examen des résultats, a établi le classement suivant pour la promotion
        ' . htmlspecialchars($palmares['promotion']) . ' pour l\'année académique
        ' . htmlspecialchars($palmares['annee_academique']) . '.
    </p>';

// Afficher les statistiques globales
$html .= '
    <h3>I. STATISTIQUES GLOBALES</h3>
    <table>
        <tr>
            <th>Total étudiants</th>
            <th>Étudiants admis</th>
            <th>Nombre d\'étudiants ajournés</th>
            <th>Taux de réussite</th>
            <th>Pourcentage Moyen</th>
        </tr>
        <tr>
            <td>' . $totalEtudiants . '</td>
            <td>' . $etudiantsAdmis . '</td>
            <td>' . $etudiantsAjournes . '</td>
            <td>' . number_format($tauxReussite, 2) . '%</td>
            <td>' . number_format($moyenneGenerale, 2) . '</td>
        </tr>
    </table>';

// Afficher le classement des étudiants
$html .= '
    <h3>II. CLASSEMENT PAR ORDRE DE MÉRITE</h3>
    
    <table>
        <tr>
            <th>Rang</th>
            <th>Matricule</th>
            <th>Nom et Prénom</th>
            <th>%</th>
            <th>Mention</th>
            <th>Crédits validés</th>
            <th>Décision</th>
        </tr>';

// Afficher les étudiants classés
foreach ($etudiants as $etudiant) {
    $rowClass = '';
    
    // Attribuer les classes pour le style visuel
    if (isset($etudiant['rang'])) {
        if ($etudiant['rang'] == 1) {
            $rowClass = 'medal-1';
        } else if ($etudiant['rang'] == 2) {
            $rowClass = 'medal-2';
        } else if ($etudiant['rang'] == 3) {
            $rowClass = 'medal-3';
        } else if ($etudiant['rang'] <= 10) {
            $rowClass = 'top-rank';
        }
    }
    
    // Déterminer la décision
    $decision = $etudiant['est_valide'] == 1 ? "ADMIS" : "AJOURNÉ";
    
    $html .= '
    <tr class="' . $rowClass . '">
        <td>' . $etudiant['rang'] . (($etudiant['rang'] <= 3) ? ' 🏆' : '') . '</td>
        <td>' . htmlspecialchars($etudiant['matricule']) . '</td>
        <td class="text-left">' . htmlspecialchars($etudiant['nom_complet']) . '</td>
        <td>' . number_format($etudiant['pourcentage'], 2) . '</td>
        <td>' . htmlspecialchars($etudiant['mention']) . '</td>
        <td>' . htmlspecialchars($etudiant['credit_obtenu'] . '/' . $etudiant['credit_total']) . '</td>
        <td>' . ($etudiant['est_valide'] == 1 ? "ADMIS" : "AJOURNÉ") . '</td>
    </tr>';

}

$html .= '
    </table>';

// Afficher la section des distinctions honorifiques
$html .= '
    <h3>III. DISTINCTIONS HONORIFIQUES</h3>';

// Major de promotion (1er)
if (!empty($etudiants)) {
    $major = $etudiants[0];
    $html .= '
    <p>
        <strong>Major de Promotion:</strong> ' . htmlspecialchars($major['nom_complet']) . ' (Matricule: ' . htmlspecialchars($major['matricule']) . ')
        avec un pourcentage de ' . number_format($major['pourcentage'], 2) . '/20, mention "' . htmlspecialchars($major['mention']) . '".
    </p>';
}

// Tableau d'honneur (les 5 premiers)
if (count($etudiants) >= 3) {
    $html .= '
    <p>
        <strong>Tableau d\'honneur:</strong> Les étudiants suivants sont inscrits au tableau d\'honneur de l\'établissement.
    </p>
    <table>
        <tr>
            <th>Rang</th>
            <th>Matricule</th>
            <th>Nom et Prénom</th>
            <th>%</th>
            <th>Mention</th>
        </tr>';
    
    // Afficher les 5 premiers étudiants (ou moins si moins de 5 au total)
    $topStudentsCount = min(5, count($etudiants));
    for ($i = 0; $i < $topStudentsCount; $i++) {
        $etudiant = $etudiants[$i];
        $html .= '
        <tr>
            <td>' . $etudiant['rang'] . '</td>
            <td>' . htmlspecialchars($etudiant['matricule']) . '</td>
            <td class="text-left">' . htmlspecialchars($etudiant['nom_complet']) . '</td>
            <td>' . number_format($etudiant['pourcentage'], 2) . '</td>
            <td>' . htmlspecialchars($etudiant['mention']) . '</td>
        </tr>';
    }
    
    $html .= '
    </table>';
}

// Conclusion et signatures
$html .= '
    <h3>IV. CERTIFICATION</h3>
    <p>
        Le présent palmarès est certifié exact et conforme aux procès-verbaux de délibération.
        Il a été établi sous la responsabilité du Jury académique pour la promotion ' . htmlspecialchars($palmares['promotion']) . '.
    </p>
    
    <div class="signature">
        <p>Fait à ________________, le ' . date('d/m/Y') . '</p>
        <p style="margin-top: 20px;">Le Président du Jury</p>
        <p style="margin-top: 40px;"><strong>' . htmlspecialchars($palmares['president_jury'] ?? 'Le Président') . '</strong></p>
    </div>
    
    <div class="footer">
        <p>Palmarès Académique - ' . htmlspecialchars($palmares['designation']) . ' - ' . htmlspecialchars($palmares['session']) . ' - ' . htmlspecialchars($palmares['annee_academique']) . '</p>
        <p>Imprimé par ' . htmlspecialchars($_SESSION['nom']) . ', le ' . date('d/m/Y') . '</p>
    </div>
</body>
</html>';

// Générer le PDF
try {
    $html2pdf = new Html2Pdf('P', 'A4', 'fr', true, 'UTF-8', array(10, 10, 10, 10));
    
    // D'abord écrire le HTML
    $html2pdf->writeHTML($html);
    
    // Accéder à l'objet TCPDF sous-jacent
    $pdf = $html2pdf->pdf;
    
    // Nombre de pages dans le document
    $numPages = $pdf->getNumPages();
    
    // Pour chaque page, ajouter le filigrane
    for ($i = 1; $i <= $numPages; $i++) {
        $pdf->setPage($i);
        
        // Dimensions de la page
        $pageWidth = $pdf->getPageWidth();
        $pageHeight = $pdf->getPageHeight();
        
        // Réduire la taille de l'image
        $imageWidth = $pageWidth * 0.3;
        $imageHeight = 0; // Proportionnel
        
        // Estimer la hauteur de l'image si elle est proportionnelle
        $estimatedHeight = $imageWidth * 0.90;
        
        // Position centrale, mais décalée vers le haut
        $x = ($pageWidth - $imageWidth) / 2;
        $y = ($pageHeight - $estimatedHeight) / 2 - 30;
        
        // Ajouter le filigrane avec une faible opacité
        if (!empty($logoBase64)) {
            $pdf->setAlpha(0.05);
            $pdf->Image('@'.base64_decode(str_replace('data:image/png;base64,', '', $logoBase64)), 
                    $x, $y, $imageWidth, $imageHeight, '', '', '', false, 300, '', false, false, 0);
        }
        
        // Ajouter le filigrane texte "DOCUMENT OFFICIEL"
        $pdf->StartTransform();
        $pdf->SetFont('helvetica', 'B', 40);
        $pdf->SetTextColor(200, 200, 200);
        $pdf->Rotate(45, $pageWidth/2, $pageHeight/2);
        $textWidth = $pdf->GetStringWidth("DOCUMENT OFFICIEL");
        $pdf->setAlpha(0.1);
        $pdf->Text($pageWidth/2 - $textWidth/2, $pageHeight/2, "DOCUMENT OFFICIEL");
        $pdf->StopTransform();
        $pdf->setAlpha(1);
    }

    // Générer le PDF
    $filename = 'Palmares_' . str_replace(' ', '_', $palmares['designation']) . '_' . date('Y-m-d') . '.pdf';
    $html2pdf->output($filename, 'I');
} catch (Html2PdfException $e) {
    echo "<div class='alert alert-danger'>Erreur lors de la génération du PDF: " . $e->getMessage() . "</div>";
}

