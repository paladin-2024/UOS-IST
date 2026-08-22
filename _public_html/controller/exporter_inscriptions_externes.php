<?php
session_start();
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/models/Universite.php';
require_once dirname(__DIR__) . '/vendor/autoload.php';

// Vérifier si l'utilisateur est connecté et est admin
if (!isset($_SESSION['id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Accès non autorisé']);
    exit;
}

// Vérifier si l'utilisateur est admin (idRole = 1)
$userRole = $_SESSION['idRole'] ?? 0;
if ($userRole != 1) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Accès réservé aux administrateurs']);
    exit;
}

try {
    $connexion = Connexion::getInstance()->getPDO();
    $universiteModel = new Universite();
    
    // Récupérer l'ID du lien si spécifié
    $lien_id = isset($_GET['lien_id']) ? intval($_GET['lien_id']) : null;
    
    // Construire la requête selon le filtre
    $where_clause = "";
    $params = [];
    
    if ($lien_id) {
        $where_clause = "WHERE ie.lien_inscription_id = ?";
        $params[] = $lien_id;
    }
    
    // Récupérer les inscriptions externes
    $stmt = $connexion->prepare("
        SELECT ie.*, 
               lie.titre as lien_titre,
               lie.reference as lien_reference,
               p.designationPromotion,
               o.designationOrientation,
               s.designationSection,
               aa.designation as annee_academique,
               COUNT(die.id) as nb_documents
        FROM inscriptions_externes ie
        LEFT JOIN liens_inscription_externe lie ON ie.lien_inscription_id = lie.id
        LEFT JOIN promotion p ON lie.promotion_id = p.idpromotion
        LEFT JOIN orientation o ON p.orientation_idorientation = o.idorientation
        LEFT JOIN section s ON o.section_idsection = s.idsection
        LEFT JOIN annee_acad aa ON lie.annee_acad_id = aa.idannee_acad
        LEFT JOIN documents_inscription_externe die ON ie.id = die.inscription_externe_id
        $where_clause
        GROUP BY ie.id
        ORDER BY ie.date_soumission DESC
    ");
    $stmt->execute($params);
    $inscriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($inscriptions)) {
        throw new Exception('Aucune inscription externe trouvée');
    }
    
    // Récupérer les informations de l'université
    $configUniversite = $universiteModel->getConfigurationUniversite();
    
    // Créer une instance de TCPDF en format paysage
    $pdf = new TCPDF('L', 'mm', 'A4', true, 'UTF-8', false);

    // Définir les informations du document
    $pdf->SetCreator('Système de gestion universitaire');
    $pdf->SetAuthor($configUniversite['nom'] ?? 'Administration');
    $pdf->SetTitle('Export Inscriptions Externes - ' . date('d/m/Y'));
    $pdf->SetSubject('Liste des inscriptions externes');
    $pdf->SetKeywords('Inscriptions, Externes, Export, Liste');

    // Supprimer les en-têtes et pieds de page par défaut
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);

    // Définir les marges
    $pdf->SetMargins(15, 20, 15);
    $pdf->SetAutoPageBreak(true, 20);

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
            $pdf->setAlpha(0.08);
            $pageWidth = $pdf->getPageWidth();
            $pageHeight = $pdf->getPageHeight();
            $logoWidth = 80;
            $logoHeight = 100;
            $x = ($pageWidth - $logoWidth) / 2;
            $y = ($pageHeight - $logoHeight) / 2;
            $pdf->Image($logoPath, $x, $y, $logoWidth, $logoHeight, '', '', '', false, 300, '', false, false, 0);
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
        $pdf->SetFont('helvetica', 'B', 12);
        $pdf->SetY(15);
        $pdf->Cell(0, 6, strtoupper($configUniversite['ministere_tutelle'] ?? ''), 0, 1, 'C');
        
        $pdf->SetFont('helvetica', 'B', 14);
        $pdf->Cell(0, 6, strtoupper($configUniversite['nom'] ?? ''), 0, 1, 'C');
        
        if (!empty($configUniversite['sigle'])) {
            $pdf->SetFont('helvetica', 'B', 12);
            $pdf->Cell(0, 5, $configUniversite['sigle'], 0, 1, 'C');
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
        
        // Ligne de séparation
        $pdf->SetLineStyle(array('width' => 0.5, 'cap' => 'butt', 'join' => 'miter', 'dash' => 0, 'color' => $accentColor));
        $pdf->Line(15, 45, $pdf->getPageWidth() - 15, 45);
    }

    // Titre du document avec fond coloré
    $pdf->SetFillColor($primaryColor[0], $primaryColor[1], $primaryColor[2]);
    $pdf->SetTextColor(255, 255, 255);
    $pdf->SetFont('helvetica', 'B', 16);
    $pdf->Ln(3);
    $pdf->Cell(0, 10, 'EXPORT DES INSCRIPTIONS EXTERNES', 0, 1, 'C', 1);

    // Informations sur l'export
    $pdf->SetTextColor(80, 80, 80);
    $pdf->SetFont('helvetica', 'B', 10);
    $exportInfo = 'Généré le ' . date('d/m/Y à H:i') . ' - Total: ' . count($inscriptions) . ' inscription(s)';
    if ($lien_id) {
        $exportInfo .= ' - Filtre: ' . ($inscriptions[0]['lien_reference'] ?? 'Lien spécifique');
    }
    $pdf->Cell(0, 6, $exportInfo, 0, 1, 'C');

    $pdf->Ln(5);

    // Définir les largeurs des colonnes pour le format paysage
    $pageWidth = $pdf->getPageWidth() - 30; // Largeur utilisable
    $colWidths = array(
        'num' => 15,        // #
        'reference' => 40,   // Référence
        'nom' => 55,        // Nom complet
        'contact' => 40,    // Email/Téléphone
        'naissance' => 25,  // Date naissance
        'formation' => 35,  // Formation
        'statut' => 20,     // Statut
        'date' => 25,       // Date soumission
        'docs' => 15        // Documents
    );

    // Fonction pour dessiner les en-têtes du tableau
    function drawTableHeaders($pdf, $colWidths, $secondaryColor, $accentColor) {
        $pdf->SetFillColor($secondaryColor[0], $secondaryColor[1], $secondaryColor[2]);
        $pdf->SetTextColor(255, 255, 255);
        $pdf->SetDrawColor($accentColor[0], $accentColor[1], $accentColor[2]);
        $pdf->SetFont('helvetica', 'B', 8);

        $pdf->Cell($colWidths['num'], 8, '#', 1, 0, 'C', 1);
        $pdf->Cell($colWidths['reference'], 8, 'Référence', 1, 0, 'C', 1);
        $pdf->Cell($colWidths['nom'], 8, 'Candidat', 1, 0, 'C', 1);
        $pdf->Cell($colWidths['contact'], 8, 'Contact', 1, 0, 'C', 1);
        $pdf->Cell($colWidths['naissance'], 8, 'Naissance', 1, 0, 'C', 1);
        $pdf->Cell($colWidths['formation'], 8, 'Formation', 1, 0, 'C', 1);
        $pdf->Cell($colWidths['statut'], 8, 'Statut', 1, 0, 'C', 1);
        $pdf->Cell($colWidths['date'], 8, 'Date soumission', 1, 0, 'C', 1);
        $pdf->Cell($colWidths['docs'], 8, 'Docs', 1, 1, 'C', 1);
    }

    // En-têtes du tableau
    drawTableHeaders($pdf, $colWidths, $secondaryColor, $accentColor);

    // Données du tableau avec MultiCell pour le retour à la ligne automatique
    $pdf->SetTextColor(60, 60, 60);
    $pdf->SetFont('helvetica', '', 7);
    
    $rowCount = 0;
    foreach ($inscriptions as $index => $inscription) {
        $rowCount++;
        
        // Vérifier si on a besoin d'une nouvelle page
        if ($pdf->GetY() > $pdf->getPageHeight() - 40) {
            $pdf->AddPage();
            drawTableHeaders($pdf, $colWidths, $secondaryColor, $accentColor);
            $pdf->SetTextColor(60, 60, 60);
            $pdf->SetFont('helvetica', '', 7);
        }
        
        // Préparer les données de la ligne
        $nomComplet = trim($inscription['prenom'] . ' ' . $inscription['nom']);
        $contact = $inscription['email'];
        $naissance = date('d/m/Y', strtotime($inscription['date_naissance']));
        $formation = $inscription['designationSection'] ?? 'N/A';
        $datesoumission = date('d/m/Y H:i', strtotime($inscription['date_soumission']));
        
        // Calculer la hauteur nécessaire pour chaque cellule avec contenu variable
        $lineHeight = 3; // Hauteur d'une ligne de texte
        $minHeight = 6;  // Hauteur minimale d'une cellule
        
        $heights = array();
        $heights['reference'] = max($minHeight, $pdf->getNumLines($inscription['reference_inscription'], $colWidths['reference']) * $lineHeight);
        $heights['nom'] = max($minHeight, $pdf->getNumLines($nomComplet, $colWidths['nom']) * $lineHeight);
        $heights['contact'] = max($minHeight, $pdf->getNumLines($contact, $colWidths['contact']) * $lineHeight);
        $heights['formation'] = max($minHeight, $pdf->getNumLines($formation, $colWidths['formation']) * $lineHeight);
        $heights['date'] = max($minHeight, $pdf->getNumLines($datesoumission, $colWidths['date']) * $lineHeight);
        
        // La hauteur de la ligne est la hauteur maximale
        $rowHeight = max($minHeight, max($heights));
        
        // Couleur de fond alternée
        $fillColor = ($index % 2 == 0) ? array(250, 250, 250) : array(255, 255, 255);
        $pdf->SetFillColor($fillColor[0], $fillColor[1], $fillColor[2]);
        
        // Sauvegarder la position de départ
        $startY = $pdf->GetY();
        $startX = $pdf->GetX();
        
        // Position X courante pour chaque colonne
        $currentX = $startX;
        
        // Numéro
        $pdf->SetXY($currentX, $startY);
        $pdf->Cell($colWidths['num'], $rowHeight, $rowCount, 1, 0, 'C', 1);
        $currentX += $colWidths['num'];
        
        // Référence
        $pdf->SetXY($currentX, $startY);
        $pdf->MultiCell($colWidths['reference'], $rowHeight, $inscription['reference_inscription'], 1, 'C', 1, 0);
        $currentX += $colWidths['reference'];
        
        // Nom complet
        $pdf->SetXY($currentX, $startY);
        $pdf->MultiCell($colWidths['nom'], $rowHeight, $nomComplet, 1, 'L', 1, 0);
        $currentX += $colWidths['nom'];
        
        // Contact
        $pdf->SetXY($currentX, $startY);
        $pdf->MultiCell($colWidths['contact'], $rowHeight, $contact, 1, 'L', 1, 0);
        $currentX += $colWidths['contact'];
        
        // Date de naissance
        $pdf->SetXY($currentX, $startY);
        $pdf->Cell($colWidths['naissance'], $rowHeight, $naissance, 1, 0, 'C', 1);
        $currentX += $colWidths['naissance'];
        
        // Formation
        $pdf->SetXY($currentX, $startY);
        $pdf->MultiCell($colWidths['formation'], $rowHeight, $formation, 1, 'L', 1, 0);
        $currentX += $colWidths['formation'];
        
        // Statut avec couleur
        $statutColor = array(60, 60, 60); // Gris par défaut
        switch ($inscription['statut']) {
            case 'Validée':
                $statutColor = array(40, 167, 69); // Vert
                break;
            case 'Rejetée':
                $statutColor = array(220, 53, 69); // Rouge
                break;
            case 'Complète':
                $statutColor = array(23, 162, 184); // Bleu
                break;
            case 'En cours':
                $statutColor = array(255, 193, 7); // Orange
                break;
        }
        
        $pdf->SetXY($currentX, $startY);
        $pdf->SetTextColor($statutColor[0], $statutColor[1], $statutColor[2]);
        $pdf->SetFont('helvetica', 'B', 7);
        $pdf->Cell($colWidths['statut'], $rowHeight, $inscription['statut'], 1, 0, 'C', 1);
        $currentX += $colWidths['statut'];
        
        // Remettre la couleur et police normales
        $pdf->SetTextColor(60, 60, 60);
        $pdf->SetFont('helvetica', '', 7);
        
        // Date de soumission
        $pdf->SetXY($currentX, $startY);
        $pdf->MultiCell($colWidths['date'], $rowHeight, $datesoumission, 1, 'C', 1, 0);
        $currentX += $colWidths['date'];
        
        // Nombre de documents
        $pdf->SetXY($currentX, $startY);
        $pdf->Cell($colWidths['docs'], $rowHeight, $inscription['nb_documents'], 1, 0, 'C', 1);
        
        // Passer à la ligne suivante
        $pdf->SetY($startY + $rowHeight);
    }

    // Statistiques en bas de page
    $pdf->Ln(5);
    
    // Calculer les statistiques
    $stats = array(
        'total' => count($inscriptions),
        'en_cours' => 0,
        'complete' => 0,
        'validee' => 0,
        'rejetee' => 0
    );
    
    foreach ($inscriptions as $inscription) {
        switch ($inscription['statut']) {
            case 'En cours':
                $stats['en_cours']++;
                break;
            case 'Complète':
                $stats['complete']++;
                break;
            case 'Validée':
                $stats['validee']++;
                break;
            case 'Rejetée':
                $stats['rejetee']++;
                break;
        }
    }
    
    // Afficher les statistiques
    $pdf->SetTextColor($secondaryColor[0], $secondaryColor[1], $secondaryColor[2]);
    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->Cell(0, 8, 'STATISTIQUES', 0, 1, 'L');
    
    $pdf->SetLineStyle(array('width' => 0.2, 'cap' => 'butt', 'join' => 'miter', 'dash' => 0, 'color' => $secondaryColor));
    $pdf->Line(15, $pdf->GetY(), 80, $pdf->GetY());
    $pdf->Ln(2);
    
    $pdf->SetTextColor(60, 60, 60);
    $pdf->SetFont('helvetica', '', 9);
    
    $statWidth = 60;
    $pdf->Cell($statWidth, 5, 'Total des inscriptions: ' . $stats['total'], 0, 0, 'L');
    $pdf->Cell($statWidth, 5, 'En cours: ' . $stats['en_cours'], 0, 0, 'L');
    $pdf->Cell($statWidth, 5, 'Complètes: ' . $stats['complete'], 0, 1, 'L');
    
    $pdf->Cell($statWidth, 5, 'Validées: ' . $stats['validee'], 0, 0, 'L');
    $pdf->Cell($statWidth, 5, 'Rejetées: ' . $stats['rejetee'], 0, 1, 'L');

    // Générer un QR code pour la vérification
    $qrCodeData = "EXPORT INSCRIPTIONS EXTERNES\n";
    $qrCodeData .= "Date export: " . date('d/m/Y H:i:s') . "\n";
    $qrCodeData .= "Total inscriptions: " . count($inscriptions) . "\n";
    $qrCodeData .= "Validees: " . $stats['validee'] . "\n";
    $qrCodeData .= "Rejetees: " . $stats['rejetee'] . "\n";
    $qrCodeData .= "En cours: " . $stats['en_cours'] . "\n";
    if ($lien_id) {
        $qrCodeData .= "Filtre lien: " . ($inscriptions[0]['lien_reference'] ?? 'ID ' . $lien_id) . "\n";
    }
    $qrCodeData .= $configUniversite['site_web'] ?? '';

    // Style pour le QR code
    $qrStyle = array(
        'border' => false,
        'padding' => 2,
        'fgcolor' => array($primaryColor[0], $primaryColor[1], $primaryColor[2]),
        'bgcolor' => array(255, 255, 255),
        'module_width' => 1,
        'module_height' => 1
    );

    // Position du QR code
    $pageWidth = $pdf->getPageWidth();
    $pageHeight = $pdf->getPageHeight();
    $bottomMargin = 20;
    $rightMargin = 15;
    $qrSize = 20;
    
    $qrX = $pageWidth - $rightMargin - $qrSize;
    $qrY = $pageHeight - $bottomMargin - $qrSize - 6;

    // Dessiner un cadre décoratif autour du QR code
    $pdf->RoundedRect($qrX - 2, $qrY - 2, $qrSize + 4, $qrSize + 4, 2, '1111', 'DF', array(), array(245, 245, 245));

    // Placer le QR code
    $pdf->write2DBarcode($qrCodeData, 'QRCODE,L', $qrX, $qrY, $qrSize, $qrSize, $qrStyle, 'N');

    // Ajouter un petit texte sous le QR code
    $pdf->SetFont('helvetica', 'I', 6);
    $pdf->SetTextColor(100, 100, 100);
    $pdf->Text($qrX, $qrY + $qrSize + 2, 'Code verification');

    // Informations de génération du document
    $pdf->SetFont('helvetica', '', 7);
    $pdf->SetTextColor(120, 120, 120);
    $pdf->SetY($pageHeight - $bottomMargin - 8);
    $pdf->Cell(0, 3, 'Document généré automatiquement le ' . date('d/m/Y à H:i:s'), 0, 1, 'L');
    $pdf->Cell(0, 3, 'Référence: EIE-' . date('YmdHis') . ($lien_id ? '-L' . $lien_id : ''), 0, 1, 'L');

    // Nettoyer la sortie et envoyer le PDF
    ob_clean();
    
    // Nom du fichier
    $fileName = 'Export_Inscriptions_Externes_' . date('Y-m-d_H-i') . ($lien_id ? '_Lien_' . $lien_id : '') . '.pdf';
    
    // Sortie du PDF
    $pdf->Output($fileName, 'I'); // 'D' force le téléchargement

} catch (Exception $e) {
    // Gestion des erreurs
    error_log("Erreur export inscriptions externes: " . $e->getMessage());
    
    echo "<script>
        alert('Erreur lors de l\\'export : " . addslashes($e->getMessage()) . "');
        window.history.back();
    </script>";
}
?>