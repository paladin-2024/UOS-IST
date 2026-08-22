<?php
session_start();
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/models/Soutenance.php';
require_once dirname(__DIR__) . '/vendor/autoload.php'; // Assurez-vous d'avoir installé TCPDF via Composer

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['id'])) {
    header('Location: ../login');
    exit();
}

// Récupérer l'ID de la soutenance
$idSoutenance = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (empty($idSoutenance)) {
    die('ID de soutenance non spécifié');
}

// Récupérer les détails de la soutenance
$soutenanceModel = new Soutenance();
$details = $soutenanceModel->getSoutenanceAvecNotes($idSoutenance);

if (!$details) {
    die('Soutenance non trouvée');
}

// Créer un nouveau document PDF
$pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

// Configurer le document
$pdf->SetCreator('e_gestion');
$pdf->SetAuthor('Université');
$pdf->SetTitle('Procès-verbal de soutenance');
$pdf->SetSubject('PV de soutenance');
$pdf->SetKeywords('Soutenance, PV, Notes');

// Supprimer les en-têtes et pieds de page par défaut
$pdf->setPrintHeader(false);
$pdf->setPrintFooter(false);

// Définir les marges
$pdf->SetMargins(15, 15, 15);

// Ajouter une page
$pdf->AddPage();

// Logo et titre
$pdf->Image(dirname(__DIR__) . '/assets/img/logo.png', 15, 10, 30, 0, 'PNG');
$pdf->SetFont('helvetica', 'B', 16);
$pdf->Cell(0, 20, 'PROCÈS-VERBAL DE SOUTENANCE', 0, 1, 'C');

// Informations sur l'étudiant et le sujet
$pdf->SetFont('helvetica', 'B', 12);
$pdf->Cell(0, 10, 'INFORMATIONS GÉNÉRALES', 0, 1, 'L');
$pdf->SetFont('helvetica', '', 11);

$pdf->SetFont('helvetica', 'B', 10);
$pdf->Cell(50, 8, 'Étudiant :', 0, 0);
$pdf->SetFont('helvetica', '', 10);
$pdf->Cell(0, 8, $details['soutenance']['etudiant_nom'] . ' (' . $details['soutenance']['matricule'] . ')', 0, 1);

$pdf->SetFont('helvetica', 'B', 10);
$pdf->Cell(50, 8, 'Sujet :', 0, 0);
$pdf->SetFont('helvetica', '', 10);
$pdf->MultiCell(0, 8, $details['soutenance']['intitule'], 0, 'L');

$pdf->SetFont('helvetica', 'B', 10);
$pdf->Cell(50, 8, 'Date de soutenance :', 0, 0);
$pdf->SetFont('helvetica', '', 10);
$pdf->Cell(0, 8, date('d/m/Y à H:i', strtotime($details['soutenance']['date_soutenance'])), 0, 1);

$pdf->SetFont('helvetica', 'B', 10);
$pdf->Cell(50, 8, 'Lieu :', 0, 0);
$pdf->SetFont('helvetica', '', 10);
$pdf->Cell(0, 8, $details['soutenance']['lieu'], 0, 1);

// Composition du jury
$pdf->Ln(5);
$pdf->SetFont('helvetica', 'B', 12);
$pdf->Cell(0, 10, 'COMPOSITION DU JURY', 0, 1, 'L');

// Récupérer l'information du jury
$query = "SELECT j.*, 
          p.noms as president_nom, p.grade_id as president_grade_id, pg.designation as president_grade,
          s.noms as secretaire_nom, s.grade_id as secretaire_grade_id, sg.designation as secretaire_grade
          FROM jury j
          JOIN agent p ON j.id_president = p.idAgent
          JOIN agent s ON j.id_secretaire = s.idAgent
          LEFT JOIN grade pg ON p.grade_id = pg.idgrade
          LEFT JOIN grade sg ON s.grade_id = sg.idgrade
          WHERE j.idjury = :idJury";
$stmt = $soutenanceModel->db->prepare($query);
$stmt->execute(['idJury' => $details['soutenance']['jury_id']]);
$jury = $stmt->fetch(PDO::FETCH_ASSOC);

$pdf->SetFont('helvetica', 'B', 10);
$pdf->Cell(50, 8, 'Président :', 0, 0);
$pdf->SetFont('helvetica', '', 10);
$pdf->Cell(0, 8, $jury['president_nom'] . ' (' . ($jury['president_grade'] ?: 'Non défini') . ')', 0, 1);

$pdf->SetFont('helvetica', 'B', 10);
$pdf->Cell(50, 8, 'Secrétaire :', 0, 0);
$pdf->SetFont('helvetica', '', 10);
$pdf->Cell(0, 8, $jury['secretaire_nom'] . ' (' . ($jury['secretaire_grade'] ?: 'Non défini') . ')', 0, 1);

// Lecteurs
$pdf->Ln(2);
$pdf->SetFont('helvetica', 'B', 10);
$pdf->Cell(0, 8, 'Lecteurs :', 0, 1);

foreach ($details['lecteurs'] as $lecteur) {
    $pdf->Cell(10, 8, '', 0, 0);
    $pdf->Cell(0, 8, '- ' . $lecteur['noms'] . ($lecteur['est_premier_lecteur'] ? ' (Premier lecteur)' : ' (Second lecteur)'), 0, 1);
}

