<?php
session_start();
header('Content-Type: application/json');

require_once dirname(__DIR__) . '/vendor/autoload.php';
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/models/Etudiant.php';
require_once dirname(__DIR__) . '/models/Universite.php';

try {
    $soutenanceId = isset($_GET['id']) ? intval($_GET['id']) : 0;
    
    if (!$soutenanceId) {
        throw new Exception('ID de soutenance manquant');
    }
    
    // Récupérer les données de soutenance
    $connexion = Connexion::getInstance()->getPDO();
    
    $query = "SELECT 
                s.idsoutenance,
                s.date_soutenance,
                s.lieu,
                s.sujets_idsujets,
                s.jury_id,
                sj.intitule as titre_memoire,
                e.noms,
                e.matricule,
                e.photo,
                p.\"designationPromotion\",
                p.idpromotion,
                p.orientation_idorientation,
                sp.designation as specialisation,
                ag.noms as directeur_noms,
                gag.designation as directeur_grade,
                j.idjury,
                apres.noms as president_nom,
                gpres.designation as president_grade,
                asec.noms as secretaire_nom,
                gsec.designation as secretaire_grade
              FROM soutenance s
              JOIN sujets sj ON s.sujets_idsujets = sj.idsujets
              JOIN etudiant e ON sj.etudiant_idetudiant = e.idetudiant
              JOIN promotion p ON e.promotion_idpromotion = p.idpromotion
              JOIN specialisation sp ON sj.\"idSpecialisation\" = sp.idspecialisation
              LEFT JOIN agent ag ON sj.\"idDirecteur\" = ag.idagent
              LEFT JOIN grade gag ON ag.grade_id = gag.idgrade
              LEFT JOIN jury j ON s.jury_id = j.idjury
              LEFT JOIN agent apres ON j.id_president = apres.idagent
              LEFT JOIN grade gpres ON apres.grade_id = gpres.idgrade
              LEFT JOIN agent asec ON j.id_secretaire = asec.idagent
              LEFT JOIN grade gsec ON asec.grade_id = gsec.idgrade
              WHERE s.idsoutenance = :id";
    
    $stmt = $connexion->prepare($query);
    $stmt->execute(['id' => $soutenanceId]);
    $soutenance = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$soutenance) {
        throw new Exception('Soutenance non trouvée');
    }
    
    // Récupérer les lecteurs
    $queryLecteurs = "SELECT 
                        ag.noms as lecteur_noms,
                        g.designation as grade,
                        l.est_premier_lecteur
                      FROM lecteurs_soutenance l
                      JOIN agent ag ON l.idenseignant = ag.idagent
                      LEFT JOIN grade g ON ag.grade_id = g.idgrade
                      WHERE l.idsoutenance = :id
                      ORDER BY l.est_premier_lecteur DESC, l.id ASC";
    
    $stmtLecteurs = $connexion->prepare($queryLecteurs);
    $stmtLecteurs->execute(['id' => $soutenanceId]);
    $lecteurs = $stmtLecteurs->fetchAll(PDO::FETCH_ASSOC);
    
    // Récupérer les informations de l'université
    $universite = new Universite();
    $configUniversite = $universite->getConfigurationUniversite();
    
    // Récupérer les informations de la section
    $sectionInfo = null;
    if ($soutenance && isset($soutenance['orientation_idorientation'])) {
        try {
            $query = "SELECT s.* FROM section s 
                      INNER JOIN orientation o ON s.idsection = o.section_idsection
                      WHERE o.idorientation = :idorientation";
            $stmt = $connexion->prepare($query);
            $stmt->execute(['idorientation' => $soutenance['orientation_idorientation']]);
            $sectionInfo = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            // Erreur silencieuse pour section
        }
    }
    
    // Format carré optimisé pour les réseaux sociaux (Instagram, Facebook)
    $pdf = new TCPDF('P', 'mm', [210, 297]); 
    $pdf->SetMargins(0, 0, 0);
    $pdf->SetAutoPageBreak(false);
    $pdf->AddPage();
    
    // Palette de couleurs moderne
    $couleurPrimaire = [25, 42, 86];        // Bleu nuit profond
    $couleurSecondaire = [64, 115, 158];    // Bleu acier
    $couleurAccent = [212, 175, 55];        // Or/Doré
    $couleurTexte = [33, 33, 33];           // Noir doux
    $couleurBlanc = [255, 255, 255];
    $couleurGrisClair = [245, 245, 250];    // Fond subtil
    
    $pageWidth = $pdf->getPageWidth();
    $pageHeight = $pdf->getPageHeight();
    
    // ==============================
    // FOND DÉGRADÉ (bande supérieure)
    // ==============================
    $headerHeight = 85;
    for ($i = 0; $i < $headerHeight; $i++) {
        $ratio = $i / $headerHeight;
        $r = $couleurPrimaire[0] + ($couleurSecondaire[0] - $couleurPrimaire[0]) * $ratio * 0.5;
        $g = $couleurPrimaire[1] + ($couleurSecondaire[1] - $couleurPrimaire[1]) * $ratio * 0.5;
        $b = $couleurPrimaire[2] + ($couleurSecondaire[2] - $couleurPrimaire[2]) * $ratio * 0.5;
        $pdf->SetFillColor($r, $g, $b);
        $pdf->Rect(0, $i, $pageWidth, 1, 'F');
    }
    
    // Fond principal clair
    $pdf->SetFillColor($couleurGrisClair[0], $couleurGrisClair[1], $couleurGrisClair[2]);
    $pdf->Rect(0, $headerHeight, $pageWidth, $pageHeight - $headerHeight, 'F');
    
    // ==============================
    // ÉLÉMENTS DÉCORATIFS
    // ==============================
    // Lignes dorées décoratives
    $pdf->SetDrawColor($couleurAccent[0], $couleurAccent[1], $couleurAccent[2]);
    $pdf->SetLineWidth(0.8);
    $pdf->Line(20, $headerHeight - 5, 60, $headerHeight - 5);
    $pdf->Line($pageWidth - 60, $headerHeight - 5, $pageWidth - 20, $headerHeight - 5);
    
    // ==============================
    // EN-TÊTE UNIVERSITÉ
    // ==============================
    $currentY = 12;
    
    // Logo centré avec fond circulaire blanc
    if ($configUniversite && !empty($configUniversite['logo'])) {
        $logoPath = dirname(__DIR__) . '/' . $configUniversite['logo'];
        if (file_exists($logoPath)) {
            $logoSize = 22;
            $logoX = ($pageWidth - $logoSize) / 2;
            // Cercle blanc derrière le logo
            $pdf->SetFillColor(255, 255, 255);
            $pdf->Circle($logoX + $logoSize/2, $currentY + $logoSize/2, $logoSize/2 + 2, 0, 360, 'F');
            $pdf->Image($logoPath, $logoX, $currentY, $logoSize, $logoSize, '', '', '', true, 300, '', false, false, 0, 'CM');
        }
    }
    
    $currentY += 28;
    
    // Texte université en blanc
    $pdf->SetTextColor($couleurBlanc[0], $couleurBlanc[1], $couleurBlanc[2]);
    $pdf->SetFont('helvetica', '', 8);
    $pdf->SetY($currentY);
    $pdf->Cell(0, 4, strtoupper($configUniversite['ministere_tutelle'] ?? 'ENSEIGNEMENT SUPÉRIEUR ET UNIVERSITAIRE'), 0, 1, 'C');
    
    $currentY += 5;
    $pdf->SetFont('helvetica', 'B', 14);
    $pdf->SetY($currentY);
    $pdf->Cell(0, 6, strtoupper($configUniversite['nom'] ?? 'UNIVERSITÉ'), 0, 1, 'C');
    
    if ($sectionInfo && !empty($sectionInfo['designationSection'])) {
        $currentY += 7;
        $pdf->SetFont('helvetica', '', 12);
        $pdf->SetY($currentY);
        $pdf->Cell(0, 4, 'Section : ' . ucwords(strtolower($sectionInfo['designationSection'])), 0, 1, 'C');
    }
    
    // Badge "SOUTENANCE DE MÉMOIRE"
    $currentY = $headerHeight - 18;
    $badgeWidth = 120;
    $badgeX = ($pageWidth - $badgeWidth) / 2;
    $pdf->SetFillColor($couleurAccent[0], $couleurAccent[1], $couleurAccent[2]);
    $pdf->RoundedRect($badgeX, $currentY, $badgeWidth, 12, 2, '1111', 'F');
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->SetTextColor($couleurPrimaire[0], $couleurPrimaire[1], $couleurPrimaire[2]);
    $pdf->SetXY($badgeX, $currentY + 2);
    $pdf->Cell($badgeWidth, 8, 'SOUTENANCE DE MÉMOIRE', 0, 0, 'C');
    
    // ==============================
    // CARTE PHOTO ÉTUDIANT (centrée, avec ombre)
    // ==============================
    $cardY = $headerHeight + 6;
    $photoCardWidth = 55;
    $photoCardHeight = 70;
    $photoCardX = ($pageWidth - $photoCardWidth) / 2;
    
    // Ombre de la carte
    $pdf->SetFillColor(200, 200, 200);
    $pdf->RoundedRect($photoCardX + 2, $cardY + 2, $photoCardWidth, $photoCardHeight, 3, '1111', 'F');
    
    // Carte blanche
    $pdf->SetFillColor(255, 255, 255);
    $pdf->RoundedRect($photoCardX, $cardY, $photoCardWidth, $photoCardHeight, 3, '1111', 'F');
    
    // Photo de l'étudiant
    $photoMargin = 4;
    $photoWidth = $photoCardWidth - 2 * $photoMargin;
    $photoHeight = 45;
    $photoX = $photoCardX + $photoMargin;
    $photoY = $cardY + $photoMargin;
    
    $photoPath = dirname(__DIR__) . '/uploads/' . $soutenance['photo'];
    if (!empty($soutenance['photo']) && file_exists($photoPath)) {
        try {
            $pdf->Image($photoPath, $photoX, $photoY, $photoWidth, $photoHeight, '', '', '', true, 300, '', false, false, 0, 'CM');
        } catch (Exception $e) {
            $pdf->SetFillColor(230, 230, 230);
            $pdf->Rect($photoX, $photoY, $photoWidth, $photoHeight, 'F');
            $pdf->SetFont('helvetica', '', 10);
            $pdf->SetTextColor(150, 150, 150);
            $pdf->SetXY($photoX, $photoY + $photoHeight / 2 - 3);
            $pdf->Cell($photoWidth, 6, 'Photo', 0, 1, 'C');
        }
    } else {
        $pdf->SetFillColor(230, 230, 230);
        $pdf->Rect($photoX, $photoY, $photoWidth, $photoHeight, 'F');
        $pdf->SetFont('helvetica', '', 10);
        $pdf->SetTextColor(150, 150, 150);
        $pdf->SetXY($photoX, $photoY + $photoHeight / 2 - 3);
        $pdf->Cell($photoWidth, 6, 'Photo', 0, 1, 'C');
    }
    
    // Nom de l'étudiant sous la photo
    $pdf->SetFont('helvetica', 'B', 8);
    $pdf->SetTextColor($couleurPrimaire[0], $couleurPrimaire[1], $couleurPrimaire[2]);
    $pdf->SetXY($photoCardX, $photoY + $photoHeight + 2);
    $pdf->MultiCell($photoCardWidth, 4, strtoupper($soutenance['noms'] ?? 'Non défini'), 0, 'C');
    
    // Matricule
    $pdf->SetFont('helvetica', '', 7);
    $pdf->SetTextColor(100, 100, 100);
    $pdf->SetXY($photoCardX, $photoY + $photoHeight + 12);
    $pdf->Cell($photoCardWidth, 4, 'Mat: ' . $soutenance['matricule'], 0, 1, 'C');
    
    // ==============================
    // TITRE DU MÉMOIRE (encadré élégant)
    // ==============================
    $titreY = $cardY + $photoCardHeight + 10;
    $titreMargin = 15;
    $titreWidth = $pageWidth - 2 * $titreMargin;
    
    // Guillemets décoratifs
    $pdf->SetFont('helvetica', 'B', 20);
    $pdf->SetTextColor($couleurAccent[0], $couleurAccent[1], $couleurAccent[2]);
    $pdf->SetXY($titreMargin, $titreY - 4);
    $pdf->Cell(10, 10, '"', 0, 0, 'L');
    
    // Titre du mémoire
    $titreMajMinuscule = ucfirst(strtolower($soutenance['titre_memoire']));
    $pdf->SetFont('helvetica', 'BI', 10);
    $pdf->SetTextColor($couleurTexte[0], $couleurTexte[1], $couleurTexte[2]);
    $pdf->SetXY($titreMargin + 8, $titreY);
    $pdf->MultiCell($titreWidth - 16, 5, $titreMajMinuscule, 0, 'C');
    
    // Guillemet fermant
    $pdf->SetFont('helvetica', 'B', 20);
    $pdf->SetTextColor($couleurAccent[0], $couleurAccent[1], $couleurAccent[2]);
    $currentY = $pdf->GetY();
    $pdf->SetXY($pageWidth - $titreMargin - 10, $currentY - 6);
    $pdf->Cell(10, 10, '"', 0, 0, 'R');
    
    // Spécialisation
    $pdf->SetFont('helvetica', '', 8);
    $pdf->SetTextColor($couleurSecondaire[0], $couleurSecondaire[1], $couleurSecondaire[2]);
    $pdf->SetXY($titreMargin, $currentY + 1);
    $pdf->Cell($titreWidth, 4, 'Spécialisation : ' . ($soutenance['specialisation'] ?? 'Non définie'), 0, 1, 'C');
    
    // ==============================
    // SECTION DATE ET LIEU (mise en avant)
    // ==============================
    $infoY = $currentY + 8;
    $boxWidth = 85;
    $boxHeight = 24;
    $boxSpacing = 10;
    $totalBoxWidth = 2 * $boxWidth + $boxSpacing;
    $boxStartX = ($pageWidth - $totalBoxWidth) / 2;
    
    $dateSoutenance = new DateTime($soutenance['date_soutenance']);
    $joursSemaine = ['Dimanche', 'Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi'];
    $mois = ['', 'Janvier', 'Février', 'Mars', 'Avril', 'Mai', 'Juin', 'Juillet', 'Août', 'Septembre', 'Octobre', 'Novembre', 'Décembre'];
    $jourSemaine = $joursSemaine[(int)$dateSoutenance->format('w')];
    $dateFormatee = $dateSoutenance->format('d') . ' ' . $mois[(int)$dateSoutenance->format('n')] . ' ' . $dateSoutenance->format('Y');
    $heureFormatee = $dateSoutenance->format('H:i');
    
    // Box DATE
    $pdf->SetFillColor($couleurPrimaire[0], $couleurPrimaire[1], $couleurPrimaire[2]);
    $pdf->RoundedRect($boxStartX, $infoY, $boxWidth, $boxHeight, 3, '1111', 'F');
    $pdf->SetFont('helvetica', 'B', 7);
    $pdf->SetTextColor($couleurAccent[0], $couleurAccent[1], $couleurAccent[2]);
    $pdf->SetXY($boxStartX, $infoY + 2);
    $pdf->Cell($boxWidth, 4, 'DATE & HEURE', 0, 1, 'C');
    $pdf->SetFont('helvetica', 'B', 9);
    $pdf->SetTextColor($couleurBlanc[0], $couleurBlanc[1], $couleurBlanc[2]);
    $pdf->SetXY($boxStartX, $infoY + 8);
    $pdf->Cell($boxWidth, 4, $jourSemaine . ' ' . $dateFormatee, 0, 1, 'C');
    $pdf->SetXY($boxStartX, $infoY + 14);
    $pdf->Cell($boxWidth, 4, 'à ' . $heureFormatee, 0, 1, 'C');
    
    // Box LIEU
    $lieuX = $boxStartX + $boxWidth + $boxSpacing;
    $pdf->SetFillColor($couleurSecondaire[0], $couleurSecondaire[1], $couleurSecondaire[2]);
    $pdf->RoundedRect($lieuX, $infoY, $boxWidth, $boxHeight, 3, '1111', 'F');
    $pdf->SetFont('helvetica', 'B', 7);
    $pdf->SetTextColor($couleurAccent[0], $couleurAccent[1], $couleurAccent[2]);
    $pdf->SetXY($lieuX, $infoY + 2);
    $pdf->Cell($boxWidth, 4, 'LIEU', 0, 1, 'C');
    $pdf->SetFont('helvetica', 'B', 9);
    $pdf->SetTextColor($couleurBlanc[0], $couleurBlanc[1], $couleurBlanc[2]);
    $pdf->SetXY($lieuX, $infoY + 10);
    $pdf->MultiCell($boxWidth, 4, $soutenance['lieu'] ?? 'À préciser', 0, 'C');
    
    // ==============================
    // SECTION ENCADREMENT ET JURY
    // ==============================
    $juryY = $infoY + $boxHeight + 6;
    $colWidth = ($pageWidth - 25) / 2;
    
    // Ligne décorative
    $pdf->SetDrawColor($couleurAccent[0], $couleurAccent[1], $couleurAccent[2]);
    $pdf->SetLineWidth(0.4);
    $pdf->Line(50, $juryY, $pageWidth - 50, $juryY);
    
    $juryY += 5;
    
    // Colonne ENCADREMENT
    $encX = 12;
    $pdf->SetFont('helvetica', 'B', 9);
    $pdf->SetTextColor($couleurPrimaire[0], $couleurPrimaire[1], $couleurPrimaire[2]);
    $pdf->SetXY($encX, $juryY);
    $pdf->Cell($colWidth, 4, 'ENCADREMENT', 0, 1, 'L');
    
    $juryY += 5;
    $pdf->SetFont('helvetica', '', 8);
    $pdf->SetTextColor($couleurTexte[0], $couleurTexte[1], $couleurTexte[2]);
    $directeur = $soutenance['directeur_noms'] ?? 'Non assigné';
    $directeurGrade = (!empty($soutenance['directeur_grade'])) ? $soutenance['directeur_grade'] . ' ' : '';
    $pdf->SetXY($encX, $juryY);
    $pdf->MultiCell($colWidth, 4, 'Directeur : ' . $directeurGrade . $directeur, 0, 'L');
    
    // Colonne JURY
    $juryX = $pageWidth / 2 + 3;
    $juryStartY = $juryY - 5;
    $pdf->SetFont('helvetica', 'B', 9);
    $pdf->SetTextColor($couleurPrimaire[0], $couleurPrimaire[1], $couleurPrimaire[2]);
    $pdf->SetXY($juryX, $juryStartY);
    $pdf->Cell($colWidth, 4, 'MEMBRES DU JURY', 0, 1, 'L');
    
    $juryStartY += 5;
    $pdf->SetFont('helvetica', '', 8);
    $pdf->SetTextColor($couleurTexte[0], $couleurTexte[1], $couleurTexte[2]);
    
    if (!empty($soutenance['president_nom'])) {
        $presidentGrade = (!empty($soutenance['president_grade'])) ? $soutenance['president_grade'] . ' ' : '';
        $pdf->SetXY($juryX, $juryStartY);
        $pdf->MultiCell($colWidth, 4, 'Président : ' . $presidentGrade . $soutenance['president_nom'], 0, 'L');
        $juryStartY += 4;
    }
    
    if (!empty($soutenance['secretaire_nom'])) {
        $secretaireGrade = (!empty($soutenance['secretaire_grade'])) ? $soutenance['secretaire_grade'] . ' ' : '';
        $pdf->SetXY($juryX, $juryStartY);
        $pdf->MultiCell($colWidth, 4, 'Secrétaire : ' . $secretaireGrade . $soutenance['secretaire_nom'], 0, 'L');
        $juryStartY += 4;
    }
    
    if (!empty($lecteurs)) {
        foreach ($lecteurs as $lecteur) {
            $nomLecteur = $lecteur['lecteur_noms'] ?? '';
            $gradeLecteur = (!empty($lecteur['grade'])) ? $lecteur['grade'] . ' ' : '';
            $role = $lecteur['est_premier_lecteur'] ? '1er Lecteur' : '2ème Lecteur';
            $pdf->SetXY($juryX, $juryStartY);
            $pdf->MultiCell($colWidth, 4, $role . ' : ' . $gradeLecteur . $nomLecteur, 0, 'L');
            $juryStartY += 4;
        }
    }
    
    // ==============================
    // PIED DE PAGE ÉLÉGANT
    // ==============================
    $footerHeight = 16;
    $footerY = $pageHeight - $footerHeight;
    
    // Bande de pied de page
    $pdf->SetFillColor($couleurPrimaire[0], $couleurPrimaire[1], $couleurPrimaire[2]);
    $pdf->Rect(0, $footerY, $pageWidth, $footerHeight, 'F');
    
    // Ligne dorée en haut du footer
    $pdf->SetDrawColor($couleurAccent[0], $couleurAccent[1], $couleurAccent[2]);
    $pdf->SetLineWidth(1);
    $pdf->Line(0, $footerY, $pageWidth, $footerY);
    
    // Texte footer
    $pdf->SetFont('helvetica', '', 7);
    $pdf->SetTextColor($couleurBlanc[0], $couleurBlanc[1], $couleurBlanc[2]);
    $pdf->SetXY(0, $footerY + 4);
    $pdf->Cell($pageWidth, 4, 'Vous êtes cordialement invité(e) à assister à cette soutenance', 0, 1, 'C');
    
    // Streamer le PDF directement au navigateur
    header('Content-Type: application/pdf');
    header('Content-Disposition: inline; filename="affiche_soutenance_' . $soutenanceId . '.pdf"');
    $pdf->Output('affiche_soutenance_' . $soutenanceId . '.pdf', 'I');
    exit;
    
} catch (Exception $e) {
    error_log('Erreur generate_defense_poster.php: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
