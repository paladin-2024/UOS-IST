<?php
session_start();
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/vendor/autoload.php'; // Pour TCPDF

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['id'])) {
    header('Location: ../index');
    exit();
}

// Initialisation des paramètres
$id_depot = isset($_GET['depot']) ? intval($_GET['depot']) : 0;
$categorie = isset($_GET['categorie']) ? intval($_GET['categorie']) : 0;
$date_rapport = date('Y-m-d');
$type_rapport = isset($_GET['type']) ? $_GET['type'] : 'general';

// Initialiser la connexion
$db = Connexion::getInstance()->getPDO();

try {
    // Récupérer les informations du dépôt
    $depot = null;
    if ($id_depot > 0) {
        $stmt = $db->prepare("SELECT * FROM depot WHERE id_depot = :id_depot");
        $stmt->bindParam(':id_depot', $id_depot, PDO::PARAM_INT);
        $stmt->execute();
        $depot = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$depot) {
            throw new Exception("Dépôt non trouvé.");
        }
    }
    
    // Récupérer les informations de l'entreprise (à adapter selon votre structure)
    $stmt = $db->prepare("SELECT * FROM succursale WHERE actif = 1 LIMIT 1");
    $stmt->execute();
    $entreprise = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Classe personnalisée pour TCPDF
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
            
            // Date et signature électronique (centré sur sa propre ligne)
            $this->SetX(15);
            $this->Cell(($this->getPageWidth() - 30), 5, 'Document généré le ' . date('d/m/Y') . ' • Signature électronique sécurisée', 0, 1, 'C');

            // Nom de l'entreprise et site web (centré sur sa propre ligne)
            $entrepriseNom = $GLOBALS['entreprise'] ? $GLOBALS['entreprise']['nom_succursale'] : 'eGestion';
            $this->Cell(($this->getPageWidth() - 30), 5, $entrepriseNom . ' • Document officiel. ', 0, 1, 'C');
        }
    }
    
    // Créer l'instance de la classe personnalisée
    $pdf = new MYPDF('P', 'mm', 'A4', true, 'UTF-8', false);
    
    // Rendre la variable entreprise accessible globalement pour le pied de page
    $GLOBALS['entreprise'] = $entreprise;
    
    // Configurer le document
    $pdf->SetCreator('eGestion');
    $pdf->SetAuthor($entreprise['nom_succursale'] ?? 'eGestion');
    
    $titre = "État du stock";
    if ($type_rapport == 'valorise') {
        $titre = "Valorisation du stock";
    } elseif ($type_rapport == 'alerte') {
        $titre = "Stock en alerte";
    } elseif ($type_rapport == 'peremption') {
        $titre = "Stock par date de péremption";
    }
    
    $pdf->SetTitle($titre);
    $pdf->SetSubject($titre);
    $pdf->SetKeywords('Stock, Inventaire, Rapport');
    
    // Supprimer les en-têtes et pieds de page par défaut
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(true);
    
    // Définir les marges
    $pdf->SetMargins(20, 20, 20);
    $pdf->SetAutoPageBreak(true, 25);
    
    // Couleurs pour le design
    $primaryColor = array(0, 87, 146); // Bleu foncé
    $secondaryColor = array(70, 130, 180); // Bleu acier
    $accentColor = array(0, 121, 194); // Bleu moyen
    
    // Ajouter une page
    $pdf->AddPage();
    
    // Ajouter le logo en filigrane
    if ($entreprise && !empty($entreprise['logo'])) {
        $logoPath = dirname(__DIR__) . '/uploads/logos/' . $entreprise['logo'];
        if (file_exists($logoPath)) {
            // Sauvegarder l'état actuel
            $pdf->setAlpha(0.1);
            
            // Position au centre
            $pageWidth = $pdf->getPageWidth();
            $pageHeight = $pdf->getPageHeight();
            
            // Définir une largeur plus petite mais conserver la même hauteur
            $logoWidth = 70;
            $logoHeight = 100;
            
            $x = ($pageWidth - $logoWidth) / 2;
            $y = ($pageHeight - $logoHeight) / 2;
            
            // Ajouter l'image en filigrane avec largeur réduite
            $pdf->Image($logoPath, $x, $y, $logoWidth, $logoHeight, '', '', '', false, 300, '', false, false, 0);
            
            // Restaurer l'état
            $pdf->setAlpha(1);
        }
    }
    
    // En-tête avec les informations de l'entreprise
    if ($entreprise) {
        // Logo de l'entreprise (visible)
        if (!empty($entreprise['logo'])) {
            $logoPath = dirname(__DIR__) . '/uploads/logos/' . $entreprise['logo'];
            if (file_exists($logoPath)) {
                $pdf->Image($logoPath, 20, 15, 20, 0, '', '', '', false, 200, '', false, false, 0);
            }
        }
        
        // Titre et informations de l'entreprise
        $pdf->SetTextColor($primaryColor[0], $primaryColor[1], $primaryColor[2]);
        $pdf->SetFont('helvetica', 'B', 14);
        $pdf->SetXY(50, 15);
        $pdf->Cell(0, 8, strtoupper($entreprise['nom_succursale'] ?? 'ENTREPRISE'), 0, 1, 'C');
        
        $pdf->SetFont('helvetica', '', 9);
        $pdf->SetTextColor(80, 80, 80);
        if (!empty($entreprise['adresse'])) {
            $pdf->SetXY(50, 25);
            $pdf->Cell(0, 4, $entreprise['adresse'], 0, 1, 'C');
        }
        
        $contactInfo = '';
        if (!empty($entreprise['telephone'])) {
            $contactInfo .= 'Tél: ' . $entreprise['telephone'] . ' ';
        }
        if (!empty($entreprise['email'])) {
            $contactInfo .= 'Email: ' . $entreprise['email'] . ' ';
        }
        
        if (!empty($contactInfo)) {
            $pdf->SetXY(50, 30);
            $pdf->Cell(0, 4, $contactInfo, 0, 1, 'C');
        }
        
        // Ligne de séparation
        $pdf->SetLineStyle(array('width' => 0.5, 'cap' => 'butt', 'join' => 'miter', 'dash' => 0, 'color' => $accentColor));
        $pdf->Line(15, 38, $pdf->getPageWidth() - 15, 38);
    }
    
    // Titre du document avec fond coloré
    $pdf->SetFillColor($primaryColor[0], $primaryColor[1], $primaryColor[2]);
    $pdf->SetTextColor(255, 255, 255);
    $pdf->SetFont('helvetica', 'B', 16);
    $pdf->Ln(5);
    $pdf->Cell(0, 10, strtoupper($titre), 0, 1, 'C', 1);
    
    $pdf->SetTextColor(80, 80, 80);
    $pdf->SetFont('helvetica', 'B', 10);
    
    // Informations du rapport
    $infoRapport = "Généré le " . date('d/m/Y à H:i');
    if ($depot) {
        $infoRapport .= " | Dépôt: " . $depot['libelle_depot'];
    }
    $pdf->Cell(0, 8, $infoRapport, 0, 1, 'C');
    
    // Générer le QR Code avec les informations du rapport
    $qrCodeData = "RAPPORT DE STOCK\n";
    $qrCodeData .= "Type: " . ucfirst($type_rapport) . "\n";
    $qrCodeData .= "Date: " . date('d/m/Y H:i:s') . "\n";
    if ($depot) {
        $qrCodeData .= "Dépôt: " . $depot['libelle_depot'] . "\n";
    }
    $qrCodeData .= "Généré par: " . ($_SESSION['nomUser'] ?? 'Utilisateur système') . "\n";
    
    // Placer le QR code en haut à droite
    $qrX = $pdf->getPageWidth() - 40;
    $qrY = 15;
    $qrSize = 20;
    
    // Style amélioré pour le QR code
    $qrStyle = array(
        'border' => false,
        'padding' => 2,
        'fgcolor' => array($primaryColor[0], $primaryColor[1], $primaryColor[2]),
        'bgcolor' => array(255, 255, 255)
    );
    
    // Dessiner un cadre décoratif autour du QR code
    $pdf->RoundedRect($qrX - 2, $qrY - 2, $qrSize + 4, $qrSize + 4, 2, '1111', 'DF', array(), array(245, 245, 245));
    
    // Placer le QR code
    $pdf->write2DBarcode($qrCodeData, 'QRCODE,L', $qrX, $qrY, $qrSize, $qrSize, $qrStyle, 'N');
    
    // Récupérer les données en fonction du type de rapport
    if ($type_rapport == 'general') {
        // État général du stock
        $sql = "SELECT p.id_produit, p.code_produit, p.libelle_produit, c.libelle_categorie, 
                u.libelle_unite, SUM(l.quantite_disponible) as stock_total,
                p.description
                FROM produit p
                LEFT JOIN categorie_produit c ON p.id_categorie = c.id_categorie
                LEFT JOIN unite_mesure u ON p.id_unite_stockage = u.id_unite_mesure
                LEFT JOIN lot_produit l ON p.id_produit = l.id_produit";
        
        // Filtre par dépôt si spécifié
        $whereConditions = ["p.actif = 1"];
        $params = [];
        
        if ($id_depot > 0) {
            $sql .= " LEFT JOIN detail_entree_stock des ON l.id_detail_entree = des.id_detail_entree
                     LEFT JOIN entree_stock es ON des.id_entree = es.id_entree";
            $whereConditions[] = "es.id_depot = :id_depot";
            $params[':id_depot'] = $id_depot;
        }
        
        if ($categorie > 0) {
            $whereConditions[] = "p.id_categorie = :id_categorie";
            $params[':id_categorie'] = $categorie;
        }
        
        if (!empty($whereConditions)) {
            $sql .= " WHERE " . implode(" AND ", $whereConditions);
        }
        
        $sql .= " GROUP BY p.id_produit
                 ORDER BY c.libelle_categorie, p.libelle_produit";
        
        $stmt = $db->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->execute();
        $produits = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Titre de la section
        $pdf->SetTextColor($secondaryColor[0], $secondaryColor[1], $secondaryColor[2]);
        $pdf->SetFont('helvetica', 'B', 12);
        $pdf->Ln(5);
        $pdf->Cell(0, 10, 'ÉTAT GÉNÉRAL DU STOCK', 0, 1, 'L');
        
        // Ligne décorative sous le titre de section
        $pdf->SetLineStyle(array('width' => 0.2, 'cap' => 'butt', 'join' => 'miter', 'dash' => 0, 'color' => $secondaryColor));
        $pdf->Line(20, $pdf->GetY(), 100, $pdf->GetY());
        $pdf->Ln(2);
        
                // En-têtes du tableau
                $pdf->SetFillColor(240, 240, 240);
                $pdf->SetTextColor(50, 50, 50);
                $pdf->SetFont('helvetica', 'B', 9);
                $pdf->Cell(20, 8, 'Code', 1, 0, 'C', 1);
                $pdf->Cell(70, 8, 'Désignation', 1, 0, 'C', 1);
                $pdf->Cell(40, 8, 'Catégorie', 1, 0, 'C', 1);
                $pdf->Cell(30, 8, 'Stock', 1, 0, 'C', 1);
                $pdf->Cell(20, 8, 'Unité', 1, 1, 'C', 1);
                
                // Contenu du tableau
                $pdf->SetFont('helvetica', '', 9);
                $categorieCourante = '';
                
                foreach ($produits as $produit) {
                    // Si on change de catégorie, afficher un sous-titre
                    if ($categorieCourante != $produit['libelle_categorie']) {
                        $categorieCourante = $produit['libelle_categorie'];
                        $pdf->SetFont('helvetica', 'BI', 9);
                        $pdf->SetFillColor(230, 230, 230);
                        $pdf->Cell(180, 7, $categorieCourante, 1, 1, 'L', 1);
                        $pdf->SetFont('helvetica', '', 9);
                        $pdf->SetFillColor(255, 255, 255);
                    }
                    
                    // Vérifier si on doit passer à une nouvelle page
                    if ($pdf->GetY() > 250) {
                        $pdf->AddPage();
                        
                        // Réafficher les en-têtes du tableau
                        $pdf->SetFillColor(240, 240, 240);
                        $pdf->SetFont('helvetica', 'B', 9);
                        $pdf->Cell(20, 8, 'Code', 1, 0, 'C', 1);
                        $pdf->Cell(70, 8, 'Désignation', 1, 0, 'C', 1);
                        $pdf->Cell(40, 8, 'Catégorie', 1, 0, 'C', 1);
                        $pdf->Cell(30, 8, 'Stock', 1, 0, 'C', 1);
                        $pdf->Cell(20, 8, 'Unité', 1, 1, 'C', 1);
                        $pdf->SetFont('helvetica', '', 9);
                    }
                    
                    $pdf->Cell(20, 6, $produit['code_produit'], 1, 0, 'L');
                    $pdf->Cell(70, 6, $produit['libelle_produit'], 1, 0, 'L');
                    $pdf->Cell(40, 6, $produit['libelle_categorie'], 1, 0, 'L');
                    $pdf->Cell(30, 6, number_format($produit['stock_total'], 2, ',', ' '), 1, 0, 'R');
                    $pdf->Cell(20, 6, $produit['libelle_unite'], 1, 1, 'C');
                }
                
                // Afficher un message si aucun produit trouvé
                if (empty($produits)) {
                    $pdf->SetFont('helvetica', 'I', 10);
                    $pdf->Cell(180, 10, 'Aucun produit trouvé avec les critères sélectionnés.', 1, 1, 'C');
                }
            } 
            elseif ($type_rapport == 'valorise') {
                // Rapport de valorisation du stock
                $sql = "SELECT p.id_produit, p.code_produit, p.libelle_produit, c.libelle_categorie, 
                        u.libelle_unite, SUM(l.quantite_disponible) as stock_total,
                        AVG(l.prix_unitaire_achat) as prix_moyen_achat,
                        SUM(l.quantite_disponible * l.prix_unitaire_achat) as valeur_stock
                        FROM produit p
                        LEFT JOIN categorie_produit c ON p.id_categorie = c.id_categorie
                        LEFT JOIN unite_mesure u ON p.id_unite_stockage = u.id_unite_mesure
                        LEFT JOIN lot_produit l ON p.id_produit = l.id_produit";
                
                // Filtre par dépôt si spécifié
                $whereConditions = ["p.actif = 1"];
                $params = [];
                
                if ($id_depot > 0) {
                    $sql .= " LEFT JOIN detail_entree_stock des ON l.id_detail_entree = des.id_detail_entree
                             LEFT JOIN entree_stock es ON des.id_entree = es.id_entree";
                    $whereConditions[] = "es.id_depot = :id_depot";
                    $params[':id_depot'] = $id_depot;
                }
                
                if ($categorie > 0) {
                    $whereConditions[] = "p.id_categorie = :id_categorie";
                    $params[':id_categorie'] = $categorie;
                }
                
                if (!empty($whereConditions)) {
                    $sql .= " WHERE " . implode(" AND ", $whereConditions);
                }
                
                $sql .= " GROUP BY p.id_produit
                         ORDER BY c.libelle_categorie, p.libelle_produit";
                
                $stmt = $db->prepare($sql);
                foreach ($params as $key => $value) {
                    $stmt->bindValue($key, $value);
                }
                $stmt->execute();
                $produits = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                // Titre de la section
                $pdf->SetTextColor($secondaryColor[0], $secondaryColor[1], $secondaryColor[2]);
                $pdf->SetFont('helvetica', 'B', 12);
                $pdf->Ln(5);
                $pdf->Cell(0, 10, 'VALORISATION DU STOCK', 0, 1, 'L');
                
                // Ligne décorative sous le titre de section
                $pdf->SetLineStyle(array('width' => 0.2, 'cap' => 'butt', 'join' => 'miter', 'dash' => 0, 'color' => $secondaryColor));
                $pdf->Line(20, $pdf->GetY(), 100, $pdf->GetY());
                $pdf->Ln(2);
                
                // En-têtes du tableau
                $pdf->SetFillColor(240, 240, 240);
                $pdf->SetTextColor(50, 50, 50);
                $pdf->SetFont('helvetica', 'B', 8);
                $pdf->Cell(15, 8, 'Code', 1, 0, 'C', 1);
                $pdf->Cell(55, 8, 'Désignation', 1, 0, 'C', 1);
                $pdf->Cell(30, 8, 'Catégorie', 1, 0, 'C', 1);
                $pdf->Cell(20, 8, 'Stock', 1, 0, 'C', 1);
                $pdf->Cell(15, 8, 'Unité', 1, 0, 'C', 1);
                $pdf->Cell(25, 8, 'Prix moyen', 1, 0, 'C', 1);
                $pdf->Cell(30, 8, 'Valeur totale', 1, 1, 'C', 1);
                
                // Contenu du tableau
                $pdf->SetFont('helvetica', '', 8);
                $categorieCourante = '';
                $totalValeur = 0;
                
                foreach ($produits as $produit) {
                    // Si on change de catégorie, afficher un sous-titre
                    if ($categorieCourante != $produit['libelle_categorie']) {
                        if ($categorieCourante != '') {
                            // Afficher le sous-total par catégorie si ce n'est pas la première catégorie
                            $pdf->SetFont('helvetica', 'B', 8);
                            $pdf->SetFillColor(230, 230, 230);
                            $pdf->Cell(135, 7, 'Sous-total ' . $categorieCourante, 1, 0, 'R', 1);
                            $pdf->Cell(45, 7, number_format($sousTotal, 2, ',', ' ') . ' $', 1, 1, 'R', 1);
                        }
                        
                        $categorieCourante = $produit['libelle_categorie'];
                        $sousTotal = 0;
                        
                        $pdf->SetFont('helvetica', 'BI', 8);
                        $pdf->SetFillColor(230, 230, 230);
                        $pdf->Cell(190, 7, $categorieCourante, 1, 1, 'L', 1);
                        $pdf->SetFont('helvetica', '', 8);
                        $pdf->SetFillColor(255, 255, 255);
                    }
                    
                    // Vérifier si on doit passer à une nouvelle page
                    if ($pdf->GetY() > 250) {
                        $pdf->AddPage();
                        
                        // Réafficher les en-têtes du tableau
                        $pdf->SetFillColor(240, 240, 240);
                        $pdf->SetFont('helvetica', 'B', 8);
                        $pdf->Cell(15, 8, 'Code', 1, 0, 'C', 1);
                        $pdf->Cell(55, 8, 'Désignation', 1, 0, 'C', 1);
                        $pdf->Cell(30, 8, 'Catégorie', 1, 0, 'C', 1);
                        $pdf->Cell(20, 8, 'Stock', 1, 0, 'C', 1);
                        $pdf->Cell(15, 8, 'Unité', 1, 0, 'C', 1);
                        $pdf->Cell(25, 8, 'Prix moyen', 1, 0, 'C', 1);
                        $pdf->Cell(30, 8, 'Valeur totale', 1, 1, 'C', 1);
                        $pdf->SetFont('helvetica', '', 8);
                    }
                    
                    $valeurTotale = floatval($produit['valeur_stock']);
                    $sousTotal += $valeurTotale;
                    $totalValeur += $valeurTotale;
                    
                    $pdf->Cell(15, 6, $produit['code_produit'], 1, 0, 'L');
                    $pdf->Cell(55, 6, $produit['libelle_produit'], 1, 0, 'L');
                    $pdf->Cell(30, 6, $produit['libelle_categorie'], 1, 0, 'L');
                    $pdf->Cell(20, 6, number_format($produit['stock_total'], 2, ',', ' '), 1, 0, 'R');
                    $pdf->Cell(15, 6, $produit['libelle_unite'], 1, 0, 'C');
                    $pdf->Cell(25, 6, number_format($produit['prix_moyen_achat'], 2, ',', ' ') . ' $', 1, 0, 'R');
                    $pdf->Cell(30, 6, number_format($valeurTotale, 2, ',', ' ') . ' $', 1, 1, 'R');
                }
                
                // Afficher le dernier sous-total par catégorie
                if (!empty($produits)) {
                    $pdf->SetFont('helvetica', 'B', 8);
                    $pdf->SetFillColor(230, 230, 230);
                    $pdf->Cell(135, 7, 'Sous-total ' . $categorieCourante, 1, 0, 'R', 1);
                    $pdf->Cell(55, 7, number_format($sousTotal, 2, ',', ' ') . ' $', 1, 1, 'R', 1);
                    
                    // Afficher le total général
                    $pdf->SetFont('helvetica', 'B', 10);
                    $pdf->SetFillColor(220, 220, 220);
                    $pdf->Cell(135, 8, 'TOTAL VALEUR DE STOCK', 1, 0, 'R', 1);
                    $pdf->Cell(55, 8, number_format($totalValeur, 2, ',', ' ') . ' $', 1, 1, 'R', 1);
                }
                
                // Afficher un message si aucun produit trouvé
                if (empty($produits)) {
                    $pdf->SetFont('helvetica', 'I', 10);
                    $pdf->Cell(190, 10, 'Aucun produit trouvé avec les critères sélectionnés.', 1, 1, 'C');
                }
            }
            elseif ($type_rapport == 'alerte') {
                // Rapport de stock en alerte
                $sql = "SELECT p.id_produit, p.code_produit, p.libelle_produit, c.libelle_categorie, 
                        u.libelle_unite, SUM(l.quantite_disponible) as stock_total,
                        p.stock_min, p.stock_max
                        FROM produit p
                        LEFT JOIN categorie_produit c ON p.id_categorie = c.id_categorie
                        LEFT JOIN unite_mesure u ON p.id_unite_stockage = u.id_unite_mesure
                        LEFT JOIN lot_produit l ON p.id_produit = l.id_produit";
                
                // Filtre par dépôt si spécifié
                $whereConditions = ["p.actif = 1"];
                $params = [];
                
                if ($id_depot > 0) {
                    $sql .= " LEFT JOIN detail_entree_stock des ON l.id_detail_entree = des.id_detail_entree
                             LEFT JOIN entree_stock es ON des.id_entree = es.id_entree";
                    $whereConditions[] = "es.id_depot = :id_depot";
                    $params[':id_depot'] = $id_depot;
                }
                
                if ($categorie > 0) {
                    $whereConditions[] = "p.id_categorie = :id_categorie";
                    $params[':id_categorie'] = $categorie;
                }
                
                if (!empty($whereConditions)) {
                    $sql .= " WHERE " . implode(" AND ", $whereConditions);
                }
                
                $sql .= " GROUP BY p.id_produit
                         HAVING stock_total <= p.stock_min OR stock_total IS NULL OR stock_total = 0
                         ORDER BY c.libelle_categorie, p.libelle_produit";
                
                $stmt = $db->prepare($sql);
                foreach ($params as $key => $value) {
                    $stmt->bindValue($key, $value);
                }
                $stmt->execute();
                $produits = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                // Titre de la section
                $pdf->SetTextColor($secondaryColor[0], $secondaryColor[1], $secondaryColor[2]);
                $pdf->SetFont('helvetica', 'B', 12);
                $pdf->Ln(5);
                $pdf->Cell(0, 10, 'PRODUITS EN ALERTE DE STOCK', 0, 1, 'L');
                
                // Ligne décorative sous le titre de section
                $pdf->SetLineStyle(array('width' => 0.2, 'cap' => 'butt', 'join' => 'miter', 'dash' => 0, 'color' => $secondaryColor));
                $pdf->Line(20, $pdf->GetY(), 120, $pdf->GetY());
                $pdf->Ln(2);
                
                // En-têtes du tableau
                $pdf->SetFillColor(240, 240, 240);
                $pdf->SetTextColor(50, 50, 50);
                $pdf->SetFont('helvetica', 'B', 9);
                $pdf->Cell(20, 8, 'Code', 1, 0, 'C', 1);
                $pdf->Cell(65, 8, 'Désignation', 1, 0, 'C', 1);
                $pdf->Cell(35, 8, 'Catégorie', 1, 0, 'C', 1);
                $pdf->Cell(20, 8, 'Stock', 1, 0, 'C', 1);
                $pdf->Cell(20, 8, 'Minimum', 1, 0, 'C', 1);
                $pdf->Cell(20, 8, 'Unité', 1, 1, 'C', 1);
                
                // Contenu du tableau
                $pdf->SetFont('helvetica', '', 9);
                $categorieCourante = '';
                
                foreach ($produits as $produit) {
                    // Si on change de catégorie, afficher un sous-titre
                    if ($categorieCourante != $produit['libelle_categorie']) {
                        $categorieCourante = $produit['libelle_categorie'];
                        $pdf->SetFont('helvetica', 'BI', 9);
                        $pdf->SetFillColor(230, 230, 230);
                        $pdf->Cell(180, 7, $categorieCourante, 1, 1, 'L', 1);
                        $pdf->SetFont('helvetica', '', 9);
                        $pdf->SetFillColor(255, 255, 255);
                    }
                    
                    // Vérifier si on doit passer à une nouvelle page
                    if ($pdf->GetY() > 250) {
                        $pdf->AddPage();
                        
                        // Réafficher les en-têtes du tableau
                        $pdf->SetFillColor(240, 240, 240);
                        $pdf->SetFont('helvetica', 'B', 9);
                        $pdf->Cell(20, 8, 'Code', 1, 0, 'C', 1);
                        $pdf->Cell(65, 8, 'Désignation', 1, 0, 'C', 1);
                        $pdf->Cell(35, 8, 'Catégorie', 1, 0, 'C', 1);
                        $pdf->Cell(20, 8, 'Stock', 1, 0, 'C', 1);
                        $pdf->Cell(20, 8, 'Minimum', 1, 0, 'C', 1);
                        $pdf->Cell(20, 8, 'Unité', 1, 1, 'C', 1);
                        $pdf->SetFont('helvetica', '', 9);
                    }
                    
                    // Déterminer la couleur de fond en fonction du niveau de stock
                    $fillColor = false;
                    if ($produit['stock_total'] === null || $produit['stock_total'] == 0) {
                        $pdf->SetFillColor(255, 200, 200); // Rouge clair pour stock nul
                        $fillColor = true;
                    } elseif ($produit['stock_total'] < $produit['stock_min']) {
                        $pdf->SetFillColor(255, 235, 200); // Orange clair pour stock bas
                        $fillColor = true;
                    }
                    
                    $pdf->Cell(20, 6, $produit['code_produit'], 1, 0, 'L', $fillColor);
                    $pdf->Cell(65, 6, $produit['libelle_produit'], 1, 0, 'L', $fillColor);
                    $pdf->Cell(35, 6, $produit['libelle_categorie'], 1, 0, 'L', $fillColor);
                    $pdf->Cell(20, 6, number_format($produit['stock_total'] ?? 0, 2, ',', ' '), 1, 0, 'R', $fillColor);
                    $pdf->Cell(20, 6, number_format($produit['stock_min'], 2, ',', ' '), 1, 0, 'R', $fillColor);
                    $pdf->Cell(20, 6, $produit['libelle_unite'], 1, 1, 'C', $fillColor);
                    
                    // Réinitialiser la couleur de remplissage
                    $pdf->SetFillColor(255, 255, 255);
                }
                
                // Afficher un message si aucun produit trouvé
                if (empty($produits)) {
                    $pdf->SetFont('helvetica', 'I', 10);
                    $pdf->Cell(180, 10, 'Aucun produit en alerte de stock trouvé avec les critères sélectionnés.', 1, 1, 'C');
                }
            }
            elseif ($type_rapport == 'peremption') {
                // Rapport par date de péremption
                $periode = isset($_GET['periode']) ? intval($_GET['periode']) : 30; // Par défaut 30 jours
                $dateLimit = date('Y-m-d', strtotime("+{$periode} days"));
                
                $sql = "SELECT p.id_produit, p.code_produit, p.libelle_produit, c.libelle_categorie, 
                        u.libelle_unite, l.quantite_disponible, l.numero_lot, l.date_peremption
                        FROM produit p
                        LEFT JOIN categorie_produit c ON p.id_categorie = c.id_categorie
                        LEFT JOIN unite_mesure u ON p.id_unite_stockage = u.id_unite_mesure
                        LEFT JOIN lot_produit l ON p.id_produit = l.id_produit";
                
                // Filtre par dépôt si spécifié
                $whereConditions = [
                    "p.actif = 1", 
                    "p.est_peremption_suivi = 1",
                    "l.date_peremption IS NOT NULL",
                    "l.date_peremption <= :date_limite",
                    "l.quantite_disponible > 0"
                ];
                $params = [':date_limite' => $dateLimit];
                
                if ($id_depot > 0) {
                    $sql .= " LEFT JOIN detail_entree_stock des ON l.id_detail_entree = des.id_detail_entree
                             LEFT JOIN entree_stock es ON des.id_entree = es.id_entree";
                    $whereConditions[] = "es.id_depot = :id_depot";
                    $params[':id_depot'] = $id_depot;
                }
                
                if ($categorie > 0) {
                    $whereConditions[] = "p.id_categorie = :id_categorie";
                    $params[':id_categorie'] = $categorie;
                }
                
                if (!empty($whereConditions)) {
                    $sql .= " WHERE " . implode(" AND ", $whereConditions);
                }
                
                $sql .= " ORDER BY l.date_peremption, c.libelle_categorie, p.libelle_produit";
                
                $stmt = $db->prepare($sql);
                foreach ($params as $key => $value) {
                    $stmt->bindValue($key, $value);
                }
                $stmt->execute();
                $lots = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                // Titre de la section
                $pdf->SetTextColor($secondaryColor[0], $secondaryColor[1], $secondaryColor[2]);
                $pdf->SetFont('helvetica', 'B', 12);
                $pdf->Ln(5);
                $pdf->Cell(0, 10, 'PRODUITS PROCHES DE LA DATE DE PÉREMPTION', 0, 1, 'L');
                $pdf->SetFont('helvetica', '', 10);
                $pdf->Cell(0, 6, "Produits expirant dans les {$periode} prochains jours (jusqu'au " . date('d/m/Y', strtotime($dateLimit)) . ")", 0, 1, 'L');
                
                // Ligne décorative sous le titre de section
                $pdf->SetLineStyle(array('width' => 0.2, 'cap' => 'butt', 'join' => 'miter', 'dash' => 0, 'color' => $secondaryColor));
                $pdf->Line(20, $pdf->GetY(), 120, $pdf->GetY());
                $pdf->Ln(2);
                
                // En-têtes du tableau
                $pdf->SetFillColor(240, 240, 240);
                $pdf->SetTextColor(50, 50, 50);
                $pdf->SetFont('helvetica', 'B', 9);
                $pdf->Cell(20, 8, 'Code', 1, 0, 'C', 1);
                $pdf->Cell(55, 8, 'Désignation', 1, 0, 'C', 1);
                $pdf->Cell(25, 8, 'N° Lot', 1, 0, 'C', 1);
                $pdf->Cell(25, 8, 'Péremption', 1, 0, 'C', 1);
                $pdf->Cell(20, 8, 'Restant', 1, 0, 'C', 1);
                $pdf->Cell(15, 8, 'Unité', 1, 0, 'C', 1);
                $pdf->Cell(20, 8, 'Jours', 1, 1, 'C', 1);
                
                // Contenu du tableau
                $pdf->SetFont('helvetica', '', 9);
                $dateCourante = '';
                
                foreach ($lots as $lot) {
                    // Calculer le nombre de jours avant péremption
                    $aujourdhui = new DateTime();
                    $datePeremption = new DateTime($lot['date_peremption']);
                    $joursRestants = $aujourdhui->diff($datePeremption)->format('%r%a'); // %r pour garder le signe (+ ou -)
                    
                    // Grouper par intervalles de dates (expiré, moins de 7 jours, moins de 30 jours, etc.)
                    $intervalleDate = '';
                    if ($joursRestants < 0) {
                        $intervalleDate = 'PRODUITS EXPIRÉS';
                    } elseif ($joursRestants <= 7) {
                        $intervalleDate = 'EXPIRATION SOUS 7 JOURS';
                    } elseif ($joursRestants <= 15) {
                        $intervalleDate = 'EXPIRATION ENTRE 8 ET 15 JOURS';
                    } elseif ($joursRestants <= 30) {
                        $intervalleDate = 'EXPIRATION ENTRE 16 ET 30 JOURS';
                    } else {
                        $intervalleDate = 'EXPIRATION APRÈS 30 JOURS';
                    }
                    
                    // Si on change d'intervalle de date, afficher un sous-titre
                    if ($dateCourante != $intervalleDate) {
                        $dateCourante = $intervalleDate;
                        $pdf->SetFont('helvetica', 'BI', 9);
                        $pdf->SetFillColor(230, 230, 230);
                        $pdf->Cell(180, 7, $dateCourante, 1, 1, 'L', 1);
                        $pdf->SetFont('helvetica', '', 9);
                        $pdf->SetFillColor(255, 255, 255);
                    }
                    
                    // Vérifier si on doit passer à une nouvelle page
                    if ($pdf->GetY() > 250) {
                        $pdf->AddPage();
                        
                        // Réafficher les en-têtes du tableau
                        $pdf->SetFillColor(240, 240, 240);
                        $pdf->SetFont('helvetica', 'B', 9);
                        $pdf->Cell(20, 8, 'Code', 1, 0, 'C', 1);
                        $pdf->Cell(55, 8, 'Désignation', 1, 0, 'C', 1);
                        $pdf->Cell(25, 8, 'N° Lot', 1, 0, 'C', 1);
                        $pdf->Cell(25, 8, 'Péremption', 1, 0, 'C', 1);
                        $pdf->Cell(20, 8, 'Restant', 1, 0, 'C', 1);
                        $pdf->Cell(15, 8, 'Unité', 1, 0, 'C', 1);
                        $pdf->Cell(20, 8, 'Jours', 1, 1, 'C', 1);
                        $pdf->SetFont('helvetica', '', 9);
                    }
                    
                    // Déterminer la couleur de fond en fonction des jours restants
                    $fillColor = false;
                    if ($joursRestants < 0) {
                        $pdf->SetFillColor(255, 200, 200); // Rouge clair pour produits expirés
                        $fillColor = true;
                    } elseif ($joursRestants <= 7) {
                        $pdf->SetFillColor(255, 235, 200); // Orange clair pour expiration proche
                        $fillColor = true;
                    } elseif ($joursRestants <= 15) {
                        $pdf->SetFillColor(255, 255, 200); // Jaune clair pour expiration sous 15 jours
                        $fillColor = true;
                    }
                    
                    $pdf->Cell(20, 6, $lot['code_produit'], 1, 0, 'L', $fillColor);
                    $pdf->Cell(55, 6, $lot['libelle_produit'], 1, 0, 'L', $fillColor);
                    $pdf->Cell(25, 6, $lot['numero_lot'], 1, 0, 'C', $fillColor);
                    $pdf->Cell(25, 6, date('d/m/Y', strtotime($lot['date_peremption'])), 1, 0, 'C', $fillColor);
                    $pdf->Cell(20, 6, number_format($lot['quantite_disponible'], 2, ',', ' '), 1, 0, 'R', $fillColor);
                    $pdf->Cell(15, 6, $lot['libelle_unite'], 1, 0, 'C', $fillColor);
                    
                    // Afficher les jours restants avec une couleur spécifique
                    $pdf->SetFont('helvetica', 'B', 9);
                    if ($joursRestants < 0) {
                        $pdf->SetTextColor(255, 0, 0); // Rouge pour expiré
                        $pdf->Cell(20, 6, 'Expiré', 1, 1, 'C', $fillColor);
                    } else {
                        if ($joursRestants <= 7) {
                            $pdf->SetTextColor(255, 0, 0); // Rouge pour urgent
                        } elseif ($joursRestants <= 15) {
                            $pdf->SetTextColor(255, 128, 0); // Orange pour attention
                        } else {
                            $pdf->SetTextColor(0, 128, 0); // Vert pour normal
                        }
                        $pdf->Cell(20, 6, $joursRestants . ' jours', 1, 1, 'C', $fillColor);
                    }
                    
                    // Réinitialiser les couleurs
                    $pdf->SetTextColor(50, 50, 50);
                    $pdf->SetFillColor(255, 255, 255);
                    $pdf->SetFont('helvetica', '', 9);
                }
                
                // Afficher un message si aucun produit trouvé
                if (empty($lots)) {
                    $pdf->SetFont('helvetica', 'I', 10);
                    $pdf->Cell(180, 10, 'Aucun produit proche de la date de péremption trouvé.', 1, 1, 'C');
                }
            }
            
            // Afficher les informations sur l'utilisateur qui a généré le rapport
            $pdf->Ln(10);
            $pdf->SetFont('helvetica', 'I', 8);
            $pdf->Cell(0, 6, 'Rapport généré par: ' . ($_SESSION['nomUser'] ?? 'Système') . ' le ' . date('d/m/Y à H:i:s'), 0, 1, 'L');
            
            // Nom du fichier PDF
            $filename = strtolower(str_replace(' ', '_', $titre)) . '_' . date('Y-m-d') . '.pdf';
            
            // Générer le PDF
            $pdf->Output($filename, 'I');
            exit();
            
        } catch (Exception $e) {
            // En cas d'erreur, afficher un message
            echo "<script>
                Swal.fire({
                    title: 'Erreur',
                    text: '" . addslashes($e->getMessage()) . "',
                    icon: 'error',
                    confirmButtonText: 'OK'
                }).then(() => {
                    window.history.back();
                });
            </script>";
            exit;
        }
        
        