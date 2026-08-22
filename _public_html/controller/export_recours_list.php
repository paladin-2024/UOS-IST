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
$date_debut = isset($_GET['date_debut']) ? $_GET['date_debut'] : date('Y-m-d', strtotime('-30 days'));
$date_fin = isset($_GET['date_fin']) ? $_GET['date_fin'] : date('Y-m-d');
$statut_paiement = isset($_GET['statut_paiement']) ? $_GET['statut_paiement'] : 'tous';
$statut_recours = isset($_GET['statut_recours']) ? $_GET['statut_recours'] : 'tous';
$id_session = isset($_GET['id_session']) ? intval($_GET['id_session']) : 0;
$id_annee = isset($_GET['annee_acad']) ? intval($_GET['annee_acad']) : 0;

// Connexion à la base de données
$conn = Connexion::getInstance()->getPDO();

// Récupérer l'année académique
$query_annee = "SELECT designation FROM annee_acad WHERE idannee_acad = :id_annee";
$stmt_annee = $conn->prepare($query_annee);
$stmt_annee->bindParam(':id_annee', $id_annee);
$stmt_annee->execute();
$annee = $stmt_annee->fetch(PDO::FETCH_ASSOC);

if (!$annee) {
    die("Année académique non trouvée.");
}

// Récupérer les informations
// Construire la requête pour récupérer les recours selon les filtres
$query_recours = "
    SELECT r.id_recours, r.matricule, e.noms as nom_etudiant, p.designationPromotion,
           ec.designationECUE, u.designationUE, r.motif, r.date_creation, r.statut,
           s.designSession, r.est_paye, r.description,
           CONCAT(us.nomUser) as encodeur
    FROM recours r
    JOIN etudiant e ON r.matricule = e.matricule
    JOIN promotion p ON e.promotion_idpromotion = p.idpromotion
    JOIN ecue ec ON r.id_ecue = ec.idECUE
    JOIN ue u ON ec.UE_idUE = u.idUE
    JOIN session s ON r.id_session = s.idsession
    LEFT JOIN t_users us ON r.id_createur = us.idUser
    WHERE r.id_annee_acad = :id_annee
    AND r.date_creation BETWEEN :date_debut AND :date_fin";

// Ajouter les filtres optionnels
$params = [
    ':id_annee' => $id_annee,
    ':date_debut' => $date_debut,
    ':date_fin' => $date_fin . ' 23:59:59' // Pour inclure tout le jour de fin
];

if ($statut_paiement !== 'tous') {
    $query_recours .= " AND r.est_paye = :est_paye";
    $params[':est_paye'] = $statut_paiement;
}

if ($statut_recours !== 'tous') {
    $query_recours .= " AND r.statut = :statut";
    $params[':statut'] = $statut_recours;
}

if ($id_session > 0) {
    $query_recours .= " AND r.id_session = :id_session";
    $params[':id_session'] = $id_session;
}

// Trier par date de création (plus récent en premier)
$query_recours .= " ORDER BY r.date_creation DESC";

$stmt_recours = $conn->prepare($query_recours);
foreach ($params as $key => $value) {
    $stmt_recours->bindValue($key, $value);
}
$stmt_recours->execute();
$recours = $stmt_recours->fetchAll(PDO::FETCH_ASSOC);

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

// Créer une instance de la classe personnalisée
$pdf = new MYPDF('L', 'mm', 'A4', true, 'UTF-8', false);

// Rendre la variable etablissement accessible globalement pour le pied de page
$GLOBALS['etablissement'] = $etablissement;

// Configurer le document
$pdf->SetCreator('eGestion');
$pdf->SetAuthor($etablissement['nom'] ?? 'eGestion');
$pdf->SetTitle('Liste des Recours - ' . $annee['designation']);
$pdf->SetSubject('Recours académiques');
$pdf->SetKeywords('Recours, Liste, PDF');

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
$pdf->Ln(5);
$pdf->Cell(0, 10, 'LISTE DES RECOURS', 0, 1, 'C', 1);

// Informations sur les filtres appliqués
$pdf->SetTextColor(80, 80, 80);
$pdf->SetFont('helvetica', 'B', 10);
$pdf->Ln(5);
$pdf->Cell(60, 6, 'Année académique:', 0, 0, 'L');
$pdf->SetFont('helvetica', '', 10);
$pdf->Cell(80, 6, $annee['designation'], 0, 0, 'L');

$pdf->SetFont('helvetica', 'B', 10);
$pdf->Cell(40, 6, 'Période:', 0, 0, 'L');
$pdf->SetFont('helvetica', '', 10);
$pdf->Cell(0, 6, 'Du ' . date('d/m/Y', strtotime($date_debut)) . ' au ' . date('d/m/Y', strtotime($date_fin)), 0, 1, 'L');

