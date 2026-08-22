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

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        // Récupérer les données du formulaire
        $depotId = isset($_POST['depot_id']) ? intval($_POST['depot_id']) : 0;
        $produits = isset($_POST['produits']) ? $_POST['produits'] : [];
        $observation = isset($_POST['observation']) ? trim($_POST['observation']) : '';
        
        // Validation des données
        if ($depotId <= 0) {
            throw new Exception("Dépôt invalide");
        }
        
        if (empty($produits)) {
            throw new Exception("Aucun produit sélectionné");
        }
        
        // Récupérer les informations du dépôt
        $stmt = $db->prepare("SELECT * FROM depot WHERE id_depot = :id_depot");
        $stmt->bindParam(':id_depot', $depotId, PDO::PARAM_INT);
        $stmt->execute();
        $depot = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$depot) {
            throw new Exception("Dépôt introuvable");
        }
        
        // Préparer les placeholders pour la requête IN
        $placeholders = implode(',', array_fill(0, count($produits), '?'));
        
        // Récupérer les détails des produits
        $query = "SELECT p.id_produit, p.code_produit, p.libelle_produit, u.symbole_unite,
                        l.id_lot, l.numero_lot, l.quantite_disponible, l.date_peremption, l.prix_unitaire_vente,
                        (SELECT COALESCE(SUM(l2.quantite_disponible), 0)
                         FROM lot_produit l2
                         WHERE l2.id_produit = p.id_produit
                         AND EXISTS (
                             SELECT 1 FROM detail_entree_stock de
                             INNER JOIN entree_stock e ON de.id_entree = e.id_entree
                             WHERE de.id_detail_entree = l2.id_detail_entree
                             AND e.id_depot = ?
                             AND e.etat = 'Validé'
                         )
                        ) as stock_total
                  FROM produit p
                  INNER JOIN lot_produit l ON p.id_produit = l.id_produit
                  LEFT JOIN unite_mesure u ON p.id_unite_stockage = u.id_unite
                  WHERE p.id_produit IN ($placeholders)
                  AND l.quantite_disponible > 0
                  AND EXISTS (
                      SELECT 1 FROM detail_entree_stock de
                      INNER JOIN entree_stock e ON de.id_entree = e.id_entree
                      WHERE de.id_detail_entree = l.id_detail_entree
                      AND e.id_depot = ?
                      AND e.etat = 'Validé'
                  )
                  ORDER BY p.libelle_produit, l.date_peremption";
        
        $stmt = $db->prepare($query);
        
        // Binding des paramètres
        $params = array_merge([$depotId], $produits, [$depotId]);
        $stmt->execute($params);
        $produitDetails = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Regrouper les lots par produit
        $produitsGroupes = [];
        foreach ($produitDetails as $detail) {
            $idProduit = $detail['id_produit'];
            
            if (!isset($produitsGroupes[$idProduit])) {
                $produitsGroupes[$idProduit] = [
                    'id_produit' => $idProduit,
                    'code_produit' => $detail['code_produit'],
                    'libelle_produit' => $detail['libelle_produit'],
                    'symbole_unite' => $detail['symbole_unite'],
                    'stock_total' => $detail['stock_total'],
                    'lots' => []
                ];
            }
            
            $produitsGroupes[$idProduit]['lots'][] = [
                'id_lot' => $detail['id_lot'],
                'numero_lot' => $detail['numero_lot'],
                'quantite_disponible' => $detail['quantite_disponible'],
                'date_peremption' => $detail['date_peremption'],
                'prix_unitaire' => $detail['prix_unitaire_vente']
            ];
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
        
        // Générer un numéro temporaire pour la fiche d'inventaire
        $dateCode = date('Ymd');
        $numeroFiche = "FICHE-INV-{$dateCode}";
        
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
                $this->Cell(($this->getPageWidth() - 30), 5, 'Document généré le ' . date('d/m/Y') . ' • Document à remplir manuellement', 0, 1, 'C');

                // Nom de l'institution et site web (centré sur sa propre ligne)
                $configInstitution = $GLOBALS['configInstitution'] ?? array('nom' => 'eGestion', 'site_web' => '');
                $this->Cell(($this->getPageWidth() - 30), 5, ($configInstitution['nom'] ?? 'eGestion') . ' • ' . ($configInstitution['site_web'] ?? ''), 0, 1, 'C');
            }
        }

        // Créer l'instance de la classe personnalisée
        $pdf = new MYPDF('P', 'mm', 'A4', true, 'UTF-8', false);

        // Rendre la variable configInstitution accessible globalement pour le pied de page
        $GLOBALS['configInstitution'] = $configInstitution;

        // Configurer le document
        $pdf->SetCreator('eGestion');
        $pdf->SetAuthor($configInstitution['nom'] ?? 'eGestion');
        $pdf->SetTitle('Fiche d\'inventaire à remplir');
        $pdf->SetSubject('Fiche d\'inventaire à remplir');
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
        
        // Ajouter une page
        $pdf->AddPage();
        
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
        $pdf->Cell(0, 10, 'FICHE D\'INVENTAIRE À REMPLIR', 0, 1, 'C', 1);

        $pdf->SetTextColor(80, 80, 80);
        $pdf->SetFont('helvetica', 'B', 10);
        $pdf->Cell(0, 8, $numeroFiche, 0, 1, 'C');
        
        $pdf->Ln(2);
        
        // Informations de l'inventaire en 2 colonnes compactes
        $pdf->SetTextColor($secondaryColor[0], $secondaryColor[1], $secondaryColor[2]);
        $pdf->SetFont('helvetica', 'B', 12);
        $pdf->Cell(0, 8, 'INFORMATIONS DE L\'INVENTAIRE', 0, 1, 'L');

                // Ligne décorative sous le titre de section
                $pdf->SetLineStyle(array('width' => 0.2, 'cap' => 'butt', 'join' => 'miter', 'dash' => 0, 'color' => $secondaryColor));
                $pdf->Line(20, $pdf->GetY(), 100, $pdf->GetY());
                $pdf->Ln(1);
        
                // Format compact pour les informations d'inventaire
                $pdf->SetTextColor(60, 60, 60);
                $pdf->SetFont('helvetica', 'B', 9);
                $pdf->Cell(30, 5, 'Dépôt:', 0, 0, 'L');
                $pdf->SetFont('helvetica', '', 9);
                $pdf->Cell(65, 5, $depot['libelle_depot'], 0, 0, 'L');
                
                $pdf->SetFont('helvetica', 'B', 9);
                $pdf->Cell(30, 5, 'Date inventaire:', 0, 0, 'L');
                $pdf->SetFont('helvetica', '', 9);
                $pdf->Cell(0, 5, date('d/m/Y'), 0, 1, 'L');
        
                // Responsable inventaire
                $pdf->SetFont('helvetica', 'B', 9);
                $pdf->Cell(30, 5, 'Responsable:', 0, 0, 'L');
                $pdf->SetFont('helvetica', '', 9);
                $pdf->Cell(65, 5, '...................................', 0, 0, 'L');
                
                // Date effective d'inventaire (à remplir)
                $pdf->SetFont('helvetica', 'B', 9);
                $pdf->Cell(30, 5, 'Date effective:', 0, 0, 'L');
                $pdf->SetFont('helvetica', '', 9);
                $pdf->Cell(0, 5, '____ / ____ / ________', 0, 1, 'L');
        
                // Observation
                if (!empty($observation)) {
                    $pdf->SetFont('helvetica', 'B', 9);
                    $pdf->Cell(30, 5, 'Observation:', 0, 0, 'L');
                    $pdf->SetFont('helvetica', '', 9);
                    $pdf->MultiCell(0, 5, $observation, 0, 'L');
                }
        
                $pdf->Ln(3);
        
                // Titre du tableau des produits à inventorier
                $pdf->SetTextColor($secondaryColor[0], $secondaryColor[1], $secondaryColor[2]);
                $pdf->SetFont('helvetica', 'B', 12);
                $pdf->Cell(0, 8, 'PRODUITS À INVENTORIER', 0, 1, 'L');
        
                // Ligne décorative sous le titre de section
                $pdf->SetLineStyle(array('width' => 0.2, 'cap' => 'butt', 'join' => 'miter', 'dash' => 0, 'color' => $secondaryColor));
                $pdf->Line(20, $pdf->GetY(), 100, $pdf->GetY());
                $pdf->Ln(2);
        
                // Notice pour remplissage
                $pdf->SetTextColor(100, 100, 100);
                $pdf->SetFont('helvetica', 'I', 8);
                $pdf->Cell(0, 5, 'Veuillez remplir manuellement les colonnes "Stock Physique" et "Écart" lors de l\'inventaire.', 0, 1, 'L');
                $pdf->Ln(2);
        
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
        
                // Lignes du tableau pour chaque produit et ses lots
                $pdf->SetFont('helvetica', '', 8);
                $index = 1;
        
                foreach ($produitsGroupes as $produit) {
                    // Ligne du produit (en gras avec fond gris clair)
                    $pdf->SetFont('helvetica', 'B', 8);
                    $pdf->SetFillColor(245, 245, 245);
                    
                    $pdf->Cell(8, 7, $index, 1, 0, 'C', 1);
                    $pdf->Cell(20, 7, $produit['code_produit'], 1, 0, 'L', 1);
                    $pdf->Cell(55, 7, $produit['libelle_produit'], 1, 0, 'L', 1);
                    $pdf->Cell(25, 7, number_format($produit['stock_total'], 2) . ' ' . $produit['symbole_unite'], 1, 0, 'R', 1);
                    $pdf->Cell(25, 7, '', 1, 0, 'C', 1); // Colonne vide pour le stock physique
                    $pdf->Cell(20, 7, '', 1, 0, 'C', 1); // Colonne vide pour l'écart
                    
                    // Prix moyen pondéré (si disponible)
                    $totalValeur = 0;
                    $totalQuantite = 0;
                    foreach ($produit['lots'] as $lot) {
                        $totalValeur += $lot['quantite_disponible'] * $lot['prix_unitaire'];
                        $totalQuantite += $lot['quantite_disponible'];
                    }
                    $prixMoyen = $totalQuantite > 0 ? $totalValeur / $totalQuantite : 0;
                    
                    $pdf->Cell(20, 7, number_format($prixMoyen, 2) . ' USD', 1, 1, 'R', 1);
                    
                    // Détails des lots (en police normale sans fond)
                    $pdf->SetFont('helvetica', '', 8);
                    $pdf->SetFillColor(255, 255, 255);
                    
                    foreach ($produit['lots'] as $lot) {
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
                        $pdf->Cell(25, 6, number_format($lot['quantite_disponible'], 2) . ' ' . $produit['symbole_unite'], 1, 0, 'R', 0);
                        $pdf->Cell(25, 6, '', 1, 0, 'C', 0); // Colonne vide pour le stock physique du lot
                        $pdf->Cell(20, 6, '', 1, 0, 'C', 0); // Colonne vide pour l'écart du lot
                        $pdf->Cell(20, 6, number_format($lot['prix_unitaire'], 2) . ' USD', 1, 1, 'R', 0);
                    }
                    
                    $index++;
                }
        
                // Espace pour notes et observations
                $pdf->Ln(5);
                $pdf->SetFont('helvetica', 'B', 9);
                $pdf->Cell(0, 5, 'NOTES / OBSERVATIONS PENDANT L\'INVENTAIRE:', 0, 1, 'L');
                
                $pdf->SetDrawColor(200, 200, 200);
                for ($i = 0; $i < 5; $i++) {
                    $pdf->Cell(0, 6, '', 'B', 1, 'L');
                }
                
                // Espace pour les signatures
                $pdf->Ln(5);
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
                $pdf->Cell($colWidth, 6, 'Préparé par', 1, 0, 'C', 1);
                $pdf->Cell($colWidth, 6, 'Validé par', 1, 1, 'C', 1);
                
                // Espaces pour les signatures
                $pdf->SetFont('helvetica', '', 9);
                $pdf->Cell($colWidth, 10, '', 1, 0, 'C');
                $pdf->Cell($colWidth, 10, '', 1, 1, 'C');
                
                // Espaces pour les noms
                $pdf->Cell($colWidth, 8, 'Nom: ................................................', 1, 0, 'L');
                $pdf->Cell($colWidth, 8, 'Nom: ................................................', 1, 1, 'L');
                
                // Espaces pour les dates
                $pdf->Cell($colWidth, 8, 'Date: ____/____/________', 1, 0, 'L');
                $pdf->Cell($colWidth, 8, 'Date: ____/____/________', 1, 1, 'L');

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
        
                // Instructions de bas de page
                $pdf->Ln(5);
                $pdf->SetFont('helvetica', 'I', 8);
                $pdf->SetTextColor(100, 100, 100);
                $pdf->MultiCell(0, 4, "INSTRUCTIONS: Cette fiche doit être complétée lors de l'inventaire physique. Veuillez remplir les colonnes \"Stock Physique\" pour chaque produit/lot, puis calculer les écarts. Le document complété devra être signé et remis au service comptable pour validation et ajustement des stocks dans le système.", 0, 'L');
        
                // Outputting the PDF
                $pdf->Output('Fiche_Inventaire_' . date('Y-m-d') . '.pdf', 'I');
            } catch (Exception $e) {
                die("Erreur: " . $e->getMessage());
            }
        } else {
            header('Location: ../stock/inventaire.fiche');
            exit();
        }
        