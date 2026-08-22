<?php
session_start();
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/models/Horaire.php';

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['id'])) {
    echo json_encode(['error' => 'Non autorisé']);
    exit;
}

// Vérifier si la requête est de type POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['error' => 'Méthode non autorisée']);
    exit;
}

// Récupérer les paramètres
$jour = isset($_POST['jour']) ? trim($_POST['jour']) : '';
$date_cours = isset($_POST['date_cours']) ? trim($_POST['date_cours']) : '';
$heureDebut = isset($_POST['heure_debut']) ? trim($_POST['heure_debut']) : '';
$heureFin = isset($_POST['heure_fin']) ? trim($_POST['heure_fin']) : '';
$salle = isset($_POST['salle']) ? trim($_POST['salle']) : '';
$idECUE = isset($_POST['idECUE']) ? intval($_POST['idECUE']) : 0;
$idAnneeAcad = isset($_POST['idAnneeAcad']) ? intval($_POST['idAnneeAcad']) : 0;
$idHoraire = isset($_POST['idHoraire']) ? intval($_POST['idHoraire']) : null;

// Validation des données
if (empty($jour) || empty($date_cours) || empty($heureDebut) || empty($heureFin) || empty($salle) || $idECUE <= 0 || $idAnneeAcad <= 0) {
    echo json_encode(['error' => 'Données invalides']);
    exit;
}

// Créer une instance de la classe Horaire
$horaire = new Horaire();

// Vérifier les conflits
$conflits = [];
$messages = [];

// Vérifier le conflit de salle
$conflitSalle = $horaire->verifierChevauchement($date_cours, $heureDebut, $heureFin, $salle, $idAnneeAcad, $idHoraire);
if ($conflitSalle['conflit']) {
    $conflits[] = 'salle';
    $messages[] = $conflitSalle['message'];
}

// Vérifier le conflit de promotion
$conflitPromotion = $horaire->verifierChevauchementPromotion($date_cours, $heureDebut, $heureFin, $idECUE, $idAnneeAcad, $idHoraire);
if ($conflitPromotion['conflit']) {
    $conflits[] = 'promotion';
    $messages[] = $conflitPromotion['message'];
}

// Vérifier le conflit d'enseignant
$conflitEnseignant = $horaire->verifierChevauchementEnseignant($date_cours, $heureDebut, $heureFin, $idECUE, $idAnneeAcad, $idHoraire);
if ($conflitEnseignant['conflit']) {
    $conflits[] = 'enseignant';
    $messages[] = $conflitEnseignant['message'];
}

// Retourner les résultats
echo json_encode([
    'hasConflicts' => !empty($conflits),
    'conflicts' => $conflits,
    'conflictMessages' => $messages
]);