$pdf->Ln(2);
$pdf->SetFont('helvetica', 'B', 10);
$pdf->Cell(50, 8, 'Directeur :', 0, 0);
$pdf->SetFont('helvetica', '', 10);
$pdf->Cell(0, 8, $details['soutenance']['directeur_nom'], 0, 1);

// Tableau des notes
$pdf->Ln(5);
$pdf->SetFont('helvetica', 'B', 12);
$pdf->Cell(0, 10, 'RÉCAPITULATIF DES NOTES', 0, 1, 'L');

// En-têtes du tableau
$pdf->SetFillColor(220, 220, 220);
$pdf->SetFont('helvetica', 'B', 10);
$pdf->Cell(60, 8, 'Évaluateur', 1, 0, 'C', true);
$pdf->Cell(40, 8, 'Note Fond (/20)', 1, 0, 'C', true);
$pdf->Cell(40, 8, 'Note Forme (/20)', 1, 0, 'C', true);
$pdf->Cell(40, 8, 'Note Soutenance (/20)', 1, 1, 'C', true);

// Notes des lecteurs
$pdf->SetFont('helvetica', '', 10);
foreach ($details['notes'] as $note) {
    if ($note['type_notation'] === 'Lecteur') {
        $pdf->Cell(60, 8, $note['noms'], 1, 0, 'L');
        $pdf->Cell(40, 8, $note['note_fond'] ?? '-', 1, 0, 'C');
        $pdf->Cell(40, 8, $note['note_forme'] ?? '-', 1, 0, 'C');
        $pdf->Cell(40, 8, '-', 1, 1, 'C');
    }
}

// Note du directeur
foreach ($details['notes'] as $note) {
    if ($note['type_notation'] === 'Directeur') {
        $pdf->Cell(60, 8, $note['noms'] . ' (Directeur)', 1, 0, 'L');
        $pdf->Cell(40, 8, '-', 1, 0, 'C');
        $pdf->Cell(40, 8, '-', 1, 0, 'C');
        $pdf->Cell(40, 8, $note['note_soutenance'] ?? '-', 1, 1, 'C');
    }
}

// Calcul des moyennes
$pdf->SetFont('helvetica', 'B', 10);
$pdf->Cell(60, 8, 'MOYENNES', 1, 0, 'L', true);
$pdf->Cell(40, 8, $details['moyennes']['moyenne_fond'] ?? '-', 1, 0, 'C', true);
$pdf->Cell(40, 8, $details['moyennes']['moyenne_forme'] ?? '-', 1, 0, 'C', true);
$pdf->Cell(40, 8, $details['moyennes']['moyenne_soutenance'] ?? '-', 1, 1, 'C', true);

// Moyenne finale
$pdf->Ln(5);
$pdf->SetFont('helvetica', 'B', 12);
$pdf->Cell(0, 10, 'DÉCISION DU JURY', 0, 1, 'L');

$pdf->SetFont('helvetica', 'B', 11);
$pdf->Cell(0, 10, 'Note finale: ' . ($details['moyennes']['moyenne_finale'] ?? '-') . '/20', 0, 1, 'C');

$mention = '';
$note_finale = $details['moyennes']['moyenne_finale'] ?? 0;
if ($note_finale >= 16) {
    $mention = 'Très Bien';
} elseif ($note_finale >= 14) {
    $mention = 'Bien';
} elseif ($note_finale >= 12) {
    $mention = 'Assez Bien';
} elseif ($note_finale >= 10) {
    $mention = 'Passable';
} else {
    $mention = 'Insuffisant';
}

$pdf->SetFont('helvetica', 'B', 11);
$pdf->Cell(0, 10, 'Mention: ' . $mention, 0, 1, 'C');

// Commentaires éventuels
if (!empty($details['validation']['commentaire'])) {
    $pdf->Ln(5);
    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->Cell(0, 8, 'Commentaires du jury:', 0, 1);
    
    $pdf->SetFont('helvetica', '', 10);
    $pdf->MultiCell(0, 8, $details['validation']['commentaire'], 0, 'L');
}

// Espace pour signatures
$pdf->Ln(10);
$pdf->SetFont('helvetica', 'B', 12);
$pdf->Cell(0, 10, 'SIGNATURES', 0, 1, 'L');

$pdf->Ln(5);
$pdf->SetFont('helvetica', '', 10);
$pdf->Cell(90, 8, 'Le Président du jury', 0, 0, 'C');
$pdf->Cell(90, 8, 'Le Secrétaire du jury', 0, 1, 'C');

$pdf->Ln(20); // Espace pour signatures manuscrites

$pdf->Cell(90, 8, $jury['president_nom'], 0, 0, 'C');
$pdf->Cell(90, 8, $jury['secretaire_nom'], 0, 1, 'C');

// Date et lieu d'édition du PV
$pdf->Ln(10);
$pdf->SetFont('helvetica', 'I', 10);
$pdf->Cell(0, 8, 'Fait à __________________, le ' . date('d/m/Y'), 0, 1, 'R');

// Générer le PDF
$pdf->Output('PV_Soutenance_' . $details['soutenance']['matricule'] . '.pdf', 'I');

