<?php
session_start();
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/models/Universite.php';
require_once dirname(__DIR__) . '/vendor/autoload.php';

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['id'])) {
    header('Location: ../index.php');
    exit;
}

// Vérifier si l'ID de la transaction est fourni
if (!isset($_GET['transaction_id']) || empty($_GET['transaction_id'])) {
    header('Location: ../?view=finance/operations_caisse');
    exit;
}

$transaction_id = intval($_GET['transaction_id']);
$format = isset($_GET['format']) ? $_GET['format'] : '1page'; // '1page' ou '2pages'
$connexion = Connexion::getInstance()->getPDO();


// Fonction alternative pour convertir un nombre en lettres sans NumberFormatter
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


// Récupérer les détails de la transaction
$stmt = $connexion->prepare("
    SELECT t.*, 
           c.designation as caisse_nom, 
           c.devise,
           a.noms as agent_nom,
           a.email as agent_email,
           cb.designation as categorie_nom,
           sc.id as session_caisse_id,
           sc.date_ouverture as session_date_ouverture
    FROM transactions t
    LEFT JOIN caisses c ON t.source_id = c.id AND t.source = 'Caisse'
    LEFT JOIN agent a ON t.\"idAgent\" = a.\"idAgent\"
    LEFT JOIN categories_budget cb ON t.categorie_id = cb.id
    LEFT JOIN sessions_caisse sc ON t.session_caisse_id = sc.id
    WHERE t.id = :transaction_id
");
$stmt->bindParam(':transaction_id', $transaction_id);
$stmt->execute();
$transaction = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$transaction) {
    header('Location: ../?view=finance/operations_caisse');
    exit;
}

// Récupérer les pièces jointes de la transaction
$pieces_jointes = [];
if (!empty($transaction['pieces_jointes'])) {
    $pieces_jointes = explode(',', $transaction['pieces_jointes']);
}

// Récupérer les informations de l'université
$universite = new Universite();
$configUniversite = $universite->getConfigurationUniversite();

// Créer une instance de TCPDF
$pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);

// Définir les informations du document
$pdf->SetCreator('Système de gestion universitaire');
$pdf->SetAuthor($configUniversite['nom'] ?? 'Administration');
$pdf->SetTitle('Reçu de Caisse - ' . $transaction['reference']);
$pdf->SetSubject('Reçu de caisse');
$pdf->SetKeywords('Reçu, Caisse, Finance');

// Supprimer les en-têtes et pieds de page par défaut
$pdf->setPrintHeader(false);
$pdf->setPrintFooter(false);

// Définir les marges
$pdf->SetMargins(15, 15, 15);
$pdf->SetAutoPageBreak(true, 15);

// Couleurs pour le design
$primaryColor = array(0, 87, 146); // Bleu foncé
$secondaryColor = array(70, 130, 180); // Bleu acier
$accentColor = array(0, 121, 194); // Bleu moyen
$successColor = array(40, 167, 69); // Vert
$dangerColor = array(220, 53, 69); // Rouge
$warningColor = array(255, 193, 7); // Jaune

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
if ($transaction['type'] === 'Recette') {
    $pdf->SetFillColor($successColor[0], $successColor[1], $successColor[2]);
    $titre = 'REÇU DE CAISSE';
} elseif ($transaction['type'] === 'Dépense') {
    $pdf->SetFillColor($dangerColor[0], $dangerColor[1], $dangerColor[2]);
    $titre = 'BORDEREAU DE PAIEMENT';
} else {
    $pdf->SetFillColor($primaryColor[0], $primaryColor[1], $primaryColor[2]);
    $titre = 'ORDRE DE TRANSFERT';
}

$pdf->SetTextColor(255, 255, 255);
$pdf->SetFont('helvetica', 'B', 16);
$pdf->Ln(5);
$pdf->Cell(0, 10, $titre, 0, 1, 'C', true);

// Numéro de reçu et date
$pdf->SetFillColor(245, 245, 245);
$pdf->SetTextColor($primaryColor[0], $primaryColor[1], $primaryColor[2]);
$pdf->SetFont('helvetica', 'B', 12);
$pdf->Ln(5);

// Boîte pour la référence
$pdf->RoundedRect(15, $pdf->GetY(), 180, 15, 2.0, '1111', 'DF', array(), array(245, 245, 245));
$pdf->SetY($pdf->GetY() + 5);
$pdf->Cell(90, 6, 'Référence: ' . $transaction['reference'], 0, 0, 'L');
$pdf->Cell(90, 6, 'Date: ' . date('d/m/Y H:i', strtotime($transaction['date_transaction'])), 0, 1, 'R');

