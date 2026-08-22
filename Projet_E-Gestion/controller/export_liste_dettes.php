<?php
session_start();
require_once dirname(__DIR__).'/config/Connexion.php';
require_once dirname(__DIR__).'/vendor/autoload.php';
require_once dirname(__DIR__).'/models/Universite.php';

if (!isset($_SESSION['id'])) {
    header('Location: ../connexion');
    exit();
}

$promotionId = isset($_GET['promotion']) ? intval($_GET['promotion']) : 0;
$anneeId = isset($_GET['annee']) ? intval($_GET['annee']) : 0;
$sessionId = isset($_GET['session']) ? intval($_GET['session']) : 0;
$showDetails = isset($_GET['details']) && $_GET['details'] == '1';

if (!$promotionId || !$anneeId) {
    die('Paramètres incomplets');
}

$universite = new Universite();
$db = Connexion::getInstance()->getPDO();

$configUniversite = $universite->getConfigurationUniversite();
$promotion = $universite->getPromotionById($promotionId);
$annee = $universite->getAnneeAcademiqueById($anneeId);
$session = $sessionId ? $universite->getSessionById($sessionId) : null;

// Statistiques globales - Vérifier la dernière session pour voir si la dette est réelle
$sql = "SELECT 
            COUNT(DISTINCT d.matricule) as nb_etudiants,
            COUNT(DISTINCT CASE WHEN (
                SELECT cg.MF FROM cotes_grille cg 
                WHERE cg.matricule = d.matricule 
                AND cg.ECUE_idECUE = d.ECUE_idECUE 
                AND cg.annee_acad_id = d.annee_acad_idannee_acad
                ORDER BY cg.session_idsession DESC 
                LIMIT 1
            ) < 10 OR (
                SELECT cg.MF FROM cotes_grille cg 
                WHERE cg.matricule = d.matricule 
                AND cg.ECUE_idECUE = d.ECUE_idECUE 
                AND cg.annee_acad_id = d.annee_acad_idannee_acad
                ORDER BY cg.session_idsession DESC 
                LIMIT 1
            ) IS NULL THEN d.id_dette END) as nb_dettes,
            SUM(CASE WHEN (
                SELECT cg.MF FROM cotes_grille cg 
                WHERE cg.matricule = d.matricule 
                AND cg.ECUE_idECUE = d.ECUE_idECUE 
                AND cg.annee_acad_id = d.annee_acad_idannee_acad
                ORDER BY cg.session_idsession DESC 
                LIMIT 1
            ) >= 10 THEN 1 ELSE 0 END) as validees,
            SUM(CASE WHEN (
                SELECT cg.MF FROM cotes_grille cg 
                WHERE cg.matricule = d.matricule 
                AND cg.ECUE_idECUE = d.ECUE_idECUE 
                AND cg.annee_acad_id = d.annee_acad_idannee_acad
                ORDER BY cg.session_idsession DESC 
                LIMIT 1
            ) < 10 OR (
                SELECT cg.MF FROM cotes_grille cg 
                WHERE cg.matricule = d.matricule 
                AND cg.ECUE_idECUE = d.ECUE_idECUE 
                AND cg.annee_acad_id = d.annee_acad_idannee_acad
                ORDER BY cg.session_idsession DESC 
                LIMIT 1
            ) IS NULL THEN 1 ELSE 0 END) as en_cours,
            SUM(CASE WHEN (
                SELECT cg.MF FROM cotes_grille cg 
                WHERE cg.matricule = d.matricule 
                AND cg.ECUE_idECUE = d.ECUE_idECUE 
                AND cg.annee_acad_id = d.annee_acad_idannee_acad
                ORDER BY cg.session_idsession DESC 
                LIMIT 1
            ) < 10 OR (
                SELECT cg.MF FROM cotes_grille cg 
                WHERE cg.matricule = d.matricule 
                AND cg.ECUE_idECUE = d.ECUE_idECUE 
                AND cg.annee_acad_id = d.annee_acad_idannee_acad
                ORDER BY cg.session_idsession DESC 
                LIMIT 1
            ) IS NULL THEN d.credits_ecue ELSE 0 END) as total_credits
        FROM dette_etudiant d
        WHERE d.promotion_idpromotion = :promotion AND d.annee_acad_idannee_acad = :annee";
