<?php
session_start();

if (!isset($_SESSION['id']) || $_SESSION['idRole'] != 1) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Non autorisé']);
    exit;
}

try {
    require_once "./config/Connexion.php";

    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    $designation = isset($_POST['designation']) ? $_POST['designation'] : '';
    $montant = isset($_POST['montant']) ? (float)$_POST['montant'] : 0;
    $devise = isset($_POST['devise']) ? $_POST['devise'] : 'XOF';
    $estObligatoire = isset($_POST['estObligatoire']) ? 1 : 0;
    $description = isset($_POST['description']) ? $_POST['description'] : '';

    if (!$id || empty($designation) || $montant <= 0) {
        echo json_encode(['success' => false, 'message' => 'Paramètres invalides']);
        exit;
    }

    $db = Connexion::getInstance()->getPDO();

    $query = "UPDATE frais_soutenance 
              SET designation = :designation, 
                  montant = :montant, 
                  devise = :devise, 
                  estObligatoire = :estObligatoire, 
                  description = :description
              WHERE idfrais_soutenance = :id";

    $stmt = $db->prepare($query);
    $result = $stmt->execute([
        'id' => $id,
        'designation' => $designation,
        'montant' => $montant,
        'devise' => $devise,
        'estObligatoire' => $estObligatoire,
        'description' => $description
    ]);

    if ($result) {
        echo json_encode(['success' => true, 'message' => 'Le frais a été mis à jour']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Erreur lors de la mise à jour']);
    }
} catch (Exception $e) {
    error_log("Erreur: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erreur serveur']);
}
