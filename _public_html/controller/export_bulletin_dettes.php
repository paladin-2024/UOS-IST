<?php
session_start();
require_once dirname(__DIR__).'/config/Connexion.php';
require_once dirname(__DIR__).'/vendor/autoload.php';
require_once dirname(__DIR__).'/models/Universite.php';

use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;

if (!isset($_SESSION['id'])) {
    header('Location: ../connexion');
    exit();
}

$matricule = isset($_GET['matricule']) ? $_GET['matricule'] : null;
$anneeId = isset($_GET['annee']) ? intval($_GET['annee']) : 0;

if (!$matricule) {
    die('Matricule manquant');
}

$universite = new Universite();
$db = Connexion::getInstance()->getPDO();
$configUniversite = $universite->getConfigurationUniversite();

// Étudiant
$stmt = $db->prepare("
    SELECT e.*, p.designationPromotion, p.cycle, p.est_terminale 
    FROM etudiant e
    JOIN promotion p ON e.promotion_idpromotion = p.idpromotion
    WHERE e.matricule = :matricule
");
$stmt->execute(['matricule' => $matricule]);
$etudiant = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$etudiant) {
    die('Étudiant non trouvé');
}

$annee = $anneeId ? $universite->getAnneeAcademiqueById($anneeId) : null;

// Dettes groupées par UE
$sql = "SELECT 
            d.*, ec.designationECUE, ue.designationUE, ue.codeUE, ue.idUE,
            s.numeroSemestre, p.designationPromotion, aa.designation as annee_academique
        FROM dette_etudiant d
        JOIN ecue ec ON d.ECUE_idECUE = ec.idECUE
        JOIN ue ON d.UE_idUE = ue.idUE
        JOIN semestre s ON d.semestre_idsemestre = s.idsemestre
        JOIN promotion p ON d.promotion_idpromotion = p.idpromotion
        JOIN annee_acad aa ON d.annee_acad_idannee_acad = aa.idannee_acad
        WHERE d.matricule = :matricule
        ORDER BY aa.designation DESC, s.numeroSemestre, ue.codeUE, ec.designationECUE";

$stmt = $db->prepare($sql);
$stmt->execute(['matricule' => $matricule]);
$dettes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Grouper par UE
$dettesParUE = [];
foreach ($dettes as $d) {
    $ueKey = $d['annee_academique'] . '_S' . $d['numeroSemestre'] . '_' . $d['idUE'];
    if (!isset($dettesParUE[$ueKey])) {
        $dettesParUE[$ueKey] = [
            'annee' => $d['annee_academique'],
            'semestre' => $d['numeroSemestre'],
            'codeUE' => $d['codeUE'],
            'designationUE' => $d['designationUE'],
            'promotion' => $d['designationPromotion'],
            'ecues' => []
        ];
    }
    $dettesParUE[$ueKey]['ecues'][] = $d;
}

// Statistiques
$totalDettes = count($dettes);
$validees = $enCours = $totalCredits = $creditsValides = 0;
foreach ($dettes as $d) {
    $totalCredits += $d['credits_ecue'];
    if ($d['statut'] == 'Validée') { $validees++; $creditsValides += $d['credits_ecue']; }
    elseif ($d['statut'] == 'En cours') { $enCours++; }
}

// QR Code
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
$bulletinUrl = $protocol . "://" . $_SERVER['HTTP_HOST'] . "/controller/export_bulletin_dettes.php?matricule=" . $matricule;
$qrData = "DETTES-" . $matricule . "-" . date('dmY');

// Créer le PDF avec TCPDF
$pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
$pdf->SetCreator('E-Gestion');
$pdf->SetAuthor($configUniversite['nom'] ?? 'Administration');
$pdf->SetTitle('Bulletin des Dettes - ' . $etudiant['noms']);
$pdf->setPrintHeader(false);
$pdf->setPrintFooter(false);
$pdf->SetMargins(10, 10, 10);
$pdf->SetAutoPageBreak(false); // Désactiver pour contrôle manuel

