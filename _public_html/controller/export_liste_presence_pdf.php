<?php
// Export PDF - Liste de présence vierge avec espace de signature
session_start();

require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/models/Universite.php';
require_once dirname(__DIR__) . '/vendor/autoload.php';

if (!isset($_SESSION['id'])) {
    header('Location: ../login');
    exit;
}

if (!isset($_GET['id']) || intval($_GET['id']) <= 0) {
    die('Paramètre manquant.');
}

$idSeance = intval($_GET['id']);
$db = Connexion::getInstance()->getPDO();
$universiteModel = new Universite();

// Récupérer les détails de la séance
$stmt = $db->prepare("
    SELECT sc.*, ec.\"designationECUE\", p.\"designationPromotion\", s.\"numeroSemestre\",
           sec.\"designationSection\"
    FROM seance_cours sc
    JOIN ecue ec ON sc.\"idECUE\" = ec.\"idECUE\"
    JOIN ue ON ec.\"UE_idUE\" = ue.\"idUE\"
    JOIN semestre s ON ue.semestre_idsemestre = s.idsemestre
    JOIN promotion p ON s.promotion_idpromotion = p.idpromotion
    JOIN orientation o ON p.orientation_idorientation = o.idorientation
    JOIN section sec ON o.section_idsection = sec.idsection
    WHERE sc.idseance = :idSeance
");
$stmt->bindParam(':idSeance', $idSeance, PDO::PARAM_INT);
$stmt->execute();
$seance = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$seance) {
    die('Séance non trouvée.');
}

// Récupérer les étudiants de la promotion (via ECUE -> UE -> semestre -> promotion)
$stmtEtudiants = $db->prepare("
    SELECT DISTINCT e.idetudiant, e.matricule, e.noms
    FROM etudiant e
    JOIN promotion p ON e.promotion_idpromotion = p.idpromotion
    JOIN semestre s ON s.promotion_idpromotion = p.idpromotion
    JOIN ue ON ue.semestre_idsemestre = s.idsemestre
    JOIN ecue ec ON ec.\"UE_idUE\" = ue.\"idUE\"
    WHERE ec.\"idECUE\" = :idECUE
    AND e.est_actif = 1
    ORDER BY e.noms
");
$stmtEtudiants->bindParam(':idECUE', $seance['idECUE'], PDO::PARAM_INT);
$stmtEtudiants->execute();
$etudiants = $stmtEtudiants->fetchAll(PDO::FETCH_ASSOC);

$configUniversite = $universiteModel->getConfigurationUniversite();

// Créer le PDF
$pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);

$pdf->SetCreator('eGestion');
$pdf->SetAuthor($configUniversite['nom'] ?? 'eGestion');
$pdf->SetTitle('Liste de présence - ' . $seance['designationECUE']);
$pdf->setPrintHeader(false);
$pdf->setPrintFooter(false);
$pdf->SetMargins(10, 10, 10);
$pdf->SetAutoPageBreak(true, 15);

$primaryColor = array(44, 62, 80);
$accentColor = array(0, 123, 194);

$pdf->AddPage();

// Watermark
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

// En-tête institutionnelle
$logoSize = 12;
if (!empty($configUniversite['logo'])) {
    $logoPath = dirname(__DIR__) . '/' . $configUniversite['logo'];
    if (file_exists($logoPath)) {
        $pdf->Image($logoPath, 10, 10, $logoSize, 0, '', '', '', false, 200, '', false, false, 0);
    }
}

$pdf->SetTextColor($primaryColor[0], $primaryColor[1], $primaryColor[2]);
$pdf->SetFont('helvetica', 'B', 9);
$pdf->SetY(10);
$pdf->Cell(0, 4, strtoupper($configUniversite['ministere_tutelle'] ?? ''), 0, 1, 'C');
$pdf->SetFont('helvetica', 'B', 11);
$pdf->Cell(0, 5, strtoupper($configUniversite['nom'] ?? ''), 0, 1, 'C');

