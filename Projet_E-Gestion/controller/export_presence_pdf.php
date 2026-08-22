<?php
// Export PDF des présences (résumé agents) avec le design du bulletin ancien
// Entrées attendues (GET): start=YYYY-MM-DD, end=YYYY-MM-DD, structureId? serviceId?

session_start();

require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/models/Agent.php';
require_once dirname(__DIR__) . '/models/Universite.php';
require_once dirname(__DIR__) . '/vendor/autoload.php'; // TCPDF via Composer

if (!isset($_SESSION['id'])) {
    header('Location: ../login');
    exit;
}

$start = isset($_GET['start']) ? $_GET['start'] : '';
$end = isset($_GET['end']) ? $_GET['end'] : '';
$structureId = isset($_GET['structureId']) ? (int)$_GET['structureId'] : 0;
$serviceId = isset($_GET['serviceId']) ? (int)$_GET['serviceId'] : 0;

if ($start === '' || $end === '') {
    // Paramètres manquants: retourner proprement
    $_SESSION['swal_error'] = [
        'title' => 'Paramètres manquants',
        'text' => 'Veuillez choisir une période (début et fin).',
        'icon' => 'error'
    ];
    header('Location: ../views/grh/presence.config.php');
    exit;
}

$agentModel = new Agent();
$universiteModel = new Universite();
$rows = $agentModel->getPresenceSummary($start, $end, $structureId ?: null, $serviceId ?: null);
$configUniversite = $universiteModel->getConfigurationUniversite();

// Créer PDF en reprenant le design du bulletin ancien
$pdf = new TCPDF('L', 'mm', 'A4', true, 'UTF-8', false);

// Métadonnées
$pdf->SetCreator('eGestion');
$pdf->SetAuthor($configUniversite['nom'] ?? 'eGestion');
$pdf->SetTitle('Présences du ' . $start . ' au ' . $end);
$pdf->SetSubject('Rapport des présences');
$pdf->SetKeywords('Présences, Rapport, Agents');

// En-têtes/pieds par défaut désactivés (comme bulletin)
$pdf->setPrintHeader(false);
$pdf->setPrintFooter(false);

// Marges et sauts de page (on active l’automatique pour la table)
$pdf->SetMargins(10, 10, 10);
$pdf->SetAutoPageBreak(true, 15);

// Couleurs (reprises du bulletin)
$primaryColor = array(44, 62, 80);
$secondaryColor = array(52, 73, 94);
$accentColor = array(0, 123, 194);

$pdf->AddPage();

// Watermark (logo en filigrane centré)
if (!empty($configUniversite['logo'])) {
    $logoPath = dirname(__DIR__) . '/' . $configUniversite['logo'];
    if (file_exists($logoPath)) {
        if (method_exists($pdf, 'SetAlpha')) { $pdf->SetAlpha(0.1); }
        $centerX = ($pdf->getPageWidth() - 60) / 2;
        $centerY = ($pdf->getPageHeight() - 60) / 2;
        $pdf->Image($logoPath, $centerX, $centerY, 60, 0, '', '', '', false, 200, '', false, false, 0);
        if (method_exists($pdf, 'SetAlpha')) { $pdf->SetAlpha(1); }
    }
}

// En-tête institutionnelle (logo + textes)
$logoSize = 12;
if (!empty($configUniversite['logo'])) {
    $logoPath = dirname(__DIR__) . '/' . $configUniversite['logo'];
    if (file_exists($logoPath)) {
        $pdf->Image($logoPath, 10, 10, $logoSize, 0, '', '', '', false, 200, '', false, false, 0);
    }
}

$pdf->SetTextColor($primaryColor[0], $primaryColor[1], $primaryColor[2]);
$pdf->SetFont('helvetica', 'B', 10);
$pdf->SetY(10);
$pdf->SetX(10 + $logoSize + 5);
$pdf->Cell(0, 4, strtoupper($configUniversite['ministere_tutelle'] ?? ''), 0, 1, 'C');

$pdf->SetFont('helvetica', 'B', 12);
$pdf->Cell(0, 5, strtoupper($configUniversite['nom'] ?? ''), 0, 1, 'C');

if (!empty($configUniversite['sigle'])) {
    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->Cell(0, 4, strtoupper($configUniversite['sigle']), 0, 1, 'C');
}

$pdf->SetFont('helvetica', '', 8);
$pdf->SetTextColor(80, 80, 80);
$contactInfo = '';
if (!empty($configUniversite['telephone'])) { $contactInfo .= 'Tél: ' . $configUniversite['telephone'] . ' '; }
if (!empty($configUniversite['email'])) { $contactInfo .= 'Email: ' . $configUniversite['email'] . ' '; }
if (!empty($configUniversite['site_web'])) { $contactInfo .= 'Web: ' . $configUniversite['site_web']; }
if ($contactInfo !== '') { $pdf->Cell(0, 3, $contactInfo, 0, 1, 'C'); }

