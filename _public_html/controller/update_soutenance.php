<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

// Vérification de la session
if (empty($_SESSION['id']) || empty($_SESSION['idRole'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Session non valide'
    ]);
    exit;
}

require_once __DIR__ . '/../config/Connexion.php';
require_once __DIR__ . '/../models/Soutenance.php';

// Vérification des droits d'accès
$hasFullAccess = $_SESSION['idRole'] == 1; // Administrateur

try {
    $connexion = Connexion::getInstance()->getPDO();
} catch (Exception $e) {
    error_log("Erreur de connexion DB: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Erreur de connexion à la base de données'
    ]);
    exit;
}

// Récupérer les responsabilités de l'utilisateur si pas admin
$userResponsibilities = [];
if (!$hasFullAccess) {
    try {
        $query = "SELECT DISTINCT section_idsection FROM responsable_section 
                  WHERE idUser = ?";
        $stmt = $connexion->prepare($query);
        $stmt->execute([$_SESSION['id']]);
        $userResponsibilities = $stmt->fetchAll(PDO::FETCH_COLUMN);
    } catch (Exception $e) {
        error_log("Erreur lors de la récupération des responsabilités: " . $e->getMessage());
    }
}

// Si l'utilisateur n'est pas admin et n'a aucune responsabilité, refuser l'accès
if (!$hasFullAccess && empty($userResponsibilities)) {
    echo json_encode([
        'success' => false,
        'message' => 'Accès refusé'
    ]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        'success' => false,
        'message' => 'Méthode non autorisée'
    ]);
    exit;
}

try {
    $soutenance_id = isset($_POST['soutenance_id']) ? intval($_POST['soutenance_id']) : 0;
    $date_soutenance = isset($_POST['date_soutenance']) ? $_POST['date_soutenance'] : null;
    $lieu_soutenance = isset($_POST['lieu_soutenance']) ? $_POST['lieu_soutenance'] : null;
    $statut = isset($_POST['statut']) ? $_POST['statut'] : 'Non programmée';
    $lecteur1 = isset($_POST['lecteur1']) ? intval($_POST['lecteur1']) : null;
    $lecteur2 = isset($_POST['lecteur2']) ? intval($_POST['lecteur2']) : null;

    if (!$soutenance_id) {
        throw new Exception('ID de soutenance invalide');
    }

    if (!$date_soutenance || !$lieu_soutenance) {
        throw new Exception('Date et lieu sont obligatoires');
    }

    // Convertir le format datetime-local en format MySQL
    $date = DateTime::createFromFormat('Y-m-d\TH:i', $date_soutenance);
    if (!$date) {
        throw new Exception('Format de date invalide');
    }
    $date_mysql = $date->format('Y-m-d H:i:00');

    // Récupérer les données actuelles et vérifier les droits d'accès
    if ($hasFullAccess) {
        // Admin - peut accéder à toutes les soutenances
        $query = "SELECT * FROM soutenance WHERE idsoutenance = ?";
        $stmt = $connexion->prepare($query);
        $stmt->execute([$soutenance_id]);
        $soutenance = $stmt->fetch(PDO::FETCH_ASSOC);
    } else {
        // Responsable de section - vérifier que la soutenance appartient à ses sections
        $sectionPlaceholders = [];
        foreach ($userResponsibilities as $index => $sectionId) {
            $paramName = ":section_" . $index;
            $sectionPlaceholders[] = $paramName;
        }
        
        $query = "SELECT s.* FROM soutenance s
                  JOIN sujets sj ON s.sujets_idsujets = sj.idsujets
                  JOIN specialisation sp ON sj.idSpecialisation = sp.idSpecialisation
                  JOIN orientation o ON sp.idorientation = o.idorientation
                  JOIN section sec ON o.section_idsection = sec.idsection
                  WHERE s.idsoutenance = ? 
                  AND sec.idsection IN (" . implode(',', $sectionPlaceholders) . ")";
        
        $stmt = $connexion->prepare($query);
        $executeParams = [$soutenance_id];
        foreach ($userResponsibilities as $index => $sectionId) {
            $executeParams[] = $sectionId;
        }
        $stmt->execute($executeParams);
        $soutenance = $stmt->fetch(PDO::FETCH_ASSOC);
    }

    if (!$soutenance) {
        throw new Exception('Soutenance non trouvée ou accès refusé');
    }

    // Mettre à jour les données
    $query = "UPDATE soutenance 
              SET date_soutenance = ?, 
                  lieu = ?, 
                  statut = ?
              WHERE idsoutenance = ?";

    $stmt = $connexion->prepare($query);
    $result = $stmt->execute([
        $date_mysql,
        $lieu_soutenance,
        $statut,
        $soutenance_id
    ]);

    if (!$result) {
        throw new Exception('Erreur lors de la mise à jour de la soutenance');
    }

    // Mettre à jour les lecteurs si fournis
    if ($lecteur1 !== null || $lecteur2 !== null) {
        // Supprimer les lecteurs existants
        $query = "DELETE FROM lecteurs_soutenance WHERE idsoutenance = ?";
        $stmt = $connexion->prepare($query);
        $stmt->execute([$soutenance_id]);

        // Ajouter les nouveaux lecteurs
        if ($lecteur1 !== null) {
            $query = "INSERT INTO lecteurs_soutenance (idsoutenance, idenseignant, est_premier_lecteur) 
                      VALUES (?, ?, 1)";
            $stmt = $connexion->prepare($query);
            $stmt->execute([$soutenance_id, $lecteur1]);
        }

        if ($lecteur2 !== null) {
            $query = "INSERT INTO lecteurs_soutenance (idsoutenance, idenseignant, est_premier_lecteur) 
                      VALUES (?, ?, 0)";
            $stmt = $connexion->prepare($query);
            $stmt->execute([$soutenance_id, $lecteur2]);
        }
    }

    echo json_encode([
        'success' => true,
        'message' => 'La soutenance a été mise à jour avec succès'
    ]);

} catch (Exception $e) {
    error_log("Erreur update_soutenance: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
