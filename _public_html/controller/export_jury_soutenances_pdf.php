<?php
session_start();
error_reporting(E_ALL);
ini_set("display_errors", 0);
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/vendor/autoload.php';
require_once dirname(__DIR__) . '/models/Universite.php';

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['id'])) {
    header('Location: ../login');
    exit();
}

// Récupérer les paramètres
$juryId = isset($_GET['jury_id']) ? intval($_GET['jury_id']) : 0;
$yearId = isset($_GET['annee_acad']) ? intval($_GET['annee_acad']) : 0;

if ($juryId <= 0 || $yearId <= 0) {
    echo "Paramètres invalides";
    exit;
}

// Initialiser la connexion
$db = Connexion::getInstance()->getPDO();
$universite = new Universite();

try {
    // Récupérer les informations de configuration de l'université
    $configUniversite = $universite->getConfigurationUniversite();

    // Récupérer le jury avec les informations complètes
    $queryJury = "SELECT * FROM jury WHERE idjury = :juryId AND annee_acad_id = :yearId";
    $stmtJury = $db->prepare($queryJury);
    $stmtJury->execute(['juryId' => $juryId, 'yearId' => $yearId]);
    $jury = $stmtJury->fetch(PDO::FETCH_ASSOC);

    if (!$jury) {
        throw new Exception("Jury non trouvé");
    }

    // Récupérer l'année académique
    $annee = $universite->getAnneeAcademiqueById($yearId);

    // Récupérer les informations de la section (basé sur la première soutenance)
    $sectionInfo = null;
    
    // D'abord, récupérer une soutenance pour obtenir la promotion/orientation
    $queryTemp = "SELECT sj.idSpecialisation, sp.idorientation
                  FROM soutenance s
                  JOIN sujets sj ON s.sujets_idsujets = sj.idsujets
                  LEFT JOIN specialisation sp ON sj.idSpecialisation = sp.idspecialisation
                  WHERE s.annee_acad_idannee_acad = :yearId AND s.jury_id = :juryId
                  LIMIT 1";
    $stmtTemp = $db->prepare($queryTemp);
    $stmtTemp->execute(['yearId' => $yearId, 'juryId' => $juryId]);
    $tempSoutenance = $stmtTemp->fetch(PDO::FETCH_ASSOC);
    
    // Si on a une soutenance, récupérer la section via l'orientation
    if ($tempSoutenance && isset($tempSoutenance['idorientation'])) {
        try {
            $querySection = "SELECT s.* FROM section s 
                            INNER JOIN orientation o ON s.idsection = o.section_idsection
                            WHERE o.idorientation = :idorientation";
            $stmtSection = $db->prepare($querySection);
            $stmtSection->execute(['idorientation' => $tempSoutenance['idorientation']]);
            $sectionInfo = $stmtSection->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            // Erreur silencieuse pour section
        }
    }

    // Récupérer les soutenances du jury
    $query = "SELECT s.*, 
                     sj.intitule as sujet_titre, sj.idsujets,
                     e.noms as etudiant_nom, e.matricule,
                     d.noms as directeur_nom,
                     gd.designation as directeur_grade,
                     sp.designation as specialisation,
                     dm.idDepot, dm.fichier as memoire_fichier, dm.dateDepot
              FROM soutenance s
              JOIN sujets sj ON s.sujets_idsujets = sj.idsujets
              JOIN etudiant e ON sj.etudiant_idetudiant = e.idetudiant
              LEFT JOIN agent d ON sj.idDirecteur = d.idAgent
              LEFT JOIN grade gd ON d.grade_id = gd.idgrade
              LEFT JOIN specialisation sp ON sj.idSpecialisation = sp.idSpecialisation
              LEFT JOIN depot_memoire dm ON sj.idsujets = dm.sujets_idsujets
              WHERE s.annee_acad_idannee_acad = :yearId AND s.jury_id = :juryId
              ORDER BY s.date_soutenance DESC";

    $stmt = $db->prepare($query);
    $stmt->execute(['yearId' => $yearId, 'juryId' => $juryId]);
    $soutenances = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Récupérer les lecteurs pour chaque soutenance
    foreach ($soutenances as &$soutenance) {
        $queryLecteurs = "SELECT a.noms, COALESCE(g.designation, '') as grade, ls.est_premier_lecteur 
                         FROM lecteurs_soutenance ls
                         JOIN agent a ON ls.idenseignant = a.idAgent
                         LEFT JOIN grade g ON a.grade_id = g.idgrade
                         WHERE ls.idsoutenance = :idSoutenance
                         ORDER BY ls.est_premier_lecteur DESC";
        $stmtLecteurs = $db->prepare($queryLecteurs);
        $stmtLecteurs->execute(['idSoutenance' => $soutenance['idsoutenance']]);
        $soutenance['lecteurs'] = $stmtLecteurs->fetchAll(PDO::FETCH_ASSOC);
    }

    // Classe PDF personnalisée
    class MYPDF extends \TCPDF
    {
        private $documentTitle = '';
        private $documentNumber = '';
        
        public function setDocumentInfo($title, $number)
        {
            $this->documentTitle = $title;
            $this->documentNumber = $number;
        }

        // En-tête personnalisé
        public function Header()
        {
            $configUniversite = $GLOBALS['configUniversite'] ?? [];
            $primaryColor = $GLOBALS['primaryColor'] ?? array(0, 87, 146);
            $accentColor = $GLOBALS['accentColor'] ?? array(0, 121, 194);

            // Logo de l'université
            $logoSize = 12;
            if (!empty($configUniversite['logo'])) {
                $logoPath = dirname(__DIR__) . '/' . $configUniversite['logo'];
                if (file_exists($logoPath)) {
                    $this->Image($logoPath, 10, 10, $logoSize, 0, '', '', '', false, 200, '', false, false, 0);
                }
            }

            // Titre et informations de l'université
            $this->SetTextColor($primaryColor[0], $primaryColor[1], $primaryColor[2]);
            $this->SetFont('helvetica', 'B', 10);
            $this->SetY(10);
            $this->SetX(10 + $logoSize + 5);
            $this->Cell(0, 3, strtoupper($configUniversite['ministere_tutelle'] ?? 'ENSEIGNEMENT SUPÉRIEUR'), 0, 1, 'C');

            $this->SetFont('helvetica', 'B', 12);
            $this->SetX(10 + $logoSize + 5);
            $this->Cell(0, 4, strtoupper($configUniversite['nom'] ?? 'UNIVERSITÉ'), 0, 1, 'C');

            // Afficher le nom de la section si elle existe
            $sectionInfo = $GLOBALS['sectionInfo'] ?? null;
            if ($sectionInfo && !empty($sectionInfo['designationSection'])) {
                $this->SetFont('helvetica', 'B', 10);
                $this->SetX(10 + $logoSize + 5);
                $this->Cell(0, 4, strtoupper($sectionInfo['designationSection']), 0, 1, 'C');
            }

            $this->SetFont('helvetica', '', 8);
            $this->SetTextColor(80, 80, 80);
            $this->SetX(10 + $logoSize + 5);

            $contactInfo = '';
            if (!empty($configUniversite['telephone'])) {
                $contactInfo .= 'Tél: ' . $configUniversite['telephone'] . ' ';
            }
            if (!empty($configUniversite['email'])) {
                $contactInfo .= 'Email: ' . $configUniversite['email'] . ' ';
            }
            if (!empty($configUniversite['site_web'])) {
                $contactInfo .= 'Web: ' . $configUniversite['site_web'];
            }

            if (!empty($contactInfo)) {
                $this->SetX(10 + $logoSize + 5);
                $this->Cell(0, 3, $contactInfo, 0, 1, 'C');
            }

            // Ligne de séparation
            $this->Ln(5);
            $this->SetLineStyle(array('width' => 0.3, 'cap' => 'butt', 'join' => 'miter', 'dash' => 0, 'color' => $accentColor));
            $this->Line(10, $this->GetY(), $this->getPageWidth() - 10, $this->GetY());
        }

        // Pied de page personnalisé
        public function Footer()
        {
            $configUniversite = $GLOBALS['configUniversite'] ?? [];

            // Position à 20mm du bas
            $this->SetY(-20);

            // Ligne de séparation fine
            $this->SetLineStyle(array('width' => 0.2, 'cap' => 'butt', 'join' => 'miter', 'dash' => 0, 'color' => array(200, 200, 200)));
            $this->Line(15, $this->GetY(), $this->getPageWidth() - 15, $this->GetY());
            $this->Ln(2);

            // Police et couleur
            $this->SetFont('helvetica', 'I', 8);
            $this->SetTextColor(100, 100, 100);

            // Numérotation des pages (à gauche)
            $this->SetX(15);
            $this->Cell(60, 5, 'Page ' . $this->getAliasNumPage() . ' sur ' . $this->getAliasNbPages(), 0, 0, 'L');

            // Date (centré)
            $this->SetX(75);
            $this->Cell(60, 5, 'Document généré le ' . date('d/m/Y H:i'), 0, 0, 'C');

            // Institution (à droite)
            $this->SetX(135);
            $this->Cell(60, 5, ($configUniversite['nom'] ?? 'E-GESTION'), 0, 1, 'R');
        }
    }

    // Couleurs
    $primaryColor = array(0, 87, 146);
    $secondaryColor = array(70, 130, 180);
    $accentColor = array(0, 121, 194);

    // Créer l'instance PDF (en paysage)
    $pdf = new MYPDF('L', 'mm', 'A4', true, 'UTF-8', false);
    
    // Rendre les variables accessibles globalement
    $GLOBALS['configUniversite'] = $configUniversite;
    $GLOBALS['primaryColor'] = $primaryColor;
    $GLOBALS['accentColor'] = $accentColor;
    $GLOBALS['sectionInfo'] = $sectionInfo;

    // Configurer le document
    $titreDocument = 'PROGRAMME DES SOUTENANCES';
    $pdf->setDocumentInfo($titreDocument, $jury['designation']);

    $pdf->SetCreator('E-GESTION');
    $pdf->SetAuthor($configUniversite['nom'] ?? 'E-GESTION');
    $pdf->SetTitle($titreDocument . ' - ' . $jury['designation']);
    $pdf->SetSubject('Soutenances de Mémoires');
    $pdf->SetKeywords('Soutenance, Mémoire, Jury, ' . $jury['designation']);

    // Configurer les marges et ruptures de page
    $pdf->setPrintHeader(true);
    $pdf->setPrintFooter(true);
    $pdf->SetMargins(10, 30, 10);
    $pdf->SetAutoPageBreak(true, 25);

    // Ajouter une page
    $pdf->AddPage();

    // TITRE PRINCIPAL
    $pdf->SetFillColor($primaryColor[0], $primaryColor[1], $primaryColor[2]);
    $pdf->SetTextColor(255, 255, 255);
    $pdf->SetFont('helvetica', 'B', 14);
    $pdf->Cell(0, 8, 'PROGRAMME DES SOUTENANCES', 0, 1, 'C', true);
    $pdf->Ln(3);

    // INFORMATIONS DU JURY
    $pdf->SetFont('helvetica', 'B', 11);
    $pdf->SetTextColor($secondaryColor[0], $secondaryColor[1], $secondaryColor[2]);
    $pdf->Cell(0, 7, 'INFORMATIONS DU JURY', 0, 1, 'L');

    // Ligne décorative
    $pdf->SetLineStyle(array('width' => 0.2, 'cap' => 'butt', 'join' => 'miter', 'dash' => 0, 'color' => $secondaryColor));
    $pdf->Line(10, $pdf->GetY(), 80, $pdf->GetY());
    $pdf->Ln(3);

    // Informations en deux colonnes
    $pdf->SetFont('helvetica', '', 10);
    $pdf->SetTextColor(0, 0, 0);
    $currentY = $pdf->GetY();
    $maxYLeft = $currentY;
    $maxYRight = $currentY;

    // Colonne gauche
    $leftX = 10;
    $pdf->SetXY($leftX, $currentY);

    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->Cell(35, 5, 'Jury:', 0, 0, 'L');
    $pdf->SetFont('helvetica', '', 10);
    $pdf->MultiCell(60, 5, htmlspecialchars($jury['designation']), 0, 'L');
    $maxYLeft = max($maxYLeft, $pdf->GetY());

    $pdf->SetXY($leftX, $pdf->GetY());
    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->Cell(35, 5, 'Année académique:', 0, 0, 'L');
    $pdf->SetFont('helvetica', '', 10);
    $anneeLabel = $annee ? htmlspecialchars($annee['designation']) : 'Non trouvée';
    $pdf->MultiCell(60, 5, $anneeLabel, 0, 'L');
    $maxYLeft = max($maxYLeft, $pdf->GetY());

    // Colonne droite
    $rightX = 110;
    $pdf->SetXY($rightX, $currentY);

    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->Cell(35, 5, 'Date du rapport:', 0, 0, 'L');
    $pdf->SetFont('helvetica', '', 10);
    $pdf->Cell(50, 5, date('d/m/Y H:i'), 0, 1, 'L');

    $pdf->SetXY($rightX, $pdf->GetY());
    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->Cell(35, 5, 'Total soutenances:', 0, 0, 'L');
    $pdf->SetFont('helvetica', '', 10);
    $pdf->Cell(50, 5, count($soutenances), 0, 1, 'L');

    $pdf->SetY(max($maxYLeft, $maxYRight) + 5);

    // PROGRAMME DES SOUTENANCES
    $pdf->SetFont('helvetica', 'B', 11);
    $pdf->SetTextColor($secondaryColor[0], $secondaryColor[1], $secondaryColor[2]);
    $pdf->Cell(0, 7, 'PROGRAMME DE SOUTENANCE', 0, 1, 'L');

    // Ligne décorative
    $pdf->SetLineStyle(array('width' => 0.2, 'cap' => 'butt', 'join' => 'miter', 'dash' => 0, 'color' => $secondaryColor));
    $pdf->Line(10, $pdf->GetY(), 287, $pdf->GetY());
    $pdf->Ln(2);

    // Configuration simple des colonnes du tableau (Paysage A4: 277mm utile avec marges 10mm)
    $col1 = 12;  // N°
    $col2 = 35;  // Étudiant
    $col3 = 80;  // Titre du Mémoire
    $col4 = 30;  // Directeur
    $col5 = 35;  // Date & Heure
    $col6 = 25;  // Lieu
    $col7 = 60;  // Lecteurs

    // En-têtes du tableau
    $pdf->SetTextColor(255, 255, 255);
    $pdf->SetFillColor($primaryColor[0], $primaryColor[1], $primaryColor[2]);
    $pdf->SetFont('helvetica', 'B', 7);
    $pdf->SetX(10);

    $pdf->Cell($col1, 6, 'N°', 1, 0, 'C', true);
    $pdf->Cell($col2, 6, 'Étudiant', 1, 0, 'C', true);
    $pdf->Cell($col3, 6, 'Titre du Mémoire', 1, 0, 'C', true);
    $pdf->Cell($col4, 6, 'Directeur', 1, 0, 'C', true);
    $pdf->Cell($col5, 6, 'Date & Heure', 1, 0, 'C', true);
    $pdf->Cell($col6, 6, 'Lieu', 1, 0, 'C', true);
    $pdf->Cell($col7, 6, 'Lecteurs', 1, 1, 'C', true);

    // Contenu du tableau
    $pdf->SetTextColor(0, 0, 0);
    $pdf->SetFont('helvetica', '', 8);
    $counter = 1;
    $bgColor = false;

    if (!empty($soutenances)) {
        foreach ($soutenances as $soutenance) {
            // Vérifier s'il y a assez d'espace
            if ($pdf->GetY() + 15 > $pdf->getPageHeight() - 30) {
                $pdf->AddPage();
                
                // Réafficher les en-têtes
                $pdf->SetTextColor(255, 255, 255);
                $pdf->SetFillColor($primaryColor[0], $primaryColor[1], $primaryColor[2]);
                $pdf->SetFont('helvetica', 'B', 7);
                $pdf->SetX(10);
                
                $pdf->Cell($col1, 6, 'N°', 1, 0, 'C', true);
                $pdf->Cell($col2, 6, 'Étudiant', 1, 0, 'C', true);
                $pdf->Cell($col3, 6, 'Titre du Mémoire', 1, 0, 'C', true);
                $pdf->Cell($col4, 6, 'Directeur', 1, 0, 'C', true);
                $pdf->Cell($col5, 6, 'Date & Heure', 1, 0, 'C', true);
                $pdf->Cell($col6, 6, 'Lieu', 1, 0, 'C', true);
                $pdf->Cell($col7, 6, 'Lecteurs', 1, 1, 'C', true);
                
                $pdf->SetTextColor(0, 0, 0);
                $pdf->SetFont('helvetica', '', 8);
                $bgColor = false;
            }

            // Alterner les couleurs
            $bgColor = !$bgColor;
            if ($bgColor) {
                $pdf->SetFillColor(245, 245, 245);
            } else {
                $pdf->SetFillColor(255, 255, 255);
            }

            // Préparer les données
             $numero = $counter;
             $etudiant = substr($soutenance['etudiant_nom'], 0, 25);
             $titre = $soutenance['sujet_titre'];
             $directeurGrade = (!empty($soutenance['directeur_grade'])) ? $soutenance['directeur_grade'] . ' ' : '';
             $directeur = substr($directeurGrade . ($soutenance['directeur_nom'] ?? '-'), 0, 20);
            
            // Formater la date et l'heure
            $dateHeure = '-';
            if ($soutenance['date_soutenance']) {
                $dateHeure = date('d/m/Y', strtotime($soutenance['date_soutenance'])) . "\n" . 
                             date('H:i', strtotime($soutenance['date_soutenance']));
            }
            
            $lieu = $soutenance['lieu'] ?? '-';
            
            // Formater les lecteurs
            $lecteursText = 'Non assignés';
            if (!empty($soutenance['lecteurs'])) {
                $lecteursArray = [];
                foreach ($soutenance['lecteurs'] as $lecteur) {
                    $gradeLecteur = (!empty($lecteur['grade'])) ? $lecteur['grade'] . ' ' : '';
                    $lecteursArray[] = substr($gradeLecteur . $lecteur['noms'], 0, 25);
                }
                $lecteursText = implode("\n", $lecteursArray);
            }

            // Calculer la hauteur nécessaire pour chaque cellule
            $pdf->SetFont('helvetica', '', 8);
            
            $heightTitre = $pdf->getStringHeight($col3, $titre);
            $heightLecteurs = $pdf->getStringHeight($col7, $lecteursText);
            $heightEtudiant = $pdf->getStringHeight($col2, $etudiant);
            $heightDirecteur = $pdf->getStringHeight($col4, $directeur);
            
            // Prendre la hauteur maximale (minimum 8mm)
            $rowHeight = max(8, $heightTitre, $heightLecteurs, $heightEtudiant, $heightDirecteur);

            // Sauvegarder la position Y de départ
            $startY = $pdf->GetY();
            $startX = 10;

            // Définir le style de ligne pour les bordures
            $pdf->SetLineStyle(array('width' => 0.2, 'cap' => 'butt', 'join' => 'miter', 'dash' => 0, 'color' => array(0, 0, 0)));
            $pdf->SetDrawColor(0, 0, 0);

            // Remplir le fond d'abord
            if ($bgColor) {
                $pdf->SetFillColor(245, 245, 245);
                $pdf->Rect($startX, $startY, $col1 + $col2 + $col3 + $col4 + $col5 + $col6 + $col7, $rowHeight, 'F');
            }

            // Dessiner toutes les bordures de cellules avec style défini
            $pdf->Rect($startX, $startY, $col1, $rowHeight, 'D');
            $pdf->Rect($startX + $col1, $startY, $col2, $rowHeight, 'D');
            $pdf->Rect($startX + $col1 + $col2, $startY, $col3, $rowHeight, 'D');
            $pdf->Rect($startX + $col1 + $col2 + $col3, $startY, $col4, $rowHeight, 'D');
            $pdf->Rect($startX + $col1 + $col2 + $col3 + $col4, $startY, $col5, $rowHeight, 'D');
            $pdf->Rect($startX + $col1 + $col2 + $col3 + $col4 + $col5, $startY, $col6, $rowHeight, 'D');
            $pdf->Rect($startX + $col1 + $col2 + $col3 + $col4 + $col5 + $col6, $startY, $col7, $rowHeight, 'D');

            // Réinitialiser la couleur de texte
            $pdf->SetTextColor(0, 0, 0);

            // N° (centré verticalement)
            $pdf->SetXY($startX, $startY + ($rowHeight - 5) / 2);
            $pdf->Cell($col1, 5, $numero, 0, 0, 'C');

            // Étudiant (MultiCell)
            $pdf->SetXY($startX + $col1 + 0.5, $startY + 1);
            $pdf->MultiCell($col2 - 1, 4, $etudiant, 0, 'L', false, 0);

            // Titre (MultiCell)
            $pdf->SetXY($startX + $col1 + $col2 + 0.5, $startY + 1);
            $pdf->MultiCell($col3 - 1, 4, $titre, 0, 'L', false, 0);

            // Directeur (MultiCell)
            $pdf->SetXY($startX + $col1 + $col2 + $col3 + 0.5, $startY + 1);
            $pdf->MultiCell($col4 - 1, 4, $directeur, 0, 'L', false, 0);

            // Date & Heure (MultiCell, centré)
            $pdf->SetXY($startX + $col1 + $col2 + $col3 + $col4 + 0.5, $startY + 1);
            $pdf->MultiCell($col5 - 1, 4, $dateHeure, 0, 'C', false, 0);

            // Lieu (centré verticalement)
            $pdf->SetXY($startX + $col1 + $col2 + $col3 + $col4 + $col5, $startY + ($rowHeight - 5) / 2);
            $pdf->Cell($col6, 5, substr($lieu, 0, 12), 0, 0, 'C');

            // Lecteurs (MultiCell)
            $pdf->SetXY($startX + $col1 + $col2 + $col3 + $col4 + $col5 + $col6 + 0.5, $startY + 1);
            $pdf->MultiCell($col7 - 1, 4, $lecteursText, 0, 'L', false, 0);

            // Positionner à la ligne suivante
            $pdf->SetY($startY + $rowHeight);

            $counter++;
        }
    } else {
        $pdf->SetFillColor(255, 255, 255);
        $totalWidth = $col1 + $col2 + $col3 + $col4 + $col5 + $col6 + $col7;
        $pdf->Cell($totalWidth, 10, 'Aucune soutenance programmée pour ce jury', 1, 1, 'C', false);
    }

    // Espace avant les signatures
    $pdf->Ln(5);

    // VALIDATION ET SIGNATURE
    $espaceDisponible = $pdf->getPageHeight() - $pdf->GetY() - 30;
    if ($espaceDisponible < 40) {
        $pdf->AddPage();
    }

  
    // Signature
    $pdf->SetFont('helvetica', '', 10);
    $pdf->SetTextColor(0, 0, 0);

    // Position de la signature à droite
    $signatureWidth = 70;
    $y = $pdf->GetY();
    $xSignature = $pdf->getPageWidth() - $signatureWidth - 15;
    
    // Espace pour la signature
    $pdf->SetXY($xSignature, $y);
    $pdf->SetFont('helvetica', '', 10);
    $pdf->Cell($signatureWidth, 5, 'Fait à Kinshasa, le ' . date('d/m/Y'), 0, 1, 'C');
    
    $pdf->Ln(15);
    
    // Titre du signataire
    $pdf->SetXY($xSignature, $pdf->GetY());
    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->Cell($signatureWidth, 5, 'Le Chef de Section', 0, 1, 'C');
    $pdf->SetXY($xSignature, $pdf->GetY());
    $pdf->Cell($signatureWidth, 5, 'en charge de la Recherche', 0, 1, 'C');
    
    $pdf->Ln(10);
    
    // Ligne de signature
    $pdf->SetXY($xSignature, $pdf->GetY());
    $pdf->Line($xSignature, $pdf->GetY(), $xSignature + $signatureWidth, $pdf->GetY());

    // Générer le PDF
    ob_clean();
    $pdf->Output('soutenances_' . $juryId . '_' . date('Y-m-d-His') . '.pdf', 'I');

} catch (Exception $e) {
    error_log("Erreur PDF: " . $e->getMessage());
    echo "Erreur lors de la génération du PDF: " . htmlspecialchars($e->getMessage());
    exit();
}