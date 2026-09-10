<?php
session_start();
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/models/Universite.php';
require_once dirname(__DIR__) . '/vendor/autoload.php';

// Vérification de la session
if (!isset($_SESSION['id'])) {
    header('Location: ../accueil');
    exit;
}

$sujetId = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($sujetId <= 0) {
    echo "<script>
        alert('Aucun sujet spécifié.');
        window.history.back();
    </script>";
    exit;
}

try {
    $db = Connexion::getInstance()->getPDO();
    $universiteModel = new Universite();

    $querySujet = "SELECT s.*,
                       a.designation as annee_designation,
                       e.noms as etudiant_nom,
                       e.matricule as etudiant_matricule,
                       sp.designation as specialisation_designation,
                       ur.\"designation_UR\" as unite_recherche,
                       direc.noms as directeur_nom,
                       direc.\"idAgent\" as directeur_id,
                       g_direc.designation as directeur_grade,
                       enc.noms as encadreur_nom,
                       enc.\"idAgent\" as encadreur_id,
                       g_enc.designation as encadreur_grade,
                       p.\"designationPromotion\" as promotion
                FROM sujets s
                LEFT JOIN annee_acad a ON s.annee_acad_idannee_acad = a.idannee_acad
                LEFT JOIN etudiant e ON s.etudiant_idetudiant = e.idetudiant
                LEFT JOIN promotion p ON e.promotion_idpromotion = p.idpromotion
                LEFT JOIN specialisation sp ON s.\"idSpecialisation\" = sp.\"idSpecialisation\"
                LEFT JOIN unite_recherche ur ON sp.\"idUnite_recherche\" = ur.idunite_recherche
                LEFT JOIN agent direc ON s.\"idDirecteur\" = direc.\"idAgent\"
                LEFT JOIN grade g_direc ON direc.grade_id = g_direc.idgrade
                LEFT JOIN agent enc ON s.\"idEncadreur\" = enc.\"idAgent\"
                LEFT JOIN grade g_enc ON enc.grade_id = g_enc.idgrade
                WHERE s.idsujets = :sujetId";

    $stmt = $db->prepare($querySujet);
    $stmt->bindParam(':sujetId', $sujetId, PDO::PARAM_INT);
    $stmt->execute();
    $sujet = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$sujet) {
        throw new Exception('Sujet introuvable');
    }

    $configUniversite = $universiteModel->getConfigurationUniversite();

    $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);

    $pdf->SetCreator('Système de gestion universitaire');
    $pdf->SetAuthor($configUniversite['nom'] ?? 'Administration');
    $pdf->SetTitle('Fiche Sujet - ' . $sujet['intitule']);
    $pdf->SetSubject('Détails du sujet de recherche');
    $pdf->SetKeywords('Sujet, Recherche, Export, PDF');

    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);

    $pdf->SetMargins(15, 20, 15);
    $pdf->SetAutoPageBreak(true, 25);

    $primaryColor = array(0, 87, 146);
    $secondaryColor = array(70, 130, 180);
    $accentColor = array(0, 121, 194);

    $pdf->AddPage();

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

    if ($configUniversite) {
        if (!empty($configUniversite['logo'])) {
            $logoPath = dirname(__DIR__) . '/' . $configUniversite['logo'];
            if (file_exists($logoPath)) {
                $pdf->Image($logoPath, 15, 15, 20, 0, '', '', '', false, 200, '', false, false, 0);
            }
        }

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

        $pdf->SetLineStyle(array('width' => 0.5, 'cap' => 'butt', 'join' => 'miter', 'dash' => 0, 'color' => $accentColor));
        $pdf->Line(15, 48, $pdf->getPageWidth() - 15, 48);
    }

    $pdf->SetFillColor($primaryColor[0], $primaryColor[1], $primaryColor[2]);
    $pdf->SetTextColor(255, 255, 255);
    $pdf->SetFont('helvetica', 'B', 16);
    $pdf->Ln(5);
    $pdf->Cell(0, 10, 'FICHE SUJET DE RECHERCHE', 0, 1, 'C', 1);

    $pdf->SetTextColor(80, 80, 80);
    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->Cell(0, 8, 'Année académique : ' . ($sujet['annee_designation'] ?? 'N/A'), 0, 1, 'C');

    $pdf->Ln(8);

    // Intitulé
    $pdf->SetTextColor($secondaryColor[0], $secondaryColor[1], $secondaryColor[2]);
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->Cell(0, 8, 'Intitulé du sujet', 0, 1, 'L');
    $pdf->SetTextColor(40, 40, 40);
    $pdf->SetFont('helvetica', '', 11);
    $pdf->MultiCell(0, 6, $sujet['intitule'], 0, 'L');
    $pdf->Ln(4);

    // Informations générales : label => value pairs
    $rows = [
        ['Cycle', $sujet['cycle'] ?? 'Non défini'],
        ['Spécialisation', $sujet['specialisation_designation'] ?? 'Non définie'],
        ['Unité de recherche', $sujet['unite_recherche'] ?? 'Non définie'],
        ['Statut', $sujet['statut_validation'] ?? 'En attente'],
        ['État', $sujet['etatSujet'] ?? 'N/A'],
    ];

    $directeurLabel = $sujet['directeur_id']
        ? trim(($sujet['directeur_grade'] ?? '') . ' ' . ($sujet['directeur_nom'] ?? ''))
        : 'Non assigné';
    $encadreurLabel = $sujet['encadreur_id']
        ? trim(($sujet['encadreur_grade'] ?? '') . ' ' . ($sujet['encadreur_nom'] ?? ''))
        : 'Non assigné';
    $rows[] = ['Directeur', $directeurLabel];
    $rows[] = ['Co-encadreur', $encadreurLabel];

    $etudiantLabel = !empty($sujet['etudiant_nom'])
        ? $sujet['etudiant_nom'] . (!empty($sujet['etudiant_matricule']) ? ' (' . $sujet['etudiant_matricule'] . ')' : '')
        : 'Non assigné';
    $rows[] = ['Étudiant', $etudiantLabel];
    if (!empty($sujet['promotion'])) {
        $rows[] = ['Promotion', $sujet['promotion']];
    }

    if (!empty($sujet['date_validation'])) {
        $rows[] = ['Date de validation', date('d/m/Y à H:i', strtotime($sujet['date_validation']))];
    }

    $pdf->SetFont('helvetica', 'B', 10);
    $labelWidth = 55;
    $valueWidth = $pdf->getPageWidth() - 30 - $labelWidth;

    foreach ($rows as $index => $row) {
        list($label, $value) = $row;
        $fill = ($index % 2 == 0) ? 0 : 1;
        if ($fill) {
            $pdf->SetFillColor(245, 245, 245);
        }
        $pdf->SetFont('helvetica', 'B', 9);
        $pdf->SetTextColor(60, 60, 60);
        $pdf->Cell($labelWidth, 7, $label, 1, 0, 'L', $fill);
        $pdf->SetFont('helvetica', '', 9);
        $pdf->SetTextColor(40, 40, 40);
        $pdf->Cell($valueWidth, 7, (string) $value, 1, 1, 'L', $fill);
    }

    if (!empty($sujet['commentaire_commission'])) {
        $pdf->Ln(4);
        $pdf->SetTextColor($secondaryColor[0], $secondaryColor[1], $secondaryColor[2]);
        $pdf->SetFont('helvetica', 'B', 11);
        $pdf->Cell(0, 7, 'Commentaire de la commission', 0, 1, 'L');
        $pdf->SetTextColor(40, 40, 40);
        $pdf->SetFont('helvetica', '', 9);
        $pdf->MultiCell(0, 6, $sujet['commentaire_commission'], 0, 'L');
    }

    $pdf->Ln(10);
    $pdf->SetFont('helvetica', '', 8);
    $pdf->SetTextColor(120, 120, 120);
    $pdf->Cell(0, 4, 'Document généré automatiquement le ' . date('d/m/Y à H:i'), 0, 1, 'L');
    $pdf->Cell(0, 4, 'Référence: SR-' . $sujet['idsujets'] . '-' . date('YmdHis'), 0, 1, 'L');

    ob_clean();

    $fileName = 'Sujet_' . $sujet['idsujets'] . '_' . date('Y-m-d') . '.pdf';

    $pdf->Output($fileName, 'I');

} catch (Exception $e) {
    error_log("Erreur génération PDF sujet: " . $e->getMessage());

    echo "<script>
        alert('Erreur lors de la génération du PDF: " . addslashes($e->getMessage()) . "');
        window.history.back();
    </script>";
}
