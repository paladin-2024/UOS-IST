<?php
session_start();
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/models/Agent.php';
require_once dirname(__DIR__) . '/models/Grade.php';
require_once dirname(__DIR__) . '/models/Service.php';
require_once dirname(__DIR__) . '/models/Structure.php';
require_once dirname(__DIR__) . '/models/Universite.php';
require_once dirname(__DIR__) . '/vendor/autoload.php';

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['id'])) {
    header('Location: ../index.php');
    exit;
}

// Vérifier si l'ID est fourni
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header('Location: ../index.php?page=grh/agent.liste');
    exit;
}

$idAgent = intval($_GET['id']);

// Initialiser les modèles
$agentModel = new Agent();
$gradeModel = new Grade();
$serviceModel = new Service();
$structureModel = new Structure();
$universiteModel = new Universite();

// Récupérer les données de l'agent
$agent = $agentModel->getAgentById($idAgent);
if (!$agent) {
    header('Location: ../index.php?page=grh/agent.liste');
    exit;
}

// Récupérer les formations et l'historique des grades
$formations = $agentModel->getFormationsForAgent($idAgent);
$gradesHistory = $agentModel->getGradeHistoryForAgent($idAgent);

// Récupérer les informations supplémentaires
$structure = $structureModel->getStructureById($agent['idStructure']);
$service = $serviceModel->getServiceById($agent['idService']);
$grade = $gradeModel->getGradeById($agent['grade_id']);

// Récupérer les informations de l'université
$configUniversite = $universiteModel->getConfigurationUniversite();

// Fonction pour rogner une image pour ne garder que le visage
function cropFaceFromImage($imagePath) {
    // Vérifier si l'extension GD est disponible
    if (!extension_loaded('gd')) {
        return $imagePath; // Retourner l'image originale si GD n'est pas disponible
    }
    
    // Créer un nom de fichier temporaire pour l'image rognée
    $tempFile = sys_get_temp_dir() . '/' . uniqid('face_') . '.jpg';
    
    // Déterminer le type d'image
    $imageInfo = getimagesize($imagePath);
    if (!$imageInfo) {
        return $imagePath;
    }
    
    // Créer une ressource d'image selon le type
    switch ($imageInfo[2]) {
        case IMAGETYPE_JPEG:
            $sourceImage = imagecreatefromjpeg($imagePath);
            break;
        case IMAGETYPE_PNG:
            $sourceImage = imagecreatefrompng($imagePath);
            break;
        case IMAGETYPE_GIF:
            $sourceImage = imagecreatefromgif($imagePath);
            break;
        default:
            return $imagePath; // Type d'image non supporté
    }
    
    if (!$sourceImage) {
        return $imagePath;
    }
    
    // Dimensions de l'image source
    $width = imagesx($sourceImage);
    $height = imagesy($sourceImage);
    
    // Définir la zone de rognage (ici, nous prenons le centre de l'image)
    // Pour un rognage plus précis, il faudrait utiliser une bibliothèque de détection faciale
    $cropSize = min($width, $height);
    $x = ($width - $cropSize) / 2;
    $y = ($height - $cropSize) / 4; // Décaler vers le haut pour mieux cadrer le visage
    
    // Créer une nouvelle image carrée
    $croppedImage = imagecreatetruecolor($cropSize, $cropSize);
    
    // Préserver la transparence pour les PNG
    if ($imageInfo[2] == IMAGETYPE_PNG) {
        imagealphablending($croppedImage, false);
        imagesavealpha($croppedImage, true);
        $transparent = imagecolorallocatealpha($croppedImage, 255, 255, 255, 127);
        imagefilledrectangle($croppedImage, 0, 0, $cropSize, $cropSize, $transparent);
    }
    
    // Copier et rogner l'image
    imagecopy($croppedImage, $sourceImage, 0, 0, $x, $y, $cropSize, $cropSize);
    
    // Enregistrer l'image rognée
    imagejpeg($croppedImage, $tempFile, 90);
    
    // Libérer la mémoire
    imagedestroy($sourceImage);
    imagedestroy($croppedImage);
    
    return $tempFile;
}

// Créer une instance de TCPDF
$pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);

// Définir les informations du document
$pdf->SetCreator('Système de gestion universitaire');
$pdf->SetAuthor($configUniversite['nom'] ?? 'Administration');
$pdf->SetTitle('Fiche du Personnel - ' . $agent['noms']);
$pdf->SetSubject('Fiche individuelle');
$pdf->SetKeywords('Agent, Personnel, Fiche');

// Supprimer les en-têtes et pieds de page par défaut
$pdf->setPrintHeader(false);
$pdf->setPrintFooter(false);

// Définir les marges
$pdf->SetMargins(20, 20, 20);
$pdf->SetAutoPageBreak(true, 25);

// Couleurs pour le design
$primaryColor = array(0, 87, 146); // Bleu foncé
$secondaryColor = array(70, 130, 180); // Bleu acier
$accentColor = array(0, 121, 194); // Bleu moyen

// Ajouter une page
$pdf->AddPage();

// Ajouter le logo en filigrane
if (!empty($configUniversite['logo'])) {
    $logoPath = dirname(__DIR__) . '/' . $configUniversite['logo'];
    if (file_exists($logoPath)) {
        // Sauvegarder l'état actuel
        $pdf->setAlpha(0.1);
        
        // Position au centre
        $pageWidth = $pdf->getPageWidth();
        $pageHeight = $pdf->getPageHeight();
        
        // Définir une largeur plus petite mais conserver la même hauteur
        $logoWidth = 70; // Largeur réduite
        $logoHeight = 100; // Hauteur inchangée
        
        $x = ($pageWidth - $logoWidth) / 2;
        $y = ($pageHeight - $logoHeight) / 2;
        
        // Ajouter l'image en filigrane avec largeur réduite
        $pdf->Image($logoPath, $x, $y, $logoWidth, $logoHeight, '', '', '', false, 300, '', false, false, 0);
        
        // Restaurer l'état
        $pdf->setAlpha(1);
    }
}

