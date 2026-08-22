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

// Récupérer l'ID du paiement
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($id <= 0) {
    die("ID de paiement non valide");
}

// Fonction pour convertir un nombre en lettres
function numberToWords($number, $devise = 'USD')
{
    $dictionary = [
        0 => 'zéro',
        1 => 'un',
        2 => 'deux',
        3 => 'trois',
        4 => 'quatre',
        5 => 'cinq',
        6 => 'six',
        7 => 'sept',
        8 => 'huit',
        9 => 'neuf',
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
        80 => 'quatre-vingts',
        90 => 'quatre-vingt-dix'
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
    // Initialisation
    $connexion = Connexion::getInstance()->getPDO();

    // Récupérer les informations du paiement avec les détails de l'affectation et du frais
    $stmt = $connexion->prepare("
    SELECT 
        pf.*, 
        af.id AS affectation_id,
        af.montant_specifique,
        af.montant_restant,
        af.statut_paiement,
        af.promotion_id,
        af.matricule_etudiant AS affectation_matricule_etudiant,
        e.noms, e.matricule,
        f.designation AS frais_designation,
        f.montant AS montant_frais,
        a.designation AS annee_academique,
        p.\"designationPromotion\" AS promotion_nom,
        CONCAT(s.\"designationSection\", ' - ', o.\"designationOrientation\") AS faculte_nom,
        t.reference AS transaction_reference,
        t.date_transaction,
        (SELECT COUNT(*) FROM paiements_frais WHERE affectation_id = af.id) AS nombre_versements,
        
        /* Calculate what this specific student has paid */
        (SELECT COALESCE(SUM(pf2.montant), 0) 
        FROM paiements_frais pf2
        JOIN transactions t2 ON pf2.transaction_id = t2.id
        WHERE pf2.affectation_id = af.id
        AND pf2.matricule_etudiant = e.matricule
        AND (t2.statut = 'Confirmée' OR pf2.est_confirme = 1)) AS total_deja_paye
    FROM paiements_frais pf
    INNER JOIN affectation_frais af ON pf.affectation_id = af.id
    INNER JOIN frais f ON af.frais_id = f.id
    INNER JOIN etudiant e ON pf.matricule_etudiant = e.matricule
    LEFT JOIN promotion p ON e.promotion_idpromotion = p.idpromotion
    LEFT JOIN orientation o ON p.orientation_idorientation = o.idorientation
    LEFT JOIN section s ON o.section_idsection = s.idsection
    LEFT JOIN transactions t ON pf.transaction_id = t.id
    LEFT JOIN annee_acad a ON f.annee_acad_id = a.idannee_acad
    WHERE pf.id = :id
    ");

    $stmt->bindParam(':id', $id);
    $stmt->execute();
    $paiement = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$paiement) {
        die("Paiement non trouvé");
    }

    // Récupérer l'historique des paiements pour ce même frais
    $stmt = $connexion->prepare("
        SELECT 
            pf.id,
            pf.montant,
            pf.devise,
            pf.date_valeur,
            pf.mode_paiement,
            pf.recu_numero,
            t.date_transaction
        FROM paiements_frais pf
        LEFT JOIN transactions t ON pf.transaction_id = t.id
        WHERE pf.affectation_id = :affectation_id
        ORDER BY t.date_transaction
    ");
    $stmt->bindParam(':affectation_id', $paiement['affectation_id']);
    $stmt->execute();
    $historique_paiements = $stmt->fetchAll(PDO::FETCH_ASSOC);

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
    $pdf->SetTitle('Reçu de Paiement - ' . $paiement['transaction_reference']);
    $pdf->SetSubject('Reçu de paiement');
    $pdf->SetKeywords('Reçu, Paiement, Frais');

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

    // Titre du reçu avec fond coloré
    $pdf->SetFillColor($successColor[0], $successColor[1], $successColor[2]);
    $pdf->SetTextColor(255, 255, 255);
    $pdf->SetFont('helvetica', 'B', 16);
    $pdf->Ln(5);
    $pdf->Cell(0, 10, 'REÇU DE PAIEMENT', 0, 1, 'C', true);

    // Numéro de reçu et date avec année académique
    $pdf->SetFillColor(245, 245, 245);
    $pdf->SetTextColor($primaryColor[0], $primaryColor[1], $primaryColor[2]);
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->Ln(5);

    // Boîte pour la référence
    $pdf->RoundedRect(15, $pdf->GetY(), 180, 20, 2.0, '1111', 'DF', array(), array(245, 245, 245));
    $pdf->SetY($pdf->GetY() + 5);
    $pdf->Cell(90, 6, 'Reçu N°: ' . $paiement['transaction_reference'], 0, 0, 'L');
    $pdf->Cell(90, 6, 'Date: ' . date('d/m/Y H:i', strtotime($paiement['date_transaction'])), 0, 1, 'R');
    $pdf->Cell(0, 6, 'Année académique: ' . ($paiement['annee_academique'] ?? 'Non spécifiée'), 0, 1, 'C');

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

    // Détails du paiement
    $pdf->Ln(8);
    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->SetTextColor(0, 0, 0);
    $pdf->Cell(0, 8, 'DÉTAILS DU PAIEMENT ACTUEL', 0, 1, 'L');

    // Table des détails du paiement
    $pdf->SetFillColor(245, 245, 245);
    $pdf->SetFont('helvetica', 'B', 9);

    // En-têtes de colonnes avec fond coloré
    $pdf->SetFillColor($accentColor[0], $accentColor[1], $accentColor[2]);
    $pdf->SetTextColor(255, 255, 255);
    $pdf->Cell(70, 8, 'Description', 1, 0, 'C', true);
    $pdf->Cell(50, 8, 'Référence', 1, 0, 'C', true);
    $pdf->Cell(60, 8, 'Montant', 1, 1, 'C', true);

    // Contenu de la table
    $pdf->SetTextColor(0, 0, 0);
    $pdf->SetFont('helvetica', '', 9);
    $pdf->Cell(70, 8, $paiement['frais_designation'], 1, 0, 'L');
    $pdf->Cell(50, 8, $paiement['transaction_reference'], 1, 0, 'C');
    $pdf->SetFont('helvetica', 'B', 9);
    $pdf->Cell(60, 8, number_format($paiement['montant'], 2, ',', ' ') . ' ' . $paiement['devise'], 1, 1, 'R');

    // Montant en toutes lettres
    $pdf->Ln(3);
    $pdf->SetFont('helvetica', 'I', 10);
    $pdf->SetTextColor(60, 60, 60);
    $pdf->Cell(40, 8, 'Soit en lettres:', 0, 0, 'L');
    $pdf->Cell(140, 8, numberToWords($paiement['montant'], $paiement['devise']), 0, 1, 'L');
    $pdf->SetTextColor(0, 0, 0);

    // Situation du paiement des frais
    $pdf->Ln(5);
    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->Cell(0, 8, 'SITUATION DU PAIEMENT DES FRAIS', 0, 1, 'L');

    // Cadre pour la situation de paiement
    $pdf->SetFillColor(250, 250, 250);
    $pdf->RoundedRect(15, $pdf->GetY(), 180, 35, 2.0, '1111', 'DF');

    $currentY = $pdf->GetY() + 5;
    $pdf->SetY($currentY);

    // Déterminer le montant total du frais (prendre le montant spécifique s'il existe, sinon le montant standard)
    $montantTotalFrais = !empty($paiement['montant_specifique']) ? $paiement['montant_specifique'] : $paiement['montant_frais'];

    $estFraisPromotion = !empty($paiement['promotion_id']) && empty($paiement['affectation_matricule_etudiant']);

    // Montant total du frais
    $pdf->SetFont('helvetica', 'B', 9);
    $pdf->Cell(90, 6, 'Montant total du frais:', 0, 0, 'L');
    $pdf->SetFont('helvetica', '', 9);
    $pdf->Cell(90, 6, number_format($montantTotalFrais, 2, ',', ' ') . ' ' . $paiement['devise'], 0, 1, 'L');

    // Total déjà payé (avant ce paiement)
    $totalDejaPaye = $paiement['total_deja_paye'];
    $pdf->SetFont('helvetica', 'B', 9);
    $pdf->Cell(90, 6, 'Total déjà payé (incluant ce paiement):', 0, 0, 'L');
    $pdf->SetFont('helvetica', '', 9);
    $pdf->Cell(90, 6, number_format($totalDejaPaye, 2, ',', ' ') . ' ' . $paiement['devise'], 0, 1, 'L');

    // Reste à payer
    $resteAPayer = $montantTotalFrais - $totalDejaPaye;
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

    // Statut du paiement
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

    // Nombre de versements
    $pdf->SetFont('helvetica', 'B', 9);
    $pdf->Cell(90, 6, 'Nombre de versements:', 0, 0, 'L');
    $pdf->SetFont('helvetica', '', 9);
    $pdf->Cell(90, 6, "", 0, 1, 'L');





    // Générer un QR code avec les informations du paiement
    $qrData = "RECU: " . $paiement['recu_numero'] . "\n";
    $qrData .= "ETUDIANT: " . $paiement['noms'] . "\n";
    $qrData .= "MATRICULE: " . $paiement['matricule'] . "\n";
    $qrData .= "FRAIS: " . $paiement['frais_designation'] . "\n";
    $qrData .= "MONTANT: " . number_format($paiement['montant'], 2, ',', ' ') . " " . $paiement['devise'] . "\n";
    $qrData .= "TOTAL PAYÉ: " . number_format($paiement['total_deja_paye'], 2, ',', ' ') . " " . $paiement['devise'] . "\n";
    $qrData .= "RESTE: " . number_format($paiement['montant_restant'], 2, ',', ' ') . " " . $paiement['devise'] . "\n";
    $qrData .= "DATE: " . date('d/m/Y', strtotime($paiement['date_transaction'])) . "\n";
    $qrData .= "REF: " . $paiement['transaction_reference'];

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

    // Zone de signature à gauche (Caissier)
    $pdf->Cell(80, 6, 'LE CAISSIER', 0, 0, 'C');

    // Zone de signature à droite (Étudiant)
    $pdf->Cell(95, 6, 'L\'ÉTUDIANT', 0, 1, 'C');

    // Espace pour les signatures
    $pdf->Ln(25);

    // Lignes pour les signatures
    $pdf->Line(20, $pdf->GetY(), 80, $pdf->GetY());
    $pdf->Line(110, $pdf->GetY(), 170, $pdf->GetY());

    // Pied de page
    $pdf->SetY(-30);
    $pdf->SetFont('helvetica', 'I', 8);
    $pdf->SetTextColor(100, 100, 100);
    $pdf->Cell(0, 5, 'Ce reçu est un document officiel. Veuillez le conserver soigneusement.', 0, 1, 'C');
    $pdf->Cell(0, 5, 'Document généré le ' . date('d/m/Y à H:i') . ' par le système de gestion universitaire', 0, 1, 'C');

    if (!$paiement['est_confirme']) {
        $pdf->SetTextColor($warningColor[0], $warningColor[1], $warningColor[2]);
        $pdf->Cell(0, 5, 'ATTENTION: Ce paiement est en attente de confirmation.', 0, 1, 'C');
    }

    // Générer le PDF
    $pdf->Output('recu_' . $paiement['recu_numero'] . '.pdf', 'I');
} catch (Exception $e) {
    // En cas d'erreur
    error_log('Erreur dans generer_recu.php: ' . $e->getMessage());
    echo "Une erreur est survenue lors de la génération du reçu: " . $e->getMessage();
}