$pdf->SetFont('helvetica', 'B', 10);
$pdf->Cell(60, 6, 'Statut de paiement:', 0, 0, 'L');
$pdf->SetFont('helvetica', '', 10);
$statut_paiement_text = ($statut_paiement === 'tous') ? 'Tous' : (($statut_paiement == '1') ? 'Payés' : 'Non payés');
$pdf->Cell(80, 6, $statut_paiement_text, 0, 0, 'L');

$pdf->SetFont('helvetica', 'B', 10);
$pdf->Cell(40, 6, 'Statut du recours:', 0, 0, 'L');
$pdf->SetFont('helvetica', '', 10);
$statut_recours_text = ($statut_recours === 'tous') ? 'Tous' : $statut_recours;
$pdf->Cell(0, 6, $statut_recours_text, 0, 1, 'L');

if ($id_session > 0) {
    // Récupérer le nom de la session
    $query_session = "SELECT designSession, description FROM session WHERE idsession = :id_session";
    $stmt_session = $conn->prepare($query_session);
    $stmt_session->bindParam(':id_session', $id_session);
    $stmt_session->execute();
    $session = $stmt_session->fetch(PDO::FETCH_ASSOC);
    
    if ($session) {
        $pdf->SetFont('helvetica', 'B', 10);
        $pdf->Cell(60, 6, 'Session:', 0, 0, 'L');
        $pdf->SetFont('helvetica', '', 10);
        $pdf->Cell(0, 6, $session['designSession'] . ' - ' . $session['description'], 0, 1, 'L');
    }
}

$pdf->Ln(5);