$stmt = $db->prepare($sql);
$stmt->execute(['promotion' => $promotionId, 'annee' => $anneeId]);
$stats = $stmt->fetch(PDO::FETCH_ASSOC);

// Liste des étudiants avec dettes - Vérifier la dernière session
$sql = "SELECT 
            d.matricule,
            (SELECT e.noms FROM etudiant e WHERE e.matricule = d.matricule LIMIT 1) as noms,
            SUM(CASE WHEN (
                SELECT cg.MF FROM cotes_grille cg 
                WHERE cg.matricule = d.matricule 
                AND cg.ECUE_idECUE = d.ECUE_idECUE 
                AND cg.annee_acad_id = d.annee_acad_idannee_acad
                ORDER BY cg.session_idsession DESC 
                LIMIT 1
            ) < 10 OR (
                SELECT cg.MF FROM cotes_grille cg 
                WHERE cg.matricule = d.matricule 
                AND cg.ECUE_idECUE = d.ECUE_idECUE 
                AND cg.annee_acad_id = d.annee_acad_idannee_acad
                ORDER BY cg.session_idsession DESC 
                LIMIT 1
            ) IS NULL THEN 1 ELSE 0 END) as nb_dettes,
            SUM(CASE WHEN (
                SELECT cg.MF FROM cotes_grille cg 
                WHERE cg.matricule = d.matricule 
                AND cg.ECUE_idECUE = d.ECUE_idECUE 
                AND cg.annee_acad_id = d.annee_acad_idannee_acad
                ORDER BY cg.session_idsession DESC 
                LIMIT 1
            ) < 10 OR (
                SELECT cg.MF FROM cotes_grille cg 
                WHERE cg.matricule = d.matricule 
                AND cg.ECUE_idECUE = d.ECUE_idECUE 
                AND cg.annee_acad_id = d.annee_acad_idannee_acad
                ORDER BY cg.session_idsession DESC 
                LIMIT 1
            ) IS NULL THEN d.credits_ecue ELSE 0 END) as credits,
            SUM(CASE WHEN (
                SELECT cg.MF FROM cotes_grille cg 
                WHERE cg.matricule = d.matricule 
                AND cg.ECUE_idECUE = d.ECUE_idECUE 
                AND cg.annee_acad_id = d.annee_acad_idannee_acad
                ORDER BY cg.session_idsession DESC 
                LIMIT 1
            ) >= 10 THEN 1 ELSE 0 END) as validees,
            SUM(CASE WHEN (
                SELECT cg.MF FROM cotes_grille cg 
                WHERE cg.matricule = d.matricule 
                AND cg.ECUE_idECUE = d.ECUE_idECUE 
                AND cg.annee_acad_id = d.annee_acad_idannee_acad
                ORDER BY cg.session_idsession DESC 
                LIMIT 1
            ) < 10 OR (
                SELECT cg.MF FROM cotes_grille cg 
                WHERE cg.matricule = d.matricule 
                AND cg.ECUE_idECUE = d.ECUE_idECUE 
                AND cg.annee_acad_id = d.annee_acad_idannee_acad
                ORDER BY cg.session_idsession DESC 
                LIMIT 1
            ) IS NULL THEN 1 ELSE 0 END) as en_cours
        FROM dette_etudiant d
        WHERE d.promotion_idpromotion = :promotion AND d.annee_acad_idannee_acad = :annee";

