<?php
session_start();
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/models/Recours.php';
require_once dirname(__DIR__) . '/models/Universite.php';
require_once dirname(__DIR__) . '/vendor/autoload.php'; // Pour TCPDF

// Vérifications de sécurité
if (!isset($_SESSION['student_id']) || !isset($_GET['id']) || empty($_GET['id'])) {
    header('Location: ../portail/login');
    exit();
}

$recoursId = intval($_GET['id']);
$studentMatricule = $_SESSION['student_matricule'] ?? '';

// Connexion à la base de données
$conn = Connexion::getInstance()->getPDO();

// Récupérer les informations du recours (requête complète)
$query_recours = '
    SELECT r.id_recours, r.matricule, r.motif, r.description, r.date_creation, r.statut,
           r.preuve, r.est_paye, r.id_ecue, r.id_session, r.id_annee_acad,
           e."designationECUE", ue."designationUE", s."designSession", s.description as descSession,
           et.noms,
           p."designationPromotion", o."designationOrientation",
           a.designation as annee_academique
    FROM recours r
    JOIN etudiant et ON r.matricule = et.matricule
    JOIN ecue e ON r.id_ecue = e."idECUE"
    JOIN ue ON e."UE_idUE" = ue."idUE"
    JOIN session s ON r.id_session = s.idsession
    JOIN promotion p ON et.promotion_idpromotion = p.idpromotion
    JOIN orientation o ON p.orientation_idorientation = o.idorientation
    JOIN annee_acad a ON r.id_annee_acad = a.idannee_acad
    WHERE r.id_recours = :id AND r.matricule = :matricule';

$stmt_recours = $conn->prepare($query_recours);
$stmt_recours->bindParam(':id', $recoursId, PDO::PARAM_INT);
$stmt_recours->bindParam(':matricule', $studentMatricule, PDO::PARAM_STR);
$stmt_recours->execute();
$recours = $stmt_recours->fetch(PDO::FETCH_ASSOC);

// Vérification de sécurité
if (!$recours) {
    die("Accès non autorisé");
}

// Récupérer les informations de l'établissement
$query_etablissement = "SELECT * FROM configuration_universite LIMIT 1";
$stmt_etablissement = $conn->prepare($query_etablissement);
$stmt_etablissement->execute();
$etablissement = $stmt_etablissement->fetch(PDO::FETCH_ASSOC);

// Classe TCPDF personnalisée pour le pied de page
class MYPDF extends TCPDF {
    // Pied de page personnalisé
    public function Footer() {
        // Position à 15mm du bas
        $this->SetY(-15);
        
        // Ligne de séparation fine
        $this->SetLineStyle(array('width' => 0.2, 'cap' => 'butt', 'join' => 'miter', 'dash' => 0, 'color' => array(200, 200, 200)));
        $this->Line(15, $this->GetY(), $this->getPageWidth() - 15, $this->GetY());
        
        // Police et couleur
        $this->SetFont('helvetica', 'I', 8);
        $this->SetTextColor(100, 100, 100);
        
        // Numéro de page
        $this->Cell(0, 5, 'Page ' . $this->getAliasNumPage() . '/' . $this->getAliasNbPages(), 0, false, 'R', 0, '', 0, false, 'T', 'M');
        
        // Date et signature électronique
        $this->SetX(15);
        $this->Cell(($this->getPageWidth() - 30) / 2, 5, 'Document généré le ' . date('d/m/Y H:i'), 0, 0, 'L');
    }
}

// Créer une instance de TCPDF
$pdf = new MYPDF('P', 'mm', 'A4', true, 'UTF-8', false);

// Configuration de base du document
$pdf->SetCreator('eGestion');
$pdf->SetAuthor($etablissement['nom'] ?? 'eGestion');
$pdf->SetTitle('Recours - ' . $recours['designationECUE']);
$pdf->setPrintHeader(false);
$pdf->setPrintFooter(true);
$pdf->SetMargins(15, 15, 15);
$pdf->SetAutoPageBreak(true, 20);

// Couleurs pour le design
$primaryColor = array(0, 87, 146); // Bleu foncé
$secondaryColor = array(70, 130, 180); // Bleu acier
$accentColor = array(0, 121, 194); // Bleu moyen

// Ajouter une page
$pdf->AddPage();

