<?php
session_start();
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/vendor/autoload.php'; // Pour TCPDF

// Vérifier que l'utilisateur est connecté
if (!isset($_SESSION['id'])) {
    header('Location: ../login');
    exit();
}

// Récupérer les paramètres
$idECUE = isset($_GET['id_ecue']) ? intval($_GET['id_ecue']) : 0;
$idSession = isset($_GET['id_session']) ? intval($_GET['id_session']) : 0;
$idAnneeAcad = isset($_GET['annee_acad']) ? intval($_GET['annee_acad']) : 0;
$titreDocument = isset($_GET['titre_document']) ? trim($_GET['titre_document']) : 'Liste des recours à traiter';

// Validation des paramètres obligatoires
if (!$idECUE || !$idSession || !$idAnneeAcad) {
    die('Paramètres manquants ou invalides');
}

// Connexion à la base de données
$conn = Connexion::getInstance()->getPDO();

// Récupérer les informations de l'ECUE
$query_ecue = "SELECT e.designationECUE, u.designationUE
               FROM ecue e
               JOIN ue u ON e.UE_idUE = u.idUE
               WHERE e.idECUE = :idECUE";
$stmt_ecue = $conn->prepare($query_ecue);
$stmt_ecue->bindParam(':idECUE', $idECUE);
$stmt_ecue->execute();
$ecue_info = $stmt_ecue->fetch(PDO::FETCH_ASSOC);

if (!$ecue_info) {
    die("ECUE non trouvé.");
}

// Récupérer la session
$query_session = "SELECT designSession, description as descSession FROM session WHERE idsession = :idSession";
$stmt_session = $conn->prepare($query_session);
$stmt_session->bindParam(':idSession', $idSession);
$stmt_session->execute();
$session_info = $stmt_session->fetch(PDO::FETCH_ASSOC);

if (!$session_info) {
    die("Session non trouvée.");
}

// Récupérer l'année académique
$query_annee = "SELECT designation FROM annee_acad WHERE idannee_acad = :id_annee";
$stmt_annee = $conn->prepare($query_annee);
$stmt_annee->bindParam(':id_annee', $idAnneeAcad);
$stmt_annee->execute();
$annee = $stmt_annee->fetch(PDO::FETCH_ASSOC);

if (!$annee) {
    die("Année académique non trouvée.");
}

// Récupérer les recours
$query_recours = "
    SELECT r.id_recours, r.matricule, e.noms as nom_etudiant, p.designationPromotion,
           ec.designationECUE, u.designationUE, r.motif, r.date_creation, r.statut,
           s.designSession, r.description, s.description as descSession
    FROM recours r
    JOIN etudiant e ON r.matricule = e.matricule
    JOIN promotion p ON e.promotion_idpromotion = p.idpromotion
    JOIN ecue ec ON r.id_ecue = ec.idECUE
    JOIN ue u ON ec.UE_idUE = u.idUE
    JOIN session s ON r.id_session = s.idsession
    WHERE r.id_ecue = :idECUE
    AND r.id_session = :idSession
    AND r.id_annee_acad = :idAnneeAcad
    AND r.statut = 'En traitement' AND r.est_paye=1
    ORDER BY e.noms";

$stmt_recours = $conn->prepare($query_recours);
$stmt_recours->bindParam(':idECUE', $idECUE);
$stmt_recours->bindParam(':idSession', $idSession);
$stmt_recours->bindParam(':idAnneeAcad', $idAnneeAcad);
$stmt_recours->execute();
$recours = $stmt_recours->fetchAll(PDO::FETCH_ASSOC);

// Si aucun recours trouvé
if (count($recours) == 0) {
    die('Aucun recours en traitement trouvé pour ce cours et cette session');
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
        
        // Nom de l'université
        $configUniversite = $GLOBALS['etablissement'] ?? array('nom' => 'eGestion');
        $this->Cell(($this->getPageWidth() - 30) / 2, 5, ($configUniversite['nom'] ?? 'eGestion'), 0, 0, 'C');
    }
}

// Créer une instance de TCPDF en mode PAYSAGE
$pdf = new MYPDF('L', 'mm', 'A4', true, 'UTF-8', false);

