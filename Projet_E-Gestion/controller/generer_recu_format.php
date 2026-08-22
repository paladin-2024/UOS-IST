<?php
session_start();
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/models/Universite.php';
require_once dirname(__DIR__) . '/vendor/autoload.php';

if (!isset($_SESSION['id'])) {
    header('Location: ../login');
    exit();
}

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$format = isset($_GET['format']) ? $_GET['format'] : 'A4';

if ($id <= 0) {
    die("ID de paiement non valide");
}

function numberToWords($number, $devise = 'USD')
{
    $dictionary = [
        0 => 'zéro', 1 => 'un', 2 => 'deux', 3 => 'trois', 4 => 'quatre',
        5 => 'cinq', 6 => 'six', 7 => 'sept', 8 => 'huit', 9 => 'neuf',
        10 => 'dix', 11 => 'onze', 12 => 'douze', 13 => 'treize', 14 => 'quatorze',
        15 => 'quinze', 16 => 'seize', 17 => 'dix-sept', 18 => 'dix-huit', 19 => 'dix-neuf',
        20 => 'vingt', 30 => 'trente', 40 => 'quarante', 50 => 'cinquante',
        60 => 'soixante', 70 => 'soixante-dix', 80 => 'quatre-vingts', 90 => 'quatre-vingt-dix'
    ];

    $parts = explode('.', (string)number_format($number, 2, '.', ''));
    $integer = (int)$parts[0];
    $decimal = (int)$parts[1];

    if ($integer == 0) {
        $words = $dictionary[0];
    } else {
        $words = '';
        if ($integer >= 1000000000) {
            $billions = intval($integer / 1000000000);
            $words .= ($billions > 1) ? numberToWords($billions, '') . ' milliards ' : 'un milliard ';
            $integer = $integer % 1000000000;
        }
        if ($integer >= 1000000) {
            $millions = intval($integer / 1000000);
            $words .= ($millions > 1) ? numberToWords($millions, '') . ' millions ' : 'un million ';
            $integer = $integer % 1000000;
        }
        if ($integer >= 1000) {
            $thousands = intval($integer / 1000);
            $words .= ($thousands > 1) ? numberToWords($thousands, '') . ' mille ' : 'mille ';
            $integer = $integer % 1000;
        }
        if ($integer >= 100) {
            $hundreds = intval($integer / 100);
            $words .= ($hundreds > 1) ? $dictionary[$hundreds] . ' cents ' : 'cent ';
            $integer = $integer % 100;
        }
        if ($integer > 0) {
            if ($integer < 20) {
                $words .= $dictionary[$integer];
            } else {
                $tens = intval($integer / 10) * 10;
                $units = $integer % 10;
                if ($tens == 70) {
                    $words .= 'soixante-' . $dictionary[10 + $units];
                } elseif ($tens == 90) {
                    $words .= 'quatre-vingt-' . $dictionary[10 + $units];
                } else {
                    $words .= $dictionary[$tens];
                    if ($units > 0) {
                        $words .= '-' . $dictionary[$units];
                    }
                }
            }
        }
    }

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

    if (!empty($devise)) {
        switch (strtoupper($devise)) {
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
    $connexion = Connexion::getInstance()->getPDO();

    $stmt = $connexion->prepare("
    SELECT 
        pf.*, 
        af.id AS affectation_id,
        af.montant_specifique,
        af.montant_restant,
        af.statut_paiement,
        af.promotion_id,
        af.matricule_etudiant AS affectation_matricule_etudiant,
        e.noms, e.matricule, e.promotion_idpromotion,
        f.designation AS frais_designation,
        f.montant AS montant_frais,
        f.lieu_paiement,
        a.designation AS annee_academique,
        p.designationPromotion AS promotion_nom,
        CONCAT(s.designationSection, ' - ', o.designationOrientation) AS faculte_nom,
        s.telephone AS section_telephone,
        s.email AS section_email,
        s.adresse AS section_adresse,
        s.boite_postale AS section_boite_postale,
        s.site_web AS section_site_web,
        t.reference AS transaction_reference,
        t.date_transaction,
        t.idUser,
        u.nomUser AS agent_nom,
        (SELECT COUNT(*) FROM paiements_frais WHERE affectation_id = af.id) AS nombre_versements,
        (SELECT COALESCE(SUM(pf2.montant), 0) 
        FROM paiements_frais pf2
        JOIN transactions t2 ON pf2.transaction_id = t2.id
        WHERE pf2.affectation_id = af.id
        AND pf2.matricule_etudiant = e.matricule
        AND (t2.statut = 'Confirmée' OR pf2.est_confirme = 1)) AS total_deja_paye
    FROM paiements_frais pf
    INNER JOIN affectation_frais af ON pf.affectation_id = af.id
    INNER JOIN frais f ON af.frais_id = f.id
    INNER JOIN etudiant e ON pf.matricule_etudiant = e.matricule AND e.est_actif = 1
    LEFT JOIN promotion p ON e.promotion_idpromotion = p.idpromotion
    LEFT JOIN orientation o ON p.orientation_idorientation = o.idorientation
    LEFT JOIN section s ON o.section_idsection = s.idsection
    LEFT JOIN transactions t ON pf.transaction_id = t.id
    LEFT JOIN t_users u ON t.idUser = u.idUser
    LEFT JOIN annee_acad a ON f.annee_acad_id = a.idannee_acad
    WHERE pf.id = :id
    ");

    $stmt->bindParam(':id', $id);
    $stmt->execute();
    $paiement = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$paiement) {
        die("Paiement non trouvé");
    }

    $universite = new Universite();
    $configUniversite = $universite->getConfigurationUniversite();
    
    // Déterminer les informations de contact à utiliser selon le lieu de paiement
    $usesFacultyContact = ($paiement['lieu_paiement'] === 'Faculté');
    
    // Informations de contact (université ou faculté)
    $contactInfo = array(
        'nom' => $usesFacultyContact 
            ? ($paiement['faculte_nom'] ?? $configUniversite['nom']) 
            : $configUniversite['nom'],
        'adresse' => $usesFacultyContact 
            ? ($paiement['section_adresse'] ?? $configUniversite['adresse']) 
            : $configUniversite['adresse'],
        'telephone' => $usesFacultyContact 
            ? ($paiement['section_telephone'] ?? $configUniversite['telephone']) 
            : $configUniversite['telephone'],
        'email' => $usesFacultyContact 
            ? ($paiement['section_email'] ?? $configUniversite['email']) 
            : $configUniversite['email'],
        'site_web' => $usesFacultyContact 
            ? ($paiement['section_site_web'] ?? $configUniversite['site_web']) 
            : $configUniversite['site_web'],
        'boite_postale' => $usesFacultyContact 
            ? ($paiement['section_boite_postale'] ?? '') 
            : ''
    );

    // Couleurs pour impression lisible (texte noir, fonds gris clair)
    $primaryColor = array(0, 0, 0);       // Noir pour texte
    $accentColor = array(200, 200, 200);  // Gris clair pour fonds de tableau
    $successColor = array(220, 220, 220); // Gris clair pour fonds de titres
    $dangerColor = array(0, 0, 0);        // Noir pour texte
    $warningColor = array(0, 0, 0);       // Noir pour texte

    $montantTotalFrais = !empty($paiement['montant_specifique']) ? $paiement['montant_specifique'] : $paiement['montant_frais'];
    $totalDejaPaye = $paiement['total_deja_paye'];
    $resteAPayer = $montantTotalFrais - $totalDejaPaye;

    if ($format === 'A4-double') {
        // Format A4 paysage avec deux reçus côte à côte
        $pdf = new TCPDF('L', 'mm', 'A4', true, 'UTF-8', false);
        $pdf->SetCreator('Système de gestion universitaire');
        $pdf->SetAuthor($configUniversite['nom'] ?? 'Administration');
        $pdf->SetTitle('Reçu de Paiement - ' . $paiement['transaction_reference']);
        $pdf->SetSubject('Reçu de paiement');
        $pdf->SetKeywords('Reçu, Paiement, Frais');
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetMargins(10, 10, 10);
        $pdf->SetAutoPageBreak(false);
        $pdf->AddPage();

        // Fonction pour dessiner un reçu avec le style A4
        $drawReceipt = function($startX, $startY, $width, $height) use ($pdf, $paiement, $configUniversite, $primaryColor, $accentColor, $successColor, $dangerColor, $warningColor, $montantTotalFrais, $totalDejaPaye, $resteAPayer, $contactInfo) {
            
            // Logo en filigrane (fond)
            if (!empty($configUniversite['logo'])) {
                $logoPath = dirname(__DIR__) . '/' . $configUniversite['logo'];
                if (file_exists($logoPath)) {
                    $pdf->setAlpha(0.1);
                    $logoSize = 50;
                    $logoX = $startX + ($width - $logoSize) / 2;
                    $logoY = $startY + ($height - $logoSize) / 2;
                    $pdf->Image($logoPath, $logoX, $logoY, $logoSize, $logoSize, '', '', '', false, 300, '', false, false, 0);
                    $pdf->setAlpha(1);
                }
            }
            
            // En-tête avec logo
            $currentY = $startY + 3;
            
            // Logo petit en haut à gauche
            if (!empty($configUniversite['logo'])) {
                $logoPath = dirname(__DIR__) . '/' . $configUniversite['logo'];
                if (file_exists($logoPath)) {
                    $pdf->Image($logoPath, $startX + 5, $currentY, 10, 0, '', '', '', false, 200, '', false, false, 0);
                }
            }
            
            // Ajuster Y pour éviter le chevauchement avec le logo
            $pdf->SetY($currentY);
            
            $pdf->SetTextColor($primaryColor[0], $primaryColor[1], $primaryColor[2]);
            $pdf->SetFont('helvetica', 'B', 7);
            $pdf->SetX($startX);
            $pdf->Cell($width, 3, strtoupper($configUniversite['ministere_tutelle'] ?? ''), 0, 1, 'C');
            
            $pdf->SetFont('helvetica', 'B', 8);
            $pdf->SetX($startX);
            $pdf->Cell($width, 3, strtoupper($contactInfo['nom']), 0, 1, 'C');
            
            if (!empty($configUniversite['sigle'])) {
                $pdf->SetFont('helvetica', 'B', 7);
                $pdf->SetX($startX);
                $pdf->Cell($width, 2.5, $configUniversite['sigle'], 0, 1, 'C');
            }
            
            $pdf->SetFont('helvetica', '', 6);
            $pdf->SetTextColor(0, 0, 0);
            if (!empty($contactInfo['adresse'])) {
                $pdf->SetX($startX);
                $pdf->Cell($width, 2, substr($contactInfo['adresse'], 0, 50), 0, 1, 'C');
            }
            
            $contactStr = '';
            if (!empty($contactInfo['telephone'])) {
                $contactStr .= 'Tél: ' . substr($contactInfo['telephone'], 0, 15) . ' ';
            }
            if (!empty($contactInfo['email'])) {
                $contactStr .= 'Email: ' . substr($contactInfo['email'], 0, 20);
            }
            
            if (!empty($contactStr)) {
                $pdf->SetX($startX);
                $pdf->Cell($width, 2, $contactStr, 0, 1, 'C');
            }
            
            $currentY = $pdf->GetY() + 2;
            
            // Ligne de séparation horizontale
            $pdf->SetLineStyle(array('width' => 0.5, 'cap' => 'butt', 'join' => 'miter', 'dash' => 0, 'color' => $accentColor));
            $pdf->Line($startX + 5, $currentY, $startX + $width - 5, $currentY);
            
            $currentY += 2;
            
            // Titre REÇU DE PAIEMENT
            $pdf->SetFillColor($successColor[0], $successColor[1], $successColor[2]);
            $pdf->SetTextColor(0, 0, 0);
            $pdf->SetFont('helvetica', 'B', 10);
            $pdf->SetXY($startX + 5, $currentY);
            $pdf->Cell($width - 10, 5, 'REÇU DE PAIEMENT', 0, 1, 'C', true);
            
            $currentY = $pdf->GetY() + 2;
            
            // Informations du reçu
            $pdf->SetFillColor(245, 245, 245);
            $pdf->SetTextColor($primaryColor[0], $primaryColor[1], $primaryColor[2]);
            $pdf->SetFont('helvetica', 'B', 7);
            $pdf->RoundedRect($startX + 5, $currentY, $width - 10, 12, 1.0, '1111', 'DF', array(), array(245, 245, 245));
            
            $pdf->SetXY($startX + 7, $currentY + 2);
            $pdf->Cell(($width - 14) / 2, 3, 'Reçu N°: ' . $paiement['transaction_reference'], 0, 0, 'L');
            $pdf->Cell(($width - 14) / 2, 3, 'Date: ' . date('d/m/Y', strtotime($paiement['date_transaction'])), 0, 1, 'R');
            
            $pdf->SetX($startX + 7);
            $pdf->Cell($width - 14, 3, 'Année académique: ' . ($paiement['annee_academique'] ?? 'Non spécifiée'), 0, 1, 'C');
            
            $currentY = $pdf->GetY() + 5;  // Augmenté de 3 à 5 pour plus d'espace
            
            // Section ÉTUDIANT
            $pdf->SetFont('helvetica', 'B', 7);
            $pdf->SetTextColor(0, 0, 0);
            $pdf->SetXY($startX + 5, $currentY);
            $pdf->Cell($width - 10, 3, 'INFORMATIONS DE L\'ÉTUDIANT', 0, 1, 'L');
            
            $currentY = $pdf->GetY() + 1;
            
            $pdf->SetFillColor(240, 248, 255);
            $pdf->RoundedRect($startX + 5, $currentY, $width - 10, 20, 1.0, '1111', 'DF');
            
            $pdf->SetFont('helvetica', '', 6);
            $pdf->SetXY($startX + 7, $currentY + 2);
            
            $pdf->SetFont('helvetica', 'B', 6);
            $pdf->Cell(20, 3, 'Nom:', 0, 0, 'L');
            $pdf->SetFont('helvetica', '', 6);
            $pdf->Cell($width - 34, 3, substr($paiement['noms'], 0, 40), 0, 1, 'L');
            
            $pdf->SetX($startX + 7);
            $pdf->SetFont('helvetica', 'B', 6);
            $pdf->Cell(20, 3, 'Matricule:', 0, 0, 'L');
            $pdf->SetFont('helvetica', '', 6);
            $pdf->Cell($width - 34, 3, $paiement['matricule'], 0, 1, 'L');
            
            $pdf->SetX($startX + 7);
            $pdf->SetFont('helvetica', 'B', 6);
            $pdf->Cell(20, 3, 'Promotion:', 0, 0, 'L');
            $pdf->SetFont('helvetica', '', 6);
            $pdf->Cell($width - 34, 3, substr($paiement['promotion_nom'] ?? 'Non spécifiée', 0, 35), 0, 1, 'L');
            
            $pdf->SetX($startX + 7);
            $pdf->SetFont('helvetica', 'B', 6);
            $pdf->Cell(20, 3, 'Section:', 0, 0, 'L');
            $pdf->SetFont('helvetica', '', 6);
            $pdf->Cell($width - 34, 3, substr($paiement['faculte_nom'] ?? 'Non spécifiée', 0, 35), 0, 1, 'L');
            
            $currentY = $pdf->GetY() + 8;  // Augmenté de 6 à 8 pour encore plus d'espace
            
            // Section PAIEMENT
            $pdf->SetFont('helvetica', 'B', 7);
            $pdf->SetTextColor(0, 0, 0);
            $pdf->SetXY($startX + 5, $currentY);
            $pdf->Cell($width - 10, 3, 'DÉTAILS DU PAIEMENT', 0, 1, 'L');
            
            $currentY = $pdf->GetY() + 1;
            
            // Tableau du paiement
            $pdf->SetFillColor($accentColor[0], $accentColor[1], $accentColor[2]);
            $pdf->SetTextColor(0, 0, 0);
            $pdf->SetFont('helvetica', 'B', 6);
            
            $pdf->SetXY($startX + 5, $currentY);
            $col1Width = ($width - 10) * 0.5;
            $col2Width = ($width - 10) * 0.25;
            $col3Width = ($width - 10) * 0.25;
            
            $pdf->Cell($col1Width, 4, 'Description', 1, 0, 'C', true);
            $pdf->Cell($col2Width, 4, 'Référence', 1, 0, 'C', true);
            $pdf->Cell($col3Width, 4, 'Montant', 1, 1, 'C', true);
            
            $pdf->SetTextColor(0, 0, 0);
            $pdf->SetFont('helvetica', '', 6);
            $pdf->SetX($startX + 5);
            $pdf->Cell($col1Width, 4, substr($paiement['frais_designation'], 0, 30), 1, 0, 'L');
            $pdf->Cell($col2Width, 4, substr($paiement['transaction_reference'], 0, 15), 1, 0, 'C');
            $pdf->SetFont('helvetica', 'B', 6);
            $pdf->Cell($col3Width, 4, number_format($paiement['montant'], 2, ',', ' ') . ' ' . $paiement['devise'], 1, 1, 'R');
            
            $currentY = $pdf->GetY() + 3;
            
            // Section SITUATION
            $pdf->SetFont('helvetica', 'B', 7);
            $pdf->SetTextColor(0, 0, 0);
            $pdf->SetXY($startX + 5, $currentY);
            $pdf->Cell($width - 10, 3, 'SITUATION DU PAIEMENT', 0, 1, 'L');
            
            $currentY = $pdf->GetY() + 1;
            
            $pdf->SetFillColor(250, 250, 250);
            $pdf->RoundedRect($startX + 5, $currentY, $width - 10, 18, 1.0, '1111', 'DF');
            
            $pdf->SetFont('helvetica', '', 6);
            $pdf->SetXY($startX + 7, $currentY + 2);
            
            $pdf->SetFont('helvetica', 'B', 6);
            $pdf->Cell(35, 3, 'Montant total du frais:', 0, 0, 'L');
            $pdf->SetFont('helvetica', '', 6);
            $pdf->Cell($width - 49, 3, number_format($montantTotalFrais, 2, ',', ' ') . ' ' . $paiement['devise'], 0, 1, 'R');
            
            $pdf->SetX($startX + 7);
            $pdf->SetFont('helvetica', 'B', 6);
            $pdf->Cell(35, 3, 'Total payé:', 0, 0, 'L');
            $pdf->SetFont('helvetica', '', 6);
            $pdf->Cell($width - 49, 3, number_format($totalDejaPaye, 2, ',', ' ') . ' ' . $paiement['devise'], 0, 1, 'R');
            
            $pdf->SetX($startX + 7);
            $pdf->SetFont('helvetica', 'B', 6);
            $pdf->Cell(35, 3, 'Reste à payer:', 0, 0, 'L');
            $pdf->SetFont('helvetica', 'B', 6);
            if ($resteAPayer <= 0) {
                $pdf->SetTextColor($successColor[0], $successColor[1], $successColor[2]);
                $pdf->Cell($width - 49, 3, 'PAYÉ INTÉGRALEMENT', 0, 1, 'R');
            } else {
                $pdf->SetTextColor($dangerColor[0], $dangerColor[1], $dangerColor[2]);
                $pdf->Cell($width - 49, 3, number_format($resteAPayer, 2, ',', ' ') . ' ' . $paiement['devise'], 0, 1, 'R');
            }
            $pdf->SetTextColor(0, 0, 0);
            
            $pdf->SetX($startX + 7);
            $pdf->SetFont('helvetica', 'B', 6);
            $pdf->Cell(35, 3, 'Statut:', 0, 0, 'L');
            $pdf->SetFont('helvetica', 'B', 6);
            
            switch ($paiement['statut_paiement']) {
                case 'Complet':
                    $pdf->SetTextColor($successColor[0], $successColor[1], $successColor[2]);
                    $statusText = 'PAYÉ INTÉGRALEMENT';
                    break;
                case 'Partiel':
                    $pdf->SetTextColor($warningColor[0], $warningColor[1], $warningColor[2]);
                    $statusText = 'PAIEMENT PARTIEL';
                    break;
                default:
                    $pdf->SetTextColor($dangerColor[0], $dangerColor[1], $dangerColor[2]);
                    $statusText = 'NON PAYÉ';
            }
            
            $pdf->Cell($width - 49, 3, $statusText, 0, 1, 'R');
            $pdf->SetTextColor(0, 0, 0);
            
            // QR Code - Pointer vers la page de détails du paiement
            $baseUrl = $_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['HTTP_HOST'] . dirname(dirname($_SERVER['REQUEST_URI']));
            $qrData = $baseUrl . '/controller/qr_paiement.php?id=' . $paiement['id'];
            
            $style = array(
                'border' => 1,
                'vpadding' => 'auto',
                'hpadding' => 'auto',
                'fgcolor' => array(0, 0, 0),
                'bgcolor' => array(255, 255, 255),
                'module_width' => 1,
                'module_height' => 1
            );
            
            $qrX = $startX + ($width - 15) / 2;
            $qrY = $startY + $height - 25;
            $pdf->write2DBarcode($qrData, 'QRCODE,L', $qrX, $qrY, 15, 15, $style, 'N');
            
            // Signatures
            $pdf->SetFont('helvetica', 'B', 6);
            $pdf->SetTextColor(0, 0, 0);
            
            $signY = $startY + $height - 38;
            
            $pdf->SetXY($startX + 10, $signY);
            $pdf->Cell(($width - 20) / 2 - 5, 3, 'LE CAISSIER', 0, 0, 'C');
            $pdf->SetX($startX + $width / 2 + 5);
            $pdf->Cell(($width - 20) / 2 - 5, 3, 'L\'ÉTUDIANT', 0, 1, 'C');
            
            // Ajouter le nom du caissier sous la signature
            $pdf->SetFont('helvetica', '', 5);
            $pdf->SetTextColor(0, 0, 0);
            $pdf->SetXY($startX + 10, $signY + 3);
            $agentName = isset($paiement['agent_nom']) ? $paiement['agent_nom'] : 'Non spécifié';
            $pdf->Cell(($width - 20) / 2 - 5, 2, '(' . substr($agentName, 0, 25) . ')', 0, 0, 'C');
            
            $pdf->SetFont('helvetica', 'B', 6);
            $pdf->SetTextColor(0, 0, 0);
            
            $pdf->SetLineStyle(array('width' => 0.3, 'cap' => 'butt', 'join' => 'miter', 'dash' => 0, 'color' => array(180, 180, 180)));
            $pdf->Line($startX + 10, $signY + 18, $startX + ($width / 2) - 10, $signY + 18);
            $pdf->Line($startX + ($width / 2) + 10, $signY + 18, $startX + $width - 10, $signY + 18);
            
            // Footer
            $pdf->SetFont('helvetica', 'I', 5);
            $pdf->SetTextColor(0, 0, 0);
            $pdf->SetXY($startX + 5, $startY + $height - 8);
            $pdf->Cell($width - 10, 2, 'Document officiel - Généré le ' . date('d/m/Y à H:i'), 0, 1, 'C');
            
            if (!$paiement['est_confirme']) {
                $pdf->SetTextColor($warningColor[0], $warningColor[1], $warningColor[2]);
                $pdf->SetX($startX + 5);
                $pdf->Cell($width - 10, 2, 'PAIEMENT EN ATTENTE DE CONFIRMATION', 0, 1, 'C');
            }
        };
        
        // Dimensions de chaque reçu
        $receiptWidth = 138.5; // (297 - 20) / 2
        $receiptHeight = 190; // 210 - 20
        
        // Dessiner le premier reçu (côté gauche)
        $drawReceipt(10, 10, $receiptWidth, $receiptHeight);
        
        // Dessiner le deuxième reçu (côté droit) - identique au premier
        $drawReceipt(10 + $receiptWidth, 10, $receiptWidth, $receiptHeight);
        
        // Ligne de découpe au milieu
        $pdf->SetLineStyle(array('width' => 0.2, 'cap' => 'butt', 'join' => 'miter', 'dash' => '5,5', 'color' => array(150, 150, 150)));
        $pdf->Line(148.5, 5, 148.5, 205);
        
        $filename = 'recu_' . $paiement['recu_numero'] . '_A4_double.pdf';
        $pdf->Output($filename, 'I');
    }
    elseif ($format === 'A4') {
        $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
        $pdf->SetCreator('Système de gestion universitaire');
        $pdf->SetAuthor($configUniversite['nom'] ?? 'Administration');
        $pdf->SetTitle('Reçu de Paiement - ' . $paiement['transaction_reference']);
        $pdf->SetSubject('Reçu de paiement');
        $pdf->SetKeywords('Reçu, Paiement, Frais');
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetMargins(15, 15, 15);
        $pdf->SetAutoPageBreak(true, 15);
        $pdf->AddPage();

        if (!empty($configUniversite['logo'])) {
            $logoPath = dirname(__DIR__) . '/' . $configUniversite['logo'];
            if (file_exists($logoPath)) {
                $pdf->setAlpha(0.1);
                $pageWidth = $pdf->getPageWidth();
                $pageHeight = $pdf->getPageHeight();
                $logoWidth = 100;
                $logoHeight = 100;
                $x = ($pageWidth - $logoWidth) / 2;
                $y = ($pageHeight - $logoHeight) / 2;
                $pdf->Image($logoPath, $x, $y, $logoWidth, $logoHeight, '', '', '', false, 300, '', false, false, 0);
                $pdf->setAlpha(1);
            }
        }

        if ($configUniversite) {
            if (!empty($configUniversite['logo'])) {
                $logoPath = dirname(__DIR__) . '/' . $configUniversite['logo'];
                if (file_exists($logoPath)) {
                    $pdf->Image($logoPath, 15, 15, 20, 0, '', '', '', false, 200, '', false, false, 0);
                }
            }

            $pdf->SetTextColor($primaryColor[0], $primaryColor[1], $primaryColor[2]);
            $pdf->SetFont('helvetica', 'B', 10);
            $pdf->SetY(15);
            $pdf->Cell(0, 6, strtoupper($configUniversite['ministere_tutelle'] ?? ''), 0, 1, 'C');

            $pdf->SetFont('helvetica', 'B', 12);
            $pdf->Cell(0, 6, strtoupper($contactInfo['nom']), 0, 1, 'C');

            if (!empty($configUniversite['sigle'])) {
                $pdf->SetFont('helvetica', 'B', 10);
                $pdf->Cell(0, 4, $configUniversite['sigle'], 0, 1, 'C');
            }

            $pdf->SetFont('helvetica', '', 8);
            $pdf->SetTextColor(0, 0, 0);
            if (!empty($contactInfo['adresse'])) {
                $pdf->Cell(0, 4, $contactInfo['adresse'], 0, 1, 'C');
            }

            $contactStr = '';
            if (!empty($contactInfo['telephone'])) {
                $contactStr .= 'Tél: ' . $contactInfo['telephone'] . ' ';
            }
            if (!empty($contactInfo['email'])) {
                $contactStr .= 'Email: ' . $contactInfo['email'] . ' ';
            }
            if (!empty($contactInfo['site_web'])) {
                $contactStr .= 'Web: ' . $contactInfo['site_web'];
            }

            if (!empty($contactStr)) {
                $pdf->Cell(0, 4, $contactStr, 0, 1, 'C');
            }

            $pdf->SetLineStyle(array('width' => 0.5, 'cap' => 'butt', 'join' => 'miter', 'dash' => 0, 'color' => $accentColor));
            $pdf->Line(15, 42, $pdf->getPageWidth() - 15, 42);
        }

        $pdf->SetFillColor($successColor[0], $successColor[1], $successColor[2]);
        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetFont('helvetica', 'B', 16);
        $pdf->Ln(5);
        $pdf->Cell(0, 10, 'REÇU DE PAIEMENT', 0, 1, 'C', true);

        $pdf->SetFillColor(245, 245, 245);
        $pdf->SetTextColor($primaryColor[0], $primaryColor[1], $primaryColor[2]);
        $pdf->SetFont('helvetica', 'B', 12);
        $pdf->Ln(5);

        $pdf->RoundedRect(15, $pdf->GetY(), 180, 20, 2.0, '1111', 'DF', array(), array(245, 245, 245));
        $pdf->SetY($pdf->GetY() + 5);
        $pdf->Cell(90, 6, 'Reçu N°: ' . $paiement['transaction_reference'], 0, 0, 'L');
        $pdf->Cell(90, 6, 'Date: ' . date('d/m/Y H:i', strtotime($paiement['date_transaction'])), 0, 1, 'R');
        $pdf->Cell(0, 6, 'Année académique: ' . ($paiement['annee_academique'] ?? 'Non spécifiée'), 0, 1, 'C');

        $pdf->Ln(5);
        $pdf->SetFont('helvetica', 'B', 10);
        $pdf->SetTextColor(0, 0, 0);
        $pdf->Cell(0, 8, 'INFORMATIONS DE L\'ÉTUDIANT', 0, 1, 'L');

        $pdf->SetFillColor(240, 248, 255);
        $pdf->RoundedRect(15, $pdf->GetY(), 180, 40, 2.0, '1111', 'DF');

        $currentY = $pdf->GetY() + 5;
        $pdf->SetY($currentY);

        $pdf->SetFont('helvetica', 'B', 10);
        $pdf->Cell(40, 8, 'Nom complet:', 0, 0, 'L');
        $pdf->SetFont('helvetica', '', 10);
        $pdf->Cell(140, 8, $paiement['noms'], 0, 1, 'L');

        $pdf->SetFont('helvetica', 'B', 10);
        $pdf->Cell(40, 8, 'Matricule:', 0, 0, 'L');
        $pdf->SetFont('helvetica', '', 10);
        $pdf->Cell(140, 8, $paiement['matricule'], 0, 1, 'L');

        $pdf->SetFont('helvetica', 'B', 10);
        $pdf->Cell(40, 8, 'Promotion:', 0, 0, 'L');
        $pdf->SetFont('helvetica', '', 10);
        $pdf->Cell(140, 8, $paiement['promotion_nom'] ?? 'Non spécifiée', 0, 1, 'L');

        $pdf->SetFont('helvetica', 'B', 10);
        $pdf->Cell(40, 8, 'Faculté/Section:', 0, 0, 'L');
        $pdf->SetFont('helvetica', '', 10);
        $pdf->Cell(140, 8, $paiement['faculte_nom'] ?? 'Non spécifiée', 0, 1, 'L');

        $pdf->Ln(8);
        $pdf->SetFont('helvetica', 'B', 10);
        $pdf->SetTextColor(0, 0, 0);
        $pdf->Cell(0, 8, 'DÉTAILS DU PAIEMENT ACTUEL', 0, 1, 'L');

        $pdf->SetFillColor(245, 245, 245);
        $pdf->SetFont('helvetica', 'B', 9);

        $pdf->SetFillColor($accentColor[0], $accentColor[1], $accentColor[2]);
        $pdf->SetTextColor(0, 0, 0);
        $pdf->Cell(70, 8, 'Description', 1, 0, 'C', true);
        $pdf->Cell(50, 8, 'R��férence', 1, 0, 'C', true);
        $pdf->Cell(60, 8, 'Montant', 1, 1, 'C', true);

        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetFont('helvetica', '', 9);
        $pdf->Cell(70, 8, $paiement['frais_designation'], 1, 0, 'L');
        $pdf->Cell(50, 8, $paiement['transaction_reference'], 1, 0, 'C');
        $pdf->SetFont('helvetica', 'B', 9);
        $pdf->Cell(60, 8, number_format($paiement['montant'], 2, ',', ' ') . ' ' . $paiement['devise'], 1, 1, 'R');

        $pdf->Ln(3);
        $pdf->SetFont('helvetica', 'I', 10);
        $pdf->SetTextColor(60, 60, 60);
        $pdf->Cell(40, 8, 'Soit en lettres:', 0, 0, 'L');
        $pdf->Cell(140, 8, numberToWords($paiement['montant'], $paiement['devise']), 0, 1, 'L');
        $pdf->SetTextColor(0, 0, 0);

        $pdf->Ln(5);
        $pdf->SetFont('helvetica', 'B', 10);
        $pdf->Cell(0, 8, 'SITUATION DU PAIEMENT DES FRAIS', 0, 1, 'L');

        $pdf->SetFillColor(250, 250, 250);
        $pdf->RoundedRect(15, $pdf->GetY(), 180, 35, 2.0, '1111', 'DF');

        $currentY = $pdf->GetY() + 5;
        $pdf->SetY($currentY);

        $pdf->SetFont('helvetica', 'B', 9);
        $pdf->Cell(90, 6, 'Montant total du frais:', 0, 0, 'L');
        $pdf->SetFont('helvetica', '', 9);
        $pdf->Cell(90, 6, number_format($montantTotalFrais, 2, ',', ' ') . ' ' . $paiement['devise'], 0, 1, 'L');

        $pdf->SetFont('helvetica', 'B', 9);
        $pdf->Cell(90, 6, 'Total déjà payé (incluant ce paiement):', 0, 0, 'L');
        $pdf->SetFont('helvetica', '', 9);
        $pdf->Cell(90, 6, number_format($totalDejaPaye, 2, ',', ' ') . ' ' . $paiement['devise'], 0, 1, 'L');

        $pdf->SetFont('helvetica', 'B', 9);
        $pdf->Cell(90, 6, 'Reste à payer:', 0, 0, 'L');
        $pdf->SetFont('helvetica', '', 9);
        if ($resteAPayer <= 0) {
            $pdf->SetTextColor($successColor[0], $successColor[1], $successColor[2]);
            $pdf->Cell(90, 6, 'PAYÉ INTÉGRALEMENT', 0, 1, 'L');
        } else {
            $pdf->SetTextColor($dangerColor[0], $dangerColor[1], $dangerColor[2]);
            $pdf->Cell(90, 6, number_format($resteAPayer, 2, ',', ' ') . ' ' . $paiement['devise'], 0, 1, 'L');
        }
        $pdf->SetTextColor(0, 0, 0);

        $pdf->SetFont('helvetica', 'B', 9);
        $pdf->Cell(90, 6, 'Statut du paiement:', 0, 0, 'L');
        $pdf->SetFont('helvetica', 'B', 9);

        switch ($paiement['statut_paiement']) {
            case 'Complet':
                $pdf->SetTextColor($successColor[0], $successColor[1], $successColor[2]);
                $statusText = 'PAYÉ INTÉGRALEMENT';
                break;
            case 'Partiel':
                $pdf->SetTextColor($warningColor[0], $warningColor[1], $warningColor[2]);
                $statusText = 'PAIEMENT PARTIEL';
                break;
            default:
                $pdf->SetTextColor($dangerColor[0], $dangerColor[1], $dangerColor[2]);
                $statusText = 'NON PAYÉ';
        }

        $pdf->Cell(90, 6, $statusText, 0, 1, 'L');
        $pdf->SetTextColor(0, 0, 0);

        // QR Code - Pointer vers la page de détails du paiement
        $baseUrl = $_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['HTTP_HOST'] . dirname(dirname($_SERVER['REQUEST_URI']));
        $qrData = $baseUrl . '/controller/qr_paiement.php?id=' . $paiement['id'];

        $style = array(
            'border' => 2,
            'vpadding' => 'auto',
            'hpadding' => 'auto',
            'fgcolor' => array(0, 0, 0),
            'bgcolor' => array(255, 255, 255),
            'module_width' => 1,
            'module_height' => 1
        );

        $pdf->write2DBarcode($qrData, 'QRCODE,L', 90, 220, 20, 20, $style, 'N');

        $pdf->SetY(-70);
        $pdf->SetFont('helvetica', 'B', 10);
        $pdf->SetTextColor(0, 0, 0);

        $pdf->SetLineStyle(array('width' => 0.5, 'cap' => 'butt', 'join' => 'miter', 'dash' => 0, 'color' => array(180, 180, 180)));

        $pdf->Cell(80, 6, 'LE CAISSIER', 0, 0, 'C');
        $pdf->Cell(95, 6, 'L\'ÉTUDIANT', 0, 1, 'C');

        $pdf->Ln(25);

        $pdf->Line(20, $pdf->GetY(), 80, $pdf->GetY());
        $pdf->Line(110, $pdf->GetY(), 170, $pdf->GetY());

        $pdf->SetY(-30);
        $pdf->SetFont('helvetica', 'I', 8);
        $pdf->SetTextColor(0, 0, 0);
        $pdf->Cell(0, 5, 'Ce reçu est un document officiel. Veuillez le conserver soigneusement.', 0, 1, 'C');
        $pdf->Cell(0, 5, 'Document généré le ' . date('d/m/Y à H:i') . ' par le système de gestion universitaire', 0, 1, 'C');

        if (!$paiement['est_confirme']) {
            $pdf->SetTextColor($warningColor[0], $warningColor[1], $warningColor[2]);
            $pdf->Cell(0, 5, 'ATTENTION: Ce paiement est en attente de confirmation.', 0, 1, 'C');
        }

        $filename = 'recu_' . $paiement['recu_numero'] . '_A4.pdf';
        $pdf->Output($filename, 'I');
    }

    elseif ($format === 'A5') {
        // Format A5 paysage pour meilleure utilisation de l'espace
        $pdf = new TCPDF('L', 'mm', 'A5', true, 'UTF-8', false);
        $pdf->SetCreator('Système de gestion universitaire');
        $pdf->SetAuthor($configUniversite['nom'] ?? 'Administration');
        $pdf->SetTitle('Reçu de Paiement - ' . $paiement['transaction_reference']);
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetMargins(4, 4, 4);
        $pdf->SetAutoPageBreak(false);
        $pdf->AddPage();

        $pageWidth = $pdf->getPageWidth();
        $pageHeight = $pdf->getPageHeight();
        $contentWidth = $pageWidth - 8;

        if (!empty($configUniversite['logo'])) {
            $logoPath = dirname(__DIR__) . '/' . $configUniversite['logo'];
            if (file_exists($logoPath)) {
                $pdf->Image($logoPath, 4, 4, 8, 0, '', '', '', false, 200, '', false, false, 0);
            }
        }

        // En-tête
        $pdf->SetTextColor($primaryColor[0], $primaryColor[1], $primaryColor[2]);
        $pdf->SetFont('helvetica', 'B', 8);
        $pdf->Cell($contentWidth, 3.5, strtoupper($configUniversite['ministere_tutelle'] ?? ''), 0, 1, 'C');

        $pdf->SetFont('helvetica', 'B', 10);
        $pdf->Cell($contentWidth, 4, strtoupper($contactInfo['nom']), 0, 1, 'C');

        $pdf->SetFont('helvetica', '', 7);
        $pdf->SetTextColor(0, 0, 0);
        if (!empty($contactInfo['adresse'])) {
            $pdf->MultiCell($contentWidth, 2, substr($contactInfo['adresse'], 0, 50), 0, 'C');
        }

        $contactStr = '';
        if (!empty($contactInfo['telephone'])) {
            $contactStr .= 'Tél: ' . substr($contactInfo['telephone'], 0, 22);
        }
        if (!empty($contactInfo['email'])) {
            if (!empty($contactStr)) {
                $contactStr .= '  |  ';
            }
            $contactStr .= 'Email: ' . substr($contactInfo['email'], 0, 22);
        }

        if (!empty($contactStr)) {
            $pdf->MultiCell($contentWidth, 2, $contactStr, 0, 'C');
        }

        $pdf->Ln(1);

        // Titre REÇU
        $pdf->SetFillColor($successColor[0], $successColor[1], $successColor[2]);
        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetFont('helvetica', 'B', 14);
        $pdf->Cell($contentWidth, 6, 'REÇU DE PAIEMENT', 0, 1, 'C', true);

        // Informations du reçu
        $pdf->SetTextColor($primaryColor[0], $primaryColor[1], $primaryColor[2]);
        $pdf->SetFont('helvetica', 'B', 9);
        $pdf->Cell($contentWidth, 3.5, 'N°: ' . substr($paiement['transaction_reference'], 0, 30), 0, 1, 'C');
        $pdf->SetFont('helvetica', '', 8);
        $pdf->SetTextColor(0, 0, 0);
        $pdf->Cell($contentWidth, 3, date('d/m/Y H:i', strtotime($paiement['date_transaction'])), 0, 1, 'C');

        $pdf->SetLineStyle(array('width' => 0.3, 'cap' => 'butt', 'join' => 'miter', 'dash' => 0, 'color' => $accentColor));
        $pdf->Line(4, $pdf->GetY(), $pageWidth - 4, $pdf->GetY());

        $pdf->Ln(0.5);

        // Section ÉTUDIANT
        $pdf->SetFont('helvetica', 'B', 9);
        $pdf->Cell(60, 3.5, 'ÉTUDIANT', 0, 1, 'L');

        $pdf->SetFont('helvetica', '', 8);
        $pdf->Cell(30, 2.8, 'Matricule:', 0, 0, 'L');
        $pdf->Cell($contentWidth - 30, 2.8, substr($paiement['matricule'], 0, 25), 0, 1, 'L');

        $pdf->Cell(30, 2.8, 'Nom:', 0, 0, 'L');
        $pdf->MultiCell($contentWidth - 30, 2.8, substr($paiement['noms'], 0, 40), 0, 'L');

        $pdf->Cell(30, 2.8, 'Promotion:', 0, 0, 'L');
        $pdf->MultiCell($contentWidth - 30, 2.8, substr($paiement['promotion_nom'] ?? '-', 0, 35), 0, 'L');

        $pdf->Cell(30, 2.8, 'Section:', 0, 0, 'L');
        $pdf->MultiCell($contentWidth - 30, 2.8, substr($paiement['faculte_nom'] ?? '-', 0, 35), 0, 'L');

        $pdf->SetLineStyle(array('width' => 0.3, 'cap' => 'butt', 'join' => 'miter', 'dash' => 0, 'color' => $accentColor));
        $pdf->Line(4, $pdf->GetY(), $pageWidth - 4, $pdf->GetY());

        $pdf->Ln(0.5);

        // Section PAIEMENT
        $pdf->SetFont('helvetica', 'B', 9);
        $pdf->Cell(60, 3.5, 'DÉTAILS DU PAIEMENT', 0, 1, 'L');

        $pdf->SetFont('helvetica', '', 8);
        $pdf->Cell(30, 2.8, 'Frais:', 0, 0, 'L');
        $pdf->MultiCell($contentWidth - 30, 2.8, substr($paiement['frais_designation'], 0, 40), 0, 'L');

        $pdf->Cell(30, 2.8, 'Montant:', 0, 0, 'L');
        $pdf->SetFont('helvetica', 'B', 9);
        $pdf->Cell($contentWidth - 30, 2.8, number_format($paiement['montant'], 2, ',', ' ') . ' ' . $paiement['devise'], 0, 1, 'L');

        $pdf->SetFont('helvetica', '', 8);
        $pdf->Cell(30, 2.8, 'Mode:', 0, 0, 'L');
        $pdf->Cell($contentWidth - 30, 2.8, substr($paiement['mode_paiement'] ?? '-', 0, 30), 0, 1, 'L');

        $pdf->SetLineStyle(array('width' => 0.3, 'cap' => 'butt', 'join' => 'miter', 'dash' => 0, 'color' => $accentColor));
        $pdf->Line(4, $pdf->GetY(), $pageWidth - 4, $pdf->GetY());

        $pdf->Ln(0.5);

        // Section SITUATION
        $pdf->SetFont('helvetica', 'B', 9);
        $pdf->Cell(60, 3.5, 'SITUATION DU PAIEMENT', 0, 1, 'L');

        $pdf->SetFont('helvetica', '', 8);
        $pdf->Cell(50, 2.8, 'Montant total du frais:', 0, 0, 'L');
        $pdf->SetFont('helvetica', 'B', 8);
        $pdf->Cell($contentWidth - 50, 2.8, number_format($montantTotalFrais, 2, ',', ' ') . ' ' . $paiement['devise'], 0, 1, 'R');

        $pdf->SetFont('helvetica', '', 8);
        $pdf->Cell(50, 2.8, 'Total payé:', 0, 0, 'L');
        $pdf->SetFont('helvetica', 'B', 8);
        $pdf->Cell($contentWidth - 50, 2.8, number_format($totalDejaPaye, 2, ',', ' ') . ' ' . $paiement['devise'], 0, 1, 'R');

        $pdf->SetFont('helvetica', '', 8);
        $pdf->Cell(50, 2.8, 'Reste à payer:', 0, 0, 'L');
        $pdf->SetFont('helvetica', 'B', 9);
        if ($resteAPayer <= 0) {
            $pdf->SetTextColor($successColor[0], $successColor[1], $successColor[2]);
            $pdf->Cell($contentWidth - 50, 2.8, 'PAYÉ INTÉGRALEMENT', 0, 1, 'R');
        } else {
            $pdf->SetTextColor(0, 0, 0);
            $pdf->Cell($contentWidth - 50, 2.8, number_format($resteAPayer, 2, ',', ' ') . ' ' . $paiement['devise'], 0, 1, 'R');
        }

        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetLineStyle(array('width' => 0.3, 'cap' => 'butt', 'join' => 'miter', 'dash' => 0, 'color' => $accentColor));
        $pdf->Line(4, $pdf->GetY(), $pageWidth - 4, $pdf->GetY());

        $pdf->Ln(0.5);

        // Section CAISSIER
        $pdf->SetFont('helvetica', 'B', 9);
        $pdf->Cell(60, 3.5, 'CAISSIER', 0, 1, 'L');

        $pdf->SetFont('helvetica', '', 8);
        $agentName = isset($paiement['agent_nom']) ? $paiement['agent_nom'] : 'Non spécifié';
        $pdf->Cell(30, 2.8, 'Nom:', 0, 0, 'L');
        $pdf->MultiCell($contentWidth - 30, 2.8, substr($agentName, 0, 35), 0, 'L');

        // Section SIGNATURES
        $pdf->SetFont('helvetica', 'B', 9);
        $pdf->Cell(($contentWidth / 2) - 1, 3.5, 'CAISSIER', 0, 0, 'C');
        $pdf->Cell(($contentWidth / 2) - 1, 3.5, 'ÉTUDIANT', 0, 1, 'C');

        // Espace pour signatures
        $pdf->Ln(10);

        // Lignes de signature
        $pdf->SetLineStyle(array('width' => 0.3, 'cap' => 'butt', 'join' => 'miter', 'dash' => 0, 'color' => array(100, 100, 100)));
        $x1 = 4;
        $y_line = $pdf->GetY();
        $x2 = 4 + ($contentWidth / 2) - 2;
        $x3 = 4 + ($contentWidth / 2) + 1;
        $x4 = $pageWidth - 4;
        
        $pdf->Line($x1, $y_line, $x2, $y_line);
        $pdf->Line($x3, $y_line, $x4, $y_line);

        // Ajouter le QR code centré après les signatures
        $pdf->Ln(8);
        
        // QR Code - Pointer vers la page de détails du paiement
        $baseUrl = $_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['HTTP_HOST'] . dirname(dirname($_SERVER['REQUEST_URI']));
        $qrData = $baseUrl . '/controller/qr_paiement.php?id=' . $paiement['id'];

        $qrSize = 18;
        $qrX = 4 + ($contentWidth - $qrSize) / 2;
        $qrY = $pdf->GetY();
        
        $style = array('border' => 2, 'vpadding' => 'auto', 'hpadding' => 'auto', 'fgcolor' => array(0, 0, 0), 'bgcolor' => array(255, 255, 255), 'module_width' => 0.8, 'module_height' => 0.8);
        $pdf->write2DBarcode($qrData, 'QRCODE,L', $qrX, $qrY, $qrSize, $qrSize, $style, 'N');

        $filename = 'recu_' . $paiement['recu_numero'] . '_A5.pdf';
        $pdf->Output($filename, 'I');
    }

    else {
        // Format POS identique à print_recu_pos.php
        class ReceiptPDF extends TCPDF
        {
            public function Footer()
            {
                $this->SetY(-10);
                $this->SetFont('helvetica', 'B', 6);
                $this->SetTextColor(80, 80, 80);
            }
        }

        $pdf = new ReceiptPDF('P', 'pt', array(204, 1000), true, 'UTF-8', false);
        $pdf->SetCreator('Système de gestion universitaire');
        $pdf->SetAuthor($configUniversite['nom'] ?? 'Administration');
        $pdf->SetTitle('Reçu de Paiement - ' . $paiement['transaction_reference']);
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetMargins(6, 8, 6);
        $pdf->SetAutoPageBreak(true, 15);
        $pdf->AddPage();

        // Logo centré
        if (!empty($configUniversite['logo'])) {
            $logoPath = dirname(__DIR__) . '/' . $configUniversite['logo'];
            if (file_exists($logoPath)) {
                $logoWidth = 40;
                $logoHeight = 60;
                $xPos = ($pdf->getPageWidth() - $logoWidth) / 2;
                $pdf->Image($logoPath, $xPos, $pdf->GetY(), $logoWidth, $logoHeight);
                $pdf->Ln($logoHeight + 2);
            }
        }

        // En-tête établissement
        $pdf->SetFont('helvetica', 'B', 11);
        $pdf->SetTextColor(0, 0, 0);
        $etablissement = strtoupper($configUniversite['sigle'] ?? 'ÉTABLISSEMENT');
        $pdf->Cell(0, 12, $etablissement, 0, 1, 'C');
        
        // Afficher les informations de contact
        $pdf->SetFont('helvetica', '', 8);
        $pdf->SetTextColor(0, 0, 0);
        if (!empty($contactInfo['adresse'])) {
            $pdf->MultiCell(0, 8, $contactInfo['adresse'], 0, 'C');
        }
        if (!empty($contactInfo['telephone'])) {
            $pdf->Cell(0, 8, 'Tél: ' . $contactInfo['telephone'], 0, 1, 'C');
        }
        if (!empty($contactInfo['email'])) {
            $pdf->Cell(0, 8, 'Email: ' . $contactInfo['email'], 0, 1, 'C');
        }

        // Ligne de séparation
        $pdf->SetLineStyle(array('width' => 0.8, 'cap' => 'butt', 'join' => 'miter', 'dash' => 0));
        $margins = $pdf->getMargins();
        $pdf->Line($margins['left'], $pdf->GetY(), $pdf->getPageWidth() - $margins['right'], $pdf->GetY());
        $pdf->Ln(4);

        // Titre du reçu
        $pdf->SetFont('helvetica', 'B', 10);
        $pdf->Cell(0, 10, 'RECU DE PAIEMENT', 0, 1, 'C');
        $pdf->Ln(3);

        // Informations du reçu - EN GRAS
        $pdf->SetFont('helvetica', 'B', 8);
        $pdf->Cell(0, 8, 'Date: ' . date('d/m/Y H:i', strtotime($paiement['date_transaction'])), 0, 1, 'L');
        $pdf->Cell(0, 8, 'Recu N°: ' . $paiement['transaction_reference'], 0, 1, 'L');
        $pdf->Cell(0, 8, 'Caissier: ' . ($paiement['agent_nom'] ?? 'N/A'), 0, 1, 'L');
        $pdf->Ln(3);

        // Informations étudiant
        $pdf->SetFont('helvetica', 'B', 9);
        $pdf->Cell(0, 8, 'ETUDIANT:', 0, 1, 'L');

        $pdf->SetFont('helvetica', 'B', 8);
        $nomComplet = trim(($paiement['noms'] ?? '') . ' ' . ($paiement['prenom'] ?? ''));
        $pdf->Cell(0, 8, $nomComplet !== '' ? $nomComplet : 'N/A', 0, 1, 'L');
        $pdf->Cell(0, 8, 'Matricule: ' . ($paiement['matricule'] ?? 'N/A'), 0, 1, 'L');
        $pdf->Cell(0, 8, 'Promotion: ' . ($paiement['promotion_nom'] ?? 'N/A'), 0, 1, 'L');
        $pdf->Cell(0, 8, 'Section: ' . ($paiement['faculte_nom'] ?? 'N/A'), 0, 1, 'L');
        $pdf->Ln(3);

        // Détails du paiement
        $pdf->SetFont('helvetica', 'B', 9);
        $pdf->Cell(0, 8, 'DETAILS DU PAIEMENT:', 0, 1, 'L');

        $pdf->SetFont('helvetica', 'B', 8);
        $pdf->Cell(0, 8, 'Frais: ' . ($paiement['frais_designation'] ?? 'N/A'), 0, 1, 'L');
        $pdf->Cell(0, 8, 'Mode: ' . ($paiement['mode_paiement'] ?? 'N/A'), 0, 1, 'L');
        $pdf->Ln(3);

        // Ligne de séparation pour les montants
        $pdf->SetLineStyle(array('width' => 0.5, 'cap' => 'butt', 'join' => 'miter', 'dash' => 0));
        $pdf->Line($margins['left'], $pdf->GetY(), $pdf->getPageWidth() - $margins['right'], $pdf->GetY());
        $pdf->Ln(2);

        // Montants
        $pdf->SetFont('helvetica', 'B', 10);
        $pdf->Cell(0, 12, 'MONTANT PAYE: ' . number_format($paiement['montant'], 2, ',', ' ') . ' ' . $paiement['devise'], 0, 1, 'C');

        $pdf->Line($margins['left'], $pdf->GetY(), $pdf->getPageWidth() - $margins['right'], $pdf->GetY());
        $pdf->Ln(3);

        // Statut facture
        if ($montantTotalFrais > 0) {
            $pdf->SetFont('helvetica', 'B', 7);
            $pdf->Cell(0, 7, 'Total frais: ' . number_format($montantTotalFrais, 2, ',', ' ') . ' ' . $paiement['devise'], 0, 1, 'L');
            $pdf->Cell(0, 7, 'Total paye: ' . number_format($totalDejaPaye, 2, ',', ' ') . ' ' . $paiement['devise'], 0, 1, 'L');

            if ($resteAPayer > 0.01) {
                $pdf->Cell(0, 7, 'Solde restant: ' . number_format($resteAPayer, 2, ',', ' ') . ' ' . $paiement['devise'], 0, 1, 'L');
            } else {
                $pdf->SetTextColor($successColor[0], $successColor[1], $successColor[2]);
                $pdf->Cell(0, 7, 'FACTURE SOLDEE', 0, 1, 'L');
                $pdf->SetTextColor(0, 0, 0);
            }
        }

        $pdf->Ln(4);

        // Pied de page
        $pdf->SetFont('helvetica', 'B', 6);
        $pdf->SetTextColor(0, 0, 0);
        $pdf->Cell(0, 5, 'Document genere le ' . date('d/m/Y a H:i'), 0, 1, 'C');
        $pdf->Cell(0, 5, 'Année académique: ' . ($paiement['annee_academique'] ?? 'N/A'), 0, 1, 'C');
        
        $pdf->Ln(2);

        // QR Code - Pointer vers la page de détails du paiement
        $baseUrl = $_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['HTTP_HOST'] . dirname(dirname($_SERVER['REQUEST_URI']));
        $qrData = $baseUrl . '/controller/qr_paiement.php?id=' . $paiement['id'];

        $qrSize = 40;
        $xPos = ($pdf->getPageWidth() - $qrSize) / 2;
        $barcodeObj = new TCPDF2DBarcode($qrData, 'QRCODE,L');
        $pdf->Image('@' . $barcodeObj->getBarcodePngData(2, 2), $xPos, $pdf->GetY(), $qrSize, $qrSize, 'PNG');

        $filename = 'recu_' . $paiement['recu_numero'] . '_POS.pdf';
        $pdf->Output($filename, 'I');
    }

} catch (Exception $e) {
    error_log('Erreur dans generer_recu_format.php: ' . $e->getMessage());
    echo "Une erreur est survenue lors de la génération du reçu: " . $e->getMessage();
}
?>
