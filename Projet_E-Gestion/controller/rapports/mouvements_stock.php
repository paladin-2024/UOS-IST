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

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['id'])) {
    header('Location: ../../index');
    exit();
}

// Initialiser la connexion
$db = Connexion::getInstance()->getPDO();

// Récupérer les paramètres du rapport
$depot_id = isset($_POST['depot_id']) ? $_POST['depot_id'] : 'all';
$produit_id = isset($_POST['produit_id']) ? $_POST['produit_id'] : 'all';
$mouvement_type = isset($_POST['mouvement_type']) ? $_POST['mouvement_type'] : 'all';
$date_debut = isset($_POST['date_debut']) ? $_POST['date_debut'] : date('Y-m-01');
$date_fin = isset($_POST['date_fin']) ? $_POST['date_fin'] : date('Y-m-d');
$format = isset($_POST['format']) ? $_POST['format'] : 'html';

// Calcul du stock initial (date N-1) uniquement si on filtre sur un produit spécifique
$stockInitial = 0;
$stockFinal = 0;
$symbolUnite = '';

if ($produit_id != 'all') {
    // Requête pour obtenir le stock à la date N-1
    $sqlStockInitial = "
        SELECT 
            COALESCE(SUM(CASE WHEN m.type_mouvement = 'Entrée' THEN m.quantite ELSE -m.quantite END), 0) as stock_initial,
            (SELECT symbole_unite FROM unite_mesure um JOIN produit p ON p.id_unite_stockage = um.id_unite WHERE p.id_produit = :produit_id_unite) as symbole_unite
        FROM (
            SELECT 'Entrée' as type_mouvement, des.quantite
            FROM entree_stock es
            JOIN detail_entree_stock des ON es.id_entree = des.id_entree
            WHERE es.etat = 'Validé' 
            AND des.id_produit = :produit_id
            AND es.date_entree < :date_debut
            
            UNION ALL
            
            SELECT 'Sortie' as type_mouvement, dss.quantite
            FROM sortie_stock ss
            JOIN detail_sortie_stock dss ON ss.id_sortie = dss.id_sortie
            WHERE ss.etat = 'Validé'
            AND dss.id_produit = :produit_id
            AND ss.date_sortie < :date_debut
        ) m
    ";
    
    $stmtStockInitial = $db->prepare($sqlStockInitial);
    $stmtStockInitial->bindParam(':produit_id', $produit_id);
    $stmtStockInitial->bindParam(':produit_id_unite', $produit_id);
    $stmtStockInitial->bindParam(':date_debut', $date_debut);
    $stmtStockInitial->execute();
    $resultStockInitial = $stmtStockInitial->fetch(PDO::FETCH_ASSOC);
    $stockInitial = $resultStockInitial['stock_initial'] ?? 0;
    $symbolUnite = $resultStockInitial['symbole_unite'] ?? '';
}