// Rendre la variable etablissement accessible globalement pour le pied de page
$GLOBALS['etablissement'] = $etablissement;

// Configurer le document
$pdf->SetCreator('eGestion');
$pdf->SetAuthor($etablissement['nom'] ?? 'eGestion');
$pdf->SetTitle('Recours à traiter - ' . $ecue_info['designationECUE']);
$pdf->SetSubject('Recours académiques pour enseignant');
$pdf->SetKeywords('Recours, Enseignant, ECUE, PDF');

// Définir les marges et le pied de page
$pdf->setPrintHeader(false);
$pdf->setPrintFooter(true);
$pdf->SetMargins(15, 15, 15);
$pdf->SetAutoPageBreak(true, 20);

// Couleurs pour le design
$primaryColor = array(0, 87, 146); // Bleu foncé
$secondaryColor = array(70, 130, 180); // Bleu acier
$accentColor = array(0, 121, 194); // Bleu moyen

// Ajouter une page
$pdf->AddPage('L');

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
$pdf->Ln(10);
$pdf->Cell(0, 10, mb_strtoupper($titreDocument, 'UTF-8'), 0, 1, 'C', 1);

// Informations sur le cours et la session
$pdf->SetTextColor(80, 80, 80);
$pdf->SetFont('helvetica', 'B', 10);
$pdf->Ln(5);

// Afficher les infos du cours en deux colonnes
$pdf->Cell(40, 6, 'Cours (ECUE):', 0, 0, 'L');
$pdf->SetFont('helvetica', '', 10);
$pdf->Cell(100, 6, $ecue_info['designationECUE'], 0, 0, 'L');

$pdf->SetFont('helvetica', 'B', 10);
$pdf->Cell(90, 6, 'Session:', 0, 0, 'R');
$pdf->SetFont('helvetica', '', 10);
$pdf->Cell(0, 6, $session_info['descSession'], 0, 1, 'R');

$pdf->SetFont('helvetica', 'B', 10);
$pdf->Cell(40, 6, 'Unité d\'enseignement:', 0, 0, 'L');
$pdf->SetFont('helvetica', '', 10);
$pdf->Cell(100, 6, $ecue_info['designationUE'], 0, 0, 'L');

$pdf->SetFont('helvetica', 'B', 10);
$pdf->Cell(90, 6, 'Année académique:', 0, 0, 'R');
$pdf->SetFont('helvetica', '', 10);
$pdf->Cell(0, 6, $annee['designation'], 0, 1, 'R');

$pdf->Ln(3);
$pdf->SetDrawColor(200, 200, 200);
$pdf->SetFont('helvetica', '', 9);
$pdf->MultiCell(0, 6, 'Ce document contient la liste des recours à traiter pour votre cours. Veuillez indiquer votre décision pour chaque recours et retourner ce document dûment complété et signé au secrétariat académique.', 0, 'L');

$pdf->Ln(5);

// En-tête du tableau
$pdf->SetFillColor(245, 245, 245);
$pdf->SetTextColor(50, 50, 50);
$pdf->SetFont('helvetica', 'B', 9);

// Définir les largeurs de colonnes pour le tableau principal
$colWidths = array(10, 25, 50, 35, 35, 40, 25, 50);

$pdf->Cell($colWidths[0], 8, 'N°', 1, 0, 'C', 1);
$pdf->Cell($colWidths[1], 8, 'Matricule', 1, 0, 'C', 1);
$pdf->Cell($colWidths[2], 8, 'Étudiant', 1, 0, 'C', 1);
$pdf->Cell($colWidths[3], 8, 'Promotion', 1, 0, 'C', 1);
$pdf->Cell($colWidths[4], 8, 'Motif du recours', 1, 0, 'C', 1);
$pdf->Cell($colWidths[5], 8, 'Description', 1, 0, 'C', 1);
$pdf->Cell($colWidths[6], 8, 'Date dépôt', 1, 0, 'C', 1);
$pdf->Cell($colWidths[7], 8, 'Décision / Nouvelle note', 1, 1, 'C', 1);