$primaryColor = [44, 62, 80];
$accentColor = [0, 123, 194];
$pdf->AddPage();

// Logo en filigrane
if (!empty($configUniversite['logo'])) {
    $logoPath = dirname(__DIR__) . '/' . $configUniversite['logo'];
    if (file_exists($logoPath)) {
        $pdf->SetAlpha(0.08);
        $centerX = ($pdf->getPageWidth() - 50) / 2;
        $centerY = ($pdf->getPageHeight() - 50) / 2;
        $pdf->Image($logoPath, $centerX, $centerY, 50, 0);
        $pdf->SetAlpha(1);
    }
}

// En-tête
if (!empty($configUniversite['logo'])) {
    $logoPath = dirname(__DIR__) . '/' . $configUniversite['logo'];
    if (file_exists($logoPath)) {
        $pdf->Image($logoPath, 10, 10, 15, 0);
    }
}

$pdf->SetTextColor($primaryColor[0], $primaryColor[1], $primaryColor[2]);
$pdf->SetFont('helvetica', 'B', 9);
$pdf->SetY(10);
$pdf->Cell(0, 4, strtoupper($configUniversite['ministere_tutelle'] ?? ''), 0, 1, 'C');
$pdf->SetFont('helvetica', 'B', 11);
$pdf->Cell(0, 5, strtoupper($configUniversite['nom'] ?? 'UNIVERSITÉ'), 0, 1, 'C');

$pdf->SetFont('helvetica', '', 8);
$pdf->SetTextColor(80, 80, 80);
$contactInfo = '';
if (!empty($configUniversite['telephone'])) $contactInfo .= 'Tél: ' . $configUniversite['telephone'] . ' | ';
if (!empty($configUniversite['email'])) $contactInfo .= $configUniversite['email'];
if (!empty($contactInfo)) $pdf->Cell(0, 4, $contactInfo, 0, 1, 'C');

$pdf->Ln(2);
$pdf->SetLineStyle(['width' => 0.3, 'color' => $primaryColor]);
$pdf->Line(10, $pdf->GetY(), $pdf->getPageWidth() - 10, $pdf->GetY());

// Titre
$pdf->Ln(3);
$pdf->SetFillColor($primaryColor[0], $primaryColor[1], $primaryColor[2]);
$pdf->SetTextColor(255, 255, 255);
$pdf->SetFont('helvetica', 'B', 11);
$pdf->Cell(0, 7, 'BULLETIN DES DETTES ACADÉMIQUES' . ($annee ? ' - ' . $annee['designation'] : ''), 0, 1, 'C', 1);

// Informations étudiant
$pdf->Ln(3);
$pdf->SetFillColor(248, 249, 250);
$pdf->SetDrawColor($accentColor[0], $accentColor[1], $accentColor[2]);
$pdf->SetTextColor(60, 60, 60);
$pdf->SetFont('helvetica', '', 9);

$infoLine = 'Matricule: ' . $etudiant['matricule'] . '  |  Nom: ' . $etudiant['noms'] . 
            '  |  Promotion: ' . $etudiant['designationPromotion'] . '  |  Cycle: ' . $etudiant['cycle'];
$pdf->Cell(0, 6, $infoLine, 1, 1, 'C', 1);

// Statistiques
$pdf->Ln(3);
$pdf->SetFillColor(245, 245, 245);
$pdf->SetFont('helvetica', '', 9);

$pageWidth = $pdf->getPageWidth() - 20;
$colW = $pageWidth / 4;
$pdf->Cell($colW, 6, 'Total dettes: ' . $totalDettes, 1, 0, 'C', 1);
$pdf->SetTextColor(50, 150, 50);
$pdf->Cell($colW, 6, 'Validées: ' . $validees, 1, 0, 'C', 1);
$pdf->SetTextColor(200, 150, 0);
$pdf->Cell($colW, 6, 'En cours: ' . $enCours, 1, 0, 'C', 1);
$pdf->SetTextColor(60, 60, 60);
$pdf->Cell($colW, 6, 'Crédits restants: ' . ($totalCredits - $creditsValides) . '/' . $totalCredits, 1, 1, 'C', 1);