try {
    // Construction de la requête SQL de base pour les entrées
    $params = [];
    $sqlEntrees = "
        SELECT 
            'Entrée' as type_mouvement,
            es.numero_entree as reference,
            es.date_entree as date_mouvement,
            es.type_entree as nature,
            d.code_depot, 
            d.libelle_depot,
            p.code_produit,
            p.libelle_produit,
            des.quantite,
            um.symbole_unite,
            des.prix_unitaire,
            des.montant_total,
            lp.numero_lot,
            lp.date_peremption,
            CONCAT(u.\"nomUser\") as utilisateur,
            es.observation
        FROM entree_stock es
        JOIN detail_entree_stock des ON es.id_entree = des.id_entree
        JOIN depot d ON es.id_depot = d.id_depot
        JOIN produit p ON des.id_produit = p.id_produit
        JOIN unite_mesure um ON p.id_unite_stockage = um.id_unite
        JOIN lot_produit lp ON des.id_detail_entree = lp.id_detail_entree
        JOIN t_users u ON es.id_user_creation = u.\"idUser\"
        WHERE es.etat = 'Validé'
        AND es.date_entree BETWEEN :date_debut_entree AND :date_fin_entree
    ";
    
    $params[':date_debut_entree'] = $date_debut;
    $params[':date_fin_entree'] = $date_fin;

    // Construction de la requête SQL de base pour les sorties
    $sqlSorties = "
        SELECT 
            'Sortie' as type_mouvement,
            ss.numero_sortie as reference,
            ss.date_sortie as date_mouvement,
            ss.type_sortie as nature,
            d.code_depot, 
            d.libelle_depot,
            p.code_produit,
            p.libelle_produit,
            dss.quantite,
            um.symbole_unite,
            dss.prix_unitaire,
            dss.montant_total,
            lp.numero_lot,
            lp.date_peremption,
            CONCAT(u.\"nomUser\") as utilisateur,
            ss.observation
        FROM sortie_stock ss
        JOIN detail_sortie_stock dss ON ss.id_sortie = dss.id_sortie
        JOIN depot d ON ss.id_depot = d.id_depot
        JOIN produit p ON dss.id_produit = p.id_produit
        JOIN unite_mesure um ON p.id_unite_stockage = um.id_unite
        JOIN detail_sortie_lot dsl ON dss.id_detail_sortie = dsl.id_detail_sortie
        JOIN lot_produit lp ON dsl.id_lot = lp.id_lot
        JOIN t_users u ON ss.id_user_creation = u.\"idUser\"
        WHERE ss.etat = 'Validé'
        AND ss.date_sortie BETWEEN :date_debut_sortie AND :date_fin_sortie
    ";
    
    $params[':date_debut_sortie'] = $date_debut;
    $params[':date_fin_sortie'] = $date_fin;

    // Construction de la requête SQL de base pour les transferts
    $sqlTransferts = "
        SELECT 
            'Transfert' as type_mouvement,
            ts.numero_transfert as reference,
            ts.date_transfert as date_mouvement,
            'Transfert' as nature,
            d_source.code_depot as code_depot, 
            CONCAT(d_source.libelle_depot, ' → ', d_dest.libelle_depot) as libelle_depot,
            p.code_produit,
            p.libelle_produit,
            dts.quantite,
            um.symbole_unite,
            lp.prix_unitaire_achat as prix_unitaire,
            (dts.quantite * lp.prix_unitaire_achat) as montant_total,
            lp.numero_lot,
            lp.date_peremption,
            CONCAT(u.\"nomUser\") as utilisateur,
            ts.observation
        FROM transfert_stock ts
        JOIN detail_transfert_stock dts ON ts.id_transfert = dts.id_transfert
        JOIN depot d_source ON ts.id_depot_source = d_source.id_depot
        JOIN depot d_dest ON ts.id_depot_destination = d_dest.id_depot
        JOIN produit p ON dts.id_produit = p.id_produit
        JOIN unite_mesure um ON p.id_unite_stockage = um.id_unite
        JOIN lot_produit lp ON dts.id_lot = lp.id_lot
        JOIN t_users u ON ts.id_user_creation = u.\"idUser\"
        WHERE ts.etat = 'Validé'
        AND ts.date_transfert BETWEEN :date_debut_transfert AND :date_fin_transfert
    ";
    
    $params[':date_debut_transfert'] = $date_debut;
    $params[':date_fin_transfert'] = $date_fin;

    // Ajout des filtres supplémentaires
    if ($depot_id != 'all') {
        $sqlEntrees .= " AND es.id_depot = :depot_id_entree";
        $sqlSorties .= " AND ss.id_depot = :depot_id_sortie";
        $sqlTransferts .= " AND (ts.id_depot_source = :depot_id_transfert OR ts.id_depot_destination = :depot_id_transfert)";
        
        $params[':depot_id_entree'] = $depot_id;
        $params[':depot_id_sortie'] = $depot_id;
        $params[':depot_id_transfert'] = $depot_id;
    }
    
    if ($produit_id != 'all') {
        $sqlEntrees .= " AND des.id_produit = :produit_id_entree";
        $sqlSorties .= " AND dss.id_produit = :produit_id_sortie";
        $sqlTransferts .= " AND dts.id_produit = :produit_id_transfert";
        
        $params[':produit_id_entree'] = $produit_id;
        $params[':produit_id_sortie'] = $produit_id;
        $params[':produit_id_transfert'] = $produit_id;
    }

    // Fusion des requêtes selon le type de mouvement demandé
    $sqlFinal = "";
    if ($mouvement_type == 'all' || $mouvement_type == 'entree') {
        $sqlFinal .= $sqlEntrees;
    }
    
    if ($mouvement_type == 'all' || $mouvement_type == 'sortie') {
        if (!empty($sqlFinal)) {
            $sqlFinal .= " UNION ALL ";
        }
        $sqlFinal .= $sqlSorties;
    }
    
    if ($mouvement_type == 'all' || $mouvement_type == 'transfert') {
        if (!empty($sqlFinal)) {
            $sqlFinal .= " UNION ALL ";
        }
        $sqlFinal .= $sqlTransferts;
    }

    // Ordre et limite finale
    $sqlFinal .= " ORDER BY code_depot, date_mouvement, reference";

    // Exécution de la requête
    $stmt = $db->prepare($sqlFinal);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->execute();
    $mouvements = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Regrouper les mouvements par dépôt et type
    $mouvementsGroupes = [];
    $totalQuantite = 0;
    $totalMontant = 0;
    
    // Si c'est un seul produit, calculer le stock après chaque mouvement
    $stockCourant = $stockInitial;
    
    if ($produit_id != 'all') {
        foreach ($mouvements as &$mouvement) {
            if ($mouvement['type_mouvement'] == 'Entrée' || 
                ($mouvement['type_mouvement'] == 'Transfert' && strpos($mouvement['libelle_depot'], '→') !== false && 
                strpos($mouvement['libelle_depot'], $mouvement['code_depot'] . ' →') === false)) {
                $stockCourant += $mouvement['quantite'];
            } else {
                $stockCourant -= $mouvement['quantite'];
            }
            
            // Ajout du stock après mouvement
            $mouvement['stock_apres'] = $stockCourant;
        }
        unset($mouvement); // Important pour éviter les effets de bord avec les références
        
        // Le stock final est égal au dernier stock courant calculé
        $stockFinal = $stockCourant;
    }
    
    foreach ($mouvements as $mouvement) {
        $depot = $mouvement['code_depot'] . ' - ' . $mouvement['libelle_depot'];
        $type = $mouvement['type_mouvement'];
        
        if (!isset($mouvementsGroupes[$depot])) {
            $mouvementsGroupes[$depot] = [];
        }
        
        if (!isset($mouvementsGroupes[$depot][$type])) {
            $mouvementsGroupes[$depot][$type] = [];
        }
        
        $mouvementsGroupes[$depot][$type][] = $mouvement;
        
        // Cumuler les totaux généraux
        $totalQuantite += $mouvement['quantite'];
        $totalMontant += $mouvement['montant_total'];
    }

    // Récupération des informations des dépôts et produits pour le titre du rapport
    $infoTitre = [];
    
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
    
    if ($produit_id != 'all') {
        $stmtProduit = $db->prepare("SELECT code_produit, libelle_produit FROM produit WHERE id_produit = :id_produit");
        $stmtProduit->bindParam(':id_produit', $produit_id);
        $stmtProduit->execute();
        $produit = $stmtProduit->fetch(PDO::FETCH_ASSOC);
        if ($produit) {
            $infoTitre[] = "Produit: " . $produit['code_produit'] . ' - ' . $produit['libelle_produit'];
        }
    } else {
        $infoTitre[] = "Tous les produits";
    }
    
    switch ($mouvement_type) {
        case 'entree':
            $infoTitre[] = "Type: Entrées de stock";
            break;
        case 'sortie':
            $infoTitre[] = "Type: Sorties de stock";
            break;
        case 'transfert':
            $infoTitre[] = "Type: Transferts de stock";
            break;
        default:
            $infoTitre[] = "Type: Tous les mouvements";
    }
    
    $infoTitre[] = "Période: du " . date('d/m/Y', strtotime($date_debut)) . " au " . date('d/m/Y', strtotime($date_fin));

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

    // FORMAT HTML
    if ($format == 'html') {
        // Afficher en HTML
        include dirname(dirname(__DIR__)) . '/views/rapports/html_mouvements_stock.php';
        
        
    }    // FORMAT EXCEL
    elseif ($format == 'excel') {
        

        // Créer un nouveau document Spreadsheet
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        
        // Configuration de la page
        $sheet->getPageSetup()->setOrientation(PageSetup::ORIENTATION_LANDSCAPE);
        $sheet->getPageSetup()->setFitToWidth(1);
        $sheet->getPageSetup()->setFitToHeight(0);
        
        // Nom de l'onglet
        $sheet->setTitle('Mouvements de stock');
        
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
            $lastCol = $produit_id != 'all' ? 'L' : 'K';
            $sheet->mergeCells('B1:' . $lastCol . '1');
            $sheet->mergeCells('B2:' . $lastCol . '2');
            $sheet->mergeCells('B3:' . $lastCol . '3');
            
            // Style pour l'en-tête
            $sheet->getStyle('B1')->getFont()->setBold(true)->setSize(14);
            $sheet->getStyle('B2')->getFont()->setBold(true)->setSize(12);
            $sheet->getStyle('B1:B3')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            
            $row = 5;
        }
        
        // Titre du rapport
        $sheet->setCellValue('A' . $row, 'RAPPORT DES MOUVEMENTS DE STOCK');
        $sheet->mergeCells('A' . $row . ':' . $lastCol . $row);
        $sheet->getStyle('A' . $row)->getFont()->setBold(true)->setSize(14);
        $sheet->getStyle('A' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        
        // Sous-titre avec filtres
        $row++;
        $sheet->setCellValue('A' . $row, implode(' | ', $infoTitre));
        $sheet->mergeCells('A' . $row . ':' . $lastCol . $row);
        $sheet->getStyle('A' . $row)->getFont()->setBold(true)->setSize(11);
        $sheet->getStyle('A' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        
        // Stock initial pour un seul produit
        if ($produit_id != 'all') {
            $row += 2;
            $sheet->mergeCells('A' . $row . ':G' . $row);
            $sheet->setCellValue('A' . $row, 'Stock initial au ' . date('d/m/Y', strtotime($date_debut.' -1 day')) . ':');
            $sheet->setCellValue('H' . $row, number_format($stockInitial, 2) . ' ' . $symbolUnite);
            
            // Style pour la ligne de stock initial
            $sheet->getStyle('A' . $row . ':H' . $row)->getFill()->setFillType(Fill::FILL_SOLID)
                ->getStartColor()->setARGB('FFD9EDF7'); // Bleu clair pour info
            $sheet->getStyle('A' . $row . ':H' . $row)->getFont()->setBold(true);
            $sheet->getStyle('A' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            $sheet->getStyle('H' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
            $sheet->getStyle('A' . $row . ':H' . $row)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        }
        
        $row += 2;
        
        // Parcourir les mouvements par dépôt et type
        foreach ($mouvementsGroupes as $depot => $typesMouvement) {
            // En-tête de dépôt
            $sheet->mergeCells('A' . $row . ':' . $lastCol . $row);
            $sheet->setCellValue('A' . $row, 'Dépôt: ' . $depot);
            $sheet->getStyle('A' . $row . ':' . $lastCol . $row)->getFill()->setFillType(Fill::FILL_SOLID)
                ->getStartColor()->setARGB('FFDDDDDD');
            $sheet->getStyle('A' . $row . ':' . $lastCol . $row)->getFont()->setBold(true);
            $sheet->getStyle('A' . $row . ':' . $lastCol . $row)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
            $row++;
            
            foreach ($typesMouvement as $typeMouvement => $mouvements) {
                // Sous-en-tête de type de mouvement
                $sheet->mergeCells('A' . $row . ':' . $lastCol . $row);
                $sheet->setCellValue('A' . $row, $typeMouvement);
                
                // Définir la couleur selon le type de mouvement
                $colorRGB = '';
                if ($typeMouvement == 'Entrée') {
                    $colorRGB = 'FFC6EFCE'; // Vert clair pour les entrées
                } elseif ($typeMouvement == 'Sortie') {
                    $colorRGB = 'FFFFC7CE'; // Rouge clair pour les sorties
                } else {
                    $colorRGB = 'FFB4C6E7'; // Bleu clair pour les transferts
                }
                
                $sheet->getStyle('A' . $row . ':' . $lastCol . $row)->getFill()->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()->setARGB($colorRGB);
                $sheet->getStyle('A' . $row . ':' . $lastCol . $row)->getFont()->setBold(true);
                $sheet->getStyle('A' . $row . ':' . $lastCol . $row)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
                $row++;
                
                // En-têtes des colonnes
                $headers = ['Date', 'Référence', 'Nature', 'Code Produit', 'Désignation', 'Lot', 'Péremption', 'Quantité', 'Prix Unitaire', 'Montant Total'];
                
                // Ajout de la colonne "Stock après" si un seul produit
                if ($produit_id != 'all') {
                    $headers[] = 'Stock Après';
                }
                
                $headers[] = 'Utilisateur';
                
                // Définir les colonnes
                $columns = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L'];
                
                for ($i = 0; $i < count($headers); $i++) {
                    $sheet->setCellValue($columns[$i] . $row, $headers[$i]);
                    $sheet->getStyle($columns[$i] . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                }
                
                $sheet->getStyle('A' . $row . ':' . $lastCol . $row)->getFill()->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()->setARGB('FFF2F2F2');
                $sheet->getStyle('A' . $row . ':' . $lastCol . $row)->getFont()->setBold(true);
                $sheet->getStyle('A' . $row . ':' . $lastCol . $row)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
                $row++;
                
                // Données des mouvements
                $sousTotal = 0;
                $sousTotalMontant = 0;
                
                foreach ($mouvements as $mouvement) {
                    $sheet->setCellValue('A' . $row, date('d/m/Y', strtotime($mouvement['date_mouvement'])));
                    $sheet->setCellValue('B' . $row, $mouvement['reference']);
                    $sheet->setCellValue('C' . $row, $mouvement['nature']);
                    $sheet->setCellValue('D' . $row, $mouvement['code_produit']);
                    $sheet->setCellValue('E' . $row, $mouvement['libelle_produit']);
                    $sheet->setCellValue('F' . $row, $mouvement['numero_lot']);
                    $sheet->setCellValue('G' . $row, $mouvement['date_peremption'] ? date('d/m/Y', strtotime($mouvement['date_peremption'])) : 'N/A');
                    $sheet->setCellValue('H' . $row, $mouvement['quantite'] . ' ' . $mouvement['symbole_unite']);
                    $sheet->setCellValue('I' . $row, number_format($mouvement['prix_unitaire'], 2) . ' USD');
                    $sheet->setCellValue('J' . $row, number_format($mouvement['montant_total'], 2) . ' USD');
                    
                    if ($produit_id != 'all') {
                        $sheet->setCellValue('K' . $row, number_format($mouvement['stock_apres'], 2) . ' ' . $mouvement['symbole_unite']);
                        $sheet->setCellValue('L' . $row, $mouvement['utilisateur']);
                    } else {
                        $sheet->setCellValue('K' . $row, $mouvement['utilisateur']);
                    }
                    
                    // Styles pour les cellules de données
                    $sheet->getStyle('A' . $row . ':' . $lastCol . $row)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
                    $sheet->getStyle('A' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                    $sheet->getStyle('H' . $row . ':J' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                    
                    if ($produit_id != 'all') {
                        $sheet->getStyle('K' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                    }
                    
                    // Cumuler les sous-totaux
                    $sousTotal += $mouvement['quantite'];
                    $sousTotalMontant += $mouvement['montant_total'];
                    $row++;
                }
                
                // Sous-total pour ce type de mouvement
                if ($produit_id != 'all') {
                    $sheet->mergeCells('A' . $row . ':G' . $row);
                } else {
                    $sheet->mergeCells('A' . $row . ':G' . $row);
                }
                
                $sheet->setCellValue('A' . $row, 'Sous-total ' . $typeMouvement);
                $sheet->setCellValue('H' . $row, number_format($sousTotal, 2));
                $sheet->setCellValue('I' . $row, '');
                $sheet->setCellValue('J' . $row, number_format($sousTotalMontant, 2) . ' USD');
                
                if ($produit_id != 'all') {
                    $sheet->setCellValue('K' . $row, '');
                    $sheet->setCellValue('L' . $row, '');
                } else {
                    $sheet->setCellValue('K' . $row, '');
                }
                
                // Style pour la ligne de sous-total
                $sheet->getStyle('A' . $row . ':' . $lastCol . $row)->getFill()->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()->setARGB($colorRGB);
                $sheet->getStyle('A' . $row . ':' . $lastCol . $row)->getFont()->setBold(true);
                $sheet->getStyle('A' . $row . ':' . $lastCol . $row)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
                $sheet->getStyle('A' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                $sheet->getStyle('H' . $row . ':J' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                
                $row += 2; // Ajouter un espace après chaque groupe
            }
        }
        
        // Total général
        if ($produit_id != 'all') {
            $sheet->mergeCells('A' . $row . ':G' . $row);
        } else {
            $sheet->mergeCells('A' . $row . ':G' . $row);
        }
        
        $sheet->setCellValue('A' . $row, 'TOTAL GÉNÉRAL');
        $sheet->setCellValue('H' . $row, number_format($totalQuantite, 2));
        $sheet->setCellValue('I' . $row, '');
        $sheet->setCellValue('J' . $row, number_format($totalMontant, 2) . ' USD');
        
        if ($produit_id != 'all') {
            $sheet->setCellValue('K' . $row, '');
            $sheet->setCellValue('L' . $row, '');
        } else {
            $sheet->setCellValue('K' . $row, '');
        }
        
        // Style pour le total général
        $sheet->getStyle('A' . $row . ':' . $lastCol . $row)->getFill()->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setARGB('FFAAAAAA');
        $sheet->getStyle('A' . $row . ':' . $lastCol . $row)->getFont()->setBold(true);
        $sheet->getStyle('A' . $row . ':' . $lastCol . $row)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        $sheet->getStyle('A' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        $sheet->getStyle('H' . $row . ':J' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        
        // Stock final pour un seul produit
        if ($produit_id != 'all') {
            $row += 2;
            $sheet->mergeCells('A' . $row . ':G' . $row);
            $sheet->setCellValue('A' . $row, 'Stock final au ' . date('d/m/Y', strtotime($date_fin)) . ':');
            $sheet->setCellValue('H' . $row, number_format($stockFinal, 2) . ' ' . $symbolUnite);
            
            // Style pour la ligne de stock final
            $sheet->getStyle('A' . $row . ':H' . $row)->getFill()->setFillType(Fill::FILL_SOLID)
                ->getStartColor()->setARGB('FFDFF0D8'); // Vert clair pour succès
            $sheet->getStyle('A' . $row . ':H' . $row)->getFont()->setBold(true);
            $sheet->getStyle('A' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            $sheet->getStyle('H' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
            $sheet->getStyle('A' . $row . ':H' . $row)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        }
        
        // Ajuster automatiquement la largeur des colonnes
        foreach (range('A', $lastCol) as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }
        
        // Information de génération
        $row += 2;
        $sheet->mergeCells('A' . $row . ':' . $lastCol . $row);
        $sheet->setCellValue('A' . $row, 'Rapport généré le ' . date('d/m/Y à H:i') . ' par ' . ($_SESSION['nom'] ?? 'Utilisateur système'));
        $sheet->getStyle('A' . $row)->getFont()->setItalic(true)->setSize(9);
        $sheet->getStyle('A' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
        
        // Créer l'objet Writer pour Excel
        $writer = new Xlsx($spreadsheet);
        
        // Définir les en-têtes pour le téléchargement
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="Rapport_Mouvements_Stock_' . date('Y-m-d') . '.xlsx"');
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
    $pdf->SetTitle('Rapport des mouvements de stock');
    $pdf->SetSubject('Rapport des mouvements de stock');
    $pdf->SetKeywords('Stock, Mouvements, Rapport');

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
    $pdf->Cell(0, 10, 'RAPPORT DES MOUVEMENTS DE STOCK', 0, 1, 'C', 1);

    // Sous-titre avec les filtres
    $pdf->SetTextColor(80, 80, 80);
    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->Cell(0, 8, implode(' | ', $infoTitre), 0, 1, 'C');

    $pdf->Ln(2);

    // Afficher le stock initial si c'est un seul produit
    if ($produit_id != 'all') {
        $pdf->SetFont('helvetica', 'B', 10);
        $pdf->SetFillColor(217, 237, 247); // Bleu clair pour l'info
        $pdf->Cell(0, 8, 'Stock initial au '.date('d/m/Y', strtotime($date_debut.' -1 day')).': '.number_format($stockInitial, 2).' '.$symbolUnite, 1, 1, 'L', 1);
        $pdf->Ln(2);
    }

    // Parcourir les données groupées
    $totalQuantite = 0;
    $totalMontant = 0;

    foreach ($mouvementsGroupes as $depot => $typesMouvement) {
        // En-tête de dépôt
        $pdf->SetFont('helvetica', 'B', 9);
        $pdf->SetFillColor(220, 220, 220);
        $pdf->Cell(0, 7, 'Dépôt: ' . $depot, 1, 1, 'L', 1);
        
        foreach ($typesMouvement as $typeMouvement => $mouvements) {
            // Sous-en-tête de type de mouvement
            $pdf->SetFont('helvetica', 'B', 8);
            
            // Définir la couleur selon le type de mouvement
            if ($typeMouvement == 'Entrée') {
                $pdf->SetTextColor(0, 128, 0); // Vert pour les entrées
            } elseif ($typeMouvement == 'Sortie') {
                $pdf->SetTextColor(220, 20, 60); // Rouge pour les sorties
            } else {
                $pdf->SetTextColor(0, 0, 255); // Bleu pour les transferts
            }
            
            $pdf->SetFillColor(240, 240, 240);
            $pdf->Cell(0, 6, $typeMouvement, 1, 1, 'L', 1);
            $pdf->SetTextColor(0, 0, 0); // Réinitialiser la couleur
            
            // En-têtes des colonnes pour ce groupe
            $pdf->SetFillColor(245, 245, 245);
            
            // Définir les en-têtes et largeurs selon le type de rapport
            if ($produit_id != 'all') {
                // Pour un seul produit: ajouter la colonne "Stock après" et garder "Utilisateur"
                $headers = array('Date', 'Référence', 'Nature', 'Code', 'Désignation', 'Lot', 'Péremption', 'Quantité', 'Prix Unit.', 'Montant', 'Stock après', 'Utilisateur');
                $colWidths = array(18, 22, 17, 15, 40, 18, 18, 18, 18, 20, 20, 22);
            } else {
                // Pour tous les produits: enlever la colonne "Utilisateur" dans le PDF
                $headers = array('Date', 'Référence', 'Nature', 'Code', 'Désignation', 'Lot', 'Péremption', 'Quantité', 'Prix Unit.', 'Montant');
                $colWidths = array(20, 30, 15, 20, 97, 20, 20, 20, 15, 20);
            }
            
            for ($i = 0; $i < count($headers); $i++) {
                $pdf->Cell($colWidths[$i], 7, $headers[$i], 1, 0, 'C', 1);
            }
            $pdf->Ln();
            
            // Données des mouvements
            $pdf->SetFont('helvetica', '', 7);
            $sousTotal = 0;
            $sousTotalMontant = 0;
            
            foreach ($mouvements as $mouvement) {
                // Formater les valeurs
                $date = date('d/m/Y', strtotime($mouvement['date_mouvement']));
                $peremption = $mouvement['date_peremption'] ? date('d/m/Y', strtotime($mouvement['date_peremption'])) : 'N/A';
                $quantite = $mouvement['quantite'] . ' ' . $mouvement['symbole_unite'];
                $prixUnitaire = number_format($mouvement['prix_unitaire'], 2) . ' USD';
                $montantTotal = number_format($mouvement['montant_total'], 2) . ' USD';
                
                // Gérer les textes longs
                $designationLines = $pdf->getNumLines($mouvement['libelle_produit'], $colWidths[4]);
                $lineHeight = $designationLines > 1 ? $designationLines * 5 : 5;
                
                // Vérifier l'espace restant sur la page
                if ($pdf->GetY() + $lineHeight > $pdf->getPageHeight() - 25) {
                    $pdf->AddPage();
                    
                    // Réimprimer l'en-tête de dépôt et type
                    $pdf->SetFont('helvetica', 'B', 9);
                    $pdf->SetFillColor(220, 220, 220);
                    $pdf->Cell(0, 7, 'Dépôt: ' . $depot . ' (suite)', 1, 1, 'L', 1);
                    
                    // Sous-en-tête de type de mouvement
                    $pdf->SetFont('helvetica', 'B', 8);
                    if ($typeMouvement == 'Entrée') {
                        $pdf->SetTextColor(0, 128, 0);
                    } elseif ($typeMouvement == 'Sortie') {
                        $pdf->SetTextColor(220, 20, 60);
                    } else {
                        $pdf->SetTextColor(0, 0, 255);
                    }
                    $pdf->SetFillColor(240, 240, 240);
                    $pdf->Cell(0, 6, $typeMouvement, 1, 1, 'L', 1);
                    $pdf->SetTextColor(0, 0, 0);
                    
                    // En-têtes des colonnes
                    $pdf->SetFillColor(245, 245, 245);
                    for ($i = 0; $i < count($headers); $i++) {
                        $pdf->Cell($colWidths[$i], 7, $headers[$i], 1, 0, 'C', 1);
                    }
                    $pdf->Ln();
                    $pdf->SetFont('helvetica', '', 7);
                }
                
                $pdf->Cell($colWidths[0], $lineHeight, $date, 1, 0, 'C');
                $pdf->Cell($colWidths[1], $lineHeight, $mouvement['reference'], 1, 0, 'L');
                $pdf->Cell($colWidths[2], $lineHeight, $mouvement['nature'], 1, 0, 'L');
                $pdf->Cell($colWidths[3], $lineHeight, $mouvement['code_produit'], 1, 0, 'C');
                $pdf->MultiCell($colWidths[4], $lineHeight, $mouvement['libelle_produit'], 1, 'L', 0, 0);
                $pdf->Cell($colWidths[5], $lineHeight, $mouvement['numero_lot'], 1, 0, 'C');
                $pdf->Cell($colWidths[6], $lineHeight, $peremption, 1, 0, 'C');
                $pdf->Cell($colWidths[7], $lineHeight, $quantite, 1, 0, 'R');
                $pdf->Cell($colWidths[8], $lineHeight, $prixUnitaire, 1, 0, 'R');
                $pdf->Cell($colWidths[9], $lineHeight, $montantTotal, 1, 0, 'R');
                
                // Affichage différent selon le type de rapport
                if ($produit_id != 'all') {
                    // Pour un seul produit: afficher le stock après et l'utilisateur
                    $pdf->Cell($colWidths[10], $lineHeight, number_format($mouvement['stock_apres'], 2) . ' ' . $mouvement['symbole_unite'], 1, 0, 'R');
                    $pdf->Cell($colWidths[11], $lineHeight, $mouvement['utilisateur'], 1, 1, 'L');
                } else {
                    // Pour tous les produits: ne pas afficher l'utilisateur dans le PDF
                    $pdf->Ln();
                }
                
                // Cumuler les totaux
                $sousTotal += $mouvement['quantite'];
                $sousTotalMontant += $mouvement['montant_total'];
                $totalQuantite += $mouvement['quantite'];
                $totalMontant += $mouvement['montant_total'];
            }
            
            // Sous-total pour ce type de mouvement
            $pdf->SetFont('helvetica', 'B', 8);
            $pdf->SetFillColor(240, 240, 240);
            
            if ($produit_id != 'all') {
                $totalColsWidth = 0;
                for ($i = 0; $i < 7; $i++) {
                    $totalColsWidth += $colWidths[$i];
                }
                
                $pdf->Cell($totalColsWidth, 6, 'Sous-total ' . $typeMouvement, 1, 0, 'R', 1);
                $pdf->Cell($colWidths[7], 6, number_format($sousTotal, 2), 1, 0, 'R', 1);
                $pdf->Cell($colWidths[8], 6, '', 1, 0, 'C', 1);
                $pdf->Cell($colWidths[9], 6, number_format($sousTotalMontant, 2) . ' USD', 1, 0, 'R', 1);
                $pdf->Cell($colWidths[10], 6, '', 1, 0, 'C', 1);
                $pdf->Cell($colWidths[11], 6, '', 1, 1, 'C', 1);
            } else {
                $totalColsWidth = 0;
                for ($i = 0; $i < 7; $i++) {
                    $totalColsWidth += $colWidths[$i];
                }
                
                $pdf->Cell($totalColsWidth, 6, 'Sous-total ' . $typeMouvement, 1, 0, 'R', 1);
                $pdf->Cell($colWidths[7], 6, number_format($sousTotal, 2), 1, 0, 'R', 1);
                $pdf->Cell($colWidths[8], 6, '', 1, 0, 'C', 1);
                $pdf->Cell($colWidths[9], 6, number_format($sousTotalMontant, 2) . ' USD', 1, 1, 'R', 1);
            }
            
            $pdf->Ln(2);
        }
    }

    // Grand total
    $pdf->SetFont('helvetica', 'B', 9);
    $pdf->SetFillColor(200, 200, 200);
    
    if ($produit_id != 'all') {
        $totalWidth = array_sum(array_slice($colWidths, 0, 7));
        $pdf->Cell($totalWidth, 7, 'TOTAL GÉNÉRAL', 1, 0, 'R', 1);
        $pdf->Cell($colWidths[7], 7, number_format($totalQuantite, 2), 1, 0, 'R', 1);
        $pdf->Cell($colWidths[8], 7, '', 1, 0, 'C', 1);
        $pdf->Cell($colWidths[9], 7, number_format($totalMontant, 2) . ' USD', 1, 0, 'R', 1);
        $pdf->Cell($colWidths[10], 7, '', 1, 0, 'C', 1);
        $pdf->Cell($colWidths[11], 7, '', 1, 1, 'C', 1);
    } else {
        $totalWidth = array_sum(array_slice($colWidths, 0, 7));
        $pdf->Cell($totalWidth, 7, 'TOTAL GÉNÉRAL', 1, 0, 'R', 1);
        $pdf->Cell($colWidths[7], 7, number_format($totalQuantite, 2), 1, 0, 'R', 1);
        $pdf->Cell($colWidths[8], 7, '', 1, 0, 'C', 1);
        $pdf->Cell($colWidths[9], 7, number_format($totalMontant, 2) . ' USD', 1, 1, 'R', 1);
    }

    // Afficher le stock final si c'est un seul produit
    if ($produit_id != 'all') {
        $pdf->Ln(2);
        $pdf->SetFont('helvetica', 'B', 10);
        $pdf->SetFillColor(223, 240, 216); // Vert clair pour le succès
        $pdf->Cell(0, 8, 'Stock final au '.date('d/m/Y', strtotime($date_fin)).': '.number_format($stockFinal, 2).' '.$symbolUnite, 1, 1, 'L', 1);
    }

    $pdf->Ln(5);

    // Informations complémentaires et légende
    $pdf->SetFont('helvetica', 'I', 8);
    $pdf->SetTextColor(80, 80, 80);
    $pdf->Cell(0, 5, 'Légende des types de mouvements:', 0, 1, 'L');
    
    $pdf->SetTextColor(0, 128, 0);
    $pdf->Cell(30, 5, 'Entrée', 0, 0, 'L');
    
    $pdf->SetTextColor(220, 20, 60);
    $pdf->Cell(30, 5, 'Sortie', 0, 0, 'L');
    
    $pdf->SetTextColor(0, 0, 255);
    $pdf->Cell(30, 5, 'Transfert', 0, 1, 'L');
    
    $pdf->SetTextColor(80, 80, 80);
    $pdf->Ln(2);
    $pdf->Cell(0, 5, 'Rapport généré le ' . date('d/m/Y à H:i') . ' par ' . ($_SESSION['nom'] ?? 'Utilisateur système'), 0, 1, 'L');

    // Ajouter le QR Code
    $qrCodeData = "RAPPORT DES MOUVEMENTS DE STOCK\n";
    $qrCodeData .= implode("\n", $infoTitre) . "\n";
    $qrCodeData .= "Total quantité: " . number_format($totalQuantite, 2) . "\n";
    $qrCodeData .= "Total montant: " . number_format($totalMontant, 2) . " USD\n";
    if ($produit_id != 'all') {
        $qrCodeData .= "Stock initial: " . number_format($stockInitial, 2) . " " . $symbolUnite . "\n";
        $qrCodeData .= "Stock final: " . number_format($stockFinal, 2) . " " . $symbolUnite . "\n";
    }
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

    // Outputting the PDF
    $pdf->Output('Rapport_Mouvements_Stock_' . date('Y-m-d') . '.pdf', 'I');
}
} catch (Exception $e) {
die("Erreur: " . $e->getMessage());
}
