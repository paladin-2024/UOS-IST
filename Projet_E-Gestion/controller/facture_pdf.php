<?php
session_start();
error_reporting(E_ALL); ini_set("display_errors", 1);
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/lib/tcpdf/tcpdf.php';

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['id'])) {
    header('Location: ../login');
    exit();
}

// Récupérer l'ID de la facture
$factureId = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($factureId <= 0) {
    echo "ID de facture invalide";
    exit;
}

// Récupérer les données de la facture
$db = Connexion::getInstance()->getPDO();

$query = "SELECT f.*, 
          four.code_fournisseur, four.nom_fournisseur, four.adresse, four.telephone, four.email, four.nif, four.rccm,
          u_creation.nomUser as user_creation, u_validation.nomUser as user_validation
          FROM facture_fournisseur f
          JOIN fournisseur four ON f.id_fournisseur = four.id_fournisseur
          JOIN t_users u_creation ON f.id_user_creation = u_creation.idUser
          LEFT JOIN t_users u_validation ON f.id_user_validation = u_validation.idUser
          WHERE f.id_facture = :id";

$stmt = $db->prepare($query);
$stmt->bindParam(':id', $factureId, PDO::PARAM_INT);
$stmt->execute();
$facture = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$facture) {
    echo "Facture non trouvée";
    exit;
}

// Récupérer les lignes de la facture
$queryLignes = "SELECT l.*, p.code_produit, p.libelle_produit
                FROM ligne_facture_fournisseur l
                JOIN produit p ON l.id_produit = p.id_produit
                WHERE l.id_facture = :id_facture";
$stmtLignes = $db->prepare($queryLignes);
$stmtLignes->bindParam(':id_facture', $factureId, PDO::PARAM_INT);
$stmtLignes->execute();
$lignes = $stmtLignes->fetchAll(PDO::FETCH_ASSOC);

// Récupérer les informations de la réception si liée
$reception = null;
if ($facture['id_reception']) {
    $queryReception = "SELECT numero_reception, date_reception FROM reception_fournisseur WHERE id_reception = :id";
    $stmtReception = $db->prepare($queryReception);
    $stmtReception->bindParam(':id', $facture['id_reception'], PDO::PARAM_INT);
    $stmtReception->execute();
    $reception = $stmtReception->fetch(PDO::FETCH_ASSOC);
}

// Classe PDF personnalisée
class MYPDF extends TCPDF {
    public function Header() {
        // Logo
        $image_file = dirname(__DIR__) . '/assets/img/logo.png';
        if (file_exists($image_file)) {
            $this->Image($image_file, 10, 10, 30, '', 'PNG', '', 'T', false, 300, '', false, false, 0, false, false, false);
        }
        
        // Titre
        $this->SetFont('helvetica', 'B', 18);
        $this->SetTextColor(0, 123, 255);
        $this->SetY(15);
        $this->Cell(0, 10, 'FACTURE FOURNISSEUR', 0, false, 'C', 0, '', 0, false, 'M', 'M');
    }

    public function Footer() {
        // Position à 15 mm du bas
        $this->SetY(-15);
        // Police
        $this->SetFont('helvetica', 'I', 8);
        // Numéro de page
        $this->Cell(0, 10, 'Page '.$this->getAliasNumPage().'/'.$this->getAliasNbPages(), 0, false, 'C', 0, '', 0, false, 'T', 'M');
    }
}

// Création du PDF
$pdf = new MYPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

// Informations du document
$pdf->SetCreator('Système de Gestion Commerciale');
$pdf->SetAuthor('Votre Entreprise');
$pdf->SetTitle('Facture Fournisseur N° ' . $facture['numero_facture']);
$pdf->SetSubject('Facture Fournisseur');
$pdf->SetKeywords('Facture, Fournisseur, Achat');

// Marges
$pdf->SetMargins(PDF_MARGIN_LEFT, PDF_MARGIN_TOP + 10, PDF_MARGIN_RIGHT);
$pdf->SetHeaderMargin(PDF_MARGIN_HEADER);
$pdf->SetFooterMargin(PDF_MARGIN_FOOTER);