// Tableau des dettes par UE/ECUE
$pdf->Ln(3);
$pdf->SetFont('helvetica', 'B', 9);
$pdf->SetTextColor($primaryColor[0], $primaryColor[1], $primaryColor[2]);
$pdf->Cell(0, 5, 'DÉTAIL DES DETTES PAR UE', 0, 1, 'L');

// Calculer la hauteur disponible pour le tableau
$headerY = $pdf->GetY();
$footerHeight = 35; // Espace pour QR code et signature
$availableHeight = $pdf->getPageHeight() - $headerY - $footerHeight;

// Calculer hauteurs dynamiques selon le nombre de lignes
$totalRows = count($dettes) + count($dettesParUE); // ECUE + UE headers
$rowHeight = min(4.5, max(3.5, ($availableHeight - 6) / max(1, $totalRows)));
$ueRowHeight = $rowHeight + 0.5;

// En-têtes
$pdf->SetFillColor(240, 240, 240);
$pdf->SetFont('helvetica', 'B', 8);
$pdf->SetTextColor(60, 60, 60);

$pageWidth = $pdf->getPageWidth() - 20;
$colUE = $pageWidth * 0.44; $colSem = $pageWidth * 0.06; $colNote = $pageWidth * 0.10; 
$colCredits = $pageWidth * 0.08; $colRachat = $pageWidth * 0.10; $colStatut = $pageWidth * 0.11;
$colAnnee = $pageWidth * 0.11;

$pdf->Cell($colUE, 6, 'UE / ECUE', 1, 0, 'L', 1);
$pdf->Cell($colSem, 6, 'Sem', 1, 0, 'C', 1);
$pdf->Cell($colAnnee, 6, 'Année', 1, 0, 'C', 1);
$pdf->Cell($colNote, 6, 'Note', 1, 0, 'C', 1);
$pdf->Cell($colCredits, 6, 'Cr.', 1, 0, 'C', 1);
$pdf->Cell($colRachat, 6, 'Rachat', 1, 0, 'C', 1);
$pdf->Cell($colStatut, 6, 'Statut', 1, 1, 'C', 1);

// Contenu avec hauteur dynamique
$pdf->SetFont('helvetica', '', 8);
foreach ($dettesParUE as $ue) {
    // Ligne UE
    $pdf->SetFillColor(230, 235, 240);
    $pdf->SetFont('helvetica', 'B', 8);
    $pdf->SetTextColor(50, 50, 50);
    
    $ueLabel = $ue['codeUE'] . ' - ' . $ue['designationUE'];
    if (strlen($ueLabel) > 45) $ueLabel = substr($ueLabel, 0, 42) . '...';
    
    $pdf->Cell($colUE, $ueRowHeight, $ueLabel, 1, 0, 'L', 1);
    $pdf->Cell($colSem, $ueRowHeight, 'S' . $ue['semestre'], 1, 0, 'C', 1);
    $pdf->Cell($colAnnee + $colNote + $colCredits + $colRachat + $colStatut, $ueRowHeight, $ue['annee'], 1, 1, 'C', 1);
    
    // Lignes ECUE
    $pdf->SetFillColor(255, 255, 255);
    $pdf->SetFont('helvetica', '', 7);
    
    foreach ($ue['ecues'] as $ecue) {
        $pdf->SetTextColor(80, 80, 80);
        
        $ecueLabel = '  ' . $ecue['designationECUE'];
        if (strlen($ecueLabel) > 50) $ecueLabel = substr($ecueLabel, 0, 47) . '...';
        
        $pdf->Cell($colUE, $rowHeight, $ecueLabel, 1, 0, 'L', 0);
        $pdf->Cell($colSem, $rowHeight, '', 1, 0, 'C', 0);
        $pdf->Cell($colAnnee, $rowHeight, '', 1, 0, 'C', 0);
        
        // Note en rouge si < 10
        $note = $ecue['note_obtenue'];
        if ($note < 10) $pdf->SetTextColor(200, 50, 50);
        else $pdf->SetTextColor(80, 80, 80);
        $pdf->Cell($colNote, $rowHeight, number_format($note, 2), 1, 0, 'C', 0);
        
        $pdf->SetTextColor(80, 80, 80);
        $pdf->Cell($colCredits, $rowHeight, $ecue['credits_ecue'], 1, 0, 'C', 0);
        
        // Note de rachat
        $rachat = $ecue['note_rachat'];
        if ($rachat !== null) {
            if ($rachat >= 10) $pdf->SetTextColor(50, 150, 50);
            else $pdf->SetTextColor(200, 150, 0);
            $pdf->Cell($colRachat, $rowHeight, number_format($rachat, 2), 1, 0, 'C', 0);
        } else {
            $pdf->Cell($colRachat, $rowHeight, '-', 1, 0, 'C', 0);
        }
        
        // Statut
        $statut = $ecue['statut'];
        if ($statut == 'Validée') {
            $pdf->SetTextColor(50, 150, 50);
        } elseif ($statut == 'En cours') {
            $pdf->SetTextColor(200, 150, 0);
        } else {
            $pdf->SetTextColor(150, 150, 150);
        }
        $pdf->Cell($colStatut, $rowHeight, $statut, 1, 1, 'C', 0);
    }
}