// En-tête avec les informations de l'université
if ($configUniversite) {
    // Logo de l'université (visible)
    if (!empty($configUniversite['logo'])) {
        $logoPath = dirname(__DIR__) . '/' . $configUniversite['logo'];
        if (file_exists($logoPath)) {
            $pdf->Image($logoPath, 20, 15, 20, 0, '', '', '', false, 200, '', false, false, 0);
        }
    }
    
    // Titre et informations de l'université - CORRECTION: Centrer correctement
    $pdf->SetTextColor($primaryColor[0], $primaryColor[1], $primaryColor[2]);
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->SetY(15);
    $pdf->Cell(0, 8, strtoupper($configUniversite['ministere_tutelle'] ?? ''), 0, 1, 'C');
    
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->Cell(0, 8, strtoupper($configUniversite['nom'] ?? ''), 0, 1, 'C');
    
    if (!empty($configUniversite['sigle'])) {
        $pdf->SetFont('helvetica', 'B', 12);
        $pdf->Cell(0, 6, $configUniversite['sigle'], 0, 1, 'C');
    }
    
    $pdf->SetFont('helvetica', '', 9);
    $pdf->SetTextColor(80, 80, 80);
    if (!empty($configUniversite['adresse'])) {
        $pdf->Cell(0, 4, $configUniversite['adresse'], 0, 1, 'C');
    }
    
    $contactInfo = '';
    if (!empty($configUniversite['telephone'])) {
        $contactInfo .= 'Tél: ' . $configUniversite['telephone'] . ' ';
    }
    if (!empty($configUniversite['email'])) {
        $contactInfo .= 'Email: ' . $configUniversite['email'] . ' ';
    }
    if (!empty($configUniversite['site_web'])) {
        $contactInfo .= 'Web: ' . $configUniversite['site_web'];
    }
    
    if (!empty($contactInfo)) {
        $pdf->Cell(0, 4, $contactInfo, 0, 1, 'C');
    }
    
    // Ligne de séparation
    $pdf->SetLineStyle(array('width' => 0.5, 'cap' => 'butt', 'join' => 'miter', 'dash' => 0, 'color' => $accentColor));
    $pdf->Line(15, 48, $pdf->getPageWidth() - 15, 48);
}

// Photo de l'agent (rognée) - positionnée à droite
if (!empty($agent['photo']) && file_exists(dirname(__DIR__) . '/uploads/agents/' . $agent['photo'])) {
    $photoPath = dirname(__DIR__) . '/uploads/agents/' . $agent['photo'];
    $croppedPhotoPath = cropFaceFromImage($photoPath);
    
    // Positionner la photo en haut à droite avec un cadre
    $pdf->SetLineStyle(array('width' => 0.5, 'cap' => 'butt', 'join' => 'miter', 'dash' => 0, 'color' => $accentColor));
    $pdf->RoundedRect($pdf->getPageWidth() - 40, 15, 30, 30, 2, '1111', 'DF', array(), array(245, 245, 245));
    $pdf->Image($croppedPhotoPath, $pdf->getPageWidth() - 38, 17, 26, 26, '', '', '', false, 300, '', false, false, 1);
    
    // Supprimer le fichier temporaire si créé
    if ($croppedPhotoPath != $photoPath) {
        @unlink($croppedPhotoPath);
    }
}

// Titre du document avec fond coloré
$pdf->SetFillColor($primaryColor[0], $primaryColor[1], $primaryColor[2]);
$pdf->SetTextColor(255, 255, 255);
$pdf->SetFont('helvetica', 'B', 16);
$pdf->Ln(15);
$pdf->Cell(0, 10, 'FICHE INDIVIDUELLE DU PERSONNEL', 0, 1, 'C', 1);

$pdf->SetTextColor(80, 80, 80);
$pdf->SetFont('helvetica', 'B', 10);
$pdf->Cell(0, 8, 'N° ' . sprintf('%04d', $idAgent) . '/' . date('Y'), 0, 1, 'C');

// Définir la largeur des colonnes pour s'adapter à la page
$col1Width = 60;
$col2Width = $pdf->getPageWidth() - $pdf->getMargins()['left'] - $pdf->getMargins()['right'] - $col1Width;

// Informations générales
$pdf->SetTextColor($secondaryColor[0], $secondaryColor[1], $secondaryColor[2]);
$pdf->SetFont('helvetica', 'B', 12);
$pdf->Cell(0, 10, 'INFORMATIONS GÉNÉRALES', 0, 1, 'L');

// Ligne décorative sous le titre de section
$pdf->SetLineStyle(array('width' => 0.2, 'cap' => 'butt', 'join' => 'miter', 'dash' => 0, 'color' => $secondaryColor));
$pdf->Line(20, $pdf->GetY(), 100, $pdf->GetY());
$pdf->Ln(2);

// Style pour les cellules de tableau
$pdf->SetFillColor(245, 245, 245);
$pdf->SetDrawColor($accentColor[0], $accentColor[1], $accentColor[2]);
$pdf->SetTextColor(60, 60, 60);
$pdf->SetFont('helvetica', 'B', 11);

$pdf->Cell($col1Width, 8, 'Code Agent:', 1, 0, 'L', 1);
$pdf->SetFont('helvetica', '', 11);
$pdf->Cell($col2Width, 8, $agent['codeAgent'], 1, 1, 'L', 0);

$pdf->SetFont('helvetica', 'B', 11);
$pdf->Cell($col1Width, 8, 'Matricule:', 1, 0, 'L', 1);
$pdf->SetFont('helvetica', '', 11);
$pdf->Cell($col2Width, 8, $agent['matricule'], 1, 1, 'L', 0);