// Données du tableau
$pdf->SetFont('helvetica', '', 8);
$i = 1;

foreach ($recours as $r) {
    // Vérifier si on a besoin d'une nouvelle page
    if ($pdf->GetY() > 180) { // Valeur ajustée pour le mode paysage
        $pdf->AddPage('L');
        
        // Répéter l'en-tête du tableau
        $pdf->SetFillColor(245, 245, 245);
        $pdf->SetTextColor(50, 50, 50);
        $pdf->SetFont('helvetica', 'B', 9);
        
        $pdf->Cell($colWidths[0], 8, 'N°', 1, 0, 'C', 1);
        $pdf->Cell($colWidths[1], 8, 'Matricule', 1, 0, 'C', 1);
        $pdf->Cell($colWidths[2], 8, 'Étudiant', 1, 0, 'C', 1);
        $pdf->Cell($colWidths[3], 8, 'Promotion', 1, 0, 'C', 1);
        $pdf->Cell($colWidths[4], 8, 'Motif du recours', 1, 0, 'C', 1);
        $pdf->Cell($colWidths[5], 8, 'Description', 1, 0, 'C', 1);
        $pdf->Cell($colWidths[6], 8, 'Date dépôt', 1, 0, 'C', 1);
        $pdf->Cell($colWidths[7], 8, 'Décision / Nouvelle note', 1, 1, 'C', 1);
        
        $pdf->SetFont('helvetica', '', 8);
    }
    
    // Alternance de couleur pour les lignes
    $fill = ($i % 2 == 0) ? true : false;
    
    // Préparation du texte de description pour MultiCell
    $description = !empty($r['description']) ? $r['description'] : '-';
    
    // Calcul de la hauteur nécessaire pour la description
    $pdf->startTransaction();
    $start_page = $pdf->getPage();
    $start_y = $pdf->GetY();
    $pdf->MultiCell($colWidths[5], 7, $description, 0, 'L');
    $end_y = $pdf->GetY();
    $cell_height = $end_y - $start_y;
    $pdf = $pdf->rollbackTransaction();
    
    // Définir une hauteur minimale
    $cell_height = max($cell_height, 7);
    
    // Dessiner les cellules avec la hauteur calculée
    $current_y = $pdf->GetY();
    
    $pdf->Cell($colWidths[0], $cell_height, $i, 1, 0, 'C', $fill);
    $pdf->Cell($colWidths[1], $cell_height, $r['matricule'], 1, 0, 'C', $fill);
    $pdf->Cell($colWidths[2], $cell_height, $r['nom_etudiant'], 1, 0, 'L', $fill);
    $pdf->Cell($colWidths[3], $cell_height, $r['designationPromotion'], 1, 0, 'L', $fill);
    $pdf->Cell($colWidths[4], $cell_height, $r['motif'], 1, 0, 'L', $fill);
    
    // Utiliser MultiCell pour la description
    $start_x = $pdf->GetX();
    $start_y = $pdf->GetY();
    $pdf->MultiCell($colWidths[5], $cell_height, $description, 1, 'L', $fill);
    $pdf->SetXY($start_x + $colWidths[5], $start_y);
    
    $pdf->Cell($colWidths[6], $cell_height, date('d/m/Y', strtotime($r['date_creation'])), 1, 0, 'C', $fill);
    $pdf->Cell($colWidths[7], $cell_height, '', 1, 1, 'C', $fill); // Colonne vide pour la décision
    $i++;
}

$pdf->Ln(5);

// Instructions pour remplir le document
$pdf->SetFont('helvetica', 'B', 11);
$pdf->Cell(0, 8, 'INSTRUCTIONS POUR REMPLIR LE TABLEAU:', 0, 1, 'L');
$pdf->SetFont('helvetica', '', 10);
$pdf->Cell(0, 6, '1. Pour chaque recours, indiquez votre décision dans la dernière colonne du tableau.', 0, 1, 'L');
$pdf->Cell(0, 6, '2. Utilisez les codes suivants: A (Approuvé), R (Rejeté), AR (À revoir).', 0, 1, 'L');
$pdf->Cell(0, 6, '3. Si vous modifiez une note, indiquez-la sous ce format: "A - 12/20" (pour Approuvé avec nouvelle note 12/20).', 0, 1, 'L');

