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

// Récupérer les paramètres
if (!isset($_GET['deliberation']) || empty($_GET['deliberation']) || !isset($_GET['matricule']) || empty($_GET['matricule'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Paramètres manquants']);
    exit();
}

$deliberationId = intval($_GET['deliberation']);
$matricule = $_GET['matricule'];
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

// Récupérer les données pour le relevé
$dataReleve = $deliberation->getDataForReleve($deliberationId, $matricule);

if (isset($dataReleve['error'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => $dataReleve['error']]);
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
    $pdf->SetTitle('Relevé de notes');
    $pdf->SetSubject('Relevé de notes - ' . $dataReleve['etudiant']['noms']);
    
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
    $pdf->Cell(0, 10, 'RELEVÉ DE NOTES', 0, 1, 'C');
    
    // Informations sur l'étudiant
    $pdf->Ln(5);
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->Cell(0, 10, 'Informations de l\'étudiant', 0, 1, 'L');
    
    $pdf->SetFont('helvetica', '', 10);
    $pdf->Cell(60, 7, 'Nom:', 0, 0, 'L');
    $pdf->Cell(0, 7, $dataReleve['etudiant']['noms'], 0, 1, 'L');
    
    $pdf->Cell(60, 7, 'Matricule:', 0, 0, 'L');
    $pdf->Cell(0, 7, $dataReleve['etudiant']['matricule'], 0, 1, 'L');
    
    $pdf->Cell(60, 7, 'Promotion:', 0, 0, 'L');
    $pdf->Cell(0, 7, $dataReleve['deliberation']['designationPromotion'], 0, 1, 'L');
    
    $pdf->Cell(60, 7, 'Année académique:', 0, 0, 'L');
    $pdf->Cell(0, 7, $dataReleve['deliberation']['designation'], 0, 1, 'L');
    
    $pdf->Cell(60, 7, 'Session:', 0, 0, 'L');
    $pdf->Cell(0, 7, $dataReleve['deliberation']['designSession'], 0, 1, 'L');
    
    // Résultats par semestre
    $semestreActuel = null;
    
    foreach ($dataReleve['notes'] as $note) {
        // Si on change de semestre, ajouter un en-tête
        if ($semestreActuel !== $note['numeroSemestre']) {
            $semestreActuel = $note['numeroSemestre'];
            
            $pdf->Ln(5);
            $pdf->SetFont('helvetica', 'B', 12);
            $pdf->Cell(0, 10, 'Semestre ' . $semestreActuel, 0, 1, 'L');
            
            $pdf->SetFont('helvetica', 'B', 8);
            $pdf->Cell(80, 7, 'Cours', 1, 0, 'C');
            $pdf->Cell(20, 7, 'CC', 1, 0, 'C');
            $pdf->Cell(20, 7, 'EX', 1, 0, 'C');
            $pdf->Cell(20, 7, 'MF', 1, 0, 'C');
            $pdf->Cell(20, 7, 'Crédits', 1, 0, 'C');
            $pdf->Cell(30, 7, 'Décision', 1, 1, 'C');
        }
        
        // Afficher la note
        $pdf->SetFont('helvetica', '', 8);
        $pdf->Cell(80, 7, $note['designationECUE'], 1, 0, 'L');
        $pdf->Cell(20, 7, $note['CC'] !== null ? number_format($note['CC'], 2) : '-', 1, 0, 'C');
        $pdf->Cell(20, 7, $note['EX'] !== null ? number_format($note['EX'], 2) : '-', 1, 0, 'C');
        $pdf->Cell(20, 7, $note['MF'] !== null ? number_format($note['MF'], 2) : '-', 1, 0, 'C');
        
        // Calculer les crédits
        $credits = ((float)$note['CMI'] + (float)$note['TD'] + (float)$note['TP'])/15;
        $creditAcquis = $note['MF'] >= 10 ? $credits : 0;
        
        $pdf->Cell(20, 7, $creditAcquis . '/' . $credits, 1, 0, 'C');
        $pdf->Cell(30, 7, $note['MF'] >= 10 ? 'Validé' : 'Non validé', 1, 1, 'C');
    }
    
    // Moyennes par UE
    $pdf->Ln(5);
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->Cell(0, 10, 'Moyennes par Unité d\'Enseignement', 0, 1, 'L');
    
    $pdf->SetFont('helvetica', 'B', 8);
    $pdf->Cell(100, 7, 'Unité d\'Enseignement', 1, 0, 'C');
    $pdf->Cell(30, 7, 'Moyenne', 1, 0, 'C');
    $pdf->Cell(30, 7, 'Crédits', 1, 0, 'C');
    $pdf->Cell(30, 7, 'Décision', 1, 1, 'C');
    
    $pdf->SetFont('helvetica', '', 8);
    foreach ($dataReleve['moyennes_ue'] as $ue) {
        $pdf->Cell(100, 7, $ue['designationUE'], 1, 0, 'L');
        $pdf->Cell(30, 7, number_format($ue['moyenne_deliberee'], 2), 1, 0, 'C');
        $pdf->Cell(30, 7, $ue['credits_obtenus'] . '/' . $ue['credits_total'], 1, 0, 'C');
        $pdf->Cell(30, 7, $ue['est_validee'] ? 'Validée' : 'Non validée', 1, 1, 'C');
    }
    
    // Moyennes par semestre
    $pdf->Ln(5);
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->Cell(0, 10, 'Moyennes par Semestre', 0, 1, 'L');
    
    $pdf->SetFont('helvetica', 'B', 8);
    $pdf->Cell(60, 7, 'Semestre', 1, 0, 'C');
    $pdf->Cell(30, 7, 'Moyenne', 1, 0, 'C');
    $pdf->Cell(30, 7, 'Crédits', 1, 0, 'C');
    $pdf->Cell(30, 7, 'Décision', 1, 1, 'C');
    
    $pdf->SetFont('helvetica', '', 8);
    foreach ($dataReleve['moyennes_semestre'] as $semestre) {
        $pdf->Cell(60, 7, 'Semestre ' . $semestre['numeroSemestre'], 1, 0, 'L');
        $pdf->Cell(30, 7, number_format($semestre['moyenne_deliberee'], 2), 1, 0, 'C');
        $pdf->Cell(30, 7, $semestre['credits_obtenus'] . '/' . $semestre['credits_total'], 1, 0, 'C');
        $pdf->Cell(30, 7, $semestre['est_valide'] ? 'Validé' : 'Non validé', 1, 1, 'C');
    }
    
    // Résultat final
    $pdf->Ln(5);
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->Cell(0, 10, 'Résultat Final', 0, 1, 'L');
    
    $pdf->SetFont('helvetica', '', 10);
    $pdf->Cell(60, 7, 'Moyenne générale:', 0, 0, 'L');
    $pdf->Cell(0, 7, number_format($dataReleve['moyenne_annuelle']['moyenne_deliberee'], 2) . '/20', 0, 1, 'L');
    
    $pdf->Cell(60, 7, 'Crédits acquis:', 0, 0, 'L');
    $pdf->Cell(0, 7, $dataReleve['resultat_final']['credits_obtenus'] . '/' . $dataReleve['resultat_final']['credits_total'], 0, 1, 'L');
    
    $pdf->Cell(60, 7, 'Mention:', 0, 0, 'L');
    $pdf->Cell(0, 7, $dataReleve['moyenne_annuelle']['mention'] ?? 'Aucune', 0, 1, 'L');
    
    $pdf->Cell(60, 7, 'Décision du jury:', 0, 0, 'L');
    $pdf->Cell(0, 7, $dataReleve['resultat_final']['decision'], 0, 1, 'L');
    
    // Mentions spéciales
    if (!empty($dataReleve['mentions_speciales'])) {
        $pdf->Ln(5);
        $pdf->SetFont('helvetica', 'B', 12);
        $pdf->Cell(0, 10, 'Mentions spéciales', 0, 1, 'L');
        
        $pdf->SetFont('helvetica', '', 10);
        foreach ($dataReleve['mentions_speciales'] as $mention) {
            $pdf->Cell(60, 7, $mention['type_mention'] . ':', 0, 0, 'L');
            $pdf->Cell(0, 7, $mention['commentaire'], 0, 1, 'L');
        }
    }
    
    // Commentaire
    if (!empty($dataReleve['resultat_final']['commentaire'])) {
        $pdf->Ln(5);
        $pdf->SetFont('helvetica', 'B', 12);
        $pdf->Cell(0, 10, 'Commentaire', 0, 1, 'L');
        
        $pdf->SetFont('helvetica', '', 10);
        $pdf->MultiCell(0, 7, $dataReleve['resultat_final']['commentaire'], 0, 'L');
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
    foreach ($dataReleve['membres_jury'] as $membre) {
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
    $filename = 'Releve_Notes_' . $matricule . '_' . date('Ymd_His') . '.pdf';
    
    // Sortie du PDF
    $pdf->Output($filename, 'I');
    
} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'Erreur lors de la génération du PDF: ' . $e->getMessage()
    ]);
}
