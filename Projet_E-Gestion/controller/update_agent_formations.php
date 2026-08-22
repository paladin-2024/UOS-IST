<?php
session_start();
require_once dirname(__DIR__) . '/config/Connexion.php';

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['id'])) {
    $_SESSION['error'] = "Vous devez être connecté pour effectuer cette action.";
    header('Location: ../index.php');
    exit;
}

// Vérifier si la requête est de type POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['error'] = "Méthode non autorisée.";
    header('Location: ../index.php');
    exit;
}

// Récupérer les données du formulaire
$idAgent = isset($_POST['idAgent']) ? intval($_POST['idAgent']) : 0;
$niveauEtude = isset($_POST['niveauEtude']) ? trim($_POST['niveauEtude']) : '';
$returnTab = isset($_POST['returnTab']) ? $_POST['returnTab'] : 'formations';

// Validation des données
if (empty($idAgent)) {
    $_SESSION['error'] = "ID de l'agent non spécifié.";
    header('Location: ../index.php?page=grh/agent.edition');
    exit;
}

if (empty($niveauEtude)) {
    $_SESSION['error'] = "Veuillez spécifier le niveau d'étude.";
    header('Location: ../index.php?page=grh/agent.edition&searchType=code&search=' . $_POST['codeAgent'] . '&tab=' . $returnTab);
    exit;
}

try {
    $pdo = Connexion::getInstance()->getPDO();
    
    // Mettre à jour le niveau d'étude de l'agent
    $query = "UPDATE agent SET \"niveauEtude\" = :\"niveauEtude\" WHERE \"idAgent\" = :\"idAgent\"";
    $stmt = $pdo->prepare($query);
    $stmt->bindParam(':niveauEtude', $niveauEtude, PDO::PARAM_STR);
    $stmt->bindParam(':idAgent', $idAgent, PDO::PARAM_INT);
    $stmt->execute();
    
    // Récupérer le code de l'agent pour la redirection
    $queryAgent = "SELECT \"codeAgent\" FROM agent WHERE \"idAgent\" = :\"idAgent\"";
    $stmtAgent = $pdo->prepare($queryAgent);
    $stmtAgent->bindParam(':idAgent', $idAgent, PDO::PARAM_INT);
    $stmtAgent->execute();
    $agent = $stmtAgent->fetch(PDO::FETCH_ASSOC);
    
    if (!$agent) {
        throw new Exception("Agent non trouvé.");
    }
    
    $_SESSION['success'] = "Informations de formation mises à jour avec succès.";
    header('Location: ../grh/agent.edition&searchType=code&search=' . $agent['codeAgent'] . '&tab=' . $returnTab);
    
} catch (PDOException $e) {
    $_SESSION['error'] = "Erreur de base de données: " . $e->getMessage();
    header('Location: ../grh/agent.edition&tab=' . $returnTab);
    exit;
} catch (Exception $e) {
    $_SESSION['error'] = "Erreur serveur: " . $e->getMessage();
    header('Location: ../page=grh/agent.edition&tab=' . $returnTab);
    exit;
}
