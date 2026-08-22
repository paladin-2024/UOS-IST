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
    $idEntree = intval($_GET['id']);

    try {
        // Récupérer les informations de l'entrée de stock
        $queryEntree = "SELECT e.*, d.libelle_depot, u.\"nomUser\" as user_creation,
                        v.\"nomUser\" as user_validation
                        FROM entree_stock e
                        LEFT JOIN depot d ON e.id_depot = d.id_depot
                        LEFT JOIN t_users u ON e.id_user_creation = u.\"idUser\"
                        LEFT JOIN t_users v ON e.id_user_validation = v.\"idUser\"
                        WHERE e.id_entree = :id_entree";
        $stmtEntree = $db->prepare($queryEntree);
        $stmtEntree->bindParam(':id_entree', $idEntree, PDO::PARAM_INT);
        $stmtEntree->execute();
        $entree = $stmtEntree->fetch(PDO::FETCH_ASSOC);

        if (!$entree) {
            throw new Exception("Entrée de stock introuvable");
        }

        // Récupérer les détails des produits
        $queryDetails = "SELECT d.*, p.code_produit, p.libelle_produit, u.code_unite, u.symbole_unite,
                        l.numero_lot, l.date_peremption
                        FROM detail_entree_stock d
                        LEFT JOIN produit p ON d.id_produit = p.id_produit
                        LEFT JOIN unite_mesure u ON p.id_unite_stockage = u.id_unite
                        LEFT JOIN lot_produit l ON d.id_detail_entree = l.id_detail_entree
                        WHERE d.id_entree = :id_entree
                        ORDER BY p.libelle_produit";
        $stmtDetails = $db->prepare($queryDetails);
        $stmtDetails->bindParam(':id_entree', $idEntree, PDO::PARAM_INT);
        $stmtDetails->execute();
        $details = $stmtDetails->fetchAll(PDO::FETCH_ASSOC);

        // Récupérer les informations de l'institution
        $queryConfig = "SELECT * FROM configuration_universite LIMIT 1";
        $stmtConfig = $db->prepare($queryConfig);
        $stmtConfig->execute();
        $configInstitution = $stmtConfig->fetch(PDO::FETCH_ASSOC);

        // Regrouper les détails par produit
        $produitsGroupes = [];
        foreach ($details as $detail) {
            $idProduit = $detail['id_produit'];
            if (!isset($produitsGroupes[$idProduit])) {
                $produitsGroupes[$idProduit] = [
                    'info_produit' => [
                        'id_produit' => $detail['id_produit'],
                        'code_produit' => $detail['code_produit'],
                        'libelle_produit' => $detail['libelle_produit'],
                        'symbole_unite' => $detail['symbole_unite'],
                        'quantite_totale' => 0,
                        'montant_total' => 0
                    ],
                    'lots' => []
                ];
            }

            // Ajouter le lot à ce produit
            $produitsGroupes[$idProduit]['lots'][] = [
                'numero_lot' => $detail['numero_lot'],
                'date_peremption' => $detail['date_peremption'],
                'quantite' => $detail['quantite'],
                'prix_unitaire' => $detail['prix_unitaire'],
                'montant_total' => $detail['montant_total']
            ];

            // Cumuler les quantités et montants
            $produitsGroupes[$idProduit]['info_produit']['quantite_totale'] += $detail['quantite'];
            $produitsGroupes[$idProduit]['info_produit']['montant_total'] += $detail['montant_total'];
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
        $pdf->SetTitle('Bon d\'entrée en stock');
        $pdf->SetSubject('Bon d\'entrée en stock');
        $pdf->SetKeywords('Stock, Entrée, Bon');

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

        function generatePage($pdf, $entree, $produitsGroupes, $configInstitution, $primaryColor, $secondaryColor, $accentColor, $isDuplicata = false)
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
            $pdf->Cell(0, 10, 'BON D\'ENTRÉE EN STOCK', 0, 1, 'C', 1);

            $pdf->SetTextColor(80, 80, 80);
            $pdf->SetFont('helvetica', 'B', 10);
            $pdf->Cell(0, 8, 'N° ' . $entree['numero_entree'], 0, 1, 'C');

            // Préparer les données du QR Code
            $qrCodeData = "BON D'ENTRÉE EN STOCK\n";
            $qrCodeData .= "N°: " . $entree['numero_entree'] . "\n";
            $qrCodeData .= "Date: " . date('d/m/Y', strtotime($entree['date_entree'])) . "\n";
            $qrCodeData .= "Dépôt: " . $entree['libelle_depot'] . "\n";
            $qrCodeData .= "Type: " . $entree['type_entree'] . "\n";
            $qrCodeData .= "Document généré le: " . date('d/m/Y H:i:s') . "\n";
            $qrCodeData .= $configInstitution['site_web'] ?? '';

            $pdf->Ln(-5);

            // MODIFICATION 1: Compacter les informations de l'entrée en 2 colonnes au lieu de 4
    $pdf->SetTextColor($secondaryColor[0], $secondaryColor[1], $secondaryColor[2]);
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->Cell(0, 8, 'INFORMATIONS DE L\'ENTRÉE', 0, 1, 'L');

    // Ligne décorative sous le titre de section
    $pdf->SetLineStyle(array('width' => 0.2, 'cap' => 'butt', 'join' => 'miter', 'dash' => 0, 'color' => $secondaryColor));
    $pdf->Line(20, $pdf->GetY(), 100, $pdf->GetY());
    $pdf->Ln(1);

    // Format plus compact - colonne gauche
    $pdf->SetTextColor(60, 60, 60);
    $pdf->SetFont('helvetica', 'B', 9);
    $pdf->Cell(30, 5, 'N° Entrée:', 0, 0, 'L');
    $pdf->SetFont('helvetica', '', 9);
    $pdf->Cell(65, 5, $entree['numero_entree'], 0, 0, 'L');
    
    // Colonne droite
    $pdf->SetFont('helvetica', 'B', 9);
    $pdf->Cell(30, 5, 'Date entrée:', 0, 0, 'L');
    $pdf->SetFont('helvetica', '', 9);
    $pdf->Cell(0, 5, date('d/m/Y', strtotime($entree['date_entree'])), 0, 1, 'L');

    // Deuxième ligne
    $pdf->SetFont('helvetica', 'B', 9);
    $pdf->Cell(30, 5, 'Dépôt:', 0, 0, 'L');
    $pdf->SetFont('helvetica', '', 9);
    $pdf->Cell(65, 5, $entree['libelle_depot'], 0, 0, 'L');
    
    $pdf->SetFont('helvetica', 'B', 9);
    $pdf->Cell(30, 5, 'Type d\'entrée:', 0, 0, 'L');
    $pdf->SetFont('helvetica', '', 9);
    $pdf->Cell(0, 5, $entree['type_entree'] . ' | Statut: ' . $entree['etat'], 0, 1, 'L');

    // Troisième ligne
    $pdf->SetFont('helvetica', 'B', 9);
    $pdf->Cell(30, 5, 'Référence:', 0, 0, 'L');
    $pdf->SetFont('helvetica', '', 9);
    $pdf->Cell(0, 5, $entree['reference_document'] ?: '-', 0, 1, 'L');

    // Observation si existante
    if (!empty($entree['observation'])) {
        $pdf->SetFont('helvetica', 'B', 9);
        $pdf->Cell(30, 5, 'Observation:', 0, 0, 'L');
        $pdf->SetFont('helvetica', '', 9);
        $pdf->MultiCell(0, 5, $entree['observation'], 0, 'L');
    }

    $pdf->Ln(2);

            // Titre du tableau combiné de produits et lots
            $pdf->SetTextColor($secondaryColor[0], $secondaryColor[1], $secondaryColor[2]);
            $pdf->SetFont('helvetica', 'B', 12);
            $pdf->Cell(0, 10, 'DÉTAILS DES PRODUITS ET LOTS', 0, 1, 'L');

            // Ligne décorative sous le titre de section
            $pdf->SetLineStyle(array('width' => 0.2, 'cap' => 'butt', 'join' => 'miter', 'dash' => 0, 'color' => $secondaryColor));
            $pdf->Line(20, $pdf->GetY(), 100, $pdf->GetY());
            $pdf->Ln(2);

            // Tableau combiné des produits et lots
            $pdf->SetTextColor(0, 0, 0);
            $pdf->SetFillColor(240, 240, 240);
            $pdf->SetFont('helvetica', 'B', 8);

            // Entêtes du tableau combiné - Suppression des colonnes Numéro Lot et Date Péremption
            $pdf->Cell(8, 7, 'N°', 1, 0, 'C', 1);
            $pdf->Cell(20, 7, 'Code', 1, 0, 'C', 1);
            $pdf->Cell(70, 7, 'Désignation', 1, 0, 'C', 1);
            $pdf->Cell(20, 7, 'Quantité', 1, 0, 'C', 1);
            $pdf->Cell(25, 7, 'Prix Unit.', 1, 0, 'C', 1);
            $pdf->Cell(30, 7, 'Montant Total', 1, 1, 'C', 1);

            // Lignes du tableau
            $pdf->SetFont('helvetica', '', 8);
            $pdf->SetFillColor(255, 255, 255);
            $index = 1;
            $totalGeneral = 0;

            foreach ($produitsGroupes as $idProduit => $produitData) {
                $infoProduit = $produitData['info_produit'];
                $lots = $produitData['lots'];
                $totalGeneral += $infoProduit['montant_total'];

                // Ligne principale du produit avec fond légèrement coloré - pour tous les produits
                $pdf->SetFillColor(245, 245, 245);
                $pdf->Cell(8, 6, $index, 1, 0, 'C', 1);
                $pdf->Cell(20, 6, $infoProduit['code_produit'], 1, 0, 'L', 1);
                $pdf->Cell(70, 6, $infoProduit['libelle_produit'], 1, 0, 'L', 1);
                $pdf->Cell(20, 6, number_format($infoProduit['quantite_totale'], 2) . ' ' . $infoProduit['symbole_unite'], 1, 0, 'R', 1);
                $pdf->Cell(25, 6, count($lots) > 1 ? "Prix divers" : number_format($lots[0]['prix_unitaire'], 2) . ' USD', 1, 0, 'R', 1);
                $pdf->Cell(30, 6, number_format($infoProduit['montant_total'], 2) . ' USD', 1, 1, 'R', 1);

                // Sous-lignes pour chaque lot, avec détail intégré - pour tous les produits
                $pdf->SetFillColor(255, 255, 255);
                foreach ($lots as $i => $lot) {
                    $datePeremption = $lot['date_peremption'] ? " (expire le " . date('d/m/Y', strtotime($lot['date_peremption'])) . ")" : "";

                    $pdf->Cell(8, 5, "", 0, 0, 'C', 0);
                    $pdf->Cell(20, 5, "", 0, 0, 'L', 0);
                    $pdf->Cell(70, 5, "Lot " . $lot['numero_lot'] . $datePeremption, 1, 0, 'L', 0);
                    $pdf->Cell(20, 5, number_format($lot['quantite'], 2) . ' ' . $infoProduit['symbole_unite'], 1, 0, 'R', 0);
                    $pdf->Cell(25, 5, number_format($lot['prix_unitaire'], 2) . ' USD', 1, 0, 'R', 0);
                    $pdf->Cell(30, 5, number_format($lot['montant_total'], 2) . ' USD', 1, 1, 'R', 0);
                }

                $index++;
            }

            // Total général
            $pdf->SetFont('helvetica', 'B', 8);
            $pdf->Cell(118, 7, 'TOTAL GÉNÉRAL', 1, 0, 'R', 0);
            $pdf->Cell(55, 7, number_format($totalGeneral, 2) . ' USD', 1, 1, 'R', 0);


            $pdf->Ln(5);

    // MODIFICATION 2: Tableau des signatures avec trois colonnes incluant le transporteur
    $pdf->SetTextColor($secondaryColor[0], $secondaryColor[1], $secondaryColor[2]);
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->Cell(0, 8, 'SIGNATURES', 0, 1, 'L');

    // Ligne décorative sous le titre de section
    $pdf->SetLineStyle(array('width' => 0.2, 'cap' => 'butt', 'join' => 'miter', 'dash' => 0, 'color' => $secondaryColor));
    $pdf->Line(20, $pdf->GetY(), 70, $pdf->GetY());
    $pdf->Ln(2);

    // Tableau de signatures
    $pdf->SetFillColor(245, 245, 245);
    $pdf->SetTextColor(60, 60, 60);
    $pdf->SetFont('helvetica', 'B', 9);
    
    // En-têtes du tableau (3 colonnes égales)
    $colWidth = 56;
    $pdf->Cell($colWidth, 6, 'Préparé par', 1, 0, 'C', 1);
    $pdf->Cell($colWidth, 6, 'Transporteur', 1, 0, 'C', 1);
    $pdf->Cell($colWidth, 6, 'Approuvé par', 1, 1, 'C', 1);
    
    // Contenu du tableau - Ligne 1 (noms)
    $pdf->SetFont('helvetica', '', 9);
    $pdf->Cell($colWidth, 10, $entree['user_creation'] ?? 'N/A', 1, 0, 'C');
    $pdf->Cell($colWidth, 10, isset($entree['transporteur']) ? $entree['transporteur'] : '........................', 1, 0, 'C');
    $pdf->Cell($colWidth, 10, $entree['user_validation'] ?? 'N/A', 1, 1, 'C');
    
    // Ligne 2 (dates)
    $pdf->SetFont('helvetica', 'I', 8);
    $pdf->Cell($colWidth, 5, 'Date: ' . date('d/m/Y', strtotime($entree['date_creation'])), 1, 0, 'C');
    $pdf->Cell($colWidth, 5, 'Date: ........................', 1, 0, 'C');
    $pdf->Cell($colWidth, 5, 'Date: ' . ($entree['date_validation'] ? date('d/m/Y', strtotime($entree['date_validation'])) : 'N/A'), 1, 1, 'C');

    $pdf->Ln(5);


            // MODIFICATION 3: QR code plus petit (25x25mm au lieu de 30x30mm)
    $startY = $pdf->GetY();
    
    // QR Code à gauche (taille réduite)
    $pdf->SetFont('helvetica', 'I', 7);
    $pdf->SetXY(20, $startY);
    $pdf->Cell(25, 5, 'Code de vérification', 0, 0, 'C');
    $pdf->write2DBarcode($qrCodeData, 'QRCODE,M', 20, $startY + 5, 25, 25);

    // Encadré pour le cachet à droite
    $pdf->SetLineStyle(array('width' => 0.3, 'cap' => 'butt', 'join' => 'miter', 'dash' => 0, 'color' => array(150, 150, 150)));
    $pdf->RoundedRect(140, $startY, 40, 25, 2, '1111', null, array('color' => array(150, 150, 150)));

    $pdf->SetXY(140, $startY + 10);
    $pdf->SetFont('helvetica', 'I', 8);
    $pdf->Cell(40, 5, 'Cachet', 0, 1, 'C');
        }

    

        // Ajouter une page
        $pdf->AddPage();

        generatePage($pdf, $entree, $produitsGroupes, $configInstitution, $primaryColor, $secondaryColor, $accentColor, $isDuplicata = false);

        if(isset($_GET['format']) && $_GET['format'] == 'compact'){
            $pdf->AddPage();
            generatePage($pdf, $entree, $produitsGroupes, $configInstitution, $primaryColor, $secondaryColor, $accentColor, $isDuplicata = false);
        }
        


        // Outputting the PDF
        $pdf->Output('Bon_Entree_Stock_' . $entree['numero_entree'] . '.pdf', 'I');
    } catch (Exception $e) {
        die("Erreur: " . $e->getMessage());
    }
} else {
    die("ID d'entrée de stock non spécifié");
}