// Informations principales
$pdf->Ln(5);
$pdf->SetFont('helvetica', 'B', 10);
$pdf->SetTextColor(0, 0, 0);
$pdf->Cell(40, 8, 'CAISSE:', 0, 0, 'L');
$pdf->SetFont('helvetica', '', 10);
$pdf->Cell(140, 8, $transaction['caisse_nom'], 0, 1, 'L');

$pdf->SetFont('helvetica', 'B', 10);
if ($transaction['type'] === 'Recette') {
    $pdf->Cell(40, 8, 'DÉPOSÉ PAR:', 0, 0, 'L');
    $pdf->SetFont('helvetica', '', 10);
    $pdf->Cell(140, 8, $transaction['depositaire'] ?? 'N/A', 0, 1, 'L');
} elseif ($transaction['type'] === 'Dépense') {
    $pdf->Cell(40, 8, 'BÉNÉFICIAIRE:', 0, 0, 'L');
    $pdf->SetFont('helvetica', '', 10);
    $pdf->Cell(140, 8, $transaction['beneficiaire'] ?? 'N/A', 0, 1, 'L');
} else {
    $pdf->Cell(40, 8, 'ORDONNÉ PAR:', 0, 0, 'L');
    $pdf->SetFont('helvetica', '', 10);
    $pdf->Cell(140, 8, $transaction['beneficiaire'] ?? 'N/A', 0, 1, 'L');
}


$pdf->SetFont('helvetica', 'B', 10);
$pdf->Cell(40, 8, 'CATÉGORIE:', 0, 0, 'L');
$pdf->SetFont('helvetica', '', 10);
$pdf->Cell(140, 8, $transaction['categorie_nom'], 0, 1, 'L');

// Montant avec mise en évidence
$pdf->SetFont('helvetica', 'B', 10);
$pdf->Cell(40, 8, 'MONTANT:', 0, 0, 'L');
$pdf->SetFont('helvetica', 'B', 12);
if ($transaction['type'] === 'Recette') {
    $pdf->SetTextColor($successColor[0], $successColor[1], $successColor[2]);
} elseif ($transaction['type'] === 'Dépense') {
    $pdf->SetTextColor($dangerColor[0], $dangerColor[1], $dangerColor[2]);
} else {
    $pdf->SetTextColor($accentColor[0], $accentColor[1], $accentColor[2]);
}
$pdf->Cell(140, 8, number_format($transaction['montant'], 2, ',', ' ') . ' ' . $transaction['devise'], 0, 1, 'L');


// Montant en toutes lettres
$pdf->Ln(2);
$pdf->SetFont('helvetica', 'I', 10);
$pdf->SetTextColor(60, 60, 60);
$pdf->Cell(40, 8, 'SOIT:', 0, 0, 'L');
$pdf->Cell(140, 8, numberToWords($transaction['montant'], $transaction['devise']), 0, 1, 'L');
$pdf->SetTextColor(0, 0, 0);


// Description / Motif
$pdf->SetTextColor(0, 0, 0);
$pdf->SetFont('helvetica', 'B', 10);
$pdf->Cell(40, 8, 'MOTIF:', 0, 0, 'L');
$pdf->SetFont('helvetica', '', 10);
$pdf->Cell(140, 8, '', 0, 1, 'L'); // Cellule vide pour l'espacement

$pdf->MultiCell(0, 8, $transaction['description'], 0, 'L');

// Statut et session
$pdf->Ln(5);
$pdf->SetFont('helvetica', 'B', 10);
$pdf->Cell(40, 8, 'STATUT:', 0, 0, 'L');
$pdf->SetFont('helvetica', '', 10);
if ($transaction['statut'] === 'Confirmée') {
    $pdf->SetTextColor($successColor[0], $successColor[1], $successColor[2]);
    $pdf->Cell(140, 8, 'CONFIRMÉE', 0, 1, 'L');
} elseif ($transaction['statut'] === 'Provisoire') {
    $pdf->SetTextColor($warningColor[0], $warningColor[1], $warningColor[2]);
    $pdf->Cell(140, 8, 'PROVISOIRE', 0, 1, 'L');
} else {
    $pdf->SetTextColor($dangerColor[0], $dangerColor[1], $dangerColor[2]);
    $pdf->Cell(140, 8, $transaction['statut'], 0, 1, 'L');
}

$pdf->SetTextColor(0, 0, 0);
$pdf->SetFont('helvetica', 'B', 10);
$pdf->Cell(40, 8, 'OPÉRATEUR:', 0, 0, 'L');
$pdf->SetFont('helvetica', '', 10);
$pdf->Cell(140, 8, $transaction['agent_nom'], 0, 1, 'L');

