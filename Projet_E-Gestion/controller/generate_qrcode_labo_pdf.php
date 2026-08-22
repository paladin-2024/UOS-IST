<?php
session_start();
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/models/Universite.php';
require_once dirname(__DIR__) . '/vendor/autoload.php'; // Pour TCPDF

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['id'])) {
    header('Location: ../index');
    exit();
}

// Vérifier si un ID est fourni
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['swal_error'] = [
        'title' => 'Erreur',
        'text' => 'ID de séance non spécifié',
        'icon' => 'error'
    ];
    header('Location: ../laboratoire/laboratoire.list');
    exit();
}

$idSeance = intval($_GET['id']);
$db = Connexion::getInstance()->getPDO();
$universiteModel = new Universite();

try {
    // Récupérer les informations de la séance
    $stmt = $db->prepare("
        SELECT sl.*, l.nom as nom_labo, l.localisation, a.noms as responsable_nom
        FROM seance_labo sl
        JOIN laboratoire l ON sl.idlabo = l.idlabo
        LEFT JOIN agent a ON sl.idresponsable = a.idAgent
        WHERE sl.idseance_labo = :idSeance
    ");
    $stmt->bindParam(':idSeance', $idSeance, PDO::PARAM_INT);
    $stmt->execute();
    
    $seance = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$seance) {
        $_SESSION['swal_error'] = [
            'title' => 'Erreur',
            'text' => 'Séance non trouvée',
            'icon' => 'error'
        ];
        header('Location: ../laboratoire/laboratoire.list');
        exit();
    }
    
    // Récupérer les informations de l'université
    $configUniversite = $universiteModel->getConfigurationUniversite();
    
    // Créer la classe TCPDF personnalisée
    class MYPDF extends TCPDF {
        // Pied de page personnalisé
        public function Footer() {
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

            // Nom de l'université et site web (centré sur sa propre ligne)
            $configUniversite = $GLOBALS['configUniversite'] ?? array('nom' => 'eGestion', 'site_web' => '');
            $this->Cell(($this->getPageWidth() - 30), 5, ($configUniversite['nom'] ?? 'eGestion') . ' • Document officiel. ' . ($configUniversite['site_web'] ?? ''), 0, 1, 'C');
        }
    }
    
    // Créer l'instance de la classe personnalisée
    $pdf = new MYPDF('P', 'mm', 'A4', true, 'UTF-8', false);
    
    // Rendre la variable configUniversite accessible globalement pour le pied de page
    $GLOBALS['configUniversite'] = $configUniversite;
    
    // Configurer le document
    $pdf->SetCreator('eGestion');
    $pdf->SetAuthor($configUniversite['nom'] ?? 'eGestion');
    $pdf->SetTitle('QR Code de Présence - Séance de Laboratoire');
    $pdf->SetSubject('QR Code de présence');
    $pdf->SetKeywords('Laboratoire, QR Code, Présence');
    
    // Supprimer les en-têtes et pieds de page par défaut
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
    
    // Ajouter le logo en filigrane
    if (!empty($configUniversite['logo'])) {
        $logoPath = dirname(__DIR__) . '/' . $configUniversite['logo'];
        if (file_exists($logoPath)) {
            // Sauvegarder l'état actuel
            $pdf->setAlpha(0.1);
            
            // Position au centre
            $pageWidth = $pdf->getPageWidth();
            $pageHeight = $pdf->getPageHeight();
            
            // Définir une largeur plus petite mais conserver la même hauteur
            $logoWidth = 70;
            $logoHeight = 100;
            
            $x = ($pageWidth - $logoWidth) / 2;
            $y = ($pageHeight - $logoHeight) / 2;
            
            // Ajouter l'image en filigrane avec largeur réduite
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
                $pdf->Image($logoPath, 20, 15, 20, 0, '', '', '', false, 200, '', false, false, 0);
            }
        }
        
        // Titre et informations de l'université
        $pdf->SetTextColor($primaryColor[0], $primaryColor[1], $primaryColor[2]);
        $pdf->SetFont('helvetica', 'B', 12);
        $pdf->SetXY(50, 15);
        $pdf->Cell(0, 8, strtoupper($configUniversite['ministere_tutelle'] ?? ''), 0, 1, 'C');
        
        $pdf->SetFont('helvetica', 'B', 14);
        $pdf->SetXY(50, 23);
        $pdf->Cell(0, 8, strtoupper($configUniversite['nom'] ?? ''), 0, 1, 'C');
        
        if (!empty($configUniversite['sigle'])) {
            $pdf->SetFont('helvetica', 'B', 12);
            $pdf->SetXY(50, 31);
            $pdf->Cell(0, 6, $configUniversite['sigle'], 0, 1, 'C');
        }
        
        $pdf->SetFont('helvetica', '', 9);
        $pdf->SetTextColor(80, 80, 80);
        if (!empty($configUniversite['adresse'])) {
            $pdf->SetXY(50, 37);
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
            $pdf->SetXY(50, 41);
            $pdf->Cell(0, 4, $contactInfo, 0, 1, 'C');
        }
        
        // Ligne de séparation
        $pdf->SetLineStyle(array('width' => 0.5, 'cap' => 'butt', 'join' => 'miter', 'dash' => 0, 'color' => $accentColor));
        $pdf->Line(15, 48, $pdf->getPageWidth() - 15, 48);
    }
    
    // Ajouter "Laboratoire de Recherche" en police calligraphique à gauche
    $pdf->SetFont('times', 'I', 12);
    $pdf->SetTextColor(100, 100, 100);
    $pdf->SetXY(15, 50);
    $pdf->Cell(100, 6, 'Laboratoire de Recherche', 0, 1, 'L');
    
    // Réinitialiser la couleur du texte pour la suite
    $pdf->SetTextColor(80, 80, 80);
    
    // Titre du document avec fond coloré
    $pdf->SetFillColor($primaryColor[0], $primaryColor[1], $primaryColor[2]);
    $pdf->SetTextColor(255, 255, 255);
    $pdf->SetFont('helvetica', 'B', 16);
    $pdf->Ln(5);
    $pdf->Cell(0, 10, 'QR CODE DE PRÉSENCE - SÉANCE DE LABORATOIRE', 0, 1, 'C', 1);
    
    $pdf->SetTextColor(80, 80, 80);
    $pdf->SetFont('helvetica', 'B', 10);
    $dateFormatee = date('d/m/Y', strtotime($seance['date_seance']));
    $pdf->Cell(0, 8, 'Séance du ' . $dateFormatee, 0, 1, 'C');
    
    // Informations du laboratoire avec style amélioré
    $pdf->SetTextColor($secondaryColor[0], $secondaryColor[1], $secondaryColor[2]);
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->Ln(5);
    $pdf->Cell(0, 10, 'INFORMATIONS DU LABORATOIRE', 0, 1, 'L');
    
    // Ligne décorative sous le titre de section
    $pdf->SetLineStyle(array('width' => 0.2, 'cap' => 'butt', 'join' => 'miter', 'dash' => 0, 'color' => $secondaryColor));
    $pdf->Line(20, $pdf->GetY(), 100, $pdf->GetY());
    $pdf->Ln(2);
    
    // Tableau d'informations
    $pdf->SetTextColor(60, 60, 60);
    $pdf->SetFont('helvetica', 'B', 11);
    
    // Style pour les cellules de tableau
    $pdf->SetFillColor(245, 245, 245);
    $pdf->SetDrawColor($accentColor[0], $accentColor[1], $accentColor[2]);
    
    $pdf->Cell(50, 8, 'Nom du laboratoire:', 1, 0, 'L', 1);
    $pdf->SetFont('helvetica', '', 11);
    $pdf->Cell(0, 8, $seance['nom_labo'], 1, 1, 'L', 0);
    
    $pdf->SetFont('helvetica', 'B', 11);
    $pdf->Cell(50, 8, 'Localisation:', 1, 0, 'L', 1);
    $pdf->SetFont('helvetica', '', 11);
    $pdf->Cell(0, 8, $seance['localisation'], 1, 1, 'L', 0);
    
    $pdf->SetFont('helvetica', 'B', 11);
    $pdf->Cell(50, 8, 'Responsable:', 1, 0, 'L', 1);
    $pdf->SetFont('helvetica', '', 11);
    $pdf->Cell(0, 8, $seance['responsable_nom'], 1, 1, 'L', 0);
    
    $pdf->Ln(5);
    
    // Détails de la séance
    $pdf->SetTextColor($secondaryColor[0], $secondaryColor[1], $secondaryColor[2]);
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->Cell(0, 10, 'DÉTAILS DE LA SÉANCE', 0, 1, 'L');
    
    // Ligne décorative sous le titre de section
    $pdf->SetLineStyle(array('width' => 0.2, 'cap' => 'butt', 'join' => 'miter', 'dash' => 0, 'color' => $secondaryColor));
    $pdf->Line(20, $pdf->GetY(), 100, $pdf->GetY());
    $pdf->Ln(2);
    
        // Tableau des détails de la séance
        $pdf->SetTextColor(60, 60, 60);
        $pdf->SetFont('helvetica', 'B', 11);
        
        $pdf->Cell(50, 8, 'Titre:', 1, 0, 'L', 1);
        $pdf->SetFont('helvetica', '', 11);
        $pdf->Cell(0, 8, $seance['titre'], 1, 1, 'L', 0);
        
        $pdf->SetFont('helvetica', 'B', 11);
        $pdf->Cell(50, 8, 'Date:', 1, 0, 'L', 1);
        $pdf->SetFont('helvetica', '', 11);
        $pdf->Cell(0, 8, date('d/m/Y', strtotime($seance['date_seance'])), 1, 1, 'L', 0);
        
        $pdf->SetFont('helvetica', 'B', 11);
        $pdf->Cell(50, 8, 'Horaire:', 1, 0, 'L', 1);
        $pdf->SetFont('helvetica', '', 11);
        $heureDebut = date('H:i', strtotime($seance['heure_debut']));
        $heureFin = date('H:i', strtotime($seance['heure_fin']));
        $pdf->Cell(0, 8, $heureDebut . ' - ' . $heureFin, 1, 1, 'L', 0);
        
        if (!empty($seance['description'])) {
            $pdf->SetFont('helvetica', 'B', 11);
            $pdf->Cell(50, 8, 'Description:', 1, 0, 'L', 1);
            $pdf->SetFont('helvetica', '', 11);
            
            // Utiliser MultiCell pour le texte long
            $xPos = $pdf->GetX();
            $yPos = $pdf->GetY();
            $width = $pdf->getPageWidth() - $xPos - 20; // Largeur de la cellule
            
            $pdf->MultiCell($width, 8, $seance['description'], 1, 'L', 0);
        }
        
        $pdf->Ln(10);
        
        // Section QR Code
        $pdf->SetTextColor($secondaryColor[0], $secondaryColor[1], $secondaryColor[2]);
        $pdf->SetFont('helvetica', 'B', 12);
        $pdf->Cell(0, 10, 'QR CODE POUR MARQUER VOTRE PRÉSENCE', 0, 1, 'C');
        
        // Créer le contenu du QR code
        $qrCodeData = json_encode([
            'id' => $seance['idseance_labo'],
            'type' => 'seance_labo',
            'titre' => $seance['titre'],
            'date' => $seance['date_seance'],
            'labo' => $seance['nom_labo'],
            'qrcode' => $seance['qrcode'] ?? ('SL_' . $seance['idseance_labo']),
            'timestamp' => time()
        ]);
        
        // Dessiner un cadre décoratif autour du QR code
        $qrSize = 60; // Taille du QR code
        $qrX = ($pdf->getPageWidth() - $qrSize) / 2; // Centrer horizontalement
        $qrY = $pdf->GetY() + 5;
        
        // Cadre avec ombre légère
        $pdf->SetFillColor(245, 245, 245);
        $pdf->RoundedRect($qrX - 5, $qrY - 5, $qrSize + 10, $qrSize + 10, 3, '1111', 'DF', 
                          array(), array(240, 240, 240));
        
        // Style pour le QR code
        $style = array(
            'border' => false,
            'padding' => 0,
            'fgcolor' => array($primaryColor[0], $primaryColor[1], $primaryColor[2]),
            'bgcolor' => array(255, 255, 255)
        );
        
        // Générer et placer le QR code au centre
        $pdf->write2DBarcode($qrCodeData, 'QRCODE,L', $qrX, $qrY, $qrSize, $qrSize, $style, 'N');
        
        // Déplacer le curseur après le QR code
        $pdf->SetY($qrY + $qrSize + 10);
        
        // Instructions pour les étudiants
        $pdf->SetTextColor(80, 80, 80);
        $pdf->SetFont('helvetica', '', 10);
        
        $instructions = "Scannez ce QR code avec l'application mobile de l'université pour marquer votre présence à cette séance de laboratoire.";
        $pdf->MultiCell(0, 6, $instructions, 0, 'C');
        
        // Informations de sécurité
        $pdf->Ln(5);
        $pdf->SetFont('helvetica', 'I', 8);
        $pdf->SetTextColor(100, 100, 100);
        
        $securityInfo = "Ce QR code est unique et ne peut être utilisé que pour cette séance spécifique. La présence est enregistrée avec l'heure exacte de scan.";
        $pdf->MultiCell(0, 4, $securityInfo, 0, 'C');
        
        // Ajouter une référence unique au bas du document
        $pdf->Ln(5);
        $pdf->SetFont('helvetica', 'B', 9);
        $pdf->SetTextColor(60, 60, 60);
        
        $reference = "Réf: LABO-" . sprintf('%04d', $seance['idseance_labo']) . "-" . date('Ymd', strtotime($seance['date_seance']));
        $pdf->Cell(0, 6, $reference, 0, 1, 'C');
        
        // Générer le PDF
        $filename = 'qrcode_labo_' . $idSeance . '.pdf';
        $pdf->Output($filename, 'I'); // 'I' pour afficher dans le navigateur
        
    } catch (Exception $e) {
        $_SESSION['swal_error'] = [
            'title' => 'Erreur',
            'text' => 'Impossible de générer le PDF: ' . $e->getMessage(),
            'icon' => 'error'
        ];
        header('Location: ../laboratoire/laboratoire.list');
        exit();
    }
    