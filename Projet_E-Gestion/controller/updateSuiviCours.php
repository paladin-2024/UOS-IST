<?php
session_start();
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/models/SuiviCours.php';

header('Content-Type: application/json');

if (!isset($_SESSION['id'])) {
    echo json_encode(['success' => false, 'message' => 'Session expirée']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
    exit;
}

$idECUE       = filter_input(INPUT_POST, 'idECUE',   FILTER_VALIDATE_INT);
$idannee      = filter_input(INPUT_POST, 'idannee',   FILTER_VALIDATE_INT);
$statut       = filter_input(INPUT_POST, 'statut',    FILTER_SANITIZE_STRING);
$observations = filter_input(INPUT_POST, 'observations', FILTER_SANITIZE_STRING) ?? '';
$idUser       = (int) $_SESSION['id'];

if (!$idECUE || !$idannee || !$statut) {
    echo json_encode(['success' => false, 'message' => 'Données manquantes']);
    exit;
}

try {
    $model = new SuiviCours();
    $ok = $model->updateStatut($idECUE, $idannee, $statut, $observations, $idUser);

    if ($ok) {
        $labels = [
            'non_commence' => 'Non commencé',
            'en_cours'     => 'En cours',
            'termine'      => 'Terminé',
        ];
        echo json_encode([
            'success' => true,
            'message' => 'Statut mis à jour : ' . ($labels[$statut] ?? $statut),
            'statut'  => $statut,
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Statut invalide ou erreur base de données']);
    }
} catch (Exception $e) {
    error_log('updateSuiviCours: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erreur système']);
}