if (!empty($configUniversite['sigle'])) {
    $pdf->SetFont('helvetica', 'B', 9);
    $pdf->Cell(0, 4, strtoupper($configUniversite['sigle']), 0, 1, 'C');
}

$pdf->SetFont('helvetica', '', 7);
$pdf->SetTextColor(80, 80, 80);
$contactInfo = '';
if (!empty($configUniversite['telephone'])) { $contactInfo .= 'Tél: ' . $configUniversite['telephone'] . ' '; }
if (!empty($configUniversite['email'])) { $contactInfo .= 'Email: ' . $configUniversite['email'] . ' '; }
if ($contactInfo !== '') { $pdf->Cell(0, 3, $contactInfo, 0, 1, 'C'); }

// Ligne séparatrice
$pdf->Ln(3);
$pdf->SetLineStyle(array('width' => 0.3, 'cap' => 'butt', 'join' => 'miter', 'dash' => 0, 'color' => $accentColor));
$pdf->Line(10, $pdf->GetY(), $pdf->getPageWidth() - 10, $pdf->GetY());

// Titre
$pdf->Ln(3);
$pdf->SetFillColor($primaryColor[0], $primaryColor[1], $primaryColor[2]);
$pdf->SetTextColor(255, 255, 255);
$pdf->SetFont('helvetica', 'B', 11);
$pdf->Cell(0, 7, 'LISTE DE PRÉSENCE', 0, 1, 'C', 1);

// Infos séance
$pdf->Ln(2);
$pdf->SetTextColor($primaryColor[0], $primaryColor[1], $primaryColor[2]);
$pdf->SetFont('helvetica', 'B', 9);

$joursFrancais = ['Monday'=>'Lundi','Tuesday'=>'Mardi','Wednesday'=>'Mercredi','Thursday'=>'Jeudi','Friday'=>'Vendredi','Saturday'=>'Samedi','Sunday'=>'Dimanche'];
$jourSemaine = $joursFrancais[date('l', strtotime($seance['date_seance']))] ?? '';
$dateFormatted = $jourSemaine . ' ' . date('d/m/Y', strtotime($seance['date_seance']));
$heureFormatted = substr($seance['heure_debut'], 0, 5) . ' - ' . substr($seance['heure_fin'], 0, 5);

$col1 = 30;
$col2 = 65;
$col3 = 30;
$col4 = 55;

$pdf->SetFont('helvetica', 'B', 8);
$pdf->Cell($col1, 5, 'Cours :', 0, 0);
$pdf->SetFont('helvetica', '', 8);
$pdf->Cell($col2, 5, $seance['designationECUE'], 0, 0);
$pdf->SetFont('helvetica', 'B', 8);
$pdf->Cell($col3, 5, 'Promotion :', 0, 0);
$pdf->SetFont('helvetica', '', 8);
$pdf->Cell($col4, 5, $seance['designationPromotion'], 0, 1);

$pdf->SetFont('helvetica', 'B', 8);
$pdf->Cell($col1, 5, 'Date :', 0, 0);
$pdf->SetFont('helvetica', '', 8);
$pdf->Cell($col2, 5, $dateFormatted, 0, 0);
$pdf->SetFont('helvetica', 'B', 8);
$pdf->Cell($col3, 5, 'Horaire :', 0, 0);
$pdf->SetFont('helvetica', '', 8);
$pdf->Cell($col4, 5, $heureFormatted, 0, 1);

$pdf->SetFont('helvetica', 'B', 8);
$pdf->Cell($col1, 5, 'Salle :', 0, 0);
$pdf->SetFont('helvetica', '', 8);
$pdf->Cell($col2, 5, $seance['salle'] ?? '-', 0, 0);
$pdf->SetFont('helvetica', 'B', 8);
$pdf->Cell($col3, 5, 'Section :', 0, 0);
$pdf->SetFont('helvetica', '', 8);
$pdf->Cell($col4, 5, $seance['designationSection'], 0, 1);

$pdf->Ln(3);