// En-tête avec les informations de l'université
if ($etablissement) {
    // Logo de l'université
    if (!empty($etablissement['logo'])) {
        $logoPath = dirname(__DIR__) . '/' . $etablissement['logo'];
        if (file_exists($logoPath)) {
            $pdf->Image($logoPath, 15, 5, 15, 0, '', '', '', false, 200, '', false, false, 0);
        }
    }
    
    // Titre et informations de l'université
    $pdf->SetTextColor($primaryColor[0], $primaryColor[1], $primaryColor[2]);
    $pdf->SetFont('helvetica', 'B', 11);
    $pdf->SetXY(40, 10);
    $pdf->Cell(0, 6, strtoupper($etablissement['ministere_tutelle'] ?? ''), 0, 1, 'C');
    
    $pdf->SetFont('helvetica', 'B', 13);
    $pdf->SetXY(40, 16);
    $pdf->Cell(0, 6, strtoupper($etablissement['nom'] ?? ''), 0, 1, 'C');
    
    $pdf->SetFont('helvetica', '', 9);
    $pdf->SetTextColor(80, 80, 80);
    if (!empty($etablissement['adresse'])) {
        $pdf->SetXY(40, 22);
        $pdf->Cell(0, 5, $etablissement['adresse'], 0, 1, 'C');
    }
    
    // Ligne de séparation
    $pdf->SetLineStyle(array('width' => 0.5, 'cap' => 'butt', 'join' => 'miter', 'dash' => 0, 'color' => $accentColor));
    $pdf->Line(15, 30, $pdf->getPageWidth() - 15, 30);
}

// Titre du document
$pdf->SetFillColor($primaryColor[0], $primaryColor[1], $primaryColor[2]);
$pdf->SetTextColor(255, 255, 255);
$pdf->SetFont('helvetica', 'B', 14);
$pdf->Ln(5);
$pdf->Cell(0, 10, 'FICHE DE RECOURS N°'. sprintf('REC-%05d', $recours['id_recours']), 0, 1, 'C', 1);

// Informations principales en format compact (2 colonnes)
$pdf->Ln(5);
$pdf->SetFont('helvetica', 'B', 10);
$pdf->SetTextColor($primaryColor[0], $primaryColor[1], $primaryColor[2]);
$pdf->Cell(90, 6, 'INFORMATIONS ÉTUDIANT', 0, 0);
$pdf->Cell(90, 6, 'INFORMATIONS COURS', 0, 1);

$pdf->SetDrawColor(220, 220, 220);
$pdf->Line(15, $pdf->GetY(), 105, $pdf->GetY());
$pdf->Line(105, $pdf->GetY()-6, 105, $pdf->GetY()+30);
$pdf->Line(105, $pdf->GetY(), 195, $pdf->GetY());

$pdf->SetTextColor(80, 80, 80);
$pdf->SetFont('helvetica', '', 9);

// Fonction pour tronquer le texte
function truncateText($text, $maxLength = 40) {
    return (strlen($text) > $maxLength) ? substr($text, 0, $maxLength) . '...' : $text;
}

// Informations étudiant
$pdf->Ln(2);
$pdf->Cell(25, 5, 'Matricule:', 0, 0);
$pdf->Cell(65, 5, $recours['matricule'], 0, 0);

// Informations cours (avec troncature)
$pdf->Cell(25, 5, 'Cours:', 0, 0);
$pdf->Cell(65, 5, truncateText($recours['designationECUE'], 35), 0, 1);

$pdf->Cell(25, 5, 'Nom:', 0, 0);
$pdf->Cell(65, 5, truncateText($recours['noms'], 35), 0, 0);
$pdf->Cell(25, 5, 'UE:', 0, 0);
$pdf->Cell(65, 5, truncateText($recours['designationUE'], 35), 0, 1);

$pdf->Cell(25, 5, 'Promotion:', 0, 0);
$pdf->Cell(65, 5, truncateText($recours['designationPromotion'], 35), 0, 0);
$pdf->Cell(25, 5, 'Session:', 0, 0);
$pdf->Cell(65, 5, truncateText($recours['descSession'], 35), 0, 1);

$pdf->Cell(25, 5, 'Orientation:', 0, 0);
$pdf->Cell(65, 5, truncateText($recours['designationOrientation'], 35), 0, 0);
$pdf->Cell(25, 5, 'Année acad.:', 0, 0);
$pdf->Cell(65, 5, $recours['annee_academique'], 0, 1);

// Détails du recours
$pdf->Ln(8);
$pdf->SetFont('helvetica', 'B', 10);
$pdf->SetTextColor($primaryColor[0], $primaryColor[1], $primaryColor[2]);
$pdf->Cell(0, 6, 'DÉTAILS DU RECOURS', 0, 1);
$pdf->SetDrawColor(220, 220, 220);
$pdf->Line(15, $pdf->GetY(), 195, $pdf->GetY());

$pdf->SetTextColor(80, 80, 80);
$pdf->SetFont('helvetica', '', 9);
$pdf->Ln(2);
$pdf->Cell(25, 5, 'Date:', 0, 0);
$pdf->Cell(65, 5, date('d/m/Y H:i', strtotime($recours['date_creation'])), 0, 0);
$pdf->Cell(25, 5, 'Motif:', 0, 0);
$pdf->Cell(65, 5, truncateText($recours['motif'], 35), 0, 1);

$pdf->SetFont('helvetica', 'B', 9);
$pdf->Ln(2);
$pdf->Cell(0, 5, 'Description:', 0, 1);
$pdf->SetFont('helvetica', '', 9);

// Gestion de la description longue avec MultiCell
$pdf->MultiCell(0, 5, $recours['description'], 0, 'L');

