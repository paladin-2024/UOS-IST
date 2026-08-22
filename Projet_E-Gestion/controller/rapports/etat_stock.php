<?php
session_start();
require_once dirname(dirname(__DIR__)) . '/config/Connexion.php';
require_once dirname(dirname(__DIR__)) . '/vendor/autoload.php'; // Pour TCPDF

// Importer les classes requises
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Color;

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['id'])) {
    header('Location: ../../index');
    exit();
}

// Initialiser la connexion
$db = Connexion::getInstance()->getPDO();

// Récupérer les paramètres du rapport
$depot_id = isset($_POST['depot_id']) ? $_POST['depot_id'] : 'all';
$categorie_id = isset($_POST['categorie_id']) ? $_POST['categorie_id'] : 'all';
$sort_by = isset($_POST['sort_by']) ? $_POST['sort_by'] : 'code';
$zero_stock = isset($_POST['zero_stock']) ? (bool)$_POST['zero_stock'] : false;
$with_image = isset($_POST['with_image']) ? (bool)$_POST['with_image'] : false;
$format = isset($_POST['format']) ? $_POST['format'] : 'html';

try {
    // Construire la requête SQL pour obtenir l'état du stock
    // Modifiez la requête SQL en utilisant des paramètres supplémentaires
$sql = "
SELECT 
    p.id_produit,
    p.code_produit,
    p.libelle_produit,
    p.description,
    p.type_produit,
    p.famille,
    p.image_produit,
    p.est_peremption_suivi AS est_peremption,
    p.seuil_min,
    p.seuil_max,
    c.id_categorie,
    c.libelle_categorie,
    d.id_depot,
    d.libelle_depot,
    um.symbole_unite,
    um_vente.symbole_unite AS symbole_unite_vente,
    COALESCE(SUM(lp.quantite_disponible), 0) AS stock_actuel,
    COALESCE(AVG(lp.prix_unitaire_achat), 0) AS prix_unitaire_moyen,
    COALESCE(SUM(lp.quantite_disponible * lp.prix_unitaire_achat), 0) AS valeur_stock
FROM 
    produit p
LEFT JOIN 
    categorie_produit c ON p.id_categorie = c.id_categorie
LEFT JOIN 
    unite_mesure um ON p.id_unite_stockage = um.id_unite
LEFT JOIN 
    unite_mesure um_vente ON p.id_unite_vente = um_vente.id_unite
LEFT JOIN 
    detail_entree_stock des ON p.id_produit = des.id_produit
LEFT JOIN 
    entree_stock es ON des.id_entree = es.id_entree 
    AND es.etat = 'Validé'
LEFT JOIN 
    depot d ON es.id_depot = d.id_depot
LEFT JOIN 
    lot_produit lp ON des.id_detail_entree = lp.id_detail_entree 
    AND lp.quantite_disponible > 0
WHERE 
    p.actif = 1
    AND (d.id_depot = :depot_id OR :is_all_depot = 1)
    AND (p.id_categorie = :categorie_id OR :is_all_categorie = 1)
GROUP BY 
    p.id_produit, 
    d.id_depot
";

// Préparez les paramètres y compris les drapeaux pour "all"
$params = [
':depot_id' => $depot_id,
':categorie_id' => $categorie_id,
':is_all_depot' => ($depot_id == 'all' ? 1 : 0),
':is_all_categorie' => ($categorie_id == 'all' ? 1 : 0)
];

// Préparez et exécutez la requête
$stmt = $db->prepare($sql);
$stmt->execute($params);

    
    
    // Tri
    switch ($sort_by) {
        case 'code':
            $sql .= " ORDER BY p.code_produit, d.libelle_depot";
            break;
        case 'libelle':
            $sql .= " ORDER BY p.libelle_produit, d.libelle_depot";
            break;
        case 'categorie':
            $sql .= " ORDER BY cp.libelle_categorie, p.libelle_produit, d.libelle_depot";
            break;
        case 'quantite':
            $sql .= " ORDER BY stock_actuel DESC, p.libelle_produit, d.libelle_depot";
            break;
        case 'valeur':
            $sql .= " ORDER BY valeur_stock DESC, p.libelle_produit, d.libelle_depot";
            break;
        default:
            $sql .= " ORDER BY p.code_produit, d.libelle_depot";
    }
    
    // Préparation et exécution de la requête
    $stmt = $db->prepare($sql);
    
    if ($depot_id != 'all') {
        $stmt->bindParam(':depot_id', $depot_id);
    }
    
    if ($categorie_id != 'all') {
        $stmt->bindParam(':categorie_id', $categorie_id);
    }
    
    $stmt->execute();
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Regrouper les produits par catégorie et/ou dépôt selon le tri
    $productsGrouped = [];
    $totalStock = 0;
    $totalValeur = 0;
    
    foreach ($products as $product) {
        $categoryName = $product['libelle_categorie'];
        $productId = $product['id_produit'];
        $depotName = $product['libelle_depot'];
        
        if ($sort_by == 'categorie') {
            // Grouper d'abord par catégorie
            if (!isset($productsGrouped[$categoryName])) {
                $productsGrouped[$categoryName] = [];
            }
            
            if (!isset($productsGrouped[$categoryName][$productId])) {
                $productsGrouped[$categoryName][$productId] = [
                    'product_info' => $product,
                    'depots' => []
                ];
            }
            
            $productsGrouped[$categoryName][$productId]['depots'][$depotName] = $product;
        } else {
            // Grouper par produit uniquement
            if (!isset($productsGrouped[$productId])) {
                $productsGrouped[$productId] = [
                    'product_info' => $product,
                    'depots' => []
                ];
            }
            
            $productsGrouped[$productId]['depots'][$depotName] = $product;
        }
        
        // Cumuler les totaux
        $totalStock += $product['stock_actuel'];
        $totalValeur += $product['valeur_stock'] ?? 0;
    }

    // Récupérer les informations de l'institution
    $queryConfig = "SELECT * FROM configuration_universite LIMIT 1";
    $stmtConfig = $db->prepare($queryConfig);
    $stmtConfig->execute();
    $configInstitution = $stmtConfig->fetch(PDO::FETCH_ASSOC);

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

    // Informations du titre
    $infoTitre = [];
    
    // Titre pour le dépôt
    if ($depot_id != 'all') {
        $stmtDepot = $db->prepare("SELECT libelle_depot FROM depot WHERE id_depot = :id_depot");
        $stmtDepot->bindParam(':id_depot', $depot_id);
        $stmtDepot->execute();
        $depot = $stmtDepot->fetch(PDO::FETCH_ASSOC);
        if ($depot) {
            $infoTitre[] = "Dépôt: " . $depot['libelle_depot'];
        }
    } else {
        $infoTitre[] = "Tous les dépôts";
    }
    
    // Titre pour la catégorie
    if ($categorie_id != 'all') {
        $stmtCategorie = $db->prepare("SELECT libelle_categorie FROM categorie_produit WHERE id_categorie = :id_categorie");
        $stmtCategorie->bindParam(':id_categorie', $categorie_id);
        $stmtCategorie->execute();
        $categorie = $stmtCategorie->fetch(PDO::FETCH_ASSOC);
        if ($categorie) {
            $infoTitre[] = "Catégorie: " . $categorie['libelle_categorie'];
        }
    } else {
        $infoTitre[] = "Toutes les catégories";
    }
    
    // Inclure ou non les produits à stock zéro
    $infoTitre[] = $zero_stock ? "Inclut les produits sans stock" : "Produits avec stock uniquement";
    
    // Date du rapport
    $infoTitre[] = "État au " . date('d/m/Y');

    // AFFICHAGE HTML
    if ($format == 'html') {
        include dirname(dirname(__DIR__)) . '/views/rapports/html_etat_stock.php';
    }
    // FORMAT EXCEL
    elseif ($format == 'excel') {
        // Créer un nouveau document Spreadsheet
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        
        // Configuration de la page
        $sheet->getPageSetup()->setOrientation(PageSetup::ORIENTATION_LANDSCAPE);
        $sheet->getPageSetup()->setFitToWidth(1);
        $sheet->getPageSetup()->setFitToHeight(0);
        
        // Nom de l'onglet
        $sheet->setTitle('État du stock');
        
        // Informations de l'institution (en-tête)
        $row = 1;
        if ($configInstitution) {
            if (!empty($configInstitution['logo'])) {
                $logoPath = dirname(dirname(__DIR__)) . '/' . $configInstitution['logo'];
                if (file_exists($logoPath)) {
                    $drawing = new \PhpOffice\PhpSpreadsheet\Worksheet\Drawing();
                    $drawing->setName('Logo');
                    $drawing->setPath($logoPath);
                    $drawing->setCoordinates('A1');
                    $drawing->setHeight(50);
                    $drawing->setWorksheet($sheet);
                }
            }
            
            $sheet->setCellValue('B1', strtoupper($configInstitution['ministere_tutelle'] ?? ''));
            $sheet->setCellValue('B2', strtoupper($configInstitution['nom'] ?? ''));
            $sheet->setCellValue('B3', 'Tél: ' . ($configInstitution['telephone'] ?? '') . ' | Email: ' . ($configInstitution['email'] ?? ''));
            
            // Fusionner les cellules pour l'en-tête
            $sheet->mergeCells('B1:J1');
            $sheet->mergeCells('B2:J2');
            $sheet->mergeCells('B3:J3');
            
            // Style pour l'en-tête
            $sheet->getStyle('B1')->getFont()->setBold(true)->setSize(14);
            $sheet->getStyle('B2')->getFont()->setBold(true)->setSize(12);
            $sheet->getStyle('B1:B3')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            
            $row = 5;
        }
        
        // Titre du rapport
        $sheet->setCellValue('A' . $row, 'ÉTAT DU STOCK');
        $sheet->mergeCells('A' . $row . ':J' . $row);
        $sheet->getStyle('A' . $row)->getFont()->setBold(true)->setSize(14);
        $sheet->getStyle('A' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        
        // Sous-titre avec filtres
        $row++;
        $sheet->setCellValue('A' . $row, implode(' | ', $infoTitre));
        $sheet->mergeCells('A' . $row . ':J' . $row);
        $sheet->getStyle('A' . $row)->getFont()->setBold(true)->setSize(11);
        $sheet->getStyle('A' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        
        $row += 2;
        
        // En-têtes des colonnes
        $headers = ['Code', 'Désignation', 'Catégorie', 'Unité', 'Dépôt', 'Stock', 'Seuil Min', 'Seuil Max', 'Prix Unit. Moyen', 'Valeur Stock'];
        $columns = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J'];
        $colWidths = [15, 40, 20, 10, 20, 10, 10, 10, 15, 15];
        
        for ($i = 0; $i < count($headers); $i++) {
            $sheet->setCellValue($columns[$i] . $row, $headers[$i]);
            $sheet->getColumnDimension($columns[$i])->setWidth($colWidths[$i]);
        }
        
        $sheet->getStyle('A' . $row . ':J' . $row)->getFill()->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setARGB('FFD3D3D3'); // Gris clair
        $sheet->getStyle('A' . $row . ':J' . $row)->getFont()->setBold(true);
        $sheet->getStyle('A' . $row . ':J' . $row)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        $sheet->getStyle('A' . $row . ':J' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        
        $row++;
        
        // Données du stock
        if ($sort_by == 'categorie') {
            // Pour le tri par catégorie
            foreach ($productsGrouped as $categoryName => $products) {
                // En-tête de catégorie
                $sheet->mergeCells('A' . $row . ':J' . $row);
                $sheet->setCellValue('A' . $row, 'Catégorie: ' . $categoryName);
                $sheet->getStyle('A' . $row . ':J' . $row)->getFill()->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()->setARGB('FFE0E0E0'); // Gris très clair
                $sheet->getStyle('A' . $row)->getFont()->setBold(true);
                $sheet->getStyle('A' . $row . ':J' . $row)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
                $row++;
                
                // Produits de cette catégorie
                foreach ($products as $productData) {
                    $product = $productData['product_info'];
                    $depots = $productData['depots'];
                    
                    $firstRow = true;
                    foreach ($depots as $depotName => $depotData) {
                        // Vérifier s'il faut afficher les produits avec stock zéro
                        if (!$zero_stock && floatval($depotData['stock_actuel']) <= 0) {
                            continue;
                        }
                        
                        // Déterminer la couleur de ligne selon l'état du stock
                        $stockColor = '';
                        $stock = floatval($depotData['stock_actuel']);
                        $seuilMin = floatval($depotData['seuil_min']);
                        $seuilMax = floatval($depotData['seuil_max']);
                        
                        if ($stock <= 0) {
                            $stockColor = 'FFFFC7CE'; // Rouge clair pour rupture
                        } elseif ($stock < $seuilMin) {
                            $stockColor = 'FFFFEB9C'; // Jaune pour niveau bas
                        } elseif ($stock > $seuilMax && $seuilMax > 0) {
                            $stockColor = 'FFC6EFCE'; // Vert clair pour surstock
                        }
                        
                        if (!empty($stockColor)) {
                            $sheet->getStyle('A' . $row . ':J' . $row)->getFill()->setFillType(Fill::FILL_SOLID)
                                ->getStartColor()->setARGB($stockColor);
                        }
                        
                        if ($firstRow) {
                            $sheet->setCellValue('A' . $row, $product['code_produit']);
                            $sheet->setCellValue('B' . $row, $product['libelle_produit']);
                            $sheet->setCellValue('C' . $row, $product['libelle_categorie']);
                            $firstRow = false;
                        } else {
                            $sheet->setCellValue('A' . $row, '');
                            $sheet->setCellValue('B' . $row, '');
                            $sheet->setCellValue('C' . $row, '');
                        }
                        
                        $sheet->setCellValue('D' . $row, $depotData['symbole_unite']);
                        $sheet->setCellValue('E' . $row, $depotName);
                        $sheet->setCellValue('F' . $row, $depotData['stock_actuel']);
                        $sheet->setCellValue('G' . $row, $depotData['seuil_min']);
                        $sheet->setCellValue('H' . $row, $depotData['seuil_max']);
                        $sheet->setCellValue('I' . $row, number_format($depotData['prix_unitaire_moyen'] ?? 0, 2) . ' USD');
                        $sheet->setCellValue('J' . $row, number_format($depotData['valeur_stock'] ?? 0, 2) . ' USD');
                        
                        $sheet->getStyle('A' . $row . ':J' . $row)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
                        $sheet->getStyle('F' . $row . ':J' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                        
                        $row++;
                    }
                }
            }
        } else {
            // Pour les autres tris (par produit)
            foreach ($productsGrouped as $product) {
                $productInfo = $product['product_info'];
                $depots = $product['depots'];
                
                $firstRow = true;
                foreach ($depots as $depotName => $depotData) {
                    // Vérifier s'il faut afficher les produits avec stock zéro
                    if (!$zero_stock && floatval($depotData['stock_actuel']) <= 0) {
                        continue;
                    }
                    
                    // Déterminer la couleur de ligne selon l'état du stock
                    $stockColor = '';
                    $stock = floatval($depotData['stock_actuel']);
                    $seuilMin = floatval($depotData['seuil_min']);
                    $seuilMax = floatval($depotData['seuil_max']);
                    
                    if ($stock <= 0) {
                        $stockColor = 'FFFFC7CE'; // Rouge clair pour rupture
                    } elseif ($stock < $seuilMin) {
                        $stockColor = 'FFFFEB9C'; // Jaune pour niveau bas
                    } elseif ($stock > $seuilMax && $seuilMax > 0) {
                        $stockColor = 'FFC6EFCE'; // Vert clair pour surstock
                    }
                    
                    if (!empty($stockColor)) {
                        $sheet->getStyle('A' . $row . ':J' . $row)->getFill()->setFillType(Fill::FILL_SOLID)
                            ->getStartColor()->setARGB($stockColor);
                    }
                    
                    if ($firstRow) {
                        $sheet->setCellValue('A' . $row, $productInfo['code_produit']);
                        $sheet->setCellValue('B' . $row, $productInfo['libelle_produit']);
                        $sheet->setCellValue('C' . $row, $productInfo['libelle_categorie']);
                        $firstRow = false;
                    } else {
                        $sheet->setCellValue('A' . $row, '');
                        $sheet->setCellValue('B' . $row, '');
                        $sheet->setCellValue('C' . $row, '');
                    }
                    
                    $sheet->setCellValue('D' . $row, $depotData['symbole_unite']);
                    $sheet->setCellValue('E' . $row, $depotName);
                    $sheet->setCellValue('F' . $row, $depotData['stock_actuel']);
                    $sheet->setCellValue('G' . $row, $depotData['seuil_min']);
                    $sheet->setCellValue('H' . $row, $depotData['seuil_max']);
                    $sheet->setCellValue('I' . $row, number_format($depotData['prix_unitaire_moyen'] ?? 0, 2) . ' USD');
                    $sheet->setCellValue('J' . $row, number_format($depotData['valeur_stock'] ?? 0, 2) . ' USD');
                    
                    $sheet->getStyle('A' . $row . ':J' . $row)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
                    $sheet->getStyle('F' . $row . ':J' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                    
                    $row++;
                }
            }
        }
        
        // Total général
        $sheet->mergeCells('A' . $row . ':E' . $row);
        $sheet->setCellValue('A' . $row, 'TOTAL GÉNÉRAL');
        $sheet->setCellValue('F' . $row, $totalStock);
        $sheet->setCellValue('G' . $row, '');
        $sheet->setCellValue('H' . $row, '');
        $sheet->setCellValue('I' . $row, '');
        $sheet->setCellValue('J' . $row, number_format($totalValeur, 2) . ' USD');
        
        $sheet->getStyle('A' . $row . ':J' . $row)->getFill()->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setARGB('FFAAAAAA'); // Gris pour le total
        $sheet->getStyle('A' . $row . ':J' . $row)->getFont()->setBold(true);
        $sheet->getStyle('A' . $row . ':J' . $row)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        $sheet->getStyle('A' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        $sheet->getStyle('F' . $row . ':J' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        
        // Information de génération
        $row += 2;
        $sheet->mergeCells('A' . $row . ':J' . $row);
        $sheet->setCellValue('A' . $row, 'Rapport généré le ' . date('d/m/Y à H:i') . ' par ' . ($_SESSION['nom'] ?? 'Utilisateur système'));
        $sheet->getStyle('A' . $row)->getFont()->setItalic(true)->setSize(9);
        $sheet->getStyle('A' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
        
        // Créer l'objet Writer pour Excel
        $writer = new Xlsx($spreadsheet);
        
        // Définir les en-têtes pour le téléchargement
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="Etat_Stock_' . date('Y-m-d') . '.xlsx"');
        header('Cache-Control: max-age=0');
        
        // Sauvegarder le fichier Excel sur PHP output
        $writer->save('php://output');
        exit;
    }
    // FORMAT PDF
    else {
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
                        $pdf = new MYPDF('L', 'mm', 'A4', true, 'UTF-8', false);
                
                        // Rendre la variable configInstitution accessible globalement pour le pied de page
                        $GLOBALS['configInstitution'] = $configInstitution;
                
                        // Configurer le document
                        $pdf->SetCreator('eGestion');
                        $pdf->SetAuthor($configInstitution['nom'] ?? 'eGestion');
                        $pdf->SetTitle('État du stock');
                        $pdf->SetSubject('État du stock');
                        $pdf->SetKeywords('Stock, Inventaire, État');
                
                        // Supprimer l'en-tête par défaut
                        $pdf->setPrintHeader(false);
                        $pdf->setPrintFooter(true);
                
                        // Définir les marges
                        $pdf->SetMargins(10, 20, 10);
                        $pdf->SetAutoPageBreak(true, 25);
                
                        // Couleurs pour le design
                        $primaryColor = array(0, 87, 146); // Bleu foncé
                        $secondaryColor = array(70, 130, 180); // Bleu acier
                        $accentColor = array(0, 121, 194); // Bleu moyen
                
                        // Ajouter une page
                        $pdf->AddPage();
                
                        // Ajouter le logo en filigrane
                        if (!empty($configInstitution['logo'])) {
                            $logoPath = dirname(dirname(__DIR__)) . '/' . $configInstitution['logo'];
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
                                $logoPath = dirname(dirname(__DIR__)) . '/' . $configInstitution['logo'];
                                if (file_exists($logoPath)) {
                                    $pdf->Image($logoPath, 15, 15, 20, 0, '', '', '', false, 200, '', false, false, 0);
                                }
                            }
                
                            // Titre et informations de l'institution
                            $pdf->SetTextColor($primaryColor[0], $primaryColor[1], $primaryColor[2]);
                            $pdf->SetFont('helvetica', 'B', 12);
                            $pdf->SetXY(40, 15);
                            $pdf->Cell(0, 8, strtoupper($configInstitution['ministere_tutelle'] ?? ''), 0, 1, 'C');
                
                            $pdf->SetFont('helvetica', 'B', 14);
                            $pdf->SetXY(40, 23);
                            $pdf->Cell(0, 8, strtoupper($configInstitution['nom'] ?? ''), 0, 1, 'C');
                
                            if (!empty($configInstitution['sigle'])) {
                                $pdf->SetFont('helvetica', 'B', 12);
                                $pdf->SetXY(40, 31);
                                $pdf->Cell(0, 6, $configInstitution['sigle'], 0, 1, 'C');
                            }
                
                            $pdf->SetFont('helvetica', '', 9);
                            $pdf->SetTextColor(80, 80, 80);
                            if (!empty($configInstitution['adresse'])) {
                                $pdf->SetXY(40, 37);
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
                                $pdf->SetXY(40, 41);
                                $pdf->Cell(0, 4, $contactInfo, 0, 1, 'C');
                            }
                
                            // Ligne de séparation
                            $pdf->SetLineStyle(array('width' => 0.5, 'cap' => 'butt', 'join' => 'miter', 'dash' => 0, 'color' => $accentColor));
                            $pdf->Line(10, 48, $pdf->getPageWidth() - 10, 48);
                        }
                
                        // Ajouter "Service des stocks" en police calligraphique à gauche
                        $pdf->SetFont('times', 'I', 12);
                        $pdf->SetTextColor(100, 100, 100);
                        $pdf->SetXY(10, 50);
                        $pdf->Cell(100, 6, 'Service des stocks et approvisionnements', 0, 1, 'L');
                
                        // Titre du document avec fond coloré
                        $pdf->SetFillColor($primaryColor[0], $primaryColor[1], $primaryColor[2]);
                        $pdf->SetTextColor(255, 255, 255);
                        $pdf->SetFont('helvetica', 'B', 16);
                        $pdf->Ln(2);
                        $pdf->Cell(0, 10, 'ÉTAT DU STOCK', 0, 1, 'C', 1);
                
                        // Sous-titre avec les filtres
                        $pdf->SetTextColor(80, 80, 80);
                        $pdf->SetFont('helvetica', 'B', 10);
                        $pdf->Cell(0, 8, implode(' | ', $infoTitre), 0, 1, 'C');
                
                        $pdf->Ln(2);
                
                        // Paramètres pour le tableau
                        $colWidths = array(15, 65, 30, 15, 30, 20, 20, 20, 25, 30);
                        $headers = array('Code', 'Désignation', 'Catégorie', 'Unité', 'Dépôt', 'Stock', 'Seuil Min', 'Seuil Max', 'Prix Unit. Moyen', 'Valeur Stock');
                
                        // En-têtes des colonnes
                        $pdf->SetFont('helvetica', 'B', 8);
                        $pdf->SetFillColor(230, 230, 230);
                        $pdf->SetTextColor(0, 0, 0);
                
                        foreach ($colWidths as $i => $width) {
                            $pdf->Cell($width, 7, $headers[$i], 1, 0, 'C', 1);
                        }
                        $pdf->Ln();
                
                        // Affichage des données
                        $pdf->SetFont('helvetica', '', 8);
                        $pdf->SetTextColor(0, 0, 0);
                        
                        $currentCategory = '';
                        
                        if ($sort_by == 'categorie') {
                            // Si on trie par catégorie
                            foreach ($productsGrouped as $categoryName => $products) {
                                // En-tête de catégorie
                                $pdf->SetFont('helvetica', 'B', 9);
                                $pdf->SetFillColor(220, 220, 220);
                                $pdf->Cell(array_sum($colWidths), 7, 'Catégorie: ' . $categoryName, 1, 1, 'L', 1);
                                $pdf->SetFont('helvetica', '', 8);
                
                                // Pour chaque produit dans cette catégorie
                                foreach ($products as $productData) {
                                    $product = $productData['product_info'];
                                    $depots = $productData['depots'];
                                    
                                    $firstRow = true;
                                    foreach ($depots as $depotName => $depotData) {
                                        // Vérifier s'il faut afficher les produits avec stock zéro
                                        if (!$zero_stock && floatval($depotData['stock_actuel']) <= 0) {
                                            continue;
                                        }
                                        
                                        // Vérifier si on a besoin d'une nouvelle page
                                        if ($pdf->GetY() > ($pdf->getPageHeight() - 30)) {
                                            $pdf->AddPage();
                                            
                                            // Réimprimer les en-têtes de colonne
                                            $pdf->SetFont('helvetica', 'B', 8);
                                            $pdf->SetFillColor(230, 230, 230);
                                            
                                            foreach ($colWidths as $i => $width) {
                                                $pdf->Cell($width, 7, $headers[$i], 1, 0, 'C', 1);
                                            }
                                            $pdf->Ln();
                                            $pdf->SetFont('helvetica', '', 8);
                                        }
                                        
                                        // Déterminer la couleur de ligne selon l'état du stock
                                        $stockColor = array(255, 255, 255); // Blanc par défaut
                                        $stock = floatval($depotData['stock_actuel']);
                                        $seuilMin = floatval($depotData['seuil_min']);
                                        $seuilMax = floatval($depotData['seuil_max']);
                                        
                                        if ($stock <= 0) {
                                            $stockColor = array(255, 200, 200); // Rouge clair pour rupture
                                        } elseif ($stock < $seuilMin) {
                                            $stockColor = array(255, 235, 156); // Jaune pour niveau bas
                                        } elseif ($stock > $seuilMax && $seuilMax > 0) {
                                            $stockColor = array(198, 239, 206); // Vert clair pour surstock
                                        }
                                        
                                        $pdf->SetFillColor($stockColor[0], $stockColor[1], $stockColor[2]);
                                        
                                        if ($firstRow) {
                                            $pdf->Cell($colWidths[0], 6, $product['code_produit'], 1, 0, 'L', 1);
                                            $pdf->Cell($colWidths[1], 6, $product['libelle_produit'], 1, 0, 'L', 1);
                                            $pdf->Cell($colWidths[2], 6, $product['libelle_categorie'], 1, 0, 'L', 1);
                                            $firstRow = false;
                                        } else {
                                            $pdf->Cell($colWidths[0], 6, '', 1, 0, 'L', 1);
                                            $pdf->Cell($colWidths[1], 6, '', 1, 0, 'L', 1);
                                            $pdf->Cell($colWidths[2], 6, '', 1, 0, 'L', 1);
                                        }
                                        
                                        $pdf->Cell($colWidths[3], 6, $depotData['symbole_unite'], 1, 0, 'C', 1);
                                        $pdf->Cell($colWidths[4], 6, $depotName, 1, 0, 'L', 1);
                                        $pdf->Cell($colWidths[5], 6, number_format($depotData['stock_actuel'], 2), 1, 0, 'R', 1);
                                        $pdf->Cell($colWidths[6], 6, number_format($depotData['seuil_min'], 2), 1, 0, 'R', 1);
                                        $pdf->Cell($colWidths[7], 6, number_format($depotData['seuil_max'], 2), 1, 0, 'R', 1);
                                        $pdf->Cell($colWidths[8], 6, number_format($depotData['prix_unitaire_moyen'] ?? 0, 2) . ' USD', 1, 0, 'R', 1);
                                        $pdf->Cell($colWidths[9], 6, number_format($depotData['valeur_stock'] ?? 0, 2) . ' USD', 1, 1, 'R', 1);
                                    }
                                }
                            }
                        } else {
                            // Si on trie autrement (par produit)
                            foreach ($productsGrouped as $product) {
                                $productInfo = $product['product_info'];
                                $depots = $product['depots'];
                                
                                $firstRow = true;
                                foreach ($depots as $depotName => $depotData) {
                                    // Vérifier s'il faut afficher les produits avec stock zéro
                                    if (!$zero_stock && floatval($depotData['stock_actuel']) <= 0) {
                                        continue;
                                    }
                                    
                                    // Vérifier si on a besoin d'une nouvelle page
                                    if ($pdf->GetY() > ($pdf->getPageHeight() - 30)) {
                                        $pdf->AddPage();
                                        
                                        // Réimprimer les en-têtes de colonne
                                        $pdf->SetFont('helvetica', 'B', 8);
                                        $pdf->SetFillColor(230, 230, 230);
                                        
                                        foreach ($colWidths as $i => $width) {
                                            $pdf->Cell($width, 7, $headers[$i], 1, 0, 'C', 1);
                                        }
                                        $pdf->Ln();
                                        $pdf->SetFont('helvetica', '', 8);
                                    }
                                    
                                    // Déterminer la couleur de ligne selon l'état du stock
                                    $stockColor = array(255, 255, 255); // Blanc par défaut
                                    $stock = floatval($depotData['stock_actuel']);
                                    $seuilMin = floatval($depotData['seuil_min']);
                                    $seuilMax = floatval($depotData['seuil_max']);
                                    
                                    if ($stock <= 0) {
                                        $stockColor = array(255, 200, 200); // Rouge clair pour rupture
                                    } elseif ($stock < $seuilMin) {
                                        $stockColor = array(255, 235, 156); // Jaune pour niveau bas
                                    } elseif ($stock > $seuilMax && $seuilMax > 0) {
                                        $stockColor = array(198, 239, 206); // Vert clair pour surstock
                                    }
                                    
                                    $pdf->SetFillColor($stockColor[0], $stockColor[1], $stockColor[2]);
                                    
                                    if ($firstRow) {
                                        $pdf->Cell($colWidths[0], 6, $productInfo['code_produit'], 1, 0, 'L', 1);
                                        $pdf->Cell($colWidths[1], 6, $productInfo['libelle_produit'], 1, 0, 'L', 1);
                                        $pdf->Cell($colWidths[2], 6, $productInfo['libelle_categorie'], 1, 0, 'L', 1);
                                        $firstRow = false;
                                    } else {
                                        $pdf->Cell($colWidths[0], 6, '', 1, 0, 'L', 1);
                                        $pdf->Cell($colWidths[1], 6, '', 1, 0, 'L', 1);
                                        $pdf->Cell($colWidths[2], 6, '', 1, 0, 'L', 1);
                                    }
                                    
                                    $pdf->Cell($colWidths[3], 6, $depotData['symbole_unite'], 1, 0, 'C', 1);
                                    $pdf->Cell($colWidths[4], 6, $depotName, 1, 0, 'L', 1);
                                    $pdf->Cell($colWidths[5], 6, number_format($depotData['stock_actuel'], 2), 1, 0, 'R', 1);
                                    $pdf->Cell($colWidths[6], 6, number_format($depotData['seuil_min'], 2), 1, 0, 'R', 1);
                                    $pdf->Cell($colWidths[7], 6, number_format($depotData['seuil_max'], 2), 1, 0, 'R', 1);
                                    $pdf->Cell($colWidths[8], 6, number_format($depotData['prix_unitaire_moyen'] ?? 0, 2) . ' USD', 1, 0, 'R', 1);
                                    $pdf->Cell($colWidths[9], 6, number_format($depotData['valeur_stock'] ?? 0, 2) . ' USD', 1, 1, 'R', 1);
                                }
                            }
                        }
                
                        // Ligne de total
                        $pdf->SetFont('helvetica', 'B', 9);
                        $pdf->SetFillColor(200, 200, 200); // Gris pour le total
                        $totalWidth = $colWidths[0] + $colWidths[1] + $colWidths[2] + $colWidths[3] + $colWidths[4];
                        $pdf->Cell($totalWidth, 7, 'TOTAL GÉNÉRAL', 1, 0, 'R', 1);
                        $pdf->Cell($colWidths[5], 7, number_format($totalStock, 2), 1, 0, 'R', 1);
                        $pdf->Cell($colWidths[6], 7, '', 1, 0, 'C', 1);
                        $pdf->Cell($colWidths[7], 7, '', 1, 0, 'C', 1);
                        $pdf->Cell($colWidths[8], 7, '', 1, 0, 'C', 1);
                        $pdf->Cell($colWidths[9], 7, number_format($totalValeur, 2) . ' USD', 1, 1, 'R', 1);
                
                        // Légende des couleurs
                        $pdf->Ln(5);
                        $pdf->SetFont('helvetica', 'I', 8);
                        $pdf->SetTextColor(80, 80, 80);
                        $pdf->Cell(0, 5, 'Légende des couleurs:', 0, 1, 'L');
                
                        // Couleur rouge pour rupture de stock
                        $pdf->SetFillColor(255, 200, 200);
                        $pdf->Cell(5, 5, '', 1, 0, 'C', 1);
                        $pdf->Cell(50, 5, ' Rupture de stock', 0, 0, 'L');
                
                        // Couleur jaune pour stock sous le seuil minimal
                        $pdf->SetFillColor(255, 235, 156);
                        $pdf->Cell(5, 5, '', 1, 0, 'C', 1);
                        $pdf->Cell(50, 5, ' Stock sous le seuil minimal', 0, 0, 'L');
                
                        // Couleur verte pour surstock
                        $pdf->SetFillColor(198, 239, 206);
                        $pdf->Cell(5, 5, '', 1, 0, 'C', 1);
                        $pdf->Cell(50, 5, ' Stock au-dessus du seuil maximal', 0, 1, 'L');
                
                        $pdf->Ln(3);
                        $pdf->SetTextColor(80, 80, 80);
                        $pdf->Cell(0, 5, 'Rapport généré le ' . date('d/m/Y à H:i') . ' par ' . ($_SESSION['nom'] ?? 'Utilisateur système'), 0, 1, 'L');
                
                        // Ajouter le QR Code
                        $qrCodeData = "ÉTAT DU STOCK\n";
                        $qrCodeData .= implode("\n", $infoTitre) . "\n";
                        $qrCodeData .= "Total articles: " . count($products) . "\n";
                        $qrCodeData .= "Valeur totale du stock: " . number_format($totalValeur, 2) . " USD\n";
                        $qrCodeData .= "Document généré le: " . date('d/m/Y H:i:s') . "\n";
                        $qrCodeData .= $configInstitution['site_web'] ?? '';
                        
                        $pdf->Ln(5);
                        $pdf->write2DBarcode($qrCodeData, 'QRCODE,M', 15, $pdf->GetY(), 25, 25);
                        
                        // Encadré pour le cachet à droite
                        $pdf->SetLineStyle(array('width' => 0.3, 'cap' => 'butt', 'join' => 'miter', 'dash' => 0, 'color' => array(150, 150, 150)));
                        $pdf->RoundedRect($pdf->getPageWidth() - 60, $pdf->GetY(), 40, 25, 2, '1111', null, array('color' => array(150, 150, 150)));
                        
                        $pdf->SetXY($pdf->getPageWidth() - 60, $pdf->GetY() + 10);
                        $pdf->SetFont('helvetica', 'I', 8);
                        $pdf->Cell(40, 5, 'Cachet', 0, 1, 'C');
                
                        // Sortie du PDF
                        $pdf->Output('Etat_Stock_' . date('Y-m-d') . '.pdf', 'I');
                    }
                } catch (Exception $e) {
                    die("Erreur: " . $e->getMessage());
                }
                