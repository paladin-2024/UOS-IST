<?php
session_start();
require_once dirname(__DIR__, 2) . '/config/Connexion.php';
require_once dirname(__DIR__, 2) . '/library/fpdf/fpdf.php';

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['id'])) {
    header('Location: ../login');
    exit();
}

// Récupérer l'ID de la sortie
$id_sortie = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Initialiser la connexion
$db = Connexion::getInstance()->getPDO();

// Récupérer les informations de la sortie
$query = "SELECT s.*, d.libelle_depot, d.adresse as depot_adresse, 
          u1.nomUser as user_creation, u2.nomUser as user_validation
          FROM sortie_stock s
          LEFT JOIN depot d ON s.id_depot = d.id_depot
          LEFT JOIN t_users u1 ON s.id_user_creation = u1.idUser
          LEFT JOIN t_users u2 ON s.id_user_validation = u2.idUser
          WHERE s.id_sortie = :id_sortie";
$stmt = $db->prepare($query);
$stmt->bindParam(':id_sortie', $id_sortie, PDO::PARAM_INT);
$stmt->execute();
$sortie = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$sortie) {
    die("Sortie de stock non trouvée");
}

// Récupérer les détails de la sortie
$query = "SELECT d.*, p.code_produit, p.libelle_produit, u.unite_symbole
          FROM detail_sortie_stock d
          JOIN produit p ON d.id_produit = p.id_produit 
          LEFT JOIN unite_mesure u ON p.id_unite_stockage = u.id_unite
          WHERE d.id_sortie = :id_sortie";
$stmt = $db->prepare($query);
$stmt->bindParam(':id_sortie', $id_sortie, PDO::PARAM_INT);
$stmt->execute();
$details = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Récupérer les détails par lot
$query = "SELECT dl.*, l.numero_lot, l.date_peremption
          FROM detail_sortie_lot dl
          JOIN lot_produit l ON dl.id_lot = l.id_lot
          JOIN detail_sortie_stock d ON dl.id_detail_sortie = d.id_detail_sortie
          WHERE d.id_sortie = :id_sortie";
$stmt = $db->prepare($query);
$stmt->bindParam(':id_sortie', $id_sortie, PDO::PARAM_INT);
$stmt->execute();
$lotDetails = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Organiser les détails de lot par id_detail_sortie
$sortedLotDetails = [];
foreach ($lotDetails as $lot) {
    $sortedLotDetails[$lot['id_detail_sortie']][] = $lot;
}

// Créer le PDF
class PDF extends FPDF {
    function Header() {
        // Logo
        $this->Image(dirname(__DIR__, 2) . '/assets/img/logo.png', 10, 10, 30);
        // Titre
        $this->SetFont('Arial', 'B', 15);
        $this->Cell(0, 10, 'BON DE SORTIE DE STOCK', 0, 1, 'C');
        $this->Ln(5);
    }

    function Footer() {
        $this->SetY(-15);
        $this->SetFont('Arial', 'I', 8);
        $this->Cell(0, 10, 'Page ' . $this->PageNo(), 0, 0, 'C');
    }
}

$pdf = new PDF();
$pdf->AddPage();

// Informations sur la sortie
$pdf->SetFont('Arial', 'B', 12);
$pdf->Cell(0, 10, utf8_decode('Informations générales'), 0, 1);

$pdf->SetFont('Arial', '', 10);

// Tableau d'informations générales
$pdf->SetFillColor(240, 240, 240);
$pdf->Cell(60, 7, utf8_decode('Numéro de sortie:'), 1, 0, 'L', true);
$pdf->Cell(130, 7, utf8_decode($sortie['numero_sortie']), 1, 1);

$pdf->Cell(60, 7, 'Date de sortie:', 1, 0, 'L', true);
$pdf->Cell(130, 7, date('d/m/Y', strtotime($sortie['date_sortie'])), 1, 1);

$pdf->Cell(60, 7, utf8_decode('Dépôt:'), 1, 0, 'L', true);
$pdf->Cell(130, 7, utf8_decode($sortie['libelle_depot']), 1, 1);

$pdf->Cell(60, 7, 'Type de sortie:', 1, 0, 'L', true);
$pdf->Cell(130, 7, utf8_decode($sortie['type_sortie']), 1, 1);

if ($sortie['reference_document']) {
    $pdf->Cell(60, 7, utf8_decode('Référence document:'), 1, 0, 'L', true);
    $pdf->Cell(130, 7, utf8_decode($sortie['reference_document']), 1, 1);
}

$pdf->Cell(60, 7, utf8_decode('État:'), 1, 0, 'L', true);
$pdf->Cell(130, 7, utf8_decode($sortie['etat']), 1, 1);

