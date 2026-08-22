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
    $idDevis = intval($_GET['id']);

    try {
        // Récupérer les informations du devis
        $queryDevis = "SELECT d.*, c.nom_client, c.code_client, c.adresse, c.telephone, c.email, c.nif, c.rccm,
                        u.nomUser as user_creation, v.nomUser as user_validation
                        FROM devis d
                        LEFT JOIN client c ON d.id_client = c.id_client
                        LEFT JOIN t_users u ON d.id_user_creation = u.idUser
                        LEFT JOIN t_users v ON d.id_user_validation = v.idUser
                        WHERE d.id_devis = :id_devis";
        $stmtDevis = $db->prepare($queryDevis);
        $stmtDevis->bindParam(':id_devis', $idDevis, PDO::PARAM_INT);
        $stmtDevis->execute();
        $devis = $stmtDevis->fetch(PDO::FETCH_ASSOC);

        if (!$devis) {
            throw new Exception("Devis introuvable");
        }

        // Récupérer les détails des produits
        $queryLignes = "SELECT ld.*, p.code_produit, p.libelle_produit, u.symbole_unite
                        FROM ligne_devis ld
                        LEFT JOIN produit p ON ld.id_produit = p.id_produit
                        LEFT JOIN unite_mesure u ON p.id_unite_vente = u.id_unite
                        WHERE ld.id_devis = :id_devis
                        ORDER BY p.libelle_produit";
        $stmtLignes = $db->prepare($queryLignes);
        $stmtLignes->bindParam(':id_devis', $idDevis, PDO::PARAM_INT);
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
        $pdf->SetTitle('Facture Proforma');
        $pdf->SetSubject('Facture Proforma');
        $pdf->SetKeywords('Facture, Proforma, Devis, Client');

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

        function generatePage($pdf, $devis, $lignes, $configInstitution, $primaryColor, $secondaryColor, $accentColor, $isDuplicata = false)
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

            // Ajouter "Service commercial" en police calligraphique à gauche
            $pdf->SetFont('times', 'I', 12);
            $pdf->SetTextColor(100, 100, 100);
            $pdf->SetXY(15, 50);
            $pdf->Cell(100, 6, 'Service commercial', 0, 1, 'L');

            // Réinitialiser la couleur du texte pour la suite
            $pdf->SetTextColor(80, 80, 80);

            // Titre du document avec fond coloré
            $pdf->SetFillColor($primaryColor[0], $primaryColor[1], $primaryColor[2]);
            $pdf->SetTextColor(255, 255, 255);
            $pdf->SetFont('helvetica', 'B', 16);
            $pdf->Ln(5);
            $pdf->Cell(0, 10, 'FACTURE PROFORMA', 0, 1, 'C', 1);

            $pdf->SetTextColor(80, 80, 80);
            $pdf->SetFont('helvetica', 'B', 10);
            $pdf->Cell(0, 8, 'N° ' . $devis['numero_devis'], 0, 1, 'C');

            // Préparer les données du QR Code
            $qrCodeData = "FACTURE PROFORMA\n";
            $qrCodeData .= "N°: " . $devis['numero_devis'] . "\n";
            $qrCodeData .= "Date: " . date('d/m/Y', strtotime($devis['date_devis'])) . "\n";
            $qrCodeData .= "Client: " . $devis['nom_client'] . "\n";
            $qrCodeData .= "Montant: " . number_format($devis['montant_ttc'], 2, '.', ' ') . " USD\n";
            $qrCodeData .= "Statut: " . $devis['etat'] . "\n";
            $qrCodeData .= "Document généré le: " . date('d/m/Y H:i:s') . "\n";
            $qrCodeData .= $configInstitution['site_web'] ?? '';

            $pdf->Ln(-5);

            // INFORMATIONS COMPACTÉES - Format 2 colonnes
            $pdf->SetTextColor($secondaryColor[0], $secondaryColor[1], $secondaryColor[2]);
            $pdf->SetFont('helvetica', 'B', 12);
            $pdf->Cell(0, 8, 'INFORMATIONS', 0, 1, 'L');

            // Ligne décorative sous le titre de section
            $pdf->SetLineStyle(array('width' => 0.2, 'cap' => 'butt', 'join' => 'miter', 'dash' => 0, 'color' => $secondaryColor));
            $pdf->Line(20, $pdf->GetY(), 100, $pdf->GetY());
            $pdf->Ln(1);

            // Colonne gauche - Informations du devis
            $pdf->SetTextColor(60, 60, 60);
            $pdf->SetFont('helvetica', 'B', 9);
            $pdf->Cell(25, 5, 'N° Devis:', 0, 0, 'L');
            $pdf->SetFont('helvetica', '', 9);
            $pdf->Cell(65, 5, $devis['numero_devis'], 0, 0, 'L');

            // Colonne droite - Informations du client
            $pdf->SetFont('helvetica', 'B', 9);
            $pdf->Cell(25, 5, 'Client:', 0, 0, 'L');
            $pdf->SetFont('helvetica', '', 9);
            $pdf->Cell(0, 5, $devis['code_client'] . ' - ' . $devis['nom_client'], 0, 1, 'L');

            // Deuxième ligne
            $pdf->SetFont('helvetica', 'B', 9);
            $pdf->Cell(25, 5, 'Date:', 0, 0, 'L');
            $pdf->SetFont('helvetica', '', 9);
            $pdf->Cell(65, 5, date('d/m/Y', strtotime($devis['date_devis'])), 0, 0, 'L');

            $pdf->SetFont('helvetica', 'B', 9);
            $pdf->Cell(25, 5, 'Adresse:', 0, 0, 'L');
            $pdf->SetFont('helvetica', '', 9);
            $pdf->Cell(0, 5, substr($devis['adresse'] ?? 'N/A', 0, 40), 0, 1, 'L');

            // Troisième ligne
            $pdf->SetFont('helvetica', 'B', 9);
            $pdf->Cell(25, 5, 'Validité:', 0, 0, 'L');
            $pdf->SetFont('helvetica', '', 9);
            $pdf->Cell(65, 5, $devis['validite'] . ' jours', 0, 0, 'L');

            $pdf->SetFont('helvetica', 'B', 9);
            $pdf->Cell(25, 5, 'Téléphone:', 0, 0, 'L');
            $pdf->SetFont('helvetica', '', 9);
            $pdf->Cell(0, 5, $devis['telephone'] ?? 'N/A', 0, 1, 'L');

            // Quatrième ligne
            $pdf->SetFont('helvetica', 'B', 9);
            $pdf->Cell(25, 5, 'État:', 0, 0, 'L');
            $pdf->SetFont('helvetica', '', 9);
            $pdf->Cell(65, 5, $devis['etat'], 0, 0, 'L');

            $pdf->SetFont('helvetica', 'B', 9);
            $pdf->Cell(25, 5, 'Email:', 0, 0, 'L');
            $pdf->SetFont('helvetica', '', 9);
            $pdf->Cell(0, 5, $devis['email'] ?? 'N/A', 0, 1, 'L');

            // Cinquième ligne (si nécessaire)
            if (!empty($devis['nif']) || !empty($devis['rccm'])) {
                $pdf->SetFont('helvetica', 'B', 9);
                $pdf->Cell(25, 5, 'Référence:', 0, 0, 'L');
                $pdf->SetFont('helvetica', '', 9);
                $pdf->Cell(65, 5, $devis['user_creation'], 0, 0, 'L');

                $pdf->SetFont('helvetica', 'B', 9);
                $pdf->Cell(25, 5, 'NIF/RCCM:', 0, 0, 'L');
                $pdf->SetFont('helvetica', '', 9);
                $nifRccm = '';
                if (!empty($devis['nif'])) $nifRccm .= 'NIF: ' . $devis['nif'] . ' ';
                if (!empty($devis['rccm'])) $nifRccm .= 'RCCM: ' . $devis['rccm'];
                $pdf->Cell(0, 5, $nifRccm, 0, 1, 'L');
            }

            // Espace avant le tableau
            $pdf->Ln(5);

            // Tableau des produits
            $pdf->SetTextColor($secondaryColor[0], $secondaryColor[1], $secondaryColor[2]);
            $pdf->SetFont('helvetica', 'B', 12);
            $pdf->Cell(0, 8, 'DÉTAILS DES PRODUITS', 0, 1, 'L');

            // Ligne décorative sous le titre de section
            $pdf->SetLineStyle(array('width' => 0.2, 'cap' => 'butt', 'join' => 'miter', 'dash' => 0, 'color' => $secondaryColor));
            $pdf->Line(20, $pdf->GetY(), 100, $pdf->GetY());
            $pdf->Ln(1);

            // En-tête du tableau
            $pdf->SetFillColor(240, 240, 240);
            $pdf->SetTextColor(50, 50, 50);
            $pdf->SetFont('helvetica', 'B', 9);
            $pdf->Cell(20, 7, 'Code', 1, 0, 'C', 1);
            $pdf->Cell(80, 7, 'Désignation', 1, 0, 'C', 1);
            $pdf->Cell(15, 7, 'Qté', 1, 0, 'C', 1);
            $pdf->Cell(20, 7, 'Prix Unit.', 1, 0, 'C', 1);
            $pdf->Cell(15, 7, 'Remise', 1, 0, 'C', 1);
            $pdf->Cell(25, 7, 'Montant', 1, 1, 'C', 1);

            // Contenu du tableau
            $pdf->SetFont('helvetica', '', 9);
            $pdf->SetTextColor(80, 80, 80);
            $totalHT = 0;

            foreach ($lignes as $ligne) {
                // Vérifier si on a besoin d'une nouvelle page
                if ($pdf->GetY() > 240) {
                    $pdf->AddPage();

                    // Réafficher l'en-tête du tableau
                    $pdf->SetFillColor(240, 240, 240);
                    $pdf->SetTextColor(50, 50, 50);
                    $pdf->SetFont('helvetica', 'B', 9);
                    $pdf->Cell(20, 7, 'Code', 1, 0, 'C', 1);
                    $pdf->Cell(80, 7, 'Désignation', 1, 0, 'C', 1);
                    $pdf->Cell(15, 7, 'Qté', 1, 0, 'C', 1);
                    $pdf->Cell(20, 7, 'Prix Unit.', 1, 0, 'C', 1);
                    $pdf->Cell(15, 7, 'Remise', 1, 0, 'C', 1);
                    $pdf->Cell(25, 7, 'Montant', 1, 1, 'C', 1);

                    $pdf->SetFont('helvetica', '', 9);
                    $pdf->SetTextColor(80, 80, 80);
                }

                // Limiter la longueur de la désignation
                $designation = $ligne['designation'];
                if (strlen($designation) > 50) {
                    $designation = substr($designation, 0, 47) . '...';
                }

                $pdf->Cell(20, 6, $ligne['code_produit'], 1, 0, 'L');
                $pdf->Cell(80, 6, $designation, 1, 0, 'L');
                $pdf->Cell(15, 6, number_format($ligne['quantite'], 0, '.', ' ') . ' ' . ($ligne['symbole_unite'] ?? ''), 1, 0, 'C');
                $pdf->Cell(20, 6, number_format($ligne['prix_unitaire'], 2, '.', ' '), 1, 0, 'R');
                $pdf->Cell(15, 6, $ligne['remise'] > 0 ? number_format($ligne['remise'], 2, '.', ' ') . '%' : '-', 1, 0, 'C');
                $pdf->Cell(25, 6, number_format($ligne['montant_ht'], 2, '.', ' '), 1, 1, 'R');

                $totalHT += $ligne['montant_ht'];
            }

            // Totaux
            $pdf->SetFont('helvetica', 'B', 9);
            $pdf->Cell(150, 7, 'Total HT', 1, 0, 'R', 1);
            $pdf->Cell(25, 7, number_format($devis['montant_ht'], 2, '.', ' '), 1, 1, 'R', 1);

            $pdf->Cell(150, 7, 'TVA (' . number_format($devis['taux_tva'], 2, '.', ' ') . '%)', 1, 0, 'R', 1);
            $pdf->Cell(25, 7, number_format($devis['montant_tva'], 2, '.', ' '), 1, 1, 'R', 1);

            $pdf->SetFont('helvetica', 'B', 10);
            $pdf->SetTextColor($primaryColor[0], $primaryColor[1], $primaryColor[2]);
            $pdf->Cell(150, 7, 'TOTAL TTC (USD)', 1, 0, 'R', 1);
            $pdf->Cell(25, 7, number_format($devis['montant_ttc'], 2, '.', ' '), 1, 1, 'R', 1);

            // Arrêté de la somme en lettres
            $pdf->Ln(5);
            $pdf->SetFont('helvetica', 'I', 9);
            $pdf->SetTextColor(80, 80, 80);
            $pdf->MultiCell(0, 5, 'Arrêté la présente facture proforma à la somme de : ' . ucfirst(convertirEnLettres($devis['montant_ttc'])) . ' dollars américains.', 0, 'L');

            // Conditions de vente et notes
            $pdf->Ln(5);
            $pdf->SetFont('helvetica', 'B', 10);
            $pdf->SetTextColor($secondaryColor[0], $secondaryColor[1], $secondaryColor[2]);
            $pdf->Cell(0, 6, 'CONDITIONS ET NOTES', 0, 1, 'L');

            // Ligne décorative sous le titre de section
            $pdf->SetLineStyle(array('width' => 0.2, 'cap' => 'butt', 'join' => 'miter', 'dash' => 0, 'color' => $secondaryColor));
            $pdf->Line(20, $pdf->GetY(), 100, $pdf->GetY());
            $pdf->Ln(1);

            $pdf->SetFont('helvetica', '', 9);
            $pdf->SetTextColor(80, 80, 80);
            $pdf->MultiCell(0, 5, "- Validité de l'offre : " . $devis['validite'] . " jours à compter de la date d'émission.\n- Cette facture proforma n'est pas une facture définitive.\n- Les prix sont indiqués en dollars américains (USD).\n- Délai de livraison : à convenir après acceptation de l'offre.", 0, 'L');

            if (!empty($devis['observation'])) {
                $pdf->Ln(2);
                $pdf->SetFont('helvetica', 'B', 9);
                $pdf->Cell(0, 5, 'Observations :', 0, 1, 'L');
                $pdf->SetFont('helvetica', '', 9);
                $pdf->MultiCell(0, 5, $devis['observation'], 0, 'L');
            }

            // Signatures
            $pdf->Ln(5);
            $pdf->SetFont('helvetica', 'B', 10);
            $pdf->Cell(90, 6, 'Pour le service commercial', 0, 0, 'L');
            $pdf->Cell(90, 6, 'Pour le client', 0, 1, 'R');

            $pdf->Ln(15);
            $pdf->SetFont('helvetica', 'I', 8);
            $pdf->Cell(90, 5, 'Signature et cachet', 0, 0, 'L');
            $pdf->Cell(90, 5, 'Bon pour accord (signature et cachet)', 0, 1, 'R');

            // QR Code en bas à droite
            $style = array(
                'border' => false,
                'vpadding' => 'auto',
                'hpadding' => 'auto',
                'fgcolor' => array(0, 0, 0),
                'bgcolor' => false,
                'module_width' => 1,
                'module_height' => 1
            );
            $pdf->write2DBarcode($qrCodeData, 'QRCODE,L', 100, $pdf->GetY() - 25, 30, 30, $style, 'N');

            // Mention duplicata si nécessaire
            if ($isDuplicata) {
                $pdf->SetFont('helvetica', 'B', 24);
                $pdf->SetTextColor(255, 0, 0, 20);
                $pdf->SetXY(0, 100);
                $pdf->RotatedText(35, 190, 'DUPLICATA', 45);
            }
        }

        // Fonction pour convertir un nombre en lettres
        // Remplacez la fonction convertirEnLettres existante par celle-ci
        function convertirEnLettres($nombre)
        {
            // Tableau des unités
            $unite = array(
                0 => 'zéro',
                1 => 'un',
                2 => 'deux',
                3 => 'trois',
                4 => 'quatre',
                5 => 'cinq',
                6 => 'six',
                7 => 'sept',
                8 => 'huit',
                9 => 'neuf'
            );

            // Tableau des dizaines
            $dizaine = array(
                10 => 'dix',
                11 => 'onze',
                12 => 'douze',
                13 => 'treize',
                14 => 'quatorze',
                15 => 'quinze',
                16 => 'seize',
                17 => 'dix-sept',
                18 => 'dix-huit',
                19 => 'dix-neuf',
                20 => 'vingt',
                30 => 'trente',
                40 => 'quarante',
                50 => 'cinquante',
                60 => 'soixante',
                70 => 'soixante-dix',
                80 => 'quatre-vingt',
                90 => 'quatre-vingt-dix'
            );

            // Tableau des centaines
            $centaine = array(100 => 'cent');

            // Tableau des milliers et millions
            $mille = array(1000 => 'mille', 1000000 => 'million', 1000000000 => 'milliard');

            // Séparer la partie entière et la partie décimale
            $nombre = number_format($nombre, 2, '.', '');
            list($entier, $decimal) = explode('.', $nombre);

            // Convertir la partie entière
            $entierEnLettres = '';
            if ($entier == 0) {
                $entierEnLettres = $unite[0];
            } else {
                // Simplification : pour les nombres jusqu'à 999999
                if ($entier < 1000000) {
                    // Traitement des milliers
                    if ($entier >= 1000) {
                        $milliers = floor($entier / 1000);
                        if ($milliers == 1) {
                            $entierEnLettres .= $mille[1000] . ' ';
                        } else {
                            $entierEnLettres .= nombreSimpleEnLettres($milliers) . ' ' . $mille[1000] . ' ';
                        }
                        $entier = $entier % 1000;
                    }

                    // Traitement des centaines, dizaines et unités
                    if ($entier > 0) {
                        $entierEnLettres .= nombreSimpleEnLettres($entier);
                    }
                } else {
                    $entierEnLettres = 'nombre trop grand';
                }
            }

            // Convertir la partie décimale
            if ($decimal == '00') {
                return trim($entierEnLettres);
            } else {
                return trim($entierEnLettres) . ' virgule ' . nombreSimpleEnLettres((int)$decimal);
            }
        }

        // Fonction auxiliaire pour convertir un nombre simple (< 1000)
        function nombreSimpleEnLettres($nombre)
        {
            // Tableau des unités
            $unite = array(
                0 => 'zéro',
                1 => 'un',
                2 => 'deux',
                3 => 'trois',
                4 => 'quatre',
                5 => 'cinq',
                6 => 'six',
                7 => 'sept',
                8 => 'huit',
                9 => 'neuf'
            );

            // Tableau des dizaines
            $dizaine = array(
                10 => 'dix',
                11 => 'onze',
                12 => 'douze',
                13 => 'treize',
                14 => 'quatorze',
                15 => 'quinze',
                16 => 'seize',
                17 => 'dix-sept',
                18 => 'dix-huit',
                19 => 'dix-neuf',
                20 => 'vingt',
                30 => 'trente',
                40 => 'quarante',
                50 => 'cinquante',
                60 => 'soixante',
                70 => 'soixante-dix',
                80 => 'quatre-vingt',
                90 => 'quatre-vingt-dix'
            );

            $resultat = '';

            // Traitement des centaines
            if ($nombre >= 100) {
                $centaines = floor($nombre / 100);
                if ($centaines == 1) {
                    $resultat .= 'cent ';
                } else {
                    $resultat .= $unite[$centaines] . ' cent ';
                }
                $nombre = $nombre % 100;
            }

            // Traitement des dizaines et unités
            if ($nombre > 0) {
                if ($nombre < 10) {
                    $resultat .= $unite[$nombre];
                } else if ($nombre < 20) {
                    $resultat .= $dizaine[$nombre];
                } else {
                    $dizaines = floor($nombre / 10) * 10;
                    $unites = $nombre % 10;

                    if ($dizaines == 70) {
                        $resultat .= 'soixante-';
                        $resultat .= $dizaine[10 + $unites];
                    } else if ($dizaines == 90) {
                        $resultat .= 'quatre-vingt-';
                        $resultat .= $dizaine[10 + $unites];
                    } else {
                        $resultat .= $dizaine[$dizaines];
                        if ($unites > 0) {
                            $resultat .= '-' . $unite[$unites];
                        }
                    }
                }
            }

            return trim($resultat);
        }


        // Ajouter une page
        $pdf->AddPage();

        // Générer le contenu de la page
        generatePage($pdf, $devis, $lignes, $configInstitution, $primaryColor, $secondaryColor, $accentColor);

        // Sortie du PDF (nettoyage des buffers pour éviter l'erreur TCPDF)
        while (ob_get_level() > 0) { @ob_end_clean(); }
        if (function_exists('apache_setenv')) { @apache_setenv('no-gzip', '1'); }
        @ini_set('zlib.output_compression', 'Off');
        $pdf->Output('Facture_Proforma_' . $devis['numero_devis'] . '.pdf', 'I');
    } catch (Exception $e) {
        // En cas d'erreur, rediriger vers la page de liste avec un message d'erreur
        header('Location: ../ventes/devis/devis.list?error=' . urlencode($e->getMessage()));
        exit();
    }
} else {
    // Redirection si l'ID n'est pas fourni
    header('Location: ../ventes/devis/devis.list?error=ID%20non%20fourni');
    exit();
}
