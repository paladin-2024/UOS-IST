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
    $idReception = intval($_GET['id']);

    try {
        // Récupérer les informations de la réception
        $queryReception = "SELECT rf.*, f.nom_fournisseur, f.code_fournisseur, f.adresse, f.telephone, f.email, f.nif, f.rccm,
                        d.libelle_depot, d.code_depot,
                        CASE WHEN rf.id_commande IS NULL THEN 'Sans commande' ELSE cf.numero_commande END as numero_commande,
                        u.\"nomUser\" as user_creation, v.\"nomUser\" as user_validation
                        FROM reception_fournisseur rf
                        LEFT JOIN fournisseur f ON rf.id_fournisseur = f.id_fournisseur
                        LEFT JOIN depot d ON rf.id_depot = d.id_depot
                        LEFT JOIN commande_fournisseur cf ON rf.id_commande = cf.id_commande
                        LEFT JOIN t_users u ON rf.id_user_creation = u.\"idUser\"
                        LEFT JOIN t_users v ON rf.id_user_validation = v.\"idUser\"
                        WHERE rf.id_reception = :id_reception";
        $stmtReception = $db->prepare($queryReception);
        $stmtReception->bindParam(':id_reception', $idReception, PDO::PARAM_INT);
        $stmtReception->execute();
        $reception = $stmtReception->fetch(PDO::FETCH_ASSOC);

        if (!$reception) {
            throw new Exception("Réception introuvable");
        }

        // Récupérer les détails des produits
        $queryLignes = "SELECT lrf.*, p.code_produit, p.libelle_produit, u.symbole_unite
                        FROM ligne_reception_fournisseur lrf
                        LEFT JOIN produit p ON lrf.id_produit = p.id_produit
                        LEFT JOIN unite_mesure u ON p.id_unite_stockage = u.id_unite
                        WHERE lrf.id_reception = :id_reception
                        ORDER BY p.libelle_produit";
        $stmtLignes = $db->prepare($queryLignes);
        $stmtLignes->bindParam(':id_reception', $idReception, PDO::PARAM_INT);
        $stmtLignes->execute();
        $lignes = $stmtLignes->fetchAll(PDO::FETCH_ASSOC);

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

        // Regrouper les produits par lot
        $produitsGroupes = [];
        foreach ($lignes as $ligne) {
            $idProduit = $ligne['id_produit'];
            if (!isset($produitsGroupes[$idProduit])) {
                $produitsGroupes[$idProduit] = [
                    'info_produit' => [
                        'id_produit' => $ligne['id_produit'],
                        'code_produit' => $ligne['code_produit'],
                        'libelle_produit' => $ligne['libelle_produit'],
                        'symbole_unite' => $ligne['symbole_unite'],
                        'quantite_totale' => 0,
                        'montant_total' => 0
                    ],
                    'lots' => []
                ];
            }

            // Ajouter le lot à ce produit
            $produitsGroupes[$idProduit]['lots'][] = [
                'numero_lot' => $ligne['numero_lot'],
                'date_peremption' => $ligne['date_peremption'],
                'quantite' => $ligne['quantite'],
                'prix_unitaire' => $ligne['prix_unitaire'],
                'montant_total' => $ligne['montant_total']
            ];

            // Cumuler les quantités et montants
            $produitsGroupes[$idProduit]['info_produit']['quantite_totale'] += $ligne['quantite'];
            $produitsGroupes[$idProduit]['info_produit']['montant_total'] += $ligne['montant_total'];
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
        $pdf->SetTitle('Bon de Réception');
        $pdf->SetSubject('Bon de Réception Fournisseur');
        $pdf->SetKeywords('Réception, Fournisseur, Stock');

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

        function generatePage($pdf, $reception, $produitsGroupes, $configInstitution, $primaryColor, $secondaryColor, $accentColor, $isDuplicata = false)
        {
            // Ajouter une nouvelle page AVANT toute opération
            $pdf->AddPage();

            // Ajouter le logo en filigrane
            if (!empty($configInstitution['logo'])) {
                $logoPath = dirname(__DIR__) . '/' . $configInstitution['logo'];
                if (file_exists($logoPath)) {
                    // Sauvegarder l'état actuel
                    $pdf->setAlpha(0.08);

                    // Position au centre
                    $pageWidth = $pdf->getPageWidth();
                    $pageHeight = $pdf->getPageHeight();

                    // Définir une largeur plus petite
                    $logoWidth = 80;
                    $logoHeight = 110;

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

            // Ajouter "Service des achats" en police calligraphique à gauche
            $pdf->SetFont('times', 'I', 12);
            $pdf->SetTextColor(100, 100, 100);
            $pdf->SetXY(15, 50);
            $pdf->Cell(100, 6, 'Service des achats et approvisionnements', 0, 1, 'L');

            // Réinitialiser la couleur du texte pour la suite
            $pdf->SetTextColor(80, 80, 80);
            $pdf->Ln(3);

            // Titre du document avec fond coloré
            $pdf->SetFillColor($primaryColor[0], $primaryColor[1], $primaryColor[2]);
            $pdf->SetTextColor(255, 255, 255);
            $pdf->SetFont('helvetica', 'B', 16);
            $pdf->Cell(0, 10, 'BON DE RÉCEPTION N° ' . $reception['numero_reception'], 0, 1, 'C', 1);

            // Mention duplicata si nécessaire
            if ($isDuplicata) {
                // Assurez-vous d'être sur la page courante
                $currentPage = $pdf->getPage();
                $pdf->setPage($currentPage);

                $pdf->SetY(60);
                $pdf->SetX(150);
                $pdf->SetFont('helvetica', 'BI', 12);
                $pdf->SetTextColor(255, 0, 0);
                $pdf->Cell(40, 8, 'DUPLICATA', 0, 1, 'C');

                // Réinitialiser la couleur du texte
                $pdf->SetTextColor(80, 80, 80);
            }

            // Préparer les données du QR Code
            $qrCodeData = "BON DE RÉCEPTION\n";
            $qrCodeData .= "N°: " . $reception['numero_reception'] . "\n";
            $qrCodeData .= "Date: " . date('d/m/Y', strtotime($reception['date_reception'])) . "\n";
            $qrCodeData .= "Fournisseur: " . $reception['nom_fournisseur'] . "\n";
            $qrCodeData .= "Statut: " . $reception['etat'] . "\n";
            $qrCodeData .= "Document généré le: " . date('d/m/Y H:i:s') . "\n";
            $qrCodeData .= $configInstitution['site_web'] ?? '';

            $pdf->Ln(0);

            // Remplacer les sections séparées "INFORMATIONS DE LA RÉCEPTION" et "FOURNISSEUR" 
            // par une section combinée "INFORMATIONS GÉNÉRALES"

            // INFORMATIONS GÉNÉRALES (Réception + Fournisseur)
            $pdf->SetTextColor($secondaryColor[0], $secondaryColor[1], $secondaryColor[2]);
            $pdf->SetFont('helvetica', 'B', 12);
            $pdf->Cell(0, 8, 'INFORMATIONS GÉNÉRALES', 0, 1, 'L');

            // Ligne décorative sous le titre de section
            $pdf->SetLineStyle(array('width' => 0.2, 'cap' => 'butt', 'join' => 'miter', 'dash' => 0, 'color' => $secondaryColor));
            $pdf->Line(20, $pdf->GetY(), 100, $pdf->GetY());
            $pdf->Ln(1);

            // Disposition en deux colonnes pour toutes les informations
            $pdf->SetTextColor(60, 60, 60);

            // Colonne gauche - Informations de la réception
            $leftX = $pdf->GetX();
            $currentY = $pdf->GetY();

            $pdf->SetFont('helvetica', 'B', 9);
            $pdf->Cell(30, 5, 'N° Réception:', 0, 0, 'L');
            $pdf->SetFont('helvetica', '', 9);
            $pdf->Cell(60, 5, $reception['numero_reception'], 0, 1, 'L');

            $pdf->SetFont('helvetica', 'B', 9);
            $pdf->Cell(30, 5, 'Date réception:', 0, 0, 'L');
            $pdf->SetFont('helvetica', '', 9);
            $pdf->Cell(60, 5, date('d/m/Y', strtotime($reception['date_reception'])), 0, 1, 'L');

            $pdf->SetFont('helvetica', 'B', 9);
            $pdf->Cell(30, 5, 'Statut:', 0, 0, 'L');
            $pdf->SetFont('helvetica', '', 9);
            $pdf->Cell(60, 5, $reception['etat'], 0, 1, 'L');

            $pdf->SetFont('helvetica', 'B', 9);
            $pdf->Cell(30, 5, 'Dépôt:', 0, 0, 'L');
            $pdf->SetFont('helvetica', '', 9);
            $pdf->Cell(60, 5, $reception['code_depot'] . ' - ' . $reception['libelle_depot'], 0, 1, 'L');

            $pdf->SetFont('helvetica', 'B', 9);
            $pdf->Cell(30, 5, 'Référence BL:', 0, 0, 'L');
            $pdf->SetFont('helvetica', '', 9);
            $pdf->Cell(60, 5, $reception['reference_bl'] ?: 'N/A', 0, 1, 'L');

            // Colonne droite - Informations du fournisseur
            $rightX = $leftX + 100;
            $pdf->SetXY($rightX, $currentY);

            $pdf->SetFont('helvetica', 'B', 9);
            $pdf->Cell(30, 5, 'Fournisseur:', 0, 0, 'L');
            $pdf->SetFont('helvetica', '', 9);
            $pdf->Cell(60, 5, $reception['code_fournisseur'] . ' - ' . $reception['nom_fournisseur'], 0, 1, 'L');

            $pdf->SetXY($rightX, $pdf->GetY());
            $pdf->SetFont('helvetica', 'B', 9);
            $pdf->Cell(30, 5, 'Téléphone:', 0, 0, 'L');
            $pdf->SetFont('helvetica', '', 9);
            $pdf->Cell(60, 5, $reception['telephone'] ?: 'N/A', 0, 1, 'L');

            $pdf->SetXY($rightX, $pdf->GetY());
            $pdf->SetFont('helvetica', 'B', 9);
            $pdf->Cell(30, 5, 'Email:', 0, 0, 'L');
            $pdf->SetFont('helvetica', '', 9);
            $pdf->Cell(60, 5, $reception['email'] ?: 'N/A', 0, 1, 'L');

            $pdf->SetXY($rightX, $pdf->GetY());
            $pdf->SetFont('helvetica', 'B', 9);
            $pdf->Cell(30, 5, 'Commande:', 0, 0, 'L');
            $pdf->SetFont('helvetica', '', 9);
            $pdf->Cell(60, 5, $reception['numero_commande'], 0, 1, 'L');

            $pdf->SetXY($rightX, $pdf->GetY());
            $pdf->SetFont('helvetica', 'B', 9);
            $pdf->Cell(30, 5, 'Créé par:', 0, 0, 'L');
            $pdf->SetFont('helvetica', '', 9);
            $pdf->Cell(60, 5, $reception['user_creation'], 0, 1, 'L');

            // Revenir à la position après la colonne la plus longue
            $pdf->SetY(max($pdf->GetY(), $currentY + 25));

            // Observation si présente (sur toute la largeur)
            if (!empty($reception['observation'])) {
                $pdf->SetX($leftX);
                $pdf->SetFont('helvetica', 'B', 9);
                $pdf->Cell(30, 5, 'Observation:', 0, 0, 'L');
                $pdf->SetFont('helvetica', '', 9);
                $pdf->MultiCell(0, 5, $reception['observation'], 0, 'L');
            }

            $pdf->Ln(3);


            // DÉTAILS DES PRODUITS
            $pdf->SetTextColor($secondaryColor[0], $secondaryColor[1], $secondaryColor[2]);
            $pdf->SetFont('helvetica', 'B', 12);
            $pdf->Cell(0, 8, 'DÉTAILS DES PRODUITS', 0, 1, 'L');

            // Ligne décorative sous le titre de section
            $pdf->SetLineStyle(array('width' => 0.2, 'cap' => 'butt', 'join' => 'miter', 'dash' => 0, 'color' => $secondaryColor));
            $pdf->Line(20, $pdf->GetY(), 90, $pdf->GetY());
            $pdf->Ln(3);

            // Tableau des produits
            $pdf->SetTextColor(0, 0, 0);
            $pdf->SetFillColor(240, 240, 240);
            $pdf->SetFont('helvetica', 'B', 8);

            // En-tête du tableau
            $pdf->Cell(20, 7, 'CODE', 1, 0, 'C', true);
            $pdf->Cell(70, 7, 'PRODUIT', 1, 0, 'C', true);
            $pdf->Cell(20, 7, 'QUANTITÉ', 1, 0, 'C', true);
            $pdf->Cell(30, 7, 'PRIX UNIT.', 1, 0, 'C', true);
            $pdf->Cell(30, 7, 'MONTANT', 1, 1, 'C', true);

            // Contenu du tableau
            $pdf->SetFont('helvetica', '', 8);
            $totalGeneral = 0;

            foreach ($produitsGroupes as $produitGroupe) {
                $infoProduit = $produitGroupe['info_produit'];
                $lots = $produitGroupe['lots'];

                // Ligne principale du produit
                $pdf->SetFillColor(245, 245, 245);
                $pdf->Cell(20, 7, $infoProduit['code_produit'], 1, 0, 'L', true);
                $pdf->Cell(70, 7, $infoProduit['libelle_produit'], 1, 0, 'L', true);
                $pdf->Cell(20, 7, number_format($infoProduit['quantite_totale'], 2, ',', ' ') . ' ' . $infoProduit['symbole_unite'], 1, 0, 'R', true);
                $pdf->Cell(30, 7, '', 1, 0, 'R', true);
                $pdf->Cell(30, 7, number_format($infoProduit['montant_total'], 2, ',', ' ') . ' USD', 1, 1, 'R', true);

                // Détails des lots pour ce produit
                foreach ($lots as $lot) {
                    $pdf->Cell(20, 6, '', 1, 0, 'L');
                    $pdf->Cell(70, 6, '   Lot: ' . $lot['numero_lot'] .
                        ($lot['date_peremption'] ? ' (Exp: ' . date('d/m/Y', strtotime($lot['date_peremption'])) . ')' : ''), 1, 0, 'L');
                    $pdf->Cell(20, 6, number_format($lot['quantite'], 2, ',', ' '), 1, 0, 'R');
                    $pdf->Cell(30, 6, number_format($lot['prix_unitaire'], 2, ',', ' ') . ' USD', 1, 0, 'R');
                    $pdf->Cell(30, 6, number_format($lot['montant_total'], 2, ',', ' ') . ' USD', 1, 1, 'R');
                }

                $totalGeneral += $infoProduit['montant_total'];
            }

            // Total général
            $pdf->SetFont('helvetica', 'B', 9);
            $pdf->SetFillColor(230, 230, 230);
            $pdf->Cell(140, 8, 'TOTAL GÉNÉRAL', 1, 0, 'R', true);
            $pdf->Cell(30, 8, number_format($totalGeneral, 2, ',', ' ') . ' USD', 1, 1, 'R', true);

            // Espace avant les signatures
            $pdf->Ln(10);

            // Style pour le QR Code
            $style = array(
                'border' => false,
                'padding' => 0,
                'fgcolor' => array(0, 0, 0),
                'bgcolor' => false
            );

            // Enregistrer la position Y pour aligner les signatures
            $signatureY = $pdf->GetY();

            // QR Code à gauche
            $pdf->write2DBarcode($qrCodeData, 'QRCODE,L', 20, $signatureY, 25, 25, $style, 'N');

            // Zone de signatures (à droite du QR code)
            $pdf->SetY($signatureY);
            $pdf->SetX(50); // Position après le QR code
            $pdf->SetFont('helvetica', 'B', 10);
            $pdf->Cell(70, 7, 'Signature du Magasinier', 0, 0, 'C');
            $pdf->Cell(70, 7, 'Signature du Fournisseur', 0, 1, 'C');

            // Espace pour les signatures
            $pdf->SetX(50);
            $pdf->Ln(15);
            $pdf->SetX(50);
            $pdf->Cell(70, 7, '____________________________', 0, 0, 'C');
            $pdf->Cell(70, 7, '____________________________', 0, 1, 'C');
        }





        // Générer la première page (original)
        generatePage($pdf, $reception, $produitsGroupes, $configInstitution, $primaryColor, $secondaryColor, $accentColor, false);

        // Sortie du PDF
        $pdf->Output('Bon_Reception_' . $reception['numero_reception'] . '.pdf', 'I');
    } catch (Exception $e) {
        // En cas d'erreur, rediriger vers la liste des réceptions avec un message d'erreur
        header('Location: ../achats/receptions/receptions.list?error=' . urlencode($e->getMessage()));
        exit();
    }
} else {
    // Redirection si aucun ID n'est fourni
    header('Location: ../achats/receptions/receptions.list');
    exit();
}
