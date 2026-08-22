<?php
session_start();
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/models/Deliberation.php';
require_once dirname(__DIR__) . '/models/Agent.php';
require_once dirname(__DIR__) . '/models/Universite.php';
require_once dirname(__DIR__) . '/vendor/autoload.php'; // Pour TCPDF ou MPDF

// Vérification de l'authentification
if (!isset($_SESSION['id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Non autorisé']);
    exit();
}

// Vérifier que la requête est de type GET
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
    exit();
}

// Récupérer l'ID de la délibération
if (!isset($_GET['deliberation']) || empty($_GET['deliberation'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'ID de délibération manquant']);
    exit();
}

$deliberationId = intval($_GET['deliberation']);
$userId = $_SESSION['id'];

// Initialiser les classes
$deliberation = new Deliberation();
$agent = new Agent();
$universite = new Universite();

// Vérifier les droits d'accès
$isAdmin = isset($_SESSION['idRole']) && $_SESSION['idRole'] == 1;
$agentId = $agent->getAgentIdByUserId($userId);

// Récupérer les informations de la délibération
$delib = $deliberation->getDeliberationById($deliberationId);

if (!$delib) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Délibération non trouvée']);
    exit();
}

if (!$isAdmin) {
    // Vérifier si l'agent est membre du bureau de jury
    $isMember = $deliberation->isAgentJuryMember($agentId, $delib['idbureau']);
    
    if (!$isMember) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Vous n\'avez pas accès à cette délibération']);
        exit();
    }
}

// Récupérer les données pour le PV
$dataPV = $deliberation->getDataForPV($deliberationId);

if (isset($dataPV['error'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => $dataPV['error']]);
    exit();
}

// Récupérer les informations de l'université
$configUniversite = $universite->getConfigurationUniversite();

