<?php
session_start();
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/models/Conge.php';
require_once dirname(__DIR__) . '/models/Agent.php';
require_once dirname(__DIR__) . '/models/Service.php';
require_once dirname(__DIR__) . '/models/Structure.php';
require_once dirname(__DIR__) . '/models/Universite.php';
require_once dirname(__DIR__) . '/vendor/autoload.php'; // Pour TCPDF

// Vérifier si l'utilisateur est connecté et a les droits d'administrateur
if (!isset($_SESSION['id'])) {
    header('Location: ../index');
    exit();
}

// Initialiser les modèles
$congeModel = new Conge();
$agentModel = new Agent();
$serviceModel = new Service();
$structureModel = new Structure();
$universiteModel = new Universite();

if (isset($_GET['id']) && intval($_GET['id']) > 0) {
    $idDemande = intval($_GET['id']);
    
    // Récupérer les détails de la demande
    $demande = $congeModel->getDemandeCongeById($idDemande);
    
    if (!$demande || $demande['statut'] != 'Approuvé') {
        $_SESSION['swal_error'] = [
            'title' => 'Erreur',
            'text' => 'Impossible de générer une attestation pour cette demande.',
            'icon' => 'error'
        ];
        header('Location: ../grh/conges.list');
        exit();
    }
    
    // Récupérer les informations de l'agent
    $agent = $agentModel->getAgentById($demande['idAgent']);
    
    // Récupérer les informations du service et de la structure
    $service = $serviceModel->getServiceById($agent['idService']);
    $structure = $structureModel->getStructureById($agent['idStructure']);
    
    // Récupérer les informations de l'université
    $configUniversite = $universiteModel->getConfigurationUniversite();
    
    // Calculer la durée en jours ouvrables
    $dateDebut = new DateTime($demande['date_debut']);
    $dateFin = new DateTime($demande['date_fin']);
    $joursOuvrables = 0;
    for ($d = clone $dateDebut; $d <= $dateFin; $d->modify('+1 day')) {
        if ($d->format('N') < 6) { // 1 (lundi) à 5 (vendredi) sont des jours ouvrables
            $joursOuvrables++;
        }
    }
    
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
    $pdf->SetTitle('Attestation de congé');
    $pdf->SetSubject('Attestation de congé');
    $pdf->SetKeywords('Congé, Attestation');
    
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
            $logoWidth = 70; // Largeur réduite (était 100)
            $logoHeight = 100; // Hauteur inchangée
            
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

    // Ajouter "Secrétariat Général Académique" en police calligraphique à gauche
    $pdf->SetFont('times', 'I', 12); // Police Times en italique qui donne un aspect calligraphique
    $pdf->SetTextColor(100, 100, 100); // Gris foncé pour un aspect officiel mais discret
    $pdf->SetXY(15, 50);
    $pdf->Cell(100, 6, 'Secrétariat Général Administratif', 0, 1, 'L');

    // Réinitialiser la couleur du texte pour la suite
    $pdf->SetTextColor(80, 80, 80);

    // Titre du document avec fond coloré
    $pdf->SetFillColor($primaryColor[0], $primaryColor[1], $primaryColor[2]);
    $pdf->SetTextColor(255, 255, 255);
    $pdf->SetFont('helvetica', 'B', 16);
    $pdf->Ln(5);
    $pdf->Cell(0, 10, 'ATTESTATION DE CONGÉ', 0, 1, 'C', 1);
    
    $pdf->SetTextColor(80, 80, 80);
    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->Cell(0, 8, 'N° ' . sprintf('%04d', $idDemande) . '/' . date('Y'), 0, 1, 'C');
    
    // Ajouter un QR Code avec les informations du congé
    $qrCodeData = "ATTESTATION DE CONGÉ\n";
    $qrCodeData .= "ID: " . $idDemande . "\n";
    $qrCodeData .= "Agent: " . $agent['noms'] . "\n";
    $qrCodeData .= "Matricule: " . $agent['matricule'] . "\n";
    $qrCodeData .= "Type: " . $demande['type_conge_nom'] . "\n";
    $qrCodeData .= "Période: " . date('d/m/Y', strtotime($demande['date_debut'])) . " au " . date('d/m/Y', strtotime($demande['date_fin'])) . "\n";
    $qrCodeData .= "Document généré le: " . date('d/m/Y H:i:s') . "\n";
    $qrCodeData .= $configUniversite['site_web'] ?? '';
    
    
    $pdf->Ln(-5);
    
    // Informations de l'agent avec style amélioré
    $pdf->SetTextColor($secondaryColor[0], $secondaryColor[1], $secondaryColor[2]);
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->Cell(0, 10, 'INFORMATIONS DE L\'AGENT', 0, 1, 'L');
    
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
    
    $pdf->Cell(50, 8, 'Nom et prénom:', 1, 0, 'L', 1);
    $pdf->SetFont('helvetica', '', 11);
    $pdf->Cell(0, 8, $agent['noms'], 1, 1, 'L', 0);
    
    $pdf->SetFont('helvetica', 'B', 11);
    $pdf->Cell(50, 8, 'Matricule:', 1, 0, 'L', 1);
    $pdf->SetFont('helvetica', '', 11);
    $pdf->Cell(0, 8, $agent['matricule'], 1, 1, 'L', 0);
    
    $pdf->SetFont('helvetica', 'B', 11);
    $pdf->Cell(50, 8, 'Service:', 1, 0, 'L', 1);
    $pdf->SetFont('helvetica', '', 11);
    $pdf->Cell(0, 8, $service['designation'] ?? 'Non défini', 1, 1, 'L', 0);
    
    $pdf->SetFont('helvetica', 'B', 11);
    $pdf->Cell(50, 8, 'Campus:', 1, 0, 'L', 1);
    $pdf->SetFont('helvetica', '', 11);
    $pdf->Cell(0, 8, $structure['designation'] ?? 'Non définie', 1, 1, 'L', 0);
    
    $pdf->Ln(5);
    
    // Détails du congé
    $pdf->SetTextColor($secondaryColor[0], $secondaryColor[1], $secondaryColor[2]);
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->Cell(0, 10, 'DÉTAILS DU CONGÉ', 0, 1, 'L');
    
        // Ligne décorative sous le titre de section
        $pdf->SetLineStyle(array('width' => 0.2, 'cap' => 'butt', 'join' => 'miter', 'dash' => 0, 'color' => $secondaryColor));
        $pdf->Line(20, $pdf->GetY(), 100, $pdf->GetY());
        $pdf->Ln(2);
        
        // Tableau des détails du congé
        $pdf->SetTextColor(60, 60, 60);
        $pdf->SetFont('helvetica', 'B', 11);
        
        $pdf->Cell(50, 8, 'Type de congé:', 1, 0, 'L', 1);
        $pdf->SetFont('helvetica', '', 11);
        $pdf->Cell(0, 8, $demande['type_conge_nom'], 1, 1, 'L', 0);
        
        $pdf->SetFont('helvetica', 'B', 11);
        $pdf->Cell(50, 8, 'Date de début:', 1, 0, 'L', 1);
        $pdf->SetFont('helvetica', '', 11);
        $pdf->Cell(0, 8, date('d/m/Y', strtotime($demande['date_debut'])), 1, 1, 'L', 0);
        
        $pdf->SetFont('helvetica', 'B', 11);
        $pdf->Cell(50, 8, 'Date de fin:', 1, 0, 'L', 1);
        $pdf->SetFont('helvetica', '', 11);
        $pdf->Cell(0, 8, date('d/m/Y', strtotime($demande['date_fin'])), 1, 1, 'L', 0);
        
        $pdf->SetFont('helvetica', 'B', 11);
        $pdf->Cell(50, 8, 'Durée:', 1, 0, 'L', 1);
        $pdf->SetFont('helvetica', '', 11);
        $pdf->Cell(0, 8, $joursOuvrables . ' jours ouvrables', 1, 1, 'L', 0);
        
        if (!empty($demande['motif'])) {
            $pdf->SetFont('helvetica', 'B', 11);
            $pdf->Cell(50, 8, 'Motif:', 1, 0, 'L', 1);
            $pdf->SetFont('helvetica', '', 11);
            
            // Utiliser MultiCell pour le texte long
            $xPos = $pdf->GetX();
            $yPos = $pdf->GetY();
            $width = $pdf->getPageWidth() - $xPos - 20; // Largeur de la cellule
            
            $pdf->MultiCell($width, 8, $demande['motif'], 1, 'L', 0);
        }
        
        $pdf->Ln(5);
        
        // Approbation
        $pdf->SetTextColor($secondaryColor[0], $secondaryColor[1], $secondaryColor[2]);
        $pdf->SetFont('helvetica', 'B', 12);
        $pdf->Cell(0, 10, 'APPROBATION', 0, 1, 'L');
        
        // Ligne décorative sous le titre de section
        $pdf->SetLineStyle(array('width' => 0.2, 'cap' => 'butt', 'join' => 'miter', 'dash' => 0, 'color' => $secondaryColor));
        $pdf->Line(20, $pdf->GetY(), 100, $pdf->GetY());
        $pdf->Ln(2);
        
        // Tableau des informations d'approbation
        $pdf->SetTextColor(60, 60, 60);
        $pdf->SetFont('helvetica', 'B', 11);
        
        $pdf->Cell(50, 8, 'Date d\'approbation:', 1, 0, 'L', 1);
        $pdf->SetFont('helvetica', '', 11);
        $pdf->Cell(0, 8, date('d/m/Y', strtotime($demande['date_decision'])), 1, 1, 'L', 0);
        
        if (!empty($demande['commentaire_decision'])) {
            $pdf->SetFont('helvetica', 'B', 11);
            $pdf->Cell(50, 8, 'Commentaire:', 1, 0, 'L', 1);
            $pdf->SetFont('helvetica', '', 11);
            
            // Utiliser MultiCell pour le texte long
            $xPos = $pdf->GetX();
            $yPos = $pdf->GetY();
            $width = $pdf->getPageWidth() - $xPos - 20; // Largeur de la cellule
            
            $pdf->MultiCell($width, 8, $demande['commentaire_decision'], 1, 'L', 0);
        }
        
        // Section de signature avec style amélioré
        $pdf->Ln(5);

        // Obtenir la position Y actuelle pour aligner les éléments
        $currentY = $pdf->GetY();

        // Style amélioré pour le QR code
        $qrStyle = array(
            'border' => false,
            'padding' => 2,
            'fgcolor' => array($primaryColor[0], $primaryColor[1], $primaryColor[2]), // Couleur du QR code (bleu foncé)
            'bgcolor' => array(255, 255, 255), // Fond blanc
            'module_width' => 1, // Largeur des modules du QR code
            'module_height' => 1 // Hauteur des modules du QR code
        );

        // Dessiner un cadre décoratif autour du QR code
        $qrX = 20;
        $qrY = $currentY;
        $qrSize = 25;
        $pdf->RoundedRect($qrX - 2, $qrY - 2, $qrSize + 4, $qrSize + 4, 2, '1111', 'DF', array(), array(245, 245, 245));

        // Placer le QR code à gauche avec style amélioré
        $pdf->write2DBarcode($qrCodeData, 'QRCODE,L', $qrX, $qrY, $qrSize, $qrSize, $qrStyle, 'N');

        // Ajouter un petit texte sous le QR code
        $pdf->SetFont('helvetica', 'I', 7);
        $pdf->SetTextColor(100, 100, 100);
        $pdf->SetXY($qrX, $qrY + $qrSize + 2);
        $pdf->Cell($qrSize, 4, 'Scan pour vérifier', 0, 0, 'C');

        // Texte de signature à droite (sur la même ligne que le QR code)
        $pdf->SetTextColor(60, 60, 60);
        $pdf->SetFont('helvetica', 'I', 10);
        $pdf->SetXY(120, $currentY);
        $pdf->Cell(70, 6, 'Fait à ' . ($configUniversite['ville'] ?? '________________'), 0, 1, 'C');
        $pdf->SetXY(120, $pdf->GetY());
        $pdf->Cell(70, 6, 'Le ' . date('d/m/Y'), 0, 1, 'C');

        $pdf->SetXY(120, $pdf->GetY() + 5);
        $pdf->SetFont('helvetica', 'B', 10);
        $pdf->Cell(70, 6, 'Le Responsable des Ressources Humaines', 0, 1, 'C');

        // Générer le PDF
        $pdf->Output('attestation_conge_' . $idDemande . '.pdf', 'I');
        exit();
    } else {
        $_SESSION['swal_error'] = [
            'title' => 'Erreur',
            'text' => 'Identifiant de demande invalide.',
            'icon' => 'error'
        ];
        header('Location: ../grh/conges.list');
        exit();
    }
    
