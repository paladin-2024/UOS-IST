<?php
session_start();
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/vendor/autoload.php'; // Pour TCPDF

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['id'])) {
    header('Location: ../index');
    exit();
}

// Initialiser la connexion
$db = Connexion::getInstance()->getPDO();

if (isset($_GET['id']) && intval($_GET['id']) > 0) {
    $idInventaire = intval($_GET['id']);

    try {
        // Récupérer les informations de l'inventaire
        $queryInventaire = "SELECT i.*, d.libelle_depot, u.\"nomUser\" as user_creation,
                        v.\"nomUser\" as user_validation
                        FROM inventaire i
                        LEFT JOIN depot d ON i.id_depot = d.id_depot
                        LEFT JOIN t_users u ON i.id_user_creation = u.\"idUser\"
                        LEFT JOIN t_users v ON i.id_user_validation = v.\"idUser\"
                        WHERE i.id_inventaire = :id_inventaire";
        $stmtInventaire = $db->prepare($queryInventaire);
        $stmtInventaire->bindParam(':id_inventaire', $idInventaire, PDO::PARAM_INT);
        $stmtInventaire->execute();
        $inventaire = $stmtInventaire->fetch(PDO::FETCH_ASSOC);

        if (!$inventaire) {
            throw new Exception("Inventaire introuvable");
        }

        // Récupérer les détails des produits
        $queryDetails = "SELECT di.*, p.code_produit, p.libelle_produit, u.code_unite, u.symbole_unite,
                        l.numero_lot, l.date_peremption
                        FROM detail_inventaire di
                        LEFT JOIN produit p ON di.id_produit = p.id_produit
                        LEFT JOIN unite_mesure u ON p.id_unite_stockage = u.id_unite
                        LEFT JOIN lot_produit l ON di.id_lot = l.id_lot
                        WHERE di.id_inventaire = :id_inventaire
                        ORDER BY p.libelle_produit";
        $stmtDetails = $db->prepare($queryDetails);
        $stmtDetails->bindParam(':id_inventaire', $idInventaire, PDO::PARAM_INT);
        $stmtDetails->execute();
        $details = $stmtDetails->fetchAll(PDO::FETCH_ASSOC);

        // Récupérer les informations de l'institution
        $queryConfig = "SELECT * FROM configuration_universite LIMIT 1";
        $stmtConfig = $db->prepare($queryConfig);
        $stmtConfig->execute();
        $configInstitution = $stmtConfig->fetch(PDO::FETCH_ASSOC);

        // Calcul des totaux
        $totalTheorique = 0;
        $totalPhysique = 0;
        $totalEcartPositif = 0;
        $totalEcartNegatif = 0;

        foreach ($details as $detail) {
            $totalTheorique += $detail['stock_theorique'] * $detail['prix_unitaire'];
            $totalPhysique += $detail['stock_physique'] * $detail['prix_unitaire'];
            
            if ($detail['ecart'] > 0) {
                $totalEcartPositif += $detail['ecart'] * $detail['prix_unitaire'];
            } else {
                $totalEcartNegatif += abs($detail['ecart']) * $detail['prix_unitaire'];
            }
        }

        // Si la table config_universite n'existe pas, utiliser des valeurs par défaut
        if (!$configInstitution) {
            $configInstitution = [
                'nom' => 'E-GESTION',
                'sigle' => 'EG',
                'ministere_tutelle' => 'SYSTÈME DE GESTION',
                'adresse' => '',
                'telephone' => '',
                'email' => '',
                'site_web' => '',
                'logo' => 'assets/img/logo.png'
            ];
        }

        // Classe TCPDF personnalisée
        class MYPDF extends TCPDF
        {
            // Pied de page personnalisé
            public function Footer()
            {
                // Position à 15mm du bas
                $this->SetY(-15);

                // Ligne de séparation fine
                $this->SetLineStyle(array('width' => 0.2, 'cap' => 'butt', 'join' => 'miter', 'dash' => 0, 'color' => array(200, 200, 200)));
                $this->Line(15, $this->GetY(), $this->getPageWidth() - 15, $this->GetY());

                // Police et couleur
                $this->SetFont('helvetica', 'I', 8);
                $this->SetTextColor(100, 100, 100);

                // Date et signature électronique (centré sur sa propre ligne)
                $this->SetX(15);
                $this->Cell(($this->getPageWidth() - 30), 5, 'Document généré le ' . date('d/m/Y') . ' • Signature électronique sécurisée', 0, 1, 'C');

                // Nom de l'institution et site web (centré sur sa propre ligne)
                $configInstitution = $GLOBALS['configInstitution'] ?? array('nom' => 'eGestion', 'site_web' => '');
                $this->Cell(($this->getPageWidth() - 30), 5, ($configInstitution['nom'] ?? 'eGestion') . ' • Document officiel. ' . ($configInstitution['site_web'] ?? ''), 0, 1, 'C');
            }
        }

        // Créer l'instance de la classe personnalisée
        $pdf = new MYPDF('P', 'mm', 'A4', true, 'UTF-8', false);

        // Rendre la variable configInstitution accessible globalement pour le pied de page
        $GLOBALS['configInstitution'] = $configInstitution;

        // Configurer le document
        $pdf->SetCreator('eGestion');
        $pdf->SetAuthor($configInstitution['nom'] ?? 'eGestion');
        $pdf->SetTitle('Fiche d\'inventaire');
        $pdf->SetSubject('Fiche d\'inventaire');
        $pdf->SetKeywords('Stock, Inventaire, Fiche');

        // Supprimer l'en-tête par défaut
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(true);

        // Définir les marges
        $pdf->SetMargins(20, 20, 20);
        $pdf->SetAutoPageBreak(true, 25);

        // Couleurs pour le design
        $primaryColor = array(0, 87, 146); // Bleu foncé
        $secondaryColor = array(70, 130, 180); // Bleu acier
        $accentColor = array(0, 121, 194); // Bleu moyen

        function generatePage($pdf, $inventaire, $details, $configInstitution, $primaryColor, $secondaryColor, $accentColor, $totalTheorique, $totalPhysique, $totalEcartPositif, $totalEcartNegatif)
        {
            // Ajouter le logo en filigrane
            if (!empty($configInstitution['logo'])) {
                $logoPath = dirname(__DIR__) . '/' . $configInstitution['logo'];
                if (file_exists($logoPath)) {
                    // Sauvegarder l'état actuel
                    $pdf->setAlpha(0.1);

                    // Position au centre
                    $pageWidth = $pdf->getPageWidth();
                    $pageHeight = $pdf->getPageHeight();

                    // Définir une largeur plus petite
                    $logoWidth = 70;
                    $logoHeight = 100;

                    $x = ($pageWidth - $logoWidth) / 2;
                    $y = ($pageHeight - $logoHeight) / 2;

                    // Ajouter l'image en filigrane
                    $pdf->Image($logoPath, $x, $y, $logoWidth, $logoHeight, '', '', '', false, 300, '', false, false, 0);

                    // Restaurer l'état
                    $pdf->setAlpha(1);
                }
            }

            // En-tête avec les informations de l'institution
            if ($configInstitution) {
                // Logo de l'institution (visible)
                if (!empty($configInstitution['logo'])) {
                    $logoPath = dirname(__DIR__) . '/' . $configInstitution['logo'];
                    if (file_exists($logoPath)) {
                        $pdf->Image($logoPath, 20, 15, 20, 0, '', '', '', false, 200, '', false, false, 0);
                    }
                }

                // Titre et informations de l'institution
                $pdf->SetTextColor($primaryColor[0], $primaryColor[1], $primaryColor[2]);
                $pdf->SetFont('helvetica', 'B', 12);
                $pdf->SetXY(50, 15);
                $pdf->Cell(0, 8, strtoupper($configInstitution['ministere_tutelle'] ?? ''), 0, 1, 'C');

                $pdf->SetFont('helvetica', 'B', 14);
                $pdf->SetXY(50, 23);
                $pdf->Cell(0, 8, strtoupper($configInstitution['nom'] ?? ''), 0, 1, 'C');

                if (!empty($configInstitution['sigle'])) {
                    $pdf->SetFont('helvetica', 'B', 12);
                    $pdf->SetXY(50, 31);
                    $pdf->Cell(0, 6, $configInstitution['sigle'], 0, 1, 'C');
                }

                $pdf->SetFont('helvetica', '', 9);
                $pdf->SetTextColor(80, 80, 80);
                if (!empty($configInstitution['adresse'])) {
                    $pdf->SetXY(50, 37);
                    $pdf->Cell(0, 4, $configInstitution['adresse'], 0, 1, 'C');
                }

                $contactInfo = '';
                if (!empty($configInstitution['telephone'])) {
                    $contactInfo .= 'Tél: ' . $configInstitution['telephone'] . ' ';
                }
                if (!empty($configInstitution['email'])) {
                    $contactInfo .= 'Email: ' . $configInstitution['email'] . ' ';
                }
                if (!empty($configInstitution['site_web'])) {
                    $contactInfo .= 'Web: ' . $configInstitution['site_web'];
                }

                if (!empty($contactInfo)) {
                    $pdf->SetXY(50, 41);
                    $pdf->Cell(0, 4, $contactInfo, 0, 1, 'C');
                }

                // Ligne de séparation
                $pdf->SetLineStyle(array('width' => 0.5, 'cap' => 'butt', 'join' => 'miter', 'dash' => 0, 'color' => $accentColor));
                $pdf->Line(15, 48, $pdf->getPageWidth() - 15, 48);
            }

            // Ajouter "Service des stocks" en police calligraphique à gauche
            $pdf->SetFont('times', 'I', 12);
            $pdf->SetTextColor(100, 100, 100);
            $pdf->SetXY(15, 50);
            $pdf->Cell(100, 6, 'Service des stocks et approvisionnements', 0, 1, 'L');

            // Réinitialiser la couleur du texte pour la suite
            $pdf->SetTextColor(80, 80, 80);

            // Titre du document avec fond coloré
            $pdf->SetFillColor($primaryColor[0], $primaryColor[1], $primaryColor[2]);
            $pdf->SetTextColor(255, 255, 255);
            $pdf->SetFont('helvetica', 'B', 16);
            $pdf->Ln(5);
            $pdf->Cell(0, 10, 'FICHE D\'INVENTAIRE', 0, 1, 'C', 1);

            $pdf->SetTextColor(80, 80, 80);
            $pdf->SetFont('helvetica', 'B', 10);
            $pdf->Cell(0, 8, 'N° ' . $inventaire['numero_inventaire'], 0, 1, 'C');

            // Préparer les données du QR Code
            $qrCodeData = "FICHE D'INVENTAIRE\n";
            $qrCodeData .= "N°: " . $inventaire['numero_inventaire'] . "\n";
            $qrCodeData .= "Date: " . date('d/m/Y', strtotime($inventaire['date_inventaire'])) . "\n";
            $qrCodeData .= "Dépôt: " . $inventaire['libelle_depot'] . "\n";
            $qrCodeData .= "État: " . $inventaire['etat'] . "\n";
            $qrCodeData .= "Document généré le: " . date('d/m/Y H:i:s') . "\n";
            $qrCodeData .= $configInstitution['site_web'] ?? '';

            $pdf->Ln(-5);
            
            // Informations de l'inventaire en 2 colonnes compactes
            $pdf->SetTextColor($secondaryColor[0], $secondaryColor[1], $secondaryColor[2]);
            $pdf->SetFont('helvetica', 'B', 12);
            $pdf->Cell(0, 8, 'INFORMATIONS DE L\'INVENTAIRE', 0, 1, 'L');

            // Ligne décorative sous le titre de section
            $pdf->SetLineStyle(array('width' => 0.2, 'cap' => 'butt', 'join' => 'miter', 'dash' => 0, 'color' => $secondaryColor));
            $pdf->Line(20, $pdf->GetY(), 100, $pdf->GetY());
            $pdf->Ln(1);

                        // Format plus compact - colonne gauche
                        $pdf->SetTextColor(60, 60, 60);
                        $pdf->SetFont('helvetica', 'B', 9);
                        $pdf->Cell(30, 5, 'N° Inventaire:', 0, 0, 'L');
                        $pdf->SetFont('helvetica', '', 9);
                        $pdf->Cell(65, 5, $inventaire['numero_inventaire'], 0, 0, 'L');
                        
                        // Colonne droite
                        $pdf->SetFont('helvetica', 'B', 9);
                        $pdf->Cell(30, 5, 'Date inventaire:', 0, 0, 'L');
                        $pdf->SetFont('helvetica', '', 9);
                        $pdf->Cell(0, 5, date('d/m/Y', strtotime($inventaire['date_inventaire'])), 0, 1, 'L');
            
                        // Deuxième ligne
                        $pdf->SetFont('helvetica', 'B', 9);
                        $pdf->Cell(30, 5, 'Dépôt:', 0, 0, 'L');
                        $pdf->SetFont('helvetica', '', 9);
                        $pdf->Cell(65, 5, $inventaire['libelle_depot'], 0, 0, 'L');
                        
                        $pdf->SetFont('helvetica', 'B', 9);
                        $pdf->Cell(30, 5, 'État:', 0, 0, 'L');
                        $pdf->SetFont('helvetica', '', 9);
                        $pdf->Cell(0, 5, $inventaire['etat'], 0, 1, 'L');
            
                        // Troisième ligne
                        $pdf->SetFont('helvetica', 'B', 9);
                        $pdf->Cell(30, 5, 'Créé par:', 0, 0, 'L');
                        $pdf->SetFont('helvetica', '', 9);
                        $pdf->Cell(65, 5, $inventaire['user_creation'], 0, 0, 'L');
                        
                        // Afficher la date de validation si l'inventaire est validé
                        if ($inventaire['etat'] == 'Validé' && !empty($inventaire['user_validation'])) {
                            $pdf->SetFont('helvetica', 'B', 9);
                            $pdf->Cell(30, 5, 'Validé par:', 0, 0, 'L');
                            $pdf->SetFont('helvetica', '', 9);
                            $pdf->Cell(0, 5, $inventaire['user_validation'], 0, 1, 'L');
                            
                            // Quatrième ligne - Date de validation
                            $pdf->SetFont('helvetica', 'B', 9);
                            $pdf->Cell(30, 5, 'Date création:', 0, 0, 'L');
                            $pdf->SetFont('helvetica', '', 9);
                            $pdf->Cell(65, 5, date('d/m/Y', strtotime($inventaire['date_creation'])), 0, 0, 'L');
                            
                            $pdf->SetFont('helvetica', 'B', 9);
                            $pdf->Cell(30, 5, 'Date validation:', 0, 0, 'L');
                            $pdf->SetFont('helvetica', '', 9);
                            $pdf->Cell(0, 5, date('d/m/Y', strtotime($inventaire['date_validation'])), 0, 1, 'L');
                        } else {
                            // Si non validé, afficher seulement la date de création sur une ligne complète
                            $pdf->SetFont('helvetica', 'B', 9);
                            $pdf->Cell(30, 5, 'Date création:', 0, 0, 'L');
                            $pdf->SetFont('helvetica', '', 9);
                            $pdf->Cell(0, 5, date('d/m/Y', strtotime($inventaire['date_creation'])), 0, 1, 'L');
                        }
            
                        // Observation si existante
                        if (!empty($inventaire['observation'])) {
                            $pdf->SetFont('helvetica', 'B', 9);
                            $pdf->Cell(30, 5, 'Observation:', 0, 0, 'L');
                            $pdf->SetFont('helvetica', '', 9);
                            $pdf->MultiCell(0, 5, $inventaire['observation'], 0, 'L');
                        }
            
                        $pdf->Ln(2);
            
                        // Titre du tableau des produits inventoriés
                        $pdf->SetTextColor($secondaryColor[0], $secondaryColor[1], $secondaryColor[2]);
                        $pdf->SetFont('helvetica', 'B', 12);
                        $pdf->Cell(0, 10, 'DÉTAILS DES PRODUITS INVENTORIÉS', 0, 1, 'L');
            
                        // Ligne décorative sous le titre de section
                        $pdf->SetLineStyle(array('width' => 0.2, 'cap' => 'butt', 'join' => 'miter', 'dash' => 0, 'color' => $secondaryColor));
                        $pdf->Line(20, $pdf->GetY(), 100, $pdf->GetY());
                        $pdf->Ln(2);
            
                        // Remplacer le code existant du tableau des produits par ce qui suit
// Après cette ligne:
// $pdf->Ln(2);

// Tableau des produits
$pdf->SetTextColor(0, 0, 0);
$pdf->SetFillColor(240, 240, 240);
$pdf->SetFont('helvetica', 'B', 8);

// En-têtes du tableau
$pdf->Cell(8, 7, 'N°', 1, 0, 'C', 1);
$pdf->Cell(20, 7, 'Code', 1, 0, 'C', 1);
$pdf->Cell(55, 7, 'Désignation/Lot', 1, 0, 'C', 1);
$pdf->Cell(25, 7, 'Théorique', 1, 0, 'C', 1);
$pdf->Cell(25, 7, 'Physique', 1, 0, 'C', 1);
$pdf->Cell(20, 7, 'Écart', 1, 0, 'C', 1);
$pdf->Cell(20, 7, 'Prix Unit.', 1, 1, 'C', 1);

// Grouper les détails par produit
$detailsByProduct = [];
foreach ($details as $detail) {
    $productId = $detail['id_produit'];
    if (!isset($detailsByProduct[$productId])) {
        $detailsByProduct[$productId] = [
            'info' => $detail,
            'lots' => []
        ];
    }
    $detailsByProduct[$productId]['lots'][] = $detail;
}

// Lignes du tableau
$pdf->SetFont('helvetica', '', 8);
$index = 1;

// Variables pour le total général
$grandTotalTheoriqueQuantity = 0;
$grandTotalPhysiqueQuantity = 0;
$grandTotalTheoriqueValue = 0;
$grandTotalPhysiqueValue = 0;

foreach ($detailsByProduct as $productDetails) {
    $product = $productDetails['info'];
    $lots = $productDetails['lots'];
    
    // Ligne du produit (en gras avec fond gris clair)
    $pdf->SetFont('helvetica', 'B', 8);
    $pdf->SetFillColor(245, 245, 245);
    
    $pdf->Cell(8, 7, $index, 1, 0, 'C', 1);
    $pdf->Cell(20, 7, $product['code_produit'], 1, 0, 'L', 1);
    $pdf->Cell(55, 7, $product['libelle_produit'], 1, 0, 'L', 1);
    
    // Calculer les totaux pour ce produit
    $totalTheoriqueQuantity = 0;
    $totalPhysiqueQuantity = 0;
    $totalEcartQuantity = 0;
    $totalTheoriqueValue = 0;
    $totalPhysiqueValue = 0;
    
    foreach ($lots as $lot) {
        $totalTheoriqueQuantity += $lot['stock_theorique'];
        $totalPhysiqueQuantity += $lot['stock_physique'];
        $totalEcartQuantity += $lot['ecart'];
        
        // Calculer les valeurs en utilisant le prix unitaire spécifique à chaque lot
        $totalTheoriqueValue += $lot['stock_theorique'] * $lot['prix_unitaire'];
        $totalPhysiqueValue += $lot['stock_physique'] * $lot['prix_unitaire'];
    }
    
    // Ajouter au total général
    $grandTotalTheoriqueQuantity += $totalTheoriqueQuantity;
    $grandTotalPhysiqueQuantity += $totalPhysiqueQuantity;
    $grandTotalTheoriqueValue += $totalTheoriqueValue;
    $grandTotalPhysiqueValue += $totalPhysiqueValue;
    
    // Afficher les totaux du produit
    $pdf->Cell(25, 7, number_format($totalTheoriqueQuantity, 2) . ' ' . $product['symbole_unite'], 1, 0, 'R', 1);
    $pdf->Cell(25, 7, number_format($totalPhysiqueQuantity, 2) . ' ' . $product['symbole_unite'], 1, 0, 'R', 1);
    
    // Colorer l'écart selon sa valeur
    if ($totalEcartQuantity < 0) {
        $pdf->SetTextColor(255, 0, 0); // Rouge pour écart négatif
    } elseif ($totalEcartQuantity > 0) {
        $pdf->SetTextColor(0, 128, 0); // Vert pour écart positif
    }
    
    $pdf->Cell(20, 7, number_format($totalEcartQuantity, 2) . ' ' . $product['symbole_unite'], 1, 0, 'R', 1);
    $pdf->SetTextColor(0, 0, 0); // Restaurer la couleur
    
    // Afficher le prix moyen pondéré pour le produit
    $avgPrice = $totalTheoriqueQuantity > 0 ? $totalTheoriqueValue / $totalTheoriqueQuantity : 0;
    $pdf->Cell(20, 7, number_format($avgPrice, 2) . ' USD', 1, 1, 'R', 1);
    
    // Détails des lots (en police normale sans fond)
    $pdf->SetFont('helvetica', '', 8);
    $pdf->SetFillColor(255, 255, 255);
    
    foreach ($lots as $lot) {
        // Détails du lot avec indentation
        $pdf->Cell(8, 6, '', 1, 0, 'C', 0); // Colonne vide pour l'indentation
        $pdf->Cell(20, 6, '', 1, 0, 'L', 0); // Colonne vide pour l'indentation
        
        // Formater l'information du lot
        $lotInfo = '   Lot: ' . $lot['numero_lot'];
        if (!empty($lot['date_peremption'])) {
            $dateExp = date('d/m/Y', strtotime($lot['date_peremption']));
            $lotInfo .= ' (Exp: ' . $dateExp . ')';
        }
        
        $pdf->Cell(55, 6, $lotInfo, 1, 0, 'L', 0);
        $pdf->Cell(25, 6, number_format($lot['stock_theorique'], 2) . ' ' . $product['symbole_unite'], 1, 0, 'R', 0);
        $pdf->Cell(25, 6, number_format($lot['stock_physique'], 2) . ' ' . $product['symbole_unite'], 1, 0, 'R', 0);
        
        // Colorer l'écart selon sa valeur
        if ($lot['ecart'] < 0) {
            $pdf->SetTextColor(255, 0, 0); // Rouge pour écart négatif
        } elseif ($lot['ecart'] > 0) {
            $pdf->SetTextColor(0, 128, 0); // Vert pour écart positif
        }
        
        $pdf->Cell(20, 6, number_format($lot['ecart'], 2) . ' ' . $product['symbole_unite'], 1, 0, 'R', 0);
        $pdf->SetTextColor(0, 0, 0); // Restaurer la couleur
        
        // Afficher le prix unitaire du lot
        $pdf->Cell(20, 6, number_format($lot['prix_unitaire'], 2) . ' USD', 1, 1, 'R', 0);
    }
    
    $index++;
}

// Ligne de total général
$pdf->SetFont('helvetica', 'B', 8);
$pdf->SetFillColor(220, 220, 220);
$pdf->Cell(83, 7, 'VALEUR TOTALE', 1, 0, 'R', 1);
$pdf->Cell(25, 7, number_format($grandTotalTheoriqueValue, 2) . ' USD', 1, 0, 'R', 1);
$pdf->Cell(25, 7, number_format($grandTotalPhysiqueValue, 2) . ' USD', 1, 0, 'R', 1);

// Colorer l'écart total selon sa valeur
$grandTotalEcartValue = $grandTotalPhysiqueValue - $grandTotalTheoriqueValue;
if ($grandTotalEcartValue < 0) {
    $pdf->SetTextColor(255, 0, 0); // Rouge pour écart négatif
} elseif ($grandTotalEcartValue > 0) {
    $pdf->SetTextColor(0, 128, 0); // Vert pour écart positif
}

$pdf->Cell(40, 7, number_format($grandTotalEcartValue, 2) . ' USD', 1, 1, 'R', 1);
$pdf->SetTextColor(0, 0, 0); // Restaurer la couleur


            
                        $pdf->Ln(3);
            
                        // Tableau récapitulatif
                        $pdf->SetTextColor($secondaryColor[0], $secondaryColor[1], $secondaryColor[2]);
                        $pdf->SetFont('helvetica', 'B', 10);
                        $pdf->Cell(0, 7, 'RÉCAPITULATIF', 0, 1, 'L');
                        
                        $pdf->SetLineStyle(array('width' => 0.2, 'cap' => 'butt', 'join' => 'miter', 'dash' => 0, 'color' => $secondaryColor));
                        $pdf->Line(20, $pdf->GetY(), 70, $pdf->GetY());
                        $pdf->Ln(2);
            
                        $pdf->SetTextColor(0, 0, 0);
                        $pdf->SetFont('helvetica', 'B', 9);
                        $pdf->Cell(100, 6, 'Valeur du stock théorique:', 0, 0, 'L');
                        $pdf->SetFont('helvetica', '', 9);
                        $pdf->Cell(0, 6, number_format($totalTheorique, 2) . ' USD', 0, 1, 'L');
                        
                        $pdf->SetFont('helvetica', 'B', 9);
                        $pdf->Cell(100, 6, 'Valeur du stock physique:', 0, 0, 'L');
                        $pdf->SetFont('helvetica', '', 9);
                        $pdf->Cell(0, 6, number_format($totalPhysique, 2) . ' USD', 0, 1, 'L');
                        
                        $pdf->SetFont('helvetica', 'B', 9);
                        $pdf->Cell(100, 6, 'Valeur des excédents (écarts positifs):', 0, 0, 'L');
                        $pdf->SetTextColor(0, 128, 0); // Vert pour les excédents
                        $pdf->SetFont('helvetica', '', 9);
                        $pdf->Cell(0, 6, number_format($totalEcartPositif, 2) . ' USD', 0, 1, 'L');
                        
                        $pdf->SetTextColor(0, 0, 0);
                        $pdf->SetFont('helvetica', 'B', 9);
                        $pdf->Cell(100, 6, 'Valeur des déficits (écarts négatifs):', 0, 0, 'L');
                        $pdf->SetTextColor(255, 0, 0); // Rouge pour les déficits
                        $pdf->SetFont('helvetica', '', 9);
                        $pdf->Cell(0, 6, number_format($totalEcartNegatif, 2) . ' USD', 0, 1, 'L');
                        
                        $pdf->SetTextColor(0, 0, 0);
                        $pdf->SetFont('helvetica', 'B', 9);
                        $pdf->Cell(100, 6, 'Écart total (valeur):', 0, 0, 'L');
                        
                        // Couleur basée sur l'écart total
                        $ecartTotal = $totalEcartPositif - $totalEcartNegatif;
                        if ($ecartTotal < 0) {
                            $pdf->SetTextColor(255, 0, 0); // Rouge si négatif
                        } elseif ($ecartTotal > 0) {
                            $pdf->SetTextColor(0, 128, 0); // Vert si positif
                        }
                        
                        $pdf->SetFont('helvetica', '', 9);
                        $pdf->Cell(0, 6, number_format($ecartTotal, 2) . ' USD', 0, 1, 'L');
            
                        $pdf->Ln(5);
            
                        // QR code à gauche (taille réduite)
                        $pdf->SetFont('helvetica', 'I', 7);
                        $pdf->SetTextColor(0, 0, 0);
                        $pdf->SetXY(20, $pdf->GetY());
                        $pdf->Cell(25, 5, 'Code de vérification', 0, 0, 'C');
                        $pdf->write2DBarcode($qrCodeData, 'QRCODE,M', 20, $pdf->GetY() + 5, 25, 25);
            
                        // Tableau des signatures
                        $pdf->SetXY(70, $pdf->GetY());
                        $pdf->SetTextColor($secondaryColor[0], $secondaryColor[1], $secondaryColor[2]);
                        $pdf->SetFont('helvetica', 'B', 10);
                        $pdf->Cell(0, 7, 'SIGNATURES', 0, 1, 'L');
                        
                        $pdf->SetLineStyle(array('width' => 0.2, 'cap' => 'butt', 'join' => 'miter', 'dash' => 0, 'color' => $secondaryColor));
                        $pdf->Line(70, $pdf->GetY(), 120, $pdf->GetY());
                        $pdf->Ln(2);
            
                        $pdf->SetXY(70, $pdf->GetY());
                        $pdf->SetFillColor(245, 245, 245);
                        $pdf->SetTextColor(60, 60, 60);
                        $pdf->SetFont('helvetica', 'B', 9);
                        
                        // En-têtes du tableau (2 colonnes égales)
                        $colWidth = 60;
                        $pdf->Cell($colWidth, 6, 'Préparé par', 1, 0, 'C', 1);
                        $pdf->Cell($colWidth, 6, 'Validé par', 1, 1, 'C', 1);
                        
                        // Contenu du tableau - Ligne 1 (noms)
                        $pdf->SetXY(70, $pdf->GetY());
                        $pdf->SetFont('helvetica', '', 9);
                        $pdf->Cell($colWidth, 10, $inventaire['user_creation'] ?? 'N/A', 1, 0, 'C');
                        $pdf->Cell($colWidth, 10, $inventaire['user_validation'] ?? 'N/A', 1, 1, 'C');
                        
                        // Ligne 2 (dates)
                        $pdf->SetXY(70, $pdf->GetY());
                        $pdf->SetFont('helvetica', 'I', 8);
                        $pdf->Cell($colWidth, 5, 'Date: ' . date('d/m/Y', strtotime($inventaire['date_creation'])), 1, 0, 'C');
                        $pdf->Cell($colWidth, 5, 'Date: ' . ($inventaire['date_validation'] ? date('d/m/Y', strtotime($inventaire['date_validation'])) : 'N/A'), 1, 1, 'C');
                        
                        // Ajouter un saut de page si l'espace restant est insuffisant
                        if ($pdf->GetY() > 200) {
                            $pdf->AddPage();
                        }

                        // Tableau des participants à l'inventaire
                        $pdf->SetTextColor($secondaryColor[0], $secondaryColor[1], $secondaryColor[2]);
                        $pdf->SetFont('helvetica', 'B', 10);
                        $pdf->Cell(0, 7, 'ÉQUIPE D\'INVENTAIRE', 0, 1, 'L');

                        $pdf->SetLineStyle(array('width' => 0.2, 'cap' => 'butt', 'join' => 'miter', 'dash' => 0, 'color' => $secondaryColor));
                        $pdf->Line(20, $pdf->GetY(), 120, $pdf->GetY());
                        $pdf->Ln(2);

                        // En-têtes du tableau des participants
                        $pdf->SetFillColor(240, 240, 240);
                        $pdf->SetTextColor(60, 60, 60);
                        $pdf->SetFont('helvetica', 'B', 8);
                        $pdf->Cell(10, 7, 'N°', 1, 0, 'C', 1);
                        $pdf->Cell(60, 7, 'Nom et prénom', 1, 0, 'C', 1);
                        $pdf->Cell(50, 7, 'Fonction', 1, 0, 'C', 1);
                        $pdf->Cell(60, 7, 'Signature', 1, 1, 'C', 1);

                        // Lignes statiques pour les participants
                        $pdf->SetFont('helvetica', '', 8);
                        $pdf->SetFillColor(255, 255, 255);

                        // Créer 5 lignes pour les signatures
                        for ($i = 1; $i <= 5; $i++) {
                            $pdf->Cell(10, 12, $i, 1, 0, 'C');
                            $pdf->Cell(60, 12, '', 1, 0, 'L');
                            $pdf->Cell(50, 12, '', 1, 0, 'L');
                            $pdf->Cell(60, 12, '', 1, 1, 'C');
                        }

                        // Ajouter une note en bas
                        $pdf->SetFont('helvetica', 'I', 8);
                        $pdf->SetTextColor(100, 100, 100);
                        $pdf->Ln(2);
                        $pdf->Cell(0, 5, 'Veuillez compléter les noms et fonctions des membres de l\'équipe d\'inventaire et recueillir leurs signatures.', 0, 1, 'L');
                        
                    }
            
                    // Ajouter une page
                    $pdf->AddPage();
            
                    // Générer le contenu de la page
                    generatePage($pdf, $inventaire, $details, $configInstitution, $primaryColor, $secondaryColor, $accentColor, $totalTheorique, $totalPhysique, $totalEcartPositif, $totalEcartNegatif);
            
                    // Ajouter un duplicata si demandé
                    if(isset($_GET['format']) && $_GET['format'] == 'duplicata'){
                        $pdf->AddPage();
                        generatePage($pdf, $inventaire, $details, $configInstitution, $primaryColor, $secondaryColor, $accentColor, $totalTheorique, $totalPhysique, $totalEcartPositif, $totalEcartNegatif);
                    }
                    
                    // Outputting the PDF
                    $pdf->Output('Inventaire_' . $inventaire['numero_inventaire'] . '.pdf', 'I');
                } catch (Exception $e) {
                    die("Erreur: " . $e->getMessage());
                }
            } else {
                die("ID d'inventaire non spécifié");
            }
            