$pdf->SetFont('helvetica', 'B', 11);
$pdf->Cell($col1Width, 8, 'Noms & Prénoms:', 1, 0, 'L', 1);
$pdf->SetFont('helvetica', '', 11);
$pdf->Cell($col2Width, 8, $agent['noms'], 1, 1, 'L', 0);

$pdf->SetFont('helvetica', 'B', 11);
$pdf->Cell($col1Width, 8, 'Type d\'agent:', 1, 0, 'L', 1);
$pdf->SetFont('helvetica', '', 11);
$pdf->Cell($col2Width, 8, $agent['type_agent'], 1, 1, 'L', 0);

// Informations personnelles
$pdf->Ln(5);
$pdf->SetTextColor($secondaryColor[0], $secondaryColor[1], $secondaryColor[2]);
$pdf->SetFont('helvetica', 'B', 12);
$pdf->Cell(0, 10, 'INFORMATIONS PERSONNELLES', 0, 1, 'L');

// Ligne décorative sous le titre de section
$pdf->SetLineStyle(array('width' => 0.2, 'cap' => 'butt', 'join' => 'miter', 'dash' => 0, 'color' => $secondaryColor));
$pdf->Line(20, $pdf->GetY(), 100, $pdf->GetY());
$pdf->Ln(2);

$pdf->SetTextColor(60, 60, 60);
$pdf->SetFont('helvetica', 'B', 11);

$pdf->Cell($col1Width, 8, 'Sexe:', 1, 0, 'L', 1);
$pdf->SetFont('helvetica', '', 11);
$pdf->Cell($col2Width, 8, ($agent['sexe'] == 'M' ? 'Masculin' : 'Féminin'), 1, 1, 'L', 0);

$pdf->SetFont('helvetica', 'B', 11);
$pdf->Cell($col1Width, 8, 'Date de naissance:', 1, 0, 'L', 1);
$pdf->SetFont('helvetica', '', 11);
$pdf->Cell($col2Width, 8, date('d/m/Y', strtotime($agent['dateNaissance'])), 1, 1, 'L', 0);

$pdf->SetFont('helvetica', 'B', 11);
$pdf->Cell($col1Width, 8, 'Lieu de naissance:', 1, 0, 'L', 1);
$pdf->SetFont('helvetica', '', 11);
$pdf->Cell($col2Width, 8, $agent['lieuNaissance'], 1, 1, 'L', 0);

$pdf->SetFont('helvetica', 'B', 11);
$pdf->Cell($col1Width, 8, 'État civil:', 1, 0, 'L', 1);
$pdf->SetFont('helvetica', '', 11);
$pdf->Cell($col2Width, 8, $agent['etatCivil'], 1, 1, 'L', 0);

if (!empty($agent['conjoint'])) {
    $pdf->SetFont('helvetica', 'B', 11);
    $pdf->Cell($col1Width, 8, 'Conjoint(e):', 1, 0, 'L', 1);
    $pdf->SetFont('helvetica', '', 11);
    $pdf->Cell($col2Width, 8, $agent['conjoint'], 1, 1, 'L', 0);
}

$adresse = [];
if (!empty($agent['adresse_avenue'])) $adresse[] = 'Av. ' . $agent['adresse_avenue'];
if (!empty($agent['adresse_quartier'])) $adresse[] = 'Q. ' . $agent['adresse_quartier'];
if (!empty($agent['adresse_commune'])) $adresse[] = 'C. ' . $agent['adresse_commune'];

if (!empty($adresse)) {
    $pdf->SetFont('helvetica', 'B', 11);
    $pdf->Cell($col1Width, 8, 'Adresse:', 1, 0, 'L', 1);
    $pdf->SetFont('helvetica', '', 11);
    $pdf->Cell($col2Width, 8, implode(', ', $adresse), 1, 1, 'L', 0);
}

$pdf->SetFont('helvetica', 'B', 11);
$pdf->Cell($col1Width, 8, 'Téléphone:', 1, 0, 'L', 1);
$pdf->SetFont('helvetica', '', 11);
$pdf->Cell($col2Width, 8, $agent['telephone'], 1, 1, 'L', 0);

if (!empty($agent['email'])) {
    $pdf->SetFont('helvetica', 'B', 11);
    $pdf->Cell($col1Width, 8, 'Email:', 1, 0, 'L', 1);
    $pdf->SetFont('helvetica', '', 11);
    $pdf->Cell($col2Width, 8, $agent['email'], 1, 1, 'L', 0);
}

// Informations professionnelles
$pdf->Ln(5);
$pdf->SetTextColor($secondaryColor[0], $secondaryColor[1], $secondaryColor[2]);
$pdf->SetFont('helvetica', 'B', 12);
$pdf->Cell(0, 10, 'INFORMATIONS PROFESSIONNELLES', 0, 1, 'L');


$pdf->Ln(2);

$pdf->SetTextColor(60, 60, 60);
$pdf->SetFont('helvetica', 'B', 11);

$pdf->Cell($col1Width, 8, 'Niveau d\'étude:', 1, 0, 'L', 1);
$pdf->SetFont('helvetica', '', 11);
$pdf->Cell($col2Width, 8, $agent['niveauEtude'], 1, 1, 'L', 0);

$pdf->SetFont('helvetica', 'B', 11);
$pdf->Cell($col1Width, 8, 'Grade actuel:', 1, 0, 'L', 1);
$pdf->SetFont('helvetica', '', 11);
$pdf->Cell($col2Width, 8, $grade['description'] ?? 'Non défini', 1, 1, 'L', 0);

$pdf->SetFont('helvetica', 'B', 11);
$pdf->Cell($col1Width, 8, 'Campus:', 1, 0, 'L', 1);
$pdf->SetFont('helvetica', '', 11);
$pdf->Cell($col2Width, 8, $structure['designation'] ?? 'Non défini', 1, 1, 'L', 0);

$pdf->SetFont('helvetica', 'B', 11);
$pdf->Cell($col1Width, 8, 'Service:', 1, 0, 'L', 1);
$pdf->SetFont('helvetica', '', 11);
$pdf->Cell($col2Width, 8, $service['designationService'] ?? 'Non défini', 1, 1, 'L', 0);

