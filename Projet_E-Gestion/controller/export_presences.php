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
            SELECT sc.*, ec.designationECUE, p.designationPromotion, s.numeroSemestre,
                (SELECT COUNT(*) FROM presence_cours WHERE idseance = sc.idseance) as nb_presents,
                e.noms as nom_enseignant
            FROM seance_cours sc
            JOIN ecue ec ON sc.idECUE = ec.idECUE
            JOIN ue ON ec.UE_idUE = ue.idUE
            JOIN semestre s ON ue.semestre_idsemestre = s.idsemestre
            JOIN promotion p ON s.promotion_idpromotion = p.idpromotion
            JOIN enseignant_ecue ee ON ec.idECUE = ee.idECUE AND ee.anneeAcad = sc.annee_acad_id
            JOIN agent e ON ee.idAgent = e.idAgent
            WHERE sc.idseance = :idSeance
        ");
        $stmt->bindParam(':idSeance', $idSeance, PDO::PARAM_INT);
        $stmt->execute();
        $seance = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$seance) {
            throw new Exception("Séance de cours non trouvée.");
        }
        
        // Récupérer les étudiants présents
        $stmtPresents = $db->prepare("
            SELECT p.*, e.matricule, e.noms, p.statut, p.commentaire, p.methode_enregistrement, p.heure_arrivee
            FROM presence_cours p
            JOIN etudiant e ON p.idetudiant = e.idetudiant
            WHERE p.idseance = :idSeance
            ORDER BY p.heure_arrivee
        ");
        $stmtPresents->bindParam(':idSeance', $idSeance, PDO::PARAM_INT);
        $stmtPresents->execute();
        $presents = $stmtPresents->fetchAll(PDO::FETCH_ASSOC);
        
        // Récupérer tous les étudiants de la promotion
        $stmtAllEtudiants = $db->prepare("
            SELECT e.idetudiant, e.matricule, e.noms
            FROM etudiant e
            JOIN promotion p ON e.promotion_idpromotion = p.idpromotion
            JOIN semestre s ON s.promotion_idpromotion = p.idpromotion
            JOIN ue ON ue.semestre_idsemestre = s.idsemestre
            JOIN ecue ec ON ec.UE_idUE = ue.idUE
            WHERE ec.idECUE = :idECUE
            ORDER BY e.noms
        ");
        $stmtAllEtudiants->bindParam(':idECUE', $seance['idECUE'], PDO::PARAM_INT);
        $stmtAllEtudiants->execute();
        $allEtudiants = $stmtAllEtudiants->fetchAll(PDO::FETCH_ASSOC);
        
        // Créer un tableau d'étudiants présents pour faciliter la vérification
        $etudiantsPresents = [];
        foreach ($presents as $present) {
            $etudiantsPresents[$present['idetudiant']] = $present;
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
        $pdf->SetTitle('Liste de présence - ' . $seance['designationECUE']);
        $pdf->SetSubject('Liste de présence pour la séance du ' . date('d/m/Y', strtotime($seance['date_seance'])));
        $pdf->SetKeywords('Présence, Cours, Séance, Université');
        
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
        $pdf->Cell(0, 10, 'LISTE DE PRÉSENCE - COURS', 0, 1, 'C', 1);
        
        
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
        
        $pdf->Cell(40, 8, 'Cours:', 1, 0, 'L', 1);
        $pdf->Cell(130, 8, htmlspecialchars($seance['designationECUE']), 1, 1, 'L');
        
        $pdf->Cell(40, 8, 'Enseignant:', 1, 0, 'L', 1);
        $pdf->Cell(130, 8, htmlspecialchars($seance['nom_enseignant']), 1, 1, 'L');
        
        $pdf->Cell(40, 8, 'Promotion:', 1, 0, 'L', 1);
        $pdf->Cell(130, 8, htmlspecialchars($seance['designationPromotion']) . ' - Semestre ' . $seance['numeroSemestre'], 1, 1, 'L');
        
        $pdf->Cell(40, 8, 'Date:', 1, 0, 'L', 1);
        $pdf->Cell(130, 8, date('d/m/Y', strtotime($seance['date_seance'])), 1, 1, 'L');
        
        $pdf->Cell(40, 8, 'Horaire:', 1, 0, 'L', 1);
        $pdf->Cell(130, 8, substr($seance['heure_debut'], 0, 5) . ' - ' . substr($seance['heure_fin'], 0, 5), 1, 1, 'L');
        
        $pdf->Cell(40, 8, 'Salle:', 1, 0, 'L', 1);
        $pdf->Cell(130, 8, htmlspecialchars($seance['salle']), 1, 1, 'L');
        
        $pdf->Cell(40, 8, 'Présents:', 1, 0, 'L', 1);
        $pdf->Cell(130, 8, count($presents) . ' / ' . count($allEtudiants) . ' étudiants', 1, 1, 'L');
        
        // Statistiques de présence
        $pdf->Ln(5);
        $pdf->SetFont('helvetica', 'B', 11);
        $pdf->SetTextColor($secondaryColor[0], $secondaryColor[1], $secondaryColor[2]);
        $pdf->Cell(0, 8, 'Statistiques de présence', 0, 1, 'L');
        
        // Calculer les statistiques
        $nbPresents = count($presents);
        $nbAbsents = count($allEtudiants) - $nbPresents;
        $tauxPresence = count($allEtudiants) > 0 ? round(($nbPresents / count($allEtudiants)) * 100, 2) : 0;
        
        // Compter les différents statuts
        $statutsCount = [
            'Présent' => 0,
            'Retard' => 0,
            'Excusé' => 0
        ];
        
        foreach ($presents as $present) {
            if (isset($statutsCount[$present['statut']])) {
                $statutsCount[$present['statut']]++;
            }
        }
        
        $pdf->SetFont('helvetica', '', 10);
        $pdf->SetTextColor(80, 80, 80);
        
        // Afficher les statistiques
        $pdf->Cell(40, 8, 'Taux de présence:', 1, 0, 'L', 1);
        $pdf->Cell(130, 8, $tauxPresence . '%', 1, 1, 'L');
        
        $pdf->Cell(40, 8, 'Présents:', 1, 0, 'L', 1);
        $pdf->Cell(130, 8, $statutsCount['Présent'] . ' étudiants', 1, 1, 'L');
        
        $pdf->Cell(40, 8, 'En retard:', 1, 0, 'L', 1);
        $pdf->Cell(130, 8, $statutsCount['Retard'] . ' étudiants', 1, 1, 'L');
        
        $pdf->Cell(40, 8, 'Excusés:', 1, 0, 'L', 1);
        $pdf->Cell(130, 8, $statutsCount['Excusé'] . ' étudiants', 1, 1, 'L');
        
        $pdf->Cell(40, 8, 'Absents:', 1, 0, 'L', 1);
        $pdf->Cell(130, 8, $nbAbsents . ' étudiants', 1, 1, 'L');
        
        // Liste des étudiants présents
        $pdf->Ln(5);
        
        // En-têtes du tableau
        $pdf->SetFont('helvetica', 'B', 10);
        $pdf->SetFillColor($primaryColor[0], $primaryColor[1], $primaryColor[2]);
        $pdf->SetTextColor(255, 255, 255);
        
        $pdf->Cell(10, 8, '#', 1, 0, 'C', 1);
        $pdf->Cell(30, 8, 'Matricule', 1, 0, 'C', 1);
        $pdf->Cell(100, 8, 'Nom & Prénom', 1, 0, 'C', 1);
        $pdf->Cell(30, 8, 'Statut', 1, 1, 'C', 1);
        
        // Contenu du tableau
        $pdf->SetFont('helvetica', '', 9);
        $pdf->SetTextColor(80, 80, 80);
        $pdf->SetFillColor(245, 245, 245);
        
        $i = 1;
        $fill = false;
        
        foreach ($allEtudiants as $etudiant) {
            $estPresent = isset($etudiantsPresents[$etudiant['idetudiant']]);
            $statut = $estPresent ? $etudiantsPresents[$etudiant['idetudiant']]['statut'] : 'Absent';
            
            $pdf->Cell(10, 7, $i++, 1, 0, 'C', $fill);
            $pdf->Cell(30, 7, $etudiant['matricule'], 1, 0, 'C', $fill);
            $pdf->Cell(100, 7, htmlspecialchars($etudiant['noms']), 1, 0, 'L', $fill);
            
            // Définir la couleur du statut
            switch ($statut) {
                case 'Présent':
                    $pdf->SetTextColor(0, 128, 0); // Vert
                    break;
                case 'Retard':
                    $pdf->SetTextColor(255, 128, 0); // Orange
                    break;
                case 'Excusé':
                    $pdf->SetTextColor(0, 0, 255); // Bleu
                    break;
                case 'Absent':
                    $pdf->SetTextColor(255, 0, 0); // Rouge
                    break;
                default:
                    $pdf->SetTextColor(80, 80, 80); // Gris
            }
            
            $pdf->Cell(30, 7, $statut, 1, 1, 'C', $fill);
            $pdf->SetTextColor(80, 80, 80); // Réinitialiser la couleur
            
            $fill = !$fill; // Alterner les couleurs de fond
        }
        
        // Espace pour signatures
        $pdf->Ln(15);
        $pdf->SetFont('helvetica', 'B', 11);
        $pdf->SetTextColor($secondaryColor[0], $secondaryColor[1], $secondaryColor[2]);
        $pdf->Cell(0, 8, 'Signatures', 0, 1, 'L');
        
        $pdf->SetFont('helvetica', '', 10);
        $pdf->SetTextColor(80, 80, 80);
        
        // Créer des espaces pour les signatures
        $pdf->Cell(85, 8, 'L\'enseignant:', 0, 0, 'L');
        $pdf->Cell(85, 8, 'Le responsable académique:', 0, 1, 'L');
        
        $pdf->Cell(85, 20, '', 'B', 0, 'L'); // Ligne pour signature
        $pdf->Cell(85, 20, '', 'B', 1, 'L'); // Ligne pour signature
        
        $pdf->Cell(85, 8, htmlspecialchars($seance['nom_enseignant']), 0, 0, 'L');
        $pdf->Cell(85, 8, '', 0, 1, 'L');
        
        // Générer le PDF
        $filename = 'liste_presence_' . $idSeance . '_' . date('Ymd') . '.pdf';
        $pdf->Output($filename, 'I');
        exit();
    }
    catch (Exception $e) {
        $_SESSION['swal_error'] = [
            'title' => 'Erreur',
            'text' => $e->getMessage(),
            'icon' => 'error'
        ];
        header('Location: ../cours/presence.list?id=' . $idSeance);
        exit();
    }
} else {
    $_SESSION['swal_error'] = [
        'title' => 'Erreur',
        'text' => 'Identifiant de séance invalide.',
        'icon' => 'error'
    ];
    header('Location: ../cours/seances.list');
    exit();
}
