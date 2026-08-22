<?php
header('Content-Type: application/json');
session_start();
require_once '../config/Connexion.php';
require_once '../models/Universite.php';
require_once '../models/Etudiant.php';

$universite = new Universite();
$etudiantModel = new Etudiant();
$connexion = Connexion::getInstance()->getPDO();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        'success' => false,
        'message' => 'Méthode non autorisée'
    ]);
    exit;
}

// Vérifier que l'utilisateur est connecté
if (!isset($_SESSION['student_id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Vous devez être connecté pour effectuer cette action'
    ]);
    exit;
}

$etudiant_id = intval($_SESSION['student_id']);
$promotion_cible_id = isset($_POST['promotion_cible_id']) ? intval($_POST['promotion_cible_id']) : 0;
$config_id = isset($_POST['config_id']) ? intval($_POST['config_id']) : 0;

if (!$promotion_cible_id || !$config_id) {
    echo json_encode([
        'success' => false,
        'message' => 'Paramètres invalides'
    ]);
    exit;
}

try {
    // Vérifier si l'étudiant a déjà fait un choix cette année
    if ($universite->aDejaChoisiOrientation($etudiant_id)) {
        echo json_encode([
            'success' => false,
            'message' => 'Vous avez déjà fait un choix d\'orientation cette année'
        ]);
        exit;
    }

    // Vérifier si les frais sont payés
    if (!$universite->hasStudentPaidOrientationChoiceFees($etudiant_id, $config_id)) {
        $feesStatus = $universite->getOrientationChoiceFeesStatus($etudiant_id, $config_id);
        $feesHtml = '';
        foreach ($feesStatus as $fee) {
            $statusBadge = '';
            if ($fee['statut_paiement'] === 'paye') {
                $statusBadge = '<span class="badge bg-success">Payé</span>';
            } elseif ($fee['statut_paiement'] === 'partiel') {
                $statusBadge = '<span class="badge bg-warning">Paiement partiel</span>';
            } else {
                $statusBadge = '<span class="badge bg-danger">Non payé</span>';
            }
            $feesHtml .= '<li>' . htmlspecialchars($fee['designation']) . ' - ' . number_format($fee['montant'], 2) . ' ' . htmlspecialchars($fee['devise']) . ' ' . $statusBadge . '</li>';
        }

        echo json_encode([
            'success' => false,
            'message' => 'Vous devez payer les frais suivants avant de faire votre choix:',
            'fees' => $feesStatus
        ]);
        exit;
    }

    // Enregistrer le choix d'orientation
    $result = $universite->enregistrerChoixOrientation($etudiant_id, $promotion_cible_id);

    if ($result) {
        // Détruire la session pour déconnecter l'étudiant
        session_destroy();
        
        echo json_encode([
            'success' => true,
            'message' => 'Votre choix d\'orientation a été enregistré avec succès. Vous allez être déconnecté.',
            'logout' => true
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Erreur lors de l\'enregistrement du choix'
        ]);
    }
} catch (Exception $e) {
    error_log("Erreur choix_orientation: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Erreur lors du traitement: ' . $e->getMessage()
    ]);
}
