<?php
ob_start();
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
    $idCommande = intval($_GET['id']);

    try {
        // Récupérer les informations de la commande
        $queryCommande = "SELECT cf.*, f.nom_fournisseur, f.code_fournisseur, f.adresse, f.telephone, f.email, f.nif, f.rccm,
                        u.\"nomUser\" as user_creation, v.\"nomUser\" as user_validation
                        FROM commande_fournisseur cf
                        LEFT JOIN fournisseur f ON cf.id_fournisseur = f.id_fournisseur
                        LEFT JOIN t_users u ON cf.id_user_creation = u.\"idUser\"
                        LEFT JOIN t_users v ON cf.id_user_validation = v.\"idUser\"
                        WHERE cf.id_commande = :id_commande";
        $stmtCommande = $db->prepare($queryCommande);
        $stmtCommande->bindParam(':id_commande', $idCommande, PDO::PARAM_INT);
        $stmtCommande->execute();
        $commande = $stmtCommande->fetch(PDO::FETCH_ASSOC);

        if (!$commande) {
            throw new Exception("Commande introuvable");
        }

        // Récupérer les détails des produits
        $queryLignes = "SELECT lcf.*, p.code_produit, p.libelle_produit, u.symbole_unite
                        FROM ligne_commande_fournisseur lcf
                        LEFT JOIN produit p ON lcf.id_produit = p.id_produit
                        LEFT JOIN unite_mesure u ON p.id_unite_stockage = u.id_unite
                        WHERE lcf.id_commande = :id_commande
                        ORDER BY p.libelle_produit";
        $stmtLignes = $db->prepare($queryLignes);
        $stmtLignes->bindParam(':id_commande', $idCommande, PDO::PARAM_INT);
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
        $pdf->SetTitle('Bon de Commande');
        $pdf->SetSubject('Bon de Commande Fournisseur');
        $pdf->SetKeywords('Commande, Fournisseur, Achat');

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

        function generatePage($pdf, $commande, $lignes, $configInstitution, $primaryColor, $secondaryColor, $accentColor, $isDuplicata = false)
        {
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

            // Titre du document avec fond coloré
            $pdf->SetFillColor($primaryColor[0], $primaryColor[1], $primaryColor[2]);
            $pdf->SetTextColor(255, 255, 255);
            $pdf->SetFont('helvetica', 'B', 16);
            $pdf->Ln(5);
            $pdf->Cell(0, 10, 'BON DE COMMANDE N° '.$commande['numero_commande'], 0, 1, 'C', 1);

            // Préparer les données du QR Code
            $qrCodeData = "BON DE COMMANDE\n";
            $qrCodeData .= "N°: " . $commande['numero_commande'] . "\n";
            $qrCodeData .= "Date: " . date('d/m/Y', strtotime($commande['date_commande'])) . "\n";
            $qrCodeData .= "Fournisseur: " . $commande['nom_fournisseur'] . "\n";
            $qrCodeData .= "Statut: " . $commande['etat'] . "\n";
            $qrCodeData .= "Document généré le: " . date('d/m/Y H:i:s') . "\n";
            $qrCodeData .= $configInstitution['site_web'] ?? '';

            $pdf->Ln(0);

            // Remplacer les sections séparées "INFORMATIONS DE LA COMMANDE" et "FOURNISSEUR" 
// par une section combinée "INFORMATIONS GÉNÉRALES"

// INFORMATIONS GÉNÉRALES (Commande + Fournisseur)
$pdf->SetTextColor($secondaryColor[0], $secondaryColor[1], $secondaryColor[2]);
$pdf->SetFont('helvetica', 'B', 12);
$pdf->Cell(0, 8, 'INFORMATIONS GÉNÉRALES', 0, 1, 'L');

// Ligne décorative sous le titre de section
$pdf->SetLineStyle(array('width' => 0.2, 'cap' => 'butt', 'join' => 'miter', 'dash' => 0, 'color' => $secondaryColor));
$pdf->Line(20, $pdf->GetY(), 100, $pdf->GetY());
$pdf->Ln(1);

// Disposition en deux colonnes pour toutes les informations
$pdf->SetTextColor(60, 60, 60);

// Colonne gauche - Informations de la commande
$leftX = $pdf->GetX();
$currentY = $pdf->GetY();

$pdf->SetFont('helvetica', 'B', 9);
$pdf->Cell(30, 5, 'N° Commande:', 0, 0, 'L');
$pdf->SetFont('helvetica', '', 9);
$pdf->Cell(60, 5, $commande['numero_commande'], 0, 1, 'L');

$pdf->SetFont('helvetica', 'B', 9);
$pdf->Cell(30, 5, 'Date commande:', 0, 0, 'L');
$pdf->SetFont('helvetica', '', 9);
$pdf->Cell(60, 5, date('d/m/Y', strtotime($commande['date_commande'])), 0, 1, 'L');

$pdf->SetFont('helvetica', 'B', 9);
$pdf->Cell(30, 5, 'Statut:', 0, 0, 'L');
$pdf->SetFont('helvetica', '', 9);
$pdf->Cell(60, 5, $commande['etat'], 0, 1, 'L');

$pdf->SetFont('helvetica', 'B', 9);
$pdf->Cell(30, 5, 'Livraison prévue:', 0, 0, 'L');
$pdf->SetFont('helvetica', '', 9);
$pdf->Cell(60, 5, $commande['date_livraison_prevue'] ? date('d/m/Y', strtotime($commande['date_livraison_prevue'])) : 'Non spécifiée', 0, 1, 'L');

$pdf->SetFont('helvetica', 'B', 9);
$pdf->Cell(30, 5, 'Créé par:', 0, 0, 'L');
$pdf->SetFont('helvetica', '', 9);
$pdf->Cell(60, 5, $commande['user_creation'], 0, 1, 'L');

if ($commande['etat'] == 'Validé' || $commande['etat'] == 'Réceptionné' || $commande['etat'] == 'Facturé') {
    $pdf->SetFont('helvetica', 'B', 9);
    $pdf->Cell(30, 5, 'Validé par:', 0, 0, 'L');
    $pdf->SetFont('helvetica', '', 9);
    $pdf->Cell(60, 5, $commande['user_validation'] ?? 'N/A', 0, 1, 'L');
}

// Colonne droite - Informations du fournisseur
$rightX = $leftX + 100;
$pdf->SetXY($rightX, $currentY);

$pdf->SetFont('helvetica', 'B', 9);
$pdf->Cell(30, 5, 'Fournisseur:', 0, 0, 'L');
$pdf->SetFont('helvetica', '', 9);
$pdf->Cell(60, 5, $commande['code_fournisseur'] . ' - ' . $commande['nom_fournisseur'], 0, 1, 'L');

if (!empty($commande['adresse'])) {
    $pdf->SetXY($rightX, $pdf->GetY());
    $pdf->SetFont('helvetica', 'B', 9);
    $pdf->Cell(30, 5, 'Adresse:', 0, 0, 'L');
    $pdf->SetFont('helvetica', '', 9);
    $pdf->MultiCell(60, 5, $commande['adresse'], 0, 'L');
    $adresseHeight = $pdf->GetY();
} else {
    $adresseHeight = $pdf->GetY();
}

$pdf->SetXY($rightX, $adresseHeight);
$pdf->SetFont('helvetica', 'B', 9);
$pdf->Cell(30, 5, 'Téléphone:', 0, 0, 'L');
$pdf->SetFont('helvetica', '', 9);
$pdf->Cell(60, 5, $commande['telephone'] ?: 'N/A', 0, 1, 'L');

$pdf->SetXY($rightX, $pdf->GetY());
$pdf->SetFont('helvetica', 'B', 9);
$pdf->Cell(30, 5, 'Email:', 0, 0, 'L');
$pdf->SetFont('helvetica', '', 9);
$pdf->Cell(60, 5, $commande['email'] ?: 'N/A', 0, 1, 'L');

if (!empty($commande['nif'])) {
    $pdf->SetXY($rightX, $pdf->GetY());
    $pdf->SetFont('helvetica', 'B', 9);
    $pdf->Cell(30, 5, 'NIF:', 0, 0, 'L');
    $pdf->SetFont('helvetica', '', 9);
    $pdf->Cell(60, 5, $commande['nif'], 0, 1, 'L');
}

if (!empty($commande['rccm'])) {
    $pdf->SetXY($rightX, $pdf->GetY());
    $pdf->SetFont('helvetica', 'B', 9);
    $pdf->Cell(30, 5, 'RCCM:', 0, 0, 'L');
    $pdf->SetFont('helvetica', '', 9);
    $pdf->Cell(60, 5, $commande['rccm'], 0, 1, 'L');
}

// Revenir à la position après la colonne la plus longue
$pdf->SetY(max($pdf->GetY(), $currentY + 25));

// Observation si présente (sur toute la largeur)
if (!empty($commande['observation'])) {
    $pdf->SetX($leftX);
    $pdf->SetFont('helvetica', 'B', 9);
    $pdf->Cell(30, 5, 'Observation:', 0, 0, 'L');
    $pdf->SetFont('helvetica', '', 9);
    $pdf->MultiCell(0, 5, $commande['observation'], 0, 'L');
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

            // En-têtes du tableau
            $pdf->SetFillColor(240, 240, 240);
            $pdf->SetTextColor(50, 50, 50);
            $pdf->SetFont('helvetica', 'B', 8);
            $pdf->Cell(20, 7, 'Code', 1, 0, 'C', 1);
            $pdf->Cell(60, 7, 'Désignation', 1, 0, 'C', 1);
            $pdf->Cell(15, 7, 'Quantité', 1, 0, 'C', 1);
            $pdf->Cell(15, 7, 'Unité', 1, 0, 'C', 1);
            $pdf->Cell(25, 7, 'Prix unitaire', 1, 0, 'C', 1);
            $pdf->Cell(15, 7, 'Remise', 1, 0, 'C', 1);
            $pdf->Cell(25, 7, 'Montant HT', 1, 1, 'C', 1);

            // Contenu du tableau
            $pdf->SetFont('helvetica', '', 8);
            $pdf->SetTextColor(0, 0, 0);
            $fill = false;

            foreach ($lignes as $ligne) {
                // Calculer la hauteur nécessaire pour la cellule de désignation
                $designation = $ligne['designation'];
                $lineHeight = max(7, $pdf->getStringHeight(60, $designation));

                // Vérifier si on a besoin d'une nouvelle page
                if ($pdf->GetY() + $lineHeight > $pdf->getPageHeight() - 30) {
                    $pdf->AddPage();
                    
                    // Répéter les en-têtes du tableau
                    $pdf->SetFillColor(240, 240, 240);
                    $pdf->SetTextColor(50, 50, 50);
                    $pdf->SetFont('helvetica', 'B', 8);
                    $pdf->Cell(20, 7, 'Code', 1, 0, 'C', 1);
                    $pdf->Cell(60, 7, 'Désignation', 1, 0, 'C', 1);
                    $pdf->Cell(15, 7, 'Quantité', 1, 0, 'C', 1);
                    $pdf->Cell(15, 7, 'Unité', 1, 0, 'C', 1);
                    $pdf->Cell(25, 7, 'Prix unitaire', 1, 0, 'C', 1);
                    $pdf->Cell(15, 7, 'Remise', 1, 0, 'C', 1);
                    $pdf->Cell(25, 7, 'Montant HT', 1, 1, 'C', 1);
                    
                    $pdf->SetFont('helvetica', '', 8);
                    $pdf->SetTextColor(0, 0, 0);
                }

                $startY = $pdf->GetY();
                
                // Code produit
                $pdf->MultiCell(20, $lineHeight, $ligne['code_produit'], 1, 'L', $fill, 0, '', '', true, 0, false, true, $lineHeight, 'M');
                
                // Désignation
                $pdf->MultiCell(60, $lineHeight, $designation, 1, 'L', $fill, 0, '', '', true, 0, false, true, $lineHeight, 'M');
                
                // Quantité
                $pdf->MultiCell(15, $lineHeight, number_format($ligne['quantite'], 2, ',', ' '), 1, 'R', $fill, 0, '', '', true, 0, false, true, $lineHeight, 'M');
                
                // Unité
                $pdf->MultiCell(15, $lineHeight, $ligne['symbole_unite'] ?? '', 1, 'C', $fill, 0, '', '', true, 0, false, true, $lineHeight, 'M');
                
                // Prix unitaire
                $pdf->MultiCell(25, $lineHeight, number_format($ligne['prix_unitaire'], 2, ',', ' ') . ' USD', 1, 'R', $fill, 0, '', '', true, 0, false, true, $lineHeight, 'M');
                
                // Remise
                $pdf->MultiCell(15, $lineHeight, $ligne['remise'] > 0 ? number_format($ligne['remise'], 2, ',', ' ') . '%' : '-', 1, 'C', $fill, 0, '', '', true, 0, false, true, $lineHeight, 'M');
                
                // Montant HT
                $pdf->MultiCell(25, $lineHeight, number_format($ligne['montant_ht'], 2, ',', ' ') . ' USD', 1, 'R', $fill, 1, '', '', true, 0, false, true, $lineHeight, 'M');
                
                $fill = !$fill;
            }

            // Totaux
            $pdf->SetFont('helvetica', 'B', 9);
            $pdf->Cell(150, 7, 'Total HT:', 1, 0, 'R', 0);
            $pdf->Cell(25, 7, number_format($commande['montant_ht'], 2, ',', ' ') . ' USD', 1, 1, 'R', 0);

            $pdf->Cell(150, 7, 'TVA (' . number_format($commande['taux_tva'], 2, ',', ' ') . '%):', 1, 0, 'R', 0);
            $pdf->Cell(25, 7, number_format($commande['montant_tva'], 2, ',', ' ') . ' USD', 1, 1, 'R', 0);

            $pdf->SetFillColor(240, 240, 240);
            $pdf->Cell(150, 7, 'Total TTC:', 1, 0, 'R', 1);
            $pdf->Cell(25, 7, number_format($commande['montant_ttc'], 2, ',', ' ') . ' USD', 1, 1, 'R', 1);

            // Signatures et QR Code sur la même ligne
            $pdf->Ln(5);

            // Définir la position Y commune pour la ligne
            $signatureLineY = $pdf->GetY();

            // QR Code à gauche
            $style = array(
                'border' => false,
                'vpadding' => 'auto',
                'hpadding' => 'auto',
                'fgcolor' => array(0, 0, 0),
                'bgcolor' => false,
                'module_width' => 1,
                'module_height' => 1
            );
            $pdf->write2DBarcode($qrCodeData, 'QRCODE,L', 20, $signatureLineY, 30, 30, $style, 'N');

            // Signatures au centre et à droite (sur la même ligne que le QR Code)
            $pdf->SetFont('helvetica', 'B', 10);
            $pdf->SetXY(60, $signatureLineY); // Position X après le QR Code, même Y
            $pdf->Cell(70, 7, 'Pour le fournisseur', 0, 0, 'C');
            $pdf->Cell(70, 7, 'Pour l\'acheteur', 0, 1, 'C');

            // Espace pour les signatures (sous les titres)
            $pdf->SetXY(60, $signatureLineY + 15);
            $pdf->SetFont('helvetica', 'I', 8);
            $pdf->Cell(70, 5, 'Nom, date et signature', 'T', 0, 'C');
            $pdf->Cell(70, 5, 'Nom, date et signature', 'T', 1, 'C');

            // Ajuster la position Y pour continuer après le QR Code et les signatures
            $pdf->SetY($signatureLineY + 30);


            // Ajouter un tampon "DUPLICATA" si nécessaire
            if ($isDuplicata) {
                $pdf->SetAlpha(0.6);
                $pdf->SetFont('helvetica', 'B', 30);
                $pdf->SetTextColor(255, 0, 0);
                $pdf->StartTransform();
                $pdf->Rotate(30, 105, 120);
                $pdf->Text(65, 120, 'DUPLICATA');
                $pdf->StopTransform();
                $pdf->SetAlpha(1);
            }

            // Conditions générales
            $pdf->SetFont('helvetica', 'B', 9);
            $pdf->Cell(0, 5, 'CONDITIONS GÉNÉRALES:', 0, 1, 'L');
            
            $pdf->SetFont('helvetica', '', 8);
            $conditions = "1. Cette commande doit être confirmée par le fournisseur dans un délai de 48 heures.
2. Toute modification des prix ou des délais de livraison doit être notifiée et acceptée par écrit.
3. Les produits livrés doivent être conformes aux spécifications mentionnées dans cette commande.
4. Le paiement sera effectué selon les conditions convenues après réception et vérification des marchandises.
5. Tout retard de livraison non justifié peut entraîner l'annulation de la commande.";
            
            $pdf->MultiCell(0, 4, $conditions, 0, 'L');
        }

        // Ajouter une page
        $pdf->AddPage();

        // Générer la première page (original)
        generatePage($pdf, $commande, $lignes, $configInstitution, $primaryColor, $secondaryColor, $accentColor, false);

        // Sortie du PDF (nettoyage des buffers pour éviter l'erreur TCPDF)
        while (ob_get_level() > 0) { @ob_end_clean(); }
        if (function_exists('apache_setenv')) { @apache_setenv('no-gzip', '1'); }
        @ini_set('zlib.output_compression', 'Off');
        $pdf->Output('Commande_' . $commande['numero_commande'] . '.pdf', 'I');

    } catch (Exception $e) {
        // En cas d'erreur, rediriger avec un message d'erreur
        header('Location: ../achats/commandes/commandes.list?error=' . urlencode($e->getMessage()));
        exit();
    }
} else {
    // Si aucun ID n'est fourni, rediriger vers la liste des commandes
    header('Location: ../achats/commandes/commandes.list?error=' . urlencode('ID de commande non spécifié'));
    exit();
}

