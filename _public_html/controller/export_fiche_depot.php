<?php
session_start();
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/models/Etudiant.php';
require_once dirname(__DIR__) . '/models/Universite.php';
require_once dirname(__DIR__) . '/vendor/autoload.php';

// Vérification de la session
if (!isset($_SESSION['student_id'])) {
    header('Location: ../login');
    exit;
}

// Récupération de l'ID étudiant
$etudiantId = isset($_GET['etudiant_id']) ? intval($_GET['etudiant_id']) : $_SESSION['student_id'];

// Vérification que l'étudiant peut seulement télécharger sa propre fiche
if ($etudiantId != $_SESSION['student_id']) {
    header('HTTP/1.0 403 Forbidden');
    exit('Accès non autorisé');
}

try {
    $etudiant = new Etudiant();
    $universiteModel = new Universite();
    
    // Récupération des informations de l'étudiant
    $etudiantInfo = $etudiant->getEtudiantById($etudiantId);
    if (!$etudiantInfo) {
        throw new Exception('Étudiant non trouvé');
    }
    
    // Récupération du sujet assigné
    $sujetAssigne = $etudiant->getSujetAssigne($etudiantId);
    if (!$sujetAssigne) {
        throw new Exception('Aucun sujet assigné trouvé');
    }
    
    // Récupération des informations de l'université
    $configUniversite = $universiteModel->getConfigurationUniversite();
    
    // Fonction pour rogner une image pour ne garder que le visage (même fonction que dans generate_agent_pdf.php)
    function cropFaceFromImage($imagePath) {
        if (!extension_loaded('gd')) {
            return $imagePath;
        }
        
        $tempFile = sys_get_temp_dir() . '/' . uniqid('face_') . '.jpg';
        $imageInfo = getimagesize($imagePath);
        if (!$imageInfo) {
            return $imagePath;
        }
        
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
                return $imagePath;
        }
        
        if (!$sourceImage) {
            return $imagePath;
        }
        
        $width = imagesx($sourceImage);
        $height = imagesy($sourceImage);
        $cropSize = min($width, $height);
        $x = ($width - $cropSize) / 2;
        $y = ($height - $cropSize) / 4;
        
        $croppedImage = imagecreatetruecolor($cropSize, $cropSize);
        
        if ($imageInfo[2] == IMAGETYPE_PNG) {
            imagealphablending($croppedImage, false);
            imagesavealpha($croppedImage, true);
            $transparent = imagecolorallocatealpha($croppedImage, 255, 255, 255, 127);
            imagefilledrectangle($croppedImage, 0, 0, $cropSize, $cropSize, $transparent);
        }
        
        imagecopy($croppedImage, $sourceImage, 0, 0, $x, $y, $cropSize, $cropSize);
        imagejpeg($croppedImage, $tempFile, 90);
        
        imagedestroy($sourceImage);
        imagedestroy($croppedImage);
        
        return $tempFile;
    }

    // Créer une instance de TCPDF
    $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);

    // Définir les informations du document
    $pdf->SetCreator('Système de gestion universitaire');
    $pdf->SetAuthor($configUniversite['nom'] ?? 'Administration');
    $pdf->SetTitle('Fiche de Dépôt de Sujet - ' . $etudiantInfo['noms']);
    $pdf->SetSubject('Fiche de dépôt individuelle');
    $pdf->SetKeywords('Étudiant, Sujet, Mémoire, Dépôt');

    // Supprimer les en-têtes et pieds de page par défaut
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);

    // Définir les marges
    $pdf->SetMargins(20, 20, 20);
    $pdf->SetAutoPageBreak(true, 25);

    // Couleurs pour le design (même palette que generate_agent_pdf.php)
    $primaryColor = array(0, 87, 146); // Bleu foncé
    $secondaryColor = array(70, 130, 180); // Bleu acier
    $accentColor = array(0, 121, 194); // Bleu moyen

    // Ajouter une page
    $pdf->AddPage();

    // Ajouter le logo en filigrane
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

    // En-tête avec les informations de l'université
    if ($configUniversite) {
        // Logo de l'université (visible)
        if (!empty($configUniversite['logo'])) {
            $logoPath = dirname(__DIR__) . '/' . $configUniversite['logo'];
            if (file_exists($logoPath)) {
                $pdf->Image($logoPath, 20, 15, 20, 0, '', '', '', false, 200, '', false, false, 0);
            }
        }
        
        // Titre et informations de l'université
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

    // Photo de l'étudiant (rognée) - positionnée à droite
    if (!empty($etudiantInfo['photo']) && file_exists(dirname(__DIR__) . '/uploads/etudiants/' . $etudiantInfo['photo'])) {
        $photoPath = dirname(__DIR__) . '/uploads/etudiants/' . $etudiantInfo['photo'];
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
    $pdf->Ln(5);
    $pdf->Cell(0, 10, 'FICHE DE DÉPÔT DU SUJET DE RECHERCHE', 0, 1, 'C', 1);

    $pdf->SetTextColor(80, 80, 80);
    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->Cell(0, 8, 'N° ' . sprintf('%04d', $etudiantId) . '/' . date('Y'), 0, 1, 'C');

    // Définir la largeur des colonnes
    $col1Width = 60;
    $col2Width = $pdf->getPageWidth() - $pdf->getMargins()['left'] - $pdf->getMargins()['right'] - $col1Width;

    // Informations de l'étudiant
    $pdf->SetTextColor($secondaryColor[0], $secondaryColor[1], $secondaryColor[2]);
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->Cell(0, 10, 'INFORMATIONS DE L\'ÉTUDIANT', 0, 1, 'L');

    // Ligne décorative sous le titre de section
    $pdf->SetLineStyle(array('width' => 0.2, 'cap' => 'butt', 'join' => 'miter', 'dash' => 0, 'color' => $secondaryColor));
    $pdf->Line(20, $pdf->GetY(), 120, $pdf->GetY());
    $pdf->Ln(2);

    // Style pour les cellules de tableau
    $pdf->SetFillColor(245, 245, 245);
    $pdf->SetDrawColor($accentColor[0], $accentColor[1], $accentColor[2]);
    $pdf->SetTextColor(60, 60, 60);
    $pdf->SetFont('helvetica', 'B', 11);

    $pdf->Cell($col1Width, 8, 'Matricule:', 1, 0, 'L', 1);
    $pdf->SetFont('helvetica', '', 11);
    $pdf->Cell($col2Width, 8, $etudiantInfo['matricule'], 1, 1, 'L', 0);

    $pdf->SetFont('helvetica', 'B', 11);
    $pdf->Cell($col1Width, 8, 'Noms & Prénoms:', 1, 0, 'L', 1);
    $pdf->SetFont('helvetica', '', 11);
    $pdf->Cell($col2Width, 8, $etudiantInfo['noms'], 1, 1, 'L', 0);

    $pdf->SetFont('helvetica', 'B', 11);
    $pdf->Cell($col1Width, 8, 'Promotion:', 1, 0, 'L', 1);
    $pdf->SetFont('helvetica', '', 11);
    $pdf->Cell($col2Width, 8, $etudiantInfo['promotion'] ?? 'Non définie', 1, 1, 'L', 0);

    
    // Section Détails du sujet
    $pdf->Ln(5);
    $pdf->SetTextColor($secondaryColor[0], $secondaryColor[1], $secondaryColor[2]);
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->Cell(0, 10, 'DÉTAILS DU SUJET', 0, 1, 'L');

    $pdf->SetLineStyle(array('width' => 0.2, 'cap' => 'butt', 'join' => 'miter', 'dash' => 0, 'color' => $secondaryColor));
    $pdf->Line(20, $pdf->GetY(), 120, $pdf->GetY());
    $pdf->Ln(2);

    // Intitulé du sujet (sur plusieurs lignes si nécessaire)
    $pdf->SetFont('helvetica', 'B', 11);
    $pdf->Cell($col1Width, 8, 'Intitulé:', 1, 0, 'L', 1);
    $pdf->SetFont('helvetica', '', 11);
    
    // Calculer la hauteur nécessaire pour l'intitulé
    $intitule = $sujetAssigne['intitule'];
    $lines = $pdf->getNumLines($intitule, $col2Width);
    $cellHeight = max(8, $lines * 4);
    
    $pdf->MultiCell($col2Width, $cellHeight, $intitule, 1, 'L', 0, 1);

    $pdf->SetFont('helvetica', 'B', 11);
    $pdf->Cell($col1Width, 8, 'Unité de recherche:', 1, 0, 'L', 1);
    $pdf->SetFont('helvetica', '', 11);
    $pdf->Cell($col2Width, 8, $sujetAssigne['unite_recherche'] ?? 'Non définie', 1, 1, 'L', 0);

    // Spécialisation avec MultiCell pour gérer le texte long
    $pdf->SetFont('helvetica', 'B', 11);
    $pdf->Cell($col1Width, 8, 'Spécialisation:', 1, 0, 'L', 1);
    $pdf->SetFont('helvetica', '', 11);
    
    // Calculer la hauteur nécessaire pour la spécialisation
    $specialisation = $sujetAssigne['specialisation'] ?? 'Non définie';
    $lines = $pdf->getNumLines($specialisation, $col2Width);
    $cellHeight = max(8, $lines * 4);
    
    $pdf->MultiCell($col2Width, $cellHeight, $specialisation, 1, 'L', 0, 1);

    // Section Encadrement
    $pdf->Ln(5);
    $pdf->SetTextColor($secondaryColor[0], $secondaryColor[1], $secondaryColor[2]);
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->Cell(0, 10, 'ENCADREMENT ACADÉMIQUE', 0, 1, 'L');

    $pdf->SetLineStyle(array('width' => 0.2, 'cap' => 'butt', 'join' => 'miter', 'dash' => 0, 'color' => $secondaryColor));
    $pdf->Line(20, $pdf->GetY(), 120, $pdf->GetY());
    $pdf->Ln(2);

    $pdf->SetFont('helvetica', 'B', 11);
    $pdf->Cell($col1Width, 8, 'Directeur :', 1, 0, 'L', 1);
    $pdf->SetFont('helvetica', '', 11);
    $pdf->Cell($col2Width, 8, $sujetAssigne['directeur'] ?? 'Non assigné', 1, 1, 'L', 0);

    if (!empty($sujetAssigne['encadreur'])) {
        $pdf->SetFont('helvetica', 'B', 11);
        $pdf->Cell($col1Width, 8, 'Co-encadreur:', 1, 0, 'L', 1);
        $pdf->SetFont('helvetica', '', 11);
        $pdf->Cell($col2Width, 8, $sujetAssigne['encadreur'], 1, 1, 'L', 0);
    }

    // Section Statut du dépôt
    $pdf->Ln(5);
    $pdf->SetTextColor($secondaryColor[0], $secondaryColor[1], $secondaryColor[2]);
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->Cell(0, 10, 'STATUT DU DÉPÔT', 0, 1, 'L');

    $pdf->SetLineStyle(array('width' => 0.2, 'cap' => 'butt', 'join' => 'miter', 'dash' => 0, 'color' => $secondaryColor));
    $pdf->Line(20, $pdf->GetY(), 120, $pdf->GetY());
    $pdf->Ln(2);

    // Couleur du statut selon l'état
    $statutColor = array(80, 80, 80); // Gris par défaut
    switch ($sujetAssigne['statut_validation']) {
        case 'Validé':
            $statutColor = array(40, 167, 69); // Vert
            break;
        case 'Rejeté':
            $statutColor = array(220, 53, 69); // Rouge
            break;
        case 'En attente':
            $statutColor = array(255, 193, 7); // Orange
            break;
    }

    $pdf->SetFont('helvetica', 'B', 11);
    $pdf->Cell($col1Width, 8, 'Statut actuel:', 1, 0, 'L', 1);
    $pdf->SetFont('helvetica', 'B', 11);
    $pdf->SetTextColor($statutColor[0], $statutColor[1], $statutColor[2]);
    $pdf->Cell($col2Width, 8, strtoupper($sujetAssigne['statut_validation']), 1, 1, 'C', 0);

    $pdf->SetTextColor(60, 60, 60);
    $pdf->SetFont('helvetica', 'B', 11);
    $pdf->Cell($col1Width, 8, 'Date de dépôt:', 1, 0, 'L', 1);
    $pdf->SetFont('helvetica', '', 11);
    $dateDepot = isset($sujetAssigne['date_depot']) ? date('d/m/Y à H:i', strtotime($sujetAssigne['date_depot'])) : 'Non définie';
    $pdf->Cell($col2Width, 8, $dateDepot, 1, 1, 'L', 0);

    if (isset($sujetAssigne['date_validation']) && $sujetAssigne['statut_validation'] !== 'En attente') {
        $pdf->SetFont('helvetica', 'B', 11);
        $pdf->Cell($col1Width, 8, 'Date de réponse:', 1, 0, 'L', 1);
        $pdf->SetFont('helvetica', '', 11);
        $dateValidation = date('d/m/Y à H:i', strtotime($sujetAssigne['date_validation']));
        $pdf->Cell($col2Width, 8, $dateValidation, 1, 1, 'L', 0);
    }

    // Commentaire de validation s'il existe
    if (!empty($sujetAssigne['commentaire_validation'])) {
        $pdf->SetFont('helvetica', 'B', 11);
        $pdf->Cell($col1Width, 8, 'Observation:', 1, 0, 'L', 1);
        $pdf->SetFont('helvetica', '', 10);
        
        $commentaire = $sujetAssigne['commentaire_validation'];
        $lines = $pdf->getNumLines($commentaire, $col2Width);
        $cellHeight = max(8, $lines * 4);
        
        $pdf->MultiCell($col2Width, $cellHeight, $commentaire, 1, 'L', 0, 1);
    }

    // Section signatures
    $pdf->Ln(5);
    $pdf->SetTextColor($secondaryColor[0], $secondaryColor[1], $secondaryColor[2]);
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->Cell(0, 10, 'SIGNATURES ET VALIDATIONS', 0, 1, 'L');

    $pdf->SetLineStyle(array('width' => 0.2, 'cap' => 'butt', 'join' => 'miter', 'dash' => 0, 'color' => $secondaryColor));
    $pdf->Line(20, $pdf->GetY(), 120, $pdf->GetY());
    $pdf->Ln(5);

    // Zone de signatures en trois colonnes
    $signWidth = ($pdf->getPageWidth() - 60) / 3;
    $pdf->SetTextColor(80, 80, 80);
    $pdf->SetFont('helvetica', 'B', 10);

    // Signature étudiant
    $pdf->Cell($signWidth, 6, 'Étudiant', 0, 0, 'C');
    $pdf->Cell($signWidth, 6, 'Directeur', 0, 0, 'C');
    $pdf->Cell($signWidth, 6, 'Administration', 0, 1, 'C');

    $pdf->SetFont('helvetica', '', 9);
    $pdf->Cell($signWidth, 4, $etudiantInfo['noms'], 0, 0, 'C');
    $pdf->Cell($signWidth, 4, $sujetAssigne['directeur'] ?? '', 0, 0, 'C');
    $pdf->Cell($signWidth, 4, 'Service Académique', 0, 1, 'C');

    // Lignes pour signatures
    $pdf->Ln(15);
    $pdf->SetLineStyle(array('width' => 0.2, 'cap' => 'butt', 'join' => 'miter', 'dash' => 0, 'color' => array(100, 100, 100)));
    
    $startX = 20;
    $pdf->Line($startX, $pdf->GetY(), $startX + $signWidth - 10, $pdf->GetY());
    $pdf->Line($startX + $signWidth + 5, $pdf->GetY(), $startX + 2*$signWidth - 5, $pdf->GetY());
    $pdf->Line($startX + 2*$signWidth + 5, $pdf->GetY(), $startX + 3*$signWidth - 10, $pdf->GetY());

    // Générer un QR code pour la vérification
    $verificationUrl = "http://" . $_SERVER['HTTP_HOST'] . "/verify_depot.php?id=" . base64_encode($etudiantId . ':' . time());
    
        // Générer les données pour le QR code de vérification
    $qrCodeData = "FICHE DEPOT SUJET\n";
    $qrCodeData .= "ID: " . $etudiantId . "\n";
    $qrCodeData .= "Etudiant: " . $etudiantInfo['noms'] . "\n";
    $qrCodeData .= "Matricule: " . $etudiantInfo['matricule'] . "\n";
    $qrCodeData .= "Sujet: " . $sujetAssigne['intitule'] . "\n";
    $qrCodeData .= "Statut: " . $sujetAssigne['statut_validation'] . "\n";
    $qrCodeData .= "Date depot: " . ($sujetAssigne['date_depot'] ? date('d/m/Y', strtotime($sujetAssigne['date_depot'])) : 'N/A') . "\n";
    $qrCodeData .= "Document genere le: " . date('d/m/Y H:i:s') . "\n";
    $qrCodeData .= $configUniversite['site_web'] ?? '';

    // Style pour le QR code
    $qrStyle = array(
        'border' => false,
        'padding' => 2,
        'fgcolor' => array($primaryColor[0], $primaryColor[1], $primaryColor[2]),
        'bgcolor' => array(255, 255, 255),
        'module_width' => 1,
        'module_height' => 1
    );

    // Calculer la position du QR code pour qu'il reste dans la page
    $pageWidth = $pdf->getPageWidth();
    $pageHeight = $pdf->getPageHeight();
    $bottomMargin = 25; // Marge du bas définie plus haut
    $rightMargin = 20;  // Marge de droite
    $qrSize = 25;
    
    // Position du QR code - s'assurer qu'il reste dans les limites de la page
    $qrX = $pageWidth - $rightMargin - $qrSize;
    $maxQrY = $pageHeight - $bottomMargin - $qrSize - 8; // -8 pour le texte sous le QR code
    
    // Obtenir la position Y actuelle et s'assurer que le QR code ne déborde pas
    $currentY = $pdf->GetY();
    $qrY = min($maxQrY, $currentY + 10); // 10mm d'espacement depuis la position actuelle
    
    // Si le QR code risque de déborder, le placer plus haut
    if ($qrY + $qrSize + 8 > $pageHeight - $bottomMargin) {
        $qrY = $pageHeight - $bottomMargin - $qrSize - 8;
    }

    // Dessiner un cadre décoratif autour du QR code
    $pdf->RoundedRect($qrX - 2, $qrY - 2, $qrSize + 4, $qrSize + 4, 2, '1111', 'DF', array(), array(245, 245, 245));

    // Placer le QR code dans le cadre calculé
    $pdf->write2DBarcode($qrCodeData, 'QRCODE,L', $qrX, $qrY, $qrSize, $qrSize, $qrStyle, 'N');

    // Ajouter un petit texte sous le QR code
    $pdf->SetFont('helvetica', 'I', 7);
    $pdf->SetTextColor(100, 100, 100);
    $pdf->Text($qrX, $qrY + $qrSize + 2, 'Code de verification');

    // Informations de génération du document - ajuster la position pour éviter le chevauchement avec le QR code
    $pdf->SetFont('helvetica', '', 8);
    $pdf->SetTextColor(120, 120, 120);
    
    // Calculer la position Y pour les informations de génération
    $infoY = max($pdf->GetY() + 5, $qrY + $qrSize + 10);
    
    // S'assurer que les informations ne débordent pas de la page
    if ($infoY + 10 > $pageHeight - $bottomMargin) {
        $infoY = $pageHeight - $bottomMargin - 10;
    }
    
    $pdf->SetY($infoY);
    $pdf->Cell(0, 4, 'Document généré automatiquement le ' . date('d/m/Y'), 0, 1, 'L');
    $pdf->Cell(0, 4, 'Référence: FDS-' . sprintf('%04d', $etudiantId) . '-' . date('YmdHis'), 0, 1, 'L');


    // Ajouter un filigrane "COPIE" si le sujet n'est pas encore validé
    if ($sujetAssigne['statut_validation'] !== 'Validé') {
        $pdf->setAlpha(0.3);
        $pdf->SetTextColor(200, 0, 0);
        $pdf->SetFont('helvetica', 'B', 50);
        $pdf->StartTransform();
        $pdf->Rotate(45, $pdf->getPageWidth()/2, $pdf->getPageHeight()/2);
        $pdf->Text($pdf->getPageWidth()/2 - 30, $pdf->getPageHeight()/2, 'PROVISOIRE');
        $pdf->StopTransform();
        $pdf->setAlpha(1);
    }

    // Nettoyer la sortie et envoyer le PDF
    ob_clean();
    
    // Nom du fichier
    $fileName = 'Fiche_Depot_' . str_replace(' ', '_', $etudiantInfo['noms']) . '_' . date('Y-m-d') . '.pdf';
    
    // Sortie du PDF
    $pdf->Output($fileName, 'I'); // 'D' force le téléchargement

} catch (Exception $e) {
    // Gestion des erreurs
    error_log("Erreur génération fiche dépôt: " . $e->getMessage());
    
    echo "<script>
        alert('Erreur lors de la génération de la fiche: " . addslashes($e->getMessage()) . "');
        window.history.back();
    </script>";
}
?>
