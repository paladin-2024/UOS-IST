<?php
session_start();
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/models/Universite.php';
require_once dirname(__DIR__) . '/vendor/autoload.php';

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['id'])) {
    header('Location: ../login');
    exit();
}

// Récupération des filtres depuis l'URL
$anneeAcad = isset($_GET['anneeAcad']) ? $_GET['anneeAcad'] : '';
$promotion = isset($_GET['promotion']) ? $_GET['promotion'] : '';
$categoriesFrais = isset($_GET['categoriesFrais']) ? $_GET['categoriesFrais'] : '';

try {
    // Initialisation de la connexion
    $connexion = Connexion::getInstance()->getPDO();
    $universite = new Universite();
    $configUniversite = $universite->getConfigurationUniversite();

    // Construction de la requête pour récupérer les frais par promotion
    $sql = "
        SELECT 
            f.id, f.designation, f.montant, f.devise, f.est_obligatoire,
            cf.designation as categorie_frais,
            p.\"designationPromotion\" as promotion,
            p.idpromotion,
            s.\"designationSection\" as section,
            a.designation as annee_academique,
            COUNT(DISTINCT e.idetudiant) as nb_etudiants,
            COUNT(DISTINCT pf.id) as nb_paiements,
            COALESCE(SUM(CASE WHEN pf.est_confirme = 1 THEN pf.montant ELSE 0 END), 0) as montant_percu
        FROM frais f
        JOIN categories_frais cf ON f.categorie_id = cf.id
        JOIN affectation_frais af ON f.id = af.frais_id
        JOIN promotion p ON af.promotion_id = p.idpromotion
        JOIN annee_acad a ON p.annee_acad_idannee_acad = a.idannee_acad
        JOIN orientation o ON p.orientation_idorientation = o.idorientation
        JOIN section s ON o.section_idsection = s.idsection
        LEFT JOIN etudiant e ON e.promotion_idpromotion = p.idpromotion AND e.est_actif = 1
        LEFT JOIN paiements_frais pf ON af.id = pf.affectation_id AND pf.etudiant_id = e.idetudiant
        WHERE 1=1
    ";

    // Ajout des conditions de filtrage
    $params = [];
    if (!empty($anneeAcad)) {
        $sql .= " AND p.annee_acad_idannee_acad = :anneeAcad";
        $params[':anneeAcad'] = $anneeAcad;
    }
    if (!empty($promotion)) {
        $sql .= " AND p.idpromotion = :promotion";
        $params[':promotion'] = $promotion;
    }
    if (!empty($categoriesFrais)) {
        $sql .= " AND f.categorie_id = :categoriesFrais";
        $params[':categoriesFrais'] = $categoriesFrais;
    }

    // Grouper par frais et promotion
    $sql .= ' GROUP BY f.id, p.idpromotion ORDER BY s."designationSection", p."designationPromotion", cf.designation, f.designation';

    // Exécution de la requête
    $stmt = $connexion->prepare($sql);
    foreach ($params as $param => $value) {
        $stmt->bindValue($param, $value);
    }
    $stmt->execute();
    $fraisPromotions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Récupérer les informations de filtres pour le titre du rapport
    $titreFiltre = "Tous les frais";
    $sousTitre = "";

    if (!empty($anneeAcad)) {
        $stmtAnnee = $connexion->prepare("SELECT designation FROM annee_acad WHERE idannee_acad = :id");
        $stmtAnnee->bindParam(':id', $anneeAcad);
        $stmtAnnee->execute();
        $anneeInfo = $stmtAnnee->fetch(PDO::FETCH_ASSOC);
        $sousTitre .= "Année académique: " . $anneeInfo['designation'];
    }

    if (!empty($promotion)) {
        $stmtPromo = $connexion->prepare("
            SELECT p.\"designationPromotion\", s.\"designationSection\"
            FROM promotion p
            JOIN orientation o ON p.orientation_idorientation = o.idorientation
            JOIN section s ON o.section_idsection = s.idsection
            WHERE p.idpromotion = :id
        ");
        $stmtPromo->bindParam(':id', $promotion);
        $stmtPromo->execute();
        $promoInfo = $stmtPromo->fetch(PDO::FETCH_ASSOC);
        if (!empty($sousTitre)) $sousTitre .= " | ";
        $sousTitre .= "Promotion: " . $promoInfo['designationSection'] . " - " . $promoInfo['designationPromotion'];
    }

    if (!empty($categoriesFrais)) {
        $stmtCat = $connexion->prepare("SELECT designation FROM categories_frais WHERE id = :id");
        $stmtCat->bindParam(':id', $categoriesFrais);
        $stmtCat->execute();
        $catInfo = $stmtCat->fetch(PDO::FETCH_ASSOC);
        if (!empty($sousTitre)) $sousTitre .= " | ";
        $sousTitre .= "Catégorie: " . $catInfo['designation'];
    }

    // Calcul des statistiques
    $totalFrais = count($fraisPromotions);
    $totalMontantAttendu = 0;
    $totalMontantPercu = 0;

    foreach ($fraisPromotions as $frais) {
        $totalMontantAttendu += $frais['montant'] * $frais['nb_etudiants'];
        $totalMontantPercu += $frais['montant_percu'];
    }

    // Couleurs pour le design
    $primaryColor = array(0, 87, 146); // Bleu foncé
    $secondaryColor = array(70, 130, 180); // Bleu acier
    $accentColor = array(0, 121, 194); // Bleu moyen
    $successColor = array(40, 167, 69); // Vert
    $dangerColor = array(220, 53, 69); // Rouge
    $warningColor = array(255, 193, 7); // Jaune

    // Créer une instance de TCPDF
    $pdf = new TCPDF('L', 'mm', 'A4', true, 'UTF-8', false);

    // Définir les informations du document
    $pdf->SetCreator('Système de gestion universitaire');
    $pdf->SetAuthor($configUniversite['nom'] ?? 'Administration');
    $pdf->SetTitle('Rapport des frais par promotion');
    $pdf->SetSubject('Frais par promotion');
    $pdf->SetKeywords('Frais, Promotion, Rapport, Financier');

    // Supprimer les en-têtes et pieds de page par défaut
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);

    // Définir les marges
    $pdf->SetMargins(15, 15, 15);
    $pdf->SetAutoPageBreak(true, 15);

    // Ajouter une page
    $pdf->AddPage();

    // Ajouter le logo en filigrane
    if (!empty($configUniversite['logo'])) {
        $logoPath = dirname(__DIR__) . '/' . $configUniversite['logo'];
        if (file_exists($logoPath)) {
            // Sauvegarder l'état actuel
            $pdf->setAlpha(0.1);
            
            // Position au centre
            $pageWidth = $pdf->getPageWidth();
            $pageHeight = $pdf->getPageHeight();
            
            $logoWidth = 100;
            $logoHeight = 100;
            
            $x = ($pageWidth - $logoWidth) / 2;
            $y = ($pageHeight - $logoHeight) / 2;
            
            // Ajouter l'image en filigrane
            $pdf->Image($logoPath, $x, $y, $logoWidth, $logoHeight, '', '', '', false, 300, '', false, false, 0);
            
            // Restaurer l'état
            $pdf->setAlpha(1);
        }
    }

    // En-tête avec les informations de l'université
    if ($configUniversite) {
        // Logo de l'université (visible)
        if (!empty($configUniversite['logo'])) {
            $logoPath = dirname(__DIR__) . '/' . $configUniversite['logo'];
            if (file_exists($logoPath)) {
                $pdf->Image($logoPath, 15, 15, 20, 0, '', '', '', false, 200, '', false, false, 0);
            }
        }
        
        // Titre et informations de l'université
        $pdf->SetTextColor($primaryColor[0], $primaryColor[1], $primaryColor[2]);
        $pdf->SetFont('helvetica', 'B', 10);
        $pdf->SetY(15);
        $pdf->Cell(0, 6, strtoupper($configUniversite['ministere_tutelle'] ?? ''), 0, 1, 'C');
        
        $pdf->SetFont('helvetica', 'B', 12);
        $pdf->Cell(0, 6, strtoupper($configUniversite['nom'] ?? ''), 0, 1, 'C');
        
        if (!empty($configUniversite['sigle'])) {
            $pdf->SetFont('helvetica', 'B', 10);
            $pdf->Cell(0, 4, $configUniversite['sigle'], 0, 1, 'C');
        }
        
        $pdf->SetFont('helvetica', '', 8);
        $pdf->SetTextColor(80, 80, 80);
        if (!empty($configUniversite['adresse'])) {
            $pdf->Cell(0, 4, $configUniversite['adresse'], 0, 1, 'C');
        }
        
        $contactInfo = '';
        if (!empty($configUniversite['telephone'])) {
            $contactInfo .= 'Tél: ' . $configUniversite['telephone'] . ' ';
        }
        if (!empty($configUniversite['email'])) {
            $contactInfo .= 'Email: ' . $configUniversite['email'] . ' ';
        }
        if (!empty($configUniversite['site_web'])) {
            $contactInfo .= 'Web: ' . $configUniversite['site_web'];
        }
        
        if (!empty($contactInfo)) {
            $pdf->Cell(0, 4, $contactInfo, 0, 1, 'C');
        }
        
        // Ligne de séparation élégante
        $pdf->SetLineStyle(array('width' => 0.5, 'cap' => 'butt', 'join' => 'miter', 'dash' => 0, 'color' => $accentColor));
        $pdf->Line(15, 42, $pdf->getPageWidth() - 15, 42);
    }

    // Titre du document avec fond coloré
    $pdf->SetFillColor($primaryColor[0], $primaryColor[1], $primaryColor[2]);
    $pdf->SetTextColor(255, 255, 255);
    $pdf->SetFont('helvetica', 'B', 16);
    $pdf->Ln(5);
    $pdf->Cell(0, 10, 'RAPPORT DES FRAIS PAR PROMOTION', 0, 1, 'C', true);

    // Sous-titre avec les filtres
    if (!empty($sousTitre)) {
        $pdf->SetFillColor(245, 245, 245);
        $pdf->SetTextColor($primaryColor[0], $primaryColor[1], $primaryColor[2]);
        $pdf->SetFont('helvetica', 'B', 10);
        $pdf->Ln(2);
        $pdf->Cell(0, 8, $sousTitre, 0, 1, 'C', true);
    }

    // Résumé des statistiques
    $pdf->Ln(5);
    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->SetTextColor(0, 0, 0);
    $pdf->Cell(0, 8, 'RÉSUMÉ FINANCIER', 0, 1, 'L');

    // Cadre pour le résumé financier
    $pdf->SetFillColor(245, 245, 245);
    
    // Largeur disponible
    $pageWidth = $pdf->getPageWidth() - 30; // 30 = marges gauche et droite
    
    // Création d'un cadre en fond
    $pdf->RoundedRect(15, $pdf->GetY(), $pageWidth, 30, 2.0, '1111', 'DF');
    
    // Position Y actuelle pour aligner les éléments
    $currentY = $pdf->GetY() + 5;
    
    // Définir les largeurs égales pour les 3 sections
    $boxWidth = $pageWidth / 3;
    
    // Première boîte: Nombre total de frais
    $pdf->SetY($currentY);
    $pdf->SetX(15);
    $pdf->SetFont('helvetica', 'B', 9);
    $pdf->Cell($boxWidth, 6, 'Nombre total de frais:', 0, 0, 'R');
    $pdf->SetFont('helvetica', '', 9);
    $pdf->Cell(30, 6, $totalFrais, 0, 1, 'L');
    
        // Deuxième boîte: Montant total attendu
        $pdf->SetY($currentY);
        $pdf->SetX(15 + $boxWidth);
        $pdf->SetFont('helvetica', 'B', 9);
        $pdf->Cell($boxWidth, 6, 'Montant total attendu:', 0, 0, 'R');
        $pdf->SetFont('helvetica', '', 9);
        $pdf->Cell(30, 6, number_format($totalMontantAttendu, 2, ',', ' ') . ' USD', 0, 1, 'L');
    
        // Troisième boîte: Montant total perçu
        $pdf->SetY($currentY);
        $pdf->SetX(15 + 2 * $boxWidth);
        $pdf->SetFont('helvetica', 'B', 9);
        $pdf->Cell($boxWidth - 30, 6, 'Montant total perçu:', 0, 0, 'R');
        $pdf->SetFont('helvetica', '', 9);
        $pdf->SetTextColor($successColor[0], $successColor[1], $successColor[2]);
        $pdf->Cell(30, 6, number_format($totalMontantPercu, 2, ',', ' ') . ' USD', 0, 1, 'L');
        
        // Taux de recouvrement (centré en bas du résumé)
        $tauxRecouvrement = $totalMontantAttendu > 0 ? ($totalMontantPercu / $totalMontantAttendu) * 100 : 0;
        
        // Définir la couleur du taux selon le pourcentage
        if ($tauxRecouvrement >= 90) {
            $pdf->SetTextColor($successColor[0], $successColor[1], $successColor[2]);
        } elseif ($tauxRecouvrement >= 50) {
            $pdf->SetTextColor($warningColor[0], $warningColor[1], $warningColor[2]);
        } else {
            $pdf->SetTextColor($dangerColor[0], $dangerColor[1], $dangerColor[2]);
        }
        
        $pdf->SetY($currentY + 15);
        $pdf->SetX(15);
        $pdf->SetFont('helvetica', 'B', 11);
        $pdf->Cell($pageWidth, 8, 'Taux de recouvrement global: ' . number_format($tauxRecouvrement, 1) . '%', 0, 1, 'C');
        
        // Réinitialiser la couleur du texte
        $pdf->SetTextColor(0, 0, 0);
    
        // Tableau des frais par promotion
        $pdf->Ln(8);
        $pdf->SetFont('helvetica', 'B', 12);
        $pdf->Cell(0, 8, 'DÉTAIL DES FRAIS PAR PROMOTION', 0, 1, 'L');
    
        if (count($fraisPromotions) > 0) {
            // En-têtes de colonnes avec fond coloré
            $pdf->SetFillColor($accentColor[0], $accentColor[1], $accentColor[2]);
            $pdf->SetTextColor(255, 255, 255);
            $pdf->SetFont('helvetica', 'B', 8);
            
            // Définir la largeur des colonnes (ajustée pour tenir dans la page)
            $widths = [35, 41, 60, 80, 20, 15, 15, 25, 25];
            
            $pdf->Cell($widths[1], 8, 'Promotion', 1, 0, 'C', true);
            $pdf->Cell($widths[2], 8, 'Catégorie', 1, 0, 'C', true);
            $pdf->Cell($widths[3], 8, 'Désignation', 1, 0, 'C', true);
            $pdf->Cell($widths[4], 8, 'Montant', 1, 0, 'C', true);
            $pdf->Cell($widths[5], 8, 'Étudiants', 1, 0, 'C', true);
            $pdf->Cell($widths[7], 8, 'Montant attendu', 1, 0, 'C', true);
            $pdf->Cell($widths[8], 8, 'Montant perçu', 1, 1, 'C', true);
            
            // Contenu du tableau
            $pdf->SetTextColor(0, 0, 0);
            $pdf->SetFont('helvetica', '', 8);
            
            $sectionCourante = '';
            $promotionCourante = '';
            $fillRow = false;
            
            foreach ($fraisPromotions as $frais) {
                // Alterner les couleurs de fond des lignes
                $fillRow = !$fillRow;
                $fillColor = $fillRow ? array(240, 240, 240) : array(255, 255, 255);
                $pdf->SetFillColor($fillColor[0], $fillColor[1], $fillColor[2]);
                
                // Calculer le montant attendu pour cette ligne
                $montantAttendu = $frais['montant'] * $frais['nb_etudiants'];
                $taux = $montantAttendu > 0 ? ($frais['montant_percu'] / $montantAttendu) * 100 : 0;
                
                // Afficher la section seulement si elle change (groupement visuel)
                $sectionAffichage = ($frais['section'] !== $sectionCourante) ? $frais['section'] : '';
                if ($frais['section'] !== $sectionCourante) {
                    $sectionCourante = $frais['section'];
                    // Mettre en gras la première cellule quand la section change
                    $pdf->SetFont('helvetica', 'B', 8);
                }
                
                
                // Revenir à la police normale
                $pdf->SetFont('helvetica', '', 8);
                
                // Afficher la promotion seulement si elle change (groupement visuel)
                $promotionAffichage = ($frais['promotion'] !== $promotionCourante) ? $frais['promotion'] : '';
                if ($frais['promotion'] !== $promotionCourante) {
                    $promotionCourante = $frais['promotion'];
                    // Mettre en gras la deuxième cellule quand la promotion change
                    $pdf->SetFont('helvetica', 'B', 8);
                }
                
                $pdf->Cell($widths[1], 6, $promotionAffichage, 1, 0, 'L', true);
                
                // Revenir à la police normale
                $pdf->SetFont('helvetica', '', 8);
                
                $pdf->Cell($widths[2], 6, $frais['categorie_frais'], 1, 0, 'L', true);
                $pdf->Cell($widths[3], 6, $frais['designation'], 1, 0, 'L', true);
                $pdf->Cell($widths[4], 6, number_format($frais['montant'], 2, ',', ' '), 1, 0, 'R', true);
                $pdf->Cell($widths[5], 6, $frais['nb_etudiants'], 1, 0, 'C', true);
                $pdf->Cell($widths[7], 6, number_format($montantAttendu, 2, ',', ' '), 1, 0, 'R', true);
                
                // Colorer le montant perçu selon le taux
                if ($taux >= 90) {
                    $pdf->SetTextColor($successColor[0], $successColor[1], $successColor[2]);
                } elseif ($taux >= 50) {
                    $pdf->SetTextColor($warningColor[0], $warningColor[1], $warningColor[2]);
                } else {
                    $pdf->SetTextColor($dangerColor[0], $dangerColor[1], $dangerColor[2]);
                }
                
                $pdf->Cell($widths[8], 6, number_format($frais['montant_percu'], 2, ',', ' ') . ' (' . number_format($taux, 1) . '%)', 1, 1, 'R', true);
                
                // Réinitialiser la couleur du texte
                $pdf->SetTextColor(0, 0, 0);
                
                // Vérifier si on doit passer à une nouvelle page
                if ($pdf->GetY() > $pdf->getPageHeight() - 25) {
                    $pdf->AddPage();
                    
                    // Réafficher les en-têtes du tableau
                    $pdf->SetFillColor($accentColor[0], $accentColor[1], $accentColor[2]);
                    $pdf->SetTextColor(255, 255, 255);
                    $pdf->SetFont('helvetica', 'B', 8);
                    
                    $pdf->Cell($widths[1], 8, 'Promotion', 1, 0, 'C', true);
                    $pdf->Cell($widths[2], 8, 'Catégorie', 1, 0, 'C', true);
                    $pdf->Cell($widths[3], 8, 'Désignation', 1, 0, 'C', true);
                    $pdf->Cell($widths[4], 8, 'Montant', 1, 0, 'C', true);
                    $pdf->Cell($widths[5], 8, 'Étudiants', 1, 0, 'C', true);
                    $pdf->Cell($widths[7], 8, 'Montant attendu', 1, 0, 'C', true);
                    $pdf->Cell($widths[8], 8, 'Montant perçu', 1, 1, 'C', true);
                    
                    $pdf->SetTextColor(0, 0, 0);
                    $pdf->SetFont('helvetica', '', 8);
                }
            }
        } else {
            $pdf->SetFont('helvetica', 'I', 10);
            $pdf->Cell(0, 10, 'Aucun frais trouvé avec les critères sélectionnés.', 0, 1, 'C');
        }
    
        // Ajouter une page pour les signatures et le QR code si nécessaire
        if ($pdf->GetY() > $pdf->getPageHeight() - 60) {
            $pdf->AddPage();
        } else {
            // Ajouter un espace pour séparer le tableau du pied de page
            $pdf->Ln(10);
        }
    
        // Générer le QR code avec les informations du rapport
        $qrData = "RAPPORT DES FRAIS PAR PROMOTION\n";
        $qrData .= $sousTitre . "\n";
        $qrData .= "FRAIS: " . $totalFrais . "\n";
        $qrData .= "MONTANT ATTENDU: " . number_format($totalMontantAttendu, 2, ',', ' ') . " USD\n";
        $qrData .= "MONTANT PERCU: " . number_format($totalMontantPercu, 2, ',', ' ') . " USD\n";
        $qrData .= "TAUX: " . number_format($tauxRecouvrement, 1) . "%\n";
        $qrData .= "DATE: " . date('d/m/Y') . "\n";
        
        $style = array(
            'border' => 2,
            'vpadding' => 'auto',
            'hpadding' => 'auto',
            'fgcolor' => array(0, 0, 0),
            'bgcolor' => array(255, 255, 255),
            'module_width' => 1,
            'module_height' => 1
        );
        
        // Positionner le QR code en bas à droite
        $pdf->write2DBarcode($qrData, 'QRCODE,L', $pdf->getPageWidth() - 40, $pdf->GetY(), 25, 25, $style, 'N');
        
        // Section des signatures (alignée de manière plus précise)
        $pdf->SetY($pdf->GetY());
        $pdf->SetFont('helvetica', 'B', 10);
        $pdf->SetTextColor(0, 0, 0);
        
        // Texte des signatures
        $pdf->SetX(25);
        $pdf->Cell(60, 6, 'LE COMPTABLE', 0, 0, 'C');
        
        $pdf->SetX($pdf->getPageWidth() - 140);
        $pdf->Cell(60, 6, 'L\'ADMINISTRATEUR DU BUDGET', 0, 1, 'C');
        
        // Espace pour les signatures
        $pdf->Ln(25);
        
        // Lignes pour les signatures
        $pdf->SetLineStyle(array('width' => 0.5, 'cap' => 'butt', 'join' => 'miter', 'dash' => 0, 'color' => array(180, 180, 180)));
        $pdf->Line(25, $pdf->GetY(), 85, $pdf->GetY()); // Ligne pour le comptable
        $pdf->Line($pdf->getPageWidth() - 140, $pdf->GetY(), $pdf->getPageWidth() - 80, $pdf->GetY()); // Ligne pour le directeur
        
        // Pied de page
        $pdf->SetY($pdf->getPageHeight() - 15);
        $pdf->SetFont('helvetica', 'I', 8);
        $pdf->SetTextColor(100, 100, 100);
        $pdf->Cell(0, 5, 'Ce document est un rapport financier officiel de l\'établissement.', 0, 1, 'C');
        $pdf->Cell(0, 5, 'Document généré le ' . date('d/m/Y à H:i') . ' par le système de gestion universitaire', 0, 1, 'C');
        
            // Nom du fichier de sortie
    $filename = 'Rapport_Frais_Promotion_' . date('Y-m-d_H-i') . '.pdf';
    
    // Générer le PDF
    $pdf->Output($filename, 'I');
    
} catch (Exception $e) {
    // En cas d'erreur
    error_log('Erreur dans export_frais_promotion.php: ' . $e->getMessage());
    echo "Une erreur est survenue lors de la génération du rapport: " . $e->getMessage();
}

    
