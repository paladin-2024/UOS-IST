<?php
session_start();

header('Content-Type: application/json; charset=utf-8');

// Vérifier l'authentification
if (!isset($_SESSION['id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Non authentifié']);
    exit;
}

error_log("DEBUG: Requête reçue avec données: " . print_r($_POST, true));

try {
    require_once __DIR__ . "/../config/Connexion.php";
    require_once __DIR__ . "/../models/Soutenance.php";

    // Récupérer les paramètres
    $soutenanceId = isset($_POST['soutenance_id']) ? (int)$_POST['soutenance_id'] : 0;
    $lecteur1Id = isset($_POST['lecteur1_id']) ? (int)$_POST['lecteur1_id'] : 0;
    $lecteur2Id = isset($_POST['lecteur2_id']) ? (int)$_POST['lecteur2_id'] : 0;
    $dateSoutenance = isset($_POST['date_soutenance']) ? $_POST['date_soutenance'] : '';
    $lieuSoutenance = isset($_POST['lieu_soutenance']) ? $_POST['lieu_soutenance'] : '';
    $juryId = isset($_POST['jury_id']) && !empty($_POST['jury_id']) ? (int)$_POST['jury_id'] : null;

    error_log("DEBUG: Paramètres - Soutenance: $soutenanceId, Lecteur1: $lecteur1Id, Lecteur2: $lecteur2Id, Jury: $juryId");

    // Validation
    if (!$soutenanceId || !$lecteur1Id || !$lecteur2Id) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Paramètres manquants ou invalides: soutenanceId=' . $soutenanceId . ', lecteur1Id=' . $lecteur1Id . ', lecteur2Id=' . $lecteur2Id
        ]);
        exit;
    }

    if ($lecteur1Id === $lecteur2Id) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Les deux lecteurs doivent être différents'
        ]);
        exit;
    }

    // Initialiser le modèle Soutenance
    $soutenance = new Soutenance();
    $db = Connexion::getInstance()->getPDO();

    // Commencer une transaction
    $db->beginTransaction();

    try {
        // Assigner les lecteurs
        $soutenance->assignerLecteurs($soutenanceId, $lecteur1Id, $lecteur2Id);

        // Mettre à jour la date, le lieu et le jury de la soutenance
        if (!empty($dateSoutenance) && !empty($lieuSoutenance)) {
            // Convertir le format datetime-local en format MySQL
            $dateSoutenance = str_replace('T', ' ', $dateSoutenance);

            $query = "UPDATE soutenance 
                      SET date_soutenance = :dateSoutenance, 
                          lieu = :lieu,
                          statut = 'Programmée'";
            
            // Ajouter le jury si spécifié
            if ($juryId !== null) {
                $query .= ", jury_id = :juryId";
            }
            
            $query .= " WHERE idsoutenance = :idSoutenance";
            
            $stmt = $db->prepare($query);
            $params = [
                'dateSoutenance' => $dateSoutenance,
                'lieu' => $lieuSoutenance,
                'idSoutenance' => $soutenanceId
            ];
            
            if ($juryId !== null) {
                $params['juryId'] = $juryId;
            }
            
            $stmt->execute($params);
        }

        // Valider la transaction
        $db->commit();

        // Enregistrer l'action dans les logs
        error_log("Lecteurs assignés pour la soutenance $soutenanceId par l'utilisateur {$_SESSION['id']}");

        echo json_encode([
            'success' => true,
            'message' => 'Les lecteurs ont été assignés et la soutenance a été programmée avec succès'
        ]);
    } catch (Exception $e) {
        // Annuler la transaction
        $db->rollBack();
        throw $e;
    }
} catch (Exception $e) {
    error_log("Erreur lors de l'assignation des lecteurs: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erreur lors du traitement: ' . $e->getMessage()
    ]);
}
