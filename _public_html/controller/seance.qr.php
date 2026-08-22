<?php
session_start();
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/models/Universite.php';
require_once dirname(__DIR__) . '/vendor/autoload.php'; // Pour TCPDF

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['id'])) {
    header('Location: ../login');
    exit();
}

// Initialiser la connexion et les modèles
$db = Connexion::getInstance()->getPDO();
$universiteModel = new Universite();

if (isset($_GET['id']) && intval($_GET['id']) > 0) {
    $idSeance = intval($_GET['id']);
    
    try {
        // Récupérer les détails de la séance
        $stmt = $db->prepare("
            SELECT sl.*, l.nom as nom_laboratoire, l.localisation, a.noms as nom_responsable 
            FROM seance_labo sl
            JOIN laboratoire l ON sl.idlabo = l.idlabo
            LEFT JOIN autorisation_labo al ON l.idlabo = al.idlabo AND al.niveau_autorisation = 'Admin'
            LEFT JOIN agent a ON al.\"idAgent\" = a.\"idAgent\"
            WHERE sl.idseance_labo = :idSeance
        ");
        $stmt->bindParam(':idSeance', $idSeance, PDO::PARAM_INT);
        $stmt->execute();
        $seance = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$seance) {
            throw new Exception("Séance de laboratoire non trouvée.");
        }
        
        // Récupérer les informations de l'université
        $configUniversite = $universiteModel->getConfigurationUniversite();
        
        // Créer une classe personnalisée héritant de TCPDF pour personnaliser le pied de page
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
        $pdf->SetTitle('QR Code - Présence Laboratoire');
        $pdf->SetSubject('QR Code pour enregistrement de présence');
        $pdf->SetKeywords('QR Code, Présence, Laboratoire');
        
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
                
                $logoWidth = 70;
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
        
        // Titre du document avec fond coloré
        $pdf->SetFillColor($primaryColor[0], $primaryColor[1], $primaryColor[2]);
        $pdf->SetTextColor(255, 255, 255);
        $pdf->SetFont('helvetica', 'B', 16);
        $pdf->Ln(10);
        $pdf->Cell(0, 10, 'FICHE DE PRÉSENCE - LABORATOIRE', 0, 1, 'C', 1);
        
        // Sous-titre
        $pdf->SetTextColor(80, 80, 80);
        $pdf->SetFont('helvetica', 'B', 14);
        $pdf->Ln(5);
        $pdf->Cell(0, 10, htmlspecialchars($seance['titre']), 0, 1, 'C');
        
        
        // Section QR Code
        $pdf->SetTextColor($secondaryColor[0], $secondaryColor[1], $secondaryColor[2]);
        $pdf->SetFont('helvetica', 'B', 12);
        $pdf->Ln(10);
        $pdf->Cell(0, 10, 'CODE DE PRÉSENCE', 0, 1, 'C');
        
        // Créer les données du QR Code
        $qrCodeData = "";
        
                // Créer les données du QR Code
                $qrCodeData = "";
        
                // Si un QR code existe déjà dans la base de données, l'utiliser
                if (!empty($seance['qrcode'])) {
                    $qrCodeData = $seance['qrcode'];
                } else {
                    // Sinon, créer un nouveau QR code et le mettre à jour dans la base
                    $qrCodeData = 'LABO_SEANCE_' . $idSeance . '_' . date('YmdHis');
                    
                    // Mettre à jour la séance avec le nouveau code QR
                    $updateStmt = $db->prepare("UPDATE seance_labo SET qrcode = :qrcode WHERE idseance_labo = :idSeance");
                    $updateStmt->bindParam(':qrcode', $qrCodeData, PDO::PARAM_STR);
                    $updateStmt->bindParam(':idSeance', $idSeance, PDO::PARAM_INT);
                    $updateStmt->execute();
                }
                
                // Ajouter des informations pour que le système reconnaisse le QR code
                $qrCodePayload = json_encode([
                    'type' => 'presence_labo',
                    'seance_id' => $idSeance,
                    'labo_id' => $seance['idlabo'],
                    'date' => $seance['date_seance'],
                    'code' => $qrCodeData,
                    'timestamp' => time(),
                    // Ajoutez ces propriétés manquantes
                    'titre' => $seance['titre'],
                    'heure_debut' => $seance['heure_debut'],
                    'heure_fin' => $seance['heure_fin'],
                    'labo_nom' => $seance['nom_laboratoire']
                ]);

                
                // Centrer le QR code sur la page
                $pdf->Ln(5);
                
                // Style pour le QR code
                $style = array(
                    'border' => 2,
                    'vpadding' => 'auto',
                    'hpadding' => 'auto',
                    'fgcolor' => array(0, 0, 0),
                    'bgcolor' => array(255, 255, 255),
                    'module_width' => 1,
                    'module_height' => 1
                );
                
                // Calculer les coordonnées pour centrer le QR code
                $qrSize = 60; // taille du QR code en mm
                $xPos = ($pdf->getPageWidth() - $qrSize) / 2;
                
                // Générer le QR code
                $pdf->write2DBarcode($qrCodePayload, 'QRCODE,L', $xPos, $pdf->GetY(), $qrSize, $qrSize, $style, 'N');
                
                // Ajouter des instructions sous le QR code
                
                $pdf->SetTextColor(80, 80, 80);
                $pdf->SetFont('helvetica', 'B', 12);
                $pdf->Cell(0, 10, 'Instructions', 0, 1, 'C');
                
                $pdf->SetFont('helvetica', '', 11);
                $instructions = "1. Scannez ce QR code avec l'application de l'université.\n";
                $instructions .= "2. Votre présence sera automatiquement enregistrée.\n";
                $instructions .= "3. Ce QR code est valide uniquement pour la séance indiquée ci-dessus.";
                
                $pdf->MultiCell(0, 8, $instructions, 0, 'C', 0);
                
                // Ajouter une note de validité
                $pdf->Ln(5);
                $pdf->SetFont('helvetica', 'I', 10);
                $pdf->Cell(0, 8, 'Ce code est valide le ' . date('d/m/Y', strtotime($seance['date_seance'])) . 
                               ' de ' . substr($seance['heure_debut'], 0, 5) . ' à ' . substr($seance['heure_fin'], 0, 5), 0, 1, 'C');
                
                // Ajouter une note de sécurité
                $pdf->SetFont('helvetica', 'I', 9);
                $pdf->SetTextColor(150, 150, 150);
                $pdf->Cell(0, 6, 'Document généré par le système de gestion universitaire. Ne pas partager ce QR code.', 0, 1, 'C');
                
                // Ajouter des informations de contact en cas de problème
                if (!empty($configUniversite['email']) || !empty($configUniversite['telephone'])) {
                    $contactText = 'En cas de problème, contactez le support technique: ';
                    if (!empty($configUniversite['email'])) {
                        $contactText .= $configUniversite['email'];
                    }
                    if (!empty($configUniversite['telephone'])) {
                        if (!empty($configUniversite['email'])) {
                            $contactText .= ' ou ';
                        }
                        $contactText .= $configUniversite['telephone'];
                    }
                    $pdf->Cell(0, 6, $contactText, 0, 1, 'C');
                }
                
                // Générer le PDF
                $pdf->Output('presence_laboratoire_' . $idSeance . '.pdf', 'I');
                exit();
            }
            catch (Exception $e) {
                $_SESSION['swal_error'] = [
                    'title' => 'Erreur',
                    'text' => $e->getMessage(),
                    'icon' => 'error'
                ];
                header('Location: ../laboratoire/seance.list&id=' . ($seance['idlabo'] ?? ''));
                exit();
            }
        } else {
            $_SESSION['swal_error'] = [
                'title' => 'Erreur',
                'text' => 'Identifiant de séance invalide.',
                'icon' => 'error'
            ];
            header('Location: ../laboratoire/laboratoire.list');
            exit();
        }
        