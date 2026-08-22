<?php
session_start();
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/models/Universite.php';
require_once dirname(__DIR__) . '/vendor/autoload.php';

if (!isset($_SESSION['id'])) {
    header('Location: ../login');
    exit();
}

$db = Connexion::getInstance()->getPDO();
$universiteModel = new Universite();

if (!isset($_GET['id']) || intval($_GET['id']) <= 0) {
    $_SESSION['swal_error'] = ['title' => 'Erreur', 'text' => 'Identifiant de séance invalide.', 'icon' => 'error'];
    header('Location: ../cours/seances.list');
    exit();
}

$idSeance = intval($_GET['id']);

try {
    // Récupérer les détails de la séance (sans enseignant_ecue)
    $stmt = $db->prepare("
        SELECT sc.*, ec.\"designationECUE\", p.\"designationPromotion\", s.\"numeroSemestre\",
               sec.\"designationSection\", ue.\"designationUE\"
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
        throw new Exception("Séance de cours non trouvée.");
    }

    // Récupérer l'année académique
    $stmtAnnee = $db->prepare("SELECT designation FROM annee_acad WHERE idannee_acad = :id");
    $stmtAnnee->bindParam(':id', $seance['annee_acad_id'], PDO::PARAM_INT);
    $stmtAnnee->execute();
    $annee = $stmtAnnee->fetch(PDO::FETCH_ASSOC);
    $anneeDesignation = $annee ? $annee['designation'] : '';

    $configUniversite = $universiteModel->getConfigurationUniversite();
    $GLOBALS['configUniversite'] = $configUniversite;

    // Classe PDF avec footer
    class MYPDF extends TCPDF {
        public function Footer() {
            $this->SetY(-12);
            $this->SetLineStyle(array('width' => 0.2, 'color' => array(200, 200, 200)));
            $this->Line(15, $this->GetY(), $this->getPageWidth() - 15, $this->GetY());
            $this->SetFont('helvetica', 'I', 7);
            $this->SetTextColor(120, 120, 120);
            $cfg = $GLOBALS['configUniversite'] ?? [];
            $this->Cell(0, 4, 'Document généré le ' . date('d/m/Y à H:i') . ' | ' . ($cfg['nom'] ?? 'eGestion'), 0, 0, 'C');
        }
    }

    $pdf = new MYPDF('P', 'mm', 'A4', true, 'UTF-8', false);
    $pdf->SetCreator('eGestion');
    $pdf->SetAuthor($configUniversite['nom'] ?? 'eGestion');
    $pdf->SetTitle('QR Code - ' . $seance['designationECUE']);
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(true);
    $pdf->SetMargins(10, 10, 10);
    $pdf->SetAutoPageBreak(true, 20);

    $primaryColor = array(44, 62, 80);
    $accentColor = array(0, 123, 194);

    $pdf->AddPage();

    // Watermark
    if (!empty($configUniversite['logo'])) {
        $logoPath = dirname(__DIR__) . '/' . $configUniversite['logo'];
        if (file_exists($logoPath)) {
            if (method_exists($pdf, 'SetAlpha')) { $pdf->SetAlpha(0.08); }
            $cx = ($pdf->getPageWidth() - 60) / 2;
            $cy = ($pdf->getPageHeight() - 60) / 2;
            $pdf->Image($logoPath, $cx, $cy, 60, 0, '', '', '', false, 200, '', false, false, 0);
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
    if (!empty($configUniversite['telephone'])) { $contactInfo .= 'Tél: ' . $configUniversite['telephone'] . '  '; }
    if (!empty($configUniversite['email'])) { $contactInfo .= 'Email: ' . $configUniversite['email']; }
    if ($contactInfo !== '') { $pdf->Cell(0, 3, $contactInfo, 0, 1, 'C'); }

    // Ligne séparatrice
    $pdf->Ln(3);
    $pdf->SetLineStyle(array('width' => 0.3, 'color' => $accentColor));
    $pdf->Line(10, $pdf->GetY(), $pdf->getPageWidth() - 10, $pdf->GetY());

    // Titre
    $pdf->Ln(3);
    $pdf->SetFillColor($primaryColor[0], $primaryColor[1], $primaryColor[2]);
    $pdf->SetTextColor(255, 255, 255);
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->Cell(0, 8, 'FICHE DE PRÉSENCE - QR CODE', 0, 1, 'C', 1);

    // Infos séance
    $pdf->Ln(3);
    $pdf->SetTextColor($primaryColor[0], $primaryColor[1], $primaryColor[2]);

    $joursFrancais = ['Monday'=>'Lundi','Tuesday'=>'Mardi','Wednesday'=>'Mercredi','Thursday'=>'Jeudi','Friday'=>'Vendredi','Saturday'=>'Samedi','Sunday'=>'Dimanche'];
    $jourSemaine = $joursFrancais[date('l', strtotime($seance['date_seance']))] ?? '';
    $dateFormatted = $jourSemaine . ' ' . date('d/m/Y', strtotime($seance['date_seance']));
    $heureFormatted = substr($seance['heure_debut'], 0, 5) . ' - ' . substr($seance['heure_fin'], 0, 5);

    $col1 = 30;
    $col2 = 65;
    $col3 = 30;
    $col4 = 55;

    $pdf->SetFont('helvetica', 'B', 9);
    $pdf->Cell($col1, 5, 'Cours :', 0, 0);
    $pdf->SetFont('helvetica', '', 9);
    $pdf->Cell($col2, 5, $seance['designationECUE'], 0, 0);
    $pdf->SetFont('helvetica', 'B', 9);
    $pdf->Cell($col3, 5, 'Promotion :', 0, 0);
    $pdf->SetFont('helvetica', '', 9);
    $pdf->Cell($col4, 5, $seance['designationPromotion'], 0, 1);

    $pdf->SetFont('helvetica', 'B', 9);
    $pdf->Cell($col1, 5, 'Date :', 0, 0);
    $pdf->SetFont('helvetica', '', 9);
    $pdf->Cell($col2, 5, $dateFormatted, 0, 0);
    $pdf->SetFont('helvetica', 'B', 9);
    $pdf->Cell($col3, 5, 'Horaire :', 0, 0);
    $pdf->SetFont('helvetica', '', 9);
    $pdf->Cell($col4, 5, $heureFormatted, 0, 1);

    $pdf->SetFont('helvetica', 'B', 9);
    $pdf->Cell($col1, 5, 'Salle :', 0, 0);
    $pdf->SetFont('helvetica', '', 9);
    $pdf->Cell($col2, 5, $seance['salle'] ?? '-', 0, 0);
    $pdf->SetFont('helvetica', 'B', 9);
    $pdf->Cell($col3, 5, 'Section :', 0, 0);
    $pdf->SetFont('helvetica', '', 9);
    $pdf->Cell($col4, 5, $seance['designationSection'], 0, 1);

    $pdf->SetFont('helvetica', 'B', 9);
    $pdf->Cell($col1, 5, 'Séance :', 0, 0);
    $pdf->SetFont('helvetica', '', 9);
    $pdf->Cell($col2, 5, $seance['titre'], 0, 0);
    $pdf->SetFont('helvetica', 'B', 9);
    $pdf->Cell($col3, 5, 'Année acad. :', 0, 0);
    $pdf->SetFont('helvetica', '', 9);
    $pdf->Cell($col4, 5, $anneeDesignation, 0, 1);

    if (!empty($seance['description'])) {
        $pdf->SetFont('helvetica', 'B', 9);
        $pdf->Cell($col1, 5, 'Description :', 0, 0);
        $pdf->SetFont('helvetica', '', 9);
        $pdf->Cell(0, 5, $seance['description'], 0, 1);
    }

    // Ligne séparatrice
    $pdf->Ln(3);
    $pdf->SetLineStyle(array('width' => 0.2, 'color' => array(200, 200, 200)));
    $pdf->Line(10, $pdf->GetY(), $pdf->getPageWidth() - 10, $pdf->GetY());

    // QR Code
    $pdf->Ln(5);
    $pdf->SetTextColor($primaryColor[0], $primaryColor[1], $primaryColor[2]);
    $pdf->SetFont('helvetica', 'B', 11);
    $pdf->Cell(0, 6, 'SCANNEZ CE CODE POUR MARQUER VOTRE PRÉSENCE', 0, 1, 'C');
    $pdf->Ln(3);

    // Données QR code
    if (empty($seance['qrcode'])) {
        $seance['qrcode'] = 'SEA_' . $idSeance . '_' . time() . '_' . rand(1000, 9999);
        $updateStmt = $db->prepare("UPDATE seance_cours SET qrcode = :qrcode WHERE idseance = :id");
        $updateStmt->execute(['qrcode' => $seance['qrcode'], 'id' => $idSeance]);
    }

    $qrCodePayload = json_encode([
        'type' => 'presence_cours',
        'seance_id' => $idSeance,
        'ecue_id' => $seance['idECUE'],
        'date' => $seance['date_seance'],
        'code' => $seance['qrcode'],
        'timestamp' => time()
    ]);

    $qrSize = 70;
    $xPos = ($pdf->getPageWidth() - $qrSize) / 2;

    $style = array(
        'border' => 2,
        'vpadding' => 'auto',
        'hpadding' => 'auto',
        'fgcolor' => array(0, 0, 0),
        'bgcolor' => array(255, 255, 255),
        'module_width' => 1,
        'module_height' => 1
    );

    $pdf->write2DBarcode($qrCodePayload, 'QRCODE,L', $xPos, $pdf->GetY(), $qrSize, $qrSize, $style, 'N');
    $pdf->SetY($pdf->GetY() + $qrSize + 5);

    // Instructions
    $pdf->SetFont('helvetica', '', 9);
    $pdf->SetTextColor(80, 80, 80);
    $pdf->MultiCell(0, 5, "1. Scannez ce QR code avec l'application de l'université.\n2. Votre présence sera automatiquement enregistrée.\n3. Ce code est valide uniquement pour cette séance.", 0, 'C');

    $pdf->Ln(3);
    $pdf->SetFont('helvetica', 'I', 8);
    $pdf->SetTextColor(150, 150, 150);
    $pdf->Cell(0, 5, 'Valide le ' . date('d/m/Y', strtotime($seance['date_seance'])) . ' de ' . substr($seance['heure_debut'], 0, 5) . ' à ' . substr($seance['heure_fin'], 0, 5) . ' | Ne pas partager ce QR code.', 0, 1, 'C');

    // Sortie
    while (ob_get_level() > 0) { ob_end_clean(); }
    $pdf->Output('qrcode_presence_' . $idSeance . '.pdf', 'I');
    exit();

} catch (Exception $e) {
    $_SESSION['swal_error'] = ['title' => 'Erreur', 'text' => $e->getMessage(), 'icon' => 'error'];
    header('Location: ../cours/seances.list');
    exit();
}