if (!empty($agent['annee_engagement'])) {
    $pdf->SetFont('helvetica', 'B', 11);
    $pdf->Cell($col1Width, 8, 'Année d\'engagement:', 1, 0, 'L', 1);
    $pdf->SetFont('helvetica', '', 11);
    $pdf->Cell($col2Width, 8, $agent['annee_engagement'], 1, 1, 'L', 0);
}

if (!empty($agent['reference_acte_engagement'])) {
    $pdf->SetFont('helvetica', 'B', 11);
    $pdf->Cell($col1Width, 8, 'Référence acte:', 1, 0, 'L', 1);
    $pdf->SetFont('helvetica', '', 11);
    $pdf->Cell($col2Width, 8, $agent['reference_acte_engagement'], 1, 1, 'L', 0);
}

// Options de paiement
$pdf->Ln(5);
$pdf->SetTextColor($secondaryColor[0], $secondaryColor[1], $secondaryColor[2]);
$pdf->SetFont('helvetica', 'B', 12);
$pdf->Cell(0, 10, 'OPTIONS DE PAIEMENT', 0, 1, 'L');

// Ligne décorative sous le titre de section
$pdf->SetLineStyle(array('width' => 0.2, 'cap' => 'butt', 'join' => 'miter', 'dash' => 0, 'color' => $secondaryColor));
$pdf->Line(20, $pdf->GetY(), 100, $pdf->GetY());
$pdf->Ln(2);

$pdf->SetTextColor(60, 60, 60);
$pdf->SetFont('helvetica', 'B', 11);

$pdf->Cell($col1Width, 8, 'Prime locale:', 1, 0, 'L', 1);
$pdf->SetFont('helvetica', '', 11);
$pdf->Cell($col2Width, 8, ($agent['prime_locale'] == 1 ? 'Oui' : 'Non'), 1, 1, 'L', 0);

$pdf->SetFont('helvetica', 'B', 11);
$pdf->Cell($col1Width, 8, 'Salaire de base de l\'état:', 1, 0, 'L', 1);
$pdf->SetFont('helvetica', '', 11);
$pdf->Cell($col2Width, 8, ($agent['salaire_etat'] == 1 ? 'Oui' : 'Non'), 1, 1, 'L', 0);

$pdf->SetFont('helvetica', 'B', 11);
$pdf->Cell($col1Width, 8, 'Prime institutionnelle:', 1, 0, 'L', 1);
$pdf->SetFont('helvetica', '', 11);
$pdf->Cell($col2Width, 8, ($agent['prime_institutionnelle'] == 1 ? 'Oui' : 'Non'), 1, 1, 'L', 0);

// CORRECTION: Ne pas ajouter de nouvelle page pour les formations
// Vérifier s'il reste assez d'espace pour les formations
$spaceNeeded = 20; // Hauteur estimée pour le titre et l'en-tête du tableau
if (!empty($formations)) {
    $spaceNeeded += count($formations) * 8; // Hauteur estimée pour chaque ligne de formation
}

// Si l'espace restant est insuffisant, alors ajouter une nouvelle page
if ($pdf->GetY() + $spaceNeeded > $pdf->getPageHeight() - 30) {
    $pdf->AddPage();
    
    // Ajouter le logo en filigrane sur la nouvelle page
    if (!empty($configUniversite['logo'])) {
        $logoPath = dirname(__DIR__) . '/' . $configUniversite['logo'];
        if (file_exists($logoPath)) {
            $pdf->setAlpha(0.1);
            $pageWidth = $pdf->getPageWidth();
            $pageHeight = $pdf->getPageHeight();
            $logoWidth = 70;
            $logoHeight = 100;
            $x = ($pageWidth - $logoWidth) / 2;
            $y = ($pageHeight - $logoHeight) / 2;
            $pdf->Image($logoPath, $x, $y, $logoWidth, $logoHeight, '', '', '', false, 300, '', false, false, 0);
            $pdf->setAlpha(1);
        }
    }
} else {
    $pdf->Ln(5);
}

// Formations
if (!empty($formations)) {
    $pdf->SetTextColor($secondaryColor[0], $secondaryColor[1], $secondaryColor[2]);
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->Cell(0, 10, 'FORMATIONS ACADÉMIQUES', 0, 1, 'L');
    
    // Ligne décorative sous le titre de section
    $pdf->SetLineStyle(array('width' => 0.2, 'cap' => 'butt', 'join' => 'miter', 'dash' => 0, 'color' => $secondaryColor));
    $pdf->Line(20, $pdf->GetY(), 100, $pdf->GetY());
    $pdf->Ln(2);
    
    // Ajuster les largeurs des colonnes pour le tableau des formations
    $colNiveau = 40;
    $colEtablissement = 50;
    $colFiliere = 50;
    $colAnnee = 30;
    
    $pdf->SetFillColor($primaryColor[0], $primaryColor[1], $primaryColor[2]);
    $pdf->SetTextColor(255, 255, 255);
    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->Cell($colNiveau, 8, 'Niveau', 1, 0, 'C', 1);
    $pdf->Cell($colEtablissement, 8, 'Établissement', 1, 0, 'C', 1);
    $pdf->Cell($colFiliere, 8, 'Filière', 1, 0, 'C', 1);
    $pdf->Cell($colAnnee, 8, 'Année', 1, 1, 'C', 1);
    
    $pdf->SetTextColor(60, 60, 60);
    $pdf->SetFont('helvetica', '', 10);
    
    $rowCount = 0;
    // Dans la section des formations, remplacer le code existant par:
foreach ($formations as $formation) {
    // Alterner les couleurs des lignes
    $fill = ($rowCount % 2 == 0) ? 0 : 1;
    $rowCount++;
    
    // Vérifier si on a besoin d'un saut de page
    if ($pdf->GetY() > $pdf->getPageHeight() - 30) {
        $pdf->AddPage();
        
        // Réafficher l'en-tête du tableau
        $pdf->SetFillColor($primaryColor[0], $primaryColor[1], $primaryColor[2]);
        $pdf->SetTextColor(255, 255, 255);
        $pdf->SetFont('helvetica', 'B', 10);
        $pdf->Cell($colNiveau, 8, 'Niveau', 1, 0, 'C', 1);
        $pdf->Cell($colEtablissement, 8, 'Établissement', 1, 0, 'C', 1);
        $pdf->Cell($colFiliere, 8, 'Filière', 1, 0, 'C', 1);
        $pdf->Cell($colAnnee, 8, 'Année', 1, 1, 'C', 1);
        $pdf->SetTextColor(60, 60, 60);
        $pdf->SetFont('helvetica', '', 10);
    }
    
    $pdf->SetFillColor(245, 245, 245);
    if ($fill) {
        $pdf->SetFillColor(235, 235, 235);
    }
    
    // Utiliser Cell au lieu de MultiCell pour maintenir l'alignement horizontal
    $pdf->Cell($colNiveau, 8, $formation['niveau'], 1, 0, 'L', $fill);
    $pdf->Cell($colEtablissement, 8, $formation['etablissement'], 1, 0, 'L', $fill);
    $pdf->Cell($colFiliere, 8, $formation['filiere'], 1, 0, 'L', $fill);
    $pdf->Cell($colAnnee, 8, $formation['annee_obtention'], 1, 1, 'C', $fill);
}

}