// Zone de commentaires généraux
$pdf->Ln(5);
$pdf->SetFont('helvetica', 'B', 11);
$pdf->Cell(0, 8, 'COMMENTAIRES GÉNÉRAUX:', 0, 1, 'L');
$pdf->SetDrawColor(180, 180, 180);
$pdf->SetLineWidth(0.1);
$startY = $pdf->GetY();
$pdf->Rect(15, $startY, $pdf->getPageWidth() - 30, 30);
$pdf->SetFont('helvetica', 'I', 9);
$pdf->SetTextColor(150, 150, 150);
$pdf->SetXY(20, $startY + 10);
$pdf->Write(0, 'Espace pour observations générales sur les recours traités');
$pdf->SetTextColor(50, 50, 50);

$pdf->Ln(5);

// Zone pour signature
$pdf->SetFont('helvetica', '', 10);
$pdf->Cell(80, 8, 'Date: _____ / _____ / _________', 0, 0, 'L');
$pdf->Cell(100, 8, 'Signature de l\'enseignant:', 0, 1, 'L');
$pdf->Cell(0, 20, '', 0, 1); // Espace pour la signature
$pdf->Cell(100, 8, '__________________________________________', 0, 1, 'L');
$pdf->Cell(100, 8, 'Nom et prénom en lettres capitales', 0, 1, 'C');

// Section pour approbation administrative
$pdf->AddPage('L');
$pdf->SetFont('helvetica', 'B', 14);
$pdf->SetTextColor($primaryColor[0], $primaryColor[1], $primaryColor[2]);
$pdf->Cell(0, 10, 'SECTION RÉSERVÉE À L\'ADMINISTRATION', 0, 1, 'C');
$pdf->Ln(5);

$pdf->SetFont('helvetica', '', 10);
$pdf->SetTextColor(50, 50, 50);
$pdf->MultiCell(0, 6, 'Cette section permet de valider officiellement les décisions prises par l\'enseignant concernant les recours traités dans ce document.', 0, 'L');
$pdf->Ln(5);

// Tableau de validation administrative
$pdf->SetFillColor(245, 245, 245);
$pdf->SetFont('helvetica', 'B', 10);
$pdf->Cell(0, 8, 'VALIDATION ADMINISTRATIVE', 1, 1, 'C', 1);

$pdf->SetFont('helvetica', '', 10);
$pdf->Cell(60, 10, 'Reçu le:', 1, 0, 'L');
$pdf->Cell(100, 10, '_____ / _____ / _________', 1, 0, 'L');
$pdf->Cell(60, 10, 'Nombre de recours traités:', 1, 0, 'L');
$pdf->Cell(47, 10, '______ / ' . count($recours), 1, 1, 'L');

$pdf->Cell(60, 10, 'Traité par:', 1, 0, 'L');
$pdf->Cell(207, 10, '', 1, 1, 'L');

$pdf->Cell(60, 20, 'Observations:', 1, 0, 'L');
$pdf->Cell(207, 20, '', 1, 1, 'L');

$pdf->Cell(60, 10, 'Date de validation:', 1, 0, 'L');
$pdf->Cell(207, 10, '_____ / _____ / _________', 1, 1, 'L');

// Zone pour signature administrative
$pdf->Ln(10);
$pdf->Cell(135, 6, 'Signature du responsable pédagogique:', 0, 0, 'L');
$pdf->Cell(135, 6, 'Cachet de l\'établissement:', 0, 1, 'L');
$pdf->Ln(20);
$pdf->Cell(135, 6, '__________________________________________', 0, 0, 'L');
$pdf->Cell(135, 6, '__________________________________________', 0, 1, 'L');

// Générer le PDF
$filename = 'Recours_Enseignant_' . str_replace(' ', '_', $ecue_info['designationECUE']) . '_' . date('Ymd_His') . '.pdf';
$pdf->Output($filename, 'I'); // 'I' pour afficher dans le navigateur
exit;
