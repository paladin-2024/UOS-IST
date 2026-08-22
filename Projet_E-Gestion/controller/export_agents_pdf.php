<?php
session_start();
require_once '../config/Connexion.php';
require_once '../models/Agent.php';
require_once '../models/Structure.php';
require_once '../models/Service.php';
require_once '../models/Grade.php';
require_once '../models/Universite.php';
require_once '../vendor/autoload.php'; // Assurez-vous que TCPDF est installé via Composer

// Vérification de la connexion et des droits
if (!isset($_SESSION['id'])) {
    header('Location: ../login');
    exit;
}

// Récupérer les paramètres de filtrage
$search = isset($_GET['search']) ? $_GET['search'] : '';
$typeAgent = isset($_GET['typeAgent']) ? $_GET['typeAgent'] : '';
$gradeId = isset($_GET['gradeId']) ? (int)$_GET['gradeId'] : 0;
$structureId = isset($_GET['structureId']) ? (int)$_GET['structureId'] : 0;
$serviceId = isset($_GET['serviceId']) ? (int)$_GET['serviceId'] : 0;

// Initialiser les modèles
$agent = new Agent();
$structure = new Structure();
$service = new Service();
$grade = new Grade();
$universite = new Universite();

// Récupérer les informations de l'université
$configUniversite = $universite->getConfigurationUniversite();

// Récupérer les agents filtrés
$agents = $agent->getFilteredAgents($search, $typeAgent, $gradeId, $structureId, $serviceId);

// Créer une instance de TCPDF
class MYPDF extends TCPDF {
    public function MultiRow($left, $right, $h=5) {
        // MultiCell($w, $h, $txt, $border=0, $align='J', $fill=0, $ln=1, $x='', $y='', $reseth=true, $stretch=0)
        
        $page_start = $this->getPage();
        $y_start = $this->GetY();
        
        // Écrivez la première cellule
        $this->MultiCell($left['w'], $h, $left['text'], 1, $left['align'], 0, 2, '', '', true, 0, false, true, 0);
        
        $page_end_1 = $this->getPage();
        $y_end_1 = $this->GetY();
        
        $this->setPage($page_start);
        
        // Positionnez-vous à droite de la première cellule
        $this->SetY($y_start);
        $this->SetX($this->GetX() + $left['w']);
        
        $this->MultiCell($right['w'], $h, $right['text'], 1, $right['align'], 0, 1, '', '', true, 0, false, true, 0);
        
        $page_end_2 = $this->getPage();
        $y_end_2 = $this->GetY();
        
        // Définir la nouvelle position Y (la plus grande des deux)
        if ($page_end_1 == $page_end_2) {
            $new_y = max($y_end_1, $y_end_2);
        } else {
            $new_y = $y_end_2;
        }
        
        $this->setPage(max($page_end_1, $page_end_2));
        $this->SetY($new_y);
    }
}

$pdf = new MYPDF('L', 'mm', 'A4', true, 'UTF-8');

// Définir les informations du document
$pdf->SetCreator('Système de gestion universitaire');
$pdf->SetAuthor($configUniversite['nom'] ?? 'Administration');
$pdf->SetTitle('Liste des Agents');
$pdf->SetSubject('Rapport des agents');
$pdf->SetKeywords('Agents, Enseignants, Personnel');

// Supprimer les en-têtes et pieds de page par défaut
$pdf->setPrintHeader(false);
$pdf->setPrintFooter(false);

// Définir la police par défaut
$pdf->SetFont('dejavusans', '', 10);

// Ajouter une page
$pdf->AddPage();

// En-tête avec les informations de l'université
if ($configUniversite) {
    // Logo de l'université (si disponible) - TAILLE RÉDUITE
    if (!empty($configUniversite['logo'])) {
        $logoPath = '../' . $configUniversite['logo'];
        if (file_exists($logoPath)) {
            // Taille réduite du logo (diminuée de 30 à 20)
            $pdf->Image($logoPath, 15, 10, 20, 0, '', '', '', false, 300, '', false, false, 0);
        }
    }
    
    // Titre et informations de l'université
    $pdf->SetFont('dejavusans', 'B', 12);
    $pdf->Cell(0, 8, strtoupper($configUniversite['ministere_tutelle']), 0, 1, 'C');
    
    $pdf->SetFont('dejavusans', 'B', 14);
    $pdf->Cell(0, 8, strtoupper($configUniversite['nom']), 0, 1, 'C');
    
    if (!empty($configUniversite['sigle'])) {
        $pdf->SetFont('dejavusans', 'B', 12);
        $pdf->Cell(0, 6, $configUniversite['sigle'], 0, 1, 'C');
    }
    
    $pdf->SetFont('dejavusans', '', 9);
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
    $pdf->Ln(2);
    $pdf->Cell(0, 0, '', 'T', 1);
    $pdf->Ln(3);
}

// Titre du rapport
$pdf->SetFont('dejavusans', 'B', 14);
$pdf->Cell(0, 8, 'Liste des Agents', 0, 1, 'C');
$pdf->SetFont('dejavusans', '', 9);
$pdf->Cell(0, 5, 'Date du rapport: ' . date('d/m/Y'), 0, 1, 'C');
$pdf->Ln(3);

// Définir la largeur des colonnes (ajustement pour éviter les débordements)
$colWidth = [
    'id' => 10,
    'noms' => 50,
    'matricule' => 25,
    'type' => 25,
    'grade' => 35,
    'telephone' => 25,
    'structure' => 85 // Augmentation pour contenir plus de texte
];