// Historique des grades
// CORRECTION: Vérifier s'il reste assez d'espace pour l'historique des grades
$spaceNeeded = 20; // Hauteur estimée pour le titre et l'en-tête du tableau
if (!empty($gradesHistory)) {
    $spaceNeeded += count($gradesHistory) * 8; // Hauteur estimée pour chaque ligne de grade
}

// Si l'espace restant est insuffisant, alors ajouter une nouvelle page
if ($pdf->GetY() + $spaceNeeded > $pdf->getPageHeight() - 30) {
    $pdf->AddPage();
    
    // Ajouter le logo en filigrane sur la nouvelle page
    if (!empty($configUniversite['logo'])) {
        $logoPath = dirname(__DIR__) . '/' . $configUniversite['logo'];
        if (file_exists($logoPath)) {
            $pdf->setAlpha(0.1);
            $pageWidth = $pdf->getPageWidth();
            $pageHeight = $pdf->getPageHeight();
            $logoWidth = 70;
            $logoHeight = 100;
            $x = ($pageWidth - $logoWidth) / 2;
            $y = ($pageHeight - $logoHeight) / 2;
            $pdf->Image($logoPath, $x, $y, $logoWidth, $logoHeight, '', '', '', false, 300, '', false, false, 0);
            $pdf->setAlpha(1);
        }
    }
} else {
    $pdf->Ln(5);
}

if (!empty($gradesHistory)) {
    $pdf->SetTextColor($secondaryColor[0], $secondaryColor[1], $secondaryColor[2]);
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->Cell(0, 10, 'HISTORIQUE DES GRADES', 0, 1, 'L');
    
    // Ligne décorative sous le titre de section
    $pdf->SetLineStyle(array('width' => 0.2, 'cap' => 'butt', 'join' => 'miter', 'dash' => 0, 'color' => $secondaryColor));
    $pdf->Line(20, $pdf->GetY(), 100, $pdf->GetY());
    $pdf->Ln(2);
    
    // Ajuster les largeurs des colonnes pour le tableau des grades
    $colGrade = 35;
    $colDate = 35;
    $colDecision = 50;
    $colNotification = 50;
    
    $pdf->SetFillColor($primaryColor[0], $primaryColor[1], $primaryColor[2]);
    $pdf->SetTextColor(255, 255, 255);
    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->Cell($colGrade, 8, 'Grade', 1, 0, 'C', 1);
    $pdf->Cell($colDate, 8, 'Date de promotion', 1, 0, 'C', 1);
    $pdf->Cell($colDecision, 8, 'Référence décision', 1, 0, 'C', 1);
    $pdf->Cell($colNotification, 8, 'Référence notification', 1, 1, 'C', 1);
    
    $pdf->SetTextColor(60, 60, 60);
    $pdf->SetFont('helvetica', '', 10);
    
    $rowCount = 0;
    foreach ($gradesHistory as $gradeHistory) {
        // Alterner les couleurs des lignes
        $fill = ($rowCount % 2 == 0) ? 0 : 1;
        $rowCount++;
        
        // Vérifier si on a besoin d'un saut de page
        if ($pdf->GetY() > $pdf->getPageHeight() - 30) {
            $pdf->AddPage();
            
            // Ajouter le logo en filigrane sur la nouvelle page
            if (!empty($configUniversite['logo'])) {
                $logoPath = dirname(__DIR__) . '/' . $configUniversite['logo'];
                if (file_exists($logoPath)) {
                    $pdf->setAlpha(0.1);
                    $pageWidth = $pdf->getPageWidth();
                    $pageHeight = $pdf->getPageHeight();
                    $logoWidth = 70;
                    $logoHeight = 100;
                    $x = ($pageWidth - $logoWidth) / 2;
                    $y = ($pageHeight - $logoHeight) / 2;
                    $pdf->Image($logoPath, $x, $y, $logoWidth, $logoHeight, '', '', '', false, 300, '', false, false, 0);
                    $pdf->setAlpha(1);
                }
            }
            
            $pdf->SetFillColor($primaryColor[0], $primaryColor[1], $primaryColor[2]);
            $pdf->SetTextColor(255, 255, 255);
            $pdf->SetFont('helvetica', 'B', 10);
            $pdf->Cell($colGrade, 8, 'Grade', 1, 0, 'C', 1);
            $pdf->Cell($colDate, 8, 'Date de promotion', 1, 0, 'C', 1);
            $pdf->Cell($colDecision, 8, 'Référence décision', 1, 0, 'C', 1);
            $pdf->Cell($colNotification, 8, 'Référence notification', 1, 1, 'C', 1);
            $pdf->SetTextColor(60, 60, 60);
            $pdf->SetFont('helvetica', '', 10);
        }
        
        // Récupérer le nom du grade
        $gradeInfo = $gradeModel->getGradeById($gradeHistory['idgrade']);
        
        // Utiliser MultiCell pour gérer les textes longs
        $startY = $pdf->GetY();
        $currentPage = $pdf->getPage();
        
        $pdf->SetFillColor(245, 245, 245);
        if ($fill) {
            $pdf->SetFillColor(235, 235, 235);
        }
        
        $pdf->MultiCell($colGrade, 8, $gradeInfo['designation'] ?? 'Non défini', 1, 'L', $fill, 0, '', '', true, 0, false, true, 0);
        $pdf->setPage($currentPage);
        
        $pdf->MultiCell($colDate, 8, date('d/m/Y', strtotime($gradeHistory['date_promotion'])), 1, 'C', $fill, 0, $pdf->GetX(), $startY, true, 0, false, true, 0);
        $pdf->setPage($currentPage);
        
        $pdf->MultiCell($colDecision, 8, $gradeHistory['reference_decision'] ?? '', 1, 'L', $fill, 0, $pdf->GetX(), $startY, true, 0, false, true, 0);
        $pdf->setPage($currentPage);
        
        $pdf->MultiCell($colNotification, 8, $gradeHistory['reference_notification'] ?? '', 1, 'L', $fill, 1, $pdf->GetX(), $startY, true, 0, false, true, 0);
    }
}

