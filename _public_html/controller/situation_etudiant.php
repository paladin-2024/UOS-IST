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

// Récupérer l'ID de l'étudiant
$etudiantId = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($etudiantId <= 0) {
    die("ID d'étudiant non valide");
}

// Fonction pour convertir un nombre en lettres
function numberToWords($number, $devise = 'USD') {
    $dictionary = [
        0 => 'zéro', 1 => 'un', 2 => 'deux', 3 => 'trois', 4 => 'quatre', 
        5 => 'cinq', 6 => 'six', 7 => 'sept', 8 => 'huit', 9 => 'neuf', 
        10 => 'dix', 11 => 'onze', 12 => 'douze', 13 => 'treize', 14 => 'quatorze', 
        15 => 'quinze', 16 => 'seize', 17 => 'dix-sept', 18 => 'dix-huit', 19 => 'dix-neuf',
        20 => 'vingt', 30 => 'trente', 40 => 'quarante', 50 => 'cinquante', 
        60 => 'soixante', 70 => 'soixante-dix', 80 => 'quatre-vingts', 90 => 'quatre-vingt-dix'
    ];
    
    // Séparer la partie entière et la partie décimale
    $parts = explode('.', (string)number_format($number, 2, '.', ''));
    $integer = (int)$parts[0];
    $decimal = (int)$parts[1];
    
    if ($integer == 0) {
        $words = $dictionary[0];
    } else {
        $words = '';
        // Traiter les milliards
        if ($integer >= 1000000000) {
            $billions = intval($integer / 1000000000);
            $words .= ($billions > 1) ? numberToWords($billions, '') . ' milliards ' : 'un milliard ';
            $integer = $integer % 1000000000;
        }
        
        // Traiter les millions
        if ($integer >= 1000000) {
            $millions = intval($integer / 1000000);
            $words .= ($millions > 1) ? numberToWords($millions, '') . ' millions ' : 'un million ';
            $integer = $integer % 1000000;
        }
        
        // Traiter les milliers
        if ($integer >= 1000) {
            $thousands = intval($integer / 1000);
            $words .= ($thousands > 1) ? numberToWords($thousands, '') . ' mille ' : 'mille ';
            $integer = $integer % 1000;
        }
        
        // Traiter les centaines
        if ($integer >= 100) {
            $hundreds = intval($integer / 100);
            $words .= ($hundreds > 1) ? $dictionary[$hundreds] . ' cents ' : 'cent ';
            $integer = $integer % 100;
        }
        
        // Traiter les dizaines et unités
        if ($integer > 0) {
            if ($integer < 20) {
                $words .= $dictionary[$integer];
            } else {
                $tens = intval($integer / 10) * 10;
                $units = $integer % 10;
                
                if ($tens == 70) {
                    $words .= 'soixante-';
                    $words .= $dictionary[10 + $units];
                } elseif ($tens == 90) {
                    $words .= 'quatre-vingt-';
                    $words .= $dictionary[10 + $units];
                } else {
                    $words .= $dictionary[$tens];
                    if ($units > 0) {
                        $words .= '-' . $dictionary[$units];
                    }
                }
            }
        }
    }
    
    // Ajouter la partie décimale
    if ($decimal > 0) {
        $words .= ' virgule ';
        if ($decimal < 20) {
            $words .= $dictionary[$decimal];
        } else {
            $decimal_tens = intval($decimal / 10) * 10;
            $decimal_units = $decimal % 10;
            $words .= $dictionary[$decimal_tens];
            if ($decimal_units > 0) {
                $words .= '-' . $dictionary[$decimal_units];
            }
        }
    }
    
    // Ajouter la devise
    if (!empty($devise)) {
        switch(strtoupper($devise)) {
            case 'USD':
                $words .= ' dollars américains';
                break;
            case 'EUR':
                $words .= ' euros';
                break;
            case 'CDF':
                $words .= ' francs congolais';
                break;
            default:
                $words .= ' ' . $devise;
        }
    }
    
    return ucfirst(trim($words));
}

