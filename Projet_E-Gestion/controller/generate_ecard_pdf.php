<?php
ini_set('display_errors', 1); 
error_reporting(E_ALL);
session_start();
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/models/Etudiant.php';
require_once dirname(__DIR__) . '/models/SecurityUtils.php';
require_once dirname(__DIR__) . '/models/Universite.php';
require_once dirname(__DIR__) . '/vendor/autoload.php';

// Vérification de l'ID de carte
if (!isset($_GET['id'])) {
    header('Location: ../etudiants/etudiant.inscrit');
    exit();
}

$cardId = $_GET['id'];

// Récupérer les données de la carte
$universite = new Universite();
$etudiantModel = new Etudiant();
$security = new SecurityUtils();

$cardDetails = $security->getCardDataById($cardId);
if (!$cardDetails) {
    header('Location: ../etudiants/etudiant.inscrit');
    exit();
}

$etudiant = $etudiantModel->getEtudiantById($cardDetails['idetudiant']);
$configUniversite = $universite->getConfigurationUniversite();

// Couleurs
$colorScheme = $security->getCardColorScheme($etudiant['promotion_idpromotion']);
[$primaryColor, $secondaryColor, $textColor, $backgroundColor] = $colorScheme;

function cssColorToRGB($color) {
    $color = trim($color);
    if (preg_match('/^#([0-9a-fA-F]{6})$/', $color, $m)) {
        return [hexdec(substr($m[1], 0, 2)), hexdec(substr($m[1], 2, 2)), hexdec(substr($m[1], 4, 2))];
    }
    if (preg_match('/^hsl\(\s*(\d+)\s*,\s*(\d+)%?\s*,\s*(\d+)%?\s*\)$/', $color, $m)) {
        $h = intval($m[1]) / 360; $s = intval($m[2]) / 100; $l = intval($m[3]) / 100;
        if ($s == 0) { return [round($l * 255), round($l * 255), round($l * 255)]; }
        $q = $l < 0.5 ? $l * (1 + $s) : $l + $s - $l * $s; $p = 2 * $l - $q;
        $hue2rgb = function($p, $q, $t) { if ($t < 0) $t++; if ($t > 1) $t--; if ($t < 1/6) return $p + ($q - $p) * 6 * $t; if ($t < 1/2) return $q; if ($t < 2/3) return $p + ($q - $p) * (2/3 - $t) * 6; return $p; };
        return [round($hue2rgb($p, $q, $h + 1/3) * 255), round($hue2rgb($p, $q, $h) * 255), round($hue2rgb($p, $q, $h - 1/3) * 255)];
    }
    return [26, 82, 118];
}

$primaryRGB = cssColorToRGB($primaryColor);
$secondaryRGB = cssColorToRGB($secondaryColor);

// Sous-classe TCPDF pour accéder à _out (protected)
class CardPDF extends TCPDF {
    public function writeRaw($s) {
        $this->_out($s);
    }
}

// Année académique
$anneeActuelle = date('Y');
$periodeAnnee = $anneeActuelle . ' - ' . ($anneeActuelle + 1);

// ============================================================
// DIMENSIONS - carte format crédit élargie
// ============================================================
$cardW = 100;
$cardH = 62;
$margin = 2;

$pdf = new CardPDF('L', 'mm', array($cardW + $margin * 2, $cardH + $margin * 2), true, 'UTF-8', false);
$pdf->setPrintHeader(false);
$pdf->setPrintFooter(false);
$pdf->SetMargins(0, 0, 0);
$pdf->SetAutoPageBreak(false, 0);
$pdf->AddPage();

$x0 = $margin;
$y0 = $margin;

// ============================================================
// FOND BLANC
// ============================================================
$pdf->SetFillColor(255, 255, 255);
$pdf->RoundedRect($x0, $y0, $cardW, $cardH, 2.5, '1111', 'F');

// ============================================================
// EN-TÊTE - bande primaire
// ============================================================
$headerH = 16;
$pdf->SetFillColor($primaryRGB[0], $primaryRGB[1], $primaryRGB[2]);
$pdf->RoundedRect($x0, $y0, $cardW, $headerH, 2.5, '1100', 'F');

// Logo
$logoPath = isset($configUniversite['logo']) && !empty($configUniversite['logo']) ?
    dirname(__DIR__) . '/' . $configUniversite['logo'] : '';
$logoW = 10;
if (!empty($logoPath) && file_exists($logoPath)) {
    $pdf->SetFillColor(255, 255, 255);
    $logoX = $x0 + 3;
    $logoY = $y0 + 1.5;
    $pdf->RoundedRect($logoX, $logoY, $logoW, $logoW, 1, '1111', 'F');
    $pdf->Image($logoPath, $logoX + 0.5, $logoY + 0.5, $logoW - 1, $logoW - 1, '', '', '', true, 300, '', false, false, 0, 'CM');
}