// Informations spécifiques selon le type d'agent
if ($agent['type_agent'] == 'Administratif') {
    // Récupérer les informations administratives
    $queryAdmin = "SELECT * FROM admin_info WHERE idAgent = :idAgent";
    $stmtAdmin = Connexion::getInstance()->getPDO()->prepare($queryAdmin);
    $stmtAdmin->bindParam(':idAgent', $idAgent, PDO::PARAM_INT);
    $stmtAdmin->execute();
    $adminInfo = $stmtAdmin->fetch(PDO::FETCH_ASSOC);
    
    if ($adminInfo) {
        // Vérifier s'il reste assez d'espace
        if ($pdf->GetY() > $pdf->getPageHeight() - 50) {
            $pdf->AddPage();
            
            // Ajouter le logo en filigrane sur la nouvelle page
            if (!empty($configUniversite['logo'])) {
                $logoPath = dirname(__DIR__) . '/' . $configUniversite['logo'];
                if (file_exists($logoPath)) {
                    $pdf->setAlpha(0.1);
                    $pageWidth = $pdf->getPageWidth();
                    $pageHeight = $pdf->getPageHeight();
                    $logoWidth = 70;
                    $logoHeight = 100;
                    $x = ($pageWidth - $logoWidth) / 2;
                    $y = ($pageHeight - $logoHeight) / 2;
                    $pdf->Image($logoPath, $x, $y, $logoWidth, $logoHeight, '', '', '', false, 300, '', false, false, 0);
                    $pdf->setAlpha(1);
                }
            }
        } else {
            $pdf->Ln(5);
        }
        
        $pdf->SetTextColor($secondaryColor[0], $secondaryColor[1], $secondaryColor[2]);
        $pdf->SetFont('helvetica', 'B', 12);
        $pdf->Cell(0, 10, 'INFORMATIONS ADMINISTRATIVES', 0, 1, 'L');
        
        // Ligne décorative sous le titre de section
        $pdf->SetLineStyle(array('width' => 0.2, 'cap' => 'butt', 'join' => 'miter', 'dash' => 0, 'color' => $secondaryColor));
        $pdf->Line(20, $pdf->GetY(), 100, $pdf->GetY());
        $pdf->Ln(2);
        
        $pdf->SetTextColor(60, 60, 60);
        $pdf->SetFont('helvetica', 'B', 11);
        
        if (!empty($adminInfo['direction'])) {
            $pdf->Cell($col1Width, 8, 'Direction:', 1, 0, 'L', 1);
            $pdf->SetFont('helvetica', '', 11);
            $pdf->Cell($col2Width, 8, $adminInfo['direction'], 1, 1, 'L', 0);
            $pdf->SetFont('helvetica', 'B', 11);
        }
        
        if (!empty($adminInfo['division'])) {
            $pdf->Cell($col1Width, 8, 'Division:', 1, 0, 'L', 1);
            $pdf->SetFont('helvetica', '', 11);
            $pdf->Cell($col2Width, 8, $adminInfo['division'], 1, 1, 'L', 0);
            $pdf->SetFont('helvetica', 'B', 11);
        }
    }
} elseif ($agent['type_agent'] == 'Enseignant') {
    // Récupérer les informations d'enseignant
    $queryTeacher = "SELECT * FROM teacher_info WHERE idAgent = :idAgent";
    $stmtTeacher = Connexion::getInstance()->getPDO()->prepare($queryTeacher);
    $stmtTeacher->bindParam(':idAgent', $idAgent, PDO::PARAM_INT);
    $stmtTeacher->execute();
    $teacherInfo = $stmtTeacher->fetch(PDO::FETCH_ASSOC);
    
    if ($teacherInfo) {
        // Vérifier s'il reste assez d'espace
        if ($pdf->GetY() > $pdf->getPageHeight() - 50) {
            $pdf->AddPage();
            
            // Ajouter le logo en filigrane sur la nouvelle page
            if (!empty($configUniversite['logo'])) {
                $logoPath = dirname(__DIR__) . '/' . $configUniversite['logo'];
                if (file_exists($logoPath)) {
                    $pdf->setAlpha(0.1);
                    $pageWidth = $pdf->getPageWidth();
                    $pageHeight = $pdf->getPageHeight();
                    $logoWidth = 70;
                    $logoHeight = 100;
                    $x = ($pageWidth - $logoWidth) / 2;
                    $y = ($pageHeight - $logoHeight) / 2;
                    $pdf->Image($logoPath, $x, $y, $logoWidth, $logoHeight, '', '', '', false, 300, '', false, false, 0);
                    $pdf->setAlpha(1);
                }
            }
        } else {
            $pdf->Ln(5);
        }
        
        $pdf->SetTextColor($secondaryColor[0], $secondaryColor[1], $secondaryColor[2]);
        $pdf->SetFont('helvetica', 'B', 12);
        $pdf->Cell(0, 10, 'INFORMATIONS ENSEIGNANT', 0, 1, 'L');
        
        // Ligne décorative sous le titre de section
        $pdf->SetLineStyle(array('width' => 0.2, 'cap' => 'butt', 'join' => 'miter', 'dash' => 0, 'color' => $secondaryColor));
        $pdf->Line(20, $pdf->GetY(), 100, $pdf->GetY());
        $pdf->Ln(2);
        
        $pdf->SetTextColor(60, 60, 60);
        $pdf->SetFont('helvetica', 'B', 11);
        
        if (!empty($teacherInfo['specialisation'])) {
            $pdf->Cell($col1Width, 8, 'Spécialisation:', 1, 0, 'L', 1);
            $pdf->SetFont('helvetica', '', 11);
            $pdf->Cell($col2Width, 8, $teacherInfo['specialisation'], 1, 1, 'L', 0);
            $pdf->SetFont('helvetica', 'B', 11);
        }
        
        if (!empty($teacherInfo['domaine_recherche'])) {
            $pdf->Cell($col1Width, 8, 'Domaine de recherche:', 1, 0, 'L', 1);
            $pdf->SetFont('helvetica', '', 11);
            $pdf->Cell($col2Width, 8, $teacherInfo['domaine_recherche'], 1, 1, 'L', 0);
            $pdf->SetFont('helvetica', 'B', 11);
        }
    }
} elseif ($agent['type_agent'] == 'Recherche') {
    // Récupérer les informations de recherche
    $queryResearch = "SELECT * FROM research_info WHERE idAgent = :idAgent";
    $stmtResearch = Connexion::getInstance()->getPDO()->prepare($queryResearch);
    $stmtResearch->bindParam(':idAgent', $idAgent, PDO::PARAM_INT);
    $stmtResearch->execute();
    $researchInfo = $stmtResearch->fetch(PDO::FETCH_ASSOC);
    
    if ($researchInfo) {
        // Vérifier s'il reste assez d'espace
        if ($pdf->GetY() > $pdf->getPageHeight() - 50) {
            $pdf->AddPage();
            
            // Ajouter le logo en filigrane sur la nouvelle page
            if (!empty($configUniversite['logo'])) {
                $logoPath = dirname(__DIR__) . '/' . $configUniversite['logo'];
                if (file_exists($logoPath)) {
                    $pdf->setAlpha(0.1);
                    $pageWidth = $pdf->getPageWidth();
                    $pageHeight = $pdf->getPageHeight();
                    $logoWidth = 70;
                    $logoHeight = 100;
                    $x = ($pageWidth - $logoWidth) / 2;
                    $y = ($pageHeight - $logoHeight) / 2;
                    $pdf->Image($logoPath, $x, $y, $logoWidth, $logoHeight, '', '', '', false, 300, '', false, false, 0);
                    $pdf->setAlpha(1);
                }
            }
        } else {
            $pdf->Ln(5);
        }
        
        $pdf->SetTextColor($secondaryColor[0], $secondaryColor[1], $secondaryColor[2]);
        $pdf->SetFont('helvetica', 'B', 12);
        $pdf->Cell(0, 10, 'INFORMATIONS RECHERCHE', 0, 1, 'L');
        
        // Ligne décorative sous le titre de section
        $pdf->SetLineStyle(array('width' => 0.2, 'cap' => 'butt', 'join' => 'miter', 'dash' => 0, 'color' => $secondaryColor));
        $pdf->Line(20, $pdf->GetY(), 100, $pdf->GetY());
        $pdf->Ln(2);
        
        $pdf->SetTextColor(60, 60, 60);
        $pdf->SetFont('helvetica', 'B', 11);
        
        if (!empty($researchInfo['unite_recherche'])) {
            $pdf->Cell($col1Width, 8, 'Unité de recherche:', 1, 0, 'L', 1);
            $pdf->SetFont('helvetica', '', 11);
            $pdf->Cell($col2Width, 8, $researchInfo['unite_recherche'], 1, 1, 'L', 0);
            $pdf->SetFont('helvetica', 'B', 11);
        }
        
        if (!empty($researchInfo['projet_recherche'])) {
            $pdf->Cell($col1Width, 8, 'Projet de recherche:', 1, 0, 'L', 1);
            $pdf->SetFont('helvetica', '', 11);
            $pdf->Cell($col2Width, 8, $researchInfo['projet_recherche'], 1, 1, 'L', 0);
            $pdf->SetFont('helvetica', 'B', 11);
        }
    }
}

