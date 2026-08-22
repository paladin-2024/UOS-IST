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
        // Récupérer les informations de la séance
        $querySeance = "SELECT sl.*, l.nom as nom_labo, l.localisation, a.noms as responsable_nom
                        FROM seance_labo sl
                        JOIN laboratoire l ON sl.idlabo = l.idlabo
                        LEFT JOIN agent a ON l.responsable_id = a.idAgent
                        WHERE sl.idseance_labo = :idSeance";

        $stmtSeance = $db->prepare($querySeance);
        $stmtSeance->bindParam(':idSeance', $idSeance);
        $stmtSeance->execute();
        $seance = $stmtSeance->fetch(PDO::FETCH_ASSOC);
        
        if (!$seance) {
            throw new Exception("Séance de laboratoire non trouvée.");
        }
        
        // Récupérer la liste des présences pour cette séance
        $queryPresences = "SELECT pl.*, e.noms, e.matricule, e.photo
                          FROM presence_labo pl
                          JOIN etudiant_tempon e ON pl.idetudiant = e.idetudiant
                          WHERE pl.idseance_labo = :idSeance
                          ORDER BY pl.heure_arrivee ASC";

        $stmtPresences = $db->prepare($queryPresences);
        $stmtPresences->bindParam(':idSeance', $idSeance);
        $stmtPresences->execute();
        $presences = $stmtPresences->fetchAll(PDO::FETCH_ASSOC);
        
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
        $pdf->SetTitle('Liste de présence - Laboratoire');
        $pdf->SetSubject('Liste de présence pour la séance de laboratoire du ' . date('d/m/Y', strtotime($seance['date_seance'])));
        $pdf->SetKeywords('Présence, Laboratoire, Séance, Université');
        
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
        $pdf->Cell(0, 10, 'LISTE DE PRÉSENCE - LABORATOIRE', 0, 1, 'C', 1);
        
        // Sous-titre
        $pdf->SetTextColor(80, 80, 80);
        $pdf->SetFont('helvetica', 'B', 14);
        $pdf->Ln(5);
        $pdf->Cell(0, 10, htmlspecialchars($seance['nom_labo']), 0, 1, 'C');
        
        // Informations sur la séance
        $pdf->SetFont('helvetica', 'B', 11);
        $pdf->SetTextColor($secondaryColor[0], $secondaryColor[1], $secondaryColor[2]);
        $pdf->Cell(0, 8, 'Informations sur la séance', 0, 1, 'L');
        
        $pdf->SetFont('helvetica', '', 10);
        $pdf->SetTextColor(80, 80, 80);
        
        // Tableau d'informations
        $pdf->SetFillColor(245, 245, 245);
        $pdf->SetLineStyle(array('width' => 0.1, 'cap' => 'butt', 'join' => 'miter', 'dash' => 0, 'color' => array(180, 180, 180)));
        
        $pdf->Cell(40, 8, 'Titre:', 1, 0, 'L', 1);
        $pdf->Cell(130, 8, htmlspecialchars($seance['titre']), 1, 1, 'L');
        
        $pdf->Cell(40, 8, 'Laboratoire:', 1, 0, 'L', 1);
        $pdf->Cell(130, 8, htmlspecialchars($seance['nom_labo']), 1, 1, 'L');
        
        $pdf->Cell(40, 8, 'Localisation:', 1, 0, 'L', 1);
        $pdf->Cell(130, 8, htmlspecialchars($seance['localisation']), 1, 1, 'L');
        
        $pdf->Cell(40, 8, 'Responsable:', 1, 0, 'L', 1);
        $pdf->Cell(130, 8, htmlspecialchars($seance['responsable_nom']), 1, 1, 'L');
        
        $pdf->Cell(40, 8, 'Date:', 1, 0, 'L', 1);
        $pdf->Cell(130, 8, date('d/m/Y', strtotime($seance['date_seance'])), 1, 1, 'L');
        
        $pdf->Cell(40, 8, 'Horaire:', 1, 0, 'L', 1);
        $pdf->Cell(130, 8, substr($seance['heure_debut'], 0, 5) . ' - ' . substr($seance['heure_fin'], 0, 5), 1, 1, 'L');
        
        $pdf->Cell(40, 8, 'Total présences:', 1, 0, 'L', 1);
        $pdf->Cell(130, 8, count($presences) . ' étudiants', 1, 1, 'L');
        
        // Liste des étudiants présents
        $pdf->Ln(5);
        $pdf->SetFont('helvetica', 'B', 11);
        $pdf->SetTextColor($secondaryColor[0], $secondaryColor[1], $secondaryColor[2]);
        $pdf->Cell(0, 8, 'Liste des étudiants présents', 0, 1, 'L');
        
        // En-têtes du tableau
        $pdf->SetFont('helvetica', 'B', 10);
        $pdf->SetFillColor($primaryColor[0], $primaryColor[1], $primaryColor[2]);
        $pdf->SetTextColor(255, 255, 255);
        
        $pdf->Cell(10, 8, '#', 1, 0, 'C', 1);
        $pdf->Cell(30, 8, 'Matricule', 1, 0, 'C', 1);
        $pdf->Cell(80, 8, 'Nom & Prénom', 1, 0, 'C', 1);
        $pdf->Cell(30, 8, 'Heure d\'arrivée', 1, 0, 'C', 1);
        $pdf->Cell(20, 8, 'Méthode', 1, 1, 'C', 1);
        
        // Contenu du tableau
        $pdf->SetFont('helvetica', '', 9);
        $pdf->SetTextColor(80, 80, 80);
        $pdf->SetFillColor(245, 245, 245);
        
        $i = 1;
        $fill = false;
        
        foreach ($presences as $presence) {
            $heureArrivee = new DateTime($presence['heure_arrivee']);
            
            // Déterminer la méthode d'enregistrement
            
            
            $pdf->Cell(10, 7, $i++, 1, 0, 'C', $fill);
            $pdf->Cell(30, 7, $presence['matricule'], 1, 0, 'C', $fill);
            $pdf->Cell(80, 7, htmlspecialchars($presence['noms']), 1, 0, 'L', $fill);
            $pdf->Cell(30, 7, $heureArrivee->format('H:i:s'), 1, 0, 'C', $fill);
            $pdf->Cell(20, 7, $presence['methode_enregistrement'], 1, 1, 'C', $fill);
            
            $fill = !$fill; // Alterner les couleurs de fond
        }
        
        // Répartition horaire des arrivées
        $pdf->SetFont('helvetica', 'B', 11);
        $pdf->SetTextColor($secondaryColor[0], $secondaryColor[1], $secondaryColor[2]);
        $pdf->Cell(0, 8, 'Répartition horaire des arrivées', 0, 1, 'L');
        
        // Analyser les heures d'arrivée
        $heuresArrivee = [];
        $heureDebut = new DateTime($seance['heure_debut']);
        $heureFin = new DateTime($seance['heure_fin']);
        
        // Créer des tranches d'une demi-heure
        $interval = new DateInterval('PT30M'); // 30 minutes
        $currentHour = clone $heureDebut;
        
        while ($currentHour <= $heureFin) {
            $key = $currentHour->format('H:i');
            $heuresArrivee[$key] = 0;
            $currentHour->add($interval);
        }
        
        // Compter les arrivées par tranche
        foreach ($presences as $presence) {
            $heureArrivee = new DateTime($presence['heure_arrivee']);
            $heureArriveeStr = $heureArrivee->format('H:i');
            
            // Trouver la tranche correspondante
            foreach (array_keys($heuresArrivee) as $tranche) {
                $trancheTime = new DateTime($tranche);
                $nextTranche = clone $trancheTime;
                $nextTranche->add($interval);
                
                if ($heureArrivee >= $trancheTime && $heureArrivee < $nextTranche) {
                    $heuresArrivee[$tranche]++;
                    break;
                }
            }
        }
        
        // Afficher la répartition
        $pdf->SetFont('helvetica', 'B', 10);
        $pdf->SetFillColor($primaryColor[0], $primaryColor[1], $primaryColor[2]);
        $pdf->SetTextColor(255, 255, 255);
        
        $pdf->Cell(40, 8, 'Tranche horaire', 1, 0, 'C', 1);
        $pdf->Cell(30, 8, 'Nombre d\'arrivées', 1, 0, 'C', 1);
        $pdf->Cell(100, 8, 'Graphique', 1, 1, 'C', 1);
        
        $pdf->SetFont('helvetica', '', 9);
        $pdf->SetTextColor(80, 80, 80);
        $pdf->SetFillColor(245, 245, 245);
        
        $fill = false;
        $maxArrivees = max($heuresArrivee) > 0 ? max($heuresArrivee) : 1;
        
        foreach ($heuresArrivee as $tranche => $nombre) {
            $nextTranche = new DateTime($tranche);
            $nextTranche->add($interval);
            $trancheAffichee = $tranche . ' - ' . $nextTranche->format('H:i');
            
            $pdf->Cell(40, 7, $trancheAffichee, 1, 0, 'C', $fill);
            $pdf->Cell(30, 7, $nombre, 1, 0, 'C', $fill);
            
            // Dessiner un graphique à barres simple
            $barWidth = ($nombre / $maxArrivees) * 90; // Largeur maximale de 90mm
            
            $pdf->Cell(100, 7, '', 1, 0, 'L', $fill);
            if ($nombre > 0) {
                $pdf->SetFillColor(0, 121, 194); // Couleur de la barre
                $pdf->Rect($pdf->GetX() - 99, $pdf->GetY() + 1, $barWidth, 5, 'F');
                $pdf->SetFillColor(245, 245, 245); // Réinitialiser la couleur de remplissage
            }
            $pdf->Ln();
            
            $fill = !$fill;
        }
        
        $pdf->AddPage();
        $pdf->SetFont('helvetica', 'B', 11);
        $pdf->SetTextColor($secondaryColor[0], $secondaryColor[1], $secondaryColor[2]);
        $pdf->Cell(0, 8, 'Validation du document', 0, 1, 'L');
        
        $pdf->SetFont('helvetica', '', 10);
        $pdf->SetTextColor(80, 80, 80);
        
        // Créer des espaces pour les signatures
        $pdf->Cell(85, 8, 'Le responsable du laboratoire:', 0, 0, 'L');
        $pdf->Cell(85, 8, 'Le responsable académique:', 0, 1, 'L');
        
        $pdf->Cell(85, 20, '', 'B', 0, 'L'); // Ligne pour signature
        $pdf->Cell(85, 20, '', 'B', 1, 'L'); // Ligne pour signature
        
        $pdf->Cell(85, 8, htmlspecialchars($seance['responsable_nom']), 0, 0, 'L');
        $pdf->Cell(85, 8, '', 0, 1, 'L');
        
        $pdf->Ln(5);
        
       
        // Générer le PDF
        $filename = 'liste_presence_labo_' . $idSeance . '_' . date('Ymd') . '.pdf';
        $pdf->Output($filename, 'I');
        exit();
    }
    catch (Exception $e) {
        $_SESSION['swal_error'] = [
            'title' => 'Erreur',
            'text' => $e->getMessage(),
            'icon' => 'error'
        ];
        header('Location: ../laboratoire/presence.list&id=' . $idSeance);
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