// Nom de l'université - à droite du logo
$pdf->SetTextColor(255, 255, 255);
$pdf->SetFont('helvetica', 'B', 6.5);
$nomUniversite = strtoupper($configUniversite['nom'] ?? 'UNIVERSITÉ');
$textStartX = $x0 + $logoW + 5;
$textW = $cardW - $logoW - 7;
$pdf->SetXY($textStartX, $y0 + 2);
$pdf->Cell($textW, 4, $nomUniversite, 0, 0, 'C');

// Année académique
$pdf->SetFont('helvetica', 'B', 9);
$pdf->SetXY($textStartX, $y0 + 7);
$pdf->Cell($textW, 5, 'Année académique : ' . $periodeAnnee, 0, 0, 'C');

// ============================================================
// BANDE TITRE - couleur secondaire
// ============================================================
$titleY = $y0 + $headerH + 0.5;
$titleH = 5.5;
$pdf->SetFillColor($secondaryRGB[0], $secondaryRGB[1], $secondaryRGB[2]);
$pdf->Rect($x0, $titleY, $cardW, $titleH, 'F');
$pdf->SetTextColor(255, 255, 255);
$pdf->SetFont('helvetica', 'B', 8);
$pdf->SetXY($x0, $titleY + 0.5);
$pdf->Cell($cardW, $titleH - 1, "CARTE D'ÉTUDIANT PROVISOIRE", 0, 0, 'C');

// ============================================================
// CONTENU PRINCIPAL
// ============================================================
$contentY = $titleY + $titleH + 2;

// --- Photo de l'étudiant (avec clipping circulaire) ---
$photoSize = 22;
$photoX = $x0 + 4;
$photoY = $contentY;
$photoCenterX = $photoX + $photoSize / 2;
$photoCenterY = $photoY + $photoSize / 2;
$photoRadius = $photoSize / 2;

if (!empty($etudiant['photo'])) {
    $photoPath = dirname(__DIR__) . '/' . $etudiant['photo'];
    if (file_exists($photoPath)) {
        // Clipping circulaire via path PDF natif
        $pdf->StartTransform();
        // Créer le chemin de découpe circulaire
        $xc = $photoCenterX;
        $yc = $photoCenterY;
        $rc = $photoRadius;
        // Bezier approximation d'un cercle (4 courbes)
        $kappa = 0.5522847498;
        $km = $rc * $kappa;
        $xr = $xc + $rc; $xl = $xc - $rc; $yt = $yc - $rc; $yb = $yc + $rc;
        // Convertir mm en points utilisateur pour le path
        $k = $pdf->getScaleFactor();
        $h = $pdf->getPageHeight();
        $toX = function($v) use ($k) { return $v * $k; };
        $toY = function($v) use ($k, $h) { return ($h - $v) * $k; };
        // Dessiner le cercle comme clipping path
        $s = sprintf('q %.2F %.2F m', $toX($xr), $toY($yc));
        $s .= sprintf(' %.2F %.2F %.2F %.2F %.2F %.2F c', $toX($xr), $toY($yc - $km), $toX($xc + $km), $toY($yt), $toX($xc), $toY($yt));
        $s .= sprintf(' %.2F %.2F %.2F %.2F %.2F %.2F c', $toX($xc - $km), $toY($yt), $toX($xl), $toY($yc - $km), $toX($xl), $toY($yc));
        $s .= sprintf(' %.2F %.2F %.2F %.2F %.2F %.2F c', $toX($xl), $toY($yc + $km), $toX($xc - $km), $toY($yb), $toX($xc), $toY($yb));
        $s .= sprintf(' %.2F %.2F %.2F %.2F %.2F %.2F c', $toX($xc + $km), $toY($yb), $toX($xr), $toY($yc + $km), $toX($xr), $toY($yc));
        $s .= ' W n';
        $pdf->setPageMark();
        $pdf->writeRaw($s);
        $pdf->Image($photoPath, $photoX, $photoY, $photoSize, $photoSize, '', '', '', true, 300, '', false, false, 0, 'CM');
        $pdf->StopTransform();
    } else {
        $pdf->SetFillColor(233, 236, 239);
        $pdf->Circle($photoCenterX, $photoCenterY, $photoRadius, 0, 360, 'F');
    }
} else {
    $pdf->SetFillColor(233, 236, 239);
    $pdf->Circle($photoCenterX, $photoCenterY, $photoRadius, 0, 360, 'F');
    $pdf->SetTextColor(108, 117, 125);
    $pdf->SetFont('helvetica', '', 6);
    $pdf->SetXY($photoX, $photoCenterY - 2);
    $pdf->Cell($photoSize, 4, 'PHOTO', 0, 0, 'C');
}

// Bordure circulaire de la photo
$pdf->SetDrawColor($primaryRGB[0], $primaryRGB[1], $primaryRGB[2]);
$pdf->SetLineWidth(0.7);
$pdf->Circle($photoCenterX, $photoCenterY, $photoRadius, 0, 360, 'D');