// Informations de contact d'urgence
if (!empty($agent['contact_urgence']) || !empty($agent['telephone_urgence'])) {
    // Vérifier s'il reste assez d'espace
    if ($pdf->GetY() > $pdf->getPageHeight() - 50) {
        $pdf->AddPage();
        
        // Ajouter le logo en filigrane sur la nouvelle page
        if (!empty($configUniversite['logo'])) {
            $logoPath = dirname(__DIR__) . '/' . $configUniversite['logo'];
            if (file_exists($logoPath)) {
                $pdf->setAlpha(0.1);
                $pageWidth = $pdf->getPageWidth();
                $pageHeight = $pdf->getPageHeight();
                $logoWidth = 70;
                $logoHeight = 100;
                $x = ($pageWidth - $logoWidth) / 2;
                $y = ($pageHeight - $logoHeight) / 2;
                $pdf->Image($logoPath, $x, $y, $logoWidth, $logoHeight, '', '', '', false, 300, '', false, false, 0);
                $pdf->setAlpha(1);
            }
        }
    } else {
        $pdf->Ln(5);
    }
    
    $pdf->SetTextColor($secondaryColor[0], $secondaryColor[1], $secondaryColor[2]);
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->Cell(0, 10, 'CONTACT D\'URGENCE', 0, 1, 'L');
    
    // Ligne décorative sous le titre de section
    $pdf->SetLineStyle(array('width' => 0.2, 'cap' => 'butt', 'join' => 'miter', 'dash' => 0, 'color' => $secondaryColor));
    $pdf->Line(20, $pdf->GetY(), 100, $pdf->GetY());
    $pdf->Ln(2);
    
    $pdf->SetTextColor(60, 60, 60);
    $pdf->SetFont('helvetica', 'B', 11);
    
    if (!empty($agent['contact_urgence'])) {
        $pdf->Cell($col1Width, 8, 'Personne à contacter:', 1, 0, 'L', 1);
        $pdf->SetFont('helvetica', '', 11);
        $pdf->Cell($col2Width, 8, $agent['contact_urgence'], 1, 1, 'L', 0);
        $pdf->SetFont('helvetica', 'B', 11);
    }
    
    if (!empty($agent['degre_parente_urgence'])) {
        $pdf->Cell($col1Width, 8, 'Degré de parenté:', 1, 0, 'L', 1);
        $pdf->SetFont('helvetica', '', 11);
        $pdf->Cell($col2Width, 8, $agent['degre_parente_urgence'], 1, 1, 'L', 0);
        $pdf->SetFont('helvetica', 'B', 11);
    }
    
    if (!empty($agent['telephone_urgence'])) {
        $pdf->Cell($col1Width, 8, 'Téléphone d\'urgence:', 1, 0, 'L', 1);
        $pdf->SetFont('helvetica', '', 11);
        $pdf->Cell($col2Width, 8, $agent['telephone_urgence'], 1, 1, 'L', 0);
    }
}