$params = ['promotion' => $promotionId, 'annee' => $anneeId];
if ($sessionId) {
    $sql .= " AND d.session_idsession = :session";
    $params['session'] = $sessionId;
}
$sql .= " GROUP BY d.matricule ORDER BY noms";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$etudiants = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Si mode détaillé, récupérer les dettes par étudiant - Filtrer sur la dernière session
$dettesParEtudiant = [];
if ($showDetails) {
    $sqlDetails = "SELECT 
                d.matricule, 
                -- Utiliser la note de la dernière session
                (SELECT cg.MF FROM cotes_grille cg 
                 WHERE cg.matricule = d.matricule 
                 AND cg.ECUE_idECUE = d.ECUE_idECUE 
                 AND cg.annee_acad_id = d.annee_acad_idannee_acad
                 ORDER BY cg.session_idsession DESC 
                 LIMIT 1) as note_obtenue,
                d.note_rachat, d.credits_ecue, d.statut,
                ec.designationECUE, ue.codeUE, ue.designationUE, s.numeroSemestre
            FROM dette_etudiant d
            LEFT JOIN ecue ec ON d.ECUE_idECUE = ec.idECUE
            LEFT JOIN ue ON d.UE_idUE = ue.idUE
            LEFT JOIN semestre s ON d.semestre_idsemestre = s.idsemestre
            WHERE d.promotion_idpromotion = :promotion AND d.annee_acad_idannee_acad = :annee
            AND (
                -- Afficher seulement si la note de la dernière session est < 10 ou NULL (pas de note)
                (SELECT cg.MF FROM cotes_grille cg 
                 WHERE cg.matricule = d.matricule 
                 AND cg.ECUE_idECUE = d.ECUE_idECUE 
                 AND cg.annee_acad_id = d.annee_acad_idannee_acad
                 ORDER BY cg.session_idsession DESC 
                 LIMIT 1) < 10
                OR
                (SELECT cg.MF FROM cotes_grille cg 
                 WHERE cg.matricule = d.matricule 
                 AND cg.ECUE_idECUE = d.ECUE_idECUE 
                 AND cg.annee_acad_id = d.annee_acad_idannee_acad
                 ORDER BY cg.session_idsession DESC 
                 LIMIT 1) IS NULL
            )";
    
    $paramsDetails = ['promotion' => $promotionId, 'annee' => $anneeId];
    if ($sessionId) {
        $sqlDetails .= " AND d.session_idsession = :session";
        $paramsDetails['session'] = $sessionId;
    }
    $sqlDetails .= " ORDER BY d.matricule, s.numeroSemestre, ue.codeUE";
    
    $stmtDetails = $db->prepare($sqlDetails);
    $stmtDetails->execute($paramsDetails);
    $allDettes = $stmtDetails->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($allDettes as $dette) {
        $dettesParEtudiant[$dette['matricule']][] = $dette;
    }
}

// Créer le PDF avec TCPDF
$pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
$pdf->SetCreator('E-Gestion');
$pdf->SetAuthor($configUniversite['nom'] ?? 'Administration');
$pdf->SetTitle('Liste des Dettes - ' . $promotion['designationPromotion']);
$pdf->setPrintHeader(false);
$pdf->setPrintFooter(false);
$pdf->SetMargins(10, 10, 10);
$pdf->SetAutoPageBreak(true, 15);

$primaryColor = [44, 62, 80];
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

$pdf->Ln(3);
$pdf->SetLineStyle(['width' => 0.3, 'color' => $primaryColor]);
$pdf->Line(10, $pdf->GetY(), $pdf->getPageWidth() - 10, $pdf->GetY());

// Titre
$pdf->Ln(5);
$pdf->SetFillColor($primaryColor[0], $primaryColor[1], $primaryColor[2]);
$pdf->SetTextColor(255, 255, 255);
$pdf->SetFont('helvetica', 'B', 11);
$titre = $showDetails ? 'LISTE DÉTAILLÉE DES DETTES ACADÉMIQUES' : 'LISTE DES DETTES ACADÉMIQUES';
$pdf->Cell(0, 7, $titre, 0, 1, 'C', 1);

$pdf->SetTextColor($primaryColor[0], $primaryColor[1], $primaryColor[2]);
$pdf->SetFont('helvetica', 'B', 10);
$pdf->Cell(0, 5, $promotion['designationPromotion'], 0, 1, 'C');

$pdf->SetFont('helvetica', 'I', 8);
$pdf->SetTextColor(80, 80, 80);
$pdf->Cell(0, 4, $annee['designation'] . ($session ? ' - ' . $session['description'] : ''), 0, 1, 'C');

// Statistiques
$pdf->Ln(5);
$pdf->SetFont('helvetica', 'B', 8);
$pdf->SetTextColor(60, 60, 60);
$pdf->SetFillColor(245, 245, 245);

