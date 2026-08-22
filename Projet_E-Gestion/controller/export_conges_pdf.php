<?php
session_start();
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/models/Conge.php';
require_once dirname(__DIR__) . '/models/Agent.php';
require_once dirname(__DIR__) . '/models/Service.php';
require_once dirname(__DIR__) . '/models/Structure.php';
require_once dirname(__DIR__) . '/models/Universite.php';
require_once dirname(__DIR__) . '/vendor/autoload.php'; // Pour TCPDF

// Vérifier si l'utilisateur est connecté
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

// Récupérer le type d'export et l'ID du service (si spécifié)
$type = isset($_GET['type']) ? $_GET['type'] : 'en_conge';
$idService = isset($_GET['service']) ? intval($_GET['service']) : null;

// Récupérer les données selon le type d'export
$data = [];
$title = '';

if ($type === 'en_conge') {
    // Récupérer les statistiques pour le tableau de bord
    $dashboard = $congeModel->getDashboardConges($idService);
    $data = $dashboard['agents_en_conge'];
    $title = 'Liste des agents actuellement en congé';
} elseif ($type === 'en_attente') {
    // Récupérer les demandes en attente
    $data = $congeModel->getDemandesCongeEnAttente($idService);
    $title = 'Liste des demandes de congé en attente';
} else {
    $_SESSION['swal_error'] = [
        'title' => 'Erreur',
        'text' => 'Type d\'export non valide.',
        'icon' => 'error'
    ];
    header('Location: ../grh/conges.list');
    exit();
}

// Récupérer les informations du service si spécifié
$serviceInfo = null;
if ($idService) {
    $serviceInfo = $serviceModel->getServiceById($idService);
    if ($serviceInfo) {
        $title .= ' - Service: ' . $serviceInfo['designationService'];
    }
}

// Récupérer les informations de l'université
$configUniversite = $universiteModel->getConfigurationUniversite();

// Créer le PDF avec TCPDF - Changement en orientation paysage ('L')
$pdf = new TCPDF('L', 'mm', 'A4', true, 'UTF-8', false);

// Configurer le document
$pdf->SetCreator('eGestion');
$pdf->SetAuthor($configUniversite['nom'] ?? 'eGestion');
$pdf->SetTitle($title);
$pdf->SetSubject($title);
$pdf->SetKeywords('Congé, Liste, Export');

// Supprimer les en-têtes et pieds de page par défaut
$pdf->setPrintHeader(false);
$pdf->setPrintFooter(false);

// Définir les marges - Ajustées pour le paysage
$pdf->SetMargins(15, 15, 15);
$pdf->SetAutoPageBreak(true, 15);

// Couleurs pour le design
$primaryColor = array(0, 87, 146); // Bleu foncé
$secondaryColor = array(70, 130, 180); // Bleu acier
$accentColor = array(0, 121, 194); // Bleu moyen

// Ajouter une page
$pdf->AddPage();

// En-tête avec les informations de l'université - Ajusté pour le paysage
if ($configUniversite) {
    // Logo de l'université
    if (!empty($configUniversite['logo'])) {
        $logoPath = dirname(__DIR__) . '/' . $configUniversite['logo'];
        if (file_exists($logoPath)) {
            $pdf->Image($logoPath, 15, 10, 18, 0, '', '', '', false, 200, '', false, false, 0);
        }
    }
    
    // Titre et informations de l'université - Centré horizontalement
    $pdf->SetTextColor($primaryColor[0], $primaryColor[1], $primaryColor[2]);
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->SetXY(45, 10);
    $pdf->Cell(200, 8, strtoupper($configUniversite['ministere_tutelle'] ?? ''), 0, 1, 'C');
    
    $pdf->SetFont('helvetica', 'B', 14);
    $pdf->SetXY(45, 18);
    $pdf->Cell(200, 8, strtoupper($configUniversite['nom'] ?? ''), 0, 1, 'C');
    
    if (!empty($configUniversite['sigle'])) {
        $pdf->SetFont('helvetica', 'B', 12);
        $pdf->SetXY(45, 26);
        $pdf->Cell(200, 6, $configUniversite['sigle'], 0, 1, 'C');
    }
    
    // Ligne de séparation - Étendue pour le paysage
    $pdf->SetLineStyle(array('width' => 0.5, 'cap' => 'butt', 'join' => 'miter', 'dash' => 0, 'color' => $accentColor));
    $pdf->Line(15, 38, $pdf->getPageWidth() - 15, 38);
}

// Titre du document avec fond coloré - Ajusté pour le paysage
$pdf->SetFillColor($primaryColor[0], $primaryColor[1], $primaryColor[2]);
$pdf->SetTextColor(255, 255, 255);
$pdf->SetFont('helvetica', 'B', 14);
$pdf->Ln(8);
$pdf->Cell(0, 10, $title, 0, 1, 'C', 1);

// Date d'impression
$pdf->SetTextColor(80, 80, 80);
$pdf->SetFont('helvetica', 'I', 10);
$pdf->Cell(0, 8, 'Date d\'impression: ' . date('d/m/Y H:i'), 0, 1, 'R');

