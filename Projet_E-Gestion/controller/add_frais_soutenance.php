<?php
session_start();

if (!isset($_SESSION['id']) || $_SESSION['idRole'] != 1) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Non autorisé']);
    exit;
}

try {
    require_once "./config/Connexion.php";

    $designation = isset($_POST['designation']) ? $_POST['designation'] : '';
    $montant = isset($_POST['montant']) ? (float)$_POST['montant'] : 0;
    $devise = isset($_POST['devise']) ? $_POST['devise'] : 'XOF';
    $estObligatoire = isset($_POST['estObligatoire']) ? 1 : 0;
    $description = isset($_POST['description']) ? $_POST['description'] : '';
    $anneeId = isset($_POST['annee_acad']) ? (int)$_POST['annee_acad'] : 0;

    if (empty($designation) || $montant <= 0 || $anneeId <= 0) {
        echo json_encode(['success' => false, 'message' => 'Paramètres invalides']);
        exit;
    }

    $db = Connexion::getInstance()->getPDO();

    $query = "INSERT INTO frais_soutenance 
              (designation, montant, devise, \"estObligatoire\", description, annee_acad_idannee_acad, \"idUser\", date_creation)
              VALUES (:designation, :montant, :devise, :estObligatoire, :description, :anneeId, :idUser, NOW())";

    $stmt = $db->prepare($query);
    $result = $stmt->execute([
        'designation' => $designation,
        'montant' => $montant,
        'devise' => $devise,
        'estObligatoire' => $estObligatoire,
        'description' => $description,
        'anneeId' => $anneeId,
        'idUser' => $_SESSION['id']
    ]);

    if ($result) {
        echo json_encode([
            'success' => true,
            'message' => 'Le frais a été ajouté avec succès',
            'id' => $db->lastInsertId()
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Erreur lors de l\'ajout']);
    }
} catch (Exception $e) {
    error_log("Erreur lors de l'ajout du frais: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erreur serveur']);
}