$pdf->Ln(5);

// Si observation existe
if ($sortie['observation']) {
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->Cell(0, 7, 'Observation:', 0, 1);
    $pdf->SetFont('Arial', '', 10);
    $pdf->MultiCell(0, 7, utf8_decode($sortie['observation']), 0, 'L');
    $pdf->Ln(5);
}

// Détails des produits
$pdf->SetFont('Arial', 'B', 12);
$pdf->Cell(0, 10, utf8_decode('Détails des produits'), 0, 1);

$pdf->SetFont('Arial', 'B', 9);
$pdf->Cell(25, 7, 'Code', 1, 0, 'C', true);
$pdf->Cell(60, 7, 'Produit', 1, 0, 'C', true);
$pdf->Cell(25, 7, utf8_decode('Quantité'), 1, 0, 'C', true);
$pdf->Cell(25, 7, 'Prix unitaire', 1, 0, 'C', true);
$pdf->Cell(25, 7, 'Montant', 1, 0, 'C', true);
$pdf->Cell(30, 7, 'Lot', 1, 1, 'C', true);

$pdf->SetFont('Arial', '', 9);
$total_general = 0;

foreach ($details as $detail) {
    $hauteur_cellule = 7;
    
    // Calculer la hauteur nécessaire pour les lots
    if (isset($sortedLotDetails[$detail['id_detail_sortie']])) {
        $nb_lots = count($sortedLotDetails[$detail['id_detail_sortie']]);
        $hauteur_lots = $nb_lots * 7;
        $hauteur_cellule = max($hauteur_cellule, $hauteur_lots);
    }
    
    $total_general += $detail['montant_total'];
    
    $x_initial = $pdf->GetX();
    $y_initial = $pdf->GetY();
    
    // Première colonne (Code)
    $pdf->Cell(25, $hauteur_cellule, $detail['code_produit'], 1, 0);
    
    // Deuxième colonne (Produit)
    $pdf->Cell(60, $hauteur_cellule, utf8_decode($detail['libelle_produit']), 1, 0);
    
    // Troisième colonne (Quantité)
    $quantite_formatee = number_format($detail['quantite'], 2) . ' ' . ($detail['unite_symbole'] ?? '');
    $pdf->Cell(25, $hauteur_cellule, $quantite_formatee, 1, 0, 'R');
    
    // Quatrième colonne (Prix unitaire)
    $pdf->Cell(25, $hauteur_cellule, number_format($detail['prix_unitaire'], 2) . ' $', 1, 0, 'R');
    
    // Cinquième colonne (Montant)
    $pdf->Cell(25, $hauteur_cellule, number_format($detail['montant_total'], 2) . ' $', 1, 0, 'R');
    
    // Sixième colonne (Lots)
    $pdf->Cell(30, $hauteur_cellule, '', 1, 1);
    
    // Retour pour écrire les détails des lots
    if (isset($sortedLotDetails[$detail['id_detail_sortie']])) {
        $pdf->SetXY($x_initial + 165, $y_initial);
        
        foreach ($sortedLotDetails[$detail['id_detail_sortie']] as $lot) {
            $lot_text = $lot['numero_lot'];
            if ($lot['date_peremption']) {
                $date_exp = date('d/m/Y', strtotime($lot['date_peremption']));
                $lot_text .= " (Exp: $date_exp)";
            }
            $pdf->Cell(30, 7, utf8_decode($lot_text), 0, 2);
        }
    }
    
    // Position pour la prochaine ligne
    $pdf->SetXY($x_initial, $y_initial + $hauteur_cellule);
}

// Total général
$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell(135, 7, 'Total général:', 1, 0, 'R', true);
$pdf->Cell(55, 7, number_format($total_general, 2) . ' $', 1, 1, 'R', true);

$pdf->Ln(10);

// Signatures
$pdf->SetFont('Arial', '', 10);
$pdf->Cell(95, 7, utf8_decode('Préparé par: ' . $sortie['user_creation']), 0, 0, 'L');

if ($sortie['etat'] == 'Validé' && $sortie['id_user_validation']) {
    $pdf->Cell(95, 7, utf8_decode('Validé par: ' . $sortie['user_validation']), 0, 1, 'L');
} else {
    $pdf->Cell(95, 7, '', 0, 1);
}

$pdf->Ln(15);

// Lignes de signature
$pdf->Cell(95, 7, 'Signature: _____________________', 0, 0, 'L');
$pdf->Cell(95, 7, 'Signature: _____________________', 0, 1, 'L');

// Générer le PDF
$pdf->Output('Bon_Sortie_' . $sortie['numero_sortie'] . '.pdf', 'I');
