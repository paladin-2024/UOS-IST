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
    $idTransfert = intval($_GET['id']);

    try {
        // Récupérer les informations du transfert de stock
        $queryTransfert = "SELECT t.*, 
                           d1.libelle_depot as depot_source_libelle, 
                           d2.libelle_depot as depot_destination_libelle,
                           u.nomUser as user_creation,
                           v.nomUser as user_validation
                           FROM transfert_stock t
                           LEFT JOIN depot d1 ON t.id_depot_source = d1.id_depot
                           LEFT JOIN depot d2 ON t.id_depot_destination = d2.id_depot
                           LEFT JOIN t_users u ON t.id_user_creation = u.idUser
                           LEFT JOIN t_users v ON t.id_user_validation = v.idUser
                           WHERE t.id_transfert = :id_transfert";
        $stmtTransfert = $db->prepare($queryTransfert);
        $stmtTransfert->bindParam(':id_transfert', $idTransfert, PDO::PARAM_INT);
        $stmtTransfert->execute();
        $transfert = $stmtTransfert->fetch(PDO::FETCH_ASSOC);

        if (!$transfert) {
            throw new Exception("Transfert de stock introuvable");
        }

        // Récupérer les détails des produits transférés
        $queryDetails = "SELECT dt.*, p.code_produit, p.libelle_produit, 
                        um.symbole_unite,
                        l.numero_lot, l.date_peremption, l.prix_unitaire_achat
                        FROM detail_transfert_stock dt
                        LEFT JOIN produit p ON dt.id_produit = p.id_produit
                        LEFT JOIN lot_produit l ON dt.id_lot = l.id_lot
                        LEFT JOIN unite_mesure um ON p.id_unite_stockage = um.id_unite
                        WHERE dt.id_transfert = :id_transfert
                        ORDER BY p.libelle_produit";
        $stmtDetails = $db->prepare($queryDetails);
        $stmtDetails->bindParam(':id_transfert', $idTransfert, PDO::PARAM_INT);
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
                        'quantite_totale' => 0
                    ],
                    'lots' => []
                ];
            }

            // Ajouter le lot à ce produit
            $produitsGroupes[$idProduit]['lots'][] = [
                'numero_lot' => $detail['numero_lot'],
                'date_peremption' => $detail['date_peremption'],
                'quantite' => $detail['quantite'],
                'prix_unitaire' => $detail['prix_unitaire_achat'] ?? 0
            ];

            // Cumuler les quantités
            $produitsGroupes[$idProduit]['info_produit']['quantite_totale'] += $detail['quantite'];
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
        $pdf->SetTitle('Bon de transfert de stock');
        $pdf->SetSubject('Bon de transfert de stock');
        $pdf->SetKeywords('Stock, Transfert, Bon');

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

        function generatePage($pdf, $transfert, $produitsGroupes, $configInstitution, $primaryColor, $secondaryColor, $accentColor, $isDuplicata = false)
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
            $pdf->Cell(0, 10, 'BON DE TRANSFERT DE STOCK', 0, 1, 'C', 1);

            $pdf->SetTextColor(80, 80, 80);
            $pdf->SetFont('helvetica', 'B', 10);
            $pdf->Cell(0, 8, 'N° ' . $transfert['numero_transfert'], 0, 1, 'C');

            // Ajouter "DUPLICATA" en filigrane si nécessaire
            if ($isDuplicata) {
                $pdf->SetFont('helvetica', 'B', 70);
                $pdf->SetTextColor(255, 0, 0, 20); // Rouge avec transparence
                $pdf->StartTransform();
                $pdf->Rotate(45, $pdf->getPageWidth() / 2, $pdf->getPageHeight() / 2);
                $pdf->SetXY(0, $pdf->getPageHeight() / 2 - 20);
                $pdf->Cell($pdf->getPageWidth(), 40, 'DUPLICATA', 0, 1, 'C');
                $pdf->StopTransform();
                $pdf->SetTextColor(80, 80, 80); // Retour à la couleur normale
            }

            $pdf->Ln(2);

            // Informations du transfert en 2 colonnes
            $pdf->SetTextColor($secondaryColor[0], $secondaryColor[1], $secondaryColor[2]);
            $pdf->SetFont('helvetica', 'B', 12);
            $pdf->Cell(0, 8, 'INFORMATIONS DU TRANSFERT', 0, 1, 'L');

            // Ligne décorative sous le titre de section
            $pdf->SetLineStyle(array('width' => 0.2, 'cap' => 'butt', 'join' => 'miter', 'dash' => 0, 'color' => $secondaryColor));
            $pdf->Line(20, $pdf->GetY(), 100, $pdf->GetY());
            $pdf->Ln(1);

            // Format compact pour les informations du transfert
            $pdf->SetTextColor(60, 60, 60);
            $pdf->SetFont('helvetica', 'B', 9);
            $pdf->Cell(30, 5, 'Date du transfert:', 0, 0, 'L');
            $pdf->SetFont('helvetica', '', 9);
            $pdf->Cell(65, 5, date('d/m/Y', strtotime($transfert['date_transfert'])), 0, 0, 'L');

            $pdf->SetFont('helvetica', 'B', 9);
            $pdf->Cell(30, 5, 'État:', 0, 0, 'L');
            $pdf->SetFont('helvetica', '', 9);
            $pdf->Cell(0, 5, $transfert['etat'], 0, 1, 'L');

            // Dépôts source et destination
            $pdf->SetFont('helvetica', 'B', 9);
            $pdf->Cell(30, 5, 'Dépôt source:', 0, 0, 'L');
            $pdf->SetFont('helvetica', '', 9);
            $pdf->Cell(65, 5, $transfert['depot_source_libelle'], 0, 0, 'L');

            $pdf->SetFont('helvetica', 'B', 9);
            $pdf->Cell(30, 5, 'Dépôt destination:', 0, 0, 'L');
            $pdf->SetFont('helvetica', '', 9);
            $pdf->Cell(0, 5, $transfert['depot_destination_libelle'], 0, 1, 'L');

            // Observation (si présente)
            if (!empty($transfert['observation'])) {
                $pdf->SetFont('helvetica', 'B', 9);
                $pdf->Cell(30, 5, 'Observation:', 0, 0, 'L');
                $pdf->SetFont('helvetica', '', 9);
                $pdf->MultiCell(0, 5, $transfert['observation'], 0, 'L');
            }

            $pdf->Ln(3);

            // Titre du tableau des produits
            $pdf->SetTextColor($secondaryColor[0], $secondaryColor[1], $secondaryColor[2]);
            $pdf->SetFont('helvetica', 'B', 12);
            $pdf->Cell(0, 8, 'PRODUITS TRANSFÉRÉS', 0, 1, 'L');

            // Ligne décorative sous le titre de section
            $pdf->SetLineStyle(array('width' => 0.2, 'cap' => 'butt', 'join' => 'miter', 'dash' => 0, 'color' => $secondaryColor));
            $pdf->Line(20, $pdf->GetY(), 100, $pdf->GetY());
            $pdf->Ln(2);

            // Tableau des produits
            $pdf->SetTextColor(0, 0, 0);
            $pdf->SetFillColor(240, 240, 240);
            $pdf->SetFont('helvetica', 'B', 8);

            // En-têtes du tableau
            $pdf->Cell(8, 7, 'N°', 1, 0, 'C', 1);
            $pdf->Cell(25, 7, 'Code', 1, 0, 'C', 1);
            $pdf->Cell(67, 7, 'Désignation', 1, 0, 'C', 1);
            $pdf->Cell(20, 7, 'Lot', 1, 0, 'C', 1);
            $pdf->Cell(20, 7, 'Exp.', 1, 0, 'C', 1);
            $pdf->Cell(30, 7, 'Quantité', 1, 1, 'C', 1);

            // Lignes du tableau pour chaque produit et ses lots
            $pdf->SetFont('helvetica', '', 8);
            $index = 1;

            foreach ($produitsGroupes as $groupe) {
                $infoProduit = $groupe['info_produit'];
                $lots = $groupe['lots'];

                // Si produit a plusieurs lots, fusionner les cellules et montrer total
                if (count($lots) > 1) {
                    // Ligne avec regroupement pour le produit
                    $pdf->SetFont('helvetica', 'B', 8);
                    $pdf->SetFillColor(245, 245, 245);

                    $pdf->Cell(8, 7, $index, 1, 0, 'C', 1);
                    $pdf->Cell(25, 7, $infoProduit['code_produit'], 1, 0, 'L', 1);
                    $pdf->Cell(67, 7, $infoProduit['libelle_produit'], 1, 0, 'L', 1);
                    $pdf->Cell(40, 7, 'TOTAL ' . count($lots) . ' lots', 1, 0, 'C', 1);
                    $pdf->Cell(30, 7, number_format($infoProduit['quantite_totale'], 2) . ' ' . $infoProduit['symbole_unite'], 1, 1, 'R', 1);

                    // Détails des lots
                    $pdf->SetFont('helvetica', '', 8);
                    $pdf->SetFillColor(255, 255, 255);

                    foreach ($lots as $key => $lot) {
                        $pdf->Cell(8, 6, '', 1, 0, 'C', 0); // Cellule vide
                        $pdf->Cell(25, 6, '', 1, 0, 'L', 0); // Cellule vide
                        $pdf->Cell(67, 6, '   → ' . $infoProduit['libelle_produit'], 1, 0, 'L', 0);
                        $pdf->Cell(20, 6, $lot['numero_lot'], 1, 0, 'C', 0);
                        $pdf->Cell(20, 6, !empty($lot['date_peremption']) ? date('d/m/Y', strtotime($lot['date_peremption'])) : 'N/A', 1, 0, 'C', 0);
                        $pdf->Cell(30, 6, number_format($lot['quantite'], 2) . ' ' . $infoProduit['symbole_unite'], 1, 1, 'R', 0);
                    }
                } else {
                    // Un seul lot, afficher simplement
                    $lot = $lots[0];

                    $pdf->Cell(8, 7, $index, 1, 0, 'C', 0);
                    $pdf->Cell(25, 7, $infoProduit['code_produit'], 1, 0, 'L', 0);
                    $pdf->Cell(67, 7, $infoProduit['libelle_produit'], 1, 0, 'L', 0);
                    $pdf->Cell(20, 7, $lot['numero_lot'], 1, 0, 'C', 0);
                    $pdf->Cell(20, 7, !empty($lot['date_peremption']) ? date('d/m/Y', strtotime($lot['date_peremption'])) : 'N/A', 1, 0, 'C', 0);
                    $pdf->Cell(30, 7, number_format($lot['quantite'], 2) . ' ' . $infoProduit['symbole_unite'], 1, 1, 'R', 0);
                }

                $index++;
            }

            // Afficher le total général
            $totalGeneral = 0;
            foreach ($produitsGroupes as $groupe) {
                $totalGeneral += count($groupe['lots']);
            }

            $pdf->SetFont('helvetica', 'B', 8);
            $pdf->Cell(100, 7, 'TOTAL GÉNÉRAL:', 1, 0, 'R', 1);
            $pdf->Cell(40, 7, $totalGeneral . ' articles', 1, 0, 'C', 1);
            $pdf->Cell(30, 7, count($produitsGroupes) . ' produits', 1, 1, 'C', 1);

            $pdf->Ln(5);

            // Signatures
            $pdf->SetFont('helvetica', 'B', 10);
            $pdf->Cell(0, 7, 'SIGNATURES', 0, 1, 'L');

            $pdf->SetLineStyle(array('width' => 0.2, 'cap' => 'butt', 'join' => 'miter', 'dash' => 0, 'color' => $secondaryColor));
            $pdf->Line(20, $pdf->GetY(), 80, $pdf->GetY());
            $pdf->Ln(2);

            $pdf->SetFillColor(245, 245, 245);
            $pdf->SetTextColor(60, 60, 60);
            $pdf->SetFont('helvetica', 'B', 9);

            // En-têtes du tableau des signatures
            $colWidth = 85;
            $pdf->Cell($colWidth / 2, 6, 'Dépôt expéditeur', 1, 0, 'C', 1);
            $pdf->Cell($colWidth / 2, 6, 'Responsable expédition', 1, 0, 'C', 1);
            $pdf->Cell($colWidth / 2, 6, 'Dépôt réceptionnaire', 1, 0, 'C', 1);
            $pdf->Cell($colWidth / 2, 6, 'Responsable réception', 1, 1, 'C', 1);

            // Espaces pour les signatures
            $pdf->SetFont('helvetica', '', 9);
            $pdf->Cell($colWidth / 2, 15, '', 1, 0, 'C');
            $pdf->Cell($colWidth / 2, 15, '', 1, 0, 'C');
            $pdf->Cell($colWidth / 2, 15, '', 1, 0, 'C');
            $pdf->Cell($colWidth / 2, 15, '', 1, 1, 'C');

            // Espaces pour les noms
            $pdf->Cell($colWidth / 2, 8, 'Date: ___/___/_____', 1, 0, 'L');
            $pdf->Cell($colWidth / 2, 8, 'Nom: ________________', 1, 0, 'L');
            $pdf->Cell($colWidth / 2, 8, 'Date: ___/___/_____', 1, 0, 'L');
            $pdf->Cell($colWidth / 2, 8, 'Nom: ________________', 1, 1, 'L');

            // Code QR pour validation
            $pdf->Ln(5);
            $style = array(
                'border' => 0,
                'vpadding' => 'auto',
                'hpadding' => 'auto',
                'fgcolor' => array(0, 0, 0),
                'bgcolor' => false,
                'module_width' => 1,
                'module_height' => 1
            );

            // Générer le contenu du QR code (informations du transfert)
            $qrContent = "TRANSFERT: " . $transfert['numero_transfert'] . "\n";
            $qrContent .= "DATE: " . $transfert['date_transfert'] . "\n";
            $qrContent .= "SOURCE: " . $transfert['depot_source_libelle'] . "\n";
            $qrContent .= "DESTINATION: " . $transfert['depot_destination_libelle'] . "\n";
            $qrContent .= "PRODUITS: " . count($produitsGroupes) . "\n";
            $qrContent .= "ETAT: " . $transfert['etat'] . "\n";
            $qrContent .= "VERIFICATION: https://votre-site.com/verifier?code=" . $transfert['numero_transfert'];

            // Position en bas à droite de la page
            $pdf->write2DBarcode($qrContent, 'QRCODE,L', 150, $pdf->GetY(), 25, 25, $style, 'N');

            // Texte d'information sur le QR code
            $pdf->SetFont('helvetica', 'I', 8);
            $pdf->SetXY(150, $pdf->GetY() + 40);
            $pdf->Cell(40, 10, 'Scan pour vérification', 0, 1, 'C');
        }

        // Ajouter une page
        $pdf->AddPage();

        // Générer le contenu de la page
        generatePage($pdf, $transfert, $produitsGroupes, $configInstitution, $primaryColor, $secondaryColor, $accentColor);

        // Outputting the PDF
        $fileName = 'Bon_Transfert_' . $transfert['numero_transfert'] . '.pdf';
        $pdf->Output($fileName, 'I');
    } catch (Exception $e) {
        die("Erreur: " . $e->getMessage());
    }
} else {
    header('Location: ../stock/transfert.list');
    exit();
}