$colW = 47.5;
$pdf->Cell($colW, 5, 'Étudiants concernés: ' . ($stats['nb_etudiants'] ?? 0), 1, 0, 'C', 1);
$pdf->Cell($colW, 5, 'Total dettes: ' . ($stats['nb_dettes'] ?? 0), 1, 0, 'C', 1);
$pdf->Cell($colW, 5, 'Validées: ' . ($stats['validees'] ?? 0), 1, 0, 'C', 1);
$pdf->Cell($colW, 5, 'En cours: ' . ($stats['en_cours'] ?? 0), 1, 1, 'C', 1);

// Tableau principal
$pdf->Ln(5);
$pdf->SetFillColor(240, 240, 240);
$pdf->SetFont('helvetica', 'B', 9);
$pdf->SetTextColor(60, 60, 60);

$col1 = 12; $col2 = 30; $col3 = 75; $col4 = 20; $col5 = 20; $col6 = 17; $col7 = 16;

$pdf->Cell($col1, 6, 'N°', 1, 0, 'C', 1);
$pdf->Cell($col2, 6, 'Matricule', 1, 0, 'C', 1);
$pdf->Cell($col3, 6, 'Nom et Prénom', 1, 0, 'C', 1);
$pdf->Cell($col4, 6, 'Dettes', 1, 0, 'C', 1);
$pdf->Cell($col5, 6, 'Crédits', 1, 0, 'C', 1);
$pdf->Cell($col6, 6, 'Valid.', 1, 0, 'C', 1);
$pdf->Cell($col7, 6, 'Enc.', 1, 1, 'C', 1);

$pdf->SetFont('helvetica', '', 8);
$pdf->SetFillColor(255, 255, 255);
$i = 1;
$fill = false;

foreach ($etudiants as $e) {
    if ($fill) $pdf->SetFillColor(250, 250, 250);
    else $pdf->SetFillColor(255, 255, 255);
    
    $pdf->SetTextColor(60, 60, 60);
    $pdf->Cell($col1, 5, $i++, 1, 0, 'C', $fill);
    $pdf->Cell($col2, 5, $e['matricule'], 1, 0, 'L', $fill);
    $pdf->Cell($col3, 5, $e['noms'], 1, 0, 'L', $fill);
    $pdf->Cell($col4, 5, $e['nb_dettes'], 1, 0, 'C', $fill);
    $pdf->Cell($col5, 5, $e['credits'], 1, 0, 'C', $fill);
    
    $pdf->SetTextColor(50, 150, 50);
    $pdf->Cell($col6, 5, $e['validees'], 1, 0, 'C', $fill);
    
    $pdf->SetTextColor(200, 150, 0);
    $pdf->Cell($col7, 5, $e['en_cours'], 1, 1, 'C', $fill);
    
    // Afficher les détails des dettes si mode détaillé activé
    if ($showDetails && isset($dettesParEtudiant[$e['matricule']])) {
        $dettes = $dettesParEtudiant[$e['matricule']];
        
        // En-tête des détails
        $pdf->SetFillColor(245, 248, 250);
        $pdf->SetFont('helvetica', 'B', 7);
        $pdf->SetTextColor(80, 80, 80);
        
        $dCol1 = 12; $dCol2 = 55; $dCol3 = 58; $dCol4 = 12; $dCol5 = 16; $dCol6 = 16; $dCol7 = 21;
        
        $pdf->Cell($dCol1, 4, 'Sem', 1, 0, 'C', 1);
        $pdf->Cell($dCol2, 4, 'UE', 1, 0, 'L', 1);
        $pdf->Cell($dCol3, 4, 'ECUE', 1, 0, 'L', 1);
        $pdf->Cell($dCol4, 4, 'Cr.', 1, 0, 'C', 1);
        $pdf->Cell($dCol5, 4, 'Note', 1, 0, 'C', 1);
        $pdf->Cell($dCol6, 4, 'Rachat', 1, 0, 'C', 1);
        $pdf->Cell($dCol7, 4, 'Statut', 1, 1, 'C', 1);
        
        // Lignes de détail
        $pdf->SetFont('helvetica', '', 7);
        $pdf->SetFillColor(255, 255, 255);
        
        foreach ($dettes as $dette) {
            $pdf->SetTextColor(80, 80, 80);
            $pdf->Cell($dCol1, 4, 'S' . ($dette['numeroSemestre'] ?? '?'), 1, 0, 'C', 0);
            
            $ueLabel = ($dette['codeUE'] && $dette['designationUE']) ? $dette['codeUE'] . ' - ' . $dette['designationUE'] : '-';
            if (mb_strlen($ueLabel, 'UTF-8') > 35) $ueLabel = mb_substr($ueLabel, 0, 32, 'UTF-8') . '...';
            $pdf->Cell($dCol2, 4, $ueLabel, 1, 0, 'L', 0);
            
            $ecueLabel = $dette['designationECUE'] ?? '-';
            if (mb_strlen($ecueLabel, 'UTF-8') > 36) $ecueLabel = mb_substr($ecueLabel, 0, 33, 'UTF-8') . '...';
            $pdf->Cell($dCol3, 4, $ecueLabel, 1, 0, 'L', 0);
            
            $pdf->Cell($dCol4, 4, $dette['credits_ecue'] ?? 0, 1, 0, 'C', 0);
            
            // Note en rouge si < 10
            $note = $dette['note_obtenue'] ?? 0;
            if ($note < 10) $pdf->SetTextColor(200, 50, 50);
            else $pdf->SetTextColor(80, 80, 80);
            $pdf->Cell($dCol5, 4, number_format($note, 2), 1, 0, 'C', 0);
            
            // Note de rachat
            $rachat = $dette['note_rachat'];
            if ($rachat !== null) {
                if ($rachat >= 10) $pdf->SetTextColor(50, 150, 50);
                else $pdf->SetTextColor(200, 150, 0);
                $pdf->Cell($dCol6, 4, number_format($rachat, 2), 1, 0, 'C', 0);
            } else {
                $pdf->SetTextColor(80, 80, 80);
                $pdf->Cell($dCol6, 4, '-', 1, 0, 'C', 0);
            }
            
            // Statut
            if ($dette['statut'] == 'Validée') {
                $pdf->SetTextColor(50, 150, 50);
            } elseif ($dette['statut'] == 'En cours') {
                $pdf->SetTextColor(200, 150, 0);
            } else {
                $pdf->SetTextColor(150, 150, 150);
            }
            $pdf->Cell($dCol7, 4, $dette['statut'], 1, 1, 'C', 0);
        }
        
        $pdf->Ln(3); // Espace après les détails
        $pdf->SetFont('helvetica', '', 8);
    }
    
    $fill = !$fill;
}