// En-têtes du tableau
$pdf->SetFillColor(200, 200, 200);
$pdf->SetFont('dejavusans', 'B', 9);
$pdf->Cell($colWidth['id'], 7, 'ID', 1, 0, 'C', 1);
$pdf->Cell($colWidth['noms'], 7, 'Noms', 1, 0, 'C', 1);
$pdf->Cell($colWidth['matricule'], 7, 'Matricule', 1, 0, 'C', 1);
$pdf->Cell($colWidth['type'], 7, 'Type', 1, 0, 'C', 1);
$pdf->Cell($colWidth['grade'], 7, 'Grade', 1, 0, 'C', 1);
$pdf->Cell($colWidth['telephone'], 7, 'Téléphone', 1, 0, 'C', 1);
$pdf->Cell($colWidth['structure'], 7, 'Structure/Service', 1, 1, 'C', 1);

// Contenu du tableau
$pdf->SetFont('dejavusans', '', 8);
$pdf->SetFillColor(255, 255, 255);

// Activer le mode pour gérer le texte long
$pdf->setCellPaddings(1, 1, 1, 1);
$pdf->SetCellHeightRatio(1.5);

$i=1;
foreach ($agents as $a) {
    // Récupérer les informations supplémentaires
    $gradeInfo = !empty($a['grade_id']) ? $grade->getGradeById($a['grade_id']) : null;
    $gradeName = $gradeInfo ? $gradeInfo['designation'] : '-';
    
    $serviceInfo = !empty($a['idService']) ? $service->getServiceById($a['idService']) : null;
    $serviceName = $serviceInfo ? $serviceInfo['designation'] : '-';
    
    $structureService = $a['designationStructure'] . ($serviceName != '-' ? ' / ' . $serviceName : '');
    
    // Calculer la hauteur nécessaire pour le texte le plus long (structure/service)
    $pdf->startTransaction();
    $startPage = $pdf->getPage();
    $startY = $pdf->GetY();
    $pdf->MultiCell($colWidth['structure'], 0, $structureService, 1, 'L', 0, 1, '', '', true, 0, false, true, 0);
    $endPage = $pdf->getPage();
    $endY = $pdf->GetY();
    $cellHeight = $endY - $startY;
    
    // Si le texte génère un changement de page, on ajuste
    if ($endPage > $startPage) {
        $cellHeight = $pdf->getPageHeight() - $startY + $endY;
    }
    
    $pdf = $pdf->rollbackTransaction();
    
    // S'assurer que la hauteur minimale est de 6mm
    $cellHeight = max(6, $cellHeight);
    
    // Ajouter la ligne au tableau avec des cellules qui peuvent contenir plusieurs lignes
    $pdf->Cell($colWidth['id'], $cellHeight, $i, 1, 0, 'C', 0, '', 0);
    $pdf->MultiCell($colWidth['noms'], $cellHeight, $a['noms'], 1, 'L', 0, 0, '', '', true, 0, false, true, $cellHeight);
    $pdf->MultiCell($colWidth['matricule'], $cellHeight, empty($a['matricule']) ? '-' : $a['matricule'], 1, 'L', 0, 0, '', '', true, 0, false, true, $cellHeight);
    $pdf->MultiCell($colWidth['type'], $cellHeight, empty($a['type_agent']) ? '-' : $a['type_agent'], 1, 'L', 0, 0, '', '', true, 0, false, true, $cellHeight);
    $pdf->MultiCell($colWidth['grade'], $cellHeight, $gradeName, 1, 'L', 0, 0, '', '', true, 0, false, true, $cellHeight);
    $pdf->MultiCell($colWidth['telephone'], $cellHeight, $a['telephone'], 1, 'L', 0, 0, '', '', true, 0, false, true, $cellHeight);
    $pdf->MultiCell($colWidth['structure'], $cellHeight, $structureService, 1, 'L', 0, 1, '', '', true, 0, false, true, $cellHeight);
    $i++;
}

// Pied de page avec signature si disponible
if ($configUniversite && !empty($configUniversite['nom_responsable'])) {
    $pdf->Ln(10);
    $pdf->SetFont('dejavusans', '', 9);
    $pdf->Cell(0, 5, 'Le ' . date('d/m/Y'), 0, 1, 'R');
    
    // Titre du responsable et nom
    $pdf->Cell(0, 5, $configUniversite['titre_responsable'] ?? 'Le Responsable', 0, 1, 'R');
    
    // Espace pour la signature
    $pdf->Ln(15);
    
    // Signature si disponible - TAILLE RÉDUITE
    if (!empty($configUniversite['signature_responsable'])) {
        $signaturePath = '../' . $configUniversite['signature_responsable'];
        if (file_exists($signaturePath)) {
            // Taille réduite de la signature (réduite à 20mm)
            $pdf->Image($signaturePath, 240, $pdf->GetY() - 15, 20, 0, '', '', '', false, 300, '', false, false, 0);
        }
    }
    
    // Nom du responsable
    $pdf->SetFont('dejavusans', 'B', 9);
    $pdf->Cell(0, 5, $configUniversite['nom_responsable'], 0, 1, 'R');
}

// Générer le PDF
$filename = 'liste_agents_' . date('Y-m-d_H-i-s') . '.pdf';

// En-têtes HTTP pour le téléchargement
$pdf->Output($filename, 'D');
exit;