// QR Code et Signature (positionné en bas de page)
$footerY = $pdf->getPageHeight() - 32;
$qrX = 10;

// Si le tableau dépasse, on ajuste
if ($pdf->GetY() > $footerY - 5) {
    $footerY = $pdf->GetY() + 3;
}

$pdf->SetY($footerY);

try {
    $pdf->write2DBarcode($qrData, 'QRCODE,L', $qrX, $footerY, 18, 18, [], 'N');
} catch (Exception $e) {
    $pdf->SetXY($qrX, $footerY);
    $pdf->SetFont('helvetica', '', 6);
    $pdf->Cell(18, 18, 'QR', 1, 0, 'C');
}

$pdf->SetXY($qrX, $footerY + 18);
$pdf->SetFont('helvetica', '', 5);
$pdf->SetTextColor(100, 100, 100);
$pdf->Cell(18, 2, 'Vérification', 0, 0, 'C');

// Signature à droite - Secrétaire Général Académique
$titreSecretaire = $configUniversite['titre_secretaire_general'] ?? 'Le Secrétaire Général Académique';
$nomSecretaire = $configUniversite['nom_secretaire_general'] ?? '';

$signX = $pdf->getPageWidth() - 65;
$pdf->SetXY($signX, $footerY);
$pdf->SetFont('helvetica', '', 8);
$pdf->SetTextColor(80, 80, 80);
$pdf->Cell(55, 4, 'Fait à ' . ($configUniversite['ville'] ?? '...') . ', le ' . date('d/m/Y'), 0, 1, 'R');
$pdf->SetXY($signX, $footerY + 5);
$pdf->SetFont('helvetica', 'B', 8);
$pdf->Cell(55, 4, $titreSecretaire, 0, 1, 'R');
if (!empty($nomSecretaire)) {
    $pdf->SetXY($signX, $footerY + 16);
    $pdf->SetFont('helvetica', 'I', 8);
    $pdf->Cell(55, 4, $nomSecretaire, 0, 1, 'R');
} else {
    $pdf->SetXY($signX, $footerY + 16);
    $pdf->Cell(55, 4, '_______________________', 0, 1, 'R');
}

// Footer
$pdf->SetY($pdf->getPageHeight() - 10);
$pdf->SetFont('helvetica', '', 6);
$pdf->SetTextColor(120, 120, 120);
$pdf->Cell(0, 3, 'Imprimé par ' . $_SESSION['nom'] . ' le ' . date('d/m/Y à H:i'), 0, 0, 'C');

$pdf->Output('bulletin_dettes_' . $matricule . '.pdf', 'I');