// Signature - Secrétaire Général Académique (sur la dernière page)
$titreSecretaire = $configUniversite['titre_secretaire_general'] ?? 'Le Secrétaire Général Académique';
$nomSecretaire = $configUniversite['nom_secretaire_general'] ?? '';

// Vérifier s'il reste assez d'espace pour la signature (environ 30mm)
$espaceRestant = $pdf->getPageHeight() - $pdf->GetY() - 15;
if ($espaceRestant < 30) {
    $pdf->AddPage();
}

$pdf->Ln(5);
$pdf->SetTextColor(80, 80, 80);
$pdf->SetFont('helvetica', '', 8);
$pdf->Cell(0, 4, 'Fait à ' . ($configUniversite['ville'] ?? '...') . ', le ' . date('d/m/Y'), 0, 1, 'R');

$pdf->Ln(2);
$pdf->SetFont('helvetica', 'B', 8);
$pdf->Cell(0, 4, $titreSecretaire, 0, 1, 'R');
if (!empty($nomSecretaire)) {
    $pdf->Ln(6);
    $pdf->SetFont('helvetica', 'I', 8);
    $pdf->Cell(0, 4, $nomSecretaire, 0, 1, 'R');
} else {
    $pdf->Ln(8);
    $pdf->Cell(0, 4, '_______________________', 0, 1, 'R');
}

// Footer en bas de la dernière page
$pdf->SetAutoPageBreak(false); // Désactiver temporairement pour éviter saut de page
$pdf->SetY($pdf->getPageHeight() - 12);
$pdf->SetFont('helvetica', '', 6);
$pdf->SetTextColor(120, 120, 120);
$pdf->Cell(0, 3, 'Imprimé par ' . $_SESSION['nom'] . ' le ' . date('d/m/Y à H:i'), 0, 0, 'C');

$pdf->Output('liste_dettes_' . $promotionId . '_' . $anneeId . '.pdf', 'I');