// Contenu du PDF selon le type - Tableaux ajustés pour le paysage
if ($type === 'en_conge') {
    // Tableau des agents en congé
    if (empty($data)) {
        $pdf->SetTextColor(80, 80, 80);
        $pdf->SetFont('helvetica', '', 11);
        $pdf->Ln(5);
        $pdf->Cell(0, 10, 'Aucun agent en congé actuellement.', 0, 1, 'C');
    } else {
        $pdf->Ln(5);
        
        // En-têtes du tableau - Largeurs ajustées pour le paysage
        $pdf->SetFillColor(240, 240, 240);
        $pdf->SetTextColor($primaryColor[0], $primaryColor[1], $primaryColor[2]);
        $pdf->SetFont('helvetica', 'B', 8);
        
        // Largeurs des colonnes optimisées pour le paysage
        $pdf->Cell(60, 8, 'Agent', 1, 0, 'C', 1);
        $pdf->Cell(20, 8, 'Matricule', 1, 0, 'C', 1);
        $pdf->Cell(60, 8, 'Service', 1, 0, 'C', 1);
        $pdf->Cell(60, 8, 'Type de congé', 1, 0, 'C', 1);
        $pdf->Cell(20, 8, 'Début', 1, 0, 'C', 1);
        $pdf->Cell(20, 8, 'Fin', 1, 0, 'C', 1);
        $pdf->Cell(25, 8, 'Jours restants', 1, 1, 'C', 1);
        
        // Données du tableau
        $pdf->SetTextColor(60, 60, 60);
        $pdf->SetFont('helvetica', '', 8);
        
        foreach ($data as $agent) {
            $dateFin = new DateTime($agent['date_fin']);
            $aujourdhui = new DateTime();
            $interval = $aujourdhui->diff($dateFin);
            $joursRestants = $interval->days;
            
            // Récupérer le service de l'agent
            $agentInfo = $agentModel->getAgentById($agent['idAgent']);
            $serviceNom = "Non défini";
            if ($agentInfo && isset($agentInfo['idService'])) {
                $serviceInfo = $serviceModel->getServiceById($agentInfo['idService']);
                if ($serviceInfo) {
                    $serviceNom = $serviceInfo['designationService'];
                }
            }
            
            $pdf->Cell(60, 8, $agent['noms'], 1, 0, 'L');
            $pdf->Cell(20, 8, $agent['matricule'], 1, 0, 'C');
            $pdf->Cell(60, 8, $serviceNom, 1, 0, 'L');
            $pdf->Cell(60, 8, $agent['type_conge_nom'], 1, 0, 'L');
            $pdf->Cell(20, 8, date('d/m/Y', strtotime($agent['date_debut'])), 1, 0, 'C');
            $pdf->Cell(20, 8, date('d/m/Y', strtotime($agent['date_fin'])), 1, 0, 'C');
            $pdf->Cell(25, 8, $joursRestants . ' jour(s)', 1, 1, 'C');
        }
    }
} elseif ($type === 'en_attente') {
    // Tableau des demandes en attente
    if (empty($data)) {
        $pdf->SetTextColor(80, 80, 80);
        $pdf->SetFont('helvetica', '', 11);
        $pdf->Ln(5);
        $pdf->Cell(0, 10, 'Aucune demande en attente actuellement.', 0, 1, 'C');
    } else {
        $pdf->Ln(5);
        
        // En-têtes du tableau - Largeurs ajustées pour le paysage
        $pdf->SetFillColor(240, 240, 240);
        $pdf->SetTextColor($primaryColor[0], $primaryColor[1], $primaryColor[2]);
        $pdf->SetFont('helvetica', 'B', 11);
        
        // Largeurs des colonnes optimisées pour le paysage
        $pdf->Cell(60, 8, 'Agent', 1, 0, 'C', 1);
        $pdf->Cell(20, 8, 'Matricule', 1, 0, 'C', 1);
        $pdf->Cell(60, 8, 'Service', 1, 0, 'C', 1);
        $pdf->Cell(60, 8, 'Type de congé', 1, 0, 'C', 1);
        $pdf->Cell(20, 8, 'Début', 1, 0, 'C', 1);
        $pdf->Cell(20, 8, 'Fin', 1, 0, 'C', 1);
        $pdf->Cell(25, 8, 'Durée (jours)', 1, 1, 'C', 1);
        
        // Données du tableau
        $pdf->SetTextColor(60, 60, 60);
        $pdf->SetFont('helvetica', '', 10);
        
        foreach ($data as $demande) {
            // Calcul de la durée en jours ouvrables
            $dateDebut = new DateTime($demande['date_debut']);
            $dateFin = new DateTime($demande['date_fin']);
            $joursOuvrables = 0;
            
            for ($date = clone $dateDebut; $date <= $dateFin; $date->modify('+1 day')) {
                $jour = $date->format('N'); // 1 (lundi) à 7 (dimanche)
                if ($jour < 6) { // Si ce n'est pas samedi (6) ou dimanche (7)
                    $joursOuvrables++;
                }
            }
            
            $pdf->Cell(60, 8, $demande['nom_agent'], 1, 0, 'L');
            $pdf->Cell(20, 8, $demande['matricule'], 1, 0, 'C');
            $pdf->Cell(60, 8, $demande['service_nom'], 1, 0, 'L');
            $pdf->Cell(60, 8, $demande['type_conge_nom'], 1, 0, 'L');
            $pdf->Cell(20, 8, date('d/m/Y', strtotime($demande['date_debut'])), 1, 0, 'C');
            $pdf->Cell(20, 8, date('d/m/Y', strtotime($demande['date_fin'])), 1, 0, 'C');
            $pdf->Cell(25, 8, $joursOuvrables, 1, 1, 'C');
        }
    }
}

$pdf->ln(10);
$pdf->SetFont('helvetica', 'I', 8);
$pdf->Cell(0, 5, 'Document généré le ' . date('d/m/Y à H:i:s'), 0, 1, 'C');
$pdf->Cell(0, 5, ($configUniversite['nom'] ?? 'eGestion') . ' - ' . ($configUniversite['site_web'] ?? 'Système de gestion intégré'), 0, 1, 'C');

// Générer le PDF
$fileName = 'liste_conges_' . $type . '_' . date('Y-m-d_H-i-s') . '.pdf';
$pdf->Output($fileName, 'I');
exit();