// Remplacer la section du QR code existante (vers la fin du fichier) par ce code amélioré:

// Ajouter un QR Code avec les informations de l'agent
$qrCodeData = "FICHE AGENT\n";
$qrCodeData .= "ID: " . $idAgent . "\n";
$qrCodeData .= "Nom: " . $agent['noms'] . "\n";
$qrCodeData .= "Matricule: " . $agent['matricule'] . "\n";
$qrCodeData .= "Type: " . $agent['type_agent'] . "\n";
$qrCodeData .= "Service: " . ($service['designationService'] ?? 'Non défini') . "\n";
$qrCodeData .= "Campus: " . ($structure['designation'] ?? 'Non définie') . "\n";
$qrCodeData .= "Document généré le: " . date('d/m/Y H:i:s') . "\n";
$qrCodeData .= $configUniversite['site_web'] ?? '';

// Pied de page avec signature
$pdf->Ln(10);

// Obtenir la position Y actuelle pour aligner les éléments
$currentY = $pdf->GetY();

// Style amélioré pour le QR code
$qrStyle = array(
    'border' => false,
    'padding' => 2,
    'fgcolor' => array($primaryColor[0], $primaryColor[1], $primaryColor[2]), // Couleur du QR code (bleu foncé)
    'bgcolor' => array(255, 255, 255), // Fond blanc
    'module_width' => 1, // Largeur des modules du QR code
    'module_height' => 1 // Hauteur des modules du QR code
);

// Dessiner un cadre décoratif autour du QR code
$qrX = 20;
$qrY = $currentY;
$qrSize = 25;
$pdf->RoundedRect($qrX - 2, $qrY - 2, $qrSize + 4, $qrSize + 4, 2, '1111', 'DF', array(), array(245, 245, 245));

// Placer le QR code à gauche avec style amélioré
$pdf->write2DBarcode($qrCodeData, 'QRCODE,L', $qrX, $qrY, $qrSize, $qrSize, $qrStyle, 'N');

// Ajouter un petit texte sous le QR code
$pdf->SetFont('helvetica', 'I', 7);
$pdf->SetTextColor(100, 100, 100);
$pdf->SetXY($qrX, $qrY + $qrSize + 2);
$pdf->Cell($qrSize, 4, 'Scan pour vérifier', 0, 0, 'C');

// Section de signature avec style amélioré
$pdf->SetTextColor(60, 60, 60);
$pdf->SetFont('helvetica', 'I', 10);
$pdf->SetXY(120, $currentY);
$pdf->Cell(70, 6, 'Fait à ' . ($configUniversite['ville'] ?? '________________'), 0, 1, 'C');
$pdf->SetXY(120, $pdf->GetY());
$pdf->Cell(70, 6, 'Le ' . date('d/m/Y'), 0, 1, 'C');

$pdf->SetXY(120, $pdf->GetY() + 5);
$pdf->SetFont('helvetica', 'B', 10);
$pdf->Cell(70, 6, 'Le Responsable des Ressources Humaines', 0, 1, 'C');

// Ajouter un pied de page avec des informations sur le document
$pdf->SetY($pdf->GetY() + 30); // Ajuster la position Y pour laisser de l'espace après la signature
$pdf->SetFont('helvetica', 'I', 8);
$pdf->Cell(0, 5, 'Ce document est généré automatiquement et comporte une signature électronique sécurisée.', 0, 1, 'C');
$pdf->Cell(0, 5, ($configUniversite['nom'] ?? 'eGestion') . ' - ' . ($configUniversite['site_web'] ?? 'Système de gestion intégré'), 0, 1, 'C');

// Sortie du PDF
$pdf->Output('Fiche_Personnel_' . $agent['codeAgent'] . '.pdf', 'I');