try {
    // Initialisation
    $connexion = Connexion::getInstance()->getPDO();
    
    // Récupérer les informations de l'étudiant
    $sqlEtudiant = "SELECT 
                    e.*, 
                    p.designationPromotion, 
                    s.designationSection,
                    o.designationOrientation,
                    a.designation as annee_academique,
                    p.idpromotion as promotion_id
                FROM etudiant e
                JOIN promotion p ON e.promotion_idpromotion = p.idpromotion
                JOIN orientation o ON p.orientation_idorientation = o.idorientation
                JOIN section s ON o.section_idsection = s.idsection
                JOIN annee_acad a ON p.annee_acad_idannee_acad = a.idannee_acad
                WHERE e.idetudiant = :etudiantId";

    $stmtEtudiant = $connexion->prepare($sqlEtudiant);
    $stmtEtudiant->bindParam(':etudiantId', $etudiantId, PDO::PARAM_INT);
    $stmtEtudiant->execute();
    $etudiant = $stmtEtudiant->fetch(PDO::FETCH_ASSOC);
    
    if (!$etudiant) {
        die("Étudiant non trouvé");
    }
    
    $matricule = $etudiant['matricule'];
    $promotion_id = $etudiant['promotion_id'];
    
    // 1. Récupérer les frais individuels de l'étudiant
    $stmt_individuels = $connexion->prepare("
        SELECT 
            af.id, 
            af.frais_id,
            af.promotion_id,
            af.matricule_etudiant,
            af.montant_specifique,
            af.date_affectation,
            af.devise,
            af.statut_paiement,
            af.est_exempte,
            f.designation AS frais_designation, 
            f.est_echelonnable,
            f.montant AS montant_frais,
            f.devise AS devise_frais,
            cf.designation AS categorie_nom,
            aa.designation AS annee_academique,
            p.designationPromotion AS promotion_nom,
            (SELECT COALESCE(SUM(pf.montant), 0) 
             FROM paiements_frais pf 
             WHERE pf.affectation_id = af.id 
             AND pf.matricule_etudiant = :matricule) AS montant_paye
        FROM affectation_frais af
        INNER JOIN frais f ON af.frais_id = f.id
        LEFT JOIN categories_frais cf ON f.categorie_id = cf.id
        LEFT JOIN annee_acad aa ON f.annee_acad_id = aa.idannee_acad
        LEFT JOIN promotion p ON af.promotion_id = p.idpromotion
        WHERE af.matricule_etudiant = :matricule
        AND af.est_exempte = 0
    ");

    $stmt_individuels->bindParam(':matricule', $matricule);
    $stmt_individuels->execute();
    $frais_individuels = $stmt_individuels->fetchAll(PDO::FETCH_ASSOC);

    // 2. Récupérer les frais de promotion (sans les frais déjà affectés individuellement)
    $stmt_promotion = $connexion->prepare("
        SELECT 
            af.id,
            af.frais_id,
            af.promotion_id,
            af.matricule_etudiant,
            af.montant_specifique,
            af.date_affectation,
            af.devise,
            af.statut_paiement,
            af.est_exempte, 
            f.designation AS frais_designation, 
            f.est_echelonnable,
            f.montant AS montant_frais,
            f.devise AS devise_frais,
            cf.designation AS categorie_nom,
            aa.designation AS annee_academique,
            p.designationPromotion AS promotion_nom,
            (SELECT COALESCE(SUM(pf.montant), 0) 
             FROM paiements_frais pf 
             WHERE pf.affectation_id = af.id 
             AND pf.matricule_etudiant = :matricule) AS montant_paye
        FROM affectation_frais af
        INNER JOIN frais f ON af.frais_id = f.id
        LEFT JOIN categories_frais cf ON f.categorie_id = cf.id
        LEFT JOIN annee_acad aa ON f.annee_acad_id = aa.idannee_acad
        LEFT JOIN promotion p ON af.promotion_id = p.idpromotion
        WHERE af.promotion_id = :promotion_id
        AND af.matricule_etudiant IS NULL
        AND af.est_exempte = 0
        AND NOT EXISTS (
            SELECT 1 FROM affectation_frais af2 
            WHERE af2.frais_id = af.frais_id 
            AND af2.matricule_etudiant = :matricule2
        )
    ");

    $stmt_promotion->bindParam(':matricule', $matricule);
    $stmt_promotion->bindParam(':matricule2', $matricule);
    $stmt_promotion->bindParam(':promotion_id', $promotion_id);
    $stmt_promotion->execute();
    $frais_promotion = $stmt_promotion->fetchAll(PDO::FETCH_ASSOC);
    
    // Fonction pour calculer les montants restants et statuts pour un tableau de frais
    function processFrais(&$frais) {
        foreach ($frais as &$affectation) {
            // Déterminer le montant total du frais
            $montant_total = $affectation['montant_specifique'] > 0 ? $affectation['montant_specifique'] : $affectation['montant_frais'];
            $affectation['montant_total'] = $montant_total;
            $affectation['montant_restant'] = $montant_total - $affectation['montant_paye'];

            // Mise à jour du statut de paiement basé sur les paiements réels de l'étudiant
            if ($affectation['montant_paye'] >= $montant_total) {
                $affectation['statut_paiement_etudiant'] = 'Complet';
            } elseif ($affectation['montant_paye'] > 0) {
                $affectation['statut_paiement_etudiant'] = 'Partiel';
            } else {
                $affectation['statut_paiement_etudiant'] = 'Non payé';
            }

            // Initialiser un tableau vide pour les tranches
            $affectation['tranches'] = [];
        }
    }

    // Traitement des frais individuels et des frais de promotion
    processFrais($frais_individuels);
    processFrais($frais_promotion);

    // Récupérer les tranches pour les frais échelonnables
    function loadTranches(&$frais, $connexion, $matricule) {
        foreach ($frais as &$affectation) {
            // Ne traiter que les frais échelonnables
            if ($affectation['est_echelonnable'] == 1) {
                $stmt = $connexion->prepare("
                    SELECT ep.*, 
                           (SELECT COALESCE(SUM(pt.montant), 0) 
                            FROM paiements_tranches pt
                            JOIN paiements_frais pf ON pt.paiement_id = pf.id
                            WHERE pt.echelonnement_id = ep.id 
                            AND pf.matricule_etudiant = :matricule) AS montant_paye
                    FROM echelonnement_paiement ep
                    WHERE ep.affectation_id = :affectation_id
                    ORDER BY ep.numero_tranche
                ");
                $stmt->bindParam(':affectation_id', $affectation['id']);
                $stmt->bindParam(':matricule', $matricule);
                $stmt->execute();
                $affectation['tranches'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

                // Calculer le montant restant pour chaque tranche
                foreach ($affectation['tranches'] as &$tranche) {
                    $tranche['montant_restant'] = $tranche['montant'] - $tranche['montant_paye'];

                    // Mettre à jour le statut de la tranche
                    if ($tranche['montant_paye'] >= $tranche['montant']) {
                        $tranche['statut_paiement'] = 'Complet';
                    } elseif ($tranche['montant_paye'] > 0) {
                        $tranche['statut_paiement'] = 'Partiel';
                    } else {
                        $tranche['statut_paiement'] = 'Non payé';
                    }
                }
            }
        }
    }

    // Chargement des tranches
    loadTranches($frais_individuels, $connexion, $matricule);
    loadTranches($frais_promotion, $connexion, $matricule);
    
    // Récupérer l'historique des paiements
    $sqlPaiements = "
        SELECT pf.*, 
               af.id AS affectation_id,
               f.designation AS frais_designation,
               t.reference AS transaction_reference,
               t.date_transaction,
               t.source,
               t.source_id,
                              u.nomUser AS agent_nom,
               CASE 
                   WHEN t.source = 'Caisse' THEN (SELECT designation FROM caisses WHERE id = t.source_id)
                   WHEN t.source = 'Banque' THEN (SELECT CONCAT(nom_banque, ' - ', intitule_compte) FROM comptes_bancaires WHERE id = t.source_id)
                   ELSE 'Non spécifié'
               END AS source_nom
        FROM paiements_frais pf
        INNER JOIN affectation_frais af ON pf.affectation_id = af.id
        INNER JOIN frais f ON af.frais_id = f.id
        LEFT JOIN transactions t ON pf.transaction_id = t.id
        LEFT JOIN t_users u ON t.idUser = u.idUser
        WHERE pf.matricule_etudiant = :matricule
        ORDER BY t.date_transaction DESC
    ";
    $stmtPaiements = $connexion->prepare($sqlPaiements);
    $stmtPaiements->bindParam(':matricule', $matricule);
    $stmtPaiements->execute();
    $historique_paiements = $stmtPaiements->fetchAll(PDO::FETCH_ASSOC);
    
    // Calcul du solde total de l'étudiant par devise
    $totaux_par_devise = [];

    // Calculer les totaux à partir des frais individuels
    foreach ($frais_individuels as $frais) {
        // Déterminer la devise avec une logique plus robuste
        $devise = !empty($frais['devise']) ? $frais['devise'] : 
                 (!empty($frais['devise_frais']) ? $frais['devise_frais'] : 'USD');
        
        // Nettoyer la devise (enlever les espaces)
        $devise = trim($devise);
        if (empty($devise)) {
            $devise = 'USD'; // Devise par défaut
        }
        
        if (!isset($totaux_par_devise[$devise])) {
            $totaux_par_devise[$devise] = [
                'total_du' => 0,
                'total_paye' => 0,
                'solde_restant' => 0
            ];
        }
        
        $totaux_par_devise[$devise]['total_du'] += $frais['montant_total'];
        $totaux_par_devise[$devise]['total_paye'] += $frais['montant_paye'];
    }

    // Calculer les totaux à partir des frais de promotion
    foreach ($frais_promotion as $frais) {
        // Déterminer la devise avec une logique plus robuste
        $devise = !empty($frais['devise']) ? $frais['devise'] : 
                 (!empty($frais['devise_frais']) ? $frais['devise_frais'] : 'USD');
        
        // Nettoyer la devise (enlever les espaces)
        $devise = trim($devise);
        if (empty($devise)) {
            $devise = 'USD'; // Devise par défaut
        }
        
        if (!isset($totaux_par_devise[$devise])) {
            $totaux_par_devise[$devise] = [
                'total_du' => 0,
                'total_paye' => 0,
                'solde_restant' => 0
            ];
        }
        
        $totaux_par_devise[$devise]['total_du'] += $frais['montant_total'];
        $totaux_par_devise[$devise]['total_paye'] += $frais['montant_paye'];
    }

    // Calculer le solde restant pour chaque devise
    foreach ($totaux_par_devise as $devise => &$totaux) {
        $totaux['solde_restant'] = $totaux['total_du'] - $totaux['total_paye'];
    }

    // Maintenir la compatibilité avec l'ancien code (pour USD par défaut)
    $total_du = isset($totaux_par_devise['USD']) ? $totaux_par_devise['USD']['total_du'] : 0;
    $total_paye = isset($totaux_par_devise['USD']) ? $totaux_par_devise['USD']['total_paye'] : 0;
    $solde_total = isset($totaux_par_devise['USD']) ? $totaux_par_devise['USD']['solde_restant'] : 0;
    
    // Récupérer les informations de l'université
    $universite = new Universite();
    $configUniversite = $universite->getConfigurationUniversite();
    
    // Couleurs pour le design
    $primaryColor = array(0, 87, 146); // Bleu foncé
    $secondaryColor = array(70, 130, 180); // Bleu acier
    $accentColor = array(0, 121, 194); // Bleu moyen
    $successColor = array(40, 167, 69); // Vert
    $dangerColor = array(220, 53, 69); // Rouge
    $warningColor = array(255, 193, 7); // Jaune
    
    // Créer une instance de TCPDF
    $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
    
    // Définir les informations du document
    $pdf->SetCreator('Système de gestion universitaire');
    $pdf->SetAuthor($configUniversite['nom'] ?? 'Administration');
    $pdf->SetTitle('Situation Financière - ' . $etudiant['noms']);
    $pdf->SetSubject('Situation financière de l\'étudiant');
    $pdf->SetKeywords('Situation, Financière, Étudiant, Frais');
    
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
    $pdf->Cell(0, 10, 'SITUATION FINANCIÈRE DE L\'ÉTUDIANT', 0, 1, 'C', true);
    
    // Année académique
    $pdf->SetFillColor(245, 245, 245);
    $pdf->SetTextColor($primaryColor[0], $primaryColor[1], $primaryColor[2]);
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->Ln(5);
    
    // Boîte pour l'année académique
    $pdf->RoundedRect(15, $pdf->GetY(), 180, 10, 2.0, '1111', 'DF', array(), array(245, 245, 245));
    $pdf->SetY($pdf->GetY() + 2.5);
    $pdf->Cell(0, 6, 'Année académique: ' . ($etudiant['annee_academique'] ?? 'Non spécifiée'), 0, 1, 'C');
    
    // Informations de l'étudiant
    $pdf->Ln(5);
    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->SetTextColor(0, 0, 0);
    $pdf->Cell(0, 8, 'INFORMATIONS DE L\'ÉTUDIANT', 0, 1, 'L');
    
    // Cadre d'information étudiant avec fond légèrement coloré
    $pdf->SetFillColor(240, 248, 255); // Bleu très clair
    $pdf->RoundedRect(15, $pdf->GetY(), 180, 40, 2.0, '1111', 'DF');
    
    $currentY = $pdf->GetY() + 5;
    $pdf->SetY($currentY);
    
    // Première colonne
    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->Cell(40, 8, 'Nom complet:', 0, 0, 'L');
    $pdf->SetFont('helvetica', '', 10);
    $pdf->Cell(140, 8, $etudiant['noms'], 0, 1, 'L');
    
    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->Cell(40, 8, 'Matricule:', 0, 0, 'L');
    $pdf->SetFont('helvetica', '', 10);
    $pdf->Cell(140, 8, $etudiant['matricule'], 0, 1, 'L');
    
    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->Cell(40, 8, 'Promotion:', 0, 0, 'L');
    $pdf->SetFont('helvetica', '', 10);
    $pdf->Cell(140, 8, $etudiant['designationPromotion'] ?? 'Non spécifiée', 0, 1, 'L');
    
    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->Cell(40, 8, 'Faculté/Section:', 0, 0, 'L');
    $pdf->SetFont('helvetica', '', 10);
    $pdf->Cell(140, 8, ($etudiant['designationSection'] . ' - ' . $etudiant['designationOrientation']) ?? 'Non spécifiée', 0, 1, 'L');
    
    // Résumé financier par devise
    $pdf->Ln(5);
    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->SetTextColor(0, 0, 0);
    $pdf->Cell(0, 8, 'RÉSUMÉ FINANCIER PAR DEVISE', 0, 1, 'L');
    
    if (!empty($totaux_par_devise)) {
        $hauteur_totale = count($totaux_par_devise) * 20 + 15; // Calculer la hauteur nécessaire
        
        // Cadre pour le résumé financier
        $pdf->SetFillColor(245, 245, 245);
        $pdf->RoundedRect(15, $pdf->GetY(), 180, $hauteur_totale, 2.0, '1111', 'DF');
        
        $currentY = $pdf->GetY() + 5;
        $pdf->SetY($currentY);
        
        foreach ($totaux_par_devise as $devise_courante => $totaux_devise) {
            // Titre de la devise
            $pdf->SetFont('helvetica', 'B', 9);
            $pdf->SetTextColor($primaryColor[0], $primaryColor[1], $primaryColor[2]);
            $pdf->Cell(0, 6, 'DEVISE: ' . strtoupper($devise_courante), 0, 1, 'L');
            
            // Montant total dû
            $pdf->SetFont('helvetica', 'B', 9);
            $pdf->SetTextColor(0, 0, 0);
            $pdf->Cell(60, 5, 'Montant total dû:', 0, 0, 'L');
            $pdf->SetFont('helvetica', '', 9);
            $pdf->Cell(120, 5, number_format($totaux_devise['total_du'], 2, ',', ' ') . ' ' . $devise_courante, 0, 1, 'L');
            
            // Montant total payé
            $pdf->SetFont('helvetica', 'B', 9);
            $pdf->Cell(60, 5, 'Montant total payé:', 0, 0, 'L');
            $pdf->SetFont('helvetica', '', 9);
            $pdf->SetTextColor($successColor[0], $successColor[1], $successColor[2]);
            $pdf->Cell(120, 5, number_format($totaux_devise['total_paye'], 2, ',', ' ') . ' ' . $devise_courante, 0, 1, 'L');
            
            // Solde restant
            $pdf->SetFont('helvetica', 'B', 9);
            $pdf->SetTextColor(0, 0, 0);
            $pdf->Cell(60, 5, 'Solde restant:', 0, 0, 'L');
            $pdf->SetFont('helvetica', '', 9);
            if ($totaux_devise['solde_restant'] <= 0) {
                $pdf->SetTextColor($successColor[0], $successColor[1], $successColor[2]);
                $pdf->Cell(120, 5, 'PAYÉ INTÉGRALEMENT', 0, 1, 'L');
            } else {
                $pdf->SetTextColor($dangerColor[0], $dangerColor[1], $dangerColor[2]);
                $pdf->Cell(120, 5, number_format($totaux_devise['solde_restant'], 2, ',', ' ') . ' ' . $devise_courante, 0, 1, 'L');
            }
            $pdf->SetTextColor(0, 0, 0);
            
            // Ligne de séparation entre les devises (sauf pour la dernière)
            if ($devise_courante !== array_key_last($totaux_par_devise)) {
                $pdf->Ln(2);
                $pdf->SetLineStyle(array('width' => 0.2, 'cap' => 'butt', 'join' => 'miter', 'dash' => 0, 'color' => array(200, 200, 200)));
                $pdf->Line(20, $pdf->GetY(), $pdf->getPageWidth() - 20, $pdf->GetY());
                $pdf->Ln(2);
            }
        }
    } else {
        // Cadre pour le message d'absence de frais
        $pdf->SetFillColor(245, 245, 245);
        $pdf->RoundedRect(15, $pdf->GetY(), 180, 15, 2.0, '1111', 'DF');
        
        $currentY = $pdf->GetY() + 5;
        $pdf->SetY($currentY);
        
        $pdf->SetFont('helvetica', 'I', 10);
        $pdf->SetTextColor(100, 100, 100);
        $pdf->Cell(0, 6, 'Aucun frais assigné à cet étudiant.', 0, 1, 'C');
    }
    $pdf->SetTextColor(0, 0, 0);
    
    // Frais individuels
    $pdf->Ln(5);
    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->Cell(0, 8, 'FRAIS INDIVIDUELS', 0, 1, 'L');
    
    if (empty($frais_individuels)) {
        $pdf->SetFont('helvetica', 'I', 10);
        $pdf->Cell(0, 8, 'Aucun frais individuel assigné à cet étudiant.', 0, 1, 'L');
    } else {
        // En-têtes de colonnes avec fond coloré
        $pdf->SetFillColor($accentColor[0], $accentColor[1], $accentColor[2]);
        $pdf->SetTextColor(255, 255, 255);
        $pdf->SetFont('helvetica', 'B', 8);
        
        $pdf->Cell(60, 8, 'Frais', 1, 0, 'C', true);
        $pdf->Cell(30, 8, 'Catégorie', 1, 0, 'C', true);
        $pdf->Cell(30, 8, 'Montant total', 1, 0, 'C', true);
        $pdf->Cell(30, 8, 'Montant payé', 1, 0, 'C', true);
        $pdf->Cell(30, 8, 'Reste à payer', 1, 1, 'C', true);
        
        // Contenu de la table des frais individuels
        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetFont('helvetica', '', 8);
        
        foreach ($frais_individuels as $frais) {
            $devise = $frais['devise'] ?: $frais['devise_frais'];
            
            $pdf->Cell(60, 8, $frais['frais_designation'], 1, 0, 'L');
            $pdf->Cell(30, 8, $frais['categorie_nom'] ?? 'Non spécifiée', 1, 0, 'L');
            $pdf->Cell(30, 8, number_format($frais['montant_total'], 2, ',', ' ') . ' ' . $devise, 1, 0, 'R');
            $pdf->Cell(30, 8, number_format($frais['montant_paye'], 2, ',', ' ') . ' ' . $devise, 1, 0, 'R');
            
            // Colorer le montant restant selon le statut
            if ($frais['montant_restant'] <= 0) {
                $pdf->SetTextColor($successColor[0], $successColor[1], $successColor[2]);
            } else {
                $pdf->SetTextColor($dangerColor[0], $dangerColor[1], $dangerColor[2]);
            }
            
            $pdf->Cell(30, 8, number_format($frais['montant_restant'], 2, ',', ' ') . ' ' . $devise, 1, 1, 'R');
            $pdf->SetTextColor(0, 0, 0);
            
            // Ajouter les tranches si le frais est échelonnable
            if ($frais['est_echelonnable'] == 1 && !empty($frais['tranches'])) {
                $pdf->SetFillColor(245, 245, 245);
                $pdf->SetFont('helvetica', 'I', 7);
                $pdf->Cell(15, 6, '', 0, 0, 'L');
                $pdf->Cell(165, 6, 'Tranches de paiement pour ' . $frais['frais_designation'], 0, 1, 'L');
                
                // En-têtes des tranches
                $pdf->SetFillColor(220, 220, 220);
                $pdf->SetFont('helvetica', 'B', 7);
                $pdf->Cell(15, 6, '', 0, 0, 'L');
                $pdf->Cell(10, 6, 'N°', 1, 0, 'C', true);
                $pdf->Cell(50, 6, 'Désignation', 1, 0, 'C', true);
                $pdf->Cell(25, 6, 'Échéance', 1, 0, 'C', true);
                $pdf->Cell(25, 6, 'Montant', 1, 0, 'C', true);
                $pdf->Cell(25, 6, 'Payé', 1, 0, 'C', true);
                $pdf->Cell(30, 6, 'Reste', 1, 1, 'C', true);
                
                // Contenu des tranches
                $pdf->SetFont('helvetica', '', 7);
                foreach ($frais['tranches'] as $tranche) {
                    $pdf->Cell(15, 6, '', 0, 0, 'L');
                    $pdf->Cell(10, 6, $tranche['numero_tranche'], 1, 0, 'C');
                    $pdf->Cell(50, 6, $tranche['designation'], 1, 0, 'L');
                    $pdf->Cell(25, 6, date('d/m/Y', strtotime($tranche['date_echeance'])), 1, 0, 'C');
                    $pdf->Cell(25, 6, number_format($tranche['montant'], 2, ',', ' ') . ' ' . $devise, 1, 0, 'R');
                    $pdf->Cell(25, 6, number_format($tranche['montant_paye'], 2, ',', ' ') . ' ' . $devise, 1, 0, 'R');
                    
                    // Colorer le montant restant selon le statut
                    if ($tranche['montant_restant'] <= 0) {
                        $pdf->SetTextColor($successColor[0], $successColor[1], $successColor[2]);
                    } else {
                        $pdf->SetTextColor($dangerColor[0], $dangerColor[1], $dangerColor[2]);
                    }
                    
                    $pdf->Cell(30, 6, number_format($tranche['montant_restant'], 2, ',', ' ') . ' ' . $devise, 1, 1, 'R');
                    $pdf->SetTextColor(0, 0, 0);
                }
                
                $pdf->Ln(2);
            }
        }
    }
    
    // Frais de promotion
    $pdf->Ln(5);
    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->Cell(0, 8, 'FRAIS DE PROMOTION', 0, 1, 'L');
    
    if (empty($frais_promotion)) {
        $pdf->SetFont('helvetica', 'I', 10);
        $pdf->Cell(0, 8, 'Aucun frais de promotion applicable à cet étudiant.', 0, 1, 'L');
    } else {
        // En-têtes de colonnes avec fond coloré
        $pdf->SetFillColor($accentColor[0], $accentColor[1], $accentColor[2]);
        $pdf->SetTextColor(255, 255, 255);
        $pdf->SetFont('helvetica', 'B', 8);
        
        $pdf->Cell(60, 8, 'Frais', 1, 0, 'C', true);
        $pdf->Cell(30, 8, 'Catégorie', 1, 0, 'C', true);
        $pdf->Cell(30, 8, 'Montant total', 1, 0, 'C', true);
        $pdf->Cell(30, 8, 'Montant payé', 1, 0, 'C', true);
        $pdf->Cell(30, 8, 'Reste à payer', 1, 1, 'C', true);
        
        // Contenu de la table des frais de promotion
        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetFont('helvetica', '', 8);
        
        foreach ($frais_promotion as $frais) {
            $devise = $frais['devise'] ?: $frais['devise_frais'];
            
            $pdf->Cell(60, 8, $frais['frais_designation'], 1, 0, 'L');
            $pdf->Cell(30, 8, $frais['categorie_nom'] ?? 'Non spécifiée', 1, 0, 'L');
            $pdf->Cell(30, 8, number_format($frais['montant_total'], 2, ',', ' ') . ' ' . $devise, 1, 0, 'R');
            $pdf->Cell(30, 8, number_format($frais['montant_paye'], 2, ',', ' ') . ' ' . $devise, 1, 0, 'R');
            
            // Colorer le montant restant selon le statut
            if ($frais['montant_restant'] <= 0) {
                $pdf->SetTextColor($successColor[0], $successColor[1], $successColor[2]);
            } else {
                $pdf->SetTextColor($dangerColor[0], $dangerColor[1], $dangerColor[2]);
            }
            
            $pdf->Cell(30, 8, number_format($frais['montant_restant'], 2, ',', ' ') . ' ' . $devise, 1, 1, 'R');
            $pdf->SetTextColor(0, 0, 0);
            
            // Ajouter les tranches si le frais est échelonnable
            if ($frais['est_echelonnable'] == 1 && !empty($frais['tranches'])) {
                $pdf->SetFillColor(245, 245, 245);
                $pdf->SetFont('helvetica', 'I', 7);
                $pdf->Cell(15, 6, '', 0, 0, 'L');
                $pdf->Cell(165, 6, 'Tranches de paiement pour ' . $frais['frais_designation'], 0, 1, 'L');
                
                // En-têtes des tranches
                $pdf->SetFillColor(220, 220, 220);
                $pdf->SetFont('helvetica', 'B', 7);
                $pdf->Cell(15, 6, '', 0, 0, 'L');
                $pdf->Cell(10, 6, 'N°', 1, 0, 'C', true);
                $pdf->Cell(50, 6, 'Désignation', 1, 0, 'C', true);
                $pdf->Cell(25, 6, 'Échéance', 1, 0, 'C', true);
                $pdf->Cell(25, 6, 'Montant', 1, 0, 'C', true);
                $pdf->Cell(25, 6, 'Payé', 1, 0, 'C', true);
                $pdf->Cell(30, 6, 'Reste', 1, 1, 'C', true);
                
                // Contenu des tranches
                $pdf->SetFont('helvetica', '', 7);
                foreach ($frais['tranches'] as $tranche) {
                    $pdf->Cell(15, 6, '', 0, 0, 'L');
                    $pdf->Cell(10, 6, $tranche['numero_tranche'], 1, 0, 'C');
                    $pdf->Cell(50, 6, $tranche['designation'], 1, 0, 'L');
                    $pdf->Cell(25, 6, date('d/m/Y', strtotime($tranche['date_echeance'])), 1, 0, 'C');
                    $pdf->Cell(25, 6, number_format($tranche['montant'], 2, ',', ' ') . ' ' . $devise, 1, 0, 'R');
                    $pdf->Cell(25, 6, number_format($tranche['montant_paye'], 2, ',', ' ') . ' ' . $devise, 1, 0, 'R');
                    
                    // Colorer le montant restant selon le statut
                    if ($tranche['montant_restant'] <= 0) {
                        $pdf->SetTextColor($successColor[0], $successColor[1], $successColor[2]);
                    } else {
                        $pdf->SetTextColor($dangerColor[0], $dangerColor[1], $dangerColor[2]);
                    }
                    
                    $pdf->Cell(30, 6, number_format($tranche['montant_restant'], 2, ',', ' ') . ' ' . $devise, 1, 1, 'R');
                    $pdf->SetTextColor(0, 0, 0);
                }
                
                $pdf->Ln(2);
            }
        }
    }
    
    // Historique des paiements
    $pdf->AddPage();
    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->Cell(0, 8, 'HISTORIQUE DES PAIEMENTS', 0, 1, 'L');
    
    if (empty($historique_paiements)) {
        $pdf->SetFont('helvetica', 'I', 10);
        $pdf->Cell(0, 8, 'Aucun paiement enregistré pour cet étudiant.', 0, 1, 'L');
    } else {
        // En-têtes de colonnes avec fond coloré
        $pdf->SetFillColor($accentColor[0], $accentColor[1], $accentColor[2]);
        $pdf->SetTextColor(255, 255, 255);
        $pdf->SetFont('helvetica', 'B', 8);
        
        $pdf->Cell(25, 8, 'Date', 1, 0, 'C', true);
        $pdf->Cell(55, 8, 'Frais', 1, 0, 'C', true);
        $pdf->Cell(25, 8, 'Montant', 1, 0, 'C', true);
        $pdf->Cell(25, 8, 'Mode', 1, 0, 'C', true);
        $pdf->Cell(20, 8, 'Source', 1, 0, 'C', true);
        $pdf->Cell(30, 8, 'Référence', 1, 1, 'C', true);
        
        // Contenu de la table des paiements
        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetFont('helvetica', '', 8);
        
        foreach ($historique_paiements as $paiement) {
            $pdf->Cell(25, 8, date('d/m/Y', strtotime($paiement['date_transaction'])), 1, 0, 'C');
            $pdf->Cell(55, 8, $paiement['frais_designation'], 1, 0, 'L');
            $pdf->Cell(25, 8, number_format($paiement['montant'], 2, ',', ' ') . ' ' . $paiement['devise'], 1, 0, 'R');
            $pdf->Cell(25, 8, $paiement['mode_paiement'], 1, 0, 'C');
            $pdf->Cell(20, 8, $paiement['source'], 1, 0, 'L');
            $pdf->Cell(30, 8, $paiement['reference_externe'] ?: $paiement['transaction_reference'], 1, 1, 'C');
        }
    }
    
    // Pied de page
    $pdf->SetY(-30);
    $pdf->SetFont('helvetica', 'I', 8);
    $pdf->SetTextColor(100, 100, 100);
    $pdf->Cell(0, 5, 'Ce document est un état financier officiel. Veuillez le conserver soigneusement.', 0, 1, 'C');
    $pdf->Cell(0, 5, 'Document généré le ' . date('d/m/Y à H:i') . ' par le système de gestion universitaire', 0, 1, 'C');
        // Générer un QR code avec les informations de l'étudiant
        $qrData = "ETUDIANT: " . $etudiant['noms'] . "\n";
        $qrData .= "MATRICULE: " . $etudiant['matricule'] . "\n";
        $qrData .= "PROMOTION: " . $etudiant['designationPromotion'] . "\n";
        $qrData .= "TOTAL DU: " . number_format($total_du, 2, ',', ' ') . " USD\n";
        $qrData .= "TOTAL PAYE: " . number_format($total_paye, 2, ',', ' ') . " USD\n";
        $qrData .= "SOLDE: " . number_format($solde_total, 2, ',', ' ') . " USD\n";
        $qrData .= "DATE: " . date('d/m/Y') . "\n";
        
        $style = array(
            'border' => 2,
            'vpadding' => 'auto',
            'hpadding' => 'auto',
            'fgcolor' => array(0, 0, 0),
            'bgcolor' => array(255, 255, 255),
            'module_width' => 1, // largeur d'un module QR
            'module_height' => 1 // hauteur d'un module QR
        );
        
        // Positionner le QR code en bas à droite
        $pdf->write2DBarcode($qrData, 'QRCODE,L', 90, 220, 20, 20, $style, 'N');
        
        // Section des signatures
        $pdf->SetY(-70); // Positionner en bas de page
        $pdf->SetFont('helvetica', 'B', 10);
        $pdf->SetTextColor(0, 0, 0);
        
        // Ligne de séparation
        $pdf->SetLineStyle(array('width' => 0.5, 'cap' => 'butt', 'join' => 'miter', 'dash' => 0, 'color' => array(180, 180, 180)));
        
        // Zone de signature à gauche (Comptable)
        $pdf->Cell(80, 6, 'LE COMPTABLE', 0, 0, 'C');
        
        // Zone de signature à droite (Étudiant)
        $pdf->Cell(95, 6, 'L\'ÉTUDIANT', 0, 1, 'C');
        
        // Espace pour les signatures
        $pdf->Ln(25);
        
        // Lignes pour les signatures
        $pdf->Line(20, $pdf->GetY(), 80, $pdf->GetY());
        $pdf->Line(110, $pdf->GetY(), 170, $pdf->GetY());
        
        // Générer le PDF
        $pdf->Output('situation_financiere_' . $etudiant['matricule'] . '.pdf', 'I');
        
    } catch (Exception $e) {
        // En cas d'erreur
        error_log('Erreur dans situation_etudiant.php: ' . $e->getMessage());
        echo "Une erreur est survenue lors de la génération de la situation financière: " . $e->getMessage();
    }
    
