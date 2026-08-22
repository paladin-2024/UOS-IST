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
    $idDemande = intval($_GET['id']);

    try {
        // Récupérer les informations de la demande de prix
        $queryDemande = "SELECT dp.*, f.nom_fournisseur, f.code_fournisseur, f.adresse, f.telephone, f.email, f.nif, f.rccm,
                        u.nomUser as user_creation, v.nomUser as user_validation
                        FROM demande_prix dp
                        LEFT JOIN fournisseur f ON dp.id_fournisseur = f.id_fournisseur
                        LEFT JOIN t_users u ON dp.id_user_creation = u.idUser
                        LEFT JOIN t_users v ON dp.id_user_validation = v.idUser
                        WHERE dp.id_demande_prix = :id_demande";
        $stmtDemande = $db->prepare($queryDemande);
        $stmtDemande->bindParam(':id_demande', $idDemande, PDO::PARAM_INT);
        $stmtDemande->execute();
        $demande = $stmtDemande->fetch(PDO::FETCH_ASSOC);

        if (!$demande) {
            throw new Exception("Demande de prix introuvable");
        }

        // Récupérer les détails des produits
        $queryLignes = "SELECT ldp.*, p.code_produit, p.libelle_produit, u.symbole_unite
                        FROM ligne_demande_prix ldp
                        LEFT JOIN produit p ON ldp.id_produit = p.id_produit
                        LEFT JOIN unite_mesure u ON p.id_unite_stockage = u.id_unite
                        WHERE ldp.id_demande_prix = :id_demande
                        ORDER BY p.libelle_produit";
        $stmtLignes = $db->prepare($queryLignes);
        $stmtLignes->bindParam(':id_demande', $idDemande, PDO::PARAM_INT);
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
        $pdf->SetTitle('Demande de Prix');
        $pdf->SetSubject('Demande de Prix');
        $pdf->SetKeywords('Demande, Prix, Fournisseur');

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

        function generatePage($pdf, $demande, $lignes, $configInstitution, $primaryColor, $secondaryColor, $accentColor, $isDuplicata = false)
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
            $pdf->Cell(0, 10, 'DEMANDE DE PRIX', 0, 1, 'C', 1);

            $pdf->SetTextColor(80, 80, 80);
            $pdf->SetFont('helvetica', 'B', 10);
            $pdf->Cell(0, 8, 'N° ' . $demande['numero_demande'], 0, 1, 'C');

            // Préparer les données du QR Code
            $qrCodeData = "DEMANDE DE PRIX\n";
            $qrCodeData .= "N°: " . $demande['numero_demande'] . "\n";
            $qrCodeData .= "Date: " . date('d/m/Y', strtotime($demande['date_demande'])) . "\n";
            $qrCodeData .= "Fournisseur: " . $demande['nom_fournisseur'] . "\n";
            $qrCodeData .= "Statut: " . $demande['etat'] . "\n";
            $qrCodeData .= "Document généré le: " . date('d/m/Y H:i:s') . "\n";
            $qrCodeData .= $configInstitution['site_web'] ?? '';

            $pdf->Ln(-5);

            // INFORMATIONS DE LA DEMANDE - Format compact
            $pdf->SetTextColor($secondaryColor[0], $secondaryColor[1], $secondaryColor[2]);
            $pdf->SetFont('helvetica', 'B', 12);
            $pdf->Cell(0, 8, 'INFORMATIONS DE LA DEMANDE', 0, 1, 'L');

            // Ligne décorative sous le titre de section
            $pdf->SetLineStyle(array('width' => 0.2, 'cap' => 'butt', 'join' => 'miter', 'dash' => 0, 'color' => $secondaryColor));
            $pdf->Line(20, $pdf->GetY(), 100, $pdf->GetY());
            $pdf->Ln(1);

            // Format plus compact - colonne gauche
            $pdf->SetTextColor(60, 60, 60);
            $pdf->SetFont('helvetica', 'B', 9);
            $pdf->Cell(30, 5, 'N° Demande:', 0, 0, 'L');
            $pdf->SetFont('helvetica', '', 9);
            $pdf->Cell(65, 5, $demande['numero_demande'], 0, 0, 'L');
            
            // Colonne droite
            $pdf->SetFont('helvetica', 'B', 9);
            $pdf->Cell(30, 5, 'Date demande:', 0, 0, 'L');
            $pdf->SetFont('helvetica', '', 9);
            $pdf->Cell(0, 5, date('d/m/Y', strtotime($demande['date_demande'])), 0, 1, 'L');

            // Deuxième ligne
            $pdf->SetFont('helvetica', 'B', 9);
            $pdf->Cell(30, 5, 'Statut:', 0, 0, 'L');
            $pdf->SetFont('helvetica', '', 9);
            $pdf->Cell(65, 5, $demande['etat'], 0, 0, 'L');
            
            $pdf->SetFont('helvetica', 'B', 9);
            $pdf->Cell(30, 5, 'Créé par:', 0, 0, 'L');
            $pdf->SetFont('helvetica', '', 9);
            $pdf->Cell(0, 5, $demande['user_creation'], 0, 1, 'L');

            // Troisième ligne si validé
            if ($demande['etat'] == 'Validé' || $demande['etat'] == 'Transformé') {
                $pdf->SetFont('helvetica', 'B', 9);
                $pdf->Cell(30, 5, 'Date validation:', 0, 0, 'L');
                $pdf->SetFont('helvetica', '', 9);
                $pdf->Cell(65, 5, date('d/m/Y', strtotime($demande['date_validation'])), 0, 0, 'L');
                
                $pdf->SetFont('helvetica', 'B', 9);
                $pdf->Cell(30, 5, 'Validé par:', 0, 0, 'L');
                $pdf->SetFont('helvetica', '', 9);
                $pdf->Cell(0, 5, $demande['user_validation'], 0, 1, 'L');
            }

            // INFORMATIONS DU FOURNISSEUR - Format compact
            $pdf->Ln(5);
            $pdf->SetTextColor($secondaryColor[0], $secondaryColor[1], $secondaryColor[2]);
            $pdf->SetFont('helvetica', 'B', 12);
            $pdf->Cell(0, 8, 'INFORMATIONS DU FOURNISSEUR', 0, 1, 'L');

            // Ligne décorative sous le titre de section
            $pdf->SetLineStyle(array('width' => 0.2, 'cap' => 'butt', 'join' => 'miter', 'dash' => 0, 'color' => $secondaryColor));
            $pdf->Line(20, $pdf->GetY(), 100, $pdf->GetY());
            $pdf->Ln(1);

            // Format plus compact - colonne gauche
            $pdf->SetTextColor(60, 60, 60);
            $pdf->SetFont('helvetica', 'B', 9);
            $pdf->Cell(30, 5, 'Code:', 0, 0, 'L');
            $pdf->SetFont('helvetica', '', 9);
            $pdf->Cell(65, 5, $demande['code_fournisseur'], 0, 0, 'L');
            
            // Colonne droite
            $pdf->SetFont('helvetica', 'B', 9);
            $pdf->Cell(30, 5, 'Nom:', 0, 0, 'L');
            $pdf->SetFont('helvetica', '', 9);
            $pdf->Cell(0, 5, $demande['nom_fournisseur'], 0, 1, 'L');

            // Deuxième ligne
            $pdf->SetFont('helvetica', 'B', 9);
            $pdf->Cell(30, 5, 'Téléphone:', 0, 0, 'L');
            $pdf->SetFont('helvetica', '', 9);
            $pdf->Cell(65, 5, $demande['telephone'] ?? 'N/A', 0, 0, 'L');
            
            $pdf->SetFont('helvetica', 'B', 9);
            $pdf->Cell(30, 5, 'Email:', 0, 0, 'L');
            $pdf->SetFont('helvetica', '', 9);
            $pdf->Cell(0, 5, $demande['email'] ?? 'N/A', 0, 1, 'L');

            // Troisième ligne
            $pdf->SetFont('helvetica', 'B', 9);
            $pdf->Cell(30, 5, 'NIF:', 0, 0, 'L');
            $pdf->SetFont('helvetica', '', 9);
            $pdf->Cell(65, 5, $demande['nif'] ?? 'N/A', 0, 0, 'L');
            
            $pdf->SetFont('helvetica', 'B', 9);
            $pdf->Cell(30, 5, 'RCCM:', 0, 0, 'L');
            $pdf->SetFont('helvetica', '', 9);
            $pdf->Cell(0, 5, $demande['rccm'] ?? 'N/A', 0, 1, 'L');

            // Adresse si disponible
            if (!empty($demande['adresse'])) {
                $pdf->SetFont('helvetica', 'B', 9);
                $pdf->Cell(30, 5, 'Adresse:', 0, 0, 'L');
                $pdf->SetFont('helvetica', '', 9);
                $pdf->Cell(0, 5, $demande['adresse'], 0, 1, 'L');
            }

            // LISTE DES PRODUITS
            $pdf->Ln(5);
            $pdf->SetTextColor($secondaryColor[0], $secondaryColor[1], $secondaryColor[2]);
            $pdf->SetFont('helvetica', 'B', 12);
            $pdf->Cell(0, 8, 'LISTE DES PRODUITS', 0, 1, 'L');

            // Ligne décorative sous le titre de section
            $pdf->SetLineStyle(array('width' => 0.2, 'cap' => 'butt', 'join' => 'miter', 'dash' => 0, 'color' => $secondaryColor));
            $pdf->Line(20, $pdf->GetY(), 100, $pdf->GetY());
            $pdf->Ln(3);

            // En-têtes du tableau des produits
            $pdf->SetFont('helvetica', 'B', 9);
            $pdf->SetFillColor($secondaryColor[0], $secondaryColor[1], $secondaryColor[2]);
            $pdf->SetTextColor(255, 255, 255);
            
            $pdf->Cell(10, 7, 'N°', 1, 0, 'C', 1);
            $pdf->Cell(25, 7, 'Code', 1, 0, 'C', 1);
            $pdf->Cell(60, 7, 'Produit', 1, 0, 'C', 1);
            $pdf->Cell(25, 7, 'Quantité', 1, 0, 'C', 1);
            $pdf->Cell(30, 7, 'Prix unitaire', 1, 0, 'C', 1);
            $pdf->Cell(30, 7, 'Montant total', 1, 1, 'C', 1);
            
            // Contenu du tableau des produits
            $pdf->SetFont('helvetica', '', 9);
            $pdf->SetTextColor(80, 80, 80);
            
            $totalGeneral = 0;
            $i = 1;
            
            foreach ($lignes as $ligne) {
                $fill = ($i % 2 == 0) ? true : false;
                if ($fill) {
                    $pdf->SetFillColor(248, 248, 248);
                } else {
                    $pdf->SetFillColor(255, 255, 255);
                }
                
                $pdf->Cell(10, 7, $i, 1, 0, 'C', $fill);
                $pdf->Cell(25, 7, $ligne['code_produit'], 1, 0, 'L', $fill);
                $pdf->Cell(60, 7, $ligne['libelle_produit'], 1, 0, 'L', $fill);
                
                // Formater la quantité avec l'unité si disponible
                $quantiteAffichee = number_format($ligne['quantite'], 2, ',', ' ');
                if (!empty($ligne['symbole_unite'])) {
                    $quantiteAffichee .= ' ' . $ligne['symbole_unite'];
                }
                $pdf->Cell(25, 7, $quantiteAffichee, 1, 0, 'R', $fill);
                
                // Prix unitaire et montant total (si disponibles)
                if (!empty($ligne['prix_unitaire'])) {
                    $pdf->Cell(30, 7, number_format($ligne['prix_unitaire'], 2, ',', ' ') . ' USD', 1, 0, 'R', $fill);
                    $pdf->Cell(30, 7, number_format($ligne['montant_total'], 2, ',', ' ') . ' USD', 1, 1, 'R', $fill);
                    $totalGeneral += $ligne['montant_total'];
                } else {
                    $pdf->Cell(30, 7, '', 1, 0, 'R', $fill);
                    $pdf->Cell(30, 7, '', 1, 1, 'R', $fill);
                }
                
                $i++;
            }
            
            // Total général si des prix sont définis
            if ($totalGeneral > 0) {
                $pdf->SetFont('helvetica', 'B', 9);
                $pdf->SetFillColor(240, 240, 240);
                $pdf->Cell(120, 7, 'TOTAL GÉNÉRAL', 1, 0, 'R', 1);
                $pdf->Cell(60, 7, number_format($totalGeneral, 2, ',', ' ') . ' USD', 1, 1, 'R', 1);
            }

            // Observations si présentes
            if (!empty($demande['observation'])) {
                $pdf->Ln(5);
                $pdf->SetTextColor($secondaryColor[0], $secondaryColor[1], $secondaryColor[2]);
                $pdf->SetFont('helvetica', 'B', 12);
                $pdf->Cell(0, 8, 'OBSERVATIONS', 0, 1, 'L');
                
                // Ligne décorative sous le titre de section
                $pdf->SetLineStyle(array('width' => 0.2, 'cap' => 'butt', 'join' => 'miter', 'dash' => 0, 'color' => $secondaryColor));
                $pdf->Line(20, $pdf->GetY(), 100, $pdf->GetY());
                $pdf->Ln(3);
                
                $pdf->SetTextColor(80, 80, 80);
                $pdf->SetFont('helvetica', '', 9);
                $pdf->MultiCell(0, 6, $demande['observation'], 1, 'L', 0);
            }

            // Signatures et QR code en bas
            $pdf->Ln(15);
            
            // Position Y pour les signatures et le QR code
            $startY = $pdf->GetY();
            
            // QR code à gauche
            $style = array(
                'border' => false,
                'vpadding' => 'auto',
                'hpadding' => 'auto',
                'fgcolor' => array(0, 0, 0),
                'bgcolor' => false,
                'module_width' => 1,
                'module_height' => 1
            );
            
            $pdf->SetXY(20, $startY);
            $pdf->Cell(25, 5, 'Code de vérification', 0, 0, 'C');
            $pdf->write2DBarcode($qrCodeData, 'QRCODE,M', 20, $startY + 5, 25, 25);
            
            // Signatures au centre et à droite
            $pdf->SetFont('helvetica', 'B', 10);
            
            // Pour l'entreprise (au centre)
            $pdf->SetXY(85, $startY);
            $pdf->Cell(40, 5, 'Pour l\'entreprise', 0, 1, 'C');
            $pdf->SetXY(85, $startY + 25);
            $pdf->SetFont('helvetica', 'I', 8);
            $pdf->Cell(40, 5, 'Nom et signature', 'T', 0, 'C');
            
            // Pour le fournisseur (à droite)
            $pdf->SetFont('helvetica', 'B', 10);
            $pdf->SetXY(145, $startY);
            $pdf->Cell(40, 5, 'Pour le fournisseur', 0, 1, 'C');
            
            // Cadre pour le cachet
            $pdf->SetLineStyle(array('width' => 0.3, 'cap' => 'butt', 'join' => 'miter', 'dash' => 0, 'color' => array(150, 150, 150)));
            $pdf->RoundedRect(145, $startY + 5, 40, 20, 2, '1111', null, array('color' => array(150, 150, 150)));
            
            $pdf->SetXY(145, $startY + 15);
            $pdf->SetFont('helvetica', 'I', 8);
            $pdf->Cell(40, 5, 'Cachet', 0, 1, 'C');
            
            $pdf->SetXY(145, $startY + 25);
            $pdf->Cell(40, 5, 'Nom et signature', 'T', 0, 'C');
            
            // Ajouter un duplicata si nécessaire
            if ($isDuplicata) {
                $pdf->SetFont('helvetica', 'B', 18);
                $pdf->SetTextColor(255, 0, 0);
                $pdf->SetXY(0, 100);
                $pdf->RotatedText(35, 190, 'DUPLICATA - NE PAS PAYER', 45);
            }
        }

        // Ajouter une page et générer le contenu
        $pdf->AddPage();
        generatePage($pdf, $demande, $lignes, $configInstitution, $primaryColor, $secondaryColor, $accentColor);

        // Sortie du PDF (nettoyage des buffers pour éviter l'erreur TCPDF)
        while (ob_get_level() > 0) { @ob_end_clean(); }
        if (function_exists('apache_setenv')) { @apache_setenv('no-gzip', '1'); }
        @ini_set('zlib.output_compression', 'Off');
        $pdf->Output('Demande_Prix_' . $demande['numero_demande'] . '.pdf', 'I');

    } catch (Exception $e) {
        // En cas d'erreur, afficher un message
        echo '<div style="text-align:center; margin-top:50px;">';
        echo '<h1>Erreur</h1>';
        echo '<p>' . $e->getMessage() . '</p>';
        echo '<a href="../achats/demandes.list">Retour à la liste des demandes</a>';
        echo '</div>';
    }
} else {
    // Redirection si aucun ID n'est fourni
    header('Location: ../achats/demandes.list');
    exit();
}





