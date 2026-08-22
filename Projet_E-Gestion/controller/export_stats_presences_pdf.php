<?php
// Export PDF - Statistiques de présences par étudiant pour un ECUE
session_start();

require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/models/Universite.php';
require_once dirname(__DIR__) . '/vendor/autoload.php';

if (!isset($_SESSION['id'])) {
    header('Location: ../login');
    exit;
}

$ecue_id = isset($_GET['ecue_id']) ? intval($_GET['ecue_id']) : 0;
$annee_id = isset($_GET['annee_id']) ? intval($_GET['annee_id']) : 0;

if ($ecue_id <= 0 || $annee_id <= 0) {
    die('Paramètres manquants.');
}

$db = Connexion::getInstance()->getPDO();
$universiteModel = new Universite();

// Récupérer les infos de l'ECUE
$stmtEcue = $db->prepare("
    SELECT e.\"designationECUE\", p.\"designationPromotion\", s.\"numeroSemestre\", sec.\"designationSection\"
    FROM ecue e
    JOIN ue u ON e.\"UE_idUE\" = u.\"idUE\"
    JOIN semestre s ON u.semestre_idsemestre = s.idsemestre
    JOIN promotion p ON s.promotion_idpromotion = p.idpromotion
    JOIN orientation o ON p.orientation_idorientation = o.idorientation
    JOIN section sec ON o.section_idsection = sec.idsection
    WHERE e.\"idECUE\" = :ecueId
");
$stmtEcue->bindParam(':ecueId', $ecue_id, PDO::PARAM_INT);
$stmtEcue->execute();
$ecue = $stmtEcue->fetch(PDO::FETCH_ASSOC);

if (!$ecue) {
    die('ECUE non trouvé.');
}

// Année académique
$stmtAnnee = $db->prepare("SELECT designation FROM annee_acad WHERE idannee_acad = :anneeId");
$stmtAnnee->bindParam(':anneeId', $annee_id, PDO::PARAM_INT);
$stmtAnnee->execute();
$annee = $stmtAnnee->fetch(PDO::FETCH_ASSOC);
$anneeDesignation = $annee ? $annee['designation'] : '';

// Total séances
$stmtSeances = $db->prepare("SELECT COUNT(*) FROM seance_cours WHERE \"idECUE\" = ? AND annee_acad_id = ?");
$stmtSeances->execute([$ecue_id, $annee_id]);
$total_seances = (int)$stmtSeances->fetchColumn();

// Promotion liée
$stmtPromo = $db->prepare("
    SELECT s.promotion_idpromotion
    FROM ecue e
    JOIN ue u ON e.\"UE_idUE\" = u.\"idUE\"
    JOIN semestre s ON u.semestre_idsemestre = s.idsemestre
    WHERE e.\"idECUE\" = ?
    LIMIT 1
");
$stmtPromo->execute([$ecue_id]);
$promotion_id = $stmtPromo->fetchColumn();

// Étudiants actifs
$etudiants = [];
if ($promotion_id) {
    $stmtEtudiants = $db->prepare("
        SELECT idetudiant, matricule, noms
        FROM etudiant
        WHERE promotion_idpromotion = ? AND est_actif = 1
        ORDER BY noms ASC
    ");
    $stmtEtudiants->execute([$promotion_id]);
    $etudiants = $stmtEtudiants->fetchAll(PDO::FETCH_ASSOC);
}

$total_etudiants = count($etudiants);

// Compter les présences par étudiant
$stmtPresEtudiant = $db->prepare("
    SELECT COUNT(*) FROM presence_cours pc
    JOIN seance_cours sc ON pc.idseance = sc.idseance
    WHERE pc.idetudiant = ? AND sc.\"idECUE\" = ? AND sc.annee_acad_id = ?
");

$statsEtudiants = [];
$totalPresences = 0;
foreach ($etudiants as $etudiant) {
    $stmtPresEtudiant->execute([$etudiant['idetudiant'], $ecue_id, $annee_id]);
    $nb = (int)$stmtPresEtudiant->fetchColumn();
    $taux = $total_seances > 0 ? round(($nb / $total_seances) * 100, 1) : 0;
    $totalPresences += $nb;
    $statsEtudiants[] = [
        'matricule' => $etudiant['matricule'],
        'noms' => $etudiant['noms'],
        'nb_present' => $nb,
        'taux' => $taux
    ];
}

$tauxMoyen = ($total_seances > 0 && $total_etudiants > 0)
    ? round(($totalPresences / ($total_seances * $total_etudiants)) * 100, 1)
    : 0;

$configUniversite = $universiteModel->getConfigurationUniversite();

// Créer le PDF
$pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);

$pdf->SetCreator('eGestion');
$pdf->SetAuthor($configUniversite['nom'] ?? 'eGestion');
$pdf->SetTitle('Statistiques présences - ' . $ecue['designationECUE']);
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
$pdf->Cell(0, 7, 'STATISTIQUES DE PRÉSENCES', 0, 1, 'C', 1);

// Infos du cours
$pdf->Ln(2);
$pdf->SetTextColor($primaryColor[0], $primaryColor[1], $primaryColor[2]);

$col1 = 30;
$col2 = 65;
$col3 = 30;
$col4 = 55;

$pdf->SetFont('helvetica', 'B', 8);
$pdf->Cell($col1, 5, 'Cours :', 0, 0);
$pdf->SetFont('helvetica', '', 8);
$pdf->Cell($col2, 5, $ecue['designationECUE'], 0, 0);
$pdf->SetFont('helvetica', 'B', 8);
$pdf->Cell($col3, 5, 'Promotion :', 0, 0);
$pdf->SetFont('helvetica', '', 8);
$pdf->Cell($col4, 5, $ecue['designationPromotion'], 0, 1);

$pdf->SetFont('helvetica', 'B', 8);
$pdf->Cell($col1, 5, 'Section :', 0, 0);
$pdf->SetFont('helvetica', '', 8);
$pdf->Cell($col2, 5, $ecue['designationSection'], 0, 0);
$pdf->SetFont('helvetica', 'B', 8);
$pdf->Cell($col3, 5, 'Année acad. :', 0, 0);
$pdf->SetFont('helvetica', '', 8);
$pdf->Cell($col4, 5, $anneeDesignation, 0, 1);

// Résumé
$pdf->Ln(2);
$pdf->SetFillColor(240, 248, 255);
$pdf->SetDrawColor(180, 200, 220);
$pdf->SetFont('helvetica', 'B', 8);
$pdf->SetTextColor($primaryColor[0], $primaryColor[1], $primaryColor[2]);

$summaryW = ($pdf->getPageWidth() - 20) / 4;
$pdf->Cell($summaryW, 6, 'Séances : ' . $total_seances, 1, 0, 'C', 1);
$pdf->Cell($summaryW, 6, 'Étudiants : ' . $total_etudiants, 1, 0, 'C', 1);
$pdf->Cell($summaryW, 6, 'Présences : ' . $totalPresences, 1, 0, 'C', 1);
$pdf->Cell($summaryW, 6, 'Taux moyen : ' . $tauxMoyen . '%', 1, 1, 'C', 1);

$pdf->Ln(3);

// Tableau des étudiants
$contentWidth = $pdf->getPageWidth() - 20;
$wNum = 10;
$wMat = 28;
$wNom = $contentWidth - $wNum - $wMat - 25 - 25 - 25;
$wPres = 25;
$wTotal = 25;
$wTaux = 25;

// En-têtes du tableau
$pdf->SetFillColor(240, 240, 240);
$pdf->SetTextColor(60, 60, 60);
$pdf->SetFont('helvetica', 'B', 8);
$pdf->SetDrawColor(180, 180, 180);

$pdf->Cell($wNum, 6, 'N°', 1, 0, 'C', 1);
$pdf->Cell($wMat, 6, 'Matricule', 1, 0, 'C', 1);
$pdf->Cell($wNom, 6, 'Noms & Prénoms', 1, 0, 'C', 1);
$pdf->Cell($wPres, 6, 'Présences', 1, 0, 'C', 1);
$pdf->Cell($wTotal, 6, 'Tot. séances', 1, 0, 'C', 1);
$pdf->Cell($wTaux, 6, 'Taux', 1, 1, 'C', 1);

// Lignes étudiants
$pdf->SetFont('helvetica', '', 8);
$pdf->SetTextColor(50, 50, 50);
$fill = false;
$i = 1;
$rowHeight = 6;

foreach ($statsEtudiants as $etud) {
    // Vérifier saut de page
    if ($pdf->GetY() + $rowHeight > $pdf->getPageHeight() - 20) {
        $pdf->AddPage();
        $pdf->SetFillColor(240, 240, 240);
        $pdf->SetTextColor(60, 60, 60);
        $pdf->SetFont('helvetica', 'B', 8);
        $pdf->SetDrawColor(180, 180, 180);
        $pdf->Cell($wNum, 6, 'N°', 1, 0, 'C', 1);
        $pdf->Cell($wMat, 6, 'Matricule', 1, 0, 'C', 1);
        $pdf->Cell($wNom, 6, 'Noms & Prénoms', 1, 0, 'C', 1);
        $pdf->Cell($wPres, 6, 'Présences', 1, 0, 'C', 1);
        $pdf->Cell($wTotal, 6, 'Tot. séances', 1, 0, 'C', 1);
        $pdf->Cell($wTaux, 6, 'Taux', 1, 1, 'C', 1);
        $pdf->SetFont('helvetica', '', 8);
        $pdf->SetTextColor(50, 50, 50);
        $fill = false;
    }

    // Couleur du taux
    $tauxColor = $etud['taux'] >= 75 ? array(39, 174, 96) : ($etud['taux'] >= 50 ? array(243, 156, 18) : array(231, 76, 60));

    $pdf->SetFillColor($fill ? 248 : 255, $fill ? 249 : 255, $fill ? 250 : 255);
    $pdf->Cell($wNum, $rowHeight, $i++, 1, 0, 'C', 1);
    $pdf->Cell($wMat, $rowHeight, $etud['matricule'] ?? '', 1, 0, 'C', 1);
    $pdf->Cell($wNom, $rowHeight, $etud['noms'] ?? '', 1, 0, 'L', 1);
    $pdf->Cell($wPres, $rowHeight, $etud['nb_present'], 1, 0, 'C', 1);
    $pdf->Cell($wTotal, $rowHeight, $total_seances, 1, 0, 'C', 1);

    // Taux avec couleur
    $pdf->SetTextColor($tauxColor[0], $tauxColor[1], $tauxColor[2]);
    $pdf->SetFont('helvetica', 'B', 8);
    $pdf->Cell($wTaux, $rowHeight, $etud['taux'] . '%', 1, 1, 'C', 1);
    $pdf->SetFont('helvetica', '', 8);
    $pdf->SetTextColor(50, 50, 50);

    $fill = !$fill;
}

// Total
$pdf->Ln(3);
$pdf->SetFont('helvetica', 'B', 8);
$pdf->SetTextColor($primaryColor[0], $primaryColor[1], $primaryColor[2]);
$pdf->Cell(0, 5, 'Total étudiants : ' . $total_etudiants . '  |  Total séances : ' . $total_seances . '  |  Taux moyen : ' . $tauxMoyen . '%', 0, 1, 'L');

// Pied de page
$pdf->Ln(8);
$pdf->SetFont('helvetica', '', 7);
$pdf->SetTextColor(120, 120, 120);
$pdf->Cell(0, 4, 'Document généré le ' . date('d/m/Y à H:i') . ' par eGestion', 0, 1, 'C');

// Sortie PDF
while (ob_get_level() > 0) { ob_end_clean(); }
if (function_exists('apache_setenv')) { @apache_setenv('no-gzip', '1'); }
@ini_set('zlib.output_compression', 'Off');

$filename = 'stats_presences_' . preg_replace('/[^a-zA-Z0-9_]/', '_', $ecue['designationECUE']) . '_' . $anneeDesignation . '.pdf';
$pdf->Output($filename, 'I');
exit;