// Si on a choisi le format 2 pages, ajouter la 2ème page avec QR code et informations supplémentaires
if ($format === '2pages') {
    $pdf->AddPage();
    
        // Titre de la page
        $pdf->SetFillColor($primaryColor[0], $primaryColor[1], $primaryColor[2]);
        $pdf->SetTextColor(255, 255, 255);
        $pdf->SetFont('helvetica', 'B', 16);
        $pdf->Cell(0, 10, 'DÉTAILS DE LA TRANSACTION', 0, 1, 'C', true);
        
        // QR Code avec les détails de la transaction
        $pdf->Ln(10);
        
        // Générer les données du QR code
        $qrData = "REF: " . $transaction['reference'] . "\n";
        $qrData .= "DATE: " . date('d/m/Y H:i', strtotime($transaction['date_transaction'])) . "\n";
        $qrData .= "TYPE: " . $transaction['type'] . "\n";
        $qrData .= "MONTANT: " . number_format($transaction['montant'], 2, ',', ' ') . " " . $transaction['devise'] . "\n";
        $qrData .= "CAISSE: " . $transaction['caisse_nom'] . "\n";
        $qrData .= "OPÉRATEUR: " . $transaction['agent_nom'] . "\n";
        $qrData .= "STATUT: " . $transaction['statut'] . "\n";
        
        // Afficher le QR code
        $style = array(
            'border' => 2,
            'vpadding' => 'auto',
            'hpadding' => 'auto',
            'fgcolor' => array(0, 0, 0),
            'bgcolor' => array(255, 255, 255),
            'module_width' => 1, // largeur d'un module QR
            'module_height' => 1 // hauteur d'un module QR
        );
        
        // Placer le QR code sur la droite
        $pdf->SetY(70);
        $pdf->Cell(120, 0, '', 0, 0);
        $pdf->write2DBarcode($qrData, 'QRCODE,L', 140, $pdf->GetY(), 50, 50, $style, 'N');
        
        // Informations détaillées à gauche du QR code
        $pdf->SetY(70);
        $pdf->SetFont('helvetica', 'B', 10);
        $pdf->SetTextColor(0, 0, 0);
        $pdf->Cell(0, 6, 'Informations détaillées', 0, 1, 'L');
        $pdf->Ln(2);
        
        // Table d'informations détaillées
        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetFont('helvetica', '', 9);
        $startY = $pdf->GetY();
        
        // Première colonne
        $pdf->SetFont('helvetica', 'B', 9);
        $pdf->Cell(40, 6, 'Identifiant:', 0, 0, 'L');
        $pdf->SetFont('helvetica', '', 9);
        $pdf->Cell(80, 6, $transaction['id'], 0, 1, 'L');
        
        $pdf->SetFont('helvetica', 'B', 9);
        $pdf->Cell(40, 6, 'Session de caisse:', 0, 0, 'L');
        $pdf->SetFont('helvetica', '', 9);
        $pdf->Cell(80, 6, 'ID: ' . $transaction['session_caisse_id'] . 
                          ' (Ouverte le ' . date('d/m/Y H:i', strtotime($transaction['session_date_ouverture'])) . ')', 0, 1, 'L');
        
        if ($transaction['type'] === 'Transfert') {
            $pdf->SetFont('helvetica', 'B', 9);
            $pdf->Cell(40, 6, 'Destination:', 0, 0, 'L');
            $pdf->SetFont('helvetica', '', 9);
            $destinationType = '';
            $destinationName = '';
            
            // Récupérer les infos de destination si c'est un transfert

            // Récupérer les infos de destination si c'est un transfert
    if ($transaction['destination_id']) {
        // Vérifier d'abord si c'est une caisse
        $stmt = $connexion->prepare("SELECT designation FROM caisses WHERE id = :id");
        $stmt->bindParam(':id', $transaction['destination_id']);
        $stmt->execute();
        $dest = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($dest) {
            $destinationType = 'Caisse';
            $destinationName = $dest['designation'];
        } else {
            // Si ce n'est pas une caisse, essayer comme compte bancaire
            $stmt = $connexion->prepare("SELECT nom_banque, numero_compte FROM comptes_bancaires WHERE id = :id");
            $stmt->bindParam(':id', $transaction['destination_id']);
            $stmt->execute();
            $dest = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($dest) {
                $destinationType = 'Compte bancaire';
                $destinationName = $dest['nom_banque'] . ' - ' . $dest['numero_compte'];
            } else {
                $destinationName = 'Inconnue';
            }
        }
    }
            
            
            $pdf->Cell(80, 6, $destinationType . ': ' . $destinationName, 0, 1, 'L');
            
            if (!empty($transaction['taux_change'])) {
                $pdf->SetFont('helvetica', 'B', 9);
                $pdf->Cell(40, 6, 'Taux de change:', 0, 0, 'L');
                $pdf->SetFont('helvetica', '', 9);
                $pdf->Cell(80, 6, '1 ' . $transaction['devise'] . ' = ' . number_format($transaction['taux_change'], 6, ',', ' '), 0, 1, 'L');
            }
        }
        
        // Information sur la dernière modification
        $pdf->SetFont('helvetica', 'B', 9);
        $pdf->Cell(40, 6, 'Enregistrée le:', 0, 0, 'L');
        $pdf->SetFont('helvetica', '', 9);
        $pdf->Cell(80, 6, date('d/m/Y H:i', strtotime($transaction['date_creation'])), 0, 1, 'L');
        
        if (!empty($transaction['date_modification'])) {
            $pdf->SetFont('helvetica', 'B', 9);
            $pdf->Cell(40, 6, 'Modifiée le:', 0, 0, 'L');
            $pdf->SetFont('helvetica', '', 9);
            $pdf->Cell(80, 6, date('d/m/Y H:i', strtotime($transaction['date_modification'])), 0, 1, 'L');
        }
        
        // Si annulée, afficher les informations d'annulation
        if ($transaction['statut'] === 'Annulée' && !empty($transaction['motif_annulation'])) {
            $pdf->Ln(5);
            $pdf->SetFont('helvetica', 'B', 10);
            $pdf->SetTextColor($dangerColor[0], $dangerColor[1], $dangerColor[2]);
            $pdf->Cell(0, 6, 'Motif d\'annulation:', 0, 1, 'L');
            $pdf->SetFont('helvetica', '', 9);
            $pdf->MultiCell(0, 6, $transaction['motif_annulation'], 0, 'L');
        }
        
        // Pièces jointes
        if (!empty($pieces_jointes)) {
            $pdf->Ln(10);
            $pdf->SetFont('helvetica', 'B', 10);
            $pdf->SetTextColor(0, 0, 0);
            $pdf->Cell(0, 6, 'Pièces jointes:', 0, 1, 'L');
            $pdf->SetFont('helvetica', '', 9);
            
            foreach ($pieces_jointes as $index => $piece) {
                $pdf->Cell(0, 6, ($index + 1) . '. ' . trim($piece), 0, 1, 'L');
            }
        }
    }
    
    // Section des signatures (sur la première ou la deuxième page)
    $pdf->SetY(-80); // Positionner en bas de page
    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->SetTextColor(0, 0, 0);
    
    // Ligne de séparation
    $pdf->SetLineStyle(array('width' => 0.5, 'cap' => 'butt', 'join' => 'miter', 'dash' => 0, 'color' => array(180, 180, 180)));
    
    // Zone de signature à gauche (Caissier)
    $pdf->Cell(80, 6, 'LE CAISSIER', 0, 0, 'C');
    
    // Zone de signature à droite (Bénéficiaire ou Déposant)
    if ($transaction['type'] === 'Recette') {
        $pdf->Cell(95, 6, 'LE DÉPOSANT', 0, 1, 'C');
    } elseif ($transaction['type'] === 'Dépense') {
        $pdf->Cell(95, 6, 'LE BÉNÉFICIAIRE', 0, 1, 'C');
    } else {
        $pdf->Cell(95, 6, 'L\'AUTORITÉ COMPÉTENTE', 0, 1, 'C');
    }
    
    // Espace pour les signatures
    $pdf->Ln(25);
    
    // Lignes pour les signatures
    $pdf->Line(20, $pdf->GetY(), 80, $pdf->GetY());
    $pdf->Line(110, $pdf->GetY(), 170, $pdf->GetY());
    
    // Noms sous les lignes
    $pdf->SetFont('helvetica', '', 8);
    $pdf->Cell(80, 5, $transaction['agent_nom'], 0, 0, 'C');
    $pdf->Cell(95, 5, $transaction['beneficiaire'] ?? '', 0, 1, 'C');
    
    // Pied de page
    $pdf->SetY(-30);
    $pdf->SetFont('helvetica', 'I', 8);
    $pdf->SetTextColor(100, 100, 100);
    $pdf->Cell(0, 5, 'Document généré le ' . date('d/m/Y à H:i') . ' par le système', 0, 1, 'C');
    if ($transaction['statut'] === 'Provisoire') {
        $pdf->SetTextColor($warningColor[0], $warningColor[1], $warningColor[2]);
        $pdf->Cell(0, 5, 'ATTENTION: Ce document est provisoire et en attente de validation.', 0, 1, 'C');
    } elseif ($transaction['statut'] === 'Annulée') {
        $pdf->SetTextColor($dangerColor[0], $dangerColor[1], $dangerColor[2]);
        $pdf->Cell(0, 5, 'DOCUMENT ANNULÉ - NE CONSTITUE PAS UN REÇU VALIDE', 0, 1, 'C');
    }
    
    // Générer le PDF avec un nom basé sur la référence
    $filename = 'Recu_' . str_replace(['/', ' ', ':'], '_', $transaction['reference']) . '.pdf';
    $pdf->Output($filename, 'I');
    