// Si aucun recours trouvé
if (count($recours) == 0) {
    $pdf->SetTextColor(50, 50, 50);
    $pdf->SetFont('helvetica', 'I', 12);
    $pdf->Cell(0, 10, 'Aucun recours trouvé pour les critères sélectionnés.', 0, 1, 'C');
} else {
    // En-têtes du tableau
    $pdf->SetFillColor(245, 245, 245);
    $pdf->SetTextColor(50, 50, 50);
    $pdf->SetFont('helvetica', 'B', 9);
    
    $pdf->Cell(10, 8, 'N°', 1, 0, 'C', 1);
    $pdf->Cell(25, 8, 'Matricule', 1, 0, 'C', 1);
    $pdf->Cell(55, 8, 'Étudiant', 1, 0, 'C', 1);
    $pdf->Cell(105, 8, 'ECUE', 1, 0, 'C', 1);  // Augmenté de 65 à 95
    $pdf->Cell(25, 8, 'Date dépôt', 1, 0, 'C', 1);
    $pdf->Cell(25, 8, 'Statut', 1, 0, 'C', 1);
    $pdf->Cell(20, 8, 'Payé', 1, 0, 'C', 1);
    $pdf->Ln();
    
    // Données du tableau
    $pdf->SetFont('helvetica', '', 8);
    $i = 1;
    
    foreach ($recours as $r) {
        // Alternance de couleur pour les lignes
        $fill = ($i % 2 == 0) ? true : false;
        
        $pdf->Cell(10, 7, $i, 1, 0, 'C', $fill);
        $pdf->Cell(25, 7, $r['matricule'], 1, 0, 'C', $fill);
        $pdf->Cell(55, 7, $r['nom_etudiant'], 1, 0, 'L', $fill);
        $pdf->Cell(105, 7, $r['designationECUE'], 1, 0, 'L', $fill);  // Augmenté de 65 à 95
        $pdf->Cell(25, 7, date('d/m/Y', strtotime($r['date_creation'])), 1, 0, 'C', $fill);
        
        // Statut avec couleur
        $pdf->SetTextColor(50, 50, 50);
        if ($r['statut'] == 'Approuvé') $pdf->SetTextColor(0, 128, 0);
        if ($r['statut'] == 'Rejeté') $pdf->SetTextColor(200, 0, 0);
        if ($r['statut'] == 'En traitement') $pdf->SetTextColor(0, 0, 200);
        $pdf->Cell(25, 7, $r['statut'], 1, 0, 'C', $fill);
        $pdf->SetTextColor(50, 50, 50);
        
        // Payé ou non
        $paye = ($r['est_paye'] == 1) ? 'Oui' : 'Non';
        $pdf->Cell(20, 7, $paye, 1, 0, 'C', $fill);
        $pdf->Ln();
        $i++;
        
        // Vérifier si on a besoin d'une nouvelle page
        if ($pdf->GetY() > 250) {
            $pdf->AddPage('L');
            
            // Répéter l'en-tête du tableau
            $pdf->SetFillColor(245, 245, 245);
            $pdf->SetTextColor(50, 50, 50);
            $pdf->SetFont('helvetica', 'B', 9);
            
            $pdf->Cell(10, 8, 'N°', 1, 0, 'C', 1);
            $pdf->Cell(25, 8, 'Matricule', 1, 0, 'C', 1);
            $pdf->Cell(55, 8, 'Étudiant', 1, 0, 'C', 1);
            $pdf->Cell(105, 8, 'ECUE', 1, 0, 'C', 1);  // Augmenté de 65 à 95
            $pdf->Cell(25, 8, 'Date dépôt', 1, 0, 'C', 1);
            $pdf->Cell(25, 8, 'Statut', 1, 0, 'C', 1);
            $pdf->Cell(20, 8, 'Payé', 1, 0, 'C', 1);
            $pdf->Ln(5);
            
            $pdf->SetFont('helvetica', '', 8);
        }
    }
    
    // Statistiques
    $pdf->AddPage('L');
    $pdf->SetFont('helvetica', 'B', 14);
    $pdf->SetTextColor($primaryColor[0], $primaryColor[1], $primaryColor[2]);
    $pdf->Cell(0, 10, 'STATISTIQUES DES RECOURS', 0, 1, 'C');
    $pdf->Ln(5);
    
    // Calculer les statistiques
    $total_recours = count($recours);
    $recours_payes = 0;
    $recours_non_payes = 0;
    $recours_en_attente = 0;
    $recours_en_traitement = 0;
    $recours_approuves = 0;
    $recours_rejetes = 0;
    
    foreach ($recours as $r) {
        if ($r['est_paye'] == 1) {
            $recours_payes++;
        } else {
            $recours_non_payes++;
        }
        
        switch ($r['statut']) {
            case 'En attente':
                $recours_en_attente++;
                break;
            case 'En traitement':
                $recours_en_traitement++;
                break;
            case 'Approuvé':
                $recours_approuves++;
                break;
            case 'Rejeté':
                $recours_rejetes++;
                break;
        }
    }
    
    // Afficher un tableau de statistiques
    $pdf->SetFillColor(245, 245, 245);
    $pdf->SetTextColor(50, 50, 50);
    $pdf->SetFont('helvetica', 'B', 10);
    
    $pdf->Cell(0, 8, 'Récapitulatif par statut de paiement', 0, 1, 'L');
    
    $pdf->Cell(100, 8, 'Indicateur', 1, 0, 'L', 1);
    $pdf->Cell(40, 8, 'Nombre', 1, 0, 'C', 1);
    $pdf->Cell(40, 8, 'Pourcentage', 1, 1, 'C', 1);
    
    $pdf->SetFont('helvetica', '', 10);
    $pdf->Cell(100, 8, 'Recours payés', 1, 0, 'L');
    $pdf->Cell(40, 8, $recours_payes, 1, 0, 'C');
    $pdf->Cell(40, 8, ($total_recours > 0 ? round(($recours_payes / $total_recours) * 100) : 0) . '%', 1, 1, 'C');
    
    $pdf->Cell(100, 8, 'Recours non payés', 1, 0, 'L');
    $pdf->Cell(40, 8, $recours_non_payes, 1, 0, 'C');
    $pdf->Cell(40, 8, ($total_recours > 0 ? round(($recours_non_payes / $total_recours) * 100) : 0) . '%', 1, 1, 'C');
    
    $pdf->Cell(100, 8, 'Total', 1, 0, 'L', 1);
    $pdf->Cell(40, 8, $total_recours, 1, 0, 'C', 1);
    $pdf->Cell(40, 8, '100%', 1, 1, 'C', 1);
    
    
    
    
}

// Signature et certificat
$pdf->Ln(15);
$pdf->SetFont('helvetica', 'I', 10);
$pdf->SetTextColor(80, 80, 80);
$pdf->Cell(0, 6, 'Document généré par: ' . ($_SESSION['nom'] ?? ''), 0, 1, 'L');
$pdf->Cell(0, 6, 'Date et heure: ' . date('d/m/Y H:i:s'), 0, 1, 'L');
$pdf->Cell(0, 6, 'Ce document est un rapport automatiquement généré par le système.', 0, 1, 'L');

// Générer le PDF
$filename = 'Liste_Recours_' . date('Ymd_His') . '.pdf';

// Sortie du PDF
$pdf->Output($filename, 'I'); // 'I' pour afficher dans le navigateur
exit;
?>