// Section pour la décision de l'enseignant
$pdf->Ln(5);
$pdf->SetFont('helvetica', 'B', 11);
$pdf->SetTextColor($primaryColor[0], $primaryColor[1], $primaryColor[2]);
$pdf->Cell(0, 8, 'SECTION RÉSERVÉE À L\'ENSEIGNANT OU AU JURY', 1, 1, 'C', 0);

// Formulaire de décision
$pdf->SetFont('helvetica', '', 9);
$pdf->SetTextColor(80, 80, 80);
$pdf->Cell(40, 7, 'Décision:', 1, 0, 'L');
$pdf->Cell(140, 7, '', 1, 1);

$pdf->Cell(40, 7, 'Nouvelle note CC:', 1, 0, 'L');
$pdf->Cell(40, 7, '', 1, 0, 'L');
$pdf->Cell(40, 7, 'Nouvelle note Examen:', 1, 0, 'L');
$pdf->Cell(60, 7, '', 1, 1, 'L');

$pdf->Cell(40, 7, 'Commentaire:', 1, 0, 'L');
$pdf->Cell(140, 15, '', 1, 1);

// Date et signature
$pdf->Ln(5);
$pdf->Cell(90, 6, 'Date: _____ / _____ / _________', 0, 0);
$pdf->Cell(100, 6, 'Signature:', 0, 1);
$pdf->Ln(5);
$pdf->Cell(90, 0, '______________________________', 0, 0);
$pdf->Cell(100, 0, '______________________________', 0, 1);

// Section pour validation administrative
$pdf->Ln(8);
$pdf->SetFont('helvetica', 'B', 11);
$pdf->SetTextColor($primaryColor[0], $primaryColor[1], $primaryColor[2]);
$pdf->Cell(0, 8, 'VALIDATION ADMINISTRATIVE', 1, 1, 'C', 0);

$pdf->SetFont('helvetica', '', 9);
$pdf->SetTextColor(80, 80, 80);
$pdf->Cell(40, 7, 'Reçu le:', 1, 0, 'L');
$pdf->Cell(60, 7, '', 1, 0, 'L');
$pdf->Cell(40, 7, 'Traité par:', 1, 0, 'L');
$pdf->Cell(40, 7, '', 1, 1, 'L');

$pdf->Cell(40, 7, 'Observations:', 1, 0, 'L');
$pdf->Cell(140, 15, '', 1, 1, 'L');

$pdf->Ln(5);
$pdf->Cell(90, 6, 'Signature responsable', 0, 0, 'C');
$pdf->Cell(100, 6, 'Cachet de l\'établissement', 0, 1, 'C');
$pdf->Ln(5);
$pdf->Cell(90, 0, '______________________________', 0, 0, 'C');
$pdf->Cell(100, 0, '______________________________', 0, 1, 'C');

// Générer le QR Code pour la sécurité
// Le QR code contient les informations essentielles du recours pour vérification
$qrCodeData = "RECOURS ACADÉMIQUE\n";
$qrCodeData .= "ID: " . $recours['id_recours'] . "\n";
$qrCodeData .= "Étudiant: " . $recours['noms'] . "\n";
$qrCodeData .= "Matricule: " . $recours['matricule'] . "\n";
$qrCodeData .= "Cours: " . $recours['designationECUE'] . "\n";
$qrCodeData .= "Session: " . $recours['designSession'] . "\n";
$qrCodeData .= "Document généré le: " . date('d/m/Y H:i:s') . "\n";
$qrCodeData .= $etablissement['site_web'] ?? '';

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
$qrX = 15;
$qrY = $pdf->GetY() + 5;
$qrSize = 25;
$pdf->RoundedRect($qrX - 2, $qrY - 2, $qrSize + 4, $qrSize + 4, 2, '1111', 'DF', array(), array(245, 245, 245));

// Placer le QR code à gauche avec style amélioré
$pdf->write2DBarcode($qrCodeData, 'QRCODE,L', $qrX, $qrY, $qrSize, $qrSize, $qrStyle, 'N');

// Ajouter un petit texte sous le QR code
$pdf->SetFont('helvetica', 'I', 7);
$pdf->SetTextColor(100, 100, 100);
$pdf->SetXY($qrX, $qrY + $qrSize + 2);
$pdf->Cell($qrSize, 4, 'Scan pour vérifier', 0, 0, 'C');

// Ajouter une note d'authenticité à côté du QR code
$pdf->SetY($qrY + 5);
$pdf->SetX(45);
$pdf->SetFont('helvetica', 'I', 8);
$pdf->SetTextColor(100, 100, 100);
$pdf->MultiCell(140, 4, 'Ce document est sécurisé par un code QR. Scannez-le pour vérifier l\'authenticité. Document généré le ' . date('d/m/Y à H:i') . ' - À imprimer et remettre à l\'enseignant ou à la Section/Faculté.', 0, 'C');

// Générer le PDF
$filename = 'Recours_' . sprintf('%05d', $recours['id_recours']) . '_' . $recours['matricule'] . '.pdf';
$pdf->Output($filename, 'I');
exit;