// Sauts de page automatiques
$pdf->SetAutoPageBreak(TRUE, PDF_MARGIN_BOTTOM);

// Police par défaut
$pdf->SetFont('helvetica', '', 10);

// Couleurs
$primaryColor = array(0, 123, 255); // Bleu
$secondaryColor = array(108, 117, 125); // Gris

// Fonction pour générer une page
function generatePage($pdf, $facture, $lignes, $reception, $primaryColor, $secondaryColor) {
    // Ajouter une page
    $pdf->AddPage();
    
    // Informations de l'entreprise
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->SetTextColor(0, 0, 0);
    $pdf->Cell(0, 6, 'VOTRE ENTREPRISE', 0, 1, 'R');
    $pdf->SetFont('helvetica', '', 9);
    $pdf->Cell(0, 5, 'Adresse: 123 Rue Principale, Ville', 0, 1, 'R');
    $pdf->Cell(0, 5, 'Téléphone: +243 123 456 789', 0, 1, 'R');
    $pdf->Cell(0, 5, 'Email: contact@votreentreprise.com', 0, 1, 'R');
    $pdf->Cell(0, 5, 'NIF: 123456789', 0, 1, 'R');
    $pdf->Cell(0, 5, 'RCCM: CD/KIN/RCCM/123-456', 0, 1, 'R');
    
    $pdf->Ln(5);
    
    // Informations de la facture
    $pdf->SetFillColor($primaryColor[0], $primaryColor[1], $primaryColor[2]);
    $pdf->SetTextColor(255, 255, 255);
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->Cell(0, 10, 'FACTURE N° ' . $facture['numero_facture'], 0, 1, 'L', 1);
    
    $pdf->SetTextColor(0, 0, 0);
    $pdf->SetFont('helvetica', '', 10);
    
    // Tableau des informations de la facture
    $pdf->SetFillColor(240, 240, 240);
    $pdf->Cell(60, 7, 'Date de facture:', 1, 0, 'L', 1);
    $pdf->Cell(60, 7, date('d/m/Y', strtotime($facture['date_facture'])), 1, 1, 'L');
    
    if ($facture['reference_fournisseur']) {
        $pdf->Cell(60, 7, 'Référence fournisseur:', 1, 0, 'L', 1);
        $pdf->Cell(60, 7, $facture['reference_fournisseur'], 1, 1, 'L');
    }
    
    $pdf->Cell(60, 7, 'Date d\'échéance:', 1, 0, 'L', 1);
    $pdf->Cell(60, 7, date('d/m/Y', strtotime($facture['date_echeance'])), 1, 1, 'L');
    
    if ($reception) {
        $pdf->Cell(60, 7, 'Réception liée:', 1, 0, 'L', 1);
        $pdf->Cell(60, 7, $reception['numero_reception'] . ' du ' . date('d/m/Y', strtotime($reception['date_reception'])), 1, 1, 'L');
    }
    
    $pdf->Cell(60, 7, 'État:', 1, 0, 'L', 1);
    
    // Définir la couleur en fonction de l'état
    switch ($facture['etat']) {
        case 'En cours':
            $pdf->SetTextColor(255, 165, 0); // Orange
            break;
        case 'Validé':
            $pdf->SetTextColor(0, 128, 0); // Vert
            break;
        case 'Payé partiellement':
            $pdf->SetTextColor(0, 0, 255); // Bleu
            break;
        case 'Payé':
            $pdf->SetTextColor(0, 128, 0); // Vert
            break;
        case 'Annulé':
            $pdf->SetTextColor(255, 0, 0); // Rouge
            break;
        default:
            $pdf->SetTextColor(0, 0, 0); // Noir
    }
    
    $pdf->Cell(60, 7, $facture['etat'], 1, 1, 'L');
    $pdf->SetTextColor(0, 0, 0); // Réinitialiser la couleur
    
    $pdf->Ln(5);
    
    // Informations du fournisseur
    $pdf->SetFillColor($secondaryColor[0], $secondaryColor[1], $secondaryColor[2]);
    $pdf->SetTextColor(255, 255, 255);
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->Cell(0, 10, 'FOURNISSEUR', 0, 1, 'L', 1);
    
    $pdf->SetTextColor(0, 0, 0);
    $pdf->SetFont('helvetica', '', 10);
    
    // Tableau des informations du fournisseur
    $pdf->SetFillColor(240, 240, 240);
    $pdf->Cell(60, 7, 'Code:', 1, 0, 'L', 1);
    $pdf->Cell(60, 7, $facture['code_fournisseur'], 1, 1, 'L');
    
    $pdf->Cell(60, 7, 'Nom:', 1, 0, 'L', 1);
    $pdf->Cell(60, 7, $facture['nom_fournisseur'], 1, 1, 'L');
    
    if ($facture['adresse']) {
        $pdf->Cell(60, 7, 'Adresse:', 1, 0, 'L', 1);
        $pdf->Cell(60, 7, $facture['adresse'], 1, 1, 'L');
    }
    
    if ($facture['telephone']) {
        $pdf->Cell(60, 7, 'Téléphone:', 1, 0, 'L', 1);
        $pdf->Cell(60, 7, $facture['telephone'], 1, 1, 'L');
    }
    
    if ($facture['email']) {
        $pdf->Cell(60, 7, 'Email:', 1, 0, 'L', 1);
        $pdf->Cell(60, 7, $facture['email'], 1, 1, 'L');
    }
    
    if ($facture['nif']) {
        $pdf->Cell(60, 7, 'NIF:', 1, 0, 'L', 1);
        $pdf->Cell(60, 7, $facture['nif'], 1, 1, 'L');
    }
    
    if ($facture['rccm']) {
        $pdf->Cell(60, 7, 'RCCM:', 1, 0, 'L', 1);
        $pdf->Cell(60, 7, $facture['rccm'], 1, 1, 'L');
    }
    
    $pdf->Ln(5);
    
    // Détails des produits
    $pdf->SetFillColor($primaryColor[0], $primaryColor[1], $primaryColor[2]);
    $pdf->SetTextColor(255, 255, 255);
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->Cell(0, 10, 'DÉTAILS DES PRODUITS', 0, 1, 'L', 1);
    
    $pdf->SetTextColor(0, 0, 0);
    $pdf->SetFont('helvetica', 'B', 9);
    
    // En-têtes du tableau des produits
    $pdf->SetFillColor(240, 240, 240);
    $pdf->Cell(25, 7, 'Code', 1, 0, 'C', 1);
    $pdf->Cell(60, 7, 'Produit', 1, 0, 'C', 1);
    $pdf->Cell(25, 7, 'Quantité', 1, 0, 'C', 1);
    $pdf->Cell(30, 7, 'Prix unitaire', 1, 0, 'C', 1);
    $pdf->Cell(30, 7, 'Montant total', 1, 1, 'C', 1);
    
    $pdf->SetFont('helvetica', '', 9);
    
    // Lignes du tableau des produits
    $totalHT = 0;
    foreach ($lignes as $ligne) {
        $pdf->Cell(25, 7, $ligne['code_produit'], 1, 0, 'L');
        $pdf->Cell(60, 7, $ligne['designation'], 1, 0, 'L');
        $pdf->Cell(25, 7, number_format($ligne['quantite'], 2, ',', ' '), 1, 0, 'R');
        $pdf->Cell(30, 7, number_format($ligne['prix_unitaire'], 2, ',', ' ') . ' USD', 1, 0, 'R');
        $pdf->Cell(30, 7, number_format($ligne['montant_ttc'], 2, ',', ' ') . ' USD', 1, 1, 'R');
        $totalHT += $ligne['montant_ht'];
    }
    
    // Totaux
    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->Cell(140, 7, 'Total HT:', 1, 0, 'R', 1);
    $pdf->Cell(30, 7, number_format($facture['montant_ht'], 2, ',', ' ') . ' USD', 1, 1, 'R');
    
    $pdf->Cell(140, 7, 'TVA (' . number_format($facture['taux_tva'], 2, ',', ' ') . '%):', 1, 0, 'R', 1);
    $pdf->Cell(30, 7, number_format($facture['montant_tva'], 2, ',', ' ') . ' USD', 1, 1, 'R');
    
    $pdf->Cell(140, 7, 'Total TTC:', 1, 0, 'R', 1);
    $pdf->Cell(30, 7, number_format($facture['montant_ttc'], 2, ',', ' ') . ' USD', 1, 1, 'R');
    
    $pdf->Cell(140, 7, 'Montant payé:', 1, 0, 'R', 1);
    $pdf->Cell(30, 7, number_format($facture['montant_paye'], 2, ',', ' ') . ' USD', 1, 1, 'R');
    
    $pdf->Cell(140, 7, 'Solde à payer:', 1, 0, 'R', 1);
    $pdf->Cell(30, 7, number_format($facture['solde'], 2, ',', ' ') . ' USD', 1, 1, 'R');
    
    $pdf->Ln(5);
    
    // Observation
    if ($facture['observation']) {
        $pdf->SetFillColor($secondaryColor[0], $secondaryColor[1], $secondaryColor[2]);
        $pdf->SetTextColor(255, 255, 255);
        $pdf->SetFont('helvetica', 'B', 12);
        $pdf->Cell(0, 10, 'OBSERVATION', 0, 1, 'L', 1);
        
        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetFont('helvetica', '', 10);
        $pdf->MultiCell(0, 7, $facture['observation'], 1, 'L', 0, 1);
        
        $pdf->Ln(5);
    }
    
    // Informations de traçabilité
    $pdf->SetFillColor($secondaryColor[0], $secondaryColor[1], $secondaryColor[2]);
    $pdf->SetTextColor(255, 255, 255);
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->Cell(0, 10, 'INFORMATIONS DE TRAÇABILITÉ', 0, 1, 'L', 1);
    
    $pdf->SetTextColor(0, 0, 0);
    $pdf->SetFont('helvetica', '', 10);
    
    // Tableau des informations de traçabilité
    $pdf->SetFillColor(240, 240, 240);
    $pdf->Cell(60, 7, 'Créé par:', 1, 0, 'L', 1);
    $pdf->Cell(60, 7, $facture['user_creation'], 1, 1, 'L');
    
    $pdf->Cell(60, 7, 'Date de création:', 1, 0, 'L', 1);
    $pdf->Cell(60, 7, date('d/m/Y H:i', strtotime($facture['date_creation'])), 1, 1, 'L');
    
    if ($facture['id_user_validation']) {
        $pdf->Cell(60, 7, 'Validé par:', 1, 0, 'L', 1);
        $pdf->Cell(60, 7, $facture['user_validation'], 1, 1, 'L');
        
        $pdf->Cell(60, 7, 'Date de validation:', 1, 0, 'L', 1);
        $pdf->Cell(60, 7, date('d/m/Y H:i', strtotime($facture['date_validation'])), 1, 1, 'L');
    }
    
    // Pied de page avec signatures
    $pdf->Ln(20);
    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->Cell(85, 7, 'Signature du fournisseur', 0, 0, 'C');
    $pdf->Cell(85, 7, 'Signature et cachet', 0, 1, 'C');
    
    $pdf->Ln(15);
    $pdf->Cell(85, 7, '....................................', 0, 0, 'C');
    $pdf->Cell(85, 7, '....................................', 0, 1, 'C');
}

// Générer la page
generatePage($pdf, $facture, $lignes, $reception, $primaryColor, $secondaryColor);

// Sortie du PDF
$pdf->Output('Facture_' . $facture['numero_facture'] . '.pdf', 'I');