// Ligne séparatrice
$pdf->Ln(6);
$pdf->SetLineStyle(array('width' => 0.3, 'cap' => 'butt', 'join' => 'miter', 'dash' => 0, 'color' => $accentColor));
$pdf->Line(10, $pdf->GetY(), $pdf->getPageWidth() - 10, $pdf->GetY());

// Titre du rapport
$pdf->Ln(4);
$pdf->SetFillColor($primaryColor[0], $primaryColor[1], $primaryColor[2]);
$pdf->SetTextColor(255, 255, 255);
$pdf->SetFont('helvetica', 'B', 11);
$titre = 'RAPPORT DES PRÉSENCES (AGENTS)';
$pdf->Cell(0, 7, $titre, 0, 1, 'C', 1);

// Sous-titre: période + filtres
$pdf->SetTextColor($secondaryColor[0], $secondaryColor[1], $secondaryColor[2]);
$pdf->SetFont('helvetica', 'B', 9);
$pdf->Cell(0, 5, 'Période: ' . date('d/m/Y', strtotime($start)) . ' au ' . date('d/m/Y', strtotime($end)), 0, 1, 'C');

$pdf->SetFont('helvetica', 'I', 8);
$pdf->SetTextColor(80, 80, 80);
$filtres = [];
if ($structureId) { $filtres[] = 'Structure ID: ' . $structureId; }
if ($serviceId) { $filtres[] = 'Service ID: ' . $serviceId; }
if (!empty($filtres)) { $pdf->Cell(0, 4, implode(' | ', $filtres), 0, 1, 'C'); }

// Espace avant tableau
$pdf->Ln(2);

// Préparer le tableau
$contentWidth = $pdf->getPageWidth() - 20; // marges 10
$wNum = $contentWidth * 0.05;   // ~9.5
$wMat = $contentWidth * 0.14;   // matricule
$wNom = $contentWidth * 0.33;   // noms
$wTel = $contentWidth * 0.14;   // téléphone
$wStr = $contentWidth * 0.18;   // structure
$wSrv = $contentWidth * 0.12;   // service
$wJrs = $contentWidth * 0.04;   // jours

// En-têtes
$pdf->SetFillColor(240, 240, 240);
$pdf->SetTextColor(60, 60, 60);
$pdf->SetFont('helvetica', 'B', 8);
$pdf->SetDrawColor(200, 200, 200);

$pdf->Cell($wNum, 6, '#', 1, 0, 'C', 1);
$pdf->Cell($wMat, 6, 'Matricule', 1, 0, 'C', 1);
$pdf->Cell($wNom, 6, 'Nom & Prénom', 1, 0, 'C', 1);
$pdf->Cell($wTel, 6, 'Téléphone', 1, 0, 'C', 1);
$pdf->Cell($wStr, 6, 'Structure', 1, 0, 'C', 1);
$pdf->Cell($wSrv, 6, 'Service', 1, 0, 'C', 1);
$pdf->Cell($wJrs, 6, 'Jrs', 1, 1, 'C', 1);

// Lignes
$pdf->SetFont('helvetica', '', 8);
$pdf->SetTextColor(50, 50, 50);
$pdf->SetFillColor(255, 255, 255);
$fill = false;
$i = 1;

foreach ($rows as $r) {
    // alterner fond
    $pdf->SetFillColor($fill ? 248 : 255, $fill ? 249 : 255, $fill ? 250 : 255);
    $pdf->Cell($wNum, 6, $i++, 1, 0, 'C', 1);
    $pdf->Cell($wMat, 6, (string)($r['matricule'] ?? ''), 1, 0, 'C', 1);
    $pdf->Cell($wNom, 6, (string)($r['noms'] ?? ''), 1, 0, 'L', 1);
    $pdf->Cell($wTel, 6, (string)($r['telephone'] ?? ''), 1, 0, 'C', 1);
    $pdf->Cell($wStr, 6, (string)($r['structure'] ?? ''), 1, 0, 'L', 1);
    $pdf->Cell($wSrv, 6, (string)($r['service'] ?? ''), 1, 0, 'L', 1);
    $pdf->Cell($wJrs, 6, (string)($r['jours_presence'] ?? '0'), 1, 1, 'C', 1);
    $fill = !$fill;
}

// Résumé simple
$pdf->Ln(3);
$pdf->SetFont('helvetica', 'B', 8);
$pdf->SetTextColor($secondaryColor[0], $secondaryColor[1], $secondaryColor[2]);
$pdf->Cell(0, 5, 'Total agents: ' . count($rows), 0, 1, 'L');

// Nettoyer tout buffer pour éviter "Some data has already been output"
while (ob_get_level() > 0) { ob_end_clean(); }
if (function_exists('apache_setenv')) { @apache_setenv('no-gzip', '1'); }
@ini_set('zlib.output_compression', 'Off');

$filename = 'presences_' . $start . '_' . $end . '.pdf';
$pdf->Output($filename, 'I');
exit;