// --- QR Code (positionné d'abord pour calculer l'espace dispo) ---
$qrSize = 16;
$qrX = $x0 + $cardW - $qrSize - 3;
$qrY = $contentY + 1;

// --- Informations de l'étudiant ---
$infoX = $photoX + $photoSize + 3;
$infoY = $contentY + 1;
$lineH = 3.5;

$pdf->SetTextColor($primaryRGB[0], $primaryRGB[1], $primaryRGB[2]);
$pdf->SetFont('helvetica', 'B', 7);

// Calculer la largeur du plus long label pour aligner les ":"
$labels = ['MATRICULE', 'NOMS', 'PROMOTION'];
$labelW = 0;
foreach ($labels as $lbl) {
    $w = $pdf->GetStringWidth($lbl) + 1;
    if ($w > $labelW) $labelW = $w;
}
$colonW = $pdf->GetStringWidth(' : ') + 0.5;
$valueX = $infoX + $labelW + $colonW;
$valueMaxW = $qrX - $valueX - 1;

// MATRICULE
$pdf->SetXY($infoX, $infoY);
$pdf->Cell($labelW, $lineH, 'MATRICULE', 0, 0, 'L');
$pdf->Cell($colonW, $lineH, ' : ', 0, 0, 'L');
$pdf->MultiCell($valueMaxW, $lineH, $etudiant['matricule'], 0, 'L', false, 1, $valueX, $infoY);
$infoY = $pdf->GetY() + 1;

// NOMS
$pdf->SetXY($infoX, $infoY);
$pdf->Cell($labelW, $lineH, 'NOMS', 0, 0, 'L');
$pdf->Cell($colonW, $lineH, ' : ', 0, 0, 'L');
$pdf->MultiCell($valueMaxW, $lineH, $etudiant['noms'], 0, 'L', false, 1, $valueX, $infoY);
$infoY = $pdf->GetY() + 1;

// PROMOTION
$pdf->SetXY($infoX, $infoY);
$pdf->Cell($labelW, $lineH, 'PROMOTION', 0, 0, 'L');
$pdf->Cell($colonW, $lineH, ' : ', 0, 0, 'L');
$designProm = $cardDetails['designationPromotion'] ?? ($etudiant['designationPromotion'] ?? '');
$pdf->MultiCell($valueMaxW, $lineH, $designProm, 0, 'L', false, 1, $valueX, $infoY);

$qrData = json_encode([
    'id' => $etudiant['idetudiant'],
    'matricule' => $etudiant['matricule'],
    'nom' => $etudiant['noms'],
    'card_id' => $cardId
]);

$qrStyle = array(
    'border' => false,
    'vpadding' => 0,
    'hpadding' => 0,
    'fgcolor' => array(0, 0, 0),
    'bgcolor' => array(255, 255, 255),
    'module_width' => 1,
    'module_height' => 1
);
$pdf->write2DBarcode($qrData, 'QRCODE,H', $qrX, $qrY, $qrSize, $qrSize, $qrStyle, 'N');

// ============================================================
// PIED DE PAGE - bande primaire
// ============================================================
$footerH = 9;
$footerY = $y0 + $cardH - $footerH;
$pdf->SetFillColor($primaryRGB[0], $primaryRGB[1], $primaryRGB[2]);
$pdf->RoundedRect($x0, $footerY, $cardW, $footerH, 2.5, '0011', 'F');

// ID NO
$pdf->SetTextColor(255, 255, 255);
$pdf->SetFont('helvetica', 'B', 8);
$pdf->SetXY($x0 + 4, $footerY + 1);
$pdf->Cell(40, 4, 'ID NO : ' . substr($cardId, -8), 0, 0, 'L');

// Adresse
$pdf->SetFont('helvetica', '', 5);
$pdf->SetXY($x0 + 4, $footerY + 5);
$pdf->Cell($cardW - 22, 3, $configUniversite['adresse'] ?? '', 0, 0, 'L');

// Bandes décoratives à droite du pied de page
$pdf->SetDrawColor(255, 255, 255);
$pdf->SetLineWidth(0.7);
$stripeX = $x0 + $cardW - 14;
for ($i = 0; $i < 3; $i++) {
    $sy = $footerY + 2.5 + ($i * 2.2);
    $pdf->Line($stripeX, $sy, $stripeX + 10, $sy - 1.2);
}

// ============================================================
// BORDURE EXTÉRIEURE
// ============================================================
$pdf->SetDrawColor(200, 200, 200);
$pdf->SetLineWidth(0.2);
$pdf->RoundedRect($x0, $y0, $cardW, $cardH, 2.5, '1111', 'D');

// Générer le PDF
$pdf->Output('carte_etudiant_' . $etudiant['matricule'] . '.pdf', 'D');
