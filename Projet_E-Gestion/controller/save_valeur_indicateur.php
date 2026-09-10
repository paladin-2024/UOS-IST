<?php
require_once dirname(__DIR__) . '/views/405.php';
session_start();
require_once dirname(__DIR__) . '/config/Connexion.php';
require_once dirname(__DIR__) . '/utils/Security.php';

AppSecurity::verifySession();

$connexion = Connexion::getInstance()->getPDO();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $idIndicateur = intval($_POST['idIndicateur'] ?? 0);
        $idStructure = intval($_POST['idStructure'] ?? 0);
        $dateEnregistrement = trim($_POST['dateEnregistrement'] ?? '');
        $valeur = $_POST['valeur'] ?? '';
        $observation = trim($_POST['observation'] ?? '');

        if ($idIndicateur <= 0) {
            throw new Exception("Indicateur invalide");
        }
        if ($idStructure <= 0) {
            throw new Exception("Structure invalide");
        }
        if (empty($dateEnregistrement) || !strtotime($dateEnregistrement)) {
            throw new Exception("Date invalide");
        }
        if ($valeur === '' || !is_numeric($valeur)) {
            throw new Exception("La valeur doit être numérique");
        }

        $stmt = $connexion->prepare("
            INSERT INTO valeur_indicateur (\"idStructure\", \"idIndicateur\", \"dateEnregistrement\", valeur, observation)
            VALUES (:idStructure, :idIndicateur, :dateEnregistrement, :valeur, :observation)
        ");
        $stmt->bindParam(':idStructure', $idStructure);
        $stmt->bindParam(':idIndicateur', $idIndicateur);
        $stmt->bindParam(':dateEnregistrement', $dateEnregistrement);
        $stmt->bindParam(':valeur', $valeur);
        $stmt->bindParam(':observation', $observation);
        $stmt->execute();

        $_SESSION['message'] = 'La valeur a été encodée avec succès';
        $_SESSION['messageType'] = 'success';
    } catch (Exception $e) {
        $_SESSION['message'] = 'Erreur: ' . $e->getMessage();
        $_SESSION['messageType'] = 'danger';
    }
}

header('Location: ../indicateur/encoder');
exit();
