<?php
session_start();
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/vendor/autoload.php'; // Pour TCPDF

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['id'])) {
    header('Location: ../login');
    exit();
}

// Initialiser la connexion
$db = Connexion::getInstance()->getPDO();

if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $idInventaire = intval($_GET['id']);
    
    try {
        // Récupérer les informations de l'inventaire
        $stmt = $db->prepare("SELECT i.*, d.libelle_depot,
                                    u1.\"nomUser\" as user_creation_nom,
                                    u2.\"nomUser\" as user_validation_nom
                             FROM inventaire i
                             JOIN depot d ON i.id_depot = d.id_depot
                             LEFT JOIN t_users u1 ON i.id_user_creation = u1.\"idUser\"
                             LEFT JOIN t_users u2 ON i.id_user_validation = u2.\"idUser\"
                             WHERE i.id_inventaire = :id_inventaire");
        $stmt->bindParam(':id_inventaire', $idInventaire, PDO::PARAM_INT);
        $stmt->execute();
        $inventaire = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$inventaire) {
            die("Inventaire non trouvé");
        }
        
        // Récupérer les détails de l'inventaire
        $stmt = $db->prepare("SELECT di.*, p.code_produit, p.libelle_produit, 
                                    l.numero_lot, l.date_peremption, u.libelle_unite
                             FROM detail_inventaire di
                             JOIN produit p ON di.id_produit = p.id_produit
                             JOIN lot_produit l ON di.id_lot = l.id_lot
                             LEFT JOIN unite_mesure u ON p.id_unite_stockage = u.id_unite_mesure
                             WHERE di.id_inventaire = :id_inventaire
                             ORDER BY p.libelle_produit");
        $stmt->bindParam(':id_inventaire', $idInventaire, PDO::PARAM_INT);
        $stmt->execute();
        $details = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Créer le PDF
        class MYPDF extends TCPDF {
            public function Footer() {
                // Position à 15mm du bas
                $this->SetY(-15);
                
                // Police et couleur
                $this->SetFont('helvetica', 'I', 8);
                $this->SetTextColor(128, 128, 128);
                
                // Date et numéro de page
                $this->Cell(0, 10, 'Document généré le ' . date('d/m/Y H:i') . ' - Page ' . $this->getAliasNumPage() . '/' . $this->getAliasNbPages(), 0, false, 'C');
            }
        }
        
        // Initialiser le PDF
        $pdf = new MYPDF('P', 'mm', 'A4', true, 'UTF-8', false);
        
        // Définir les méta-informations du document
        $pdf->SetCreator('eGestion');
        $pdf->SetAuthor($_SESSION['nomUser'] ?? 'Système');
        $pdf->SetTitle('Rapport d\'Inventaire - ' . $inventaire['numero_inventaire']);
        $pdf->SetSubject('Rapport d\'Inventaire');
        
        // Définir les marges
        $pdf->SetMargins(15, 15, 15);
        $pdf->SetHeaderMargin(5);
        $pdf->SetFooterMargin(10);
        
        // Désactiver l'en-tête par défaut
        $pdf->setPrintHeader(false);
        
        // Activer la pagination automatique
        $pdf->SetAutoPageBreak(true, 15);
        
        // Ajouter une page
        $pdf->AddPage();
        
        // Définir les couleurs
        $primaryColor = array(52, 73, 94); // Bleu foncé
        $secondaryColor = array(41, 128, 185); // Bleu clair
        $positiveColor = array(39, 174, 96); // Vert
        $negativeColor = array(231, 76, 60); // Rouge
        
        // Entête du document
        $pdf->SetFillColor($primaryColor[0], $primaryColor[1], $primaryColor[2]);
        $pdf->SetTextColor(255, 255, 255);
        $pdf->SetFont('helvetica', 'B', 16);
        $pdf->Cell(180, 12, 'RAPPORT D\'INVENTAIRE', 0, 1, 'C', 1);
        
        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetFont('helvetica', 'B', 12);
        $pdf->Cell(180, 10, 'N° ' . $inventaire['numero_inventaire'], 0, 1, 'C');
        
        // Informations de l'inventaire
        $pdf->SetFont('helvetica', 'B', 10);
        $pdf->SetFillColor($secondaryColor[0], $secondaryColor[1], $secondaryColor[2]);
        $pdf->SetTextColor(255, 255, 255);
        $pdf->Cell(180, 8, 'INFORMATIONS GÉNÉRALES', 0, 1, 'L', 1);
        
        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetFont('helvetica', '', 10);
        $pdf->SetFillColor(240, 240, 240);
        
        // Définir les styles de table
        $style = array('width' => 0.2, 'cap' => 'butt', 'join' => 'miter', 'dash' => 0, 'color' => array(80, 80, 80));
        
        // Création du tableau d'informations générales
        $pdf->Cell(60, 8, 'Date de l\'inventaire:', 1, 0, 'L', 1, '', 0, false, 'T', 'C');
        $pdf->Cell(120, 8, date('d/m/Y', strtotime($inventaire['date_inventaire'])), 1, 1, 'L', 0, '', 0, false, 'T', 'C');
        
        $pdf->Cell(60, 8, 'Dépôt:', 1, 0, 'L', 1, '', 0, false, 'T', 'C');
        $pdf->Cell(120, 8, $inventaire['libelle_depot'], 1, 1, 'L', 0, '', 0, false, 'T', 'C');
        
        $pdf->Cell(60, 8, 'État de l\'inventaire:', 1, 0, 'L', 1, '', 0, false, 'T', 'C');
        $pdf->Cell(120, 8, $inventaire['etat'], 1, 1, 'L', 0, '', 0, false, 'T', 'C');
        
        if (!empty($inventaire['observation'])) {
            $pdf->Cell(60, 8, 'Observations:', 1, 0, 'L', 1, '', 0, false, 'T', 'C');
            $pdf->Cell(120, 8, $inventaire['observation'], 1, 1, 'L', 0, '', 0, false, 'T', 'C');
        }
        
        // Espace
        $pdf->Ln(5);
        
        // Calculer les totaux
        $totalTheorique = 0;
        $totalPhysique = 0;
        $totalEcart = 0;
        $totalValeur = 0;
        
        foreach ($details as $detail) {
            $totalTheorique += $detail['stock_theorique'];
            $totalPhysique += $detail['stock_physique'];
            $totalEcart += $detail['ecart'];
            $totalValeur += abs($detail['ecart']) * $detail['prix_unitaire'];
        }
        
        // Résumé
        $pdf->SetFont('helvetica', 'B', 10);
        $pdf->SetFillColor($secondaryColor[0], $secondaryColor[1], $secondaryColor[2]);
        $pdf->SetTextColor(255, 255, 255);
        $pdf->Cell(180, 8, 'RÉSUMÉ DE L\'INVENTAIRE', 0, 1, 'L', 1);
        
        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetFont('helvetica', 'B', 10);
        $pdf->SetFillColor(240, 240, 240);
        
        // Tableau de résumé
        $pdf->Cell(60, 8, 'Stock théorique total:', 1, 0, 'L', 1);
        $pdf->Cell(120, 8, number_format($totalTheorique, 2, ',', ' ') . ' unités', 1, 1, 'R', 0);
        
        $pdf->Cell(60, 8, 'Stock physique total:', 1, 0, 'L', 1);
        $pdf->Cell(120, 8, number_format($totalPhysique, 2, ',', ' ') . ' unités', 1, 1, 'R', 0);
        
        $pdf->Cell(60, 8, 'Écart total:', 1, 0, 'L', 1);
        
        // Écart en couleur selon positif ou négatif
        if ($totalEcart > 0) {
            $pdf->SetTextColor($positiveColor[0], $positiveColor[1], $positiveColor[2]);
            $ecartText = '+' . number_format($totalEcart, 2, ',', ' ') . ' unités';
        } elseif ($totalEcart < 0) {
            $pdf->SetTextColor($negativeColor[0], $negativeColor[1], $negativeColor[2]);
            $ecartText = number_format($totalEcart, 2, ',', ' ') . ' unités';
        } else {
            $ecartText = '0 unités';
        }
        
        $pdf->Cell(120, 8, $ecartText, 1, 1, 'R', 0);
        $pdf->SetTextColor(0, 0, 0);
        
        $pdf->Cell(60, 8, 'Valeur de l\'écart:', 1, 0, 'L', 1);
        $pdf->Cell(120, 8, number_format($totalValeur, 2, ',', ' ') . ' $', 1, 1, 'R', 0);
        
        // Espace
        $pdf->Ln(5);
        
        // Liste des produits
        $pdf->SetFont('helvetica', 'B', 10);
        $pdf->SetFillColor($secondaryColor[0], $secondaryColor[1], $secondaryColor[2]);
        $pdf->SetTextColor(255, 255, 255);
        $pdf->Cell(180, 8, 'DÉTAILS DES ARTICLES', 0, 1, 'L', 1);
        
        // En-têtes du tableau des produits
        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetFillColor(220, 220, 220);
        $pdf->Cell(10, 8, 'N°', 1, 0, 'C', 1);
        $pdf->Cell(20, 8, 'Code', 1, 0, 'C', 1);
        $pdf->Cell(45, 8, 'Désignation', 1, 0, 'C', 1);
        $pdf->Cell(20, 8, 'Lot', 1, 0, 'C', 1);
        $pdf->Cell(20, 8, 'Théorique', 1, 0, 'C', 1);
        $pdf->Cell(20, 8, 'Physique', 1, 0, 'C', 1);
        $pdf->Cell(15, 8, 'Écart', 1, 0, 'C', 1);
        $pdf->Cell(15, 8, 'Unité', 1, 0, 'C', 1);
        $pdf->Cell(15, 8, 'PU', 1, 1, 'C', 1);
        
        // Données du tableau
        $pdf->SetFont('helvetica', '', 9);
        $pdf->SetFillColor(245, 245, 245);
        $fill = false;
        
        $i = 1;
        foreach ($details as $detail) {
            // Vérifier si on a besoin d'une nouvelle page
            if ($pdf->GetY() > 250) {
                $pdf->AddPage();
                
                // Réimprimer les en-têtes du tableau
                $pdf->SetFont('helvetica', 'B', 10);
                $pdf->SetFillColor($secondaryColor[0], $secondaryColor[1], $secondaryColor[2]);
                $pdf->SetTextColor(255, 255, 255);
                $pdf->Cell(180, 8, 'DÉTAILS DES ARTICLES (suite)', 0, 1, 'L', 1);
                
                $pdf->SetTextColor(0, 0, 0);
                $pdf->SetFillColor(220, 220, 220);
                $pdf->Cell(10, 8, 'N°', 1, 0, 'C', 1);
                $pdf->Cell(20, 8, 'Code', 1, 0, 'C', 1);
                $pdf->Cell(45, 8, 'Désignation', 1, 0, 'C', 1);
                $pdf->Cell(20, 8, 'Lot', 1, 0, 'C', 1);
                $pdf->Cell(20, 8, 'Théorique', 1, 0, 'C', 1);
                $pdf->Cell(20, 8, 'Physique', 1, 0, 'C', 1);
                $pdf->Cell(15, 8, 'Écart', 1, 0, 'C', 1);
                $pdf->Cell(15, 8, 'Unité', 1, 0, 'C', 1);
                $pdf->Cell(15, 8, 'PU', 1, 1, 'C', 1);
                
                $pdf->SetFont('helvetica', '', 9);
                $pdf->SetFillColor(245, 245, 245);
            }
            
            $pdf->Cell(10, 7, $i, 1, 0, 'C', $fill);
            $pdf->Cell(20, 7, $detail['code_produit'], 1, 0, 'L', $fill);
            $pdf->Cell(45, 7, $detail['libelle_produit'], 1, 0, 'L', $fill);
            $pdf->Cell(20, 7, $detail['numero_lot'], 1, 0, 'C', $fill);
            $pdf->Cell(20, 7, number_format($detail['stock_theorique'], 2, ',', ' '), 1, 0, 'R', $fill);
            $pdf->Cell(20, 7, number_format($detail['stock_physique'], 2, ',', ' '), 1, 0, 'R', $fill);
            
            // Écart en couleur selon positif ou négatif
            if ($detail['ecart'] > 0) {
                $pdf->SetTextColor($positiveColor[0], $positiveColor[1], $positiveColor[2]);
                $ecartText = '+' . number_format($detail['ecart'], 2, ',', ' ');
            } elseif ($detail['ecart'] < 0) {
                $pdf->SetTextColor($negativeColor[0], $negativeColor[1], $negativeColor[2]);
                $ecartText = number_format($detail['ecart'], 2, ',', ' ');
            } else {
                $ecartText = '0';
            }
            
            $pdf->Cell(15, 7, $ecartText, 1, 0, 'R', $fill);
            $pdf->SetTextColor(0, 0, 0);
            
            $pdf->Cell(15, 7, $detail['libelle_unite'], 1, 0, 'C', $fill);
            $pdf->Cell(15, 7, number_format($detail['prix_unitaire'], 2, ',', ' '), 1, 1, 'R', $fill);
            
            $fill = !$fill;
            $i++;
        }
        
        // Section signatures
        $pdf->Ln(15);
        $pdf->SetFont('helvetica', 'B', 10);
        
        // Créateur du document
        $pdf->Cell(85, 8, 'Établi par:', 0, 0, 'C');
        $pdf->Cell(10, 8, '', 0, 0);
        $pdf->Cell(85, 8, 'Approuvé par:', 0, 1, 'C');
        
        $pdf->SetFont('helvetica', '', 10);
        $pdf->Cell(85, 6, $inventaire['user_creation_nom'], 0, 0, 'C');
        $pdf->Cell(10, 6, '', 0, 0);
        
        if ($inventaire['etat'] == 'Validé') {
            $pdf->Cell(85, 6, $inventaire['user_validation_nom'], 0, 1, 'C');
            $pdf->Cell(85, 6, 'Le ' . date('d/m/Y', strtotime($inventaire['date_creation'])), 0, 0, 'C');
            $pdf->Cell(10, 6, '', 0, 0);
            $pdf->Cell(85, 6, 'Le ' . date('d/m/Y', strtotime($inventaire['date_validation'])), 0, 1, 'C');
        } else {
            $pdf->Cell(85, 6, '', 0, 1, 'C');
            $pdf->Cell(85, 6, 'Le ' . date('d/m/Y', strtotime($inventaire['date_creation'])), 0, 0, 'C');
            $pdf->Cell(10, 6, '', 0, 0);
            $pdf->Cell(85, 6, '', 0, 1, 'C');
        }
        
        // Espace pour signatures
        $pdf->Ln(15);
        $pdf->Cell(85, 6, '____________________', 0, 0, 'C');
        $pdf->Cell(10, 6, '', 0, 0);
        $pdf->Cell(85, 6, '____________________', 0, 1, 'C');
        
        // Si l'inventaire est validé, ajouter un filigrane "VALIDÉ"
        if ($inventaire['etat'] == 'Validé') {
            $pdf->SetAlpha(0.2);
            $pdf->SetFont('helvetica', 'B', 70);
            $pdf->SetTextColor(0, 150, 0);
            
            // Sauvegarder la position
            $x = $pdf->GetX();
            $y = $pdf->GetY();
            
            // Calculer les dimensions de la page
            $width = $pdf->getPageWidth();
            $height = $pdf->getPageHeight();
            
            // Positionner le filigrane en diagonale
            $pdf->SetXY(25, $height/2 - 20);
            $pdf->StartTransform();
            $pdf->Rotate(45, $width/2, $height/2);
            $pdf->Cell($width, 20, 'VALIDÉ', 0, 0, 'C');
            $pdf->StopTransform();
            
            // Restaurer la position et l'alpha
            $pdf->SetXY($x, $y);
            $pdf->SetAlpha(1);
        }
        
        // Générer le nom du fichier
        $filename = 'inventaire_' . $inventaire['numero_inventaire'] . '.pdf';
        
        // Générer le PDF
        $pdf->Output($filename, 'I');
        exit;
        
    } catch (Exception $e) {
        die("Erreur lors de la génération du rapport: " . $e->getMessage());
    }
} else {
    die("Identifiant d'inventaire invalide");
}