// Générer le PDF avec TCPDF ou MPDF
try {
    // Initialiser TCPDF
    $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
    
    // Configurer le document
    $pdf->SetCreator('Système de Gestion Académique');
    $pdf->SetAuthor($configUniversite['nom']);
    $pdf->SetTitle('Procès-verbal de délibération');
    $pdf->SetSubject('Délibération ' . $delib['iddeliberation']);
    
    // Supprimer les en-têtes et pieds de page par défaut
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);
    
    // Ajouter une page
    $pdf->AddPage();
    
    // En-tête avec logo et informations de l'université
    $pdf->Image('../uploads/' . $configUniversite['logo'], 10, 10, 30, 0, '', '', '', false, 300);
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->Cell(0, 10, $configUniversite['nom'], 0, 1, 'C');
    $pdf->SetFont('helvetica', '', 10);
    $pdf->Cell(0, 5, $configUniversite['adresse'], 0, 1, 'C');
    $pdf->Cell(0, 5, $configUniversite['ville'] . ', ' . $configUniversite['pays'], 0, 1, 'C');
    
    // Titre du document
    $pdf->Ln(10);
    $pdf->SetFont('helvetica', 'B', 16);
    $pdf->Cell(0, 10, 'PROCÈS-VERBAL DE DÉLIBÉRATION', 0, 1, 'C');
    
    // Informations sur la délibération
    $pdf->Ln(5);
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->Cell(0, 10, 'Informations générales', 0, 1, 'L');
    
    $pdf->SetFont('helvetica', '', 10);
    $pdf->Cell(60, 7, 'Promotion:', 0, 0, 'L');
    $pdf->Cell(0, 7, $dataPV['deliberation']['designationPromotion'], 0, 1, 'L');
    
    $pdf->Cell(60, 7, 'Session:', 0, 0, 'L');
    $pdf->Cell(0, 7, $dataPV['deliberation']['designSession'], 0, 1, 'L');
    
    $pdf->Cell(60, 7, 'Année académique:', 0, 0, 'L');
    $pdf->Cell(0, 7, $dataPV['deliberation']['designation'], 0, 1, 'L');
    
    $pdf->Cell(60, 7, 'Date de délibération:', 0, 0, 'L');
    $pdf->Cell(0, 7, date('d/m/Y', strtotime($dataPV['deliberation']['date_deliberation'])), 0, 1, 'L');
    
    // Membres du jury
    $pdf->Ln(5);
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->Cell(0, 10, 'Membres du jury', 0, 1, 'L');
    
    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->Cell(80, 7, 'Nom', 1, 0, 'C');
    $pdf->Cell(60, 7, 'Grade', 1, 0, 'C');
    $pdf->Cell(50, 7, 'Fonction', 1, 1, 'C');
    
    $pdf->SetFont('helvetica', '', 10);
    foreach ($dataPV['membres'] as $membre) {
        $pdf->Cell(80, 7, $membre['noms'], 1, 0, 'L');
        $pdf->Cell(60, 7, $membre['grade'], 1, 0, 'L');
        $pdf->Cell(50, 7, $membre['fonction'], 1, 1, 'L');
    }
    
    // Statistiques
    $pdf->Ln(5);
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->Cell(0, 10, 'Statistiques de la délibération', 0, 1, 'L');
    
    $pdf->SetFont('helvetica', '', 10);
    $pdf->Cell(60, 7, 'Nombre total d\'étudiants:', 0, 0, 'L');
    $pdf->Cell(0, 7, $dataPV['statistiques']['total_etudiants'], 0, 1, 'L');
    
    // Statistiques par décision
    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->Cell(80, 7, 'Décision', 1, 0, 'C');
    $pdf->Cell(30, 7, 'Nombre', 1, 0, 'C');
    $pdf->Cell(30, 7, 'Pourcentage', 1, 1, 'C');
    
    $pdf->SetFont('helvetica', '', 10);
    foreach ($dataPV['statistiques']['decisions'] as $decision) {
        $pourcentage = ($decision['nombre'] / $dataPV['statistiques']['total_etudiants']) * 100;
        $pdf->Cell(80, 7, $decision['decision'], 1, 0, 'L');
        $pdf->Cell(30, 7, $decision['nombre'], 1, 0, 'C');
        $pdf->Cell(30, 7, number_format($pourcentage, 2) . '%', 1, 1, 'C');
    }
    
    // Liste des étudiants
    $pdf->AddPage();
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->Cell(0, 10, 'Résultats des étudiants', 0, 1, 'L');
    
    $pdf->SetFont('helvetica', 'B', 8);
    $pdf->Cell(10, 7, '#', 1, 0, 'C');
    $pdf->Cell(60, 7, 'Nom', 1, 0, 'C');
    $pdf->Cell(25, 7, 'Matricule', 1, 0, 'C');
    $pdf->Cell(25, 7, 'Moyenne', 1, 0, 'C');
    $pdf->Cell(25, 7, 'Crédits', 1, 0, 'C');
    $pdf->Cell(45, 7, 'Décision', 1, 1, 'C');
    
    $pdf->SetFont('helvetica', '', 8);
    $i = 1;
    foreach ($dataPV['resultats'] as $resultat) {
        $pdf->Cell(10, 7, $i++, 1, 0, 'C');
        $pdf->Cell(60, 7, $resultat['noms'], 1, 0, 'L');
        $pdf->Cell(25, 7, $resultat['matricule'], 1, 0, 'C');
        $pdf->Cell(25, 7, number_format($resultat['moyenne_generale'], 2), 1, 0, 'C');
        $pdf->Cell(25, 7, $resultat['credits_acquis'] . '/' . $resultat['credits_total'], 1, 0, 'C');
        $pdf->Cell(45, 7, $resultat['decision'], 1, 1, 'C');
    }
    
    // Signatures
    $pdf->Ln(20);
    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->Cell(95, 7, 'Le Président du Jury', 0, 0, 'C');
    $pdf->Cell(95, 7, 'Le Secrétaire du Jury', 0, 1, 'C');
    
    $pdf->Ln(15);
    
    // Trouver le président et le secrétaire
    $president = null;
    $secretaire = null;
    foreach ($dataPV['membres'] as $membre) {
        if ($membre['fonction'] === 'Président') {
            $president = $membre;
        } elseif ($membre['fonction'] === 'Secrétaire') {
            $secretaire = $membre;
        }
    }
    
    $pdf->SetFont('helvetica', '', 10);
    $pdf->Cell(95, 7, $president ? $president['noms'] : '', 0, 0, 'C');
    $pdf->Cell(95, 7, $secretaire ? $secretaire['noms'] : '', 0, 1, 'C');
    
        // Générer le nom du fichier
        $filename = 'PV_Deliberation_' . $deliberationId . '_' . date('Ymd_His') . '.pdf';
    
        // Enregistrer le chemin du PV dans la base de données
        $filePath = 'uploads/pv_deliberation/' . $filename;
       // $deliberation->updateProcesVerbal($deliberationId, $filePath);
        
        // Sortie du PDF
        $pdf->Output(dirname(__DIR__) . '/' . $filePath, 'F');
        $pdf->Output($filename, 'I');
        
    } catch (Exception $e) {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => 'Erreur lors de la génération du PDF: ' . $e->getMessage()
        ]);
    }
    