// Tableau des étudiants
$contentWidth = $pdf->getPageWidth() - 20;
$wNum = 10;
$wMat = 30;
$wNom = $contentWidth - $wNum - $wMat - 40; // espace restant pour le nom
$wSign = 40; // colonne signature

// En-têtes du tableau
$pdf->SetFillColor(240, 240, 240);
$pdf->SetTextColor(60, 60, 60);
$pdf->SetFont('helvetica', 'B', 8);
$pdf->SetDrawColor(180, 180, 180);

$headerY = $pdf->GetY();

$pdf->Cell($wNum, 6, 'N°', 1, 0, 'C', 1);
$pdf->Cell($wMat, 6, 'Matricule', 1, 0, 'C', 1);
$pdf->Cell($wNom, 6, 'Noms & Prénoms', 1, 0, 'C', 1);
$pdf->Cell($wSign, 6, 'Signature', 1, 1, 'C', 1);

// Lignes étudiants
$pdf->SetFont('helvetica', '', 8);
$pdf->SetTextColor(50, 50, 50);
$fill = false;
$i = 1;
$rowHeight = 8;

foreach ($etudiants as $etudiant) {
    // Vérifier saut de page
    if ($pdf->GetY() + $rowHeight > $pdf->getPageHeight() - 20) {
        $pdf->AddPage();
        // Ré-afficher les en-têtes
        $pdf->SetFillColor(240, 240, 240);
        $pdf->SetTextColor(60, 60, 60);
        $pdf->SetFont('helvetica', 'B', 8);
        $pdf->Cell($wNum, 6, 'N°', 1, 0, 'C', 1);
        $pdf->Cell($wMat, 6, 'Matricule', 1, 0, 'C', 1);
        $pdf->Cell($wNom, 6, 'Noms & Prénoms', 1, 0, 'C', 1);
        $pdf->Cell($wSign, 6, 'Signature', 1, 1, 'C', 1);
        $pdf->SetFont('helvetica', '', 8);
        $pdf->SetTextColor(50, 50, 50);
        $fill = false;
    }

    $pdf->SetFillColor($fill ? 248 : 255, $fill ? 249 : 255, $fill ? 250 : 255);
    $pdf->Cell($wNum, $rowHeight, $i++, 1, 0, 'C', 1);
    $pdf->Cell($wMat, $rowHeight, $etudiant['matricule'] ?? '', 1, 0, 'C', 1);
    $pdf->Cell($wNom, $rowHeight, $etudiant['noms'] ?? '', 1, 0, 'L', 1);
    $pdf->Cell($wSign, $rowHeight, '', 1, 1, 'C', 1); // Espace vide pour signature
    $fill = !$fill;
}

// Total
$pdf->Ln(3);
$pdf->SetFont('helvetica', 'B', 8);
$pdf->SetTextColor($primaryColor[0], $primaryColor[1], $primaryColor[2]);
$pdf->Cell(0, 5, 'Total étudiants : ' . count($etudiants), 0, 1, 'L');

// Espace signature enseignant
$pdf->Ln(10);
$pdf->SetFont('helvetica', 'B', 9);
$pdf->Cell(95, 5, 'Signature de l\'enseignant :', 0, 0, 'L');
$pdf->Cell(95, 5, 'Visa du chef de section :', 0, 1, 'L');
$pdf->Ln(20);
$pdf->SetDrawColor(150, 150, 150);
$pdf->Line(10, $pdf->GetY(), 95, $pdf->GetY());
$pdf->Line(105, $pdf->GetY(), 190, $pdf->GetY());

// Sortie PDF
while (ob_get_level() > 0) { ob_end_clean(); }
if (function_exists('apache_setenv')) { @apache_setenv('no-gzip', '1'); }
@ini_set('zlib.output_compression', 'Off');

$filename = 'liste_presence_' . $seance['designationECUE'] . '_' . $seance['date_seance'] . '.pdf';
$pdf->Output($filename, 'I');
exit;
